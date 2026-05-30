<?php
// public/rapid_import_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// RAPID IMPORT FORGE — MD Importer for Rapid Showcase
// Forge design system port of rapid_import.php.
// All import logic preserved exactly.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$em     = $spw->getEntityManager();
$conn   = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) { header('Location: /login.php'); exit; }

// ── LOAD INSTRUCTIONS (preserved exactly from rapid_import.php) ──────────────
$instructionFile    = __DIR__ . '/rapid.json';
$instructionContent = file_exists($instructionFile)
    ? file_get_contents($instructionFile)
    : "{\n  \"error\": \"rapid.json not found in public directory.\"\n}";

// ── AJAX HANDLER (preserved exactly from rapid_import.php) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['md_file'])) {
    header('Content-Type: application/json');

    $file = $_FILES['md_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'File upload failed error code: ' . $file['error']]);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'md' && $ext !== 'txt') {
        echo json_encode(['ok' => false, 'error' => 'Only .md or .txt files allowed']);
        exit;
    }

    $content = file_get_contents($file['tmp_name']);
    $stats   = ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0, 'log' => []];

    $genRows = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active=1");
    $genMap  = [];
    foreach ($genRows as $row) {
        $key          = strtolower(preg_replace('/[^a-z0-9]/i', '', $row['title']));
        $genMap[$key] = $row['config_id'];
    }

    $fallbackGenId = '446437576e785bbf3d188624dd9794eb';
    $sections      = preg_split('/^##\s+(.+)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 1; $i < count($sections); $i += 2) {
        $categoryRaw = trim($sections[$i]);
        $category    = preg_replace('/^\d+\.\s+/', '', $categoryRaw);
        $block       = $sections[$i + 1];

        $currentGenId  = $fallbackGenId;
        $genNameFound  = "Default";

        if (preg_match('/\*\*Generator\*\*:\s*`?([^`\n]+)`?/i', $block, $m)) {
            $rawGenName = trim($m[1]);
            $lookupKey  = strtolower(preg_replace('/[^a-z0-9]/i', '', $rawGenName));
            if (isset($genMap[$lookupKey])) {
                $currentGenId = $genMap[$lookupKey];
                $genNameFound = $rawGenName . " (Matched)";
            } else {
                foreach ($genMap as $k => $id) {
                    if (str_contains($k, $lookupKey) || str_contains($lookupKey, $k)) {
                        $currentGenId = $id;
                        $genNameFound = $rawGenName . " (Fuzzy Match)";
                        break;
                    }
                }
            }
        }

        $pattern = '/###\s*([A-Z0-9-_]+):\s*(.*?)\s*\n.*?```(.*?)```/s';

        if (preg_match_all($pattern, $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $refCode     = trim($match[1]);
                $title       = trim($match[2]);
                $promptRaw   = trim($match[3]);
                $cleanPrompt = preg_replace('/\s+/', ' ', $promptRaw);

                $stats['processed']++;

                try {
                    $ins = $conn->prepare("INSERT INTO rapid_showcase (reference_code, title, category, description_prompt, generator_config_id, is_generated, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
                    $ins->bindValue(1, $refCode);
                    $ins->bindValue(2, $title);
                    $ins->bindValue(3, $category);
                    $ins->bindValue(4, $cleanPrompt);
                    $ins->bindValue(5, $currentGenId);
                    $ins->executeStatement();

                    $stats['inserted']++;
                    $stats['log'][] = "[NEW] $refCode ($category)";
                } catch (Exception $ex) {
                    $stats['errors']++;
                    $stats['log'][] = "[ERR] $refCode: " . $ex->getMessage();
                }
            }
        }
    }

    echo json_encode(['ok' => true, 'stats' => $stats]);
    exit;
}

$viewportScale = !empty($_GET['embed']) ? '1.0' : '0.9';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Rapid Import Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark')  document.documentElement.setAttribute('data-theme', 'dark');
        else if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch(e) {}
})();
</script>
<style>
/* ═══════════════════════════════════════════════════════════════
   FORGE DESIGN SYSTEM
═══════════════════════════════════════════════════════════════ */
:root {
    --bg:#080b10; --surface:#0e1319; --card:#111820; --card-hover:#141e28;
    --border:#1c2535; --border-glow:#2a3a52; --text:#c8d4e8; --text-dim:#5a6a80;
    --text-bright:#e8f0ff; --amber:#f5a623; --amber-dim:rgba(245,166,35,0.08);
    --amber-mid:rgba(245,166,35,0.15); --amber-glow:rgba(245,166,35,0.4);
    --green:#22d3a0; --green-dim:rgba(34,211,160,0.1);
    --red:#f05060; --red-dim:rgba(240,80,96,0.1);
    --blue:#4da6ff; --blue-dim:rgba(77,166,255,0.1);
    --mono:'Space Mono','Fira Mono',monospace;
    --sans:'Syne',system-ui,sans-serif;
    --radius:6px; --radius-lg:10px;
}
@media (prefers-color-scheme:light) { :root {
    --bg:#f6f8fa; --surface:#e1e4e8; --card:#ffffff; --card-hover:#f3f4f6;
    --border:#d1d5db; --border-glow:#9ca3af; --text:#111827; --text-dim:#4b5563;
    --text-bright:#000000; --amber:#d97706; --amber-dim:rgba(217,119,6,0.1);
    --amber-mid:rgba(217,119,6,0.2); --amber-glow:rgba(217,119,6,0.4);
    --green:#059669; --green-dim:rgba(5,150,105,0.1);
    --red:#dc2626; --red-dim:rgba(220,38,38,0.1);
    --blue:#2563eb; --blue-dim:rgba(37,99,235,0.1);
}}
:root[data-theme="light"],html[data-theme="light"],body[data-theme="light"] {
    --bg:#f6f8fa; --surface:#e1e4e8; --card:#ffffff; --card-hover:#f3f4f6;
    --border:#d1d5db; --border-glow:#9ca3af; --text:#111827; --text-dim:#4b5563;
    --text-bright:#000000; --amber:#d97706; --amber-dim:rgba(217,119,6,0.1);
    --amber-mid:rgba(217,119,6,0.2); --amber-glow:rgba(217,119,6,0.4);
    --green:#059669; --green-dim:rgba(5,150,105,0.1);
    --red:#dc2626; --red-dim:rgba(220,38,38,0.1);
    --blue:#2563eb; --blue-dim:rgba(37,99,235,0.1);
}
:root[data-theme="dark"],html[data-theme="dark"],body[data-theme="dark"] {
    --bg:#080b10; --surface:#0e1319; --card:#111820; --card-hover:#141e28;
    --border:#1c2535; --border-glow:#2a3a52; --text:#c8d4e8; --text-dim:#5a6a80;
    --text-bright:#e8f0ff; --amber:#f5a623; --amber-dim:rgba(245,166,35,0.08);
    --amber-mid:rgba(245,166,35,0.15); --amber-glow:rgba(245,166,35,0.4);
    --green:#22d3a0; --green-dim:rgba(34,211,160,0.1);
    --red:#f05060; --red-dim:rgba(240,80,96,0.1);
    --blue:#4da6ff; --blue-dim:rgba(77,166,255,0.1);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html,body { height:100%; background:var(--bg); color:var(--text); font-family:var(--sans); font-size:14px; line-height:1.5; -webkit-font-smoothing:antialiased; overflow:hidden; }
::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--border-glow); border-radius:4px; }

/* ── LAYOUT ── */
.forge-layout { display:grid; grid-template-rows:52px 1fr; grid-template-columns:240px 1fr; grid-template-areas:"header header""sidebar main"; height:100vh; height:100dvh; overflow:hidden; }

/* ── HEADER ── */
.forge-header { grid-area:header; display:flex; align-items:center; justify-content:space-between; padding:0 20px; background:var(--surface); border-bottom:1px solid var(--border); z-index:100; }
.forge-logo { display:flex; align-items:center; gap:10px; font-family:var(--mono); font-size:0.85rem; font-weight:700; color:var(--amber); letter-spacing:2px; text-transform:uppercase; }
.forge-logo-icon { width:28px; height:28px; background:var(--amber-mid); border:1px solid var(--amber-glow); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-header-right { display:flex; align-items:center; gap:10px; }
.btn-icon-sm { width:36px; height:36px; border-radius:var(--radius); border:1px solid var(--border); background:var(--card); color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:15px; text-decoration:none; }
.btn-icon-sm:hover { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }

/* ── SIDEBAR (nav only) ── */
.forge-sidebar { grid-area:sidebar; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; padding:16px 12px; gap:6px; }
.nav-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:var(--radius); border:1px solid transparent; cursor:pointer; text-decoration:none; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem; transition:all 0.15s; }
.nav-item:hover { background:var(--card); border-color:var(--border); color:var(--text); }
.nav-item.active { background:var(--amber-dim); border-color:var(--amber); color:var(--amber); }
.nav-item i { font-size:14px; flex-shrink:0; }
.nav-divider { height:1px; background:var(--border); margin:8px 0; }

/* ── MAIN ── */
.forge-main { grid-area:main; display:flex; flex-direction:column; overflow:hidden; background:var(--bg); }

/* ── WORKSPACE ── */
.forge-workspace { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.workspace-body { flex:1; display:grid; grid-template-columns:1fr 340px; overflow:hidden; }

/* Drop panel */
.drop-panel { padding:28px; overflow-y:auto; border-right:1px solid var(--border); display:flex; flex-direction:column; gap:20px; }
.panel-label { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin-bottom:14px; display:flex; align-items:center; gap:6px; }
.panel-label::after { content:''; flex:1; height:1px; background:var(--border); }

/* Upload zone */
.upload-zone {
    border:2px dashed var(--border-glow); border-radius:var(--radius-lg); padding:40px 20px;
    text-align:center; background:var(--card); cursor:pointer; transition:all 0.2s;
    display:flex; flex-direction:column; align-items:center; gap:12px;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color:var(--amber); background:var(--amber-dim);
}
.upload-zone-icon { font-size:36px; opacity:0.5; }
.upload-zone-title { font-family:var(--mono); font-size:0.85rem; color:var(--text-bright); }
.upload-zone-sub   { font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); }

/* Stats row */
.stats-row { display:flex; gap:10px; flex-wrap:wrap; }
.stat-card { flex:1; min-width:80px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:10px 14px; }
.stat-card-label { font-family:var(--mono); font-size:0.63rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.stat-card-value { font-family:var(--mono); font-size:1.2rem; font-weight:700; }
.stat-new  { color:var(--green); }
.stat-err  { color:var(--red); }

/* Instructions JSON box */
.json-box { flex:1; overflow:auto; font-family:var(--mono); font-size:0.72rem; padding:14px; background:#050a05; color:#4ade80; border:1px solid var(--border); border-radius:var(--radius); white-space:pre-wrap; word-break:break-all; }

/* Right: log panel */
.log-panel { display:flex; flex-direction:column; overflow:hidden; background:var(--bg); }
.log-panel-header { padding:12px 16px; border-bottom:1px solid var(--border); background:var(--surface); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.log-panel-title { font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1.5px; }
.log-console { flex:1; overflow-y:auto; padding:14px; font-family:var(--mono); font-size:0.75rem; line-height:1.6; color:#4ade80; background:#050a05; white-space:pre-wrap; word-break:break-word; }
.log-console .ts { opacity:0.45; }

/* Action bar */
.action-bar { padding:14px 20px; border-top:1px solid var(--border); border-right:1px solid var(--border); background:var(--surface); display:flex; gap:8px; align-items:center; flex-shrink:0; }
.btn-forge-primary { padding:10px 20px; background:var(--amber); color:#000; border:none; border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; transition:all 0.15s; }
.btn-forge-primary:hover { filter:brightness(1.1); }
.btn-forge-secondary { padding:10px 16px; background:transparent; color:var(--text-dim); border:1px solid var(--border); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.78rem; transition:all 0.15s; }
.btn-forge-secondary:hover { border-color:var(--border-glow); color:var(--text); }

/* ── Instruction modal ── */
.forge-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(3px); z-index:10000; display:none; align-items:center; justify-content:center; padding:16px; }
.forge-modal-overlay.open { display:flex; }
.forge-modal { background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:100%; max-width:680px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.6); animation:modalIn 0.2s ease; }
.forge-modal-header { padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.forge-modal-title { font-family:var(--mono); font-size:0.8rem; font-weight:700; color:var(--amber); text-transform:uppercase; letter-spacing:1.5px; }
.forge-modal-close { width:28px; height:28px; border-radius:4px; border:1px solid var(--border); background:transparent; color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-modal-close:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); }
.forge-modal-body { padding:20px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:14px; }

/* ── TOAST ── */
.forge-toast-container { position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.forge-toast { padding:10px 16px; border-radius:var(--radius); background:var(--card); border:1px solid var(--border); font-family:var(--mono); font-size:0.8rem; color:var(--text); box-shadow:0 4px 20px rgba(0,0,0,0.5); animation:toastIn 0.25s ease; pointer-events:all; cursor:pointer; max-width:320px; display:flex; align-items:center; gap:8px; }
.forge-toast.success { border-color:var(--green); }
.forge-toast.error   { border-color:var(--red); color:var(--red); }
.forge-toast.info    { border-color:var(--amber); }
.forge-toast.out     { animation:toastOut 0.25s ease forwards; }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
@keyframes toastOut { to{opacity:0;transform:translateY(10px)} }
@keyframes modalIn  { from{opacity:0;transform:scale(0.96) translateY(-10px)} to{opacity:1;transform:none} }

@media (max-width:900px) {
    .forge-layout { grid-template-columns:1fr; grid-template-rows:52px auto 1fr; grid-template-areas:"header""sidebar""main"; }
    .forge-sidebar { border-right:none; border-bottom:1px solid var(--border); flex-direction:row; flex-wrap:wrap; padding:10px; height:auto; }
    .nav-divider { display:none; }
    .workspace-body { grid-template-columns:1fr; grid-template-rows:auto 220px; }
    .log-panel { border-top:1px solid var(--border); }
    .action-bar { border-right:none; }
}
</style>
</head>
<body>
<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-download"></i></div>
            Import Forge
        </div>
        <div class="forge-header-right">
            <button class="btn-icon-sm" onclick="ImportForge.openInstructionsModal()" title="AI Instructions"><i class="bi bi-robot"></i></button>
            <a href="rapid_forge.php"        class="btn-icon-sm" title="Generator"><i class="bi bi-rocket-takeoff"></i></a>
            <a href="rapid_config_forge.php"  class="btn-icon-sm" title="Config"><i class="bi bi-gear"></i></a>
            <a href="/dashboard.php"          class="btn-icon-sm" title="Dashboard" style="text-decoration:none;"><i class="bi bi-house"></i></a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <a href="rapid_forge.php"       class="nav-item"><i class="bi bi-rocket-takeoff"></i> Generator</a>
        <a href="rapid_import_forge.php" class="nav-item active"><i class="bi bi-download"></i> Importer</a>
        <a href="rapid_config_forge.php" class="nav-item"><i class="bi bi-gear"></i> Config</a>
        <div class="nav-divider"></div>
        <button class="nav-item" onclick="ImportForge.openInstructionsModal()" style="border:none; background:none; text-align:left; width:100%;">
            <i class="bi bi-robot"></i> AI Instructions
        </button>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main">
        <div class="forge-workspace">
            <div class="workspace-body">

                <!-- ── DROP PANEL ── -->
                <div class="drop-panel">
                    <div>
                        <div class="panel-label">Import Scenarios</div>
                        <div style="font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); margin-bottom:16px; line-height:1.6;">
                            Drag &amp; drop a Markdown file to parse scenarios into <code style="color:var(--amber);">rapid_showcase</code>.
                            Always creates new entries — no overwrites.
                        </div>

                        <form id="uploadForm">
                            <input type="file" id="fileInput" name="md_file" accept=".md,.txt" style="display:none">
                            <div class="upload-zone" id="dropZone">
                                <div class="upload-zone-icon"><i class="bi bi-file-earmark-arrow-up"></i></div>
                                <div class="upload-zone-title">Click to Browse or Drag MD File</div>
                                <div class="upload-zone-sub">.md or .txt files only</div>
                            </div>
                        </form>
                    </div>

                    <div id="statsRow" class="stats-row" style="display:none;">
                        <div class="stat-card">
                            <div class="stat-card-label">New</div>
                            <div class="stat-card-value stat-new" id="countNew">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-label">Errors</div>
                            <div class="stat-card-value stat-err" id="countErr">0</div>
                        </div>
                    </div>
                </div>

                <!-- ── LOG PANEL ── -->
                <div class="log-panel">
                    <div class="log-panel-header">
                        <span class="log-panel-title">Import Log</span>
                        <button class="btn-icon-sm" style="width:28px;height:28px;font-size:12px;" onclick="document.getElementById('importLog').innerHTML=''" title="Clear"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="log-console" id="importLog"><span class="ts">[system]</span> Ready to import…</div>
                </div>

            </div><!-- /workspace-body -->

            <div class="action-bar">
                <button class="btn-forge-primary" onclick="document.getElementById('fileInput').click()">
                    <i class="bi bi-folder2-open"></i> Browse File
                </button>
                <button class="btn-forge-secondary" onclick="ImportForge.openInstructionsModal()">
                    <i class="bi bi-robot"></i> AI Instructions
                </button>
            </div>
        </div>
    </main>

</div><!-- /forge-layout -->

<!-- ── INSTRUCTIONS MODAL ── -->
<div class="forge-modal-overlay" id="instructionsModal">
    <div class="forge-modal">
        <div class="forge-modal-header">
            <div class="forge-modal-title">AI Instruction Protocol</div>
            <button class="forge-modal-close" onclick="ImportForge.closeInstructionsModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <div style="font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); line-height:1.6;">
                Copy the JSON below and provide it to an LLM (ChatGPT, Claude, etc.) to generate perfectly formatted Markdown files for this importer.
            </div>
            <pre class="json-box" id="jsonInstruction"><?php echo htmlspecialchars($instructionContent); ?></pre>
            <div style="display:flex; justify-content:flex-end;">
                <button class="btn-forge-primary" id="btnCopyInstruction" onclick="ImportForge.copyInstruction()">
                    <i class="bi bi-clipboard"></i> Copy JSON
                </button>
            </div>
        </div>
    </div>
</div>

<div class="forge-toast-container" id="toastContainer"></div>

<script>
const ImportForge = (() => {
    'use strict';

    function toast(msg, type = 'info', duration = 3500) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        const icons = { success:'✓', error:'✕', info:'◆' };
        el.innerHTML = `<span style="font-size:12px;">${icons[type]||'◆'}</span> ${msg}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        function dismiss(e) { e.classList.add('out'); setTimeout(()=>e.remove(),300); }
        setTimeout(()=>dismiss(el), duration);
    }

    function log(msg) {
        const c   = document.getElementById('importLog');
        const now = new Date().toLocaleTimeString([], {hour12:false});
        c.innerHTML += `\n<span class="ts">[${now}]</span> ${msg}`;
        c.scrollTop = c.scrollHeight;
    }

    // ── Instructions modal ────────────────────────────────────────────────────

    function openInstructionsModal() {
        document.getElementById('instructionsModal').classList.add('open');
    }
    function closeInstructionsModal() {
        document.getElementById('instructionsModal').classList.remove('open');
    }
    function copyInstruction() {
        const text = document.getElementById('jsonInstruction').textContent;
        const btn  = document.getElementById('btnCopyInstruction');
        navigator.clipboard.writeText(text).then(() => {
            toast('Instructions copied!', 'success');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        });
    }

    // ── Upload (preserved exactly from rapid_import.php) ─────────────────────

    async function handleUpload(file) {
        const dropZone = document.getElementById('dropZone');
        dropZone.innerHTML = `
            <div class="upload-zone-icon" style="animation:spin 0.75s linear infinite; display:inline-block;"><i class="bi bi-arrow-repeat"></i></div>
            <div class="upload-zone-title">Processing…</div>`;

        document.getElementById('statsRow').style.display = 'none';
        log(`📄 Uploading: ${file.name}`);

        const formData = new FormData();
        formData.append('md_file', file);

        try {
            const res  = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.ok) {
                dropZone.innerHTML = `
                    <div class="upload-zone-icon" style="color:var(--green);"><i class="bi bi-check-circle"></i></div>
                    <div class="upload-zone-title">Done! Drop another?</div>
                    <div class="upload-zone-sub">${file.name}</div>`;

                document.getElementById('statsRow').style.display = 'flex';
                document.getElementById('countNew').textContent = data.stats.inserted;
                document.getElementById('countErr').textContent = data.stats.errors;

                log(`<strong>Results:</strong> ${data.stats.inserted} new, ${data.stats.errors} errors.`);
                log('─────────────────────────────');
                data.stats.log.forEach(line => log(line));
                toast(`Imported ${data.stats.inserted} scenarios`, 'success');
            } else {
                dropZone.innerHTML = `
                    <div class="upload-zone-icon" style="color:var(--red);"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="upload-zone-title">Upload Error</div>`;
                log(`<span style="color:var(--red)">Error: ${data.error}</span>`);
                toast(data.error, 'error');
            }
        } catch(e) {
            dropZone.innerHTML = `
                <div class="upload-zone-icon" style="color:var(--red);"><i class="bi bi-x-circle"></i></div>
                <div class="upload-zone-title">System Error</div>`;
            log(`<span style="color:var(--red)">System Error: ${e.message}</span>`);
            toast(e.message, 'error');
        }
    }

    function bindEvents() {
        const dropZone  = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) handleUpload(e.dataTransfer.files[0]);
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) handleUpload(fileInput.files[0]);
        });

        // Close modal on overlay click
        document.getElementById('instructionsModal').addEventListener('click', e => {
            if (e.target === document.getElementById('instructionsModal')) closeInstructionsModal();
        });
    }

    function init() {
        bindEvents();
    }

    return { init, openInstructionsModal, closeInstructionsModal, copyInstruction };
})();

document.addEventListener('DOMContentLoaded', () => ImportForge.init());
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
