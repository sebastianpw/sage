<?php
// public/view_scrollmagic_map_run.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\GearMenuModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Sage Map Run Viewer";

// --- GET Params ---
$map_run_id = filter_input(INPUT_GET, 'map_run_id', FILTER_VALIDATE_INT);
if (!$map_run_id) {
    die("Map Run ID is required.");
}

/**
 * AJAX action: set regenerate_images = 1 for all mapped entities in this map run.
 * This stays in this file to keep the viewer self-contained.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'regenerate_mapped_entities')) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT f.entity_type, f.entity_id
            FROM frames f
            WHERE f.map_run_id = :map_run_id
              AND f.entity_type IS NOT NULL
              AND f.entity_type <> ''
              AND f.entity_id IS NOT NULL
              AND f.entity_id > 0
            ORDER BY f.entity_type ASC, f.entity_id ASC
        ");
        $stmt->execute(['map_run_id' => $map_run_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            echo json_encode([
                'success' => true,
                'message' => 'No mapped entities found for this map run.',
                'map_run_id' => $map_run_id,
                'matched_entities' => 0,
                'updated_rows' => 0,
                'skipped_tables' => [],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $grouped = [];
        foreach ($rows as $row) {
            $entityType = (string)($row['entity_type'] ?? '');
            $entityId = (int)($row['entity_id'] ?? 0);

            if ($entityType === '' || $entityId <= 0) {
                continue;
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $entityType)) {
                continue;
            }

            $grouped[$entityType][] = $entityId;
        }

        $updatedRows = 0;
        $skippedTables = [];
        $processedTables = [];

        foreach ($grouped as $tableName => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $ids = array_filter($ids, static fn($v) => $v > 0);

            if (!$ids) {
                continue;
            }

            $stmtCol = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = 'regenerate_images'
            ");
            $stmtCol->execute(['table_name' => $tableName]);
            $hasColumn = (int)$stmtCol->fetchColumn() > 0;

            if (!$hasColumn) {
                $skippedTables[] = $tableName;
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE `{$tableName}` SET regenerate_images = 1 WHERE id IN ({$placeholders})";
            $stmtUpd = $pdo->prepare($sql);
            $stmtUpd->execute($ids);
            $updatedRows += $stmtUpd->rowCount();
            $processedTables[] = $tableName;
        }

        echo json_encode([
            'success' => true,
            'message' => 'regenerate_images set to 1 for mapped entities.',
            'map_run_id' => $map_run_id,
            'matched_entities' => count($rows),
            'updated_rows' => $updatedRows,
            'processed_tables' => array_values(array_unique($processedTables)),
            'skipped_tables' => array_values(array_unique($skippedTables)),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update mapped entities.',
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// --- Navigation Logic (Sketches specific, ID Descending) ---
$stmtPrev = $pdo->prepare("SELECT id FROM map_runs WHERE entity_type = 'sketches' AND id > :id ORDER BY id ASC LIMIT 1");
$stmtPrev->execute(['id' => $map_run_id]);
$prevRunId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare("SELECT id FROM map_runs WHERE entity_type = 'sketches' AND id < :id ORDER BY id DESC LIMIT 1");
$stmtNext->execute(['id' => $map_run_id]);
$nextRunId = $stmtNext->fetchColumn();

// Initialize Modules
$gearMenu = new GearMenuModule(['position' => 'manual', 'icon' => '&#9881;', 'icon_size' => '1.5em']);
require __DIR__ . '/entity_icons.php';

foreach (array_keys($entityIcons) as $entity) {
    $gearMenu->addStandardActions($entity, [
        'overrides' => [
            'edit_image' => [
                 'callback' => 'const $w = $(wrapper); ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find(\'img.main-img\').attr(\'data-src\') || $w.find(\'img.main-img\').attr(\'src\') });'
            ]
        ]
    ]);
}

$imageEditor = new ImageEditorModule(['modes'=>['mask','crop'],'enable_resize'=>true]);

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

ob_start();
?>

<!-- Deps -->
<link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    body { background: #0f0f0f; color: #ddd; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; overflow-anchor: none; }

    .topbar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
        background: rgba(16, 16, 16, 0.96);
        border-bottom: 1px solid #333;
        backdrop-filter: blur(8px);
        padding: 8px 16px;
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
        min-height: 50px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }

    .topbar-brand { font-weight: 600; color: #eee; white-space: nowrap; margin-right: 5px; display: flex; align-items: center; }
    .topbar-controls { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; flex: 1; justify-content: flex-end; }

    .topbar-input { background: #222; border: 1px solid #444; color: #eee; padding: 4px 8px; border-radius: 4px; font-size: 13px; }
    .topbar-input:focus { outline: none; border-color: #0cf; background: #2a2a2a; }

    .nav-btn {
        background: #222; border: 1px solid #444; color: #aaa;
        padding: 4px 10px; border-radius: 4px; font-size: 13px;
        text-decoration: none; display: flex; align-items: center; gap: 6px;
        transition: all 0.2s; height: 30px;
    }
    .nav-btn:hover:not(.disabled) { background: #333; border-color: #666; color: #fff; }
    .nav-btn.disabled { opacity: 0.3; pointer-events: none; border-color: #333; }

    .view-container { max-width: 1200px; margin: 0 auto; padding: 10px; min-height: 100vh; padding-top: 80px; transition: padding-top 0.2s; }
    .frames-column { width: 100%; max-width: 1000px; margin: 0 auto; padding-bottom: 100px; }

    .frame { display: block; position: relative; background: #181818; border-radius: 6px; margin-bottom: 20px; min-height: 200px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
    .frame img.main-img { width: 100%; height: auto; display: block; opacity: 0; transition: opacity 0.3s; border-radius: 6px; position: relative; z-index: 2; }
    .frame.loaded img.main-img { opacity: 1; }
    .placeholder { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #181818; border-radius: 6px; z-index: 1; }
    .frame.loaded .placeholder { display: none !important; }
    .sage-spinner { width: 24px; height: 24px; border: 2px solid rgba(255,255,255,0.1); border-top-color: #888; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 8px; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .gear-icon { position: absolute; top: 8px; right: 8px; z-index: 50; color: #fff; background: rgba(0,0,0,0.6); padding: 5px 8px; border-radius: 4px; cursor: pointer; opacity: 0.6; transition: 0.2s; }
    .frame:hover .gear-icon { opacity: 1; background: rgba(0,0,0,0.8); }

    .caption-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.95), transparent); padding: 20px 10px 10px; color: #ccc; font-size: 12px; pointer-events: none; border-radius: 0 0 6px 6px; opacity: 0; transition: opacity 0.2s; z-index: 3; }
    .frame:hover .caption-overlay { opacity: 1; }

    .auto-scroll-player {
        position: fixed; bottom: 15px; right: 15px; z-index: 10000;
        background: rgba(0, 0, 0, 0.96);
        padding: 5px;
        border-radius: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        width: 140px;
        display: flex; flex-direction: column; gap: 4px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.9);
        cursor: grab;
        user-select: none;
        overflow: hidden;
    }
    .auto-scroll-player:active { cursor: grabbing; }

    .asc-row { display: flex; gap: 2px; justify-content: space-between; width: 100%; }

    .asc-btn {
        background: transparent; border: 1px solid rgba(255,255,255,0.1);
        color: #999;
        padding: 4px 0; border-radius: 3px; cursor: pointer;
        font-size: 10px;
        flex: 1; text-align: center; transition: all 0.1s;
    }
    .asc-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }
    .asc-btn.active {
        background: rgba(255, 255, 255, 0.25);
        color: #fff;
        border-color: rgba(255,255,255,0.4);
    }

    .asc-slider-row { width: 100%; display: flex; align-items: center; padding: 4px 2px 2px 2px; box-sizing: border-box; }
    .asc-slider {
        width: 96%; margin: 0 auto; height: 4px;
        accent-color: #666; cursor: pointer; opacity: 0.8;
        display: block;
    }
    .asc-slider:hover { opacity: 1; accent-color: #ccc; }

    .asc-restore-btn {
        position: fixed; bottom: 15px; right: 15px; z-index: 9999;
        width: 28px; height: 28px;
        background: rgba(0, 0, 0, 0.7);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 50%;
        color: #aaa;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        backdrop-filter: blur(4px);
        font-size: 14px;
    }
    .asc-restore-btn:hover {
        background: rgba(0, 0, 0, 0.95);
        color: #fff;
        transform: scale(1.1);
        box-shadow: 0 0 8px rgba(0,0,0,0.8);
        border-color: rgba(255,255,255,0.3);
    }

    @media (max-width: 768px) {
        .topbar { padding: 10px; gap: 6px; }
        .topbar-controls { width: 100%; justify-content: space-between; }
        .view-container { padding-top: 80px; }
        .auto-scroll-player, .asc-restore-btn { bottom: 10px; right: 10px; }
    }

    .sb-menu { position: absolute !important; }
</style>

<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>
<script src="/js/gear_menu_globals.js"></script>

<div class="topbar" id="main-topbar">
    <div class="topbar-brand" title="Map Run">
        <i class="bi bi-collection-play me-2"></i>
    </div>

    <div class="topbar-controls">
        <a href="<?= $prevRunId ? '?map_run_id='.$prevRunId : '#' ?>"
           class="nav-btn <?= !$prevRunId ? 'disabled' : '' ?>"
           title="Previous map run">
            <i class="bi bi-chevron-left"></i>
        </a>

        <input
            id="map-run-input"
            class="topbar-input"
            type="number"
            min="1"
            value="<?= (int)$map_run_id ?>"
            title="Load map run by ID"
            style="width:90px; text-align:center;"
        >

        <a href="<?= $nextRunId ? '?map_run_id='.$nextRunId : '#' ?>"
           class="nav-btn <?= !$nextRunId ? 'disabled' : '' ?>"
           title="Next map run">
            <i class="bi bi-chevron-right"></i>
        </a>
        
        <div style="width:1px; height:20px; background:#444; margin:0 5px;"></div>
        
        <button id="btn-regenerate-entities" class="nav-btn" style="cursor:pointer;" title="Set regenerate_images=1 for all entities mapped to frames">
            <i class="bi bi-arrow-repeat"></i> Regen
        </button>

        <button id="btn-export-zip" class="nav-btn" style="cursor: pointer;" title="Download all frames as ZIP">
            <i class="bi bi-download"></i> ZIP
        </button>

        <div style="width:1px; height:20px; background:#444; margin:0 5px;"></div>

        <div style="display:flex; align-items:center; gap:5px;">
             <input id="jump-input" class="topbar-input" type="number" min="1" placeholder="#" style="width:50px; text-align:center;">
             <div id="status" style="font-size:11px; color:#888; font-family:monospace; white-space:nowrap;">...</div>
        </div>
    </div>
</div>

<div class="view-container" id="view-container">
    <div id="top-sentinel"></div>
    <div id="frames" class="frames-column"></div>
    
    <div id="loader" style="text-align:center; padding:30px; display:none; color:#666;">
        <div class="sage-spinner" style="margin:0 auto;"></div>
        <small>Loading Frames...</small>
    </div>

    <div id="autoScrollControls" class="auto-scroll-player" style="display:none;">
        <div class="asc-row">
            <button id="asc-start" class="asc-btn" title="Start"><i class="bi bi-play-fill"></i></button>
            <button id="asc-pause" class="asc-btn active" title="Pause"><i class="bi bi-pause-fill"></i></button>
            <button id="asc-top" class="asc-btn" title="Top"><i class="bi bi-arrow-up"></i></button>
            <button id="asc-close" class="asc-btn" title="Close" style="flex:0; min-width:20px;">&times;</button>
        </div>
        <div class="asc-slider-row">
            <input type="range" id="asc-speed" class="asc-slider" min="1" max="100" value="50" title="Speed">
        </div>
    </div>
    
    <div id="asc-restore" class="asc-restore-btn" style="display:none;" title="Show Player">
        <i class="bi bi-play-circle"></i>
    </div>

    <div id="gallery-config" style="display:none"
         data-api-url="/scrollmagic_images_map_run.php"
         data-map-run-id="<?= htmlspecialchars($map_run_id) ?>">
    </div>
</div>

<script>
(function(){
    const header = document.getElementById('main-topbar');
    const container = document.getElementById('view-container');
    const resizeObs = new ResizeObserver(entries => {
        for (let entry of entries) {
            container.style.paddingTop = (entry.contentRect.height + 20) + 'px';
        }
    });
    resizeObs.observe(header);

    const els = {
        frames: document.getElementById('frames'),
        sentinel: document.getElementById('top-sentinel'),
        status: document.getElementById('status'),
        loader: document.getElementById('loader'),
        jumpInput: document.getElementById('jump-input'),
        mapRunInput: document.getElementById('map-run-input'),
    };

    const cfgEl = document.getElementById('gallery-config');
    const API_CONFIG = {
        url: cfgEl.dataset.apiUrl,
        mapRunId: cfgEl.dataset.mapRunId
    };

    const UI_CONFIG = { batch: 20, preload: 4, threshold: 8 };
    const state = { topOffset:0, displayOffset:0, total:null, observer:null, sentinelObserver:null, isLoading:false, allLoaded:false, abortCtrl:null };
    
    async function waitForGlobals() {
        if(window.GearMenu && typeof window.GearMenu.attach === 'function') return;
        return new Promise(resolve => {
            const i = setInterval(() => {
                if(window.GearMenu && typeof window.GearMenu.attach === 'function') {
                    clearInterval(i);
                    resolve();
                }
            }, 100);
            setTimeout(() => { clearInterval(i); resolve(); }, 5000);
        });
    }

    function attachGearMenuSafely(newDivs) {
        if (!window.GearMenu || typeof window.GearMenu.attach !== 'function') { 
            console.warn("GearMenu not loaded yet.");
            return document.createDocumentFragment(); 
        }

        const dummy = document.createElement('div');
        newDivs.forEach(div => dummy.appendChild(div));
        
        if (window.jQuery) window.GearMenu.attach(window.jQuery(dummy)); 
        else window.GearMenu.attach(dummy);
        
        const frag = document.createDocumentFragment();
        while (dummy.firstChild) frag.appendChild(dummy.firstChild);
        return frag;
    }

    function handleJump(e){ if (e.type==='keydown' && e.key!=='Enter') return; let val=parseInt(els.jumpInput.value); if(isNaN(val)||val<1)return; els.jumpInput.blur(); jumpTo(val-1); }
    els.jumpInput.addEventListener('change', handleJump);
    els.jumpInput.addEventListener('keydown', handleJump);

    function jumpTo(index) {
        if (state.abortCtrl) state.abortCtrl.abort();
        els.frames.innerHTML = '';
        state.topOffset = index;
        state.displayOffset = index;
        state.allLoaded = false;
        state.isLoading = false;
        fetchNextBatch();
    }

    function lockHeight(div,img){ if(!div.style.height){ const h=img.getBoundingClientRect().height; if(h>50){ div.style.height=h+'px'; div.style.minHeight=h+'px'; } } }
    
    function loadImage(div){ 
        const img = div.querySelector('img.main-img'); 
        if(!img || div.classList.contains('loaded')) return; 
        const onLoaded = () => { requestAnimationFrame(() => { lockHeight(div, img); div.classList.add('loaded'); }); };
        if(img.complete && img.naturalHeight > 0) { onLoaded(); } else {
            img.onload = onLoaded;
            img.onerror = () => { const info = div.querySelector('.placeholder small'); if(info) info.innerText = 'Error'; div.querySelector('.sage-spinner')?.remove(); };
            if(img.getAttribute('src') !== img.dataset.src) img.src = img.dataset.src;
        }
    }
    
    function unloadImage(div){ if(!div.style.height) return; const img=div.querySelector('img.main-img'); if(img && img.src){ img.removeAttribute('src'); div.classList.remove('loaded'); } }

    function createDivs(items, startIdx) {
        const divs = [];
        items.forEach((item,i)=>{
            const globalIdx = startIdx + i;
            const div = document.createElement('div');
            div.className = 'frame';
            div.dataset.idx = globalIdx;
            div.dataset.frameId = item.id;
            div.dataset.entity = item.entity || 'frames';
            div.dataset.entityId = item.entity_id || 0;

            div.innerHTML = `
                <div class="placeholder">
                    <div class="sage-spinner"></div>
                    <small style="color:#666">#${globalIdx+1}</small>
                </div>
                <img class="main-img" data-src="${item.url}" loading="lazy" alt="frame">
                <div class="caption-overlay">
                    <span style="opacity:0.7; margin-right:5px">#${globalIdx+1}</span> ${item.caption || ''}
                </div>
                <span class="gear-icon">&#9881;</span>
            `;
            state.observer.observe(div);
            divs.push(div);
        });
        return divs;
    }

    async function fetchApi(offset, limit) {
        state.abortCtrl = new AbortController();
        const p = new URLSearchParams({ 
            offset: offset, 
            batch_size: limit,
            map_run_id: API_CONFIG.mapRunId
        });

        const res = await fetch(`${API_CONFIG.url}?${p.toString()}`, { signal: state.abortCtrl.signal });
        const json = await res.json();
        if(json.error) throw new Error(json.error);
        return json;
    }

    function initObserver(){
        state.observer = new IntersectionObserver(entries=>{
            entries.forEach(e=>{
                if(e.isIntersecting){
                    const idx = parseInt(e.target.dataset.idx);
                    updateStatus(idx);
                    manageMemory(idx);
                }
            });
        }, {rootMargin:'800px 0px'});

        state.sentinelObserver = new IntersectionObserver(e=>{
            if(e[0].isIntersecting && state.topOffset>0 && !state.isLoading) fetchPreviousBatch();
        }, {rootMargin:'200px 0px 0px 0px'});
        state.sentinelObserver.observe(els.sentinel);
    }
    
    function updateStatus(idx){ els.status.textContent = `${idx+1} / ${state.total||'?'}`; }
    
    function manageMemory(centerIdx){
        const min=centerIdx-UI_CONFIG.preload, max=centerIdx+UI_CONFIG.preload;
        const kids=els.frames.children;
        for(let i=0; i<kids.length; i++){
            const div=kids[i], idx=parseInt(div.dataset.idx);
            if(Math.abs(idx-centerIdx)<50){ (idx>=min && idx<=max) ? loadImage(div) : unloadImage(div); }
        }
        if(els.frames.lastElementChild){
            const lastIdx=parseInt(els.frames.lastElementChild.dataset.idx);
            if(centerIdx >= lastIdx - UI_CONFIG.threshold && !state.allLoaded) fetchNextBatch();
        }
    }

    async function fetchPreviousBatch(){
        state.isLoading=true;
        const target = Math.max(0, state.topOffset - UI_CONFIG.batch);
        const limit = state.topOffset - target;
        if(limit<=0) { state.isLoading=false; return; }
        try {
            await waitForGlobals();
            const d = await fetchApi(target, limit);
            if(d.images.length){
                const f = els.frames.firstElementChild;
                const oldTop = f ? f.getBoundingClientRect().top : 0;
                const divs = createDivs(d.images, target);
                els.frames.prepend(attachGearMenuSafely(divs));
                state.topOffset = target;
                if(f) window.scrollBy(0, f.getBoundingClientRect().top - oldTop);
                divs.forEach(loadImage);
            }
        } catch(e){ console.warn(e); } finally { state.isLoading=false; }
    }

    async function fetchNextBatch(){
        if(state.isLoading || state.allLoaded) return;
        state.isLoading=true;
        els.loader.style.display='block';
        try {
            await waitForGlobals();
            const d = await fetchApi(state.displayOffset, UI_CONFIG.batch);
            state.total = d.total;
            if(d.images.length) {
                const divs = createDivs(d.images, state.displayOffset);
                els.frames.appendChild(attachGearMenuSafely(divs));
                state.displayOffset += d.images.length;
                if(state.displayOffset >= state.total) state.allLoaded=true;
                if(state.displayOffset === d.images.length) divs.slice(0,5).forEach(loadImage);
            } else { state.allLoaded=true; }
        } catch(e){
            if(e.name !== 'AbortError') {
                console.error(e);
                els.status.textContent='Err';
            }
        } finally {
            state.isLoading=false;
            els.loader.style.display='none';
            state.abortCtrl=null;
        }
    }

    function initAutoScroll() {
        const player = document.getElementById('autoScrollControls');
        const startBtn = document.getElementById('asc-start');
        const pauseBtn = document.getElementById('asc-pause');
        const topBtn = document.getElementById('asc-top');
        const closeBtn = document.getElementById('asc-close');
        const restoreBtn = document.getElementById('asc-restore');
        const speedInput = document.getElementById('asc-speed');
        
        setTimeout(() => player.style.display = 'flex', 1000);

        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        
        player.addEventListener('mousedown', (e) => {
            if(e.target.closest('button') || e.target.closest('input')) return;
            isDragging = true;
            player.style.bottom = 'auto';
            player.style.right = 'auto';
            const rect = player.getBoundingClientRect();
            player.style.left = rect.left + 'px';
            player.style.top = rect.top + 'px';
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            player.style.cursor = 'grabbing';
            e.preventDefault(); 
        });
        
        window.addEventListener('mousemove', (e) => {
            if(!isDragging) return;
            player.style.left = (e.clientX - dragOffset.x) + 'px';
            player.style.top = (e.clientY - dragOffset.y) + 'px';
        });
        
        window.addEventListener('mouseup', () => {
            if(isDragging) { isDragging = false; player.style.cursor = 'grab'; }
        });

        let isPlaying = false;
        let accumulator = 0;

        function animate() {
            if (!isPlaying) return;
            const val = parseInt(speedInput.value);
            const moveAmt = Math.pow(val, 2.2) / 1400; 
            accumulator += moveAmt;
            if (accumulator >= 1) {
                const move = Math.floor(accumulator);
                window.scrollBy({ top: move, behavior: 'instant' });
                accumulator -= move;
            }
            requestAnimationFrame(animate);
        }

        function play() { if(isPlaying) return; isPlaying = true; startBtn.classList.add('active'); pauseBtn.classList.remove('active'); animate(); }
        function pause() { isPlaying = false; startBtn.classList.remove('active'); pauseBtn.classList.add('active'); }

        startBtn.onclick = play;
        pauseBtn.onclick = pause;
        topBtn.onclick = () => { pause(); window.scrollTo({ top: 0, behavior: 'instant' }); };
        closeBtn.onclick = () => { player.style.display = 'none'; restoreBtn.style.display = 'flex'; };
        restoreBtn.onclick = () => { restoreBtn.style.display = 'none'; player.style.display = 'flex'; };

        window.addEventListener('keydown', (e) => {
            if(e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if(e.code === 'Space') { e.preventDefault(); isPlaying ? pause() : play(); }
        });
    }

    initObserver();
    initAutoScroll();
    
    const btnRegenerate = document.getElementById('btn-regenerate-entities');
    if (btnRegenerate) {
        btnRegenerate.addEventListener('click', async (e) => {
            e.preventDefault();

            const ok = window.confirm('Set regenerate_images=1 for all entities mapped to frames in this map run?');
            if (!ok) return;

            const originalHtml = btnRegenerate.innerHTML;
            btnRegenerate.innerHTML = '<i class="bi bi-hourglass-split"></i> Working...';
            btnRegenerate.classList.add('disabled');

            try {
                const formData = new FormData();
                formData.append('action', 'regenerate_mapped_entities');

                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const json = await res.json();

                if (json.success) {
                    alert(`Done.\n\nMatched entities: ${json.matched_entities || 0}\nUpdated rows: ${json.updated_rows || 0}`);
                } else {
                    alert('Update failed: ' + (json.message || 'Unknown error'));
                }
            } catch (err) {
                console.error(err);
                alert('Update failed: Server error');
            } finally {
                btnRegenerate.innerHTML = originalHtml;
                btnRegenerate.classList.remove('disabled');
            }
        });
    }

    const btnZip = document.getElementById('btn-export-zip');
    if (btnZip) {
        btnZip.addEventListener('click', async (e) => {
            e.preventDefault();
            if(btnZip.classList.contains('disabled')) return;
            
            const originalHtml = btnZip.innerHTML;
            btnZip.innerHTML = '<i class="bi bi-hourglass-split"></i> Zipping...';
            btnZip.classList.add('disabled');
            
            try {
                const formData = new FormData();
                formData.append('action', 'export_zip');
                formData.append('map_run_id', API_CONFIG.mapRunId);
                
                const res = await fetch('api_map_runs.php', {
                    method: 'POST',
                    body: formData
                });
                
                const json = await res.json();
                
                if (json.success && json.download_url) {
                    btnZip.innerHTML = '<i class="bi bi-check-lg"></i> Done';
                    window.location.href = json.download_url;
                    
                    setTimeout(() => {
                        btnZip.innerHTML = originalHtml;
                        btnZip.classList.remove('disabled');
                    }, 2000);
                } else {
                    alert('Export failed: ' + (json.message || 'Unknown error'));
                    btnZip.innerHTML = originalHtml;
                    btnZip.classList.remove('disabled');
                }
            } catch (err) {
                console.error(err);
                alert('Export failed: Server error');
                btnZip.innerHTML = originalHtml;
                btnZip.classList.remove('disabled');
            }
        });
    }

    if (els.mapRunInput) {
        const goToMapRun = () => {
            const val = parseInt(els.mapRunInput.value, 10);
            if (!isNaN(val) && val > 0) {
                window.location.href = '?map_run_id=' + encodeURIComponent(val);
            } else {
                els.mapRunInput.value = API_CONFIG.mapRunId;
            }
        };

        els.mapRunInput.addEventListener('change', goToMapRun);
        els.mapRunInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') goToMapRun();
        });
    }

    els.jumpInput.value = 1;
    fetchNextBatch();

})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/scrollmagic.php');
?>