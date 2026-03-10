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
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
.login-logo-text { font-family: 'Be Vietnam Pro', sans-serif; font-size: 1.4rem; font-weight: 800; color: #fff; letter-spacing: -0.5px; }
.login-logo-text em { font-style: normal; background: linear-gradient(90deg, #6366f1, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.login-welcome { font-size: 22px; font-family: 'Be Vietnam Pro', sans-serif; font-weight: 800; color: #fff; margin-bottom: 4px; letter-spacing: -0.5px; }
.login-sub { font-size: 13px; color: rgba(255,255,255,0.4); margin-bottom: 1.8rem; }
.login-input-wrap { position: relative; margin-bottom: 10px; }
.login-input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); font-size: 16px; pointer-events: none; }
.login-input { width: 100%; padding: 12px 14px 12px 40px; border: 1.5px solid rgba(255,255,255,0.07); border-radius: 12px; background: rgba(255,255,255,0.04); color: #fff; font-family: 'Be Vietnam Pro', sans-serif; font-size: 14px; outline: none; transition: all 0.2s; }
.login-input:focus { border-color: #6366f1; background: rgba(99,102,241,0.06); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.login-input::placeholder { color: rgba(255,255,255,0.25); }
.login-btn { width: 100%; padding: 13px; border-radius: 12px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; border: none; font-family: 'Be Vietnam Pro', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 8px; box-shadow: 0 4px 20px rgba(79,70,229,0.3); }
.login-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 28px rgba(79,70,229,0.4); }
.login-error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); border-radius: 10px; padding: 10px 14px; color: #f87171; font-size: 13px; margin-bottom: 14px; }
.login-success { display:none; }
/* Success overlay */
.success-overlay {
  display: none;
  position: fixed; inset: 0; z-index: 999;
  background: rgba(5,5,15,0.85);
  backdrop-filter: blur(10px);
  align-items: center; justify-content: center;
  flex-direction: column; gap: 20px;
}
.success-overlay.show { display: flex; animation: fadeIn .2s cubic-bezier(0.22,1,0.36,1); }
@keyframes fadeIn { from { opacity:0; backdrop-filter:blur(0px); } to { opacity:1; backdrop-filter:blur(10px); } }

.success-spinner {
  width: 64px; height: 64px;
  border-radius: 50%;
  position: relative;
  animation: spin 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
.success-spinner::before {
  content: '';
  position: absolute; inset: 0;
  border-radius: 50%;
  border: 3px solid transparent;
  border-top-color: #6366f1;
  border-right-color: rgba(99,102,241,0.4);
  filter: drop-shadow(0 0 8px rgba(99,102,241,0.6));
}
.success-spinner::after {
  content: '';
  position: absolute; inset: 6px;
  border-radius: 50%;
  border: 2px solid transparent;
  border-bottom-color: #818cf8;
  animation: spinReverse 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  opacity: 0.6;
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes spinReverse { to { transform: rotate(-360deg); } }

.success-check {
  display: none;
  width: 64px; height: 64px; border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #059669);
  align-items: center; justify-content: center;
  box-shadow: 0 0 40px rgba(16,185,129,0.5);
  animation: popIn .5s cubic-bezier(0.34,1.56,0.64,1);
}
.success-check.show { display: flex; }
@keyframes popIn { from { opacity:0; transform:scale(.3) rotate(-15deg); } to { opacity:1; transform:scale(1) rotate(0deg); } }
.success-check svg { width:32px; height:32px; stroke:#fff; fill:none; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; }

.success-text {
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 18px; font-weight: 700;
  color: #fff; letter-spacing: -0.3px;
  text-align: center;
}
.success-sub {
  font-size: 13px; color: rgba(255,255,255,0.45);
}
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
    <?php if ($error): ?><div class="login-error"> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
    <script>window.addEventListener('DOMContentLoaded',()=>showSuccess())</script>
    <?php endif; ?>
    <form method="POST">
      <div class="login-input-wrap">
        <img src="https://api.iconify.design/ph/user-bold.svg" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;filter:invert(0.5);pointer-events:none">
        <input type="text" name="name" class="login-input" placeholder="Họ và tên của bạn" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="login-input-wrap">
        <img src="https://api.iconify.design/ph/envelope-bold.svg" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;filter:invert(0.5);pointer-events:none">
        <input type="email" name="email" class="login-input" placeholder="Email của bạn" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="login-input-wrap">
        <img src="https://api.iconify.design/ph/lock-bold.svg" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;filter:invert(0.5);pointer-events:none">
        <input type="password" name="password" class="login-input" placeholder="Mật khẩu (ít nhất 6 ký tự)" required>
      </div>
      <div class="login-input-wrap">
        <span class="login-input-icon"></span>
        <input type="password" name="confirm" class="login-input" placeholder="Xác nhận mật khẩu" required>
      </div>
      <button type="submit" class="login-btn">Tạo tài khoản →</button>
    </form>
    <div class="login-footer">Đã có tài khoản? <a href="login.php">Đăng nhập</a></div>
  </div>
</div>
<script>(function(){ const t=localStorage.getItem('theme')||'dark'; document.documentElement.setAttribute('data-theme',t); })();</script>
<!-- Success Overlay -->
<div class="success-overlay" id="successOverlay">
  <div class="success-spinner" id="spinner"></div>
  <div class="success-check" id="checkIcon">
    <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
  </div>
  <div class="success-text" id="successText">Đang tạo tài khoản...</div>
  <div class="success-sub" id="successSub">Vui lòng chờ một chút</div>
</div>

<script>
function showSuccess() {
  const overlay = document.getElementById('successOverlay');
  const spinner = document.getElementById('spinner');
  const check   = document.getElementById('checkIcon');
  const text    = document.getElementById('successText');
  const sub     = document.getElementById('successSub');

  overlay.classList.add('show');

  // Sau 800ms: ẩn spinner, hiện check + đổi text
  setTimeout(() => {
    spinner.style.display = 'none';
    check.classList.add('show');
    text.textContent = 'Đăng ký thành công!';
    sub.textContent  = 'Đang chuyển hướng...';
  }, 600);

  // Sau 1.6s: chuyển trang
  setTimeout(() => {
    window.location.href = 'dashboard.php';
  }, 1400);
}
</script>
</body>
</html>
