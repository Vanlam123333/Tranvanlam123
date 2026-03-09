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
    if (!$name || !$email || !$password) { $error = 'Vui lòng điền đầy đủ thông tin!'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Email không hợp lệ!'; }
    elseif (strlen($password) < 6) { $error = 'Mật khẩu phải có ít nhất 6 ký tự!'; }
    elseif ($password !== $confirm) { $error = 'Mật khẩu xác nhận không khớp!'; }
    else {
        $check = $db->prepare('SELECT id FROM users WHERE email = :email');
        $check->bindValue(':email', $email);
        if ($check->execute()->fetchArray()) { $error = 'Email này đã được đăng ký!'; }
        else {
            $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :pass)');
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':pass', password_hash($password, PASSWORD_DEFAULT));
            $stmt->execute();
            $success = 'Đăng ký thành công! Chuyển hướng...';
            $_SESSION['user_id'] = $db->lastInsertRowID();
            $_SESSION['user_name'] = $name;
            header('refresh:1;url=dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng ký — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
body { background: #07070e; }
.blob { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.3; pointer-events: none; animation: blobMove 8s ease-in-out infinite; }
.blob1 { width: 500px; height: 500px; top: -150px; right: -150px; background: radial-gradient(circle, #7c3aed, transparent); }
.blob2 { width: 400px; height: 400px; bottom: -100px; left: -100px; background: radial-gradient(circle, #4f46e5, transparent); animation-delay: -4s; }
@keyframes blobMove { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(20px,-20px) scale(1.04); } }
.auth-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem; position: relative; z-index: 1; }
.login-box { background: rgba(17,17,32,0.85); border: 1px solid rgba(255,255,255,0.06); border-radius: 28px; padding: 2.5rem 2.2rem; width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); backdrop-filter: blur(20px); animation: authIn 0.5s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes authIn { from { opacity: 0; transform: translateY(16px) scale(0.96); } to { opacity: 1; transform: none; } }
.login-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem; text-decoration: none; }
.login-logo-mark { width: 44px; height: 44px; border-radius: 13px; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 20px rgba(79,70,229,0.4); }
.login-logo-mark svg { width: 26px; height: 26px; }
.login-logo-text { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800; color: #fff; letter-spacing: -0.5px; }
.login-logo-text em { font-style: normal; background: linear-gradient(90deg, #6366f1, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.login-welcome { font-size: 22px; font-family: 'Syne', sans-serif; font-weight: 800; color: #fff; margin-bottom: 4px; letter-spacing: -0.5px; }
.login-sub { font-size: 13px; color: rgba(255,255,255,0.4); margin-bottom: 1.8rem; }
.login-input-wrap { position: relative; margin-bottom: 10px; }
.login-input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); font-size: 16px; pointer-events: none; }
.login-input { width: 100%; padding: 12px 14px 12px 40px; border: 1.5px solid rgba(255,255,255,0.07); border-radius: 12px; background: rgba(255,255,255,0.04); color: #fff; font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; transition: all 0.2s; }
.login-input:focus { border-color: #6366f1; background: rgba(99,102,241,0.06); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.login-input::placeholder { color: rgba(255,255,255,0.25); }
.login-btn { width: 100%; padding: 13px; border-radius: 12px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; border: none; font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 8px; box-shadow: 0 4px 20px rgba(79,70,229,0.3); }
.login-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 28px rgba(79,70,229,0.4); }
.login-error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); border-radius: 10px; padding: 10px 14px; color: #f87171; font-size: 13px; margin-bottom: 14px; }
.login-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; padding: 10px 14px; color: #10b981; font-size: 13px; margin-bottom: 14px; }
.login-footer { text-align: center; font-size: 13px; color: rgba(255,255,255,0.3); margin-top: 1.2rem; }
.login-footer a { color: #818cf8; text-decoration: none; font-weight: 600; }
</style>
</head>
<body>
<div class="blob blob1"></div>
<div class="blob blob2"></div>
<div class="auth-wrap">
  <div class="login-box">
    <a href="login.php" class="login-logo">
      <div class="login-logo-mark">
        <svg viewBox="0 0 28 28" fill="none">
          <circle cx="14" cy="14" r="3.5" fill="#fff" opacity=".95"/>
          <line x1="14" y1="3" x2="14" y2="9.5" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
          <line x1="14" y1="18.5" x2="14" y2="25" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
          <line x1="3" y1="14" x2="9.5" y2="14" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
          <line x1="18.5" y1="14" x2="25" y2="14" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
        </svg>
      </div>
      <span class="login-logo-text">Mind<em>Spark</em></span>
    </a>
    <div class="login-welcome">Tạo tài khoản mới</div>
    <div class="login-sub">Bắt đầu hành trình học tập thông minh ngay hôm nay!</div>
    <?php if ($error): ?><div class="login-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="login-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="POST">
      <div class="login-input-wrap">
        <span class="login-input-icon">👤</span>
        <input type="text" name="name" class="login-input" placeholder="Họ và tên của bạn" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="login-input-wrap">
        <span class="login-input-icon">✉️</span>
        <input type="email" name="email" class="login-input" placeholder="Email của bạn" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="login-input-wrap">
        <span class="login-input-icon">🔑</span>
        <input type="password" name="password" class="login-input" placeholder="Mật khẩu (ít nhất 6 ký tự)" required>
      </div>
      <div class="login-input-wrap">
        <span class="login-input-icon">🔒</span>
        <input type="password" name="confirm" class="login-input" placeholder="Xác nhận mật khẩu" required>
      </div>
      <button type="submit" class="login-btn">Tạo tài khoản →</button>
    </form>
    <div class="login-footer">Đã có tài khoản? <a href="login.php">Đăng nhập</a></div>
  </div>
</div>
<script>(function(){ const t=localStorage.getItem('theme')||'dark'; document.documentElement.setAttribute('data-theme',t); })();</script>
</body>
</html>
