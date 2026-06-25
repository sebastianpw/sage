<?php
// public/kg_graph.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Knowledge Graph Visualizer";

ob_start();

$pdo = $spw->getPDO();

// Fetch nodes (now joined with coordinates)
$stmtNodes = $pdo->query("
    SELECT n.id, n.name, n.node_type, c.x, c.y 
    FROM kg_nodes n 
    LEFT JOIN kg_node_coordinates c ON n.id = c.node_id 
    WHERE n.status='active'
");
$dbNodes = $stmtNodes->fetchAll(PDO::FETCH_ASSOC);

// Fetch edges
$stmtEdges = $pdo->query("
    SELECT id, node_id AS source, item_id AS target, relationship, item_label
    FROM kg_node_items 
    WHERE item_type='kg_node' AND item_id IS NOT NULL
");
$dbEdges = $stmtEdges->fetchAll(PDO::FETCH_ASSOC);

$validNodeIds = array_fill_keys(array_column($dbNodes, 'id'), true);
$edges =[];
foreach ($dbEdges as $e) {
    if (isset($validNodeIds[$e['source']]) && isset($validNodeIds[$e['target']])) {
        $edges[] = $e;
    }
}

$jsonNodes = json_encode($dbNodes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsonEdges = json_encode($edges, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch(e) {}
})();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    const lightbox = new PhotoSwipeLightbox({
        gallery: '.pswp-gallery', children: 'a', pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
        initialZoomLevel: 'fit',
        secondaryZoomLevel: 1
    });
    lightbox.init();
</script>

<style>
/* ── Variables ── */
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
}
@media (prefers-color-scheme:dark) {
    :root:not([data-theme="light"]) {
        --bg:#0d1117; --card:#161b22; --border:#30363d;
        --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    }
}

body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100vh; overflow:hidden; }

/* ── Layout ── */
.kg-layout { display: flex; height: 100vh; flex-direction: column; }
.kg-topbar {
    height: 52px; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; padding: 0 16px; gap: 10px; flex-shrink: 0; z-index: 10;
}
.kg-topbar h2 { margin:0; font-size:1rem; display: flex; align-items: center; gap: 8px; }

/* ── Graph Area ── */
.kg-main { flex: 1; position: relative; overflow: hidden; display: flex; }
#graph-container { flex: 1; height: 100%; background: var(--bg); outline: none; }

/* ── UI Panels ── */
.graph-panel {
    position: absolute;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    z-index: 100;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.panel-left { top: 10px; left: 10px; width: 220px; }
.panel-right { top: 10px; right: 10px; width: 280px; display: none; }

/* Panel Drag & Collapse Elements */
.panel-header {
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    padding: 8px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: move;
    user-select: none;
    touch-action: none;
}
.panel-header h3 { margin: 0; font-size: 0.9rem; pointer-events: none; }
.collapse-btn {
    background: none; border: none; color: var(--text-muted);
    cursor: pointer; padding: 4px; border-radius: 4px; line-height: 1;
}
.collapse-btn:hover { background: rgba(125,125,125,0.2); color: var(--text); }
.panel-content { padding: 12px; }

.graph-panel .stat { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px; }

/* ── Buttons ── */
.btn {
    padding: 6px 11px; border-radius:6px; border:none; cursor:pointer;
    font-weight:600; font-size:0.85rem; display:inline-flex; align-items:center; gap:5px;
    text-decoration:none; white-space:nowrap; justify-content: center;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.btn-primary { background:var(--accent); color:#fff; }
.btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
.btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
.btn-block { width: 100%; margin-bottom: 8px; }
.btn-sm { padding:4px 8px; font-size:0.78rem; }

/* ── Node type badge ── */
.kge-type-pill {
    font-size: 0.68rem; font-weight: 700;
    padding: 1px 6px; border-radius: 8px;
    white-space: nowrap; display: inline-block;
}
.kge-pill-character    { background:rgba(59,130,246,.12);  color:#3b82f6; border:1px solid rgba(59,130,246,.25); }
.kge-pill-location     { background:rgba(16,185,129,.12);  color:#10b981; border:1px solid rgba(16,185,129,.25); }
.kge-pill-concept      { background:rgba(245,158,11,.12);  color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
.kge-pill-event        { background:rgba(239,68,68,.12);   color:#ef4444; border:1px solid rgba(239,68,68,.25); }
.kge-pill-arc          { background:rgba(139,92,246,.12);  color:#8b5cf6; border:1px solid rgba(139,92,246,.25); }
.kge-pill-episode      { background:rgba(6,182,212,.12);   color:#06b6d4; border:1px solid rgba(6,182,212,.25); }
.kge-pill-note         { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }
.kge-pill-relationship { background:rgba(236,72,153,.12);  color:#ec4899; border:1px solid rgba(236,72,153,.25); }
.kge-pill-default      { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }

/* --- VISUAL GALLERY --- */
.visual-container { display: none; flex-direction: column; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 20px; }
.swiper-slide { width: auto; height: 100%; display: flex; align-items: center; justify-content: center; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--border); position: relative; }
.swiper-slide img { width: 240px; height: 240px; display: block; object-fit: contain; }

/* Frame View Modal */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }

.f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
.swiper-slide:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--text); border-color: var(--text); color: #000; }

/* --- CURATION MODAL STYLES --- */
.badge-curator {
    background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(52,211,153,0.1));
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.3);
    cursor: pointer;
    display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; margin-left: 10px;
    vertical-align: middle;
}
.badge-curator:hover { background: rgba(16,185,129,0.15); }
.pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin-right: 4px; margin-bottom: 4px; color: var(--text); border: 1px solid transparent; }
.pill-theme { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.1); }
.pill-char { border-color: #f59e0b; color: #f59e0b; background: rgba(245,159,11,0.1); }

.curation-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; justify-content: center; align-items: center; }
.curation-modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
.curation-modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: var(--text-muted); background: none; border: none; line-height: 1; }
.curation-modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; align-items: flex-start; }
.curation-modal-label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; display: block; margin-bottom: 4px; min-width: 100px; }
.curation-modal-value { font-size: 0.95rem; display: block; flex: 1; }

/* ── Modals ── */
.kg-modal-bg {
    position:fixed; inset:0; background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center; z-index:9999;
}
.kg-modal {
    background:var(--card); border:1px solid var(--border); border-radius:10px;
    padding:20px; width:360px; max-width:94vw;
    box-shadow:0 10px 30px rgba(0,0,0,.3);
}
.kg-modal h3 { margin:0 0 14px 0; font-size:1rem; }
.kg-modal input, .kg-modal select {
    width:100%; padding:8px 10px; border-radius:6px;
    border:1px solid var(--border); background:var(--bg);
    color:var(--text); font-size:0.9rem; margin-bottom:10px; box-sizing: border-box;
}
.kg-modal .actions { display:flex; gap:8px; justify-content:flex-end; }

#kg-toast {
    position:fixed; bottom:24px; right:24px; z-index:99999;
    background:var(--card); color:var(--text); border:1px solid var(--border);
    border-left:4px solid var(--green); border-radius:6px;
    padding:12px 18px; font-size:0.9rem;
    display:none; box-shadow:0 4px 12px rgba(0,0,0,.2);
}

/* Markdown render styles scoped inside details-body */
#details-body h1,#details-body h2,#details-body h3,
#details-body h4,#details-body h5,#details-body h6 {
    margin:1.2em 0 0.4em; line-height:1.3; font-weight:700; color:var(--text);
}
#details-body h1 { font-size:1.5rem; border-bottom:1px solid var(--border); padding-bottom:6px; }
#details-body h2 { font-size:1.2rem; border-bottom:1px solid var(--border); padding-bottom:4px; }
#details-body h3 { font-size:1rem; }
#details-body p  { margin:0 0 0.9em; }
#details-body ul,#details-body ol { margin:0 0 0.9em; padding-left:1.6em; }
#details-body li { margin-bottom:0.3em; }
#details-body blockquote {
    margin:0 0 0.9em; padding:8px 14px;
    border-left:3px solid var(--accent);
    background:rgba(9,105,218,0.06); border-radius:0 6px 6px 0;
    color:var(--text-muted);
}
#details-body code {
    font-family:ui-monospace,monospace; font-size:0.85em;
    background:var(--bg); border:1px solid var(--border);
    padding:1px 5px; border-radius:4px;
}
#details-body pre {
    background:var(--bg); border:1px solid var(--border);
    border-radius:6px; padding:12px 14px; overflow-x:auto;
    margin:0 0 0.9em;
}
#details-body pre code { background:none; border:none; padding:0; }
#details-body hr {
    border:none; border-top:1px solid var(--border); margin:1.2em 0;
}
#details-body a { color:var(--accent); }
#details-body table {
    border-collapse:collapse; width:100%; margin-bottom:0.9em; font-size:0.88rem;
}
#details-body th,#details-body td {
    border:1px solid var(--border); padding:6px 10px; text-align:left;
}
#details-body th { background:var(--bg); font-weight:700; }
#details-body .details-empty {
    color:var(--text-muted); font-style:italic; text-align:center; padding:30px 0;
}
.details-connections {
    margin-top:28px; border-top:1px solid var(--border); padding-top:16px;
}
.details-connections h4 {
    font-size:0.72rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.06em; color:var(--text-muted); margin:0 0 10px;
}
.details-conn-list {
    display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;
}
.details-conn-pill {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 10px; border-radius:20px; font-size:0.78rem; font-weight:600;
    border:1px solid var(--border); background:var(--bg);
    cursor:pointer; color:var(--text);
    transition:border-color 0.15s, color 0.15s, background 0.15s;
}
.details-conn-pill:hover {
    border-color:var(--accent); color:var(--accent);
    background:rgba(9,105,218,0.06);
}
.details-conn-pill .conn-rel {
    font-size:0.68rem; font-weight:400; color:var(--text-muted);
    padding-left:4px; border-left:1px solid var(--border); margin-left:2px;
}

/* ══════════════════════════════════════════════
   FILTER MODAL STYLES
   ══════════════════════════════════════════════ */
.kgf-modal-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(3px);
    display: none; align-items: center; justify-content: center;
    z-index: 9990;
}
.kgf-modal-bg.open { display: flex; }

.kgf-modal {
    width: min(620px, 96vw);
    max-height: 88vh;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,0.45);
}

.kgf-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
    flex-shrink: 0;
}
.kgf-header h3 { margin: 0; font-size: 1rem; flex: 1; display: flex; align-items: center; gap: 8px; }
.kgf-close {
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); font-size: 1.3rem; line-height: 1;
    padding: 2px 6px; border-radius: 4px;
    transition: color 0.15s, background 0.15s;
}
.kgf-close:hover { color: var(--text); background: var(--bg); }

.kgf-picker-header {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 20px 8px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.kgf-picker-header span {
    font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--text-muted); flex: 1;
}
.kgf-picker-select-all {
    background: none; border: none; color: var(--accent);
    font-size: 0.75rem; cursor: pointer; padding: 0; font-weight: 600;
}
.kgf-picker-select-all:hover { text-decoration: underline; }

.kgf-picker-tree-wrap {
    flex: 1; overflow-y: auto; min-height: 0;
    padding: 4px 0;
    background: var(--bg);
}
.kgf-picker-loading {
    padding: 20px; text-align: center;
    color: var(--text-muted); font-size: 0.85rem;
}

.kgf-tree-node {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 10px; cursor: pointer; user-select: none;
    transition: background 0.1s; font-size: 0.86rem;
    border-radius: 4px; margin: 1px 4px;
}
.kgf-tree-node:hover { background: rgba(59,130,246,0.07); }
.kgf-tree-node input[type=checkbox] {
    width: 14px; height: 14px; accent-color: var(--accent);
    cursor: pointer; flex-shrink: 0; margin: 0;
}
.kgf-tree-node .kgf-node-toggle {
    width: 16px; height: 16px; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.65rem; color: var(--text-muted); cursor: pointer;
    border-radius: 3px; transition: background 0.1s, transform 0.15s;
}
.kgf-tree-node .kgf-node-toggle:hover { background: rgba(59,130,246,0.12); }
.kgf-tree-node .kgf-node-toggle.open { transform: rotate(90deg); }
.kgf-tree-node .kgf-node-icon { font-size: 0.85rem; flex-shrink: 0; opacity: 0.75; }
.kgf-tree-node .kgf-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kgf-tree-node.is-folder > .kgf-node-label { font-weight: 600; color: var(--text); }
.kgf-tree-node.is-node > .kgf-node-label { color: var(--text-muted); }
.kgf-tree-children { display: none; }
.kgf-tree-children.open { display: block; }
.kgf-tree-node input[type=checkbox]:indeterminate { opacity: 0.7; }

.kgf-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    display: flex; gap: 8px; justify-content: flex-end; align-items: center;
    flex-shrink: 0;
}
.kgf-footer-left { flex: 1; font-size: 0.82rem; color: var(--text-muted); }
</style>

<div class="kg-layout">
    <div class="kg-topbar">
        <h2><i class="bi bi-diagram-3-fill" style="color:var(--accent);"></i> Knowledge Graph Visualizer</h2>
        <div style="margin-left: auto; display: flex; gap: 8px;">
            <button class="btn btn-ghost btn-sm" onclick="window.location.href='kg_travel.php'"><i class="bi bi-airplane-engines"></i> Travel View</button>
            <button class="btn btn-ghost btn-sm" onclick="window.location.href='kg_view.php'"><i class="bi bi-list"></i> Tree View</button>
        </div>
    </div>

    <div class="kg-main">
        <div id="graph-container"></div>
        
        <div class="graph-panel panel-left" id="controls-panel">
            <div class="panel-header">
                <h3>Controls</h3>
                <button class="collapse-btn" onclick="togglePanel('controls-panel', this)"><i class="bi bi-dash"></i></button>
            </div>
            <div class="panel-content">
                <button class="btn btn-primary btn-block" id="btn-layout"><i class="bi bi-play-fill"></i> Run ForceAtlas2</button>
                <button class="btn btn-ghost btn-block" id="btn-save-layout" onclick="saveLayout()"><i class="bi bi-floppy"></i> Save Layout</button>
                <button class="btn btn-ghost btn-block" id="btn-reset"><i class="bi bi-arrows-collapse"></i> Reset Camera</button>
                <button class="btn btn-ghost btn-block" onclick="showModal('modalNode')"><i class="bi bi-plus-circle"></i> Add Node</button>
                <button class="btn btn-ghost btn-block" id="btn-filter" onclick="openFilterModal()"><i class="bi bi-funnel"></i> Filter Nodes</button>
                
                <div style="margin-top:10px;">
                    <input type="search" id="graph-search"
                        placeholder="&#128269; Search nodes…"
                        style="width:100%; padding:6px 9px; border-radius:6px;
                               border:1px solid var(--border); background:var(--bg);
                               color:var(--text); font-size:0.83rem; box-sizing:border-box;
                               outline:none;"
                        autocomplete="off">
                    <div id="graph-search-count" style="font-size:0.75rem; color:var(--text-muted); margin-top:4px; min-height:16px;"></div>

                    <label style="display:flex; align-items:center; gap:6px; font-size:0.78rem;
                                  color:var(--text-muted); cursor:pointer; margin-top:8px;
                                  user-select:none;">
                        <input type="checkbox" id="search-export-content"
                               style="accent-color:var(--accent); cursor:pointer; margin:0;">
                        Include lore content
                    </label>
                    <button class="btn btn-ghost btn-block" id="btn-search-export"
                            onclick="exportSearchMatches()"
                            style="margin-top:6px; opacity:0.4; pointer-events:none;">
                        <i class="bi bi-download"></i> Export Matches
                    </button>
                </div>

                <div style="margin-top:10px; padding-top:10px; border-top: 1px solid var(--border);">
                    <div class="stat">Nodes: <strong id="stat-nodes">0</strong></div>
                    <div class="stat">Edges: <strong id="stat-edges">0</strong></div>
                    <div class="stat" id="stat-filter" style="display:none; color:var(--orange);">
                        <i class="bi bi-funnel-fill"></i> Filter active
                    </div>
                </div>
            </div>
        </div>

        <div class="graph-panel panel-right" id="node-panel">
            <div class="panel-header">
                <h3>Node Details</h3>
                <button class="collapse-btn" onclick="togglePanel('node-panel', this)"><i class="bi bi-dash"></i></button>
            </div>
            <div class="panel-content">
                <h3 id="np-name" style="margin-top:0;">Node Name</h3>
                <div style="margin-bottom: 15px;">
                    <span id="np-type" class="kge-type-pill kge-pill-note">note</span>
                </div>
                
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="btn btn-primary" id="btn-open-node"><i class="bi bi-box-arrow-up-right"></i> Open in Editor</button>
                    <button class="btn btn-ghost" id="btn-open-fuzz" style="display:none; border-color:#a855f7; color:#a855f7;" title="Open linked Fuzz Candidate landing page">
                        <i class="bi bi-diagram-3-fill"></i> Open in Fuzz
                    </button>
                    <button class="btn btn-ghost" id="btn-view-details"><i class="bi bi-file-text"></i> View Details</button>
                    <button class="btn btn-ghost" id="btn-add-edge"><i class="bi bi-link"></i> Add Link from here</button>
                    <button class="btn btn-ghost" id="btn-close-panel">Close Panel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="kg-modal-bg" id="modalNode">
    <div class="kg-modal">
        <h3><i class="bi bi-plus-circle"></i> New Node</h3>
        <label style="font-size:0.8rem; color:var(--text-muted);">Name</label>
        <input type="text" id="newNodeName" placeholder="Node name…">
        <label style="font-size:0.8rem; color:var(--text-muted);">Type</label>
        <select id="newNodeType">
            <option value="note">📝 Note</option>
            <option value="relationship">🔗 Relationship</option>
            <option value="character">👤 Character</option>
            <option value="location">📍 Location</option>
            <option value="event">📅 Event</option>
            <option value="concept">💡 Concept</option>
            <option value="arc">🌀 Arc</option>
            <option value="episode">🎬 Episode</option>
        </select>
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalNode')">Cancel</button>
            <button class="btn btn-primary" onclick="createNode()">Create & Add</button>
        </div>
    </div>
</div>

<div class="kg-modal-bg" id="modalAddEdge">
    <div class="kg-modal">
        <h3><i class="bi bi-link"></i> Add Edge / Link</h3>
        <p style="font-size:0.85rem; color:var(--text-muted);">From: <strong id="edgeSourceLabel"></strong></p>
        
        <label style="font-size:0.8rem; color:var(--text-muted);">Target Node Search</label>
        <input type="text" id="edgeTargetSearch" placeholder="Type to search nodes..." autocomplete="off">
        <div id="edgeTargetResults" style="max-height: 100px; overflow-y: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; display: none; margin-bottom: 10px; font-size: 0.85rem;"></div>

        <input type="hidden" id="edgeTargetId">

        <label style="font-size:0.8rem; color:var(--text-muted);">Relationship Label (optional)</label>
        <input type="text" id="edgeRelationship" placeholder="e.g. causes, knows, part_of">
        
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalAddEdge')">Cancel</button>
            <button class="btn btn-primary" onclick="createEdge()">Link nodes</button>
        </div>
    </div>
</div>

<!-- Node Details Full-Screen Modal -->
<div id="modalDetails" style="
    position:fixed; inset:0; background:rgba(0,0,0,0.75);
    display:none; align-items:flex-start; justify-content:center;
    z-index:9998; overflow-y:auto; padding:20px; box-sizing:border-box;
">
    <div style="
        background:var(--card); border:1px solid var(--border); border-radius:12px;
        width:100%; max-width:780px; min-height:200px;
        box-shadow:0 20px 60px rgba(0,0,0,0.5);
        display:flex; flex-direction:column; overflow:hidden;
        margin:auto;
    ">
        <div id="details-header" style="
            padding:10px 14px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:8px; flex-shrink:0;
            background:var(--bg); position:sticky; top:0; z-index:2;
        ">
            <button id="details-btn-back" onclick="detailsHistoryBack()" title="Back"
                style="background:none;border:1px solid var(--border);color:var(--text-muted);
                       border-radius:5px;padding:3px 8px;cursor:pointer;font-size:1rem;line-height:1;
                       opacity:0.35;" disabled>&#8592;</button>
            <button id="details-btn-fwd" onclick="detailsHistoryFwd()" title="Forward"
                style="background:none;border:1px solid var(--border);color:var(--text-muted);
                       border-radius:5px;padding:3px 8px;cursor:pointer;font-size:1rem;line-height:1;
                       opacity:0.35;" disabled>&#8594;</button>
            <span id="details-title" style="font-weight:700; font-size:0.95rem; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></span>
            <span id="details-type" class="kge-type-pill kge-pill-note" style="flex-shrink:0;"></span>
            <button onclick="hideModal('modalDetails'); detailsHistory=[]; detailsHistPos=-1;" style="
                background:none; border:none; color:var(--text-muted);
                font-size:1.4rem; cursor:pointer; line-height:1; padding:2px 6px;
                border-radius:4px; flex-shrink:0;
            " title="Close">&times;</button>
        </div>
        <div id="details-body" style="
            padding:20px 24px; overflow-y:auto; flex:1;
            font-size:0.95rem; line-height:1.7; color:var(--text);
        ">
            <!-- Visual Gallery Container -->
            <div id="mVisuals" class="visual-container">
                <h3 style="margin:0 0 10px 0; font-size:1rem; display:flex; justify-content:space-between; align-items:center; color: var(--accent);">
                    <span>🖼️ Visual Sketch Preview</span>
                    <span id="sketchTitle" style="font-weight:normal; color:var(--text-muted); font-size:0.9rem; display:flex; align-items:center;"></span>
                </h3>
                <div class="swiper pswp-gallery" id="sketchSwiper" style="width:100%; height:240px; margin-bottom:10px;">
                    <div class="swiper-wrapper" id="sketchWrapper"></div>
                    <div class="swiper-button-next" style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.8);"></div>
                    <div class="swiper-button-prev" style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.8);"></div>
                </div>
                <textarea id="sketchDesc" readonly style="width:100%; font-size:0.85rem; color:var(--text-muted); background:var(--bg); border:1px solid var(--border); padding: 8px; border-radius: 4px; resize:vertical; height:60px; outline:none;" placeholder="Sketch Description..."></textarea>
            </div>

            <div id="details-text-content">
                <div class="details-empty">Loading…</div>
            </div>
            <div id="details-connections-content"></div>
        </div>
    </div>
</div>

<!-- CURATION ANALYSIS MODAL -->
<div id="curation-modal" class="curation-modal-overlay">
    <div class="curation-modal-content">
        <button class="curation-modal-close" onclick="document.getElementById('curation-modal').style.display='none'">&times;</button>
        <h3 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Narrative Analysis</h3>
        <div id="curation-modal-body"></div>
    </div>
</div>

<!-- Frame View Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<!-- ═══════ FILTER MODAL ═══════ -->
<div class="kgf-modal-bg" id="kgf-modal-bg">
    <div class="kgf-modal">
        <div class="kgf-header">
            <h3>&#x1F50D; Filter Graph Nodes</h3>
            <button class="kgf-close" onclick="closeFilterModal()">&#x2715;</button>
        </div>
        <div class="kgf-picker-header">
            <span>Select categories &amp; nodes to show</span>
            <button class="kgf-picker-select-all" onclick="kgfToggleAll()">Check all</button>
        </div>
        <div class="kgf-picker-tree-wrap" id="kgf-picker-tree-wrap">
            <div class="kgf-picker-loading">Loading…</div>
        </div>
        <div class="kgf-footer">
            <span class="kgf-footer-left" id="kgf-footer-count"></span>
            <button class="btn btn-ghost" onclick="closeFilterModal()">Cancel</button>
            <button class="btn btn-primary" onclick="kgfApplyFilter()">&#x2714; Apply Filter</button>
        </div>
    </div>
</div>

<div id="kg-toast"></div>

<!-- Data dependencies -->
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

<script>
const dbNodes = <?php echo $jsonNodes; ?>;
const dbEdges = <?php echo $jsonEdges; ?>;

let graph, renderer;
let isLayoutRunning = false;
let fa2LoopId = null;
let selectedNode = null;
let hoveredNode = null;
let searchMatches = null; // null = no search active; Set = active filter
let visualSwiper = null;

const typeColors = {
    'note': '#64748b', 'relationship': '#ec4899', 'character': '#3b82f6',
    'location': '#10b981', 'event': '#ef4444', 'concept': '#f59e0b',
    'arc': '#8b5cf6', 'episode': '#06b6d4', 'default': '#888888'
};

function getMutedColor() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? '#30363d' : '#e2e8f0';
}

function escapeHtml(text) { return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
function escapeHtmlAttr(str) { return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function updateSizes() {
    graph.forEachNode((node, attrs) => {
        const degree = graph.degree(node);
        graph.setNodeAttribute(node, 'size', 1 + Math.sqrt(degree) * 2);
    });
    document.getElementById('stat-nodes').textContent = graph.order;
    document.getElementById('stat-edges').textContent = graph.size;
}

function saveLayout() {
    const btn = document.getElementById('btn-save-layout');
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Saving...';
    btn.disabled = true;

    const positions = [];
    graph.forEachNode((node, attrs) => {
        positions.push({ id: parseInt(node), x: attrs.x, y: attrs.y });
    });

    fetch('kg_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_layout', positions: positions })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            toast('Layout saved successfully!');
        } else {
            toast('Error: ' + res.error, 'error');
        }
    })
    .catch(err => toast('Network error', 'error'))
    .finally(() => {
        btn.innerHTML = '<i class="bi bi-floppy"></i> Save Layout';
        btn.disabled = false;
    });
}

// ══════════════════════════════════════════════
// FILTER STATE
// Namespaced localStorage key
// ══════════════════════════════════════════════
const KGF_STORAGE_KEY    = 'kg_live_graph_filter';
const KGF_OPEN_KEY       = 'kg_live_graph_filter_open';

let kgfRawTree     = [];      // full tree from API
let kgfChecked     = new Set(); // jstree-style IDs currently checked in modal
let kgfActiveIds   = null;    // Set of DB node id strings currently visible; null = show all

function kgfLoadFromStorage() {
    try {
        const raw = localStorage.getItem(KGF_STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) return new Set(parsed);
        }
    } catch(e) {}
    return null; // null means "all"
}

function kgfSaveToStorage(ids) {
    try {
        if (ids === null) {
            localStorage.removeItem(KGF_STORAGE_KEY);
        } else {
            localStorage.setItem(KGF_STORAGE_KEY, JSON.stringify(Array.from(ids)));
        }
    } catch(e) {}
}

function kgfSaveOpenState() {
    const open = [];
    document.querySelectorAll('#kgf-picker-tree-wrap .kgf-tree-children.open').forEach(el => {
        open.push(el.id.replace('kgf-kids-', ''));
    });
    try { localStorage.setItem(KGF_OPEN_KEY, JSON.stringify(open)); } catch(e) {}
}

function kgfLoadOpenState() {
    try {
        const raw = localStorage.getItem(KGF_OPEN_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch(e) {}
    return null; // null = all open by default
}

function kgfGetVisibleDbIds() {
    const visibleIds = new Set();
    kgfRawTree.forEach(n => {
        if (n.type === 'node' && kgfChecked.has(n.id)) {
            visibleIds.add(n.data.db_id.toString());
        }
    });
    return visibleIds;
}

function kgfIsAllSelected() {
    const totalNodes = kgfRawTree.filter(n => n.type === 'node').length;
    const checkedNodes = kgfRawTree.filter(n => n.type === 'node' && kgfChecked.has(n.id)).length;
    return checkedNodes === totalNodes;
}

function kgfApplyActiveFilter() {
    if (renderer) renderer.refresh();
    const statEl = document.getElementById('stat-filter');
    if (kgfActiveIds === null) {
        statEl.style.display = 'none';
        document.getElementById('btn-filter').style.borderColor = '';
        document.getElementById('btn-filter').style.color = '';
    } else {
        statEl.style.display = 'block';
        document.getElementById('btn-filter').style.borderColor = 'var(--orange)';
        document.getElementById('btn-filter').style.color = 'var(--orange)';
    }
}

// ── Filter Modal ─────────────────────────────

function openFilterModal() {
    document.getElementById('kgf-modal-bg').classList.add('open');
    kgfLoadTree();
}

function closeFilterModal() {
    document.getElementById('kgf-modal-bg').classList.remove('open');
}

function kgfLoadTree() {
    const wrap = document.getElementById('kgf-picker-tree-wrap');
    wrap.innerHTML = '<div class="kgf-picker-loading">Loading…</div>';
    fetch('kg_api.php?action=fetch_tree')
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { wrap.innerHTML = '<div class="kgf-picker-loading">Failed to load.</div>'; return; }
            kgfRawTree = res.tree;

            if (kgfActiveIds !== null) {
                kgfChecked = new Set();
                kgfRawTree.forEach(n => {
                    if (n.type === 'node' && kgfActiveIds.has(n.data.db_id.toString())) {
                        kgfChecked.add(n.id);
                    }
                });
                const folders = kgfRawTree.filter(n => n.type === 'folder');
                function getFolderDepth(jsId) {
                    let d = 0, cur = jsId;
                    while (true) {
                        const node = kgfRawTree.find(n => n.id === cur);
                        if (!node || !node.parent || node.parent === '#') break;
                        cur = node.parent; d++;
                    }
                    return d;
                }
                folders.sort((a, b) => getFolderDepth(b.id) - getFolderDepth(a.id));
                folders.forEach(folder => {
                    const children = kgfRawTree.filter(n => n.parent === folder.id);
                    if (!children.length) return;
                    const checkedCount = children.filter(c => kgfChecked.has(c.id)).length;
                    if (checkedCount === children.length) {
                        kgfChecked.add(folder.id);
                    } else {
                        kgfChecked.delete(folder.id);
                    }
                });
            } else {
                kgfChecked = new Set(kgfRawTree.map(n => n.id));
            }

            kgfRenderTree();
            kgfUpdateFooter();
        })
        .catch(() => { wrap.innerHTML = '<div class="kgf-picker-loading">Error loading tree.</div>'; });
}

function kgfNodeIcon(type) {
    const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' };
    return map[type] || '📝';
}

function kgfRenderTree() {
    const wrap = document.getElementById('kgf-picker-tree-wrap');
    const childMap = {};
    kgfRawTree.forEach(n => {
        const p = n.parent || '#';
        if (!childMap[p]) childMap[p] = [];
        childMap[p].push(n);
    });
    wrap.innerHTML = kgfBuildLevel('#', childMap, 0);

    const savedOpen = kgfLoadOpenState();
    wrap.querySelectorAll('.kgf-tree-children').forEach(el => {
        const jsId = el.id.replace('kgf-kids-', '');
        const shouldOpen = savedOpen === null || savedOpen.has(jsId);
        el.classList.toggle('open', shouldOpen);
    });
    wrap.querySelectorAll('.kgf-node-toggle').forEach(el => {
        const row  = el.closest('.kgf-tree-node');
        const jsId = row ? row.dataset.jid : null;
        const kids = jsId ? document.getElementById('kgf-kids-' + jsId) : null;
        el.classList.toggle('open', kids ? kids.classList.contains('open') : false);
    });
    kgfRawTree.filter(n => n.type === 'folder').forEach(folder => {
        const el = wrap.querySelector(`.kgf-tree-node[data-jid="${folder.id}"] input[type=checkbox]`);
        if (!el) return;
        const children = kgfRawTree.filter(n => n.parent === folder.id);
        if (!children.length) return;
        const checkedCount = children.filter(c => kgfChecked.has(c.id)).length;
        if (checkedCount === 0 || checkedCount === children.length) {
            el.indeterminate = false;
        } else {
            el.indeterminate = true;
        }
    });
}

function kgfBuildLevel(parentId, childMap, depth) {
    const children = childMap[parentId] || [];
    if (!children.length) return '';
    const indent = depth * 14;
    let html = '';
    children.forEach(node => {
        const isFolder = node.type === 'folder';
        const jsId     = node.id;
        const checked  = kgfChecked.has(jsId);
        const hasKids  = !!(childMap[jsId] && childMap[jsId].length);
        const icon     = isFolder ? '📁' : kgfNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');

        const toggleBtn = (isFolder && hasKids)
            ? `<span class="kgf-node-toggle open" onclick="kgfToggleFolder('${jsId}', this)">▶</span>`
            : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

        html += `
        <div class="kgf-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
             style="padding-left:${10 + indent}px;"
             data-jid="${jsId}">
            ${toggleBtn}
            <input type="checkbox" ${checked ? 'checked' : ''}
                   onchange="kgfCheck('${jsId}', this.checked)">
            <span class="kgf-node-icon">${icon}</span>
            <span class="kgf-node-label">${escHtml(node.text)}</span>
        </div>`;

        if (hasKids) {
            html += `<div class="kgf-tree-children open" id="kgf-kids-${jsId}">`;
            html += kgfBuildLevel(jsId, childMap, depth + 1);
            html += `</div>`;
        }
    });
    return html;
}

function kgfToggleFolder(jsId, btn) {
    const kids = document.getElementById('kgf-kids-' + jsId);
    if (!kids) return;
    kids.classList.toggle('open');
    btn.classList.toggle('open');
    kgfSaveOpenState();
}

function kgfDescendants(jsId) {
    const result = [jsId];
    const queue  = [jsId];
    while (queue.length) {
        const cur = queue.shift();
        kgfRawTree.filter(n => n.parent === cur).forEach(n => {
            result.push(n.id);
            queue.push(n.id);
        });
    }
    return result;
}

function kgfCheck(jsId, checked) {
    const ids = kgfDescendants(jsId);
    ids.forEach(id => {
        if (checked) kgfChecked.add(id);
        else         kgfChecked.delete(id);
    });
    ids.forEach(id => {
        const el = document.querySelector(`#kgf-picker-tree-wrap .kgf-tree-node[data-jid="${id}"] input[type=checkbox]`);
        if (el) { el.checked = checked; el.indeterminate = false; }
    });
    kgfSyncAncestors(jsId);
    kgfUpdateFooter();
}

function kgfSyncAncestors(jsId) {
    const node = kgfRawTree.find(n => n.id === jsId);
    if (!node || !node.parent || node.parent === '#') return;
    const parentJid  = node.parent;
    const siblings   = kgfRawTree.filter(n => n.parent === parentJid);
    const allChecked  = siblings.every(s => kgfChecked.has(s.id));
    const noneChecked = siblings.every(s => !kgfChecked.has(s.id));
    const el = document.querySelector(`#kgf-picker-tree-wrap .kgf-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);
    if (el) {
        if (allChecked) {
            el.checked = true; el.indeterminate = false;
            kgfChecked.add(parentJid);
        } else if (noneChecked) {
            el.checked = false; el.indeterminate = false;
            kgfChecked.delete(parentJid);
        } else {
            el.checked = false; el.indeterminate = true;
            kgfChecked.delete(parentJid);
        }
    }
    kgfSyncAncestors(parentJid);
}

function kgfToggleAll() {
    const allChecked = kgfRawTree.every(n => kgfChecked.has(n.id));
    if (allChecked) {
        kgfChecked.clear();
    } else {
        kgfRawTree.forEach(n => kgfChecked.add(n.id));
    }
    kgfRenderTree();
    kgfUpdateFooter();
}

function kgfUpdateFooter() {
    const nodeCount = kgfRawTree.filter(n => n.type === 'node' && kgfChecked.has(n.id)).length;
    const total     = kgfRawTree.filter(n => n.type === 'node').length;
    const el = document.getElementById('kgf-footer-count');
    if (el) {
        el.textContent = nodeCount === total
            ? `All ${total} nodes visible`
            : `${nodeCount} of ${total} nodes visible`;
    }
}

function kgfApplyFilter() {
    if (kgfIsAllSelected()) {
        kgfActiveIds = null;
        kgfSaveToStorage(null);
    } else {
        kgfActiveIds = kgfGetVisibleDbIds();
        kgfSaveToStorage(kgfActiveIds);
    }
    closeFilterModal();
    kgfApplyActiveFilter();
    toast(kgfActiveIds === null ? 'Filter cleared — showing all nodes' : `Filter applied — ${kgfActiveIds.size} nodes visible`);
}

function kgfInitFromStorage() {
    const stored = kgfLoadFromStorage();
    if (stored !== null && stored.size > 0) {
        kgfActiveIds = stored;
    } else {
        kgfActiveIds = null;
    }
    kgfApplyActiveFilter();
}


// Draggable & Collapsible Panels
function makeDraggable(panelId) {
    const panel = document.getElementById(panelId);
    const header = panel.querySelector('.panel-header');
    let isDragging = false;
    let startX, startY, initialX, initialY;

    function start(e) {
        if (e.target.closest('.collapse-btn')) return; 
        isDragging = true;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        startX = clientX;
        startY = clientY;
        initialX = panel.offsetLeft;
        initialY = panel.offsetTop;
        
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', end);
        document.addEventListener('touchmove', move, {passive: false});
        document.addEventListener('touchend', end);
    }
    function move(e) {
        if (!isDragging) return;
        e.preventDefault(); 
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        panel.style.left = (initialX + (clientX - startX)) + 'px';
        panel.style.top = (initialY + (clientY - startY)) + 'px';
        panel.style.right = 'auto'; // release constraints
    }
    function end() {
        isDragging = false;
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', end);
        document.removeEventListener('touchmove', move);
        document.removeEventListener('touchend', end);
    }
    header.addEventListener('mousedown', start);
    header.addEventListener('touchstart', start, {passive: false});
}

function togglePanel(panelId, btn) {
    const content = document.querySelector(`#${panelId} .panel-content`);
    if (content.style.display === 'none') {
        content.style.display = 'block';
        btn.innerHTML = '<i class="bi bi-dash"></i>';
    } else {
        content.style.display = 'none';
        btn.innerHTML = '<i class="bi bi-plus"></i>';
    }
}

function openNodePanel(nodeId) {
    selectedNode = nodeId;
    const attrs = graph.getNodeAttributes(nodeId);
    const panel = document.getElementById('node-panel');
    document.getElementById('np-name').textContent = attrs.label;
    document.getElementById('np-type').textContent = attrs.node_type;
    document.getElementById('np-type').className = 'kge-type-pill kge-pill-' + (attrs.node_type || 'default');
    
    // Check for a linked promoted fuzz candidate
    const fuzzBtn = document.getElementById('btn-open-fuzz');
    fuzzBtn.style.display = 'none';
    fuzzBtn.dataset.candidateId = '';
    fetch('kg_api.php?action=get_fuzz_candidate_for_node&node_id=' + nodeId)
        .then(r => r.json())
        .then(res => {
            if (res.ok && res.candidate) {
                fuzzBtn.dataset.candidateId = res.candidate.id;
                fuzzBtn.style.display = 'flex';
            }
        })
        .catch(() => {});

    panel.style.display = 'flex';
    document.querySelector('#node-panel .panel-content').style.display = 'block';
    document.querySelector('#node-panel .collapse-btn').innerHTML = '<i class="bi bi-dash"></i>';
    
    renderer.refresh();
}

// ═══════════════════════════════════════════════
// VISUAL FETCHING AND MODALS
// ═══════════════════════════════════════════════
function fetchVisuals(name) {
    const container = document.getElementById('mVisuals');
    const wrapper = document.getElementById('sketchWrapper');
    container.style.display = 'none';
    wrapper.innerHTML = '';
    
    $.post('kg_api.php', {
        action: 'fetch_visuals',
        entity_name: name
    }, res => {
        if (res.ok && res.sketch && res.sketch.frames && res.sketch.frames.length > 0) {
            let curationBadge = '';
            if (res.sketch.curation) {
                const cData = escapeHtmlAttr(JSON.stringify(res.sketch.curation));
                curationBadge = `<span class="badge-curator curation-pill-trigger" data-curation="${cData}" title="Quality Score: ${res.sketch.curation.score}">🕵️ Analysis (${res.sketch.curation.score})</span>`;
            }

            document.getElementById('sketchTitle').innerHTML = escapeHtml(res.sketch.name || '') + curationBadge;
            document.getElementById('sketchDesc').value = res.sketch.description || '';
            
            let slides = '';
            res.sketch.frames.forEach(f => {
                const safeUrl = escapeHtml(f.filename);
                slides += `
                    <div class="swiper-slide">
                        <a href="${safeUrl}" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                            <img src="${safeUrl}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                        </a>
                        <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openFrameModal(${f.id})"><i class="bi bi-arrows-fullscreen"></i></div>
                    </div>
                `;
            });
            wrapper.innerHTML = slides;
            container.style.display = 'flex';

            if (visualSwiper) { visualSwiper.destroy(true, true); }
            setTimeout(() => {
                visualSwiper = new Swiper('#sketchSwiper', {
                    slidesPerView: 'auto', spaceBetween: 10, freeMode: true,
                    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                });
            }, 50);
        }
    }, 'json').fail(err => console.error('Error fetching visuals:', err));
}

document.getElementById('curation-modal').addEventListener('click', function(e) {
    if (e.target === this) { this.style.display = 'none'; }
});

document.addEventListener('click', function(e) {
    const trigger = e.target.closest('.curation-pill-trigger');
    if (!trigger) return;
    e.stopPropagation();
    const raw = trigger.dataset.curation;
    if (!raw) return;
    const data = JSON.parse(raw);
    const body = document.getElementById('curation-modal-body');
    
    let html = `
        <div style="margin-bottom:15px;">
            <div class="score-badge" style="display:inline-block; padding:4px 10px; background:#10b981; color:white; border-radius:6px; font-weight:800; font-size:1.2em; margin-right:10px;">${data.score}</div>
            <strong style="font-size:1.1em;">Overall Quality</strong>
        </div>
    `;
    if(data.class) {
        if(data.class.narrative_function) html += `<div class="curation-modal-row"><span class="curation-modal-label">Function</span><span class="curation-modal-value">${escapeHtml(data.class.narrative_function)}</span></div>`;
        if(data.class.emotional_tone) html += `<div class="curation-modal-row"><span class="curation-modal-label">Tone</span><span class="curation-modal-value">${escapeHtml(data.class.emotional_tone)}</span></div>`;
    }
    if (data.themes && data.themes.primary_themes) {
        html += `<div class="curation-modal-row"><span class="curation-modal-label">Themes</span><div style="margin-top:4px;">`;
        let themes = Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes : [data.themes.primary_themes];
        themes.forEach(t => html += `<span class="pill pill-theme">${escapeHtml(t)}</span> `);
        html += `</div></div>`;
    }
    if (data.entities) {
        if(data.entities.characters && data.entities.characters.length > 0) {
            html += `<div class="curation-modal-row"><span class="curation-modal-label">Characters</span><div style="margin-top:4px;">`;
            data.entities.characters.forEach(c => html += `<span class="pill pill-char">${escapeHtml(c)}</span> `);
            html += `</div></div>`;
        }
        if(data.entities.artifacts && data.entities.artifacts.length > 0) {
            html += `<div class="curation-modal-row"><span class="curation-modal-label">Artifacts</span><div style="margin-top:4px;">${escapeHtml(data.entities.artifacts.join(', '))}</div></div>`;
        }
    }
    if(data.recs && data.recs.potential_use) {
        html += `<div style="margin-top:15px; background:rgba(245,159,11,0.1); padding:10px; border-radius:6px; border:1px dashed rgba(245,159,11,0.4);">
                    <span class="curation-modal-label" style="color:#f59e0b; border:none; margin:0;">Suggestion</span>
                    <div style="font-style:italic; margin-top:4px;">${escapeHtml(data.recs.potential_use)}</div>
                  </div>`;
    }
    if(data.score_breakdown) {
        html += `<div style="margin-top:15px; border-top:1px dashed var(--border); padding-top:10px;">
                    <span class="curation-modal-label">Score Breakdown</span>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:0.9em; margin-top:5px;">
                        <div>Narrative: <b>${data.score_breakdown.narrative_completeness || '-'}</b></div>
                        <div>Visual: <b>${data.score_breakdown.visual_impact || '-'}</b></div>
                        <div>Production: <b>${data.score_breakdown.production_readiness || '-'}</b></div>
                        <div>Distinctiveness: <b>${data.score_breakdown.visual_distinctiveness || '-'}</b></div>
                    </div>
                  </div>`;
    }
    body.innerHTML = html;
    document.getElementById('curation-modal').style.display = 'flex';
});

function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}

document.addEventListener('DOMContentLoaded', () => {
    makeDraggable('controls-panel');
    makeDraggable('node-panel');

    kgfInitFromStorage();

    graph = new graphology.MultiDirectedGraph();

    dbNodes.forEach(n => {
        graph.addNode(n.id.toString(), {
            x: n.x !== null ? parseFloat(n.x) : Math.random() * 100,
            y: n.y !== null ? parseFloat(n.y) : Math.random() * 100,
            size: 8,
            label: n.name,
            color: typeColors[n.node_type] || typeColors['default'],
            node_type: n.node_type || 'note'
        });
    });

    dbEdges.forEach(e => {
        const s = e.source.toString();
        const t = e.target.toString();
        if (graph.hasNode(s) && graph.hasNode(t)) {
            graph.addDirectedEdge(s, t, {
                label: e.relationship || '',
                size: 1,
                color: getMutedColor()
            });
        }
    });

    updateSizes();
    
    const hasSavedCoords = dbNodes.some(n => n.x !== null);
    
    // Clear heavy JSON arrays to free memory for WebGL
    dbNodes.length = 0;
    dbEdges.length = 0;

    const container = document.getElementById('graph-container');
    const getLabelColor = () => document.documentElement.getAttribute('data-theme') === 'dark' ? '#c9d1d9' : '#24292f';

    renderer = new Sigma(graph, container, {
        renderEdgeLabels: graph.size <= 150,
        defaultEdgeType: "arrow",
        allowInvalidContainer: true,
        labelRenderedSizeThreshold: 2, 
        labelColor: { color: getLabelColor() },
        edgeLabelColor: { color: getLabelColor() },
        edgeLabelSize: 7,
        pixelRatio: Math.min(window.devicePixelRatio || 1, 1.5)
    });

    new MutationObserver(() => {
        renderer.setSetting('labelColor', { color: getLabelColor() });
        renderer.setSetting('edgeLabelColor', { color: getLabelColor() });
        renderer.refresh();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    const fa2 = graphologyLibrary.layoutForceAtlas2;
    const fa2Btn = document.getElementById('btn-layout');

    function toggleLayout() {
        if (isLayoutRunning) {
            cancelAnimationFrame(fa2LoopId);
            isLayoutRunning = false;
            fa2Btn.innerHTML = '<i class="bi bi-play-fill"></i> Run ForceAtlas2';
        } else {
            isLayoutRunning = true;
            fa2Btn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop ForceAtlas2';
            const settings = {
                barnesHutOptimize: graph.order > 100,
                strongGravityMode: true,
                gravity: 0.05,
                scalingRatio: 10,
                slowDown: 10
            };
            function step() {
                fa2.assign(graph, { iterations: 1, settings: settings });
                renderer.refresh();
                if (isLayoutRunning) fa2LoopId = requestAnimationFrame(step);
            }
            step();
        }
    }
    fa2Btn.addEventListener('click', toggleLayout);

    // Only run layout automatically if we do NOT have saved coordinates
    if (!hasSavedCoords) {
        toggleLayout();
        setTimeout(() => { 
            if (isLayoutRunning) toggleLayout(); 
            resetGraphCamera();
        }, 3000);
    } else {
        setTimeout(resetGraphCamera, 100);
    }
    
    function resetGraphCamera() {
        if(!renderer) return;
        renderer.getCamera().animatedReset({ duration: 500 });
        setTimeout(() => {
            const cam = renderer.getCamera();
            //cam.animatedZoom({ ratio: cam.ratio * 0.5, duration: 300 });
        }, 520);
    }
    document.getElementById('btn-reset').addEventListener('click', resetGraphCamera);

    // ── Drag & Tap (Mobile-friendly threshold) ──
    let dragNode = null;
    let dragStartX = 0;
    let dragStartY = 0;
    let dragFrame = null;
    const DRAG_THRESHOLD = 6;

    renderer.on("downNode", (e) => {
        dragNode = e.node;
        const ne = e.event && e.event.original ? e.event.original : (e.event || {});
        if (ne.touches && ne.touches.length) {
            dragStartX = ne.touches[0].clientX;
            dragStartY = ne.touches[0].clientY;
        } else {
            dragStartX = ne.clientX || 0;
            dragStartY = ne.clientY || 0;
        }
        renderer.getCamera().disable();
    });

    renderer.getMouseCaptor().on("mousemovebody", (e) => {
        if (!dragNode) return;
        e.preventSigmaDefault();
        e.original.preventDefault();
        e.original.stopPropagation();
        if (dragFrame) cancelAnimationFrame(dragFrame);
        dragFrame = requestAnimationFrame(() => {
            const pos = renderer.viewportToGraph(e);
            graph.setNodeAttribute(dragNode, "x", pos.x);
            graph.setNodeAttribute(dragNode, "y", pos.y);
            dragFrame = null;
        });
    });

    container.addEventListener('touchmove', (e) => {
        if (!dragNode) return;
        e.preventDefault();
        const rect = container.getBoundingClientRect();
        const touch = e.touches[0];
        if (dragFrame) cancelAnimationFrame(dragFrame);
        dragFrame = requestAnimationFrame(() => {
            const pos = renderer.viewportToGraph({
                x: touch.clientX - rect.left,
                y: touch.clientY - rect.top
            });
            graph.setNodeAttribute(dragNode, "x", pos.x);
            graph.setNodeAttribute(dragNode, "y", pos.y);
            dragFrame = null;
        });
    }, { passive: false });

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
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (dist < DRAG_THRESHOLD) {
            openNodePanel(dragNode);
        }

        renderer.getCamera().enable();
        dragNode = null;
    }
    window.addEventListener('mouseup', releaseNode);
    window.addEventListener('touchend', releaseNode);

    renderer.on("clickNode", ({ node }) => {
        if (!selectedNode || selectedNode !== node) {
            openNodePanel(node);
        }
    });

    renderer.on("clickStage", () => {
        selectedNode = null;
        document.getElementById('node-panel').style.display = 'none';
        renderer.refresh();
    });

    renderer.on('enterNode', ({ node }) => { hoveredNode = node; renderer.refresh(); });
    renderer.on('leaveNode', () => { hoveredNode = null; renderer.refresh(); });

    // ── Node Reducer — incorporates filter, search, and hover/select highlight ──
    renderer.setSetting('nodeReducer', (node, data) => {
        const res = { ...data };
        const muted = getMutedColor();

        // ── Category filter: hide nodes not in the active set ──
        if (kgfActiveIds !== null && !kgfActiveIds.has(node)) {
            res.hidden = true;
            return res;
        }

        // Search filter: dim non-matches
        if (searchMatches !== null) {
            if (!searchMatches.has(node)) {
                res.color = muted;
                res.label = '';
                res.zIndex = 0;
            } else {
                res.zIndex = 2;
                res.size = (data.size || 8) * 1.4;
            }
            return res;
        }

        if (hoveredNode && hoveredNode !== node && !graph.hasEdge(node, hoveredNode) && !graph.hasEdge(hoveredNode, node)) {
            res.color = muted;
            res.zIndex = 0;
        } else if (selectedNode && selectedNode !== node && !graph.hasEdge(node, selectedNode) && !graph.hasEdge(selectedNode, node)) {
            res.color = muted;
            res.zIndex = 0;
        } else {
            res.zIndex = 1;
        }
        if (node === hoveredNode || node === selectedNode) {
            res.zIndex = 2;
        }
        return res;
    });

    renderer.setSetting('edgeReducer', (edge, data) => {
        const res = { ...data };
        const source = graph.source(edge);
        const target = graph.target(edge);
        const muted = getMutedColor();

        // ── Hide edges where either endpoint is filtered out ──
        if (kgfActiveIds !== null && (!kgfActiveIds.has(source) || !kgfActiveIds.has(target))) {
            res.hidden = true;
            return res;
        }

        // Hide edges where neither endpoint is a search match
        if (searchMatches !== null) {
            if (!searchMatches.has(source) && !searchMatches.has(target)) {
                res.hidden = true;
            }
            return res;
        }

        if (hoveredNode && source !== hoveredNode && target !== hoveredNode) {
            res.color = muted;
            res.hidden = true;
        } else if (selectedNode && source !== selectedNode && target !== selectedNode) {
            res.color = muted;
            res.hidden = true;
        } else if (hoveredNode || selectedNode) {
            res.size = 2;
            res.color = document.documentElement.getAttribute('data-theme') === 'dark' ? '#6b7280' : '#94a3b8';
        }
        return res;
    });

    // Connect node panel buttons
    document.getElementById('btn-close-panel').addEventListener('click', () => {
        selectedNode = null;
        document.getElementById('node-panel').style.display = 'none';
        renderer.refresh();
    });

    document.getElementById('btn-open-node').addEventListener('click', () => {
        if (selectedNode) window.open('kg_view.php?node_id=' + selectedNode, '_blank');
    });
    
    document.getElementById('btn-open-fuzz').addEventListener('click', () => {
        const candidateId = document.getElementById('btn-open-fuzz').dataset.candidateId;
        if (candidateId) window.open('fuzz_forge_landing.php?id=' + candidateId, '_blank');
    });

    document.getElementById('btn-add-edge').addEventListener('click', () => {
        if (selectedNode) {
            const attrs = graph.getNodeAttributes(selectedNode);
            document.getElementById('edgeSourceLabel').textContent = attrs.label + ' (#' + selectedNode + ')';
            showModal('modalAddEdge');
        }
    });

    document.getElementById('btn-view-details').addEventListener('click', () => {
        if (!selectedNode) return;
        openDetailsModal(selectedNode);
    });

    // ── Graph node search ──
    document.getElementById('graph-search').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        const countEl = document.getElementById('graph-search-count');
        if (!q) {
            searchMatches = null;
            countEl.textContent = '';
            const eb = document.getElementById('btn-search-export');
            eb.style.opacity = '0.4';
            eb.style.pointerEvents = 'none';
            renderer.refresh();
            return;
        }
        searchMatches = new Set();
        graph.forEachNode((node, attrs) => {
            // Respect active filter: only search within visible nodes
            if (kgfActiveIds !== null && !kgfActiveIds.has(node)) return;
            if (attrs.label && attrs.label.toLowerCase().includes(q)) {
                searchMatches.add(node);
            }
        });
        const n = searchMatches.size;
        countEl.textContent = n === 0 ? 'No matches' : n + ' node' + (n === 1 ? '' : 's') + ' matched';
        countEl.style.color = n === 0 ? 'var(--red)' : 'var(--text-muted)';
        const exportBtn = document.getElementById('btn-search-export');
        if (n > 0) {
            exportBtn.style.opacity = '1';
            exportBtn.style.pointerEvents = 'auto';
        } else {
            exportBtn.style.opacity = '0.4';
            exportBtn.style.pointerEvents = 'none';
        }
        renderer.refresh();
    });

    // Target Node Search logic
    const searchInput = document.getElementById('edgeTargetSearch');
    const searchResults = document.getElementById('edgeTargetResults');
    const targetInput = document.getElementById('edgeTargetId');

    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        searchResults.innerHTML = '';
        if (q.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        const matches = graph.nodes().filter(n => graph.getNodeAttribute(n, 'label').toLowerCase().includes(q));
        if (matches.length > 0) {
            matches.slice(0, 10).forEach(n => {
                const label = graph.getNodeAttribute(n, 'label');
                const div = document.createElement('div');
                div.textContent = label + ' (#' + n + ')';
                div.style.padding = '6px 10px';
                div.style.cursor = 'pointer';
                div.addEventListener('click', () => {
                    targetInput.value = n;
                    searchInput.value = label;
                    searchResults.style.display = 'none';
                });
                div.addEventListener('mouseover', () => div.style.background = 'var(--border)');
                div.addEventListener('mouseout', () => div.style.background = 'transparent');
                searchResults.appendChild(div);
            });
            searchResults.style.display = 'block';
        } else {
            searchResults.style.display = 'none';
        }
    });
});

// Common UI Utils
function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function hideModal(id) { document.getElementById(id).style.display = 'none'; }

// ── Details modal history stack ──
let detailsHistory = [];
let detailsHistPos = -1;

function openDetailsModal(nodeId, addToHistory = true) {
    if (addToHistory) {
        detailsHistory = detailsHistory.slice(0, detailsHistPos + 1);
        detailsHistory.push(nodeId);
        detailsHistPos = detailsHistory.length - 1;
    }
    detailsUpdateNavButtons();

    openNodePanel(nodeId);

    const attrs = graph.getNodeAttributes(nodeId);
    document.getElementById('details-title').textContent = attrs.label;
    const typeEl = document.getElementById('details-type');
    typeEl.textContent = attrs.node_type;
    typeEl.className = 'kge-type-pill kge-pill-' + (attrs.node_type || 'default');

    const body = document.getElementById('details-text-content');
    const connContent = document.getElementById('details-connections-content');
    
    body.innerHTML = '<div class="details-empty" style="font-style:italic;">Loading…</div>';
    connContent.innerHTML = '';

    document.getElementById('modalDetails').style.display = 'flex';
    document.getElementById('details-body').scrollTop = 0;

    fetchVisuals(attrs.label);

    $.get('kg_api.php?action=get_node&id=' + nodeId, res => {
        if (!res.ok) {
            body.innerHTML = '<div class="details-empty">Failed to load node content.</div>';
            return;
        }
        const md = (res.node && res.node.content) ? res.node.content.trim() : '';
        body.innerHTML = md ? marked.parse(md) : '<div class="details-empty">This node has no content yet.</div>';

        connContent.appendChild(detailsBuildConnections(nodeId));
        document.getElementById('details-body').scrollTop = 0;
    }, 'json').fail(() => {
        body.innerHTML = '<div class="details-empty">Network error loading content.</div>';
    });
}

function detailsBuildConnections(nodeId) {
    const nid = nodeId.toString();
    const outgoing = [];
    const incoming = [];

    graph.forEachOutboundEdge(nid, (edge, attrs, source, target) => {
        if (target !== nid && graph.hasNode(target)) {
            outgoing.push({ id: target, label: graph.getNodeAttribute(target, 'label'),
                            type: graph.getNodeAttribute(target, 'node_type'),
                            rel: attrs.label || '' });
        }
    });
    graph.forEachInboundEdge(nid, (edge, attrs, source, target) => {
        if (source !== nid && graph.hasNode(source)) {
            incoming.push({ id: source, label: graph.getNodeAttribute(source, 'label'),
                            type: graph.getNodeAttribute(source, 'node_type'),
                            rel: attrs.label || '' });
        }
    });

    if (!outgoing.length && !incoming.length) return document.createDocumentFragment();

    const wrap = document.createElement('div');
    wrap.className = 'details-connections';

    function makeSection(title, items) {
        if (!items.length) return;
        const h4 = document.createElement('h4');
        h4.textContent = title + ' (' + items.length + ')';
        wrap.appendChild(h4);
        const list = document.createElement('div');
        list.className = 'details-conn-list';
        items.forEach(item => {
            const pill = document.createElement('button');
            pill.className = 'details-conn-pill';
            const typeColor = { character:'#3b82f6', location:'#10b981', concept:'#f59e0b',
                                event:'#ef4444', arc:'#8b5cf6', episode:'#06b6d4',
                                relationship:'#ec4899', note:'#64748b' };
            const dot = document.createElement('span');
            dot.style.cssText = 'width:7px;height:7px;border-radius:50%;flex-shrink:0;background:' +
                                 (typeColor[item.type] || '#888');
            pill.appendChild(dot);
            const lbl = document.createElement('span');
            lbl.textContent = item.label;
            pill.appendChild(lbl);
            if (item.rel) {
                const rel = document.createElement('span');
                rel.className = 'conn-rel';
                rel.textContent = item.rel;
                pill.appendChild(rel);
            }
            pill.addEventListener('click', () => openDetailsModal(item.id));
            list.appendChild(pill);
        });
        wrap.appendChild(list);
    }

    makeSection('Outgoing', outgoing);
    makeSection('Incoming', incoming);
    return wrap;
}

function detailsUpdateNavButtons() {
    const back = document.getElementById('details-btn-back');
    const fwd  = document.getElementById('details-btn-fwd');
    const canBack = detailsHistPos > 0;
    const canFwd  = detailsHistPos < detailsHistory.length - 1;
    back.disabled = !canBack; back.style.opacity = canBack ? '1' : '0.35';
    fwd.disabled  = !canFwd;  fwd.style.opacity  = canFwd  ? '1' : '0.35';
}

function detailsHistoryBack() {
    if (detailsHistPos > 0) {
        detailsHistPos--;
        openDetailsModal(detailsHistory[detailsHistPos], false);
    }
}

function detailsHistoryFwd() {
    if (detailsHistPos < detailsHistory.length - 1) {
        detailsHistPos++;
        openDetailsModal(detailsHistory[detailsHistPos], false);
    }
}

let toastTimer;
function toast(msg, type='success') {
    const el = document.getElementById('kg-toast');
    el.textContent = msg;
    el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display='none', 2800);
}

document.querySelectorAll('.kg-modal-bg').forEach(bg => {
    bg.addEventListener('click', e => { if (e.target === bg) bg.style.display='none'; });
});
document.getElementById('modalDetails').addEventListener('click', function(e) {
    if (e.target === this) { hideModal('modalDetails'); detailsHistory = []; detailsHistPos = -1; }
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const viewModal     = document.getElementById('viewModal');
        const curationModal = document.getElementById('curation-modal');
        if (viewModal && viewModal.classList.contains('active')) {
            closeFrameModal();
        } else if (curationModal && curationModal.style.display === 'flex') {
            curationModal.style.display = 'none';
        } else {
            document.querySelectorAll('.kg-modal-bg').forEach(b => b.style.display='none');
            hideModal('modalDetails'); detailsHistory = []; detailsHistPos = -1;
            closeFilterModal();
        }
    }
});

// Close filter modal on backdrop click
document.getElementById('kgf-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) closeFilterModal();
});

// Graph Mutations API Hooks
function exportSearchMatches() {
    if (!searchMatches || searchMatches.size === 0) return;

    const nodeIds = Array.from(searchMatches);
    const withContent = document.getElementById('search-export-content').checked;
    const btn = document.getElementById('btn-search-export');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Building…';

    fetch('kg_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action:       'focused_snapshot',
            node_ids:     nodeIds,
            with_content: withContent,
        }),
    })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) throw new Error(res.error || 'Snapshot failed');
        const q = document.getElementById('graph-search').value.trim()
                         .toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
        const suffix = withContent ? '_content' : '';
        const date   = new Date().toISOString().slice(0, 10);
        const fname  = 'kg_search_' + (q || 'export') + suffix + '_' + date + '.json';
        const blob   = new Blob([JSON.stringify(res.snapshot, null, 2)], { type: 'application/json' });
        const url    = URL.createObjectURL(blob);
        const a      = document.createElement('a');
        a.href = url; a.download = fname; a.click();
        URL.revokeObjectURL(url);
        toast('Exported ' + nodeIds.length + ' node' + (nodeIds.length === 1 ? '' : 's') + ' ✓');
    })
    .catch(err => toast('Export failed: ' + err.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download"></i> Export Matches';
    });
}

function createNode() {
    const name = document.getElementById('newNodeName').value.trim();
    const type = document.getElementById('newNodeType').value;
    if (!name) return;
    
    $.post('kg_api.php', { action: 'create_node', name: name, node_type: type }, res => {
        if (res.ok) {
            hideModal('modalNode');
            document.getElementById('newNodeName').value = '';
            
            const id = res.id.toString();
            const centerPos = renderer.viewportToGraph({ 
                x: renderer.getContainer().offsetWidth / 2, 
                y: renderer.getContainer().offsetHeight / 2 
            });
            
            graph.addNode(id, {
                x: centerPos.x,
                y: centerPos.y,
                size: 8,
                label: name,
                color: typeColors[type] || typeColors['default'],
                node_type: type
            });
            updateSizes();
            renderer.refresh();
            toast('Node created');
        } else {
            toast('Error: ' + res.error, 'error');
        }
    }, 'json');
}

function createEdge() {
    if (!selectedNode) return;
    const targetId = document.getElementById('edgeTargetId').value;
    const rel = document.getElementById('edgeRelationship').value.trim();
    
    if (!targetId || !graph.hasNode(targetId.toString())) {
        toast('Invalid target node selected', 'error');
        return;
    }
    if (targetId.toString() === selectedNode.toString()) {
        toast('Cannot link node to itself', 'error');
        return;
    }
    
    const targetLabel = graph.getNodeAttribute(targetId, 'label');

    $.post('kg_api.php', {
        action: 'add_item',
        node_id: selectedNode,
        item_type: 'kg_node',
        item_id: targetId,
        item_label: targetLabel,
        relationship: rel,
        note: ''
    }, res => {
        if (res.ok) {
            hideModal('modalAddEdge');
            document.getElementById('edgeTargetId').value = '';
            document.getElementById('edgeTargetSearch').value = '';
            document.getElementById('edgeRelationship').value = '';
            
            graph.addDirectedEdge(selectedNode, targetId, {
                label: rel || '',
                size: 1,
                color: getMutedColor()
            });
            updateSizes();
            renderer.refresh();
            toast('Link added');
        } else {
            toast('Error: ' + res.error, 'error');
        }
    }, 'json');
}
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
