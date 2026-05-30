<?php
// public/scheduler_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// SCHEDULER FORGE
// World-class scheduled tasks UI based on the Forge design system.
// Includes form-based task editing, log viewers, and global locks manager.
// ─────────────────────────────────────────────────────────────────────────────
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
<title>Scheduler Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    } catch (e) {
      // Fails gracefully
    }
  })();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   FORGE — Design System
═══════════════════════════════════════════════════════════════════════════ */
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

/* -------------------------
   LIGHT THEME OVERRIDES
   ------------------------- */
@media (prefers-color-scheme: light) {
    :root {
        --bg:           #f6f8fa;
        --surface:      #e1e4e8;
        --card:         #ffffff;
        --card-hover:   #f3f4f6;
        --border:       #d1d5db;
        --border-glow:  #9ca3af;
        --text:         #111827;
        --text-dim:     #4b5563;
        --text-bright:  #000000;
        --amber:        #d97706;
        --amber-dim:    rgba(217, 119, 6, 0.1);
        --amber-mid:    rgba(217, 119, 6, 0.2);
        --amber-glow:   rgba(217, 119, 6, 0.4);
        --green:        #059669;
        --green-dim:    rgba(5, 150, 105, 0.1);
        --red:          #dc2626;
        --red-dim:      rgba(220, 38, 38, 0.1);
        --blue:         #2563eb;
        --blue-dim:     rgba(37, 99, 235, 0.1);
    }
}

:root[data-theme="light"],
html[data-theme="light"],
body[data-theme="light"] {
    --bg:           #f6f8fa;
    --surface:      #e1e4e8;
    --card:         #ffffff;
    --card-hover:   #f3f4f6;
    --border:       #d1d5db;
    --border-glow:  #9ca3af;
    --text:         #111827;
    --text-dim:     #4b5563;
    --text-bright:  #000000;
    --amber:        #d97706;
    --amber-dim:    rgba(217, 119, 6, 0.1);
    --amber-mid:    rgba(217, 119, 6, 0.2);
    --amber-glow:   rgba(217, 119, 6, 0.4);
    --green:        #059669;
    --green-dim:    rgba(5, 150, 105, 0.1);
    --red:          #dc2626;
    --red-dim:      rgba(220, 38, 38, 0.1);
    --blue:         #2563eb;
    --blue-dim:     rgba(37, 99, 235, 0.1);
}

:root[data-theme="dark"],
html[data-theme="dark"],
body[data-theme="dark"] {
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
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; overflow: hidden; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

.forge-layout { display: grid; grid-template-rows: 52px 1fr; grid-template-columns: 340px 1fr; grid-template-areas: "header header" "sidebar main"; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ── */
.forge-header { grid-area: header; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; background: var(--surface); border-bottom: 1px solid var(--border); position: relative; z-index: 100; }
.forge-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.85rem; font-weight: 700; color: var(--amber); letter-spacing: 2px; text-transform: uppercase; }
.forge-logo-icon { width: 28px; height: 28px; background: var(--amber-mid); border: 1px solid var(--amber-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; }
.forge-header-right { display: flex; align-items: center; gap: 10px; }
.forge-header-stat { display: flex; align-items: center; font-family: var(--mono); font-size: 0.7rem; color: var(--text-dim); padding: 4px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); }
.forge-header-stat span { color: var(--amber); margin-right: 4px; }

/* ── SIDEBAR ── */
.forge-sidebar { grid-area: sidebar; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
.sidebar-search { padding: 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.sidebar-search-input { width: 100%; padding: 8px 10px 8px 32px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.8rem; position: relative; transition: border-color 0.2s; }
.sidebar-search-input:focus { outline: none; border-color: var(--amber); }
.sidebar-search-wrap { position: relative; }
.sidebar-search-wrap::before { content: '⌕'; position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 16px; pointer-events: none; z-index: 1; }
.sidebar-list { flex: 1; overflow-y: auto; padding: 8px; }

.task-card { padding: 8px 10px 8px 12px; border-radius: var(--radius); border: 1px solid transparent; cursor: pointer; transition: all 0.15s; margin-bottom: 3px; position: relative; background: transparent; display: flex; align-items: center; gap: 8px; }
.task-card:hover { background: var(--card); border-color: var(--border); }
.task-card.active { background: var(--amber-dim); border-color: var(--amber); }
.task-card.active .task-card-title { color: var(--amber); }
.task-card-body { flex: 1; min-width: 0; }
.task-card-title { font-family: var(--sans); font-weight: 600; font-size: 0.85rem; color: var(--text-bright); line-height: 1.3; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: color 0.15s; }
.task-card-meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.task-card-toggle { flex-shrink: 0; width: 30px; height: 30px; border-radius: var(--radius); border: 1px solid var(--border); background: transparent; color: var(--text-dim); display: flex; align-items: center; justify-content: center; font-size: 13px; cursor: pointer; transition: all 0.15s; }
.task-card-toggle:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.task-card-toggle.is-active { border-color: var(--green); color: var(--green); background: var(--green-dim); }
.task-card-indicator { position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 2px; height: 0; background: var(--amber); border-radius: 0 2px 2px 0; transition: height 0.2s; }
.task-card.active .task-card-indicator { height: 60%; }

.gen-badge { font-family: var(--mono); font-size: 0.65rem; padding: 1px 5px; border-radius: 3px; border: 1px solid; }
.gen-badge.model { border-color: var(--border-glow); color: var(--text-dim); background: var(--card); }
.gen-badge.active { border-color: var(--green); color: var(--green); background: var(--green-dim); }
.gen-badge.inactive { border-color: var(--border); color: var(--text-dim); }
.gen-badge.public { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

.sidebar-empty { text-align: center; padding: 40px 20px; color: var(--text-dim); font-family: var(--mono); font-size: 0.8rem; }

/* ── MAIN AREA ── */
.forge-main { grid-area: main; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }
.forge-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 40px; color: var(--text-dim); }
.forge-empty-icon { font-size: 48px; opacity: 0.3; filter: grayscale(1); }
.forge-empty-title { font-family: var(--mono); font-size: 1rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; }

.forge-workspace { flex: 1; display: flex; flex-direction: column; overflow: hidden; display: none; }
.workspace-header { padding: 16px 20px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0; display: flex; align-items: flex-start; gap: 12px; }
.workspace-title-block { flex: 1; min-width: 0; }
.workspace-title { font-family: var(--sans); font-size: 1.1rem; font-weight: 700; color: var(--text-bright); line-height: 1.2; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.workspace-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.workspace-header-actions { display: flex; gap: 6px; flex-shrink: 0; }

.workspace-body { display: grid; grid-template-columns: 400px 1fr; grid-template-rows: 1fr auto; flex: 1; overflow: hidden; }

/* Params panel */
.params-panel { grid-row: 1; grid-column: 1; padding: 20px; overflow-y: auto; border-right: 1px solid var(--border); }
.panel-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
.panel-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-family: var(--mono); font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.form-label .param-type { color: var(--amber); margin-left: 4px; font-size: 0.65rem; }
.form-input, .form-select { width: 100%; padding: 9px 12px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.8rem; transition: border-color 0.15s, background 0.15s; appearance: none; }
.form-input:focus, .form-select:focus { outline: none; border-color: var(--amber); background: var(--card-hover); }
.form-select { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }

/* Generate bar */
.generate-bar { grid-row: 2; grid-column: 1; padding: 16px 20px; border-top: 1px solid var(--border); border-right: 1px solid var(--border); background: var(--surface); display: flex; gap: 8px; align-items: center; }
.btn-generate { flex: 1; padding: 12px 20px; background: var(--amber); color: #000; border: none; border-radius: var(--radius); font-family: var(--mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-generate:hover:not(:disabled) { filter: brightness(1.15); transform: translateY(-1px); }

.btn-icon-sm { width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 15px; }
.btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* Buttons inside forms and modals */
.btn-forge-primary { padding: 8px 18px; background: var(--amber); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s; }
.btn-forge-primary:hover { filter: brightness(1.1); }
.btn-forge-secondary { padding: 8px 18px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.78rem; transition: all 0.15s; }
.btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }
.btn-forge-danger { padding: 8px 18px; background: var(--red-dim); color: var(--red); border: 1px solid var(--red); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.78rem; transition: all 0.15s; }
.btn-forge-danger:hover { background: var(--red); color: #fff; }

/* Right Result Panel */
.result-panel { grid-row: 1 / 3; grid-column: 2; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }
.result-toolbar { padding: 12px 16px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; }
.result-toolbar-left { display: flex; align-items: center; gap: 8px; }
.result-tab { padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); font-family: var(--mono); font-size: 0.72rem; cursor: pointer; transition: all 0.15s; }
.result-tab.active { background: var(--amber-dim); border-color: var(--amber); color: var(--amber); }
.result-tab:hover:not(.active) { border-color: var(--border-glow); color: var(--text); }
.result-body { flex: 1; overflow: hidden; padding: 16px; display: flex; flex-direction: column; }
.result-view { display: none; height: 100%; overflow: hidden; }
.result-view.active { display: flex; flex-direction: column; }

/* Tables */
.table-responsive { width: 100%; overflow: auto; max-height: 100%; }
.forge-table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 0.75rem; text-align: left; }
.forge-table th { padding: 8px 12px; border-bottom: 1px solid var(--border); color: var(--text-dim); font-weight: normal; text-transform: uppercase; letter-spacing: 1px; }
.forge-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text); }
.forge-table tr:hover td { background: var(--card-hover); }
.status-badge { padding: 2px 6px; border-radius: 3px; font-size: 0.65rem; text-transform: uppercase; border: 1px solid; }
.status-badge.completed { color: var(--green); border-color: var(--green); background: var(--green-dim); }
.status-badge.failed { color: var(--red); border-color: var(--red); background: var(--red-dim); }
.status-badge.running { color: var(--amber); border-color: var(--amber); background: var(--amber-dim); }
.status-badge.pending { color: var(--blue); border-color: var(--blue); background: var(--blue-dim); }
.status-badge.stale { color: var(--text-dim); border-color: var(--border); background: var(--card); }

/* Raw log container */
.result-raw { flex: 1; font-family: var(--mono); font-size: 0.75rem; line-height: 1.6; white-space: pre-wrap; word-break: break-word; padding: 14px; border-radius: var(--radius); overflow-y: auto; }

/* Special links */
.entity-link { cursor: pointer; transition: all 0.15s; }
.entity-link:hover strong { color: var(--amber); text-decoration: underline; }

/* ── MODALS ── */
.forge-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 16px; }
.forge-modal-overlay.open { display: flex; }
.forge-modal { background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius-lg); width: 100%; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.6); animation: modalIn 0.2s ease; }
.forge-modal-header { padding: 18px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.forge-modal-title { font-family: var(--mono); font-size: 0.8rem; font-weight: 700; color: var(--amber); text-transform: uppercase; letter-spacing: 1.5px; }
.forge-modal-close { width: 28px; height: 28px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.forge-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.forge-modal-body { padding: 20px; overflow-y: auto; flex: 1; }

/* ── TOAST ── */
.forge-toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.forge-toast { padding: 10px 16px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.8rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); animation: toastIn 0.25s ease; pointer-events: all; cursor: pointer; max-width: 320px; display: flex; align-items: center; gap: 8px; }
.forge-toast.success { border-color: var(--green); }
.forge-toast.error   { border-color: var(--red); color: var(--red); }
.forge-toast.info    { border-color: var(--amber); }
.forge-toast.out     { animation: toastOut 0.25s ease forwards; }

@keyframes toastIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(10px); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-10px); } to { opacity: 1; transform: none; } }

@media (max-width: 900px) {
    .workspace-body { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
    .generate-bar { grid-row: 2; border-right: none; }
    .result-panel { grid-row: 3; grid-column: 1; border-top: 1px solid var(--border); }
    .forge-layout { grid-template-columns: 1fr; grid-template-rows: 52px 200px 1fr; grid-template-areas: "header" "sidebar" "main"; }
    .forge-sidebar { border-right: none; border-bottom: 1px solid var(--border); }
}

input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
</head>
<body>

<div class="forge-layout">

    <!-- HEADER -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-stopwatch"></i></div>
            <!-- Removed the text 'Scheduler Forge' to save space for new buttons -->
        </div>
        <div class="forge-header-right">
            <div class="forge-header-stat" title="Heartbeat indicator">
                <div id="heartbeatLed" style="width:8px; height:8px; border-radius:50%; background:var(--red); display:inline-block; margin-right:6px; box-shadow: 0 0 5px var(--red);"></div>
                <span id="statCount">—</span> tasks
            </div>
            <button class="btn-icon-sm" onclick="SchedulerForge.openQueueModal()" title="Map Run Queue">
                <i class="bi bi-list-task"></i>
            </button>
            <button class="btn-icon-sm" onclick="SchedulerForge.openGlobalLogsModal()" title="Global Logs Viewer">
                <i class="bi bi-terminal"></i>
            </button>
            <button class="btn-icon-sm" onclick="SchedulerForge.openGlobalLocksModal()" title="Global Locks Manager">
                <i class="bi bi-lock-fill"></i>
            </button>
            <button class="btn-icon-sm" onclick="SchedulerForge.newTask()" title="New Task">
                <i class="bi bi-plus-lg"></i>
            </button>
            <a href="/dashboard.php" class="btn-icon-sm" title="Back to Dashboard" style="text-decoration:none;">
                <i class="bi bi-house"></i>
            </a>
        </div>
    </header>

    <!-- SIDEBAR -->
    <aside class="forge-sidebar">
        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="sidebarSearch" placeholder="Search tasks…" autocomplete="off">
            </div>
        </div>
        <div class="sidebar-list" id="sidebarList">
            <div class="sidebar-empty">
                <div style="font-size:2rem; margin-bottom:8px;"><i class="bi bi-hourglass-split"></i></div>
                Loading tasks…
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="forge-main" id="forgeMain">

        <!-- Empty state -->
        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon"><i class="bi bi-calendar-range"></i></div>
            <div class="forge-empty-title">Select a Task</div>
            <div class="forge-empty-sub">Choose from the sidebar to inspect or edit</div>
        </div>

        <!-- Workspace -->
        <div class="forge-workspace" id="forgeWorkspace">
            <div class="workspace-header">
                <div class="workspace-title-block">
                    <div class="workspace-title" id="wsTitle">—</div>
                    <div class="workspace-meta" id="wsMeta"></div>
                </div>
                <div class="workspace-header-actions">
                    <button class="btn-icon-sm" id="btnDeleteTask" onclick="SchedulerForge.deleteTask()" title="Delete Task">
                        <i class="bi bi-trash" style="color:var(--red);"></i>
                    </button>
                </div>
            </div>

            <div class="workspace-body">
                <!-- PARAMS PANEL -->
                <div class="params-panel" id="paramsPanel">
                    <div class="panel-label">Basic Configuration</div>
                    <div class="form-group">
                        <label class="form-label">Task Name</label>
                        <input type="text" id="task_name" class="form-input" placeholder="e.g. Daily Cleanup">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Script Path</label>
                        <input type="text" id="task_script_path" class="form-input" placeholder="e.g. php bash/cleanup.php">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Arguments</label>
                        <input type="text" id="task_args" class="form-input" placeholder="e.g. --force">
                    </div>
                    
                    <div class="panel-label" style="margin-top:24px;">Schedule Rules</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Schedule Time <span class="param-type">HH:MM:SS UTC</span></label>
                            <input type="time" step="1" id="task_schedule_time" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interval <span class="param-type">Seconds</span></label>
                            <input type="number" id="task_schedule_interval" class="form-input" placeholder="e.g. 3600">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Days of Week (DOW) <span class="param-type">0-6 (Sun-Sat)</span></label>
                        <input type="text" id="task_schedule_dow" class="form-input" placeholder="0,1,2,3,4,5,6">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-family:var(--mono); font-size:0.75rem; color:var(--text); margin-top:24px;">
                                <input type="checkbox" id="task_active"> Enable Automatic Schedule
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">List Order</label>
                            <input type="number" id="task_order" class="form-input" value="0">
                        </div>
                    </div>

                    <div class="panel-label" style="margin-top:24px;">Concurrency & Locks</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-family:var(--mono); font-size:0.75rem; color:var(--text); margin-top:24px;">
                                <input type="checkbox" id="task_require_lock"> Require Lock Mutex
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lock Scope</label>
                            <select id="task_lock_scope" class="form-select">
                                <option value="global">Global (Task Wide)</option>
                                <option value="entity">Entity (Per Param)</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Lock Timeout (Min)</label>
                            <input type="number" id="task_lock_timeout_minutes" class="form-input" value="60">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Concurrent Runs</label>
                            <input type="number" id="task_max_concurrent_runs" class="form-input" value="1">
                        </div>
                    </div>
                </div>

                <!-- GENERATE BAR -->
                <div class="generate-bar">
                    <button class="btn-generate" onclick="SchedulerForge.saveTask()">
                        <span class="btn-label"><i class="bi bi-floppy"></i> SAVE TASK</span>
                    </button>
                    <button class="btn-forge-secondary" id="btnRunNow" onclick="SchedulerForge.runTaskNow()" style="display:flex; align-items:center; gap:8px;">
                        <i class="bi bi-play-fill" style="color:var(--green); font-size:1.1rem;"></i> RUN NOW
                    </button>
                </div>

                <!-- RESULT PANEL -->
                <div class="result-panel">
                    <div class="result-toolbar">
                        <div class="result-toolbar-left">
                            <button class="result-tab active" data-view="runs">Runs History</button>
                            <button class="result-tab" data-view="locks">Active Locks</button>
                            <button class="result-tab" data-view="log">Log Viewer</button>
                        </div>
                        <div>
                            <button class="btn-icon-sm" onclick="SchedulerForge.refreshRightPanel()" title="Refresh Panel">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>

                    <div class="result-body">
                        <!-- Runs View -->
                        <div class="result-view active" id="viewRuns">
                            <div class="table-responsive">
                                <table class="forge-table">
                                    <thead>
                                        <tr>
                                            <th>Run ID</th>
                                            <th>Status</th>
                                            <th>PID</th>
                                            <th>Started (UTC)</th>
                                            <th>Duration</th>
                                            <th>Exit</th>
                                            <th>Logs</th>
                                        </tr>
                                    </thead>
                                    <tbody id="runsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Locks View -->
                        <div class="result-view" id="viewLocks">
                            <div class="table-responsive">
                                <table class="forge-table">
                                    <thead>
                                        <tr>
                                            <th>Lock Key</th>
                                            <th>Acquired (UTC)</th>
                                            <th>Expires (UTC)</th>
                                            <th>PID</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="locksTableBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Log Viewer View -->
                        <div class="result-view" id="viewLog">
                            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                                <div style="font-family:var(--mono); font-size:0.8rem; color:var(--text-bright);">
                                    Viewing Run #<span id="logRunId" style="color:var(--amber);">—</span>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <select id="logTypeSelect" class="form-select" style="padding:4px 28px 4px 8px; font-size:0.75rem;" onchange="SchedulerForge.refreshLog()">
                                        <option value="stdout">STDOUT</option>
                                        <option value="stderr">STDERR</option>
                                    </select>
                                </div>
                            </div>
                            <pre class="result-raw" id="logContent" style="background:#050505; color:#0f0; border-color:#222;">Select a run from the history to view its logs.</pre>
                        </div>
                    </div>
                </div>

            </div><!-- /workspace-body -->
        </div><!-- /forge-workspace -->
    </main>
</div><!-- /forge-layout -->

<!-- ── GLOBAL LOCKS MODAL ── -->
<div class="forge-modal-overlay" id="globalLocksModal">
    <div class="forge-modal" style="max-width:900px; max-height:80vh;">
        <div class="forge-modal-header">
            <div class="forge-modal-title">Global Lock Manager</div>
            <div style="display:flex; gap:8px;">
                <button class="btn-forge-secondary" onclick="SchedulerForge.cleanupLocks()">Cleanup Expired</button>
                <button class="forge-modal-close" onclick="SchedulerForge.closeModal('globalLocksModal')"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="forge-modal-body" style="padding:0; overflow-y:hidden; display:flex; flex-direction:column;">
            <div class="table-responsive" style="flex:1;">
                <table class="forge-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Lock Key</th>
                            <th>Age</th>
                            <th>TTL</th>
                            <th>PID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="globalLocksTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── GLOBAL LOGS MODAL ── -->
<div class="forge-modal-overlay" id="globalLogsModal">
    <div class="forge-modal" style="max-width:950px; height: 85vh;">
        <div class="forge-modal-header" style="flex-wrap: wrap; gap: 10px;">
            <div class="forge-modal-title" style="display:flex; align-items:center; gap:8px;">
                <i class="bi bi-terminal" style="font-size:1.1rem;"></i> Global Logs Viewer
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex:1; justify-content: flex-end;">
                <select id="globalLogFileSelect" class="form-select" style="max-width:300px; padding:4px 28px 4px 8px; font-size:0.75rem;" onchange="SchedulerForge.changeGlobalLogFile()"></select>
                <button class="btn-forge-secondary" id="btnToggleLogRefresh" onclick="SchedulerForge.toggleLogRefresh()" style="width:36px; height:30px; padding:0; display:flex; justify-content:center; align-items:center;" title="Toggle Auto-Refresh">
                    <i class="bi bi-pause-fill"></i>
                </button>
                <button class="forge-modal-close" onclick="SchedulerForge.closeGlobalLogsModal()"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="forge-modal-body" style="padding:0; overflow:hidden; display:flex; flex-direction:column; background:#050505;">
            <pre id="globalLogContent" style="margin:0; padding:15px; flex:1; overflow-y:auto; color:#0f0; font-family:var(--mono); font-size:13px; white-space:pre-wrap; word-break:break-all;">Loading...</pre>
        </div>
    </div>
</div>

<!-- ── TOAST CONTAINER ── -->
<div class="forge-toast-container" id="toastContainer"></div>

<!-- ── MAIN JS ── -->
<script>
const SchedulerForge = (() => {
    'use strict';

    const API = '/api/scheduler_forge_api.php';

    let _tasks =[];
    let _currentTask = null;
    let _currentRuns =[];
    let _currentLocks =[];
    let _isNewTask = false;
    let _currentLogRunId = null;
    let _searchTimeout = null;
    
    // Global Logs state
    let _logRefreshInterval = null;
    let _logFileListInterval = null;
    let _logAutoRefresh = true;
    let _currentGlobalLogFile = '';

    async function api(action, data = {}) {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data })
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    function toast(msg, type = 'info', duration = 3000) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        const icons = { success: '✓', error: '✕', info: '◆' };
        el.innerHTML = `<span style="font-size:12px;">${icons[type] || '◆'}</span> ${msg}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        const dismiss = (e) => { e.classList.add('out'); setTimeout(() => e.remove(), 300); };
        setTimeout(() => dismiss(el), duration);
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    async function init() {
        await loadTasks();
        bindEvents();
        setInterval(checkHeartbeat, 10000);
        checkHeartbeat();
    }

    async function loadTasks() {
        const r = await api('list_tasks');
        if (r.ok) {
            _tasks = r.data ||[];
            document.getElementById('statCount').textContent = _tasks.length;
            renderSidebar();
        } else {
            toast('Failed to load tasks', 'error');
        }
    }

    function renderSidebar() {
        const container = document.getElementById('sidebarList');
        const search = document.getElementById('sidebarSearch').value.toLowerCase().trim();
        const filtered = search ? _tasks.filter(t => t.name.toLowerCase().includes(search) || t.id.toString() === search) : _tasks;

        if (filtered.length === 0) {
            container.innerHTML = `<div class="sidebar-empty"><div style="font-size:2rem; margin-bottom:8px;"><i class="bi bi-clock-history"></i></div>No tasks found</div>`;
            return;
        }

        container.innerHTML = filtered.map(t => {
            const isActive = _currentTask && _currentTask.id === t.id;
            const lastRun = t.last_run ? t.last_run : 'Never';
            const badges =[];

            // Active State
            badges.push(`<span class="gen-badge ${t.active ? 'active' : 'inactive'}">${t.active ? 'ACTIVE' : 'INACTIVE'}</span>`);

            // Locks
            if (t.active_locks > 0) {
                badges.push(`<span class="gen-badge public" style="color:var(--amber); border-color:var(--amber); background:var(--amber-dim);">🔒 ${t.active_locks} ACTIVE</span>`);
            } else if (t.require_lock) {
                badges.push(`<span class="gen-badge public" style="border-color:var(--amber); color:var(--amber); background:var(--amber-dim);">🔒 REQ</span>`);
            }

            // Scope
            if (t.lock_scope && t.lock_scope !== 'none') {
                badges.push(`<span class="gen-badge model">Scope: ${escHtml(t.lock_scope)}</span>`);
            }

            // ID & Last Run combined pill
            badges.push(`<span class="gen-badge model">ID: ${t.id} <span style="font-size:0.9em; opacity:0.75; margin-left:3px; padding-left:3px; border-left:1px solid var(--border-glow);">Last: ${lastRun}</span></span>`);

            // Pending execution flag
            if (t.run_now == 1) {
                badges.push(`<span class="gen-badge" style="background:var(--blue-dim); border-color:var(--blue); color:var(--blue);">PENDING</span>`);
            }

            const toggleCls = t.active ? 'is-active' : '';
            const toggleIcon = t.active ? '<i class="bi bi-pause-fill"></i>' : '<i class="bi bi-play-fill"></i>';
            const toggleTitle = t.active ? 'Active — click to deactivate' : 'Inactive — click to activate';

            return `
            <div class="task-card${isActive ? ' active' : ''}" onclick="SchedulerForge.selectTask(${t.id})">
                <div class="task-card-indicator"></div>
                <div class="task-card-body">
                    <div class="task-card-title">${escHtml(t.name)}</div>
                    <div class="task-card-meta">${badges.join('')}</div>
                </div>
                <button class="task-card-toggle" title="Duplicate task" onclick="event.stopPropagation(); SchedulerForge.copyTask(${t.id}, this)">
                    <i class="bi bi-copy"></i>
                </button>
                <button class="task-card-toggle ${toggleCls}" title="${toggleTitle}" onclick="event.stopPropagation(); SchedulerForge.toggleTask(${t.id}, this)">
                    ${toggleIcon}
                </button>
            </div>`;
        }).join('');
    }

    async function selectTask(id) {
        _isNewTask = false;
        const r = await api('get_task', { id });
        if (!r.ok) { toast('Failed to load task', 'error'); return; }
        
        _currentTask = r.data.task;
        _currentRuns = r.data.runs;
        _currentLocks = r.data.locks;
        
        document.getElementById('forgeEmpty').style.display = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';
        document.getElementById('btnRunNow').style.display = 'flex';
        document.getElementById('btnDeleteTask').style.display = 'inline-flex';
        
        document.getElementById('wsTitle').textContent = _currentTask.name;
        updateWorkspaceMeta();
        
        document.getElementById('task_name').value = _currentTask.name || '';
        document.getElementById('task_script_path').value = _currentTask.script_path || '';
        document.getElementById('task_args').value = _currentTask.args || '';
        document.getElementById('task_schedule_time').value = _currentTask.schedule_time || '';
        document.getElementById('task_schedule_interval').value = _currentTask.schedule_interval || '';
        document.getElementById('task_schedule_dow').value = _currentTask.schedule_dow || '0,1,2,3,4,5,6';
        document.getElementById('task_active').checked = !!_currentTask.active;
        document.getElementById('task_order').value = _currentTask.order || 0;
        
        document.getElementById('task_require_lock').checked = !!_currentTask.require_lock;
        document.getElementById('task_lock_scope').value = _currentTask.lock_scope || 'global';
        document.getElementById('task_lock_timeout_minutes').value = _currentTask.lock_timeout_minutes || 60;
        document.getElementById('task_max_concurrent_runs').value = _currentTask.max_concurrent_runs || 1;
        
        renderRuns();
        renderLocks();
        renderSidebar();

        if (document.querySelector('.result-tab[data-view="log"]').classList.contains('active')) {
            refreshLog();
        } else {
            document.querySelectorAll('.result-tab').forEach(b => b.classList.remove('active'));
            document.querySelector('.result-tab[data-view="runs"]').classList.add('active');
            activateResultView('runs');
        }
    }

    function updateWorkspaceMeta() {
        if (!_currentTask) return;
        const meta = document.getElementById('wsMeta');
        const lastRun = _currentTask.last_run ? _currentTask.last_run : 'Never';
        meta.innerHTML = `
            <span class="gen-badge ${_currentTask.active ? 'active' : 'inactive'}">${_currentTask.active ? 'ACTIVE' : 'INACTIVE'}</span>
            ${_currentTask.require_lock ? '<span class="gen-badge public" style="border-color:var(--amber); color:var(--amber); background:var(--amber-dim);">🔒 REQUIRED</span>' : ''}
            <span class="gen-badge model">Scope: ${escHtml(_currentTask.lock_scope)}</span>
            <span class="gen-badge model">Last: ${lastRun}</span>
        `;
    }

    function newTask() {
        _isNewTask = true;
        _currentTask = null;
        document.getElementById('forgeEmpty').style.display = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';
        document.getElementById('wsTitle').textContent = 'New Scheduled Task';
        document.getElementById('wsMeta').innerHTML = '';
        
        document.getElementById('task_name').value = 'New Script Runner';
        document.getElementById('task_script_path').value = '';
        document.getElementById('task_args').value = '';
        document.getElementById('task_schedule_time').value = '';
        document.getElementById('task_schedule_interval').value = '';
        document.getElementById('task_schedule_dow').value = '0,1,2,3,4,5,6';
        document.getElementById('task_active').checked = true;
        document.getElementById('task_order').value = 0;
        document.getElementById('task_require_lock').checked = true;
        document.getElementById('task_lock_scope').value = 'global';
        document.getElementById('task_lock_timeout_minutes').value = 60;
        document.getElementById('task_max_concurrent_runs').value = 1;
        
        document.getElementById('btnRunNow').style.display = 'none';
        document.getElementById('btnDeleteTask').style.display = 'none';
        
        document.getElementById('runsTableBody').innerHTML = '<tr><td colspan="7" style="text-align:center;">Save task first</td></tr>';
        document.getElementById('locksTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center;">Save task first</td></tr>';
        document.getElementById('logContent').textContent = 'Save task first to view logs';
        
        document.querySelectorAll('.task-card').forEach(c => c.classList.remove('active'));
    }

    async function saveTask() {
        if (!_currentTask && !_isNewTask) return;
        const data = {
            name: document.getElementById('task_name').value.trim(),
            script_path: document.getElementById('task_script_path').value.trim(),
            args: document.getElementById('task_args').value.trim(),
            schedule_time: document.getElementById('task_schedule_time').value,
            schedule_interval: document.getElementById('task_schedule_interval').value,
            schedule_dow: document.getElementById('task_schedule_dow').value.trim(),
            active: document.getElementById('task_active').checked,
            order: document.getElementById('task_order').value,
            require_lock: document.getElementById('task_require_lock').checked,
            lock_scope: document.getElementById('task_lock_scope').value,
            lock_timeout_minutes: document.getElementById('task_lock_timeout_minutes').value,
            max_concurrent_runs: document.getElementById('task_max_concurrent_runs').value
        };
        
        if (!data.name) { toast('Task name required', 'error'); return; }
        
        const id = _isNewTask ? 0 : _currentTask.id;
        const r = await api('save_task', { id, task: data });
        if (r.ok) {
            toast('Task saved', 'success');
            await loadTasks();
            selectTask(r.data.id);
        } else {
            toast(r.error || 'Save failed', 'error');
        }
    }

    async function runTaskNow() {
        if (!_currentTask) return;
        document.getElementById('btnRunNow').disabled = true;
        try {
            const r = await api('run_task', { id: _currentTask.id });
            if (r.ok) {
                toast('Triggered! The scheduler daemon will pick it up momentarily.', 'success');
                setTimeout(() => selectTask(_currentTask.id), 1500);
            } else {
                toast('Trigger failed', 'error');
            }
        } catch(e) {}
        document.getElementById('btnRunNow').disabled = false;
    }

    async function deleteTask() {
        if (!_currentTask || !confirm('Permanently delete this task?')) return;
        const r = await api('delete_task', { id: _currentTask.id });
        if (r.ok) {
            toast('Task deleted', 'success');
            _currentTask = null;
            document.getElementById('forgeEmpty').style.display = 'flex';
            document.getElementById('forgeWorkspace').style.display = 'none';
            loadTasks();
        } else {
            toast('Delete failed', 'error');
        }
    }

    async function copyTask(id, btnEl) {
        btnEl.disabled = true;
        const r = await api('copy_task', { id });
        if (r.ok) {
            toast('Duplicated!', 'success');
            await loadTasks();
            selectTask(r.data.new_id);
        } else {
            toast('Copy failed', 'error');
        }
        btnEl.disabled = false;
    }

    async function toggleTask(id, btnEl) {
        btnEl.disabled = true;
        const r = await api('toggle_task', { id });
        if (r.ok) {
            toast(r.data.active ? 'Task Enabled' : 'Task Disabled', 'success');
            if (_currentTask && _currentTask.id === id) {
                document.getElementById('task_active').checked = !!r.data.active;
                _currentTask.active = r.data.active;
                updateWorkspaceMeta();
            }
            loadTasks();
        }
        btnEl.disabled = false;
    }

    function formatDuration(start, end) {
        if (!start) return '-';
        const s = new Date(start + ' Z');
        const e = end ? new Date(end + ' Z') : new Date();
        const diff = Math.max(0, Math.floor((e - s) / 1000));
        if (diff < 60) return diff + 's';
        return Math.floor(diff / 60) + 'm ' + (diff % 60) + 's';
    }

    function renderRuns() {
        const tbody = document.getElementById('runsTableBody');
        if (!_currentRuns || _currentRuns.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--text-dim);">No recent runs found</td></tr>';
            return;
        }
        tbody.innerHTML = _currentRuns.map(r => {
            let badgeCls = 'pending';
            if (r.status === 'completed') badgeCls = 'completed';
            else if (r.status === 'failed') badgeCls = 'failed';
            else if (r.status === 'running') badgeCls = 'running';
            else if (r.status === 'stale') badgeCls = 'stale';
            
            return `
            <tr>
                <td>#${r.id}</td>
                <td><span class="status-badge ${badgeCls}">${r.status}</span></td>
                <td>${r.pid || '-'}</td>
                <td>${r.started_at}</td>
                <td>${formatDuration(r.started_at, r.finished_at)}</td>
                <td>${r.exit_code !== null ? r.exit_code : '-'}</td>
                <td>
                    <button class="btn-icon-sm" style="width:28px;height:28px;font-size:12px;" onclick="SchedulerForge.viewLog(${r.id})" title="View Log">
                        <i class="bi bi-file-text"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderLocks() {
        const tbody = document.getElementById('locksTableBody');
        if (!_currentLocks || _currentLocks.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-dim);">No active locks</td></tr>';
            return;
        }
        tbody.innerHTML = _currentLocks.map(l => `
            <tr>
                <td><span style="font-family:monospace; font-size:0.7rem; background:var(--bg); padding:2px 4px; border-radius:3px;">${l.lock_key}</span></td>
                <td>${l.acquired_at}</td>
                <td>${l.expires_at}</td>
                <td>${l.pid || '-'}</td>
                <td><button class="btn-forge-danger" style="padding:4px 8px; font-size:0.7rem;" onclick="SchedulerForge.releaseLock(${l.id})">Release</button></td>
            </tr>
        `).join('');
    }

    async function openGlobalLocksModal() {
        const r = await api('get_global_locks');
        if (r.ok) {
            const tbody = document.getElementById('globalLocksTableBody');
            if (r.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-dim); padding:30px;">All clear. No active locks found.</td></tr>';
            } else {
                tbody.innerHTML = r.data.map(l => `
                <tr>
                    <td><strong>${escHtml(l.task_name)}</strong> <br><small>#${l.task_id}</small></td>
                    <td><span style="font-family:monospace; font-size:0.7rem; background:var(--bg); padding:2px 4px; border-radius:3px;">${l.lock_key}</span></td>
                    <td>${l.age_seconds}s</td>
                    <td>${l.ttl_seconds > 0 ? l.ttl_seconds + 's' : '<span style="color:var(--red);">Expired</span>'}</td>
                    <td>${l.pid || '-'}</td>
                    <td><button class="btn-forge-danger" style="padding:4px 8px; font-size:0.7rem;" onclick="SchedulerForge.releaseLock(${l.id}, true)">Release</button></td>
                </tr>`).join('');
            }
            document.getElementById('globalLocksModal').classList.add('open');
        }
    }

    // ── Global Logs Manager ──

    async function openGlobalLogsModal() {
        document.getElementById('globalLogsModal').classList.add('open');
        await fetchLogFiles();
        await refreshGlobalLog(true);
        if (_logAutoRefresh) {
            startLogIntervals();
        }
    }

    function closeGlobalLogsModal() {
        document.getElementById('globalLogsModal').classList.remove('open');
        stopLogIntervals();
    }

    function startLogIntervals() {
        if (!_logRefreshInterval) _logRefreshInterval = setInterval(refreshGlobalLog, 2000);
        if (!_logFileListInterval) _logFileListInterval = setInterval(fetchLogFiles, 10000);
    }

    function stopLogIntervals() {
        if (_logRefreshInterval) { clearInterval(_logRefreshInterval); _logRefreshInterval = null; }
        if (_logFileListInterval) { clearInterval(_logFileListInterval); _logFileListInterval = null; }
    }

    async function fetchLogFiles() {
        try {
            const r = await api('list_log_files');
            if (r.ok && r.data.length > 0) {
                const sel = document.getElementById('globalLogFileSelect');
                const currentVal = sel.value;
                sel.innerHTML = r.data.map(f => `<option value="${f}">${f}</option>`).join('');
                
                if (currentVal && r.data.includes(currentVal)) {
                    sel.value = currentVal;
                } else if (!_currentGlobalLogFile || !r.data.includes(_currentGlobalLogFile)) {
                    _currentGlobalLogFile = r.data[0];
                    sel.value = _currentGlobalLogFile;
                }
            } else {
                document.getElementById('globalLogFileSelect').innerHTML = '<option value="">No logs found</option>';
                _currentGlobalLogFile = '';
            }
        } catch(e) {}
    }

    async function refreshGlobalLog(forceScroll = false) {
        if (!_currentGlobalLogFile) return;
        try {
            const r = await api('fetch_log_file', { file: _currentGlobalLogFile });
            if (r.ok) {
                const box = document.getElementById('globalLogContent');
                const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 20;
                box.textContent = r.data.content;
                if (_logAutoRefresh && (nearBottom || forceScroll)) {
                    box.scrollTop = box.scrollHeight;
                }
            }
        } catch(e) {}
    }

    function changeGlobalLogFile() {
        _currentGlobalLogFile = document.getElementById('globalLogFileSelect').value;
        const box = document.getElementById('globalLogContent');
        box.textContent = 'Loading...';
        refreshGlobalLog(true);
    }

    function toggleLogRefresh() {
        _logAutoRefresh = !_logAutoRefresh;
        const btn = document.getElementById('btnToggleLogRefresh');
        if (_logAutoRefresh) {
            btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            btn.classList.add('btn-forge-secondary');
            startLogIntervals();
            refreshGlobalLog(true);
        } else {
            btn.innerHTML = '<i class="bi bi-play-fill"></i>';
            stopLogIntervals();
        }
    }
    
    // ── Global Queue Viewer (Iframe integration) ──

    function openQueueModal() {
        if (window.showIframeModal) {
            window.showIframeModal('/view_queue.php?embed=1', 'Loading Map Run Queue...');
        }
    }

    async function releaseLock(id, isGlobal = false) {
        if (!confirm('Force release this lock?')) return;
        const r = await api('release_lock', { lock_id: id });
        if (r.ok) {
            toast('Lock released', 'success');
            if (isGlobal) openGlobalLocksModal();
            else if (_currentTask) selectTask(_currentTask.id);
        } else {
            toast('Release failed', 'error');
        }
    }

    async function cleanupLocks() {
        const r = await api('cleanup_locks');
        if (r.ok) {
            toast(`Cleaned up ${r.data.count} expired locks`, 'success');
            openGlobalLocksModal();
        }
    }

    function viewLog(runId) {
        _currentLogRunId = runId;
        document.getElementById('logRunId').textContent = runId;
        document.querySelectorAll('.result-tab').forEach(b => b.classList.remove('active'));
        document.querySelector('.result-tab[data-view="log"]').classList.add('active');
        activateResultView('log');
        refreshLog();
    }

    async function refreshLog() {
        if (!_currentLogRunId) return;
        const type = document.getElementById('logTypeSelect').value;
        const box = document.getElementById('logContent');
        box.textContent = 'Fetching logs...';
        const r = await api('get_log', { run_id: _currentLogRunId, type });
        if (r.ok) {
            box.textContent = r.data.content;
            box.scrollTop = box.scrollHeight;
        } else {
            box.textContent = 'Error: ' + r.error;
        }
    }

    function refreshRightPanel() {
        if (_currentTask) selectTask(_currentTask.id);
    }

    async function checkHeartbeat() {
        try {
            const r = await api('heartbeat');
            if (r.ok) {
                let lastSeen = new Date(r.data.last_seen + ' Z');
                let serverNow = new Date(r.data.server_time + ' Z');
                let diffSec = (serverNow - lastSeen) / 1000;
                const led = document.getElementById('heartbeatLed');
                if (diffSec <= 15) {
                    led.style.background = 'var(--green)';
                    led.style.boxShadow = '0 0 5px var(--green)';
                } else {
                    led.style.background = 'var(--red)';
                    led.style.boxShadow = '0 0 5px var(--red)';
                }
            }
        } catch(e) {}
    }

    function activateResultView(view) {['viewRuns', 'viewLocks', 'viewLog'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = (id === 'view' + view.charAt(0).toUpperCase() + view.slice(1)) ? 'block' : 'none';
        });
    }

    function bindEvents() {
        document.getElementById('sidebarSearch').addEventListener('input', () => {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(renderSidebar, 200);
        });

        document.querySelectorAll('.result-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.result-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activateResultView(btn.dataset.view);
            });
        });

        document.querySelectorAll('.forge-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('open');
                    if (overlay.id === 'globalLogsModal') stopLogIntervals();
                }
            });
        });
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        if (id === 'globalLogsModal') stopLogIntervals();
    }

    return {
        init, selectTask, newTask, saveTask, runTaskNow, deleteTask, 
        copyTask, toggleTask, openGlobalLocksModal, releaseLock, 
        cleanupLocks, viewLog, refreshLog, refreshRightPanel, closeModal,
        openGlobalLogsModal, closeGlobalLogsModal, changeGlobalLogFile, toggleLogRefresh,
        openQueueModal
    };

})();

document.addEventListener('DOMContentLoaded', () => SchedulerForge.init());
</script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>