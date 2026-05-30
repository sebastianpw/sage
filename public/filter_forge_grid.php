<?php
// public/forge_filter_grid.php
// Forge Filter API Browser -- Unified test view cloning Enhanimatics Grid
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php'; // provides $entityIcons

// Allowed entities come from entity_icons.php (whitelist)
$allowedEntities = array_keys($entityIcons ?? []);
// Selected entity defaults to 'sketches'
$selectedEntity = $_REQUEST['entity'] ?? 'sketches';
if (!in_array($selectedEntity, $allowedEntities, true)) {
    $selectedEntity = 'sketches';
}

$entityType = $selectedEntity;

// Deep-link params
$deepLinkRunId = isset($_GET['map_run_id']) ? (int)$_GET['map_run_id'] : 0;
$deepLinkEntityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$deepLinkSearch = isset($_GET['search']) ? $_GET['search'] : '';
$mapRunDisabled = isset($_GET['map_run_dis']) && $_GET['map_run_dis'] == '1';

// --------------------- Page render ---------------------
$pageTitle = 'Forge Filter API Browser';
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
    --teal: #14b8a6;
    --teal-dim: rgba(20, 184, 166, 0.12);
}
    [data-theme="light"] {
        --bg:         #f4f4f8;
        --card:       #ffffff;
        --border:     #d0d0e0;
        --text:       #1a1a2e;
        --text-muted: #888899;
    }
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }
.eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }
.eh-header {
    flex-shrink: 0; padding: 0 16px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.eh-title span { color: var(--purple); }
.eh-nav { display: flex; height: 100%; gap: 12px; align-items:center; }

/* FF Browser Params UI */
.ff-param-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; padding: 10px 15px; }
.ff-param-group { display: flex; flex-direction: column; gap: 4px; }
.ff-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;}
.ff-input { padding: 6px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 4px; color: var(--text); font-family: inherit; font-size: 0.75rem; width: 100%; box-sizing: border-box; }
.ff-input:focus { outline: none; border-color: var(--purple); }
.ff-btn-run { padding: 10px 16px; background: var(--purple); color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-family: inherit; font-size: 0.8rem; margin-top: 6px; display:flex; align-items:center; justify-content:center; gap:8px; grid-column: 1 / -1;}
.ff-btn-run:hover { filter: brightness(1.1); }

.grid-toolbar {
    background: rgba(0,0,0,0.2);
    display: flex; flex-direction: column;
}
.gt-row1 {
    padding: 6px 12px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.gt-row2 {
    padding: 5px 12px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.gt-left { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.gt-info { font-size: 0.7rem; color: var(--text-muted); }
.action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;}
.action-btn:hover { color: var(--purple); border-color: var(--purple); }
.action-btn.primary { border-color: var(--purple); color: var(--purple); }
.chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; select-none: none; }
#hideImported:checked { accent-color: var(--purple); }
#hideEnhanced:checked { accent-color: var(--amber); }
#showRaw:checked { accent-color: #4ade80; }
#oneColGrid:checked { accent-color: var(--teal); }
.eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: var(--bg); min-height: 0; }
.frames-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding-bottom: 20px; }
.frames-grid.one-col { grid-template-columns: 1fr; }
@media (min-width: 600px) {
    .frames-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}
.f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.f-card.selected { border-color: var(--text); box-shadow: 0 0 0 1px var(--text); }
.f-card.is-imported { border-color: #333; opacity: 0.25; filter: grayscale(80%); }
.f-card.is-imported::before { content: "IMPORTED"; position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 0.7rem; font-weight: 900; color: rgba(139, 92, 246, 0.6); transform: rotate(-15deg); pointer-events: none; z-index: 5; }
.f-card.is-imported.hidden-in-grid { display: none; }
.f-card.is-enhanced { border-color: rgba(245, 158, 11, 0.4); opacity: 0.4; filter: sepia(80%) hue-rotate(-10deg) saturate(1.5) brightness(0.6); }
.f-card.is-enhanced::after { content: "ENHANCED"; position: absolute; bottom: 35px; left: 0; right: 0; text-align: center; font-size: 0.65rem; font-weight: 800; color: rgba(245, 158, 11, 0.8); pointer-events: none; z-index: 5; text-shadow: 0 1px 2px #000; }
.f-card.is-enhanced.hidden-in-grid { display: none; }
.frames-grid.show-raw .f-card.is-imported,
.frames-grid.show-raw .f-card.is-enhanced { opacity: 1; filter: none; border-color: var(--border); }
.frames-grid.show-raw .f-card.is-imported::before,
.frames-grid.show-raw .f-card.is-enhanced::after { display: none; }
.f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.03); }
.f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--text); border-color: var(--text); color: #000; }
.f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
.f-card.selected .f-label { background: rgba(255,255,255,0.1); color: #fff; border-top-color: #fff; }
.f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; }
.f-card.selected .f-select-trigger { background: #fff; border-color: #fff; color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }
.eh-footer {
    flex-shrink: 0; padding: 10px 16px; 
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    background: var(--card); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    z-index: 10; position: relative;
}
.ft-summary { font-size: 0.75rem; color: var(--text-muted); }
.ft-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.btn-action { padding: 10px 12px; border-radius: 4px; border: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; cursor: pointer; font-family: inherit; transition: filter 0.15s; color: #fff; min-width: 0; white-space: nowrap; }
@media (min-width: 600px) {
    .ft-actions { gap: 10px; flex-wrap: nowrap; }
    .btn-action { padding: 12px 20px; font-size: 0.85rem; min-width: 100px; }
}
.btn-action:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }
.btn-enhance { background: var(--amber); color: #000; }
.btn-import { background: var(--purple); }
.btn-stba { background: var(--teal); color: #000; }

.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }
.state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--text); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.pswp { z-index: 99999; }
</style>

<div class="eh-layout">
    <!-- Header -->
    <div class="eh-header">
        <div class="eh-title"><span>&#10024;</span>  <span style="font-size:0.7em; opacity:0.6; margin-left:8px;">Forge API Browser</span></div>
        <div class="eh-nav">
            <label for="entitySelect" style="font-size:0.85rem; color:var(--text-muted); margin-right:8px;">Entity:</label>
            <select id="entitySelect" onchange="selectEntity(this.value)" style="background:var(--card); border:1px solid var(--border); color:var(--text); padding:6px 8px; border-radius:4px; font-family:inherit;">
                <?php foreach ($entityIcons as $ename => $icon): ?>
                    <option value="<?php echo htmlspecialchars($ename, ENT_QUOTES); ?>" <?php echo ($ename === $selectedEntity ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($icon . ' ' . $ename, ENT_QUOTES); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Parameterization UI (Replaces original Map Run and Config Panels) -->
    <div class="eh-top-panel forge-test-panel" style="flex-shrink:0; background:var(--card); border-bottom:1px solid var(--border); z-index:5;">
        <div class="ff-param-grid">
            <div class="ff-param-group">
                <span class="ff-label">Action</span>
                <select class="ff-input" id="ff_action" onchange="loadList(1)">
                    <option value="list_frames">list_frames</option>
                    <option value="list_entities">list_entities</option>
                    <option value="list_filter_options">list_filter_options</option>
                    <option value="check_membership">check_membership</option>
                    <option value="resolve_relationships">resolve_relationships</option>
                </select>
            </div>
            <div class="ff-param-group">
                <span class="ff-label">Entity Type</span>
                <select class="ff-input" id="ff_entity_type" onchange="document.getElementById('entitySelect').value=this.value; loadList(1)">
                    <?php foreach ($entityIcons as $ename => $icon): ?>
                        <option value="<?php echo htmlspecialchars($ename); ?>" <?php echo ($ename === $selectedEntity ? 'selected' : ''); ?>><?php echo $ename; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ff-param-group">
                <span class="ff-label">Filter Mode</span>
                <select class="ff-input" id="ff_filter_mode" onchange="loadList(1)">
                    <option value="intersection">intersection (AND)</option>
                    <option value="union">union (OR)</option>
                </select>
            </div>
            <div class="ff-param-group"><span class="ff-label">Fuzz ID</span><input type="number" class="ff-input" id="ff_fuzz_id"></div>
            <div class="ff-param-group"><span class="ff-label">Doc ID</span><input type="number" class="ff-input" id="ff_doc_id"></div>
            <div class="ff-param-group"><span class="ff-label">KG Node ID</span><input type="number" class="ff-input" id="ff_kg_node_id"></div>
            <div class="ff-param-group"><span class="ff-label">Seq ID</span><input type="number" class="ff-input" id="ff_seq_id"></div>
            <div class="ff-param-group"><span class="ff-label">Storyboard ID</span><input type="number" class="ff-input" id="ff_storyboard_id"></div>
            <div class="ff-param-group"><span class="ff-label">Map Run ID</span><input type="number" class="ff-input" id="ff_map_run_id"></div>
            <div class="ff-param-group"><span class="ff-label">Entity ID</span><input type="number" class="ff-input" id="ff_entity_id"></div>
            <div class="ff-param-group"><span class="ff-label">Doc Entity Name(s)</span><input type="text" class="ff-input" id="ff_doc_entity_name" placeholder="Exact or LIKE"></div>
            <div class="ff-param-group"><span class="ff-label">Vector Text</span><input type="text" class="ff-input" id="ff_vector_text"></div>
            <div class="ff-param-group"><span class="ff-label">Search</span><input type="text" class="ff-input" id="ff_search"></div>
            <div class="ff-param-group">
                <span class="ff-label">Sort</span>
                <select class="ff-input" id="ff_sort">
                    <option value="id">id</option>
                    <option value="latest_frame">latest_frame</option>
                    <option value="entity_id">entity_id</option>
                    <option value="map_run">map_run</option>
                </select>
            </div>
            <div class="ff-param-group" style="flex-direction:row; align-items:flex-end; gap:4px;">
                <div style="flex:1;"><span class="ff-label">Page</span><input type="number" class="ff-input" id="ff_page" value="1"></div>
                <div style="flex:1;"><span class="ff-label">Per Pg</span><input type="number" class="ff-input" id="ff_per_page" value="50"></div>
            </div>
            <div class="ff-param-group" style="justify-content:center; flex-direction:row; align-items:center; gap:5px;">
                <input type="checkbox" id="ff_include_membership"> <span class="ff-label" style="margin:0;">Inc. Membership</span>
            </div>

            <button class="ff-btn-run" onclick="loadList()">Fetch via Filter Forge API</button>
        </div>

        <!-- Toolbar preserved for Grid functionality -->
        <div class="grid-toolbar" style="border-top: 1px solid rgba(255,255,255,0.04); margin-top: 8px;">
            <div class="gt-row1" style="background:transparent;">
                <div class="gt-info" id="gridInfo">Configure parameters and fetch</div>
                <div class="gt-actions" id="gridActions" style="display:none;">
                    <a id="lnkScrollMagic" href="#" target="_blank" class="action-btn" title="ScrollMagic" style="display:none;">
                        <i class="bi bi-collection-play" style="font-size: 1.1em;"></i>
                    </a>
                    <div style="width:1px; background:var(--border); margin:0 8px;"></div>
                    <button class="action-btn" onclick="toggleAll(false)">None</button>
                    <button class="action-btn primary" onclick="toggleAll(true)" style="border-color:var(--text); color:var(--text);">All</button>
                </div>
            </div>
            <div class="gt-row2">
                <label class="chk-label" title="Hide frames imported to Animatics">
                    <input type="checkbox" id="hideImported" onchange="applyGridFilters()"> HideImp
                </label>
                <label class="chk-label" title="Hide frames already enhanced">
                    <input type="checkbox" id="hideEnhanced" onchange="applyGridFilters()"> HideEnh
                </label>
                <label class="chk-label" title="Show all frames without opacity/darkness indicators">
                    <input type="checkbox" id="showRaw" onchange="applyGridFilters()"> Raw
                </label>
                <label class="chk-label" title="Show one column grid for larger images">
                    <input type="checkbox" id="oneColGrid" onchange="toggleGridCols()"> 1Col
                </label>
                <!-- Placeholders for compatibility with unused scripts -->
                <input type="checkbox" id="useDepth2Img" style="display:none;">
                <input type="checkbox" id="useEntityPrompt" style="display:none;">
            </div>
        </div>
    </div>

    <!-- 3. Grid -->
    <div class="eh-grid-area">
        <div class="state-msg" id="gridState"><div>&#8593; Configure parameters and fetch</div></div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
        <pre id="rawJsonContainer" style="display:none; padding: 15px; color: var(--teal); font-size: 0.75rem; white-space: pre-wrap; word-break: break-all; margin:0;"></pre>
    </div>

    <!-- Footer -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 selected</div>
        <div class="ft-actions">
            <!-- Action buttons preserved visually but they won't have an active enhanimaticism API -->
            <button class="btn-action btn-stba"    id="btnStba"    disabled onclick="alert('Disabled in API Browser mode')">STBA</button>
            <button class="btn-action btn-enhance" id="btnEnhance" disabled onclick="alert('Disabled in API Browser mode')">Enhance Frames</button>
            <button class="btn-action btn-import"  id="btnImport"  disabled onclick="alert('Disabled in API Browser mode')">Import to Animatics</button>
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

<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
let curPage = 1, totalPages = 1, currentFrames = [], selectedFrameIds = new Set();
let currentEntity = "<?php echo addslashes($selectedEntity); ?>";

// Overrides for integration compatibility
function selectEntity(entity) {
    currentEntity = entity;
    document.getElementById('ff_entity_type').value = entity;
    loadList(1);
}

function changePage(d) {
    const n = parseInt(document.getElementById('ff_page').value) + d;
    if (n >= 1) loadList(n);
}

// Replaced LoadList -> directly interfaces with Filter Forge API
function loadList(pageOverride) {
    if (typeof pageOverride === 'number') {
        document.getElementById('ff_page').value = pageOverride;
    }

    const action = document.getElementById('ff_action').value;
    const params = new URLSearchParams();
    params.append('action', action);

    // Map fields
    const inputs = ['entity_type', 'filter_mode', 'fuzz_id', 'doc_id', 'kg_node_id', 'seq_id', 'storyboard_id', 'map_run_id', 'entity_id', 'vector_text', 'search', 'sort', 'per_page'];
    inputs.forEach(id => {
        const el = document.getElementById('ff_' + id);
        if (el && el.value.trim() !== '') params.append(id, el.value.trim());
    });
    
    // Doc Entity Names comma handling
    const den = document.getElementById('ff_doc_entity_name').value.trim();
    if (den) {
        if (den.includes(',')) {
            den.split(',').forEach(n => {
                if(n.trim()) params.append('doc_entity_names[]', n.trim());
            });
        } else {
            params.append('doc_entity_name', den);
        }
    }

    params.append('page', document.getElementById('ff_page').value || 1);

    if (document.getElementById('ff_include_membership').checked) {
        params.append('include_membership', '1');
    }

    const grid = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    const rawJsonContainer = document.getElementById('rawJsonContainer');
    const gridActions = document.getElementById('gridActions');

    state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Fetching Forge API...</div>';
    grid.style.display = 'none';
    gridActions.style.display = 'none';
    if(rawJsonContainer) rawJsonContainer.style.display = 'none';

    fetch('filter_forge_api.php?' + params.toString())
        .then(r => r.json())
        .then(res => {
            state.style.display = 'none';

            if (res.status !== 'success') {
                rawJsonContainer.textContent = JSON.stringify(res, null, 2);
                rawJsonContainer.style.display = 'block';
                return;
            }

            if (res.meta) {
                document.getElementById('ff_page').value = res.meta.page;
                curPage = res.meta.page;
                totalPages = res.meta.pages;
                document.getElementById('gridInfo').innerHTML = `<strong>${action}</strong> • ${res.meta.total} total • pg ${curPage}/${totalPages}`;
            }

            if (action === 'list_frames') {
                currentFrames = res.data || [];
                selectedFrameIds.clear();
                renderGrid();
                grid.style.display = 'grid';
                gridActions.style.display = 'flex';
                updateSummary();
            } else if (action === 'list_entities') {
                currentFrames = res.data || [];
                selectedFrameIds.clear();
                renderEntityGrid(res.data || []);
                grid.style.display = 'grid';
                updateSummary();
            } else {
                rawJsonContainer.textContent = JSON.stringify(res, null, 2);
                rawJsonContainer.style.display = 'block';
            }
        })
        .catch(err => {
            state.innerHTML = '<div style="color:var(--red);">Network Error</div>';
        });
}

function renderEntityGrid(entities) {
    const grid = document.getElementById('framesGrid');
    grid.innerHTML = '';
    grid.classList.remove('show-raw');
    entities.forEach(e => {
        const card = document.createElement('div');
        card.className = 'f-card';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.padding = '10px';
        card.style.justifyContent = 'center';
        card.style.alignItems = 'center';
        card.style.textAlign = 'center';
        card.style.cursor = 'default';

        card.innerHTML = `
            <div style="font-size:1.2rem; font-weight:bold; color:var(--purple);">#${e.id}</div>
            <div style="font-size:0.8rem; margin:5px 0; color:var(--text);">${esc(e.name || 'Unnamed')}</div>
            <div style="font-size:0.65rem; color:var(--text-muted);">${e.frame_count || 0} frames</div>
        `;
        grid.appendChild(card);
    });
}

// ----------------------------------------------------
// Preserved original grid functions
// ----------------------------------------------------
function renderGrid() {
    const grid    = document.getElementById('framesGrid');
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    const showRaw = document.getElementById('showRaw').checked;
    grid.innerHTML = '';
    grid.classList.toggle('show-raw', showRaw);
    currentFrames.forEach(f => {
        const isImp = parseInt(f.is_imported) === 1;
        const isEnh = parseInt(f.is_enhanced) === 1;
        const card  = document.createElement('div');
        card.className = 'f-card';
        if (isImp) card.classList.add('is-imported');
        if (isEnh) card.classList.add('is-enhanced');
        if ((isImp && hideImp) || (isEnh && hideEnh)) card.classList.add('hidden-in-grid');
        card.dataset.fid      = f.frame_id;
        card.dataset.imported = isImp ? "1" : "0";
        card.dataset.enhanced = isEnh ? "1" : "0";
        const link = document.createElement('a');
        link.className = 'f-link'; link.href = f.filename; link.target = '_blank';
        link.dataset.pswpWidth = 1024; link.dataset.pswpHeight = 1024;
        const img = document.createElement('img'); img.src = f.filename; img.loading = 'lazy';
        img.onload = function() { link.dataset.pswpWidth = this.naturalWidth; link.dataset.pswpHeight = this.naturalHeight; };
        link.appendChild(img);
        const viewBtn = document.createElement('div');
        viewBtn.className = 'f-view-btn'; viewBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        viewBtn.onclick = (e) => { e.stopPropagation(); e.preventDefault(); openFrameModal(f.frame_id); };
        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = (e) => { e.preventDefault(); toggleFrame(f.frame_id, card); };
        const labelText = f.entity_name 
            ? `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%;">${esc(f.entity_name)}</span>`
            : `<span>#${f.frame_id}</span>`;
        label.innerHTML = labelText + `<div class="f-select-trigger"></div>`;
        card.appendChild(link); card.appendChild(viewBtn); card.appendChild(label);
        grid.appendChild(card);
    });
    if (typeof PhotoSwipeLightbox !== 'undefined') {
        new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe }).init();
    }
}

function applyGridFilters() {
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    const showRaw = document.getElementById('showRaw').checked;
    document.getElementById('framesGrid').classList.toggle('show-raw', showRaw);
    document.querySelectorAll('.f-card').forEach(c => {
        const isImp = c.dataset.imported === "1";
        const isEnh = c.dataset.enhanced === "1";
        if ((isImp && hideImp) || (isEnh && hideEnh)) {
            if (c.classList.contains('selected')) { selectedFrameIds.delete(parseInt(c.dataset.fid)); c.classList.remove('selected'); }
            c.classList.add('hidden-in-grid');
        } else { c.classList.remove('hidden-in-grid'); }
    });
    updateSummary();
}

function toggleFrame(fid, card) {
    if (card.classList.contains('hidden-in-grid')) return;
    if (selectedFrameIds.has(fid)) { selectedFrameIds.delete(fid); card.classList.remove('selected'); }
    else { selectedFrameIds.add(fid); card.classList.add('selected'); }
    updateSummary();
}

function toggleAll(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        if (c.classList.contains('hidden-in-grid')) { c.classList.remove('selected'); return; }
        const fid = parseInt(c.dataset.fid);
        if (select) { selectedFrameIds.add(fid); c.classList.add('selected'); }
        else { selectedFrameIds.delete(fid); c.classList.remove('selected'); }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
    const disabled = count === 0;
    document.getElementById('btnEnhance').disabled = disabled;
    document.getElementById('btnImport').disabled  = disabled;
    const btnStba = document.getElementById('btnStba');
    if (btnStba) btnStba.disabled = disabled;
}

function toggleGridCols() {
    const isOneCol = document.getElementById('oneColGrid').checked;
    document.getElementById('framesGrid').classList.toggle('one-col', isOneCol);
}

function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeFrameModal();
});

function esc(s) { return s ? s.toString().replace(/"/g, '&quot;') : ''; }

document.addEventListener('DOMContentLoaded', () => {
    if (deepLinkRunId) { document.getElementById('ff_map_run_id').value = deepLinkRunId; }
    if (deepLinkEntityId) { document.getElementById('ff_entity_id').value = deepLinkEntityId; }
    if (deepLinkSearch) { document.getElementById('ff_search').value = deepLinkSearch; }
    loadList(1);
});
</script>
<?php

echo $eruda ?? '';

$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>