<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];

// Lưu session hoàn thành
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_session') {
        $type     = in_array($_POST['type'] ?? '', ['focus','short','long']) ? $_POST['type'] : 'focus';
        $duration = (int)($_POST['duration'] ?? 25);
        $subject  = trim($_POST['subject'] ?? '');
        $stmt = $db->prepare('INSERT INTO pomodoro_sessions (user_id, type, duration, completed, subject) VALUES (:uid, :type, :dur, 1, :sub)');
        $stmt->bindValue(':uid', $uid);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':dur', $duration);
        $stmt->bindValue(':sub', $subject);
        $stmt->execute();
        echo json_encode(['ok' => true]); exit;
    }
}

// Stats hôm nay
$today = date('Y-m-d');
$stats_today = $db->query("SELECT COUNT(*) as sessions, SUM(duration) as minutes FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND DATE(created_at)='$today'")->fetchArray(SQLITE3_ASSOC);
$stats_week  = $db->query("SELECT COUNT(*) as sessions, SUM(duration) as minutes FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND DATE(created_at) >= DATE('now','-6 days')")->fetchArray(SQLITE3_ASSOC);
$stats_total = $db->query("SELECT COUNT(*) as sessions FROM pomodoro_sessions WHERE user_id=$uid AND type='focus'")->fetchArray(SQLITE3_ASSOC);

// Lịch sử 10 session gần nhất
$history = $db->query("SELECT * FROM pomodoro_sessions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 10");

// Chart 7 ngày
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $label = $i === 0 ? 'Hôm nay' : date('d/m', strtotime("-$i days"));
    $row = $db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND DATE(created_at)='$d'")->fetchArray();
    $chart_data[] = ['label' => $label, 'count' => (int)$row['c']];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pomodoro — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
/* ── TIMER RING ── */
.pomo-wrap {
  display: flex; flex-direction: column; align-items: center;
  padding: 2rem 0 1rem;
}
.pomo-ring-wrap {
  position: relative; width: 240px; height: 240px;
  filter: drop-shadow(0 8px 32px rgba(79,110,247,0.25));
}
.pomo-svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.pomo-track { fill: none; stroke: var(--surface2); stroke-width: 10; }
.pomo-progress {
  fill: none; stroke-width: 10;
  stroke-linecap: round;
  transition: stroke-dashoffset 1s linear, stroke 0.4s;
}
.pomo-center {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  text-align: center; pointer-events: none;
}
.pomo-time {
  font-size: 3rem; font-weight: 800; letter-spacing: -2px;
  color: var(--text); font-family: var(--mono); line-height: 1;
}
.pomo-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--muted); margin-top: 4px;
}
.pomo-session-dots {
  display: flex; gap: 6px; margin-top: 6px; justify-content: center;
}
.pomo-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--border2); transition: background 0.3s;
}
.pomo-dot.done { background: var(--accent); }

/* ── MODE TABS ── */
.pomo-modes {
  display: flex; gap: 4px; background: var(--surface2);
  border-radius: 12px; padding: 4px; margin-bottom: 1.5rem;
}
.pomo-mode {
  flex: 1; padding: 8px 14px; border-radius: 9px; border: none;
  background: transparent; color: var(--muted); cursor: pointer;
  font-family: var(--font); font-weight: 700; font-size: 12px;
  transition: all 0.15s; text-align: center; white-space: nowrap;
}
.pomo-mode.active { background: var(--surface); color: var(--text); box-shadow: var(--shadow); }
.pomo-mode.focus.active { background: var(--accent); color: #fff; }
.pomo-mode.short.active { background: var(--green); color: #fff; }
.pomo-mode.long.active  { background: var(--gold); color: #fff; }

/* ── CONTROLS ── */
.pomo-controls { display: flex; gap: 10px; align-items: center; margin-top: 1.5rem; }
.pomo-btn-play {
  width: 64px; height: 64px; border-radius: 50%; border: none;
  background: var(--accent); color: #fff; font-size: 22px; cursor: pointer;
  box-shadow: 0 4px 20px rgba(79,110,247,0.4);
  transition: all 0.15s; display: flex; align-items: center; justify-content: center;
}
.pomo-btn-play:hover { transform: scale(1.07); box-shadow: 0 6px 24px rgba(79,110,247,0.5); }
.pomo-btn-play:active { transform: scale(0.97); }
.pomo-btn-play.running { background: var(--surface2); color: var(--text); box-shadow: none; }
.pomo-btn-reset {
  width: 44px; height: 44px; border-radius: 50%; border: 1.5px solid var(--border);
  background: var(--surface2); color: var(--muted); font-size: 16px; cursor: pointer;
  transition: all 0.15s; display: flex; align-items: center; justify-content: center;
}
.pomo-btn-reset:hover { color: var(--text); border-color: var(--border2); }

/* ── SUBJECT INPUT ── */
.pomo-subject-wrap { width: 100%; max-width: 320px; margin-top: 1rem; }

/* ── SOUND TOGGLE ── */
.sound-toggle {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; border-radius: 10px; border: 1.5px solid var(--border);
  background: var(--surface2); cursor: pointer; font-size: 12px; font-weight: 600;
  color: var(--text2); transition: all 0.15s; user-select: none;
}
.sound-toggle:hover { border-color: var(--border2); }
.sound-toggle.on { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

/* ── STAT CARDS ── */
.pomo-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 1.5rem; }
.pomo-stat {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 14px; padding: 16px; text-align: center;
}
.pomo-stat-num {
  font-size: 1.8rem; font-weight: 800; color: var(--accent);
  font-family: var(--mono); line-height: 1;
}
.pomo-stat-label { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

/* ── CHART BARS ── */
.chart-wrap {
  display: flex; align-items: flex-end; gap: 8px; height: 80px; padding: 0 4px;
}
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.chart-bar {
  width: 100%; border-radius: 6px 6px 2px 2px;
  background: var(--accent-soft); border: 1.5px solid var(--accent);
  min-height: 4px; transition: height 0.6s cubic-bezier(0.4,0,0.2,1);
  position: relative;
}
.chart-bar:hover::after {
  content: attr(data-count);
  position: absolute; top: -22px; left: 50%; transform: translateX(-50%);
  background: var(--accent); color: #fff; font-size: 10px; font-weight: 700;
  padding: 2px 6px; border-radius: 5px; white-space: nowrap;
}
.chart-lbl { font-size: 10px; color: var(--muted); font-weight: 600; }

/* ── HISTORY ── */
.history-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-radius: 10px; background: var(--surface2);
  border: 1px solid var(--border); margin-bottom: 6px;
}
.history-icon { font-size: 18px; flex-shrink: 0; }
.history-info { flex: 1; min-width: 0; }
.history-title { font-size: 13px; font-weight: 700; color: var(--text); }
.history-sub { font-size: 11px; color: var(--muted); }
.history-time { font-size: 11px; color: var(--muted); flex-shrink: 0; font-family: var(--mono); }

/* ── COMPLETION OVERLAY ── */
.pomo-overlay {
  display: none; position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.6); backdrop-filter: blur(8px);
  align-items: center; justify-content: center;
}
.pomo-overlay.show { display: flex; }
.pomo-overlay-box {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 24px; padding: 2.5rem; text-align: center;
  max-width: 340px; width: 90%;
  animation: popIn 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes popIn { from { transform: scale(0.7); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.pomo-emoji { font-size: 3.5rem; margin-bottom: 0.5rem; }
.pomo-overlay-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; }
.pomo-overlay-sub { color: var(--muted); font-size: 14px; margin-bottom: 1.5rem; }

@media(max-width:640px) {
  .pomo-stats { grid-template-columns: repeat(3,1fr); gap: 8px; }
  .pomo-stat-num { font-size: 1.4rem; }
  .pomo-ring-wrap { width: 200px; height: 200px; }
  .pomo-time { font-size: 2.5rem; }
}

/* ── MUSIC CARD ── */
.music-tabs { display:flex; gap:3px; background:var(--surface2); border-radius:10px; padding:3px; margin-bottom:12px; }
.music-tab { flex:1; padding:7px; border-radius:8px; border:none; background:transparent; color:var(--muted); cursor:pointer; font-family:var(--font); font-weight:700; font-size:11px; transition:all .15s; }
.music-tab.active { background:var(--accent); color:#fff; box-shadow:0 2px 8px rgba(79,110,247,.3); }

/* Search tab */
.music-search-row { display:flex; gap:6px; }
.music-search-row input { flex:1; padding:9px 12px; border-radius:10px; border:1.5px solid var(--border); background:var(--surface2); color:var(--text); font-family:var(--font); font-size:13px; outline:none; transition:border-color .15s; }
.music-search-row input:focus { border-color:var(--accent); }
.music-search-row input::placeholder { color:var(--muted); }

/* Chill cards grid */
.chill-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.chill-card {
  border-radius:12px; overflow:hidden; position:relative;
  cursor:pointer; transition:transform .18s, box-shadow .18s;
  border:1.5px solid var(--border);
}
.chill-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.chill-card.playing { border-color:var(--accent); box-shadow:0 0 0 2px var(--accent-soft); }
.chill-thumb {
  width:100%; height:80px; object-fit:cover; display:block;
  background:var(--surface2);
}
.chill-info { padding:8px 10px; background:var(--surface); }
.chill-title { font-size:11px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:2px; }
.chill-channel { font-size:10px; color:var(--muted); }
.chill-play-btn {
  position:absolute; top:50%; left:50%; transform:translate(-50%,-60%);
  width:32px; height:32px; border-radius:50%;
  background:rgba(0,0,0,.55); backdrop-filter:blur(4px);
  border:2px solid rgba(255,255,255,.7); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-size:11px; opacity:0; transition:opacity .15s;
  pointer-events:none;
}
.chill-card:hover .chill-play-btn { opacity:1; }
.chill-card.playing .chill-play-btn { opacity:1; background:var(--accent); border-color:var(--accent); }

/* Now playing bar */
.now-playing-bar {
  display:none; align-items:center; gap:10px;
  padding:10px 14px; border-radius:12px;
  background:var(--accent-soft); border:1.5px solid var(--accent);
  margin-top:10px;
}
.now-playing-bar.show { display:flex; }
.np-eq { display:flex; gap:2px; align-items:flex-end; height:14px; flex-shrink:0; }
.np-eq span { width:3px; border-radius:2px; background:var(--accent); animation:eq .6s ease-in-out infinite alternate; }
.np-eq span:nth-child(1){height:5px;animation-delay:0s}
.np-eq span:nth-child(2){height:10px;animation-delay:.15s}
.np-eq span:nth-child(3){height:14px;animation-delay:.3s}
.np-eq span:nth-child(4){height:7px;animation-delay:.45s}
@keyframes eq { from{transform:scaleY(.3)} to{transform:scaleY(1)} }
.np-title { flex:1; font-size:12px; font-weight:700; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.np-open { font-size:11px; font-weight:700; color:var(--accent); text-decoration:none; flex-shrink:0; }
.np-open:hover { text-decoration:underline; }
.np-stop { background:none; border:none; color:var(--accent); cursor:pointer; font-size:14px; flex-shrink:0; }

/* Mood pills */
.mood-pills { display:flex; gap:5px; flex-wrap:wrap; margin-bottom:10px; }
.mood-pill {
  padding:5px 11px; border-radius:20px; border:1.5px solid var(--border);
  background:var(--surface2); color:var(--text2); font-size:11px; font-weight:700;
  cursor:pointer; transition:all .15s; white-space:nowrap;
}
.mood-pill:hover,.mood-pill.active { border-color:var(--accent); background:var(--accent-soft); color:var(--accent); }

/* Embed */
.yt-embed-wrap { display:none; margin-top:10px; border-radius:12px; overflow:hidden; position:relative; }
.yt-embed-wrap.show { display:block; }
.yt-embed-wrap iframe { display:block; width:100%; height:180px; border:none; }
.yt-embed-close { position:absolute; top:6px; right:6px; background:rgba(0,0,0,.65); border:none; color:#fff; border-radius:50%; width:26px; height:26px; cursor:pointer; font-size:13px; z-index:2; display:flex; align-items:center; justify-content:center; }

@keyframes spin { to{transform:rotate(360deg)} }

/* ── FULLSCREEN TIMER ── */
#fsTimerOverlay {
  background: var(--bg);
}
#fsTime {
  text-shadow: 0 4px 40px rgba(79,110,247,0.3);
}
#fsPlayBtn:hover { transform: scale(1.07); }
#fsPlayBtn:active { transform: scale(0.96); }
</style>
</head>
<body>
<?php require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Năng suất</div>
    <h1 class="page-title">🍅 Pomodoro Timer</h1>
    <div class="page-sub">Tập trung sâu · Nghỉ ngơi đúng lúc · Học hiệu quả hơn</div>
  </div>

  <!-- STAT CARDS -->
  <div class="pomo-stats">
    <div class="pomo-stat">
      <div class="pomo-stat-num" id="statToday"><?= (int)$stats_today['sessions'] ?></div>
      <div class="pomo-stat-label">Hôm nay</div>
    </div>
    <div class="pomo-stat">
      <div class="pomo-stat-num"><?= (int)$stats_week['sessions'] ?></div>
      <div class="pomo-stat-label">7 ngày</div>
    </div>
    <div class="pomo-stat">
      <div class="pomo-stat-num"><?= (int)$stats_today['minutes'] ?: 0 ?></div>
      <div class="pomo-stat-label">Phút hôm nay</div>
    </div>
  </div>

  <div class="grid-2" style="gap:1.5rem; align-items:start;">

    <!-- LEFT: TIMER -->
    <div class="card">
      <div class="card-body" style="padding:1.5rem;">

        <!-- Mode selector -->
        <div class="pomo-modes" id="pomModes">
          <button class="pomo-mode focus active" onclick="setMode('focus',25)">🍅 Tập trung</button>
          <button class="pomo-mode short" onclick="setMode('short',5)">☕ Nghỉ ngắn</button>
          <button class="pomo-mode long"  onclick="setMode('long',15)">🌿 Nghỉ dài</button>
        </div>

        <!-- Ring timer -->
        <div class="pomo-wrap">
          <div class="pomo-ring-wrap">
            <svg class="pomo-svg" width="240" height="240" viewBox="0 0 240 240">
              <circle class="pomo-track" cx="120" cy="120" r="108"/>
              <circle class="pomo-progress" id="pomRing" cx="120" cy="120" r="108"
                stroke="#4f6ef7"
                stroke-dasharray="678.6"
                stroke-dashoffset="0"/>
            </svg>
            <div class="pomo-center">
              <div class="pomo-time" id="pomTime">25:00</div>
              <div class="pomo-label" id="pomModeLabel">FOCUS</div>
              <div class="pomo-session-dots" id="pomDots">
                <div class="pomo-dot"></div><div class="pomo-dot"></div>
                <div class="pomo-dot"></div><div class="pomo-dot"></div>
              </div>
            </div>
          </div>

          <!-- Controls -->
          <div class="pomo-controls">
            <button class="pomo-btn-reset" onclick="pomReset()" title="Reset">↺</button>
            <button class="pomo-btn-play" id="pomPlayBtn" onclick="pomToggle()">▶</button>
            <button class="pomo-btn-reset" onclick="pomSkip()" title="Bỏ qua">⏭</button>
          </div>

          <!-- Subject -->
          <div class="pomo-subject-wrap">
            <input type="text" id="pomSubject" class="form-input" placeholder="📚 Đang học gì? (tuỳ chọn)">
          </div>

          <!-- Sound toggle -->
          <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;justify-content:center;">
            <div class="sound-toggle on" id="soundToggle" onclick="toggleSound()">
              🔔 Âm thanh
            </div>
            <div class="sound-toggle on" id="ambientToggle" onclick="toggleAmbient()">
              🌧 Lo-fi
            </div>
          </div>
        </div>

        <!-- Custom time -->
        <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:0.5rem;">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Tuỳ chỉnh thời gian</div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="number" id="customMin" min="1" value="25" class="form-input" style="width:80px;text-align:center;" oninput="">
            <!-- Unit toggle -->
            <div style="display:flex;gap:2px;background:var(--surface2);border-radius:8px;padding:3px;">
              <button id="unitMin" onclick="setUnit('min')" style="padding:5px 10px;border-radius:6px;border:none;background:var(--accent);color:#fff;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;">phút</button>
              <button id="unitHour" onclick="setUnit('hour')" style="padding:5px 10px;border-radius:6px;border:none;background:transparent;color:var(--muted);font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;">giờ</button>
            </div>
            <button class="btn btn-ghost btn-sm" onclick="setCustom()">Áp dụng</button>
          </div>
          <div id="customHint" style="font-size:10px;color:var(--muted);margin-top:5px;">Tối đa không giới hạn · hiện tại: <span id="customPreview">25 phút</span></div>
        </div>

      </div>
    </div>

    <!-- RIGHT: CHART + HISTORY -->
    <div style="display:flex;flex-direction:column;gap:1.2rem;">

      <!-- 7-day chart -->
      <div class="card">
        <div class="card-header"><div class="card-title">📈 Hoạt động 7 ngày</div></div>
        <div class="card-body">
          <?php
          $counts = array_column($chart_data, 'count');
          $maxCount = max(array_merge([1], $counts));
          ?>
          <div class="chart-wrap">
            <?php foreach ($chart_data as $d): ?>
            <div class="chart-col">
              <div class="chart-bar"
                style="height:<?= max(4, round($d['count'] / $maxCount * 70)) ?>px"
                data-count="<?= $d['count'] ?> 🍅"></div>
              <span class="chart-lbl"><?= htmlspecialchars($d['label']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:8px;font-size:11px;color:var(--muted);text-align:center;">
            Tổng: <strong style="color:var(--accent)"><?= (int)$stats_total['sessions'] ?></strong> sessions · <strong style="color:var(--accent)"><?= round((int)$stats_week['minutes'] / 60, 1) ?>h</strong> tuần này
          </div>
        </div>
      </div>

      <!-- Session history -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">🕐 Lịch sử gần đây</div>
        </div>
        <div class="card-body" style="padding-top:0.5rem;" id="historyList">
          <?php
          $hasHistory = false;
          while ($row = $history->fetchArray(SQLITE3_ASSOC)):
            $hasHistory = true;
            $icon = $row['type'] === 'focus' ? '🍅' : ($row['type'] === 'short' ? '☕' : '🌿');
            $label = $row['type'] === 'focus' ? 'Tập trung' : ($row['type'] === 'short' ? 'Nghỉ ngắn' : 'Nghỉ dài');
            $timeAgo = '';
            $created = strtotime($row['created_at']);
            $diff = time() - $created;
            if ($diff < 60) $timeAgo = 'vừa xong';
            elseif ($diff < 3600) $timeAgo = round($diff/60) . ' phút trước';
            elseif ($diff < 86400) $timeAgo = round($diff/3600) . 'h trước';
            else $timeAgo = date('d/m', $created);
          ?>
          <div class="history-item">
            <div class="history-icon"><?= $icon ?></div>
            <div class="history-info">
              <div class="history-title"><?= $label ?> · <?= $row['duration'] ?> phút</div>
              <?php if ($row['subject']): ?><div class="history-sub">📚 <?= htmlspecialchars($row['subject']) ?></div><?php endif; ?>
            </div>
            <div class="history-time"><?= $timeAgo ?></div>
          </div>
          <?php endwhile; ?>
          <?php if (!$hasHistory): ?>
          <div style="text-align:center;color:var(--muted);font-size:13px;padding:1rem 0;">
            Chưa có session nào. Bắt đầu thôi! 🍅
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 🎵 MUSIC -->
      <div class="card" id="musicCard">
        <div class="card-header">
          <div class="card-title">🎵 Nhạc học bài</div>
          <button class="btn btn-ghost btn-sm" onclick="shuffleChill()" style="margin-left:auto;">🔀 Shuffle</button>
        </div>
        <div class="card-body" style="padding-top:0.75rem;">

          <!-- Tabs -->
          <div class="music-tabs">
            <button class="music-tab active" id="tabChill" onclick="switchMusicTab('chill')">🎧 Đề xuất</button>
            <button class="music-tab" id="tabSearch" onclick="switchMusicTab('search')">🔍 Tìm nhạc</button>
          </div>

          <!-- TAB: Đề xuất chill -->
          <div id="panelChill">
            <div class="mood-pills" id="moodPills">
              <button class="mood-pill active" data-mood="chill"  onclick="setMood('chill',this)">☕ Chill</button>
              <button class="mood-pill" data-mood="study" onclick="setMood('study',this)">📚 Học bài</button>
              <button class="mood-pill" data-mood="focus" onclick="setMood('focus',this)">🎯 Focus</button>
              <button class="mood-pill" data-mood="sleep" onclick="setMood('sleep',this)">🌙 Ngủ</button>
              <button class="mood-pill" data-mood="jazz"  onclick="setMood('jazz',this)">🎷 Jazz</button>
              <button class="mood-pill" data-mood="piano" onclick="setMood('piano',this)">🎹 Piano</button>
            </div>
            <div class="chill-grid" id="chillGrid"></div>
          </div>

          <!-- TAB: Tìm nhạc -->
          <div id="panelSearch" style="display:none;">
            <div class="music-search-row">
              <input type="text" id="musicSearchInput"
                placeholder="Tìm bất kỳ bài nhạc, video nào..."
                onkeydown="if(event.key==='Enter')doSearch()">
              <button class="btn btn-primary" onclick="doSearch()" style="flex-shrink:0;">Tìm ↗</button>
            </div>
            <div style="margin-top:8px;" id="searchSuggest">
              <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Gợi ý tìm kiếm nhanh:</div>
              <div style="display:flex;flex-wrap:wrap;gap:5px;" id="quickSearchTags"></div>
            </div>
            <div id="searchNote" style="display:none;margin-top:10px;padding:12px;background:var(--surface2);border-radius:10px;text-align:center;">
              <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:4px;" id="searchNoteTitle"></div>
              <div style="font-size:11px;color:var(--muted);" id="searchNoteDesc"></div>
            </div>
          </div>

          <!-- Embed player -->
          <div class="yt-embed-wrap" id="ytEmbedWrap">
            <button class="yt-embed-close" onclick="closeEmbed()">✕</button>
            <iframe id="ytEmbed" allowfullscreen
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
          </div>

          <!-- Now playing bar -->
          <div class="now-playing-bar" id="nowPlayingBar">
            <div class="np-eq"><span></span><span></span><span></span><span></span></div>
            <div class="np-title" id="npTitle">Đang phát...</div>
            <a class="np-open" id="npYtLink" href="#" target="_blank" rel="noopener">▶ YouTube</a>
            <button class="np-stop" onclick="closeEmbed()" title="Dừng">⏹</button>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<!-- FULLSCREEN START DIALOG -->
<div class="pomo-overlay" id="fsDialog">
  <div class="pomo-overlay-box" style="max-width:320px;">
    <div class="pomo-emoji">🚀</div>
    <div class="pomo-overlay-title">Bắt đầu thôi!</div>
    <div class="pomo-overlay-sub" id="fsDialogSub">Bạn muốn bật chế độ toàn màn hình không?</div>
    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
      <button class="btn btn-ghost" onclick="startWithFullscreen(false)">🪟 Thường</button>
      <button class="btn btn-primary" onclick="startWithFullscreen(true)">⛶ Toàn màn hình</button>
    </div>
  </div>
</div>

<!-- FULLSCREEN OVERLAY (shown when in fullscreen mode) -->
<div id="fsTimerOverlay" style="display:none;position:fixed;inset:0;z-index:9000;background:var(--bg);flex-direction:column;align-items:center;justify-content:center;gap:20px;">
  <div style="position:absolute;top:20px;right:20px;">
    <button onclick="exitFullscreenMode()" style="padding:8px 16px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-weight:700;cursor:pointer;font-size:13px;">✕ Thoát</button>
  </div>
  <div style="text-align:center;">
    <div id="fsModeLabel" style="font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:var(--muted);margin-bottom:12px;">FOCUS</div>
    <div id="fsTime" style="font-size:clamp(5rem,15vw,10rem);font-weight:900;font-family:var(--mono);color:var(--text);line-height:1;letter-spacing:-4px;">25:00</div>
    <div id="fsSubject" style="font-size:1rem;color:var(--muted);margin-top:10px;"></div>
  </div>
  <div style="display:flex;gap:16px;align-items:center;">
    <button onclick="pomReset();syncFS()" style="width:52px;height:52px;border-radius:50%;border:1.5px solid var(--border);background:var(--surface2);color:var(--muted);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;">↺</button>
    <button id="fsPlayBtn" onclick="pomToggle();syncFS()" style="width:80px;height:80px;border-radius:50%;border:none;background:var(--accent);color:#fff;font-size:28px;cursor:pointer;box-shadow:0 4px 24px rgba(79,110,247,0.4);display:flex;align-items:center;justify-content:center;transition:all .15s;">▶</button>
    <button onclick="pomSkip()" style="width:52px;height:52px;border-radius:50%;border:1.5px solid var(--border);background:var(--surface2);color:var(--muted);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;">⏭</button>
  </div>
  <div id="fsDots" style="display:flex;gap:8px;"></div>
</div>

<!-- COMPLETION OVERLAY -->
<div class="pomo-overlay" id="pomOverlay">
  <div class="pomo-overlay-box">
    <div class="pomo-emoji" id="overlayEmoji">🎉</div>
    <div class="pomo-overlay-title" id="overlayTitle">Hoàn thành!</div>
    <div class="pomo-overlay-sub" id="overlaySub">Tuyệt vời! Đã xong 1 pomodoro.</div>
    <div style="display:flex;gap:8px;justify-content:center;">
      <button class="btn btn-ghost" onclick="closeOverlay()">Nghỉ ngơi</button>
      <button class="btn btn-primary" onclick="startNext()" id="overlayNextBtn">Tiếp tục ▶</button>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════════════
//  POMODORO ENGINE
// ══════════════════════════════════════
const CIRCUMFERENCE = 2 * Math.PI * 108; // 678.6
const ring    = document.getElementById('pomRing');
const timeEl  = document.getElementById('pomTime');
const labelEl = document.getElementById('pomModeLabel');
const playBtn = document.getElementById('pomPlayBtn');

let mode       = 'focus';
let totalSecs  = 25 * 60;
let remaining  = totalSecs;
let running    = false;
let intervalId = null;
let sessionCount = 0;
let soundOn    = true;
let ambientOn  = true;
let ambientCtx = null;
let ambientSource = null;
let currentUnit = 'min'; // 'min' or 'hour'
let fsMode = false; // fullscreen mode active

const MODES = {
  focus: { label: 'FOCUS',      color: '#4f6ef7', mins: 25 },
  short: { label: 'NGHỈ NGẮN',  color: '#34d399', mins: 5  },
  long:  { label: 'NGHỈ DÀI',   color: '#fbbf24', mins: 15 },
};

function setMode(m, mins) {
  if (running) { pomReset(); }
  mode = m;
  totalSecs = mins * 60;
  remaining = totalSecs;
  document.getElementById('customMin').value = mins;
  document.querySelectorAll('.pomo-mode').forEach(b => b.classList.remove('active'));
  document.querySelector(`.pomo-mode.${m}`).classList.add('active');
  ring.setAttribute('stroke', MODES[m].color);
  playBtn.style.background = mode === 'focus' ? '' : (mode === 'short' ? 'var(--green)' : 'var(--gold)');
  labelEl.textContent = MODES[m].label;
  render();
}

function setUnit(unit) {
  currentUnit = unit;
  document.getElementById('unitMin').style.background  = unit === 'min'  ? 'var(--accent)' : 'transparent';
  document.getElementById('unitMin').style.color       = unit === 'min'  ? '#fff' : 'var(--muted)';
  document.getElementById('unitHour').style.background = unit === 'hour' ? 'var(--accent)' : 'transparent';
  document.getElementById('unitHour').style.color      = unit === 'hour' ? '#fff' : 'var(--muted)';
  updateCustomPreview();
}

function updateCustomPreview() {
  const v = parseFloat(document.getElementById('customMin').value) || 1;
  const mins = currentUnit === 'hour' ? Math.round(v * 60) : Math.round(v);
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  let label = '';
  if (h > 0 && m > 0) label = `${h} giờ ${m} phút`;
  else if (h > 0) label = `${h} giờ`;
  else label = `${mins} phút`;
  const previewEl = document.getElementById('customPreview');
  if (previewEl) previewEl.textContent = label;
}

function setCustom() {
  const v = parseFloat(document.getElementById('customMin').value) || 1;
  const mins = currentUnit === 'hour' ? Math.round(v * 60) : Math.round(Math.max(1, v));
  totalSecs = mins * 60;
  remaining = totalSecs;
  if (running) { clearInterval(intervalId); running = false; playBtn.textContent = '▶'; playBtn.classList.remove('running'); }
  updateCustomPreview();
  render();
}

function pomToggle() {
  if (running) {
    clearInterval(intervalId); running = false;
    playBtn.textContent = '▶'; playBtn.classList.remove('running');
    if (fsMode) { document.getElementById('fsPlayBtn').textContent = '▶'; document.getElementById('fsPlayBtn').style.background = 'var(--accent)'; }
    if (ambientOn) stopAmbient();
  } else {
    // First press when not running → show fullscreen dialog
    if (!fsMode) {
      const sub = document.getElementById('pomSubject').value.trim();
      const minsLabel = formatSecsLabel(totalSecs);
      document.getElementById('fsDialogSub').textContent = `Chế độ: ${MODES[mode].label} · ${minsLabel}`;
      document.getElementById('fsDialog').classList.add('show');
      return;
    }
    _startTimer();
  }
}

function _startTimer() {
  running = true;
  playBtn.textContent = '⏸'; playBtn.classList.add('running');
  if (fsMode) { document.getElementById('fsPlayBtn').textContent = '⏸'; document.getElementById('fsPlayBtn').style.background = '#e53e3e'; }
  if (ambientOn) startAmbient();
  intervalId = setInterval(tick, 1000);
}

function startWithFullscreen(goFS) {
  document.getElementById('fsDialog').classList.remove('show');
  if (goFS) {
    enterFullscreenMode();
  }
  _startTimer();
}

function enterFullscreenMode() {
  fsMode = true;
  const overlay = document.getElementById('fsTimerOverlay');
  overlay.style.display = 'flex';
  // Try browser fullscreen
  try { document.documentElement.requestFullscreen && document.documentElement.requestFullscreen(); } catch(e) {}
  syncFS();
}

function exitFullscreenMode() {
  fsMode = false;
  document.getElementById('fsTimerOverlay').style.display = 'none';
  try { document.exitFullscreen && document.exitFullscreen(); } catch(e) {}
}

function syncFS() {
  if (!fsMode) return;
  const m = String(Math.floor(remaining / 60)).padStart(2, '0');
  const s = String(remaining % 60).padStart(2, '0');
  document.getElementById('fsTime').textContent = `${m}:${s}`;
  document.getElementById('fsModeLabel').textContent = MODES[mode].label;
  document.getElementById('fsModeLabel').style.color = MODES[mode].color;
  const sub = document.getElementById('pomSubject').value.trim();
  document.getElementById('fsSubject').textContent = sub ? `📚 ${sub}` : '';
  // dots
  const dotsHtml = [0,1,2,3].map(i =>
    `<div style="width:12px;height:12px;border-radius:50%;background:${i < sessionCount % 4 ? MODES[mode].color : 'var(--border2)'};"></div>`
  ).join('');
  document.getElementById('fsDots').innerHTML = dotsHtml;
  // play btn color
  if (running) {
    document.getElementById('fsPlayBtn').textContent = '⏸';
    document.getElementById('fsPlayBtn').style.background = '#e53e3e';
  } else {
    document.getElementById('fsPlayBtn').textContent = '▶';
    document.getElementById('fsPlayBtn').style.background = MODES[mode].color;
  }
}

function formatSecsLabel(secs) {
  const totalMins = Math.round(secs / 60);
  const h = Math.floor(totalMins / 60);
  const m = totalMins % 60;
  if (h > 0 && m > 0) return `${h} giờ ${m} phút`;
  if (h > 0) return `${h} giờ`;
  return `${totalMins} phút`;
}

function pomReset() {
  clearInterval(intervalId); running = false;
  playBtn.textContent = '▶'; playBtn.classList.remove('running');
  remaining = totalSecs;
  if (fsMode) exitFullscreenMode();
  stopAmbient();
  render();
}

function pomSkip() {
  clearInterval(intervalId); running = false;
  remaining = 0;
  stopAmbient();
  if (fsMode) exitFullscreenMode();
  onComplete(false);
}

function tick() {
  remaining--;
  render();
  if (fsMode) syncFS();
  if (remaining <= 0) {
    clearInterval(intervalId); running = false;
    playBtn.textContent = '▶'; playBtn.classList.remove('running');
    stopAmbient();
    if (fsMode) exitFullscreenMode();
    onComplete(true);
  }
}

function render() {
  const h = Math.floor(remaining / 3600);
  const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
  const s = String(remaining % 60).padStart(2, '0');
  const display = h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
  timeEl.textContent = display;
  // Scale font for long timers
  timeEl.style.fontSize = h > 0 ? '2.2rem' : '';
  document.title = running ? `${display} — MindSpark 🍅` : 'Pomodoro — MindSpark';

  const pct = remaining / totalSecs;
  ring.setAttribute('stroke-dashoffset', CIRCUMFERENCE * (1 - pct));
}

function updateDots() {
  document.querySelectorAll('.pomo-dot').forEach((d, i) => {
    d.classList.toggle('done', i < sessionCount % 4);
  });
}

async function onComplete(auto) {
  if (mode === 'focus' && auto) {
    sessionCount++;
    updateDots();
    playBell();
    saveSession();
    // Update stat counter
    const el = document.getElementById('statToday');
    if (el) el.textContent = parseInt(el.textContent || 0) + 1;
  }
  showOverlay(auto);
}

function showOverlay(auto) {
  const isFocus = mode === 'focus';
  document.getElementById('overlayEmoji').textContent = isFocus ? '🎉' : '⏰';
  document.getElementById('overlayTitle').textContent = isFocus ? (auto ? 'Hoàn thành! 🍅' : 'Session kết thúc') : 'Hết giờ nghỉ!';
  document.getElementById('overlaySub').textContent = isFocus
    ? `Tuyệt vời! Đã xong session #${sessionCount}. Nghỉ một chút nhé!`
    : 'Sẵn sàng tập trung tiếp chưa?';
  document.getElementById('overlayNextBtn').textContent = isFocus ? '☕ Nghỉ ngắn' : '🍅 Bắt đầu Focus';
  document.getElementById('pomOverlay').classList.add('show');
}

function closeOverlay() {
  document.getElementById('pomOverlay').classList.remove('show');
  if (mode !== 'focus') {
    setMode('focus', parseInt(document.getElementById('customMin').value) || 25);
  }
}

function startNext() {
  document.getElementById('pomOverlay').classList.remove('show');
  if (mode === 'focus') {
    setMode(sessionCount % 4 === 0 ? 'long' : 'short', sessionCount % 4 === 0 ? 15 : 5);
  } else {
    setMode('focus', parseInt(document.getElementById('customMin').value) || 25);
  }
  _startTimer(); // bypass fullscreen dialog for auto-next
}

// ── Save session to server ──
async function saveSession() {
  const subject = document.getElementById('pomSubject').value.trim();
  const mins = Math.round(totalSecs / 60);
  try {
    await fetch('pomodoro.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=save_session&type=${mode}&duration=${mins}&subject=${encodeURIComponent(subject)}`
    });
  } catch(e) {}
}

// ── Sound via Web Audio API ──
let audioCtx = null;
function getAudioCtx() {
  if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  return audioCtx;
}

function playBell() {
  if (!soundOn) return;
  try {
    const ctx = getAudioCtx();
    [0, 0.2, 0.5].forEach(delay => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.frequency.value = 880;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0.4, ctx.currentTime + delay);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 1.2);
      osc.start(ctx.currentTime + delay);
      osc.stop(ctx.currentTime + delay + 1.3);
    });
  } catch(e) {}
}

function startAmbient() {
  if (!ambientOn || ambientCtx) return;
  try {
    ambientCtx = new (window.AudioContext || window.webkitAudioContext)();
    // Brown noise (lo-fi feel)
    const bufferSize = ambientCtx.sampleRate * 2;
    const buffer = ambientCtx.createBuffer(1, bufferSize, ambientCtx.sampleRate);
    const data = buffer.getChannelData(0);
    let lastOut = 0;
    for (let i = 0; i < bufferSize; i++) {
      const white = Math.random() * 2 - 1;
      data[i] = (lastOut + 0.02 * white) / 1.02;
      lastOut = data[i]; data[i] *= 3.5;
    }
    ambientSource = ambientCtx.createBufferSource();
    ambientSource.buffer = buffer;
    ambientSource.loop = true;
    const gain = ambientCtx.createGain();
    gain.gain.value = 0.06;
    // Low-pass filter for warmth
    const filter = ambientCtx.createBiquadFilter();
    filter.type = 'lowpass'; filter.frequency.value = 400;
    ambientSource.connect(filter); filter.connect(gain); gain.connect(ambientCtx.destination);
    ambientSource.start();
  } catch(e) {}
}

function stopAmbient() {
  if (ambientSource) { try { ambientSource.stop(); } catch(e) {} ambientSource = null; }
  if (ambientCtx) { try { ambientCtx.close(); } catch(e) {} ambientCtx = null; }
}

function toggleSound() {
  soundOn = !soundOn;
  document.getElementById('soundToggle').classList.toggle('on', soundOn);
  document.getElementById('soundToggle').textContent = soundOn ? '🔔 Âm thanh' : '🔕 Tắt tiếng';
}

function toggleAmbient() {
  ambientOn = !ambientOn;
  document.getElementById('ambientToggle').classList.toggle('on', ambientOn);
  document.getElementById('ambientToggle').textContent = ambientOn ? '🌧 Lo-fi' : '🌧 Lo-fi (tắt)';
  if (running) {
    if (ambientOn) startAmbient(); else stopAmbient();
  }
}

// Init
ring.setAttribute('stroke-dasharray', CIRCUMFERENCE);
ring.setAttribute('stroke-dashoffset', 0);
render();
updateCustomPreview();
document.getElementById('customMin').addEventListener('input', updateCustomPreview);

// ══════════════════════════════════════
//  🎵 MUSIC PLAYER
// ══════════════════════════════════════

const CHILL_DB = {
  chill: [
    { id: 'MVPTGNGiI-4', title: 'Coffee Shop Ambience',         channel: 'Relaxing Cafe',       dur: '2h' },
    { id: 'rUxyKA_-grg', title: 'Chillhop Essentials',          channel: 'Chillhop Music',       dur: '1h' },
    { id: 'lTRiuFIWV54', title: 'Japanese City Pop Lofi',       channel: 'Tokyo Lofi',           dur: '1h' },
    { id: 'K-x_qBzCH98', title: 'Lofi Evening Beats',           channel: 'Lofi Coder',           dur: '2h' },
    { id: 'qYnA9wWFHLk', title: 'Acoustic Chill Covers',        channel: 'Chillout Lounge',      dur: '2h' },
    { id: '4xDzrJKXOOY', title: 'Synthwave Retrowave Mix',      channel: 'Synthwave+',           dur: '2h' },
    { id: '9RIb6yjDCNQ', title: 'Lofi Hip Hop Mix',             channel: 'College Music',        dur: '1h' },
    { id: 'n61ULEU7CO0', title: 'Calm Music for Relaxing',      channel: 'Soothing Relaxation',  dur: '3h' },
  ],
  study: [
    { id: '7NOSDKb0HlU', title: 'Deep Focus Study Music',       channel: 'Yellow Brick Cinema',  dur: '3h' },
    { id: 'WPni755-Krg', title: 'Brain Power Focus',            channel: 'Greenred Productions', dur: '3h' },
    { id: 'n61ULEU7CO0', title: 'Relaxing Study Music',         channel: 'Soothing Relaxation',  dur: '3h' },
    { id: 'MVPTGNGiI-4', title: 'Coffee Shop Study Session',    channel: 'Relaxing Cafe',        dur: '2h' },
    { id: 'K-x_qBzCH98', title: 'Lofi Coding & Study',         channel: 'Lofi Coder',           dur: '2h' },
    { id: '9RIb6yjDCNQ', title: 'Lofi Hip Hop Study Beats',    channel: 'College Music',        dur: '1h' },
  ],
  focus: [
    { id: 'WPni755-Krg', title: 'Brain Power Deep Focus',       channel: 'Greenred Productions', dur: '3h' },
    { id: 'hHW1oY26kxQ', title: 'Minimal Techno Focus',         channel: 'Flow State',           dur: '2h' },
    { id: 'sjkrrmBnpGE', title: 'Dark Ambient Concentration',   channel: 'Studying & Working',   dur: '3h' },
    { id: '7NOSDKb0HlU', title: 'Deep Work 4 Hours',            channel: 'Yellow Brick Cinema',  dur: '4h' },
    { id: 'UfcAVejslrU', title: 'Cinematic Focus Music',        channel: 'Epic Music VN',        dur: '2h' },
  ],
  sleep: [
    { id: 'Z5iZ4fBSi8M', title: 'Rain Sounds for Sleep',        channel: 'Rain Sounds',          dur: '8h' },
    { id: 'HuFYqnbVbzY', title: 'Delta Waves Deep Sleep',       channel: 'Jason Stephenson',     dur: '8h' },
    { id: 'YHPN81GaLaE', title: 'Peaceful Sleep Piano',         channel: 'Soothing Relaxation',  dur: '3h' },
    { id: '1ZYbU82GVz4', title: 'Beautiful Piano & Violin',     channel: 'Relaxing Music',       dur: '3h' },
  ],
  jazz: [
    { id: 'rUxyKA_-grg', title: 'Jazz & Lofi Hip Hop',          channel: 'Chillhop Music',       dur: '1h' },
    { id: 'qYnA9wWFHLk', title: 'Smooth Jazz Acoustic',         channel: 'Chillout Lounge',      dur: '2h' },
    { id: 'MVPTGNGiI-4', title: 'Jazz Cafe Ambience',           channel: 'Relaxing Cafe',        dur: '2h' },
    { id: 'K-x_qBzCH98', title: 'Late Night Jazz Lofi',         channel: 'Lofi Coder',           dur: '2h' },
  ],
  piano: [
    { id: 'hlWiI4xVXKY', title: 'Gentle Piano Background',      channel: 'Soothing Relaxation',  dur: '3h' },
    { id: 'YHPN81GaLaE', title: 'Peaceful Piano Ambient',       channel: 'Soothing Relaxation',  dur: '3h' },
    { id: '1ZYbU82GVz4', title: 'Piano & Violin Beautiful',     channel: 'Relaxing Music',       dur: '3h' },
    { id: 'n61ULEU7CO0', title: 'Relaxing Piano Music',         channel: 'Soothing Relaxation',  dur: '3h' },
  ],
};

const QUICK_TAGS = [
  'lofi hip hop','piano chill','jazz café','rain sleep',
  'synthwave','deep focus','acoustic covers','nhạc không lời',
  'sơn tùng mtp','vpop chill','nhạc trẻ 2024','bolero',
];

let currentMood = 'chill';
let currentVideoId = null;
let currentTitle = '';
let musicTab = 'chill';
let shuffled = {};

// ── Tab switch ──
function switchMusicTab(tab) {
  musicTab = tab;
  document.getElementById('tabChill').classList.toggle('active', tab === 'chill');
  document.getElementById('tabSearch').classList.toggle('active', tab === 'search');
  document.getElementById('panelChill').style.display  = tab === 'chill'  ? '' : 'none';
  document.getElementById('panelSearch').style.display = tab === 'search' ? '' : 'none';
}

// ── Mood ──
function setMood(mood, btn) {
  currentMood = mood;
  document.querySelectorAll('.mood-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderChillGrid(true);
}

function shuffleChill() {
  if (musicTab === 'chill') renderChillGrid(true);
  else document.getElementById('musicSearchInput')?.focus();
}

function renderChillGrid(reshuffle = false) {
  const list = CHILL_DB[currentMood] || CHILL_DB.chill;
  if (reshuffle || !shuffled[currentMood]) {
    const idx = [...Array(list.length).keys()];
    for (let i = idx.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [idx[i], idx[j]] = [idx[j], idx[i]];
    }
    shuffled[currentMood] = idx;
  }
  const picked = shuffled[currentMood].slice(0, 4).map(i => list[i]);
  document.getElementById('chillGrid').innerHTML = picked.map(t => `
    <div class="chill-card ${t.id === currentVideoId ? 'playing' : ''}"
         onclick="playVideo('${t.id}', \`${t.title.replace(/`/g,"'")}\`)">
      <img class="chill-thumb"
           src="https://i.ytimg.com/vi/${t.id}/mqdefault.jpg"
           onerror="this.style.opacity='.3'" loading="lazy">
      <div class="chill-play-btn">${t.id === currentVideoId ? '▐▐' : '▶'}</div>
      <div class="chill-info">
        <div class="chill-title">${t.title}</div>
        <div class="chill-channel">⏱ ${t.dur} · ${t.channel}</div>
      </div>
    </div>
  `).join('');
}

// ── Search → mở YouTube ──
function initQuickTags() {
  document.getElementById('quickSearchTags').innerHTML = QUICK_TAGS.map(t =>
    `<button onclick="searchTag('${t}')" style="padding:4px 10px;border-radius:20px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text2);font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;"
     onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
     onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text2)'">${t}</button>`
  ).join('');
}

function searchTag(tag) {
  document.getElementById('musicSearchInput').value = tag;
  doSearch();
}

function doSearch() {
  const q = document.getElementById('musicSearchInput').value.trim();
  if (!q) { document.getElementById('musicSearchInput').focus(); return; }

  // Check nếu là YouTube URL → embed trực tiếp
  const ytMatch = q.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);
  if (ytMatch) {
    playVideo(ytMatch[1], q);
    return;
  }

  // Tìm trong local DB trước
  const all = Object.values(CHILL_DB).flat();
  const local = all.filter(t =>
    t.title.toLowerCase().includes(q.toLowerCase()) ||
    t.channel.toLowerCase().includes(q.toLowerCase())
  );

  // Show note + mở YouTube
  const note = document.getElementById('searchNote');
  const ytUrl = `https://www.youtube.com/results?search_query=${encodeURIComponent(q)}`;

  if (local.length > 0) {
    // Có trong local → embed luôn bài đầu tiên
    const t = local[0];
    playVideo(t.id, t.title);
    document.getElementById('searchSuggest').style.display = 'none';
    note.style.display = 'none';
  } else {
    // Không có → mở YouTube search tab mới
    window.open(ytUrl, '_blank', 'noopener');
    document.getElementById('searchNoteTitle').textContent = `🔍 Đã mở YouTube: "${q}"`;
    document.getElementById('searchNoteDesc').textContent = 'Tìm thấy kết quả trên YouTube. Copy link video rồi dán vào đây để phát ngay trong app!';
    note.style.display = '';
    document.getElementById('searchSuggest').style.display = '';
  }
}

// ── Play ──
function playVideo(videoId, title) {
  currentVideoId = videoId;
  currentTitle   = title;

  const embedWrap = document.getElementById('ytEmbedWrap');
  const embed     = document.getElementById('ytEmbed');
  embedWrap.classList.add('show');
  embed.src = `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1`;

  // Now playing bar
  const bar = document.getElementById('nowPlayingBar');
  document.getElementById('npTitle').textContent = title;
  document.getElementById('npYtLink').href = `https://www.youtube.com/watch?v=${videoId}`;
  bar.classList.add('show');

  // Refresh grid
  if (musicTab === 'chill') renderChillGrid(false);
}

function closeEmbed() {
  document.getElementById('ytEmbedWrap').classList.remove('show');
  document.getElementById('ytEmbed').src = '';
  document.getElementById('nowPlayingBar').classList.remove('show');
  currentVideoId = null;
  if (musicTab === 'chill') renderChillGrid(false);
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  renderChillGrid(true);
  initQuickTags();
});
</script>
</body>
</html>
