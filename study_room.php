<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$user = getCurrentUser();

$db->exec("CREATE TABLE IF NOT EXISTS study_rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT UNIQUE NOT NULL,
    host_id INTEGER NOT NULL,
    timer_state TEXT DEFAULT 'idle',
    timer_end INTEGER DEFAULT 0,
    focus_min INTEGER DEFAULT 25,
    break_min INTEGER DEFAULT 5,
    session_count INTEGER DEFAULT 0,
    topic TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS study_room_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT 'active',
    UNIQUE(room_id,user_id)
)");

if($_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json');
    $input=json_decode(file_get_contents('php://input'),true)??[];
    $action=$input['action']??'';

    if($action==='create_room'){
        $name=SQLite3::escapeString(mb_substr($input['name']??'Phòng học',0,50));
        $topic=SQLite3::escapeString(mb_substr($input['topic']??'',0,100));
        $focusMin=max(5,min(60,(int)($input['focus_min']??25)));
        $breakMin=max(1,min(30,(int)($input['break_min']??5)));
        $code=strtoupper(substr(md5(uniqid()),0,6));
        $db->exec("INSERT INTO study_rooms (name,code,host_id,topic,focus_min,break_min) VALUES ('$name','$code',$uid,'$topic',$focusMin,$breakMin)");
        $roomId=$db->lastInsertRowID();
        $db->exec("INSERT OR REPLACE INTO study_room_members (room_id,user_id,last_seen) VALUES ($roomId,$uid,CURRENT_TIMESTAMP)");
        echo json_encode(['ok'=>true,'room_id'=>$roomId,'code'=>$code]); exit;
    }

    if($action==='join_room'){
        $code=strtoupper(SQLite3::escapeString($input['code']??''));
        $room=$db->query("SELECT * FROM study_rooms WHERE code='$code'")->fetchArray(SQLITE3_ASSOC);
        if(!$room){echo json_encode(['ok'=>false,'msg'=>'Không tìm thấy phòng!']);exit;}
        $db->exec("INSERT OR REPLACE INTO study_room_members (room_id,user_id,last_seen) VALUES ({$room['id']},$uid,CURRENT_TIMESTAMP)");
        echo json_encode(['ok'=>true,'room_id'=>$room['id'],'room'=>$room]); exit;
    }

    if($action==='get_room'){
        $roomId=(int)($input['room_id']??0);
        $room=$db->query("SELECT * FROM study_rooms WHERE id=$roomId")->fetchArray(SQLITE3_ASSOC);
        if(!$room){echo json_encode(['ok'=>false]);exit;}
        $db->exec("INSERT OR REPLACE INTO study_room_members (room_id,user_id,last_seen) VALUES ($roomId,$uid,CURRENT_TIMESTAMP)");
        $db->exec("DELETE FROM study_room_members WHERE room_id=$roomId AND last_seen < datetime('now','-2 minutes')");
        $members=[];
        $mRows=$db->query("SELECT u.id,u.name,u.avatar FROM study_room_members m JOIN users u ON m.user_id=u.id WHERE m.room_id=$roomId");
        while($r=$mRows->fetchArray(SQLITE3_ASSOC)) $members[]=$r;
        echo json_encode(['ok'=>true,'room'=>$room,'members'=>$members,'now'=>time()]); exit;
    }

    if($action==='start_timer'){
        $roomId=(int)($input['room_id']??0);
        $room=$db->query("SELECT * FROM study_rooms WHERE id=$roomId")->fetchArray(SQLITE3_ASSOC);
        if(!$room||$room['host_id']!=$uid){echo json_encode(['ok'=>false,'msg'=>'Chỉ host mới được điều khiển!']);exit;}
        $focusSecs=$room['focus_min']*60;
        $endTime=time()+$focusSecs;
        $db->exec("UPDATE study_rooms SET timer_state='focus',timer_end=$endTime,session_count=session_count+1 WHERE id=$roomId");
        echo json_encode(['ok'=>true,'timer_end'=>$endTime,'state'=>'focus']); exit;
    }

    if($action==='stop_timer'){
        $roomId=(int)($input['room_id']??0);
        $room=$db->query("SELECT * FROM study_rooms WHERE id=$roomId")->fetchArray(SQLITE3_ASSOC);
        if(!$room||$room['host_id']!=$uid){echo json_encode(['ok'=>false]);exit;}
        $db->exec("UPDATE study_rooms SET timer_state='idle',timer_end=0 WHERE id=$roomId");
        echo json_encode(['ok'=>true]); exit;
    }

    if($action==='break_timer'){
        $roomId=(int)($input['room_id']??0);
        $room=$db->query("SELECT * FROM study_rooms WHERE id=$roomId")->fetchArray(SQLITE3_ASSOC);
        if(!$room||$room['host_id']!=$uid){echo json_encode(['ok'=>false]);exit;}
        $breakSecs=$room['break_min']*60;
        $endTime=time()+$breakSecs;
        $db->exec("UPDATE study_rooms SET timer_state='break',timer_end=$endTime WHERE id=$roomId");
        echo json_encode(['ok'=>true,'timer_end'=>$endTime,'state'=>'break']); exit;
    }

    echo json_encode(['ok'=>false]); exit;
}

$roomId=(int)($_GET['room']??0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Study Room — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.room-hero{background:linear-gradient(135deg,#0f172a,#1e1b4b,#312e81);border-radius:var(--radius-xl);padding:2rem;color:#fff;text-align:center;margin-bottom:1.25rem;position:relative;overflow:hidden;}
.room-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 50% 50%,rgba(139,92,246,0.2),transparent 70%);pointer-events:none;}
.timer-display{font-size:5rem;font-weight:900;letter-spacing:-4px;font-variant-numeric:tabular-nums;margin:1rem 0;line-height:1;text-shadow:0 0 40px rgba(139,92,246,0.8);}
.timer-state{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:2px;opacity:0.7;margin-bottom:0.5rem;}
.timer-ring{width:200px;height:200px;margin:0 auto;position:relative;}
.timer-ring svg{transform:rotate(-90deg);}
.timer-ring-bg{fill:none;stroke:rgba(255,255,255,0.1);stroke-width:8;}
.timer-ring-fill{fill:none;stroke:#818cf8;stroke-width:8;stroke-linecap:round;transition:stroke-dashoffset 1s linear;}
.timer-ring-inner{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.ctrl-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:1rem;}
.ctrl-btn{padding:10px 20px;border-radius:12px;border:none;font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;transition:all 0.15s;}
.ctrl-btn.primary{background:rgba(255,255,255,0.2);color:#fff;border:1.5px solid rgba(255,255,255,0.3);}
.ctrl-btn.primary:hover{background:rgba(255,255,255,0.3);}
.ctrl-btn.danger{background:rgba(239,68,68,0.2);color:#fca5a5;border:1.5px solid rgba(239,68,68,0.3);}
.members-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;}
.member-card{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:12px;text-align:center;}
.member-name{font-size:12px;font-weight:700;color:var(--text);margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.member-status{font-size:10px;color:var(--muted);margin-top:2px;}
.room-code{font-size:1.5rem;font-weight:900;letter-spacing:6px;color:var(--accent);background:var(--accent-soft);border-radius:12px;padding:10px 20px;display:inline-block;margin:8px 0;}
.lobby{max-width:480px;margin:0 auto;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div id="lobbyView" style="display:<?= $roomId?'none':'block' ?>;">
    <div class="page-header">
      <div class="page-eyebrow">Cùng học</div>
      <h1 class="page-title">🏠 Study Room</h1>
    </div>
    <div class="lobby">
      <div class="card" style="margin-bottom:1rem;">
        <div class="card-body">
          <div style="font-size:15px;font-weight:800;margin-bottom:14px;">➕ Tạo phòng mới</div>
          <input type="text" id="roomName" class="form-input" placeholder="Tên phòng học..." style="width:100%;margin-bottom:8px;">
          <input type="text" id="roomTopic" class="form-input" placeholder="Chủ đề hôm nay (tuỳ chọn)" style="width:100%;margin-bottom:8px;">
          <div style="display:flex;gap:8px;margin-bottom:12px;">
            <div style="flex:1;"><label style="font-size:11px;font-weight:700;color:var(--muted);">Tập trung (phút)</label>
            <input type="number" id="focusMin" class="form-input" value="25" min="5" max="60" style="width:100%;margin-top:4px;"></div>
            <div style="flex:1;"><label style="font-size:11px;font-weight:700;color:var(--muted);">Nghỉ (phút)</label>
            <input type="number" id="breakMin" class="form-input" value="5" min="1" max="30" style="width:100%;margin-top:4px;"></div>
          </div>
          <button class="btn btn-primary" onclick="createRoom()" style="width:100%;">🚀 Tạo phòng</button>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <div style="font-size:15px;font-weight:800;margin-bottom:14px;">🔗 Tham gia phòng</div>
          <input type="text" id="joinCode" class="form-input" placeholder="Nhập mã phòng (6 ký tự)..." style="width:100%;margin-bottom:10px;text-transform:uppercase;letter-spacing:4px;text-align:center;font-weight:900;font-size:1.1rem;" maxlength="6">
          <button class="btn btn-ghost" onclick="joinRoom()" style="width:100%;">🚪 Vào phòng</button>
        </div>
      </div>
    </div>
  </div>

  <div id="roomView" style="display:<?= $roomId?'block':'none' ?>;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
      <button class="btn btn-ghost btn-sm" onclick="leaveRoom()">← Thoát</button>
      <div>
        <div style="font-size:16px;font-weight:800;" id="roomNameDisplay">Đang tải...</div>
        <div style="font-size:12px;color:var(--muted);">Mã: <span id="roomCodeDisplay" style="font-weight:800;color:var(--accent);letter-spacing:3px;cursor:pointer;" onclick="copyCode()">---</span> <span style="font-size:10px;">📋</span></div>
      </div>
    </div>

    <div class="room-hero">
      <div class="timer-state" id="timerState">⏸ Chờ bắt đầu</div>
      <div class="timer-ring">
        <svg width="200" height="200" viewBox="0 0 200 200">
          <circle class="timer-ring-bg" cx="100" cy="100" r="88"/>
          <circle class="timer-ring-fill" id="timerRingFill" cx="100" cy="100" r="88"
            stroke-dasharray="553" stroke-dashoffset="553"/>
        </svg>
        <div class="timer-ring-inner">
          <div class="timer-display" id="timerDisplay">25:00</div>
          <div style="font-size:12px;opacity:0.6;" id="sessionCount">Phiên 0</div>
        </div>
      </div>
      <div class="ctrl-btns" id="hostControls" style="display:none;">
        <button class="ctrl-btn primary" id="startBtn" onclick="startTimer()">▶ Bắt đầu</button>
        <button class="ctrl-btn primary" id="breakBtn" onclick="startBreak()" style="display:none;">☕ Nghỉ</button>
        <button class="ctrl-btn danger" id="stopBtn" onclick="stopTimer()" style="display:none;">⏹ Dừng</button>
      </div>
      <div id="guestMsg" style="display:none;font-size:12px;opacity:0.6;margin-top:10px;">Host đang điều khiển đồng hồ</div>
    </div>

    <div class="card">
      <div class="card-body">
        <div style="font-size:14px;font-weight:800;margin-bottom:12px;">👥 Thành viên đang học</div>
        <div class="members-grid" id="membersList">
          <div style="color:var(--muted);font-size:13px;">Đang tải...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const ME = <?= $uid ?>;
const ME_NAME = <?= json_encode($user['name']) ?>;
let currentRoomId = <?= $roomId ?: 0 ?>;
let roomData = null;
let timerInterval = null;
let pollInterval = null;

async function createRoom(){
  const name=document.getElementById('roomName').value.trim()||'Phòng học';
  const res=await fetch('study_room.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'create_room',name,topic:document.getElementById('roomTopic').value,
    focus_min:parseInt(document.getElementById('focusMin').value)||25,
    break_min:parseInt(document.getElementById('breakMin').value)||5})});
  const data=await res.json();
  if(data.ok){currentRoomId=data.room_id;enterRoom();}
}

async function joinRoom(){
  const code=document.getElementById('joinCode').value.trim().toUpperCase();
  if(code.length!==6){alert('Mã phòng phải có 6 ký tự!');return;}
  const res=await fetch('study_room.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'join_room',code})});
  const data=await res.json();
  if(data.ok){currentRoomId=data.room_id;enterRoom();}
  else alert(data.msg||'Không tìm thấy phòng!');
}

function enterRoom(){
  document.getElementById('lobbyView').style.display='none';
  document.getElementById('roomView').style.display='block';
  window.history.pushState({},'','?room='+currentRoomId);
  pollRoom();
  pollInterval=setInterval(pollRoom,3000);
}

async function pollRoom(){
  const res=await fetch('study_room.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_room',room_id:currentRoomId})});
  const data=await res.json();
  if(!data.ok)return;
  roomData=data.room;
  const isHost=roomData.host_id==ME;
  document.getElementById('roomNameDisplay').textContent=roomData.name+(roomData.topic?' · '+roomData.topic:'');
  document.getElementById('roomCodeDisplay').textContent=roomData.code;
  document.getElementById('sessionCount').textContent='Phiên '+roomData.session_count;
  document.getElementById('hostControls').style.display=isHost?'flex':'none';
  document.getElementById('guestMsg').style.display=isHost?'none':'block';
  // Sync timer
  syncTimer(roomData,data.now);
  // Members
  document.getElementById('membersList').innerHTML=data.members.map(m=>`
    <div class="member-card">
      <div style="width:40px;height:40px;border-radius:50%;background:#4f6ef7;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;margin:0 auto;font-size:1rem;">${m.name[0].toUpperCase()}</div>
      <div class="member-name">${m.name}${m.id==roomData.host_id?' 👑':''}</div>
      <div class="member-status">${roomData.timer_state==='focus'?'🔴 Đang học':roomData.timer_state==='break'?'☕ Nghỉ':'💤 Chờ'}</div>
    </div>`).join('');
}

function syncTimer(room,serverNow){
  const state=room.timer_state;
  const endTime=parseInt(room.timer_end);
  const totalSecs=state==='focus'?room.focus_min*60:room.break_min*60;
  if(timerInterval){clearInterval(timerInterval);timerInterval=null;}
  if(state==='idle'){
    document.getElementById('timerState').textContent='⏸ Chờ bắt đầu';
    document.getElementById('timerDisplay').textContent=room.focus_min+':00';
    document.getElementById('timerRingFill').style.strokeDashoffset='553';
    const isHost=room.host_id==ME;
    if(isHost){document.getElementById('startBtn').style.display='';document.getElementById('stopBtn').style.display='none';document.getElementById('breakBtn').style.display='none';}
    return;
  }
  const circumference=553;
  function tick(){
    const remaining=endTime-Math.floor(Date.now()/1000);
    if(remaining<=0){
      clearInterval(timerInterval);
      document.getElementById('timerDisplay').textContent='00:00';
      document.getElementById('timerState').textContent=state==='focus'?'✅ Xong rồi!':'🎯 Hết giờ nghỉ!';
      if(room.host_id==ME){document.getElementById('startBtn').style.display='';document.getElementById('breakBtn').style.display=state==='focus'?'':'none';document.getElementById('stopBtn').style.display='none';}
      return;
    }
    const m=Math.floor(remaining/60).toString().padStart(2,'0');
    const s=(remaining%60).toString().padStart(2,'0');
    document.getElementById('timerDisplay').textContent=m+':'+s;
    document.getElementById('timerState').textContent=state==='focus'?'🔴 Đang tập trung':'☕ Đang nghỉ';
    const offset=circumference*(1-remaining/totalSecs);
    document.getElementById('timerRingFill').style.strokeDashoffset=offset;
    document.getElementById('timerRingFill').style.stroke=state==='focus'?'#818cf8':'#34d399';
    if(room.host_id==ME){document.getElementById('startBtn').style.display='none';document.getElementById('stopBtn').style.display='';document.getElementById('breakBtn').style.display='none';}
  }
  tick();
  timerInterval=setInterval(tick,1000);
}

async function startTimer(){
  await fetch('study_room.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'start_timer',room_id:currentRoomId})});
  pollRoom();
}
async function startBreak(){
  await fetch('study_room.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'break_timer',room_id:currentRoomId})});
  pollRoom();
}
async function stopTimer(){
  await fetch('study_room.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'stop_timer',room_id:currentRoomId})});
  pollRoom();
}
function leaveRoom(){
  clearInterval(pollInterval);clearInterval(timerInterval);
  currentRoomId=0;
  document.getElementById('lobbyView').style.display='block';
  document.getElementById('roomView').style.display='none';
  window.history.pushState({},'','study_room.php');
}
function copyCode(){
  if(roomData)navigator.clipboard.writeText(roomData.code).then(()=>alert('Đã copy mã phòng: '+roomData.code));
}

<?php if($roomId): ?>
window.addEventListener('load',()=>enterRoom());
<?php endif; ?>
</script>
</body>
</html>
