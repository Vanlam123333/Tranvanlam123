<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid  = $_SESSION['user_id'];
$user = getCurrentUser();

$db->exec("CREATE TABLE IF NOT EXISTS cowork_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL,
    host_id INTEGER NOT NULL, title TEXT, music TEXT DEFAULT 'lofi',
    status TEXT DEFAULT 'active', pomo_duration INTEGER DEFAULT 25,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS cowork_whiteboard (
    id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL, data TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
@$db->exec("ALTER TABLE chat_rooms ADD COLUMN is_cowork INTEGER DEFAULT 0");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $a = $_POST['action'] ?? '';

    if ($a === 'create_session') {
        $rid   = (int)($_POST['room_id']??0);
        $title = mb_substr(trim($_POST['title']??'Phiên học nhóm'),0,80);
        $music = trim($_POST['music']??'lofi');
        $pomo  = max(5,min(60,(int)($_POST['pomo']??25)));
        // End any existing active sessions in this room
        $db->exec("UPDATE cowork_sessions SET status='ended' WHERE room_id=$rid AND status='active'");
        $st=$db->prepare('INSERT INTO cowork_sessions (room_id,host_id,title,music,pomo_duration) VALUES(:r,:h,:t,:m,:p)');
        $st->bindValue(':r',$rid);$st->bindValue(':h',$uid);
        $st->bindValue(':t',$title);$st->bindValue(':m',$music);$st->bindValue(':p',$pomo);
        $st->execute();
        $sid=$db->lastInsertRowID();
        echo json_encode(['ok'=>true,'session_id'=>$sid,'title'=>htmlspecialchars($title),'music'=>$music,'pomo'=>$pomo]); exit;
    }

    if ($a === 'get_session') {
        $rid=(int)($_POST['room_id']??0);
        $row=$db->query("SELECT * FROM cowork_sessions WHERE room_id=$rid AND status='active' ORDER BY id DESC LIMIT 1")->fetchArray(SQLITE3_ASSOC);
        if(!$row){echo json_encode(['ok'=>false]);exit;}
        echo json_encode(['ok'=>true,'session'=>[
            'id'=>$row['id'],'title'=>htmlspecialchars($row['title']),
            'music'=>$row['music'],'pomo'=>$row['pomo_duration'],
            'started'=>$row['started_at'],'host'=>$row['host_id']===$uid,
        ]]); exit;
    }

    if ($a === 'save_whiteboard') {
        $sid=(int)($_POST['session_id']??0);
        $data=trim($_POST['data']??'');
        $ex=$db->query("SELECT id FROM cowork_whiteboard WHERE session_id=$sid AND user_id=$uid")->fetchArray();
        if($ex){
            $st=$db->prepare('UPDATE cowork_whiteboard SET data=:d,updated_at=CURRENT_TIMESTAMP WHERE session_id=:s AND user_id=:u');
        } else {
            $st=$db->prepare('INSERT INTO cowork_whiteboard (session_id,user_id,data) VALUES(:s,:u,:d)');
            $st->bindValue(':s',$sid);$st->bindValue(':u',$uid);
        }
        $st->bindValue(':d',$data);
        $st->execute();
        echo json_encode(['ok'=>true]); exit;
    }

    if ($a === 'get_whiteboard') {
        $sid=(int)($_POST['session_id']??0);
        $row=$db->query("SELECT data FROM cowork_whiteboard WHERE session_id=$sid ORDER BY updated_at DESC LIMIT 1")->fetchArray(SQLITE3_ASSOC);
        echo json_encode(['ok'=>true,'data'=>$row['data']??'']); exit;
    }

    if ($a === 'end_session') {
        $sid=(int)($_POST['session_id']??0);
        $db->exec("UPDATE cowork_sessions SET status='ended' WHERE id=$sid");
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false]); exit;
}

// Load rooms for selector
$rooms=[];
$rq=$db->query("SELECT * FROM chat_rooms WHERE is_public=1 ORDER BY id ASC");
while($r=$rq->fetchArray(SQLITE3_ASSOC)) $rooms[]=$r;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Co-working Space — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.cw-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 20px;
  height: calc(100vh - 120px);
  min-height: 500px;
}

.cw-sidebar {
  display: flex; flex-direction: column; gap: 14px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 18px;
  overflow-y: auto;
  height: 100%;
}

.cw-main {
  display: flex; flex-direction: column; gap: 14px;
  height: 100%;
}

/* Pomodoro */
.pomo-ring {
  position: relative;
  width: 140px; height: 140px;
  margin: 0 auto;
}
.pomo-ring svg { position: absolute; top: 0; left: 0; transform: rotate(-90deg); }
.pomo-time {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 30px; font-weight: 800;
  color: var(--text); font-family: var(--mono, monospace);
}

/* Music player */
.music-track {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px;
  background: var(--surface2);
  border-radius: 12px;
  border: 1px solid var(--border);
}
.music-icon { font-size: 24px; flex-shrink: 0; }
.music-info { flex: 1; min-width: 0; }
.music-name { font-size: 13px; font-weight: 600; color: var(--text); }
.music-artist { font-size: 11px; color: var(--muted); }
.music-waveform {
  display: flex; gap: 2px; align-items: center;
  height: 24px;
}
.mw-bar {
  width: 3px; border-radius: 2px;
  background: var(--accent);
  animation: mwave 0.8s ease-in-out infinite alternate;
}
@keyframes mwave {
  from { height: 4px; }
  to   { height: 20px; }
}

/* Whiteboard */
.whiteboard-wrap {
  flex: 1;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.wb-toolbar {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px;
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
  flex-wrap: wrap;
}
.wb-btn {
  padding: 6px 12px; border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text); cursor: pointer;
  font-size: 12px; font-weight: 600;
  font-family: var(--font);
  transition: background .1s;
}
.wb-btn:hover { background: var(--border); }
.wb-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.wb-canvas {
  flex: 1;
  width: 100%; height: 100%;
  cursor: crosshair;
  background: var(--bg);
  display: block;
}

/* Room selector */
.room-select-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 12px;
  cursor: pointer; transition: background .1s;
}
.room-select-item:hover { background: var(--surface2); }
.room-select-item.active { background: rgba(99,102,241,.12); }
.room-select-icon { font-size: 18px; flex-shrink: 0; }

/* Labels */
.cw-label {
  font-size: 11px; font-weight: 800; color: var(--muted);
  text-transform: uppercase; letter-spacing: .5px;
  margin-bottom: 8px;
}

/* Control btns */
.cw-btn {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  width: 100%; padding: 10px; border-radius: 12px;
  background: var(--accent); color: #fff; border: none;
  cursor: pointer; font-family: var(--font); font-weight: 700;
  font-size: 13px; transition: all .15s;
}
.cw-btn:hover { background: var(--accent-hover); }
.cw-btn.ghost {
  background: var(--surface2); color: var(--text);
  border: 1px solid var(--border);
}
.cw-btn.ghost:hover { background: var(--border); }
.cw-btn.red { background: #ef4444; }
.cw-btn.green { background: #10b981; }

/* Session info bar */
.session-bar {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  flex-shrink: 0;
}
.session-title { font-size: 15px; font-weight: 700; color: var(--text); flex: 1; }
.session-status { font-size: 12px; color: #22c55e; font-weight: 500; display: flex; align-items: center; gap: 5px; }
.session-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1;}50%{opacity:.4;} }

/* Color swatches */
.swatch {
  width: 22px; height: 22px; border-radius: 50%;
  cursor: pointer; border: 2px solid transparent;
  flex-shrink: 0;
}
.swatch.active { border-color: var(--text); transform: scale(1.2); }

/* modal */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-bg.show{display:flex;}
.modal{background:var(--surface);border-radius:20px;padding:24px 22px;width:440px;max-width:94vw;max-height:85vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.3);}
.modal-title{font-size:17px;font-weight:700;margin-bottom:16px;color:var(--text);}
.form-label{font-size:11px;font-weight:800;color:var(--muted);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px;}
.form-input{width:100%;padding:10px 13px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:var(--font);font-size:14px;outline:none;box-sizing:border-box;}
.form-input:focus{border-color:var(--accent);}

@media(max-width:768px){
  .cw-layout { grid-template-columns: 1fr; height: auto; }
  .cw-sidebar { height: auto; max-height: 40vh; }
  .whiteboard-wrap { min-height: 300px; }
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <h1 class="page-title">🎓 Phòng học/Làm việc nhóm</h1>

  <div class="cw-layout">
    <!-- SIDEBAR -->
    <div class="cw-sidebar">
      <div>
        <div class="cw-label">Chọn phòng</div>
        <div id="roomSelectList">
          <?php foreach($rooms as $r): ?>
          <div class="room-select-item <?=$r['id']==($rooms[0]['id']??0)?'active':''?>"
               id="rsel-<?=$r['id']?>" onclick="selectRoom(<?=$r['id']?>,'<?=addslashes(htmlspecialchars($r['name']))?>')">
            <span class="room-select-icon"><?=htmlspecialchars($r['icon']??'💬')?></span>
            <span style="font-size:13px;font-weight:600;color:var(--text);"><?=htmlspecialchars($r['name'])?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div id="pomoSection">
        <div class="cw-label">Pomodoro</div>
        <div class="pomo-ring" id="pomoRing">
          <svg width="140" height="140" viewBox="0 0 140 140">
            <circle cx="70" cy="70" r="58" fill="none" stroke="var(--border2)" stroke-width="10"/>
            <circle cx="70" cy="70" r="58" fill="none" stroke="var(--accent)" stroke-width="10"
                    stroke-dasharray="364" stroke-dashoffset="0" id="pomoProgress" stroke-linecap="round"/>
          </svg>
          <div class="pomo-time" id="pomoTime">25:00</div>
        </div>
        <div style="text-align:center;font-size:12px;color:var(--muted);margin-top:8px;" id="pomoStatus">Chưa bắt đầu</div>
        <div style="display:flex;gap:6px;margin-top:10px;">
          <button class="cw-btn" onclick="startPomo()" id="pomoStartBtn">▶ Bắt đầu</button>
          <button class="cw-btn ghost" onclick="resetPomo()">↺</button>
        </div>
      </div>

      <div id="musicSection">
        <div class="cw-label">Nhạc nền</div>
        <div class="music-track" id="nowPlaying">
          <div class="music-icon" id="musicIcon">🎵</div>
          <div class="music-info">
            <div class="music-name" id="musicName">Lo-fi Hip Hop</div>
            <div class="music-artist" id="musicArtist">Chill Beats Radio</div>
          </div>
          <div class="music-waveform" id="musicWave">
            <?php for($i=0;$i<5;$i++): ?>
            <div class="mw-bar" style="animation-delay:<?=$i*.1?>s"></div>
            <?php endfor; ?>
          </div>
        </div>
        <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">
          <?php foreach([
            ['lofi','🎵','Lo-fi'],['ambient','🌊','Ambient'],
            ['jazz','🎷','Jazz'],['classical','🎻','Classical'],['silence','🔇','Im lặng'],
          ] as [$k,$e,$n]): ?>
          <button class="cw-btn ghost" style="flex:0;padding:5px 8px;font-size:11px;"
                  onclick="setMusic('<?=$k?>','<?=$e?>','<?=$n?>')"><?=$e?> <?=$n?></button>
          <?php endforeach; ?>
        </div>
        <!-- Hidden audio player -->
        <audio id="bgAudio" loop style="display:none;"></audio>
        <div style="margin-top:6px;">
          <input type="range" id="volSlider" min="0" max="100" value="40"
                 style="width:100%;accent-color:var(--accent);" oninput="setVol(this.value)">
          <div style="font-size:11px;color:var(--muted);text-align:center;">🔊 Âm lượng</div>
        </div>
      </div>

      <div>
        <button class="cw-btn green" onclick="showModal('startSessionModal')">＋ Tạo phiên học</button>
        <button class="cw-btn ghost" style="margin-top:6px;" onclick="endCurrentSession()">⏹ Kết thúc phiên</button>
      </div>
    </div>

    <!-- MAIN AREA -->
    <div class="cw-main">
      <!-- Session bar -->
      <div class="session-bar" id="sessionBar">
        <div class="session-status"><div class="session-dot"></div>Chờ khởi động</div>
        <div class="session-title" id="sessionTitle">Chọn phòng và tạo phiên học để bắt đầu</div>
        <div style="font-size:12px;color:var(--muted);" id="sessionParticipants"></div>
      </div>

      <!-- Whiteboard -->
      <div class="whiteboard-wrap">
        <div class="wb-toolbar">
          <span style="font-size:13px;font-weight:700;color:var(--text);">✏️ Bảng trắng</span>
          <!-- Tools -->
          <button class="wb-btn active" id="tbPen" onclick="setTool('pen')">🖊 Bút</button>
          <button class="wb-btn" id="tbEraser" onclick="setTool('eraser')">⬜ Xóa</button>
          <button class="wb-btn" id="tbText" onclick="setTool('text')">T Chữ</button>
          <button class="wb-btn" id="tbLine" onclick="setTool('line')">╱ Đường</button>
          <button class="wb-btn" id="tbRect" onclick="setTool('rect')">▭ Hình chữ nhật</button>
          <!-- Colors -->
          <div style="display:flex;gap:4px;align-items:center;margin-left:4px;">
            <?php foreach(['#6366f1','#ef4444','#10b981','#f59e0b','#000000','#ffffff'] as $c): ?>
            <div class="swatch" style="background:<?=$c?>;" onclick="setColor('<?=$c?>',this)" title="<?=$c?>"></div>
            <?php endforeach; ?>
          </div>
          <!-- Size -->
          <input type="range" id="brushSize" min="1" max="30" value="3"
                 style="width:70px;accent-color:var(--accent);" oninput="document.getElementById('bsVal').textContent=this.value">
          <span id="bsVal" style="font-size:11px;color:var(--muted);min-width:16px;">3</span>
          <div style="margin-left:auto;display:flex;gap:4px;">
            <button class="wb-btn" onclick="undoWb()">↩ Undo</button>
            <button class="wb-btn" onclick="clearWb()">🗑 Xóa</button>
            <button class="wb-btn" onclick="saveWb()">💾 Lưu</button>
            <button class="wb-btn" onclick="downloadWb()">⬇ Tải</button>
          </div>
        </div>
        <canvas class="wb-canvas" id="wbCanvas"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Start Session Modal -->
<div class="modal-bg" id="startSessionModal" onclick="if(event.target===this)hideModal('startSessionModal')">
  <div class="modal">
    <div class="modal-title">🎓 Tạo phiên học nhóm</div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Tiêu đề phiên</label>
      <input type="text" id="sesTitle" class="form-input" placeholder="Ôn thi Toán, Làm project nhóm...">
    </div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Nhạc nền</label>
      <select id="sesMusic" class="form-input">
        <option value="lofi">🎵 Lo-fi Hip Hop</option>
        <option value="ambient">🌊 Ambient</option>
        <option value="jazz">🎷 Jazz</option>
        <option value="classical">🎻 Classical</option>
        <option value="silence">🔇 Im lặng</option>
      </select>
    </div>
    <div style="margin-bottom:4px;">
      <label class="form-label">Thời gian Pomodoro (phút)</label>
      <div style="display:flex;gap:6px;">
        <?php foreach([15,25,45,60] as $m): ?>
        <button class="cw-btn ghost pomoOpt" data-m="<?=$m?>" onclick="setPomoOpt(<?=$m?>,this)"
                style="flex:1;padding:8px;font-size:13px;"><?=$m?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:16px;">
      <button class="cw-btn" onclick="createSession()">🚀 Bắt đầu phiên học</button>
      <button class="cw-btn ghost" onclick="hideModal('startSessionModal')">Huỷ</button>
    </div>
  </div>
</div>

<script>
let currentRoomId = <?=($rooms[0]['id']??1)?>;
let currentSessionId = null;
let pomoTotal = 25 * 60;
let pomoLeft = 25 * 60;
let pomoRunning = false;
let pomoTimer = null;
let pomoPhase = 'focus'; // 'focus' or 'break'
let selectedPomoMin = 25;

// Whiteboard
let wbTool = 'pen';
let wbColor = '#6366f1';
let wbHistory = [];
let wbDrawing = false;
let wbStartX, wbStartY;
let wbCanvas, wbCtx, wbSnapshot;
let wbTextMode = false;

// ── UTILS ──
function showModal(id){document.getElementById(id).classList.add('show');}
function hideModal(id){document.getElementById(id).classList.remove('show');}
function showToast(msg,type='ok'){
  const t=document.createElement('div');
  t.style.cssText=`position:fixed;bottom:80px;right:20px;z-index:9999;padding:12px 18px;border-radius:12px;font-size:14px;font-weight:600;color:#fff;background:${type==='ok'?'#10b981':'#ef4444'};box-shadow:0 4px 20px rgba(0,0,0,.2);transition:opacity .3s;`;
  t.textContent=msg; document.body.appendChild(t);
  setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),300);},2500);
}

// ── ROOM SELECT ──
function selectRoom(rid, name) {
  document.querySelectorAll('.room-select-item').forEach(e=>e.classList.remove('active'));
  document.getElementById('rsel-'+rid)?.classList.add('active');
  currentRoomId = rid;
  checkExistingSession();
}

async function checkExistingSession() {
  const fd=new FormData(); fd.append('action','get_session'); fd.append('room_id',currentRoomId);
  const r=await fetch('cowork.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){
    currentSessionId=d.session.id;
    document.getElementById('sessionTitle').textContent=d.session.title;
    document.getElementById('sessionBar').querySelector('.session-status').innerHTML='<div class="session-dot"></div>Đang diễn ra';
    // Apply pomo duration
    pomoTotal = d.session.pomo * 60;
    pomoLeft = pomoTotal;
    updatePomoDisplay();
    // Load whiteboard
    loadWhiteboard();
    showToast('📚 Đã vào phiên: '+d.session.title);
    // Apply music
    setMusic(d.session.music,'🎵',d.session.music);
  } else {
    currentSessionId=null;
    document.getElementById('sessionTitle').textContent='Chưa có phiên học đang chạy trong phòng này';
  }
}

// ── SESSION ──
let selectedPomoForSession = 25;
function setPomoOpt(m,el){
  selectedPomoForSession=m;
  document.querySelectorAll('.pomoOpt').forEach(b=>{b.style.background='';b.style.color='';});
  el.style.background='var(--accent)'; el.style.color='#fff';
}

async function createSession() {
  const title=document.getElementById('sesTitle').value.trim()||'Phiên học nhóm';
  const music=document.getElementById('sesMusic').value;
  const fd=new FormData();
  fd.append('action','create_session'); fd.append('room_id',currentRoomId);
  fd.append('title',title); fd.append('music',music); fd.append('pomo',selectedPomoForSession);
  const r=await fetch('cowork.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){
    hideModal('startSessionModal');
    currentSessionId=d.session_id;
    document.getElementById('sessionTitle').textContent=d.title;
    document.getElementById('sessionBar').querySelector('.session-status').innerHTML='<div class="session-dot"></div>Đang diễn ra';
    pomoTotal=d.pomo*60; pomoLeft=pomoTotal; updatePomoDisplay();
    setMusic(d.music,'🎵',d.music);
    showToast('🚀 Đã tạo phiên học!');
  }
}

async function endCurrentSession(){
  if(!currentSessionId){showToast('Chưa có phiên nào','err');return;}
  const fd=new FormData(); fd.append('action','end_session'); fd.append('session_id',currentSessionId);
  await fetch('cowork.php',{method:'POST',body:fd});
  resetPomo(); currentSessionId=null;
  document.getElementById('sessionTitle').textContent='Phiên học đã kết thúc';
  showToast('✅ Phiên học kết thúc!');
}

// ── POMODORO ──
function setPomoOpt2(m){selectedPomoMin=m; pomoTotal=m*60; pomoLeft=m*60; resetPomo();}
function startPomo(){
  if(!currentSessionId){showToast('Tạo phiên học trước','err');return;}
  if(pomoRunning){clearInterval(pomoTimer);pomoRunning=false;document.getElementById('pomoStartBtn').textContent='▶ Tiếp tục';return;}
  pomoRunning=true;
  document.getElementById('pomoStartBtn').textContent='⏸ Tạm dừng';
  pomoTimer=setInterval(()=>{
    if(!pomoRunning) return;
    pomoLeft--;
    updatePomoDisplay();
    if(pomoLeft<=0){
      clearInterval(pomoTimer);pomoRunning=false;
      if(pomoPhase==='focus'){
        pomoPhase='break'; pomoLeft=5*60; pomoTotal=5*60;
        document.getElementById('pomoStatus').textContent='☕ Nghỉ giải lao 5 phút!';
        showToast('⏰ Hết giờ! Nghỉ 5 phút nhé 🎉');
      } else {
        pomoPhase='focus'; pomoLeft=selectedPomoMin*60; pomoTotal=selectedPomoMin*60;
        document.getElementById('pomoStatus').textContent='💪 Sẵn sàng tập trung!';
        showToast('💪 Hết giờ nghỉ! Tiếp tục nào');
      }
      document.getElementById('pomoStartBtn').textContent='▶ Bắt đầu';
    }
  },1000);
}

function resetPomo(){
  clearInterval(pomoTimer); pomoRunning=false;
  pomoLeft=pomoTotal; updatePomoDisplay();
  document.getElementById('pomoStatus').textContent='Chưa bắt đầu';
  document.getElementById('pomoStartBtn').textContent='▶ Bắt đầu';
}

function updatePomoDisplay(){
  const m=Math.floor(pomoLeft/60).toString().padStart(2,'0');
  const s=(pomoLeft%60).toString().padStart(2,'0');
  document.getElementById('pomoTime').textContent=`${m}:${s}`;
  const pct=pomoLeft/pomoTotal;
  document.getElementById('pomoProgress').style.strokeDashoffset=364*(1-pct);
  const phase=pomoPhase==='focus'?'🎯 Tập trung':'☕ Nghỉ ngơi';
  document.getElementById('pomoStatus').textContent=phase;
}

// ── MUSIC ──
const MUSIC_URLS = {
  lofi: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
  ambient: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
  jazz: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
  classical: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3',
};
let musicPlaying = false;

function setMusic(type, icon, name) {
  const audio = document.getElementById('bgAudio');
  document.getElementById('musicIcon').textContent = icon;
  document.getElementById('musicName').textContent = name;
  const wave = document.getElementById('musicWave');
  if(type==='silence'){
    audio.pause(); musicPlaying=false;
    wave.style.display='none';
    return;
  }
  wave.style.display='flex';
  const url = MUSIC_URLS[type]||MUSIC_URLS.lofi;
  audio.src = url;
  audio.volume = parseInt(document.getElementById('volSlider').value)/100;
  audio.play().catch(()=>{});
  musicPlaying = true;
  document.getElementById('musicArtist').textContent = 'MindSpark Radio';
}

function setVol(v) {
  const audio=document.getElementById('bgAudio');
  audio.volume=v/100;
}

// ── WHITEBOARD ──
function initWb() {
  wbCanvas = document.getElementById('wbCanvas');
  wbCtx = wbCanvas.getContext('2d');
  resizeWb();
  window.addEventListener('resize', resizeWb);
  wbCanvas.addEventListener('mousedown', wbDown);
  wbCanvas.addEventListener('mousemove', wbMove);
  wbCanvas.addEventListener('mouseup', wbUp);
  wbCanvas.addEventListener('mouseleave', wbUp);
  // Touch
  wbCanvas.addEventListener('touchstart', e=>{e.preventDefault();wbDown({clientX:e.touches[0].clientX,clientY:e.touches[0].clientY});},{passive:false});
  wbCanvas.addEventListener('touchmove', e=>{e.preventDefault();wbMove({clientX:e.touches[0].clientX,clientY:e.touches[0].clientY});},{passive:false});
  wbCanvas.addEventListener('touchend', wbUp);
  // Text click
  wbCanvas.addEventListener('dblclick', wbDblClick);
}

function resizeWb() {
  const rect = wbCanvas.parentElement.getBoundingClientRect();
  const imageData = wbCtx?.getImageData(0,0,wbCanvas.width,wbCanvas.height);
  wbCanvas.width = rect.width;
  wbCanvas.height = rect.height - 52;
  if(imageData) wbCtx.putImageData(imageData,0,0);
}

function getPos(e) {
  const rect = wbCanvas.getBoundingClientRect();
  return {x: e.clientX-rect.left, y: e.clientY-rect.top};
}

function setTool(t) {
  wbTool=t;
  document.querySelectorAll('[id^=tb]').forEach(b=>b.classList.remove('active'));
  const map={pen:'tbPen',eraser:'tbEraser',text:'tbText',line:'tbLine',rect:'tbRect'};
  document.getElementById(map[t])?.classList.add('active');
  wbCanvas.style.cursor=t==='eraser'?'cell':t==='text'?'text':'crosshair';
}

function setColor(c, el) {
  wbColor=c;
  document.querySelectorAll('.swatch').forEach(s=>s.classList.remove('active'));
  el.classList.add('active');
}

function wbDown(e){
  const p=getPos(e); wbStartX=p.x; wbStartY=p.y;
  wbDrawing=true;
  wbSnapshot=wbCtx.getImageData(0,0,wbCanvas.width,wbCanvas.height);
  if(wbTool==='pen'||wbTool==='eraser'){
    wbCtx.beginPath(); wbCtx.moveTo(p.x,p.y);
  }
}

function wbMove(e){
  if(!wbDrawing) return;
  const p=getPos(e);
  const size=parseInt(document.getElementById('brushSize').value)||3;
  if(wbTool==='pen'){
    wbCtx.lineWidth=size; wbCtx.strokeStyle=wbColor;
    wbCtx.lineCap='round'; wbCtx.lineJoin='round';
    wbCtx.lineTo(p.x,p.y); wbCtx.stroke();
  } else if(wbTool==='eraser'){
    wbCtx.lineWidth=size*4; wbCtx.strokeStyle='#ffffff';
    wbCtx.lineCap='round';
    wbCtx.lineTo(p.x,p.y); wbCtx.stroke();
  } else if(wbTool==='line'||wbTool==='rect'){
    wbCtx.putImageData(wbSnapshot,0,0);
    wbCtx.lineWidth=size; wbCtx.strokeStyle=wbColor; wbCtx.lineCap='round';
    wbCtx.beginPath();
    if(wbTool==='line'){wbCtx.moveTo(wbStartX,wbStartY);wbCtx.lineTo(p.x,p.y);}
    else{wbCtx.strokeRect(wbStartX,wbStartY,p.x-wbStartX,p.y-wbStartY);}
    wbCtx.stroke();
  }
}

function wbUp(){ wbDrawing=false; saveHistory(); }

function wbDblClick(e){
  if(wbTool!=='text') return;
  const p=getPos(e);
  const text=prompt('Nhập chữ:'); if(!text) return;
  wbCtx.font=`${18}px sans-serif`;
  wbCtx.fillStyle=wbColor; wbCtx.fillText(text,p.x,p.y);
  saveHistory();
}

function saveHistory(){
  wbHistory.push(wbCtx.getImageData(0,0,wbCanvas.width,wbCanvas.height));
  if(wbHistory.length>30) wbHistory.shift();
}

function undoWb(){
  if(wbHistory.length<2){wbCtx.clearRect(0,0,wbCanvas.width,wbCanvas.height);return;}
  wbHistory.pop();
  wbCtx.putImageData(wbHistory[wbHistory.length-1],0,0);
}

function clearWb(){
  if(!confirm('Xóa toàn bộ bảng trắng?')) return;
  wbCtx.clearRect(0,0,wbCanvas.width,wbCanvas.height);
  wbHistory=[];
}

async function saveWb(){
  if(!currentSessionId){showToast('Cần tạo phiên học trước','err');return;}
  const data=wbCanvas.toDataURL('image/png',0.7);
  const fd=new FormData(); fd.append('action','save_whiteboard');
  fd.append('session_id',currentSessionId); fd.append('data',data);
  await fetch('cowork.php',{method:'POST',body:fd});
  showToast('💾 Đã lưu bảng trắng!');
}

async function loadWhiteboard(){
  if(!currentSessionId) return;
  const fd=new FormData(); fd.append('action','get_whiteboard'); fd.append('session_id',currentSessionId);
  const r=await fetch('cowork.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok && d.data){
    const img=new Image(); img.onload=()=>{wbCtx.drawImage(img,0,0);}; img.src=d.data;
  }
}

function downloadWb(){
  const a=document.createElement('a');
  a.download='whiteboard-mindspark.png';
  a.href=wbCanvas.toDataURL('image/png');
  a.click();
}

// ── INIT ──
document.addEventListener('DOMContentLoaded',()=>{
  initWb();
  checkExistingSession();
  setPomoOpt(25, document.querySelector('.pomoOpt[data-m="25"]'));
  // Start lofi by default after user interaction
  document.body.addEventListener('click',()=>{
    if(!musicPlaying) setMusic('lofi','🎵','Lo-fi Hip Hop');
  },{once:true});
});
</script>
</body>
</html>
