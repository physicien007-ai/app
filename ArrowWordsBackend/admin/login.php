<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/../db.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :u');
    $stmt->execute([':u' => trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        header('Location: index.php');
        exit;
    }
    $error = 'بيانات الدخول غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول — كلمات مسهمة</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="wrap" style="max-width:360px; padding-top:60px;">
  <div class="card">
    <h1>لوحة تحكم كلمات مسهمة</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label>اسم المستخدم</label>
      <input name="username" required autofocus>
      <label>كلمة المرور</label>
      <input type="password" name="password" required>
      <button class="btn" style="width:100%; margin-top:16px;" type="submit">دخول</button>
    </form>
  </div>
</div>
</body>
</html>
