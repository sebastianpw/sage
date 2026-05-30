<?php
// public/seqview.php
// Showrunner V10 - Cinematic Sequence Viewer
// A breathtaking, mobile-first, Anime.js-powered immersive player for narrative sequences.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/SketchLibrary.php';

// 1. Fetch the sequence
$seqId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'auto'; // 'auto' or 'manual'

$seq = null;
if ($seqId) {
    $table = 'narrative_sequences';
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$seqId]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Fallback: load the most recent auto sequence
    $seq = $pdo->query("SELECT * FROM narrative_sequences ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$seq) {
    die("<div style='background:#000; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Sequence not found or empty.</div>");
}

// 2. Hydrate the frames
$library = new SketchLibrary($pdo);
$itemIds = json_decode($seq['sequence_data'], true) ?:[];
$frames = $library->hydrateSpecificIds($itemIds);

// Sanitize output for JS
$sequenceJson = json_encode([
    'id' => $seq['id'],
    'name' => $seq['name'],
    'description' => $seq['description'],
    'frames' => $frames
]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title><?= htmlspecialchars($seq['name']) ?> - Cinematic Viewer</title>
    
    <!-- Fonts & Anime.js -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700;900&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
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
            inset: -5%; /* Slight bleed for Ken Burns pan/scale */
            width: 110%; height: 110%;
            object-fit: cover;
            opacity: 0;
            will-change: transform, opacity;
            pointer-events: none;
        }

        /* Gradient Overlay for Text Readability */
        .vignette {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.5) 60%, rgba(0,0,0,0.95) 100%);
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
            pointer-events: none; /* Let clicks pass to swipe zones */
        }

        /* Progress Bar */
        .progress-container {
            display: flex;
            gap: 4px;
            width: 100%;
        }
        .progress-segment {
            flex: 1;
            height: 3px;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: var(--text-main);
            border-radius: 2px;
            transform-origin: left;
        }

        /* Header Info */
        .header-meta {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .seq-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-weight: 700;
            color: var(--accent);
            margin: 0;
        }
        .frame-counter {
            font-family: monospace;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
        }

        /* Frame Text Data */
        .text-stage {
            margin-bottom: 20px;
        }
        .sketch-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 6vw, 3.5rem);
            margin: 0 0 10px 0;
            line-height: 1.1;
            /* For AnimeJS split text */
            display: flex;
            flex-wrap: wrap;
        }
        .sketch-title .word { display: inline-block; white-space: pre; overflow: hidden; }
        .sketch-title .letter { display: inline-block; transform: translateY(100%); opacity: 0; }

        .sketch-desc {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0;
            opacity: 0;
            transform: translateY(20px);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* --- CONTROLS --- */
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            pointer-events: auto;
        }
        
        .btn-nav {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            width: 48px; height: 48px;
            border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: all 0.3s;
        }
        .btn-nav:hover { background: rgba(255,255,255,0.15); transform: scale(1.05); }

        .btn-analysis {
            background: var(--glass-bg);
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            cursor: pointer;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            transition: all 0.3s;
        }
        .btn-analysis:hover {
            background: var(--accent);
            color: #000;
            box-shadow: 0 0 20px var(--accent-glow);
        }

        /* Swipe Zones for invisible touch navigation */
        .swipe-zone {
            position: absolute; top: 100px; bottom: 150px;
            width: 40%; z-index: 5; pointer-events: auto;
        }
        .swipe-left { left: 0; }
        .swipe-right { right: 0; }

        /* --- ANALYSIS PANEL (Glassmorphism Bottom/Side Sheet) --- */
        #analysis-panel {
            position: absolute;
            z-index: 20;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 30px;
            overflow-y: auto;
            pointer-events: auto;
            /* Hidden by default, animated by anime.js */
            transform: translateY(100%);
            opacity: 0;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Mobile First: Bottom Sheet */
        @media (max-width: 768px) {
            #analysis-panel {
                bottom: 0; left: 0; right: 0;
                height: 75vh;
                border-top-left-radius: 24px;
                border-top-right-radius: 24px;
                border-bottom: none;
            }
        }
        /* Desktop: Side Panel */
        @media (min-width: 769px) {
            #analysis-panel {
                top: 0; bottom: 0; right: 0;
                width: 450px;
                transform: translateX(100%);
                border-left: 1px solid var(--glass-border);
            }
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }
        .panel-title { margin: 0; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.05em; display: flex; align-items: center; gap: 10px; }
        .score-badge {
            background: var(--accent); color: #000; padding: 4px 10px; border-radius: 12px; font-weight: 900; font-size: 0.9rem;
        }
        .btn-close {
            background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;
        }
        .btn-close:hover { opacity: 1; }

        /* Data Groups inside Panel */
        .stat-group { opacity: 0; transform: translateY(15px); }
        .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 8px; }
        
        .pill-container { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #e2e8f0;
        }
        .pill.hl { border-color: rgba(16, 185, 129, 0.4); color: #34d399; background: rgba(16, 185, 129, 0.05); }
        
        .recs-box {
            background: rgba(0,0,0,0.3);
            border-left: 3px solid #8b5cf6;
            padding: 12px 15px;
            font-size: 0.85rem;
            line-height: 1.5;
            color: #cbd5e1;
            border-radius: 0 8px 8px 0;
        }
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
        
        <!-- Top: Progress & Sequence Meta -->
        <div>
            <div class="progress-container" id="progress-container">
                <!-- Segments injected via JS -->
            </div>
            <div class="header-meta">
                <h1 class="seq-title" id="ui-seq-name">Sequence Title</h1>
                <div class="frame-counter"><span id="curr-count">1</span> / <span id="tot-count">10</span></div>
            </div>
        </div>

        <!-- Bottom: Text & Controls -->
        <div>
            <div class="text-stage">
                <h2 class="sketch-title" id="ui-title"></h2>
                <p class="sketch-desc" id="ui-desc"></p>
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
                <button class="btn-analysis" onclick="toggleAnalysis()">
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

        <div class="stat-group">
            <div class="stat-label">Classification</div>
            <div class="pill-container" id="pan-class"></div>
        </div>

        <div class="stat-group">
            <div class="stat-label">Themes Present</div>
            <div class="pill-container" id="pan-themes"></div>
        </div>

        <div class="stat-group">
            <div class="stat-label">Entities In Frame</div>
            <div class="pill-container" id="pan-entities"></div>
        </div>

        <div class="stat-group">
            <div class="stat-label">Director's Notes (AI)</div>
            <div class="recs-box" id="pan-recs"></div>
        </div>
    </div>

    <script>
        // --- 1. Core Data ---
        const sequenceData = <?= $sequenceJson ?>;
        const frames = sequenceData.frames ||[];
        
        let currentIndex = -1;
        let isAnimating = false;
        let isAnalysisOpen = false;
        let activePlane = 'img-a'; // Tracks which img tag is currently front

        // DOM Elements
        const elImgA = document.getElementById('img-a');
        const elImgB = document.getElementById('img-b');
        const elTitle = document.getElementById('ui-title');
        const elDesc = document.getElementById('ui-desc');
        const elCurr = document.getElementById('curr-count');
        const elTot = document.getElementById('tot-count');
        const elProgBox = document.getElementById('progress-container');
        const panel = document.getElementById('analysis-panel');

        // Check if mobile for panel animation direction
        const isMobile = window.innerWidth <= 768;

        // --- 2. Initialization ---
        function init() {
            if (frames.length === 0) return;
            document.getElementById('ui-seq-name').innerText = sequenceData.name;
            elTot.innerText = frames.length;

            // Build progress segments
            for (let i = 0; i < frames.length; i++) {
                const seg = document.createElement('div');
                seg.className = 'progress-segment';
                seg.innerHTML = `<div class="progress-fill" id="prog-${i}"></div>`;
                elProgBox.appendChild(seg);
            }

            // Start first frame
            goToFrame(0, 1);
        }

        // --- 3. Animation & State Logic ---
        function splitText(text) {
            // Wraps words/letters for anime.js stagger effects
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

            // Update UI Counters
            elCurr.innerText = index + 1;
            updateProgress(oldIndex, index);

            // Setup Image Planes
            const oldPlane = activePlane === 'img-a' ? elImgA : elImgB;
            const newPlane = activePlane === 'img-a' ? elImgB : elImgA;
            activePlane = newPlane.id;

            newPlane.src = frame.thumb;
            newPlane.style.zIndex = 2;
            oldPlane.style.zIndex = 1;

            // Populate Text (Split text for the title animation)
            elTitle.innerHTML = splitText(frame.name || 'Untitled');
            elDesc.innerText = frame.desc || '';

            // Update Analysis Panel in background
            updateAnalysisData(frame);

            // --- ANIME.JS TIMELINES ---
            const tl = anime.timeline({
                easing: 'easeOutExpo',
                complete: () => { isAnimating = false; }
            });

            // 1. Crossfade & Scale Image
            // New image scales down slightly while fading in (cinematic depth)
            tl.add({
                targets: newPlane,
                opacity: [0, 1],
                scale:[1.15, 1],
                duration: 1200
            }, 0);

            // Old image scales up slightly while fading out
            if (oldIndex !== -1) {
                tl.add({
                    targets: oldPlane,
                    opacity: [1, 0],
                    scale:[1, 1.05],
                    duration: 1200
                }, 0);
            }

            // Continuous Ken-Burns effect for new image (lasts long after transition)
            anime({
                targets: newPlane,
                scale: [1, 1.05],
                duration: 10000,
                easing: 'linear'
            });

            // 2. Animate Text Reveal
            tl.add({
                targets: '#ui-title .letter',
                translateY:['100%', '0%'],
                opacity: [0, 1],
                duration: 800,
                easing: 'easeOutQuint',
                delay: anime.stagger(15) // Rapid stagger
            }, 200);

            tl.add({
                targets: elDesc,
                translateY:[20, 0],
                opacity: [0, 1],
                duration: 800,
                easing: 'easeOutQuad'
            }, 400);

            // If analysis is open, bump the stats slightly
            if (isAnalysisOpen) {
                anime({
                    targets: '.stat-group',
                    opacity: [0, 1],
                    translateY: [10, 0],
                    duration: 400,
                    delay: anime.stagger(100),
                    easing: 'easeOutQuad'
                });
            }
        }

        function updateProgress(oldIdx, newIdx) {
            // Fill previous segments instantly, empty future ones
            for(let i=0; i<frames.length; i++) {
                const fill = document.getElementById(`prog-${i}`);
                if (i < newIdx) fill.style.width = '100%';
                else if (i > newIdx) fill.style.width = '0%';
            }
            // Animate current segment
            anime({
                targets: `#prog-${newIdx}`,
                width: ['0%', '100%'],
                duration: 800,
                easing: 'easeOutQuad'
            });
        }

        // --- 4. Navigation ---
        function nextFrame() {
            if (currentIndex < frames.length - 1) goToFrame(currentIndex + 1, 1);
            else {
                // Shake effect if at end
                anime({ targets: '#viewport', translateX: [0, -10, 10, -5, 5, 0], duration: 400 });
            }
        }

        function prevFrame() {
            if (currentIndex > 0) goToFrame(currentIndex - 1, -1);
            else {
                anime({ targets: '#viewport', translateX: [0, 10, -10, 5, -5, 0], duration: 400 });
            }
        }

        // Keyboard Support
        window.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') nextFrame();
            if (e.key === 'ArrowLeft') prevFrame();
        });

        // --- 5. Analysis Panel ---
        function updateAnalysisData(frame) {
            const cur = frame.curation || {};
            
            // Score
            document.getElementById('pan-score').innerText = (cur.score || 0).toFixed(1);
            
            // Classifications
            const cls = cur.class || {};
            let clsHtml = '';
            if(cls.narrative_function) clsHtml += `<span class="pill hl">${cls.narrative_function}</span>`;
            if(cls.emotional_tone) clsHtml += `<span class="pill">${cls.emotional_tone}</span>`;
            document.getElementById('pan-class').innerHTML = clsHtml || '<span style="color:#666">N/A</span>';

            // Themes
            const thm = cur.themes || {};
            let thmHtml = '';
            if(thm.primary_themes && Array.isArray(thm.primary_themes)) {
                thm.primary_themes.forEach(t => thmHtml += `<span class="pill">${t}</span>`);
            }
            document.getElementById('pan-themes').innerHTML = thmHtml || '<span style="color:#666">N/A</span>';

            // Entities
            const ent = cur.entities || {};
            let entHtml = '';
            if(ent.characters && Array.isArray(ent.characters)) {
                ent.characters.forEach(c => entHtml += `<span class="pill" style="border-color:rgba(245, 158, 11, 0.4); color:#fbbf24;">👤 ${c}</span>`);
            }
            if(ent.locations && Array.isArray(ent.locations)) {
                ent.locations.forEach(l => entHtml += `<span class="pill">📍 ${l}</span>`);
            }
            document.getElementById('pan-entities').innerHTML = entHtml || '<span style="color:#666">None Detected</span>';

            // Recs
            const recs = cur.recs || {};
            document.getElementById('pan-recs').innerText = recs.potential_use || 'No specific editorial hints provided for this frame.';
        }

        function toggleAnalysis() {
            isAnalysisOpen = !isAnalysisOpen;
            
            const timeline = anime.timeline({ easing: 'easeInOutExpo' });

            if (isAnalysisOpen) {
                // 1. Push main viewport back slightly (3D effect)
                timeline.add({
                    targets: '#viewport',
                    scale: 0.92,
                    opacity: 0.6,
                    duration: 600
                }, 0);

                // 2. Slide panel in
                const slideProp = isMobile ? { translateY:['100%', '0%'] } : { translateX: ['100%', '0%'] };
                timeline.add({
                    targets: panel,
                    ...slideProp,
                    opacity: [0, 1],
                    duration: 600
                }, 0);

                // 3. Stagger data rows
                timeline.add({
                    targets: '.stat-group',
                    opacity: [0, 1],
                    translateY: [20, 0],
                    delay: anime.stagger(100),
                    duration: 600,
                    easing: 'easeOutCubic'
                }, 300);

            } else {
                // Hide
                const slideProp = isMobile ? { translateY: '100%' } : { translateX: '100%' };
                
                timeline.add({
                    targets: panel,
                    ...slideProp,
                    opacity: 0,
                    duration: 500
                }, 0);

                timeline.add({
                    targets: '#viewport',
                    scale: 1,
                    opacity: 1,
                    duration: 600
                }, 0);
            }
        }

        // Boot
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>