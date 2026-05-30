<?php
// public/ag_graph.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "AG Visualizer";

$docId = (int)($_GET['doc_id'] ?? 0);
$pdo = $spw->getPDO();

$dbNodes =[];
$edges   =[];

if ($docId > 0) {
    $stmtNodes = $pdo->prepare("SELECT id, name, node_type, doc_id FROM ag_nodes WHERE doc_id=? AND status='active'");
    $stmtNodes->execute([$docId]);
    $dbNodes = $stmtNodes->fetchAll(PDO::FETCH_ASSOC);

    $stmtEdges = $pdo->prepare("
        SELECT ani.id, ani.node_id AS source, an_tgt.id AS target, ani.relationship, ani.item_label
        FROM ag_node_items ani
        JOIN ag_nodes an_tgt ON an_tgt.name = ani.item_label AND an_tgt.doc_id = ani.doc_id
        WHERE ani.doc_id = ?
    ");
    $stmtEdges->execute([$docId]);
    $edges = $stmtEdges->fetchAll(PDO::FETCH_ASSOC);
}

$allDocs = $pdo->query("
    SELECT a.doc_id as id, d.name
    FROM md_doc_analysis a
    JOIN documentations d ON a.doc_id = d.id
    ORDER BY d.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$jsonNodes = json_encode($dbNodes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsonEdges = json_encode($edges,   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

ob_start();
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
:root { --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de; --text:#24292f; --text-muted:#57606a; --accent:#0969da; --green:#238636; --red:#da3633; }
:root[data-theme="dark"] { --bg:#0d1117; --card:#161b22; --border:#30363d; --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff; }
@media (prefers-color-scheme:dark) { :root:not([data-theme="light"]) { --bg:#0d1117; --card:#161b22; --border:#30363d; --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff; } }
body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100vh; overflow:hidden; }

.ag-layout { display: flex; height: 100vh; flex-direction: column; }
.ag-topbar { height: 52px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 16px; gap: 10px; flex-shrink: 0; z-index: 10; }
.ag-topbar h2 { margin:0; font-size:1rem; display: flex; align-items: center; gap: 8px; }
.ag-doc-selector { padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.85rem; font-weight: 600; min-width: 200px; }

.ag-main { flex: 1; position: relative; overflow: hidden; display: flex; }
#graph-container { flex: 1; height: 100%; background: var(--bg); outline: none; }

.graph-panel { position: absolute; background: var(--card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); z-index: 100; display: flex; flex-direction: column; overflow: hidden; }
.panel-left  { top: 10px; left: 10px; width: 220px; }
.panel-right { top: 10px; right: 10px; width: 280px; display: none; }

.panel-header { background: var(--bg); border-bottom: 1px solid var(--border); padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; cursor: move; user-select: none; touch-action: none; }
.panel-header h3 { margin: 0; font-size: 0.9rem; pointer-events: none; }
.collapse-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; border-radius: 4px; line-height: 1; }
.collapse-btn:hover { background: rgba(125,125,125,0.2); color: var(--text); }
.panel-content { padding: 12px; }

.graph-panel .stat { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px; }

.btn { padding: 6px 11px; border-radius:6px; border:none; cursor:pointer; font-weight:600; font-size:0.85rem; display:inline-flex; align-items:center; gap:5px; text-decoration:none; white-space:nowrap; justify-content: center; transition: background 0.15s, color 0.15s, border-color 0.15s; }
.btn-primary { background:var(--accent); color:#fff; }
.btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
.btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
.btn-block { width: 100%; margin-bottom: 8px; }
.btn-sm { padding:4px 8px; font-size:0.78rem; }

.age-type-pill { font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 8px; white-space: nowrap; display: inline-block; }
.age-pill-character    { background:rgba(59,130,246,.12);  color:#3b82f6; border:1px solid rgba(59,130,246,.25); }
.age-pill-location     { background:rgba(16,185,129,.12);  color:#10b981; border:1px solid rgba(16,185,129,.25); }
.age-pill-concept      { background:rgba(245,158,11,.12);  color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
.age-pill-event        { background:rgba(239,68,68,.12);   color:#ef4444; border:1px solid rgba(239,68,68,.25); }
.age-pill-arc          { background:rgba(139,92,246,.12);  color:#8b5cf6; border:1px solid rgba(139,92,246,.25); }
.age-pill-episode      { background:rgba(6,182,212,.12);   color:#06b6d4; border:1px solid rgba(6,182,212,.25); }
.age-pill-narrative    { background:rgba(236,72,153,.12);  color:#ec4899; border:1px solid rgba(236,72,153,.25); }
.age-pill-scene_hook   { background:rgba(245,158,11,.12);  color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
.age-pill-default      { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }

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

/* ── Details Modal ── */
#modalDetails { position:fixed; inset:0; background:rgba(0,0,0,0.75); display:none; align-items:flex-start; justify-content:center; z-index:9998; overflow-y:auto; padding:20px; box-sizing:border-box; }
.details-inner { background:var(--card); border:1px solid var(--border); border-radius:12px; width:100%; max-width:780px; min-height:200px; box-shadow:0 20px 60px rgba(0,0,0,0.5); display:flex; flex-direction:column; overflow:hidden; margin:auto; }
.details-header { padding:10px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; flex-shrink:0; background:var(--bg); position:sticky; top:0; z-index:2; }
#details-body { padding:20px 24px; overflow-y:auto; flex:1; font-size:0.95rem; line-height:1.7; color:var(--text); }

#details-body h1,#details-body h2,#details-body h3,#details-body h4,#details-body h5,#details-body h6 { margin:1.2em 0 0.4em; line-height:1.3; font-weight:700; }
#details-body h1 { font-size:1.5rem; border-bottom:1px solid var(--border); padding-bottom:6px; }
#details-body h2 { font-size:1.2rem; border-bottom:1px solid var(--border); padding-bottom:4px; }
#details-body h3 { font-size:1rem; }
#details-body p  { margin:0 0 0.9em; }
#details-body ul,#details-body ol { margin:0 0 0.9em; padding-left:1.6em; }
#details-body li { margin-bottom:0.3em; }
#details-body blockquote { margin:0 0 0.9em; padding:8px 14px; border-left:3px solid var(--accent); background:rgba(9,105,218,0.06); border-radius:0 6px 6px 0; color:var(--text-muted); }
#details-body code { font-family:ui-monospace,monospace; font-size:0.85em; background:var(--bg); border:1px solid var(--border); padding:1px 5px; border-radius:4px; }
#details-body pre { background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:12px 14px; overflow-x:auto; margin:0 0 0.9em; }
#details-body pre code { background:none; border:none; padding:0; }
#details-body hr { border:none; border-top:1px solid var(--border); margin:1.2em 0; }
#details-body a { color:var(--accent); }
#details-body table { border-collapse:collapse; width:100%; margin-bottom:0.9em; font-size:0.88rem; }
#details-body th,#details-body td { border:1px solid var(--border); padding:6px 10px; text-align:left; }
#details-body th { background:var(--bg); font-weight:700; }
#details-body .details-empty { color:var(--text-muted); font-style:italic; text-align:center; padding:30px 0; }

.details-connections { margin-top:28px; border-top:1px solid var(--border); padding-top:16px; }
.details-connections h4 { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin:0 0 10px; }
.details-conn-list { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px; }
.details-conn-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:0.78rem; font-weight:600; border:1px solid var(--border); background:var(--bg); cursor:pointer; color:var(--text); transition:border-color 0.15s, color 0.15s, background 0.15s; }
.details-conn-pill:hover { border-color:var(--accent); color:var(--accent); background:rgba(9,105,218,0.06); }
.details-conn-pill .conn-rel { font-size:0.68rem; font-weight:400; color:var(--text-muted); padding-left:4px; border-left:1px solid var(--border); margin-left:2px; }

#ag-toast { position:fixed; bottom:24px; right:24px; z-index:99999; background:var(--card); color:var(--text); border:1px solid var(--border); border-left:4px solid var(--green); border-radius:6px; padding:12px 18px; font-size:0.9rem; display:none; box-shadow:0 4px 12px rgba(0,0,0,.2); }
</style>

<div class="ag-layout">
    <div class="ag-topbar">
        <h2><i class="bi bi-diagram-3-fill" style="color:var(--accent);"></i> AG Visualizer</h2>

        <select class="ag-doc-selector" onchange="window.location.href='ag_graph.php?doc_id=' + this.value">
            <option value="0">-- Select Document --</option>
            <?php foreach ($allDocs as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id'] == $docId ? 'selected' : '' ?>>
                    [<?= $d['id'] ?>] <?= htmlspecialchars($d['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div style="margin-left: auto; display: flex; gap: 8px;">
            <button class="btn btn-ghost btn-sm" onclick="window.location.href='ag_view.php?doc_id=<?= $docId ?>'"><i class="bi bi-list"></i> Standard Tree View</button>
        </div>
    </div>

    <div class="ag-main">
        <?php if (!$docId): ?>
            <div style="display:flex; flex:1; align-items:center; justify-content:center; color:var(--text-muted); flex-direction:column; gap:10px;">
                <i class="bi bi-folder2-open" style="font-size:3rem;"></i>
                <p>Please select a document from the dropdown above.</p>
            </div>
        <?php else: ?>
            <div id="graph-container"></div>

            <div class="graph-panel panel-left" id="controls-panel">
                <div class="panel-header">
                    <h3>Controls</h3>
                    <button class="collapse-btn" onclick="togglePanel('controls-panel', this)"><i class="bi bi-dash"></i></button>
                </div>
                <div class="panel-content">
                    <button class="btn btn-primary btn-block" id="btn-layout"><i class="bi bi-play-fill"></i> Run ForceAtlas2</button>
                    <button class="btn btn-ghost btn-block" id="btn-reset"><i class="bi bi-arrows-collapse"></i> Reset Camera</button>

                    <div style="margin-top:10px;">
                        <input type="search" id="graph-search"
                            placeholder="&#128269; Search nodes…"
                            style="width:100%; padding:6px 9px; border-radius:6px; border:1px solid var(--border); background:var(--bg); color:var(--text); font-size:0.83rem; box-sizing:border-box; outline:none;"
                            autocomplete="off">
                        <div id="graph-search-count" style="font-size:0.75rem; color:var(--text-muted); margin-top:4px; min-height:16px;"></div>
                    </div>

                    <div style="margin-top:10px; padding-top:10px; border-top: 1px solid var(--border);">
                        <div class="stat">Nodes: <strong id="stat-nodes">0</strong></div>
                        <div class="stat">Edges: <strong id="stat-edges">0</strong></div>
                    </div>
                </div>
            </div>

            <div class="graph-panel panel-right" id="node-panel">
                <div class="panel-header">
                    <h3>Node Details</h3>
                    <button class="collapse-btn" onclick="togglePanel('node-panel', this)"><i class="bi bi-dash"></i></button>
                </div>
                <div class="panel-content">
                    <h3 id="np-name" style="margin-top:0; font-size:0.95rem; word-break:break-word;">Node Name</h3>
                    <div style="margin-bottom: 15px;">
                        <span id="np-type" class="age-type-pill age-pill-default">note</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <button class="btn btn-primary" id="btn-open-node"><i class="bi bi-box-arrow-up-right"></i> Open in Editor</button>
                        <a class="btn btn-ghost" id="btn-open-docs" href="#" target="_blank" rel="noopener" style="display:none;">
                            <i class="bi bi-journal-text"></i> Open in Curated Docs
                        </a>
                        <button class="btn btn-ghost" id="btn-view-details"><i class="bi bi-file-text"></i> View Details</button>
                        <button class="btn btn-ghost" id="btn-add-edge"><i class="bi bi-link"></i> Add Link from here</button>
                        <button class="btn btn-ghost" id="btn-close-panel">Close Panel</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ ADD EDGE MODAL ═══ -->
<div style="position:fixed; inset:0; background:rgba(0,0,0,.55); display:none; align-items:center; justify-content:center; z-index:9999;" id="modalAddEdge">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:10px; padding:20px; width:360px; max-width:94vw; box-shadow:0 10px 30px rgba(0,0,0,.3);">
        <h3 style="margin:0 0 14px 0; font-size:1rem;"><i class="bi bi-link"></i> Add Edge / Link</h3>
        <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 10px;">From: <strong id="edgeSourceLabel"></strong></p>
        <label style="font-size:0.8rem; color:var(--text-muted);">Target Node Search</label>
        <input type="text" id="edgeTargetSearch" placeholder="Type to search nodes..."
            style="width:100%; padding:8px 10px; border-radius:6px; border:1px solid var(--border); background:var(--bg); color:var(--text); font-size:0.9rem; margin-bottom:4px; box-sizing:border-box;"
            autocomplete="off">
        <div id="edgeTargetResults" style="max-height:100px; overflow-y:auto; background:var(--bg); border:1px solid var(--border); border-radius:4px; display:none; margin-bottom:10px; font-size:0.85rem;"></div>
        <input type="hidden" id="edgeTargetId">
        <label style="font-size:0.8rem; color:var(--text-muted);">Relationship Label (optional)</label>
        <input type="text" id="edgeRelationship" placeholder="e.g. causes, knows, part_of"
            style="width:100%; padding:8px 10px; border-radius:6px; border:1px solid var(--border); background:var(--bg); color:var(--text); font-size:0.9rem; margin-bottom:10px; box-sizing:border-box;">
        <div style="display:flex; gap:8px; justify-content:flex-end;">
            <button class="btn btn-ghost" onclick="hideModal('modalAddEdge')">Cancel</button>
            <button class="btn btn-primary" onclick="createEdge()">Link nodes</button>
        </div>
    </div>
</div>

<!-- ═══ DETAILS MODAL ═══ -->
<div id="modalDetails">
    <div class="details-inner">
        <div class="details-header">
            <button id="details-btn-back" onclick="detailsHistoryBack()" title="Back"
                style="background:none;border:1px solid var(--border);color:var(--text-muted);border-radius:5px;padding:3px 8px;cursor:pointer;font-size:1rem;line-height:1;opacity:0.35;" disabled>&#8592;</button>
            <button id="details-btn-fwd" onclick="detailsHistoryFwd()" title="Forward"
                style="background:none;border:1px solid var(--border);color:var(--text-muted);border-radius:5px;padding:3px 8px;cursor:pointer;font-size:1rem;line-height:1;opacity:0.35;" disabled>&#8594;</button>
            <span id="details-title" style="font-weight:700; font-size:0.95rem; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></span>
            <span id="details-type" class="age-type-pill age-pill-default" style="flex-shrink:0;"></span>
            <button onclick="closeDetailsModal()"
                style="background:none; border:none; color:var(--text-muted); font-size:1.4rem; cursor:pointer; line-height:1; padding:2px 6px; border-radius:4px; flex-shrink:0;" title="Close">&times;</button>
        </div>
        <div id="details-body">
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

<div id="ag-toast"></div>

<?php if ($docId > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

<script>
const dbNodes      = <?php echo $jsonNodes; ?>;
const dbEdges      = <?php echo $jsonEdges; ?>;
const currentDocId = <?php echo $docId; ?>;

let graph, renderer;
let isLayoutRunning = false;
let fa2LoopId       = null;
let selectedNode    = null;
let hoveredNode     = null;
let searchMatches   = null; // null = no filter active; Set = active filter

const typeColors = {
    'note': '#64748b', 'relationship': '#ec4899', 'character': '#3b82f6',
    'location': '#10b981', 'event': '#ef4444', 'concept': '#f59e0b',
    'arc': '#8b5cf6', 'episode': '#06b6d4', 'narrative': '#ec4899',
    'scene_hook': '#f59e0b', 'faction': '#a78bfa', 'artifact': '#fb923c',
    'default': '#888888'
};

function getMutedColor() { return document.documentElement.getAttribute('data-theme') === 'dark' ? '#30363d' : '#e2e8f0'; }
function getLabelColor() { return document.documentElement.getAttribute('data-theme') === 'dark' ? '#c9d1d9' : '#24292f'; }

function escapeHtml(text) { return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
function escapeHtmlAttr(str) { return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

// ── Panel drag & collapse ──
function makeDraggable(panelId) {
    const panel  = document.getElementById(panelId);
    const header = panel.querySelector('.panel-header');
    let isDragging = false, startX, startY, initialX, initialY;

    function start(e) {
        if (e.target.closest('.collapse-btn')) return;
        isDragging = true;
        const cx = e.touches ? e.touches[0].clientX : e.clientX;
        const cy = e.touches ? e.touches[0].clientY : e.clientY;
        startX = cx; startY = cy; initialX = panel.offsetLeft; initialY = panel.offsetTop;
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', end);
        document.addEventListener('touchmove', move, { passive: false });
        document.addEventListener('touchend', end);
    }
    function move(e) {
        if (!isDragging) return;
        e.preventDefault();
        const cx = e.touches ? e.touches[0].clientX : e.clientX;
        const cy = e.touches ? e.touches[0].clientY : e.clientY;
        panel.style.left  = (initialX + (cx - startX)) + 'px';
        panel.style.top   = (initialY + (cy - startY)) + 'px';
        panel.style.right = 'auto';
    }
    function end() {
        isDragging = false;
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', end);
        document.removeEventListener('touchmove', move);
        document.removeEventListener('touchend', end);
    }
    header.addEventListener('mousedown', start);
    header.addEventListener('touchstart', start, { passive: false });
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

// ── Node side panel ──
function openNodePanel(nodeId) {
    selectedNode = nodeId;
    const attrs = graph.getNodeAttributes(nodeId);
    document.getElementById('np-name').textContent    = attrs.label;
    document.getElementById('np-type').textContent   = attrs.node_type;
    document.getElementById('np-type').className     = 'age-type-pill age-pill-' + (attrs.node_type || 'default');

    // ── Curated Docs deep-link ──
    const docsBtn = document.getElementById('btn-open-docs');
    const docId   = attrs.doc_id;
    if (docId) {
        const storyTypes = new Set(['episode', 'narrative', 'scene_hook', 'arc']);
        const focusType  = storyTypes.has(attrs.node_type) ? 'story' : 'world';
        const url = 'view_curated_docs.php'
            + '?doc_id='       + encodeURIComponent(docId)
            + '&focus_type='   + encodeURIComponent(focusType)
            + '&focus_entity=' + encodeURIComponent(attrs.label);
        docsBtn.href         = url;
        docsBtn.style.display = 'inline-flex';
    } else {
        docsBtn.style.display = 'none';
    }

    const panel = document.getElementById('node-panel');
    panel.style.display = 'flex';
    document.querySelector('#node-panel .panel-content').style.display = 'block';
    document.querySelector('#node-panel .collapse-btn').innerHTML = '<i class="bi bi-dash"></i>';
    renderer.refresh();
}

// ═══════════════════════════════════════════════
// VISUAL FETCHING AND MODALS
// ═══════════════════════════════════════════════
let visualSwiper = null;

function fetchVisuals(docId, cat, name) {
    const container = document.getElementById('mVisuals');
    const wrapper = document.getElementById('sketchWrapper');
    container.style.display = 'none';
    wrapper.innerHTML = '';
    
    $.post('ag_api.php', {
        action: 'fetch_visuals',
        doc_id: docId,
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
            
            // Set flex before init so swiper can compute width safely
            container.style.display = 'flex';

            if (visualSwiper) {
                visualSwiper.destroy(true, true);
            }
            
            setTimeout(() => {
                visualSwiper = new Swiper('#sketchSwiper', {
                    slidesPerView: 'auto',
                    spaceBetween: 10,
                    freeMode: true,
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                });
            }, 50);
        }
    }, 'json').fail(err => console.error('Error fetching visuals:', err));
}

document.getElementById('curation-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
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
        let themes = Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes :[data.themes.primary_themes];
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

// ═══════════════════════════════════════════════
// DETAILS MODAL
// ═══════════════════════════════════════════════
let detailsHistory =[];
let detailsHistPos = -1;

function openDetailsModal(nodeId, addToHistory = true) {
    if (addToHistory) {
        detailsHistory = detailsHistory.slice(0, detailsHistPos + 1);
        detailsHistory.push(nodeId);
        detailsHistPos = detailsHistory.length - 1;
    }
    detailsUpdateNavButtons();
    openNodePanel(nodeId); // keep side panel in sync

    const attrs = graph.getNodeAttributes(nodeId);
    document.getElementById('details-title').textContent = attrs.label;
    const typeEl = document.getElementById('details-type');
    typeEl.textContent = attrs.node_type;
    typeEl.className   = 'age-type-pill age-pill-' + (attrs.node_type || 'default');

    const textContent = document.getElementById('details-text-content');
    const connContent = document.getElementById('details-connections-content');

    textContent.innerHTML = '<div class="details-empty" style="font-style:italic;">Loading…</div>';
    connContent.innerHTML = '';

    document.getElementById('modalDetails').style.display = 'flex';
    document.getElementById('details-body').scrollTop = 0;

    fetchVisuals(currentDocId, attrs.node_type, attrs.label);

    // Uses ag_api.php with doc_id context
    $.get(`ag_api.php?action=get_node&doc_id=${currentDocId}&id=${nodeId}`, res => {
        if (!res.ok) { textContent.innerHTML = '<div class="details-empty">Failed to load node.</div>'; return; }
        const md = (res.node && res.node.content) ? res.node.content.trim() : '';
        textContent.innerHTML = md
            ? marked.parse(md)
            : '<div class="details-empty">This node has no content yet.</div>';
        connContent.appendChild(detailsBuildConnections(nodeId));
        document.getElementById('details-body').scrollTop = 0;
    }, 'json').fail(() => {
        textContent.innerHTML = '<div class="details-empty">Network error loading content.</div>';
    });
}

function closeDetailsModal() {
    document.getElementById('modalDetails').style.display = 'none';
    detailsHistory =[]; detailsHistPos = -1;
}

function detailsBuildConnections(nodeId) {
    const nid = nodeId.toString();
    const outgoing = [], incoming =[];

    graph.forEachOutboundEdge(nid, (edge, attrs, source, target) => {
        if (target !== nid && graph.hasNode(target))
            outgoing.push({ id: target, label: graph.getNodeAttribute(target, 'label'), type: graph.getNodeAttribute(target, 'node_type'), rel: attrs.label || '' });
    });
    graph.forEachInboundEdge(nid, (edge, attrs, source, target) => {
        if (source !== nid && graph.hasNode(source))
            incoming.push({ id: source, label: graph.getNodeAttribute(source, 'label'), type: graph.getNodeAttribute(source, 'node_type'), rel: attrs.label || '' });
    });

    if (!outgoing.length && !incoming.length) return document.createDocumentFragment();

    const wrap = document.createElement('div');
    wrap.className = 'details-connections';

    const typeColor = { character:'#3b82f6', location:'#10b981', concept:'#f59e0b', event:'#ef4444', arc:'#8b5cf6', episode:'#06b6d4', relationship:'#ec4899', narrative:'#ec4899', scene_hook:'#f59e0b', faction:'#a78bfa', note:'#64748b' };

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
            const dot = document.createElement('span');
            dot.style.cssText = 'width:7px;height:7px;border-radius:50%;flex-shrink:0;background:' + (typeColor[item.type] || '#888');
            pill.appendChild(dot);
            const lbl = document.createElement('span');
            lbl.textContent = item.label;
            pill.appendChild(lbl);
            if (item.rel) { const rel = document.createElement('span'); rel.className = 'conn-rel'; rel.textContent = item.rel; pill.appendChild(rel); }
            pill.addEventListener('click', () => openDetailsModal(item.id));
            list.appendChild(pill);
        });
        wrap.appendChild(list);
    }
    makeSection('Outgoing', incoming);
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
function detailsHistoryBack() { if (detailsHistPos > 0)                             { detailsHistPos--; openDetailsModal(detailsHistory[detailsHistPos], false); } }
function detailsHistoryFwd()  { if (detailsHistPos < detailsHistory.length - 1)    { detailsHistPos++; openDetailsModal(detailsHistory[detailsHistPos], false); } }

// ── Modal helpers ──
function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function hideModal(id) { document.getElementById(id).style.display = 'none'; }

document.getElementById('modalDetails').addEventListener('click', function(e) { if (e.target === this) closeDetailsModal(); });

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const viewModal = document.getElementById('viewModal');
        const curationModal = document.getElementById('curation-modal');
        if (viewModal && viewModal.classList.contains('active')) {
            closeFrameModal();
        } else if (curationModal && curationModal.style.display === 'flex') {
            curationModal.style.display = 'none';
        } else {
            closeDetailsModal();
            hideModal('modalAddEdge');
        }
    }
});

// ── Toast ──
let toastTimer;
function toast(msg, type = 'success') {
    const el = document.getElementById('ag-toast');
    el.textContent = msg;
    el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display = 'none', 2800);
}

// ═══════════════════════════════════════════════
// GRAPH INIT
// ═══════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    makeDraggable('controls-panel');
    makeDraggable('node-panel');

    graph = new graphology.MultiDirectedGraph();

    dbNodes.forEach(n => {
        graph.addNode(n.id.toString(), {
            x: Math.random() * 100, y: Math.random() * 100,
            size: 4, label: n.name,
            color: typeColors[n.node_type] || typeColors['default'],
            node_type: n.node_type || 'note',
            doc_id: n.doc_id || null
        });
    });

    dbEdges.forEach(e => {
        const s = e.source.toString(), t = e.target.toString();
        if (graph.hasNode(s) && graph.hasNode(t)) {
            graph.addDirectedEdge(s, t, { label: e.relationship || '', size: 1, color: getMutedColor() });
        }
    });

    // Size nodes by degree
    graph.forEachNode(node => {
        graph.setNodeAttribute(node, 'size', 4 + Math.sqrt(graph.degree(node)) * 1.5);
    });
    document.getElementById('stat-nodes').textContent = graph.order;
    document.getElementById('stat-edges').textContent = graph.size;

    const container = document.getElementById('graph-container');
    renderer = new Sigma(graph, container, {
        renderEdgeLabels:  true,
        defaultEdgeType:   "arrow",
        allowInvalidContainer: true,
        labelColor:      { color: getLabelColor() },
        edgeLabelColor:  { color: getLabelColor() },
        edgeLabelSize:   7
    });

    new MutationObserver(() => {
        renderer.setSetting('labelColor',     { color: getLabelColor() });
        renderer.setSetting('edgeLabelColor', { color: getLabelColor() });
        renderer.refresh();
    }).observe(document.documentElement, { attributes: true, attributeFilter:['data-theme'] });

    // ── ForceAtlas2 ──
    const fa2    = graphologyLibrary.layoutForceAtlas2;
    const fa2Btn = document.getElementById('btn-layout');

    function toggleLayout() {
        if (isLayoutRunning) {
            cancelAnimationFrame(fa2LoopId); isLayoutRunning = false;
            fa2Btn.innerHTML = '<i class="bi bi-play-fill"></i> Run ForceAtlas2';
        } else {
            isLayoutRunning = true;
            fa2Btn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop ForceAtlas2';
            const settings = { barnesHutOptimize: graph.order > 100, strongGravityMode: true, gravity: 0.05, scalingRatio: 10, slowDown: 10 };
            function step() { fa2.assign(graph, { iterations: 1, settings }); renderer.refresh(); if (isLayoutRunning) fa2LoopId = requestAnimationFrame(step); }
            step();
        }
    }
    fa2Btn.addEventListener('click', toggleLayout);
    toggleLayout();
    setTimeout(() => { if (isLayoutRunning) toggleLayout(); }, 2500);

    document.getElementById('btn-reset').addEventListener('click', () => renderer.getCamera().animatedReset({ duration: 500 }));

    // ── Node drag (tap vs drag detection) ──
    let dragNode = null, dragStartX = 0, dragStartY = 0;
    const DRAG_THRESHOLD = 6;

    renderer.on("downNode", e => {
        dragNode = e.node;
        const ne = e.event && e.event.original ? e.event.original : (e.event || {});
        dragStartX = (ne.touches && ne.touches.length) ? ne.touches[0].clientX : (ne.clientX || 0);
        dragStartY = (ne.touches && ne.touches.length) ? ne.touches[0].clientY : (ne.clientY || 0);
        renderer.getCamera().disable();
    });

    renderer.getMouseCaptor().on("mousemovebody", e => {
        if (!dragNode) return;
        const pos = renderer.viewportToGraph(e);
        graph.setNodeAttribute(dragNode, "x", pos.x);
        graph.setNodeAttribute(dragNode, "y", pos.y);
        e.preventSigmaDefault(); e.original.preventDefault(); e.original.stopPropagation();
    });

    container.addEventListener('touchmove', e => {
        if (!dragNode) return;
        const rect  = container.getBoundingClientRect();
        const touch = e.touches[0];
        const pos   = renderer.viewportToGraph({ x: touch.clientX - rect.left, y: touch.clientY - rect.top });
        graph.setNodeAttribute(dragNode, "x", pos.x);
        graph.setNodeAttribute(dragNode, "y", pos.y);
        e.preventDefault();
    }, { passive: false });

    function releaseNode(e) {
        if (!dragNode) return;
        let endX = (e.changedTouches && e.changedTouches.length) ? e.changedTouches[0].clientX : (e.clientX || dragStartX);
        let endY = (e.changedTouches && e.changedTouches.length) ? e.changedTouches[0].clientY : (e.clientY || dragStartY);
        const dist = Math.sqrt(Math.pow(endX - dragStartX, 2) + Math.pow(endY - dragStartY, 2));
        if (dist < DRAG_THRESHOLD) openNodePanel(dragNode);
        renderer.getCamera().enable();
        dragNode = null;
    }
    window.addEventListener('mouseup', releaseNode);
    window.addEventListener('touchend', releaseNode);

    renderer.on("clickNode", ({ node }) => { if (!selectedNode || selectedNode !== node) openNodePanel(node); });
    renderer.on("clickStage", () => { selectedNode = null; document.getElementById('node-panel').style.display = 'none'; renderer.refresh(); });
    renderer.on('enterNode', ({ node }) => { hoveredNode = node; renderer.refresh(); });
    renderer.on('leaveNode', ()         => { hoveredNode = null; renderer.refresh(); });

    // ── Node/Edge reducers with search support ──
    renderer.setSetting('nodeReducer', (node, data) => {
        const res   = { ...data };
        const muted = getMutedColor();

        if (searchMatches !== null) {
            if (!searchMatches.has(node)) { res.color = muted; res.label = ''; res.zIndex = 0; }
            else { res.zIndex = 2; res.size = (data.size || 4) * 1.4; }
            return res;
        }
        if (hoveredNode && hoveredNode !== node && !graph.hasEdge(node, hoveredNode) && !graph.hasEdge(hoveredNode, node)) {
            res.color = muted; res.zIndex = 0;
        } else if (selectedNode && selectedNode !== node && !graph.hasEdge(node, selectedNode) && !graph.hasEdge(selectedNode, node)) {
            res.color = muted; res.zIndex = 0;
        } else { res.zIndex = 1; }
        if (node === hoveredNode || node === selectedNode) res.zIndex = 2;
        return res;
    });

    renderer.setSetting('edgeReducer', (edge, data) => {
        const res    = { ...data };
        const source = graph.source(edge), target = graph.target(edge);
        const muted  = getMutedColor();

        if (searchMatches !== null) {
            if (!searchMatches.has(source) && !searchMatches.has(target)) res.hidden = true;
            return res;
        }
        if (hoveredNode && source !== hoveredNode && target !== hoveredNode) { res.color = muted; res.hidden = true; }
        else if (selectedNode && source !== selectedNode && target !== selectedNode) { res.color = muted; res.hidden = true; }
        else if (hoveredNode || selectedNode) { res.size = 2; res.color = document.documentElement.getAttribute('data-theme') === 'dark' ? '#6b7280' : '#94a3b8'; }
        return res;
    });

    // ── Node panel buttons ──
    document.getElementById('btn-close-panel').addEventListener('click', () => {
        selectedNode = null; document.getElementById('node-panel').style.display = 'none'; renderer.refresh();
    });
    document.getElementById('btn-open-node').addEventListener('click', () => {
        if (selectedNode) window.open(`ag_view.php?doc_id=${currentDocId}&node_id=${selectedNode}`, '_blank');
    });
    document.getElementById('btn-view-details').addEventListener('click', () => {
        if (selectedNode) openDetailsModal(selectedNode);
    });
    document.getElementById('btn-add-edge').addEventListener('click', () => {
        if (selectedNode) {
            const attrs = graph.getNodeAttributes(selectedNode);
            document.getElementById('edgeSourceLabel').textContent = attrs.label + ' (#' + selectedNode + ')';
            showModal('modalAddEdge');
        }
    });

    // ── Search ──
    document.getElementById('graph-search').addEventListener('input', e => {
        const q = e.target.value.trim().toLowerCase();
        const countEl = document.getElementById('graph-search-count');
        if (!q) { searchMatches = null; countEl.textContent = ''; renderer.refresh(); return; }
        searchMatches = new Set();
        graph.forEachNode((node, attrs) => { if (attrs.label && attrs.label.toLowerCase().includes(q)) searchMatches.add(node); });
        const n = searchMatches.size;
        countEl.textContent = n === 0 ? 'No matches' : n + ' node' + (n === 1 ? '' : 's') + ' matched';
        countEl.style.color = n === 0 ? 'var(--red)' : 'var(--text-muted)';
        renderer.refresh();
    });

    // ── Add edge target search ──
    const searchInput   = document.getElementById('edgeTargetSearch');
    const searchResults = document.getElementById('edgeTargetResults');
    const targetInput   = document.getElementById('edgeTargetId');

    searchInput.addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        searchResults.innerHTML = '';
        if (q.length < 2) { searchResults.style.display = 'none'; return; }
        const matches = graph.nodes().filter(n => graph.getNodeAttribute(n, 'label').toLowerCase().includes(q));
        if (matches.length > 0) {
            matches.slice(0, 10).forEach(n => {
                const label = graph.getNodeAttribute(n, 'label');
                const div   = document.createElement('div');
                div.textContent = label + ' (#' + n + ')';
                div.style.padding = '6px 10px'; div.style.cursor = 'pointer';
                div.addEventListener('click', () => { targetInput.value = n; searchInput.value = label; searchResults.style.display = 'none'; });
                div.addEventListener('mouseover', () => div.style.background = 'var(--border)');
                div.addEventListener('mouseout',  () => div.style.background = 'transparent');
                searchResults.appendChild(div);
            });
            searchResults.style.display = 'block';
        } else { searchResults.style.display = 'none'; }
    });
});

// ═══════════════════════════════════════════════
// GRAPH MUTATIONS
// ═══════════════════════════════════════════════
function createEdge() {
    if (!selectedNode) return;
    const targetId = document.getElementById('edgeTargetId').value;
    const rel      = document.getElementById('edgeRelationship').value.trim();

    if (!targetId || !graph.hasNode(targetId.toString())) { toast('Invalid target node', 'error'); return; }
    if (targetId.toString() === selectedNode.toString())  { toast('Cannot link node to itself', 'error'); return; }

    const targetLabel = graph.getNodeAttribute(targetId, 'label');

    $.post('ag_api.php', {
        action: 'add_item', doc_id: currentDocId,
        node_id:      selectedNode,
        item_type:    'ag_node',
        item_id:      targetId,
        item_label:   targetLabel,
        relationship: rel,
        note:         ''
    }, res => {
        if (res.ok) {
            hideModal('modalAddEdge');
            document.getElementById('edgeTargetId').value      = '';
            document.getElementById('edgeTargetSearch').value  = '';
            document.getElementById('edgeRelationship').value  = '';
            graph.addDirectedEdge(selectedNode, targetId, { label: rel || '', size: 1, color: getMutedColor() });
            renderer.refresh();
            toast('Link added');
        } else { toast('Error: ' + res.error, 'error'); }
    }, 'json');
}
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>