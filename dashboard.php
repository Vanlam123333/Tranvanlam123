<?php
require_once __DIR__ . "/db.php";
requireLogin();
$user = getCurrentUser();
$uid = $_SESSION['user_id'];

// Stats
$quiz_stats = $db->query("SELECT COUNT(*) as total, SUM(score) as pts, SUM(total) as all_q FROM quiz_results WHERE user_id=$uid")->fetchArray(SQLITE3_ASSOC);
$notes_count = $db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid")->fetchArray()['c'];
$today = date('Y-m-d');
$done_today = $db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$today' AND done=1")->fetchArray()['c'];
$total_today = $db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$today'")->fetchArray()['c'];
$accuracy = ($quiz_stats['all_q'] > 0) ? round($quiz_stats['pts'] / $quiz_stats['all_q'] * 100) : 0;

// Recent quiz
$recent_quiz = $db->query("SELECT * FROM quiz_results WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");

// Today tasks
$today_tasks = $db->query("SELECT * FROM plans WHERE user_id=$uid AND date='$today' ORDER BY done ASC, created_at DESC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — MindSpark</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php
require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <div style="margin-bottom:1.5rem;">
    <div style="font-family:var(--font-head);font-size:0.82rem;color:var(--muted);margin-bottom:0.3rem;"><?= date('l, d/m/Y') ?></div>
    <h1 class="page-title" style="margin-bottom:0;">Xin chào, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>! 👋</h1>
  </div>

  <!-- STATS -->
  <div class="grid-3" style="margin-bottom:1.5rem;">
    <div class="stat-card">
      <div class="stat-num"><?= $quiz_stats['total'] ?: 0 ?></div>
      <div class="stat-label">Bài quiz đã làm</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $accuracy ?>%</div>
      <div class="stat-label">Độ chính xác</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $notes_count ?></div>
      <div class="stat-label">Ghi chú đã tạo</div>
    </div>
  </div>

  <div class="grid-2">
    <!-- TODAY PLAN -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">📅 Kế hoạch hôm nay</div>
        <a href="planner.php" class="btn btn-ghost btn-sm">Xem thêm</a>
      </div>
      <div class="card-body">
        <?php
require_once __DIR__ . "/db.php"; if ($total_today > 0): ?>
          <div style="margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
              <span style="font-size:0.82rem;color:var(--muted);font-family:var(--font-head);font-weight:700;">TIẾN ĐỘ</span>
              <span style="font-size:0.82rem;font-family:var(--font-head);font-weight:700;color:var(--green);"><?= $done_today ?>/<?= $total_today ?></span>
            </div>
            <div class="progress-wrap"><div class="progress-fill" style="width:<?= $total_today > 0 ? round($done_today/$total_today*100) : 0 ?>%;background:var(--green);"></div></div>
          </div>
          <?php
require_once __DIR__ . "/db.php"; while ($t = $today_tasks->fetchArray(SQLITE3_ASSOC)): ?>
          <div class="todo-item">
            <div class="todo-check <?= $t['done']?'done':'' ?>">
              <?= $t['done'] ? '✓' : '' ?>
            </div>
            <div>
              <div class="todo-text <?= $t['done']?'done':'' ?>"><?= htmlspecialchars($t['task']) ?></div>
              <div style="font-size:0.76rem;color:var(--muted);"><?= htmlspecialchars($t['subject']) ?></div>
            </div>
          </div>
          <?php
require_once __DIR__ . "/db.php"; endwhile; ?>
        <?php
require_once __DIR__ . "/db.php"; else: ?>
          <div style="text-align:center;padding:1.5rem;color:var(--muted);">
            <div style="font-size:2rem;margin-bottom:0.5rem;">📋</div>
            Chưa có kế hoạch hôm nay<br>
            <a href="planner.php" class="btn btn-primary btn-sm" style="margin-top:0.75rem;display:inline-flex;">+ Tạo kế hoạch</a>
          </div>
        <?php
require_once __DIR__ . "/db.php"; endif; ?>
      </div>
    </div>

    <!-- RECENT QUIZ -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">🎯 Quiz gần đây</div>
        <a href="quiz.php" class="btn btn-ghost btn-sm">Làm quiz</a>
      </div>
      <div class="card-body" style="padding:0;">
        <?php
require_once __DIR__ . "/db.php"; $rows = []; while ($r = $recent_quiz->fetchArray(SQLITE3_ASSOC)) $rows[] = $r; ?>
        <?php
require_once __DIR__ . "/db.php"; if (count($rows)): ?>
        <table class="table">
          <tr><th>Chủ đề</th><th>Điểm</th><th>Thời gian</th></tr>
          <?php
require_once __DIR__ . "/db.php"; foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['topic']) ?></td>
            <td><span class="badge <?= ($r['score']/$r['total'])>=0.7?'badge-green':'badge-red' ?>"><?= $r['score'] ?>/<?= $r['total'] ?></span></td>
            <td style="color:var(--muted);font-size:0.8rem;"><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
          </tr>
          <?php
require_once __DIR__ . "/db.php"; endforeach; ?>
        </table>
        <?php
require_once __DIR__ . "/db.php"; else: ?>
        <div style="text-align:center;padding:2rem;color:var(--muted);">
          Chưa có kết quả quiz nào<br>
          <a href="quiz.php" class="btn btn-primary btn-sm" style="margin-top:0.75rem;display:inline-flex;">Làm quiz ngay</a>
        </div>
        <?php
require_once __DIR__ . "/db.php"; endif; ?>
      </div>
    </div>
  </div>

  <!-- QUICK ACCESS -->
  <div class="card">
    <div class="card-header"><div class="card-title">⚡ Truy cập nhanh</div></div>
    <div class="card-body">
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <a href="chat.php" class="btn btn-ghost">🧠 Hỏi gia sư AI</a>
        <a href="flashcard.php" class="btn btn-ghost">⚡ Ôn flashcard</a>
        <a href="quiz.php" class="btn btn-ghost">🎯 Làm quiz</a>
        <a href="notes.php" class="btn btn-ghost">🗒️ Ghi chú mới</a>
        <a href="planner.php" class="btn btn-ghost">📅 Lên kế hoạch</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
