<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$user = getCurrentUser();

// ── MIGRATIONS ──
@$db->exec("ALTER TABLE users ADD COLUMN coins INTEGER DEFAULT 500");
@$db->exec("ALTER TABLE users ADD COLUMN xp INTEGER DEFAULT 0");
@$db->exec("ALTER TABLE users ADD COLUMN level INTEGER DEFAULT 1");
@$db->exec("ALTER TABLE users ADD COLUMN streak INTEGER DEFAULT 0");
@$db->exec("ALTER TABLE users ADD COLUMN last_active_date TEXT DEFAULT ''");
@$db->exec("ALTER TABLE users ADD COLUMN longest_streak INTEGER DEFAULT 0");

$db->exec("CREATE TABLE IF NOT EXISTS user_badges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    badge_id TEXT NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, badge_id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS xp_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    xp INTEGER NOT NULL,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS daily_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    challenge_id TEXT NOT NULL,
    target INTEGER NOT NULL,
    progress INTEGER DEFAULT 0,
    completed INTEGER DEFAULT 0,
    xp_reward INTEGER DEFAULT 0,
    UNIQUE(user_id, date, challenge_id)
)");

// ── BADGE DEFINITIONS ──
function getAllBadges() {
    return [
        // Streak badges
        ['id'=>'streak_3',    'name'=>'Khởi đầu',       'desc'=>'Học 3 ngày liên tiếp',        'icon'=>'🔥', 'color'=>'#f97316', 'req_streak'=>3],
        ['id'=>'streak_7',    'name'=>'Tuần hoàn hảo',  'desc'=>'Học 7 ngày liên tiếp',        'icon'=>'⚡', 'color'=>'#eab308', 'req_streak'=>7],
        ['id'=>'streak_14',   'name'=>'Kiên trì',        'desc'=>'Học 14 ngày liên tiếp',       'icon'=>'💪', 'color'=>'#10b981', 'req_streak'=>14],
        ['id'=>'streak_30',   'name'=>'Huyền thoại',    'desc'=>'Học 30 ngày liên tiếp',       'icon'=>'👑', 'color'=>'#8b5cf6', 'req_streak'=>30],
        ['id'=>'streak_100',  'name'=>'Bất khả chiến bại','desc'=>'Học 100 ngày liên tiếp',    'icon'=>'🏆', 'color'=>'#ef4444', 'req_streak'=>100],
        // XP / Level badges
        ['id'=>'lvl_5',       'name'=>'Học sinh',        'desc'=>'Đạt cấp độ 5',               'icon'=>'📚', 'color'=>'#06b6d4', 'req_level'=>5],
        ['id'=>'lvl_10',      'name'=>'Chiến binh',      'desc'=>'Đạt cấp độ 10',              'icon'=>'⚔️', 'color'=>'#4f6ef7', 'req_level'=>10],
        ['id'=>'lvl_20',      'name'=>'Thiên tài',       'desc'=>'Đạt cấp độ 20',              'icon'=>'🧠', 'color'=>'#8b5cf6', 'req_level'=>20],
        ['id'=>'lvl_50',      'name'=>'Bậc thầy',        'desc'=>'Đạt cấp độ 50',              'icon'=>'🌟', 'color'=>'#f59e0b', 'req_level'=>50],
        // Activity badges
        ['id'=>'pomo_10',     'name'=>'Tập trung cao',   'desc'=>'Hoàn thành 10 pomodoro',     'icon'=>'🍅', 'color'=>'#ef4444', 'req_pomo'=>10],
        ['id'=>'pomo_50',     'name'=>'Máy học',         'desc'=>'Hoàn thành 50 pomodoro',     'icon'=>'⏰', 'color'=>'#f97316', 'req_pomo'=>50],
        ['id'=>'pomo_100',    'name'=>'Siêu nhân',       'desc'=>'Hoàn thành 100 pomodoro',    'icon'=>'🦸', 'color'=>'#8b5cf6', 'req_pomo'=>100],
        ['id'=>'quiz_10',     'name'=>'Hay hỏi',         'desc'=>'Làm 10 bài quiz',            'icon'=>'🎯', 'color'=>'#10b981', 'req_quiz'=>10],
        ['id'=>'quiz_ace',    'name'=>'Hoàn hảo',        'desc'=>'Đạt 100% một bài quiz',      'icon'=>'💯', 'color'=>'#eab308', 'req_quiz_perfect'=>1],
        ['id'=>'flash_50',    'name'=>'Thẻ nhớ pro',     'desc'=>'Học 50 flashcard',           'icon'=>'🃏', 'color'=>'#06b6d4', 'req_flash'=>50],
        ['id'=>'flash_200',   'name'=>'Từ điển sống',    'desc'=>'Học 200 flashcard',          'icon'=>'📖', 'color'=>'#4f6ef7', 'req_flash'=>200],
        ['id'=>'note_10',     'name'=>'Nhà văn',         'desc'=>'Tạo 10 ghi chú',            'icon'=>'✍️', 'color'=>'#ec4899', 'req_notes'=>10],
        ['id'=>'social_1',    'name'=>'Hòa đồng',        'desc'=>'Đăng bài đầu tiên',         'icon'=>'🤝', 'color'=>'#06b6d4', 'req_posts'=>1],
        ['id'=>'early_bird',  'name'=>'Dậy sớm',         'desc'=>'Học trước 7 giờ sáng',      'icon'=>'🌅', 'color'=>'#f59e0b', 'req_special'=>'early_bird'],
        ['id'=>'night_owl',   'name'=>'Cú đêm',          'desc'=>'Học sau 11 giờ đêm',        'icon'=>'🦉', 'color'=>'#6d28d9', 'req_special'=>'night_owl'],
    ];
}

// ── XP THRESHOLDS PER LEVEL ──
function xpForLevel($level) {
    return (int)(100 * pow($level, 1.5));
}
function getLevelFromXP($xp) {
    $level = 1;
    while (xpForLevel($level + 1) <= $xp) $level++;
    return $level;
}
function getXPProgress($xp) {
    $level = getLevelFromXP($xp);
    $currentThresh = xpForLevel($level);
    $nextThresh = xpForLevel($level + 1);
    $progress = $xp - $currentThresh;
    $needed = $nextThresh - $currentThresh;
    return ['level'=>$level, 'progress'=>$progress, 'needed'=>$needed, 'pct'=>min(100, round($progress/$needed*100))];
}

// ── AWARD XP ──
function awardXP($uid, $action, $xp, $note = '') {
    global $db;
    $db->exec("UPDATE users SET xp = xp + $xp WHERE id = $uid");
    $db->exec("INSERT INTO xp_log (user_id, action, xp, note) VALUES ($uid, '".SQLite3::escapeString($action)."', $xp, '".SQLite3::escapeString($note)."')");
    // Update level
    $newXP = (int)$db->query("SELECT xp FROM users WHERE id=$uid")->fetchArray()['xp'];
    $newLevel = getLevelFromXP($newXP);
    $db->exec("UPDATE users SET level=$newLevel WHERE id=$uid");
    checkAndAwardBadges($uid);
    return $newXP;
}

// ── UPDATE STREAK ──
function updateStreak($uid) {
    global $db;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $row = $db->query("SELECT streak, last_active_date, longest_streak FROM users WHERE id=$uid")->fetchArray(SQLITE3_ASSOC);
    $streak = (int)($row['streak'] ?? 0);
    $longest = (int)($row['longest_streak'] ?? 0);
    $last = $row['last_active_date'] ?? '';

    if ($last === $today) return $streak; // already updated today
    if ($last === $yesterday) {
        $streak++;
    } else {
        $streak = 1; // reset
    }
    $longest = max($longest, $streak);
    $db->exec("UPDATE users SET streak=$streak, last_active_date='$today', longest_streak=$longest WHERE id=$uid");
    awardXP($uid, 'daily_login', 10, "Đăng nhập ngày $today");
    checkAndAwardBadges($uid);
    return $streak;
}

// ── CHECK & AWARD BADGES ──
function checkAndAwardBadges($uid) {
    global $db;
    $badges = getAllBadges();
    $user = $db->query("SELECT * FROM users WHERE id=$uid")->fetchArray(SQLITE3_ASSOC);
    $streak = (int)($user['streak'] ?? 0);
    $level  = (int)($user['level']  ?? 1);
    $pomo   = (int)$db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND completed=1")->fetchArray()['c'];
    $quiz   = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid")->fetchArray()['c'];
    $flash  = (int)$db->query("SELECT COUNT(*) as c FROM flashcard_history WHERE user_id=$uid")->fetchArray()['c'];
    $notes  = (int)$db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid")->fetchArray()['c'];
    $posts  = (int)$db->query("SELECT COUNT(*) as c FROM social_posts WHERE user_id=$uid")->fetchArray()['c'];
    $perfectQuiz = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid AND score=total AND total>0")->fetchArray()['c'];
    $hour = (int)date('H');
    $newBadges = [];

    foreach ($badges as $b) {
        $earned = false;
        if (isset($b['req_streak'])  && $streak  >= $b['req_streak'])  $earned = true;
        if (isset($b['req_level'])   && $level   >= $b['req_level'])   $earned = true;
        if (isset($b['req_pomo'])    && $pomo    >= $b['req_pomo'])    $earned = true;
        if (isset($b['req_quiz'])    && $quiz    >= $b['req_quiz'])    $earned = true;
        if (isset($b['req_flash'])   && $flash   >= $b['req_flash'])   $earned = true;
        if (isset($b['req_notes'])   && $notes   >= $b['req_notes'])   $earned = true;
        if (isset($b['req_posts'])   && $posts   >= $b['req_posts'])   $earned = true;
        if (isset($b['req_quiz_perfect']) && $perfectQuiz >= $b['req_quiz_perfect']) $earned = true;
        if (isset($b['req_special'])) {
            if ($b['req_special'] === 'early_bird' && $hour < 7) $earned = true;
            if ($b['req_special'] === 'night_owl'  && $hour >= 23) $earned = true;
        }
        if ($earned) {
            $res = $db->exec("INSERT OR IGNORE INTO user_badges (user_id, badge_id) VALUES ($uid, '".SQLite3::escapeString($b['id'])."')");
            if ($db->changes() > 0) $newBadges[] = $b;
        }
    }
    return $newBadges;
}

// ── AJAX ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $_POST['action'] ?? '';

    if ($action === 'get_stats') {
        $u = $db->query("SELECT xp, level, streak, longest_streak, coins FROM users WHERE id=$uid")->fetchArray(SQLITE3_ASSOC);
        $earnedBadgeIds = [];
        $bRows = $db->query("SELECT badge_id, earned_at FROM user_badges WHERE user_id=$uid");
        while ($r = $bRows->fetchArray(SQLITE3_ASSOC)) $earnedBadgeIds[$r['badge_id']] = $r['earned_at'];
        $lvlInfo = getXPProgress((int)$u['xp']);
        echo json_encode(['ok'=>true, 'user'=>$u, 'level_info'=>$lvlInfo, 'earned_badges'=>$earnedBadgeIds]);
        exit;
    }

    if ($action === 'update_streak') {
        $streak = updateStreak($uid);
        $u = $db->query("SELECT xp, level, streak FROM users WHERE id=$uid")->fetchArray(SQLITE3_ASSOC);
        echo json_encode(['ok'=>true, 'streak'=>$streak, 'xp'=>$u['xp'], 'level'=>$u['level']]);
        exit;
    }

    if ($action === 'award_xp') {
        $act  = SQLite3::escapeString($input['for'] ?? 'action');
        $xpAmt = (int)($input['xp'] ?? 10);
        $note = SQLite3::escapeString($input['note'] ?? '');
        $newXP = awardXP($uid, $act, $xpAmt, $note);
        $lvlInfo = getXPProgress($newXP);
        echo json_encode(['ok'=>true, 'xp'=>$newXP, 'level_info'=>$lvlInfo]);
        exit;
    }
    echo json_encode(['ok'=>false]);
    exit;
}

// ── LOAD PAGE DATA ──
updateStreak($uid);
$u = $db->query("SELECT xp, level, streak, longest_streak, coins FROM users WHERE id=$uid")->fetchArray(SQLITE3_ASSOC);
$xp = (int)$u['xp'];
$lvlInfo = getXPProgress($xp);
$streak = (int)$u['streak'];
$longestStreak = (int)$u['longest_streak'];
$coins = (int)$u['coins'];

$earnedBadgeIds = [];
$bRows = $db->query("SELECT badge_id, earned_at FROM user_badges WHERE user_id=$uid ORDER BY earned_at DESC");
while ($r = $bRows->fetchArray(SQLITE3_ASSOC)) $earnedBadgeIds[$r['badge_id']] = $r['earned_at'];

$allBadges = getAllBadges();
$earnedCount = count($earnedBadgeIds);
$totalBadges = count($allBadges);

// Recent XP log
$xpLog = [];
$logRows = $db->query("SELECT * FROM xp_log WHERE user_id=$uid ORDER BY created_at DESC LIMIT 10");
while ($r = $logRows->fetchArray(SQLITE3_ASSOC)) $xpLog[] = $r;

// Stats for badges progress
$pomo  = (int)$db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus'")->fetchArray()['c'];
$quiz  = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid")->fetchArray()['c'];
$flash = (int)$db->query("SELECT COUNT(*) as c FROM flashcard_history WHERE user_id=$uid")->fetchArray()['c'];
$notes = (int)$db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid")->fetchArray()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Thành tích — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── HERO ── */
.gami-hero {
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4c1d95 100%);
  border-radius: var(--radius-xl); padding: 1.75rem;
  margin-bottom: 1.25rem; position: relative; overflow: hidden; color: #fff;
}
.gami-hero::before {
  content:''; position:absolute; top:-80px; right:-60px;
  width:300px; height:300px; border-radius:50%;
  background:rgba(139,92,246,0.2); pointer-events:none;
}
.gami-hero-top { display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; }
.gami-level-badge {
  width:64px; height:64px; border-radius:50%;
  background: linear-gradient(135deg, #8b5cf6, #6366f1);
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  font-weight:900; flex-shrink:0; border:3px solid rgba(255,255,255,0.3);
  box-shadow: 0 0 20px rgba(139,92,246,0.5);
}
.gami-level-num { font-size:1.4rem; line-height:1; color:#fff; }
.gami-level-label { font-size:9px; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,0.7); }
.gami-hero-info h2 { font-size:1.2rem; font-weight:800; margin:0 0 3px; }
.gami-hero-info p  { font-size:12px; color:rgba(255,255,255,0.7); margin:0; }
.xp-bar-wrap { margin-bottom:8px; }
.xp-bar-labels { display:flex; justify-content:space-between; font-size:11px; color:rgba(255,255,255,0.7); margin-bottom:5px; }
.xp-bar { height:8px; background:rgba(255,255,255,0.15); border-radius:99px; overflow:hidden; }
.xp-bar-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,#a78bfa,#818cf8); transition:width 1s ease; }
.gami-hero-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:1rem; }
.gami-hero-stat { background:rgba(255,255,255,0.1); border-radius:12px; padding:10px; text-align:center; backdrop-filter:blur(4px); }
.gami-hero-stat-num { font-size:1.3rem; font-weight:900; }
.gami-hero-stat-label { font-size:10px; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.5px; }

/* ── STREAK ── */
.streak-card {
  background: linear-gradient(135deg, #431407, #7c2d12);
  border-radius: var(--radius-xl); padding: 1.25rem 1.5rem;
  margin-bottom: 1.25rem; color:#fff; display:flex; align-items:center; gap:1rem;
  border:1px solid rgba(249,115,22,0.3);
}
.streak-flame { font-size:3rem; filter:drop-shadow(0 0 12px rgba(249,115,22,0.8)); animation:flamePulse 2s ease-in-out infinite; }
@keyframes flamePulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
.streak-info h3 { font-size:1.6rem; font-weight:900; margin:0; }
.streak-info p  { font-size:12px; color:rgba(255,255,255,0.7); margin:3px 0 0; }
.streak-best { margin-left:auto; text-align:right; }
.streak-best-num { font-size:1.1rem; font-weight:800; color:#fb923c; }
.streak-best-label { font-size:10px; color:rgba(255,255,255,0.6); }

/* ── SECTION TITLE ── */
.section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.section-title { font-size:15px; font-weight:800; color:var(--text); }
.section-sub { font-size:12px; color:var(--muted); }

/* ── BADGE GRID ── */
.badge-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px; }
.badge-item {
  background:var(--surface); border:1.5px solid var(--border);
  border-radius:14px; padding:14px 10px; text-align:center;
  transition:all 0.2s; position:relative; overflow:hidden;
}
.badge-item.earned {
  border-color: transparent;
  box-shadow: 0 0 0 2px var(--badge-color, #8b5cf6), 0 4px 16px rgba(0,0,0,0.15);
}
.badge-item.earned::before {
  content:''; position:absolute; inset:0;
  background: radial-gradient(circle at top, color-mix(in srgb, var(--badge-color, #8b5cf6) 12%, transparent), transparent 70%);
}
.badge-item.locked { opacity:0.45; filter:grayscale(0.6); }
.badge-icon { font-size:2rem; margin-bottom:6px; display:block; }
.badge-name { font-size:11px; font-weight:800; color:var(--text); margin-bottom:3px; line-height:1.3; }
.badge-desc { font-size:10px; color:var(--muted); line-height:1.4; }
.badge-earned-date { font-size:9px; color:var(--muted); margin-top:4px; }
.badge-check {
  position:absolute; top:7px; right:7px;
  width:16px; height:16px; border-radius:50%;
  background:var(--badge-color, #8b5cf6);
  display:flex; align-items:center; justify-content:center;
  font-size:9px; color:#fff;
}

/* ── XP LOG ── */
.xp-log { display:flex; flex-direction:column; gap:6px; }
.xp-log-item { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:10px; background:var(--surface2); }
.xp-log-icon { width:28px; height:28px; border-radius:8px; background:var(--accent-soft); display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
.xp-log-text { flex:1; font-size:12px; color:var(--text2); }
.xp-log-xp { font-size:12px; font-weight:800; color:var(--accent); }

/* ── PROGRESS STATS ── */
.progress-stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.prog-stat { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px; }
.prog-stat-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.prog-stat-label { font-size:12px; font-weight:700; color:var(--text2); }
.prog-stat-num { font-size:1.1rem; font-weight:900; }
.prog-bar { height:5px; background:var(--surface2); border-radius:99px; overflow:hidden; }
.prog-bar-fill { height:100%; border-radius:99px; transition:width 1s ease; }

/* ── TOAST ── */
#toastWrap { position:fixed; bottom:80px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.toast {
  background:var(--surface); border:1.5px solid var(--border);
  border-radius:14px; padding:12px 16px; display:flex; align-items:center; gap:10px;
  box-shadow:0 8px 24px rgba(0,0,0,0.2); pointer-events:auto;
  animation: toastIn 0.3s ease;
  min-width:220px;
}
@keyframes toastIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
.toast-icon { font-size:1.5rem; }
.toast-title { font-size:13px; font-weight:800; color:var(--text); }
.toast-sub { font-size:11px; color:var(--muted); }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div id="toastWrap"></div>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Gamification</div>
    <h1 class="page-title">Thành tích & Cấp độ</h1>
  </div>

  <!-- HERO: Level + XP -->
  <div class="gami-hero">
    <div class="gami-hero-top">
      <div class="gami-level-badge">
        <div class="gami-level-num"><?= $lvlInfo['level'] ?></div>
        <div class="gami-level-label">Level</div>
      </div>
      <div class="gami-hero-info">
        <h2><?= htmlspecialchars($user['name']) ?></h2>
        <p><?= number_format($xp) ?> XP tổng · Còn <?= number_format($lvlInfo['needed'] - $lvlInfo['progress']) ?> XP lên cấp <?= $lvlInfo['level']+1 ?></p>
      </div>
    </div>
    <div class="xp-bar-wrap">
      <div class="xp-bar-labels">
        <span>Cấp <?= $lvlInfo['level'] ?></span>
        <span><?= $lvlInfo['pct'] ?>%</span>
        <span>Cấp <?= $lvlInfo['level']+1 ?></span>
      </div>
      <div class="xp-bar">
        <div class="xp-bar-fill" style="width:<?= $lvlInfo['pct'] ?>%"></div>
      </div>
    </div>
    <div class="gami-hero-stats">
      <div class="gami-hero-stat">
        <div class="gami-hero-stat-num"><?= $earnedCount ?>/<?= $totalBadges ?></div>
        <div class="gami-hero-stat-label">Huy hiệu</div>
      </div>
      <div class="gami-hero-stat">
        <div class="gami-hero-stat-num"><?= number_format($coins) ?></div>
        <div class="gami-hero-stat-label">Xu 🪙</div>
      </div>
      <div class="gami-hero-stat">
        <div class="gami-hero-stat-num"><?= $pomo ?></div>
        <div class="gami-hero-stat-label">Pomodoro</div>
      </div>
    </div>
  </div>

  <!-- STREAK -->
  <div class="streak-card">
    <div class="streak-flame">🔥</div>
    <div class="streak-info">
      <h3><?= $streak ?> ngày</h3>
      <p>Chuỗi học hiện tại<?= $streak >= 7 ? ' · 🔥 Đang bốc!' : '' ?></p>
    </div>
    <div class="streak-best">
      <div class="streak-best-num">🏆 <?= $longestStreak ?></div>
      <div class="streak-best-label">Kỷ lục</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

    <!-- LEFT -->
    <div>
      <!-- Badges -->
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-body">
          <div class="section-header">
            <div class="section-title">🏅 Huy hiệu</div>
            <div class="section-sub"><?= $earnedCount ?>/<?= $totalBadges ?> đã đạt</div>
          </div>
          <div class="badge-grid">
            <?php foreach ($allBadges as $b):
              $earned = isset($earnedBadgeIds[$b['id']]);
              $earnedDate = $earned ? date('d/m/Y', strtotime($earnedBadgeIds[$b['id']])) : '';
            ?>
            <div class="badge-item <?= $earned ? 'earned' : 'locked' ?>" style="--badge-color:<?= $b['color'] ?>" title="<?= htmlspecialchars($b['desc']) ?>">
              <?php if($earned): ?><div class="badge-check">✓</div><?php endif; ?>
              <span class="badge-icon"><?= $b['icon'] ?></span>
              <div class="badge-name"><?= htmlspecialchars($b['name']) ?></div>
              <div class="badge-desc"><?= htmlspecialchars($b['desc']) ?></div>
              <?php if($earned): ?><div class="badge-earned-date"><?= $earnedDate ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT -->
    <div>
      <!-- Progress stats -->
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-body">
          <div class="section-header">
            <div class="section-title">📊 Tiến độ</div>
          </div>
          <div class="progress-stats">
            <?php
            $progItems = [
              ['label'=>'🍅 Pomodoro', 'val'=>$pomo,  'max'=>100, 'color'=>'#ef4444'],
              ['label'=>'🃏 Flashcard', 'val'=>$flash, 'max'=>200, 'color'=>'#06b6d4'],
              ['label'=>'🎯 Quiz',      'val'=>$quiz,  'max'=>50,  'color'=>'#10b981'],
              ['label'=>'📝 Ghi chú',  'val'=>$notes, 'max'=>30,  'color'=>'#f59e0b'],
            ];
            foreach($progItems as $p):
              $pct2 = min(100, round($p['val']/$p['max']*100));
            ?>
            <div class="prog-stat">
              <div class="prog-stat-top">
                <div class="prog-stat-label"><?= $p['label'] ?></div>
                <div class="prog-stat-num" style="color:<?= $p['color'] ?>"><?= $p['val'] ?></div>
              </div>
              <div class="prog-bar">
                <div class="prog-bar-fill" style="width:<?= $pct2 ?>%;background:<?= $p['color'] ?>"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- XP Log -->
      <div class="card">
        <div class="card-body">
          <div class="section-header">
            <div class="section-title">⚡ Lịch sử XP</div>
          </div>
          <div class="xp-log">
            <?php if(empty($xpLog)): ?>
            <div style="text-align:center;padding:2rem;color:var(--muted);font-size:13px;">Chưa có XP nào</div>
            <?php else: foreach($xpLog as $log):
              $icons = ['daily_login'=>'📅','pomodoro'=>'🍅','quiz'=>'🎯','flashcard'=>'🃏','note'=>'📝'];
              $ic = $icons[$log['action']] ?? '⚡';
            ?>
            <div class="xp-log-item">
              <div class="xp-log-icon"><?= $ic ?></div>
              <div class="xp-log-text"><?= htmlspecialchars($log['note'] ?: $log['action']) ?></div>
              <div class="xp-log-xp">+<?= $log['xp'] ?> XP</div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ── TOAST ──
function showToast(icon, title, sub, color='#8b5cf6') {
  const wrap = document.getElementById('toastWrap');
  const el = document.createElement('div');
  el.className = 'toast';
  el.innerHTML = `<div class="toast-icon">${icon}</div><div><div class="toast-title">${title}</div><div class="toast-sub">${sub}</div></div>`;
  el.style.borderColor = color;
  wrap.appendChild(el);
  setTimeout(() => { el.style.transition='all 0.3s'; el.style.opacity='0'; el.style.transform='translateX(100%)'; setTimeout(()=>el.remove(),300); }, 3500);
}

// ── CHECK FOR NEW BADGES ON LOAD ──
(async function() {
  try {
    const res = await fetch('gamification.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'update_streak'})
    });
    const data = await res.json();
    if (data.ok && data.streak) {
      if (data.streak % 7 === 0) {
        showToast('🔥', `${data.streak} ngày liên tiếp!`, 'Tuyệt vời! Giữ vững nhé!', '#f97316');
      }
    }
  } catch(e) {}
})();
</script>
</body>
</html>
