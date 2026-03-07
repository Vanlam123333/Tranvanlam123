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
        $msgType = $_POST['msg_type'] ?? 'text';

        $fileData = null;
        $fileName = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {
            $tmp      = $_FILES['attachment']['tmp_name'];
            $origName = basename($_FILES['attachment']['name']);
            $mime     = mime_content_type($tmp);
            $b64      = base64_encode(file_get_contents($tmp));
            $fileData = 'data:'.$mime.';base64,'.$b64;
            $fileName = $origName;
            if (!$content) $content = $origName;
        }

        if (!$content && !$fileData) { echo json_encode(['ok'=>false]); exit; }
        if (!$rid) { echo json_encode(['ok'=>false]); exit; }

        @$db->exec("ALTER TABLE room_messages ADD COLUMN msg_type TEXT DEFAULT 'text'");
        @$db->exec("ALTER TABLE room_messages ADD COLUMN file_data TEXT");
        @$db->exec("ALTER TABLE room_messages ADD COLUMN file_name TEXT");

        $st = $db->prepare('INSERT INTO room_messages (room_id,user_id,content,reply_to,msg_type,file_data,file_name) VALUES(:r,:u,:c,:rt,:mt,:fd,:fn)');
        $st->bindValue(':r',$rid); $st->bindValue(':u',$uid);
        $st->bindValue(':c',$content); $st->bindValue(':rt',$replyTo);
        $st->bindValue(':mt',$msgType); $st->bindValue(':fd',$fileData);
        $st->bindValue(':fn',$fileName);
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
            'msg_type'=>$msg['msg_type']??'text',
            'file_data'=>$msg['file_data']??null,
            'file_name'=>htmlspecialchars($msg['file_name']??''),
        ]]); exit;
    }

    if ($action === 'poll') {
        $rid   = (int)($_POST['room_id']??0);
        $after = (int)($_POST['after_id']??0);
        @$db->exec("ALTER TABLE room_messages ADD COLUMN msg_type TEXT DEFAULT 'text'");
        @$db->exec("ALTER TABLE room_messages ADD COLUMN file_data TEXT");
        @$db->exec("ALTER TABLE room_messages ADD COLUMN file_name TEXT");
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
                'time'=>timeAgo($r['created_at']),'mine'=>$r['user_id']==$uid,'reply'=>$reply,
                'msg_type'=>$r['msg_type']??'text',
                'file_data'=>$r['file_data']??null,
                'file_name'=>htmlspecialchars($r['file_name']??''),
            ];
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

@$db->exec("ALTER TABLE room_messages ADD COLUMN msg_type TEXT DEFAULT 'text'");
@$db->exec("ALTER TABLE room_messages ADD COLUMN file_data TEXT");
@$db->exec("ALTER TABLE room_messages ADD COLUMN file_name TEXT");

$rooms = [];
$rq = $db->query("SELECT r.*,(SELECT COUNT(*) FROM room_messages WHERE room_id=r.id) as msg_count,
    (SELECT content FROM room_messages WHERE room_id=r.id ORDER BY created_at DESC LIMIT 1) as last_msg
    FROM chat_rooms r WHERE r.is_public=1 ORDER BY r.id ASC");
while($r=$rq->fetchArray(SQLITE3_ASSOC)) $rooms[] = $r;

$activeRid = (int)($_GET['room']??($rooms[0]['id']??1));
$activeRoom = null;
foreach($rooms as $r) if($r['id']==$activeRid) { $activeRoom=$r; break; }

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

.rooms-sidebar{background:var(--surface2);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
.sidebar-header{padding:14px 14px 10px;border-bottom:1px solid var(--border);flex-shrink:0;}
.sidebar-title{font-size:13px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;}
.rooms-list{flex:1;overflow-y:auto;padding:6px;}
.room-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:10px;cursor:pointer;transition:background .12s;margin-bottom:2px;}
.room-item:hover{background:var(--surface);}
.room-item.active{background:var(--accent);color:#fff;}
.room-icon{font-size:18px;flex-shrink:0;width:30px;text-align:center;}
.room-info{flex:1;min-width:0;}
.room-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.room-item.active .room-name{color:#fff;}
.room-preview{font-size:10px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
.room-item.active .room-preview{color:rgba(255,255,255,.7);}
.room-cnt{font-size:9px;font-weight:800;color:var(--muted);}
.room-item.active .room-cnt{color:rgba(255,255,255,.7);}
.sidebar-footer{padding:10px;border-top:1px solid var(--border);flex-shrink:0;}
.new-room-btn{width:100%;padding:9px;border-radius:10px;border:1.5px dashed var(--border);
  background:transparent;color:var(--muted);cursor:pointer;font-family:var(--font);font-weight:700;font-size:12px;transition:all .15s;}
.new-room-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}

.chat-main{display:flex;flex-direction:column;overflow:hidden;}
.chat-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.chat-room-name{font-size:15px;font-weight:800;color:var(--text);}
.chat-room-desc{font-size:11px;color:var(--muted);margin-top:1px;}
.online-dot{width:8px;height:8px;border-radius:50%;background:var(--green);flex-shrink:0;box-shadow:0 0 6px var(--green);}

/* ── Fixed message layout ── */
.messages{flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:4px;}

.msg-row{display:flex;align-items:flex-end;gap:8px;width:100%;}
.msg-row.mine{flex-direction:row-reverse;}

.msg-avatar-wrap{flex-shrink:0;width:34px;align-self:flex-end;}
.msg-row.mine .msg-avatar-wrap{display:none;}

.msg-body{display:flex;flex-direction:column;max-width:65%;}
.msg-row.mine .msg-body{align-items:flex-end;}
.msg-row.theirs .msg-body{align-items:flex-start;}

.msg-sender{font-size:10px;font-weight:800;color:var(--accent);margin-bottom:3px;padding:0 2px;}
.msg-row.mine .msg-sender{display:none;}

.msg-bubble{position:relative;padding:9px 13px;border-radius:16px;font-size:13px;line-height:1.55;
  word-break:break-word;cursor:pointer;display:inline-block;max-width:100%;}
.msg-bubble.theirs{background:var(--surface2);border-radius:4px 16px 16px 16px;color:var(--text);}
.msg-bubble.mine{background:var(--accent);color:#fff;border-radius:16px 4px 16px 16px;}
.msg-bubble:hover .msg-actions{opacity:1;pointer-events:all;}

.msg-meta{margin-top:3px;padding:0 2px;}
.msg-time{font-size:9px;color:var(--muted);}

.msg-actions{position:absolute;top:-32px;right:4px;display:flex;gap:3px;opacity:0;pointer-events:none;
  transition:opacity .15s;background:var(--surface);border:1px solid var(--border);
  border-radius:8px;padding:3px 6px;box-shadow:var(--shadow);z-index:10;white-space:nowrap;}
.msg-row.mine .msg-bubble .msg-actions{right:auto;left:4px;}
.msg-action-btn{background:none;border:none;cursor:pointer;font-size:13px;padding:1px 4px;border-radius:4px;}
.msg-action-btn:hover{background:var(--surface2);}

.reply-preview{background:rgba(0,0,0,.08);border-left:3px solid rgba(255,255,255,.5);
  border-radius:6px;padding:5px 8px;margin-bottom:6px;font-size:11px;opacity:.85;}
.msg-bubble.theirs .reply-preview{background:var(--surface);border-left-color:var(--accent);}

/* Image messages */
.msg-image{max-width:220px;max-height:180px;border-radius:10px;display:block;cursor:pointer;}

/* File messages */
.msg-file{display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(0,0,0,.1);
  border-radius:10px;text-decoration:none;color:inherit;font-size:12px;font-weight:600;min-width:150px;}
.msg-file-icon{font-size:20px;}
.msg-bubble.mine .msg-file{background:rgba(255,255,255,.2);color:#fff;}

/* Voice messages */
.voice-msg{display:flex;align-items:center;gap:8px;min-width:180px;}
.voice-play-btn{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.25);
  border:none;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;color:#fff;}
.msg-bubble.theirs .voice-play-btn{background:var(--accent);color:#fff;}
.voice-play-btn:hover{opacity:.8;}
.voice-waveform{flex:1;height:26px;border-radius:13px;background:rgba(255,255,255,.15);
  display:flex;align-items:center;padding:0 6px;gap:2px;overflow:hidden;}
.msg-bubble.theirs .voice-waveform{background:var(--surface);}
.wave-bar{width:3px;border-radius:2px;background:rgba(255,255,255,.7);flex-shrink:0;}
.msg-bubble.theirs .wave-bar{background:var(--accent);}

/* Reply bar */
.reply-bar{padding:8px 16px;background:var(--accent-soft);border-top:1px solid var(--border);
  display:none;align-items:center;gap:10px;flex-shrink:0;}
.reply-bar.show{display:flex;}
.reply-bar-text{flex:1;font-size:12px;color:var(--text2);}
.reply-bar-close{background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;}

/* Attach preview */
.attach-preview{display:none;align-items:center;gap:8px;padding:6px 14px;background:var(--accent-soft);
  font-size:12px;border-top:1px solid var(--border);}
.attach-preview.show{display:flex;}
.attach-preview-name{flex:1;font-weight:600;color:var(--text);overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
.attach-preview-remove{background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;}

/* Input area */
.chat-input-area{padding:10px 14px 12px;border-top:1px solid var(--border);flex-shrink:0;}
.chat-input-row{display:flex;gap:7px;align-items:flex-end;}
.chat-input-wrap{flex:1;display:flex;align-items:center;border:1.5px solid var(--border);
  border-radius:22px;background:var(--surface2);transition:border-color .15s;padding:4px 10px;gap:2px;}
.chat-input-wrap:focus-within{border-color:var(--accent);background:var(--surface);}
.chat-input{flex:1;border:none;background:transparent;outline:none;font-family:var(--font);
  font-size:13px;color:var(--text);padding:6px 6px;resize:none;min-height:36px;max-height:120px;line-height:1.5;}
.attach-btn{background:none;border:none;cursor:pointer;font-size:16px;padding:3px 4px;
  color:var(--muted);border-radius:6px;transition:color .15s;flex-shrink:0;line-height:1;}
.attach-btn:hover{color:var(--accent);}
.send-btn{width:40px;height:40px;border-radius:50%;background:var(--accent);border:none;
  color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .15s;flex-shrink:0;}
.send-btn:hover{background:var(--accent-hover);transform:scale(1.05);}
.send-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.voice-btn{width:40px;height:40px;border-radius:50%;background:var(--surface2);border:1.5px solid var(--border);
  color:var(--muted);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .15s;flex-shrink:0;}
.voice-btn:hover{border-color:var(--accent);color:var(--accent);}
.voice-btn.recording{background:#ef4444;border-color:#ef4444;color:#fff;animation:pulse-rec 1s infinite;}
@keyframes pulse-rec{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4);}50%{box-shadow:0 0 0 8px rgba(239,68,68,0);}}
.rec-status{font-size:10px;color:var(--muted);margin-top:4px;padding-left:48px;min-height:14px;}

/* Lightbox */
.lightbox{position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:200;
  display:none;align-items:center;justify-content:center;cursor:pointer;}
.lightbox.show{display:flex;}
.lightbox img{max-width:90vw;max-height:90vh;border-radius:12px;}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;
  display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);display:none;}
.modal-overlay.show{display:flex;}
.modal-box{background:var(--surface);border-radius:16px;padding:24px;width:380px;max-width:90vw;box-shadow:0 24px 48px rgba(0,0,0,.2);}
.modal-title{font-size:16px;font-weight:800;margin-bottom:16px;}
.icon-picker{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;}
.icon-opt{width:36px;height:36px;border-radius:8px;border:2px solid var(--border);
  background:var(--surface2);cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:all .15s;}
.icon-opt:hover,.icon-opt.sel{border-color:var(--accent);background:var(--accent-soft);}

.date-divider{text-align:center;font-size:10px;font-weight:700;color:var(--muted);margin:10px 0;
  display:flex;align-items:center;gap:8px;}
.date-divider::before,.date-divider::after{content:'';flex:1;height:1px;background:var(--border);}

.messages::-webkit-scrollbar,.rooms-list::-webkit-scrollbar{width:4px;}
.messages::-webkit-scrollbar-thumb,.rooms-list::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px;}

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
        <div style="font-size:22px;" id="chatIcon"><?=htmlspecialchars($activeRoom['icon']??'💬')?></div>
        <div style="flex:1;">
          <div class="chat-room-name" id="chatName"><?=htmlspecialchars($activeRoom['name']??'Phòng chat')?></div>
          <div class="chat-room-desc" id="chatDesc"><?=htmlspecialchars($activeRoom['description']??'')?></div>
        </div>
        <div class="online-dot" title="Online"></div>
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

          $msgType  = $m['msg_type'] ?? 'text';
          $fileData = $m['file_data'] ?? '';
          $fileName = htmlspecialchars($m['file_name'] ?? '');
          $content  = htmlspecialchars($m['content']);
          $time     = timeAgo($m['created_at']);
          $mid      = (int)$m['id'];

          $actions = ($mine?'<button class="msg-action-btn" onclick="deleteMsg('.$mid.',event)" title="Xoá">🗑️</button>':'')
            .'<button class="msg-action-btn" onclick="setReply('.$mid.',\''.addslashes(htmlspecialchars($m['name'])).'\',\''.addslashes(mb_substr(htmlspecialchars($m['content']),0,60)).'\')" title="Reply">↩</button>';

          if($msgType==='image' && $fileData) {
            $safeData = htmlspecialchars($fileData);
            $innerHtml = $replyHtml.'<img src="'.$safeData.'" class="msg-image" onclick="openLightbox(this.src)" alt="'.$fileName.'">';
            if($content && $content!==$fileName) $innerHtml.='<div style="margin-top:4px;">'.$content.'</div>';
          } elseif($msgType==='voice' && $fileData) {
            $safeJs = addslashes($fileData);
            $innerHtml = $replyHtml.'<div class="voice-msg"><button class="voice-play-btn" onclick="playVoice(this,\''. $safeJs .'\')">▶</button><div class="voice-waveform"></div></div>';
          } elseif($msgType==='file' && $fileData) {
            $ext  = strtolower(pathinfo($m['file_name']??'',PATHINFO_EXTENSION));
            $icon = in_array($ext,['pdf'])?'📄':(in_array($ext,['zip','rar','7z'])?'🗜️':(in_array($ext,['doc','docx'])?'📝':'📎'));
            $safeData = htmlspecialchars($fileData);
            $innerHtml = $replyHtml.'<a href="'.$safeData.'" download="'.$fileName.'" class="msg-file">'
              .'<span class="msg-file-icon">'.$icon.'</span><span>'.($fileName?:$content).'</span></a>';
          } else {
            $innerHtml = $replyHtml.$content;
          }

          $avatarHtml = $mine ? '' : '<div class="msg-avatar-wrap">'.$av.'</div>';
          echo <<<HTML
<div class="msg-row {$side}" id="m-{$mid}">
  {$avatarHtml}
  <div class="msg-body">
    <div class="msg-sender">{$m['name']}</div>
    <div class="msg-bubble {$side}">
      {$innerHtml}
      <div class="msg-actions">{$actions}</div>
    </div>
    <div class="msg-meta"><span class="msg-time">{$time}</span></div>
  </div>
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

      <!-- Attach preview -->
      <div class="attach-preview" id="attachPreview">
        <span id="attachPreviewIcon" style="font-size:18px;">📎</span>
        <span class="attach-preview-name" id="attachPreviewName"></span>
        <button class="attach-preview-remove" onclick="clearAttach()">✕</button>
      </div>

      <!-- Input area -->
      <div class="chat-input-area">
        <div class="chat-input-row">
          <?=userAvatar($user,36)?>
          <div class="chat-input-wrap">
            <button class="attach-btn" title="Gửi ảnh" onclick="document.getElementById('imgInput').click()">🖼️</button>
            <input type="file" id="imgInput" accept="image/*" style="display:none" onchange="onFileChosen(this,'image')">
            <button class="attach-btn" title="Gửi file" onclick="document.getElementById('fileInput').click()">📎</button>
            <input type="file" id="fileInput" style="display:none" onchange="onFileChosen(this,'file')">
            <textarea class="chat-input" id="chatInput" placeholder="Nhập tin nhắn... (Enter để gửi)"
              onkeydown="inputKey(event)" rows="1" maxlength="4000"></textarea>
          </div>
          <button class="voice-btn" id="voiceBtn" title="Ghi âm" onclick="toggleRecording()">🎤</button>
          <button class="send-btn" id="sendBtn" onclick="sendMsg()">➤</button>
        </div>
        <div class="rec-status" id="recStatus"></div>
      </div>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="this.classList.remove('show')">
  <img id="lightboxImg" src="" alt="">
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
let pendingFile = null;
let mediaRecorder = null;
let audioChunks   = [];
let isRecording   = false;

window.addEventListener('load', () => {
  const ml = document.getElementById('msgList');
  ml.scrollTop = ml.scrollHeight;
  initAllWaveforms();
});

/* Textarea auto-resize */
document.getElementById('chatInput').addEventListener('input', function(){
  this.style.height='auto';
  this.style.height=Math.min(this.scrollHeight,120)+'px';
});

function inputKey(e) {
  if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendMsg(); }
}

/* File chooser */
function onFileChosen(input, type) {
  const f = input.files[0];
  if(!f) return;
  pendingFile = {file:f, type};
  document.getElementById('attachPreviewIcon').textContent = type==='image'?'🖼️':'📎';
  document.getElementById('attachPreviewName').textContent = f.name+' ('+(f.size/1024).toFixed(1)+' KB)';
  document.getElementById('attachPreview').classList.add('show');
  input.value='';
}
function clearAttach(){
  pendingFile=null;
  document.getElementById('attachPreview').classList.remove('show');
}

/* Send */
async function sendMsg(){
  const input   = document.getElementById('chatInput');
  const content = input.value.trim();
  if(!content && !pendingFile) return;

  const fd=new FormData();
  fd.append('action','send');
  fd.append('room_id',currentRoom);
  fd.append('content', content||(pendingFile?.file.name??''));
  if(replyToId) fd.append('reply_to',replyToId);
  if(pendingFile){ fd.append('attachment',pendingFile.file); fd.append('msg_type',pendingFile.type); }
  else fd.append('msg_type','text');

  input.value=''; input.style.height='auto';
  clearReply(); clearAttach();
  document.getElementById('sendBtn').disabled=true;
  try{
    const res=await fetch('rooms.php',{method:'POST',body:fd});
    const data=await res.json();
    if(data.ok){ appendMsg(data.message); lastMsgId=data.message.id; }
  }catch(e){console.error(e);}
  document.getElementById('sendBtn').disabled=false;
}

/* Render message */
function appendMsg(m){
  const ml=document.getElementById('msgList');
  const div=document.createElement('div');
  div.className='msg-row '+(m.mine?'mine':'theirs');
  div.id='m-'+m.id;

  const replyHtml = m.reply
    ?`<div class="reply-preview">↩ <strong>${m.reply.user}</strong>: ${m.reply.text}</div>`:'';
  const actions=(m.mine
    ?`<button class="msg-action-btn" onclick="deleteMsg(${m.id},event)" title="Xoá">🗑️</button>`:'')
    +`<button class="msg-action-btn" onclick="setReply(${m.id},'${(m.user||'').replace(/'/g,"\\'")}','${(m.content||'').replace(/'/g,"\\'").slice(0,60)}')" title="Reply">↩</button>`;

  const t=m.msg_type||'text';
  let inner='';
  if(t==='image'&&m.file_data){
    inner=replyHtml+`<img src="${m.file_data}" class="msg-image" onclick="openLightbox(this.src)" alt="">`;
    if(m.content&&m.content!==m.file_name) inner+=`<div style="margin-top:4px;">${m.content}</div>`;
  } else if(t==='voice'&&m.file_data){
    const sd=m.file_data.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    inner=replyHtml+`<div class="voice-msg"><button class="voice-play-btn" onclick="playVoice(this,'${sd}')">▶</button><div class="voice-waveform" id="wf-${m.id}"></div></div>`;
  } else if(t==='file'&&m.file_data){
    const ext=(m.file_name||'').split('.').pop().toLowerCase();
    const ico=['pdf'].includes(ext)?'📄':['zip','rar','7z'].includes(ext)?'🗜️':['doc','docx'].includes(ext)?'📝':'📎';
    inner=replyHtml+`<a href="${m.file_data}" download="${m.file_name||'file'}" class="msg-file"><span class="msg-file-icon">${ico}</span><span>${m.file_name||m.content}</span></a>`;
  } else {
    inner=replyHtml+(m.content||'');
  }

  const avatarHtml=m.mine?'':`<div class="msg-avatar-wrap">${m.avatar}</div>`;
  div.innerHTML=`${avatarHtml}<div class="msg-body">
    <div class="msg-sender">${m.user}</div>
    <div class="msg-bubble ${m.mine?'mine':'theirs'}">${inner}<div class="msg-actions">${actions}</div></div>
    <div class="msg-meta"><span class="msg-time">${m.time}</span></div>
  </div>`;

  const atBottom=ml.scrollTop+ml.clientHeight>=ml.scrollHeight-60;
  ml.appendChild(div);
  if(t==='voice'){
    const wf=div.querySelector('.voice-waveform');
    if(wf) initWaveformEl(wf);
  }
  if(atBottom||m.mine) ml.scrollTop=ml.scrollHeight;
}

/* Waveform */
function initAllWaveforms(){ document.querySelectorAll('.voice-waveform').forEach(initWaveformEl); }
function initWaveformEl(el){
  if(!el||el.children.length>0) return;
  [3,6,10,14,18,12,8,16,11,7,15,9,13,5,10,12,8,14,6,10].forEach(h=>{
    const b=document.createElement('div');
    b.className='wave-bar'; b.style.height=h+'px'; el.appendChild(b);
  });
}

/* Voice playback */
function playVoice(btn,dataUrl){
  const audio=new Audio(dataUrl);
  btn.textContent='⏸';
  audio.play();
  audio.onended=()=>btn.textContent='▶';
  audio.onerror=()=>{btn.textContent='▶';};
}

/* Voice recording */
async function toggleRecording(){
  if(!isRecording){
    try{
      const stream=await navigator.mediaDevices.getUserMedia({audio:true});
      mediaRecorder=new MediaRecorder(stream);
      audioChunks=[];
      mediaRecorder.ondataavailable=e=>{if(e.data.size>0)audioChunks.push(e.data);};
      mediaRecorder.onstop=async()=>{
        const blob=new Blob(audioChunks,{type:'audio/webm'});
        const file=new File([blob],'voice-message.webm',{type:'audio/webm'});
        stream.getTracks().forEach(t=>t.stop());
        const fd=new FormData();
        fd.append('action','send'); fd.append('room_id',currentRoom);
        fd.append('content','🎤 Tin nhắn thoại'); fd.append('msg_type','voice');
        fd.append('attachment',file);
        if(replyToId) fd.append('reply_to',replyToId);
        clearReply();
        document.getElementById('sendBtn').disabled=true;
        document.getElementById('recStatus').textContent='⏳ Đang gửi...';
        try{
          const res=await fetch('rooms.php',{method:'POST',body:fd});
          const data=await res.json();
          if(data.ok){appendMsg(data.message); lastMsgId=data.message.id;}
        }catch(e){console.error(e);}
        document.getElementById('sendBtn').disabled=false;
        document.getElementById('recStatus').textContent='';
      };
      mediaRecorder.start();
      isRecording=true;
      document.getElementById('voiceBtn').classList.add('recording');
      document.getElementById('voiceBtn').textContent='⏹';
      let sec=0;
      window._recTimer=setInterval(()=>{
        sec++;
        document.getElementById('recStatus').textContent='🔴 Đang ghi âm '+sec+'s — bấm ⏹ để gửi';
      },1000);
    }catch(e){alert('Không thể truy cập microphone. Vui lòng cấp quyền mic.');}
  } else {
    clearInterval(window._recTimer);
    mediaRecorder.stop();
    isRecording=false;
    document.getElementById('voiceBtn').classList.remove('recording');
    document.getElementById('voiceBtn').textContent='🎤';
  }
}

/* Poll */
async function poll(){
  try{
    const fd=new FormData();
    fd.append('action','poll'); fd.append('room_id',currentRoom); fd.append('after_id',lastMsgId);
    const res=await fetch('rooms.php',{method:'POST',body:fd});
    const data=await res.json();
    if(data.ok&&data.messages.length){
      data.messages.forEach(m=>{
        if(!document.getElementById('m-'+m.id)) appendMsg(m);
        lastMsgId=Math.max(lastMsgId,m.id);
      });
    }
  }catch(e){}
  pollTimer=setTimeout(poll,2500);
}
poll();

/* Delete */
async function deleteMsg(mid,e){
  e.stopPropagation();
  if(!confirm('Xoá tin nhắn này?')) return;
  const fd=new FormData(); fd.append('action','delete_msg'); fd.append('msg_id',mid);
  await fetch('rooms.php',{method:'POST',body:fd});
  document.getElementById('m-'+mid)?.remove();
}

/* Reply */
function setReply(id,user,text){
  replyToId=id;
  document.getElementById('replyBarText').innerHTML=`<strong>${user}</strong>: ${text}`;
  document.getElementById('replyBar').classList.add('show');
  document.getElementById('chatInput').focus();
}
function clearReply(){
  replyToId=null;
  document.getElementById('replyBar').classList.remove('show');
}

/* Switch room */
async function switchRoom(rid,nameWithIcon,desc){
  clearTimeout(pollTimer);
  currentRoom=rid; lastMsgId=0;
  document.querySelectorAll('.room-item').forEach(r=>r.classList.toggle('active',r.id==='ri-'+rid));
  const parts=nameWithIcon.split(' ');
  document.getElementById('chatIcon').textContent=parts[0];
  document.getElementById('chatName').textContent=parts.slice(1).join(' ');
  document.getElementById('chatDesc').textContent=desc;
  const ml=document.getElementById('msgList');
  ml.innerHTML='<div style="text-align:center;color:var(--muted);padding:2rem;">⏳ Đang tải...</div>';
  const fd=new FormData(); fd.append('action','poll'); fd.append('room_id',rid); fd.append('after_id',0);
  const res=await fetch('rooms.php',{method:'POST',body:fd});
  const data=await res.json();
  ml.innerHTML='';
  if(!data.messages.length) ml.innerHTML='<div style="text-align:center;color:var(--muted);padding:2rem;font-size:13px;font-weight:600;">👋 Bắt đầu chat thôi!</div>';
  else{ data.messages.forEach(m=>{appendMsg(m); lastMsgId=Math.max(lastMsgId,m.id);}); }
  ml.scrollTop=ml.scrollHeight;
  poll();
}

/* Create room */
let selectedIcon='💬';
function pickIcon(ico,el){
  selectedIcon=ico;
  document.querySelectorAll('.icon-opt').forEach(e=>e.classList.remove('sel'));
  el.classList.add('sel');
}
async function createRoom(){
  const name=document.getElementById('newRoomName').value.trim();
  const desc=document.getElementById('newRoomDesc').value.trim();
  if(name.length<2){alert('Tên phòng phải có ít nhất 2 ký tự!');return;}
  const fd=new FormData();
  fd.append('action','create_room'); fd.append('name',name);
  fd.append('desc',desc); fd.append('icon',selectedIcon);
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
  }else{alert(data.msg||'Lỗi!');}
}

/* Lightbox */
function openLightbox(src){
  document.getElementById('lightboxImg').src=src;
  document.getElementById('lightbox').classList.add('show');
}
</script>
</body>
</html>
