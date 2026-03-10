<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];

$db->exec("CREATE TABLE IF NOT EXISTS question_bank (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    creator_id INTEGER,
    subject TEXT NOT NULL,
    grade TEXT DEFAULT '10',
    title TEXT NOT NULL,
    questions TEXT NOT NULL,
    is_public INTEGER DEFAULT 1,
    play_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Seed some default question sets if empty
$count=(int)$db->query("SELECT COUNT(*) as c FROM question_bank WHERE is_public=1")->fetchArray()['c'];
if($count===0){
    $sets=[
        ['Tiếng Anh','IELTS','Từ vựng IELTS Band 6-7','[{"q":"What does \'ubiquitous\' mean?","opts":["Có mặt khắp nơi","Hiếm gặp","Nhanh chóng","Cổ xưa"],"ans":0},{"q":"\'Ameliorate\' means to...","opts":["Xấu đi","Cải thiện","Giữ nguyên","Phá hủy"],"ans":1},{"q":"\'Ephemeral\' means...","opts":["Vĩnh cửu","Thoáng qua","Mạnh mẽ","Phức tạp"],"ans":1},{"q":"\'Pragmatic\' means...","opts":["Thực tế","Lãng mạn","Tức giận","Tinh tế"],"ans":0},{"q":"\'Eloquent\' describes someone who is...","opts":["Im lặng","Nói hay, lưu loát","Ngại ngùng","Chậm chạp"],"ans":1}]'],
        ['Toán','12','Đạo hàm cơ bản','[{"q":"Đạo hàm của x² là gì?","opts":["x","2x","2","x³/3"],"ans":1},{"q":"Đạo hàm của sin(x) là gì?","opts":["cos(x)","-cos(x)","sin(x)","-sin(x)"],"ans":0},{"q":"Đạo hàm của e^x là gì?","opts":["xe^(x-1)","e^x","1/e^x","ln(x)"],"ans":1},{"q":"Đạo hàm của ln(x) là gì?","opts":["1/x","x","ln(x)/x","e^x"],"ans":0},{"q":"Đạo hàm của cos(x) là gì?","opts":["sin(x)","-sin(x)","cos(x)","-cos(x)"],"ans":1}]'],
        ['Lý','10','Chuyển động thẳng đều','[{"q":"Công thức tính vận tốc trung bình là gì?","opts":["v=s/t","v=s×t","v=a/t","v=F/m"],"ans":0},{"q":"Đơn vị của gia tốc là gì?","opts":["m/s","m/s²","kg.m/s","N/m"],"ans":1},{"q":"Chuyển động thẳng đều có gia tốc bằng mấy?","opts":["1 m/s²","Khác 0","0","Tùy trường hợp"],"ans":2},{"q":"Trong chuyển động thẳng đều, đồ thị v-t là gì?","opts":["Đường cong","Đường thẳng song song trục t","Đường thẳng qua gốc tọa độ","Đường parabol"],"ans":1},{"q":"Công thức tính quãng đường trong chuyển động thẳng đều?","opts":["s=v²/2a","s=v×t","s=½at²","s=v₀t+½at²"],"ans":1}]'],
        ['Tiếng Anh','9','Thì quá khứ đơn','[{"q":"She ___ to school yesterday.","opts":["go","goes","went","gone"],"ans":2},{"q":"They ___ a movie last night.","opts":["watch","watched","watching","watches"],"ans":1},{"q":"I ___ my homework two hours ago.","opts":["finish","finishing","finished","have finished"],"ans":2},{"q":"He ___ born in 1990.","opts":["is","are","was","were"],"ans":2},{"q":"We ___ at the park all afternoon.","opts":["play","played","playing","plays"],"ans":1}]'],
        ['Hóa','11','Liên kết hóa học','[{"q":"Liên kết ion được hình thành giữa...","opts":["Kim loại và phi kim","Hai phi kim","Hai kim loại","Kim loại và kim loại"],"ans":0},{"q":"NaCl là ví dụ của loại liên kết nào?","opts":["Cộng hóa trị","Ion","Kim loại","Hydro"],"ans":1},{"q":"Liên kết cộng hóa trị phân cực xảy ra khi...","opts":["Hai nguyên tử giống nhau","Hai nguyên tử khác nhau, độ âm điện gần nhau","Hai nguyên tử khác nhau, chênh lệch độ âm điện","Ion hút nhau"],"ans":2},{"q":"H₂O có kiểu liên kết nào?","opts":["Ion","Cộng hóa trị không phân cực","Cộng hóa trị phân cực","Kim loại"],"ans":2},{"q":"O₂ có loại liên kết nào?","opts":["Đơn","Đôi","Ba","Ion"],"ans":1}]'],
    ];
    foreach($sets as $s){
        $db->exec("INSERT INTO question_bank (creator_id,subject,grade,title,questions,is_public) VALUES (NULL,'".SQLite3::escapeString($s[0])."','$s[1]','".SQLite3::escapeString($s[2])."','".SQLite3::escapeString($s[3])."',1)");
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json');
    $input=json_decode(file_get_contents('php://input'),true)??[];
    $action=$input['action']??'';

    if($action==='get_set'){
        $id=(int)($input['id']??0);
        $r=$db->query("SELECT * FROM question_bank WHERE id=$id AND (is_public=1 OR creator_id=$uid)")->fetchArray(SQLITE3_ASSOC);
        if(!$r){echo json_encode(['ok'=>false]);exit;}
        $r['questions']=json_decode($r['questions'],true);
        $db->exec("UPDATE question_bank SET play_count=play_count+1 WHERE id=$id");
        echo json_encode(['ok'=>true,'set'=>$r]); exit;
    }

    if($action==='save_set'){
        $subject=SQLite3::escapeString($input['subject']??'');
        $grade=SQLite3::escapeString($input['grade']??'10');
        $title=SQLite3::escapeString($input['title']??'');
        $questions=SQLite3::escapeString(json_encode($input['questions']??[]));
        $public=(int)($input['is_public']??0);
        $db->exec("INSERT INTO question_bank (creator_id,subject,grade,title,questions,is_public) VALUES ($uid,'$subject','$grade','$title','$questions',$public)");
        require_once __DIR__ . '/gamification.php';
        awardXP($uid,'quiz',15,"Tạo bộ câu hỏi: $title");
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertRowID()]); exit;
    }

    if($action==='ai_generate'){
        $subject=SQLite3::escapeString($input['subject']??'');
        $topic=SQLite3::escapeString($input['topic']??'');
        $count=max(5,min(20,(int)($input['count']??10)));
        // Call AI via ai_api
        echo json_encode(['ok'=>true,'redirect'=>"ai_api.php",'params'=>['type'=>'quiz','topic'=>"$subject: $topic",'count'=>$count]]); exit;
    }

    echo json_encode(['ok'=>false]); exit;
}

$subjects=['Tiếng Anh','Toán','Lý','Hóa','Sinh','Văn','Sử','Địa','GDCD','Tin học','Khác'];
$filterSubject=$_GET['subject']??'';
$filterGrade=$_GET['grade']??'';
$search=SQLite3::escapeString($_GET['q']??'');
$where="is_public=1";
if($filterSubject) $where.=" AND subject='".SQLite3::escapeString($filterSubject)."'";
if($filterGrade)   $where.=" AND grade='".SQLite3::escapeString($filterGrade)."'";
if($search)        $where.=" AND (title LIKE '%$search%' OR subject LIKE '%$search%')";
$sets=[];
$rows=$db->query("SELECT id,subject,grade,title,play_count,created_at,(SELECT COUNT(*) FROM json_each(questions)) as qcount FROM question_bank WHERE $where ORDER BY play_count DESC,created_at DESC LIMIT 30");
while($r=$rows->fetchArray(SQLITE3_ASSOC)) $sets[]=$r;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ngân hàng đề — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.bank-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center;}
.filter-chip{padding:6px 14px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface2);font-size:12px;font-weight:700;cursor:pointer;color:var(--text2);transition:all 0.15s;white-space:nowrap;}
.filter-chip:hover,.filter-chip.active{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
.bank-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin-bottom:1.5rem;}
.bank-card{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:16px;cursor:pointer;transition:all 0.15s;position:relative;}
.bank-card:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,0.1);}
.bank-subject{display:inline-flex;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;background:var(--accent-soft);color:var(--accent);margin-bottom:8px;}
.bank-title{font-size:14px;font-weight:800;color:var(--text);margin-bottom:6px;line-height:1.4;}
.bank-meta{display:flex;gap:12px;font-size:11px;color:var(--muted);}
.quiz-modal{position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:flex;align-items:center;justify-content:center;padding:1rem;}
.quiz-modal-box{background:var(--surface);border-radius:20px;padding:2rem;max-width:560px;width:100%;max-height:90vh;overflow-y:auto;}
.quiz-option{padding:12px 16px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;margin-bottom:8px;font-size:13px;font-weight:600;transition:all 0.15s;}
.quiz-option:hover{border-color:var(--accent);background:var(--accent-soft);}
.quiz-option.correct{border-color:var(--green)!important;background:#dcfce7!important;color:#16a34a!important;}
.quiz-option.wrong{border-color:var(--red)!important;background:#fce7e7!important;color:#dc2626!important;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Học tập</div>
    <h1 class="page-title">📚 Ngân hàng đề thi</h1>
  </div>

  <div class="bank-filters">
    <input type="text" class="form-input" placeholder="🔍 Tìm kiếm..." id="searchInput" value="<?= htmlspecialchars($_GET['q']??'') ?>" oninput="applyFilter()" style="flex:1;min-width:200px;max-width:300px;">
    <?php foreach($subjects as $s): ?>
    <span class="filter-chip <?= $filterSubject===$s?'active':'' ?>" onclick="filterSubject('<?= htmlspecialchars($s) ?>')"><?= $s ?></span>
    <?php endforeach; ?>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('createModal').style.display='flex'">➕ Tạo bộ đề</button>
  </div>

  <div class="bank-grid">
    <?php foreach($sets as $s):
      $icons=['Tiếng Anh'=>'🇬🇧','Toán'=>'📐','Lý'=>'⚡','Hóa'=>'🧪','Sinh'=>'🌱','Văn'=>'✍️','Sử'=>'📜','Địa'=>'🌍','GDCD'=>'⚖️','Tin học'=>'💻'];
      $icon=$icons[$s['subject']]??'📚'; ?>
    <div class="bank-card" onclick="openSet(<?= $s['id'] ?>)">
      <div class="bank-subject"><?= $icon ?> <?= htmlspecialchars($s['subject']) ?> · Lớp <?= htmlspecialchars($s['grade']) ?></div>
      <div class="bank-title"><?= htmlspecialchars($s['title']) ?></div>
      <div class="bank-meta">
        <span>📝 <?= $s['qcount'] ?> câu</span>
        <span>▶️ <?= $s['play_count'] ?> lượt</span>
        <span>📅 <?= date('d/m',strtotime($s['created_at'])) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($sets)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--muted);">
      <div style="font-size:3rem;margin-bottom:12px;">📚</div>
      <div>Không tìm thấy bộ đề nào</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Quiz modal -->
<div class="quiz-modal" id="quizModal" style="display:none;" onclick="if(event.target===this)closeQuiz()">
  <div class="quiz-modal-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <div style="font-size:16px;font-weight:800;" id="quizTitle">Quiz</div>
      <button onclick="closeQuiz()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);">✕</button>
    </div>
    <div id="quizProgress" style="font-size:12px;color:var(--muted);margin-bottom:12px;"></div>
    <div style="height:4px;background:var(--surface2);border-radius:99px;margin-bottom:16px;overflow:hidden;">
      <div id="quizBar" style="height:100%;background:var(--accent);border-radius:99px;transition:width 0.3s;width:0%"></div>
    </div>
    <div style="font-size:1rem;font-weight:700;color:var(--text);margin-bottom:16px;line-height:1.5;" id="quizQuestion"></div>
    <div id="quizOptions"></div>
    <div id="quizFeedback" style="display:none;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
    <button class="btn btn-primary" id="quizNext" style="display:none;width:100%;" onclick="nextQuestion()">Câu tiếp →</button>
    <div id="quizResult" style="display:none;text-align:center;padding:1rem 0;">
      <div style="font-size:3rem;margin-bottom:12px;" id="quizResultEmoji">🎉</div>
      <div style="font-size:1.3rem;font-weight:800;" id="quizResultText"></div>
      <div style="font-size:13px;color:var(--muted);margin:8px 0 16px;" id="quizResultSub"></div>
      <div style="display:flex;gap:8px;justify-content:center;">
        <button class="btn btn-primary" onclick="restartQuiz()">🔁 Làm lại</button>
        <button class="btn btn-ghost" onclick="closeQuiz()">Đóng</button>
      </div>
    </div>
  </div>
</div>

<!-- Create set modal -->
<div class="quiz-modal" id="createModal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
  <div class="quiz-modal-box">
    <div style="font-size:16px;font-weight:800;margin-bottom:1rem;">➕ Tạo bộ đề mới</div>
    <div style="margin-bottom:10px;">
      <label style="font-size:12px;font-weight:700;color:var(--muted);">AI tạo tự động</label>
      <div style="display:flex;gap:8px;margin-top:6px;">
        <input type="text" id="aiSubject" class="form-input" placeholder="Môn học" style="flex:1">
        <input type="text" id="aiTopic" class="form-input" placeholder="Chủ đề" style="flex:2">
        <button class="btn btn-primary" onclick="aiGenerate()">✨ Tạo</button>
      </div>
    </div>
    <div style="text-align:center;font-size:12px;color:var(--muted);padding:8px 0;">— hoặc —</div>
    <div>
      <label style="font-size:12px;font-weight:700;color:var(--muted);">Tự nhập</label>
      <input type="text" id="newTitle" class="form-input" placeholder="Tên bộ đề" style="width:100%;margin-top:6px;margin-bottom:8px;">
      <div style="display:flex;gap:8px;margin-bottom:8px;">
        <select id="newSubject" class="form-input" style="flex:1">
          <?php foreach($subjects as $s) echo "<option>$s</option>"; ?>
        </select>
        <select id="newGrade" class="form-input" style="flex:1">
          <?php for($g=6;$g<=12;$g++) echo "<option>$g</option>"; echo "<option>ĐH</option>"; ?>
        </select>
      </div>
      <div id="newQuestions" style="display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto;"></div>
      <button class="btn btn-ghost btn-sm" onclick="addQuestion()" style="width:100%;margin-top:8px;">+ Thêm câu hỏi</button>
      <div style="display:flex;gap:8px;margin-top:12px;">
        <label style="font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
          <input type="checkbox" id="isPublic" checked> Chia sẻ công khai
        </label>
        <button class="btn btn-primary" onclick="saveSet()" style="margin-left:auto;">💾 Lưu</button>
      </div>
    </div>
  </div>
</div>

<script>
let currentSet=null,qIdx=0,score=0,answered=false;

function filterSubject(s){
  const url=new URL(window.location);
  url.searchParams.set('subject',s===url.searchParams.get('subject')?'':s);
  window.location=url;
}
function applyFilter(){
  const q=document.getElementById('searchInput').value;
  const url=new URL(window.location);
  url.searchParams.set('q',q);
  clearTimeout(window._ft);
  window._ft=setTimeout(()=>window.location=url,600);
}

async function openSet(id){
  const res=await fetch('question_bank.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_set',id})});
  const data=await res.json();
  if(!data.ok)return;
  currentSet=data.set; qIdx=0; score=0; answered=false;
  document.getElementById('quizTitle').textContent=currentSet.title;
  document.getElementById('quizResult').style.display='none';
  document.getElementById('quizModal').style.display='flex';
  showQuestion();
}
function showQuestion(){
  const q=currentSet.questions[qIdx];
  const total=currentSet.questions.length;
  document.getElementById('quizProgress').textContent=`Câu ${qIdx+1}/${total}`;
  document.getElementById('quizBar').style.width=(qIdx/total*100)+'%';
  document.getElementById('quizQuestion').textContent=q.q;
  document.getElementById('quizFeedback').style.display='none';
  document.getElementById('quizNext').style.display='none';
  answered=false;
  document.getElementById('quizOptions').innerHTML=q.opts.map((o,i)=>`<div class="quiz-option" onclick="answer(${i})" id="opt${i}">${String.fromCharCode(65+i)}. ${o}</div>`).join('');
}
function answer(i){
  if(answered)return;
  answered=true;
  const q=currentSet.questions[qIdx];
  const fb=document.getElementById('quizFeedback');
  if(i===q.ans){
    score++;
    document.getElementById('opt'+i).classList.add('correct');
    fb.style.background='#dcfce7';fb.style.color='#16a34a';fb.textContent='✅ Chính xác!';
  } else {
    document.getElementById('opt'+i).classList.add('wrong');
    document.getElementById('opt'+q.ans).classList.add('correct');
    fb.style.background='#fce7e7';fb.style.color='#dc2626';fb.textContent='❌ Đáp án đúng: '+String.fromCharCode(65+q.ans)+'. '+q.opts[q.ans];
  }
  fb.style.display='block';
  document.getElementById('quizNext').style.display='block';
}
function nextQuestion(){
  qIdx++;
  if(qIdx<currentSet.questions.length) showQuestion();
  else showResult();
}
function showResult(){
  document.getElementById('quizOptions').innerHTML='';
  document.getElementById('quizFeedback').style.display='none';
  document.getElementById('quizNext').style.display='none';
  document.getElementById('quizResult').style.display='block';
  const pct=Math.round(score/currentSet.questions.length*100);
  document.getElementById('quizResultEmoji').textContent=pct>=80?'🎉':pct>=60?'😊':'😅';
  document.getElementById('quizResultText').textContent=`${score}/${currentSet.questions.length} câu đúng`;
  document.getElementById('quizResultSub').textContent=pct+'% - '+(pct>=80?'Xuất sắc!':pct>=60?'Khá tốt!':'Cố gắng hơn nhé!');
  document.getElementById('quizBar').style.width='100%';
  // Save result
  fetch('save_quiz.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({topic:currentSet.title,score,total:currentSet.questions.length})});
}
function restartQuiz(){qIdx=0;score=0;answered=false;document.getElementById('quizResult').style.display='none';showQuestion();}
function closeQuiz(){document.getElementById('quizModal').style.display='none';}

function addQuestion(){
  const wrap=document.getElementById('newQuestions');
  const i=wrap.children.length+1;
  const div=document.createElement('div');
  div.style.cssText='background:var(--surface2);border-radius:10px;padding:10px;';
  div.innerHTML=`<div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;">Câu ${i}</div>
    <input type="text" placeholder="Câu hỏi" class="form-input q-question" style="width:100%;margin-bottom:6px;">
    ${[0,1,2,3].map(j=>`<input type="text" placeholder="Đáp án ${String.fromCharCode(65+j)}" class="form-input q-opt" style="width:100%;margin-bottom:4px;">`).join('')}
    <select class="form-input q-ans" style="width:100%;margin-top:4px;"><option value="0">Đáp án đúng: A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select>`;
  wrap.appendChild(div);
}

async function saveSet(){
  const title=document.getElementById('newTitle').value.trim();
  if(!title){alert('Nhập tên bộ đề!');return;}
  const qs=[];
  document.querySelectorAll('#newQuestions > div').forEach(d=>{
    const q=d.querySelector('.q-question').value.trim();
    const opts=[...d.querySelectorAll('.q-opt')].map(i=>i.value.trim());
    const ans=parseInt(d.querySelector('.q-ans').value);
    if(q&&opts[0]) qs.push({q,opts,ans});
  });
  if(!qs.length){alert('Thêm ít nhất 1 câu hỏi!');return;}
  const res=await fetch('question_bank.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save_set',title,subject:document.getElementById('newSubject').value,grade:document.getElementById('newGrade').value,questions:qs,is_public:document.getElementById('isPublic').checked?1:0})});
  const data=await res.json();
  if(data.ok){location.reload();}
}

async function aiGenerate(){
  const subject=document.getElementById('aiSubject').value.trim();
  const topic=document.getElementById('aiTopic').value.trim();
  if(!subject||!topic){alert('Nhập môn học và chủ đề!');return;}
  const btn=event.target;btn.textContent='⏳';btn.disabled=true;
  const res=await fetch('ai_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'quiz',topic:`${subject}: ${topic}`,count:10})});
  const data=await res.json();
  btn.textContent='✨ Tạo';btn.disabled=false;
  if(data.cards||data.questions){
    const qs=(data.cards||data.questions).map(q=>({q:q.question||q.q,opts:q.options||q.opts,ans:q.answer??q.ans??0}));
    document.getElementById('newTitle').value=`${subject}: ${topic}`;
    document.getElementById('newSubject').value=subject;
    document.getElementById('newQuestions').innerHTML='';
    qs.forEach(q=>{
      const div=document.createElement('div');div.style.cssText='background:var(--surface2);border-radius:10px;padding:10px;font-size:12px;color:var(--text2);';
      div.innerHTML=`<strong>${q.q}</strong><br>${(q.opts||[]).map((o,i)=>String.fromCharCode(65+i)+'. '+o).join(' · ')}<input type="hidden" class="q-question" value="${q.q.replace(/"/g,"'")}"><input type="hidden" class="q-ans" value="${q.ans}">${(q.opts||[]).map(o=>`<input type="hidden" class="q-opt" value="${o.replace(/"/g,"'")}">`).join('')}`;
      document.getElementById('newQuestions').appendChild(div);
    });
    alert(`✅ AI đã tạo ${qs.length} câu hỏi! Nhấn Lưu để thêm vào ngân hàng đề.`);
  }
}
</script>
</body>
</html>
