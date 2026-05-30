<?php
// public/view_editorial_shot.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();

$sceneId = (int)($_GET['scene_id'] ?? 0);
$pageTitle = "Editorial: Scene Shots";
ob_start();

require_once __DIR__ . '/modal_video_details.php';
require_once __DIR__ . '/modal_frame_details.php'; 
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── FORGE DESIGN TOKENS ── */
:root {
    --forge-bg:          #080b10;
    --forge-surface:     #0e1319;
    --forge-card:        #111820;
    --forge-card-hover:  #141e28;
    --forge-border:      #1c2535;
    --forge-border-glow: #2a3a52;
    --forge-text:        #c8d4e8;
    --forge-text-dim:    #5a6a80;
    --forge-text-bright: #e8f0ff;
    --forge-amber:       #f5a623;
    --forge-amber-dim:   rgba(245,166,35,0.08);
    --forge-amber-mid:   rgba(245,166,35,0.15);
    --forge-amber-glow:  rgba(245,166,35,0.4);
    --forge-red:         #f05060;
    --forge-red-dim:     rgba(240,80,96,0.1);
    --forge-green:       #22d3a0;
    --forge-green-dim:   rgba(34,211,160,0.1);
    --mono: 'Space Mono', 'Fira Mono', monospace;
    --sans: 'Syne', system-ui, sans-serif;
    --forge-radius: 6px;
}
[data-theme="light"], html[data-theme="light"] {
    --forge-bg:          #f6f8fa;
    --forge-surface:     #e1e4e8;
    --forge-card:        #ffffff;
    --forge-card-hover:  #f3f4f6;
    --forge-border:      #d1d5db;
    --forge-border-glow: #9ca3af;
    --forge-text:        #111827;
    --forge-text-dim:    #4b5563;
    --forge-text-bright: #000000;
    --forge-amber:       #d97706;
    --forge-amber-dim:   rgba(217,119,6,0.1);
    --forge-amber-mid:   rgba(217,119,6,0.2);
    --forge-amber-glow:  rgba(217,119,6,0.4);
    --forge-red:         #dc2626;
    --forge-red-dim:     rgba(220,38,38,0.1);
    --forge-green:       #059669;
    --forge-green-dim:   rgba(5,150,105,0.1);
}

/* ── PAGE ── */
.view-wrap {
    padding: 10px;
    font-family: var(--sans);
    color: var(--forge-text);
}

/* ── FORGE HEADER BAR ── */
.forge-header-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.forge-logo {
    font-family: var(--mono);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--forge-amber);
    letter-spacing: 2px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 1;
    min-width: 0;
    overflow: hidden;
}
.forge-logo-icon {
    width: 26px; height: 26px;
    background: var(--forge-amber-mid);
    border: 1px solid var(--forge-amber-glow);
    border-radius: var(--forge-radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}
.forge-logo span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.forge-breadcrumb {
    font-family: var(--mono);
    font-size: 0.7rem;
    color: var(--forge-text-dim);
    display: flex;
    align-items: center;
    gap: 5px;
    flex: 1;
    min-width: 0;
    flex-wrap: wrap;
}
.forge-breadcrumb a {
    color: var(--forge-text-dim);
    text-decoration: none;
    transition: color 0.15s;
}
.forge-breadcrumb a:hover { color: var(--forge-amber); }
.forge-breadcrumb .sep { opacity: 0.4; }

/* ── TOOLBAR STRIP ── */
.forge-toolbar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.forge-tool-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.72rem;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.forge-tool-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); background: var(--forge-amber-dim); }
.forge-tool-btn:disabled { opacity: 0.5; cursor: default; }
.forge-tool-btn.primary {
    background: var(--forge-amber);
    color: #000;
    border-color: var(--forge-amber);
    font-weight: 700;
}
.forge-tool-btn.primary:hover { filter: brightness(1.1); color: #000; background: var(--forge-amber); }
.forge-tool-btn.accent {
    background: var(--forge-green-dim);
    color: var(--forge-green);
    border-color: var(--forge-green);
    font-weight: 700;
}
.forge-tool-btn.accent:hover { filter: brightness(1.1); }

/* ── SHOT GRID ── */
.shot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    margin-top: 0;
}

/* ── SHOT CARD ── */
.shot-card {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    padding: 5px;
    position: relative;
    transition: border-color 0.2s, background 0.2s;
}
.shot-card:hover {
    border-color: var(--forge-border-glow);
    background: var(--forge-card-hover);
}

.shot-thumb-wrap {
    position: relative;
    width: 100%;
    padding-top: 56.25%;
    background: #000;
    border-radius: calc(var(--forge-radius) - 1px);
    overflow: hidden;
    cursor: pointer;
}
.shot-thumb-wrap:hover { opacity: 0.88; }
.shot-thumb {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
}
.shot-dur {
    position: absolute; bottom: 4px; right: 4px;
    background: rgba(0,0,0,0.75);
    color: #fff;
    font-family: var(--mono);
    font-size: 0.62rem;
    padding: 1px 5px;
    border-radius: 3px;
}
.shot-play-icon {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    color: rgba(255,255,255,0.85);
    font-size: 22px;
    pointer-events: none;
    text-shadow: 0 2px 6px rgba(0,0,0,0.6);
    display: none;
}
.shot-thumb-wrap:hover .shot-play-icon { display: block; }

.shot-meta {
    margin-top: 5px;
    font-family: var(--mono);
    font-size: 0.65rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 4px;
}
.shot-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    color: var(--forge-text);
    font-weight: 600;
}

/* drag handle */
.handle {
    cursor: grab;
    padding: 3px 5px;
    border-radius: 3px;
    border: 1px solid var(--forge-border);
    color: var(--forge-text-dim);
    font-size: 0.9rem;
    line-height: 1;
    user-select: none;
    display: flex;
    align-items: center;
    background: transparent;
    transition: all 0.15s;
}
.handle:hover { border-color: var(--forge-border-glow); color: var(--forge-text); }
.handle:active { cursor: grabbing; }

/* animatic button */
.btn-animatic-shot {
    border: none;
    background: none;
    cursor: pointer;
    padding: 2px 3px;
    font-size: 0.9rem;
    opacity: 0.75;
    line-height: 1;
    transition: opacity 0.15s;
}
.btn-animatic-shot:hover { opacity: 1; }
.btn-animatic-shot:disabled { opacity: 0.2; cursor: default; }

/* delete button */
.btn-del-shot {
    padding: 3px 6px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: 3px;
    color: var(--forge-text-dim);
    font-size: 0.65rem;
    cursor: pointer;
    line-height: 1;
    transition: all 0.15s;
}
.btn-del-shot:hover { border-color: var(--forge-red); color: var(--forge-red); background: var(--forge-red-dim); }

/* ── VIDEO PICKER MODAL — preserved exactly, only outer shell gets forge chrome ── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(3px); }
.modal-overlay.active { display: flex; }
.picker-box { background: var(--card, #1a1a2e); width: 95%; max-width: 1100px; height: 88vh; border-radius: 8px; display: flex; flex-direction: column; position: relative; overflow: hidden; border: 1px solid var(--forge-border-glow); }
.picker-head {
    padding: 10px 14px;
    padding-left: 115px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    position: relative;
    background: var(--forge-surface);
}
.picker-head h3 {
    margin: 0; margin-right: 10px;
    font-family: var(--mono);
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--forge-amber);
    letter-spacing: 1.5px;
    text-transform: uppercase;
}
.picker-head-close {
    margin-left: auto;
    padding: 5px 12px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.72rem;
    cursor: pointer;
    transition: all 0.15s;
}
.picker-head-close:hover { border-color: var(--forge-red); color: var(--forge-red); background: var(--forge-red-dim); }

.picker-body { flex: 1; overflow: hidden; display: flex; min-height: 0; position: relative; }

/* Hamburger button — visible on mobile only */
.picker-tree-toggle {
    position: absolute;
    left: 65px;
    top: 50%;
    transform: translateY(-50%);
    width: 36px; height: 36px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 5px;
    color: var(--text, #eee);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    -webkit-tap-highlight-color: transparent;
    z-index: 10;
}
.picker-tree-toggle:active { background: rgba(255,255,255,0.1); }
@media (min-width: 700px) {
    .picker-tree-toggle { display: none; }
}

/* Tree flyout backdrop — mobile only */
.picker-tree-backdrop {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 20;
}
.picker-tree-backdrop.active { display: block; }
@media (min-width: 700px) {
    .picker-tree-backdrop { display: none !important; }
}

/* Tree panel */
.picker-tree-panel {
    width: 240px;
    flex-shrink: 0;
    border-right: 1px solid rgba(255,255,255,0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Mobile: flyout overlay, hidden off-screen left */
@media (max-width: 699px) {
    .picker-tree-panel {
        position: absolute;
        top: 0; left: 0; bottom: 0;
        z-index: 30;
        background: var(--card, #1a1a2e);
        border-right: 1px solid rgba(255,255,255,0.15);
        box-shadow: 4px 0 20px rgba(0,0,0,0.5);
        transform: translateX(-100%);
        transition: transform 0.22s ease;
        width: 80%;
        max-width: 280px;
    }
    .picker-tree-panel.open {
        transform: translateX(0);
    }
}
.picker-tree-header {
    padding: 8px 10px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    flex-shrink: 0;
}
.picker-tree-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 6px 4px;
    background: var(--bg);
}
.picker-tree-scroll::-webkit-scrollbar { width: 3px; }
.picker-tree-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }

.picker-tree-clear {
    font-size: 0.7rem;
    padding: 2px 6px;
    border: 1px solid rgba(255,255,255,0.15);
    background: transparent;
    color: var(--text-muted);
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
}
.picker-tree-clear:hover { border-color: var(--accent); color: var(--accent); }

/* jsTree dark overrides inside picker */
.picker-tree-scroll .jstree-default .jstree-anchor { color: var(--text) !important; line-height: 26px; height: 26px; }
.picker-tree-scroll .jstree-default .jstree-hovered { background: rgba(108,99,255,0.12) !important; border-radius: 3px; }
.picker-tree-scroll .jstree-default .jstree-clicked { background: rgba(108,99,255,0.25) !important; color: var(--accent) !important; border-radius: 3px; }
.picker-tree-scroll .jstree-default { background: transparent !important; }

/* Videos panel */
.picker-videos-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}
.picker-search-bar {
    padding: 8px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}
.picker-videos-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
}
.picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.picker-footer { padding: 10px 15px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; gap: 8px; }
.picker-page-jump { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text-muted); }
.picker-page-input { width: 48px; text-align: center; padding: 3px 6px; background: var(--bg); border: 1px solid rgba(255,255,255,0.15); color: var(--text); border-radius: 4px; font-size: 0.85rem; font-family: inherit; -moz-appearance: textfield; }
.picker-page-input::-webkit-outer-spin-button, .picker-page-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.picker-page-input:focus { outline: none; border-color: var(--accent); }

.vid-item { 
    cursor: pointer; border: 2px solid transparent; border-radius: 6px; overflow: hidden; background: var(--bg);
    transition: transform 0.15s; position: relative;
}
.vid-item:hover { border-color: var(--accent); transform: scale(1.02); }
.vid-item-btns {
    position: absolute; bottom: 36px; right: 4px;
    display: flex; flex-direction: column; gap: 4px;
    opacity: 0; transition: opacity 0.15s; pointer-events: none;
}
.vid-item:hover .vid-item-btns { opacity: 1; pointer-events: auto; }
@media (hover: none) {
    .vid-item-btns { opacity: 1; pointer-events: auto; }
}
.vid-item-btn {
    width: 28px; height: 28px;
    border-radius: 4px; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; line-height: 1;
    -webkit-tap-highlight-color: transparent;
}
.vid-item-btn-preview { background: rgba(108,99,255,0.85); color: #fff; }
.vid-item-btn-preview:active { background: rgba(108,99,255,1); }
.vid-item-btn-add    { background: rgba(34,197,94,0.85);  color: #fff; }
.vid-item-btn-add:active    { background: rgba(34,197,94,1); }
.vid-img { width: 100%; height: 90px; object-fit: cover; background: #000; }
.vid-info { padding: 6px; }
.vid-name { font-size: 0.85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vid-sub { font-size: 0.75rem; color: var(--text-muted); }

/* Animatic indicator on picker item */
.vid-animatic-badge {
    display: inline-block;
    font-size: 0.65rem;
    margin-left: 4px;
    opacity: 0.7;
    vertical-align: middle;
}

/* Mode toggle buttons inside tree panel header */
.picker-mode-btns {
    display: flex;
    gap: 3px;
    flex-shrink: 0;
}
.picker-mode-btn {
    padding: 2px 7px;
    font-size: 0.68rem;
    font-weight: 600;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.15);
    background: transparent;
    color: var(--text-muted, #888);
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    -webkit-tap-highlight-color: transparent;
}
.picker-mode-btn.active {
    background: rgba(108,99,255,0.18);
    border-color: var(--accent, #6c63ff);
    color: var(--accent, #6c63ff);
}
.picker-mode-btn:active { opacity: 0.7; }

/* Sequence selector panel */
.picker-seq-panel {
    flex: 1;
    overflow-y: auto;
    padding: 8px 6px;
    background: var(--bg);
    display: none;
}
.picker-seq-panel.active { display: block; }
.picker-seq-item {
    padding: 8px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.82rem;
    color: var(--text, #eee);
    border: 1px solid transparent;
    margin-bottom: 3px;
    line-height: 1.3;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.1s, border-color 0.1s;
}
.picker-seq-item:hover  { background: rgba(108,99,255,0.1); border-color: rgba(108,99,255,0.2); }
.picker-seq-item.active { background: rgba(108,99,255,0.2); border-color: var(--accent, #6c63ff); color: var(--accent, #6c63ff); }
.picker-seq-item .seq-item-id { font-size: 0.65rem; color: var(--text-muted, #888); font-family: monospace; margin-bottom: 2px; }
.picker-seq-item .seq-item-name { font-weight: 600; }
.picker-seq-item .seq-item-desc { font-size: 0.72rem; color: var(--text-muted, #888); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Fuzz candidate selector panel */
.picker-fuzz-panel {
    flex: 1;
    overflow-y: auto;
    padding: 8px 6px;
    background: var(--bg);
    display: none;
}
.picker-fuzz-panel.active { display: block; }
.picker-fuzz-search {
    padding: 4px 6px 6px;
    flex-shrink: 0;
}
.picker-fuzz-search-input {
    width: 100%;
    padding: 5px 8px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    color: var(--text, #eee);
    font-size: 0.75rem;
    font-family: inherit;
}
.picker-fuzz-search-input:focus { outline: none; border-color: var(--accent, #6c63ff); }
.picker-fuzz-item {
    padding: 7px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--text, #eee);
    border: 1px solid transparent;
    margin-bottom: 3px;
    line-height: 1.3;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.1s, border-color 0.1s;
}
.picker-fuzz-item:hover  { background: rgba(108,99,255,0.1); border-color: rgba(108,99,255,0.2); }
.picker-fuzz-item.active { background: rgba(108,99,255,0.2); border-color: var(--accent, #6c63ff); color: var(--accent, #6c63ff); }
.picker-fuzz-item .fuzz-item-id   { font-size: 0.62rem; color: var(--text-muted, #888); font-family: monospace; margin-bottom: 1px; }
.picker-fuzz-item .fuzz-item-name { font-weight: 600; }
.picker-fuzz-item .fuzz-item-type { font-size: 0.65rem; color: var(--text-muted, #888); margin-top: 1px; }
.picker-fuzz-item .fuzz-item-status {
    display: inline-block; padding: 1px 4px; border-radius: 2px;
    font-size: 0.57rem; font-family: monospace; text-transform: uppercase;
    letter-spacing: 0.5px; margin-top: 2px;
}
.fuzz-item-status.promoted  { color: #6c63ff; background: rgba(108,99,255,0.12); border: 1px solid rgba(108,99,255,0.25); }
.fuzz-item-status.canonized { color: #00e5a0; background: rgba(0,229,160,0.10); border: 1px solid rgba(0,229,160,0.25); }

/* Picker Pagination */
.picker-fuzz-pg {
    flex-shrink: 0;
    border-top: 1px solid rgba(255,255,255,0.07);
    padding: 5px 6px;
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--bg);
}
.picker-fuzz-pg-btn {
    width: 28px; height: 28px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    color: var(--text-muted, #888);
    font-size: 0.9rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
}
.picker-fuzz-pg-btn:disabled { opacity: 0.3; pointer-events: none; }
.picker-fuzz-pg-input {
    width: 34px; text-align: center;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    color: var(--accent, #6c63ff);
    font-family: monospace; font-size: 0.75rem; font-weight: 700;
    padding: 3px 2px; height: 28px;
    -moz-appearance: textfield;
}
.picker-fuzz-pg-input::-webkit-outer-spin-button, .picker-fuzz-pg-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.picker-fuzz-pg-input:focus { outline: none; border-color: var(--accent, #6c63ff); }
.picker-fuzz-pg-of { font-size: 0.6rem; color: var(--text-muted, #888); white-space: nowrap; flex: 1; text-align: center; }

/* Storyboard selector panel */
.picker-storyboard-panel {
    flex: 1;
    overflow-y: auto;
    padding: 8px 6px;
    background: var(--bg);
    display: none;
}
.picker-storyboard-panel.active { display: block; }
.picker-storyboard-item {
    padding: 8px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.82rem;
    color: var(--text, #eee);
    border: 1px solid transparent;
    margin-bottom: 3px;
    line-height: 1.3;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.1s, border-color 0.1s;
}
.picker-storyboard-item:hover  { background: rgba(108,99,255,0.1); border-color: rgba(108,99,255,0.2); }
.picker-storyboard-item.active { background: rgba(108,99,255,0.2); border-color: var(--accent, #6c63ff); color: var(--accent, #6c63ff); }
.picker-storyboard-item .sb-item-id { font-size: 0.65rem; color: var(--text-muted, #888); font-family: monospace; margin-bottom: 2px; }
.picker-storyboard-item .sb-item-name { font-weight: 600; }
.picker-storyboard-item .sb-item-desc { font-size: 0.72rem; color: var(--text-muted, #888); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.form-control { 
    padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; background: var(--bg); color: var(--text); font-size: 0.9rem;
}
</style>

<div class="view-wrap">

    <!-- FORGE HEADER -->
    <div class="forge-header-bar">
        <div class="forge-logo">
            <div class="forge-logo-icon">🎬</div>
            <span id="scene-name">Scene …</span>
        </div>
        <div class="forge-breadcrumb" id="breadcrumbs">…</div>
    </div>

    <!-- TOOLBAR -->
    <div class="forge-toolbar">
        <button class="forge-tool-btn" id="btn-save-order">
            <i class="fa fa-save"></i> Save Order
        </button>
        <button class="forge-tool-btn" id="btn-prefix">
            <i class="fa fa-sort-numeric-asc"></i> Auto-Prefix
        </button>
        <button class="forge-tool-btn" id="btn-export">
            <i class="fa fa-download"></i> Export ZIP
        </button>
        <button class="forge-tool-btn accent" id="btn-preview-scene">
            <i class="fa fa-play-circle"></i> Preview Scene
        </button>
        

         <button class="forge-tool-btn accent" id="btn-edit-dialogue" onclick="window.location.href='paradigm_b.php?scene_id=<?php echo $sceneId; ?>'">
             <i class="fa fa-comments"></i> Edit Dialogue
         </button>
         
        
        
        
        <button class="forge-tool-btn primary" id="btn-add-shot">
            <i class="fa fa-plus"></i> Add Video
        </button>
    </div>

    <div id="shot-grid" class="shot-grid"></div>
</div>

<!-- Video Picker Modal -->
<div id="picker-modal" class="modal-overlay">
    <div class="picker-box">
        <div class="picker-head">
            <button class="picker-tree-toggle" id="picker-tree-toggle" onclick="togglePickerTree()" title="Story Tree">☰</button>
            <h3>Select Video</h3>
            <span id="picker-tree-active" style="font-size:0.8rem; color:var(--accent); display:none;"></span>
            <button class="picker-head-close" onclick="closePicker()">Close</button>
        </div>
        <div class="picker-body">
            <!-- Tree flyout backdrop (mobile only) -->
            <div class="picker-tree-backdrop" id="picker-tree-backdrop" onclick="closePickerTree()"></div>
            <!-- Tree panel -->
            <div class="picker-tree-panel" id="picker-tree-panel">
                <div class="picker-tree-header">
                    <div class="picker-mode-btns">
                        <button class="picker-mode-btn active" id="picker-mode-tree" onclick="switchPickerMode('tree')" title="Filter by Story Tree node">🌳 Tree</button>
                        <button class="picker-mode-btn" id="picker-mode-seq" onclick="switchPickerMode('seq')" title="Browse by Narrative Sequence">🎬 Seq</button>
                        <button class="picker-mode-btn" id="picker-mode-fuzz" onclick="switchPickerMode('fuzz')" title="Browse by Fuzz Candidate">🧩 Fuzz</button>
                        <button class="picker-mode-btn" id="picker-mode-storyboard" onclick="switchPickerMode('storyboard')" title="Browse by Storyboard">🖼️ Board</button>
                    </div>
                    <button class="picker-tree-clear" id="picker-tree-clear" style="display:none;" onclick="clearTreeFilter()">All Videos</button>
                </div>
                <!-- Tree mode content -->
                <div class="picker-tree-scroll" id="picker-tree-scroll">
                    <div id="picker-tree">Loading…</div>
                </div>
                <!-- Sequence mode content -->
                <div class="picker-seq-panel" id="picker-seq-panel">
                    <div id="picker-seq-list" style="padding:4px 0;"></div>
                </div>
                <!-- Fuzz mode content -->
                <div class="picker-fuzz-panel" id="picker-fuzz-panel">
                    <div class="picker-fuzz-search">
                        <input type="search" id="picker-fuzz-search" class="picker-fuzz-search-input" placeholder="Search candidates…" autocomplete="off">
                    </div>
                    <div id="picker-fuzz-list" style="padding:4px 0;"></div>
                    <div class="picker-fuzz-pg" id="picker-fuzz-pg" style="display:none;">
                        <button class="picker-fuzz-pg-btn" id="picker-fuzz-prev" disabled>‹</button>
                        <input type="number" class="picker-fuzz-pg-input" id="picker-fuzz-page-input" value="1" min="1">
                        <span class="picker-fuzz-pg-of" id="picker-fuzz-pg-of">/ 1</span>
                        <button class="picker-fuzz-pg-btn" id="picker-fuzz-next" disabled>›</button>
                    </div>
                </div>
                <!-- Storyboard mode content -->
                <div class="picker-storyboard-panel" id="picker-storyboard-panel">
                    <div class="picker-fuzz-search">
                        <input type="search" id="picker-storyboard-search" class="picker-fuzz-search-input" placeholder="Search storyboards…" autocomplete="off">
                    </div>
                    <div id="picker-storyboard-list" style="padding:4px 0;"></div>
                    <div class="picker-fuzz-pg" id="picker-storyboard-pg" style="display:none;">
                        <button class="picker-fuzz-pg-btn" id="picker-storyboard-prev" disabled>‹</button>
                        <input type="number" class="picker-fuzz-pg-input" id="picker-storyboard-page-input" value="1" min="1">
                        <span class="picker-fuzz-pg-of" id="picker-storyboard-pg-of">/ 1</span>
                        <button class="picker-fuzz-pg-btn" id="picker-storyboard-next" disabled>›</button>
                    </div>
                </div>
            </div>
            <!-- Videos panel -->
            <div class="picker-videos-panel">
                <div class="picker-search-bar">
                    <input type="text" id="picker-search" placeholder="Search videos…" class="form-control" style="width:100%;">
                </div>
                <div class="picker-videos-scroll">
                    <div id="picker-results" class="picker-grid"></div>
                    <div id="picker-loading" style="display:none; text-align:center; padding:20px; color:#888;">Loading...</div>
                    <div id="picker-empty" style="display:none; text-align:center; padding:20px; color:#888;">No videos found</div>
                </div>
                <div class="picker-footer">
                    <button id="picker-prev" class="btn btn-sm btn-outline-secondary" disabled>Previous</button>
                    <div class="picker-page-jump">
                        <span>Page</span>
                        <input type="number" id="picker-page-input" class="picker-page-input" value="1" min="1">
                        <span id="picker-page-of">of 1</span>
                    </div>
                    <button id="picker-next" class="btn btn-sm btn-outline-secondary" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
<script src="js/toast.js"></script>
<script>
const SCENE_ID = <?php echo $sceneId; ?>;
let shots =[];
let pickerTreeInited = false;
let pickerNodeId = null;
let pickerNodeName = '';
let pickerMode = 'tree';      // 'tree' | 'seq' | 'fuzz' | 'storyboard'
let pickerSeqId = null;
let pickerSeqName = '';
let pickerSeqsLoaded = false;
let pickerFuzzId = null;
let pickerFuzzName = '';
let pickerFuzzLoaded = false;
let pickerFuzzPage = 1;
let pickerFuzzTotalPages = 1;
let pickerFuzzSearch = '';
let pickerFuzzSearchTimer = null;
const FUZZ_PER_PAGE = 20;

let pickerStoryboardId = null;
let pickerStoryboardName = '';
let pickerStoryboardsLoaded = false;
let pickerStoryboardPage = 1;
let pickerStoryboardTotalPages = 1;
let pickerStoryboardSearch = '';
let pickerStoryboardSearchTimer = null;

let currentPage = 1;
const itemsPerPage = 12;

// Init
(async () => {
    const sData = await fetch(`editorial_api.php?action=get_scene_details&scene_id=${SCENE_ID}`).then(r=>r.json());
    if(sData.success) {
        document.getElementById('scene-name').textContent = sData.data.name;
        document.getElementById('breadcrumbs').innerHTML = `
            <a href="view_editorial_scenes.php?sequence_id=${sData.data.sequence_id}">${esc(sData.data.sequence_name)}</a>
            <span class="sep">›</span>
            <span style="opacity:0.7;">${esc(sData.data.name)}</span>
        `;
    }
    loadShots();
})();

async function loadShots() {
    const res = await fetch(`editorial_api.php?action=list_shots&scene_id=${SCENE_ID}`).then(r=>r.json());
    if(res.success) {
        shots = res.data;
        render();
    }
}

function render() {
    const grid = document.getElementById('shot-grid');
    grid.innerHTML = '';
    shots.forEach(shot => {
        const el = document.createElement('div');
        el.className = 'shot-card';
        el.dataset.id = shot.id;
        const dur = formatDur(shot.duration_est);
        const hasAnimatic = !!(shot.animatic_id);
        
        el.innerHTML = `
            <div class="shot-thumb-wrap" title="Click to play">
                <img src="${shot.video_thumbnail}" class="shot-thumb" loading="lazy">
                <div class="shot-play-icon">▶</div>
                <span class="shot-dur">${dur}</span>
            </div>
            <div class="shot-meta">
                <span class="shot-name" title="${esc(shot.name)}">${esc(shot.name)}</span>
                <div style="display:flex; align-items:center; gap:3px;">
                    <button class="btn-animatic-shot" title="${hasAnimatic ? 'Open Animatic #'+shot.animatic_id : 'No linked animatic'}"
                            ${hasAnimatic ? '' : 'disabled'}
                            data-animatic-id="${shot.animatic_id || ''}">🎬</button>
                    <div class="handle" title="Drag to reorder">☰</div>
                    <button class="btn-del-shot" onclick="deleteShot(${shot.id})" title="Delete shot">✕</button>
                </div>
            </div>
        `;
        
        el.querySelector('.shot-thumb-wrap').onclick = () => {
            if(shot.filename) {
                window.showVideoPreview(shot.filename, shot.name);
            }
        };

        el.querySelector('.btn-animatic-shot').onclick = () => {
            if(!hasAnimatic) return;
            if(typeof window.showEntityFormInModal === 'function') {
                window.showEntityFormInModal('animatics', shot.animatic_id);
            } else {
                window.open('animatics_crud.php?id=' + shot.animatic_id, '_blank');
            }
        };
        
        grid.appendChild(el);
    });
}

// Drag & Drop
new Sortable(document.getElementById('shot-grid'), {
    animation: 150,
    handle: '.handle', 
    onEnd: () => {
        if(typeof Toast !== 'undefined') Toast.show('Order changed. Click Save Order.', 'info');
    }
});

// Save Order
document.getElementById('btn-save-order').onclick = async () => {
    const ids = Array.from(document.querySelectorAll('.shot-card')).map(el => el.dataset.id);
    const res = await fetch('editorial_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=reorder_shots&ids=${JSON.stringify(ids)}`
    }).then(r=>r.json());
    if(res.success) {
        if(typeof Toast !== 'undefined') Toast.show('Order saved', 'success');
    }
};

// Preview Scene in Modal
document.getElementById('btn-preview-scene').onclick = () => {
    if(window.showVideoContextUrl) {
        window.showVideoContextUrl(`view_editorial_playlist.php?scene_id=${SCENE_ID}`, 'Scene Preview');
    } else {
        alert('Modal not available');
    }
};

// --- VIDEO PICKER LOGIC ---

const pickerModal = document.getElementById('picker-modal');
const searchInp = document.getElementById('picker-search');
const prevBtn = document.getElementById('picker-prev');
const nextBtn = document.getElementById('picker-next');
const pageInput = document.getElementById('picker-page-input');
const pageOf    = document.getElementById('picker-page-of');
let totalPages = 1;

pageInput.addEventListener('change', () => {
    const v = parseInt(pageInput.value, 10);
    if (!isNaN(v) && v >= 1 && v <= totalPages && v !== currentPage) {
        currentPage = v;
        searchVideos(false);
    } else {
        pageInput.value = currentPage;
    }
});
const treeActiveEl = document.getElementById('picker-tree-active');
const treeClearBtn = document.getElementById('picker-tree-clear');

document.getElementById('btn-add-shot').onclick = () => {
    pickerModal.classList.add('active');
    // On desktop the tree panel is always visible — init it immediately
    if(!pickerTreeInited && window.innerWidth >= 700) initPickerTree();
    searchVideos(true);
};

function closePicker() {
    pickerModal.classList.remove('active');
    closePickerTree();
}

function togglePickerTree() {
    const panel    = document.getElementById('picker-tree-panel');
    const backdrop = document.getElementById('picker-tree-backdrop');
    const isOpen   = panel.classList.contains('open');
    if (isOpen) {
        panel.classList.remove('open');
        backdrop.classList.remove('active');
    } else {
        panel.classList.add('open');
        backdrop.classList.add('active');
        if (!pickerTreeInited) initPickerTree();
    }
}

function closePickerTree() {
    document.getElementById('picker-tree-panel').classList.remove('open');
    document.getElementById('picker-tree-backdrop').classList.remove('active');
}

function initPickerTree() {
    pickerTreeInited = true;
    $('#picker-tree').jstree({
        core: {
            data: {
                url: 'view_video_review.php?api_action=tree_fetch',
                dataType: 'json',
                dataFilter: function(raw) {
                    try {
                        const j = JSON.parse(raw);
                        return JSON.stringify(j.status === 'ok' ? j.tree :[]);
                    } catch(e) { return '[]'; }
                }
            },
            themes: { name: 'default', dots: true, icons: true },
            check_callback: false,
        },
        plugins:['types'],
        types: {
            folder:   { icon: 'bi bi-folder2' },
            episode:  { icon: 'bi bi-film' },
            sequence: { icon: 'bi bi-collection-play' },
            scene:    { icon: 'bi bi-camera-video' },
            other:    { icon: 'bi bi-tag' },
        },
    }).on('select_node.jstree', function(e, data) {
        pickerNodeId   = data.node.data.db_id;
        pickerNodeName = data.node.text;
        treeActiveEl.textContent = '⬡ ' + pickerNodeName;
        treeActiveEl.style.display = 'inline';
        treeClearBtn.style.display = 'inline-block';
        closePickerTree();
        searchVideos(true);
    }).on('deselect_node.jstree', function() {
        pickerNodeId   = null;
        pickerNodeName = '';
        treeActiveEl.style.display = 'none';
        treeClearBtn.style.display = 'none';
        searchVideos(true);
    });
}

function clearTreeFilter() {
    if (pickerMode === 'seq') {
        pickerSeqId = null;
        pickerSeqName = '';
        document.querySelectorAll('.picker-seq-item').forEach(i => i.classList.remove('active'));
        treeActiveEl.style.display = 'none';
        treeClearBtn.style.display = 'none';
        searchVideos(true);
    } else if (pickerMode === 'fuzz') {
        pickerFuzzId = null;
        pickerFuzzName = '';
        document.querySelectorAll('.picker-fuzz-item').forEach(i => i.classList.remove('active'));
        treeActiveEl.style.display = 'none';
        treeClearBtn.style.display = 'none';
        searchVideos(true);
    } else if (pickerMode === 'storyboard') {
        pickerStoryboardId = null;
        pickerStoryboardName = '';
        document.querySelectorAll('.picker-storyboard-item').forEach(i => i.classList.remove('active'));
        treeActiveEl.style.display = 'none';
        treeClearBtn.style.display = 'none';
        searchVideos(true);
    } else {
        $('#picker-tree').jstree('deselect_all');
        // deselect_node event fires and resets pickerNodeId + triggers searchVideos
    }
}

function switchPickerMode(mode) {
    pickerMode = mode;
    const treeScroll = document.getElementById('picker-tree-scroll');
    const seqPanel   = document.getElementById('picker-seq-panel');
    const fuzzPanel  = document.getElementById('picker-fuzz-panel');
    const sbPanel    = document.getElementById('picker-storyboard-panel');
    const btnTree    = document.getElementById('picker-mode-tree');
    const btnSeq     = document.getElementById('picker-mode-seq');
    const btnFuzz    = document.getElementById('picker-mode-fuzz');
    const btnSb      = document.getElementById('picker-mode-storyboard');
    const clearBtn   = document.getElementById('picker-tree-clear');

    // Reset all panels
    treeScroll.style.display = 'none';
    seqPanel.classList.remove('active');
    fuzzPanel.classList.remove('active');
    sbPanel.classList.remove('active');
    btnTree.classList.remove('active');
    btnSeq.classList.remove('active');
    btnFuzz.classList.remove('active');
    btnSb.classList.remove('active');

    if (mode === 'tree') {
        treeScroll.style.display = 'block';
        btnTree.classList.add('active');
        if (pickerNodeId) {
            treeActiveEl.textContent = '⬡ ' + pickerNodeName;
            treeActiveEl.style.display = 'inline';
            clearBtn.style.display = 'inline-block';
        } else {
            treeActiveEl.style.display = 'none';
            clearBtn.style.display = 'none';
        }
        pickerSeqId  = null;
        pickerFuzzId = null;
        pickerStoryboardId = null;
        if (!pickerTreeInited) initPickerTree();

    } else if (mode === 'seq') {
        seqPanel.classList.add('active');
        btnSeq.classList.add('active');
        if (pickerSeqId) {
            treeActiveEl.textContent = '🎬 ' + pickerSeqName;
            treeActiveEl.style.display = 'inline';
            clearBtn.textContent = 'All Videos';
            clearBtn.style.display = 'inline-block';
        } else {
            treeActiveEl.style.display = 'none';
            clearBtn.style.display = 'none';
        }
        pickerNodeId = null;
        pickerFuzzId = null;
        pickerStoryboardId = null;
        if (!pickerSeqsLoaded) loadSequences();

    } else if (mode === 'fuzz') {
        fuzzPanel.classList.add('active');
        btnFuzz.classList.add('active');
        if (pickerFuzzId) {
            treeActiveEl.textContent = '🧩 ' + pickerFuzzName;
            treeActiveEl.style.display = 'inline';
            clearBtn.textContent = 'All Videos';
            clearBtn.style.display = 'inline-block';
        } else {
            treeActiveEl.style.display = 'none';
            clearBtn.style.display = 'none';
        }
        pickerNodeId = null;
        pickerSeqId  = null;
        pickerStoryboardId = null;
        if (!pickerFuzzLoaded) loadFuzzCandidates(1);
        
    } else if (mode === 'storyboard') {
        sbPanel.classList.add('active');
        btnSb.classList.add('active');
        if (pickerStoryboardId) {
            treeActiveEl.textContent = '🖼️ ' + pickerStoryboardName;
            treeActiveEl.style.display = 'inline';
            clearBtn.textContent = 'All Videos';
            clearBtn.style.display = 'inline-block';
        } else {
            treeActiveEl.style.display = 'none';
            clearBtn.style.display = 'none';
        }
        pickerNodeId = null;
        pickerSeqId  = null;
        pickerFuzzId = null;
        if (!pickerStoryboardsLoaded) loadStoryboards(1);
    }

    searchVideos(true);
}

async function loadSequences() {
    pickerSeqsLoaded = true;
    const list = document.getElementById('picker-seq-list');
    list.innerHTML = '<div style="padding:12px; font-size:0.75rem; color:#888;">Loading…</div>';

    const res = await fetch('editorial_api.php?action=list_narrative_sequences').then(r=>r.json());
    list.innerHTML = '';

    if (!res.success || !res.data || !res.data.length) {
        list.innerHTML = '<div style="padding:12px; font-size:0.75rem; color:#888;">No sequences found.</div>';
        return;
    }

    res.data.forEach(seq => {
        const el = document.createElement('div');
        el.className = 'picker-seq-item' + (seq.id == pickerSeqId ? ' active' : '');
        el.dataset.id = seq.id;
        el.innerHTML = `
            <div class="seq-item-id">#${seq.id}</div>
            <div class="seq-item-name">${esc(seq.name)}</div>
            ${seq.description ? `<div class="seq-item-desc">${esc(seq.description)}</div>` : ''}
        `;
        el.onclick = () => {
            document.querySelectorAll('.picker-seq-item').forEach(i => i.classList.remove('active'));
            el.classList.add('active');
            pickerSeqId   = seq.id;
            pickerSeqName = seq.name;
            treeActiveEl.textContent = '🎬 ' + seq.name;
            treeActiveEl.style.display = 'inline';
            treeClearBtn.textContent = 'All Videos';
            treeClearBtn.style.display = 'inline-block';
            closePickerTree();
            searchVideos(true);
        };
        list.appendChild(el);
    });
}

// --- STORYBOARDS PICKER ---
async function loadStoryboards(page) {
    page = parseInt(page) || 1;
    if (page < 1) page = 1;
    pickerStoryboardsLoaded = true;
    pickerStoryboardPage = page;

    const list = document.getElementById('picker-storyboard-list');
    list.innerHTML = '<div style="padding:12px; font-size:0.75rem; color:#888;">Loading…</div>';

    const params = new URLSearchParams({
        action: 'list_storyboards',
        page,
        search: pickerStoryboardSearch
    });

    const res = await fetch('editorial_api.php?' + params).then(r=>r.json()).catch(() => null);
    list.innerHTML = '';

    if (!res || !res.success || !res.data || !res.data.length) {
        list.innerHTML = '<div style="padding:12px; font-size:0.75rem; color:#888;">No storyboards found.</div>';
        document.getElementById('picker-storyboard-pg').style.display = 'none';
        return;
    }

    const pg = res.pagination || { pages: 1, page: 1 };
    pickerStoryboardTotalPages = pg.pages;
    document.getElementById('picker-storyboard-page-input').value = pg.page;
    document.getElementById('picker-storyboard-page-input').max   = pg.pages;
    document.getElementById('picker-storyboard-pg-of').textContent = `/ ${pg.pages}`;
    document.getElementById('picker-storyboard-prev').disabled = pg.page <= 1;
    document.getElementById('picker-storyboard-next').disabled = pg.page >= pg.pages;
    document.getElementById('picker-storyboard-pg').style.display = pg.pages > 1 ? 'flex' : 'none';

    res.data.forEach(sb => {
        const el = document.createElement('div');
        el.className = 'picker-storyboard-item' + (sb.id == pickerStoryboardId ? ' active' : '');
        el.dataset.id = sb.id;
        el.innerHTML = `
            <div class="sb-item-id">#${sb.id}</div>
            <div class="sb-item-name">${esc(sb.name || sb.title || 'Untitled')}</div>
            ${sb.description ? `<div class="sb-item-desc">${esc(sb.description)}</div>` : ''}
        `;
        el.onclick = () => {
            document.querySelectorAll('.picker-storyboard-item').forEach(i => i.classList.remove('active'));
            el.classList.add('active');
            pickerStoryboardId   = sb.id;
            pickerStoryboardName = sb.name || sb.title;
            treeActiveEl.textContent = '🖼️ ' + pickerStoryboardName;
            treeActiveEl.style.display = 'inline';
            treeClearBtn.textContent = 'All Videos';
            treeClearBtn.style.display = 'inline-block';
            closePickerTree();
            searchVideos(true);
        };
        list.appendChild(el);
    });
}

document.getElementById('picker-storyboard-prev').addEventListener('click', () => loadStoryboards(pickerStoryboardPage - 1));
document.getElementById('picker-storyboard-next').addEventListener('click', () => loadStoryboards(pickerStoryboardPage + 1));
document.getElementById('picker-storyboard-page-input').addEventListener('change', function() {
    const v = parseInt(this.value, 10);
    if (!isNaN(v) && v >= 1 && v <= pickerStoryboardTotalPages) loadStoryboards(v);
    else this.value = pickerStoryboardPage;
});

document.getElementById('picker-storyboard-search').addEventListener('input', function() {
    clearTimeout(pickerStoryboardSearchTimer);
    pickerStoryboardSearchTimer = setTimeout(() => {
        pickerStoryboardSearch = this.value.trim();
        pickerStoryboardPage = 1;
        pickerStoryboardsLoaded = false;
        loadStoryboards(1);
    }, 280);
});

// --- FUZZ CANDIDATES PICKER ---

const FUZZ_TYPE_ICONS = {
    character: '🦸', location: '🗺️', faction: '⚔️', artifact: '🏺',
    event: '⚡', concept: '💡', relationship: '🔗', other: '◆'
};

async function loadFuzzCandidates(page) {
    page = parseInt(page) || 1;
    if (page < 1) page = 1;
    pickerFuzzLoaded = true;
    pickerFuzzPage = page;

    const list = document.getElementById('picker-fuzz-list');
    list.innerHTML = '<div style="padding:12px; font-size:0.75rem; color:#888;">Loading…</div>';

    const params = new URLSearchParams({
        api_action: 'list_candidates',
        page,
        limit: FUZZ_PER_PAGE,
        search: pickerFuzzSearch
    });

    const res = await fetch('view_fuzz_preview.php?' + params).then(r => r.json()).catch(() => null);
    list.innerHTML = '';

    if (!res || res.status !== 'ok' || !res.data || !res.data.length) {
        list.innerHTML = '<div style="padding:12px; font-size:0.75rem; color:#888;">No approved candidates found.</div>';
        document.getElementById('picker-fuzz-pg').style.display = 'none';
        return;
    }

    const pg = res.pagination;
    pickerFuzzTotalPages = pg.pages;
    document.getElementById('picker-fuzz-page-input').value = pg.page;
    document.getElementById('picker-fuzz-page-input').max   = pg.pages;
    document.getElementById('picker-fuzz-pg-of').textContent = `/ ${pg.pages}`;
    document.getElementById('picker-fuzz-prev').disabled = pg.page <= 1;
    document.getElementById('picker-fuzz-next').disabled = pg.page >= pg.pages;
    document.getElementById('picker-fuzz-pg').style.display = pg.pages > 1 ? 'flex' : 'none';

    res.data.forEach(cand => {
        const el = document.createElement('div');
        el.className = 'picker-fuzz-item' + (cand.id == pickerFuzzId ? ' active' : '');
        el.dataset.id = cand.id;
        const icon = FUZZ_TYPE_ICONS[cand.concept_type] || '◆';
        el.innerHTML = `
            <div class="fuzz-item-id">#${cand.id}</div>
            <div class="fuzz-item-name">${icon} ${esc(cand.label)}</div>
            ${cand.concept_type ? `<div class="fuzz-item-type">${esc(cand.concept_type)}</div>` : ''}
            <span class="fuzz-item-status ${esc(cand.status)}">${esc(cand.status)}</span>
        `;
        el.onclick = () => {
            document.querySelectorAll('.picker-fuzz-item').forEach(i => i.classList.remove('active'));
            el.classList.add('active');
            pickerFuzzId   = cand.id;
            pickerFuzzName = cand.label;
            treeActiveEl.textContent = '🧩 ' + cand.label;
            treeActiveEl.style.display = 'inline';
            treeClearBtn.textContent = 'All Videos';
            treeClearBtn.style.display = 'inline-block';
            closePickerTree();
            searchVideos(true);
        };
        list.appendChild(el);
    });
}

// Fuzz panel pagination wiring
document.getElementById('picker-fuzz-prev').addEventListener('click', () => loadFuzzCandidates(pickerFuzzPage - 1));
document.getElementById('picker-fuzz-next').addEventListener('click', () => loadFuzzCandidates(pickerFuzzPage + 1));
document.getElementById('picker-fuzz-page-input').addEventListener('change', function() {
    const v = parseInt(this.value, 10);
    if (!isNaN(v) && v >= 1 && v <= pickerFuzzTotalPages) loadFuzzCandidates(v);
    else this.value = pickerFuzzPage;
});

// Fuzz search input
document.getElementById('picker-fuzz-search').addEventListener('input', function() {
    clearTimeout(pickerFuzzSearchTimer);
    pickerFuzzSearchTimer = setTimeout(() => {
        pickerFuzzSearch = this.value.trim();
        pickerFuzzPage = 1;
        pickerFuzzLoaded = false;
        loadFuzzCandidates(1);
    }, 280);
});

// Listeners
let debounceTimer;
searchInp.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => searchVideos(true), 300);
});

prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; searchVideos(false); } };
nextBtn.onclick = () => { currentPage++; searchVideos(false); };

async function searchVideos(resetPage = false) {
    if(resetPage) currentPage = 1;

    const q = searchInp.value;
    
    document.getElementById('picker-loading').style.display = 'block';
    document.getElementById('picker-results').innerHTML = '';
    document.getElementById('picker-empty').style.display = 'none';
    prevBtn.disabled = true;
    nextBtn.disabled = true;

    const params = new URLSearchParams({ 
        action: 'search_videos', 
        q, 
        page: currentPage,
        limit: itemsPerPage
    });
    if(pickerMode === 'seq' && pickerSeqId) {
        params.set('seq_id', pickerSeqId);
    } else if(pickerMode === 'fuzz' && pickerFuzzId) {
        params.set('fuzz_cand_id', pickerFuzzId);
    } else if(pickerMode === 'storyboard' && pickerStoryboardId) {
        params.set('storyboard_id', pickerStoryboardId);
    } else if(pickerNodeId) {
        params.set('node_id', pickerNodeId);
        params.set('include_descendants', '1');
    }
    
    const res = await fetch('editorial_api.php?' + params).then(r=>r.json());

    document.getElementById('picker-loading').style.display = 'none';
    const container = document.getElementById('picker-results');
    
    if(res.success && res.data && res.data.length > 0) {
        res.data.forEach(vid => {
            const d = document.createElement('div');
            d.className = 'vid-item';
            const hasAnimatic = !!(vid.animatic_id);
            d.innerHTML = `
                <img src="${vid.thumbnail}" class="vid-img">
                <div class="vid-item-btns">
                    <button class="vid-item-btn vid-item-btn-preview" title="Preview"><i class="fa-solid fa-eye"></i></button>
                    <button class="vid-item-btn vid-item-btn-add" title="Add to Shot"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div class="vid-info">
                    <div class="vid-name" title="${esc(vid.name)}">${esc(vid.name)}${hasAnimatic ? '<span class="vid-animatic-badge">🎬</span>' : ''}</div>
                    <div class="vid-sub">${formatDur(vid.duration)}</div>
                </div>
            `;
            d.querySelector('.vid-item-btn-preview').onclick = (e) => { e.stopPropagation(); window.showVideoPickerPreview(vid.id, () => addShot(vid.id)); };
            d.querySelector('.vid-item-btn-add').onclick    = (e) => { e.stopPropagation(); addShot(vid.id); };
            d.onclick = () => window.showVideoPickerPreview(vid.id, () => addShot(vid.id));
            container.appendChild(d);
        });

        if(res.pagination) {
            const p = res.pagination;
            totalPages = p.pages || 1;
            pageInput.value = p.page;
            pageInput.max   = totalPages;
            pageOf.textContent = `of ${totalPages}`;
            prevBtn.disabled = p.page <= 1;
            nextBtn.disabled = p.page >= p.pages;
        }

    } else {
        document.getElementById('picker-empty').style.display = 'block';
        totalPages = 1;
        pageInput.value = 1;
        pageInput.max   = 1;
        pageOf.textContent = 'of 1';
    }
}

// ---

async function addShot(vidId) {
    const res = await fetch('editorial_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=add_shot&scene_id=${SCENE_ID}&video_id=${vidId}`
    }).then(r=>r.json());
    
    if(res.success) {
        loadShots();
        if(typeof Toast !== 'undefined') Toast.show('Shot added', 'success');
    } else {
        alert(res.message);
    }
}

// Delete
async function deleteShot(id) {
    if(!confirm('Remove this shot?')) return;
    const res = await fetch('editorial_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=delete_shot&id=${id}`
    }).then(r=>r.json());
    if(res.success) loadShots();
}

// Auto Prefix
document.getElementById('btn-prefix').onclick = async () => {
    if(!confirm('Rename physical files based on current order?')) return;
    const ids = Array.from(document.querySelectorAll('.shot-card')).map(el => el.dataset.id);
    const res = await fetch('editorial_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=auto_prefix_shots&scene_id=${SCENE_ID}&order=${JSON.stringify(ids)}`
    }).then(r=>r.json());
    
    if(res.success) {
        if(typeof Toast !== 'undefined') Toast.show('Files renamed', 'success');
        loadShots();
    } else {
        alert(res.message);
    }
};

// Export ZIP
document.getElementById('btn-export').onclick = async () => {
    const btn = document.getElementById('btn-export');
    btn.disabled = true; btn.textContent = 'Zipping...';
    
    const res = await fetch('editorial_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=export_zip&scene_id=${SCENE_ID}`
    }).then(r=>r.json());
    
    btn.disabled = false; btn.innerHTML = '<i class="fa fa-download"></i> Export ZIP';
    
    if(res.success && res.download_url) {
        window.location.href = res.download_url;
    } else {
        alert(res.message || 'Export failed');
    }
};

function formatDur(s) {
    if(!s) return '0:00';
    const m = Math.floor(s/60);
    const sec = Math.floor(s%60);
    return `${m}:${sec.toString().padStart(2,'0')}`;
}
function esc(t) { return t ? t.replace(/&/g,'&amp;').replace(/</g,'&lt;') : ''; }
</script>
<?php
$spw->renderLayout(ob_get_clean(), $pageTitle);
?>
