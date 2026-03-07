<?php
require_once __DIR__ . "/db.php"; requireLogin();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Toán học — MindSpark</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/mathjs/11.11.0/math.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/contrib/auto-render.min.js"></script>
<style>
/* ── TABS ── */
.math-tabs {
  display: flex; gap: 4px; margin-bottom: 20px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; padding: 4px;
}
.math-tab {
  flex: 1; padding: 8px 6px; border-radius: 9px; border: none;
  background: transparent; color: var(--muted);
  font-family: var(--font); font-weight: 700; font-size: 12px;
  cursor: pointer; transition: all 0.15s; text-align: center;
}
.math-tab.active { background: var(--accent); color: #fff; }

/* ── GRAPH LAYOUT ── */
.graph-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 0;
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  background: var(--surface);
  height: 580px;
}
@media(max-width:768px){
  .graph-layout { grid-template-columns: 1fr; height: auto; }
  .graph-sidebar { border-right: none; border-bottom: 1px solid var(--border); max-height: 300px; overflow-y: auto; }
  #graphCanvas { height: 320px !important; }
}

/* ── SIDEBAR ── */
.graph-sidebar {
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  background: var(--surface);
}
.sidebar-header {
  padding: 12px 14px; border-bottom: 1px solid var(--border);
  font-size: 12px; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: 0.5px;
  display: flex; align-items: center; justify-content: space-between;
}
.fn-list { flex: 1; overflow-y: auto; padding: 8px; }

.fn-row {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 8px; border-radius: 10px; margin-bottom: 4px;
  border: 1.5px solid var(--border); background: var(--surface2);
  transition: border-color 0.15s;
}
.fn-row:hover { border-color: var(--border2); }
.fn-row.active-fn { border-color: var(--accent); }

.fn-color-dot {
  width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; cursor: pointer;
  border: 2px solid rgba(255,255,255,0.2);
}
.fn-label {
  font-size: 11px; font-weight: 700; color: var(--muted);
  flex-shrink: 0; font-family: var(--mono);
}
.fn-input {
  flex: 1; background: transparent; border: none;
  color: var(--text); font-family: var(--mono); font-size: 13px;
  outline: none; min-width: 0;
}
.fn-input::placeholder { color: var(--muted); font-size: 12px; }
.fn-vis-btn {
  background: none; border: none; color: var(--muted);
  cursor: pointer; font-size: 13px; padding: 2px; flex-shrink: 0;
}
.fn-del-btn {
  background: none; border: none; color: var(--muted);
  cursor: pointer; font-size: 12px; padding: 2px; flex-shrink: 0;
}
.fn-del-btn:hover { color: var(--red); }

.sidebar-actions { padding: 8px; border-top: 1px solid var(--border); }

/* ── CANVAS AREA ── */
.canvas-wrap {
  position: relative; flex: 1; background: var(--bg);
  display: flex; flex-direction: column;
}
.canvas-toolbar {
  display: flex; align-items: center; gap: 4px;
  padding: 8px 10px; border-bottom: 1px solid var(--border);
  background: var(--surface); flex-wrap: wrap;
}
.tool-btn {
  width: 30px; height: 30px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface2);
  color: var(--muted); cursor: pointer; font-size: 14px;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; flex-shrink: 0;
}
.tool-btn:hover { color: var(--text); border-color: var(--border2); }
.tool-btn.active { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }
.tool-sep { width: 1px; height: 20px; background: var(--border); margin: 0 2px; }
.coord-badge {
  margin-left: auto; font-family: var(--mono); font-size: 11px;
  color: var(--muted); background: var(--surface2);
  border: 1px solid var(--border); border-radius: 6px;
  padding: 3px 8px; flex-shrink: 0;
}

canvas#graphCanvas {
  flex: 1; width: 100%; display: block; cursor: crosshair;
  touch-action: none;
}

/* ── CANVAS STATUS BAR ── */
.canvas-statusbar {
  display: flex; align-items: center; gap: 8px;
  padding: 5px 10px; border-top: 1px solid var(--border);
  background: var(--surface); font-size: 11px; color: var(--muted);
  font-family: var(--mono); flex-wrap: wrap;
}
.status-item { display: flex; align-items: center; gap: 4px; }

/* ── QUICK PRESETS ── */
.preset-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;
  padding: 8px; border-top: 1px solid var(--border);
}
.preset-btn {
  padding: 5px 4px; border-radius: 7px; border: 1px solid var(--border);
  background: var(--surface2); color: var(--text2);
  font-family: var(--mono); font-size: 11px; cursor: pointer;
  transition: all 0.15s; text-align: center; white-space: nowrap; overflow: hidden;
}
.preset-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

/* ── INTERSECTION POPUP ── */
.intersection-dot {
  position: absolute; width: 10px; height: 10px;
  border-radius: 50%; background: #fff; border: 2px solid var(--accent);
  transform: translate(-50%, -50%); pointer-events: none;
}

/* ── RANGE INPUTS ── */
.range-row {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 8px; border-top: 1px solid var(--border);
  font-size: 11px; color: var(--muted); font-family: var(--mono);
}
.range-input {
  width: 52px; padding: 3px 6px; border-radius: 5px;
  border: 1px solid var(--border); background: var(--surface2);
  color: var(--text); font-family: var(--mono); font-size: 11px;
  outline: none; text-align: center;
}
.range-input:focus { border-color: var(--accent); }

/* ── OTHER TABS ── */
.fcat {
  padding: 5px 12px; border-radius: 20px; border: 1.5px solid var(--border);
  background: var(--surface2); font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all 0.15s; color: var(--text2);
}
.fcat:hover, .fcat.active { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); }
.formula-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
.formula-card {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 12px; padding: 14px 16px; cursor: pointer;
  transition: border-color 0.15s;
}
.formula-card:hover { border-color: var(--accent); }
.formula-name { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 8px; }
.formula-eq { font-size: 1rem; text-align: center; padding: 4px 0; }

.solve-output {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 12px; padding: 1.2rem; min-height: 80px;
  line-height: 1.8; font-size: 14px;
}
.solve-output .step { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.solve-output .step:last-child { border-bottom: none; margin-bottom: 0; }
.step-num { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--accent); margin-bottom: 4px; }

.calc-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 6px; }
.calc-btn {
  padding: 14px; border-radius: 10px; border: none;
  font-family: var(--font); font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.15s;
}
.calc-btn:active { transform: scale(0.95); }
.calc-num { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
.calc-op  { background: var(--accent-soft); color: var(--accent); border: 1px solid rgba(79,110,247,0.2); }
.calc-eq  { background: var(--accent); color: #fff; }
.calc-clr { background: var(--red-soft); color: var(--red); border: 1px solid rgba(220,38,38,0.2); }
.calc-display {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 12px; padding: 16px; margin-bottom: 10px; text-align: right;
}
.calc-expr { font-family: var(--mono); font-size: 12px; color: var(--muted); min-height: 1.2em; word-break: break-all; }
.calc-result { font-family: var(--font); font-weight: 800; font-size: 2rem; color: var(--text); margin-top: 4px; }

.loading { display: inline-block; width: 14px; height: 14px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.7s linear infinite; vertical-align: middle; margin-right: 6px; }
@keyframes spin { to { transform: rotate(360deg); } }

.error-msg { color: var(--red); font-size: 11px; padding: 2px 8px; font-family: var(--mono); }
</style>
</head>
<body>
<?php require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page">
  <div class="page-header">
    <div class="page-eyebrow">Công cụ</div>
    <h1 class="page-title">Toán học</h1>
  </div>

  <div class="math-tabs">
    <button class="math-tab active" onclick="showTab('graph')">📈 Vẽ đồ thị</button>
    <button class="math-tab" onclick="showTab('solver')">🧮 Giải toán AI</button>
    <button class="math-tab" onclick="showTab('formulas')">📚 Công thức</button>
    <button class="math-tab" onclick="showTab('calc')">🔢 Máy tính</button>
  </div>

  <!-- ══════════ TAB: GRAPH ══════════ -->
  <div id="tab-graph">
    <div style="border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--surface);">

      <!-- Toolbar trên -->
      <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--border);background:var(--surface);flex-wrap:wrap;">
        <span style="font-size:13px;font-weight:700;color:var(--text);">📐 GeoGebra Graphing</span>
        <div style="margin-left:auto;display:flex;gap:6px;">
          <button class="btn btn-ghost btn-sm" onclick="ggbReset()">⌂ Reset</button>
          <button class="btn btn-ghost btn-sm" onclick="ggbFullscreen()">⛶ Toàn màn hình</button>
          <button class="btn btn-ghost btn-sm" onclick="ggbToggleTheme()" id="ggbThemeBtn">🌙 Dark</button>
        </div>
      </div>

      <!-- GeoGebra iframe -->
      <div style="position:relative;width:100%;padding-top:65%;" id="ggbWrap">
        <iframe
          id="ggbFrame"
          src="https://www.geogebra.org/graphing?lang=vi"
          style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;"
          allowfullscreen
          allow="fullscreen"
        ></iframe>
      </div>

      <!-- Quick launch buttons -->
      <div style="padding:12px 14px;border-top:1px solid var(--border);background:var(--surface2);">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:8px;">Mở nhanh công cụ khác</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <a href="https://www.geogebra.org/graphing?lang=vi" target="_blank" class="btn btn-ghost btn-sm">📈 Đồ thị</a>
          <a href="https://www.geogebra.org/geometry?lang=vi" target="_blank" class="btn btn-ghost btn-sm">📐 Hình học</a>
          <a href="https://www.geogebra.org/3d?lang=vi" target="_blank" class="btn btn-ghost btn-sm">🧊 3D</a>
          <a href="https://www.geogebra.org/cas?lang=vi" target="_blank" class="btn btn-ghost btn-sm">🔣 CAS</a>
          <a href="https://www.geogebra.org/spreadsheet?lang=vi" target="_blank" class="btn btn-ghost btn-sm">📊 Bảng tính</a>
          <a href="https://www.geogebra.org/probability?lang=vi" target="_blank" class="btn btn-ghost btn-sm">🎲 Xác suất</a>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ TAB: SOLVER ══════════ -->
  <div id="tab-solver" style="display:none">
    <div class="card">
      <div class="card-header"><div class="card-title">🧮 Giải toán từng bước</div></div>
      <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
          <span class="fcat active" id="stype-pt" onclick="setSolveType('pt')">Phương trình</span>
          <span class="fcat" id="stype-bpt" onclick="setSolveType('bpt')">Bất phương trình</span>
          <span class="fcat" id="stype-dao" onclick="setSolveType('dao')">Đạo hàm</span>
          <span class="fcat" id="stype-tich" onclick="setSolveType('tich')">Tích phân</span>
          <span class="fcat" id="stype-luong" onclick="setSolveType('luong')">Lượng giác</span>
          <span class="fcat" id="stype-free" onclick="setSolveType('free')">Tự do</span>
        </div>
        <div id="solverHint" style="font-size:13px;color:var(--muted);margin-bottom:8px;">Nhập phương trình cần giải, VD: 2x² - 5x + 3 = 0</div>
        <div class="row" style="margin-bottom:12px;">
          <input type="text" id="solveInput" class="form-input grow" placeholder="Nhập bài toán..." onkeydown="if(event.key==='Enter')solveMath()">
          <button class="btn btn-primary" onclick="solveMath()" id="solveBtn">✨ Giải</button>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
          <span class="fcat" onclick="setExample('2x² - 5x + 3 = 0')">2x²-5x+3=0</span>
          <span class="fcat" onclick="setExample('x³ - 6x² + 11x - 6 = 0')">x³-6x²+11x=6</span>
          <span class="fcat" onclick="setExample('sin(x) = √3/2')">sin(x)=√3/2</span>
          <span class="fcat" onclick="setExample('f(x) = x³ - 3x + 2, tìm đạo hàm')">Đạo hàm x³-3x+2</span>
          <span class="fcat" onclick="setExample('∫(x² + 2x) dx')">∫(x²+2x)dx</span>
        </div>
        <div id="solveOutput" class="solve-output" style="display:none"></div>
      </div>
    </div>
  </div>

  <!-- ══════════ TAB: FORMULAS ══════════ -->
  <div id="tab-formulas" style="display:none">
    <div class="card">
      <div class="card-header"><div class="card-title">📚 Thư viện công thức</div></div>
      <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;" id="formulaCats"></div>
        <div class="formula-grid" id="formulaGrid"></div>
      </div>
    </div>
  </div>

  <!-- ══════════ TAB: CALCULATOR ══════════ -->
  <div id="tab-calc" style="display:none">
    <div style="max-width:360px;margin:0 auto;">
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
            <button class="calc-btn calc-eq" onclick="calcEval()">=</button>
          </div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:6px;">
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('sin(')">sin</button>
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('cos(')">cos</button>
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('tan(')">tan</button>
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('sqrt(')">√</button>
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('^')">xⁿ</button>
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('log(')">log</button>
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('pi')">π</button>
            <button class="calc-btn calc-op" style="font-size:12px;" onclick="calcInput('(')">( )</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════════════
//  TABS
// ══════════════════════════════════════
function showTab(t) {
  ['graph','solver','formulas','calc'].forEach(x => {
    document.getElementById('tab-'+x).style.display = x===t ? 'block' : 'none';
  });
  document.querySelectorAll('.math-tab').forEach((b,i) => {
    b.classList.toggle('active', ['graph','solver','formulas','calc'][i] === t);
  });
  if (t === 'graph') setTimeout(() => { resizeCanvas(); drawAll(); }, 50);
  if (t === 'formulas') renderFormulas('all');
}

// ══════════════════════════════════════
//  GRAPH ENGINE
// ══════════════════════════════════════
const COLORS = ['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
let fns = [{ expr: 'x^2 - 3*x + 2', color: COLORS[0], visible: true, id: 0 }];
let fnCounter = 1;
let view = { xMin: -10, xMax: 10, yMin: -8, yMax: 8 };
let showGrid = true, showAxes = true, showDots = true, showTangent = false;
let dragging = false, lastMouse = { x: 0, y: 0 };
let hoverX = null, tangentX = null;
let activeTool = 'move';
let touchDist = null;

const canvas = document.getElementById('graphCanvas');
const ctx = canvas.getContext('2d');

function resizeCanvas() {
  const wrap = canvas.parentElement;
  canvas.width = wrap.clientWidth;
  canvas.height = wrap.clientHeight - 40 - 30; // toolbar + statusbar
}

// ── Coordinate transforms ──
function toCanvas(wx, wy) {
  const W = canvas.width, H = canvas.height;
  return {
    cx: (wx - view.xMin) / (view.xMax - view.xMin) * W,
    cy: H - (wy - view.yMin) / (view.yMax - view.yMin) * H
  };
}
function toWorld(cx, cy) {
  const W = canvas.width, H = canvas.height;
  return {
    wx: view.xMin + cx / W * (view.xMax - view.xMin),
    wy: view.yMin + (H - cy) / H * (view.yMax - view.yMin)
  };
}

// ── Function list ──
function addFunction(expr = '', color = null) {
  const id = fnCounter++;
  fns.push({ expr, color: color || COLORS[fns.length % COLORS.length], visible: true, id });
  renderFnList();
  // Focus new input
  setTimeout(() => {
    const inputs = document.querySelectorAll('.fn-input');
    if (inputs.length) inputs[inputs.length - 1].focus();
  }, 50);
}

function removeFn(id) {
  fns = fns.filter(f => f.id !== id);
  if (!fns.length) addFunction('');
  renderFnList(); drawAll();
}

function toggleVisible(id) {
  const f = fns.find(f => f.id === id);
  if (f) { f.visible = !f.visible; renderFnList(); drawAll(); }
}

function renderFnList() {
  document.getElementById('fnList').innerHTML = fns.map((f, i) => `
    <div class="fn-row ${f.visible ? '' : 'opacity-50'}" id="fnrow${f.id}">
      <div class="fn-color-dot" style="background:${f.color};opacity:${f.visible?1:0.3}"
        onclick="pickColor(${f.id})"></div>
      <span class="fn-label">f${i+1}=</span>
      <input class="fn-input" value="${f.expr}"
        oninput="fns[${i}].expr=this.value"
        onkeydown="if(event.key==='Enter')drawAll()"
        placeholder="vd: sin(x)+x/2">
      <button class="fn-vis-btn" onclick="toggleVisible(${f.id})" title="${f.visible?'Ẩn':'Hiện'}">
        ${f.visible ? '👁' : '🙈'}
      </button>
      ${fns.length > 1 ? `<button class="fn-del-btn" onclick="removeFn(${f.id})">✕</button>` : ''}
    </div>
    <div class="error-msg" id="err${f.id}"></div>
  `).join('');
}

function pickColor(id) {
  const colors = COLORS;
  const f = fns.find(f => f.id === id);
  if (!f) return;
  const idx = colors.indexOf(f.color);
  f.color = colors[(idx + 1) % colors.length];
  renderFnList(); drawAll();
}

// ── Drawing ──
function niceStep(rough) {
  const pow = Math.pow(10, Math.floor(Math.log10(rough)));
  const frac = rough / pow;
  return (frac < 1.5 ? 1 : frac < 3.5 ? 2 : frac < 7.5 ? 5 : 10) * pow;
}

function drawAll() {
  resizeCanvas();
  const W = canvas.width, H = canvas.height;
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const bg = isDark ? '#0d0d12' : '#fafafa';
  const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';
  const gridMajorColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.12)';
  const axisColor = isDark ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.4)';
  const labelColor = isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.4)';

  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  const xStep = niceStep((view.xMax - view.xMin) / 10);
  const yStep = niceStep((view.yMax - view.yMin) / 8);

  // Grid
  if (showGrid) {
    ctx.lineWidth = 1;
    for (let x = Math.ceil(view.xMin / xStep) * xStep; x <= view.xMax + xStep; x += xStep) {
      const { cx } = toCanvas(x, 0);
      ctx.strokeStyle = Math.abs(x) < 1e-9 ? axisColor : gridColor;
      ctx.beginPath(); ctx.moveTo(cx, 0); ctx.lineTo(cx, H); ctx.stroke();
    }
    for (let y = Math.ceil(view.yMin / yStep) * yStep; y <= view.yMax + yStep; y += yStep) {
      const { cy } = toCanvas(0, y);
      ctx.strokeStyle = Math.abs(y) < 1e-9 ? axisColor : gridColor;
      ctx.beginPath(); ctx.moveTo(0, cy); ctx.lineTo(W, cy); ctx.stroke();
    }
  }

  // Axes
  if (showAxes) {
    const orig = toCanvas(0, 0);
    ctx.strokeStyle = axisColor; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.moveTo(0, orig.cy); ctx.lineTo(W, orig.cy); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(orig.cx, 0); ctx.lineTo(orig.cx, H); ctx.stroke();

    // Arrows
    ctx.fillStyle = axisColor;
    // X arrow
    ctx.beginPath(); ctx.moveTo(W - 8, orig.cy - 4); ctx.lineTo(W, orig.cy); ctx.lineTo(W - 8, orig.cy + 4); ctx.fill();
    // Y arrow
    ctx.beginPath(); ctx.moveTo(orig.cx - 4, 8); ctx.lineTo(orig.cx, 0); ctx.lineTo(orig.cx + 4, 8); ctx.fill();

    // Axis labels
    ctx.fillStyle = labelColor; ctx.font = '11px monospace'; ctx.textAlign = 'center';
    for (let x = Math.ceil(view.xMin / xStep) * xStep; x <= view.xMax; x += xStep) {
      if (Math.abs(x) < 1e-9) continue;
      const { cx, cy } = toCanvas(x, 0);
      const ly = Math.min(Math.max(cy + 14, 14), H - 4);
      ctx.fillText(+(x.toFixed(2)), cx, ly);
      // Tick
      ctx.strokeStyle = axisColor; ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(cx, cy - 3); ctx.lineTo(cx, cy + 3); ctx.stroke();
    }
    ctx.textAlign = 'right';
    for (let y = Math.ceil(view.yMin / yStep) * yStep; y <= view.yMax; y += yStep) {
      if (Math.abs(y) < 1e-9) continue;
      const { cx, cy } = toCanvas(0, y);
      const lx = Math.min(Math.max(orig.cx - 6, 30), W - 4);
      ctx.fillText(+(y.toFixed(2)), lx, cy + 4);
      ctx.strokeStyle = axisColor; ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(cx - 3, cy); ctx.lineTo(cx + 3, cy); ctx.stroke();
    }
    // x, y labels
    ctx.fillStyle = isDark ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)';
    ctx.font = 'bold 12px monospace'; ctx.textAlign = 'left';
    ctx.fillText('x', W - 16, orig.cy - 8);
    ctx.fillText('y', orig.cx + 6, 14);
  }

  // Plot functions
  const specialPoints = [];
  fns.forEach(fn => {
    if (!fn.expr.trim() || !fn.visible) return;
    document.getElementById('err' + fn.id) && (document.getElementById('err' + fn.id).textContent = '');
    try {
      const compiled = math.compile(fn.expr);
      const pts = [];
      const steps = W * 2;
      for (let px = 0; px <= steps; px++) {
        const wx = view.xMin + px / steps * (view.xMax - view.xMin);
        let wy;
        try { wy = compiled.evaluate({ x: wx }); } catch { pts.push(null); continue; }
        if (!isFinite(wy) || isNaN(wy) || Math.abs(wy) > 1e8) { pts.push(null); continue; }
        pts.push({ wx, wy });
      }

      // Draw with gradient glow
      ctx.save();
      ctx.shadowColor = fn.color;
      ctx.shadowBlur = 4;
      ctx.strokeStyle = fn.color; ctx.lineWidth = 2.5; ctx.lineJoin = 'round';
      ctx.beginPath();
      let started = false, prevPt = null;
      for (const pt of pts) {
        if (!pt) { started = false; prevPt = null; continue; }
        // Detect discontinuity (vertical asymptote)
        if (prevPt && Math.abs(pt.wy - prevPt.wy) > (view.yMax - view.yMin) * 0.5) {
          started = false; prevPt = null;
        }
        const { cx, cy } = toCanvas(pt.wx, pt.wy);
        if (!started) { ctx.moveTo(cx, cy); started = true; }
        else { ctx.lineTo(cx, cy); }
        prevPt = pt;
      }
      ctx.stroke();
      ctx.restore();

      // Find zeros & special points
      if (showDots) {
        let prevY = null, prevX = null;
        for (let px = 0; px <= W; px++) {
          const wx = view.xMin + px / W * (view.xMax - view.xMin);
          let wy;
          try { wy = compiled.evaluate({ x: wx }); } catch { prevY = null; continue; }
          if (!isFinite(wy)) { prevY = null; continue; }
          // Zero crossing
          if (prevY !== null && prevY * wy < 0) {
            const zx = (prevX + wx) / 2;
            specialPoints.push({ type: 'zero', x: +zx.toFixed(3), y: 0, color: fn.color, label: `(${+zx.toFixed(3)}, 0)` });
            const { cx, cy } = toCanvas(zx, 0);
            ctx.beginPath(); ctx.arc(cx, cy, 5, 0, Math.PI * 2);
            ctx.fillStyle = fn.color; ctx.fill();
            ctx.strokeStyle = isDark ? '#0d0d12' : '#fff'; ctx.lineWidth = 2; ctx.stroke();
          }
          prevY = wy; prevX = wx;
        }
        // Y-intercept
        try {
          const yi = compiled.evaluate({ x: 0 });
          if (isFinite(yi) && yi > view.yMin && yi < view.yMax) {
            specialPoints.push({ type: 'yint', x: 0, y: +yi.toFixed(3), color: fn.color, label: `(0, ${+yi.toFixed(3)})` });
            const { cx, cy } = toCanvas(0, yi);
            ctx.beginPath(); ctx.arc(cx, cy, 5, 0, Math.PI * 2);
            ctx.fillStyle = fn.color; ctx.fill();
            ctx.strokeStyle = isDark ? '#0d0d12' : '#fff'; ctx.lineWidth = 2; ctx.stroke();
          }
        } catch {}
      }

    } catch (e) {
      const errEl = document.getElementById('err' + fn.id);
      if (errEl) errEl.textContent = '⚠ Cú pháp sai';
    }
  });

  // Hover crosshair
  if (hoverX !== null) {
    const { cx } = toCanvas(hoverX, 0);
    ctx.strokeStyle = isDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.15)';
    ctx.lineWidth = 1; ctx.setLineDash([4, 4]);
    ctx.beginPath(); ctx.moveTo(cx, 0); ctx.lineTo(cx, H); ctx.stroke();
    ctx.setLineDash([]);

    // Hover points on each function
    fns.forEach(fn => {
      if (!fn.expr.trim() || !fn.visible) return;
      try {
        const compiled = math.compile(fn.expr);
        const wy = compiled.evaluate({ x: hoverX });
        if (!isFinite(wy) || isNaN(wy)) return;
        const { cx: pcx, cy: pcy } = toCanvas(hoverX, wy);
        if (pcy >= 0 && pcy <= H) {
          ctx.beginPath(); ctx.arc(pcx, pcy, 5, 0, Math.PI * 2);
          ctx.fillStyle = fn.color; ctx.fill();
          ctx.strokeStyle = isDark ? '#0d0d12' : '#fff'; ctx.lineWidth = 2; ctx.stroke();
          // Value label
          ctx.fillStyle = fn.color; ctx.font = 'bold 11px monospace'; ctx.textAlign = 'left';
          ctx.fillText(`y=${+wy.toFixed(4)}`, Math.min(pcx + 8, W - 80), Math.max(pcy - 6, 14));
        }
      } catch {}
    });
  }

  // Tangent line
  if (showTangent && tangentX !== null) {
    fns.forEach(fn => {
      if (!fn.expr.trim() || !fn.visible) return;
      try {
        const compiled = math.compile(fn.expr);
        const h = 1e-5;
        const y0 = compiled.evaluate({ x: tangentX });
        const slope = (compiled.evaluate({ x: tangentX + h }) - compiled.evaluate({ x: tangentX - h })) / (2 * h);
        if (!isFinite(slope) || !isFinite(y0)) return;
        const x1 = view.xMin, y1 = y0 + slope * (x1 - tangentX);
        const x2 = view.xMax, y2 = y0 + slope * (x2 - tangentX);
        const p1 = toCanvas(x1, y1), p2 = toCanvas(x2, y2);
        ctx.strokeStyle = fn.color; ctx.lineWidth = 1.5; ctx.setLineDash([6, 4]);
        ctx.beginPath(); ctx.moveTo(p1.cx, p1.cy); ctx.lineTo(p2.cx, p2.cy); ctx.stroke();
        ctx.setLineDash([]);
        // Label slope
        const { cx, cy } = toCanvas(tangentX, y0);
        ctx.fillStyle = fn.color; ctx.font = 'bold 11px monospace'; ctx.textAlign = 'left';
        ctx.fillText(`k=${+slope.toFixed(4)}`, Math.min(cx + 8, W - 100), Math.max(cy - 18, 14));
      } catch {}
    });
  }

  // Update special points panel
  if (specialPoints.length > 0 && showDots) {
    document.getElementById('intersectPanel').style.display = 'block';
    document.getElementById('intersectList').innerHTML = specialPoints.slice(0, 20).map(p =>
      `<span style="color:${p.color};background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:2px 8px;">${p.label}</span>`
    ).join('');
  } else {
    document.getElementById('intersectPanel').style.display = 'none';
  }

  // Update status
  document.getElementById('rangeInfo').textContent =
    `x[${+view.xMin.toFixed(2)}, ${+view.xMax.toFixed(2)}]  y[${+view.yMin.toFixed(2)}, ${+view.yMax.toFixed(2)}]`;
  updateRangeInputs();
}

// ── View controls ──
function zoom(factor, cx, cy) {
  cx = cx ?? (view.xMin + view.xMax) / 2;
  cy = cy ?? (view.yMin + view.yMax) / 2;
  const xr = (view.xMax - view.xMin) / 2 * factor;
  const yr = (view.yMax - view.yMin) / 2 * factor;
  view = { xMin: cx - xr, xMax: cx + xr, yMin: cy - yr, yMax: cy + yr };
  drawAll();
}

function resetView() {
  view = { xMin: -10, xMax: 10, yMin: -8, yMax: 8 };
  drawAll();
}

function fitView() {
  // Try to fit all functions
  let yMin = Infinity, yMax = -Infinity;
  fns.forEach(fn => {
    if (!fn.expr.trim() || !fn.visible) return;
    try {
      const compiled = math.compile(fn.expr);
      for (let x = view.xMin; x <= view.xMax; x += (view.xMax - view.xMin) / 200) {
        try {
          const y = compiled.evaluate({ x });
          if (isFinite(y) && !isNaN(y)) { yMin = Math.min(yMin, y); yMax = Math.max(yMax, y); }
        } catch {}
      }
    } catch {}
  });
  if (isFinite(yMin) && isFinite(yMax) && yMax > yMin) {
    const pad = (yMax - yMin) * 0.15;
    view.yMin = yMin - pad; view.yMax = yMax + pad;
    drawAll();
  }
}

function applyRange() {
  const xMin = parseFloat(document.getElementById('xMinIn').value);
  const xMax = parseFloat(document.getElementById('xMaxIn').value);
  const yMin = parseFloat(document.getElementById('yMinIn').value);
  const yMax = parseFloat(document.getElementById('yMaxIn').value);
  if ([xMin,xMax,yMin,yMax].every(isFinite) && xMax > xMin && yMax > yMin) {
    view = { xMin, xMax, yMin, yMax };
    drawAll();
  }
}

function updateRangeInputs() {
  document.getElementById('xMinIn').value = +view.xMin.toFixed(2);
  document.getElementById('xMaxIn').value = +view.xMax.toFixed(2);
  document.getElementById('yMinIn').value = +view.yMin.toFixed(2);
  document.getElementById('yMaxIn').value = +view.yMax.toFixed(2);
}

function quickPlot(expr) {
  fns = [{ expr, color: COLORS[0], visible: true, id: fnCounter++ }];
  renderFnList(); drawAll();
}

// ── Toggle options ──
function setTool(t) { activeTool = t; }
function toggleGrid() {
  showGrid = !showGrid;
  document.getElementById('toolGrid').classList.toggle('active', showGrid);
  drawAll();
}
function toggleAxes() {
  showAxes = !showAxes;
  document.getElementById('toolAxes').classList.toggle('active', showAxes);
  drawAll();
}
function toggleDots() {
  showDots = !showDots;
  document.getElementById('toolDots').classList.toggle('active', showDots);
  drawAll();
}
function toggleTangent() {
  showTangent = !showTangent;
  document.getElementById('toolTangent').classList.toggle('active', showTangent);
  drawAll();
}

// ── Export ──
function exportPNG() {
  const link = document.createElement('a');
  link.download = 'dothi.png';
  link.href = canvas.toDataURL();
  link.click();
}

// ── Mouse events ──
canvas.addEventListener('mousedown', e => {
  dragging = true;
  lastMouse = { x: e.offsetX, y: e.offsetY };
  if (showTangent) {
    tangentX = toWorld(e.offsetX, e.offsetY).wx;
    drawAll();
  }
});
canvas.addEventListener('mousemove', e => {
  const { wx, wy } = toWorld(e.offsetX, e.offsetY);
  hoverX = wx;
  document.getElementById('coordDisplay').textContent = `x: ${wx.toFixed(3)}  y: ${wy.toFixed(3)}`;
  if (dragging && !showTangent) {
    const W = canvas.width, H = canvas.height;
    const dx = (e.offsetX - lastMouse.x) / W * (view.xMax - view.xMin);
    const dy = (e.offsetY - lastMouse.y) / H * (view.yMax - view.yMin);
    view.xMin -= dx; view.xMax -= dx;
    view.yMin += dy; view.yMax += dy;
    lastMouse = { x: e.offsetX, y: e.offsetY };
  }
  drawAll();
});
canvas.addEventListener('mouseup', () => dragging = false);
canvas.addEventListener('mouseleave', () => { dragging = false; hoverX = null; drawAll(); });
canvas.addEventListener('wheel', e => {
  e.preventDefault();
  const { wx, wy } = toWorld(e.offsetX, e.offsetY);
  const f = e.deltaY > 0 ? 1.12 : 0.89;
  if (e.shiftKey) {
    // Zoom Y only
    const yr = (view.yMax - view.yMin) / 2 * f;
    view.yMin = wy - yr; view.yMax = wy + yr;
  } else {
    const xr = (view.xMax - view.xMin) / 2 * f;
    const yr = (view.yMax - view.yMin) / 2 * f;
    view.xMin = wx - xr; view.xMax = wx + xr;
    view.yMin = wy - yr; view.yMax = wy + yr;
  }
  drawAll();
}, { passive: false });

// ── Touch events ──
canvas.addEventListener('touchstart', e => {
  e.preventDefault();
  if (e.touches.length === 1) {
    dragging = true;
    lastMouse = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  } else if (e.touches.length === 2) {
    touchDist = Math.hypot(
      e.touches[0].clientX - e.touches[1].clientX,
      e.touches[0].clientY - e.touches[1].clientY
    );
  }
}, { passive: false });

canvas.addEventListener('touchmove', e => {
  e.preventDefault();
  if (e.touches.length === 1 && dragging) {
    const rect = canvas.getBoundingClientRect();
    const tx = e.touches[0].clientX;
    const ty = e.touches[0].clientY;
    const W = canvas.width, H = canvas.height;
    const scaleX = W / rect.width, scaleY = H / rect.height;
    const dx = (tx - lastMouse.x) * scaleX / W * (view.xMax - view.xMin);
    const dy = (ty - lastMouse.y) * scaleY / H * (view.yMax - view.yMin);
    view.xMin -= dx; view.xMax -= dx;
    view.yMin += dy; view.yMax += dy;
    lastMouse = { x: tx, y: ty };
    drawAll();
  } else if (e.touches.length === 2) {
    const newDist = Math.hypot(
      e.touches[0].clientX - e.touches[1].clientX,
      e.touches[0].clientY - e.touches[1].clientY
    );
    if (touchDist) { zoom(touchDist / newDist); }
    touchDist = newDist;
  }
}, { passive: false });

canvas.addEventListener('touchend', () => { dragging = false; touchDist = null; });

// ── Resize observer ──
new ResizeObserver(() => { resizeCanvas(); drawAll(); }).observe(canvas.parentElement);

// Init
renderFnList();
setTimeout(() => { resizeCanvas(); drawAll(); }, 100);


// ══════════════════════════════════════
//  GEOGEBRA HELPERS
// ══════════════════════════════════════
function ggbReset() {
  const frame = document.getElementById('ggbFrame');
  frame.src = frame.src;
}

function ggbFullscreen() {
  const wrap = document.getElementById('ggbWrap');
  const frame = document.getElementById('ggbFrame');
  if (document.fullscreenElement) {
    document.exitFullscreen();
  } else {
    frame.requestFullscreen?.() || wrap.requestFullscreen?.();
  }
}

let ggbDark = false;
function ggbToggleTheme() {
  ggbDark = !ggbDark;
  const frame = document.getElementById('ggbFrame');
  const btn = document.getElementById('ggbThemeBtn');
  const base = 'https://www.geogebra.org/graphing?lang=vi';
  frame.src = ggbDark ? base + '&app=graphing&darkMode=true' : base;
  btn.textContent = ggbDark ? '☀️ Light' : '🌙 Dark';
}

// Responsive iframe height
function resizeGgb() {
  const wrap = document.getElementById('ggbWrap');
  if (!wrap) return;
  const w = wrap.clientWidth;
  // taller on mobile
  const ratio = window.innerWidth < 640 ? 1.4 : 0.65;
  wrap.style.paddingTop = (w * ratio) + 'px';
}
window.addEventListener('resize', resizeGgb);
document.addEventListener('DOMContentLoaded', resizeGgb);
setTimeout(resizeGgb, 100);

// ══════════════════════════════════════
//  SOLVER
// ══════════════════════════════════════
let solveType = 'pt';
const solveHints = {
  pt: 'Nhập phương trình, VD: 2x² - 5x + 3 = 0',
  bpt: 'Nhập bất phương trình, VD: x² - 5x + 6 > 0',
  dao: 'Nhập hàm số, VD: f(x) = x³ - 3x² + 2x',
  tich: 'Nhập tích phân, VD: ∫(x² + 2x) dx',
  luong: 'Nhập PT lượng giác, VD: sin(2x) = cos(x)',
  free: 'Nhập bất kỳ bài toán nào'
};
function setSolveType(t) {
  solveType = t;
  document.querySelectorAll('[id^=stype-]').forEach(el => el.classList.remove('active'));
  document.getElementById('stype-' + t).classList.add('active');
  document.getElementById('solverHint').textContent = solveHints[t];
}
function setExample(v) { document.getElementById('solveInput').value = v; }

async function solveMath() {
  const input = document.getElementById('solveInput').value.trim();
  if (!input) return;
  const btn = document.getElementById('solveBtn');
  btn.disabled = true; btn.innerHTML = '<span class="loading"></span>Đang giải...';
  const out = document.getElementById('solveOutput');
  out.style.display = 'block';
  out.innerHTML = '<span class="loading"></span> AI đang giải từng bước...';
  try {
    const res = await fetch('ai_api.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type: 'math_solve', problem: input, solveType })
    });
    const data = await res.json();
    out.innerHTML = data.result || 'Không giải được!';
    renderMathInElement(out, {
      delimiters: [
        { left: '$$', right: '$$', display: true },
        { left: '$', right: '$', display: false }
      ], throwOnError: false
    });
  } catch { out.innerHTML = 'Lỗi kết nối AI!'; }
  btn.disabled = false; btn.textContent = '✨ Giải';
}

// ══════════════════════════════════════
//  FORMULAS
// ══════════════════════════════════════
const formulaData = {
  'Lượng giác': [
    { name: 'Hằng đẳng thức cơ bản', tex: '\\sin^2 x + \\cos^2 x = 1' },
    { name: 'sin 2x', tex: '\\sin 2x = 2\\sin x\\cos x' },
    { name: 'cos 2x', tex: '\\cos 2x = \\cos^2x - \\sin^2x = 2\\cos^2x-1 = 1-2\\sin^2x' },
    { name: 'Cộng góc sin', tex: '\\sin(a\\pm b) = \\sin a\\cos b \\pm \\cos a\\sin b' },
    { name: 'Cộng góc cos', tex: '\\cos(a\\pm b) = \\cos a\\cos b \\mp \\sin a\\sin b' },
    { name: 'Hạ bậc sin²', tex: '\\sin^2 x = \\dfrac{1-\\cos 2x}{2}' },
    { name: 'Hạ bậc cos²', tex: '\\cos^2 x = \\dfrac{1+\\cos 2x}{2}' },
    { name: 'Tổng → Tích sin', tex: '\\sin a + \\sin b = 2\\sin\\dfrac{a+b}{2}\\cos\\dfrac{a-b}{2}' },
  ],
  'Đạo hàm': [
    { name: "(xⁿ)'", tex: "(x^n)' = nx^{n-1}" },
    { name: "(sin x)'", tex: "(\\sin x)' = \\cos x" },
    { name: "(cos x)'", tex: "(\\cos x)' = -\\sin x" },
    { name: "(eˣ)'", tex: "(e^x)' = e^x" },
    { name: "(ln x)'", tex: "(\\ln x)' = \\dfrac{1}{x}" },
    { name: "(u·v)'", tex: "(uv)' = u'v + uv'" },
    { name: "(u/v)'", tex: "\\left(\\dfrac{u}{v}\\right)' = \\dfrac{u'v - uv'}{v^2}" },
    { name: 'Chain rule', tex: "[f(g(x))]' = f'(g(x))\\cdot g'(x)" },
    { name: 'Tiếp tuyến', tex: "y - f(x_0) = f'(x_0)(x - x_0)" },
  ],
  'Tích phân': [
    { name: '∫xⁿdx', tex: '\\int x^n\\,dx = \\dfrac{x^{n+1}}{n+1} + C' },
    { name: '∫(1/x)dx', tex: '\\int \\dfrac{1}{x}\\,dx = \\ln|x| + C' },
    { name: '∫eˣdx', tex: '\\int e^x\\,dx = e^x + C' },
    { name: '∫sin x dx', tex: '\\int \\sin x\\,dx = -\\cos x + C' },
    { name: '∫cos x dx', tex: '\\int \\cos x\\,dx = \\sin x + C' },
    { name: 'Từng phần', tex: '\\int u\\,dv = uv - \\int v\\,du' },
    { name: 'Newton-Leibniz', tex: '\\int_a^b f(x)\\,dx = F(b) - F(a)' },
    { name: 'Diện tích', tex: 'S = \\int_a^b |f(x) - g(x)|\\,dx' },
  ],
  'Phương trình': [
    { name: 'Bậc 2', tex: 'x = \\dfrac{-b \\pm \\sqrt{\\Delta}}{2a},\\quad \\Delta = b^2 - 4ac' },
    { name: 'Viète', tex: 'x_1+x_2 = -\\dfrac{b}{a},\\quad x_1 x_2 = \\dfrac{c}{a}' },
    { name: 'sin x = m', tex: '\\sin x = m \\Rightarrow x = \\arcsin m + k2\\pi \\text{ hoặc } x = \\pi - \\arcsin m + k2\\pi' },
    { name: 'cos x = m', tex: '\\cos x = m \\Rightarrow x = \\pm\\arccos m + k2\\pi' },
    { name: 'tan x = m', tex: '\\tan x = m \\Rightarrow x = \\arctan m + k\\pi' },
  ],
  'Hình học': [
    { name: 'Định lý Cosine', tex: 'a^2 = b^2 + c^2 - 2bc\\cos A' },
    { name: 'Định lý Sine', tex: '\\dfrac{a}{\\sin A} = \\dfrac{b}{\\sin B} = 2R' },
    { name: 'Diện tích tròn', tex: 'S = \\pi R^2,\\quad C = 2\\pi R' },
    { name: 'Thể tích cầu', tex: 'V = \\dfrac{4}{3}\\pi R^3' },
    { name: 'Thể tích chóp', tex: 'V = \\dfrac{1}{3}S_{đáy} \\cdot h' },
    { name: 'Khoảng cách điểm–đường', tex: 'd = \\dfrac{|ax_0+by_0+c|}{\\sqrt{a^2+b^2}}' },
  ],
  'Tổ hợp & XS': [
    { name: 'Tổ hợp', tex: 'C_n^k = \\dfrac{n!}{k!(n-k)!}' },
    { name: 'Nhị thức Newton', tex: '(a+b)^n = \\sum_{k=0}^n C_n^k a^{n-k}b^k' },
    { name: 'Xác suất cổ điển', tex: 'P(A) = \\dfrac{m}{n}' },
    { name: 'XS độc lập', tex: 'P(A\\cap B) = P(A)\\cdot P(B)' },
  ],
};

let activeCat = 'all';
function renderFormulas(cat) {
  activeCat = cat;
  const cats = Object.keys(formulaData);
  document.getElementById('formulaCats').innerHTML =
    `<span class="fcat ${cat==='all'?'active':''}" onclick="renderFormulas('all')">Tất cả</span>` +
    cats.map(c => `<span class="fcat ${cat===c?'active':''}" onclick="renderFormulas('${c}')">${c}</span>`).join('');
  const items = cat === 'all' ? cats.flatMap(c => formulaData[c]) : formulaData[cat] || [];
  document.getElementById('formulaGrid').innerHTML = items.map(f => `
    <div class="formula-card">
      <div class="formula-name">${f.name}</div>
      <div class="formula-eq">\\(${f.tex}\\)</div>
    </div>`).join('');
  renderMathInElement(document.getElementById('formulaGrid'), {
    delimiters: [{ left: '\\(', right: '\\)', display: false }],
    throwOnError: false
  });
}

// ══════════════════════════════════════
//  CALCULATOR
// ══════════════════════════════════════
let calcExpr = '';
function calcInput(v) {
  if (v === '(') calcExpr += calcExpr.length && /[\d)]$/.test(calcExpr) ? '*(' : '(';
  else calcExpr += v;
  document.getElementById('calcExpr').textContent = calcExpr;
  try {
    const r = math.evaluate(calcExpr.replace(/\^/g, '**'));
    document.getElementById('calcResult').textContent = +r.toFixed(10);
  } catch {}
}
function calcClear() {
  calcExpr = '';
  document.getElementById('calcExpr').textContent = '';
  document.getElementById('calcResult').textContent = '0';
}
function calcDel() {
  calcExpr = calcExpr.slice(0, -1);
  document.getElementById('calcExpr').textContent = calcExpr;
}
function calcEval() {
  try {
    const r = math.evaluate(calcExpr.replace(/\^/g, '**'));
    const res = +r.toFixed(10);
    document.getElementById('calcResult').textContent = res;
    calcExpr = String(res);
    document.getElementById('calcExpr').textContent = '';
  } catch {
    document.getElementById('calcResult').textContent = 'Lỗi';
  }
}
</script>
</body>
</html>
