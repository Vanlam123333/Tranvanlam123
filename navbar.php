<?php
$current = basename($_SERVER['PHP_SELF']);
$user    = getCurrentUser();
?>
<script>
  (function(){
    const t=localStorage.getItem('theme')||'light';
    document.documentElement.setAttribute('data-theme',t);
  })();
</script>
<style>
.nav-avatar-img{width:32px;height:32px;border-radius:50%;object-fit:cover;border:1.5px solid var(--border);}
.nav-dropdown{position:relative;}
.nav-dropdown-menu{position:absolute;right:0;top:calc(100%+8px);background:var(--surface);
  border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow-lg);
  min-width:210px;overflow:hidden;z-index:200;display:none;padding:5px;}
.nav-dropdown-menu.open{display:block;}
.nav-dropdown-header{padding:10px 13px 8px;border-bottom:1px solid var(--border);margin-bottom:4px;}
.nav-dropdown-name{font-size:13px;font-weight:600;color:var(--text);letter-spacing:-0.2px;}
.nav-dropdown-email{font-size:11px;color:var(--muted);margin-top:1px;}
.nav-dd-item{display:flex;align-items:center;gap:9px;padding:7px 11px;border-radius:7px;
  text-decoration:none;color:var(--text2);font-size:13px;font-weight:500;transition:background .12s;
  cursor:pointer;border:none;background:none;width:100%;font-family:var(--font);letter-spacing:-0.01em;}
.nav-dd-item:hover{background:var(--surface2);color:var(--text);}
.nav-dd-item.danger{color:var(--red);}
.nav-dd-item svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8;
  stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;opacity:.7;}
.nav-dd-sep{height:1px;background:var(--border);margin:4px 0;}
</style>

<!-- SVG icon helper (inline, reusable) -->
<?php
// Returns inline SVG for nav icons — clean, no emoji
function navIcon($name) {
  $icons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'ai'        => '<path d="M12 2a4 4 0 0 1 4 4v1h1a3 3 0 0 1 0 6h-1v1a4 4 0 0 1-8 0v-1H7a3 3 0 0 1 0-6h1V6a4 4 0 0 1 4-4Z"/><circle cx="10" cy="9" r="1"/><circle cx="14" cy="9" r="1"/><path d="M10 14s.5 1 2 1 2-1 2-1"/>',
    'flashcard' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M8 12h8M12 9v6"/>',
    'notes'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/>',
    'planner'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'pomodoro'  => '<circle cx="12" cy="12" r="9"/><polyline points="12,7 12,12 15,15"/>',
    'community' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'chat'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'profile'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'mindmap'   => '<circle cx="12" cy="12" r="3"/><line x1="12" y1="9" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="15"/><line x1="9" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="15" y2="12"/>',
    'math'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/><line x1="5" y1="7" x2="10" y2="7"/><line x1="14" y1="17" x2="19" y2="17"/>',
    'quiz'      => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'sun'       => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
    'moon'      => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    'menu'      => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
    'logo'      => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
  ];
  $path = $icons[$name] ?? '';
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;flex-shrink:0;">'.$path.'</svg>';
}
?>

<!-- DESKTOP NAVBAR -->
<nav class="navbar">
  <a href="dashboard.php" class="logo">
    <div class="logo-mark"><?=navIcon('logo')?></div>
    <div class="logo-name">Mind<span>Spark</span></div>
  </a>

  <div class="nav-links">
    <a href="dashboard.php" class="nav-link <?=$current=='dashboard.php'?'active':''?>">Dashboard</a>
    <a href="chat.php"      class="nav-link <?=$current=='chat.php'?'active':''?>">Chat AI</a>
    <a href="flashcard.php" class="nav-link <?=$current=='flashcard.php'?'active':''?>">Flashcard</a>
    <a href="notes.php"     class="nav-link <?=$current=='notes.php'?'active':''?>">Ghi chú</a>
    <a href="planner.php"   class="nav-link <?=$current=='planner.php'?'active':''?>">Kế hoạch</a>
    <a href="pomodoro.php"  class="nav-link <?=$current=='pomodoro.php'?'active':''?>">Pomodoro</a>
    <a href="community.php" class="nav-link <?=$current=='community.php'?'active':''?>">Cộng đồng</a>
    <a href="rooms.php"     class="nav-link <?=$current=='rooms.php'?'active':''?>">Chat</a>
    <a href="mindmap.php"   class="nav-link <?=$current=='mindmap.php'?'active':''?>" style="font-weight:700;color:var(--accent)">Mind Map</a>
    <a href="math.php"      class="nav-link <?=$current=='math.php'?'active':''?>" style="font-weight:700;color:var(--accent)">Toán học</a>
  </div>

  <div class="nav-user">
    <!-- Theme toggle -->
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn" title="Đổi giao diện">
      <svg id="themeSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>

    <!-- User dropdown -->
    <div class="nav-dropdown" id="navDropdown">
      <div style="cursor:pointer;display:flex;align-items:center;gap:8px;" onclick="toggleNavDrop()">
        <?php if(!empty($user['avatar'])): ?>
          <img src="<?=htmlspecialchars($user['avatar'])?>" class="nav-avatar-img">
        <?php else:
          $colors=['#3b5bdb','#0f7240','#a16207','#c9242a','#6d28d9','#0e7490'];
          $c=$colors[abs(crc32($user['name']))%count($colors)];
        ?>
          <div class="avatar" style="background:<?=$c?>;font-size:13px;font-weight:600;">
            <?=mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'))?>
          </div>
        <?php endif;?>
        <span class="nav-name"><?=htmlspecialchars(explode(' ',$user['name'])[0])?></span>
        <svg style="width:10px;height:10px;stroke:var(--muted);fill:none;stroke-width:2;" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
      </div>

      <div class="nav-dropdown-menu" id="navDropMenu">
        <div class="nav-dropdown-header">
          <div class="nav-dropdown-name"><?=htmlspecialchars($user['name'])?></div>
          <div class="nav-dropdown-email"><?=htmlspecialchars($user['email'])?></div>
        </div>
        <a href="profile.php"   class="nav-dd-item"><?=navIcon('profile')?> Hồ sơ cá nhân</a>
        <a href="community.php" class="nav-dd-item"><?=navIcon('community')?> Cộng đồng</a>
        <a href="rooms.php"     class="nav-dd-item"><?=navIcon('chat')?> Phòng chat</a>
        <div class="nav-dd-sep"></div>
        <a href="mindmap.php"   class="nav-dd-item"><?=navIcon('mindmap')?> Mind Map</a>
        <a href="math.php"      class="nav-dd-item"><?=navIcon('math')?> Toán học</a>
        <a href="quiz.php"      class="nav-dd-item"><?=navIcon('quiz')?> Quiz</a>
        <div class="nav-dd-sep"></div>
        <a href="logout.php"    class="nav-dd-item danger"><?=navIcon('logout')?> Đăng xuất</a>
      </div>
    </div>
  </div>
</nav>

<!-- MOBILE TOP BAR -->
<div class="mobile-topbar">
  <a href="dashboard.php" class="logo">
    <div class="logo-mark"><?=navIcon('logo')?></div>
    <div class="logo-name">Mind<span>Spark</span></div>
  </a>
  <div style="display:flex;align-items:center;gap:8px;">
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtnMobile">
      <svg id="themeSvgMobile" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>
    <a href="profile.php">
      <?php if(!empty($user['avatar'])): ?>
        <img src="<?=htmlspecialchars($user['avatar'])?>" class="nav-avatar-img">
      <?php else: ?>
        <div class="avatar" style="width:32px;height:32px;font-size:13px;"><?=mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'))?></div>
      <?php endif;?>
    </a>
  </div>
</div>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="dashboard.php" class="bottom-nav-item <?=$current=='dashboard.php'?'active':''?>">
    <div class="bottom-nav-icon"><?=navIcon('dashboard')?></div>
    <span class="bottom-nav-label">Home</span>
  </a>
  <a href="chat.php" class="bottom-nav-item <?=$current=='chat.php'?'active':''?>">
    <div class="bottom-nav-icon"><?=navIcon('ai')?></div>
    <span class="bottom-nav-label">AI</span>
  </a>
  <a href="mindmap.php" class="bottom-nav-item <?=$current=='mindmap.php'?'active':''?>">
    <div class="bottom-nav-icon"><?=navIcon('mindmap')?></div>
    <span class="bottom-nav-label">Mind Map</span>
  </a>
  <a href="math.php" class="bottom-nav-item <?=$current=='math.php'?'active':''?>">
    <div class="bottom-nav-icon"><?=navIcon('math')?></div>
    <span class="bottom-nav-label">Toán học</span>
  </a>
  <button class="bottom-nav-item" onclick="toggleDropdown(event)">
    <div class="bottom-nav-icon"><?=navIcon('menu')?></div>
    <span class="bottom-nav-label">Thêm</span>
  </button>
</nav>

<!-- MORE DROPDOWN (mobile) -->
<div class="more-dropdown" id="moreDropdown">
  <a href="profile.php"   ><?=navIcon('profile')?>  Hồ sơ</a>
  <a href="flashcard.php" ><?=navIcon('flashcard')?> Flashcard</a>
  <a href="notes.php"     ><?=navIcon('notes')?>     Ghi chú</a>
  <a href="planner.php"   ><?=navIcon('planner')?>   Kế hoạch</a>
  <a href="pomodoro.php"  ><?=navIcon('pomodoro')?>  Pomodoro</a>
  <a href="community.php" ><?=navIcon('community')?> Cộng đồng</a>
  <a href="rooms.php"     ><?=navIcon('chat')?>      Chat phòng</a>
  <a href="mindmap.php"   ><?=navIcon('mindmap')?>   Mind Map</a>
  <a href="math.php"      ><?=navIcon('math')?>      Toán học</a>
  <a href="quiz.php"      ><?=navIcon('quiz')?>      Quiz</a>
  <a href="logout.php"    ><?=navIcon('logout')?>    Đăng xuất</a>
</div>

<script>
function toggleNavDrop() {
  const m=document.getElementById('navDropMenu');
  m.classList.toggle('open');
  document.addEventListener('click',function h(e){
    if(!document.getElementById('navDropdown')?.contains(e.target)){
      m.classList.remove('open'); document.removeEventListener('click',h);
    }
  });
}
function toggleDropdown(e) {
  e.stopPropagation();
  document.getElementById('moreDropdown').classList.toggle('open');
}
document.addEventListener('click',function(e){
  const dd=document.getElementById('moreDropdown');
  if(dd&&!dd.contains(e.target)) dd.classList.remove('open');
});

const sunPath  = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
const moonPath = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';

function setThemeIcon(theme) {
  const isDark = theme === 'dark';
  ['themeSvg','themeSvgMobile'].forEach(id => {
    const el = document.getElementById(id);
    if(el) el.innerHTML = isDark ? sunPath : moonPath;
  });
}
function toggleTheme() {
  const html = document.documentElement;
  const next = html.getAttribute('data-theme')==='light' ? 'dark' : 'light';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  setThemeIcon(next);
}
document.addEventListener('DOMContentLoaded', function(){
  setThemeIcon(localStorage.getItem('theme')||'light');
});
</script>
