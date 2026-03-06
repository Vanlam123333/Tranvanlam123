<?php
require_once __DIR__ . "/db.php"; requireLogin();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flashcard — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
/* ── PAGE LAYOUT ── */
.fc-layout { display: grid; grid-template-columns: 260px 1fr; gap: 20px; align-items: start; }
@media(max-width:768px){ .fc-layout { grid-template-columns: 1fr; } }

/* ── SIDEBAR ── */
.fc-sidebar { position: sticky; top: 74px; }
.fc-section { margin-bottom: 8px; }
.fc-section-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.8px; color: var(--muted); margin-bottom: 8px; padding: 0 4px;
}
.chip-group { display: flex; flex-wrap: wrap; gap: 6px; }
.chip {
  padding: 5px 12px; border-radius: 20px;
  border: 1.5px solid var(--border); background: var(--surface2);
  font-size: 12px; font-weight: 600; cursor: pointer;
  transition: all 0.15s; color: var(--text2); white-space: nowrap;
}
.chip:hover { border-color: var(--accent); color: var(--accent); }
.chip.active { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); }

.mode-switch {
  display: flex; gap: 4px;
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 10px; padding: 4px;
}
.mode-btn {
  flex: 1; padding: 7px 8px; border: none; border-radius: 7px;
  background: transparent; color: var(--muted);
  font-family: var(--font); font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all 0.15s; text-align: center;
}
.mode-btn.active { background: var(--surface); color: var(--text); box-shadow: var(--shadow); }

.word-input {
  width: 100%; min-height: 100px;
  background: var(--surface2); border: 1.5px solid var(--border);
  border-radius: 10px; padding: 10px 12px;
  color: var(--text); font-size: 13px;
  resize: vertical; font-family: var(--font); line-height: 1.6;
  outline: none; transition: border-color 0.15s;
}
.word-input:focus { border-color: var(--accent); }

/* ── STATS BAR ── */
.stats-bar {
  display: flex; gap: 12px; margin-bottom: 16px;
}
.stat-pill {
  flex: 1; background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; padding: 12px 14px; text-align: center;
}
.stat-pill-num { font-size: 1.4rem; font-weight: 800; color: var(--text); line-height: 1; }
.stat-pill-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-top: 3px; }
.stat-pill.green .stat-pill-num { color: var(--green); }
.stat-pill.red .stat-pill-num { color: var(--red); }
.stat-pill.accent .stat-pill-num { color: var(--accent); }

/* ── PROGRESS ── */
.fc-progress-bar {
  background: var(--surface2); border-radius: 99px; height: 4px;
  margin-bottom: 16px; overflow: hidden;
}
.fc-progress-fill {
  height: 100%; border-radius: 99px;
  background: linear-gradient(90deg, var(--accent), #a78bfa);
  transition: width 0.4s ease;
}

/* ── CARD ── */
.fc-scene { cursor: pointer; margin-bottom: 16px; user-select: none; }
.fc-card {
  position: relative; width: 100%; min-height: 260px;
  border-radius: 18px; border: 1.5px solid var(--border);
  background: var(--surface);
  transition: border-color 0.2s;
}
.fc-face {
  width: 100%; min-height: 260px;
  border-radius: 18px;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 2rem; text-align: center;
  box-sizing: border-box;
}
.fc-back {
  border-color: var(--accent);
}
.fc-tap-hint {
  position: absolute; bottom: 14px;
  font-size: 11px; font-weight: 600; color: var(--muted);
  display: flex; align-items: center; gap: 4px;
}
.fc-word { font-size: 2.2rem; font-weight: 800; letter-spacing: -1px; color: var(--text); margin-bottom: 6px; }
.fc-phonetic { font-size: 14px; color: var(--muted); font-style: italic; margin-bottom: 8px; }
.fc-badge {
  display: inline-flex; padding: 3px 10px; border-radius: 20px;
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
  background: var(--accent-soft); color: var(--accent);
}
.fc-meaning { font-size: 1.4rem; font-weight: 700; color: var(--text); margin-bottom: 10px; }
.fc-example { font-size: 13px; color: var(--muted); line-height: 1.7; max-width: 360px; }
.fc-example em { color: var(--text2); font-style: normal; }
.fc-example-vi { color: var(--accent); font-size: 12px; margin-top: 4px; }

/* ── AUDIO BTN ── */
.audio-btn {
  position: absolute; top: 14px; right: 14px;
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--surface2); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 14px; transition: all 0.15s;
  z-index: 2;
}
.audio-btn:hover { background: var(--accent-soft); border-color: var(--accent); }

/* ── SRS BUTTONS ── */
.srs-btns { display: flex; gap: 8px; margin-bottom: 16px; }
.srs-btn {
  flex: 1; padding: 10px 6px; border: none; border-radius: 10px;
  font-family: var(--font); font-size: 12px; font-weight: 700;
  cursor: pointer; transition: all 0.15s; display: flex;
  flex-direction: column; align-items: center; gap: 2px;
}
.srs-btn:active { transform: scale(0.96); }
.srs-btn.hard { background: var(--red-soft); color: var(--red); }
.srs-btn.ok   { background: var(--gold-soft); color: var(--gold); }
.srs-btn.good { background: var(--green-soft); color: var(--green); }
.srs-btn.easy { background: var(--accent-soft); color: var(--accent); }
.srs-sub { font-size: 10px; font-weight: 600; opacity: 0.7; }

/* ── NAVIGATION ── */
.fc-nav {
  display: flex; align-items: center; gap: 8px; margin-bottom: 16px;
}
.fc-nav-counter {
  flex: 1; text-align: center; font-size: 13px;
  font-weight: 700; color: var(--muted);
}

/* ── CARD LIST ── */
.fc-list { display: flex; flex-direction: column; gap: 4px; max-height: 280px; overflow-y: auto; }
.fc-list-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 8px; cursor: pointer;
  transition: background 0.15s; font-size: 13px;
}
.fc-list-item:hover { background: var(--surface2); }
.fc-list-item.active { background: var(--accent-soft); color: var(--accent); }
.fc-list-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
  background: var(--border2);
}
.fc-list-dot.known { background: var(--green); }
.fc-list-dot.hard { background: var(--red); }
.fc-list-word { font-weight: 700; flex: 1; }
.fc-list-meaning { font-size: 11px; color: var(--muted); }

/* ── TEST MODE ── */
.test-input-wrap { position: relative; margin-bottom: 10px; }
.test-input {
  width: 100%; padding: 14px 16px; border-radius: 12px;
  border: 2px solid var(--border); background: var(--surface2);
  color: var(--text); font-family: var(--font);
  font-size: 1.1rem; font-weight: 600; outline: none;
  transition: border-color 0.15s; text-align: center;
}
.test-input:focus { border-color: var(--accent); background: var(--surface); }
.test-input.correct { border-color: var(--green); background: var(--green-soft); color: var(--green); }
.test-input.wrong { border-color: var(--red); background: var(--red-soft); color: var(--red); }
.test-hint { font-size: 12px; color: var(--muted); text-align: center; margin-bottom: 10px; }
.test-feedback {
  padding: 10px 14px; border-radius: 10px; font-size: 13px;
  font-weight: 600; text-align: center; margin-bottom: 10px;
}
.test-feedback.correct { background: var(--green-soft); color: var(--green); }
.test-feedback.wrong { background: var(--red-soft); color: var(--red); }

/* ── EMPTY STATE ── */
.fc-empty {
  text-align: center; padding: 4rem 2rem; color: var(--muted);
}
.fc-empty-icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.4; }
.fc-empty-text { font-size: 14px; font-weight: 500; }

/* ── DONE SCREEN ── */
.fc-done {
  text-align: center; padding: 3rem 2rem;
}
.fc-done-emoji { font-size: 3.5rem; margin-bottom: 12px; }
.fc-done-title { font-size: 1.4rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
.fc-done-sub { font-size: 14px; color: var(--muted); }
</style>
</head>
<body>
<?php require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Học từ vựng</div>
    <h1 class="page-title">Flashcard Tiếng Anh</h1>
  </div>

  <div class="fc-layout">

    <!-- ── SIDEBAR ── -->
    <div class="fc-sidebar">
      <div class="card">
        <div class="card-body" style="padding:14px;">

          <!-- Input mode -->
          <div class="fc-section" style="margin-bottom:12px;">
            <div class="mode-switch">
              <button class="mode-btn active" id="modeAI" onclick="switchInputMode('ai')">✨ AI tạo</button>
              <button class="mode-btn" id="modeList" onclick="switchInputMode('list')">📋 Nhập từ</button>
            </div>
          </div>

          <!-- AI mode -->
          <div id="aiMode">
            <div class="fc-section">
              <div class="fc-section-title">Chủ đề</div>
              <div class="chip-group" id="topicChips">
                <span class="chip active" onclick="selectChip('topic',this,'Giao tiếp hàng ngày')">💬 Giao tiếp</span>
                <span class="chip" onclick="selectChip('topic',this,'IELTS Academic')">📚 IELTS</span>
                <span class="chip" onclick="selectChip('topic',this,'TOEIC Business')">💼 TOEIC</span>
                <span class="chip" onclick="selectChip('topic',this,'Du lịch')">✈️ Du lịch</span>
                <span class="chip" onclick="selectChip('topic',this,'Công nghệ')">💻 Tech</span>
                <span class="chip" onclick="selectChip('topic',this,'Idioms thông dụng')">🎯 Idioms</span>
                <span class="chip" onclick="selectChip('topic',this,'Phrasal verbs')">⚡ Phrasal</span>
                <span class="chip" onclick="selectChip('topic',this,'Sức khỏe')">🏥 Y tế</span>
              </div>
            </div>

            <div class="fc-section" style="margin-top:12px;">
              <div class="fc-section-title">Trình độ</div>
              <div class="chip-group" id="levelChips">
                <span class="chip" onclick="selectChip('level',this,'A1-A2')">A1–A2</span>
                <span class="chip active" onclick="selectChip('level',this,'B1-B2')">B1–B2</span>
                <span class="chip" onclick="selectChip('level',this,'C1-C2')">C1–C2</span>
              </div>
            </div>

            <div class="fc-section" style="margin-top:12px;">
              <div class="fc-section-title">Loại từ</div>
              <div class="chip-group" id="typeChips">
                <span class="chip active" onclick="selectChip('type',this,'tất cả')">Tất cả</span>
                <span class="chip" onclick="selectChip('type',this,'danh từ')">Danh từ</span>
                <span class="chip" onclick="selectChip('type',this,'động từ')">Động từ</span>
                <span class="chip" onclick="selectChip('type',this,'tính từ')">Tính từ</span>
              </div>
            </div>
          </div>

          <!-- List mode -->
          <div id="listMode" style="display:none;">
            <div class="fc-section-title">Nhập từ vựng (mỗi từ 1 dòng)</div>
            <textarea class="word-input" id="wordList" placeholder="ephemeral&#10;ameliorate&#10;ubiquitous: có mặt khắp nơi&#10;perseverance"></textarea>
          </div>

          <button class="btn btn-primary" id="genBtn" onclick="generate()" style="width:100%;margin-top:12px;">
            ✨ Tạo flashcard
          </button>

          <!-- Card list -->
          <div id="cardListWrap" style="display:none;margin-top:16px;">
            <div class="fc-section-title" style="margin-bottom:6px;">Danh sách từ</div>
            <div class="fc-list" id="cardList"></div>
          </div>

        </div>
      </div>
    </div>

    <!-- ── MAIN AREA ── -->
    <div>

      <!-- Empty state -->
      <div id="emptyState" class="card">
        <div class="fc-empty">
          <div class="fc-empty-icon">🃏</div>
          <div class="fc-empty-text">Chọn chủ đề và bấm <strong>Tạo flashcard</strong> để bắt đầu</div>
        </div>
      </div>

      <!-- Study area -->
      <div id="studyArea" style="display:none;">

        <!-- Stats -->
        <div class="stats-bar">
          <div class="stat-pill accent">
            <div class="stat-pill-num" id="statTotal">0</div>
            <div class="stat-pill-label">Tổng</div>
          </div>
          <div class="stat-pill green">
            <div class="stat-pill-num" id="statKnown">0</div>
            <div class="stat-pill-label">Thuộc</div>
          </div>
          <div class="stat-pill red">
            <div class="stat-pill-num" id="statHard">0</div>
            <div class="stat-pill-label">Khó</div>
          </div>
          <div class="stat-pill">
            <div class="stat-pill-num" id="statPct">0%</div>
            <div class="stat-pill-label">Tiến độ</div>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="fc-progress-bar">
          <div class="fc-progress-fill" id="progressFill" style="width:0%"></div>
        </div>

        <!-- Study/Test mode toggle -->
        <div style="display:flex;gap:8px;margin-bottom:14px;">
          <button class="mode-btn active" id="studyModeBtn" onclick="setViewMode('study')"
            style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:10px;background:var(--surface2);color:var(--text2);font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;">
            🃏 Học thẻ
          </button>
          <button class="mode-btn" id="testModeBtn" onclick="setViewMode('test')"
            style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:10px;background:var(--surface2);color:var(--muted);font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;">
            ✏️ Kiểm tra
          </button>
          <button onclick="shuffleCards()"
            style="padding:8px 12px;border:1.5px solid var(--border);border-radius:10px;background:var(--surface2);color:var(--muted);font-family:var(--font);font-size:13px;cursor:pointer;"
            title="Xáo trộn">🔀</button>
        </div>

        <!-- STUDY MODE -->
        <div id="studyMode">
          <!-- Flashcard -->
          <div class="fc-scene" onclick="flipCard()">
            <div class="fc-card" id="fcCard">
              <!-- Front -->
              <div class="fc-face" id="fcFront">
                <button class="audio-btn" onclick="event.stopPropagation();speak()" title="Nghe phát âm">🔊</button>
                <div class="fc-word" id="fcWord"></div>
                <div class="fc-phonetic" id="fcPhonetic"></div>
                <div class="fc-badge" id="fcType"></div>
                <div class="fc-tap-hint">👆 Nhấn để xem nghĩa</div>
              </div>
              <!-- Back -->
              <div class="fc-face fc-back" id="fcBack" style="display:none;">
                <button class="audio-btn" onclick="event.stopPropagation();speak()" title="Nghe phát âm">🔊</button>
                <div class="fc-meaning" id="fcMeaning"></div>
                <div class="fc-example">
                  <em id="fcExample"></em>
                  <div class="fc-example-vi" id="fcExampleVi"></div>
                </div>
                <div class="fc-tap-hint">👆 Nhấn để lật lại</div>
              </div>
            </div>
          </div>

          <!-- SRS buttons (show after flip) -->
          <div class="srs-btns" id="srsBtns" style="display:none;">
            <button class="srs-btn hard" onclick="rateCard('hard')">
              😓 Khó
              <span class="srs-sub">Ôn lại sớm</span>
            </button>
            <button class="srs-btn ok" onclick="rateCard('ok')">
              🤔 Ổn
              <span class="srs-sub">2 ngày</span>
            </button>
            <button class="srs-btn good" onclick="rateCard('good')">
              😊 Tốt
              <span class="srs-sub">4 ngày</span>
            </button>
            <button class="srs-btn easy" onclick="rateCard('easy')">
              🚀 Dễ
              <span class="srs-sub">7 ngày</span>
            </button>
          </div>

          <!-- Navigation -->
          <div class="fc-nav">
            <button class="btn btn-ghost btn-sm" onclick="prevCard()">← Trước</button>
            <div class="fc-nav-counter" id="fcCount"></div>
            <button class="btn btn-ghost btn-sm" onclick="nextCard()">Tiếp →</button>
          </div>
        </div>

        <!-- TEST MODE -->
        <div id="testMode" style="display:none;">
          <div class="card" style="margin-bottom:12px;">
            <div class="card-body" style="text-align:center;">
              <div style="font-size:13px;color:var(--muted);margin-bottom:8px;">Dịch nghĩa tiếng Việt sang tiếng Anh</div>
              <div style="font-size:1.5rem;font-weight:800;color:var(--text);margin-bottom:16px;" id="testQuestion"></div>
              <div class="test-input-wrap">
                <input type="text" class="test-input" id="testInput"
                  placeholder="Nhập từ tiếng Anh..."
                  onkeydown="if(event.key==='Enter')checkAnswer()">
              </div>
              <div class="test-hint" id="testHint">Gợi ý: <span id="hintLetters"></span></div>
              <div class="test-feedback" id="testFeedback" style="display:none;"></div>
              <div style="display:flex;gap:8px;justify-content:center;margin-top:8px;">
                <button class="btn btn-primary" onclick="checkAnswer()">Kiểm tra</button>
                <button class="btn btn-ghost" onclick="skipTest()">Bỏ qua →</button>
              </div>
            </div>
          </div>
          <div style="text-align:center;font-size:13px;color:var(--muted);" id="testCount"></div>
        </div>

        <!-- Done screen -->
        <div id="doneScreen" style="display:none;" class="card">
          <div class="fc-done">
            <div class="fc-done-emoji">🎉</div>
            <div class="fc-done-title">Hoàn thành!</div>
            <div class="fc-done-sub" id="doneMsg"></div>
            <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;">
              <button class="btn btn-primary" onclick="restartSession()">🔁 Học lại</button>
              <button class="btn btn-ghost" onclick="reviewHard()">😓 Ôn từ khó</button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
let cards = [], idx = 0, flipped = false, viewMode = 'study';
let ratings = {}; // idx -> 'hard'|'ok'|'good'|'easy'
let selected = { topic: 'Giao tiếp hàng ngày', level: 'B1-B2', type: 'tất cả' };
let inputMode = 'ai';
let testAnswered = false;

// ── CHIP SELECTION ──
function selectChip(key, el, val) {
  selected[key] = val;
  el.closest('.chip-group').querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
}

function switchInputMode(mode) {
  inputMode = mode;
  document.getElementById('aiMode').style.display = mode === 'ai' ? 'block' : 'none';
  document.getElementById('listMode').style.display = mode === 'list' ? 'block' : 'none';
  document.getElementById('modeAI').classList.toggle('active', mode === 'ai');
  document.getElementById('modeList').classList.toggle('active', mode === 'list');
}

// ── GENERATE ──
async function generate() {
  const btn = document.getElementById('genBtn');
  btn.disabled = true; btn.textContent = '⏳ Đang tạo...';
  try {
    let body;
    if (inputMode === 'ai') {
      body = { type: 'flashcard_en', topic: selected.topic, level: selected.level, wordType: selected.type };
    } else {
      const raw = document.getElementById('wordList').value.trim();
      if (!raw) { alert('Nhập từ vựng đi!'); btn.disabled = false; btn.textContent = '✨ Tạo flashcard'; return; }
      body = { type: 'flashcard_list', words: raw };
    }
    const res = await fetch('ai_api.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const data = await res.json();
    loadCards(data.cards || []);
  } catch(e) { alert('Lỗi tạo card!'); }
  btn.disabled = false; btn.textContent = '✨ Tạo flashcard';
}

// ── LOAD CARDS ──
function loadCards(c) {
  if (!c.length) { alert('Không tạo được card, thử lại!'); return; }
  cards = c; idx = 0; ratings = {}; flipped = false; testAnswered = false;
  document.getElementById('emptyState').style.display = 'none';
  document.getElementById('studyArea').style.display = 'block';
  document.getElementById('doneScreen').style.display = 'none';
  document.getElementById('studyMode').style.display = 'block';
  document.getElementById('testMode').style.display = 'none';
  setViewMode('study');
  renderCard(); renderCardList(); updateStats();
}

// ── RENDER CARD ──
function renderCard() {
  const c = cards[idx];
  if (!c) return;
  document.getElementById('fcWord').textContent = c.word;
  document.getElementById('fcPhonetic').textContent = c.phonetic || '';
  document.getElementById('fcType').textContent = c.type || '';
  document.getElementById('fcMeaning').textContent = c.meaning || '';
  document.getElementById('fcExample').textContent = c.example ? `"${c.example}"` : '';
  document.getElementById('fcExampleVi').textContent = c.example_vi || '';
  document.getElementById('fcCount').textContent = `${idx + 1} / ${cards.length}`;
  // Reset card faces
  const front = document.getElementById('fcFront');
  const back = document.getElementById('fcBack');
  if (front) front.style.display = 'flex';
  if (back) back.style.display = 'none';
  if (document.getElementById('fcCard')) document.getElementById('fcCard').style.borderColor = 'var(--border)';
  document.getElementById('srsBtns').style.display = 'none';
  flipped = false;
  highlightListItem();

  // Test mode
  renderTestCard();
}

function flipCard() {
  if (viewMode !== 'study') return;
  flipped = !flipped;
  const front = document.getElementById('fcFront');
  const back = document.getElementById('fcBack');
  if (flipped) {
    front.style.display = 'none';
    back.style.display = 'flex';
    document.getElementById('fcCard').style.borderColor = 'var(--accent)';
  } else {
    front.style.display = 'flex';
    back.style.display = 'none';
    document.getElementById('fcCard').style.borderColor = 'var(--border)';
  }
  document.getElementById('srsBtns').style.display = flipped ? 'flex' : 'none';
}

// ── NAVIGATION ──
function prevCard() {
  if (idx > 0) { idx--; renderCard(); }
}
function nextCard() {
  if (idx < cards.length - 1) { idx++; renderCard(); }
  else showDone();
}

// ── SRS RATING ──
function rateCard(rating) {
  ratings[idx] = rating;
  updateStats();
  renderCardList();
  if (idx < cards.length - 1) { idx++; renderCard(); }
  else showDone();
}

// ── STATS ──
function updateStats() {
  const total = cards.length;
  const known = Object.values(ratings).filter(r => r === 'good' || r === 'easy').length;
  const hard = Object.values(ratings).filter(r => r === 'hard').length;
  const rated = Object.keys(ratings).length;
  const pct = total ? Math.round(rated / total * 100) : 0;
  document.getElementById('statTotal').textContent = total;
  document.getElementById('statKnown').textContent = known;
  document.getElementById('statHard').textContent = hard;
  document.getElementById('statPct').textContent = pct + '%';
  document.getElementById('progressFill').style.width = pct + '%';
}

// ── CARD LIST ──
function renderCardList() {
  const wrap = document.getElementById('cardListWrap');
  const list = document.getElementById('cardList');
  wrap.style.display = cards.length ? 'block' : 'none';
  list.innerHTML = cards.map((c, i) => {
    const r = ratings[i];
    const dotClass = r === 'good' || r === 'easy' ? 'known' : r === 'hard' ? 'hard' : '';
    return `<div class="fc-list-item ${i === idx ? 'active' : ''}" onclick="jumpTo(${i})" id="li${i}">
      <div class="fc-list-dot ${dotClass}"></div>
      <div class="fc-list-word">${c.word}</div>
      <div class="fc-list-meaning">${c.meaning || ''}</div>
    </div>`;
  }).join('');
}

function highlightListItem() {
  document.querySelectorAll('.fc-list-item').forEach((el, i) => {
    el.classList.toggle('active', i === idx);
  });
  const active = document.getElementById(`li${idx}`);
  if (active) active.scrollIntoView({ block: 'nearest' });
}

function jumpTo(i) { idx = i; renderCard(); }

// ── VIEW MODE (study/test) ──
function setViewMode(mode) {
  viewMode = mode;
  document.getElementById('studyMode').style.display = mode === 'study' ? 'block' : 'none';
  document.getElementById('testMode').style.display = mode === 'test' ? 'block' : 'none';
  document.getElementById('studyModeBtn').style.borderColor = mode === 'study' ? 'var(--accent)' : 'var(--border)';
  document.getElementById('studyModeBtn').style.color = mode === 'study' ? 'var(--accent)' : 'var(--text2)';
  document.getElementById('testModeBtn').style.borderColor = mode === 'test' ? 'var(--accent)' : 'var(--border)';
  document.getElementById('testModeBtn').style.color = mode === 'test' ? 'var(--accent)' : 'var(--muted)';
  if (mode === 'test') { idx = 0; renderTestCard(); }
}

// ── TEST MODE ──
function renderTestCard() {
  if (viewMode !== 'test') return;
  const c = cards[idx];
  if (!c) return;
  document.getElementById('testQuestion').textContent = c.meaning || '';
  document.getElementById('testInput').value = '';
  document.getElementById('testInput').className = 'test-input';
  document.getElementById('testFeedback').style.display = 'none';
  document.getElementById('testCount').textContent = `Câu ${idx + 1} / ${cards.length}`;
  // Hint: show first letter + underscores
  const hint = c.word[0] + '_'.repeat(c.word.length - 1);
  document.getElementById('hintLetters').textContent = hint;
  testAnswered = false;
  setTimeout(() => document.getElementById('testInput').focus(), 100);
}

function checkAnswer() {
  if (testAnswered) { skipTest(); return; }
  const input = document.getElementById('testInput');
  const answer = input.value.trim().toLowerCase();
  const correct = cards[idx].word.toLowerCase();
  const fb = document.getElementById('testFeedback');

  if (answer === correct) {
    input.className = 'test-input correct';
    fb.className = 'test-feedback correct';
    fb.textContent = '✅ Chính xác!';
    ratings[idx] = 'good';
  } else {
    input.className = 'test-input wrong';
    fb.className = 'test-feedback wrong';
    fb.textContent = `❌ Đáp án: ${cards[idx].word}`;
    ratings[idx] = 'hard';
  }
  fb.style.display = 'block';
  testAnswered = true;
  updateStats(); renderCardList();
}

function skipTest() {
  if (idx < cards.length - 1) { idx++; renderTestCard(); }
  else showDone();
}

// ── AUDIO (Web Speech API) ──
function speak() {
  if (!cards[idx]) return;
  const word = cards[idx].word;
  if ('speechSynthesis' in window) {
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(word);
    u.lang = 'en-US'; u.rate = 0.9;
    window.speechSynthesis.speak(u);
  }
}

// ── SHUFFLE ──
function shuffleCards() {
  for (let i = cards.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [cards[i], cards[j]] = [cards[j], cards[i]];
  }
  idx = 0; ratings = {};
  renderCard(); renderCardList(); updateStats();
}

// ── DONE ──
function showDone() {
  const known = Object.values(ratings).filter(r => r === 'good' || r === 'easy').length;
  const hard = Object.values(ratings).filter(r => r === 'hard').length;
  document.getElementById('doneScreen').style.display = 'block';
  document.getElementById('studyMode').style.display = 'none';
  document.getElementById('testMode').style.display = 'none';
  document.getElementById('doneMsg').textContent =
    `Thuộc ${known}/${cards.length} từ · Cần ôn lại ${hard} từ khó`;
}

function restartSession() {
  idx = 0; ratings = {}; flipped = false;
  document.getElementById('doneScreen').style.display = 'none';
  setViewMode('study');
  renderCard(); renderCardList(); updateStats();
}

function reviewHard() {
  const hardCards = cards.filter((_, i) => ratings[i] === 'hard');
  if (!hardCards.length) { alert('Không có từ khó!'); return; }
  loadCards(hardCards);
}
</script>
</body>
</html>
