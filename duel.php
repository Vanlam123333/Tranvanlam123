<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$user = getCurrentUser();

$db->exec("CREATE TABLE IF NOT EXISTS duels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    host_id INTEGER NOT NULL,
    guest_id INTEGER,
    topic TEXT NOT NULL,
    questions TEXT,
    host_answers TEXT DEFAULT '[]',
    guest_answers TEXT DEFAULT '[]',
    host_score INTEGER DEFAULT 0,
    guest_score INTEGER DEFAULT 0,
    status TEXT DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

if($_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json');
    $input=json_decode(file_get_contents('php://input'),true)??[];
    $action=$input['action']??'';

    if($action==='create_duel'){
        $topic=SQLite3::escapeString($input['topic']??'Kiến thức chung');
        $count=max(5,min(15,(int)($input['count']??10)));
        $code=strtoupper(substr(md5(uniqid()),0,6));
        $db->exec("INSERT INTO duels (code,host_id,topic) VALUES ('$code',$uid,'$topic')");
        $id=$db->lastInsertRowID();
        echo json_encode(['ok'=>true,'duel_id'=>$id,'code'=>$code,'count'=>$count]); exit;
    }

    if($action==='set_questions'){
        $duelId=(int)($input['duel_id']??0);
        $duel=$db->query("SELECT * FROM duels WHERE id=$duelId AND host_id=$uid")->fetchArray(SQLITE3_ASSOC);
        if(!$duel){echo json_encode(['ok'=>false]);exit;}
        $questions=SQLite3::escapeString(json_encode($input['questions']??[]));
        $db->exec("UPDATE duels SET questions='$questions',status='ready' WHERE id=$duelId");
        echo json_encode(['ok'=>true]); exit;
    }

    if($action==='join_duel'){
        $code=strtoupper(SQLite3::escapeString($input['code']??''));
        $duel=$db->query("SELECT * FROM duels WHERE code='$code'")->fetchArray(SQLITE3_ASSOC);
        if(!$duel){echo json_encode(['ok'=>false,'msg'=>'Không tìm thấy duel!']);exit;}
        if($duel['host_id']==$uid){echo json_encode(['ok'=>true,'duel_id'=>$duel['id'],'as'=>'host','duel'=>$duel]);exit;}
        if($duel['guest_id']&&$duel['guest_id']!=$uid){echo json_encode(['ok'=>false,'msg'=>'Phòng đã đầy!']);exit;}
        $db->exec("UPDATE duels SET guest_id=$uid,status='playing' WHERE id={$duel['id']}");
        echo json_encode(['ok'=>true,'duel_id'=>$duel['id'],'as'=>'guest','duel'=>$duel]); exit;
    }

    if($action==='submit_answer'){
        $duelId=(int)($input['duel_id']??0);
        $answers=json_encode($input['answers']??[]);
        $duel=$db->query("SELECT * FROM duels WHERE id=$duelId")->fetchArray(SQLITE3_ASSOC);
        if(!$duel){echo json_encode(['ok'=>false]);exit;}
        $questions=json_decode($duel['questions'],true)??[];
        $myAnswers=$input['answers']??[];
        $myScore=0;
        foreach($questions as $i=>$q){ if(isset($myAnswers[$i])&&$myAnswers[$i]==$q['ans']) $myScore++; }
        $field=$duel['host_id']==$uid?'host':'guest';
        $escaped=SQLite3::escapeString($answers);
        $db->exec("UPDATE duels SET {$field}_answers='$escaped',{$field}_score=$myScore WHERE id=$duelId");
        // Check if both submitted
        $updated=$db->query("SELECT * FROM duels WHERE id=$duelId")->fetchArray(SQLITE3_ASSOC);
        $bothDone=count(json_decode($updated['host_answers'],true))>0&&count(json_decode($updated['guest_answers'],true))>0;
        if($bothDone) $db->exec("UPDATE duels SET status='done' WHERE id=$duelId");
        require_once __DIR__ . '/gamification.php';
        awardXP($uid,'quiz',20+($myScore*2),"Duel: $myScore điểm");
        echo json_encode(['ok'=>true,'my_score'=>$myScore,'status'=>$bothDone?'done':'waiting']); exit;
    }

    if($action==='get_duel'){
        $duelId=(int)($input['duel_id']??0);
        $duel=$db->query("SELECT d.*,u1.name as host_name,u2.name as guest_name FROM duels d LEFT JOIN users u1 ON d.host_id=u1.id LEFT JOIN users u2 ON d.guest_id=u2.id WHERE d.id=$duelId")->fetchArray(SQLITE3_ASSOC);
        if(!$duel){echo json_encode(['ok'=>false]);exit;}
        $duel['questions_parsed']=json_decode($duel['questions'],true);
        echo json_encode(['ok'=>true,'duel'=>$duel]); exit;
    }

    echo json_encode(['ok'=>false]); exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Duel Mode — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.duel-hero{background:linear-gradient(135deg,#450a0a,#7f1d1d,#991b1b);border-radius:var(--radius-xl);padding:1.75rem;color:#fff;text-align:center;margin-bottom:1.25rem;position:relative;overflow:hidden;}
.duel-vs{font-size:4rem;font-weight:900;letter-spacing:-4px;margin:0.5rem 0;}
.duel-players{display:flex;align-items:center;gap:1rem;justify-content:center;margin-bottom:0.5rem;}
.duel-player{flex:1;max-width:160px;}
.duel-player-name{font-size:14px;font-weight:800;}
.duel-player-score{font-size:2rem;font-weight:900;}
.duel-code{font-size:1.8rem;font-weight:900;letter-spacing:8px;color:#fca5a5;background:rgba(255,255,255,0.1);border-radius:12px;padding:10px 20px;display:inline-block;margin:8px 0;cursor:pointer;}
.q-card{background:var(--surface);border:1.5px solid var(--border);border-radius:16px;padding:1.5rem;margin-bottom:1rem;}
.q-opts{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;}
.q-opt{padding:12px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;transition:all 0.15s;text-align:center;}
.q-opt:hover{border-color:var(--accent);background:var(--accent-soft);}
.q-opt.selected{border-color:var(--accent);background:var(--accent-soft);color:var(--accent);}
.q-opt.correct{border-color:var(--green)!important;background:#dcfce7!important;color:#16a34a!important;}
.q-opt.wrong{border-color:var(--red)!important;background:#fce7e7!important;color:#dc2626!important;}
.duel-result{text-align:center;padding:2rem;}
.winner-banner{font-size:3rem;margin-bottom:12px;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <!-- LOBBY -->
  <div id="lobbyView">
    <div class="page-header">
      <div class="page-eyebrow">1v1 Battle</div>
      <h1 class="page-title">⚔️ Duel Mode</h1>
    </div>
    <div style="max-width:480px;margin:0 auto;">
      <div class="card" style="margin-bottom:1rem;">
        <div class="card-body">
          <div style="font-size:15px;font-weight:800;margin-bottom:14px;">⚔️ Tạo duel mới</div>
          <input type="text" id="duelTopic" class="form-input" placeholder="Chủ đề (VD: Tiếng Anh B1, Toán 10...)" style="width:100%;margin-bottom:8px;">
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
            <label style="font-size:12px;font-weight:700;color:var(--muted);white-space:nowrap;">Số câu:</label>
            <input type="number" id="duelCount" class="form-input" value="10" min="5" max="15" style="flex:1;">
          </div>
          <button class="btn btn-primary" onclick="createDuel()" style="width:100%;">⚔️ Tạo trận đấu</button>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <div style="font-size:15px;font-weight:800;margin-bottom:14px;">🔗 Tham gia duel</div>
          <input type="text" id="joinCode" class="form-input" placeholder="Nhập mã duel..." style="width:100%;margin-bottom:10px;text-transform:uppercase;letter-spacing:4px;text-align:center;font-weight:900;font-size:1.1rem;" maxlength="6">
          <button class="btn btn-ghost" onclick="joinDuel()" style="width:100%;">🚪 Tham gia</button>
        </div>
      </div>
    </div>
  </div>

  <!-- WAITING -->
  <div id="waitingView" style="display:none;">
    <div class="duel-hero">
      <div style="font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:2px;opacity:0.7;margin-bottom:8px;">Chia sẻ mã này</div>
      <div class="duel-code" id="duelCodeDisplay" onclick="copyDuelCode()">------</div>
      <div style="font-size:12px;opacity:0.6;margin-top:8px;">📋 Nhấn để copy · Chờ đối thủ tham gia...</div>
      <div style="margin-top:16px;" id="aiGenStatus">
        <div style="font-size:13px;opacity:0.8;">⏳ AI đang tạo câu hỏi...</div>
      </div>
    </div>
    <div style="text-align:center;padding:2rem;color:var(--muted);">
      <div style="font-size:2rem;margin-bottom:12px;">⏳</div>
      <div>Đang chờ đối thủ vào phòng...</div>
    </div>
  </div>

  <!-- PLAYING -->
  <div id="playingView" style="display:none;">
    <div class="duel-hero">
      <div class="duel-players">
        <div class="duel-player">
          <div class="duel-player-name" id="p1Name">Bạn</div>
          <div class="duel-player-score" id="p1Score">0</div>
        </div>
        <div style="font-size:2rem;font-weight:900;opacity:0.6;">VS</div>
        <div class="duel-player">
          <div class="duel-player-name" id="p2Name">Đối thủ</div>
          <div class="duel-player-score" id="p2Score">?</div>
        </div>
      </div>
      <div style="font-size:12px;opacity:0.6;" id="duelProgress">Câu 1/10</div>
    </div>
    <div id="questionArea"></div>
    <div style="display:flex;gap:8px;margin-top:10px;">
      <button class="btn btn-ghost btn-sm" id="prevQBtn" onclick="prevQ()" style="flex:1;">← Trước</button>
      <button class="btn btn-primary" id="nextQBtn" onclick="nextQ()" style="flex:1;">Tiếp →</button>
    </div>
    <button class="btn btn-primary" id="submitBtn" onclick="submitAnswers()" style="width:100%;margin-top:14px;display:none;">🏁 Nộp bài</button>
  </div>

  <!-- RESULT -->
  <div id="resultView" style="display:none;">
    <div class="card">
      <div class="card-body duel-result">
        <div class="winner-banner" id="resultEmoji">🏆</div>
        <div style="font-size:1.5rem;font-weight:900;margin-bottom:8px;" id="resultTitle"></div>
        <div class="duel-players" style="margin:1rem 0;">
          <div class="duel-player" style="background:var(--surface2);border-radius:12px;padding:12px;">
            <div class="duel-player-name" id="r1Name"></div>
            <div class="duel-player-score" id="r1Score" style="color:var(--accent)"></div>
          </div>
          <div style="font-size:1.3rem;font-weight:900;color:var(--muted);">VS</div>
          <div class="duel-player" style="background:var(--surface2);border-radius:12px;padding:12px;">
            <div class="duel-player-name" id="r2Name"></div>
            <div class="duel-player-score" id="r2Score" style="color:var(--muted)"></div>
          </div>
        </div>
        <button class="btn btn-primary" onclick="location.reload()">🔁 Chơi lại</button>
      </div>
    </div>
  </div>
</div>

<script>
const ME = <?= $uid ?>;
const ME_NAME = <?= json_encode($user['name']) ?>;
let duelId=null, duelCode=null, questions=[], myAnswers={}, qIdx=0, isHost=false, pollTimer=null;

async function createDuel(){
  const topic=document.getElementById('duelTopic').value.trim()||'Kiến thức chung';
  const count=parseInt(document.getElementById('duelCount').value)||10;
  const res=await fetch('duel.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create_duel',topic,count})});
  const data=await res.json();
  if(!data.ok) return;
  duelId=data.duel_id; duelCode=data.code; isHost=true;
  document.getElementById('lobbyView').style.display='none';
  document.getElementById('waitingView').style.display='block';
  document.getElementById('duelCodeDisplay').textContent=duelCode;
  // Generate questions via AI
  try {
    const aiRes=await fetch('ai_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'quiz',topic,count})});
    const aiData=await aiRes.json();
    if(aiData.questions||aiData.cards){
      const qs=(aiData.questions||aiData.cards).slice(0,count).map(q=>({q:q.question||q.q,opts:q.options||q.opts,ans:q.answer??q.ans??0}));
      await fetch('duel.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'set_questions',duel_id:duelId,questions:qs})});
      document.getElementById('aiGenStatus').innerHTML='<div style="font-size:13px;opacity:0.8;">✅ '+qs.length+' câu hỏi sẵn sàng · Chờ đối thủ...</div>';
    }
  } catch(e){}
  pollTimer=setInterval(pollDuel,3000);
}

async function joinDuel(){
  const code=document.getElementById('joinCode').value.trim().toUpperCase();
  if(code.length!==6){alert('Mã 6 ký tự!');return;}
  const res=await fetch('duel.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'join_duel',code})});
  const data=await res.json();
  if(!data.ok){alert(data.msg||'Lỗi!');return;}
  duelId=data.duel_id; isHost=data.as==='host';
  document.getElementById('lobbyView').style.display='none';
  if(data.duel.status==='ready'||data.duel.status==='playing'){
    startPlaying(data.duel);
  } else {
    document.getElementById('waitingView').style.display='block';
    document.getElementById('duelCodeDisplay').textContent=data.duel.code;
    pollTimer=setInterval(pollDuel,3000);
  }
}

async function pollDuel(){
  if(!duelId) return;
  const res=await fetch('duel.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_duel',duel_id:duelId})});
  const data=await res.json();
  if(!data.ok) return;
  const duel=data.duel;
  if(duel.status==='playing'||duel.status==='ready'){
    if(document.getElementById('waitingView').style.display!=='none'){
      clearInterval(pollTimer);
      startPlaying(duel);
    }
  }
  if(duel.status==='done'){
    clearInterval(pollTimer);
    showResult(duel);
  }
}

function startPlaying(duel){
  questions=duel.questions_parsed||JSON.parse(duel.questions||'[]');
  if(!questions.length){alert('Chưa có câu hỏi!');return;}
  document.getElementById('waitingView').style.display='none';
  document.getElementById('playingView').style.display='block';
  const amHost=duel.host_id==ME;
  document.getElementById('p1Name').textContent=ME_NAME+' (Bạn)';
  document.getElementById('p2Name').textContent=amHost?duel.guest_name||'Đối thủ':duel.host_name||'Đối thủ';
  renderQuestion();
  if(duel.status==='done') pollTimer=null;
  else pollTimer=setInterval(pollDuel,4000);
}

function renderQuestion(){
  document.getElementById('duelProgress').textContent='Câu '+(qIdx+1)+'/'+questions.length;
  const q=questions[qIdx];
  const answered=myAnswers[qIdx]!==undefined;
  document.getElementById('questionArea').innerHTML=`
    <div class="q-card">
      <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px;">${q.q}</div>
      <div class="q-opts">${(q.opts||[]).map((o,i)=>`<div class="q-opt ${myAnswers[qIdx]===i?'selected':''}" onclick="selectOpt(${i})" id="dopt${i}">${String.fromCharCode(65+i)}. ${o}</div>`).join('')}</div>
    </div>`;
  const allAnswered=Object.keys(myAnswers).length===questions.length;
  document.getElementById('submitBtn').style.display=allAnswered?'block':'none';
  document.getElementById('nextQBtn').textContent=qIdx===questions.length-1?'Câu cuối':'Tiếp →';
}

function selectOpt(i){myAnswers[qIdx]=i;renderQuestion();}
function prevQ(){if(qIdx>0){qIdx--;renderQuestion();}}
function nextQ(){if(qIdx<questions.length-1){qIdx++;renderQuestion();}}

async function submitAnswers(){
  if(!confirm('Nộp bài?')) return;
  const res=await fetch('duel.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'submit_answer',duel_id:duelId,answers:myAnswers})});
  const data=await res.json();
  document.getElementById('p1Score').textContent=data.my_score;
  document.getElementById('submitBtn').style.display='none';
  if(data.status==='done'){
    const r=await fetch('duel.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_duel',duel_id:duelId})});
    const rd=await r.json();
    showResult(rd.duel);
  } else {
    alert('Đã nộp! Chờ đối thủ...');
    pollTimer=setInterval(pollDuel,3000);
  }
}

function showResult(duel){
  clearInterval(pollTimer);
  document.getElementById('playingView').style.display='none';
  document.getElementById('resultView').style.display='block';
  const amHost=duel.host_id==ME;
  const myScore=amHost?duel.host_score:duel.guest_score;
  const theirScore=amHost?duel.guest_score:duel.host_score;
  const myName=amHost?duel.host_name:duel.guest_name;
  const theirName=amHost?duel.guest_name||'Đối thủ':duel.host_name||'Đối thủ';
  const won=myScore>theirScore;
  const tied=myScore===theirScore;
  document.getElementById('resultEmoji').textContent=won?'🏆':tied?'🤝':'😅';
  document.getElementById('resultTitle').textContent=won?'Bạn thắng!':tied?'Hòa!':'Thua rồi!';
  document.getElementById('r1Name').textContent=myName+' (Bạn)';
  document.getElementById('r1Score').textContent=myScore+'/'+questions.length;
  document.getElementById('r2Name').textContent=theirName;
  document.getElementById('r2Score').textContent=theirScore+'/'+questions.length;
}

function copyDuelCode(){navigator.clipboard.writeText(duelCode).then(()=>alert('Đã copy: '+duelCode));}
</script>
</body>
</html>
