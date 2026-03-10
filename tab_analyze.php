<?php
// TAB: PHÂN TÍCH HÀM SỐ — Drop-in replacement for tab-analyze in math.php
// Replace the entire <div id="tab-analyze" ...> block with this content
?>
<!-- ═══════════════════════════════════════════
     TAB: PHÂN TÍCH HÀM SỐ v2
     - GeoGebra graphing embed
     - Math keyboard
     - AI analysis panel
════════════════════════════════════════════ -->
<style>
/* ── ANALYZE v2 STYLES ── */
#tab-analyze * { box-sizing: border-box; }

.az-wrap {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 16px;
  align-items: start;
}
@media (max-width: 960px) {
  .az-wrap { grid-template-columns: 1fr; }
}

/* ── LEFT: Graph + Input ── */
.az-left {}

.az-input-bar {
  display: flex;
  align-items: center;
  gap: 0;
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 14px;
  margin-bottom: 12px;
  overflow: hidden;
  transition: border-color 0.2s;
}
.az-input-bar:focus-within { border-color: var(--accent); }

.az-fn-label {
  padding: 0 14px;
  font-size: 15px; font-weight: 800; font-family: 'Georgia', serif;
  color: var(--accent); white-space: nowrap;
  background: var(--surface2);
  border-right: 1.5px solid var(--border);
  height: 52px; display: flex; align-items: center;
}

.az-input {
  flex: 1;
  background: transparent; border: none; outline: none;
  color: var(--text); font-size: 17px; font-family: 'Courier New', monospace;
  font-weight: 600; padding: 14px 16px; min-width: 0;
  caret-color: var(--accent);
}

.az-go-btn {
  padding: 0 20px; height: 52px;
  background: var(--accent);
  border: none; border-left: 1.5px solid var(--accent);
  color: #fff; font-size: 13px; font-weight: 800;
  cursor: pointer; transition: all 0.15s; white-space: nowrap;
  letter-spacing: 0.3px;
}
.az-go-btn:hover { filter: brightness(1.12); }

/* Quick examples */
.az-examples {
  display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;
  align-items: center;
}
.az-ex-label { font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5px; color: var(--muted); margin-right: 2px; }
.az-ex-chip {
  padding: 4px 12px; border-radius: 20px;
  border: 1.5px solid var(--border); background: var(--surface2);
  font-size: 12px; font-weight: 700; font-family: 'Courier New', monospace;
  cursor: pointer; color: var(--text2); transition: all 0.15s;
}
.az-ex-chip:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

/* ── GeoGebra ── */
.az-graph-wrap {
  border: 1.5px solid var(--border); border-radius: 16px;
  overflow: hidden; position: relative;
  background: #1a1a2e;
}
.az-graph-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 14px; border-bottom: 1px solid var(--border);
  background: var(--surface);
}
.az-graph-title {
  font-size: 12px; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: 0.5px;
  display: flex; align-items: center; gap: 6px;
}
.az-graph-btns { display: flex; gap: 6px; }
.az-graph-btn {
  padding: 4px 10px; border-radius: 6px;
  border: 1px solid var(--border); background: var(--surface2);
  color: var(--muted); font-size: 11px; font-weight: 700; cursor: pointer;
  transition: all 0.15s;
}
.az-graph-btn:hover { border-color: var(--accent); color: var(--accent); }

#azGgbFrame {
  width: 100%; height: 480px; border: none; display: block;
}

/* ── Stats bar ── */
.az-stats {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 8px; margin-top: 12px;
}
@media (max-width: 640px) {
  .az-stats { grid-template-columns: repeat(2, 1fr); }
}
.az-stat {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 12px; padding: 12px 14px;
  transition: border-color 0.2s;
}
.az-stat.loaded { border-color: var(--accent); }
.az-stat-label {
  font-size: 10px; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.6px; color: var(--muted); margin-bottom: 4px;
}
.az-stat-value {
  font-size: 14px; font-weight: 800; color: var(--text);
  font-family: 'Courier New', monospace; line-height: 1.3;
}

/* ── RIGHT: Math Keyboard + AI ── */
.az-right {}

/* Math Keyboard */
.az-kbd-wrap {
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 14px;
}
.az-kbd-header {
  padding: 10px 14px; border-bottom: 1px solid var(--border);
  background: var(--surface2);
  display: flex; align-items: center; justify-content: space-between;
}
.az-kbd-title {
  font-size: 11px; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.6px; color: var(--muted);
}
.az-kbd-tabs {
  display: flex; gap: 3px;
}
.az-kbd-tab {
  padding: 4px 10px; border-radius: 6px; border: none;
  background: transparent; color: var(--muted);
  font-size: 11px; font-weight: 700; cursor: pointer;
  font-family: var(--font); transition: all 0.15s;
}
.az-kbd-tab.active {
  background: var(--accent-soft); color: var(--accent);
}

.az-kbd-body { padding: 10px; }

.az-kbd-grid {
  display: grid; grid-template-columns: repeat(5, 1fr);
  gap: 5px;
}
.az-kbd-key {
  height: 40px; border-radius: 8px;
  border: 1.5px solid var(--border); background: var(--surface2);
  color: var(--text); font-family: 'Georgia', serif;
  font-size: 13px; font-weight: 700; cursor: pointer;
  transition: all 0.12s; display: flex; align-items: center; justify-content: center;
  position: relative; white-space: nowrap;
}
.az-kbd-key:hover { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.2); }
.az-kbd-key:active { transform: translateY(0); box-shadow: none; }
.az-kbd-key.fn { background: var(--surface); border-color: var(--border2); color: var(--accent); font-size: 11px; }
.az-kbd-key.fn:hover { background: var(--accent); color: #fff; }
.az-kbd-key.op { color: var(--gold); border-color: var(--gold-soft); background: var(--gold-soft); }
.az-kbd-key.op:hover { background: var(--gold); color: #fff; border-color: var(--gold); }
.az-kbd-key.act { background: var(--accent); color: #fff; border-color: var(--accent); font-size: 11px; }
.az-kbd-key.act:hover { filter: brightness(1.1); transform: translateY(-1px); }
.az-kbd-key.del-key { background: var(--red-soft); color: var(--red); border-color: var(--red-soft); }
.az-kbd-key.del-key:hover { background: var(--red); color: #fff; }
.az-kbd-key span.sub { font-size: 9px; vertical-align: sub; }
.az-kbd-key span.sup { font-size: 9px; vertical-align: super; }

.az-kbd-panel { display: none; }
.az-kbd-panel.active { display: block; }

/* Current expression preview */
.az-expr-preview {
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 8px; padding: 8px 12px; margin-bottom: 8px;
  font-family: 'Courier New', monospace; font-size: 13px;
  color: var(--text2); min-height: 36px; word-break: break-all;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.az-expr-preview span { flex: 1; }
.az-expr-cursor {
  display: inline-block; width: 2px; height: 16px;
  background: var(--accent); animation: blink 1s step-end infinite; vertical-align: middle;
}
@keyframes blink { 50% { opacity: 0; } }

/* ── AI Result panel ── */
.az-ai-wrap {
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 16px; overflow: hidden;
}
.az-ai-header {
  padding: 10px 14px; border-bottom: 1px solid var(--border);
  background: var(--surface2);
  display: flex; align-items: center; gap: 8px;
}
.az-ai-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--muted); flex-shrink: 0;
  transition: background 0.3s;
}
.az-ai-dot.on { background: var(--green); box-shadow: 0 0 8px var(--green); }
.az-ai-dot.loading {
  background: var(--accent);
  animation: pulse-dot 0.8s ease-in-out infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(1.3)} }

.az-ai-title {
  font-size: 12px; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.5px; color: var(--text2);
}
.az-ai-body {
  padding: 14px 16px; font-size: 13px; line-height: 1.85;
  color: var(--text2); min-height: 100px;
  max-height: 420px; overflow-y: auto;
}
.az-ai-body strong, .az-ai-body b { color: var(--text); }
.az-ai-body .az-step {
  margin-bottom: 10px; padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
}
.az-ai-body .az-step:last-child { border-bottom: none; margin-bottom: 0; }
.az-ai-placeholder {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding: 2.5rem; color: var(--muted);
  gap: 8px; text-align: center;
}
.az-ai-placeholder .icon { font-size: 2.5rem; opacity: 0.3; }
.az-ai-spinner {
  width: 32px; height: 32px; border: 3px solid var(--border2);
  border-top-color: var(--accent); border-radius: 50%;
  animation: spin .7s linear infinite; margin: 0 auto;
}
</style>

<div id="tab-analyze" style="display:none">
  <div class="az-wrap">

    <!-- ── LEFT COLUMN ── -->
    <div class="az-left">

      <!-- Input bar -->
      <div class="az-input-bar" id="azInputBar">
        <span class="az-fn-label">f(x) =</span>
        <input type="text" class="az-input" id="azInput"
          placeholder="x^3 - 3*x^2 + 2"
          oninput="azSyncInput()"
          onkeydown="if(event.key==='Enter')azAnalyze()">
        <button class="az-go-btn" onclick="azAnalyze()">▶ Phân tích</button>
      </div>

      <!-- Quick examples -->
      <div class="az-examples">
        <span class="az-ex-label">Ví dụ:</span>
        <?php foreach([
          'x^2 - 3*x + 2',
          'x^3 - 3*x',
          'sin(x)',
          '1/x',
          'sqrt(x)',
          'e^x',
          'ln(x)',
          'abs(x)',
        ] as $ex): ?>
        <span class="az-ex-chip" onclick="azSetExpr('<?= addslashes($ex) ?>')"><?= htmlspecialchars($ex) ?></span>
        <?php endforeach; ?>
      </div>

      <!-- GeoGebra Graph -->
      <div class="az-graph-wrap">
        <div class="az-graph-header">
          <span class="az-graph-title">
            <span>📐</span> GeoGebra — Vẽ đồ thị chi tiết
          </span>
          <div class="az-graph-btns">
            <button class="az-graph-btn" onclick="azGgbReset()">⌂ Reset</button>
            <button class="az-graph-btn" onclick="azGgbPlot()">▶ Vẽ hàm</button>
            <button class="az-graph-btn" onclick="azGgbFullscreen()">⛶ Phóng to</button>
          </div>
        </div>
        <iframe
          id="azGgbFrame"
          src="https://www.geogebra.org/graphing?lang=vi"
          allowfullscreen
          allow="fullscreen">
        </iframe>
      </div>

      <!-- Stats row -->
      <div class="az-stats" id="azStats">
        <div class="az-stat" id="azStatDomain">
          <div class="az-stat-label">Tập xác định</div>
          <div class="az-stat-value" id="azStatDomainVal">—</div>
        </div>
        <div class="az-stat" id="azStatLimP">
          <div class="az-stat-label">Giới hạn x→+∞</div>
          <div class="az-stat-value" id="azStatLimPVal">—</div>
        </div>
        <div class="az-stat" id="azStatLimN">
          <div class="az-stat-label">Giới hạn x→−∞</div>
          <div class="az-stat-value" id="azStatLimNVal">—</div>
        </div>
        <div class="az-stat" id="azStatY0">
          <div class="az-stat-label">Cắt trục Oy (x=0)</div>
          <div class="az-stat-value" id="azStatY0Val">—</div>
        </div>
      </div>
    </div>

    <!-- ── RIGHT COLUMN ── -->
    <div class="az-right">

      <!-- Math Keyboard -->
      <div class="az-kbd-wrap">
        <div class="az-kbd-header">
          <span class="az-kbd-title">⌨ Bàn phím toán học</span>
          <div class="az-kbd-tabs">
            <button class="az-kbd-tab active" onclick="azKbdTab('basic', this)">Cơ bản</button>
            <button class="az-kbd-tab" onclick="azKbdTab('trig', this)">Lượng giác</button>
            <button class="az-kbd-tab" onclick="azKbdTab('calc', this)">Giải tích</button>
            <button class="az-kbd-tab" onclick="azKbdTab('sym', this)">Ký hiệu</button>
          </div>
        </div>
        <div class="az-kbd-body">

          <!-- Expression preview -->
          <div class="az-expr-preview">
            <span id="azKbdPreview">_</span>
            <button class="az-kbd-key del-key" style="width:36px;height:28px;font-size:14px;flex-shrink:0;" onclick="azKbdDel()">⌫</button>
          </div>

          <!-- BASIC panel -->
          <div class="az-kbd-panel active" id="azKbdBasic">
            <div class="az-kbd-grid">
              <?php
              $basicKeys = [
                ['x',    'x',      ''],
                ['x²',   '^2',     ''],
                ['xⁿ',   '^',      ''],
                ['√x',   'sqrt(',  'fn'],
                ['|x|',  'abs(',   'fn'],
                ['7',    '7',      ''],
                ['8',    '8',      ''],
                ['9',    '9',      ''],
                ['(',    '(',      'op'],
                [')',    ')',      'op'],
                ['4',    '4',      ''],
                ['5',    '5',      ''],
                ['6',    '6',      ''],
                ['×',    '*',      'op'],
                ['÷',    '/',      'op'],
                ['1',    '1',      ''],
                ['2',    '2',      ''],
                ['3',    '3',      ''],
                ['+',    '+',      'op'],
                ['−',    '-',      'op'],
                ['0',    '0',      ''],
                ['.',    '.',      ''],
                ['e',    'e',      'fn'],
                ['π',    'pi',     'fn'],
                ['AC',   '__clear__', 'act'],
              ];
              foreach ($basicKeys as [$label, $val, $cls]):
              ?>
              <button class="az-kbd-key <?= $cls ?>"
                onclick="azKbdInsert('<?= addslashes($val) ?>')"
                title="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- TRIG panel -->
          <div class="az-kbd-panel" id="azKbdTrig">
            <div class="az-kbd-grid">
              <?php
              $trigKeys = [
                ['sin',    'sin(',    'fn'],
                ['cos',    'cos(',    'fn'],
                ['tan',    'tan(',    'fn'],
                ['cot',    '1/tan(',  'fn'],
                ['sec',    '1/cos(',  'fn'],
                ['asin',   'asin(',   'fn'],
                ['acos',   'acos(',   'fn'],
                ['atan',   'atan(',   'fn'],
                ['sinh',   'sinh(',   'fn'],
                ['cosh',   'cosh(',   'fn'],
                ['tanh',   'tanh(',   'fn'],
                ['π',      'pi',      'fn'],
                ['π/2',    'pi/2',    'fn'],
                ['π/4',    'pi/4',    'fn'],
                ['2π',     '2*pi',    'fn'],
                ['x',      'x',       ''],
                ['^',      '^',       'op'],
                ['(',      '(',       'op'],
                [')',       ')',       'op'],
                ['AC',     '__clear__','act'],
              ];
              foreach ($trigKeys as [$label, $val, $cls]):
              ?>
              <button class="az-kbd-key <?= $cls ?>"
                onclick="azKbdInsert('<?= addslashes($val) ?>')"><?= htmlspecialchars($label) ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- CALC panel -->
          <div class="az-kbd-panel" id="azKbdCalc">
            <div class="az-kbd-grid">
              <?php
              $calcKeys = [
                ['ln',     'ln(',     'fn'],
                ['log',    'log(',    'fn'],
                ['log₂',   'log2(',   'fn'],
                ['eˣ',     'exp(',    'fn'],
                ['10ˣ',    '10^(',    'fn'],
                ['√',      'sqrt(',   'fn'],
                ['∛',      'cbrt(',   'fn'],
                ['x²',     '^2',      ''],
                ['x³',     '^3',      ''],
                ['xⁿ',     '^',       'op'],
                ['1/x',    '1/',      'fn'],
                ['|x|',    'abs(',    'fn'],
                ['⌊x⌋',   'floor(',  'fn'],
                ['⌈x⌉',   'ceil(',   'fn'],
                ['round',  'round(',  'fn'],
                ['x',      'x',       ''],
                ['e',      'e',       'fn'],
                ['(',      '(',       'op'],
                [')',       ')',       'op'],
                ['AC',     '__clear__','act'],
              ];
              foreach ($calcKeys as [$label, $val, $cls]):
              ?>
              <button class="az-kbd-key <?= $cls ?>"
                onclick="azKbdInsert('<?= addslashes($val) ?>')"><?= htmlspecialchars($label) ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- SYM panel -->
          <div class="az-kbd-panel" id="azKbdSym">
            <div class="az-kbd-grid">
              <?php
              $symKeys = [
                ['∞',  'Infinity',    'fn'],
                ['π',  'pi',          'fn'],
                ['e',  'e',           'fn'],
                ['i',  'i',           'fn'],
                ['φ',  '(1+sqrt(5))/2','fn'],
                ['≤',  '<=',          'op'],
                ['≥',  '>=',          'op'],
                ['≠',  '!=',          'op'],
                ['+',  '+',           'op'],
                ['−',  '-',           'op'],
                ['×',  '*',           'op'],
                ['÷',  '/',           'op'],
                ['^',  '^',           'op'],
                ['(',  '(',           'op'],
                [')',  ')',           'op'],
                ['max','max(',        'fn'],
                ['min','min(',        'fn'],
                ['mod','%',           'op'],
                [',',  ',',           ''],
                ['AC', '__clear__',   'act'],
              ];
              foreach ($symKeys as [$label, $val, $cls]):
              ?>
              <button class="az-kbd-key <?= $cls ?>"
                onclick="azKbdInsert('<?= addslashes($val) ?>')"><?= htmlspecialchars($label) ?></button>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </div><!-- /az-kbd-wrap -->

      <!-- AI Analysis panel -->
      <div class="az-ai-wrap">
        <div class="az-ai-header">
          <div class="az-ai-dot" id="azAiDot"></div>
          <span class="az-ai-title">Phân tích chi tiết từ AI</span>
        </div>
        <div class="az-ai-body" id="azAiBody">
          <div class="az-ai-placeholder">
            <span class="icon">🧮</span>
            <span style="font-size:13px;">Nhập hàm số và bấm <strong>Phân tích</strong></span>
          </div>
        </div>
      </div>

    </div><!-- /az-right -->
  </div><!-- /az-wrap -->
</div><!-- /tab-analyze -->

<script>
// ══════════════════════════════════════
//  PHÂN TÍCH HÀM SỐ v2
// ══════════════════════════════════════

// ── Keyboard tab switch ──
function azKbdTab(name, btn) {
  document.querySelectorAll('.az-kbd-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.az-kbd-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('azKbd' + name.charAt(0).toUpperCase() + name.slice(1)).classList.add('active');
  btn.classList.add('active');
}

// ── Keyboard insert ──
function azKbdInsert(val) {
  const inp = document.getElementById('azInput');
  if (val === '__clear__') { inp.value = ''; azSyncInput(); return; }
  const start = inp.selectionStart;
  const end   = inp.selectionEnd;
  const cur   = inp.value;
  inp.value   = cur.slice(0, start) + val + cur.slice(end);
  const newPos = start + val.length;
  inp.setSelectionRange(newPos, newPos);
  inp.focus();
  azSyncInput();
}

function azKbdDel() {
  const inp = document.getElementById('azInput');
  const start = inp.selectionStart;
  if (start === 0) return;
  const cur = inp.value;
  inp.value = cur.slice(0, start - 1) + cur.slice(start);
  inp.setSelectionRange(start - 1, start - 1);
  inp.focus();
  azSyncInput();
}

function azSyncInput() {
  const val = document.getElementById('azInput').value;
  const preview = document.getElementById('azKbdPreview');
  preview.innerHTML = (val || '<span style="color:var(--muted)">Nhập biểu thức...</span>')
    + '<span class="az-expr-cursor"></span>';
}

// ── Set expression from chip ──
function azSetExpr(expr) {
  document.getElementById('azInput').value = expr;
  azSyncInput();
}

// ── GeoGebra helpers ──
function azGgbReset() {
  const f = document.getElementById('azGgbFrame');
  f.src = f.src;
}

function azGgbPlot() {
  const expr = document.getElementById('azInput').value.trim();
  if (!expr) return;
  // Encode as GeoGebra URL with the function pre-typed
  const encoded = encodeURIComponent(expr);
  document.getElementById('azGgbFrame').src =
    `https://www.geogebra.org/graphing?lang=vi#${encoded}`;
}

function azGgbFullscreen() {
  const el = document.getElementById('azGgbFrame');
  if (el.requestFullscreen) el.requestFullscreen();
  else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
}

// ── Local math analysis ──
function azLocalAnalyze(expr) {
  const tryEval = (x) => {
    try { return math.evaluate(expr, { x }); } catch { return null; }
  };

  // Domain heuristic
  let domain = 'ℝ (toàn bộ trục số)';
  if (/sqrt\s*\(/.test(expr)) domain = 'Tùy biểu thức trong căn (≥ 0)';
  if (/\/\s*x\b/.test(expr) || /\/\s*\(?\s*x\s*\)?/.test(expr)) domain = 'x ≠ 0';
  if (/\b(ln|log)\s*\(/.test(expr)) domain = 'x > 0';
  if (/tan\s*\(/.test(expr)) domain = 'x ≠ π/2 + kπ';

  const fmt = v => {
    if (v === null) return 'Không xác định';
    if (!isFinite(v)) return v > 0 ? '+∞' : '−∞';
    return v.toFixed(4);
  };

  return {
    domain,
    limitInf:    fmt(tryEval(1e9)),
    limitNegInf: fmt(tryEval(-1e9)),
    yIntercept:  fmt(tryEval(0)),
  };
}

// ── Main analyze ──
async function azAnalyze() {
  const expr = document.getElementById('azInput').value.trim();
  if (!expr) return;

  // Update stats immediately with local math
  const local = azLocalAnalyze(expr);
  document.getElementById('azStatDomainVal').textContent = local.domain;
  document.getElementById('azStatLimPVal').textContent   = local.limitInf;
  document.getElementById('azStatLimNVal').textContent   = local.limitNegInf;
  document.getElementById('azStatY0Val').textContent     = local.yIntercept;
  document.querySelectorAll('.az-stat').forEach(s => s.classList.add('loaded'));

  // Plot in GeoGebra
  const encoded = encodeURIComponent(expr);
  document.getElementById('azGgbFrame').src =
    `https://www.geogebra.org/graphing?lang=vi#${encoded}`;

  // AI analysis
  const dot  = document.getElementById('azAiDot');
  const body = document.getElementById('azAiBody');
  dot.className = 'az-ai-dot loading';
  body.innerHTML = '<div class="az-ai-spinner"></div>';

  try {
    const res  = await fetch('ai_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type: 'math_analyze', expr })
    });
    const data = await res.json();
    dot.className = 'az-ai-dot on';

    let html = (data.result || 'Không phân tích được.')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\n\n/g, '</p><p>')
      .replace(/\n/g, '<br>');

    body.innerHTML = '<p>' + html + '</p>';

    // Re-render KaTeX if available
    if (typeof renderMathInElement !== 'undefined') {
      renderMathInElement(body, {
        delimiters: [
          { left: '$$', right: '$$', display: true },
          { left: '$',  right: '$',  display: false }
        ],
        throwOnError: false
      });
    }
  } catch(e) {
    dot.className = 'az-ai-dot';
    body.innerHTML = '<span style="color:var(--red);font-size:13px;">⚠ Lỗi kết nối AI. Kết quả cục bộ đã hiển thị ở trên.</span>';
  }
}

// Init preview
azSyncInput();
</script>
