<?php
// Mini graph modal for KG Narrative Export module — adapted from sketchup_graph.php's SuGraph
?>
<style>
.kgn-topbar { flex-shrink:0; display:flex; align-items:center; gap:6px; padding:6px 8px; border-bottom:1px solid var(--border); background:var(--surface); }
.kgn-topbar input[type=search] { flex:1; padding:5px 8px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.72rem; outline:none; }
.kgn-topbar input[type=search]:focus { border-color:var(--teal); }
.kgn-btn { flex-shrink:0; padding:4px 8px; background:transparent; border:1px solid var(--border); border-radius:var(--radius); color:var(--text-dim); font-family:var(--mono); font-size:0.65rem; font-weight:700; cursor:pointer; transition:all 0.15s; white-space:nowrap; display:flex; align-items:center; gap:4px; }
.kgn-btn:hover { border-color:var(--teal); color:var(--teal); }
.kgn-btn.running { border-color:var(--amber); color:var(--amber); }

#kgn-node-panel { position:absolute; top:8px; right:8px; z-index:50; width:200px; background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); padding:12px; box-shadow:0 4px 20px rgba(0,0,0,0.4); display:none; flex-direction:column; gap:8px; }
.kgn-panel-name { font-family:var(--mono); font-size:0.8rem; font-weight:700; color:var(--text-bright); word-break:break-word; line-height:1.3; }
.kgn-panel-type { font-family:var(--mono); font-size:0.62rem; color:var(--teal); text-transform:uppercase; letter-spacing:1px; }
.kgn-panel-actions { display:flex; flex-direction:column; gap:5px; }
.kgn-panel-btn { width:100%; padding:6px 8px; background:transparent; border:1px solid var(--border); border-radius:var(--radius); color:var(--text-dim); font-family:var(--mono); font-size:0.68rem; font-weight:700; cursor:pointer; transition:all 0.15s; text-align:left; display:flex; align-items:center; gap:6px; }
.kgn-panel-btn:hover { border-color:var(--border-glow); color:var(--text); }
.kgn-panel-btn.btn-add-pot { border-color:var(--teal); color:var(--teal); background:rgba(58,181,200,0.1); }
.kgn-panel-btn.btn-add-pot:hover { background:var(--teal); color:#000; }
.kgn-panel-btn.btn-already-added { border-color:var(--green); color:var(--green); background:var(--green-dim); cursor:default; }
.kgn-panel-btn.btn-remove-pot { border-color:var(--red); color:var(--red); }
.kgn-panel-btn.btn-remove-pot:hover { background:var(--red); color:#fff; }
.kgn-panel-close { position:absolute; top:6px; right:8px; background:none; border:none; color:var(--text-dim); font-size:1rem; cursor:pointer; line-height:1; padding:2px 4px; }
.kgn-panel-close:hover { color:var(--text); }

.kgn-stats { font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); padding:3px 8px 4px; border-bottom:1px solid var(--border); flex-shrink:0; display:flex; gap:12px; align-items:center; background:var(--surface); }
.kgn-stats span { display:flex; align-items:center; gap:3px; }
.kgn-stats strong { color:var(--text); }

#kgn-details-bg { position:fixed; inset:0; background:rgba(0,0,0,0.78); backdrop-filter:blur(2px); z-index:99000; display:none; align-items:flex-start; justify-content:center; padding:20px; overflow-y:auto; }
#kgn-details-bg.open { display:flex; }
.kgn-details-inner { background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:100%; max-width:680px; margin:auto; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.6); display:flex; flex-direction:column; }
.kgn-details-header { padding:10px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; background:var(--card); flex-shrink:0; position:sticky; top:0; z-index:2; }
.kgn-details-nav { background:none; border:1px solid var(--border); color:var(--text-dim); border-radius:5px; padding:3px 8px; cursor:pointer; font-size:1rem; line-height:1; opacity:0.35; }
.kgn-details-nav:not(:disabled) { opacity:1; }
.kgn-details-title { font-family:var(--mono); font-weight:700; font-size:0.9rem; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-bright); }
.kgn-details-type { font-family:var(--mono); font-size:0.65rem; font-weight:700; padding:2px 7px; border-radius:8px; flex-shrink:0; background:rgba(58,181,200,0.1); color:var(--teal); border:1px solid rgba(58,181,200,0.3); }
.kgn-details-close { background:none; border:none; color:var(--text-dim); font-size:1.4rem; cursor:pointer; line-height:1; padding:2px 6px; border-radius:4px; flex-shrink:0; }
.kgn-details-close:hover { color:var(--text); }
.kgn-details-body { padding:18px 22px; overflow-y:auto; flex:1; font-size:0.9rem; line-height:1.7; color:var(--text); max-height:70vh; }
.kgn-details-body h1 { font-size:1.3rem; border-bottom:1px solid var(--border); padding-bottom:5px; }
.kgn-details-body h2 { font-size:1.05rem; border-bottom:1px solid var(--border); padding-bottom:4px; }
.kgn-details-body p, .kgn-details-body ul, .kgn-details-body ol { margin:0 0 0.8em; }
.kgn-details-body pre { background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:10px 12px; overflow-x:auto; margin:0 0 0.8em; }
.kgn-details-pot-row { flex-shrink:0; padding:10px 14px; border-top:1px solid var(--border); background:var(--card); display:flex; align-items:center; gap:8px; }
.kgn-details-pot-btn { padding:7px 14px; background:var(--teal); color:#000; border:none; border-radius:var(--radius); font-family:var(--mono); font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; cursor:pointer; transition:filter 0.15s; display:flex; align-items:center; gap:6px; }
.kgn-details-pot-btn:hover { filter:brightness(1.15); }
.kgn-details-pot-btn.added { background:var(--green); cursor:default; filter:none; }
.kgn-conn-section { margin-top:20px; border-top:1px solid var(--border); padding-top:12px; }
.kgn-conn-section h4 { font-family:var(--mono); font-size:0.62rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-dim); margin:0 0 8px; }
.kgn-conn-list { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:12px; }
.kgn-conn-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:20px; font-family:var(--mono); font-size:0.72rem; font-weight:600; border:1px solid var(--border); background:var(--bg); cursor:pointer; color:var(--text); transition:border-color 0.15s, color 0.15s; }
.kgn-conn-pill:hover { border-color:var(--teal); color:var(--teal); }
.kgn-conn-pill .conn-rel { font-size:0.62rem; font-weight:400; color:var(--text-dim); padding-left:4px; border-left:1px solid var(--border); margin-left:2px; }
.kgn-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
</style>

<div class="kgn-modal-overlay" id="kgnGraphModal" style="position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(3px); z-index:10000; display:none; align-items:center; justify-content:center; padding:16px;">
    <div class="kgn-modal" style="background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:95%; max-width:900px; height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.6);">
        <div style="padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <div style="font-family:var(--mono); font-size:0.75rem; font-weight:700; color:var(--teal); text-transform:uppercase; letter-spacing:1.5px;"><i class="bi bi-diagram-2-fill"></i> <span id="kgn-modal-title">Mini Graph</span></div>
            <button onclick="KgnGraph.closeModal()" style="background:transparent; border:1px solid var(--border); color:var(--text-dim); border-radius:4px; cursor:pointer;"><i class="bi bi-x"></i></button>
        </div>
        <div class="kgn-topbar" style="border-top:none;">
            <input type="search" id="kgn-search" placeholder="Search nodes…" autocomplete="off">
            <button class="kgn-btn" id="kgn-btn-layout"><i class="bi bi-play-fill"></i> Layout</button>
            <button class="kgn-btn" id="kgn-btn-reset"><i class="bi bi-arrows-collapse"></i></button>
        </div>
        <div class="kgn-stats" id="kgn-stats">
            <span><strong id="kgn-stat-nodes">—</strong> nodes</span>
            <span><strong id="kgn-stat-edges">—</strong> edges</span>
            <span id="kgn-focal-label" style="color:var(--amber); font-weight:700; display:none;"></span>
        </div>
        <div style="flex:1; position:relative; min-height:0; display:flex; flex-direction:column; background:var(--bg); border-bottom-left-radius:var(--radius-lg); border-bottom-right-radius:var(--radius-lg); overflow:hidden;">
            <div id="kgn-container" style="flex:1; width:100%; height:100%; outline:none;"></div>
            <div id="kgn-node-panel"><button class="kgn-panel-close" id="kgn-panel-close">×</button><div class="kgn-panel-name" id="kgn-panel-name">—</div><div class="kgn-panel-type" id="kgn-panel-type"></div><div class="kgn-panel-actions" id="kgn-panel-actions"></div></div>
        </div>
    </div>
</div>

<div id="kgn-details-bg">
    <div class="kgn-details-inner">
        <div class="kgn-details-header">
            <button class="kgn-details-nav" id="kgn-det-back" disabled>&#8592;</button>
            <button class="kgn-details-nav" id="kgn-det-fwd" disabled>&#8594;</button>
            <span class="kgn-details-title" id="kgn-det-title"></span>
            <span class="kgn-details-type" id="kgn-det-type"></span>
            <button class="kgn-details-close" id="kgn-det-close">&times;</button>
        </div>
        <div class="kgn-details-body" id="kgn-det-body"><div class="kgn-empty">Loading…</div></div>
        <div class="kgn-details-pot-row" id="kgn-det-pot-row">
            <button class="kgn-details-pot-btn" id="kgn-det-pot-btn"><i class="bi bi-plus-lg"></i> Add to Pot</button>
            <span style="font-family:var(--mono);font-size:0.68rem;color:var(--text-dim);" id="kgn-det-pot-label"></span>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>

<script>
const KgnGraph = (() => {
    'use strict';
    const API = 'kg_narrative_api.php';
    const TYPE_COLORS = { note:'#64748b', relationship:'#ec4899', character:'#3b82f6', location:'#10b981', event:'#ef4444', concept:'#f59e0b', arc:'#8b5cf6', episode:'#06b6d4' };
    function typeColor(t) { return TYPE_COLORS[t] || '#888888'; }
    function getMuted() { return '#1c2535'; }
    function getLabelC() { return '#c8d4e8'; }

    let graph = null, renderer = null, isRunning = false, fa2LoopId = null;
    let selected = null, hovered = null, focalId = null, searchMatches = null;
    let currentSeedId = null, currentHops = 1;
    let detHist = [], detHistPos = -1, detCurrentNodeId = null;

    function openModal(nodeId, hops = 1) {
        currentSeedId = nodeId; currentHops = Math.max(1, Math.min(2, hops)); focalId = String(nodeId);
        document.getElementById('kgnGraphModal').style.display = 'flex';
        loadMiniGraph(nodeId, currentHops);
    }
    function closeModal() {
        document.getElementById('kgnGraphModal').style.display = 'none';
        stopLayout();
        if (renderer) { renderer.kill(); renderer = null; }
        if (graph) graph.clear();
        closeNodePanel(); searchMatches = null;
        const s = document.getElementById('kgn-search'); if (s) s.value = '';
    }

    function loadMiniGraph(nodeId, hops) {
        const container = document.getElementById('kgn-container');
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
                document.getElementById('kgn-modal-title').textContent = graph.getNodeAttribute(node, 'label') + ' (Mini Graph)';
                const label = document.getElementById('kgn-focal-label');
                if (label) { label.textContent = '⦿ ' + graph.getNodeAttribute(node, 'label'); label.style.display = ''; }
            }
        });
        document.getElementById('kgn-stat-nodes').textContent = graph.order; document.getElementById('kgn-stat-edges').textContent = graph.size;
        initRenderer(); startLayout(true);
    }

    function initRenderer() {
        const container = document.getElementById('kgn-container'); container.innerHTML = '';
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
        const btn = document.getElementById('kgn-btn-layout'); if (!btn) return;
        btn.innerHTML = isRunning ? '<i class="bi bi-stop-fill"></i> Stop' : '<i class="bi bi-play-fill"></i> Layout';
        btn.classList.toggle('running', isRunning);
    }

    function recenterOn(nodeId) { if(graph && graph.hasNode(nodeId)) openModal(parseInt(nodeId, 10), currentHops); }

    function openNodePanel(nodeId) {
        if (!graph.hasNode(nodeId)) return; selected = nodeId;
        const attrs = graph.getNodeAttributes(nodeId), panel = document.getElementById('kgn-node-panel');
        document.getElementById('kgn-panel-name').textContent = attrs.label; document.getElementById('kgn-panel-type').textContent = attrs.node_type||'note';
        buildPanelActions(nodeId, attrs); panel.style.display = 'flex'; renderer.refresh();
    }
    function buildPanelActions(nodeId, attrs) {
        const actions = document.getElementById('kgn-panel-actions'); actions.innerHTML = '';
        const recBtn = document.createElement('button'); recBtn.className = 'kgn-panel-btn'; recBtn.innerHTML = '<i class="bi bi-crosshair2"></i> Re-center here'; recBtn.onclick = () => recenterOn(nodeId); actions.appendChild(recBtn);
        const detBtn = document.createElement('button'); detBtn.className = 'kgn-panel-btn'; detBtn.innerHTML = '<i class="bi bi-file-text"></i> View Details'; detBtn.onclick = () => openDetails(nodeId); actions.appendChild(detBtn);

        const inPot = window.KgNarrativeApp && KgNarrativeApp.isInPot(parseInt(nodeId, 10));
        const toggleBtn = document.createElement('button');
        if (inPot) {
            toggleBtn.className = 'kgn-panel-btn btn-remove-pot';
            toggleBtn.innerHTML = '<i class="bi bi-dash-lg"></i> Remove from Pot';
            toggleBtn.onclick = () => { KgNarrativeApp.removeFromPot(parseInt(nodeId,10)); buildPanelActions(nodeId, attrs); };
        } else {
            toggleBtn.className = 'kgn-panel-btn btn-add-pot';
            toggleBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Add to Pot';
            toggleBtn.onclick = () => { KgNarrativeApp.addToPot({ id:parseInt(nodeId,10), name:attrs.label, node_type:attrs.node_type }); buildPanelActions(nodeId, attrs); };
        }
        actions.appendChild(toggleBtn);
    }
    function closeNodePanel() { selected = null; const p = document.getElementById('kgn-node-panel'); if(p) p.style.display='none'; if(renderer) renderer.refresh(); }

    function openDetails(nodeId, addHistory=true) {
        if (!graph.hasNode(nodeId)) return; const attrs = graph.getNodeAttributes(nodeId); detCurrentNodeId = nodeId;
        if (addHistory) { detHist = detHist.slice(0, detHistPos+1); detHist.push(nodeId); detHistPos = detHist.length-1; }
        updateDetNav(); document.getElementById('kgn-det-title').textContent = attrs.label; document.getElementById('kgn-det-type').textContent = attrs.node_type||'note';
        const body = document.getElementById('kgn-det-body'); body.innerHTML = '<div class="kgn-empty">Loading…</div>';
        document.getElementById('kgn-details-bg').classList.add('open'); body.scrollTop = 0; updateDetPotBtn(nodeId, attrs);
        fetch(`${API}?action=get_node&id=${nodeId}`).then(r=>r.json()).then(res => {
            if(!res.ok){ body.innerHTML='<div class="kgn-empty">Failed to load.</div>'; return; }
            const md = (res.node && res.node.content) ? res.node.content.trim() : '';
            const wrap = document.createElement('div'); wrap.innerHTML = md ? marked.parse(md) : '<div class="kgn-empty">No content yet.</div>';
            body.innerHTML = ''; body.appendChild(wrap); body.appendChild(buildConnPanel(nodeId)); body.scrollTop = 0;
        });
    }
    function updateDetPotBtn(nodeId, attrs) {
        const btn = document.getElementById('kgn-det-pot-btn'), label = document.getElementById('kgn-det-pot-label');
        const inPot = window.KgNarrativeApp && KgNarrativeApp.isInPot(parseInt(nodeId, 10));
        if (inPot) { btn.className='kgn-details-pot-btn added'; btn.innerHTML='<i class="bi bi-check-lg"></i> In Pot'; btn.onclick=null; if(label) label.textContent=attrs.label; }
        else { btn.className='kgn-details-pot-btn'; btn.innerHTML='<i class="bi bi-plus-lg"></i> Add to Pot'; btn.onclick = () => { KgNarrativeApp.addToPot({ id:parseInt(nodeId,10), name:attrs.label, node_type:attrs.node_type }); updateDetPotBtn(nodeId, attrs); if(selected===String(nodeId)) buildPanelActions(nodeId, attrs); }; if(label) label.textContent=''; }
    }
    function closeDetails() { document.getElementById('kgn-details-bg').classList.remove('open'); detHist=[]; detHistPos=-1; detCurrentNodeId=null; updateDetNav(); }
    function updateDetNav() {
        const back = document.getElementById('kgn-det-back'), fwd = document.getElementById('kgn-det-fwd'); if(!back||!fwd) return;
        back.disabled = detHistPos<=0; back.style.opacity = detHistPos>0?'1':'0.35';
        fwd.disabled = detHistPos>=detHist.length-1; fwd.style.opacity = detHistPos<detHist.length-1?'1':'0.35';
    }
    function buildConnPanel(nodeId) {
        const nid = String(nodeId), out = [], inc = [];
        graph.forEachOutboundEdge(nid, (e,a,s,t) => { if(t!==nid && graph.hasNode(t)) out.push({id:t, label:graph.getNodeAttribute(t,'label'), type:graph.getNodeAttribute(t,'node_type'), rel:a.label||''}); });
        graph.forEachInboundEdge(nid, (e,a,s,t) => { if(s!==nid && graph.hasNode(s)) inc.push({id:s, label:graph.getNodeAttribute(s,'label'), type:graph.getNodeAttribute(s,'node_type'), rel:a.label||''}); });
        if (!out.length && !inc.length) return document.createDocumentFragment();
        const wrap = document.createElement('div'); wrap.className = 'kgn-conn-section';
        function makeSection(title, items) {
            if(!items.length) return; const h4 = document.createElement('h4'); h4.textContent = `${title} (${items.length})`; wrap.appendChild(h4);
            const list = document.createElement('div'); list.className = 'kgn-conn-list';
            items.forEach(i => { const pill = document.createElement('button'); pill.className = 'kgn-conn-pill'; const dot = document.createElement('span'); dot.className = 'kgn-dot'; dot.style.background = typeColor(i.type); pill.appendChild(dot); const lbl = document.createElement('span'); lbl.textContent = i.label; pill.appendChild(lbl); if(i.rel){ const rel = document.createElement('span'); rel.className = 'conn-rel'; rel.textContent = i.rel; pill.appendChild(rel); } pill.onclick = () => openDetails(i.id); list.appendChild(pill); }); wrap.appendChild(list);
        }
        makeSection('Outgoing', out); makeSection('Incoming', inc); return wrap;
    }

    function onSearch(q) {
        q = q.trim().toLowerCase();
        if(!q) searchMatches = null; else { searchMatches = new Set(); graph.forEachNode((n,a) => { if(a.label && a.label.toLowerCase().includes(q)) searchMatches.add(n); }); }
        if(renderer) renderer.refresh();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('kgn-btn-layout').addEventListener('click', toggleLayout);
        document.getElementById('kgn-btn-reset').addEventListener('click', () => { if(renderer) renderer.getCamera().animatedReset({duration:400}); });
        document.getElementById('kgn-search').addEventListener('input', e => onSearch(e.target.value));
        document.getElementById('kgn-panel-close').addEventListener('click', closeNodePanel);
        document.getElementById('kgn-det-close').addEventListener('click', closeDetails);
        document.getElementById('kgn-details-bg').addEventListener('click', e => { if(e.target === document.getElementById('kgn-details-bg')) closeDetails(); });
        document.getElementById('kgn-det-back').addEventListener('click', () => { if(detHistPos>0){ detHistPos--; openDetails(detHist[detHistPos],false); } });
        document.getElementById('kgn-det-fwd').addEventListener('click', () => { if(detHistPos<detHist.length-1){ detHistPos++; openDetails(detHist[detHistPos],false); } });
    });

    return {
        openModal, closeModal, recenterOn, openDetails,
        onPotChanged: () => {
            if (renderer) renderer.refresh();
            if (selected && graph && graph.hasNode(selected)) buildPanelActions(selected, graph.getNodeAttributes(selected));
        }
    };
})();
</script>
