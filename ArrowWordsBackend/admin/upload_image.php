<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'لم يتم استلام أي ملف']);
    exit;
}

$file = $_FILES['image'];
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = mime_content_type($file['tmp_name']);

if (!isset($allowed[$mime])) {
    echo json_encode(['ok' => false, 'error' => 'نوع ملف غير مدعوم — يُسمح فقط بـ JPG وPNG وWEBP']);
    exit;
}
if ($file['size'] > 8 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'الملف كبير جداً (الحد 8MB)']);
    exit;
}

$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$name = 'ref_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
$dest = $uploadsDir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok' => false, 'error' => 'فشل حفظ الملف على الخادم']);
    exit;
}

echo json_encode(['ok' => true, 'url' => 'uploads/' . $name], JSON_UNESCAPED_UNICODE);
