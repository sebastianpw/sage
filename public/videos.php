<?php
// public/videos.php
// Videos -- Video Management with Regenerator Interface
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\UI\Modules\VideoFrameExtractorModule;
use App\UI\Modules\ImageEditorModule;

// Initialize Modules for the Rich Modal
$videoExtractor = new VideoFrameExtractorModule();
$imageEditor = new ImageEditorModule();

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        // 1. GET MAP RUNS (Filtered: Only runs containing videos)
        if ($action === 'get_map_runs') {
            $limit  = (int)($_GET['limit'] ?? 7);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = $_GET['search'] ?? '';
            
            // Base Where
            $whereParts = ["m.entity_type = 'animatics'"];
            $params = [];

            if ($search) {
                $whereParts[] = "(m.note LIKE ? OR m.id = ?)";
                $params[] = "%$search%";
                $params[] = intval($search);
            }
            
            $whereSQL = implode(' AND ', $whereParts);

            // 1. Count Total (Distinct Map Runs that have videos)
            $countSql = "SELECT COUNT(DISTINCT m.id) 
                         FROM map_runs m
                         INNER JOIN videos v ON m.id = v.map_run_id 
                         WHERE $whereSQL";
            $stmtCount = $pdo->prepare($countSql);
            $stmtCount->execute($params);
            $total = $stmtCount->fetchColumn();

            // 2. Fetch Data
            $sql = "SELECT m.*, COUNT(v.id) as item_count 
                    FROM map_runs m
                    INNER JOIN videos v ON m.id = v.map_run_id
                    WHERE $whereSQL
                    GROUP BY m.id
                    ORDER BY m.id DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
            exit;
        }

        // 2. GET VIDEOS (Grid Items)
        if ($action === 'get_videos') {
            $runId = (int)$_GET['map_run_id'];
            
            // Fetch videos and their parent animatic ID
            $sql = "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, v.file_size, v.description,
                           va.to_id as animatic_id,
                           a.regenerate_videos as is_queued
                    FROM videos v 
                    LEFT JOIN videos_2_animatics va ON v.id = va.from_id
                    LEFT JOIN animatics a ON va.to_id = a.id
                    WHERE v.map_run_id = $runId 
                    ORDER BY v.id DESC";
            
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$rows]);
            exit;
        }

        // 3. SUBMIT REGENERATION
        if ($action === 'submit_regeneration') {
            $input = json_decode(file_get_contents('php://input'), true);
            $videoIds = $input['video_ids'] ?? [];

            if (empty($videoIds)) throw new Exception("No videos selected.");

            $idsStr = implode(',', array_map('intval', $videoIds));
            
            // Find parent Animatics
            $sql = "SELECT DISTINCT to_id FROM videos_2_animatics WHERE from_id IN ($idsStr)";
            $animaticIds = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

            if (empty($animaticIds)) throw new Exception("No parent animatics found for selected videos.");

            // Update Animatics
            $aIdsStr = implode(',', $animaticIds);
            $count = $pdo->exec("UPDATE animatics SET regenerate_videos = 1, updated_at = NOW() WHERE id IN ($aIdsStr)");

            echo json_encode(['status'=>'success', 'count'=>$count]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'Videos Regenerator';
ob_start();
?>
<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<style>
    :root {
        --bg: #0a0a0f;
        --card: #111118;
        --border: #1e1e2e;
        --text: #e2e2f0;
        --text-muted: #555570;
        --blue: #3b82f6;
        --blue-dim: rgba(59, 130, 246, 0.1);
        --green: #10b981;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

    /* ── HEADER ── */
    .eh-header {
        flex-shrink: 0; padding: 10px 16px;
        background: var(--card); border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--blue); display: flex; align-items: center; gap: 8px; }

    /* ── TOP PANEL ── */
    .eh-top-panel { flex-shrink: 0; display: flex; flex-direction: column; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2); max-height: 30vh; }
    .mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); }
    .mr-search-input { flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
    .mr-search-input:focus { outline: none; border-color: var(--blue); }
    
    .mr-pagination { display: flex; align-items: center; gap: 4px; }
    .pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
    .pg-btn:hover:not(:disabled) { border-color: var(--blue); color: var(--blue); }
    .pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--blue); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
    .pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

    .mr-list-scroll { overflow-y: auto; overflow-x: hidden; min-height: 60px; }
    .mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
    .mr-item:hover { background: rgba(255,255,255,0.03); }
    .mr-item.active { background: var(--blue-dim); border-left: 3px solid var(--blue); padding-left: 9px; }
    .mr-id { font-size: 0.7rem; font-weight: 700; color: var(--blue); min-width: 40px; }
    .mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; }

    /* ── MID PANEL ── */
    .eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
    .grid-toolbar { padding: 6px 12px; background: rgba(0,0,0,0.2); border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .gt-left { display: flex; align-items: center; gap: 12px; }
    .gt-info { font-size: 0.7rem; color: var(--text-muted); }
    .gt-actions { display: flex; gap: 8px; align-items: center; }
    
    .action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .action-btn:hover { color: var(--blue); border-color: var(--blue); }
    .action-btn.primary { border-color: var(--blue); color: var(--blue); }

    .chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; select-none: none; }
    .chk-label input { accent-color: var(--blue); }

    /* ── GRID ── */
    .eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: #000; min-height: 0; }
    .frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; padding-bottom: 20px; }
    
    .f-card { aspect-ratio: 16/9; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
    .f-card.selected { border-color: var(--blue); }
    .f-card.is-queued { border-color: #333; opacity: 0.4; filter: grayscale(50%); }
    .f-card.is-queued::before { content: "QUEUED"; position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 0.7rem; font-weight: 900; color: rgba(255,255,255,0.4); transform: rotate(-15deg); pointer-events: none; z-index: 5; }
    .f-card.is-queued.hidden-in-grid { display: none; }

    .f-thumb { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; cursor: pointer; }
    .f-thumb:hover { transform: scale(1.03); }
    
    .f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
    .f-card.selected .f-label { background: var(--blue-dim); color: var(--blue); border-top-color: var(--blue); }
    .f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; }
    .f-card.selected .f-select-trigger { background: var(--blue); border-color: var(--blue); color: #fff; font-size: 10px; font-weight: 900; }
    .f-card.selected .f-select-trigger::after { content: '✓'; }

    /* View Button (Trigger Rich Modal) */
    .f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
    .f-card:hover .f-view-btn { opacity: 1; }
    .f-view-btn:hover { background: var(--blue); border-color: var(--blue); }

    /* ── FOOTER ── */
    .eh-footer { flex-shrink: 0; padding: 10px 16px; background: var(--card); border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; z-index: 10; position: relative; }
    .ft-summary { font-size: 0.75rem; color: var(--text-muted); }
    .submit-btn { padding: 12px 24px; border-radius: 4px; background: var(--blue); color: #fff; border: none; font-size: 0.9rem; font-weight: 700; text-transform: uppercase; cursor: pointer; font-family: inherit; transition: filter 0.15s; }
    .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border); }

    .state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
    .spinner { width: 20px; height: 20px; border: 2px solid var(--blue-dim); border-top-color: var(--blue); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── RICH VIDEO MODAL ── */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 12000; padding: 10px; }
    .modal-overlay.active { display: flex; }
    .detail-modal-card { width: 100%; max-width: 800px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.5); display: flex; flex-direction: column; max-height: 95vh; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
    .detail-player-wrapper { width: 100%; background: #000; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; }
    .detail-player-wrapper video { width: 100%; height: 100%; max-height: 50vh; }
    .detail-content { padding: 16px; overflow-y: auto; }
    .detail-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; color: var(--text); }
    .detail-meta-row { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; }
    
    .detail-actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
    .detail-actions-grid .btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 4px; font-size: 0.75rem; gap: 4px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: var(--text); border-radius: 6px; cursor: pointer; text-decoration: none; }
    .detail-actions-grid .btn:hover { background: rgba(255,255,255,0.1); border-color: var(--blue); color: var(--text); }
    
    .detail-desc-btn { width: 100%; text-align: left; margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; border: 1px solid var(--border); color: var(--text); cursor: pointer; }
</style>

<div class="eh-layout">
    <div class="eh-header">
        <div class="eh-title"><span>&#127909;</span> VIDEOS CURATOR</div>
    </div>

    <!-- 1. Map Runs -->
    <div class="eh-top-panel">
        <div class="mr-controls-row">
            <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search Run..." oninput="debounceSearch()">
            <div class="mr-pagination">
                <button class="pg-btn" id="mrPrev" onclick="changePage(-1)">&#8592;</button>
                <input type="number" class="pg-input" id="mrPageInput" value="1" onchange="jumpToPage()">
                <span class="pg-total" id="mrTotalPages">/ 1</span>
                <button class="pg-btn" id="mrNext" onclick="changePage(1)">&#8594;</button>
            </div>
        </div>
        <div class="mr-list-scroll" id="mrList">
            <div class="state-msg">Loading runs...</div>
        </div>
    </div>

    <!-- 2. Toolbar -->
    <div class="eh-mid-panel">
        <div class="grid-toolbar">
            <div class="gt-left">
                <div class="gt-info" id="gridInfo">Select a run above</div>
                <label class="chk-label" title="Hide videos already queued">
                    <input type="checkbox" id="hideQueued" onchange="applyGridFilters()">
                    Hide Queued
                </label>
            </div>
            <div class="gt-actions" id="gridActions" style="display:none;">
                <a id="lnkScrollMagic" href="#" target="_blank" class="action-btn" title="ScrollMagic">
                    <i class="bi bi-collection-play"></i>
                </a>
                <div style="width:1px; background:var(--border); margin:0 8px;"></div>
                <button class="action-btn" onclick="toggleAll(false)">None</button>
                <button class="action-btn primary" onclick="toggleAll(true)">All</button>
            </div>
        </div>
    </div>

    <!-- 3. Grid -->
    <div class="eh-grid-area">
        <div class="state-msg" id="gridState">
            <div>&#8593; Select a Map Run</div>
        </div>
        <div class="frames-grid" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- Footer -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 videos selected</div>
        <button class="submit-btn" id="submitBtn" disabled onclick="submitRegeneration()">
            Flag for Regeneration
        </button>
    </div>
</div>

<!-- RICH VIDEO DETAIL MODAL -->
<div id="videoDetailModal" class="modal-overlay">
    <div class="detail-modal-card">
        <div class="modal-header" style="padding:10px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between;">
            <strong>Video Details</strong>
            <button class="btn btn-sm btn-outline-secondary close-modal" onclick="closeVideoDetailModal()" type="button">Close</button>
        </div>
        
        <div class="detail-player-wrapper">
            <video id="detailVideoPlayer" controls playsinline controlsList="nodownload"></video>
        </div>

        <div class="detail-content">
            <div class="detail-title" id="detailVideoName"></div>
            <div class="detail-meta-row" id="detailMeta"></div>
            <div class="detail-actions-grid" id="detailActionButtons"></div>
            <button id="detailDescTrigger" class="detail-desc-btn">
                📄 <strong>Description:</strong> <span id="detailDescSnippet"></span>
            </button>
        </div>
    </div>
</div>

<!-- Modules Output -->
<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script>
let curPage = 1, totalPages = 1, currentRunId = null, currentVideos = [], selectedIds = new Set(), debounceTimer;

document.addEventListener('DOMContentLoaded', () => { loadMapRuns(1); });

function debounceSearch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadMapRuns(1), 300); }
function changePage(d) { const n = curPage + d; if (n >= 1 && n <= totalPages) loadMapRuns(n); }
function jumpToPage() { const v = parseInt(document.getElementById('mrPageInput').value); if (v >= 1 && v <= totalPages) loadMapRuns(v); }

function loadMapRuns(page) {
    const list = document.getElementById('mrList');
    const search = document.getElementById('mrSearch').value.trim();
    if(page === 1) list.scrollTop = 0;
    
    fetch(`?api_action=get_map_runs&limit=7&offset=${(page-1)*7}&search=${encodeURIComponent(search)}`)
        .then(r => r.json()).then(res => {
            if(res.status !== 'success') return;
            curPage = page; totalPages = Math.ceil(res.total/7) || 1;
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
            
            list.innerHTML = '';
            if(!res.data.length) { list.innerHTML = '<div class="state-msg">No runs with videos found</div>'; return; }
            
            res.data.forEach(run => {
                const el = document.createElement('div');
                el.className = `mr-item ${run.id == currentRunId ? 'active' : ''}`;
                el.onclick = () => selectRun(run.id, el);
                el.innerHTML = `<div class="mr-id">#${run.id}</div><div class="mr-note">${run.note||'No note'}</div><div class="mr-meta">${run.item_count} vids • ${run.created_at.substr(0,10)}</div>`;
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
    
    state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Loading videos...</div>';
    grid.style.display = 'none';
    
    fetch(`?api_action=get_videos&map_run_id=${runId}`).then(r => r.json()).then(res => {
        currentVideos = res.data;
        selectedIds.clear();
        renderGrid();
        state.style.display = 'none';
        grid.style.display = 'grid';
        document.getElementById('gridActions').style.display = 'flex';
        info.innerHTML = `Run <strong>#${runId}</strong> • ${currentVideos.length} videos`;
        updateSummary();
    });
}

function renderGrid() {
    const grid = document.getElementById('framesGrid');
    const hide = document.getElementById('hideQueued').checked;
    grid.innerHTML = '';
    
    currentVideos.forEach(v => {
        const isQueued = parseInt(v.is_queued) === 1;
        const card = document.createElement('div');
        card.className = 'f-card';
        if(isQueued) card.classList.add('is-queued');
        if(isQueued && hide) card.classList.add('hidden-in-grid');
        
        card.dataset.id = v.id;
        card.dataset.queued = isQueued ? "1" : "0";
        
        // View Button
        const viewBtn = document.createElement('div');
        viewBtn.className = 'f-view-btn';
        viewBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        viewBtn.onclick = (e) => { e.stopPropagation(); openDetailModal(v.id); };
        
        // Thumbnail
        const img = document.createElement('img');
        img.className = 'f-thumb';
        img.src = v.thumbnail || '';
        img.loading = "lazy";
        
        // Label
        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = (e) => { e.preventDefault(); toggleSelect(v.id, card); };
        label.innerHTML = `<span>#${v.id}</span><div class="f-select-trigger"></div>`;
        
        card.appendChild(img);
        card.appendChild(viewBtn);
        card.appendChild(label);
        grid.appendChild(card);
    });
}

function toggleSelect(id, card) {
    if(selectedIds.has(id)) { selectedIds.delete(id); card.classList.remove('selected'); }
    else { selectedIds.add(id); card.classList.add('selected'); }
    updateSummary();
}

function toggleAll(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        if(c.classList.contains('hidden-in-grid')) { c.classList.remove('selected'); return; }
        const id = parseInt(c.dataset.id);
        if(select) { selectedIds.add(id); c.classList.add('selected'); }
        else { selectedIds.delete(id); c.classList.remove('selected'); }
    });
    updateSummary();
}

function applyGridFilters() {
    const hide = document.getElementById('hideQueued').checked;
    document.querySelectorAll('.f-card').forEach(c => {
        if(c.dataset.queued === "1") {
            if(hide) {
                if(c.classList.contains('selected')) { selectedIds.delete(parseInt(c.dataset.id)); c.classList.remove('selected'); }
                c.classList.add('hidden-in-grid');
            } else c.classList.remove('hidden-in-grid');
        }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
    document.getElementById('submitBtn').disabled = count === 0;
}

// RICH MODAL LOGIC
function openDetailModal(id) {
    const vid = currentVideos.find(v => v.id == id);
    if(!vid) return;
    
    const p = document.getElementById('detailVideoPlayer');
    p.src = vid.url; p.load();
    
    document.getElementById('detailVideoName').textContent = vid.name;
    document.getElementById('detailMeta').innerHTML = `<span>ID: ${vid.id}</span><span>Size: ${(vid.file_size/1024/1024).toFixed(2)} MB</span><span>Duration: ${vid.duration}s</span>`;
    document.getElementById('detailDescSnippet').textContent = vid.description || 'No description';
    
    // Actions - Buttons
    let animaticBtn = '';
    if(vid.animatic_id) {
        // Only show if linked
        animaticBtn = `<button class="btn edit-animatic-btn" data-animatic-id="${vid.animatic_id}" style="border-color:var(--blue); color:var(--blue);">🎬 Animatics</button>`;
    }

    const btns = document.getElementById('detailActionButtons');
    btns.innerHTML = `
        <button class="btn" onclick="window.VideoFrameExtractor.open('${vid.url}', ${vid.id})">✂️ Frame</button>
        ${animaticBtn}
        <button class="btn" onclick="triggerAdminAction('regenerate_thumbnail', ${vid.id})">🌇 Thumb</button>
        <button class="btn" onclick="triggerAdminAction('queue_rembg', ${vid.id})">◩ Rembg</button>
        <a class="btn" href="${vid.url}" download target="_blank">⬇️ DL</a>
    `;
    
    // Attach event listeners for dynamic buttons after insertion
    btns.querySelectorAll('.edit-animatic-btn').forEach(b => {
        b.onclick = () => {
            const aId = b.dataset.animaticId;
            if(window.showEntityFormInModal) window.showEntityFormInModal('animatics', aId);
            else alert("CRUD Modal not available.");
        };
    });

    document.getElementById('videoDetailModal').classList.add('active');
}

function closeVideoDetailModal() {
    document.getElementById('videoDetailModal').classList.remove('active');
    document.getElementById('detailVideoPlayer').pause();
}

function triggerAdminAction(act, id) {
    if(!confirm('Perform this action?')) return;
    fetch('video_admin_api.php?action='+act, {
        method:'POST', body: JSON.stringify({id: id})
    }).then(r=>r.json()).then(d => {
        if(d.status==='ok') Toast.show('Success', 'success');
        else Toast.show(d.message, 'error');
    });
}

function submitRegeneration() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.textContent = 'Processing...';
    fetch('?api_action=submit_regeneration', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({video_ids: Array.from(selectedIds)})
    }).then(r=>r.json()).then(res => {
        if(res.status==='success') {
            Toast.show(`Flagged ${res.count} animatics`);
            selectedIds.clear();
            if(currentRunId) selectRun(currentRunId, null); // refresh
        } else Toast.show(res.message, 'error');
    }).finally(() => { btn.disabled = false; btn.textContent = 'Flag for Regeneration'; });
}
</script>
<?php
// Include modal_frame_details to support showEntityFormInModal
include __DIR__ . '/modal_frame_details.php'; 
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
