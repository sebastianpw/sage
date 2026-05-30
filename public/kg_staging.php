<?php
// public/kg_staging.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Knowledge Graph – Staging";

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

<!-- jsTree -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<!-- Toast UI Editor -->
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css"/>
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" id="tui-dark-theme" disabled/>
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>

<style>
/* ── Variables ── */
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
    --sidebar-w:320px;
    --staging-accent:#7c3aed;
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
    --card-elevation:0 6px 18px rgba(2,6,23,.4);
    --staging-accent:#a78bfa;
}
@media (prefers-color-scheme:dark) {
    :root:not([data-theme="light"]) {
        --bg:#0d1117; --card:#161b22; --border:#30363d;
        --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
        --staging-accent:#a78bfa;
    }
}

* { box-sizing: border-box; }
body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100vh; overflow:hidden; }

/* ── Layout ── */
.kg-layout {
    display: flex;
    height: 100vh;
    flex-direction: column;
}

/* ── Top bar ── */
.kg-topbar {
    height: 52px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 10px;
    flex-shrink: 0;
    position: relative;
    z-index: 10;
}
.kg-topbar-left {
    margin-left: 64px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.kg-topbar h2 { margin:0; font-size:1rem; }
.kg-topbar-right { margin-left:auto; display:flex; gap:6px; align-items:center; }

/* ── Staging badge in topbar ── */
.kg-staging-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 10px;
    background: rgba(124,58,237,0.12);
    color: var(--staging-accent);
    border: 1px solid rgba(124,58,237,0.3);
    flex-shrink: 0;
}

/* ── Hamburger ── */
.kg-hamburger {
    position: fixed;
    top: 10px;
    left: 70px;
    z-index: 1100;
    width: 38px; height: 38px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 5px; cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,.12);
    transition: background 0.2s;
}
.kg-hamburger:hover { background: var(--bg); }
.kg-hamburger span {
    display: block; width: 20px; height: 2px;
    background: var(--text); border-radius: 2px;
    transition: all 0.25s;
}
.kg-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.kg-hamburger.open span:nth-child(2) { opacity: 0; }
.kg-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Flyout overlay ── */
.kg-flyout-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1050;
    display: none;
    pointer-events: none;
}
.kg-flyout-overlay.open { display: block; pointer-events: auto; }

/* ── Sidebar flyout ── */
.kg-sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: min(320px, 88vw);
    background: var(--card);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 1060;
    transform: translateX(-110%);
    transition: transform 0.27s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 4px 0 20px rgba(0,0,0,.18);
}
.kg-sidebar.open { transform: translateX(0); }

.kg-sidebar-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    min-height: 52px;
}
.kg-sidebar-header h2 { margin:0; font-size:1rem; flex:1; }
.kg-sidebar-actions {
    padding: 8px 10px;
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    flex-shrink: 0;
    align-items: center;
}
.kg-tree-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

/* ── DnD toggle ── */
.kg-dnd-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: var(--text-muted);
    cursor: pointer;
    user-select: none;
    padding: 4px 6px;
    border-radius: 5px;
    border: 1px solid var(--border);
    background: transparent;
    margin-left: auto;
    white-space: nowrap;
    transition: border-color 0.15s, color 0.15s;
}
.kg-dnd-toggle:hover { border-color: var(--accent); color: var(--accent); }
.kg-dnd-toggle input[type="checkbox"] {
    accent-color: var(--accent);
    cursor: pointer;
    margin: 0;
    width: 13px;
    height: 13px;
}

/* ── Main content ── */
.kg-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
    min-height: 0;
}
.kg-main-header {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    background: var(--card);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    min-height: 52px;
}
.kg-main-header h3 { margin:0; font-size:1rem; flex:1; }

.kg-empty {
    flex:1; display:flex; align-items:center; justify-content:center;
    color:var(--text-muted); font-size:1.1rem;
    flex-direction: column; gap: 10px;
}
.kg-empty .hint { font-size:0.85rem; opacity:0.7; }

/* ── Editor area ── */
.kg-editor-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}
.kg-meta-bar {
    padding: 8px 14px;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    flex-shrink: 0;
}
.kg-meta-bar input, .kg-meta-bar select {
    background: var(--card);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 0.85rem;
}
.kg-meta-bar input[type="text"] { flex:1; min-width:120px; }

#kg-editor {
    flex: 1;
    min-height: 0;
}

/* ── FIXED Bottom Drawer / Flyout ── */
.kg-bottom-drawer {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    z-index: 1000;
    background: var(--card);
    border-top: 1px solid var(--border);
    box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
    height: 44px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(100%);
    transition: transform 0.35s cubic-bezier(0.2, 0.8, 0.2, 1), height 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
}
:root[data-theme="dark"] .kg-bottom-drawer {
    box-shadow: 0 -4px 25px rgba(0,0,0,0.7);
}
.kg-bottom-drawer.visible {
    transform: translateY(0);
}
.kg-bottom-drawer.open {
    height: min(400px, 60vh);
}

/* ── Drawer Handle ── */
.kg-drawer-handle {
    height: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    position: relative;
    flex-shrink: 0;
    transition: background 0.15s;
}
.kg-drawer-handle:hover {
    background: rgba(59,130,246,0.06);
}
.kg-drawer-title {
    position: absolute;
    left: 16px;
    top: 0; bottom: 0;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}
.kg-drawer-hamburger {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 4px;
    width: 44px;
    height: 44px;
}
.kg-drawer-hamburger span {
    display: block;
    width: 22px;
    height: 2px;
    background: var(--text-muted);
    border-radius: 2px;
    transition: transform 0.25s ease, opacity 0.25s ease;
}
.kg-bottom-drawer.open .kg-drawer-hamburger span:nth-child(1) {
    transform: translateY(6px) rotate(45deg);
}
.kg-bottom-drawer.open .kg-drawer-hamburger span:nth-child(2) {
    opacity: 0;
}
.kg-bottom-drawer.open .kg-drawer-hamburger span:nth-child(3) {
    transform: translateY(-6px) rotate(-45deg);
}

.kg-drawer-content {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    background: var(--card);
}

/* ── Linked item rows ── */
.kg-item-row {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 0.88rem;
    gap: 10px;
    transition: background 0.15s;
}
.kg-item-row:hover { background: rgba(59,130,246,0.04); }
.kg-item-row:last-child { border-bottom: none; }

.kg-item-type-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    white-space: nowrap;
    flex-shrink: 0;
    min-width: 80px;
    justify-content: center;
}
.kg-pill-kg_node    { background: rgba(139,92,246,0.12); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.25); }
.kg-pill-character  { background: rgba(59,130,246,0.12); color: var(--accent); border: 1px solid rgba(59,130,246,0.25); }
.kg-pill-location   { background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.25); }
.kg-pill-md_doc     { background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.25); }
.kg-pill-episode    { background: rgba(239,68,68,0.12);  color: #ef4444; border: 1px solid rgba(239,68,68,0.25); }
.kg-pill-anima      { background: rgba(6,182,212,0.12);  color: #06b6d4; border: 1px solid rgba(6,182,212,0.25); }
.kg-pill-board      { background: rgba(236,72,153,0.12); color: #ec4899; border: 1px solid rgba(236,72,153,0.25); }
.kg-pill-other      { background: rgba(100,116,139,0.12);color:var(--text-muted); border:1px solid var(--border); }

/* Deep-link pill */
.kg-pill-md_doc_deep {
    background: rgba(245,158,11,0.18);
    color: #d97706;
    border: 1px solid rgba(245,158,11,0.45);
}

/* Incoming direction pill */
.kg-pill-incoming {
    background: rgba(16,185,129,0.08);
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.2);
    font-size: 0.68rem;
    padding: 1px 5px;
    border-radius: 8px;
    flex-shrink: 0;
}

.kg-item-label-btn {
    flex: 1;
    background: none;
    border: none;
    color: var(--accent);
    cursor: pointer;
    text-align: left;
    font-size: 0.88rem;
    padding: 0;
    font-weight: 600;
    text-decoration: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: color 0.15s;
}
.kg-item-label-btn:hover { color: var(--text); text-decoration: underline; }
.kg-item-label-btn.no-link { color: var(--text); cursor: default; font-weight: normal; }
.kg-item-label-btn.no-link:hover { text-decoration: none; }

.kg-item-rel {
    font-size: 0.75rem;
    color: var(--text-muted);
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 2px 8px;
    white-space: nowrap;
    flex-shrink: 0;
}

.kg-item-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.kg-item-ext-btn, .kg-item-remove, .kg-item-edit {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 0.9rem;
    line-height: 1;
    transition: color 0.15s, background 0.15s;
}
.kg-item-ext-btn:hover  { color: var(--accent); background: rgba(59,130,246,0.1); }
.kg-item-edit:hover     { color: var(--orange);  background: rgba(245,158,11,0.1); }
.kg-item-remove:hover   { color: var(--red);     background: rgba(218,54,51,0.1); }

/* ── Buttons ── */
.btn {
    padding: 6px 11px; border-radius:6px; border:none; cursor:pointer;
    font-weight:600; font-size:0.85rem; display:inline-flex; align-items:center; gap:5px;
    text-decoration:none; white-space:nowrap;
}
.btn-primary { background:var(--accent); color:#fff; }
.btn-green { background:var(--green); color:#fff; }
.btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
.btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
.btn-danger { background:transparent; border:1px solid var(--border); color:var(--red); }
.btn-staging { background: var(--staging-accent); color:#fff; }
.btn-staging:hover { opacity: 0.88; }
.btn-sm { padding:4px 8px; font-size:0.78rem; }
.btn:disabled { opacity:0.5; cursor:not-allowed; }

/* ── Tree overrides ── */
.jstree-default .jstree-hovered { background:rgba(59,130,246,.1)!important; color:var(--text)!important; }
.jstree-default .jstree-clicked { background:rgba(59,130,246,.2)!important; color:var(--accent)!important; }
.jstree-default .jstree-anchor { line-height:28px; height:28px; font-size:0.92rem; }
.jstree-default .jstree-icon { width:28px; height:28px; line-height:28px; }

/* ── Modals ── */
.kg-modal-bg {
    position:fixed; inset:0; background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center; z-index:9999;
}
.kg-modal {
    background:var(--card); border:1px solid var(--border); border-radius:10px;
    padding:20px; width:360px; max-width:94vw;
    box-shadow:0 10px 30px rgba(0,0,0,.3);
}
.kg-modal h3 { margin:0 0 14px 0; font-size:1rem; }
.kg-modal input, .kg-modal select, .kg-modal textarea {
    width:100%; padding:8px 10px; border-radius:6px;
    border:1px solid var(--border); background:var(--bg);
    color:var(--text); font-size:0.9rem; margin-bottom:10px;
}
.kg-modal textarea { resize:vertical; min-height:70px; }
.kg-modal .actions { display:flex; gap:8px; justify-content:flex-end; }

/* ── Toast ── */
#kg-toast {
    position:fixed; bottom:24px; right:24px; z-index:99999;
    background:var(--card); color:var(--text); border:1px solid var(--border);
    border-left:4px solid var(--green); border-radius:6px;
    padding:12px 18px; font-size:0.9rem;
    display:none; box-shadow:0 4px 12px rgba(0,0,0,.2);
}

/* ── Node type badge ── */
.node-type-badge {
    font-size:0.72rem; padding:2px 7px; border-radius:10px;
    background:rgba(59,130,246,.12); color:var(--accent);
    border:1px solid rgba(59,130,246,.25); font-weight:600;
}

/* ── Curated-docs inline viewer modal ── */
.kg-curated-modal-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
    z-index: 9998;
}
.kg-curated-modal {
    width: 95%; max-width: 1200px;
    height: 90vh;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    position: relative;
}
.kg-curated-modal iframe {
    flex: 1;
    border: none;
    width: 100%;
    background: var(--bg);
}
.kg-curated-modal-close {
    position: absolute;
    top: 10px; right: 12px;
    z-index: 2;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 34px; height: 34px;
    font-size: 1.4rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-muted);
    line-height: 1;
}
.kg-curated-modal-close:hover { color: var(--text); background: var(--bg); }

#kg-hamburger { opacity: 0.5; }
#kg-export-btn { opacity: 0.5; }
#kg-import-btn { opacity: 0.5; }

#kg-editor { max-height: 55vh; }

/* ── Spinner for TTS ── */
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* ══════════════════════════════════════════════
   EXPORT MODAL STYLES
   ══════════════════════════════════════════════ */
.kge-modal-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(3px);
    display: none; align-items: center; justify-content: center;
    z-index: 9990;
}
.kge-modal-bg.open { display: flex; }

.kge-modal {
    width: min(780px, 96vw);
    max-height: 90vh;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,0.45);
}

.kge-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
    flex-shrink: 0;
}
.kge-header h3 {
    margin: 0; font-size: 1rem; flex: 1;
    display: flex; align-items: center; gap: 8px;
}
.kge-close {
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); font-size: 1.3rem; line-height: 1;
    padding: 2px 6px; border-radius: 4px;
    transition: color 0.15s, background 0.15s;
}
.kge-close:hover { color: var(--text); background: var(--bg); }

/* Tabs */
.kge-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
    padding: 0 20px;
    flex-shrink: 0;
    gap: 0;
}
.kge-tab {
    padding: 10px 16px;
    font-size: 0.85rem; font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
    background: none;
    border-top: none; border-left: none; border-right: none;
    white-space: nowrap;
}
.kge-tab:hover { color: var(--text); }
.kge-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.kge-tab.tab-promote.active { color: var(--staging-accent); border-bottom-color: var(--staging-accent); }

/* Panes */
.kge-panes { flex: 1; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
.kge-pane  { display: none; flex: 1; flex-direction: column; overflow: hidden; min-height: 0; }
.kge-pane.active { display: flex; }

/* ── Full export pane — new picker layout ── */
.kge-full-body {
    padding: 16px 20px;
    display: flex; flex-direction: column; gap: 12px;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}
.kge-option-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.88rem;
}
.kge-option-row label { flex: 1; cursor: pointer; }
.kge-option-row input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); flex-shrink: 0; }
.kge-desc {
    font-size: 0.78rem; color: var(--text-muted); line-height: 1.5;
    padding: 0 2px;
}
.kge-full-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    display: flex; gap: 8px; justify-content: flex-end; align-items: center;
    flex-shrink: 0;
}
.kge-full-footer-left {
    flex: 1;
    font-size: 0.82rem;
    color: var(--text-muted);
}

/* ── Picker tree ── */
.kge-picker-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px 6px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.kge-picker-header span {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    flex: 1;
}
.kge-picker-select-all {
    background: none; border: none; color: var(--accent);
    font-size: 0.75rem; cursor: pointer; padding: 0;
    font-weight: 600;
}
.kge-picker-select-all:hover { text-decoration: underline; }

.kge-picker-tree-wrap {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding: 4px 0;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
}
.kge-picker-loading {
    padding: 20px;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Tree rows */
.kge-tree-node {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    cursor: pointer;
    user-select: none;
    transition: background 0.1s;
    font-size: 0.86rem;
    border-radius: 4px;
    margin: 1px 4px;
}
.kge-tree-node:hover { background: rgba(59,130,246,0.07); }
.kge-tree-node input[type=checkbox] {
    width: 14px; height: 14px;
    accent-color: var(--accent);
    cursor: pointer;
    flex-shrink: 0;
    margin: 0;
}
.kge-tree-node .kge-node-toggle {
    width: 16px; height: 16px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 0.65rem;
    color: var(--text-muted);
    cursor: pointer;
    border-radius: 3px;
    transition: background 0.1s, transform 0.15s;
}
.kge-tree-node .kge-node-toggle:hover { background: rgba(59,130,246,0.12); }
.kge-tree-node .kge-node-toggle.open { transform: rotate(90deg); }
.kge-tree-node .kge-node-icon { font-size: 0.85rem; flex-shrink: 0; opacity: 0.75; }
.kge-tree-node .kge-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kge-tree-node.is-folder > .kge-node-label { font-weight: 600; color: var(--text); }
.kge-tree-node.is-node > .kge-node-label { color: var(--text-muted); }
.kge-tree-children { display: none; }
.kge-tree-children.open { display: block; }

/* checkbox indeterminate visual (3-state) */
.kge-tree-node input[type=checkbox]:indeterminate { opacity: 0.7; }

/* ── Options strip (lore + edges checkboxes) ── */
.kge-options-strip {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}
.kge-options-strip .kge-option-row {
    flex: 1;
    min-width: 160px;
    padding: 9px 12px;
}

/* Semantic pane */
.kge-sem-top {
    padding: 16px 20px 12px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    display: flex; flex-direction: column; gap: 10px;
}
.kge-query-row { display: flex; gap: 8px; }
.kge-query-input {
    flex: 1;
    padding: 9px 12px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.9rem;
    transition: border-color 0.15s;
}
.kge-query-input:focus { outline: none; border-color: var(--accent); }
.kge-query-input::placeholder { color: var(--text-muted); opacity: 0.7; }
.kge-n-select {
    padding: 9px 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.85rem;
    min-width: 80px;
}

.kge-hits-area {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}
.kge-hits-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    height: 100%; gap: 8px;
    color: var(--text-muted); font-size: 0.9rem;
    padding: 40px 20px; text-align: center;
}
.kge-hits-empty .hint { font-size: 0.78rem; opacity: 0.65; max-width: 320px; line-height: 1.5; }

.kge-hit-row {
    display: flex; align-items: flex-start;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    gap: 10px;
    transition: background 0.12s;
    cursor: pointer;
}
.kge-hit-row:hover { background: rgba(59,130,246,0.04); }
.kge-hit-row.selected { background: rgba(59,130,246,0.08); }
.kge-hit-check {
    width: 16px; height: 16px; flex-shrink: 0;
    margin-top: 3px; cursor: pointer; accent-color: var(--accent);
}
.kge-hit-body { flex: 1; min-width: 0; }
.kge-hit-name {
    font-weight: 600; font-size: 0.88rem;
    display: flex; align-items: center; gap: 6px;
    flex-wrap: wrap;
}
.kge-hit-excerpt {
    font-size: 0.78rem; color: var(--text-muted);
    margin-top: 3px; line-height: 1.45;
    overflow: hidden; display: -webkit-box;
    -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.kge-hit-score {
    font-size: 0.72rem; color: var(--text-muted);
    font-family: ui-monospace, monospace;
    flex-shrink: 0; padding-top: 3px;
}
.kge-score-bar {
    display: inline-block;
    height: 3px; border-radius: 2px;
    background: var(--accent); opacity: 0.5;
    vertical-align: middle; margin-left: 4px;
    flex-shrink: 0;
}

/* Type pills for export modal */
.kge-type-pill {
    font-size: 0.68rem; font-weight: 700;
    padding: 1px 6px; border-radius: 8px;
    white-space: nowrap;
}
.kge-pill-character    { background:rgba(59,130,246,.12);  color:var(--accent); border:1px solid rgba(59,130,246,.25); }
.kge-pill-location     { background:rgba(16,185,129,.12);  color:#10b981;       border:1px solid rgba(16,185,129,.25); }
.kge-pill-concept      { background:rgba(245,158,11,.12);  color:#f59e0b;       border:1px solid rgba(245,158,11,.25); }
.kge-pill-event        { background:rgba(239,68,68,.12);   color:#ef4444;       border:1px solid rgba(239,68,68,.25); }
.kge-pill-arc          { background:rgba(139,92,246,.12);  color:#8b5cf6;       border:1px solid rgba(139,92,246,.25); }
.kge-pill-episode      { background:rgba(6,182,212,.12);   color:#06b6d4;       border:1px solid rgba(6,182,212,.25); }
.kge-pill-note         { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }
.kge-pill-relationship { background:rgba(236,72,153,.12);  color:#ec4899;       border:1px solid rgba(236,72,153,.25); }

.kge-status-dot {
    display: inline-block; width: 7px; height: 7px;
    border-radius: 50%; flex-shrink: 0; margin-top: 5px;
}
.kge-dot-filled  { background: var(--green); }
.kge-dot-partial { background: var(--orange); }
.kge-dot-stub    { background: #6b7280; }
.kge-dot-empty   { background: var(--border); }

.kge-sem-footer {
    padding: 12px 20px;
    border-top: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
    flex-shrink: 0; flex-wrap: wrap;
}
.kge-sel-count { font-size: 0.82rem; color: var(--text-muted); flex: 1; }
.kge-content-toggle {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.82rem; color: var(--text-muted); cursor: pointer;
}
.kge-content-toggle input { accent-color: var(--accent); cursor: pointer; }
.kge-loading-bar {
    height: 2px; background: var(--accent);
    position: absolute; top: 0; left: 0;
    animation: kge-load 1.4s ease-in-out infinite;
    display: none;
}
@keyframes kge-load {
    0%   { width: 0;   left: 0; }
    50%  { width: 60%; left: 20%; }
    100% { width: 0;   left: 100%; }
}
.kge-hits-header {
    padding: 8px 14px 6px;
    font-size: 0.75rem; font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
    display: flex; align-items: center; gap: 8px;
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; background: var(--card); z-index: 1;
}
.kge-select-all-btn {
    background: none; border: none; color: var(--accent);
    font-size: 0.75rem; cursor: pointer; padding: 0;
    font-weight: 600; margin-left: auto;
}
.kge-select-all-btn:hover { text-decoration: underline; }

/* ── Promote pane specific ── */
.kge-promote-body {
    padding: 16px 20px;
    display: flex; flex-direction: column; gap: 14px;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}
.kge-promote-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    background: rgba(124,58,237,0.07);
    border: 1px solid rgba(124,58,237,0.25);
    border-radius: 8px;
    font-size: 0.83rem;
    line-height: 1.55;
    color: var(--text-muted);
    flex-shrink: 0;
}
.kge-promote-notice .icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
.kge-promote-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    display: flex; gap: 8px; justify-content: flex-end; align-items: center;
    flex-shrink: 0;
}
.kge-promote-footer-left {
    flex: 1;
    font-size: 0.82rem;
    color: var(--text-muted);
}
/* Promote result block */
.kge-promote-result {
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 0.84rem;
    line-height: 1.6;
    display: none;
    flex-shrink: 0;
}
.kge-promote-result.success {
    background: rgba(35,134,54,0.1);
    border: 1px solid rgba(35,134,54,0.3);
    color: var(--text);
    display: block;
}
.kge-promote-result.error {
    background: rgba(218,54,51,0.08);
    border: 1px solid rgba(218,54,51,0.3);
    color: var(--red);
    display: block;
}
</style>

<!-- ═══════════════════ LAYOUT ═══════════════════ -->

<button class="kg-hamburger" id="kg-hamburger" onclick="toggleSidebar()" title="Toggle navigation">
    <span></span><span></span><span></span>
</button>

<button id="kg-import-btn" onclick="importMdDocEntities()" title="KG Importer"
    style="position:fixed; top:10px; left:116px; z-index:1100;
           width:38px; height:38px; border-radius:8px;
           background:var(--card); border:1px solid var(--border);
           display:flex; align-items:center; justify-content:center;
           cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,.12);
           font-size:1.1rem; transition:background 0.2s;">
    📥
</button>

<button id="kg-export-btn" onclick="openExportModal()" title="Export / Promote"
    style="position:fixed; top:10px; left:162px; z-index:1100;
           width:38px; height:38px; border-radius:8px;
           background:var(--card); border:1px solid var(--border);
           display:flex; align-items:center; justify-content:center;
           cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,.12);
           font-size:1.1rem; transition:background 0.2s;">
    &#x1F4E4;
</button>

<div class="kg-flyout-overlay" id="kg-flyout-overlay" onclick="closeSidebar()"></div>

<div class="kg-sidebar" id="kg-sidebar">
    <div class="kg-sidebar-header">
        <i class="bi bi-diagram-3-fill" style="color:var(--staging-accent);font-size:1.2rem;"></i>
        <h2> </h2>
        <span class="kg-staging-badge">Staging</span>
        <button class="btn btn-ghost btn-sm" onclick="closeSidebar()" title="Close">&#x2715;</button>
    </div>

    <div class="kg-sidebar-actions">
        <button class="btn btn-ghost btn-sm" onclick="showModal('modalFolder')" title="New Folder">
            <i class="bi bi-folder-plus"></i> 
        </button>
        <button class="btn btn-ghost btn-sm" onclick="showModal('modalNode')" title="New Node">
            <i class="bi bi-plus-circle"></i> 
        </button>
        <button class="btn btn-ghost btn-sm" id="btnEditSelected" onclick="editSelected()" title="Edit selected" disabled style="opacity:0.4;">
            <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-ghost btn-sm" onclick="refreshTree()" title="Refresh">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <label class="kg-dnd-toggle" title="Enable or disable drag &amp; drop reordering">
            <input type="checkbox" id="chkDnd" onchange="toggleDnd(this.checked)">
            <i class="bi bi-arrows-move"></i> DnD
        </label>
    </div>

    <div style="padding:6px 10px; border-bottom:1px solid var(--border); flex-shrink:0;">
        <input type="text" id="treeSearch" placeholder="Search nodes..."
            style="width:100%; padding:5px 8px; border-radius:5px; border:1px solid var(--border);
                   background:var(--bg); color:var(--text); font-size:0.83rem;">
    </div>

    <div class="kg-tree-wrap" id="kg-tree">Loading...</div>
</div>

<div class="kg-layout">

    <div class="kg-topbar">
        <div class="kg-topbar-left">
            <i class="bi bi-diagram-3-fill" style="color:var(--staging-accent);"></i>
            <h2 id="kg-topbar-title"> </h2>
            <span class="kg-staging-badge">Staging</span>
        </div>
        <div class="kg-topbar-right">

            <!-- TTS Widget integrated in topbar -->
            <div id="kg-tts-widget" style="display:flex; align-items:center; gap:8px; margin-right: 5px;">
                <div id="kg-tts-status-text" style="font-size: 0.8rem; color: var(--text-muted); max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                <div id="kg-tts-loader" style="border: 2px solid rgba(125,125,125,0.2); border-top: 2px solid var(--accent, #007bff); border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; display: none;"></div>
                <button class="btn btn-ghost btn-sm" id="kg-tts-play-btn" onclick="playNodeTts()" title="Read Node Content" style="font-size:1.1rem; padding: 2px 6px;">▶️</button>
                <button class="btn btn-ghost btn-sm" id="kg-tts-stop-btn" onclick="stopNodeTts()" style="display:none; font-size:1.1rem; padding: 2px 6px;" title="Stop">⏹️</button>
            </div>
            <audio id="kg-tts-audio" style="display:none;" onended="resetNodeTtsUI()"></audio>

            <button class="btn btn-ghost btn-sm" id="btnTheme" title="Toggle theme" style="display:none;">&#x1F319;</button>
        </div>
    </div>

    <div class="kg-main">

        <div class="kg-empty" id="kg-empty-state">
            <i class="bi bi-diagram-3" style="font-size:3rem; opacity:0.3;"></i>
            <span>Select a node from the tree</span>
            <span class="hint">or create a new one with the + Node button</span>
        </div>

        <div id="kg-node-view" style="display:none; flex:1; flex-direction:column; overflow:hidden; min-height:0; padding-bottom: 44px;">

            <div class="kg-main-header">
                <h4 style="font-size:10px;" id="kg-node-title">—</h4>
                <span class="node-type-badge" id="kg-node-type-badge"></span>
                <div style="display:flex; gap:6px; margin-left:auto;">
                    <button class="btn btn-ghost btn-sm" onclick="showAddItemModal()" title="Link entity">
                        <i class="bi bi-link-45deg"></i> Link
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="downloadNodeMd()" title="Download as Markdown">
                        <i class="bi bi-download"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCurrentNode()" title="Archive node">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-green" id="btnSaveNode" onclick="saveNode()">
                        <i class="bi bi-floppy"></i> Save
                    </button>
                </div>
            </div>

            <div class="kg-editor-wrap">
                <div class="kg-meta-bar">
                    <span id="nodeIdDisplay" title="Node ID" style="font-family: ui-monospace, SFMono-Regular, monospace; color: var(--text-muted); font-size: 0.85rem; padding: 5px 8px; background: var(--bg); border: 1px solid var(--border); border-radius: 5px; user-select: all; cursor: default;"></span>
                    <input type="text" id="nodeNameInput" placeholder="Node name…" style="font-weight:600; font-size:0.95rem;">
                    <select id="nodeTypeSelect">
                        <option value="note">📝 Note</option>
                        <option value="relationship">🔗 Relationship</option>
                        <option value="character">👤 Character</option>
                        <option value="location">📍 Location</option>
                        <option value="event">📅 Event</option>
                        <option value="concept">💡 Concept</option>
                        <option value="arc">🌀 Arc</option>
                        <option value="episode">🎬 Episode</option>
                    </select>
                    <input type="text" id="nodeKeywords" placeholder="keywords, comma, separated" style="max-width:220px;">
                    <select id="nodeCategorySelect" style="max-width:180px;">
                        <option value="">— No folder —</option>
                    </select>
                </div>

                <div id="kg-editor"></div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ BOTTOM DRAWER ═══════ -->
<div class="kg-bottom-drawer" id="kg-bottom-drawer">
    <div class="kg-drawer-handle" onclick="toggleBottomDrawer()">
        <div class="kg-drawer-title">
            <i class="bi bi-link-45deg" style="font-size:1.1rem;"></i> Linked Entities
            <span id="kg-items-count" style="opacity:0.6;"></span>
        </div>
        <div class="kg-drawer-hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="kg-drawer-content">
        <div id="kg-items-list"></div>
    </div>
</div>

<!-- ═══════ CURATED DOCS INLINE VIEWER ═══════ -->
<div class="kg-curated-modal-bg" id="kg-curated-modal-bg">
    <div class="kg-curated-modal">
        <button class="kg-curated-modal-close" onclick="closeCuratedModal()">&times;</button>
        <iframe id="kg-curated-frame" src="about:blank" allowfullscreen></iframe>
    </div>
</div>

<!-- ═══════ MODALS ═══════ -->

<div class="kg-modal-bg" id="modalEditFolder">
    <div class="kg-modal">
        <h3><i class="bi bi-pencil"></i> Edit Folder</h3>
        <input type="hidden" id="editFolderId">
        <label style="font-size:0.8rem; color:var(--text-muted);">Name</label>
        <input type="text" id="editFolderName" placeholder="Folder name...">
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalEditFolder')">Cancel</button>
            <button class="btn btn-primary" onclick="saveEditFolder()">Save</button>
        </div>
    </div>
</div>

<div class="kg-modal-bg" id="modalFolder">
    <div class="kg-modal">
        <h3><i class="bi bi-folder-plus"></i> New Folder</h3>
        <label style="font-size:0.8rem; color:var(--text-muted);">Name</label>
        <input type="text" id="folderName" placeholder="Folder name…">
        <label style="font-size:0.8rem; color:var(--text-muted);">Parent folder (optional)</label>
        <select id="folderParent"><option value="">— Root —</option></select>
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalFolder')">Cancel</button>
            <button class="btn btn-primary" onclick="createFolder()">Create</button>
        </div>
    </div>
</div>

<div class="kg-modal-bg" id="modalNode">
    <div class="kg-modal">
        <h3><i class="bi bi-plus-circle"></i> New Node</h3>
        <label style="font-size:0.8rem; color:var(--text-muted);">Name</label>
        <input type="text" id="newNodeName" placeholder="Node name…">
        <label style="font-size:0.8rem; color:var(--text-muted);">Type</label>
        <select id="newNodeType">
            <option value="note">📝 Note</option>
            <option value="relationship">🔗 Relationship</option>
            <option value="character">👤 Character</option>
            <option value="location">📍 Location</option>
            <option value="event">📅 Event</option>
            <option value="concept">💡 Concept</option>
            <option value="arc">🌀 Arc</option>
            <option value="episode">🎬 Episode</option>
        </select>
        <label style="font-size:0.8rem; color:var(--text-muted);">Folder (optional)</label>
        <select id="newNodeCategory"><option value="">— Root —</option></select>
        <div class="actions">
            <button class="btn btn-ghost" onclick="hideModal('modalNode')">Cancel</button>
            <button class="btn btn-primary" onclick="createNode()">Create</button>
        </div>
    </div>
</div>

<!-- ═══════ ADD / EDIT LINKED ITEM MODAL ═══════ -->
<div class="kg-modal-bg" id="modalAddItem">
    <div class="kg-modal">
        <h3 id="modalAddItemTitle"><i class="bi bi-link-45deg"></i> Link Entity</h3>
        <input type="hidden" id="editingItemId" value="">
        <label style="font-size:0.8rem; color:var(--text-muted);">Entity type</label>
        <select id="itemType">
            <option value="kg_node">KG Node</option>
            <option value="character">🦸 Character</option>
            <option value="anima">🐾 Anima</option>
            <option value="location">🗺️ Location</option>
            <option value="background">🏞️ Background</option>
            <option value="artifact">🏺 Artifact</option>
            <option value="vehicle">🛸 Vehicle</option>
            <option value="generative">⚡ Generative</option>
            <option value="sketch">🪄 Sketch</option>
            <option value="frame">🖼️ Frame</option>
            <option value="md_doc">MD Document</option>
            <option value="episode">Episode</option>
            <option value="board">Board</option>
            <option value="other">Other</option>
        </select>
        <label style="font-size:0.8rem; color:var(--text-muted);">ID (optional)</label>
        <input type="number" id="itemEntityId" placeholder="Entity ID…">
        <label style="font-size:0.8rem; color:var(--text-muted);">Label / Name</label>
        <input type="text" id="itemLabel" placeholder="Display name…">
        <label style="font-size:0.8rem; color:var(--text-muted);">Relationship</label>
        <input type="text" id="itemRelationship" placeholder="e.g. subject, see_also, caused_by, companion…">
        <label style="font-size:0.8rem; color:var(--text-muted);">Note (optional)</label>
        <textarea id="itemNote" placeholder="Optional note…" style="height:60px;"></textarea>
        <div class="actions">
            <button class="btn btn-ghost" onclick="closeAddItemModal()">Cancel</button>
            <button class="btn btn-primary" id="modalAddItemSaveBtn" onclick="saveLinkedItem()">Link</button>
        </div>
    </div>
</div>

<!-- ═══════ EXPORT / PROMOTE MODAL ═══════ -->
<div class="kge-modal-bg" id="kge-modal-bg">
    <div class="kge-modal">

        <div class="kge-header">
            <h3>&#x1F4E4; Export / Promote — Staging</h3>
            <button class="kge-close" onclick="closeExportModal()">&#x2715;</button>
        </div>

        <div class="kge-tabs">
            <!-- Promote tab is FIRST -->
            <button class="kge-tab tab-promote active" id="kge-tab-promote"  onclick="kgeSetTab('promote')">&#x1F680; Promote to Live</button>
            <button class="kge-tab"                    id="kge-tab-full"     onclick="kgeSetTab('full')">Full Graph</button>
            <button class="kge-tab"                    id="kge-tab-semantic" onclick="kgeSetTab('semantic')">&#x1F9E0; Semantic Slice</button>
        </div>

        <div class="kge-panes">

            <!-- ══ Promote Pane ══ -->
            <div class="kge-pane active" id="kge-pane-promote">
                <div class="kge-promote-body">
                    <div class="kge-promote-notice">
                        <span class="icon">⚠️</span>
                        <span>
                            Selected staging nodes will be <strong>copied into the live KG tables</strong> as new nodes.
                            Folder placement is matched by folder name — if a matching live folder is found the node lands there, otherwise it is placed at root.
                            Edges between promoted nodes are remapped automatically; edges pointing to non-promoted staging nodes retain their staging IDs.
                            The staging nodes are <em>not</em> deleted after promotion.
                        </span>
                    </div>

                    <div style="display:flex; flex-direction:column; flex:1; min-height:0; gap:0;">
                        <div class="kge-picker-header">
                            <span>Select nodes to promote</span>
                            <button class="kge-picker-select-all" onclick="kgePromotePickerToggleAll()">Select all</button>
                        </div>
                        <div class="kge-picker-tree-wrap" id="kge-promote-picker-wrap">
                            <div class="kge-picker-loading">Loading…</div>
                        </div>
                    </div>

                    <div class="kge-options-strip">
                        <div class="kge-option-row">
                            <input type="checkbox" id="kge-promote-with-edges" checked>
                            <label for="kge-promote-with-edges">
                                <strong>Include edges</strong>
                                <span style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:1px;">Copy linked entity rows into live kg_node_items.</span>
                            </label>
                        </div>
                        <div class="kge-option-row">
                            <input type="checkbox" id="kge-promote-overwrite">
                            <label for="kge-promote-overwrite">
                                <strong>Overwrite if name+type matches</strong>
                                <span style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:1px;">Update existing live node instead of inserting a new one.</span>
                            </label>
                        </div>
                    </div>

                    <div class="kge-promote-result" id="kge-promote-result"></div>
                </div>

                <div class="kge-promote-footer">
                    <span class="kge-promote-footer-left" id="kge-promote-count"></span>
                    <button class="btn btn-ghost" onclick="closeExportModal()">Cancel</button>
                    <button class="btn btn-staging" id="kge-promote-btn" onclick="kgeDoPromote()" disabled>
                        &#x1F680; Promote to Live
                    </button>
                </div>
            </div>

            <!-- ══ Full Export Pane ══ -->
            <div class="kge-pane" id="kge-pane-full">
                <div class="kge-full-body">
                    <p class="kge-desc" style="margin:0;">
                        Select nodes and/or folders to export. Selecting a folder includes all child nodes recursively.
                        Leave everything unchecked to export the entire graph.
                    </p>

                    <!-- Picker tree -->
                    <div style="display:flex; flex-direction:column; flex:1; min-height:0; gap:0;">
                        <div class="kge-picker-header">
                            <span>Select nodes &amp; folders</span>
                            <button class="kge-picker-select-all" onclick="kgePickerToggleAll()">Select all</button>
                        </div>
                        <div class="kge-picker-tree-wrap" id="kge-picker-tree-wrap">
                            <div class="kge-picker-loading">Loading tree…</div>
                        </div>
                    </div>

                    <!-- Options: lore content + edges -->
                    <div class="kge-options-strip">
                        <div class="kge-option-row">
                            <input type="checkbox" id="kge-full-with-content">
                            <label for="kge-full-with-content">
                                <strong>Include lore content</strong>
                                <span style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:1px;">Adds full Markdown text for every node.</span>
                            </label>
                        </div>
                        <div class="kge-option-row">
                            <input type="checkbox" id="kge-full-with-edges" checked>
                            <label for="kge-full-with-edges">
                                <strong>Include edges</strong>
                                <span style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:1px;">Exports graph relationships (kg_staging_node_items).</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="kge-full-footer">
                    <span class="kge-full-footer-left" id="kge-picker-count"></span>
                    <button class="btn btn-ghost" onclick="closeExportModal()">Cancel</button>
                    <button class="btn btn-primary" id="kge-full-export-btn" onclick="kgeDoFullExport()">
                        &#x1F4E5; Export
                    </button>
                </div>
            </div>

            <!-- ══ Semantic Slice Pane ══ -->
            <div class="kge-pane" id="kge-pane-semantic" style="position:relative;">
                <div class="kge-loading-bar" id="kge-loading-bar"></div>

                <div class="kge-sem-top">
                    <p class="kge-desc" style="margin:0;">
                        Describe what you need in plain language. The graph will be searched
                        semantically and the most relevant nodes pre-selected for export.
                    </p>
                    <div class="kge-query-row">
                        <input
                            type="text"
                            class="kge-query-input"
                            id="kge-query-input"
                            placeholder="e.g. Kaelen's suppression system and its effect on Crater City…"
                            onkeydown="if(event.key==='Enter') kgeRunQuery()"
                        >
                        <select class="kge-n-select" id="kge-n-select" title="Max results">
                            <option value="10">Top 10</option>
                            <option value="20" selected>Top 20</option>
                            <option value="35">Top 35</option>
                            <option value="50">Top 50</option>
                        </select>
                        <button class="btn btn-primary" onclick="kgeRunQuery()" id="kge-search-btn">
                            Search
                        </button>
                    </div>
                </div>

                <div class="kge-hits-area" id="kge-hits-area">
                    <div class="kge-hits-empty" id="kge-hits-empty">
                        <span style="font-size:2rem;">🧠</span>
                        <span>Describe your context need above</span>
                        <span class="hint">
                            The semantic search will rank all graph nodes by relevance to your query
                            and pre-select the most useful ones for export.
                        </span>
                    </div>
                </div>

                <div class="kge-sem-footer">
                    <span class="kge-sel-count" id="kge-sel-count"></span>
                    <label class="kge-content-toggle">
                        <input type="checkbox" id="kge-sem-with-content">
                        Include lore content
                    </label>
                    <button class="btn btn-ghost" onclick="closeExportModal()">Cancel</button>
                    <button class="btn btn-primary" id="kge-export-sel-btn"
                            onclick="kgeDoFocusedExport()" disabled>
                        &#x1F4E5; Export Selected
                    </button>
                </div>
            </div>

        </div><!-- /kge-panes -->
    </div><!-- /kge-modal -->
</div><!-- /kge-modal-bg -->

<div id="kg-toast"></div>

<script>
// ═══════════════════════════════════════════════
// UI & FLYOUT TOGGLES
// ═══════════════════════════════════════════════
function toggleSidebar() {
    const sidebar   = document.getElementById('kg-sidebar');
    const overlay   = document.getElementById('kg-flyout-overlay');
    const hamburger = document.getElementById('kg-hamburger');
    if (sidebar.classList.contains('open')) {
        closeSidebar();
    } else {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        hamburger.classList.add('open');
        if (!treeInitialized) { initTree(); treeInitialized = true; }
    }
}
function closeSidebar() {
    document.getElementById('kg-sidebar').classList.remove('open');
    document.getElementById('kg-flyout-overlay').classList.remove('open');
    document.getElementById('kg-hamburger').classList.remove('open');
}

function toggleBottomDrawer() {
    document.getElementById('kg-bottom-drawer').classList.toggle('open');
}

// ═══════════════════════════════════════════════
// CURATED DOCS INLINE VIEWER
// ═══════════════════════════════════════════════
function openCuratedDoc(docId, focusType, focusEntity) {
    if (!docId) return;
    let url = `view_curated_docs.php?doc_id=${encodeURIComponent(docId)}&embed=1`;
    if (focusType)   url += `&focus_type=${encodeURIComponent(focusType)}`;
    if (focusEntity) url += `&focus_entity=${encodeURIComponent(focusEntity)}`;
    document.getElementById('kg-curated-frame').src = url;
    document.getElementById('kg-curated-modal-bg').style.display = 'flex';
}

function closeCuratedModal() {
    document.getElementById('kg-curated-modal-bg').style.display = 'none';
    document.getElementById('kg-curated-frame').src = 'about:blank';
}

document.getElementById('kg-curated-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) closeCuratedModal();
});

// ═══════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════
let currentNodeId    = null;
let selectedTreeItem = null;
let editor           = null;
let treeInitialized  = false;
let allCategories    = [];

// ═══════════════════════════════════════════════
// DRAG & DROP TOGGLE
// Namespaced key so staging and live don't share DnD state
// ═══════════════════════════════════════════════
const KG_DND_KEY = 'kg_staging_dnd_enabled';

function isDndEnabled() {
    try { return localStorage.getItem(KG_DND_KEY) === 'true'; } catch(e) { return false; }
}

function toggleDnd(enabled) {
    try { localStorage.setItem(KG_DND_KEY, enabled ? 'true' : 'false'); } catch(e) {}
    const tree = $('#kg-tree').jstree(true);
    if (tree) { $('#kg-tree').jstree('destroy'); treeInitialized = false; }
    initTree();
    toast(enabled ? 'Drag & Drop enabled' : 'Drag & Drop disabled');
}

// ═══════════════════════════════════════════════
// TTS WIDGET
// ═══════════════════════════════════════════════
const kgTtsStatus  = document.getElementById('kg-tts-status-text');
const kgTtsLoader  = document.getElementById('kg-tts-loader');
const kgTtsPlayBtn = document.getElementById('kg-tts-play-btn');
const kgTtsStopBtn = document.getElementById('kg-tts-stop-btn');
const kgTtsAudio   = document.getElementById('kg-tts-audio');

function resetNodeTtsUI() {
    kgTtsPlayBtn.style.display = 'inline-flex';
    kgTtsStopBtn.style.display = 'none';
    kgTtsLoader.style.display  = 'none';
    kgTtsStatus.textContent    = "";
    kgTtsPlayBtn.disabled      = false;
}

function stopNodeTts() {
    if (kgTtsAudio) { kgTtsAudio.pause(); kgTtsAudio.currentTime = 0; }
    resetNodeTtsUI();
}

async function playNodeTts() {
    if (!editor || !currentNodeId) {
        kgTtsStatus.textContent = "No node selected!";
        setTimeout(() => kgTtsStatus.textContent = "", 2000);
        return;
    }
    let text = editor.getMarkdown() || '';
    text = text.replace(/[#*`_\[\]>]/g, '').trim();
    if (!text) {
        kgTtsStatus.textContent = "Node is empty!";
        setTimeout(() => kgTtsStatus.textContent = "", 2000);
        return;
    }
    if (text.length > 500000) { kgTtsStatus.textContent = "Text too long"; return; }

    kgTtsPlayBtn.style.display = 'none';
    kgTtsStopBtn.style.display = 'none';
    kgTtsLoader.style.display  = 'block';
    kgTtsStatus.textContent    = "Generating...";

    try {
        const response = await fetch('api_tts_inline.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text, model: 'en_US-libritts_r-medium' })
        });
        const data = await response.json();
        if (data.status === 'success' && data.url) {
            kgTtsLoader.style.display  = 'none';
            kgTtsStopBtn.style.display = 'inline-flex';
            kgTtsStatus.textContent    = "Playing...";
            kgTtsAudio.src = data.url;
            kgTtsAudio.play().catch(e => {
                console.error("Autoplay failed:", e);
                kgTtsStatus.textContent = "Autoplay blocked";
                resetNodeTtsUI();
            });
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    } catch (error) {
        console.error('TTS Error:', error);
        resetNodeTtsUI();
        kgTtsStatus.textContent = "Error";
        setTimeout(() => kgTtsStatus.textContent = "", 2000);
    }
}

// ═══════════════════════════════════════════════
// THEME
// ═══════════════════════════════════════════════
function isDark() {
    const t = document.documentElement.getAttribute('data-theme');
    if (t === 'dark') return true;
    if (t === 'light') return false;
    return window.matchMedia('(prefers-color-scheme:dark)').matches;
}
function applyTheme() {
    const dark = isDark();
    document.getElementById('tui-dark-theme')[dark ? 'removeAttribute' : 'setAttribute']('disabled','true');
    document.getElementById('btnTheme').textContent = dark ? '☀️' : '🌙';
}
document.getElementById('btnTheme').addEventListener('click', () => {
    const next = isDark() ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('spw_theme', next); } catch(e){}
    applyTheme();
    if (editor) { editor.destroy(); initEditor(currentNodeId ? '' : ''); }
});
applyTheme();
new MutationObserver(() => applyTheme()).observe(document.documentElement, {attributes:true});

// ═══════════════════════════════════════════════
// EDITOR
// ═══════════════════════════════════════════════
function initEditor(initialMd = '') {
    const el = document.getElementById('kg-editor');
    if (editor) { try { editor.destroy(); } catch(e){} editor = null; }
    editor = new toastui.Editor({
        el,
        height: '100%',
        initialEditType: 'markdown',
        previewStyle: 'tab',
        hideModeSwitch: true,
        theme: isDark() ? 'dark' : 'light',
        initialValue: initialMd,
        usageStatistics: false,
        autofocus: false
    });
    el.addEventListener('paste', e => {
        const text = e.clipboardData?.getData('text/plain');
        if (text) { e.preventDefault(); e.stopPropagation(); editor.insertText(text); }
    }, true);
}

// ═══════════════════════════════════════════════
// TREE
// ═══════════════════════════════════════════════
function initTree() {
    const dndEnabled = isDndEnabled();
    const plugins = ['search', 'types', 'state', 'contextmenu'];
    if (dndEnabled) plugins.push('dnd');

    $('#kg-tree').jstree({
        core: {
            data: {
                url: 'kg_staging_api.php?action=fetch_tree',
                dataType: 'json',
                dataFilter: raw => {
                    const j = JSON.parse(raw);
                    return j.ok ? JSON.stringify(j.tree) : '[]';
                }
            },
            themes: { name:'default', dots:true, icons:true },
            check_callback: true,
        },
        plugins: plugins,
        types: {
            folder: { icon:'bi bi-folder2' },
            node:   { icon:'bi bi-journal-text' },
        },
        contextmenu: { items: contextMenuItems },
    })
    .on('select_node.jstree', (e, data) => {
        if (!data.event) return;
        const node = data.node;
        selectedTreeItem = {db_id: node.data.db_id, type: node.type, text: node.text};
        const editBtn = document.getElementById('btnEditSelected');
        if (editBtn) { editBtn.disabled = false; editBtn.style.opacity = '1'; }
        if (node.type === 'node') {
            loadNode(node.data.db_id);
            closeSidebar();
        } else {
            document.getElementById('currentFolderId').value = node.data.db_id;
        }
    })
    .on('move_node.jstree', (e, data) => {
        $.post('kg_staging_api.php', { action:'move_node', id:data.node.id, parent:data.parent })
         .fail(() => { data.instance.refresh(); toast('Move failed','error'); });
    });

    treeInitialized = true;
}

function refreshTree() {
    if ($('#kg-tree').jstree(true)) {
        $('#kg-tree').jstree('refresh');
    } else {
        initTree();
    }
    loadCategories();
}

function contextMenuItems(node) {
    const items = {};
    if (node.type === 'folder') {
        items.rename = {
            label: 'Rename Folder',
            action: () => {
                const name = prompt('Rename folder:', node.text);
                if (name && name.trim()) {
                    $.post('kg_staging_api.php', {action:'rename_category', id:node.data.db_id, name:name.trim()}, res => {
                        if (res.ok) refreshTree(); else toast('Error','error');
                    }, 'json');
                }
            }
        };
        items.del = {
            label: 'Delete Folder',
            action: () => {
                if (confirm('Delete folder? Nodes inside will move to root.')) {
                    $.post('kg_staging_api.php', {action:'delete_category', id:node.data.db_id}, res => {
                        if (res.ok) refreshTree(); else toast('Error','error');
                    }, 'json');
                }
            }
        };
    } else {
        items.rename = {
            label: 'Rename Node',
            action: () => {
                const name = prompt('Rename node:', node.text);
                if (name && name.trim()) {
                    $.post('kg_staging_api.php', {action:'rename_node', id:node.data.db_id, name:name.trim()}, res => {
                        if (res.ok) { refreshTree(); if (currentNodeId == node.data.db_id) document.getElementById('nodeNameInput').value = name.trim(); }
                        else toast('Error','error');
                    }, 'json');
                }
            }
        };
        items.del = {
            label: 'Archive Node',
            action: () => {
                if (confirm('Archive this node?')) {
                    $.post('kg_staging_api.php', {action:'delete_node', id:node.data.db_id}, res => {
                        if (res.ok) { refreshTree(); if (currentNodeId == node.data.db_id) showEmptyState(); }
                        else toast('Error','error');
                    }, 'json');
                }
            }
        };
    }
    return items;
}

document.addEventListener('DOMContentLoaded', () => {
    const hf = document.createElement('input');
    hf.type = 'hidden'; hf.id = 'currentFolderId'; hf.value = '';
    document.body.appendChild(hf);

    const chkDnd = document.getElementById('chkDnd');
    if (chkDnd) chkDnd.checked = isDndEnabled();

    initTree();
    loadCategories();
    initEditor('');

    let st;
    document.getElementById('treeSearch').addEventListener('input', e => {
        clearTimeout(st);
        st = setTimeout(() => $('#kg-tree').jstree(true).search(e.target.value), 250);
    });
});

// ═══════════════════════════════════════════════
// CATEGORIES
// ═══════════════════════════════════════════════
function loadCategories() {
    $.get('kg_staging_api.php?action=fetch_tree', res => {
        if (!res.ok) return;
        allCategories = res.tree.filter(n => n.type === 'folder').map(n => ({
            id: n.data.db_id, name: n.text, parent: n.parent
        }));
        populateCategorySelects();
    }, 'json');
}

function populateCategorySelects() {
    const selects = ['folderParent','newNodeCategory','nodeCategorySelect'];
    selects.forEach(sid => {
        const sel = document.getElementById(sid);
        if (!sel) return;
        const prev = sel.value;
        while (sel.options.length > 1) sel.remove(1);
        allCategories.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id; o.text = c.name;
            sel.add(o);
        });
        sel.value = prev;
    });
}

// ═══════════════════════════════════════════════
// LOAD NODE
// ═══════════════════════════════════════════════
function loadNode(id) {
    if (typeof stopNodeTts === 'function') stopNodeTts();
    currentNodeId = id;
    $.get('kg_staging_api.php?action=get_node&id=' + id, res => {
        if (!res.ok) { toast('Failed to load node','error'); return; }
        const n = res.node;

        document.getElementById('kg-empty-state').style.display = 'none';
        const view = document.getElementById('kg-node-view');
        view.style.display = 'flex';

        document.getElementById('kg-bottom-drawer').classList.add('visible');
        document.getElementById('kg-bottom-drawer').classList.remove('open');

        document.getElementById('kg-node-title').textContent      = n.name;
        document.getElementById('kg-topbar-title').textContent    = n.name;
        document.getElementById('kg-node-type-badge').textContent = n.node_type;
        document.getElementById('nodeIdDisplay').textContent      = n.id;
        document.getElementById('nodeNameInput').value            = n.name;
        document.getElementById('nodeTypeSelect').value           = n.node_type;
        document.getElementById('nodeKeywords').value             = n.keywords || '';
        document.getElementById('nodeCategorySelect').value       = n.category_id || '';

        initEditor(n.content || '');
        renderLinkedItems(n.items || []);
    }, 'json');
}

function showEmptyState() {
    if (typeof stopNodeTts === 'function') stopNodeTts();
    currentNodeId = null;
    document.getElementById('kg-empty-state').style.display = 'flex';
    document.getElementById('kg-node-view').style.display   = 'none';
    document.getElementById('kg-bottom-drawer').classList.remove('visible');
    document.getElementById('kg-bottom-drawer').classList.remove('open');
}

// ═══════════════════════════════════════════════
// SAVE NODE
// ═══════════════════════════════════════════════
function saveNode() {
    if (!currentNodeId) return;
    const btn = document.getElementById('btnSaveNode');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass"></i> Saving…';

    const payload = {
        id:          currentNodeId,
        name:        document.getElementById('nodeNameInput').value,
        content:     editor ? editor.getMarkdown() : '',
        keywords:    document.getElementById('nodeKeywords').value,
        node_type:   document.getElementById('nodeTypeSelect').value,
        category_id: document.getElementById('nodeCategorySelect').value || null,
    };

    $.post('kg_staging_api.php', {action:'save_node', ...payload}, res => {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy"></i> Save';
        if (res.ok) {
            toast('Saved ✓');
            document.getElementById('kg-node-title').textContent      = payload.name;
            document.getElementById('kg-node-type-badge').textContent = payload.node_type;
            refreshTree();
        } else {
            toast('Save failed: ' + (res.error || ''), 'error');
        }
    }, 'json').fail(() => {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy"></i> Save';
        toast('Network error','error');
    });
}

// ═══════════════════════════════════════════════
// DELETE NODE
// ═══════════════════════════════════════════════
function deleteCurrentNode() {
    if (!currentNodeId) return;
    if (!confirm('Archive this node?')) return;
    $.post('kg_staging_api.php', {action:'delete_node', id:currentNodeId}, res => {
        if (res.ok) { refreshTree(); showEmptyState(); toast('Archived'); }
        else toast('Error','error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// LINKED ITEMS — RENDER
// ═══════════════════════════════════════════════
function itemTypeMeta(type, hasDeepLink) {
    const map = {
        kg_node:    { cls: 'kg_node',    icon: '🔮', label: 'KG Node'    },
        character:  { cls: 'character',  icon: '🦸', label: 'Character'  },
        anima:      { cls: 'anima',      icon: '🐾', label: 'Anima'      },
        location:   { cls: 'location',   icon: '🗺️', label: 'Location'   },
        background: { cls: 'other',      icon: '🏞️', label: 'Background' },
        artifact:   { cls: 'other',      icon: '🏺', label: 'Artifact'   },
        vehicle:    { cls: 'other',      icon: '🛸', label: 'Vehicle'    },
        generative: { cls: 'other',      icon: '⚡', label: 'Generative' },
        sketch:     { cls: 'other',      icon: '🪄', label: 'Sketch'     },
        frame:      { cls: 'other',      icon: '🖼️', label: 'Frame'      },
        md_doc:     { cls: 'md_doc',     icon: '📄', label: 'Document'   },
        episode:    { cls: 'episode',    icon: '🎬', label: 'Episode'    },
        board:      { cls: 'board',      icon: '📋', label: 'Board'      },
    };
    const base = map[type] || { cls: 'other', icon: '📦', label: type || 'Other' };
    if (type === 'md_doc' && hasDeepLink) {
        return { ...base, cls: 'md_doc_deep', icon: '🔍', label: 'Lore Entity' };
    }
    return base;
}

function parseDeepLinkMeta(item) {
    if (item.item_type !== 'md_doc' || !item.note) return null;
    try {
        const parsed = JSON.parse(item.note);
        if (parsed && parsed.focus_type && parsed.focus_entity) {
            return { focusType: parsed.focus_type, focusEntity: parsed.focus_entity, docName: parsed.doc_name || '' };
        }
    } catch (e) {}
    return null;
}

function itemExternalUrl(item, deepLink) {
    const id = item.item_id;
    if (!id) return null;
    if (item.item_type === 'md_doc') {
        if (deepLink) {
            return `view_curated_docs.php?doc_id=${encodeURIComponent(id)}`
                 + `&focus_type=${encodeURIComponent(deepLink.focusType)}`
                 + `&focus_entity=${encodeURIComponent(deepLink.focusEntity)}`;
        }
        return `view_md.php?id=${id}`;
    }
    const routes = {
        kg_node:    null,
        character:  `entity_form.php?entity_type=characters&entity_id=${id}`,
        anima:      `entity_form.php?entity_type=animas&entity_id=${id}`,
        location:   `entity_form.php?entity_type=locations&entity_id=${id}`,
        background: `entity_form.php?entity_type=backgrounds&entity_id=${id}`,
        artifact:   `entity_form.php?entity_type=artifacts&entity_id=${id}`,
        vehicle:    `entity_form.php?entity_type=vehicles&entity_id=${id}`,
        generative: `entity_form.php?entity_type=generatives&entity_id=${id}`,
        sketch:     `entity_form.php?entity_type=sketches&entity_id=${id}`,
        frame:      `view_frame.php?frame_id=${id}`,
        episode:    `view_editorial_sequences.php?episode_id=${id}`,
        board:      `boards_view.php?board_id=${id}`,
    };
    return routes.hasOwnProperty(item.item_type) ? routes[item.item_type] : null;
}

function renderLinkedItems(items) {
    const list  = document.getElementById('kg-items-list');
    const count = document.getElementById('kg-items-count');
    count.textContent = items.length ? '(' + items.length + ')' : '';

    if (!items.length) {
        list.innerHTML = `
            <div style="padding:20px; color:var(--text-muted); font-size:0.85rem; font-style:italic; text-align:center;">
                No linked entities yet — use the <strong>Link</strong> button above to connect this node to characters, locations, documents or other nodes.
            </div>`;
        return;
    }

    list.innerHTML = items.map(item => {
        const deepLink   = parseDeepLinkMeta(item);
        const meta       = itemTypeMeta(item.item_type, !!deepLink);
        const isIncoming = item.direction === 'incoming';

        const linkId    = isIncoming ? parseInt(item.node_id)   : parseInt(item.item_id);
        const linkLabel = isIncoming
            ? escHtml(item.source_node_name || item.item_label)
            : escHtml(item.item_label || (item.item_id ? '#' + item.item_id : '—'));

        const dirBadge = isIncoming
            ? `<span class="kg-pill-incoming">← from</span>`
            : '';

        const relBadge = item.relationship
            ? `<span class="kg-item-rel">${escHtml(item.relationship)}</span>`
            : '';

        let primaryBtn = '';
        if (item.item_type === 'kg_node' && (item.item_id || item.node_id)) {
            primaryBtn = `
                <button class="kg-item-label-btn"
                        onclick="loadNode(${linkId})"
                        title="Open KG node: ${linkLabel}">
                    ${meta.icon} ${linkLabel}
                </button>`;
        } else if (item.item_type === 'md_doc' && deepLink) {
            primaryBtn = `
                <button class="kg-item-label-btn"
                        onclick="openCuratedDoc(${parseInt(item.item_id)}, '${escHtml(deepLink.focusType)}', '${escHtml(deepLink.focusEntity)}')"
                        title="Open in Curated Docs: ${linkLabel}${deepLink.docName ? ' (' + escHtml(deepLink.docName) + ')' : ''}">
                    ${meta.icon} ${linkLabel}
                </button>`;
        } else {
            const extUrl = itemExternalUrl(item, null);
            if (extUrl) {
                primaryBtn = `
                    <a class="kg-item-label-btn"
                       href="${escHtml(extUrl)}" target="_blank" rel="noopener"
                       title="Open ${meta.label}: ${linkLabel}">
                        ${meta.icon} ${linkLabel}
                    </a>`;
            } else {
                primaryBtn = `
                    <span class="kg-item-label-btn no-link">
                        ${meta.icon} ${linkLabel}
                    </span>`;
            }
        }

        let extBtn = '';
        const extUrl = itemExternalUrl(item, deepLink);
        if (extUrl) {
            extBtn = `
                <a class="kg-item-ext-btn"
                   href="${escHtml(extUrl)}" target="_blank" rel="noopener"
                   title="Open in new tab">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>`;
        } else if (item.item_type === 'kg_node') {
            const extId = isIncoming ? parseInt(item.node_id) : parseInt(item.item_id);
            extBtn = `
                <a class="kg-item-ext-btn"
                   href="kg_staging.php?node_id=${extId}" target="_blank"
                   title="Open in new tab">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>`;
        }

        const editBtn = !isIncoming ? `
            <button class="kg-item-edit"
                    onclick="editLinkedItem(${item.id})"
                    title="Edit this link">
                <i class="bi bi-pencil"></i>
            </button>` : '';

        const removeBtn = !isIncoming ? `
            <button class="kg-item-remove" onclick="removeLinkedItem(${item.id})" title="Remove link">
                <i class="bi bi-trash"></i>
            </button>` : '';

        return `
        <div class="kg-item-row" data-item-id="${item.id}"
             data-item-type="${escHtml(item.item_type || '')}"
             data-entity-id="${item.item_id || ''}"
             data-item-label="${escHtml(item.item_label || '')}"
             data-relationship="${escHtml(item.relationship || '')}"
             data-note="${escHtml(item.note || '')}">
            <span class="kg-item-type-pill kg-pill-${meta.cls}">${meta.label}</span>
            ${dirBadge}
            ${primaryBtn}
            ${relBadge}
            <div class="kg-item-actions">
                ${extBtn}
                ${editBtn}
                ${removeBtn}
            </div>
        </div>`;
    }).join('');
}

// ═══════════════════════════════════════════════
// LINKED ITEMS — ADD / EDIT / REMOVE
// ═══════════════════════════════════════════════

function showAddItemModal() {
    if (!currentNodeId) return;
    document.getElementById('editingItemId').value      = '';
    document.getElementById('modalAddItemTitle').innerHTML = '<i class="bi bi-link-45deg"></i> Link Entity';
    document.getElementById('modalAddItemSaveBtn').textContent = 'Link';
    document.getElementById('itemType').value           = 'kg_node';
    document.getElementById('itemEntityId').value       = '';
    document.getElementById('itemLabel').value          = '';
    document.getElementById('itemRelationship').value   = '';
    document.getElementById('itemNote').value           = '';
    showModal('modalAddItem');
}

function editLinkedItem(id) {
    const row = document.querySelector(`.kg-item-row[data-item-id="${id}"]`);
    if (!row) return;
    document.getElementById('editingItemId').value         = id;
    document.getElementById('modalAddItemTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Link';
    document.getElementById('modalAddItemSaveBtn').textContent = 'Save Changes';
    document.getElementById('itemType').value              = row.dataset.itemType || 'kg_node';
    document.getElementById('itemEntityId').value          = row.dataset.entityId || '';
    document.getElementById('itemLabel').value             = row.dataset.itemLabel || '';
    document.getElementById('itemRelationship').value      = row.dataset.relationship || '';
    document.getElementById('itemNote').value              = row.dataset.note || '';
    showModal('modalAddItem');
}

function closeAddItemModal() {
    hideModal('modalAddItem');
    document.getElementById('editingItemId').value = '';
}

function saveLinkedItem() {
    const editingId = document.getElementById('editingItemId').value;

    if (editingId) {
        $.post('kg_staging_api.php', {
            action:       'update_item',
            id:           editingId,
            item_type:    document.getElementById('itemType').value,
            item_id:      document.getElementById('itemEntityId').value || null,
            item_label:   document.getElementById('itemLabel').value,
            relationship: document.getElementById('itemRelationship').value,
            note:         document.getElementById('itemNote').value,
        }, res => {
            if (res.ok) {
                closeAddItemModal();
                loadNode(currentNodeId);
                toast('Link updated ✓');
            } else {
                toast('Error: ' + res.error, 'error');
            }
        }, 'json');
    } else {
        $.post('kg_staging_api.php', {
            action:       'add_item',
            node_id:      currentNodeId,
            item_type:    document.getElementById('itemType').value,
            item_id:      document.getElementById('itemEntityId').value || null,
            item_label:   document.getElementById('itemLabel').value,
            relationship: document.getElementById('itemRelationship').value,
            note:         document.getElementById('itemNote').value,
        }, res => {
            if (res.ok) {
                closeAddItemModal();
                loadNode(currentNodeId);
                toast('Linked ✓');
                document.getElementById('kg-bottom-drawer').classList.add('open');
            } else {
                toast('Error: ' + res.error, 'error');
            }
        }, 'json');
    }
}

function removeLinkedItem(id) {
    $.post('kg_staging_api.php', {action:'remove_item', id}, res => {
        if (res.ok) { loadNode(currentNodeId); toast('Removed'); }
        else toast('Error','error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// CREATE FOLDER / NODE
// ═══════════════════════════════════════════════
function createFolder() {
    const name     = document.getElementById('folderName').value.trim();
    const parentId = document.getElementById('folderParent').value || null;
    if (!name) return;
    $.post('kg_staging_api.php', {action:'create_category', name, parent_id:parentId}, res => {
        if (res.ok) { hideModal('modalFolder'); document.getElementById('folderName').value=''; refreshTree(); toast('Folder created'); }
        else toast('Error: ' + res.error,'error');
    }, 'json');
}

function createNode() {
    const name     = document.getElementById('newNodeName').value.trim();
    const nodeType = document.getElementById('newNodeType').value;
    const catId    = document.getElementById('newNodeCategory').value ||
                     document.getElementById('currentFolderId').value || null;
    if (!name) return;
    $.post('kg_staging_api.php', {action:'create_node', name, node_type:nodeType, category_id:catId}, res => {
        if (res.ok) {
            hideModal('modalNode');
            document.getElementById('newNodeName').value = '';
            refreshTree();
            toast('Node created');
            setTimeout(() => loadNode(res.id), 400);
        } else toast('Error: ' + res.error,'error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// MODAL HELPERS
// ═══════════════════════════════════════════════
function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function hideModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.kg-modal-bg').forEach(bg => {
    bg.addEventListener('click', e => {
        if (e.target === bg) {
            bg.style.display = 'none';
            if (bg.id === 'modalAddItem') {
                document.getElementById('editingItemId').value = '';
            }
        }
    });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.kg-modal-bg').forEach(b => b.style.display='none');
        document.getElementById('editingItemId').value = '';
        closeCuratedModal();
        closeExportModal();
    }
});

// ═══════════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════════
let toastTimer;
function toast(msg, type='success') {
    const el = document.getElementById('kg-toast');
    el.textContent = msg;
    el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display='none', 2800);
}

// ═══════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════
function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ═══════════════════════════════════════════════
// EDIT SELECTED TREE ITEM
// ═══════════════════════════════════════════════
function editSelected() {
    if (!selectedTreeItem) return;
    if (selectedTreeItem.type === 'folder') {
        document.getElementById('editFolderName').value = selectedTreeItem.text;
        document.getElementById('editFolderId').value   = selectedTreeItem.db_id;
        showModal('modalEditFolder');
    } else {
        if (currentNodeId !== selectedTreeItem.db_id) {
            loadNode(selectedTreeItem.db_id);
            closeSidebar();
        } else {
            closeSidebar();
            document.getElementById('nodeNameInput').focus();
        }
    }
}

function saveEditFolder() {
    const id   = parseInt(document.getElementById('editFolderId').value);
    const name = document.getElementById('editFolderName').value.trim();
    if (!id || !name) return;
    $.post('kg_staging_api.php', {action:'rename_category', id, name}, res => {
        if (res.ok) { hideModal('modalEditFolder'); refreshTree(); toast('Folder renamed'); }
        else toast('Error: ' + (res.error||''), 'error');
    }, 'json');
}

// ═══════════════════════════════════════════════
// DEEP-LINK SUPPORT
// ═══════════════════════════════════════════════
(function checkDeepLink() {
    const params = new URLSearchParams(window.location.search);
    const nodeId = parseInt(params.get('node_id') || '0');
    if (nodeId) { setTimeout(() => loadNode(nodeId), 600); }
})();

// ═══════════════════════════════════════════════
// DOWNLOAD CURRENT NODE AS MD
// ═══════════════════════════════════════════════
function downloadNodeMd() {
    if (!editor || !currentNodeId) return;
    const name = document.getElementById('nodeNameInput').value || 'node-' + currentNodeId;
    const md   = editor.getMarkdown();
    const blob = new Blob([md], {type: 'text/markdown'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = name.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'') + '.md';
    a.click();
    URL.revokeObjectURL(url);
    toast('Downloaded ✓');
}

// ═══════════════════════════════════════════════
// EXPORT MODAL
// ═══════════════════════════════════════════════
let kgeCurrentHits  = [];
let kgeSelectedIds  = new Set();

// ── Picker tree state (Full Export tab) ─────────────────────
// Namespaced localStorage key so staging and live don't share open-folder state
let kgePickerRaw     = [];
let kgePickerChecked = new Set();

const KGE_PICKER_OPEN_KEY = 'kg_staging_picker_open_folders';

function kgePickerSaveOpenState() {
    const open = [];
    document.querySelectorAll('#kge-picker-tree-wrap .kge-tree-children.open').forEach(el => {
        open.push(el.id.replace('kge-kids-', ''));
    });
    try { localStorage.setItem(KGE_PICKER_OPEN_KEY, JSON.stringify(open)); } catch(e) {}
}

function kgePickerLoadOpenState() {
    try {
        const raw = localStorage.getItem(KGE_PICKER_OPEN_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch(e) {}
    return null;
}

// ── Promote picker state ─────────────────────────────────────
// Separate tree instance for the Promote tab — own raw data, own checked set,
// own localStorage key for open-folder state.
let kgePromoteRaw     = [];
let kgePromoteChecked = new Set();

const KGE_PROMOTE_OPEN_KEY = 'kg_staging_promote_open_folders';

function kgePromotePickerSaveOpenState() {
    const open = [];
    document.querySelectorAll('#kge-promote-picker-wrap .kge-tree-children.open').forEach(el => {
        open.push(el.id.replace('kge-promote-kids-', ''));
    });
    try { localStorage.setItem(KGE_PROMOTE_OPEN_KEY, JSON.stringify(open)); } catch(e) {}
}

function kgePromotePickerLoadOpenState() {
    try {
        const raw = localStorage.getItem(KGE_PROMOTE_OPEN_KEY);
        if (raw) return new Set(JSON.parse(raw));
    } catch(e) {}
    return null;
}

function openExportModal() {
    document.getElementById('kge-modal-bg').classList.add('open');
    kgeSetTab('promote');
    kgeLoadPromotePicker();
    kgeLoadPickerTree();
}

function closeExportModal() {
    document.getElementById('kge-modal-bg').classList.remove('open');
}

document.getElementById('kge-modal-bg').addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});

function kgeSetTab(tab) {
    document.getElementById('kge-tab-promote').classList.toggle('active',  tab === 'promote');
    document.getElementById('kge-tab-full').classList.toggle('active',     tab === 'full');
    document.getElementById('kge-tab-semantic').classList.toggle('active', tab === 'semantic');
    document.getElementById('kge-pane-promote').classList.toggle('active',  tab === 'promote');
    document.getElementById('kge-pane-full').classList.toggle('active',     tab === 'full');
    document.getElementById('kge-pane-semantic').classList.toggle('active', tab === 'semantic');
    if (tab === 'semantic') { setTimeout(() => document.getElementById('kge-query-input').focus(), 80); }
}

// ══════════════════════════════════════════════
// PROMOTE PICKER TREE
// ══════════════════════════════════════════════

function kgeLoadPromotePicker() {
    const wrap = document.getElementById('kge-promote-picker-wrap');
    wrap.innerHTML = '<div class="kge-picker-loading">Loading…</div>';
    fetch('kg_staging_api.php?action=fetch_tree')
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { wrap.innerHTML = '<div class="kge-picker-loading">Failed to load tree.</div>'; return; }
            kgePromoteRaw = res.tree;
            kgePromoteChecked = new Set(res.tree.map(n => n.id));
            kgeRenderPromotePicker();
            kgeUpdatePromoteCount();
        })
        .catch(() => { wrap.innerHTML = '<div class="kge-picker-loading">Error loading tree.</div>'; });
}

function kgeRenderPromotePicker() {
    const wrap = document.getElementById('kge-promote-picker-wrap');
    const childMap = {};
    kgePromoteRaw.forEach(n => {
        const p = n.parent || '#';
        if (!childMap[p]) childMap[p] = [];
        childMap[p].push(n);
    });
    wrap.innerHTML = kgeBuildPromoteLevel('#', childMap, 0);

    const savedOpen = kgePromotePickerLoadOpenState();
    wrap.querySelectorAll('.kge-tree-children').forEach(el => {
        const jsId = el.id.replace('kge-promote-kids-', '');
        const shouldOpen = savedOpen === null || savedOpen.has(jsId);
        el.classList.toggle('open', shouldOpen);
    });
    wrap.querySelectorAll('.kge-node-toggle').forEach(el => {
        const row  = el.closest('.kge-tree-node');
        const jsId = row ? row.dataset.jid : null;
        const kids = jsId ? document.getElementById('kge-promote-kids-' + jsId) : null;
        el.classList.toggle('open', kids ? kids.classList.contains('open') : false);
    });
}

function kgeBuildPromoteLevel(parentId, childMap, depth) {
    const children = childMap[parentId] || [];
    if (!children.length) return '';
    const indent = depth * 14;
    let html = '';
    children.forEach(node => {
        const isFolder = node.type === 'folder';
        const jsId     = node.id;
        const checked  = kgePromoteChecked.has(jsId);
        const hasKids  = !!(childMap[jsId] && childMap[jsId].length);
        const icon     = isFolder ? '📁' : kgeNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');

        const toggleBtn = (isFolder && hasKids)
            ? `<span class="kge-node-toggle open" onclick="kgePromoteToggleFolder('${jsId}', this)">▶</span>`
            : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

        html += `
        <div class="kge-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
             style="padding-left:${10 + indent}px;"
             data-jid="${jsId}">
            ${toggleBtn}
            <input type="checkbox" ${checked ? 'checked' : ''}
                   onchange="kgePromoteCheck('${jsId}', this.checked)">
            <span class="kge-node-icon">${icon}</span>
            <span class="kge-node-label">${escHtml(node.text)}</span>
        </div>`;

        if (hasKids) {
            html += `<div class="kge-tree-children open" id="kge-promote-kids-${jsId}">`;
            html += kgeBuildPromoteLevel(jsId, childMap, depth + 1);
            html += `</div>`;
        }
    });
    return html;
}

function kgePromoteToggleFolder(jsId, btn) {
    const kids = document.getElementById('kge-promote-kids-' + jsId);
    if (!kids) return;
    kids.classList.toggle('open');
    btn.classList.toggle('open');
    kgePromotePickerSaveOpenState();
}

function kgePromoteCheck(jsId, checked) {
    const ids = kgePromoteDescendants(jsId);
    ids.forEach(id => {
        if (checked) kgePromoteChecked.add(id);
        else         kgePromoteChecked.delete(id);
    });
    ids.forEach(id => {
        const el = document.querySelector(`#kge-promote-picker-wrap .kge-tree-node[data-jid="${id}"] input[type=checkbox]`);
        if (el) { el.checked = checked; el.indeterminate = false; }
    });
    kgePromoteSyncAncestors(jsId);
    kgeUpdatePromoteCount();
}

function kgePromoteDescendants(jsId) {
    const result = [jsId];
    const queue  = [jsId];
    while (queue.length) {
        const cur = queue.shift();
        kgePromoteRaw.filter(n => n.parent === cur).forEach(n => {
            result.push(n.id);
            queue.push(n.id);
        });
    }
    return result;
}

function kgePromoteSyncAncestors(jsId) {
    const node = kgePromoteRaw.find(n => n.id === jsId);
    if (!node || !node.parent || node.parent === '#') return;
    const parentJid  = node.parent;
    const siblings   = kgePromoteRaw.filter(n => n.parent === parentJid);
    const allChecked  = siblings.every(s => kgePromoteChecked.has(s.id));
    const noneChecked = siblings.every(s => !kgePromoteChecked.has(s.id));
    const el = document.querySelector(`#kge-promote-picker-wrap .kge-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);
    if (el) {
        if (allChecked) {
            el.checked = true; el.indeterminate = false;
            kgePromoteChecked.add(parentJid);
        } else if (noneChecked) {
            el.checked = false; el.indeterminate = false;
            kgePromoteChecked.delete(parentJid);
        } else {
            el.checked = false; el.indeterminate = true;
            kgePromoteChecked.delete(parentJid);
        }
    }
    kgePromoteSyncAncestors(parentJid);
}

function kgePromotePickerToggleAll() {
    const allChecked = kgePromoteRaw.every(n => kgePromoteChecked.has(n.id));
    if (allChecked) {
        kgePromoteChecked.clear();
    } else {
        kgePromoteRaw.forEach(n => kgePromoteChecked.add(n.id));
    }
    kgeRenderPromotePicker();
    kgeUpdatePromoteCount();
}

function kgeUpdatePromoteCount() {
    const nodeCount = kgePromoteRaw.filter(n => n.type === 'node' && kgePromoteChecked.has(n.id)).length;
    const total     = kgePromoteRaw.filter(n => n.type === 'node').length;
    const el = document.getElementById('kge-promote-count');
    const btn = document.getElementById('kge-promote-btn');
    if (el) {
        el.textContent = nodeCount === total
            ? `All ${total} nodes selected`
            : `${nodeCount} of ${total} nodes selected`;
    }
    if (btn) btn.disabled = nodeCount === 0;
}

function kgeGetPromoteNodeIds() {
    return kgePromoteRaw
        .filter(n => n.type === 'node' && kgePromoteChecked.has(n.id))
        .map(n => n.data.db_id);
}

// ── Execute Promote ──────────────────────────
async function kgeDoPromote() {
    const nodeIds   = kgeGetPromoteNodeIds();
    if (!nodeIds.length) { toast('No nodes selected', 'error'); return; }

    const withEdges  = document.getElementById('kge-promote-with-edges').checked;
    const overwrite  = document.getElementById('kge-promote-overwrite').checked;
    const btn        = document.getElementById('kge-promote-btn');
    const resultEl   = document.getElementById('kge-promote-result');

    btn.disabled    = true;
    btn.textContent = '⏳ Promoting…';
    resultEl.className = 'kge-promote-result';
    resultEl.style.display = 'none';

    try {
        const res = await fetch('kg_staging_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action:     'promote_nodes',
                node_ids:   nodeIds,
                with_edges: withEdges,
                overwrite:  overwrite,
            }),
        });
        const data = await res.json();

        if (!data.ok) throw new Error(data.error || 'Promote failed');

        resultEl.className = 'kge-promote-result success';
        resultEl.innerHTML =
            `✅ <strong>${data.promoted_nodes} node${data.promoted_nodes !== 1 ? 's' : ''} promoted</strong>` +
            (data.promoted_edges ? ` · ${data.promoted_edges} edges copied` : '') +
            (data.skipped ? ` · ${data.skipped} skipped` : '') +
            `.<br><span style="font-size:0.78rem; color:var(--text-muted);">The staging nodes have not been removed.</span>`;

        toast(`Promoted ${data.promoted_nodes} node${data.promoted_nodes !== 1 ? 's' : ''} to live ✓`);

    } catch(e) {
        resultEl.className = 'kge-promote-result error';
        resultEl.textContent = '⚠️ ' + e.message;
        toast('Promote failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '&#x1F680; Promote to Live';
        kgeUpdatePromoteCount();
    }
}

// ══════════════════════════════════════════════
// PICKER TREE (Full Export tab)
// ══════════════════════════════════════════════

function kgeLoadPickerTree() {
    const wrap = document.getElementById('kge-picker-tree-wrap');
    wrap.innerHTML = '<div class="kge-picker-loading">Loading…</div>';
    fetch('kg_staging_api.php?action=fetch_tree')
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { wrap.innerHTML = '<div class="kge-picker-loading">Failed to load tree.</div>'; return; }
            kgePickerRaw = res.tree;
            kgePickerChecked = new Set(res.tree.map(n => n.id));
            kgeRenderPickerTree();
            kgeUpdatePickerCount();
        })
        .catch(() => { wrap.innerHTML = '<div class="kge-picker-loading">Error loading tree.</div>'; });
}

function kgeRenderPickerTree() {
    const wrap = document.getElementById('kge-picker-tree-wrap');
    const childMap = {};
    kgePickerRaw.forEach(n => {
        const p = n.parent || '#';
        if (!childMap[p]) childMap[p] = [];
        childMap[p].push(n);
    });
    wrap.innerHTML = kgeBuildPickerLevel('#', childMap, 0);
    const savedOpen = kgePickerLoadOpenState();
    wrap.querySelectorAll('.kge-tree-children').forEach(el => {
        const jsId = el.id.replace('kge-kids-', '');
        const shouldOpen = savedOpen === null || savedOpen.has(jsId);
        el.classList.toggle('open', shouldOpen);
    });
    wrap.querySelectorAll('.kge-node-toggle').forEach(el => {
        const row  = el.closest('.kge-tree-node');
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
        const isFolder = node.type === 'folder';
        const jsId     = node.id;
        const checked  = kgePickerChecked.has(jsId);
        const hasKids  = !!(childMap[jsId] && childMap[jsId].length);
        const icon     = isFolder ? '📁' : kgeNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');

        const toggleBtn = (isFolder && hasKids)
            ? `<span class="kge-node-toggle open" onclick="kgePickerToggleFolder('${jsId}', this)">▶</span>`
            : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

        html += `
        <div class="kge-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
             style="padding-left:${10 + indent}px;"
             data-jid="${jsId}">
            ${toggleBtn}
            <input type="checkbox" ${checked ? 'checked' : ''}
                   onchange="kgePickerCheck('${jsId}', this.checked)">
            <span class="kge-node-icon">${icon}</span>
            <span class="kge-node-label">${escHtml(node.text)}</span>
        </div>`;

        if (hasKids) {
            html += `<div class="kge-tree-children open" id="kge-kids-${jsId}">`;
            html += kgeBuildPickerLevel(jsId, childMap, depth + 1);
            html += `</div>`;
        }
    });
    return html;
}

function kgeNodeIcon(type) {
    const map = {
        relationship:'🔗', character:'👤', location:'📍',
        event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝'
    };
    return map[type] || '📝';
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
    ids.forEach(id => {
        if (checked) kgePickerChecked.add(id);
        else         kgePickerChecked.delete(id);
    });
    ids.forEach(id => {
        const el = document.querySelector(`#kge-picker-tree-wrap .kge-tree-node[data-jid="${id}"] input[type=checkbox]`);
        if (el) { el.checked = checked; el.indeterminate = false; }
    });
    kgePickerSyncAncestors(jsId);
    kgeUpdatePickerCount();
}

function kgePickerDescendants(jsId) {
    const result = [jsId];
    const queue  = [jsId];
    while (queue.length) {
        const cur = queue.shift();
        kgePickerRaw.filter(n => n.parent === cur).forEach(n => {
            result.push(n.id);
            queue.push(n.id);
        });
    }
    return result;
}

function kgePickerSyncAncestors(jsId) {
    const node = kgePickerRaw.find(n => n.id === jsId);
    if (!node || !node.parent || node.parent === '#') return;
    const parentJid = node.parent;
    const siblings  = kgePickerRaw.filter(n => n.parent === parentJid);
    const allChecked  = siblings.every(s => kgePickerChecked.has(s.id));
    const noneChecked = siblings.every(s => !kgePickerChecked.has(s.id));
    const el = document.querySelector(`#kge-picker-tree-wrap .kge-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);
    if (el) {
        if (allChecked) {
            el.checked = true; el.indeterminate = false;
            kgePickerChecked.add(parentJid);
        } else if (noneChecked) {
            el.checked = false; el.indeterminate = false;
            kgePickerChecked.delete(parentJid);
        } else {
            el.checked = false; el.indeterminate = true;
            kgePickerChecked.delete(parentJid);
        }
    }
    kgePickerSyncAncestors(parentJid);
}

function kgePickerToggleAll() {
    const allChecked = kgePickerRaw.every(n => kgePickerChecked.has(n.id));
    if (allChecked) {
        kgePickerChecked.clear();
    } else {
        kgePickerRaw.forEach(n => kgePickerChecked.add(n.id));
    }
    kgeRenderPickerTree();
    kgeUpdatePickerCount();
}

function kgeUpdatePickerCount() {
    const nodeCount = kgePickerRaw.filter(n => n.type === 'node' && kgePickerChecked.has(n.id)).length;
    const total     = kgePickerRaw.filter(n => n.type === 'node').length;
    const el = document.getElementById('kge-picker-count');
    if (el) {
        if (nodeCount === total) {
            el.textContent = `All ${total} nodes selected`;
        } else {
            el.textContent = `${nodeCount} of ${total} nodes selected`;
        }
    }
}

function kgeGetPickerNodeIds() {
    const allNodeIds = kgePickerRaw.filter(n => n.type === 'node').map(n => n.data.db_id);
    const selNodeIds = kgePickerRaw
        .filter(n => n.type === 'node' && kgePickerChecked.has(n.id))
        .map(n => n.data.db_id);
    if (selNodeIds.length === allNodeIds.length) return null;
    return selNodeIds;
}

// ── Full export ──────────────────────────────
async function kgeDoFullExport() {
    const withContent = document.getElementById('kge-full-with-content').checked;
    const withEdges   = document.getElementById('kge-full-with-edges').checked;
    const btn         = document.getElementById('kge-full-export-btn');
    btn.disabled      = true;
    btn.textContent   = '⏳ Building…';

    const nodeIds = kgeGetPickerNodeIds();

    try {
        if (nodeIds === null && !withContent) {
            const res  = await fetch(`kg_staging_api.php?action=export_snapshot&with_edges=${withEdges ? 1 : 0}`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Export failed');
            const snap = data.snapshot;
            if (!withEdges) snap.edges = { fields: snap.edges.fields, rows: [] };
            kgeTriggerDownload(snap, 'kg_staging_full_' + kgeDate() + '.json');
            toast('Full export downloaded ✓');
        } else {
            let ids = nodeIds;
            if (ids === null) {
                ids = kgePickerRaw.filter(n => n.type === 'node').map(n => n.data.db_id);
            }
            if (!ids.length) { toast('No nodes selected', 'error'); return; }
            const res  = await fetch('kg_staging_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'focused_snapshot', node_ids: ids, with_content: withContent, with_edges: withEdges }),
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Snapshot failed');
            const suffix = withContent ? '_content' : '';
            kgeTriggerDownload(data.snapshot, `kg_staging_export${suffix}_${kgeDate()}.json`);
            toast(`Exported ${ids.length} nodes ✓`);
        }
        closeExportModal();
    } catch(e) {
        toast('Export failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '&#x1F4E5; Export';
    }
}

// ── Semantic query ───────────────────────────
async function kgeRunQuery() {
    const query = document.getElementById('kge-query-input').value.trim();
    if (!query) return;

    const nResults   = parseInt(document.getElementById('kge-n-select').value);
    const loadingBar = document.getElementById('kge-loading-bar');
    const searchBtn  = document.getElementById('kge-search-btn');
    const hitsArea   = document.getElementById('kge-hits-area');

    loadingBar.style.display = 'block';
    searchBtn.disabled = true;
    searchBtn.textContent = '…';

    kgeCurrentHits = [];
    kgeSelectedIds = new Set();
    hitsArea.innerHTML = '';

    try {
        const res = await fetch('kg_staging_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'semantic_query', query, n_results: nResults}),
        });
        const data = await res.json();

        if (!data.ok) {
            hitsArea.innerHTML = `
                <div class="kge-hits-empty">
                    <span style="font-size:2rem">⚠️</span>
                    <span>Search failed</span>
                    <span class="hint">${escHtml(data.error || 'Unknown error')}</span>
                </div>`;
            toast('Search failed: ' + (data.error || ''), 'error');
            return;
        }

        kgeCurrentHits = data.hits || [];
        if (!kgeCurrentHits.length) {
            hitsArea.innerHTML = `
                <div class="kge-hits-empty">
                    <span style="font-size:2rem">🔍</span>
                    <span>No matching nodes found</span>
                    <span class="hint">Try a broader or differently phrased query.</span>
                </div>`;
            return;
        }

        kgeSelectedIds = new Set(kgeCurrentHits.filter(h => h.score > 0.35).map(h => h.node_id));
        kgeRenderHits(kgeCurrentHits);
        kgeUpdateSelCount();

    } catch(e) {
        hitsArea.innerHTML = `
            <div class="kge-hits-empty">
                <span style="font-size:2rem">⚠️</span>
                <span>Request error</span>
                <span class="hint">${escHtml(e.message)}</span>
            </div>`;
        toast('Search error: ' + e.message, 'error');
    } finally {
        loadingBar.style.display = 'none';
        searchBtn.disabled = false;
        searchBtn.textContent = 'Search';
    }
}

function kgeRenderHits(hits) {
    const area = document.getElementById('kge-hits-area');
    if (!hits.length) { area.innerHTML = ''; return; }

    const maxScore = hits[0]?.score || 1;
    const header = `
        <div class="kge-hits-header">
            <span>${hits.length} node${hits.length !== 1 ? 's' : ''} ranked by relevance</span>
            <button class="kge-select-all-btn" onclick="kgeToggleAll()">Toggle all</button>
        </div>`;

    const rows = hits.map(hit => {
        const checked  = kgeSelectedIds.has(hit.node_id) ? 'checked' : '';
        const selClass = kgeSelectedIds.has(hit.node_id) ? 'selected' : '';
        const barW     = Math.max(8, Math.round((hit.score / maxScore) * 60));
        const typeKey  = (hit.node_type || 'note').toLowerCase();
        const dotClass = `kge-dot-${hit.content_status}`;
        const excerpt  = escHtml(hit.excerpt || '').replace(/^Node:[^\n]*\n?Type:[^\n]*\n?/, '').trim();

        return `
        <div class="kge-hit-row ${selClass}" data-node-id="${hit.node_id}" onclick="kgeToggleHit(${hit.node_id}, this)">
            <input type="checkbox" class="kge-hit-check" ${checked}
                   onclick="event.stopPropagation(); kgeToggleHit(${hit.node_id}, this.closest('.kge-hit-row'))">
            <span class="kge-status-dot ${dotClass}" title="${hit.content_status}"></span>
            <div class="kge-hit-body">
                <div class="kge-hit-name">
                    ${escHtml(hit.name)}
                    <span class="kge-type-pill kge-pill-${typeKey}">${typeKey}</span>
                    ${hit.category_name ? `<span style="font-size:0.72rem;color:var(--text-muted);font-weight:400">${escHtml(hit.category_name)}</span>` : ''}
                    <span class="kge-score-bar" style="width:${barW}px"></span>
                </div>
                ${excerpt ? `<div class="kge-hit-excerpt">${excerpt}</div>` : ''}
            </div>
            <span class="kge-hit-score">${(hit.score * 100).toFixed(0)}%</span>
        </div>`;
    }).join('');

    area.innerHTML = header + rows;
}

function kgeToggleHit(nodeId, rowEl) {
    if (kgeSelectedIds.has(nodeId)) {
        kgeSelectedIds.delete(nodeId);
        rowEl.classList.remove('selected');
        rowEl.querySelector('input[type=checkbox]').checked = false;
    } else {
        kgeSelectedIds.add(nodeId);
        rowEl.classList.add('selected');
        rowEl.querySelector('input[type=checkbox]').checked = true;
    }
    kgeUpdateSelCount();
}

function kgeToggleAll() {
    const allSelected = kgeCurrentHits.every(h => kgeSelectedIds.has(h.node_id));
    if (allSelected) { kgeSelectedIds.clear(); }
    else { kgeCurrentHits.forEach(h => kgeSelectedIds.add(h.node_id)); }
    kgeRenderHits(kgeCurrentHits);
    kgeUpdateSelCount();
}

function kgeUpdateSelCount() {
    const n   = kgeSelectedIds.size;
    const btn = document.getElementById('kge-export-sel-btn');
    document.getElementById('kge-sel-count').textContent =
        n ? `${n} node${n !== 1 ? 's' : ''} selected` : 'No nodes selected';
    btn.disabled = n === 0;
}

// ── Focused export (semantic tab) ────────────
async function kgeDoFocusedExport() {
    if (!kgeSelectedIds.size) return;
    const withContent = document.getElementById('kge-sem-with-content').checked;
    const btn         = document.getElementById('kge-export-sel-btn');
    btn.disabled      = true;
    btn.textContent   = '⏳ Building…';

    try {
        const res = await fetch('kg_staging_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'focused_snapshot', node_ids: Array.from(kgeSelectedIds), with_content: withContent, with_edges: true }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Snapshot failed');
        const suffix = withContent ? '_content' : '';
        kgeTriggerDownload(data.snapshot, `kg_staging_semantic${suffix}_${kgeDate()}.json`);
        toast(`Exported ${kgeSelectedIds.size} nodes ✓`);
        closeExportModal();
    } catch(e) {
        toast('Export failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '&#x1F4E5; Export Selected';
        kgeUpdateSelCount();
    }
}

// ── Helpers ──────────────────────────────────
function kgeTriggerDownload(obj, filename) {
    const blob = new Blob([JSON.stringify(obj, null, 2)], {type: 'application/json'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
}

function kgeDate() {
    return new Date().toISOString().slice(0, 10);
}

// ═══════════════════════════════════════════════
// LEGACY exportSnapshot
// ═══════════════════════════════════════════════
function exportSnapshot() { openExportModal(); }

function importMdDocEntities() { window.location.href = 'view_kg_staging_import.php'; }

// ═══════════════════════════════════════════════
// KEYBOARD SHORTCUTS
// ═══════════════════════════════════════════════
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        if (currentNodeId) saveNode();
    }
});
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
