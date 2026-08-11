<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../db.php';

$packId = $_GET['pack_id'] ?? '';
$id = $_GET['id'] ?? '';

if ($packId !== '' && $id !== '') {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM puzzles WHERE id = :id AND pack_id = :pack_id')->execute([':id' => $id, ':pack_id' => $packId]);
        $now = (int)round(microtime(true) * 1000);
        $pdo->prepare('UPDATE packs SET version = version + 1, updated_at = :now WHERE id = :pack_id')->execute([':now' => $now, ':pack_id' => $packId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
    }
}

header('Location: pack_form.php?id=' . urlencode($packId));
exit;
