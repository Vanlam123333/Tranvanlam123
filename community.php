<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid  = $_SESSION['user_id'];
$user = getCurrentUser();

// Migrate reaction columns
@$db->exec("ALTER TABLE post_likes ADD COLUMN reaction TEXT DEFAULT 'like'");
@$db->exec("ALTER TABLE post_comments ADD COLUMN image_data TEXT");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'post') {
        $content = trim($_POST['content'] ?? '');
        $imgData = $_POST['image_data'] ?? '';
        if (!$content && !$imgData) { echo json_encode(['ok'=>false,'msg'=>'Nội dung trống']); exit; }
        $st = $db->prepare('INSERT INTO social_posts (user_id,content,image_data) VALUES(:u,:c,:i)');
        $st->bindValue(':u',$uid); $st->bindValue(':c',$content);
        $st->bindValue(':i', $imgData ?: null);
        $st->execute();
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertRowID()]); exit;
    }

    if ($action === 'delete_post') {
        $pid = (int)($_POST['post_id']??0);
        $own = $db->query("SELECT user_id FROM social_posts WHERE id=$pid")->fetchArray();
        if ($own && $own['user_id']==$uid) {
            $db->exec("DELETE FROM social_posts WHERE id=$pid");
            $db->exec("DELETE FROM post_likes WHERE post_id=$pid");
            $db->exec("DELETE FROM post_comments WHERE post_id=$pid");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'react') {
        $pid     = (int)($_POST['post_id']??0);
        $reaction = $_POST['reaction'] ?? 'like';
        $ex = $db->query("SELECT id,reaction FROM post_likes WHERE post_id=$pid AND user_id=$uid")->fetchArray(SQLITE3_ASSOC);
        if ($ex) {
            if ($ex['reaction'] === $reaction) {
                // Remove reaction
                $db->exec("DELETE FROM post_likes WHERE post_id=$pid AND user_id=$uid");
                $db->exec("UPDATE social_posts SET likes_count=MAX(0,likes_count-1) WHERE id=$pid");
                $myReaction = null;
            } else {
                // Change reaction
                $db->exec("UPDATE post_likes SET reaction='".SQLite3::escapeString($reaction)."' WHERE post_id=$pid AND user_id=$uid");
                $myReaction = $reaction;
            }
        } else {
            $st=$db->prepare('INSERT INTO post_likes (post_id,user_id,reaction) VALUES(:p,:u,:r)');
            $st->bindValue(':p',$pid);$st->bindValue(':u',$uid);$st->bindValue(':r',$reaction);
            $st->execute();
            $db->exec("UPDATE social_posts SET likes_count=likes_count+1 WHERE id=$pid");
            $myReaction = $reaction;
        }
        // Get reaction counts
        $cnt = (int)$db->query("SELECT COUNT(*) as c FROM post_likes WHERE post_id=$pid")->fetchArray()['c'];
        $topR = $db->query("SELECT reaction, COUNT(*) as c FROM post_likes WHERE post_id=$pid GROUP BY reaction ORDER BY c DESC LIMIT 3");
        $top = [];
        while($r=$topR->fetchArray(SQLITE3_ASSOC)) $top[]=$r['reaction'];
        echo json_encode(['ok'=>true,'count'=>$cnt,'my_reaction'=>$myReaction,'top'=>$top]); exit;
    }

    if ($action === 'comment') {
        $pid     = (int)($_POST['post_id']??0);
        $content = trim($_POST['content']??'');
        $imgData = $_POST['image_data'] ?? '';
        if ((!$content && !$imgData) || !$pid) { echo json_encode(['ok'=>false]); exit; }
        $st = $db->prepare('INSERT INTO post_comments (post_id,user_id,content,image_data) VALUES(:p,:u,:c,:i)');
        $st->bindValue(':p',$pid);$st->bindValue(':u',$uid);$st->bindValue(':c',$content);$st->bindValue(':i',$imgData?:null);
        $st->execute();
        $db->exec("UPDATE social_posts SET comments_count=comments_count+1 WHERE id=$pid");
        $newId = $db->lastInsertRowID();
        $cm = $db->query("SELECT c.*,u.name,u.avatar FROM post_comments c JOIN users u ON c.user_id=u.id WHERE c.id=$newId")->fetchArray(SQLITE3_ASSOC);
        echo json_encode(['ok'=>true,'comment'=>[
            'id'=>$cm['id'],'content'=>htmlspecialchars($cm['content']),
            'image_data'=>$cm['image_data']??'',
            'user'=>htmlspecialchars($cm['name']),'avatar'=>userAvatar($cm,34),
            'time'=>timeAgo($cm['created_at']),'mine'=>true,
        ]]); exit;
    }

    if ($action === 'delete_comment') {
        $cid = (int)($_POST['comment_id']??0);
        $cm  = $db->query("SELECT * FROM post_comments WHERE id=$cid")->fetchArray(SQLITE3_ASSOC);
        if ($cm && $cm['user_id']==$uid) {
            $db->exec("DELETE FROM post_comments WHERE id=$cid");
            $db->exec("UPDATE social_posts SET comments_count=MAX(0,comments_count-1) WHERE id={$cm['post_id']}");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'load_comments') {
        $pid  = (int)($_POST['post_id']??0);
        $rows = $db->query("SELECT c.*,u.name,u.avatar FROM post_comments c JOIN users u ON c.user_id=u.id WHERE c.post_id=$pid ORDER BY c.created_at ASC LIMIT 50");
        $out  = [];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)) {
            $out[] = ['id'=>$r['id'],'content'=>htmlspecialchars($r['content']),
                'image_data'=>$r['image_data']??'',
                'user'=>htmlspecialchars($r['name']),'avatar'=>userAvatar($r,34),
                'time'=>timeAgo($r['created_at']),'mine'=>$r['user_id']==$uid];
        }
        echo json_encode(['ok'=>true,'comments'=>$out]); exit;
    }
    echo json_encode(['ok'=>false]); exit;
}

$offset = (int)($_GET['offset']??0);
$posts_q = $db->query("SELECT p.*,u.name,u.avatar FROM social_posts p JOIN users u ON p.user_id=u.id ORDER BY p.created_at DESC LIMIT 15 OFFSET $offset");
$posts = [];
while ($r=$posts_q->fetchArray(SQLITE3_ASSOC)) {
    $r['my_reaction'] = null;
    $rx = $db->query("SELECT reaction FROM post_likes WHERE post_id={$r['id']} AND user_id=$uid")->fetchArray(SQLITE3_ASSOC);
    if ($rx) $r['my_reaction'] = $rx['reaction'];
    $topR = $db->query("SELECT reaction FROM post_likes WHERE post_id={$r['id']} GROUP BY reaction ORDER BY COUNT(*) DESC LIMIT 3");
    $r['top_reactions'] = [];
    while($t=$topR->fetchArray(SQLITE3_ASSOC)) $r['top_reactions'][]=$t['reaction'];
    $r['mine'] = $r['user_id']==$uid;
    $posts[] = $r;
}

$REACTIONS = ['like'=>'👍','love'=>'❤️','haha'=>'😂','wow'=>'😮','sad'=>'😢','angry'=>'😡'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cộng đồng — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
:root { --fb-blue:#1877f2; }

.feed-wrap{max-width:600px;margin:0 auto;padding-bottom:40px;}

/* ── Compose ── */
.compose-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;
  padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.compose-top{display:flex;gap:10px;align-items:center;margin-bottom:10px;}
.compose-fake-input{flex:1;padding:10px 16px;background:var(--surface2);border:1.5px solid var(--border);
  border-radius:30px;font-size:14px;color:var(--muted);cursor:pointer;transition:all .15s;font-family:var(--font);}
.compose-fake-input:hover{background:var(--surface);border-color:var(--accent);}
.compose-divider{height:1px;background:var(--border);margin:10px 0;}
.compose-tools{display:flex;gap:4px;}
.compose-tool{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;
  border:none;background:none;cursor:pointer;font-family:var(--font);font-size:12px;font-weight:700;
  color:var(--muted);transition:all .15s;flex:1;justify-content:center;}
.compose-tool:hover{background:var(--surface2);color:var(--text);}
.compose-tool .ti{font-size:18px;}

/* Compose modal */
.compose-modal{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;
  display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:16px;}
.compose-modal.show{display:flex;}
.compose-modal-box{background:var(--surface);border-radius:16px;width:520px;max-width:100%;
  max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.25);}
.compose-modal-header{display:flex;align-items:center;justify-content:center;padding:16px;
  border-bottom:1px solid var(--border);position:relative;}
.compose-modal-title{font-size:16px;font-weight:800;color:var(--text);}
.compose-modal-close{position:absolute;right:16px;width:32px;height:32px;border-radius:50%;
  background:var(--surface2);border:none;cursor:pointer;font-size:18px;color:var(--muted);
  display:flex;align-items:center;justify-content:center;transition:background .15s;}
.compose-modal-close:hover{background:var(--border);}
.compose-modal-user{display:flex;align-items:center;gap:10px;padding:14px 16px;}
.compose-modal-name{font-size:14px;font-weight:800;color:var(--text);}
.compose-textarea{width:100%;border:none;outline:none;background:transparent;font-family:var(--font);
  font-size:16px;color:var(--text);resize:none;padding:0 16px 12px;min-height:80px;line-height:1.6;}
.compose-img-preview{margin:0 16px 12px;position:relative;}
.compose-img-preview img{width:100%;border-radius:10px;max-height:280px;object-fit:cover;display:block;}
.compose-img-remove{position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:50%;
  background:rgba(0,0,0,.7);border:none;color:#fff;font-size:14px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;}
.compose-modal-footer{padding:12px 16px;border-top:1px solid var(--border);display:flex;align-items:center;gap:8px;}
.compose-modal-tools{display:flex;gap:6px;}
.cmtool{background:none;border:none;cursor:pointer;font-size:20px;padding:6px;border-radius:8px;
  color:var(--muted);transition:all .15s;}
.cmtool:hover{background:var(--surface2);color:var(--text);}
.compose-submit{margin-left:auto;padding:9px 24px;border-radius:22px;background:var(--accent);
  border:none;color:#fff;font-family:var(--font);font-size:14px;font-weight:800;
  cursor:pointer;transition:all .15s;}
.compose-submit:hover{opacity:.9;}
.compose-submit:disabled{opacity:.4;cursor:not-allowed;}
.char-hint{font-size:10px;color:var(--muted);}

/* ── Post card ── */
.post-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;
  margin-bottom:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:box-shadow .15s;}
.post-header{display:flex;align-items:center;gap:10px;padding:14px 16px 10px;}
.post-user-info{flex:1;}
.post-user-name{font-size:14px;font-weight:800;color:var(--text);}
.post-time{font-size:11px;color:var(--muted);margin-top:1px;display:flex;align-items:center;gap:4px;}
.post-content{padding:4px 16px 12px;font-size:14px;line-height:1.65;color:var(--text);
  white-space:pre-wrap;word-break:break-word;}
.post-image-wrap{margin-bottom:0;}
.post-image{width:100%;max-height:450px;object-fit:cover;display:block;cursor:pointer;}

.post-menu{position:relative;}
.post-menu-btn{width:34px;height:34px;border-radius:50%;background:none;border:none;cursor:pointer;
  font-size:18px;color:var(--muted);display:flex;align-items:center;justify-content:center;
  transition:background .15s;letter-spacing:1px;}
.post-menu-btn:hover{background:var(--surface2);}
.post-menu-drop{position:absolute;right:0;top:38px;background:var(--surface);border:1px solid var(--border);
  border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.15);z-index:20;min-width:160px;overflow:hidden;display:none;}
.post-menu-drop.show{display:block;}
.post-menu-item{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;border:none;
  background:none;cursor:pointer;font-family:var(--font);font-size:13px;font-weight:600;
  color:var(--text2);transition:background .12s;text-align:left;}
.post-menu-item:hover{background:var(--surface2);}
.post-menu-item.danger{color:#ef4444;}

/* ── Reactions ── */
.reaction-summary{padding:6px 16px;display:flex;align-items:center;justify-content:space-between;
  font-size:12px;color:var(--muted);}
.reaction-emojis{display:flex;gap:2px;align-items:center;}
.reaction-emoji-badge{font-size:14px;display:inline-flex;align-items:center;justify-content:center;
  width:22px;height:22px;border-radius:50%;background:var(--surface2);border:2px solid var(--surface);}
.reaction-count{font-size:12px;color:var(--muted);margin-left:4px;}

.post-actions{display:flex;border-top:1px solid var(--border);}
.post-action-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
  padding:8px 6px;border:none;background:none;cursor:pointer;font-family:var(--font);font-size:13px;
  font-weight:700;color:var(--muted);transition:background .15s;position:relative;}
.post-action-btn:hover{background:var(--surface2);}
.post-action-btn.reacted{color:var(--accent);}
.post-action-btn .ba{font-size:16px;}

/* Reaction picker */
.reaction-picker{position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);
  background:var(--surface);border:1px solid var(--border);border-radius:40px;padding:6px 10px;
  display:flex;gap:4px;box-shadow:0 8px 30px rgba(0,0,0,.18);z-index:30;
  opacity:0;pointer-events:none;transition:opacity .15s,transform .15s;transform:translateX(-50%) translateY(8px) scale(.9);}
.reaction-picker.show{opacity:1;pointer-events:all;transform:translateX(-50%) translateY(0) scale(1);}
.rpick{font-size:26px;cursor:pointer;padding:4px;border-radius:50%;transition:transform .12s;border:none;background:none;line-height:1;}
.rpick:hover{transform:scale(1.4) translateY(-4px);}

/* ── Comments ── */
.comments-section{padding:0 16px 12px;display:none;}
.comments-section.open{display:block;}
.comments-divider{height:1px;background:var(--border);margin-bottom:12px;}
.comment-item{display:flex;gap:8px;margin-bottom:10px;align-items:flex-start;}
.comment-body{flex:1;}
.comment-bubble{background:var(--surface2);border-radius:18px;padding:9px 14px;display:inline-block;max-width:100%;}
.comment-user{font-size:12px;font-weight:800;color:var(--text);}
.comment-text{font-size:13px;color:var(--text2);margin-top:1px;word-break:break-word;line-height:1.5;}
.comment-img{max-width:200px;border-radius:10px;margin-top:6px;display:block;cursor:pointer;}
.comment-meta{display:flex;gap:10px;padding:3px 6px;font-size:11px;color:var(--muted);font-weight:600;}
.comment-meta button{background:none;border:none;cursor:pointer;font-size:11px;color:var(--muted);font-weight:600;padding:0;}
.comment-meta button:hover{color:#ef4444;text-decoration:underline;}

/* Comment input */
.comment-input-area{display:flex;gap:8px;align-items:flex-end;margin-top:8px;}
.comment-input-wrap{flex:1;background:var(--surface2);border:1.5px solid var(--border);
  border-radius:22px;display:flex;align-items:center;padding:6px 12px;gap:6px;transition:border-color .15s;}
.comment-input-wrap:focus-within{border-color:var(--accent);background:var(--surface);}
.comment-input{flex:1;border:none;background:transparent;outline:none;font-family:var(--font);
  font-size:13px;color:var(--text);resize:none;min-height:32px;max-height:80px;line-height:1.5;}
.cmt-attach{background:none;border:none;cursor:pointer;font-size:16px;color:var(--muted);padding:2px;transition:color .15s;}
.cmt-attach:hover{color:var(--accent);}
.comment-send-btn{width:34px;height:34px;border-radius:50%;background:var(--accent);border:none;
  color:#fff;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:all .15s;}
.comment-send-btn:hover{opacity:.85;}
.comment-send-btn:disabled{opacity:.4;cursor:not-allowed;}
.cmt-img-preview{padding:6px 0;position:relative;display:none;}
.cmt-img-preview.show{display:block;}
.cmt-img-preview img{max-height:80px;border-radius:8px;}
.cmt-img-preview .rm{position:absolute;top:10px;right:4px;width:20px;height:20px;border-radius:50%;
  background:rgba(0,0,0,.7);border:none;color:#fff;font-size:10px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;}

/* Emoji picker */
.emoji-picker-btn{background:none;border:none;cursor:pointer;font-size:16px;color:var(--muted);padding:2px;}
.emoji-panel{display:none;padding:8px;flex-wrap:wrap;gap:4px;background:var(--surface);
  border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);
  position:absolute;bottom:100%;right:0;z-index:50;width:230px;}
.emoji-panel.show{display:flex;}
.ep-btn{background:none;border:none;cursor:pointer;font-size:20px;padding:4px;border-radius:6px;transition:background .12s;}
.ep-btn:hover{background:var(--surface2);}

/* Load more */
.load-more-wrap{text-align:center;padding:10px 0;}
.load-more-btn{padding:10px 28px;border-radius:22px;background:var(--surface);border:1.5px solid var(--border);
  color:var(--accent);font-weight:800;font-size:13px;cursor:pointer;transition:all .15s;font-family:var(--font);}
.load-more-btn:hover{background:var(--accent-soft);border-color:var(--accent);}

/* Lightbox */
.lightbox{position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:400;
  display:none;align-items:center;justify-content:center;cursor:pointer;}
.lightbox.show{display:flex;}
.lightbox img{max-width:92vw;max-height:92vh;border-radius:10px;}

/* Empty */
.empty-feed{text-align:center;padding:3rem 1rem;color:var(--muted);}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="page">
<div class="page-header" style="text-align:center;">
  <div style="font-size:11px;font-weight:800;color:var(--accent);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;">Cộng đồng</div>
  <h1 style="font-size:1.8rem;font-weight:900;margin:0;">MindSpark Community</h1>
  <div style="font-size:13px;color:var(--muted);margin-top:6px;">Chia sẻ kiến thức · Học hỏi lẫn nhau · Kết nối cùng bạn học</div>
</div>

<div class="feed-wrap">

  <!-- Compose box (collapsed) -->
  <div class="compose-card">
    <div class="compose-top">
      <?=userAvatar($user,42)?>
      <div class="compose-fake-input" onclick="openCompose()">
        <?=htmlspecialchars($user['name'])?> ơi, bạn đang nghĩ gì thế?
      </div>
    </div>
    <div class="compose-divider"></div>
    <div class="compose-tools">
      <button class="compose-tool" onclick="openCompose(true)">
        <span class="ti">🖼️</span> Ảnh/Video
      </button>
      <button class="compose-tool" onclick="openCompose()">
        <span class="ti">😊</span> Cảm xúc
      </button>
      <button class="compose-tool" onclick="openCompose()">
        <span class="ti">✍️</span> Bài viết
      </button>
    </div>
  </div>

  <!-- Feed -->
  <div id="feedList">
    <?php foreach($posts as $p): renderPost($p, $REACTIONS); endforeach; ?>
    <?php if(empty($posts)): ?>
    <div class="empty-feed">
      <div style="font-size:3rem;opacity:.3;margin-bottom:12px;">🌱</div>
      <div style="font-weight:800;font-size:16px;margin-bottom:6px;">Chưa có bài đăng nào</div>
      <div style="font-size:13px;">Hãy là người đầu tiên chia sẻ!</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if(count($posts)>=15):?>
  <div class="load-more-wrap">
    <button class="load-more-btn" id="loadMoreBtn" onclick="loadMore()">Xem thêm ↓</button>
  </div>
  <?php endif;?>
</div>
</div>

<!-- Compose Modal -->
<div class="compose-modal" id="composeModal" onclick="if(event.target===this)closeCompose()">
  <div class="compose-modal-box">
    <div class="compose-modal-header">
      <div class="compose-modal-title">Tạo bài viết</div>
      <button class="compose-modal-close" onclick="closeCompose()">✕</button>
    </div>
    <div class="compose-modal-user">
      <?=userAvatar($user,44)?>
      <div>
        <div class="compose-modal-name"><?=htmlspecialchars($user['name'])?></div>
        <div style="font-size:11px;color:var(--muted);font-weight:600;">🌐 Công khai</div>
      </div>
    </div>
    <textarea class="compose-textarea" id="postContent"
      placeholder="<?=htmlspecialchars($user['name'])?> ơi, bạn đang nghĩ gì thế?"
      oninput="updateCompose(this)"></textarea>
    <div class="compose-img-preview" id="composeImgWrap" style="display:none;">
      <img id="composeImgPreview" src="">
      <button class="compose-img-remove" onclick="removePostImg()">✕</button>
    </div>
    <div class="compose-modal-footer">
      <div class="compose-modal-tools">
        <button class="cmtool" title="Thêm ảnh" onclick="document.getElementById('imgInput').click()">🖼️</button>
        <button class="cmtool" title="Emoji" onclick="toggleComposeEmoji()">😊</button>
      </div>
      <span class="char-hint" id="charCount">0/3000</span>
      <button class="compose-submit" id="postBtn" onclick="submitPost()" disabled>Đăng bài</button>
    </div>
    <!-- Emoji panel for compose -->
    <div id="composeEmojiPanel" style="display:none;padding:10px 16px 14px;flex-wrap:wrap;gap:6px;border-top:1px solid var(--border);">
      <?php foreach(['😀','😂','🥰','😎','🤔','😢','😡','🎉','🔥','👍','❤️','✅','📚','💡','🚀','🎯','💪','🙌','🤝','⭐'] as $e): ?>
      <button onclick="insertEmoji('<?=$e?>')" style="background:none;border:none;cursor:pointer;font-size:22px;padding:4px;border-radius:6px;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='none'"><?=$e?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<input type="file" id="imgInput" accept="image/*" style="display:none" onchange="attachPostImg(this)">

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="this.classList.remove('show')">
  <img id="lightboxImg" src="" alt="">
</div>

<?php
function renderPost($p, $REACTIONS) {
    global $uid;
    $mine  = !empty($p['mine']);
    $av    = userAvatar($p, 40);
    $pid   = (int)$p['id'];
    $myRx  = $p['my_reaction'] ?? null;
    $reacted = $myRx !== null;
    $likeCount = (int)$p['likes_count'];
    $cmtCount  = (int)$p['comments_count'];

    // Top reaction display
    $topHtml = '';
    foreach(($p['top_reactions']??[]) as $rx) {
        $em = $REACTIONS[$rx] ?? '👍';
        $topHtml .= '<span class="reaction-emoji-badge">'.$em.'</span>';
    }

    // Current user's reaction display
    $myRxEmoji = $myRx ? ($REACTIONS[$myRx] ?? '👍') : '';
    $reactLabel = $myRx ? htmlspecialchars($myRx) : 'Thích';
    $reactClass = $reacted ? 'reacted' : '';

    // Reaction picker buttons
    $rpickHtml = '';
    foreach($REACTIONS as $k=>$em) {
        $rpickHtml .= '<button class="rpick" onclick="doReact('.$pid.',\''.$k.'\')" title="'.ucfirst($k).'">'.$em.'</button>';
    }

    echo <<<HTML
<div class="post-card" id="post-{$pid}">
  <div class="post-header">
    {$av}
    <div class="post-user-info">
      <div class="post-user-name">{$p['name']}</div>
      <div class="post-time"><span>🌐</span><span class="post-time-text" data-raw="{$p['created_at']}">{$p['created_at']}</span></div>
    </div>
    <div class="post-menu">
      <button class="post-menu-btn" onclick="toggleMenu({$pid},event)">···</button>
      <div class="post-menu-drop" id="pmenu-{$pid}">
HTML;
    if ($mine) echo '<button class="post-menu-item danger" onclick="deletePost('.$pid.')"><span>🗑️</span> Xoá bài viết</button>';
    echo <<<HTML
        <button class="post-menu-item" onclick="copyPostLink({$pid})"><span>🔗</span> Sao chép liên kết</button>
      </div>
    </div>
  </div>
HTML;
    if (!empty($p['content'])) {
        $c = htmlspecialchars($p['content']);
        // Big text if short
        $fs = strlen($p['content']) < 80 ? 'font-size:20px;font-weight:700;' : '';
        echo '<div class="post-content" style="'.$fs.'">'.$c.'</div>';
    }
    if (!empty($p['image_data'])) {
        echo '<div class="post-image-wrap"><img class="post-image" src="'.htmlspecialchars($p['image_data']).'" alt="" loading="lazy" onclick="openLightbox(this.src)"></div>';
    }
    echo <<<HTML
  <div class="reaction-summary" id="react-summary-{$pid}">
    <div class="reaction-emojis" id="react-emojis-{$pid}">{$topHtml}<span class="reaction-count" id="likeCount-{$pid}">{$likeCount}</span></div>
    <span style="font-size:11px;color:var(--muted);" id="cmtLabel-{$pid}">{$cmtCount} bình luận</span>
  </div>
  <div class="post-actions">
    <button class="post-action-btn {$reactClass}" id="like-{$pid}"
      onclick="toggleReactPicker({$pid})"
      ondblclick="doReact({$pid},'like')"
      title="Giữ để chọn cảm xúc">
      <div class="reaction-picker" id="rpicker-{$pid}">{$rpickHtml}</div>
      <span class="ba" id="react-icon-{$pid}">{$myRxEmoji}</span>
      <span id="react-label-{$pid}">{$reactLabel}</span>
    </button>
    <button class="post-action-btn" onclick="toggleComments({$pid})">
      <span class="ba">💬</span> Bình luận
    </button>
    <button class="post-action-btn" onclick="sharePost({$pid})">
      <span class="ba">↗️</span> Chia sẻ
    </button>
  </div>
  <div class="comments-section" id="cmt-{$pid}">
    <div class="comments-divider"></div>
    <div id="cmtList-{$pid}"></div>
    <div class="comment-input-area">
      {$av}
      <div style="flex:1;">
        <div class="cmt-img-preview" id="cmtImgPrev-{$pid}">
          <img id="cmtImgThumb-{$pid}" src="">
          <button class="rm" onclick="removeCmtImg({$pid})">✕</button>
        </div>
        <div class="comment-input-wrap">
          <textarea class="comment-input" placeholder="Viết bình luận..." id="cmtInput-{$pid}"
            onkeydown="cmtKey(event,{$pid})" rows="1" oninput="autoResizeCmt(this)"></textarea>
          <button class="cmt-attach" title="Gửi ảnh" onclick="document.getElementById('cmtImgInput-{$pid}').click()">🖼️</button>
          <button class="emoji-picker-btn" onclick="toggleCmtEmoji({$pid})">😊</button>
          <input type="file" id="cmtImgInput-{$pid}" accept="image/*" style="display:none" onchange="attachCmtImg(this,{$pid})">
        </div>
      </div>
      <button class="comment-send-btn" onclick="submitComment({$pid})" id="cmtBtn-{$pid}">➤</button>
    </div>
  </div>
</div>
HTML;
}
?>

<script>
const ME = <?=$uid?>;
let postOffset = <?=count($posts)?>;
let postImgData = '';
let cmtImgData  = {};

/* ── Time format ── */
document.querySelectorAll('.post-time-text').forEach(el => {
  const raw=el.dataset.raw||'';
  if(raw.includes('-')){
    const d=new Date(raw.replace(' ','T'));
    const diff=(Date.now()-d)/1000;
    if(diff<60) el.textContent='Vừa xong';
    else if(diff<3600) el.textContent=Math.floor(diff/60)+' phút trước';
    else if(diff<86400) el.textContent=Math.floor(diff/3600)+' giờ trước';
    else if(diff<604800) el.textContent=Math.floor(diff/86400)+' ngày trước';
    else el.textContent=d.toLocaleDateString('vi-VN');
  }
});

/* ── Compose ── */
function openCompose(withImg=false){
  document.getElementById('composeModal').classList.add('show');
  setTimeout(()=>document.getElementById('postContent').focus(),100);
  if(withImg) document.getElementById('imgInput').click();
}
function closeCompose(){
  document.getElementById('composeModal').classList.remove('show');
}
function updateCompose(ta){
  const len=ta.value.length;
  document.getElementById('charCount').textContent=len+'/3000';
  document.getElementById('postBtn').disabled=(len===0&&!postImgData);
  // font size adaptive
  ta.style.fontSize = len<80&&len>0 ? '20px' : '15px';
}
function attachPostImg(input){
  if(!input.files[0]) return;
  const r=new FileReader();
  r.onload=e=>{
    postImgData=e.target.result;
    document.getElementById('composeImgPreview').src=e.target.result;
    document.getElementById('composeImgWrap').style.display='block';
    document.getElementById('postBtn').disabled=false;
  };
  r.readAsDataURL(input.files[0]);
}
function removePostImg(){
  postImgData='';
  document.getElementById('composeImgWrap').style.display='none';
  document.getElementById('imgInput').value='';
  const ta=document.getElementById('postContent');
  document.getElementById('postBtn').disabled=ta.value.trim().length===0;
}
function toggleComposeEmoji(){
  const p=document.getElementById('composeEmojiPanel');
  p.style.display=p.style.display==='none'?'flex':'none';
}
function insertEmoji(e){
  const ta=document.getElementById('postContent');
  const pos=ta.selectionStart;
  ta.value=ta.value.slice(0,pos)+e+ta.value.slice(pos);
  updateCompose(ta);
  ta.focus();
}
async function submitPost(){
  const content=document.getElementById('postContent').value.trim();
  if(!content&&!postImgData) return;
  document.getElementById('postBtn').disabled=true;
  document.getElementById('postBtn').textContent='⏳';
  const fd=new FormData();
  fd.append('action','post'); fd.append('content',content);
  if(postImgData) fd.append('image_data',postImgData);
  const res=await fetch('community.php',{method:'POST',body:fd});
  const data=await res.json();
  if(data.ok){ closeCompose(); location.reload(); }
  else{ alert(data.msg||'Lỗi!'); }
  document.getElementById('postBtn').disabled=false;
  document.getElementById('postBtn').textContent='Đăng bài';
}

/* ── Post menu ── */
function toggleMenu(pid,e){
  e.stopPropagation();
  const d=document.getElementById('pmenu-'+pid);
  d.classList.toggle('show');
  document.addEventListener('click',()=>d.classList.remove('show'),{once:true});
}
async function deletePost(pid){
  if(!confirm('Xoá bài đăng này?')) return;
  const fd=new FormData(); fd.append('action','delete_post'); fd.append('post_id',pid);
  await fetch('community.php',{method:'POST',body:fd});
  document.getElementById('post-'+pid)?.remove();
}
function copyPostLink(pid){
  navigator.clipboard?.writeText(location.origin+location.pathname+'#post-'+pid);
}
function sharePost(pid){ copyPostLink(pid); }

/* ── Reactions ── */
const REACTIONS = <?=json_encode($REACTIONS)?>;
let pickerTimer = {};

function toggleReactPicker(pid){
  const picker=document.getElementById('rpicker-'+pid);
  if(picker.classList.contains('show')){
    picker.classList.remove('show');
    clearTimeout(pickerTimer[pid]);
  } else {
    picker.classList.add('show');
    pickerTimer[pid]=setTimeout(()=>picker.classList.remove('show'),3000);
  }
}

async function doReact(pid, reaction){
  document.getElementById('rpicker-'+pid)?.classList.remove('show');
  const fd=new FormData();
  fd.append('action','react'); fd.append('post_id',pid); fd.append('reaction',reaction);
  const res=await fetch('community.php',{method:'POST',body:fd});
  const d=await res.json();
  if(d.ok){
    const btn=document.getElementById('like-'+pid);
    const icon=document.getElementById('react-icon-'+pid);
    const label=document.getElementById('react-label-'+pid);
    const cnt=document.getElementById('likeCount-'+pid);
    const emojisEl=document.getElementById('react-emojis-'+pid);
    cnt.textContent=d.count;
    if(d.my_reaction){
      btn.classList.add('reacted');
      icon.textContent=REACTIONS[d.my_reaction]||'👍';
      label.textContent=d.my_reaction.charAt(0).toUpperCase()+d.my_reaction.slice(1);
    } else {
      btn.classList.remove('reacted');
      icon.textContent='';
      label.textContent='Thích';
    }
    // Update top emojis display
    let newEmojis='';
    (d.top||[]).forEach(r=>{ newEmojis+='<span class="reaction-emoji-badge">'+(REACTIONS[r]||'👍')+'</span>'; });
    emojisEl.innerHTML=newEmojis+'<span class="reaction-count" id="likeCount-'+pid+'">'+d.count+'</span>';
  }
}

/* ── Comments ── */
const cmtOpen={};
async function toggleComments(pid){
  const sec=document.getElementById('cmt-'+pid);
  if(cmtOpen[pid]){ sec.classList.remove('open'); cmtOpen[pid]=false; return; }
  cmtOpen[pid]=true; sec.classList.add('open');
  await loadComments(pid);
  document.getElementById('cmtInput-'+pid)?.focus();
}

async function loadComments(pid){
  const fd=new FormData(); fd.append('action','load_comments'); fd.append('post_id',pid);
  const res=await fetch('community.php',{method:'POST',body:fd});
  const d=await res.json();
  const list=document.getElementById('cmtList-'+pid);
  if(!d.comments.length){
    list.innerHTML='<div style="font-size:12px;color:var(--muted);padding:4px 0 8px;">Chưa có bình luận. Hãy là người đầu tiên!</div>';
    return;
  }
  list.innerHTML=d.comments.map(c=>renderComment(c,pid)).join('');
}

function renderComment(c, pid){
  const imgHtml = c.image_data ? `<img src="${c.image_data}" class="comment-img" onclick="openLightbox(this.src)">` : '';
  const delBtn  = c.mine ? `<button onclick="deleteComment(${c.id},${pid})">Xoá</button>` : '';
  return `<div class="comment-item" id="cm-${c.id}">
    ${c.avatar}
    <div class="comment-body">
      <div class="comment-bubble">
        <div class="comment-user">${c.user}</div>
        <div class="comment-text">${c.content}</div>
        ${imgHtml}
      </div>
      <div class="comment-meta"><span>${c.time}</span>${delBtn}</div>
    </div>
  </div>`;
}

function autoResizeCmt(ta){
  ta.style.height='auto';
  ta.style.height=Math.min(ta.scrollHeight,80)+'px';
}
function cmtKey(e,pid){
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();submitComment(pid);}
}
function attachCmtImg(input,pid){
  if(!input.files[0]) return;
  const r=new FileReader();
  r.onload=e=>{
    cmtImgData[pid]=e.target.result;
    const prev=document.getElementById('cmtImgPrev-'+pid);
    document.getElementById('cmtImgThumb-'+pid).src=e.target.result;
    prev.classList.add('show');
  };
  r.readAsDataURL(input.files[0]);
}
function removeCmtImg(pid){
  delete cmtImgData[pid];
  document.getElementById('cmtImgPrev-'+pid).classList.remove('show');
  document.getElementById('cmtImgInput-'+pid).value='';
}
function toggleCmtEmoji(pid){
  // Simple inline emoji insert
  const emojis=['😀','😂','🥰','😎','🤔','❤️','🔥','👍','🎉','💪'];
  let panel=document.getElementById('cmtEmojiPanel-'+pid);
  if(!panel){
    panel=document.createElement('div');
    panel.id='cmtEmojiPanel-'+pid;
    panel.style.cssText='display:flex;flex-wrap:wrap;gap:4px;padding:8px;background:var(--surface);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);margin-top:4px;';
    emojis.forEach(em=>{
      const b=document.createElement('button');
      b.textContent=em; b.style.cssText='background:none;border:none;cursor:pointer;font-size:20px;padding:4px;border-radius:6px;';
      b.onclick=()=>{ const ta=document.getElementById('cmtInput-'+pid); ta.value+=em; ta.focus(); panel.remove(); };
      panel.appendChild(b);
    });
    document.getElementById('cmtInput-'+pid).parentElement.parentElement.appendChild(panel);
    setTimeout(()=>document.addEventListener('click',()=>panel.remove(),{once:true}),0);
  } else { panel.remove(); }
}
async function submitComment(pid){
  const input=document.getElementById('cmtInput-'+pid);
  const content=input.value.trim();
  const imgData=cmtImgData[pid]||'';
  if(!content&&!imgData) return;
  const fd=new FormData();
  fd.append('action','comment'); fd.append('post_id',pid); fd.append('content',content);
  if(imgData) fd.append('image_data',imgData);
  const res=await fetch('community.php',{method:'POST',body:fd});
  const d=await res.json();
  if(d.ok){
    input.value=''; input.style.height='auto';
    removeCmtImg(pid);
    const list=document.getElementById('cmtList-'+pid);
    // Remove "no comments" placeholder
    if(list.querySelector('div[style]')) list.innerHTML='';
    list.insertAdjacentHTML('beforeend',renderComment(d.comment,pid));
    const lbl=document.getElementById('cmtLabel-'+pid);
    if(lbl) lbl.textContent=(parseInt(lbl.textContent)||0)+1+' bình luận';
    list.parentElement.scrollTop=list.parentElement.scrollHeight;
  }
}
async function deleteComment(cid,pid){
  const fd=new FormData(); fd.append('action','delete_comment'); fd.append('comment_id',cid);
  await fetch('community.php',{method:'POST',body:fd});
  document.getElementById('cm-'+cid)?.remove();
  const lbl=document.getElementById('cmtLabel-'+pid);
  if(lbl){ const n=Math.max(0,(parseInt(lbl.textContent)||0)-1); lbl.textContent=n+' bình luận'; }
}

/* ── Load more ── */
async function loadMore(){
  const btn=document.getElementById('loadMoreBtn');
  btn.textContent='Đang tải...'; btn.disabled=true;
  const res=await fetch('community.php?offset='+postOffset);
  const html=await res.text();
  const parser=new DOMParser();
  const doc=parser.parseFromString(html,'text/html');
  const newPosts=doc.querySelectorAll('.post-card');
  const feed=document.getElementById('feedList');
  newPosts.forEach(p=>feed.appendChild(p));
  postOffset+=newPosts.length;
  // Re-run time format
  feed.querySelectorAll('.post-time-text').forEach(el=>{
    const raw=el.dataset.raw||el.textContent;
    if(raw.includes('-')){
      const d2=new Date(raw.replace(' ','T'));
      const diff2=(Date.now()-d2)/1000;
      if(diff2<60)el.textContent='Vừa xong';
      else if(diff2<3600)el.textContent=Math.floor(diff2/60)+' phút trước';
      else if(diff2<86400)el.textContent=Math.floor(diff2/3600)+' giờ trước';
      else el.textContent=d2.toLocaleDateString('vi-VN');
    }
  });
  if(newPosts.length<15) btn.parentElement.remove();
  else{ btn.textContent='Xem thêm ↓'; btn.disabled=false; }
}

/* ── Lightbox ── */
function openLightbox(src){
  document.getElementById('lightboxImg').src=src;
  document.getElementById('lightbox').classList.add('show');
}

/* Close pickers on outside click */
document.addEventListener('click', e=>{
  if(!e.target.closest('.post-menu')) {
    document.querySelectorAll('.post-menu-drop.show').forEach(d=>d.classList.remove('show'));
  }
});
</script>
</body>
</html>
