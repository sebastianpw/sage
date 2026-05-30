<?php
// public/sequence_viewer.php
// Cinematic Narrative Sequence Viewer
// A world-class, mobile-first, anime.js powered interactive storyboard player.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/SketchLibrary.php';

$seqId = $_GET['id'] ?? null;
if (!$seqId) die("<div style='color:white; font-family:sans-serif; padding:20px;'>Error: Sequence ID required (?id=...)</div>");

// 1. Fetch sequence (check auto first, then manual)
$stmt = $pdo->prepare("SELECT * FROM narrative_sequences_auto WHERE id = ?");
$stmt->execute([$seqId]);
$sequence = $stmt->fetch(PDO::FETCH_ASSOC);
$isAuto = true;

if (!$sequence) {
    $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
    $stmt->execute([$seqId]);
    $sequence = $stmt->fetch(PDO::FETCH_ASSOC);
    $isAuto = false;
}

if (!$sequence) die("<div style='color:white; font-family:sans-serif; padding:20px;'>Error: Sequence not found.</div>");

$sketchIds = json_decode($sequence['sequence_data'], true) ?:[];

// 2. Hydrate Sketch Data
$lib = new SketchLibrary($pdo);
$hydratedItems = $lib->hydrateSpecificIds($sketchIds);

// Pass data to Javascript
$sequenceJson = json_encode([
    'id' => $sequence['id'],
    'name' => $sequence['name'],
    'description' => $sequence['description'],
    'is_auto' => $isAuto,
    'items' => $hydratedItems
]);

?>
<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($sequence['name']) ?> - Cinematic Viewer</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Anime.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap');

        :root {
            --accent: #10b981;
        }

        body {
            background-color: #030303;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            overscroll-behavior-y: none;
            -webkit-tap-highlight-color: transparent;
        }

        .font-serif { font-family: 'Playfair Display', serif; }

        /* Stage Layers for Crossfading & Ken Burns */
        .stage-layer {
            position: absolute;
            inset: -5%; /* Bleed edge to allow panning without clipping */
            width: 110%;
            height: 110%;
            object-fit: cover;
            opacity: 0;
            will-change: transform, opacity;
            pointer-events: none;
        }

        /* Vignettes for text readability */
        .vignette {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, transparent 30%, rgba(0,0,0,0.85) 100%);
            z-index: 10;
            pointer-events: none;
        }

        .gradient-top {
            position: absolute;
            top: 0; left: 0; right: 0; height: 30vh;
            background: linear-gradient(to bottom, rgba(0,0,0,0.8) 0%, transparent 100%);
            z-index: 10;
            pointer-events: none;
        }

        .gradient-bottom {
            position: absolute;
            bottom: 0; left: 0; right: 0; height: 40vh;
            background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, transparent 100%);
            z-index: 10;
            pointer-events: none;
        }

        /* Glassmorphism Context Drawer */
        .context-drawer {
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            background: rgba(10, 10, 12, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transform: translateY(120%);
            will-change: transform;
            box-shadow: 0 -20px 50px rgba(0,0,0,0.5);
        }

        .context-drawer::-webkit-scrollbar { width: 4px; }
        .context-drawer::-webkit-scrollbar-track { background: transparent; }
        .context-drawer::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        /* UI Elements */
        .timeline-node { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .timeline-node.active { background-color: var(--accent); transform: scaleY(1.5); }
        .timeline-node.played { background-color: rgba(255,255,255,0.8); }

        .pill { 
            display: inline-block; padding: 4px 10px; background: rgba(255,255,255,0.05); 
            border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; 
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #aaa; 
        }

        /* Nav Arrows - hidden on mobile, hover on desktop */
        .nav-btn {
            background: radial-gradient(circle at center, rgba(0,0,0,0.5) 0%, transparent 70%);
            opacity: 0; transition: opacity 0.3s ease;
        }
        @media(min-width: 768px) {
            .stage-container:hover .nav-btn { opacity: 1; }
        }
    </style>
</head>
<body class="relative w-screen h-screen">

    <!-- THE STAGE -->
    <div class="absolute inset-0 bg-black z-0 stage-container" id="touch-area">
        <img id="stage-A" class="stage-layer" src="" alt="">
        <img id="stage-B" class="stage-layer" src="" alt="">
        
        <!-- Desktop Hover Navigation -->
        <button onclick="prevFrame()" class="nav-btn absolute left-0 top-0 bottom-0 w-32 flex items-center justify-center z-20 hidden md:flex">
            <i class="fas fa-chevron-left text-4xl text-white drop-shadow-lg"></i>
        </button>
        <button onclick="nextFrame()" class="nav-btn absolute right-0 top-0 bottom-0 w-32 flex items-center justify-center z-20 hidden md:flex">
            <i class="fas fa-chevron-right text-4xl text-white drop-shadow-lg"></i>
        </button>
    </div>

    <!-- OVERLAYS -->
    <div class="vignette"></div>
    <div class="gradient-top"></div>
    <div class="gradient-bottom"></div>

    <!-- MAIN UI LAYER (Z-20) -->
    <div id="ui-layer" class="absolute inset-0 z-20 flex flex-col justify-between p-6 pointer-events-none">
        
        <!-- Top Header -->
        <header class="flex justify-between items-start opacity-0 ui-element pointer-events-auto">
            <button onclick="window.history.back()" class="w-12 h-12 rounded-full bg-black/40 hover:bg-black/60 backdrop-blur-md flex justify-center items-center transition border border-white/10">
                <i class="fas fa-arrow-left text-sm"></i>
            </button>
            <div class="text-center flex-1 px-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-emerald-400 font-bold mb-1" id="seq-meta">
                    <?= $isAuto ? 'AUTO SEQUENCE' : 'DIRECTOR SEQUENCE' ?>
                </p>
                <h1 class="text-xl md:text-2xl font-serif text-white/90 drop-shadow-md" id="seq-title">Loading...</h1>
            </div>
            <button onclick="toggleAutoPlay()" id="btn-autoplay" class="w-12 h-12 rounded-full bg-black/40 hover:bg-emerald-500/20 backdrop-blur-md flex justify-center items-center transition border border-white/10 text-white group">
                <i class="fas fa-play text-sm group-hover:text-emerald-400 transition-colors"></i>
            </button>
        </header>

        <!-- Bottom Footer -->
        <footer class="w-full pointer-events-auto relative z-30 opacity-0 ui-element">
            
            <div class="flex justify-between items-end mb-6">
                <!-- Frame Text -->
                <div class="max-w-[75%] md:max-w-[60%]">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-gray-400 mb-2 frame-text-anim" id="frame-id">SHOT #000</p>
                    <h2 class="text-3xl md:text-5xl font-serif font-bold leading-tight drop-shadow-xl text-white frame-text-anim" id="frame-title">...</h2>
                    <p class="text-sm md:text-base text-gray-300 mt-3 drop-shadow-md line-clamp-3 md:line-clamp-none font-light frame-text-anim" id="frame-desc">...</p>
                </div>

                <!-- Analysis Toggle Button -->
                <button onclick="toggleContext()" id="insight-btn" class="flex flex-col items-center justify-center gap-2 group transform transition active:scale-95">
                    <div class="w-14 h-14 rounded-full bg-black/50 backdrop-blur-xl border border-white/10 flex items-center justify-center group-hover:bg-white/10 group-hover:border-emerald-500/50 transition-all duration-300 shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                        <i class="fas fa-microchip text-xl text-emerald-400"></i>
                    </div>
                    <span class="text-[9px] uppercase tracking-widest text-emerald-400/80 font-bold">Analysis</span>
                </button>
            </div>

            <!-- Timeline Scrubber -->
            <div class="flex items-center gap-1 w-full h-2" id="timeline-container">
                <!-- Injected via JS -->
            </div>
        </footer>
    </div>

    <!-- CONTEXT DRAWER (Z-40) - Glassmorphism Bottom/Side Sheet -->
    <div id="context-drawer" class="absolute bottom-0 left-0 right-0 h-[80vh] md:h-auto md:max-h-[85vh] md:w-[450px] md:left-auto md:right-6 md:bottom-6 md:rounded-2xl z-40 context-drawer overflow-y-auto p-6 md:p-8 flex flex-col pointer-events-auto">
        
        <!-- Drawer Header -->
        <div class="flex justify-between items-center mb-8 border-b border-white/10 pb-4 drawer-item opacity-0">
            <h3 class="text-sm font-bold tracking-[0.15em] uppercase text-emerald-400">
                <i class="fas fa-database mr-2"></i> Sequence Engine Data
            </h3>
            <button onclick="toggleContext()" class="text-gray-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-full bg-white/5">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Drawer Content -->
        <div class="space-y-6 pb-10">
            
            <!-- Quality & Function -->
            <div class="drawer-item opacity-0 flex gap-4">
                <div class="bg-black/40 rounded-xl p-4 border border-white/5 text-center flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">Curation Score</p>
                    <p class="text-2xl font-serif font-bold text-white" id="ctx-score">0.0</p>
                </div>
                <div class="bg-black/40 rounded-xl p-4 border border-white/5 flex-[2]">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">Narrative Function</p>
                    <p class="text-sm font-semibold text-emerald-300" id="ctx-function">...</p>
                    <p class="text-xs text-gray-400 mt-1" id="ctx-tone">...</p>
                </div>
            </div>

            <!-- Focus Entities -->
            <div class="drawer-item opacity-0">
                <h4 class="text-[10px] uppercase tracking-[0.15em] text-gray-500 mb-3"><i class="fas fa-users mr-2"></i>Entities In Frame</h4>
                <div class="flex flex-wrap gap-2" id="ctx-entities"></div>
            </div>

            <!-- Thematics -->
            <div class="drawer-item opacity-0">
                <h4 class="text-[10px] uppercase tracking-[0.15em] text-gray-500 mb-3"><i class="fas fa-mask mr-2"></i>Primary Themes</h4>
                <div class="flex flex-wrap gap-2" id="ctx-themes"></div>
            </div>

            <!-- AI Director Notes -->
            <div class="drawer-item opacity-0">
                <h4 class="text-[10px] uppercase tracking-[0.15em] text-gray-500 mb-3"><i class="fas fa-comment-alt mr-2"></i>Director Notes</h4>
                <div class="bg-black/40 rounded-xl p-5 border border-white/5">
                    <p class="text-sm text-gray-300 leading-relaxed italic font-serif" id="ctx-recs">...</p>
                </div>
            </div>

        </div>
    </div>

    <script>
        // 1. DATA INITIALIZATION
        const sequenceData = <?= $sequenceJson ?>;
        const frames = sequenceData.items; // Array of hydrated sketch items
        
        let currentIndex = 0;
        let isDrawerOpen = false;
        let activeLayer = 'A'; // Tracks which img tag is in front
        let isAnimating = false;
        
        // Autoplay settings
        let isAutoplay = false;
        let autoplayTimer = null;
        const AUTOPLAY_SPEED = 6000; // ms per frame

        // 2. BOOTSTRAP UI
        document.addEventListener('DOMContentLoaded', () => {
            if (frames.length === 0) {
                document.getElementById('seq-title').textContent = "Empty Sequence";
                return;
            }

            document.getElementById('seq-title').textContent = sequenceData.name;
            
            // Build timeline scrubber
            const timeline = document.getElementById('timeline-container');
            frames.forEach((_, i) => {
                const node = document.createElement('div');
                node.className = `h-full flex-1 rounded-full bg-white/20 timeline-node cursor-pointer border border-black/50`;
                node.onclick = () => goToFrame(i);
                timeline.appendChild(node);
            });

            // Initial load sequence
            loadFrame(0, true);

            // Intro UI Animation
            anime({
                targets: '.ui-element',
                translateY:[20, 0],
                opacity: [0, 1],
                duration: 1200,
                delay: anime.stagger(200, {start: 500}),
                easing: 'easeOutExpo'
            });
            
            // Setup Swipe Gestures
            setupTouch();
        });

        // 3. CINEMATIC RENDER ENGINE
        function loadFrame(index, isInit = false) {
            if ((isAnimating && !isInit) || index < 0 || index >= frames.length) return;
            isAnimating = true;

            const item = frames[index];
            const nextLayer = activeLayer === 'A' ? 'B' : 'A';
            const currentEl = document.getElementById(`stage-${activeLayer}`);
            const nextEl = document.getElementById(`stage-${nextLayer}`);

            // Preload next image
            nextEl.src = item.thumb;

            // Update Timeline
            document.querySelectorAll('.timeline-node').forEach((el, i) => {
                el.classList.toggle('active', i === index);
                el.classList.toggle('played', i < index);
            });

            // Text Glitch & Reveal Animation
            anime({
                targets: '.frame-text-anim',
                opacity: 0,
                translateY: -10,
                duration: 300,
                easing: 'easeInSine',
                complete: () => {
                    // Inject Text
                    document.getElementById('frame-id').textContent = `SHOT #${item.id}`;
                    document.getElementById('frame-title').textContent = item.name;
                    document.getElementById('frame-desc').textContent = item.desc || 'No description available.';
                    
                    // Inject Drawer Data
                    updateDrawerData(item);

                    anime({
                        targets: '.frame-text-anim',
                        opacity: 1,
                        translateY: [10, 0],
                        duration: 800,
                        delay: anime.stagger(100),
                        easing: 'easeOutExpo'
                    });
                }
            });

            // --- CROSSFADING & KEN BURNS ---
            if (!isInit) {
                // Fade out current layer
                anime({
                    targets: currentEl,
                    opacity: 0,
                    duration: 1500,
                    easing: 'easeInOutSine'
                });
            }

            // Kill any ongoing zooms on the next element to reset it
            anime.remove(nextEl); 

            // 1. Fade In
            anime({
                targets: nextEl,
                opacity: 1,
                duration: 1500,
                easing: 'easeInOutSine',
                complete: () => {
                    activeLayer = nextLayer;
                    isAnimating = false;
                    
                    // If auto-playing, schedule next
                    if (isAutoplay && currentIndex < frames.length - 1) {
                        autoplayTimer = setTimeout(nextFrame, AUTOPLAY_SPEED);
                    } else if (isAutoplay && currentIndex === frames.length - 1) {
                        toggleAutoPlay(); // Stop at end
                    }
                }
            });

            // 2. Infinite Ken Burns scale/pan
            // Alternate pan direction and origin for organic feel
            const scaleStart = 1.02;
            const scaleEnd = 1.15;
            const panX = index % 2 === 0 ? [0, '-2%'] : [0, '2%'];
            const panY = index % 3 === 0 ? [0, '2%'] : [0, '-1%'];
            
            anime({
                targets: nextEl,
                scale: [scaleStart, scaleEnd],
                translateX: panX,
                translateY: panY,
                duration: 30000, // Very slow 30s movement
                easing: 'linear'
            });
            
            currentIndex = index;
        }

        function updateDrawerData(item) {
            const cur = item.curation || {};
            const cls = cur.class || {};
            const ent = cur.entities || {};
            const themes = cur.themes || {};
            const recs = cur.recs || {};

            document.getElementById('ctx-score').textContent = (cur.score || 0).toFixed(1);
            
            // Color code score
            const sc = cur.score || 0;
            const scEl = document.getElementById('ctx-score');
            scEl.className = `text-2xl font-serif font-bold ${sc >= 8 ? 'text-emerald-400' : (sc >= 5 ? 'text-amber-400' : 'text-rose-400')}`;

            document.getElementById('ctx-function').textContent = cls.narrative_function || 'Unknown Function';
            document.getElementById('ctx-tone').textContent = `Tone: ${cls.emotional_tone || 'Neutral'}`;
            
            // Entities HTML
            const chars = Array.isArray(ent.characters) ? ent.characters :[];
            const locs = Array.isArray(ent.locations) ? ent.locations :[];
            let entHtml = '';
            chars.forEach(c => entHtml += `<span class="pill border-amber-500/30 text-amber-200/80 bg-amber-500/10"><i class="fas fa-user text-[8px] mr-1"></i>${c}</span>`);
            locs.forEach(l => entHtml += `<span class="pill border-blue-500/30 text-blue-200/80 bg-blue-500/10"><i class="fas fa-map-marker-alt text-[8px] mr-1"></i>${l}</span>`);
            document.getElementById('ctx-entities').innerHTML = entHtml || '<span class="text-xs text-gray-600 italic">None detected</span>';

            // Themes HTML
            const thms = Array.isArray(themes.primary_themes) ? themes.primary_themes :[];
            let thmHtml = '';
            thms.forEach(t => thmHtml += `<span class="pill border-purple-500/30 text-purple-200/80 bg-purple-500/10">${t}</span>`);
            document.getElementById('ctx-themes').innerHTML = thmHtml || '<span class="text-xs text-gray-600 italic">None detected</span>';

            // Recs
            document.getElementById('ctx-recs').innerHTML = recs.potential_use 
                ? `&ldquo;${recs.potential_use}&rdquo;` 
                : 'No director notes available for this frame.';
        }

        // 4. NAVIGATION
        function nextFrame() {
            clearTimeout(autoplayTimer);
            if (currentIndex < frames.length - 1) loadFrame(currentIndex + 1);
            else flashVignette();
        }

        function prevFrame() {
            clearTimeout(autoplayTimer);
            if (currentIndex > 0) loadFrame(currentIndex - 1);
            else flashVignette();
        }

        function goToFrame(index) {
            clearTimeout(autoplayTimer);
            if (index !== currentIndex) loadFrame(index);
        }
        
        function flashVignette() {
            anime({
                targets: '.vignette',
                opacity: [0.8, 0],
                direction: 'alternate',
                duration: 200,
                easing: 'easeInOutQuad'
            });
        }

        // 5. AUTOPLAY LOGIC
        function toggleAutoPlay() {
            isAutoplay = !isAutoplay;
            const btn = document.getElementById('btn-autoplay');
            const icon = btn.querySelector('i');
            
            if (isAutoplay) {
                btn.classList.add('bg-emerald-500/30', 'border-emerald-500');
                icon.classList.replace('fa-play', 'fa-pause');
                if (currentIndex < frames.length - 1) {
                    autoplayTimer = setTimeout(nextFrame, 1000); // Start shortly
                } else {
                    // If at end, loop back to start
                    goToFrame(0);
                }
            } else {
                btn.classList.remove('bg-emerald-500/30', 'border-emerald-500');
                icon.classList.replace('fa-pause', 'fa-play');
                clearTimeout(autoplayTimer);
            }
        }

        // 6. INTERACTIVE CONTEXT DRAWER
        function toggleContext() {
            const drawer = document.getElementById('context-drawer');
            const btn = document.getElementById('insight-btn');
            const icon = btn.querySelector('i');
            
            isDrawerOpen = !isDrawerOpen;

            if (isDrawerOpen) {
                // Open State
                icon.classList.replace('fa-microchip', 'fa-times');
                btn.querySelector('div').classList.add('bg-emerald-500/20', 'border-emerald-500');
                
                // Drawer Slide Up
                anime({
                    targets: drawer,
                    translateY: ['120%', '0%'],
                    opacity:[0, 1],
                    duration: 800,
                    easing: 'easeOutExpo'
                });

                // Content Stagger Reveal
                anime({
                    targets: '.drawer-item',
                    translateY: [30, 0],
                    opacity: [0, 1],
                    duration: 800,
                    delay: anime.stagger(150, {start: 200}),
                    easing: 'easeOutExpo'
                });

                // Push UI up slightly on mobile (Parallax)
                if (window.innerWidth < 768) {
                    anime({ targets: 'footer', translateY: -10, opacity: 0.2, duration: 600, easing: 'easeOutExpo' });
                }

            } else {
                // Close State
                icon.classList.replace('fa-times', 'fa-microchip');
                btn.querySelector('div').classList.remove('bg-emerald-500/20', 'border-emerald-500');

                // Drawer Slide Down
                anime({
                    targets: drawer,
                    translateY: '120%',
                    opacity: 0,
                    duration: 500,
                    easing: 'easeInExpo'
                });

                anime({
                    targets: '.drawer-item',
                    opacity: 0,
                    duration: 300,
                    easing: 'linear'
                });

                if (window.innerWidth < 768) {
                    anime({ targets: 'footer', translateY: 0, opacity: 1, duration: 600, easing: 'easeOutExpo' });
                }
            }
        }

        // 7. SWIPE / GESTURE SUPPORT
        function setupTouch() {
            let touchstartX = 0;
            let touchendX = 0;
            const threshold = 50;
            
            const area = document.getElementById('touch-area');

            area.addEventListener('touchstart', e => { 
                touchstartX = e.changedTouches[0].screenX; 
            }, {passive: true});
            
            area.addEventListener('touchend', e => { 
                touchendX = e.changedTouches[0].screenX; 
                handleGesture(); 
            }, {passive: true});

            function handleGesture() {
                if (touchendX < touchstartX - threshold) nextFrame(); // Swipe Left
                if (touchendX > touchstartX + threshold) prevFrame(); // Swipe Right
            }
            
            // Keyboard nav
            document.addEventListener('keydown', (e) => {
                if(e.key === 'ArrowRight' || e.key === ' ') nextFrame();
                if(e.key === 'ArrowLeft') prevFrame();
                if(e.key === 'i') toggleContext();
            });
        }
    </script>
</body>
</html>