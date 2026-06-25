<?php
/**
 * fuki.php
 * Fuki — Multilingual Text Overlay Editor
 * Combines sequence list browsing, sketch metadata deep-dives, and Konva free-floating text logic.
 */
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$seqId    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editLang = strtolower($_GET['lang'] ?? 'en');

// Helper to resolve frame thumbnails
function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = '';
    foreach (['thumb', 'thumbnail', 'image', 'image_url', 'image_path', 'file_path', 'path', 'src', 'url', 'filename', 'file_name'] as $key) {
        if (!empty($row[$key]) && is_string($row[$key])) {
            $candidate = $row[$key]; break;
        }
    }
    if ($candidate !== '') {
        if (strpos($candidate, 'http') !== 0 && strpos($candidate, 'view_frame.php') === false) {
            $parts = array_map('rawurlencode', explode('/', ltrim($candidate, '/')));
            return '/' . implode('/', $parts);
        }
        return $candidate;
    }
    return $frameId > 0 ? 'view_frame.php?frame_id=' . $frameId : '';
}

// ── 1. Empty State: Sequence List View ───────────────────────────────────────
if (!$seqId) {
    $seqs = $pdo->query("SELECT id, name, created_at, sequence_data FROM narrative_sequences ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    :root, [data-theme="dark"] {
        --fk-bg: #080a0f; --fk-surface: #111620; --fk-card: #151b28; --fk-border: #1b2333;
        --fk-text: #c8d4e8; --fk-text-dim: #5a6a80; --fk-accent: #f5a623;
    }
    [data-theme="light"] {
        --fk-bg: #f4f6fa; --fk-surface: #ffffff; --fk-card: #ffffff; --fk-border: #d0d8e8;
        --fk-text: #1a2233; --fk-text-dim: #7a8aaa; --fk-accent: #c8880a;
    }
    body { background: var(--fk-bg); color: var(--fk-text); font-family: 'Syne', sans-serif; }
    
    .list-wrap { max-width: 700px; margin: 60px auto; padding: 20px; }
    .list-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .list-title { font-family: 'Space Mono', monospace; color: var(--fk-accent); margin: 0; display:flex; align-items:center; gap:8px;}
    .list-item { display: flex; align-items: center; background: var(--fk-card); border: 1px solid var(--fk-border); border-radius: 6px; overflow: hidden; transition: border-color 0.2s; margin-bottom: 8px;}
    .list-item:hover { border-color: var(--fk-accent); }
    .list-link { display: flex; justify-content: space-between; align-items: center; flex: 1; padding: 12px 14px; text-decoration: none; color: var(--fk-text); font-family: 'Space Mono', monospace; font-size: 0.85rem; }
    </style>

    <div class="list-wrap">
        <div class="list-header">
            <h2 class="list-title"><i class="bi bi-chat-square-text"></i> Fuki Text Editor</h2>
        </div>
        <p style="color:var(--fk-text-dim);font-size:.85rem; margin-top:0;">Select a narrative sequence to add text overlays:</p>

        <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
        <?php foreach ($seqs as $s): ?>
            <div class="list-item">
                <a href="?id=<?= $s['id'] ?>" class="list-link">
                    <?php $sktCount = count(json_decode($s['sequence_data'] ?? '[]', true) ?: []); ?>
                    <span>
                        #<?= $s['id'] ?> 
                        <span style="color:var(--fk-accent);opacity:0.8;margin:0 6px;">[<?= $sktCount ?> skts]</span> 
                        <?= htmlspecialchars($s['name']) ?>
                    </span>
                    <span style="color:var(--fk-text-dim);"><?= date('Y-m-d', strtotime($s['created_at'])) ?></span>
                </a>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Sequence - Fuki Editor', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── 2. Active State: Sequence Editor View ────────────────────────────────────
$seqStmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
$seqStmt->execute([$seqId]);
$seq = $seqStmt->fetch(PDO::FETCH_ASSOC);
if (!$seq) die("<div style='padding:40px; color:red;'>Sequence #$seqId not found.</div>");

// Global System Languages Fetch
$allLanguages = [];
try {
    $allLanguages = $pdo->query("SELECT * FROM system_languages ORDER BY is_main DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
$pureSketchIds    = [];
$selectedFrameIds = [];

foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0) $pureSketchIds[] = $sid;
    $selectedFrameIds[$idx] = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : null;
}
$pureSketchIds = array_values(array_unique($pureSketchIds));

$sketchesData = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtS = $pdo->prepare("SELECT id, name, description FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }
}

$selectedFrameMap = [];
$activeFrameIds   = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT id, filename FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $selectedFrameMap[(int)$row['id']] = resolveFrameThumb($row, (int)$row['id']);
    }
}

$sketchIdsNeedingLatestFrame = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0 && empty($selectedFrameIds[$idx])) $sketchIdsNeedingLatestFrame[] = $sid;
}
$sketchIdsNeedingLatestFrame = array_values(array_unique($sketchIdsNeedingLatestFrame));

$latestFrameBySketch = [];
if (!empty($sketchIdsNeedingLatestFrame)) {
    $inClauseFb = implode(',', array_fill(0, count($sketchIdsNeedingLatestFrame), '?'));
    $stmtFb = $pdo->prepare("SELECT f.id, f.filename, f.entity_id AS _sketch_id FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id IN ($inClauseFb) ORDER BY f.id DESC");
    $stmtFb->execute($sketchIdsNeedingLatestFrame);
    foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchId = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sketchId])) {
            $latestFrameBySketch[$sketchId] = resolveFrameThumb($row, (int)$row['id']);
        }
    }
}

$pageTitle = "Fuki: " . htmlspecialchars($seq['name']);
ob_start();
?>
<!-- Pre-load Comic Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bangers&family=Permanent+Marker&family=Oswald:wght@600;700&family=Cinzel:wght@400;700&family=Space+Mono:wght@400;700&family=Lora:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
:root, [data-theme="dark"] {
    --fk-bg: #080a0f; --fk-surface: #111620; --fk-card: #151b28; --fk-border: #1b2333;
    --fk-text: #c8d4e8; --fk-text-dim: #5a6a80; --fk-accent: #f5a623;
}
[data-theme="light"] {
    --fk-bg: #f4f6fa; --fk-surface: #ffffff; --fk-card: #ffffff; --fk-border: #d0d8e8;
    --fk-text: #1a2233; --fk-text-dim: #7a8aaa; --fk-accent: #c8880a;
}

body { background: var(--fk-bg); color: var(--fk-text); font-family: 'DM Sans', sans-serif; margin: 0; padding: 0; padding-bottom: 100px; }

/* Sticky Header */
.fuki-nav { display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: rgba(8,10,15,0.85); border-bottom: 1px solid var(--fk-border); position: sticky; top: 0; z-index: 1000; backdrop-filter: blur(8px); }
[data-theme="light"] .fuki-nav { background: rgba(244,246,250,0.92); }
.fuki-nav-title { font-family: 'Space Mono', monospace; font-size: 0.85rem; font-weight: bold; color: var(--fk-accent); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 8px; }
.fuki-nav-title span.badge { background: var(--fk-accent); color: #000; font-size: 0.55rem; padding: 2px 6px; border-radius: 3px; letter-spacing: 1px; }

.lang-select { background: var(--fk-surface); color: var(--fk-text); border: 1px solid var(--fk-border); border-radius: 4px; padding: 6px; font-family: 'Space Mono', monospace; font-size: 0.7rem; outline: none; }
.fk-btn-back { color: var(--fk-text-dim); text-decoration: none; font-family: 'Space Mono', monospace; font-size: 0.7rem; padding: 6px 10px; border: 1px solid var(--fk-border); border-radius: 4px; transition: 0.2s; }
.fk-btn-back:hover { border-color: var(--fk-accent); color: var(--fk-accent); }

/* Workspace */
.fuki-workspace { max-width: 800px; margin: 0 auto; padding: 20px 15px; display: flex; flex-direction: column; gap: 40px; }
.frame-wrap { display: flex; flex-direction: column; gap: 15px; background: var(--fk-card); border: 1px solid var(--fk-border); border-radius: 8px; padding: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }

/* Rich Header (Metadata) */
.frame-header { display: flex; gap: 15px; align-items: center; }
.sketch-thumb { width: 80px; height: 80px; flex-shrink: 0; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--fk-border); cursor: pointer; transition: filter 0.15s; }
.sketch-thumb:hover { filter: brightness(1.2); }
.sketch-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sketch-thumb a { display: block; width: 100%; height: 100%; }

.sketch-info { flex: 1; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
.sketch-id { font-family: 'Space Mono', monospace; font-size: 0.65rem; color: var(--fk-accent); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 5px; }
.sketch-title { font-size: 1.05rem; font-weight: bold; margin: 0 0 5px 0; color: var(--fk-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; transition: color 0.15s; font-family: 'Syne', sans-serif;}
.sketch-title:hover { color: var(--fk-accent); }
.sketch-desc { font-size: 0.75rem; color: var(--fk-text-dim); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.add-txt-btn { background: var(--fk-surface); color: var(--fk-text); border: 1px solid var(--fk-border); padding: 8px 14px; border-radius: 4px; font-family: 'Space Mono', monospace; font-size: 0.7rem; font-weight: bold; cursor: pointer; transition: 0.15s; align-self: center; white-space: nowrap; }
.add-txt-btn:hover { border-color: var(--fk-accent); color: var(--fk-accent); }

/* Konva Hosts */
.konva-host-wrap { width: 100%; position: relative; background: var(--fk-surface); border: 1px solid var(--fk-border); border-radius: 4px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.konva-bg-img { display: none; } /* Used only to load natural bounds */
.konva-host { width: 100%; height: auto; touch-action: none; }

/* Floating Text Editor */
.fuki-textarea { position: absolute; z-index: 999; background: rgba(255,255,255,0.85); border: none; color: #000; padding: 0; margin: 0; outline: 2px solid var(--fk-accent); resize: none; overflow: hidden; transform-origin: left top; white-space: pre-wrap; word-wrap: break-word; }

/* Floating Toolbar */
.fuki-toolbar { position: fixed; bottom: 15px; left: 50%; transform: translateX(-50%); background: rgba(17,22,32,0.95); backdrop-filter: blur(10px); border: 1px solid var(--fk-border); border-radius: 30px; display: flex; gap: 6px; padding: 6px 12px; z-index: 2000; box-shadow: 0 10px 40px rgba(0,0,0,0.8); align-items: center; overflow-x: auto; max-width: 95vw; }
.tool-btn { width: 34px; height: 34px; border-radius: 50%; background: transparent; border: 1px solid transparent; color: var(--fk-text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: 0.15s; flex-shrink: 0; }
.tool-btn:hover { color: var(--fk-text); background: rgba(255,255,255,0.05); }
.tool-btn.active { color: var(--fk-accent); background: rgba(245,166,35,0.1); border-color: rgba(245,166,35,0.3); }
.tool-sep { width: 1px; height: 20px; background: var(--fk-border); margin: 0 4px; flex-shrink: 0; }
.tool-select { background: transparent; color: var(--fk-text); border: 1px solid transparent; font-size: 0.8rem; font-family: inherit; outline: none; cursor: pointer; border-radius: 15px; padding: 0 8px; height: 34px; transition: 0.15s; }
.tool-select:hover { border-color: var(--fk-border); background: rgba(255,255,255,0.05); }
.tool-color { -webkit-appearance: none; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; padding: 0; overflow: hidden; flex-shrink: 0; }
.tool-color::-webkit-color-swatch-wrapper { padding: 0; }
.tool-color::-webkit-color-swatch { border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; }

/* Hide global text selection while dragging in Konva */
.konvajs-content { user-select: none; -webkit-user-select: none; }

/* Modals */
.su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.su-modal-backdrop.active { display:flex; }
.su-modal-box { width:100%; max-width:700px; height:85vh; padding:0; display:flex; flex-direction:column; overflow:hidden; background:var(--fk-surface); border:1px solid var(--fk-border); border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
.su-modal-header { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid var(--fk-border); flex-shrink:0; }
.su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--fk-accent); text-transform:uppercase; letter-spacing:1px; }
.su-modal-close { background:transparent; border:none; color:var(--fk-text-dim); cursor:pointer; font-size:1.2rem; }

/* PhotoSwipe Overrides */
.pswp { z-index: 400000 !important; }
</style>

<div class="fuki-nav">
    <a href="fuki.php" class="fk-btn-back">&#9664; Sequences</a>
    <div class="fuki-nav-title">
        <span class="badge">FUKI</span> 
        <?= htmlspecialchars($seq['name']) ?>
    </div>
    <select class="lang-select" id="lang-select" onchange="window.location.href='?id=<?= $seqId ?>&lang='+this.value">
        <?php foreach ($allLanguages as $l): ?>
            <option value="<?= $l['code'] ?>" <?= $l['code'] === $editLang ? 'selected' : '' ?>><?= strtoupper($l['code']) ?> — <?= htmlspecialchars($l['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="fuki-workspace editor-pswp-gallery" id="workspace">
    <?php foreach ($itemIds as $idx => $item):
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid <= 0 || !isset($sketchesData[$sid])) continue;

        $sketchRow = $sketchesData[$sid];
        $activeFrameId = $selectedFrameIds[$idx] ?? null;
        $imgUrl = '';
        if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
            $imgUrl = $selectedFrameMap[$activeFrameId];
        } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
            $imgUrl = $latestFrameBySketch[$sid];
        }
        if (!$imgUrl) continue;
    ?>
    <div class="frame-wrap">
        <div class="frame-header">
            <div class="sketch-thumb">
                <a href="<?= htmlspecialchars($imgUrl) ?>" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                    <img src="<?= htmlspecialchars($imgUrl) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                </a>
            </div>
            <div class="sketch-info">
                <div class="sketch-id">Item <?= sprintf('%02d', $idx + 1) ?> &bull; Sketch #<?= $sid ?></div>
                <div class="sketch-title" onclick="openEntityModal('sketches', <?= $sid ?>, '<?= htmlspecialchars(addslashes($sketchRow['name'])) ?>')">
                    <?= htmlspecialchars($sketchRow['name']) ?>
                </div>
                <div class="sketch-desc"><?= htmlspecialchars($sketchRow['description'] ?? 'No description.') ?></div>
            </div>
            <button class="add-txt-btn" onclick="addFukiText(<?= $sid ?>)">+ Add Text</button>
        </div>
        <div class="konva-host-wrap">
            <img src="<?= htmlspecialchars($imgUrl) ?>" class="konva-bg-img" id="img-<?= $sid ?>" crossorigin="anonymous">
            <div class="konva-host" id="stage-<?= $sid ?>" data-sketch-id="<?= $sid ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Floating Toolbar -->
<div class="fuki-toolbar" id="fuki-toolbar" style="display:none;">
    <select id="t-font" class="tool-select">
        <option value="Bangers">Bangers</option>
        <option value="Permanent Marker">Marker</option>
        <option value="Oswald">Oswald</option>
        <option value="Cinzel">Cinzel</option>
        <option value="Lora">Lora</option>
        <option value="Space Mono">Mono</option>
        <option value="Arial">Arial</option>
    </select>
    <div class="tool-sep"></div>
    <select id="t-size" class="tool-select" style="width:50px;">
        <option value="12">12</option><option value="16">16</option><option value="20">20</option>
        <option value="24">24</option><option value="28">28</option><option value="36">36</option>
        <option value="48">48</option><option value="64">64</option><option value="72">72</option>
    </select>
    <div class="tool-sep"></div>
    <input type="color" id="t-color" class="tool-color">
    <div class="tool-sep"></div>
    <button class="tool-btn" id="t-al-l"><i class="bi bi-text-left"></i></button>
    <button class="tool-btn" id="t-al-c"><i class="bi bi-text-center"></i></button>
    <button class="tool-btn" id="t-al-r"><i class="bi bi-text-right"></i></button>
    <div class="tool-sep"></div>
    <button class="tool-btn" id="t-bold"><i class="bi bi-type-bold"></i></button>
    <button class="tool-btn" id="t-italic"><i class="bi bi-type-italic"></i></button>
    <button class="tool-btn" id="t-underline"><i class="bi bi-type-underline"></i></button>
    <div class="tool-sep"></div>
    <button class="tool-btn" id="t-delete" style="color:var(--red);"><i class="bi bi-trash"></i></button>
</div>

<!-- Entity Details Modal (Iframe) -->
<div class="su-modal-backdrop" id="entity-modal-backdrop" onmousedown="if(event.target===this)closeEntityModal()">
    <div class="su-modal-box">
        <div class="su-modal-header">
            <span class="su-modal-title" id="entityModalTitle">Entity Details</span>
            <button class="su-modal-close" onclick="closeEntityModal()">✕</button>
        </div>
        <iframe id="entity-iframe" src="about:blank" style="flex:1; border:none; width:100%; background:var(--fk-surface);"></iframe>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script src="https://unpkg.com/konva@9.3.3/konva.min.js"></script>

<!-- PhotoSwipe Lightbox Module -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    
    window.initLightbox = () => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.editor-pswp-gallery',
            children: '.editor-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (window.initLightbox) window.initLightbox();
    });
</script>

<script>
'use strict';

const SEQ_ID   = <?= $seqId ?>;
const CUR_LANG = '<?= $editLang ?>';
const stages   = {};    // sketch_id -> { stage, layer, tr, background }
let activeNode = null;  // globally selected Konva.Text
let currentTextarea = null;

// ── 0. Deep Dive Modal ───────────────────────────────────────────────────────
window.openEntityModal = function(entityType, entityId, label) {
    const url = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
    document.getElementById('entity-iframe').src = url;
    document.getElementById('entityModalTitle').textContent = label + ' — ' + entityType;
    document.getElementById('entity-modal-backdrop').classList.add('active');
}
window.closeEntityModal = function() {
    document.getElementById('entity-modal-backdrop').classList.remove('active');
    document.getElementById('entity-iframe').src = 'about:blank';
}

// ── 1. Init Stages ───────────────────────────────────────────────────────────
document.querySelectorAll('.konva-host').forEach(host => {
    const sid = host.dataset.sketchId;
    const imgEl = document.getElementById('img-' + sid);
    
    // Wait for image to load to get natural bounds
    if (imgEl.complete) { initStage(sid, host, imgEl); } 
    else { imgEl.onload = () => initStage(sid, host, imgEl); }
});

function initStage(sid, host, imgEl) {
    const natW = imgEl.naturalWidth;
    const natH = imgEl.naturalHeight;
    const wrap = host.parentElement;
    const dispW = wrap.clientWidth;
    const scale = dispW / natW;
    const dispH = natH * scale;
    
    const stage = new Konva.Stage({ container: host.id, width: dispW, height: dispH });
    const bgLayer = new Konva.Layer();
    const layer = new Konva.Layer();
    stage.add(bgLayer, layer);

    // Background Image
    const kImg = new Konva.Image({ image: imgEl, width: natW, height: natH, listening: false });
    bgLayer.add(kImg);
    
    // Stage scaling — so internal coords are natural image coords
    bgLayer.scale({x: scale, y: scale});
    layer.scale({x: scale, y: scale});
    
    // Transformer
    const tr = new Konva.Transformer({
        nodes: [],
        enabledAnchors: ['middle-left', 'middle-right'],
        boundBoxFunc: (oldBox, newBox) => {
            if (newBox.width < 50) return oldBox;
            return newBox;
        }
    });
    layer.add(tr);
    
    stages[sid] = { stage, layer, bgLayer, tr, scale, natW, natH };

    // Deselect click
    stage.on('click tap', e => {
        if (e.target === stage || e.target === kImg) deselectAll();
    });
}

// ── 2. Load Elements from API ───────────────────────────────────────────────
window.addEventListener('load', () => {
    // Slight delay to ensure all images loaded and stages sized
    setTimeout(loadFukiElements, 300);
});

async function loadFukiElements() {
    const r = await fetch(`fuki_api.php?action=load_elements&sequence_id=${SEQ_ID}&lang=${CUR_LANG}`);
    const res = await r.json();
    if (!res.success) return;

    res.elements.forEach(el => {
        const sid = el.sketch_id;
        if (stages[sid]) createKonvaText(sid, el);
    });
}

// ── 3. Text Creation & Interaction ──────────────────────────────────────────
window.addFukiText = function(sid) {
    const sData = stages[sid];
    if (!sData) return;

    const el = {
        element_uid: 'fuki_' + Math.random().toString(36).substring(2, 10),
        text_content: 'Double tap to edit',
        x: sData.natW / 2 - 100, y: sData.natH / 2 - 30,
        width: 200, rotation: 0,
        font_family: 'Bangers', font_size: 32, fill_color: '#111111',
        text_align: 'center', is_bold: 0, is_italic: 0, is_underline: 0
    };
    
    const node = createKonvaText(sid, el);
    selectNode(node, sid);
    saveElement(node, sid);
    
    // Automatically open the editor for new blocks
    setTimeout(() => openInlineEditor(node, sid), 50);
};

function createKonvaText(sid, el) {
    const layer = stages[sid].layer;
    const textNode = new Konva.Text({
        id: el.element_uid,
        x: parseFloat(el.x), y: parseFloat(el.y),
        width: parseFloat(el.width), rotation: parseFloat(el.rotation),
        text: el.text_content || 'Double tap to edit',
        fontFamily: el.font_family,
        fontSize: parseFloat(el.font_size),
        fill: el.fill_color,
        align: el.text_align,
        fontStyle: (el.is_bold == 1 ? 'bold ' : '') + (el.is_italic == 1 ? 'italic' : ''),
        textDecoration: el.is_underline == 1 ? 'underline' : '',
        draggable: true,
        // Custom attrs for syncing
        _isBold: el.is_bold == 1,
        _isItalic: el.is_italic == 1,
        _isUnderline: el.is_underline == 1
    });

    textNode.on('transform', () => {
        // Enforce width-only scaling for text wrap
        textNode.setAttrs({
            width: Math.max(textNode.width() * textNode.scaleX(), 50),
            scaleX: 1, scaleY: 1
        });
    });

    textNode.on('dragend transformend', () => saveElement(textNode, sid));
    
    textNode.on('click tap', () => selectNode(textNode, sid));
    
    textNode.on('dblclick dbltap', () => openInlineEditor(textNode, sid));

    layer.add(textNode);
    layer.batchDraw();
    return textNode;
}

// ── 4. Selection & Toolbar ──────────────────────────────────────────────────
function deselectAll() {
    Object.values(stages).forEach(s => {
        s.tr.nodes([]);
        s.layer.batchDraw();
    });
    activeNode = null;
    document.getElementById('fuki-toolbar').style.display = 'none';
    if (currentTextarea) { currentTextarea.blur(); }
}

function selectNode(node, sid) {
    deselectAll();
    activeNode = { node, sid };
    const sData = stages[sid];
    sData.tr.nodes([node]);
    sData.layer.batchDraw();
    updateToolbarState();
    document.getElementById('fuki-toolbar').style.display = 'flex';
}

const tb = {
    font: document.getElementById('t-font'),
    size: document.getElementById('t-size'),
    color: document.getElementById('t-color'),
    alL: document.getElementById('t-al-l'),
    alC: document.getElementById('t-al-c'),
    alR: document.getElementById('t-al-r'),
    bold: document.getElementById('t-bold'),
    italic: document.getElementById('t-italic'),
    under: document.getElementById('t-underline'),
    del: document.getElementById('t-delete')
};

function updateToolbarState() {
    if (!activeNode) return;
    const n = activeNode.node;
    tb.font.value = n.fontFamily();
    
    // Ensure size dropdown shows exact value or closest
    let sMatch = Array.from(tb.size.options).find(o => o.value == n.fontSize());
    if(!sMatch) {
        const opt = document.createElement('option');
        opt.value = n.fontSize(); opt.text = n.fontSize();
        tb.size.appendChild(opt);
    }
    tb.size.value = n.fontSize();
    
    tb.color.value = n.fill();
    
    [tb.alL, tb.alC, tb.alR].forEach(b => b.classList.remove('active'));
    if (n.align() === 'left') tb.alL.classList.add('active');
    else if (n.align() === 'right') tb.alR.classList.add('active');
    else tb.alC.classList.add('active');

    tb.bold.classList.toggle('active', n.getAttr('_isBold'));
    tb.italic.classList.toggle('active', n.getAttr('_isItalic'));
    tb.under.classList.toggle('active', n.getAttr('_isUnderline'));
}

// Toolbar bindings
tb.font.onchange = e => { if(activeNode) { activeNode.node.fontFamily(e.target.value); syncToolChange(); } };
tb.size.onchange = e => { if(activeNode) { activeNode.node.fontSize(parseInt(e.target.value)); syncToolChange(); } };
tb.color.oninput = e => { if(activeNode) { activeNode.node.fill(e.target.value); syncToolChange(); } };

tb.alL.onclick = () => setAlign('left');
tb.alC.onclick = () => setAlign('center');
tb.alR.onclick = () => setAlign('right');
function setAlign(a) { if(!activeNode) return; activeNode.node.align(a); updateToolbarState(); syncToolChange(); }

tb.bold.onclick = () => { if(!activeNode) return; activeNode.node.setAttr('_isBold', !activeNode.node.getAttr('_isBold')); updateFontStyle(); };
tb.italic.onclick = () => { if(!activeNode) return; activeNode.node.setAttr('_isItalic', !activeNode.node.getAttr('_isItalic')); updateFontStyle(); };
tb.under.onclick = () => { if(!activeNode) return; activeNode.node.setAttr('_isUnderline', !activeNode.node.getAttr('_isUnderline')); activeNode.node.textDecoration(activeNode.node.getAttr('_isUnderline') ? 'underline' : ''); syncToolChange(); };

function updateFontStyle() {
    const n = activeNode.node;
    n.fontStyle((n.getAttr('_isBold') ? 'bold ' : '') + (n.getAttr('_isItalic') ? 'italic' : ''));
    syncToolChange();
}

function syncToolChange() {
    updateToolbarState();
    stages[activeNode.sid].layer.batchDraw();
    saveElement(activeNode.node, activeNode.sid);
}

tb.del.onclick = () => {
    if (!activeNode) return;
    if (!confirm('Delete this text? This removes it for ALL languages.')) return;
    const uid = activeNode.node.id();
    activeNode.node.destroy();
    deselectAll();
    
    const fd = new URLSearchParams();
    fd.append('action', 'delete_element');
    fd.append('element_uid', uid);
    fetch('fuki_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(!res.success) Toast.show('Delete failed', 'error');
    });
};

// ── 5. Inline Text Editing ──────────────────────────────────────────────────
function openInlineEditor(textNode, sid) {
    if (currentTextarea) return;
    
    textNode.hide();
    stages[sid].tr.hide();
    stages[sid].layer.draw();

    const sData = stages[sid];
    const textPosition = textNode.absolutePosition();
    const stageBox = sData.stage.container().getBoundingClientRect();

    const areaPosition = {
        x: stageBox.left + textPosition.x,
        y: stageBox.top + textPosition.y
    };

    const textarea = document.createElement('textarea');
    document.body.appendChild(textarea);
    currentTextarea = textarea;

    textarea.value = textNode.text();
    textarea.className = 'fuki-textarea';
    
    // Apply styling to perfectly overlay Konva
    const pxScale = sData.scale; // Screen scale
    textarea.style.top = areaPosition.y + 'px';
    textarea.style.left = areaPosition.x + 'px';
    textarea.style.width = (textNode.width() * pxScale) + 'px';
    textarea.style.height = (textNode.height() * pxScale + 20) + 'px';
    textarea.style.fontSize = (textNode.fontSize() * pxScale) + 'px';
    
    textarea.style.lineHeight = textNode.lineHeight();
    textarea.style.fontFamily = textNode.fontFamily();
    textarea.style.textAlign = textNode.align();
    textarea.style.fontStyle = textNode.getAttr('_isItalic') ? 'italic' : 'normal';
    textarea.style.fontWeight = textNode.getAttr('_isBold') ? 'bold' : 'normal';
    
    const rotation = textNode.rotation();
    textarea.style.transform = `rotateZ(${rotation}deg)`;

    textarea.focus();

    // Auto resize height
    textarea.addEventListener('keydown', function () {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    });

    textarea.addEventListener('blur', function () {
        textNode.text(textarea.value);
        textNode.show();
        stages[sid].tr.show();
        sData.layer.draw();
        saveElement(textNode, sid);
        textarea.remove();
        currentTextarea = null;
    });
}

// ── 6. Save Engine ──────────────────────────────────────────────────────────
let saveTimer = null;
function saveElement(node, sid) {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        const fd = new URLSearchParams();
        fd.append('action', 'save_element');
        fd.append('sequence_id', SEQ_ID);
        fd.append('sketch_id', sid);
        fd.append('element_uid', node.id());
        fd.append('lang', CUR_LANG);
        
        fd.append('text_content', node.text());
        fd.append('x', node.x());
        fd.append('y', node.y());
        fd.append('width', node.width());
        fd.append('rotation', node.rotation());
        
        fd.append('font_family', node.fontFamily());
        fd.append('font_size', node.fontSize());
        fd.append('fill_color', node.fill());
        fd.append('text_align', node.align());
        
        fd.append('is_bold', node.getAttr('_isBold') ? 1 : 0);
        fd.append('is_italic', node.getAttr('_isItalic') ? 1 : 0);
        fd.append('is_underline', node.getAttr('_isUnderline') ? 1 : 0);

        fetch('fuki_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
            if(!res.success) Toast.show('Save failed', 'error');
        });
    }, 400); // 400ms debounce
}

</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>