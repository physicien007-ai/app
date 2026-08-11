<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../db.php';

$packs = $pdo->query(
    "SELECT p.*, (SELECT COUNT(*) FROM puzzles pz WHERE pz.pack_id = p.id) AS puzzle_count
     FROM packs p ORDER BY updated_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>المجموعات — كلمات مسهمة</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="topbar">
  <div class="brand">كلمات مسهمة — لوحة التحكم</div>
  <nav>
    <span class="muted" style="color:#ccc;"><?= htmlspecialchars(current_admin_username()) ?></span>
    <a href="logout.php">خروج</a>
  </nav>
</div>
<div class="wrap">
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h1 style="margin:0;">المجموعات (Packs)</h1>
      <a class="btn" href="pack_form.php">+ مجموعة جديدة</a>
    </div>
  </div>

  <div class="card">
    <?php if (empty($packs)): ?>
      <p class="muted">لا توجد مجموعات بعد. أنشئ أول مجموعة للبدء بإضافة الألغاز.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>العنوان</th><th>المعرف</th><th>عدد الألغاز</th><th>الإصدار</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($packs as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['title']) ?></td>
            <td><code><?= htmlspecialchars($p['id']) ?></code></td>
            <td><?= (int)$p['puzzle_count'] ?></td>
            <td><?= (int)$p['version'] ?></td>
            <td><a class="btn small" href="pack_form.php?id=<?= urlencode($p['id']) ?>">فتح</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card muted">
    نقطتا الاتصال بالتطبيق: <code>api/packs.php</code> و <code>api/pack.php?id=...</code> — يقرآن مباشرة من هذه القاعدة، لا حاجة لأي إعداد إضافي.
    كل حفظ للغز يرفع رقم إصدار المجموعة تلقائياً، فالتطبيق يكتشف التحديث بنفسه.
  </div>
</div>
</body>
</html>
