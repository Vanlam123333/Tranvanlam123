<?php
require_once __DIR__ . "/db.php"; requireLogin(); $uid = $_SESSION['user_id']; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat AI — MindSpark</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php
require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <h1 class="page-title">🧠 Gia sư AI</h1>
  <div class="card">
    <div class="card-header">
      <div class="card-title">Hỏi bất kỳ câu gì</div>
      <button class="btn btn-ghost btn-sm" onclick="clearChat()">🗑 Xóa</button>
    </div>
    <div class="card-body" style="padding:1rem;">
      <div class="chat-box" id="chatBox">
        <div class="msg assistant">
          <div class="msg-avatar">🧠</div>
          <div class="msg-bubble">Xin chào! Mình là gia sư AI 👋 Bạn cần giải thích bài gì hôm nay?</div>
        </div>
      </div>
      <div class="row center" style="margin-top:0.9rem;">
        <input type="text" id="chatInput" class="form-input grow" placeholder="Hỏi bất kỳ bài tập, khái niệm..." onkeydown="if(event.key==='Enter')sendChat()">
        <button class="btn btn-primary" onclick="sendChat()" id="sendBtn">Gửi ➤</button>
      </div>
      <div style="margin-top:0.6rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
        <span style="font-size:0.76rem;color:var(--muted);">Gợi ý:</span>
        <button class="btn btn-ghost btn-sm" onclick="quickAsk('Giải phương trình bậc 2: x² - 5x + 6 = 0')">Toán PT bậc 2</button>
        <button class="btn btn-ghost btn-sm" onclick="quickAsk('Cách mạng Pháp 1789 là gì?')">Lịch sử</button>
        <button class="btn btn-ghost btn-sm" onclick="quickAsk('Giải thích quang hợp đơn giản')">Sinh học</button>
      </div>
    </div>
  </div>
</div>
<script>
let history = [];
function appendMsg(role, text) {
  const box = document.getElementById('chatBox');
  const d = document.createElement('div');
  d.className = 'msg ' + role;
  d.innerHTML = `<div class="msg-avatar">${role==='user'?'👤':'🧠'}</div><div class="msg-bubble">${text.replace(/\n/g,'<br>')}</div>`;
  box.appendChild(d); box.scrollTop = box.scrollHeight;
}
function showTyping() {
  const box = document.getElementById('chatBox');
  const d = document.createElement('div'); d.className='msg assistant'; d.id='typing';
  d.innerHTML='<div class="msg-avatar">🧠</div><div class="msg-bubble"><div class="typing"><span></span><span></span><span></span></div></div>';
  box.appendChild(d); box.scrollTop = box.scrollHeight;
}
function quickAsk(t) { document.getElementById('chatInput').value=t; sendChat(); }
function clearChat() { history=[]; document.getElementById('chatBox').innerHTML='<div class="msg assistant"><div class="msg-avatar">🧠</div><div class="msg-bubble">Đã xóa! Hỏi gì đi bạn 😊</div></div>'; }
async function sendChat() {
  const input = document.getElementById('chatInput');
  const text = input.value.trim(); if (!text) return;
  input.value=''; appendMsg('user', text);
  history.push({role:'user', content:text});
  document.getElementById('sendBtn').disabled=true; showTyping();
  try {
    const res = await fetch('ai_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({type:'chat', messages:history}) });
    const data = await res.json();
    document.getElementById('typing')?.remove();
    appendMsg('assistant', data.result);
    history.push({role:'assistant', content:data.result});
  } catch(e) { document.getElementById('typing')?.remove(); appendMsg('assistant','⚠️ Lỗi kết nối!'); }
  document.getElementById('sendBtn').disabled=false;
}
</script>
</body>
</html>
