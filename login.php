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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập — MindSpark</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #05050f;
    --card: #0d0d1f;
    --border: rgba(255,255,255,0.07);
    --accent: #5b5ef4;
    --accent2: #a78bfa;
    --text: #f0f0ff;
    --muted: rgba(240,240,255,0.38);
  }

  body {
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    overflow: hidden;
  }

  body::before {
    content: '';
    position: fixed; inset: 0;
    background-image:
      linear-gradient(rgba(91,94,244,0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(91,94,244,0.05) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none; z-index: 0;
  }

  .orb {
    position: fixed; border-radius: 50%;
    filter: blur(100px); pointer-events: none; z-index: 0;
  }
  .orb1 { width: 600px; height: 600px; top: -200px; left: -150px; background: radial-gradient(circle, rgba(91,94,244,0.22), transparent 70%); }
  .orb2 { width: 500px; height: 500px; bottom: -150px; right: -100px; background: radial-gradient(circle, rgba(167,139,250,0.18), transparent 70%); }

  /* LEFT */
  .left {
    flex: 1; display: none;
    flex-direction: column; justify-content: center;
    padding: 5rem 4rem; position: relative; z-index: 1;
  }
  @media(min-width:900px){ .left { display: flex; } }

  .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 4rem; text-decoration: none; }
  .brand-icon {
    width: 46px; height: 46px; border-radius: 14px;
    background: linear-gradient(135deg, #5b5ef4, #a78bfa);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; box-shadow: 0 0 30px rgba(91,94,244,0.4);
  }
  .brand-name { font-family: 'Syne', sans-serif; font-size: 1.5rem; color: var(--text); }
  .brand-name em { font-style: normal; color: var(--accent2); }

  .tag {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(91,94,244,0.1); border: 1px solid rgba(91,94,244,0.22);
    border-radius: 50px; padding: 5px 13px;
    font-size: 11px; font-weight: 700; color: var(--accent2);
    letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 1.4rem;
  }

  .hero-title {
    font-family: 'Syne', sans-serif;
    font-size: clamp(2.8rem, 4vw, 3.8rem);
    color: var(--text); line-height: 1.0;
    letter-spacing: -2px; margin-bottom: 1.2rem;
  }
  .hero-title span { color: var(--accent2); }

  .hero-desc { font-size: 15px; color: var(--muted); line-height: 1.7; max-width: 340px; margin-bottom: 3rem; }

  .features { display: flex; flex-direction: column; gap: 10px; }
  .feat {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 17px;
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border); border-radius: 14px;
    transition: all 0.2s; cursor: default;
  }
  .feat:hover { background: rgba(91,94,244,0.07); border-color: rgba(91,94,244,0.18); transform: translateX(5px); }
  .feat-ico { width: 34px; height: 34px; border-radius: 9px; background: rgba(91,94,244,0.14); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
  .feat-lbl { font-size: 13.5px; color: rgba(240,240,255,0.55); font-weight: 500; }

  /* RIGHT */
  .right {
    display: flex; align-items: center; justify-content: center;
    padding: 2rem; width: 100%; position: relative; z-index: 1;
  }
  @media(min-width:900px){ .right { width: 480px; flex-shrink: 0; } }

  .card {
    width: 100%; max-width: 400px;
    background: var(--card);
    border: 1px solid var(--border); border-radius: 24px;
    padding: 2.5rem 2.2rem;
    box-shadow: 0 32px 80px rgba(0,0,0,0.6);
    animation: up 0.5s cubic-bezier(0.22,1,0.36,1) both;
  }
  @keyframes up { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:none; } }

  .card-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; text-decoration: none; }
  .card-brand-ico { width: 38px; height: 38px; border-radius: 11px; background: linear-gradient(135deg,#5b5ef4,#a78bfa); display: flex; align-items: center; justify-content: center; font-size: 18px; }
  .card-brand-name { font-family: 'Syne', sans-serif; font-size: 1.2rem; color: var(--text); }
  .card-brand-name em { font-style: normal; color: var(--accent2); }

  .card-title { font-family: 'Syne', sans-serif; font-size: 1.7rem; color: var(--text); letter-spacing: -0.8px; margin-bottom: 5px; }
  .card-sub { font-size: 13px; color: var(--muted); margin-bottom: 2rem; }

  .inp-wrap { position: relative; margin-bottom: 12px; }
  .inp-ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; pointer-events: none; }
  .inp {
    width: 100%; padding: 13px 14px 13px 42px;
    background: rgba(255,255,255,0.04);
    border: 1.5px solid var(--border); border-radius: 12px;
    color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px;
    outline: none; transition: all 0.2s;
  }
  .inp::placeholder { color: rgba(240,240,255,0.2); }
  .inp:focus { border-color: var(--accent); background: rgba(91,94,244,0.07); box-shadow: 0 0 0 3px rgba(91,94,244,0.18); }

  .err { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: 10px 14px; color: #f87171; font-size: 13px; margin-bottom: 14px; }

  .btn {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #5b5ef4, #a78bfa);
    border: none; border-radius: 12px;
    color: #fff; font-family: 'Syne', sans-serif;
    font-size: 15px; font-weight: 700;
    cursor: pointer; margin-top: 8px;
    box-shadow: 0 4px 24px rgba(91,94,244,0.35);
    transition: all 0.2s;
  }
  .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(91,94,244,0.45); }
  .btn:active { transform: none; }

  .footer { text-align: center; font-size: 13px; color: var(--muted); margin-top: 1.4rem; }
  .footer a { color: var(--accent2); text-decoration: none; font-weight: 600; }
  .footer a:hover { color: #c4b5fd; }
</style>
</head>
<body>
<div class="orb orb1"></div>
<div class="orb orb2"></div>

<div class="left">
  <a href="#" class="brand">
    <div class="brand-icon">✦</div>
    <span class="brand-name">Mind<em>Spark</em></span>
  </a>
  <div class="tag">✦ Nền tảng học tập AI</div>
  <h1 class="hero-title">Học thông<br>minh hơn<br><span>mỗi ngày.</span></h1>
  <p class="hero-desc">MindSpark kết hợp AI tiên tiến với công cụ học tập hiệu quả để giúp bạn đạt kết quả tốt nhất.</p>
  <div class="features">
    <div class="feat"><div class="feat-ico">🧠</div><div class="feat-lbl">Gia sư AI giải thích mọi môn học</div></div>
    <div class="feat"><div class="feat-ico">⚡</div><div class="feat-lbl">Flashcard thông minh với spaced repetition</div></div>
    <div class="feat"><div class="feat-ico">🗺️</div><div class="feat-lbl">Mind Map trực quan bằng AI</div></div>
    <div class="feat"><div class="feat-ico">🍅</div><div class="feat-lbl">Pomodoro + Deep Focus cho năng suất cao</div></div>
    <div class="feat"><div class="feat-ico">📐</div><div class="feat-lbl">Giải toán từng bước với LaTeX</div></div>
  </div>
</div>

<div class="right">
  <div class="card">
    <a href="#" class="card-brand">
      <div class="card-brand-ico">✦</div>
      <span class="card-brand-name">Mind<em>Spark</em></span>
    </a>
    <div class="card-title">Chào mừng trở lại!</div>
    <div class="card-sub">Đăng nhập để tiếp tục hành trình học tập của bạn.</div>

    <?php if ($error): ?>
    <div class="err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="inp-wrap">
        <span class="inp-ico">✉️</span>
        <input type="email" name="email" class="inp" placeholder="Email của bạn" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="inp-wrap">
        <span class="inp-ico">🔑</span>
        <input type="password" name="password" class="inp" placeholder="Mật khẩu" required>
      </div>
      <button type="submit" class="btn">Đăng nhập →</button>
    </form>

    <div class="footer">Chưa có tài khoản? <a href="register.php">Đăng ký miễn phí</a></div>
  </div>
</div>

</body>
</html>
