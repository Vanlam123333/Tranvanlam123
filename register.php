<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'db.php';

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (!$name || !$email || !$password) { $error = 'Vui lòng điền đầy đủ!'; }
    elseif ($password !== $confirm) { $error = 'Mật khẩu xác nhận không khớp!'; }
    elseif (strlen($password) < 6) { $error = 'Mật khẩu phải ít nhất 6 ký tự!'; }
    else {
        $check = $db->prepare('SELECT id FROM users WHERE email = :email');
        $check->bindValue(':email', $email);
        if ($check->execute()->fetchArray()) { $error = 'Email đã được sử dụng!'; }
        else {
            $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)');
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT));
            $stmt->execute();
            $_SESSION['user_id'] = $db->lastInsertRowID();
            $_SESSION['user_name'] = $name;
            header('Location: dashboard.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng ký — MindSpark</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-box">
    <div class="auth-logo">Mind<span>Spark</span></div>
    <div class="auth-sub">Tạo tài khoản miễn phí ✨</div>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Họ tên</label>
        <input type="text" name="name" class="form-input" placeholder="Nguyễn Văn A" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" placeholder="email@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Mật khẩu</label>
        <input type="password" name="password" class="form-input" placeholder="Ít nhất 6 ký tự" required>
      </div>
      <div class="form-group">
        <label class="form-label">Xác nhận mật khẩu</label>
        <input type="password" name="confirm" class="form-input" placeholder="Nhập lại mật khẩu" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">Tạo tài khoản →</button>
    </form>
    <div class="auth-footer">Đã có tài khoản? <a href="login.php">Đăng nhập</a></div>
  </div>
</div>
</body>
</html>
