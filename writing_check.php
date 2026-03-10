<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];
$db->exec("CREATE TABLE IF NOT EXISTS writing_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    essay TEXT NOT NULL,
    feedback TEXT,
    score INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Chấm bài viết — MindSpark</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.writing-layout{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start;}
@media(max-width:768px){.writing-layout{grid-template-columns:1fr;}}
.essay-area{width:100%;min-height:340px;background:var(--surface2);border:1.5px solid var(--border);border-radius:12px;padding:14px;color:var(--text);font-family:var(--font);font-size:14px;line-height:1.9;resize:vertical;outline:none;transition:border-color 0.15s;box-sizing:border-box;}
.essay-area:focus{border-color:var(--accent);}
.score-ring{width:80px;height:80px;position:relative;flex-shrink:0;}
.score-ring svg{transform:rotate(-90deg);}
.score-ring-bg{fill:none;stroke:var(--surface2);stroke-width:8;}
.score-ring-fill{fill:none;stroke-width:8;stroke-linecap:round;transition:stroke-dashoffset 1s ease;}
.score-ring-inner{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:900;}
.feedback-section{margin-bottom:14px;padding:14px;background:var(--surface2);border-radius:12px;}
.feedback-section-title{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:var(--muted);margin-bottom:8px;}
.feedback-text{font-size:13px;color:var(--text2);line-height:1.7;}
.score-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:800;}
.prompt-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;}
.prompt-chip{padding:6px 12px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface2);font-size:11px;font-weight:700;cursor:pointer;color:var(--text2);transition:all 0.15s;}
.prompt-chip:hover{border-color:var(--accent);color:var(--accent);}
.hist-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:10px;cursor:pointer;border-bottom:1px solid var(--border);}
.hist-item:last-child{border-bottom:none;}
.hist-item:hover{background:var(--surface2);}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">AI Tool</div>
    <h1 class="page-title">✍️ Chấm bài viết tiếng Anh</h1>
  </div>

  <div class="writing-layout">
    <div>
      <div class="card" style="margin-bottom:1rem;">
        <div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <div style="font-size:14px;font-weight:800;">📝 Bài viết của bạn</div>
            <div style="font-size:11px;color:var(--muted);" id="charCount">0 từ</div>
          </div>
          <textarea class="essay-area" id="essayInput" placeholder="Paste bài viết tiếng Anh vào đây...&#10;&#10;Hỗ trợ: IELTS Writing Task 1/2, paragraph, essay, email..." oninput="countWords()"></textarea>
          <div style="margin-top:10px;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:6px;">Đề bài mẫu:</div>
            <div class="prompt-chips">
              <span class="prompt-chip" onclick="usePrompt(this)">IELTS Task 2: Advantages of technology</span>
              <span class="prompt-chip" onclick="usePrompt(this)">Email: Complain to a hotel</span>
              <span class="prompt-chip" onclick="usePrompt(this)">Describe your hometown</span>
            </div>
          </div>
          <button class="btn btn-primary" id="checkBtn" onclick="checkWriting()" style="width:100%;margin-top:14px;">🎓 Chấm bài</button>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div style="font-size:14px;font-weight:800;margin-bottom:10px;">📅 Lịch sử bài viết</div>
          <div id="histList">
            <?php
            $rows=$db->query("SELECT id,score,substr(essay,1,80) as preview,created_at FROM writing_submissions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");
            $cnt=0;
            while($r=$rows->fetchArray(SQLITE3_ASSOC)){$cnt++;
              $scoreColor=$r['score']>=8?'var(--green)':$r['score']>=6?'var(--gold)':'var(--red)';
              echo '<div class="hist-item" onclick="loadHistory('.$r['id'].')">
                <div style="width:36px;height:36px;border-radius:50%;background:'.$scoreColor.';display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;color:#fff;flex-shrink:0;">'.$r['score'].'/10</div>
                <div style="flex:1;min-width:0;">
                  <div style="font-size:12px;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'.htmlspecialchars($r['preview']).'...</div>
                  <div style="font-size:10px;color:var(--muted);">'.date('d/m/Y H:i',strtotime($r['created_at'])).'</div>
                </div>
              </div>';
            }
            if(!$cnt) echo '<div style="text-align:center;padding:2rem;color:var(--muted);font-size:13px;">Chưa có bài nào</div>';
            ?>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <div style="font-size:14px;font-weight:800;">📊 Kết quả chấm</div>
            <div id="overallScore" style="display:none;"></div>
          </div>
          <div id="feedbackEmpty" style="text-align:center;padding:4rem 2rem;color:var(--muted);">
            <div style="font-size:3rem;margin-bottom:12px;opacity:0.4;">✍️</div>
            <div style="font-size:14px;">Nhập bài viết và nhấn <strong>Chấm bài</strong></div>
          </div>
          <div id="feedbackLoading" style="display:none;text-align:center;padding:3rem;color:var(--muted);">
            <div style="font-size:2rem;margin-bottom:12px;animation:spin 2s linear infinite;">⚙️</div>
            <div style="font-size:13px;">AI đang chấm bài...</div>
          </div>
          <div id="feedbackContent" style="display:none;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function countWords(){
  const t=document.getElementById('essayInput').value.trim();
  document.getElementById('charCount').textContent=(t?t.split(/\s+/).length:0)+' từ';
}
function usePrompt(el){document.getElementById('essayInput').focus();}

async function checkWriting(){
  const essay=document.getElementById('essayInput').value.trim();
  if(essay.length<50){alert('Viết ít nhất 50 ký tự!');return;}
  const btn=document.getElementById('checkBtn'); btn.disabled=true; btn.textContent='⏳ Đang chấm...';
  document.getElementById('feedbackEmpty').style.display='none';
  document.getElementById('feedbackLoading').style.display='block';
  document.getElementById('feedbackContent').style.display='none';
  document.getElementById('overallScore').style.display='none';
  try {
    const res=await fetch('ai_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'writing_check',essay})});
    const data=await res.json();
    if(data.result) displayFeedback(data.result, essay);
  } catch(e){alert('Lỗi kết nối AI!');}
  document.getElementById('feedbackLoading').style.display='none';
  btn.disabled=false; btn.textContent='🎓 Chấm bài';
}

function displayFeedback(raw, essay){
  // Parse scores from response
  const scoreMatch=raw.match(/(?:điểm tổng|overall)[:\s]*(\d+(?:\.\d+)?)\s*\/\s*10/i);
  const overall=scoreMatch?parseFloat(scoreMatch[1]):0;
  if(overall>0){
    const color=overall>=8?'#16a34a':overall>=6?'#d97706':'#dc2626';
    const circ=2*Math.PI*34; const offset=circ*(1-overall/10);
    document.getElementById('overallScore').style.display='block';
    document.getElementById('overallScore').innerHTML=`<div class="score-ring"><svg width="80" height="80" viewBox="0 0 80 80"><circle class="score-ring-bg" cx="40" cy="40" r="34"/><circle class="score-ring-fill" cx="40" cy="40" r="34" stroke="${color}" stroke-dasharray="${circ.toFixed(2)}" stroke-dashoffset="${offset.toFixed(2)}"/></svg><div class="score-ring-inner" style="color:${color}">${overall}</div></div>`;
    // Save to history
    fetch('writing_check.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save',essay,feedback:raw,score:Math.round(overall)})});
  }
  // Render sections
  const sections=raw.split(/\n(?=\d+\.|[A-Z][a-zA-Z]+:)/);
  let html='';
  sections.forEach(s=>{
    if(s.trim()) html+=`<div class="feedback-section"><div class="feedback-text">${s.trim().replace(/\n/g,'<br>').replace(/(\d+\/10)/g,'<strong style="color:var(--accent)">$1</strong>')}</div></div>`;
  });
  document.getElementById('feedbackContent').innerHTML=html||`<div class="feedback-section"><div class="feedback-text">${raw.replace(/\n/g,'<br>')}</div></div>`;
  document.getElementById('feedbackContent').style.display='block';
}

async function loadHistory(id){
  const res=await fetch('writing_check.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'load',id})});
  const data=await res.json();
  if(data.ok){document.getElementById('essayInput').value=data.essay;countWords();displayFeedback(data.feedback,data.essay);}
}

// Handle POST
<?php if($_SERVER['REQUEST_METHOD']==='POST'):
  header('Content-Type: application/json');
  $input=json_decode(file_get_contents('php://input'),true)??[];
  $action=$input['action']??'';
  if($action==='save'){
    $essay=SQLite3::escapeString($input['essay']??'');
    $fb=SQLite3::escapeString($input['feedback']??'');
    $score=(int)($input['score']??0);
    $db->exec("INSERT INTO writing_submissions (user_id,essay,feedback,score) VALUES ($uid,'$essay','$fb',$score)");
    require_once __DIR__ . '/gamification.php';
    awardXP($uid,'quiz',15,'Chấm bài viết tiếng Anh');
    echo json_encode(['ok'=>true]); exit;
  }
  if($action==='load'){
    $id=(int)($input['id']??0);
    $r=$db->query("SELECT * FROM writing_submissions WHERE id=$id AND user_id=$uid")->fetchArray(SQLITE3_ASSOC);
    if($r) echo json_encode(['ok'=>true,'essay'=>$r['essay'],'feedback'=>$r['feedback']]);
    else echo json_encode(['ok'=>false]);
    exit;
  }
  echo json_encode(['ok'=>false]); exit;
endif; ?>
</script>
</body>
</html>
