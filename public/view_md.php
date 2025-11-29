<?php
// public/view_md.php
//
// Markdown viewer integrated into SAGE
// - Renders .md files from public/docs/
// - GitHub-style CSS (light/dark) taken from /vendor/github-markdown-css/
// - Prevents path-traversal and only serves *.md files
// - Uses Parsedown in safe mode to avoid raw HTML/script injection
//
//

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

//use Parsedown;

$spw = \App\Core\SpwBase::getInstance();

$docDirRel = '/docs/'; // relative to public root (URL)
$docDirAbs = realpath($spw->getPublicPath() . $docDirRel) . DIRECTORY_SEPARATOR;

// Security: ensure doc directory exists and is inside public
if ($docDirAbs === false || strpos($docDirAbs, realpath($spw->getPublicPath())) !== 0) {
    die("Documentation directory not found or misconfigured.");
}

$fileParam = $_GET['file'] ?? '';
// Normalize & sanitize file name (allow only .md)
if ($fileParam !== '') {
    // allow only basename-ish names and subpaths inside docs/ (but prevent traversal)
    $requested = $docDirAbs . ltrim($fileParam, "/\\");
    $realRequested = realpath($requested);
    if ($realRequested === false || strpos($realRequested, $docDirAbs) !== 0) {
        http_response_code(404);
        $pageTitle = "Document not found";
        ob_start();
        echo "<div class='alert alert-danger'>File not found or invalid path.</div>";
        $content = ob_get_clean();
        $spw->renderLayout($content, $pageTitle);
        exit;
    }

    // ensure extension is .md
    if (strtolower(pathinfo($realRequested, PATHINFO_EXTENSION)) !== 'md') {
        http_response_code(403);
        $pageTitle = "Forbidden";
        ob_start();
        echo "<div class='alert alert-danger'>Only .md files can be displayed.</div>";
        $content = ob_get_clean();
        $spw->renderLayout($content, $pageTitle);
        exit;
    }

    // Load and render
    $raw = @file_get_contents($realRequested);
    if ($raw === false) {
        http_response_code(500);
        $pageTitle = "Error";
        ob_start();
        echo "<div class='alert alert-danger'>Could not read file.</div>";
        $content = ob_get_clean();
        $spw->renderLayout($content, $pageTitle);
        exit;
    }




// temporarily suppress deprecation notices
$oldErrorReporting = error_reporting();
error_reporting($oldErrorReporting & ~E_DEPRECATED);

// Parse Markdown
$parsedown = new Parsedown();
if (method_exists($parsedown, 'setSafeMode')) {
    $parsedown->setSafeMode(true);
}
$html = $parsedown->text($raw);

// restore previous error reporting
error_reporting($oldErrorReporting);





    // Page title (use filename)
    $pageTitle = htmlspecialchars(basename($realRequested));

    ob_start();
    ?>

    <style>
/* Base page */
body {
    background-color: #0d1117;
    color: #c9d1d9;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
    line-height: 1.6;
    margin: 0;
    padding: 0;
}

/* Container */
.view-container {
    max-width: 980px;
    margin: 0 auto;
    padding: 10px;
}

/* Links */
a {
    color: #58a6ff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
    
    <div class="view-container">
        <div style="margin-bottom:1rem;">
            <a href="/view_md.php">&larr; back to docs index</a>
            &nbsp;|&nbsp;
            <a href="<?php echo htmlspecialchars($docDirRel . basename($realRequested)); ?>" download>Download raw</a>
        </div>

        <!-- Styles for GitHub markdown (light/dark) -->
        <link rel="stylesheet" href="/vendor/github-markdown-css/github-markdown-light.css" media="(prefers-color-scheme: light)">
        <link rel="stylesheet" href="/vendor/github-markdown-css/github-markdown-dark.css" media="(prefers-color-scheme: dark)">
        <style>
	    /* Layout wrapper like GitHub demo */
            body { background: #000; }
            body .markdown-container {
                box-sizing: border-box;
                min-width: 200px;
                max-width: 980px;
                margin: 0 auto;
                padding: 10px;
            }
            /* Ensure images and code wrap nicely */
            .markdown-body img { max-width: 100%; height: auto; }
            pre { white-space: pre-wrap; word-wrap: break-word; }
        </style>

        <div class="markdown-container">






<style>
/* Auto-scroll controls */
.auto-scroll-controls {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 1200;
  background: rgba(10,11,13,0.88);
  color: #c9d1d9;
  border: 1px solid rgba(255,255,255,0.04);
  padding: 10px;
  border-radius: 10px;
  box-shadow: 0 6px 18px rgba(2,6,23,0.6);
  font-family: inherit;
  font-size: 14px;
  max-width: 320px;
}
.auto-scroll-controls .row { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
.auto-scroll-controls button {
  background: transparent;
  border: 1px solid rgba(255,255,255,0.06);
  color: inherit;
  padding:6px 8px;
  border-radius:6px;
  cursor:pointer;
}
.auto-scroll-controls button.primary { border-color: rgba(88,166,255,0.18); }
.auto-scroll-controls input[type="range"] { width: 140px; }
.auto-scroll-controls input[type="number"] { width: 72px; background: #0f1418; border:1px solid #222; color: #c9d1d9; padding:4px;border-radius:6px; }
.auto-scroll-controls small { color: #8b949e; display:block; margin-top:4px; }
@media (max-width:720px) {
  .auto-scroll-controls { left: 10px; right: 10px; bottom: 12px; max-width: unset; display:flex; flex-direction:column; gap:6px; }
}
</style>

<div id="autoScrollControls" class="auto-scroll-controls" aria-hidden="false">
  <div class="row">
    <button id="asc-start" class="primary" title="Start (S)">Start</button>
    <button id="asc-pause" title="Pause (Space)">Pause</button>
    <button id="asc-stop" title="Stop">Stop</button>
    <button id="asc-restart" title="Restart (R)">Restart</button>
  </div>
  <div class="row" style="align-items:center;">
    <label for="asc-duration" style="min-width:58px;">Duration</label>
    <input id="asc-duration" type="range" min="10" max="1200" step="5" value="120">
    <input id="asc-duration-num" type="number" min="10" max="1200" step="1" value="120"> <span style="margin-left:6px;">s</span>
  </div>
  <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;">
    <label style="display:none;gap:6px;align-items:center;"><input style="display: none;" id="asc-autoplay" type="checkbox"> Autoplay</label>
    <button id="asc-hide" title="Hide controls">✕</button>
  </div>
  <small>Space = pause/resume • S = start • R = restart • Manual scroll or blur = paused</small>
</div>

<script>
(function(){
  // state
  let rafId = null;
  let startTs = null;
  let elapsedBeforePause = 0;
  let running = false;
  let paused = false;
  let durationMs = 120000; // default 120s
  let startY = 0;
  let endY = 0;

  const controls = {
    startBtn: document.getElementById('asc-start'),
    pauseBtn: document.getElementById('asc-pause'),
    stopBtn: document.getElementById('asc-stop'),
    restartBtn: document.getElementById('asc-restart'),
    durationRange: document.getElementById('asc-duration'),
    durationNum: document.getElementById('asc-duration-num'),
    autoplayCheckbox: document.getElementById('asc-autoplay'),
    hideBtn: document.getElementById('asc-hide'),
    container: document.getElementById('autoScrollControls')
  };

  function computeBounds() {
    startY = window.scrollY;
    endY = Math.max(document.documentElement.scrollHeight - window.innerHeight, 0);
  }

  function step(ts) {
    if (!startTs) startTs = ts - elapsedBeforePause;
    const elapsed = ts - startTs;
    const progress = Math.min(elapsed / durationMs, 1);
    const y = startY + (endY - startY) * progress;
    window.scrollTo(0, Math.round(y));

    if (progress < 1 && running && !paused) {
      rafId = window.requestAnimationFrame(step);
    } else {
      running = false;
      paused = false;
      startTs = null;
      elapsedBeforePause = 0;
      cancelAnimationFrame(rafId);
    }
  }

  function startAutoScroll(fromTop=false) {
    // compute duration from inputs
    const sec = parseFloat(controls.durationNum.value) || parseFloat(controls.durationRange.value) || 120;
    durationMs = Math.max(10, sec) * 1000;

    computeBounds();
    if (fromTop) {
      window.scrollTo(0, 0);
      startY = 0;
    }
    // nothing to do if no scrollable area
    if (endY <= startY) return;

    running = true;
    paused = false;
    startTs = null;
    elapsedBeforePause = 0;

    cancelAnimationFrame(rafId);
    rafId = window.requestAnimationFrame(step);
    updateUI();
  }

  function pauseAutoScroll() {
    if (!running || paused) return;
    paused = true;
    elapsedBeforePause = (performance.now() - startTs);
    cancelAnimationFrame(rafId);
    updateUI();
  }

  function resumeAutoScroll() {
    if (!running || !paused) return;
    paused = false;
    // keep startTs so step will use startTs - elapsedBeforePause offset
    rafId = window.requestAnimationFrame(step);
    updateUI();
  }

  function stopAutoScroll() {
    running = false;
    paused = false;
    cancelAnimationFrame(rafId);
    startTs = null;
    elapsedBeforePause = 0;
    updateUI();
  }

  function restartAutoScroll() {
    stopAutoScroll();
    // start from top and use current duration
    startAutoScroll(true);
  }

  function updateUI() {
    controls.pauseBtn.textContent = paused ? 'Resume' : 'Pause';
    if (!running) controls.pauseBtn.disabled = true; else controls.pauseBtn.disabled = false;
  }

  // wire buttons
  controls.startBtn.addEventListener('click', function(){ startAutoScroll(false); });
  controls.pauseBtn.addEventListener('click', function(){ if (paused) resumeAutoScroll(); else pauseAutoScroll(); });
  controls.stopBtn.addEventListener('click', stopAutoScroll);
  controls.restartBtn.addEventListener('click', restartAutoScroll);
  controls.durationRange.addEventListener('input', function(){ controls.durationNum.value = controls.durationRange.value; });
  controls.durationNum.addEventListener('input', function(){
    let v = parseInt(controls.durationNum.value) || 120;
    if (v < parseInt(controls.durationNum.min)) v = controls.durationNum.min;
    if (v > parseInt(controls.durationNum.max)) v = controls.durationNum.max;
    controls.durationNum.value = v;
    controls.durationRange.value = v;
  });

  controls.hideBtn.addEventListener('click', function(){ controls.container.style.display='none'; });

  // keyboard shortcuts
  window.addEventListener('keydown', function(e){
    // don't do shortcuts when typing in a field
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable)) return;
    if (e.code === 'Space') {
      e.preventDefault();
      if (!running) startAutoScroll(false);
      else if (paused) resumeAutoScroll();
      else pauseAutoScroll();
    } else if (e.key === 's' || e.key === 'S') {
      startAutoScroll(false);
    } else if (e.key === 'r' || e.key === 'R') {
      restartAutoScroll();
    }
  });

  // pause on manual scroll (user intervened)
  let userScrolledTimer = null;
  window.addEventListener('scroll', function(){
    if (!running) return;
    // if scroll was caused by RAF we shouldn't auto-pause; detect by comparing last RAF-set position?
    // Simpler heuristic: if running and not paused, and user scrolls manually (quick event), pause.
    // throttle to avoid immediate pause when auto-scrolling (RAF scroll events are frequent)
    if (userScrolledTimer) clearTimeout(userScrolledTimer);
    userScrolledTimer = setTimeout(function(){
      // if running and not paused, consider this user intervention -> pause
      if (running && !paused) {
        pauseAutoScroll();
      }
    }, 120);
  }, { passive: true });

  // pause when window loses focus
  window.addEventListener('blur', function(){ if (running && !paused) pauseAutoScroll(); });

  // URL params: ?autoscroll=1&duration=120
  (function handleUrlParams(){
    const params = new URLSearchParams(window.location.search);
    const autoscroll = params.get('autoscroll');
    const duration = params.get('duration');
    if (duration) {
      let d = parseInt(duration, 10);
      if (!isNaN(d)) {
        controls.durationNum.value = d;
        controls.durationRange.value = d;
      }
    }
    if (autoscroll === '1' || controls.autoplayCheckbox.checked) {
      controls.autoplayCheckbox.checked = true;
      // start after a short delay to let page render
      setTimeout(function(){ startAutoScroll(false); }, 400);
    }
  })();

  // Keep UI in sync on resize (recompute end)
  window.addEventListener('resize', function(){ computeBounds(); });

  // initial UI sync
  updateUI();

})();
</script>






            <article class="markdown-body">
                <?php echo $html; ?>
            </article>
        </div>
    </div>

    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content.$eruda, $pageTitle);
    exit;
}

// NO file param -> show index of .md files
$files = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docDirAbs, FilesystemIterator::SKIP_DOTS));
foreach ($iter as $fileinfo) {
    if ($fileinfo->isFile() && strtolower($fileinfo->getExtension()) === 'md') {
        // compute path relative to docs dir for URL
        $rel = substr($fileinfo->getRealPath(), strlen($docDirAbs));
        $files[] = $rel;
    }
}
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$pageTitle = "Documentation";

ob_start();
?>

<style>
/* Base page */
body {
    background-color: #0d1117;
    color: #c9d1d9;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
    line-height: 1.6;
    margin: 0;
    padding: 1.5rem;
}

/* Container */
.view-container {
    max-width: 980px;
    margin: 0 auto;
    padding: 10px;
}

/* Headings */
h1, h2, h3, h4, h5, h6 {
    color: #c9d1d9;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    font-weight: 600;
}

/* Links */
a {
    color: #58a6ff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}

/* Lists */
ul, ol {
    padding-left: 2rem;
    margin-bottom: 1rem;
}
li {
    margin-bottom: 0.5rem;
}

/* Search input */
input#md-search {
    background-color: #161b22;
    color: #c9d1d9;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 0.5rem;
    width: 100%;
    box-sizing: border-box;
}
input#md-search::placeholder {
    color: #8b949e;
}

/* List items hover */
ul#md-list li:hover {
    background-color: #161b22;
    border-radius: 4px;
    padding-left: 0.25rem;
}

/* Download links */
ul#md-list li a[href$=".md"] {
    color: #58a6ff;
}
ul#md-list li a[href$=".md"]:hover {
    text-decoration: underline;
}

/* Optional minor separators */
ul#md-list li + li {
    margin-top: 0.25rem;
}

/* Scrollbar for long lists */
ul#md-list {
    max-height: 70vh;
    overflow-y: auto;
    padding-right: 0.5rem;
}
ul#md-list::-webkit-scrollbar {
    width: 8px;
}
ul#md-list::-webkit-scrollbar-thumb {
    background-color: #30363d;
    border-radius: 4px;
}
</style>

<div class="view-container">
    <h1>Documentation</h1>

    <?php if (count($files) === 0): ?>
        <div class="alert alert-info">No .md files found in the documentation folder.</div>
    <?php else: ?>

        <input id="md-search" placeholder="Filter files..." style="width:100%;padding:.5rem;margin-bottom:1rem;border:1px solid #ccc;border-radius:4px;">
        <ul id="md-list">
            <?php foreach ($files as $f): ?>
                <li style="margin: .35rem 0;">
                    <a href="/view_md.php?file=<?php echo urlencode($f); ?>"><?php echo htmlspecialchars($f); ?></a>
                    &nbsp;&nbsp;
                    <a href="<?php echo htmlspecialchars($docDirRel . $f); ?>" download style="font-size:.9em;color:#666;">(download)</a>
                </li>
            <?php endforeach; ?>
        </ul>

        <script>
            // small client-side filter (no dependencies)
            (function(){
                var input = document.getElementById('md-search');
                var list = document.getElementById('md-list');
                input.addEventListener('input', function(){
                    var q = input.value.toLowerCase().trim();
                    Array.from(list.getElementsByTagName('li')).forEach(function(li){
                        var txt = li.textContent.toLowerCase();
                        li.style.display = txt.indexOf(q) === -1 ? 'none' : '';
                    });
                });
            })();
        </script>

    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
