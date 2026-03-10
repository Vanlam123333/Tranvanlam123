<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid  = $_SESSION['user_id'];
$user = getCurrentUser();
$msg = $err = '';

// Migrate cover_image column
@$db->exec("ALTER TABLE users ADD COLUMN cover_image TEXT");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Reload user after any update
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $bio   = trim($_POST['bio']  ?? '');
        $cover = trim($_POST['cover_color'] ?? '#4f6ef7');
        if (mb_strlen($name) < 2) { $err = 'Tên phải có ít nhất 2 ký tự!'; }
        else {
            $st=$db->prepare('UPDATE users SET name=:n,bio=:b,cover_color=:c WHERE id=:id');
            $st->bindValue(':n',$name);$st->bindValue(':b',$bio);
            $st->bindValue(':c',$cover);$st->bindValue(':id',$uid);
            $st->execute();
            $_SESSION['user_name']=$name; $msg='✅ Đã cập nhật hồ sơ!'; $user=getCurrentUser();
        }
    }
    elseif ($action === 'update_avatar') {
        $data=$_POST['avatar_data']??'';
        if ($data && strlen($data)<600000) {
            $st=$db->prepare('UPDATE users SET avatar=:a WHERE id=:id');
            $st->bindValue(':a',$data);$st->bindValue(':id',$uid);$st->execute();
            $msg='✅ Đã cập nhật ảnh đại diện!'; $user=getCurrentUser();
        } else { $err='Ảnh quá lớn! Chọn ảnh dưới 400KB.'; }
    }
    elseif ($action === 'update_cover') {
        $data=$_POST['cover_data']??'';
        if ($data && strlen($data)<2000000) {
            $st=$db->prepare('UPDATE users SET cover_image=:c WHERE id=:id');
            $st->bindValue(':c',$data);$st->bindValue(':id',$uid);$st->execute();
            $msg='✅ Đã cập nhật ảnh bìa!'; $user=getCurrentUser();
        } else { $err='Ảnh bìa quá lớn! Chọn ảnh dưới 1.5MB.'; }
    }
    elseif ($action === 'remove_avatar') {
        $db->exec("UPDATE users SET avatar=NULL WHERE id=$uid");
        $msg='Đã xoá ảnh đại diện!'; $user=getCurrentUser();
    }
    elseif ($action === 'remove_cover') {
        $db->exec("UPDATE users SET cover_image=NULL WHERE id=$uid");
        $msg='Đã xoá ảnh bìa!'; $user=getCurrentUser();
    }
    elseif ($action === 'change_email') {
        $email=trim($_POST['email']??''); $pass=$_POST['password_check']??'';
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $err='Email không hợp lệ!'; }
        elseif (!password_verify($pass,$user['password'])) { $err='Mật khẩu xác nhận sai!'; }
        else {
            $ex=$db->query("SELECT id FROM users WHERE email='".SQLite3::escapeString($email)."' AND id!=$uid")->fetchArray();
            if ($ex) { $err='Email đã được dùng!'; }
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
    'posts'  => (int)$db->query("SELECT COUNT(*) as c FROM social_posts WHERE user_id=$uid")->fetchArray()['c'],
    'pomo'   => (int)$db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus'")->fetchArray()['c'],
    'notes'  => (int)$db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid")->fetchArray()['c'],
    'plans'  => (int)$db->query("SELECT COUNT(*) as c FROM plans WHERE user_id=$uid AND done=1")->fetchArray()['c'],
];

$cover      = htmlspecialchars($user['cover_color'] ?? '#4f6ef7');
$coverImg   = $user['cover_image'] ?? '';
$coverColors = ['#4f6ef7','#7c3aed','#10b981','#f59e0b','#ef4444','#ec4899','#06b6d4','#f97316','#0f172a','#064e3b'];

// Avatar vars
$colors  = ['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
$avatarBg= $colors[abs(crc32($user['name']??''))%count($colors)];
$initial = mb_strtoupper(mb_substr($user['name']??'',0,1,'UTF-8'));
$joinDate = date('d/m/Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Hồ sơ — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.pf-layout{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;max-width:900px;margin:0 auto;}

/* ── Profile card ── */
.pf-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);}

/* Cover photo */
.pf-cover{height:220px;position:relative;overflow:hidden;background:<?=$coverImg?'#000':('linear-gradient(135deg,'.$cover.','.$cover.'cc)')?>;}
<?php if($coverImg): ?>
.pf-cover{background-image:url('<?=htmlspecialchars($coverImg)?>');background-size:cover;background-position:center;}
<?php endif; ?>
.pf-cover-overlay{position:absolute;inset:0;background:linear-gradient(to bottom,transparent 50%,rgba(0,0,0,.3) 100%);}
.pf-cover-pattern{position:absolute;inset:0;opacity:.08;background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:22px 22px;}
<?php if($coverImg): ?>.pf-cover-pattern{display:none;}<?php endif;?>

.pf-cover-edit-btn{position:absolute;bottom:12px;right:14px;display:flex;align-items:center;gap:7px;
  padding:8px 14px;background:rgba(0,0,0,.55);backdrop-filter:blur(8px);border:1.5px solid rgba(255,255,255,.2);
  border-radius:22px;color:#fff;cursor:pointer;font-size:12px;font-weight:700;transition:all .15s;font-family:var(--font);}
.pf-cover-edit-btn:hover{background:rgba(0,0,0,.75);}

/* Avatar area */
.pf-avatar-row{display:flex;align-items:flex-end;justify-content:space-between;padding:0 20px;margin-top:-44px;margin-bottom:14px;position:relative;z-index:2;}
.pf-avatar-wrap{position:relative;cursor:pointer;flex-shrink:0;}
.pf-avatar{width:100px;height:100px;border-radius:50%;border:4px solid var(--surface);
  overflow:hidden;background:var(--surface2);transition:filter .2s;box-shadow:0 4px 16px rgba(0,0,0,.15);}
.pf-avatar:hover{filter:brightness(.8);}
.pf-avatar-edit-badge{position:absolute;bottom:4px;right:4px;width:28px;height:28px;border-radius:50%;
  background:var(--surface2);border:2px solid var(--surface);display:flex;align-items:center;
  justify-content:center;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.15);}
.pf-avatar-actions{display:flex;gap:8px;padding-bottom:8px;}
.pf-edit-profile-btn{display:flex;align-items:center;gap:7px;padding:8px 18px;
  background:var(--surface2);border:1.5px solid var(--border);border-radius:22px;
  color:var(--text);cursor:pointer;font-size:13px;font-weight:700;transition:all .15s;font-family:var(--font);}
.pf-edit-profile-btn:hover{background:var(--border);}

/* Profile info */
.pf-info{padding:0 20px 20px;}
.pf-name{font-size:1.6rem;font-weight:900;color:var(--text);letter-spacing:-.5px;margin-bottom:4px;}
.pf-bio{font-size:14px;color:var(--text2);line-height:1.6;margin-bottom:10px;}
.pf-meta-row{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:14px;}
.pf-meta-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);font-weight:600;}
.pf-stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
.pf-stat{text-align:center;padding:12px 8px;background:var(--surface2);border-radius:12px;border:1px solid var(--border);}
.pf-stat-num{font-size:1.4rem;font-weight:900;color:var(--accent);font-family:var(--mono);display:block;}
.pf-stat-label{font-size:10px;color:var(--muted);font-weight:700;margin-top:2px;display:block;}

/* ── Quick links card ── */
.quick-links-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:16px;box-shadow:0 1px 6px rgba(0,0,0,.05);}
.quick-links-title{font-size:13px;font-weight:800;color:var(--text);margin-bottom:12px;}
.quick-link-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;
  text-decoration:none;color:var(--text2);font-size:13px;font-weight:600;transition:background .12s;margin-bottom:2px;}
.quick-link-item:hover{background:var(--surface2);color:var(--text);}
.quick-link-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}

/* ── Settings panel ── */
.settings-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);}
.settings-header{padding:16px 20px;border-bottom:1px solid var(--border);}
.settings-title{font-size:14px;font-weight:800;color:var(--text);}
.settings-tabs{display:flex;border-bottom:1px solid var(--border);}
.stab{flex:1;padding:12px 8px;border:none;background:none;cursor:pointer;font-family:var(--font);
  font-size:12px;font-weight:700;color:var(--muted);border-bottom:2.5px solid transparent;
  transition:all .15s;margin-bottom:-1px;}
.stab:hover{color:var(--text);background:var(--surface2);}
.stab.active{color:var(--accent);border-bottom-color:var(--accent);}
.settings-body{padding:20px;}

.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;
  letter-spacing:.6px;margin-bottom:6px;}
.form-input-enhanced{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;
  font-family:var(--font);font-size:13px;color:var(--text);background:var(--surface2);outline:none;
  transition:all .15s;box-sizing:border-box;}
.form-input-enhanced:focus{border-color:var(--accent);background:var(--surface);box-shadow:0 0 0 3px var(--accent-soft);}
textarea.form-input-enhanced{resize:vertical;min-height:80px;line-height:1.5;}

.color-grid{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;}
.color-swatch{width:30px;height:30px;border-radius:50%;cursor:pointer;border:3px solid transparent;
  transition:all .15s;position:relative;}
.color-swatch.sel{border-color:var(--text);transform:scale(1.2);}
.color-swatch.sel::after{content:'✓';position:absolute;inset:0;display:flex;align-items:center;
  justify-content:center;font-size:12px;font-weight:900;color:#fff;}

.save-btn{width:100%;padding:11px;border-radius:10px;background:var(--accent);border:none;
  color:#fff;font-family:var(--font);font-size:14px;font-weight:800;cursor:pointer;transition:all .15s;}
.save-btn:hover{opacity:.9;}

.info-box{padding:12px 14px;background:var(--surface2);border-radius:10px;margin-bottom:16px;
  font-size:12px;color:var(--muted);border:1px solid var(--border);}
.info-box strong{color:var(--text);}

/* Notification flash */
.flash-msg{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13px;font-weight:700;
  display:flex;align-items:center;gap:8px;}
.flash-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
.flash-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

/* Avatar/cover upload preview */
.upload-preview-modal{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:300;
  display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.upload-preview-modal.show{display:flex;}
.upload-preview-box{background:var(--surface);border-radius:16px;padding:24px;width:380px;max-width:92vw;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.25);}
.upload-preview-box img{border-radius:10px;max-height:220px;max-width:100%;object-fit:cover;margin-bottom:16px;}
.upload-preview-actions{display:flex;gap:10px;}
.upload-preview-actions button{flex:1;padding:10px;border-radius:10px;font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;border:none;}

@media(max-width:700px){
  .pf-layout{grid-template-columns:1fr;}
  .pf-stats-row{grid-template-columns:repeat(2,1fr);}
}
em, i { font-style: normal !important; }
* { font-style: normal; }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">

<?php if($msg): ?>
<div class="flash-msg flash-success">✅ <?=htmlspecialchars($msg)?></div>
<?php endif; ?>
<?php if($err): ?>
<div class="flash-msg flash-error">❌ <?=htmlspecialchars($err)?></div>
<?php endif; ?>

<div class="pf-layout">

  <!-- LEFT COLUMN -->
  <div>

    <!-- Main Profile Card -->
    <div class="pf-card">
      <!-- Cover Photo -->
      <div class="pf-cover" id="pfCoverEl">
        <div class="pf-cover-pattern"></div>
        <div class="pf-cover-overlay"></div>
        <button class="pf-cover-edit-btn" onclick="document.getElementById('coverInput').click()">
          📷 Đổi ảnh bìa
        </button>
      </div>
      <input type="file" id="coverInput" accept="image/*" style="display:none" onchange="handleCoverUpload(this)">

      <!-- Avatar row -->
      <div class="pf-avatar-row">
        <div class="pf-avatar-wrap" onclick="document.getElementById('avatarInput').click()" title="Đổi ảnh đại diện">
          <div class="pf-avatar" id="pfAvatarEl">
            <?php if(!empty($user['avatar'])): ?>
              <img src="<?=htmlspecialchars($user['avatar'])?>" style="width:100%;height:100%;object-fit:cover;" id="pfAvatarImg">
            <?php else: ?>
              <div id="pfAvatarImg" style="width:100%;height:100%;background:<?=$avatarBg?>;display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:900;color:#fff;"><?=$initial?></div>
            <?php endif; ?>
          </div>
          <div class="pf-avatar-edit-badge">📷</div>
        </div>
        <div class="pf-avatar-actions">
          <button class="pf-edit-profile-btn" onclick="openEditPanel()">✏️ Chỉnh sửa</button>
        </div>
      </div>
      <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="handleAvatarUpload(this)">

      <!-- Profile Info -->
      <div class="pf-info">
        <div class="pf-name"><?=htmlspecialchars($user['name'])?></div>
        <?php if(!empty($user['bio'])): ?>
        <div class="pf-bio"><?=htmlspecialchars($user['bio'])?></div>
        <?php else: ?>
        <div class="pf-bio" style="font-style:italic;opacity:.5;">Chưa có bio · <a href="#" onclick="openEditPanel()" style="color:var(--accent)">Thêm ngay</a></div>
        <?php endif; ?>
        <div class="pf-meta-row">
          <div class="pf-meta-item">📧 <?=htmlspecialchars($user['email'])?></div>
          <div class="pf-meta-item">📅 Tham gia <?=$joinDate?></div>
        </div>
        <div class="pf-stats-row">
          <div class="pf-stat"><span class="pf-stat-num"><?=$stats['posts']?></span><span class="pf-stat-label">Bài đăng</span></div>
          <div class="pf-stat"><span class="pf-stat-num"><?=$stats['pomo']?></span><span class="pf-stat-label">Pomodoro</span></div>
          <div class="pf-stat"><span class="pf-stat-num"><?=$stats['notes']?></span><span class="pf-stat-label">Ghi chú</span></div>
          <div class="pf-stat"><span class="pf-stat-num"><?=$stats['plans']?></span><span class="pf-stat-label">Đã hoàn thành</span></div>
        </div>
      </div>
    </div>

    <!-- Quick links -->
    <div class="quick-links-card">
      <div class="quick-links-title">Điều hướng nhanh</div>
      <a href="community.php" class="quick-link-item">
        <div class="quick-link-icon" style="background:#e0e7ff;">📝</div>
        Trang cộng đồng
      </a>
      <a href="rooms.php" class="quick-link-item">
        <div class="quick-link-icon" style="background:#d1fae5;">💬</div>
        Phòng chat
      </a>
      <a href="dashboard.php" class="quick-link-item">
        <div class="quick-link-icon" style="background:#fef3c7;">📊</div>
        Dashboard
      </a>
      <a href="planner.php" class="quick-link-item">
        <div class="quick-link-icon" style="background:#fce7f3;">📅</div>
        Kế hoạch học tập
      </a>
    </div>
  </div>

  <!-- RIGHT COLUMN: Settings -->
  <div id="settingsPanel">
    <div class="settings-card">
      <div class="settings-header">
        <div class="settings-title">⚙️ Cài đặt tài khoản</div>
      </div>
      <div class="settings-tabs">
        <button class="stab active" onclick="showTab('profile',this)">👤 Hồ sơ</button>
        <button class="stab" onclick="showTab('email',this)">📧 Email</button>
        <button class="stab" onclick="showTab('password',this)">🔒 Bảo mật</button>
      </div>

      <div class="settings-body">

        <!-- TAB: Profile -->
        <div id="tab-profile">
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
              <label class="form-label">Tên hiển thị</label>
              <input type="text" name="name" class="form-input-enhanced"
                value="<?=htmlspecialchars($user['name'])?>" required maxlength="50">
            </div>
            <div class="form-group">
              <label class="form-label">Bio</label>
              <textarea name="bio" class="form-input-enhanced"
                placeholder="Học sinh / sinh viên / đam mê..."><?=htmlspecialchars($user['bio']??'')?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Màu chủ đạo hồ sơ</label>
              <div class="color-grid" id="colorRow">
                <?php foreach($coverColors as $c):?>
                <div class="color-swatch <?=$c===$cover?'sel':''?>" style="background:<?=$c?>;"
                     onclick="pickColor('<?=$c?>',this)" title="<?=$c?>"></div>
                <?php endforeach;?>
              </div>
              <input type="hidden" name="cover_color" id="coverColorInput" value="<?=$cover?>">
            </div>
            <button type="submit" class="save-btn">💾 Lưu thay đổi</button>
          </form>

          <!-- Avatar management -->
          <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
            <div class="form-label" style="margin-bottom:10px;">Ảnh đại diện</div>
            <div style="display:flex;gap:10px;">
              <button onclick="document.getElementById('avatarInput').click()"
                style="flex:1;padding:9px;border-radius:10px;border:1.5px dashed var(--border);background:var(--surface2);cursor:pointer;font-family:var(--font);font-size:12px;font-weight:700;color:var(--muted);transition:all .15s;"
                onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                📷 Đổi ảnh đại diện
              </button>
              <?php if(!empty($user['avatar'])): ?>
              <form method="POST" style="flex:1;">
                <input type="hidden" name="action" value="remove_avatar">
                <button type="submit" style="width:100%;padding:9px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface2);cursor:pointer;font-family:var(--font);font-size:12px;font-weight:700;color:#ef4444;transition:all .15s;">
                  🗑️ Xoá ảnh
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>

          <!-- Cover image management -->
          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
            <div class="form-label" style="margin-bottom:10px;">Ảnh bìa</div>
            <div style="display:flex;gap:10px;">
              <button onclick="document.getElementById('coverInput').click()"
                style="flex:1;padding:9px;border-radius:10px;border:1.5px dashed var(--border);background:var(--surface2);cursor:pointer;font-family:var(--font);font-size:12px;font-weight:700;color:var(--muted);transition:all .15s;"
                onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                🖼️ Đổi ảnh bìa
              </button>
              <?php if(!empty($user['cover_image'])): ?>
              <form method="POST" style="flex:1;">
                <input type="hidden" name="action" value="remove_cover">
                <button type="submit" style="width:100%;padding:9px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface2);cursor:pointer;font-family:var(--font);font-size:12px;font-weight:700;color:#ef4444;transition:all .15s;">
                  🗑️ Xoá bìa
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- TAB: Email -->
        <div id="tab-email" style="display:none;">
          <div class="info-box">
            📧 Email hiện tại: <strong><?=htmlspecialchars($user['email'])?></strong>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="change_email">
            <div class="form-group">
              <label class="form-label">Email mới</label>
              <input type="email" name="email" class="form-input-enhanced" placeholder="email@gmail.com" required>
            </div>
            <div class="form-group">
              <label class="form-label">Xác nhận bằng mật khẩu hiện tại</label>
              <input type="password" name="password_check" class="form-input-enhanced" placeholder="••••••••" required>
            </div>
            <button type="submit" class="save-btn">📧 Cập nhật email</button>
          </form>
        </div>

        <!-- TAB: Password -->
        <div id="tab-password" style="display:none;">
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label class="form-label">Mật khẩu hiện tại</label>
              <input type="password" name="old_password" class="form-input-enhanced" placeholder="••••••••" required>
            </div>
            <div class="form-group">
              <label class="form-label">Mật khẩu mới <span style="color:var(--muted);text-transform:none;font-size:10px;">(ít nhất 6 ký tự)</span></label>
              <input type="password" name="new_password" class="form-input-enhanced" placeholder="••••••••" required minlength="6">
            </div>
            <div class="form-group">
              <label class="form-label">Nhập lại mật khẩu mới</label>
              <input type="password" name="conf_password" class="form-input-enhanced" placeholder="••••••••" required>
            </div>
            <button type="submit" class="save-btn">🔒 Đổi mật khẩu</button>
          </form>
        </div>

      </div>
    </div>
  </div>

</div>
</div>

<!-- Avatar upload preview modal -->
<div class="upload-preview-modal" id="avatarPreviewModal">
  <div class="upload-preview-box">
    <div style="font-size:15px;font-weight:800;margin-bottom:14px;">Xem trước ảnh đại diện</div>
    <img id="avatarPreviewImg" src="" style="border-radius:50%;width:120px;height:120px;object-fit:cover;border:4px solid var(--border);">
    <form method="POST" id="avatarForm" style="margin-top:16px;">
      <input type="hidden" name="action" value="update_avatar">
      <input type="hidden" name="avatar_data" id="avatarDataInput">
      <div class="upload-preview-actions">
        <button type="button" onclick="closeAvatarModal()" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);">Huỷ</button>
        <button type="submit" style="background:var(--accent);color:#fff;">💾 Lưu</button>
      </div>
    </form>
  </div>
</div>

<!-- Cover upload preview modal -->
<div class="upload-preview-modal" id="coverPreviewModal">
  <div class="upload-preview-box">
    <div style="font-size:15px;font-weight:800;margin-bottom:14px;">Xem trước ảnh bìa</div>
    <img id="coverPreviewImg" src="" style="width:100%;height:150px;object-fit:cover;border-radius:10px;margin-bottom:0;">
    <form method="POST" id="coverForm" style="margin-top:14px;">
      <input type="hidden" name="action" value="update_cover">
      <input type="hidden" name="cover_data" id="coverDataInput">
      <div class="upload-preview-actions">
        <button type="button" onclick="closeCoverModal()" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);">Huỷ</button>
        <button type="submit" style="background:var(--accent);color:#fff;">💾 Lưu ảnh bìa</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ── Tabs ── */
function showTab(tab, btn) {
  ['profile','email','password'].forEach(t => {
    document.getElementById('tab-'+t).style.display = t===tab?'':'none';
  });
  document.querySelectorAll('.stab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
function openEditPanel() {
  document.getElementById('settingsPanel').scrollIntoView({behavior:'smooth',block:'start'});
}

/* ── Color picker ── */
function pickColor(c, el) {
  document.getElementById('coverColorInput').value = c;
  document.querySelectorAll('.color-swatch').forEach(s=>s.classList.remove('sel'));
  el.classList.add('sel');
  // Preview cover gradient live
  const cover = document.getElementById('pfCoverEl');
  if(!cover.style.backgroundImage || cover.style.backgroundImage==='none' || cover.dataset.hasImg!=='true') {
    cover.style.background = 'linear-gradient(135deg,'+c+','+c+'cc)';
  }
}

/* ── Avatar upload ── */
function handleAvatarUpload(input) {
  if (!input.files[0]) return;
  if (input.files[0].size > 500000) { alert('Ảnh quá lớn! Chọn ảnh nhỏ hơn 500KB.'); return; }
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('avatarPreviewImg').src = e.target.result;
    document.getElementById('avatarDataInput').value = e.target.result;
    document.getElementById('avatarPreviewModal').classList.add('show');
  };
  r.readAsDataURL(input.files[0]);
}
function closeAvatarModal() {
  document.getElementById('avatarPreviewModal').classList.remove('show');
  document.getElementById('avatarInput').value='';
}

/* ── Cover upload ── */
function handleCoverUpload(input) {
  if (!input.files[0]) return;
  if (input.files[0].size > 2000000) { alert('Ảnh bìa quá lớn! Chọn ảnh dưới 1.5MB.'); return; }
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('coverPreviewImg').src = e.target.result;
    document.getElementById('coverDataInput').value = e.target.result;
    document.getElementById('coverPreviewModal').classList.add('show');
    // Live preview
    const cover = document.getElementById('pfCoverEl');
    cover.style.backgroundImage = 'url('+e.target.result+')';
    cover.style.backgroundSize = 'cover';
    cover.style.backgroundPosition = 'center';
    cover.dataset.hasImg = 'true';
    const pattern = cover.querySelector('.pf-cover-pattern');
    if(pattern) pattern.style.display='none';
  };
  r.readAsDataURL(input.files[0]);
}
function closeCoverModal() {
  document.getElementById('coverPreviewModal').classList.remove('show');
  document.getElementById('coverInput').value='';
}

/* Auto-open correct tab based on server message */
<?php if($msg && str_contains($msg,'email')): ?>showTab('email',document.querySelectorAll('.stab')[1]);<?php endif;?>
<?php if($msg && str_contains($msg,'khẩu')): ?>showTab('password',document.querySelectorAll('.stab')[2]);<?php endif;?>
<?php if($err && (str_contains($err,'email')||str_contains($err,'Email'))): ?>showTab('email',document.querySelectorAll('.stab')[1]);<?php endif;?>
<?php if($err && str_contains($err,'khẩu')): ?>showTab('password',document.querySelectorAll('.stab')[2]);<?php endif;?>
</script>
</body>
</html>
