<?php
// public/sqlish.php
// ─────────────────────────────────────────────────────────────────────────────
// SQLISH — Structured SQL-Op Extraction UI
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Sqlish — Data Extraction</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
(function() {
  try {
    var t = localStorage.getItem('spw_theme');
    if (t) document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>

<style>
/* Forge Design System - Sqlish specific accent (Azure/Blue) */
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
  --green-dim:    rgba(34,211,160,0.1);
  --red:          #f05060;
  --red-dim:      rgba(240,80,96,0.1);
  --blue:         #3b82f6;
  --blue-dim:     rgba(59,130,246,0.1);
  --blue-mid:     rgba(59,130,246,0.18);
  --blue-glow:    rgba(59,130,246,0.45);
  --mono:         'Space Mono', 'Fira Mono', monospace;
  --sans:         'Syne', system-ui, sans-serif;
  --radius:       6px;
  --radius-lg:    10px;
}

:root[data-theme="light"], html[data-theme="light"] {
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
  --green-dim:    rgba(5,150,105,0.1);
  --red:          #dc2626;
  --red-dim:      rgba(220,38,38,0.1);
  --blue:         #2563eb;
  --blue-dim:     rgba(37,99,235,0.1);
  --blue-mid:     rgba(37,99,235,0.18);
  --blue-glow:    rgba(37,99,235,0.4);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  height: 100%; background: var(--bg); color: var(--text);
  font-family: var(--sans); font-size: 14px; line-height: 1.5;
  -webkit-font-smoothing: antialiased; overflow: hidden;
}

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

.sq-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ── */
.sq-header {
  height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border); z-index: 100;
}
.sq-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.82rem; font-weight: 700; color: var(--blue); letter-spacing: 2px; text-transform: uppercase; }
.sq-logo-icon { width: 28px; height: 28px; background: var(--blue-mid); border: 1px solid var(--blue-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; }

.btn-icon-sm { width: 32px; height: 32px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.btn-icon-sm:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-dim); }

.sq-hamburger { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; flex-shrink: 0; font-size: 16px; position: relative; }
.sq-hamburger:hover, .sq-hamburger.open { border-color: var(--blue); color: var(--blue); background: var(--blue-dim); }

/* ── FLYOUT ── */
.sq-flyout { position: fixed; top: 52px; bottom: 0; width: min(300px, 88vw); background: var(--surface); border-right: 1px solid var(--border); z-index: 500; display: flex; flex-direction: column; overflow: hidden; transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 0 40px rgba(0,0,0,0.6); left: 0; transform: translateX(-100%); }
.sq-flyout.open { transform: translateX(0); }
.sq-flyout-backdrop { position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.45); z-index: 499; display: none; backdrop-filter: blur(2px); }
.sq-flyout-backdrop.visible { display: block; }
.flyout-head { padding: 12px 14px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; }
.flyout-head-title { font-family: var(--mono); font-size: 0.65rem; color: var(--blue); text-transform: uppercase; letter-spacing: 2px; }
.flyout-close { width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.flyout-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

.left-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.left-tab { flex: 1; padding: 8px 4px; font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-align: center; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid transparent; transition: all 0.15s; background: none; border-top: none; border-left: none; border-right: none; }
.left-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
.left-tab:hover:not(.active) { color: var(--text); }
.left-tab-body { flex: 1; overflow: hidden; position: relative; }
.left-tab-content { position: absolute; inset: 0; overflow-y: auto; display: none; padding: 12px; }
.left-tab-content.active { display: block; }

/* ── FORMS ── */
.ctx-group { margin-bottom: 12px; }
.ctx-label { font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 5px; display: block; }
.ctx-textarea { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.73rem; line-height: 1.5; padding: 8px 10px; resize: vertical; min-height: 60px; transition: border-color 0.15s; }
.ctx-textarea:focus { outline: none; border-color: var(--blue); }
.ctx-input { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.73rem; padding: 7px 10px; transition: border-color 0.15s; }
.ctx-input:focus { outline: none; border-color: var(--blue); }
.ctx-select { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.73rem; padding: 7px 10px; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }
.ctx-select:focus { outline: none; border-color: var(--blue); }
.ctx-select:disabled { opacity: 0.5; cursor: not-allowed; }

.empty-state { text-align: center; padding: 24px 12px; color: var(--text-dim); font-family: var(--mono); font-size: 0.75rem; }
.empty-icon { font-size: 28px; opacity: 0.2; margin-bottom: 6px; }

/* Items List */
.sq-item { display: flex; align-items: flex-start; gap: 8px; padding: 8px 10px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: all 0.15s; font-family: var(--mono); font-size: 0.7rem; position: relative; margin-bottom: 6px; }
.sq-item:hover, .sq-item.active { border-color: var(--blue); background: var(--blue-dim); }
.sq-item-body { flex: 1; min-width: 0; }
.sq-item-title { color: var(--text); font-weight: 700; padding-right: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sq-item-meta { color: var(--text-dim); margin-top: 3px; font-size: 0.63rem; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.status-chip { font-size: 0.58rem; padding: 1px 6px; border-radius: 10px; border: 1px solid var(--border-glow); text-transform: uppercase; letter-spacing: 0.5px; }
.status-chip.pending { color: var(--text-dim); }
.status-chip.running { color: var(--amber); border-color: var(--amber); }
.status-chip.done { color: var(--green); border-color: var(--green); }
.status-chip.failed { color: var(--red); border-color: var(--red); }
.status-chip.cancelled { color: var(--text-dim); text-decoration: line-through; }

/* ── MAIN AREA ── */
.sq-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); min-height: 0; }
.sq-feed { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 16px; }

.sq-welcome { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; gap: 20px; padding: 40px; }
.sq-welcome-sigil { font-size: 52px; opacity: 0.15; line-height: 1; font-family: var(--mono); }
.sq-welcome-title { font-family: var(--mono); font-size: 1rem; color: var(--blue); text-transform: uppercase; letter-spacing: 3px; }
.sq-welcome-body { font-size: 0.85rem; color: var(--text-dim); line-height: 1.7; max-width: 480px; }
.sq-welcome-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }

/* Run View */
.run-header { margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
.run-title { font-size: 1.2rem; color: var(--blue); font-weight: bold; font-family: var(--sans); margin-bottom: 6px; }
.run-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-family: var(--mono); font-size: 0.75rem; color: var(--text-dim); }

.record-list { display: flex; flex-direction: column; gap: 12px; }
.record-item { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; position: relative; }
.record-item.status-written { border-left: 3px solid var(--green); }
.record-item.status-failed { border-left: 3px solid var(--red); background: var(--red-dim); }
.record-item.status-pending { border-left: 3px solid var(--text-dim); }
.record-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-family: var(--mono); font-size: 0.75rem; }
.record-num { color: var(--text-dim); font-weight: bold; }
.record-code { background: var(--surface); padding: 8px; border-radius: 4px; border: 1px solid var(--border); font-family: var(--mono); font-size: 0.7rem; color: var(--text-bright); white-space: pre-wrap; word-wrap: break-word; }
.record-error { margin-top: 8px; font-family: var(--mono); font-size: 0.7rem; color: var(--red); }

/* ── MODALS ── */
.sq-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px; }
.sq-modal-overlay.open { display: flex; }
.sq-modal { background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius-lg); width: 100%; max-width: 700px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.7); animation: modalIn 0.2s ease; }
.sq-modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.sq-modal-title { font-family: var(--mono); font-size: 0.78rem; color: var(--blue); text-transform: uppercase; letter-spacing: 1.5px; }
.sq-modal-close { width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s; }
.sq-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.sq-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.sq-modal-footer { padding: 12px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; background: var(--bg); }

.btn-primary { padding: 8px 18px; background: var(--blue); color: #fff; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s; }
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary { padding: 8px 18px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; transition: all 0.15s; }
.btn-secondary:hover { border-color: var(--border-glow); color: var(--text); }
.btn-danger { padding: 8px 18px; background: var(--red-dim); color: var(--red); border: 1px solid var(--red); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; transition: all 0.15s; }
.btn-danger:hover { background: var(--red); color: #fff; }

/* Column Editor */
.col-editor-row { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px; margin-bottom: 8px; }
.col-editor-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
.col-editor-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; border-bottom: 1px solid var(--border); padding-bottom: 6px; }
.col-remove-btn { width: 22px; height: 22px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; font-size: 11px; display: flex; align-items: center; justify-content: center; }
.col-remove-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.sep { height: 1px; background: var(--border); margin: 10px 0; }
.hint { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 3px; }

/* ── TOAST ── */
.toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 6px; pointer-events: none; }
.toast { padding: 8px 14px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.76rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); animation: toastIn 0.2s ease; pointer-events: all; cursor: pointer; display: flex; gap: 6px; align-items: center; max-width: 300px; }
.toast.success { border-color: var(--green); }
.toast.error { border-color: var(--red); color: var(--red); }
.toast.info { border-color: var(--blue); }
.toast.out { animation: toastOut 0.2s forwards; }

@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(8px); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-8px); } to { opacity: 1; transform: none; } }

/* ── PAGINATION BAR ── */
.pg-bar { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 4px 2px; margin-top: 4px; font-family: var(--mono); }
.pg-btn { width: 26px; height: 26px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--blue); color: var(--blue); background: var(--blue-dim); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-input { width: 40px; height: 26px; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.72rem; padding: 0 2px; }
.pg-input:focus { outline: none; border-color: var(--blue); }
.pg-of { font-size: 0.68rem; color: var(--text-dim); white-space: nowrap; }
</style>
</head>
<body>

<div class="sq-layout">
<div class="sq-flyout-backdrop" id="flyoutBackdrop" onclick="SQ.closeFlyouts()"></div>

<header class="sq-header">
  <button class="sq-hamburger" id="hamburgerLeft" onclick="SQ.toggleFlyout('left')">
    <i class="bi bi-layout-text-sidebar"></i>
  </button>
  <div class="sq-logo" style="flex:1; justify-content:center;">
    <div class="sq-logo-icon">▤</div>
    <span>Sqlish</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <button class="btn-icon-sm" onclick="SQ.openNewTemplateModal()" title="New Template">
      <i class="bi bi-plus-circle"></i>
    </button>
    <a href="/wtf.php" class="btn-icon-sm" title="WTF Engine" style="text-decoration:none;">
      <i class="bi bi-gear"></i>
    </a>
  </div>
</header>

<!-- LEFT FLYOUT -->
<div class="sq-flyout" id="flyoutLeft">
  <div class="flyout-head">
    <span class="flyout-head-title">Sqlish Engine</span>
    <button class="flyout-close" onclick="SQ.closeFlyouts()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
    <div class="left-tabs">
      <button class="left-tab active" data-ltab="runs">Runs</button>
      <button class="left-tab" data-ltab="templates">Templates</button>
    </div>
    <div class="left-tab-body">
      <!-- RUNS -->
      <div class="left-tab-content active" id="ltab-runs">
        <div id="runsList"><div class="empty-state">Loading...</div></div>
        <div class="pg-bar" id="runsListPager" style="display:none;">
          <button class="pg-btn" id="runsListPagerPrev"><i class="bi bi-chevron-left"></i></button>
          <input type="text" class="pg-input" id="runsListPagerInput" inputmode="numeric">
          <span class="pg-of">/ <span id="runsListPagerTotal">1</span></span>
          <button class="pg-btn" id="runsListPagerNext"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
      <!-- TEMPLATES -->
      <div class="left-tab-content" id="ltab-templates">
        <div id="templatesList"><div class="empty-state">Loading...</div></div>
        <div class="pg-bar" id="templatesListPager" style="display:none;">
          <button class="pg-btn" id="templatesListPagerPrev"><i class="bi bi-chevron-left"></i></button>
          <input type="text" class="pg-input" id="templatesListPagerInput" inputmode="numeric">
          <span class="pg-of">/ <span id="templatesListPagerTotal">1</span></span>
          <button class="pg-btn" id="templatesListPagerNext"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div style="margin-top:10px;">
          <button class="btn-secondary" style="width:100%;font-size:0.7rem;" onclick="SQ.openNewTemplateModal()">
            <i class="bi bi-plus-lg"></i> New Template
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MAIN -->
<main class="sq-main">
  <div class="sq-feed" id="feed">
    <div class="sq-welcome" id="welcomeState">
      <div class="sq-welcome-sigil">▤</div>
      <div class="sq-welcome-title">Sqlish — Data Extraction</div>
      <div class="sq-welcome-body">
        Configure structural operations for your data tables, then let AI extract values directly from narrative prose. Zero hand-written SQL. Zero AI table-guessing.
      </div>
      <div class="sq-welcome-actions">
        <button class="btn-primary" onclick="SQ.openNewTemplateModal()"><i class="bi bi-plus-lg"></i> New Template</button>
      </div>
    </div>
  </div>
</main>

</div><!-- /sq-layout -->

<!-- MODAL: TEMPLATE BUILDER -->
<div class="sq-modal-overlay" id="templateModal">
  <div class="sq-modal">
    <div class="sq-modal-header">
      <div class="sq-modal-title" id="templateModalTitle">New Template</div>
      <button class="sq-modal-close" onclick="SQ.closeModal('templateModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sq-modal-body">
      <input type="hidden" id="tplId" value="">
      <div class="ctx-group">
        <label class="ctx-label">Template Name</label>
        <input class="ctx-input" type="text" id="tplName" placeholder="e.g. Extract Episode Sketches">
      </div>
      <!-- ADDED: The missing description textarea -->
      <div class="ctx-group">
        <label class="ctx-label">Internal Description</label>
        <textarea class="ctx-textarea" id="tplDescription" placeholder="Optional notes..."></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Target Table</label>
        <select class="ctx-select" id="tplTable" onchange="SQ.handleTableChange()"><option value="">-- Select Table --</option></select>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Operation Type</label>
        <select class="ctx-select" id="tplOp" onchange="SQ.handleOpChange()">
            <option value="insert">Insert</option>
            <option value="update">Update</option>
            <option value="select">Select (Read-Only)</option>
        </select>
      </div>
      <div class="ctx-group" id="wrapKeyCol" style="display:none;">
        <label class="ctx-label">Key Column (Required for Update/Select)</label>
        <select class="ctx-select" id="tplKeyCol"><option value="">-- Wait for table --</option></select>
        <div class="hint">Identifies the row. Must be supplied by context during run, not by AI.</div>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">AI Instructions (Optional)</label>
        <textarea class="ctx-textarea" id="tplInstruct" placeholder="Extra prompt guidance (e.g. 'One row per scene mentioned')"></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Writeback Context Key (Optional)</label>
        <input class="ctx-input" type="text" id="tplWriteback" placeholder="e.g. sketch_id_range">
        <div class="hint">Saves {min_id, max_id, count, ids} back to WTF context after an insert.</div>
      </div>
      <div class="sep"></div>
      <div class="ctx-group">
        <label class="ctx-label">Mapped Columns</label>
        <div id="colEditorList"></div>
        <button class="btn-secondary" style="width:100%;font-size:0.7rem;margin-top:4px;" onclick="SQ.addColEditorRow()" id="btnAddCol" disabled>
          <i class="bi bi-plus-lg"></i> Add Column
        </button>
      </div>
    </div>
    <div class="sq-modal-footer">
      <button class="btn-danger" id="btnDeleteTpl" style="display:none; margin-right:auto;" onclick="SQ.deleteTemplate()">Delete</button>
      <button class="btn-secondary" onclick="SQ.closeModal('templateModal')">Cancel</button>
      <button class="btn-primary" onclick="SQ.saveTemplate()">Save Template</button>
    </div>
  </div>
</div>

<!-- MODAL: START RUN -->
<div class="sq-modal-overlay" id="startRunModal">
  <div class="sq-modal">
    <div class="sq-modal-header">
      <div class="sq-modal-title">Test Extraction Run</div>
      <button class="sq-modal-close" onclick="SQ.closeModal('startRunModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sq-modal-body">
      <input type="hidden" id="startRunTplId" value="">
      <div class="ctx-group">
        <label class="ctx-label">Source Text (Lore / Prose)</label>
        <textarea class="ctx-textarea tall" id="startRunSource" placeholder="Paste the text the AI should extract values from..."></textarea>
      </div>
      <div class="ctx-group">
        <label class="ctx-label">Extra Context (JSON)</label>
        <textarea class="ctx-textarea" id="startRunContext" placeholder='{"episode_id": 42}'></textarea>
        <div class="hint">Provide any variables needed for 'Context Source Key' columns.</div>
      </div>
    </div>
    <div class="sq-modal-footer">
      <button class="btn-secondary" onclick="SQ.closeModal('startRunModal')">Cancel</button>
      <button class="btn-primary" onclick="SQ.confirmStartRun()">Run Extraction</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
window.SQ = (function() {
  'use strict';

  let _tables = [];
  let _tableColumns = {}; // cache: { "table_name": [ {name, data_type}, ... ] }
  let _templates = [];
  let _runs = [];
  let _currentRun = null;
  let _isBusy = false;

  // Pagination state — one entry per paginated list container id.
  let _pg = {
    runsList: { page: 1, limit: 12, total_pages: 1 },
    templatesList: { page: 1, limit: 12, total_pages: 1 }
  };

  async function api(payload, method = 'POST') {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    let url = '/sqlish_api.php';
    if (method === 'GET') {
      url += '?' + new URLSearchParams(payload).toString();
    } else {
      opts.body = JSON.stringify(payload);
    }
    const res = await fetch(url, opts);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data;
  }

  // ── Shared pagination bar wiring ──────────────────────────────────────
  // Wires up the prev/next buttons + editable page-index input for a given
  // list id (e.g. "runsList"), calling `loader(page)` whenever the page
  // should change. Reflects state from _pg[listId] into the bar's controls.
  function bindPager(listId, loader) {
    const prevBtn = document.getElementById(listId + 'PagerPrev');
    const nextBtn = document.getElementById(listId + 'PagerNext');
    const input = document.getElementById(listId + 'PagerInput');
    if (!prevBtn || !nextBtn || !input) return;

    prevBtn.addEventListener('click', () => {
      const st = _pg[listId];
      if (st.page > 1) loader(st.page - 1);
    });
    nextBtn.addEventListener('click', () => {
      const st = _pg[listId];
      if (st.page < st.total_pages) loader(st.page + 1);
    });
    input.addEventListener('change', () => {
      const st = _pg[listId];
      let p = parseInt(input.value, 10);
      if (!Number.isFinite(p) || p < 1) p = 1;
      if (p > st.total_pages) p = st.total_pages;
      loader(p);
    });
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); input.blur(); input.dispatchEvent(new Event('change')); }
    });
  }

  function renderPager(listId) {
    const st = _pg[listId];
    const bar = document.getElementById(listId + 'Pager');
    const input = document.getElementById(listId + 'PagerInput');
    const total = document.getElementById(listId + 'PagerTotal');
    const prevBtn = document.getElementById(listId + 'PagerPrev');
    const nextBtn = document.getElementById(listId + 'PagerNext');
    if (!bar) return;

    bar.style.display = st.total_pages > 1 ? 'flex' : 'none';
    input.value = st.page;
    total.textContent = st.total_pages;
    prevBtn.disabled = st.page <= 1;
    nextBtn.disabled = st.page >= st.total_pages;
  }

  async function init() {
    bindEvents();
    bindPager('runsList', p => loadRuns(p));
    bindPager('templatesList', p => loadTemplates(p));
    try {
      const td = await api({action: 'list_tables'}, 'GET');
      _tables = td.tables || [];
      const sel = document.getElementById('tplTable');
      sel.innerHTML = '<option value="">-- Select Table --</option>' + _tables.map(t => `<option value="${escAttr(t)}">${escHtml(t)}</option>`).join('');
      
      await Promise.all([loadTemplates(), loadRuns()]);
      
      // Auto-open newest run if desired
      if (_runs.length > 0) openRun(_runs[0].id);
      
    } catch(e) { toast(e.message, 'error'); }
  }

  function bindEvents() {
    document.querySelectorAll('.left-tab').forEach(btn => {
      btn.addEventListener('click', () => setLeftTab(btn.dataset.ltab));
    });
    document.querySelectorAll('.sq-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeFlyouts();
        document.querySelectorAll('.sq-modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });
  }

  // ── TEMPLATES ──
  async function loadTemplates(page) {
    page = page || _pg.templatesList.page || 1;
    const d = await api({action: 'list_templates', page, limit: _pg.templatesList.limit}, 'GET');
    _templates = d.templates || [];
    _pg.templatesList = { page: d.page || page, limit: d.limit || _pg.templatesList.limit, total_pages: d.total_pages || 1 };
    const list = document.getElementById('templatesList');
    if (_templates.length === 0) { list.innerHTML = `<div class="empty-state">No templates yet</div>`; renderPager('templatesList'); return; }
    list.innerHTML = _templates.map(t => `
      <div class="sq-item" onclick="SQ.openStartRunModal(${t.id})">
        <div class="sq-item-body">
          <div class="sq-item-title">${escHtml(t.name)}</div>
          <div class="sq-item-meta">
            <span class="status-chip ${t.operation_type}">${t.operation_type}</span>
            <span>${escHtml(t.target_table)}</span>
            <span style="text-decoration:underline; cursor:pointer; margin-left:auto;" onclick="event.stopPropagation(); SQ.openEditTemplateModal(${t.id})">edit</span>
          </div>
        </div>
      </div>`).join('');
    renderPager('templatesList');
  }

  async function ensureTableColumns(table) {
    if (!table) return [];
    if (_tableColumns[table]) return _tableColumns[table];
    const d = await api({action: 'list_columns', table}, 'GET');
    _tableColumns[table] = d.columns || [];
    return _tableColumns[table];
  }

  async function handleTableChange() {
    const table = document.getElementById('tplTable').value;
    const btn = document.getElementById('btnAddCol');
    const keyCol = document.getElementById('tplKeyCol');
    
    if (!table) {
      btn.disabled = true;
      keyCol.innerHTML = '<option value="">-- Wait for table --</option>';
      document.getElementById('colEditorList').innerHTML = '';
      return;
    }
    
    btn.disabled = false;
    const cols = await ensureTableColumns(table);
    
    // Update key column select
    const prevKey = keyCol.value;
    keyCol.innerHTML = '<option value="">-- Select Key Column --</option>' + cols.map(c => `<option value="${escAttr(c.name)}">${escHtml(c.name)}</option>`).join('');
    if (prevKey && cols.find(c => c.name === prevKey)) keyCol.value = prevKey;
    
    // Update existing row selects
    document.querySelectorAll('.col-sel-name').forEach(sel => {
        const prev = sel.value;
        sel.innerHTML = '<option value="">-- Column --</option>' + cols.map(c => `<option value="${escAttr(c.name)}">${escHtml(c.name)}</option>`).join('');
        if (prev && cols.find(c => c.name === prev)) sel.value = prev;
    });
  }

  function handleOpChange() {
      const op = document.getElementById('tplOp').value;
      document.getElementById('wrapKeyCol').style.display = (op === 'update' || op === 'select') ? 'block' : 'none';
  }

  async function openNewTemplateModal() {
    try {
      document.getElementById('templateModalTitle').textContent = 'New Template';
      document.getElementById('tplId').value = '';
      document.getElementById('tplName').value = '';
      document.getElementById('tplDescription').value = '';
      document.getElementById('tplTable').value = '';
      document.getElementById('tplOp').value = 'insert';
      document.getElementById('tplInstruct').value = '';
      document.getElementById('tplWriteback').value = '';
      document.getElementById('btnDeleteTpl').style.display = 'none';
      document.getElementById('colEditorList').innerHTML = '';
      handleOpChange();
      await handleTableChange();
      document.getElementById('templateModal').classList.add('open');
    } catch(e) { console.error(e); }
  }

  async function openEditTemplateModal(id) {
    try {
      const t = _templates.find(x => x.id === id);
      if (!t) return;
      document.getElementById('templateModalTitle').textContent = 'Edit Template';
      document.getElementById('tplId').value = t.id;
      document.getElementById('tplName').value = t.name || '';
      document.getElementById('tplDescription').value = t.description || '';
      document.getElementById('tplTable').value = t.target_table || '';
      await handleTableChange(); // loads columns
      
      document.getElementById('tplOp').value = t.operation_type || 'insert';
      handleOpChange();
      if (t.key_column) document.getElementById('tplKeyCol').value = t.key_column;
      
      document.getElementById('tplInstruct').value = t.ai_instructions || '';
      document.getElementById('tplWriteback').value = t.writeback_context_key || '';
      document.getElementById('btnDeleteTpl').style.display = 'inline-flex';

      document.getElementById('colEditorList').innerHTML = '';
      (t.columns || []).forEach(c => addColEditorRow(c));

      document.getElementById('templateModal').classList.add('open');
    } catch(e) { console.error(e); }
  }

  function addColEditorRow(col = {}) {
    const list = document.getElementById('colEditorList');
    const row = document.createElement('div');
    row.className = 'col-editor-row';
    
    const table = document.getElementById('tplTable').value;
    const availableCols = _tableColumns[table] || [];
    const opts = '<option value="">-- Column --</option>' + availableCols.map(c => `<option value="${escAttr(c.name)}" ${col.column_name === c.name ? 'selected' : ''}>${escHtml(c.name)}</option>`).join('');

    row.innerHTML = `
      <div class="col-editor-head">
        <select class="ctx-select col-sel-name" style="width:50%;">${opts}</select>
        <button type="button" class="col-remove-btn" onclick="this.closest('.col-editor-row').remove()"><i class="bi bi-x"></i></button>
      </div>
      <div class="col-editor-grid">
        <div class="ctx-group" style="margin:0;">
          <label style="display:flex;align-items:center;gap:6px;font-family:var(--mono);font-size:0.65rem;color:var(--text);cursor:pointer;">
            <input type="checkbox" class="col-chk-ai" ${col.is_ai_fillable ? 'checked' : ''} onchange="SQ.toggleColAi(this)"> AI Fillable
          </label>
        </div>
        <div class="ctx-group" style="margin:0;">
          <label style="display:flex;align-items:center;gap:6px;font-family:var(--mono);font-size:0.65rem;color:var(--text);cursor:pointer;">
            <input type="checkbox" class="col-chk-req" ${col.is_required ? 'checked' : ''}> Required (if AI)
          </label>
        </div>
      </div>
      <div class="col-opts-ai" style="display:${col.is_ai_fillable ? 'block' : 'none'}; margin-top:8px;">
        <input type="text" class="ctx-input col-desc" placeholder="AI Description (e.g. 'Character name')" value="${escAttr(col.description_for_ai || '')}">
      </div>
      <div class="col-opts-sys" style="display:${!col.is_ai_fillable ? 'block' : 'none'}; margin-top:8px;">
        <div style="display:flex; gap:8px;">
          <input type="text" class="ctx-input col-def" placeholder="Default Value (e.g. 'active')" value="${escAttr(col.default_value || '')}">
          <input type="text" class="ctx-input col-ctx" placeholder="Context Key (e.g. 'episode_id')" value="${escAttr(col.context_source_key || '')}">
        </div>
      </div>
    `;
    list.appendChild(row);
  }

  function toggleColAi(chk) {
      const row = chk.closest('.col-editor-row');
      row.querySelector('.col-opts-ai').style.display = chk.checked ? 'block' : 'none';
      row.querySelector('.col-opts-sys').style.display = !chk.checked ? 'block' : 'none';
  }

  async function saveTemplate() {
    const id = document.getElementById('tplId').value;
    const name = document.getElementById('tplName').value.trim();
    const target_table = document.getElementById('tplTable').value;
    const op = document.getElementById('tplOp').value;
    const key_column = document.getElementById('tplKeyCol').value;

    if (!name || !target_table) { toast('Name and Table required', 'error'); return; }
    if ((op === 'update' || op === 'select') && !key_column) { toast('Key column required for Update/Select', 'error'); return; }

    const rows = document.querySelectorAll('#colEditorList .col-editor-row');
    if (rows.length === 0) { toast('Add at least one column', 'error'); return; }

    const columns = [];
    for (const row of rows) {
      const cname = row.querySelector('.col-sel-name').value;
      if (!cname) continue;
      
      const availableCols = _tableColumns[target_table] || [];
      const colDef = availableCols.find(x => x.name === cname);
      
      columns.push({
        column_name: cname,
        data_type: colDef ? colDef.data_type : null,
        is_ai_fillable: row.querySelector('.col-chk-ai').checked ? 1 : 0,
        is_required: row.querySelector('.col-chk-req').checked ? 1 : 0,
        description_for_ai: row.querySelector('.col-desc').value.trim(),
        default_value: row.querySelector('.col-def').value.trim() || null,
        context_source_key: row.querySelector('.col-ctx').value.trim() || null
      });
    }

    if (columns.length === 0) { toast('Valid columns required', 'error'); return; }

    const tpl = {
      id: id ? parseInt(id) : null,
      name,
      description: document.getElementById('tplDescription').value.trim(), 
      ai_instructions: document.getElementById('tplInstruct').value.trim(),
      target_table,
      operation_type: op,
      key_column: key_column || null,
      writeback_context_key: document.getElementById('tplWriteback').value.trim() || null,
      columns
    };

    try {
      await api({ action: 'save_template', template: tpl });
      closeModal('templateModal');
      toast('Template saved', 'success');
      loadTemplates(_pg.templatesList.page);
    } catch (e) { toast(e.message, 'error'); }
  }

  async function deleteTemplate() {
    const id = document.getElementById('tplId').value;
    if (!id || !confirm('Delete this template?')) return;
    try {
      await api({ action: 'delete_template', id: parseInt(id) });
      closeModal('templateModal');
      toast('Deleted', 'info');
      loadTemplates(_pg.templatesList.page);
    } catch (e) { toast(e.message, 'error'); }
  }

  // ── RUNS ──
  async function loadRuns(page) {
    page = page || _pg.runsList.page || 1;
    const d = await api({action: 'list_runs', page, limit: _pg.runsList.limit}, 'GET');
    _runs = d.runs || [];
    _pg.runsList = { page: d.page || page, limit: d.limit || _pg.runsList.limit, total_pages: d.total_pages || 1 };
    const list = document.getElementById('runsList');
    if (_runs.length === 0) { list.innerHTML = `<div class="empty-state">No runs yet</div>`; renderPager('runsList'); return; }
    list.innerHTML = _runs.map(r => `
      <div class="sq-item ${_currentRun && _currentRun.id === r.id ? 'active' : ''}" onclick="SQ.openRun(${r.id})">
        <div class="sq-item-body">
          <div class="sq-item-title">${escHtml(r.title)}</div>
          <div class="sq-item-meta">
            <span class="status-chip ${r.status}">${r.status}</span>
            <span>${escHtml(r.template_name)}</span>
          </div>
        </div>
      </div>`).join('');
    renderPager('runsList');
  }

  function openStartRunModal(tplId) {
    document.getElementById('startRunTplId').value = tplId;
    document.getElementById('startRunSource').value = '';
    document.getElementById('startRunContext').value = '';
    document.getElementById('startRunModal').classList.add('open');
  }

 async function confirmStartRun() {
    const template_id = document.getElementById('startRunTplId').value;
    const source_text = document.getElementById('startRunSource').value.trim();
    const ctxRaw = document.getElementById('startRunContext').value.trim();
    let context = {};
    if (ctxRaw) {
        try { context = JSON.parse(ctxRaw); } catch(e) { toast('Invalid JSON Context', 'error'); return; }
    }
    
    if (!source_text && !confirm('Source text is empty. Run anyway?')) return;
    
    document.getElementById('startRunModal').classList.remove('open');
    toast('Starting extraction...', 'info');
    showRunningState(template_id);
    
    try {
      const d = await api({ action: 'start_run', template_id: parseInt(template_id), source_text, context });
      toast('Run complete', 'success');
      await loadRuns(1);
      openRun(d.run.id);
      setLeftTab('runs');
    } catch (e) {
      toast(e.message, 'error');
      renderRun(); // fall back to last known state (clears the pending placeholder)
    }
  }

  function showRunningState(templateId) {
    _currentRun = null;
    const tpl = _templates.find(x => x.id === parseInt(templateId));
    const feed = document.getElementById('feed');
    feed.innerHTML = `
      <div class="run-header">
        <div class="run-title">${escHtml(tpl ? tpl.name : 'Extraction')} <span class="status-chip running">running</span></div>
        <div class="run-meta">
            <span class="status-chip running"><i class="bi bi-hourglass-split"></i> running</span>
            ${tpl ? `<span>Table: <strong>${escHtml(tpl.target_table)}</strong></span><span>Operation: <strong>${escHtml(tpl.operation_type)}</strong></span>` : ''}
        </div>
      </div>
      <div class="empty-state"><i class="bi bi-hourglass-split"></i> Extracting and writing records — this may take a moment...</div>
    `;
  }
  
  /*
  async function confirmStartRun() {
    const template_id = document.getElementById('startRunTplId').value;
    const source_text = document.getElementById('startRunSource').value.trim();
    const ctxRaw = document.getElementById('startRunContext').value.trim();
    let context = {};
    if (ctxRaw) {
        try { context = JSON.parse(ctxRaw); } catch(e) { toast('Invalid JSON Context', 'error'); return; }
    }
    
    if (!source_text && !confirm('Source text is empty. Run anyway?')) return;
    
    document.getElementById('startRunModal').classList.remove('open');
    toast('Starting extraction...', 'info');
    
    try {
      const d = await api({ action: 'start_run', template_id: parseInt(template_id), source_text, context });
      toast('Run complete', 'success');
      await loadRuns();
      openRun(d.run.id);
      setLeftTab('runs');
    } catch (e) { toast(e.message, 'error'); }
  }
  */

  async function openRun(id) {
    try {
      const d = await api({action: 'get_run', id}, 'GET');
      _currentRun = d.run;
      renderRun();
      loadRuns(_pg.runsList.page); // update active state in sidebar
      closeFlyouts();
    } catch (e) { toast(e.message, 'error'); }
  }

  function renderRun() {
    const feed = document.getElementById('feed');
    if (!_currentRun) { feed.innerHTML = ''; return; }
    
    let html = `
      <div class="run-header">
        <div class="run-title">${escHtml(_currentRun.title)}</div>
        <div class="run-meta">
            <span class="status-chip ${_currentRun.status}">${_currentRun.status}</span>
            <span>Table: <strong>${escHtml(_currentRun.target_table)}</strong></span>
            <span>Operation: <strong>${escHtml(_currentRun.operation_type)}</strong></span>
            <span>Written: <strong style="color:var(--green);">${_currentRun.items_written}</strong></span>
            <span>Failed: <strong style="color:var(--red);">${_currentRun.items_failed}</strong></span>
            <span>${formatTime(_currentRun.created_at)}</span>
        </div>
      </div>
    `;

    if (_currentRun.last_error) {
        html += `<div class="record-item status-failed" style="margin-bottom:16px;">
            <div class="record-error" style="margin:0;"><i class="bi bi-exclamation-triangle"></i> ${escHtml(_currentRun.last_error)}</div>
        </div>`;
    }

    if (_currentRun.items && _currentRun.items.length > 0) {
        html += `<div class="record-list">`;
        _currentRun.items.forEach((it, i) => {
            const isFail = it.status === 'failed';
            html += `
            <div class="record-item status-${it.status}">
                <div class="record-header">
                    <span class="record-num">Record #${i+1}</span>
                    <span class="status-chip ${it.status}">${it.status}</span>
                </div>
                <div style="font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); margin-bottom:4px;">Extracted Values:</div>
                <div class="record-code">${escHtml(JSON.stringify(it.extracted_values, null, 2))}</div>
            `;
            if (it.final_values) {
                html += `
                <div style="font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); margin-top:8px; margin-bottom:4px;">Final Values (incl. system/context):</div>
                <div class="record-code" style="border-color:var(--blue-glow);">${escHtml(JSON.stringify(it.final_values, null, 2))}</div>
                `;
            }
            if (it.written_row_id) {
                html += `<div style="margin-top:8px; font-family:var(--mono); font-size:0.7rem; color:var(--green);">Inserted ID: <strong>${it.written_row_id}</strong></div>`;
            }
            if (it.error_text) {
                html += `<div class="record-error"><i class="bi bi-exclamation-triangle"></i> ${escHtml(it.error_text)}</div>`;
            }
            html += `</div>`;
        });
        html += `</div>`;
    } else {
        html += `<div class="empty-state">No records extracted.</div>`;
    }

    feed.innerHTML = html;
  }

  // ── FLYOUTS & UTILS ──
  function toggleFlyout(side) {
    const el = document.getElementById('flyoutLeft');
    const h = document.getElementById('hamburgerLeft');
    const bd = document.getElementById('flyoutBackdrop');
    if (el.classList.contains('open')) { closeFlyouts(); return; }
    el.classList.add('open'); h.classList.add('open'); bd.classList.add('visible');
  }
  function closeFlyouts() {
    document.getElementById('flyoutLeft').classList.remove('open');
    document.getElementById('hamburgerLeft').classList.remove('open');
    document.getElementById('flyoutBackdrop').classList.remove('visible');
  }
  function setLeftTab(tab) {
    document.querySelectorAll('.left-tab').forEach(t => t.classList.toggle('active', t.dataset.ltab === tab));
    document.querySelectorAll('.left-tab-content').forEach(c => c.classList.toggle('active', c.id === `ltab-${tab}`));
  }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  function formatTime(dStr) { if(!dStr) return ''; return new Date(dStr + ' Z').toLocaleString(); }
  function escHtml(s) { if (s === null || s === undefined) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
  function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
  function toast(msg, type) {
    const el = document.createElement('div'); el.className = `toast ${type}`;
    el.innerHTML = `<span>${type==='success'?'✓':(type==='error'?'✕':'▤')}</span> ${escHtml(msg)}`;
    el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); };
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); }, 3000);
  }

  return {
    init, toggleFlyout, closeFlyouts, closeModal,
    openNewTemplateModal, openEditTemplateModal, saveTemplate, deleteTemplate,
    handleTableChange, handleOpChange, addColEditorRow, toggleColAi,
    openStartRunModal, confirmStartRun, openRun
  };
})();

document.addEventListener('DOMContentLoaded', () => { if(window.SQ) window.SQ.init(); });
</script>


<?php echo $eruda ?? ''; ?>


</body>
</html>
