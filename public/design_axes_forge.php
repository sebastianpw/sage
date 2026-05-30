<?php
// public/design_axes_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// DESIGN AXES FORGE
// Forge design system port of view_design_axes_admin.php.
// Sidebar = axes list with group filter + search.
// Main panel = edit/create form for the selected axis.
// All PHP logic, API calls, save/delete preserved exactly.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Design Axes Forge";

// ── Column existence checks (preserved exactly from view_design_axes_admin.php) ─
$columnExists         = false;
$categoryColumnExists = false;
try {
    $checkCol    = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'axis_group'");
    $columnExists = $checkCol->rowCount() > 0;
    $checkCatCol  = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'category'");
    $categoryColumnExists = $checkCatCol->rowCount() > 0;
} catch (Exception $e) {}

// ── Fetch axes (preserved exactly) ───────────────────────────────────────────
try {
    $selectFields = "id, axis_name, pole_left, pole_right, notes, created_at";
    if ($columnExists)         $selectFields .= ", COALESCE(axis_group, 'default') as axis_group";
    if ($categoryColumnExists) $selectFields .= ", COALESCE(category, '') as category";
    $orderBy = $columnExists ? "ORDER BY axis_group ASC, id ASC" : "ORDER BY id ASC";
    $stmt    = $pdo->query("SELECT $selectFields FROM design_axes $orderBy");
    $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

// ── Fetch groups + categories for dropdowns (preserved exactly) ───────────────
$availableGroups      = [];
$availableCategories  = [];
if ($columnExists) {
    try {
        $groupsStmt    = $pdo->query("SELECT DISTINCT COALESCE(axis_group, 'default') as axis_group FROM design_axes ORDER BY axis_group ASC");
        $availableGroups = $groupsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}
if ($categoryColumnExists) {
    try {
        $catStmt = $pdo->query("SELECT DISTINCT category FROM design_axes WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        $availableCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}

$viewportScale = !empty($_GET['embed']) ? '1.0' : '0.9';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Design Axes Forge</title>
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
::-webkit-scrollbar-thumb:hover { background:var(--text-dim); }

/* ── LAYOUT ── */
.forge-layout { display:grid; grid-template-rows:52px 1fr; grid-template-columns:300px 1fr; grid-template-areas:"header header""sidebar main"; height:100vh; height:100dvh; overflow:hidden; }

/* ── HEADER ── */
.forge-header { grid-area:header; display:flex; align-items:center; justify-content:space-between; padding:0 20px; background:var(--surface); border-bottom:1px solid var(--border); z-index:100; }
.forge-logo { display:flex; align-items:center; gap:10px; font-family:var(--mono); font-size:0.85rem; font-weight:700; color:var(--amber); letter-spacing:2px; text-transform:uppercase; }
.forge-logo-icon { width:28px; height:28px; background:var(--amber-mid); border:1px solid var(--amber-glow); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-header-right { display:flex; align-items:center; gap:8px; }
.btn-icon-sm { width:36px; height:36px; border-radius:var(--radius); border:1px solid var(--border); background:var(--card); color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:15px; text-decoration:none; }
.btn-icon-sm:hover { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }
.forge-header-stat { display:flex; align-items:center; font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); padding:4px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); }
.forge-header-stat .val { color:var(--amber); margin-right:4px; }

/* ── SIDEBAR ── */
.forge-sidebar { grid-area:sidebar; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
.sidebar-search { padding:12px; border-bottom:1px solid var(--border); flex-shrink:0; }
.sidebar-search-input { width:100%; padding:8px 10px 8px 32px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.8rem; transition:border-color 0.2s; }
.sidebar-search-input:focus { outline:none; border-color:var(--amber); }
.sidebar-search-wrap { position:relative; }
.sidebar-search-wrap::before { content:'⌕'; position:absolute; left:8px; top:50%; transform:translateY(-50%); color:var(--text-dim); font-size:16px; pointer-events:none; }

/* Group filter */
.sidebar-filter { padding:8px 12px; border-bottom:1px solid var(--border); flex-shrink:0; }
.filter-select-full { width:100%; padding:6px 24px 6px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.75rem; cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; transition:border-color 0.15s; }
.filter-select-full:focus { outline:none; border-color:var(--amber); }

.sidebar-count { padding:4px 12px 8px; font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); flex-shrink:0; }
.sidebar-list { flex:1; overflow-y:auto; padding:8px; }
.sidebar-empty { text-align:center; padding:40px 20px; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem; }

/* Axis card */
.axis-card { padding:8px 10px 8px 12px; border-radius:var(--radius); border:1px solid transparent; cursor:pointer; transition:all 0.15s; margin-bottom:3px; position:relative; background:transparent; display:flex; align-items:center; gap:8px; }
.axis-card:hover { background:var(--card); border-color:var(--border); }
.axis-card.active { background:var(--amber-dim); border-color:var(--amber); }
.axis-card.active .axis-card-title { color:var(--amber); }
.axis-card-indicator { position:absolute; left:0; top:50%; transform:translateY(-50%); width:2px; height:0; background:var(--amber); border-radius:0 2px 2px 0; transition:height 0.2s; }
.axis-card.active .axis-card-indicator { height:60%; }
.axis-card-body { flex:1; min-width:0; }
.axis-card-title { font-family:var(--sans); font-weight:600; font-size:0.82rem; color:var(--text-bright); line-height:1.3; margin-bottom:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.axis-card-meta { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
.axis-card-delete { flex-shrink:0; width:26px; height:26px; border-radius:var(--radius); border:1px solid transparent; background:transparent; color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:12px; cursor:pointer; transition:all 0.15s; opacity:0; }
.axis-card:hover .axis-card-delete { opacity:1; }
.axis-card-delete:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); opacity:1; }

.gen-badge { font-family:var(--mono); font-size:0.63rem; padding:1px 5px; border-radius:3px; border:1px solid; }
.gen-badge.model { border-color:var(--border-glow); color:var(--text-dim); background:var(--card); }
.gen-badge.group { border-color:var(--blue); color:var(--blue); background:var(--blue-dim); }
.gen-badge.cat   { border-color:var(--green); color:var(--green); background:var(--green-dim); }

/* ── MAIN ── */
.forge-main { grid-area:main; display:flex; flex-direction:column; overflow:hidden; background:var(--bg); }
.forge-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px; padding:40px; color:var(--text-dim); }
.forge-empty-icon { font-size:48px; opacity:0.3; filter:grayscale(1); }
.forge-empty-title { font-family:var(--mono); font-size:1rem; text-transform:uppercase; letter-spacing:2px; }
.forge-empty-sub { font-family:var(--mono); font-size:0.75rem; opacity:0.7; }

/* ── WORKSPACE ── */
.forge-workspace { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.workspace-body { flex:1; overflow-y:auto; padding:24px; max-width:700px; display:flex; flex-direction:column; gap:20px; }

.panel-label { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin-bottom:14px; display:flex; align-items:center; gap:6px; }
.panel-label::after { content:''; flex:1; height:1px; background:var(--border); }

.editor-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.editor-card-header { padding:12px 16px; background:var(--surface); border-bottom:1px solid var(--border); font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.editor-card-body { padding:20px; display:flex; flex-direction:column; gap:14px; }

.form-group { display:flex; flex-direction:column; gap:5px; }
.form-label { font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.form-input,.form-textarea { width:100%; padding:9px 12px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.8rem; transition:border-color 0.15s; }
.form-input:focus,.form-textarea:focus { outline:none; border-color:var(--amber); background:var(--card-hover); }
.form-textarea { resize:vertical; min-height:80px; line-height:1.5; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

/* Poles preview */
.poles-preview { display:flex; align-items:center; gap:8px; padding:10px 14px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); }
.poles-preview-left  { font-family:var(--mono); font-size:0.78rem; color:var(--text-dim); flex:1; }
.poles-preview-arrow { color:var(--amber); font-family:var(--mono); font-size:0.8rem; flex-shrink:0; }
.poles-preview-right { font-family:var(--mono); font-size:0.78rem; color:var(--text-dim); flex:1; text-align:right; }

/* Info card for existing axis */
.info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:4px; }
.info-card { background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:10px 12px; }
.info-card-label { font-family:var(--mono); font-size:0.63rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.info-card-value { font-family:var(--mono); font-size:0.8rem; color:var(--text-bright); word-break:break-word; }

/* Generate bar */
.generate-bar { padding:14px 24px; border-top:1px solid var(--border); background:var(--surface); display:flex; gap:8px; align-items:center; flex-shrink:0; }
.btn-generate { flex:1; padding:12px 20px; background:var(--amber); color:#000; border:none; border-radius:var(--radius); font-family:var(--mono); font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-generate:hover:not(:disabled) { filter:brightness(1.15); }
.btn-generate:disabled { opacity:0.5; cursor:not-allowed; }
.btn-forge-secondary { padding:10px 16px; background:transparent; color:var(--text-dim); border:1px solid var(--border); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.78rem; transition:all 0.15s; }
.btn-forge-secondary:hover { border-color:var(--border-glow); color:var(--text); }
.btn-forge-danger { padding:10px 16px; background:var(--red-dim); color:var(--red); border:1px solid var(--red); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.78rem; transition:all 0.15s; }
.btn-forge-danger:hover { background:var(--red); color:#fff; }

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
    .form-grid-2 { grid-template-columns:1fr; }
    .workspace-body { max-width:100%; }
}
</style>
</head>
<body>
<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-rulers"></i></div>
            Design Axes
        </div>
        <div class="forge-header-right">
            <div class="forge-header-stat" title="Total axes">
                <span class="val" id="statTotal"><?= count($rows) ?></span> axes
            </div>
            <button class="btn-icon-sm" onclick="AxesForge.newAxis()" title="Create new axis"><i class="bi bi-plus-lg"></i></button>
            <a href="style_profiles_forge.php" class="btn-icon-sm" title="Style Profiles"><i class="bi bi-sliders"></i></a>
            <a href="/dashboard.php" class="btn-icon-sm" title="Dashboard" style="text-decoration:none;"><i class="bi bi-house"></i></a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="sidebarSearch" placeholder="Search axes…" autocomplete="off">
            </div>
        </div>
        <?php if ($columnExists && count($availableGroups) > 0): ?>
        <div class="sidebar-filter">
            <select id="groupFilter" class="filter-select-full">
                <option value="">All Groups</option>
                <?php foreach ($availableGroups as $grp): ?>
                    <option value="<?= htmlspecialchars($grp) ?>"><?= htmlspecialchars(ucfirst($grp)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="sidebar-count" id="sidebarCount"></div>
        <div class="sidebar-list" id="sidebarList">
            <!-- populated by JS from PHP data -->
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main">

        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon"><i class="bi bi-rulers"></i></div>
            <div class="forge-empty-title">Select an Axis</div>
            <div class="forge-empty-sub">Choose from the sidebar or create a new axis</div>
        </div>

        <div class="forge-workspace" id="forgeWorkspace" style="display:none;">
            <div class="workspace-body">

                <div>
                    <div class="panel-label" id="wsLabel">Axis Editor</div>
                    <div class="editor-card">
                        <div class="editor-card-header" id="wsCardHeader">Create New Axis</div>
                        <div class="editor-card-body">
                            <input type="hidden" id="axisId" value="">

                            <div class="form-group">
                                <label class="form-label">Axis Name <span style="color:var(--red);">*</span></label>
                                <input type="text" id="axisName" class="form-input" placeholder="e.g. Brightness" required>
                            </div>

                            <div class="form-grid-2">
                                <?php if ($columnExists): ?>
                                <div class="form-group">
                                    <label class="form-label">Axis Group <span style="color:var(--red);">*</span></label>
                                    <input type="text" id="axisGroup" class="form-input" list="axisGroupList" value="default" required>
                                    <datalist id="axisGroupList">
                                        <?php foreach ($availableGroups as $grp): ?>
                                            <option value="<?= htmlspecialchars($grp) ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <?php endif; ?>
                                <?php if ($categoryColumnExists): ?>
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <input type="text" id="axisCategory" class="form-input" list="axisCategoryList">
                                    <datalist id="axisCategoryList">
                                        <?php foreach ($availableCategories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Left Pole <span style="color:var(--red);">*</span></label>
                                    <input type="text" id="poleLeft" class="form-input" placeholder="e.g. Dark" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Right Pole <span style="color:var(--red);">*</span></label>
                                    <input type="text" id="poleRight" class="form-input" placeholder="e.g. Bright" required>
                                </div>
                            </div>

                            <!-- Live poles preview -->
                            <div class="poles-preview" id="polesPreview">
                                <span class="poles-preview-left" id="previewLeft">Left pole</span>
                                <span class="poles-preview-arrow">←───────────────→</span>
                                <span class="poles-preview-right" id="previewRight">Right pole</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Notes</label>
                                <textarea id="axisNotes" class="form-textarea"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info block (shown when editing existing) -->
                <div id="axisInfoBlock" style="display:none;">
                    <div class="panel-label">Axis Info</div>
                    <div class="info-grid" id="axisInfoGrid"></div>
                </div>

            </div><!-- /workspace-body -->

            <div class="generate-bar">
                <button class="btn-generate" id="btnSave" onclick="AxesForge.saveAxis()">
                    <i class="bi bi-floppy"></i> SAVE AXIS
                </button>
                <button class="btn-forge-secondary" onclick="AxesForge.newAxis()">
                    <i class="bi bi-plus-lg"></i> New
                </button>
                <button class="btn-forge-danger" id="btnDelete" onclick="AxesForge.deleteAxis()" style="display:none;">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div><!-- /forge-workspace -->

    </main>
</div>

<div class="forge-toast-container" id="toastContainer"></div>

<!-- PHP data for JS -->
<script>
const _PHP_AXES         = <?= json_encode($rows) ?>;
const _columnExists     = <?= $columnExists ? 'true' : 'false' ?>;
const _catColumnExists  = <?= $categoryColumnExists ? 'true' : 'false' ?>;
</script>

<script>
const AxesForge = (() => {
    'use strict';

    let _axes         = _PHP_AXES;
    let _filtered     = [];
    let _currentId    = null;
    let _searchTimeout= null;
    let _groupFilter  = '';

    // ── helpers ───────────────────────────────────────────────────────────────

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

    function showToast(msg, type) {
        const mapType = (type === 'danger' || type === 'error') ? 'error' : (type === 'warning' ? 'info' : (type || 'info'));
        toast(msg, mapType);
    }

    function esc(str) {
        if (str == null) return '';
        const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
    }

    // ── sidebar ───────────────────────────────────────────────────────────────

    function renderSidebar() {
        const list    = document.getElementById('sidebarList');
        const countEl = document.getElementById('sidebarCount');
        const term    = (document.getElementById('sidebarSearch').value || '').trim().toLowerCase();

        _filtered = _axes.filter(a => {
            if (_groupFilter && (a.axis_group || 'default') !== _groupFilter) return false;
            if (!term) return true;
            return (a.axis_name || '').toLowerCase().includes(term)
                || (a.pole_left  || '').toLowerCase().includes(term)
                || (a.pole_right || '').toLowerCase().includes(term)
                || (a.notes      || '').toLowerCase().includes(term);
        });

        countEl.textContent = `${_filtered.length} of ${_axes.length} axes`;

        if (_filtered.length === 0) {
            list.innerHTML = `<div class="sidebar-empty"><i class="bi bi-search" style="font-size:2rem; display:block; margin-bottom:8px;"></i>No axes match</div>`;
            return;
        }

        list.innerHTML = _filtered.map(a => {
            const isActive = parseInt(a.id) === _currentId;
            const grpBadge = _columnExists && a.axis_group
                ? `<span class="gen-badge group">${esc(a.axis_group)}</span>` : '';
            const catBadge = _catColumnExists && a.category
                ? `<span class="gen-badge cat">${esc(a.category)}</span>` : '';
            return `
            <div class="axis-card${isActive?' active':''}" data-id="${a.id}">
                <div class="axis-card-indicator"></div>
                <div class="axis-card-body">
                    <div class="axis-card-title">${esc(a.axis_name)}</div>
                    <div class="axis-card-meta">
                        ${grpBadge}${catBadge}
                        <span class="gen-badge model">${esc(a.pole_left)} → ${esc(a.pole_right)}</span>
                    </div>
                </div>
                <button class="axis-card-delete" data-id="${a.id}" title="Delete" onclick="event.stopPropagation();">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        }).join('');
    }

    // ── show workspace ────────────────────────────────────────────────────────

    function showWorkspace() {
        document.getElementById('forgeEmpty').style.display    = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';
    }

    // ── select axis (load into form) ──────────────────────────────────────────

    function selectAxis(id) {
        id = parseInt(id);

        // Try local data first (fast path)
        const local = _axes.find(a => parseInt(a.id) === id);
        if (local) {
            populateForm(local);
            _currentId = id;
            renderSidebar();
            showWorkspace();
            return;
        }

        // Fallback: fetch from API (preserved exactly from view_design_axes_admin.php)
        fetch('design_axes_api.php?action=load&id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(data => {
                if (data && data.status === 'ok' && data.axis) {
                    populateForm(data.axis);
                    _currentId = id;
                    renderSidebar();
                    showWorkspace();
                } else {
                    showToast('Could not load axis', 'error');
                }
            })
            .catch(() => showToast('Network error loading axis', 'error'));
    }

    function populateForm(axis) {
        document.getElementById('axisId').value   = axis.id || '';
        document.getElementById('axisName').value = axis.axis_name || '';
        if (_columnExists)    document.getElementById('axisGroup').value    = axis.axis_group || 'default';
        if (_catColumnExists) document.getElementById('axisCategory').value = axis.category   || '';
        document.getElementById('poleLeft').value  = axis.pole_left  || '';
        document.getElementById('poleRight').value = axis.pole_right || '';
        document.getElementById('axisNotes').value = axis.notes      || '';

        updatePolesPreview();

        document.getElementById('wsCardHeader').textContent = 'Edit Axis — ' + (axis.axis_name || '');
        document.getElementById('btnDelete').style.display = 'flex';

        // Info block
        const infoBlock = document.getElementById('axisInfoBlock');
        infoBlock.style.display = 'block';
        document.getElementById('axisInfoGrid').innerHTML = `
            <div class="info-card">
                <div class="info-card-label">ID</div>
                <div class="info-card-value">#${esc(axis.id)}</div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Group</div>
                <div class="info-card-value">${esc(axis.axis_group || 'default')}</div>
            </div>
            ${_catColumnExists && axis.category ? `<div class="info-card"><div class="info-card-label">Category</div><div class="info-card-value">${esc(axis.category)}</div></div>` : ''}
            <div class="info-card">
                <div class="info-card-label">Created</div>
                <div class="info-card-value">${esc(axis.created_at || '')}</div>
            </div>`;
    }

    function updatePolesPreview() {
        const l = document.getElementById('poleLeft').value  || 'Left pole';
        const r = document.getElementById('poleRight').value || 'Right pole';
        document.getElementById('previewLeft').textContent  = l;
        document.getElementById('previewRight').textContent = r;
    }

    // ── new axis ──────────────────────────────────────────────────────────────

    function newAxis() {
        _currentId = null;
        document.getElementById('axisId').value   = '';
        document.getElementById('axisName').value = '';
        if (_columnExists)    document.getElementById('axisGroup').value    = 'default';
        if (_catColumnExists) document.getElementById('axisCategory').value = '';
        document.getElementById('poleLeft').value  = '';
        document.getElementById('poleRight').value = '';
        document.getElementById('axisNotes').value = '';
        document.getElementById('btnDelete').style.display   = 'none';
        document.getElementById('axisInfoBlock').style.display = 'none';
        document.getElementById('wsCardHeader').textContent  = 'Create New Axis';
        updatePolesPreview();
        renderSidebar();
        showWorkspace();
    }

    // ── save axis (preserved exactly from view_design_axes_admin.php) ─────────

    function saveAxis() {
        const name = document.getElementById('axisName').value.trim();
        if (!name) { showToast('Axis name is required', 'error'); return; }

        const payload = {
            axis_name:  name,
            pole_left:  document.getElementById('poleLeft').value.trim(),
            pole_right: document.getElementById('poleRight').value.trim(),
            notes:      document.getElementById('axisNotes').value.trim()
        };
        if (!payload.pole_left  ) { showToast('Left pole is required',  'error'); return; }
        if (!payload.pole_right ) { showToast('Right pole is required', 'error'); return; }

        if (_columnExists)    payload.axis_group = document.getElementById('axisGroup').value.trim() || 'default';
        if (_catColumnExists) payload.category   = document.getElementById('axisCategory').value.trim();

        const axisId = document.getElementById('axisId').value;
        if (axisId) payload.id = parseInt(axisId, 10);

        const btn  = document.getElementById('btnSave');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
        btn.disabled  = true;

        fetch('design_axes_api.php?action=save', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        }).then(r => r.json())
        .then(data => {
            if (data && data.status === 'ok') {
                showToast(axisId ? 'Axis updated' : 'Axis created', 'success');
                const newId = data.id || (axisId ? parseInt(axisId) : null);

                // Update local array
                const entry = { ...payload, id: newId, created_at: '' };
                const idx   = _axes.findIndex(a => parseInt(a.id) === newId);
                if (idx >= 0) { _axes[idx] = entry; } else { _axes.unshift(entry); }

                _currentId = newId;
                document.getElementById('axisId').value = String(newId);
                document.getElementById('btnDelete').style.display = 'flex';
                document.getElementById('wsCardHeader').textContent = 'Edit Axis — ' + name;
                document.getElementById('statTotal').textContent = _axes.length;

                renderSidebar();
            } else {
                showToast('Save failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
            }
        }).catch(() => showToast('Network error saving axis', 'error'))
        .finally(() => { btn.innerHTML = orig; btn.disabled = false; });
    }

    // ── delete axis (preserved exactly) ──────────────────────────────────────

    function deleteAxis(idOverride) {
        const id = idOverride || _currentId;
        if (!id) return;
        if (!confirm('Delete this axis? This will also remove it from all profiles.')) return;

        fetch('design_axes_api.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: id})
        }).then(r => r.json())
        .then(data => {
            if (data && data.status === 'ok') {
                showToast('Axis deleted', 'success');
                _axes = _axes.filter(a => parseInt(a.id) !== id);
                document.getElementById('statTotal').textContent = _axes.length;

                if (_currentId === id) {
                    _currentId = null;
                    document.getElementById('forgeEmpty').style.display    = 'flex';
                    document.getElementById('forgeWorkspace').style.display = 'none';
                }
                renderSidebar();
            } else {
                showToast('Delete failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
            }
        }).catch(() => showToast('Network error deleting axis', 'error'));
    }

    // ── events & init ─────────────────────────────────────────────────────────

    function bindEvents() {
        // Sidebar delegation
        document.getElementById('sidebarList').addEventListener('click', e => {
            const delBtn = e.target.closest('.axis-card-delete');
            if (delBtn) { deleteAxis(parseInt(delBtn.dataset.id)); return; }
            const card = e.target.closest('.axis-card');
            if (card) selectAxis(card.dataset.id);
        });

        // Search
        document.getElementById('sidebarSearch').addEventListener('input', () => {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(() => renderSidebar(), 150);
        });

        // Group filter
        const gf = document.getElementById('groupFilter');
        if (gf) {
            gf.addEventListener('change', function() {
                _groupFilter = this.value;
                renderSidebar();
            });
        }

        // Live poles preview
        document.getElementById('poleLeft').addEventListener('input',  updatePolesPreview);
        document.getElementById('poleRight').addEventListener('input', updatePolesPreview);
    }

    function init() {
        renderSidebar();
        bindEvents();
    }

    return { init, newAxis, saveAxis, deleteAxis };
})();

document.addEventListener('DOMContentLoaded', () => AxesForge.init());
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>
