<?php
// public/kg_edge_curator.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "KG Edge Curator";

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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
    --green:#238636; --red:#da3633; --warn:#b08800;
    --purple:#7c3aed; --purple-dim:rgba(124,58,237,0.08);
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    --green:#3fb950; --red:#f85149; --warn:#d29922;
    --purple:#a78bfa; --purple-dim:rgba(167,139,250,0.08);
}

html, body { margin:0; padding:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100%; width:100%; overflow:hidden; }
.layout-container { display: flex; flex-direction: column; width: 100%; height: 100%; }

.layout-main-split {
    display: flex;
    flex: 1;
    overflow: hidden;
    width: 100%;
    min-height: 0; /* <-- critical: lets flex children shrink below content size */
}

.curator-sidebar {
    width: 100%;
    background: var(--card);
    display: flex;
    flex-direction: column;
    min-height: 0; /* <-- critical */
    overflow: hidden;
}

.curator-main {
    display: none;
    width: 100%;
    flex-direction: column;
    background: var(--bg);
    min-height: 0; /* <-- critical */
    overflow: hidden;
}

@media (min-width: 768px) {
    .curator-sidebar {
        width: 340px;
        border-right: 1px solid var(--border);
        flex-shrink: 0;
    }
    .curator-main {
        display: flex;
        flex: 1;
        min-width: 0;
    }
    .mobile-back-btn { display: none !important; }
}

.sidebar-header { padding: 12px 16px; border-bottom: 1px solid var(--border); background: var(--bg); display: flex; flex-direction:column; gap: 8px;}
.sidebar-top-bar { display:flex; justify-content:space-between; align-items:center; }
.sidebar-tabs { display:flex; gap:10px; border-bottom:1px solid var(--border); }
.sb-tab { background:none; border:none; padding:6px 4px; font-size:0.85rem; font-weight:600; color:var(--text-muted); cursor:pointer; border-bottom:2px solid transparent; }
.sb-tab.active { color:var(--accent); border-bottom-color:var(--accent); }

.focal-list, .archive-tree-container { flex: 1; overflow-y: auto; padding: 12px; }
.archive-tree-container { display:none; padding:8px 0; }
.archive-tree-container.active { display:block; }
.focal-list.active { display:block; }
.focal-list { display:none; }

.focal-list {
    display: none;
    flex: 1;
    overflow-y: auto !important;   /* <-- was missing / overridden */
    overflow-x: hidden;
    padding: 12px;
    min-height: 0;      /* <-- critical for flex scroll */
    -webkit-overflow-scrolling: touch; /* smooth on Android Chrome */
}

.focal-list.active {
    display: flex;
    flex-direction: column;
}

.archive-tree-container {
    display: none;
    flex: 1;
    overflow-y: auto !important;
    overflow-x: hidden;
    padding: 8px 0;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
}

.archive-tree-container.active {
    display: block;
}

.focal-item { padding: 14px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: background 0.2s; background: var(--card); display: flex; flex-direction: column; gap: 6px;}
.focal-item:hover { background: rgba(59,130,246,0.05); border-color: var(--accent); }
.focal-item.active { background: rgba(59,130,246,0.1); border-color: var(--accent); box-shadow: inset 4px 0 0 var(--accent); }
.badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; background: var(--border); color: var(--text-muted); font-weight: bold; }

.main-header { padding: 16px; border-bottom: 1px solid var(--border); background: var(--card); display: flex; flex-direction: column; gap: 10px;}
@media (min-width: 768px) { .main-header { flex-direction: row; align-items: center; justify-content: space-between; padding: 16px 24px;} }

.main-content {
    flex: 1;
    overflow-y: auto !important;         /* <-- was already set but flex parent lacked min-height:0 */
    overflow-x: hidden;
    padding: 16px;
    min-height: 0;            /* <-- critical */
    -webkit-overflow-scrolling: touch;
}

.mobile-back-btn { display: inline-flex; align-items: center; gap: 5px; background: none; border: none; color: var(--accent); font-size: 1rem; padding: 0; cursor: pointer; margin-bottom: 8px;}

.proposal-row { background: var(--card); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 12px; padding: 16px; display: flex; align-items: flex-start; gap: 16px; transition: 0.2s;}
.proposal-row:active { background: rgba(59,130,246,0.05); }
.proposal-controls { display:flex; flex-direction:column; gap:8px; align-items:center; }
.prop-checkbox { width: 22px; height: 22px; cursor: pointer; margin:0; }
.cb-label-pro { font-size:0.7rem; font-weight:bold; color:var(--green); display:flex; flex-direction:column; align-items:center; cursor:pointer; }
.cb-label-rej { font-size:0.7rem; font-weight:bold; color:var(--red); display:flex; flex-direction:column; align-items:center; cursor:pointer; }
.cb-label-pro input { accent-color: var(--green); }
.cb-label-rej input { accent-color: var(--red); }

.proposal-body { flex: 1; min-width:0; }
.proposal-title { font-weight: bold; color: var(--text); font-size: 1.05rem; display: flex; align-items: center; flex-wrap: wrap; gap: 6px;}
.proposal-rel { background: rgba(236,72,153,.12); color: #ec4899; padding: 2px 8px; border-radius: 6px; font-size: 0.8rem; border: 1px solid rgba(236,72,153,.25); white-space: nowrap;}
.proposal-rationale { margin-top: 8px; font-size: 0.95rem; color: var(--text-muted); line-height: 1.5; font-style: italic; }

/* INLINE EDIT STYLES */
.editable-prop { cursor: text; outline: none; transition: 0.2s; }
.editable-prop:hover { filter: brightness(0.9); }
.editable-prop:focus { box-shadow: 0 0 0 2px var(--accent); }
.proposal-rationale.editable-prop { padding: 4px; border-radius: 4px; margin-left: -4px; margin-top: 4px; }


.btn { padding: 8px 14px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
.btn-secondary:hover { background: var(--border); }
.btn-sm { padding: 6px 12px; font-size: 0.85rem; }
.btn-danger { background: var(--red); color: #fff; }
.btn-success { background: var(--green); color: #fff; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.action-controls { display: flex; gap: 8px; flex-wrap: wrap; }

#toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--card); border-top: 4px solid var(--accent); padding: 12px 20px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: none; z-index: 10000; width: 90%; max-width: 420px; text-align: center; color: var(--text); }

/* --- TERMINAL LOWER THIRD --- */
.kge-terminal-panel { background: var(--card); border-top: 1px solid var(--border); display: flex; flex-direction: column; transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 900; }
.kge-terminal-panel.minimized { height: 46px !important; min-height: 46px !important; overflow: hidden; }
.kge-terminal-panel.normal { height: 38vh; min-height: 250px; }
.kge-terminal-panel.fullscreen { position: fixed; inset: 0; height: 100vh !important; z-index: 10000; border-top: none; }
.kge-terminal-header { height: 46px; display: flex; align-items: center; justify-content: space-between; padding: 0 10px; border-bottom: 1px solid var(--border); background: var(--bg); flex-shrink: 0; }
.kge-terminal-tabs { display: flex; gap: 5px; height: 100%; }
.kge-term-tab { background: none; border: none; border-bottom: 2px solid transparent; padding: 0 14px; color: var(--text-muted); cursor: pointer; font-weight: 600; font-size: 0.85rem; height: 100%; display: flex; align-items: center; gap: 6px; }
.kge-term-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.kge-terminal-actions { display: flex; gap: 8px; align-items: center; }
.kge-terminal-body { flex: 1; position: relative; background: var(--card); display: flex; flex-direction: column; overflow: hidden; }
.kge-term-content { position: absolute; inset: 0; display: none; overflow-y: auto; padding: 12px; }
.kge-term-content.active { display: block; }
.kge-term-log-area { background: #1e1e1e; color: #a5d6a7; font-family: 'Courier New', Courier, monospace; font-size: 0.8rem; padding: 12px; height: 100%; overflow-y: auto; border-radius: 6px; line-height: 1.5; }
.kge-term-log-line { margin-bottom: 4px; word-break: break-word; }
.kge-term-log-ts { color: #888; margin-right: 8px; }
.kge-term-log-err { color: #ef5350; }
.kge-term-log-info { color: #64b5f6; }
.kge-term-log-success { color: #81c784; }
.kge-q-item { padding: 10px 14px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; font-size: 0.9rem; background: var(--bg); transition: 0.2s; }
.kge-q-item:hover { border-color: var(--accent); }
.kge-q-item.status-running { border-left: 4px solid var(--accent); }
.kge-q-item.status-error { border-left: 4px solid var(--red); }
.kge-q-item.status-completed { border-left: 4px solid var(--green); opacity: 0.7; }
.kge-q-item.status-awaiting_offline { border-left: 4px solid var(--purple); }
.kge-q-item-info { flex: 1; }
.kge-q-actions { display: flex; gap: 6px; }

/* Tree Base */
.kge-tree-node { display: flex; align-items: center; gap: 6px; padding: 5px 10px; cursor: pointer; user-select: none; font-size: 0.9rem; border-radius: 4px; margin: 1px 4px; }
.kge-node-toggle { width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.7rem; color: var(--text-muted); cursor: pointer; border-radius: 3px; }
.kge-node-toggle.open { transform: rotate(90deg); }
.kge-node-icon { font-size: 0.9rem; flex-shrink: 0; opacity: 0.75; }
.kge-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kge-tree-node.is-folder > .kge-node-label { font-weight: 600; color: var(--text); }
.kge-tree-children { display: none; }
.kge-tree-children.open { display: block; }
.kge-tree-node input[type=checkbox] { width: 16px; height: 16px; margin: 0; flex-shrink:0; cursor:pointer;}

/* BATCH MODAL */
.kge-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 9990; }
.kge-modal-bg.open { display: flex; }
.kge-modal { width: min(680px, 96vw); max-height: 92vh; background: var(--card); border: 1px solid var(--border); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,0.45); }
.kge-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.kge-header h3 { margin: 0; font-size: 1.1rem; flex: 1; display: flex; align-items: center; gap: 8px; }
.kge-close { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 1.3rem; line-height: 1; padding: 2px 6px; border-radius: 4px; }
.kge-section-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--bg); }
.kge-section-tab { flex: 1; padding: 10px 8px; background: none; border: none; border-bottom: 2px solid transparent; color: var(--text-muted); font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.15s; }
.kge-section-tab.active { color: var(--accent); border-bottom-color: var(--accent); background: var(--card); }
.kge-section-tab.pot-active { color: var(--purple); border-bottom-color: var(--purple); background: var(--card); }
.kge-section-panel { display: none; flex: 1; flex-direction: column; overflow: hidden; }
.kge-section-panel.active { display: flex; }
.kge-picker-header { display: flex; align-items: center; gap: 8px; padding: 10px 20px 8px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.kge-picker-header span { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); flex: 1; }
.kge-picker-select-all { background: none; border: none; color: var(--accent); font-size: 0.8rem; cursor: pointer; padding: 0; font-weight: 600; }
.kge-picker-tree-wrap { flex: 1; overflow-y: auto; padding: 4px 0; background: var(--bg); }

/* Target Pot specifics */
.kge-pot-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.kge-pot-toolbar { padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
.kge-pot-toolbar input[type=search] { flex: 1; min-width: 140px; padding: 5px 8px; background: var(--bg); border: 1px solid var(--border); border-radius: 5px; color: var(--text); font-size: 0.8rem; outline: none; }
.kge-pot-toolbar input[type=search]:focus { border-color: var(--purple); }
.kge-pot-info { padding: 6px 16px 4px; font-size: 0.72rem; color: var(--text-muted); flex-shrink: 0; border-bottom: 1px solid var(--border); }
.kge-pot-tree-wrap { flex: 1; overflow-y: auto; padding: 4px 0; background: var(--bg); }
.kge-pot-tree-wrap .kge-tree-node input[type=checkbox] { accent-color: var(--purple); }

.kge-pot-chips { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 16px; border-bottom: 1px solid var(--border); min-height: 44px; max-height: 140px; overflow-y: auto; flex-shrink: 0; align-content: flex-start; }

.kge-pot-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; border: 1px solid var(--purple); color: var(--purple); background: var(--purple-dim); }
.kge-pot-chip-remove { background: none; border: none; cursor: pointer; color: inherit; opacity: 0.7; padding: 0; line-height: 1; }
.kge-pot-chip-remove:hover { opacity: 1; }
.kge-pot-empty { color: var(--text-muted); font-size: 0.78rem; padding: 2px 0; }
.kge-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-shrink: 0; }
.kge-footer-left { flex: 1; font-size: 0.85rem; color: var(--text-muted); }

/* --- GRAPHS MODALS --- */
.kg-minigraph-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.82); backdrop-filter: blur(3px); z-index: 100010; display: none; align-items: center; justify-content: center; }
.kg-minigraph-modal-bg.open { display: flex; }
.kg-minigraph-inner { width: 94vw; height: 92vh; background: var(--card); position: relative; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,0.6); }
.kg-minigraph-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: var(--card); color: var(--text); border: 1px solid var(--border); border-radius: 5px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem; z-index: 10; transition: background 0.15s; }
.kg-minigraph-close:hover { background: var(--bg); }
.kg-minigraph-iframe { width: 100%; height: 100%; border: none; }
.kge-pot-graph-modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.82); backdrop-filter: blur(3px); z-index: 100002; display: none; align-items: center; justify-content: center; }
.kge-pot-graph-modal-bg.open { display: flex; }
.kge-pot-graph-modal-inner { width: 94vw; height: 92vh; background: var(--card); border: 1px solid var(--border); border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,0.6); }
.kge-pot-graph-modal-header { padding: 10px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.kge-pot-graph-modal-title { font-size: 0.9rem; font-weight: 700; color: var(--purple); flex: 1; }
.kge-pot-graph-modal-close { background: none; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; cursor: pointer; font-size: 1rem; padding: 2px 8px; }
.kge-pot-graph-container { flex: 1; position: relative; overflow: hidden; }

/* Archive Edges */
.archive-edge-item { border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:10px; background:var(--card); display:flex; flex-direction:column; gap:6px; }
.archive-edge-header { display:flex; justify-content:space-between; align-items:center; }
.archive-badge-ai { background:var(--purple-dim); color:var(--purple); border:1px solid var(--purple); }
.archive-badge-rej { background:rgba(218,54,51,0.1); color:var(--red); border:1px solid var(--red); }
.archive-badge-man { background:rgba(100,116,139,0.1); color:var(--text-muted); border:1px solid var(--border); }

/* Node details modal */
.node-link-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:14px; border:1px solid var(--border); background:var(--bg); font-size:0.75rem; cursor:pointer; transition:0.2s; }
.node-link-pill:hover { background:var(--card); border-color:var(--accent); }

/* Dropdown for manual edge */
.dropdown-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border); display:flex; align-items:center; }
.dropdown-item:last-child { border-bottom:none; }
.dropdown-item:hover { background:var(--bg); }

</style>

<div class="layout-container">
    <div class="layout-main-split">
        <div class="curator-sidebar" id="sidebarPanel" style="height:550px;">
            <div class="sidebar-header">
                <div class="sidebar-top-bar">
                    <h3 style="margin:0; font-size:1.1rem;"><i class="bi bi-robot"></i> Curation</h3>
                    <button class="btn btn-primary btn-sm" onclick="openBatchModal()" id="btnGenerate">
                        <i class="bi bi-plus-lg"></i> Enqueue
                    </button>
                </div>
                <div class="sidebar-tabs">
                    <button class="sb-tab active" id="sbtab-pending" onclick="kgeSwitchSidebar('pending')">Pending Reviews</button>
                    <button class="sb-tab" id="sbtab-archive" onclick="kgeSwitchSidebar('archive')">Archive Browser</button>
                </div>
            </div>
            
            <div class="focal-list active" id="focalList">
                <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                    <i class="bi bi-inbox" style="font-size: 2.5rem; opacity:0.5; margin-bottom:10px; display:block;"></i>
                    No pending proposals.<br><br>Tap <b>Enqueue</b> to let the AI analyze nodes.
                </div>
            </div>

            <div class="archive-tree-container" id="archiveTreeWrap">
                <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">Loading Archive...</div>
            </div>
        </div>

        <div class="curator-main" id="mainPanel" style="height:550px;" >
            <div class="main-header">
                <div>
                    <button class="mobile-back-btn" onclick="showSidebar()"><i class="bi bi-chevron-left"></i> Back</button>
                    <h2 id="wkTitle" style="margin:0; font-size:1.3rem;">Select a Node</h2>
                    <span id="wkCount" style="color:var(--text-muted); font-size:0.9rem;"></span>
                </div>
                
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <!-- Always visible when node selected -->
                    <div class="action-controls" id="sharedControls" style="display:none;">
                        <button class="btn btn-secondary btn-sm" onclick="kgeOpenFocalGraph()" title="View node in mini-graph"><i class="bi bi-diagram-2-fill"></i> Graph</button>
                        <button class="btn btn-primary btn-sm" onclick="kgeOpenManualModal()" title="Manually add an edge"><i class="bi bi-plus-lg"></i> Edge</button>
                    </div>

                    <!-- Action controls for pending mode -->
                    <div class="action-controls" id="actionControls" style="display:none;">
                        <button class="btn btn-secondary btn-sm" onclick="kgeToggleAllCheckboxes('pro')"><i class="bi bi-check2-all"></i> All Pro</button>
                        <button class="btn btn-secondary btn-sm" onclick="kgeToggleAllCheckboxes('rej')"><i class="bi bi-x-lg"></i> All Rej</button>
                        <button class="btn btn-secondary btn-sm" onclick="kgeDismissLinked()" title="Auto-promote items that are already linked"><i class="bi bi-magic"></i> Dismiss Linked</button>
                        <button class="btn btn-secondary btn-sm" onclick="kgeExportProposals()" title="Export pending to JSON"><i class="bi bi-download"></i></button>
                        <button class="btn btn-primary btn-sm" onclick="processCuration()"><i class="bi bi-check2-circle"></i> Submit Selected</button>
                    </div>


                    <!-- Action controls for archive mode -->
                    <div class="action-controls" id="archiveControls" style="display:none;">
                        <select id="archiveFilter" onchange="kgeRenderArchiveEdges()" style="padding:6px; border-radius:4px; border:1px solid var(--border); background:var(--card); color:var(--text);">
                            <option value="all">All Relationships</option>
                            <option value="promoted">AI Promoted</option>
                            <option value="rejected">AI Rejected</option>
                            <option value="manual">Manual Only</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="main-content" id="proposalList">
                <div style="text-align:center; padding-top:100px; color:var(--text-muted);">
                    <i class="bi bi-diagram-3" style="font-size:4rem; opacity:0.2;"></i>
                    <p>Select a node from the sidebar to begin.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- TERMINAL LOWER THIRD -->
    <div id="kgeTerminal" class="kge-terminal-panel normal">
        <div class="kge-terminal-header">
            <div class="kge-terminal-tabs">
                <button class="kge-term-tab" id="tab-log" onclick="kgeSwitchTermTab('log')"><i class="bi bi-terminal"></i> Log</button>
                <button class="kge-term-tab active" id="tab-queue" onclick="kgeSwitchTermTab('queue')">
                    <i class="bi bi-list-task"></i> Queue 
                    <span id="queue-badge" class="badge" style="margin-left:4px;">0</span>
                </button>
            </div>
            <div class="kge-terminal-actions">
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem; font-weight:600; color:var(--text-muted); margin-right:10px;">
                    <input type="checkbox" id="kge-offline-toggle" onchange="kgeToggleOfflineMode(this.checked)" style="accent-color:var(--purple); width:16px; height:16px; margin:0;">
                    Offline Mode
                </label>
                <button id="kge-play-btn" class="btn btn-sm btn-success" onclick="kgeToggleQueuePlay()"><i class="bi bi-play-fill"></i> Play</button>
                <button class="btn btn-sm btn-secondary" onclick="kgeToggleTerminal()"><i class="bi bi-chevron-expand"></i></button>
            </div>
        </div>
        <div class="kge-terminal-body">
            <div id="kge-term-log" class="kge-term-content">
                <div class="kge-term-log-area" id="kgeLogArea">
                    <div class="kge-term-log-line"><span class="kge-term-log-ts">[Sys]</span> Ready. Select nodes and add to queue.</div>
                </div>
            </div>
            <div id="kge-term-queue" class="kge-term-content active">
                <div id="kge-queue-list">
                    <div style="padding:20px; text-align:center; color:var(--text-muted);">Queue is empty.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BATCH MODAL -->
<div class="kge-modal-bg" id="kge-modal-bg">
    <div class="kge-modal">
        <div class="kge-header">
            <h3><i class="bi bi-lightning-charge"></i> Start Edge Discovery Run</h3>
            <button class="kge-close" onclick="closeBatchModal()">&#x2715;</button>
        </div>
        <div class="kge-section-tabs">
            <button class="kge-section-tab active" id="kge-tab-pool" onclick="kgeSwitchTab('pool')"><i class="bi bi-grid"></i> Focal Node Pool</button>
            <button class="kge-section-tab" id="kge-tab-pot" onclick="kgeSwitchTab('pot')">
                <i class="bi bi-funnel"></i> Target Pot <span id="kge-pot-count-badge" style="display:none; background:var(--purple); color:#fff; border-radius:10px; padding:1px 6px; font-size:0.68rem;" ></span>
            </button>
        </div>
        <div class="kge-section-panel active" id="kge-panel-pool">
            <div style="padding: 10px 20px; font-size:0.85rem; color:var(--text-muted); background:var(--bg); border-bottom: 1px solid var(--border);">Select folders/nodes to limit the pool. Auto-picks 10 random nodes if empty.</div>
            <div class="kge-picker-header"><span>Select Pool Nodes</span><button class="kge-picker-select-all" onclick="kgePickerToggleAll()">Select all</button></div>
            <div class="kge-picker-tree-wrap" id="kge-picker-tree-wrap"><div style="padding: 20px; text-align: center;">Loading tree…</div></div>
        </div>
        <div class="kge-section-panel" id="kge-panel-pot">
            <div style="padding: 10px 20px; font-size:0.85rem; color:var(--text-muted); background:var(--bg); border-bottom: 1px solid var(--border);">Manually define candidate pool against which the focal node matches (bypasses Chroma).</div>
            <div class="kge-pot-toolbar">
                <input type="search" id="kge-pot-search" placeholder="Filter nodes…" autocomplete="off" oninput="kgePotFilterTree(this.value)">
                <button class="btn btn-secondary btn-sm" onclick="kgePotClear()"><i class="bi bi-trash"></i></button>
            </div>
            <div class="kge-pot-info" id="kge-pot-info">No nodes in target pot — AI will use Chroma instead.</div>
            <div class="kge-pot-chips" id="kge-pot-chips"><span class="kge-pot-empty">Empty — check nodes in the tree below</span></div>
            <div class="kge-pot-tree-wrap" id="kge-pot-tree-wrap"><div style="padding: 14px 20px; text-align: center;">Loading tree…</div></div>
        </div>
        <div class="kge-footer">
            <span class="kge-footer-left" id="kge-picker-count">No nodes selected (Auto-Pool)</span>
            <button class="btn btn-secondary" onclick="closeBatchModal()">Cancel</button>
            <button class="btn btn-primary" onclick="kgeEnqueueAndStart()"><i class="bi bi-play-circle"></i> Enqueue Tasks</button>
        </div>
    </div>
</div>

<!-- MANUAL EDGE MODAL -->
<div class="kge-modal-bg" id="kge-manual-modal-bg">
    <div class="kge-modal" style="max-width: 500px; overflow:visible;">
        <div class="kge-header">
            <h3><i class="bi bi-plus-circle"></i> Add Manual Edge</h3>
            <button class="kge-close" onclick="kgeCloseManualModal()">&#x2715;</button>
        </div>
        <div style="padding: 20px; display:flex; flex-direction:column; gap:14px; overflow:visible;">
            <div>
                <label style="font-size:0.8rem; color:var(--text-muted); font-weight:bold;">Focal Node</label>
                <div id="manual-focal-name" style="padding:8px; background:var(--bg); border:1px solid var(--border); border-radius:6px; font-weight:bold;"></div>
            </div>
            
            <div style="position:relative;">
                <label style="font-size:0.8rem; color:var(--text-muted); font-weight:bold;">Target Node (Search)</label>
                <input type="text" id="manual-target-search" placeholder="Type to search..." style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; background:var(--bg); color:var(--text);" oninput="kgeSearchTarget()">
                <div id="manual-target-results" style="display:none; position:absolute; top:100%; left:0; right:0; max-height:200px; overflow-y:auto; background:var(--card); border:1px solid var(--border); border-radius:6px; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,0.15);"></div>
            </div>
            
            <div id="manual-target-display" style="display:none; align-items:center; gap:8px; padding:8px; border:1px solid var(--green); background:rgba(35,134,54,0.05); border-radius:6px; color:var(--green); font-weight:bold;">
                <i class="bi bi-check-circle-fill"></i> <span id="manual-target-label"></span>
                <button class="btn btn-sm" style="margin-left:auto; padding:2px 6px; background:none; border:none; color:var(--text-muted);" onclick="kgeClearManualTarget()"><i class="bi bi-x-lg"></i></button>
            </div>
            <input type="hidden" id="manual-target-id">

            <div>
                <label style="font-size:0.8rem; color:var(--text-muted); font-weight:bold;">Relationship</label>
                <input type="text" id="manual-rel" placeholder="e.g. allied_with" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; background:var(--bg); color:var(--text);">
            </div>

            <div>
                <label style="font-size:0.8rem; color:var(--text-muted); font-weight:bold;">Item Label (Optional)</label>
                <input type="text" id="manual-item-label" placeholder="Display label snapshot..." style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; background:var(--bg); color:var(--text);">
            </div>

            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer; margin-top:8px;">
                <input type="checkbox" id="manual-commit" style="width:18px; height:18px; accent-color:var(--accent);">
                <strong>Commit immediately</strong>
            </label>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-left:26px; margin-top:-4px;">Bypass proposal, create edge directly in graph.</div>

        </div>
        <div class="kge-footer">
            <button class="btn btn-secondary" onclick="kgeCloseManualModal()">Cancel</button>
            <button class="btn btn-primary" onclick="kgeSubmitManualEdge()"><i class="bi bi-save"></i> Save</button>
        </div>
    </div>
</div>

<!-- JOB DETAILS MODAL -->
<div class="kge-modal-bg" id="kge-job-modal-bg">
    <div class="kge-modal" style="max-width: 500px;">
        <div class="kge-header">
            <h3><i class="bi bi-info-circle"></i> Queue Job Details</h3>
            <button class="kge-close" onclick="document.getElementById('kge-job-modal-bg').classList.remove('open')">&#x2715;</button>
        </div>
        <div style="padding: 20px; overflow-y:auto;" id="kge-job-details-body"></div>
    </div>
</div>

<!-- NODE DETAILS MODAL -->
<div id="kge-det-bg" onclick="if(event.target===this)kgeCloseNodeDetails()" style="position:fixed;inset:0;background:rgba(0,0,0,0.72);backdrop-filter:blur(3px);z-index:100020;display:none;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;width:100%;max-width:680px;margin:auto;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.6);">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;background:var(--bg);flex-shrink:0;position:sticky;top:0;z-index:2;">
            <span style="font-weight:700;font-size:0.95rem;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" id="kge-det-title">—</span>
            <span style="font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:8px;background:var(--purple-dim);color:var(--purple);border:1px solid rgba(167,139,250,0.3);flex-shrink:0;" id="kge-det-type"></span>
            <button onclick="kgeCloseNodeDetails()" style="background:none;border:none;color:var(--text-muted);font-size:1.4rem;cursor:pointer;line-height:1;padding:2px 6px;border-radius:4px;flex-shrink:0;">&times;</button>
        </div>
        <div id="kge-det-body" style="padding:20px 24px;overflow-y:auto;max-height:78vh;font-size:0.9rem;line-height:1.7;color:var(--text);">
            <div style="color:var(--text-muted);text-align:center;padding:40px 0;">Loading…</div>
        </div>
    </div>
</div>

<!-- OFFLINE INGEST MODAL -->
<div class="kge-modal-bg" id="kge-ingest-modal-bg">
    <div class="kge-modal" style="max-width: 600px; overflow:visible;">
        <div class="kge-header">
            <h3><i class="bi bi-upload"></i> Ingest Offline AI Result</h3>
            <button class="kge-close" onclick="kgeCloseIngestModal()">&#x2715;</button>
        </div>
        <div style="padding: 20px; display:flex; flex-direction:column; gap:14px; overflow:visible;">
            <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">Paste the raw JSON response from your external AI for this job.</p>
            <textarea id="kge-ingest-text" placeholder="Paste the exact JSON object you received here..." style="width:100%; height:200px; padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--bg); color:var(--text); font-family:monospace; resize:vertical; outline:none;"></textarea>
            <input type="hidden" id="kge-ingest-run-id">
        </div>
        <div class="kge-footer">
            <button class="btn btn-secondary" onclick="kgeCloseIngestModal()">Cancel</button>
            <button class="btn btn-primary" onclick="kgeSubmitIngest()"><i class="bi bi-check2-circle"></i> Ingest</button>
        </div>
    </div>
</div>

<!-- TARGET POT MINI-GRAPH MODAL -->
<div class="kge-pot-graph-modal-bg" id="kge-pot-graph-modal-bg">
    <div class="kge-pot-graph-modal-inner">
        <div class="kge-pot-graph-modal-header">
            <span class="kge-pot-graph-modal-title"><i class="bi bi-diagram-2-fill"></i> Node Graph — <span id="kge-pot-graph-seed-label" style="color:var(--text-muted);">—</span></span>
            <div style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:4px;">
                    <i class="bi bi-bezier2"></i> Hops
                    <select id="kge-pot-graph-hops" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:4px; padding:2px 5px; outline:none;">
                        <option value="1">1</option><option value="2">2</option><option value="3">3</option>
                    </select>
                </label>
                <input type="search" id="kge-pot-graph-search" placeholder="Search nodes…" style="padding:4px 8px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text); font-size:0.78rem; outline:none; width:130px;">
                <button class="kge-pot-graph-modal-close" onclick="kgeClosePotGraph()">&#x2715;</button>
            </div>
        </div>
        <div style="padding:6px 14px; font-size:0.72rem; color:var(--text-muted); border-bottom:1px solid var(--border); flex-shrink:0;">
            Click any node to open its panel. Use <b>Add to Pot</b> to include it as a candidate, or <b>Re-center</b> to explore further.
        </div>
        <div class="kge-pot-graph-container" id="kge-pot-graph-container">
            <div style="display:flex; align-items:center; justify-content:center; height:100%; color:var(--text-muted);">Select a starting node to load graph.</div>
        </div>
        <div id="kge-pot-graph-node-panel" style="position:absolute; top:60px; right:12px; z-index:50; width:190px; background:var(--card); border:1px solid var(--border); border-radius:8px; padding:12px; box-shadow:0 4px 20px rgba(0,0,0,.4); display:none; flex-direction:column; gap:8px;">
            <button onclick="kgePotGraphClosePanel()" style="position:absolute; top:5px; right:7px; background:none; border:none; color:var(--text-muted); font-size:1rem; cursor:pointer; line-height:1;">×</button>
            <div style="font-weight:700; font-size:0.82rem; word-break:break-word; line-height:1.3;" id="kge-pot-graph-panel-name">—</div>
            <div style="font-size:0.65rem; color:var(--purple); text-transform:uppercase; letter-spacing:1px;" id="kge-pot-graph-panel-type"></div>
            <div id="kge-pot-graph-panel-actions" style="display:flex; flex-direction:column; gap:5px;"></div>
        </div>
    </div>
</div>

<!-- FOCAL NODE MINI-GRAPH MODAL -->
<div class="kge-pot-graph-modal-bg" id="kge-focal-graph-modal-bg">
    <div class="kge-pot-graph-modal-inner">
        <div class="kge-pot-graph-modal-header">
            <span class="kge-pot-graph-modal-title" style="color:var(--accent);"><i class="bi bi-diagram-2-fill"></i> Node Graph — <span id="kge-focal-graph-seed-label" style="color:var(--text-muted);">—</span></span>
            <div style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:4px;">
                    <i class="bi bi-bezier2"></i> Hops
                    <select id="kge-focal-graph-hops" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:4px; padding:2px 5px; outline:none;">
                        <option value="1">1</option><option value="2">2</option><option value="3">3</option>
                    </select>
                </label>
                <input type="search" id="kge-focal-graph-search" placeholder="Search nodes…" style="padding:4px 8px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text); font-size:0.78rem; outline:none; width:130px;">
                <button class="kge-pot-graph-modal-close" onclick="kgeCloseFocalGraph()">&#x2715;</button>
            </div>
        </div>
        <div style="padding:6px 14px; font-size:0.72rem; color:var(--text-muted); border-bottom:1px solid var(--border); flex-shrink:0;">
            Click any node to open its panel. Use <b>Re-center</b> to explore further.
        </div>
        <div class="kge-pot-graph-container" id="kge-focal-graph-container">
            <div style="display:flex; align-items:center; justify-content:center; height:100%; color:var(--text-muted);">Select a starting node to load graph.</div>
        </div>
        <div id="kge-focal-graph-node-panel" style="position:absolute; top:60px; right:12px; z-index:50; width:190px; background:var(--card); border:1px solid var(--border); border-radius:8px; padding:12px; box-shadow:0 4px 20px rgba(0,0,0,.4); display:none; flex-direction:column; gap:8px;">
            <button onclick="kgeFocalGraphClosePanel()" style="position:absolute; top:5px; right:7px; background:none; border:none; color:var(--text-muted); font-size:1rem; cursor:pointer; line-height:1;">×</button>
            <div style="font-weight:700; font-size:0.82rem; word-break:break-word; line-height:1.3;" id="kge-focal-graph-panel-name">—</div>
            <div style="font-size:0.65rem; color:var(--accent); text-transform:uppercase; letter-spacing:1px;" id="kge-focal-graph-panel-type"></div>
            <div id="kge-focal-graph-panel-actions" style="display:flex; flex-direction:column; gap:5px;"></div>
        </div>
    </div>
</div>

<div id="toast"></div>

<!-- Sigma/Graphology for Target Pot graph -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

<script>
let globalProposals = [];
let currentFocalId = null;
let currentSidebarMode = 'pending'; // 'pending' or 'archive'
let archiveTreeRaw = [];
let archiveCounts = {};
let currentArchiveEdges = [];
let isQueuePlaying = false;
let terminalState = 'normal'; // minimized, normal, fullscreen
let currentRunningId = null;
let kgeLogSeen = new Set();
let kgeQueueData = [];
let isOfflineMode = false;

function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.innerText = msg;
    t.style.borderTopColor = isError ? 'var(--red)' : 'var(--accent)';
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3800);
}

function escapeHtml(text) {
    return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

async function fetchJsonSafe(url, options = {}) {
    const res = await fetch(url, options);
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch (e) {
        throw new Error(`Invalid JSON from ${url}: ${text ? text.slice(0, 500) : '(empty)'}`);
    }
    if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
    return data;
}

function showError(err) {
    showToast((err && err.message) ? err.message : String(err || 'Unknown error'), true);
    console.error('[KG Edge Curator]', err);
}

function showSidebar() {
    if (window.innerWidth < 768) {
        document.getElementById('sidebarPanel').style.display = 'flex';
        document.getElementById('mainPanel').style.display = 'none';
    }
    currentFocalId = null;
    document.getElementById('sharedControls').style.display = 'none';
    document.querySelectorAll('.focal-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.archive-tree-container .kge-tree-node').forEach(el => el.style.color = '');
}

function showMainPanel() {
    if (window.innerWidth < 768) {
        document.getElementById('sidebarPanel').style.display = 'none';
        document.getElementById('mainPanel').style.display = 'flex';
    }
}

window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        document.getElementById('sidebarPanel').style.display = 'flex';
        document.getElementById('mainPanel').style.display = 'flex';
    } else {
        if (currentFocalId) {
            document.getElementById('sidebarPanel').style.display = 'none';
            document.getElementById('mainPanel').style.display = 'flex';
        } else {
            document.getElementById('sidebarPanel').style.display = 'flex';
            document.getElementById('mainPanel').style.display = 'none';
        }
    }
});

// JSON Export Handlers
function kgeDownloadJson(data, filename) {
    const jsonStr = JSON.stringify(data, null, 2);
    const blob = new Blob([jsonStr], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function kgeExportQueueItem(runUuid) {
    showToast("Preparing export...");
    fetch(`api_kg_edge_queue.php?action=export_run_data&run_id=${encodeURIComponent(runUuid)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) throw new Error(res.error || "Failed to export run");
            const safeName = (res.export_data.focal_node.name || 'node').replace(/[^a-z0-9]/gi, '_').toLowerCase();
            kgeDownloadJson(res.export_data, `kge_queue_run_${safeName}_${runUuid.slice(0,6)}.json`);
        })
        .catch(err => showError(err));
}

function kgeExportProposals() {
    if (!currentFocalId) return;
    const nameStr = document.getElementById('wkTitle').innerText || 'node';
    showToast("Preparing export...");
    fetch(`api_kg_edge_queue.php?action=export_proposals&focal_id=${encodeURIComponent(currentFocalId)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) throw new Error(res.error || "Failed to export proposals");
            const safeName = nameStr.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            kgeDownloadJson(res.export_data, `kge_proposals_${safeName}_${currentFocalId}.json`);
        })
        .catch(err => showError(err));
}

// INLINE PROPOSAL EDITING
function kgeSavePropEdit(id, field, value, el) {
    const val = value.trim();
    const prop = globalProposals.find(p => Number(p.id) === Number(id));
    if (!prop) return;

    if (prop[field] === val) return; 

    fetch('api_kg_edge_queue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'update_proposal', id: id, field: field, value: val })
    }).then(r => r.json()).then(res => {
        if (res.ok) {
            prop[field] = val; 
            showToast('Updated successfully.');
        } else {
            showToast('Failed to update: ' + res.error, true);
            el.innerText = prop[field]; 
        }
    }).catch(e => {
        showToast('Network error', true);
        el.innerText = prop[field]; 
    });
}

// -------------------------------------------------------------
// OFFLINE MODE LOGIC
// -------------------------------------------------------------
function kgeToggleOfflineMode(checked) {
    isOfflineMode = checked;
    fetch('api_kg_edge_queue.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'set_offline_mode', offline: checked })
    }).then(r=>r.json()).then(res => {
        if(res.ok) {
            showToast(`Offline mode ${checked ? 'enabled' : 'disabled'}.`);
            kgeAppendLog(`[System] Offline mode ${checked ? 'enabled' : 'disabled'}.`);
            if (!checked && isQueuePlaying) kgeProcessNextInQueue();
        }
    });
}

function kgeExportOfflineJob(runId) {
    showToast("Preparing offline request package...");
    fetch(`api_kg_edge_queue.php?action=export_offline_job&run_id=${encodeURIComponent(runId)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) throw new Error(res.error || "Failed to export offline job");
            const safeName = (res.export_data.focal_node_name || 'node').replace(/[^a-z0-9]/gi, '_').toLowerCase();
            kgeDownloadJson(res.export_data, `kge_offline_req_${safeName}_${runId.slice(0,6)}.json`);
            kgeFetchQueue(); 
        })
        .catch(err => showError(err));
}

function kgeOpenIngestModal(runId) {
    document.getElementById('kge-ingest-run-id').value = runId;
    document.getElementById('kge-ingest-text').value = '';
    document.getElementById('kge-ingest-modal-bg').classList.add('open');
}

function kgeCloseIngestModal() {
    document.getElementById('kge-ingest-modal-bg').classList.remove('open');
}

function kgeSubmitIngest() {
    const runId = document.getElementById('kge-ingest-run-id').value;
    const text = document.getElementById('kge-ingest-text').value.trim();

    if (!text) {
        showToast("Please paste the AI answer.", true);
        return;
    }

    const btn = document.querySelector('#kge-ingest-modal-bg .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Ingesting...';

    fetch('api_kg_edge_queue.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'ingest_offline_result', run_id: runId, answer_text: text })
    }).then(r => r.json()).then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> Ingest';

        if (res.ok) {
            showToast("Offline answer ingested successfully!");
            kgeCloseIngestModal();
            kgeFetchQueue();
            if (isQueuePlaying) kgeProcessNextInQueue();
        } else {
            showToast(res.error || "Failed to ingest offline result.", true);
        }
    }).catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> Ingest';
        showError(err);
    });
}


// -------------------------------------------------------------
// MANUAL EDGE CREATION
// -------------------------------------------------------------
function kgeOpenManualModal() {
    if(!currentFocalId) return;
    document.getElementById('manual-focal-name').innerText = document.getElementById('wkTitle').innerText;
    document.getElementById('manual-target-search').value = '';
    document.getElementById('manual-rel').value = '';
    document.getElementById('manual-item-label').value = '';
    document.getElementById('manual-commit').checked = false;
    kgeClearManualTarget();
    document.getElementById('kge-manual-modal-bg').classList.add('open');
}

function kgeCloseManualModal() {
    document.getElementById('kge-manual-modal-bg').classList.remove('open');
}

let kgeManualSearchTimeout = null;
function kgeSearchTarget() {
    clearTimeout(kgeManualSearchTimeout);
    const q = document.getElementById('manual-target-search').value.trim();
    const resDiv = document.getElementById('manual-target-results');
    
    if (q.length < 2) { resDiv.style.display = 'none'; return; }
    
    kgeManualSearchTimeout = setTimeout(() => {
        fetch('kg_api.php?action=search&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (data.ok && data.results && data.results.length) {
                    resDiv.innerHTML = data.results.map(n => 
                        `<div class="dropdown-item" onclick="kgeSelectManualTarget(${n.id}, '${escapeHtml(n.name).replace(/'/g,"\\'").replace(/"/g,"&quot;")}')">
                            <span class="badge" style="margin-right:6px;">${n.id}</span>${escapeHtml(n.name)} <span style="font-size:0.7rem;color:var(--text-muted);margin-left:4px;">(${n.node_type})</span>
                        </div>`
                    ).join('');
                    resDiv.style.display = 'block';
                } else {
                    resDiv.innerHTML = '<div style="padding:8px; color:var(--text-muted);">No results</div>';
                    resDiv.style.display = 'block';
                }
            });
    }, 300);
}

function kgeSelectManualTarget(id, name) {
    document.getElementById('manual-target-id').value = id;
    document.getElementById('manual-target-label').innerText = name;
    document.getElementById('manual-target-search').value = '';
    document.getElementById('manual-target-results').style.display = 'none';
    document.getElementById('manual-target-display').style.display = 'flex';
}

function kgeClearManualTarget() {
    document.getElementById('manual-target-id').value = '';
    document.getElementById('manual-target-label').innerText = '';
    document.getElementById('manual-target-display').style.display = 'none';
}

function kgeSubmitManualEdge() {
    const targetId = document.getElementById('manual-target-id').value;
    const targetName = document.getElementById('manual-target-label').innerText;
    const rel = document.getElementById('manual-rel').value.trim();
    const label = document.getElementById('manual-item-label').value.trim();
    const commit = document.getElementById('manual-commit').checked;

    if (!targetId || !targetName) {
        showToast('Please select a target node.', true);
        return;
    }
    
    fetch('api_kg_edge_queue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'add_manual_edge',
            focal_id: currentFocalId,
            target_id: targetId,
            target_name: targetName,
            relationship: rel,
            item_label: label,
            commit_immediate: commit
        })
    }).then(r => r.json()).then(res => {
        if(res.ok) {
            kgeCloseManualModal();
            showToast(commit ? 'Edge committed to graph.' : 'Proposal added to queue.');
            
            // Refresh current view
            const title = document.getElementById('wkTitle').innerText;
            if(currentSidebarMode === 'pending') {
                fetch('api_kg_edge_queue.php?action=fetch_pending').then(r=>r.json()).then(pRes => {
                    if(pRes.ok) {
                        globalProposals = pRes.proposals || [];
                        selectFocal(currentFocalId, title, 'Updated');
                    }
                });
            } else {
                kgeSelectArchiveNode(currentFocalId, title, null);
            }
        } else {
            showToast(res.error || 'Failed to add manual edge.', true);
        }
    }).catch(e => {
        showToast('Network error.', true);
    });
}


// -------------------------------------------------------------
// PENDING REVIEWS
// -------------------------------------------------------------
function kgeSwitchSidebar(mode) {
    currentSidebarMode = mode;
    document.getElementById('sbtab-pending').classList.toggle('active', mode === 'pending');
    document.getElementById('sbtab-archive').classList.toggle('active', mode === 'archive');
    
    document.getElementById('focalList').classList.toggle('active', mode === 'pending');
    document.getElementById('archiveTreeWrap').classList.toggle('active', mode === 'archive');

    document.getElementById('sharedControls').style.display = 'none';
    document.getElementById('actionControls').style.display = 'none';
    document.getElementById('archiveControls').style.display = 'none';
    document.getElementById('proposalList').innerHTML = `<div style="text-align:center; padding-top:100px; color:var(--text-muted);"><i class="bi bi-diagram-3" style="font-size:4rem; opacity:0.2;"></i><p>Select a node from the sidebar.</p></div>`;
    document.getElementById('wkTitle').innerText = 'Select a Node';
    document.getElementById('wkCount').innerText = '';
    currentFocalId = null;

    if (mode === 'archive') {
        kgeLoadArchiveTree();
    } else {
        loadPending();
    }
}

function loadPending() {
    if (currentSidebarMode !== 'pending') return;
    fetch('api_kg_edge_queue.php?action=fetch_pending')
        .then(r => r.json())
        .then(res => {
            if (!res.ok) return;
            globalProposals = res.proposals || [];
            const list = document.getElementById('focalList');
            list.innerHTML = '';

            if (!(res.focal_nodes || []).length) {
                list.innerHTML = `
                    <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                        <i class="bi bi-inbox" style="font-size: 2.5rem; opacity:0.5; margin-bottom:10px; display:block;"></i>
                        No pending proposals.<br><br>Tap <b>Enqueue</b> to let the AI analyze nodes.
                    </div>`;
                return;
            }

            res.focal_nodes.forEach(f => {
                const div = document.createElement('div');
                div.className = `focal-item ${Number(f.focal_node_id) === Number(currentFocalId) ? 'active' : ''}`;
                div.onclick = () => selectFocal(f.focal_node_id, f.focal_name, f.pending_count, div);
                div.innerHTML = `
                    <div style="font-weight:bold; font-size:1.05rem;">${escapeHtml(f.focal_name)}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                        <span style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase;">${escapeHtml(f.node_type || 'node')}</span>
                        <span class="badge">${escapeHtml(String(f.pending_count))} proposals</span>
                    </div>
                `;
                list.appendChild(div);
            });
        });
}

function selectFocal(id, name, count, el) {
    currentFocalId = id;
    document.querySelectorAll('.focal-item').forEach(item => item.classList.remove('active'));
    if (el) el.classList.add('active');

    document.getElementById('wkTitle').innerText = name;
    document.getElementById('wkCount').innerText = `${count} proposed relationships`;
    document.getElementById('sharedControls').style.display = 'flex';
    document.getElementById('actionControls').style.display = 'flex';
    document.getElementById('archiveControls').style.display = 'none';

    const props = globalProposals.filter(p => Number(p.focal_node_id) === Number(id));
    const container = document.getElementById('proposalList');
    
    let phtml = ``;
    props.forEach(p => {
        const isDup = Number(p.is_duplicate) === 1;
        const dupBadge = isDup ? '<span class="badge" style="background:var(--warn);color:#fff;border:none;">Already Linked</span>' : '';
phtml += `
            <div class="proposal-row" data-is-dup="${isDup ? '1' : '0'}">


                <div class="proposal-controls">
                    <label class="cb-label-pro" title="Promote">
                        <input type="checkbox" class="prop-cb-promote" value="${escapeHtml(p.id)}" onchange="if(this.checked) this.closest('.proposal-row').querySelector('.prop-cb-reject').checked=false;">
                        Pro
                    </label>
                    <label class="cb-label-rej" title="Reject">
                        <input type="checkbox" class="prop-cb-reject" value="${escapeHtml(p.id)}" onchange="if(this.checked) this.closest('.proposal-row').querySelector('.prop-cb-promote').checked=false;">
                        Rej
                    </label>
                </div>
                <div class="proposal-body">
                    <div class="proposal-title">
                        <i class="bi bi-link-45deg" style="color:var(--text-muted);"></i>
                        ${escapeHtml(p.target_name)}
                        <span class="proposal-rel editable-prop" contenteditable="true" spellcheck="false" title="Tap to edit" onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}" onblur="kgeSavePropEdit(${p.id}, 'relationship', this.innerText, this)">${escapeHtml(p.relationship || 'linked_to')}</span>
                        ${dupBadge}
                    </div>
                    <div class="proposal-rationale editable-prop" contenteditable="true" spellcheck="false" title="Tap to edit" onblur="kgeSavePropEdit(${p.id}, 'rationale', this.innerText, this)">${escapeHtml(p.rationale || '')}</div>
                </div>
            </div>
        `;
    });
    container.innerHTML = phtml;

    showMainPanel();
}

function kgeToggleAllCheckboxes(type) {
    const isPro = type === 'pro';
    const targets = document.querySelectorAll(isPro ? '.prop-cb-promote' : '.prop-cb-reject');
    const opposites = document.querySelectorAll(isPro ? '.prop-cb-reject' : '.prop-cb-promote');
    
    const shouldCheck = Array.from(targets).some(b => !b.checked);
    targets.forEach(b => b.checked = shouldCheck);
    if (shouldCheck) {
        opposites.forEach(b => b.checked = false);
    }
}

function kgeDismissLinked() {
    let found = false;
    document.querySelectorAll('.proposal-row[data-is-dup="1"]').forEach(row => {
        const proCb = row.querySelector('.prop-cb-promote');
        const rejCb = row.querySelector('.prop-cb-reject');
        if (proCb) { proCb.checked = true; found = true; }
        if (rejCb) rejCb.checked = false;
    });
    
    if (found) {
        processCuration();
    } else {
        showToast("No 'Already Linked' items found for this node.", true);
    }
}

function processCuration() {
    if (!currentFocalId) return;
    
    const promoted = [], rejected = [];
    document.querySelectorAll('.prop-cb-promote:checked').forEach(b => promoted.push(Number(b.value)));
    document.querySelectorAll('.prop-cb-reject:checked').forEach(b => rejected.push(Number(b.value)));

    if (promoted.length === 0 && rejected.length === 0) {
        showToast("No items checked. Check boxes to promote or reject proposals.", true);
        return;
    }

    const btn = document.querySelector('#actionControls .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Saving...';

    fetch('api_kg_edge_queue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'process_curation', promoted_ids: promoted, rejected_ids: rejected })
    }).then(r => r.json()).then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> Submit Selected';
        if (res.ok) {
            showToast(`Success: ${res.promoted} promoted, ${res.rejected} rejected.`);
            // Remain on page if there are unchecked items
            const totalCbs = document.querySelectorAll('.prop-cb-promote').length;
            if (promoted.length + rejected.length >= totalCbs) {
                document.getElementById('proposalList').innerHTML = '<div style="text-align:center; padding-top:100px; color:var(--green);"><i class="bi bi-check-circle" style="font-size:4rem;"></i><p style="font-size:1.1rem; margin-top:10px;">Processed successfully!</p></div>';
                document.getElementById('actionControls').style.display = 'none';
                document.getElementById('sharedControls').style.display = 'none';
                currentFocalId = null;
                setTimeout(() => { showSidebar(); loadPending(); }, 900);
            } else {
                loadPending(); // refresh remaining list quietly
                setTimeout(() => { 
                    if (currentFocalId) selectFocal(currentFocalId, document.getElementById('wkTitle').innerText, 'Remaining');
                }, 300);
            }
        } else {
            showToast(res.error || 'Error processing curation.', true);
        }
    }).catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> Submit Selected';
        showToast('Network error while saving curation.', true);
    });
}

// -------------------------------------------------------------
// ARCHIVE BROWSER
// -------------------------------------------------------------
function kgeLoadArchiveTree() {
    const wrap = document.getElementById('archiveTreeWrap');
    wrap.innerHTML = '<div style="text-align:center; padding:40px 20px; color:var(--text-muted);">Loading Archive...</div>';
    
    // Fetch base tree and counts
    Promise.all([
        fetch('kg_api.php?action=fetch_tree').then(r => r.json()),
        fetch('api_kg_edge_queue.php?action=fetch_archive_counts').then(r => r.json())
    ]).then(([treeRes, countsRes]) => {
        if (!treeRes.ok || !countsRes.ok) {
            wrap.innerHTML = '<div style="color:var(--red); padding:20px; text-align:center;">Failed to load archive.</div>';
            return;
        }
        archiveTreeRaw = treeRes.tree;
        archiveCounts = countsRes.counts || {};
        kgeRenderArchiveTree();
    }).catch(e => {
        wrap.innerHTML = `<div style="color:var(--red); padding:20px; text-align:center;">${escapeHtml(e.message)}</div>`;
    });
}

function kgeRenderArchiveTree() {
    const wrap = document.getElementById('archiveTreeWrap');
    const childMap = {};
    archiveTreeRaw.forEach(n => { const p = n.parent || '#'; if (!childMap[p]) childMap[p] = []; childMap[p].push(n); });

    function buildLevel(parentId, depth) {
        const children = childMap[parentId] || [];
        if (!children.length) return '';
        const indent = depth * 14;
        let html = '';
        children.forEach(node => {
            const isFolder = node.type === 'folder', jsId = node.id;
            const hasKids = !!(childMap[jsId] && childMap[jsId].length);
            const icon = isFolder ? '📁' : '📝';
            const toggleBtn = (isFolder && hasKids)
                ? `<span class="kge-node-toggle" onclick="this.classList.toggle('open'); document.getElementById('arc-kids-${jsId}').classList.toggle('open');">▶</span>`
                : `<span style="width:18px;display:inline-block;flex-shrink:0;"></span>`;
            
            let labelExtra = '';
            let onClick = '';
            let hasContent = false;
            
            if (isFolder) {
                onClick = `onclick="this.closest('.kge-tree-node').querySelector('.kge-node-toggle').click()"`;
            } else {
                const dbId = node.data && node.data.db_id ? node.data.db_id : null;
                const c = archiveCounts[dbId] || {all:0, pro:0, rej:0};
                if (c.all > 0 || c.pro > 0 || c.rej > 0) {
                    labelExtra = ` <span style="font-size:0.7rem; color:var(--text-muted); opacity:0.8;">[All:${c.all}|AI:${c.pro}|Rej:${c.rej}]</span>`;
                    hasContent = true;
                }
                onClick = `onclick="kgeSelectArchiveNode(${dbId}, '${escapeHtml(node.text).replace(/'/g,"\\'").replace(/"/g,"&quot;")}', this)"`;
            }

            // Only render nodes that are folders or have counts > 0 to keep tree clean?
            // Actually, showing the whole tree helps context. We just dim nodes with no relationships.
            const styleExtra = (!isFolder && !hasContent) ? "opacity:0.4;" : "";
            
            html += `
            <div class="kge-tree-node ${isFolder ? 'is-folder' : 'is-node'}" style="padding-left:${10 + indent}px; ${styleExtra}">
                ${toggleBtn}
                <span class="kge-node-icon">${icon}</span>
                <span class="kge-node-label" ${onClick} style="flex:1;">${escapeHtml(node.text)}${labelExtra}</span>
            </div>`;
            if (hasKids) {
                html += `<div class="kge-tree-children" id="arc-kids-${jsId}">${buildLevel(jsId, depth + 1)}</div>`;
            }
        });
        return html;
    }

    wrap.innerHTML = buildLevel('#', 0);
}

function kgeSelectArchiveNode(focalId, focalName, el) {
    currentFocalId = focalId;
    document.querySelectorAll('.archive-tree-container .kge-tree-node .kge-node-label').forEach(n => n.style.color = '');
    if(el) el.style.color = 'var(--accent)';

    document.getElementById('wkTitle').innerText = focalName;
    document.getElementById('wkCount').innerText = `Archive Relationships`;
    document.getElementById('sharedControls').style.display = 'flex';
    document.getElementById('actionControls').style.display = 'none';
    document.getElementById('archiveControls').style.display = 'flex';
    document.getElementById('archiveFilter').value = 'all';

    const container = document.getElementById('proposalList');
    container.innerHTML = `<div style="text-align:center; padding:40px;"><div class="spinner"></div>Loading edges...</div>`;

    fetch('api_kg_edge_queue.php?action=fetch_archive_edges&focal_id=' + focalId)
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                currentArchiveEdges = res.edges || [];
                kgeRenderArchiveEdges();
                showMainPanel();
            } else {
                container.innerHTML = `<div style="color:var(--red); padding:20px;">Failed to load relationships.</div>`;
            }
        });
}

function kgeRenderArchiveEdges() {
    const container = document.getElementById('proposalList');
    const filter = document.getElementById('archiveFilter').value;
    
    let filtered = currentArchiveEdges;
    if (filter === 'promoted') filtered = currentArchiveEdges.filter(e => e.status === 'promoted');
    if (filter === 'rejected') filtered = currentArchiveEdges.filter(e => e.status === 'rejected');
    if (filter === 'manual') filtered = currentArchiveEdges.filter(e => !e.is_ai);

    if (!filtered.length) {
        container.innerHTML = `<div style="text-align:center; padding:40px; color:var(--text-muted);">No relationships match this filter.</div>`;
        return;
    }

    let html = '';
    filtered.forEach(e => {
        let badgeClass = 'archive-badge-man';
        let badgeText = 'Manual';
        if (e.status === 'promoted') { badgeClass = 'archive-badge-ai'; badgeText = 'AI Promoted'; }
        if (e.status === 'rejected') { badgeClass = 'archive-badge-rej'; badgeText = 'AI Rejected'; }

        html += `
            <div class="archive-edge-item">
                <div class="archive-edge-header">
                    <div style="font-weight:bold; color:var(--text); display:flex; align-items:center; gap:8px;">
                        <span class="badge ${badgeClass}">${badgeText}</span>
                        <i class="bi bi-link"></i> ${escapeHtml(e.target_name)}
                        <span style="font-size:0.75rem; color:var(--purple); border:1px solid var(--purple-dim); padding:1px 6px; border-radius:4px;">${escapeHtml(e.relationship)}</span>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-secondary btn-sm" onclick="kgeRevertEdge(${e.target_node_id})" title="Reintroduce to queue for evaluation"><i class="bi bi-arrow-return-left"></i> Re-eval</button>
                    </div>
                </div>
                ${e.rationale ? `<div style="font-size:0.85rem; color:var(--text-muted); font-style:italic; border-left:2px solid var(--border); padding-left:8px; margin-top:4px;">"${escapeHtml(e.rationale)}"</div>` : ''}
            </div>
        `;
    });
    container.innerHTML = html;
}

function kgeRevertEdge(targetId) {
    if(!confirm("Reintroduce this relationship to the pending queue? (It will be removed from the graph until re-promoted)")) return;
    
    fetch('api_kg_edge_queue.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'revert_edge', focal_id: currentFocalId, target_id: targetId })
    }).then(r => r.json()).then(res => {
        if(res.ok) {
            showToast('Edge returned to pending queue.');
            // Reload the archive view to remove the reverted edge from list
            const title = document.getElementById('wkTitle').innerText;
            kgeSelectArchiveNode(currentFocalId, title, null);
        } else {
            showToast(res.error || 'Failed to revert edge', true);
        }
    });
}

// -------------------------------------------------------------
// INTERCONNECTED MODAL (Node Details)
// -------------------------------------------------------------
function kgeOpenNodeDetails(nodeId, nodeName, nodeType) {
    document.getElementById('kge-det-title').textContent = nodeName || '—';
    document.getElementById('kge-det-type').textContent = nodeType || 'node';
    document.getElementById('kge-det-body').innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:40px 0;">Loading…</div>';
    document.getElementById('kge-det-bg').style.display = 'flex';

    fetch('kg_api.php?action=get_node&id=' + encodeURIComponent(nodeId))
        .then(r => r.json())
        .then(res => {
            if (!res.ok || !res.node) { document.getElementById('kge-det-body').innerHTML = '<div style="color:var(--red);padding:20px;">Failed to load node.</div>'; return; }
            const n = res.node;
            let html = '';
            if (n.description) html += `<p style="color:var(--text-muted);font-style:italic;margin:0 0 14px;border-left:3px solid var(--purple);padding-left:10px;">${escapeHtml(n.description)}</p>`;
            if (n.keywords) html += `<div style="margin-bottom:12px;font-size:0.8rem;color:var(--text-muted);"><strong style="color:var(--text);">Keywords:</strong> ${escapeHtml(n.keywords)}</div>`;
            if (n.content && n.content.trim()) {
                html += `<div style="white-space:pre-wrap;font-family:inherit;font-size:0.88rem;line-height:1.65;">${escapeHtml(n.content.trim())}</div>`;
            } else {
                html += `<div style="color:var(--text-muted);font-style:italic;">No content yet.</div>`;
            }
            const edges = (n.items || []).filter(i => i.item_type === 'kg_node');
            if (edges.length) {
                html += `<div style="margin-top:18px;border-top:1px solid var(--border);padding-top:12px;"><div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:8px;">Connections (${edges.length})</div><div style="display:flex;flex-wrap:wrap;gap:5px;">`;
                edges.forEach(e => {
                    const targetId = e.direction === 'incoming' ? e.node_id : e.item_id;
                    const targetName = e.direction === 'incoming' ? e.source_node_name : e.item_label;
                    const targetType = e.direction === 'incoming' ? e.source_node_type : 'note';
                    const dir = e.direction === 'incoming' ? '← ' : '→ ';
                    const rel = e.relationship ? ` · <span style="opacity:0.6;font-size:0.7rem;">${escapeHtml(e.relationship)}</span>` : '';
                    
                    // The pill is clickable and calls kgeOpenNodeDetails on the target node
                    const safeName = escapeHtml(targetName).replace(/'/g,"\\'").replace(/"/g,"&quot;");
                    html += `<span class="node-link-pill" onclick="kgeOpenNodeDetails(${targetId}, '${safeName}', '${targetType}')">${dir}${escapeHtml(targetName)}${rel}</span>`;
                });
                html += `</div></div>`;
            }
            document.getElementById('kge-det-body').innerHTML = html;
        })
        .catch(e => { document.getElementById('kge-det-body').innerHTML = `<div style="color:var(--red);padding:20px;">${escapeHtml(e.message)}</div>`; });
}

function kgeCloseNodeDetails() {
    document.getElementById('kge-det-bg').style.display = 'none';
}


// =====================================================================
// MODAL SECTION TABS (Batch)
// =====================================================================
function kgeSwitchTab(tab) {
    ['pool', 'pot'].forEach(t => {
        document.getElementById('kge-panel-' + t).classList.toggle('active', t === tab);
        const tabEl = document.getElementById('kge-tab-' + t);
        tabEl.classList.remove('active', 'pot-active');
        if (t === tab) tabEl.classList.add(t === 'pot' ? 'pot-active' : 'active');
    });
}

// =====================================================================
// POOL TREE LOGIC
// =====================================================================
let kgePickerRaw = [];
let kgePickerChecked = new Set();
const KGE_PICKER_OPEN_KEY = 'kg_picker_open_folders';

function openBatchModal() {
    document.getElementById('kge-modal-bg').classList.add('open');
    kgeLoadPickerTree();
    kgePotLoadTree();
}

function closeBatchModal() {
    document.getElementById('kge-modal-bg').classList.remove('open');
}

document.getElementById('kge-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) closeBatchModal();
});

function kgePickerSaveOpenState() {
    const open = [];
    document.querySelectorAll('#kge-picker-tree-wrap .kge-tree-children.open').forEach(el => open.push(el.id.replace('kge-kids-', '')));
    try { localStorage.setItem(KGE_PICKER_OPEN_KEY, JSON.stringify(open)); } catch(e) {}
}

function kgePickerLoadOpenState() {
    try { const raw = localStorage.getItem(KGE_PICKER_OPEN_KEY); if (raw) return new Set(JSON.parse(raw)); } catch(e) {}
    return null;
}

function kgeLoadPickerTree() {
    const wrap = document.getElementById('kge-picker-tree-wrap');
    wrap.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">Loading tree…</div>';
    fetch('kg_api.php?action=fetch_tree')
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { wrap.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">Failed to load tree.</div>'; return; }
            kgePickerRaw = res.tree;
            kgePickerChecked = new Set();
            kgeRenderPickerTree();
            kgeUpdatePickerCount();
        })
        .catch(() => { wrap.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">Error loading tree.</div>'; });
}

function kgeRenderPickerTree() {
    const wrap = document.getElementById('kge-picker-tree-wrap');
    const childMap = {};
    kgePickerRaw.forEach(n => { const p = n.parent || '#'; if (!childMap[p]) childMap[p] = []; childMap[p].push(n); });
    wrap.innerHTML = kgeBuildPickerLevel('#', childMap, 0);
    const savedOpen = kgePickerLoadOpenState();
    wrap.querySelectorAll('.kge-tree-children').forEach(el => {
        const jsId = el.id.replace('kge-kids-', '');
        el.classList.toggle('open', savedOpen === null || savedOpen.has(jsId));
    });
    wrap.querySelectorAll('.kge-node-toggle').forEach(el => {
        const row = el.closest('.kge-tree-node');
        const jsId = row ? row.dataset.jid : null;
        const kids = jsId ? document.getElementById('kge-kids-' + jsId) : null;
        el.classList.toggle('open', kids ? kids.classList.contains('open') : false);
    });
}

function kgeBuildPickerLevel(parentId, childMap, depth) {
    const children = childMap[parentId] || [];
    if (!children.length) return '';
    const indent = depth * 14;
    let html = '';
    children.forEach(node => {
        const isFolder = node.type === 'folder', jsId = node.id;
        const checked = kgePickerChecked.has(jsId);
        const hasKids = !!(childMap[jsId] && childMap[jsId].length);
        const icon = isFolder ? '📁' : '📝';
        const toggleBtn = (isFolder && hasKids)
            ? `<span class="kge-node-toggle open" onclick="kgePickerToggleFolder('${jsId}', this)">▶</span>`
            : `<span style="width:18px;display:inline-block;flex-shrink:0;"></span>`;
        html += `
        <div class="kge-tree-node ${isFolder ? 'is-folder' : 'is-node'}" style="padding-left:${10 + indent}px;" data-jid="${jsId}">
            ${toggleBtn}
            <input type="checkbox" ${checked ? 'checked' : ''} onchange="kgePickerCheck('${jsId}', this.checked)">
            <span class="kge-node-icon">${icon}</span>
            <span class="kge-node-label">${escapeHtml(node.text)}</span>
        </div>`;
        if (hasKids) {
            html += `<div class="kge-tree-children open" id="kge-kids-${jsId}">${kgeBuildPickerLevel(jsId, childMap, depth + 1)}</div>`;
        }
    });
    return html;
}

function kgePickerToggleFolder(jsId, btn) {
    const kids = document.getElementById('kge-kids-' + jsId);
    if (!kids) return;
    kids.classList.toggle('open');
    btn.classList.toggle('open');
    kgePickerSaveOpenState();
}

function kgePickerCheck(jsId, checked) {
    const ids = kgePickerDescendants(jsId);
    ids.forEach(id => { if (checked) kgePickerChecked.add(id); else kgePickerChecked.delete(id); });
    ids.forEach(id => {
        const el = document.querySelector(`.kge-tree-node[data-jid="${id}"] input[type=checkbox]`);
        if (el) { el.checked = checked; el.indeterminate = false; }
    });
    kgePickerSyncAncestors(jsId);
    kgeUpdatePickerCount();
}

function kgePickerDescendants(jsId) {
    const result = [jsId], queue = [jsId];
    while (queue.length) {
        const cur = queue.shift();
        kgePickerRaw.filter(n => n.parent === cur).forEach(n => { result.push(n.id); queue.push(n.id); });
    }
    return result;
}

function kgePickerSyncAncestors(jsId) {
    const node = kgePickerRaw.find(n => n.id === jsId);
    if (!node || !node.parent || node.parent === '#') return;
    const parentJid = node.parent;
    const siblings = kgePickerRaw.filter(n => n.parent === parentJid);
    const allChecked = siblings.every(s => kgePickerChecked.has(s.id));
    const noneChecked = siblings.every(s => !kgePickerChecked.has(s.id));
    const el = document.querySelector(`.kge-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);
    if (el) {
        if (allChecked) { el.checked = true; el.indeterminate = false; kgePickerChecked.add(parentJid); }
        else if (noneChecked) { el.checked = false; el.indeterminate = false; kgePickerChecked.delete(parentJid); }
        else { el.checked = false; el.indeterminate = true; kgePickerChecked.delete(parentJid); }
    }
    kgePickerSyncAncestors(parentJid);
}

function kgePickerToggleAll() {
    const allChecked = kgePickerRaw.every(n => kgePickerChecked.has(n.id));
    if (allChecked) kgePickerChecked.clear();
    else kgePickerRaw.forEach(n => kgePickerChecked.add(n.id));
    kgeRenderPickerTree();
    kgeUpdatePickerCount();
}

function kgeUpdatePickerCount() {
    const nodeCount = kgePickerRaw.filter(n => n.type === 'node' && kgePickerChecked.has(n.id)).length;
    const total = kgePickerRaw.filter(n => n.type === 'node').length;
    const el = document.getElementById('kge-picker-count');
    if (el) {
        if (nodeCount === 0) {
            el.textContent = `No pool nodes selected (Auto-Pool: top 10 nodes)`;
            el.style.color = 'var(--text-muted)';
        } else {
            el.textContent = `${nodeCount} pool nodes selected`;
            el.style.color = 'var(--accent)';
        }
    }
    kgeUpdateFooterSummary();
}

function kgeGetPickerNodeIds() {
    return kgePickerRaw.filter(n => n.type === 'node' && kgePickerChecked.has(n.id)).map(n => n.data.db_id);
}

// =====================================================================
// TARGET POT LOGIC
// =====================================================================
let kgePotRaw = [];           
let kgePotNodes = new Map();  
let kgePotFilter = '';
const KGE_POT_OPEN_KEY = 'kg_pot_tree_open';

function kgePotLoadTree() {
    const wrap = document.getElementById('kge-pot-tree-wrap');
    if (kgePotRaw.length) { kgePotRenderTree(); return; } 
    wrap.innerHTML = '<div style="padding: 14px 20px; text-align: center; color: var(--text-muted); font-size:0.8rem;">Loading…</div>';
    fetch('kg_api.php?action=fetch_tree')
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { wrap.innerHTML = '<div style="padding:14px;color:var(--text-muted);">Failed.</div>'; return; }
            kgePotRaw = res.tree;
            kgePotRenderTree();
        });
}

function kgePotFilterTree(val) {
    kgePotFilter = val.trim().toLowerCase();
    kgePotRenderTree();
}

function kgePotGetDescendants(jsId) {
    const result = [];
    const queue = [jsId];
    while(queue.length) {
        const cur = queue.shift();
        kgePotRaw.filter(x => x.parent === cur).forEach(child => {
            result.push(child);
            queue.push(child.id);
        });
    }
    return result;
}

function kgePotCheckFolder(jsId, checked) {
    const descendants = kgePotGetDescendants(jsId);
    descendants.forEach(n => {
        if (n.type === 'node' && n.data && n.data.db_id) {
            if (checked) kgePotAddNode(n.data.db_id, n.text, n.data.node_type);
            else kgePotRemoveNode(n.data.db_id);
        }
    });
    descendants.forEach(n => {
        if(n.type === 'node' && n.data && n.data.db_id) {
            const cb = document.querySelector(`#kge-pot-tree-wrap input[data-potdbid="${n.data.db_id}"]`);
            if (cb) cb.checked = checked;
        } else if (n.type === 'folder') {
            const cb = document.querySelector(`#kge-pot-tree-wrap input[data-potfoldid="${n.id}"]`);
            if (cb) cb.checked = checked;
        }
    });
}

function kgePotRenderTree() {
    const wrap = document.getElementById('kge-pot-tree-wrap');
    if (!kgePotRaw.length) return;
    const childMap = {};
    kgePotRaw.forEach(n => { const p = n.parent || '#'; if (!childMap[p]) childMap[p] = []; childMap[p].push(n); });

    let matchJsIds = null;
    if (kgePotFilter) {
        matchJsIds = new Set();
        kgePotRaw.forEach(n => {
            if (n.type === 'node' && n.text.toLowerCase().includes(kgePotFilter)) {
                matchJsIds.add(n.id);
                let cur = n;
                while (cur.parent && cur.parent !== '#') {
                    matchJsIds.add(cur.parent);
                    cur = kgePotRaw.find(x => x.id === cur.parent) || { parent: '#' };
                }
            }
        });
    }

    function buildLevel(parentId, depth) {
        const children = (childMap[parentId] || []).filter(n => !matchJsIds || matchJsIds.has(n.id));
        if (!children.length) return '';
        let html = '';
        const indent = depth * 14;
        children.forEach(node => {
            const isFolder = node.type === 'folder', jsId = node.id;
            const dbId = node.data && node.data.db_id ? node.data.db_id : null;
            const isInPot = dbId && kgePotNodes.has(dbId);
            const hasKids = !!(childMap[jsId] && childMap[jsId].filter(c => !matchJsIds || matchJsIds.has(c.id)).length);
            const icon = isFolder ? '📁' : '📝';
            const toggleBtn = (isFolder && hasKids)
                ? `<span class="kge-node-toggle" data-potjid="${jsId}" onclick="kgePotToggleFolder(this)" style="cursor:pointer; display:inline-block; width:15px; text-align:center; font-size:0.7rem; color:var(--text-muted);">▶</span>`
                : `<span style="width:15px;display:inline-block;flex-shrink:0;"></span>`;
            
            const cbOrSpace = isFolder
                ? `<input type="checkbox" onchange="kgePotCheckFolder('${jsId}', this.checked)" data-potfoldid="${jsId}" style="accent-color:var(--purple); cursor:pointer; width:15px; height:15px; flex-shrink:0; margin:0 4px 0 0;">`
                : `<input type="checkbox" ${isInPot ? 'checked' : ''} data-potjid="${jsId}" data-potdbid="${dbId}" data-potname="${node.text.replace(/"/g, '&quot;')}" data-pottype="${(node.data && node.data.node_type) || 'note'}" onchange="kgePotCheckNode(this)" style="accent-color:var(--purple); cursor:pointer; width:15px; height:15px; flex-shrink:0; margin:0;">`;
                
            html += `<div class="kge-tree-node ${isFolder ? 'is-folder' : 'is-node'}" style="padding-left:${10+indent}px; ${isInPot ? 'color:var(--purple);' : ''}">
                ${toggleBtn} ${cbOrSpace}
                <span class="kge-node-icon">${icon}</span>
                <span class="kge-node-label">${escapeHtml(node.text)}</span>
            </div>`;
            if (hasKids) {
                html += `<div class="kge-tree-children" id="kge-pot-kids-${jsId}" style="display:none;">${buildLevel(jsId, depth+1)}</div>`;
            }
        });
        return html;
    }

    wrap.innerHTML = buildLevel('#', 0);

    try {
        const raw = localStorage.getItem(KGE_POT_OPEN_KEY);
        if (raw) {
            const open = new Set(JSON.parse(raw));
            wrap.querySelectorAll('.kge-tree-children').forEach(el => {
                const jsId = el.id.replace('kge-pot-kids-', '');
                if (open.has(jsId)) {
                    el.style.display = 'block'; el.classList.add('open');
                    const btn = wrap.querySelector(`.kge-node-toggle[data-potjid="${jsId}"]`);
                    if (btn) { btn.style.transform = 'rotate(90deg)'; btn.classList.add('open'); }
                }
            });
        }
    } catch(e) {}
}

function kgePotToggleFolder(btn) {
    const jsId = btn.dataset.potjid;
    const kids = document.getElementById('kge-pot-kids-' + jsId);
    if (!kids) return;
    const isOpen = kids.classList.contains('open');
    kids.style.display = isOpen ? 'none' : 'block';
    kids.classList.toggle('open');
    btn.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
    btn.classList.toggle('open');
    try {
        const open = new Set();
        document.querySelectorAll('#kge-pot-tree-wrap .kge-tree-children.open').forEach(el => open.add(el.id.replace('kge-pot-kids-', '')));
        localStorage.setItem(KGE_POT_OPEN_KEY, JSON.stringify([...open]));
    } catch(e) {}
}

function kgePotCheckNode(cb) {
    const dbId = parseInt(cb.dataset.potdbid), name = cb.dataset.potname, type = cb.dataset.pottype;
    if (cb.checked) kgePotAddNode(dbId, name, type);
    else kgePotRemoveNode(dbId);
}

function kgePotAddNode(dbId, name, nodeType) {
    if (!dbId || kgePotNodes.has(dbId)) return;
    kgePotNodes.set(dbId, { id: dbId, name, node_type: nodeType });
    const cb = document.querySelector(`#kge-pot-tree-wrap input[data-potdbid="${dbId}"]`);
    if (cb) cb.checked = true;
    kgePotRenderChips();
    kgeUpdateFooterSummary();
}

function kgePotRemoveNode(dbId) {
    kgePotNodes.delete(dbId);
    const cb = document.querySelector(`#kge-pot-tree-wrap input[data-potdbid="${dbId}"]`);
    if (cb) cb.checked = false;
    kgePotRenderChips();
    kgeUpdateFooterSummary();
}

function kgePotClear() {
    kgePotNodes.clear();
    kgePotRenderTree();
    kgePotRenderChips();
    kgeUpdateFooterSummary();
}

function kgePotRenderChips() {
    const el = document.getElementById('kge-pot-chips');
    const info = document.getElementById('kge-pot-info');
    const badge = document.getElementById('kge-pot-count-badge');

    if (!kgePotNodes.size) {
        el.innerHTML = '<span class="kge-pot-empty">Empty — check nodes in the tree below to add them</span>';
        info.textContent = 'No nodes in target pot — AI will use Chroma retrieval instead.';
        badge.style.display = 'none';
    } else {
        badge.style.display = 'inline';
        badge.textContent = kgePotNodes.size;
        info.textContent = `${kgePotNodes.size} node(s) in pot — AI will match focal node against these candidates only.`;
        el.innerHTML = [...kgePotNodes.values()].map(n => `
            <span class="kge-pot-chip">
                <span>${escapeHtml(n.name)}</span>
                <button class="kge-pot-chip-remove" onclick="kgeOpenPotGraph(${n.id})" title="Browse graph from this node" style="opacity:0.8; margin-left:2px;"><i class="bi bi-diagram-2-fill" style="font-size:0.7rem;"></i></button>
                <button class="kge-pot-chip-remove" onclick="kgePotRemoveNode(${n.id})" title="Remove">×</button>
            </span>
        `).join('');
    }
}

function kgeUpdateFooterSummary() {
    const poolCount = kgePickerRaw.filter(n => n.type === 'node' && kgePickerChecked.has(n.id)).length;
    const potCount = kgePotNodes.size;
    const el = document.getElementById('kge-picker-count');
    if (!el) return;
    const parts = [];
    if (poolCount > 0) parts.push(`${poolCount} pool node(s)`);
    if (potCount > 0) parts.push(`${potCount} pot candidate(s)`);
    if (parts.length === 0) {
        el.textContent = 'No selections — Auto-Pool + Chroma retrieval';
        el.style.color = 'var(--text-muted)';
    } else {
        el.textContent = parts.join(' · ');
        el.style.color = potCount > 0 ? 'var(--purple)' : 'var(--accent)';
    }
}

// =====================================================================
// TARGET POT MINI-GRAPH (Sigma inline)
// =====================================================================
let kgePotGraph = null, kgePotRenderer = null, kgePotIsRunning = false, kgePotFa2Id = null;
let kgePotSelectedNode = null, kgePotHoveredNode = null;
let kgePotCurrentSeed = null, kgePotCurrentHops = 1;
let kgePotSearchMatches = null;

function kgeOpenPotGraph(nodeId) {
    document.getElementById('kge-pot-graph-modal-bg').classList.add('open');
    if (nodeId) {
        const n = kgePotNodes.get(parseInt(nodeId, 10));
        const lbl = document.getElementById('kge-pot-graph-seed-label');
        if (lbl) lbl.textContent = n ? n.name : '#' + nodeId;
        kgePotLoadGraph(parseInt(nodeId, 10), kgePotCurrentHops);
    }
}

function kgeClosePotGraph() {
    document.getElementById('kge-pot-graph-modal-bg').classList.remove('open');
    kgePotGraphClosePanel();
}

document.getElementById('kge-pot-graph-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) kgeClosePotGraph();
});

document.getElementById('kge-pot-graph-hops').addEventListener('change', function() {
    kgePotCurrentHops = parseInt(this.value) || 1;
    if (kgePotCurrentSeed) kgePotLoadGraph(kgePotCurrentSeed, kgePotCurrentHops);
});

document.getElementById('kge-pot-graph-search').addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    if (!q) { kgePotSearchMatches = null; if (kgePotRenderer) kgePotRenderer.refresh(); return; }
    if (!kgePotGraph) return; 
    kgePotSearchMatches = new Set();
    kgePotGraph.forEachNode((n, a) => { if (a.label && a.label.toLowerCase().includes(q)) kgePotSearchMatches.add(n); });
    if (kgePotRenderer) kgePotRenderer.refresh();
});

function kgePotLoadGraph(nodeId, hops) {
    kgePotCurrentSeed = nodeId;
    kgePotCurrentHops = hops;
    kgePotGraphClosePanel();
    kgePotSearchMatches = null;

    const lbl = document.getElementById('kge-pot-graph-seed-label');
    if (lbl) {
        const n = kgePotNodes.get(parseInt(nodeId, 10));
        lbl.textContent = n ? n.name : '#' + nodeId;
    }

    const container = document.getElementById('kge-pot-graph-container');
    container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">Loading graph…</div>';

    if (kgePotRenderer) { kgePotRenderer.kill(); kgePotRenderer = null; }
    if (kgePotGraph) kgePotGraph.clear();
    kgePotIsRunning = false;

    fetch('api_kg_edge_queue.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'fetch_mini_graph', node_id: nodeId, hops: hops })
    }).then(r => r.json()).then(res => {
        if (!res.ok) { container.innerHTML = `<div style="color:var(--red);padding:20px;">Error: ${escapeHtml(res.error)}</div>`; return; }
        kgePotBuildGraph(res.nodes, res.edges, container);
    }).catch(e => { container.innerHTML = `<div style="color:var(--red);padding:20px;">${escapeHtml(e.message)}</div>`; });
}

const KGE_TYPE_COLORS = { note:'#64748b', relationship:'#ec4899', character:'#3b82f6', location:'#10b981', event:'#ef4444', concept:'#f59e0b', arc:'#8b5cf6', episode:'#06b6d4' };
function kgePotTypeColor(t) { return KGE_TYPE_COLORS[t] || '#888'; }

function kgePotBuildGraph(nodes, edges, container) {
    container.innerHTML = '';
    kgePotGraph = new graphology.MultiDirectedGraph();
    nodes.forEach(n => {
        kgePotGraph.addNode(String(n.id), { x: Math.random()*100, y: Math.random()*100, size: 4, label: n.name || '', color: kgePotTypeColor(n.node_type || 'note'), node_type: n.node_type || 'note' });
    });
    const validIds = new Set(kgePotGraph.nodes());
    edges.forEach(e => {
        const s = String(e.source), t = String(e.target);
        if (validIds.has(s) && validIds.has(t) && s !== t) {
            try { kgePotGraph.addDirectedEdge(s, t, { label: e.relationship || '', size: 1, color: '#1c2535' }); } catch(err) {}
        }
    });
    kgePotGraph.forEachNode(node => {
        const isFocal = node === String(kgePotCurrentSeed);
        const deg = kgePotGraph.degree(node);
        kgePotGraph.setNodeAttribute(node, 'size', isFocal ? 10 : 3 + Math.sqrt(deg) * 1.6);
    });

    kgePotRenderer = new Sigma(kgePotGraph, container, {
        renderEdgeLabels: kgePotGraph.size < 200,
        defaultEdgeType: 'arrow',
        allowInvalidContainer: true,
        labelColor: { color: '#c8d4e8' },
        edgeLabelColor: { color: '#c8d4e8' },
        edgeLabelSize: 7
    });

    kgePotRenderer.setSetting('nodeReducer', kgePotNodeReducer);
    kgePotRenderer.setSetting('edgeReducer', kgePotEdgeReducer);

    let dragNode = null;
    kgePotRenderer.on('downNode', e => { dragNode = e.node; kgePotRenderer.getCamera().disable(); });
    kgePotRenderer.getMouseCaptor().on('mousemovebody', e => {
        if (!dragNode) return; e.preventSigmaDefault(); e.original.preventDefault();
        const pos = kgePotRenderer.viewportToGraph(e);
        kgePotGraph.setNodeAttribute(dragNode, 'x', pos.x);
        kgePotGraph.setNodeAttribute(dragNode, 'y', pos.y);
    });
    window.addEventListener('mouseup', () => { if (dragNode) { kgePotRenderer.getCamera().enable(); dragNode = null; } });
    kgePotRenderer.on('clickNode', ({node}) => kgePotOpenPanel(node));
    kgePotRenderer.on('clickStage', kgePotGraphClosePanel);
    kgePotRenderer.on('enterNode', ({node}) => { kgePotHoveredNode = node; kgePotRenderer.refresh(); });
    kgePotRenderer.on('leaveNode', () => { kgePotHoveredNode = null; kgePotRenderer.refresh(); });

    kgePotIsRunning = true;
    const settings = { barnesHutOptimize: kgePotGraph.order > 80, strongGravityMode: true, gravity: 0.05, scalingRatio: 8, slowDown: 8 };
    (function step() {
        graphologyLibrary.layoutForceAtlas2.assign(kgePotGraph, { iterations: 1, settings });
        kgePotRenderer.refresh();
        if (kgePotIsRunning) kgePotFa2Id = requestAnimationFrame(step);
    })();
    setTimeout(() => { kgePotIsRunning = false; cancelAnimationFrame(kgePotFa2Id); }, 2200);
}

function kgePotNodeReducer(node, data) {
    const res = { ...data }, muted = '#1c2535', isFocal = node === String(kgePotCurrentSeed);
    if (kgePotSearchMatches !== null && !kgePotSearchMatches.has(node)) { res.color = muted; res.label = ''; res.zIndex = 0; return res; }
    if (isFocal) { res.highlighted = true; res.zIndex = 3; res.size = (data.size||4)*1.4; }
    if (kgePotHoveredNode && kgePotHoveredNode !== node && !kgePotGraph.hasEdge(node, kgePotHoveredNode) && !kgePotGraph.hasEdge(kgePotHoveredNode, node)) { res.color = muted; res.zIndex = 0; }
    else if (kgePotSelectedNode && kgePotSelectedNode !== node && !kgePotGraph.hasEdge(node, kgePotSelectedNode) && !kgePotGraph.hasEdge(kgePotSelectedNode, node)) { res.color = muted; res.zIndex = isFocal ? 3 : 0; }
    if (node === kgePotHoveredNode || node === kgePotSelectedNode) res.zIndex = 2;
    return res;
}
function kgePotEdgeReducer(edge, data) {
    const res = { ...data }, src = kgePotGraph.source(edge), tgt = kgePotGraph.target(edge), muted = '#1c2535';
    if (kgePotSearchMatches !== null && !kgePotSearchMatches.has(src) && !kgePotSearchMatches.has(tgt)) { res.hidden = true; return res; }
    if (kgePotHoveredNode && src !== kgePotHoveredNode && tgt !== kgePotHoveredNode) { res.color = muted; res.hidden = true; }
    else if (kgePotSelectedNode && src !== kgePotSelectedNode && tgt !== kgePotSelectedNode) { res.color = muted; res.hidden = true; }
    return res;
}

function kgePotOpenPanel(nodeId) {
    kgePotSelectedNode = nodeId;
    const attrs = kgePotGraph.getNodeAttributes(nodeId);
    const panel = document.getElementById('kge-pot-graph-node-panel');
    document.getElementById('kge-pot-graph-panel-name').textContent = attrs.label;
    document.getElementById('kge-pot-graph-panel-type').textContent = attrs.node_type || 'note';

    const dbId = parseInt(nodeId, 10);
    const actions = document.getElementById('kge-pot-graph-panel-actions');
    actions.innerHTML = '';

    const isInPot = kgePotNodes.has(dbId);
    const rcBtn = document.createElement('button');
    rcBtn.style.cssText = 'width:100%;padding:5px 8px;background:transparent;border:1px solid var(--border);border-radius:4px;color:var(--text-muted);font-size:0.7rem;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:5px;';
    rcBtn.innerHTML = '<i class="bi bi-crosshair2"></i> Re-center';
    rcBtn.onclick = () => kgePotLoadGraph(dbId, kgePotCurrentHops);
    actions.appendChild(rcBtn);

    const detBtn = document.createElement('button');
    detBtn.style.cssText = 'width:100%;padding:5px 8px;background:transparent;border:1px solid var(--border);border-radius:4px;color:var(--text-muted);font-size:0.7rem;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:5px;';
    detBtn.innerHTML = '<i class="bi bi-file-text"></i> View Details';
    detBtn.onclick = () => kgeOpenNodeDetails(dbId, attrs.label, attrs.node_type);
    actions.appendChild(detBtn);

    const potBtn = document.createElement('button');
    if (isInPot) {
        potBtn.style.cssText = 'width:100%;padding:5px 8px;background:rgba(74,222,128,0.1);border:1px solid var(--green);border-radius:4px;color:var(--green);font-size:0.7rem;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:5px;';
        potBtn.innerHTML = '<i class="bi bi-check-lg"></i> In Pot';
        potBtn.onclick = () => { kgePotRemoveNode(dbId); kgePotOpenPanel(nodeId); };
    } else {
        potBtn.style.cssText = 'width:100%;padding:5px 8px;background:var(--purple-dim);border:1px solid var(--purple);border-radius:4px;color:var(--purple);font-size:0.7rem;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:5px;';
        potBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Add to Pot';
        potBtn.onclick = () => { kgePotAddNode(dbId, attrs.label, attrs.node_type); kgePotOpenPanel(nodeId); };
    }
    actions.appendChild(potBtn);

    panel.style.display = 'flex';
    if (kgePotRenderer) kgePotRenderer.refresh();
}

function kgePotGraphClosePanel() {
    kgePotSelectedNode = null;
    document.getElementById('kge-pot-graph-node-panel').style.display = 'none';
    if (kgePotRenderer) kgePotRenderer.refresh();
}

// =====================================================================
// FOCAL NODE MINI-GRAPH (Sigma inline)
// =====================================================================
let kgeFocalGraph = null, kgeFocalRenderer = null, kgeFocalIsRunning = false, kgeFocalFa2Id = null;
let kgeFocalSelectedNode = null, kgeFocalHoveredNode = null;
let kgeFocalCurrentSeed = null, kgeFocalCurrentHops = 1;
let kgeFocalSearchMatches = null;

function kgeOpenFocalGraph() {
    if (!currentFocalId) return;
    document.getElementById('kge-focal-graph-modal-bg').classList.add('open');
    kgeFocalLoadGraph(parseInt(currentFocalId, 10), kgeFocalCurrentHops, document.getElementById('wkTitle').innerText);
}

function kgeCloseFocalGraph() {
    document.getElementById('kge-focal-graph-modal-bg').classList.remove('open');
    kgeFocalGraphClosePanel();
}

document.getElementById('kge-focal-graph-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) kgeCloseFocalGraph();
});

document.getElementById('kge-focal-graph-hops').addEventListener('change', function() {
    kgeFocalCurrentHops = parseInt(this.value) || 1;
    if (kgeFocalCurrentSeed) kgeFocalLoadGraph(kgeFocalCurrentSeed, kgeFocalCurrentHops);
});

document.getElementById('kge-focal-graph-search').addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    if (!q) { kgeFocalSearchMatches = null; if (kgeFocalRenderer) kgeFocalRenderer.refresh(); return; }
    if (!kgeFocalGraph) return; 
    kgeFocalSearchMatches = new Set();
    kgeFocalGraph.forEachNode((n, a) => { if (a.label && a.label.toLowerCase().includes(q)) kgeFocalSearchMatches.add(n); });
    if (kgeFocalRenderer) kgeFocalRenderer.refresh();
});

function kgeFocalLoadGraph(nodeId, hops, nodeName = null) {
    kgeFocalCurrentSeed = nodeId;
    kgeFocalCurrentHops = hops;
    kgeFocalGraphClosePanel();
    kgeFocalSearchMatches = null;

    const lbl = document.getElementById('kge-focal-graph-seed-label');
    if (lbl) {
        if (nodeName) {
            lbl.textContent = nodeName;
        } else if (nodeId == currentFocalId) {
            lbl.textContent = document.getElementById('wkTitle').innerText;
        } else {
            lbl.textContent = '#' + nodeId;
        }
    }

    const container = document.getElementById('kge-focal-graph-container');
    container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">Loading graph…</div>';

    if (kgeFocalRenderer) { kgeFocalRenderer.kill(); kgeFocalRenderer = null; }
    if (kgeFocalGraph) kgeFocalGraph.clear();
    kgeFocalIsRunning = false;

    fetch('api_kg_edge_queue.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'fetch_mini_graph', node_id: nodeId, hops: hops })
    }).then(r => r.json()).then(res => {
        if (!res.ok) { container.innerHTML = `<div style="color:var(--red);padding:20px;">Error: ${escapeHtml(res.error)}</div>`; return; }
        kgeFocalBuildGraph(res.nodes, res.edges, container);
    }).catch(e => { container.innerHTML = `<div style="color:var(--red);padding:20px;">${escapeHtml(e.message)}</div>`; });
}

function kgeFocalBuildGraph(nodes, edges, container) {
    container.innerHTML = '';
    kgeFocalGraph = new graphology.MultiDirectedGraph();
    nodes.forEach(n => {
        kgeFocalGraph.addNode(String(n.id), { x: Math.random()*100, y: Math.random()*100, size: 4, label: n.name || '', color: kgePotTypeColor(n.node_type || 'note'), node_type: n.node_type || 'note' });
    });
    const validIds = new Set(kgeFocalGraph.nodes());
    edges.forEach(e => {
        const s = String(e.source), t = String(e.target);
        if (validIds.has(s) && validIds.has(t) && s !== t) {
            try { kgeFocalGraph.addDirectedEdge(s, t, { label: e.relationship || '', size: 1, color: '#1c2535' }); } catch(err) {}
        }
    });
    kgeFocalGraph.forEachNode(node => {
        const isFocal = node === String(kgeFocalCurrentSeed);
        const deg = kgeFocalGraph.degree(node);
        kgeFocalGraph.setNodeAttribute(node, 'size', isFocal ? 10 : 3 + Math.sqrt(deg) * 1.6);
    });

    kgeFocalRenderer = new Sigma(kgeFocalGraph, container, {
        renderEdgeLabels: kgeFocalGraph.size < 200,
        defaultEdgeType: 'arrow',
        allowInvalidContainer: true,
        labelColor: { color: '#c8d4e8' },
        edgeLabelColor: { color: '#c8d4e8' },
        edgeLabelSize: 7
    });

    kgeFocalRenderer.setSetting('nodeReducer', kgeFocalNodeReducer);
    kgeFocalRenderer.setSetting('edgeReducer', kgeFocalEdgeReducer);

    let dragNode = null;
    kgeFocalRenderer.on('downNode', e => { dragNode = e.node; kgeFocalRenderer.getCamera().disable(); });
    kgeFocalRenderer.getMouseCaptor().on('mousemovebody', e => {
        if (!dragNode) return; e.preventSigmaDefault(); e.original.preventDefault();
        const pos = kgeFocalRenderer.viewportToGraph(e);
        kgeFocalGraph.setNodeAttribute(dragNode, 'x', pos.x);
        kgeFocalGraph.setNodeAttribute(dragNode, 'y', pos.y);
    });
    window.addEventListener('mouseup', () => { if (dragNode) { kgeFocalRenderer.getCamera().enable(); dragNode = null; } });
    kgeFocalRenderer.on('clickNode', ({node}) => kgeFocalOpenPanel(node));
    kgeFocalRenderer.on('clickStage', kgeFocalGraphClosePanel);
    kgeFocalRenderer.on('enterNode', ({node}) => { kgeFocalHoveredNode = node; kgeFocalRenderer.refresh(); });
    kgeFocalRenderer.on('leaveNode', () => { kgeFocalHoveredNode = null; kgeFocalRenderer.refresh(); });

    kgeFocalIsRunning = true;
    const settings = { barnesHutOptimize: kgeFocalGraph.order > 80, strongGravityMode: true, gravity: 0.05, scalingRatio: 8, slowDown: 8 };
    (function step() {
        graphologyLibrary.layoutForceAtlas2.assign(kgeFocalGraph, { iterations: 1, settings });
        kgeFocalRenderer.refresh();
        if (kgeFocalIsRunning) kgeFocalFa2Id = requestAnimationFrame(step);
    })();
    setTimeout(() => { kgeFocalIsRunning = false; cancelAnimationFrame(kgeFocalFa2Id); }, 2200);
}

function kgeFocalNodeReducer(node, data) {
    const res = { ...data }, muted = '#1c2535', isFocal = node === String(kgeFocalCurrentSeed);
    if (kgeFocalSearchMatches !== null && !kgeFocalSearchMatches.has(node)) { res.color = muted; res.label = ''; res.zIndex = 0; return res; }
    if (isFocal) { res.highlighted = true; res.zIndex = 3; res.size = (data.size||4)*1.4; }
    if (kgeFocalHoveredNode && kgeFocalHoveredNode !== node && !kgeFocalGraph.hasEdge(node, kgeFocalHoveredNode) && !kgeFocalGraph.hasEdge(kgeFocalHoveredNode, node)) { res.color = muted; res.zIndex = 0; }
    else if (kgeFocalSelectedNode && kgeFocalSelectedNode !== node && !kgeFocalGraph.hasEdge(node, kgeFocalSelectedNode) && !kgeFocalGraph.hasEdge(kgeFocalSelectedNode, node)) { res.color = muted; res.zIndex = isFocal ? 3 : 0; }
    if (node === kgeFocalHoveredNode || node === kgeFocalSelectedNode) res.zIndex = 2;
    return res;
}
function kgeFocalEdgeReducer(edge, data) {
    const res = { ...data }, src = kgeFocalGraph.source(edge), tgt = kgeFocalGraph.target(edge), muted = '#1c2535';
    if (kgeFocalSearchMatches !== null && !kgeFocalSearchMatches.has(src) && !kgeFocalSearchMatches.has(tgt)) { res.hidden = true; return res; }
    if (kgeFocalHoveredNode && src !== kgeFocalHoveredNode && tgt !== kgeFocalHoveredNode) { res.color = muted; res.hidden = true; }
    else if (kgeFocalSelectedNode && src !== kgeFocalSelectedNode && tgt !== kgeFocalSelectedNode) { res.color = muted; res.hidden = true; }
    return res;
}

function kgeFocalOpenPanel(nodeId) {
    kgeFocalSelectedNode = nodeId;
    const attrs = kgeFocalGraph.getNodeAttributes(nodeId);
    const panel = document.getElementById('kge-focal-graph-node-panel');
    document.getElementById('kge-focal-graph-panel-name').textContent = attrs.label;
    document.getElementById('kge-focal-graph-panel-type').textContent = attrs.node_type || 'note';

    const dbId = parseInt(nodeId, 10);
    const actions = document.getElementById('kge-focal-graph-panel-actions');
    actions.innerHTML = '';

    const rcBtn = document.createElement('button');
    rcBtn.style.cssText = 'width:100%;padding:5px 8px;background:transparent;border:1px solid var(--border);border-radius:4px;color:var(--text-muted);font-size:0.7rem;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:5px;';
    rcBtn.innerHTML = '<i class="bi bi-crosshair2"></i> Re-center';
    rcBtn.onclick = () => kgeFocalLoadGraph(dbId, kgeFocalCurrentHops, attrs.label);
    actions.appendChild(rcBtn);

    const detBtn = document.createElement('button');
    detBtn.style.cssText = 'width:100%;padding:5px 8px;background:transparent;border:1px solid var(--border);border-radius:4px;color:var(--text-muted);font-size:0.7rem;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:5px;';
    detBtn.innerHTML = '<i class="bi bi-file-text"></i> View Details';
    detBtn.onclick = () => kgeOpenNodeDetails(dbId, attrs.label, attrs.node_type);
    actions.appendChild(detBtn);

    panel.style.display = 'flex';
    if (kgeFocalRenderer) kgeFocalRenderer.refresh();
}

function kgeFocalGraphClosePanel() {
    kgeFocalSelectedNode = null;
    document.getElementById('kge-focal-graph-node-panel').style.display = 'none';
    if (kgeFocalRenderer) kgeFocalRenderer.refresh();
}

// =====================================================================
// QUEUE & EXECUTION TERMINAL
// =====================================================================
function kgeSwitchTermTab(tab) {
    document.getElementById('tab-log').classList.toggle('active', tab === 'log');
    document.getElementById('tab-queue').classList.toggle('active', tab === 'queue');
    document.getElementById('kge-term-log').classList.toggle('active', tab === 'log');
    document.getElementById('kge-term-queue').classList.toggle('active', tab === 'queue');
}

function kgeToggleTerminal() {
    const el = document.getElementById('kgeTerminal');
    if (terminalState === 'minimized') {
        terminalState = 'normal';
        el.className = 'kge-terminal-panel normal';
        kgeFetchQueue();
    } else if (terminalState === 'normal') {
        terminalState = 'fullscreen';
        el.className = 'kge-terminal-panel fullscreen';
    } else {
        terminalState = 'minimized';
        el.className = 'kge-terminal-panel minimized';
    }
}

function kgeToggleQueuePlay() {
    isQueuePlaying = !isQueuePlaying;
    const btn = document.getElementById('kge-play-btn');
    if (isQueuePlaying) {
        btn.className = 'btn btn-sm btn-warn';
        btn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
        kgeAppendLog('[System] Queue processing started.', 'info');
        kgeProcessNextInQueue();
    } else {
        btn.className = 'btn btn-sm btn-success';
        btn.innerHTML = '<i class="bi bi-play-fill"></i> Play';
        kgeAppendLog('[System] Queue paused. Current running job will finish its step.', 'info');
    }
}

function kgeAppendLog(msg, type = 'info', ts = null) {
    const area = document.getElementById('kgeLogArea');
    const d = document.createElement('div');
    d.className = `kge-term-log-line kge-term-log-${type}`;
    const time = ts ? ts : new Date().toLocaleTimeString();
    d.innerHTML = `<span class="kge-term-log-ts">[${time}]</span> ${escapeHtml(msg)}`;
    area.appendChild(d);
    area.scrollTop = area.scrollHeight;
}

function kgeSyncLogs(runId, logs) {
    if (!logs || !logs.length) return;
    logs.forEach(l => {
        const key = runId + '_' + l.ts + '_' + l.step;
        if (!kgeLogSeen.has(key)) {
            kgeLogSeen.add(key);
            kgeAppendLog(`[Job ${runId.slice(0,6)}] ${l.message}`, l.step.includes('error') ? 'err' : 'info', l.ts.split(' ')[1] || null);
        }
    });
}

async function kgeFetchQueue() {
    try {
        const res = await fetch('api_kg_edge_queue.php?action=fetch_queue&_t=' + Date.now()).then(r => r.json());
        if (res.ok) {
            kgeQueueData = res.queue || [];
            if (res.offline !== undefined) {
                isOfflineMode = res.offline;
                document.getElementById('kge-offline-toggle').checked = isOfflineMode;
            }
            kgeRenderQueue();
        }
    } catch(e) {}
}

function kgeRenderQueue() {
    const list = document.getElementById('kge-queue-list');
    const badge = document.getElementById('queue-badge');
    
    badge.innerText = kgeQueueData.filter(q => q.status === 'queued' || q.status === 'awaiting_offline').length;
    
    if (!kgeQueueData.length) {
        list.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">Queue is empty.</div>';
        return;
    }

    let html = '';
    kgeQueueData.forEach(q => {
        const isErr = q.status === 'error';
        const isOffline = q.status === 'awaiting_offline';
        html += `
            <div class="kge-q-item status-${q.status}">
                <div class="kge-q-item-info">
                    <strong>${escapeHtml(q.focal_node_name)}</strong> 
                    <span style="color:var(--text-muted); font-size:0.8rem;"> — ${q.status.toUpperCase()} (${escapeHtml(q.step_label)})</span>
                    ${isErr ? `<div style="color:var(--red); font-size:0.75rem; margin-top:2px;">${escapeHtml(q.ai_error || 'Error')}</div>` : ''}
                </div>
                <div class="kge-q-actions">
                    <button class="btn btn-secondary btn-sm" onclick="kgeShowJobDetails('${q.run_uuid}')" title="View Details"><i class="bi bi-info-circle"></i></button>
                    <button class="btn btn-secondary btn-sm" onclick="kgeExportQueueItem('${q.run_uuid}')" title="Export JSON Context"><i class="bi bi-download"></i></button>
                    ${isOffline || isErr ? `<button class="btn btn-secondary btn-sm" style="color:var(--purple);" onclick="kgeExportOfflineJob('${q.run_uuid}')" title="Export Offline AI Request"><i class="bi bi-file-arrow-down"></i></button>` : ''}
                    ${isOffline || isErr ? `<button class="btn btn-secondary btn-sm" style="color:var(--green);" onclick="kgeOpenIngestModal('${q.run_uuid}')" title="Ingest AI Answer"><i class="bi bi-upload"></i></button>` : ''}
                    ${isErr || isOffline ? `<button class="btn btn-secondary btn-sm" onclick="kgeQueueAction('reset', '${q.run_uuid}')" title="Reset / Retry"><i class="bi bi-arrow-clockwise"></i></button>` : ''}
                    <button class="btn btn-secondary btn-sm" style="color:var(--red);" onclick="kgeQueueAction('delete', '${q.run_uuid}')" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        `;
    });
    list.innerHTML = html;
}

function kgeQueueAction(cmd, runId) {
    fetch('api_kg_edge_queue.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'queue_action', cmd, run_id: runId })
    }).then(() => kgeFetchQueue());
}

function kgeShowJobDetails(runId) {
    const job = kgeQueueData.find(q => q.run_uuid === runId);
    if (!job) return;
    
    let html = `
        <div style="margin-bottom:10px;"><strong>Focal Node:</strong> ${escapeHtml(job.focal_node_name)} (ID: ${job.focal_node_id})</div>
        <div style="margin-bottom:10px;"><strong>Status:</strong> ${job.status} — ${job.step_label}</div>
        <div style="margin-bottom:10px;"><strong>Created:</strong> ${job.created_at}</div>
    `;
    if (job.message) {
        try {
            const msg = JSON.parse(job.message);
            if (msg.pot_node_ids) html += `<div style="margin-bottom:10px;"><strong>Target Pot:</strong> ${msg.pot_node_ids.length} nodes</div>`;
        } catch(e) {}
    }
    if (job.ai_error) {
        html += `<div style="color:var(--red); padding:10px; background:rgba(255,0,0,0.1); border-radius:4px; margin-top:10px;"><strong>Error:</strong><br>${escapeHtml(job.ai_error)}</div>`;
    }
    
    document.getElementById('kge-job-details-body').innerHTML = html;
    document.getElementById('kge-job-modal-bg').classList.add('open');
}

async function kgeProcessNextInQueue() {
    if (!isQueuePlaying) return;
    if (currentRunningId) return;

    let nextJob = kgeQueueData.find(q => q.status === 'queued' || q.status === 'running');
    if (!nextJob) {
        await kgeFetchQueue();
        nextJob = kgeQueueData.find(q => q.status === 'queued' || q.status === 'running');
        if (!nextJob && isQueuePlaying) {
            kgeToggleQueuePlay();
            kgeAppendLog('[System] Queue empty or all done. Auto-paused.', 'info');
            loadPending();
        }
        return;
    }

    currentRunningId = nextJob.run_uuid;
    if (nextJob.status === 'queued') {
        kgeAppendLog(`[System] Starting job ${nextJob.id} for ${nextJob.focal_node_name}...`, 'info');
        nextJob.status = 'running';
        kgeRenderQueue(); // Optimistic UI update
    }

    try {
        const stepData = await fetchJsonSafe('api_kg_edge_queue.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'advance_batch', run_id: currentRunningId })
        });

        if (stepData.state) {
            kgeSyncLogs(stepData.state.run_id, stepData.state.logs);
            // Ignore rendering standard mini-status block since we removed it from DOM mostly, or if it exists update it:
            if(document.getElementById('statusMonitor')) renderStatus(stepData.state); 
        }

        const state = stepData.state || {};
        if (state.status === 'completed') kgeAppendLog(`[System] Job ${state.focal_node?.name || state.run_id} finished successfully.`, 'success');
        if (state.status === 'error') kgeAppendLog(`[System] Job ${state.focal_node?.name || state.run_id} encountered an error.`, 'err');
        if (state.status === 'awaiting_offline') kgeAppendLog(`[System] Job ${state.focal_node?.name || state.run_id} is parked waiting for offline ingestion.`, 'info');

    } catch(err) {
        kgeAppendLog(`[System] Execution error: ${err.message}`, 'err');
    }

    await kgeFetchQueue();
    currentRunningId = null; // Clear so the next tick can proceed

    setTimeout(() => {
        kgeProcessNextInQueue();
    }, 500);
}

async function kgeEnqueueAndStart() {
    const nodeIds = kgeGetPickerNodeIds();
    const potNodeIds = [...kgePotNodes.values()].map(n => n.id);

    closeBatchModal();
    showToast("Adding to queue...");

    try {
        const res = await fetchJsonSafe('api_kg_edge_queue.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'enqueue_batch', node_ids: nodeIds, pot_node_ids: potNodeIds })
        });

        if (res.ok) {
            if (res.enqueued === 0) {
                showToast("No eligible nodes were enqueued.", true);
                kgeAppendLog("[System] Notice: No eligible nodes were enqueued.", "err");
                return;
            }

            showToast(`Enqueued ${res.enqueued} job(s).`);
            kgeAppendLog(`[System] Enqueued ${res.enqueued} new task(s).`, 'info');
            kgePotClear(); 
            
            if (terminalState === 'minimized') kgeToggleTerminal();
            kgeSwitchTermTab('queue');
            
            // Wait for queue data to be retrieved so Play loop finds it immediately
            await kgeFetchQueue();
            
            if (!isQueuePlaying) kgeToggleQueuePlay();
        }

    } catch(err) {
        showError(err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadPending();
    kgeFetchQueue();
    // Poll queue quietly every 8s if idle
    setInterval(() => { if (!currentRunningId) kgeFetchQueue(); }, 8000);
});
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
