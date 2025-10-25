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

error_reporting(0);
ini_set('display_errors', '0');

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
