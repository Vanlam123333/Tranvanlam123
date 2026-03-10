<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];

$db->exec("CREATE TABLE IF NOT EXISTS daily_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    challenge_id TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    target INTEGER NOT NULL DEFAULT 1,
    progress INTEGER DEFAULT 0,
    completed INTEGER DEFAULT 0,
    xp_reward INTEGER DEFAULT 0,
    icon TEXT DEFAULT '🎯',
    UNIQUE(user_id, date, challenge_id)
)");

$today = date('Y-m-d');
$allChallenges = [
    ['id'=>'pomo2',   'title'=>'Tập trung 2 pomodoro', 'desc'=>'Hoàn thành 2 phiên pomodoro hôm nay',   'target'=>2,  'xp'=>50, 'icon'=>'🍅'],
    ['id'=>'flash10', 'title'=>'Học 10 flashcard',      'desc'=>'Ôn 10 từ vựng bằng Flashcard',         'target'=>10, 'xp'=>40, 'icon'=>'🃏'],
    ['id'=>'quiz1',   'title'=>'Làm 1 bài quiz',        'desc'=>'Hoàn thành ít nhất 1 bài quiz',        'target'=>1,  'xp'=>35, 'icon'=>'🎯'],
    ['id'=>'note1',   'title'=>'Ghi chú hôm nay',       'desc'=>'Tạo ít nhất 1 ghi chú mới',           'target'=>1,  'xp'=>25, 'icon'=>'📝'],
    ['id'=>'plan3',   'title'=>'Hoàn thành 3 nhiệm vụ', 'desc'=>'Tick xong 3 việc trong Planner',       'target'=>3,  'xp'=>45, 'icon'=>'✅'],
    ['id'=>'pomo3',   'title'=>'Tập trung 3 pomodoro',  'desc'=>'Hoàn thành 3 phiên pomodoro hôm nay',  'target'=>3,  'xp'=>70, 'icon'=>'⏰'],
    ['id'=>'flash20', 'title'=>'Học 20 flashcard',      'desc'=>'Ôn 20 từ vựng bằng Flashcard',        'target'=>20, 'xp'=>60, 'icon'=>'📖'],
    ['id'=>'login',   'title'=>'Đăng nhập hôm nay',     'desc'=>'Vào MindSpark hôm nay',               'target'=>1,  'xp'=>10, 'icon'=>'🌟'],
    ['id'=>'streak',  'title'=>'Giữ streak',             'desc'=>'Học ngày hôm nay để duy trì chuỗi',   'target'=>1,  'xp'=>20, 'icon'=>'🔥'],
    ['id'=>'mindmap1','title'=>'Tạo mindmap',            'desc'=>'Tạo 1 sơ đồ tư duy hôm nay',         'target'=>1,  'xp'=>40, 'icon'=>'🧠'],
    ['id'=>'quiz80',  'title'=>'Quiz đạt 80%+',         'desc'=>'Đạt ít nhất 80% một bài quiz',        'target'=>1,  'xp'=>60, 'icon'=>'💯'],
    ['id'=>'chat5',   'title'=>'Hỏi AI 5 câu',          'desc'=>'Đặt 5 câu hỏi cho Spark AI',         'target'=>5,  'xp'=>30, 'icon'=>'🤖'],
];

// Pick 3 deterministic challenges per day
srand((int)date('z') + (int)date('Y')*365);
shuffle($allChallenges);
$todayChallenges = array_slice($allChallenges, 0, 3);
// Always include login
$hasLogin = false;
foreach ($todayChallenges as $c) if ($c['id']==='login') $hasLogin = true;
if (!$hasLogin) $todayChallenges[2] = ['id'=>'login','title'=>'Đăng nhập hôm nay','desc'=>'Vào MindSpark hôm nay','target'=>1,'xp'=>10,'icon'=>'🌟'];

foreach ($todayChallenges as $c) {
    $db->exec("INSERT OR IGNORE INTO daily_challenges (user_id,date,challenge_id,title,description,target,xp_reward,icon)
        VALUES ($uid,'$today','".SQLite3::escapeString($c['id'])."','".SQLite3::escapeString($c['title'])."',
        '".SQLite3::escapeString($c['desc'])."',{$c['target']},{$c['xp']},'".SQLite3::escapeString($c['icon'])."')");
}

// Auto-sync progress
$pomoToday  = (int)$db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND DATE(created_at)='$today'")->fetchArray()['c'];
$flashToday = (int)$db->query("SELECT COUNT(*) as c FROM flashcard_history WHERE user_id=$uid AND date='$today'")->fetchArray()['c'];
$quizToday  = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid AND DATE(created_at)='$today'")->fetchArray()['c'];
$noteToday  = (int)$db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid AND DATE(created_at)='$today'")->fetchArray()['c'];
$planToday  = (int)$db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$today' AND done=1")->fetchArray()['c'];
$mindmapToday = (int)$db->query("SELECT COUNT(*) as c FROM mindmaps WHERE user_id=$uid AND DATE(created_at)='$today'")->fetchArray()['c'];
$quiz80Today = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid AND DATE(created_at)='$today' AND total>0 AND CAST(score AS REAL)/CAST(total AS REAL)>=0.8")->fetchArray()['c'];

$progressMap = [
    'pomo2'=>$pomoToday,'pomo3'=>$pomoToday,
    'flash10'=>$flashToday,'flash20'=>$flashToday,
    'quiz1'=>$quizToday,'quiz80'=>$quiz80Today,
    'note1'=>$noteToday,'plan3'=>$planToday,
    'login'=>1,'streak'=>1,'mindmap1'=>$mindmapToday,'chat5'=>0,
];

foreach ($todayChallenges as $c) {
    $prog = min($c['target'], $progressMap[$c['id']] ?? 0);
    $done = $prog >= $c['target'] ? 1 : 0;
    $db->exec("UPDATE daily_challenges SET progress=$prog,completed=$done WHERE user_id=$uid AND date='$today' AND challenge_id='".SQLite3::escapeString($c['id'])."'");
    if ($done) {
        require_once __DIR__ . '/gamification.php';
        $already = $db->query("SELECT id FROM xp_log WHERE user_id=$uid AND action='challenge' AND note LIKE '%[".$c['id']."]%' AND DATE(created_at)='$today'")->fetchArray();
        if (!$already) awardXP($uid, 'challenge', $c['xp'], "Daily Challenge: ".$c['title']." [".$c['id']."]");
    }
}

// AJAX
if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'),true) ?? [];
    if (($input['action']??'')==='get_challenges') {
        $rows=$db->query("SELECT * FROM daily_challenges WHERE user_id=$uid AND date='$today'");
        $data=[];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)) $data[]=$r;
        echo json_encode(['ok'=>true,'challenges'=>$data]); exit;
    }
    echo json_encode(['ok'=>false]); exit;
}

// Page load
$challenges=[];
$rows=$db->query("SELECT * FROM daily_challenges WHERE user_id=$uid AND date='$today' ORDER BY completed ASC");
while($r=$rows->fetchArray(SQLITE3_ASSOC)) $challenges[]=$r;
$doneCount=(int)array_sum(array_column($challenges,'completed'));
$totalXP=(int)array_sum(array_column($challenges,'xp_reward'));
$earnedXP=(int)array_sum(array_map(fn($c)=>$c['completed']?$c['xp_reward']:0,$challenges));

$history=[];
for($i=6;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days"));
    $tot=(int)$db->query("SELECT COUNT(*) as c FROM daily_challenges WHERE user_id=$uid AND date='$d'")->fetchArray()['c'];
    $dn=(int)$db->query("SELECT COUNT(*) as c FROM daily_challenges WHERE user_id=$uid AND date='$d' AND completed=1")->fetchArray()['c'];
    $history[]=['date'=>$d,'label'=>$i===0?'Hôm nay':date('d/m',strtotime($d)),'total'=>$tot,'done'=>$dn];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Daily Challenge — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.dc-hero{background:linear-gradient(135deg,#064e3b,#065f46,#047857);border-radius:var(--radius-xl);padding:1.5rem 1.75rem;margin-bottom:1.25rem;color:#fff;position:relative;overflow:hidden;}
.dc-hero::after{content:'';position:absolute;bottom:-40px;right:-30px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.06);pointer-events:none;}
.dc-hero-top{display:flex;align-items:center;justify-content:space-between;gap:1rem;}
.dc-hero-title{font-size:1.2rem;font-weight:900;}
.dc-hero-date{font-size:12px;color:rgba(255,255,255,0.65);margin-top:3px;}
.dc-progress-ring{position:relative;width:72px;height:72px;flex-shrink:0;}
.dc-ring-svg{transform:rotate(-90deg);}
.dc-ring-bg{fill:none;stroke:rgba(255,255,255,0.15);stroke-width:6;}
.dc-ring-fill{fill:none;stroke:#34d399;stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset 1s ease;}
.dc-ring-text{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.dc-ring-num{font-size:1.3rem;font-weight:900;line-height:1;}
.dc-ring-sub{font-size:9px;color:rgba(255,255,255,0.65);text-transform:uppercase;letter-spacing:0.5px;}
.dc-xp-label{display:flex;justify-content:space-between;font-size:11px;color:rgba(255,255,255,0.7);margin:12px 0 4px;}
.dc-xp-bar{background:rgba(255,255,255,0.15);border-radius:99px;height:6px;overflow:hidden;}
.dc-xp-fill{height:100%;background:linear-gradient(90deg,#34d399,#059669);border-radius:99px;transition:width 1s ease;}
.challenge-list{display:flex;flex-direction:column;gap:10px;margin-bottom:1.5rem;}
.challenge-card{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:all 0.2s;}
.challenge-card.done{border-color:rgba(52,211,153,0.4);background:rgba(52,211,153,0.05);}
.challenge-icon{font-size:2rem;flex-shrink:0;width:44px;text-align:center;}
.challenge-info{flex:1;min-width:0;}
.challenge-title{font-size:14px;font-weight:800;color:var(--text);margin-bottom:3px;}
.challenge-desc{font-size:12px;color:var(--muted);margin-bottom:8px;}
.challenge-prog-wrap{display:flex;align-items:center;gap:8px;}
.challenge-prog-bar{flex:1;height:5px;background:var(--surface2);border-radius:99px;overflow:hidden;}
.challenge-prog-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--accent),#a78bfa);transition:width 0.8s ease;}
.challenge-prog-fill.done-fill{background:linear-gradient(90deg,#34d399,#059669);}
.challenge-prog-label{font-size:11px;font-weight:700;color:var(--muted);white-space:nowrap;}
.challenge-xp{font-size:12px;font-weight:800;color:var(--accent);background:var(--accent-soft);padding:3px 10px;border-radius:99px;flex-shrink:0;}
.hist-week{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;}
.hist-day-cell{text-align:center;}
.hist-day-bar-wrap{height:40px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;}
.hist-day-bar{width:22px;border-radius:4px 4px 0 0;background:var(--surface2);transition:height 0.5s;min-height:3px;}
.hist-day-bar.full{background:linear-gradient(180deg,#34d399,#059669);}
.hist-day-bar.partial{background:linear-gradient(180deg,#fbbf24,#d97706);}
.hist-day-label{font-size:10px;color:var(--muted);font-weight:700;}
.hist-day-count{font-size:9px;color:var(--muted);margin-top:2px;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Hôm nay · <?= date('d/m/Y') ?></div>
    <h1 class="page-title">Daily Challenge 🎯</h1>
  </div>

  <div class="dc-hero">
    <div class="dc-hero-top">
      <div style="flex:1;">
        <div class="dc-hero-title">Thử thách trong ngày</div>
        <div class="dc-hero-date"><?= ['Sunday'=>'Chủ nhật','Monday'=>'Thứ hai','Tuesday'=>'Thứ ba','Wednesday'=>'Thứ tư','Thursday'=>'Thứ năm','Friday'=>'Thứ sáu','Saturday'=>'Thứ bảy'][date('l')] ?></div>
        <div class="dc-xp-label"><span>+<?= $earnedXP ?> XP đã nhận</span><span>+<?= $totalXP ?> XP tổng hôm nay</span></div>
        <div class="dc-xp-bar"><div class="dc-xp-fill" style="width:<?= $totalXP?round($earnedXP/$totalXP*100):0 ?>%"></div></div>
      </div>
      <?php $pct=count($challenges)?round($doneCount/count($challenges)*100):0;
        $circ=2*M_PI*30;$offset=$circ*(1-$pct/100); ?>
      <div class="dc-progress-ring">
        <svg class="dc-ring-svg" width="72" height="72" viewBox="0 0 72 72">
          <circle class="dc-ring-bg" cx="36" cy="36" r="30"/>
          <circle class="dc-ring-fill" cx="36" cy="36" r="30" stroke-dasharray="<?= round($circ,2) ?>" stroke-dashoffset="<?= round($offset,2) ?>"/>
        </svg>
        <div class="dc-ring-text"><div class="dc-ring-num"><?= $doneCount ?>/<?= count($challenges) ?></div><div class="dc-ring-sub">Xong</div></div>
      </div>
    </div>
  </div>

  <div class="challenge-list">
    <?php foreach ($challenges as $c):
      $pct2 = $c['target']>0?min(100,round($c['progress']/$c['target']*100)):0; ?>
    <div class="challenge-card <?= $c['completed']?'done':'' ?>">
      <div class="challenge-icon"><?= htmlspecialchars($c['icon']) ?></div>
      <div class="challenge-info">
        <div class="challenge-title"><?= htmlspecialchars($c['title']) ?></div>
        <div class="challenge-desc"><?= htmlspecialchars($c['description']) ?></div>
        <div class="challenge-prog-wrap">
          <div class="challenge-prog-bar"><div class="challenge-prog-fill <?= $c['completed']?'done-fill':'' ?>" style="width:<?= $pct2 ?>%"></div></div>
          <div class="challenge-prog-label"><?= $c['progress'] ?>/<?= $c['target'] ?></div>
        </div>
      </div>
      <div class="challenge-xp">+<?= $c['xp_reward'] ?> XP</div>
      <div style="font-size:1.3rem"><?= $c['completed']?'✅':'⬜' ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-body">
      <div style="font-size:15px;font-weight:800;color:var(--text);margin-bottom:14px;">📅 7 ngày qua</div>
      <div class="hist-week">
        <?php foreach($history as $h):
          $barPct=$h['total']>0?round($h['done']/$h['total']*100):0;
          $barH=max(4,round($barPct*0.4));
          $cls=$barPct>=100?'full':($barPct>0?'partial':''); ?>
        <div class="hist-day-cell">
          <div class="hist-day-bar-wrap"><div class="hist-day-bar <?= $cls ?>" style="height:<?= $barH ?>px"></div></div>
          <div class="hist-day-label"><?= $h['label'] ?></div>
          <div class="hist-day-count"><?= $h['done'] ?>/<?= $h['total'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
