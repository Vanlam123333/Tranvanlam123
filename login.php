<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email && $password) {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($result && password_verify($password, $result['password'])) {
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['user_name'] = $result['name'];
            header('Location: dashboard.php'); exit;
        } else { $error = 'Email hoặc mật khẩu không đúng!'; }
    } else { $error = 'Vui lòng điền đầy đủ thông tin!'; }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập — MindSpark</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-box">
    <div class="auth-logo">Mind<span>Spark</span></div>
    <div class="auth-sub">Chào mừng trở lại! 👋</div>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" placeholder="email@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Mật khẩu</label>
        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">Đăng nhập →</button>
    </form>
    <div class="auth-footer">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></div>
  </div>
</div>
</body>
</html>
