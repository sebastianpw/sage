<?php
// public/view_md.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; 

$spw = \App\Core\SpwBase::getInstance();
$docId = $_GET['id'] ?? null;
$download = $_GET['download'] ?? false;
$filterCatId = $_GET['category_id'] ?? ''; 
// New Sort Parameter (Default: Created Descending)
$filterSort = $_GET['sort'] ?? 'created_desc';

// ==========================================
// 1. READ MODE (Single Document)
// ==========================================
if ($docId) {
    // 1. Fetch Document
    $stmt = $pdo->prepare("SELECT * FROM documentations WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        $spw->renderLayout("<div class='alert alert-danger'>Document not found (ID: $docId)</div>", "404 Not Found");
        exit;
    }

    if ($download) {
        header('Content-Type: text/markdown');
        header('Content-Disposition: attachment; filename="' .  preg_replace('/[^a-z0-9]+/', '_', strtolower($doc['name'])) . '.md"');
        echo $doc['content'];
        exit;
    }

    // 2. Fetch Latest Audio
    $latestAudio = null;
    try {
        $tblCheck = $pdo->query("SHOW TABLES LIKE 'audios_2_documentations'");
        if ($tblCheck->rowCount() > 0) {
            $stmtAudio = $pdo->prepare("
                SELECT a.filename 
                FROM audios a 
                JOIN audios_2_documentations m ON a.id = m.from_id 
                WHERE m.to_id = ? 
                ORDER BY a.created_at DESC 
                LIMIT 1
            ");
            $stmtAudio->execute([$docId]);
            $audioRow = $stmtAudio->fetch(PDO::FETCH_ASSOC);
            if ($audioRow) {
                $latestAudio = $audioRow['filename'];
            }
        }
    } catch (Exception $e) {}

    // 3. Parse Markdown
    $currentReporting = error_reporting();
    error_reporting($currentReporting & ~E_DEPRECATED);
    $parsedown = new Parsedown();
    if (method_exists($parsedown, 'setSafeMode')) {
        $parsedown->setSafeMode(true);
    }
    $htmlContent = $parsedown->text($doc['content']);
    error_reporting($currentReporting);

    $pageTitle = htmlspecialchars($doc['name']);
    
    // Links (Preserve Category AND Sort)
    $backLink = "view_md.php";
    $queryParams = [];
    if ($filterCatId !== '') $queryParams['category_id'] = $filterCatId;
    if ($filterSort !== 'created_desc') $queryParams['sort'] = $filterSort;
    
    if (!empty($queryParams)) {
        $backLink .= '?' . http_build_query($queryParams);
    }

    $editLink = "edit_md.php?id=" . $docId;
    if (!empty($queryParams)) {
        $editLink .= "&" . http_build_query($queryParams);
    }

    ob_start();
    ?>
    <!-- THEME INIT SCRIPT -->
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('spw_theme');
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (theme === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Dynamic Markdown Styles -->
    <link id="md-css-light" rel="stylesheet" href="/vendor/github-markdown-css/github-markdown-light.css">
    <link id="md-css-dark" rel="stylesheet" href="/vendor/github-markdown-css/github-markdown-dark.css" disabled>

    <style>
        :root {
            --bg: #f6f8fa;
            --card: #ffffff;
            --border: #d0d7de;
            --text: #24292f;
            --text-muted: #57606a;
            --accent: #0969da;
            --green: #238636;
            --red: #da3633;
            --orange: #f59e0b;
            --overlay-bg: rgba(0,0,0,0.55);
            --focus-ring: rgba(9, 105, 218, 0.15);
            --card-elevation: 0 4px 14px rgba(0,0,0,0.08);
            --toc-max-height: 70vh;
            --blue-light-bg: rgba(9, 105, 218, 0.08);
        }
        :root[data-theme="dark"] {
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --green: #238636;
            --red: #da3633;
            --orange: #f59e0b;
            --overlay-bg: rgba(0,0,0,0.75);
            --focus-ring: rgba(88, 166, 255, 0.2);
            --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
            --blue-light-bg: rgba(88, 166, 255, 0.12);
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg: #0d1117;
                --card: #161b22;
                --border: #30363d;
                --text: #c9d1d9;
                --text-muted: #8b949e;
                --accent: #58a6ff;
                --green: #238636;
                --red: #da3633;
                --orange: #f59e0b;
                --overlay-bg: rgba(0,0,0,0.75);
                --focus-ring: rgba(88, 166, 255, 0.2);
                --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
                --blue-light-bg: rgba(88, 166, 255, 0.12);
            }
        }

        * { box-sizing: border-box; }
        body { 
            background-color: var(--bg) !important; 
            color: var(--text) !important; 
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            scroll-behavior: smooth; 
            touch-action: manipulation; 
            transition: background-color 0.2s, color 0.2s; 
            margin: 0;
        }
        
        .view-container { 
            max-width: 980px; 
            margin: 0 auto; 
            padding: 10px 14px 100px 14px; 
        }
        
        /* Markdown Container */
        .markdown-body { 
            box-sizing: border-box; 
            min-width: 200px; 
            max-width: 980px; 
            margin: 0 auto; 
            background-color: transparent !important; 
            color: var(--text) !important; 
        }
        .markdown-body img { max-width: 100%; height: auto; background-color: transparent; }
        .markdown-body table tr { background-color: var(--bg) !important; border-color: var(--border) !important; }
        .markdown-body table tr:nth-child(2n) { background-color: var(--card) !important; }
        
        /* Navigation Bar */
        .nav-bar { 
            margin-bottom: 20px; 
            padding: 8px 12px; 
            background: var(--card); 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 6px 11px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s, border-color 0.15s, color 0.15s, opacity 0.15s, transform 0.1s;
            height: 34px;
            flex: 0 0 auto;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { filter: brightness(1.08); }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn-sm { padding: 4px 8px; font-size: 0.78rem; height: 28px; }

        .input-control {
            width: 100%;
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 0.88rem;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .input-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        /* Floating Panels */
        .floating-panel {
            position: fixed; 
            z-index: 9999; 
            display: none; 
            flex-direction: column;
            background: var(--card); 
            border: 1px solid var(--border);
            border-radius: 10px; 
            box-shadow: var(--card-elevation);
            color: var(--text); 
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            will-change: transform, top, left; 
            touch-action: none; 
        }
        .floating-panel.dragging { transition: none; }
        .floating-panel:not(.dragging) { transition: transform 0.1s cubic-bezier(0.2, 0.8, 0.2, 1); }
        
        .panel-header { 
            padding: 9px 12px; 
            border-bottom: 1px solid var(--border); 
            cursor: grab; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            user-select: none;
            background: rgba(125,125,125,0.04); 
            touch-action: none; 
            border-radius: 10px 10px 0 0;
        }
        .panel-close { 
            background: none; 
            border: none; 
            color: var(--text-muted); 
            cursor: pointer; 
            padding: 4px; 
            margin-left: auto; 
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        .panel-close:hover { color: var(--text); background: var(--bg); }

        /* TOC */
        #floatingToc { top: 80px; right: 20px; width: 300px; max-height: var(--toc-max-height); }
        #tocArrow { display: inline-block; width: 14px; text-align: center; font-size: 12px; }
        #tocBody { padding: 10px; overflow-y: auto; scrollbar-width: thin; }
        
        ul#tocList { list-style: none; padding: 0; margin: 0; }
        ul#tocList li { margin-bottom: 2px; }
        ul#tocList li a { 
            display: block; 
            padding: 5px 8px; 
            font-size: 13px; 
            text-decoration: none; 
            color: var(--text); 
            border-radius: 5px;
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap;
            transition: background 0.15s, color 0.15s;
        }
        ul#tocList li a:hover { background: var(--blue-light-bg); color: var(--accent); }
        
        /* Audio Player */
        #floatingPlayer { top: 140px; right: 20px; width: 320px; height: auto; z-index: 9999; }
        #playerBody { padding: 14px; display: flex; flex-direction: column; gap: 10px; min-width: 290px; }
        audio.custom-audio { width: 100%; height: 36px; border-radius: 6px; outline: none; }
        
        html[data-theme="dark"] audio.custom-audio { filter: invert(0.9) hue-rotate(180deg); }
        @media (prefers-color-scheme: dark) { html:not([data-theme="light"]) audio.custom-audio { filter: invert(0.9) hue-rotate(180deg); } }

        .para-anchor { 
            opacity: 0; 
            margin-left: 8px; 
            cursor: pointer; 
            background: none; 
            border: none; 
            color: var(--text-muted); 
            font-size: 14px; 
            padding: 0 4px; 
            transition: opacity 0.2s, color 0.2s; 
        }
        p:hover .para-anchor { opacity: 1; }
        
        .auto-scroll-controls { 
            position: fixed; 
            bottom: 20px; 
            right: 20px; 
            z-index: 9000; 
            background: var(--card); 
            padding: 10px 12px; 
            border-radius: 10px; 
            border: 1px solid var(--border); 
            display: block; 
            box-shadow: var(--card-elevation); 
            width: 260px; 
        }
        .asc-row { display: flex; gap: 6px; margin-bottom: 8px; justify-content: space-between; }
        .asc-btn { 
            background: var(--bg); 
            border: 1px solid var(--border); 
            color: var(--text); 
            padding: 4px 8px; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 12px; 
            font-weight: 600; 
            flex: 1; 
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .asc-btn:hover { background: rgba(125,125,125,0.15); }
        .asc-btn.active { border-color: var(--accent); color: var(--accent); }
        
        @media (max-width: 900px) {
            #floatingToc { right: 10px; width: 260px; max-height: 50vh; }
            #floatingPlayer { right: 10px; left: 10px; width: auto; top: 70px; }
            .auto-scroll-controls { bottom: 10px; right: 10px; left: 10px; width: auto; }
        }
    </style>

    <div class="view-container">
        <!-- Nav -->
        <div class="nav-bar">
            <div>
                <a href="<?= $backLink ?>" class="btn btn-ghost" style="margin-left: 20px;" title="Back to Index"><i class="bi bi-arrow-left"></i> Index</a>
            </div>
            <div style="display:flex; gap:6px; align-items:center;">
                <?php if ($latestAudio): ?>
                    <button id="btnTogglePlayer" class="btn btn-ghost" onclick="togglePlayer()" title="Toggle Audio Player"><i class="bi bi-headphones"></i></button>
                <?php else: ?>
                    <button class="btn btn-ghost" title="No Audio Available" disabled style="opacity:0.35;"><i class="bi bi-headphones"></i></button>
                <?php endif; ?>

                <a href="?id=<?= $docId ?>&download=1" class="btn btn-ghost" title="Download"><i class="bi bi-download"></i></a>
                <a href="<?= $editLink ?>" class="btn btn-primary" title="Edit"><i class="bi bi-pencil"></i> Edit</a>
            </div>
        </div>

        <article class="markdown-body" id="markdown-content">
            <?php echo $htmlContent; ?>
        </article>
    </div>

    <!-- TOC Overlay -->
    <div id="floatingToc" class="floating-panel">
        <div id="tocHeader" class="panel-header" title="Drag to move">
            <span id="tocArrow">▾</span>
            <span style="font-weight:600; flex:1; font-size:0.85rem;"><i class="bi bi-list-nested"></i> Index</span>
            <button class="panel-close" onclick="document.getElementById('floatingToc').style.display='none'"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="tocBody">
            <input id="tocSearch" class="input-control" placeholder="Filter..." style="margin-bottom:8px; font-size:0.82rem; padding:5px 8px;">
            <ul id="tocList"></ul>
        </div>
    </div>

    <!-- Player Overlay -->
    <?php if ($latestAudio): ?>
    <div id="floatingPlayer" class="floating-panel">
        <div id="playerHeader" class="panel-header" title="Drag to move">
            <span style="font-size:1rem; margin-right:6px;"><i class="bi bi-headphones" style="color:var(--accent);"></i></span>
            <span style="font-weight:600; flex:1; font-size:0.85rem;">Audio Player</span>
            <button class="panel-close" onclick="togglePlayer()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="playerBody">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                <span style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-right:10px;">Latest audio</span>
                <div style="display:flex; gap:6px; align-items:center;">
                    <button id="btnRewind" class="btn btn-ghost btn-sm" title="-10s" style="padding:2px 6px; font-size:0.75rem;"><i class="bi bi-rewind"></i> 10s</button>
                    <button id="btnForward" class="btn btn-ghost btn-sm" title="+10s" style="padding:2px 6px; font-size:0.75rem;"><i class="bi bi-fast-forward"></i> 10s</button>
                    <button id="btnBookmark" class="btn btn-ghost btn-sm" title="Bookmark position" style="padding:2px 6px; font-size:0.75rem;"><i class="bi bi-bookmark"></i></button>
                </div>
            </div>
            <audio controls class="custom-audio" id="docAudio">
                <source src="<?= htmlspecialchars($latestAudio) ?>" type="audio/wav">
                Your browser does not support the audio element.
            </audio>
        </div>
    </div>
    <?php endif; ?>

    <!-- Auto Scroll -->
    <div id="autoScrollControls" class="auto-scroll-controls">
        <div class="asc-row">
            <button id="asc-start" class="asc-btn" title="Start Scrolling (S)">Start</button>
            <button id="asc-pause" class="asc-btn" title="Pause (Space)">Pause</button>
            <button id="asc-top" class="asc-btn" title="Reset">Top</button>
            <button id="asc-hide" class="asc-btn" style="flex:0; width:28px; padding:0;"><i class="bi bi-x-lg"></i></button>
        </div>
        <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted);">
            <span>Speed:</span>
            <input type="range" id="asc-speed" min="1" max="100" value="20" style="flex:1; accent-color:var(--accent);">
        </div>
    </div>

    <?php require_once 'modal_audio_details.php'; ?>

    <script>
        // --- Theme Switching Logic ---
        function updateMarkdownTheme() {
            const lightLink = document.getElementById('md-css-light');
            const darkLink = document.getElementById('md-css-dark');
            const root = document.documentElement;
            
            let isDark = false;
            if (root.getAttribute('data-theme') === 'dark') {
                isDark = true;
            } else if (root.getAttribute('data-theme') === 'light') {
                isDark = false;
            } else {
                isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            if (isDark) {
                darkLink.removeAttribute('disabled');
                lightLink.setAttribute('disabled', 'true');
            } else {
                lightLink.removeAttribute('disabled');
                darkLink.setAttribute('disabled', 'true');
            }
        }
        
        updateMarkdownTheme();

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((m) => { if(m.type === 'attributes' && m.attributeName === 'data-theme') updateMarkdownTheme(); });
        });
        observer.observe(document.documentElement, { attributes: true });
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateMarkdownTheme);

        document.addEventListener('DOMContentLoaded', () => {
            processContent(); 
            initToc(); 
            initAutoScroll();
            makeDraggable(document.getElementById('floatingToc'), document.getElementById('tocHeader'));
            
            // Audio Player Logic
            const player = document.getElementById('floatingPlayer');
            const audio = document.getElementById('docAudio');
            
            if(player && audio) { 
                makeDraggable(player, document.getElementById('playerHeader')); 
                
                const src = audio.querySelector('source').src;
                const key = 'spw_audio_bookmark_' + src.split('/').pop();
                
                audio.addEventListener('loadedmetadata', () => {
                    const savedTime = localStorage.getItem(key);
                    if(savedTime) { audio.currentTime = parseFloat(savedTime); }
                });

                const btnRewind = document.getElementById('btnRewind');
                const btnForward = document.getElementById('btnForward');
                if(btnRewind) btnRewind.onclick = () => audio.currentTime = Math.max(0, audio.currentTime - 10);
                if(btnForward) btnForward.onclick = () => audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);

                const btnBookmark = document.getElementById('btnBookmark');
                if(btnBookmark) {
                    btnBookmark.addEventListener('click', () => {
                        localStorage.setItem(key, audio.currentTime);
                        const originalHtml = btnBookmark.innerHTML;
                        btnBookmark.innerHTML = '<i class="bi bi-check2"></i>';
                        setTimeout(() => btnBookmark.innerHTML = originalHtml, 1000);
                    });
                }
            }
        });

        function togglePlayer() {
            const p = document.getElementById('floatingPlayer'), btn = document.getElementById('btnTogglePlayer');
            if(!p) return;
            if (p.style.display === 'none' || p.style.display === '') { p.style.display = 'flex'; btn.style.color = '#58a6ff'; } 
            else { p.style.display = 'none'; btn.style.color = ''; document.getElementById('docAudio').pause(); }
        }

        function makeDraggable(element, handle) {
            let dragging = false, pointerId = null, startX = 0, startY = 0, startLeft = 0, startTop = 0, downTs = 0; const TAP_MAX_MOVE = 6, TAP_MAX_TIME = 300;
            handle.addEventListener('pointerdown', (ev) => { if (ev.target.tagName === 'BUTTON' || (ev.button && ev.button !== 0)) return; handle.setPointerCapture(ev.pointerId); pointerId = ev.pointerId; dragging = true; element.classList.add('dragging'); startX = ev.clientX; startY = ev.clientY; const r = element.getBoundingClientRect(); startLeft = r.left; startTop = r.top; downTs = Date.now(); ev.preventDefault(); });
            window.addEventListener('pointermove', (ev) => { if (!dragging || ev.pointerId !== pointerId) return; element.style.transform = `translate3d(${ev.clientX - startX}px, ${ev.clientY - startY}px, 0)`; ev.preventDefault(); }, { passive: false });
            window.addEventListener('pointerup', (ev) => { if (!dragging || ev.pointerId !== pointerId) return; handle.releasePointerCapture(pointerId); dragging = false; pointerId = null; element.classList.remove('dragging'); const dx = ev.clientX - startX, dy = ev.clientY - startY; const r = element.getBoundingClientRect(), w = window.innerWidth, h = window.innerHeight; let left = Math.max(8, Math.min(startLeft + dx, w - r.width - 8)), top = Math.max(8, Math.min(startTop + dy, h - r.height - 8)); element.style.left = left + 'px'; element.style.top = top + 'px'; element.style.right = 'auto'; element.style.transform = ''; 
                if (element.id === 'floatingToc' && Math.hypot(dx, dy) <= TAP_MAX_MOVE && (Date.now() - downTs) <= TAP_MAX_TIME) { const b = document.getElementById('tocBody'), a = document.getElementById('tocArrow'); if (b.style.display === 'none') { b.style.display = 'block'; a.textContent = '▾'; } else { b.style.display = 'none'; a.textContent = '▸'; } }
            });
            window.addEventListener('pointercancel', (ev) => { if (dragging && ev.pointerId === pointerId) { dragging = false; pointerId = null; element.classList.remove('dragging'); element.style.transform = ''; } });
        }

        function processContent() {
            const content = document.getElementById('markdown-content');
            content.querySelectorAll('h1,h2,h3,h4').forEach((h, idx) => { if(!h.id) { h.id = h.textContent.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); if(!h.id) h.id = 'h-' + idx; } });
            content.querySelectorAll('p').forEach((p, idx) => { const id = 'p-' + idx; if(!p.id) p.id = id; const btn = document.createElement('button'); btn.innerHTML = '#'; btn.className = 'para-anchor'; btn.onclick = (e) => { e.stopPropagation(); navigator.clipboard.writeText(location.href.split('#')[0] + '#' + p.id); history.replaceState(null, null, '#' + p.id); btn.style.color = 'var(--accent)'; setTimeout(() => btn.style.color = '', 500); }; p.appendChild(btn); });
            if(location.hash) { const el = document.getElementById(location.hash.substring(1)); if(el) setTimeout(() => el.scrollIntoView({block: 'center'}), 100); }
        }
        function initToc() {
            const list = document.getElementById('tocList'), content = document.getElementById('markdown-content'), headers = content.querySelectorAll('h1,h2,h3');
            if (headers.length > 0) {
                document.getElementById('floatingToc').style.display = 'flex';
                headers.forEach(h => { const li = document.createElement('li'); li.className = h.tagName.toLowerCase(); const a = document.createElement('a'); a.href = '#' + h.id; a.textContent = h.textContent; a.onclick = (e) => { e.preventDefault(); document.getElementById(h.id).scrollIntoView({behavior:'smooth', block:'start'}); history.replaceState(null,null, '#'+h.id); }; li.appendChild(a); list.appendChild(li); });
            }
            document.getElementById('tocSearch').addEventListener('input', (e) => { const v = e.target.value.toLowerCase(); list.querySelectorAll('li').forEach(li => { li.style.display = li.textContent.toLowerCase().includes(v) ? '' : 'none'; }); });
        }
        function initAutoScroll() {
            let t = null, p = true; const s = document.getElementById('asc-speed'), b1 = document.getElementById('asc-start'), b2 = document.getElementById('asc-pause');
            function step() { if(!p) { window.scrollBy(0, 1); t = setTimeout(step, 105 - parseInt(s.value)); } }
            b1.onclick = () => { if(p) { p = false; step(); b1.classList.add('active'); b2.classList.remove('active'); } };
            b2.onclick = () => { p = true; clearTimeout(t); b1.classList.remove('active'); b2.classList.add('active'); };
            document.getElementById('asc-top').onclick = () => { p = true; clearTimeout(t); window.scrollTo({top:0, behavior:'smooth'}); b1.classList.remove('active'); b2.classList.remove('active'); };
            document.getElementById('asc-hide').onclick = () => { document.getElementById('autoScrollControls').style.display = 'none'; };
            window.addEventListener('keydown', (e) => { if(e.target.tagName === 'INPUT') return; if(e.code === 'Space') { e.preventDefault(); b2.click(); } if(e.key === 's' || e.key === 'S') { b1.click(); } });
        }
    </script>

    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, $pageTitle);
    exit;
}

// ==========================================
// 2. INDEX MODE
// ==========================================

$cats = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Determine Sort Order
$orderByClause = "d.created_at DESC";
switch ($filterSort) {
    case 'created_asc': $orderByClause = "d.created_at ASC"; break;
    case 'updated_desc': $orderByClause = "d.updated_at DESC"; break;
    case 'name_asc': $orderByClause = "d.name ASC"; break;
    case 'name_desc': $orderByClause = "d.name DESC"; break;
    case 'created_desc': default: $orderByClause = "d.created_at DESC"; break;
}

$query = "SELECT d.id, d.name, d.updated_at, d.regenerate_audios, c.name as category, c.id as cat_id,
          da.id as analysis_id 
          FROM documentations d 
          LEFT JOIN documentation_categories c ON d.category_id = c.id 
          LEFT JOIN md_doc_analysis da ON d.id = da.doc_id
          WHERE d.is_active = 1 
          ORDER BY d.category_id ASC, " . $orderByClause;

$stmt = $pdo->query($query);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach($docs as $d) {
    $cId = $d['cat_id'] ?: 0;
    $cName = $d['category'] ?: 'Uncategorized';
    if(!isset($grouped[$cId])) {
        $grouped[$cId] = ['name' => $cName, 'docs' => []];
    }
    $grouped[$cId]['docs'][] = $d;
}

$newLink = "edit_md.php";
$linkParams = [];
if ($filterCatId !== '') $linkParams['category_id'] = $filterCatId;
if ($filterSort !== 'created_desc') $linkParams['sort'] = $filterSort;
if (!empty($linkParams)) $newLink .= '?' . http_build_query($linkParams);

$pageTitle = "Documentation Index";
ob_start();
?>
<!-- THEME INIT SCRIPT -->
<script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        } catch (e) {}
    })();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    :root {
        --bg: #f6f8fa;
        --card: #ffffff;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.55);
        --focus-ring: rgba(9, 105, 218, 0.15);
        --card-elevation: 0 4px 14px rgba(0,0,0,0.08);
        --blue-light-bg: rgba(9, 105, 218, 0.08);
    }
    :root[data-theme="dark"] {
        --bg: #0d1117;
        --card: #161b22;
        --border: #30363d;
        --text: #c9d1d9;
        --text-muted: #8b949e;
        --accent: #58a6ff;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.75);
        --focus-ring: rgba(88, 166, 255, 0.2);
        --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
        --blue-light-bg: rgba(88, 166, 255, 0.12);
    }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --green: #238636;
            --red: #da3633;
            --orange: #f59e0b;
            --overlay-bg: rgba(0,0,0,0.75);
            --focus-ring: rgba(88, 166, 255, 0.2);
            --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
            --blue-light-bg: rgba(88, 166, 255, 0.12);
        }
    }

    * { box-sizing: border-box; }
    body { 
        background-color: var(--bg) !important; 
        color: var(--text) !important; 
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 10px; 
        margin: 0;
        transition: background-color 0.2s, color 0.2s; 
    }
    .container { max-width: 820px; margin: 0 auto; }
    
    .header-row { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 16px; 
        border-bottom: 1px solid var(--border); 
        padding-bottom: 10px; 
        gap: 8px;
    }

    .btn {
        padding: 6px 11px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.15s, border-color 0.15s, color 0.15s, opacity 0.15s, transform 0.1s;
        height: 34px;
        flex: 0 0 auto;
    }
    .btn:active { transform: scale(0.97); }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { filter: brightness(1.08); }
    .btn-save { background: var(--green); color: #fff; }
    .btn-save:hover { filter: brightness(1.08); }
    .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
    .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
    .btn-sm { padding: 4px 8px; font-size: 0.78rem; height: 28px; }

    .input-control {
        width: 100%;
        padding: 7px 10px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.88rem;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .input-control:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--focus-ring);
    }
    
    .filters-area { 
        background: var(--card); 
        border: 1px solid var(--border); 
        border-radius: 8px; 
        padding: 10px; 
        margin-bottom: 20px; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .cat-group { margin-bottom: 22px; }
    .cat-title { 
        font-size: 0.88rem; 
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted); 
        margin-bottom: 8px; 
        border-bottom: 1px solid var(--border); 
        padding-bottom: 4px; 
    }
    
    .doc-list { list-style: none; padding: 0; margin: 0; }
    .doc-item { 
        background: var(--card); 
        margin-bottom: 6px; 
        padding: 8px 12px; 
        border-radius: 6px; 
        border: 1px solid var(--border); 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        transition: border-color 0.15s, box-shadow 0.15s; 
    }
    .doc-item:hover { 
        border-color: var(--accent); 
        box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
    }
    .doc-link { 
        font-size: 0.92rem; 
        font-weight: 600; 
        color: var(--text); 
        text-decoration: none; 
        flex: 1; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        margin-right: 10px;
        transition: color 0.15s;
    }
    .doc-link:hover { color: var(--accent); }
    
    .meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
    .icon-btn { 
        text-decoration: none; 
        border: none; 
        background: none; 
        cursor: pointer; 
        padding: 4px 6px; 
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: color 0.15s, background 0.15s, transform 0.1s; 
    }
    .icon-btn:hover { color: var(--accent); background: var(--bg); transform: scale(1.08); }
    .icon-btn.btn-delete:hover { color: var(--red); }
    
    .audio-chk { cursor: pointer; width: 15px; height: 15px; accent-color: var(--orange); margin: 0 4px 0 0; }
    .doc-select-chk { cursor: pointer; width: 15px; height: 15px; accent-color: var(--accent); margin: 0 4px 0 2px; }
    .audio-wrapper { display: flex; align-items: center; border-right: 1px solid var(--border); padding-right: 8px; margin-right: 4px; }

    /* Drag & Drop Upload Zone */
    .upload-drop-zone {
        border: 2px dashed var(--border);
        border-radius: 8px;
        padding: 22px 16px;
        text-align: center;
        background: var(--bg);
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
        margin-top: 10px;
    }
    .upload-drop-zone:hover, .upload-drop-zone.drag-over {
        border-color: var(--accent);
        background: var(--blue-light-bg);
    }
    .upload-prog-wrap {
        width: 100%;
        height: 6px;
        background: rgba(125,125,125,0.2);
        border-radius: 3px;
        overflow: hidden;
        margin-top: 10px;
        display: none;
    }
    .upload-prog-fill {
        width: 0%;
        height: 100%;
        background: var(--accent);
        transition: width 0.15s ease;
    }

    #toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99999;
        background: var(--card);
        color: var(--text);
        border: 1px solid var(--border);
        border-left: 4px solid var(--green);
        border-radius: 6px;
        padding: 10px 16px;
        font-size: 0.88rem;
        display: none;
        box-shadow: var(--card-elevation);
    }

    @media (max-width: 600px) {
        .doc-item { padding: 8px 10px; }
        .doc-link { font-size: 0.88rem; }
    }
</style>

<div class="container">
    <div class="header-row">
        <div style="display:flex; align-items:center; gap:8px; margin-left: 40px;">
            <button class="btn btn-ghost btn-sm runBtn scheduler" data-id="40" title="Run TTS Scheduler"><i class="bi bi-arrow-repeat"></i></button>
            <button class="btn btn-ghost btn-sm" onclick="toggleLogsModal()" title="View Logs"><i class="bi bi-journal-text"></i></button>
            <button id="btnMassDelete" class="btn btn-ghost btn-sm" onclick="massDeleteDocs()" title="Delete Selected Documents" style="color:var(--red);"><i class="bi bi-trash"></i></button>
        </div>

        <div style="display:flex; gap:6px; align-items:center;">
            <button id="btnImportMd" class="btn btn-ghost btn-sm"><i class="bi bi-upload"></i> Import</button>
            <button id="btnExportMd" class="btn btn-ghost btn-sm" onclick="openMdExportModal()"><i class="bi bi-download"></i> Export</button>
            <a href="<?= $newLink ?>" class="btn btn-save btn-sm btn-create"><i class="bi bi-plus-lg"></i> New</a>
        </div>
    </div>

    <!-- Filter Area -->
    <div class="filters-area">
        <input id="docSearch" class="input-control" placeholder="Filter text..." value="" style="margin-bottom:8px;">
        <div style="display:flex; gap:8px;">
            <select id="catFilter" class="input-control" style="flex:1;">
                <option value="">All Categories</option>
                <?php foreach($cats as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($filterCatId == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
                <option value="0" <?= ($filterCatId === '0') ? 'selected' : '' ?>>Uncategorized</option>
            </select>

            <select id="sortFilter" class="input-control" style="flex:1;">
                <option value="created_desc" <?= $filterSort === 'created_desc' ? 'selected' : '' ?>>📅 Created (Newest)</option>
                <option value="created_asc" <?= $filterSort === 'created_asc' ? 'selected' : '' ?>>📅 Created (Oldest)</option>
                <option value="updated_desc" <?= $filterSort === 'updated_desc' ? 'selected' : '' ?>>📝 Updated</option>
                <option value="name_asc" <?= $filterSort === 'name_asc' ? 'selected' : '' ?>>🔤 Name (A-Z)</option>
                <option value="name_desc" <?= $filterSort === 'name_desc' ? 'selected' : '' ?>>🔤 Name (Z-A)</option>
            </select>
        </div>
    </div>

    <div id="docsContainer">
        <?php if(empty($grouped)): ?>
            <div style="padding:40px; text-align:center; color:var(--text-muted); font-size:0.9rem;">No documents found.</div>
        <?php else: ?>
            <?php foreach($grouped as $catId => $group): ?>
                <div class="cat-group" data-cat-id="<?= $catId ?>">
                    <div class="cat-title"><?php echo htmlspecialchars($group['name']); ?></div>
                    <ul class="doc-list">
                        <?php foreach($group['docs'] as $item): 
                            $baseParams = [];
                            if ($filterCatId !== '') $baseParams['category_id'] = $filterCatId;
                            if ($filterSort !== 'created_desc') $baseParams['sort'] = $filterSort;

                            $itemEditLink = "edit_md.php?id=" . $item['id'];
                            if (!empty($baseParams)) $itemEditLink .= "&" . http_build_query($baseParams);

                            $itemReadLink = "?id=" . $item['id'];
                            if (!empty($baseParams)) $itemReadLink .= "&" . http_build_query($baseParams);
                        ?>
                            <li class="doc-item" id="row-<?= $item['id'] ?>">
                                <!-- ICON AREA -->
                                <?php if(!empty($item['analysis_id'])): ?>
                                    <a href="view_curated_docs.php?doc_id=<?= $item['id'] ?>" style="margin-right:8px; text-decoration:none;" title="View Curated Analysis">
                                        <i class="bi bi-journal-bookmark-fill" style="color:var(--orange); font-size:1.05rem;"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="margin-right:8px;">
                                        <i class="bi bi-file-earmark-text" style="color:var(--text-muted); font-size:1.05rem;"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- DOCUMENT LINK -->
                                <a href="<?= $itemReadLink ?>" class="doc-link">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>

                                <div class="meta">
                                    <span style="margin-right:6px; opacity:0.6; display:none; sm:display:inline;"><?= date('M d', strtotime($item['updated_at'])) ?></span>
                                    
                                    <div class="audio-wrapper" title="Check to Regenerate Audio">
                                        <input type="checkbox" class="audio-chk" 
                                               onchange="toggleAudio(this, <?= $item['id'] ?>)" 
                                               <?= $item['regenerate_audios'] ? 'checked' : '' ?>>
                                        <i class="bi bi-volume-up" style="font-size:0.95rem;"></i>
                                    </div>

                                    <!-- Mass selection checkbox right next to regenerate checkbox -->
                                    <input type="checkbox" class="doc-select-chk" data-id="<?= $item['id'] ?>" title="Select document for Export / Mass Delete">

                                    <a href="?id=<?= $item['id'] ?>&download=1" class="icon-btn" title="Download"><i class="bi bi-download"></i></a>
                                    <a href="<?= $itemEditLink ?>" class="icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <button onclick="deleteDoc(<?= $item['id'] ?>)" class="icon-btn btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="toast"></div>

<script>
    // DEBOUNCED SERVER-SIDE SEARCH LOGIC
    let currentSearchIds = null;
    let debounceTimer;

    const searchInput = document.getElementById('docSearch');
    const catSelect = document.getElementById('catFilter');
    const sortSelect = document.getElementById('sortFilter');
    
    function renderList() {
        const cat = catSelect.value;
        const txt = searchInput.value.trim();
        
        document.querySelectorAll('.cat-group').forEach(group => {
            const groupCatId = group.getAttribute('data-cat-id');
            const isCatMatch = (cat === "") || (groupCatId === cat);
            
            if (!isCatMatch) {
                group.style.display = 'none';
                return;
            }

            let visibleCount = 0;
            group.querySelectorAll('.doc-item').forEach(item => {
                const itemId = parseInt(item.id.replace('row-', ''));
                
                let isSearchMatch = true;
                if (txt.length > 0 && currentSearchIds !== null) {
                    isSearchMatch = currentSearchIds.includes(itemId);
                }

                if (isSearchMatch) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            group.style.display = (visibleCount > 0) ? 'block' : 'none';
        });
        
        const url = new URL(window.location);
        if (cat) url.searchParams.set('category_id', cat); else url.searchParams.delete('category_id');
        window.history.replaceState({}, '', url);
        updateLinks(cat);
    }

    function updateLinks(catId) {
        const currentUrl = new URL(window.location);
        const sortVal = currentUrl.searchParams.get('sort');

        document.querySelectorAll('a').forEach(a => {
            let href = a.getAttribute('href');
            if(!href) return;
            if (href.includes('edit_md.php') || (href.includes('view_md.php') && href.includes('id=')) || href.startsWith('?id=')) {
                let u = new URL(a.href, window.location.origin);
                
                if (catId) u.searchParams.set('category_id', catId);
                else u.searchParams.delete('category_id');
                
                if (sortVal) u.searchParams.set('sort', sortVal);
                else u.searchParams.delete('sort');
                
                a.href = u.toString();
            }
            if (a.classList.contains('btn-create')) {
                let u = new URL(a.href, window.location.origin);
                if (catId) u.searchParams.set('category_id', catId);
                else u.searchParams.delete('category_id');
                
                if (sortVal) u.searchParams.set('sort', sortVal);
                
                a.href = u.toString();
            }
        });
    }

    catSelect.addEventListener('change', renderList);
    
    sortSelect.addEventListener('change', function() {
        const url = new URL(window.location);
        url.searchParams.set('sort', this.value);
        window.location.href = url.toString();
    });

    searchInput.addEventListener('input', () => {
        const val = searchInput.value.trim();
        
        if (val === '') {
            currentSearchIds = null;
            renderList();
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetch(`api_md.php?action=search&q=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        currentSearchIds = res.ids.map(Number);
                        renderList();
                    }
                });
        }, 300);
    });

    if(searchInput.value.trim() !== '') {
        searchInput.dispatchEvent(new Event('input'));
    } else {
        renderList();
    }

    function toggleAudio(cb, id) {
        const state = cb.checked ? 1 : 0;
        cb.style.opacity = '0.5';
        fetch('api_md.php?action=toggle_audio', { method: 'POST', body: JSON.stringify({id: id, state: state}) })
        .then(r => r.json()).then(res => { cb.style.opacity = '1'; if(res.status !== 'success') { cb.checked = !cb.checked; alert('Failed to update DB'); } })
        .catch(e => { cb.style.opacity = '1'; cb.checked = !cb.checked; });
    }

    function deleteDoc(id) {
        if(!confirm('Are you sure you want to delete this document?')) return;
        fetch('api_md.php?action=delete', { method: 'POST', body: JSON.stringify({id: id}) })
        .then(r => r.json()).then(res => { if(res.status === 'success') { const row = document.getElementById('row-' + id); if(row) { row.style.opacity = '0'; setTimeout(() => row.remove(), 400); } } else { alert('Error: ' + res.message); } });
    }

    // Mass Delete Selected Docs
    async function massDeleteDocs() {
        const checkedBoxes = Array.from(document.querySelectorAll('.doc-select-chk:checked'));
        if (checkedBoxes.length === 0) {
            alert('No documents selected.');
            return;
        }

        const count = checkedBoxes.length;
        if (!confirm(`Are you sure you want to delete ${count} selected document${count > 1 ? 's' : ''}?`)) {
            return;
        }

        const btn = document.getElementById('btnMassDelete');
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

        let deletedCount = 0;
        for (const cb of checkedBoxes) {
            const id = parseInt(cb.dataset.id);
            try {
                const res = await fetch('api_md.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                }).then(r => r.json());

                if (res && res.status === 'success') {
                    deletedCount++;
                    const row = document.getElementById('row-' + id);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-15px)';
                        setTimeout(() => row.remove(), 350);
                    }
                }
            } catch(e) {
                console.error('Failed to delete doc #' + id, e);
            }
        }

        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (deletedCount > 0) {
            showToast(`Deleted ${deletedCount} document${deletedCount > 1 ? 's' : ''}`);
        } else {
            alert('Failed to delete selected documents.');
        }
    }

    let toastTimer;
    function showToast(msg, isError = false) {
        const el = document.getElementById('toast');
        if (!el) return;
        el.textContent = msg;
        el.style.borderLeftColor = isError ? 'var(--red)' : 'var(--green)';
        el.style.display = 'block';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.style.display = 'none'; }, 2800);
    }

    document.addEventListener('click', function(e) {
        if (e.target.closest('.runBtn')) {
            const btn = e.target.closest('.runBtn');
            const id = btn.getAttribute('data-id');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            
            const formData = new FormData();
            formData.append('action', 'run_now');
            formData.append('id', id);

            fetch('scheduler_view.php', { method: 'POST', body: formData })
            .then(r => r.text())
            .then(res => {
                btn.innerHTML = original;
                if(res.trim() === 'success') { alert('Task scheduled!'); } 
                else { alert('Failed: ' + res); }
            })
            .catch(err => {
                btn.innerHTML = original;
                alert('Error connecting');
            });
        }
    });

    (function(){
        function createModal(id, iframeSrc){
            let modal = $('#' + id);
            if(modal.length) return modal;

            modal = $(`
                <div id="${id}" style="position:fixed;top:0;left:0;width:100%;height:100%;
                    background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:9999;display:flex;justify-content:center;align-items:center;">
                    <div style="width:85%;max-width:960px;height:80%;background:var(--card);padding:20px;position:relative;display:flex;flex-direction:column;border:1px solid var(--border);border-radius:10px;box-shadow:var(--card-elevation);">
                        <button class="close-btn" style="position:absolute;top:12px;right:12px;
                            background:transparent;color:var(--text-muted);border:none;padding:6px;cursor:pointer;font-size:1.1rem;line-height:1;"><i class="bi bi-x-lg"></i></button>
                        <iframe src="${iframeSrc}" frameborder="0" style="flex:1;width:100%;background:var(--bg);border-radius:6px;border:1px solid var(--border);"></iframe>
                    </div>
                </div>
            `).hide();

            modal.find('.close-btn').click(()=>modal.fadeOut());
            $('body').append(modal);
            return modal;
        }

        window.toggleLogsModal = function(){
            if (typeof $ === 'undefined') { alert('jQuery not loaded'); return; }
            const modal = createModal('floatool-logs-modal', 'view_scheduler_log.php');
            modal.fadeToggle();
        };
    })();
</script>

<!-- ===== MD IMPORT MODAL ===== -->
<style>
    #mdImportModal { 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: var(--overlay-bg); 
        backdrop-filter: blur(4px); 
        -webkit-backdrop-filter: blur(4px);
        z-index: 99999; 
        display: none; 
        align-items: center; 
        justify-content: center; 
        padding: 12px;
    }
    #mdImportModal .box { 
        width: 100%; 
        max-width: 600px; 
        background: var(--card); 
        border: 1px solid var(--border); 
        padding: 18px 20px; 
        border-radius: 10px; 
        color: var(--text); 
        box-shadow: var(--card-elevation); 
    }
    #mdImportModal .box h3 { margin-top: 0; font-size: 1.05rem; display: flex; align-items: center; gap: 8px; }
    #mdImportModal .row { display: flex; gap: 8px; align-items: center; margin-top: 12px; flex-wrap: wrap; }
    #mdImportModal .msg { margin-top: 10px; font-size: 0.85rem; color: var(--text-muted); }
</style>

<div id="mdImportModal" aria-hidden="true">
    <div class="box" role="dialog" aria-modal="true" aria-labelledby="mdImportTitle">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 id="mdImportTitle"><i class="bi bi-upload" style="color:var(--accent);"></i> Import Markdown</h3>
            <button id="mdImportClose" class="btn btn-ghost btn-sm" title="Close"><i class="bi bi-x-lg"></i></button>
        </div>

        <p style="margin:8px 0 0 0; color:var(--text-muted); font-size:0.85rem;">Select single or multiple <strong>.md</strong> / <strong>.markdown</strong> / <strong>.txt</strong> files to batch import. Filenames will become document titles.</p>

        <!-- Drag & Drop Zone -->
        <div class="upload-drop-zone" id="mdDropZone">
            <i class="bi bi-cloud-arrow-up" style="font-size: 2rem; color: var(--accent);"></i>
            <div id="mdFileCountLabel" style="font-size: 0.9rem; font-weight: 600; margin-top: 6px;">Click or Drag & Drop Markdown files here</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Supports multiple files (max 5MB each)</div>
        </div>
        <input id="mdImportFile" type="file" multiple accept=".md,.markdown,text/markdown,text/plain,.txt" style="display:none;">

        <!-- Progress bar indicator -->
        <div class="upload-prog-wrap" id="mdProgWrap">
            <div class="upload-prog-fill" id="mdProgFill"></div>
        </div>
        <div id="mdProgText" style="font-size:0.8rem; color:var(--text); margin-top:6px; font-weight:600; min-height:16px;"></div>

        <div class="row" style="margin-top:10px;">
            <label style="font-size:0.82rem; font-weight:600; color:var(--text-muted); width:100%;">Assign to Category:</label>
            <select id="mdImportCat" class="input-control" style="flex: 1; min-width: 180px;">
                <option value="">No category (Uncategorized)</option>
                <?php foreach($cats as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($filterCatId == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
                <option value="0" <?= ($filterCatId === '0') ? 'selected' : '' ?>>Uncategorized</option>
            </select>
        </div>

        <div class="row" style="justify-content:flex-end; margin-top:14px;">
            <button id="mdImportCancel" class="btn btn-ghost btn-sm">Cancel</button>
            <button id="mdImportUpload" class="btn btn-save btn-sm"><i class="bi bi-upload"></i> Upload</button>
        </div>

        <div class="msg" id="mdImportMsg" aria-live="polite"></div>
    </div>
</div>

<script>
    (function(){
        const currentCategory = "<?= htmlspecialchars($filterCatId, ENT_QUOTES) ?>";

        const btnImport = document.getElementById('btnImportMd');
        const modal = document.getElementById('mdImportModal');
        const closeBtn = document.getElementById('mdImportClose');
        const cancelBtn = document.getElementById('mdImportCancel');
        const uploadBtn = document.getElementById('mdImportUpload');
        const fileInput = document.getElementById('mdImportFile');
        const dropZone = document.getElementById('mdDropZone');
        const catSelect = document.getElementById('mdImportCat');
        const msgEl = document.getElementById('mdImportMsg');
        const progWrap = document.getElementById('mdProgWrap');
        const progFill = document.getElementById('mdProgFill');
        const progText = document.getElementById('mdProgText');

        let isUploading = false;

        btnImport && btnImport.addEventListener('click', function(){
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            if(currentCategory !== '') {
                const opt = Array.from(catSelect.options).find(o => o.value === currentCategory);
                if(opt) opt.selected = true;
            }
            resetModal();
        });

        function resetModal() {
            msgEl.textContent = '';
            fileInput.value = '';
            progWrap.style.display = 'none';
            progFill.style.width = '0%';
            progText.textContent = '';
            uploadBtn.disabled = false;
            cancelBtn.disabled = false;
            closeBtn.style.opacity = '1';
            closeBtn.style.pointerEvents = 'auto';
            uploadBtn.innerHTML = '<i class="bi bi-upload"></i> Upload';
            isUploading = false;
            updateFileLabel();
        }

        function closeModal() {
            if (isUploading) return;
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        closeBtn && closeBtn.addEventListener('click', closeModal);
        cancelBtn && cancelBtn.addEventListener('click', closeModal);

        dropZone && dropZone.addEventListener('click', () => {
            if (!isUploading) fileInput.click();
        });

        dropZone && dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (!isUploading) dropZone.classList.add('drag-over');
        });

        dropZone && dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });

        dropZone && dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            if (isUploading) return;
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileLabel();
            }
        });

        fileInput && fileInput.addEventListener('change', updateFileLabel);

        function updateFileLabel() {
            const files = fileInput.files;
            const count = files ? files.length : 0;
            const labelEl = document.getElementById('mdFileCountLabel');
            if (!labelEl) return;
            if (count === 0) {
                labelEl.textContent = 'Click or Drag & Drop Markdown files here';
            } else if (count === 1) {
                labelEl.textContent = files[0].name;
            } else {
                labelEl.textContent = `${count} files selected`;
            }
        }

        uploadBtn && uploadBtn.addEventListener('click', async function(){
            const allFiles = Array.from(fileInput.files || []);
            if(allFiles.length === 0) { 
                msgEl.textContent = 'No files selected.'; 
                return; 
            }

            const allowed = ['md','markdown','txt'];
            const validFiles = allFiles.filter(f => {
                const ext = (f.name.split('.').pop() || '').toLowerCase();
                return allowed.includes(ext) && f.size <= 5 * 1024 * 1024;
            });

            if (validFiles.length === 0) {
                msgEl.textContent = 'No valid .md/.markdown/.txt files found (max 5MB each).';
                return;
            }

            if (validFiles.length < allFiles.length) {
                msgEl.textContent = `${allFiles.length - validFiles.length} file(s) ignored (invalid type or >5MB).`;
            }

            isUploading = true;
            uploadBtn.disabled = true;
            cancelBtn.disabled = true;
            closeBtn.style.opacity = '0.3';
            closeBtn.style.pointerEvents = 'none';
            progWrap.style.display = 'block';

            const catVal = catSelect.value;
            const total = validFiles.length;
            let successes = 0;
            let errors = 0;

            for (let i = 0; i < total; i++) {
                const f = validFiles[i];
                const currentNum = i + 1;
                
                progText.textContent = `Uploading ${currentNum}/${total}: ${f.name}`;
                const pct = Math.round((i / total) * 100);
                progFill.style.width = pct + '%';
                uploadBtn.innerHTML = `<i class="bi bi-hourglass-split"></i> ${currentNum}/${total}`;

                const fd = new FormData();
                fd.append('file', f);
                if (catVal !== '') fd.append('category_id', catVal);

                try {
                    await new Promise(resolve => {
                        fetch('api_md.php?action=import_md', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(res => {
                            if(res.status === 'success') {
                                successes++;
                            } else {
                                errors++;
                            }
                            resolve();
                        })
                        .catch(() => {
                            errors++;
                            resolve();
                        });
                    });
                } catch(e) {
                    errors++;
                }
            }

            progFill.style.width = '100%';
            progText.textContent = `Completed: ${successes} imported${errors > 0 ? `, ${errors} failed` : ''}.`;
            msgEl.textContent = 'Refreshing list...';

            setTimeout(() => {
                window.location.reload();
            }, 700);
        });

        window.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
        });
    })();
</script>
<!-- ===== END MD IMPORT MODAL ===== -->

<!-- ===== MD EXPORT TREE MODAL ===== -->
<style>
.mde-modal-bg { 
    position: fixed; 
    inset: 0; 
    background: var(--overlay-bg); 
    backdrop-filter: blur(4px); 
    -webkit-backdrop-filter: blur(4px);
    display: none; 
    align-items: center; 
    justify-content: center; 
    z-index: 99999; 
    padding: 12px;
}
.mde-modal-bg.open { display: flex; }
.mde-modal { 
    width: min(780px, 96vw); 
    max-height: 90vh; 
    background: var(--card); 
    border: 1px solid var(--border); 
    border-radius: 10px; 
    display: flex; 
    flex-direction: column; 
    overflow: hidden; 
    box-shadow: var(--card-elevation); 
}
.mde-header { 
    padding: 14px 18px; 
    border-bottom: 1px solid var(--border); 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    flex-shrink: 0; 
}
.mde-header h3 { margin: 0; font-size: 1.05rem; flex: 1; display: flex; align-items: center; gap: 8px; }
.mde-close { 
    background: none; 
    border: none; 
    cursor: pointer; 
    color: var(--text-muted); 
    font-size: 1rem; 
    padding: 4px; 
    border-radius: 4px; 
    transition: color 0.15s, background 0.15s; 
    display: flex;
    align-items: center;
    justify-content: center;
}
.mde-close:hover { color: var(--text); background: var(--bg); }
.mde-body { 
    padding: 14px 18px; 
    display: flex; 
    flex-direction: column; 
    gap: 12px; 
    overflow-y: auto; 
    flex: 1; 
    min-height: 0; 
}
.mde-desc { font-size: 0.8rem; color: var(--text-muted); line-height: 1.45; padding: 0 2px; margin: 0; }
.mde-picker-header { 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    padding: 8px 12px; 
    border-bottom: 1px solid var(--border); 
    flex-shrink: 0; 
    background: var(--card); 
}
.mde-picker-header input { 
    flex: 1; 
    padding: 6px 10px; 
    border: 1px solid var(--border); 
    border-radius: 6px; 
    background: var(--bg); 
    color: var(--text); 
    font-size: 0.85rem; 
}
.mde-picker-header input:focus { outline: none; border-color: var(--accent); }
.mde-picker-select-all { 
    background: none; 
    border: none; 
    color: var(--accent); 
    font-size: 0.78rem; 
    cursor: pointer; 
    padding: 0; 
    font-weight: 600; 
}
.mde-picker-select-all:hover { text-decoration: underline; }
.mde-picker-tree-wrap { 
    flex: 1; 
    overflow-y: auto; 
    min-height: 200px; 
    padding: 4px 0; 
    background: var(--bg); 
    border: 1px solid var(--border); 
    border-radius: 6px; 
}
.mde-picker-loading { padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem; }
.mde-tree-node { 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    padding: 5px 10px; 
    cursor: pointer; 
    user-select: none; 
    transition: background 0.1s; 
    font-size: 0.86rem; 
    border-radius: 4px; 
    margin: 1px 4px; 
}
.mde-tree-node:hover { background: var(--blue-light-bg); }
.mde-tree-node input[type=checkbox] { width: 14px; height: 14px; accent-color: var(--accent); cursor: pointer; flex-shrink: 0; margin: 0; }
.mde-tree-node input[type=checkbox]:indeterminate { opacity: 0.7; }
.mde-node-toggle { 
    width: 16px; 
    height: 16px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    flex-shrink: 0; 
    font-size: 0.65rem; 
    color: var(--text-muted); 
    cursor: pointer; 
    border-radius: 3px; 
    transition: background 0.1s, transform 0.15s; 
}
.mde-node-toggle:hover { background: rgba(59,130,246,0.12); }
.mde-node-toggle.open { transform: rotate(90deg); }
.mde-node-icon { font-size: 0.9rem; flex-shrink: 0; opacity: 0.75; }
.mde-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mde-tree-node.is-folder > .mde-node-label { font-weight: 600; color: var(--text); }
.mde-tree-node.is-node > .mde-node-label { color: var(--text-muted); }
.mde-tree-children { display: none; }
.mde-tree-children.open { display: block; }
.mde-options-strip { display: flex; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
.mde-option-row { 
    flex: 1; 
    min-width: 160px; 
    padding: 8px 12px; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    background: var(--bg); 
    border: 1px solid var(--border); 
    border-radius: 6px; 
    font-size: 0.85rem; 
}
.mde-option-row label { flex: 1; cursor: pointer; }
.mde-option-row input[type=checkbox] { width: 15px; height: 15px; cursor: pointer; accent-color: var(--accent); flex-shrink: 0; }
.mde-footer { 
    padding: 12px 18px; 
    border-top: 1px solid var(--border); 
    display: flex; 
    gap: 8px; 
    justify-content: flex-end; 
    align-items: center; 
    flex-shrink: 0; 
}
.mde-footer-left { flex: 1; font-size: 0.8rem; color: var(--text-muted); }
</style>

<div class="mde-modal-bg" id="mde-modal-bg">
    <div class="mde-modal">
        <div class="mde-header">
            <h3><i class="bi bi-download" style="color:var(--accent);"></i> Export Documents</h3>
            <button class="mde-close" onclick="mdeCloseModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="mde-body">
            <p class="mde-desc">
                Select documents and/or categories to export. MD Content is always included.
            </p>
            <div style="display:flex; flex-direction:column; flex:1; min-height:0; gap:0;">
                <div class="mde-picker-header">
                    <input type="text" id="mdeSearchInput" placeholder="Search docs & content..." oninput="mdeDebounceSearch()">
                    <span id="mdeSearchSpinner" style="display:none; font-size:0.8rem; margin-left:5px;"><i class="bi bi-hourglass-split"></i></span>
                    <button class="mde-picker-select-all" onclick="mdePickerToggleAll()" style="margin-left:auto;">Select all</button>
                </div>
                <div class="mde-picker-tree-wrap" id="mde-picker-tree-wrap">
                    <div class="mde-picker-loading">Loading tree…</div>
                </div>
            </div>
            <div class="mde-options-strip">
                <div class="mde-option-row">
                    <input type="checkbox" id="mde-with-meta">
                    <label for="mde-with-meta">
                        <strong>Include Meta Info</strong>
                        <span style="color:var(--text-muted); font-size:0.75rem; display:block; margin-top:1px;">Keywords, short description, description.</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="mde-footer">
            <span class="mde-footer-left" id="mde-picker-count"></span>
            <button class="btn btn-ghost btn-sm" onclick="mdeCloseModal()">Cancel</button>
            <button class="btn btn-primary btn-sm" id="mde-export-btn" onclick="mdeDoExport()">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>
</div>

<script>
let mdeTreeRaw = [];
let mdeTreeChecked = new Set();
let mdeSearchTimer = null;

function openMdExportModal() {
    document.getElementById('mde-modal-bg').classList.add('open');
    const metaChk = document.getElementById('mde-with-meta');
    if (metaChk) metaChk.checked = false;
    mdeLoadTree();
}

function mdeCloseModal() {
    document.getElementById('mde-modal-bg').classList.remove('open');
    document.getElementById('mdeSearchInput').value = '';
}

function mdeLoadTree() {
    const wrap = document.getElementById('mde-picker-tree-wrap');
    wrap.innerHTML = '<div class="mde-picker-loading">Loading tree…</div>';
    
    fetch('api_md.php?action=export_tree_data')
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') {
                wrap.innerHTML = '<div class="mde-picker-loading">Failed to load tree.</div>'; return;
            }
            
            mdeTreeRaw = [];
            mdeTreeRaw.push({ id: 'c_0', parent: '#', text: 'Uncategorized', type: 'folder' });
            
            res.categories.forEach(c => {
                mdeTreeRaw.push({ id: 'c_' + c.id, parent: '#', text: c.name, type: 'folder' });
            });
            
            res.docs.forEach(d => {
                const catId = d.category_id ? 'c_' + d.category_id : 'c_0';
                mdeTreeRaw.push({ id: 'd_' + d.id, parent: catId, text: d.name, type: 'node', doc_id: d.id });
            });
            
            // Check if any documents are selected on the page
            const selectedDocIds = Array.from(document.querySelectorAll('.doc-select-chk:checked')).map(cb => parseInt(cb.dataset.id));

            if (selectedDocIds.length > 0) {
                // Pre-select ONLY the checked documents and sync their folder ancestors
                mdeTreeChecked = new Set(selectedDocIds.map(id => 'd_' + id));
                selectedDocIds.forEach(id => {
                    mdePickerSyncAncestors('d_' + id);
                });
            } else {
                // No pre-selection by default
                mdeTreeChecked = new Set();
            }
            
            mdeRenderTree(null);
        })
        .catch(() => { wrap.innerHTML = '<div class="mde-picker-loading">Error loading tree.</div>'; });
}

function mdeRenderTree(hits = null) {
    const wrap = document.getElementById('mde-picker-tree-wrap');
    const childMap = {};
    mdeTreeRaw.forEach(n => {
        if (!childMap[n.parent]) childMap[n.parent] = [];
        childMap[n.parent].push(n);
    });
    wrap.innerHTML = mdeBuildLevel('#', childMap, 0, hits);
    mdeUpdatePickerCount();
}

function mdeGetDescendantDocs(folderId, childMap) {
    const docs = [];
    const children = childMap[folderId] || [];
    children.forEach(n => {
        if (n.type === 'node') docs.push(n.doc_id);
        if (n.type === 'folder') docs.push(...mdeGetDescendantDocs(n.id, childMap));
    });
    return docs;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function mdeBuildLevel(parentId, childMap, depth, hits) {
    const children = childMap[parentId] || [];
    if (!children.length) return '';
    const indent = depth * 14;
    let html = '';
    
    children.forEach(node => {
        const isFolder = node.type === 'folder';
        const jsId = node.id;
        const checked = mdeTreeChecked.has(jsId);
        const hasKids = !!(childMap[jsId] && childMap[jsId].length);
        const icon = isFolder ? '<i class="bi bi-folder-fill" style="color:var(--orange);"></i>' : '<i class="bi bi-file-earmark-text" style="color:var(--text-muted);"></i>';

        let isVisible = true;
        let labelHtml = escapeHtml(node.text);
        let excerptHtml = '';

        if (hits !== null) {
            if (isFolder) {
                const descendantDocs = mdeGetDescendantDocs(jsId, childMap);
                isVisible = descendantDocs.some(dId => hits[dId]);
            } else {
                if (hits[node.doc_id]) {
                    isVisible = true;
                    labelHtml = hits[node.doc_id].name_hl;
                    if (hits[node.doc_id].excerpt) {
                        excerptHtml = `<div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px; margin-bottom:8px; margin-left:26px; padding-left:10px; border-left:2px solid var(--accent); white-space: normal;">${hits[node.doc_id].excerpt}</div>`;
                    }
                } else {
                    isVisible = false;
                }
            }
        }

        if (!isVisible) return;

        const isOpenClass = (isFolder && hasKids && hits !== null) ? 'open' : '';
        const toggleBtn = (isFolder && hasKids)
            ? `<span class="mde-node-toggle ${isOpenClass}" onclick="mdePickerToggleFolder('${jsId}', this)"><i class="bi bi-chevron-right"></i></span>`
            : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

        html += `
        <div style="display:flex; flex-direction:column;">
            <div class="mde-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
                 style="padding-left:${10 + indent}px;"
                 data-jid="${jsId}">
                ${toggleBtn}
                <input type="checkbox" ${checked ? 'checked' : ''} onchange="mdePickerCheck('${jsId}', this.checked)">
                <span class="mde-node-icon">${icon}</span>
                <span class="mde-node-label">${labelHtml}</span>
            </div>
            ${excerptHtml ? `<div style="padding-left:${10 + indent + 30}px;">${excerptHtml}</div>` : ''}
        </div>`;

        if (hasKids) {
            html += `<div class="mde-tree-children ${isOpenClass}" id="mde-kids-${jsId}">`;
            html += mdeBuildLevel(jsId, childMap, depth + 1, hits);
            html += `</div>`;
        }
    });
    return html;
}

function mdeDebounceSearch() {
    clearTimeout(mdeSearchTimer);
    mdeSearchTimer = setTimeout(mdeSearch, 300);
}

function mdeSearch() {
    const q = document.getElementById('mdeSearchInput').value.trim();
    if (q === '') {
        mdeRenderTree(null);
        return;
    }
    
    document.getElementById('mdeSearchSpinner').style.display = 'inline-block';
    
    fetch('api_md.php?action=export_search&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            document.getElementById('mdeSearchSpinner').style.display = 'none';
            if (res.status === 'success') {
                const hitMap = {};
                res.hits.forEach(h => hitMap[h.id] = h);
                mdeRenderTree(hitMap);
            }
        });
}

function mdePickerToggleFolder(jsId, btn) {
    const kids = document.getElementById('mde-kids-' + jsId);
    if (!kids) return;
    kids.classList.toggle('open');
    btn.classList.toggle('open');
}

function mdePickerCheck(jsId, checked) {
    const ids = mdePickerDescendants(jsId);
    ids.forEach(id => {
        if (checked) mdeTreeChecked.add(id);
        else mdeTreeChecked.delete(id);
    });
    ids.forEach(id => {
        const el = document.querySelector(`.mde-tree-node[data-jid="${id}"] input[type=checkbox]`);
        if (el) { el.checked = checked; el.indeterminate = false; }
    });
    mdePickerSyncAncestors(jsId);
    mdeUpdatePickerCount();
}

function mdePickerDescendants(jsId) {
    const result = [jsId];
    const queue = [jsId];
    while (queue.length) {
        const cur = queue.shift();
        mdeTreeRaw.filter(n => n.parent === cur).forEach(n => {
            result.push(n.id);
            queue.push(n.id);
        });
    }
    return result;
}

function mdePickerSyncAncestors(jsId) {
    const node = mdeTreeRaw.find(n => n.id === jsId);
    if (!node || !node.parent || node.parent === '#') return;
    const parentJid = node.parent;
    const siblings = mdeTreeRaw.filter(n => n.parent === parentJid);
    const allChecked = siblings.every(s => mdeTreeChecked.has(s.id));
    const noneChecked = siblings.every(s => !mdeTreeChecked.has(s.id));
    const el = document.querySelector(`.mde-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);
    if (el) {
        if (allChecked) {
            el.checked = true; el.indeterminate = false;
            mdeTreeChecked.add(parentJid);
        } else if (noneChecked) {
            el.checked = false; el.indeterminate = false;
            mdeTreeChecked.delete(parentJid);
        } else {
            el.checked = false; el.indeterminate = true;
            mdeTreeChecked.delete(parentJid);
        }
    }
    mdePickerSyncAncestors(parentJid);
}

function mdePickerToggleAll() {
    const allChecked = mdeTreeRaw.every(n => mdeTreeChecked.has(n.id));
    if (allChecked) {
        mdeTreeChecked.clear();
    } else {
        mdeTreeRaw.forEach(n => mdeTreeChecked.add(n.id));
    }
    const q = document.getElementById('mdeSearchInput').value.trim();
    if (q === '') { mdeRenderTree(null); } else { mdeSearch(); }
    mdeUpdatePickerCount();
}

function mdeUpdatePickerCount() {
    const nodeCount = mdeTreeRaw.filter(n => n.type === 'node' && mdeTreeChecked.has(n.id)).length;
    const total = mdeTreeRaw.filter(n => n.type === 'node').length;
    const el = document.getElementById('mde-picker-count');
    if (el) el.textContent = `${nodeCount} of ${total} documents selected`;
}

function mdeDoExport() {
    const withMeta = document.getElementById('mde-with-meta').checked;
    const btn = document.getElementById('mde-export-btn');
    
    const selectedDocs = mdeTreeRaw.filter(n => n.type === 'node' && mdeTreeChecked.has(n.id)).map(n => n.doc_id);
    
    if (selectedDocs.length === 0) {
        alert('No documents selected.'); return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Building…';
    
    fetch('api_md.php?action=export_docs', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ doc_ids: selectedDocs, with_meta: withMeta })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            const blob = new Blob([JSON.stringify(res.snapshot, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; 
            a.download = `md_export_${new Date().toISOString().slice(0, 10)}.json`;
            a.click();
            URL.revokeObjectURL(url);
            mdeCloseModal();
        } else {
            alert('Export failed: ' + res.message);
        }
    })
    .catch(err => alert('Network error during export: ' + err.message))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download"></i> Export';
    });
}

document.getElementById('mde-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) mdeCloseModal();
});
</script>
<!-- ===== END MD EXPORT TREE MODAL ===== -->

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);