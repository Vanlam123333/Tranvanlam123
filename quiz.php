<?php
require_once __DIR__ . "/db.php"; requireLogin(); $uid = $_SESSION['user_id']; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiz — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
.quiz-opt{padding:0.85rem 1.2rem;border-radius:10px;border:1px solid var(--border);background:var(--surface2);color:var(--text);text-align:left;cursor:pointer;font-family:var(--font-body);font-size:0.92rem;transition:all 0.18s;width:100%;margin-bottom:0.5rem;}
.quiz-opt:hover:not(:disabled){border-color:var(--accent);background:rgba(91,127,255,0.08);}
.quiz-opt:disabled{cursor:default;}
.quiz-opt.correct{border-color:var(--green);background:rgba(52,211,153,0.1);color:var(--green);font-weight:700;}
.quiz-opt.wrong{border-color:var(--red);background:rgba(248,113,113,0.1);color:var(--red);}
em, i { font-style: normal !important; }
* { font-style: normal; }
</style>
</head>
<body>
<?php
require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <h1 class="page-title">🎯 Quiz thích nghi AI</h1>
  <div id="startArea">
    <div class="card">
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Chủ đề</label>
          <input type="text" id="quizTopic" class="form-input" placeholder="Toán vi tích phân, Hóa hữu cơ, IELTS..." onkeydown="if(event.key==='Enter')startQuiz()">
        </div>
        <div class="form-group">
          <label class="form-label">Độ khó ban đầu</label>
          <div style="display:flex;gap:0.5rem;">
            <button class="btn btn-success active-lvl" id="ql-1" onclick="setLvl(1)">🟢 Dễ</button>
            <button class="btn btn-ghost" id="ql-2" onclick="setLvl(2)">🟡 Trung bình</button>
            <button class="btn btn-ghost" id="ql-3" onclick="setLvl(3)">🔴 Khó</button>
          </div>
        </div>
        <button class="btn btn-primary btn-full" onclick="startQuiz()">🎯 Bắt đầu Quiz</button>
      </div>
    </div>
  </div>

  <div id="quizArea" style="display:none;">
    <div class="card">
      <div class="card-header">
        <div id="lvlBadge" class="badge badge-green">🟢 Dễ</div>
        <div style="display:flex;gap:0.75rem;">
          <span class="badge badge-blue">✅ <span id="qScore">0</span>/<span id="qTotal">0</span></span>
          <span class="badge badge-blue">🔥 <span id="qStreak">0</span></span>
          <span id="qAdapt" style="font-size:0.8rem;color:var(--muted);font-family:var(--font-head);font-weight:700;"></span>
        </div>
      </div>
      <div class="card-body">
        <div id="quizQ" style="background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:1.4rem;font-size:1rem;font-weight:600;line-height:1.6;margin-bottom:1rem;"></div>
        <div id="quizOpts"></div>
        <div id="feedback" style="display:none;margin-top:0.75rem;">
          <div id="feedbackText" style="background:var(--surface2);border-left:3px solid var(--accent);border-radius:0 14px 14px 0;padding:1rem 1.2rem;font-size:0.88rem;line-height:1.75;color:var(--muted);margin-bottom:0.75rem;"></div>
          <div style="display:flex;gap:0.6rem;">
            <button class="btn btn-primary" onclick="nextQ()">Câu tiếp →</button>
            <button class="btn btn-ghost" onclick="resetQuiz()">↩ Đổi chủ đề</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
let level=1, streak=0, correct=0, total=0, topic='', curAnswer=-1, curExplain='';
function setLvl(l){
  level=l;
  [1,2,3].forEach(x=>{
    const b=document.getElementById('ql-'+x);
    b.className='btn '+(x===l?(['btn-success','btn-ghost','btn-danger'][l-1]):'btn-ghost');
  });
}
function startQuiz(){
  topic=document.getElementById('quizTopic').value.trim();
  if(!topic)return alert('Nhập chủ đề đi!');
  streak=0; correct=0; total=0;
  document.getElementById('startArea').style.display='none';
  document.getElementById('quizArea').style.display='block';
  loadQ();
}
function resetQuiz(){
  document.getElementById('startArea').style.display='block';
  document.getElementById('quizArea').style.display='none';
  // Save score to server
  if(total>0){
    fetch('save_quiz.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({topic,score:correct,total,level})});
  }
}
async function loadQ(){
  const lvlBadge=document.getElementById('lvlBadge');
  const labels={1:'🟢 Dễ',2:'🟡 Trung bình',3:'🔴 Khó'};
  const classes={1:'badge-green',2:'badge badge-blue',3:'badge-red'};
  lvlBadge.textContent=labels[level]; lvlBadge.className='badge '+classes[level];
  document.getElementById('quizQ').innerHTML='<span style="color:var(--muted);">⏳ Đang tạo câu hỏi...</span>';
  document.getElementById('quizOpts').innerHTML='';
  document.getElementById('feedback').style.display='none';
  try{
    const res=await fetch('ai_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'quiz',topic,level})});
    const data=await res.json();
    const q=data.question;
    curAnswer=q.answer; curExplain=q.explain;
    document.getElementById('quizQ').textContent=q.question;
    document.getElementById('quizOpts').innerHTML=q.options.map((o,i)=>
      `<button class="quiz-opt" onclick="answer(${i})">${o}</button>`).join('');
  }catch(e){document.getElementById('quizQ').textContent='⚠️ Lỗi tải câu hỏi!';}
}
function answer(i){
  document.querySelectorAll('.quiz-opt').forEach(b=>b.disabled=true);
  const correct_ans = i===curAnswer;
  document.querySelectorAll('.quiz-opt')[curAnswer].classList.add('correct');
  if(!correct_ans) document.querySelectorAll('.quiz-opt')[i].classList.add('wrong');
  total++;
  if(correct_ans){correct++;streak++;if(streak>=2&&level<3){level++;document.getElementById('qAdapt').textContent='📈 Tăng độ khó!';}}
  else{streak=0;if(level>1){level--;document.getElementById('qAdapt').textContent='📉 Giảm độ khó';}else document.getElementById('qAdapt').textContent='';}
  document.getElementById('qScore').textContent=correct;
  document.getElementById('qTotal').textContent=total;
  document.getElementById('qStreak').textContent=streak;
  document.getElementById('feedbackText').textContent=(correct_ans?'✅ Chính xác! ':'❌ Chưa đúng. ')+curExplain;
  document.getElementById('feedback').style.display='block';
}
function nextQ(){document.getElementById('qAdapt').textContent='';loadQ();}
window.addEventListener('beforeunload',()=>{if(total>0)fetch('save_quiz.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({topic,score:correct,total,level})});});
</script>
</body>
</html>
