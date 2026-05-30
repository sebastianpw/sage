<?php
// public/locahub.php
// LocaHub — Location Sources Browser
// Aggregates locations from: locations table, fuzz candidates (promoted, type=location),
// KG nodes (node_type=location), AG nodes (node_type=location),
// and sketches via sketch_location_ranges helper table.
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// ── Source definitions ──────────────────────────────────
// Each source has: key, label, icon, color CSS var
$locationSources = [
    'locations'  => ['label' => 'Locations',      'icon' => '📍', 'color' => '--amber'],
    'fuzz'       => ['label' => 'Fuzz Promoted',  'icon' => '🧩', 'color' => '--purple'],
    'kg'         => ['label' => 'KG Nodes',        'icon' => '🌳', 'color' => '--teal'],
    'ag'         => ['label' => 'AG Nodes',        'icon' => '⚡', 'color' => '--teal'],
    'sketches'   => ['label' => 'Sketch Ranges',  'icon' => '🎨', 'color' => '--red'],
];

$selectedSource = $_REQUEST['source'] ?? 'locations';
if (!array_key_exists($selectedSource, $locationSources)) {
    $selectedSource = 'locations';
}

$deepLinkId     = isset($_GET['item_id'])   ? (int)$_GET['item_id']   : 0;
$deepLinkSearch = isset($_GET['search'])    ? $_GET['search']          : '';

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    require_once __DIR__ . '/locahub_api.php';
}

// --------------------- Page render ---------------------
$pageTitle = 'LocaHub — Location Sources';
ob_start();
?>
<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>

<style>
:root {
    --bg: #0a0a0f;
    --card: #111118;
    --border: #1e1e2e;
    --text: #e2e2f0;
    --text-muted: #555570;
    --purple: #8b5cf6;
    --purple-dim: rgba(139, 92, 246, 0.1);
    --amber: #f59e0b;
    --amber-dim: rgba(245, 158, 11, 0.1);
    --red: #ef4444;
    --red-dim: rgba(239, 68, 68, 0.1);
    --teal: #14b8a6;
    --teal-dim: rgba(20, 184, 166, 0.12);
}
[data-theme="light"] {
    --bg: #f4f4f8; --card: #ffffff; --border: #d0d0e0; --text: #1a1a2e; --text-muted: #888899;
}
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }
.lh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* Header */
.lh-header {
    flex-shrink: 0; padding: 0 16px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.lh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.lh-title .lh-icon { color: var(--amber); }

/* Source Tabs */
.source-tabs {
    flex-shrink: 0; display: flex; overflow-x: auto; scrollbar-width: none;
    background: var(--card); border-bottom: 1px solid var(--border);
}
.source-tabs::-webkit-scrollbar { display: none; }
.source-tab {
    flex-shrink: 0; padding: 8px 14px; font-size: 0.65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer;
    color: var(--text-muted); border-bottom: 2px solid transparent; transition: 0.15s;
    white-space: nowrap; display: flex; align-items: center; gap: 5px;
}
.source-tab:hover { color: var(--text); }
.source-tab.active { color: var(--amber); border-bottom-color: var(--amber); }

/* Controls row */
.mr-controls-row {
    display: flex; gap: 8px; padding: 8px 12px;
    border-bottom: 1px solid var(--border); align-items: center;
    background: var(--card);
}
.mr-search-input {
    flex: 1; min-width: 0; padding: 6px 10px; border-radius: 4px;
    border: 1px solid var(--border); background: var(--bg); color: var(--text);
    font-family: inherit; font-size: 0.8rem;
}
.mr-search-input:focus { outline: none; border-color: var(--amber); }
.mr-pagination { display: flex; align-items: center; gap: 4px; }
.pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--amber); color: var(--amber); }
.pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--amber); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
.pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

/* Selection bar */
.mr-selection-bar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 12px; background: var(--card); border-bottom: 1px solid var(--border);
    font-size: 0.7rem;
}

/* Source info bar */
.source-info-bar {
    flex-shrink: 0; padding: 5px 12px;
    background: var(--bg); border-bottom: 1px solid var(--border);
    font-size: 0.68rem; color: var(--text-muted);
    display: flex; align-items: center; gap: 8px;
}
.source-badge {
    padding: 2px 8px; border-radius: 20px; font-size: 0.6rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.source-badge.locations { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
.source-badge.fuzz      { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(139,92,246,0.3); }
.source-badge.kg        { background: var(--teal-dim); color: var(--teal); border: 1px solid rgba(20,184,166,0.3); }
.source-badge.ag        { background: var(--teal-dim); color: var(--teal); border: 1px solid rgba(20,184,166,0.3); }
.source-badge.sketches  { background: var(--red-dim); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }

/* List panel */
.lh-top-panel { flex-shrink: 0; }
.mr-list-scroll { overflow-y: auto; overflow-x: hidden; min-height: 60px; max-height: 220px; }
.mr-item {
    padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer;
    transition: background 0.15s; display: flex; align-items: center; gap: 10px;
}
.mr-item:hover { background: rgba(255,255,255,0.05); }
.mr-item.active { background: var(--amber-dim); border-left: 3px solid var(--amber); padding-left: 9px; }
.mr-item-chk { margin-right: 2px; accent-color: var(--amber); transform: scale(1.1); cursor: pointer; }
.mr-id { font-size: 0.7rem; font-weight: 700; color: var(--amber); min-width: 44px; }
.mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

/* Grid toolbar */
.grid-toolbar { background: rgba(0,0,0,0.2); display: flex; flex-direction: column; }
.gt-row1 {
    padding: 6px 12px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.gt-row2 { padding: 5px 12px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.gt-info { font-size: 0.7rem; color: var(--text-muted); }
.gt-actions { display: flex; align-items: center; gap: 8px; }
.action-btn {
    padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    cursor: pointer; text-transform: uppercase; font-family: inherit;
    text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
}
.action-btn:hover { color: var(--amber); border-color: var(--amber); }
.action-btn.primary { border-color: var(--amber); color: var(--amber); }
.chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; }

/* Grid */
.lh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: var(--bg); min-height: 0; }
.frames-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding-bottom: 20px; }
.frames-grid.one-col { grid-template-columns: 1fr; }
@media (min-width: 600px) {
    .frames-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}
.f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.f-card.selected { border-color: var(--amber); box-shadow: 0 0 0 1px var(--amber); }
.f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.03); }
.f-view-btn {
    position: absolute; top: 5px; right: 5px; width: 24px; height: 24px;
    background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px;
}
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--amber); border-color: var(--amber); color: #000; }
.f-label {
    position: absolute; bottom: 0; left: 0; right: 0; height: 24px;
    background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa;
    border-top: 1px solid var(--border); display: flex; align-items: center;
    justify-content: space-between; cursor: pointer; user-select: none; z-index: 2;
}
.f-card.selected .f-label { background: rgba(245,158,11,0.15); color: var(--amber); border-top-color: var(--amber); }
.f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; flex-shrink: 0; }
.f-card.selected .f-select-trigger { background: var(--amber); border-color: var(--amber); color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }

/* Footer */
.lh-footer {
    flex-shrink: 0; padding: 10px 16px;
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    background: var(--card); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    z-index: 10; position: relative;
}
.ft-summary { font-size: 0.75rem; color: var(--text-muted); }
.ft-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.btn-action {
    padding: 10px 12px; border-radius: 4px; border: none;
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    cursor: pointer; font-family: inherit; transition: filter 0.15s;
    color: #fff; min-width: 0; white-space: nowrap;
}
@media (min-width: 600px) {
    .ft-actions { gap: 10px; flex-wrap: nowrap; }
    .btn-action { padding: 12px 20px; font-size: 0.85rem; min-width: 100px; }
}
.btn-action:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }
.btn-migrate { background: var(--amber); color: #000; }

/* State messages */
.state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--text); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Frame viewer modal */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: var(--amber); color: #000; border-color: var(--amber); }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }

/* Migrate confirm modal */
.migrate-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85);
    z-index: 200000; display: none; align-items: flex-end; justify-content: center;
}
.migrate-backdrop.active { display: flex; }
.migrate-sheet {
    width: 100%; max-width: 520px;
    background: var(--card); border: 1px solid var(--border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    padding: 0 0 max(16px, env(safe-area-inset-bottom));
    font-family: 'DM Mono', 'Fira Mono', monospace;
    max-height: 70vh; display: flex; flex-direction: column;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
}
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.ms-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.ms-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.ms-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.ms-title { font-size: 0.8rem; font-weight: 700; color: var(--amber); text-transform: uppercase; letter-spacing: 1px; }
.ms-close { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.ms-close:hover { color: var(--text); border-color: var(--text); }
.ms-body { padding: 16px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 12px; }
.ms-field label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); display: block; margin-bottom: 4px; font-weight: 700; }
.ms-field input, .ms-field textarea, .ms-field select {
    width: 100%; padding: 7px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: rgba(0,0,0,0.3); color: var(--text); font-family: inherit; font-size: 0.8rem;
    outline: none;
}
.ms-field input:focus, .ms-field textarea:focus, .ms-field select:focus { border-color: var(--amber); }
.ms-field textarea { resize: vertical; min-height: 60px; }
.ms-footer { padding: 12px 16px 0; flex-shrink: 0; display: flex; gap: 8px; }
.ms-submit {
    flex: 1; padding: 13px; border-radius: 5px; border: none;
    background: var(--amber); color: #000; font-family: inherit;
    font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    cursor: pointer; transition: filter 0.15s;
}
.ms-submit:hover { filter: brightness(1.1); }
.ms-cancel {
    padding: 13px 16px; border-radius: 5px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.75rem; cursor: pointer; transition: all 0.15s;
}
.ms-cancel:hover { border-color: var(--red); color: var(--red); }

/* Batch Auto Migrate Modal elements */
.batch-terminal {
    background: #000; color: #4ade80; font-family: 'DM Mono', monospace;
    font-size: 0.7rem; padding: 10px; border-radius: 4px; height: 180px; 
    overflow-y: auto; border: 1px solid #333; margin-top: 10px;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.8);
}
.batch-terminal p { margin: 0 0 4px 0; word-break: break-all; }
.batch-terminal p.error { color: #ef4444; }
.batch-terminal p.info { color: #3b82f6; }

/* Sketch ranges info panel */
.ranges-info {
    margin: 10px 12px; padding: 10px 12px; border-radius: 6px;
    background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3);
    font-size: 0.72rem; color: var(--text-muted); display: none;
}
.ranges-info.active { display: block; }
.ranges-info strong { color: var(--red); }

.pswp { z-index: 99999; }
</style>

<div class="lh-layout">
    <!-- Header -->
    <div class="lh-header">
        <div class="lh-title">
            <span class="lh-icon">📍</span>
            LocaHub
            <span style="font-size:0.7em; opacity:0.6; margin-left:8px;">Location Sources</span>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="/locations.php" target="_blank" class="action-btn" title="Manage Locations Table" style="border-color:var(--amber); color:var(--amber);">
                <i class="bi bi-geo-alt"></i> Locations
            </a>
        </div>
    </div>

    <!-- Source Tabs -->
    <div class="source-tabs" id="sourceTabs">
        <?php foreach ($locationSources as $key => $src): ?>
            <div class="source-tab <?php echo $key === $selectedSource ? 'active' : ''; ?>"
                 data-source="<?php echo htmlspecialchars($key); ?>"
                 onclick="selectSource('<?php echo htmlspecialchars($key); ?>')">
                <?php echo $src['icon'] . ' ' . $src['label']; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Source Info Bar -->
    <div class="source-info-bar" id="sourceInfoBar">
        <span class="source-badge <?php echo $selectedSource; ?>" id="sourceBadge">
            <?php echo $locationSources[$selectedSource]['icon'] . ' ' . $locationSources[$selectedSource]['label']; ?>
        </span>
        <span id="sourceDescription"></span>
    </div>

    <!-- Controls Row -->
    <div class="lh-top-panel">
        <div class="mr-controls-row">
            <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search locations..." oninput="debounceSearch()">
            <div class="mr-pagination">
                <button class="pg-btn" id="mrPrev" onclick="changePage(-1)">&#8592;</button>
                <input type="number" class="pg-input" id="mrPageInput" value="1" onchange="jumpToPage()">
                <span class="pg-total" id="mrTotalPages">/ 1</span>
                <button class="pg-btn" id="mrNext" onclick="changePage(1)">&#8594;</button>
            </div>
        </div>
        
        <!-- Selection Bar (Hidden when source is locations) -->
        <div class="mr-selection-bar" id="selectionBar">
            <div style="display:flex; gap:6px;">
                <button class="action-btn" onclick="toggleAllItems(true)">Check All</button>
                <button class="action-btn" onclick="toggleAllItems(false)">None</button>
                <button class="action-btn" id="btnAutoBatch" style="display:none; color:var(--teal); border-color:var(--teal);" onclick="openAutoBatchModal()">
                    <i class="bi bi-robot"></i> Auto-Batch
                </button>
            </div>
            <div id="itemSelectionCount" style="color:var(--amber); font-weight:bold;">0 items selected</div>
        </div>

        <!-- Sketch Ranges info (only shown for sketches source) -->
        <div class="ranges-info" id="rangesInfo">
            <strong>Sketch Location Ranges</strong> — This source uses the <code>sketch_location_ranges</code> table.
            Insert ranges manually in phpMyAdmin: <code>(label, sketch_id_from, sketch_id_to, notes)</code>.
            Frames for sketches within each range are shown below.
        </div>

        <!-- Item list -->
        <div class="mr-list-scroll" id="mrList">
            <div class="state-msg">Loading...</div>
        </div>
    </div>

    <!-- Grid Toolbar -->
    <div class="grid-toolbar">
        <div class="gt-row1">
            <div class="gt-info" id="gridInfo">Select an item above</div>
            <div class="gt-actions" id="gridActions" style="display:none;">
                <button class="action-btn" onclick="toggleAllFrames(false)">None</button>
                <button class="action-btn primary" onclick="toggleAllFrames(true)">All</button>
            </div>
        </div>
        <div class="gt-row2">
            <label class="chk-label" title="Show one column grid for larger images">
                <input type="checkbox" id="oneColGrid" onchange="toggleGridCols()" style="accent-color: var(--teal);"> 1Col
            </label>
        </div>
    </div>

    <!-- Frame Grid -->
    <div class="lh-grid-area">
        <div class="state-msg" id="gridState"><div>&#8593; Select an Item</div></div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- Footer -->
    <div class="lh-footer" id="mainFooter">
        <div class="ft-summary" id="footerSummary">0 selected</div>
        <div class="ft-actions">
            <button class="btn-action btn-migrate" id="btnMigrate" disabled onclick="openMigrateModal()">
                <i class="bi bi-arrow-right-circle"></i> Migrate to Locations
            </button>
        </div>
    </div>
</div>

<!-- Frame viewer Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<!-- Standard Migrate Modal -->
<div class="migrate-backdrop" id="migrateBackdrop" onmousedown="onMigrateBackdropClick(event)">
    <div class="migrate-sheet" id="migrateSheet">
        <div class="ms-handle" onclick="closeMigrateModal()"><div class="ms-handle-bar"></div></div>
        <div class="ms-header">
            <div class="ms-title"><i class="bi bi-arrow-right-circle"></i> Migrate to Locations</div>
            <button type="button" class="ms-close" onclick="closeMigrateModal()"><i class="bi bi-x"></i></button>
        </div>
        <div class="ms-body">
            <div id="migrateBatchInfo" style="display:none; font-size:0.8rem; color:var(--text); margin-bottom:10px;"></div>
            <p id="migrateDescText" style="font-size:0.75rem; color:var(--text-muted); margin:0;">
                This will create a new entry in the <strong style="color:var(--amber);">locations</strong> table.
            </p>
            <div class="ms-field" id="migrateNameField">
                <label>Name</label>
                <input type="text" id="migrateNameInput" placeholder="Location name...">
            </div>
            <div class="ms-field" id="migrateDescField">
                <label>Description</label>
                <textarea id="migrateDescInput" rows="3" placeholder="Optional description..."></textarea>
            </div>
            <div class="ms-field">
                <label id="migrateTypeLabel">Type</label>
                <input type="text" id="migrateTypeInput" placeholder="e.g. interior, exterior, planet, station...">
            </div>
            <div id="migrateSourceInfo" style="font-size:0.65rem; color:var(--text-muted); padding:6px 0; border-top:1px solid var(--border);"></div>
        </div>
        <div class="ms-footer">
            <button type="button" class="ms-cancel" onclick="closeMigrateModal()">Cancel</button>
            <button type="button" class="ms-submit" onclick="submitMigration()">
                <i class="bi bi-check-lg"></i> Save to Locations
            </button>
        </div>
    </div>
</div>

<!-- Auto-Batch Migrate Modal -->
<div class="migrate-backdrop" id="autoBatchBackdrop" onmousedown="onAutoBatchBackdropClick(event)">
    <div class="migrate-sheet">
        <div class="ms-handle" onclick="closeAutoBatchModal()"><div class="ms-handle-bar"></div></div>
        <div class="ms-header">
            <div class="ms-title" style="color:var(--teal);"><i class="bi bi-robot"></i> Auto-Batch Migration</div>
            <button type="button" class="ms-close" onclick="closeAutoBatchModal()"><i class="bi bi-x"></i></button>
        </div>
        <div class="ms-body">
            <p style="font-size:0.75rem; color:var(--text-muted); margin:0;">
                This tool automatically migrates nodes from the selected source into the locations table. 
                Nodes already migrated (based on their origin_id) will be skipped safely.
            </p>
            <div style="padding: 10px; background:rgba(0,0,0,0.2); border:1px solid var(--border); border-radius:4px; margin-top:8px;">
                <div style="font-size:0.75rem; font-weight:bold; color:var(--text);">
                    Unmigrated items found: <span id="abUnmigratedCount" style="color:var(--teal);">Loading...</span>
                </div>
            </div>
            <div class="ms-field" style="margin-top:10px;">
                <label>Limit (Leave blank for ALL)</label>
                <input type="number" id="abLimitInput" placeholder="e.g. 50">
            </div>
            
            <div class="batch-terminal" id="abTerminal">
                <p class="info">Ready to process.</p>
            </div>
        </div>
        <div class="ms-footer">
            <button type="button" class="ms-cancel" id="btnAbCancel" onclick="closeAutoBatchModal()">Close</button>
            <button type="button" class="ms-submit" id="btnAbStart" onclick="startAutoBatch()" style="background:var(--teal);">
                <i class="bi bi-play-fill"></i> Start Batch
            </button>
        </div>
    </div>
</div>

<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
// ── State ──────────────────────────────────────────────
let currentSource = <?php echo json_encode($selectedSource); ?>;
let curPage = 1, totalPages = 1;
let currentFrames = [], selectedFrameIds = new Set();
let currentListedItems = [];
let checkedItems = new Map();
let currentItemId = null;
let debounceTimer;

let autoBatchActive = false;
let autoBatchStop = false;

const SOURCE_DESCRIPTIONS = {
    locations: 'Canonical locations from the locations table.',
    fuzz:      'Fuzz candidates with concept_type=location and status=promoted.',
    kg:        'KG nodes with node_type=location (manually curated knowledge graph).',
    ag:        'AG nodes with node_type=location (automatically generated graphs).',
    sketches:  'Sketch ID ranges defined in sketch_location_ranges table.'
};

const SOURCE_LIMITS = {
    locations: 6, fuzz: 6, kg: 6, ag: 6, sketches: 6
};

// ── Init ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    try {
        if (localStorage.getItem('locahub_one_col') === '1') {
            document.getElementById('oneColGrid').checked = true;
            document.getElementById('framesGrid').classList.add('one-col');
        }
    } catch(e) {}

    updateSourceUI();
    loadList(1);
});

// ── Source switching ───────────────────────────────────
function selectSource(source) {
    currentSource = source;
    currentItemId = null;
    checkedItems.clear();
    selectedFrameIds.clear();
    document.getElementById('mrSearch').value = '';
    curPage = 1;
    updateSourceUI();
    updateItemSelectionUI();
    loadList(1);
    resetGrid();
}

function updateSourceUI() {
    // Tabs
    document.querySelectorAll('.source-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.source === currentSource);
    });
    
    // Badge & Desc
    const badgeEl = document.getElementById('sourceBadge');
    const sources = <?php echo json_encode($locationSources); ?>;
    const src = sources[currentSource];
    badgeEl.className = `source-badge ${currentSource}`;
    badgeEl.textContent = (src ? src.icon + ' ' + src.label : currentSource);
    document.getElementById('sourceDescription').textContent = SOURCE_DESCRIPTIONS[currentSource] || '';
    
    // UI Toggles (Hide selection/footer for 'locations')
    const isLoc = currentSource === 'locations';
    document.getElementById('selectionBar').style.display = isLoc ? 'none' : 'flex';
    document.getElementById('mainFooter').style.display = isLoc ? 'none' : 'flex';
    
    // Auto Batch Toggle (Only for KG/AG)
    const canBatch = currentSource === 'kg' || currentSource === 'ag';
    document.getElementById('btnAutoBatch').style.display = canBatch ? 'inline-flex' : 'none';

    // Sketch ranges info
    document.getElementById('rangesInfo').classList.toggle('active', currentSource === 'sketches');
    
    // Search placeholder
    const placeholders = {
        locations: 'Search locations...', fuzz: 'Search fuzz candidates....',
        kg: 'Search KG nodes...', ag: 'Search AG nodes...', sketches: 'Search sketch ranges...'
    };
    document.getElementById('mrSearch').placeholder = placeholders[currentSource] || 'Search...';
}

// ── List loading & Selection ───────────────────────────
function debounceSearch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadList(1), 300); }
function changePage(d)    { const n = curPage + d; if (n >= 1 && n <= totalPages) loadList(n); }
function jumpToPage()     { const v = parseInt(document.getElementById('mrPageInput').value); if (v >= 1 && v <= totalPages) loadList(v); }

function loadList(page) {
    const list   = document.getElementById('mrList');
    const search = document.getElementById('mrSearch').value.trim();
    if (page === 1 && list) list.scrollTop = 0;
    const limit  = SOURCE_LIMITS[currentSource] || 6;

    fetch(`?api_action=get_items&source=${encodeURIComponent(currentSource)}&limit=${limit}&offset=${(page-1)*limit}&search=${encodeURIComponent(search)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = `<div class="state-msg"><div>${esc(res.message||'Error')}</div></div>`; return; }
            curPage = page;
            totalPages = Math.ceil(res.total / limit) || 1;
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;

            currentListedItems = res.data || [];
            list.innerHTML = '';
            
            if (!currentListedItems.length) {
                list.innerHTML = `<div class="state-msg" style="padding:16px;"><div>No items found for this source</div></div>`;
                return;
            }
            
            const showCheckbox = currentSource !== 'locations';

            currentListedItems.forEach(item => {
                const isActive = item.id == currentItemId;
                const isChecked = checkedItems.has(item.id);
                const el = document.createElement('div');
                el.className = `mr-item ${isActive ? 'active' : ''}`;
                el.dataset.id = item.id;
                el.onclick = () => selectItem(item, el);
                
                const chkHtml = showCheckbox ? `<input type="checkbox" class="mr-item-chk" value="${item.id}" ${isChecked ? 'checked' : ''} onclick="toggleItemCheck(event, ${item.id})">` : '';
                
                el.innerHTML = `
                    ${chkHtml}
                    <div class="mr-id">#${item.id}</div>
                    <div class="mr-note">${esc(item.name || 'Unnamed')}</div>
                    <div class="mr-meta">${esc(item.meta || '')}</div>`;
                list.appendChild(el);
            });
        })
        .catch(() => { list.innerHTML = '<div class="state-msg"><div>Network error</div></div>'; });
}

function toggleItemCheck(e, id) {
    e.stopPropagation();
    const item = currentListedItems.find(i => i.id == id);
    if (!item) return;
    
    if (e.target.checked) checkedItems.set(id, item);
    else checkedItems.delete(id);
    
    updateItemSelectionUI();
}

function toggleAllItems(select) {
    document.querySelectorAll('.mr-item-chk').forEach(chk => {
        chk.checked = select;
        const id = parseInt(chk.value);
        const item = currentListedItems.find(i => i.id == id);
        if (select && item) checkedItems.set(id, item);
        else checkedItems.delete(id);
    });
    updateItemSelectionUI();
}

function updateItemSelectionUI() {
    const count = checkedItems.size;
    document.getElementById('itemSelectionCount').textContent = `${count} item${count !== 1 ? 's' : ''} selected`;
    const btn = document.getElementById('btnMigrate');
    
    if (count > 0) {
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-arrow-right-circle"></i> Migrate (${count})`;
    } else {
        btn.disabled = true;
        btn.innerHTML = `<i class="bi bi-arrow-right-circle"></i> Migrate to Locations`;
    }
}

// ── Item selection → load frames ───────────────────────
function selectItem(item, el) {
    document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    currentItemId = item.id;
    selectedFrameIds.clear();
    updateSummary();

    const grid  = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Loading frames...</div>';
    grid.style.display = 'none';
    document.getElementById('gridActions').style.display = 'none';

    fetch(`?api_action=get_frames&source=${encodeURIComponent(currentSource)}&item_id=${item.id}`)
        .then(r => r.json())
        .then(res => {
            currentFrames = res.data || [];
            renderGrid();
            state.style.display = 'none'; grid.style.display = 'grid';
            document.getElementById('gridActions').style.display = 'flex';
            document.getElementById('gridInfo').innerHTML =
                `<strong>${esc(item.name)}</strong> &mdash; ${currentFrames.length} frames &mdash; <em>${esc(currentSource)}</em>`;
            updateSummary();
        })
        .catch(() => {
            state.innerHTML = '<div class="state-msg"><div>Error loading frames</div></div>';
        });
}

// ── Grid rendering ─────────────────────────────────────
function renderGrid() {
    const grid = document.getElementById('framesGrid');
    grid.innerHTML = '';

    if (!currentFrames.length) {
        grid.style.display = 'none';
        document.getElementById('gridState').style.display = 'flex';
        document.getElementById('gridState').innerHTML = '<div>No frames found for this item</div>';
        return;
    }

    currentFrames.forEach(f => {
        const card = document.createElement('div');
        card.className = 'f-card' + (selectedFrameIds.has(f.frame_id) ? ' selected' : '');
        card.dataset.fid = f.frame_id;

        const link = document.createElement('a');
        link.className = 'f-link'; link.href = f.filename; link.target = '_blank';
        link.dataset.pswpWidth = 1024; link.dataset.pswpHeight = 1024;
        const img = document.createElement('img'); img.src = f.filename; img.loading = 'lazy';
        img.onload = function() { link.dataset.pswpWidth = this.naturalWidth; link.dataset.pswpHeight = this.naturalHeight; };
        link.appendChild(img);

        const viewBtn = document.createElement('div');
        viewBtn.className = 'f-view-btn'; viewBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        viewBtn.onclick = e => { e.stopPropagation(); e.preventDefault(); openFrameModal(f.frame_id); };

        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = e => { e.preventDefault(); toggleFrame(f.frame_id, card); };
        label.innerHTML = `<span>#${f.frame_id}</span><div class="f-select-trigger"></div>`;

        card.appendChild(link); card.appendChild(viewBtn); card.appendChild(label);
        grid.appendChild(card);
    });

    if (typeof PhotoSwipeLightbox !== 'undefined') {
        new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe }).init();
    }
}

function resetGrid() {
    const grid = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    grid.innerHTML = ''; grid.style.display = 'none';
    state.style.display = 'flex'; state.innerHTML = '<div>&#8593; Select an Item</div>';
    document.getElementById('gridActions').style.display = 'none';
    document.getElementById('gridInfo').textContent = 'Select an item above';
    currentFrames = [];
    selectedFrameIds.clear();
    updateSummary();
}

function toggleGridCols() {
    const isOneCol = document.getElementById('oneColGrid').checked;
    document.getElementById('framesGrid').classList.toggle('one-col', isOneCol);
    try { localStorage.setItem('locahub_one_col', isOneCol ? '1' : '0'); } catch(e) {}
}

function toggleFrame(fid, card) {
    if (selectedFrameIds.has(fid)) { selectedFrameIds.delete(fid); card.classList.remove('selected'); }
    else { selectedFrameIds.add(fid); card.classList.add('selected'); }
    updateSummary();
}

function toggleAllFrames(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        const fid = parseInt(c.dataset.fid);
        if (select) { selectedFrameIds.add(fid); c.classList.add('selected'); }
        else { selectedFrameIds.delete(fid); c.classList.remove('selected'); }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
}

// ── Frame modal ────────────────────────────────────────
function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}

// ── Standard Migrate modal ─────────────────────────────
function openMigrateModal() {
    if (checkedItems.size === 0) return;
    const isBatch = checkedItems.size > 1;

    if (!isBatch) {
        const item = Array.from(checkedItems.values())[0];
        document.getElementById('migrateNameField').style.display = 'block';
        document.getElementById('migrateDescField').style.display = 'block';
        document.getElementById('migrateBatchInfo').style.display = 'none';
        document.getElementById('migrateTypeLabel').textContent = 'Type';
        document.getElementById('migrateDescText').style.display = 'block';

        document.getElementById('migrateNameInput').value    = item.name || '';
        document.getElementById('migrateDescInput').value    = item.description || '';
        document.getElementById('migrateTypeInput').value    = item.type_hint || '';
        document.getElementById('migrateSourceInfo').innerHTML =
            `Source: <strong style="color:var(--amber);">${esc(currentSource)}</strong> &mdash; ID #${item.id} &mdash; ${esc(item.name)}`;
    } else {
        document.getElementById('migrateNameField').style.display = 'none';
        document.getElementById('migrateDescField').style.display = 'none';
        document.getElementById('migrateBatchInfo').style.display = 'block';
        document.getElementById('migrateDescText').style.display = 'none';
        document.getElementById('migrateTypeLabel').textContent = 'Common Type (Optional)';

        document.getElementById('migrateBatchInfo').innerHTML = `You are about to batch migrate <strong style="color:var(--amber);">${checkedItems.size}</strong> items from <span style="color:var(--amber);">${esc(currentSource)}</span>.`;
        document.getElementById('migrateTypeInput').value = '';
        document.getElementById('migrateSourceInfo').innerHTML = 'Names and descriptions will be imported exactly as they appear in the source. This action cannot be undone.';
    }

    document.getElementById('migrateBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMigrateModal() {
    document.getElementById('migrateBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function onMigrateBackdropClick(e) {
    if (e.target === document.getElementById('migrateBackdrop')) closeMigrateModal();
}

function submitMigration() {
    if (checkedItems.size === 0) return;
    const isBatch = checkedItems.size > 1;
    const commonType = document.getElementById('migrateTypeInput').value.trim();

    let payloadItems = [];

    if (!isBatch) {
        const item = Array.from(checkedItems.values())[0];
        const name = document.getElementById('migrateNameInput').value.trim();
        if (!name) { Toast.show('Name is required', 'error'); return; }
        
        payloadItems.push({
            source: currentSource,
            source_id: item.id,
            name: name,
            description: document.getElementById('migrateDescInput').value.trim(),
            type: commonType || item.type_hint
        });
    } else {
        checkedItems.forEach(item => {
            payloadItems.push({
                source: currentSource,
                source_id: item.id,
                name: item.name || `Migrated ${currentSource} #${item.id}`,
                description: item.description || '',
                type: commonType || item.type_hint || ''
            });
        });
    }

    const btn = document.querySelector('.ms-submit');
    btn.disabled = true; btn.textContent = 'Saving...';

    fetch('?api_action=migrate_to_locations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: payloadItems })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Toast.show(`Migrated ${res.count} item(s) to locations`);
            closeMigrateModal();
            checkedItems.clear();
            updateItemSelectionUI();
            loadList(curPage);
        } else {
            Toast.show(res.message || 'Migration failed', 'error');
        }
    })
    .catch(() => Toast.show('Network error', 'error'))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Save to Locations'; });
}

// ── Auto-Batch Modal Logic ─────────────────────────────

function openAutoBatchModal() {
    if (currentSource !== 'kg' && currentSource !== 'ag') return;
    
    document.getElementById('autoBatchBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    const countEl = document.getElementById('abUnmigratedCount');
    countEl.textContent = 'Loading...';
    
    document.getElementById('abLimitInput').value = '';
    document.getElementById('abTerminal').innerHTML = '<p class="info">Ready to process.</p>';
    
    autoBatchActive = false;
    autoBatchStop = false;
    
    const btnStart = document.getElementById('btnAbStart');
    btnStart.innerHTML = '<i class="bi bi-play-fill"></i> Start Batch';
    btnStart.disabled = true;

    // Fetch total unmigrated
    fetch(`?api_action=get_batch_info&source=${encodeURIComponent(currentSource)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                countEl.textContent = res.unmigrated_count;
                if (res.unmigrated_count > 0) {
                    btnStart.disabled = false;
                } else {
                    logTerminal('No items left to migrate!', 'info');
                }
            } else {
                countEl.textContent = 'Error loading';
                logTerminal(res.message || 'Failed to fetch batch info.', 'error');
            }
        })
        .catch(err => {
            countEl.textContent = 'Network Error';
            logTerminal('Network error fetching batch info.', 'error');
        });
}

function closeAutoBatchModal() {
    if (autoBatchActive) {
        if (confirm("Batch is currently running. Stop it?")) {
            autoBatchStop = true;
        } else {
            return;
        }
    }
    document.getElementById('autoBatchBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function onAutoBatchBackdropClick(e) {
    if (e.target === document.getElementById('autoBatchBackdrop')) closeAutoBatchModal();
}

function logTerminal(msg, type = '') {
    const term = document.getElementById('abTerminal');
    const p = document.createElement('p');
    if (type) p.className = type;
    p.textContent = `> ${msg}`;
    term.appendChild(p);
    term.scrollTop = term.scrollHeight;
}

async function startAutoBatch() {
    if (autoBatchActive) {
        autoBatchStop = true;
        logTerminal("Stopping after current chunk...", "info");
        return;
    }
    
    autoBatchActive = true;
    autoBatchStop = false;
    
    const btnStart = document.getElementById('btnAbStart');
    btnStart.innerHTML = '<i class="bi bi-stop-fill"></i> Stop Batch';
    btnStart.style.background = 'var(--red)';
    document.getElementById('btnAbCancel').disabled = true;
    
    let limitInput = document.getElementById('abLimitInput').value.trim();
    let maxToProcess = limitInput ? parseInt(limitInput) : 999999;
    let totalProcessed = 0;
    let chunkSize = 20;

    logTerminal(`Starting auto-batch for up to ${maxToProcess} items...`);

    try {
        while (totalProcessed < maxToProcess && !autoBatchStop) {
            let currentChunkSize = Math.min(chunkSize, maxToProcess - totalProcessed);
            
            logTerminal(`Fetching chunk of ${currentChunkSize}...`);
            
            const resp = await fetch(`?api_action=process_auto_batch_chunk`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ source: currentSource, chunk_size: currentChunkSize })
            });
            
            const res = await resp.json();
            
            if (res.status === 'success') {
                if (res.logs && res.logs.length > 0) {
                    res.logs.forEach(l => logTerminal(l));
                }
                
                if (res.processed === 0) {
                    logTerminal("No more unmigrated items found. Done.", "info");
                    break;
                }
                
                totalProcessed += res.processed;
                
                // Update total label
                const countEl = document.getElementById('abUnmigratedCount');
                let curCount = parseInt(countEl.textContent) || 0;
                countEl.textContent = Math.max(0, curCount - res.processed);
                
            } else {
                logTerminal(`Error: ${res.message}`, "error");
                break;
            }
        }
    } catch (err) {
        logTerminal(`Network/Processing Error: ${err.message}`, "error");
    }
    
    if (autoBatchStop) {
        logTerminal("Batch stopped by user.", "info");
    } else {
        logTerminal(`Batch complete. Migrated ${totalProcessed} items.`, "info");
    }
    
    autoBatchActive = false;
    btnStart.innerHTML = '<i class="bi bi-play-fill"></i> Start Batch';
    btnStart.style.background = 'var(--teal)';
    btnStart.disabled = true; // prevent re-run until modal is closed/reopened
    document.getElementById('btnAbCancel').disabled = false;
    
    // Refresh main list so we don't see migrated ones
    loadList(curPage);
}

// ── Keyboard ───────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeFrameModal();
        if (document.getElementById('migrateBackdrop').classList.contains('active')) closeMigrateModal();
        if (document.getElementById('autoBatchBackdrop').classList.contains('active') && !autoBatchActive) closeAutoBatchModal();
    }
});

function esc(s) { return s ? s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
</script>
<?php

echo $eruda ?? '';

$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>