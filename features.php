<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid  = $_SESSION['user_id'];
$user = getCurrentUser();

// Run migrations
@$db->exec("ALTER TABLE users ADD COLUMN coins INTEGER DEFAULT 500");
@$db->exec("ALTER TABLE users ADD COLUMN verified INTEGER DEFAULT 0");
@$db->exec("ALTER TABLE users ADD COLUMN verify_type TEXT DEFAULT ''");
@$db->exec("ALTER TABLE users ADD COLUMN ai_name TEXT DEFAULT 'Spark'");

$db->exec("CREATE TABLE IF NOT EXISTS time_capsules (
    id INTEGER PRIMARY KEY AUTOINCREMENT, sender_id INTEGER NOT NULL,
    recipient_id INTEGER, title TEXT NOT NULL, content TEXT NOT NULL,
    image_data TEXT, unlock_at DATETIME NOT NULL, is_public INTEGER DEFAULT 0,
    opened INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS marketplace_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER NOT NULL,
    title TEXT NOT NULL, description TEXT, price INTEGER DEFAULT 0,
    category TEXT DEFAULT 'other', file_data TEXT, preview_data TEXT,
    status TEXT DEFAULT 'active', sales_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS marketplace_purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL,
    buyer_id INTEGER NOT NULL, price INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(item_id,buyer_id)
)");
$db->exec("CREATE TABLE IF NOT EXISTS virtual_gifts (
    id INTEGER PRIMARY KEY AUTOINCREMENT, sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL, post_id INTEGER, gift_type TEXT NOT NULL,
    coins_value INTEGER DEFAULT 0, message TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS user_locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL UNIQUE,
    lat REAL, lng REAL, visible_to TEXT DEFAULT 'friends',
    expires_at DATETIME, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS focus_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    duration_min INTEGER NOT NULL, goal TEXT, completed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS verify_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    selfie_data TEXT, status TEXT DEFAULT 'pending', admin_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS ai_reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    title TEXT NOT NULL, remind_at DATETIME NOT NULL, done INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    type TEXT NOT NULL, title TEXT, body TEXT, link TEXT,
    read_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ── AJAX handlers ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $a = $_POST['action'] ?? '';

    // ── TIME CAPSULE ──
    if ($a === 'create_capsule') {
        $title   = mb_substr(trim($_POST['title']??''),0,100);
        $content = trim($_POST['content']??'');
        $years   = max(1,min(10,(int)($_POST['years']??1)));
        $pub     = (int)($_POST['is_public']??0);
        if(!$title||!$content){echo json_encode(['ok'=>false,'msg'=>'Thiếu nội dung']);exit;}
        $unlockAt = date('Y-m-d H:i:s', strtotime("+$years years"));
        $st=$db->prepare('INSERT INTO time_capsules (sender_id,title,content,unlock_at,is_public) VALUES(:s,:t,:c,:u,:p)');
        $st->bindValue(':s',$uid);$st->bindValue(':t',$title);
        $st->bindValue(':c',$content);$st->bindValue(':u',$unlockAt);$st->bindValue(':p',$pub);
        $st->execute();
        echo json_encode(['ok'=>true,'unlock_at'=>$unlockAt]); exit;
    }

    if ($a === 'get_capsules') {
        $rows=$db->query("SELECT * FROM time_capsules WHERE sender_id=$uid ORDER BY created_at DESC");
        $out=[];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)){
            $unlocked = strtotime($r['unlock_at']) <= time();
            $out[]=['id'=>$r['id'],'title'=>htmlspecialchars($r['title']),
                'unlock_at'=>$r['unlock_at'],'unlocked'=>$unlocked,
                'content'=>$unlocked?htmlspecialchars($r['content']):'🔒',
                'is_public'=>$r['is_public']];
        }
        echo json_encode(['ok'=>true,'capsules'=>$out]); exit;
    }

    // ── MARKETPLACE ──
    if ($a === 'list_items') {
        $cat = $_POST['cat']??'';
        $q   = SQLite3::escapeString(trim($_POST['q']??''));
        $sql = "SELECT m.*,u.name as seller_name FROM marketplace_items m JOIN users u ON m.seller_id=u.id WHERE m.status='active'";
        if($cat) $sql.=" AND m.category='".SQLite3::escapeString($cat)."'";
        if($q)   $sql.=" AND (m.title LIKE '%$q%' OR m.description LIKE '%$q%')";
        $sql.=" ORDER BY m.created_at DESC LIMIT 30";
        $rows=$db->query($sql);$out=[];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)){
            $bought=(bool)$db->query("SELECT id FROM marketplace_purchases WHERE item_id={$r['id']} AND buyer_id=$uid")->fetchArray();
            $out[]=['id'=>$r['id'],'title'=>htmlspecialchars($r['title']),
                'description'=>htmlspecialchars(mb_substr($r['description']??'',0,100)),
                'price'=>$r['price'],'category'=>$r['category'],
                'seller'=>htmlspecialchars($r['seller_name']),'sales'=>$r['sales_count'],
                'preview'=>$r['preview_data'],'mine'=>$r['seller_id']==$uid,'bought'=>$bought];
        }
        echo json_encode(['ok'=>true,'items'=>$out]); exit;
    }

    if ($a === 'sell_item') {
        $title=$_POST['title']??''; $desc=$_POST['desc']??'';
        $price=max(0,(int)($_POST['price']??0));
        $cat  =$_POST['cat']??'document';
        $fileData=$_POST['file_data']??'';
        $prevData=$_POST['preview_data']??'';
        if(mb_strlen($title)<2){echo json_encode(['ok'=>false,'msg'=>'Tên quá ngắn']);exit;}
        $st=$db->prepare('INSERT INTO marketplace_items (seller_id,title,description,price,category,file_data,preview_data) VALUES(:s,:t,:d,:p,:c,:f,:pr)');
        $st->bindValue(':s',$uid);$st->bindValue(':t',$title);$st->bindValue(':d',$desc);
        $st->bindValue(':p',$price);$st->bindValue(':c',$cat);
        $st->bindValue(':f',$fileData?:null);$st->bindValue(':pr',$prevData?:null);
        $st->execute();
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertRowID()]); exit;
    }

    if ($a === 'buy_item') {
        $iid=(int)($_POST['item_id']??0);
        $item=$db->query("SELECT * FROM marketplace_items WHERE id=$iid AND status='active'")->fetchArray(SQLITE3_ASSOC);
        if(!$item){echo json_encode(['ok'=>false,'msg'=>'Sản phẩm không tồn tại']);exit;}
        if($item['seller_id']==$uid){echo json_encode(['ok'=>false,'msg'=>'Không tự mua của mình']);exit;}
        $coins=(int)$db->query("SELECT coins FROM users WHERE id=$uid")->fetchArray()['coins'];
        if($coins < $item['price']){echo json_encode(['ok'=>false,'msg'=>'Không đủ xu']);exit;}
        $ex=$db->query("SELECT id FROM marketplace_purchases WHERE item_id=$iid AND buyer_id=$uid")->fetchArray();
        if($ex){echo json_encode(['ok'=>false,'msg'=>'Đã mua rồi']);exit;}
        $db->exec("UPDATE users SET coins=coins-{$item['price']} WHERE id=$uid");
        $db->exec("UPDATE users SET coins=coins+{$item['price']} WHERE id={$item['seller_id']}");
        $st=$db->prepare('INSERT INTO marketplace_purchases (item_id,buyer_id,price) VALUES(:i,:b,:p)');
        $st->bindValue(':i',$iid);$st->bindValue(':b',$uid);$st->bindValue(':p',$item['price']);
        $st->execute();
        $db->exec("UPDATE marketplace_items SET sales_count=sales_count+1 WHERE id=$iid");
        echo json_encode(['ok'=>true,'file_data'=>$item['file_data'],'coins_left'=>$coins-$item['price']]); exit;
    }

    // ── VIRTUAL GIFTS ──
    if ($a === 'send_gift') {
        $to  =(int)($_POST['to']??0);
        $type=trim($_POST['gift_type']??'star');
        $msg =mb_substr(trim($_POST['message']??''),0,100);
        $pid =(int)($_POST['post_id']??0)?:null;
        $gifts=['star'=>10,'heart'=>25,'fire'=>50,'crown'=>100,'diamond'=>200,'rocket'=>500];
        $cost=$gifts[$type]??10;
        $coins=(int)$db->query("SELECT coins FROM users WHERE id=$uid")->fetchArray()['coins'];
        if($coins<$cost){echo json_encode(['ok'=>false,'msg'=>'Không đủ xu']);exit;}
        $db->exec("UPDATE users SET coins=coins-$cost WHERE id=$uid");
        $db->exec("UPDATE users SET coins=coins+$cost WHERE id=$to");
        $st=$db->prepare('INSERT INTO virtual_gifts (sender_id,recipient_id,post_id,gift_type,coins_value,message) VALUES(:s,:r,:p,:t,:c,:m)');
        $st->bindValue(':s',$uid);$st->bindValue(':r',$to);$st->bindValue(':p',$pid);
        $st->bindValue(':t',$type);$st->bindValue(':c',$cost);$st->bindValue(':m',$msg);
        $st->execute();
        echo json_encode(['ok'=>true,'coins_left'=>$coins-$cost]); exit;
    }

    if ($a === 'get_gifts') {
        $rows=$db->query("SELECT g.*,u.name as sender_name FROM virtual_gifts g JOIN users u ON g.sender_id=u.id WHERE g.recipient_id=$uid ORDER BY g.created_at DESC LIMIT 20");
        $out=[];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)){
            $out[]=['type'=>$r['gift_type'],'sender'=>htmlspecialchars($r['sender_name']),'msg'=>htmlspecialchars($r['message']??''),'time'=>$r['created_at'],'coins'=>$r['coins_value']];
        }
        echo json_encode(['ok'=>true,'gifts'=>$out]); exit;
    }

    // ── NEARBY FRIENDS ──
    if ($a === 'share_location') {
        $lat=(float)($_POST['lat']??0);
        $lng=(float)($_POST['lng']??0);
        $vis=$_POST['visible_to']??'friends';
        $dur=(int)($_POST['duration']??60);
        $exp=date('Y-m-d H:i:s',time()+$dur*60);
        $st=$db->prepare('INSERT OR REPLACE INTO user_locations (user_id,lat,lng,visible_to,expires_at,updated_at) VALUES(:u,:la,:ln,:v,:e,CURRENT_TIMESTAMP)');
        $st->bindValue(':u',$uid);$st->bindValue(':la',$lat);$st->bindValue(':ln',$lng);
        $st->bindValue(':v',$vis);$st->bindValue(':e',$exp);
        $st->execute();
        echo json_encode(['ok'=>true]); exit;
    }

    if ($a === 'get_nearby') {
        $rows=$db->query("SELECT ul.*,u.name,u.avatar FROM user_locations ul JOIN users u ON ul.user_id=u.id WHERE ul.expires_at>CURRENT_TIMESTAMP AND ul.user_id!=$uid");
        $out=[];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)){
            $out[]=['name'=>htmlspecialchars($r['name']),'lat'=>$r['lat'],'lng'=>$r['lng'],'expires'=>$r['expires_at']];
        }
        echo json_encode(['ok'=>true,'users'=>$out]); exit;
    }

    if ($a === 'stop_sharing') {
        $db->exec("DELETE FROM user_locations WHERE user_id=$uid");
        echo json_encode(['ok'=>true]); exit;
    }

    // ── FOCUS MODE ──
    if ($a === 'start_focus') {
        $dur=(int)($_POST['duration']??25);
        $goal=mb_substr(trim($_POST['goal']??''),0,200);
        $st=$db->prepare('INSERT INTO focus_sessions (user_id,duration_min,goal) VALUES(:u,:d,:g)');
        $st->bindValue(':u',$uid);$st->bindValue(':d',$dur);$st->bindValue(':g',$goal);
        $st->execute();
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertRowID()]); exit;
    }

    if ($a === 'end_focus') {
        $sid=(int)($_POST['session_id']??0);
        $db->exec("UPDATE focus_sessions SET completed=1 WHERE id=$sid AND user_id=$uid");
        echo json_encode(['ok'=>true]); exit;
    }

    // ── AI REMINDERS ──
    if ($a === 'add_reminder') {
        $title=mb_substr(trim($_POST['title']??''),0,200);
        $at=trim($_POST['remind_at']??'');
        if(!$title||!$at){echo json_encode(['ok'=>false]);exit;}
        $st=$db->prepare('INSERT INTO ai_reminders (user_id,title,remind_at) VALUES(:u,:t,:a)');
        $st->bindValue(':u',$uid);$st->bindValue(':t',$title);$st->bindValue(':a',$at);
        $st->execute();
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertRowID()]); exit;
    }

    if ($a === 'get_reminders') {
        $rows=$db->query("SELECT * FROM ai_reminders WHERE user_id=$uid AND done=0 ORDER BY remind_at ASC LIMIT 20");
        $out=[];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)){
            $out[]=['id'=>$r['id'],'title'=>htmlspecialchars($r['title']),'remind_at'=>$r['remind_at'],'overdue'=>strtotime($r['remind_at'])<time()];
        }
        echo json_encode(['ok'=>true,'reminders'=>$out]); exit;
    }

    if ($a === 'done_reminder') {
        $id=(int)($_POST['id']??0);
        $db->exec("UPDATE ai_reminders SET done=1 WHERE id=$id AND user_id=$uid");
        echo json_encode(['ok'=>true]); exit;
    }

    // ── VERIFY REQUEST ──
    if ($a === 'request_verify') {
        $selfie=trim($_POST['selfie_data']??'');
        $ex=$db->query("SELECT id,status FROM verify_requests WHERE user_id=$uid ORDER BY id DESC LIMIT 1")->fetchArray(SQLITE3_ASSOC);
        if($ex&&$ex['status']==='pending'){echo json_encode(['ok'=>false,'msg'=>'Đang chờ xét duyệt']);exit;}
        $st=$db->prepare('INSERT INTO verify_requests (user_id,selfie_data) VALUES(:u,:s)');
        $st->bindValue(':u',$uid);$st->bindValue(':s',$selfie?:null);
        $st->execute();
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
}

// Load stats
$coins = (int)($db->query("SELECT coins FROM users WHERE id=$uid")->fetchArray()['coins'] ?? 500);
$verified = (int)($user['verified'] ?? 0);
$pendingVerify = $db->query("SELECT id FROM verify_requests WHERE user_id=$uid AND status='pending'")->fetchArray();
$myItems = (int)$db->query("SELECT COUNT(*) as c FROM marketplace_items WHERE seller_id=$uid AND status='active'")->fetchArray()['c'];
$myPurchases = (int)$db->query("SELECT COUNT(*) as c FROM marketplace_purchases WHERE buyer_id=$uid")->fetchArray()['c'];
$myCapsules = (int)$db->query("SELECT COUNT(*) as c FROM time_capsules WHERE sender_id=$uid")->fetchArray()['c'];
$gifts_received = (int)$db->query("SELECT COUNT(*) as c FROM virtual_gifts WHERE recipient_id=$uid")->fetchArray()['c'];
$location_active = $db->query("SELECT id FROM user_locations WHERE user_id=$uid AND expires_at>CURRENT_TIMESTAMP")->fetchArray();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tính năng nâng cao — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
/* ══ FEATURES HUB ══ */
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
  padding: 0 0 40px;
}

.feat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  overflow: hidden;
  transition: transform .2s, box-shadow .2s;
  display: flex;
  flex-direction: column;
}
.feat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 40px rgba(0,0,0,.12);
}

.feat-card-header {
  padding: 20px 20px 14px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
}
.feat-icon {
  width: 52px; height: 52px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 24px; flex-shrink: 0;
}
.feat-icon.purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.feat-icon.blue   { background: linear-gradient(135deg, #0ea5e9, #3b82f6); }
.feat-icon.green  { background: linear-gradient(135deg, #10b981, #059669); }
.feat-icon.orange { background: linear-gradient(135deg, #f59e0b, #ef4444); }
.feat-icon.pink   { background: linear-gradient(135deg, #ec4899, #a855f7); }
.feat-icon.teal   { background: linear-gradient(135deg, #14b8a6, #0284c7); }
.feat-icon.red    { background: linear-gradient(135deg, #ef4444, #dc2626); }
.feat-icon.indigo { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
.feat-icon.gold   { background: linear-gradient(135deg, #f59e0b, #d97706); }
.feat-icon.slate  { background: linear-gradient(135deg, #475569, #334155); }

.feat-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
.feat-desc { font-size: 13px; color: var(--muted); line-height: 1.5; }
.feat-badge {
  margin-left: auto; padding: 3px 9px; border-radius: 20px;
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; flex-shrink: 0;
}
.feat-badge.new { background: rgba(16,185,129,.15); color: #10b981; }
.feat-badge.hot { background: rgba(239,68,68,.12); color: #ef4444; }
.feat-badge.pro { background: rgba(99,102,241,.12); color: #6366f1; }

.feat-body { padding: 0 20px 20px; flex: 1; display: flex; flex-direction: column; gap: 10px; }

/* Stat row */
.stat-row {
  display: flex; gap: 10px;
}
.stat-pill {
  flex: 1; background: var(--surface2);
  border-radius: 12px; padding: 10px 12px;
  text-align: center;
}
.stat-pill-val { font-size: 20px; font-weight: 800; color: var(--text); }
.stat-pill-lbl { font-size: 11px; color: var(--muted); margin-top: 2px; }

/* Coin display */
.coin-bar {
  display: flex; align-items: center; gap: 10px;
  background: linear-gradient(135deg, #f59e0b22, #d9770611);
  border: 1px solid rgba(245,158,11,.3);
  border-radius: 12px; padding: 12px 14px;
}
.coin-amount { font-size: 22px; font-weight: 800; color: #f59e0b; }
.coin-lbl { font-size: 12px; color: var(--muted); }

/* Feature action button */
.feat-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 11px; border-radius: 12px;
  background: var(--accent); color: #fff; border: none;
  cursor: pointer; font-family: var(--font); font-weight: 700;
  font-size: 14px; transition: all .15s; text-decoration: none;
}
.feat-btn:hover { background: var(--accent-hover); transform: scale(1.01); }
.feat-btn.secondary {
  background: var(--surface2); color: var(--text);
  border: 1px solid var(--border);
}
.feat-btn.secondary:hover { background: var(--border); }
.feat-btn.green { background: linear-gradient(135deg,#10b981,#059669); }
.feat-btn.orange { background: linear-gradient(135deg,#f59e0b,#ef4444); }
.feat-btn.pink { background: linear-gradient(135deg,#ec4899,#a855f7); }
.feat-btn.red { background: linear-gradient(135deg,#ef4444,#dc2626); }

/* Capsule list */
.capsule-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; background: var(--surface2);
  border-radius: 12px; border: 1px solid var(--border);
}
.capsule-lock {
  width: 36px; height: 36px; border-radius: 10px;
  background: var(--accent-soft); display: flex;
  align-items: center; justify-content: center;
  font-size: 16px; flex-shrink: 0;
}
.capsule-info { flex: 1; min-width: 0; }
.capsule-title { font-size: 13px; font-weight: 600; color: var(--text); }
.capsule-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }

/* Gift list */
.gift-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 12px; background: var(--surface2); border-radius: 12px;
}
.gift-icon { font-size: 24px; flex-shrink: 0; }
.gift-info { flex: 1; min-width: 0; }
.gift-from { font-size: 13px; font-weight: 600; color: var(--text); }
.gift-msg { font-size: 11px; color: var(--muted); }
.gift-coins { font-size: 12px; font-weight: 700; color: #f59e0b; flex-shrink: 0; }

/* Map area */
.map-box {
  width: 100%; height: 200px; border-radius: 12px;
  background: var(--surface2); border: 1px solid var(--border);
  overflow: hidden; position: relative;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; color: var(--muted);
}
.map-box canvas { position: absolute; inset: 0; width: 100%; height: 100%; }

/* Focus timer */
.focus-ring {
  width: 120px; height: 120px; border-radius: 50%;
  border: 8px solid var(--border2); position: relative;
  margin: 0 auto 12px;
  display: flex; align-items: center; justify-content: center;
}
.focus-ring svg { position: absolute; top: -8px; left: -8px; transform: rotate(-90deg); }
.focus-time { font-size: 28px; font-weight: 800; color: var(--text); font-family: var(--mono); }
.focus-goal { font-size: 12px; color: var(--muted); text-align: center; margin-bottom: 8px; }

/* Verify badge */
.verify-status {
  display: flex; align-items: center; gap: 10px;
  padding: 12px; background: var(--surface2);
  border-radius: 12px;
}
.verify-tick {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.verify-tick.ok { background: rgba(16,185,129,.15); }
.verify-tick.pending { background: rgba(245,158,11,.15); }
.verify-tick.none { background: var(--border); }

/* Market grid */
.market-grid {
  display: flex; flex-direction: column; gap: 8px;
  max-height: 280px; overflow-y: auto;
}
.market-grid::-webkit-scrollbar { width: 3px; }
.market-grid::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

.market-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; background: var(--surface2);
  border-radius: 12px; border: 1px solid var(--border);
}
.market-thumb {
  width: 40px; height: 40px; border-radius: 8px;
  background: var(--accent-soft); display: flex;
  align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0; overflow: hidden;
}
.market-thumb img { width: 100%; height: 100%; object-fit: cover; }
.market-info { flex: 1; min-width: 0; }
.market-name { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.market-seller { font-size: 11px; color: var(--muted); }
.market-price { font-size: 14px; font-weight: 700; color: #f59e0b; flex-shrink: 0; }

/* Reminder list */
.reminder-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; background: var(--surface2);
  border-radius: 12px;
}
.reminder-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.reminder-dot.ok { background: var(--accent); }
.reminder-dot.overdue { background: #ef4444; }
.reminder-title { font-size: 13px; font-weight: 600; color: var(--text); flex: 1; }
.reminder-time { font-size: 11px; color: var(--muted); }
.reminder-done-btn { background: none; border: none; cursor: pointer; font-size: 16px; padding: 0; }

/* Modal */
.modal-bg { position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px); }
.modal-bg.show { display:flex; }
.modal { background:var(--surface);border-radius:20px;padding:24px 22px;width:440px;max-width:94vw;max-height:85vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.3); }
.modal-title { font-size:17px;font-weight:700;margin-bottom:16px;color:var(--text); }
.form-label { font-size:11px;font-weight:800;color:var(--muted);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px; }
.form-input { width:100%;padding:10px 13px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:var(--font);font-size:14px;outline:none;box-sizing:border-box; }
.form-input:focus { border-color:var(--accent); }
textarea.form-input { resize:vertical;min-height:80px; }
.modal-actions { display:flex;gap:8px;margin-top:16px; }
.btn-primary { flex:1;padding:11px;border-radius:11px;background:var(--accent);color:#fff;border:none;cursor:pointer;font-weight:700;font-size:14px;font-family:var(--font); }
.btn-ghost { padding:11px 16px;border-radius:11px;background:var(--surface2);color:var(--text);border:1px solid var(--border);cursor:pointer;font-family:var(--font); }

/* Gift picker */
.gift-grid { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:4px; }
.gift-opt { text-align:center;cursor:pointer;padding:10px;border-radius:12px;border:2px solid transparent;transition:all .15s;background:var(--surface2); }
.gift-opt:hover,.gift-opt.sel { border-color:var(--accent);background:var(--accent-soft); }
.gift-opt .g-ico { font-size:28px;display:block;margin-bottom:4px; }
.gift-opt .g-cost { font-size:11px;font-weight:700;color:#f59e0b; }

/* Deep Focus overlay */
#focusOverlay {
  position: fixed; inset: 0; background: #0a0a0f;
  z-index: 800; display: none;
  flex-direction: column; align-items: center; justify-content: center;
  color: #fff;
}
#focusOverlay.show { display: flex; }
.fo-title { font-size: 14px; color: rgba(255,255,255,.5); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 8px; }
.fo-goal { font-size: 18px; font-weight: 600; margin-bottom: 40px; color: rgba(255,255,255,.8); }
.fo-ring { width: 200px; height: 200px; position: relative; margin-bottom: 32px; }
.fo-ring svg { position: absolute; top: 0; left: 0; transform: rotate(-90deg); }
.fo-time-big { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 44px; font-weight: 800; font-family: var(--mono, monospace); }
.fo-controls { display: flex; gap: 16px; }
.fo-btn { padding: 12px 24px; border-radius: 50px; border: none; cursor: pointer; font-size: 14px; font-weight: 700; font-family: var(--font); }
.fo-btn.primary { background: #6366f1; color: #fff; }
.fo-btn.ghost { background: rgba(255,255,255,.1); color: rgba(255,255,255,.8); }
.fo-ambient { position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 60%, #1a1040 0%, #0a0a0f 70%); pointer-events: none; }

/* Nearby map dots */
.nearby-dot {
  position: absolute;
  width: 12px; height: 12px; border-radius: 50%;
  background: #6366f1; border: 2px solid #fff;
  transform: translate(-50%,-50%);
  cursor: pointer;
}
.nearby-dot::after {
  content: attr(data-name);
  position: absolute; bottom: 16px; left: 50%;
  transform: translateX(-50%);
  background: #000; color: #fff;
  font-size: 10px; padding: 2px 6px; border-radius: 4px;
  white-space: nowrap; display: none;
}
.nearby-dot:hover::after { display: block; }
.nearby-me {
  position: absolute; width: 16px; height: 16px; border-radius: 50%;
  background: #10b981; border: 3px solid #fff;
  transform: translate(-50%,-50%);
}

/* Page header */
.page-heading {
  margin-bottom: 24px;
}
.page-heading h1 { font-size: 28px; font-weight: 800; letter-spacing: -1px; color: var(--text); }
.page-heading p { font-size: 14px; color: var(--muted); margin-top: 4px; }

.section-group {
  margin-bottom: 28px;
}
.section-group-label {
  font-size: 11px; font-weight: 800; color: var(--muted);
  text-transform: uppercase; letter-spacing: 1px;
  margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.section-group-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

@media(max-width:600px) {
  .features-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<!-- Deep Focus Overlay -->
<div id="focusOverlay">
  <div class="fo-ambient"></div>
  <div class="fo-title" style="position:relative">⚡ DEEP FOCUS MODE</div>
  <div class="fo-goal" id="foGoalText" style="position:relative">Tập trung</div>
  <div class="fo-ring" style="position:relative">
    <svg width="200" height="200" viewBox="0 0 200 200">
      <circle cx="100" cy="100" r="88" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="12"/>
      <circle cx="100" cy="100" r="88" fill="none" stroke="#6366f1" stroke-width="12"
              stroke-dasharray="553" stroke-dashoffset="0" id="foProgress" stroke-linecap="round"/>
    </svg>
    <div class="fo-time-big" id="foTimerDisplay">25:00</div>
  </div>
  <div class="fo-controls" style="position:relative">
    <button class="fo-btn ghost" onclick="endFocus()">Kết thúc</button>
    <button class="fo-btn primary" id="foPauseBtn" onclick="pauseFocus()">⏸ Tạm dừng</button>
  </div>
  <div style="position:relative;margin-top:20px;font-size:13px;color:rgba(255,255,255,.3);">Tất cả thông báo đã bị tắt</div>
</div>

<div class="page">
  <div class="page-heading">
    <h1>⚡ Tính năng nâng cao</h1>
    <p>10 tính năng mới giúp MindSpark trở thành siêu ứng dụng</p>
  </div>

  <!-- Coin bar -->
  <div class="coin-bar" style="margin-bottom:24px;max-width:360px;">
    <span style="font-size:24px;">🪙</span>
    <div>
      <div class="coin-amount" id="coinDisplay"><?=$coins?></div>
      <div class="coin-lbl">Xu MindSpark của bạn</div>
    </div>
    <div style="margin-left:auto;font-size:12px;color:var(--muted);">Dùng để mua, tặng quà</div>
  </div>

  <!-- AI & PERSONALIZATION -->
  <div class="section-group">
    <div class="section-group-label">🤖 AI & Cá nhân hóa</div>
    <div class="features-grid">

      <!-- 1. AI COMPANION -->
      <div class="feat-card">
        <div class="feat-card-header">
          <div class="feat-icon purple">🧠</div>
          <div>
            <div class="feat-title">Tri kỷ AI</div>
            <div class="feat-desc">Trợ lý AI riêng học phong cách của bạn, nhắc lịch và gợi ý thông minh</div>
          </div>
          <span class="feat-badge new">Mới</span>
        </div>
        <div class="feat-body">
          <div id="reminderList" style="display:flex;flex-direction:column;gap:6px;max-height:160px;overflow-y:auto;"></div>
          <button class="feat-btn" onclick="showModal('reminderModal')">
            ＋ Thêm nhắc nhở
          </button>
          <a href="chat.php" class="feat-btn secondary">Mở Gia sư AI →</a>
        </div>
      </div>

      <!-- 6. DEEP FOCUS -->
      <div class="feat-card">
        <div class="feat-card-header">
          <div class="feat-icon indigo">🎯</div>
          <div>
            <div class="feat-title">Chế độ Deep Focus</div>
            <div class="feat-desc">Ẩn mọi thông báo, toàn màn hình tối dịu, đồng hồ đếm ngược tập trung</div>
          </div>
          <span class="feat-badge hot">Hot</span>
        </div>
        <div class="feat-body">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:2px;">
            <?php foreach([25,45,60] as $m): ?>
            <button onclick="setFocusDuration(<?=$m?>)" class="feat-btn secondary" id="fdBtn<?=$m?>" style="padding:8px;font-size:13px;"><?=$m?> phút</button>
            <?php endforeach; ?>
          </div>
          <div style="margin-bottom:6px;">
            <input type="text" class="form-input" id="focusGoalInput" placeholder="Mục tiêu tập trung hôm nay..." style="font-size:13px;">
          </div>
          <button class="feat-btn orange" onclick="startFocusMode()">🚀 Bắt đầu Focus</button>
        </div>
      </div>

      <!-- 7. VERIFY BADGE -->
      <div class="feat-card">
        <div class="feat-card-header">
          <div class="feat-icon blue">✅</div>
          <div>
            <div class="feat-title">Tích xanh AI</div>
            <div class="feat-desc">Xác thực danh tính bằng AI để nhận tích xanh, tăng uy tín tài khoản</div>
          </div>
          <span class="feat-badge pro">Pro</span>
        </div>
        <div class="feat-body">
          <div class="verify-status">
            <?php if($verified): ?>
              <div class="verify-tick ok">✅</div>
              <div><div style="font-weight:700;font-size:14px;color:var(--text);">Đã xác thực</div><div style="font-size:12px;color:var(--muted);">Tài khoản của bạn đã có tích xanh</div></div>
            <?php elseif($pendingVerify): ?>
              <div class="verify-tick pending">⏳</div>
              <div><div style="font-weight:700;font-size:14px;color:var(--text);">Đang xét duyệt</div><div style="font-size:12px;color:var(--muted);">Yêu cầu đã gửi, chờ admin duyệt</div></div>
            <?php else: ?>
              <div class="verify-tick none">❓</div>
              <div><div style="font-weight:700;font-size:14px;color:var(--text);">Chưa xác thực</div><div style="font-size:12px;color:var(--muted);">Gửi ảnh selfie để xác minh</div></div>
            <?php endif; ?>
          </div>
          <?php if(!$verified && !$pendingVerify): ?>
          <button class="feat-btn blue" onclick="showModal('verifyModal')">📸 Gửi yêu cầu xác thực</button>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <!-- SOCIAL & GIFTS -->
  <div class="section-group">
    <div class="section-group-label">🎁 Xã hội & Quà tặng</div>
    <div class="features-grid">

      <!-- 9. VIRTUAL GIFTS -->
      <div class="feat-card">
        <div class="feat-card-header">
          <div class="feat-icon pink">🎁</div>
          <div>
            <div class="feat-title">Quà tặng ảo</div>
            <div class="feat-desc">Tặng icon đặc biệt cho bài viết hay, người nhận đổi thành xu thưởng</div>
          </div>
          <span class="feat-badge hot">Hot</span>
        </div>
        <div class="feat-body">
          <div class="stat-row">
            <div class="stat-pill"><div class="stat-pill-val"><?=$gifts_received?></div><div class="stat-pill-lbl">Quà nhận được</div></div>
            <div class="stat-pill"><div class="stat-pill-val" id="coinDisplay2"><?=$coins?></div><div class="stat-pill-lbl">Xu hiện có</div></div>
          </div>
          <div id="giftFeed" style="display:flex;flex-direction:column;gap:6px;max-height:140px;overflow-y:auto;"></div>
          <button class="feat-btn pink" onclick="showModal('giftModal')">🎁 Tặng quà cho bạn bè</button>
        </div>
      </div>

      <!-- 5. TIME CAPSULE -->
      <div class="feat-card">
        <div class="feat-card-header">
          <div class="feat-icon teal">⏳</div>
          <div>
            <div class="feat-title">Viên nang thời gian</div>
            <div class="feat-desc">Gửi tin nhắn cho tương lai, chỉ mở được sau 1–5 năm</div>
          </div>
          <span class="feat-badge new">Mới</span>
        </div>
        <div class="feat-body">
          <div class="stat-row" style="margin-bottom:2px;">
            <div class="stat-pill"><div class="stat-pill-val"><?=$myCapsules?></div><div class="stat-pill-lbl">Viên nang</div></div>
          </div>
          <div id="capsuleList" style="display:flex;flex-direction:column;gap:6px;max-height:150px;overflow-y:auto;"></div>
          <button class="feat-btn green" onclick="showModal('capsuleModal')">＋ Tạo viên nang mới</button>
        </div>
      </div>

      <!-- 10. NEARBY FRIENDS -->
      <div class="feat-card">
        <div class="feat-card-header">
          <div class="feat-icon slate">📍</div>
          <div>
            <div class="feat-title">Bạn bè quanh đây</div>
            <div class="feat-desc">Chia sẻ vị trí tạm thời để dễ hẹn gặp, tắt bất cứ lúc nào</div>
          </div>
        </div>
        <div class="feat-body">
          <div class="map-box" id="nearbyMap">
            <canvas id="nearbyCanvas"></canvas>
            <div id="nearbyEmpty" style="position:relative;z-index:1;">📍 Bật chia sẻ để thấy bạn bè</div>
          </div>
          <div id="nearbyStatus" style="font-size:12px;color:var(--muted);text-align:center;"></div>
          <?php if(!$location_active): ?>
          <button class="feat-btn" onclick="showModal('nearbyModal')">📍 Chia sẻ vị trí</button>
          <?php else: ?>
          <button class="feat-btn red" onclick="stopSharing()">⏹ Dừng chia sẻ vị trí</button>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <!-- ECONOMY -->
  <div class="section-group">
    <div class="section-group-label">🛒 Chợ ảo & Kinh tế</div>
    <div class="features-grid">

      <!-- 2. MARKETPLACE -->
      <div class="feat-card" style="grid-column: span 2;">
        <div class="feat-card-header">
          <div class="feat-icon orange">🛒</div>
          <div>
            <div class="feat-title">Chợ ảo MindSpark</div>
            <div class="feat-desc">Mua bán tài liệu học tập, preset, code bằng xu MindSpark</div>
          </div>
          <span class="feat-badge hot">Hot</span>
        </div>
        <div class="feat-body">
          <div class="stat-row" style="margin-bottom:8px;">
            <div class="stat-pill"><div class="stat-pill-val"><?=$myItems?></div><div class="stat-pill-lbl">Sản phẩm của bạn</div></div>
            <div class="stat-pill"><div class="stat-pill-val"><?=$myPurchases?></div><div class="stat-pill-lbl">Đã mua</div></div>
            <div class="stat-pill"><div class="stat-pill-val" style="color:#f59e0b"><?=$coins?></div><div class="stat-pill-lbl">Xu hiện có</div></div>
          </div>
          <div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
            <button onclick="loadMarket('all')" class="feat-btn secondary" style="flex:0;padding:7px 12px;font-size:12px;" id="mcAll">Tất cả</button>
            <button onclick="loadMarket('document')" class="feat-btn secondary" style="flex:0;padding:7px 12px;font-size:12px;">📚 Tài liệu</button>
            <button onclick="loadMarket('code')" class="feat-btn secondary" style="flex:0;padding:7px 12px;font-size:12px;">💻 Code</button>
            <button onclick="loadMarket('design')" class="feat-btn secondary" style="flex:0;padding:7px 12px;font-size:12px;">🎨 Thiết kế</button>
            <button onclick="loadMarket('other')" class="feat-btn secondary" style="flex:0;padding:7px 12px;font-size:12px;">📦 Khác</button>
          </div>
          <div class="market-grid" id="marketList"><div style="text-align:center;color:var(--muted);padding:20px;">Đang tải...</div></div>
          <div style="display:flex;gap:8px;margin-top:4px;">
            <button class="feat-btn green" onclick="showModal('sellModal')">＋ Đăng bán sản phẩm</button>
            <a href="community.php" class="feat-btn secondary" style="flex:0;white-space:nowrap;">Xem cộng đồng →</a>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>

<!-- ═══ MODALS ═══ -->

<!-- Reminder Modal -->
<div class="modal-bg" id="reminderModal" onclick="if(event.target===this)hideModal('reminderModal')">
  <div class="modal">
    <div class="modal-title">🔔 Thêm nhắc nhở</div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Nội dung nhắc nhở</label>
      <input type="text" id="remTitle" class="form-input" placeholder="Ví dụ: Ôn bài Toán, Sinh nhật bạn Minh...">
    </div>
    <div style="margin-bottom:4px;">
      <label class="form-label">Thời gian nhắc</label>
      <input type="datetime-local" id="remTime" class="form-input">
    </div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="addReminder()">Thêm nhắc nhở</button>
      <button class="btn-ghost" onclick="hideModal('reminderModal')">Huỷ</button>
    </div>
  </div>
</div>

<!-- Capsule Modal -->
<div class="modal-bg" id="capsuleModal" onclick="if(event.target===this)hideModal('capsuleModal')">
  <div class="modal">
    <div class="modal-title">⏳ Tạo viên nang thời gian</div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Tiêu đề</label>
      <input type="text" id="capTitle" class="form-input" placeholder="Gửi cho tương lai của mình...">
    </div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Nội dung (chỉ bạn mới đọc được vào ngày mở)</label>
      <textarea id="capContent" class="form-input" rows="4" placeholder="Viết gì đó cho tương lai của bạn..."></textarea>
    </div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Mở khóa sau</label>
      <div style="display:flex;gap:8px;">
        <?php foreach([1,2,5,10] as $y): ?>
        <button class="feat-btn secondary cap-yr" data-yr="<?=$y?>" onclick="setCapsuleYr(<?=$y?>,this)" style="flex:1;padding:8px;font-size:13px;"><?=$y?> năm</button>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
      <input type="checkbox" id="capPublic" style="width:16px;height:16px;">
      <label for="capPublic" style="font-size:13px;color:var(--muted);">Cho phép mọi người xem sau khi mở</label>
    </div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="createCapsule()">🔒 Niêm phong viên nang</button>
      <button class="btn-ghost" onclick="hideModal('capsuleModal')">Huỷ</button>
    </div>
  </div>
</div>

<!-- Gift Modal -->
<div class="modal-bg" id="giftModal" onclick="if(event.target===this)hideModal('giftModal')">
  <div class="modal">
    <div class="modal-title">🎁 Tặng quà ảo</div>
    <div style="margin-bottom:14px;">
      <label class="form-label">Chọn quà</label>
      <div class="gift-grid">
        <?php
        $giftTypes=[
          'star'=>['⭐','Sao','10'],
          'heart'=>['❤️','Tim','25'],
          'fire'=>['🔥','Lửa','50'],
          'crown'=>['👑','Vương miện','100'],
          'diamond'=>['💎','Kim cương','200'],
          'rocket'=>['🚀','Tên lửa','500'],
        ];
        foreach($giftTypes as $k=>[$ico,$name,$cost]):
        ?>
        <div class="gift-opt" data-gift="<?=$k?>" onclick="pickGift('<?=$k?>',this)">
          <span class="g-ico"><?=$ico?></span>
          <span style="font-size:11px;font-weight:600;display:block;"><?=$name?></span>
          <span class="g-cost">🪙<?=$cost?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Gửi đến (User ID)</label>
      <input type="number" id="giftTo" class="form-input" placeholder="Nhập ID người nhận (<?=$uid?> = bạn)">
    </div>
    <div style="margin-bottom:4px;">
      <label class="form-label">Lời nhắn (tuỳ chọn)</label>
      <input type="text" id="giftMsg" class="form-input" placeholder="Cảm ơn vì bài viết hay!">
    </div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="sendGift()">🎁 Gửi quà</button>
      <button class="btn-ghost" onclick="hideModal('giftModal')">Huỷ</button>
    </div>
  </div>
</div>

<!-- Nearby Modal -->
<div class="modal-bg" id="nearbyModal" onclick="if(event.target===this)hideModal('nearbyModal')">
  <div class="modal">
    <div class="modal-title">📍 Chia sẻ vị trí</div>
    <p style="font-size:13px;color:var(--muted);margin-bottom:14px;">Vị trí của bạn sẽ tự động ẩn sau thời gian chọn</p>
    <div style="margin-bottom:12px;">
      <label class="form-label">Chia sẻ trong bao lâu?</label>
      <div style="display:flex;gap:8px;">
        <?php foreach([30,60,120,240] as $m): ?>
        <button class="feat-btn secondary nrBtn" data-m="<?=$m?>" onclick="setNearbyDur(<?=$m?>,this)" style="flex:1;padding:8px;font-size:12px;"><?=$m>=60?($m/60).'h':$m.'p'?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="margin-bottom:4px;">
      <label class="form-label">Ai có thể thấy?</label>
      <select id="nearbyVis" class="form-input">
        <option value="everyone">Mọi người</option>
        <option value="friends" selected>Bạn bè</option>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="shareLocation()">📍 Bật chia sẻ</button>
      <button class="btn-ghost" onclick="hideModal('nearbyModal')">Huỷ</button>
    </div>
  </div>
</div>

<!-- Sell Modal -->
<div class="modal-bg" id="sellModal" onclick="if(event.target===this)hideModal('sellModal')">
  <div class="modal">
    <div class="modal-title">📦 Đăng bán sản phẩm</div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Tên sản phẩm</label>
      <input type="text" id="sellTitle" class="form-input" placeholder="Ví dụ: Tài liệu Toán 12 cực hay">
    </div>
    <div style="margin-bottom:12px;">
      <label class="form-label">Mô tả</label>
      <textarea id="sellDesc" class="form-input" rows="2" placeholder="Mô tả ngắn về sản phẩm..."></textarea>
    </div>
    <div style="display:flex;gap:10px;margin-bottom:12px;">
      <div style="flex:1;">
        <label class="form-label">Danh mục</label>
        <select id="sellCat" class="form-input">
          <option value="document">📚 Tài liệu</option>
          <option value="code">💻 Code</option>
          <option value="design">🎨 Thiết kế</option>
          <option value="other">📦 Khác</option>
        </select>
      </div>
      <div style="flex:1;">
        <label class="form-label">Giá (xu 🪙)</label>
        <input type="number" id="sellPrice" class="form-input" placeholder="0 = miễn phí" min="0" max="9999">
      </div>
    </div>
    <div style="margin-bottom:4px;">
      <label class="form-label">File (tuỳ chọn, <500KB)</label>
      <input type="file" id="sellFile" class="form-input" style="padding:6px;" accept=".pdf,.doc,.docx,.zip,.txt,.png,.jpg">
    </div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="sellItem()">📦 Đăng bán</button>
      <button class="btn-ghost" onclick="hideModal('sellModal')">Huỷ</button>
    </div>
  </div>
</div>

<!-- Verify Modal -->
<div class="modal-bg" id="verifyModal" onclick="if(event.target===this)hideModal('verifyModal')">
  <div class="modal">
    <div class="modal-title">✅ Xác thực danh tính</div>
    <p style="font-size:13px;color:var(--muted);margin-bottom:14px;">Chụp ảnh selfie để gửi yêu cầu xác thực. Admin sẽ xem xét trong 24h.</p>
    <div style="margin-bottom:12px;text-align:center;">
      <video id="verifyVideo" style="width:100%;max-height:200px;border-radius:12px;background:#000;display:none;" autoplay></video>
      <canvas id="verifyCanvas" style="display:none;"></canvas>
      <img id="verifyPreview" style="width:100%;max-height:200px;object-fit:cover;border-radius:12px;display:none;" alt="">
      <div id="verifyCamPlaceholder" style="padding:40px;background:var(--surface2);border-radius:12px;color:var(--muted);font-size:14px;cursor:pointer;" onclick="startCamera()">
        📷 Nhấn để mở camera
      </div>
    </div>
    <div id="verifyCamBtns" style="display:none;margin-bottom:12px;">
      <button class="feat-btn" onclick="takeSnap()">📸 Chụp ảnh</button>
    </div>
    <div class="modal-actions">
      <button class="btn-primary" id="verifySubmitBtn" onclick="submitVerify()" disabled>Gửi yêu cầu</button>
      <button class="btn-ghost" onclick="hideModal('verifyModal');stopCamera()">Huỷ</button>
    </div>
  </div>
</div>

<script>
const MY_UID = <?=$uid?>;
let coins = <?=$coins?>;
let focusDuration = 25;
let focusSessionId = null;
let focusTimer = null;
let focusPaused = false;
let focusSecondsLeft = 25 * 60;
let capsuleYears = 1;
let nearbyDur = 60;
let selectedGift = 'star';
let cameraStream = null;
let capturedSelfie = null;

// ── UTILS ──
function showModal(id) { document.getElementById(id).classList.add('show'); }
function hideModal(id) { document.getElementById(id).classList.remove('show'); }
function showToast(msg, type='ok') {
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;bottom:80px;right:20px;z-index:9999;padding:12px 18px;border-radius:12px;font-size:14px;font-weight:600;color:#fff;background:${type==='ok'?'#10b981':'#ef4444'};box-shadow:0 4px 20px rgba(0,0,0,.2);transition:opacity .3s;`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2500);
}
function updateCoins(n) {
  coins = n;
  document.querySelectorAll('[id^=coinDisplay]').forEach(el => el.textContent = n);
}

// ── REMINDERS ──
async function loadReminders() {
  const fd = new FormData(); fd.append('action','get_reminders');
  const r = await fetch('features.php',{method:'POST',body:fd});
  const d = await r.json();
  const list = document.getElementById('reminderList');
  list.innerHTML = '';
  if(!d.reminders.length) { list.innerHTML='<div style="font-size:12px;color:var(--muted);text-align:center;padding:10px;">Chưa có nhắc nhở nào</div>'; return; }
  d.reminders.forEach(rem => {
    const div = document.createElement('div');
    div.className = 'reminder-item';
    div.innerHTML = `<div class="reminder-dot ${rem.overdue?'overdue':'ok'}"></div>
      <div class="reminder-title">${rem.title}</div>
      <div class="reminder-time">${new Date(rem.remind_at).toLocaleString('vi')}</div>
      <button class="reminder-done-btn" onclick="doneReminder(${rem.id},this)">✓</button>`;
    list.appendChild(div);
  });
}

async function addReminder() {
  const title = document.getElementById('remTitle').value.trim();
  const at    = document.getElementById('remTime').value;
  if(!title||!at){showToast('Vui lòng điền đầy đủ','err');return;}
  const fd=new FormData(); fd.append('action','add_reminder'); fd.append('title',title); fd.append('remind_at',at);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){hideModal('reminderModal');loadReminders();showToast('✅ Đã thêm nhắc nhở!');}
}

async function doneReminder(id, btn) {
  const fd=new FormData(); fd.append('action','done_reminder'); fd.append('id',id);
  await fetch('features.php',{method:'POST',body:fd});
  btn.closest('.reminder-item').style.opacity='0';
  setTimeout(()=>btn.closest('.reminder-item').remove(),300);
}

// ── DEEP FOCUS ──
function setFocusDuration(m) {
  focusDuration = m;
  document.querySelectorAll('[id^=fdBtn]').forEach(b=>b.classList.remove('active'));
  document.getElementById('fdBtn'+m)?.classList.add('active');
}

async function startFocusMode() {
  const goal = document.getElementById('focusGoalInput').value.trim() || 'Tập trung học tập';
  const fd=new FormData(); fd.append('action','start_focus');
  fd.append('duration',focusDuration); fd.append('goal',goal);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok) {
    focusSessionId = d.id;
    focusSecondsLeft = focusDuration * 60;
    focusPaused = false;
    document.getElementById('foGoalText').textContent = goal;
    document.getElementById('focusOverlay').classList.add('show');
    updateFocusDisplay();
    focusTimer = setInterval(tickFocus, 1000);
  }
}

function tickFocus() {
  if(focusPaused) return;
  focusSecondsLeft--;
  updateFocusDisplay();
  if(focusSecondsLeft <= 0) {
    clearInterval(focusTimer);
    endFocus(true);
  }
}

function updateFocusDisplay() {
  const m = Math.floor(focusSecondsLeft / 60).toString().padStart(2,'0');
  const s = (focusSecondsLeft % 60).toString().padStart(2,'0');
  document.getElementById('foTimerDisplay').textContent = `${m}:${s}`;
  const total = focusDuration * 60;
  const pct = focusSecondsLeft / total;
  const dash = 553;
  document.getElementById('foProgress').style.strokeDashoffset = dash * pct;
}

function pauseFocus() {
  focusPaused = !focusPaused;
  document.getElementById('foPauseBtn').textContent = focusPaused ? '▶ Tiếp tục' : '⏸ Tạm dừng';
}

async function endFocus(completed=false) {
  clearInterval(focusTimer);
  document.getElementById('focusOverlay').classList.remove('show');
  if(focusSessionId) {
    const fd=new FormData(); fd.append('action','end_focus'); fd.append('session_id',focusSessionId);
    await fetch('features.php',{method:'POST',body:fd});
    if(completed) showToast('🎉 Hoàn thành phiên tập trung!');
    focusSessionId = null;
  }
}

// ── TIME CAPSULE ──
function setCapsuleYr(y, el) {
  capsuleYears = y;
  document.querySelectorAll('.cap-yr').forEach(b=>b.classList.remove('active','secondary'));
  document.querySelectorAll('.cap-yr').forEach(b=>b.classList.add('secondary'));
  el.classList.remove('secondary'); el.classList.add('active');
  el.style.background='var(--accent)'; el.style.color='#fff';
}

async function createCapsule() {
  const title = document.getElementById('capTitle').value.trim();
  const content = document.getElementById('capContent').value.trim();
  const isPub = document.getElementById('capPublic').checked?1:0;
  if(!title||!content){showToast('Vui lòng điền đầy đủ','err');return;}
  const fd=new FormData(); fd.append('action','create_capsule');
  fd.append('title',title); fd.append('content',content);
  fd.append('years',capsuleYears); fd.append('is_public',isPub);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){hideModal('capsuleModal');loadCapsules();showToast('🔒 Viên nang đã được niêm phong đến '+new Date(d.unlock_at).getFullYear()+'!');}
  else showToast(d.msg||'Lỗi','err');
}

async function loadCapsules() {
  const fd=new FormData(); fd.append('action','get_capsules');
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  const list=document.getElementById('capsuleList'); list.innerHTML='';
  if(!d.capsules.length){list.innerHTML='<div style="font-size:12px;color:var(--muted);text-align:center;padding:10px;">Chưa có viên nang nào</div>';return;}
  d.capsules.forEach(c=>{
    const div=document.createElement('div'); div.className='capsule-item';
    const date=new Date(c.unlock_at);
    div.innerHTML=`<div class="capsule-lock">${c.unlocked?'📬':'🔒'}</div>
      <div class="capsule-info">
        <div class="capsule-title">${c.title}</div>
        <div class="capsule-meta">${c.unlocked?'✅ Có thể đọc':'🔒 Mở vào '+date.toLocaleDateString('vi')}</div>
      </div>`;
    list.appendChild(div);
  });
}

// ── MARKETPLACE ──
async function loadMarket(cat='') {
  const fd=new FormData(); fd.append('action','list_items'); if(cat&&cat!=='all') fd.append('cat',cat);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  const list=document.getElementById('marketList'); list.innerHTML='';
  const catIcons={document:'📚',code:'💻',design:'🎨',other:'📦'};
  if(!d.items.length){list.innerHTML='<div style="text-align:center;color:var(--muted);padding:20px;font-size:13px;">Chưa có sản phẩm nào</div>';return;}
  d.items.forEach(item=>{
    const div=document.createElement('div'); div.className='market-item';
    const ico=catIcons[item.category]||'📦';
    div.innerHTML=`<div class="market-thumb">${item.preview?`<img src="${item.preview}" alt="">`:ico}</div>
      <div class="market-info">
        <div class="market-name">${item.title}</div>
        <div class="market-seller">bởi ${item.seller} · ${item.sales} lượt mua</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
        <div class="market-price">🪙${item.price||'Miễn phí'}</div>
        ${item.mine?'<span style="font-size:11px;color:var(--muted);">Của bạn</span>':
          item.bought?'<span style="font-size:11px;color:#10b981;">✅ Đã mua</span>':
          `<button onclick="buyItem(${item.id},${item.price})" style="padding:4px 10px;border-radius:8px;background:var(--accent);color:#fff;border:none;cursor:pointer;font-size:12px;font-weight:700;">Mua</button>`}
      </div>`;
    list.appendChild(div);
  });
}

async function buyItem(id, price) {
  if(!confirm(`Mua với giá 🪙${price}?`)) return;
  const fd=new FormData(); fd.append('action','buy_item'); fd.append('item_id',id);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){showToast('✅ Mua thành công!');updateCoins(d.coins_left);loadMarket();}
  else showToast(d.msg||'Lỗi mua hàng','err');
}

async function sellItem() {
  const title=document.getElementById('sellTitle').value.trim();
  const desc=document.getElementById('sellDesc').value.trim();
  const price=parseInt(document.getElementById('sellPrice').value)||0;
  const cat=document.getElementById('sellCat').value;
  if(!title){showToast('Vui lòng nhập tên sản phẩm','err');return;}
  let fileData='', prevData='';
  const file=document.getElementById('sellFile').files[0];
  if(file){
    if(file.size>500000){showToast('File quá lớn (tối đa 500KB)','err');return;}
    fileData=await toBase64(file);
    if(file.type.startsWith('image/')) prevData=fileData;
  }
  const fd=new FormData(); fd.append('action','sell_item');
  fd.append('title',title); fd.append('desc',desc);
  fd.append('price',price); fd.append('cat',cat);
  fd.append('file_data',fileData); fd.append('preview_data',prevData);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){hideModal('sellModal');loadMarket();showToast('✅ Đăng bán thành công!');}
  else showToast(d.msg||'Lỗi','err');
}

function toBase64(file) {
  return new Promise(res=>{
    const fr=new FileReader(); fr.onload=e=>res(e.target.result); fr.readAsDataURL(file);
  });
}

// ── VIRTUAL GIFTS ──
function pickGift(type, el) {
  selectedGift=type;
  document.querySelectorAll('.gift-opt').forEach(e=>e.classList.remove('sel'));
  el.classList.add('sel');
}

async function sendGift() {
  const to=parseInt(document.getElementById('giftTo').value);
  const msg=document.getElementById('giftMsg').value.trim();
  if(!to){showToast('Nhập ID người nhận','err');return;}
  const fd=new FormData(); fd.append('action','send_gift');
  fd.append('to',to); fd.append('gift_type',selectedGift); fd.append('message',msg);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){hideModal('giftModal');updateCoins(d.coins_left);showToast('🎁 Đã gửi quà!');loadGifts();}
  else showToast(d.msg||'Lỗi','err');
}

async function loadGifts() {
  const fd=new FormData(); fd.append('action','get_gifts');
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  const feed=document.getElementById('giftFeed'); feed.innerHTML='';
  const gicons={star:'⭐',heart:'❤️',fire:'🔥',crown:'👑',diamond:'💎',rocket:'🚀'};
  d.gifts.slice(0,5).forEach(g=>{
    const div=document.createElement('div'); div.className='gift-item';
    div.innerHTML=`<div class="gift-icon">${gicons[g.type]||'🎁'}</div>
      <div class="gift-info"><div class="gift-from">${g.sender}</div><div class="gift-msg">${g.msg||'Không có lời nhắn'}</div></div>
      <div class="gift-coins">+🪙${g.coins}</div>`;
    feed.appendChild(div);
  });
  if(!d.gifts.length) feed.innerHTML='<div style="font-size:12px;color:var(--muted);text-align:center;padding:8px;">Chưa nhận được quà nào</div>';
}

// ── NEARBY ──
let nearbyDuration = 60;
function setNearbyDur(m, el) {
  nearbyDuration = m;
  document.querySelectorAll('.nrBtn').forEach(b=>b.style.cssText='');
  el.style.background='var(--accent)'; el.style.color='#fff';
}

async function shareLocation() {
  if(!navigator.geolocation){showToast('Trình duyệt không hỗ trợ vị trí','err');return;}
  navigator.geolocation.getCurrentPosition(async pos=>{
    const fd=new FormData(); fd.append('action','share_location');
    fd.append('lat',pos.coords.latitude); fd.append('lng',pos.coords.longitude);
    fd.append('duration',nearbyDuration);
    fd.append('visible_to',document.getElementById('nearbyVis').value);
    const r=await fetch('features.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){hideModal('nearbyModal');showToast('📍 Đang chia sẻ vị trí!');loadNearby();}
  },()=>showToast('Không lấy được vị trí','err'));
}

async function stopSharing() {
  const fd=new FormData(); fd.append('action','stop_sharing');
  await fetch('features.php',{method:'POST',body:fd});
  showToast('Đã tắt chia sẻ vị trí');
  document.getElementById('nearbyEmpty').style.display='';
}

async function loadNearby() {
  const fd=new FormData(); fd.append('action','get_nearby');
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  const canvas=document.getElementById('nearbyCanvas');
  const empty=document.getElementById('nearbyEmpty');
  if(!d.users.length){empty.style.display='';return;}
  empty.style.display='none';
  const ctx=canvas.getContext('2d');
  canvas.width=canvas.parentElement.offsetWidth;
  canvas.height=200;
  ctx.fillStyle=getComputedStyle(document.documentElement).getPropertyValue('--surface2').trim()||'#f1f5f9';
  ctx.fillRect(0,0,canvas.width,canvas.height);
  // Draw grid lines
  ctx.strokeStyle='rgba(128,128,128,.1)'; ctx.lineWidth=1;
  for(let i=0;i<canvas.width;i+=40){ctx.beginPath();ctx.moveTo(i,0);ctx.lineTo(i,200);ctx.stroke();}
  for(let j=0;j<200;j+=40){ctx.beginPath();ctx.moveTo(0,j);ctx.lineTo(canvas.width,j);ctx.stroke();}
  // Draw users as dots
  d.users.forEach((u,i)=>{
    const x=50+(i*80)%(canvas.width-100)+50;
    const y=50+Math.floor((i*80)/(canvas.width-100))*60;
    ctx.beginPath(); ctx.arc(x,y,8,0,Math.PI*2);
    ctx.fillStyle='#6366f1'; ctx.fill();
    ctx.fillStyle=getComputedStyle(document.documentElement).getPropertyValue('--text').trim()||'#111';
    ctx.font='11px sans-serif'; ctx.textAlign='center';
    ctx.fillText(u.name.slice(0,8),x,y-14);
  });
  // Me dot in center
  ctx.beginPath(); ctx.arc(canvas.width/2,100,10,0,Math.PI*2);
  ctx.fillStyle='#10b981'; ctx.fill();
  ctx.fillStyle='white'; ctx.font='bold 10px sans-serif'; ctx.textAlign='center';
  ctx.fillText('Bạn',canvas.width/2,100+4);
  document.getElementById('nearbyStatus').textContent=d.users.length+' người dùng đang chia sẻ vị trí';
}

// ── VERIFY / CAMERA ──
async function startCamera() {
  try {
    cameraStream=await navigator.mediaDevices.getUserMedia({video:true});
    const video=document.getElementById('verifyVideo');
    video.srcObject=cameraStream; video.style.display='block';
    document.getElementById('verifyCamPlaceholder').style.display='none';
    document.getElementById('verifyCamBtns').style.display='block';
  } catch(e){showToast('Không thể mở camera','err');}
}

function takeSnap() {
  const video=document.getElementById('verifyVideo');
  const canvas=document.getElementById('verifyCanvas');
  canvas.width=video.videoWidth; canvas.height=video.videoHeight;
  canvas.getContext('2d').drawImage(video,0,0);
  capturedSelfie=canvas.toDataURL('image/jpeg',0.7);
  const preview=document.getElementById('verifyPreview');
  preview.src=capturedSelfie; preview.style.display='block';
  video.style.display='none';
  document.getElementById('verifyCamBtns').style.display='none';
  document.getElementById('verifySubmitBtn').disabled=false;
  stopCamera();
}

function stopCamera() {
  if(cameraStream){cameraStream.getTracks().forEach(t=>t.stop());cameraStream=null;}
}

async function submitVerify() {
  const fd=new FormData(); fd.append('action','request_verify');
  if(capturedSelfie) fd.append('selfie_data',capturedSelfie);
  const r=await fetch('features.php',{method:'POST',body:fd});
  const d=await r.json();
  if(d.ok){hideModal('verifyModal');showToast('✅ Yêu cầu đã gửi! Admin sẽ xét duyệt trong 24h');}
  else showToast(d.msg||'Lỗi','err');
}

// ── INIT ──
document.addEventListener('DOMContentLoaded',()=>{
  loadReminders();
  loadCapsules();
  loadMarket();
  loadGifts();
  setFocusDuration(25);
});
</script>
</body>
</html>
