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
    $week_data[] = [
        'date'     => $d,
        'day'      => $days[$dow],
        'dd'       => date('d', strtotime("-$i days")),
        'done'     => (int)$db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND date='$d' AND done=1")->fetchArray()['c'],
        'pomo'     => (int)$db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND DATE(created_at)='$d'")->fetchArray()['c'],
        'is_today' => $i === 0,
    ];
}

$upcoming_rows = [];
while ($r = $upcoming_q->fetchArray(SQLITE3_ASSOC)) $upcoming_rows[] = $r;
$grouped = [];
foreach ($upcoming_rows as $r) $grouped[$r['date']][] = $r;

$banners = [
    ['icon'=>'🔥','title'=>'Đừng phá vỡ chuỗi học!',   'sub'=>'Hôm nay hãy hoàn thành ít nhất 1 nhiệm vụ để duy trì thói quen.'],
    ['icon'=>'🎯','title'=>'Tập trung 25 phút — nghỉ 5 phút','sub'=>'Kỹ thuật Pomodoro giúp năng suất tăng 40%. Thử ngay hôm nay!'],
    ['icon'=>'📚','title'=>'Kiến thức là sức mạnh',    'sub'=>'Mỗi ngày học một điều mới, sau 1 năm bạn biết 365 điều mới.'],
    ['icon'=>'⚡','title'=>'Bắt đầu ngay đi!',          'sub'=>'Điều khó nhất là bắt đầu. Hãy làm nhiệm vụ dễ nhất trước.'],
    ['icon'=>'🌱','title'=>'Tiến bộ từng ngày',         'sub'=>'Chỉ cần tốt hơn 1% mỗi ngày — sau 1 năm bạn sẽ tốt hơn 37 lần.'],
    ['icon'=>'💡','title'=>'Ôn lại kiến thức cũ',       'sub'=>'Spaced repetition: ôn sau 1 ngày → 3 ngày → 1 tuần → 1 tháng.'],
    ['icon'=>'🧠','title'=>'Ngủ đủ giấc để học tốt',   'sub'=>'Não củng cố ký ức khi ngủ. 7-8 tiếng giúp nhớ bài lâu hơn 40%.'],
];
$banner = $banners[date('N') % count($banners)];
$hour = (int)date('H');
$greeting = $hour < 12 ? '☀️ Buổi sáng tốt lành!' : ($hour < 17 ? '🌤️ Buổi chiều năng suất!' : '🌙 Chúc tối học tốt!');
$nameArr = explode(' ', $user['name']);
$firstName = end($nameArr);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.dash-date{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.dash-name{font-size:2rem;font-weight:800;letter-spacing:-1px;line-height:1.1}
.dash-name span{color:var(--accent)}
.stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.5rem}
.stat-tile{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:transform .15s,box-shadow .15s}
.stat-tile:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
.stat-tile-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-tile-icon.blue{background:var(--accent-soft)}.stat-tile-icon.green{background:var(--green-soft)}.stat-tile-icon.gold{background:var(--gold-soft)}.stat-tile-icon.red{background:var(--red-soft)}
.stat-tile-val{font-size:1.4rem;font-weight:800;color:var(--text);line-height:1;font-family:var(--mono)}
.stat-tile-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
.moti-banner{border-radius:16px;padding:16px 20px;background:linear-gradient(135deg,var(--accent) 0%,#7c3aed 100%);color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:1.5rem;position:relative;overflow:hidden}
.moti-banner::before{content:'';position:absolute;right:-20px;top:-20px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.08)}
.week-strip{display:flex;gap:6px}
.week-day{flex:1;border-radius:12px;padding:10px 6px;display:flex;flex-direction:column;align-items:center;gap:5px;border:1.5px solid var(--border);background:var(--surface2)}
.week-day.today{border-color:var(--accent);background:var(--accent-soft)}
.week-day.has-act{border-color:var(--green)}
.week-day-lbl{font-size:9px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.week-day.today .week-day-lbl{color:var(--accent)}
.week-day-num{font-size:12px;font-weight:800;color:var(--text)}
.week-dots{display:flex;gap:3px;min-height:8px}
.dot{width:6px;height:6px;border-radius:50%}
.dot-p{background:var(--accent)}.dot-t{background:var(--green)}
.task-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)}
.task-row:last-child{border-bottom:none}
.task-chk{width:20px;height:20px;border-radius:50%;border:2px solid var(--border2);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:10px}
.task-chk.done{background:var(--green);border-color:var(--green);color:#fff}
.task-txt{flex:1;font-size:13px;font-weight:600;color:var(--text)}
.task-txt.done{color:var(--muted);text-decoration:line-through}
.task-subj{font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;background:var(--accent-soft);color:var(--accent);flex-shrink:0}
.up-hdr{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);padding:8px 0 4px;display:flex;align-items:center;gap:6px}
.up-hdr::after{content:'';flex:1;height:1px;background:var(--border)}
.up-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.up-item{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:10px;background:var(--surface2);margin-bottom:4px;border-left:3px solid var(--accent)}
.quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.quick-btn{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;border-radius:14px;border:1.5px solid var(--border);background:var(--surface2);text-decoration:none;color:var(--text2);font-size:11px;font-weight:700;transition:all .15s;text-align:center}
.quick-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);transform:translateY(-2px)}
.quick-btn-icon{font-size:22px}

/* ── Hero feature cards ── */
.hero-cards{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:1.2rem;}
.hero-card{position:relative;border-radius:16px;padding:22px 22px 20px;text-decoration:none;
  display:flex;flex-direction:column;gap:10px;overflow:hidden;transition:transform .18s,box-shadow .18s;}
.hero-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,.18);}
.hero-card-mindmap{background:linear-gradient(135deg,#3b5bdb 0%,#6741d9 100%);}
.hero-card-math{background:linear-gradient(135deg,#0f766e 0%,#0891b2 100%);}
.hero-card-icon{width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.18);
  display:flex;align-items:center;justify-content:center;}
.hero-card-icon svg{width:24px;height:24px;stroke:#fff;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.hero-card-title{font-size:17px;font-weight:800;color:#fff;letter-spacing:-.3px;}
.hero-card-sub{font-size:12px;color:rgba(255,255,255,.75);font-weight:500;line-height:1.4;}
.hero-card-arrow{margin-top:auto;align-self:flex-start;background:rgba(255,255,255,.2);color:#fff;
  border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:6px 14px;
  font-size:12px;font-weight:700;font-family:var(--font);}
.hero-card:hover .hero-card-arrow{background:rgba(255,255,255,.3);}
/* decorative circle */
.hero-card::after{content:'';position:absolute;right:-30px;top:-30px;
  width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none;}
@media(max-width:600px){
  .hero-cards{grid-template-columns:1fr;}
}
@media(max-width:768px){.stat-strip{grid-template-columns:repeat(2,1fr)}.dash-name{font-size:1.5rem}}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">

  <!-- GREETING -->
  <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
    <div>
      <div class="dash-date"><?= $dayVi ?>, ngày <?= date('d/m/Y') ?></div>
      <h1 class="dash-name">Chào <span><?= htmlspecialchars($firstName) ?></span>! 👋</h1>
    </div>
    <div style="text-align:right;">
      <div style="font-size:13px;font-weight:700;color:var(--text2);"><?= $greeting ?></div>
      <div style="font-size:13px;color:var(--muted);font-family:var(--mono);margin-top:2px;" id="liveClock">--:--:--</div>
    </div>
  </div>

  <!-- BANNER -->
  <div class="moti-banner">
    <div style="font-size:2rem;flex-shrink:0;z-index:1;"><?= $banner['icon'] ?></div>
    <div style="z-index:1;">
      <div style="font-size:14px;font-weight:800;margin-bottom:2px;"><?= $banner['title'] ?></div>
      <div style="font-size:12px;opacity:.85;"><?= $banner['sub'] ?></div>
    </div>
    <a href="pomodoro.php" style="background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.3);font-size:12px;margin-left:auto;z-index:1;flex-shrink:0;white-space:nowrap;padding:8px 14px;border-radius:10px;text-decoration:none;font-weight:700;">🍅 Bắt đầu học</a>
  </div>

  <!-- STATS -->
  <div class="stat-strip">
    <div class="stat-tile"><div class="stat-tile-icon green">✅</div><div><div class="stat-tile-val"><?= $done_today ?>/<?= $total_today ?: '–' ?></div><div class="stat-tile-label">Nhiệm vụ hôm nay</div></div></div>
    <div class="stat-tile"><div class="stat-tile-icon blue">🍅</div><div><div class="stat-tile-val"><?= $pomo_today['c'] ?></div><div class="stat-tile-label">Pomodoro hôm nay</div></div></div>
    <div class="stat-tile"><div class="stat-tile-icon gold">⏱️</div><div><div class="stat-tile-val"><?= $pomo_today['m'] ?>p</div><div class="stat-tile-label">Phút học hôm nay</div></div></div>
    <div class="stat-tile"><div class="stat-tile-icon red">🗒️</div><div><div class="stat-tile-val"><?= $notes_count ?></div><div class="stat-tile-label">Ghi chú đã tạo</div></div></div>
  </div>

  <!-- HERO FEATURE CARDS -->
  <div class="hero-cards">
    <a href="mindmap.php" class="hero-card hero-card-mindmap">
      <div class="hero-card-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><line x1="12" y1="9" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="15"/><line x1="9" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="15" y2="12"/><circle cx="12" cy="3" r="1.5"/><circle cx="12" cy="21" r="1.5"/><circle cx="3" cy="12" r="1.5"/><circle cx="21" cy="12" r="1.5"/></svg>
      </div>
      <div class="hero-card-title">Mind Map</div>
      <div class="hero-card-sub">Sơ đồ tư duy trực quan · Kết nối ý tưởng · Ghi nhớ sâu hơn</div>
      <div class="hero-card-arrow">Mở Mind Map →</div>
    </a>
    <a href="math.php" class="hero-card hero-card-math">
      <div class="hero-card-icon">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/><line x1="5" y1="7" x2="10" y2="7"/><line x1="14" y1="17" x2="19" y2="17"/></svg>
      </div>
      <div class="hero-card-title">Toán học</div>
      <div class="hero-card-sub">Giải toán tức thì · Vẽ đồ thị · Luyện tập mỗi ngày</div>
      <div class="hero-card-arrow">Mở Toán học →</div>
    </a>
  </div>

  <!-- MAIN GRID -->
  <div class="grid-2" style="gap:1.2rem;margin-bottom:1.2rem;">

    <!-- TODAY TASKS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">📅 Nhiệm vụ hôm nay</div>
        <div style="display:flex;align-items:center;gap:8px;">
          <?php if ($total_today > 0): ?><span style="font-size:11px;font-weight:800;color:var(--green);"><?= $done_today ?>/<?= $total_today ?></span><?php endif; ?>
          <a href="planner.php" class="btn btn-ghost btn-sm">+ Thêm</a>
        </div>
      </div>
      <div class="card-body">
        <?php if ($total_today > 0): ?>
        <div style="margin-bottom:12px;">
          <div class="progress-wrap"><div class="progress-fill" style="width:<?= $total_today>0?round($done_today/$total_today*100):0 ?>%;background:var(--green);transition:width .8s;"></div></div>
          <div style="font-size:10px;color:var(--muted);margin-top:4px;font-weight:700;"><?= $total_today>0?round($done_today/$total_today*100):0 ?>% hoàn thành</div>
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
          <div style="font-size:2.5rem;margin-bottom:8px;opacity:.4;">📋</div>
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
          <div class="card-title">📈 Hoạt động 7 ngày</div>
          <span style="font-size:10px;color:var(--muted);">🔵 Pomo &nbsp; 🟢 Task</span>
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
          <?php if (count($upcoming_rows) > 0):
            foreach ($grouped as $gdate => $gtasks):
              $diff = (strtotime($gdate) - strtotime($today)) / 86400;
              $dlabel = $diff==0?'Hôm nay':($diff==1?'Ngày mai':($diff==2?'Ngày kia':date('d/m',strtotime($gdate))));
              $col = $diff==0?'var(--red)':($diff==1?'var(--gold)':'var(--accent)');
          ?>
          <div class="up-hdr"><div class="up-dot" style="background:<?= $col ?>;"></div><?= $dlabel ?></div>
          <?php foreach (array_slice($gtasks,0,3) as $gt): ?>
          <div class="up-item" style="border-left-color:<?= $col ?>;">
            <div style="flex:1;font-size:12px;font-weight:600;color:var(--text);"><?= htmlspecialchars($gt['task']) ?></div>
            <?php if ($gt['subject']): ?><span class="task-subj" style="font-size:9px;"><?= htmlspecialchars($gt['subject']) ?></span><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="text-align:center;color:var(--muted);font-size:12px;padding:1rem 0;font-weight:600;">🎉 Không có nhiệm vụ sắp đến hạn!</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- QUICK ACCESS -->
  <div class="card">
    <div class="card-header"><div class="card-title">⚡ Truy cập nhanh</div></div>
    <div class="card-body">
      <div class="quick-grid">
        <a href="chat.php"      class="quick-btn"><span class="quick-btn-icon">🧠</span>Chat AI</a>
        <a href="flashcard.php" class="quick-btn"><span class="quick-btn-icon">⚡</span>Flashcard</a>
        <a href="notes.php"     class="quick-btn"><span class="quick-btn-icon">🗒️</span>Ghi chú</a>
        <a href="planner.php"   class="quick-btn"><span class="quick-btn-icon">📅</span>Kế hoạch</a>
        <a href="pomodoro.php"  class="quick-btn"><span class="quick-btn-icon">🍅</span>Pomodoro</a>
        <a href="mindmap.php"   class="quick-btn"><span class="quick-btn-icon">🗺️</span>Mind Map</a>
        <a href="math.php"      class="quick-btn"><span class="quick-btn-icon">📐</span>Toán</a>
        <a href="quiz.php"      class="quick-btn"><span class="quick-btn-icon">🎯</span>Quiz</a>
      </div>
    </div>
  </div>

</div>
<script>
(function tick(){
  const n=new Date(),h=String(n.getHours()).padStart(2,'0'),m=String(n.getMinutes()).padStart(2,'0'),s=String(n.getSeconds()).padStart(2,'0');
  const el=document.getElementById('liveClock'); if(el) el.textContent=h+':'+m+':'+s;
  setTimeout(tick,1000);
})();
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.stat-tile').forEach((el,i)=>{
    el.style.opacity='0'; el.style.transform='translateY(10px)';
    setTimeout(()=>{ el.style.transition='opacity .4s,transform .4s'; el.style.opacity='1'; el.style.transform='translateY(0)'; }, i*80);
  });
});
</script>
</body>
</html>
