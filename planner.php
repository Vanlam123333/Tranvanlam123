<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');
$date = $_GET['date'] ?? $today;

// Thêm task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $task = trim($_POST['task'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $task_date = $_POST['date'] ?? $today;
        if ($task) {
            $stmt = $db->prepare('INSERT INTO plans (user_id, date, subject, task) VALUES (:uid, :date, :subject, :task)');
            $stmt->bindValue(':uid', $uid); $stmt->bindValue(':date', $task_date);
            $stmt->bindValue(':subject', $subject); $stmt->bindValue(':task', $task);
            $stmt->execute();
        }
    } elseif ($_POST['action'] === 'toggle') {
        $id = (int)$_POST['id'];
        $db->exec("UPDATE plans SET done = 1 - done WHERE id=$id AND user_id=$uid");
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $db->exec("DELETE FROM plans WHERE id=$id AND user_id=$uid");
    }
    header('Location: planner.php?date=' . $date); exit;
}

$tasks = $db->query("SELECT * FROM plans WHERE user_id=$uid AND date='$date' ORDER BY done ASC, created_at DESC");
$done_count = $db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$date' AND done=1")->fetchArray()['c'];
$total_count = $db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$date'")->fetchArray()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kế hoạch học — MindSpark</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php
require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <div class="row center" style="margin-bottom:1.5rem;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <h1 class="page-title" style="margin:0;">📅 Kế hoạch học tập</h1>
    <input type="date" value="<?= $date ?>" onchange="location='planner.php?date='+this.value"
      style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:0.6rem 1rem;color:var(--text);font-size:0.9rem;outline:none;">
  </div>

  <!-- ADD TASK -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title">+ Thêm nhiệm vụ</div></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="date" value="<?= $date ?>">
        <div class="grid-2" style="margin-bottom:0.75rem;">
          <div>
            <label class="form-label">Môn học</label>
            <input type="text" name="subject" class="form-input" placeholder="Toán, Văn, Anh...">
          </div>
          <div>
            <label class="form-label">Nhiệm vụ</label>
            <input type="text" name="task" class="form-input" placeholder="Làm bài tập trang 45..." required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">+ Thêm</button>
      </form>
    </div>
  </div>

  <!-- TASK LIST -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📋 <?= date('d/m/Y', strtotime($date)) ?> <?= $date===$today ? '— Hôm nay' : '' ?></div>
      <?php
require_once __DIR__ . "/db.php"; if ($total_count > 0): ?>
      <span style="font-family:var(--font-head);font-size:0.82rem;color:var(--green);font-weight:700;"><?= $done_count ?>/<?= $total_count ?> hoàn thành</span>
      <?php
require_once __DIR__ . "/db.php"; endif; ?>
    </div>
    <div class="card-body">
      <?php
require_once __DIR__ . "/db.php"; if ($total_count > 0): ?>
        <div class="progress-wrap" style="margin-bottom:1.2rem;">
          <div class="progress-fill" style="width:<?= $total_count>0?round($done_count/$total_count*100):0 ?>%;background:var(--green);"></div>
        </div>
      <?php
require_once __DIR__ . "/db.php"; endif; ?>
      <?php
require_once __DIR__ . "/db.php"; $rows=[]; while($t=$tasks->fetchArray(SQLITE3_ASSOC)) $rows[]=$t; ?>
      <?php
require_once __DIR__ . "/db.php"; if (count($rows)): ?>
        <?php
require_once __DIR__ . "/db.php"; foreach ($rows as $t): ?>
        <div class="todo-item">
          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button type="submit" class="todo-check <?= $t['done']?'done':'' ?>" style="font-size:0.8rem;">
              <?= $t['done'] ? '✓' : '' ?>
            </button>
          </form>
          <div style="flex:1;">
            <div class="todo-text <?= $t['done']?'done':'' ?>"><?= htmlspecialchars($t['task']) ?></div>
            <?php
require_once __DIR__ . "/db.php"; if ($t['subject']): ?><div style="font-size:0.76rem;color:var(--muted);margin-top:0.15rem;"><?= htmlspecialchars($t['subject']) ?></div><?php endif; ?>
          </div>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Xóa?')">🗑</button>
          </form>
        </div>
        <?php
require_once __DIR__ . "/db.php"; endforeach; ?>
      <?php
require_once __DIR__ . "/db.php"; else: ?>
        <div style="text-align:center;padding:2rem;color:var(--muted);">
          <div style="font-size:2.5rem;margin-bottom:0.75rem;">🎉</div>
          Chưa có nhiệm vụ nào cho ngày này!
        </div>
      <?php
require_once __DIR__ . "/db.php"; endif; ?>
    </div>
  </div>
</div>
</body>
</html>
