<?php
declare(strict_types=1);

require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$id = $_GET['id'] ?? '';
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing id']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM packs WHERE id = :id');
$stmt->execute([':id' => $id]);
$pack = $stmt->fetch();

if (!$pack) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

$stmt2 = $pdo->prepare('SELECT * FROM puzzles WHERE pack_id = :id');
$stmt2->execute([':id' => $id]);
$puzzles = $stmt2->fetchAll();

$out = [
    'id'        => $pack['id'],
    'title'     => $pack['title'],
    'version'   => (int)$pack['version'],
    'updatedAt' => (int)$pack['updated_at'],
    'puzzles'   => array_map(static function (array $pz): array {
        return [
            'id'    => $pz['id'],
            'title' => $pz['title'],
            'rows'  => (int)$pz['rows'],
            'cols'  => (int)$pz['cols'],
            'words' => json_decode($pz['words_json'], true),
        ];
    }, $puzzles),
];

echo json_encode($out, JSON_UNESCAPED_UNICODE);
