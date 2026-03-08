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
/* ══════════════════════════════════════════════════════
   MESSENGER — FULL REWRITE
══════════════════════════════════════════════════════ */

/* ── LAYOUT ── */
.rooms-page { padding:0 !important; }
.rooms-layout {
  display:grid;
  grid-template-columns:360px 1fr;
  height:calc(100vh - 60px);
  border:none;border-radius:0;
  overflow:hidden;
  background:var(--bg);
}
.page-header { display:none !important; }

/* ══════════════════════════════
   LEFT SIDEBAR — FB Messenger style
══════════════════════════════ */
.rooms-sidebar {
  background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;overflow:hidden;
}
.sidebar-header {
  padding:16px 16px 8px;flex-shrink:0;
}
.sidebar-title {
  font-size:22px;font-weight:800;color:var(--text);
  letter-spacing:-.5px;margin-bottom:12px;
}
.sidebar-search {
  display:flex;align-items:center;gap:8px;
  background:var(--surface2);border-radius:20px;
  padding:8px 14px;
}
.sidebar-search svg{width:14px;height:14px;stroke:var(--muted);fill:none;stroke-width:2;flex-shrink:0;}
.sidebar-search input{border:none;background:transparent;outline:none;font-family:var(--font);font-size:14px;color:var(--text);flex:1;}
.sidebar-search input::placeholder{color:var(--muted);}

/* Tab bar like Messenger */
.sidebar-tabs {
  display:flex;padding:0 8px;border-bottom:1px solid var(--border);flex-shrink:0;
}
.sidebar-tab {
  flex:1;padding:10px 0;font-size:13px;font-weight:600;color:var(--muted);
  background:none;border:none;cursor:pointer;border-bottom:3px solid transparent;
  transition:all .15s;
}
.sidebar-tab.active {color:var(--accent);border-bottom-color:var(--accent);}
.sidebar-tab:hover:not(.active){color:var(--text);}

.rooms-list{flex:1;overflow-y:auto;padding:6px 0;}
.room-item{
  display:flex;align-items:center;gap:12px;
  padding:8px 16px;cursor:pointer;transition:background .1s;
  border-radius:0;position:relative;
}
.room-item:hover{background:var(--surface2);}
.room-item.active{background:rgba(99,102,241,.1);}

.room-avatar{
  width:52px;height:52px;border-radius:50%;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  flex-shrink:0;display:flex;align-items:center;justify-content:center;position:relative;
}
.room-avatar svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:1.8;stroke-linecap:round;}
.room-online{
  position:absolute;bottom:2px;right:2px;
  width:14px;height:14px;border-radius:50%;
  background:#22c55e;border:2.5px solid var(--surface);
}
.room-info{flex:1;min-width:0;}
.room-name{
  font-size:15px;font-weight:600;color:var(--text);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.room-item.active .room-name{font-weight:700;}
.room-preview{
  font-size:13px;color:var(--muted);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;
}
.room-item.active .room-preview{color:var(--accent);font-weight:600;}
.room-meta-right{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;}
.room-time{font-size:11px;color:var(--muted);}
.room-badge{
  min-width:20px;height:20px;border-radius:10px;
  background:var(--accent);font-size:11px;font-weight:700;
  color:#fff;display:flex;align-items:center;justify-content:center;padding:0 5px;
}

.sidebar-footer{padding:12px 16px;border-top:1px solid var(--border);flex-shrink:0;}
.new-room-btn{
  width:100%;padding:10px 14px;border-radius:10px;
  border:1.5px dashed var(--border);background:transparent;
  color:var(--muted);cursor:pointer;font-family:var(--font);
  font-weight:600;font-size:13px;transition:all .15s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.new-room-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.5;}
.new-room-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}

/* ══════════════════════════════
   RIGHT PANEL — Messenger right sidebar
══════════════════════════════ */
.rooms-layout-inner {
  display:grid;
  grid-template-columns:1fr 340px;
  height:100%;overflow:hidden;
}
.rooms-layout-inner.no-info {
  grid-template-columns:1fr 0;
}
.chat-main{
  display:flex;flex-direction:column;overflow:hidden;
  background:var(--bg);border-right:1px solid var(--border);
}

/* HEADER */
.chat-header{
  padding:10px 16px;background:var(--surface);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px;
  flex-shrink:0;min-height:60px;
}
.chat-header-avatar{
  width:40px;height:40px;border-radius:50%;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  flex-shrink:0;display:flex;align-items:center;justify-content:center;position:relative;
}
.chat-header-avatar svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:1.8;stroke-linecap:round;}
.chat-header-online{
  position:absolute;bottom:0;right:0;width:11px;height:11px;
  border-radius:50%;background:#22c55e;border:2px solid var(--surface);
}
.chat-room-name{font-size:15px;font-weight:700;color:var(--text);letter-spacing:-.2px;}
.chat-room-status{font-size:12px;color:#22c55e;margin-top:1px;font-weight:500;}
.chat-header-actions{margin-left:auto;display:flex;gap:2px;align-items:center;}
.chat-header-btn{
  width:36px;height:36px;border-radius:50%;border:none;
  background:transparent;cursor:pointer;color:var(--accent);
  display:flex;align-items:center;justify-content:center;transition:background .1s;
}
.chat-header-btn:hover{background:var(--accent-soft);}
.chat-header-btn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}
.chat-header-btn.active{background:var(--accent-soft);}

/* MESSAGES AREA */
.messages{
  flex:1;overflow-y:auto;
  padding:12px 16px;
  display:flex;flex-direction:column;gap:0;
  background:var(--bg);
}

.msg-row{display:flex;align-items:flex-end;gap:6px;width:100%;margin-bottom:2px;}
.msg-row.mine{flex-direction:row-reverse;}
.msg-avatar-wrap{flex-shrink:0;width:28px;margin-bottom:2px;align-self:flex-end;}
.msg-row.mine .msg-avatar-wrap{display:none;}
.msg-row.theirs.has-next .msg-avatar-wrap{visibility:hidden;}

.msg-body{display:flex;flex-direction:column;max-width:58%;}
.msg-row.mine .msg-body{align-items:flex-end;}
.msg-row.theirs .msg-body{align-items:flex-start;}

.msg-sender{font-size:11px;font-weight:600;color:var(--accent);margin-bottom:2px;padding:0 4px;}
.msg-row.mine .msg-sender{display:none;}
/* hide sender if consecutive */
.msg-row.theirs.has-prev .msg-sender{display:none;}

.msg-bubble{
  position:relative;padding:9px 14px;
  border-radius:18px;font-size:14px;line-height:1.45;
  word-break:break-word;cursor:pointer;
  display:inline-block;width:fit-content;max-width:100%;
}
.msg-bubble.theirs{
  background:var(--surface);color:var(--text);
  border-radius:4px 18px 18px 18px;
}
.msg-bubble.mine{
  background:var(--accent);color:#fff;
  border-radius:18px 4px 18px 18px;
}
/* Messenger tail shaping for consecutive */
.msg-row.mine.has-next .msg-bubble.mine{border-radius:18px 4px 4px 18px;}
.msg-row.theirs.has-next .msg-bubble.theirs{border-radius:4px 18px 18px 4px;}
.msg-row.mine.has-prev .msg-bubble.mine{border-radius:18px 4px 4px 18px;}
.msg-row.theirs.has-prev .msg-bubble.theirs{border-radius:4px 18px 18px 4px;}
/* First in group keeps top radius */
.msg-row.mine:not(.has-prev) .msg-bubble.mine{border-top-right-radius:18px;}
.msg-row.theirs:not(.has-prev) .msg-bubble.theirs{border-top-left-radius:4px;}

/* Hover actions */
.msg-bubble:hover .msg-actions{opacity:1;pointer-events:all;}
.msg-actions{
  position:absolute;top:50%;transform:translateY(-50%);
  right:-80px;
  display:flex;gap:2px;opacity:0;pointer-events:none;
  transition:opacity .12s;
  z-index:10;white-space:nowrap;
}
.msg-row.theirs .msg-bubble .msg-actions{right:auto;left:-80px;}
.msg-row.mine .msg-bubble .msg-actions{right:-80px;left:auto;}
.msg-action-btn{
  width:28px;height:28px;border-radius:50%;
  background:var(--surface);border:1px solid var(--border);
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  box-shadow:0 1px 6px rgba(0,0,0,.12);transition:background .1s;
}
.msg-action-btn:hover{background:var(--surface2);}
.msg-action-btn svg{width:12px;height:12px;stroke:var(--muted);fill:none;stroke-width:1.8;stroke-linecap:round;}

.msg-meta{margin-top:3px;padding:0 4px;display:flex;align-items:center;gap:6px;}
.msg-time{font-size:11px;color:var(--muted);}
/* seen indicator */
.msg-seen{font-size:10px;color:var(--muted);}

/* Reply preview inside bubble */
.reply-preview{
  background:rgba(0,0,0,.08);border-left:3px solid rgba(255,255,255,.5);
  border-radius:6px;padding:5px 8px;margin-bottom:6px;font-size:12px;opacity:.9;
  cursor:pointer;
}
.msg-bubble.theirs .reply-preview{background:var(--surface2);border-left-color:var(--accent);}

/* Images */
.msg-image{width:100%;max-width:250px;max-height:250px;object-fit:cover;display:block;cursor:pointer;border-radius:12px;}
.msg-bubble.img-only{background:transparent !important;padding:0 !important;border-radius:12px;overflow:hidden;}

/* Files */
.msg-file{display:flex;align-items:center;gap:8px;padding:10px 12px;
  background:rgba(0,0,0,.1);border-radius:10px;text-decoration:none;color:inherit;
  font-size:12px;font-weight:600;min-width:160px;}
.msg-bubble.mine .msg-file{background:rgba(255,255,255,.18);}
.msg-file-icon{flex-shrink:0;}

/* Voice */
.voice-msg{display:flex;align-items:center;gap:10px;min-width:180px;}
.voice-play-btn{
  width:34px;height:34px;border-radius:50%;
  background:rgba(255,255,255,.2);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;
}
.voice-play-btn svg{width:12px;height:12px;fill:currentColor;}
.msg-bubble.theirs .voice-play-btn{background:var(--accent);}
.voice-waveform{flex:1;height:28px;border-radius:14px;background:rgba(255,255,255,.12);
  display:flex;align-items:center;padding:0 6px;gap:2px;overflow:hidden;}
.msg-bubble.theirs .voice-waveform{background:var(--surface2);}
.wave-bar{width:3px;border-radius:2px;background:rgba(255,255,255,.6);flex-shrink:0;}
.msg-bubble.theirs .wave-bar{background:var(--accent);}

/* Date divider */
.date-divider{text-align:center;font-size:11px;font-weight:600;color:var(--muted);
  margin:14px 0;display:flex;align-items:center;gap:10px;}
.date-divider::before,.date-divider::after{content:'';flex:1;height:1px;background:var(--border);}

/* Reaction summary on bubble */
.msg-reactions{
  display:flex;gap:2px;margin-top:2px;flex-wrap:wrap;
}
.msg-react-badge{
  background:var(--surface);border:1px solid var(--border);border-radius:20px;
  padding:1px 6px;font-size:12px;cursor:pointer;
  display:flex;align-items:center;gap:2px;
  box-shadow:0 1px 4px rgba(0,0,0,.1);
}
.msg-react-badge:hover{background:var(--surface2);}

/* ── INPUT AREA ── */
/* Reply bar */
.reply-bar{
  padding:8px 16px;background:var(--surface);
  border-top:1px solid var(--border);
  display:none;align-items:center;gap:10px;flex-shrink:0;
}
.reply-bar.show{display:flex;}
.reply-bar-preview{
  flex:1;padding:6px 10px;background:var(--surface2);
  border-radius:8px;border-left:3px solid var(--accent);
  font-size:12px;color:var(--text);
}
.reply-bar-preview strong{color:var(--accent);display:block;font-size:11px;margin-bottom:2px;}
.reply-bar-text{color:var(--muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.reply-bar-close{
  background:none;border:none;cursor:pointer;
  width:24px;height:24px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.reply-bar-close:hover{background:var(--border);}
.reply-bar-close svg{width:12px;height:12px;stroke:var(--muted);fill:none;stroke-width:2.5;}

/* Attach preview */
.attach-preview{
  display:none;align-items:center;gap:8px;
  padding:8px 16px;background:var(--surface);
  border-top:1px solid var(--border);font-size:12px;flex-shrink:0;
}
.attach-preview.show{display:flex;}
.attach-preview-name{flex:1;font-weight:600;color:var(--text);overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
.attach-preview-remove{background:none;border:none;cursor:pointer;}
.attach-preview-remove svg{width:13px;height:13px;stroke:var(--muted);fill:none;stroke-width:2.5;}

/* Main input row */
.chat-input-area{
  padding:10px 16px 12px;border-top:1px solid var(--border);
  flex-shrink:0;background:var(--surface);position:relative;
}
.chat-input-row{display:flex;align-items:flex-end;gap:6px;}

/* Icon buttons left of input */
.chat-icon-btns{display:flex;align-items:center;gap:0;flex-shrink:0;}
.chat-icon-btn{
  width:36px;height:36px;border-radius:50%;border:none;
  background:transparent;cursor:pointer;color:var(--accent);
  display:flex;align-items:center;justify-content:center;transition:background .1s;
}
.chat-icon-btn:hover{background:var(--accent-soft);}
.chat-icon-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}

/* Input wrap */
.chat-input-wrap{
  flex:1;display:flex;align-items:flex-end;
  border-radius:22px;background:var(--surface2);
  padding:8px 12px;gap:4px;
  border:1.5px solid transparent;transition:border-color .15s;
  min-height:42px;
}
.chat-input-wrap:focus-within{border-color:var(--border2);}
.chat-input{
  flex:1;border:none;background:transparent;outline:none;
  font-family:var(--font);font-size:14px;color:var(--text);
  padding:0 2px;resize:none;line-height:1.45;
  min-height:24px;max-height:100px;overflow-y:auto;
  align-self:center;
}
.chat-input::placeholder{color:var(--muted);}

/* Emoji in input */
.chat-emoji-inline{
  width:28px;height:28px;border-radius:50%;border:none;
  background:transparent;cursor:pointer;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  align-self:flex-end;margin-bottom:2px;
}
.chat-emoji-inline:hover{background:var(--border);}
.chat-emoji-inline svg{width:18px;height:18px;}

/* Send / voice button */
.send-btn{
  width:36px;height:36px;border-radius:50%;
  background:var(--accent);border:none;
  color:#fff;cursor:pointer;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  transition:all .15s;
}
.send-btn svg{width:16px;height:16px;fill:#fff;}
.send-btn:hover{background:var(--accent-hover);transform:scale(1.05);}
.send-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;}
.voice-btn{
  width:36px;height:36px;border-radius:50%;
  background:transparent;border:none;
  color:var(--accent);cursor:pointer;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;transition:all .15s;
}
.voice-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}
.voice-btn.recording{color:#ef4444;animation:pulse-rec 1s infinite;}
@keyframes pulse-rec{0%,100%{filter:drop-shadow(0 0 0 rgba(239,68,68,.4));}50%{filter:drop-shadow(0 0 8px rgba(239,68,68,.7));}}
.rec-status{font-size:11px;color:var(--muted);margin-top:4px;text-align:center;min-height:14px;}

/* ══════════════════════════════
   RIGHT INFO PANEL — Messenger
══════════════════════════════ */
.chat-info-panel{
  background:var(--surface);
  overflow-y:auto;flex-shrink:0;
  display:flex;flex-direction:column;
}
.info-panel-header{
  padding:20px 16px 12px;border-bottom:1px solid var(--border);
  text-align:center;flex-shrink:0;
}
.info-panel-avatar{
  width:72px;height:72px;border-radius:50%;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  margin:0 auto 10px;
  display:flex;align-items:center;justify-content:center;
}
.info-panel-avatar svg{width:32px;height:32px;stroke:#fff;fill:none;stroke-width:1.8;stroke-linecap:round;}
.info-panel-name{font-size:16px;font-weight:700;color:var(--text);margin-bottom:3px;}
.info-panel-status{font-size:12px;color:#22c55e;font-weight:500;}
.info-panel-actions{display:flex;justify-content:center;gap:12px;margin-top:14px;}
.info-btn{
  display:flex;flex-direction:column;align-items:center;gap:4px;
  width:52px;cursor:pointer;background:none;border:none;
}
.info-btn-icon{
  width:36px;height:36px;border-radius:50%;
  background:var(--surface2);
  display:flex;align-items:center;justify-content:center;
  transition:background .1s;
}
.info-btn:hover .info-btn-icon{background:var(--border);}
.info-btn-icon svg{width:16px;height:16px;stroke:var(--text);fill:none;stroke-width:2;stroke-linecap:round;}
.info-btn-label{font-size:11px;color:var(--muted);font-weight:500;}

.info-section{border-bottom:1px solid var(--border);}
.info-section-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 16px;cursor:pointer;
}
.info-section-title{font-size:14px;font-weight:700;color:var(--text);}
.info-section-arrow{transition:transform .2s;width:16px;height:16px;stroke:var(--muted);fill:none;stroke-width:2.5;stroke-linecap:round;}
.info-section-header.open .info-section-arrow{transform:rotate(180deg);}
.info-section-body{padding:0 16px 12px;display:none;}
.info-section-body.show{display:block;}
.info-item{
  display:flex;align-items:center;gap:10px;
  padding:8px 0;cursor:pointer;border-radius:8px;
  font-size:13px;color:var(--text);
}
.info-item:hover{color:var(--accent);}
.info-item svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0;}
.info-item.danger{color:#ef4444;}
.info-item.danger:hover{color:#dc2626;}

.info-members-list{display:flex;flex-direction:column;gap:4px;}
.info-member{display:flex;align-items:center;gap:10px;padding:4px 0;}
.info-member-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;}
.info-member-name{font-size:13px;font-weight:500;color:var(--text);}
.info-member-role{font-size:11px;color:var(--muted);}

/* ══════════════════════════════
   SEARCH BAR IN CHAT
══════════════════════════════ */
.msg-search-bar{
  display:none;padding:8px 16px;background:var(--surface);
  border-bottom:1px solid var(--border);flex-shrink:0;
}
.msg-search-bar.show{display:block;}
.msg-search-wrap{
  display:flex;align-items:center;gap:6px;
  background:var(--surface2);border-radius:20px;padding:6px 12px;
}
.msg-search-wrap input{
  flex:1;border:none;background:transparent;outline:none;
  font-family:var(--font);font-size:13px;color:var(--text);
}
.msg-search-wrap input::placeholder{color:var(--muted);}
.search-result-count{font-size:11px;color:var(--muted);white-space:nowrap;min-width:40px;text-align:center;}
.search-nav-btn{
  background:none;border:none;cursor:pointer;padding:3px;
  border-radius:6px;display:flex;align-items:center;
  justify-content:center;color:var(--muted);
}
.search-nav-btn:hover{background:var(--border);color:var(--text);}
.msg-highlight{background:rgba(99,102,241,.3) !important;border-radius:3px;}
.msg-highlight-active{background:rgba(99,102,241,.65) !important;}

/* ══════════════════════════════
   EMOJI PANEL
══════════════════════════════ */
.chat-emoji-panel{
  position:absolute;bottom:calc(100% + 4px);right:16px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;padding:12px;
  box-shadow:0 8px 40px rgba(0,0,0,.2);
  display:none;z-index:100;width:300px;
}
.chat-emoji-panel.show{display:block;}
.emoji-search{
  display:flex;align-items:center;gap:6px;
  background:var(--surface2);border-radius:20px;
  padding:6px 12px;margin-bottom:8px;
}
.emoji-search input{
  flex:1;border:none;background:transparent;outline:none;
  font-family:var(--font);font-size:13px;color:var(--text);
}
.emoji-search input::placeholder{color:var(--muted);}
.emoji-tabs{display:flex;gap:2px;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--border);}
.emoji-tab{
  flex:1;background:none;border:none;cursor:pointer;
  font-size:18px;padding:4px;border-radius:8px;transition:background .1s;
}
.emoji-tab:hover,.emoji-tab.active{background:var(--surface2);}
.emoji-grid{display:flex;flex-wrap:wrap;gap:1px;max-height:180px;overflow-y:auto;}
.emoji-cell{
  font-size:24px;width:36px;height:36px;
  cursor:pointer;border-radius:8px;background:none;border:none;
  display:flex;align-items:center;justify-content:center;
  transition:background .1s;line-height:1;
}
.emoji-cell:hover{background:var(--surface2);}

/* ══════════════════════════════
   MODALS
══════════════════════════════ */
.lightbox{
  position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:400;
  display:none;align-items:center;justify-content:center;cursor:pointer;
}
.lightbox.show{display:flex;}
.lightbox img{max-width:90vw;max-height:90vh;border-radius:8px;}

.modal-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;
  align-items:center;justify-content:center;backdrop-filter:blur(4px);display:none;
}
.modal-overlay.show{display:flex;}
.modal-box{
  background:var(--surface);border-radius:16px;padding:24px 20px;
  width:420px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.25);
}
.modal-handle{display:none;}
.modal-title{font-size:16px;font-weight:700;margin-bottom:16px;letter-spacing:-.2px;color:var(--text);}
.icon-picker{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:4px;}
.icon-opt{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:20px;background:var(--surface2);border:2px solid transparent;transition:all .1s;}
.icon-opt:hover{background:var(--border);}
.icon-opt.sel{border-color:var(--accent);background:var(--accent-soft);}
.form-input{width:100%;padding:9px 13px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:var(--font);font-size:14px;outline:none;box-sizing:border-box;}
.form-input:focus{border-color:var(--accent);}

/* scrollbar */
.messages::-webkit-scrollbar,.rooms-list::-webkit-scrollbar,.chat-info-panel::-webkit-scrollbar{width:4px;}
.messages::-webkit-scrollbar-thumb,.rooms-list::-webkit-scrollbar-thumb,.chat-info-panel::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px;}

/* mobile */
.mobile-back-btn{display:none;}
@media(max-width:768px){
  .rooms-layout{grid-template-columns:100%;height:calc(100vh - 60px);position:relative;}
  .rooms-layout-inner{grid-template-columns:1fr;}
  .rooms-sidebar{
    position:absolute;top:0;left:0;right:0;bottom:0;z-index:10;
    transform:translateX(0);transition:transform .25s cubic-bezier(.4,0,.2,1);
  }
  .rooms-sidebar.slide-out{transform:translateX(-100%);}
  .chat-main{
    position:absolute;top:0;left:0;right:0;bottom:0;z-index:10;
    transform:translateX(100%);transition:transform .25s cubic-bezier(.4,0,.2,1);
  }
  .chat-main.slide-in{transform:translateX(0);}
  .chat-info-panel{display:none !important;}
  .mobile-back-btn{
    display:flex;width:36px;height:36px;border-radius:50%;
    border:none;background:transparent;cursor:pointer;
    color:var(--accent);align-items:center;justify-content:center;
    flex-shrink:0;
  }
  .mobile-back-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;}
  .msg-body{max-width:78%;}
  .chat-input-area{padding:8px 10px 12px;}
}
</style>
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page rooms-page">
  <div class="rooms-layout">
    <!-- ══ SIDEBAR ══ -->
    <div class="rooms-sidebar" id="roomsSidebar">
      <div class="sidebar-header">
        <div class="sidebar-title">Chats</div>
        <div class="sidebar-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" placeholder="Tìm kiếm..." oninput="filterRooms(this.value)">
        </div>
      </div>
      <div class="sidebar-tabs">
        <button class="sidebar-tab active">Tất cả</button>
        <button class="sidebar-tab">Chưa đọc</button>
        <button class="sidebar-tab">Nhóm</button>
      </div>
      <div class="rooms-list" id="roomsList">
        <?php foreach($rooms as $r): ?>
        <div class="room-item <?=$r['id']==$activeRid?'active':''?>"
             id="ri-<?=$r['id']?>" onclick="openRoom(<?=$r['id']?>,'<?=addslashes(htmlspecialchars($r['name']))?>','<?=addslashes(htmlspecialchars($r['description']??''))?>')">
          <div class="room-avatar">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:22px;height:22px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <div class="room-online"></div>
          </div>
          <div class="room-info">
            <div class="room-name"><?=htmlspecialchars($r['name'])?></div>
            <div class="room-preview"><?=htmlspecialchars(mb_substr($r['last_msg']??'Chưa có tin nhắn',0,40))?></div>
          </div>
          <div class="room-meta-right">
            <?php if($r['msg_count']>0): ?><div class="room-badge"><?=$r['msg_count']?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sidebar-footer">
        <button class="new-room-btn" onclick="document.getElementById('newRoomModal').classList.add('show')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:14px;height:14px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Tạo phòng mới
        </button>
      </div>
    </div>

    <!-- ══ CHAT AREA + INFO PANEL ══ -->
    <div class="rooms-layout-inner" id="roomsInner">
      <!-- CHAT MAIN -->
      <div class="chat-main" id="chatMain">
        <!-- Header -->
        <div class="chat-header">
          <button class="mobile-back-btn" onclick="goBackToList()">
            <svg viewBox="0 0 24 24"><polyline points="15,18 9,12 15,6"/></svg>
          </button>
          <div class="chat-header-avatar">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <div class="chat-header-online"></div>
          </div>
          <div style="flex:1;min-width:0;">
            <div class="chat-room-name" id="chatName"><?=htmlspecialchars($activeRoom['name']??'Phòng chat')?></div>
            <div class="chat-room-status">Đang hoạt động</div>
          </div>
          <div class="chat-header-actions">
            <button class="chat-header-btn" title="Tìm kiếm" onclick="toggleMsgSearch()" id="searchToggleBtn">
              <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
            <button class="chat-header-btn" title="Thành viên" onclick="toggleInfoPanel()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </button>
          </div>
        </div>

        <!-- Search bar -->
        <div class="msg-search-bar" id="msgSearchBar">
          <div class="msg-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:14px;height:14px;flex-shrink:0;color:var(--muted)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="msgSearchInput" placeholder="Tìm kiếm trong cuộc trò chuyện..." oninput="doMsgSearch(this.value)" onkeydown="if(event.key==='Escape')toggleMsgSearch()">
            <span class="search-result-count" id="searchCount"></span>
            <button class="search-nav-btn" onclick="searchNav(-1)" title="Trước"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:13px;height:13px;"><polyline points="15 18 9 12 15 6"/></svg></button>
            <button class="search-nav-btn" onclick="searchNav(1)" title="Sau"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:13px;height:13px;"><polyline points="9 18 15 12 9 6"/></svg></button>
            <button class="search-nav-btn" onclick="toggleMsgSearch()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:13px;height:13px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
        </div>

        <!-- Messages -->
        <div class="messages" id="msgList">
          <?php
          $prevDate=''; $prevUser=null; $prevMine=null;
          foreach($msgs as $idx => $m):
            $date = date('d/m/Y',strtotime($m['created_at']));
            if($date!==$prevDate){echo '<div class="date-divider">'.$date.'</div>'; $prevDate=$date; $prevUser=null;}
            $mine = $m['user_id']==$uid;
            $av   = userAvatar($m,28);
            $side = $mine?'mine':'theirs';
            $nextM = $msgs[$idx+1] ?? null;
            $hasPrev = ($prevUser === $m['user_id']);
            $hasNext = ($nextM && $nextM['user_id'] === $m['user_id']);
            $rowCls = $side.($hasPrev?' has-prev':'').($hasNext?' has-next':'');
            $prevUser = $m['user_id'];

            $replyHtml='';
            if(!empty($m['reply_to'])){
              $rp=$db->query("SELECT m.*,u.name FROM room_messages m JOIN users u ON m.user_id=u.id WHERE m.id={$m['reply_to']}")->fetchArray(SQLITE3_ASSOC);
              if($rp) $replyHtml='<div class="reply-preview" onclick="scrollToMsg('.(int)$m['reply_to'].')"><strong>'.htmlspecialchars($rp['name']).'</strong><br>'.mb_substr(htmlspecialchars($rp['content']),0,80).'</div>';
            }

            $msgType  = $m['msg_type'] ?? 'text';
            $fileData = $m['file_data'] ?? '';
            $fileName = htmlspecialchars($m['file_name'] ?? '');
            $content  = htmlspecialchars($m['content']);
            $time     = timeAgo($m['created_at']);
            $mid      = (int)$m['id'];

            $delSvg   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,6 5,6 21,6"/><path d="M19,6l-1,14H6L5,6"/><path d="M10,11v6M14,11v6M9,6V4h6v2"/></svg>';
            $replySvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9,17 4,12 9,7"/><path d="M20,18v-2a4,4,0,0,0-4-4H4"/></svg>';
            $actions = ($mine?'<button class="msg-action-btn" onclick="deleteMsg('.$mid.',event)" title="Xoá">'.$delSvg.'</button>':'')
              .'<button class="msg-action-btn" onclick="setReply('.$mid.',\''.addslashes(htmlspecialchars($m['name'])).'\',\''.addslashes(mb_substr(htmlspecialchars($m['content']),0,60)).'\')" title="Trả lời">'.$replySvg.'</button>';

            $imgOnly = false;
            if($msgType==='image' && $fileData) {
              $safeData = htmlspecialchars($fileData);
              $imgOnly = !$replyHtml;
              $innerHtml = $replyHtml.'<img src="'.$safeData.'" class="msg-image" onclick="openLightbox(this.src)" alt="'.$fileName.'">';
            } elseif($msgType==='voice' && $fileData) {
              $safeJs = addslashes($fileData);
              $innerHtml = $replyHtml.'<div class="voice-msg"><button class="voice-play-btn" onclick="playVoice(this,\''.$safeJs.'\')"><svg viewBox="0 0 24 24" fill="currentColor" style="width:10px;height:10px;"><polygon points="5,3 19,12 5,21"/></svg></button><div class="voice-waveform"></div></div>';
            } elseif($msgType==='file' && $fileData) {
              $ext = strtolower(pathinfo($m['file_name']??'',PATHINFO_EXTENSION));
              $fsvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
              $safeData = htmlspecialchars($fileData);
              $innerHtml = $replyHtml.'<a href="'.$safeData.'" download="'.$fileName.'" class="msg-file"><span class="msg-file-icon">'.$fsvg.'</span><span>'.($fileName?:$content).'</span></a>';
            } else {
              $innerHtml = $replyHtml.$content;
            }

            $imgOnlyClass = $imgOnly ? ' img-only' : '';
            $avatarHtml = $mine ? '' : '<div class="msg-avatar-wrap">'.$av.'</div>';
            $showSender = !$hasPrev && !$mine;
            echo <<<HTML
<div class="msg-row {$rowCls}" id="m-{$mid}">
  {$avatarHtml}
  <div class="msg-body">
    <div class="msg-bubble {$side}{$imgOnlyClass}">
      {$innerHtml}
      <div class="msg-actions">{$actions}</div>
    </div>
    <div class="msg-meta"><span class="msg-time">{$time}</span></div>
  </div>
</div>
HTML;
          endforeach;
          if(empty($msgs)) echo '<div style="text-align:center;color:var(--muted);padding:3rem;font-size:13px;">Chưa có tin nhắn. Hãy bắt đầu cuộc trò chuyện!</div>';
          ?>
        </div>

        <!-- Reply bar -->
        <div class="reply-bar" id="replyBar">
          <div class="reply-bar-preview">
            <strong id="replyBarUser">Đang trả lời</strong>
            <div class="reply-bar-text" id="replyBarText"></div>
          </div>
          <button class="reply-bar-close" onclick="clearReply()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:12px;height:12px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>

        <!-- Attach preview -->
        <div class="attach-preview" id="attachPreview">
          <span id="attachPreviewIcon" style="display:flex;align-items:center;flex-shrink:0;color:var(--accent);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          </span>
          <span class="attach-preview-name" id="attachPreviewName"></span>
          <button class="attach-preview-remove" onclick="clearAttach()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:13px;height:13px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>

        <!-- Input area -->
        <div class="chat-input-area">
          <div class="chat-input-row">
            <!-- Left icon buttons like Messenger -->
            <div class="chat-icon-btns">
              <button class="chat-icon-btn" title="Gửi ảnh" onclick="document.getElementById('imgInput').click()">
                <svg viewBox="0 0 24 24" stroke="#45bd62"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="#45bd62" stroke="none"/><polyline points="21,15 16,10 5,21"/></svg>
              </button>
              <button class="chat-icon-btn" title="Gửi file" onclick="document.getElementById('fileInput').click()">
                <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
              </button>
              <button class="chat-icon-btn" title="Ghi âm" id="voiceBtn" onclick="toggleRecording()">
                <svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
              </button>
            </div>
            <input type="file" id="imgInput" accept="image/*" style="display:none" onchange="onFileChosen(this,'image')">
            <input type="file" id="fileInput" style="display:none" onchange="onFileChosen(this,'file')">
            <!-- Input wrap -->
            <div class="chat-input-wrap">
              <textarea class="chat-input" id="chatInput" placeholder="Aa" onkeydown="inputKey(event)" rows="1" maxlength="4000"></textarea>
              <button class="chat-emoji-inline" onclick="toggleChatEmoji(event)" title="Emoji">
                <svg viewBox="0 0 24 24" fill="none" stroke="#f7b928" stroke-width="2" stroke-linecap="round" style="width:18px;height:18px;"><circle cx="12" cy="12" r="9"/><path d="M8 13s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9" stroke-width="2.5"/><line x1="15" y1="9" x2="15.01" y2="9" stroke-width="2.5"/></svg>
              </button>
            </div>
            <!-- Send -->
            <button class="send-btn" id="sendBtn" onclick="sendMsg()">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
            </button>
          </div>
          <div class="rec-status" id="recStatus"></div>
        </div>
      </div>

      <!-- ══ INFO PANEL ══ -->
      <div class="chat-info-panel" id="chatInfoPanel">
        <div class="info-panel-header">
          <div class="info-panel-avatar">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </div>
          <div class="info-panel-name" id="infoPanelName"><?=htmlspecialchars($activeRoom['name']??'Phòng chat')?></div>
          <div class="info-panel-status">Đang hoạt động</div>
          <div class="info-panel-actions">
            <button class="info-btn" title="Tắt thông báo">
              <div class="info-btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:16px;height:16px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
              <span class="info-btn-label">Tắt tiếng</span>
            </button>
            <button class="info-btn" title="Tìm kiếm" onclick="toggleMsgSearch()">
              <div class="info-btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:16px;height:16px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
              <span class="info-btn-label">Tìm kiếm</span>
            </button>
            <button class="info-btn" title="Thêm thành viên">
              <div class="info-btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:16px;height:16px;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
              <span class="info-btn-label">Mời</span>
            </button>
          </div>
        </div>

        <!-- Chat info sections -->
        <div class="info-section">
          <div class="info-section-header" onclick="toggleSection(this)">
            <span class="info-section-title">Tuỳ chỉnh cuộc trò chuyện</span>
            <svg class="info-section-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
          <div class="info-section-body">
            <div class="info-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg> Đổi chủ đề</div>
            <div class="info-item"><svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 13v3h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/></svg> Đổi emoji</div>
            <div class="info-item"><svg viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg> Biệt danh</div>
          </div>
        </div>

        <div class="info-section">
          <div class="info-section-header" onclick="toggleSection(this)">
            <span class="info-section-title">Thành viên phòng</span>
            <svg class="info-section-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
          <div class="info-section-body">
            <div class="info-members-list" id="membersList">
              <div class="info-member">
                <div class="info-member-av"><?=mb_strtoupper(mb_substr($user['name']??'U',0,1))?></div>
                <div><div class="info-member-name"><?=htmlspecialchars($user['name']??'Bạn')?></div><div class="info-member-role">Bạn</div></div>
              </div>
            </div>
          </div>
        </div>

        <div class="info-section">
          <div class="info-section-header" onclick="toggleSection(this)">
            <span class="info-section-title">File & ảnh được chia sẻ</span>
            <svg class="info-section-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
          <div class="info-section-body">
            <div style="font-size:12px;color:var(--muted);">Chưa có file nào được chia sẻ.</div>
          </div>
        </div>

        <div class="info-section">
          <div class="info-section-header" onclick="toggleSection(this)">
            <span class="info-section-title">Quyền riêng tư & hỗ trợ</span>
            <svg class="info-section-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
          <div class="info-section-body">
            <div class="info-item"><svg viewBox="0 0 24 24"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg> Xem tin nhắn biến mất</div>
            <div class="info-item danger"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Chặn phòng</div>
          </div>
        </div>
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
    <div class="modal-title">Tạo phòng chat mới</div>
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
      <button class="btn btn-primary" style="flex:1;" onclick="createRoom()">Tạo phòng</button>
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
let infoPanelOpen = true;

const isMobile = () => window.innerWidth <= 768;

/* ══ INIT ══ */
window.addEventListener('DOMContentLoaded', () => {
  if(isMobile()){
    document.getElementById('chatMain').classList.remove('slide-in');
    document.getElementById('roomsInner').classList.remove('rooms-layout-inner');
  }
  const ml = document.getElementById('msgList');
  ml.scrollTop = ml.scrollHeight;
  initAllWaveforms();
  poll();
});

/* ══ ROOM SWITCHING ══ */
function openRoom(rid, name, desc){
  switchRoom(rid, name, desc);
  if(isMobile()){
    document.getElementById('roomsSidebar').classList.add('slide-out');
    document.getElementById('chatMain').classList.add('slide-in');
  }
}
function goBackToList(){
  document.getElementById('roomsSidebar').classList.remove('slide-out');
  document.getElementById('chatMain').classList.remove('slide-in');
}
function filterRooms(q){
  q = q.toLowerCase();
  document.querySelectorAll('.room-item').forEach(r=>{
    const name = r.querySelector('.room-name')?.textContent.toLowerCase()||'';
    r.style.display = name.includes(q) ? '' : 'none';
  });
}

/* ══ INFO PANEL TOGGLE ══ */
function toggleInfoPanel(){
  infoPanelOpen = !infoPanelOpen;
  const inner = document.getElementById('roomsInner');
  const panel = document.getElementById('chatInfoPanel');
  if(infoPanelOpen){
    inner.classList.remove('no-info');
    panel.style.display='';
  } else {
    inner.classList.add('no-info');
    panel.style.display='none';
  }
  document.querySelectorAll('.chat-header-btn')[1]?.classList.toggle('active', infoPanelOpen);
}

function toggleSection(header){
  header.classList.toggle('open');
  const body = header.nextElementSibling;
  body.classList.toggle('show');
}

/* ══ TEXTAREA AUTO-RESIZE ══ */
document.addEventListener('DOMContentLoaded', ()=>{
  const ta = document.getElementById('chatInput');
  if(ta) ta.addEventListener('input', function(){
    this.style.height='auto';
    this.style.height=Math.min(this.scrollHeight,100)+'px';
  });
});

function inputKey(e){
  if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendMsg(); }
}

/* ══ FILE CHOOSER ══ */
function onFileChosen(input, type){
  const f = input.files[0];
  if(!f) return;
  pendingFile = {file:f, type};
  const icon = document.getElementById('attachPreviewIcon');
  if(type==='image'){
    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" style="width:18px;height:18px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>';
  }
  document.getElementById('attachPreviewName').textContent = f.name+' ('+(f.size/1024).toFixed(1)+' KB)';
  document.getElementById('attachPreview').classList.add('show');
  input.value='';
}
function clearAttach(){
  pendingFile=null;
  document.getElementById('attachPreview').classList.remove('show');
}

/* ══ SEND ══ */
async function sendMsg(){
  const input   = document.getElementById('chatInput');
  const content = input.value.trim();
  if(!content && !pendingFile) return;
  const fd=new FormData();
  fd.append('action','send'); fd.append('room_id',currentRoom);
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

/* ══ RENDER MESSAGE ══ */
function appendMsg(m){
  const ml = document.getElementById('msgList');
  const div = document.createElement('div');
  const side = m.mine?'mine':'theirs';
  div.className = 'msg-row ' + side;
  div.id = 'm-'+m.id;

  const replyHtml = m.reply
    ? `<div class="reply-preview" onclick="scrollToMsg(${m.reply_id||0})"><strong>${m.reply.user}</strong><br>${m.reply.text}</div>` : '';

  const delSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,6 5,6 21,6"/><path d="M19,6l-1,14H6L5,6"/><path d="M10,11v6M14,11v6M9,6V4h6v2"/></svg>';
  const replySvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9,17 4,12 9,7"/><path d="M20,18v-2a4,4,0,0,0-4-4H4"/></svg>';
  const actions = (m.mine ? `<button class="msg-action-btn" onclick="deleteMsg(${m.id},event)" title="Xoá">${delSvg}</button>` : '')
    + `<button class="msg-action-btn" onclick="setReply(${m.id},'${(m.user||'').replace(/'/g,"\\'")}','${(m.content||'').replace(/'/g,"\\'").slice(0,60)}')" title="Trả lời">${replySvg}</button>`;

  const t = m.msg_type||'text';
  let inner='', imgOnly=false;
  if(t==='image' && m.file_data){
    imgOnly = !m.reply;
    inner = replyHtml+`<img src="${m.file_data}" class="msg-image" onclick="openLightbox(this.src)" alt="">`;
  } else if(t==='voice' && m.file_data){
    const sd = m.file_data.replace(/'/g,"\\'");
    inner = replyHtml+`<div class="voice-msg"><button class="voice-play-btn" onclick="playVoice(this,'${sd}')"><svg viewBox="0 0 24 24" fill="currentColor" style="width:10px;height:10px;"><polygon points="5,3 19,12 5,21"/></svg></button><div class="voice-waveform" id="wf-${m.id}"></div></div>`;
  } else if(t==='file' && m.file_data){
    const fsvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="width:18px;height:18px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    inner = replyHtml+`<a href="${m.file_data}" download="${m.file_name||'file'}" class="msg-file"><span class="msg-file-icon">${fsvg}</span><span>${m.file_name||m.content}</span></a>`;
  } else {
    inner = replyHtml+(m.content||'');
  }

  const avatarHtml = m.mine ? '' : `<div class="msg-avatar-wrap">${m.avatar}</div>`;
  div.innerHTML = `${avatarHtml}<div class="msg-body">
    <div class="msg-bubble ${side}${imgOnly?' img-only':''}">${inner}<div class="msg-actions">${actions}</div></div>
    <div class="msg-meta"><span class="msg-time">${m.time}</span></div>
  </div>`;

  const atBottom = ml.scrollTop+ml.clientHeight >= ml.scrollHeight-80;
  ml.appendChild(div);
  if(t==='voice'){ const wf=div.querySelector('.voice-waveform'); if(wf) initWaveformEl(wf); }
  if(atBottom||m.mine) ml.scrollTop=ml.scrollHeight;
}

function scrollToMsg(mid){
  const el = document.getElementById('m-'+mid);
  if(el){ el.scrollIntoView({behavior:'smooth',block:'center'}); el.querySelector('.msg-bubble')?.classList.add('msg-highlight'); setTimeout(()=>el.querySelector('.msg-bubble')?.classList.remove('msg-highlight'),1500); }
}

/* ══ POLL ══ */
async function poll(){
  try{
    const fd=new FormData(); fd.append('action','poll'); fd.append('room_id',currentRoom); fd.append('after_id',lastMsgId);
    const res=await fetch('rooms.php',{method:'POST',body:fd});
    const data=await res.json();
    if(data.ok&&data.messages.length){
      data.messages.forEach(m=>{ if(!document.getElementById('m-'+m.id)) appendMsg(m); lastMsgId=Math.max(lastMsgId,m.id); });
    }
  }catch(e){}
  pollTimer=setTimeout(poll,2500);
}

/* ══ DELETE ══ */
async function deleteMsg(mid,e){
  e.stopPropagation();
  if(!confirm('Xoá tin nhắn này?')) return;
  const fd=new FormData(); fd.append('action','delete_msg'); fd.append('msg_id',mid);
  await fetch('rooms.php',{method:'POST',body:fd});
  document.getElementById('m-'+mid)?.remove();
}

/* ══ REPLY ══ */
function setReply(id,user,text){
  replyToId=id;
  document.getElementById('replyBarUser').textContent='Đang trả lời '+user;
  document.getElementById('replyBarText').textContent=text;
  document.getElementById('replyBar').classList.add('show');
  document.getElementById('chatInput').focus();
}
function clearReply(){
  replyToId=null;
  document.getElementById('replyBar').classList.remove('show');
}

/* ══ SWITCH ROOM ══ */
async function switchRoom(rid,name,desc){
  clearTimeout(pollTimer);
  currentRoom=rid; lastMsgId=0;
  document.querySelectorAll('.room-item').forEach(r=>r.classList.toggle('active',r.id==='ri-'+rid));
  document.getElementById('chatName').textContent=name;
  document.getElementById('infoPanelName').textContent=name;
  const ml=document.getElementById('msgList');
  ml.innerHTML='<div style="text-align:center;color:var(--muted);padding:3rem;font-size:13px;">Đang tải...</div>';
  const fd=new FormData(); fd.append('action','poll'); fd.append('room_id',rid); fd.append('after_id',0);
  const res=await fetch('rooms.php',{method:'POST',body:fd});
  const data=await res.json();
  ml.innerHTML='';
  if(!data.messages.length) ml.innerHTML='<div style="text-align:center;color:var(--muted);padding:3rem;font-size:13px;">Bắt đầu chat thôi!</div>';
  else data.messages.forEach(m=>{ appendMsg(m); lastMsgId=Math.max(lastMsgId,m.id); });
  ml.scrollTop=ml.scrollHeight;
  poll();
}

/* ══ CREATE ROOM ══ */
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
  fd.append('action','create_room'); fd.append('name',name); fd.append('desc',desc); fd.append('icon',selectedIcon);
  const res=await fetch('rooms.php',{method:'POST',body:fd});
  const data=await res.json();
  if(data.ok){
    document.getElementById('newRoomModal').classList.remove('show');
    const list=document.getElementById('roomsList');
    const div=document.createElement('div');
    div.className='room-item'; div.id='ri-'+data.id;
    div.onclick=()=>openRoom(data.id,data.name,data.desc);
    div.innerHTML=`<div class="room-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:22px;height:22px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><div class="room-online"></div></div><div class="room-info"><div class="room-name">${data.name}</div><div class="room-preview">Vừa tạo</div></div><div class="room-meta-right"></div>`;
    list.appendChild(div);
    switchRoom(data.id,data.name,data.desc);
    document.getElementById('newRoomName').value='';
    document.getElementById('newRoomDesc').value='';
  }else{alert(data.msg||'Lỗi!');}
}

/* ══ VOICE RECORDING ══ */
async function toggleRecording(){
  const btn=document.getElementById('voiceBtn');
  if(!isRecording){
    try{
      const stream=await navigator.mediaDevices.getUserMedia({audio:true});
      mediaRecorder=new MediaRecorder(stream);
      audioChunks=[];
      mediaRecorder.ondataavailable=e=>{if(e.data.size>0)audioChunks.push(e.data);};
      mediaRecorder.onstop=async()=>{
        const blob=new Blob(audioChunks,{type:'audio/webm'});
        const file=new File([blob],'voice.webm',{type:'audio/webm'});
        stream.getTracks().forEach(t=>t.stop());
        const fd=new FormData();
        fd.append('action','send'); fd.append('room_id',currentRoom);
        fd.append('content','Tin nhắn thoại'); fd.append('msg_type','voice');
        fd.append('attachment',file);
        if(replyToId) fd.append('reply_to',replyToId);
        clearReply();
        document.getElementById('sendBtn').disabled=true;
        document.getElementById('recStatus').textContent='Đang gửi...';
        try{
          const res=await fetch('rooms.php',{method:'POST',body:fd});
          const data=await res.json();
          if(data.ok){appendMsg(data.message); lastMsgId=data.message.id;}
        }catch(e){console.error(e);}
        document.getElementById('sendBtn').disabled=false;
        document.getElementById('recStatus').textContent='';
      };
      mediaRecorder.start(); isRecording=true;
      btn.classList.add('recording');
      let sec=0;
      window._recTimer=setInterval(()=>{
        sec++;
        document.getElementById('recStatus').textContent='Đang ghi '+sec+'s — nhấn nút mic để dừng';
      },1000);
    }catch(e){alert('Không thể truy cập microphone.');}
  }else{
    clearInterval(window._recTimer);
    mediaRecorder.stop(); isRecording=false;
    btn.classList.remove('recording');
  }
}

/* ══ WAVEFORM ══ */
function initAllWaveforms(){ document.querySelectorAll('.voice-waveform').forEach(initWaveformEl); }
function initWaveformEl(el){
  if(!el||el.children.length>0) return;
  [3,6,10,14,18,12,8,16,11,7,15,9,13,5,10,12,8,14,6,10].forEach(h=>{
    const b=document.createElement('div'); b.className='wave-bar'; b.style.height=h+'px'; el.appendChild(b);
  });
}
function playVoice(btn,dataUrl){
  const audio=new Audio(dataUrl);
  btn.innerHTML='<svg viewBox="0 0 24 24" fill="currentColor" style="width:10px;height:10px;"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>';
  audio.play();
  audio.onended=()=>{btn.innerHTML='<svg viewBox="0 0 24 24" fill="currentColor" style="width:10px;height:10px;"><polygon points="5,3 19,12 5,21"/></svg>';};
}

/* ══ LIGHTBOX ══ */
function openLightbox(src){
  document.getElementById('lightboxImg').src=src;
  document.getElementById('lightbox').classList.add('show');
}

/* ══ EMOJI PANEL ══ */
const EMOJI_CATS = {
  '😀': ['😀','😂','🥰','😍','🤩','😎','🥹','😭','😤','🤔','😴','🤯','🥳','😇','🤗','😅','😬','🫣','😮','😱','🤭','😆','😊','🙃','🤪','😋','😏','🥺','😳','😶'],
  '❤️': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','💯','✨','🔥','⭐','🌟','💫','🎉','🎊','🏆','👑','💎','🎁','🎈','🎀'],
  '👍': ['👍','👎','👏','🙌','🤝','🤜','🤛','✊','👊','💪','🙏','🫶','👋','🤟','🫡','🤙','☝️','🫵','🫂','💅','🤌','👌','✌️','🤞','🤘'],
  '🐶': ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐸','🐧','🦁','🐯','🐮','🐷','🐙','🦋','🌸','🌈','🍀','🌊','🌻','🌺','🌴','🦄','🐬'],
  '🍕': ['🍕','🍔','🍟','🍣','🍜','🍩','🎂','🍦','☕','🧋','🍺','🥤','🍫','🍿','🥗','🌮','🍱','🍛','🥐','🧆','🥘','🍲','🫔','🥙','🧇'],
  '🎮': ['🎮','⚽','🏀','🎸','🎤','📱','💻','📸','🎬','📚','✏️','🔑','💡','🎯','🪄','🧩','🎲','🏆','🎻','🎺','🥁','🎹','🎭','🎪','🎨'],
};
let currentEmojiCat = '😀';
let emojiPanelOpen = false;

function toggleChatEmoji(e){
  e.stopPropagation();
  let panel = document.getElementById('chatEmojiPanel');
  if(!panel){ buildEmojiPanel(); return; }
  emojiPanelOpen = !emojiPanelOpen;
  panel.classList.toggle('show', emojiPanelOpen);
  if(emojiPanelOpen) setTimeout(()=>document.addEventListener('click', closeEmojiOnOutside, {once:true}), 0);
}

function buildEmojiPanel(){
  const area = document.querySelector('.chat-input-area');
  const panel = document.createElement('div');
  panel.id = 'chatEmojiPanel';
  panel.className = 'chat-emoji-panel show';

  // Search
  const search = document.createElement('div');
  search.className = 'emoji-search';
  search.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:13px;height:13px;flex-shrink:0;color:var(--muted)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Tìm emoji...">';
  const searchInput = search.querySelector('input');
  searchInput.oninput = () => filterEmojis(searchInput.value, panel);
  panel.appendChild(search);

  // Tabs
  const tabs = document.createElement('div');
  tabs.className = 'emoji-tabs';
  Object.keys(EMOJI_CATS).forEach(cat => {
    const btn = document.createElement('button');
    btn.className = 'emoji-tab' + (cat===currentEmojiCat?' active':'');
    btn.textContent = cat;
    btn.onclick = (e)=>{ e.stopPropagation(); currentEmojiCat=cat; renderEmojiGrid(panel); tabs.querySelectorAll('.emoji-tab').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); };
    tabs.appendChild(btn);
  });
  panel.appendChild(tabs);

  const grid = document.createElement('div');
  grid.className = 'emoji-grid'; grid.id = 'emojiGrid';
  panel.appendChild(grid);

  area.appendChild(panel);
  renderEmojiGrid(panel);
  emojiPanelOpen = true;
  setTimeout(()=>document.addEventListener('click', closeEmojiOnOutside, {once:true}), 0);
}

function filterEmojis(q, panel){
  if(!q.trim()){ renderEmojiGrid(panel); return; }
  const grid = panel.querySelector('.emoji-grid');
  grid.innerHTML = '';
  const all = Object.values(EMOJI_CATS).flat();
  all.filter((em,i,a)=>a.indexOf(em)===i).forEach(em=>{
    const btn=document.createElement('button'); btn.className='emoji-cell'; btn.textContent=em;
    btn.onclick=(e)=>{e.stopPropagation();insertEmoji(em);};
    grid.appendChild(btn);
  });
}

function closeEmojiOnOutside(){
  const panel=document.getElementById('chatEmojiPanel');
  if(panel){panel.classList.remove('show');emojiPanelOpen=false;}
}
function renderEmojiGrid(panel){
  const grid=panel.querySelector('.emoji-grid'); grid.innerHTML='';
  (EMOJI_CATS[currentEmojiCat]||[]).forEach(em=>{
    const btn=document.createElement('button'); btn.className='emoji-cell'; btn.textContent=em;
    btn.onclick=(e)=>{e.stopPropagation();insertEmoji(em);};
    grid.appendChild(btn);
  });
}
function insertEmoji(em){
  const ta=document.getElementById('chatInput');
  const s=ta.selectionStart, e2=ta.selectionEnd;
  ta.value=ta.value.slice(0,s)+em+ta.value.slice(e2);
  ta.selectionStart=ta.selectionEnd=s+em.length;
  ta.focus(); ta.dispatchEvent(new Event('input'));
}

/* ══ MSG SEARCH ══ */
let searchMatches=[], searchIdx=0;
function toggleMsgSearch(){
  const bar=document.getElementById('msgSearchBar');
  const isShow=bar.classList.toggle('show');
  if(isShow) document.getElementById('msgSearchInput').focus();
  else clearSearch();
  document.getElementById('searchToggleBtn')?.classList.toggle('active',isShow);
}
function doMsgSearch(query){
  clearSearch(false);
  if(!query.trim()){document.getElementById('searchCount').textContent='';return;}
  const q=query.toLowerCase();
  const bubbles=document.querySelectorAll('#msgList .msg-bubble');
  searchMatches=[];
  bubbles.forEach(b=>{ if((b.textContent||'').toLowerCase().includes(q)){b.classList.add('msg-highlight');searchMatches.push(b);} });
  searchIdx=0;
  if(searchMatches.length){
    document.getElementById('searchCount').textContent='1/'+searchMatches.length;
    scrollToMatch(0);
  } else {
    document.getElementById('searchCount').textContent='0 kết quả';
  }
}
function scrollToMatch(idx){
  searchMatches.forEach((b,i)=>b.classList.toggle('msg-highlight-active',i===idx));
  searchMatches[idx]?.scrollIntoView({behavior:'smooth',block:'center'});
  document.getElementById('searchCount').textContent=(idx+1)+'/'+searchMatches.length;
}
function searchNav(dir){
  if(!searchMatches.length) return;
  searchIdx=(searchIdx+dir+searchMatches.length)%searchMatches.length;
  scrollToMatch(searchIdx);
}
function clearSearch(clearInput=true){
  searchMatches.forEach(b=>b.classList.remove('msg-highlight','msg-highlight-active'));
  searchMatches=[];
  if(clearInput){
    const inp=document.getElementById('msgSearchInput');
    if(inp) inp.value='';
    document.getElementById('searchCount').textContent='';
  }
}
</script>
</body>
</html>
