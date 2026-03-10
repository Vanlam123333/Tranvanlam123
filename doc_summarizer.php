<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tóm tắt tài liệu AI — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.ds-layout { display:grid; grid-template-columns:340px 1fr; gap:20px; align-items:start; }
@media(max-width:768px){.ds-layout{grid-template-columns:1fr;}}
.ds-sidebar { position:sticky; top:74px; }
.output-section { background:var(--surface); border:1.5px solid var(--border); border-radius:14px; padding:1.25rem; margin-bottom:14px; }
.output-section-title { font-size:13px; font-weight:800; color:var(--text); margin-bottom:10px; display:flex; align-items:center; gap:6px; }
.summary-text { font-size:14px; line-height:1.8; color:var(--text2); white-space:pre-wrap; }
.bullet-list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
.bullet-list li { display:flex; gap:10px; font-size:13px; color:var(--text2); line-height:1.6; }
.bullet-list li::before { content:'→'; color:var(--accent); font-weight:800; flex-shrink:0; }
.mode-tabs { display:flex; gap:4px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:4px; margin-bottom:14px; }
.mode-tab { flex:1; padding:7px; border:none; border-radius:7px; background:transparent; color:var(--muted); font-family:var(--font); font-size:12px; font-weight:700; cursor:pointer; transition:all 0.15s; text-align:center; }
.mode-tab.active { background:var(--surface); color:var(--text); box-shadow:var(--shadow); }
.quiz-card { background:var(--surface2); border-radius:12px; padding:14px; margin-bottom:10px; }
.quiz-q { font-size:13px; font-weight:700; color:var(--text); margin-bottom:8px; }
.quiz-opts { display:flex; flex-direction:column; gap:6px; }
.quiz-opt { padding:8px 12px; border-radius:8px; border:1.5px solid var(--border); background:var(--surface); font-size:12px; cursor:pointer; transition:all 0.15s; text-align:left; font-family:var(--font); color:var(--text2); }
.quiz-opt:hover { border-color:var(--accent); color:var(--accent); }
.quiz-opt.correct { border-color:var(--green); background:var(--green-soft); color:var(--green); }
.quiz-opt.wrong   { border-color:var(--red);   background:var(--red-soft);   color:var(--red);   }
.mindmap-preview { background:var(--surface2); border-radius:12px; padding:1rem; font-size:12px; line-height:2; color:var(--text2); max-height:300px; overflow-y:auto; }
.loading-pulse { display:flex; align-items:center; gap:10px; padding:2rem; color:var(--muted); font-size:13px; }
.dot-pulse { display:flex; gap:4px; }
.dot-pulse span { width:6px; height:6px; border-radius:50%; background:var(--accent); animation:dp 1.2s infinite; }
.dot-pulse span:nth-child(2){animation-delay:.2s}.dot-pulse span:nth-child(3){animation-delay:.4s}
@keyframes dp{0%,80%,100%{transform:scale(0.6);opacity:0.4}40%{transform:scale(1);opacity:1}}
.word-count { font-size:11px; color:var(--muted); margin-top:6px; }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">AI học tập</div>
    <h1 class="page-title">Tóm tắt tài liệu AI</h1>
  </div>

  <div class="ds-layout">
    <!-- SIDEBAR INPUT -->
    <div class="ds-sidebar">
      <div class="card">
        <div class="card-body" style="padding:16px;">
          <div class="mode-tabs">
            <button class="mode-tab active" id="tabText" onclick="switchMode('text')">📝 Dán văn bản</button>
            <button class="mode-tab" id="tabTopic" onclick="switchMode('topic')">💡 Nhập chủ đề</button>
          </div>

          <div id="modeText">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;">Dán nội dung cần tóm tắt</div>
            <textarea id="docInput" class="form-input" style="width:100%;min-height:200px;resize:vertical;font-size:13px;line-height:1.6;" placeholder="Dán văn bản, bài giảng, tài liệu học tập vào đây..."></textarea>
            <div class="word-count" id="wordCount">0 từ</div>
          </div>

          <div id="modeTopic" style="display:none;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;">Chủ đề cần học</div>
            <input type="text" id="topicInput" class="form-input" style="width:100%" placeholder="VD: Quang hợp, Chiến tranh thế giới 2...">
            <div style="margin-top:10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;">Cấp độ</div>
            <select id="levelSelect" class="form-input" style="width:100%">
              <option value="cơ bản">Cơ bản (phổ thông)</option>
              <option value="trung bình" selected>Trung bình (đại học)</option>
              <option value="nâng cao">Nâng cao (chuyên sâu)</option>
            </select>
          </div>

          <div style="margin-top:12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;">Xuất ra</div>
          <div style="display:flex;flex-direction:column;gap:6px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="outSummary" checked> 📄 Tóm tắt ngắn gọn</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="outBullets" checked> • Ý chính (bullet points)</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="outQuiz"> 🎯 Quiz tự động (5 câu)</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="outMindmap"> 🧠 Sơ đồ tư duy</label>
          </div>

          <button class="btn btn-primary" id="analyzeBtn" onclick="analyze()" style="width:100%;margin-top:14px;">✨ Phân tích & Tóm tắt</button>
        </div>
      </div>
    </div>

    <!-- OUTPUT -->
    <div id="outputArea">
      <div class="card">
        <div style="text-align:center;padding:4rem 2rem;color:var(--muted);">
          <div style="font-size:3rem;margin-bottom:12px;opacity:0.4;">🤖</div>
          <div style="font-size:14px;font-weight:500;">Dán tài liệu vào và bấm <strong>Phân tích</strong></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let inputMode = 'text';
let quizData = [];

function switchMode(m) {
  inputMode = m;
  document.getElementById('modeText').style.display  = m==='text'  ? 'block':'none';
  document.getElementById('modeTopic').style.display = m==='topic' ? 'block':'none';
  document.getElementById('tabText').classList.toggle('active', m==='text');
  document.getElementById('tabTopic').classList.toggle('active', m==='topic');
}

document.getElementById('docInput').addEventListener('input', function() {
  const words = this.value.trim().split(/\s+/).filter(Boolean).length;
  document.getElementById('wordCount').textContent = words + ' từ';
});

async function analyze() {
  const btn = document.getElementById('analyzeBtn');
  btn.disabled = true; btn.textContent = '⏳ Đang phân tích...';

  const wantSummary  = document.getElementById('outSummary').checked;
  const wantBullets  = document.getElementById('outBullets').checked;
  const wantQuiz     = document.getElementById('outQuiz').checked;
  const wantMindmap  = document.getElementById('outMindmap').checked;

  let content = '';
  if (inputMode === 'text') {
    content = document.getElementById('docInput').value.trim();
    if (!content) { alert('Nhập nội dung đi!'); btn.disabled=false; btn.textContent='✨ Phân tích & Tóm tắt'; return; }
  } else {
    const topic = document.getElementById('topicInput').value.trim();
    const level = document.getElementById('levelSelect').value;
    if (!topic) { alert('Nhập chủ đề đi!'); btn.disabled=false; btn.textContent='✨ Phân tích & Tóm tắt'; return; }
    content = `Hãy giải thích chi tiết về chủ đề: "${topic}" ở mức độ ${level}.`;
  }

  const outputs = [];
  if (wantSummary)  outputs.push('summary');
  if (wantBullets)  outputs.push('bullets');
  if (wantQuiz)     outputs.push('quiz');
  if (wantMindmap)  outputs.push('mindmap');
  if (!outputs.length) { alert('Chọn ít nhất 1 loại output!'); btn.disabled=false; btn.textContent='✨ Phân tích & Tóm tắt'; return; }

  document.getElementById('outputArea').innerHTML = `<div class="loading-pulse"><div class="dot-pulse"><span></span><span></span><span></span></div> AI đang đọc và phân tích tài liệu...</div>`;

  try {
    const res = await fetch('ai_api.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ type:'doc_summarize', content: content.substring(0,8000), outputs })
    });
    const data = await res.json();
    renderOutput(data, wantSummary, wantBullets, wantQuiz, wantMindmap);
  } catch(e) {
    document.getElementById('outputArea').innerHTML = '<div class="card"><div style="padding:2rem;color:var(--red);text-align:center;">❌ Lỗi kết nối AI</div></div>';
  }
  btn.disabled=false; btn.textContent='✨ Phân tích & Tóm tắt';
}

function renderOutput(data, wantSummary, wantBullets, wantQuiz, wantMindmap) {
  let html = '';
  if (wantSummary && data.summary) {
    html += `<div class="output-section"><div class="output-section-title">📄 Tóm tắt</div><div class="summary-text">${escHtml(data.summary)}</div></div>`;
  }
  if (wantBullets && data.bullets && data.bullets.length) {
    html += `<div class="output-section"><div class="output-section-title">• Ý chính</div><ul class="bullet-list">${data.bullets.map(b=>`<li>${escHtml(b)}</li>`).join('')}</ul></div>`;
  }
  if (wantQuiz && data.quiz && data.quiz.length) {
    quizData = data.quiz;
    html += `<div class="output-section"><div class="output-section-title">🎯 Quiz tự động</div><div id="quizArea">${renderQuiz(data.quiz)}</div></div>`;
  }
  if (wantMindmap && data.mindmap) {
    html += `<div class="output-section"><div class="output-section-title">🧠 Sơ đồ tư duy (văn bản)</div><div class="mindmap-preview">${escHtml(data.mindmap)}</div><a href="mindmap.php" class="btn btn-ghost btn-sm" style="margin-top:8px;">Mở Mindmap Editor →</a></div>`;
  }
  if (!html) html = '<div class="card"><div style="padding:2rem;text-align:center;color:var(--muted);">Không có kết quả</div></div>';
  document.getElementById('outputArea').innerHTML = html;
}

function renderQuiz(quiz) {
  return quiz.map((q, qi) => `
    <div class="quiz-card">
      <div class="quiz-q">Câu ${qi+1}: ${escHtml(q.question)}</div>
      <div class="quiz-opts">
        ${q.options.map((o,oi)=>`<button class="quiz-opt" onclick="answerQuiz(this,${qi},${oi},${q.correct})">${String.fromCharCode(65+oi)}. ${escHtml(o)}</button>`).join('')}
      </div>
    </div>`).join('');
}
function answerQuiz(btn, qi, oi, correct) {
  const card = btn.closest('.quiz-card');
  card.querySelectorAll('.quiz-opt').forEach(b=>b.disabled=true);
  btn.classList.add(oi===correct?'correct':'wrong');
  if (oi!==correct) card.querySelectorAll('.quiz-opt')[correct].classList.add('correct');
}
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>
