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
    if ($filterSort !== 'created_desc') $queryParams['sort'] = $filterSort; // Only add if not default
    
    if (!empty($queryParams)) {
        $backLink .= '?' . http_build_query($queryParams);
    }

    $editLink = "edit_md.php?id=" . $docId;
    // Append params to edit link as well to maintain context
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

    <!-- Dynamic Markdown Styles -->
    <link id="md-css-light" rel="stylesheet" href="/vendor/github-markdown-css/github-markdown-light.css">
    <link id="md-css-dark" rel="stylesheet" href="/vendor/github-markdown-css/github-markdown-dark.css" disabled>

    <style>
        /* --- CSS PATCH: Ensure variables exist in all modes --- */
        
        /* 1. Default (Light) */
        :root {
            --bg: #ffffff;
            --card: #f6f8fa;
            --border: #d0d7de;
            --text: #24292f;
            --text-muted: #57606a;
            --accent: #0969da;
            --toc-max-height: 70vh;
            --blue-light-bg: rgba(84, 174, 255, 0.2);
        }
        
        /* 2. Explicit Dark Mode */
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
            --blue-light-bg: rgba(56, 139, 253, 0.15);
            --blue-light-text: #79c0ff;
            --blue-light-border: rgba(59,130,246,0.3);
            --card-elevation: 0 6px 18px rgba(2,6,23,0.4);
        }

        /* 3. System Dark Mode (if no data-theme set) */
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg: #0d1117;
                --card: #161b22;
                --border: #30363d;
                --text: #c9d1d9;
                --text-muted: #8b949e;
                --accent: #58a6ff;
                --blue-light-bg: rgba(56, 139, 253, 0.15);
            }
        }

        /* --- GLOBAL LAYOUT --- */
        body { 
            background-color: var(--bg) !important; 
            color: var(--text) !important; 
            scroll-behavior: smooth; 
            touch-action: manipulation; 
            transition: background-color 0.2s, color 0.2s; 
        }
        
        .view-container { max-width: 980px; margin: 0 auto; padding: 20px; padding-bottom: 100px; }
        
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
            margin-bottom: 20px; padding: 12px 15px; 
            background: var(--card); border-bottom: 1px solid var(--border); 
            border-radius: 6px; display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .nav-bar a, .nav-bar button { color: var(--accent); text-decoration: none; font-size: 0.95rem; background:none; border:none; cursor:pointer; }
        .nav-bar a:hover, .nav-bar button:hover { text-decoration: underline; }
        .nav-bar button:disabled { color: var(--text-muted); cursor: not-allowed; opacity: 0.5; }

        /* Floating Panels */
        .floating-panel {
            position: fixed; z-index: 9999; 
            display: none; flex-direction: column;
            background: var(--card); border: 1px solid var(--border);
            border-radius: 8px; box-shadow: var(--card-elevation);
            color: var(--text); backdrop-filter: blur(5px);
            will-change: transform, top, left; touch-action: none; 
        }
        .floating-panel.dragging { transition: none; }
        .floating-panel:not(.dragging) { transition: transform 0.1s cubic-bezier(0.2, 0.8, 0.2, 1); }
        
        .panel-header { 
            padding: 10px 12px; border-bottom: 1px solid var(--border); 
            cursor: grab; display:flex; align-items:center; gap: 8px; user-select: none;
            background: rgba(125,125,125,0.05); touch-action: none; 
        }
        .panel-close { background:none; border:none; color:var(--text-muted); cursor:pointer; padding:5px; margin-left: auto; }
        .panel-close:hover { color: var(--text); }

        /* TOC */
        #floatingToc { top: 80px; right: 20px; width: 300px; max-height: var(--toc-max-height); }
        #tocArrow { display: inline-block; width: 14px; text-align: center; font-size: 12px; }
        #tocBody { padding: 10px; overflow-y: auto; scrollbar-width: thin; }
        
        ul#tocList { list-style:none; padding:0; margin:0; }
        ul#tocList li { margin-bottom: 2px; }
        ul#tocList li a { 
            display:block; padding: 4px 6px; font-size:13px; 
            text-decoration:none; color: var(--text); border-radius: 4px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        ul#tocList li a:hover { background: var(--blue-light-bg); color: var(--accent); }
        
        /* Audio Player */
        #floatingPlayer { top: 140px; right: 20px; width: 320px; height: auto; z-index: 9999; }
        #playerBody { padding: 15px; display: flex; flex-direction: column; gap: 10px; min-width: 300px; }
        audio.custom-audio { width: 100%; height: 36px; border-radius: 4px; outline: none; }
        
        /* Audio Invert Logic */
        html[data-theme="dark"] audio.custom-audio { filter: invert(0.9) hue-rotate(180deg); }
        @media (prefers-color-scheme: dark) { html:not([data-theme="light"]) audio.custom-audio { filter: invert(0.9) hue-rotate(180deg); } }

        .para-anchor { opacity: 0; margin-left: 8px; cursor: pointer; background:none; border:none; color: var(--text-muted); font-size: 14px; padding: 0 4px; transition: opacity 0.2s; }
        p:hover .para-anchor { opacity: 1; }
        
        .auto-scroll-controls { position: fixed; bottom: 20px; right: 20px; z-index: 9000; background: var(--card); padding: 12px; border-radius: 10px; border: 1px solid var(--border); display: block; box-shadow: 0 4px 12px rgba(0,0,0,0.2); width: 260px; }
        .asc-row { display: flex; gap: 8px; margin-bottom: 8px; justify-content: space-between; }
        .asc-btn { background: rgba(125,125,125,0.1); border: 1px solid var(--border); color: var(--text); padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; flex: 1; }
        .asc-btn:hover { background: rgba(125,125,125,0.2); }
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
                <a href="<?= $backLink ?>">&larr; Doc Index</a>
            </div>
            <div>
                <?php if ($latestAudio): ?>
                    <button id="btnTogglePlayer" onclick="togglePlayer()" title="Toggle Audio Player" style="margin-right:15px; font-size:1.2rem;">🎧</button>
                <?php else: ?>
                    <button title="No Audio Available" disabled style="margin-right:15px; font-size:1.2rem; filter:grayscale(1);">🎧</button>
                <?php endif; ?>

                <a href="?id=<?= $docId ?>&download=1" title="Download">📥</a>
                &nbsp;|&nbsp;
                <a href="<?= $editLink ?>" style="color: #fca326;" title="Edit">✏️ Edit</a>
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
            <span style="font-weight:600; flex:1;">Index</span>
            <button class="panel-close" onclick="document.getElementById('floatingToc').style.display='none'">✕</button>
        </div>
        <div id="tocBody">
            <input id="tocSearch" placeholder="Filter..." style="width:100%; margin-bottom:8px; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:6px; border-radius:4px; box-sizing:border-box;">
            <ul id="tocList"></ul>
        </div>
    </div>

    <!-- Player Overlay -->
    <?php if ($latestAudio): ?>
    <div id="floatingPlayer" class="floating-panel">
        <div id="playerHeader" class="panel-header" title="Drag to move">
            <span style="font-size:1.1rem; margin-right:6px;">🎧</span>
            <span style="font-weight:600; flex:1;">Audio Player</span>
            <button class="panel-close" onclick="togglePlayer()">✕</button>
        </div>
        <div id="playerBody">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                <span style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-right:10px;">Playing: Latest generated audio</span>
                <div style="display:flex; gap:8px; align-items:center;">
                    <button id="btnRewind" title="-10s" style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0;">⏪</button>
                    <button id="btnForward" title="+10s" style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0;">⏩</button>
                    <button id="btnBookmark" title="Bookmark position" style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0;">🔖</button>
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
            <button id="asc-hide" class="asc-btn" style="flex:0; width:30px;">✕</button>
        </div>
        <div style="display:flex; align-items:center; gap:10px; font-size:12px; color:var(--text-muted);">
            <span>Speed:</span>
            <input type="range" id="asc-speed" min="1" max="100" value="20" style="flex:1;">
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
                
                // Load Bookmark
                audio.addEventListener('loadedmetadata', () => {
                    const savedTime = localStorage.getItem(key);
                    if(savedTime) { audio.currentTime = parseFloat(savedTime); }
                });

                // Transport Controls
                const btnRewind = document.getElementById('btnRewind');
                const btnForward = document.getElementById('btnForward');
                if(btnRewind) btnRewind.onclick = () => audio.currentTime = Math.max(0, audio.currentTime - 10);
                if(btnForward) btnForward.onclick = () => audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);

                // Save Bookmark
                const btnBookmark = document.getElementById('btnBookmark');
                if(btnBookmark) {
                    btnBookmark.addEventListener('click', () => {
                        localStorage.setItem(key, audio.currentTime);
                        const originalHtml = btnBookmark.innerHTML;
                        btnBookmark.innerHTML = '✅';
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
            content.querySelectorAll('p').forEach((p, idx) => { const id = 'p-' + idx; if(!p.id) p.id = id; const btn = document.createElement('button'); btn.innerHTML = '#'; btn.className = 'para-anchor'; btn.onclick = (e) => { e.stopPropagation(); navigator.clipboard.writeText(location.href.split('#')[0] + '#' + p.id); history.replaceState(null, null, '#' + p.id); btn.style.color = '#58a6ff'; setTimeout(() => btn.style.color = '', 500); }; p.appendChild(btn); });
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
$orderByClause = "d.created_at DESC"; // Default request: Newest first
switch ($filterSort) {
    case 'created_asc': $orderByClause = "d.created_at ASC"; break;
    case 'updated_desc': $orderByClause = "d.updated_at DESC"; break;
    case 'name_asc': $orderByClause = "d.name ASC"; break;
    case 'name_desc': $orderByClause = "d.name DESC"; break;
    case 'created_desc': default: $orderByClause = "d.created_at DESC"; break;
}

// Fetch documents
// We keep 'category_id ASC' as primary sort to preserve the visual grouping structure,
// but apply the user's selected sort order within categories.
// JOIN with md_doc_analysis to check for curation data
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

<style>
    /* CSS PATCH for Dark Mode override (Index View) */
    :root {
        --bg: #ffffff;
        --card: #f6f8fa;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --blue-light-bg: rgba(84, 174, 255, 0.2);
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
        --blue-light-bg: rgba(56, 139, 253, 0.15);
        --blue-light-text: #79c0ff;
        --blue-light-border: rgba(59,130,246,0.3);
        --card-elevation: 0 6px 18px rgba(2,6,23,0.4);
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
            --blue-light-bg: rgba(56, 139, 253, 0.15);
            --blue-light-text: #79c0ff;
            --blue-light-border: rgba(59,130,246,0.3);
            --card-elevation: 0 6px 18px rgba(2,6,23,0.4);
        }
    }

    body { background-color: var(--bg) !important; color: var(--text) !important; padding: 20px; transition: background-color 0.2s, color 0.2s; }
    .container { max-width: 800px; margin: 0 auto; }
    
    .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom:10px; }
    
    /* FIX: Explicit Green Background and White Text for New Button */
    .btn-create { 
        background: #238636 !important; 
        color: #ffffff !important; 
        padding: 6px 12px; border-radius: 6px; 
        text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;
        border: 1px solid rgba(255,255,255,0.1);
        cursor: pointer;
    }
    .btn-create:hover { filter: brightness(0.9); }

    .filters-area { background: var(--card); border: 1px solid var(--border); border-radius: 6px; padding: 10px; margin-bottom: 20px; }
    .filters-area input, .filters-area select { width: 100%; box-sizing: border-box; padding: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; font-size: 14px; }
    .filters-area input { margin-bottom: 10px; }

    .cat-group { margin-bottom: 25px; }
    .cat-title { font-size: 1.1em; color: var(--text-muted); margin-bottom: 10px; border-bottom: 1px solid var(--border); padding-bottom: 4px; }
    
    .doc-list { list-style: none; padding: 0; }
    .doc-item { background: var(--card); margin-bottom: 6px; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; transition: border-color 0.2s; }
    .doc-item:hover { border-color: var(--text-muted); }
    .doc-link { font-size: 16px; font-weight: 600; color: var(--accent); text-decoration: none; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-right: 10px;}
    .doc-link:hover { text-decoration: underline; }
    
    .meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
    .icon-btn { text-decoration: none; font-size: 16px; border: none; background: none; cursor: pointer; padding: 0 2px; filter: grayscale(100%); transition: all 0.2s; }
    .icon-btn:hover { filter: grayscale(0%); transform: scale(1.1); }
    
    .audio-chk { cursor: pointer; width: 16px; height: 16px; accent-color: var(--orange); margin: 0; margin-right: 4px; }
    .audio-wrapper { display: flex; align-items: center; border-right: 1px solid var(--border); padding-right: 8px; margin-right: 4px; title: "Regenerate Audio"; }
</style>

<div class="container">
    <div class="header-row">
        <div style="display:flex; align-items:center;">
            <h2 style="margin:0;padding-left:45px; margin-right: 10px;"> </h2>
            <a class="runBtn scheduler" data-id="40" title="Run TTS Scheduler" style="cursor:pointer; font-size:1.2rem; text-decoration:none;">🌀</a>
            <!-- Logs Button: Reset opacity/filter to ensure visibility in all themes -->
            <button onclick="toggleLogsModal()" title="View Logs" style="background:none; border:none; cursor:pointer; font-size:1.2rem; margin-left:10px; color: var(--text); opacity: 1; filter: none;">📓</button>
        </div>

        <!-- NEW: Import button (opens modal) + existing New button -->
        <div style="display:flex; gap:8px; align-items:center;">
            <button id="btnImportMd" class="btn-create" style="background:#0366d6 !important;">⇪ Import</button>
            <button id="btnExportMd" class="btn-create" style="background:#0969da !important;" onclick="openMdExportModal()">&#x1F4E4; Export</button>
            <a href="<?= $newLink ?>" class="btn-create"><span>+</span> New</a>
        </div>
    </div>

    <!-- Filter Area -->
    <div class="filters-area">
        <input id="docSearch" placeholder="Filter text..." value="">
        <div style="display:flex; gap:10px;">
            <select id="catFilter">
                <option value="">All Categories</option>
                <?php foreach($cats as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($filterCatId == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
                <option value="0" <?= ($filterCatId === '0') ? 'selected' : '' ?>>Uncategorized</option>
            </select>

            <select id="sortFilter">
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
            <div class="alert alert-info">No documents found.</div>
        <?php else: ?>
            <?php foreach($grouped as $catId => $group): ?>
                <div class="cat-group" data-cat-id="<?= $catId ?>">
                    <div class="cat-title"><?php echo htmlspecialchars($group['name']); ?></div>
                    <ul class="doc-list">
                        <?php foreach($group['docs'] as $item): 
                            // Construct links preserving sort and category
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
                                    <a href="view_curated_docs.php?doc_id=<?= $item['id'] ?>" style="font-size: 1.25rem; margin-right: 6px; text-decoration: none;" title="View Curated Analysis">📑</a>
                                <?php else: ?>
                                    <span style="font-size: 1.25rem; margin-right: 6px;">📄</span>
                                <?php endif; ?>

                                <!-- DOCUMENT LINK -->
                                <a href="<?= $itemReadLink ?>" class="doc-link">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>

                                <div class="meta">
                                    <span style="margin-right:8px; opacity:0.6; display:none; sm:display:inline;"><?= date('M d', strtotime($item['updated_at'])) ?></span>
                                    
                                    <div class="audio-wrapper" title="Check to Regenerate Audio">
                                        <input type="checkbox" class="audio-chk" 
                                               onchange="toggleAudio(this, <?= $item['id'] ?>)" 
                                               <?= $item['regenerate_audios'] ? 'checked' : '' ?>>
                                        <span style="font-size:14px;">🔊</span>
                                    </div>

                                    <a href="?id=<?= $item['id'] ?>&download=1" class="icon-btn" title="Download">📥</a>
                                    <a href="<?= $itemEditLink ?>" class="icon-btn" title="Edit">✏️</a>
                                    <button onclick="deleteDoc(<?= $item['id'] ?>)" class="icon-btn" title="Delete">🗑️</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

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
                
                // If text is entered, filter by IDs returned from server.
                // If text empty, show all (currentSearchIds is null).
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
        // Get current sort param from URL to preserve it
        const currentUrl = new URL(window.location);
        const sortVal = currentUrl.searchParams.get('sort');

        document.querySelectorAll('a').forEach(a => {
            let href = a.getAttribute('href');
            if(!href) return;
            // Target Edit links, Read links, and Create button
            if (href.includes('edit_md.php') || (href.includes('view_md.php') && href.includes('id=')) || href.startsWith('?id=')) {
                let u = new URL(a.href, window.location.origin);
                
                // Update Category
                if (catId) u.searchParams.set('category_id', catId);
                else u.searchParams.delete('category_id');
                
                // Preserve Sort
                if (sortVal) u.searchParams.set('sort', sortVal);
                else u.searchParams.delete('sort'); // if empty or null
                
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
    
    // Sort Select Listener - Requires Reload to re-order SQL results
    sortSelect.addEventListener('change', function() {
        const url = new URL(window.location);
        url.searchParams.set('sort', this.value);
        window.location.href = url.toString();
    });

    // Debounced Input Handler
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

    // Initial Render
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

    document.addEventListener('click', function(e) {
        if (e.target.closest('.runBtn')) {
            const btn = e.target.closest('.runBtn');
            const id = btn.getAttribute('data-id');
            const original = btn.innerHTML;
            btn.innerHTML = '⏳';
            
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
                    background:rgba(0,0,0,0.85);z-index:9999;display:flex;justify-content:center;align-items:center;">
                    <div style="width:80%;height:80%;background:var(--card);padding:20px;position:relative;display:flex;flex-direction:column;border:1px solid var(--border);">
                        <button class="close-btn" style="position:absolute;top:10px;right:10px;
                            background:var(--red);color:#fff;border:none;padding:5px 10px;cursor:pointer;">✖ Close</button>
                        <iframe src="${iframeSrc}" frameborder="0" style="flex:1;width:100%;background:var(--bg);"></iframe>
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

<!-- ===== MD IMPORT MODAL (INJECT) ===== -->
<style>
    /* small helper styles for modal import */
    #mdImportModal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:99999; display:none; align-items:center; justify-content:center; }
    #mdImportModal .box { width:90%; max-width:720px; background:var(--card); border:1px solid var(--border); padding:18px; border-radius:8px; color:var(--text); box-shadow: var(--card-elevation); }
    #mdImportModal .box h3 { margin-top:0; }
    #mdImportModal .row { display:flex; gap:8px; align-items:center; margin-top:12px; }
    #mdImportModal .btn { padding:8px 12px; border-radius:6px; border: none; cursor:pointer; font-weight:600; }
    #mdImportModal .btn.primary { background:#238636; color:#fff; }
    #mdImportModal .btn.ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
    #mdImportModal .msg { margin-top:10px; font-size:0.9rem; color:var(--text-muted); }
</style>

<div id="mdImportModal" aria-hidden="true">
    <div class="box" role="dialog" aria-modal="true" aria-labelledby="mdImportTitle">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 id="mdImportTitle">Import Markdown</h3>
            <button id="mdImportClose" class="btn ghost" title="Close">✕</button>
        </div>

        <p style="margin:8px 0 0 0; color:var(--text-muted);">Select a <strong>.md</strong> / <strong>.markdown</strong> / <strong>.txt</strong> file. The filename will be used as the document name.</p>

        <div class="row">
            <input id="mdImportFile" type="file" accept=".md,.markdown,text/markdown,text/plain,.txt" style="flex:1;">
            <select id="mdImportCat" style="min-width:160px;">
                <option value="">No category (Uncategorized)</option>
                <?php foreach($cats as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($filterCatId == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
                <option value="0" <?= ($filterCatId === '0') ? 'selected' : '' ?>>Uncategorized</option>
            </select>
        </div>

        <div class="row" style="justify-content:flex-end; margin-top:14px;">
            <button id="mdImportCancel" class="btn ghost">Cancel</button>
            <button id="mdImportUpload" class="btn primary" style="margin-left:8px;">Upload</button>
        </div>

        <div class="msg" id="mdImportMsg" aria-live="polite"></div>
    </div>
</div>

<script>
    (function(){
        // current category (string). Mirrors server-side filterCatId
        const currentCategory = "<?= htmlspecialchars($filterCatId, ENT_QUOTES) ?>";

        const btnImport = document.getElementById('btnImportMd');
        const modal = document.getElementById('mdImportModal');
        const closeBtn = document.getElementById('mdImportClose');
        const cancelBtn = document.getElementById('mdImportCancel');
        const uploadBtn = document.getElementById('mdImportUpload');
        const fileInput = document.getElementById('mdImportFile');
        const catSelect = document.getElementById('mdImportCat');
        const msgEl = document.getElementById('mdImportMsg');

        // Open modal
        btnImport && btnImport.addEventListener('click', function(){
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            // set category select to current if available
            if(currentCategory !== '') {
                const opt = Array.from(catSelect.options).find(o => o.value === currentCategory);
                if(opt) opt.selected = true;
            }
            msgEl.textContent = '';
            fileInput.value = '';
        });

        function closeModal() {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        closeBtn && closeBtn.addEventListener('click', closeModal);
        cancelBtn && cancelBtn.addEventListener('click', closeModal);

        uploadBtn && uploadBtn.addEventListener('click', function(){
            const f = fileInput.files[0];
            if(!f) { msgEl.textContent = 'No file selected.'; return; }

            // Basic client-side validation
            const allowed = ['md','markdown','txt'];
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if(!allowed.includes(ext)) { msgEl.textContent = 'Invalid file type. Use .md / .markdown / .txt'; return; }
            if(f.size > 5 * 1024 * 1024) { msgEl.textContent = 'File too large (max 5MB)'; return; }

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading…';
            msgEl.textContent = '';

            const fd = new FormData();
            fd.append('file', f);
            const catVal = catSelect.value;
            if(catVal !== '') fd.append('category_id', catVal);

            fetch('api_md.php?action=import_md', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    msgEl.textContent = 'Imported — refreshing list...';
                    // Keep UX simple and safe: reload to render new document with all current filters/sort.
                    setTimeout(() => { window.location.reload(); }, 600);
                } else {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload';
                    msgEl.textContent = 'Import failed: ' + (res.message || 'unknown');
                }
            })
            .catch(err => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload';
                msgEl.textContent = 'Upload error: ' + err.message;
            });
        });

        // Close modal on ESC
        window.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
        });
    })();
</script>
<!-- ===== END MD IMPORT MODAL ===== -->

<!-- ===== MD EXPORT TREE MODAL ===== -->
<style>
.mde-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 99999; }
.mde-modal-bg.open { display: flex; }
.mde-modal { width: min(780px, 96vw); max-height: 90vh; background: var(--card); border: 1px solid var(--border); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,0.45); }
.mde-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.mde-header h3 { margin: 0; font-size: 1rem; flex: 1; display: flex; align-items: center; gap: 8px; }
.mde-close { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 1.3rem; line-height: 1; padding: 2px 6px; border-radius: 4px; transition: color 0.15s, background 0.15s; }
.mde-close:hover { color: var(--text); background: var(--bg); }
.mde-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; flex: 1; min-height: 0; }
.mde-desc { font-size: 0.78rem; color: var(--text-muted); line-height: 1.5; padding: 0 2px; margin: 0; }
.mde-picker-header { display: flex; align-items: center; gap: 8px; padding: 8px 14px 6px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--card); }
.mde-picker-header input { flex:1; padding:6px 10px; border:1px solid var(--border); border-radius:4px; background:var(--bg); color:var(--text); font-size:0.85rem; }
.mde-picker-header input:focus { outline:none; border-color:var(--accent); }
.mde-picker-select-all { background: none; border: none; color: var(--accent); font-size: 0.75rem; cursor: pointer; padding: 0; font-weight: 600; }
.mde-picker-select-all:hover { text-decoration: underline; }
.mde-picker-tree-wrap { flex: 1; overflow-y: auto; min-height: 200px; padding: 4px 0; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; }
.mde-picker-loading { padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem; }
.mde-tree-node { display: flex; align-items: center; gap: 6px; padding: 5px 10px; cursor: pointer; user-select: none; transition: background 0.1s; font-size: 0.86rem; border-radius: 4px; margin: 1px 4px; }
.mde-tree-node:hover { background: rgba(59,130,246,0.07); }
.mde-tree-node input[type=checkbox] { width: 14px; height: 14px; accent-color: var(--accent); cursor: pointer; flex-shrink: 0; margin: 0; }
.mde-tree-node input[type=checkbox]:indeterminate { opacity: 0.7; }
.mde-node-toggle { width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.65rem; color: var(--text-muted); cursor: pointer; border-radius: 3px; transition: background 0.1s, transform 0.15s; }
.mde-node-toggle:hover { background: rgba(59,130,246,0.12); }
.mde-node-toggle.open { transform: rotate(90deg); }
.mde-node-icon { font-size: 0.85rem; flex-shrink: 0; opacity: 0.75; }
.mde-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mde-tree-node.is-folder > .mde-node-label { font-weight: 600; color: var(--text); }
.mde-tree-node.is-node > .mde-node-label { color: var(--text-muted); }
.mde-tree-children { display: none; }
.mde-tree-children.open { display: block; }
.mde-options-strip { display: flex; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
.mde-option-row { flex: 1; min-width: 160px; padding: 9px 12px; display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; font-size: 0.88rem; }
.mde-option-row label { flex: 1; cursor: pointer; }
.mde-option-row input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); flex-shrink: 0; }
.mde-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-shrink: 0; }
.mde-footer-left { flex: 1; font-size: 0.82rem; color: var(--text-muted); }
</style>

<div class="mde-modal-bg" id="mde-modal-bg">
    <div class="mde-modal">
        <div class="mde-header">
            <h3>&#x1F4E4; Export Documents</h3>
            <button class="mde-close" onclick="mdeCloseModal()">&#x2715;</button>
        </div>
        <div class="mde-body">
            <p class="mde-desc">
                Select documents and/or categories to export. MD Content is always included.
            </p>
            <div style="display:flex; flex-direction:column; flex:1; min-height:0; gap:0;">
                <div class="mde-picker-header">
                    <input type="text" id="mdeSearchInput" placeholder="Search docs & content..." oninput="mdeDebounceSearch()">
                    <span id="mdeSearchSpinner" style="display:none; font-size:0.8rem; margin-left:5px;">⏳</span>
                    <button class="mde-picker-select-all" onclick="mdePickerToggleAll()" style="margin-left:auto;">Select all</button>
                </div>
                <div class="mde-picker-tree-wrap" id="mde-picker-tree-wrap">
                    <div class="mde-picker-loading">Loading tree…</div>
                </div>
            </div>
            <div class="mde-options-strip">
                <div class="mde-option-row">
                    <input type="checkbox" id="mde-with-meta" checked>
                    <label for="mde-with-meta">
                        <strong>Include Meta Info</strong>
                        <span style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:1px;">Keywords, short description, description.</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="mde-footer">
            <span class="mde-footer-left" id="mde-picker-count"></span>
            <button class="btn ghost" style="padding:6px 11px; border:1px solid var(--border); background:transparent; color:var(--text); border-radius:6px; cursor:pointer;" onclick="mdeCloseModal()">Cancel</button>
            <button class="btn primary" style="padding:6px 11px; background:var(--accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:bold;" id="mde-export-btn" onclick="mdeDoExport()">
                &#x1F4E5; Export
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
            // Root Uncategorized folder
            mdeTreeRaw.push({ id: 'c_0', parent: '#', text: 'Uncategorized', type: 'folder' });
            
            res.categories.forEach(c => {
                mdeTreeRaw.push({ id: 'c_' + c.id, parent: '#', text: c.name, type: 'folder' });
            });
            
            res.docs.forEach(d => {
                const catId = d.category_id ? 'c_' + d.category_id : 'c_0';
                mdeTreeRaw.push({ id: 'd_' + d.id, parent: catId, text: d.name, type: 'node', doc_id: d.id });
            });
            
            mdeTreeChecked = new Set(mdeTreeRaw.map(n => n.id));
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
        const icon = isFolder ? '📁' : '📄';

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
                    labelHtml = hits[node.doc_id].name_hl; // server sends safe HTML marked up
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
            ? `<span class="mde-node-toggle ${isOpenClass}" onclick="mdePickerToggleFolder('${jsId}', this)">▶</span>`
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
    // We pass current hits if there's a search term
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
    btn.innerHTML = '⏳ Building…';
    
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
        btn.innerHTML = '&#x1F4E5; Export';
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




