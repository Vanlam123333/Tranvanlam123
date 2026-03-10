<?php
require_once __DIR__ . "/db.php";
requireLogin();
$user  = getCurrentUser();
$uid   = $_SESSION['user_id'];
$today = date('Y-m-d');
$dayNames = ['Sunday'=>'Chủ nhật','Monday'=>'Thứ hai','Tuesday'=>'Thứ ba','Wednesday'=>'Thứ tư','Thursday'=>'Thứ năm','Friday'=>'Thứ sáu','Saturday'=>'Thứ bảy'];
$dayVi = $dayNames[date('l')] ?? date('l');
$notes_count = (int)$db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid")->fetchArray()['c'];
$pomo_today  = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(duration),0) as m FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND DATE(created_at)='$today'")->fetchArray(SQLITE3_ASSOC);
$done_today  = (int)$db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$today' AND done=1")->fetchArray()['c'];
$total_today = (int)$db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$today'")->fetchArray()['c'];
$today_tasks = $db->query("SELECT * FROM plans WHERE user_id=$uid AND date='$today' ORDER BY done ASC, created_at ASC LIMIT 8");
$upcoming_q  = $db->query("SELECT * FROM plans WHERE user_id=$uid AND date>='$today' AND date<=DATE('$today','+6 days') AND done=0 ORDER BY date ASC, created_at ASC LIMIT 12");
$week_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d    = date('Y-m-d', strtotime("-$i days"));
    $dow  = (int)date('w', strtotime("-$i days"));
    $days = ['CN','T2','T3','T4','T5','T6','T7'];
    $week_data[] = ['date'=>$d,'day'=>$days[$dow],'dd'=>date('d',strtotime("-$i days")),
        'done'=>(int)$db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$d' AND done=1")->fetchArray()['c'],
        'pomo'=>(int)$db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND DATE(created_at)='$d'")->fetchArray()['c'],
        'is_today'=>$i===0];
}
$upcoming_rows = [];
while ($r = $upcoming_q->fetchArray(SQLITE3_ASSOC)) $upcoming_rows[] = $r;
$grouped = [];
foreach ($upcoming_rows as $r) $grouped[$r['date']][] = $r;
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Chào buổi sáng' : ($hour < 17 ? 'Chào buổi chiều' : 'Chào buổi tối');
$greetingIcon = $hour < 12 ? '<img src="https://api.iconify.design/ph/sun-bold.svg" style="width:20px;height:20px;vertical-align:middle">' : ($hour < 17 ? '<img src="https://api.iconify.design/ph/cloud-sun-bold.svg" style="width:20px;height:20px;vertical-align:middle">' : '<img src="https://api.iconify.design/ph/moon-bold.svg" style="width:20px;height:20px;vertical-align:middle">');
$nameArr = explode(' ', $user['name']);
$firstName = end($nameArr);
$pct = $total_today > 0 ? round($done_today/$total_today*100) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
/* ── DASHBOARD ── */
.dash-hero {
  background: linear-gradient(135deg, var(--accent) 0%, var(--accent2) 60%, #0891b2 100%);
  border-radius: var(--radius-xl); padding: 1.5rem 1.75rem;
  margin-bottom: 1.25rem; position: relative; overflow: hidden;
  color: #fff;
}
.dash-hero::before {
  content: ''; position: absolute; top: -60%; right: -10%;
  width: 300px; height: 300px; border-radius: 50%;
  background: rgba(255,255,255,0.06); pointer-events: none;
}
.dash-hero::after {
  content: ''; position: absolute; bottom: -40%; left: 30%;
  width: 200px; height: 200px; border-radius: 50%;
  background: rgba(255,255,255,0.04); pointer-events: none;
}
.dash-greeting { font-size: 12px; font-weight: 700; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.dash-name { font-family: var(--font-display); font-size: 2rem; font-weight: 800; letter-spacing: -1px; line-height: 1.1; margin-bottom: 8px; }
.dash-meta { display: flex; align-items: center; gap: 12px; font-size: 12px; opacity: 0.75; flex-wrap: wrap; }
.dash-meta-item { display: flex; align-items: center; gap: 5px; }
.dash-actions { display: flex; gap: 8px; margin-top: 1.2rem; z-index: 1; position: relative; }
.dash-action-btn {
  padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 700;
  cursor: pointer; border: none; font-family: var(--font-display); transition: all 0.2s;
  display: flex; align-items: center; gap: 6px;
  text-decoration: none;
}
.dash-action-primary { background: rgba(255,255,255,0.2); color: #fff; border: 1.5px solid rgba(255,255,255,0.3); backdrop-filter: blur(10px); }
.dash-action-primary:hover { background: rgba(255,255,255,0.3); color: #fff; }
.dash-action-ghost { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); border: 1.5px solid rgba(255,255,255,0.15); }
.dash-action-ghost:hover { background: rgba(255,255,255,0.15); color: #fff; }

/* STAT GRID */
.stat-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 1.25rem; }
.stat-tile {
  background: var(--card-gradient); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 14px 16px;
  display: flex; align-items: center; gap: 12px;
  transition: all 0.2s; box-shadow: var(--shadow-sm);
  animation: fadeUp 0.4s both;
}
.stat-tile:hover { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--border2); }
@keyframes fadeUp { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform:none; } }
.stat-tile:nth-child(1) { animation-delay: 0ms; }
.stat-tile:nth-child(2) { animation-delay: 60ms; }
.stat-tile:nth-child(3) { animation-delay: 120ms; }
.stat-tile:nth-child(4) { animation-delay: 180ms; }
.stat-tile-icon {
  width: 42px; height: 42px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.stat-tile-icon.indigo { background: var(--accent-soft); }
.stat-tile-icon.green  { background: var(--green-soft); }
.stat-tile-icon.gold   { background: var(--gold-soft); }
.stat-tile-icon.teal   { background: var(--teal-soft); }
.stat-tile-val { font-family: var(--mono); font-size: 1.4rem; font-weight: 500; color: var(--text); line-height: 1; }
.stat-tile-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; }

/* HERO FEATURE CARDS */
.hero-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1.25rem; }
.hero-card {
  position: relative; border-radius: var(--radius-lg); padding: 20px 20px 18px;
  text-decoration: none; display: flex; flex-direction: column; gap: 8px;
  overflow: hidden; transition: transform .18s, box-shadow .18s;
}
.hero-card:hover { transform: translateY(-3px); box-shadow: 0 14px 35px rgba(0,0,0,0.2); }
.hero-card-mindmap { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
.hero-card-math { background: linear-gradient(135deg, #059669 0%, #0891b2 100%); }
.hero-card-icon { width: 44px; height: 44px; border-radius: 11px; background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; }
.hero-card-icon svg { width: 22px; height: 22px; stroke: #fff; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.hero-card-title { font-family: var(--font-display); font-size: 16px; font-weight: 800; color: #fff; }
.hero-card-sub { font-size: 12px; color: rgba(255,255,255,0.72); font-weight: 500; line-height: 1.4; }
.hero-card-arrow { margin-top: auto; align-self: flex-start; background: rgba(255,255,255,0.18); color: #fff; border: 1px solid rgba(255,255,255,0.25); border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 700; font-family: var(--font-display); }
.hero-card:hover .hero-card-arrow { background: rgba(255,255,255,0.28); }
.hero-card::after { content: ''; position: absolute; right: -30px; top: -30px; width: 130px; height: 130px; border-radius: 50%; background: rgba(255,255,255,0.07); pointer-events: none; }

/* AI INSIGHT CARD */
.ai-insight {
  background: var(--card-gradient); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 1rem 1.1rem;
  margin-bottom: 1.25rem; position: relative; overflow: hidden;
}
.ai-insight::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, var(--accent), var(--accent2), #0891b2);
}
.ai-insight-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.ai-insight-badge { display: flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 99px; background: var(--accent-soft); color: var(--accent); font-size: 11px; font-weight: 700; }
.ai-insight-text { font-size: 13px; color: var(--text2); line-height: 1.55; }
.ai-insight-text strong { color: var(--text); }

/* TASK CARD */
.task-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border); }
.task-row:last-child { border-bottom: none; }
.task-chk { width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--border2); flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 10px; }
.task-chk.done { background: var(--green); border-color: var(--green); color: #fff; }
.task-txt { flex: 1; font-size: 13px; font-weight: 600; color: var(--text); }
.task-txt.done { color: var(--muted); text-decoration: line-through; }
.task-subj { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px; background: var(--accent-soft); color: var(--accent); flex-shrink: 0; }

/* UPCOMING */
.up-hdr { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); padding: 8px 0 4px; display: flex; align-items: center; gap: 6px; }
.up-hdr::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.up-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.up-item { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 10px; background: var(--surface2); margin-bottom: 4px; border-left: 3px solid var(--accent); }

/* AI STUDY PLAN */
.ai-plan-wrap { background: var(--surface2); border-radius: var(--radius); padding: 10px 12px; font-size: 13px; color: var(--text2); min-height: 60px; line-height: 1.55; }

@media(max-width:900px) { .stat-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:600px) { .stat-grid { grid-template-columns: repeat(2,1fr); } .hero-cards { grid-template-columns: 1fr; } .dash-name { font-size: 1.6rem; } }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">

  <!-- HERO GREETING -->
  <div class="dash-hero">
    <div class="dash-greeting"><?= $greetingIcon ?> <?= $dayVi ?>, <?= date('d/m/Y') ?></div>
    <h1 class="dash-name">Chào, <?= htmlspecialchars($firstName) ?>! </h1>
    <div class="dash-meta">
      <div class="dash-meta-item"><img src="https://api.iconify.design/ph/clock-bold.svg" style="width:14px;height:14px;vertical-align:middle;filter:invert(0.5)"> <span id="liveClock">--:--</span></div>
      <?php if($total_today > 0): ?>
      <div class="dash-meta-item"><img src="https://api.iconify.design/ph/check-circle-bold.svg" style="width:14px;height:14px;vertical-align:middle;filter:invert(0.5)"> <?= $done_today ?>/<?= $total_today ?> nhiệm vụ hôm nay</div>
      <div class="dash-meta-item"><img src="https://api.iconify.design/ph/chart-bar-bold.svg" style="width:18px;height:18px;vertical-align:middle;filter:invert(1)"> <?= $pct ?>% hoàn thành</div>
      <?php endif; ?>
      <div class="dash-meta-item"><img src="https://api.iconify.design/ph/timer-bold.svg" style="width:18px;height:18px;vertical-align:middle;filter:invert(1)"> <?= $pomo_today['c'] ?> pomodoro</div>
    </div>
    <div class="dash-actions">
      <a href="chat.php" class="dash-action-btn dash-action-primary"><img src="https://api.iconify.design/ph/brain-bold.svg" style="width:18px;height:18px;vertical-align:middle;filter:invert(1)"> Chat với Spark AI</a>
      <a href="pomodoro.php" class="dash-action-btn dash-action-ghost"><img src="https://api.iconify.design/ph/timer-bold.svg" style="width:18px;height:18px;vertical-align:middle;filter:invert(1)"> Bắt đầu học</a>
      <a href="planner.php" class="dash-action-btn dash-action-ghost"><img src="https://api.iconify.design/ph/plus-circle-bold.svg" style="width:16px;height:16px;vertical-align:middle;filter:invert(1)"> Thêm việc</a>
    </div>
  </div>

  <!-- STATS -->
  <div class="stat-grid">
    <div class="stat-tile">
      <div class="stat-tile-icon green"><img src="https://api.iconify.design/ph/check-circle-bold.svg" style="width:22px;height:22px;filter:invert(1)"></div>
      <div><div class="stat-tile-val"><?= $done_today ?>/<?= $total_today ?: '0' ?></div><div class="stat-tile-label">Nhiệm vụ hôm nay</div></div>
    </div>
    <div class="stat-tile">
      <div class="stat-tile-icon indigo"><img src="https://api.iconify.design/ph/timer-bold.svg" style="width:18px;height:18px;vertical-align:middle;filter:invert(1)"></div>
      <div><div class="stat-tile-val"><?= $pomo_today['c'] ?></div><div class="stat-tile-label">Pomodoro hôm nay</div></div>
    </div>
    <div class="stat-tile">
      <div class="stat-tile-icon gold">⏱️</div>
      <div><div class="stat-tile-val"><?= $pomo_today['m'] ?>p</div><div class="stat-tile-label">Phút học hôm nay</div></div>
    </div>
    <div class="stat-tile">
      <div class="stat-tile-icon teal"><img src="https://api.iconify.design/ph/note-bold.svg" style="width:22px;height:22px;filter:invert(1)"></div>
      <div><div class="stat-tile-val"><?= $notes_count ?></div><div class="stat-tile-label">Ghi chú</div></div>
    </div>
  </div>

  <!-- AI INSIGHT -->
  <div class="ai-insight">
    <div class="ai-insight-header">
      <div class="ai-insight-badge"><img src="https://api.iconify.design/ph/sparkle-bold.svg" style="width:12px;height:12px;filter:invert(1)"> AI <span>Spark</span></div>
      <span style="font-size:11px;color:var(--muted);">Lời khuyên hôm nay</span>
      <button onclick="loadAIInsight()" class="btn btn-ghost btn-sm" style="margin-left:auto;" id="insightRefresh"><img src="https://api.iconify.design/ph/arrow-clockwise-bold.svg" style="width:13px;height:13px;vertical-align:middle;filter:invert(0.6)"> Làm mới</button>
    </div>
    <div class="ai-insight-text" id="aiInsight">
      <span style="color:var(--muted);">⌛ Đang tải lời khuyên AI...</span>
    </div>
  </div>

  <!-- HERO FEATURES -->
  <div class="hero-cards">
    <a href="mindmap.php" class="hero-card hero-card-mindmap">
      <div class="hero-card-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><line x1="12" y1="9" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="15"/><line x1="9" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="15" y2="12"/><circle cx="12" cy="3" r="1.5"/><circle cx="12" cy="21" r="1.5"/><circle cx="3" cy="12" r="1.5"/><circle cx="21" cy="12" r="1.5"/></svg>
      </div>
      <div class="hero-card-title">Mind Map AI</div>
      <div class="hero-card-sub">Tự động tạo sơ đồ tư duy từ văn bản · Kết nối ý tưởng · Ghi nhớ sâu hơn</div>
      <div class="hero-card-arrow">Mở Mind Map →</div>
    </a>
    <a href="math.php" class="hero-card hero-card-math">
      <div class="hero-card-icon">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/><line x1="5" y1="7" x2="10" y2="7"/><line x1="14" y1="17" x2="19" y2="17"/></svg>
      </div>
      <div class="hero-card-title">Giải Toán AI</div>
      <div class="hero-card-sub">Giải phương trình · Vẽ đồ thị · Từng bước chi tiết với công thức LaTeX</div>
      <div class="hero-card-arrow">Mở Toán học →</div>
    </a>
  </div>

  <!-- MAIN GRID -->
  <div class="grid-2" style="gap:1.2rem;margin-bottom:1.2rem;">

    <!-- TODAY TASKS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><img src="https://api.iconify.design/ph/calendar-bold.svg" style="width:14px;height:14px;vertical-align:middle"> Nhiệm vụ hôm nay</div>
        <div style="display:flex;align-items:center;gap:8px;">
          <?php if($total_today > 0): ?>
          <span style="font-size:11px;font-weight:800;color:var(--green);"><?= $done_today ?>/<?= $total_today ?></span>
          <?php endif; ?>
          <a href="planner.php" class="btn btn-ghost btn-sm">+ Thêm</a>
        </div>
      </div>
      <div class="card-body">
        <?php if($total_today > 0): ?>
        <div style="margin-bottom:12px;">
          <div class="progress-wrap"><div class="progress-fill" style="width:<?= $pct ?>%;"></div></div>
          <div style="font-size:10px;color:var(--muted);margin-top:4px;font-weight:700;"><?= $pct ?>% hoàn thành</div>
        </div>
        <?php while ($t = $today_tasks->fetchArray(SQLITE3_ASSOC)): ?>
        <div class="task-row">
          <div class="task-chk <?= $t['done']?'done':'' ?>"><?= $t['done']?'✓':'' ?></div>
          <div class="task-txt <?= $t['done']?'done':'' ?>"><?= htmlspecialchars($t['task']) ?></div>
          <?php if ($t['subject']): ?><span class="task-subj"><?= htmlspecialchars($t['subject']) ?></span><?php endif; ?>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div style="text-align:center;padding:1.5rem 0;color:var(--muted);">
          <div style="font-size:2.5rem;margin-bottom:8px;opacity:.4;"><img src="https://api.iconify.design/ph/clipboard-bold.svg" style="width:28px;height:28px;opacity:.25"></div>
          <div style="font-size:13px;font-weight:600;margin-bottom:12px;">Chưa có nhiệm vụ hôm nay</div>
          <a href="planner.php" class="btn btn-primary btn-sm">+ Tạo kế hoạch</a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT COL -->
    <div style="display:flex;flex-direction:column;gap:1.2rem;">
      <!-- Week activity -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><img src="https://api.iconify.design/ph/trend-up-bold.svg" style="width:14px;height:14px;vertical-align:middle"> Hoạt động 7 ngày</div>
          <span style="font-size:10px;color:var(--muted);"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--accent)"></span> Pomo &nbsp; <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--green)"></span> Task</span>
        </div>
        <div class="card-body" style="padding-top:.75rem;">
          <div class="week-strip">
            <?php foreach ($week_data as $wd): ?>
            <div class="week-day <?= $wd['is_today']?'today':'' ?> <?= ($wd['done']>0||$wd['pomo']>0)?'has-act':'' ?>">
              <div class="week-day-lbl"><?= $wd['day'] ?></div>
              <div class="week-day-num"><?= $wd['dd'] ?></div>
              <div class="week-dots">
                <?php for($p=0;$p<min($wd['pomo'],3);$p++): ?><div class="dot dot-p"></div><?php endfor; ?>
                <?php for($t2=0;$t2<min($wd['done'],3);$t2++): ?><div class="dot dot-t"></div><?php endfor; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Upcoming -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">⏰ Sắp đến hạn</div>
          <a href="planner.php" class="btn btn-ghost btn-sm">Xem tất cả</a>
        </div>
        <div class="card-body" style="padding-top:.5rem;">
          <?php if(count($upcoming_rows) > 0):
            foreach ($grouped as $gdate => $gtasks):
              $diff = (strtotime($gdate) - strtotime($today)) / 86400;
              $dlabel = $diff==0?'Hôm nay':($diff==1?'Ngày mai':($diff==2?'Ngày kia':date('d/m',strtotime($gdate))));
              $col = $diff==0?'var(--red)':($diff==1?'var(--gold)':'var(--accent)');
          ?>
          <div class="up-hdr"><div class="up-dot" style="background:<?= $col ?>;"></div><?= $dlabel ?></div>
          <?php foreach(array_slice($gtasks,0,3) as $gt): ?>
          <div class="up-item" style="border-left-color:<?= $col ?>;">
            <div style="flex:1;font-size:12px;font-weight:600;color:var(--text);"><?= htmlspecialchars($gt['task']) ?></div>
            <?php if ($gt['subject']): ?><span class="task-subj" style="font-size:9px;"><?= htmlspecialchars($gt['subject']) ?></span><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="text-align:center;color:var(--muted);font-size:12px;padding:1rem 0;font-weight:600;"><img src="https://api.iconify.design/ph/star-bold.svg" style="width:14px;height:14px;vertical-align:middle"> Không có nhiệm vụ sắp đến hạn!</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- AI STUDY PLAN -->
  <div class="card" style="margin-bottom:1.2rem;">
    <div class="card-header">
      <div class="card-title"><img src="https://api.iconify.design/ph/robot-bold.svg" style="width:15px;height:15px;vertical-align:middle"> AI Lập kế hoạch học</div>
      <button onclick="generateStudyPlan()" class="btn btn-primary btn-sm" id="planBtn">Tạo kế hoạch</button>
    </div>
    <div class="card-body">
      <div class="ai-plan-wrap" id="aiPlan">
        <span style="color:var(--muted);">Nhấn "Tạo kế hoạch" để AI đề xuất lịch học thông minh cho bạn hôm nay.</span>
      </div>
    </div>
  </div>



</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
// Live clock
(function tick(){
  const n=new Date(),h=String(n.getHours()).padStart(2,'0'),m=String(n.getMinutes()).padStart(2,'0');
  const el=document.getElementById('liveClock'); if(el) el.textContent=h+':'+m;
  setTimeout(tick,10000);
})();

// AI Insight
async function loadAIInsight() {
  const el = document.getElementById('aiInsight');
  const btn = document.getElementById('insightRefresh');
  el.innerHTML = '<span style="color:var(--muted);">Đang tải lời khuyên từ AI...</span>';
  if(btn) btn.disabled = true;
  const hour = new Date().getHours();
  const tod = hour < 12 ? 'buổi sáng' : hour < 17 ? 'buổi chiều' : 'buổi tối';
  const msgs = [{role:'user', content:`Tôi đang học vào ${tod}. Hôm nay tôi có ${<?=$total_today?>} nhiệm vụ, đã hoàn thành ${<?=$done_today?>}. Đã học ${<?=$pomo_today['c']?>} pomodoro (${<?=$pomo_today['m']?>} phút). Hãy đưa ra 1 lời khuyên học tập ngắn gọn, truyền cảm hứng và thực tế cho tôi (2-3 câu, bằng tiếng Việt, thân thiện, kèm emoji phù hợp).`}];
  try {
    const res = await fetch('ai_api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'chat',messages:msgs})});
    const data = await res.json();
    el.innerHTML = data.result || 'Hãy giữ vững tinh thần học tập nhé!';
  } catch(e) {
    el.innerHTML = '<img src="https://api.iconify.design/ph/lightbulb-bold.svg" style="width:18px;height:18px;vertical-align:middle;filter:invert(1)"> Mỗi ngày học một điều mới, sau một năm bạn sẽ biết 365 điều mới!';
  }
  if(btn) btn.disabled = false;
}

async function generateStudyPlan() {
  const el = document.getElementById('aiPlan');
  const btn = document.getElementById('planBtn');
  btn.disabled = true; btn.textContent = '⌛ Đang tạo...';
  el.innerHTML = '<span style="color:var(--muted);">AI đang phân tích và tạo kế hoạch...</span>';
  const hour = new Date().getHours();
  const msgs = [{role:'user', content:`Tôi có ${<?=$total_today?>} nhiệm vụ hôm nay, đã làm ${<?=$done_today?>}. Đã học ${<?=$pomo_today['m']?>} phút. Hãy tạo kế hoạch học tập chi tiết cho phần còn lại của ngày hôm nay (từ ${hour}h), chia theo khung giờ pomodoro 25 phút, có nghỉ giải lao. Ngắn gọn, thực tế, bằng tiếng Việt.`}];
  try {
    const res = await fetch('ai_api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'chat',messages:msgs})});
    const data = await res.json();
    el.innerHTML = (data.result||'').replace(/\n/g,'<br>');
  } catch(e) {
    el.innerHTML = 'Không thể tải kế hoạch. Thử lại!';
  }
  btn.disabled = false; btn.innerHTML = 'Tạo lại';
}

// Load on page ready
document.addEventListener('DOMContentLoaded', () => {
  loadAIInsight();
});

function showToast(msg, type='ok') {
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div'); t.className = `toast ${type}`;
  t.textContent = msg; wrap.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = '0.3s'; setTimeout(()=>t.remove(),300); }, 2800);
}
</script>
</body>
</html>
