<?php
declare(strict_types=1);

require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$since = isset($_GET['since']) ? (int)$_GET['since'] : 0;

$stmt = $pdo->prepare(
    "SELECT p.id, p.title, p.version, p.updated_at,
            (SELECT COUNT(*) FROM puzzles pz WHERE pz.pack_id = p.id) AS puzzle_count
     FROM packs p
     WHERE p.updated_at >= :since
     ORDER BY p.updated_at DESC"
);
$stmt->execute([':since' => $since]);
$rows = $stmt->fetchAll();

$out = array_map(static function (array $r): array {
    return [
        'id'          => $r['id'],
        'title'       => $r['title'],
        'version'     => (int)$r['version'],
        'updatedAt'   => (int)$r['updated_at'],
        'puzzleCount' => (int)$r['puzzle_count'],
    ];
}, $rows);

echo json_encode($out, JSON_UNESCAPED_UNICODE);
