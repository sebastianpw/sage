<?php
// public/sequence_viewer.php
// Showrunner - Spatial Narrative Viewer
// Features: 3D Z-Axis Scrubbing, Scrollable Isometric Matrix, Flow Looping, ZIP Export

// ==============================================================================
// CONFIGURATION
// Tweak this value to change how much scroll distance is required to move 
// from one frame to the next. Lower = faster transitions. Higher = slower.
define('SCROLL_SPEED_PER_FRAME', 500); 

// Enable to show the ZIP Download button for standalone packaging.
define('ADMIN_MODE', true);
// ==============================================================================

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/SketchLibrary.php';

$seqId = $_GET['id'] ?? null;


/*
if (!$seqId) die("<div style='color:white; font-family:sans-serif; padding:20px;'>Error: Sequence ID required (?id=...)</div>");

// Fetch sequence (Only using manual table)
$stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
$stmt->execute([$seqId]);
$sequence = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sequence) die("<div style='color:white; font-family:sans-serif; padding:20px;'>Error: Sequence not found.</div>");
*/

if ($seqId) {
    $table = 'narrative_sequences';
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$seqId]);
    $sequence = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Fallback: load the most recent auto sequence
    $sequence = $pdo->query("SELECT * FROM narrative_sequences ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}




$sketchIds = json_decode($sequence['sequence_data'], true) ?:[];

// Hydrate Sketch Data
$lib = new SketchLibrary($pdo);
$hydratedItems = $lib->hydrateSpecificIds($sketchIds);

// ==============================================================================
// ZIP EXPORT LOGIC
// ==============================================================================
$isExport = defined('ADMIN_MODE') && ADMIN_MODE && isset($_GET['export']);
$frameDir = 'frames_' . $seqId;
$exportItems =[];

if ($isExport) {
    // Clone and rewrite paths for the export data
    foreach ($hydratedItems as $item) {
        $newItem = $item;
        $filename = basename($item['thumb']);
        $newItem['thumb'] = $frameDir . '/' . $filename; // Rewrite to relative path
        $exportItems[] = $newItem;
    }
} else {
    $exportItems = $hydratedItems; // Standard web rendering uses absolute paths
}

// Prepare the core data object
$sequenceDataArray = [
    'id' => $sequence['id'],
    'name' => $sequence['name'],
    'description' => $sequence['description'],
    'items' => $exportItems
];

$outputJson = json_encode($sequenceDataArray);

// Start output buffering for the HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5, maximum-scale=0.5, user-scalable=no">
    <title><?= htmlspecialchars($sequence['name']) ?> - Spatial Viewer</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Syncopate:wght@400;700&family=Inter:wght@300;400;600;800&display=swap');

        :root {
            --bg-void: #020203;
            --accent: #00f0ff;
            --accent-glow: rgba(0, 240, 255, 0.4);
        }

        body {
            background-color: var(--bg-void);
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        body::-webkit-scrollbar { display: none; }

        .font-display { font-family: 'Syncopate', sans-serif; text-transform: uppercase; }

        /* VIRTUAL SCROLL PROXY */
        #scroll-proxy {
            position: absolute; top: 0; left: 0; width: 1px; z-index: 1;
        }

        /* FIXED CANVAS (Where the 3D magic happens) */
        #canvas {
            position: fixed; inset: 0; width: 100vw; height: 100vh;
            perspective: 1200px; overflow: hidden; z-index: 10;
            display: flex; align-items: center; justify-content: center;
            transform-style: preserve-3d;
        }

        /* INDIVIDUAL FRAMES IN 3D SPACE */
        .frame-container {
            position: absolute;
            width: 90vw; max-width: 1000px; aspect-ratio: 16/9;
            will-change: transform, opacity, filter;
            transform-origin: center center;
            opacity: 0;
            box-shadow: 0 30px 60px rgba(0,0,0,0.8), 0 0 40px var(--accent-glow);
            border: 1px solid rgba(255,255,255,0.1);
            background: #000;
            overflow: hidden;
            border-radius: 8px;
            transform: translateZ(-2000px); 
        }

        .frame-image {
            width: 100%; height: 100%; object-fit: cover; transform: scale(1.05); 
        }

        /* FLOATING HUD */
        .frame-hud {
            position: absolute; bottom: -80px; left: 0; right: 0;
            text-align: center; opacity: 0; will-change: transform, opacity;
        }

        /* GLOBAL UI OVERLAYS */
        #ui-layer {
            position: fixed; inset: 0; z-index: 50; pointer-events: none;
            display: flex; flex-direction: column; justify-content: space-between; padding: 2rem;
        }

        /* FLOW NAVIGATION BUTTONS */
        .nav-btn-flow {
            width: 50px; height: 50px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            color: #fff;
            transition: all 0.3s ease;
            cursor: pointer;
            outline: none;
        }
        .nav-btn-flow:hover {
            background: rgba(0,240,255,0.1);
            border-color: var(--accent);
            color: var(--accent);
            transform: scale(1.1);
            box-shadow: 0 0 15px var(--accent-glow);
        }
        .nav-btn-flow:active { transform: scale(0.95); }

        /* ANALYSIS MATRIX */
        #analysis-matrix {
            position: fixed; inset: 0; z-index: 40; pointer-events: none;
            display: grid; grid-template-columns: 1fr; opacity: 0;
            background: radial-gradient(circle at 70% 50%, rgba(0, 50, 60, 0.4) 0%, transparent 60%);
        }

        @media(min-width: 768px) {
            #analysis-matrix { grid-template-columns: 1.5fr 1fr; }
        }

        /* Matrix Scrollability Fix */
        .matrix-content { 
            grid-column: 2; 
            height: 100vh;
            overflow-y: auto;
            -ms-overflow-style: none; scrollbar-width: none;
            display: flex; flex-direction: column;
        }
        .matrix-content::-webkit-scrollbar { display: none; }
        
        .matrix-scroll-area {
            margin: auto 0;
            padding: 4rem 2rem;
            display: flex; flex-direction: column;
            width: 100%;
        }

        .matrix-node {
            background: rgba(10, 15, 20, 0.85); backdrop-filter: blur(12px);
            border-left: 2px solid var(--accent); border: 1px solid rgba(255,255,255,0.05); border-left-width: 2px; border-left-color: var(--accent);
            padding: 1.5rem; margin-bottom: 1rem; transform: translateX(100px); opacity: 0; will-change: transform, opacity;
        }

        .svg-lines { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1; }
        .svg-lines path { fill: none; stroke: var(--accent); stroke-width: 1; stroke-dasharray: 1000; stroke-dashoffset: 1000; }

        /* ENTRY CINEMATIC */
        #entry-cinematic {
            position: fixed; inset: 0; background: var(--bg-void); z-index: 100;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }

        .pill { border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; padding: 2px 8px; font-size: 0.7rem; font-family: monospace; }
    </style>
</head>
<body>

    <!-- Virtual Scroll Proxy -->
    <div id="scroll-proxy"></div>

    <!-- 3D Stage -->
    <div id="canvas"></div>

    <!-- Analysis Matrix Overlay -->
    <div id="analysis-matrix">
        <svg class="svg-lines" id="matrix-lines"><path id="line-1" d="M 300,500 L 600,300 L 900,300" /></svg>
        <div class="matrix-content">
            <div class="matrix-scroll-area">
                <div class="matrix-node" id="node-func">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 mb-2">Narrative Core</p>
                    <h3 class="text-2xl font-display text-[#00f0ff] mb-1" id="m-func">Function</h3>
                    <p class="text-sm text-[#00f0ff]/60 tracking-widest uppercase" id="m-tone">Tone</p>
                </div>
                <div class="matrix-node" id="node-entities">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 mb-2">Detected Entities</p>
                    <div class="flex flex-wrap gap-2" id="m-entities-list"></div>
                </div>
                <div class="matrix-node" id="node-themes">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 mb-2">Thematic DNA</p>
                    <div class="flex flex-wrap gap-2" id="m-themes-list"></div>
                </div>
                <div class="matrix-node" id="node-recs">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 mb-2">Director's Note</p>
                    <p class="text-sm text-gray-300 font-serif italic border-l border-white/20 pl-4" id="m-recs-text"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Static UI -->
    <div id="ui-layer">
        <header class="flex justify-between items-start pointer-events-auto">
            <div>
                <h1 class="text-2xl font-display tracking-widest text-white drop-shadow-lg" id="global-title">Loading</h1>
                <p class="text-xs text-[#00f0ff] tracking-[0.3em] uppercase mt-1">Spatial Array Viewer</p>
            </div>
            <!-- Matrix Toggle -->
            <button id="btn-matrix" class="w-14 h-14 rounded bg-white/5 border border-white/10 hover:border-[#00f0ff] hover:bg-[#00f0ff]/10 backdrop-blur-md transition-all flex flex-col items-center justify-center gap-1 group">
                <i class="fas fa-cube text-[#00f0ff] group-hover:animate-pulse"></i>
                <span class="text-[8px] uppercase tracking-widest text-gray-400 group-hover:text-[#00f0ff]">Matrix</span>
            </button>
        </header>

        <footer class="flex flex-col items-center gap-6">
            
            <!-- Flow Controls -->
            <div class="flex items-center gap-4 pointer-events-auto bg-black/40 backdrop-blur-xl px-4 py-2 rounded-full border border-white/10 shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
                <button id="btn-prev" class="nav-btn-flow" title="Previous Frame"><i class="fas fa-step-backward"></i></button>
                <div class="w-px h-6 bg-white/20 mx-2"></div>
                <button id="btn-next" class="nav-btn-flow" title="Next Frame"><i class="fas fa-step-forward"></i></button>
                
                <!-- Admin Mode: ZIP Export (Hidden in actual export) -->
                <?php if (defined('ADMIN_MODE') && ADMIN_MODE && !$isExport): ?>
                <div class="w-px h-6 bg-white/20 mx-2"></div>
                <a href="?id=<?= $seqId ?>&export=1" class="nav-btn-flow" title="Download Standalone ZIP" style="text-decoration:none;">
                    <i class="fas fa-download"></i>
                </a>
                <?php endif; ?>
            </div>

            <div class="w-full flex justify-between items-end">
                <div class="text-[10px] tracking-widest text-gray-500 font-monospace">
                    SCROLL OR FLOW <i class="fas fa-arrows-alt-v ml-2 animate-bounce"></i>
                </div>
                <!-- Progress Dots -->
                <div class="flex gap-1" id="progress-bars"></div>
            </div>
        </footer>
    </div>

    <!-- Entry Cinematic -->
    <div id="entry-cinematic">
        <h2 class="font-display text-4xl md:text-6xl tracking-[0.2em] text-white opacity-0" id="entry-text">INITIALIZING</h2>
        <div class="w-64 h-[1px] bg-gradient-to-r from-transparent via-[#00f0ff] to-transparent mt-4 opacity-0" id="entry-line"></div>
    </div>

    <!-- Inject Sequence Data dynamically -->
    <?php if ($isExport): ?>
        <script src="<?= $frameDir ?>/frames_<?= $seqId ?>.js"></script>
    <?php else: ?>
        <script>const seqData = <?= $outputJson ?>;</script>
    <?php endif; ?>

    <script>
        // --- 0. PREVENT BROWSER SCROLL RESTORATION ---
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        window.scrollTo(0, 0);

        // --- 1. CORE DATA & STATE ---
        const frames = seqData.items;
        
        let currentScroll = 0;
        let targetScroll = 0;
        let isMatrixMode = false;
        
        // Physics / Tuning
        const SCROLL_PER_FRAME = <?= SCROLL_SPEED_PER_FRAME ?>; 
        const LERP_FACTOR = 0.08; 
        
        const maxScroll = (frames.length - 1) * SCROLL_PER_FRAME;
        document.getElementById('scroll-proxy').style.height = `${maxScroll + window.innerHeight}px`;

        let masterTl;
        const domFrames = [];
        const domHuds =[];

        // --- 2. BUILD THE DOM ---
        function buildStage() {
            const canvas = document.getElementById('canvas');
            const progressContainer = document.getElementById('progress-bars');
            document.getElementById('global-title').textContent = seqData.name;

            frames.forEach((frame, i) => {
                // Image
                const cont = document.createElement('div');
                cont.className = 'frame-container';
                const img = document.createElement('img');
                img.src = frame.thumb;
                img.className = 'frame-image';
                cont.appendChild(img);
                canvas.appendChild(cont);
                domFrames.push(cont);

                // HUD
                const hud = document.createElement('div');
                hud.className = 'frame-hud';
                hud.innerHTML = `
                    <div class="inline-block bg-black/60 backdrop-blur-md border border-white/10 p-4 rounded text-center shadow-2xl">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-[#00f0ff] mb-1 font-monospace">SHOT[ 00${i+1} ]</p>
                        <h2 class="text-xl md:text-2xl font-serif font-bold text-white">${frame.name}</h2>
                    </div>
                `;
                canvas.appendChild(hud);
                domHuds.push(hud);

                // Progress
                const dot = document.createElement('div');
                dot.className = 'w-5 h-1 bg-white/20 rounded transition-colors duration-300';
                progressContainer.appendChild(dot);
            });
        }

        // --- 3. CONSTRUCT THE SPATIAL TIMELINE ---
        function buildTimeline() {
            const DURATION = 1000;
            masterTl = anime.timeline({ autoplay: false, duration: (frames.length - 1) * DURATION, easing: 'linear' });

            frames.forEach((_, i) => {
                const el = domFrames[i];
                const hud = domHuds[i];
                const startTime = (i - 1) * DURATION;
                
                if (i > 0) {
                    masterTl.add({
                        targets: el,
                        translateZ:[-2500, 0],
                        translateY: [200, 0],
                        opacity:[0, 1],
                        filter:['blur(15px)', 'blur(0px)'],
                        duration: DURATION, easing: 'easeOutSine'
                    }, startTime);

                    masterTl.add({
                        targets: hud,
                        translateY:[50, 0], opacity: [0, 1],
                        duration: DURATION * 0.4, easing: 'easeOutQuad'
                    }, startTime + DURATION * 0.6);
                } else {
                    anime.set(el, { translateZ: 0, opacity: 1 });
                    anime.set(hud, { opacity: 1, translateY: 0 });
                }

                if (i < frames.length - 1) {
                    const exitTime = i * DURATION;
                    masterTl.add({
                        targets: el,
                        translateZ:[0, 500],
                        translateY:[0, -100],
                        opacity:[1, 0],
                        filter:['blur(0px)', 'blur(20px)'],
                        duration: DURATION * 0.8, easing: 'easeInCubic'
                    }, exitTime);

                    masterTl.add({
                        targets: hud,
                        translateY:[0, -50], opacity: [1, 0],
                        duration: DURATION * 0.3, easing: 'easeInQuad'
                    }, exitTime);
                }
            });
        }

        // --- 4. THE ENGINE (Lerp) ---
        function updateScroll() {
            if (!isMatrixMode) {
                targetScroll = window.scrollY;
                currentScroll += (targetScroll - currentScroll) * LERP_FACTOR;
                
                if (currentScroll < 0) currentScroll = 0;
                if (currentScroll > maxScroll) currentScroll = maxScroll;

                const progress = maxScroll > 0 ? (currentScroll / maxScroll) : 0;
                masterTl.seek(masterTl.duration * progress);
                updateIndicators(progress);
            }
            requestAnimationFrame(updateScroll);
        }

        function updateIndicators(progress) {
            const activeIndex = Math.round(progress * (frames.length - 1));
            document.querySelectorAll('#progress-bars div').forEach((dot, i) => {
                dot.style.backgroundColor = i === activeIndex ? '#00f0ff' : (i < activeIndex ? 'rgba(255,255,255,0.6)' : 'rgba(255,255,255,0.2)');
            });
        }

        function getActiveIndex() {
            const progress = maxScroll > 0 ? (currentScroll / maxScroll) : 0;
            return Math.round(progress * (frames.length - 1));
        }

        // --- 5. FLOW NAVIGATION (With Round-Trip Looping) ---
        function flowToFrame(offset) {
            if (isMatrixMode) return;

            let activeIndex = getActiveIndex();
            let targetIndex = activeIndex + offset;
            
            // Loop Backwards (Round-Trip)
            if (targetIndex < 0) {
                targetIndex = frames.length - 1;
            }
            // Loop Forwards (Round-Trip)
            if (targetIndex >= frames.length) {
                targetIndex = 0;
            }
            
            const targetY = targetIndex * SCROLL_PER_FRAME;
            
            // Tween native scrolling element to maintain Lerp physics harmony
            anime({
                targets: document.scrollingElement,
                scrollTop: targetY,
                duration: 1200,
                easing: 'easeInOutQuint'
            });
        }

        document.getElementById('btn-prev').addEventListener('click', () => flowToFrame(-1));
        document.getElementById('btn-next').addEventListener('click', () => flowToFrame(1));

        // --- 6. ANALYSIS MATRIX (ISOMETRIC) ---
        function toggleMatrixMode() {
            const btn = document.getElementById('btn-matrix');
            const icon = btn.querySelector('i');
            const matrixContainer = document.getElementById('analysis-matrix');
            const svgPath = document.getElementById('line-1');
            
            const activeIndex = getActiveIndex();
            const activeFrameEl = domFrames[activeIndex];
            const activeHudEl = domHuds[activeIndex];
            const item = frames[activeIndex];

            isMatrixMode = !isMatrixMode;

            if (isMatrixMode) {
                document.body.style.overflow = 'hidden';
                populateMatrixData(item);

                icon.classList.replace('fa-cube', 'fa-times');
                btn.classList.add('bg-[#00f0ff]/20', 'border-[#00f0ff]');

                anime({
                    targets: activeFrameEl,
                    rotateY: 25, rotateX: 5, rotateZ: -2,
                    translateZ: -300,
                    translateX: window.innerWidth > 768 ? '-15vw' : '0',
                    translateY: window.innerWidth > 768 ? '0' : '-20vh',
                    scale: 0.85,
                    boxShadow: '20px 40px 80px rgba(0,0,0,0.9), -10px -10px 40px rgba(0,240,255,0.2)',
                    duration: 1000, easing: 'easeOutElastic(1, .8)'
                });

                anime({ targets: activeHudEl, opacity: 0, duration: 300, easing: 'easeOutQuad' });

                matrixContainer.style.pointerEvents = 'auto';
                anime({ targets: matrixContainer, opacity: 1, duration: 500, easing: 'linear' });

                anime({
                    targets: svgPath,
                    strokeDashoffset:[anime.setDashoffset, 0],
                    easing: 'easeInOutSine', duration: 500, direction: 'alternate', loop: false
                });

                anime({
                    targets: '.matrix-node',
                    translateX: [100, 0], opacity:[0, 1],
                    delay: anime.stagger(150, {start: 300}),
                    duration: 800, easing: 'easeOutExpo'
                });

            } else {
                document.body.style.overflow = 'auto';
                icon.classList.replace('fa-times', 'fa-cube');
                btn.classList.remove('bg-[#00f0ff]/20', 'border-[#00f0ff]');
                matrixContainer.style.pointerEvents = 'none';
                
                anime({
                    targets: activeFrameEl,
                    rotateY: 0, rotateX: 0, rotateZ: 0,
                    translateX: 0, translateY: 0, scale: 1,
                    boxShadow: '0 30px 60px rgba(0,0,0,0.8), 0 0 40px rgba(0,240,255,0.4)',
                    duration: 800, easing: 'easeOutExpo',
                    complete: () => {
                        const progress = maxScroll > 0 ? (currentScroll / maxScroll) : 0;
                        masterTl.seek(masterTl.duration * progress);
                    }
                });

                anime({ targets: activeHudEl, opacity: 1, duration: 800, delay: 200, easing: 'easeOutQuad' });
                anime({ targets: '.matrix-node', translateX: 50, opacity: 0, duration: 400, easing: 'easeInCubic' });
                anime({ targets: matrixContainer, opacity: 0, duration: 600, delay: 200, easing: 'linear' });
            }
        }

        function populateMatrixData(item) {
            const cur = item.curation || {};
            const cls = cur.class || {};
            const ent = cur.entities || {};
            const themes = cur.themes || {};
            const recs = cur.recs || {};

            document.getElementById('m-func').textContent = cls.narrative_function || 'Undefined Function';
            document.getElementById('m-tone').textContent = `Tone: ${cls.emotional_tone || 'Neutral'}`;

            // Entities
            const chars = Array.isArray(ent.characters) ? ent.characters :[];
            const locs = Array.isArray(ent.locations) ? ent.locations :[];
            let entHtml = '';
            chars.forEach(c => entHtml += `<span class="pill text-[#00f0ff] border-[#00f0ff]/30">👤 ${c}</span>`);
            locs.forEach(l => entHtml += `<span class="pill text-emerald-300 border-emerald-300/30">📍 ${l}</span>`);
            document.getElementById('m-entities-list').innerHTML = entHtml || '<span class="text-xs">No specific entities locked.</span>';

            // Themes
            const thms = Array.isArray(themes.primary_themes) ? themes.primary_themes :[];
            let thmHtml = '';
            thms.forEach(t => thmHtml += `<span class="pill text-purple-300 border-purple-300/30">${t}</span>`);
            document.getElementById('m-themes-list').innerHTML = thmHtml || '<span class="text-xs">N/A</span>';

            // Recs
            document.getElementById('m-recs-text').textContent = recs.potential_use || 'No specialized director notes for this frame.';
        }

        document.getElementById('btn-matrix').addEventListener('click', toggleMatrixMode);

        // --- 7. ENTRY CINEMATIC ---
        function playEntryCinematic() {
            const tl = anime.timeline({ easing: 'easeOutExpo' });

            tl.add({
                targets: '#entry-text', opacity:[0, 1], letterSpacing:['0.5em', '0.2em'], duration: 500,
            })
            .add({
                targets: '#entry-line', opacity:[0, 1], scaleX: [0, 1], duration: 1000,
            }, '-=1000')
            .add({
                targets: '#entry-cinematic', opacity: 0, duration: 800, easing: 'linear',
                complete: () => document.getElementById('entry-cinematic').style.display = 'none'
            })
            .add({
                targets: '#ui-layer header, #ui-layer footer',
                translateY: (el) => el.tagName === 'HEADER' ? [-30, 0] :[30, 0],
                opacity:[0, 1], duration: 1000, delay: anime.stagger(200)
            }, '-=400');
        }

        // --- 8. BOOTSTRAP ---
        window.onload = () => {
            buildStage();
            buildTimeline();
            requestAnimationFrame(updateScroll);
            playEntryCinematic();
        };

        window.addEventListener('resize', () => {
            if (masterTl) {
                if (isMatrixMode) toggleMatrixMode();
            }
        });

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
    $zipName = sys_get_temp_dir() . '/sequence_' . $seqId . '_' . time() . '.zip';
    
    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        // 1. Add the Frame Directory
        $zip->addEmptyDir($frameDir);
        
        // 2. Add the Main HTML File
        $zip->addFromString("seq_{$seqId}.html", $htmlContent);
        
        // 3. Generate & Add the External JS File (Pretty Printed)
        $jsFileContent = "const seqData = " . json_encode(
            $sequenceDataArray, 
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . ";\n";
        $zip->addFromString($frameDir . '/frames_' . $seqId . '.js', $jsFileContent);
        
        // 4. Fetch and Package Images
        foreach ($hydratedItems as $item) {
            $originalThumbUrl = $item['thumb'];
            $filename = basename($originalThumbUrl);
            $localZipPath = $frameDir . '/' . $filename;
            
            // Try physical path using Document Root
            $physicalPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($originalThumbUrl, '/');
            
            // Try alternate physical path relative to this script's directory
            $altPhysicalPath = realpath(__DIR__ . '/../') . '/' . ltrim($originalThumbUrl, '/');
            
            if (file_exists($physicalPath)) {
                $zip->addFile($physicalPath, $localZipPath);
            } elseif (file_exists($altPhysicalPath)) {
                $zip->addFile($altPhysicalPath, $localZipPath);
            } else {
                // Fallback: Fetch via HTTP
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $fullUrl = $protocol . $host . '/' . ltrim($originalThumbUrl, '/');
                
                // Allow fetching even if local SSL is self-signed/invalid
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
        
        // Output Zip to Browser
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=spatial_sequence_' . $seqId . '.zip');
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