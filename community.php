<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid  = $_SESSION['user_id'];
$user = getCurrentUser();

// ── AJAX handlers ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'post') {
        $content = trim($_POST['content'] ?? '');
        $imgData = $_POST['image_data'] ?? '';
        if (strlen($content) < 1 && !$imgData) { echo json_encode(['ok'=>false,'msg'=>'Nội dung trống']); exit; }
        if (strlen($content) > 3000) { echo json_encode(['ok'=>false,'msg'=>'Quá 3000 ký tự']); exit; }
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

    if ($action === 'like') {
        $pid = (int)($_POST['post_id']??0);
        $ex  = $db->query("SELECT id FROM post_likes WHERE post_id=$pid AND user_id=$uid")->fetchArray();
        if ($ex) {
            $db->exec("DELETE FROM post_likes WHERE post_id=$pid AND user_id=$uid");
            $db->exec("UPDATE social_posts SET likes_count=likes_count-1 WHERE id=$pid");
            $liked = false;
        } else {
            $db->exec("INSERT INTO post_likes (post_id,user_id) VALUES($pid,$uid)");
            $db->exec("UPDATE social_posts SET likes_count=likes_count+1 WHERE id=$pid");
            $liked = true;
        }
        $cnt = $db->query("SELECT likes_count FROM social_posts WHERE id=$pid")->fetchArray()['likes_count'];
        echo json_encode(['ok'=>true,'liked'=>$liked,'count'=>(int)$cnt]); exit;
    }

    if ($action === 'comment') {
        $pid     = (int)($_POST['post_id']??0);
        $content = trim($_POST['content']??'');
        if (!$content || !$pid) { echo json_encode(['ok'=>false]); exit; }
        $st = $db->prepare('INSERT INTO post_comments (post_id,user_id,content) VALUES(:p,:u,:c)');
        $st->bindValue(':p',$pid);$st->bindValue(':u',$uid);$st->bindValue(':c',$content);
        $st->execute();
        $db->exec("UPDATE social_posts SET comments_count=comments_count+1 WHERE id=$pid");
        $newId = $db->lastInsertRowID();
        $cm = $db->query("SELECT c.*,u.name,u.avatar FROM post_comments c JOIN users u ON c.user_id=u.id WHERE c.id=$newId")->fetchArray(SQLITE3_ASSOC);
        echo json_encode(['ok'=>true,'comment'=>[
            'id'=>$cm['id'],'content'=>htmlspecialchars($cm['content']),
            'user'=>htmlspecialchars($cm['name']),'avatar'=>userAvatar($cm,32),
            'time'=>timeAgo($cm['created_at']),'mine'=>true,
        ]]); exit;
    }

    if ($action === 'delete_comment') {
        $cid = (int)($_POST['comment_id']??0);
        $cm  = $db->query("SELECT * FROM post_comments WHERE id=$cid")->fetchArray(SQLITE3_ASSOC);
        if ($cm && $cm['user_id']==$uid) {
            $db->exec("DELETE FROM post_comments WHERE id=$cid");
            $db->exec("UPDATE social_posts SET comments_count=comments_count-1 WHERE id={$cm['post_id']}");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'load_comments') {
        $pid  = (int)($_POST['post_id']??0);
        $rows = $db->query("SELECT c.*,u.name,u.avatar FROM post_comments c JOIN users u ON c.user_id=u.id WHERE c.post_id=$pid ORDER BY c.created_at ASC LIMIT 50");
        $out  = [];
        while($r=$rows->fetchArray(SQLITE3_ASSOC)) {
            $out[] = ['id'=>$r['id'],'content'=>htmlspecialchars($r['content']),
                'user'=>htmlspecialchars($r['name']),'avatar'=>userAvatar($r,32),
                'time'=>timeAgo($r['created_at']),'mine'=>$r['user_id']==$uid];
        }
        echo json_encode(['ok'=>true,'comments'=>$out]); exit;
    }
    echo json_encode(['ok'=>false]); exit;
}

// ── Load posts ──
$offset = (int)($_GET['offset']??0);
$posts_q = $db->query("SELECT p.*,u.name,u.avatar,u.cover_color
    FROM social_posts p JOIN users u ON p.user_id=u.id
    ORDER BY p.created_at DESC LIMIT 15 OFFSET $offset");
$posts = [];
while ($r=$posts_q->fetchArray(SQLITE3_ASSOC)) {
    $r['liked'] = (bool)$db->query("SELECT id FROM post_likes WHERE post_id={$r['id']} AND user_id=$uid")->fetchArray();
    $r['mine']  = $r['user_id']==$uid;
    $posts[]    = $r;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cộng đồng — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.feed-wrap{max-width:640px;margin:0 auto;}
.compose-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:16px;}
.compose-row{display:flex;gap:10px;align-items:flex-start;}
.compose-input{flex:1;border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;
  font-family:var(--font);font-size:13px;color:var(--text);background:var(--surface2);
  resize:none;min-height:44px;max-height:200px;transition:border-color .15s;outline:none;line-height:1.5;}
.compose-input:focus{border-color:var(--accent);background:var(--surface);}
.compose-actions{display:flex;align-items:center;gap:8px;margin-top:10px;padding-top:10px;border-top:1px solid var(--border);}
.compose-btn-icon{background:none;border:none;cursor:pointer;font-size:18px;padding:4px 8px;border-radius:8px;
  transition:background .15s;color:var(--text2);}
.compose-btn-icon:hover{background:var(--surface2);}
.img-preview-wrap{position:relative;display:inline-block;margin-top:10px;}
.img-preview-wrap img{max-height:200px;border-radius:10px;display:block;}
.img-preview-wrap button{position:absolute;top:4px;right:4px;background:rgba(0,0,0,.6);border:none;
  color:#fff;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:11px;}
.char-count{font-size:10px;color:var(--muted);margin-left:auto;}

.post-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;
  margin-bottom:12px;overflow:hidden;transition:box-shadow .15s;}
.post-card:hover{box-shadow:var(--shadow-lg);}
.post-header{display:flex;align-items:center;gap:10px;padding:14px 16px 0;}
.post-user-info{flex:1;min-width:0;}
.post-user-name{font-size:13px;font-weight:800;color:var(--text);}
.post-time{font-size:11px;color:var(--muted);}
.post-menu{position:relative;}
.post-menu-btn{background:none;border:none;cursor:pointer;font-size:16px;color:var(--muted);
  padding:4px 8px;border-radius:8px;transition:background .15s;}
.post-menu-btn:hover{background:var(--surface2);}
.post-menu-drop{position:absolute;right:0;top:100%;background:var(--surface);border:1px solid var(--border);
  border-radius:10px;box-shadow:var(--shadow-lg);z-index:10;min-width:140px;overflow:hidden;display:none;}
.post-menu-drop.show{display:block;}
.post-menu-item{display:block;width:100%;padding:9px 14px;text-align:left;border:none;
  background:none;cursor:pointer;font-family:var(--font);font-size:12px;font-weight:600;
  color:var(--text2);transition:background .12s;}
.post-menu-item:hover{background:var(--surface2);}
.post-menu-item.danger{color:var(--red);}

.post-content{padding:12px 16px;font-size:14px;line-height:1.6;color:var(--text);white-space:pre-wrap;word-break:break-word;}
.post-image{width:100%;max-height:400px;object-fit:cover;display:block;}
.post-actions{display:flex;align-items:center;gap:4px;padding:6px 10px;border-top:1px solid var(--border);}
.action-btn{display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:10px;border:none;
  background:none;cursor:pointer;font-family:var(--font);font-size:12px;font-weight:700;
  color:var(--muted);transition:all .15s;}
.action-btn:hover{background:var(--surface2);color:var(--text);}
.action-btn.liked{color:var(--red);}
.action-btn.liked .like-icon{animation:pop .3s ease;}
@keyframes pop{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}

.comments-section{padding:0 16px 14px;display:none;}
.comments-section.open{display:block;}
.comment-item{display:flex;gap:8px;margin-bottom:10px;align-items:flex-start;}
.comment-bubble{flex:1;background:var(--surface2);border-radius:10px;padding:8px 12px;}
.comment-user{font-size:11px;font-weight:800;color:var(--text);}
.comment-text{font-size:13px;color:var(--text2);margin-top:2px;word-break:break-word;}
.comment-meta{font-size:10px;color:var(--muted);margin-top:4px;display:flex;gap:8px;align-items:center;}
.comment-del{background:none;border:none;cursor:pointer;font-size:10px;color:var(--muted);
  padding:0;font-weight:600;}
.comment-del:hover{color:var(--red);}
.comment-input-row{display:flex;gap:8px;margin-top:8px;align-items:center;}
.comment-input{flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:20px;
  font-family:var(--font);font-size:12px;background:var(--surface2);color:var(--text);
  outline:none;transition:border-color .15s;}
.comment-input:focus{border-color:var(--accent);}

.load-more-btn{display:block;width:100%;padding:12px;text-align:center;background:var(--surface);
  border:1px solid var(--border);border-radius:12px;color:var(--accent);font-weight:700;
  font-size:13px;cursor:pointer;transition:all .15s;}
.load-more-btn:hover{background:var(--accent-soft);}

.empty-feed{text-align:center;padding:3rem 1rem;color:var(--muted);}
.empty-feed .icon{font-size:3rem;margin-bottom:12px;opacity:.4;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Cộng đồng</div>
    <h1 class="page-title">🌏 MindSpark Community</h1>
    <div class="page-sub">Chia sẻ kiến thức · Học hỏi lẫn nhau · Kết nối cùng bạn học</div>
  </div>

  <div class="feed-wrap">

    <!-- Compose -->
    <div class="compose-card">
      <div class="compose-row">
        <div style="margin-top:2px;"><?=userAvatar($user,40)?></div>
        <div style="flex:1;">
          <textarea class="compose-input" id="postContent" placeholder="Bạn đang nghĩ gì? Chia sẻ kiến thức, hỏi bài, hay chỉ nói hello 👋"
            oninput="updateCompose(this)" onkeydown="composeKey(event)"></textarea>
          <div id="imgPreviewWrap" style="display:none;" class="img-preview-wrap">
            <img id="imgPreview" src="">
            <button onclick="removeImg()">✕</button>
          </div>
        </div>
      </div>
      <div class="compose-actions">
        <button class="compose-btn-icon" onclick="document.getElementById('imgInput').click()" title="Đính kèm ảnh">🖼️</button>
        <input type="file" id="imgInput" accept="image/*" style="display:none" onchange="attachImg(this)">
        <span class="char-count" id="charCount">0/3000</span>
        <button class="btn btn-primary btn-sm" onclick="submitPost()" id="postBtn" disabled>Đăng bài</button>
      </div>
    </div>

    <!-- Feed -->
    <div id="feedList">
      <?php foreach($posts as $p): renderPost($p); endforeach; ?>
      <?php if(empty($posts)): ?>
      <div class="empty-feed">
        <div class="icon">🌱</div>
        <div style="font-weight:700;font-size:15px;margin-bottom:6px;">Chưa có bài đăng nào</div>
        <div style="font-size:13px;">Hãy là người đầu tiên chia sẻ!</div>
      </div>
      <?php endif; ?>
    </div>

    <?php if(count($posts)>=15):?>
    <button class="load-more-btn" id="loadMoreBtn" onclick="loadMore()">Xem thêm bài đăng ↓</button>
    <?php endif;?>
  </div>
</div>

<?php
function renderPost($p) {
    global $uid;
    $liked = !empty($p['liked']);
    $mine  = !empty($p['mine']);
    $av    = userAvatar($p, 42);
    $pid   = (int)$p['id'];
    echo <<<HTML
<div class="post-card" id="post-{$pid}">
  <div class="post-header">
    {$av}
    <div class="post-user-info">
      <div class="post-user-name">{$p['name']}</div>
      <div class="post-time">{$p['created_at']}</div>
    </div>
    <div class="post-menu">
      <button class="post-menu-btn" onclick="toggleMenu({$pid})">•••</button>
      <div class="post-menu-drop" id="pmenu-{$pid}">
HTML;
    if ($mine) echo <<<HTML
        <button class="post-menu-item danger" onclick="deletePost({$pid})">🗑️ Xoá bài</button>
HTML;
    echo <<<HTML
        <button class="post-menu-item" onclick="copyPostLink({$pid})">🔗 Copy link</button>
      </div>
    </div>
  </div>
HTML;
    if (!empty($p['content'])) {
        $content = htmlspecialchars($p['content']);
        echo '<div class="post-content">' . $content . '</div>';
    }
    if (!empty($p['image_data'])) {
        echo '<img class="post-image" src="'.htmlspecialchars($p['image_data']).'" alt="" loading="lazy">';
    }
    $likedClass = $liked ? 'liked' : '';
    $likeCount  = (int)$p['likes_count'];
    $cmtCount   = (int)$p['comments_count'];
    $heartIcon  = $liked ? '❤️' : '🤍';
    echo <<<HTML
  <div class="post-actions">
    <button class="action-btn {$likedClass}" id="like-{$pid}" onclick="toggleLike({$pid})">
      <span class="like-icon">{$heartIcon}</span>
      <span id="likeCount-{$pid}">{$likeCount}</span>
    </button>
    <button class="action-btn" onclick="toggleComments({$pid})">
      💬 <span id="cmtCount-{$pid}">{$cmtCount}</span>
    </button>
  </div>
  <div class="comments-section" id="cmt-{$pid}">
    <div id="cmtList-{$pid}"></div>
    <div class="comment-input-row">
      <div style="flex-shrink:0;">{$av}</div>
      <input class="comment-input" placeholder="Viết bình luận..." id="cmtInput-{$pid}"
        onkeydown="cmtKey(event,{$pid})">
      <button class="btn btn-primary btn-sm" onclick="submitComment({$pid})">Gửi</button>
    </div>
  </div>
</div>
HTML;
}
// Fix: set like icon after function
?>
<script>
// Fix post time display & icons
document.querySelectorAll('.post-time').forEach(el => {
  const raw = el.textContent.trim();
  if (raw.includes('-')) {
    const d = new Date(raw.replace(' ','T'));
    const diff = (Date.now()-d)/1000;
    if(diff<60) el.textContent='Vừa xong';
    else if(diff<3600) el.textContent=Math.floor(diff/60)+' phút trước';
    else if(diff<86400) el.textContent=Math.floor(diff/3600)+' giờ trước';
    else if(diff<604800) el.textContent=Math.floor(diff/86400)+' ngày trước';
    else el.textContent=d.toLocaleDateString('vi-VN');
  }
});

const ME = <?=$uid?>;
let postOffset = <?=count($posts)?>;
let imgDataGlobal = '';

function updateCompose(ta) {
  const len = ta.value.length;
  document.getElementById('charCount').textContent = len+'/3000';
  document.getElementById('postBtn').disabled = len===0 && !imgDataGlobal;
}
function composeKey(e) {
  if(e.ctrlKey && e.key==='Enter') submitPost();
}
function attachImg(input) {
  if(!input.files[0]) return;
  if(input.files[0].size > 1500000){ alert('Ảnh tối đa 1MB!'); return; }
  const r = new FileReader();
  r.onload = e => {
    imgDataGlobal = e.target.result;
    document.getElementById('imgPreview').src = e.target.result;
    document.getElementById('imgPreviewWrap').style.display = 'inline-block';
    document.getElementById('postBtn').disabled = false;
  };
  r.readAsDataURL(input.files[0]);
}
function removeImg() {
  imgDataGlobal=''; document.getElementById('imgPreviewWrap').style.display='none';
  document.getElementById('imgInput').value='';
  const ta = document.getElementById('postContent');
  document.getElementById('postBtn').disabled = ta.value.trim().length===0;
}
async function submitPost() {
  const content = document.getElementById('postContent').value.trim();
  if(!content && !imgDataGlobal) return;
  document.getElementById('postBtn').disabled = true;
  document.getElementById('postBtn').textContent = '⏳';
  const fd = new FormData();
  fd.append('action','post'); fd.append('content',content);
  if(imgDataGlobal) fd.append('image_data',imgDataGlobal);
  const res = await fetch('community.php',{method:'POST',body:fd});
  const data = await res.json();
  if(data.ok) {
    document.getElementById('postContent').value='';
    imgDataGlobal='';
    document.getElementById('imgPreviewWrap').style.display='none';
    document.getElementById('charCount').textContent='0/3000';
    removeImg();
    location.reload();
  } else { alert(data.msg||'Lỗi!'); }
  document.getElementById('postBtn').disabled = false;
  document.getElementById('postBtn').textContent = 'Đăng bài';
}

function toggleMenu(pid) {
  const d = document.getElementById('pmenu-'+pid);
  d.classList.toggle('show');
  document.addEventListener('click', ()=>d.classList.remove('show'), {once:true});
}
async function deletePost(pid) {
  if(!confirm('Xoá bài đăng này?')) return;
  const fd = new FormData(); fd.append('action','delete_post'); fd.append('post_id',pid);
  await fetch('community.php',{method:'POST',body:fd});
  document.getElementById('post-'+pid)?.remove();
}
function copyPostLink(pid) {
  navigator.clipboard.writeText(location.origin+location.pathname+'#post-'+pid);
  alert('Đã copy link!');
}

async function toggleLike(pid) {
  const btn = document.getElementById('like-'+pid);
  const fd = new FormData(); fd.append('action','like'); fd.append('post_id',pid);
  const res = await fetch('community.php',{method:'POST',body:fd});
  const d = await res.json();
  if(d.ok) {
    btn.classList.toggle('liked', d.liked);
    btn.querySelector('.like-icon').textContent = d.liked ? '❤️' : '🤍';
    document.getElementById('likeCount-'+pid).textContent = d.count;
  }
}

const cmtOpen = {};
async function toggleComments(pid) {
  const sec = document.getElementById('cmt-'+pid);
  if(cmtOpen[pid]) { sec.classList.remove('open'); cmtOpen[pid]=false; return; }
  cmtOpen[pid]=true; sec.classList.add('open');
  await loadComments(pid);
  document.getElementById('cmtInput-'+pid)?.focus();
}
async function loadComments(pid) {
  const fd = new FormData(); fd.append('action','load_comments'); fd.append('post_id',pid);
  const res = await fetch('community.php',{method:'POST',body:fd});
  const d = await res.json();
  const list = document.getElementById('cmtList-'+pid);
  if(!d.comments.length){ list.innerHTML='<div style="font-size:12px;color:var(--muted);padding:6px 0;">Chưa có bình luận nào.</div>'; return; }
  list.innerHTML = d.comments.map(c => `
    <div class="comment-item" id="cm-${c.id}">
      ${c.avatar}
      <div class="comment-bubble">
        <div class="comment-user">${c.user}</div>
        <div class="comment-text">${c.content}</div>
        <div class="comment-meta">
          ${c.time}
          ${c.mine ? `<button class="comment-del" onclick="deleteComment(${c.id},${pid})">✕ Xoá</button>` : ''}
        </div>
      </div>
    </div>
  `).join('');
}
function cmtKey(e,pid){ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();submitComment(pid);} }
async function submitComment(pid) {
  const input = document.getElementById('cmtInput-'+pid);
  const content = input.value.trim(); if(!content) return;
  const fd = new FormData(); fd.append('action','comment'); fd.append('post_id',pid); fd.append('content',content);
  const res = await fetch('community.php',{method:'POST',body:fd});
  const d = await res.json();
  if(d.ok) {
    input.value='';
    const c = d.comment;
    const list = document.getElementById('cmtList-'+pid);
    const div = document.createElement('div');
    div.className='comment-item'; div.id='cm-'+c.id;
    div.innerHTML=`${c.avatar}<div class="comment-bubble"><div class="comment-user">${c.user}</div><div class="comment-text">${c.content}</div><div class="comment-meta">${c.time} <button class="comment-del" onclick="deleteComment(${c.id},${pid})">✕ Xoá</button></div></div>`;
    list.appendChild(div);
    const cnt = document.getElementById('cmtCount-'+pid);
    cnt.textContent = parseInt(cnt.textContent||0)+1;
  }
}
async function deleteComment(cid,pid) {
  const fd=new FormData(); fd.append('action','delete_comment'); fd.append('comment_id',cid);
  await fetch('community.php',{method:'POST',body:fd});
  document.getElementById('cm-'+cid)?.remove();
  const cnt=document.getElementById('cmtCount-'+pid);
  cnt.textContent=Math.max(0,parseInt(cnt.textContent||0)-1);
}

async function loadMore() {
  const btn = document.getElementById('loadMoreBtn');
  btn.textContent='Đang tải...'; btn.disabled=true;
  const res = await fetch(`community.php?offset=${postOffset}`);
  const html = await res.text();
  const parser = new DOMParser();
  const doc = parser.parseFromString(html,'text/html');
  const newPosts = doc.querySelectorAll('.post-card');
  const feed = document.getElementById('feedList');
  newPosts.forEach(p=>feed.appendChild(p));
  postOffset += newPosts.length;
  if(newPosts.length<15) btn.style.display='none'; else {btn.textContent='Xem thêm ↓'; btn.disabled=false;}
  // reprocess times
  feed.querySelectorAll('.post-time').forEach(el => {
    const raw=el.textContent.trim();
    if(raw.includes('-')) {
      const d=new Date(raw.replace(' ','T'));const diff=(Date.now()-d)/1000;
      if(diff<60)el.textContent='Vừa xong';
      else if(diff<3600)el.textContent=Math.floor(diff/60)+' phút trước';
      else if(diff<86400)el.textContent=Math.floor(diff/3600)+' giờ trước';
      else el.textContent=Math.floor(diff/86400)+' ngày trước';
    }
  });
  feed.querySelectorAll('.like-icon').forEach(el=>{
    if(!el.textContent.trim()||el.textContent.includes('undefined'))
      el.textContent=el.closest('.action-btn')?.classList.contains('liked')?'❤️':'🤍';
  });
}
</script>
</body>
</html>
