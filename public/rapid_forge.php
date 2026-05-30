<?php
// public/rapid_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// RAPID FORGE — Rapid Showcase Generator
// Forge design system port of rapid_gen.php.
// Sidebar = pending job queue. Main panel = current job + generation controls.
// All AJAX from rapid_gen.php preserved exactly.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

define('ID_NAME_GEN', '9bf6de291765e2ced28589de857a9f0b');
define('ID_DESC_GEN', '446437576e785bbf3d188624dd9794eb');

$em     = $spw->getEntityManager();
$conn   = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) { header('Location: /login.php'); exit; }

// ── AJAX HANDLERS (preserved exactly from rapid_gen.php) ─────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'get_next_job') {
        $sql = "SELECT * FROM rapid_showcase WHERE is_generated = 0 AND is_archived = 0 ORDER BY id ASC LIMIT 1";
        $job = $conn->fetchAssociative($sql);
        if ($job) {
            echo json_encode(['ok' => true, 'job' => $job]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'No more pending active scenarios.']);
        }
        exit;
    }

    if ($_GET['action'] === 'mark_complete' && isset($_POST['rapid_id'], $_POST['sketch_id'])) {
        $rId = (int)$_POST['rapid_id'];
        $sId = (int)$_POST['sketch_id'];
        $upd  = "UPDATE rapid_showcase SET is_generated = 1, created_sketch_id = ? WHERE id = ?";
        $stmt = $conn->prepare($upd);
        $stmt->bindValue(1, $sId);
        $stmt->bindValue(2, $rId);
        $stmt->executeStatement();
        echo json_encode(['ok' => true]);
        exit;
    }

    // get_queue_list — sidebar population
    if ($_GET['action'] === 'get_queue_list') {
        $sql = "SELECT id, reference_code, title, category, is_generated
                FROM rapid_showcase
                WHERE is_archived = 0
                ORDER BY is_generated ASC, id ASC
                LIMIT 200";
        $rows = $conn->fetchAllAssociative($sql);
        $pending = $conn->fetchOne("SELECT COUNT(*) FROM rapid_showcase WHERE is_generated = 0 AND is_archived = 0");
        echo json_encode(['ok' => true, 'rows' => $rows, 'pending' => (int)$pending]);
        exit;
    }
}

// ── SAVE HANDLER (preserved exactly from rapid_gen.php) ──────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $payload     = $_POST;
    $name        = trim($payload['name'] ?? '');
    $description = trim($payload['description'] ?? '');

    if (empty($name)) $errors[] = 'Name is required';

    if (empty($errors)) {
        $sql  = "INSERT INTO sketches (name, description, `order`, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $name);
        $stmt->bindValue(2, $description);
        $stmt->executeStatement();
        $newId = (int)$conn->lastInsertId();

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'created' => true, 'id' => $newId, 'message' => 'Sketch created successfully']);
        exit;
    } else {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }
}

// ── Fetch Generators (preserved exactly) ─────────────────────────────────────
$repo       = $em->getRepository(App\Entity\GeneratorConfig::class);
$generators = [];
if ($userId) {
    $qb = $repo->createQueryBuilder('g')
        ->where('g.userId = :userId OR g.isPublic = :isPublic')
        ->andWhere('g.active = :isActive')
        ->setParameter('userId', $userId)
        ->setParameter('isPublic', true)
        ->setParameter('isActive', true)
        ->orderBy('g.title', 'ASC');
    $generators = $qb->getQuery()->getResult();
}

$viewportScale = !empty($_GET['embed']) ? '1.0' : '0.9';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Rapid Forge</title>
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
    --bg:          #080b10; --surface:    #0e1319; --card:       #111820;
    --card-hover:  #141e28; --border:     #1c2535; --border-glow:#2a3a52;
    --text:        #c8d4e8; --text-dim:   #5a6a80; --text-bright:#e8f0ff;
    --amber:       #f5a623; --amber-dim:  rgba(245,166,35,0.08);
    --amber-mid:   rgba(245,166,35,0.15); --amber-glow: rgba(245,166,35,0.4);
    --green:       #22d3a0; --green-dim:  rgba(34,211,160,0.1);
    --red:         #f05060; --red-dim:    rgba(240,80,96,0.1);
    --blue:        #4da6ff; --blue-dim:   rgba(77,166,255,0.1);
    --mono: 'Space Mono','Fira Mono',monospace;
    --sans: 'Syne',system-ui,sans-serif;
    --radius: 6px; --radius-lg: 10px;
}
@media (prefers-color-scheme: light) { :root {
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
.forge-layout { display:grid; grid-template-rows:52px 1fr; grid-template-columns:300px 1fr; grid-template-areas:"header header""sidebar main"; height:100vh; height:100dvh; overflow:hidden; }

/* ── HEADER ── */
.forge-header { grid-area:header; display:flex; align-items:center; justify-content:space-between; padding:0 20px; background:var(--surface); border-bottom:1px solid var(--border); z-index:100; }
.forge-logo { display:flex; align-items:center; gap:10px; font-family:var(--mono); font-size:0.85rem; font-weight:700; color:var(--amber); letter-spacing:2px; text-transform:uppercase; }
.forge-logo-icon { width:28px; height:28px; background:var(--amber-mid); border:1px solid var(--amber-glow); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-header-right { display:flex; align-items:center; gap:10px; }
.forge-header-stat { display:flex; align-items:center; font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); padding:4px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); }
.forge-header-stat .val { color:var(--amber); margin-right:4px; }
.btn-icon-sm { width:36px; height:36px; border-radius:var(--radius); border:1px solid var(--border); background:var(--card); color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:15px; text-decoration:none; }
.btn-icon-sm:hover { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }
.btn-icon-sm.running { border-color:var(--green); color:var(--green); background:var(--green-dim); animation:pulse 1.5s ease-in-out infinite; }
.btn-icon-sm.danger  { border-color:var(--red); color:var(--red); background:var(--red-dim); }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(34,211,160,0.4)} 50%{box-shadow:0 0 0 4px rgba(34,211,160,0)} }

/* ── SIDEBAR ── */
.forge-sidebar { grid-area:sidebar; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
.sidebar-search { padding:12px; border-bottom:1px solid var(--border); flex-shrink:0; }
.sidebar-search-input { width:100%; padding:8px 10px 8px 32px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.8rem; transition:border-color 0.2s; }
.sidebar-search-input:focus { outline:none; border-color:var(--amber); }
.sidebar-search-wrap { position:relative; }
.sidebar-search-wrap::before { content:'⌕'; position:absolute; left:8px; top:50%; transform:translateY(-50%); color:var(--text-dim); font-size:16px; pointer-events:none; }
.sidebar-count { padding:4px 12px 8px; font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); flex-shrink:0; }
.sidebar-list { flex:1; overflow-y:auto; padding:8px; }

.job-card { padding:8px 10px 8px 12px; border-radius:var(--radius); border:1px solid transparent; cursor:pointer; transition:all 0.15s; margin-bottom:3px; position:relative; background:transparent; display:flex; align-items:center; gap:8px; }
.job-card:hover { background:var(--card); border-color:var(--border); }
.job-card.active { background:var(--amber-dim); border-color:var(--amber); }
.job-card.active .job-card-title { color:var(--amber); }
.job-card.done { opacity:0.45; }
.job-card-indicator { position:absolute; left:0; top:50%; transform:translateY(-50%); width:2px; height:0; background:var(--amber); border-radius:0 2px 2px 0; transition:height 0.2s; }
.job-card.active .job-card-indicator { height:60%; }
.job-card-body { flex:1; min-width:0; }
.job-card-title { font-family:var(--sans); font-weight:600; font-size:0.82rem; color:var(--text-bright); line-height:1.3; margin-bottom:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; transition:color 0.15s; }
.job-card-meta { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }

.gen-badge { font-family:var(--mono); font-size:0.63rem; padding:1px 5px; border-radius:3px; border:1px solid; }
.gen-badge.model  { border-color:var(--border-glow); color:var(--text-dim); background:var(--card); }
.gen-badge.done   { border-color:var(--green); color:var(--green); background:var(--green-dim); }
.gen-badge.pending{ border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }

.sidebar-empty { text-align:center; padding:40px 20px; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem; }
.sidebar-loading-spinner { width:24px; height:24px; margin:0 auto 10px; border:3px solid rgba(245,166,35,0.15); border-top-color:var(--amber); border-radius:50%; animation:spin 0.75s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* ── MAIN ── */
.forge-main { grid-area:main; display:flex; flex-direction:column; overflow:hidden; background:var(--bg); }
.forge-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px; padding:40px; color:var(--text-dim); }
.forge-empty-icon { font-size:48px; opacity:0.3; filter:grayscale(1); }
.forge-empty-title { font-family:var(--mono); font-size:1rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; }

/* ── WORKSPACE ── */
.forge-workspace { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.workspace-body { flex:1; display:grid; grid-template-columns:1fr 340px; overflow:hidden; }

/* Left: form panel */
.params-panel { padding:20px; overflow-y:auto; border-right:1px solid var(--border); }
.panel-label { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin-bottom:14px; display:flex; align-items:center; gap:6px; }
.panel-label::after { content:''; flex:1; height:1px; background:var(--border); }

.form-group { margin-bottom:16px; }
.form-label { display:block; font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
.form-input,.form-select,.form-textarea {
    width:100%; padding:9px 12px; background:var(--card); border:1px solid var(--border);
    border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.8rem;
    transition:border-color 0.15s; appearance:none;
}
.form-input:focus,.form-select:focus,.form-textarea:focus { outline:none; border-color:var(--amber); background:var(--card-hover); }
.form-textarea { resize:vertical; min-height:100px; line-height:1.5; }
.form-textarea.context-box { color:var(--text-dim); min-height:120px; font-size:0.78rem; }
.form-textarea.desc-box { min-height:180px; }
.form-select { cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:28px; }
.form-select:disabled { opacity:0.6; cursor:not-allowed; }

/* Right: log panel */
.log-panel { display:flex; flex-direction:column; overflow:hidden; background:var(--bg); }
.log-panel-header { padding:12px 16px; border-bottom:1px solid var(--border); background:var(--surface); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.log-panel-title { font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1.5px; }
.log-console { flex:1; overflow-y:auto; padding:14px; font-family:var(--mono); font-size:0.75rem; line-height:1.6; color:#4ade80; background:#050a05; white-space:pre-wrap; word-break:break-word; }
.log-console .ts { opacity:0.45; }

/* Generate bar */
.generate-bar { padding:14px 20px; border-top:1px solid var(--border); border-right:1px solid var(--border); background:var(--surface); display:flex; gap:8px; align-items:center; flex-shrink:0; }
.btn-generate { flex:1; padding:12px 20px; background:var(--amber); color:#000; border:none; border-radius:var(--radius); font-family:var(--mono); font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-generate:hover:not(:disabled) { filter:brightness(1.15); transform:translateY(-1px); }
.btn-generate:disabled { opacity:0.5; cursor:not-allowed; }
.btn-generate.stop { background:var(--red); color:#fff; }
.btn-forge-secondary { padding:10px 16px; background:transparent; color:var(--text-dim); border:1px solid var(--border); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.78rem; transition:all 0.15s; white-space:nowrap; }
.btn-forge-secondary:hover { border-color:var(--border-glow); color:var(--text); }

/* ── TOAST ── */
.forge-toast-container { position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.forge-toast { padding:10px 16px; border-radius:var(--radius); background:var(--card); border:1px solid var(--border); font-family:var(--mono); font-size:0.8rem; color:var(--text); box-shadow:0 4px 20px rgba(0,0,0,0.5); animation:toastIn 0.25s ease; pointer-events:all; cursor:pointer; max-width:320px; display:flex; align-items:center; gap:8px; }
.forge-toast.success { border-color:var(--green); }
.forge-toast.error   { border-color:var(--red); color:var(--red); }
.forge-toast.info    { border-color:var(--amber); }
.forge-toast.out     { animation:toastOut 0.25s ease forwards; }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
@keyframes toastOut { to{opacity:0;transform:translateY(10px)} }

@media (max-width:900px) {
    .forge-layout { grid-template-columns:1fr; grid-template-rows:52px 180px 1fr; grid-template-areas:"header""sidebar""main"; }
    .forge-sidebar { border-right:none; border-bottom:1px solid var(--border); }
    .workspace-body { grid-template-columns:1fr; grid-template-rows:1fr 200px; }
    .log-panel { border-top:1px solid var(--border); }
    .generate-bar { border-right:none; }
}
</style>
</head>
<body>
<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-rocket-takeoff"></i></div>
            Rapid Forge
        </div>
        <div class="forge-header-right">
            <div class="forge-header-stat" title="Pending jobs">
                <span class="val" id="statPending">—</span> pending
            </div>
            <button id="btnToggleLoop" class="btn-icon-sm" onclick="RapidForge.toggleLoop()" title="Start/Stop sequence">
                <i class="bi bi-play-fill"></i>
            </button>
            <a href="rapid_import_forge.php" class="btn-icon-sm" title="Import Scenarios"><i class="bi bi-download"></i></a>
            <a href="rapid_config_forge.php" class="btn-icon-sm" title="Config"><i class="bi bi-gear"></i></a>
            <a href="/dashboard.php" class="btn-icon-sm" title="Dashboard" style="text-decoration:none;"><i class="bi bi-house"></i></a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="sidebarSearch" placeholder="Search jobs…" autocomplete="off">
            </div>
        </div>
        <div class="sidebar-count" id="sidebarCount"></div>
        <div class="sidebar-list" id="sidebarList">
            <div class="sidebar-empty">
                <div class="sidebar-loading-spinner"></div>
                Loading queue…
            </div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main">

        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon"><i class="bi bi-rocket-takeoff"></i></div>
            <div class="forge-empty-title">Start Sequence or Select Job</div>
        </div>

        <div class="forge-workspace" id="forgeWorkspace" style="display:none;">
            <div class="workspace-body">

                <!-- ── FORM PANEL ── -->
                <div class="params-panel">
                    <form id="entityForm">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" id="rapid_ref" name="rapid_ref" value="">
                        <input type="hidden" id="rapid_id" value="">

                        <div class="panel-label">Generator</div>
                        <div class="form-group">
                            <label class="form-label">Active Generator <span style="color:var(--amber);">(auto-selected)</span></label>
                            <select id="descGeneratorSelect" class="form-select generator-select" disabled>
                                <option value="">-- Manual / Default --</option>
                                <?php foreach ($generators as $gen): ?>
                                    <option value="<?= $gen->getConfigId() ?>">
                                        <?= htmlspecialchars($gen->getTitle()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="panel-label" style="margin-top:20px;">Current Scenario</div>
                        <div class="form-group">
                            <label class="form-label">Context</label>
                            <textarea id="scenario_prompt" class="form-textarea context-box" readonly></textarea>
                        </div>

                        <div class="panel-label" style="margin-top:20px;">Generated Output</div>
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" id="name" name="name" class="form-input" placeholder="Waiting for generation…">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-textarea desc-box" placeholder="Waiting for generation…"></textarea>
                        </div>
                    </form>
                </div>

                <!-- ── LOG PANEL ── -->
                <div class="log-panel">
                    <div class="log-panel-header">
                        <span class="log-panel-title">Generation Log</span>
                        <button class="btn-icon-sm" style="width:28px;height:28px;font-size:12px;" onclick="RapidForge.clearLog()" title="Clear log"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="log-console" id="rapidLog">
                        <span class="ts">[system]</span> Ready to process active scenarios…
                    </div>
                </div>

            </div><!-- /workspace-body -->

            <!-- ── GENERATE BAR ── -->
            <div class="generate-bar">
                <button class="btn-generate" id="btnStartStop" onclick="RapidForge.toggleLoop()">
                    <i class="bi bi-play-fill"></i> START SEQUENCE
                </button>
                <button class="btn-forge-secondary" onclick="RapidForge.loadQueue()" title="Refresh queue">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>

        </div><!-- /forge-workspace -->
    </main>

</div><!-- /forge-layout -->

<div class="forge-toast-container" id="toastContainer"></div>

<script>
const RapidForge = (() => {
    'use strict';

    let _jobs         = [];
    let _filtered     = [];
    let _isRunning    = false;
    let _loopTimer    = null;
    let _searchTimeout= null;

    // ── helpers ──────────────────────────────────────────────────────────────

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
        const c   = document.getElementById('rapidLog');
        const now = new Date().toLocaleTimeString([], {hour12:false});
        c.innerHTML += `\n<span class="ts">[${now}]</span> ${msg}`;
        c.scrollTop = c.scrollHeight;
    }

    function clearLog() {
        document.getElementById('rapidLog').innerHTML = '<span class="ts">[system]</span> Log cleared.';
    }

    function esc(str) {
        if (str == null) return '';
        const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
    }

    // ── queue load ────────────────────────────────────────────────────────────

    async function loadQueue() {
        try {
            const res  = await fetch('?action=get_queue_list');
            const data = await res.json();
            if (!data.ok) return;

            _jobs     = data.rows || [];
            _filtered = _jobs;

            document.getElementById('statPending').textContent = data.pending;
            renderSidebar();

            // Show workspace once we have data
            if (_jobs.length > 0) {
                document.getElementById('forgeEmpty').style.display    = 'none';
                document.getElementById('forgeWorkspace').style.display = 'flex';
            }
        } catch(e) {
            toast('Failed to load queue', 'error');
        }
    }

    function renderSidebar() {
        const list    = document.getElementById('sidebarList');
        const countEl = document.getElementById('sidebarCount');
        const term    = document.getElementById('sidebarSearch').value.trim().toLowerCase();

        _filtered = term
            ? _jobs.filter(j =>
                (j.title         ||'').toLowerCase().includes(term) ||
                (j.reference_code||'').toLowerCase().includes(term) ||
                (j.category      ||'').toLowerCase().includes(term))
            : _jobs;

        countEl.textContent = term
            ? `${_filtered.length} of ${_jobs.length} jobs`
            : `${_jobs.length} jobs`;

        if (_filtered.length === 0) {
            list.innerHTML = `<div class="sidebar-empty"><i class="bi bi-search" style="font-size:2rem; display:block; margin-bottom:8px;"></i>No jobs match</div>`;
            return;
        }

        const currentId = parseInt(document.getElementById('rapid_id').value || '0');

        list.innerHTML = _filtered.map(j => {
            const isActive = j.id === currentId;
            const isDone   = parseInt(j.is_generated) === 1;
            return `
            <div class="job-card${isActive?' active':''}${isDone?' done':''}" data-id="${j.id}">
                <div class="job-card-indicator"></div>
                <div class="job-card-body">
                    <div class="job-card-title">${esc(j.reference_code)}: ${esc(j.title)}</div>
                    <div class="job-card-meta">
                        <span class="gen-badge model">${esc(j.category)}</span>
                        <span class="gen-badge ${isDone?'done':'pending'}">${isDone?'DONE':'PENDING'}</span>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    // ── loop control ─────────────────────────────────────────────────────────

    function toggleLoop() {
        _isRunning = !_isRunning;

        const btnHeader  = document.getElementById('btnToggleLoop');
        const btnMain    = document.getElementById('btnStartStop');

        if (_isRunning) {
            btnHeader.innerHTML = '<i class="bi bi-stop-fill"></i>';
            btnHeader.classList.add('running');
            btnMain.innerHTML   = '<i class="bi bi-stop-fill"></i> STOP SEQUENCE';
            btnMain.classList.add('stop');
            log('▶ Sequence started.');
            processNextJob();
        } else {
            btnHeader.innerHTML = '<i class="bi bi-play-fill"></i>';
            btnHeader.classList.remove('running');
            btnMain.innerHTML   = '<i class="bi bi-play-fill"></i> START SEQUENCE';
            btnMain.classList.remove('stop');
            clearTimeout(_loopTimer);
            log('⏸ Sequence paused.');
        }
    }

    // ── core job processing (logic preserved exactly from rapid_gen.php) ──────

    async function processNextJob() {
        if (!_isRunning) return;

        try {
            log('🔍 Fetching next scenario…');
            const res  = await fetch('?action=get_next_job');
            const data = await res.json();

            if (!data.ok || !data.job) {
                log('✨ <strong>Queue empty!</strong> All active scenarios processed.');
                document.getElementById('statPending').textContent = '0';
                toggleLoop();
                await loadQueue();
                return;
            }

            const job = data.job;
            log(`📂 Loaded: <strong>${job.reference_code}</strong>`);

            // Update form
            document.getElementById('rapid_ref').value   = job.reference_code;
            document.getElementById('rapid_id').value    = job.id;
            document.getElementById('scenario_prompt').value =
                `TITLE: ${job.title}\nCATEGORY: ${job.category}\n\nSCENARIO:\n${job.description_prompt}`;
            document.getElementById('name').value        = '';
            document.getElementById('description').value = '';

            // Highlight active job in sidebar
            document.querySelectorAll('.job-card').forEach(c => {
                c.classList.toggle('active', parseInt(c.dataset.id) === job.id);
            });

            // Show workspace
            document.getElementById('forgeEmpty').style.display    = 'none';
            document.getElementById('forgeWorkspace').style.display = 'flex';

            // Select generator
            const genSelect = document.getElementById('descGeneratorSelect');
            if (job.generator_config_id) {
                genSelect.value = job.generator_config_id;
                if (genSelect.selectedIndex === -1) {
                    log('⚠️ Assigned Gen ID not found. Using default.');
                    genSelect.selectedIndex = 1;
                }
            } else {
                genSelect.selectedIndex = 1;
            }

            const fullContext = document.getElementById('scenario_prompt').value;

            // Generate Description
            log('🧠 Generating Description…');
            const desc = await runAiGeneration(genSelect.value, fullContext, 'description');
            document.getElementById('description').value = desc;

            // Generate Name
            log('🏷️ Generating Name…');
            let nameGenId = '9bf6de291765e2ced28589de857a9f0b';
            for (let opt of genSelect.options) {
                if (opt.text.toLowerCase().includes('name gen')) nameGenId = opt.value;
            }
            const name = await runAiGeneration(nameGenId, desc, 'name');
            document.getElementById('name').value = `${job.reference_code}: ${name.replace(/^"/, '').replace(/"$/, '')}`;

            // Save
            log('💾 Saving Sketch…');
            await saveForm(job.id);

            // Refresh pending count
            await loadQueue();

            // Loop
            if (_isRunning) {
                log('⏳ Cooling down (2s)…');
                _loopTimer = setTimeout(processNextJob, 2000);
            }

        } catch(e) {
            log(`❌ Error: ${e.message}`);
            toast(e.message, 'error');
            toggleLoop();
        }
    }

    // ── AI generation (preserved exactly from rapid_gen.php) ─────────────────

    async function runAiGeneration(configId, contextText, fieldType) {
        const params = {
            config_id:    configId,
            entity_type:  'sketches',
            entity_name:  contextText,
            random_seed:  Math.floor(Math.random() * 1000000)
        };
        const res  = await fetch('/api/generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(params)
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'AI Gen Failed');
        if (json.data.description) return json.data.description;
        if (json.data.name)        return json.data.name;
        if (json.data.text)        return json.data.text;
        if (typeof json.data === 'string') return json.data;
        return JSON.stringify(json.data);
    }

    // ── save form (preserved exactly from rapid_gen.php) ─────────────────────

    async function saveForm(rapidId) {
        const form     = document.getElementById('entityForm');
        const formData = new FormData(form);

        const res  = await fetch('rapid_forge.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (!json.ok) throw new Error(json.errors ? json.errors.join(', ') : 'Save failed');

        const newSketchId = json.id;

        const markParams = new FormData();
        markParams.append('rapid_id',  rapidId);
        markParams.append('sketch_id', newSketchId);
        await fetch('?action=mark_complete', { method: 'POST', body: markParams });

        log(`✅ Created Sketch #${newSketchId}`);
    }

    // ── events ────────────────────────────────────────────────────────────────

    function bindEvents() {
        document.getElementById('sidebarList').addEventListener('click', e => {
            const card = e.target.closest('.job-card');
            if (!card) return;
            // Manual preview: just load the context into the form
            const id  = parseInt(card.dataset.id);
            const job = _jobs.find(j => j.id === id);
            if (!job) return;

            document.getElementById('rapid_ref').value = job.reference_code;
            document.getElementById('rapid_id').value  = job.id;
            document.getElementById('scenario_prompt').value =
                `TITLE: ${job.title}\nCATEGORY: ${job.category}\n\nSCENARIO:\n${(job.description_prompt||'')}`;
            document.getElementById('name').value        = '';
            document.getElementById('description').value = '';

            document.getElementById('forgeEmpty').style.display    = 'none';
            document.getElementById('forgeWorkspace').style.display = 'flex';

            renderSidebar();
        });

        document.getElementById('sidebarSearch').addEventListener('input', e => {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(() => renderSidebar(), 150);
        });
    }

    async function init() {
        bindEvents();
        await loadQueue();
    }

    return { init, toggleLoop, loadQueue, clearLog };
})();

document.addEventListener('DOMContentLoaded', () => RapidForge.init());
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
