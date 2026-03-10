<?php
require_once __DIR__ . "/db.php";
requireLogin();
require_once __DIR__ . "/gamification.php";
$uid = $_SESSION['user_id'];
$user = getCurrentUser();

$db->exec("CREATE TABLE IF NOT EXISTS srs_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    word TEXT NOT NULL,
    phonetic TEXT DEFAULT '',
    word_type TEXT DEFAULT '',
    meaning TEXT NOT NULL,
    example TEXT DEFAULT '',
    topic TEXT DEFAULT '',
    interval_days INTEGER DEFAULT 1,
    ease_factor REAL DEFAULT 2.5,
    repetitions INTEGER DEFAULT 0,
    next_review TEXT DEFAULT CURRENT_DATE,
    last_rating TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    if ($action === 'get_due') {
        $today = date('Y-m-d');
        $rows = $db->query("SELECT * FROM srs_cards WHERE user_id=$uid AND next_review <= '$today' ORDER BY next_review ASC, id ASC LIMIT 30");
        $cards = [];
        while ($r = $rows->fetchArray(SQLITE3_ASSOC)) $cards[] = $r;
        $total = (int)$db->query("SELECT COUNT(*) as c FROM srs_cards WHERE user_id=$uid")->fetchArray()['c'];
        $new   = (int)$db->query("SELECT COUNT(*) as c FROM srs_cards WHERE user_id=$uid AND repetitions=0")->fetchArray()['c'];
        $upcoming = (int)$db->query("SELECT COUNT(*) as c FROM srs_cards WHERE user_id=$uid AND next_review > '$today'")->fetchArray()['c'];
        echo json_encode(['ok'=>true,'cards'=>$cards,'total'=>$total,'due'=>count($cards),'new'=>$new,'upcoming'=>$upcoming]);
        exit;
    }

    if ($action === 'rate') {
        $cardId = (int)$input['card_id'];
        $rating = $input['rating']; // again|hard|good|easy
        $card = $db->query("SELECT * FROM srs_cards WHERE id=$cardId AND user_id=$uid")->fetchArray(SQLITE3_ASSOC);
        if (!$card) { echo json_encode(['ok'=>false]); exit; }

        $ef = (float)$card['ease_factor'];
        $reps = (int)$card['repetitions'];
        $interval = (int)$card['interval_days'];

        // SM-2 algorithm
        $qMap = ['again'=>0,'hard'=>3,'good'=>4,'easy'=>5];
        $q = $qMap[$rating] ?? 4;

        if ($q < 3) {
            $reps = 0; $interval = 1;
        } else {
            if ($reps === 0)      $interval = 1;
            elseif ($reps === 1)  $interval = 3;
            else                  $interval = (int)round($interval * $ef);
            $reps++;
            if ($rating === 'easy') $interval = (int)round($interval * 1.3);
        }
        $ef = max(1.3, $ef + (0.1 - (5-$q)*(0.08+(5-$q)*0.02)));
        $nextReview = date('Y-m-d', strtotime("+$interval days"));

        $db->exec("UPDATE srs_cards SET interval_days=$interval, ease_factor=$ef, repetitions=$reps, next_review='$nextReview', last_rating='".SQLite3::escapeString($rating)."' WHERE id=$cardId");
        echo json_encode(['ok'=>true,'next_review'=>$nextReview,'interval'=>$interval]);
        exit;
    }

    if ($action === 'import_from_history') {
        // Import flashcard_history to SRS
        $today = date('Y-m-d');
        $rows = $db->query("SELECT * FROM flashcard_history WHERE user_id=$uid ORDER BY created_at DESC LIMIT 200");
        $imported = 0;
        while ($r = $rows->fetchArray(SQLITE3_ASSOC)) {
            $w = SQLite3::escapeString($r['word']);
            $existing = $db->query("SELECT id FROM srs_cards WHERE user_id=$uid AND word='$w'")->fetchArray();
            if (!$existing) {
                $st = $db->prepare("INSERT INTO srs_cards (user_id,word,phonetic,word_type,meaning,topic,next_review) VALUES (?,?,?,?,?,?,?)");
                $st->bindValue(1,$uid); $st->bindValue(2,$r['word']); $st->bindValue(3,$r['phonetic']??'');
                $st->bindValue(4,$r['word_type']??''); $st->bindValue(5,$r['meaning']??'');
                $st->bindValue(6,$r['topic']??''); $st->bindValue(7,$today);
                $st->execute(); $imported++;
            }
        }
        echo json_encode(['ok'=>true,'imported'=>$imported]);
        exit;
    }

    echo json_encode(['ok'=>false]);
    exit;
}

$today = date('Y-m-d');
$dueCount = (int)$db->query("SELECT COUNT(*) as c FROM srs_cards WHERE user_id=$uid AND next_review <= '$today'")->fetchArray()['c'];
$totalCards = (int)$db->query("SELECT COUNT(*) as c FROM srs_cards WHERE user_id=$uid")->fetchArray()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Spaced Repetition — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.srs-hero { background:linear-gradient(135deg,#064e3b,#065f46); border-radius:var(--radius-xl); padding:1.5rem 1.75rem; margin-bottom:1.25rem; color:#fff; }
.srs-hero h2 { font-size:1.2rem; font-weight:900; margin:0 0 4px; }
.srs-hero p { font-size:12px; color:rgba(255,255,255,0.65); margin:0 0 1rem; }
.srs-stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.srs-stat { background:rgba(255,255,255,0.1); border-radius:10px; padding:10px; text-align:center; }
.srs-stat-num { font-size:1.3rem; font-weight:900; }
.srs-stat-label { font-size:10px; color:rgba(255,255,255,0.6); text-transform:uppercase; }

.srs-card { background:var(--surface); border:2px solid var(--border); border-radius:20px; padding:2.5rem 2rem; text-align:center; min-height:260px; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:pointer; transition:border-color 0.2s; margin-bottom:14px; }
.srs-card.revealed { border-color:var(--accent); }
.srs-word { font-size:2.5rem; font-weight:900; color:var(--text); margin-bottom:8px; }
.srs-phonetic { font-size:15px; color:var(--muted); font-style:italic; margin-bottom:6px; }
.srs-type-badge { display:inline-flex; padding:3px 12px; border-radius:20px; background:var(--accent-soft); color:var(--accent); font-size:11px; font-weight:700; margin-bottom:16px; }
.srs-meaning { font-size:1.3rem; font-weight:700; color:var(--text); margin-bottom:8px; }
.srs-example { font-size:13px; color:var(--muted); font-style:italic; max-width:400px; }
.srs-hint { font-size:12px; color:var(--muted); margin-top:12px; }

.srs-btns { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:14px; }
.srs-rate-btn { padding:12px 6px; border:none; border-radius:12px; font-family:var(--font); font-size:12px; font-weight:700; cursor:pointer; transition:all 0.15s; display:flex; flex-direction:column; align-items:center; gap:3px; }
.srs-rate-btn:active { transform:scale(0.96); }
.srs-rate-btn.again { background:var(--red-soft);    color:var(--red);    }
.srs-rate-btn.hard  { background:var(--gold-soft);   color:var(--gold);   }
.srs-rate-btn.good  { background:var(--green-soft);  color:var(--green);  }
.srs-rate-btn.easy  { background:var(--accent-soft); color:var(--accent); }
.srs-rate-sub { font-size:10px; opacity:0.7; }

.srs-done { text-align:center; padding:3rem 2rem; }
.forecast-bar { display:flex; gap:4px; align-items:flex-end; height:60px; margin:12px 0; }
.fc-col { flex:1; border-radius:4px 4px 0 0; background:var(--accent); min-height:4px; transition:height 0.4s; }
.fc-label { font-size:9px; color:var(--muted); text-align:center; margin-top:4px; }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Luyện tập thông minh</div>
    <h1 class="page-title">Spaced Repetition</h1>
  </div>

  <div class="srs-hero">
    <h2>🧠 Ôn tập đúng lúc, nhớ lâu hơn</h2>
    <p>Thuật toán SM-2 nhắc ôn từng từ đúng lúc sắp quên — hiệu quả hơn học vẹt 5 lần.</p>
    <div class="srs-stats-row">
      <div class="srs-stat"><div class="srs-stat-num" style="color:#34d399" id="statDue"><?= $dueCount ?></div><div class="srs-stat-label">Cần ôn</div></div>
      <div class="srs-stat"><div class="srs-stat-num" id="statTotal"><?= $totalCards ?></div><div class="srs-stat-label">Tổng thẻ</div></div>
      <div class="srs-stat"><div class="srs-stat-num" id="statNew">–</div><div class="srs-stat-label">Thẻ mới</div></div>
      <div class="srs-stat"><div class="srs-stat-num" id="statUpcoming">–</div><div class="srs-stat-label">Sắp tới</div></div>
    </div>
  </div>

  <?php if ($totalCards === 0): ?>
  <div class="card">
    <div style="text-align:center;padding:3rem 2rem;">
      <div style="font-size:3rem;margin-bottom:12px;">📥</div>
      <div style="font-size:15px;font-weight:800;margin-bottom:8px;">Chưa có thẻ nào</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:1.5rem;">Import từ lịch sử Flashcard để bắt đầu ôn tập thông minh</div>
      <button class="btn btn-primary" onclick="importCards()">📥 Import từ Flashcard</button>
    </div>
  </div>
  <?php elseif ($dueCount === 0): ?>
  <div class="card">
    <div class="srs-done">
      <div style="font-size:3.5rem;margin-bottom:12px;">🎉</div>
      <div style="font-size:1.3rem;font-weight:800;margin-bottom:8px;">Hôm nay ôn xong rồi!</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:1.5rem;">Còn <?= (int)$db->query("SELECT COUNT(*) as c FROM srs_cards WHERE user_id=$uid AND next_review > '$today'")->fetchArray()['c'] ?> thẻ đang lên lịch ôn.</div>
      <a href="flashcard.php" class="btn btn-ghost">➕ Học từ mới</a>
    </div>
  </div>
  <?php else: ?>
  <!-- Study area -->
  <div id="srsArea">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <div style="font-size:13px;font-weight:700;color:var(--muted);" id="srsCounter">Đang tải...</div>
      <div style="font-size:12px;color:var(--muted);">Bấm thẻ để xem nghĩa</div>
    </div>
    <div class="srs-card" id="srsCard" onclick="revealCard()">
      <div style="font-size:2rem;margin-bottom:12px;opacity:0.3;">🃏</div>
      <div style="font-size:14px;color:var(--muted);">Đang tải...</div>
    </div>
    <div class="srs-btns" id="srsBtns" style="display:none;">
      <button class="srs-rate-btn again" onclick="rate('again')">😓 Quên<span class="srs-rate-sub">Ôn lại ngay</span></button>
      <button class="srs-rate-btn hard"  onclick="rate('hard')">🤔 Khó<span class="srs-rate-sub">1–2 ngày</span></button>
      <button class="srs-rate-btn good"  onclick="rate('good')">😊 Nhớ<span class="srs-rate-sub">3–5 ngày</span></button>
      <button class="srs-rate-btn easy"  onclick="rate('easy')">🚀 Dễ<span class="srs-rate-sub">7+ ngày</span></button>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
let cards = [], idx = 0, revealed = false;

async function loadCards() {
  const res = await fetch('spaced_repetition.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_due'})});
  const data = await res.json();
  if (!data.ok) return;
  cards = data.cards;
  document.getElementById('statDue').textContent      = data.due;
  document.getElementById('statTotal').textContent    = data.total;
  document.getElementById('statNew').textContent      = data.new;
  document.getElementById('statUpcoming').textContent = data.upcoming;
  if (cards.length) renderCard();
}

function renderCard() {
  const c = cards[idx];
  if (!c) { showDone(); return; }
  revealed = false;
  const card = document.getElementById('srsCard');
  card.className = 'srs-card';
  card.innerHTML = `
    <div class="srs-word">${esc(c.word)}</div>
    <div class="srs-phonetic">${esc(c.phonetic||'')}</div>
    ${c.word_type?`<div class="srs-type-badge">${esc(c.word_type)}</div>`:''}
    <div class="srs-hint">👆 Bấm để xem nghĩa</div>`;
  document.getElementById('srsBtns').style.display = 'none';
  document.getElementById('srsCounter').textContent = `Thẻ ${idx+1} / ${cards.length}`;
}

function revealCard() {
  if (revealed) return;
  revealed = true;
  const c = cards[idx];
  const card = document.getElementById('srsCard');
  card.className = 'srs-card revealed';
  card.innerHTML = `
    <div class="srs-word">${esc(c.word)}</div>
    <div class="srs-phonetic">${esc(c.phonetic||'')}</div>
    <div style="width:40px;height:2px;background:var(--border2);border-radius:99px;margin:10px 0;"></div>
    <div class="srs-meaning">${esc(c.meaning)}</div>
    ${c.example?`<div class="srs-example">"${esc(c.example)}"</div>`:''}`;
  document.getElementById('srsBtns').style.display = 'grid';
  // Speak
  if ('speechSynthesis' in window) {
    const u = new SpeechSynthesisUtterance(c.word);
    u.lang = 'en-US'; u.rate = 0.9;
    window.speechSynthesis.speak(u);
  }
}

async function rate(r) {
  const c = cards[idx];
  await fetch('spaced_repetition.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'rate',card_id:c.id,rating:r})});
  idx++;
  if (idx < cards.length) renderCard();
  else showDone();
}

function showDone() {
  document.getElementById('srsArea').innerHTML = `
    <div class="card"><div class="srs-done">
      <div style="font-size:3.5rem;margin-bottom:12px;">🎉</div>
      <div style="font-size:1.3rem;font-weight:800;margin-bottom:6px;">Ôn xong ${cards.length} thẻ!</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:1.5rem;">Thuật toán SM-2 đã lên lịch ôn tập tiếp theo cho bạn.</div>
      <div style="display:flex;gap:10px;justify-content:center;">
        <button class="btn btn-primary" onclick="location.reload()">🔁 Ôn thêm</button>
        <a href="flashcard.php" class="btn btn-ghost">➕ Học từ mới</a>
      </div>
    </div></div>`;
}

async function importCards() {
  const btn = event.target;
  btn.disabled=true; btn.textContent='⏳ Đang import...';
  const res = await fetch('spaced_repetition.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'import_from_history'})});
  const data = await res.json();
  if (data.ok) { alert(`✅ Đã import ${data.imported} thẻ mới!`); location.reload(); }
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
loadCards();
</script>
</body>
</html>
