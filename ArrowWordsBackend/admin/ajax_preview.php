<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../lib/Validator.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['ok' => false, 'error' => 'بيانات غير صالحة']);
    exit;
}

try {
    $grid = Validator::buildGrid($input);
    $out = [];
    foreach ($grid as $row) {
        $outRow = [];
        foreach ($row as $cell) {
            if ($cell['type'] === 'clue') {
                $outRow[] = ['type' => 'clue', 'directions' => array_column($cell['clues'], 'direction')];
            } else {
                $outRow[] = ['type' => 'letter', 'letter' => $cell['letter']];
            }
        }
        $out[] = $outRow;
    }
    echo json_encode(['ok' => true, 'grid' => $out], JSON_UNESCAPED_UNICODE);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'خطأ غير متوقع: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
