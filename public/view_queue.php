<?php
// public/view_queue.php
// ─────────────────────────────────────────────────────────────────────────────
// SCHEDULER FORGE - STANDALONE QUEUE VIEWER
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

$viewportScale = !empty($_GET['embed']) ? '0.8' : '0.8';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Map Run Queue</title>
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
   FORGE — Design System (Queue Standalone)
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
    --green:        #22d3a0;
    --green-dim:    rgba(34, 211, 160, 0.1);
    --red:          #f05060;
    --red-dim:      rgba(240, 80, 96, 0.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77, 166, 255, 0.1);
    --mono:         'Space Mono', 'Fira Mono', monospace;
    --sans:         'Syne', system-ui, sans-serif;
    --radius:       6px;
}

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
        --green:        #059669;
        --green-dim:    rgba(5, 150, 105, 0.1);
        --red:          #dc2626;
        --red-dim:      rgba(220, 38, 38, 0.1);
        --blue:         #2563eb;
        --blue-dim:     rgba(37, 99, 235, 0.1);
    }
}

:root[data-theme="light"], html[data-theme="light"], body[data-theme="light"] {
    --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6;
    --border: #d1d5db; --border-glow: #9ca3af; --text: #111827; --text-dim: #4b5563;
    --text-bright: #000000; --amber: #d97706; --green: #059669; --green-dim: rgba(5, 150, 105, 0.1);
    --red: #dc2626; --red-dim: rgba(220, 38, 38, 0.1); --blue: #2563eb; --blue-dim: rgba(37, 99, 235, 0.1);
}

:root[data-theme="dark"], html[data-theme="dark"], body[data-theme="dark"] {
    --bg: #080b10; --surface: #0e1319; --card: #111820; --card-hover: #141e28;
    --border: #1c2535; --border-glow: #2a3a52; --text: #c8d4e8; --text-dim: #5a6a80;
    --text-bright: #e8f0ff; --amber: #f5a623; --green: #22d3a0; --green-dim: rgba(34, 211, 160, 0.1);
    --red: #f05060; --red-dim: rgba(240, 80, 96, 0.1); --blue: #4da6ff; --blue-dim: rgba(77, 166, 255, 0.1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.5; overflow: hidden; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

.forge-layout { display: flex; flex-direction: column; height: 100vh; }

/* Header */
.forge-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; background: var(--surface); border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; flex-shrink: 0; }
.forge-header-title { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 1.1rem; font-weight: 700; color: var(--amber); text-transform: uppercase; letter-spacing: 1.5px; }
.forge-header-actions { display: flex; gap: 8px; align-items: center; flex: 1; justify-content: flex-end; flex-wrap: wrap; }

/* Buttons */
.btn-forge-secondary { padding: 4px 12px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.78rem; transition: all 0.15s; display: flex; align-items: center; gap: 6px; }
.btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }
.btn-icon-sm { width: 32px; height: 32px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); }

/* Main Content */
.forge-body { flex: 1; padding: 20px; overflow: hidden; display: flex; flex-direction: column; gap: 12px; }
.table-wrapper { flex: 1; overflow: auto; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); }

/* Table */
.forge-table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 0.75rem; text-align: left; }
.forge-table th { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text-dim); font-weight: normal; text-transform: uppercase; letter-spacing: 1px; background: var(--surface); position: sticky; top: 0; z-index: 10; }
.forge-table td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
.forge-table tr:hover td { background: var(--card-hover); }

/* Checkbox column */
.forge-table th.col-check,
.forge-table td.col-check { width: 36px; padding-left: 14px; padding-right: 4px; }
.forge-table input[type=checkbox] { cursor: pointer; accent-color: var(--amber); width: 14px; height: 14px; }

/* Special links */
.entity-link { cursor: pointer; transition: all 0.15s; }
.entity-link:hover strong { color: var(--amber); text-decoration: underline; }

/* Badges */
.status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.65rem; text-transform: uppercase; border: 1px solid; display: inline-block; }
.status-badge.completed { color: var(--green); border-color: var(--green); background: var(--green-dim); }
.status-badge.failed { color: var(--red); border-color: var(--red); background: var(--red-dim); }
.status-badge.running { color: var(--amber); border-color: var(--amber); background: rgba(245, 166, 35, 0.1); }
.status-badge.pending { color: var(--blue); border-color: var(--blue); background: var(--blue-dim); }
.status-badge.stale { color: var(--text-dim); border-color: var(--border); background: var(--bg); }

/* Spinner */
.spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--amber); border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; vertical-align: middle; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Toast */
.forge-toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.forge-toast { padding: 10px 16px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.8rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); animation: toastIn 0.25s ease; pointer-events: all; cursor: pointer; max-width: 320px; display: flex; align-items: center; gap: 8px; }
.forge-toast.success { border-color: var(--green); }
.forge-toast.error   { border-color: var(--red); color: var(--red); }
.forge-toast.out     { animation: toastOut 0.25s ease forwards; }
@keyframes toastIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(10px); } }

/* Inputs */
input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

/* ── Provider Config Panel ─────────────────────────────────────────────────── */
.provider-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    flex-shrink: 0;
    overflow: hidden;
    transition: max-height 0.25s ease;
}
.provider-panel.collapsed { max-height: 42px; }
.provider-panel.expanded  { max-height: 400px; }
.provider-panel-header {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px; cursor: pointer;
    border-bottom: 1px solid var(--border);
    font-family: var(--mono); font-size: 0.75rem;
    color: var(--text-dim); user-select: none;
}
.provider-panel-header:hover { color: var(--text); }
.provider-panel-header .pp-title { color: var(--amber); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
.provider-panel-header .pp-summary { flex: 1; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.provider-panel-header .pp-caret { transition: transform 0.2s; }
.provider-panel.expanded .pp-caret { transform: rotate(180deg); }
.provider-panel-body {
    padding: 14px 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 20px;
    overflow-y: auto;
    max-height: 350px;
}
@media (max-width: 600px) { .provider-panel-body { grid-template-columns: 1fr; } }
.pp-scope-block { display: flex; flex-direction: column; gap: 8px; }
.pp-scope-label {
    font-family: var(--mono); font-size: 0.7rem; text-transform: uppercase;
    letter-spacing: 1px; color: var(--text-dim); padding-bottom: 4px;
    border-bottom: 1px solid var(--border);
}
.pp-scope-label.scope-global { color: var(--green); }
.pp-scope-label.scope-manual { color: var(--blue); }
.pp-field { display: flex; flex-direction: column; gap: 4px; }
.pp-field label { font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; }
.pp-field select,
.pp-field input[type=text] {
    background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--text); font-family: var(--mono); font-size: 0.75rem; padding: 5px 8px;
    transition: border-color 0.15s; width: 100%;
}
.pp-field select:focus,
.pp-field input[type=text]:focus { outline: none; border-color: var(--amber); }
.pp-field .pp-sub { display: flex; gap: 6px; }
.pp-field .pp-sub input { flex: 1; }
.pp-save-btn {
    align-self: flex-end; margin-top: 4px;
    padding: 5px 14px; background: transparent; border: 1px solid var(--amber);
    color: var(--amber); border-radius: var(--radius); cursor: pointer;
    font-family: var(--mono); font-size: 0.73rem; transition: all 0.15s;
}
.pp-save-btn:hover { background: rgba(245,166,35,0.12); }

/* Model override inline edit in table */
.model-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 7px; border-radius: 4px;
    border: 1px solid var(--border); background: var(--bg);
    font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim);
    cursor: pointer; transition: all 0.15s; white-space: nowrap;
}
.model-chip:hover { border-color: var(--amber); color: var(--amber); }
.model-chip.has-override { border-color: var(--blue); color: var(--blue); background: var(--blue-dim); }
.model-chip i { font-size: 10px; }
</style>
</head>
<body>

<div class="forge-layout">
    <!-- HEADER -->
    <header class="forge-header">
        <div class="forge-header-title">
            <i class="bi bi-list-task"></i> Map Run Queue
            <span id="queuePendingCount" style="font-size:0.75rem; color:var(--text-dim); margin-left:6px; font-weight:normal; cursor:default;"></span>
        </div>
        <div class="forge-header-actions">
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-family:var(--mono); font-size:0.75rem; color:var(--text);">
                <input type="checkbox" id="queueArchiveToggle" onchange="QueueViewer.toggleArchive()"> Show Archive
            </label>
            
            <button class="btn-forge-secondary" style="margin-left:8px;" onclick="QueueViewer.archiveCompleted()" title="Archive Completed">
                <i class="bi bi-archive"></i> arcomp
            </button>

            <button id="btnDel" class="btn-forge-secondary" style="margin-left:0;" onclick="QueueViewer.cancelSelected()" title="Cancel selected pending items">
                <i class="bi bi-x-circle"></i> del
            </button>

            <button id="btnRst" class="btn-forge-secondary" style="margin-left:0; display:none;" onclick="QueueViewer.resetSelected()" title="Reset attempts and restore selected failed items to pending">
                <i class="bi bi-arrow-repeat"></i> rst
            </button>

            <button id="btnPrio" class="btn-forge-secondary" style="margin-left:0;" onclick="QueueViewer.togglePrioritySelected()" title="Toggle priority (0↔1) for selected pending/failed items">
                <i class="bi bi-lightning-charge"></i> prio
            </button>

            <button id="btnUndelete" class="btn-forge-secondary" style="margin-left:0; display:none;" onclick="QueueViewer.undeleteSelected()" title="Restore selected cancelled items to pending">
                <i class="bi bi-arrow-counterclockwise"></i> undel
            </button>
            
            <div style="display:flex; align-items:center; gap:4px; margin-left:15px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:2px;">
                <button class="btn-forge-secondary" style="padding:2px 8px; border:none;" onclick="QueueViewer.changePage(-1)"><i class="bi bi-chevron-left"></i></button>
                <input type="number" id="queuePageIndex" style="width:50px; text-align:center; background:transparent; border:none; color:var(--text); font-family:var(--mono); font-size:0.75rem;" value="1" onchange="QueueViewer.load(this.value)">
                <span style="font-family:var(--mono); font-size:0.75rem; color:var(--text-dim);">/ <span id="queueTotalPages">1</span></span>
                <button class="btn-forge-secondary" style="padding:2px 8px; border:none;" onclick="QueueViewer.changePage(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
            
            <button class="btn-icon-sm" style="margin-left:10px;" onclick="QueueViewer.load()" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </header>

    <!-- BODY -->
    <div class="forge-body">

        <!-- ── Provider Config Panel ── -->
        <div class="provider-panel collapsed" id="providerPanel">
            <div class="provider-panel-header" onclick="ProviderPanel.toggle()">
                <i class="bi bi-gear-fill" style="color:var(--amber);"></i>
                <span class="pp-title">Global & Manual Worker Defaults</span>
                <span class="pp-summary" id="ppSummary">Loading...</span>
                <i class="bi bi-chevron-down pp-caret"></i>
            </div>
            <div class="provider-panel-body" id="providerPanelBody">
                <!-- Scopes rendered by JS -->
                <div style="color:var(--text-dim); font-family:var(--mono); font-size:0.75rem; grid-column:1/-1; padding:10px 0;">
                    <div class="spinner" style="margin-right:8px;"></div> Loading provider config...
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="forge-table">
                <thead>
                    <tr>
                        <th class="col-check"><input type="checkbox" id="checkAll" onchange="QueueViewer.toggleCheckAll(this)"></th>
                        <th>ID</th>
                        <th>Map Run</th>
                        <th>Entity</th>
                        <th>Status</th>
                        <th>Asset</th>
                        <th>Model</th>
                        <th>Attempts</th>
                        <th>Prio</th>
                        <th>Created (UTC)</th>
                    </tr>
                </thead>
                <tbody id="queueTableBody">
                    <tr><td colspan="10" style="text-align:center; color:var(--text-dim); padding:30px;"><div class="spinner"></div> Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.breakdown-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10001; display: none; align-items: center; justify-content: center; padding: 16px; }
.breakdown-overlay.open { display: flex; }
.breakdown-modal { background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius); width: 100%; max-width: 380px; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.7); animation: toastIn 0.2s ease; }
.breakdown-header { padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.breakdown-title { font-family: var(--mono); font-size: 0.75rem; font-weight: 700; color: var(--amber); text-transform: uppercase; letter-spacing: 1.5px; }
.breakdown-close { width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s; }
.breakdown-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

/* Model override modal */
.model-override-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 10002; display: none; align-items: center; justify-content: center; padding: 16px; }
.model-override-overlay.open { display: flex; }
.model-override-modal { background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius); width: 100%; max-width: 340px; box-shadow: 0 20px 60px rgba(0,0,0,0.8); animation: toastIn 0.2s ease; }
.model-override-modal .mo-header { padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.model-override-modal .mo-title { font-family: var(--mono); font-size: 0.75rem; font-weight: 700; color: var(--blue); text-transform: uppercase; letter-spacing: 1.5px; }
.model-override-modal .mo-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.model-override-modal .mo-note { font-family: var(--mono); font-size: 0.7rem; color: var(--text-dim); }
.model-override-modal .mo-field label { font-family: var(--mono); font-size: 0.68rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.model-override-modal .mo-field input,
.model-override-modal .mo-field select {
    width: 100%; background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text); font-family: var(--mono);
    font-size: 0.75rem; padding: 6px 10px;
}
.model-override-modal .mo-field input:focus,
.model-override-modal .mo-field select:focus { outline: none; border-color: var(--blue); }
.model-override-modal .mo-actions { display: flex; gap: 8px; justify-content: flex-end; padding: 0 16px 16px; }
</style>

<div class="breakdown-overlay" id="pendingBreakdownOverlay" onclick="if(event.target===this) QueueViewer.closePendingBreakdown()">
    <div class="breakdown-modal">
        <div class="breakdown-header">
            <div class="breakdown-title">Pending by Entity Type</div>
            <button class="breakdown-close" onclick="QueueViewer.closePendingBreakdown()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div style="overflow-y:auto; max-height:60vh;">
            <table class="forge-table" id="pendingBreakdownTable">
                <thead>
                    <tr>
                        <th>Entity Type</th>
                        <th style="text-align:right;">Pending</th>
                    </tr>
                </thead>
                <tbody id="pendingBreakdownBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Model Override Modal -->
<div class="model-override-overlay" id="modelOverrideOverlay" onclick="if(event.target===this) ModelOverride.close()">
    <div class="model-override-modal">
        <div class="mo-header">
            <div class="mo-title"><i class="bi bi-sliders" style="margin-right:6px;"></i>Job Model Override</div>
            <button class="breakdown-close" onclick="ModelOverride.close()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="mo-body">
            <div class="mo-note">
                Override model/dimensions for queue job <strong id="moQueueId" style="color:var(--text);"></strong>.<br>
                Leave blank to use the scope default. Saved into <code>api_provider_config.provider</code>.
            </div>
            <div class="mo-field">
                <label>Endpoint</label>
                <select id="moEndpointId"><option value="">— use default —</option></select>
            </div>
            <div class="mo-field">
                <label>Model</label>
                <select id="moModel"><option value="">— loading models... —</option></select>
            </div>
            <div class="mo-field">
                <label>Width × Height</label>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="text" id="moWidth"  placeholder="1024" style="flex:1;">
                    <span style="color:var(--text-dim); font-family:var(--mono);">×</span>
                    <input type="text" id="moHeight" placeholder="1024" style="flex:1;">
                </div>
            </div>
        </div>
        <div class="mo-actions">
            <button class="btn-forge-secondary" onclick="ModelOverride.clear()"><i class="bi bi-x-circle"></i> Clear</button>
            <button class="pp-save-btn" onclick="ModelOverride.save()"><i class="bi bi-check-lg"></i> Save</button>
        </div>
    </div>
</div>

<div class="forge-toast-container" id="toastContainer"></div>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// Shared utilities
// ─────────────────────────────────────────────────────────────────────────────
const API = '/api/scheduler_forge_api.php';

async function apiCall(action, data = {}) {
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

// ─────────────────────────────────────────────────────────────────────────────
// Pollinations Models Fetcher
// ─────────────────────────────────────────────────────────────────────────────
const PollinationsAPI = (() => {
    let _models = [];
    async function getModels() {
        if (_models.length > 0) return _models;
        try {
            const r = await fetch('https://gen.pollinations.ai/image/models');
            const d = await r.json();
            _models = d.map(x => x.name);
        } catch(e) {
            console.error('Model fetch failed', e);
            _models = ['flux', 'turbo', 'nanobanana', 'gptimage']; // fallback
        }
        return _models;
    }
    return { getModels };
})();

// ─────────────────────────────────────────────────────────────────────────────
// ProviderPanel — manages global/manual scope defaults
// ─────────────────────────────────────────────────────────────────────────────
const ProviderPanel = (() => {
    'use strict';

    let _endpoints = [];
    let _scopeData  = {};   // { global: {...}, manual: {...} }
    let _loaded     = false;

    const SCOPES = ['global', 'manual'];
    const SCOPE_LABELS = { global: 'Cron Worker (global)', manual: 'Manual / Ad-hoc' };

    function toggle() {
        const panel = document.getElementById('providerPanel');
        const isCollapsed = panel.classList.contains('collapsed');
        panel.classList.toggle('collapsed', !isCollapsed);
        panel.classList.toggle('expanded', isCollapsed);
        if (isCollapsed && !_loaded) load();
    }

    async function load() {
        const body = document.getElementById('providerPanelBody');
        body.innerHTML = '<div style="color:var(--text-dim);font-family:var(--mono);font-size:0.75rem;grid-column:1/-1;"><div class="spinner" style="margin-right:8px;"></div> Loading...</div>';
        try {
            const r = await apiCall('fetch_provider_config');
            if (!r.ok) throw new Error(r.error || 'Failed');
            _endpoints = r.data.endpoints || [];
            _scopeData  = {};
            (r.data.scopes || []).forEach(s => { _scopeData[s.scope] = s; });
            
            // Ensure remote models are loaded before rendering
            await PollinationsAPI.getModels();
            
            _loaded = true;
            _render(body);
            _updateSummary();
        } catch (e) {
            body.innerHTML = `<div style="color:var(--red);font-family:var(--mono);font-size:0.75rem;grid-column:1/-1;">Error: ${escHtml(e.message)}</div>`;
        }
    }

    function _endpointOptions(selectedId) {
        return _endpoints.map(ep =>
            `<option value="${ep.id}" ${parseInt(selectedId) === ep.id ? 'selected' : ''}>
                ${escHtml(ep.provider_name)} — ${escHtml(ep.endpoint_code)}
            </option>`
        ).join('');
    }

    async function _render(body) {
        const models = await PollinationsAPI.getModels();
        
        body.innerHTML = SCOPES.map(scope => {
            const d = _scopeData[scope] || {};
            const colorClass = scope === 'global' ? 'scope-global' : 'scope-manual';
            
            const modelOpts = '<option value="">— endpoint default —</option>' +
                models.map(m => `<option value="${m}" ${d.model_override === m ? 'selected' : ''}>${m}</option>`).join('');

            return `
            <div class="pp-scope-block" id="ppBlock_${scope}">
                <div class="pp-scope-label ${colorClass}">
                    <i class="bi bi-${scope === 'global' ? 'clock' : 'play-circle'}"></i>
                    ${SCOPE_LABELS[scope]}
                </div>
                <div class="pp-field">
                    <label>Endpoint</label>
                    <select id="ppEndpoint_${scope}">${_endpointOptions(d.endpoint_id)}</select>
                </div>
                <div class="pp-field">
                    <label>Model Override <span style="color:var(--border-glow);">(blank = endpoint default)</span></label>
                    <select id="ppModel_${scope}">${modelOpts}</select>
                </div>
                <div class="pp-field">
                    <label>Width × Height Override <span style="color:var(--border-glow);">(0 = default)</span></label>
                    <div class="pp-sub">
                        <input type="text" id="ppWidth_${scope}"  placeholder="0" value="${d.width_override  || ''}">
                        <span style="color:var(--text-dim);font-family:var(--mono);align-self:center;">×</span>
                        <input type="text" id="ppHeight_${scope}" placeholder="0" value="${d.height_override || ''}">
                    </div>
                </div>
                <button class="pp-save-btn" onclick="ProviderPanel.save('${scope}')">
                    <i class="bi bi-floppy"></i> Save ${SCOPE_LABELS[scope]}
                </button>
            </div>`;
        }).join('');

        // Populate the model-override modal endpoint dropdown too
        _populateModalEndpoints();
    }

    function _populateModalEndpoints() {
        const sel = document.getElementById('moEndpointId');
        if (!sel) return;
        const existing = sel.value;
        sel.innerHTML = '<option value="">— use default —</option>' +
            _endpoints.map(ep =>
                `<option value="${ep.id}" ${parseInt(existing) === ep.id ? 'selected' : ''}>
                    ${escHtml(ep.provider_name)} — ${escHtml(ep.endpoint_code)}
                </option>`
            ).join('');
    }

    function _updateSummary() {
        const g = _scopeData['global'] || {};
        const m = _scopeData['manual'] || {};
        const epLabel = (d) => {
            const ep = _endpoints.find(e => e.id === parseInt(d.endpoint_id));
            return ep ? `${ep.provider_name}/${ep.endpoint_code}` : `ep#${d.endpoint_id}`;
        };
        const modelStr = (d) => d.model_override ? ` [${d.model_override}]` : '';
        const parts = [];
        if (g.endpoint_id) parts.push(`global: ${epLabel(g)}${modelStr(g)}`);
        if (m.endpoint_id) parts.push(`manual: ${epLabel(m)}${modelStr(m)}`);
        document.getElementById('ppSummary').textContent = parts.join('  ·  ') || 'Not configured';
    }

    async function save(scope) {
        const endpoint_id   = document.getElementById(`ppEndpoint_${scope}`)?.value;
        const model_override = document.getElementById(`ppModel_${scope}`)?.value.trim() || null;
        const width_override  = parseInt(document.getElementById(`ppWidth_${scope}`)?.value)  || 0;
        const height_override = parseInt(document.getElementById(`ppHeight_${scope}`)?.value) || 0;

        if (!endpoint_id) { toast('Select an endpoint first.', 'error'); return; }

        try {
            const r = await apiCall('save_provider_default', {
                scope, endpoint_id: parseInt(endpoint_id),
                model_override, width_override, height_override
            });
            if (!r.ok) throw new Error(r.error || 'Save failed');
            _scopeData[scope] = r.data;
            _updateSummary();
            toast(`Saved ${scope} provider config`, 'success');
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function getEndpoints() { return _endpoints; }

    return { toggle, load, save, getEndpoints };
})();


// ─────────────────────────────────────────────────────────────────────────────
// ModelOverride — per-job model/endpoint inline override
// ─────────────────────────────────────────────────────────────────────────────
const ModelOverride = (() => {
    'use strict';

    let _queueId       = null;
    let _currentConfig = {};

    async function open(queueId, configJson) {
        _queueId = queueId;
        try { _currentConfig = configJson ? JSON.parse(configJson) : {}; } catch { _currentConfig = {}; }

        document.getElementById('moQueueId').textContent = `#${queueId}`;

        const prov = _currentConfig.provider || {};
        document.getElementById('moEndpointId').value = prov.endpoint_id || '';
        document.getElementById('moWidth').value      = prov.width  || '';
        document.getElementById('moHeight').value     = prov.height || '';

        // Render external models dynamically
        const models = await PollinationsAPI.getModels();
        const selModel = document.getElementById('moModel');
        selModel.innerHTML = '<option value="">— use scope default —</option>' +
            models.map(m => `<option value="${m}" ${prov.model === m ? 'selected' : ''}>${m}</option>`).join('');

        // Ensure endpoint dropdown is populated
        const sel = document.getElementById('moEndpointId');
        if (sel.options.length <= 1) {
            const eps = ProviderPanel.getEndpoints();
            sel.innerHTML = '<option value="">— use default —</option>' +
                eps.map(ep =>
                    `<option value="${ep.id}" ${parseInt(prov.endpoint_id) === ep.id ? 'selected' : ''}>
                        ${escHtml(ep.provider_name)} — ${escHtml(ep.endpoint_code)}
                    </option>`
                ).join('');
        }

        document.getElementById('modelOverrideOverlay').classList.add('open');
    }

    function close() {
        document.getElementById('modelOverrideOverlay').classList.remove('open');
        _queueId = null;
    }

    async function save() {
        if (!_queueId) return;

        const endpoint_id = document.getElementById('moEndpointId').value;
        const model  = document.getElementById('moModel').value.trim();
        const width  = parseInt(document.getElementById('moWidth').value)  || 0;
        const height = parseInt(document.getElementById('moHeight').value) || 0;

        // Merge into existing config, preserving task-level keys (limit, offset etc.)
        const newConfig = Object.assign({}, _currentConfig);
        const prov = {};
        if (endpoint_id) prov.endpoint_id = parseInt(endpoint_id);
        if (model)       prov.model  = model;
        if (width  > 0)  prov.width  = width;
        if (height > 0)  prov.height = height;

        if (Object.keys(prov).length > 0) {
            newConfig.provider = prov;
        } else {
            delete newConfig.provider;
        }

        try {
            const r = await apiCall('set_queue_item_provider', {
                id: _queueId,
                api_provider_config: Object.keys(newConfig).length > 0 ? newConfig : null
            });
            if (!r.ok) throw new Error(r.error || 'Save failed');
            toast(`Job #${_queueId} model override saved`, 'success');
            close();
            QueueViewer.load();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function clear() {
        if (!_queueId) return;
        const newConfig = Object.assign({}, _currentConfig);
        delete newConfig.provider;
        try {
            const r = await apiCall('set_queue_item_provider', {
                id: _queueId,
                api_provider_config: Object.keys(newConfig).length > 0 ? newConfig : null
            });
            if (!r.ok) throw new Error(r.error || 'Clear failed');
            toast(`Job #${_queueId} model override cleared`, 'success');
            close();
            QueueViewer.load();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    return { open, close, save, clear };
})();


// ─────────────────────────────────────────────────────────────────────────────
// QueueViewer — main queue table (unchanged logic, new Model column added)
// ─────────────────────────────────────────────────────────────────────────────
const QueueViewer = (() => {
    'use strict';

    let _currentPage = 1;
    let _isArchive = false;
    let _pendingByEntity = [];

    function getSelectedIds() {
        return Array.from(document.querySelectorAll('.row-check:checked')).map(cb => parseInt(cb.value));
    }

    function toggleCheckAll(masterCb) {
        document.querySelectorAll('.row-check').forEach(cb => { cb.checked = masterCb.checked; });
    }

    function _updateButtonVisibility() {
        const btnDel = document.getElementById('btnDel');
        const btnRst = document.getElementById('btnRst');
        const btnPrio = document.getElementById('btnPrio');
        const btnUndel = document.getElementById('btnUndelete');
        if (_isArchive) {
            btnDel.style.display = 'none';
            btnRst.style.display = 'none';
            btnPrio.style.display = 'none';
            btnUndel.style.display = '';
        } else {
            btnDel.style.display = '';
            btnRst.style.display = '';
            btnPrio.style.display = '';
            btnUndel.style.display = 'none';
        }
    }

    async function load(pageStr = null) {
        if (pageStr !== null) {
            _currentPage = parseInt(pageStr) || 1;
        }
        
        // Reset header checkbox
        const checkAll = document.getElementById('checkAll');
        if (checkAll) checkAll.checked = false;

        _updateButtonVisibility();

        const tbody = document.getElementById('queueTableBody');
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; color:var(--text-dim); padding:40px;"><div class="spinner" style="margin-right:10px;"></div> Loading...</td></tr>';
        
        try {
            const r = await apiCall('fetch_queue', { page: _currentPage, limit: 50, archive: _isArchive });
            if (r.ok) {
                if (!_isArchive) {
                    _pendingByEntity = r.data.pending_by_entity || [];
                    const countEl = document.getElementById('queuePendingCount');
                    countEl.textContent = `(${r.data.pending_count} pending)`;
                    countEl.style.color = 'var(--amber)';
                    countEl.style.cursor = _pendingByEntity.length > 0 ? 'pointer' : 'default';
                    countEl.style.textDecoration = _pendingByEntity.length > 0 ? 'underline dotted' : 'none';
                    countEl.onclick = _pendingByEntity.length > 0 ? () => QueueViewer.openPendingBreakdown() : null;
                } else {
                    const countEl = document.getElementById('queuePendingCount');
                    countEl.textContent = `(Archive View)`;
                    countEl.style.color = 'var(--text-dim)';
                    countEl.style.cursor = 'default';
                    countEl.style.textDecoration = 'none';
                    countEl.onclick = null;
                }

                document.getElementById('queueTotalPages').textContent = r.data.total_pages || 1;
                document.getElementById('queuePageIndex').value = r.data.page;
                _currentPage = r.data.page;
                
                if (r.data.rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; color:var(--text-dim); padding:40px;">Queue is empty.</td></tr>';
                } else {
                    tbody.innerHTML = r.data.rows.map(q => {
                        let badgeCls = 'pending';
                        if (q.status === 'completed') badgeCls = 'completed';
                        else if (q.status === 'failed') badgeCls = 'failed';
                        else if (q.status === 'processing') badgeCls = 'running';
                        else if (q.status === 'cancelled') badgeCls = 'stale';

                        // Queue mode: pending and failed rows get a checkbox
                        // Archive mode: only cancelled rows get a checkbox (undelete candidates)
                        let checkboxCell = '<td class="col-check"></td>';
                        if (!_isArchive && (q.status === 'pending' || q.status === 'failed')) {
                            checkboxCell = `<td class="col-check"><input type="checkbox" class="row-check" value="${q.id}" data-status="${q.status}"></td>`;
                        } else if (_isArchive && q.status === 'cancelled') {
                            checkboxCell = `<td class="col-check"><input type="checkbox" class="row-check" value="${q.id}" data-status="${q.status}"></td>`;
                        }

                        // Model chip: shows per-job override or "—"
                        let provObj = null;
                        try { provObj = q.api_provider_config ? JSON.parse(q.api_provider_config) : null; } catch {}
                        const jobModel = provObj?.provider?.model || '';
                        const hasOverride = !!jobModel;
                        const canEdit = !_isArchive && (q.status === 'pending' || q.status === 'failed');
                        let modelCell;
                        if (canEdit) {
                            // Using encodeURIComponent strictly avoids any syntax issues with JS/HTML quotes
                            const safeConfig = encodeURIComponent(q.api_provider_config || '');
                            modelCell = `<td>
                                <span class="model-chip ${hasOverride ? 'has-override' : ''}"
                                    onclick="ModelOverride.open(${q.id}, decodeURIComponent('${safeConfig}'))"
                                    title="${hasOverride ? 'Override: ' + escHtml(jobModel) : 'Set model override'}">
                                    <i class="bi bi-${hasOverride ? 'cpu-fill' : 'cpu'}"></i>
                                    ${hasOverride ? escHtml(jobModel) : '—'}
                                </span>
                            </td>`;
                        } else {
                            modelCell = `<td><span style="color:var(--text-dim); font-family:var(--mono); font-size:0.7rem;">${hasOverride ? escHtml(jobModel) : '—'}</span></td>`;
                        }

                        return `
                        <tr>
                            ${checkboxCell}
                            <td>#${q.id}</td>
                            <td><span style="color:var(--text-dim);">#</span>${q.map_run_id}</td>
                            <td class="entity-link" onclick="if(window.showEntityFormInModal) showEntityFormInModal('${escHtml(q.entity_type)}', ${q.entity_id})" title="Open Entity Form">
                                <strong>${escHtml(q.entity_type)}</strong> <span style="color:var(--text-dim);">#${q.entity_id}</span>
                            </td>
                            <td><span class="status-badge ${badgeCls}">${q.status}</span></td>
                            <td><strong>${escHtml(q.asset_type)}</strong> ${q.asset_id ? '<span style="color:var(--text-dim);">#' + q.asset_id + '</span>' : '<span style="color:var(--text-dim); font-style:italic;">pending</span>'}</td>
                            ${modelCell}
                            <td>${q.attempts} <span style="color:var(--text-dim); font-size:0.85em;">/ 3</span></td>
                            <td>${parseInt(q.priority) === 1 ? '<span style="color:var(--amber); font-size:1rem;" title="High priority">⚡</span>' : '<span style="color:var(--border-glow);" title="Normal priority">—</span>'}</td>
                            <td>${q.created_at}</td>
                        </tr>`;
                    }).join('');
                }
            } else {
                throw new Error(r.error || 'Failed to fetch');
            }
        } catch (e) {
            toast('Error: ' + e.message, 'error');
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; color:var(--red); padding:40px;">Failed to load queue data</td></tr>';
        }
    }

    async function toggleArchive() {
        _isArchive = document.getElementById('queueArchiveToggle').checked;
        _currentPage = 1;
        document.getElementById('queuePageIndex').value = 1;
        await load();
    }

    async function changePage(delta) {
        let newPage = parseInt(document.getElementById('queuePageIndex').value) + delta;
        if (newPage < 1) newPage = 1;
        document.getElementById('queuePageIndex').value = newPage;
        await load(newPage);
    }

    async function archiveCompleted() {
        if (!confirm('Archive all completed tasks? This will move them to the archive table.')) return;
        try {
            const r = await apiCall('archive_completed_queue');
            if (r.ok) {
                toast(`Archived ${r.data.count} completed tasks`, 'success');
                load();
            } else {
                throw new Error(r.error || 'Archive failed');
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function cancelSelected() {
        const allChecked = Array.from(document.querySelectorAll('.row-check:checked'));
        const pendingIds = allChecked.filter(cb => cb.dataset.status === 'pending').map(cb => parseInt(cb.value));
        const failedIds  = allChecked.filter(cb => cb.dataset.status === 'failed').map(cb => parseInt(cb.value));
        if (pendingIds.length === 0 && failedIds.length === 0) { toast('No items selected.', 'info'); return; }

        const parts = [];
        if (pendingIds.length) parts.push(`${pendingIds.length} pending`);
        if (failedIds.length)  parts.push(`${failedIds.length} failed`);
        if (!confirm(`Remove ${parts.join(' and ')} item(s)? Pending items will be cancelled (moved to archive); failed items will be deleted.`)) return;

        try {
            let count = 0;
            if (pendingIds.length) {
                const r = await apiCall('cancel_queue_items', { ids: pendingIds });
                if (!r.ok) throw new Error(r.error || 'Cancel failed');
                count += r.data.count;
            }
            if (failedIds.length) {
                const r = await apiCall('delete_failed_queue_items', { ids: failedIds });
                if (!r.ok) throw new Error(r.error || 'Delete failed');
                count += r.data.count;
            }
            toast(`Removed ${count} item(s)`, 'success');
            load();
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function resetSelected() {
        const ids = Array.from(document.querySelectorAll('.row-check:checked'))
            .filter(cb => cb.dataset.status === 'failed')
            .map(cb => parseInt(cb.value));
        if (ids.length === 0) { toast('No failed items selected.', 'info'); return; }
        if (!confirm(`Reset ${ids.length} failed item(s) back to pending with attempts cleared?`)) return;
        try {
            const r = await apiCall('reset_failed_queue_items', { ids });
            if (r.ok) {
                toast(`Reset ${r.data.count} item(s) to pending`, 'success');
                load();
            } else {
                throw new Error(r.error || 'Reset failed');
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function undeleteSelected() {
        const ids = getSelectedIds();
        if (ids.length === 0) { toast('No cancelled items selected.', 'info'); return; }
        if (!confirm(`Restore ${ids.length} cancelled item(s) back to pending?`)) return;
        try {
            const r = await apiCall('uncancel_queue_items', { ids });
            if (r.ok) {
                toast(`Restored ${r.data.count} item(s) to pending`, 'success');
                load();
            } else {
                throw new Error(r.error || 'Restore failed');
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    async function togglePrioritySelected() {
        const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => parseInt(cb.value));
        if (ids.length === 0) { toast('No items selected.', 'info'); return; }
        try {
            const r = await apiCall('toggle_queue_priority', { ids });
            if (r.ok) {
                toast(`Priority toggled for ${r.data.count} item(s)`, 'success');
                load();
            } else {
                throw new Error(r.error || 'Toggle failed');
            }
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    function openPendingBreakdown() {
        const tbody = document.getElementById('pendingBreakdownBody');
        const total = _pendingByEntity.reduce((s, r) => s + parseInt(r.cnt), 0);
        tbody.innerHTML = _pendingByEntity.map(r => `
            <tr>
                <td>${escHtml(r.entity_type)}</td>
                <td style="text-align:right; color:var(--amber); font-weight:700;">${r.cnt}</td>
            </tr>`).join('') + `
            <tr style="border-top:1px solid var(--border-glow);">
                <td style="color:var(--text-bright); font-weight:700;">Total</td>
                <td style="text-align:right; color:var(--text-bright); font-weight:700;">${total}</td>
            </tr>`;
        document.getElementById('pendingBreakdownOverlay').classList.add('open');
    }

    function closePendingBreakdown() {
        document.getElementById('pendingBreakdownOverlay').classList.remove('open');
    }

    return {
        load,
        toggleArchive,
        changePage,
        archiveCompleted,
        toggleCheckAll,
        cancelSelected,
        resetSelected,
        undeleteSelected,
        togglePrioritySelected,
        openPendingBreakdown,
        closePendingBreakdown
    };

})();

document.addEventListener('DOMContentLoaded', () => {
    QueueViewer.load();
    // Pre-load provider config in background so endpoint list is ready for modal
    ProviderPanel.load();
});
</script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php //echo $eruda; ?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
