<?php
// public/sketchmig.php
// SketchMig -- Smart Entity to Sketches Migration
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php';

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        // 1. GET MAP RUNS
        if ($action === 'get_map_runs') {
            $limit  = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = $_GET['search'] ?? '';
            $type   = $_GET['entity_type'] ?? 'animas';
            
            if (!preg_match('/^[a-z0-9_]+$/', $type)) $type = 'animas';

            $where = "entity_type = " . $pdo->quote($type);
            
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

        // 2. GET FRAMES (With Migration Check)
        if ($action === 'get_frames') {
            $runId = (int)$_GET['map_run_id'];
            $type  = $_GET['entity_type'] ?? 'animas';
            
            if (!preg_match('/^[a-z0-9_]+$/', $type)) throw new Exception("Invalid entity type");

            $tableName = $type; 
            
            // Check sketch_migration_frames to see if frame was already copied
            $sql = "SELECT f.id as frame_id, f.filename, f.prompt,
                           e.id as entity_id, e.name as entity_name,
                           CASE WHEN smf.source_frame_id IS NOT NULL THEN 1 ELSE 0 END as is_migrated
                    FROM frames f 
                    LEFT JOIN $tableName e ON f.entity_id = e.id 
                    LEFT JOIN sketch_migration_frames smf ON f.id = smf.source_frame_id
                    WHERE f.map_run_id = $runId 
                    ORDER BY f.id ASC";
            
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$rows]);
            exit;
        }

        // 3. SUBMIT MIGRATION
        if ($action === 'submit_migration') {
            $input = json_decode(file_get_contents('php://input'), true);
            $frameIds = $input['frame_ids'] ?? [];
            $sourceType = $input['entity_type'] ?? '';

            if (empty($frameIds)) throw new Exception("No frames selected.");
            if (empty($sourceType) || !preg_match('/^[a-z0-9_]+$/', $sourceType)) throw new Exception("Invalid source type.");

            $idsStr = implode(',', array_map('intval', $frameIds));
            
            // Fetch source data
            $sql = "SELECT f.*, 
                           e.name as source_name, e.description as source_desc, 
                           e.prompt_negative as source_neg, e.seed as source_seed
                    FROM frames f
                    JOIN $sourceType e ON f.entity_id = e.id
                    WHERE f.id IN ($idsStr)";
            
            $sourceRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if (empty($sourceRows)) throw new Exception("Could not fetch source data.");

            // Group by Entity ID to prevent duplicate sketches
            $grouped = [];
            foreach ($sourceRows as $row) {
                $eid = $row['entity_id'];
                if (!isset($grouped[$eid])) {
                    $grouped[$eid] = [
                        'entity_data' => [
                            'id' => $eid,
                            'name' => $row['source_name'],
                            'description' => $row['source_desc'],
                            'prompt_negative' => $row['source_neg'],
                            'seed' => $row['source_seed']
                        ],
                        'frames' => []
                    ];
                }
                $grouped[$eid]['frames'][] = $row;
            }

            $pdo->beginTransaction();

            // Create new Map Run
            $note = "Migration from " . ucfirst($sourceType) . " (" . count($frameIds) . " frames)";
            $stmtMR = $pdo->prepare("INSERT INTO map_runs (entity_type, note, created_at) VALUES ('sketches', ?, NOW())");
            $stmtMR->execute([$note]);
            $newMapRunId = $pdo->lastInsertId();

            $totalSketches = 0;
            $totalFrames = 0;

            // Prepared Statements
            $stmtCheckMap = $pdo->prepare("SELECT target_sketch_id FROM sketch_migration_entities WHERE source_type = ? AND source_id = ?");
            $stmtInsertMap = $pdo->prepare("INSERT INTO sketch_migration_entities (source_type, source_id, target_sketch_id) VALUES (?, ?, ?)");
            
            $stmtSketch = $pdo->prepare("
                INSERT INTO sketches 
                (name, description, prompt_negative, seed, created_at, updated_at, active_map_run_id, regenerate_images)
                VALUES (?, ?, ?, ?, NOW(), NOW(), ?, 0)
            ");
            
            // Frame: entity_id points to new sketch
            $stmtFrame = $pdo->prepare("
                INSERT INTO frames 
                (map_run_id, name, filename, prompt, prompt_negative, seed, entity_type, entity_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'sketches', ?, NOW())
            ");

            // *** CRITICAL: Frames 2 Sketches Mapping ***
            $stmtLink = $pdo->prepare("INSERT INTO frames_2_sketches (from_id, to_id) VALUES (?, ?)");
            
            // Migration Tracking
            $stmtFrameMap = $pdo->prepare("INSERT IGNORE INTO sketch_migration_frames (source_frame_id, target_frame_id) VALUES (?, ?)");

            foreach ($grouped as $srcEid => $group) {
                $eData = $group['entity_data'];
                $targetSketchId = null;

                // 1. Resolve Target Sketch
                $stmtCheckMap->execute([$sourceType, $eData['id']]);
                $existing = $stmtCheckMap->fetchColumn();

                if ($existing) {
                    $targetSketchId = $existing;
                    // Update timestamp to show activity
                    $pdo->prepare("UPDATE sketches SET updated_at = NOW() WHERE id = ?")->execute([$targetSketchId]);
                } else {
                    // Create New Sketch
                    $baseName = $eData['name'];
                    $newName = $baseName; 
                    
                    // Name Collision Check
                    $checkName = $pdo->prepare("SELECT id FROM sketches WHERE name = ?");
                    $checkName->execute([$newName]);
                    if ($checkName->fetch()) {
                        $newName = $baseName . " (Mig " . date('ymd') . ")";
                    }

                    $stmtSketch->execute([
                        $newName, 
                        $eData['description'], 
                        $eData['prompt_negative'], 
                        $eData['seed'],
                        $newMapRunId
                    ]);
                    $targetSketchId = $pdo->lastInsertId();
                    
                    // Save Mapping
                    $stmtInsertMap->execute([$sourceType, $eData['id'], $targetSketchId]);
                    $totalSketches++;
                }

                // 2. Clone Frames
                foreach ($group['frames'] as $fRow) {
                    $stmtFrame->execute([
                        $newMapRunId,
                        $eData['name'], // Name of frame usually matches entity
                        $fRow['filename'], 
                        $fRow['prompt'],
                        $fRow['prompt_negative'],
                        $fRow['seed'],
                        $targetSketchId // entity_id = sketch_id
                    ]);
                    $newFrameId = $pdo->lastInsertId();
                    
                    // *** INSERT MAPPING ***
                    $stmtLink->execute([$newFrameId, $targetSketchId]);
                    
                    // Track Migration
                    $stmtFrameMap->execute([$fRow['id'], $newFrameId]);
                    
                    $totalFrames++;
                }
            }

            $pdo->commit();

            echo json_encode([
                'status' => 'success', 
                'count' => $totalFrames, 
                'sketches_created' => $totalSketches,
                'map_run_id' => $newMapRunId
            ]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'SketchMig';
ob_start();
?>
<!-- Dependencies -->
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
        --accent: #2dd4bf; /* Teal */
        --accent-dim: rgba(45, 212, 191, 0.1);
        --green: #10b981;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout { 
        display: flex; flex-direction: column; 
        height: 100vh; height: 100dvh; 
        overflow: hidden; 
    }

    /* ── HEADER ── */
    .eh-header {
        flex-shrink: 0; padding: 10px 16px;
        background: var(--card); border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--accent); display: flex; align-items: center; gap: 8px; }

    .source-select {
        background: var(--bg); color: var(--text); border: 1px solid var(--border);
        padding: 4px 8px; border-radius: 4px; font-family: inherit; font-size: 0.85rem;
        cursor: pointer; outline: none;
    }
    .source-select:focus { border-color: var(--accent); }

    /* ── TOP PANEL ── */
    .eh-top-panel {
        flex-shrink: 0; display: flex; flex-direction: column;
        border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2); max-height: 30vh;
    }
    .mr-controls-row {
        display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border);
        align-items: center; background: var(--card);
    }
    .mr-search-input {
        flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border);
        background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem;
    }
    .mr-search-input:focus { outline: none; border-color: var(--accent); }

    .mr-pagination { display: flex; align-items: center; gap: 4px; }
    .pg-btn {
        width: 26px; height: 26px; background: transparent; border: 1px solid var(--border);
        color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0;
    }
    .pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
    .pg-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    
    .pg-input {
        width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border);
        color: var(--accent); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700;
        padding: 4px 0; -moz-appearance: textfield;
    }
    .pg-input:focus { outline: none; border-color: var(--accent); }
    .pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

    .mr-list-scroll { overflow-y: auto; overflow-x: hidden; min-height: 60px; }
    .mr-item {
        padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s;
        display: flex; align-items: center; gap: 10px;
    }
    .mr-item:hover { background: rgba(255,255,255,0.03); }
    .mr-item.active { background: var(--accent-dim); border-left: 3px solid var(--accent); padding-left: 9px; }
    .mr-id { font-size: 0.7rem; font-weight: 700; color: var(--accent); min-width: 40px; }
    .mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; }

    /* ── MID PANEL ── */
    .eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
    .grid-toolbar {
        padding: 6px 12px; background: rgba(0,0,0,0.2); border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .gt-info { font-size: 0.7rem; color: var(--text-muted); }
    .gt-actions { display: flex; gap: 8px; }
    .action-btn {
        padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700;
        border: 1px solid var(--border); background: transparent; color: var(--text-muted);
        cursor: pointer; text-transform: uppercase; font-family: inherit;
    }
    .action-btn.primary { border-color: var(--accent); color: var(--accent); }

    /* Checkbox */
    .chk-label {
        display: flex; align-items: center; gap: 6px; font-size: 0.7rem; 
        color: var(--text); cursor: pointer; select-none: none;
    }
    .chk-label input { accent-color: var(--accent); }

    /* ── GRID AREA ── */
    .eh-grid-area { 
        flex: 1; overflow-y: auto; padding: 10px; 
        position: relative; background: #000; min-height: 0; 
    }
    .frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; padding-bottom: 20px; }
    .f-card {
        aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px;
        position: relative; overflow: hidden;
    }
    .f-card.selected { border-color: var(--accent); }
    
    /* MIGRATED STATE */
    .f-card.is-migrated { 
        border-color: #333; 
        opacity: 0.4; 
        filter: grayscale(60%); 
    }
    .f-card.is-migrated::before {
        content: "MIGRATED"; position: absolute; top: 40%; left: 0; right: 0;
        text-align: center; font-size: 0.7rem; font-weight: 900; 
        color: rgba(255,255,255,0.4); transform: rotate(-15deg); pointer-events: none; z-index: 5;
    }
    .f-card.is-migrated.hidden-in-grid { display: none; }

    .f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
    .f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
    .f-link:hover img { transform: scale(1.03); }
    
    .f-label {
        position: absolute; bottom: 0; left: 0; right: 0; height: 24px;
        background: rgba(20,20,25,0.95); padding: 0 6px;
        font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
        cursor: pointer; user-select: none; z-index: 2;
    }
    .f-label:hover { background: #222; color: #fff; }
    .f-card.selected .f-label { background: var(--accent-dim); color: var(--accent); border-top-color: var(--accent); }

    .f-select-trigger {
        width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(0,0,0,0.5); font-size: 0; transition: all 0.1s;
    }
    .f-card.selected .f-select-trigger { background: var(--accent); border-color: var(--accent); color: #000; font-size: 10px; font-weight: 900; }
    .f-card.selected .f-select-trigger::after { content: '✓'; }

    /* ── FOOTER ── */
    .eh-footer {
        flex-shrink: 0; padding: 10px 16px; 
        /* Safe Area Fix */
        padding-bottom: max(10px, env(safe-area-inset-bottom));
        background: var(--card); border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        z-index: 10; position: relative;
    }
    .ft-summary { font-size: 0.75rem; color: var(--text-muted); }
    .submit-btn {
        padding: 12px 24px; border-radius: 4px; background: var(--accent); color: #000;
        border: none; font-size: 0.9rem; font-weight: 700; text-transform: uppercase;
        cursor: pointer; font-family: inherit; transition: filter 0.15s;
    }
    .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    /* Helper States */
    .state-msg {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; padding: 20px; text-align: center;
    }
    .spinner {
        width: 20px; height: 20px; border: 2px solid var(--accent-dim);
        border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .pswp { z-index: 99999; }
</style>

<div class="eh-layout">
    <!-- Header -->
    <div class="eh-header">
        <div class="eh-title"><span>&#10145;</span> SKETCHMIG <span style="font-size:0.7em; opacity:0.6; margin-left:5px;">// SMART MIGRATOR</span></div>
        <select id="sourceType" class="source-select" onchange="resetAndLoad()">
            <?php foreach($entityIcons as $type => $icon): ?>
                <?php if($type !== 'sketches' && $type !== 'pastebin' && $type !== 'sage_todos' && $type !== 'meta_entities' && $type !== 'animatics'): ?>
                    <option value="<?= $type ?>" <?= $type==='animas'?'selected':'' ?>><?= $icon . ' ' . ucfirst($type) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- 1. Map Runs Selection -->
    <div class="eh-top-panel">
        <div class="mr-controls-row">
            <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search Run..." oninput="debounceSearch()">
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
                <label class="chk-label" title="Hide frames already migrated">
                    <input type="checkbox" id="hideMigrated" onchange="applyGridFilters()">
                    Hide Migrated
                </label>
            </div>
            <div class="gt-actions" id="gridActions" style="display:none;">
                <button class="action-btn" onclick="toggleAll(false)">None</button>
                <button class="action-btn primary" onclick="toggleAll(true)">All</button>
            </div>
        </div>
    </div>

    <!-- 3. Frames Grid -->
    <div class="eh-grid-area" id="framesScroll">
        <div class="state-msg" id="gridState">
            <div>&#8593; Select a Map Run</div>
        </div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- Footer -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 selected</div>
        <button class="submit-btn" id="submitBtn" disabled onclick="submitMigration()">
            Migrate to Sketches
        </button>
    </div>
</div>

<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
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

function resetAndLoad() {
    curPage = 1;
    currentRunId = null;
    selectedFrameIds.clear();
    updateSummary();
    document.getElementById('framesGrid').style.display = 'none';
    document.getElementById('gridActions').style.display = 'none';
    document.getElementById('gridState').style.display = 'flex';
    document.getElementById('gridState').innerHTML = '<div>&#8593; Select a Map Run</div>';
    loadMapRuns(1);
}

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
    const type = document.getElementById('sourceType').value;
    
    if (page === 1) list.scrollTop = 0;
    
    fetch(`?api_action=get_map_runs&limit=20&offset=${(page-1)*20}&search=${encodeURIComponent(search)}&entity_type=${type}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            curPage = page;
            totalPages = Math.ceil(res.total / 20) || 1;
            
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
            document.getElementById('mrPrev').disabled = curPage <= 1;
            document.getElementById('mrNext').disabled = curPage >= totalPages;

            list.innerHTML = '';
            if (res.data.length === 0) {
                list.innerHTML = '<div class="state-msg">No runs found for ' + type + '.</div>';
                return;
            }
            res.data.forEach(run => {
                const el = document.createElement('div');
                el.className = `mr-item ${run.id == currentRunId ? 'active' : ''}`;
                el.onclick = () => selectRun(run.id, el);
                el.innerHTML = `
                    <div class="mr-id">#${run.id}</div>
                    <div class="mr-note">${esc(run.note || 'No note')}</div>
                    <div class="mr-meta">${run.frame_count} fr • ${run.created_at.substring(0,10)}</div>
                `;
                list.appendChild(el);
            });
        });
}

function selectRun(runId, el) {
    document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    currentRunId = runId;

    const grid = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    const info = document.getElementById('gridInfo');
    const type = document.getElementById('sourceType').value;
    
    state.style.display = 'flex';
    state.innerHTML = '<div class="spinner"></div><div>Loading frames...</div>';
    grid.style.display = 'none';
    document.getElementById('gridActions').style.display = 'none';

    fetch(`?api_action=get_frames&map_run_id=${runId}&entity_type=${type}`)
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
    const hideMigrated = document.getElementById('hideMigrated').checked;
    grid.innerHTML = '';
    
    currentFrames.forEach(f => {
        const isMigrated = parseInt(f.is_migrated) === 1;
        const card = document.createElement('div');
        card.className = 'f-card';
        if(isMigrated) card.classList.add('is-migrated');
        if(isMigrated && hideMigrated) card.classList.add('hidden-in-grid');
        
        card.dataset.fid = f.frame_id;
        card.dataset.isMigrated = isMigrated ? "1" : "0";
        
        const link = document.createElement('a');
        link.className = 'f-link';
        link.href = f.filename;
        link.target = '_blank';
        link.dataset.pswpWidth = 1024;
        link.dataset.pswpHeight = 1024;

        const img = document.createElement('img');
        img.src = f.filename;
        img.loading = "lazy";
        img.onload = function() {
            link.dataset.pswpWidth = this.naturalWidth;
            link.dataset.pswpHeight = this.naturalHeight;
        };
        link.appendChild(img);
        
        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = (e) => { e.preventDefault(); toggleFrame(f.frame_id, card); };
        const dispName = f.entity_name ? f.entity_name.substring(0, 15) : '#' + f.frame_id;
        label.innerHTML = `<span>${dispName}</span><div class="f-select-trigger"></div>`;
        
        card.appendChild(link);
        card.appendChild(label);
        grid.appendChild(card);
    });
}

function toggleFrame(fid, card) {
    if (selectedFrameIds.has(fid)) {
        selectedFrameIds.delete(fid);
        card.classList.remove('selected');
    } else {
        selectedFrameIds.add(fid);
        card.classList.add('selected');
    }
    updateSummary();
}

function toggleAll(select) {
    const cards = document.querySelectorAll('.f-card');
    selectedFrameIds.clear();
    cards.forEach(card => {
        if (select) {
            const fid = parseInt(card.dataset.fid);
            selectedFrameIds.add(fid);
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
    document.getElementById('submitBtn').disabled = count === 0;
}

function submitMigration() {
    const type = document.getElementById('sourceType').value;
    const btn = document.getElementById('submitBtn');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Migrating...';

    fetch('?api_action=submit_migration', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            frame_ids: Array.from(selectedFrameIds),
            entity_type: type
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Toast.show(`Migrated ${res.count} frames`);
            selectedFrameIds.clear();
            if(currentRunId) {
                fetch(`?api_action=get_frames&map_run_id=${currentRunId}&entity_type=${type}`)
                .then(r => r.json())
                .then(r2 => {
                    currentFrames = r2.data;
                    renderGrid();
                    updateSummary();
                });
            }
        } else {
            Toast.show(res.message, 'error');
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = orig;
    });
}

function esc(s) { return s ? s.toString().replace(/"/g, '&quot;') : ''; }
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>