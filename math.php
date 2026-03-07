<?php
require_once __DIR__ . "/db.php"; requireLogin(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Toán học — MindSpark</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Lora:ital,wght@0,400;0,600;1,400&display=swap">
<link rel="stylesheet" href="style.css">
<style>
  :root {
    --font-head: 'Syne', 'Segoe UI', system-ui, sans-serif;
    --font-body: 'Lora', Georgia, serif;
  }
</style>
<!-- Math rendering -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/mathjs/11.11.0/math.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/contrib/auto-render.min.js"></script>
<style>
/* TABS */
.math-tabs{display:flex;gap:0.4rem;margin-bottom:1.5rem;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:0.4rem;}
.math-tab{flex:1;padding:0.6rem 0.5rem;border-radius:9px;border:none;background:transparent;color:var(--muted);font-family:var(--font-head);font-weight:700;font-size:0.8rem;cursor:pointer;transition:all 0.18s;text-align:center;}
.math-tab.active{background:var(--accent);color:#fff;}

/* GRAPH */
#graphCanvas{width:100%;border-radius:10px;background:#0d1525;cursor:crosshair;display:block;}
.fn-row{display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;}
.fn-color{width:20px;height:20px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;}
.fn-input{flex:1;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:0.55rem 0.8rem;color:var(--text);font-family:monospace;font-size:0.9rem;outline:none;}
.fn-input:focus{border-color:var(--accent);}
.fn-remove{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:0.3rem;}
.fn-remove:hover{color:var(--red);}
.graph-controls{display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;margin-top:0.75rem;}
.coord-display{font-family:monospace;font-size:0.78rem;color:var(--muted);margin-left:auto;}

/* SOLVER */
.solve-output{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:1.2rem;min-height:80px;line-height:1.8;font-size:0.92rem;}
.solve-output .step{margin-bottom:0.6rem;padding-bottom:0.6rem;border-bottom:1px solid var(--border);}
.solve-output .step:last-child{border-bottom:none;margin-bottom:0;}
.step-num{font-family:var(--font-head);font-size:0.7rem;font-weight:700;text-transform:uppercase;color:var(--accent);margin-bottom:0.25rem;}

/* FORMULA LIBRARY */
.formula-cats{display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:1rem;}
.fcat{padding:0.3rem 0.8rem;border-radius:20px;border:1px solid var(--border);background:var(--surface2);font-size:0.78rem;cursor:pointer;transition:all 0.18s;font-family:var(--font-head);font-weight:600;}
.fcat:hover,.fcat.active{border-color:var(--accent);background:rgba(91,127,255,0.15);color:#a5b4fc;}
.formula-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.75rem;}
.formula-card{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:1rem;cursor:pointer;transition:border-color 0.18s;}
.formula-card:hover{border-color:var(--accent);}
.formula-name{font-family:var(--font-head);font-weight:700;font-size:0.82rem;margin-bottom:0.5rem;color:var(--muted);}
.formula-eq{font-size:1rem;text-align:center;padding:0.5rem 0;}

/* CALCULATOR */
.calc-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:0.4rem;}
.calc-btn{padding:0.9rem;border-radius:9px;border:none;font-family:var(--font-head);font-weight:700;font-size:0.95rem;cursor:pointer;transition:all 0.15s;}
.calc-btn:hover{opacity:0.85;transform:translateY(-1px);}
.calc-num{background:var(--surface2);color:var(--text);}
.calc-op{background:rgba(91,127,255,0.2);color:var(--accent);}
.calc-eq{background:var(--accent);color:#fff;grid-column:span 2;}
.calc-clr{background:rgba(248,113,113,0.2);color:var(--red);}
.calc-display{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:1rem 1.2rem;margin-bottom:0.75rem;text-align:right;}
.calc-expr{font-family:monospace;font-size:0.8rem;color:var(--muted);min-height:1.2em;word-break:break-all;}
.calc-result{font-family:var(--font-head);font-weight:800;font-size:2rem;color:var(--text);margin-top:0.2rem;}

/* LOADING */
.loading{display:inline-block;width:16px;height:16px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.7s linear infinite;vertical-align:middle;margin-right:0.5rem;}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<?php require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <h1 class="page-title">📐 Toán học</h1>

  <div class="math-tabs">
    <button class="math-tab active" onclick="showTab('graph')">📈 Vẽ đồ thị</button>
    <button class="math-tab" onclick="showTab('solver')">🧮 Giải toán AI</button>
    <button class="math-tab" onclick="showTab('formulas')">📚 Công thức</button>
    <button class="math-tab" onclick="showTab('calc')">🔢 Máy tính</button>
  </div>

  <!-- ===== TAB 1: GRAPH ===== -->
  <div id="tab-graph">
    <div class="card">
      <div class="card-header">
        <div class="card-title">📈 Vẽ đồ thị hàm số</div>
        <div class="coord-display" id="coordDisplay">x: —, y: —</div>
      </div>
      <div class="card-body">
        <div id="fnList"></div>
        <div style="display:flex;gap:0.5rem;margin-bottom:1rem;">
          <button class="btn btn-ghost btn-sm" onclick="addFunction()">+ Thêm hàm</button>
          <button class="btn btn-primary btn-sm" onclick="drawAll()">▶ Vẽ</button>
        </div>
        <canvas id="graphCanvas" height="420"></canvas>
        <div class="graph-controls">
          <button class="btn btn-ghost btn-sm" onclick="zoom(1.3)">🔍+</button>
          <button class="btn btn-ghost btn-sm" onclick="zoom(0.77)">🔍−</button>
          <button class="btn btn-ghost btn-sm" onclick="resetView()">⌂ Reset</button>
          <span style="font-family:var(--font-head);font-size:0.75rem;color:var(--muted);">
            Kéo để di chuyển · Scroll để zoom
          </span>
          <div style="margin-left:auto;font-family:monospace;font-size:0.75rem;color:var(--muted)" id="rangeInfo"></div>
        </div>
        <div style="margin-top:0.75rem;">
          <div style="font-family:var(--font-head);font-size:0.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:0.4rem;">Ví dụ nhanh</div>
          <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
            <span class="fcat" onclick="quickPlot('x^2 - 3*x + 2')">x²-3x+2</span>
            <span class="fcat" onclick="quickPlot('sin(x)')">sin(x)</span>
            <span class="fcat" onclick="quickPlot('cos(x)')">cos(x)</span>
            <span class="fcat" onclick="quickPlot('tan(x)')">tan(x)</span>
            <span class="fcat" onclick="quickPlot('sqrt(x)')">√x</span>
            <span class="fcat" onclick="quickPlot('abs(x)')">|x|</span>
            <span class="fcat" onclick="quickPlot('log(x)')">log(x)</span>
            <span class="fcat" onclick="quickPlot('exp(x)')">eˣ</span>
            <span class="fcat" onclick="quickPlot('x^3 - x')">x³-x</span>
            <span class="fcat" onclick="quickPlot('1/x')">1/x</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== TAB 2: SOLVER ===== -->
  <div id="tab-solver" style="display:none">
    <div class="card">
      <div class="card-header"><div class="card-title">🧮 Giải toán từng bước</div></div>
      <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.75rem;">
          <span class="fcat active" id="stype-pt" onclick="setSolveType('pt')">Phương trình</span>
          <span class="fcat" id="stype-bpt" onclick="setSolveType('bpt')">Bất phương trình</span>
          <span class="fcat" id="stype-dao" onclick="setSolveType('dao')">Đạo hàm</span>
          <span class="fcat" id="stype-tich" onclick="setSolveType('tich')">Tích phân</span>
          <span class="fcat" id="stype-luong" onclick="setSolveType('luong')">Lượng giác</span>
          <span class="fcat" id="stype-free" onclick="setSolveType('free')">Bài toán tự do</span>
        </div>
        <div id="solverHint" style="font-size:0.82rem;color:var(--muted);margin-bottom:0.6rem;">Nhập phương trình cần giải, VD: 2x² - 5x + 3 = 0</div>
        <div class="row" style="margin-bottom:1rem;">
          <input type="text" id="solveInput" class="form-input grow" placeholder="Nhập bài toán..." onkeydown="if(event.key==='Enter')solveMath()">
          <button class="btn btn-primary" onclick="solveMath()" id="solveBtn">✨ Giải</button>
        </div>
        <div id="solveOutput" class="solve-output" style="display:none"></div>

        <div style="margin-top:1rem;">
          <div style="font-family:var(--font-head);font-size:0.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:0.4rem;">Ví dụ</div>
          <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
            <span class="fcat" onclick="setExample('2x² - 5x + 3 = 0')">2x²-5x+3=0</span>
            <span class="fcat" onclick="setExample('x³ - 6x² + 11x - 6 = 0')">x³-6x²+11x-6=0</span>
            <span class="fcat" onclick="setExample('sin(x) = √3/2')">sin(x)=√3/2</span>
            <span class="fcat" onclick="setExample('f(x) = x³ - 3x + 2, tìm đạo hàm')">f(x)=x³-3x+2</span>
            <span class="fcat" onclick="setExample('∫(x² + 2x) dx')">∫(x²+2x)dx</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== TAB 3: FORMULAS ===== -->
  <div id="tab-formulas" style="display:none">
    <div class="card">
      <div class="card-header"><div class="card-title">📚 Thư viện công thức</div></div>
      <div class="card-body">
        <div class="formula-cats" id="formulaCats"></div>
        <div class="formula-grid" id="formulaGrid"></div>
      </div>
    </div>
  </div>

  <!-- ===== TAB 4: CALCULATOR ===== -->
  <div id="tab-calc" style="display:none">
    <div style="max-width:340px;margin:0 auto;">
      <div class="card">
        <div class="card-body">
          <div class="calc-display">
            <div class="calc-expr" id="calcExpr"></div>
            <div class="calc-result" id="calcResult">0</div>
          </div>
          <div class="calc-grid">
            <button class="calc-btn calc-clr" onclick="calcClear()" style="grid-column:span 2">AC</button>
            <button class="calc-btn calc-op" onclick="calcDel()">⌫</button>
            <button class="calc-btn calc-op" onclick="calcInput('/')">÷</button>

            <button class="calc-btn calc-num" onclick="calcInput('7')">7</button>
            <button class="calc-btn calc-num" onclick="calcInput('8')">8</button>
            <button class="calc-btn calc-num" onclick="calcInput('9')">9</button>
            <button class="calc-btn calc-op" onclick="calcInput('*')">×</button>

            <button class="calc-btn calc-num" onclick="calcInput('4')">4</button>
            <button class="calc-btn calc-num" onclick="calcInput('5')">5</button>
            <button class="calc-btn calc-num" onclick="calcInput('6')">6</button>
            <button class="calc-btn calc-op" onclick="calcInput('-')">−</button>

            <button class="calc-btn calc-num" onclick="calcInput('1')">1</button>
            <button class="calc-btn calc-num" onclick="calcInput('2')">2</button>
            <button class="calc-btn calc-num" onclick="calcInput('3')">3</button>
            <button class="calc-btn calc-op" onclick="calcInput('+')">+</button>

            <button class="calc-btn calc-num" onclick="calcInput('0')" style="grid-column:span 2">0</button>
            <button class="calc-btn calc-num" onclick="calcInput('.')">.</button>
            <button class="calc-btn calc-eq" onclick="calcEval()" style="grid-column:span 1">=</button>
          </div>
          <!-- Scientific row -->
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.4rem;margin-top:0.4rem;">
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('sin(')">sin</button>
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('cos(')">cos</button>
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('tan(')">tan</button>
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('sqrt(')">√</button>
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('^')">xⁿ</button>
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('log(')">log</button>
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('pi')">π</button>
            <button class="calc-btn calc-op" style="font-size:0.78rem;" onclick="calcInput('(')">( )</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ===================== TABS =====================
function showTab(t){
  ['graph','solver','formulas','calc'].forEach(x=>{
    document.getElementById('tab-'+x).style.display=x===t?'block':'none';
  });
  document.querySelectorAll('.math-tab').forEach((b,i)=>{
    b.classList.toggle('active',['graph','solver','formulas','calc'][i]===t);
  });
  if(t==='graph') setTimeout(()=>{resizeCanvas();drawAll();},50);
  if(t==='formulas') renderFormulas('all');
}

// ===================== GRAPH =====================
const COLORS=['#5b7fff','#34d399','#f5c842','#f87171','#a78bfa','#fb923c'];
let fns=[{expr:'x^2 - 3*x + 2',color:COLORS[0]}];
let view={xMin:-10,xMax:10,yMin:-8,yMax:8};
let dragging=false,lastMouse={x:0,y:0};

function resizeCanvas(){
  const c=document.getElementById('graphCanvas');
  c.width=c.offsetWidth;
  c.height=420;
}

function addFunction(expr='',colorIdx=null){
  const idx=fns.length;
  fns.push({expr:expr||'',color:COLORS[idx%COLORS.length]});
  renderFnList();
}

function renderFnList(){
  const el=document.getElementById('fnList');
  el.innerHTML=fns.map((f,i)=>`
    <div class="fn-row">
      <div class="fn-color" style="background:${f.color}"></div>
      <span style="font-family:var(--font-head);font-size:0.8rem;color:var(--muted);min-width:30px;">y =</span>
      <input class="fn-input" value="${f.expr}" oninput="fns[${i}].expr=this.value" onkeydown="if(event.key==='Enter')drawAll()" placeholder="Nhập hàm số, vd: x^2 + 1">
      ${fns.length>1?`<button class="fn-remove" onclick="removeFn(${i})">✕</button>`:''}
    </div>`).join('');
}

function removeFn(i){fns.splice(i,1);renderFnList();drawAll();}

function toCanvas(x,y,w,h){
  return{
    cx:(x-view.xMin)/(view.xMax-view.xMin)*w,
    cy:h-(y-view.yMin)/(view.yMax-view.yMin)*h
  };
}
function toWorld(cx,cy,w,h){
  return{
    x:view.xMin+cx/w*(view.xMax-view.xMin),
    y:view.yMin+(h-cy)/h*(view.yMax-view.yMin)
  };
}

function drawAll(){
  const canvas=document.getElementById('graphCanvas');
  resizeCanvas();
  const c=canvas.getContext('2d');
  const W=canvas.width,H=canvas.height;
  c.clearRect(0,0,W,H);

  // Grid
  c.strokeStyle='rgba(255,255,255,0.05)';c.lineWidth=1;
  const xStep=niceStep((view.xMax-view.xMin)/10);
  const yStep=niceStep((view.yMax-view.yMin)/8);
  for(let x=Math.ceil(view.xMin/xStep)*xStep;x<=view.xMax;x+=xStep){
    const {cx}=toCanvas(x,0,W,H);
    c.beginPath();c.moveTo(cx,0);c.lineTo(cx,H);c.stroke();
  }
  for(let y=Math.ceil(view.yMin/yStep)*yStep;y<=view.yMax;y+=yStep){
    const {cy}=toCanvas(0,y,W,H);
    c.beginPath();c.moveTo(0,cy);c.lineTo(W,cy);c.stroke();
  }

  // Axes
  const orig=toCanvas(0,0,W,H);
  c.strokeStyle='rgba(255,255,255,0.25)';c.lineWidth=1.5;
  c.beginPath();c.moveTo(0,orig.cy);c.lineTo(W,orig.cy);c.stroke();
  c.beginPath();c.moveTo(orig.cx,0);c.lineTo(orig.cx,H);c.stroke();

  // Axis labels
  c.fillStyle='rgba(255,255,255,0.3)';c.font='10px monospace';c.textAlign='center';
  for(let x=Math.ceil(view.xMin/xStep)*xStep;x<=view.xMax;x+=xStep){
    if(Math.abs(x)<1e-10) continue;
    const {cx,cy}=toCanvas(x,0,W,H);
    const ly=Math.min(Math.max(cy,12),H-4);
    c.fillText(+x.toFixed(2),cx,ly+12);
  }
  c.textAlign='right';
  for(let y=Math.ceil(view.yMin/yStep)*yStep;y<=view.yMax;y+=yStep){
    if(Math.abs(y)<1e-10) continue;
    const {cx,cy}=toCanvas(0,y,W,H);
    const lx=Math.min(Math.max(orig.cx,12),W-4);
    c.fillText(+y.toFixed(2),lx-4,cy+4);
  }

  // Plot functions
  fns.forEach(fn=>{
    if(!fn.expr.trim()) return;
    try{
      const compiled=math.compile(fn.expr);
      c.strokeStyle=fn.color;c.lineWidth=2.5;c.beginPath();
      let started=false;
      for(let px=0;px<W;px++){
        const wx=view.xMin+px/W*(view.xMax-view.xMin);
        let wy;
        try{wy=compiled.evaluate({x:wx});}catch{started=false;continue;}
        if(!isFinite(wy)||isNaN(wy)||Math.abs(wy)>1e6){started=false;continue;}
        const {cy}=toCanvas(wx,wy,W,H);
        if(!started){c.moveTo(px,cy);started=true;}else{c.lineTo(px,cy);}
      }
      c.stroke();
    }catch(e){}
  });

  // Range info
  document.getElementById('rangeInfo').textContent=
    `x: [${+view.xMin.toFixed(2)}, ${+view.xMax.toFixed(2)}]  y: [${+view.yMin.toFixed(2)}, ${+view.yMax.toFixed(2)}]`;
}

function niceStep(rough){
  const pow=Math.pow(10,Math.floor(Math.log10(rough)));
  const frac=rough/pow;
  return (frac<1.5?1:frac<3.5?2:frac<7.5?5:10)*pow;
}

function zoom(factor){
  const cx=(view.xMin+view.xMax)/2,cy=(view.yMin+view.yMax)/2;
  const xr=(view.xMax-view.xMin)/2*factor,yr=(view.yMax-view.yMin)/2*factor;
  view={xMin:cx-xr,xMax:cx+xr,yMin:cy-yr,yMax:cy+yr};
  drawAll();
}
function resetView(){view={xMin:-10,xMax:10,yMin:-8,yMax:8};drawAll();}
function quickPlot(expr){fns=[{expr,color:COLORS[0]}];renderFnList();drawAll();}

// Drag & scroll
const cvs=document.getElementById('graphCanvas');
cvs.addEventListener('mousedown',e=>{dragging=true;lastMouse={x:e.offsetX,y:e.offsetY};});
cvs.addEventListener('mousemove',e=>{
  if(dragging){
    const W=cvs.width,H=cvs.height;
    const dx=(e.offsetX-lastMouse.x)/W*(view.xMax-view.xMin);
    const dy=(e.offsetY-lastMouse.y)/H*(view.yMax-view.yMin);
    view.xMin-=dx;view.xMax-=dx;view.yMin+=dy;view.yMax+=dy;
    lastMouse={x:e.offsetX,y:e.offsetY};drawAll();
  }
  const W=cvs.width,H=cvs.height;
  const {x,y}=toWorld(e.offsetX,e.offsetY,W,H);
  document.getElementById('coordDisplay').textContent=`x: ${x.toFixed(3)}, y: ${y.toFixed(3)}`;
});
cvs.addEventListener('mouseup',()=>dragging=false);
cvs.addEventListener('mouseleave',()=>dragging=false);
cvs.addEventListener('wheel',e=>{
  e.preventDefault();
  const f=e.deltaY>0?1.15:0.87;
  zoom(f);
},{passive:false});

// Init graph
renderFnList();
setTimeout(()=>{resizeCanvas();drawAll();},100);

// ===================== SOLVER =====================
let solveType='pt';
const solveHints={
  pt:'Nhập phương trình cần giải, VD: 2x² - 5x + 3 = 0',
  bpt:'Nhập bất phương trình, VD: x² - 5x + 6 > 0',
  dao:'Nhập hàm số, VD: f(x) = x³ - 3x² + 2x',
  tich:'Nhập biểu thức tích phân, VD: x² + 3x - 1',
  luong:'Nhập phương trình lượng giác, VD: sin(2x) = cos(x)',
  free:'Nhập bất kỳ bài toán nào bằng tiếng Việt'
};

function setSolveType(t){
  solveType=t;
  document.querySelectorAll('[id^=stype-]').forEach(el=>el.classList.remove('active'));
  document.getElementById('stype-'+t).classList.add('active');
  document.getElementById('solverHint').textContent=solveHints[t];
  document.getElementById('solveInput').placeholder=solveHints[t];
}
function setExample(v){document.getElementById('solveInput').value=v;}

async function solveMath(){
  const input=document.getElementById('solveInput').value.trim();
  if(!input) return alert('Nhập bài toán đi!');
  const btn=document.getElementById('solveBtn');
  btn.disabled=true;btn.innerHTML='<span class="loading"></span>Đang giải...';
  const out=document.getElementById('solveOutput');
  out.style.display='block';
  out.innerHTML='<span class="loading"></span> AI đang giải từng bước...';
  try{
    const res=await fetch('ai_api.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({type:'math_solve',problem:input,solveType})});
    if(!res.ok){
      if(res.status===401){out.innerHTML='<span style="color:var(--red)">⚠️ Bạn chưa đăng nhập. Vui lòng đăng nhập lại.</span>';return;}
      throw new Error('HTTP '+res.status);
    }
    const data=await res.json();
    if(data.error){out.innerHTML='<span style="color:var(--red)">⚠️ Lỗi: '+data.error+'</span>';return;}
    out.innerHTML=data.result||'Không giải được!';
    renderMathInElement(out,{delimiters:[
      {left:'$$',right:'$$',display:true},
      {left:'$',right:'$',display:false},
      {left:'\\(',right:'\\)',display:false}
    ],throwOnError:false});
  }catch(e){
    out.innerHTML='<span style="color:var(--red)">⚠️ Lỗi kết nối AI! Kiểm tra lại kết nối mạng hoặc thử lại sau giây lát.<br><small style="opacity:0.6">Chi tiết: '+e.message+'</small></span>';
  }
  btn.disabled=false;btn.textContent='✨ Giải';
}

// ===================== FORMULAS =====================
const formulaData={
  'Lượng giác':[
    {name:'Hằng đẳng thức cơ bản',tex:'\\sin^2 x + \\cos^2 x = 1'},
    {name:'1 + tan²x',tex:'1 + \\tan^2 x = \\dfrac{1}{\\cos^2 x}'},
    {name:'1 + cot²x',tex:'1 + \\cot^2 x = \\dfrac{1}{\\sin^2 x}'},
    {name:'tan x',tex:'\\tan x = \\dfrac{\\sin x}{\\cos x},\\quad \\cot x = \\dfrac{\\cos x}{\\sin x}'},
    {name:'sin 2x',tex:'\\sin 2x = 2\\sin x\\cos x'},
    {name:'cos 2x (3 dạng)',tex:'\\cos 2x = \\cos^2x - \\sin^2x = 2\\cos^2x-1 = 1-2\\sin^2x'},
    {name:'tan 2x',tex:'\\tan 2x = \\dfrac{2\\tan x}{1 - \\tan^2 x}'},
    {name:'sin 3x',tex:'\\sin 3x = 3\\sin x - 4\\sin^3 x'},
    {name:'cos 3x',tex:'\\cos 3x = 4\\cos^3 x - 3\\cos x'},
    {name:'Cộng góc sin',tex:'\\sin(a\\pm b) = \\sin a\\cos b \\pm \\cos a\\sin b'},
    {name:'Cộng góc cos',tex:'\\cos(a\\pm b) = \\cos a\\cos b \\mp \\sin a\\sin b'},
    {name:'Cộng góc tan',tex:'\\tan(a\\pm b) = \\dfrac{\\tan a \\pm \\tan b}{1 \\mp \\tan a\\tan b}'},
    {name:'Tích → Tổng sin·cos',tex:'2\\sin a\\cos b = \\sin(a+b)+\\sin(a-b)'},
    {name:'Tích → Tổng cos·cos',tex:'2\\cos a\\cos b = \\cos(a-b)+\\cos(a+b)'},
    {name:'Tích → Tổng sin·sin',tex:'2\\sin a\\sin b = \\cos(a-b)-\\cos(a+b)'},
    {name:'Tổng → Tích sin',tex:'\\sin a + \\sin b = 2\\sin\\dfrac{a+b}{2}\\cos\\dfrac{a-b}{2}'},
    {name:'Tổng → Tích cos',tex:'\\cos a + \\cos b = 2\\cos\\dfrac{a+b}{2}\\cos\\dfrac{a-b}{2}'},
    {name:'Hạ bậc sin²',tex:'\\sin^2 x = \\dfrac{1-\\cos 2x}{2}'},
    {name:'Hạ bậc cos²',tex:'\\cos^2 x = \\dfrac{1+\\cos 2x}{2}'},
    {name:'Giá trị đặc biệt',tex:'\\sin 30°=\\tfrac{1}{2},\\ \\sin 45°=\\tfrac{\\sqrt{2}}{2},\\ \\sin 60°=\\tfrac{\\sqrt{3}}{2}'},
  ],
  'Đạo hàm':[
    {name:"(c)' = 0",tex:"(c)' = 0 \\quad (c \\text{ hằng số})"},
    {name:"(xⁿ)'",tex:"(x^n)' = nx^{n-1}"},
    {name:"(√x)'",tex:"(\\sqrt{x})' = \\dfrac{1}{2\\sqrt{x}}"},
    {name:"(sin x)'",tex:"(\\sin x)' = \\cos x"},
    {name:"(cos x)'",tex:"(\\cos x)' = -\\sin x"},
    {name:"(tan x)'",tex:"(\\tan x)' = \\dfrac{1}{\\cos^2 x}"},
    {name:"(cot x)'",tex:"(\\cot x)' = -\\dfrac{1}{\\sin^2 x}"},
    {name:"(eˣ)'",tex:"(e^x)' = e^x"},
    {name:"(aˣ)'",tex:"(a^x)' = a^x \\ln a"},
    {name:"(ln x)'",tex:"(\\ln x)' = \\dfrac{1}{x}"},
    {name:"(log_a x)'",tex:"(\\log_a x)' = \\dfrac{1}{x\\ln a}"},
    {name:"(u + v)'",tex:"(u \\pm v)' = u' \\pm v'"},
    {name:"(u·v)'",tex:"(uv)' = u'v + uv'"},
    {name:"(u/v)'",tex:"\\left(\\dfrac{u}{v}\\right)' = \\dfrac{u'v - uv'}{v^2}"},
    {name:'Hàm hợp (chain rule)',tex:"[f(g(x))]' = f'(g(x))\\cdot g'(x)"},
    {name:'Đạo hàm cấp 2',tex:"f''(x) = (f'(x))'"},
    {name:'Tiếp tuyến tại x₀',tex:"y - f(x_0) = f'(x_0)(x - x_0)"},
  ],
  'Tích phân':[
    {name:'∫c dx',tex:'\\int c\\,dx = cx + C'},
    {name:'∫xⁿdx',tex:'\\int x^n\\,dx = \\dfrac{x^{n+1}}{n+1} + C \\quad (n\\neq -1)'},
    {name:'∫(1/x)dx',tex:'\\int \\dfrac{1}{x}\\,dx = \\ln|x| + C'},
    {name:'∫eˣdx',tex:'\\int e^x\\,dx = e^x + C'},
    {name:'∫aˣdx',tex:'\\int a^x\\,dx = \\dfrac{a^x}{\\ln a} + C'},
    {name:'∫sin x dx',tex:'\\int \\sin x\\,dx = -\\cos x + C'},
    {name:'∫cos x dx',tex:'\\int \\cos x\\,dx = \\sin x + C'},
    {name:'∫tan x dx',tex:'\\int \\tan x\\,dx = -\\ln|\\cos x| + C'},
    {name:'∫(1/cos²x)dx',tex:'\\int \\dfrac{dx}{\\cos^2 x} = \\tan x + C'},
    {name:'∫(1/sin²x)dx',tex:'\\int \\dfrac{dx}{\\sin^2 x} = -\\cot x + C'},
    {name:'∫√x dx',tex:'\\int \\sqrt{x}\\,dx = \\dfrac{2}{3}x^{3/2} + C'},
    {name:'∫1/(x²+a²)dx',tex:'\\int \\dfrac{dx}{x^2+a^2} = \\dfrac{1}{a}\\arctan\\dfrac{x}{a} + C'},
    {name:'Tích phân từng phần',tex:'\\int u\\,dv = uv - \\int v\\,du'},
    {name:'Đổi biến',tex:'\\int f(g(x))g\'(x)\\,dx = \\int f(u)\\,du'},
    {name:'Newton-Leibniz',tex:'\\int_a^b f(x)\\,dx = F(b) - F(a)'},
    {name:'Diện tích hình phẳng',tex:'S = \\int_a^b |f(x) - g(x)|\\,dx'},
    {name:'Thể tích vật thể xoay',tex:'V = \\pi\\int_a^b [f(x)]^2\\,dx'},
  ],
  'Phương trình':[
    {name:'Bậc 1',tex:'ax + b = 0 \\Rightarrow x = -\\dfrac{b}{a}'},
    {name:'Bậc 2 — Công thức nghiệm',tex:'x = \\dfrac{-b \\pm \\sqrt{\\Delta}}{2a},\\quad \\Delta = b^2 - 4ac'},
    {name:'Bậc 2 — Delta\'',tex:"x = \\dfrac{-b' \\pm \\sqrt{\\Delta'}}{a},\\quad \\Delta' = b'^2 - ac"},
    {name:'Định lý Viète',tex:'x_1+x_2 = -\\dfrac{b}{a},\\quad x_1 x_2 = \\dfrac{c}{a}'},
    {name:'PT lượng giác: sin',tex:'\\sin x = m \\Leftrightarrow \\begin{cases}x = \\arcsin m + k2\\pi\\\\x = \\pi - \\arcsin m + k2\\pi\\end{cases}'},
    {name:'PT lượng giác: cos',tex:'\\cos x = m \\Leftrightarrow x = \\pm\\arccos m + k2\\pi'},
    {name:'PT lượng giác: tan',tex:'\\tan x = m \\Leftrightarrow x = \\arctan m + k\\pi'},
    {name:'PT mũ cơ bản',tex:'a^{f(x)} = a^{g(x)} \\Leftrightarrow f(x) = g(x)'},
    {name:'PT logarithm cơ bản',tex:'\\log_a f(x) = \\log_a g(x) \\Leftrightarrow f(x) = g(x)'},
    {name:'Hệ PT bậc nhất 2 ẩn',tex:'\\begin{cases}a_1x+b_1y=c_1\\\\a_2x+b_2y=c_2\\end{cases}'},
  ],
  'Hình học':[
    {name:'Diện tích tam giác',tex:'S = \\dfrac{1}{2}ah = \\dfrac{1}{2}ab\\sin C'},
    {name:'Công thức Heron',tex:'S = \\sqrt{p(p-a)(p-b)(p-c)},\\quad p=\\dfrac{a+b+c}{2}'},
    {name:'Định lý Cosine',tex:'a^2 = b^2 + c^2 - 2bc\\cos A'},
    {name:'Định lý Sine',tex:'\\dfrac{a}{\\sin A} = \\dfrac{b}{\\sin B} = \\dfrac{c}{\\sin C} = 2R'},
    {name:'Bán kính nội tiếp',tex:'r = \\dfrac{S}{p}'},
    {name:'Bán kính ngoại tiếp',tex:'R = \\dfrac{abc}{4S}'},
    {name:'Diện tích hình thang',tex:'S = \\dfrac{(a+b)h}{2}'},
    {name:'Diện tích hình tròn',tex:'S = \\pi R^2,\\quad C = 2\\pi R'},
    {name:'Thể tích lăng trụ',tex:'V = S_{đáy} \\cdot h'},
    {name:'Thể tích chóp',tex:'V = \\dfrac{1}{3}S_{đáy} \\cdot h'},
    {name:'Thể tích hình cầu',tex:'V = \\dfrac{4}{3}\\pi R^3'},
    {name:'Diện tích mặt cầu',tex:'S = 4\\pi R^2'},
    {name:'Thể tích hình trụ',tex:'V = \\pi R^2 h'},
    {name:'Thể tích hình nón',tex:'V = \\dfrac{1}{3}\\pi R^2 h'},
    {name:'Khoảng cách 2 điểm',tex:'d = \\sqrt{(x_2-x_1)^2+(y_2-y_1)^2}'},
    {name:'Khoảng cách điểm–đường',tex:'d(M,\\Delta) = \\dfrac{|ax_0+by_0+c|}{\\sqrt{a^2+b^2}}'},
  ],
  'Tổ hợp & XS':[
    {name:'Hoán vị',tex:'P_n = n!'},
    {name:'Chỉnh hợp',tex:'A_n^k = \\dfrac{n!}{(n-k)!}'},
    {name:'Tổ hợp',tex:'C_n^k = \\dfrac{n!}{k!(n-k)!}'},
    {name:'Tính chất tổ hợp',tex:'C_n^k = C_n^{n-k},\\quad C_n^0 = C_n^n = 1'},
    {name:'Công thức Pascal',tex:'C_{n+1}^k = C_n^k + C_n^{k-1}'},
    {name:'Nhị thức Newton',tex:'(a+b)^n = \\sum_{k=0}^n C_n^k a^{n-k}b^k'},
    {name:'Số hạng tổng quát',tex:'T_{k+1} = C_n^k a^{n-k}b^k'},
    {name:'Xác suất cổ điển',tex:'P(A) = \\dfrac{\\text{số kết quả thuận lợi}}{\\text{tổng số kết quả}}'},
    {name:'Quy tắc cộng XS',tex:'P(A\\cup B) = P(A)+P(B)-P(A\\cap B)'},
    {name:'XS độc lập',tex:'P(A\\cap B) = P(A)\\cdot P(B)'},
    {name:'XS có điều kiện',tex:'P(A|B) = \\dfrac{P(A\\cap B)}{P(B)}'},
    {name:'Kỳ vọng',tex:'E(X) = \\sum x_i p_i'},
  ],
  'Dãy số & Giới hạn':[
    {name:'Cấp số cộng — số hạng',tex:'u_n = u_1 + (n-1)d'},
    {name:'Cấp số cộng — tổng',tex:'S_n = \\dfrac{n(u_1+u_n)}{2} = nu_1 + \\dfrac{n(n-1)}{2}d'},
    {name:'Cấp số nhân — số hạng',tex:'u_n = u_1 \\cdot q^{n-1}'},
    {name:'Cấp số nhân — tổng',tex:'S_n = u_1\\dfrac{q^n-1}{q-1} \\quad (q\\neq 1)'},
    {name:'Tổng cấp số nhân lùi vô hạn',tex:'S = \\dfrac{u_1}{1-q} \\quad (|q|<1)'},
    {name:'Giới hạn cơ bản',tex:'\\lim_{x\\to 0}\\dfrac{\\sin x}{x} = 1'},
    {name:'Số e',tex:'e = \\lim_{n\\to\\infty}\\left(1+\\dfrac{1}{n}\\right)^n'},
    {name:'Quy tắc L\'Hôpital',tex:"\\lim\\dfrac{f}{g} = \\lim\\dfrac{f'}{g'} \\quad \\left(\\tfrac{0}{0}\\text{ hoặc }\\tfrac{\\infty}{\\infty}\\right)"},
  ],
  'Mũ & Logarithm':[
    {name:'Định nghĩa logarithm',tex:'\\log_a b = c \\Leftrightarrow a^c = b'},
    {name:'log tích',tex:'\\log_a(mn) = \\log_a m + \\log_a n'},
    {name:'log thương',tex:'\\log_a\\dfrac{m}{n} = \\log_a m - \\log_a n'},
    {name:'log lũy thừa',tex:'\\log_a m^k = k\\log_a m'},
    {name:'Đổi cơ số',tex:'\\log_a b = \\dfrac{\\log_c b}{\\log_c a}'},
    {name:'log₁₀ và ln',tex:'\\lg x = \\log_{10} x,\\quad \\ln x = \\log_e x'},
    {name:'Tính chất mũ',tex:'a^m \\cdot a^n = a^{m+n},\\quad \\dfrac{a^m}{a^n}=a^{m-n}'},
    {name:'Mũ phân số',tex:'a^{m/n} = \\sqrt[n]{a^m} = (\\sqrt[n]{a})^m'},
  ],
};

let activeCat='all';
function renderFormulas(cat){
  activeCat=cat;
  const cats=Object.keys(formulaData);
  document.getElementById('formulaCats').innerHTML=
    `<span class="fcat ${cat==='all'?'active':''}" onclick="renderFormulas('all')">📋 Tất cả</span>`+
    cats.map(c=>`<span class="fcat ${cat===c?'active':''}" onclick="renderFormulas('${c}')">${c}</span>`).join('');

  const items=cat==='all'?cats.flatMap(c=>formulaData[c]):formulaData[cat]||[];
  document.getElementById('formulaGrid').innerHTML=items.map(f=>`
    <div class="formula-card">
      <div class="formula-name">${f.name}</div>
      <div class="formula-eq">\\(${f.tex}\\)</div>
    </div>`).join('');
  renderMathInElement(document.getElementById('formulaGrid'),{
    delimiters:[{left:'\\(',right:'\\)',display:false},{left:'$$',right:'$$',display:true}],
    throwOnError:false
  });
}

// ===================== CALCULATOR =====================
let calcExpr='';
function calcInput(v){
  if(v==='(') calcExpr+=calcExpr.length&&/[\d)]$/.test(calcExpr)?'*(':' (';
  else calcExpr+=v;
  document.getElementById('calcExpr').textContent=calcExpr;
  try{
    const r=math.evaluate(calcExpr.replace(/\^/g,'**'));
    document.getElementById('calcResult').textContent=+r.toFixed(10);
  }catch{}
}
function calcClear(){calcExpr='';document.getElementById('calcExpr').textContent='';document.getElementById('calcResult').textContent='0';}
function calcDel(){calcExpr=calcExpr.slice(0,-1);document.getElementById('calcExpr').textContent=calcExpr;}
function calcEval(){
  try{
    const r=math.evaluate(calcExpr.replace(/\^/g,'**'));
    const res=+r.toFixed(10);
    document.getElementById('calcResult').textContent=res;
    calcExpr=String(res);
    document.getElementById('calcExpr').textContent='';
  }catch{document.getElementById('calcResult').textContent='Lỗi';}
}
</script>
</body>
</html>
