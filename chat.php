<?php
require_once __DIR__ . "/db.php"; requireLogin(); $uid = $_SESSION['user_id'];
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat AI — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.chat-page { display: flex; flex-direction: column; height: calc(100vh - 56px); }
.chat-main { flex: 1; display: flex; overflow: hidden; }
.chat-sidebar {
  width: 260px; border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  background: var(--surface);
  overflow: hidden;
  flex-shrink: 0;
}
.chat-sidebar-header {
  padding: 16px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.chat-sidebar-title { font-family: var(--font-display); font-size: 14px; font-weight: 700; color: var(--text); }
.new-chat-btn {
  width: 30px; height: 30px; border-radius: 8px;
  background: var(--accent); color: #fff; border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 18px;
  transition: all 0.2s;
}
.new-chat-btn:hover { background: var(--accent-hover); transform: scale(1.05); }
.mode-list { padding: 10px; flex: 1; overflow-y: auto; }
.mode-item {
  display: flex; align-items: center; gap: 10px; padding: 10px 10px;
  border-radius: 10px; cursor: pointer; transition: all 0.15s;
  margin-bottom: 2px; border: 1.5px solid transparent; color: var(--text2);
  font-size: 13px; font-weight: 500;
}
.mode-item:hover { background: var(--surface2); border-color: var(--border); color: var(--text); }
.mode-item.active { background: var(--accent-soft); border-color: var(--accent-soft); color: var(--accent); font-weight: 600; }
.mode-icon { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 16px; background: var(--surface2); flex-shrink: 0; }
.mode-item.active .mode-icon { background: var(--accent); }

.chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.chat-header {
  padding: 16px 20px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
  background: var(--surface);
}
.ai-status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green-glow, rgba(5,150,105,0.4)); flex-shrink: 0; animation: pulse 2s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
.chat-header-name { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--text); }
.chat-header-sub { font-size: 11px; color: var(--muted); }

.chat-messages {
  flex: 1; overflow-y: auto; padding: 20px;
  display: flex; flex-direction: column; gap: 14px;
  scroll-behavior: smooth;
}
.msg-meta { font-size: 10px; color: var(--muted); padding: 0 44px; margin-top: -8px; }
.msg.user .msg-meta { text-align: right; }

.chat-footer {
  padding: 14px 16px; border-top: 1px solid var(--border);
  background: var(--surface);
}
.chat-input-row {
  display: flex; align-items: flex-end; gap: 8px;
  background: var(--surface2); border: 1.5px solid var(--border);
  border-radius: 16px; padding: 8px 8px 8px 14px;
  transition: all 0.2s;
}
.chat-input-row:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
#chatInput {
  flex: 1; border: none; background: none; outline: none;
  font-family: var(--font); font-size: 14px; color: var(--text);
  resize: none; max-height: 120px; line-height: 1.5;
  padding: 2px 0;
}
#chatInput::placeholder { color: var(--muted); }
.send-btn {
  width: 36px; height: 36px; border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: #fff; border: none; cursor: pointer; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s; box-shadow: 0 2px 8px var(--accent-glow, rgba(79,70,229,0.2));
}
.send-btn:hover { transform: scale(1.05); box-shadow: 0 4px 14px var(--accent-glow, rgba(79,70,229,0.3)); }
.send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.send-btn svg { width: 16px; height: 16px; stroke: #fff; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

.suggestions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.sug-chip {
  padding: 5px 12px; border-radius: 99px;
  background: var(--surface); border: 1px solid var(--border);
  font-size: 12px; color: var(--text2); cursor: pointer;
  transition: all 0.15s; font-weight: 500;
}
.sug-chip:hover { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }

.ai-welcome {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; height: 100%; padding: 2rem; text-align: center;
  color: var(--muted);
}
.ai-welcome-icon { font-size: 48px; margin-bottom: 12px; }
.ai-welcome-title { font-family: var(--font-display); font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.ai-welcome-sub { font-size: 14px; max-width: 320px; line-height: 1.6; }
.quick-topics { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 20px; justify-content: center; }
.quick-topic {
  padding: 8px 16px; border-radius: 99px;
  border: 1.5px solid var(--border); background: var(--surface);
  cursor: pointer; font-size: 13px; font-weight: 600; color: var(--text2);
  transition: all 0.15s;
}
.quick-topic:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); transform: translateY(-2px); }

/* Markdown rendering */
.msg-bubble p { margin-bottom: 6px; }
.msg-bubble ul, .msg-bubble ol { padding-left: 20px; margin: 6px 0; }
.msg-bubble li { margin-bottom: 2px; }
.msg-bubble strong { font-weight: 700; }
.msg-bubble h1, .msg-bubble h2, .msg-bubble h3 { font-family: var(--font-display); font-weight: 700; margin: 10px 0 5px; }
.msg-bubble h3 { font-size: 14px; }
.msg-bubble code { font-family: var(--mono); font-size: 12px; background: rgba(0,0,0,0.08); padding: 1px 5px; border-radius: 4px; }
.msg.user .msg-bubble code { background: rgba(255,255,255,0.15); }
.msg-bubble pre { background: rgba(0,0,0,0.06); border-radius: 8px; padding: 10px; overflow-x: auto; margin: 8px 0; }
.msg-bubble pre code { background: none; }
.msg-bubble blockquote { border-left: 3px solid var(--accent); padding-left: 12px; color: var(--muted); font-style: italic; margin: 6px 0; }

/* Mode-specific accents */
[data-mode="math"] .send-btn { background: linear-gradient(135deg, #059669, #0891b2); }
[data-mode="english"] .send-btn { background: linear-gradient(135deg, #d97706, #dc2626); }
[data-mode="summary"] .send-btn { background: linear-gradient(135deg, #7c3aed, #db2777); }
[data-mode="code"] .send-btn { background: linear-gradient(135deg, #0f172a, #1e293b); }

@media(max-width:768px) { .chat-sidebar { display: none; } }
em, i { font-style: normal !important; }
* { font-style: normal; }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="chat-page">
  <div class="chat-main">
    <!-- SIDEBAR -->
    <div class="chat-sidebar">
      <div class="chat-sidebar-header">
        <span class="chat-sidebar-title">Chế độ AI</span>
        <button class="new-chat-btn" onclick="clearChat()" title="Cuộc trò chuyện mới">+</button>
      </div>
      <div class="mode-list">
        <div class="mode-item active" onclick="setMode('tutor',this)" data-mode="tutor">
          <div class="mode-icon">🧠</div>
          <div><div style="font-weight:700;font-size:13px;">Gia sư AI</div><div style="font-size:11px;color:var(--muted);">Giải thích mọi môn học</div></div>
        </div>
        <div class="mode-item" onclick="setMode('math',this)" data-mode="math">
          <div class="mode-icon">📐</div>
          <div><div style="font-weight:700;font-size:13px;">Giải Toán</div><div style="font-size:11px;color:var(--muted);">Từng bước chi tiết</div></div>
        </div>
        <div class="mode-item" onclick="setMode('english',this)" data-mode="english">
          <div class="mode-icon">🌏</div>
          <div><div style="font-weight:700;font-size:13px;">Luyện Tiếng Anh</div><div style="font-size:11px;color:var(--muted);">Từ vựng, ngữ pháp</div></div>
        </div>
        <div class="mode-item" onclick="setMode('summary',this)" data-mode="summary">
          <div class="mode-icon">📋</div>
          <div><div style="font-weight:700;font-size:13px;">Tóm tắt</div><div style="font-size:11px;color:var(--muted);">Tóm tắt văn bản</div></div>
        </div>
        <div class="mode-item" onclick="setMode('code',this)" data-mode="code">
          <div class="mode-icon">💻</div>
          <div><div style="font-weight:700;font-size:13px;">Lập trình</div><div style="font-size:11px;color:var(--muted);">Hỏi đáp code</div></div>
        </div>
        <div class="mode-item" onclick="setMode('essay',this)" data-mode="essay">
          <div class="mode-icon">✍️</div>
          <div><div style="font-weight:700;font-size:13px;">Viết văn</div><div style="font-size:11px;color:var(--muted);">Soạn thảo, chỉnh sửa</div></div>
        </div>
        <div class="mode-item" onclick="setMode('quiz',this)" data-mode="quiz">
          <div class="mode-icon">🎯</div>
          <div><div style="font-weight:700;font-size:13px;">Quiz AI</div><div style="font-size:11px;color:var(--muted);">Kiểm tra kiến thức</div></div>
        </div>
      </div>
    </div>

    <!-- CHAT AREA -->
    <div class="chat-area" id="chatArea" data-mode="tutor">
      <div class="chat-header">
        <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">🧠</div>
        <div class="ai-status-dot"></div>
        <div>
          <div class="chat-header-name" id="modeTitle">Gia sư AI</div>
          <div class="chat-header-sub" id="modeDesc">Giải thích mọi môn học, từng bước rõ ràng</div>
        </div>
        <button onclick="clearChat()" class="btn btn-ghost btn-sm" style="margin-left:auto;">🗑️ Xóa</button>
        <button onclick="toggleTTS()" class="btn btn-ghost btn-sm" id="ttsBtn" title="Đọc to đáp án">🔊</button>
      </div>

      <div class="chat-messages" id="chatMessages">
        <div class="ai-welcome" id="welcomeScreen">
          <div class="ai-welcome-icon">🧠</div>
          <div class="ai-welcome-title">Xin chào! Tôi là Spark</div>
          <div class="ai-welcome-sub">Trợ lý học tập AI của bạn. Hãy hỏi tôi bất kỳ điều gì về toán học, khoa học, văn học hay bất kỳ môn học nào!</div>
          <div class="quick-topics">
            <div class="quick-topic" onclick="quickAsk('Giải phương trình bậc 2: 2x² - 5x + 3 = 0')">📐 Giải PT bậc 2</div>
            <div class="quick-topic" onclick="quickAsk('Giải thích quang hợp ở thực vật')">🌱 Quang hợp</div>
            <div class="quick-topic" onclick="quickAsk('Cách mạng Tháng Tám 1945 diễn ra như thế nào?')">📜 Lịch sử VN</div>
            <div class="quick-topic" onclick="quickAsk('Giải thích định luật Newton thứ nhất')">⚡ Vật lý Newton</div>
            <div class="quick-topic" onclick="quickAsk('Dạy tôi 10 từ vựng tiếng Anh về công nghệ')">🌍 Từ vựng tech</div>
            <div class="quick-topic" onclick="quickAsk('Cấu trúc dữ liệu stack là gì và cách dùng?')">💻 Stack trong CS</div>
          </div>
        </div>
      </div>

      <div class="chat-footer">
        <div class="chat-input-row">
          <textarea id="chatInput" rows="1" placeholder="Hỏi Spark bất kỳ điều gì..."></textarea>
          <button class="send-btn" id="sendBtn" onclick="sendChat()">
            <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </div>
        <div class="suggestions" id="suggestions">
          <div class="sug-chip" onclick="quickAsk('Giải thích chi tiết hơn')">🔍 Giải thích thêm</div>
          <div class="sug-chip" onclick="quickAsk('Cho tôi ví dụ cụ thể')">💡 Ví dụ cụ thể</div>
          <div class="sug-chip" onclick="quickAsk('Cho tôi bài tập tương tự để luyện tập')">📝 Bài tập thêm</div>
          <div class="sug-chip" onclick="quickAsk('Tóm tắt điểm chính')">📋 Tóm tắt</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
let history = [];
let currentMode = 'tutor';
let ttsEnabled = false;

const modeConfig = {
  tutor: {
    title: 'Gia sư AI', desc: 'Giải thích mọi môn học, từng bước rõ ràng', icon: '🧠',
    system: 'Bạn là Spark, gia sư AI thông minh và thân thiện. Trả lời bằng tiếng Việt, giải thích từng bước rõ ràng với ví dụ cụ thể. Dùng emoji phù hợp, format markdown đẹp (headers, bullet points, bold). Khuyến khích học sinh sau mỗi câu trả lời.',
    placeholder: 'Hỏi Spark bất kỳ điều gì về học tập...'
  },
  math: {
    title: 'Giải Toán', desc: 'Giải từng bước chi tiết với LaTeX', icon: '📐',
    system: 'Bạn là chuyên gia Toán học. Giải bài từng bước cực kỳ chi tiết bằng tiếng Việt. Dùng $...$ cho công thức inline, $$...$$ cho công thức block. Mỗi bước đặt tiêu đề rõ ràng. Kiểm tra lại kết quả ở bước cuối.',
    placeholder: 'Nhập bài toán cần giải...'
  },
  english: {
    title: 'Luyện Tiếng Anh', desc: 'Từ vựng, ngữ pháp, dịch thuật', icon: '🌏',
    system: 'Bạn là giáo viên tiếng Anh chuyên nghiệp. Giải thích cả tiếng Anh lẫn tiếng Việt. Khi dạy từ vựng: thêm phiên âm IPA, từ loại, ví dụ câu. Khi chữa lỗi: giải thích tại sao sai và cách đúng. Thân thiện, khuyến khích học sinh.',
    placeholder: 'Hỏi về từ vựng, ngữ pháp, dịch văn bản...'
  },
  summary: {
    title: 'Tóm tắt thông minh', desc: 'AI tóm tắt văn bản, tài liệu', icon: '📋',
    system: 'Bạn là chuyên gia tóm tắt tài liệu. Tóm tắt theo cấu trúc: 🎯 Chủ đề → 📌 Điểm chính (bullet points) → 💡 Kết luận. Ngắn gọn, súc tích, giữ thông tin quan trọng nhất. Trả lời bằng tiếng Việt.',
    placeholder: 'Dán văn bản cần tóm tắt vào đây...'
  },
  code: {
    title: 'Lập trình AI', desc: 'Hỏi đáp code, debug, giải thích', icon: '💻',
    system: 'Bạn là senior developer. Giải thích code rõ ràng bằng tiếng Việt. Khi viết code: thêm comments giải thích. Khi debug: chỉ ra chính xác lỗi và cách sửa. Cung cấp best practices và cải thiện code khi có thể. Dùng markdown code blocks.',
    placeholder: 'Hỏi về code, gửi code để debug...'
  },
  essay: {
    title: 'Viết văn AI', desc: 'Soạn thảo, chỉnh sửa, ý tưởng', icon: '✍️',
    system: 'Bạn là nhà văn và giáo viên văn học. Giúp viết văn bản chất lượng cao bằng tiếng Việt. Khi chỉnh sửa: đưa ra phiên bản cải thiện và giải thích tại sao. Khi đặt ý tưởng: đưa ra outline chi tiết. Ngôn ngữ phong phú, sáng tạo.',
    placeholder: 'Yêu cầu viết bài hoặc gửi bài để chỉnh sửa...'
  },
  quiz: {
    title: 'Quiz AI', desc: 'Kiểm tra kiến thức với câu hỏi AI', icon: '🎯',
    system: 'Bạn là giáo viên ra đề kiểm tra. Tạo câu hỏi trắc nghiệm hoặc tự luận về chủ đề người dùng yêu cầu. Format: **Câu hỏi:** ... / **A.** / **B.** / **C.** / **D.** / Khi người dùng trả lời, chấm điểm và giải thích. Tạo nhiều cấp độ khó dễ.',
    placeholder: 'Nhập chủ đề muốn kiểm tra...'
  }
};

function setMode(mode, el) {
  currentMode = mode;
  history = [];
  document.querySelectorAll('.mode-item').forEach(m => m.classList.remove('active'));
  el.classList.add('active');
  const cfg = modeConfig[mode];
  document.getElementById('modeTitle').textContent = cfg.title;
  document.getElementById('modeDesc').textContent = cfg.desc;
  document.getElementById('chatInput').placeholder = cfg.placeholder;
  document.getElementById('chatArea').setAttribute('data-mode', mode);
  // Reset chat
  const msgs = document.getElementById('chatMessages');
  msgs.innerHTML = '';
  const welcome = document.createElement('div');
  welcome.className = 'ai-welcome';
  welcome.innerHTML = `<div class="ai-welcome-icon">${cfg.icon}</div><div class="ai-welcome-title">${cfg.title}</div><div class="ai-welcome-sub">${cfg.desc}</div>`;
  msgs.appendChild(welcome);
}

function appendMsg(role, text, time) {
  const msgs = document.getElementById('chatMessages');
  const welcome = msgs.querySelector('.ai-welcome');
  if(welcome) welcome.remove();
  const d = document.createElement('div');
  d.className = 'msg ' + role;
  const timeStr = time || new Date().toLocaleTimeString('vi', {hour:'2-digit',minute:'2-digit'});
  const rendered = role === 'assistant' ? renderMarkdown(text) : escapeHtml(text);
  d.innerHTML = `<div class="msg-avatar">${role==='user'?'👤':'🧠'}</div><div class="msg-bubble">${rendered}</div>`;
  msgs.appendChild(d);
  const meta = document.createElement('div');
  meta.className = 'msg-meta';
  meta.textContent = timeStr;
  msgs.appendChild(meta);
  msgs.scrollTop = msgs.scrollHeight;
  // TTS
  if(role === 'assistant' && ttsEnabled && window.speechSynthesis) {
    const utter = new SpeechSynthesisUtterance(text.replace(/[#*`_]/g,''));
    utter.lang = 'vi-VN';
    window.speechSynthesis.speak(utter);
  }
}

function escapeHtml(t) {
  return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

function renderMarkdown(text) {
  return text
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/```(\w*)\n?([\s\S]*?)```/g,'<pre><code>$2</code></pre>')
    .replace(/`([^`]+)`/g,'<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g,'<em>$1</em>')
    .replace(/^### (.+)$/gm,'<h3>$1</h3>')
    .replace(/^## (.+)$/gm,'<h2>$1</h2>')
    .replace(/^# (.+)$/gm,'<h1>$1</h1>')
    .replace(/^> (.+)$/gm,'<blockquote>$1</blockquote>')
    .replace(/^[-•] (.+)$/gm,'<li>$1</li>')
    .replace(/(<li>.*<\/li>\n?)+/g,'<ul>$&</ul>')
    .replace(/^\d+\. (.+)$/gm,'<li>$1</li>')
    .replace(/\n\n/g,'</p><p>')
    .replace(/\n/g,'<br>');
}

function showTyping() {
  const msgs = document.getElementById('chatMessages');
  const d = document.createElement('div'); d.className='msg assistant'; d.id='typing';
  d.innerHTML='<div class="msg-avatar">🧠</div><div class="msg-bubble"><div class="typing"><span></span><span></span><span></span></div></div>';
  msgs.appendChild(d); msgs.scrollTop = msgs.scrollHeight;
}

function quickAsk(t) {
  document.getElementById('chatInput').value = t;
  sendChat();
}

function clearChat() {
  history = [];
  const msgs = document.getElementById('chatMessages');
  const cfg = modeConfig[currentMode];
  msgs.innerHTML = `<div class="ai-welcome"><div class="ai-welcome-icon">${cfg.icon}</div><div class="ai-welcome-title">${cfg.title}</div><div class="ai-welcome-sub">${cfg.desc}</div></div>`;
  showToast('💬 Cuộc trò chuyện mới', 'info');
}

function toggleTTS() {
  ttsEnabled = !ttsEnabled;
  document.getElementById('ttsBtn').style.opacity = ttsEnabled ? '1' : '0.5';
  showToast(ttsEnabled ? '🔊 Đọc to: Bật' : '🔇 Đọc to: Tắt', 'info');
}

async function sendChat() {
  const input = document.getElementById('chatInput');
  const text = input.value.trim(); if (!text) return;
  input.value = ''; input.style.height = 'auto';
  appendMsg('user', text);
  history.push({role:'user', content: text});
  document.getElementById('sendBtn').disabled = true; showTyping();
  const cfg = modeConfig[currentMode];
  const messages = [{role:'system', content: cfg.system}, ...history];
  try {
    const res = await fetch('ai_api.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({type:'chat', messages})
    });
    const data = await res.json();
    document.getElementById('typing')?.remove();
    const reply = data.result || 'Xin lỗi, có lỗi xảy ra. Thử lại nhé!';
    appendMsg('assistant', reply);
    history.push({role:'assistant', content: reply});
    // MathJax re-render if needed
    if(window.MathJax) MathJax.typesetPromise();
  } catch(e) {
    document.getElementById('typing')?.remove();
    appendMsg('assistant','⚠️ Lỗi kết nối. Vui lòng thử lại!');
  }
  document.getElementById('sendBtn').disabled = false;
  input.focus();
}

function showToast(msg, type='ok') {
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div'); t.className = `toast ${type}`;
  t.textContent = msg; wrap.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = '0.3s'; setTimeout(()=>t.remove(),300); }, 2500);
}

// Auto-resize textarea
document.getElementById('chatInput').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
document.getElementById('chatInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
});
</script>
</body>
</html>
