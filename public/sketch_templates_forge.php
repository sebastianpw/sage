<?php
// public/sketch_templates_forge.php
// Consolidates: view_sketch_templates_admin.php, view_shot_types_admin.php,
//               view_camera_angles_admin.php, view_camera_perspectives_admin.php,
//               view_interactions_admin.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

$viewportScale = !empty($_GET['embed']) ? '1.0' : '0.9';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Templates Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') { document.documentElement.setAttribute('data-theme', 'dark'); }
      else if (theme === 'light') { document.documentElement.setAttribute('data-theme', 'light'); }
    } catch (e) {}
  })();
</script>

<style>
/* ═══════════════════════════════════════════════════════════
   FORGE — Design System (mirrors scheduler_forge.php)
═══════════════════════════════════════════════════════════ */
:root {
    --bg:           #080b10;
    --surface:      #0e1319;
    --card:         #111820;
    --card-hover:   #141e28;
    --border:       #1c2535;
    --border-glow:  #2a3a52;
    --text:         #c8d4e8;
    --text-dim:     #5a6a80;
    --text-bright:  #e8f0ff;
    --amber:        #f5a623;
    --amber-dim:    rgba(245, 166, 35, 0.08);
    --amber-mid:    rgba(245, 166, 35, 0.15);
    --amber-glow:   rgba(245, 166, 35, 0.4);
    --green:        #22d3a0;
    --green-dim:    rgba(34, 211, 160, 0.1);
    --red:          #f05060;
    --red-dim:      rgba(240, 80, 96, 0.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77, 166, 255, 0.1);
    --mono:         'Space Mono', 'Fira Mono', monospace;
    --sans:         'Syne', system-ui, sans-serif;
    --radius:       6px;
    --radius-lg:    10px;
}

@media (prefers-color-scheme: light) {
    :root {
        --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6;
        --border: #d1d5db; --border-glow: #9ca3af; --text: #111827; --text-dim: #4b5563;
        --text-bright: #000000; --amber: #d97706; --amber-dim: rgba(217,119,6,0.1);
        --amber-mid: rgba(217,119,6,0.2); --amber-glow: rgba(217,119,6,0.4);
        --green: #059669; --green-dim: rgba(5,150,105,0.1);
        --red: #dc2626; --red-dim: rgba(220,38,38,0.1);
        --blue: #2563eb; --blue-dim: rgba(37,99,235,0.1);
    }
}
:root[data-theme="light"], html[data-theme="light"] {
    --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6;
    --border: #d1d5db; --border-glow: #9ca3af; --text: #111827; --text-dim: #4b5563;
    --text-bright: #000000; --amber: #d97706; --amber-dim: rgba(217,119,6,0.1);
    --amber-mid: rgba(217,119,6,0.2); --amber-glow: rgba(217,119,6,0.4);
    --green: #059669; --green-dim: rgba(5,150,105,0.1);
    --red: #dc2626; --red-dim: rgba(220,38,38,0.1);
    --blue: #2563eb; --blue-dim: rgba(37,99,235,0.1);
}
:root[data-theme="dark"], html[data-theme="dark"] {
    --bg: #080b10; --surface: #0e1319; --card: #111820; --card-hover: #141e28;
    --border: #1c2535; --border-glow: #2a3a52; --text: #c8d4e8; --text-dim: #5a6a80;
    --text-bright: #e8f0ff; --amber: #f5a623; --amber-dim: rgba(245,166,35,0.08);
    --amber-mid: rgba(245,166,35,0.15); --amber-glow: rgba(245,166,35,0.4);
    --green: #22d3a0; --green-dim: rgba(34,211,160,0.1);
    --red: #f05060; --red-dim: rgba(240,80,96,0.1);
    --blue: #4da6ff; --blue-dim: rgba(77,166,255,0.1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; overflow: hidden; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

/* ── LAYOUT ── */
.forge-layout { display: grid; grid-template-rows: 52px 1fr; grid-template-columns: 320px 1fr; grid-template-areas: "header header" "sidebar main"; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ── */
.forge-header { grid-area: header; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; background: var(--surface); border-bottom: 1px solid var(--border); position: relative; z-index: 100; }
.forge-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.85rem; font-weight: 700; color: var(--amber); letter-spacing: 2px; text-transform: uppercase; }
.forge-logo-icon { width: 28px; height: 28px; background: var(--amber-mid); border: 1px solid var(--amber-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; }
.forge-header-right { display: flex; align-items: center; gap: 8px; }
.btn-icon-sm { width: 34px; height: 34px; border-radius: var(--radius); border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 15px; text-decoration: none; }
.btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.btn-icon-sm.active-section { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* ── SIDEBAR ── */
.forge-sidebar { grid-area: sidebar; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }

/* Section tabs in sidebar */
.sidebar-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; overflow-x: auto; }
.sidebar-tab { flex: 1; padding: 10px 4px; background: transparent; border: none; color: var(--text-dim); font-family: var(--mono); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; transition: all 0.15s; white-space: nowrap; border-bottom: 2px solid transparent; }
.sidebar-tab:hover { color: var(--text); }
.sidebar-tab.active { color: var(--amber); border-bottom-color: var(--amber); }

.sidebar-controls { padding: 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; gap: 6px; flex-direction: column; }
.sidebar-search-wrap { position: relative; }
.sidebar-search-wrap::before { content: '⌕'; position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 16px; pointer-events: none; }
.sidebar-search-input { width: 100%; padding: 7px 10px 7px 30px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.78rem; transition: border-color 0.2s; }
.sidebar-search-input:focus { outline: none; border-color: var(--amber); }

/* Filter row */
.sidebar-filter-row { display: flex; gap: 4px; }
.sidebar-filter-row select { flex: 1; padding: 5px 6px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.7rem; min-width: 0; }
.sidebar-filter-row select:focus { outline: none; border-color: var(--amber); }

.sidebar-count { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); padding: 0 2px; }

.sidebar-list { flex: 1; overflow-y: auto; padding: 6px; }

/* Item cards in sidebar */
.item-card { padding: 8px 10px 8px 12px; border-radius: var(--radius); border: 1px solid transparent; cursor: pointer; transition: all 0.15s; margin-bottom: 3px; position: relative; background: transparent; display: flex; align-items: center; gap: 8px; }
.item-card:hover { background: var(--card); border-color: var(--border); }
.item-card.active { background: var(--amber-dim); border-color: var(--amber); }
.item-card.active .item-card-title { color: var(--amber); }
.item-card-indicator { position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 2px; height: 0; background: var(--amber); border-radius: 0 2px 2px 0; transition: height 0.2s; }
.item-card.active .item-card-indicator { height: 60%; }
.item-card-body { flex: 1; min-width: 0; }
.item-card-title { font-family: var(--sans); font-weight: 600; font-size: 0.83rem; color: var(--text-bright); line-height: 1.3; margin-bottom: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.item-card-meta { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }

.gen-badge { font-family: var(--mono); font-size: 0.63rem; padding: 1px 5px; border-radius: 3px; border: 1px solid; white-space: nowrap; }
.gen-badge.active  { border-color: var(--green); color: var(--green); background: var(--green-dim); }
.gen-badge.inactive { border-color: var(--border); color: var(--text-dim); }
.gen-badge.model   { border-color: var(--border-glow); color: var(--text-dim); background: var(--card); }
.gen-badge.amber   { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

.sidebar-empty { text-align: center; padding: 40px 20px; color: var(--text-dim); font-family: var(--mono); font-size: 0.78rem; }

/* ── MAIN AREA ── */
.forge-main { grid-area: main; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }

.forge-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 40px; color: var(--text-dim); }
.forge-empty-icon { font-size: 48px; opacity: 0.3; filter: grayscale(1); }
.forge-empty-title { font-family: var(--mono); font-size: 1rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; }

.forge-workspace { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

/* Workspace header */
.workspace-header { padding: 14px 20px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0; display: flex; align-items: flex-start; gap: 12px; }
.workspace-title-block { flex: 1; min-width: 0; }
.workspace-title { font-family: var(--sans); font-size: 1.05rem; font-weight: 700; color: var(--text-bright); margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.workspace-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.workspace-header-actions { display: flex; gap: 6px; flex-shrink: 0; }

/* Workspace body — form panel + preview panel */
.workspace-body { flex: 1; display: grid; grid-template-columns: 420px 1fr; overflow: hidden; }
.params-panel { padding: 20px; overflow-y: auto; border-right: 1px solid var(--border); }
.preview-panel { padding: 16px; overflow-y: auto; background: var(--bg); }

/* Save bar */
.save-bar { padding: 14px 20px; border-top: 1px solid var(--border); background: var(--surface); display: flex; gap: 8px; align-items: center; }
.btn-save { flex: 1; max-width: 180px; padding: 10px 20px; background: var(--amber); color: #000; border: none; border-radius: var(--radius); font-family: var(--mono); font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-save:hover { filter: brightness(1.1); }

/* Panel section labels */
.panel-label { font-family: var(--mono); font-size: 0.63rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
.panel-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* Form elements */
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
.form-label .param-type { color: var(--amber); margin-left: 4px; font-size: 0.63rem; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 8px 11px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.78rem; transition: border-color 0.15s; appearance: none; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--amber); background: var(--card-hover); }
.form-select { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }
.form-textarea { min-height: 90px; resize: vertical; font-family: var(--mono); }
.form-textarea.json { font-size: 0.75rem; min-height: 70px; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.form-check-label { display: flex; align-items: center; gap: 7px; cursor: pointer; font-family: var(--mono); font-size: 0.75rem; color: var(--text); }
.form-check-label input[type=checkbox] { accent-color: var(--amber); width: 14px; height: 14px; }

/* Preview/detail card in right panel */
.detail-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 14px; }
.detail-card h4 { font-family: var(--mono); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-dim); margin-bottom: 10px; }
.detail-field { margin-bottom: 8px; }
.detail-field label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 2px; }
.detail-field .val { font-size: 0.85rem; color: var(--text-bright); }
.detail-field pre { font-family: var(--mono); font-size: 0.73rem; background: var(--bg); padding: 8px; border-radius: var(--radius); border: 1px solid var(--border); white-space: pre-wrap; word-break: break-word; color: var(--green); }

/* Buttons */
.btn-forge-primary   { padding: 7px 16px; background: var(--amber); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s; }
.btn-forge-primary:hover { filter: brightness(1.1); }
.btn-forge-secondary { padding: 7px 16px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.75rem; transition: all 0.15s; }
.btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }
.btn-forge-danger    { padding: 7px 16px; background: var(--red-dim); color: var(--red); border: 1px solid var(--red); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.75rem; transition: all 0.15s; }
.btn-forge-danger:hover { background: var(--red); color: #fff; }

/* Status badges */
.status-badge { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 0.65rem; text-transform: uppercase; border: 1px solid; font-family: var(--mono); }
.status-badge.active   { color: var(--green); border-color: var(--green); background: var(--green-dim); }
.status-badge.inactive { color: var(--text-dim); border-color: var(--border); background: var(--card); }

/* ── MODAL ── */
.forge-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 16px; }
.forge-modal-overlay.open { display: flex; }
.forge-modal { background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius-lg); width: 100%; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.6); animation: modalIn 0.2s ease; max-height: 90vh; }
.forge-modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.forge-modal-title { font-family: var(--mono); font-size: 0.78rem; font-weight: 700; color: var(--amber); text-transform: uppercase; letter-spacing: 1.5px; }
.forge-modal-close { width: 28px; height: 28px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.forge-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.forge-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.forge-modal-footer { padding: 12px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; }

/* ── TOAST ── */
.forge-toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.forge-toast { padding: 9px 14px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.78rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); animation: toastIn 0.25s ease; pointer-events: all; cursor: pointer; max-width: 320px; display: flex; align-items: center; gap: 8px; }
.forge-toast.success { border-color: var(--green); }
.forge-toast.error   { border-color: var(--red); color: var(--red); }
.forge-toast.info    { border-color: var(--amber); }
.forge-toast.out     { animation: toastOut 0.25s ease forwards; }

@keyframes toastIn  { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to   { opacity: 0; transform: translateY(10px); } }
@keyframes modalIn  { from { opacity: 0; transform: scale(0.96) translateY(-10px); } to { opacity: 1; transform: none; } }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .forge-layout { grid-template-columns: 1fr; grid-template-rows: 52px 180px 1fr; grid-template-areas: "header" "sidebar" "main"; }
    .forge-sidebar { border-right: none; border-bottom: 1px solid var(--border); }
    .workspace-body { grid-template-columns: 1fr; }
    .params-panel { border-right: none; border-bottom: 1px solid var(--border); }
    .preview-panel { display: none; }
}
</style>
</head>
<body>

<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-pencil-square"></i></div>
            Templates Forge
        </div>
        <div class="forge-header-right">
            <!-- Section switcher buttons -->
            <button class="btn-icon-sm active-section" id="headerBtnTemplates" onclick="TF.setSection('templates')" title="Sketch Templates">
                <i class="bi bi-file-earmark-code"></i>
            </button>
            <button class="btn-icon-sm" id="headerBtnInteractions" onclick="TF.setSection('interactions')" title="Interactions">
                <i class="bi bi-arrows-angle-contract"></i>
            </button>
            <button class="btn-icon-sm" id="headerBtnShotTypes" onclick="TF.setSection('shot_types')" title="Shot Types">
                <i class="bi bi-camera-video"></i>
            </button>
            <button class="btn-icon-sm" id="headerBtnAngles" onclick="TF.setSection('camera_angles')" title="Camera Angles">
                <i class="bi bi-camera"></i>
            </button>
            <button class="btn-icon-sm" id="headerBtnPerspectives" onclick="TF.setSection('camera_perspectives')" title="Camera Perspectives">
                <i class="bi bi-eye"></i>
            </button>
            <button class="btn-icon-sm" onclick="TF.newItem()" title="New Item">
                <i class="bi bi-plus-lg"></i>
            </button>
            <a href="/dashboard.php" class="btn-icon-sm" title="Back to Dashboard" style="text-decoration:none;">
                <i class="bi bi-house"></i>
            </a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-controls">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="sidebarSearch" placeholder="Search…" autocomplete="off">
            </div>
            <!-- Filter row — only shown for templates -->
            <div class="sidebar-filter-row" id="filterRow" style="display:none;">
                <select id="filterEntityType" title="Entity Type">
                    <option value="">All Types</option>
                </select>
                <select id="filterShotType" title="Shot Type">
                    <option value="">All Shots</option>
                </select>
            </div>
            <!-- Filter row — only shown for interactions -->
            <div class="sidebar-filter-row" id="filterRowInteractions" style="display:none;">
                <select id="filterGroup" title="Group">
                    <option value="">All Groups</option>
                </select>
                <select id="filterCategory" title="Category">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="sidebar-count" id="sidebarCount"></div>
        </div>
        <div class="sidebar-list" id="sidebarList">
            <div class="sidebar-empty">
                <div style="font-size:2rem; margin-bottom:8px;"><i class="bi bi-hourglass-split"></i></div>
                Loading…
            </div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main" id="forgeMain">

        <!-- Empty state -->
        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon"><i class="bi bi-pencil-square"></i></div>
            <div class="forge-empty-title">Select an Item</div>
            <div style="font-family:var(--mono); font-size:0.78rem; color:var(--text-dim);">or click + to create new</div>
        </div>

        <!-- Workspace -->
        <div class="forge-workspace" id="forgeWorkspace" style="display:none;">
            <div class="workspace-header">
                <div class="workspace-title-block">
                    <div class="workspace-title" id="wsTitle">—</div>
                    <div class="workspace-meta" id="wsMeta"></div>
                </div>
                <div class="workspace-header-actions">
                    <button class="btn-icon-sm" id="btnCopyItem" onclick="TF.copyCurrentItem()" title="Copy / Duplicate">
                        <i class="bi bi-copy"></i>
                    </button>
                    <button class="btn-icon-sm" id="btnToggleItem" onclick="TF.toggleCurrentItem()" title="Toggle Active">
                        <i class="bi bi-toggle-on"></i>
                    </button>
                    <button class="btn-icon-sm" onclick="TF.deleteCurrentItem()" title="Delete Item">
                        <i class="bi bi-trash" style="color:var(--red);"></i>
                    </button>
                </div>
            </div>

            <div class="workspace-body">
                <!-- LEFT: Form Panel -->
                <div class="params-panel" id="paramsPanel">
                    <!-- Template form -->
                    <div id="formTemplate">
                        <div class="panel-label">Identity</div>
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" id="t_name" class="form-input" placeholder="Template name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Core Idea</label>
                            <input type="text" id="t_core_idea" class="form-input" placeholder="The central concept">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Entity Type</label>
                            <select id="t_entity_type" class="form-select"></select>
                        </div>

                        <div class="panel-label" style="margin-top:20px;">Camera</div>
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Shot Type</label>
                                <select id="t_shot_type" class="form-select"></select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Angle</label>
                                <select id="t_camera_angle" class="form-select"></select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Perspective</label>
                                <select id="t_perspective" class="form-select"></select>
                            </div>
                        </div>

                        <div class="panel-label" style="margin-top:20px;">Slots & Tags</div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Entity Slots <span class="param-type">JSON</span></label>
                                <textarea id="t_entity_slots" class="form-textarea json">["ENVIRONMENT"]</textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tags <span class="param-type">JSON</span></label>
                                <textarea id="t_tags" class="form-textarea json">["tag1"]</textarea>
                            </div>
                        </div>

                        <div class="panel-label" style="margin-top:20px;">Prompt</div>
                        <div class="form-group">
                            <label class="form-label">Example Prompt</label>
                            <textarea id="t_example_prompt" class="form-textarea" style="min-height:100px;"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-check-label">
                                <input type="checkbox" id="t_active" checked> Active
                            </label>
                        </div>
                    </div>

                    <!-- Interaction form -->
                    <div id="formInteraction" style="display:none;">
                        <div class="panel-label">Identity</div>
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" id="i_name" class="form-input" placeholder="Interaction name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea id="i_description" class="form-textarea"></textarea>
                        </div>

                        <div class="panel-label" style="margin-top:20px;">Classification</div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Group <span class="param-type">required</span></label>
                                <input type="text" id="i_interaction_group" class="form-input" list="groupDatalist" placeholder="e.g. Combat">
                                <datalist id="groupDatalist"></datalist>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category <span class="param-type">optional</span></label>
                                <input type="text" id="i_category" class="form-input" list="categoryDatalist" placeholder="e.g. Melee">
                                <datalist id="categoryDatalist"></datalist>
                            </div>
                        </div>

                        <div class="panel-label" style="margin-top:20px;">Prompt</div>
                        <div class="form-group">
                            <label class="form-label">Example Prompt</label>
                            <textarea id="i_example_prompt" class="form-textarea" style="min-height:100px;"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-check-label">
                                <input type="checkbox" id="i_active" checked> Active
                            </label>
                        </div>
                    </div>

                    <!-- Simple (Shot Type / Angle / Perspective) form -->
                    <div id="formSimple" style="display:none;">
                        <div class="panel-label">Identity</div>
                        <div class="form-group">
                            <label class="form-label" id="simpleNameLabel">Name</label>
                            <input type="text" id="s_name" class="form-input" placeholder="Name">
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Preview Panel -->
                <div class="preview-panel" id="previewPanel">
                    <div id="previewEmpty" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem; text-align:center; gap:12px;">
                        <i class="bi bi-eye" style="font-size:2rem; opacity:0.3;"></i>
                        Preview will appear here after loading an item
                    </div>
                    <div id="previewContent" style="display:none;"></div>
                </div>
            </div>

            <!-- Save bar -->
            <div class="save-bar">
                <button class="btn-save" onclick="TF.saveCurrentItem()">
                    <i class="bi bi-floppy"></i> SAVE
                </button>
                <span style="font-family:var(--mono); font-size:0.7rem; color:var(--text-dim);" id="saveHint"></span>
            </div>
        </div><!-- /forge-workspace -->
    </main>
</div><!-- /forge-layout -->

<!-- ── TOAST ── -->
<div class="forge-toast-container" id="toastContainer"></div>

<script>
const TF = (() => {
    'use strict';

    const API = '/sketch_templates_forge_api.php';

    // ── State ──
    let _section   = 'templates'; // templates | interactions | shot_types | camera_angles | camera_perspectives
    let _allItems  = [];          // raw data from server for current section
    let _filtered  = [];          // after client-side filter
    let _current   = null;        // selected item object
    let _isNew     = false;

    // Lookup data (for template dropdowns)
    let _shotTypes   = [];
    let _angles      = [];
    let _perspectives = [];
    let _groups      = [];
    let _categories  = [];

    const ENTITY_TYPES = {
        characters: 'Characters', character_poses: 'Character Poses', animas: 'Animas',
        locations: 'Locations', backgrounds: 'Backgrounds', artifacts: 'Artifacts',
        vehicles: 'Vehicles', scene_parts: 'Scene Parts', controlnet_maps: 'Controlnet Maps',
        spawns: 'Spawns', generatives: 'Generatives', sketches: 'Sketches',
        prompt_matrix_blueprints: 'Prompt Matrix Blueprints', composites: 'Composites'
    };
    const ENTITY_ICONS = {
        characters:'🦸', character_poses:'🤸', animas:'🐾', locations:'🗺️', backgrounds:'🏞️',
        artifacts:'🏺', vehicles:'🛸', scene_parts:'🎬', controlnet_maps:'☠️', spawns:'🌱',
        generatives:'⚡', sketches:'🪄', prompt_matrix_blueprints:'🌌', composites:'🧩'
    };

    // ── API ──
    async function api(action, data = {}) {
        const res = await fetch(`${API}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // ── Toast ──
    function toast(msg, type = 'info', duration = 3200) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        const icons = { success: '✓', error: '✕', info: '◆' };
        el.innerHTML = `<span style="font-size:11px;">${icons[type] || '◆'}</span> ${msg}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        const dismiss = (e) => { e.classList.add('out'); setTimeout(() => e.remove(), 300); };
        setTimeout(() => dismiss(el), duration);
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // ── Init ──
    async function init() {
        await loadLookups();
        populateTemplateDropdowns();
        bindSearchAndFilters();
        await setSection('templates');
    }

    async function loadLookups() {
        const [r1, r2, r3] = await Promise.all([
            api('shot_types_list'),
            api('camera_angles_list'),
            api('camera_perspectives_list')
        ]);
        _shotTypes    = r1.status === 'ok' ? r1.data : [];
        _angles       = r2.status === 'ok' ? r2.data : [];
        _perspectives = r3.status === 'ok' ? r3.data : [];
    }

    function populateTemplateDropdowns() {
        // Build a <select> safely by creating option elements directly (avoids
        // escHtml mangling values so that .value = rawString always matches).
        function buildOptions(sel, items, valueFn, labelFn) {
            sel.innerHTML = '';
            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value       = valueFn(item);   // raw — no HTML encoding
                opt.textContent = labelFn(item);   // textContent is safe
                sel.appendChild(opt);
            });
        }

        buildOptions(
            document.getElementById('t_entity_type'),
            Object.entries(ENTITY_TYPES),
            ([k])     => k,
            ([k, v])  => (ENTITY_ICONS[k] || '') + ' ' + v
        );

        buildOptions(
            document.getElementById('t_shot_type'),
            _shotTypes,
            s => s.name,
            s => s.name
        );

        buildOptions(
            document.getElementById('t_camera_angle'),
            _angles,
            a => a.name,
            a => a.name
        );

        buildOptions(
            document.getElementById('t_perspective'),
            _perspectives,
            p => p.name,
            p => p.name
        );
    }

    // ── Section switching ──
    async function setSection(section) {
        _section = section;
        _current = null;
        _isNew   = false;

        // Header button highlights
        ['templates','interactions','shot_types','camera_angles','camera_perspectives'].forEach(s => {
            const map = {
                templates: 'headerBtnTemplates',
                interactions: 'headerBtnInteractions',
                shot_types: 'headerBtnShotTypes',
                camera_angles: 'headerBtnAngles',
                camera_perspectives: 'headerBtnPerspectives'
            };
            const btn = document.getElementById(map[s]);
            if (btn) btn.classList.toggle('active-section', s === section);
        });

        // Filter rows
        document.getElementById('filterRow').style.display = section === 'templates' ? 'flex' : 'none';
        document.getElementById('filterRowInteractions').style.display = section === 'interactions' ? 'flex' : 'none';

        showEmpty();
        await loadSection();
    }

    async function loadSection() {
        const actionMap = {
            templates: 'templates_list',
            interactions: 'interactions_list',
            shot_types: 'shot_types_list',
            camera_angles: 'camera_angles_list',
            camera_perspectives: 'camera_perspectives_list'
        };
        try {
            const r = await api(actionMap[_section]);
            if (r.status === 'ok') {
                _allItems = r.data;
                if (_section === 'templates') populateSidebarFilterDropdowns();
                if (_section === 'interactions') populateInteractionFilterDropdowns();
                applyFilter();
            }
        } catch(e) { toast('Failed to load data', 'error'); }
    }

    function populateSidebarFilterDropdowns() {
        const entityTypes = [...new Set(_allItems.map(i => i.entity_type).filter(Boolean))].sort();
        const shotTypes   = [...new Set(_allItems.map(i => i.shot_type).filter(Boolean))].sort();

        function buildFilterSelect(sel, placeholder, values, labelFn) {
            sel.innerHTML = '';
            const def = document.createElement('option');
            def.value = ''; def.textContent = placeholder;
            sel.appendChild(def);
            values.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = labelFn ? labelFn(v) : v;
                sel.appendChild(opt);
            });
        }

        buildFilterSelect(
            document.getElementById('filterEntityType'),
            'All Types', entityTypes,
            t => (ENTITY_ICONS[t] || '') + ' ' + (ENTITY_TYPES[t] || t)
        );
        buildFilterSelect(
            document.getElementById('filterShotType'),
            'All Shots', shotTypes
        );
    }

    function populateInteractionFilterDropdowns() {
        _groups     = [...new Set(_allItems.map(i => i.interaction_group).filter(Boolean))].sort();
        _categories = [...new Set(_allItems.map(i => i.category).filter(Boolean))].sort();

        function buildSelect(sel, placeholder, values) {
            sel.innerHTML = '';
            const def = document.createElement('option');
            def.value = ''; def.textContent = placeholder;
            sel.appendChild(def);
            values.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v; opt.textContent = v;
                sel.appendChild(opt);
            });
        }
        function buildDatalist(dl, values) {
            dl.innerHTML = '';
            values.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v;
                dl.appendChild(opt);
            });
        }

        buildSelect(document.getElementById('filterGroup'), 'All Groups', _groups);
        buildSelect(document.getElementById('filterCategory'), 'All Categories', _categories);
        buildDatalist(document.getElementById('groupDatalist'), _groups);
        buildDatalist(document.getElementById('categoryDatalist'), _categories);
    }

    // ── Filter & Render Sidebar ──
    function applyFilter() {
        const search = document.getElementById('sidebarSearch').value.toLowerCase().trim();

        if (_section === 'templates') {
            const et = document.getElementById('filterEntityType').value.toLowerCase();
            const st = document.getElementById('filterShotType').value.toLowerCase();
            _filtered = _allItems.filter(item => {
                if (et && (item.entity_type || '').toLowerCase() !== et) return false;
                if (st && (item.shot_type || '').toLowerCase() !== st) return false;
                if (search) {
                    const hay = [item.name, item.core_idea, item.shot_type, item.camera_angle, item.perspective, item.entity_type].join(' ').toLowerCase();
                    if (!hay.includes(search)) return false;
                }
                return true;
            });
        } else if (_section === 'interactions') {
            const grp = document.getElementById('filterGroup').value.toLowerCase();
            const cat = document.getElementById('filterCategory').value.toLowerCase();
            _filtered = _allItems.filter(item => {
                if (grp && (item.interaction_group || '').toLowerCase() !== grp) return false;
                if (cat && (item.category || '').toLowerCase() !== cat) return false;
                if (search) {
                    const hay = [item.name, item.description, item.interaction_group, item.category].join(' ').toLowerCase();
                    if (!hay.includes(search)) return false;
                }
                return true;
            });
        } else {
            _filtered = search ? _allItems.filter(item => (item.name || '').toLowerCase().includes(search)) : [..._allItems];
        }

        renderSidebar();
    }

    function renderSidebar() {
        const container = document.getElementById('sidebarList');
        document.getElementById('sidebarCount').textContent = `${_filtered.length} of ${_allItems.length} shown`;

        if (_filtered.length === 0) {
            container.innerHTML = `<div class="sidebar-empty"><i class="bi bi-search" style="font-size:1.5rem; margin-bottom:8px;"></i><br>No items found</div>`;
            return;
        }

        container.innerHTML = _filtered.map(item => {
            const isActive = _current && _current.id === item.id;
            let badges = '';
            let subtitle = '';

            if (_section === 'templates') {
                const icon = ENTITY_ICONS[item.entity_type] || '▫️';
                badges += `<span class="gen-badge ${item.active ? 'active' : 'inactive'}">${item.active ? 'ON' : 'OFF'}</span>`;
                badges += `<span class="gen-badge model">${escHtml(icon)} ${escHtml(item.entity_type)}</span>`;
                subtitle = `<span class="gen-badge model">${escHtml(item.shot_type)}</span>`;
            } else if (_section === 'interactions') {
                badges += `<span class="gen-badge ${item.active ? 'active' : 'inactive'}">${item.active ? 'ON' : 'OFF'}</span>`;
                badges += `<span class="gen-badge amber">${escHtml(item.interaction_group)}</span>`;
                if (item.category) badges += `<span class="gen-badge model">${escHtml(item.category)}</span>`;
            } else {
                // simple lookup — no badges needed beyond name
            }

            return `
            <div class="item-card${isActive ? ' active' : ''}" onclick="TF.selectItem(${item.id})">
                <div class="item-card-indicator"></div>
                <div class="item-card-body">
                    <div class="item-card-title">${escHtml(item.name)}</div>
                    ${badges || subtitle ? `<div class="item-card-meta">${badges}${subtitle}</div>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    // ── Select / Load item ──
    async function selectItem(id) {
        _isNew = false;
        const item = _allItems.find(i => i.id === id) || null;
        _current = item;
        if (!item) { toast('Item not found in local data', 'error'); return; }

        showWorkspace();
        populateForm(item);
        renderPreview(item);
        renderSidebar();
    }

    function populateForm(item) {
        document.getElementById('wsTitle').textContent = item.name;
        updateWsMeta(item);

        // Show/hide correct form
        document.getElementById('formTemplate').style.display    = _section === 'templates'    ? '' : 'none';
        document.getElementById('formInteraction').style.display  = _section === 'interactions' ? '' : 'none';
        document.getElementById('formSimple').style.display       = ['shot_types','camera_angles','camera_perspectives'].includes(_section) ? '' : 'none';

        if (_section === 'templates') {
            document.getElementById('t_name').value           = item.name || '';
            document.getElementById('t_core_idea').value      = item.core_idea || '';
            document.getElementById('t_entity_type').value    = item.entity_type || 'sketches';
            document.getElementById('t_shot_type').value      = item.shot_type || '';
            document.getElementById('t_camera_angle').value   = item.camera_angle || '';
            document.getElementById('t_perspective').value    = item.perspective || '';
            document.getElementById('t_entity_slots').value   = item.entity_slots || '["ENVIRONMENT"]';
            document.getElementById('t_tags').value           = item.tags || '["tag1"]';
            document.getElementById('t_example_prompt').value = item.example_prompt || '';
            document.getElementById('t_active').checked       = !!parseInt(item.active);
        } else if (_section === 'interactions') {
            document.getElementById('i_name').value              = item.name || '';
            document.getElementById('i_description').value       = item.description || '';
            document.getElementById('i_interaction_group').value = item.interaction_group || '';
            document.getElementById('i_category').value          = item.category || '';
            document.getElementById('i_example_prompt').value    = item.example_prompt || '';
            document.getElementById('i_active').checked          = !!parseInt(item.active);
        } else {
            const labelMap = { shot_types: 'Shot Type Name', camera_angles: 'Angle Name', camera_perspectives: 'Perspective Name' };
            document.getElementById('simpleNameLabel').textContent = labelMap[_section] || 'Name';
            document.getElementById('s_name').value = item.name || '';
        }

        // Hide copy/toggle for simple lookups
        const hasToggle = _section === 'templates' || _section === 'interactions';
        document.getElementById('btnCopyItem').style.display   = hasToggle ? '' : 'none';
        document.getElementById('btnToggleItem').style.display = hasToggle ? '' : 'none';
    }

    function updateWsMeta(item) {
        const meta = document.getElementById('wsMeta');
        if (_section === 'templates') {
            const icon = ENTITY_ICONS[item.entity_type] || '';
            meta.innerHTML = `
                <span class="status-badge ${item.active ? 'active' : 'inactive'}">${item.active ? 'ACTIVE' : 'INACTIVE'}</span>
                <span class="gen-badge model">${icon} ${escHtml(item.entity_type)}</span>
                <span class="gen-badge model">${escHtml(item.shot_type)}</span>`;
        } else if (_section === 'interactions') {
            meta.innerHTML = `
                <span class="status-badge ${item.active ? 'active' : 'inactive'}">${item.active ? 'ACTIVE' : 'INACTIVE'}</span>
                <span class="gen-badge amber">${escHtml(item.interaction_group)}</span>
                ${item.category ? `<span class="gen-badge model">${escHtml(item.category)}</span>` : ''}`;
        } else {
            meta.innerHTML = `<span class="gen-badge model">ID: ${item.id}</span>`;
        }
    }

    function renderPreview(item) {
        document.getElementById('previewEmpty').style.display = 'none';
        document.getElementById('previewContent').style.display = '';

        let html = '';

        if (_section === 'templates') {
            const icon = ENTITY_ICONS[item.entity_type] || '';
            html = `
            <div class="detail-card">
                <h4>Template Summary</h4>
                <div class="detail-field"><label>Name</label><div class="val">${escHtml(item.name)}</div></div>
                <div class="detail-field"><label>Core Idea</label><div class="val" style="font-style:italic;">"${escHtml(item.core_idea)}"</div></div>
                <div class="detail-field"><label>Entity Type</label><div class="val">${icon} ${escHtml(ENTITY_TYPES[item.entity_type] || item.entity_type)}</div></div>
            </div>
            <div class="detail-card">
                <h4>Camera Config</h4>
                <div class="detail-field"><label>Shot Type</label><div class="val">${escHtml(item.shot_type)}</div></div>
                <div class="detail-field"><label>Angle</label><div class="val">${escHtml(item.camera_angle)}</div></div>
                <div class="detail-field"><label>Perspective</label><div class="val">${escHtml(item.perspective)}</div></div>
            </div>
            <div class="detail-card">
                <h4>Slots &amp; Tags</h4>
                <div class="detail-field"><label>Entity Slots</label><pre>${escHtml(item.entity_slots)}</pre></div>
                <div class="detail-field"><label>Tags</label><pre>${escHtml(item.tags)}</pre></div>
            </div>
            ${item.example_prompt ? `<div class="detail-card"><h4>Example Prompt</h4><div class="detail-field"><pre style="color:var(--text);">${escHtml(item.example_prompt)}</pre></div></div>` : ''}`;
        } else if (_section === 'interactions') {
            html = `
            <div class="detail-card">
                <h4>Interaction Summary</h4>
                <div class="detail-field"><label>Name</label><div class="val">${escHtml(item.name)}</div></div>
                <div class="detail-field"><label>Description</label><div class="val" style="font-style:italic;">"${escHtml(item.description)}"</div></div>
                <div class="detail-field"><label>Group</label><div class="val">${escHtml(item.interaction_group)}</div></div>
                ${item.category ? `<div class="detail-field"><label>Category</label><div class="val">${escHtml(item.category)}</div></div>` : ''}
            </div>
            ${item.example_prompt ? `<div class="detail-card"><h4>Example Prompt</h4><div class="detail-field"><pre style="color:var(--text);">${escHtml(item.example_prompt)}</pre></div></div>` : ''}`;
        } else {
            html = `<div class="detail-card"><h4>Details</h4><div class="detail-field"><label>Name</label><div class="val">${escHtml(item.name)}</div></div><div class="detail-field"><label>ID</label><div class="val">${item.id}</div></div></div>`;
        }

        document.getElementById('previewContent').innerHTML = html;
    }

    // ── New item ──
    function newItem() {
        _isNew   = true;
        _current = null;

        showWorkspace();

        document.getElementById('wsTitle').textContent = 'New Item';
        document.getElementById('wsMeta').innerHTML = '';
        document.getElementById('previewEmpty').style.display = 'flex';
        document.getElementById('previewContent').style.display = 'none';

        document.getElementById('formTemplate').style.display    = _section === 'templates'    ? '' : 'none';
        document.getElementById('formInteraction').style.display  = _section === 'interactions' ? '' : 'none';
        document.getElementById('formSimple').style.display       = ['shot_types','camera_angles','camera_perspectives'].includes(_section) ? '' : 'none';

        // Reset forms
        if (_section === 'templates') {
            document.getElementById('t_name').value           = '';
            document.getElementById('t_core_idea').value      = '';
            document.getElementById('t_entity_type').value    = 'sketches';
            document.getElementById('t_entity_slots').value   = '["ENVIRONMENT"]';
            document.getElementById('t_tags').value           = '["tag1", "tag2"]';
            document.getElementById('t_example_prompt').value = '';
            document.getElementById('t_active').checked       = true;
        } else if (_section === 'interactions') {
            document.getElementById('i_name').value              = '';
            document.getElementById('i_description').value       = '';
            document.getElementById('i_interaction_group').value = '';
            document.getElementById('i_category').value          = '';
            document.getElementById('i_example_prompt').value    = '';
            document.getElementById('i_active').checked          = true;
        } else {
            const labelMap = { shot_types: 'Shot Type Name', camera_angles: 'Angle Name', camera_perspectives: 'Perspective Name' };
            document.getElementById('simpleNameLabel').textContent = labelMap[_section] || 'Name';
            document.getElementById('s_name').value = '';
        }

        const hasToggle = _section === 'templates' || _section === 'interactions';
        document.getElementById('btnCopyItem').style.display   = 'none';
        document.getElementById('btnToggleItem').style.display = hasToggle ? '' : 'none';

        document.getElementById('saveHint').textContent = 'Unsaved new item';
        renderSidebar();
    }

    // ── Save ──
    async function saveCurrentItem() {
        const id = _isNew ? 0 : (_current ? _current.id : 0);
        let action, data;

        if (_section === 'templates') {
            action = 'templates_save';
            data = {
                id,
                name:           document.getElementById('t_name').value.trim(),
                core_idea:      document.getElementById('t_core_idea').value.trim(),
                entity_type:    document.getElementById('t_entity_type').value,
                shot_type:      document.getElementById('t_shot_type').value,
                camera_angle:   document.getElementById('t_camera_angle').value,
                perspective:    document.getElementById('t_perspective').value,
                entity_slots:   document.getElementById('t_entity_slots').value,
                tags:           document.getElementById('t_tags').value,
                example_prompt: document.getElementById('t_example_prompt').value.trim(),
                active:         document.getElementById('t_active').checked ? 1 : 0
            };
        } else if (_section === 'interactions') {
            action = 'interactions_save';
            data = {
                id,
                name:              document.getElementById('i_name').value.trim(),
                description:       document.getElementById('i_description').value.trim(),
                interaction_group: document.getElementById('i_interaction_group').value.trim(),
                category:          document.getElementById('i_category').value.trim(),
                example_prompt:    document.getElementById('i_example_prompt').value.trim(),
                active:            document.getElementById('i_active').checked ? 1 : 0
            };
        } else {
            const actionMap = { shot_types: 'shot_types_save', camera_angles: 'camera_angles_save', camera_perspectives: 'camera_perspectives_save' };
            action = actionMap[_section];
            data   = { id, name: document.getElementById('s_name').value.trim() };
        }

        try {
            const r = await api(action, data);
            if (r.status === 'ok') {
                toast('Saved', 'success');
                const savedId = r.id || id;
                await loadSection();
                // Re-select the saved item
                const found = _allItems.find(i => i.id === savedId);
                if (found) { _current = found; _isNew = false; renderPreview(found); updateWsMeta(found); }
                renderSidebar();
                document.getElementById('saveHint').textContent = '';
            } else {
                toast(r.message || 'Save failed', 'error');
            }
        } catch(e) { toast('Save error: ' + e.message, 'error'); }
    }

    // ── Toggle ──
    async function toggleCurrentItem() {
        if (!_current) return;
        const actionMap = { templates: 'templates_toggle', interactions: 'interactions_toggle' };
        const action = actionMap[_section];
        if (!action) return;

        try {
            const r = await api(action, { id: _current.id });
            if (r.status === 'ok') {
                toast('Status toggled', 'success');
                await loadSection();
                const found = _allItems.find(i => i.id === _current.id);
                if (found) { _current = found; populateForm(found); renderPreview(found); }
                renderSidebar();
            } else { toast(r.message || 'Toggle failed', 'error'); }
        } catch(e) { toast('Error: ' + e.message, 'error'); }
    }

    // ── Copy ──
    async function copyCurrentItem() {
        if (!_current) return;
        // Load fresh copy, mutate, then switch to new form
        _isNew   = true;
        const orig = { ..._current };

        showWorkspace();
        document.getElementById('wsTitle').textContent = '[Copy] ' + orig.name;
        document.getElementById('wsMeta').innerHTML = '';

        document.getElementById('formTemplate').style.display    = _section === 'templates'    ? '' : 'none';
        document.getElementById('formInteraction').style.display  = _section === 'interactions' ? '' : 'none';
        document.getElementById('formSimple').style.display       = 'none';

        if (_section === 'templates') {
            document.getElementById('t_name').value           = '[Copy] ' + orig.name;
            document.getElementById('t_core_idea').value      = orig.core_idea || '';
            document.getElementById('t_entity_type').value    = orig.entity_type || 'sketches';
            document.getElementById('t_shot_type').value      = orig.shot_type || '';
            document.getElementById('t_camera_angle').value   = orig.camera_angle || '';
            document.getElementById('t_perspective').value    = orig.perspective || '';
            document.getElementById('t_entity_slots').value   = orig.entity_slots || '[]';
            document.getElementById('t_tags').value           = orig.tags || '[]';
            document.getElementById('t_example_prompt').value = orig.example_prompt || '';
            document.getElementById('t_active').checked       = !!parseInt(orig.active);
        } else if (_section === 'interactions') {
            document.getElementById('i_name').value              = '[Copy] ' + orig.name;
            document.getElementById('i_description').value       = orig.description || '';
            document.getElementById('i_interaction_group').value = orig.interaction_group || '';
            document.getElementById('i_category').value          = orig.category || '';
            document.getElementById('i_example_prompt').value    = orig.example_prompt || '';
            document.getElementById('i_active').checked          = !!parseInt(orig.active);
        }

        _current = null;
        document.getElementById('previewEmpty').style.display = 'flex';
        document.getElementById('previewContent').style.display = 'none';
        document.getElementById('saveHint').textContent = 'Unsaved copy';
        renderSidebar();
    }

    // ── Delete ──
    async function deleteCurrentItem() {
        if (!_current) return;
        if (!confirm('Permanently delete this item?')) return;

        const actionMap = {
            templates: 'templates_delete', interactions: 'interactions_delete',
            shot_types: 'shot_types_delete', camera_angles: 'camera_angles_delete',
            camera_perspectives: 'camera_perspectives_delete'
        };
        try {
            const r = await api(actionMap[_section], { id: _current.id });
            if (r.status === 'ok') {
                toast('Deleted', 'success');
                _current = null;
                showEmpty();
                await loadSection();
                renderSidebar();
            } else { toast(r.message || 'Delete failed', 'error'); }
        } catch(e) { toast('Error: ' + e.message, 'error'); }
    }

    // ── UI helpers ──
    function showEmpty() {
        document.getElementById('forgeEmpty').style.display = 'flex';
        document.getElementById('forgeWorkspace').style.display = 'none';
    }
    function showWorkspace() {
        document.getElementById('forgeEmpty').style.display = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';
    }

    // ── Bind search & filter events ──
    function bindSearchAndFilters() {
        let timeout;
        document.getElementById('sidebarSearch').addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(applyFilter, 180);
        });
        ['filterEntityType','filterShotType'].forEach(id => {
            document.getElementById(id).addEventListener('change', applyFilter);
        });
        ['filterGroup','filterCategory'].forEach(id => {
            document.getElementById(id).addEventListener('change', applyFilter);
        });
    }

    return {
        init, setSection,
        selectItem, newItem,
        saveCurrentItem, toggleCurrentItem, copyCurrentItem, deleteCurrentItem
    };
})();

document.addEventListener('DOMContentLoaded', () => TF.init());
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
