<?php
declare(strict_types=1);

/**
 * Usage:  php scripts/import_pack.php path/to/pack.json
 *
 * Reads a pack JSON file (same shape the API returns), validates every
 * puzzle with Validator::validatePuzzle(), and upserts it into MySQL.
 * On re-import of an existing pack id, its puzzles are replaced wholesale
 * with the new file's puzzles — so your workflow is simply: edit the JSON
 * file (bump "version"), re-run this script.
 *
 * Nothing is written to the database if ANY puzzle in the file fails
 * validation — the whole import is rejected so you never publish a broken
 * puzzle.
 */

require __DIR__ . '/../db.php';
require __DIR__ . '/../lib/Validator.php';

$path = $argv[1] ?? null;
if (!$path) {
    fwrite(STDERR, "Usage: php import_pack.php path/to/pack.json\n");
    exit(1);
}
if (!file_exists($path)) {
    fwrite(STDERR, "File not found: $path\n");
    exit(1);
}

$json = json_decode(file_get_contents($path), true);
if ($json === null) {
    fwrite(STDERR, "Invalid JSON in $path\n");
    exit(1);
}

foreach (['id', 'title', 'version', 'puzzles'] as $field) {
    if (!array_key_exists($field, $json)) {
        fwrite(STDERR, "Pack JSON missing required field: $field\n");
        exit(1);
    }
}

foreach ($json['puzzles'] as $puzzle) {
    try {
        Validator::buildGrid($puzzle);
    } catch (ValidationException $e) {
        fwrite(STDERR, "REJECTED — pack '{$json['id']}' puzzle '{$puzzle['id']}': " . $e->getMessage() . "\n");
        exit(1);
    }
}

$now = (int)round(microtime(true) * 1000);

try {
    $pdo->beginTransaction();

    $packStmt = $pdo->prepare(
        'INSERT INTO packs (id, title, version, updated_at, created_at)
         VALUES (:id, :title, :version, :updated_at, :created_at)
         ON DUPLICATE KEY UPDATE title = VALUES(title), version = VALUES(version), updated_at = VALUES(updated_at)'
    );
    $packStmt->execute([
        ':id'         => $json['id'],
        ':title'      => $json['title'],
        ':version'    => $json['version'],
        ':updated_at' => $now,
        ':created_at' => $now,
    ]);

    // Replace this pack's puzzles wholesale with what's in the file.
    $pdo->prepare('DELETE FROM puzzles WHERE pack_id = :id')->execute([':id' => $json['id']]);

    $puzzleStmt = $pdo->prepare(
        'INSERT INTO puzzles (id, pack_id, title, rows, cols, words_json)
         VALUES (:id, :pack_id, :title, :rows, :cols, :words_json)'
    );
    foreach ($json['puzzles'] as $puzzle) {
        $puzzleStmt->execute([
            ':id'         => $puzzle['id'],
            ':pack_id'    => $json['id'],
            ':title'      => $puzzle['title'],
            ':rows'       => $puzzle['rows'],
            ':cols'       => $puzzle['cols'],
            ':words_json' => json_encode($puzzle['words'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    $pdo->commit();
    $count = count($json['puzzles']);
    echo "Imported pack '{$json['id']}' (version {$json['version']}) with $count puzzle(s).\n";
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Database error: ' . $e->getMessage() . "\n");
    exit(1);
}
