<?php
// public/seqview_story.php
// Showrunner V10 - Cinematic Storyboard Viewer
// Features: True Entity-Chain Resolution, Reduced Zoom, ZIP/JSON Export, Text Truncation, Frame Detail Iframe.

// ==============================================================================
// CONFIGURATION
// Enable to show the ZIP Download button for standalone packaging.
define('ADMIN_MODE', true);
// ==============================================================================

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// 1. Fetch the Storyboard
$sbId = $_GET['id'] ?? null;
$sb = null;

if ($sbId) {
    $stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ? AND is_archived = 0");
    $stmt->execute([$sbId]);
    $sb = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $sb = $pdo->query("SELECT * FROM storyboards WHERE is_archived = 0 ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$sb) {
    die("<div style='background:#030508; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Storyboard not found.</div>");
}

// Find Previous and Next Storyboard IDs
$stmtPrev = $pdo->prepare("SELECT id FROM storyboards WHERE id < ? AND is_archived = 0 ORDER BY id DESC LIMIT 1");
$stmtPrev->execute([$sb['id']]);
$prevSbId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare("SELECT id FROM storyboards WHERE id > ? AND is_archived = 0 ORDER BY id ASC LIMIT 1");
$stmtNext->execute([$sb['id']]);
$nextSbId = $stmtNext->fetchColumn();

// 2. Fetch Storyboard Frames
$stmtFrames = $pdo->prepare("
    SELECT sf.id as sf_id, sf.frame_id, sf.name as sb_name, sf.description as sb_desc, sf.filename as sb_filename, sf.sort_order,
           f.filename as orig_filename, f.name as frame_name, f.prompt as frame_prompt, f.entity_type, f.entity_id,
           sa.classification, sa.thematics, sa.entities, sa.recommendations, sa.scoring
    FROM storyboard_frames sf
    LEFT JOIN frames f ON sf.frame_id = f.id
    LEFT JOIN sketch_analysis sa ON (f.entity_type = 'sketches' AND f.entity_id = sa.sketch_id)
    WHERE sf.storyboard_id = ?
    ORDER BY sf.sort_order ASC
");
$stmtFrames->execute([$sb['id']]);
$rawFrames = $stmtFrames->fetchAll(PDO::FETCH_ASSOC);

// 3. Resolve True Entity Names and Descriptions (The Entity Chain)
$entityRequests =[];
foreach ($rawFrames as $row) {
    if (!empty($row['entity_type']) && !empty($row['entity_id'])) {
        $entityRequests[$row['entity_type']][] = $row['entity_id'];
    }
}

$entityData =[];
foreach ($entityRequests as $eType => $eIds) {
    $allowedTables =['sketches', 'characters', 'locations', 'spawns', 'generatives', 'animas', 'artifacts', 'lotations', 'character_poses', 'character_anima_poses', 'character_expressions'];
    if (in_array($eType, $allowedTables)) {
        $uniqueIds = array_unique($eIds);
        $inClause = implode(',', array_fill(0, count($uniqueIds), '?'));
        
        $nameCol = 'name';
        $descCol = 'description';
        if ($eType === 'episodes') {
            $nameCol = 'title';
            $descCol = 'logline';
        } elseif ($eType === 'scene_hooks') {
            $nameCol = 'title';
        }
        
        try {
            $stmtEnt = $pdo->prepare("SELECT id, $nameCol as ent_name, $descCol as ent_desc FROM `$eType` WHERE id IN ($inClause)");
            $stmtEnt->execute($uniqueIds);
            foreach ($stmtEnt->fetchAll(PDO::FETCH_ASSOC) as $eRow) {
                $entityData[$eType][$eRow['id']] =[
                    'name' => $eRow['ent_name'] ?? '',
                    'description' => $eRow['ent_desc'] ?? ''
                ];
            }
        } catch (Exception $e) {
            // Ignore gracefully if table/column missing
        }
    }
}

// 4. Hydrate Final Frame Data
$frames = [];
$exportRawFrames =[];

foreach ($rawFrames as $row) {
    $exportNode = $row;
    foreach (['entities', 'classification', 'thematics', 'recommendations', 'scoring'] as $jsonCol) {
        if (isset($exportNode[$jsonCol]) && is_string($exportNode[$jsonCol])) {
            $exportNode[$jsonCol] = json_decode($exportNode[$jsonCol], true) ?: $exportNode[$jsonCol];
        }
    }
    $exportRawFrames[] = $exportNode;

    $eType = $row['entity_type'] ?? '';
    $eId = $row['entity_id'] ?? '';
    $entName = $entityData[$eType][$eId]['name'] ?? '';
    $entDesc = $entityData[$eType][$eId]['description'] ?? '';

    $name = !empty($entName) ? $entName : (!empty($row['sb_name']) ? $row['sb_name'] : (!empty($row['frame_name']) ? $row['frame_name'] : 'Storyboard Frame'));
    $desc = !empty($entDesc) ? $entDesc : (!empty($row['sb_desc']) ? $row['sb_desc'] : (!empty($row['frame_prompt']) ? $row['frame_prompt'] : ''));
    $thumb = !empty($row['orig_filename']) ? $row['orig_filename'] : $row['sb_filename'];

    $curation = [];
    if ($row['entity_type'] === 'sketches' && !empty($row['classification'])) {
        $scoringObj = json_decode($row['scoring'] ?? '{}', true);
        $curation =[
            'class' => json_decode($row['classification'] ?? '{}', true),
            'themes' => json_decode($row['thematics'] ?? '{}', true),
            'entities' => json_decode($row['entities'] ?? '{}', true),
            'recs' => json_decode($row['recommendations'] ?? '{}', true),
            'score' => $scoringObj['overall_quality'] ?? 0
        ];
    }

    $frames[] =[
        'id' => $row['sf_id'],
        '_active_frame_id' => $row['frame_id'],
        'thumb' => $thumb,
        'name' => $name,
        'desc' => $desc,
        'curation' => $curation
    ];
}

// ==============================================================================
// ZIP EXPORT LOGIC SETUP
// ==============================================================================
$isExport = defined('ADMIN_MODE') && ADMIN_MODE && isset($_GET['export']);
$frameDir = 'storyboard_' . $sb['id'];
$imagesToDownload =[];

if ($isExport) {
    foreach ($frames as &$frame) {
        $origUrl = $frame['thumb'];
        if ($origUrl) {
            $filename = basename($origUrl);
            $frame['thumb'] = $frameDir . '/' . $filename;
            $imagesToDownload[$filename] = $origUrl;
        }
    }
    unset($frame);
}

// Sanitize for JS UI
$storyboardJson = json_encode([
    'id' => $sb['id'],
    'name' => $sb['name'],
    'description' => $sb['description'],
    'frames' => $frames
]);

$exportObject =[
    'storyboard' => $sb,
    'frames' => $exportRawFrames
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
    <title><?= htmlspecialchars($sb['name']) ?> - Cinematic Viewer</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700;900&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>

    <style>
        :root {
            --bg: #030508;
            --accent: #10b981;
            --accent-glow: rgba(16, 185, 129, 0.4);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(15, 23, 42, 0.65);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body, html {
            margin: 0; padding: 0;
            width: 100%; height: 100%;
            background: var(--bg);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            overscroll-behavior: none;
        }

        /* --- IMMERSIVE MEDIA LAYER --- */
        #viewport {
            position: absolute;
            inset: 0;
            perspective: 1000px;
            background: #000;
        }

        .media-plane {
            position: absolute;
            /* Reduced Zoom */
            inset: -2%; 
            width: 104%; height: 104%;
            object-fit: cover;
            opacity: 0;
            will-change: transform, opacity;
            pointer-events: none;
        }

        /* Lighter Vignette Overlay */
        .vignette {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.1) 50%, rgba(0,0,0,0.85) 100%);
            z-index: 5;
            pointer-events: none;
        }

        /* --- UI LAYER --- */
        #ui-layer {
            position: absolute;
            inset: 0;
            z-index: 10;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px 24px 30px 24px;
            pointer-events: none; 
        }

        /* Progress Bar */
        .progress-container {
            display: flex; gap: 4px; width: 100%;
        }
        .progress-segment {
            flex: 1; height: 3px; background: rgba(255,255,255,0.2);
            border-radius: 2px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; width: 0%; background: var(--text-main);
            border-radius: 2px; transform-origin: left;
        }

        /* Header Info & Nav */
        .header-meta {
            margin-top: 15px;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .header-meta-left { display: flex; flex-direction: column; gap: 10px; }
        
        .seq-title {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.2em; font-weight: 700; color: var(--accent); margin: 0;
        }
        .frame-counter {
            font-family: monospace; font-size: 0.85rem; color: rgba(255,255,255,0.5);
        }

        /* Action Buttons */
        .header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; pointer-events: auto; }
        .seq-nav {
            display: flex; align-items: center; background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 2px;
            backdrop-filter: blur(4px);
        }
        .btn-nav-small {
            background: transparent; border: none; color: var(--text-muted);
            width: 24px; height: 24px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1rem; transition: 0.2s; line-height: 1; padding-bottom: 2px;
        }
        .btn-nav-small:hover:not(:disabled) { color: #fff; background: rgba(255,255,255,0.1); }
        .btn-nav-small:disabled { opacity: 0.3; cursor: not-allowed; }
        
        .seq-id-input {
            width: 40px; background: transparent; border: none;
            color: var(--accent); text-align: center; font-family: monospace;
            font-size: 0.75rem; font-weight: 700; -moz-appearance: textfield;
        }
        .seq-id-input::-webkit-outer-spin-button, .seq-id-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .seq-id-input:focus { outline: none; color: #fff; border-bottom: 1px solid var(--accent); }

        .btn-download {
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.4);
            color: var(--accent); padding: 4px 10px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 4px; text-decoration: none;
            transition: all 0.3s ease; backdrop-filter: blur(4px); white-space: nowrap;
        }
        .btn-download:hover { background: var(--accent); color: #000; box-shadow: 0 0 10px var(--accent-glow); }

        /* Top-Right Iframe Button */
        .f-view-btn { 
            position: absolute; top: 60px; right: 24px; 
            width: 36px; height: 36px; 
            background: rgba(0,0,0,0.6); color: #fff; 
            border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            cursor: pointer; z-index: 50; opacity: 0.8; transition: all 0.2s; 
            font-size: 16px; backdrop-filter: blur(5px); pointer-events: auto;
        }
        .f-view-btn:hover { opacity: 1; background: var(--accent); color: #000; border-color: var(--accent); transform: scale(1.1); }

        /* Frame Text Data */
        .text-stage { margin-bottom: 20px; }
        .sketch-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 6vw, 3.5rem); margin: 0 0 10px 0; line-height: 1.1;
            display: flex; flex-wrap: wrap; text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }
        .sketch-title .word { display: inline-block; white-space: pre; overflow: hidden; }
        .sketch-title .letter { display: inline-block; transform: translateY(100%); opacity: 0; }

        .sketch-desc {
            font-size: 0.95rem; line-height: 1.6; color: var(--text-main);
            max-width: 600px; margin: 0; opacity: 0; transform: translateY(20px);
            text-shadow: 0 2px 10px rgba(0,0,0,0.9); pointer-events: auto;
            max-height: 30vh; overflow-y: auto; 
            -ms-overflow-style: none; scrollbar-width: none;
        }
        .sketch-desc::-webkit-scrollbar { display: none; }
        
        .desc-hidden { display: none; opacity: 0; }
        .read-more-toggle {
            color: var(--accent); cursor: pointer;
            font-weight: 600; font-size: 0.9rem;
            margin-left: 6px; transition: color 0.2s; white-space: nowrap;
        }
        .read-more-toggle:hover { color: #fff; }

        /* --- CONTROLS --- */
        .controls-row {
            display: flex; justify-content: space-between; align-items: center; pointer-events: auto;
        }
        .btn-nav {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: white; width: 48px; height: 48px; border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            cursor: pointer; backdrop-filter: blur(8px); transition: all 0.3s;
        }
        .btn-nav:hover { background: rgba(255,255,255,0.15); transform: scale(1.05); }

        .btn-analysis {
            background: var(--glass-bg); border: 1px solid var(--accent); color: var(--accent);
            padding: 12px 24px; border-radius: 30px; font-size: 0.85rem; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase; cursor: pointer;
            backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.5); transition: all 0.3s;
        }
        .btn-analysis:hover { background: var(--accent); color: #000; box-shadow: 0 0 20px var(--accent-glow); }

        /* Swipe Zones for invisible touch navigation */
        .swipe-zone {
            position: absolute; top: 100px; bottom: 150px;
            width: 40%; z-index: 5; pointer-events: auto;
        }
        .swipe-left { left: 0; }
        .swipe-right { right: 0; }

        /* --- ANALYSIS PANEL --- */
        #analysis-panel {
            position: absolute; z-index: 20; background: var(--glass-bg);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border); padding: 30px; overflow-y: auto; pointer-events: auto;
            transform: translateY(100%); opacity: 0; display: flex; flex-direction: column; gap: 25px;
        }

        @media (max-width: 768px) {
            #analysis-panel {
                bottom: 0; left: 0; right: 0; height: 75vh;
                border-top-left-radius: 24px; border-top-right-radius: 24px; border-bottom: none;
            }
        }
        @media (min-width: 769px) {
            #analysis-panel {
                top: 0; bottom: 0; right: 0; width: 450px;
                transform: translateX(100%); border-left: 1px solid var(--glass-border);
            }
        }

        .panel-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; }
        .panel-title { margin: 0; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.05em; display: flex; align-items: center; gap: 10px; }
        .score-badge { background: var(--accent); color: #000; padding: 4px 10px; border-radius: 12px; font-weight: 900; font-size: 0.9rem; }
        .btn-close { background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s; }
        .btn-close:hover { opacity: 1; }

        .stat-group { opacity: 0; transform: translateY(15px); }
        .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 8px; }
        
        .pill-container { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; color: #e2e8f0;
            white-space: normal; word-break: break-word; max-width: 100%; line-height: 1.4; display: inline-block;
        }
        .pill.hl { border-color: rgba(16, 185, 129, 0.4); color: #34d399; background: rgba(16, 185, 129, 0.05); }
        .pill.char { border-color: rgba(245, 159, 11, 0.3); color: #fbbf24; }
        
        .recs-box {
            background: rgba(0,0,0,0.3); border-left: 3px solid #8b5cf6; padding: 12px 15px;
            font-size: 0.85rem; line-height: 1.5; color: #cbd5e1; border-radius: 0 8px 8px 0;
        }

        /* --- Iframe Detail Modal --- */
        .view-modal { 
            position: fixed; inset: 0; background: rgba(0,0,0,0.95); 
            z-index: 100000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);
        }
        .view-modal.active { display: flex; }
        .view-modal-content { 
            width: 95vw; height: 95vh; background: #000; position: relative; 
            border: 1px solid var(--glass-border); box-shadow: 0 0 40px rgba(0,0,0,0.8); border-radius: 8px; overflow: hidden;
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

    <!-- 1. Media Viewport -->
    <div id="viewport">
        <!-- Two image planes for crossfading -->
        <img id="img-a" class="media-plane" src="" alt="">
        <img id="img-b" class="media-plane" src="" alt="">
        <div class="vignette"></div>
    </div>

    <!-- Invisible swipe zones for mobile -->
    <div class="swipe-zone swipe-left" onclick="prevFrame()"></div>
    <div class="swipe-zone swipe-right" onclick="nextFrame()"></div>

    <!-- 2. Main UI Layer -->
    <div id="ui-layer">
        
        <?php if (!$isExport): ?>
        <!-- Top-Right Detail Button (Moved into UI Layer) -->
        <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openCurrentFrame();" title="View Frame Detail">
            <i class="bi bi-arrows-fullscreen"></i>
        </div>
        <?php endif; ?>

        <!-- Top: Progress & Sequence Meta -->
        <div>
            <div class="progress-container" id="progress-container">
                <!-- Segments injected via JS -->
            </div>
            <div class="header-meta">
                <div class="header-meta-left">
                    <h1 class="seq-title" id="ui-seq-name">Storyboard</h1>
                    
                    <?php if (!$isExport): ?>
                    <div class="header-actions">
                        <span class="pill hl" style="font-family: monospace; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; margin-right: 4px;" title="Total Frames in Storyboard">
                            <?= count($frames) ?> FRAMES
                        </span>

                        <div class="seq-nav">
                            <button class="btn-nav-small" onclick="navSeq(<?= $prevSbId ?: 'null' ?>)" <?= !$prevSbId ? 'disabled' : '' ?> title="Previous Storyboard">&#8249;</button>
                            <input type="number" class="seq-id-input" value="<?= $sb['id'] ?>" onchange="jumpToSeq(this.value)" title="Current Storyboard ID (Type to jump)">
                            <button class="btn-nav-small" onclick="navSeq(<?= $nextSbId ?: 'null' ?>)" <?= !$nextSbId ? 'disabled' : '' ?> title="Next Storyboard">&#8250;</button>
                        </div>
                        
                        <button class="btn-download" onclick="downloadStoryboardJSON()" title="Download Storyboard as JSON">
                            <i class="bi bi-filetype-json"></i> JSON
                        </button>
                        
                        <?php if (defined('ADMIN_MODE') && ADMIN_MODE): ?>
                        <a href="?id=<?= $sb['id'] ?>&export=1" class="btn-download" title="Download Standalone ZIP" style="text-decoration:none;">
                            <i class="bi bi-file-earmark-zip"></i> ZIP
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="frame-counter"><span id="curr-count">1</span> / <span id="tot-count">10</span></div>
            </div>
        </div>

        <!-- Bottom: Text & Controls -->
        <div>
            <div class="text-stage">
                <h2 class="sketch-title" id="ui-title"></h2>
                <div class="sketch-desc" id="ui-desc"></div>
            </div>
            
            <div class="controls-row">
                <div style="display:flex; gap:10px;">
                    <button class="btn-nav" onclick="prevFrame()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="btn-nav" onclick="nextFrame()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
                <button class="btn-analysis" id="btn-analysis" style="display:none;" onclick="toggleAnalysis()">
                    <svg style="vertical-align:middle; margin-right:6px;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Analysis
                </button>
            </div>
        </div>
    </div>

    <!-- 3. Glassmorphic Analysis Panel -->
    <div id="analysis-panel">
        <div class="panel-header">
            <h3 class="panel-title">Frame Intel <span class="score-badge" id="pan-score">0.0</span></h3>
            <button class="btn-close" onclick="toggleAnalysis()">&times;</button>
        </div>

        <div class="stat-group" id="grp-class">
            <div class="stat-label">Classification</div>
            <div class="pill-container" id="pan-class"></div>
        </div>

        <div class="stat-group" id="grp-themes">
            <div class="stat-label">Themes Present</div>
            <div class="pill-container" id="pan-themes"></div>
        </div>

        <div class="stat-group" id="grp-entities">
            <div class="stat-label">Entities In Frame</div>
            <div class="pill-container" id="pan-entities"></div>
        </div>

        <div class="stat-group" id="grp-recs">
            <div class="stat-label">Director's Notes (AI)</div>
            <div class="recs-box" id="pan-recs"></div>
        </div>
    </div>

    <!-- 4. Iframe Detail Modal -->
    <div class="view-modal" id="viewModal">
        <div class="view-modal-content">
            <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
            <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
        </div>
    </div>

    <!-- Inject Sequence Data Dynamically -->
    <?php if ($isExport): ?>
        <script src="<?= $frameDir ?>/data_<?= $sb['id'] ?>.js"></script>
    <?php else: ?>
        <script>
            const storyboardData = <?= $storyboardJson ?>;
            const rawExportData = <?= $exportJson ?>;
        </script>
    <?php endif; ?>

    <script>
        const frames = storyboardData.frames ||[];
        
        let currentIndex = -1;
        let isAnimating = false;
        let isAnalysisOpen = false;
        let activePlane = 'img-a';

        const elImgA = document.getElementById('img-a');
        const elImgB = document.getElementById('img-b');
        const elTitle = document.getElementById('ui-title');
        const elDesc = document.getElementById('ui-desc');
        const elCurr = document.getElementById('curr-count');
        const elTot = document.getElementById('tot-count');
        const elProgBox = document.getElementById('progress-container');
        const panel = document.getElementById('analysis-panel');
        const btnAnalysis = document.getElementById('btn-analysis');

        const isMobile = window.innerWidth <= 768;

        function init() {
            if (frames.length === 0) return;
            document.getElementById('ui-seq-name').innerText = storyboardData.name || 'Untitled';
            elTot.innerText = frames.length;

            for (let i = 0; i < frames.length; i++) {
                const seg = document.createElement('div');
                seg.className = 'progress-segment';
                seg.innerHTML = `<div class="progress-fill" id="prog-${i}"></div>`;
                elProgBox.appendChild(seg);
            }

            goToFrame(0, 1);
        }

        function splitText(text) {
            const words = text.split(' ');
            return words.map(word => {
                const letters = word.split('').map(l => `<span class="letter">${l}</span>`).join('');
                return `<span class="word">${letters}</span>`;
            }).join(' ');
        }

        function goToFrame(index, direction = 1) {
            if (isAnimating || index < 0 || index >= frames.length || index === currentIndex) return;
            isAnimating = true;

            const frame = frames[index];
            const oldIndex = currentIndex;
            currentIndex = index;

            elCurr.innerText = index + 1;
            updateProgress(oldIndex, index);

            const oldPlane = activePlane === 'img-a' ? elImgA : elImgB;
            const newPlane = activePlane === 'img-a' ? elImgB : elImgA;
            activePlane = newPlane.id;

            const imgPreload = new Image();
            const startAnimation = () => {
                newPlane.src = frame.thumb;
                newPlane.style.zIndex = 2;
                oldPlane.style.zIndex = 1;

                elTitle.innerHTML = splitText(frame.name || 'Untitled');
                
                // Handle Text Truncation dynamically
                const descText = (frame.desc || '').trim();
                const words = descText.split(/\s+/);
                if (words.length > 25) {
                    const visiblePart = words.slice(0, 25).join(' ');
                    const hiddenPart = words.slice(25).join(' ');
                    elDesc.innerHTML = `
                        <span class="desc-visible">${visiblePart}</span><span class="desc-ellipsis">...</span>
                        <span class="desc-hidden" style="display:none; opacity:0;"> ${hiddenPart}</span>
                        <span class="read-more-toggle" onclick="toggleDesc(event)">Read more</span>
                    `;
                } else {
                    elDesc.innerHTML = descText;
                }

                // Handle Analysis Button Visibility
                const hasCuration = frame.curation && Object.keys(frame.curation).length > 0;
                if (hasCuration) {
                    btnAnalysis.style.display = 'flex';
                    updateAnalysisData(frame);
                } else {
                    btnAnalysis.style.display = 'none';
                    if (isAnalysisOpen) toggleAnalysis(); 
                }

                const tl = anime.timeline({ 
                    easing: 'easeOutExpo', 
                    complete: () => { 
                        isAnimating = false; 
                        const nextIndex = direction === 1 ? index + 1 : index - 1;
                        if (nextIndex >= 0 && nextIndex < frames.length) {
                            const nextPreload = new Image();
                            nextPreload.src = frames[nextIndex].thumb;
                        }
                    } 
                });

                // REDUCED ZOOM: scale 1.05 instead of 1.15
                tl.add({ targets: newPlane, opacity: [0, 1], scale:[1.05, 1], duration: 1200 }, 0);
                
                if (oldIndex !== -1) {
                    tl.add({ targets: oldPlane, opacity: [1, 0], scale:[1, 1.02], duration: 1200 }, 0);
                }

                // REDUCED INFINITE PAN: scale 1.02 instead of 1.05
                anime({ targets: newPlane, scale:[1, 1.02], duration: 10000, easing: 'linear' });

                tl.add({
                    targets: '#ui-title .letter', translateY:['100%', '0%'], opacity: [0, 1],
                    duration: 800, easing: 'easeOutQuint', delay: anime.stagger(15)
                }, 200);

                tl.add({ targets: elDesc, translateY:[20, 0], opacity:[0, 1], duration: 800, easing: 'easeOutQuad' }, 400);

                if (isAnalysisOpen && hasCuration) {
                    anime({
                        targets: '.stat-group', opacity: [0, 1], translateY: [10, 0],
                        duration: 400, delay: anime.stagger(100), easing: 'easeOutQuad'
                    });
                }
            };

            imgPreload.onload = startAnimation;
            imgPreload.onerror = startAnimation;
            imgPreload.src = frame.thumb;
        }

        window.toggleDesc = (e) => {
            e.stopPropagation();
            const hidden = elDesc.querySelector('.desc-hidden');
            const ellipsis = elDesc.querySelector('.desc-ellipsis');
            const btn = elDesc.querySelector('.read-more-toggle');

            if (!hidden) return;

            if (hidden.style.display === 'none' || hidden.style.display === '') {
                hidden.style.display = 'inline';
                ellipsis.style.display = 'none';
                btn.innerText = ' Show less';
                anime({ targets: hidden, opacity:[0, 1], duration: 400, easing: 'easeOutQuad' });
            } else {
                hidden.style.display = 'none';
                ellipsis.style.display = 'inline';
                btn.innerText = ' Read more';
                hidden.style.opacity = 0; 
            }
        };

        function updateProgress(oldIdx, newIdx) {
            for(let i=0; i<frames.length; i++) {
                const fill = document.getElementById(`prog-${i}`);
                if (i < newIdx) fill.style.width = '100%';
                else if (i > newIdx) fill.style.width = '0%';
            }
            anime({ targets: `#prog-${newIdx}`, width:['0%', '100%'], duration: 800, easing: 'easeOutQuad' });
        }

        function nextFrame() {
            if (currentIndex < frames.length - 1) goToFrame(currentIndex + 1, 1);
            else anime({ targets: '#viewport', translateX:[0, -10, 10, -5, 5, 0], duration: 400 });
        }

        function prevFrame() {
            if (currentIndex > 0) goToFrame(currentIndex - 1, -1);
            else anime({ targets: '#viewport', translateX:[0, 10, -10, 5, -5, 0], duration: 400 });
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') nextFrame();
            if (e.key === 'ArrowLeft') prevFrame();
        });

        function updateAnalysisData(frame) {
            const cur = frame.curation || {};
            document.getElementById('pan-score').innerText = (cur.score || 0).toFixed(1);
            
            const cls = cur.class || {};
            let clsHtml = '';
            if(cls.narrative_function) clsHtml += `<span class="pill hl">${cls.narrative_function}</span>`;
            if(cls.emotional_tone) clsHtml += `<span class="pill">${cls.emotional_tone}</span>`;
            document.getElementById('pan-class').innerHTML = clsHtml || '-';
            document.getElementById('grp-class').style.display = clsHtml ? 'block' : 'none';

            const thm = cur.themes || {};
            let thmHtml = '';
            if(thm.primary_themes && Array.isArray(thm.primary_themes)) {
                thm.primary_themes.forEach(t => thmHtml += `<span class="pill">${t}</span>`);
            }
            document.getElementById('pan-themes').innerHTML = thmHtml || '-';
            document.getElementById('grp-themes').style.display = thmHtml ? 'block' : 'none';

            const ent = cur.entities || {};
            let entHtml = '';
            if(ent.characters && Array.isArray(ent.characters)) {
                ent.characters.forEach(c => entHtml += `<span class="pill char" style="border-color:rgba(245, 158, 11, 0.4); color:#fbbf24;">👤 ${c}</span>`);
            }
            if(ent.locations && Array.isArray(ent.locations)) {
                ent.locations.forEach(l => entHtml += `<span class="pill">📍 ${l}</span>`);
            }
            document.getElementById('pan-entities').innerHTML = entHtml || '-';
            document.getElementById('grp-entities').style.display = entHtml ? 'block' : 'none';

            const recs = cur.recs || {};
            document.getElementById('pan-recs').innerText = recs.potential_use || '-';
            document.getElementById('grp-recs').style.display = recs.potential_use ? 'block' : 'none';
        }

        function toggleAnalysis() {
            isAnalysisOpen = !isAnalysisOpen;
            const timeline = anime.timeline({ easing: 'easeInOutExpo' });

            if (isAnalysisOpen) {
                timeline.add({ targets: '#viewport', scale: 0.92, opacity: 0.6, duration: 600 }, 0);
                const slideProp = isMobile ? { translateY:['100%', '0%'] } : { translateX:['100%', '0%'] };
                timeline.add({ targets: panel, ...slideProp, opacity:[0, 1], duration: 600 }, 0);
                timeline.add({
                    targets: '.stat-group', opacity: [0, 1], translateY: [20, 0],
                    delay: anime.stagger(100), duration: 600, easing: 'easeOutCubic'
                }, 300);
            } else {
                const slideProp = isMobile ? { translateY: '100%' } : { translateX: '100%' };
                timeline.add({ targets: panel, ...slideProp, opacity: 0, duration: 500 }, 0);
                timeline.add({ targets: '#viewport', scale: 1, opacity: 1, duration: 600 }, 0);
            }
        }

        // Export Logic
        window.downloadStoryboardJSON = () => {
            if (typeof rawExportData === 'undefined') return;
            const dataStr = JSON.stringify(rawExportData, null, 2);
            const blob = new Blob([dataStr], { type: "application/json" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            let safeName = (storyboardData.name || 'storyboard').replace(/[^a-z0-9]/gi, '_').toLowerCase();
            a.download = safeName + '_export.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        };

        window.navSeq = (id) => {
            if (id) window.location.href = '?id=' + id;
        };
        window.jumpToSeq = (id) => {
            const cleanId = parseInt(id);
            if (!isNaN(cleanId) && cleanId > 0) {
                window.location.href = '?id=' + cleanId;
            }
        };

        // Modal Logic
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
            if (currentIndex === -1) return;
            const f = frames[currentIndex];
            let targetId = f._active_frame_id;
            
            if (targetId) {
                openFrameModal(targetId);
            } else {
                alert("No original frame is mapped to this storyboard image.");
            }
        };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const viewModal = document.getElementById('viewModal');
                if (viewModal && viewModal.classList.contains('active')) closeFrameModal();
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
    $zipName = sys_get_temp_dir() . '/storyboard_' . $sb['id'] . '_' . time() . '.zip';
    
    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $zip->addEmptyDir($frameDir);
        $zip->addFromString("storyboard_{$sb['id']}.html", $htmlContent);
        
        $jsFileContent = "const storyboardData = " . $storyboardJson . ";\n\n";
        $jsFileContent .= "const rawExportData = " . $exportJson . ";\n";
        $zip->addFromString($frameDir . '/data_' . $sb['id'] . '.js', $jsFileContent);
        
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
                    "ssl" =>[
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
        
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=standalone_storyboard_' . $sb['id'] . '.zip');
        header('Content-Length: ' . filesize($zipName));
        readfile($zipName);
        unlink($zipName);
        exit;
    } else {
        die("Failed to create ZIP file.");
    }
} else {
    echo $htmlContent;
}
?>