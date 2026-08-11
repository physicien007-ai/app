<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../db.php';

$id = $_GET['id'] ?? null;
$pack = null;
$puzzles = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM packs WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $pack = $stmt->fetch();
    if (!$pack) {
        http_response_code(404);
        die('Pack not found');
    }
    $stmt2 = $pdo->prepare('SELECT id, title, rows, cols FROM puzzles WHERE pack_id = :id ORDER BY id');
    $stmt2->execute([':id' => $id]);
    $puzzles = $stmt2->fetchAll();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $newId = $pack ? $pack['id'] : trim($_POST['id'] ?? '');

    if ($title === '' || $newId === '') {
        $error = 'الرجاء تعبئة جميع الحقول';
    } elseif (!preg_match('/^[a-z0-9\-_]+$/i', $newId)) {
        $error = 'المعرف يجب أن يحتوي أحرف/أرقام/شرطات فقط، بدون مسافات';
    } else {
        if ($pack) {
            $pdo->prepare('UPDATE packs SET title = :t WHERE id = :id')->execute([':t' => $title, ':id' => $pack['id']]);
        } else {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM packs WHERE id = :id');
            $exists->execute([':id' => $newId]);
            if ((int)$exists->fetchColumn() > 0) {
                $error = 'هذا المعرف مستخدم بالفعل';
            } else {
                $now = (int)round(microtime(true) * 1000);
                $pdo->prepare('INSERT INTO packs (id, title, version, updated_at, created_at) VALUES (:id, :t, 1, :now, :now)')
                    ->execute([':id' => $newId, ':t' => $title, ':now' => $now]);
            }
        }
        if (!$error) {
            header('Location: pack_form.php?id=' . urlencode($newId));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pack ? htmlspecialchars($pack['title']) : 'مجموعة جديدة' ?> — كلمات مسهمة</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="topbar">
  <div class="brand">كلمات مسهمة — لوحة التحكم</div>
  <nav><a href="index.php">المجموعات</a><a href="logout.php">خروج</a></nav>
</div>
<div class="wrap">
  <div class="card">
    <h1><?= $pack ? 'تعديل المجموعة' : 'مجموعة جديدة' ?></h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <?php if (!$pack): ?>
        <label>المعرف (id) — يُستخدم في رابط الـ API، بدون مسافات</label>
        <input name="id" value="<?= htmlspecialchars($_POST['id'] ?? '') ?>" required pattern="[a-zA-Z0-9\-_]+">
      <?php else: ?>
        <label>المعرف</label>
        <input value="<?= htmlspecialchars($pack['id']) ?>" disabled>
      <?php endif; ?>
      <label>العنوان</label>
      <input name="title" value="<?= htmlspecialchars($pack['title'] ?? ($_POST['title'] ?? '')) ?>" required>
      <button class="btn" style="margin-top:16px;" type="submit"><?= $pack ? 'حفظ' : 'إنشاء' ?></button>
    </form>
    <?php if ($pack): ?>
      <p class="muted" style="margin-top:14px;">الإصدار الحالي: <b><?= (int)$pack['version'] ?></b> — يرتفع تلقائياً عند حفظ أي لغز داخل هذه المجموعة.</p>
    <?php endif; ?>
  </div>

  <?php if ($pack): ?>
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h2 style="margin:0;">الألغاز في هذه المجموعة</h2>
      <a class="btn" href="puzzle_form.php?pack_id=<?= urlencode($pack['id']) ?>">+ لغز جديد</a>
    </div>
    <?php if (empty($puzzles)): ?>
      <p class="muted">لا توجد ألغاز بعد.</p>
    <?php else: ?>
      <table style="margin-top:12px;">
        <thead><tr><th>العنوان</th><th>المعرف</th><th>الأبعاد</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($puzzles as $pz): ?>
          <tr>
            <td><?= htmlspecialchars($pz['title']) ?></td>
            <td><code><?= htmlspecialchars($pz['id']) ?></code></td>
            <td><?= (int)$pz['cols'] ?> × <?= (int)$pz['rows'] ?></td>
            <td>
              <a class="btn small" href="puzzle_form.php?pack_id=<?= urlencode($pack['id']) ?>&id=<?= urlencode($pz['id']) ?>">تعديل</a>
              <a class="btn small danger" href="puzzle_delete.php?pack_id=<?= urlencode($pack['id']) ?>&id=<?= urlencode($pz['id']) ?>"
                 onclick="return confirm('حذف هذا اللغز نهائياً؟')">حذف</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
