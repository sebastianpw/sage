<?php
// public/stark.php
// Enhanimatics Clone -> "Stark Forge"
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php';

$allowedEntities = array_keys($entityIcons ?? []);
$selectedEntity = $_REQUEST['entity'] ?? 'sketches';
if (!in_array($selectedEntity, $allowedEntities, true)) { $selectedEntity = 'sketches'; }
$entityType = $selectedEntity;

$deepLinkRunId = isset($_GET['map_run_id']) ? (int)$_GET['map_run_id'] : 0;
$deepLinkEntityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$deepLinkSearch = isset($_GET['search']) ? $_GET['search'] : '';
$mapRunDisabled = isset($_GET['map_run_dis']) && $_GET['map_run_dis'] == '1';

$pageTitle = 'Stark Forge — Unified View';
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
    --bg: #0a0a0f; --card: #111118; --border: #1e1e2e;
    --text: #e2e2f0; --text-muted: #555570;
    --purple: #8b5cf6; --purple-dim: rgba(139, 92, 246, 0.1);
    --amber: #f59e0b; --amber-dim: rgba(245, 158, 11, 0.1);
    --red: #ef4444; --teal: #14b8a6; --teal-dim: rgba(20, 184, 166, 0.12);
}
[data-theme="light"] {
    --bg: #f4f4f8; --card: #ffffff; --border: #d0d0e0;
    --text: #1a1a2e; --text-muted: #888899;
}
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }
.eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* Header */
.eh-header {
    flex-shrink: 0; padding: 0 16px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.eh-title span { color: var(--teal); }
.eh-nav { display: flex; height: 100%; gap: 12px; align-items:center; }

/* Controls & Lists */
.mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); }
.map-run-toggle {
    flex-shrink: 0; padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
    text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;
}
.map-run-toggle.active { border-color: var(--teal); color: #000; background: var(--teal); box-shadow: 0 0 10px rgba(20, 184, 166, 0.4); }

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
.btn-mr-ops { flex-shrink: 0; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; }
.btn-mr-ops:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.mr-ops-menu { display: none; position: absolute; right: 0; top: calc(100% + 4px); background: var(--card); border: 1px solid var(--border); border-radius: 4px; min-width: 140px; z-index: 9999; box-shadow: 0 4px 16px rgba(0,0,0,0.5); }
.mr-ops-menu.open { display: block; }
.mr-ops-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; font-size: 0.72rem; color: var(--text-muted); cursor: pointer; border: none; background: none; width: 100%; text-align: left; }
.mr-ops-item:hover { background: rgba(255,255,255,0.06); color: var(--text); }

/* Sort Bar */
.sort-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: var(--card); }
.sort-bar-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; flex-shrink: 0; }
.sort-btn { padding: 3px 9px; border-radius: 20px; font-size: 0.65rem; font-family: inherit; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; transition: all 0.15s; white-space: nowrap; display: flex; align-items: center; gap: 4px; }
.sort-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.sort-btn.active { border-color: var(--purple); color: var(--text); background: var(--purple-dim); }

/* Active Forge Filters Bar */
.forge-filters-bar { padding: 6px 12px; display: flex; gap: 6px; align-items: center; border-bottom: 1px solid var(--border); background: var(--bg); flex-wrap: wrap; }
.forge-pill { background: rgba(139,92,246,0.15); border: 1px solid rgba(139,92,246,0.3); color: var(--purple); padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; display: flex; align-items: center; gap: 6px; font-weight: bold; }
.forge-pill-close { cursor: pointer; opacity: 0.7; font-size: 1.1em; }
.forge-pill-close:hover { opacity: 1; color: var(--red); }

/* Modals & Menus reused verbatim */
.compose-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 200000; display: none; align-items: flex-end; justify-content: center; }
.compose-modal-backdrop.active { display: flex; }
.compose-modal { width: 100%; max-width: 520px; background: var(--card); border: 1px solid var(--border); border-bottom: none; border-radius: 14px 14px 0 0; padding: 0 0 max(16px, env(safe-area-inset-bottom)); font-family: 'DM Mono', 'Fira Mono', monospace; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 -8px 40px rgba(0,0,0,0.6); animation: slideUp 0.22s ease; }
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.cm-header { padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.cm-title { font-size: 0.8rem; font-weight: 700; color: var(--purple); text-transform: uppercase; letter-spacing: 1px; }
.cm-close-btn { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--text); border-color: var(--text); }
.cm-body { overflow-y: auto; padding: 0 16px; flex: 1; }

/* Forge Modal specific styles */
.forge-sidebar { display: flex; flex-direction: column; gap: 4px; padding-bottom: 15px; }
.forge-sidebar-btn { padding: 8px 12px; background: transparent; border: none; color: var(--text-muted); text-align: left; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 0.8rem; }
.forge-sidebar-btn:hover { background: rgba(255,255,255,0.05); color: var(--text); }
.forge-sidebar-btn.active { background: var(--purple-dim); color: var(--purple); }
.forge-search { width: 100%; padding: 8px 12px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-family: inherit; font-size: 0.85rem; margin-bottom: 10px; outline: none; }
.forge-search:focus { border-color: var(--purple); }
.forge-item { padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; margin-bottom: 6px; transition: background 0.15s; }
.forge-item:hover { background: var(--purple-dim); border-color: var(--purple); }

/* Grid / Toolbar / Prompts (Same as Enhanimaticism) */
.eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
.config-bar { padding: 6px 12px 4px; display: flex; flex-direction: column; gap: 5px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.prompt-row { display: flex; gap: 6px; align-items: center; }
.prompt-input { flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: rgba(0,0,0,0.3); color: var(--text); font-family: inherit; font-size: 0.85rem; min-width: 0; }
.prompt-input:focus { outline: none; border-color: var(--amber); }
.btn-compose { flex-shrink: 0; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--teal); background: var(--teal-dim); color: var(--teal); font-family: inherit; font-size: 0.7rem; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px; }
.btn-compose:hover { background: var(--teal); color: #000; }
.chips-row { display: flex; gap: 8px; align-items: center; border-top: 1px solid rgba(255,255,255,0.04); padding: 5px 0; }
.phrase-chips { flex: 1; display: flex; gap: 5px; overflow-x: auto; scrollbar-width: none; min-width: 0; align-items: center; }
.phrase-chips::-webkit-scrollbar { display: none; }
.phrase-chip { flex-shrink: 0; padding: 3px 9px; border-radius: 20px; border: 1px solid var(--border); background: rgba(255,255,255,0.04); color: var(--text-muted); font-family: inherit; font-size: 0.65rem; cursor: pointer; white-space: nowrap; }
.btn-cb-manage { flex-shrink: 0; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-muted); font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.grid-toolbar { background: rgba(0,0,0,0.2); display: flex; flex-direction: column; }
.gt-row1 { padding: 6px 12px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.04); }
.gt-row2 { padding: 5px 12px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.gt-info { font-size: 0.7rem; color: var(--text-muted); }
.action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; display: inline-flex; }
.chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; }

/* Grid specific */
.eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: var(--bg); min-height: 0; }
.frames-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding-bottom: 20px; }
.frames-grid.one-col { grid-template-columns: 1fr; }
@media (min-width: 600px) { .frames-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); } }
.f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.f-card.selected { border-color: var(--text); box-shadow: 0 0 0 1px var(--text); }
.f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; font-size: 14px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
.f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; }
.f-card.selected .f-select-trigger { background: #fff; border-color: #fff; color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }

/* Footer */
.eh-footer { flex-shrink: 0; padding: 10px 16px; padding-bottom: max(10px, env(safe-area-inset-bottom)); background: var(--card); border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; z-index: 10; }
.ft-summary { font-size: 0.75rem; color: var(--text-muted); }
.ft-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.btn-action { padding: 10px 12px; border-radius: 4px; border: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; cursor: pointer; color: #fff; }
.btn-action:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }
.btn-enhance { background: var(--amber); color: #000; }
.btn-import { background: var(--purple); }
.btn-stba { background: var(--teal); color: #000; }
</style>

<?php require_once "forge_tool.php"; ?>

<div class="eh-layout">
    <div class="eh-header">
        <div class="eh-title"><span>⚡</span> Stark Forge Filter</div>
        <div class="eh-nav">
            <label for="entitySelect" style="font-size:0.85rem; color:var(--text-muted); margin-right:8px;">Entity:</label>
            <select id="entitySelect" onchange="selectEntity(this.value)" style="background:var(--card); border:1px solid var(--border); color:var(--text); padding:6px 8px; border-radius:4px; font-family:inherit;">
                <?php foreach ($entityIcons as $ename => $icon): ?>
                    <option value="<?= htmlspecialchars($ename, ENT_QUOTES) ?>" <?= ($ename === $selectedEntity ? 'selected' : '') ?>>
                        <?= htmlspecialchars($icon . ' ' . $ename, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Active Filters Bar -->
    <div class="forge-filters-bar" id="forgeActiveFilters">
        <div style="font-size:0.7rem; color:var(--text-muted); font-style:italic;">No active Forge filters.</div>
    </div>

    <div class="eh-top-panel">
        <div class="mr-controls-row">
            <button id="btnMapRunToggle" class="map-run-toggle active" onclick="toggleMapRunMode()">Map Runs</button>
            <button class="map-run-toggle btn-forge-open" onclick="openForgeModal()"><i class="bi bi-lightning-charge-fill"></i> Forge Filter</button>
            <button id="btnCreateEntity" class="map-run-toggle" onclick="createNewEntity()" style="display:none; padding:4px 8px;"><i class="bi bi-plus-lg"></i></button>
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

    <!-- Mid Panel (Config / Prompts) -->
    <div class="eh-mid-panel">
        <div class="config-bar">
            <div class="prompt-row">
                <input type="text" class="prompt-input" id="enhancePrompt" value="Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is" placeholder="Enhancement Instruction...">
                <button class="btn-compose" onclick="openComposeModal()"><i class="bi bi-magic"></i> Compose</button>
            </div>
            <div class="chips-row">
                <div class="phrase-chips" id="clipboardChips"><span class="chips-empty">loading…</span></div>
                <button class="btn-cb-manage" onclick="openClipboardManager()"><i class="bi bi-clipboard2-plus"></i> Manage</button>
                <button class="btn-cb-manage" id="btnAddFrames" onclick="openAddFramesModal()"><i class="bi bi-images"></i> +Frames</button>
            </div>
        </div>
        <div class="grid-toolbar">
            <div class="gt-row1">
                <div class="gt-info" id="gridInfo">Select an item above</div>
                <div class="gt-actions" id="gridActions" style="display:none;">
                    <a id="lnkScrollMagic" href="#" target="_blank" class="action-btn" title="ScrollMagic"><i class="bi bi-collection-play"></i></a>
                    <button class="action-btn" onclick="toggleAll(false)">None</button>
                    <button class="action-btn" onclick="toggleAll(true)" style="border-color:var(--text); color:var(--text);">All</button>
                </div>
            </div>
            <div class="gt-row2">
                <label class="chk-label"><input type="checkbox" id="hideImported" onchange="applyGridFilters()"> Hide Imp</label>
                <label class="chk-label"><input type="checkbox" id="hideEnhanced" onchange="applyGridFilters()"> Hide Enh</label>
                <label class="chk-label"><input type="checkbox" id="showRaw" onchange="applyGridFilters()"> Show Raw</label>
                <label class="chk-label"><input type="checkbox" id="oneColGrid" onchange="toggleGridCols()"> 1 Col</label>
                <label class="chk-label"><input type="checkbox" id="useDepth2Img" style="accent-color: #3b82f6;"> d2i</label>
            </div>
        </div>
    </div>

    <!-- Grid -->
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

<!-- FORGE MODAL -->
<div class="compose-modal-backdrop" id="forgeModalBackdrop" onmousedown="onForgeBackdropClick(event)">
    <div class="compose-modal" id="forgeModal" style="max-height:85vh; max-width:600px;">
        <div class="cm-handle" onclick="closeForgeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-lightning-charge-fill"></i> Stark Forge Intersection</div>
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

<script>
// --- CORE ENHANIMATICISM VARIABLES ---
let curPage = 1, totalPages = 1, currentFrames = [], selectedFrameIds = new Set(), debounceTimer;
let currentRunId = null, currentEntityId = null;
const DEFAULT_PROMPT = "Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is";
let currentEntity = "<?= addslashes($selectedEntity); ?>";

const deepLinkRunId    = <?= $deepLinkRunId; ?>;
const deepLinkEntityId = <?= $deepLinkEntityId; ?>;
const deepLinkSearch   = <?= json_encode($deepLinkSearch); ?>;
let mapRunMode = <?= $mapRunDisabled ? 'false' : 'true'; ?>;
let entitySort = 'id';

// --- FORGE FILTER VARIABLES ---
let forgeFilters = []; // Array of {type, id, text, label}
let activeForgeMode = 'fuzz';
let forgeSearchTimer;
const FORGE_MODES = {
    fuzz: { icon: '🧩', label: 'Fuzz Candidate' },
    doc: { icon: '📜', label: 'Lore Doc' },
    kg: { icon: '🌳', label: 'KG Node' },
    seq: { icon: '🎬', label: 'Sequence' },
    storyboard: { icon: '🖼️', label: 'Storyboard' },
    vector_text: { icon: '🔍', label: 'Semantic Text' }
};

// --- FORGE MODAL LOGIC ---
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
    document.querySelector(`.forge-sidebar-btn[data-mode="${mode}"]`).classList.add('active');
    
    const searchInp = document.getElementById('forgeSearchInput');
    searchInp.value = '';
    searchInp.placeholder = (mode === 'vector_text') ? 'Enter semantic search prompt...' : `Search ${FORGE_MODES[mode].label}...`;
    
    if (mode === 'vector_text') {
        document.getElementById('forgeList').innerHTML = `<div style="padding:10px; text-align:center;"><button class="btn-action btn-import" onclick="addForgeFilter('vector_text', null, document.getElementById('forgeSearchInput').value)">Search Vector DB</button></div>`;
    } else {
        loadForgeItems();
    }
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
    
    fetch(`stark_api.php?api_action=list_forge_items&mode=${activeForgeMode}&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                list.innerHTML = '';
                if(res.data.length === 0) list.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">No items found.</div>';
                res.data.forEach(item => {
                    const el = document.createElement('div');
                    el.className = 'forge-item';
                    el.innerHTML = `<div style="font-weight:bold; font-size:0.85rem; color:var(--text);">${esc(item.label)}</div><div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">#${item.id} — ${item.meta}</div>`;
                    el.onclick = () => addForgeFilter(activeForgeMode, item.id, item.label);
                    list.appendChild(el);
                });
            }
        });
}
function addForgeFilter(type, id, labelOrText) {
    // Only allow one filter of the exact same type for now, or replace
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
        bar.innerHTML += `
            <div class="forge-pill">
                ${m.icon} ${m.label}: ${esc(displayLabel)}
                <span class="forge-pill-close" onclick="removeForgeFilter('${f.type}')">&times;</span>
            </div>
        `;
    });
}

// --- STANDARD ENHANIMATICISM FETCH LOGIC ---
function loadList(page, onLoaded) {
    const list = document.getElementById('mrList');
    const search = document.getElementById('mrSearch').value.trim();
    if (page === 1) list.scrollTop = 0;
    
    const endpoint = mapRunMode ? 'get_map_runs' : 'get_entities';
    const reqLimit = mapRunMode ? 4 : 1;
    const sortParam = mapRunMode ? 'id' : entitySort;
    const filtersJson = encodeURIComponent(JSON.stringify(forgeFilters));

    fetch(`stark_api.php?api_action=${endpoint}&limit=${reqLimit}&offset=${(page-1)*reqLimit}&search=${encodeURIComponent(search)}&entity=${encodeURIComponent(currentEntity)}&sort=${sortParam}&filters=${filtersJson}`)
        .then(r => r.json()).then(res => {
            if (res.status !== 'success') return;
            curPage = page; totalPages = Math.ceil(res.total / reqLimit) || 1;
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
            saveNav();
            list.innerHTML = '';
            if (!res.data.length) { list.innerHTML = `<div class="state-msg">No ${mapRunMode ? 'runs' : 'entities'} found matching intersection.</div>`; return; }
            
            res.data.forEach(item => {
                const isActive = (mapRunMode && item.id == currentRunId) || (!mapRunMode && item.id == currentEntityId);
                const el = document.createElement('div');
                el.className = `mr-item ${isActive ? 'active' : ''}`;
                el.dataset.id = item.id;
                el.onclick = () => selectItem(item.id, el);
                
                if (mapRunMode) {
                    el.innerHTML = `<div class="mr-id">#${item.id}</div><div class="mr-note">${esc(item.note||'No note')}</div><div class="mr-meta">${item.frame_count} fr</div>`;
                } else {
                    el.innerHTML = `<div class="mr-id">#${item.id}</div><div class="mr-note">${esc(item.name||'Unnamed Entity')}</div><div class="mr-meta">${item.frame_count} fr</div>`;
                }
                list.appendChild(el);
            });

            if (!mapRunMode && res.data.length === 1) {
                const firstEl = list.querySelector('.mr-item');
                if (firstEl) selectItem(res.data[0].id, firstEl);
            }

            if (typeof onLoaded === 'function') onLoaded();
        });
}

function selectItem(id, el) {
    document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    
    if (mapRunMode) { currentRunId = id; currentEntityId = null; } 
    else { currentEntityId = id; currentRunId = null; }
    
    const grid = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Loading...</div>';
    grid.style.display = 'none';
    
    const queryParam = mapRunMode ? `map_run_id=${id}` : `entity_id=${id}`;
    fetch(`stark_api.php?api_action=get_frames&${queryParam}&entity=${encodeURIComponent(currentEntity)}`).then(r => r.json()).then(res => {
        currentFrames = res.data;
        selectedFrameIds.clear();
        renderGrid();
        state.style.display = 'none'; grid.style.display = 'grid';
        document.getElementById('gridActions').style.display = 'flex';
        document.getElementById('gridInfo').innerHTML = `Item <strong>#${id}</strong> • ${currentFrames.length} frames • <em>${currentEntity}</em>`;
        updateSummary();
    });
}

function renderGrid() {
    const grid = document.getElementById('framesGrid');
    const hideImp = document.getElementById('hideImported').checked;
    grid.innerHTML = '';
    currentFrames.forEach(f => {
        const isImp = parseInt(f.is_imported) === 1;
        if (isImp && hideImp) return;

        const card = document.createElement('div');
        card.className = 'f-card' + (isImp ? ' is-imported' : '');
        card.dataset.fid = f.frame_id;
        
        const link = document.createElement('a');
        link.className = 'f-link'; link.href = f.filename; link.target = '_blank';
        link.dataset.pswpWidth = 1024; link.dataset.pswpHeight = 1024;
        
        const img = document.createElement('img'); img.src = f.filename; img.loading = 'lazy';
        img.onload = function() { link.dataset.pswpWidth = this.naturalWidth; link.dataset.pswpHeight = this.naturalHeight; };
        link.appendChild(img);
        
        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = (e) => { e.preventDefault(); toggleFrame(f.frame_id, card); };
        label.innerHTML = `<span>#${f.frame_id}</span><div class="f-select-trigger"></div>`;
        
        card.appendChild(link); card.appendChild(label);
        grid.appendChild(card);
    });
    if (typeof PhotoSwipeLightbox !== 'undefined') {
        new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe }).init();
    }
}

// Checkboxes and Actions (same as original)
function toggleFrame(fid, card) {
    if (selectedFrameIds.has(fid)) { selectedFrameIds.delete(fid); card.classList.remove('selected'); }
    else { selectedFrameIds.add(fid); card.classList.add('selected'); }
    updateSummary();
}
function toggleAll(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        const fid = parseInt(c.dataset.fid);
        if (select) { selectedFrameIds.add(fid); c.classList.add('selected'); }
        else { selectedFrameIds.delete(fid); c.classList.remove('selected'); }
    });
    updateSummary();
}
function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
    document.getElementById('btnEnhance').disabled = count === 0;
    document.getElementById('btnImport').disabled = count === 0;
    if(document.getElementById('btnStba')) document.getElementById('btnStba').disabled = count === 0;
}
function applyGridFilters() { renderGrid(); }
function toggleGridCols() { document.getElementById('framesGrid').classList.toggle('one-col', document.getElementById('oneColGrid').checked); }

// Basic Nav
function debounceSearch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadList(1), 300); }
function changePage(d)    { const n = curPage + d; if (n >= 1 && n <= totalPages) loadList(n); }
function jumpToPage()     { const v = parseInt(document.getElementById('mrPageInput').value); if (v >= 1 && v <= totalPages) loadList(v); }

// Initialization
document.addEventListener('DOMContentLoaded', () => {
    // Keep identical initialization sequence
    if (deepLinkRunId) { mapRunMode = true; document.getElementById('mrSearch').value = String(deepLinkRunId); loadList(1); } 
    else if (deepLinkEntityId) { mapRunMode = false; document.getElementById('mrSearch').value = String(deepLinkEntityId); loadList(1); } 
    else { const startPage = 1; loadList(startPage); }
});

function selectEntity(entity) {
    currentEntity = entity;
    loadList(1);
}
function toggleMapRunMode() {
    mapRunMode = !mapRunMode;
    const btn = document.getElementById('btnMapRunToggle');
    if (mapRunMode) { btn.classList.add('active'); document.getElementById('mrSearch').placeholder = 'Search Run...'; } 
    else { btn.classList.remove('active'); document.getElementById('mrSearch').placeholder = 'Search Entity...'; }
    curPage = 1; loadList(1);
}

function setEntitySort(key) {
    entitySort = key;
    document.getElementById('esort_id').classList.toggle('active', key === 'id');
    document.getElementById('esort_latest_frame').classList.toggle('active', key === 'latest_frame');
    loadList(1);
}
function esc(s) { return s ? s.toString().replace(/"/g, '&quot;') : ''; }
// Nav Storage
const LS_KEY = 'stark_forge_nav';
function saveNav() { try { localStorage.setItem(LS_KEY, JSON.stringify({ entity: currentEntity, page: curPage, mode: mapRunMode })); } catch(e) {} }
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>