<?php
$current = basename($_SERVER['PHP_SELF']);
$user    = getCurrentUser();

$navItems = [
  ['href'=>'dashboard.php', 'label'=>'Dashboard', 'icon'=>'dashboard'],
  ['href'=>'chat.php',      'label'=>'Chat AI',    'icon'=>'ai'],
  ['href'=>'flashcard.php', 'label'=>'Flashcard',  'icon'=>'flashcard'],
  ['href'=>'notes.php',     'label'=>'Ghi chú',    'icon'=>'notes'],
  ['href'=>'planner.php',   'label'=>'Kế hoạch',   'icon'=>'planner'],
  ['href'=>'pomodoro.php',  'label'=>'Pomodoro',   'icon'=>'pomodoro'],
  ['href'=>'community.php', 'label'=>'Cộng đồng',  'icon'=>'community'],
  ['href'=>'rooms.php',     'label'=>'Chat',       'icon'=>'chat'],
  ['href'=>'mindmap.php',   'label'=>'Mind Map',   'icon'=>'mindmap'],
  ['href'=>'math.php',      'label'=>'Toán học',   'icon'=>'math'],
];

$bottomPrimary = [
  ['href'=>'dashboard.php', 'label'=>'Home',      'icon'=>'dashboard'],
  ['href'=>'chat.php',      'label'=>'Chat AI',   'icon'=>'ai'],
  ['href'=>'flashcard.php', 'label'=>'Cards',     'icon'=>'flashcard'],
  ['href'=>'planner.php',   'label'=>'Planner',   'icon'=>'planner'],
];

$allDrawer = [
  ['href'=>'dashboard.php', 'label'=>'Dashboard', 'icon'=>'dashboard'],
  ['href'=>'chat.php',      'label'=>'Chat AI',   'icon'=>'ai'],
  ['href'=>'flashcard.php', 'label'=>'Flashcard', 'icon'=>'flashcard'],
  ['href'=>'notes.php',     'label'=>'Ghi chú',   'icon'=>'notes'],
  ['href'=>'planner.php',   'label'=>'Kế hoạch',  'icon'=>'planner'],
  ['href'=>'pomodoro.php',  'label'=>'Pomodoro',  'icon'=>'pomodoro'],
  ['href'=>'community.php', 'label'=>'Cộng đồng', 'icon'=>'community'],
  ['href'=>'rooms.php',     'label'=>'Chat',      'icon'=>'chat'],
  ['href'=>'mindmap.php',   'label'=>'Mind Map',  'icon'=>'mindmap'],
  ['href'=>'math.php',      'label'=>'Toán học',  'icon'=>'math'],
  ['href'=>'quiz.php',      'label'=>'Quiz',      'icon'=>'quiz'],
  ['href'=>'gamification.php','label'=>'Thành tích', 'icon'=>'trophy'],
  ['href'=>'profile.php',     'label'=>'Hồ sơ',       'icon'=>'profile'],
  ['href'=>'gamification.php', 'label'=>'Thành tích',   'icon'=>'trophy'],
  ['href'=>'daily_challenge.php','label'=>'Thử thách',  'icon'=>'challenge'],
  ['href'=>'spaced_repetition.php','label'=>'SRS Ôn tập','icon'=>'srs'],
  ['href'=>'question_bank.php','label'=>'Ngân hàng đề', 'icon'=>'bank'],
  ['href'=>'study_room.php',   'label'=>'Study Room',   'icon'=>'studyroom'],
  ['href'=>'duel.php',         'label'=>'Duel Mode',    'icon'=>'duel'],
  ['href'=>'ai_tutor.php',     'label'=>'AI Gia sư',    'icon'=>'ai'],
  ['href'=>'doc_summarizer.php','label'=>'Tóm tắt AI',  'icon'=>'doc'],
  ['href'=>'writing_check.php','label'=>'Chấm bài AI',  'icon'=>'write'],
];

$accentColors = ['#3b5bdb','#1a7f4b','#b45309','#b91c1c','#6d28d9','#0369a1','#9d174d'];
$userColor    = $accentColors[abs(crc32($user['name'])) % count($accentColors)];
$userInitial  = mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'));
$firstName    = htmlspecialchars(explode(' ', $user['name'])[0]);
$drawerActive = !in_array($current, array_column($bottomPrimary, 'href'));

function ni($n) { // navIcon
  $p = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'ai'        => '<path d="M12 2a4 4 0 0 1 4 4v1h1a3 3 0 0 1 0 6h-1v1a4 4 0 0 1-8 0v-1H7a3 3 0 0 1 0-6h1V6a4 4 0 0 1 4-4Z"/><circle cx="10" cy="9" r="1" fill="currentColor" stroke="none"/><circle cx="14" cy="9" r="1" fill="currentColor" stroke="none"/>',
    'flashcard' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M8 12h8M12 9v6"/>',
    'notes'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/>',
    'planner'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01"/>',
    'pomodoro'  => '<circle cx="12" cy="12" r="9"/><polyline points="12,7 12,12 15,15"/>',
    'community' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'chat'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'mindmap'   => '<circle cx="12" cy="12" r="3"/><path d="M12 9V5m0 14v-4M9 12H5m14 0h-4M7.05 7.05 10 10m4 4 2.95 2.95M16.95 7.05 14 10M10 14l-2.95 2.95"/>',
    'math'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/><line x1="5" y1="7" x2="10" y2="7"/><line x1="14" y1="17" x2="19" y2="17"/>',
    'profile'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'quiz'      => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'challenge' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
    'srs'       => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
    'bank'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/>',
    'studyroom' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'duel'      => '<path d="m14.5 17.5 3-3-3-3"/><path d="m9.5 6.5-3 3 3 3"/><path d="M3 17.5h4a2 2 0 0 0 2-2v-7"/><path d="M21 6.5h-4a2 2 0 0 0-2 2v7"/>',
    'doc'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h4"/>',
    'write'     => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
    'trophy'    => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/>',
    'dots'      => '<circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none"/>',
  ];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.($p[$n]??'').'</svg>';
}

function logoMark() {
  return '<svg viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="3.5" fill="#fff" opacity=".95"/>
<line x1="14" y1="3" x2="14" y2="9.5" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
<line x1="14" y1="18.5" x2="14" y2="25" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
<line x1="3" y1="14" x2="9.5" y2="14" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
<line x1="18.5" y1="14" x2="25" y2="14" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
<line x1="6.5" y1="6.5" x2="10.8" y2="10.8" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".45"/>
<line x1="17.2" y1="17.2" x2="21.5" y2="21.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".45"/>
<line x1="21.5" y1="6.5" x2="17.2" y2="10.8" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".45"/>
<line x1="10.8" y1="17.2" x2="6.5" y2="21.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".45"/>
</svg>';
}
?>
<script>
(function(){
  const t = localStorage.getItem('theme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
})();
</script>

<style>
/* ═══════════════════════════════════
   SHARED
═══════════════════════════════════ */
.nv-logo {
  display: flex; align-items: center; gap: 8px;
  text-decoration: none; flex-shrink: 0;
  transition: opacity .15s;
}
.nv-logo:hover { opacity: .75; }
.nv-mark {
  width: 28px; height: 28px; border-radius: 7px;
  background: linear-gradient(140deg, #3b5bdb 0%, #6741d9 100%);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 1px 6px rgba(59,91,219,.38);
  flex-shrink: 0;
}
.nv-mark svg { width: 16px; height: 16px; }
.nv-wordmark {
  font-size: 14.5px; font-weight: 800;
  letter-spacing: -.5px; color: var(--text); line-height: 1;
}
.nv-wordmark em {
  font-style: normal;
  background: linear-gradient(100deg, #3b5bdb, #6741d9);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}

/* avatar */
.nv-av {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 11.5px; color: #fff;
  flex-shrink: 0; overflow: hidden;
  box-shadow: 0 0 0 1.5px rgba(255,255,255,.1);
}
.nv-av img { width: 100%; height: 100%; object-fit: cover; }

/* theme btn */
.nv-tbtn {
  width: 28px; height: 28px; border-radius: 7px;
  border: 1px solid var(--border); background: transparent;
  color: var(--muted); cursor: pointer; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: color .14s, border-color .14s, background .14s;
}
.nv-tbtn:hover { color: var(--text); border-color: var(--border2); background: var(--surface2); }
.nv-tbtn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

/* ═══════════════════════════════════
   DESKTOP NAV
═══════════════════════════════════ */
.navbar {
  position: sticky; top: 0; z-index: 200;
  height: 52px; padding: 0 18px;
  display: flex; align-items: center; gap: 6px;
  background: var(--nav-bg);
  backdrop-filter: blur(18px) saturate(1.5);
  -webkit-backdrop-filter: blur(18px) saturate(1.5);
  border-bottom: 1px solid var(--border);
}
.navbar .nv-logo { margin-right: 8px; }

/* scrollable link strip */
.nv-strip {
  flex: 1; display: flex; align-items: center; gap: 1px;
  overflow-x: auto; scrollbar-width: none; min-width: 0;
}
.nv-strip::-webkit-scrollbar { display: none; }

.nv-link {
  display: flex; align-items: center; gap: 5px;
  padding: 5px 9px; border-radius: 7px;
  font-size: 12.5px; font-weight: 500;
  color: var(--muted); text-decoration: none;
  white-space: nowrap; flex-shrink: 0;
  transition: color .12s, background .12s;
}
.nv-link svg { width: 13px; height: 13px; opacity: .6; transition: opacity .12s; flex-shrink: 0; }
.nv-link:hover  { color: var(--text); background: var(--surface2); }
.nv-link:hover svg { opacity: 1; }
.nv-link.on {
  color: var(--accent); background: var(--accent-soft);
  font-weight: 640;
}
.nv-link.on svg { opacity: 1; }

/* right cluster */
.nv-right { display: flex; align-items: center; gap: 5px; flex-shrink: 0; margin-left: 6px; }

/* user pill */
.nv-pill {
  display: flex; align-items: center; gap: 6px;
  padding: 2px 8px 2px 2px; border-radius: 99px;
  border: 1px solid var(--border); background: var(--surface2);
  cursor: pointer; transition: border-color .13s, background .13s;
}
.nv-pill:hover { border-color: var(--border2); background: var(--surface); }
.nv-pill-name { font-size: 12px; font-weight: 600; color: var(--text2); }
.nv-pill-chev {
  width: 9px; height: 9px;
  stroke: var(--muted); fill: none; stroke-width: 2.2; stroke-linecap: round;
  transition: transform .2s;
}
.nv-pill.on .nv-pill-chev { transform: rotate(180deg); }

/* dropdown */
.nv-ddwrap { position: relative; }
.nv-dd {
  position: absolute; top: calc(100% + 7px); right: 0;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 13px; padding: 5px;
  box-shadow: 0 10px 36px rgba(0,0,0,.13);
  min-width: 188px; z-index: 300;
  opacity: 0; transform: translateY(-5px) scale(.97);
  pointer-events: none;
  transition: opacity .14s, transform .14s;
}
.nv-dd.on { opacity: 1; transform: none; pointer-events: auto; }
.nv-dd-head {
  padding: 8px 11px 7px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 4px;
}
.nv-dd-name  { font-size: 12.5px; font-weight: 700; color: var(--text); }
.nv-dd-email { font-size: 10.5px; color: var(--muted); margin-top: 1px; }
.nv-dd-row {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 9px; border-radius: 7px;
  font-size: 12.5px; font-weight: 500; color: var(--text2);
  text-decoration: none; border: none; background: none;
  width: 100%; cursor: pointer; font-family: var(--font);
  transition: background .11s, color .11s;
}
.nv-dd-row svg { width: 13px; height: 13px; flex-shrink: 0; opacity: .55; transition: opacity .11s; }
.nv-dd-row:hover { background: var(--surface2); color: var(--text); }
.nv-dd-row:hover svg { opacity: 1; }
.nv-dd-row.red { color: #e53e3e; }
.nv-dd-row.red:hover { background: rgba(229,62,62,.07); }
.nv-dd-sep { height: 1px; background: var(--border); margin: 3px 0; }

/* ═══════════════════════════════════
   MOBILE TOPBAR
═══════════════════════════════════ */
.mobile-topbar {
  display: none;
  position: sticky; top: 0; z-index: 200;
  height: 48px; padding: 0 14px;
  align-items: center; justify-content: space-between;
  background: var(--nav-bg);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-bottom: 1px solid var(--border);
}

/* ═══════════════════════════════════
   BOTTOM NAV
═══════════════════════════════════ */
.bottom-nav {
  display: none;
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 200;
  height: calc(54px + env(safe-area-inset-bottom));
  padding-bottom: env(safe-area-inset-bottom);
  background: var(--nav-bg);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-top: 1px solid var(--border);
  justify-content: space-around; align-items: center;
}
.bn {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px;
  text-decoration: none; color: var(--muted);
  padding: 6px 4px; border: none; background: none;
  cursor: pointer; font-family: var(--font);
  transition: color .12s, transform .1s;
  position: relative;
}
.bn svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.bn-lbl { font-size: 9.5px; font-weight: 600; letter-spacing: .1px; line-height: 1; }
.bn.on { color: var(--accent); }
.bn.on svg { stroke-width: 2.2; }
.bn:active { transform: scale(.86); }
/* top pill indicator */
.bn.on::before {
  content: '';
  position: absolute; top: 0; left: 50%; transform: translateX(-50%);
  width: 20px; height: 2.5px; border-radius: 0 0 3px 3px;
  background: var(--accent);
}

/* ═══════════════════════════════════
   DRAWER
═══════════════════════════════════ */
.nv-ov {
  display: none; position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,.42);
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
}
.nv-ov.on { display: block; }

.nv-drawer {
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 501;
  background: var(--surface);
  border-radius: 20px 20px 0 0;
  padding-bottom: max(18px, env(safe-area-inset-bottom));
  max-height: 80vh; overflow-y: auto;
  transform: translateY(100%);
  transition: transform .28s cubic-bezier(.32,.72,0,1);
}
.nv-ov.on .nv-drawer { transform: none; }

.nv-drawer-pill {
  width: 34px; height: 4px; border-radius: 99px;
  background: var(--border2); margin: 10px auto 0;
}

/* user strip inside drawer */
.nv-drawer-user {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px 14px;
  border-bottom: 1px solid var(--border);
}
.nv-drawer-uname  { font-size: 13px; font-weight: 700; color: var(--text); line-height: 1.3; }
.nv-drawer-uemail { font-size: 10.5px; color: var(--muted); }

/* 4-col app grid */
.nv-drawer-grid {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 2px; padding: 10px 8px 6px;
}
.nv-drawer-app {
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding: 12px 6px 10px; border-radius: 11px;
  text-decoration: none; color: var(--text2);
  font-size: 10.5px; font-weight: 600; text-align: center;
  line-height: 1.3; transition: background .12s, color .12s;
}
.nv-drawer-app svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.nv-drawer-app:hover, .nv-drawer-app.on {
  background: var(--accent-soft); color: var(--accent);
}
.nv-drawer-app.on svg { stroke-width: 2.2; }

/* bottom row */
.nv-drawer-foot {
  display: flex; gap: 6px; padding: 6px 10px 2px;
  border-top: 1px solid var(--border); margin-top: 4px;
}
.nv-drawer-btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 9px 10px; border-radius: 10px;
  border: 1px solid var(--border); background: var(--surface2);
  color: var(--text2); font-size: 12.5px; font-weight: 600;
  cursor: pointer; text-decoration: none; font-family: var(--font);
  transition: all .12s;
}
.nv-drawer-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
.nv-drawer-btn.red { color: #e53e3e; }
.nv-drawer-btn.red:hover { border-color: #e53e3e; background: rgba(229,62,62,.07); }
.nv-drawer-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }

/* ═══════════════════════════════════
   RESPONSIVE
═══════════════════════════════════ */
@media (max-width: 1020px) {
  .nv-link span { display: none; }
  .nv-link { padding: 7px 8px; }
  .nv-pill-name { display: none; }
}
@media (max-width: 640px) {
  .navbar        { display: none !important; }
  .mobile-topbar { display: flex !important; }
  .bottom-nav    { display: flex !important; }
}
em, i { font-style: normal !important; }
* { font-style: normal; }
</style>

<!-- ══════════════════════════════════
     DESKTOP NAVBAR
══════════════════════════════════ -->
<header>
<nav class="navbar" role="navigation" aria-label="Điều hướng chính">
  <a href="dashboard.php" class="nv-logo" aria-label="MindSpark — Trang chủ">
    <div class="nv-mark"><?= logoMark() ?></div>
    <span class="nv-wordmark">Mind<em>Spark</em></span>
  </a>

  <div class="nv-strip" role="list">
    <?php foreach ($navItems as $it):
      $on = $current === $it['href'];
    ?>
    <a href="<?= $it['href'] ?>"
       class="nv-link<?= $on ? ' on' : '' ?>"
       role="listitem"
       <?= $on ? 'aria-current="page"' : '' ?>>
      <?= ni($it['icon']) ?><span><?= $it['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="nv-right">
    <button class="nv-tbtn" id="nvTD" onclick="nvTheme()" aria-label="Chuyển giao diện">
      <svg id="nvTDi" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    <div class="nv-ddwrap" id="nvDDW">
      <button class="nv-pill" id="nvPill" onclick="nvDropToggle()" aria-haspopup="menu" aria-expanded="false">
        <?php if (!empty($user['avatar'])): ?>
          <div class="nv-av"><img src="<?= htmlspecialchars($user['avatar']) ?>" alt=""></div>
        <?php else: ?>
          <div class="nv-av" style="background:<?= $userColor ?>"><?= $userInitial ?></div>
        <?php endif; ?>
        <span class="nv-pill-name"><?= $firstName ?></span>
        <svg class="nv-pill-chev" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
      </button>

      <div class="nv-dd" id="nvDD" role="menu">
        <div class="nv-dd-head">
          <div class="nv-dd-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="nv-dd-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <a href="profile.php" class="nv-dd-row" role="menuitem"><?= ni('profile') ?> Hồ sơ cá nhân</a>
        <a href="quiz.php"    class="nv-dd-row" role="menuitem"><?= ni('quiz') ?> Quiz</a>
        <div class="nv-dd-sep"></div>
        <a href="logout.php"  class="nv-dd-row red" role="menuitem"><?= ni('logout') ?> Đăng xuất</a>
      </div>
    </div>
  </div>
</nav>
</header>

<!-- ══════════════════════════════════
     MOBILE — TOP BAR
══════════════════════════════════ -->
<header class="mobile-topbar" role="banner">
  <a href="dashboard.php" class="nv-logo">
    <div class="nv-mark"><?= logoMark() ?></div>
    <span class="nv-wordmark">Mind<em>Spark</em></span>
  </a>
  <div style="display:flex;align-items:center;gap:8px">
    <button class="nv-tbtn" id="nvTM" onclick="nvTheme()" aria-label="Chuyển giao diện">
      <svg id="nvTMi" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <?php if (!empty($user['avatar'])): ?>
      <a href="profile.php" class="nv-av"><img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Hồ sơ"></a>
    <?php else: ?>
      <a href="profile.php" class="nv-av" style="background:<?= $userColor ?>;text-decoration:none;width:28px;height:28px"><?= $userInitial ?></a>
    <?php endif; ?>
  </div>
</header>

<!-- ══════════════════════════════════
     MOBILE — BOTTOM NAV
══════════════════════════════════ -->
<nav class="bottom-nav" role="navigation" aria-label="Điều hướng chính">
  <?php foreach ($bottomPrimary as $it):
    $on = $current === $it['href'];
  ?>
  <a href="<?= $it['href'] ?>" class="bn<?= $on ? ' on' : '' ?>" <?= $on ? 'aria-current="page"' : '' ?>>
    <?= ni($it['icon']) ?>
    <span class="bn-lbl"><?= $it['label'] ?></span>
  </a>
  <?php endforeach; ?>
  <button class="bn<?= $drawerActive ? ' on' : '' ?>" onclick="nvDrawerOpen()" aria-label="Tất cả tính năng" aria-haspopup="dialog">
    <?= ni('dots') ?>
    <span class="bn-lbl">Thêm</span>
  </button>
</nav>

<!-- ══════════════════════════════════
     MOBILE — DRAWER
══════════════════════════════════ -->
<div class="nv-ov" id="nvOv" role="dialog" aria-modal="true" aria-label="Menu" onclick="nvOvClick(event)">
  <div class="nv-drawer" id="nvDrawer">
    <div class="nv-drawer-pill"></div>

    <div class="nv-drawer-user">
      <?php if (!empty($user['avatar'])): ?>
        <div class="nv-av" style="width:36px;height:36px"><img src="<?= htmlspecialchars($user['avatar']) ?>" alt=""></div>
      <?php else: ?>
        <div class="nv-av" style="width:36px;height:36px;font-size:14px;background:<?= $userColor ?>"><?= $userInitial ?></div>
      <?php endif; ?>
      <div>
        <div class="nv-drawer-uname"><?= htmlspecialchars($user['name']) ?></div>
        <div class="nv-drawer-uemail"><?= htmlspecialchars($user['email']) ?></div>
      </div>
    </div>

    <div class="nv-drawer-grid">
      <?php foreach ($allDrawer as $it):
        $on = $current === $it['href'];
      ?>
      <a href="<?= $it['href'] ?>" class="nv-drawer-app<?= $on ? ' on' : '' ?>">
        <?= ni($it['icon']) ?><?= $it['label'] ?>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="nv-drawer-foot">
      <button class="nv-drawer-btn" id="nvTDr" onclick="nvTheme()">
        <svg id="nvTDri" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        Giao diện
      </button>
      <a href="profile.php" class="nv-drawer-btn">
        <?= ni('profile') ?> Hồ sơ
      </a>
      <a href="logout.php" class="nv-drawer-btn red">
        <?= ni('logout') ?> Đăng xuất
      </a>
    </div>
  </div>
</div>

<script>
const _S = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
const _M = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';

function nvSetIco(dark) {
  ['nvTDi','nvTMi','nvTDri'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.innerHTML = dark ? _S : _M;
  });
}
function nvTheme() {
  const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  nvSetIco(next === 'dark');
}
function nvDropToggle() {
  const pill = document.getElementById('nvPill');
  const dd   = document.getElementById('nvDD');
  const open = dd.classList.toggle('on');
  pill.classList.toggle('on', open);
  pill.setAttribute('aria-expanded', open);
  if (open) {
    const h = e => {
      if (!document.getElementById('nvDDW')?.contains(e.target)) {
        dd.classList.remove('on'); pill.classList.remove('on');
        pill.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', h);
      }
    };
    setTimeout(() => document.addEventListener('click', h), 0);
  }
}
function nvDrawerOpen() {
  document.getElementById('nvOv').classList.add('on');
  document.body.style.overflow = 'hidden';
}
function nvDrawerClose() {
  document.getElementById('nvOv').classList.remove('on');
  document.body.style.overflow = '';
}
function nvOvClick(e) {
  if (e.target === document.getElementById('nvOv')) nvDrawerClose();
}
document.addEventListener('DOMContentLoaded', () => nvSetIco(localStorage.getItem('theme') === 'dark'));
document.addEventListener('keydown', e => { if (e.key === 'Escape') nvDrawerClose(); });

// backward compat
function toggleTheme()   { nvTheme(); }
function toggleNavDrop() { nvDropToggle(); }
function toggleDropdown(){ nvDrawerOpen(); }
</script>
