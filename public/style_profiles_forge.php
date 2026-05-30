<?php
// public/style_profiles_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// STYLE PROFILES FORGE
// Forge design system port of view_style_profiles_admin.php + view_style_sliders.php.
// Sidebar = saved profiles list. Main panel = slider editor + profile actions.
// Generator config settings live in a Forge modal.
// All PHP logic, API calls, slider sync, convert/download/save preserved exactly.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Style Profiles Forge";

// ── Column existence checks (preserved exactly from view_style_sliders.php) ──
$axisGroupColumnExists  = false;
$categoryColumnExists   = false;
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'axis_group'");
    $axisGroupColumnExists = $checkCol->rowCount() > 0;
    $checkCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'category'");
    $categoryColumnExists = $checkCol->rowCount() > 0;
} catch (Exception $e) {}

// ── Determine current axis group + category (preserved exactly) ──────────────
$currentGroup      = isset($_GET['axis_group']) ? trim($_GET['axis_group']) : null;
$availableGroups   = [];

if ($axisGroupColumnExists) {
    $groupsStmt    = $pdo->query("SELECT DISTINCT COALESCE(axis_group, 'default') as axis_group FROM design_axes ORDER BY axis_group ASC");
    $availableGroups = $groupsStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$currentGroup && !empty($availableGroups)) {
        $currentGroup = $availableGroups[0];
    }
} else {
    $currentGroup    = 'default';
    $availableGroups = ['default'];
}

$currentCategory     = isset($_GET['category']) ? trim($_GET['category']) : null;
$availableCategories = [];
if ($categoryColumnExists && $currentGroup) {
    $catStmt = $pdo->prepare("SELECT DISTINCT category FROM design_axes WHERE axis_group = :group AND category IS NOT NULL ORDER BY category ASC");
    $catStmt->execute([':group' => $currentGroup]);
    $availableCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$currentCategory && !empty($availableCategories)) {
        $currentCategory = $availableCategories[0];
    }
}

// ── Fetch axes (preserved exactly from view_style_sliders.php) ───────────────
if ($axisGroupColumnExists) {
    $sql    = "SELECT id, axis_name, pole_left, pole_right, notes FROM design_axes WHERE COALESCE(axis_group, 'default') = :group";
    $params = [':group' => $currentGroup ?: 'default'];
    if ($categoryColumnExists && $currentCategory) {
        $sql   .= " AND category = :category";
        $params[':category'] = $currentCategory;
    } else if ($categoryColumnExists) {
        $sql   .= " AND category IS NULL";
    }
    $sql   .= " ORDER BY id ASC";
    $stmt   = $pdo->prepare($sql);
    $stmt->execute($params);
    $axes   = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("SELECT id, axis_name, pole_left, pole_right, notes FROM design_axes ORDER BY id ASC");
    $axes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Fetch saved profiles for sidebar (preserved from view_style_profiles_admin.php) ─
try {
    $stmt = $pdo->query("
        SELECT id, IFNULL(name,'') AS name, IFNULL(description,'') AS description,
               IFNULL(axis_group,'default') AS axis_group, IFNULL(filename,'') AS filename, created_at
        FROM style_profiles
        ORDER BY axis_group ASC, created_at DESC
        LIMIT 500
    ");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $profiles = [];
}

$viewportScale = !empty($_GET['embed']) ? '1.0' : '0.9';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
<title>Style Profiles Forge</title>
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
.forge-layout { display:grid; grid-template-rows:52px 1fr; grid-template-columns:280px 1fr; grid-template-areas:"header header""sidebar main"; height:100vh; height:100dvh; overflow:hidden; }

/* ── HEADER ── */
.forge-header { grid-area:header; display:flex; align-items:center; justify-content:space-between; padding:0 20px; background:var(--surface); border-bottom:1px solid var(--border); z-index:100; }
.forge-logo { display:flex; align-items:center; gap:10px; font-family:var(--mono); font-size:0.85rem; font-weight:700; color:var(--amber); letter-spacing:2px; text-transform:uppercase; }
.forge-logo-icon { width:28px; height:28px; background:var(--amber-mid); border:1px solid var(--amber-glow); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-header-right { display:flex; align-items:center; gap:8px; }
.btn-icon-sm { width:36px; height:36px; border-radius:var(--radius); border:1px solid var(--border); background:var(--card); color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:15px; text-decoration:none; }
.btn-icon-sm:hover { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }

/* ── SIDEBAR ── */
.forge-sidebar { grid-area:sidebar; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
.sidebar-search { padding:12px; border-bottom:1px solid var(--border); flex-shrink:0; }
.sidebar-search-input { width:100%; padding:8px 10px 8px 32px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.8rem; transition:border-color 0.2s; }
.sidebar-search-input:focus { outline:none; border-color:var(--amber); }
.sidebar-search-wrap { position:relative; }
.sidebar-search-wrap::before { content:'⌕'; position:absolute; left:8px; top:50%; transform:translateY(-50%); color:var(--text-dim); font-size:16px; pointer-events:none; }
.sidebar-count { padding:4px 12px 8px; font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); flex-shrink:0; }
.sidebar-list { flex:1; overflow-y:auto; padding:8px; }
.sidebar-empty { text-align:center; padding:40px 20px; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem; }

/* Profile card */
.profile-card { padding:8px 10px 8px 12px; border-radius:var(--radius); border:1px solid transparent; cursor:pointer; transition:all 0.15s; margin-bottom:3px; position:relative; background:transparent; display:flex; align-items:center; gap:8px; }
.profile-card:hover { background:var(--card); border-color:var(--border); }
.profile-card.active { background:var(--amber-dim); border-color:var(--amber); }
.profile-card.active .profile-card-title { color:var(--amber); }
.profile-card-indicator { position:absolute; left:0; top:50%; transform:translateY(-50%); width:2px; height:0; background:var(--amber); border-radius:0 2px 2px 0; transition:height 0.2s; }
.profile-card.active .profile-card-indicator { height:60%; }
.profile-card-body { flex:1; min-width:0; }
.profile-card-title { font-family:var(--sans); font-weight:600; font-size:0.82rem; color:var(--text-bright); line-height:1.3; margin-bottom:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.profile-card-desc { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); margin-bottom:4px; cursor:pointer; transition:color 0.15s; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.profile-card-desc:hover { color:var(--text); }
.profile-card-meta { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }

/* Actions container replaces simple delete */
.profile-card-actions { display:flex; flex-direction:column; gap:4px; opacity:0; transition:opacity 0.15s; }
.profile-card:hover .profile-card-actions { opacity:1; }
@media (hover: none) { .profile-card-actions { opacity:1; } }
.profile-card-btn { flex-shrink:0; width:26px; height:26px; border-radius:var(--radius); border:1px solid transparent; background:transparent; color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; cursor:pointer; transition:all 0.15s; }
.profile-card-btn:hover { background:var(--card); border-color:var(--border-glow); color:var(--text); }
.profile-card-btn.edit:hover { border-color:var(--blue); color:var(--blue); background:var(--blue-dim); }
.profile-card-btn.delete:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); }

.gen-badge { font-family:var(--mono); font-size:0.63rem; padding:1px 5px; border-radius:3px; border:1px solid; }
.gen-badge.model  { border-color:var(--border-glow); color:var(--text-dim); background:var(--card); }
.gen-badge.group  { border-color:var(--blue); color:var(--blue); background:var(--blue-dim); }
.gen-badge.converted { border-color:var(--green); color:var(--green); background:var(--green-dim); }

/* ── MAIN ── */
.forge-main { grid-area:main; display:flex; flex-direction:column; overflow:hidden; background:var(--bg); }
.forge-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px; padding:40px; color:var(--text-dim); }
.forge-empty-icon { font-size:48px; opacity:0.3; filter:grayscale(1); }
.forge-empty-title { font-family:var(--mono); font-size:1rem; text-transform:uppercase; letter-spacing:2px; }
.forge-empty-sub { font-family:var(--mono); font-size:0.75rem; opacity:0.7; }

/* ── WORKSPACE ── */
.forge-workspace { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.workspace-body { flex:1; display:grid; grid-template-columns:1fr 320px; overflow:hidden; }

/* Sliders panel (left) */
.sliders-panel { padding:20px; overflow-y:auto; border-right:1px solid var(--border); display:flex; flex-direction:column; gap:16px; }

.panel-label { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
.panel-label::after { content:''; flex:1; height:1px; background:var(--border); }

/* Filter bar (group + category) */
.filter-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:4px; }
.filter-select { padding:6px 24px 6px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.75rem; cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; transition:border-color 0.15s; }
.filter-select:focus { outline:none; border-color:var(--amber); }
.filter-label { font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); }
.filter-count { font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); margin-left:auto; }

/* Axis rows */
.axes-list { display:flex; flex-direction:column; gap:8px; }
.axis-row { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:10px 12px; border-radius:var(--radius); background:var(--card); border:1px solid var(--border); transition:border-color 0.15s; }
.axis-row:hover { border-color:var(--border-glow); }
.axis-left { min-width:100px; flex:1 1 200px; }
.axis-name { font-family:var(--sans); font-weight:600; font-size:0.8rem; color:var(--text-bright); margin-bottom:2px; }
.axis-notes { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); }
.axis-range { flex:2 1 250px; display:flex; flex-wrap:wrap; align-items:center; gap:8px; min-width:0; }
.axis-pole { font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); min-width:40px; flex-shrink:1; word-break:break-word; }
.axis-pole.right { text-align:right; }

/* Range slider — Forge-styled */
.axis-slider { flex:1 1 80px; min-width:60px; -webkit-appearance:none; appearance:none; height:4px; border-radius:2px; background:var(--border); outline:none; cursor:pointer; }
.axis-slider::-webkit-slider-thumb { -webkit-appearance:none; width:14px; height:14px; border-radius:50%; background:var(--amber); border:2px solid var(--surface); box-shadow:0 0 0 1px var(--amber-glow); cursor:pointer; transition:transform 0.1s; }
.axis-slider::-webkit-slider-thumb:hover { transform:scale(1.25); }
.axis-slider::-moz-range-thumb { width:14px; height:14px; border-radius:50%; background:var(--amber); border:2px solid var(--surface); cursor:pointer; }
.axis-inputnum { width:52px; padding:4px 6px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.75rem; text-align:center; flex-shrink:0; }
.axis-inputnum:focus { outline:none; border-color:var(--amber); }

/* Right panel */
.profile-panel { display:flex; flex-direction:column; overflow:hidden; }
.profile-panel-scroll { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:16px; }

.form-group { display:flex; flex-direction:column; gap:5px; }
.form-label { font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.form-input,.form-textarea { width:100%; padding:8px 10px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.78rem; transition:border-color 0.15s; }
.form-input:focus,.form-textarea:focus { outline:none; border-color:var(--amber); }
.form-textarea { resize:vertical; min-height:60px; line-height:1.5; }

/* Converted prompt block */
.convert-block { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.convert-block-header { padding:8px 12px; background:var(--surface); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.convert-block-label { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.convert-pre { padding:12px; font-family:var(--mono); font-size:0.72rem; color:var(--green); white-space:pre-wrap; word-break:break-all; line-height:1.6; max-height:200px; overflow-y:auto; background:#050a05; }
.convert-loading { padding:14px 12px; display:flex; align-items:center; gap:10px; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); }
.convert-spinner { width:14px; height:14px; border:2px solid rgba(245,166,35,0.2); border-top-color:var(--amber); border-radius:50%; animation:spin 0.7s linear infinite; flex-shrink:0; }
@keyframes spin { to { transform:rotate(360deg); } }

/* JSON preview block */
.json-preview-block { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.json-preview-block-header { padding:8px 12px; background:var(--surface); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.json-preview-pre { padding:12px; font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); white-space:pre-wrap; word-break:break-all; line-height:1.5; max-height:160px; overflow-y:auto; }

/* Generate bar */
.generate-bar { padding:12px 16px; border-top:1px solid var(--border); background:var(--surface); display:flex; gap:6px; align-items:center; flex-shrink:0; flex-wrap:wrap; }
.btn-generate { flex:1; min-width:120px; padding:10px 16px; background:var(--amber); color:#000; border:none; border-radius:var(--radius); font-family:var(--mono); font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; gap:6px; }
.btn-generate:hover:not(:disabled) { filter:brightness(1.15); }
.btn-generate:disabled { opacity:0.5; cursor:not-allowed; }
.btn-forge-secondary { padding:10px 14px; background:transparent; color:var(--text-dim); border:1px solid var(--border); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; transition:all 0.15s; white-space:nowrap; }
.btn-forge-secondary:hover { border-color:var(--border-glow); color:var(--text); }
.btn-forge-danger { padding:10px 14px; background:var(--red-dim); color:var(--red); border:1px solid var(--red); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; transition:all 0.15s; }
.btn-forge-danger:hover { background:var(--red); color:#fff; }

/* ── MODALS ── */
.forge-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(3px); z-index:10000; display:none; align-items:center; justify-content:center; padding:16px; }
.forge-modal-overlay.open { display:flex; }
.forge-modal { background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:100%; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.6); animation:modalIn 0.2s ease; }
.forge-modal-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.forge-modal-title { font-family:var(--mono); font-size:0.8rem; font-weight:700; color:var(--amber); text-transform:uppercase; letter-spacing:1.5px; }
.forge-modal-close { width:28px; height:28px; border-radius:4px; border:1px solid var(--border); background:transparent; color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:14px; }
.forge-modal-close:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); }
.forge-modal-body { padding:20px; overflow-y:auto; flex:1; }
.forge-modal-footer { padding:12px 20px; border-top:1px solid var(--border); background:var(--bg); display:flex; justify-content:flex-end; gap:8px; flex-shrink:0; }

/* Generator config selects inside modal */
.config-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.config-select { width:100%; padding:8px 28px 8px 10px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--mono); font-size:0.78rem; appearance:none; cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; }
.config-select:focus { outline:none; border-color:var(--amber); }

/* Preview JSON modal */
.json-full-pre { font-family:var(--mono); font-size:0.72rem; color:var(--text); white-space:pre-wrap; word-break:break-all; line-height:1.5; padding:4px; }

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

@media (max-width:1100px) {
    .config-grid { grid-template-columns:1fr; }
}
@media (max-width:900px) {
    .forge-layout { grid-template-columns:1fr; grid-template-rows:52px 180px 1fr; grid-template-areas:"header""sidebar""main"; }
    .forge-sidebar { border-right:none; border-bottom:1px solid var(--border); }
    .workspace-body { grid-template-columns:1fr; grid-template-rows:1fr auto; }
    .profile-panel { border-top:1px solid var(--border); max-height:none; }
    .generate-bar { flex-wrap:wrap; }
}
</style>
</head>
<body>
<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-sliders"></i></div>
            Style Profiles
        </div>
        <div class="forge-header-right">
            <button class="btn-icon-sm" onclick="StyleForge.newProfile()" title="New profile (reset sliders)"><i class="bi bi-plus-lg"></i></button>
            <button class="btn-icon-sm" onclick="StyleForge.openConfigModal()" title="AI Generator Config"><i class="bi bi-gear"></i></button>
            <a href="design_axes_forge.php" class="btn-icon-sm" title="Design Axes"><i class="bi bi-rulers"></i></a>
            <a href="/dashboard.php" class="btn-icon-sm" title="Dashboard" style="text-decoration:none;"><i class="bi bi-house"></i></a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="sidebarSearch" placeholder="Search profiles…" autocomplete="off">
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
            <div class="forge-empty-icon"><i class="bi bi-sliders"></i></div>
            <div class="forge-empty-title">Style Profiles</div>
            <div class="forge-empty-sub">Select a profile or adjust sliders to create a new one</div>
        </div>

        <div class="forge-workspace" id="forgeWorkspace" style="display:none;">
            <div class="workspace-body">

                <!-- ── SLIDERS PANEL ── -->
                <div class="sliders-panel" id="slidersPanel">

                    <!-- Filter bar (group + category) — preserved from view_style_sliders.php -->
                    <?php if ($axisGroupColumnExists && !empty($availableGroups)): ?>
                    <div>
                        <div class="panel-label">Axis Filters</div>
                        <div class="filter-bar">
                            <span class="filter-label">Entity:</span>
                            <select id="axisGroupSelect" class="filter-select">
                                <?php foreach ($availableGroups as $grp): ?>
                                    <option value="<?= htmlspecialchars($grp) ?>" <?= $grp === $currentGroup ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst(str_replace('_',' ',$grp))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($categoryColumnExists && !empty($availableCategories)): ?>
                            <span class="filter-label">Category:</span>
                            <select id="categorySelect" class="filter-select">
                                <?php foreach ($availableCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $currentCategory ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <span class="filter-count"><?= count($axes) ?> axes</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Hidden state fields (preserved exactly) -->
                    <input type="hidden" id="currentAxisGroup" value="<?= htmlspecialchars($currentGroup) ?>">
                    <input type="hidden" id="currentCategory" value="<?= htmlspecialchars($currentCategory) ?>">
                    <input type="hidden" id="axisGroupsEnabled" value="<?= $axisGroupColumnExists ? '1' : '0' ?>">
                    <input type="hidden" id="currentProfileId" value="">

                    <!-- Axes list (preserved exactly from view_style_sliders.php) -->
                    <div>
                        <div class="panel-label">Axes <span style="color:var(--amber); margin-left:4px;">(<?= count($axes) ?>)</span></div>
                        <div class="axes-list" id="axesList">
                            <?php if (empty($axes)): ?>
                                <div style="padding:20px; text-align:center; font-family:var(--mono); font-size:0.8rem; color:var(--text-dim);">
                                    No axes defined for this entity/category.
                                </div>
                            <?php else: ?>
                                <?php foreach ($axes as $axis):
                                    $id    = (int)$axis['id'];
                                    $name  = $axis['axis_name'];
                                    $left  = $axis['pole_left'];
                                    $right = $axis['pole_right'];
                                    $notes = $axis['notes'];
                                ?>
                                <div class="axis-row" data-axis-id="<?= $id ?>">
                                    <div class="axis-left">
                                        <div class="axis-name"><?= htmlspecialchars($name) ?></div>
                                        <?php if (!empty($notes)): ?>
                                            <div class="axis-notes"><?= htmlspecialchars($notes) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="axis-range">
                                        <span class="axis-pole"><?= htmlspecialchars($left) ?></span>
                                        <input
                                            type="range"
                                            class="axis-slider"
                                            min="0" max="100" value="50"
                                            data-axis-id="<?= $id ?>"
                                            data-axis-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
                                            data-pole-left="<?= htmlspecialchars($left, ENT_QUOTES) ?>"
                                            data-pole-right="<?= htmlspecialchars($right, ENT_QUOTES) ?>"
                                        />
                                        <span class="axis-pole right"><?= htmlspecialchars($right) ?></span>
                                        <input type="number" class="axis-inputnum" min="0" max="100" value="50" data-axis-id="<?= $id ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /sliders-panel -->

                <!-- ── PROFILE PANEL (right) ── -->
                <div class="profile-panel">
                    <!-- Hidden workspace metadata inputs (replaces the visual section) -->
                    <input type="hidden" id="profileName" value="">
                    <input type="hidden" id="profileDescription" value="">

                    <div class="profile-panel-scroll" id="profilePanelScroll">
                        <!-- convert result populated by JS -->
                        <div id="convertResultBlock" style="display:none;"></div>
                        <!-- JSON preview populated by JS -->
                        <div id="jsonPreviewBlock" style="display:none;"></div>
                    </div>

                    <!-- Generate bar -->
                    <div class="generate-bar">
                        <button class="btn-generate" id="btnSave" onclick="StyleForge.saveProfile()">
                            <i class="bi bi-floppy"></i> Save
                        </button>
                        <button class="btn-forge-secondary" onclick="StyleForge.openDetailsModal()" title="Edit Name & Description">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn-forge-secondary" id="btnDownload" onclick="StyleForge.downloadJson()" title="Download JSON">
                            <i class="bi bi-download"></i>
                        </button>
                        <button class="btn-forge-secondary" id="btnConvert" onclick="StyleForge.convertProfile()" title="Convert to AI prompt">
                            <i class="bi bi-magic"></i> Convert
                        </button>
                        <button class="btn-forge-danger" id="btnDelete" onclick="StyleForge.deleteProfile()" style="display:none;" title="Delete profile">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div><!-- /profile-panel -->

            </div><!-- /workspace-body -->
        </div><!-- /forge-workspace -->
    </main>

</div><!-- /forge-layout -->

<!-- ── GENERATOR CONFIG MODAL ── -->
<div class="forge-modal-overlay" id="configModal">
    <div class="forge-modal" style="max-width:680px; max-height:80vh;">
        <div class="forge-modal-header">
            <div class="forge-modal-title">AI Generator Configuration</div>
            <button class="forge-modal-close" onclick="StyleForge.closeModal('configModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <div style="font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); margin-bottom:16px; line-height:1.6;">
                These configs control how style profiles are converted to AI prompts. Changes save automatically.
            </div>
            <div class="config-grid">
                <div class="form-group">
                    <label class="form-label">Axes Translation Config</label>
                    <select id="axesGeneratorSelect" class="config-select">
                        <option value="">Loading…</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Prompt Polish Config</label>
                    <select id="polishGeneratorSelect" class="config-select">
                        <option value="">Loading…</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="StyleForge.closeModal('configModal')">Close</button>
        </div>
    </div>
</div>

<!-- ── PROFILE DETAILS EDIT MODAL ── -->
<div class="forge-modal-overlay" id="detailsModal">
    <div class="forge-modal" style="max-width:500px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title">Profile Details</div>
            <button class="forge-modal-close" onclick="StyleForge.closeModal('detailsModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">Name <span style="color:var(--red);">*</span></label>
                <input type="text" id="modalProfileName" class="form-input" placeholder="e.g. Modern Minimalist">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea id="modalProfileDescription" class="form-textarea" rows="4" placeholder="Describe this style profile…"></textarea>
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="StyleForge.closeModal('detailsModal')">Cancel</button>
            <button class="btn-generate" style="padding:8px 16px; font-size:0.75rem;" onclick="StyleForge.commitDetailsModal()">Apply & Save</button>
        </div>
    </div>
</div>

<!-- ── DESCRIPTION VIEW MODAL ── -->
<div class="forge-modal-overlay" id="descModal" onclick="StyleForge.closeModal('descModal')">
    <div class="forge-modal" style="max-width:400px;" onclick="event.stopPropagation()">
        <div class="forge-modal-header">
            <div class="forge-modal-title" id="descModalTitle">Description</div>
            <button class="forge-modal-close" onclick="StyleForge.closeModal('descModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <div id="descModalText" style="font-family:var(--sans); font-size:0.85rem; color:var(--text); white-space:pre-wrap; line-height:1.5;"></div>
        </div>
    </div>
</div>

<div class="forge-toast-container" id="toastContainer"></div>

<!-- PHP data for JS -->
<script>
const _PHP_PROFILES = <?= json_encode($profiles) ?>;
</script>

<script>
const StyleForge = (() => {
    'use strict';

    // ── state ─────────────────────────────────────────────────────────────────
    let _profiles       = _PHP_PROFILES;
    let _filtered       = [];
    let _currentId      = null;  // currently loaded profile id (null = new)
    let _searchTimeout  = null;
    const axisGroupsEnabled = document.getElementById('axisGroupsEnabled').value === '1';

    // ── helpers ───────────────────────────────────────────────────────────────

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

    function showToast(msg, type) {
        const mapType = (type === 'danger' || type === 'error') ? 'error' : (type === 'warning' ? 'info' : (type || 'info'));
        toast(msg, mapType);
    }

    function esc(str) {
        if (str == null) return '';
        const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
    }

    function truncateWords(str, num) {
        if (!str) return '';
        const words = str.trim().split(/\s+/);
        if (words.length <= num) return str;
        return words.slice(0, num).join(' ') + '…';
    }

    // ── sidebar ───────────────────────────────────────────────────────────────

    function renderSidebar() {
        const list    = document.getElementById('sidebarList');
        const countEl = document.getElementById('sidebarCount');
        const term    = (document.getElementById('sidebarSearch').value || '').trim().toLowerCase();

        _filtered = term
            ? _profiles.filter(p =>
                (p.name         || '').toLowerCase().includes(term) ||
                (p.axis_group   || '').toLowerCase().includes(term) ||
                (p.description  || '').toLowerCase().includes(term))
            : _profiles;

        countEl.textContent = term
            ? `${_filtered.length} of ${_profiles.length} profiles`
            : `${_profiles.length} profiles`;

        if (_filtered.length === 0) {
            list.innerHTML = `<div class="sidebar-empty"><i class="bi bi-search" style="font-size:2rem; display:block; margin-bottom:8px;"></i>No profiles found</div>`;
            return;
        }

        list.innerHTML = _filtered.map(p => {
            const isActive = parseInt(p.id) === _currentId;
            return `
            <div class="profile-card${isActive?' active':''}" data-id="${p.id}">
                <div class="profile-card-indicator"></div>
                <div class="profile-card-body">
                    <div class="profile-card-title">${esc(p.name || '(untitled)')}</div>
                    ${p.description ? `<div class="profile-card-desc" title="Click to view description">${esc(truncateWords(p.description, 6))}</div>` : ''}
                    <div class="profile-card-meta">
                        <span class="gen-badge group">${esc(p.axis_group || 'default')}</span>
                    </div>
                </div>
                <div class="profile-card-actions">
                    <button class="profile-card-btn edit" data-id="${p.id}" title="Edit Name & Desc"><i class="bi bi-pencil"></i></button>
                    <button class="profile-card-btn delete" data-id="${p.id}" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </div>`;
        }).join('');
    }

    // ── details & desc modal ───────────────────────────────────────────────────

    function openDetailsModal() {
        document.getElementById('modalProfileName').value = document.getElementById('profileName').value;
        document.getElementById('modalProfileDescription').value = document.getElementById('profileDescription').value;
        document.getElementById('detailsModal').classList.add('open');
        setTimeout(() => document.getElementById('modalProfileName').focus(), 100);
    }

    function commitDetailsModal() {
        const n = document.getElementById('modalProfileName').value.trim();
        if (!n) {
            showToast('Name is required', 'error');
            return;
        }
        document.getElementById('profileName').value = n;
        document.getElementById('profileDescription').value = document.getElementById('modalProfileDescription').value.trim();
        closeModal('detailsModal');
        // trigger full save of the workspace since details were applied
        saveProfile();
    }

    function editProfileFromSidebar(id) {
        if (_currentId === id) {
            openDetailsModal();
        } else {
            showToast('Loading profile...', 'info');
            loadProfileById(id, false).then(() => {
                openDetailsModal();
            }).catch(() => {});
        }
    }

    function showDescriptionModal(id) {
        const p = _profiles.find(x => parseInt(x.id) === id);
        if (p && p.description) {
            document.getElementById('descModalTitle').textContent = p.name || '(untitled)';
            document.getElementById('descModalText').textContent = p.description;
            document.getElementById('descModal').classList.add('open');
        }
    }

    // ── slider sync (preserved exactly from view_style_sliders.php) ──────────

    function setUpSync() {
        document.querySelectorAll('.axis-slider').forEach(function(slider) {
            slider.addEventListener('input', function() {
                const id  = this.dataset.axisId;
                const val = this.value;
                const num = document.querySelector('input.axis-inputnum[data-axis-id="'+id+'"]');
                if (num) num.value = val;
            });
        });
        document.querySelectorAll('input.axis-inputnum').forEach(function(num) {
            num.addEventListener('input', function() {
                let v = parseInt(this.value || 0, 10);
                if (isNaN(v)) v = 0;
                if (v < 0)   v = 0;
                if (v > 100) v = 100;
                this.value = v;
                const id     = this.dataset.axisId;
                const slider = document.querySelector('.axis-slider[data-axis-id="'+id+'"]');
                if (slider) slider.value = v;
            });
        });
    }

    // ── collect payload (preserved exactly from view_style_sliders.php) ───────

    function collectPayload(name, description) {
        const payload = {
            name:        name || null,
            description: description || null,
            created_at:  new Date().toISOString(),
            axes:        []
        };
        if (axisGroupsEnabled) {
            payload.axis_group = document.getElementById('currentAxisGroup').value || 'default';
        }
        document.querySelectorAll('.axis-slider').forEach(function(slider) {
            payload.axes.push({
                id:         parseInt(slider.dataset.axisId, 10),
                key:        slider.dataset.axisName,
                pole_left:  slider.dataset.poleLeft,
                pole_right: slider.dataset.poleRight,
                value:      parseInt(slider.value, 10)
            });
        });
        return payload;
    }

    // ── apply profile to UI (preserved exactly from view_style_sliders.php) ───

    function applyProfileToUI(payload) {
        if (!payload || !payload.axes) return;
        document.getElementById('currentProfileId').value = payload.id ? String(payload.id) : '';
        document.getElementById('profileName').value        = payload.name || '';
        document.getElementById('profileDescription').value = payload.description || '';

        if (axisGroupsEnabled && payload.axis_group) {
            const currentGroup = document.getElementById('currentAxisGroup').value;
            if (payload.axis_group !== currentGroup) {
                window.location.href = 'style_profiles_forge.php?axis_group=' + encodeURIComponent(payload.axis_group) + '&load_profile_id=' + payload.id;
                return;
            }
        }

        payload.axes.forEach(function(ax) {
            const slider = document.querySelector('.axis-slider[data-axis-id="'+ax.id+'"]');
            const num    = document.querySelector('input.axis-inputnum[data-axis-id="'+ax.id+'"]');
            if (slider) slider.value = ax.value;
            if (num)    num.value    = ax.value;
        });

        _currentId = payload.id ? parseInt(payload.id) : null;
        document.getElementById('btnDelete').style.display = _currentId ? 'flex' : 'none';
        renderSidebar();

        // Show convert result if available
        if (payload.convert_result) {
            renderConvertResult(payload.convert_result, payload);
        } else {
            document.getElementById('convertResultBlock').style.display = 'none';
        }
        document.getElementById('jsonPreviewBlock').style.display = 'none';
    }

    // ── load profile by id (preserved exactly) ────────────────────────────────

    function loadProfileById(id, showToastOnSuccess = true) {
        if (!id) return Promise.reject();
        return fetch('style_profiles_api.php?action=load&id=' + encodeURIComponent(id))
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => {
                if (data && data.status === 'ok' && data.payload) {
                    applyProfileToUI(data.payload);
                    if (showToastOnSuccess) showToast('Profile loaded: ' + (data.payload.name || 'id:' + id), 'success');
                    showWorkspace();
                    return data.payload;
                } else {
                    showToast('Could not load profile: ' + (data && data.message ? data.message : 'unknown'), 'error');
                    throw new Error(data.message || 'unknown');
                }
            })
            .catch(err => { 
                showToast('Network error: ' + (err.message || ''), 'error'); 
                throw err;
            });
    }

    // ── save profile to DB (preserved exactly from view_style_sliders.php) ────

    function saveProfile() {
        const name        = document.getElementById('profileName').value.trim();
        const description = document.getElementById('profileDescription').value.trim();
        
        if (!name) {
            openDetailsModal();
            return;
        }

        const payload     = collectPayload(name, description);
        const curId       = document.getElementById('currentProfileId').value || '';
        if (curId) payload.id = parseInt(curId, 10);

        showToast('Saving profile to DB…', 'info');
        fetch('style_profiles_api.php?action=save_db', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        }).then(r => r.json())
        .then(data => {
            if (data && data.status === 'ok') {
                document.getElementById('currentProfileId').value = String(data.profile_id);
                _currentId = data.profile_id;
                showToast('Saved profile id: ' + data.profile_id, 'success');

                // Update or insert in local profiles array
                const existing = _profiles.findIndex(p => parseInt(p.id) === data.profile_id);
                const newEntry = {
                    id:         data.profile_id,
                    name:       name || '(untitled)',
                    description: description,
                    axis_group: payload.axis_group || 'default',
                    filename:   data.filename || '',
                    created_at: data.payload?.created_at || ''
                };
                if (existing >= 0) {
                    _profiles[existing] = newEntry;
                } else {
                    _profiles.unshift(newEntry);
                }
                document.getElementById('btnDelete').style.display = 'flex';
                renderSidebar();
            } else {
                showToast('Save failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
            }
        }).catch(err => { showToast('Network error saving to DB', 'error'); });
    }

    // ── download JSON (preserved exactly from view_style_sliders.php) ─────────

    function downloadJson() {
        const name        = document.getElementById('profileName').value.trim();
        const description = document.getElementById('profileDescription').value.trim();
        if (!name) {
            showToast('Please set a profile name first', 'info');
            openDetailsModal();
            return;
        }
        
        const payload     = collectPayload(name, description);
        const filename    = (name ? name.replace(/\s+/g,'_') : 'style_profile') + '_' + (new Date()).toISOString().replace(/[:.]/g,'-') + '.json';
        const dataStr     = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(payload, null, 2));
        const a           = document.createElement('a');
        a.setAttribute('href', dataStr);
        a.setAttribute('download', filename);
        document.body.appendChild(a);
        a.click();
        a.remove();
        showToast('Download started', 'info');
    }

    // ── convert profile (preserved exactly from view_style_profiles_admin.php) -

    function convertProfile() {
        const name        = document.getElementById('profileName').value.trim();
        const description = document.getElementById('profileDescription').value.trim();
        if (!name) {
            showToast('Please set a profile name first', 'info');
            openDetailsModal();
            return;
        }
        
        let profilePayload = collectPayload(name, description);
        const curId       = document.getElementById('currentProfileId').value || '';
        if (curId) profilePayload.id = parseInt(curId, 10);

        // Show loading state
        const block = document.getElementById('convertResultBlock');
        block.style.display = 'block';
        block.innerHTML = `
            <div class="convert-block">
                <div class="convert-block-header">
                    <span class="convert-block-label">Converting…</span>
                </div>
                <div class="convert-loading">
                    <div class="convert-spinner"></div>
                    <span>Running AI conversion…</span>
                </div>
            </div>`;
        document.getElementById('profilePanelScroll').scrollTop = 0;

        fetch('style_profiles_api.php?action=convert_proxy', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ profiles: [profilePayload] })
        }).then(function(resp) {
            if (!resp.ok) {
                return resp.text().then(function(text) {
                    throw new Error('Proxy error: ' + resp.status + ' ' + text);
                });
            }
            return resp.json();
        }).then(function(json) {
            let promptText = null;
            if (typeof json === 'string') {
                promptText = json;
            } else if (json.prompt) {
                promptText = json.prompt;
            } else if (json.payload && json.payload.prompt) {
                promptText = json.payload.prompt;
            } else if (json.result) {
                promptText = json.result;
            } else {
                promptText = JSON.stringify(json, null, 2);
            }

            renderConvertResult(promptText, profilePayload);

            // Save result to DB (preserved exactly)
            if (profilePayload.id) {
                fetch('style_profiles_api.php?action=save_convert_result', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: profilePayload.id, result: promptText })
                }).then(r => r.json())
                .then(data => {
                    if (data.status !== 'ok') {
                        showToast('Could not save prompt to DB', 'info');
                    }
                }).catch(() => { showToast('Network error saving prompt', 'error'); });
            }

        }).catch(function(err) {
            block.innerHTML = `
                <div class="convert-block">
                    <div class="convert-block-header">
                        <span class="convert-block-label" style="color:var(--red);">Conversion Failed</span>
                    </div>
                    <div class="convert-loading" style="color:var(--red);">${esc(err.message || 'unknown error')}</div>
                </div>`;
            showToast('Conversion failed: ' + (err.message || ''), 'error');
        });
    }

    function renderConvertResult(promptText, profilePayload) {
        const block = document.getElementById('convertResultBlock');
        block.style.display = 'block';

        const blobUrl = URL.createObjectURL(new Blob([promptText], {type:'text/plain;charset=utf-8'}));
        const dlName  = ((profilePayload?.name || 'prompt') + '_' + (profilePayload?.id || '') + '.txt').replace(/[^a-zA-Z0-9_\-\.]/g,'_');

        block.innerHTML = `
            <div class="convert-block">
                <div class="convert-block-header">
                    <span class="convert-block-label">Converted Prompt</span>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-icon-sm" id="btnCopyPrompt" style="width:26px;height:26px;font-size:11px;" title="Copy prompt"><i class="bi bi-clipboard"></i></button>
                        <a class="btn-icon-sm" href="${blobUrl}" download="${esc(dlName)}" style="width:26px;height:26px;font-size:11px;" title="Download .txt"><i class="bi bi-download"></i></a>
                    </div>
                </div>
                <pre class="convert-pre" id="convertedPromptPre">${esc(promptText)}</pre>
            </div>`;

        document.getElementById('btnCopyPrompt').addEventListener('click', function() {
            copyText(promptText, 'Prompt copied');
        });

        document.getElementById('profilePanelScroll').scrollTop = 0;
    }

    // ── delete profile ────────────────────────────────────────────────────────

    function deleteProfile(idOverride) {
        const id = idOverride || _currentId;
        if (!id) return;
        if (!confirm('Delete profile id ' + id + '? This cannot be undone.')) return;

        fetch('style_profiles_api.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: id})
        }).then(r => r.json())
        .then(data => {
            if (data && data.status === 'ok') {
                showToast('Deleted profile ' + id, 'success');
                _profiles = _profiles.filter(p => parseInt(p.id) !== id);

                if (_currentId === id) {
                    _currentId = null;
                    document.getElementById('currentProfileId').value = '';
                    document.getElementById('profileName').value      = '';
                    document.getElementById('profileDescription').value = '';
                    document.getElementById('btnDelete').style.display = 'none';
                    document.getElementById('convertResultBlock').style.display = 'none';
                    document.getElementById('jsonPreviewBlock').style.display   = 'none';
                }

                renderSidebar();
            } else {
                showToast('Delete failed', 'error');
            }
        }).catch(() => { showToast('Network error while deleting', 'error'); });
    }

    // ── new profile (reset) ───────────────────────────────────────────────────

    function newProfile() {
        _currentId = null;
        document.getElementById('currentProfileId').value   = '';
        document.getElementById('profileName').value         = '';
        document.getElementById('profileDescription').value  = '';
        document.getElementById('btnDelete').style.display   = 'none';
        document.getElementById('convertResultBlock').style.display = 'none';
        document.getElementById('jsonPreviewBlock').style.display   = 'none';

        // Reset all sliders to 50
        document.querySelectorAll('.axis-slider').forEach(s => { s.value = 50; });
        document.querySelectorAll('.axis-inputnum').forEach(n => { n.value = 50; });

        showWorkspace();
        renderSidebar();
        showToast('Sliders reset for new profile', 'info');
    }

    // ── workspace visibility ──────────────────────────────────────────────────

    function showWorkspace() {
        document.getElementById('forgeEmpty').style.display    = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';
    }

    // ── generator config modal (preserved exactly from view_style_profiles_admin.php) ─

    function openConfigModal() {
        loadGeneratorConfigs();
        document.getElementById('configModal').classList.add('open');
    }

    function loadGeneratorConfigs() {
        fetch('style_profiles_api.php?action=get_generator_configs')
            .then(r => r.json())
            .then(data => {
                if (data && data.status === 'ok' && data.configs) {
                    const axesSel   = document.getElementById('axesGeneratorSelect');
                    const polishSel = document.getElementById('polishGeneratorSelect');
                    axesSel.innerHTML   = '<option value="">-- Select Config --</option>';
                    polishSel.innerHTML = '<option value="">-- Select Config --</option>';
                    data.configs.forEach(cfg => {
                        const o1 = document.createElement('option');
                        o1.value = cfg.config_id; o1.textContent = cfg.title; axesSel.appendChild(o1);
                        const o2 = document.createElement('option');
                        o2.value = cfg.config_id; o2.textContent = cfg.title; polishSel.appendChild(o2);
                    });
                    loadCurrentConfig();
                }
            }).catch(err => console.error('Failed to load generator configs', err));
    }

    function loadCurrentConfig() {
        fetch('style_profiles_api.php?action=get_config')
            .then(r => r.json())
            .then(data => {
                if (data && data.status === 'ok' && data.config) {
                    if (data.config.axes_generator_config_id)   document.getElementById('axesGeneratorSelect').value   = data.config.axes_generator_config_id;
                    if (data.config.polish_generator_config_id) document.getElementById('polishGeneratorSelect').value = data.config.polish_generator_config_id;
                }
            }).catch(err => console.error('Failed to load config', err));
    }

    function saveConfigValue(key, value) {
        const payload = {}; payload[key] = value;
        fetch('style_profiles_api.php?action=save_config', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        }).then(r => r.json())
        .then(data => {
            if (data && data.status === 'ok') { showToast('Config saved', 'success'); }
            else { showToast('Failed to save config', 'error'); }
        }).catch(() => { showToast('Network error saving config', 'error'); });
    }

    // ── copy helper ───────────────────────────────────────────────────────────

    function copyText(text, successMsg) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => { showToast(successMsg || 'Copied', 'success'); }, () => { showToast('Could not copy', 'error'); });
        } else {
            try { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); showToast(successMsg || 'Copied', 'success'); }
            catch(e) { showToast('Copy failed', 'error'); }
        }
    }

    // ── modal helpers ─────────────────────────────────────────────────────────

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // ── filter navigation (preserved exactly from view_style_sliders.php) ─────

    function bindFilterEvents() {
        const axisGroupSelect = document.getElementById('axisGroupSelect');
        if (axisGroupSelect) {
            axisGroupSelect.addEventListener('change', function() {
                window.location.href = 'style_profiles_forge.php?axis_group=' + encodeURIComponent(this.value);
            });
        }

        const categorySelect = document.getElementById('categorySelect');
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                const newCategory  = this.value;
                const currentGroup = document.getElementById('currentAxisGroup').value;
                const currentProfileId = document.getElementById('currentProfileId').value;
                let url = 'style_profiles_forge.php?axis_group=' + encodeURIComponent(currentGroup) + '&category=' + encodeURIComponent(newCategory);
                if (currentProfileId) {
                    url += '&load_profile_id=' + encodeURIComponent(currentProfileId);
                }
                window.location.href = url;
            });
        }
    }

    // ── events & init ─────────────────────────────────────────────────────────

    function bindEvents() {
        // Sidebar delegation
        document.getElementById('sidebarList').addEventListener('click', e => {
            const delBtn = e.target.closest('.profile-card-btn.delete');
            if (delBtn) { deleteProfile(parseInt(delBtn.dataset.id)); return; }

            const editBtn = e.target.closest('.profile-card-btn.edit');
            if (editBtn) { editProfileFromSidebar(parseInt(editBtn.dataset.id)); return; }

            const descDiv = e.target.closest('.profile-card-desc');
            if (descDiv) { 
                const card = descDiv.closest('.profile-card');
                showDescriptionModal(parseInt(card.dataset.id)); 
                return; 
            }

            const card = e.target.closest('.profile-card');
            if (card) { loadProfileById(parseInt(card.dataset.id)); }
        });

        // Search
        document.getElementById('sidebarSearch').addEventListener('input', () => {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(() => renderSidebar(), 150);
        });

        // Config dropdowns auto-save
        document.getElementById('axesGeneratorSelect').addEventListener('change', function() {
            saveConfigValue('axes_generator_config_id', this.value);
        });
        document.getElementById('polishGeneratorSelect').addEventListener('change', function() {
            saveConfigValue('polish_generator_config_id', this.value);
        });

        // Close modals on overlay click
        document.querySelectorAll('.forge-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) closeModal(overlay.id);
            });
        });

        bindFilterEvents();
        setUpSync();

        // Handle load_profile_id from URL (preserved exactly from view_style_sliders.php)
        const params   = new URLSearchParams(window.location.search);
        const loadId   = params.get('load_profile_id') || params.get('load_profile') || params.get('id');
        if (loadId) {
            showWorkspace();
            setTimeout(() => loadProfileById(loadId, true), 300);
        } else if (_profiles.length > 0) {
            // Show workspace with reset sliders by default
            showWorkspace();
        }
    }

    function init() {
        renderSidebar();
        bindEvents();
    }

    return { 
        init, newProfile, saveProfile, downloadJson, convertProfile, 
        deleteProfile, openConfigModal, closeModal, openDetailsModal, 
        commitDetailsModal, showDescriptionModal 
    };
})();

document.addEventListener('DOMContentLoaded', () => StyleForge.init());
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>