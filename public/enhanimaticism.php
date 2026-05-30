<?php
// public/enhanimaticism.php
// Enhanimatics (Entities mode) -- Unified Sketches Tool (Entity-specific)
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php'; // provides $entityIcons

// Allowed entities come from entity_icons.php (whitelist)
$allowedEntities = array_keys($entityIcons ?? []);
// Selected entity comes from request or defaults to 'sketches'
$selectedEntity = $_REQUEST['entity'] ?? 'sketches';
if (!in_array($selectedEntity, $allowedEntities, true)) {
    $selectedEntity = 'sketches';
}

// Determine runtime entity for API calls (safe - already whitelisted)
$entityType = $selectedEntity;

// Deep-link params: entity_type, map_run_id, entity_id, search, map_run_dis
// entity_type overrides the entity select on page load
if (isset($_GET['entity_type']) && in_array($_GET['entity_type'], $allowedEntities, true)) {
    $selectedEntity = $_GET['entity_type'];
    $entityType = $selectedEntity;
}
$deepLinkRunId = isset($_GET['map_run_id']) ? (int)$_GET['map_run_id'] : 0;
$deepLinkEntityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$deepLinkSearch = isset($_GET['search']) ? $_GET['search'] : '';
$mapRunDisabled = isset($_GET['map_run_dis']) && $_GET['map_run_dis'] == '1';

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    require_once __DIR__ . '/enhanimaticism_api.php';
}

// --------------------- Page render ---------------------
$pageTitle = 'Enhanimatics — Entities Mode';
ob_start();
?>
<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>

<style>
:root {
    --bg: #0a0a0f;
    --card: #111118;
    --border: #1e1e2e;
    --text: #e2e2f0;
    --text-muted: #555570;
    --purple: #8b5cf6; 
    --purple-dim: rgba(139, 92, 246, 0.1);
    --amber: #f59e0b;
    --amber-dim: rgba(245, 158, 11, 0.1);
    --red: #ef4444;
    --teal: #14b8a6;
    --teal-dim: rgba(20, 184, 166, 0.12);
}
    [data-theme="light"] {
        --bg:         #f4f4f8;
        --card:       #ffffff;
        --border:     #d0d0e0;
        --text:       #1a1a2e;
        --text-muted: #888899;
    }
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }
.eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }
.eh-header {
    flex-shrink: 0; padding: 0 16px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.eh-title span { color: var(--purple); }
.eh-nav { display: flex; height: 100%; gap: 12px; align-items:center; }
.mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); }
.map-run-toggle {
    flex-shrink: 0; padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
    text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;
}
.map-run-toggle.active {
    border-color: var(--teal); color: #000; background: var(--teal);
    box-shadow: 0 0 10px rgba(20, 184, 166, 0.4);
}
.btn-forge-open { background: rgba(139,92,246,0.15); border-color: var(--purple); color: var(--purple); }
.btn-forge-open:hover { background: var(--purple); color: #fff; }
.mr-search-input { flex: 1; min-width: 0; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
.mr-search-input:focus { outline: none; border-color: var(--purple); }
.mr-pagination { display: flex; align-items: center; gap: 4px; }
.pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--purple); color: var(--purple); }
.pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--purple); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
.pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }
.mr-list-scroll { overflow-y: auto; overflow-x: hidden; min-height: 60px; }
.mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
.mr-item:hover { background: rgba(255,255,255,0.05); }
.mr-item.active { background: var(--purple-dim); border-left: 3px solid var(--purple); padding-left: 9px; }
.mr-id { font-size: 0.7rem; font-weight: 700; color: var(--purple); min-width: 40px; }
.mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }
.mr-regen-wrap { flex-shrink: 0; display: flex; align-items: center; justify-content: center; padding: 0 4px; }
.regen-checkbox { transform: scale(1.1); cursor: pointer; margin: 0; accent-color: var(--amber); }
.mr-ops-wrap { position: relative; flex-shrink: 0; }
.btn-mr-ops {
    flex-shrink: 0; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
}
.btn-mr-ops:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.mr-ops-menu {
    display: none; position: absolute; right: 0; top: calc(100% + 4px);
    background: var(--card); border: 1px solid var(--border); border-radius: 4px;
    min-width: 140px; z-index: 9999; box-shadow: 0 4px 16px rgba(0,0,0,0.5);
}
.mr-ops-menu.open { display: block; }
.mr-ops-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; font-size: 0.72rem; color: var(--text-muted);
    cursor: pointer; transition: background 0.12s, color 0.12s;
    border: none; background: none; width: 100%; text-align: left; font-family: inherit;
    white-space: nowrap;
}
.mr-ops-item:hover { background: rgba(255,255,255,0.06); color: var(--text); }
.mr-ops-item + .mr-ops-item { border-top: 1px solid var(--border); }

/* Active Forge Filters Bar */
.forge-filters-bar { padding: 6px 12px; display: flex; gap: 6px; align-items: center; border-bottom: 1px solid var(--border); background: var(--bg); flex-wrap: wrap; }
.forge-pill { background: rgba(139,92,246,0.15); border: 1px solid rgba(139,92,246,0.3); color: var(--purple); padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; display: flex; align-items: center; gap: 6px; font-weight: bold; }
.forge-pill-close { cursor: pointer; opacity: 0.7; font-size: 1.1em; }
.forge-pill-close:hover { opacity: 1; color: var(--red); }

/* Forge Modal specific styles */
.forge-sidebar { display: flex; flex-direction: column; gap: 4px; padding-bottom: 15px; }
.forge-sidebar-btn { padding: 8px 12px; background: transparent; border: none; color: var(--text-muted); text-align: left; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 0.8rem; font-family: inherit; }
.forge-sidebar-btn:hover { background: rgba(255,255,255,0.05); color: var(--text); }
.forge-sidebar-btn.active { background: var(--purple-dim); color: var(--purple); }
.forge-search { width: 100%; padding: 8px 12px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-family: inherit; font-size: 0.85rem; margin-bottom: 10px; outline: none; }
.forge-search:focus { border-color: var(--purple); }
.forge-item { padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; margin-bottom: 6px; transition: background 0.15s; }
.forge-item:hover { background: var(--purple-dim); border-color: var(--purple); }

.sb-picker-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.7);
    z-index: 300000; display: none; align-items: flex-end; justify-content: center;
}
.sb-picker-backdrop.active { display: flex; }
.sb-picker-sheet {
    width: 100%; max-width: 520px;
    background: var(--card); border: 1px solid var(--border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    padding: 0 0 max(16px, env(safe-area-inset-bottom));
    font-family: 'DM Mono', 'Fira Mono', monospace;
    max-height: 80vh; display: flex; flex-direction: column;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
}
.sb-picker-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.sb-picker-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.sb-picker-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.sb-picker-title { font-size: 0.8rem; font-weight: 700; color: var(--purple); text-transform: uppercase; letter-spacing: 1px; }
.sb-picker-close { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.sb-picker-close:hover { color: var(--text); border-color: var(--text); }
.sb-picker-filters {
    padding: 10px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; flex-direction: column; gap: 8px;
}
.sb-picker-select {
    width: 100%; padding: 6px 8px; background: rgba(0,0,0,0.4); border: 1px solid var(--border);
    border-radius: 4px; color: var(--text); font-family: inherit; font-size: 0.75rem; outline: none;
}
.sb-picker-select:focus { border-color: var(--purple); }
.sb-picker-select:disabled { opacity: 0.4; cursor: not-allowed; }
.sb-picker-editorial { display: none; flex-direction: column; gap: 6px; }
.sb-picker-list { overflow-y: auto; flex: 1; }
.sb-picker-item {
    display: flex; flex-direction: column; padding: 10px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.12s;
}
.sb-picker-item:hover { background: rgba(139,92,246,0.1); }
.sb-picker-item-name { font-size: 0.78rem; font-weight: 600; color: var(--text); }
.sb-picker-item-meta { font-size: 0.65rem; color: var(--text-muted); margin-top: 2px; display: flex; justify-content: space-between; }
.sb-picker-empty { padding: 24px 16px; text-align: center; font-size: 0.75rem; color: var(--text-muted); font-style: italic; }
.sb-picker-loading { padding: 24px 16px; text-align: center; font-size: 0.75rem; color: var(--text-muted); }
.sb-picker-footer {
    padding: 10px 16px 0; flex-shrink: 0; border-top: 1px solid var(--border);
    font-size: 0.65rem; color: var(--text-muted); text-align: center;
}
.sb-picker-footer a { color: var(--purple); text-decoration: none; }
.sb-picker-footer a:hover { text-decoration: underline; }

/* Sort Bar */
.sort-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: var(--card); }
.sort-bar-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; flex-shrink: 0; }
.sort-btn {
    padding: 3px 9px; border-radius: 20px; font-size: 0.65rem; font-family: inherit;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    cursor: pointer; transition: all 0.15s; white-space: nowrap; display: flex; align-items: center; gap: 4px;
}
.sort-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.sort-btn.active { border-color: var(--purple); color: var(--text); background: var(--purple-dim); }

.eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
.config-bar { padding: 6px 12px 4px; display: flex; flex-direction: column; gap: 5px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.prompt-row { display: flex; gap: 6px; align-items: center; }
.prompt-input {
    flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: rgba(0,0,0,0.3); color: var(--text); font-family: inherit; font-size: 0.85rem;
    min-width: 0;
}
.prompt-input:focus { outline: none; border-color: var(--amber); }
.btn-compose {
    flex-shrink: 0; padding: 6px 10px; border-radius: 4px;
    border: 1px solid var(--teal); background: var(--teal-dim); color: var(--teal);
    font-family: inherit; font-size: 0.7rem; font-weight: 700; cursor: pointer;
    text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: background 0.15s, color 0.15s;
}
.btn-compose:hover { background: var(--teal); color: #000; }
.chips-row { display: flex; gap: 8px; align-items: center; border-top: 1px solid rgba(255,255,255,0.04); padding: 5px 0; }
.phrase-chips { flex: 1; display: flex; gap: 5px; overflow-x: auto; scrollbar-width: none; min-width: 0; align-items: center; }
.phrase-chips::-webkit-scrollbar { display: none; }
.phrase-chip {
    flex-shrink: 0; padding: 3px 9px; border-radius: 20px;
    border: 1px solid var(--border); background: rgba(255,255,255,0.04);
    color: var(--text-muted); font-family: inherit; font-size: 0.65rem;
    cursor: pointer; transition: all 0.15s; white-space: nowrap;
}
.phrase-chip:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.phrase-chip.pinned { border-color: rgba(245, 158, 11, 0.3); color: var(--amber); }
.phrase-chip.pinned:hover { border-color: var(--amber); background: var(--amber-dim); }
.chips-empty { font-size: 0.6rem; color: var(--text-muted); opacity: 0.5; padding: 0 8px; white-space: nowrap; font-style: italic; }
.btn-cb-manage {
    flex-shrink: 0; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
}
.btn-cb-manage:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.btn-remove-frame {
    width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
    transition: all 0.12s; flex-shrink: 0;
}
.btn-remove-frame:hover { color: var(--red); border-color: var(--red); background: rgba(239, 68, 68, 0.12); }
.grid-toolbar {
    background: rgba(0,0,0,0.2);
    display: flex; flex-direction: column;
}
.gt-row1 {
    padding: 6px 12px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.gt-row2 {
    padding: 5px 12px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.gt-left { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.gt-info { font-size: 0.7rem; color: var(--text-muted); }
.action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;}
.action-btn:hover { color: var(--purple); border-color: var(--purple); }
.action-btn.primary { border-color: var(--purple); color: var(--purple); }
.chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; select-none: none; }
#hideImported:checked { accent-color: var(--purple); }
#hideEnhanced:checked { accent-color: var(--amber); }
#showRaw:checked { accent-color: #4ade80; }
#oneColGrid:checked { accent-color: var(--teal); }
    .eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: var(--bg); min-height: 0; }
.frames-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding-bottom: 20px; }
.frames-grid.one-col { grid-template-columns: 1fr; }
@media (min-width: 600px) {
    .frames-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}
.f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.f-card.selected { border-color: var(--text); box-shadow: 0 0 0 1px var(--text); }
.f-card.is-imported { border-color: #333; opacity: 0.25; filter: grayscale(80%); }
.f-card.is-imported::before { content: "IMPORTED"; position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 0.7rem; font-weight: 900; color: rgba(139, 92, 246, 0.6); transform: rotate(-15deg); pointer-events: none; z-index: 5; }
.f-card.is-imported.hidden-in-grid { display: none; }
.f-card.is-enhanced { border-color: rgba(245, 158, 11, 0.4); opacity: 0.4; filter: sepia(80%) hue-rotate(-10deg) saturate(1.5) brightness(0.6); }
.f-card.is-enhanced::after { content: "ENHANCED"; position: absolute; bottom: 35px; left: 0; right: 0; text-align: center; font-size: 0.65rem; font-weight: 800; color: rgba(245, 158, 11, 0.8); pointer-events: none; z-index: 5; text-shadow: 0 1px 2px #000; }
.f-card.is-enhanced.hidden-in-grid { display: none; }
.frames-grid.show-raw .f-card.is-imported,
.frames-grid.show-raw .f-card.is-enhanced { opacity: 1; filter: none; border-color: var(--border); }
.frames-grid.show-raw .f-card.is-imported::before,
.frames-grid.show-raw .f-card.is-enhanced::after { display: none; }
.f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.03); }
.f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--text); border-color: var(--text); color: #000; }
.f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
.f-card.selected .f-label { background: rgba(255,255,255,0.1); color: #fff; border-top-color: #fff; }
.f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; }
.f-card.selected .f-select-trigger { background: #fff; border-color: #fff; color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }
.eh-footer {
    flex-shrink: 0; padding: 10px 16px; 
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    background: var(--card); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    z-index: 10; position: relative;
}
.ft-summary { font-size: 0.75rem; color: var(--text-muted); }
.ft-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.btn-action { padding: 10px 12px; border-radius: 4px; border: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; cursor: pointer; font-family: inherit; transition: filter 0.15s; color: #fff; min-width: 0; white-space: nowrap; }
@media (min-width: 600px) {
    .ft-actions { gap: 10px; flex-wrap: nowrap; }
    .btn-action { padding: 12px 20px; font-size: 0.85rem; min-width: 100px; }
}
.btn-action:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }
.btn-enhance { background: var(--amber); color: #000; }
.btn-import { background: var(--purple); }
.btn-stba { background: var(--teal); color: #000; }

.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }
.state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--text); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.pswp { z-index: 99999; }
.compose-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85);
    z-index: 200000; display: none; align-items: flex-end; justify-content: center;
}
.compose-modal-backdrop.active { display: flex; }
.compose-modal {
    width: 100%; max-width: 520px;
    background: var(--card); border: 1px solid var(--border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    padding: 0 0 max(16px, env(safe-area-inset-bottom));
    font-family: 'DM Mono', 'Fira Mono', monospace;
    max-height: 88vh; display: flex; flex-direction: column;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
}
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.cm-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.cm-title { font-size: 0.8rem; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 1px; }
.cm-close-btn { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--text); border-color: var(--text); }
.cm-preview {
    margin: 10px 16px 6px; padding: 8px 10px; border-radius: 5px;
    background: rgba(128,128,128,0.1); border: 1px solid var(--amber);
    font-size: 0.78rem; color: var(--amber); min-height: 36px;
    word-break: break-word; line-height: 1.5; flex-shrink: 0;
    cursor: text; transition: border-color 0.15s;
}
.cm-preview:empty::before { content: "Compose your instruction below…"; color: var(--text-muted); font-style: italic; }
.cm-body { overflow-y: auto; padding: 0 16px; flex: 1; }
.cm-section { margin-top: 14px; }
.cm-section-title {
    font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;
    color: var(--text-muted); margin-bottom: 7px; display: flex; align-items: center; gap: 6px;
}
.cm-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.cm-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.cm-tag {
    padding: 5px 11px; border-radius: 20px; font-size: 0.68rem; font-family: inherit;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    cursor: pointer; transition: all 0.12s; white-space: nowrap;
}
.cm-tag:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.cm-tag.selected { border-color: var(--teal); color: #000; background: var(--teal); }
.cm-color-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.cm-color-label { font-size: 0.65rem; color: var(--text-muted); min-width: 28px; }
.cm-color-swatch {
    width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--border);
    cursor: pointer; transition: transform 0.12s, border-color 0.12s; flex-shrink: 0;
}
.cm-color-swatch:hover { transform: scale(1.15); border-color: #fff; }
.cm-color-swatch.selected { border-color: #fff; outline: 2px solid rgba(255,255,255,0.3); outline-offset: 2px; }
.cm-freetext {
    width: 100%; margin-top: 10px; padding: 7px 10px; border-radius: 4px;
    border: 1px solid var(--border); background: rgba(0,0,0,0.3);
    color: var(--text); font-family: inherit; font-size: 0.78rem; resize: none;
}
.cm-freetext:focus { outline: none; border-color: var(--amber); }
.cm-intensity { display: flex; gap: 6px; align-items: center; }
.cm-int-btn {
    flex: 1; padding: 5px 4px; border-radius: 4px; font-size: 0.65rem; font-family: inherit;
    border: 1px solid var(--border); background: transparent; color: var(--text-muted);
    cursor: pointer; text-align: center; transition: all 0.12s;
}
.cm-int-btn:hover { border-color: var(--purple); color: var(--purple); }
.cm-int-btn.selected { border-color: var(--purple); color: #000; background: var(--purple); }
.cm-footer { padding: 12px 16px 0; flex-shrink: 0; display: flex; gap: 8px; }
.cm-use-btn {
    flex: 1; padding: 13px; border-radius: 5px; border: none;
    background: var(--teal); color: #000; font-family: inherit;
    font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    cursor: pointer; transition: filter 0.15s;
}
.cm-use-btn:hover { filter: brightness(1.12); }
.cm-clear-btn {
    padding: 13px 16px; border-radius: 5px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.75rem; cursor: pointer; transition: all 0.15s;
}
.cm-clear-btn:hover { border-color: var(--red); color: var(--red); }

/* ── ADD FRAMES MODAL: Browse tab extras ── */
.af-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.af-tab {
    flex: 1; padding: 8px 10px; font-size: 0.65rem; font-weight: 700; text-align: center;
    text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer;
    color: var(--text-muted); border-bottom: 2px solid transparent; transition: 0.15s;
}
.af-tab.active { color: var(--teal); border-bottom-color: var(--teal); }
.af-tab-content { display: none; flex: 1; flex-direction: column; overflow: hidden; }
.af-tab-content.active { display: flex; }

/* Browse sub-panel */
.af-browse-filters {
    flex-shrink: 0; padding: 8px 12px; display: flex; flex-direction: column; gap: 6px;
    border-bottom: 1px solid var(--border);
}
.af-browse-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.af-area-btn {
    padding: 3px 8px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.6rem; cursor: pointer; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.15s;
}
.af-area-btn.active { border-color: var(--teal); color: #000; background: var(--teal); }

/* Inline AJAX selects for browse */
.af-select-wrap { flex: 1; min-width: 0; position: relative; }
.af-search-input {
    width: 100%; padding: 5px 24px 5px 8px; border-radius: 4px; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.72rem; outline: none;
}
.af-search-input:focus { border-color: var(--teal); }
.af-search-clear {
    position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: var(--text-muted); cursor: pointer;
    font-size: 11px; padding: 2px; display: none;
}
.af-search-clear.visible { display: block; }
.af-dropdown {
    position: absolute; top: calc(100% + 2px); left: 0; right: 0; z-index: 99999;
    background: var(--card); border: 1px solid var(--border); border-radius: 4px;
    max-height: 160px; overflow-y: auto; display: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.6);
}
.af-dropdown.open { display: block; }
.af-option {
    padding: 7px 10px; font-size: 0.7rem; cursor: pointer; color: var(--text);
    border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; justify-content: space-between;
}
.af-option:hover { background: var(--teal-dim); }
.af-option .opt-id { font-size: 0.6rem; color: var(--text-muted); }

/* Browse grid inside modal */
.af-browse-grid-wrap { flex: 1; overflow-y: auto; padding: 8px; }
.af-browse-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px;
}
.af-frame-card {
    aspect-ratio: 1; border-radius: 3px; overflow: hidden; cursor: pointer;
    border: 2px solid transparent; position: relative; background: #111;
    transition: border-color 0.12s;
}
.af-frame-card:hover { border-color: var(--teal); }
.af-frame-card.selected { border-color: var(--text); }
.af-frame-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.af-frame-card .af-fid {
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7);
    font-size: 0.55rem; color: #aaa; padding: 2px 4px; text-align: center;
}
.af-frame-card.selected .af-fid { background: rgba(255,255,255,0.15); color: #fff; }
.af-browse-state { padding: 20px; text-align: center; font-size: 0.72rem; color: var(--text-muted); font-style: italic; }
</style>

<?php require_once "forge_tool.php"; ?>

<div class="eh-layout">
    <!-- Header -->
    <div class="eh-header">
        <div class="eh-title"><span>&#10024;</span>  <span style="font-size:0.7em; opacity:0.6; margin-left:8px;">Entities Mode</span></div>
        <div class="eh-nav">
            <label for="entitySelect" style="font-size:0.85rem; color:var(--text-muted); margin-right:8px;">Entity:</label>
            <select id="entitySelect" onchange="selectEntity(this.value)" style="background:var(--card); border:1px solid var(--border); color:var(--text); padding:6px 8px; border-radius:4px; font-family:inherit;">
                <?php foreach ($entityIcons as $ename => $icon): ?>
                    <option value="<?php echo htmlspecialchars($ename, ENT_QUOTES); ?>" <?php echo ($ename === $selectedEntity ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($icon . ' ' . $ename, ENT_QUOTES); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Active Forge Filters Bar -->
    <div class="forge-filters-bar" id="forgeActiveFilters">
        <div style="font-size:0.7rem; color:var(--text-muted); font-style:italic;">No active Forge filters.</div>
    </div>

    <!-- 1. List Top Panel -->
    <div class="eh-top-panel">
        <div class="mr-controls-row">
            <button id="btnMapRunToggle" class="map-run-toggle active" onclick="toggleMapRunMode()" title="Toggle Map Run Mode">Map Runs</button>
            <button class="map-run-toggle btn-forge-open" onclick="openForgeModal()" title="Open Forge Intersection Filter"><i class="bi bi-lightning-charge-fill"></i> Forge Filter</button>
            <button id="btnCreateEntity" class="map-run-toggle" onclick="createNewEntity()" style="display:none; padding: 4px 8px;" title="Create New Entity"><i class="bi bi-plus-lg"></i></button>
            <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search Run..." oninput="debounceSearch()">
            <div class="mr-pagination">
                <button class="pg-btn" id="mrPrev" onclick="changePage(-1)">&#8592;</button>
                <input type="number" class="pg-input" id="mrPageInput" value="1" onchange="jumpToPage()">
                <span class="pg-total" id="mrTotalPages">/ 1</span>
                <button class="pg-btn" id="mrNext" onclick="changePage(1)">&#8594;</button>
            </div>
        </div>
        <div class="sort-bar" id="entitySortBar" style="display:none; padding:4px 12px; border-bottom:1px solid rgba(255,255,255,0.05);">
            <span class="sort-bar-label"><i class="bi bi-sort-down"></i> Sort</span>
            <button class="sort-btn active" id="esort_id" onclick="setEntitySort('id')"><i class="bi bi-hash"></i> ID</button>
            <button class="sort-btn" id="esort_latest_frame" onclick="setEntitySort('latest_frame')"><i class="bi bi-images"></i> Latest Frame</button>
        </div>
        <div class="mr-list-scroll" id="mrList">
            <div class="state-msg">Loading...</div>
        </div>
    </div>

    <!-- 2. Config & Toolbar -->
    <div class="eh-mid-panel">
        <div class="config-bar">
            <div class="prompt-row">
                <input type="text" class="prompt-input" id="enhancePrompt" value="Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is" placeholder="Enhancement Instruction...">
                <button class="btn-compose" onclick="openComposeModal()" title="Quick-compose instruction">
                    <i class="bi bi-magic"></i> Compose
                </button>
            </div>
            <div class="chips-row">
                <div class="phrase-chips" id="clipboardChips"><span class="chips-empty">loading…</span></div>
                <button class="btn-cb-manage" onclick="openClipboardManager()"><i class="bi bi-clipboard2-plus"></i> Manage</button>
                <button class="btn-cb-manage" id="btnAddFrames" onclick="openAddFramesModal()" title="Add Additional Reference Frames"><i class="bi bi-images"></i> +Frames</button>
            </div>
        </div>
        <div class="grid-toolbar">
            <div class="gt-row1">
                <div class="gt-info" id="gridInfo">Select an item above</div>
                <div class="gt-actions" id="gridActions" style="display:none;">
                    <a id="lnkScrollMagic" href="#" target="_blank" class="action-btn" title="ScrollMagic">
                        <i class="bi bi-collection-play" style="font-size: 1.1em;"></i>
                    </a>
                    <div style="width:1px; background:var(--border); margin:0 8px;"></div>
                    <button class="action-btn" onclick="toggleAll(false)">None</button>
                    <button class="action-btn primary" onclick="toggleAll(true)" style="border-color:var(--text); color:var(--text);">All</button>
                </div>
            </div>
            <div class="gt-row2">
                <label class="chk-label" title="Hide frames imported to Animatics">
                    <input type="checkbox" id="hideImported" onchange="applyGridFilters()"> HideImp
                </label>
                <label class="chk-label" title="Hide frames already enhanced">
                    <input type="checkbox" id="hideEnhanced" onchange="applyGridFilters()"> HideEnh
                </label>
                <label class="chk-label" title="Show all frames without opacity/darkness indicators">
                    <input type="checkbox" id="showRaw" onchange="applyGridFilters()"> Raw
                </label>
                <label class="chk-label" title="Show one column grid for larger images">
                    <input type="checkbox" id="oneColGrid" onchange="toggleGridCols()"> 1Col
                </label>
                <label class="chk-label" title="Use depth2img for enhancement inference">
                    <input type="checkbox" id="useDepth2Img" style="accent-color: #3b82f6;"> d2i
                </label>
                <label class="chk-label" title="Use original entity description as enhancement prompt">
                    <input type="checkbox" id="useEntityPrompt" style="accent-color: #f59e0b;"> prompt
                </label>
            </div>
        </div>
    </div>

    <!-- 3. Grid -->
    <div class="eh-grid-area">
        <div class="state-msg" id="gridState"><div>&#8593; Select an Item</div></div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- Footer -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 selected</div>
        <div class="ft-actions">
            <button class="btn-action btn-stba"    id="btnStba"    disabled onclick="openSbPickerForFrames()">STBA</button>
            <button class="btn-action btn-enhance" id="btnEnhance" disabled onclick="submitEnhancement()">Enhance Frames</button>
            <button class="btn-action btn-import"  id="btnImport"  disabled onclick="submitImport()">Import to Animatics</button>
        </div>
    </div>
</div>

<!-- Frame viewer Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<!-- FORGE MODAL -->
<div class="compose-modal-backdrop" id="forgeModalBackdrop" onmousedown="onForgeBackdropClick(event)">
    <div class="compose-modal" id="forgeModal" style="max-height:85vh; max-width:600px;">
        <div class="cm-handle" onclick="closeForgeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title" style="color:var(--purple);"><i class="bi bi-lightning-charge-fill"></i> Forge Intersection Filter</div>
            <button type="button" class="cm-close-btn" onclick="closeForgeModal()"><i class="bi bi-x"></i></button>
        </div>
        <div class="cm-body" style="display:flex; padding:0;">
            <div class="forge-sidebar" style="width:140px; border-right:1px solid var(--border); padding:10px;">
                <button class="forge-sidebar-btn active" data-mode="fuzz" onclick="switchForgeMode('fuzz')">🧩 Fuzz</button>
                <button class="forge-sidebar-btn" data-mode="doc" onclick="switchForgeMode('doc')">📜 Lore Doc</button>
                <button class="forge-sidebar-btn" data-mode="kg" onclick="switchForgeMode('kg')">🌳 KG Node</button>
                <button class="forge-sidebar-btn" data-mode="seq" onclick="switchForgeMode('seq')">🎬 Sequence</button>
                <button class="forge-sidebar-btn" data-mode="storyboard" onclick="switchForgeMode('storyboard')">🖼️ Storyboard</button>
                <hr style="border-color:var(--border); margin:10px 0;">
                <button class="forge-sidebar-btn" data-mode="vector_text" onclick="switchForgeMode('vector_text')">🔍 Semantic Text</button>
            </div>
            <div style="flex:1; padding:10px; display:flex; flex-direction:column;">
                <input type="text" class="forge-search" id="forgeSearchInput" placeholder="Search...">
                <div id="forgeList" style="flex:1; overflow-y:auto; padding-right:5px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal -->
<div class="compose-modal-backdrop" id="composeBackdrop" onmousedown="onBackdropClick(event)">
    <div class="compose-modal" id="composeModal">
        <div class="cm-handle" onclick="closeComposeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-magic"></i> Quick Compose</div>
            <button type="button" class="cm-close-btn" onclick="closeComposeModal()"><i class="bi bi-x"></i></button>
        </div>
        <div class="cm-preview" id="cmPreview"></div>
        <div class="cm-body">
            <div class="cm-section"><div class="cm-section-title">Operation</div><div class="cm-tags" id="cm-ops"></div></div>
            <div class="cm-section"><div class="cm-section-title">Subject</div><div class="cm-tags" id="cm-subjects"></div></div>
            <div class="cm-section" id="cm-color-section" style="display:none;">
                <div class="cm-section-title">Color Change</div>
                <div class="cm-color-row"><span class="cm-color-label">From</span><div id="cm-from-swatches"></div></div>
                <div class="cm-color-row" style="margin-top:6px;"><span class="cm-color-label">To</span><div id="cm-to-swatches"></div></div>
            </div>
            <div class="cm-section"><div class="cm-section-title">Style / Modifier</div><div class="cm-tags" id="cm-modifiers"></div></div>
            <div class="cm-section"><div class="cm-section-title">Intensity</div><div class="cm-intensity" id="cm-intensity"></div></div>
            <div class="cm-section">
                <div class="cm-section-title">Custom detail (optional)</div>
                <textarea class="cm-freetext" id="cm-freetext" rows="2" placeholder="e.g. only on the left side, keep shadows…" oninput="updatePreview()"></textarea>
            </div>
            <div style="height:8px;"></div>
        </div>
        <div class="cm-footer">
            <button type="button" class="cm-clear-btn" onclick="clearCompose()"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button type="button" class="cm-use-btn" onclick="useComposed()">Use This ↑</button>
        </div>
    </div>
</div>

<!-- ADD FRAMES MODAL — upgraded with Browse tab -->
<div class="compose-modal-backdrop" id="addFramesBackdrop" onmousedown="onAddFramesBackdropClick(event)">
    <div class="compose-modal" id="addFramesModal">
        <div class="cm-handle" onclick="closeAddFramesModal(false)"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-images"></i> Additional Reference Frames</div>
            <button type="button" class="cm-close-btn" onclick="closeAddFramesModal(false)"><i class="bi bi-x"></i></button>
        </div>

        <!-- Tab bar -->
        <div class="af-tabs">
            <div class="af-tab active" id="afTab-id"     onclick="switchAfTab('id')">By Frame ID</div>
            <div class="af-tab"        id="afTab-browse" onclick="switchAfTab('browse')">Browse Variants</div>
        </div>

        <!-- Tab: By Frame ID (original behaviour) -->
        <div class="af-tab-content active" id="afContent-id">
            <div class="cm-body" style="padding:16px;">
                <p style="font-size:0.75rem; color:var(--text-muted); margin-top:0;">Add frames by entering their ID directly.</p>
                <div style="display:flex; gap:8px; margin-bottom:16px; align-items:center;">
                    <input type="number" id="addFrameIdInput" class="prompt-input" placeholder="Frame ID..." style="max-width:120px; flex:none;"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();addReferenceFrame();}">
                    <button type="button" class="btn-compose" onclick="addReferenceFrame()"><i class="bi bi-plus-lg"></i> Add Frame</button>
                </div>
                <div id="addFramesList" style="display:flex; flex-direction:column; gap:8px;"></div>
                <div style="height:8px;"></div>
            </div>
        </div>

        <!-- Tab: Browse Variants -->
        <div class="af-tab-content" id="afContent-browse">
            <!-- filters -->
            <div class="af-browse-filters">
                <div class="af-browse-row">
                    <button type="button" class="af-area-btn active" id="afAreaBtn-poses"       onclick="afSetArea('poses')">Poses</button>
                    <button type="button" class="af-area-btn"        id="afAreaBtn-expressions" onclick="afSetArea('expressions')">Expressions</button>
                    <button type="button" class="af-area-btn"        id="afAreaBtn-anima_poses" onclick="afSetArea('anima_poses')">Anima</button>
                </div>
                <div class="af-browse-row">
                    <div class="af-select-wrap">
                        <input type="text" class="af-search-input" id="afCharInput" placeholder="Character…" autocomplete="off"
                               oninput="afOnCharSearch()" onfocus="afOnCharFocus()" onblur="afOnCharBlur()"
                               onkeydown="return afHandleCharKeydown(event)">
                        <button type="button" class="af-search-clear" id="afCharClear" onclick="afClearChar()"><i class="bi bi-x"></i></button>
                        <div class="af-dropdown" id="afCharDropdown"></div>
                    </div>
                    <div class="af-select-wrap">
                        <input type="text" class="af-search-input" id="afVariantInput" placeholder="All variants…" autocomplete="off"
                               oninput="afOnVariantSearch()" onfocus="afOnVariantFocus()" onblur="afOnVariantBlur()"
                               onkeydown="return afHandleVariantKeydown(event)">
                        <button type="button" class="af-search-clear" id="afVariantClear" onclick="afClearVariant()"><i class="bi bi-x"></i></button>
                        <div class="af-dropdown" id="afVariantDropdown"></div>
                    </div>
                </div>
            </div>
            <!-- frame grid -->
            <div class="af-browse-grid-wrap">
                <div class="af-browse-state" id="afBrowseState">Select a character to browse frames</div>
                <div class="af-browse-grid" id="afBrowseGrid" style="display:none;"></div>
            </div>
        </div>

        <div class="cm-footer" style="padding-bottom:16px;">
            <button type="button" class="cm-clear-btn" onclick="closeAddFramesModal(false)">Cancel</button>
            <button type="button" class="cm-use-btn" onclick="closeAddFramesModal(true)">Confirm</button>
        </div>
    </div>
</div>

<!-- Storyboard Picker Bottom-Sheet -->
<div class="sb-picker-backdrop" id="sbPickerBackdrop" onmousedown="onSbPickerBackdropClick(event)">
    <div class="sb-picker-sheet" id="sbPickerSheet">
        <div class="sb-picker-handle" onclick="closeSbPicker()"><div class="sb-picker-handle-bar"></div></div>
        <div class="sb-picker-header">
            <div class="sb-picker-title"><i class="bi bi-film"></i> Assign to Storyboard</div>
            <button class="sb-picker-close" onclick="closeSbPicker()"><i class="bi bi-x"></i></button>
        </div>
        <div class="sb-picker-filters">
            <select class="sb-picker-select" id="sbPickerCatFilter" onchange="sbPickerRenderList()">
                <option value="all">All Categories</option>
            </select>
            <div class="sb-picker-editorial" id="sbPickerEditorial">
                <select class="sb-picker-select" id="sbPickerEpFilter" onchange="sbPickerOnEpChange()">
                    <option value="">All Episodes</option>
                </select>
                <select class="sb-picker-select" id="sbPickerSeqFilter" disabled onchange="sbPickerRenderList()">
                    <option value="">All Sequences</option>
                </select>
            </div>
        </div>
        <div class="sb-picker-list" id="sbPickerList">
            <div class="sb-picker-loading">Loading storyboards…</div>
        </div>
        <div class="sb-picker-footer">
            <a href="/view_storyboards.php" target="_blank">Manage Storyboards ↗</a>
        </div>
    </div>
</div>

<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
let curPage = 1, totalPages = 1, currentFrames = [], selectedFrameIds = new Set(), debounceTimer;
let currentRunId = null, currentEntityId = null;
const DEFAULT_PROMPT = "Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is";
let currentEntity = "<?php echo addslashes($selectedEntity); ?>";

const deepLinkRunId    = <?php echo $deepLinkRunId; ?>;
const deepLinkEntityId = <?php echo $deepLinkEntityId; ?>;
const deepLinkSearch   = <?php echo json_encode($deepLinkSearch); ?>;
// listMode: 'runs' | 'entities' | 'frames'
let listMode = <?php echo $mapRunDisabled ? "'entities'" : "'runs'"; ?>;
// legacy alias kept for any internal references
Object.defineProperty(window, 'mapRunMode', { get: () => listMode === 'runs', set: v => { listMode = v ? 'runs' : 'entities'; } });
let entitySort = 'id';

// ── FORGE FILTER VARIABLES ───────────────────────────
let forgeFilters = []; // Array of {type, id, text, label}
let activeForgeMode = 'fuzz';
let forgeSearchTimer;
const FORGE_MODES = {
    fuzz:        { icon: '🧩', label: 'Fuzz Candidate' },
    doc:         { icon: '📜', label: 'Lore Doc' },
    kg:          { icon: '🌳', label: 'KG Node' },
    seq:         { icon: '🎬', label: 'Sequence' },
    storyboard:  { icon: '🖼️', label: 'Storyboard' },
    vector_text: { icon: '🔍', label: 'Semantic Text' }
};

// ── FORGE MODAL LOGIC ────────────────────────────────
function openForgeModal() {
    document.getElementById('forgeModalBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    switchForgeMode(activeForgeMode);
}
function closeForgeModal() {
    document.getElementById('forgeModalBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}
function onForgeBackdropClick(e) {
    if (e.target === document.getElementById('forgeModalBackdrop')) closeForgeModal();
}
function switchForgeMode(mode) {
    activeForgeMode = mode;
    document.querySelectorAll('.forge-sidebar-btn').forEach(b => b.classList.remove('active'));
    const activeBtn = document.querySelector(`.forge-sidebar-btn[data-mode="${mode}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    const searchInp = document.getElementById('forgeSearchInput');
    searchInp.value = '';

    if (mode === 'vector_text') {
        searchInp.placeholder = 'Enter semantic search prompt...';
        document.getElementById('forgeList').innerHTML = `<div style="padding:10px; text-align:center;"><button class="btn-action btn-import" style="background:var(--purple);" onclick="addForgeFilter('vector_text', null, document.getElementById('forgeSearchInput').value)">Search Vector DB</button></div>`;
    } else if (mode === 'doc') {
        searchInp.placeholder = 'Search curated doc...';
        loadForgeItems();
    } else {
        searchInp.placeholder = `Search ${FORGE_MODES[mode]?.label || mode}...`;
        loadForgeItems();
    }
}

// doc 2-step state
let forgeDocStepDocId = null;
let forgeDocStepDocLabel = '';

const FORGE_DOC_LS = 'enhanimatics_forge_doc_sections';
function forgeDocSectionKey(docId, sectionLabel) { return `${docId}_${sectionLabel.replace(/\s+/g,'_')}`; }
function forgeDocSectionIsOpen(docId, sectionLabel) {
    try { const s = JSON.parse(localStorage.getItem(FORGE_DOC_LS) || '{}'); return s[forgeDocSectionKey(docId, sectionLabel)] !== false; }
    catch(e) { return true; }
}
function forgeDocSectionSetOpen(docId, sectionLabel, open) {
    try { const s = JSON.parse(localStorage.getItem(FORGE_DOC_LS) || '{}'); s[forgeDocSectionKey(docId, sectionLabel)] = open; localStorage.setItem(FORGE_DOC_LS, JSON.stringify(s)); }
    catch(e) {}
}

function forgeDocPickDoc(id, label) {
    forgeDocStepDocId = id;
    forgeDocStepDocLabel = label;
    const list = document.getElementById('forgeList');
    list.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem; padding:4px 0;">Loading entities...</div>';

    fetch(`?api_action=list_forge_items&mode=doc_entities&doc_id=${id}`)
        .then(r => r.json())
        .then(res => {
            list.innerHTML = '';

            // "Whole document" option always first
            const wholeEl = document.createElement('div');
            wholeEl.className = 'forge-item';
            wholeEl.style.cssText = 'border-color:var(--teal); margin-bottom:10px;';
            wholeEl.innerHTML = `<div style="font-weight:bold; font-size:0.85rem; color:var(--teal);">📜 Whole document</div><div style="font-size:0.7rem; color:var(--text-muted);">All sketches linked to: ${esc(label)}</div>`;
            wholeEl.onclick = () => addForgeFilter('doc', id, label);
            list.appendChild(wholeEl);

            if (res.status === 'success' && res.sections && res.sections.length > 0) {
                res.sections.forEach(section => {
                    const isOpen = forgeDocSectionIsOpen(id, section.section);

                    // Section header — collapsible
                    const hdrWrap = document.createElement('div');
                    hdrWrap.style.cssText = 'border-top:1px solid var(--border); margin-top:4px;';

                    const hdr = document.createElement('div');
                    hdr.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:7px 0 5px; cursor:pointer; user-select:none;';
                    hdr.innerHTML = `
                        <span style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; font-weight:700;">${esc(section.section)} <span style="color:var(--border);">(${section.items.length})</span></span>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <button class="sort-btn" style="padding:2px 7px; font-size:0.6rem;" onclick="event.stopPropagation(); addForgeFilterDoc(${id}, '${esc(label)}', null, '${esc(section.section)}', ${JSON.stringify(section.items)})">All ${esc(section.section)}</button>
                            <span class="forge-section-arrow" style="font-size:0.75rem; color:var(--text-muted); transition:transform 0.15s; display:inline-block; transform: rotate(${isOpen ? '90' : '0'}deg);">▶</span>
                        </div>`;
                    hdr.onclick = () => {
                        const body = hdrWrap.querySelector('.forge-section-body');
                        const arrow = hdr.querySelector('.forge-section-arrow');
                        const opening = body.style.display === 'none';
                        body.style.display = opening ? '' : 'none';
                        arrow.style.transform = `rotate(${opening ? '90' : '0'}deg)`;
                        forgeDocSectionSetOpen(id, section.section, opening);
                    };

                    const body = document.createElement('div');
                    body.className = 'forge-section-body';
                    body.style.display = isOpen ? '' : 'none';

                    section.items.forEach(entityName => {
                        const el = document.createElement('div');
                        el.className = 'forge-item';
                        el.style.cssText = 'padding:6px 10px; margin-bottom:3px;';
                        el.innerHTML = `<div style="font-size:0.8rem; color:var(--text);">${esc(entityName)}</div>`;
                        el.onclick = () => addForgeFilterDoc(id, label, entityName, null, null);
                        body.appendChild(el);
                    });

                    hdrWrap.appendChild(hdr);
                    hdrWrap.appendChild(body);
                    list.appendChild(hdrWrap);
                });
            } else if (res.status === 'error') {
                list.innerHTML += `<div style="color:var(--red); font-size:0.75rem; padding:8px 0;">${esc(res.message || 'Error loading entities')}</div>`;
            }

            // Back button
            const backEl = document.createElement('div');
            backEl.style.cssText = 'padding:10px 0 4px; text-align:center; border-top:1px solid var(--border); margin-top:8px;';
            backEl.innerHTML = `<button class="btn-cb-manage" onclick="switchForgeMode('doc')"><i class="bi bi-arrow-left"></i> Back to Docs</button>`;
            list.appendChild(backEl);
        })
        .catch(() => {
            list.innerHTML = '<div style="color:var(--red); font-size:0.75rem;">Network error loading entities</div>';
        });
}

// entityName = specific name, or null for whole section
// sectionLabel = section name (e.g. "Characters"), or null for specific entity
// sectionItems = array of names in section (for "whole section" filter), or null
function addForgeFilterDoc(docId, docLabel, entityName, sectionLabel, sectionItems) {
    forgeFilters = forgeFilters.filter(f => f.type !== 'doc');
    if (sectionLabel && sectionItems) {
        // Whole section filter — store all entity names from this section
        forgeFilters.push({
            type: 'doc',
            id: docId,
            entity_names: sectionItems, // array — PHP will OR-match all names
            entity_name: null,
            text: null,
            label: docLabel + ' › [' + sectionLabel + ']'
        });
    } else {
        forgeFilters.push({
            type: 'doc',
            id: docId,
            entity_name: entityName,
            text: null,
            label: docLabel + ' › ' + entityName
        });
    }
    renderForgeFilters();
    closeForgeModal();
    curPage = 1;
    loadList(1);
}
document.getElementById('forgeSearchInput').addEventListener('input', () => {
    if (activeForgeMode !== 'vector_text') {
        clearTimeout(forgeSearchTimer);
        forgeSearchTimer = setTimeout(loadForgeItems, 300);
    }
});
function loadForgeItems() {
    const q = document.getElementById('forgeSearchInput').value.trim();
    const list = document.getElementById('forgeList');
    list.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">Loading...</div>';

    fetch(`?api_action=list_forge_items&mode=${activeForgeMode}&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                list.innerHTML = '';
                if(res.data.length === 0) list.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">No items found.</div>';
                res.data.forEach(item => {
                    const el = document.createElement('div');
                    el.className = 'forge-item';
                    el.innerHTML = `<div style="font-weight:bold; font-size:0.85rem; color:var(--text);">${esc(item.label)}</div><div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">#${item.id} — ${item.meta}</div>`;
                    if (activeForgeMode === 'doc') {
                        // 2-step: clicking a doc goes to episode sub-picker
                        el.innerHTML += `<div style="font-size:0.65rem; color:var(--teal); margin-top:3px;">Tap to select scope →</div>`;
                        el.onclick = () => forgeDocPickDoc(item.id, item.label);
                    } else {
                        el.onclick = () => addForgeFilter(activeForgeMode, item.id, item.label);
                    }
                    list.appendChild(el);
                });
            }
        });
}
function addForgeFilter(type, id, labelOrText) {
    // Replace any existing filter of the same type
    forgeFilters = forgeFilters.filter(f => f.type !== type);
    forgeFilters.push({
        type: type,
        id: id,
        text: type === 'vector_text' ? labelOrText : null,
        label: labelOrText
    });
    renderForgeFilters();
    closeForgeModal();
    curPage = 1;
    loadList(1);
}
function removeForgeFilter(type) {
    forgeFilters = forgeFilters.filter(f => f.type !== type);
    renderForgeFilters();
    curPage = 1;
    loadList(1);
}
function renderForgeFilters() {
    const bar = document.getElementById('forgeActiveFilters');
    bar.innerHTML = '';
    if (forgeFilters.length === 0) {
        bar.innerHTML = '<div style="font-size:0.7rem; color:var(--text-muted); font-style:italic;">No active Forge filters.</div>';
        return;
    }
    forgeFilters.forEach(f => {
        const m = FORGE_MODES[f.type];
        const displayLabel = f.type === 'vector_text' ? `"${f.label}"` : f.label;
        const pill = document.createElement('div');
        pill.className = 'forge-pill';
        pill.innerHTML = `${m.icon} ${m.label}: ${esc(displayLabel)} <span class="forge-pill-close" onclick="removeForgeFilter('${f.type}')">&times;</span>`;
        bar.appendChild(pill);
    });
}

// ── CLIPBOARD CHIPS ──────────────────────────────────────
const CB_AREA = 'enhanimatics';

function loadClipboardChips() {
    fetch('clipboard_manager.php?api_action=cb_get&view_area=' + CB_AREA)
        .then(r => r.json())
        .then(res => { if (res.status === 'success') renderClipboardChips(res.data); })
        .catch(() => {});
}

function renderClipboardChips(items) {
    const container = document.getElementById('clipboardChips');
    container.innerHTML = '';
    if (!items || items.length === 0) { container.innerHTML = '<span class="chips-empty">Clipboard empty</span>'; return; }
    items.forEach(item => {
        const btn = document.createElement('button');
        btn.className = 'phrase-chip' + (parseInt(item.pinned) ? ' pinned' : '');
        let displayTxt = item.label ? item.label : item.content;
        btn.textContent = displayTxt.length > 28 ? displayTxt.slice(0, 27) + '…' : displayTxt;
        btn.title = item.content;
        btn.onclick = () => { document.getElementById('enhancePrompt').value = item.content; };
        container.appendChild(btn);
    });
}

function openClipboardManager() {
    const url = `clipboard_manager.php?view_area=${CB_AREA}`;
    const modal  = document.getElementById('frameDetailsModal');
    const iframe = document.getElementById('frameDetailsIframe');
    const loader = document.getElementById('ieLoadingOverlay');
    const pickerFooter = document.getElementById('iePickerFooter');
    if (modal && iframe) {
        if (pickerFooter) pickerFooter.style.display = 'none';
        if (loader) { loader.style.display = 'flex'; const p = loader.querySelector('p'); if (p) p.textContent = 'Loading Clipboard...'; }
        iframe.style.opacity = '0';
        iframe.src = url;
        modal.style.display = 'flex';
    } else {
        window.open(url, '_blank', 'width=400,height=600');
    }
}

window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'clipboard_updated' && e.data.view_area === CB_AREA) {
        renderClipboardChips(e.data.items);
    }
});

function addToHistory(prompt) {
    if (!prompt || prompt === DEFAULT_PROMPT) return;
    fetch(`clipboard_manager.php?api_action=cb_get&view_area=${CB_AREA}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                const exists = res.data.find(i => i.content === prompt);
                if (!exists) {
                    fetch(`clipboard_manager.php?api_action=cb_add&view_area=${CB_AREA}`, {
                        method: 'POST', headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ content: prompt, label: '' })
                    }).then(() => loadClipboardChips());
                }
            }
        });
}

// ══════════════════════════════════════════════════════
// ADD FRAMES MODAL — upgraded
// ══════════════════════════════════════════════════════
let tempReferenceFrames      = [];
let confirmedReferenceFrames = [];
let afArea       = 'poses';
let afCharId     = null;
let afVariantId  = null;
let afCharDebounce   = null;
let afVariantDebounce = null;

// ── Tab switching ─────────────────────────────────────
function switchAfTab(tab) {
    ['id','browse'].forEach(t => {
        document.getElementById('afTab-' + t).classList.toggle('active', t === tab);
        document.getElementById('afContent-' + t).classList.toggle('active', t === tab);
    });
}

// ── Open / Close ──────────────────────────────────────
function openAddFramesModal() {
    tempReferenceFrames = [...confirmedReferenceFrames];
    document.getElementById('addFrameIdInput').value = '';
    renderAddFramesList();
    renderAfBrowseGrid([]);
    document.getElementById('addFramesBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    switchAfTab('id');
}

function closeAddFramesModal(save) {
    if (save) { confirmedReferenceFrames = [...tempReferenceFrames]; updateAddFramesButton(); }
    document.getElementById('addFramesBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function onAddFramesBackdropClick(e) {
    if (e.target === document.getElementById('addFramesBackdrop')) closeAddFramesModal(false);
}
function onBackdropClick(e) {
    if (e.target === document.getElementById('composeBackdrop')) closeComposeModal();
}
function onSbPickerBackdropClick(e) {
    if (e.target === document.getElementById('sbPickerBackdrop')) closeSbPicker();
}

// ── By-ID tab (original logic, unchanged) ─────────────
function addReferenceFrame() {
    const input = document.getElementById('addFrameIdInput');
    const fid   = parseInt(input.value);
    if (!fid || isNaN(fid)) return;
    if (tempReferenceFrames.find(f => f.id === fid)) { input.value = ''; return; }
    fetch(`?api_action=get_single_frame&frame_id=${fid}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                tempReferenceFrames.push({ id: res.data.id, filename: res.data.filename });
                renderAddFramesList(); input.value = '';
            } else { Toast.show('Frame not found', 'error'); }
        });
}

function removeReferenceFrame(fid) {
    tempReferenceFrames = tempReferenceFrames.filter(f => f.id !== fid);
    renderAddFramesList();
    const card = document.querySelector(`.af-frame-card[data-fid="${fid}"]`);
    if (card) card.classList.remove('selected');
}

function renderAddFramesList() {
    const list = document.getElementById('addFramesList');
    list.innerHTML = '';
    if (!tempReferenceFrames.length) {
        list.innerHTML = '<div style="font-size:0.7rem; color:var(--text-muted); font-style:italic;">No additional frames assigned.</div>';
        return;
    }
    tempReferenceFrames.forEach(f => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:10px; background:rgba(255,255,255,0.05); padding:6px; border-radius:4px; border:1px solid var(--border);';
        row.innerHTML = `<img src="${f.filename}" style="width:40px;height:40px;object-fit:cover;border-radius:3px;"><div style="flex:1;font-size:0.75rem;color:var(--text);">Frame #${f.id}</div><button type="button" class="btn-remove-frame" onclick="removeReferenceFrame(${f.id})" title="Remove"><i class="bi bi-trash3"></i></button>`;
        list.appendChild(row);
    });
}

function updateAddFramesButton() {
    const btn = document.getElementById('btnAddFrames');
    if (confirmedReferenceFrames.length > 0) {
        btn.innerHTML = `<i class="bi bi-images"></i> +Frames (${confirmedReferenceFrames.length})`;
        btn.style.borderColor = 'var(--amber)'; btn.style.color = 'var(--amber)'; btn.style.background = 'var(--amber-dim)';
    } else {
        btn.innerHTML = `<i class="bi bi-images"></i> +Frames`;
        btn.style.borderColor = ''; btn.style.color = ''; btn.style.background = '';
    }
}

// ── Browse tab: area ──────────────────────────────────
function afSetArea(area) {
    afArea = area;
    ['poses','expressions','anima_poses'].forEach(a => {
        document.getElementById('afAreaBtn-' + a).classList.toggle('active', a === area);
    });
    afClearVariant();
    afLoadGrid();
}

// ── Browse tab: Keydown Handlers ──────────────────────
function afHandleCharKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); e.stopPropagation();
        const dd = document.getElementById('afCharDropdown');
        if (dd.classList.contains('open')) {
            const firstOpt = dd.querySelector('.af-option:not(.no-res)');
            if (firstOpt && firstOpt.onmousedown) firstOpt.onmousedown(e);
        }
        return false;
    }
    return true;
}

function afHandleVariantKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); e.stopPropagation();
        const dd = document.getElementById('afVariantDropdown');
        if (dd.classList.contains('open')) {
            const firstOpt = dd.querySelector('.af-option:not(.no-res)');
            if (firstOpt && firstOpt.onmousedown) firstOpt.onmousedown(e);
        }
        return false;
    }
    return true;
}

// ── Browse tab: character AJAX ────────────────────────
function afOnCharSearch()  { clearTimeout(afCharDebounce); afCharDebounce = setTimeout(afFetchChars, 250); }
function afOnCharFocus()   { afFetchChars(); }
function afOnCharBlur()    { setTimeout(() => afCloseDropdown('afCharDropdown'), 180); }

function afFetchChars() {
    const q = document.getElementById('afCharInput').value.trim();
    document.getElementById('afCharClear').classList.toggle('visible', q.length > 0);
    const dd = document.getElementById('afCharDropdown');

    if (q.length > 0 && !dd.classList.contains('open')) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">Searching…</div>';
        dd.classList.add('open');
    }

    fetch('?api_action=af_search_characters&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            afRenderDropdown('afCharDropdown', res.data, item => {
                afCharId = item.id;
                document.getElementById('afCharInput').value = item.name;
                document.getElementById('afCharClear').classList.add('visible');
                afCloseDropdown('afCharDropdown');
                afLoadGrid();
            });
        });
}

function afClearChar() {
    afCharId = null;
    document.getElementById('afCharInput').value = '';
    document.getElementById('afCharClear').classList.remove('visible');
    afClearVariant();
    afLoadGrid();
}

// ── Browse tab: variant AJAX ──────────────────────────
function afOnVariantSearch()  { clearTimeout(afVariantDebounce); afVariantDebounce = setTimeout(afFetchVariants, 250); }
function afOnVariantFocus()   { afFetchVariants(); }
function afOnVariantBlur()    { setTimeout(() => afCloseDropdown('afVariantDropdown'), 180); }

function afFetchVariants() {
    const q = document.getElementById('afVariantInput').value.trim();
    document.getElementById('afVariantClear').classList.toggle('visible', q.length > 0);
    const dd = document.getElementById('afVariantDropdown');

    if (q.length > 0 && !dd.classList.contains('open')) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">Searching…</div>';
        dd.classList.add('open');
    }

    fetch('?api_action=af_search_variants&area=' + encodeURIComponent(afArea) + '&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            afRenderDropdown('afVariantDropdown', res.data, item => {
                afVariantId = item.id;
                document.getElementById('afVariantInput').value = item.name;
                document.getElementById('afVariantClear').classList.add('visible');
                afCloseDropdown('afVariantDropdown');
                afLoadGrid();
            });
        });
}

function afClearVariant() {
    afVariantId = null;
    document.getElementById('afVariantInput').value = '';
    document.getElementById('afVariantClear').classList.remove('visible');
    afLoadGrid();
}

// ── Browse tab: dropdown helper ───────────────────────
function afRenderDropdown(dropId, items, onSelect) {
    const dd = document.getElementById(dropId);
    dd.innerHTML = '';
    if (!items.length) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">No results</div>';
    } else {
        items.slice(0, 100).forEach(item => {
            const el = document.createElement('div');
            el.className = 'af-option';
            el.innerHTML = `<span>${afEsc(item.name)}</span><span class="opt-id">#${item.id}</span>`;
            el.onmousedown = e => {
                e.preventDefault();
                e.stopPropagation();
                setTimeout(() => onSelect(item), 0);
            };
            dd.appendChild(el);
        });
    }
    dd.classList.add('open');
}

function afCloseDropdown(id) { document.getElementById(id).classList.remove('open'); }

// ── Browse tab: load frame grid ───────────────────────
function afLoadGrid() {
    if (!afCharId) {
        document.getElementById('afBrowseState').textContent = 'Select a character to browse frames';
        document.getElementById('afBrowseState').style.display = 'block';
        document.getElementById('afBrowseGrid').style.display = 'none';
        return;
    }

    document.getElementById('afBrowseState').textContent = 'Loading…';
    document.getElementById('afBrowseState').style.display = 'block';
    document.getElementById('afBrowseGrid').style.display = 'none';

    const params = new URLSearchParams({ api_action: 'af_get_variant_frames', area: afArea, char_id: afCharId });
    if (afVariantId) params.set('variant_id', afVariantId);

    fetch('?' + params.toString())
        .then(r => r.json())
        .then(res => {
            document.getElementById('afBrowseState').style.display = 'none';
            renderAfBrowseGrid(res.frames || []);
        })
        .catch(() => {
            document.getElementById('afBrowseState').textContent = 'Error loading frames';
            document.getElementById('afBrowseState').style.display = 'block';
        });
}

function renderAfBrowseGrid(frames) {
    const grid = document.getElementById('afBrowseGrid');
    grid.innerHTML = '';

    if (!frames.length) {
        document.getElementById('afBrowseState').textContent = 'No frames found for this selection';
        document.getElementById('afBrowseState').style.display = 'block';
        grid.style.display = 'none';
        return;
    }

    document.getElementById('afBrowseState').style.display = 'none';
    grid.style.display = 'grid';

    const selectedIds = new Set(tempReferenceFrames.map(f => f.id));

    frames.forEach(f => {
        const card = document.createElement('div');
        card.className = 'af-frame-card' + (selectedIds.has(f.frame_id) ? ' selected' : '');
        card.dataset.fid = f.frame_id;
        card.innerHTML = `<img src="${afEsc(f.filename)}" loading="lazy"><div class="af-fid">#${f.frame_id}</div>`;
        card.onclick = () => afToggleFrame(f.frame_id, f.filename, card);
        grid.appendChild(card);
    });
}

function afToggleFrame(fid, filename, card) {
    const idx = tempReferenceFrames.findIndex(f => f.id === fid);
    if (idx > -1) {
        tempReferenceFrames.splice(idx, 1);
        card.classList.remove('selected');
    } else {
        tempReferenceFrames.push({ id: fid, filename: filename });
        card.classList.add('selected');
    }
    renderAddFramesList();
}

function afEsc(s) { return s ? s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

// ══════════════════════════════════════════════════════
// OPS MENU
// ══════════════════════════════════════════════════════
let activeOpsMenu = null;

function toggleOpsMenu(e, id) {
    e.stopPropagation();
    const wrap = e.currentTarget.closest('.mr-ops-wrap');
    const menu = wrap.querySelector('.mr-ops-menu');
    if (activeOpsMenu && activeOpsMenu !== menu) activeOpsMenu.classList.remove('open');
    menu.classList.toggle('open');
    activeOpsMenu = menu.classList.contains('open') ? menu : null;
}

function closeAllOpsMenus() {
    document.querySelectorAll('.mr-ops-menu.open').forEach(m => m.classList.remove('open'));
    activeOpsMenu = null;
}

document.addEventListener('click', function() { closeAllOpsMenus(); });

function opsMenuRegen(e, runId) {
    e.stopPropagation(); closeAllOpsMenus();
    if (!confirm('Regenerate all images in run #' + runId + '?')) return;
    fetch('?api_action=regenerate_run&entity=' + encodeURIComponent(currentEntity), {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'map_run_id=' + runId
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') Toast.show('Marked ' + res.count + ' for regeneration');
        else Toast.show(res.message || 'Error', 'error');
    }).catch(() => Toast.show('Network error', 'error'));
}
function opsMenuScrollMagic(e, runId)  { e.stopPropagation(); closeAllOpsMenus(); window.open('view_scrollmagic_map_run.php?map_run_id=' + runId, '_blank'); }
function opsMenuStoryboard(e, runId)   { e.stopPropagation(); closeAllOpsMenus(); openSbPicker(runId); }

function toggleRegen(cb, id, col) {
    const val = cb.checked ? 1 : 0;
    const fd = new URLSearchParams();
    fd.append('entity_id', id); fd.append('value', val); fd.append('column', col);
    fetch(`?api_action=toggle_regenerate&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') Toast.show('Regenerate updated', 'success');
            else { cb.checked = !cb.checked; Toast.show(res.message || 'Error updating', 'error'); }
        }).catch(() => { cb.checked = !cb.checked; Toast.show('Network error', 'error'); });
}
function opsMenuEntityEdit(e, id)   { e.stopPropagation(); closeAllOpsMenus(); openEntityModal(id); }
function opsMenuEntityCopy(e, id) {
    e.stopPropagation(); closeAllOpsMenus();
    if (!confirm('Copy entity #' + id + '?')) return;
    const fd = new URLSearchParams(); fd.append('entity_id', id);
    fetch(`?api_action=copy_entity&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') { Toast.show('Copied', 'success'); loadList(curPage); }
            else Toast.show(res.message || 'Error', 'error');
        }).catch(() => Toast.show('Network error', 'error'));
}
function opsMenuEntityDelete(e, id) {
    e.stopPropagation(); closeAllOpsMenus();
    if (!confirm('Delete entity #' + id + '?')) return;
    const fd = new URLSearchParams(); fd.append('entity_id', id);
    fetch(`?api_action=delete_entity&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.status === 'success') { Toast.show('Deleted', 'success'); loadList(curPage); }
        });
}
function opsMenuEntityMatrix(e, id) {
    e.stopPropagation(); closeAllOpsMenus();
    window.open('view_prompt_matrix.php?entity_type=' + encodeURIComponent(currentEntity) + '&entity_id=' + id, '_blank');
}

// ══════════════════════════════════════════════════════
// STORYBOARD PICKER
// ══════════════════════════════════════════════════════
let sbPickerRunId = null;
let sbPickerMode  = null; // 'run' or 'frames'
let sbPickerData  = { boards: [], cats: [], eps: [] };
let sbPickerLoaded = false;

function openSbPicker(runId) {
    sbPickerMode = 'run';
    sbPickerRunId = runId;
    document.getElementById('sbPickerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    if (!sbPickerLoaded) sbPickerLoad(); else sbPickerRenderList();
}

function openSbPickerForFrames() {
    if (selectedFrameIds.size === 0) return;
    sbPickerMode = 'frames';
    document.getElementById('sbPickerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    if (!sbPickerLoaded) sbPickerLoad(); else sbPickerRenderList();
}

function closeSbPicker() {
    document.getElementById('sbPickerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function sbPickerLoad() {
    const list = document.getElementById('sbPickerList');
    list.innerHTML = '<div class="sb-picker-loading">Loading storyboards…</div>';
    fetch('?api_action=get_storyboards')
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = '<div class="sb-picker-empty">Failed to load</div>'; return; }
            sbPickerData = { boards: res.boards || [], cats: res.cats || [], eps: res.eps || [] };
            sbPickerLoaded = true;
            sbPickerBuildFilters();
            sbPickerRenderList();
        })
        .catch(() => { list.innerHTML = '<div class="sb-picker-empty">Network error</div>'; });
}

function sbPickerBuildFilters() {
    const catSel = document.getElementById('sbPickerCatFilter');
    catSel.innerHTML = '<option value="all">All Categories</option>';
    sbPickerData.cats.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name; o.dataset.code = c.code; catSel.appendChild(o); });
    const epSel = document.getElementById('sbPickerEpFilter');
    epSel.innerHTML = '<option value="">All Episodes</option>';
    sbPickerData.eps.forEach(ep => { const o = document.createElement('option'); o.value = ep.id; o.textContent = 'Ep ' + ep.number + ': ' + ep.name; epSel.appendChild(o); });
}

function sbPickerOnEpChange() { sbPickerRenderList(); }

function sbPickerRenderList() {
    const catSel = document.getElementById('sbPickerCatFilter');
    const catId  = catSel.value;
    const catCode = catSel.selectedOptions[0]?.dataset?.code || '';
    const isEd = catCode === 'editorial';
    document.getElementById('sbPickerEditorial').style.display = isEd ? 'flex' : 'none';
    const epId = document.getElementById('sbPickerEpFilter').value;
    let items = sbPickerData.boards;
    if (catId !== 'all') items = items.filter(b => String(b.category_id) === String(catId));
    if (isEd && epId) items = items.filter(b => String(b.episode_id) === String(epId));
    const list = document.getElementById('sbPickerList');
    list.innerHTML = '';
    if (!items.length) { list.innerHTML = '<div class="sb-picker-empty">No storyboards found</div>'; return; }
    items.forEach(sb => {
        let meta = '';
        if (sb.category_code === 'editorial' && sb.scene_name) meta = 'Ep ' + sb.episode_number + ' · ' + sb.scene_name;
        else meta = sb.custom_tag || sb.category_name || '';
        const el = document.createElement('div');
        el.className = 'sb-picker-item';
        el.innerHTML = `<div class="sb-picker-item-name">${esc(sb.name)}</div><div class="sb-picker-item-meta"><span>${esc(meta)}</span><span>${sb.frame_count} fr</span></div>`;
        el.onclick = () => sbPickerDoImport(sb.id, sb.name);
        list.appendChild(el);
    });
}

function sbPickerDoImport(storyboardId, storyboardName) {
    closeSbPicker();

    if (sbPickerMode === 'run') {
        if (!sbPickerRunId) return;
        const fd = new URLSearchParams();
        fd.append('map_run_id', sbPickerRunId); fd.append('storyboard_id', storyboardId);
        Toast.show('Importing run #' + sbPickerRunId + ' → ' + storyboardName + '…');
        fetch('?api_action=import_run_to_storyboard', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') { Toast.show('Imported ' + res.count + ' frames → "' + res.storyboard_name + '"'); if (window._sbCache) window._sbCache.boards = null; }
                else Toast.show(res.message || 'Import failed', 'error');
            })
            .catch(() => Toast.show('Network error', 'error'));
    }
    else if (sbPickerMode === 'frames') {
        if (selectedFrameIds.size === 0) return;
        const btn = document.getElementById('btnStba');
        if (btn) { btn.disabled = true; btn.textContent = '…'; }

        Toast.show('Adding ' + selectedFrameIds.size + ' frames → ' + storyboardName + '…');
        fetch('?api_action=import_frames_to_storyboard', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                frame_ids: Array.from(selectedFrameIds),
                storyboard_id: storyboardId
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Toast.show('Imported ' + res.count + ' frames → "' + res.storyboard_name + '"');
                selectedFrameIds.clear();
                document.querySelectorAll('.f-card.selected').forEach(c => c.classList.remove('selected'));
                updateSummary();
            } else {
                Toast.show(res.message || 'Assignment failed', 'error');
            }
        })
        .catch(() => Toast.show('Network error', 'error'))
        .finally(() => {
            if (btn) { btn.disabled = false; btn.textContent = 'STBA'; }
            updateSummary();
        });
    }
}

// ══════════════════════════════════════════════════════
// COMPOSE MODAL
// ══════════════════════════════════════════════════════
const CM_OPS = [
    { label: '🧹 Remove', value: 'Remove' }, { label: '🎨 Change color of', value: 'Change color of' },
    { label: '✨ Enhance', value: 'Enhance' }, { label: '💡 Adjust lighting on', value: 'Adjust lighting on' },
    { label: '🔍 Sharpen', value: 'Sharpen' }, { label: '🖌️ Stylize', value: 'Stylize' },
    { label: '🫥 Erase', value: 'Erase' }, { label: '🔄 Replace', value: 'Replace' },
];
const CM_SUBJECTS = [
    'all text & speech bubbles', 'people & characters', 'hair', 'eyes', 'skin', 'outfit / clothing',
    'background', 'shadows', 'highlights', 'outlines / edges', 'face',
    'hands', 'sky', 'water', 'fire', 'armor', 'weapon', 'logo / watermark',
];
const CM_COLORS = [
    { name: 'Red', hex: '#ef4444' }, { name: 'Orange', hex: '#f97316' }, { name: 'Yellow', hex: '#eab308' },
    { name: 'Green', hex: '#22c55e' }, { name: 'Teal', hex: '#14b8a6' }, { name: 'Blue', hex: '#3b82f6' },
    { name: 'Indigo', hex: '#6366f1' }, { name: 'Purple', hex: '#a855f7' }, { name: 'Pink', hex: '#ec4899' },
    { name: 'White', hex: '#f8fafc' }, { name: 'Silver', hex: '#94a3b8' }, { name: 'Black', hex: '#1e293b' },
    { name: 'Brown', hex: '#92400e' }, { name: 'Gold', hex: '#d97706' },
];
const CM_MODIFIERS  = ['naturally', 'seamlessly', 'dramatically', 'subtly', 'realistically', 'in anime style', 'in painterly style', 'with hard edges', 'with soft edges', 'while preserving everything else exactly as is'];
const CM_INTENSITIES = ['Lightly', 'Moderately', 'Strongly', 'Completely'];
let cmState = { op: '', subject: '', fromColor: '', toColor: '', modifier: '', intensity: '', freetext: '' };

function buildComposeModal() {
    const opsEl = document.getElementById('cm-ops');
    CM_OPS.forEach(o => {
        const btn = document.createElement('button'); btn.className = 'cm-tag'; btn.textContent = o.label;
        btn.onclick = () => { cmState.op = cmState.op === o.value ? '' : o.value; document.getElementById('cm-color-section').style.display = cmState.op === 'Change color of' ? '' : 'none'; syncTagGroup(opsEl, o.value, cmState.op); updatePreview(); };
        opsEl.appendChild(btn);
    });
    const subEl = document.getElementById('cm-subjects');
    CM_SUBJECTS.forEach(s => {
        const btn = document.createElement('button'); btn.className = 'cm-tag'; btn.textContent = s;
        btn.onclick = () => { cmState.subject = cmState.subject === s ? '' : s; syncTagGroup(subEl, s, cmState.subject); updatePreview(); };
        subEl.appendChild(btn);
    });
    const fromEl = document.getElementById('cm-from-swatches');
    fromEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;';
    CM_COLORS.forEach(c => { const sw = document.createElement('div'); sw.className = 'cm-color-swatch'; sw.style.background = c.hex; sw.title = c.name; sw.onclick = () => { cmState.fromColor = cmState.fromColor === c.name ? '' : c.name; syncSwatches(fromEl, c.name, cmState.fromColor); updatePreview(); }; fromEl.appendChild(sw); });
    const toEl = document.getElementById('cm-to-swatches');
    toEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;';
    CM_COLORS.forEach(c => { const sw = document.createElement('div'); sw.className = 'cm-color-swatch'; sw.style.background = c.hex; sw.title = c.name; sw.onclick = () => { cmState.toColor = cmState.toColor === c.name ? '' : c.name; syncSwatches(toEl, c.name, cmState.toColor); updatePreview(); }; toEl.appendChild(sw); });
    const modEl = document.getElementById('cm-modifiers');
    CM_MODIFIERS.forEach(m => { const btn = document.createElement('button'); btn.className = 'cm-tag'; btn.textContent = m; btn.onclick = () => { cmState.modifier = cmState.modifier === m ? '' : m; syncTagGroup(modEl, m, cmState.modifier); updatePreview(); }; modEl.appendChild(btn); });
    const intEl = document.getElementById('cm-intensity');
    CM_INTENSITIES.forEach(i => { const btn = document.createElement('button'); btn.className = 'cm-int-btn'; btn.textContent = i; btn.onclick = () => { cmState.intensity = cmState.intensity === i ? '' : i; syncIntensity(intEl, i, cmState.intensity); updatePreview(); }; intEl.appendChild(btn); });
}

function syncTagGroup(container, value, activeValue) {
    container.querySelectorAll('.cm-tag').forEach(b => { b.classList.toggle('selected', b.textContent.trim() === value && value === activeValue); });
}
function syncSwatches(container, name, activeName) {
    container.querySelectorAll('.cm-color-swatch').forEach(sw => { sw.classList.toggle('selected', sw.title === name && name === activeName); });
}
function syncIntensity(container, value, activeValue) {
    container.querySelectorAll('.cm-int-btn').forEach(b => { b.classList.toggle('selected', b.textContent === value && value === activeValue); });
}
function buildComposedString() {
    const parts = [];
    if (cmState.intensity) parts.push(cmState.intensity);
    if (cmState.op) parts.push(cmState.op);
    if (cmState.subject) parts.push(cmState.subject);
    if (cmState.op === 'Change color of' && cmState.fromColor) parts.push('from ' + cmState.fromColor);
    if (cmState.op === 'Change color of' && cmState.toColor)   parts.push('to '   + cmState.toColor);
    if (cmState.modifier) parts.push(cmState.modifier);
    if (cmState.freetext.trim()) parts.push(cmState.freetext.trim());
    return parts.join(' ');
}
function updatePreview() { cmState.freetext = document.getElementById('cm-freetext').value; document.getElementById('cmPreview').textContent = buildComposedString(); }
function clearCompose() {
    cmState = { op: '', subject: '', fromColor: '', toColor: '', modifier: '', intensity: '', freetext: '' };
    document.getElementById('cm-freetext').value = '';
    document.getElementById('cm-ops').querySelectorAll('.cm-tag').forEach(b => b.classList.remove('selected'));
    document.getElementById('cm-subjects').querySelectorAll('.cm-tag').forEach(b => b.classList.remove('selected'));
    document.getElementById('cm-from-swatches').querySelectorAll('.cm-color-swatch').forEach(b => b.classList.remove('selected'));
    document.getElementById('cm-to-swatches').querySelectorAll('.cm-color-swatch').forEach(b => b.classList.remove('selected'));
    document.getElementById('cm-modifiers').querySelectorAll('.cm-tag').forEach(b => b.classList.remove('selected'));
    document.getElementById('cm-intensity').querySelectorAll('.cm-int-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('cm-color-section').style.display = 'none';
    updatePreview();
}
function useComposed() { const str = buildComposedString().trim(); if (str) document.getElementById('enhancePrompt').value = str; closeComposeModal(); }
function openComposeModal()  { document.getElementById('composeBackdrop').classList.add('active');    document.body.style.overflow = 'hidden'; }
function closeComposeModal() { document.getElementById('composeBackdrop').classList.remove('active'); document.body.style.overflow = ''; }

// ══════════════════════════════════════════════════════
// PAGINATION & NAV PERSISTENCE
// ══════════════════════════════════════════════════════
const LS_KEY = 'enhanimatics_nav';
function saveNav() { try { localStorage.setItem(LS_KEY, JSON.stringify({ entity: currentEntity, page: curPage, mode: listMode })); } catch(e) {} }
function loadNav() { try { return JSON.parse(localStorage.getItem(LS_KEY) || 'null'); } catch(e) { return null; } }

function setEntitySort(key) {
    entitySort = key;
    document.getElementById('esort_id').classList.toggle('active', key === 'id');
    document.getElementById('esort_latest_frame').classList.toggle('active', key === 'latest_frame');
    curPage = 1;
    loadList(1);
}

function toggleMapRunMode() {
    // Cycle: runs → entities → frames → runs
    if (listMode === 'runs')     listMode = 'entities';
    else if (listMode === 'entities') listMode = 'frames';
    else listMode = 'runs';
    updateToggleUI();
    document.getElementById('mrSearch').value = '';
    curPage = 1;
    loadList(1);
}

function toggleGridCols() {
    const isOneCol = document.getElementById('oneColGrid').checked;
    document.getElementById('framesGrid').classList.toggle('one-col', isOneCol);
    try { localStorage.setItem('enhanimatics_one_col', isOneCol ? '1' : '0'); } catch(e) {}
}

function updateToggleUI() {
    const btn       = document.getElementById('btnMapRunToggle');
    const btnCreate = document.getElementById('btnCreateEntity');
    const sortBar   = document.getElementById('entitySortBar');
    const mrList    = document.getElementById('mrList');

    if (listMode === 'runs') {
        btn.classList.add('active'); btn.innerHTML = 'Map Runs';
        btn.style.borderColor = ''; btn.style.color = ''; btn.style.background = '';
        document.getElementById('mrSearch').placeholder = 'Search Run...';
        btnCreate.style.display = 'none';
        sortBar.style.display = 'none';
        mrList.style.display = '';
    } else if (listMode === 'entities') {
        btn.classList.remove('active'); btn.innerHTML = 'Entities';
        btn.style.borderColor = ''; btn.style.color = ''; btn.style.background = '';
        document.getElementById('mrSearch').placeholder = 'Search Entity...';
        btnCreate.style.display = 'flex';
        sortBar.style.display = 'flex';
        mrList.style.display = '';
    } else { // frames
        btn.classList.remove('active'); btn.innerHTML = 'Frames';
        btn.style.cssText = 'border-color:var(--amber); color:var(--amber); background:var(--amber-dim);';
        document.getElementById('mrSearch').placeholder = 'Search Frame ID or name...';
        btnCreate.style.display = 'none';
        sortBar.style.display = 'flex'; // keep sort bar for frames mode
        mrList.style.display = 'none';  // hide the list panel in frames mode
    }
}

function createNewEntity() {
    fetch(`?api_action=add_entity&entity=${encodeURIComponent(currentEntity)}`, { method: 'POST' })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Toast.show('Created new entity', 'success');
                curPage = 1;
                document.getElementById('mrSearch').value = '';
                loadList(1, () => {
                    const newEl = document.querySelector(`.mr-item[data-id="${res.id}"]`);
                    if (newEl) selectItem(res.id, newEl);
                    openEntityModal(res.id);
                });
            }
        });
}

function openEntityModal(id) {
    document.getElementById('frameViewer').src = `entity_form.php?entity_type=${encodeURIComponent(currentEntity)}&entity_id=${id}`;
    document.getElementById('viewModal').classList.add('active');
}

// ══════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadClipboardChips();
    buildComposeModal();

    try {
        if (localStorage.getItem('enhanimatics_one_col') === '1') {
            document.getElementById('oneColGrid').checked = true;
            document.getElementById('framesGrid').classList.add('one-col');
        }
    } catch(e) {}

    const sel = document.getElementById('entitySelect');
    if (sel) sel.value = currentEntity;

    if (deepLinkRunId) {
        listMode = 'runs'; updateToggleUI();
        document.getElementById('mrSearch').value = String(deepLinkRunId);
        loadList(1, () => { const fi = document.querySelector('.mr-item'); if (fi) fi.click(); });
    } else if (deepLinkEntityId) {
        listMode = 'entities'; updateToggleUI();
        document.getElementById('mrSearch').value = String(deepLinkEntityId);
        loadList(1, () => { const fi = document.querySelector('.mr-item'); if (fi) fi.click(); });
    } else if (deepLinkSearch) {
        const saved = loadNav();
        if (saved && saved.mode !== undefined && saved.entity === currentEntity) listMode = saved.mode;
        updateToggleUI();
        document.getElementById('mrSearch').value = deepLinkSearch;
        loadList(1);
    } else {
        const saved = loadNav();
        if (saved && saved.mode !== undefined && saved.entity === currentEntity) listMode = saved.mode;
        updateToggleUI();
        const startPage = (saved && saved.entity === currentEntity && saved.page > 1) ? saved.page : 1;
        loadList(startPage);
    }
});

function selectEntity(entity) {
    currentEntity = entity;
    const saved = loadNav();
    const startPage = (saved && saved.entity === entity && saved.page > 1) ? saved.page : 1;
    if (saved && saved.mode !== undefined && saved.entity === entity) { listMode = saved.mode; updateToggleUI(); }
    loadList(startPage);
}

function debounceSearch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadList(1), 300); }
function changePage(d)    { const n = curPage + d; if (n >= 1 && n <= totalPages) loadList(n); }
function jumpToPage()     { const v = parseInt(document.getElementById('mrPageInput').value); if (v >= 1 && v <= totalPages) loadList(v); }

function loadList(page, onLoaded) {
    const list   = document.getElementById('mrList');
    const search = document.getElementById('mrSearch').value.trim();
    if (page === 1 && list) list.scrollTop = 0;

    const filtersJson = encodeURIComponent(JSON.stringify(forgeFilters));

    // ── FRAMES MODE: load directly into grid ─────────────
    if (listMode === 'frames') {
        const sortParam = entitySort === 'latest_frame' ? 'entity_id' : 'id';
        const reqLimit = 50;
        const grid  = document.getElementById('framesGrid');
        const state = document.getElementById('gridState');
        state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Loading frames...</div>';
        grid.style.display = 'none';
        document.getElementById('gridActions').style.display = 'none';

        fetch(`?api_action=get_frames_direct&limit=${reqLimit}&offset=${(page-1)*reqLimit}&search=${encodeURIComponent(search)}&entity=${encodeURIComponent(currentEntity)}&sort=${sortParam}&filters=${filtersJson}`)
            .then(r => r.json()).then(res => {
                if (res.status !== 'success') return;
                curPage = page; totalPages = Math.ceil(res.total / reqLimit) || 1;
                document.getElementById('mrPageInput').value = curPage;
                document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
                saveNav();

                currentFrames = res.data;
                selectedFrameIds.clear();
                renderGrid();
                state.style.display = 'none'; grid.style.display = 'grid';
                document.getElementById('gridActions').style.display = 'flex';
                document.getElementById('gridInfo').innerHTML = `Frames • ${res.total} total • <em>${currentEntity}</em> • page ${curPage}`;
                updateSummary();
                if (typeof onLoaded === 'function') onLoaded();
            });
        return;
    }

    // ── RUNS / ENTITIES MODES ─────────────────────────────
    const endpoint  = listMode === 'runs' ? 'get_map_runs' : 'get_entities';
    const reqLimit  = listMode === 'runs' ? 4 : 1;
    const sortParam = listMode === 'runs' ? 'id' : entitySort;

    fetch(`?api_action=${endpoint}&limit=${reqLimit}&offset=${(page-1)*reqLimit}&search=${encodeURIComponent(search)}&entity=${encodeURIComponent(currentEntity)}&sort=${sortParam}&filters=${filtersJson}`)
        .then(r => r.json()).then(res => {
            if (res.status !== 'success') return;
            curPage = page; totalPages = Math.ceil(res.total / reqLimit) || 1;
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
            saveNav();
            list.innerHTML = '';
            if (!res.data.length) { list.innerHTML = `<div class="state-msg">No ${listMode === 'runs' ? 'runs' : 'entities'} found${forgeFilters.length ? ' matching intersection' : ''}</div>`; return; }
            res.data.forEach(item => {
                const isActive = (listMode === 'runs' && item.id == currentRunId) || (listMode === 'entities' && item.id == currentEntityId);
                const el = document.createElement('div');
                el.className = `mr-item ${isActive ? 'active' : ''}`;
                el.dataset.id = item.id;
                el.onclick = () => selectItem(item.id, el);
                if (listMode === 'runs') {
                    el.innerHTML = `
                        <div class="mr-id">#${item.id}</div>
                        <div class="mr-note">${esc(item.note||'No note')}</div>
                        <div class="mr-meta">${item.frame_count} fr • ${item.created_at.substr(0,10)}</div>
                        <div class="mr-ops-wrap" onclick="event.stopPropagation()">
                            <button class="btn-mr-ops" onclick="toggleOpsMenu(event,${item.id})"><i class="bi bi-three-dots-vertical"></i></button>
                            <div class="mr-ops-menu">
                                <button class="mr-ops-item" onclick="opsMenuRegen(event,${item.id})"><i class="bi bi-arrow-repeat"></i> Regen</button>
                                <button class="mr-ops-item" onclick="opsMenuScrollMagic(event,${item.id})"><i class="bi bi-collection-play"></i> Scromag</button>
                                <button class="mr-ops-item" onclick="opsMenuStoryboard(event,${item.id})"><i class="bi bi-film"></i> Storyboard</button>
                            </div>
                        </div>`;
                } else {
                    let regenCol = null, regenVal = 0;
                    if (item.regenerate_images !== undefined)  { regenCol = 'regenerate_images';  regenVal = item.regenerate_images; }
                    else if (item.regenerate_videos !== undefined) { regenCol = 'regenerate_videos'; regenVal = item.regenerate_videos; }
                    else if (item.regenerate_audios !== undefined) { regenCol = 'regenerate_audios'; regenVal = item.regenerate_audios; }
                    else if (item.regenerate !== undefined)    { regenCol = 'regenerate';           regenVal = item.regenerate; }
                    const regenHtml = regenCol ? `<div class="mr-regen-wrap" title="Regenerate?" onclick="event.stopPropagation()"><input type="checkbox" class="regen-checkbox" onchange="toggleRegen(this,${item.id},'${regenCol}')" ${parseInt(regenVal)===1?'checked':''}></div>` : '';
                    el.innerHTML = `
                        <div class="mr-id">#${item.id}</div>
                        <div class="mr-note">${esc(item.name||'Unnamed Entity')}</div>
                        <div class="mr-meta">${item.frame_count} fr • ${item.created_at ? item.created_at.substr(0,10) : ''}</div>
                        ${regenHtml}
                        <div class="mr-ops-wrap" onclick="event.stopPropagation()">
                            <button class="btn-mr-ops" onclick="toggleOpsMenu(event,${item.id})"><i class="bi bi-three-dots-vertical"></i></button>
                            <div class="mr-ops-menu">
                                <button class="mr-ops-item" onclick="opsMenuEntityEdit(event,${item.id})"><i class="bi bi-pencil"></i> Edit</button>
                                <button class="mr-ops-item" onclick="opsMenuEntityCopy(event,${item.id})"><i class="bi bi-files"></i> Copy</button>
                                <button class="mr-ops-item" onclick="opsMenuEntityMatrix(event,${item.id})"><i class="bi bi-grid-3x3"></i> Matrix</button>
                                <button class="mr-ops-item" onclick="opsMenuEntityDelete(event,${item.id})" style="color:var(--red);"><i class="bi bi-trash"></i> Delete</button>
                            </div>
                        </div>`;
                }
                list.appendChild(el);
            });

            // Auto-select for Entity Mode
            if (listMode === 'entities' && res.data.length === 1) {
                const firstEl = list.querySelector('.mr-item');
                if (firstEl) {
                    selectItem(res.data[0].id, firstEl);
                }
            }

            if (typeof onLoaded === 'function') onLoaded();
        });
}

function selectItem(id, el) {
    document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    const lnkScrollMagic = document.getElementById('lnkScrollMagic');
    if (listMode === 'runs') {
        currentRunId = id; currentEntityId = null;
        lnkScrollMagic.style.display = 'inline-flex';
        lnkScrollMagic.href = `view_scrollmagic_map_run.php?map_run_id=${id}`;
    } else {
        currentEntityId = id; currentRunId = null;
        lnkScrollMagic.style.display = 'none';
    }
    const grid  = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Loading...</div>';
    grid.style.display = 'none';
    document.getElementById('gridActions').style.display = 'none';
    const queryParam = mapRunMode ? `map_run_id=${id}` : `entity_id=${id}`;
    fetch(`?api_action=get_frames&${queryParam}&entity=${encodeURIComponent(currentEntity)}`).then(r => r.json()).then(res => {
        currentFrames = res.data;
        selectedFrameIds.clear();
        renderGrid();
        state.style.display = 'none'; grid.style.display = 'grid';
        document.getElementById('gridActions').style.display = 'flex';
        if (listMode === 'runs') document.getElementById('gridInfo').innerHTML = `Run <strong>#${id}</strong> • ${currentFrames.length} frames • <em>${currentEntity}</em>`;
        else                    document.getElementById('gridInfo').innerHTML = `Entity <strong>#${id}</strong> • ${currentFrames.length} frames • <em>${currentEntity}</em>`;
        updateSummary();
    });
}

function renderGrid() {
    const grid    = document.getElementById('framesGrid');
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    const showRaw = document.getElementById('showRaw').checked;
    grid.innerHTML = '';
    grid.classList.toggle('show-raw', showRaw);
    currentFrames.forEach(f => {
        const isImp = parseInt(f.is_imported) === 1;
        const isEnh = parseInt(f.is_enhanced) === 1;
        const card  = document.createElement('div');
        card.className = 'f-card';
        if (isImp) card.classList.add('is-imported');
        if (isEnh) card.classList.add('is-enhanced');
        if ((isImp && hideImp) || (isEnh && hideEnh)) card.classList.add('hidden-in-grid');
        card.dataset.fid      = f.frame_id;
        card.dataset.imported = isImp ? "1" : "0";
        card.dataset.enhanced = isEnh ? "1" : "0";
        const link = document.createElement('a');
        link.className = 'f-link'; link.href = f.filename; link.target = '_blank';
        link.dataset.pswpWidth = 1024; link.dataset.pswpHeight = 1024;
        const img = document.createElement('img'); img.src = f.filename; img.loading = 'lazy';
        img.onload = function() { link.dataset.pswpWidth = this.naturalWidth; link.dataset.pswpHeight = this.naturalHeight; };
        link.appendChild(img);
        const viewBtn = document.createElement('div');
        viewBtn.className = 'f-view-btn'; viewBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        viewBtn.onclick = (e) => { e.stopPropagation(); e.preventDefault(); openFrameModal(f.frame_id); };
        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = (e) => { e.preventDefault(); toggleFrame(f.frame_id, card); };
        const labelText = (listMode === 'frames' && f.entity_name)
            ? `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%;">${esc(f.entity_name)}</span>`
            : `<span>#${f.frame_id}</span>`;
        label.innerHTML = labelText + `<div class="f-select-trigger"></div>`;
        card.appendChild(link); card.appendChild(viewBtn); card.appendChild(label);
        grid.appendChild(card);
    });
    if (typeof PhotoSwipeLightbox !== 'undefined') {
        new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe }).init();
    }
}

function applyGridFilters() {
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    const showRaw = document.getElementById('showRaw').checked;
    document.getElementById('framesGrid').classList.toggle('show-raw', showRaw);
    document.querySelectorAll('.f-card').forEach(c => {
        const isImp = c.dataset.imported === "1";
        const isEnh = c.dataset.enhanced === "1";
        if ((isImp && hideImp) || (isEnh && hideEnh)) {
            if (c.classList.contains('selected')) { selectedFrameIds.delete(parseInt(c.dataset.fid)); c.classList.remove('selected'); }
            c.classList.add('hidden-in-grid');
        } else { c.classList.remove('hidden-in-grid'); }
    });
    updateSummary();
}

function toggleFrame(fid, card) {
    if (card.classList.contains('hidden-in-grid')) return;
    if (selectedFrameIds.has(fid)) { selectedFrameIds.delete(fid); card.classList.remove('selected'); }
    else { selectedFrameIds.add(fid); card.classList.add('selected'); }
    updateSummary();
}

function toggleAll(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        if (c.classList.contains('hidden-in-grid')) { c.classList.remove('selected'); return; }
        const fid = parseInt(c.dataset.fid);
        if (select) { selectedFrameIds.add(fid); c.classList.add('selected'); }
        else { selectedFrameIds.delete(fid); c.classList.remove('selected'); }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
    const disabled = count === 0;
    document.getElementById('btnEnhance').disabled = disabled;
    document.getElementById('btnImport').disabled  = disabled;
    const btnStba = document.getElementById('btnStba');
    if (btnStba) btnStba.disabled = disabled;
}

function submitImport() {
    performAction('submit_import', { frame_ids: Array.from(selectedFrameIds), entity: currentEntity }, 'Import to Animatics');
}

function submitEnhancement() {
    const prompt = document.getElementById('enhancePrompt').value.trim();
    const useEntityPrompt = document.getElementById('useEntityPrompt').checked;
    if (!prompt && !useEntityPrompt) { Toast.show('Enter instruction for enhancement', 'error'); return; }
    const extraFrames = confirmedReferenceFrames.map(f => f.id);
    const useD2i = document.getElementById('useDepth2Img').checked ? 1 : 0;
    performAction('submit_enhancement', { frame_ids: Array.from(selectedFrameIds), description: prompt, entity: currentEntity, extra_frames: extraFrames, depth2img: useD2i, use_entity_prompt: useEntityPrompt }, 'Enhance Frames');
}

function performAction(action, data, btnText) {
    const btnId = action === 'submit_import' ? 'btnImport' : 'btnEnhance';
    const btn   = document.getElementById(btnId);
    btn.disabled = true; btn.textContent = 'Processing...';
    fetch('?api_action=' + action + '&entity=' + encodeURIComponent(currentEntity), {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            Toast.show(`Success: ${res.count} frames processed`);
            const processedIds = new Set(data.frame_ids.map(Number));
            if (action === 'submit_enhancement') {
                addToHistory(data.description || '');
                if (!document.getElementById('useEntityPrompt').checked) {
                    document.getElementById('enhancePrompt').value = DEFAULT_PROMPT;
                }
                confirmedReferenceFrames = [];
                updateAddFramesButton();
                // Mark enhanced in current frame array — no refetch needed, preserves all grid context
                currentFrames = currentFrames.map(f =>
                    processedIds.has(Number(f.frame_id)) ? { ...f, is_enhanced: 1 } : f
                );
            } else if (action === 'submit_import') {
                // Mark imported in current frame array
                currentFrames = currentFrames.map(f =>
                    processedIds.has(Number(f.frame_id)) ? { ...f, is_imported: 1 } : f
                );
            }
            selectedFrameIds.clear();
            renderGrid();
            updateSummary();
        } else { Toast.show(res.message, 'error'); }
    }).finally(() => { btn.disabled = false; btn.textContent = btnText; updateSummary(); });
}

function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => {
        document.getElementById('frameViewer').src = '';
        if (listMode === 'entities') loadList(curPage);
    }, 200);
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeFrameModal(); closeComposeModal(); closeAllOpsMenus(); closeSbPicker();
        if (document.getElementById('addFramesBackdrop').classList.contains('active')) closeAddFramesModal(false);
        if (document.getElementById('forgeModalBackdrop').classList.contains('active')) closeForgeModal();
        document.querySelectorAll('.af-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});

function esc(s) { return s ? s.toString().replace(/"/g, '&quot;') : ''; }
</script>
<?php

echo $eruda ?? '';

$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
