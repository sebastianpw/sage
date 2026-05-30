<?php
// public/animejseq.php
// Showrunner - The Split-Cinematic Gallery V8
// A breathtaking, non-overlapping sequence viewer using Anime.js
// Features: JSON Export, ZIP Standalone Export, Sequence Navigation, Smart Text Truncation, Frame Detail Iframe Modal.

// ==============================================================================
// CONFIGURATION
// Enable to show the ZIP Download button for standalone packaging.
define('ADMIN_MODE', true);
// ==============================================================================

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

/**
 * Return the first non-empty value from a row by trying multiple keys.
 */
function firstExistingValue(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
            return $row[$key];
        }
    }
    return $default;
}

/**
 * Decode a JSON string if needed.
 */
function maybeDecodeJson($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
    return $value;
}

/**
 * Try to resolve a displayable image URL/path for a frame row without assuming a thumb column exists.
 */
function resolveFrameThumb(array $row, int $frameId = 0): string
{
    $candidate = firstExistingValue($row, [
        'thumb',
        'thumbnail',
        'image',
        'image_url',
        'image_path',
        'file_path',
        'path',
        'src',
        'url',
        'filename',
        'file_name',
    ], '');

    if (is_string($candidate) && $candidate !== '') {
        return $candidate;
    }

    if ($frameId > 0) {
        return 'view_frame.php?frame_id=' . $frameId;
    }

    return '';
}

/**
 * Build the UI curation structure expected by the viewer.
 */
function buildCuration(?array $analysis): array
{
    $analysis = $analysis ?: [];

    return [
        'class' => maybeDecodeJson($analysis['classification'] ?? []) ?: [],
        'themes' => maybeDecodeJson($analysis['thematics'] ?? []) ?: [],
        'entities' => maybeDecodeJson($analysis['entities'] ?? []) ?: [],
        'scoring' => maybeDecodeJson($analysis['scoring'] ?? []) ?: [],
        'recs' => maybeDecodeJson($analysis['recommendations'] ?? []) ?: [],
    ];
}

// 1. Fetch the sequence
$seqId = $_GET['id'] ?? null;
$table = 'narrative_sequences';

$seq = null;

if ($seqId) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$seqId]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $seq = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$seq) {
    die("<div style='background:#050508; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Sequence not found.</div>");
}

// Find Previous and Next Sequence IDs
$prevSeqId = null;
$nextSeqId = null;
if ($seq) {
    $stmtPrev = $pdo->prepare("SELECT id FROM $table WHERE id < ? ORDER BY id DESC LIMIT 1");
    $stmtPrev->execute([$seq['id']]);
    $prevSeqId = $stmtPrev->fetchColumn();

    $stmtNext = $pdo->prepare("SELECT id FROM $table WHERE id > ? ORDER BY id ASC LIMIT 1");
    $stmtNext->execute([$seq['id']]);
    $nextSeqId = $stmtNext->fetchColumn();
}

// 2. Build frames directly from the saved sequence_data
$itemIds = json_decode($seq['sequence_data'] ?? '[]', true);
if (!is_array($itemIds)) {
    $itemIds = [];
}

$pureSketchIds = [];
$selectedFrameIds = [];

foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0) {
        $pureSketchIds[] = $sid;
    }

    $selectedFrameIds[$idx] = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : null;
}

$pureSketchIds = array_values(array_unique($pureSketchIds));

// Load sketches
$sketchesData = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtS = $pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }
}

// Load analyses
$analysisData = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtA = $pdo->prepare("SELECT * FROM sketch_analysis WHERE sketch_id IN ($inClause)");
    $stmtA->execute($pureSketchIds);
    foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $row) {
        foreach (['entities', 'classification', 'scoring', 'thematics', 'recommendations'] as $jsonCol) {
            if (isset($row[$jsonCol]) && is_string($row[$jsonCol])) {
                $row[$jsonCol] = json_decode($row[$jsonCol], true) ?: $row[$jsonCol];
            }
        }
        $analysisData[(int)$row['sketch_id']] = $row;
    }
}

// Load selected frames directly, without assuming any thumb column exists
$selectedFrameMap = [];
$activeFrameIds = array_values(array_unique(array_filter($selectedFrameIds)));

if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fid = (int)$row['id'];
        $selectedFrameMap[$fid] = [
            'row' => $row,
            'thumb' => resolveFrameThumb($row, $fid),
        ];
    }
}

// For sketch IDs that have no explicit frame_id, resolve the latest frame per sketch
// so that entity-only sequences (plain integer arrays) also display images.
$sketchIdsNeedingLatestFrame = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0 && empty($selectedFrameIds[$idx])) {
        $sketchIdsNeedingLatestFrame[] = $sid;
    }
}
$sketchIdsNeedingLatestFrame = array_values(array_unique($sketchIdsNeedingLatestFrame));

// Map of sketch_id => latest frame row (for fallback display)
$latestFrameBySketch = [];
if (!empty($sketchIdsNeedingLatestFrame)) {
    // frames_2_sketches maps frames to sketches; entity_type='sketches' by convention.
    // We want the latest frame per sketch ordered by frame id desc.
    $inClauseFb = implode(',', array_fill(0, count($sketchIdsNeedingLatestFrame), '?'));
    $stmtFb = $pdo->prepare(
        "SELECT f.*, f.entity_id AS _sketch_id
         FROM frames f
         INNER JOIN frames_2_sketches m ON m.from_id = f.id
         WHERE f.entity_id IN ($inClauseFb)
         ORDER BY f.id DESC"
    );
    $stmtFb->execute($sketchIdsNeedingLatestFrame);
    foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchId = (int)$row['_sketch_id'];
        // Keep only the first (latest) frame encountered per sketch
        if (!isset($latestFrameBySketch[$sketchId])) {
            $fid = (int)$row['id'];
            $latestFrameBySketch[$sketchId] = [
                'frame_id' => $fid,
                'row' => $row,
                'thumb' => resolveFrameThumb($row, $fid),
            ];
        }
    }
}

// Build viewer frames in the exact order of sequence_data
$frames = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid <= 0 || !isset($sketchesData[$sid])) {
        continue;
    }

    $sketchRow = $sketchesData[$sid];
    $activeFrameId = $selectedFrameIds[$idx] ?? null;

    $activeThumb = '';
    if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
        // Explicit frame_id present and loaded — use it directly
        $activeThumb = $selectedFrameMap[$activeFrameId]['thumb'];
    } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
        // No explicit frame_id — fall back to latest frame for this sketch
        $activeThumb = $latestFrameBySketch[$sid]['thumb'];
        $activeFrameId = $latestFrameBySketch[$sid]['frame_id'];
    }

    $frameNode = [
        'id' => $sid,
        'name' => firstExistingValue($sketchRow, ['name', 'title'], 'Untitled Sketch'),
        'desc' => firstExistingValue($sketchRow, ['description', 'desc', 'prompt', 'text'], ''),
        'thumb' => $activeThumb,
        '_active_frame_id' => $activeFrameId,
        'analysis' => $analysisData[$sid] ?? null,
        'curation' => buildCuration($analysisData[$sid] ?? null),
    ];

    $frames[] = $frameNode;
}

// ==============================================================================
// ZIP EXPORT LOGIC SETUP
// ==============================================================================
$isExport = defined('ADMIN_MODE') && ADMIN_MODE && isset($_GET['export']);
$frameDir = 'frames_' . $seq['id'];
$imagesToDownload = []; // Map of local filename => original URL

if ($isExport) {
    foreach ($frames as &$frame) {
        $origUrl = $frame['thumb'];
        $filename = basename($origUrl);
        $frame['thumb'] = $frameDir . '/' . $filename;
        $imagesToDownload[$filename] = $origUrl;
    }
    unset($frame);
}

// Sanitize for JS UI
$sequenceJson = json_encode([
    'id' => $seq['id'],
    'name' => $seq['name'],
    'description' => $seq['description'],
    'frames' => $frames
]);

// 3. Build the Raw Export Object (Full DB Rows)
$rawSketches = [];
if (!empty($pureSketchIds)) {
    foreach ($itemIds as $item) {
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid > 0 && isset($sketchesData[$sid])) {
            $sketchNode = $sketchesData[$sid];
            if (is_array($item) && isset($item['frame_id'])) {
                $sketchNode['_active_frame_id'] = $item['frame_id'];
            }
            $sketchNode['analysis'] = $analysisData[$sid] ?? null;
            $rawSketches[] = $sketchNode;
        }
    }
}

if (isset($seq['sequence_data']) && is_string($seq['sequence_data'])) {
    $seq['sequence_data'] = json_decode($seq['sequence_data'], true) ?: $seq['sequence_data'];
}
if (isset($seq['generation_log']) && is_string($seq['generation_log'])) {
    $seq['generation_log'] = json_decode($seq['generation_log'], true) ?: $seq['generation_log'];
}

$exportObject = [
    'sequence' => $seq,
    'sketches' => $rawSketches
];
$exportJson = json_encode($exportObject, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

// Start output buffering for HTML (needed for ZIP export)
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title><?= htmlspecialchars($seq['name']) ?> - The Narrative Gallery</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Playfair+Display:ital,wght@0,600;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Anime.js -->
    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>

    <style>
        :root {
            --bg-deep: #050508;
            --accent: #10b981;
            --accent-glow: rgba(16, 185, 129, 0.4);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(15, 23, 42, 0.4);
            --glass-border: rgba(255, 255, 255, 0.08);
            --font-ui: 'Inter', system-ui, sans-serif;
            --font-display: 'Playfair Display', serif;
        }

        body, html {
            margin: 0; padding: 0;
            width: 100%; height: 100%;
            background-color: var(--bg-deep);
            color: var(--text-main);
            font-family: var(--font-ui);
            overflow: hidden; 
            overscroll-behavior: none;
        }

        /* --- 1. Ambient Background --- */
        #ambient-bg {
            position: fixed; inset: -10%;
            width: 120%; height: 120%;
            z-index: 0;
            pointer-events: none;
            filter: blur(80px) saturate(1.5) opacity(0.4);
            transform: translateZ(0); 
        }
        .ambient-layer {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            opacity: 0;
            will-change: opacity;
        }

        /* --- 2. Split Layout Architecture --- */
        #layout {
            position: absolute; 
            inset: 0;
            display: flex;
            flex-direction: column;
            z-index: 10;
            height: 100dvh; 
        }

        /* MEDIA STAGE */
        #media-stage {
            position: relative;
            flex: none; 
            width: 100%;
            aspect-ratio: 1 / 1; 
            max-height: 55dvh; 
            background: #000;
            z-index: 20;
            box-shadow: 0 10px 40px rgba(0,0,0,0.9);
        }
        
        .media-frame {
            position: absolute;
            inset: 0;
            width: 100%; 
            height: 100%;
            overflow: hidden;
            background: #000;
        }

        .media-plane {
            position: absolute;
            inset: -5%; 
            width: 110%; 
            height: 110%;
            object-fit: cover;
            opacity: 0;
            will-change: transform, opacity;
            transform-origin: center center;
        }

        /* STORY THREAD */
        #story-thread {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            padding: 30px 20px 60vh 20px; 
            position: relative;
            z-index: 15;
            background: linear-gradient(180deg, rgba(5,5,8,0.4) 0%, rgba(5,5,8,0.95) 15%);
        }

        @media(min-width: 1024px) {
            #layout { flex-direction: row; }
            #media-stage { 
                flex: 0 0 55vw; 
                height: 100dvh; 
                max-height: none; 
                aspect-ratio: auto; 
                background: transparent;
                box-shadow: none;
                padding: 40px; 
                display: flex; align-items: center; justify-content: center;
            }
            .media-frame {
                position: relative;
                width: 100%; height: 100%;
                border-radius: 16px; 
                box-shadow: 0 30px 60px rgba(0,0,0,0.8);
            }
            #story-thread { 
                flex: 0 0 45vw; 
                height: 100dvh; 
                padding: 100px 60px 80vh 40px; 
                background: transparent;
            }
        }

        /* --- 3. Story Beats --- */
        .story-beat {
            position: relative;
            margin-bottom: 60px;
            padding-left: 25px;
            opacity: 0.2; 
            transform: scale(0.95) translateX(-10px);
            transition: all 0.6s cubic-bezier(0.25, 1, 0.5, 1);
            will-change: transform, opacity;
        }
        
        .story-beat::before {
            content: ''; position: absolute;
            left: 0; top: 10px; bottom: -70px;
            width: 2px;
            background: rgba(255,255,255,0.08);
        }
        
        .story-beat::after {
            content: ''; position: absolute;
            left: -4px; top: 10px;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--text-muted);
            border: 2px solid var(--bg-deep);
            transition: all 0.4s;
        }

        .story-beat.active {
            opacity: 1;
            transform: scale(1) translateX(0);
        }
        .story-beat.active::after {
            background: var(--accent);
            box-shadow: 0 0 15px var(--accent);
            transform: scale(1.4);
            border-color: #000;
        }

        .beat-index {
            font-family: monospace;
            color: var(--accent);
            letter-spacing: 0.15em;
            font-size: 0.75rem;
            display: block;
            font-weight: 700;
        }

        .beat-title {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 5vw, 2.8rem);
            margin: 0 0 12px 0;
            line-height: 1.1;
            color: #fff;
        }

        .beat-desc {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        /* Expandable Text Styles */
        .desc-hidden { display: none; opacity: 0; }
        .read-more-toggle {
            color: var(--accent); cursor: pointer;
            font-weight: 600; font-size: 0.9rem;
            margin-left: 6px; transition: color 0.2s; white-space: nowrap;
        }
        .read-more-toggle:hover { color: #fff; }

        /* --- 4. Glassmorphic Data Panels --- */
        .intel-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 16px;
            display: flex; flex-direction: column; gap: 12px;
        }

        .intel-row { display: flex; flex-direction: column; gap: 6px; }
        .intel-label {
            font-size: 0.65rem; text-transform: uppercase;
            letter-spacing: 0.1em; color: rgba(255,255,255,0.4); font-weight: 700;
        }
        .pill-wrap { display: flex; flex-wrap: wrap; gap: 6px; }
        
        .pill {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 4px 10px; border-radius: 12px;
            font-size: 0.75rem; color: #cbd5e1; 
            white-space: normal;
            word-break: break-word;
            max-width: 100%;
            line-height: 1.4;
            display: inline-block;
        }
        .pill.hl { border-color: rgba(16, 185, 129, 0.4); color: #34d399; background: rgba(16, 185, 129, 0.05); }
        .pill.char { border-color: rgba(245, 159, 11, 0.3); color: #fbbf24; }

        .director-note {
            background: rgba(0,0,0,0.4);
            border-left: 2px solid #8b5cf6;
            padding: 10px 12px;
            font-size: 0.85rem; line-height: 1.5;
            color: #e2e8f0; font-style: italic;
            border-radius: 0 6px 6px 0;
        }

        /* --- Beat Navigation Actions --- */
        .btn-beat-action {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        .btn-beat-action:hover {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent);
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 0 10px var(--accent-glow);
        }

        /* --- Intro Header & Navigation Controls --- */
        .seq-header {
            margin-bottom: 80px; padding-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .seq-header-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 15px;
            margin-bottom: 15px; flex-wrap: wrap;
        }
        .header-actions {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }

        /* Sequence Navigation */
        .seq-nav {
            display: flex; align-items: center;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px; padding: 2px;
            backdrop-filter: blur(4px);
        }
        .btn-nav-small {
            background: transparent; border: none; color: var(--text-muted);
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.2rem; transition: 0.2s; line-height: 1; padding-bottom: 2px;
        }
        .btn-nav-small:hover:not(:disabled) { color: #fff; background: rgba(255,255,255,0.1); }
        .btn-nav-small:disabled { opacity: 0.3; cursor: not-allowed; }
        
        .seq-id-input {
            width: 50px; background: transparent; border: none;
            color: var(--accent); text-align: center; font-family: monospace;
            font-size: 0.85rem; font-weight: 700; -moz-appearance: textfield;
        }
        .seq-id-input::-webkit-outer-spin-button, .seq-id-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .seq-id-input:focus { outline: none; color: #fff; border-bottom: 1px solid var(--accent); }

        .seq-header h1 {
            font-family: var(--font-display);
            font-size: clamp(2.2rem, 6vw, 4rem);
            margin: 0; line-height: 1.1;
        }
        .seq-header p.sub {
            color: var(--accent); text-transform: uppercase;
            letter-spacing: 0.2em; font-size: 0.75rem;
            font-weight: 800; margin: 0 0 10px 0;
        }
        .seq-header .desc { color: var(--text-muted); line-height: 1.6; font-size: 1rem; }

        .btn-download {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: var(--accent);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            transition: all 0.3s ease; backdrop-filter: blur(4px); white-space: nowrap; text-decoration: none;
        }
        .btn-download:hover {
            background: var(--accent); color: #000; box-shadow: 0 0 15px var(--accent-glow);
        }

        /* --- Iframe Detail Modal --- */
        .f-view-btn { 
            position: absolute; 
            top: 15px; right: 15px; 
            width: 40px; height: 40px; 
            background: rgba(0,0,0,0.6); color: #fff; 
            border: 1px solid rgba(255,255,255,0.2); 
            border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            cursor: pointer; z-index: 50; 
            opacity: 0.8; transition: all 0.2s; 
            font-size: 18px; backdrop-filter: blur(5px);
        }
        .media-frame:hover .f-view-btn, .f-view-btn:hover { 
            opacity: 1; background: var(--accent); color: #000; border-color: var(--accent); 
        }

        .view-modal { 
            position: fixed; inset: 0; 
            background: rgba(0,0,0,0.95); 
            z-index: 100000; display: none; 
            align-items: center; justify-content: center; 
            backdrop-filter: blur(10px);
        }
        .view-modal.active { display: flex; }
        .view-modal-content { 
            width: 95vw; height: 95vh; 
            background: #000; position: relative; 
            border: 1px solid var(--glass-border); 
            box-shadow: 0 0 40px rgba(0,0,0,0.8); 
            border-radius: 8px; overflow: hidden;
        }
        .view-close { 
            position: absolute; top: 15px; right: 15px; 
            width: 40px; height: 40px; 
            background: rgba(0,0,0,0.8); color: #fff; 
            border: 1px solid var(--glass-border); 
            border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            cursor: pointer; font-size: 24px; 
            z-index: 200; transition: all 0.2s; backdrop-filter: blur(5px);
        }
        .view-close:hover { background: var(--accent); color: #000; border-color: var(--accent); }
        iframe.frame-viewer { width: 100%; height: 100%; border: none; display: block; }

    </style>
</head>
<body>

    <!-- 1. Ambient Background -->
    <div id="ambient-bg">
        <img id="amb-a" class="ambient-layer" src="" alt="">
        <img id="amb-b" class="ambient-layer" src="" alt="">
    </div>

    <!-- 2. Main Layout -->
    <div id="layout">
        
        <!-- MEDIA STAGE -->
        <div id="media-stage">
            <div class="media-frame" id="media-frame">
                <?php if (!$isExport): ?>
                <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openCurrentFrame()" title="View Frame Detail">
                    <i class="bi bi-arrows-fullscreen"></i>
                </div>
                <?php endif; ?>
                <img id="img-a" class="media-plane" src="" alt="">
                <img id="img-b" class="media-plane" src="" alt="">
            </div>
        </div>

        <!-- STORY THREAD -->
        <div id="story-thread">
            <div class="seq-header">
                <div class="seq-header-top">
                    <div>
                        <p class="sub">Narrative Sequence</p>
                        <h1 id="ui-seq-name">Title</h1>
                    </div>
                    <div class="header-actions">
                        
                        <span class="pill hl" style="font-family: monospace; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; margin-right: 4px;" title="Total Sketches in Sequence">
                            <?= count($itemIds) ?> SKETCHES
                        </span>

                        <?php if (!$isExport): ?>
                        <div class="seq-nav">
                            <button class="btn-nav-small" onclick="navSeq(<?= $prevSeqId ?: 'null' ?>)" <?= !$prevSeqId ? 'disabled' : '' ?> title="Previous Sequence">&#8249;</button>
                            <input type="number" class="seq-id-input" value="<?= $seq['id'] ?>" onchange="jumpToSeq(this.value)" title="Current Sequence ID (Type to jump)">
                            <button class="btn-nav-small" onclick="navSeq(<?= $nextSeqId ?: 'null' ?>)" <?= !$nextSeqId ? 'disabled' : '' ?> title="Next Sequence">&#8250;</button>
                        </div>

                        <!-- JSON Button -->
                        <button class="btn-download" onclick="downloadSequenceJSON()" title="Download Full Sequence as JSON">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            <span>JSON</span>
                        </button>
                        <?php endif; ?>
                        
                        <!-- ZIP Button (Admin Mode) -->
                        <?php if (defined('ADMIN_MODE') && ADMIN_MODE && !$isExport): ?>
                        <a href="?id=<?= $seq['id'] ?>&export=1" class="btn-download" title="Download Standalone ZIP" style="text-decoration:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            <span>ZIP</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="desc" id="ui-seq-desc"></div>
            </div>
            <div id="beats-container"></div>
        </div>
    </div>

    <!-- 3. Iframe Modal -->
    <div class="view-modal" id="viewModal">
        <div class="view-modal-content">
            <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
            <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
        </div>
    </div>

    <!-- Inject Sequence Data Dynamically -->
    <?php if ($isExport): ?>
        <script src="<?= $frameDir ?>/data_<?= $seq['id'] ?>.js"></script>
    <?php else: ?>
        <script>
            const sequenceData = <?= $sequenceJson ?>;
            const rawExportData = <?= $exportJson ?>;
        </script>
    <?php endif; ?>

    <script>
        const frames = sequenceData.frames || [];

        // State
        let activeIndex = -1;
        let activePlane = 'img-a';
        let continuousPanAnim = null;
        let transitionToken = 0;
        const imageLoadCache = new Map();

        // DOM Elements
        const elImgA = document.getElementById('img-a');
        const elImgB = document.getElementById('img-b');
        const elAmbA = document.getElementById('amb-a');
        const elAmbB = document.getElementById('amb-b');
        const thread = document.getElementById('story-thread');
        const beatsContainer = document.getElementById('beats-container');

        function preloadFrameImage(src) {
            if (!src) return Promise.resolve('');
            if (imageLoadCache.has(src)) return imageLoadCache.get(src);

            const promise = new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve(src);
                img.onerror = () => resolve(src);
                img.src = src;
            });

            imageLoadCache.set(src, promise);
            return promise;
        }

        // --- 1. Init UI ---
        function init() {
            document.getElementById('ui-seq-name').innerText = sequenceData.name || 'Untitled';
            document.getElementById('ui-seq-desc').innerText = sequenceData.description || 'Scroll through the thread below to experience the sequence.';

            // Build Story Beats
            frames.forEach((frame, i) => {
                const cur = frame.curation || {};
                const cls = cur.class || {};
                const thm = cur.themes || {};
                const ent = cur.entities || {};
                const recs = cur.recs || {};

                let fnHtml = cls.narrative_function ? `<span class="pill hl">${cls.narrative_function}</span>` : '';
                let tnHtml = cls.emotional_tone ? `<span class="pill">${cls.emotional_tone}</span>` : '';
                let thmHtml = '';
                if (thm.primary_themes) thm.primary_themes.slice(0, 3).forEach(t => thmHtml += `<span class="pill">${t}</span>`);
                let entHtml = '';
                if (ent.characters) ent.characters.forEach(c => entHtml += `<span class="pill char">👤 ${c}</span>`);
                if (ent.locations) ent.locations.forEach(l => entHtml += `<span class="pill">📍 ${l}</span>`);

                let intelHtml = ``;
                if (fnHtml || tnHtml || thmHtml || entHtml || recs.potential_use) {
                    intelHtml = `
                    <div class="intel-card">
                        ${(fnHtml || tnHtml) ? `<div class="intel-row"><span class="intel-label">Classification</span><div class="pill-wrap">${fnHtml}${tnHtml}</div></div>` : ''}
                        ${thmHtml ? `<div class="intel-row"><span class="intel-label">Themes</span><div class="pill-wrap">${thmHtml}</div></div>` : ''}
                        ${entHtml ? `<div class="intel-row"><span class="intel-label">Entities</span><div class="pill-wrap">${entHtml}</div></div>` : ''}
                        ${recs.potential_use ? `<div class="intel-row"><span class="intel-label">AI Director's Note</span><div class="director-note">${recs.potential_use}</div></div>` : ''}
                    </div>`;
                }

                // Handle Text Truncation
                const descText = (frame.desc || '').trim();
                const words = descText.split(/\s+/);
                let descHtml = '';

                if (words.length > 25) {
                    const visiblePart = words.slice(0, 25).join(' ');
                    const hiddenPart = words.slice(25).join(' ');
                    descHtml = `
                        <span class="desc-visible">${visiblePart}</span><span class="desc-ellipsis">...</span>
                        <span class="desc-hidden" style="display:none;"> ${hiddenPart}</span>
                        <span class="read-more-toggle" onclick="toggleDesc(event, ${i})">Read more</span>
                    `;
                } else {
                    descHtml = descText;
                }

                const hasPrev = i > 0;
                const hasNext = i < frames.length - 1;
                const beat = document.createElement('div');
                beat.className = 'story-beat';
                beat.id = `beat-${i}`;
                beat.dataset.index = i;
                
                beat.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span class="beat-index" style="margin-bottom:0;">FRAME ${String(i + 1).padStart(2, '0')}</span>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top:0, behavior:'smooth'})" title="Back to Top">
                                <i class="bi bi-arrow-up"></i> Top
                            </button>
                            ${hasPrev ? `
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i - 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Previous Sketch">
                                <i class="bi bi-arrow-up"></i> Prev
                            </button>
                            ` : ''}
                            ${hasNext ? `
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i + 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Next Sketch">
                                Next <i class="bi bi-arrow-down"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    <h2 class="beat-title">${frame.name || 'Untitled Segment'}</h2>
                    <div class="beat-desc">${descHtml}</div>
                    ${intelHtml}
                `;
                beatsContainer.appendChild(beat);
            });

            if (frames.length > 0) {
                const firstThumb = frames[0].thumb || '';
                if (firstThumb) {
                    elImgA.src = firstThumb;
                    elAmbA.src = firstThumb;
                }
                elImgA.style.opacity = 1;
                elAmbA.style.opacity = 1;
                elImgA.style.transform = "scale(1)";
            }

            setupIntersectionObserver();
        }

        // --- 2. Expandable Text Logic ---
        window.toggleDesc = (e, idx) => {
            e.stopPropagation();
            const beat = document.getElementById(`beat-${idx}`);
            const hidden = beat.querySelector('.desc-hidden');
            const ellipsis = beat.querySelector('.desc-ellipsis');
            const btn = beat.querySelector('.read-more-toggle');

            if (hidden.style.display === 'none') {
                hidden.style.display = 'inline';
                ellipsis.style.display = 'none';
                btn.innerText = 'Show less';
                anime({ targets: hidden, opacity: [0, 1], duration: 400, easing: 'easeOutQuad' });
            } else {
                hidden.style.display = 'none';
                ellipsis.style.display = 'inline';
                btn.innerText = 'Read more';
                hidden.style.opacity = 0; 
            }
        };

        // --- 3. Scroll Observation ---
        function setupIntersectionObserver() {
            const options = {
                root: thread,
                rootMargin: '-30% 0px -40% 0px',
                threshold: 0
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const idx = parseInt(entry.target.dataset.index);
                        activateBeat(idx);
                    }
                });
            }, options);

            document.querySelectorAll('.story-beat').forEach(beat => observer.observe(beat));
        }

        // --- 4. Anime.js Visual Transitions ---
        async function activateBeat(index) {
            if (index === activeIndex) return;

            const beatEl = document.getElementById(`beat-${index}`);
            if (!beatEl) return;

            document.querySelectorAll('.story-beat').forEach(b => b.classList.remove('active'));
            beatEl.classList.add('active');

            const newFrame = frames[index];
            if (!newFrame || !newFrame.thumb) {
                activeIndex = index;
                return;
            }

            const myToken = ++transitionToken;
            const previousIndex = activeIndex;
            activeIndex = index;

            await preloadFrameImage(newFrame.thumb);
            if (myToken !== transitionToken) return;

            const previousPlaneId = activePlane;
            const oldPlane = previousPlaneId === 'img-a' ? elImgA : elImgB;
            const newPlane = previousPlaneId === 'img-a' ? elImgB : elImgA;
            const oldAmb = previousPlaneId === 'img-a' ? elAmbA : elAmbB;
            const newAmb = previousPlaneId === 'img-a' ? elAmbB : elAmbA;

            activePlane = newPlane.id;

            newPlane.src = newFrame.thumb;
            newAmb.src = newFrame.thumb;

            newPlane.style.zIndex = 2;
            oldPlane.style.zIndex = 1;

            if (continuousPanAnim) continuousPanAnim.pause();

            const scrollingDown = index > previousIndex;
            const yOffset = scrollingDown ? 15 : -15;
            const rotateDir = scrollingDown ? 4 : -4;

            anime.remove([oldPlane, newPlane, oldAmb, newAmb, document.getElementById('media-frame')]);

            anime.set(newPlane, { opacity: 0, scale: 1.15, translateX: 0, translateY: 0, rotateZ: 0 });
            anime.set(oldPlane, { opacity: 1 });

            const tl = anime.timeline({ easing: 'easeOutCubic' });

            anime({ targets: newAmb, opacity: 1, duration: 1500, easing: 'linear' });
            anime({ targets: oldAmb, opacity: 0, duration: 1500, easing: 'linear' });

            tl.add({ targets: document.getElementById('media-frame'), scale: [0.98, 1], duration: 800 }, 0);
            tl.add({ targets: newPlane, opacity: [0, 1], translateY: [yOffset + '%', '0%'], scale: [1.1, 1], rotateZ: [rotateDir, 0], duration: 1000 }, 0);
            tl.add({ targets: oldPlane, opacity: [1, 0], translateY: ['0%', (-yOffset) + '%'], scale: [1, 0.9], duration: 900 }, 0);

            tl.finished.then(() => {
                if (myToken === transitionToken) {
                    startContinuousPan(newPlane);
                }
            });
        }

        function startContinuousPan(targetElement) {
            const panX = (Math.random() > 0.5 ? 2 : -2) + '%';
            const panY = (Math.random() > 0.5 ? 2 : -2) + '%';

            continuousPanAnim = anime({
                targets: targetElement,
                scale: [1, 1.05],
                translateX: [0, panX],
                translateY: [0, panY],
                duration: 15000,
                easing: 'linear',
                direction: 'alternate',
                loop: true
            });
        }

        // --- 5. Export Logic ---
        window.downloadSequenceJSON = () => {
            if (typeof rawExportData === 'undefined') return;
            const dataStr = JSON.stringify(rawExportData, null, 2);
            const blob = new Blob([dataStr], { type: "application/json" });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement("a");
            a.href = url;
            let safeName = (sequenceData.name || 'sequence').replace(/[^a-z0-9]/gi, '_').toLowerCase();
            a.download = safeName + '_export.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        };

        // --- 6. Sequence Navigation ---
        window.navSeq = (id) => {
            if (id) window.location.href = '?id=' + id;
        };
        
        window.jumpToSeq = (id) => {
            const cleanId = parseInt(id);
            if (!isNaN(cleanId) && cleanId > 0) {
                window.location.href = '?id=' + cleanId;
            }
        };

        // --- 7. Iframe Modal Logic ---
        window.openFrameModal = (id) => {
            if (!id) return;
            document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
            document.getElementById('viewModal').classList.add('active');
        };

        window.closeFrameModal = () => {
            document.getElementById('viewModal').classList.remove('active');
            setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
        };

        window.openCurrentFrame = () => {
            if (activeIndex === -1) return;
            const f = frames[activeIndex];
            let targetId = f._active_frame_id;
            if (!targetId && f.frames && f.frames.length > 0) targetId = f.frames[0].id;
            if (!targetId) targetId = f.id; 
            
            openFrameModal(targetId);
        };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const viewModal = document.getElementById('viewModal');
                if (viewModal && viewModal.classList.contains('active')) {
                    closeFrameModal();
                }
            }
        });

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
<?php
$htmlContent = ob_get_clean();

// ==============================================================================
// ZIP PACKAGING EXECUTION
// ==============================================================================
if ($isExport) {
    if (!extension_loaded('zip')) die("Error: ZIP extension is not loaded in PHP.");
    
    $zip = new ZipArchive();
    $zipName = sys_get_temp_dir() . '/sequence_' . $seq['id'] . '_' . time() . '.zip';
    
    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        // 1. Add the Frame Directory
        $zip->addEmptyDir($frameDir);
        
        // 2. Add the Main HTML File
        $zip->addFromString("seq_{$seq['id']}.html", $htmlContent);
        
        // 3. Generate & Add the External JS File
        $jsFileContent = "const sequenceData = " . $sequenceJson . ";\n\n";
        $jsFileContent .= "const rawExportData = " . $exportJson . ";\n";
        $zip->addFromString($frameDir . '/data_' . $seq['id'] . '.js', $jsFileContent);
        
        // 4. Fetch and Package Images
        foreach ($imagesToDownload as $filename => $originalThumbUrl) {
            $localZipPath = $frameDir . '/' . $filename;
            
            $physicalPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($originalThumbUrl, '/');
            $altPhysicalPath = realpath(__DIR__ . '/../') . '/' . ltrim($originalThumbUrl, '/');
            
            if (file_exists($physicalPath)) {
                $zip->addFile($physicalPath, $localZipPath);
            } elseif (file_exists($altPhysicalPath)) {
                $zip->addFile($altPhysicalPath, $localZipPath);
            } else {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $fullUrl = $protocol . $host . '/' . ltrim($originalThumbUrl, '/');
                
                $context = stream_context_create([
                    "ssl" => [
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ],
                ]);
                
                $imgData = @file_get_contents($fullUrl, false, $context);
                if ($imgData) {
                    $zip->addFromString($localZipPath, $imgData);
                }
            }
        }
        $zip->close();
        
        // Output Zip to Browser
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=standalone_sequence_' . $seq['id'] . '.zip');
        header('Content-Length: ' . filesize($zipName));
        readfile($zipName);
        unlink($zipName);
        exit;
    } else {
        die("Failed to create ZIP file.");
    }
} else {
    // Normal Web Rendering
    echo $htmlContent;
}
?>
