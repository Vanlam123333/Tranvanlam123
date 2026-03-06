<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$GROQ_KEY = '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';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $title = trim($_POST['title'] ?? 'Ghi chú ' . date('d/m'));
        $content = trim($_POST['content'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($content) {
            if ($id) {
                $stmt = $db->prepare('UPDATE notes SET title=:t, content=:c WHERE id=:id AND user_id=:uid');
                $stmt->bindValue(':t', $title); $stmt->bindValue(':c', $content);
                $stmt->bindValue(':id', $id); $stmt->bindValue(':uid', $uid);
                $stmt->execute();
            } else {
                $stmt = $db->prepare('INSERT INTO notes (user_id, title, content) VALUES (:uid, :t, :c)');
                $stmt->bindValue(':uid', $uid); $stmt->bindValue(':t', $title); $stmt->bindValue(':c', $content);
                $stmt->execute();
            }
        }
        header('Location: notes.php'); exit;
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->exec("DELETE FROM notes WHERE id=$id AND user_id=$uid");
        header('Location: notes.php'); exit;
    }
}

$edit_id = (int)($_GET['edit'] ?? 0);
$edit_note = null;
if ($edit_id) {
    $edit_note = $db->query("SELECT * FROM notes WHERE id=$edit_id AND user_id=$uid")->fetchArray(SQLITE3_ASSOC);
}
$notes = $db->query("SELECT * FROM notes WHERE user_id=$uid ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ghi chú — MindSpark</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php
require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <h1 class="page-title">🗒️ Ghi chú thông minh</h1>
  <div class="grid-2" style="align-items:start;">
    <!-- EDITOR -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><?= $edit_note ? '✏️ Chỉnh sửa' : '+ Ghi chú mới' ?></div>
        <?php
require_once __DIR__ . "/db.php"; if ($edit_note): ?><a href="notes.php" class="btn btn-ghost btn-sm">Hủy</a><?php endif; ?>
      </div>
      <div class="card-body">
        <form method="POST" id="noteForm">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $edit_note['id'] ?? 0 ?>">
          <div class="form-group">
            <label class="form-label">Tiêu đề</label>
            <input type="text" name="title" class="form-input" placeholder="Tiêu đề ghi chú..." value="<?= htmlspecialchars($edit_note['title'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Nội dung</label>
            <textarea name="content" id="noteContent" class="form-input" rows="8" placeholder="Ghi nội dung bài học, ý tưởng..."><?= htmlspecialchars($edit_note['content'] ?? '') ?></textarea>
          </div>
          <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">💾 Lưu</button>
            <button type="button" class="btn btn-ghost" onclick="aiSummarize()" id="aiBtn">✨ AI Tóm tắt</button>
          </div>
        </form>
        <div id="aiResult" style="display:none;margin-top:1rem;">
          <div style="font-family:var(--font-head);font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--accent);margin-bottom:0.5rem;">✨ Tóm tắt AI</div>
          <div id="aiText" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:1rem;font-size:0.88rem;line-height:1.8;white-space:pre-wrap;"></div>
        </div>
      </div>
    </div>

    <!-- NOTE LIST -->
    <div>
      <?php
require_once __DIR__ . "/db.php"; $rows=[]; while($n=$notes->fetchArray(SQLITE3_ASSOC)) $rows[]=$n; ?>
      <?php
require_once __DIR__ . "/db.php"; if (count($rows)): ?>
        <?php
require_once __DIR__ . "/db.php"; foreach ($rows as $n): ?>
        <div class="card" style="margin-bottom:0.75rem;">
          <div class="card-body" style="padding:1rem 1.2rem;">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:0.5rem;">
              <div style="flex:1;">
                <div style="font-family:var(--font-head);font-weight:700;font-size:0.95rem;margin-bottom:0.3rem;"><?= htmlspecialchars($n['title']) ?></div>
                <div style="font-size:0.82rem;color:var(--muted);line-height:1.5;"><?= htmlspecialchars(mb_substr($n['content'], 0, 100)) ?>...</div>
                <div style="font-size:0.74rem;color:var(--muted);margin-top:0.4rem;"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
              </div>
              <div style="display:flex;gap:0.4rem;flex-shrink:0;">
                <a href="notes.php?edit=<?= $n['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
                <form method="POST" style="margin:0;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Xóa ghi chú này?')">🗑</button></form>
              </div>
            </div>
          </div>
        </div>
        <?php
require_once __DIR__ . "/db.php"; endforeach; ?>
      <?php
require_once __DIR__ . "/db.php"; else: ?>
        <div class="card"><div class="card-body" style="text-align:center;padding:2rem;color:var(--muted);">
          <div style="font-size:2.5rem;margin-bottom:0.75rem;">📝</div>Chưa có ghi chú nào!
        </div></div>
      <?php
require_once __DIR__ . "/db.php"; endif; ?>
    </div>
  </div>
</div>
<script>
async function aiSummarize() {
  const content = document.getElementById('noteContent').value.trim();
  if (!content) return alert('Hãy nhập nội dung trước!');
  const btn = document.getElementById('aiBtn');
  btn.textContent = '⏳ Đang xử lý...'; btn.disabled = true;
  try {
    const res = await fetch('ai_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ type: 'summarize', text: content })
    });
    const data = await res.json();
    document.getElementById('aiText').textContent = data.result;
    document.getElementById('aiResult').style.display = 'block';
  } catch(e) { alert('Lỗi kết nối AI!'); }
  btn.textContent = '✨ AI Tóm tắt'; btn.disabled = false;
}
</script>
</body>
</html>
