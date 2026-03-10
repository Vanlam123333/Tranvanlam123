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
<html lang="vi" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body { background: #07070e; overflow-x: hidden; }
.login-scene {
  min-height: 100vh; display: flex;
  position: relative; overflow: hidden;
}
/* Animated gradient blobs */
.blob {
  position: fixed; border-radius: 50%; filter: blur(80px);
  opacity: 0.35; pointer-events: none; animation: blobMove 8s ease-in-out infinite;
}
.blob1 { width: 500px; height: 500px; top: -150px; left: -150px; background: radial-gradient(circle, #4f46e5, transparent); animation-delay: 0s; }
.blob2 { width: 400px; height: 400px; bottom: -100px; right: -100px; background: radial-gradient(circle, #7c3aed, transparent); animation-delay: -3s; }
.blob3 { width: 300px; height: 300px; top: 50%; left: 50%; background: radial-gradient(circle, #0891b2, transparent); animation-delay: -5s; }
@keyframes blobMove {
  0%,100% { transform: translate(0, 0) scale(1); }
  33% { transform: translate(30px, -20px) scale(1.05); }
  66% { transform: translate(-20px, 30px) scale(0.95); }
}

/* Left panel */
.login-left {
  flex: 1; display: none; align-items: center; justify-content: center;
  padding: 3rem; flex-direction: column; gap: 2rem;
  position: relative; z-index: 1;
}
.login-hero-text { text-align: center; }
.login-hero-title {
  font-family: 'Be Vietnam Pro', sans-serif; font-size: 3rem; font-weight: 800;
  color: #fff; letter-spacing: -1.5px; line-height: 1.05;
  margin-bottom: 1rem;
}
.login-hero-sub { font-size: 1rem; color: rgba(255,255,255,0.5); line-height: 1.6; max-width: 320px; }
.feature-pills { display: flex; flex-direction: column; gap: 10px; margin-top: 1rem; }
.feature-pill {
  display: flex; align-items: center; gap: 12px;
  background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 50px; padding: 10px 18px;
  font-size: 13px; color: rgba(255,255,255,0.7); font-weight: 500;
  backdrop-filter: blur(10px); transition: all 0.2s;
}
.feature-pill:hover { background: rgba(255,255,255,0.08); color: #fff; }
.feature-pill span { font-size: 18px; }

/* Right panel */
.login-right {
  display: flex; align-items: center; justify-content: center;
  padding: 1.5rem; min-height: 100vh; position: relative; z-index: 1;
  width: 100%;
}
.login-box {
  background: rgba(17,17,32,0.85); border: 1px solid rgba(255,255,255,0.06);
  border-radius: 28px; padding: 2.5rem 2.2rem;
  width: 100%; max-width: 420px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.03);
  backdrop-filter: blur(20px);
  animation: authIn 0.5s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes authIn { from { opacity: 0; transform: translateY(16px) scale(0.96); } to { opacity: 1; transform: none; } }

.login-logo {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 1.5rem; text-decoration: none;
}
.login-logo-mark {
  width: 44px; height: 44px; border-radius: 13px;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 20px rgba(79,70,229,0.4);
}
.login-logo-mark svg { width: 26px; height: 26px; }
.login-logo-text { font-family: 'Be Vietnam Pro', sans-serif; font-size: 1.4rem; font-weight: 800; color: #fff; letter-spacing: -0.5px; }
.login-logo-text em { font-style: normal; background: linear-gradient(90deg, #6366f1, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

.login-welcome { font-size: 22px; font-family: 'Be Vietnam Pro', sans-serif; font-weight: 800; color: #fff; margin-bottom: 4px; letter-spacing: -0.5px; }
.login-sub { font-size: 13px; color: rgba(255,255,255,0.45); margin-bottom: 1.8rem; }

.login-input-wrap { position: relative; margin-bottom: 12px; }
.login-input-icon {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  font-size: 16px; pointer-events: none;
}
.login-input {
  width: 100%; padding: 12px 14px 12px 40px;
  border: 1.5px solid rgba(255,255,255,0.07);
  border-radius: 12px; background: rgba(255,255,255,0.04);
  color: #fff; font-family: 'Be Vietnam Pro', sans-serif; font-size: 14px;
  outline: none; transition: all 0.2s;
}
.login-input:focus { border-color: #6366f1; background: rgba(99,102,241,0.06); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.login-input::placeholder { color: rgba(255,255,255,0.25); }

.login-btn {
  width: 100%; padding: 13px; border-radius: 12px;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: #fff; border: none; font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 15px; font-weight: 700; cursor: pointer;
  transition: all 0.2s; margin-top: 8px;
  box-shadow: 0 4px 20px rgba(79,70,229,0.3);
  letter-spacing: -0.2px;
}
.login-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 28px rgba(79,70,229,0.4); }
.login-btn:active { transform: translateY(0); }

.login-error {
  background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2);
  border-radius: 10px; padding: 10px 14px; color: #f87171;
  font-size: 13px; font-weight: 500; margin-bottom: 14px;
}
.login-footer { text-align: center; font-size: 13px; color: rgba(255,255,255,0.3); margin-top: 1.2rem; }
.login-footer a { color: #818cf8; text-decoration: none; font-weight: 600; }
.login-footer a:hover { color: #a5b4fc; }

.login-divider { display: flex; align-items: center; gap: 12px; margin: 16px 0; }
.login-divider::before, .login-divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.07); }
.login-divider span { font-size: 11px; color: rgba(255,255,255,0.25); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

.guest-features { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
.guest-feat { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 10px 12px; display: flex; align-items: center; gap: 8px; }
.guest-feat-ico { font-size: 18px; }
.guest-feat-lbl { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.4); line-height: 1.2; }

@media(min-width:900px) {
  .login-left { display: flex; max-width: 480px; }
  .login-right { width: auto; min-width: 480px; }
}
</style>
</head>
<body>
<div class="blob blob1"></div>
<div class="blob blob2"></div>
<div class="blob blob3"></div>

<div class="login-scene">
  <!-- Left Panel -->
  <div class="login-left">
    <div class="login-hero-text">
      <div class="login-hero-title">Học thông minh<br>hơn mỗi ngày.</div>
      <div class="login-hero-sub">MindSpark kết hợp AI tiên tiến với công cụ học tập hiệu quả để giúp bạn đạt kết quả tốt nhất.</div>
    </div>
    <div class="feature-pills">
      <div class="feature-pill"><img src="https://api.iconify.design/ph/brain-bold.svg" style="width:20px;height:20px;filter:invert(1)"> Gia sư AI giải thích mọi môn học</div>
      <div class="feature-pill"><img src="https://api.iconify.design/ph/lightning-bold.svg" style="width:20px;height:20px;filter:invert(1)"> Flashcard thông minh với spaced repetition</div>
      <div class="feature-pill"><img src="https://api.iconify.design/ph/map-trifold-bold.svg" style="width:20px;height:20px;filter:invert(1)"> Mind Map trực quan bằng AI</div>
      <div class="feature-pill"><img src="https://api.iconify.design/ph/timer-bold.svg" style="width:20px;height:20px;filter:invert(1)"> Pomodoro + Deep Focus cho năng suất cao</div>
      <div class="feature-pill"><img src="https://api.iconify.design/ph/calculator-bold.svg" style="width:20px;height:20px;filter:invert(1)"> Giải toán từng bước với LaTeX</div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="login-right">
    <div class="login-box">
      <a href="#" class="login-logo">
        <div class="login-logo-mark">
          <svg viewBox="0 0 28 28" fill="none">
            <circle cx="14" cy="14" r="3.5" fill="#fff" opacity=".95"/>
            <line x1="14" y1="3" x2="14" y2="9.5" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
            <line x1="14" y1="18.5" x2="14" y2="25" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
            <line x1="3" y1="14" x2="9.5" y2="14" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
            <line x1="18.5" y1="14" x2="25" y2="14" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
            <line x1="6.5" y1="6.5" x2="10.8" y2="10.8" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".4"/>
            <line x1="17.2" y1="17.2" x2="21.5" y2="21.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".4"/>
            <line x1="21.5" y1="6.5" x2="17.2" y2="10.8" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".4"/>
            <line x1="10.8" y1="17.2" x2="6.5" y2="21.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".4"/>
          </svg>
        </div>
        <span class="login-logo-text">Mind<em>Spark</em></span>
      </a>

      <div class="login-welcome">Chào mừng trở lại!</div>
      <div class="login-sub">Đăng nhập để tiếp tục hành trình học tập của bạn.</div>

      <?php if ($error): ?>
      <div class="login-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="login-input-wrap">
          <img src="https://api.iconify.design/ph/envelope-bold.svg" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;filter:invert(0.5)">
          <input type="email" name="email" class="login-input" placeholder="Email của bạn" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="login-input-wrap">
          <img src="https://api.iconify.design/ph/lock-bold.svg" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;filter:invert(0.5)">
          <input type="password" name="password" class="login-input" placeholder="Mật khẩu" required>
        </div>
        <button type="submit" class="login-btn">Đăng nhập →</button>
      </form>

      <div class="login-footer">Chưa có tài khoản? <a href="register.php">Đăng ký miễn phí</a></div>
    </div>
  </div>
</div>

<script>
(function(){
  const t = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
</body>
</html>
