<?php
// public/view_scrollmagic_narseq.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\GearMenuModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- Direct ZIP Download Action ---
if (isset($_GET['action']) && $_GET['action'] === 'download_zip') {
    $file = basename($_GET['file'] ?? '');
    $tempDir = sys_get_temp_dir();
    $filePath = $tempDir . '/' . $file;

    if ($file !== '' && file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        @unlink($filePath);
        exit;
    }
    die('ZIP file not found or expired.');
}

// --- GET Params ---
$sequence_id = filter_input(INPUT_GET, 'sequence_id', FILTER_VALIDATE_INT);
if (!$sequence_id) {
    $sequence_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}
if (!$sequence_id) {
    $stmtLatest = $pdo->query("SELECT id FROM narrative_sequences ORDER BY id DESC LIMIT 1");
    $sequence_id = (int)$stmtLatest->fetchColumn();
}
if (!$sequence_id) {
    die("No Narrative Sequences found.");
}

// Fetch sequence row
$stmtSeq = $pdo->prepare("SELECT id, name, description, sequence_data FROM narrative_sequences WHERE id = :id");
$stmtSeq->execute(['id' => $sequence_id]);
$seq = $stmtSeq->fetch(PDO::FETCH_ASSOC);

if (!$seq) {
    die("Narrative Sequence #{$sequence_id} not found.");
}

$pageTitle = "Narrative Sequence: " . htmlspecialchars($seq['name']);

/**
 * AJAX action: set regenerate_images = 1 for all sketches in this sequence.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'regenerate_sequence_sketches')) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $rawItems = json_decode($seq['sequence_data'] ?: '[]', true) ?: [];

        $sketchIds = [];
        foreach ($rawItems as $item) {
            $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            if ($sid > 0) $sketchIds[] = $sid;
        }
        $sketchIds = array_values(array_unique($sketchIds));

        if (empty($sketchIds)) {
            echo json_encode([
                'success' => true,
                'message' => 'No sketches found in this narrative sequence.',
                'sequence_id' => $sequence_id,
                'matched_entities' => 0,
                'updated_rows' => 0,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($sketchIds), '?'));
        $sql = "UPDATE `sketches` SET regenerate_images = 1 WHERE id IN ({$placeholders})";
        $stmtUpd = $pdo->prepare($sql);
        $stmtUpd->execute($sketchIds);
        $updatedRows = $stmtUpd->rowCount();

        echo json_encode([
            'success' => true,
            'message' => 'regenerate_images set to 1 for sequence sketches.',
            'sequence_id' => $sequence_id,
            'matched_entities' => count($sketchIds),
            'updated_rows' => $updatedRows,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update sequence sketches.',
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * AJAX action: export sequence frames as a ZIP archive.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'export_zip')) {
    header('Content-Type: application/json; charset=utf-8');

    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'message' => 'ZipArchive PHP extension is not available.']);
        exit;
    }

    try {
        $rawItems = json_decode($seq['sequence_data'] ?: '[]', true) ?: [];

        if (empty($rawItems)) {
            echo json_encode(['success' => false, 'message' => 'Sequence has no items to export.']);
            exit;
        }

        $batchSketchIds = [];
        $batchFrameIds  = [];
        foreach ($rawItems as $item) {
            $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            if ($sid > 0) $batchSketchIds[] = $sid;
            if (is_array($item) && !empty($item['frame_id'])) {
                $batchFrameIds[] = (int)$item['frame_id'];
            }
        }
        $batchSketchIds = array_values(array_unique($batchSketchIds));
        $batchFrameIds  = array_values(array_unique(array_filter($batchFrameIds)));

        // Fetch explicit frames
        $framesMap = [];
        if (!empty($batchFrameIds)) {
            $inFrames = implode(',', array_fill(0, count($batchFrameIds), '?'));
            $stmtFr = $pdo->prepare("SELECT id, filename, name FROM `frames` WHERE id IN ($inFrames)");
            $stmtFr->execute($batchFrameIds);
            foreach ($stmtFr->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $framesMap[(int)$f['id']] = $f;
            }
        }

        // Fetch fallback frames for sketches missing an explicit frame
        $sketchIdsNeedingFrames = [];
        foreach ($rawItems as $item) {
            $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            $fid = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : 0;
            if ($sid > 0 && (!$fid || !isset($framesMap[$fid]))) {
                $sketchIdsNeedingFrames[] = $sid;
            }
        }
        $sketchIdsNeedingFrames = array_values(array_unique($sketchIdsNeedingFrames));

        $fallbackFramesBySketch = [];
        if (!empty($sketchIdsNeedingFrames)) {
            $inNeed = implode(',', array_fill(0, count($sketchIdsNeedingFrames), '?'));
            $stmtFb = $pdo->prepare("
                SELECT id, filename, name, entity_id AS sketch_id
                FROM frames
                WHERE entity_type = 'sketches' AND entity_id IN ($inNeed)
                UNION
                SELECT f.id, f.filename, f.name, f2s.to_id AS sketch_id
                FROM frames f
                JOIN frames_2_sketches f2s ON f2s.from_id = f.id
                WHERE f2s.to_id IN ($inNeed)
                ORDER BY id DESC
            ");
            $stmtFb->execute(array_merge($sketchIdsNeedingFrames, $sketchIdsNeedingFrames));
            foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $fr) {
                $sid = (int)$fr['sketch_id'];
                if (!isset($fallbackFramesBySketch[$sid])) {
                    $fallbackFramesBySketch[$sid] = $fr;
                }
            }
        }

        $zipFilename = 'sequence_' . $sequence_id . '_' . date('Ymd_His') . '.zip';
        $tempZipPath = sys_get_temp_dir() . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            echo json_encode(['success' => false, 'message' => 'Failed to create ZIP file on server.']);
            exit;
        }

        $addedCount = 0;
        foreach ($rawItems as $idx => $item) {
            $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            $fid = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : 0;

            $frame = null;
            if ($fid && isset($framesMap[$fid])) {
                $frame = $framesMap[$fid];
            } elseif ($sid && isset($fallbackFramesBySketch[$sid])) {
                $frame = $fallbackFramesBySketch[$sid];
            }

            if (!$frame) continue;

            $filename = $frame['filename'] ?? '';
            if ($filename === '') continue;

            // Resolve physical file path on disk
            $cleanRel = ltrim($filename, '/');
            $candidates = [
                $projectPath . '/' . $cleanRel,
                $publicPathAbs . '/' . $cleanRel,
                $framesDir . '/' . basename($cleanRel),
                $projectPath . '/public/' . $cleanRel,
            ];

            $foundPath = null;
            foreach ($candidates as $cand) {
                if (file_exists($cand) && !is_dir($cand)) {
                    $foundPath = $cand;
                    break;
                }
            }

            if ($foundPath) {
                $ext = pathinfo($foundPath, PATHINFO_EXTENSION) ?: 'jpg';
                $entryName = sprintf('%03d_sketch_%d_frame_%d.%s', $idx + 1, $sid, (int)$frame['id'], $ext);
                $zip->addFile($foundPath, $entryName);
                $addedCount++;
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($tempZipPath);
            echo json_encode(['success' => false, 'message' => 'No image files could be located on disk to zip.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'download_url' => 'view_scrollmagic_narseq.php?action=download_zip&file=' . urlencode($zipFilename),
            'count' => $addedCount
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating ZIP: ' . $e->getMessage()]);
    }
    exit;
}

// --- Navigation Logic (Narrative Sequences, ID Descending) ---
$stmtPrev = $pdo->prepare("SELECT id FROM narrative_sequences WHERE id > :id ORDER BY id ASC LIMIT 1");
$stmtPrev->execute(['id' => $sequence_id]);
$prevSeqId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare("SELECT id FROM narrative_sequences WHERE id < :id ORDER BY id DESC LIMIT 1");
$stmtNext->execute(['id' => $sequence_id]);
$nextSeqId = $stmtNext->fetchColumn();

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
    body { background: #0f0f0f; color: #ddd; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; overflow-anchor: auto; }

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
    .topbar-title-text { max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: #3ab5c8; font-weight: bold; }
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
    .frames-column { width: 100%; max-width: 1000px; margin: 0 auto; padding-bottom: 100px; overflow-anchor: auto; }

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
    .frame:hover .caption-overlay, .frame.show-caption .caption-overlay { opacity: 1; }

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
        touch-action: none;
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
        .topbar { padding: 8px 10px; gap: 6px; }
        .topbar-brand { font-size: 13px; }
        .topbar-title-text { max-width: 140px; }
        .topbar-controls { width: 100%; justify-content: space-between; gap: 4px; }
        .nav-btn { padding: 4px 8px; font-size: 12px; height: 28px; }
        .topbar-input { font-size: 12px; padding: 3px 6px; }
        .view-container { padding-top: 85px; }
        .auto-scroll-player, .asc-restore-btn { bottom: 10px; right: 10px; }
    }

    .sb-menu { position: absolute !important; }
</style>

<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>
<script src="/js/gear_menu_globals.js"></script>

<div class="topbar" id="main-topbar">
    <div class="topbar-brand" title="Narrative Sequence">
        <i class="bi bi-film me-2"></i>
        <span class="topbar-title-text"><?= htmlspecialchars($seq['name']) ?></span>
    </div>

    <div class="topbar-controls">
        <a href="<?= $prevSeqId ? '?id='.$prevSeqId : '#' ?>"
           class="nav-btn <?= !$prevSeqId ? 'disabled' : '' ?>"
           title="Previous sequence">
            <i class="bi bi-chevron-left"></i>
        </a>

        <input
            id="seq-input"
            class="topbar-input"
            type="number"
            min="1"
            value="<?= (int)$sequence_id ?>"
            title="Load sequence by ID"
            style="width:75px; text-align:center;"
        >

        <a href="<?= $nextSeqId ? '?id='.$nextSeqId : '#' ?>"
           class="nav-btn <?= !$nextSeqId ? 'disabled' : '' ?>"
           title="Next sequence">
            <i class="bi bi-chevron-right"></i>
        </a>

        <div style="width:1px; height:20px; background:#444; margin:0 2px;"></div>

        <a href="narseq.php?id=<?= (int)$sequence_id ?>" class="nav-btn" title="Open in Sequencer Editor">
            <i class="bi bi-pencil-square"></i> Edit
        </a>

        <button id="btn-regenerate-entities" class="nav-btn" style="cursor:pointer;" title="Set regenerate_images=1 for all sketches in this sequence">
            <i class="bi bi-arrow-repeat"></i> Regen
        </button>

        <button id="btn-export-zip" class="nav-btn" style="cursor:pointer;" title="Download all sequence frames as ZIP">
            <i class="bi bi-download"></i> ZIP
        </button>

        <div style="width:1px; height:20px; background:#444; margin:0 2px;"></div>

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
         data-api-url="/scrollmagic_images_narseq.php"
         data-sequence-id="<?= htmlspecialchars($sequence_id) ?>">
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
        seqInput: document.getElementById('seq-input'),
    };

    const cfgEl = document.getElementById('gallery-config');
    const API_CONFIG = {
        url: cfgEl.dataset.apiUrl,
        sequenceId: cfgEl.dataset.sequenceId
    };

    const getStorageKey = () => `sage_narseq_pos_${API_CONFIG.sequenceId}`;
    const UI_CONFIG = { batch: 20, preload: 10, threshold: 10 };
    
    let fetchSessionId = 0;
    
    const state = { 
        topOffset: 0, 
        displayOffset: 0, 
        total: null, 
        observer: null, 
        sentinelObserver: null, 
        isLoadingNext: false, 
        isLoadingPrev: false, 
        allLoaded: false 
    };

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
        const frag = document.createDocumentFragment();
        if (!window.GearMenu || typeof window.GearMenu.attach !== 'function') { 
            console.warn("GearMenu not loaded yet.");
            newDivs.forEach(div => frag.appendChild(div));
            return frag; 
        }

        const dummy = document.createElement('div');
        newDivs.forEach(div => dummy.appendChild(div));

        if (window.jQuery) window.GearMenu.attach(window.jQuery(dummy)); 
        else window.GearMenu.attach(dummy);

        while (dummy.firstChild) frag.appendChild(dummy.firstChild);
        return frag;
    }

    function handleJump(e){ if (e.type==='keydown' && e.key!=='Enter') return; let val=parseInt(els.jumpInput.value); if(isNaN(val)||val<1)return; els.jumpInput.blur(); jumpTo(val-1); }
    els.jumpInput.addEventListener('change', handleJump);
    els.jumpInput.addEventListener('keydown', handleJump);

    function jumpTo(index) {
        fetchSessionId++;
        els.frames.innerHTML = '';
        state.topOffset = index;
        state.displayOffset = index;
        state.allLoaded = false;
        state.isLoadingNext = false;
        state.isLoadingPrev = false;
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
            const globalIdx = (item.sequence_idx !== undefined) ? item.sequence_idx : (startIdx + i);
            const div = document.createElement('div');
            div.className = 'frame';
            div.dataset.idx = globalIdx;
            div.dataset.frameId = item.id;
            div.dataset.entity = item.entity || 'sketches';
            div.dataset.entityId = item.entity_id || item.sketch_id || 0;

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

            div.addEventListener('click', (e) => {
                if (e.target.closest('.gear-icon') || e.target.closest('.sb-menu')) return;
                div.classList.toggle('show-caption');
            });

            state.observer.observe(div);
            divs.push(div);
        });
        return divs;
    }

    async function fetchApi(offset, limit) {
        const p = new URLSearchParams({ 
            offset: offset, 
            batch_size: limit,
            sequence_id: API_CONFIG.sequenceId
        });

        const res = await fetch(`${API_CONFIG.url}?${p.toString()}`);
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
            if(e[0].isIntersecting && state.topOffset > 0 && !state.isLoadingPrev) fetchPreviousBatch();
        }, {rootMargin:'200px 0px 0px 0px'});
        state.sentinelObserver.observe(els.sentinel);
    }

    function updateStatus(idx){ 
        els.status.textContent = `${idx+1} / ${state.total||'?'}`; 
        localStorage.setItem(getStorageKey(), idx);
    }

    function manageMemory(centerIdx){
        const min=centerIdx-UI_CONFIG.preload, max=centerIdx+UI_CONFIG.preload;
        const kids=els.frames.children;
        for(let i=0; i<kids.length; i++){
            const div=kids[i], idx=parseInt(div.dataset.idx);
            if(Math.abs(idx-centerIdx)<100){ (idx>=min && idx<=max) ? loadImage(div) : unloadImage(div); }
        }
        if(els.frames.lastElementChild){
            const lastIdx=parseInt(els.frames.lastElementChild.dataset.idx);
            if(centerIdx >= lastIdx - UI_CONFIG.threshold && !state.allLoaded) fetchNextBatch();
        }
    }

    async function fetchPreviousBatch(){
        if(state.isLoadingPrev) return;
        const target = Math.max(0, state.topOffset - UI_CONFIG.batch);
        const limit = state.topOffset - target;
        if(limit <= 0) return;
        
        state.isLoadingPrev = true;
        const sessionId = fetchSessionId;
        
        try {
            await waitForGlobals();
            const d = await fetchApi(target, limit);
            if(sessionId !== fetchSessionId) return;

            if(d.images && d.images.length){
                const f = els.frames.firstElementChild;
                const oldTop = f ? f.getBoundingClientRect().top : 0;
                
                const divs = createDivs(d.images, target);
                els.frames.prepend(attachGearMenuSafely(divs));
                
                if(f) {
                    const diff = f.getBoundingClientRect().top - oldTop;
                    if (diff !== 0) window.scrollBy(0, diff);
                }
                divs.forEach(loadImage);
            }
            state.topOffset = target;
        } catch(e){ 
            console.warn(e); 
        } finally { 
            if(sessionId === fetchSessionId) state.isLoadingPrev = false; 
        }
    }

    async function fetchNextBatch(){
        if(state.isLoadingNext || state.allLoaded) return;
        state.isLoadingNext = true;
        els.loader.style.display = 'block';
        const sessionId = fetchSessionId;
        
        try {
            await waitForGlobals();
            const d = await fetchApi(state.displayOffset, UI_CONFIG.batch);
            if(sessionId !== fetchSessionId) return;
            
            state.total = d.total;
            const processed = (d.processed !== undefined) ? d.processed : UI_CONFIG.batch;
            
            if(d.images && d.images.length) {
                const divs = createDivs(d.images, state.displayOffset);
                els.frames.appendChild(attachGearMenuSafely(divs));
                divs.slice(0,5).forEach(loadImage);
            }
            
            state.displayOffset += processed;
            
            if(state.displayOffset >= state.total) {
                state.allLoaded = true;
            }
        } catch(e){
            console.error(e);
            els.status.textContent='Err';
        } finally {
            if(sessionId === fetchSessionId) {
                state.isLoadingNext = false;
                els.loader.style.display = 'none';
            }
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

        player.addEventListener('pointerdown', (e) => {
            if(e.target.closest('button') || e.target.closest('input')) return;
            isDragging = true;
            player.style.bottom = 'auto';
            player.style.right = 'auto';
            const rect = player.getBoundingClientRect();
            player.style.left = rect.left + 'px';
            player.style.top = rect.top + 'px';
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            player.setPointerCapture?.(e.pointerId);
            e.preventDefault(); 
        });

        window.addEventListener('pointermove', (e) => {
            if(!isDragging) return;
            player.style.left = (e.clientX - dragOffset.x) + 'px';
            player.style.top = (e.clientY - dragOffset.y) + 'px';
        });

        window.addEventListener('pointerup', () => {
            if(isDragging) { isDragging = false; }
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

            const ok = window.confirm('Set regenerate_images=1 for all sketches in this sequence?');
            if (!ok) return;

            const originalHtml = btnRegenerate.innerHTML;
            btnRegenerate.innerHTML = '<i class="bi bi-hourglass-split"></i> Working...';
            btnRegenerate.classList.add('disabled');

            try {
                const formData = new FormData();
                formData.append('action', 'regenerate_sequence_sketches');

                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const json = await res.json();

                if (json.success) {
                    alert(`Done.\n\nMatched sketches: ${json.matched_entities || 0}\nUpdated rows: ${json.updated_rows || 0}`);
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
            if (btnZip.classList.contains('disabled')) return;

            const originalHtml = btnZip.innerHTML;
            btnZip.innerHTML = '<i class="bi bi-hourglass-split"></i> Zipping...';
            btnZip.classList.add('disabled');

            try {
                const formData = new FormData();
                formData.append('action', 'export_zip');

                const res = await fetch(window.location.href, {
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

    if (els.seqInput) {
        const goToSeq = () => {
            const val = parseInt(els.seqInput.value, 10);
            if (!isNaN(val) && val > 0) {
                window.location.href = '?id=' + encodeURIComponent(val);
            } else {
                els.seqInput.value = API_CONFIG.sequenceId;
            }
        };

        els.seqInput.addEventListener('change', goToSeq);
        els.seqInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') goToSeq();
        });
    }

    const saved = parseInt(localStorage.getItem(getStorageKey()), 10);
    if (!isNaN(saved) && saved > 0) {
        els.jumpInput.value = saved + 1;
        jumpTo(saved);
    } else {
        els.jumpInput.value = 1;
        fetchNextBatch();
    }

})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/scrollmagic.php');
?>