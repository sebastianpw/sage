<?php
// public/regenerator.php
// Regenerator -- Batch Sketch Regeneration Interface
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// ... [API HANDLER SAME AS BEFORE] ...
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];
    try {
        if ($action === 'get_map_runs') {
            $limit  = (int)($_GET['limit'] ?? 7);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = $_GET['search'] ?? '';
            $where = "entity_type = 'sketches'";
            if ($search) {
                $safeSearch = $pdo->quote("%$search%");
                $safeId     = intval($search);
                $where .= " AND (note LIKE $safeSearch OR id = $safeId)";
            }
            $total = $pdo->query("SELECT COUNT(*) FROM map_runs WHERE $where")->fetchColumn();
            $sql   = "SELECT *, (SELECT COUNT(*) FROM frames WHERE map_run_id = map_runs.id) as frame_count 
                      FROM map_runs WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $rows  = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
            exit;
        }
        if ($action === 'get_frames') {
            $runId = (int)$_GET['map_run_id'];
            $sql = "SELECT f.id as frame_id, f.filename, 
                           s.id as sketch_id, s.name as sketch_name, 
                           s.regenerate_images as is_queued
                    FROM frames f 
                    LEFT JOIN sketches s ON (f.entity_id = s.id AND f.entity_type = 'sketches')
                    WHERE f.map_run_id = $runId 
                    ORDER BY f.id ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$rows]);
            exit;
        }
        if ($action === 'submit_regeneration') {
            $input = json_decode(file_get_contents('php://input'), true);
            $frameIds = $input['frame_ids'] ?? [];
            if (empty($frameIds)) throw new Exception("No frames selected.");
            $idsStr = implode(',', array_map('intval', $frameIds));
            $sql = "SELECT DISTINCT entity_id FROM frames WHERE id IN ($idsStr) AND entity_type = 'sketches'";
            $sketchIds = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            if (empty($sketchIds)) throw new Exception("No parent sketches found for selected frames.");
            $sIdsStr = implode(',', $sketchIds);
            $count = $pdo->exec("UPDATE sketches SET regenerate_images = 1, updated_at = NOW() WHERE id IN ($sIdsStr)");
            echo json_encode(['status'=>'success', 'count'=>$count, 'sketch_ids' => $sketchIds]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'Regenerator';
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

<style>
    :root {
        --bg: #0a0a0f;
        --card: #111118;
        --border: #1e1e2e;
        --text: #e2e2f0;
        --text-muted: #555570;
        --accent: #f43f5e; /* Rose-500 */
        --accent-dim: rgba(244, 63, 94, 0.1);
    }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }
    
    .eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }
    
    /* HEADER & NAV */
    .eh-header {
        flex-shrink: 0; padding: 0 16px; height: 50px;
        background: var(--card); border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--accent); display: flex; align-items: center; gap: 8px; }
    
    .eh-nav { display: flex; height: 100%; gap: 0; }
    .nav-link-btn {
        display: flex; align-items: center; padding: 0 20px;
        color: var(--text-muted); text-decoration: none;
        font-size: 0.85rem; font-weight: 700; border-left: 1px solid var(--border);
        transition: all 0.2s; height: 100%;
    }
    .nav-link-btn:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    .nav-link-btn.active { background: var(--bg); color: var(--accent); border-bottom: 2px solid var(--accent); }

    /* LAYOUT PANELS */
    .eh-top-panel { flex-shrink: 0; display: flex; flex-direction: column; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2); max-height: 30vh; }
    .mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); }
    .mr-search-input { flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
    .mr-search-input:focus { outline: none; border-color: var(--accent); }
    
    .mr-pagination { display: flex; align-items: center; gap: 4px; }
    .pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
    .pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
    .pg-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--accent); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
    .pg-input:focus { outline: none; border-color: var(--accent); }
    .pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

    .mr-list-scroll { overflow-y: auto; overflow-x: hidden; min-height: 60px; }
    .mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
    .mr-item:hover { background: rgba(255,255,255,0.03); }
    .mr-item.active { background: var(--accent-dim); border-left: 3px solid var(--accent); padding-left: 9px; }
    .mr-id { font-size: 0.7rem; font-weight: 700; color: var(--accent); min-width: 40px; }
    .mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; }

    .eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
    .grid-toolbar { padding: 6px 12px; background: rgba(0,0,0,0.2); border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .gt-left { display: flex; align-items: center; gap: 12px; }
    .gt-info { font-size: 0.7rem; color: var(--text-muted); }
    .gt-actions { display: flex; gap: 8px; align-items: center; }
    .action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .action-btn:hover { color: var(--accent); border-color: var(--accent); }
    .action-btn.primary { border-color: var(--accent); color: var(--accent); }

    .chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; select-none: none; }
    .chk-label input { accent-color: var(--accent); }

    .eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: #000; min-height: 0; }
    .frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; padding-bottom: 20px; }
    
    .f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
    .f-card.selected { border-color: var(--accent); }
    .f-card.is-queued { border-color: #333; opacity: 0.4; filter: grayscale(50%); }
    .f-card.is-queued::before { content: "QUEUED"; position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 0.7rem; font-weight: 900; color: rgba(255,255,255,0.4); transform: rotate(-15deg); pointer-events: none; z-index: 5; }
    .f-card.is-queued.hidden-in-grid { display: none; }

    .f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
    .f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
    .f-link:hover img { transform: scale(1.03); }
    
    .f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
    .f-label:hover { background: #222; color: #fff; }
    .f-card.selected .f-label { background: var(--accent-dim); color: var(--accent); border-top-color: var(--accent); }
    .f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; transition: all 0.1s; }
    .f-card.selected .f-select-trigger { background: var(--accent); border-color: var(--accent); color: #fff; font-size: 10px; font-weight: 900; }
    .f-card.selected .f-select-trigger::after { content: '✓'; }

    .f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
    .f-card:hover .f-view-btn { opacity: 1; }
    .f-view-btn:hover { background: var(--accent); border-color: var(--accent); color: #000; }

    .view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
    .view-modal.active { display: flex; }
    .view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
    .view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
    .view-close:hover { background: var(--accent); color: #000; border-color: var(--accent); }
    iframe.frame-viewer { width: 100%; height: 100%; border: none; }

    .eh-footer { flex-shrink: 0; padding: 10px 16px; background: var(--card); border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; z-index: 10; position: relative; }
    .ft-summary { font-size: 0.75rem; color: var(--text-muted); }
    .submit-btn { padding: 12px 24px; border-radius: 4px; background: var(--accent); color: #fff; border: none; font-size: 0.9rem; font-weight: 700; text-transform: uppercase; cursor: pointer; font-family: inherit; transition: filter 0.15s; }
    .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border); }
    
    .state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; padding: 20px; text-align: center; }
    .spinner { width: 20px; height: 20px; border: 2px solid var(--accent-dim); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .pswp { z-index: 99999; }
</style>

<div class="eh-layout">
    <div class="eh-header">
        <div class="eh-title"><span>&#9851;</span> </div>
        <div class="eh-nav">
         
            
            
            <a href="enhanimatics.php" class="nav-link-btn">Enhanimatics</a>
            <a href="regenerator.php" class="nav-link-btn active">Regenerator</a>
        </div>
    </div>

    <!-- 1. Map Runs Selection -->
    <div class="eh-top-panel">
        <div class="mr-controls-row">
            <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search Run ID or Note..." oninput="debounceSearch()">
            <div class="mr-pagination">
                <button class="pg-btn" id="mrPrev" onclick="changePage(-1)">&#8592;</button>
                <input type="number" class="pg-input" id="mrPageInput" value="1" onchange="jumpToPage()" onkeydown="if(event.key==='Enter') jumpToPage()">
                <span class="pg-total" id="mrTotalPages">/ 1</span>
                <button class="pg-btn" id="mrNext" onclick="changePage(1)">&#8594;</button>
            </div>
        </div>
        <div class="mr-list-scroll" id="mrList">
            <div class="state-msg">Loading runs...</div>
        </div>
    </div>

    <!-- 2. Config & Toolbar -->
    <div class="eh-mid-panel">
        <div class="grid-toolbar">
            <div class="gt-left">
                <div class="gt-info" id="gridInfo">Select a run above</div>
                <label class="chk-label" title="Hide sketches already queued">
                    <input type="checkbox" id="hideQueued" onchange="applyGridFilters()">
                    Hide Queued
                </label>
            </div>
            <div class="gt-actions" id="gridActions" style="display:none;">
                <a id="lnkScrollMagic" href="#" target="_blank" class="action-btn" title="Open ScrollMagic Viewer">
                    <i class="bi bi-collection-play" style="font-size: 1.1em;"></i>
                </a>
                <div style="width:1px; background:var(--border); margin:0 8px;"></div>
                <button class="action-btn" onclick="toggleAll(false)">None</button>
                <button class="action-btn primary" onclick="toggleAll(true)">All</button>
            </div>
        </div>
    </div>

    <!-- 3. Grid -->
    <div class="eh-grid-area" id="framesScroll">
        <div class="state-msg" id="gridState">
            <div>&#8593; Select a Map Run to load frames</div>
        </div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- Footer -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 frames selected</div>
        <button class="submit-btn" id="submitBtn" disabled onclick="submitRegeneration()">
            Regenerate Sketches
        </button>
    </div>
</div>

<!-- IFRAME MODAL -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
// [JS LOGIC SAME AS BEFORE]
let curPage = 1;
let totalPages = 1;
let currentRunId = null;
let currentFrames = []; 
let selectedFrameIds = new Set();
let debounceTimer = null;
let lightbox = null;

document.addEventListener('DOMContentLoaded', () => {
    loadMapRuns(1);
    initLightbox();
});

function debounceSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadMapRuns(1), 300);
}

function changePage(delta) {
    const newPage = curPage + delta;
    if (newPage >= 1 && newPage <= totalPages) loadMapRuns(newPage);
}

function jumpToPage() {
    const val = parseInt(document.getElementById('mrPageInput').value);
    if (!isNaN(val)) {
        if (val < 1) loadMapRuns(1);
        else if (val > totalPages) loadMapRuns(totalPages);
        else loadMapRuns(val);
    } else {
        document.getElementById('mrPageInput').value = curPage;
    }
}

function loadMapRuns(page) {
    const list = document.getElementById('mrList');
    const search = document.getElementById('mrSearch').value.trim();
    if (page === 1) list.scrollTop = 0;
    fetch(`?api_action=get_map_runs&limit=7&offset=${(page-1)*7}&search=${encodeURIComponent(search)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            curPage = page;
            totalPages = Math.ceil(res.total / 7) || 1;
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
            document.getElementById('mrPrev').disabled = curPage <= 1;
            document.getElementById('mrNext').disabled = curPage >= totalPages;
            list.innerHTML = '';
            if (res.data.length === 0) { list.innerHTML = '<div class="state-msg">No runs found.</div>'; return; }
            res.data.forEach(run => {
                const el = document.createElement('div');
                el.className = `mr-item ${run.id == currentRunId ? 'active' : ''}`;
                el.onclick = () => selectRun(run.id, el);
                el.innerHTML = `<div class="mr-id">#${run.id}</div><div class="mr-note">${esc(run.note || 'No note')}</div><div class="mr-meta">${run.frame_count} fr • ${run.created_at.substring(0,10)}</div>`;
                list.appendChild(el);
            });
        });
}

function selectRun(runId, el) {
    document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    currentRunId = runId;
    document.getElementById('lnkScrollMagic').href = `view_scrollmagic_map_run.php?map_run_id=${runId}`;
    const grid = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    const info = document.getElementById('gridInfo');
    state.style.display = 'flex';
    state.innerHTML = '<div class="spinner"></div><div>Loading frames...</div>';
    grid.style.display = 'none';
    document.getElementById('gridActions').style.display = 'none';
    fetch(`?api_action=get_frames&map_run_id=${runId}`)
        .then(r => r.json())
        .then(res => {
            currentFrames = res.data;
            selectedFrameIds.clear();
            renderGrid();
            state.style.display = 'none';
            grid.style.display = 'grid';
            document.getElementById('gridActions').style.display = 'flex';
            info.innerHTML = `Run <strong>#${runId}</strong> • ${currentFrames.length} frames`;
            updateSummary();
        });
}

function initLightbox() {
    if (typeof PhotoSwipeLightbox === 'undefined') return;
    lightbox = new PhotoSwipeLightbox({
        gallery: '#framesGrid',
        children: 'a.f-link', 
        pswpModule: PhotoSwipe
    });
    lightbox.init();
}

function renderGrid() {
    const grid = document.getElementById('framesGrid');
    const hideQueued = document.getElementById('hideQueued').checked;
    grid.innerHTML = '';
    currentFrames.forEach(f => {
        const isQueued = parseInt(f.is_queued) === 1;
        const card = document.createElement('div');
        card.className = 'f-card';
        if (isQueued) card.classList.add('is-queued');
        if (isQueued && hideQueued) card.classList.add('hidden-in-grid');
        card.dataset.fid = f.frame_id;
        card.dataset.isQueued = isQueued ? "1" : "0";
        
        const link = document.createElement('a');
        link.className = 'f-link';
        link.href = f.filename;
        link.target = '_blank';
        link.dataset.pswpWidth = 1024;
        link.dataset.pswpHeight = 1024;
        const img = document.createElement('img');
        img.src = f.filename;
        img.loading = "lazy";
        img.onload = function() { link.dataset.pswpWidth = this.naturalWidth; link.dataset.pswpHeight = this.naturalHeight; };
        link.appendChild(img);
        
        const viewBtn = document.createElement('div');
        viewBtn.className = 'f-view-btn';
        viewBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        viewBtn.onclick = (e) => { e.stopPropagation(); e.preventDefault(); openFrameModal(f.frame_id); };

        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = (e) => { e.preventDefault(); toggleFrame(f.frame_id, card); };
        label.innerHTML = `<span>#${f.frame_id}</span><div class="f-select-trigger"></div>`;
        
        card.appendChild(link);
        card.appendChild(viewBtn);
        card.appendChild(label);
        grid.appendChild(card);
    });
}

function applyGridFilters() {
    const hideQueued = document.getElementById('hideQueued').checked;
    const cards = document.querySelectorAll('.f-card');
    cards.forEach(card => {
        const isQueued = card.dataset.isQueued === "1";
        if (isQueued && hideQueued) {
            if (card.classList.contains('selected')) {
                selectedFrameIds.delete(parseInt(card.dataset.fid));
                card.classList.remove('selected');
            }
            card.classList.add('hidden-in-grid');
        } else {
            card.classList.remove('hidden-in-grid');
        }
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
    const cards = document.querySelectorAll('.f-card');
    selectedFrameIds.clear();
    cards.forEach(card => {
        if (card.classList.contains('hidden-in-grid')) { card.classList.remove('selected'); return; }
        if (select) { selectedFrameIds.add(parseInt(card.dataset.fid)); card.classList.add('selected'); } 
        else { card.classList.remove('selected'); }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} frames selected`;
    document.getElementById('submitBtn').disabled = count === 0;
}

function submitRegeneration() {
    const btn = document.getElementById('submitBtn');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Processing...';
    fetch('?api_action=submit_regeneration', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ frame_ids: Array.from(selectedFrameIds) })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            Toast.show(`Flagged ${res.count} sketches for regeneration`);
            selectedFrameIds.clear();
            if(currentRunId) {
                fetch(`?api_action=get_frames&map_run_id=${currentRunId}`).then(r => r.json()).then(r2 => {
                    currentFrames = r2.data; renderGrid(); updateSummary();
                });
            }
        } else { Toast.show(res.message, 'error'); updateSummary(); }
    }).finally(() => { btn.disabled = false; btn.textContent = orig; });
}

function openFrameModal(frameId) {
    const modal = document.getElementById('viewModal');
    const iframe = document.getElementById('frameViewer');
    iframe.src = `view_frame.php?frame_id=${frameId}&view=modal`;
    modal.classList.add('active');
}
function closeFrameModal() {
    const modal = document.getElementById('viewModal');
    const iframe = document.getElementById('frameViewer');
    modal.classList.remove('active');
    setTimeout(() => { iframe.src = ''; }, 200);
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('viewModal').classList.contains('active')) closeFrameModal();
});
function esc(s) { return s ? s.toString().replace(/"/g, '&quot;') : ''; }
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>