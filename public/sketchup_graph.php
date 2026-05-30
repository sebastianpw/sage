<?php
// Exact clone of the graph environment from sceki adapted for SketchUp
?>
<style>
.skgr-topbar { flex-shrink:0; display:flex; align-items:center; gap:6px; padding:6px 8px; border-bottom:1px solid var(--border); background:var(--surface); }
.skgr-topbar input[type=search] { flex:1; padding:5px 8px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.72rem; outline:none; transition:border-color 0.15s; }
.skgr-topbar input[type=search]:focus { border-color:var(--purple); }
.skgr-btn { flex-shrink:0; padding:4px 8px; background:transparent; border:1px solid var(--border); border-radius:var(--radius); color:var(--text-dim); font-family:var(--mono); font-size:0.65rem; font-weight:700; cursor:pointer; transition:all 0.15s; white-space:nowrap; display:flex; align-items:center; gap:4px; }
.skgr-btn:hover { border-color:var(--purple); color:var(--purple); }
.skgr-btn.running { border-color:var(--amber); color:var(--amber); }

#skgr-node-panel { position:absolute; top:8px; right:8px; z-index:50; width:200px; background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); padding:12px; box-shadow:0 4px 20px rgba(0,0,0,0.4); display:none; flex-direction:column; gap:8px; }
.skgr-panel-name { font-family:var(--mono); font-size:0.8rem; font-weight:700; color:var(--text-bright); word-break:break-word; line-height:1.3; }
.skgr-panel-type { font-family:var(--mono); font-size:0.62rem; color:var(--purple); text-transform:uppercase; letter-spacing:1px; }
.skgr-panel-actions { display:flex; flex-direction:column; gap:5px; }
.skgr-panel-btn { width:100%; padding:6px 8px; background:transparent; border:1px solid var(--border); border-radius:var(--radius); color:var(--text-dim); font-family:var(--mono); font-size:0.68rem; font-weight:700; cursor:pointer; transition:all 0.15s; text-align:left; display:flex; align-items:center; gap:6px; }
.skgr-panel-btn:hover { border-color:var(--border-glow); color:var(--text); }
.skgr-panel-btn.btn-add-subpot { border-color:var(--purple); color:var(--purple); background:var(--purple-dim); }
.skgr-panel-btn.btn-add-subpot:hover { background:var(--purple); color:#fff; }
.skgr-panel-btn.btn-already-added { border-color:var(--green); color:var(--green); background:var(--green-dim); cursor:default; }
.skgr-panel-close { position:absolute; top:6px; right:8px; background:none; border:none; color:var(--text-dim); font-size:1rem; cursor:pointer; line-height:1; padding:2px 4px; }
.skgr-panel-close:hover { color:var(--text); }

.skgr-stats { font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); padding:3px 8px 4px; border-bottom:1px solid var(--border); flex-shrink:0; display:flex; gap:12px; align-items:center; background:var(--surface); }
.skgr-stats span { display:flex; align-items:center; gap:3px; }
.skgr-stats strong { color:var(--text); }

#skgr-details-bg { position:fixed; inset:0; background:rgba(0,0,0,0.78); backdrop-filter:blur(2px); z-index:99000; display:none; align-items:flex-start; justify-content:center; padding:20px; overflow-y:auto; }
#skgr-details-bg.open { display:flex; }
.skgr-details-inner { background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:100%; max-width:680px; margin:auto; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.6); display:flex; flex-direction:column; }
.skgr-details-header { padding:10px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; background:var(--card); flex-shrink:0; position:sticky; top:0; z-index:2; }
.skgr-details-nav { background:none; border:1px solid var(--border); color:var(--text-dim); border-radius:5px; padding:3px 8px; cursor:pointer; font-size:1rem; line-height:1; opacity:0.35; }
.skgr-details-nav:not(:disabled) { opacity:1; }
.skgr-details-title { font-family:var(--mono); font-weight:700; font-size:0.9rem; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-bright); }
.skgr-details-type { font-family:var(--mono); font-size:0.65rem; font-weight:700; padding:2px 7px; border-radius:8px; flex-shrink:0; background:var(--purple-dim); color:var(--purple); border:1px solid rgba(167,139,250,0.3); }
.skgr-details-close { background:none; border:none; color:var(--text-dim); font-size:1.4rem; cursor:pointer; line-height:1; padding:2px 6px; border-radius:4px; flex-shrink:0; }
.skgr-details-close:hover { color:var(--text); }
.skgr-details-body { padding:18px 22px; overflow-y:auto; flex:1; font-size:0.9rem; line-height:1.7; color:var(--text); max-height:70vh; }
.skgr-details-body h1 { font-size:1.3rem; border-bottom:1px solid var(--border); padding-bottom:5px; }
.skgr-details-body h2 { font-size:1.05rem; border-bottom:1px solid var(--border); padding-bottom:4px; }
.skgr-details-body p, .skgr-details-body ul, .skgr-details-body ol { margin:0 0 0.8em; }
.skgr-details-body pre { background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:10px 12px; overflow-x:auto; margin:0 0 0.8em; }
.skgr-details-subpot-row { flex-shrink:0; padding:10px 14px; border-top:1px solid var(--border); background:var(--card); display:flex; align-items:center; gap:8px; }
.skgr-details-subpot-btn { padding:7px 14px; background:var(--purple); color:#fff; border:none; border-radius:var(--radius); font-family:var(--mono); font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; cursor:pointer; transition:filter 0.15s; display:flex; align-items:center; gap:6px; }
.skgr-details-subpot-btn:hover { filter:brightness(1.15); }
.skgr-details-subpot-btn.added { background:var(--green); cursor:default; filter:none; }
.skgr-conn-section { margin-top:20px; border-top:1px solid var(--border); padding-top:12px; }
.skgr-conn-section h4 { font-family:var(--mono); font-size:0.62rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-dim); margin:0 0 8px; }
.skgr-conn-list { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:12px; }
.skgr-conn-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:20px; font-family:var(--mono); font-size:0.72rem; font-weight:600; border:1px solid var(--border); background:var(--bg); cursor:pointer; color:var(--text); transition:border-color 0.15s, color 0.15s; }
.skgr-conn-pill:hover { border-color:var(--purple); color:var(--purple); }
.skgr-conn-pill .conn-rel { font-size:0.62rem; font-weight:400; color:var(--text-dim); padding-left:4px; border-left:1px solid var(--border); margin-left:2px; }
.skgr-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
</style>

<div style="display:flex; flex-direction:column; gap:12px;">
    <div>
        <div class="kg-section-label" style="font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1.5px; margin-bottom:6px;">Browse KG Nodes</div>
        <input type="text" id="kgTreeSearch" placeholder="Filter nodes in tree…" style="width:100%; padding:6px 9px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.72rem; margin-bottom:6px;">
        <div id="kgTreeWrap" style="background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); max-height:280px; overflow-y:auto; padding:4px 0;"></div>
    </div>
    <div>
        <div class="kg-section-label" style="font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1.5px; margin-bottom:6px;">Selected Export Nodes</div>
        <div id="kgSubpotChips" style="display:flex; flex-wrap:wrap; gap:6px; min-height:36px; padding:6px; border:1px dashed var(--border); border-radius:var(--radius);"></div>
    </div>
    <div>
        <label style="display:flex; align-items:center; gap:6px; font-family:var(--mono); font-size:0.75rem; color:var(--text); cursor:pointer;">
            <input type="checkbox" id="kgIncludeEdges" checked style="accent-color:var(--purple); width:14px; height:14px;">
            Include relationship edges between selected nodes
        </label>
    </div>
    <div>
        <div class="kg-section-label" style="font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1.5px; margin-bottom:6px;">Subpot Preview</div>
        <div id="kgSubpotPreview" style="background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:10px; font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); min-height:60px; white-space:pre-wrap; line-height:1.5;">Select nodes to preview...</div>
    </div>
</div>

<div class="su-modal-overlay" id="skGraphModal" style="position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(3px); z-index:10000; display:none; align-items:center; justify-content:center; padding:16px;">
    <div class="su-modal" style="background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:95%; max-width:900px; height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.6);">
        <div style="padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <div style="font-family:var(--mono); font-size:0.75rem; font-weight:700; color:var(--purple); text-transform:uppercase; letter-spacing:1.5px;"><i class="bi bi-diagram-2-fill"></i> <span id="skgr-modal-title">Mini Graph</span></div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="display:flex; align-items:center; gap:6px; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim);">
                    <i class="bi bi-bezier2"></i> Hops
                    <select id="skgr-hops-select" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:4px; padding:2px 6px; outline:none;"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select>
                </div>
                <button onclick="SuGraph.closeModal()" style="background:transparent; border:1px solid var(--border); color:var(--text-dim); border-radius:4px; cursor:pointer;"><i class="bi bi-x"></i></button>
            </div>
        </div>
        <div class="skgr-topbar" style="border-top:none;">
            <input type="search" id="skgr-search" placeholder="Search nodes…" autocomplete="off">
            <button class="skgr-btn" id="skgr-btn-layout"><i class="bi bi-play-fill"></i> Layout</button>
            <button class="skgr-btn" id="skgr-btn-reset"><i class="bi bi-arrows-collapse"></i></button>
        </div>
        <div class="skgr-stats" id="skgr-stats">
            <span><strong id="skgr-stat-nodes">—</strong> nodes</span>
            <span><strong id="skgr-stat-edges">—</strong> edges</span>
            <span id="skgr-focal-label" style="color:var(--amber); font-weight:700; display:none;"></span>
        </div>
        <div style="flex:1; position:relative; min-height:0; display:flex; flex-direction:column; background:var(--bg); border-bottom-left-radius:var(--radius-lg); border-bottom-right-radius:var(--radius-lg); overflow:hidden;">
            <div id="skgr-container" style="flex:1; width:100%; height:100%; outline:none;"></div>
            <div id="skgr-node-panel"><button class="skgr-panel-close" id="skgr-panel-close">×</button><div class="skgr-panel-name" id="skgr-panel-name">—</div><div class="skgr-panel-type" id="skgr-panel-type"></div><div class="skgr-panel-actions" id="skgr-panel-actions"></div></div>
        </div>
    </div>
</div>

<div id="skgr-details-bg">
    <div class="skgr-details-inner">
        <div class="skgr-details-header">
            <button class="skgr-details-nav" id="skgr-det-back" disabled>&#8592;</button>
            <button class="skgr-details-nav" id="skgr-det-fwd" disabled>&#8594;</button>
            <span class="skgr-details-title" id="skgr-det-title"></span>
            <span class="skgr-details-type" id="skgr-det-type"></span>
            <button class="skgr-details-close" id="skgr-det-close">&times;</button>
        </div>
        <div class="skgr-details-body" id="skgr-det-body"><div class="skgr-empty">Loading…</div></div>
        <div class="skgr-details-subpot-row" id="skgr-det-subpot-row">
            <button class="skgr-details-subpot-btn" id="skgr-det-subpot-btn"><i class="bi bi-plus-lg"></i> Add to Export Selection</button>
            <span style="font-family:var(--mono);font-size:0.68rem;color:var(--text-dim);" id="skgr-det-subpot-label"></span>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>

<script>
const SuGraph = (() => {
    'use strict';
    const API = 'sketchup_api.php';
    const TYPE_COLORS = { note:'#64748b', relationship:'#ec4899', character:'#3b82f6', location:'#10b981', event:'#ef4444', concept:'#f59e0b', arc:'#8b5cf6', episode:'#06b6d4' };
    function typeColor(t) { return TYPE_COLORS[t] || '#888888'; }
    function getMuted() { return '#1c2535'; }
    function getLabelC() { return '#c8d4e8'; }

    let graph = null, renderer = null, isRunning = false, fa2LoopId = null;
    let selected = null, hovered = null, focalId = null, searchMatches = null;
    let currentSeedId = null, currentHops = 1;
    let detHist = [], detHistPos = -1, detCurrentNodeId = null;

    function openModal(nodeId, hops = null) {
        if (hops === null) hops = parseInt(document.getElementById('skgr-hops-select').value) || 1;
        else document.getElementById('skgr-hops-select').value = hops;
        currentSeedId = nodeId; currentHops = hops; focalId = String(nodeId);
        document.getElementById('skGraphModal').style.display = 'flex';
        loadMiniGraph(nodeId, hops);
    }
    function closeModal() {
        document.getElementById('skGraphModal').style.display = 'none';
        stopLayout();
        if (renderer) { renderer.kill(); renderer = null; }
        if (graph) graph.clear();
        closeNodePanel(); searchMatches = null; document.getElementById('skgr-search').value = '';
    }

    function loadMiniGraph(nodeId, hops) {
        const container = document.getElementById('skgr-container');
        container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-dim);">Loading graph…</div>';
        fetch(API, { method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'fetch_mini_graph', node_id:nodeId, hops:hops}) })
            .then(r=>r.json()).then(res => {
                if (!res.ok) { container.innerHTML = `<div style="color:var(--red);">Error: ${res.error}</div>`; return; }
                buildGraph(res.nodes, res.edges);
            });
    }

    function buildGraph(nodes, edges) {
        graph = new graphology.MultiDirectedGraph();
        nodes.forEach(n => {
            graph.addNode(String(n.id), { x:Math.random()*100, y:Math.random()*100, size:4, label:n.name||'', color:typeColor(n.node_type||'note'), node_type:n.node_type||'note' });
        });
        const validIds = new Set(graph.nodes());
        edges.forEach(e => {
            const s = String(e.source), t = String(e.target);
            if (validIds.has(s) && validIds.has(t) && s !== t) {
                try { graph.addDirectedEdge(s, t, { label: e.relationship||'', size:1, color:getMuted() }); } catch(err){}
            }
        });
        graph.forEachNode(node => {
            const isFocal = node === String(currentSeedId);
            const deg = graph.degree(node);
            graph.setNodeAttribute(node, 'size', isFocal ? 10 : 3 + Math.sqrt(deg) * 1.6);
            if (isFocal) {
                document.getElementById('skgr-modal-title').textContent = graph.getNodeAttribute(node, 'label') + ' (Mini Graph)';
                const label = document.getElementById('skgr-focal-label');
                if (label) { label.textContent = '⦿ ' + graph.getNodeAttribute(node, 'label'); label.style.display = ''; }
            }
        });
        document.getElementById('skgr-stat-nodes').textContent = graph.order; document.getElementById('skgr-stat-edges').textContent = graph.size;
        initRenderer(); startLayout(true);
    }

    function initRenderer() {
        const container = document.getElementById('skgr-container'); container.innerHTML = '';
        renderer = new Sigma(graph, container, { renderEdgeLabels: graph.size < 300, defaultEdgeType: 'arrow', allowInvalidContainer: true, labelColor: {color:getLabelC()}, edgeLabelColor: {color:getLabelC()}, edgeLabelSize: 7 });
        renderer.setSetting('nodeReducer', nodeReducer); renderer.setSetting('edgeReducer', edgeReducer);
        let dragNode = null, dragStartX = 0, dragStartY = 0, dragFrame = null;
        renderer.on('downNode', e => { dragNode = e.node; const ne = e.event.original||e.event||{}; dragStartX = ne.touches?ne.touches[0].clientX:(ne.clientX||0); dragStartY = ne.touches?ne.touches[0].clientY:(ne.clientY||0); renderer.getCamera().disable(); });
        renderer.getMouseCaptor().on('mousemovebody', e => {
            if (!dragNode) return; e.preventSigmaDefault(); e.original.preventDefault(); e.original.stopPropagation();
            if (dragFrame) cancelAnimationFrame(dragFrame);
            dragFrame = requestAnimationFrame(() => { const pos = renderer.viewportToGraph(e); graph.setNodeAttribute(dragNode, 'x', pos.x); graph.setNodeAttribute(dragNode, 'y', pos.y); dragFrame=null; });
        });
        function releaseNode(e) {
            if (!dragNode) return; if(dragFrame){cancelAnimationFrame(dragFrame); dragFrame=null;}
            const endX = e.changedTouches?e.changedTouches[0].clientX:(e.clientX||dragStartX), endY = e.changedTouches?e.changedTouches[0].clientY:(e.clientY||dragStartY);
            if (Math.sqrt((endX-dragStartX)**2+(endY-dragStartY)**2) < 6) openNodePanel(dragNode);
            renderer.getCamera().enable(); dragNode = null;
        }
        window.addEventListener('mouseup', releaseNode); window.addEventListener('touchend', releaseNode);
        renderer.on('clickNode', ({node}) => openNodePanel(node)); renderer.on('clickStage', closeNodePanel);
        renderer.on('enterNode', ({node}) => { hovered = node; renderer.refresh(); }); renderer.on('leaveNode', () => { hovered = null; renderer.refresh(); });
    }

    function nodeReducer(node, data) {
        const res = {...data}, muted = getMuted(), isFocal = node === focalId;
        if (searchMatches !== null && !searchMatches.has(node)) { res.color = muted; res.label = ''; res.zIndex = 0; return res; }
        if (isFocal) { res.highlighted = true; res.zIndex = 3; res.size = (data.size||4)*1.4; }
        if (hovered && hovered !== node && !graph.hasEdge(node, hovered) && !graph.hasEdge(hovered, node)) { res.color = muted; res.zIndex = 0; }
        else if (selected && selected !== node && !graph.hasEdge(node, selected) && !graph.hasEdge(selected, node)) { res.color = muted; res.zIndex = isFocal?3:0; }
        else if (!isFocal) res.zIndex = 1;
        if (node === hovered || node === selected) res.zIndex = 2;
        return res;
    }
    function edgeReducer(edge, data) {
        const res = {...data}, source = graph.source(edge), target = graph.target(edge), muted = getMuted();
        if (searchMatches !== null && !searchMatches.has(source) && !searchMatches.has(target)) { res.hidden = true; return res; }
        if (hovered && source !== hovered && target !== hovered) { res.color = muted; res.hidden = true; }
        else if (selected && source !== selected && target !== selected) { res.color = muted; res.hidden = true; }
        else if (hovered || selected) res.size = 2;
        return res;
    }

    const fa2 = () => graphologyLibrary.layoutForceAtlas2;
    function startLayout(autoStop=false) {
        if (!graph || !renderer || isRunning) return; isRunning = true; updateLayoutBtn();
        const settings = { barnesHutOptimize: graph.order > 80, strongGravityMode: true, gravity: 0.05, scalingRatio: 8, slowDown: 8 };
        (function step() { fa2().assign(graph, {iterations:1, settings}); renderer.refresh(); if (isRunning) fa2LoopId = requestAnimationFrame(step); })();
        if (autoStop) setTimeout(stopLayout, 2200);
    }
    function stopLayout() { if (!isRunning) return; cancelAnimationFrame(fa2LoopId); isRunning = false; updateLayoutBtn(); }
    function toggleLayout() { isRunning ? stopLayout() : startLayout(); }
    function updateLayoutBtn() {
        const btn = document.getElementById('skgr-btn-layout'); if (!btn) return;
        btn.innerHTML = isRunning ? '<i class="bi bi-stop-fill"></i> Stop' : '<i class="bi bi-play-fill"></i> Layout';
        btn.classList.toggle('running', isRunning);
    }

    function recenterOn(nodeId) { if(graph && graph.hasNode(nodeId)) openModal(parseInt(nodeId, 10), currentHops); }

    function openNodePanel(nodeId) {
        if (!graph.hasNode(nodeId)) return; selected = nodeId;
        const attrs = graph.getNodeAttributes(nodeId), panel = document.getElementById('skgr-node-panel');
        document.getElementById('skgr-panel-name').textContent = attrs.label; document.getElementById('skgr-panel-type').textContent = attrs.node_type||'note';
        buildPanelActions(nodeId, attrs); panel.style.display = 'flex'; renderer.refresh();
    }
    function buildPanelActions(nodeId, attrs) {
        const actions = document.getElementById('skgr-panel-actions'); actions.innerHTML = '';
        const recBtn = document.createElement('button'); recBtn.className = 'skgr-panel-btn'; recBtn.innerHTML = '<i class="bi bi-crosshair2"></i> Re-center here'; recBtn.onclick = () => recenterOn(nodeId); actions.appendChild(recBtn);
        const detBtn = document.createElement('button'); detBtn.className = 'skgr-panel-btn'; detBtn.innerHTML = '<i class="bi bi-file-text"></i> View Details'; detBtn.onclick = () => openDetails(nodeId); actions.appendChild(detBtn);
        
        const isAdded = SketchUpApp._kgNodeAdded && SketchUpApp._kgNodeAdded(parseInt(nodeId, 10));
        const addBtn = document.createElement('button');
        if (isAdded) { addBtn.className = 'skgr-panel-btn btn-already-added'; addBtn.innerHTML = '<i class="bi bi-check-lg"></i> In Selection'; addBtn.disabled = true; }
        else { addBtn.className = 'skgr-panel-btn btn-add-subpot'; addBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Add to Export'; addBtn.onclick = () => SketchUpApp.addKgNode({ id:parseInt(nodeId,10), name:attrs.label, node_type:attrs.node_type }); }
        actions.appendChild(addBtn);
    }
    function closeNodePanel() { selected = null; const p = document.getElementById('skgr-node-panel'); if(p) p.style.display='none'; if(renderer) renderer.refresh(); }

    function openDetails(nodeId, addHistory=true) {
        if (!graph.hasNode(nodeId)) return; const attrs = graph.getNodeAttributes(nodeId); detCurrentNodeId = nodeId;
        if (addHistory) { detHist = detHist.slice(0, detHistPos+1); detHist.push(nodeId); detHistPos = detHist.length-1; }
        updateDetNav(); document.getElementById('skgr-det-title').textContent = attrs.label; document.getElementById('skgr-det-type').textContent = attrs.node_type||'note';
        const body = document.getElementById('skgr-det-body'); body.innerHTML = '<div class="skgr-empty">Loading…</div>';
        document.getElementById('skgr-details-bg').classList.add('open'); body.scrollTop = 0; updateDetSubpotBtn(nodeId, attrs);
        fetch(`${API}?action=get_node&id=${nodeId}`).then(r=>r.json()).then(res => {
            if(!res.ok){ body.innerHTML='<div class="skgr-empty">Failed to load.</div>'; return; }
            const md = (res.node && res.node.content) ? res.node.content.trim() : '';
            const wrap = document.createElement('div'); wrap.innerHTML = md ? marked.parse(md) : '<div class="skgr-empty">No content yet.</div>';
            body.innerHTML = ''; body.appendChild(wrap); body.appendChild(buildConnPanel(nodeId)); body.scrollTop = 0;
        });
    }
    function updateDetSubpotBtn(nodeId, attrs) {
        const btn = document.getElementById('skgr-det-subpot-btn'), label = document.getElementById('skgr-det-subpot-label');
        const isAdded = SketchUpApp._kgNodeAdded && SketchUpApp._kgNodeAdded(parseInt(nodeId, 10));
        if (isAdded) { btn.className='skgr-details-subpot-btn added'; btn.innerHTML='<i class="bi bi-check-lg"></i> In Selection'; btn.onclick=null; if(label) label.textContent=attrs.label; }
        else { btn.className='skgr-details-subpot-btn'; btn.innerHTML='<i class="bi bi-plus-lg"></i> Add to Export'; btn.onclick = () => { SketchUpApp.addKgNode({ id:parseInt(nodeId,10), name:attrs.label, node_type:attrs.node_type }); updateDetSubpotBtn(nodeId, attrs); if(selected===String(nodeId)) buildPanelActions(nodeId, attrs); }; if(label) label.textContent=''; }
    }
    function closeDetails() { document.getElementById('skgr-details-bg').classList.remove('open'); detHist=[]; detHistPos=-1; detCurrentNodeId=null; updateDetNav(); }
    function updateDetNav() {
        const back = document.getElementById('skgr-det-back'), fwd = document.getElementById('skgr-det-fwd'); if(!back||!fwd) return;
        back.disabled = detHistPos<=0; back.style.opacity = detHistPos>0?'1':'0.35';
        fwd.disabled = detHistPos>=detHist.length-1; fwd.style.opacity = detHistPos<detHist.length-1?'1':'0.35';
    }
    function buildConnPanel(nodeId) {
        const nid = String(nodeId), out = [], inc = [];
        graph.forEachOutboundEdge(nid, (e,a,s,t) => { if(t!==nid && graph.hasNode(t)) out.push({id:t, label:graph.getNodeAttribute(t,'label'), type:graph.getNodeAttribute(t,'node_type'), rel:a.label||''}); });
        graph.forEachInboundEdge(nid, (e,a,s,t) => { if(s!==nid && graph.hasNode(s)) inc.push({id:s, label:graph.getNodeAttribute(s,'label'), type:graph.getNodeAttribute(s,'node_type'), rel:a.label||''}); });
        if (!out.length && !inc.length) return document.createDocumentFragment();
        const wrap = document.createElement('div'); wrap.className = 'skgr-conn-section';
        function makeSection(title, items) {
            if(!items.length) return; const h4 = document.createElement('h4'); h4.textContent = `${title} (${items.length})`; wrap.appendChild(h4);
            const list = document.createElement('div'); list.className = 'skgr-conn-list';
            items.forEach(i => { const pill = document.createElement('button'); pill.className = 'skgr-conn-pill'; const dot = document.createElement('span'); dot.className = 'skgr-dot'; dot.style.background = typeColor(i.type); pill.appendChild(dot); const lbl = document.createElement('span'); lbl.textContent = i.label; pill.appendChild(lbl); if(i.rel){ const rel = document.createElement('span'); rel.className = 'conn-rel'; rel.textContent = i.rel; pill.appendChild(rel); } pill.onclick = () => openDetails(i.id); list.appendChild(pill); }); wrap.appendChild(list);
        }
        makeSection('Outgoing', out); makeSection('Incoming', inc); return wrap;
    }

    function onSearch(q) {
        q = q.trim().toLowerCase();
        if(!q) searchMatches = null; else { searchMatches = new Set(); graph.forEachNode((n,a) => { if(a.label && a.label.toLowerCase().includes(q)) searchMatches.add(n); }); }
        if(renderer) renderer.refresh();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('skgr-btn-layout').addEventListener('click', toggleLayout);
        document.getElementById('skgr-btn-reset').addEventListener('click', () => { if(renderer) renderer.getCamera().animatedReset({duration:400}); });
        document.getElementById('skgr-search').addEventListener('input', e => onSearch(e.target.value));
        document.getElementById('skgr-panel-close').addEventListener('click', closeNodePanel);
        document.getElementById('skgr-hops-select').addEventListener('change', function() { if(currentSeedId) openModal(currentSeedId, parseInt(this.value,10)); });
        document.getElementById('skgr-det-close').addEventListener('click', closeDetails);
        document.getElementById('skgr-details-bg').addEventListener('click', e => { if(e.target === document.getElementById('skgr-details-bg')) closeDetails(); });
        document.getElementById('skgr-det-back').addEventListener('click', () => { if(detHistPos>0){ detHistPos--; openDetails(detHist[detHistPos],false); } });
        document.getElementById('skgr-det-fwd').addEventListener('click', () => { if(detHistPos<detHist.length-1){ detHistPos++; openDetails(detHist[detHistPos],false); } });
    });

    return { openModal, closeModal, recenterOn, openDetails, onKgNodesChanged: () => { if(renderer) renderer.refresh(); if(selected && graph && graph.hasNode(selected)) buildPanelActions(selected, graph.getNodeAttributes(selected)); } };
})();
</script>