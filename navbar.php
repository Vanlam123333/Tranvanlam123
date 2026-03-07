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
.nav-avatar-img{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--border);}
.nav-dropdown{position:relative;}
.nav-dropdown-menu{position:absolute;right:0;top:calc(100%+8px);background:var(--surface);
  border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow-lg);
  min-width:200px;overflow:hidden;z-index:200;display:none;padding:6px;}
.nav-dropdown-menu.open{display:block;}
.nav-dropdown-header{padding:10px 14px 8px;border-bottom:1px solid var(--border);margin-bottom:4px;}
.nav-dropdown-name{font-size:14px;font-weight:800;color:var(--text);}
.nav-dropdown-email{font-size:11px;color:var(--muted);margin-top:1px;}
.nav-dd-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;
  text-decoration:none;color:var(--text2);font-size:13px;font-weight:600;transition:background .12s;cursor:pointer;border:none;background:none;width:100%;font-family:var(--font);}
.nav-dd-item:hover{background:var(--surface2);color:var(--text);}
.nav-dd-item.danger{color:var(--red);}
.nav-dd-sep{height:1px;background:var(--border);margin:4px 0;}
</style>

<!-- DESKTOP NAVBAR -->
<nav class="navbar">
  <a href="dashboard.php" class="logo">
    <div class="logo-icon">⚡</div>Mind<span>Spark</span>
  </a>
  <div class="nav-links">
    <a href="dashboard.php" class="nav-link <?=$current=='dashboard.php'?'active':''?>">📊 <span class="label">Dashboard</span></a>
    <a href="chat.php"      class="nav-link <?=$current=='chat.php'?'active':''?>">🧠 <span class="label">Chat AI</span></a>
    <a href="flashcard.php" class="nav-link <?=$current=='flashcard.php'?'active':''?>">⚡ <span class="label">Flashcard</span></a>
    <a href="notes.php"     class="nav-link <?=$current=='notes.php'?'active':''?>">🗒️ <span class="label">Ghi chú</span></a>
    <a href="planner.php"   class="nav-link <?=$current=='planner.php'?'active':''?>">📅 <span class="label">Kế hoạch</span></a>
    <a href="pomodoro.php"  class="nav-link <?=$current=='pomodoro.php'?'active':''?>">🍅 <span class="label">Pomodoro</span></a>
    <a href="community.php" class="nav-link <?=$current=='community.php'?'active':''?>">🌏 <span class="label">Cộng đồng</span></a>
    <a href="rooms.php"     class="nav-link <?=$current=='rooms.php'?'active':''?>">💬 <span class="label">Chat</span></a>
  </div>
  <div class="nav-user">
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn">🌙</button>
    <!-- User dropdown -->
    <div class="nav-dropdown" id="navDropdown">
      <div style="cursor:pointer;display:flex;align-items:center;gap:8px;" onclick="toggleNavDrop()">
        <?php if(!empty($user['avatar'])): ?>
          <img src="<?=htmlspecialchars($user['avatar'])?>" class="nav-avatar-img">
        <?php else:
          $colors=['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
          $c=$colors[abs(crc32($user['name']))%count($colors)];
        ?>
          <div class="avatar" style="background:<?=$c?>;width:34px;height:34px;font-size:14px;"><?=mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'))?></div>
        <?php endif;?>
        <span class="nav-name"><?=htmlspecialchars(explode(' ',$user['name'])[0])?></span>
        <span style="font-size:10px;color:var(--muted);">▾</span>
      </div>
      <div class="nav-dropdown-menu" id="navDropMenu">
        <div class="nav-dropdown-header">
          <div class="nav-dropdown-name"><?=htmlspecialchars($user['name'])?></div>
          <div class="nav-dropdown-email"><?=htmlspecialchars($user['email'])?></div>
        </div>
        <a href="profile.php"   class="nav-dd-item">👤 Hồ sơ cá nhân</a>
        <a href="community.php" class="nav-dd-item">🌏 Cộng đồng</a>
        <a href="rooms.php"     class="nav-dd-item">💬 Phòng chat</a>
        <div class="nav-dd-sep"></div>
        <a href="mindmap.php"   class="nav-dd-item">🗺️ Mind Map</a>
        <a href="math.php"      class="nav-dd-item">📐 Toán</a>
        <a href="quiz.php"      class="nav-dd-item">🎯 Quiz</a>
        <div class="nav-dd-sep"></div>
        <a href="logout.php"    class="nav-dd-item danger">🚪 Đăng xuất</a>
      </div>
    </div>
  </div>
</nav>

<!-- MOBILE TOP BAR -->
<div class="mobile-topbar">
  <a href="dashboard.php" class="logo"><div class="logo-icon">⚡</div>Mind<span>Spark</span></a>
  <div style="display:flex;align-items:center;gap:8px;">
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtnMobile">🌙</button>
    <a href="profile.php">
      <?php if(!empty($user['avatar'])): ?>
        <img src="<?=htmlspecialchars($user['avatar'])?>" class="nav-avatar-img">
      <?php else: ?>
        <div class="avatar"><?=mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'))?></div>
      <?php endif;?>
    </a>
  </div>
</div>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="dashboard.php" class="bottom-nav-item <?=$current=='dashboard.php'?'active':''?>"><span class="bottom-nav-icon">📊</span><span class="bottom-nav-label">Home</span></a>
  <a href="chat.php"      class="bottom-nav-item <?=$current=='chat.php'?'active':''?>"><span class="bottom-nav-icon">🧠</span><span class="bottom-nav-label">AI</span></a>
  <a href="community.php" class="bottom-nav-item <?=$current=='community.php'?'active':''?>"><span class="bottom-nav-icon">🌏</span><span class="bottom-nav-label">Cộng đồng</span></a>
  <a href="rooms.php"     class="bottom-nav-item <?=$current=='rooms.php'?'active':''?>"><span class="bottom-nav-icon">💬</span><span class="bottom-nav-label">Chat</span></a>
  <button class="bottom-nav-item" onclick="toggleDropdown(event)"><span class="bottom-nav-icon">☰</span><span class="bottom-nav-label">Thêm</span></button>
</nav>

<div class="more-dropdown" id="moreDropdown">
  <a href="profile.php">👤 Hồ sơ cá nhân</a>
  <a href="flashcard.php">⚡ Flashcard</a>
  <a href="notes.php">🗒️ Ghi chú</a>
  <a href="planner.php">📅 Kế hoạch</a>
  <a href="pomodoro.php">🍅 Pomodoro</a>
  <a href="mindmap.php">🗺️ Mind Map</a>
  <a href="math.php">📐 Toán</a>
  <a href="quiz.php">🎯 Quiz</a>
  <a href="logout.php">🚪 Đăng xuất</a>
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
function toggleTheme() {
  const html=document.documentElement;
  const next=html.getAttribute('data-theme')==='light'?'dark':'light';
  html.setAttribute('data-theme',next);
  localStorage.setItem('theme',next);
  const icon=next==='dark'?'☀️':'🌙';
  ['themeBtn','themeBtnMobile'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=icon;});
}
document.addEventListener('DOMContentLoaded',function(){
  const t=localStorage.getItem('theme')||'light';
  const icon=t==='dark'?'☀️':'🌙';
  ['themeBtn','themeBtnMobile'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=icon;});
});
</script>
