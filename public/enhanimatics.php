<?php
// public/enhanimatics.php
// Enhanimatics -- Unified Sketches Tool (Import + Enhance)
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        // 1. GET MAP RUNS
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

        // 2. GET FRAMES (Check both Animatics and Enhancements)
        if ($action === 'get_frames') {
            $runId = (int)$_GET['map_run_id'];
            
            $sql = "SELECT f.id as frame_id, f.filename, f.name, f.prompt,
                    -- Check if in Animatics
                    CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END as is_imported,
                    -- Check if in Frame Enhancements
                    CASE WHEN fe.id IS NOT NULL THEN 1 ELSE 0 END as is_enhanced
                    FROM frames f 
                    LEFT JOIN animatics a ON a.img2img_frame_id = f.id
                    LEFT JOIN frame_enhancements fe ON fe.img2img_frame_id = f.id
                    WHERE f.map_run_id = $runId 
                    ORDER BY f.id ASC";
            
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$rows]);
            exit;
        }

        // 3. ACTION: IMPORT TO ANIMATICS
        if ($action === 'submit_import') {
            $input = json_decode(file_get_contents('php://input'), true);
            $frameIds = $input['frame_ids'] ?? [];

            if (empty($frameIds)) throw new Exception("No frames selected.");

            $idsStr = implode(',', array_map('intval', $frameIds));
            
            // Fetch source data (Sketch Name/Desc)
            $sql = "SELECT f.id as frame_id, f.filename, 
                           s.name as sketch_name, s.description as sketch_desc,
                           f.name as frame_name, f.prompt as frame_prompt
                    FROM frames f
                    LEFT JOIN sketches s ON (f.entity_id = s.id AND f.entity_type = 'sketches')
                    WHERE f.id IN ($idsStr)";
            
            $framesData = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            if (empty($framesData)) throw new Exception("Selected frames not found.");

            $stmt = $pdo->prepare("
                INSERT INTO animatics 
                (name, description, img2img, img2img_frame_id, regenerate_videos, created_at, updated_at) 
                VALUES (?, ?, 1, ?, 1, NOW(), NOW())
            ");

            $count = 0;
            $pdo->beginTransaction();
            foreach ($framesData as $row) {
                $name = !empty($row['sketch_name']) ? $row['sketch_name'] : ($row['frame_name'] ?: $row['filename']);
                $description = !empty($row['sketch_desc']) ? $row['sketch_desc'] : $row['frame_prompt'];
                $stmt->execute([$name, $description, $row['frame_id']]);
                $count++;
            }
            $pdo->commit();

            echo json_encode(['status'=>'success', 'count'=>$count]);
            exit;
        }

        // 4. ACTION: ENHANCE FRAMES
        if ($action === 'submit_enhancement') {
            $input = json_decode(file_get_contents('php://input'), true);
            $frameIds    = $input['frame_ids'] ?? [];
            $description = trim($input['description'] ?? '');

            if (empty($frameIds)) throw new Exception("No frames selected.");
            if (empty($description)) throw new Exception("Please enter an enhancement instruction.");

            $stmt = $pdo->prepare("INSERT INTO frame_enhancements (entity_type, entity_id, description, img2img_frame_id, regenerate_images) VALUES ('sketches', ?, ?, ?, 1)");
            
            $idsStr = implode(',', array_map('intval', $frameIds));
            $metaData = $pdo->query("SELECT id, entity_id FROM frames WHERE id IN ($idsStr)")->fetchAll(PDO::FETCH_KEY_PAIR);

            $count = 0;
            $pdo->beginTransaction();
            foreach ($frameIds as $fid) {
                $sketchId = $metaData[$fid] ?? null;
                if ($sketchId) {
                    $stmt->execute([$sketchId, $description, $fid]);
                    $count++;
                }
            }
            $pdo->commit();

            echo json_encode(['status'=>'success', 'count'=>$count]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'Enhanimatics Unified';
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
        --purple: #8b5cf6; 
        --purple-dim: rgba(139, 92, 246, 0.1);
        --amber: #f59e0b;
        --amber-dim: rgba(245, 158, 11, 0.1);
        --red: #ef4444;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

    /* ── HEADER ── */
    .eh-header {
        flex-shrink: 0; padding: 0 16px; height: 50px;
        background: var(--card); border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
    .eh-title span { color: var(--purple); }

    /* NAV LINKS */
    .eh-nav { display: flex; height: 100%; gap: 0; }
    .nav-link-btn {
        display: flex; align-items: center; padding: 0 20px;
        color: var(--text-muted); text-decoration: none;
        font-size: 0.85rem; font-weight: 700; border-left: 1px solid var(--border);
        transition: all 0.2s; height: 100%;
    }
    .nav-link-btn:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    .nav-link-btn.active { background: var(--bg); color: var(--purple); border-bottom: 2px solid var(--purple); }

    /* ── TOP PANEL ── */
    .eh-top-panel { flex-shrink: 0; display: flex; flex-direction: column; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2); max-height: 30vh; }
    .mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); }
    .mr-search-input { flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
    .mr-search-input:focus { outline: none; border-color: var(--purple); }
    
    .mr-pagination { display: flex; align-items: center; gap: 4px; }
    .pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
    .pg-btn:hover:not(:disabled) { border-color: var(--purple); color: var(--purple); }
    .pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--purple); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
    .pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

    .mr-list-scroll { overflow-y: auto; overflow-x: hidden; min-height: 60px; }
    .mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
    .mr-item:hover { background: rgba(255,255,255,0.03); }
    .mr-item.active { background: var(--purple-dim); border-left: 3px solid var(--purple); padding-left: 9px; }
    .mr-id { font-size: 0.7rem; font-weight: 700; color: var(--purple); min-width: 40px; }
    .mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; }

    /* ── MID PANEL (Unified Config) ── */
    .eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
    
    .config-bar { padding: 8px 12px; display: flex; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .prompt-input {
        width: 100%; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border);
        background: rgba(0,0,0,0.3); color: var(--text); font-family: inherit; font-size: 0.85rem;
    }
    .prompt-input:focus { outline: none; border-color: var(--amber); } /* Amber for Enhancement focus */

    .grid-toolbar {
        padding: 6px 12px; background: rgba(0,0,0,0.2); 
        display: flex; align-items: center; justify-content: space-between;
    }
    .gt-left { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .gt-info { font-size: 0.7rem; color: var(--text-muted); margin-right: 10px; }
    
    .action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;}
    .action-btn:hover { color: var(--purple); border-color: var(--purple); }
    .action-btn.primary { border-color: var(--purple); color: var(--purple); }

    /* Checkboxes */
    .chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; select-none: none; }
    /* Dual colors for checkboxes */
    #hideImported:checked { accent-color: var(--purple); }
    #hideEnhanced:checked { accent-color: var(--amber); }

    /* ── GRID AREA ── */
    .eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: #000; min-height: 0; }
    .frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; padding-bottom: 20px; }
    
    .f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
    .f-card.selected { border-color: var(--text); box-shadow: 0 0 0 1px var(--text); }
    
    /* STATES */
    /* 1. Imported (Purple/Dark) */
    .f-card.is-imported { 
        border-color: #333; 
        opacity: 0.25; 
        filter: grayscale(80%); 
    }
    .f-card.is-imported::before {
        content: "IMPORTED"; position: absolute; top: 40%; left: 0; right: 0;
        text-align: center; font-size: 0.7rem; font-weight: 900; 
        color: rgba(139, 92, 246, 0.6); transform: rotate(-15deg); pointer-events: none; z-index: 5;
    }
    .f-card.is-imported.hidden-in-grid { display: none; }
    
    /* 2. Enhanced (Amber/Sepia) */
    .f-card.is-enhanced { 
        border-color: rgba(245, 158, 11, 0.4); opacity: 0.4; 
        filter: sepia(80%) hue-rotate(-10deg) saturate(1.5) brightness(0.6);
    }
    .f-card.is-enhanced::after {
        content: "ENHANCED"; position: absolute; bottom: 35px; left: 0; right: 0;
        text-align: center; font-size: 0.65rem; font-weight: 800; 
        color: rgba(245, 158, 11, 0.8); pointer-events: none; z-index: 5;
        text-shadow: 0 1px 2px #000;
    }
    .f-card.is-enhanced.hidden-in-grid { display: none; }

    /* PhotoSwipe Trigger */
    .f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
    .f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
    .f-link:hover img { transform: scale(1.03); }
    
    /* View Button */
    .f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
    .f-card:hover .f-view-btn { opacity: 1; }
    .f-view-btn:hover { background: var(--text); border-color: var(--text); color: #000; }

    /* Selection Label */
    .f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
    .f-card.selected .f-label { background: rgba(255,255,255,0.1); color: #fff; border-top-color: #fff; }
    .f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; }
    .f-card.selected .f-select-trigger { background: #fff; border-color: #fff; color: #000; font-size: 10px; font-weight: 900; }
    .f-card.selected .f-select-trigger::after { content: '✓'; }

    /* ── FOOTER (Dual Action) ── */
    .eh-footer {
        flex-shrink: 0; padding: 10px 16px; 
        padding-bottom: max(10px, env(safe-area-inset-bottom));
        background: var(--card); border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        z-index: 10; position: relative;
    }
    .ft-summary { font-size: 0.75rem; color: var(--text-muted); }
    
    .ft-actions { display: flex; gap: 10px; }
    
    .btn-action {
        padding: 12px 20px; border-radius: 4px; border: none; 
        font-size: 0.85rem; font-weight: 700; text-transform: uppercase;
        cursor: pointer; font-family: inherit; transition: filter 0.15s;
        color: #fff; min-width: 120px;
    }
    .btn-action:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }
    
    .btn-enhance { background: var(--amber); color: #000; }
    .btn-import { background: var(--purple); }

    /* Modal */
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
        <div class="eh-title"><span>&#10024;</span>  <span style="font-size:0.7em; opacity:0.6; margin-left:5px;"> </span></div>
        <div class="eh-nav">
            <a href="enhanimatics.php" class="nav-link-btn active">Enhanimatics</a>
            <a href="regenerator.php" class="nav-link-btn">Regenerator</a>
        </div>
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

    <!-- 2. Config & Toolbar -->
    <div class="eh-mid-panel">
        <!-- Prompt Input (For Enhancement) -->
        <div class="config-bar">
            <input type="text" class="prompt-input" id="enhancePrompt" value="Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is" placeholder="Enhancement Instruction...">
        </div>
        
        <div class="grid-toolbar">
            <div class="gt-left">
                <div class="gt-info" id="gridInfo">Select a run above</div>
                
                <label class="chk-label" title="Hide frames imported to Animatics">
                    <input type="checkbox" id="hideImported" onchange="applyGridFilters()">
                    Hide Imported
                </label>
                
                <label class="chk-label" title="Hide frames already enhanced">
                    <input type="checkbox" id="hideEnhanced" onchange="applyGridFilters()">
                    Hide Enhanced
                </label>
            </div>

            <div class="gt-actions" id="gridActions" style="display:none;">
                <a id="lnkScrollMagic" href="#" target="_blank" class="action-btn" title="ScrollMagic">
                    <i class="bi bi-collection-play" style="font-size: 1.1em;"></i>
                </a>
                <div style="width:1px; background:var(--border); margin:0 8px;"></div>
                <button class="action-btn" onclick="toggleAll(false)">None</button>
                <button class="action-btn primary" onclick="toggleAll(true)" style="border-color:var(--text); color:var(--text);">All</button>
            </div>
        </div>
    </div>

    <!-- 3. Grid -->
    <div class="eh-grid-area">
        <div class="state-msg" id="gridState">
            <div>&#8593; Select a Map Run</div>
        </div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- Footer: Dual Actions -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 selected</div>
        <div class="ft-actions">
            <button class="btn-action btn-enhance" id="btnEnhance" disabled onclick="submitEnhancement()">
                Enhance Frames
            </button>
            <button class="btn-action btn-import" id="btnImport" disabled onclick="submitImport()">
                Import to Animatics
            </button>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
let curPage = 1, totalPages = 1, currentRunId = null, currentFrames = [], selectedFrameIds = new Set(), debounceTimer;
const DEFAULT_PROMPT = "Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is";

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
            if(!res.data.length) { list.innerHTML = '<div class="state-msg">No runs found</div>'; return; }
            res.data.forEach(run => {
                const el = document.createElement('div');
                el.className = `mr-item ${run.id == currentRunId ? 'active' : ''}`;
                el.onclick = () => selectRun(run.id, el);
                el.innerHTML = `<div class="mr-id">#${run.id}</div><div class="mr-note">${esc(run.note||'No note')}</div><div class="mr-meta">${run.frame_count} fr • ${run.created_at.substr(0,10)}</div>`;
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
    state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Loading...</div>';
    grid.style.display = 'none';
    document.getElementById('gridActions').style.display = 'none';
    
    fetch(`?api_action=get_frames&map_run_id=${runId}`).then(r => r.json()).then(res => {
        currentFrames = res.data;
        selectedFrameIds.clear();
        renderGrid();
        state.style.display = 'none';
        grid.style.display = 'grid';
        document.getElementById('gridActions').style.display = 'flex';
        document.getElementById('gridInfo').innerHTML = `Run <strong>#${runId}</strong> • ${currentFrames.length} frames`;
        updateSummary();
    });
}

function renderGrid() {
    const grid = document.getElementById('framesGrid');
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    grid.innerHTML = '';
    
    currentFrames.forEach(f => {
        const isImp = parseInt(f.is_imported) === 1;
        const isEnh = parseInt(f.is_enhanced) === 1;
        
        const card = document.createElement('div');
        card.className = 'f-card';
        if(isImp) card.classList.add('is-imported');
        if(isEnh) card.classList.add('is-enhanced');
        
        if((isImp && hideImp) || (isEnh && hideEnh)) card.classList.add('hidden-in-grid');
        
        card.dataset.fid = f.frame_id;
        card.dataset.imported = isImp ? "1" : "0";
        card.dataset.enhanced = isEnh ? "1" : "0";
        
        const link = document.createElement('a');
        link.className = 'f-link';
        link.href = f.filename; link.target = '_blank';
        link.dataset.pswpWidth = 1024; link.dataset.pswpHeight = 1024;
        
        const img = document.createElement('img');
        img.src = f.filename; img.loading = "lazy";
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
    
    if(typeof PhotoSwipeLightbox !== 'undefined') {
        new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe }).init();
    }
}

function applyGridFilters() {
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    
    document.querySelectorAll('.f-card').forEach(c => {
        const isImp = c.dataset.imported === "1";
        const isEnh = c.dataset.enhanced === "1";
        
        if((isImp && hideImp) || (isEnh && hideEnh)) {
            if(c.classList.contains('selected')) { 
                selectedFrameIds.delete(parseInt(c.dataset.fid)); 
                c.classList.remove('selected'); 
            }
            c.classList.add('hidden-in-grid');
        } else {
            c.classList.remove('hidden-in-grid');
        }
    });
    updateSummary();
}

function toggleFrame(fid, card) {
    if(card.classList.contains('hidden-in-grid')) return;
    if(selectedFrameIds.has(fid)) { selectedFrameIds.delete(fid); card.classList.remove('selected'); }
    else { selectedFrameIds.add(fid); card.classList.add('selected'); }
    updateSummary();
}

function toggleAll(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        if(c.classList.contains('hidden-in-grid')) { c.classList.remove('selected'); return; }
        const fid = parseInt(c.dataset.fid);
        if(select) { selectedFrameIds.add(fid); c.classList.add('selected'); }
        else { selectedFrameIds.delete(fid); c.classList.remove('selected'); }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
    const disabled = count === 0;
    document.getElementById('btnEnhance').disabled = disabled;
    document.getElementById('btnImport').disabled = disabled;
}

// ACTIONS
function submitImport() {
    performAction('submit_import', {frame_ids: Array.from(selectedFrameIds)}, 'Import to Animatics');
}

function submitEnhancement() {
    const prompt = document.getElementById('enhancePrompt').value.trim();
    if(!prompt) { Toast.show('Enter instruction for enhancement', 'error'); return; }
    performAction('submit_enhancement', {frame_ids: Array.from(selectedFrameIds), description: prompt}, 'Enhance Frames');
}

function performAction(action, data, btnText) {
    const btnId = action === 'submit_import' ? 'btnImport' : 'btnEnhance';
    const btn = document.getElementById(btnId);
    
    btn.disabled = true; btn.textContent = 'Processing...';
    
    fetch('?api_action=' + action, {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(res => {
        if(res.status === 'success') {
            Toast.show(`Success: ${res.count} frames processed`);
            selectedFrameIds.clear();
            
            // Reset to default instead of clearing
            if(action === 'submit_enhancement') {
                document.getElementById('enhancePrompt').value = DEFAULT_PROMPT;
            }
            
            if(currentRunId) {
                fetch(`?api_action=get_frames&map_run_id=${currentRunId}`).then(r=>r.json()).then(d => {
                    currentFrames = d.data; renderGrid(); updateSummary();
                });
            }
        } else {
            Toast.show(res.message, 'error');
        }
    }).finally(() => {
        btn.disabled = false; btn.textContent = btnText;
        updateSummary();
    });
}

// Modal
function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeFrameModal(); });
function esc(s) { return s ? s.toString().replace(/"/g, '&quot;') : ''; }
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>