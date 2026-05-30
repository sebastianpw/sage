<?php
// public/animejstory.php
// Showrunner - The Split-Cinematic Storyboard Gallery
// A breathtaking, non-overlapping viewer using Anime.js adapted for Storyboards.
// Features: Original Frame Resolution, Dynamic Entity Analysis, JSON Export, ZIP Export.

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
    die("<div style='background:#050508; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Storyboard not found.</div>");
}

// Find Previous and Next Storyboard IDs
$stmtPrev = $pdo->prepare("SELECT id FROM storyboards WHERE id < ? AND is_archived = 0 ORDER BY id DESC LIMIT 1");
$stmtPrev->execute([$sb['id']]);
$prevSbId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare("SELECT id FROM storyboards WHERE id > ? AND is_archived = 0 ORDER BY id ASC LIMIT 1");
$stmtNext->execute([$sb['id']]);
$nextSbId = $stmtNext->fetchColumn();

// 2. Fetch and Hydrate Storyboard Frames
// We join with the original `frames` table to get the true filename, 
// and optionally join `sketch_analysis` if the frame belongs to a sketch.
$stmtFrames = $pdo->prepare("
    SELECT sf.id as sf_id, sf.frame_id, sf.name as sb_name, sf.description as sb_desc, sf.filename as sb_filename, sf.sort_order,
           f.filename as orig_filename, f.name as frame_name, f.prompt as frame_prompt, f.entity_type, f.entity_id,
           sa.classification, sa.thematics, sa.entities, sa.recommendations
    FROM storyboard_frames sf
    LEFT JOIN frames f ON sf.frame_id = f.id
    LEFT JOIN sketch_analysis sa ON (f.entity_type = 'sketches' AND f.entity_id = sa.sketch_id)
    WHERE sf.storyboard_id = ?
    ORDER BY sf.sort_order ASC
");
$stmtFrames->execute([$sb['id']]);
$rawFrames = $stmtFrames->fetchAll(PDO::FETCH_ASSOC);

$frames = [];
$exportRawFrames =[];

foreach ($rawFrames as $row) {
    // Keep raw data for JSON export
    $exportNode = $row;
    
    // Decode JSON columns for cleaner export
    foreach (['entities', 'classification', 'thematics', 'recommendations'] as $jsonCol) {
        if (isset($exportNode[$jsonCol]) && is_string($exportNode[$jsonCol])) {
            $exportNode[$jsonCol] = json_decode($exportNode[$jsonCol], true) ?: $exportNode[$jsonCol];
        }
    }
    $exportRawFrames[] = $exportNode;

    // Prepare UI Data
    // Always operate on the original filename if it exists, otherwise fallback to the storyboard copy
    $thumb = !empty($row['orig_filename']) ? $row['orig_filename'] : $row['sb_filename'];
    
    // Fallback chain for Name and Description
    $name = !empty($row['sb_name']) ? $row['sb_name'] : (!empty($row['frame_name']) ? $row['frame_name'] : 'Storyboard Frame');
    $desc = !empty($row['sb_desc']) ? $row['sb_desc'] : (!empty($row['frame_prompt']) ? $row['frame_prompt'] : '');

    $curation = [];
    if ($row['entity_type'] === 'sketches') {
        $curation = [
            'class' => json_decode($row['classification'] ?? '{}', true),
            'themes' => json_decode($row['thematics'] ?? '{}', true),
            'entities' => json_decode($row['entities'] ?? '{}', true),
            'recs' => json_decode($row['recommendations'] ?? '{}', true),
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
    <title><?= htmlspecialchars($sb['name']) ?> - Storyboard Gallery</title>

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
            white-space: normal; word-break: break-word;
            max-width: 100%; line-height: 1.4; display: inline-block;
        }
        .pill.hl { border-color: rgba(16, 185, 129, 0.4); color: #34d399; background: rgba(16, 185, 129, 0.05); }
        .pill.char { border-color: rgba(245, 159, 11, 0.3); color: #fbbf24; }

        .director-note {
            background: rgba(0,0,0,0.4); border-left: 2px solid #8b5cf6;
            padding: 10px 12px; font-size: 0.85rem; line-height: 1.5;
            color: #e2e8f0; font-style: italic; border-radius: 0 6px 6px 0;
        }

        /* --- Beat Navigation Actions --- */
        .btn-beat-action {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted); padding: 4px 10px; border-radius: 12px;
            font-size: 0.7rem; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            transition: all 0.2s ease; text-transform: uppercase; backdrop-filter: blur(4px);
        }
        .btn-beat-action:hover {
            background: rgba(16, 185, 129, 0.1); color: var(--accent);
            border-color: rgba(16, 185, 129, 0.4); box-shadow: 0 0 10px var(--accent-glow);
        }

        /* --- Intro Header & Navigation Controls --- */
        .seq-header {
            margin-bottom: 80px; padding-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .seq-header-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;
        }
        .header-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

        .seq-nav {
            display: flex; align-items: center; background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 2px;
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
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.4);
            color: var(--accent); padding: 6px 14px; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            transition: all 0.3s ease; backdrop-filter: blur(4px); white-space: nowrap; text-decoration: none;
        }
        .btn-download:hover { background: var(--accent); color: #000; box-shadow: 0 0 15px var(--accent-glow); }

        /* --- Iframe Detail Modal --- */
        .f-view-btn { 
            position: absolute; top: 15px; right: 15px; 
            width: 40px; height: 40px; 
            background: rgba(0,0,0,0.6); color: #fff; 
            border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            cursor: pointer; z-index: 50; opacity: 0.8; transition: all 0.2s; 
            font-size: 18px; backdrop-filter: blur(5px);
        }
        .media-frame:hover .f-view-btn, .f-view-btn:hover { opacity: 1; background: var(--accent); color: #000; border-color: var(--accent); }

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
            position: absolute; top: 15px; right: 15px; 
            width: 40px; height: 40px; background: rgba(0,0,0,0.8); color: #fff; 
            border: 1px solid var(--glass-border); border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            cursor: pointer; font-size: 24px; z-index: 200; transition: all 0.2s; backdrop-filter: blur(5px);
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
                        <p class="sub">Storyboard</p>
                        <h1 id="ui-seq-name">Title</h1>
                    </div>
                    <div class="header-actions">
                        
                        <span class="pill hl" style="font-family: monospace; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; margin-right: 4px;" title="Total Frames in Storyboard">
                            <?= count($frames) ?> FRAMES
                        </span>

                        <?php if (!$isExport): ?>
                        <div class="seq-nav">
                            <button class="btn-nav-small" onclick="navSeq(<?= $prevSbId ?: 'null' ?>)" <?= !$prevSbId ? 'disabled' : '' ?> title="Previous Storyboard">&#8249;</button>
                            <input type="number" class="seq-id-input" value="<?= $sb['id'] ?>" onchange="jumpToSeq(this.value)" title="Current Storyboard ID (Type to jump)">
                            <button class="btn-nav-small" onclick="navSeq(<?= $nextSbId ?: 'null' ?>)" <?= !$nextSbId ? 'disabled' : '' ?> title="Next Storyboard">&#8250;</button>
                        </div>

                        <button class="btn-download" onclick="downloadStoryboardJSON()" title="Download Full Storyboard as JSON">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            <span>JSON</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (defined('ADMIN_MODE') && ADMIN_MODE && !$isExport): ?>
                        <a href="?id=<?= $sb['id'] ?>&export=1" class="btn-download" title="Download Standalone ZIP" style="text-decoration:none;">
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

    <!-- Inject Data Dynamically -->
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

        let activeIndex = -1;
        let activePlane = 'img-a';
        let continuousPanAnim = null; 

        const elImgA = document.getElementById('img-a');
        const elImgB = document.getElementById('img-b');
        const elAmbA = document.getElementById('amb-a');
        const elAmbB = document.getElementById('amb-b');
        const thread = document.getElementById('story-thread');
        const beatsContainer = document.getElementById('beats-container');

        // --- 1. Init UI ---
        function init() {
            document.getElementById('ui-seq-name').innerText = storyboardData.name || 'Untitled';
            document.getElementById('ui-seq-desc').innerText = storyboardData.description || 'Scroll through the thread below to experience the storyboard.';

            frames.forEach((frame, i) => {
                const cur = frame.curation || {};
                const cls = cur.class || {};
                const thm = cur.themes || {};
                const ent = cur.entities || {};
                const recs = cur.recs || {};

                // Only generate intel cards if the underlying entity actually had an analysis
                let hasIntel = false;
                let intelHtml = ``;

                let fnHtml = cls.narrative_function ? `<span class="pill hl">${cls.narrative_function}</span>` : '';
                let tnHtml = cls.emotional_tone ? `<span class="pill">${cls.emotional_tone}</span>` : '';
                let thmHtml = '';
                if(thm.primary_themes) thm.primary_themes.slice(0,3).forEach(t => thmHtml += `<span class="pill">${t}</span>`);
                let entHtml = '';
                if(ent.characters) ent.characters.forEach(c => entHtml += `<span class="pill char">👤 ${c}</span>`);
                if(ent.locations) ent.locations.forEach(l => entHtml += `<span class="pill">📍 ${l}</span>`);

                if(fnHtml || tnHtml || thmHtml || entHtml || recs.potential_use) {
                    hasIntel = true;
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

                const hasNext = i < frames.length - 1;
                const beat = document.createElement('div');
                beat.className = 'story-beat';
                beat.id = `beat-${i}`;
                beat.dataset.index = i;
                
                beat.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span class="beat-index" style="margin-bottom:0;">FRAME ${String(i+1).padStart(2,'0')}</span>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top:0, behavior:'smooth'})" title="Back to Top">
                                <i class="bi bi-arrow-up"></i> Top
                            </button>
                            ${hasNext ? `
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i+1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Next Frame">
                                Next <i class="bi bi-arrow-down"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    <h2 class="beat-title">${frame.name || 'Untitled Frame'}</h2>
                    <div class="beat-desc">${descHtml}</div>
                    ${intelHtml}
                `;
                beatsContainer.appendChild(beat);
            });

            if(frames.length > 0 && frames[0].thumb) {
                elImgA.src = frames[0].thumb;
                elAmbA.src = frames[0].thumb;
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
        function activateBeat(index) {
            if (index === activeIndex) return;

            document.querySelectorAll('.story-beat').forEach(b => b.classList.remove('active'));
            document.getElementById(`beat-${index}`).classList.add('active');

            if (activeIndex === -1) {
                activeIndex = index;
                startContinuousPan(elImgA);
                return;
            }

            const newFrame = frames[index];
            const scrollingDown = index > activeIndex;
            activeIndex = index;

            const oldPlane = activePlane === 'img-a' ? elImgA : elImgB;
            const newPlane = activePlane === 'img-a' ? elImgB : elImgA;
            activePlane = newPlane.id;

            const oldAmb = activePlane === 'img-b' ? elAmbA : elAmbB; 
            const newAmb = activePlane === 'img-b' ? elAmbB : elAmbA;

            if (newFrame.thumb) {
                newPlane.src = newFrame.thumb;
                newAmb.src = newFrame.thumb;
            }
            newPlane.style.zIndex = 2;
            oldPlane.style.zIndex = 1;

            if(continuousPanAnim) continuousPanAnim.pause();

            const yOffset = scrollingDown ? 15 : -15;
            const rotateDir = scrollingDown ? 4 : -4;

            anime.set(newPlane, { opacity: 0, scale: 1.15, translateX: 0, translateY: 0 });

            const tl = anime.timeline({ easing: 'easeOutCubic' });

            anime({ targets: newAmb, opacity: 1, duration: 1500, easing: 'linear' });
            anime({ targets: oldAmb, opacity: 0, duration: 1500, easing: 'linear' });

            tl.add({ targets: document.getElementById('media-frame'), scale:[0.98, 1], duration: 800 }, 0);
            tl.add({ targets: newPlane, opacity:[0, 1], translateY:[yOffset+'%', '0%'], scale:[1.1, 1], rotateZ:[rotateDir, 0], duration: 1000 }, 0);
            tl.add({ targets: oldPlane, opacity:[1, 0], translateY:['0%', (-yOffset)+'%'], scale:[1, 0.9], duration: 900 }, 0);

            tl.finished.then(() => startContinuousPan(newPlane));
        }

        function startContinuousPan(targetElement) {
            const panX = (Math.random() > 0.5 ? 2 : -2) + '%';
            const panY = (Math.random() > 0.5 ? 2 : -2) + '%';

            continuousPanAnim = anime({
                targets: targetElement,
                scale:[1, 1.05],
                translateX: [0, panX],
                translateY:[0, panY],
                duration: 15000,
                easing: 'linear',
                direction: 'alternate',
                loop: true
            });
        }

        // --- 5. Export Logic ---
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
            // Uses the stored active frame id from the DB join
            const targetId = f._active_frame_id;
            if (targetId) openFrameModal(targetId);
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