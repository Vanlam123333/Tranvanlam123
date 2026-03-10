<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$user = getCurrentUser();

// Gather personalized data
$today = date('Y-m-d');
$weakTopics = [];
$quizRows = $db->query("SELECT topic, AVG(score*1.0/total) as avg_score, COUNT(*) as attempts FROM quiz_results WHERE user_id=$uid AND total>0 GROUP BY topic ORDER BY avg_score ASC LIMIT 5");
while ($r = $quizRows->fetchArray(SQLITE3_ASSOC)) {
    if ((float)$r['avg_score'] < 0.7) $weakTopics[] = ['topic'=>$r['topic'],'pct'=>round((float)$r['avg_score']*100),'attempts'=>(int)$r['attempts']];
}
$hardFlash = [];
$flashRows = $db->query("SELECT word, meaning, COUNT(*) as times FROM flashcard_history WHERE user_id=$uid AND rating='hard' GROUP BY word ORDER BY times DESC LIMIT 5");
while ($r = $flashRows->fetchArray(SQLITE3_ASSOC)) $hardFlash[] = $r;

$pomoDays = (int)$db->query("SELECT COUNT(DISTINCT DATE(created_at)) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus' AND created_at >= DATE('now','-7 days')")->fetchArray()['c'];
$totalPomo = (int)$db->query("SELECT COUNT(*) as c FROM pomodoro_sessions WHERE user_id=$uid AND type='focus'")->fetchArray()['c'];
$streak = (int)$db->query("SELECT streak FROM users WHERE id=$uid")->fetchArray()['streak'] ?? 0;
$level  = (int)$db->query("SELECT level FROM users WHERE id=$uid")->fetchArray()['level'] ?? 1;
$dueToday = (int)$db->query("SELECT COUNT(*) as c FROM srs_cards WHERE user_id=$uid AND next_review <= '$today'")->fetchArray()['c'] ?? 0;
$notesToday = (int)$db->query("SELECT COUNT(*) as c FROM notes WHERE user_id=$uid AND DATE(created_at)='$today'")->fetchArray()['c'];

// Build tutor context
$context = "Học sinh: {$user['name']}, cấp độ $level, streak $streak ngày.";
if (!empty($weakTopics)) {
    $context .= " Chủ đề yếu: " . implode(', ', array_map(fn($t)=>"{$t['topic']} ({$t['pct']}%)", $weakTopics)) . ".";
}
if (!empty($hardFlash)) {
    $context .= " Từ vựng khó: " . implode(', ', array_column($hardFlash,'word')) . ".";
}
$context .= " Pomodoro 7 ngày: $pomoDays buổi. Thẻ SRS cần ôn: $dueToday.";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>AI Gia sư — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.tutor-layout { display:grid; grid-template-columns:300px 1fr; gap:20px; }
@media(max-width:768px){.tutor-layout{grid-template-columns:1fr;}}
.insight-card { background:var(--surface); border:1.5px solid var(--border); border-radius:14px; padding:14px; margin-bottom:12px; }
.insight-title { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); margin-bottom:10px; }
.weak-topic { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid var(--border); font-size:13px; }
.weak-topic:last-child { border-bottom:none; }
.weak-pct { margin-left:auto; font-size:12px; font-weight:800; }
.pct-bar { height:4px; background:var(--surface2); border-radius:99px; overflow:hidden; width:60px; }
.pct-fill { height:100%; border-radius:99px; }

.chat-area { display:flex; flex-direction:column; height:calc(100vh - 200px); min-height:500px; }
.chat-messages { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:12px; }
.msg-bubble { max-width:80%; }
.msg-bubble.user { align-self:flex-end; }
.msg-bubble.ai { align-self:flex-start; }
.msg-inner { padding:12px 16px; border-radius:16px; font-size:14px; line-height:1.7; }
.msg-bubble.user .msg-inner { background:var(--accent); color:#fff; border-radius:16px 16px 4px 16px; }
.msg-bubble.ai .msg-inner { background:var(--surface2); color:var(--text); border-radius:16px 16px 16px 4px; border:1px solid var(--border); }
.msg-name { font-size:11px; font-weight:700; color:var(--muted); margin-bottom:4px; }
.quick-prompts { display:flex; flex-wrap:wrap; gap:6px; padding:8px 16px; }
.quick-prompt { padding:6px 12px; border-radius:20px; border:1.5px solid var(--border); background:var(--surface2); font-size:12px; font-weight:600; cursor:pointer; transition:all 0.15s; color:var(--text2); }
.quick-prompt:hover { border-color:var(--accent); color:var(--accent); }
.chat-input-area { padding:12px 16px; border-top:1px solid var(--border); display:flex; gap:8px; }
.typing-dots { display:flex; gap:4px; align-items:center; padding:8px 12px; }
.typing-dots span { width:6px; height:6px; border-radius:50%; background:var(--muted); animation:td 1.2s infinite; }
.typing-dots span:nth-child(2){animation-delay:.2s}.typing-dots span:nth-child(3){animation-delay:.4s}
@keyframes td{0%,80%,100%{transform:scale(0.6);opacity:0.4}40%{transform:scale(1);opacity:1}}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">AI cá nhân hóa</div>
    <h1 class="page-title">🤖 Gia sư AI</h1>
  </div>
  <div class="tutor-layout">
    <!-- Sidebar: Insights -->
    <div>
      <?php if (!empty($weakTopics)): ?>
      <div class="insight-card">
        <div class="insight-title">⚠️ Chủ đề cần ôn</div>
        <?php foreach($weakTopics as $t): ?>
        <div class="weak-topic">
          <span><?= htmlspecialchars($t['topic']) ?></span>
          <div class="pct-bar"><div class="pct-fill" style="width:<?= $t['pct'] ?>%;background:<?= $t['pct']<50?'var(--red)':'var(--gold)' ?>"></div></div>
          <span class="weak-pct" style="color:<?= $t['pct']<50?'var(--red)':'var(--gold)' ?>"><?= $t['pct'] ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($hardFlash)): ?>
      <div class="insight-card">
        <div class="insight-title">🃏 Từ hay quên</div>
        <?php foreach($hardFlash as $f): ?>
        <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:12px;">
          <span style="font-weight:700;"><?= htmlspecialchars($f['word']) ?></span>
          <span style="color:var(--muted);"><?= htmlspecialchars($f['meaning']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="insight-card">
        <div class="insight-title">📊 Tổng quan tuần</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div style="background:var(--surface2);border-radius:10px;padding:10px;text-align:center;">
            <div style="font-size:1.3rem;font-weight:900;color:var(--accent);"><?= $pomoDays ?>/7</div>
            <div style="font-size:10px;color:var(--muted);">Ngày học</div>
          </div>
          <div style="background:var(--surface2);border-radius:10px;padding:10px;text-align:center;">
            <div style="font-size:1.3rem;font-weight:900;color:<?= $dueToday>0?'var(--red)':'var(--green)' ?>;"><?= $dueToday ?></div>
            <div style="font-size:10px;color:var(--muted);">Cần ôn SRS</div>
          </div>
        </div>
        <?php if($dueToday>0): ?>
        <a href="spaced_repetition.php" class="btn btn-primary btn-sm" style="width:100%;margin-top:10px;">Ôn ngay <?= $dueToday ?> thẻ →</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Chat -->
    <div class="card" style="padding:0;overflow:hidden;">
      <div class="chat-area">
        <div class="chat-messages" id="chatMsgs">
          <div class="msg-bubble ai">
            <div class="msg-name">✨ Gia sư Spark</div>
            <div class="msg-inner">
              Xin chào <?= htmlspecialchars(explode(' ',$user['name'])[count(explode(' ',$user['name']))-1]) ?>! 👋 Mình là gia sư AI cá nhân của bạn.<br><br>
              <?php if(!empty($weakTopics)): ?>
              Mình thấy bạn đang gặp khó khăn với <strong><?= htmlspecialchars($weakTopics[0]['topic']) ?></strong> (chỉ đạt <?= $weakTopics[0]['pct'] ?>%). Muốn mình giải thích lại không?
              <?php elseif($dueToday>0): ?>
              Bạn có <strong><?= $dueToday ?> thẻ flashcard</strong> cần ôn hôm nay theo lịch spaced repetition. Hãy <a href="spaced_repetition.php">ôn ngay</a> nhé!
              <?php else: ?>
              Bạn đang học rất tốt! 🎉 Hỏi mình bất cứ điều gì bạn muốn học nhé.
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="quick-prompts" id="quickPrompts">
          <?php if(!empty($weakTopics)): ?>
          <button class="quick-prompt" onclick="sendQuick('Giải thích lại về <?= htmlspecialchars($weakTopics[0]['topic']) ?> cho mình')">📚 Ôn <?= htmlspecialchars($weakTopics[0]['topic']) ?></button>
          <?php endif; ?>
          <?php if(!empty($hardFlash)): ?>
          <button class="quick-prompt" onclick="sendQuick('Hãy giúp mình nhớ từ &quot;<?= htmlspecialchars($hardFlash[0]['word']) ?>&quot; bằng cách thú vị')">🃏 Nhớ từ "<?= htmlspecialchars($hardFlash[0]['word']) ?>"</button>
          <?php endif; ?>
          <button class="quick-prompt" onclick="sendQuick('Hôm nay mình nên học gì để hiệu quả nhất?')">🎯 Hôm nay học gì?</button>
          <button class="quick-prompt" onclick="sendQuick('Tạo cho mình 5 câu hỏi ôn tập để kiểm tra kiến thức')">✍️ Tạo câu hỏi ôn</button>
          <button class="quick-prompt" onclick="sendQuick('Chia sẻ một mẹo học tập hiệu quả cho mình')">💡 Mẹo học tập</button>
        </div>
        <div class="chat-input-area">
          <input type="text" id="tutorInput" class="form-input" placeholder="Hỏi gia sư bất cứ điều gì..." style="flex:1" onkeydown="if(event.key==='Enter')sendMsg()">
          <button class="btn btn-primary" onclick="sendMsg()">Gửi</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const context = <?= json_encode($context) ?>;
const history = [
  {role:'system', content:`Bạn là gia sư AI thông minh của MindSpark, tên là Spark. Bạn biết thông tin cá nhân về học sinh này: ${context}. Hãy dựa vào dữ liệu này để đưa ra lời khuyên cá nhân hóa, giải thích những chủ đề yếu, và động viên học sinh. Trả lời bằng tiếng Việt, thân thiện, ngắn gọn, dùng emoji phù hợp.`}
];

async function sendMsg() {
  const input = document.getElementById('tutorInput');
  const msg = input.value.trim();
  if (!msg) return;
  input.value = '';
  addMsg('user', 'Bạn', msg);
  await callTutor(msg);
}

function sendQuick(msg) {
  document.getElementById('tutorInput').value = msg;
  sendMsg();
  document.getElementById('quickPrompts').style.display = 'none';
}

function addMsg(role, name, text) {
  const msgs = document.getElementById('chatMsgs');
  const div = document.createElement('div');
  div.className = 'msg-bubble ' + role;
  div.innerHTML = `${role==='ai'?'<div class="msg-name">✨ Gia sư Spark</div>':''}<div class="msg-inner">${esc(text).replace(/\n/g,'<br>')}</div>`;
  msgs.appendChild(div);
  msgs.scrollTop = msgs.scrollHeight;
  return div;
}

async function callTutor(userMsg) {
  history.push({role:'user', content:userMsg});
  // Show typing
  const msgs = document.getElementById('chatMsgs');
  const typing = document.createElement('div');
  typing.className = 'msg-bubble ai';
  typing.innerHTML = '<div class="typing-dots"><span></span><span></span><span></span></div>';
  msgs.appendChild(typing);
  msgs.scrollTop = msgs.scrollHeight;

  try {
    const res = await fetch('ai_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'tutor_chat',messages:history.slice(-10)})});
    const data = await res.json();
    typing.remove();
    const reply = data.reply || data.result || 'Xin lỗi, mình không hiểu ý bạn. Thử lại nhé!';
    history.push({role:'assistant', content:reply});
    addMsg('ai','Spark',reply);
  } catch(e) {
    typing.remove();
    addMsg('ai','Spark','Lỗi kết nối, thử lại nhé!');
  }
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>
