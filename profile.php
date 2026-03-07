<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid  = $_SESSION['user_id'];
$user = getCurrentUser();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $bio   = trim($_POST['bio']  ?? '');
        $cover = trim($_POST['cover_color'] ?? '#4f6ef7');
        if (mb_strlen($name) < 2) { $err = 'Tên phải có ít nhất 2 ký tự!'; }
        else {
            $st = $db->prepare('UPDATE users SET name=:n,bio=:b,cover_color=:c WHERE id=:id');
            $st->bindValue(':n',$name);$st->bindValue(':b',$bio);
            $st->bindValue(':c',$cover);$st->bindValue(':id',$uid);
            $st->execute();
            $_SESSION['user_name']=$name; $msg='✅ Đã cập nhật hồ sơ!'; $user=getCurrentUser();
        }
    }
    elseif ($action === 'update_avatar') {
        $data = $_POST['avatar_data'] ?? '';
        if ($data && strlen($data) < 600000) {
            $st = $db->prepare('UPDATE users SET avatar=:a WHERE id=:id');
            $st->bindValue(':a',$data); $st->bindValue(':id',$uid); $st->execute();
            $msg='✅ Đã cập nhật ảnh đại diện!'; $user=getCurrentUser();
        } else { $err='Ảnh quá lớn! Chọn ảnh dưới 400KB.'; }
    }
    elseif ($action === 'remove_avatar') {
        $db->exec("UPDATE users SET avatar=NULL WHERE id=$uid");
        $msg='Đã xoá ảnh đại diện!'; $user=getCurrentUser();
    }
    elseif ($action === 'change_email') {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password_check'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err='Email không hợp lệ!'; }
        elseif (!password_verify($pass, $user['password'])) { $err='Mật khẩu xác nhận sai!'; }
        else {
            $ex = $db->query("SELECT id FROM users WHERE email='".SQLite3::escapeString($email)."' AND id!=$uid")->fetchArray();
            if ($ex) { $err='Email đã được dùng bởi tài khoản khác!'; }
            else {
                $st=$db->prepare('UPDATE users SET email=:e WHERE id=:id');
                $st->bindValue(':e',$email);$st->bindValue(':id',$uid);$st->execute();
                $msg='✅ Đã cập nhật email!'; $user=getCurrentUser();
            }
        }
    }
    elseif ($action === 'change_password') {
        $old=$_POST['old_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['conf_password']??'';
        if (!password_verify($old,$user['password'])) { $err='Mật khẩu cũ không đúng!'; }
        elseif (strlen($new)<6) { $err='Mật khẩu mới phải có ít nhất 6 ký tự!'; }
        elseif ($new!==$conf) { $err='Xác nhận mật khẩu không khớp!'; }
        else {
            $st=$db->prepare('UPDATE users SET password=:p WHERE id=:id');
            $st->bindValue(':p',password_hash($new,PASSWORD_DEFAULT));$st->bindValue(':id',$uid);$st->execute();
            $msg='✅ Đổi mật khẩu thành công!';
        }
    }
}

$stats = [
    'posts'  => $db->query("SELECT COUNT(*) as c FROM social_posts WHERE user_id=$uid")->fetchArray()['c'],
    'pomo'   => $db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus'")->fetchArray()['c'],
    'notes'  => $db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid")->fetchArray()['c'],
    'plans'  => $db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND done=1")->fetchArray()['c'],
];
$cover = htmlspecialchars($user['cover_color'] ?? '#4f6ef7');
$coverColors = ['#4f6ef7','#7c3aed','#10b981','#f59e0b','#ef4444','#ec4899','#06b6d4','#f97316','#0f172a','#064e3b'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Hồ sơ cá nhân — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.pf-cover{height:150px;border-radius:16px 16px 0 0;position:relative;overflow:hidden;
  background:linear-gradient(135deg,<?= $cover ?>,<?= $cover ?>dd);}
.pf-cover-pattern{position:absolute;inset:0;opacity:.12;
  background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:20px 20px;}
.pf-avatar-ring{position:absolute;bottom:-44px;left:20px;width:88px;height:88px;
  border-radius:50%;border:4px solid var(--surface);overflow:hidden;cursor:pointer;
  background:var(--surface2);transition:filter .2s;}
.pf-avatar-ring:hover{filter:brightness(.8);}
.pf-avatar-ring::after{content:'📷 Đổi ảnh';position:absolute;inset:0;background:rgba(0,0,0,.5);
  color:#fff;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;
  opacity:0;transition:opacity .2s;border-radius:50%;}
.pf-avatar-ring:hover::after{opacity:1;}
.pf-body{padding:52px 20px 20px;}
.pf-name{font-size:1.5rem;font-weight:800;letter-spacing:-.5px;}
.pf-bio{font-size:13px;color:var(--muted);margin-top:4px;line-height:1.5;}
.pf-meta{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;}
.pf-meta span{font-size:11px;color:var(--muted);font-weight:600;}
.pf-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:14px;}
.pf-stat{background:var(--surface2);border-radius:12px;padding:10px;text-align:center;border:1px solid var(--border);}
.pf-stat-num{font-size:1.3rem;font-weight:800;color:var(--accent);font-family:var(--mono);}
.pf-stat-label{font-size:10px;color:var(--muted);font-weight:700;margin-top:2px;}

.color-swatch{width:28px;height:28px;border-radius:50%;cursor:pointer;border:3px solid transparent;
  transition:all .15s;flex-shrink:0;}
.color-swatch.sel{border-color:var(--text);transform:scale(1.2);}

.settings-tabs{display:flex;gap:4px;background:var(--surface2);border-radius:12px;padding:4px;margin-bottom:20px;}
.stab{flex:1;padding:8px;border-radius:9px;border:none;background:transparent;color:var(--muted);
  cursor:pointer;font-family:var(--font);font-weight:700;font-size:12px;transition:all .15s;}
.stab.active{background:var(--surface);color:var(--text);box-shadow:var(--shadow);}

.form-row{margin-bottom:14px;}
.form-row label{display:block;font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;
  letter-spacing:.5px;margin-bottom:5px;}

.preview-wrap{display:none;margin-top:12px;padding:14px;background:var(--surface2);
  border-radius:12px;border:1.5px dashed var(--border);align-items:center;gap:12px;}
.preview-wrap.show{display:flex;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">

<?php if($msg):?><div class="alert alert-success" style="margin-bottom:1rem;border-radius:12px;padding:12px 16px;">
  <?=htmlspecialchars($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert alert-error" style="margin-bottom:1rem;border-radius:12px;padding:12px 16px;">
  <?=htmlspecialchars($err)?></div><?php endif;?>

<div class="grid-2" style="gap:1.5rem;align-items:start;">

  <!-- ── LEFT: Profile Card ── -->
  <div>
    <div class="card" style="overflow:hidden;margin-bottom:1rem;">
      <!-- Cover -->
      <div class="pf-cover" id="pfCover">
        <div class="pf-cover-pattern"></div>
        <div class="pf-avatar-ring" onclick="document.getElementById('avatarInput').click()">
          <?php if(!empty($user['avatar'])):?>
            <img src="<?=htmlspecialchars($user['avatar'])?>" style="width:100%;height:100%;object-fit:cover;">
          <?php else:
            $colors=['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
            $c=$colors[abs(crc32($user['name']))%count($colors)];
          ?>
            <div style="width:100%;height:100%;background:<?=$c?>;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;">
              <?=mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'))?>
            </div>
          <?php endif;?>
        </div>
      </div>
      <!-- Info -->
      <div class="pf-body">
        <div class="pf-name"><?=htmlspecialchars($user['name'])?></div>
        <div class="pf-bio"><?=htmlspecialchars($user['bio']??'Chưa có bio...')?></div>
        <div class="pf-meta">
          <span>📧 <?=htmlspecialchars($user['email'])?></span>
          <span>📅 Tham gia <?=date('d/m/Y',strtotime($user['created_at']))?></span>
        </div>
        <div class="pf-stats">
          <div class="pf-stat"><div class="pf-stat-num"><?=$stats['posts']?></div><div class="pf-stat-label">Bài đăng</div></div>
          <div class="pf-stat"><div class="pf-stat-num"><?=$stats['pomo']?></div><div class="pf-stat-label">Pomodoro</div></div>
          <div class="pf-stat"><div class="pf-stat-num"><?=$stats['notes']?></div><div class="pf-stat-label">Ghi chú</div></div>
          <div class="pf-stat"><div class="pf-stat-num"><?=$stats['plans']?></div><div class="pf-stat-label">✅ Done</div></div>
        </div>
      </div>
    </div>

    <!-- Avatar upload hidden -->
    <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="handleAvatar(this)">
    <!-- Avatar preview -->
    <div class="card preview-wrap" id="avatarPreview">
      <img id="previewImg" src="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex-shrink:0;border:3px solid var(--border);">
      <div style="flex:1;">
        <div style="font-size:13px;font-weight:700;color:var(--text);">Xem trước ảnh đại diện</div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px;">Ảnh sẽ hiện ở khắp nơi trong app</div>
        <form method="POST" id="avatarForm" style="margin-top:10px;display:flex;gap:6px;">
          <input type="hidden" name="action" value="update_avatar">
          <input type="hidden" name="avatar_data" id="avatarData">
          <button type="submit" class="btn btn-primary btn-sm">💾 Lưu</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="cancelAvatar()">Huỷ</button>
        </form>
      </div>
    </div>
    <?php if(!empty($user['avatar'])):?>
    <form method="POST" style="margin-top:8px;">
      <input type="hidden" name="action" value="remove_avatar">
      <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;color:var(--red);">🗑️ Xoá ảnh đại diện hiện tại</button>
    </form>
    <?php endif;?>

    <!-- Quick links -->
    <div class="card" style="margin-top:1rem;">
      <div class="card-body" style="display:flex;flex-direction:column;gap:6px;padding:12px;">
        <a href="community.php" class="btn btn-ghost" style="justify-content:flex-start;gap:10px;">📝 Trang cộng đồng</a>
        <a href="rooms.php"     class="btn btn-ghost" style="justify-content:flex-start;gap:10px;">💬 Phòng chat</a>
        <a href="dashboard.php" class="btn btn-ghost" style="justify-content:flex-start;gap:10px;">📊 Dashboard</a>
      </div>
    </div>
  </div>

  <!-- ── RIGHT: Settings ── -->
  <div>
    <div class="card">
      <div class="card-header"><div class="card-title">⚙️ Chỉnh sửa tài khoản</div></div>
      <div class="card-body">

        <div class="settings-tabs">
          <button class="stab active" onclick="showTab('profile',this)">👤 Hồ sơ</button>
          <button class="stab" onclick="showTab('email',this)">📧 Email</button>
          <button class="stab" onclick="showTab('password',this)">🔒 Mật khẩu</button>
        </div>

        <!-- TAB: Profile -->
        <div id="tab-profile">
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-row">
              <label>Tên hiển thị</label>
              <input type="text" name="name" class="form-input" value="<?=htmlspecialchars($user['name'])?>" required>
            </div>
            <div class="form-row">
              <label>Bio — Giới thiệu bản thân</label>
              <textarea name="bio" class="form-input" rows="3" placeholder="Học sinh / sinh viên / đam mê..."
                style="resize:none;line-height:1.5;"><?=htmlspecialchars($user['bio']??'')?></textarea>
            </div>
            <div class="form-row">
              <label>Màu bìa hồ sơ</label>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;" id="colorRow">
                <?php foreach($coverColors as $c):?>
                <div class="color-swatch <?=$c===$cover?'sel':''?>" style="background:<?=$c?>;"
                     data-color="<?=$c?>" onclick="pickColor('<?=$c?>',this)"></div>
                <?php endforeach;?>
              </div>
              <input type="hidden" name="cover_color" id="coverInput" value="<?=$cover?>">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">💾 Lưu thay đổi</button>
          </form>
        </div>

        <!-- TAB: Email -->
        <div id="tab-email" style="display:none;">
          <div style="padding:12px;background:var(--surface2);border-radius:10px;margin-bottom:14px;font-size:12px;color:var(--muted);">
            📧 Email hiện tại: <strong style="color:var(--text);"><?=htmlspecialchars($user['email'])?></strong>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="change_email">
            <div class="form-row">
              <label>Email mới</label>
              <input type="email" name="email" class="form-input" placeholder="email@gmail.com" required>
            </div>
            <div class="form-row">
              <label>Xác nhận bằng mật khẩu hiện tại</label>
              <input type="password" name="password_check" class="form-input" placeholder="Nhập mật khẩu để xác nhận" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">📧 Cập nhật email</button>
          </form>
        </div>

        <!-- TAB: Password -->
        <div id="tab-password" style="display:none;">
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-row">
              <label>Mật khẩu hiện tại</label>
              <input type="password" name="old_password" class="form-input" placeholder="••••••••" required>
            </div>
            <div class="form-row">
              <label>Mật khẩu mới <span style="color:var(--muted);font-weight:600;">(ít nhất 6 ký tự)</span></label>
              <input type="password" name="new_password" class="form-input" placeholder="••••••••" required minlength="6">
            </div>
            <div class="form-row">
              <label>Xác nhận mật khẩu mới</label>
              <input type="password" name="conf_password" class="form-input" placeholder="Nhập lại mật khẩu mới" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">🔒 Đổi mật khẩu</button>
          </form>
        </div>

      </div>
    </div>
  </div>

</div>
</div>
<script>
function showTab(tab, btn) {
  ['profile','email','password'].forEach(t => {
    document.getElementById('tab-'+t).style.display = t===tab ? '' : 'none';
  });
  document.querySelectorAll('.stab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
function pickColor(c, el) {
  document.getElementById('coverInput').value = c;
  document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('pfCover').style.background = 'linear-gradient(135deg,'+c+','+c+'dd)';
}
function handleAvatar(input) {
  if (!input.files[0]) return;
  if (input.files[0].size > 500000) { alert('Ảnh quá lớn! Chọn ảnh nhỏ hơn 500KB.'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('previewImg').src = e.target.result;
    document.getElementById('avatarData').value = e.target.result;
    document.getElementById('avatarPreview').classList.add('show');
    document.getElementById('avatarPreview').scrollIntoView({behavior:'smooth',block:'center'});
  };
  reader.readAsDataURL(input.files[0]);
}
function cancelAvatar() {
  document.getElementById('avatarPreview').classList.remove('show');
  document.getElementById('avatarInput').value = '';
}
// Mở đúng tab nếu có lỗi/thành công từ form
<?php if($msg && str_contains($msg,'email')): ?>showTab('email', document.querySelectorAll('.stab')[1]);<?php endif;?>
<?php if($msg && str_contains($msg,'khẩu')): ?>showTab('password', document.querySelectorAll('.stab')[2]);<?php endif;?>
<?php if($err && (str_contains($err,'email')||str_contains($err,'Email'))): ?>showTab('email', document.querySelectorAll('.stab')[1]);<?php endif;?>
<?php if($err && str_contains($err,'khẩu')): ?>showTab('password', document.querySelectorAll('.stab')[2]);<?php endif;?>
</script>
</body>
</html>
