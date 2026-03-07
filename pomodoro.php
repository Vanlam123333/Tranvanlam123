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
.pomo-svg { transform: rotate(-90deg); }
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
.mood-btn {
  padding: 5px 10px; border-radius: 20px; border: 1.5px solid var(--border);
  background: var(--surface2); color: var(--text2); font-size: 11px; font-weight: 700;
  cursor: pointer; transition: all 0.15s; white-space: nowrap;
}
.mood-btn:hover { border-color: var(--accent); color: var(--accent); }
.mood-btn.active { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); }

.music-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 10px; border: 1.5px solid var(--border);
  background: var(--surface2); margin-bottom: 6px; cursor: pointer;
  transition: all 0.18s;
}
.music-item:hover { border-color: var(--accent); background: var(--accent-soft); }
.music-item.playing { border-color: var(--accent); background: var(--accent-soft); }
.music-thumb {
  width: 52px; height: 38px; border-radius: 6px; object-fit: cover;
  flex-shrink: 0; background: var(--border);
}
.music-info { flex: 1; min-width: 0; }
.music-title { font-size: 12px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.music-meta  { font-size: 10px; color: var(--muted); margin-top: 2px; }
.music-play  { font-size: 20px; flex-shrink: 0; transition: transform 0.15s; }
.music-item:hover .music-play { transform: scale(1.2); }

.equalizer {
  display: flex; gap: 2px; align-items: flex-end; height: 14px; flex-shrink: 0;
}
.eq-bar { width: 3px; background: var(--accent); border-radius: 2px; animation: eq 0.6s ease-in-out infinite alternate; }
.eq-bar:nth-child(1) { height: 6px;  animation-delay: 0s; }
.eq-bar:nth-child(2) { height: 10px; animation-delay: 0.15s; }
.eq-bar:nth-child(3) { height: 14px; animation-delay: 0.3s; }
.eq-bar:nth-child(4) { height: 8px;  animation-delay: 0.45s; }
@keyframes eq { from { transform: scaleY(0.4); } to { transform: scaleY(1); } }
@keyframes spin { to { transform: rotate(360deg); } }
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
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="number" id="customMin" min="1" max="120" value="25" class="form-input" style="width:72px;text-align:center;" oninput="setCustom()">
            <span style="color:var(--muted);font-size:13px;">phút</span>
            <button class="btn btn-ghost btn-sm" onclick="setCustom()">Áp dụng</button>
          </div>
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

      <!-- 🎵 LOFI MUSIC AI -->
      <div class="card" id="musicCard">
        <div class="card-header" style="gap:8px;">
          <div class="card-title">🎵 Nhạc Lo-fi học bài</div>
          <button class="btn btn-ghost btn-sm" id="musicRefreshBtn" onclick="loadLofiMusic(true)" style="margin-left:auto;">
            🔀 Gợi ý khác
          </button>
        </div>
        <div class="card-body" style="padding-top:0.5rem;">

          <!-- Mood selector -->
          <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;" id="moodBtns">
            <button class="mood-btn active" data-mood="study" onclick="setMood('study',this)">📚 Học bài</button>
            <button class="mood-btn" data-mood="relax"  onclick="setMood('relax',this)">😌 Thư giãn</button>
            <button class="mood-btn" data-mood="focus"  onclick="setMood('focus',this)">🎯 Deep focus</button>
            <button class="mood-btn" data-mood="sleep"  onclick="setMood('sleep',this)">🌙 Buồn ngủ</button>
            <button class="mood-btn" data-mood="chill"  onclick="setMood('chill',this)">☕ Chill</button>
          </div>

          <!-- Loading -->
          <div id="musicLoading" style="display:none;text-align:center;padding:1rem;color:var(--muted);font-size:13px;">
            <div style="display:inline-block;width:16px;height:16px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.7s linear infinite;margin-right:6px;vertical-align:middle;"></div>
            AI đang chọn nhạc phù hợp...
          </div>

          <!-- Music list -->
          <div id="musicList"></div>

          <!-- Now playing embed -->
          <div id="ytEmbedWrap" style="display:none;margin-top:10px;">
            <iframe id="ytEmbed" width="100%" height="120" frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen style="border-radius:10px;"></iframe>
            <div id="nowPlayingLabel" style="font-size:11px;color:var(--accent);font-weight:700;margin-top:6px;text-align:center;"></div>
          </div>

        </div>
      </div>

    </div>
  </div>
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

function setCustom() {
  const v = parseInt(document.getElementById('customMin').value) || 25;
  const clamped = Math.max(1, Math.min(120, v));
  totalSecs = clamped * 60;
  remaining = totalSecs;
  if (running) { clearInterval(intervalId); running = false; playBtn.textContent = '▶'; playBtn.classList.remove('running'); }
  render();
}

function pomToggle() {
  if (running) {
    clearInterval(intervalId); running = false;
    playBtn.textContent = '▶'; playBtn.classList.remove('running');
    if (ambientOn) stopAmbient();
  } else {
    running = true;
    playBtn.textContent = '⏸'; playBtn.classList.add('running');
    if (ambientOn) startAmbient();
    intervalId = setInterval(tick, 1000);
  }
}

function pomReset() {
  clearInterval(intervalId); running = false;
  playBtn.textContent = '▶'; playBtn.classList.remove('running');
  remaining = totalSecs;
  stopAmbient();
  render();
}

function pomSkip() {
  clearInterval(intervalId); running = false;
  remaining = 0;
  stopAmbient();
  onComplete(false);
}

function tick() {
  remaining--;
  render();
  if (remaining <= 0) {
    clearInterval(intervalId); running = false;
    playBtn.textContent = '▶'; playBtn.classList.remove('running');
    stopAmbient();
    onComplete(true);
  }
}

function render() {
  const m = String(Math.floor(remaining / 60)).padStart(2, '0');
  const s = String(remaining % 60).padStart(2, '0');
  timeEl.textContent = `${m}:${s}`;
  document.title = running ? `${m}:${s} — MindSpark 🍅` : 'Pomodoro — MindSpark';

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
  pomToggle();
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

// ══════════════════════════════════════
//  LOFI MUSIC AI
// ══════════════════════════════════════

// Playlist tĩnh phân loại theo mood — YouTube video IDs đã kiểm tra
const MUSIC_DB = {
  study: [
    { id: 'jfKfPfyJRdk', title: 'lofi hip hop radio - beats to relax/study to', channel: 'Lofi Girl', duration: 'LIVE 24/7' },
    { id: '5qap5aO4i9A', title: 'lofi hip hop radio - beats to sleep/chill to', channel: 'Lofi Girl', duration: 'LIVE 24/7' },
    { id: 'DWcJFNfaw9c', title: 'Chillhop Radio - jazzy & lofi hip hop beats', channel: 'Chillhop Music', duration: 'LIVE 24/7' },
    { id: '7NOSDKb0HlU', title: 'Deep Focus Music - Study, Work, Concentration', channel: 'Yellow Brick Cinema', duration: '3h' },
    { id: 'n61ULEU7CO0', title: 'Relaxing Music for Studying - Focus, Reading', channel: 'Soothing Relaxation', duration: '3h' },
    { id: 'lTRiuFIWV54', title: 'Japanese Lofi Hip Hop Mix - Study & Work', channel: 'Lofi Tokyo', duration: '1h' },
    { id: '4xDzrJKXOOY', title: 'Synthwave Radio - Retrowave & Outrun Music', channel: 'Synthwave+', duration: 'LIVE' },
    { id: 'MVPTGNGiI-4', title: 'Coffee Shop Ambience - Calm Music & Cafe Sounds', channel: 'Relaxing White Noise', duration: '2h' },
  ],
  relax: [
    { id: '5qap5aO4i9A', title: 'lofi hip hop radio - beats to sleep/chill to', channel: 'Lofi Girl', duration: 'LIVE 24/7' },
    { id: 'MVPTGNGiI-4', title: 'Coffee Shop Ambience with Calm Music', channel: 'Relaxing White Noise', duration: '2h' },
    { id: 'hlWiI4xVXKY', title: 'Gentle Piano - Relaxing Background Music', channel: 'Soothing Relaxation', duration: '3h' },
    { id: 'qYnA9wWFHLk', title: 'Acoustic Covers & Chill Vibes Mix', channel: 'Chillout Lounge', duration: '2h' },
    { id: 'Z5iZ4fBSi8M', title: 'Rain Sounds + Lofi - Perfect for Relaxing', channel: 'Rain Lofi', duration: '4h' },
    { id: 'YHPN81GaLaE', title: 'Peaceful Piano Music - Ambient Relaxation', channel: 'Soothing Relaxation', duration: '3h' },
  ],
  focus: [
    { id: '7NOSDKb0HlU', title: 'Deep Focus Music - 4 Hours Study Session', channel: 'Yellow Brick Cinema', duration: '4h' },
    { id: 'WPni755-Krg', title: 'Brain Power - Focus Study Music', channel: 'Greenred Productions', duration: '3h' },
    { id: 'hHW1oY26kxQ', title: 'Minimal Techno & Deep Focus Work Music', channel: 'Flow State', duration: '2h' },
    { id: 'sjkrrmBnpGE', title: 'Dark Ambient Study Music - Deep Concentration', channel: 'Studying & Working', duration: '3h' },
    { id: 'DWcJFNfaw9c', title: 'Chillhop Radio - Focus & Flow State', channel: 'Chillhop Music', duration: 'LIVE' },
    { id: 'UfcAVejslrU', title: 'Epic Focus Music Mix - Cinematic', channel: 'Epic Music VN', duration: '2h' },
  ],
  sleep: [
    { id: '5qap5aO4i9A', title: 'Lofi Hip Hop Radio - Sleep & Chill', channel: 'Lofi Girl', duration: 'LIVE' },
    { id: 'Z5iZ4fBSi8M', title: 'Rain Sounds for Sleeping - Gentle Rain', channel: 'Rain Sounds', duration: '8h' },
    { id: 'YHPN81GaLaE', title: 'Peaceful Piano - Sleep & Meditation', channel: 'Soothing Relaxation', duration: '3h' },
    { id: 'hlWiI4xVXKY', title: 'Gentle Piano & Soft Music for Sleep', channel: 'Soothing Relaxation', duration: '3h' },
    { id: 'HuFYqnbVbzY', title: 'Delta Waves - Deep Sleep Music', channel: 'Jason Stephenson', duration: '8h' },
  ],
  chill: [
    { id: 'jfKfPfyJRdk', title: 'Lofi Hip Hop Radio - Chill Beats', channel: 'Lofi Girl', duration: 'LIVE' },
    { id: '4xDzrJKXOOY', title: 'Synthwave Radio - Chill & Drive', channel: 'Synthwave+', duration: 'LIVE' },
    { id: 'qYnA9wWFHLk', title: 'Acoustic Covers - Easy Listening', channel: 'Chillout Lounge', duration: '2h' },
    { id: 'MVPTGNGiI-4', title: 'Coffee Shop Vibes - Chill Cafe Music', channel: 'Relaxing Cafe', duration: '2h' },
    { id: 'lTRiuFIWV54', title: 'Japanese City Pop & Lofi Mix', channel: 'Tokyo Lofi', duration: '1h' },
    { id: 'DWcJFNfaw9c', title: 'Chillhop Essentials - Jazzy Beats', channel: 'Chillhop Music', duration: 'LIVE' },
  ]
};

let currentMood = 'study';
let currentVideoId = null;
let shownIndices = {};

function setMood(mood, btn) {
  currentMood = mood;
  document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  shownIndices[mood] = shownIndices[mood] || [];
  loadLofiMusic(true);
}

function loadLofiMusic(shuffle = false) {
  const list = MUSIC_DB[currentMood] || MUSIC_DB.study;

  // Shuffle order nếu cần
  let indices = [...Array(list.length).keys()];
  if (shuffle || !shownIndices[currentMood]) {
    // Fisher-Yates shuffle
    for (let i = indices.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [indices[i], indices[j]] = [indices[j], indices[i]];
    }
    shownIndices[currentMood] = indices;
  } else {
    indices = shownIndices[currentMood];
  }

  // Lấy 4 bài đầu từ indices
  const picked = indices.slice(0, 4).map(i => list[i]);

  const container = document.getElementById('musicList');
  container.innerHTML = picked.map((track, i) => `
    <div class="music-item ${track.id === currentVideoId ? 'playing' : ''}"
         id="mitem_${track.id}"
         onclick="playTrack('${track.id}', '${track.title.replace(/'/g,"\\'")}')">
      <img class="music-thumb"
           src="https://i.ytimg.com/vi/${track.id}/mqdefault.jpg"
           onerror="this.style.background='var(--border)';this.src=''"
           loading="lazy">
      <div class="music-info">
        <div class="music-title">${track.title}</div>
        <div class="music-meta">📺 ${track.channel} · ⏱ ${track.duration}</div>
      </div>
      ${track.id === currentVideoId
        ? `<div class="equalizer"><div class="eq-bar"></div><div class="eq-bar"></div><div class="eq-bar"></div><div class="eq-bar"></div></div>`
        : `<span class="music-play">▶️</span>`
      }
    </div>
  `).join('');
}

function playTrack(videoId, title) {
  currentVideoId = videoId;
  const embedWrap = document.getElementById('ytEmbedWrap');
  const embed = document.getElementById('ytEmbed');
  const label = document.getElementById('nowPlayingLabel');

  embed.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
  embedWrap.style.display = '';
  label.textContent = '♫ Đang phát: ' + title.slice(0, 50);

  // Cập nhật lại list để show equalizer
  loadLofiMusic(false);
}

// Load nhạc khi trang mở
document.addEventListener('DOMContentLoaded', () => loadLofiMusic(true));
</script>
</body>
</html>
