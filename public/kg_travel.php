<?php
// public/kg_travel.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Knowledge Graph Travel";
$nodeId = isset($_GET['node_id']) ? (int)$_GET['node_id'] : 0;

ob_start();
?>
<!-- Theme Script -->
<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch(e) {}
})();
</script>

<!-- Vendor CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<!-- Custom Styles -->
<style>
/* Variables */
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
}

body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100vh; overflow:hidden; }

/* Layout */
.kg-layout { display: flex; height: 100vh; flex-direction: column; position: relative; }
.kg-topbar {
    height: 52px; background: rgba(0,0,0,0.6); border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; padding: 0 16px; gap: 10px; flex-shrink: 0; z-index: 10;
    color: #fff; position: absolute; top:0; left:0; right:0; backdrop-filter: blur(4px);
}
.kg-topbar h2 { margin:0; font-size:1rem; display: flex; align-items: center; gap: 8px; }

/* Main Area */
.kg-main { flex: 1; position: relative; overflow: hidden; display: flex; background: #000; }

/* Swiper - Top Aligned with padding to clear topbar */
.swiper-slide { 
    display: flex; align-items: flex-start; justify-content: center; 
    background: #000; position: relative; padding-top: 52px; box-sizing: border-box; 
}
.swiper-slide img { max-width: 100%; max-height: calc(100vh - 52px); object-fit: contain; }

.f-view-btn { 
    position: absolute; top: 68px; right: 20px; width: 44px; height: 44px; 
    background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.25); 
    border-radius: 8px; display: flex; align-items: center; justify-content: center; 
    cursor: pointer; z-index: 10; font-size: 1.3rem; transition: background 0.2s; 
}
.f-view-btn:hover { background: rgba(255,255,255,0.2); }

/* Mini Graph Overlay */
.mg-overlay {
    position: absolute; bottom: 20px; left: 20px; width: min(400px, 90vw); height: min(380px, 50vh);
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.6); z-index: 100; display: flex; flex-direction: column;
    overflow: hidden; transition: height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s;
}
.mg-overlay.collapsed { height: 42px; opacity: 0.9; }
.mg-header {
    height: 42px; min-height: 42px; padding: 0 14px; border-bottom: 1px solid var(--border); 
    font-size: 0.85rem; font-weight: 700; display: flex; justify-content: space-between; 
    align-items: center; background: var(--bg); cursor: move; user-select: none; touch-action: none;
    flex-shrink: 0;
}

/* History Buttons */
.hist-btn {
    background: none; border: 1px solid var(--border); color: var(--text-muted);
    border-radius: 4px; padding: 2px 6px; cursor: pointer; line-height: 1; font-size: 0.9rem;
    transition: color 0.15s, background 0.15s, border-color 0.15s; display: inline-flex; align-items: center; justify-content: center;
}
.hist-btn:hover:not(:disabled) {
    color: var(--text); background: var(--bg); border-color: var(--accent);
}
.hist-btn:disabled { cursor: default; opacity: 0.3; }

/* Mini Graph Toolbar */
.mg-toolbar {
    padding: 6px 10px; background: var(--bg); border-bottom: 1px solid var(--border);
    display: flex; gap: 8px; align-items: center; flex-shrink: 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.mg-toolbar::-webkit-scrollbar { display: none; }
.mg-btn {
    display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px;
    border: 1px solid var(--border); background: var(--card); color: var(--text);
    font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: 0.15s; white-space: nowrap;
}
.mg-btn:hover { border-color: var(--accent); color: var(--accent); }
.mg-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.mg-toolbar select {
    padding: 2px 4px; border-radius: 4px; border: 1px solid var(--border);
    background: var(--card); color: var(--text); font-size: 0.75rem; cursor: pointer;
}

.mg-container { flex: 1; outline: none; background: var(--bg); min-height: 0; }

/* Node Context Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; z-index:9998; padding:20px; box-sizing:border-box; }
.modal-content { background:var(--card); border:1px solid var(--border); border-radius:12px; width:100%; max-width:800px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,0.6); }
.modal-header { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; background:var(--bg); flex-shrink:0; }
.modal-title { font-weight:700; flex:1; font-size:1.05rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.modal-body { padding:24px; overflow-y:auto; flex:1; font-size:0.95rem; line-height:1.6; }
.btn { padding: 6px 12px; border-radius:6px; border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
.btn-primary { background:var(--accent); color:#fff; }
.btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
.btn-ghost:hover { color:var(--accent); border-color:var(--accent); }

/* Markdown Content */
.modal-body h1, .modal-body h2, .modal-body h3 { margin-top: 1em; margin-bottom: 0.5em; line-height: 1.3;}
.modal-body h1 { font-size:1.4rem; border-bottom:1px solid var(--border); padding-bottom:5px; }
.modal-body p { margin-bottom: 1em; }
.modal-body img { max-width: 100%; border-radius:6px; }
.modal-body code { background: rgba(125,125,125,0.2); padding: 2px 4px; border-radius: 4px; font-family: ui-monospace, monospace; font-size: 0.9em; }
.modal-body pre { background: rgba(125,125,125,0.1); padding: 12px; border-radius: 6px; overflow-x: auto; border: 1px solid var(--border); }
.modal-body pre code { background: none; border: none; padding: 0; }

/* Connections */
.conn-section { margin-top: 28px; border-top: 1px solid var(--border); padding-top: 16px; }
.conn-section h4 { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); margin:0 0 10px; }
.conn-list { display: flex; flex-wrap: wrap; gap: 6px; }
.conn-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; border: 1px solid var(--border); background: var(--bg); cursor: pointer; color: var(--text); transition: background 0.15s, border-color 0.15s; }
.conn-pill:hover { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.06); }
.conn-rel { font-size: 0.7rem; font-weight: 400; color: var(--text-muted); padding-left: 5px; border-left: 1px solid var(--border); margin-left: 2px; }
</style>

<div class="kg-layout">
    <div class="kg-topbar">
        <h2><i class="bi bi-airplane-engines" style="color:#58a6ff;"></i> KG Travel <span id="travel-title" style="margin-left:10px; opacity:0.8; font-weight:normal; font-size:0.9rem;"></span></h2>
        <div style="margin-left: auto;">
             <a href="kg_graph.php" class="btn btn-ghost" style="color:#fff; border-color:rgba(255,255,255,0.3);"><i class="bi bi-diagram-3"></i> Graph View</a>
        </div>
    </div>
    
    <div class="kg-main">
        <!-- Swiper -->
        <div class="swiper" id="travelSwiper" style="width:100%; height:100%;">
            <div class="swiper-wrapper" id="travelWrapper"></div>
            <div class="swiper-button-next" style="color: white; text-shadow: 0 2px 6px rgba(0,0,0,0.8);"></div>
            <div class="swiper-button-prev" style="color: white; text-shadow: 0 2px 6px rgba(0,0,0,0.8);"></div>
        </div>
        
        <!-- Mini Graph Overlay -->
        <div class="mg-overlay" id="mgOverlay">
            <div class="mg-header" id="mgHeader">
                <div style="display:flex; align-items:center; gap:8px;">
                    <button class="hist-btn" id="btnHistBack" onclick="travelBack(event)" title="Back" disabled><i class="bi bi-arrow-left"></i></button>
                    <button class="hist-btn" id="btnHistFwd" onclick="travelFwd(event)" title="Forward" disabled><i class="bi bi-arrow-right"></i></button>
                    <span style="margin-left:4px;"><i class="bi bi-compass"></i> Map</span>
                </div>
                <i class="bi bi-arrows-move" style="color:var(--text-muted);"></i>
            </div>
            <div class="mg-toolbar">
                <span style="font-size:0.75rem; color:var(--text-muted);"><i class="bi bi-bezier2"></i> Hops</span>
                <select id="mgHopsSelect" onchange="travelToNode(currentNodeId, false)">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
                <div style="width:1px; height:12px; background:var(--border); margin:0 4px;"></div>
                <button class="mg-btn" id="btnLayout" onclick="toggleLayout()">
                    <i class="bi bi-play-fill"></i> Lyt
                </button>
                <button class="mg-btn" onclick="resetGraphCamera()">
                    <i class="bi bi-arrows-collapse"></i>
                </button>
                
                <!-- Dynamic Actions (Visible on Selection) -->
                <div id="mgActionSep" style="width:1px; height:12px; background:var(--border); margin:0 4px; display:none;"></div>
                <button class="mg-btn" id="btnTravel" onclick="doTravelSelected()" style="display:none; color:var(--accent); border-color:var(--accent);">
                    <i class="bi bi-airplane"></i> Trv
                </button>
                <button class="mg-btn" id="btnDetails" onclick="doDetailsSelected()" style="display:none;">
                    <i class="bi bi-file-text"></i> Det
                </button>
            </div>
            <div class="mg-container" id="mgContainer"></div>
        </div>
    </div>
</div>

<!-- Node Context Modal -->
<div class="modal-overlay" id="nodeContextModal" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle"></span>
            <button id="btnTravelHere" class="btn btn-primary"><i class="bi bi-airplane"></i> Travel Here</button>
            <button onclick="document.getElementById('nodeContextModal').style.display='none'" style="background:none; border:none; color:var(--text); font-size:1.6rem; cursor:pointer; line-height:1;">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalTextContent"></div>
            <div id="modalConnections"></div>
        </div>
    </div>
</div>

<!-- Frame Details Modal Included -->
<?php require __DIR__ . '/modal_frame_details.php'; ?>

<!-- Vendor JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.PhotoSwipeLightbox = PhotoSwipeLightbox;
</script>
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

<!-- Logic -->
<script>
let currentNodeId = <?= $nodeId ?>;
let selectedNodeId = null;

let travelSwiper = null;
let lightbox = null;
let graph = null;
let renderer = null;

let isLayoutRunning = false;
let fa2LoopId = null;

// Travel History State
let travelHistory = [];
let travelIndex = -1;

const TYPE_COLORS = {
    note: '#64748b', relationship: '#ec4899', character: '#3b82f6',
    location: '#10b981', event: '#ef4444', concept: '#f59e0b',
    arc: '#8b5cf6', episode: '#06b6d4', default: '#888888'
};

function escHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escHtmlAttr(s) { return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// Map panel drag and click-to-collapse logic
function makeDraggable(panelId, handleId) {
    const panel = document.getElementById(panelId);
    const handle = document.getElementById(handleId);
    let isDragging = false;
    let startX, startY, initialX, initialY;
    let lastTouchTime = 0;

    function start(e) {
        if(e.target.closest('select') || e.target.closest('button')) return;
        
        if (e.type === 'touchstart') {
            lastTouchTime = Date.now();
        } else if (e.type === 'mousedown' && Date.now() - lastTouchTime < 500) {
            return;
        }

        isDragging = false;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        startX = clientX; startY = clientY;

        const rect = panel.getBoundingClientRect();
        initialX = rect.left; 
        initialY = rect.top;

        panel.style.bottom = 'auto';
        panel.style.right = 'auto';
        panel.style.left = initialX + 'px';
        panel.style.top = initialY + 'px';
        panel.style.transition = 'none';

        document.addEventListener('mousemove', move, {passive: false});
        document.addEventListener('mouseup', end);
        document.addEventListener('touchmove', move, {passive: false});
        document.addEventListener('touchend', end);
    }

    function move(e) {
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const dx = clientX - startX;
        const dy = clientY - startY;

        if (!isDragging && Math.sqrt(dx*dx + dy*dy) > 8) {
            isDragging = true;
        }

        if (isDragging) {
            e.preventDefault();
            panel.style.left = (initialX + dx) + 'px';
            panel.style.top  = (initialY + dy) + 'px';
        }
    }

    function end(e) {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', end);
        document.removeEventListener('touchmove', move);
        document.removeEventListener('touchend', end);
        
        panel.style.transition = 'height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s';
        
        if (!isDragging) {
            panel.classList.toggle('collapsed');
        }
    }

    handle.addEventListener('mousedown', start);
    handle.addEventListener('touchstart', start, {passive: true});
}

// ---------------------------------------------------------
// Travel History Logic
// ---------------------------------------------------------

function initHistory() {
    try {
        const storedHist = sessionStorage.getItem('kg_travel_hist');
        const storedIdx = sessionStorage.getItem('kg_travel_idx');
        if (storedHist !== null && storedIdx !== null) {
            travelHistory = JSON.parse(storedHist);
            travelIndex = parseInt(storedIdx);
        }
    } catch(e) {}

    // If session is empty or URL points to a new place not matching current history tip, start fresh branch
    if (travelIndex === -1 || travelHistory[travelIndex] !== currentNodeId) {
        pushHistory(currentNodeId);
    }
    updateHistoryButtons();
    
    // Explicitly trigger the initial travel fetch!
    travelToNode(currentNodeId, true);
}

function pushHistory(nodeId) {
    if (travelIndex > -1) {
        travelHistory = travelHistory.slice(0, travelIndex + 1);
    }
    if (travelHistory.length === 0 || travelHistory[travelHistory.length - 1] !== nodeId) {
        travelHistory.push(nodeId);
        travelIndex = travelHistory.length - 1;
        saveHistory();
    }
}

function saveHistory() {
    try {
        sessionStorage.setItem('kg_travel_hist', JSON.stringify(travelHistory));
        sessionStorage.setItem('kg_travel_idx', travelIndex.toString());
    } catch(e) {}
    updateHistoryButtons();
}

function updateHistoryButtons() {
    const btnBack = document.getElementById('btnHistBack');
    const btnFwd = document.getElementById('btnHistFwd');
    
    if (btnBack) btnBack.disabled = travelIndex <= 0;
    if (btnFwd) btnFwd.disabled = travelIndex >= travelHistory.length - 1;
}

function travelBack(e) {
    if(e) e.stopPropagation();
    if (travelIndex > 0) {
        travelIndex--;
        saveHistory();
        travelToNode(travelHistory[travelIndex], true);
    }
}

function travelFwd(e) {
    if(e) e.stopPropagation();
    if (travelIndex < travelHistory.length - 1) {
        travelIndex++;
        saveHistory();
        travelToNode(travelHistory[travelIndex], true);
    }
}

// ---------------------------------------------------------
// Travel Core Logic
// ---------------------------------------------------------

function travelToNode(nodeId, isHistoryNav = false) {
    document.getElementById('nodeContextModal').style.display = 'none';
    document.getElementById('travel-title').textContent = "Traveling...";
    const hops = parseInt(document.getElementById('mgHopsSelect').value) || 1;

    if (!isHistoryNav) {
        pushHistory(nodeId);
    }

    fetch('kg_travel_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'travel_context', node_id: nodeId, hops: hops })
    })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) {
            alert("Travel error: " + res.error);
            document.getElementById('travel-title').textContent = "Error";
            return;
        }
        
        currentNodeId = res.focal_node.id;
        
        // Correct history if we passed 0 and the API returned a random node
        if (travelHistory[travelIndex] !== currentNodeId) {
            travelHistory[travelIndex] = currentNodeId;
            saveHistory();
        }
        
        selectNode(null); 
        
        const url = new URL(window.location);
        url.searchParams.set('node_id', currentNodeId);
        window.history.replaceState({}, '', url);

        renderTravel(res);
    })
    .catch(err => {
        console.error("Travel network error:", err);
        document.getElementById('travel-title').textContent = "Network Error";
    });
}

function renderTravel(data) {
    const focalNode = data.focal_node;
    const visuals = data.visuals || [];
    const graphData = data.graph;

    document.getElementById('travel-title').textContent = "— " + focalNode.name;

    // Render Swiper
    const wrapper = document.getElementById('travelWrapper');
    if (visuals.length === 0) {
        wrapper.innerHTML = `
            <div class="swiper-slide">
                <div style="color:var(--text-muted); font-size:1.1rem; text-align:center; padding:20px;">
                    <i class="bi bi-image" style="font-size:3rem; display:block; margin-bottom:12px; opacity:0.5;"></i>
                    No visual frames available for <strong>${escHtml(focalNode.name)}</strong>
                </div>
            </div>`;
    } else {
        wrapper.innerHTML = visuals.map(f => `
            <div class="swiper-slide">
                <a href="/${escHtmlAttr(f.filename)}" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                    <img src="/${escHtmlAttr(f.filename)}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                </a>
                <button class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); window.showFrameDetailsModal(${f.id})" title="Deep Dive">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
            </div>
        `).join('');
    }

    if (travelSwiper) travelSwiper.destroy(true, true);
    setTimeout(() => {
        travelSwiper = new Swiper('#travelSwiper', {
            slidesPerView: 1,
            spaceBetween: 0,
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            keyboard: { enabled: true }
        });
        
        if (lightbox) lightbox.destroy();
        if (window.PhotoSwipeLightbox) {
            lightbox = new window.PhotoSwipeLightbox({
                gallery: '#travelSwiper',
                children: 'a',
                pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js')
            });
            lightbox.init();
        }
    }, 50);

    renderMiniGraph(graphData, focalNode.id);
}

function selectNode(nodeId) {
    selectedNodeId = nodeId;
    if (renderer) renderer.refresh();
    
    const sep = document.getElementById('mgActionSep');
    const btnT = document.getElementById('btnTravel');
    const btnD = document.getElementById('btnDetails');
    
    if (nodeId) {
        sep.style.display = 'block';
        btnT.style.display = 'inline-flex';
        btnD.style.display = 'inline-flex';
    } else {
        sep.style.display = 'none';
        btnT.style.display = 'none';
        btnD.style.display = 'none';
    }
}

function doTravelSelected() {
    if (selectedNodeId) travelToNode(selectedNodeId);
}

function doDetailsSelected() {
    if (selectedNodeId) openNodeContextModal(selectedNodeId);
}

function renderMiniGraph(graphData, focalId) {
    if (graph) graph.clear();
    else graph = new graphology.MultiDirectedGraph();

    // Add nodes
    graphData.nodes.forEach(n => {
        const isFocal = n.id == focalId;
        graph.addNode(n.id.toString(), {
            x: n.x !== undefined ? n.x : Math.random() * 10,
            y: n.y !== undefined ? n.y : Math.random() * 10,
            size: isFocal ? 16 : 8,
            label: n.name,
            color: isFocal ? '#f59e0b' : (TYPE_COLORS[n.node_type] || '#888888'),
            is_focal: isFocal
        });
    });

    graphData.edges.forEach(e => {
        const s = e.source.toString();
        const t = e.target.toString();
        if (graph.hasNode(s) && graph.hasNode(t) && s !== t) {
            try { graph.addDirectedEdge(s, t, { label: e.relationship, size:1, color:'#666' }); } catch(err){}
        }
    });

    // Scale node sizes by degree
    graph.forEachNode(node => {
        const isFocal = graph.getNodeAttribute(node, 'is_focal');
        const deg = graph.degree(node);
        graph.setNodeAttribute(node, 'size', isFocal ? 16 : (8 + Math.sqrt(deg) * 2));
    });

    if (!renderer) {
        const container = document.getElementById('mgContainer');
        renderer = new Sigma(graph, container, {
            renderEdgeLabels: false,
            defaultEdgeType: 'arrow',
            allowInvalidContainer: true,
            labelRenderedSizeThreshold: 2, 
            labelColor: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#c9d1d9' : '#24292f' }
        });
        
        renderer.on('clickStage', () => { selectNode(null); });
        
        let dragNode = null, dragFrame = null, dragStartX = 0, dragStartY = 0;
        
        renderer.on('downNode', e => { 
            dragNode = e.node; 
            const ne = e.event.original || e.event;
            dragStartX = ne.touches ? ne.touches[0].clientX : (ne.clientX || 0);
            dragStartY = ne.touches ? ne.touches[0].clientY : (ne.clientY || 0);
            renderer.getCamera().disable(); 
        });
        
        container.addEventListener('touchmove', e => {
            if (!dragNode) return;
            e.preventDefault();
            const rect = container.getBoundingClientRect();
            const touch = e.touches[0];
            if (dragFrame) cancelAnimationFrame(dragFrame);
            dragFrame = requestAnimationFrame(() => {
                const pos = renderer.viewportToGraph({ x: touch.clientX - rect.left, y: touch.clientY - rect.top });
                graph.setNodeAttribute(dragNode, 'x', pos.x);
                graph.setNodeAttribute(dragNode, 'y', pos.y);
                dragFrame = null;
            });
        }, { passive: false });

        renderer.getMouseCaptor().on('mousemovebody', e => {
            if(!dragNode) return;
            e.preventSigmaDefault(); e.original.preventDefault();
            if(dragFrame) cancelAnimationFrame(dragFrame);
            dragFrame = requestAnimationFrame(() => {
                const pos = renderer.viewportToGraph(e);
                graph.setNodeAttribute(dragNode, 'x', pos.x);
                graph.setNodeAttribute(dragNode, 'y', pos.y);
                dragFrame = null;
            });
        });
        
        function releaseNode(e) {
            if (!dragNode) return;
            let endX = 0, endY = 0;
            if (e.changedTouches && e.changedTouches.length) {
                endX = e.changedTouches[0].clientX;
                endY = e.changedTouches[0].clientY;
            } else {
                endX = e.clientX || dragStartX;
                endY = e.clientY || dragStartY;
            }
            const dx = endX - dragStartX;
            const dy = endY - dragStartY;
            if (Math.sqrt(dx * dx + dy * dy) < 6) { 
                selectNode(dragNode);
            }
            renderer.getCamera().enable();
            dragNode = null;
        }
        window.addEventListener('mouseup', releaseNode);
        window.addEventListener('touchend', releaseNode);

        let hoveredNode = null;
        renderer.setSetting('nodeReducer', (node, data) => {
            const res = { ...data };
            let isDimmed = false;

            if (hoveredNode && hoveredNode !== node && !graph.hasEdge(node, hoveredNode) && !graph.hasEdge(hoveredNode, node)) {
                isDimmed = true;
            } else if (selectedNodeId && selectedNodeId !== node && !graph.hasEdge(node, selectedNodeId) && !graph.hasEdge(selectedNodeId, node)) {
                isDimmed = true;
            }

            if (isDimmed) {
                res.color = '#444'; res.zIndex = 0;
            } else {
                res.zIndex = data.is_focal ? 3 : 1;
            }
            
            if (node === hoveredNode || node === selectedNodeId) res.zIndex = 2;
            if (data.is_focal || node === selectedNodeId) res.highlighted = true;
            
            return res;
        });
        
        renderer.setSetting('edgeReducer', (edge, data) => {
            const res = { ...data };
            if (hoveredNode && graph.source(edge) !== hoveredNode && graph.target(edge) !== hoveredNode) {
                res.color = '#333'; res.hidden = true;
            } else if (selectedNodeId && graph.source(edge) !== selectedNodeId && graph.target(edge) !== selectedNodeId) {
                res.color = '#333'; res.hidden = true;
            } else if (hoveredNode || selectedNodeId) {
                res.size = 2; res.color = '#888';
            }
            return res;
        });

        renderer.on('enterNode', ({ node }) => { hoveredNode = node; renderer.refresh(); });
        renderer.on('leaveNode', () => { hoveredNode = null; renderer.refresh(); });
        
    } else {
        renderer.refresh();
    }

    if (!graphData.nodes.some(n => n.x !== undefined)) {
        const fa2 = graphologyLibrary.layoutForceAtlas2;
        fa2.assign(graph, { iterations: 120, settings: { barnesHutOptimize: false, gravity: 0.1, scalingRatio: 2 } });
        renderer.refresh();
    }
    
    resetGraphCamera();
}

function resetGraphCamera() {
    if(!renderer) return;
    renderer.getCamera().animatedReset({ duration: 300 });
    setTimeout(() => {
        const cam = renderer.getCamera();
        //cam.animatedZoom({ ratio: cam.ratio * 0.5, duration: 300 });
    }, 320);
}

function toggleLayout() {
    if(!graph || !renderer) return;
    const btn = document.getElementById('btnLayout');
    if (isLayoutRunning) {
        cancelAnimationFrame(fa2LoopId);
        isLayoutRunning = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> Lyt';
        btn.classList.remove('active');
    } else {
        isLayoutRunning = true;
        btn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop';
        btn.classList.add('active');
        const fa2 = graphologyLibrary.layoutForceAtlas2;
        const settings = { barnesHutOptimize: graph.order > 50, gravity: 0.05, scalingRatio: 8, slowDown: 5 };
        
        function step() {
            fa2.assign(graph, { iterations: 1, settings });
            renderer.refresh();
            if (isLayoutRunning) fa2LoopId = requestAnimationFrame(step);
        }
        step();
    }
}

function openNodeContextModal(nodeId) {
    const nodeAttrs = graph.getNodeAttributes(nodeId);
    document.getElementById('modalTitle').textContent = nodeAttrs.label;
    
    document.getElementById('btnTravelHere').onclick = () => {
        travelToNode(nodeId);
        document.getElementById('nodeContextModal').style.display = 'none';
    };

    const textContent = document.getElementById('modalTextContent');
    const connContent = document.getElementById('modalConnections');
    
    textContent.innerHTML = '<i>Loading context payload...</i>';
    connContent.innerHTML = '';
    document.getElementById('nodeContextModal').style.display = 'flex';

    fetch('kg_api.php?action=get_node&id=' + nodeId)
        .then(r => r.json())
        .then(res => {
            if(!res.ok) { textContent.innerHTML = 'Failed to load content.'; return; }
            
            const md = (res.node && res.node.content) ? res.node.content.trim() : '';
            textContent.innerHTML = md ? marked.parse(md) : '<i>This node contains no markdown content.</i>';
            
            const outgoing = [], incoming = [];
            graph.forEachOutboundEdge(nodeId, (e, a, s, t) => {
                if(t !== nodeId) outgoing.push({ id: t, label: graph.getNodeAttribute(t, 'label'), rel: a.label });
            });
            graph.forEachInboundEdge(nodeId, (e, a, s, t) => {
                if(s !== nodeId) incoming.push({ id: s, label: graph.getNodeAttribute(s, 'label'), rel: a.label });
            });
            
            let html = '';
            if(outgoing.length > 0) {
                html += `<div class="conn-section"><h4>Outgoing (${outgoing.length})</h4><div class="conn-list">`;
                outgoing.forEach(n => html += `<button class="conn-pill" onclick="openNodeContextModal('${n.id}')">${escHtml(n.label)} ${n.rel ? '<span class="conn-rel">'+escHtml(n.rel)+'</span>' : ''}</button>`);
                html += `</div></div>`;
            }
            if(incoming.length > 0) {
                html += `<div class="conn-section"><h4>Incoming (${incoming.length})</h4><div class="conn-list">`;
                incoming.forEach(n => html += `<button class="conn-pill" onclick="openNodeContextModal('${n.id}')">${escHtml(n.label)} ${n.rel ? '<span class="conn-rel">'+escHtml(n.rel)+'</span>' : ''}</button>`);
                html += `</div></div>`;
            }
            connContent.innerHTML = html;
        });
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const ctxModal = document.getElementById('nodeContextModal');
        if (ctxModal.style.display === 'flex') {
            ctxModal.style.display = 'none';
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        initHistory();
        makeDraggable('mgOverlay', 'mgHeader');
    }, 200);
});
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>