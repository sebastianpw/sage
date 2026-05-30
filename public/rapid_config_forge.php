<?php
// public/rapid_config_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// RAPID CONFIG FORGE — Configuration for Rapid Showcase
// Forge design system port of rapid_config.php.
// Sidebar = category list with progress. Main panel = category editor.
// All AJAX logic preserved exactly.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$em     = $spw->getEntityManager();
$conn   = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) { header('Location: /login.php'); exit; }

// ── AJAX ACTION HANDLER (preserved exactly from rapid_config.php) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_category') {
        $category   = $_POST['category']     ?? '';
        $configId   = $_POST['config_id']    ?? '';
        $doReset    = !empty($_POST['reset_status']) && $_POST['reset_status'] === 'true';
        $isArchived = isset($_POST['is_archived']) ? ($_POST['is_archived'] === 'true' ? 1 : 0) : 0;

        if (empty($category) || empty($configId)) {
            echo json_encode(['ok' => false, 'error' => 'Missing category or config ID']);
            exit;
        }

        try {
            $sql = "UPDATE rapid_showcase SET generator_config_id = ?, is_archived = ?";
            if ($doReset) $sql .= ", is_generated = 0";
            $sql .= " WHERE category = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(1, $configId);
            $stmt->bindValue(2, $isArchived);
            $stmt->bindValue(3, $category);
            $result = $stmt->executeStatement();

            $msg = "Updated " . htmlspecialchars($category);
            if ($doReset)    $msg .= " (Reset)";
            if ($isArchived) $msg .= " (Archived)";

            echo json_encode(['ok' => true, 'message' => $msg, 'count' => $result]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_category_items') {
        $category = $_POST['category'] ?? '';
        try {
            $stmt  = $conn->prepare("SELECT reference_code, title, description_prompt, is_generated FROM rapid_showcase WHERE category = ? ORDER BY id ASC");
            $stmt->bindValue(1, $category);
            $items = $stmt->executeQuery()->fetchAllAssociative();
            echo json_encode(['ok' => true, 'items' => $items]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// ── FETCH DATA (preserved exactly from rapid_config.php) ─────────────────────
$genStmt = $conn->prepare("SELECT id, config_id, title FROM generator_config WHERE (user_id = ? OR is_public = 1) AND active = 1 ORDER BY title ASC");
$genStmt->bindValue(1, $userId);
$generators = $genStmt->executeQuery()->fetchAllAssociative();

$catSql = "
    SELECT category, COUNT(*) as total_rows,
    SUM(CASE WHEN is_generated = 1 THEN 1 ELSE 0 END) as generated_rows,
    (SELECT generator_config_id FROM rapid_showcase r2 WHERE r2.category = r1.category LIMIT 1) as current_config_id,
    (SELECT description_prompt FROM rapid_showcase r3 WHERE r3.category = r1.category ORDER BY id ASC LIMIT 1) as sample_prompt,
    MAX(is_archived) as is_archived
    FROM rapid_showcase r1
    GROUP BY category
    ORDER BY category ASC
";
$categories = $conn->executeQuery($catSql)->fetchAllAssociative();

$viewportScale = !empty($_GET['embed']) ? '1.0' : '0.9';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
<title>Rapid Config Forge</title>
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
.forge-layout { display:grid; grid-template-rows:52px 1fr; grid-template-columns:280px 1fr; grid-template-areas:"header header""sidebar main"; height:100vh; height:100dvh; overflow:hidden; }

/* ── HEADER ── */
.forge-header { grid-area:header; display:flex; align-items:center; justify-content:space-between; padding:0 20px; background:var(--surface); border-bottom:1px solid var(--border); z-index:100; }
.forge-logo { display:flex; align-items:center; gap:10px; font-family:var(--mono); font-size:0.85rem; font-weight:700; color:var(--amber); letter-spacing:2px; text-transform:uppercase; }
.forge-logo-icon { width:28px; height:28px; background:var(--amber-mid); border:1px solid var(--amber-glow); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-header-right { display:flex; align-items:center; gap:10px; }
.btn-icon-sm { width:36px; height:36px; border-radius:var(--radius); border:1px solid var(--border); background:var(--card); color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:15px; text-decoration:none; }
.btn-icon-sm:hover { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }
.forge-header-toggles { display:flex; gap:6px; align-items:center; }

/* Toggle pill */
.toggle-pill { display:flex; align-items:center; gap:6px; padding:4px 10px; border-radius:20px; border:1px solid var(--border); background:var(--card); font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); cursor:pointer; transition:all 0.15s; user-select:none; }
.toggle-pill:hover { border-color:var(--border-glow); color:var(--text); }
.toggle-pill.active { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }
.toggle-pill input { display:none; }

/* ── SIDEBAR ── */
.forge-sidebar { grid-area:sidebar; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
.sidebar-search { padding:12px; border-bottom:1px solid var(--border); flex-shrink:0; }
.sidebar-search-input { width:100%; padding:8px 10px 8px 32px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.8rem; transition:border-color 0.2s; }
.sidebar-search-input:focus { outline:none; border-color:var(--amber); }
.sidebar-search-wrap { position:relative; }
.sidebar-search-wrap::before { content:'⌕'; position:absolute; left:8px; top:50%; transform:translateY(-50%); color:var(--text-dim); font-size:16px; pointer-events:none; }
.sidebar-count { padding:4px 12px 8px; font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); flex-shrink:0; }
.sidebar-list { flex:1; overflow-y:auto; padding:8px; }

/* Category card */
.cat-card { padding:10px 12px; border-radius:var(--radius); border:1px solid transparent; cursor:pointer; transition:all 0.15s; margin-bottom:4px; position:relative; background:transparent; }
.cat-card:hover { background:var(--card); border-color:var(--border); }
.cat-card.active { background:var(--amber-dim); border-color:var(--amber); }
.cat-card.active .cat-card-name { color:var(--amber); }
.cat-card-indicator { position:absolute; left:0; top:50%; transform:translateY(-50%); width:2px; height:0; background:var(--amber); border-radius:0 2px 2px 0; transition:height 0.2s; }
.cat-card.active .cat-card-indicator { height:60%; }
.cat-card-name { font-family:var(--sans); font-weight:600; font-size:0.82rem; color:var(--text-bright); margin-bottom:6px; transition:color 0.15s; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cat-card.archived { opacity:0.45; }

/* Progress bar */
.progress-wrap { height:4px; background:var(--border); border-radius:2px; overflow:hidden; margin-bottom:5px; }
.progress-bar { height:100%; border-radius:2px; background:var(--green); transition:width 0.3s; }
.progress-bar.amber { background:var(--amber); }
.progress-bar.red   { background:var(--red); }

.cat-card-meta { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
.gen-badge { font-family:var(--mono); font-size:0.63rem; padding:1px 5px; border-radius:3px; border:1px solid; }
.gen-badge.model    { border-color:var(--border-glow); color:var(--text-dim); background:var(--card); }
.gen-badge.done     { border-color:var(--green); color:var(--green); background:var(--green-dim); }
.gen-badge.partial  { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }
.gen-badge.archived { border-color:var(--border); color:var(--text-dim); }

.sidebar-empty { text-align:center; padding:40px 20px; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem; }

/* ── MAIN ── */
.forge-main { grid-area:main; display:flex; flex-direction:column; overflow:hidden; background:var(--bg); }
.forge-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px; padding:40px; color:var(--text-dim); }
.forge-empty-icon { font-size:48px; opacity:0.3; filter:grayscale(1); }
.forge-empty-title { font-family:var(--mono); font-size:1rem; text-transform:uppercase; letter-spacing:2px; }

/* ── WORKSPACE ── */
.forge-workspace { flex:1; display:flex; flex-direction:column; overflow:hidden; display:none; }
.workspace-body { flex:1; display:flex; flex-direction:column; overflow-y:auto; padding:24px; gap:20px; }

.panel-label { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin-bottom:14px; display:flex; align-items:center; gap:6px; }
.panel-label::after { content:''; flex:1; height:1px; background:var(--border); }

/* Info cards */
.info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; margin-bottom:4px; }
.info-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:12px; }
.info-card-label { font-family:var(--mono); font-size:0.63rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.info-card-value { font-family:var(--mono); font-size:1rem; font-weight:700; }

/* Sample prompt block */
.context-block { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.context-block-header { padding:8px 14px; background:var(--surface); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.context-block-label { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.context-pre { padding:14px; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); white-space:pre-wrap; word-break:break-word; line-height:1.6; max-height:140px; overflow-y:auto; }

/* Editor form */
.editor-block { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.editor-block-header { padding:10px 16px; background:var(--surface); border-bottom:1px solid var(--border); font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.editor-block-body { padding:16px; display:flex; flex-direction:column; gap:14px; }

.form-group { display:flex; flex-direction:column; gap:6px; }
.form-label { font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.form-select { width:100%; padding:9px 28px 9px 12px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.8rem; transition:border-color 0.15s; appearance:none; cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; }
.form-select:focus { outline:none; border-color:var(--amber); }

.checkbox-row { display:flex; gap:16px; flex-wrap:wrap; }
.checkbox-label { display:flex; align-items:center; gap:8px; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); cursor:pointer; user-select:none; }
.checkbox-label input { accent-color:var(--amber); cursor:pointer; width:14px; height:14px; }

/* Generate bar */
.generate-bar { padding:14px 24px; border-top:1px solid var(--border); background:var(--surface); display:flex; gap:8px; align-items:center; flex-shrink:0; }
.btn-generate { flex:1; padding:12px 20px; background:var(--amber); color:#000; border:none; border-radius:var(--radius); font-family:var(--mono); font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-generate:hover:not(:disabled) { filter:brightness(1.15); }
.btn-generate:disabled { opacity:0.5; cursor:not-allowed; }
.btn-forge-secondary { padding:10px 16px; background:transparent; color:var(--text-dim); border:1px solid var(--border); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.78rem; transition:all 0.15s; }
.btn-forge-secondary:hover { border-color:var(--border-glow); color:var(--text); }

/* Items panel inside workspace */
.items-list { display:flex; flex-direction:column; gap:4px; }
.item-row { padding:10px 14px; border-radius:var(--radius); border:1px solid var(--border); background:var(--card); transition:background 0.15s; }
.item-row.generated { border-color:rgba(34,211,160,0.2); background:rgba(34,211,160,0.03); }
.item-row-top { display:flex; align-items:center; gap:8px; margin-bottom:4px; }
.item-ref { font-family:var(--mono); font-size:0.72rem; color:var(--amber); font-weight:700; }
.item-title { font-family:var(--sans); font-weight:600; font-size:0.85rem; color:var(--text-bright); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.item-check { color:var(--green); font-size:0.9rem; }
.item-desc { font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.4; }

/* ── MODAL (context viewer) ── */
.forge-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(3px); z-index:10000; display:none; align-items:center; justify-content:center; padding:16px; }
.forge-modal-overlay.open { display:flex; }
.forge-modal { background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:100%; max-width:600px; max-height:80vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.6); animation:modalIn 0.2s ease; }
.forge-modal-header { padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.forge-modal-title { font-family:var(--mono); font-size:0.8rem; font-weight:700; color:var(--amber); text-transform:uppercase; letter-spacing:1.5px; }
.forge-modal-close { width:28px; height:28px; border-radius:4px; border:1px solid var(--border); background:transparent; color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-modal-close:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); }
.forge-modal-body { padding:20px; overflow-y:auto; flex:1; }
.context-viewer { white-space:pre-wrap; font-family:var(--mono); font-size:0.8rem; color:var(--text); line-height:1.6; }

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
    .forge-layout { grid-template-columns:1fr; grid-template-rows:52px 200px 1fr; grid-template-areas:"header""sidebar""main"; }
    .forge-sidebar { border-right:none; border-bottom:1px solid var(--border); }
    .info-grid { grid-template-columns:repeat(2,1fr); }
}
</style>
</head>
<body>

<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-gear"></i></div>
            Config Forge
        </div>
        <div class="forge-header-right">
            <div class="forge-header-toggles">
                <label class="toggle-pill" id="pillArchived">
                    <input type="checkbox" id="showArchivedToggle" onchange="ConfigForge.toggleView()">
                    <i class="bi bi-archive"></i> Archived
                </label>
                <label class="toggle-pill" id="pillCompleted">
                    <input type="checkbox" id="hideCompletedToggle" onchange="ConfigForge.toggleView()">
                    <i class="bi bi-check-circle"></i> Hide Done
                </label>
            </div>
            <a href="rapid_forge.php"        class="btn-icon-sm" title="Generator"><i class="bi bi-rocket-takeoff"></i></a>
            <a href="rapid_import_forge.php"  class="btn-icon-sm" title="Import"><i class="bi bi-download"></i></a>
            <a href="/dashboard.php"          class="btn-icon-sm" title="Dashboard" style="text-decoration:none;"><i class="bi bi-house"></i></a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="sidebarSearch" placeholder="Search categories…" autocomplete="off">
            </div>
        </div>
        <div class="sidebar-count" id="sidebarCount"></div>
        <div class="sidebar-list" id="sidebarList">
            <!-- populated by JS from PHP data -->
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main">

        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon"><i class="bi bi-gear"></i></div>
            <div class="forge-empty-title">Select a Category</div>
        </div>

        <div class="forge-workspace" id="forgeWorkspace">
            <div class="workspace-body" id="workspaceBody">
                <!-- populated by JS -->
            </div>
            <div class="generate-bar">
                <button class="btn-generate" id="btnSave" onclick="ConfigForge.saveCategory()">
                    <i class="bi bi-floppy"></i> SAVE CATEGORY
                </button>
                <button class="btn-forge-secondary" onclick="ConfigForge.viewItems(ConfigForge.currentCategory())">
                    <i class="bi bi-list-ul"></i> Items
                </button>
            </div>
        </div>

    </main>
</div>

<!-- ── CONTEXT VIEWER MODAL ── -->
<div class="forge-modal-overlay" id="contextModal">
    <div class="forge-modal">
        <div class="forge-modal-header">
            <div class="forge-modal-title" id="modalTitle">Context Preview</div>
            <button class="forge-modal-close" onclick="ConfigForge.closeModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body" id="modalBody"></div>
    </div>
</div>

<div class="forge-toast-container" id="toastContainer"></div>

<!-- PHP data injected for JS -->
<script>
const _PHP_CATEGORIES = <?= json_encode($categories) ?>;
const _PHP_GENERATORS  = <?= json_encode($generators) ?>;
</script>

<script>
const ConfigForge = (() => {
    'use strict';

    let _cats         = _PHP_CATEGORIES;
    let _filtered     = [];
    let _current      = null;
    let _searchTimeout= null;

    // ── visibility state (preserved from rapid_config.php) ───────────────────

    function applyVisibility() {
        const showArchived  = document.getElementById('showArchivedToggle').checked;
        const hideCompleted = document.getElementById('hideCompletedToggle').checked;

        document.getElementById('pillArchived').classList.toggle('active', showArchived);
        document.getElementById('pillCompleted').classList.toggle('active', hideCompleted);

        localStorage.setItem('rapid_show_archived',  showArchived);
        localStorage.setItem('rapid_hide_completed', hideCompleted);

        renderSidebar();
    }

    function toggleView() {
        applyVisibility();
    }

    function currentCategory() {
        return _current ? _current.category : '';
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    function toast(msg, type = 'info', duration = 3000) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        const icons = { success:'✓', error:'✕', info:'◆' };
        el.innerHTML = `<span style="font-size:12px;">${icons[type]||'◆'}</span> ${msg}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        function dismiss(e) { e.classList.add('out'); setTimeout(()=>e.remove(),300); }
        setTimeout(()=>dismiss(el), duration);
    }

    function esc(str) {
        if (str == null) return '';
        const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
    }

    // ── sidebar ───────────────────────────────────────────────────────────────

    function renderSidebar() {
        const list     = document.getElementById('sidebarList');
        const countEl  = document.getElementById('sidebarCount');
        const term     = (document.getElementById('sidebarSearch').value || '').trim().toLowerCase();
        const showArc  = document.getElementById('showArchivedToggle').checked;
        const hideDone = document.getElementById('hideCompletedToggle').checked;

        // Apply all filters (mirrors rapid_config.php CSS logic, now in JS)
        _filtered = _cats.filter(cat => {
            const percent  = cat.total_rows > 0 ? Math.round((cat.generated_rows / cat.total_rows) * 100) : 0;
            const archived = parseInt(cat.is_archived) === 1;
            const complete = percent === 100;

            if (!showArc  && archived)  return false;
            if (showArc   && !archived) return false;
            if (hideDone  && complete)  return false;

            if (term) {
                return (cat.category || '').toLowerCase().includes(term);
            }
            return true;
        });

        countEl.textContent = `${_filtered.length} of ${_cats.length} categories`;

        if (_filtered.length === 0) {
            list.innerHTML = `<div class="sidebar-empty"><i class="bi bi-search" style="font-size:2rem; display:block; margin-bottom:8px;"></i>No categories match</div>`;
            return;
        }

        list.innerHTML = _filtered.map(cat => {
            const percent   = cat.total_rows > 0 ? Math.round((cat.generated_rows / cat.total_rows) * 100) : 0;
            const archived  = parseInt(cat.is_archived) === 1;
            const isActive  = _current && _current.category === cat.category;
            const barClass  = percent === 100 ? '' : (percent >= 50 ? 'amber' : 'red');
            const badge     = percent === 100
                ? `<span class="gen-badge done">DONE</span>`
                : `<span class="gen-badge partial">${percent}%</span>`;
            const arcBadge  = archived ? `<span class="gen-badge archived">ARC</span>` : '';

            return `
            <div class="cat-card${isActive?' active':''}${archived?' archived':''}" data-cat="${esc(cat.category)}">
                <div class="cat-card-indicator"></div>
                <div class="cat-card-name">${esc(cat.category)}</div>
                <div class="progress-wrap">
                    <div class="progress-bar ${barClass}" style="width:${percent}%"></div>
                </div>
                <div class="cat-card-meta">
                    <span class="gen-badge model">${cat.total_rows} items</span>
                    ${badge}${arcBadge}
                </div>
            </div>`;
        }).join('');
    }

    // ── select category ───────────────────────────────────────────────────────

    function selectCategory(catName) {
        _current = _cats.find(c => c.category === catName) || null;
        if (!_current) return;

        renderSidebar();

        document.getElementById('forgeEmpty').style.display     = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';

        renderWorkspace();
    }

    function renderWorkspace() {
        const cat     = _current;
        const percent = cat.total_rows > 0 ? Math.round((cat.generated_rows / cat.total_rows) * 100) : 0;
        const barClass= percent === 100 ? '' : (percent >= 50 ? 'amber' : 'red');

        // Build generator options
        const genOptions = _PHP_GENERATORS.map(g =>
            `<option value="${esc(g.config_id)}" ${cat.current_config_id === g.config_id ? 'selected' : ''}>
                ${esc(g.title)}
            </option>`
        ).join('');

        const sampleHtml = cat.sample_prompt
            ? `<div class="panel-label">Sample Context</div>
               <div class="context-block">
                   <div class="context-block-header">
                       <span class="context-block-label">First item prompt</span>
                       <button class="btn-icon-sm" style="width:26px;height:26px;font-size:11px;" onclick="ConfigForge.viewContext(${JSON.stringify(cat.sample_prompt)})" title="Full preview"><i class="bi bi-arrows-fullscreen"></i></button>
                   </div>
                   <pre class="context-pre">${esc(cat.sample_prompt)}</pre>
               </div>`
            : '';

        document.getElementById('workspaceBody').innerHTML = `
            <div>
                <div class="panel-label">${esc(cat.category)}</div>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-card-label">Total</div>
                        <div class="info-card-value">${cat.total_rows}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-label">Generated</div>
                        <div class="info-card-value" style="color:var(--green);">${cat.generated_rows}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-label">Remaining</div>
                        <div class="info-card-value" style="color:var(--amber);">${cat.total_rows - cat.generated_rows}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-label">Progress</div>
                        <div class="info-card-value" style="color:${percent===100?'var(--green)':'var(--text-bright)'};">${percent}%</div>
                    </div>
                </div>
                <div class="progress-wrap" style="height:6px; margin-bottom:20px;">
                    <div class="progress-bar ${barClass}" style="width:${percent}%"></div>
                </div>
            </div>

            ${sampleHtml}

            <div>
                <div class="panel-label">Generator Assignment</div>
                <div class="editor-block">
                    <div class="editor-block-header">Category Settings</div>
                    <div class="editor-block-body">
                        <div class="form-group">
                            <label class="form-label">Default Generator</label>
                            <select class="form-select" id="editorGenSelect">
                                <option value="">-- Manual Selection --</option>
                                ${genOptions}
                            </select>
                        </div>
                        <div class="checkbox-row">
                            <label class="checkbox-label">
                                <input type="checkbox" id="editorResetChk">
                                <span>Reset Progress</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="editorArchiveChk" ${parseInt(cat.is_archived)===1?'checked':''}>
                                <span>Archive Category</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ── save (preserved exactly from rapid_config.php) ────────────────────────

    async function saveCategory() {
        if (!_current) return;

        const configId = document.getElementById('editorGenSelect').value;
        const doReset  = document.getElementById('editorResetChk').checked;
        const doArc    = document.getElementById('editorArchiveChk').checked;

        if (!configId) { toast('Please select a generator first.', 'error'); return; }

        const btn  = document.getElementById('btnSave');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
        btn.disabled  = true;

        try {
            const formData = new FormData();
            formData.append('action',       'update_category');
            formData.append('category',     _current.category);
            formData.append('config_id',    configId);
            formData.append('reset_status', doReset);
            formData.append('is_archived',  doArc);

            const res  = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.ok) {
                toast(data.message, 'success');

                // Update local data
                const idx = _cats.findIndex(c => c.category === _current.category);
                if (idx !== -1) {
                    _cats[idx].current_config_id = configId;
                    _cats[idx].is_archived       = doArc ? 1 : 0;
                    if (doReset) {
                        _cats[idx].generated_rows = 0;
                        _current = _cats[idx];
                        setTimeout(() => location.reload(), 1000); // reload for accurate counts on reset
                    } else {
                        _current = _cats[idx];
                    }
                }

                document.getElementById('editorResetChk').checked = false;
                renderSidebar();
                renderWorkspace();
            } else {
                toast('Error: ' + data.error, 'error');
            }
        } catch(e) {
            toast('Request failed: ' + e.message, 'error');
        } finally {
            btn.innerHTML = orig;
            btn.disabled  = false;
        }
    }

    // ── items modal ───────────────────────────────────────────────────────────

    async function viewItems(catName) {
        if (!catName) return;
        openModal('Items — ' + catName, '<div style="padding:20px; text-align:center; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem;">Loading…</div>');

        const formData = new FormData();
        formData.append('action',   'get_category_items');
        formData.append('category', catName);

        try {
            const res  = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.ok && data.items.length > 0) {
                const html = data.items.map(item => {
                    const check    = item.is_generated == 1 ? '<span class="item-check"><i class="bi bi-check-lg"></i></span>' : '';
                    const genClass = item.is_generated == 1 ? 'generated' : '';
                    return `
                    <div class="item-row ${genClass}">
                        <div class="item-row-top">
                            <span class="item-ref">${esc(item.reference_code)}</span>
                            <span class="item-title">${esc(item.title)}</span>
                            ${check}
                        </div>
                        <div class="item-desc">${esc(item.description_prompt)}</div>
                    </div>`;
                }).join('');
                document.getElementById('modalBody').innerHTML = `<div class="items-list">${html}</div>`;
            } else {
                document.getElementById('modalBody').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-dim);">No items found.</div>';
            }
        } catch(e) {
            document.getElementById('modalBody').innerHTML = `<div style="padding:20px; text-align:center; color:var(--red);">Error: ${esc(e.message)}</div>`;
        }
    }

    function viewContext(text) {
        openModal('Context Preview', `<div class="context-viewer">${esc(text)}</div>`);
    }

    function openModal(title, bodyHtml) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalBody').innerHTML    = bodyHtml;
        document.getElementById('contextModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('contextModal').classList.remove('open');
    }

    // ── events & init ─────────────────────────────────────────────────────────

    function bindEvents() {
        document.getElementById('sidebarList').addEventListener('click', e => {
            const card = e.target.closest('.cat-card');
            if (card) selectCategory(card.dataset.cat);
        });

        document.getElementById('sidebarSearch').addEventListener('input', () => {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(() => renderSidebar(), 150);
        });

        document.getElementById('contextModal').addEventListener('click', e => {
            if (e.target === document.getElementById('contextModal')) closeModal();
        });
    }

    function init() {
        // Restore toggle state (preserved from rapid_config.php)
        const showArc  = localStorage.getItem('rapid_show_archived')  === 'true';
        const hideDone = localStorage.getItem('rapid_hide_completed') === 'true';
        document.getElementById('showArchivedToggle').checked  = showArc;
        document.getElementById('hideCompletedToggle').checked = hideDone;
        document.getElementById('pillArchived').classList.toggle('active', showArc);
        document.getElementById('pillCompleted').classList.toggle('active', hideDone);

        bindEvents();
        renderSidebar();
    }

    return { init, toggleView, saveCategory, viewItems, viewContext, closeModal, currentCategory };
})();

document.addEventListener('DOMContentLoaded', () => ConfigForge.init());
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
<?php echo $eruda ?? ''; ?>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
