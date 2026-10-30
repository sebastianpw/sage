<?php
// public/mermaids.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Story Threads & Timelines";

ob_start();
?>
<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch(e) {}
})();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css"/>
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" id="tui-dark-theme" disabled/>
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
    --lane-bg: #ebf0f4;
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    --lane-bg: #10141a;
}
@media (prefers-color-scheme:dark) {
    :root:not([data-theme="light"]) {
        --bg:#0d1117; --card:#161b22; --border:#30363d;
        --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
        --lane-bg: #10141a;
    }
}

* { box-sizing: border-box; }
body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100vh; overflow:hidden; }

.layout { display: flex; height: 100vh; flex-direction: column; }

.topbar {
    height: 52px; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; padding: 0 12px; gap: 10px; flex-shrink: 0; z-index: 10;
}
.topbar h2 { margin:0; font-size:1rem; display:flex; align-items:center; gap:8px; }
.topbar-right { margin-left:auto; display:flex; gap:6px; align-items:center; }

.btn {
    padding: 6px 11px; border-radius:6px; border:none; cursor:pointer;
    font-weight:600; font-size:0.85rem; display:inline-flex; align-items:center; gap:5px;
    text-decoration:none; white-space:nowrap; transition: background 0.15s, opacity 0.15s;
}
.btn:active { transform: scale(0.97); }
.btn-primary { background:var(--accent); color:#fff; }
.btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
.btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
.btn-danger { background:rgba(218,54,51,0.1); color:var(--red); border:1px solid rgba(218,54,51,0.2); }
.btn-sm { padding:4px 8px; font-size:0.78rem; }
.btn-icon.filtered { border-color: var(--orange); color: var(--orange); }

.btn-group { display: flex; }
.btn-group .btn { border-radius: 0; }
.btn-group .btn:first-child { border-radius: 6px 0 0 6px; }
.btn-group .btn:last-child { border-radius: 0 6px 6px 0; }
.btn-group .btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

.main-area { flex: 1; position: relative; overflow: auto; background: var(--bg); display: flex; flex-direction: column; }
.view-container { flex: 1; overflow: auto; display: none; flex-direction: column; }
.view-container.active { display: flex; }

/* ── GRID (Editor) View ── */
.grid-table { border-collapse: separate; border-spacing: 0; min-width: 100%; font-family: sans-serif; }
.grid-table th, .grid-table td {
    border-right: 1px solid var(--border); border-bottom: 1px solid var(--border);
    padding: 6px; vertical-align: top;
}
.grid-table thead th {
    background: var(--card); position: sticky; top: 0; z-index: 10;
    border-top: 1px solid var(--border); font-weight:700;
    text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted);
    font-size: 8px;
    min-width: 140px;
}
.grid-table thead th.col-time {
    text-align: left; left: 0; z-index: 11; border-left: 1px solid var(--border);
    min-width: 120px; width: 120px; font-size: 11px;
}
.grid-table tbody th.col-time {
    position: sticky; left: 0; background: var(--card); z-index: 9;
    border-left: 1px solid var(--border); font-weight: 600;
    text-align: left; font-size: 0.85rem; color: var(--text);
    box-shadow: 2px 0 5px rgba(0,0,0,0.05);
}

.grid-cell { background: var(--bg); transition: background 0.15s; padding: 6px; min-width: 140px; }
.grid-cell.drag-over { background: rgba(59,130,246,0.1); }
.grid-cell .cell-inner { 
    display: flex; flex-direction: column; gap: 20px;
    min-height: 60px; height: 100%; border-radius: 6px; align-items: stretch;
}

.grid-cell .cell-inner .beat-card { position: relative; z-index: 2; }
.grid-cell .cell-inner .beat-card:not(:last-child)::after {
    content: '\F128';
    font-family: "bootstrap-icons" !important;
    position: absolute; bottom: -18px; left: 50%; transform: translateX(-50%);
    color: var(--text-muted); opacity: 0.5; font-size: 14px; pointer-events: none;
}

.thread-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; vertical-align: middle; }

/* ── Beat Card ── */
.beat-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 6px;
    padding: 8px 10px; cursor: grab; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex; flex-direction: column; gap: 4px;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.beat-card:active { cursor: grabbing; }
.beat-card:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.beat-card.sortable-ghost { opacity: 0.4; background: var(--lane-bg); border: 2px dashed var(--accent); }
.sortable-ghost::after, .sortable-drag::after { display: none !important; }

.beat-header { display: flex; justify-content: space-between; align-items: flex-start; }
.beat-title { font-weight: 600; font-size: 0.85rem; line-height: 1.3; }
.beat-type-pill {
    font-size: 0.62rem; padding: 1px 5px; border-radius: 4px; font-weight: 700;
    text-transform: uppercase; background: rgba(100,116,139,0.1); color: var(--text-muted);
}
.beat-meta { font-size: 0.72rem; color: var(--text-muted); display: flex; justify-content: space-between; gap: 6px; }

/* ── Strict Timeline (Matrix) View ── */
.timeline-table { border-collapse: separate; border-spacing: 0; min-width: 100%; font-family: sans-serif; font-size: 0.85rem; }
.timeline-table th, .timeline-table td { 
    border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); 
    padding: 10px; vertical-align: top; min-width: 140px; 
}
.timeline-table th { 
    background: var(--card); position: sticky; top: 0; z-index: 10; 
    border-top: 1px solid var(--border); font-weight:700; 
    text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); font-size: 8px;
}
.timeline-table th.col-thread { 
    text-align: left; left: 0; z-index: 11; border-left: 1px solid var(--border); min-width:140px; font-size: 11px;
}
.timeline-table td.col-thread { 
    text-align: left; position: sticky; left: 0; background: var(--card); z-index: 9; 
    border-left: 1px solid var(--border); font-weight: 600; box-shadow: 2px 0 5px rgba(0,0,0,0.05); 
}

.timeline-cell { background: var(--bg); transition: background 0.15s; padding: 10px; }
.timeline-cell.drag-over { background: rgba(59,130,246,0.1); }
.timeline-cell-content { 
    display:flex; flex-direction:row; gap:24px;
    align-items: flex-start; min-height: 60px; 
    width: max-content; /* Forces table col to stretch */
    padding-right: 4px;
}

.timeline-cell-content .beat-card { 
    min-width: 160px; max-width: 220px; 
    flex-shrink: 0; position: relative; z-index: 2; 
}
.timeline-cell-content .beat-card:not(:last-child)::after {
    content: '\F133';
    font-family: "bootstrap-icons" !important;
    position: absolute; right: -18px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); opacity: 0.5; font-size: 14px; pointer-events: none;
}

@media (max-width: 767px) {
 .timeline-table { display: block; overflow-x: auto; }
 .timeline-cell-content { flex-wrap: wrap; }
}

/* ── Arc Tracker (Mobile First) ── */
#tracker-view {
  display: none;
  flex-direction: column;
  height: 100%;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
}
#tracker-view.active {
  display: flex;
}
.char-selector-wrap {
  padding: 12px;
  background: var(--card);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 5;
}
.phase-summary {
  padding: 12px;
  display: flex;
  gap: 8px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.phase-summary::-webkit-scrollbar { display: none; }
.phase-chip {
  flex: 0 0 auto;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 10px 14px;
  min-height: 44px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  font-size: 0.85rem;
  color: var(--text);
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}
.phase-chip:hover { border-color: var(--accent); }
.phase-chip strong { font-size: 0.9rem; }

/* Arc Tracker Selection States */
.phase-chip.is-active {
  background: var(--accent);
  color: #fff;
  border-color: var(--accent);
}
.phase-chip.is-active span {
  color: rgba(255,255,255,0.85) !important;
}
.phase-chip.is-diff-include {
  background: rgba(35, 134, 54, 0.15);
  border-color: var(--green);
  color: var(--text);
}
.phase-chip.is-diff-exclude {
  background: rgba(218, 54, 51, 0.15);
  border-color: var(--red);
  color: var(--red);
}

.phase-list {
  padding: 0 12px 80px 12px; 
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.phase-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
}
.phase-card summary {
  list-style: none;
  padding: 14px 16px;
  min-height: 44px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  font-weight: 600;
}
.phase-card summary::-webkit-details-marker { display: none; }
.phase-card[open] summary { border-bottom: 1px solid var(--border); }
.phase-card summary i { transition: transform 0.2s; }
.phase-card[open] summary i { transform: rotate(180deg); }
.beat-row {
  padding: 12px 16px;
  min-height: 44px;
  border-bottom: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 4px;
  cursor: pointer;
}
.beat-row:last-child { border-bottom: none; }
.beat-row:active { background: rgba(59,130,246,0.08); }

/* Bottom sheet for Android */
.sheet-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4);
  display: none; z-index: 8000;
}
.sheet-overlay.active { display: block; }
.bottom-sheet {
  position: fixed; left: 0; right: 0; bottom: 0;
  background: var(--card); border-top: 1px solid var(--border);
  border-radius: 16px 16px 0 0;
  max-height: 70vh;
  transform: translateY(100%);
  transition: transform 0.3s cubic-bezier(0.2,0.8,0.2,1);
  z-index: 8001;
  display: flex; flex-direction: column;
}
.bottom-sheet.open { transform: translateY(0); }
.sheet-handle {
  width: 36px; height: 4px; background: var(--border);
  border-radius: 2px; margin: 10px auto;
}
.sheet-header {
  padding: 0 16px 12px 16px;
  display: flex; justify-content: space-between; align-items: center;
  border-bottom: 1px solid var(--border);
}
.sheet-list { overflow-y: auto; padding: 12px; }

/* Desktop enhancement */
@media (min-width: 768px) {
  #tracker-view.active { flex-direction: row; }
  .char-selector-wrap {
    width: 290px; flex-shrink: 0; position: static;
    border-right: 1px solid var(--border); border-bottom: none;
    overflow-y: auto;
  }
  #charSheetTrigger { display: none !important; }
  #charChips { flex-direction: column; overflow: visible; }
  .phase-summary { flex-wrap: wrap; overflow: visible; }
  .phase-list { flex: 1; }
}

/* ── Forms & Modals ── */
.input-control {
    width: 100%; padding: 8px 10px; border-radius: 6px;
    border: 1px solid var(--border); background: var(--bg);
    color: var(--text); font-size: 0.9rem; font-family: inherit;
    margin-bottom: 12px;
}
.input-control:focus { outline: none; border-color: var(--accent); }
textarea.input-control { resize: vertical; min-height: 60px; }
label.field-label {
    display: block; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); margin-bottom: 4px;
}

.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.55);
    display: none; align-items: center; justify-content: center; z-index: 9000;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--card); border: 1px solid var(--border); border-radius: 10px;
    padding: 20px; width: 100%; max-width: 540px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.modal-box h3 { margin: 0 0 16px 0; font-size: 1.1rem; }
.modal-actions { display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-top: 10px; flex-wrap: wrap; }

/* ── Chips for Export ── */
.chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 8px; border-radius: 12px; font-size: 0.75rem;
    background: rgba(59,130,246,0.1); color: var(--accent);
    border: 1px solid rgba(59,130,246,0.3); font-weight: 600;
}
.chip .chip-remove { cursor: pointer; color: inherit; opacity: 0.7; font-size:0.8rem; }
.chip .chip-remove:hover { opacity: 1; }
.chip-series { background: rgba(239,68,68,0.1); color: #ef4444; border-color: rgba(239,68,68,0.3); }
.chip-season { background: rgba(16,185,129,0.1); color: #10b981; border-color: rgba(16,185,129,0.3); }
.chip-episode { background: rgba(245,158,11,0.1); color: #f59e0b; border-color: rgba(245,158,11,0.3); }

/* ── Slide-over Panel (Beat Inspector) ── */
.slide-panel-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    display: none; z-index: 8000; transition: opacity 0.3s; opacity: 0;
}
.slide-panel-overlay.active { display: block; opacity: 1; }
.slide-panel {
    position: fixed; top: 0; right: 0; bottom: 0; width: 100%; max-width: 600px;
    background: var(--card); border-left: 1px solid var(--border);
    box-shadow: -10px 0 30px rgba(0,0,0,0.2); z-index: 8001;
    display: flex; flex-direction: column; transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.slide-panel.open { transform: translateX(0); }
.slide-header {
    padding: 16px; border-bottom: 1px solid var(--border); background: var(--bg);
    display: flex; justify-content: space-between; align-items: center;
}
.slide-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; min-height: 0; }
.slide-footer { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; }

.panel-tabs { display: flex; border-bottom: 1px solid var(--border); background: var(--card); }
.panel-tab {
    flex: 1; text-align: center; padding: 10px; font-size: 0.85rem; font-weight: 600;
    color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent;
}
.panel-tab:hover { background: rgba(59,130,246,0.05); }
.panel-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.panel-tab-content { display: none; padding: 16px; flex: 1; flex-direction: column; min-height:0; }
.panel-tab-content.active { display: flex; }

.list-item-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 8px; background: var(--bg);
}
.search-results {
    border: 1px solid var(--border); border-radius: 6px; background: var(--card);
    max-height: 150px; overflow-y: auto; display: none; position: absolute; width: 100%; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.search-result-item { padding: 8px 10px; cursor: pointer; font-size: 0.85rem; border-bottom: 1px solid var(--border); }
.search-result-item:hover { background: rgba(59,130,246,0.1); }

/* Toast */
#toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 99999;
    background: var(--card); color: var(--text); border: 1px solid var(--border);
    border-left: 4px solid var(--green); border-radius: 6px; padding: 12px 18px;
    font-size: 0.9rem; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
</style>

<div class="layout">
    <div class="topbar">
        <h2><i class="bi bi-clock-history" style="color:var(--accent);"></i> Story Timelines</h2>

        <div class="btn-group" style="margin-left:20px;">
            <button class="btn btn-ghost active" id="btn-view-editor" onclick="switchMainView('editor')"><i class="bi bi-grid-3x3-gap"></i> Grid</button>
            <button class="btn btn-ghost" id="btn-view-timeline" onclick="switchMainView('timeline')"><i class="bi bi-distribute-horizontal"></i> Timeline</button>
            <button class="btn btn-ghost" id="btn-view-tracker" onclick="switchMainView('tracker')"><i class="bi bi-person-lines-fill"></i> Arc Tracker</button>
        </div>

        <div class="topbar-right">
            <button class="btn btn-ghost btn-sm btn-icon" id="btnExport" onclick="openExportModal()" title="Export Timelines">
                <i class="bi bi-download"></i>
            </button>
            <button class="btn btn-ghost btn-sm btn-icon" id="btnFilter" onclick="openFilterModal()" title="Filter Scope">
                <i class="bi bi-funnel"></i>
            </button>
            
            <button class="btn btn-primary btn-sm" onclick="openThreadModal()"><i class="bi bi-plus-lg"></i> Add Thread</button>
            <button style="display:none;" class="btn btn-ghost btn-sm" id="btnTheme" title="Toggle theme">☀️</button>
        </div>
    </div>

    <div class="main-area">
        <!-- GRID VIEW (Vertical swimlanes, horizontal times) -->
        <div id="editor-view" class="view-container active"></div>

        <!-- TIMELINE VIEW (Horizontal timeline matrix) -->
        <div id="timeline-view" class="view-container"></div>
        
        <!-- ARC TRACKER VIEW (Mobile First Character Beats) -->
        <div id="tracker-view" class="view-container">
            <div id="charSelectorWrap" class="char-selector-wrap">
                <!-- On the fly instant search input -->
                <div style="position:relative; margin-bottom:8px;">
                    <input type="text" id="trackerSearchInput" class="input-control" placeholder="Search beats (#id, title, content, role)..." style="min-height:44px; margin-bottom:0; padding-right:32px;" oninput="onTrackerSearchInput(this.value)">
                    <button type="button" id="trackerSearchClearBtn" onclick="clearTrackerSearch()" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:transparent; color:var(--text-muted); cursor:pointer; display:none; padding:4px;">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>

                <div id="trackerModeSection">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="field-label" style="margin:0;">Filter Mode</label>
                        <span id="trackerSelectionInfo" style="font-size:0.75rem; color:var(--text-muted); font-weight:600;"></span>
                    </div>
                    <select id="trackerFilterMode" class="input-control" style="min-height:44px; margin-bottom:8px;" onchange="onTrackerModeChange(this.value)">
                        <option value="single">Single Character</option>
                        <option value="and">All Selected (AND / Intersection)</option>
                        <option value="or">Any Selected (OR / Broad Union)</option>
                        <option value="diff">Has First, Not Second (A without B)</option>
                    </select>

                    <!-- Alternative bottom sheet trigger for Android -->
                    <button id="charSheetTrigger" class="btn btn-ghost" style="display:none; min-height:44px; width:100%; margin-bottom:6px;" onclick="openCharBottomSheet()">
                        <i class="bi bi-people"></i> Browse all characters
                    </button>
                    <div id="charChips" style="display:flex; gap:6px; overflow-x:auto; padding:6px 0; -webkit-overflow-scrolling:touch;"></div>
                </div>
            </div>

            <div id="charPhaseSummary" class="phase-summary"></div>
            <div id="charPhaseList" class="phase-list"></div>
        </div>
    </div>
</div>

<!-- Bottom sheet for character browsing on mobile (Arc Tracker) -->
<div id="charBottomSheetOverlay" class="sheet-overlay" onclick="closeCharBottomSheet()"></div>
<div id="charBottomSheet" class="bottom-sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <h3 style="margin:0; font-size:1.1rem;">Characters</h3>
        <button class="btn btn-ghost btn-sm" onclick="closeCharBottomSheet()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="charSheetList" class="sheet-list"></div>
</div>

<!-- Export Modal (Cascading Chips) -->
<div class="modal-overlay" id="modalExport">
    <div class="modal-box" style="max-width: 600px;">
        <h3><i class="bi bi-download"></i> Export Timelines</h3>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:12px;">Add filters to include specific series, seasons, or episodes. Leave empty to export everything.</p>

        <div style="display:flex; gap:10px; margin-bottom:12px; align-items:center;">
            <label style="width:50px; font-size:0.75rem; font-weight:bold;">Series</label>
            <select id="exportSeries" class="input-control" style="margin-bottom:0; flex:1;" onchange="updateExportDropdowns('series')"></select>
            <button class="btn btn-ghost" onclick="addExportChip('series')" title="Add Series to export"><i class="bi bi-plus-lg"></i></button>
        </div>
        <div style="display:flex; gap:10px; margin-bottom:12px; align-items:center;">
            <label style="width:50px; font-size:0.75rem; font-weight:bold;">Season</label>
            <select id="exportSeason" class="input-control" style="margin-bottom:0; flex:1;" onchange="updateExportDropdowns('season')"></select>
            <button class="btn btn-ghost" onclick="addExportChip('season')" title="Add Season to export"><i class="bi bi-plus-lg"></i></button>
        </div>
        <div style="display:flex; gap:10px; margin-bottom:16px; align-items:center;">
            <label style="width:50px; font-size:0.75rem; font-weight:bold;">Episode</label>
            <select id="exportEpisode" class="input-control" style="margin-bottom:0; flex:1;"></select>
            <button class="btn btn-ghost" onclick="addExportChip('episode')" title="Add Episode to export"><i class="bi bi-plus-lg"></i></button>
        </div>

        <div id="exportChipsContainer" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; min-height:50px; border:1px dashed var(--border); padding:10px; border-radius:6px; background:var(--bg);">
        </div>

        <div class="modal-actions" style="border-top:1px solid var(--border); padding-top:16px; justify-content: space-between; align-items: center;">
            <div id="exportStats" style="font-size:0.75rem; color:var(--text-muted); font-style:italic;">Loading...</div>
            <div style="display:flex; gap:6px;">
                <button class="btn btn-ghost" onclick="closeModal('modalExport')">Cancel</button>
                <button class="btn btn-primary" onclick="executeExport('md')" title="Downloads chronologically sorted markdown"><i class="bi bi-filetype-md"></i> MD</button>
                <button class="btn btn-ghost" style="color:var(--accent); border-color:var(--accent);" onclick="executeExport('json')"><i class="bi bi-filetype-json"></i> JSON</button>
                <button class="btn btn-ghost" style="color:var(--orange); border-color:var(--orange);" onclick="executeExport('sql')"><i class="bi bi-filetype-sql"></i> SQL</button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal (Cascading) -->
<div class="modal-overlay" id="modalFilter">
    <div class="modal-box">
        <h3><i class="bi bi-funnel"></i> Filter Scope</h3>
        
        <label class="field-label">Series</label>
        <select id="filterSeries" class="input-control" onchange="handleSeriesFilterChange()"></select>
        
        <label class="field-label">Season</label>
        <select id="filterSeason" class="input-control" onchange="updateFilterDropdowns('season')"></select>
        
        <label class="field-label">Episode</label>
        <select id="filterEpisode" class="input-control" onchange="refreshFilterStats()"></select>

        <div id="seriesStartBlock" style="display:none;">
            <hr style="border:0; border-top:1px solid var(--border); margin: 15px 0;">
            <label class="field-label">Series Anchor Date <br><span style="text-transform:none; font-weight:normal; opacity:0.8;">(Shifts ALL beats in this series simultaneously)</span></label>
            <div style="display:flex; gap:10px;">
                <input type="datetime-local" id="seriesStartDate" class="input-control" style="margin-bottom:0;">
                <button class="btn btn-ghost" onclick="shiftSeriesStart()">Apply Shift</button>
            </div>
        </div>

        <div class="modal-actions" style="margin-top:20px; justify-content: space-between; align-items: center;">
            <div id="filterStats" style="font-size:0.75rem; color:var(--text-muted); font-style:italic;">Loading...</div>
            <div style="display:flex; gap:6px;">
                <button class="btn btn-ghost" onclick="closeModal('modalFilter')">Cancel</button>
                <button class="btn btn-ghost" onclick="clearFilters()">Clear</button>
                <button class="btn btn-primary" onclick="applyFilter()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Thread Modal -->
<div class="modal-overlay" id="modalThread">
    <div class="modal-box">
        <h3 id="modalThreadTitle">Add Thread</h3>
        <input type="hidden" id="threadId">

        <label class="field-label">Name</label>
        <input type="text" id="threadName" class="input-control" placeholder="e.g. Protagonist Journey">

        <div style="display:flex; gap:10px;">
            <div style="flex:1;">
                <label class="field-label">Series</label>
                <input type="text" id="threadSeries" class="input-control" list="seriesList">
                <datalist id="seriesList"></datalist>
            </div>
            <div style="flex:1;">
                <label class="field-label">Season</label>
                <input type="text" id="threadSeason" class="input-control" list="seasonList">
                <datalist id="seasonList"></datalist>
            </div>
        </div>
        <label class="field-label">Episode</label>
        <input type="text" id="threadEpisode" class="input-control" list="episodeList">
        <datalist id="episodeList"></datalist>

        <label class="field-label">Color (Hex)</label>
        <div style="display:flex; gap:10px;">
            <input type="color" id="threadColorPicker" style="width:40px; height:38px; border-radius:6px; cursor:pointer; padding:0;">
            <input type="text" id="threadColor" class="input-control" placeholder="#3b82f6" style="margin-bottom:0;">
        </div>
        <div style="margin-bottom:12px;"></div>

        <label class="field-label">Description (Optional)</label>
        <textarea id="threadDesc" class="input-control"></textarea>

        <div class="modal-actions" style="justify-content: flex-end;">
            <button class="btn btn-ghost" onclick="closeModal('modalThread')">Cancel</button>
            <button class="btn btn-primary" onclick="saveThread()">Save Thread</button>
        </div>
    </div>
</div>

<!-- Beat Inspector (Slide-over) -->
<div class="slide-panel-overlay" id="slideOverlay" onclick="closeBeatInspector()"></div>
<div class="slide-panel" id="beatInspector">
    <div class="slide-header">
        <div style="flex:1; display:flex; flex-direction:column; gap:4px;">
            <input type="text" id="inspBeatTitle" class="input-control" style="font-size:1.1rem; font-weight:700; margin:0; border:none; background:transparent; padding:0;" placeholder="Beat Title...">
            <div style="font-size:0.75rem; color:var(--text-muted);" id="inspBeatMeta">Thread Name</div>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="closeBeatInspector()"><i class="bi bi-x-lg"></i></button>
    </div>

    <div class="panel-tabs">
        <div class="panel-tab active" onclick="switchInspTab('details')">Details & Content</div>
        <div class="panel-tab" onclick="switchInspTab('participants')">Participants</div>
        <div class="panel-tab" onclick="switchInspTab('links')">Links</div>
    </div>

    <div class="slide-body">
        <div id="insp-tab-details" class="panel-tab-content active">
            <input type="hidden" id="inspBeatId">
            <input type="hidden" id="inspThreadId">

            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label class="field-label">Type</label>
                    <select id="inspBeatType" class="input-control">
                        <option value="event">Event</option>
                        <option value="decision">Decision / Reveal</option>
                        <option value="milestone">Milestone</option>
                        <option value="death">Death / Consequence</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="field-label">Status</label>
                    <select id="inspBeatStatus" class="input-control">
                        <option value="draft">Draft</option>
                        <option value="planned">Planned</option>
                        <option value="locked">Locked</option>
                    </select>
                </div>
                <div style="flex:2;">
                    <label class="field-label">Date & Time</label>
                    <input type="datetime-local" id="inspStoryTimeDt" class="input-control">
                </div>
            </div>
            
            
            
            
                       <!-- DROP-IN: Phase & Thread Context -->
            <div style="background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:8px 10px; margin-bottom:12px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; font-size:0.8rem;">
                <div><span style="color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; font-weight:700; display:block;">Phase (Episode)</span><strong id="inspPhaseEp">—</strong></div>
                <div><span style="color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; font-weight:700; display:block;">Season</span><span id="inspPhaseSeason">—</span></div>
                <div><span style="color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; font-weight:700; display:block;">Series</span><span id="inspPhaseSeries">—</span></div>
                <div style="margin-left:auto;"><span style="color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; font-weight:700; display:block;">Thread / Lane</span><span id="inspPhaseThread">—</span></div>
            </div>
            

            <label class="field-label">Summary</label>
            <textarea id="inspBeatSummary" class="input-control" style="min-height:50px;"></textarea>

            <label class="field-label">Full Content (Markdown)</label>
            <div id="inspBeatEditor" style="flex:1; min-height:250px; background:var(--bg); border-radius:6px; overflow:hidden;"></div>
        </div>

        <div id="insp-tab-participants" class="panel-tab-content">
            <div style="position:relative; margin-bottom:16px;">
                <label class="field-label">Add Participant (Search KG Nodes)</label>
                <input type="text" id="searchKgInput" class="input-control" placeholder="Type character, location, etc..." autocomplete="off">
                <div id="searchKgResults" class="search-results"></div>
            </div>
            <h4 style="font-size:0.85rem; margin-top:0; border-bottom:1px solid var(--border); padding-bottom:6px;">Current Participants</h4>
            <div id="inspParticipantsList">
                <div style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Loading...</div>
            </div>
        </div>

        <div id="insp-tab-links" class="panel-tab-content">
            <div style="background:var(--bg); padding:12px; border:1px solid var(--border); border-radius:6px; margin-bottom:16px;">
                <label class="field-label">Link to another Beat</label>
                <select id="linkTargetBeat" class="input-control">
                    <option value="">-- Select Beat --</option>
                </select>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label class="field-label">Relationship</label>
                        <select id="linkRelationship" class="input-control">
                            <option value="leads_to">Leads to (Solid Arrow)</option>
                            <option value="concurrent_with">Concurrent with (Dotted Arrow)</option>
                            <option value="causes">Causes</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label class="field-label">Label (Optional)</label>
                        <input type="text" id="linkLabel" class="input-control" placeholder="Edge text">
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" style="width:100%; justify-content:center;" onclick="addBeatLink()">Add Link</button>
            </div>

            <h4 style="font-size:0.85rem; margin-top:0; border-bottom:1px solid var(--border); padding-bottom:6px;">Current Links</h4>
            <div id="inspLinksList">
                <div style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Loading...</div>
            </div>
        </div>
    </div>

    <div class="slide-footer">
        <button class="btn btn-danger" onclick="archiveCurrentBeat()"><i class="bi bi-trash"></i> Archive</button>
        <button class="btn btn-primary" onclick="saveBeatDetails()"><i class="bi bi-floppy"></i> Save Beat</button>
    </div>
</div>

<div id="toast"></div>

<script>
const API_URL = 'mermaids_api.php';
const LS_SERIES_KEY = 'merm_series_filter';
const LS_SEASON_KEY = 'merm_season_filter';
const LS_EPISODE_KEY = 'merm_episode_filter';

let allThreads = [];
let threads = [];
let allProjectBeats = [];
let allParticipants = [];
let allBeats = [];
let customTimes = []; 
let editorInstance = null;
let currentOpenBeat = null;

let activeSeries = '';
let activeSeason = '';
let activeEpisode = '';

let exportSelection = { series: new Set(), season: new Set(), episode: new Set() };

$(document).ready(function() {
    try { 
        activeSeries = localStorage.getItem(LS_SERIES_KEY) || ''; 
        activeSeason = localStorage.getItem(LS_SEASON_KEY) || ''; 
        activeEpisode = localStorage.getItem(LS_EPISODE_KEY) || ''; 
    } catch(e) {}

    applyTheme(); 
    initThemeToggle();
    initColorPickerSync();
    updateFilterBadge();
    loadEditorData();
    initSearchDebounce();
});

function isDark() {
    const t = document.documentElement.getAttribute('data-theme');
    if (t === 'dark') return true;
    if (t === 'light') return false;
    return window.matchMedia('(prefers-color-scheme:dark)').matches;
}

function applyTheme() {
    const dark = isDark();
    const link = document.getElementById('tui-dark-theme');
    if (link) {
        if (dark) link.removeAttribute('disabled');
        else link.setAttribute('disabled', 'true');
    }
}

function initThemeToggle() {
    const btn = document.getElementById('btnTheme');
    btn.textContent = isDark() ? '☀️' : '🌙';
    btn.addEventListener('click', () => {
        const next = isDark() ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('spw_theme', next);
        btn.textContent = isDark() ? '☀️' : '🌙';
        
        applyTheme();

        if (editorInstance) {
            const md = editorInstance.getMarkdown();
            editorInstance.destroy();
            initTuiEditor(md);
        }
    });
}

function initColorPickerSync() {
    $('#threadColorPicker').on('input', function() { $('#threadColor').val($(this).val()); });
    $('#threadColor').on('input', function() { $('#threadColorPicker').val($(this).val()); });
}

function switchMainView(view) {
    $('.btn-group .btn').removeClass('active');
    $('#btn-view-' + view).addClass('active');
    $('.view-container').removeClass('active');
    $('#' + view + '-view').addClass('active');
    
    if (view === 'timeline') renderTimelineView();
    else if (view === 'editor') renderGrid();
    else if (view === 'tracker') renderTrackerView();
}

let toastTimer;
function showToast(msg, type='success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display='none', 2800);
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDt(dtStr) {
    if(!dtStr) return 'Unassigned';
    const d = new Date(dtStr);
    return d.toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
}
function isoToMysql(iso) {
    if(!iso) return null;
    return iso.replace('T', ' ') + ':00';
}
function mysqlToIso(mysql) {
    if(!mysql) return '';
    return mysql.substring(0, 16).replace(' ', 'T');
}

// ======================== STATS ========================
async function refreshFilterStats() {
    $('#filterStats').html('<i>Calculating...</i>');
    const series = $('#filterSeries').val();
    const season = $('#filterSeason').val();
    const ep = $('#filterEpisode').val();
    
    const filters = {
        series: series ? [series] : [],
        season: season ? [season] : [],
        episode: ep ? [ep] : []
    };
    
    try {
        const res = await $.post(API_URL, { action: 'get_stats', filters: filters });
        if(res.ok) {
            $('#filterStats').html(`<i>Matches: ${res.stats.threads} thread(s), ${res.stats.beats} beat(s)</i>`);
        }
    } catch(e) {
        $('#filterStats').html('<i>Stats error</i>');
    }
}

async function refreshExportStats() {
    $('#exportStats').html('<i>Calculating...</i>');
    const filters = {
        series: Array.from(exportSelection.series),
        season: Array.from(exportSelection.season),
        episode: Array.from(exportSelection.episode)
    };
    
    try {
        const res = await $.post(API_URL, { action: 'get_stats', filters: filters });
        if(res.ok) {
            $('#exportStats').html(`<i>Matches: ${res.stats.threads} thread(s), ${res.stats.beats} beat(s)</i>`);
        }
    } catch(e) {
        $('#exportStats').html('<i>Stats error</i>');
    }
}

// ======================== CASCADING FILTER ========================
function openFilterModal() {
    const seriesSet = new Set();
    allThreads.forEach(t => { if(t.series_key) seriesSet.add(t.series_key); });
    populateSelect('filterSeries', seriesSet, activeSeries, '— All series —');
    
    const seasonSet = new Set();
    allThreads.filter(t => !activeSeries || t.series_key === activeSeries).forEach(t => { if(t.season_key) seasonSet.add(t.season_key); });
    populateSelect('filterSeason', seasonSet, activeSeason, '— All seasons —');
    
    const epSet = new Set();
    allThreads.filter(t => (!activeSeries || t.series_key === activeSeries) && (!activeSeason || t.season_key === activeSeason)).forEach(t => { if(t.episode_key) epSet.add(t.episode_key); });
    populateSelect('filterEpisode', epSet, activeEpisode, '— All episodes —');

    $('#filterSeason').prop('disabled', !activeSeries && seasonSet.size === 0);
    $('#filterEpisode').prop('disabled', !activeSeason && epSet.size === 0);

    updateSeriesAnchorDate(activeSeries);
    refreshFilterStats();
    $('#modalFilter').addClass('active');
}

function handleSeriesFilterChange() {
    updateFilterDropdowns('series');
    updateSeriesAnchorDate($('#filterSeries').val());
    refreshFilterStats();
}

function updateFilterDropdowns(changed, clearLower = true) {
    const currentSeries = $('#filterSeries').val() || '';
    let currentSeason = $('#filterSeason').val() || '';
    let currentEpisode = $('#filterEpisode').val() || '';

    if (clearLower && changed === 'series') {
        currentSeason = ''; currentEpisode = '';
    } else if (clearLower && changed === 'season') {
        currentEpisode = '';
    }

    const seasonSet = new Set();
    allThreads.filter(t => !currentSeries || t.series_key === currentSeries).forEach(t => { if(t.season_key) seasonSet.add(t.season_key); });
    populateSelect('filterSeason', seasonSet, currentSeason, '— All seasons —');
    $('#filterSeason').prop('disabled', !currentSeries && seasonSet.size === 0);

    currentSeason = $('#filterSeason').val() || '';

    const epSet = new Set();
    allThreads.filter(t => (!currentSeries || t.series_key === currentSeries) && (!currentSeason || t.season_key === currentSeason)).forEach(t => { if(t.episode_key) epSet.add(t.episode_key); });
    populateSelect('filterEpisode', epSet, currentEpisode, '— All episodes —');
    $('#filterEpisode').prop('disabled', !currentSeason && epSet.size === 0);
    
    refreshFilterStats();
}

function populateSelect(id, set, activeVal, defaultText) {
    const sel = document.getElementById(id);
    sel.innerHTML = `<option value="">${defaultText}</option>`;
    Array.from(set).sort((a,b) => a.localeCompare(b, undefined, {sensitivity:'base'})).forEach(val => {
        const opt = document.createElement('option');
        opt.value = val; opt.textContent = val;
        sel.appendChild(opt);
    });
    sel.value = activeVal;
}

async function updateSeriesAnchorDate(seriesKey) {
    if (!seriesKey) {
        $('#seriesStartDate').val('');
        $('#seriesStartBlock').hide();
        return;
    }
    $('#seriesStartBlock').show();
    try {
        const res = await $.get(API_URL, { action: 'get_series_start', series_key: seriesKey });
        if (res.ok && res.min_dt) {
            $('#seriesStartDate').val(mysqlToIso(res.min_dt));
        } else {
            $('#seriesStartDate').val('');
        }
    } catch(e) {}
}

async function shiftSeriesStart() {
    const seriesKey = $('#filterSeries').val();
    const newDt = $('#seriesStartDate').val();
    if (!seriesKey || !newDt) return;
    
    if (!confirm(`Are you sure you want to shift all beats in "${seriesKey}" to start at ${newDt}?`)) return;
    
    try {
        const res = await $.post(API_URL, { 
            action: 'shift_series_start', 
            series_key: seriesKey, 
            new_start_dt: isoToMysql(newDt) 
        });
        if (!res.ok) throw new Error(res.error);
        showToast('Series timeline shifted successfully');
        loadEditorData();
    } catch(err) {
        showToast('Error shifting series: ' + err.message, 'error');
    }
}

function clearFilters() {
    activeSeries = ''; activeSeason = ''; activeEpisode = '';
    try { 
        localStorage.removeItem(LS_SERIES_KEY); 
        localStorage.removeItem(LS_SEASON_KEY); 
        localStorage.removeItem(LS_EPISODE_KEY); 
    } catch(e) {}
    closeModal('modalFilter');
    updateFilterBadge();
    loadEditorData();
}

function applyFilter() {
    activeSeries = $('#filterSeries').val() || '';
    activeSeason = $('#filterSeason').val() || '';
    activeEpisode = $('#filterEpisode').val() || '';
    try { 
        localStorage.setItem(LS_SERIES_KEY, activeSeries); 
        localStorage.setItem(LS_SEASON_KEY, activeSeason); 
        localStorage.setItem(LS_EPISODE_KEY, activeEpisode); 
    } catch(e) {}
    closeModal('modalFilter');
    updateFilterBadge();
    loadEditorData();
}

function updateFilterBadge() {
    const btn = document.getElementById('btnFilter');
    if (activeSeries || activeSeason || activeEpisode) btn.classList.add('filtered');
    else btn.classList.remove('filtered');
}

// ======================== EXPORT MODAL ========================
function openExportModal() {
    exportSelection = { series: new Set(), season: new Set(), episode: new Set() };
    
    const seriesSet = new Set();
    allThreads.forEach(t => { if(t.series_key) seriesSet.add(t.series_key); });
    populateSelect('exportSeries', seriesSet, '', '— Select Series —');
    
    updateExportDropdowns('series', false);
    renderExportChips();
    refreshExportStats();
    
    $('#modalExport').addClass('active');
}

function updateExportDropdowns(changed, clearLower = true) {
    const currentSeries = $('#exportSeries').val() || '';
    let currentSeason = $('#exportSeason').val() || '';
    let currentEpisode = $('#exportEpisode').val() || '';

    if (clearLower && changed === 'series') { currentSeason = ''; currentEpisode = ''; } 
    else if (clearLower && changed === 'season') { currentEpisode = ''; }

    const seasonSet = new Set();
    allThreads.filter(t => !currentSeries || t.series_key === currentSeries).forEach(t => { if(t.season_key) seasonSet.add(t.season_key); });
    populateSelect('exportSeason', seasonSet, currentSeason, '— Select Season —');
    $('#exportSeason').prop('disabled', !currentSeries && seasonSet.size === 0);

    currentSeason = $('#exportSeason').val() || '';

    const epSet = new Set();
    allThreads.filter(t => (!currentSeries || t.series_key === currentSeries) && (!currentSeason || t.season_key === currentSeason)).forEach(t => { if(t.episode_key) epSet.add(t.episode_key); });
    populateSelect('exportEpisode', epSet, currentEpisode, '— Select Episode —');
    $('#exportEpisode').prop('disabled', !currentSeason && epSet.size === 0);
}

function addExportChip(type) {
    let val = '';
    if (type === 'series') val = $('#exportSeries').val();
    if (type === 'season') val = $('#exportSeason').val();
    if (type === 'episode') val = $('#exportEpisode').val();
    
    if(!val) return;
    exportSelection[type].add(val);
    renderExportChips();
    refreshExportStats();
}

function removeExportChip(type, val) {
    exportSelection[type].delete(val);
    renderExportChips();
    refreshExportStats();
}

function renderExportChips() {
    const container = document.getElementById('exportChipsContainer');
    let html = '';
    ['series', 'season', 'episode'].forEach(type => {
        exportSelection[type].forEach(val => {
            const colorCls = type === 'series' ? 'chip-series' : (type === 'season' ? 'chip-season' : 'chip-episode');
            const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
            html += `<div class="chip ${colorCls}">
                ${typeLabel}: ${escHtml(val)}
                <i class="bi bi-x-circle-fill chip-remove" onclick="removeExportChip('${type}', '${escHtml(val).replace(/'/g, "\\'")}')"></i>
            </div>`;
        });
    });
    
    if(html === '') {
        html = '<span style="color:var(--text-muted); font-size:0.8rem; font-style:italic; padding-top:4px;">No filters added. Will export ALL timelines.</span>';
    }
    container.innerHTML = html;
}

async function executeExport(format) {
    const payload = {
        action: 'export_data',
        format: format,
        filters: {
            series: Array.from(exportSelection.series),
            season: Array.from(exportSelection.season),
            episode: Array.from(exportSelection.episode)
        }
    };
    
    try {
        const res = await $.post(API_URL, payload);
        if(!res.ok) throw new Error(res.error);
        
        let blob, extension;
        if (format === 'json') {
            blob = new Blob([JSON.stringify(res.export_data, null, 2)], { type: 'application/json' });
            extension = 'json';
        } else if (format === 'sql') {
            blob = new Blob([res.export_text], { type: 'application/sql' });
            extension = 'sql';
        } else if (format === 'md') {
            blob = new Blob([res.export_text], { type: 'text/markdown' });
            extension = 'md';
        }

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; 
        a.download = `timeline_export_${new Date().toISOString().slice(0,10)}.${extension}`;
        a.click();
        URL.revokeObjectURL(url);
        
        showToast(`Exported as ${format.toUpperCase()}`);
        closeModal('modalExport');
    } catch(e) {
        showToast('Export failed: ' + e.message, 'error');
    }
}

// ======================== DATA LOADING ========================
async function loadEditorData() {
    try {
        const res = await $.get(API_URL, { action: 'fetch_bundle' });
        if (!res.ok) throw new Error(res.error);

        allThreads = res.threads || [];
        allProjectBeats = res.beats || [];
        allParticipants = res.participants || [];

        threads = allThreads.filter(t => {
            if (activeSeries && t.series_key !== activeSeries) return false;
            if (activeSeason && t.season_key !== activeSeason) return false;
            if (activeEpisode && t.episode_key !== activeEpisode) return false;
            return true;
        });

        const activeThreadIds = new Set(threads.map(t => parseInt(t.id)));
        allBeats = allProjectBeats.filter(b => activeThreadIds.has(parseInt(b.thread_id)));

        if (document.getElementById('timeline-view').classList.contains('active')) {
            renderTimelineView();
        } else if (document.getElementById('tracker-view').classList.contains('active')) {
            renderTrackerView();
        } else {
            renderGrid();
        }
        
        populateLinkBeatDropdown();
    } catch(err) {
        showToast('Error loading data: ' + err.message, 'error');
    }
}

// ======================== GRID RENDER (Vertical Lanes) ========================
function renderGrid() {
    const container = document.getElementById('editor-view');

    if (allBeats.length > 100 || threads.length > 25) {
        container.innerHTML = `
            <div style="padding:40px 20px; text-align:center; max-width:400px; margin:auto;">
                <i class="bi bi-funnel" style="font-size:2.5rem; color:var(--accent);"></i>
                <h3 style="margin:12px 0 6px 0; font-size:1.1rem;">Large Scope (${allBeats.length} beats, ${threads.length} threads)</h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">
                    Rendering the full unfiltered matrix on mobile creates thousands of cells. Please apply a scope filter or switch to the Arc Tracker tab.
                </p>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="btn btn-primary" onclick="openFilterModal()" style="width:100%; justify-content:center; min-height:44px;">
                        <i class="bi bi-funnel"></i> Open Filter
                    </button>
                    <button class="btn btn-ghost" onclick="switchMainView('tracker')" style="width:100%; justify-content:center; min-height:44px;">
                        <i class="bi bi-person-lines-fill"></i> View in Arc Tracker
                    </button>
                </div>
            </div>`;
        return;
    }

    if (threads.length === 0) {
        container.innerHTML = `<div style="padding:60px; text-align:center; color:var(--text-muted);">
            <i class="bi bi-clock-history" style="font-size:3rem; opacity:0.5;"></i><br><br>
            No threads found for this scope. Click <strong>Add Thread</strong>.
        </div>`;
        return;
    }

    let times = [];
    allBeats.forEach(b => {
        if (b.story_time_dt) {
            let t = mysqlToIso(b.story_time_dt);
            if(!times.includes(t)) times.push(t);
        }
    });
    customTimes.forEach(t => { if(!times.includes(t)) times.push(t); });
    times.sort();
    times.unshift(''); 

    let html = `<table class="grid-table"><thead><tr>`;
    html += `<th class="col-time">Date & Time</th>`;
    threads.forEach(t => {
        const colorStyle = t.color ? `background-color:${escHtml(t.color)};` : 'background-color:var(--accent);';
        html += `<th>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div><span class="thread-dot" style="${colorStyle}"></span>${escHtml(t.name)}</div>
                <div style="display:flex; gap:4px;">
                    <button class="btn btn-ghost btn-sm" style="padding:2px 6px;" onclick="editThread(${t.id})" title="Edit Thread"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-ghost btn-sm" style="padding:2px 6px;" onclick="quickAddBeat(${t.id})" title="Add Beat"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>
        </th>`;
    });
    html += `</tr></thead><tbody>`;

    times.forEach(t => {
        html += `<tr>`;
        html += `<th class="col-time">
                    ${formatDt(t)}
                    ${t ? `<br><button class="btn btn-ghost btn-sm" style="padding:2px 4px; font-size:0.6rem; margin-top:4px;" onclick="shiftTimeline('${t}')" title="Shift this and all future rows by +1 day"><i class="bi bi-arrow-down"></i> +1 Day</button>` : ''}
                 </th>`;

        threads.forEach(thread => {
            const cellBeats = allBeats.filter(b => {
                if (parseInt(b.thread_id) !== parseInt(thread.id)) return false;
                const bTime = b.story_time_dt ? mysqlToIso(b.story_time_dt) : '';
                return bTime === t;
            });
            const cellClass = 'grid-cell' + (cellBeats.length ? '' : ' is-empty');

            html += `<td class="${cellClass}" data-time="${t}" data-thread="${thread.id}">`;
            html += `<div class="cell-inner thread-beat-list">`;
            cellBeats.forEach(b => { html += buildBeatCard(b); });
            html += `</div></td>`;
        });
        html += `</tr>`;
    });

    html += `</tbody></table>`;
    
    html += `<div style="padding:16px;">
        <button class="btn btn-ghost btn-sm" onclick="addCustomTimeRow()"><i class="bi bi-plus-circle"></i> Add Time Row</button>
    </div>`;
    
    container.innerHTML = html;
    
    if (window._sortables) window._sortables.forEach(s => s.destroy());
    window._sortables = [];
    
    document.querySelectorAll('.grid-cell .cell-inner').forEach(cell => {
        const s = new Sortable(cell, {
            group: 'grid', animation: 150, ghostClass: 'sortable-ghost', delay: 100, delayOnTouchOnly: true,
            onStart: function(evt) { evt.from.closest('.grid-cell').classList.add('drag-over'); },
            onEnd: async function(evt) { handleSortableEnd(evt, '.grid-cell'); }
        });
        window._sortables.push(s);
    });
}

function buildBeatCard(beat) {
    const icon = getBeatTypeIcon(beat.beat_type);
    return `
    <div class="beat-card" data-id="${beat.id}" onclick="openBeatInspector(${beat.id})">
        <div class="beat-header">
            <div class="beat-title">
                ${escHtml(beat.title)} 
                <span style="font-size:0.75rem; color:var(--text-muted); font-weight:normal; opacity:0.8;">#${beat.id}</span>
            </div>
        </div>
        <div class="beat-meta">
            <span class="beat-type-pill"><i class="bi ${icon}"></i> ${escHtml(beat.beat_type)}</span>
        </div>
    </div>`;
}

function getBeatTypeIcon(type) {
    switch(type) {
        case 'decision': case 'reveal': return 'bi-signpost-split';
        case 'milestone': case 'sync_point': return 'bi-flag-fill';
        case 'death': case 'consequence': return 'bi-skull';
        default: return 'bi-card-text';
    }
}

// ======================== TIMELINE MATRIX VIEW (Horizontal Lanes) ========================
function renderTimelineView() {
    const container = document.getElementById('timeline-view');
    
    if (allBeats.length > 100 || threads.length > 25) {
        container.innerHTML = `
            <div style="padding:40px 20px; text-align:center; max-width:400px; margin:auto;">
                <i class="bi bi-funnel" style="font-size:2.5rem; color:var(--accent);"></i>
                <h3 style="margin:12px 0 6px 0; font-size:1.1rem;">Large Scope (${allBeats.length} beats, ${threads.length} threads)</h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">
                    Rendering the full unfiltered timeline on mobile creates thousands of cells. Please apply a scope filter or switch to the Arc Tracker tab.
                </p>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="btn btn-primary" onclick="openFilterModal()" style="width:100%; justify-content:center; min-height:44px;">
                        <i class="bi bi-funnel"></i> Open Filter
                    </button>
                    <button class="btn btn-ghost" onclick="switchMainView('tracker')" style="width:100%; justify-content:center; min-height:44px;">
                        <i class="bi bi-person-lines-fill"></i> View in Arc Tracker
                    </button>
                </div>
            </div>`;
        return;
    }

    if (threads.length === 0 || allBeats.length === 0) {
        container.innerHTML = `<div style="padding:40px; text-align:center; color:var(--text-muted);">
            No threads or beats to display in the timeline.
        </div>`;
        return;
    }

    let times = [];
    allBeats.forEach(b => {
        if (b.story_time_dt) {
            let t = mysqlToIso(b.story_time_dt);
            if(!times.includes(t)) times.push(t);
        }
    });
    customTimes.forEach(t => { if(!times.includes(t)) times.push(t); });
    times.sort();
    times.push(''); 

    let html = `<table class="timeline-table"><thead><tr>`;
    html += `<th class="col-thread">Threads</th>`;
    
    times.forEach(t => {
        html += `<th>${t === '' ? '<i>Unassigned Time</i>' : formatDt(t)}</th>`;
    });
    html += `</tr></thead><tbody>`;

    threads.forEach(thread => {
        const colorStyle = thread.color ? `background-color: ${escHtml(thread.color)};` : 'background-color: var(--accent);';
        html += `<tr>`;
        html += `<td class="col-thread">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="width:12px; height:12px; border-radius:50%; ${colorStyle}"></span>
                        ${escHtml(thread.name)}
                    </div>
                 </td>`;
        
        times.forEach(t => {
            const cellBeats = allBeats.filter(b => {
                const bTime = b.story_time_dt ? mysqlToIso(b.story_time_dt) : '';
                return parseInt(b.thread_id) === parseInt(thread.id) && bTime === t;
            });
            
            const cellClass = 'timeline-cell' + (cellBeats.length ? '' : ' is-empty');
            html += `<td class="${cellClass}" data-time="${t}" data-thread="${thread.id}">`;
            html += `<div class="timeline-cell-content">`;
            cellBeats.forEach(b => { html += buildBeatCard(b); });
            html += `</div></td>`;
        });
        html += `</tr>`;
    });
    
    html += `</tbody></table>`;
    container.innerHTML = html;

    if (window._sortables) window._sortables.forEach(s => s.destroy());
    window._sortables = [];
    
    document.querySelectorAll('.timeline-cell .timeline-cell-content').forEach(cell => {
        const s = new Sortable(cell, {
            group: 'grid', animation: 150, ghostClass: 'sortable-ghost', delay: 100, delayOnTouchOnly: true,
            onStart: function(evt) { evt.from.closest('.timeline-cell').classList.add('drag-over'); },
            onEnd: async function(evt) { handleSortableEnd(evt, '.timeline-cell'); }
        });
        window._sortables.push(s);
    });
}

async function handleSortableEnd(evt, cellSelector) {
    document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));

    const toCell   = evt.to.closest(cellSelector);
    const fromCell = evt.from.closest(cellSelector);
    const newTime   = toCell.dataset.time;
    const newThread = parseInt(toCell.dataset.thread);

    const moves = [];
    Array.from(toCell.querySelectorAll('.beat-card')).forEach((card, i) => {
        moves.push({
            beat_id: parseInt(card.dataset.id),
            story_time_dt: newTime === '' ? null : newTime,
            thread_id: newThread,
            seq_order: i + 1
        });
    });

    if (fromCell !== toCell) {
        const fromTime   = fromCell.dataset.time;
        const fromThread = parseInt(fromCell.dataset.thread);
        Array.from(fromCell.querySelectorAll('.beat-card')).forEach((card, i) => {
            moves.push({
                beat_id: parseInt(card.dataset.id),
                story_time_dt: fromTime === '' ? null : fromTime,
                thread_id: fromThread,
                seq_order: i + 1
            });
        });
    }

    try {
        const res = await $.post(API_URL, { action: 'save_grid', moves: JSON.stringify(moves) });
        if (!res.ok) throw new Error(res.error);
        
        moves.forEach(m => {
            const b = allBeats.find(x => parseInt(x.id) === m.beat_id);
            if (b) {
                b.story_time_dt = m.story_time_dt ? isoToMysql(m.story_time_dt) : null;
                b.thread_id     = m.thread_id;
                b.seq_order     = m.seq_order;
            }
        });
        showToast('Sequence saved');
    } catch(err) {
        showToast('Move failed: ' + err.message, 'error');
        loadEditorData();
    }
}

// ======================== TIMELINE ACTIONS ========================
function addCustomTimeRow() {
    const input = prompt("Enter new Date/Time (YYYY-MM-DD HH:MM):", "2024-01-01 12:00");
    if (!input) return;
    const d = new Date(input);
    if (isNaN(d.getTime())) { showToast('Invalid date format', 'error'); return; }
    
    const tzOffset = (new Date()).getTimezoneOffset() * 60000;
    const localISOTime = (new Date(d.getTime() - tzOffset)).toISOString().slice(0, 16);
    
    if(!customTimes.includes(localISOTime)) {
        customTimes.push(localISOTime);
        renderGrid();
    }
}

async function shiftTimeline(isoDt) {
    let msg = `Shift this row and all subsequent rows by +1 Day?`;
    if (activeSeries) {
        msg = `Shift this row and all subsequent rows for series "${activeSeries}" by +1 Day?`;
    }
    
    if (!confirm(msg)) return;
    try {
        const res = await $.post(API_URL, { action: 'shift_timeline', from_dt: isoDt, series_key: activeSeries });
        if (!res.ok) throw new Error(res.error);
        showToast('Timeline shifted');
        customTimes = [];
        loadEditorData();
    } catch(err) {
        showToast('Error: ' + err.message, 'error');
    }
}

// ======================== THREAD MANAGEMENT ========================
function refreshThreadDatalists() {
    const sSet = new Set(), seSet = new Set(), epSet = new Set();
    allThreads.forEach(t => { 
        if(t.series_key) sSet.add(t.series_key); 
        if(t.season_key) seSet.add(t.season_key);
        if(t.episode_key) epSet.add(t.episode_key);
    });
    document.getElementById('seriesList').innerHTML = Array.from(sSet).map(x => `<option value="${escHtml(x)}"></option>`).join('');
    document.getElementById('seasonList').innerHTML = Array.from(seSet).map(x => `<option value="${escHtml(x)}"></option>`).join('');
    document.getElementById('episodeList').innerHTML = Array.from(epSet).map(x => `<option value="${escHtml(x)}"></option>`).join('');
}

function openThreadModal() {
    $('#threadId').val('');
    $('#threadName').val('');
    $('#threadSeries').val(activeSeries || '');
    $('#threadSeason').val(activeSeason || '');
    $('#threadEpisode').val(activeEpisode || '');
    $('#threadDesc').val('');
    $('#threadColor').val('#3b82f6');
    $('#threadColorPicker').val('#3b82f6');
    $('#modalThreadTitle').text('Add Thread');
    refreshThreadDatalists();
    $('#modalThread').addClass('active');
}

function editThread(id) {
    const thread = threads.find(t => t.id == id);
    if (!thread) return;
    $('#threadId').val(thread.id);
    $('#threadName').val(thread.name);
    $('#threadSeries').val(thread.series_key || '');
    $('#threadSeason').val(thread.season_key || '');
    $('#threadEpisode').val(thread.episode_key || '');
    $('#threadDesc').val(thread.description || '');
    $('#threadColor').val(thread.color || '#3b82f6');
    $('#threadColorPicker').val(thread.color || '#3b82f6');
    $('#modalThreadTitle').text('Edit Thread');
    refreshThreadDatalists();
    $('#modalThread').addClass('active');
}

function closeModal(id) { $('#' + id).removeClass('active'); }

async function saveThread() {
    const id = $('#threadId').val();
    const name = $('#threadName').val().trim();
    if (!name) return showToast('Name required', 'error');

    const payload = {
        name: name,
        series_key: $('#threadSeries').val().trim(),
        season_key: $('#threadSeason').val().trim(),
        episode_key: $('#threadEpisode').val().trim(),
        description: $('#threadDesc').val(),
        color: $('#threadColor').val(),
        sort_order: allThreads.length + 1
    };

    try {
        let res;
        if (id) {
            payload.id = id;
            payload.action = 'update_thread';
            res = await $.post(API_URL, payload);
        } else {
            payload.action = 'create_thread';
            res = await $.post(API_URL, payload);
        }
        if (!res.ok) throw new Error(res.error);
        showToast(id ? 'Thread updated' : 'Thread created');
        closeModal('modalThread');
        loadEditorData();
    } catch(err) {
        showToast(err.message, 'error');
    }
}

// ======================== BEAT INSPECTOR ========================
async function quickAddBeat(threadId) {
    try {
        const title = prompt("Enter beat title:");
        if(!title) return;
        const res = await $.post(API_URL, { action: 'create_beat', thread_id: threadId, title: title, beat_type: 'event' });
        if(!res.ok) throw new Error(res.error);
        showToast('Beat added');
        loadEditorData();
    } catch(err) {
        showToast(err.message, 'error');
    }
}

async function openBeatInspector(id) {
    currentOpenBeat = id;
    switchInspTab('details');
    $('#inspParticipantsList').html('<div style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Loading...</div>');
    $('#inspLinksList').html('<div style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Loading...</div>');

    try {
        const res = await $.get(API_URL, { action: 'get_beat', id: id });
        if (!res.ok) throw new Error(res.error);

        const b = res.beat;
        // Lookup thread across all project threads to always get phase even outside active filter
        const thread = (allThreads || []).find(t => parseInt(t.id) === parseInt(b.thread_id)) || {};

        $('#inspPhaseEp').text(thread.episode_key || 'UNASSIGNED');
        $('#inspPhaseSeason').text(thread.season_key || '—');
        $('#inspPhaseSeries').text(thread.series_key || '—');
        $('#inspPhaseThread').text(thread.name || 'Unknown');
        $('#inspBeatId').val(b.id);
        $('#inspThreadId').val(b.thread_id);
        $('#inspBeatTitle').val(b.title);
        $('#inspBeatMeta').text(`Lane: ${thread ? thread.name : 'Unknown'} • Order: ${b.seq_order}`);
        $('#inspBeatType').val(b.beat_type || 'event');
        $('#inspBeatStatus').val(b.status || 'draft');
        $('#inspStoryTimeDt').val(mysqlToIso(b.story_time_dt));
        $('#inspBeatSummary').val(b.summary || '');

        initTuiEditor(b.content || '');
        renderParticipants(b.participants || []);
        renderLinks(b.links || []);

        $('#slideOverlay').addClass('active');
        $('#beatInspector').addClass('open');
    } catch(err) {
        showToast('Error loading beat: ' + err.message, 'error');
    }
}

function closeBeatInspector() {
    $('#slideOverlay').removeClass('active');
    $('#beatInspector').removeClass('open');
    if (editorInstance) { editorInstance.destroy(); editorInstance = null; }
    currentOpenBeat = null;
}

function switchInspTab(tab) {
    $('.panel-tab').removeClass('active');
    $('.panel-tab-content').removeClass('active');
    $(`.panel-tab[onclick="switchInspTab('${tab}')"]`).addClass('active');
    $(`#insp-tab-${tab}`).addClass('active');
}

function initTuiEditor(initialMd) {
    const el = document.getElementById('inspBeatEditor');
    if (editorInstance) { editorInstance.destroy(); }
    editorInstance = new toastui.Editor({
        el: el,
        height: '100%',
        initialEditType: 'markdown',
        previewStyle: 'tab',
        hideModeSwitch: true,
        theme: isDark() ? 'dark' : 'light',
        initialValue: initialMd,
        usageStatistics: false,
        autofocus: false
    });
}

async function saveBeatDetails() {
    if (!currentOpenBeat) return;
    try {
        const dtVal = $('#inspStoryTimeDt').val();
        const payload = {
            action: 'update_beat',
            id: $('#inspBeatId').val(),
            thread_id: $('#inspThreadId').val(),
            title: $('#inspBeatTitle').val().trim(),
            beat_type: $('#inspBeatType').val(),
            status: $('#inspBeatStatus').val(),
            story_time_dt: dtVal ? dtVal : null,
            summary: $('#inspBeatSummary').val(),
            content: editorInstance ? editorInstance.getMarkdown() : ''
        };
        const res = await $.post(API_URL, payload);
        if (!res.ok) throw new Error(res.error);
        showToast('Beat saved ✓');
        loadEditorData();
    } catch(err) {
        showToast('Error saving: ' + err.message, 'error');
    }
}

async function archiveCurrentBeat() {
    if (!currentOpenBeat) return;
    if (!confirm("Archive this beat?")) return;
    try {
        const res = await $.post(API_URL, { action: 'delete_beat', id: currentOpenBeat });
        if (!res.ok) throw new Error(res.error);
        showToast('Beat archived');
        closeBeatInspector();
        loadEditorData();
    } catch(err) {
        showToast('Error archiving: ' + err.message, 'error');
    }
}

// ======================== PARTICIPANTS ========================
let searchTimer;
function initSearchDebounce() {
    $('#searchKgInput').on('input', function() {
        clearTimeout(searchTimer);
        const q = $(this).val().trim();
        if (q.length < 2) { $('#searchKgResults').hide(); return; }
        searchTimer = setTimeout(() => doKgSearch(q), 300);
    });
    $(document).click(function(e) {
        if (!$(e.target).closest('#insp-tab-participants').length) $('#searchKgResults').hide();
    });
}

async function doKgSearch(q) {
    try {
        const res = await $.get(API_URL, { action: 'search_kg_nodes', q: q });
        if (!res.ok) return;
        const html = res.results.map(n => `
            <div class="search-result-item" onclick="addParticipant(${n.id}, '${escHtml(n.name)}')">
                <strong>${escHtml(n.name)}</strong> <span style="color:var(--text-muted); font-size:0.7rem;">(${n.node_type})</span>
            </div>
        `).join('');
        $('#searchKgResults').html(html || '<div style="padding:8px 10px; font-size:0.8rem; color:var(--text-muted);">No matches</div>').show();
    } catch(err) {}
}

async function addParticipant(kgNodeId, nodeName) {
    $('#searchKgResults').hide();
    $('#searchKgInput').val('');
    const role = prompt(`Add role for ${nodeName}? (optional)`, '');
    if (role === null) return;
    try {
        const res = await $.post(API_URL, { action: 'add_participant', beat_id: currentOpenBeat, kg_node_id: kgNodeId, role: role });
        if (!res.ok) throw new Error(res.error);
        showToast('Participant added');
        refreshBeatInspectorData();
    } catch(err) {
        showToast('Error adding: ' + err.message, 'error');
    }
}

async function removeParticipant(id) {
    try {
        const res = await $.post(API_URL, { action: 'remove_participant', id: id });
        if (!res.ok) throw new Error(res.error);
        showToast('Participant removed');
        refreshBeatInspectorData();
    } catch(err) {
        showToast('Error removing: ' + err.message, 'error');
    }
}

function renderParticipants(parts) {
    if (!parts || parts.length === 0) {
        $('#inspParticipantsList').html('<div style="font-size:0.85rem; color:var(--text-muted); font-style:italic;">No participants linked yet.</div>');
        return;
    }
    const html = parts.map(p => `
        <div class="list-item-row">
            <div>
                <strong>${escHtml(p.kg_node_name)}</strong>
                ${p.role ? `<div style="font-size:0.75rem; color:var(--text-muted);">Role: ${escHtml(p.role)}</div>` : ''}
            </div>
            <button class="btn btn-ghost btn-sm" onclick="removeParticipant(${p.id})"><i class="bi bi-trash" style="color:var(--red);"></i></button>
        </div>
    `).join('');
    $('#inspParticipantsList').html(html);
}

// ======================== LINKS ========================
function populateLinkBeatDropdown() {
    const select = $('#linkTargetBeat');
    select.empty().append('<option value="">-- Select Target Beat --</option>');
    threads.forEach(t => {
        const optgroup = $('<optgroup>').attr('label', t.name);
        allBeats.filter(b => b.thread_id == t.id && b.id != currentOpenBeat).forEach(b => {
            optgroup.append($('<option>').val(b.id).text(b.title));
        });
        select.append(optgroup);
    });
}

async function addBeatLink() {
    const targetBeatId = $('#linkTargetBeat').val();
    if (!targetBeatId) return showToast('Please select a target beat', 'error');
    try {
        const res = await $.post(API_URL, {
            action: 'add_link',
            from_beat_id: currentOpenBeat,
            to_beat_id: targetBeatId,
            relationship: $('#linkRelationship').val(),
            label: $('#linkLabel').val()
        });
        if (!res.ok) throw new Error(res.error);
        showToast('Link added');
        $('#linkTargetBeat').val('');
        $('#linkLabel').val('');
        refreshBeatInspectorData();
    } catch(err) {
        showToast('Error linking: ' + err.message, 'error');
    }
}

async function removeBeatLink(id) {
    try {
        const res = await $.post(API_URL, { action: 'remove_link', id: id });
        if (!res.ok) throw new Error(res.error);
        showToast('Link removed');
        refreshBeatInspectorData();
    } catch(err) {
        showToast('Error removing: ' + err.message, 'error');
    }
}

function renderLinks(links) {
    if (!links || links.length === 0) {
        $('#inspLinksList').html('<div style="font-size:0.85rem; color:var(--text-muted); font-style:italic;">No links connected yet.</div>');
        return;
    }
    const html = links.map(l => {
        const isOut = l.direction === 'outgoing';
        const badgeColor = isOut ? 'var(--accent)' : 'var(--orange)';
        const dirText = isOut ? 'Leads to' : 'Comes from';
        return `
        <div class="list-item-row">
            <div>
                <div style="font-size:0.7rem; font-weight:700; color:${badgeColor}; text-transform:uppercase;">${dirText}</div>
                <strong>${escHtml(l.other_beat_title)}</strong>
                <div style="font-size:0.75rem; color:var(--text-muted);">
                    Relation: ${escHtml(l.relationship)} ${l.label ? `(${escHtml(l.label)})` : ''}
                </div>
            </div>
            <button class="btn btn-ghost btn-sm" onclick="removeBeatLink(${l.id})"><i class="bi bi-trash" style="color:var(--red);"></i></button>
        </div>`;
    }).join('');
    $('#inspLinksList').html(html);
}

async function refreshBeatInspectorData() {
    if (!currentOpenBeat) return;
    try {
        const res = await $.get(API_URL, { action: 'get_beat', id: currentOpenBeat });
        if (res.ok) {
            renderParticipants(res.beat.participants || []);
            renderLinks(res.beat.links || []);
        }
    } catch(e) {}
}

// ======================== ARC TRACKER LOGIC ========================
let trackerMode = 'single'; // 'single' | 'and' | 'or' | 'diff'
let selectedTrackerCharIds = []; 
let diffIncludeCharId = null;
let diffExcludeCharId = null;
let trackerSearchQuery = '';

function onTrackerSearchInput(val) {
    trackerSearchQuery = (val || '').trim();
    if (trackerSearchQuery) {
        $('#trackerSearchClearBtn').show();
        $('#trackerModeSection').hide();
    } else {
        $('#trackerSearchClearBtn').hide();
        $('#trackerModeSection').show();
    }
    renderTrackerView();
}

function clearTrackerSearch() {
    $('#trackerSearchInput').val('');
    trackerSearchQuery = '';
    $('#trackerSearchClearBtn').hide();
    $('#trackerModeSection').show();
    renderTrackerView();
}

function onTrackerModeChange(mode) {
    trackerMode = mode;

    if (mode === 'single') {
        if (selectedTrackerCharIds.length > 1) {
            selectedTrackerCharIds = [selectedTrackerCharIds[0]];
        } else if (!selectedTrackerCharIds.length && diffIncludeCharId) {
            selectedTrackerCharIds = [diffIncludeCharId];
        }
    } else if (mode === 'diff') {
        diffIncludeCharId = selectedTrackerCharIds[0] || diffIncludeCharId || null;
        diffExcludeCharId = selectedTrackerCharIds[1] || diffExcludeCharId || null;
    } else {
        // and / or
        if (!selectedTrackerCharIds.length && diffIncludeCharId) {
            selectedTrackerCharIds = [diffIncludeCharId];
            if (diffExcludeCharId) selectedTrackerCharIds.push(diffExcludeCharId);
        }
    }

    renderTrackerView();
}

function getSeriesScopedData() {
    // Arc tracker respects the active series scope if one is set
    const seriesThreads = activeSeries 
        ? allThreads.filter(t => t.series_key === activeSeries)
        : allThreads;
    
    const seriesThreadIds = new Set(seriesThreads.map(t => parseInt(t.id)));

    const scopedBeats = allProjectBeats.filter(b => seriesThreadIds.has(parseInt(b.thread_id)));
    const scopedBeatIds = new Set(scopedBeats.map(b => parseInt(b.id)));

    const scopedParticipants = allParticipants.filter(p => scopedBeatIds.has(parseInt(p.beat_id)));

    return {
        threads: seriesThreads,
        beats: scopedBeats,
        participants: scopedParticipants
    };
}

function renderTrackerView() {
    const scoped = getSeriesScopedData();

    const charMap = new Map();
    scoped.participants.forEach(p => {
        const cid = parseInt(p.char_id);
        if (!charMap.has(cid)) {
            charMap.set(cid, { id: cid, name: p.char_name, total_beats: 0 });
        }
        charMap.get(cid).total_beats++;
    });

    const chars = Array.from(charMap.values()).sort((a, b) => b.total_beats - a.total_beats || a.name.localeCompare(b.name));

    // Default initialization if empty
    if (chars.length) {
        if (trackerMode === 'diff') {
            if (!diffIncludeCharId || !charMap.has(diffIncludeCharId)) diffIncludeCharId = chars[0].id;
            if (diffExcludeCharId && !charMap.has(diffExcludeCharId)) diffExcludeCharId = null;
        } else {
            selectedTrackerCharIds = selectedTrackerCharIds.filter(id => charMap.has(id));
            if (!selectedTrackerCharIds.length) selectedTrackerCharIds = [chars[0].id];
        }
    } else {
        diffIncludeCharId = null;
        diffExcludeCharId = null;
        selectedTrackerCharIds = [];
    }

    // Render character buttons/chips
    const chipsHtml = chars.slice(0, 30).map(c => buildCharButtonHtml(c)).join('');
    document.getElementById('charChips').innerHTML = chipsHtml;

    // Render bottom sheet item list
    const sheetHtml = chars.map(c => `
        <div class="beat-row ${getCharClass(c.id)}" style="min-height:56px;" onclick="toggleTrackerChar(${c.id}); closeCharBottomSheet();">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong>${escHtml(c.name)}</strong>
                ${getCharBadge(c.id)}
            </div>
            <span style="font-size:0.8rem; color:var(--text-muted);">${c.total_beats} beats total</span>
        </div>
    `).join('');
    document.getElementById('charSheetList').innerHTML = sheetHtml;

    // Update selection info label
    updateTrackerSelectionInfo(charMap);

    // Compute matching beats and render phases
    renderTrackerBeats(charMap, scoped);
}

function getCharClass(id) {
    if (trackerMode === 'diff') {
        if (id === diffIncludeCharId) return 'is-diff-include';
        if (id === diffExcludeCharId) return 'is-diff-exclude';
        return '';
    }
    return selectedTrackerCharIds.includes(id) ? 'is-active' : '';
}

function getCharBadge(id) {
    if (trackerMode === 'diff') {
        if (id === diffIncludeCharId) return '<span style="color:var(--green); font-size:0.75rem; font-weight:700;">[+ INCLUDE]</span>';
        if (id === diffExcludeCharId) return '<span style="color:var(--red); font-size:0.75rem; font-weight:700;">[- EXCLUDE]</span>';
    }
    return '';
}

function buildCharButtonHtml(c) {
    const cls = getCharClass(c.id);
    const badge = getCharBadge(c.id);
    return `
        <button class="phase-chip ${cls}" type="button" onclick="toggleTrackerChar(${c.id})">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                <strong>${escHtml(c.name)}</strong>
                ${badge}
            </div>
            <span style="font-size:0.75rem; color:var(--text-muted);">${c.total_beats} beats</span>
        </button>
    `;
}

function toggleTrackerChar(charId) {
    charId = parseInt(charId);

    if (trackerMode === 'single') {
        selectedTrackerCharIds = [charId];
    } else if (trackerMode === 'and' || trackerMode === 'or') {
        const idx = selectedTrackerCharIds.indexOf(charId);
        if (idx > -1) {
            if (selectedTrackerCharIds.length > 1) {
                selectedTrackerCharIds.splice(idx, 1);
            }
        } else {
            selectedTrackerCharIds.push(charId);
        }
    } else if (trackerMode === 'diff') {
        if (!diffIncludeCharId || diffIncludeCharId === charId) {
            diffIncludeCharId = charId;
        } else if (!diffExcludeCharId) {
            diffExcludeCharId = charId;
        } else if (diffExcludeCharId === charId) {
            diffExcludeCharId = null;
        } else {
            diffExcludeCharId = charId;
        }
    }

    renderTrackerView();
}

function updateTrackerSelectionInfo(charMap) {
    const infoEl = document.getElementById('trackerSelectionInfo');
    if (!infoEl) return;

    if (trackerSearchQuery) {
        infoEl.textContent = 'Search Mode Active';
        return;
    }

    if (trackerMode === 'single') {
        const c = charMap.get(selectedTrackerCharIds[0]);
        infoEl.textContent = c ? c.name : '';
    } else if (trackerMode === 'and') {
        infoEl.textContent = `${selectedTrackerCharIds.length} characters (All)`;
    } else if (trackerMode === 'or') {
        infoEl.textContent = `${selectedTrackerCharIds.length} characters (Any)`;
    } else if (trackerMode === 'diff') {
        const inc = charMap.get(diffIncludeCharId)?.name || 'None';
        const exc = charMap.get(diffExcludeCharId)?.name;
        infoEl.textContent = exc ? `+${inc} / -${exc}` : `+${inc} (Select exclude)`;
    }
}

function renderTrackerBeats(charMap, scoped) {
    const beatCharMap = new Map();
    const beatRolesMap = new Map();

    scoped.participants.forEach(p => {
        const bId = parseInt(p.beat_id);
        const cId = parseInt(p.char_id);
        if (!beatCharMap.has(bId)) {
            beatCharMap.set(bId, new Set());
            beatRolesMap.set(bId, []);
        }
        beatCharMap.get(bId).add(cId);
        if (p.role) beatRolesMap.get(bId).push(`${p.char_name}: ${p.role}`);
        else beatRolesMap.get(bId).push(p.char_name);
    });

    const threadMap = new Map();
    scoped.threads.forEach(t => threadMap.set(parseInt(t.id), t));

    const isSearchActive = !!trackerSearchQuery;
    const cleanSearch = trackerSearchQuery.toLowerCase().replace(/^#/, '');

    // Filter beats matching search or character mode
    const matchedBeats = scoped.beats.filter(b => {
        const bId = parseInt(b.id);
        const charsInBeat = beatCharMap.get(bId) || new Set();
        const t = threadMap.get(parseInt(b.thread_id)) || {};
        const roles = beatRolesMap.get(bId) || [];

        // Global on-the-fly Search Override
        if (isSearchActive) {
            if (String(b.id) === cleanSearch) return true;
            if (b.title && b.title.toLowerCase().includes(cleanSearch)) return true;
            if (b.summary && b.summary.toLowerCase().includes(cleanSearch)) return true;
            if (b.content && b.content.toLowerCase().includes(cleanSearch)) return true;
            if (b.beat_type && b.beat_type.toLowerCase().includes(cleanSearch)) return true;
            if (t.name && t.name.toLowerCase().includes(cleanSearch)) return true;
            if (t.episode_key && t.episode_key.toLowerCase().includes(cleanSearch)) return true;
            if (roles.some(r => r.toLowerCase().includes(cleanSearch))) return true;
            return false;
        }

        // Standard Character Filter Modes
        if (charsInBeat.size === 0) return false;

        if (trackerMode === 'single') {
            const target = selectedTrackerCharIds[0];
            return target ? charsInBeat.has(target) : false;
        } else if (trackerMode === 'and') {
            if (!selectedTrackerCharIds.length) return false;
            return selectedTrackerCharIds.every(cid => charsInBeat.has(cid));
        } else if (trackerMode === 'or') {
            if (!selectedTrackerCharIds.length) return false;
            return selectedTrackerCharIds.some(cid => charsInBeat.has(cid));
        } else if (trackerMode === 'diff') {
            if (!diffIncludeCharId) return false;
            if (!charsInBeat.has(diffIncludeCharId)) return false;
            if (diffExcludeCharId && charsInBeat.has(diffExcludeCharId)) return false;
            return true;
        }
        return false;
    }).map(b => {
        const t = threadMap.get(parseInt(b.thread_id)) || {};
        const roles = beatRolesMap.get(parseInt(b.id)) || [];
        return {
            ...b,
            thread_name: t.name || 'Unknown',
            phase: t.episode_key || 'UNASSIGNED',
            role_summary: roles.join(', ')
        };
    });

    // Sort matching beats
    matchedBeats.sort((a, b) => {
        const epCmp = (a.phase || '').localeCompare(b.phase || '');
        if (epCmp !== 0) return epCmp;
        const timeA = a.story_time_dt || '9999-12-31';
        const timeB = b.story_time_dt || '9999-12-31';
        const tCmp = timeA.localeCompare(timeB);
        if (tCmp !== 0) return tCmp;
        return (a.seq_order || 0) - (b.seq_order || 0);
    });

    // Group by phase
    const grouped = {};
    matchedBeats.forEach(b => {
        if (!grouped[b.phase]) grouped[b.phase] = [];
        grouped[b.phase].push(b);
    });

    // Render phase summary pills
    const phaseNames = Object.keys(grouped).sort();
    const emptyMsg = isSearchActive 
        ? `No beats match search: "${escHtml(trackerSearchQuery)}"`
        : (activeSeries ? `No matching beats for this selection in series "${escHtml(activeSeries)}".` : 'No matching beats for this selection.');

    document.getElementById('charPhaseSummary').innerHTML = phaseNames.map(phase => `
        <div class="phase-chip">
            <strong>${escHtml(phase)}</strong>
            <span>${grouped[phase].length} beats</span>
        </div>
    `).join('') || `<div style="padding:12px; color:var(--text-muted);">${emptyMsg}</div>`;

    // Render collapsible phase cards
    let html = '';
    phaseNames.forEach(phase => {
        const beats = grouped[phase];
        html += `
            <details class="phase-card" ${isSearchActive ? 'open' : ''}>
                <summary>
                    <span><strong>${escHtml(phase)}</strong> <span style="color:var(--text-muted);">(${beats.length})</span></span>
                    <i class="bi bi-chevron-down"></i>
                </summary>
                <div>
                    ${beats.map(b => `
                        <div class="beat-row" onclick="openBeatInspector(${b.id})">
                            <strong style="font-size:0.9rem;">
                                ${escHtml(b.title)} 
                                <span style="font-size:0.75rem; color:var(--text-muted); font-weight:normal; opacity:0.8;">#${b.id}</span>
                            </strong>
                            <span style="font-size:0.75rem; color:var(--text-muted);">
                                ${escHtml(b.beat_type)} • ${escHtml(b.thread_name)} ${b.role_summary ? '• ' + escHtml(b.role_summary) : ''}
                            </span>
                        </div>
                    `).join('')}
                </div>
            </details>
        `;
    });

    document.getElementById('charPhaseList').innerHTML = html || `<div style="padding:16px; color:var(--text-muted);">${emptyMsg}</div>`;
}

function openCharBottomSheet() {
    document.getElementById('charBottomSheetOverlay').classList.add('active');
    document.getElementById('charBottomSheet').classList.add('open');
}
function closeCharBottomSheet() {
    document.getElementById('charBottomSheetOverlay').classList.remove('active');
    document.getElementById('charBottomSheet').classList.remove('open');
}
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/gallery.php');
?>