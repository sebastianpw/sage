<?php
// public/view_narrative_synthesis.php
// Showrunner - Narrative Synthesis Viewer
// A breathtaking, non-overlapping sequence viewer using Anime.js
// Tailored to display Pass 1, 2, and 3 AI Episode Generation data.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

/**
 * Clean and decode JSON, handling markdown blocks and uppercase keys.
 */
function robustJsonDecode($raw) {
    if (!is_string($raw) || trim($raw) === '') return [];
    $clean = trim($raw);
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $clean, $m)) {
        $clean = trim($m[1]);
    }
    $d = json_decode($clean, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($d)) return [];
    
    // Normalize keys to lowercase so we don't miss "LOGLINE" vs "logline"
    return array_change_key_case($d, CASE_LOWER);
}

/**
 * Return the first non-empty value from a row by trying multiple keys.
 */
function firstExistingValue(array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
            return $row[$key];
        }
    }
    return $default;
}

/**
 * Try to resolve a displayable image URL/path for a frame row.
 */
function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = firstExistingValue($row, [
        'thumb', 'thumbnail', 'image', 'image_url', 'image_path',
        'file_path', 'path', 'src', 'url', 'filename', 'file_name',
    ], '');
    if (is_string($candidate) && $candidate !== '') return $candidate;
    if ($frameId > 0) return 'view_frame.php?frame_id=' . $frameId;
    return '';
}

// 1. Fetch the sequence
$seqId = $_GET['id'] ?? null;
$table = 'narrative_sequences';

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

// Sequence Navigation
$stmtPrev = $pdo->prepare("SELECT id FROM $table WHERE id < ? ORDER BY id DESC LIMIT 1");
$stmtPrev->execute([$seq['id']]);
$prevSeqId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare("SELECT id FROM $table WHERE id > ? ORDER BY id ASC LIMIT 1");
$stmtNext->execute([$seq['id']]);
$nextSeqId = $stmtNext->fetchColumn();

// 2. Fetch the Synthesiser Data (Pass 3)
$stmtSynth = $pdo->prepare("SELECT * FROM narrative_sequence_analysis WHERE sequence_id = ?");
$stmtSynth->execute([$seq['id']]);
$seqAnalysis = $stmtSynth->fetch(PDO::FETCH_ASSOC) ?: [];

// Safely extract all Pass 3 data from the raw JSON payload in case the columns missed uppercase keys
$synthData = robustJsonDecode($seqAnalysis['synthesiser_raw'] ?? '');

$episodeTitle    = $synthData['episode_title'] ?? $seqAnalysis['episode_title'] ?? $seq['name'];
$episodeSubtitle = $synthData['episode_subtitle'] ?? $seqAnalysis['episode_subtitle'] ?? '';
$logline         = $synthData['logline'] ?? $seqAnalysis['logline'] ?? '';
$thesis          = $synthData['episode_thesis'] ?? $seqAnalysis['episode_thesis'] ?? '';

// Array fields
$motifs   = $synthData['recurring_motifs'] ?? robustJsonDecode($seqAnalysis['recurring_motifs'] ?? '[]');
$tensions = $synthData['open_tensions'] ?? robustJsonDecode($seqAnalysis['open_tensions'] ?? '[]');
$prodNotes= $synthData['production_notes'] ?? robustJsonDecode($seqAnalysis['production_notes'] ?? '[]');
$actStruct= $synthData['act_structure'] ?? robustJsonDecode($seqAnalysis['act_structure'] ?? '[]');

// 3. Fetch the Beat Analyses (Pass 1 & 2)
$stmtBeats = $pdo->prepare("SELECT * FROM narrative_beat_analysis WHERE sequence_id = ? ORDER BY position ASC");
$stmtBeats->execute([$seq['id']]);
$beatAnalysisRows = [];
foreach ($stmtBeats->fetchAll(PDO::FETCH_ASSOC) as $brow) {
    $beatAnalysisRows[$brow['position']] = $brow;
}

// 4. Resolve Images
$itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
$pureSketchIds = [];
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
    $stmtS = $pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }
}

$selectedFrameMap = [];
$activeFrameIds = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fid = (int)$row['id'];
        $selectedFrameMap[$fid] = ['row' => $row, 'thumb' => resolveFrameThumb($row, $fid)];
    }
}

// Fallback logic for sketches lacking explicit frames
$latestFrameBySketch = [];
$sketchIdsNeedingLatestFrame = array_values(array_unique(array_filter(array_map(function($idx, $item) use ($selectedFrameIds) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    return ($sid > 0 && empty($selectedFrameIds[$idx])) ? $sid : null;
}, array_keys($itemIds), $itemIds))));

if (!empty($sketchIdsNeedingLatestFrame)) {
    $inClauseFb = implode(',', array_fill(0, count($sketchIdsNeedingLatestFrame), '?'));
    $stmtFb = $pdo->prepare("
        SELECT f.*, f.entity_id AS _sketch_id
        FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id
        WHERE f.entity_id IN ($inClauseFb) ORDER BY f.id DESC
    ");
    $stmtFb->execute($sketchIdsNeedingLatestFrame);
    foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchId = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sketchId])) {
            $fid = (int)$row['id'];
            $latestFrameBySketch[$sketchId] = ['frame_id' => $fid, 'thumb' => resolveFrameThumb($row, $fid)];
        }
    }
}

// 5. Build Final Viewer Frames
$frames = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid <= 0 || !isset($sketchesData[$sid])) continue;

    $sketchRow = $sketchesData[$sid];
    $activeFrameId = $selectedFrameIds[$idx] ?? null;
    $activeThumb = $activeFrameId && isset($selectedFrameMap[$activeFrameId]) 
        ? $selectedFrameMap[$activeFrameId]['thumb'] 
        : ($latestFrameBySketch[$sid]['thumb'] ?? '');

    $bRow = $beatAnalysisRows[$idx] ?? [];
    $bRaw = robustJsonDecode($bRow['beat_raw'] ?? '');
    $cRaw = robustJsonDecode($bRow['compose_raw'] ?? '');

    // Fallback cascade for description: prose -> summary -> raw sketch prompt
    $desc = $cRaw['scene_prose'] ?? $cRaw['beat_summary'] ?? $bRow['scene_title'] ?? $sketchRow['description'] ?? '';

    $frames[] = [
        'id'                 => $sid,
        'name'               => $bRow['scene_title'] ?? $sketchRow['name'] ?? 'Untitled Beat',
        'desc'               => $desc,
        'thumb'              => $activeThumb,
        '_active_frame_id'   => $activeFrameId ?: ($latestFrameBySketch[$sid]['frame_id'] ?? null),
        'act_label'          => $bRow['act_label'] ?? '',
        'emotional_register' => $bRow['emotional_register'] ?? $bRaw['emotional_register'] ?? '',
        'tension_type'       => $bRaw['tension_type'] ?? '',
        'narrative_function' => $bRaw['narrative_function'] ?? '',
        'visual_anchors'     => $bRaw['visual_anchors'] ?? [],
        'new_motifs'         => $cRaw['new_motifs'] ?? [],
        'new_tensions'       => $cRaw['new_tensions'] ?? [],
    ];
}

$sequenceJson = json_encode([
    'id'       => $seq['id'],
    'title'    => $episodeTitle,
    'subtitle' => $episodeSubtitle,
    'logline'  => $logline,
    'thesis'   => $thesis,
    'motifs'   => is_array($motifs) ? $motifs : [],
    'tensions' => is_array($tensions) ? $tensions : [],
    'prodNotes'=> is_array($prodNotes) ? $prodNotes : [],
    'frames'   => $frames
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title><?= htmlspecialchars($episodeTitle) ?> - Synthesis Viewer</title>

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
            --glass-bg: rgba(15, 23, 42, 0.5);
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

        /* --- Ambient Background --- */
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

        /* --- Split Layout Architecture --- */
        #layout {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            z-index: 10; height: 100dvh; 
        }

        /* MEDIA STAGE */
        #media-stage {
            position: relative; flex: none; width: 100%;
            aspect-ratio: 1 / 1; max-height: 55dvh; 
            background: #000; z-index: 20;
            box-shadow: 0 10px 40px rgba(0,0,0,0.9);
        }
        
        .media-frame {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            overflow: hidden; background: #000;
        }

        .media-plane {
            position: absolute; inset: -5%; 
            width: 110%; height: 110%;
            object-fit: cover; opacity: 0;
            will-change: transform, opacity;
            transform-origin: center center;
        }

        /* STORY THREAD */
        #story-thread {
            flex: 1; overflow-y: auto; overflow-x: hidden;
            scroll-behavior: smooth;
            padding: 30px 20px 60vh 20px; 
            position: relative; z-index: 15;
            background: linear-gradient(180deg, rgba(5,5,8,0.4) 0%, rgba(5,5,8,0.95) 15%);
        }

        @media(min-width: 1024px) {
            #layout { flex-direction: row; }
            #media-stage { 
                flex: 0 0 55vw; height: 100dvh; max-height: none; 
                aspect-ratio: auto; background: transparent; box-shadow: none;
                padding: 40px; display: flex; align-items: center; justify-content: center;
            }
            .media-frame {
                position: relative; width: 100%; height: 100%;
                border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8);
            }
            #story-thread { 
                flex: 0 0 45vw; height: 100dvh; 
                padding: 100px 60px 80vh 40px; background: transparent;
            }
        }

        /* --- The Episode Document (Synthesis) --- */
        .synthesis-doc {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 60px;
            backdrop-filter: blur(16px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .synthesis-doc h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 3rem);
            margin: 0 0 10px 0; color: #fff; line-height: 1.1;
        }
        .synthesis-doc h3 {
            color: var(--accent); text-transform: uppercase;
            letter-spacing: 0.1em; font-size: 0.85rem; margin: 0 0 20px 0;
        }
        
        .logline-box {
            font-size: 1.1rem; line-height: 1.6; color: #e2e8f0;
            border-left: 3px solid var(--accent);
            padding-left: 15px; margin-bottom: 25px;
            font-weight: 300; font-style: italic;
        }

        .thesis-box {
            font-size: 0.95rem; line-height: 1.6; color: #cbd5e1;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 15px; border-radius: 8px; margin-bottom: 25px;
        }
        .thesis-box strong { color: #fff; font-weight: 600; font-family: var(--font-ui); text-transform: uppercase; font-size:0.8rem; letter-spacing:0.1em;}

        .synth-list-title {
            font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);
            letter-spacing: 0.1em; font-weight: 700; margin-bottom: 8px; margin-top: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 4px;
        }
        .synth-list {
            margin: 0; padding-left: 18px; color: #cbd5e1; font-size: 0.9rem; line-height: 1.5;
        }
        .synth-list li { margin-bottom: 8px; }

        /* --- Story Beats --- */
        .story-beat {
            position: relative; margin-bottom: 60px; padding-left: 25px;
            opacity: 0.2; transform: scale(0.95) translateX(-10px);
            transition: all 0.6s cubic-bezier(0.25, 1, 0.5, 1);
            will-change: transform, opacity;
        }
        .story-beat::before {
            content: ''; position: absolute;
            left: 0; top: 10px; bottom: -70px; width: 2px;
            background: rgba(255,255,255,0.08);
        }
        .story-beat::after {
            content: ''; position: absolute;
            left: -4px; top: 10px; width: 10px; height: 10px;
            border-radius: 50%; background: var(--text-muted);
            border: 2px solid var(--bg-deep); transition: all 0.4s;
        }
        .story-beat.active { opacity: 1; transform: scale(1) translateX(0); }
        .story-beat.active::after {
            background: var(--accent); box-shadow: 0 0 15px var(--accent);
            transform: scale(1.4); border-color: #000;
        }

        .beat-index {
            font-family: monospace; color: var(--accent); letter-spacing: 0.15em;
            font-size: 0.75rem; display: block; font-weight: 700; text-transform: uppercase; margin-bottom: 0;
        }
        .beat-title {
            font-family: var(--font-display); font-size: clamp(1.6rem, 4vw, 2.4rem);
            margin: 0 0 12px 0; line-height: 1.1; color: #fff;
        }
        .beat-desc {
            font-size: 1rem; line-height: 1.7; color: var(--text-muted);
            margin-bottom: 20px; white-space: pre-wrap; /* Honors prose line breaks */
        }

        /* --- Beat Navigation Actions --- */
        .btn-beat-action {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted); padding: 4px 10px; border-radius: 12px;
            font-size: 0.7rem; font-weight: 600; cursor: pointer; display: flex;
            align-items: center; gap: 6px; transition: all 0.2s ease;
            text-transform: uppercase; backdrop-filter: blur(4px);
        }
        .btn-beat-action:hover {
            background: rgba(16, 185, 129, 0.1); color: var(--accent);
            border-color: rgba(16, 185, 129, 0.4); box-shadow: 0 0 10px var(--accent-glow);
        }

        /* Expandable Text Styles */
        .desc-hidden { display: none; opacity: 0; }
        .read-more-toggle {
            color: var(--accent); cursor: pointer; font-weight: 600; font-size: 0.9rem;
            margin-left: 6px; transition: color 0.2s; white-space: nowrap; display:inline-block; margin-top:8px;
        }
        .read-more-toggle:hover { color: #fff; }

        /* --- Glassmorphic Intel Panels --- */
        .intel-card {
            background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border);
            border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 12px;
        }
        .intel-row { display: flex; flex-direction: column; gap: 6px; }
        .intel-label {
            font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; 
            color: rgba(255,255,255,0.4); font-weight: 700;
        }
        .pill-wrap { display: flex; flex-wrap: wrap; gap: 6px; }
        .pill {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; color: #cbd5e1; 
            line-height: 1.4; display: inline-block;
        }
        .pill.hl { border-color: rgba(16, 185, 129, 0.4); color: #34d399; background: rgba(16, 185, 129, 0.05); }
        .pill.warn { border-color: rgba(245, 159, 11, 0.3); color: #fbbf24; }

        /* --- Top Navigation Actions --- */
        .nav-island {
            position: absolute; top: 30px; right: 30px; z-index: 100;
            display: flex; align-items: center; gap: 10px;
        }
        .seq-nav {
            display: flex; align-items: center; background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border); border-radius: 20px; padding: 2px;
            backdrop-filter: blur(8px);
        }
        .btn-nav-small {
            background: transparent; border: none; color: var(--text-muted);
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.2rem; transition: 0.2s;
        }
        .btn-nav-small:hover:not(:disabled) { color: #fff; background: rgba(255,255,255,0.1); }
        .btn-nav-small:disabled { opacity: 0.3; cursor: not-allowed; }
        .seq-id-input {
            width: 45px; background: transparent; border: none;
            color: var(--accent); text-align: center; font-family: monospace;
            font-size: 0.9rem; font-weight: 700; -moz-appearance: textfield;
        }
        .seq-id-input:focus { outline: none; color: #fff; border-bottom: 1px solid var(--accent); }

        /* --- Iframe Modal --- */
        .f-view-btn { 
            position: absolute; top: 15px; right: 15px; width: 40px; height: 40px; 
            background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            cursor: pointer; z-index: 50; opacity: 0.8; transition: all 0.2s; 
            font-size: 18px; backdrop-filter: blur(5px);
        }
        .media-frame:hover .f-view-btn, .f-view-btn:hover { 
            opacity: 1; background: var(--accent); color: #000; border-color: var(--accent); 
        }

        .view-modal { 
            position: fixed; inset: 0; background: rgba(0,0,0,0.95); 
            z-index: 100000; display: none; align-items: center; justify-content: center; 
            backdrop-filter: blur(10px);
        }
        .view-modal.active { display: flex; }
        .view-modal-content { 
            width: 95vw; height: 95vh; background: #000; position: relative; 
            border: 1px solid var(--glass-border); box-shadow: 0 0 40px rgba(0,0,0,0.8); 
            border-radius: 8px; overflow: hidden;
        }
        .view-close { 
            position: absolute; top: 15px; right: 15px; width: 40px; height: 40px; 
            background: rgba(0,0,0,0.8); color: #fff; border: 1px solid var(--glass-border); 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            cursor: pointer; font-size: 24px; z-index: 200; transition: all 0.2s; backdrop-filter: blur(5px);
        }
        .view-close:hover { background: var(--accent); color: #000; border-color: var(--accent); }
        iframe.frame-viewer { width: 100%; height: 100%; border: none; display: block; }
    </style>
</head>
<body>

    <!-- Ambient Background -->
    <div id="ambient-bg">
        <img id="amb-a" class="ambient-layer" src="" alt="">
        <img id="amb-b" class="ambient-layer" src="" alt="">
    </div>

    <!-- Navigation Island -->
    <div class="nav-island">
        <div class="seq-nav">
            <button class="btn-nav-small" onclick="navSeq(<?= $prevSeqId ?: 'null' ?>)" <?= !$prevSeqId ? 'disabled' : '' ?> title="Previous Sequence">&#8249;</button>
            <input type="number" class="seq-id-input" value="<?= $seq['id'] ?>" onchange="jumpToSeq(this.value)" title="Current Sequence ID">
            <button class="btn-nav-small" onclick="navSeq(<?= $nextSeqId ?: 'null' ?>)" <?= !$nextSeqId ? 'disabled' : '' ?> title="Next Sequence">&#8250;</button>
        </div>
    </div>

    <!-- Main Layout -->
    <div id="layout">
        <!-- MEDIA STAGE -->
        <div id="media-stage">
            <div class="media-frame" id="media-frame">
                <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openCurrentFrame()" title="View Frame Detail">
                    <i class="bi bi-arrows-fullscreen"></i>
                </div>
                <img id="img-a" class="media-plane" src="" alt="">
                <img id="img-b" class="media-plane" src="" alt="">
            </div>
        </div>

        <!-- STORY THREAD -->
        <div id="story-thread">
            
            <!-- EPISODE SYNTHESIS BIBLE -->
            <div class="synthesis-doc" id="synthesis-doc">
                <!-- Injected via JS -->
            </div>

            <!-- BEATS -->
            <div id="beats-container"></div>
        </div>
    </div>

    <!-- Iframe Modal -->
    <div class="view-modal" id="viewModal">
        <div class="view-modal-content">
            <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
            <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
        </div>
    </div>

    <!-- Payload -->
    <script>
        const sd = <?= $sequenceJson ?>;
    </script>

    <script>
        const frames = sd.frames || [];

        // State
        let activeIndex = -1;
        let activePlane = 'img-a';
        let continuousPanAnim = null;
        let transitionToken = 0;
        const imageLoadCache = new Map();

        const elImgA = document.getElementById('img-a');
        const elImgB = document.getElementById('img-b');
        const elAmbA = document.getElementById('amb-a');
        const elAmbB = document.getElementById('amb-b');
        const thread = document.getElementById('story-thread');
        const beatsContainer = document.getElementById('beats-container');

        function preloadFrameImage(src) {
            if (!src) return Promise.resolve('');
            if (imageLoadCache.has(src)) return imageLoadCache.get(src);
            const promise = new Promise(resolve => {
                const img = new Image();
                img.onload = () => resolve(src);
                img.onerror = () => resolve(src);
                img.src = src;
            });
            imageLoadCache.set(src, promise);
            return promise;
        }

        function init() {
            // Build Episode Document Section
            const doc = document.getElementById('synthesis-doc');
            
            let motifHtml = sd.motifs && sd.motifs.length > 0 
                ? `<div class="synth-list-title">Recurring Motifs</div><ul class="synth-list">${sd.motifs.map(m => `<li>${m}</li>`).join('')}</ul>` 
                : '';
                
            let tensionHtml = sd.tensions && sd.tensions.length > 0 
                ? `<div class="synth-list-title">Open Tensions</div><ul class="synth-list">${sd.tensions.map(t => `<li>${t}</li>`).join('')}</ul>` 
                : '';
                
            let prodHtml = sd.prodNotes && sd.prodNotes.length > 0 
                ? `<div class="synth-list-title">Production Notes</div><ul class="synth-list">${sd.prodNotes.map(p => typeof p === 'string' ? `<li>${p}</li>` : `<li><strong>${p.topic || 'Note'}:</strong> ${p.directive || p.note || ''}</li>`).join('')}</ul>` 
                : '';

            doc.innerHTML = `
                ${sd.subtitle ? `<h3>${sd.subtitle}</h3>` : ''}
                <h1>${sd.title || 'Untitled Episode'}</h1>
                ${sd.logline ? `<div class="logline-box">${sd.logline}</div>` : ''}
                ${sd.thesis ? `<div class="thesis-box"><strong>Episode Thesis:</strong><br>${sd.thesis}</div>` : ''}
                ${motifHtml}
                ${tensionHtml}
                ${prodHtml}
            `;

            // Build Story Beats
            frames.forEach((frame, i) => {
                const hasPrev = i > 0;
                const hasNext = i < frames.length - 1;

                let pillsHtml = '';
                if (frame.act_label) pillsHtml += `<span class="pill hl">${frame.act_label}</span>`;
                if (frame.tension_type) pillsHtml += `<span class="pill warn">Tension: ${frame.tension_type}</span>`;
                if (frame.emotional_register) pillsHtml += `<span class="pill">Mood: ${frame.emotional_register}</span>`;
                if (frame.narrative_function) pillsHtml += `<span class="pill">${frame.narrative_function}</span>`;

                let newElementsHtml = '';
                if (frame.new_motifs && frame.new_motifs.length > 0) {
                    newElementsHtml += `<div class="intel-row"><span class="intel-label">New Motifs Established</span><div class="pill-wrap">${frame.new_motifs.map(m => `<span class="pill">${m}</span>`).join('')}</div></div>`;
                }
                if (frame.new_tensions && frame.new_tensions.length > 0) {
                    newElementsHtml += `<div class="intel-row"><span class="intel-label">New Tensions Raised</span><div class="pill-wrap">${frame.new_tensions.map(t => `<span class="pill warn">${t}</span>`).join('')}</div></div>`;
                }

                let intelHtml = '';
                if (pillsHtml || newElementsHtml) {
                    intelHtml = `
                    <div class="intel-card">
                        ${pillsHtml ? `<div class="intel-row"><span class="intel-label">Structural Analysis</span><div class="pill-wrap">${pillsHtml}</div></div>` : ''}
                        ${newElementsHtml}
                    </div>`;
                }

                // Smart Text Truncation for long Prose
                const descText = (frame.desc || '').trim();
                const paragraphs = descText.split('\n').filter(p => p.trim() !== '');
                let descHtml = '';

                if (paragraphs.length > 2 || descText.length > 500) {
                    const threshold = descText.indexOf(' ', 400);
                    const splitPos = threshold > -1 ? threshold : 400;
                    
                    const visiblePart = descText.substring(0, splitPos);
                    const hiddenPart = descText.substring(splitPos);
                    
                    descHtml = `
                        <span class="desc-visible">${visiblePart}</span><span class="desc-ellipsis">...</span>
                        <span class="desc-hidden" style="display:none;">${hiddenPart}</span>
                        <br><span class="read-more-toggle" onclick="toggleDesc(event, ${i})">Read full scene</span>
                    `;
                } else {
                    descHtml = descText;
                }

                const beat = document.createElement('div');
                beat.className = 'story-beat';
                beat.id = `beat-${i}`;
                beat.dataset.index = i;
                
                beat.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span class="beat-index">BEAT ${String(i + 1).padStart(2, '0')}</span>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top:0, behavior:'smooth'})" title="Back to Top">
                                <i class="bi bi-arrow-up"></i> Top
                            </button>
                            ${hasPrev ? `
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i - 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Previous Beat">
                                <i class="bi bi-arrow-up"></i> Prev
                            </button>
                            ` : ''}
                            ${hasNext ? `
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i + 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Next Beat">
                                Next <i class="bi bi-arrow-down"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    <h2 class="beat-title">${frame.name}</h2>
                    <div class="beat-desc">${descHtml}</div>
                    ${intelHtml}
                `;
                beatsContainer.appendChild(beat);
            });

            if (frames.length > 0) {
                const firstThumb = frames[0].thumb || '';
                if (firstThumb) { elImgA.src = firstThumb; elAmbA.src = firstThumb; }
                elImgA.style.opacity = 1; elAmbA.style.opacity = 1;
                elImgA.style.transform = "scale(1)";
            }

            setupIntersectionObserver();
        }

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
                btn.innerText = 'Read full scene';
                hidden.style.opacity = 0; 
            }
        };

        function setupIntersectionObserver() {
            const options = { root: thread, rootMargin: '-30% 0px -40% 0px', threshold: 0 };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        activateBeat(parseInt(entry.target.dataset.index));
                    }
                });
            }, options);
            document.querySelectorAll('.story-beat').forEach(beat => observer.observe(beat));
        }

        async function activateBeat(index) {
            if (index === activeIndex) return;

            const beatEl = document.getElementById(`beat-${index}`);
            if (!beatEl) return;

            document.querySelectorAll('.story-beat').forEach(b => b.classList.remove('active'));
            beatEl.classList.add('active');

            const newFrame = frames[index];
            if (!newFrame || !newFrame.thumb) { activeIndex = index; return; }

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
            newPlane.src = newFrame.thumb; newAmb.src = newFrame.thumb;
            newPlane.style.zIndex = 2; oldPlane.style.zIndex = 1;

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

            tl.finished.then(() => { if (myToken === transitionToken) startContinuousPan(newPlane); });
        }

        function startContinuousPan(targetElement) {
            const panX = (Math.random() > 0.5 ? 2 : -2) + '%';
            const panY = (Math.random() > 0.5 ? 2 : -2) + '%';
            continuousPanAnim = anime({
                targets: targetElement, scale: [1, 1.05], translateX: [0, panX], translateY: [0, panY],
                duration: 15000, easing: 'linear', direction: 'alternate', loop: true
            });
        }

        // --- Navigation ---
        window.navSeq = (id) => { if (id) window.location.href = '?id=' + id; };
        window.jumpToSeq = (id) => {
            const cleanId = parseInt(id);
            if (!isNaN(cleanId) && cleanId > 0) window.location.href = '?id=' + cleanId;
        };

        // --- Iframe Modal ---
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
            if (f._active_frame_id) openFrameModal(f._active_frame_id);
            else if (f.id) openFrameModal(f.id); // fallback to sketch id
        };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeFrameModal();
        });

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>