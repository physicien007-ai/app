<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../db.php';
require __DIR__ . '/../lib/Validator.php';

$packId = $_POST['pack_id'] ?? '';
$originalId = $_POST['original_id'] ?? '';
$id = trim($_POST['id'] ?? '');
$title = trim($_POST['title'] ?? '');
$rows = (int)($_POST['rows'] ?? 0);
$cols = (int)($_POST['cols'] ?? 0);
$words = json_decode($_POST['words_json'] ?? '[]', true);

if ($packId === '' || $id === '' || $title === '' || !is_array($words)) {
    header('Location: puzzle_form.php?pack_id=' . urlencode($packId) . '&id=' . urlencode($originalId) . '&error=' . urlencode('بيانات ناقصة'));
    exit;
}
if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $id)) {
    header('Location: puzzle_form.php?pack_id=' . urlencode($packId) . '&id=' . urlencode($originalId) . '&error=' . urlencode('معرف اللغز يجب أن يحتوي أحرف/أرقام/شرطات فقط'));
    exit;
}

$puzzle = ['id' => $id, 'title' => $title, 'rows' => $rows, 'cols' => $cols, 'words' => $words];

try {
    Validator::buildGrid($puzzle); // never trust the client — re-validate before writing to DB
} catch (ValidationException $e) {
    header('Location: puzzle_form.php?pack_id=' . urlencode($packId) . '&id=' . urlencode($originalId ?: $id) . '&error=' . urlencode($e->getMessage()));
    exit;
}

$wordsJson = json_encode($words, JSON_UNESCAPED_UNICODE);

try {
    $pdo->beginTransaction();

    if ($originalId !== '' && $originalId !== $id) {
        // puzzle id was changed — move it rather than leaving an orphan row
        $pdo->prepare('DELETE FROM puzzles WHERE id = :id AND pack_id = :pack_id')
            ->execute([':id' => $originalId, ':pack_id' => $packId]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO puzzles (id, pack_id, title, rows, cols, words_json)
         VALUES (:id, :pack_id, :title, :rows, :cols, :words_json)
         ON DUPLICATE KEY UPDATE title = VALUES(title), rows = VALUES(rows), cols = VALUES(cols), words_json = VALUES(words_json)'
    );
    $stmt->execute([
        ':id' => $id, ':pack_id' => $packId, ':title' => $title,
        ':rows' => $rows, ':cols' => $cols, ':words_json' => $wordsJson,
    ]);

    // This is the entire mechanism that lets the app pick up the change:
    // bump the parent pack's version + updated_at on every save.
    $now = (int)round(microtime(true) * 1000);
    $pdo->prepare('UPDATE packs SET version = version + 1, updated_at = :now WHERE id = :pack_id')
        ->execute([':now' => $now, ':pack_id' => $packId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    header('Location: puzzle_form.php?pack_id=' . urlencode($packId) . '&id=' . urlencode($originalId ?: $id) . '&error=' . urlencode('خطأ في قاعدة البيانات: ' . $e->getMessage()));
    exit;
}

header('Location: pack_form.php?id=' . urlencode($packId));
exit;
