<?php
require_once __DIR__ . "/db.php";
requireLogin();
$uid = $_SESSION['user_id'];

// Load danh sách mind maps
$maps = $db->query("SELECT id, title, topic, created_at FROM mindmaps WHERE user_id=$uid ORDER BY created_at DESC LIMIT 20");

// Lấy data 1 mind map cụ thể
if (isset($_GET['load'])) {
    $mid = (int)$_GET['load'];
    $row = $db->query("SELECT * FROM mindmaps WHERE id=$mid AND user_id=$uid")->fetchArray(SQLITE3_ASSOC);
    if ($row) { echo json_encode(['ok'=>true,'data'=>json_decode($row['data'],true),'title'=>$row['title'],'topic'=>$row['topic']]); exit; }
    echo json_encode(['ok'=>false]); exit;
}

// Xoá mind map
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $mid = (int)$_POST['id'];
    $db->exec("DELETE FROM mindmaps WHERE id=$mid AND user_id=$uid");
    echo json_encode(['ok'=>true]); exit;
}

// Lưu mind map
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $title = trim($_POST['title'] ?? 'Mind Map');
    $topic = trim($_POST['topic'] ?? '');
    $data  = $_POST['data'] ?? '{}';
    $mid   = (int)($_POST['id'] ?? 0);
    if ($mid) {
        $stmt = $db->prepare('UPDATE mindmaps SET title=:t,data=:d WHERE id=:id AND user_id=:uid');
        $stmt->bindValue(':t', $title); $stmt->bindValue(':d', $data);
        $stmt->bindValue(':id', $mid);  $stmt->bindValue(':uid', $uid);
        $stmt->execute();
        echo json_encode(['ok'=>true,'id'=>$mid]); exit;
    } else {
        $stmt = $db->prepare('INSERT INTO mindmaps (user_id,title,topic,data) VALUES (:uid,:t,:top,:d)');
        $stmt->bindValue(':uid', $uid); $stmt->bindValue(':t', $title);
        $stmt->bindValue(':top', $topic); $stmt->bindValue(':d', $data);
        $stmt->execute();
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertRowID()]); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mind Map AI — MindSpark</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js"></script>
<style>
/* ── LAYOUT ── */
.mm-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 0;
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  background: var(--surface);
  height: calc(100vh - 180px);
  min-height: 560px;
}
@media(max-width:768px){
  .mm-layout { grid-template-columns:1fr; height:auto; }
  .mm-sidebar { max-height:300px; overflow-y:auto; border-right:none!important; border-bottom:1px solid var(--border); }
  #mmCanvas { height:420px!important; }
}

/* ── SIDEBAR ── */
.mm-sidebar {
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  background: var(--surface);
}
.mm-sidebar-head {
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  font-size: 12px; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: 0.5px;
}
.mm-generate-area { padding: 12px; }
.mm-generate-area textarea {
  width: 100%; min-height: 70px; resize: vertical;
  background: var(--surface2); border: 1.5px solid var(--border);
  border-radius: 10px; color: var(--text); font-family: var(--font);
  font-size: 13px; padding: 10px 12px; outline: none;
  transition: border-color 0.15s;
}
.mm-generate-area textarea:focus { border-color: var(--accent); }

.mm-style-row {
  display: flex; gap: 4px; margin: 8px 0;
}
.mm-style-btn {
  flex:1; padding: 6px 4px; border-radius: 8px; border: 1.5px solid var(--border);
  background: var(--surface2); color: var(--text2); font-size: 11px; font-weight: 700;
  cursor: pointer; transition: all 0.15s; text-align: center;
}
.mm-style-btn:hover, .mm-style-btn.active {
  border-color: var(--accent); background: var(--accent-soft); color: var(--accent);
}

.mm-depth-row {
  display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
  font-size: 12px; color: var(--muted);
}
.mm-depth-row input[type=range] {
  flex:1; accent-color: var(--accent);
}

/* ── SAVED LIST ── */
.mm-list { flex:1; overflow-y:auto; padding: 8px; }
.mm-list-item {
  display: flex; align-items: center; gap: 8px; padding: 8px 10px;
  border-radius: 10px; border: 1px solid var(--border);
  background: var(--surface2); margin-bottom: 6px; cursor: pointer;
  transition: border-color 0.15s;
}
.mm-list-item:hover { border-color: var(--accent); }
.mm-list-item.active { border-color: var(--accent); background: var(--accent-soft); }
.mm-list-icon { font-size: 18px; flex-shrink:0; }
.mm-list-info { flex:1; min-width:0; }
.mm-list-title { font-size: 12px; font-weight: 700; color: var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mm-list-sub { font-size: 10px; color: var(--muted); }
.mm-list-del { background:none; border:none; color:var(--muted); cursor:pointer; font-size:13px; padding:2px; flex-shrink:0; }
.mm-list-del:hover { color: var(--red); }

/* ── CANVAS AREA ── */
.mm-canvas-wrap {
  position: relative; flex:1; background: var(--bg);
  display: flex; flex-direction: column; overflow: hidden;
}
.mm-toolbar {
  display: flex; align-items: center; gap: 6px; padding: 8px 12px;
  border-bottom: 1px solid var(--border); background: var(--surface); flex-wrap: wrap;
}
.mm-title-input {
  flex:1; background: transparent; border: none; color: var(--text);
  font-family: var(--font); font-size: 14px; font-weight: 700; outline: none;
  min-width: 0;
}
.mm-title-input::placeholder { color: var(--muted); }

#mmCanvas {
  flex:1; width:100%; overflow: hidden;
}

/* ── NODES ── */
.mm-node circle {
  stroke-width: 2.5;
  cursor: pointer;
  transition: r 0.2s;
}
.mm-node circle:hover { filter: brightness(1.2); }
.mm-node text {
  font-family: 'Plus Jakarta Sans', sans-serif;
  pointer-events: none;
  dominant-baseline: middle;
}
.mm-link {
  fill: none; stroke-width: 2; opacity: 0.5;
}

/* ── STATUS BAR ── */
.mm-statusbar {
  display: flex; align-items: center; gap: 12px;
  padding: 5px 12px; border-top: 1px solid var(--border);
  background: var(--surface); font-size: 11px; color: var(--muted); font-family: var(--mono);
}
.mm-statusbar span { display: flex; align-items: center; gap: 4px; }

/* ── LOADING ── */
.mm-loading-overlay {
  display: none; position: absolute; inset: 0;
  background: rgba(13,13,18,0.7); backdrop-filter: blur(4px);
  align-items: center; justify-content: center; flex-direction: column; gap: 12px;
  z-index: 10;
}
.mm-loading-overlay.show { display: flex; }
.mm-loading-text { color: #fff; font-size: 14px; font-weight: 600; }
.mm-spinner {
  width: 40px; height: 40px; border-radius: 50%;
  border: 3px solid rgba(255,255,255,0.2);
  border-top-color: var(--accent);
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── EMPTY STATE ── */
.mm-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 100%; color: var(--muted); gap: 12px;
}
.mm-empty-icon { font-size: 3rem; opacity: 0.4; }
.mm-empty-text { font-size: 14px; font-weight: 600; }

/* ── NODE TOOLTIP ── */
.mm-tooltip {
  position: absolute; background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 8px 12px; font-size: 12px; font-weight: 600;
  color: var(--text); pointer-events: none; box-shadow: var(--shadow-lg);
  max-width: 200px; z-index: 20; display: none;
}
</style>
</head>
<body>
<?php require_once __DIR__ . "/db.php"; include 'navbar.php'; ?>
<div class="page" style="padding-bottom:1rem;">
  <div class="page-header" style="margin-bottom:1.2rem;">
    <div class="page-eyebrow">AI Tools</div>
    <h1 class="page-title">🗺️ Mind Map AI</h1>
    <div class="page-sub">Nhập chủ đề — AI tự động xây dựng sơ đồ tư duy tương tác</div>
  </div>

  <div class="mm-layout">

    <!-- SIDEBAR -->
    <div class="mm-sidebar">
      <div class="mm-sidebar-head">✨ Tạo mới</div>

      <div class="mm-generate-area">
        <textarea id="mmTopic" placeholder="Nhập chủ đề...&#10;VD: Quang hợp, Cách mạng Pháp, Machine Learning, Blockchain..."></textarea>

        <div class="mm-depth-row">
          <span>Độ sâu:</span>
          <input type="range" id="mmDepth" min="1" max="3" value="2" oninput="document.getElementById('mmDepthVal').textContent=this.value">
          <span id="mmDepthVal" style="font-weight:700;color:var(--accent);min-width:12px;">2</span>
        </div>

        <div class="mm-style-row">
          <button class="mm-style-btn active" data-style="radial" onclick="setStyle('radial')">🌐 Radial</button>
          <button class="mm-style-btn" data-style="tree" onclick="setStyle('tree')">🌳 Tree</button>
          <button class="mm-style-btn" data-style="force" onclick="setStyle('force')">⚛️ Force</button>
        </div>

        <button class="btn btn-primary btn-full" onclick="generateMap()">
          ✨ Tạo Mind Map
        </button>
      </div>

      <div class="mm-sidebar-head" style="border-top:1px solid var(--border);">📁 Đã lưu</div>
      <div class="mm-list" id="mmSavedList">
        <?php
        $hasMaps = false;
        while ($row = $maps->fetchArray(SQLITE3_ASSOC)):
          $hasMaps = true;
          $ago = '';
          $diff = time() - strtotime($row['created_at']);
          if ($diff < 86400) $ago = 'hôm nay';
          else $ago = date('d/m/Y', strtotime($row['created_at']));
        ?>
        <div class="mm-list-item" id="mitem<?= $row['id'] ?>" onclick="loadMap(<?= $row['id'] ?>)">
          <div class="mm-list-icon">🗺️</div>
          <div class="mm-list-info">
            <div class="mm-list-title"><?= htmlspecialchars($row['title']) ?></div>
            <div class="mm-list-sub"><?= htmlspecialchars($row['topic']) ?> · <?= $ago ?></div>
          </div>
          <button class="mm-list-del" onclick="event.stopPropagation();deleteMap(<?= $row['id'] ?>)" title="Xoá">✕</button>
        </div>
        <?php endwhile; ?>
        <?php if (!$hasMaps): ?>
        <div style="text-align:center;color:var(--muted);font-size:12px;padding:1rem 0.5rem;">
          Chưa có mind map nào được lưu.
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CANVAS -->
    <div class="mm-canvas-wrap">

      <div class="mm-toolbar">
        <input type="text" id="mmTitleInput" class="mm-title-input" placeholder="Tên mind map...">
        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;">
          <button class="btn btn-ghost btn-sm" onclick="mmZoomIn()">＋ Zoom</button>
          <button class="btn btn-ghost btn-sm" onclick="mmZoomOut()">－ Zoom</button>
          <button class="btn btn-ghost btn-sm" onclick="mmFit()">⊡ Fit</button>
          <button class="btn btn-ghost btn-sm" onclick="exportPNG()">📸 Xuất PNG</button>
          <button class="btn btn-primary btn-sm" onclick="saveMap()" id="mmSaveBtn" style="display:none;">💾 Lưu</button>
        </div>
      </div>

      <svg id="mmCanvas">
        <g id="mmRoot"></g>
      </svg>

      <div class="mm-loading-overlay" id="mmLoading">
        <div class="mm-spinner"></div>
        <div class="mm-loading-text" id="mmLoadingText">AI đang tạo mind map...</div>
      </div>

      <div class="mm-empty" id="mmEmpty">
        <div class="mm-empty-icon">🗺️</div>
        <div class="mm-empty-text">Nhập chủ đề và nhấn "Tạo Mind Map"</div>
        <div style="font-size:12px;color:var(--muted);">AI sẽ tự động xây dựng sơ đồ tư duy cho bạn</div>
      </div>

      <div class="mm-statusbar">
        <span>🔵 <span id="mmNodeCount">0</span> nodes</span>
        <span>🔗 <span id="mmLinkCount">0</span> links</span>
        <span id="mmTopicLabel" style="margin-left:auto;color:var(--accent);font-weight:700;"></span>
      </div>
    </div>

  </div>
</div>

<div class="mm-tooltip" id="mmTooltip"></div>

<script>
// ══════════════════════════════════════
//  MIND MAP ENGINE
// ══════════════════════════════════════
let currentStyle = 'radial';
let currentMapId = null;
let currentData  = null;
let zoomBehavior = null;

const svg   = d3.select('#mmCanvas');
const root  = d3.select('#mmRoot');
const tooltip = document.getElementById('mmTooltip');

// Color palette per depth
const DEPTH_COLORS = ['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4'];
const DEPTH_SIZES  = [36, 26, 20, 16, 14];

function setStyle(s) {
  currentStyle = s;
  document.querySelectorAll('.mm-style-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.style === s);
  });
  if (currentData) renderMap(currentData);
}

// ── Generate via AI ──
async function generateMap() {
  const topic = document.getElementById('mmTopic').value.trim();
  if (!topic) { alert('Vui lòng nhập chủ đề!'); return; }
  const depth = parseInt(document.getElementById('mmDepth').value);

  document.getElementById('mmEmpty').style.display = 'none';
  document.getElementById('mmLoading').classList.add('show');
  document.getElementById('mmLoadingText').textContent = 'AI đang phân tích "' + topic + '"...';

  try {
    const res = await fetch('ai_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type: 'mindmap', topic, depth })
    });
    const data = await res.json();
    if (!data.tree) throw new Error('Không nhận được dữ liệu từ AI');

    currentData = data.tree;
    currentMapId = null;
    document.getElementById('mmTitleInput').value = 'Mind Map: ' + topic;
    document.getElementById('mmSaveBtn').style.display = '';
    renderMap(currentData);
    document.getElementById('mmTopicLabel').textContent = '🗺️ ' + topic;

  } catch(e) {
    alert('Lỗi tạo mind map: ' + e.message);
    document.getElementById('mmEmpty').style.display = 'flex';
  } finally {
    document.getElementById('mmLoading').classList.remove('show');
  }
}

// ── Render ──
function renderMap(data) {
  root.selectAll('*').remove();
  if (currentStyle === 'radial') renderRadial(data);
  else if (currentStyle === 'tree') renderTree(data);
  else renderForce(data);
}

function initZoom() {
  zoomBehavior = d3.zoom()
    .scaleExtent([0.2, 4])
    .on('zoom', e => root.attr('transform', e.transform));
  svg.call(zoomBehavior);
}

function mmFit() {
  const svgEl = document.getElementById('mmCanvas');
  const W = svgEl.clientWidth, H = svgEl.clientHeight;
  if (zoomBehavior) svg.transition().duration(600).call(zoomBehavior.transform, d3.zoomIdentity.translate(W/2, H/2));
}

function mmZoomIn()  { if (zoomBehavior) svg.transition().duration(300).call(zoomBehavior.scaleBy, 1.3); }
function mmZoomOut() { if (zoomBehavior) svg.transition().duration(300).call(zoomBehavior.scaleBy, 0.77); }

// ── Flatten tree to nodes/links ──
function flatten(node, parent = null, depth = 0) {
  const nodes = [{ ...node, depth, parent: parent?.name || null }];
  const links = parent ? [{ source: parent.name, target: node.name }] : [];
  if (node.children) {
    for (const child of node.children) {
      const { nodes: cn, links: cl } = flatten(child, node, depth + 1);
      nodes.push(...cn); links.push(...cl);
    }
  }
  return { nodes, links };
}

// ── RADIAL layout ──
function renderRadial(data) {
  const svgEl = document.getElementById('mmCanvas');
  const W = svgEl.clientWidth || 800, H = svgEl.clientHeight || 500;
  initZoom();
  svg.call(zoomBehavior.transform, d3.zoomIdentity.translate(W/2, H/2));

  const hier = d3.hierarchy(data);
  const layout = d3.tree().size([2 * Math.PI, Math.min(W, H) / 2 - 80]).separation((a, b) => (a.parent === b.parent ? 1 : 1.5) / a.depth);
  layout(hier);

  // Links
  root.append('g').selectAll('path')
    .data(hier.links()).join('path')
    .attr('class', 'mm-link')
    .attr('d', d3.linkRadial().angle(d => d.x).radius(d => d.y))
    .attr('stroke', d => DEPTH_COLORS[d.source.depth % DEPTH_COLORS.length])
    .style('stroke-dasharray', function() { return this.getTotalLength(); })
    .style('stroke-dashoffset', function() { return this.getTotalLength(); })
    .transition().duration(800).delay((d,i) => i * 30)
    .style('stroke-dashoffset', 0);

  // Nodes
  const node = root.append('g').selectAll('g')
    .data(hier.descendants()).join('g')
    .attr('class', 'mm-node')
    .attr('transform', d => `rotate(${d.x * 180 / Math.PI - 90}) translate(${d.y},0)`)
    .style('opacity', 0)
    .call(g => g.transition().duration(500).delay((d,i) => i * 40).style('opacity', 1));

  node.append('circle')
    .attr('r', d => DEPTH_SIZES[Math.min(d.depth, DEPTH_SIZES.length - 1)])
    .attr('fill', d => DEPTH_COLORS[d.depth % DEPTH_COLORS.length])
    .attr('fill-opacity', d => 1 - d.depth * 0.15)
    .attr('stroke', d => DEPTH_COLORS[d.depth % DEPTH_COLORS.length])
    .on('mouseover', (event, d) => showTooltip(event, d.data.name))
    .on('mouseout', hideTooltip);

  node.append('text')
    .attr('transform', d => `rotate(${d.x >= Math.PI ? 180 : 0})`)
    .attr('text-anchor', d => d.x >= Math.PI ? 'end' : 'start')
    .attr('x', d => d.x >= Math.PI ? -(DEPTH_SIZES[Math.min(d.depth, DEPTH_SIZES.length-1)] + 5) : (DEPTH_SIZES[Math.min(d.depth, DEPTH_SIZES.length-1)] + 5))
    .attr('fill', 'var(--text)')
    .attr('font-size', d => [13, 12, 11, 10, 9][Math.min(d.depth, 4)])
    .attr('font-weight', d => d.depth < 2 ? '700' : '500')
    .text(d => d.data.name);

  updateStats(hier);
}

// ── TREE layout ──
function renderTree(data) {
  const svgEl = document.getElementById('mmCanvas');
  const W = svgEl.clientWidth || 800, H = svgEl.clientHeight || 500;
  initZoom();
  svg.call(zoomBehavior.transform, d3.zoomIdentity.translate(80, H/2));

  const hier = d3.hierarchy(data);
  d3.tree().nodeSize([40, 180])(hier);

  root.append('g').selectAll('path')
    .data(hier.links()).join('path')
    .attr('class', 'mm-link')
    .attr('d', d3.linkHorizontal().x(d => d.y).y(d => d.x))
    .attr('stroke', d => DEPTH_COLORS[d.source.depth % DEPTH_COLORS.length])
    .style('stroke-dasharray', function() { return this.getTotalLength(); })
    .style('stroke-dashoffset', function() { return this.getTotalLength(); })
    .transition().duration(700).delay((d,i) => i * 25)
    .style('stroke-dashoffset', 0);

  const node = root.append('g').selectAll('g')
    .data(hier.descendants()).join('g')
    .attr('class', 'mm-node')
    .attr('transform', d => `translate(${d.y},${d.x})`)
    .style('opacity', 0)
    .call(g => g.transition().duration(400).delay((d,i) => i * 35).style('opacity', 1));

  node.append('circle')
    .attr('r', d => DEPTH_SIZES[Math.min(d.depth, DEPTH_SIZES.length-1)])
    .attr('fill', d => DEPTH_COLORS[d.depth % DEPTH_COLORS.length])
    .attr('fill-opacity', 0.85)
    .attr('stroke', d => DEPTH_COLORS[d.depth % DEPTH_COLORS.length])
    .on('mouseover', (event, d) => showTooltip(event, d.data.name))
    .on('mouseout', hideTooltip);

  node.append('text')
    .attr('dy', '0.31em')
    .attr('x', d => d.children ? -(DEPTH_SIZES[Math.min(d.depth, DEPTH_SIZES.length-1)] + 6) : (DEPTH_SIZES[Math.min(d.depth, DEPTH_SIZES.length-1)] + 6))
    .attr('text-anchor', d => d.children ? 'end' : 'start')
    .attr('fill', 'var(--text)')
    .attr('font-size', d => [13, 12, 11, 10, 9][Math.min(d.depth, 4)])
    .attr('font-weight', d => d.depth < 2 ? '700' : '500')
    .text(d => d.data.name);

  updateStats(hier);
}

// ── FORCE layout ──
function renderForce(data) {
  const svgEl = document.getElementById('mmCanvas');
  const W = svgEl.clientWidth || 800, H = svgEl.clientHeight || 500;
  initZoom();
  svg.call(zoomBehavior.transform, d3.zoomIdentity.translate(W/2, H/2));

  const hier = d3.hierarchy(data);
  const { nodes, links } = flatten(data);

  const nodeMap = {};
  nodes.forEach(n => nodeMap[n.name] = n);

  const sim = d3.forceSimulation(nodes)
    .force('link', d3.forceLink(links).id(d => d.name).distance(d => 80 + d.source.depth * 20).strength(0.8))
    .force('charge', d3.forceManyBody().strength(-300))
    .force('center', d3.forceCenter(0, 0))
    .force('collision', d3.forceCollide(40));

  const link = root.append('g').selectAll('line')
    .data(links).join('line')
    .attr('class', 'mm-link')
    .attr('stroke', d => DEPTH_COLORS[(nodeMap[d.source]?.depth || 0) % DEPTH_COLORS.length]);

  const node = root.append('g').selectAll('g')
    .data(nodes).join('g')
    .attr('class', 'mm-node')
    .call(d3.drag()
      .on('start', (event, d) => { if (!event.active) sim.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
      .on('drag',  (event, d) => { d.fx = event.x; d.fy = event.y; })
      .on('end',   (event, d) => { if (!event.active) sim.alphaTarget(0); d.fx = null; d.fy = null; }));

  node.append('circle')
    .attr('r', d => DEPTH_SIZES[Math.min(d.depth, DEPTH_SIZES.length-1)])
    .attr('fill', d => DEPTH_COLORS[d.depth % DEPTH_COLORS.length])
    .attr('fill-opacity', 0.85)
    .attr('stroke', d => DEPTH_COLORS[d.depth % DEPTH_COLORS.length])
    .on('mouseover', (event, d) => showTooltip(event, d.name))
    .on('mouseout', hideTooltip);

  node.append('text')
    .attr('text-anchor', 'middle')
    .attr('dy', '0.35em')
    .attr('fill', 'var(--text)')
    .attr('font-size', d => [13, 12, 11, 10, 9][Math.min(d.depth, 4)])
    .attr('font-weight', d => d.depth < 2 ? '700' : '500')
    .text(d => d.name.length > 12 ? d.name.slice(0, 10) + '…' : d.name);

  sim.on('tick', () => {
    link.attr('x1', d => d.source.x).attr('y1', d => d.source.y)
        .attr('x2', d => d.target.x).attr('y2', d => d.target.y);
    node.attr('transform', d => `translate(${d.x},${d.y})`);
  });

  document.getElementById('mmNodeCount').textContent = nodes.length;
  document.getElementById('mmLinkCount').textContent = links.length;
}

function updateStats(hier) {
  document.getElementById('mmNodeCount').textContent = hier.descendants().length;
  document.getElementById('mmLinkCount').textContent = hier.links().length;
}

// ── Tooltip ──
function showTooltip(event, text) {
  tooltip.textContent = text;
  tooltip.style.display = 'block';
  const svgRect = document.getElementById('mmCanvas').getBoundingClientRect();
  tooltip.style.left = (event.clientX - svgRect.left + 12) + 'px';
  tooltip.style.top  = (event.clientY - svgRect.top  - 10) + 'px';
}
function hideTooltip() { tooltip.style.display = 'none'; }

// ── Save / Load / Delete ──
async function saveMap() {
  const title = document.getElementById('mmTitleInput').value.trim() || 'Mind Map';
  const topic = document.getElementById('mmTopic').value.trim();
  if (!currentData) return;
  try {
    const res = await fetch('mindmap.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=save&title=${encodeURIComponent(title)}&topic=${encodeURIComponent(topic)}&data=${encodeURIComponent(JSON.stringify(currentData))}&id=${currentMapId || 0}`
    });
    const result = await res.json();
    if (result.ok) {
      currentMapId = result.id;
      // Refresh list
      addToSidebar(result.id, title, topic);
      document.getElementById('mmSaveBtn').textContent = '✅ Đã lưu';
      setTimeout(() => document.getElementById('mmSaveBtn').textContent = '💾 Lưu', 2000);
    }
  } catch(e) {}
}

async function loadMap(id) {
  document.querySelectorAll('.mm-list-item').forEach(el => el.classList.remove('active'));
  document.getElementById('mitem' + id)?.classList.add('active');
  document.getElementById('mmLoading').classList.add('show');
  document.getElementById('mmLoadingText').textContent = 'Đang tải mind map...';
  try {
    const res = await fetch('mindmap.php?load=' + id);
    const result = await res.json();
    if (result.ok) {
      currentData = result.data; currentMapId = id;
      document.getElementById('mmTitleInput').value = result.title;
      document.getElementById('mmTopic').value = result.topic;
      document.getElementById('mmTopicLabel').textContent = '🗺️ ' + result.topic;
      document.getElementById('mmEmpty').style.display = 'none';
      document.getElementById('mmSaveBtn').style.display = '';
      renderMap(currentData);
    }
  } catch(e) {}
  document.getElementById('mmLoading').classList.remove('show');
}

async function deleteMap(id) {
  if (!confirm('Xoá mind map này?')) return;
  await fetch('mindmap.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=delete&id=${id}`
  });
  document.getElementById('mitem' + id)?.remove();
  if (currentMapId === id) { currentMapId = null; root.selectAll('*').remove(); document.getElementById('mmEmpty').style.display = 'flex'; }
}

function addToSidebar(id, title, topic) {
  const existing = document.getElementById('mitem' + id);
  if (existing) { existing.querySelector('.mm-list-title').textContent = title; return; }
  const list = document.getElementById('mmSavedList');
  const el = document.createElement('div');
  el.className = 'mm-list-item active'; el.id = 'mitem' + id;
  el.innerHTML = `<div class="mm-list-icon">🗺️</div><div class="mm-list-info"><div class="mm-list-title">${title}</div><div class="mm-list-sub">${topic} · hôm nay</div></div><button class="mm-list-del" onclick="event.stopPropagation();deleteMap(${id})">✕</button>`;
  el.onclick = () => loadMap(id);
  list.prepend(el);
  document.querySelectorAll('.mm-list-item').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
}

// ── Export PNG ──
function exportPNG() {
  const svgEl = document.getElementById('mmCanvas');
  const serializer = new XMLSerializer();
  const svgStr = serializer.serializeToString(svgEl);
  const canvas = document.createElement('canvas');
  canvas.width = svgEl.clientWidth * 2; canvas.height = svgEl.clientHeight * 2;
  const ctx2 = canvas.getContext('2d');
  ctx2.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim() || '#0d0d12';
  ctx2.fillRect(0, 0, canvas.width, canvas.height);
  const img = new Image();
  img.onload = () => { ctx2.drawImage(img, 0, 0, canvas.width, canvas.height); const a = document.createElement('a'); a.download = 'mindmap.png'; a.href = canvas.toDataURL(); a.click(); };
  img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgStr)));
}

// Resize observer
new ResizeObserver(() => { if (currentData) renderMap(currentData); }).observe(document.getElementById('mmCanvas'));
</script>
</body>
</html>
