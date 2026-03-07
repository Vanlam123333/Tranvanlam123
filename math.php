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

/* ════════════════════════════════════
   CASIO FX-880BTG REPLICA
════════════════════════════════════ */
.cx-wrap { display:flex; justify-content:center; padding:16px 0 24px; }
.cx-body {
  background: linear-gradient(170deg,#1c2c44 0%,#0e1a28 100%);
  border-radius: 14px 14px 24px 24px;
  padding: 12px 10px 18px;
  width: 296px;
  box-shadow: 0 16px 48px rgba(0,0,0,0.7),
              inset 0 1px 0 rgba(255,255,255,0.07),
              inset 0 -3px 0 rgba(0,0,0,0.4);
  user-select: none;
  -webkit-user-select: none;
}
/* Header */
.cx-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 4px; margin-bottom: 8px;
}
.cx-brand { font-size:17px; font-weight:900; color:#e8501a; letter-spacing:3px; font-style:italic; font-family:'Arial Black',sans-serif; }
.cx-solar { width:44px; height:9px; background:linear-gradient(90deg,#0d220d,#1a4a1a,#0d220d); border-radius:2px; border:1px solid #091209; box-shadow:inset 0 1px 2px rgba(0,0,0,0.5); }
.cx-model { font-size:9px; color:rgba(255,255,255,0.35); letter-spacing:0.5px; font-family:'Arial',sans-serif; }
/* Display */
.cx-display {
  background: #c2d48e;
  border-radius: 5px 5px 3px 3px;
  padding: 6px 8px 5px;
  margin-bottom: 9px;
  border: 2px solid #8a9a60;
  box-shadow: inset 0 3px 8px rgba(0,0,0,0.35), 0 1px 0 rgba(255,255,255,0.08);
  min-height: 68px;
}
.cx-ind-bar {
  display:flex; gap:5px; margin-bottom:3px; align-items:center;
}
.cx-ind {
  font-size:7.5px; font-weight:800; color:#1e2e08;
  opacity:0.2; letter-spacing:0.3px; font-family:'Courier New',monospace;
  transition:opacity 0.1s;
}
.cx-ind.on { opacity:1; }
.cx-ind-mode { font-size:7.5px; font-weight:800; color:#1e2e08; opacity:0.55; margin-left:auto; font-family:'Courier New',monospace; }
.cx-disp-expr {
  font-family:'Courier New',monospace; font-size:12.5px; color:#1a2a05;
  text-align:right; min-height:17px; line-height:1.3;
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.cx-disp-result {
  font-family:'Courier New',monospace; font-size:19px; font-weight:700;
  color:#1a2a05; text-align:right; min-height:23px; margin-top:1px;
}
/* Key structure */
.cx-rows { display:flex; flex-direction:column; gap:0; }
.cx-row { display:flex; gap:3px; margin-bottom:3px; }
.cx-row-gap { margin-bottom:5px; }
.cx-divider { height:1px; background:rgba(255,255,255,0.05); margin: 3px 2px 6px; }
/* Base key */
.cx-key {
  position:relative; flex:1;
  height:30px; border:none; border-radius:4px;
  cursor:pointer;
  background: linear-gradient(180deg,#3c4e64 0%,#2a3848 100%);
  box-shadow: 0 3px 0 #111c2a, 0 1px 0 rgba(255,255,255,0.07) inset;
  color:#dce4f0; font-family:'Arial',sans-serif;
  font-size:10px; font-weight:700;
  display:flex; flex-direction:column;
  align-items:center; justify-content:flex-end;
  padding-bottom:4px; outline:none;
  transition:filter 0.07s;
  -webkit-tap-highlight-color:transparent;
}
.cx-key:active { transform:translateY(2px); box-shadow:0 1px 0 #111c2a; filter:brightness(1.15); }
/* Shift label (yellow, top inside key) */
.cx-sl {
  position:absolute; top:2px; left:50%; transform:translateX(-50%);
  font-size:6.5px; font-weight:800; color:#e8b800;
  white-space:nowrap; pointer-events:none; line-height:1;
  font-family:'Arial',sans-serif; letter-spacing:0.2px;
}
/* Alpha label (red, top-right) */
.cx-al {
  position:absolute; top:2px; right:3px;
  font-size:6px; font-weight:800; color:#ff6a6a;
  pointer-events:none; line-height:1; font-family:'Arial',sans-serif;
}
/* Main key text */
.cx-kl { font-size:10px; font-weight:700; line-height:1; }
.cx-kl-sm { font-size:8.5px; font-weight:700; line-height:1; }
.cx-kl-lg { font-size:12px; font-weight:800; line-height:1; }
/* Key variants */
.cx-k-shift {
  background:linear-gradient(180deg,#c49000 0%,#9a6e00 100%) !important;
  box-shadow:0 3px 0 #5a3e00, 0 1px 0 rgba(255,255,255,0.15) inset !important;
  color:#fff !important; flex:1.05 !important;
}
.cx-k-alpha {
  background:linear-gradient(180deg,#b83030 0%,#881818 100%) !important;
  box-shadow:0 3px 0 #480808, 0 1px 0 rgba(255,255,255,0.1) inset !important;
  color:#fff !important; flex:1.05 !important;
}
.cx-k-on {
  background:linear-gradient(180deg,#cc2020 0%,#991010 100%) !important;
  box-shadow:0 3px 0 #550000, 0 1px 0 rgba(255,255,255,0.1) inset !important;
  color:#fff !important;
}
.cx-k-eq {
  background:linear-gradient(180deg,#1a4dcc 0%,#113499 100%) !important;
  box-shadow:0 4px 0 #091a66, 0 1px 0 rgba(255,255,255,0.15) inset !important;
  color:#fff !important; height:34px !important; flex:1 !important;
}
.cx-k-del {
  background:linear-gradient(180deg,#4a3a60 0%,#382a50 100%) !important;
  box-shadow:0 3px 0 #18082a !important;
}
.cx-k-ac {
  background:linear-gradient(180deg,#364e2c 0%,#253820 100%) !important;
  box-shadow:0 3px 0 #0a1808 !important;
  color:#a0e080 !important;
}
.cx-k-op {
  background:linear-gradient(180deg,#263d58 0%,#182c44 100%) !important;
  box-shadow:0 3px 0 #080f1e !important;
}
.cx-k-mem {
  background:linear-gradient(180deg,#2c3a50 0%,#1e2c40 100%) !important;
  box-shadow:0 3px 0 #080f1e !important;
  color:#90aacc !important;
}
/* Active glow on shift/alpha state */
.cx-shift-on .cx-k-shift { box-shadow:0 3px 0 #5a3e00, 0 0 8px #e8b800 !important; }
.cx-alpha-on .cx-k-alpha { box-shadow:0 3px 0 #480808, 0 0 8px #ff6060 !important; }
.cx-hyp-on .cx-key[data-key="hyp"] { box-shadow:0 3px 0 #111c2a, 0 0 6px #60c0ff !important; color:#60c0ff !important; }
/* Casio key accent */
.cx-k-num {
  background:linear-gradient(180deg,#344258 0%,#222e44 100%) !important;
  box-shadow:0 3px 0 #0c1422 !important;
}

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

  <!-- ══════════ TAB: CALCULATOR (CASIO FX-880BTG) ══════════ -->
  <div id="tab-calc" style="display:none">
    <div class="cx-wrap">
      <div class="cx-body" id="cxBody">

        <!-- BRAND HEADER -->
        <div class="cx-header">
          <span class="cx-brand">CASIO</span>
          <div class="cx-solar"></div>
          <span class="cx-model">fx-880BTG</span>
        </div>

        <!-- DISPLAY -->
        <div class="cx-display">
          <div class="cx-ind-bar">
            <span class="cx-ind" id="cxIS">S</span>
            <span class="cx-ind" id="cxIA">A</span>
            <span class="cx-ind" id="cxIM">M</span>
            <span class="cx-ind" id="cxIH">HYP</span>
            <span class="cx-ind on" id="cxID">D</span>
            <span class="cx-ind" id="cxIR">R</span>
            <span class="cx-ind" id="cxIG">G</span>
            <span class="cx-ind-mode" id="cxModeLabel">COMP</span>
          </div>
          <div class="cx-disp-expr" id="cxExpr">0</div>
          <div class="cx-disp-result" id="cxResult">&nbsp;</div>
        </div>

        <!-- KEYS -->
        <div class="cx-rows">

          <!-- ── ROW 1: SHIFT ALPHA MODE ON ── -->
          <div class="cx-row cx-row-gap">
            <button class="cx-key cx-k-shift" id="cxBtnShift" onclick="cx('shift')">
              <span class="cx-kl">SHIFT</span>
            </button>
            <button class="cx-key cx-k-alpha" id="cxBtnAlpha" onclick="cx('alpha')">
              <span class="cx-kl">ALPHA</span>
            </button>
            <button class="cx-key" onclick="cx('mode')">
              <span class="cx-sl">SETUP</span>
              <span class="cx-kl">MODE</span>
            </button>
            <button class="cx-key cx-k-on" onclick="cx('on')">
              <span class="cx-sl" style="color:#ff9090;">CLR</span>
              <span class="cx-kl">ON</span>
            </button>
          </div>

          <!-- ── ROW 2: CALC ∫dx SOLVE STO ── -->
          <div class="cx-row cx-row-gap">
            <button class="cx-key" onclick="cx('calc')">
              <span class="cx-sl">d/dx</span>
              <span class="cx-kl-sm">CALC</span>
            </button>
            <button class="cx-key" onclick="cx('integ')">
              <span class="cx-sl">Σ</span>
              <span class="cx-kl-sm">∫dx</span>
            </button>
            <button class="cx-key" onclick="cx('solve')">
              <span class="cx-sl">Ref</span>
              <span class="cx-kl-sm">SOLVE</span>
            </button>
            <button class="cx-key cx-k-mem" onclick="cx('sto')">
              <span class="cx-sl">RCL</span>
              <span class="cx-kl-sm">STO▶</span>
            </button>
          </div>

          <div class="cx-divider"></div>

          <!-- ── ROW 3: x² x⁻¹ sin cos tan ── -->
          <div class="cx-row">
            <button class="cx-key" onclick="cx('x2')">
              <span class="cx-sl">√</span>
              <span class="cx-kl">x²</span>
            </button>
            <button class="cx-key" onclick="cx('xinv')">
              <span class="cx-sl">x!</span>
              <span class="cx-kl">x⁻¹</span>
            </button>
            <button class="cx-key" onclick="cx('sin')">
              <span class="cx-sl">sin⁻¹</span>
              <span class="cx-kl">sin</span>
            </button>
            <button class="cx-key" onclick="cx('cos')">
              <span class="cx-sl">cos⁻¹</span>
              <span class="cx-kl">cos</span>
            </button>
            <button class="cx-key" onclick="cx('tan')">
              <span class="cx-sl">tan⁻¹</span>
              <span class="cx-kl">tan</span>
            </button>
          </div>

          <!-- ── ROW 4: ^ log ln ( ) ── -->
          <div class="cx-row">
            <button class="cx-key" onclick="cx('pow')">
              <span class="cx-sl">ˣ√</span>
              <span class="cx-kl">^</span>
            </button>
            <button class="cx-key" onclick="cx('log')">
              <span class="cx-sl">10ˣ</span>
              <span class="cx-kl">log</span>
            </button>
            <button class="cx-key" onclick="cx('ln')">
              <span class="cx-sl">eˣ</span>
              <span class="cx-kl">ln</span>
            </button>
            <button class="cx-key" onclick="cx('lpar')">
              <span class="cx-sl">Abs</span>
              <span class="cx-kl">(</span>
            </button>
            <button class="cx-key" onclick="cx('rpar')">
              <span class="cx-sl">)</span>
              <span class="cx-kl">)</span>
            </button>
          </div>

          <!-- ── ROW 5: S⟺D (-) °'" hyp Ran# ── -->
          <div class="cx-row cx-row-gap">
            <button class="cx-key" onclick="cx('std')">
              <span class="cx-sl">←</span>
              <span class="cx-kl-sm">S⟺D</span>
            </button>
            <button class="cx-key" onclick="cx('neg')">
              <span class="cx-sl">d/c</span>
              <span class="cx-kl-sm">(-)</span>
            </button>
            <button class="cx-key" onclick="cx('dms')">
              <span class="cx-sl">←°'"</span>
              <span class="cx-kl">°'"</span>
            </button>
            <button class="cx-key" data-key="hyp" id="cxBtnHyp" onclick="cx('hyp')">
              <span class="cx-sl">HYP⁻¹</span>
              <span class="cx-kl">hyp</span>
            </button>
            <button class="cx-key" onclick="cx('ran')">
              <span class="cx-sl">π</span>
              <span class="cx-kl-sm">Ran#</span>
            </button>
          </div>

          <div class="cx-divider"></div>

          <!-- ── ROW 6: 7 8 9 DEL AC ── -->
          <div class="cx-row">
            <button class="cx-key cx-k-num" onclick="cx('7')">
              <span class="cx-sl">nPr</span>
              <span class="cx-kl-lg">7</span>
            </button>
            <button class="cx-key cx-k-num" onclick="cx('8')">
              <span class="cx-sl">nCr</span>
              <span class="cx-kl-lg">8</span>
            </button>
            <button class="cx-key cx-k-num" onclick="cx('9')">
              <span class="cx-sl">Pol(</span>
              <span class="cx-kl-lg">9</span>
            </button>
            <button class="cx-key cx-k-del" onclick="cx('del')">
              <span class="cx-sl">INS</span>
              <span class="cx-kl">DEL</span>
            </button>
            <button class="cx-key cx-k-ac" onclick="cx('ac')">
              <span class="cx-sl" style="color:#ff9090;">OFF</span>
              <span class="cx-kl">AC</span>
            </button>
          </div>

          <!-- ── ROW 7: 4 5 6 × M+ ── -->
          <div class="cx-row">
            <button class="cx-key cx-k-num" onclick="cx('4')">
              <span class="cx-sl">Rec(</span>
              <span class="cx-kl-lg">4</span>
            </button>
            <button class="cx-key cx-k-num" onclick="cx('5')">
              <span class="cx-sl">→r θ</span>
              <span class="cx-kl-lg">5</span>
            </button>
            <button class="cx-key cx-k-num" onclick="cx('6')">
              <span class="cx-sl">→x y</span>
              <span class="cx-kl-lg">6</span>
            </button>
            <button class="cx-key cx-k-op" onclick="cx('mul')">
              <span class="cx-sl">÷</span>
              <span class="cx-kl">×</span>
            </button>
            <button class="cx-key cx-k-mem" onclick="cx('mplus')">
              <span class="cx-sl">M–</span>
              <span class="cx-kl">M+</span>
            </button>
          </div>

          <!-- ── ROW 8: 1 2 3 - RCL ── -->
          <div class="cx-row">
            <button class="cx-key cx-k-num" onclick="cx('1')">
              <span class="cx-sl">←ENG</span>
              <span class="cx-kl-lg">1</span>
            </button>
            <button class="cx-key cx-k-num" onclick="cx('2')">
              <span class="cx-sl" style="font-size:5.5px;">Ran#Int</span>
              <span class="cx-kl-lg">2</span>
            </button>
            <button class="cx-key cx-k-num" onclick="cx('3')">
              <span class="cx-al">C</span>
              <span class="cx-kl-lg">3</span>
            </button>
            <button class="cx-key cx-k-op" onclick="cx('sub')">
              <span class="cx-kl">–</span>
            </button>
            <button class="cx-key cx-k-mem" onclick="cx('rcl')">
              <span class="cx-sl">STO</span>
              <span class="cx-kl">RCL</span>
            </button>
          </div>

          <!-- ── ROW 9: 0 . EXP + Ans ── -->
          <div class="cx-row">
            <button class="cx-key cx-k-num" onclick="cx('0')" style="flex:1.4;">
              <span class="cx-sl" style="font-size:5.5px;">Ran#Int</span>
              <span class="cx-kl-lg">0</span>
            </button>
            <button class="cx-key cx-k-num" onclick="cx('dot')">
              <span class="cx-sl">:</span>
              <span class="cx-kl-lg">.</span>
            </button>
            <button class="cx-key cx-k-op" onclick="cx('exp')">
              <span class="cx-sl">×10ˣ</span>
              <span class="cx-kl-sm">EXP</span>
            </button>
            <button class="cx-key cx-k-op" onclick="cx('add')">
              <span class="cx-kl">+</span>
            </button>
            <button class="cx-key cx-k-mem" onclick="cx('ans')">
              <span class="cx-sl">%</span>
              <span class="cx-kl">Ans</span>
            </button>
          </div>

          <!-- ── ROW 10: = ── -->
          <div class="cx-row" style="margin-top:2px;">
            <button class="cx-key cx-k-eq" onclick="cx('eq')">
              <span class="cx-kl-lg">=</span>
            </button>
          </div>

        </div><!-- /cx-rows -->
      </div><!-- /cx-body -->
    </div><!-- /cx-wrap -->
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
  if (t === 'graph' && canvas) setTimeout(() => { resizeCanvas(); drawAll(); }, 50);
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
const ctx = canvas ? canvas.getContext('2d') : null;

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

// ── Mouse & Touch events (chỉ khi canvas tồn tại) ──
if (canvas) {

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

} // end if (canvas)


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
    if (res.status === 401) {
      out.innerHTML = '<div class="step"><div class="step-num" style="color:var(--red)">⚠️ Lỗi đăng nhập</div>Vui lòng <a href="login.php">đăng nhập</a> để sử dụng tính năng giải toán AI.</div>';
      btn.disabled = false; btn.textContent = '✨ Giải'; return;
    }
    let data;
    try { data = await res.json(); } catch {
      out.innerHTML = '<div class="step"><div class="step-num" style="color:var(--red)">⚠️ Lỗi máy chủ</div>Máy chủ trả về phản hồi không hợp lệ. Kiểm tra lại PHP server.</div>';
      btn.disabled = false; btn.textContent = '✨ Giải'; return;
    }
    out.innerHTML = data.result || 'Không giải được!';
    renderMathInElement(out, {
      delimiters: [
        { left: '$$', right: '$$', display: true },
        { left: '$', right: '$', display: false }
      ], throwOnError: false
    });
  } catch (err) {
    if (err instanceof TypeError && err.message.includes('fetch')) {
      out.innerHTML = '<div class="step"><div class="step-num" style="color:var(--red)">⚠️ Không kết nối được</div>Không thể kết nối đến máy chủ. Đảm bảo PHP server đang chạy và file <code>ai_api.php</code> tồn tại.</div>';
    } else {
      out.innerHTML = '<div class="step"><div class="step-num" style="color:var(--red)">⚠️ Lỗi</div>' + (err.message || 'Lỗi không xác định') + '</div>';
    }
  }
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
//  CASIO FX-880BTG CALCULATOR ENGINE
// ══════════════════════════════════════
const cxS = {
  expr:      '',      // display expression string
  isShift:   false,
  isAlpha:   false,
  isHyp:     false,
  angleMode: 0,       // 0=DEG 1=RAD 2=GRAD
  memory:    0,
  lastAns:   0,
  newEntry:  true,    // start fresh after =
  errState:  false,
};

/* ── Angle-aware trig scope ── */
function cxScope() {
  const toR = cxS.angleMode===0 ? Math.PI/180 : cxS.angleMode===2 ? Math.PI/200 : 1;
  const frR = 1/toR;
  const nPr = (n,r)=>{ let p=1; for(let i=0;i<r;i++) p*=(n-i); return p; };
  const nCr = (n,r)=>{ let p=1,k=Math.min(r,n-r); for(let i=0;i<k;i++) p*=(n-i)/(i+1); return Math.round(p); };
  return {
    sin:x=>Math.sin(x*toR),  cos:x=>Math.cos(x*toR),  tan:x=>Math.tan(x*toR),
    asin:x=>Math.asin(x)*frR, acos:x=>Math.acos(x)*frR, atan:x=>Math.atan(x)*frR,
    sinh:Math.sinh, cosh:Math.cosh, tanh:Math.tanh,
    asinh:Math.asinh, acosh:Math.acosh, atanh:Math.atanh,
    log:x=>Math.log10(x),   /* Casio: log = log10 */
    ln:x=>Math.log(x),
    sqrt:Math.sqrt,  cbrt:Math.cbrt,
    abs:Math.abs,    sign:Math.sign,
    ceil:Math.ceil,  floor:Math.floor, round:Math.round,
    pi:Math.PI, e:Math.E,
    Ans:cxS.lastAns, MR:cxS.memory,
    nPr, nCr,
  };
}

/* ── Preprocess display string → evaluable string ── */
function cxPrep(s) {
  s = s.replace(/Ans/g, '('+cxS.lastAns+')');
  s = s.replace(/MR/g,  '('+cxS.memory+')');
  s = s.replace(/π/g,   '(pi)');
  s = s.replace(/×/g,   '*');
  s = s.replace(/÷/g,   '/');
  s = s.replace(/–/g,   '-');
  s = s.replace(/×10\^/g,'*10^');
  s = s.replace(/√\(/g, 'sqrt(');
  s = s.replace(/∛\(/g, 'cbrt(');
  s = s.replace(/%/g,   '/100');
  s = s.replace(/°/g,   '');
  return s;
}

/* ── Format result for display ── */
function cxFmt(n) {
  if (!isFinite(n)) return 'Math ERROR';
  if (n === 0) return '0';
  const abs = Math.abs(n);
  if (abs >= 1e10 || (abs < 1e-4 && abs > 0)) {
    const e = n.toExponential(7).replace(/\.?0+e/,'e');
    return e.replace('e+','×10^').replace('e','×10^');
  }
  let s = n.toPrecision(10).replace(/\.?0+$/, '');
  // remove redundant leading 0 issues
  return s;
}

/* ── Convert decimal → fraction string (best effort) ── */
function cxFrac(n) {
  if (Number.isInteger(n)) return String(n);
  const tol = 1e-9; let h1=1,h2=0,k1=0,k2=1,b=Math.abs(n);
  for (let i=0;i<40;i++) {
    const a=Math.floor(b); let t;
    t=h1; h1=a*h1+h2; h2=t;
    t=k1; k1=a*k1+k2; k2=t;
    b=1/(b-a); if (Math.abs(Math.abs(n)-h1/k1)<tol) break;
  }
  if (k1>9999) return null;
  return (n<0?'-':'')+(h1>k1 ? Math.floor(h1/k1)+'⌐'+(h1%k1)+'/'+k1 : h1+'/'+k1);
}

/* ── Update UI ── */
function cxRender() {
  const el_e = document.getElementById('cxExpr');
  const el_r = document.getElementById('cxResult');
  if (!el_e) return;

  el_e.textContent = cxS.expr || '0';

  // Live preview
  if (cxS.expr && !cxS.errState) {
    try {
      const r = math.evaluate(cxPrep(cxS.expr), cxScope());
      if (typeof r === 'number' && isFinite(r)) {
        el_r.textContent = cxFmt(r);
      } else { el_r.textContent = '\u00a0'; }
    } catch { el_r.textContent = '\u00a0'; }
  } else if (!cxS.expr) {
    el_r.textContent = '\u00a0';
  }

  // Indicators
  const tog = (id,on) => document.getElementById(id)?.classList.toggle('on',on);
  tog('cxIS', cxS.isShift);
  tog('cxIA', cxS.isAlpha);
  tog('cxIM', cxS.memory !== 0);
  tog('cxIH', cxS.isHyp);
  tog('cxID', cxS.angleMode === 0);
  tog('cxIR', cxS.angleMode === 1);
  tog('cxIG', cxS.angleMode === 2);

  const body = document.getElementById('cxBody');
  if (body) {
    body.classList.toggle('cx-shift-on', cxS.isShift);
    body.classList.toggle('cx-alpha-on', cxS.isAlpha);
    body.classList.toggle('cx-hyp-on',  cxS.isHyp);
  }

  // SHIFT/ALPHA button brightness
  const bs = document.getElementById('cxBtnShift');
  const ba = document.getElementById('cxBtnAlpha');
  const bh = document.getElementById('cxBtnHyp');
  if (bs) bs.style.filter = cxS.isShift ? 'brightness(1.3) drop-shadow(0 0 5px #ffcc00)' : '';
  if (ba) ba.style.filter = cxS.isAlpha ? 'brightness(1.3) drop-shadow(0 0 5px #ff6060)' : '';
  if (bh) bh.style.filter = cxS.isHyp  ? 'brightness(1.3) drop-shadow(0 0 5px #60c0ff)' : '';

  const modes = ['DEG','RAD','GRAD'];
  const ml = document.getElementById('cxModeLabel');
  if (ml) ml.textContent = modes[cxS.angleMode];
}

/* ── Append to expression ── */
function cxAppend(s) {
  if (cxS.newEntry) {
    // If continuing with an operator, keep result; else start fresh
    if (/^[+\-×÷^]/.test(s)) { cxS.expr = cxFmt(cxS.lastAns) + s; }
    else { cxS.expr = s; }
    cxS.newEntry = false;
  } else {
    cxS.expr += s;
  }
  cxS.errState = false;
}

/* ── Clear last token (smart DEL) ── */
function cxDel() {
  const toks = ['asin(','acos(','atan(','asinh(','acosh(','atanh(','sinh(','cosh(','tanh(',
                'sin(','cos(','tan(','sqrt(','cbrt(','log(','ln(','abs(',
                '×10^','nPr(','nCr(','Pol(','Rec(','Ans','MR','pi','(-'];
  for (const t of toks) {
    if (cxS.expr.endsWith(t)) { cxS.expr = cxS.expr.slice(0,-t.length); return; }
  }
  cxS.expr = cxS.expr.slice(0,-1);
}

/* ── Main key handler ── */
function cx(key) {
  const sh = cxS.isShift;
  const hy = cxS.isHyp;

  // Reset modifiers (except hyp, handled separately)
  if (key !== 'shift' && key !== 'alpha' && key !== 'hyp') {
    cxS.isShift = false;
    cxS.isAlpha = false;
  }
  if (key !== 'hyp' && !['sin','cos','tan'].includes(key)) cxS.isHyp = false;

  if (cxS.errState && !['ac','on','del'].includes(key)) {
    cxS.expr=''; cxS.errState=false; cxS.newEntry=true;
  }

  switch(key) {

    /* ── CONTROL ── */
    case 'shift': cxS.isShift = !cxS.isShift; if(cxS.isShift) cxS.isAlpha=false; break;
    case 'alpha': cxS.isAlpha = !cxS.isAlpha; if(cxS.isAlpha) cxS.isShift=false; break;
    case 'hyp':   cxS.isHyp   = !cxS.isHyp;  break;

    case 'on':
    case 'ac':
      cxS.expr=''; cxS.isShift=false; cxS.isAlpha=false;
      cxS.isHyp=false; cxS.newEntry=true; cxS.errState=false;
      document.getElementById('cxResult').textContent='\u00a0';
      break;

    case 'del':
      if (sh) { cxS.expr=''; cxS.newEntry=true; }
      else cxDel();
      break;

    case 'mode':
      cxS.angleMode = (cxS.angleMode+1)%3;
      break;

    /* ── DIGITS ── */
    case '0': case '1': case '2': case '3': case '4':
    case '5': case '6': case '7': case '8': case '9':
      if (sh) {
        const sm={'7':'nPr(','8':'nCr(','9':'Pol(','4':'Rec('};
        if (sm[key]) { cxAppend(sm[key]); break; }
      }
      cxAppend(key);
      break;

    case 'dot': cxAppend('.'); break;

    /* ── OPERATORS ── */
    case 'add': cxAppend('+'); break;
    case 'sub': cxAppend('–'); break;
    case 'mul': cxAppend(sh ? '÷' : '×'); break;
    case 'neg': cxAppend('(–'); break;
    case 'lpar': cxAppend(sh ? 'abs(' : '('); break;
    case 'rpar': cxAppend(')'); break;

    /* ── POWER / ROOT ── */
    case 'pow':
      cxAppend(sh ? '^(1÷' : '^');
      break;

    case 'x2':
      if (sh) cxAppend('√(');
      else    cxAppend('^2');
      break;

    case 'xinv':
      if (sh) cxAppend('!');
      else    cxAppend('^(–1)');
      break;

    /* ── TRIG ── */
    case 'sin':
      if (sh && hy)       cxAppend('asinh(');
      else if (sh)        cxAppend('asin(');
      else if (hy)        cxAppend('sinh(');
      else                cxAppend('sin(');
      cxS.isHyp = false;
      break;

    case 'cos':
      if (sh && hy)       cxAppend('acosh(');
      else if (sh)        cxAppend('acos(');
      else if (hy)        cxAppend('cosh(');
      else                cxAppend('cos(');
      cxS.isHyp = false;
      break;

    case 'tan':
      if (sh && hy)       cxAppend('atanh(');
      else if (sh)        cxAppend('atan(');
      else if (hy)        cxAppend('tanh(');
      else                cxAppend('tan(');
      cxS.isHyp = false;
      break;

    /* ── LOG ── */
    case 'log':
      cxAppend(sh ? '10^(' : 'log(');
      break;

    case 'ln':
      cxAppend(sh ? 'e^(' : 'ln(');
      break;

    /* ── EXP / PI / ANS ── */
    case 'exp':
      cxAppend('×10^');
      break;

    case 'ran':
      if (sh) cxAppend('π');
      else    cxAppend(parseFloat(Math.random().toFixed(3)).toString());
      break;

    case 'ans':
      if (sh) cxAppend('%');
      else    cxAppend('Ans');
      break;

    /* ── DMS ── */
    case 'dms':
      // Insert degree symbol or convert result
      if (cxS.expr) cxAppend('°');
      break;

    /* ── S⟺D (fraction/decimal toggle) ── */
    case 'std':
      if (cxS.expr) {
        try {
          const r = math.evaluate(cxPrep(cxS.expr), cxScope());
          if (typeof r==='number' && isFinite(r)) {
            const frac = cxFrac(r);
            const resultEl = document.getElementById('cxResult');
            if (frac && frac !== cxFmt(r)) {
              resultEl.textContent = frac;
            } else {
              resultEl.textContent = cxFmt(r);
            }
          }
        } catch {}
      }
      cxRender(); return;

    /* ── MEMORY ── */
    case 'mplus':
      try {
        const r = math.evaluate(cxPrep(cxS.expr||'0'), cxScope());
        if (typeof r==='number' && isFinite(r)) cxS.memory += sh ? -r : r;
      } catch {}
      break;

    case 'rcl':
      if (sh) {
        // STO: store result
        try {
          const r = math.evaluate(cxPrep(cxS.expr||'0'), cxScope());
          if (typeof r==='number' && isFinite(r)) cxS.memory = r;
        } catch {}
      } else {
        // RCL: paste memory value
        cxAppend(cxFmt(cxS.memory));
      }
      break;

    case 'sto':
      if (sh) {
        // RCL in shift mode
        cxAppend(cxFmt(cxS.memory));
      } else {
        try {
          const r = math.evaluate(cxPrep(cxS.expr||'0'), cxScope());
          if (typeof r==='number' && isFinite(r)) cxS.memory = r;
        } catch {}
      }
      break;

    /* ── UNIMPLEMENTED PLACEHOLDERS ── */
    case 'calc': case 'integ': case 'solve':
      // Show brief message
      document.getElementById('cxResult').textContent = sh ?
        (key==='calc'?'d/dx':key==='integ'?'Σ':'Refresh') : key.toUpperCase()+' N/A';
      setTimeout(()=>cxRender(), 1400);
      break;

    /* ── EQUALS ── */
    case 'eq':
      if (!cxS.expr) break;
      try {
        const r = math.evaluate(cxPrep(cxS.expr), cxScope());
        if (typeof r === 'number' && isFinite(r)) {
          cxS.lastAns = r;
          const res = cxFmt(r);
          document.getElementById('cxExpr').textContent = cxS.expr;
          document.getElementById('cxResult').textContent = res;
          cxS.expr = res;
          cxS.newEntry = true;
          cxS.errState = false;
          cxRender(); return;
        } else { throw new Error(); }
      } catch {
        document.getElementById('cxExpr').textContent = cxS.expr;
        document.getElementById('cxResult').textContent = 'Math ERROR';
        cxS.expr = ''; cxS.newEntry = true; cxS.errState = true;
        cxRender(); return;
      }
  }

  cxRender();
}

// Keyboard support for calculator tab
document.addEventListener('keydown', function(e) {
  if (document.getElementById('tab-calc').style.display === 'none') return;
  const map = {
    '0':'0','1':'1','2':'2','3':'3','4':'4',
    '5':'5','6':'6','7':'7','8':'8','9':'9',
    '.':'dot','+':'add','-':'sub','*':'mul','/':e.shiftKey?'':'mul',
    'Enter':'eq','=':'eq','Backspace':'del','Escape':'ac',
  };
  if (e.key === '/') { e.preventDefault(); cxAppend('÷'); return; }
  if (map[e.key]) { e.preventDefault(); cx(map[e.key]); }
  if (e.key === '(' || (e.key === '9' && e.shiftKey)) { e.preventDefault(); cx('lpar'); }
  if (e.key === ')' || (e.key === '0' && e.shiftKey)) { e.preventDefault(); cx('rpar'); }
});
</script>
</body>
</html>
