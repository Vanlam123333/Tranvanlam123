<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid  = $_SESSION['user_id'];
$user = getCurrentUser();

// ── AJAX ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $rid     = (int)($_POST['room_id']??0);
        $content = trim($_POST['content']??'');
        $replyTo = (int)($_POST['reply_to']??0) ?: null;
        if (!$content || !$rid) { echo json_encode(['ok'=>false]); exit; }
        $st = $db->prepare('INSERT INTO room_messages (room_id,user_id,content,reply_to) VALUES(:r,:u,:c,:rt)');
        $st->bindValue(':r',$rid);$st->bindValue(':u',$uid);
        $st->bindValue(':c',$content);$st->bindValue(':rt',$replyTo);
        $st->execute();
        $mid = $db->lastInsertRowID();
        $msg = $db->query("SELECT m.*,u.name,u.avatar FROM room_messages m JOIN users u ON m.user_id=u.id WHERE m.id=$mid")->fetchArray(SQLITE3_ASSOC);
        $reply = null;
        if ($msg['reply_to']) {
            $rp = $db->query("SELECT m.*,u.name FROM room_messages m JOIN users u ON m.user_id=u.id WHERE m.id={$msg['reply_to']}")->fetchArray(SQLITE3_ASSOC);
            if ($rp) $reply = ['user'=>htmlspecialchars($rp['name']),'text'=>mb_substr(htmlspecialchars($rp['content']),0,80)];
        }
        echo json_encode(['ok'=>true,'message'=>[
            'id'=>$mid,'content'=>htmlspecialchars($msg['content']),
            'user'=>htmlspecialchars($msg['name']),'avatar'=>userAvatar($msg,34),
            'time'=>timeAgo($msg['created_at']),'mine'=>true,'reply'=>$reply,
        ]]); exit;
    }

    if ($action === 'poll') {
        $rid   = (int)($_POST['room_id']??0);
        $after = (int)($_POST['after_id']??0);
        $rows  = $db->query("SELECT m.*,u.name,u.avatar FROM room_messages m JOIN users u ON m.user_id=u.id WHERE m.room_id=$rid AND m.id>$after ORDER BY m.id ASC LIMIT 30");
        $out   = [];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)) {
            $reply = null;
            if ($r['reply_to']) {
                $rp = $db->query("SELECT m.*,u.name FROM room_messages m JOIN users u ON m.user_id=u.id WHERE m.id={$r['reply_to']}")->fetchArray(SQLITE3_ASSOC);
                if ($rp) $reply = ['user'=>htmlspecialchars($rp['name']),'text'=>mb_substr(htmlspecialchars($rp['content']),0,80)];
            }
            $out[] = ['id'=>$r['id'],'content'=>htmlspecialchars($r['content']),
                'user'=>htmlspecialchars($r['name']),'avatar'=>userAvatar($r,34),
                'time'=>timeAgo($r['created_at']),'mine'=>$r['user_id']==$uid,'reply'=>$reply];
        }
        echo json_encode(['ok'=>true,'messages'=>$out]); exit;
    }

    if ($action === 'delete_msg') {
        $mid = (int)($_POST['msg_id']??0);
        $own = $db->query("SELECT user_id FROM room_messages WHERE id=$mid")->fetchArray();
        if ($own && $own['user_id']==$uid) $db->exec("DELETE FROM room_messages WHERE id=$mid");
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'create_room') {
        $name = mb_substr(trim($_POST['name']??''),0,40);
        $desc = mb_substr(trim($_POST['desc']??''),0,120);
        $icon = trim($_POST['icon']??'💬');
        if (mb_strlen($name)<2) { echo json_encode(['ok'=>false,'msg'=>'Tên phòng quá ngắn']); exit; }
        $st = $db->prepare('INSERT INTO chat_rooms (name,description,icon,created_by,is_public) VALUES(:n,:d,:i,:u,1)');
        $st->bindValue(':n',$name);$st->bindValue(':d',$desc);
        $st->bindValue(':i',$icon);$st->bindValue(':u',$uid);
        $st->execute();
        $rid = $db->lastInsertRowID();
        echo json_encode(['ok'=>true,'id'=>$rid,'name'=>htmlspecialchars($name),'icon'=>htmlspecialchars($icon),'desc'=>htmlspecialchars($desc)]); exit;
    }

    echo json_encode(['ok'=>false]); exit;
}

// Load rooms
$rooms = [];
$rq = $db->query("SELECT r.*,(SELECT COUNT(*) FROM room_messages WHERE room_id=r.id) as msg_count,
    (SELECT content FROM room_messages WHERE room_id=r.id ORDER BY created_at DESC LIMIT 1) as last_msg
    FROM chat_rooms r WHERE r.is_public=1 ORDER BY r.id ASC");
while($r=$rq->fetchArray(SQLITE3_ASSOC)) $rooms[] = $r;

$activeRid = (int)($_GET['room']??($rooms[0]['id']??1));
$activeRoom = null;
foreach($rooms as $r) if($r['id']==$activeRid) { $activeRoom=$r; break; }

// Load initial messages
$msgs = [];
$mq = $db->query("SELECT m.*,u.name,u.avatar FROM room_messages m JOIN users u ON m.user_id=u.id WHERE m.room_id=$activeRid ORDER BY m.id DESC LIMIT 50");
while($m=$mq->fetchArray(SQLITE3_ASSOC)) $msgs[]=$m;
$msgs = array_reverse($msgs);
$lastId = empty($msgs) ? 0 : (int)end($msgs)['id'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($activeRoom['name']??'Phòng chat')?> — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.rooms-layout{display:grid;grid-template-columns:260px 1fr;height:calc(100vh - 120px);min-height:500px;
  border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--surface);}

/* Sidebar */
.rooms-sidebar{background:var(--surface2);border-right:1px solid var(--border);
  display:flex;flex-direction:column;overflow:hidden;}
.sidebar-header{padding:14px 14px 10px;border-bottom:1px solid var(--border);flex-shrink:0;}
.sidebar-title{font-size:13px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;}
.rooms-list{flex:1;overflow-y:auto;padding:6px;}
.room-item{display:flex;align-items:center;gap:10px;padding:10px 10px;border-radius:10px;
  cursor:pointer;transition:background .12s;margin-bottom:2px;}
.room-item:hover{background:var(--surface);}
.room-item.active{background:var(--accent);color:#fff;}
.room-icon{font-size:18px;flex-shrink:0;width:30px;text-align:center;}
.room-info{flex:1;min-width:0;}
.room-name{font-size:13px;font-weight:700;color:inherit;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.room-item.active .room-name{color:#fff;}
.room-preview{font-size:10px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
.room-item.active .room-preview{color:rgba(255,255,255,.7);}
.room-cnt{font-size:9px;font-weight:800;color:var(--muted);flex-shrink:0;}
.room-item.active .room-cnt{color:rgba(255,255,255,.7);}

.sidebar-footer{padding:10px;border-top:1px solid var(--border);flex-shrink:0;}
.new-room-btn{width:100%;padding:9px;border-radius:10px;border:1.5px dashed var(--border);
  background:transparent;color:var(--muted);cursor:pointer;font-family:var(--font);font-weight:700;
  font-size:12px;transition:all .15s;}
.new-room-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}

/* Chat main */
.chat-main{display:flex;flex-direction:column;overflow:hidden;}
.chat-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.chat-room-icon{font-size:22px;}
.chat-room-name{font-size:15px;font-weight:800;color:var(--text);}
.chat-room-desc{font-size:11px;color:var(--muted);margin-top:1px;}
.online-dot{width:8px;height:8px;border-radius:50%;background:var(--green);flex-shrink:0;
  box-shadow:0 0 6px var(--green);}

.messages{flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:2px;}
.msg-group{margin-bottom:10px;}
.msg-row{display:flex;gap:8px;align-items:flex-end;margin-bottom:2px;}
.msg-row.mine{flex-direction:row-reverse;}
.msg-bubble{max-width:70%;padding:9px 13px;border-radius:16px;font-size:13px;line-height:1.5;
  word-break:break-word;position:relative;cursor:pointer;}
.msg-bubble.theirs{background:var(--surface2);border-radius:16px 16px 16px 4px;color:var(--text);}
.msg-bubble.mine{background:var(--accent);color:#fff;border-radius:16px 16px 4px 16px;}
.msg-bubble:hover .msg-actions{opacity:1;}
.msg-sender{font-size:10px;font-weight:800;color:var(--accent);margin-bottom:3px;}
.msg-row.mine .msg-sender{display:none;}
.msg-time{font-size:9px;color:var(--muted);padding:0 2px;flex-shrink:0;margin-bottom:4px;}
.msg-actions{position:absolute;top:-28px;right:0;display:flex;gap:4px;opacity:0;transition:opacity .15s;
  background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:3px 6px;
  box-shadow:var(--shadow);}
.msg-row.mine .msg-actions{right:auto;left:0;}
.msg-action-btn{background:none;border:none;cursor:pointer;font-size:13px;padding:1px 3px;border-radius:4px;}
.msg-action-btn:hover{background:var(--surface2);}
.reply-preview{background:rgba(0,0,0,.08);border-left:3px solid rgba(255,255,255,.5);
  border-radius:6px;padding:5px 8px;margin-bottom:6px;font-size:11px;opacity:.85;}
.msg-bubble.theirs .reply-preview{background:var(--surface);border-left-color:var(--accent);}

.reply-bar{padding:8px 16px;background:var(--accent-soft);border-top:1px solid var(--border);
  display:none;align-items:center;gap:10px;flex-shrink:0;}
.reply-bar.show{display:flex;}
.reply-bar-text{flex:1;font-size:12px;color:var(--text2);}
.reply-bar-close{background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;}

.chat-input-area{padding:12px 16px;border-top:1px solid var(--border);flex-shrink:0;}
.chat-input-row{display:flex;gap:8px;align-items:center;}
.chat-input{flex:1;padding:10px 16px;border:1.5px solid var(--border);border-radius:20px;
  font-family:var(--font);font-size:13px;background:var(--surface2);color:var(--text);
  outline:none;transition:border-color .15s;}
.chat-input:focus{border-color:var(--accent);background:var(--surface);}
.send-btn{width:40px;height:40px;border-radius:50%;background:var(--accent);border:none;
  color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .15s;flex-shrink:0;}
.send-btn:hover{background:var(--accent-hover);transform:scale(1.05);}
.send-btn:active{transform:scale(.95);}

/* New room modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;
  display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);display:none;}
.modal-overlay.show{display:flex;}
.modal-box{background:var(--surface);border-radius:16px;padding:24px;width:380px;max-width:90vw;
  box-shadow:0 24px 48px rgba(0,0,0,.2);}
.modal-title{font-size:16px;font-weight:800;margin-bottom:16px;}
.icon-picker{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;}
.icon-opt{width:36px;height:36px;border-radius:8px;border:2px solid var(--border);
  background:var(--surface2);cursor:pointer;font-size:18px;display:flex;align-items:center;
  justify-content:center;transition:all .15s;}
.icon-opt:hover,.icon-opt.sel{border-color:var(--accent);background:var(--accent-soft);}

/* Scrollbar */
.messages::-webkit-scrollbar,.rooms-list::-webkit-scrollbar{width:4px;}
.messages::-webkit-scrollbar-thumb,.rooms-list::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px;}

/* Date divider */
.date-divider{text-align:center;font-size:10px;font-weight:700;color:var(--muted);margin:10px 0;
  display:flex;align-items:center;gap:8px;}
.date-divider::before,.date-divider::after{content:'';flex:1;height:1px;background:var(--border);}

@media(max-width:640px){
  .rooms-layout{grid-template-columns:1fr;height:auto;}
  .rooms-sidebar{height:auto;border-right:none;border-bottom:1px solid var(--border);}
  .rooms-list{max-height:160px;}
  .chat-main{height:calc(100vh - 320px);min-height:300px;}
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Cộng đồng</div>
    <h1 class="page-title">💬 Phòng Chat</h1>
    <div class="page-sub">Chat theo nhóm · Hỏi đáp bài tập · Trò chuyện thoải mái</div>
  </div>

  <div class="rooms-layout">

    <!-- SIDEBAR -->
    <div class="rooms-sidebar">
      <div class="sidebar-header">
        <div class="sidebar-title">📡 Phòng Chat</div>
      </div>
      <div class="rooms-list" id="roomsList">
        <?php foreach($rooms as $r): ?>
        <div class="room-item <?=$r['id']==$activeRid?'active':''?>"
             id="ri-<?=$r['id']?>" onclick="switchRoom(<?=$r['id']?>,'<?=htmlspecialchars($r['icon'])?> <?=htmlspecialchars($r['name'])?>','<?=htmlspecialchars($r['description']??'')?>')">
          <div class="room-icon"><?=htmlspecialchars($r['icon'])?></div>
          <div class="room-info">
            <div class="room-name"><?=htmlspecialchars($r['name'])?></div>
            <div class="room-preview"><?=htmlspecialchars(mb_substr($r['last_msg']??'Chưa có tin nhắn',0,30))?></div>
          </div>
          <div class="room-cnt"><?=$r['msg_count']?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sidebar-footer">
        <button class="new-room-btn" onclick="document.getElementById('newRoomModal').classList.add('show')">+ Tạo phòng mới</button>
      </div>
    </div>

    <!-- CHAT MAIN -->
    <div class="chat-main">
      <div class="chat-header">
        <div class="chat-room-icon" id="chatIcon"><?=htmlspecialchars($activeRoom['icon']??'💬')?></div>
        <div style="flex:1;">
          <div class="chat-room-name" id="chatName"><?=htmlspecialchars($activeRoom['name']??'Phòng chat')?></div>
          <div class="chat-room-desc" id="chatDesc"><?=htmlspecialchars($activeRoom['description']??'')?></div>
        </div>
        <div class="online-dot" title="Online"></div>
        <span style="font-size:11px;color:var(--muted);" id="onlineCount"></span>
      </div>

      <div class="messages" id="msgList">
        <?php
        $prevDate='';
        foreach($msgs as $m):
          $date = date('d/m/Y',strtotime($m['created_at']));
          if($date!==$prevDate){echo '<div class="date-divider">'.$date.'</div>'; $prevDate=$date;}
          $mine = $m['user_id']==$uid;
          $av   = userAvatar($m,34);
          $side = $mine?'mine':'theirs';
          $replyHtml='';
          if(!empty($m['reply_to'])){
            $rp=$db->query("SELECT m.*,u.name FROM room_messages m JOIN users u ON m.user_id=u.id WHERE m.id={$m['reply_to']}")->fetchArray(SQLITE3_ASSOC);
            if($rp) $replyHtml='<div class="reply-preview">↩ <strong>'.htmlspecialchars($rp['name']).'</strong>: '.mb_substr(htmlspecialchars($rp['content']),0,80).'</div>';
          }
          $content=htmlspecialchars($m['content']);
          $time=timeAgo($m['created_at']);
          $mid=(int)$m['id'];
          $actions=$mine?'<button class="msg-action-btn" onclick="deleteMsg('.$mid.',event)" title="Xoá">🗑️</button>':'';
          $actions.='<button class="msg-action-btn" onclick="setReply('.$mid.',\''.addslashes(htmlspecialchars($m['name'])).'\',\''.addslashes(mb_substr(htmlspecialchars($m['content']),0,60)).'\')" title="Reply">↩</button>';
          echo <<<HTML
<div class="msg-row {$side}" id="m-{$mid}">
  {$av}
  <div>
    <div class="msg-sender">{$m['name']}</div>
    <div class="msg-bubble {$side}">
      {$replyHtml}{$content}
      <div class="msg-actions">{$actions}</div>
    </div>
  </div>
  <div class="msg-time">{$time}</div>
</div>
HTML;
        endforeach;
        if(empty($msgs)) echo '<div style="text-align:center;color:var(--muted);padding:2rem;font-size:13px;font-weight:600;">👋 Chưa có tin nhắn. Hãy bắt đầu cuộc trò chuyện!</div>';
        ?>
      </div>

      <!-- Reply bar -->
      <div class="reply-bar" id="replyBar">
        <div style="font-size:14px;">↩</div>
        <div class="reply-bar-text" id="replyBarText">Đang trả lời...</div>
        <button class="reply-bar-close" onclick="clearReply()">✕</button>
      </div>

      <!-- Input -->
      <div class="chat-input-area">
        <div class="chat-input-row">
          <?=userAvatar($user,36)?>
          <input class="chat-input" id="chatInput" placeholder="Nhập tin nhắn... (Enter để gửi)"
            onkeydown="inputKey(event)" maxlength="2000">
          <button class="send-btn" onclick="sendMsg()">➤</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- New Room Modal -->
<div class="modal-overlay" id="newRoomModal" onclick="if(event.target===this)this.classList.remove('show')">
  <div class="modal-box">
    <div class="modal-title">➕ Tạo phòng chat mới</div>
    <div style="margin-bottom:12px;">
      <label style="font-size:11px;font-weight:800;color:var(--muted);display:block;margin-bottom:5px;">ICON PHÒNG</label>
      <div class="icon-picker" id="iconPicker">
        <?php foreach(['💬','📚','🎮','🎵','🔬','🎨','⚽','🍜','💡','🌍','🎯','🤝'] as $ico): ?>
        <div class="icon-opt <?=$ico==='💬'?'sel':''?>" data-icon="<?=$ico?>" onclick="pickIcon('<?=$ico?>',this)"><?=$ico?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="margin-bottom:12px;">
      <label style="font-size:11px;font-weight:800;color:var(--muted);display:block;margin-bottom:5px;">TÊN PHÒNG *</label>
      <input type="text" id="newRoomName" class="form-input" placeholder="Ví dụ: Học Toán 12" maxlength="40">
    </div>
    <div style="margin-bottom:18px;">
      <label style="font-size:11px;font-weight:800;color:var(--muted);display:block;margin-bottom:5px;">MÔ TẢ</label>
      <input type="text" id="newRoomDesc" class="form-input" placeholder="Phòng dành cho..." maxlength="120">
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-primary" style="flex:1;" onclick="createRoom()">➕ Tạo phòng</button>
      <button class="btn btn-ghost" onclick="document.getElementById('newRoomModal').classList.remove('show')">Huỷ</button>
    </div>
  </div>
</div>

<script>
let currentRoom = <?=$activeRid?>;
let lastMsgId   = <?=$lastId?>;
let replyToId   = null;
let pollTimer   = null;

// Auto scroll to bottom on load
window.addEventListener('load', () => {
  const ml = document.getElementById('msgList');
  ml.scrollTop = ml.scrollHeight;
});

function inputKey(e) {
  if(e.key==='Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
}

async function sendMsg() {
  const input   = document.getElementById('chatInput');
  const content = input.value.trim();
  if(!content) return;
  input.value = '';
  const fd = new FormData();
  fd.append('action','send'); fd.append('room_id',currentRoom);
  fd.append('content',content);
  if(replyToId) fd.append('reply_to',replyToId);
  clearReply();
  const res  = await fetch('rooms.php',{method:'POST',body:fd});
  const data = await res.json();
  if(data.ok) {
    appendMsg(data.message);
    lastMsgId = data.message.id;
  }
}

function appendMsg(m) {
  const ml   = document.getElementById('msgList');
  const div  = document.createElement('div');
  div.className = 'msg-row '+(m.mine?'mine':'theirs');
  div.id = 'm-'+m.id;
  const replyHtml = m.reply ? `<div class="reply-preview">↩ <strong>${m.reply.user}</strong>: ${m.reply.text}</div>` : '';
  const actions = (m.mine ? `<button class="msg-action-btn" onclick="deleteMsg(${m.id},event)" title="Xoá">🗑️</button>` : '')
    + `<button class="msg-action-btn" onclick="setReply(${m.id},'${(m.user||'').replace(/'/g,"\\'")}','${(m.content||'').replace(/'/g,"\\'").slice(0,60)}')" title="Reply">↩</button>`;
  div.innerHTML = `${m.avatar}<div><div class="msg-sender">${m.user}</div><div class="msg-bubble ${m.mine?'mine':'theirs'}">${replyHtml}${m.content}<div class="msg-actions">${actions}</div></div></div><div class="msg-time">${m.time}</div>`;
  const atBottom = ml.scrollTop+ml.clientHeight >= ml.scrollHeight-60;
  ml.appendChild(div);
  if(atBottom || m.mine) ml.scrollTop = ml.scrollHeight;
}

// Long polling
async function poll() {
  try {
    const fd = new FormData();
    fd.append('action','poll'); fd.append('room_id',currentRoom); fd.append('after_id',lastMsgId);
    const res  = await fetch('rooms.php',{method:'POST',body:fd});
    const data = await res.json();
    if(data.ok && data.messages.length) {
      data.messages.forEach(m => {
        if(!document.getElementById('m-'+m.id)) appendMsg(m);
        lastMsgId = Math.max(lastMsgId, m.id);
      });
    }
  } catch(e) {}
  pollTimer = setTimeout(poll, 2500);
}
poll();

async function deleteMsg(mid, e) {
  e.stopPropagation();
  if(!confirm('Xoá tin nhắn này?')) return;
  const fd=new FormData(); fd.append('action','delete_msg'); fd.append('msg_id',mid);
  await fetch('rooms.php',{method:'POST',body:fd});
  document.getElementById('m-'+mid)?.remove();
}

function setReply(id, user, text) {
  replyToId = id;
  document.getElementById('replyBarText').innerHTML = `<strong>${user}</strong>: ${text}`;
  document.getElementById('replyBar').classList.add('show');
  document.getElementById('chatInput').focus();
}
function clearReply() {
  replyToId = null;
  document.getElementById('replyBar').classList.remove('show');
}

async function switchRoom(rid, nameWithIcon, desc) {
  clearTimeout(pollTimer);
  currentRoom = rid; lastMsgId = 0;
  // Update active
  document.querySelectorAll('.room-item').forEach(r=>{
    r.classList.toggle('active',r.id==='ri-'+rid);
  });
  // Update header
  const parts = nameWithIcon.split(' ');
  document.getElementById('chatIcon').textContent = parts[0];
  document.getElementById('chatName').textContent = parts.slice(1).join(' ');
  document.getElementById('chatDesc').textContent = desc;
  // Clear & reload messages
  const ml = document.getElementById('msgList');
  ml.innerHTML = '<div style="text-align:center;color:var(--muted);padding:2rem;">⏳ Đang tải...</div>';
  const fd=new FormData(); fd.append('action','poll'); fd.append('room_id',rid); fd.append('after_id',0);
  const res=await fetch('rooms.php',{method:'POST',body:fd});
  const data=await res.json();
  ml.innerHTML='';
  if(!data.messages.length) ml.innerHTML='<div style="text-align:center;color:var(--muted);padding:2rem;font-size:13px;font-weight:600;">👋 Chưa có tin nhắn. Bắt đầu chat thôi!</div>';
  else { data.messages.forEach(m=>{appendMsg(m); lastMsgId=Math.max(lastMsgId,m.id);}); }
  ml.scrollTop=ml.scrollHeight;
  poll();
}

// Create room
let selectedIcon = '💬';
function pickIcon(ico, el) {
  selectedIcon = ico;
  document.querySelectorAll('.icon-opt').forEach(e=>e.classList.remove('sel'));
  el.classList.add('sel');
}
async function createRoom() {
  const name = document.getElementById('newRoomName').value.trim();
  const desc = document.getElementById('newRoomDesc').value.trim();
  if(name.length<2){alert('Tên phòng phải có ít nhất 2 ký tự!');return;}
  const fd=new FormData();
  fd.append('action','create_room');fd.append('name',name);
  fd.append('desc',desc);fd.append('icon',selectedIcon);
  const res=await fetch('rooms.php',{method:'POST',body:fd});
  const data=await res.json();
  if(data.ok){
    document.getElementById('newRoomModal').classList.remove('show');
    const list=document.getElementById('roomsList');
    const div=document.createElement('div');
    div.className='room-item'; div.id='ri-'+data.id;
    div.onclick=()=>switchRoom(data.id,data.icon+' '+data.name,data.desc);
    div.innerHTML=`<div class="room-icon">${data.icon}</div><div class="room-info"><div class="room-name">${data.name}</div><div class="room-preview">Vừa tạo</div></div><div class="room-cnt">0</div>`;
    list.appendChild(div);
    switchRoom(data.id,data.icon+' '+data.name,data.desc);
    document.getElementById('newRoomName').value='';
    document.getElementById('newRoomDesc').value='';
  } else {alert(data.msg||'Lỗi!');}
}
</script>
</body>
</html>
