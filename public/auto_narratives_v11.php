<?php
// public/auto_narratives_v11.php
// Showrunner V11 - Graph-Augmented Auto-Narrative Laboratory (CLONE)
// ----------------------------------------------------
// V11 UI Updates:
//   - Wired to auto_narratives_api_v11.php
//   - Added V11 Hybrid Engine Settings inside the Advanced Filter modal
//     (Enable Graph-Walker, Enable SQL Tag Striker, Enable Chroma Sweep)
//   - Filter payload dynamically bundles these toggles.
// ----------------------------------------------------
// V5 UI Updates:
//   - DEBUG_MODE constant: when true, full structured debug log is
//     collected client-side (all Chroma queries, AI queries, results)
//     and a "Download Debug Log" button appears after each run.
//   - "Use AI" checkbox: passes use_ai=1 to API.
//   - "Single Run" checkbox (checked by default): runs once then stops.
//   - AI status indicator in monitor: shows when AI is active.
//   - Debug log shows full query payloads, AI input/output, vector
//     results — everything the API returns in logs + debug_data.
// V9.2: Entity Preview ("Peek") button in Advanced Filter
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// ============================================================
// DEBUG MODE — set to false in production
// When true: API returns extended debug_data, UI collects a full
// structured debug log and offers a JSON download after each run.
// ============================================================
const DEBUG_MODE = true;

$pageTitle = 'Auto-Narrative Lab V11';

// Load Context Docs
$docsRaw = $pdo->query("
    SELECT d.id, d.name 
    FROM documentations d 
    JOIN md_doc_analysis da ON d.id = da.doc_id 
    WHERE da.narrative_utility IS NOT NULL 
    ORDER BY da.narrative_utility DESC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    :root { 
        --term-bg: #0c0c0c; 
        --term-text: #33ff00; 
        --accent: #8b5cf6; 
        --panel-bg: #111; 
        --border-color: #333; 
    }
    
    html, body { height: 100%; margin: 0; overflow: hidden; background: #000; }

    /* Layout Grid */
    .lab-layout {
        display: grid;
        grid-template-rows: 50px 1fr;
        grid-template-columns: 1fr;
        height: 100vh;
        width: 100vw;
    }

    .lab-content {
        display: grid;
        grid-template-columns: 1fr 320px;
        height: 100%;
        overflow: hidden;
    }

    @media (max-width: 768px) {
        .lab-content { grid-template-columns: 1fr 240px; }
    }

    /* Header */
    .lab-header {
        grid-row: 1;
        background: #1a1a1a;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        padding: 0 15px;
        justify-content: space-between;
        z-index: 10;
    }
    .lab-title { font-size: 1.1rem; font-weight: 800; color: var(--accent); display: flex; align-items: center; gap: 10px; }
    
    /* LEFT COLUMN STACK */
    .left-stack {
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--border-color);
        background: var(--bg);
        overflow: hidden;
        position: relative;
    }
    
    /* Config Panel */
    .config-panel {
        flex: 0 0 auto;
        padding: 15px;
        background: #111;
        border-bottom: 1px solid var(--border-color);
        max-height: 55vh;
        overflow-y: auto;
    }

    /* Monitor Terminal */
    .monitor-panel {
        flex: 1;
        background: var(--term-bg);
        padding: 15px;
        font-family: 'Courier New', monospace;
        color: var(--term-text);
        overflow-y: auto;
        font-size: 0.85rem;
        line-height: 1.4;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        border-bottom: 1px solid var(--border-color);
    }
    .log-line { margin-bottom: 4px; word-wrap: break-word; }
    .log-info    { color: #60a5fa; } 
    .log-success { color: #34d399; } 
    .log-warn    { color: #fbbf24; }
    .log-ai      { color: #c084fc; } /* Purple for AI events */
    .log-debug   { color: #f97316; } /* Orange for debug events */
    .log-vector  { color: #38bdf8; } /* Sky blue for vector queries */

    /* Visual Strip */
    .visual-strip {
        height: 140px;
        background: #000;
        display: flex;
        align-items: center;
        overflow-x: auto;
        padding: 0 10px;
        gap: 8px;
        border-top: 1px solid var(--border-color);
        flex-shrink: 0;
    }
    .forge-frame { 
        height: 110px; aspect-ratio: 16/9; background: #222; border: 1px solid #444; 
        position: relative; flex-shrink: 0; border-radius: 4px; overflow: hidden; 
    }
    .forge-frame img { width: 100%; height: 100%; object-fit: cover; opacity: 0.9; }

    /* RIGHT COLUMN (Results) */
    .results-panel {
        background: #080808;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .results-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
    }
    .result-card { 
        background: #161616; border: 1px solid #333; padding: 10px; border-radius: 6px; 
        margin-bottom: 8px; border-left: 3px solid #444; 
    }
    .result-card:hover { border-color: var(--accent); background: #222; }
    .result-card.promoted { border-left-color: #10b981; opacity: 0.6; }
    .res-title { font-weight: 700; font-size: 0.85rem; color: #fff; margin-bottom: 4px; }
    .res-meta { font-size: 0.7rem; color: #888; display: flex; justify-content: space-between; margin-bottom: 6px; }
    .res-actions { display: flex; gap: 4px; }
    .res-btn { flex: 1; background: #2a2a2a; border: 1px solid #444; color: #ccc; font-size: 0.7rem; padding: 6px 0; cursor: pointer; border-radius: 4px; text-align: center; }
    .res-btn:hover { background: #333; color: white; }
    .res-btn.promote { color: #10b981; border-color: rgba(16,185,129,0.2); }
    .res-btn.delete { color: #ef4444; max-width: 30px; }

    /* Form Controls */
    .control-group { margin-bottom: 12px; }
    .c-label { display: block; font-size: 0.75rem; margin-bottom: 4px; color: #888; font-weight: 700; }
    .c-select, .c-input { width: 100%; padding: 8px; background: #222; border: 1px solid #444; color: #eee; border-radius: 4px; font-size: 0.9rem; }

    /* Run Controls Row */
    .run-controls-row {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #2a2a2a;
        flex-wrap: wrap;
    }

    /* Checkboxes */
    .check-group {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        font-size: 0.8rem;
        color: #aaa;
        user-select: none;
        white-space: nowrap;
    }
    .check-group input[type=checkbox] {
        width: 15px;
        height: 15px;
        accent-color: var(--accent);
        cursor: pointer;
        flex-shrink: 0;
    }
    .check-group:hover { color: #fff; }

    /* AI Status Pill */
    .ai-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.7rem;
        padding: 3px 8px;
        border-radius: 20px;
        border: 1px solid #444;
        color: #888;
        background: #1a1a1a;
        margin-left: auto;
    }
    .ai-status-pill.active {
        color: #c084fc;
        border-color: rgba(192,132,252,0.4);
        background: rgba(139,92,246,0.1);
    }
    .ai-status-pill .ai-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #555;
    }
    .ai-status-pill.active .ai-dot {
        background: #c084fc;
        box-shadow: 0 0 6px #c084fc;
        animation: ai-pulse 1.5s infinite;
    }
    @keyframes ai-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
    
    .btn-start { 
        flex: 1;
        padding: 11px; 
        background: var(--accent); color: white; border: none; font-weight: 800; 
        cursor: pointer; border-radius: 4px; font-size: 0.95rem;
        min-width: 130px;
    }
    .btn-start.running { background: #b91c1c; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

    .btn-filter { background: #333; border: 1px solid #555; color: white; padding: 8px; border-radius: 4px; cursor: pointer; font-size: 0.9rem; margin-bottom: 2px; }
    .btn-filter:hover { background: #444; border-color: var(--accent); }
    .filter-badge { font-size: 0.7rem; color: var(--accent); margin-top: 5px; display: none; font-family: monospace; }

    /* Debug Download Button */
    .btn-debug-download {
        display: none; /* shown only in debug mode after run */
        width: 100%;
        padding: 8px;
        background: rgba(249,115,22,0.15);
        border: 1px solid rgba(249,115,22,0.5);
        color: #fb923c;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        border-radius: 4px;
        margin-top: 8px;
        text-align: center;
        font-family: monospace;
    }
    .btn-debug-download:hover { background: rgba(249,115,22,0.25); }

    /* FILTER MODAL STYLES */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-content { background: #1a1a1a; width: 95%; max-width: 900px; height: 85vh; padding: 20px; border-radius: 8px; border: 1px solid #333; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
    
    .filter-cols { display: flex; gap: 12px; flex: 1; min-height: 0; margin-top: 10px; }
    .filter-col { flex: 1; border: 1px solid #333; border-radius: 6px; display: flex; flex-direction: column; background: #111; overflow: hidden; }
    
    .f-head { padding: 10px; background: #222; border-bottom: 1px solid #333; font-weight: bold; font-size: 0.8rem; text-align: center; color: #888; text-transform: uppercase; }
    .f-list { flex: 1; overflow-y: auto; padding: 6px; display: flex; flex-direction: column; gap: 4px; }
    
    .f-item { 
        padding: 8px 10px; background: #191919; border: 1px solid #2a2a2a; border-radius: 4px;
        color: #ddd; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center;
        cursor: default; 
    }
    .f-item:hover { background: #252525; border-color: #555; }
    .f-item.active { background: var(--accent); color: white; border-color: var(--accent); }

    /* DRAG HANDLE + PEEK BUTTON ROW FOR FILTER ITEMS */
    .filter-item-controls {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
        margin-left: 8px;
    }
    
    .filter-drag-handle {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #666; cursor: grab; background: rgba(255,255,255,0.05);
        border-radius: 4px; font-family: monospace; border: 1px solid rgba(255,255,255,0.1); font-size: 0.8rem;
        flex-shrink: 0;
    }
    .filter-drag-handle:hover { color: var(--accent); background: rgba(255,255,255,0.1); border-color: var(--accent); }

    /* PEEK BUTTON — same size as drag handle */
    .filter-peek-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #666;
        cursor: pointer;
        background: rgba(255,255,255,0.05);
        border-radius: 4px;
        border: 1px solid rgba(255,255,255,0.1);
        font-size: 0.8rem;
        flex-shrink: 0;
        transition: all 0.15s;
        line-height: 1;
    }
    .filter-peek-btn:hover {
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.1);
        border-color: rgba(245, 158, 11, 0.4);
    }
    
    .pot-item { background: rgba(139,92,246,0.1); border: 1px solid var(--accent); color: #d8b4fe; cursor: default; }
    .pot-item .remove-btn { color: #ff6b6b; font-weight: bold; padding: 4px 8px; cursor: pointer; border-radius: 4px; }
    .pot-item .remove-btn:hover { background: rgba(255,0,0,0.2); }

    /* ENTITY PREVIEW MODAL (Peek) — sits above the filter modal */
    #entity-preview-modal {
        z-index: 5000; /* above filter modal at 4000 */
    }
    #entity-preview-modal .modal-content {
        max-width: 700px;
        height: auto;
        max-height: 80vh;
        overflow-y: auto;
    }
    .preview-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid #333;
        gap: 12px;
    }
    .preview-cat-badge {
        font-size: 0.7rem;
        padding: 3px 9px;
        border-radius: 12px;
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.3);
        text-transform: uppercase;
        font-weight: 700;
        white-space: nowrap;
        flex-shrink: 0;
        margin-top: 4px;
    }
    .preview-section { margin-bottom: 14px; }
    .preview-section-title {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #888;
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }
    .preview-value { font-size: 0.92rem; line-height: 1.55; color: #ddd; }
    .preview-pill-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
    .preview-pill {
        font-size: 0.78rem;
        padding: 2px 10px;
        border-radius: 10px;
        background: rgba(255,255,255,0.06);
        border: 1px solid #333;
        color: #ccc;
    }
    .preview-kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
    .preview-kv-key { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #888; margin-bottom: 2px; }
    .preview-kv-val { font-size: 0.85rem; color: #ddd; line-height: 1.4; }
    .preview-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 0;
        gap: 12px;
        color: #888;
        font-size: 0.9rem;
    }
    .preview-spinner {
        width: 22px; height: 22px;
        border: 3px solid rgba(255,255,255,0.1);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        flex-shrink: 0;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    .preview-not-found {
        padding: 30px 0;
        text-align: center;
        color: #888;
        font-size: 0.9rem;
    }
    /* Preview modal close button */
    .preview-close {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 1.8rem;
        cursor: pointer;
        color: #888;
        line-height: 1;
        background: none;
        border: none;
    }
    .preview-close:hover { color: #fff; }
</style>

<div class="lab-layout">
    <!-- Header -->
    <div class="lab-header">
        <div class="lab-title">
            <span>⚡ SHOWRUNNER LABS V11</span>
            <?php if (DEBUG_MODE): ?>
                <span style="font-size:0.65rem; background:rgba(249,115,22,0.2); color:#fb923c; border:1px solid rgba(249,115,22,0.4); padding:2px 8px; border-radius:10px; font-weight:600;">DEBUG</span>
            <?php endif; ?>
        </div>
        <a href="/narratives_v11.php" class="res-btn" style="padding: 6px 12px; text-decoration: none; display: inline-block;">Exit to Editor</a>
    </div>

    <div class="lab-content">
        <!-- LEFT COLUMN: Config + Monitor + Visuals -->
        <div class="left-stack">
            
            <div class="config-panel">
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <div class="control-group" style="flex: 1; margin-bottom:0;">
                        <label class="c-label">Context & Filter</label>
                        <select id="seedDoc" class="c-select" onchange="resetFilter()">
                            <option value="">-- Global Database --</option>
                            <?php foreach($docsRaw as $d): ?>
                                <option value="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button class="btn-filter" onclick="openFilterModal()" title="Open Advanced Filter">
                        🧠 Filter
                    </button>
                    
                    <div class="control-group" style="width: 70px; margin-bottom:0;">
                        <label class="c-label">Shots</label>
                        <input type="number" id="targetLength" class="c-input" value="6" min="3" max="20">
                    </div>
                </div>
                
                <div id="activeFilterBadge" class="filter-badge">Active: 0 items</div>

                <div class="control-group" style="margin-top:12px;">
                    <label class="c-label">Director Logic</label>
                    <select id="logicMode" class="c-select">
                        <option value="associative">Associative (Flow & Continuity)</option>
                        <option value="contrast">Contrast (Conflict & Change)</option>
                        <option value="chaos">Chaos (Random Walk)</option>
                    </select>
                </div>

                <!-- Run Controls Row: Button + Checkboxes + AI pill -->
                <div class="run-controls-row">
                    <button id="btnToggle" class="btn-start" onclick="toggleGenerator()">START ENGINE</button>
                    
                    <label class="check-group" title="Use Claude AI to reason about each next shot (recommended)">
                        <input type="checkbox" id="chkUseAi">
                        Use AI
                    </label>

                    <label class="check-group" title="Run once and stop automatically">
                        <input type="checkbox" id="chkSingle" checked>
                        Single Run
                    </label>

                    <div id="aiStatusPill" class="ai-status-pill">
                        <span class="ai-dot"></span>
                        <span id="aiStatusText">AI OFF</span>
                    </div>
                </div>

                <div id="statusText" style="text-align: center; margin-top: 8px; font-size: 0.75rem; color: #666;">SYSTEM STANDBY</div>

                <?php if (DEBUG_MODE): ?>
                    <button id="btnDebugDownload" class="btn-debug-download" onclick="downloadDebugLog()">
                        ⬇ DOWNLOAD DEBUG LOG (JSON)
                    </button>
                <?php endif; ?>
            </div>

            <div id="monitorTerm" style="max-height:360px;" class="monitor-panel">
                <div class="log-line">> Showrunner Auto-Narrative System v11</div>
                <div class="log-line">> Ready. Select Context or Filter to begin.</div>
                <?php if (DEBUG_MODE): ?>
                    <div class="log-line log-debug">> DEBUG MODE ACTIVE — full query log enabled.</div>
                <?php endif; ?>
            </div>

            <div id="forgeVisuals" class="visual-strip">
                <div style="color:#444; font-size:0.8rem; width:100%; text-align:center;">Visual output...</div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Results -->
        <div class="results-panel">
            <div style="padding:10px; border-bottom:1px solid #333; background:#111; color:#666; font-size:0.8rem; font-weight:bold; text-align:center;">GENERATED SEQUENCES</div>
            <div id="resultsList" class="results-list"></div>
        </div>
    </div>
</div>

<!-- FILTER MODAL -->
<div id="filter-modal" class="modal-overlay">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #333; padding-bottom:10px;">
            <h3 style="margin:0; color:#fff;">Advanced Context Filter</h3>
            <div style="display:flex; gap:10px;">
                <button class="res-btn" onclick="applyAdvancedFilter()" style="padding:8px 20px; background:var(--accent); color:white; border:none; font-weight:bold;">APPLY FILTER</button>
                <button class="res-btn" onclick="$('#filter-modal').hide()">CLOSE</button>
            </div>
        </div>
        
        <div style="margin-top:15px;">
            <label class="c-label">Additional Instructions / Free Text</label>
            <input type="text" id="filterFreeText" class="c-input" placeholder="e.g. 'Dark mood, rainy neon streets, focus on blue tones'...">
        </div>

        <!-- V11 HYBRID ENGINE SETTINGS -->
        <div style="margin-top:15px; padding-top:15px; border-top:1px solid #333;">
            <label class="c-label" style="color: var(--accent);">V11 Hybrid Engine Settings</label>
            <div style="display:flex; gap:20px; margin-top:8px;">
                <label class="check-group" title="Walk 1-degree of Knowledge Graph edges for causal logic">
                    <input type="checkbox" id="chkEnableKgGraph">
                    Enable Graph-Walker (KG)
                </label>
                <label class="check-group" title="Prioritize exact matches from the SQL tags table">
                    <input type="checkbox" id="chkEnableSqlTags">
                    Enable SQL Tag Striker
                </label>
                <label class="check-group" title="Use Chroma vector database for semantic vibes">
                    <input type="checkbox" id="chkEnableChroma" checked>
                    Enable Chroma Sweep
                </label>
            </div>
        </div>

        <div class="filter-cols">
            <div class="filter-col">
                <div class="f-head">1. Categories</div>
                <div id="filterCats" class="f-list"></div>
            </div>
            <div class="filter-col">
                <div class="f-head">2. Available Items (Drag · 👁 Peek)</div>
                <div id="filterItems" class="f-list"></div>
            </div>
            <div class="filter-col" style="border-color:var(--accent);">
                <div class="f-head" style="color:var(--accent);">3. Active Filter Pot</div>
                <div id="filterPot" class="f-list"></div>
            </div>
        </div>
    </div>
</div>

<!-- ENTITY PREVIEW MODAL (Peek) — z-index 5000, on top of filter modal -->
<div id="entity-preview-modal" class="modal-overlay" style="position:fixed;">
    <div class="modal-content" style="position:relative;">
        <button class="preview-close" onclick="$('#entity-preview-modal').hide()">&times;</button>
        <div id="entity-preview-body">
            <div class="preview-loading"><div class="preview-spinner"></div> Loading preview...</div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// ============================================================
// DEBUG MODE (mirrors PHP constant)
// ============================================================
const DEBUG_MODE = <?= DEBUG_MODE ? 'true' : 'false' ?>;

// ============================================================
// STATE
// ============================================================
let isRunning    = false;
let currentFilter = null;

// Full structured debug log — accumulated across the whole run session.
// Each entry is one complete generation cycle with full detail.
let debugLog = [];

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    refreshResultList();
    syncAiStatusPill();

    // Watch Use AI checkbox to update pill immediately
    document.getElementById('chkUseAi').addEventListener('change', syncAiStatusPill);

    // SortableJS: Available Items (Clone source, drag-handle only)
    new Sortable(document.getElementById('filterItems'), {
        group: { name: 'filter', pull: 'clone', put: false },
        sort: false,
        animation: 150,
        handle: '.filter-drag-handle'
    });

    // SortableJS: Filter Pot (Target)
    new Sortable(document.getElementById('filterPot'), {
        group: 'filter',
        animation: 150,
        onAdd: function(evt) {
            const item = evt.item;
            item.classList.add('pot-item');
            item.classList.remove('f-item');
            const text = item.getAttribute('data-text') || item.innerText.replace('::', '').trim();
            const cat  = item.getAttribute('data-cat');
            item.innerHTML = `
                <span style="font-weight:bold;">${text}</span>
                <span class="remove-btn" onclick="this.parentElement.remove()">✕</span>
            `;
            item.setAttribute('data-text', text);
            if (cat) item.setAttribute('data-cat', cat);
        }
    });
});

// ============================================================
// AI STATUS PILL
// ============================================================
function syncAiStatusPill() {
    const useAi = document.getElementById('chkUseAi').checked;
    const pill   = document.getElementById('aiStatusPill');
    const label  = document.getElementById('aiStatusText');
    if (useAi) {
        pill.classList.add('active');
        label.textContent = 'AI ON';
    } else {
        pill.classList.remove('active');
        label.textContent = 'AI OFF';
    }
}

function setAiPillState(state) {
    // state: 'idle' | 'querying' | 'done' | 'error'
    const pill  = document.getElementById('aiStatusPill');
    const label = document.getElementById('aiStatusText');
    const useAi = document.getElementById('chkUseAi').checked;
    if (!useAi) return;

    pill.classList.add('active');
    const map = {
        idle:     'AI READY',
        querying: 'AI THINKING…',
        done:     'AI DONE',
        error:    'AI ERROR'
    };
    label.textContent = map[state] || 'AI ON';
}

// ============================================================
// ENGINE CONTROL
// ============================================================
function toggleGenerator() {
    if (isRunning) stopGenerator();
    else startGenerator();
}

function startGenerator() {
    isRunning = true;
    if (DEBUG_MODE) {
        debugLog = []; // fresh log each session
        document.getElementById('btnDebugDownload').style.display = 'none';
    }
    $('#btnToggle').text('STOP ENGINE').addClass('running');
    $('#statusText').text('ENGINE RUNNING');
    $('#seedDoc, #logicMode, #targetLength').prop('disabled', true);
    runGenerationCycle();
}

function stopGenerator() {
    isRunning = false;
    $('#btnToggle').text('START ENGINE').removeClass('running');
    $('#statusText').text('SYSTEM STANDBY');
    $('#seedDoc, #logicMode, #targetLength').prop('disabled', false);
    syncAiStatusPill();
    log('ENGINE STOPPED.', 'warn');

    if (DEBUG_MODE && debugLog.length > 0) {
        document.getElementById('btnDebugDownload').style.display = 'block';
        log('Debug log ready — click DOWNLOAD DEBUG LOG.', 'debug');
    }
}

function runGenerationCycle() {
    if (!isRunning) return;

    $('#forgeVisuals').empty();
    log('----------------------------------------');
    log('Initializing new sequence...');

    const docId   = $('#seedDoc').val();
    const useAi   = document.getElementById('chkUseAi').checked;
    const single  = document.getElementById('chkSingle').checked;

    let docName = '';
    if (docId) {
        const opt = document.querySelector(`#seedDoc option[value="${docId}"]`);
        if (opt) docName = opt.getAttribute('data-name');
    }

    let filterJson = '';
    if (currentFilter) {
        try { filterJson = JSON.stringify(currentFilter); }
        catch(e) { console.error('Filter stringify error', e); }
    }

    if (useAi) setAiPillState('querying');

    // Record what we're about to send (debug)
    const cycleStartTime = Date.now();
    const cycleDebugEntry = {
        timestamp: new Date().toISOString(),
        request: {
            doc_id:         docId,
            doc_name:       docName,
            mode:           $('#logicMode').val(),
            length:         parseInt($('#targetLength').val()),
            use_ai:         useAi,
            filter_payload: currentFilter || null
        },
        logs: [],
        debug_data: null,
        items_returned: [],
        duration_ms: 0
    };

    const payload = {
        action:         'generate_sequence',
        doc_id:         docId,
        doc_name:       docName,
        mode:           $('#logicMode').val(),
        length:         $('#targetLength').val(),
        filter_payload: filterJson,
        use_ai:         useAi ? '1' : '0',
        debug_mode:     DEBUG_MODE ? '1' : '0'
    };

    // --- V11 API POINTER ---
    $.post('auto_narratives_api_v11.php', payload, function(data) {
        cycleDebugEntry.duration_ms = Date.now() - cycleStartTime;

        if (data.status === 'success') {
            if (useAi) setAiPillState('done');

            // Capture logs & debug data
            cycleDebugEntry.logs = data.logs || [];
            if (DEBUG_MODE && data.debug_data) {
                cycleDebugEntry.debug_data = data.debug_data;
            }
            cycleDebugEntry.items_returned = (data.items || []).map(i => ({ id: i.id, name: i.name }));

            if (DEBUG_MODE) debugLog.push(cycleDebugEntry);

            playbackLogs(data.logs, () => {
                // Annotate AI queries in log from debug_data if present
                if (DEBUG_MODE && data.debug_data && data.debug_data.ai_calls) {
                    data.debug_data.ai_calls.forEach((call, idx) => {
                        log(`[AI CALL ${idx + 1}] Shot intent: ${(call.output && call.output.next_shot_intent) ? call.output.next_shot_intent.substring(0, 80) : 'n/a'}`, 'ai');
                    });
                }

                log(`Generated: ${data.sequence_name}`, 'success');
                refreshResultList();
                renderForgeVisuals(data.items);

                if (single) {
                    // Single run mode: stop automatically
                    isRunning = false;
                    $('#btnToggle').text('START ENGINE').removeClass('running');
                    $('#statusText').text('RUN COMPLETE');
                    $('#seedDoc, #logicMode, #targetLength').prop('disabled', false);
                    syncAiStatusPill();
                    log('Single run complete.', 'success');

                    if (DEBUG_MODE && debugLog.length > 0) {
                        document.getElementById('btnDebugDownload').style.display = 'block';
                        log('Debug log ready — click DOWNLOAD DEBUG LOG.', 'debug');
                    }
                } else {
                    // Loop mode: continue after cooldown
                    if (isRunning) setTimeout(runGenerationCycle, 2500);
                }
            });
        } else {
            if (useAi) setAiPillState('error');
            cycleDebugEntry.error = data.message;
            if (DEBUG_MODE) debugLog.push(cycleDebugEntry);
            log('Error: ' + data.message, 'warn');
            stopGenerator();
        }
    }, 'json').fail(function(xhr) {
        if (useAi) setAiPillState('error');
        cycleDebugEntry.error      = 'HTTP error';
        cycleDebugEntry.xhr_status = xhr.status;
        cycleDebugEntry.xhr_body   = xhr.responseText ? xhr.responseText.substring(0, 500) : '';
        if (DEBUG_MODE) debugLog.push(cycleDebugEntry);
        log('Connection Failed. Check Server Logs.', 'warn');
        console.error('Server Error:', xhr.responseText);
        stopGenerator();
    });
}

// ============================================================
// DEBUG LOG DOWNLOAD
// ============================================================
function downloadDebugLog() {
    if (!debugLog || debugLog.length === 0) {
        alert('No debug data available.');
        return;
    }
    const payload = {
        generated_at:  new Date().toISOString(),
        debug_mode:    true,
        total_cycles:  debugLog.length,
        cycles:        debugLog
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `showrunner_debug_${Date.now()}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    log('Debug log downloaded.', 'debug');
}

// ============================================================
// FILTER LOGIC
// ============================================================
function openFilterModal() {
    const docId = $('#seedDoc').val();
    $('#filter-modal').css('display', 'flex');

    if (!docId) {
        $('#filterCats').html('<div style="padding:10px; color:#aaa; font-style:italic;">Global Mode Active.<br>Please select a Context Doc to browse entities.</div>');
        $('#filterItems').empty();
    } else {
        $('#filterCats').html('<div style="padding:10px;">Loading...</div>');
        $.get(`narratives_api.php?action=get_filter_cats&doc_id=${docId}`, function(res) {
            if (res.status === 'success') {
                let html = '';
                res.data.forEach(c => {
                    html += `<div class="f-item" onclick="loadFilterItems('${c}', this)">${c} <span style="opacity:0.5;">›</span></div>`;
                });
                $('#filterCats').html(html);
            }
        }, 'json');
    }
}

function loadFilterItems(category, el) {
    const docId = $('#seedDoc').val();
    $('#filterCats .f-item').removeClass('active');
    $(el).addClass('active');
    $('#filterItems').html('<div style="padding:10px;">Loading...</div>');
    $.get(`narratives_api.php?action=get_filter_items&doc_id=${docId}&cat=${encodeURIComponent(category)}`, function(res) {
        if (res.status === 'success') {
            let html = '';
            res.data.forEach(item => {
                let label = typeof item === 'string' ? item : (item.name || 'Unknown');
                const safeLabel = label.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                html += `
                    <div class="f-item" data-cat="${category}" data-text="${safeLabel}">
                        <span style="flex:1; pointer-events:none;">${label}</span>
                        <div class="filter-item-controls">
                            <button class="filter-peek-btn" title="Preview this entity" onclick="peekFilterEntity(event, '${safeLabel.replace(/'/g,"\\'")}', '${category}')">👁</button>
                            <span class="filter-drag-handle">::</span>
                        </div>
                    </div>`;
            });
            $('#filterItems').html(html);
        }
    }, 'json');
}

// ============================================================
// ENTITY PEEK — opens the preview modal for a filter item
// ============================================================
window.peekFilterEntity = function(event, name, cat) {
    event.stopPropagation();
    event.preventDefault();

    const docId = $('#seedDoc').val();
    const body  = document.getElementById('entity-preview-body');

    body.innerHTML = '<div class="preview-loading"><div class="preview-spinner"></div> Loading preview...</div>';
    $('#entity-preview-modal').css('display', 'flex');

    if (!docId) {
        body.innerHTML = '<div class="preview-not-found">No context document selected. Select a context doc to enable previews.</div>';
        return;
    }

    fetch(`narratives_api.php?action=get_entity_preview&doc_id=${encodeURIComponent(docId)}&cat=${encodeURIComponent(cat)}&name=${encodeURIComponent(name)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                renderEntityPreview(res.data, name, cat, body);
            } else {
                body.innerHTML = `<div class="preview-not-found">
                    <div style="font-size:2rem; margin-bottom:8px;">🔍</div>
                    <div><strong>${name}</strong></div>
                    <div style="margin-top:6px; font-size:0.82rem;">${res.message || 'No detailed data found for this entity.'}</div>
                </div>`;
            }
        })
        .catch(err => {
            body.innerHTML = `<div class="preview-not-found">Failed to load preview: ${err.message}</div>`;
        });
};

function renderEntityPreview(data, name, cat, container) {
    let html = '';

    const catColors = {
        episodes: '#93c5fd', scene_hooks: '#fcd34d', characters: '#f9a8d4',
        factions: '#c4b5fd', locations: '#6ee7b7', artifacts: '#fca5a5'
    };
    const catColor = catColors[cat] || '#e5e7eb';

    html += `<div class="preview-header">
        <div>
            <h3 style="margin:0; font-size:1.25rem; line-height:1.3; color:#fff;">${escHtml(data.name || name)}</h3>
            ${data.roles && data.roles.length ? `<div style="margin-top:6px; font-size:0.82rem; color:#aaa;">${data.roles.map(r => `<span class="preview-pill">${escHtml(r)}</span>`).join(' ')}</div>` : ''}
        </div>
        <span class="preview-cat-badge" style="background:rgba(0,0,0,0.15); color:${catColor}; border-color:${catColor}40;">${escHtml(cat)}</span>
    </div>`;

    if (data.aliases && data.aliases.length > 0) {
        html += `<div class="preview-section">
            <div class="preview-section-title">Also Known As</div>
            <div class="preview-pill-row">${data.aliases.map(a => `<span class="preview-pill">${escHtml(String(a))}</span>`).join('')}</div>
        </div>`;
    }

    if (data.attributes && typeof data.attributes === 'object') {
        const attrs = Object.entries(data.attributes).filter(([k, v]) => v !== null && v !== undefined && v !== '');
        if (attrs.length > 0) {
            const longFields = ['description', 'summary', 'backstory', 'purpose', 'function',
                                'personality', 'motivation', 'production_notes', 'significance',
                                'visual', 'appearance', 'logline', 'act_structure'];
            const longPairs  = attrs.filter(([k]) => longFields.includes(k));
            const shortPairs = attrs.filter(([k]) => !longFields.includes(k));

            longPairs.forEach(([k, v]) => {
                const display = renderAttrValue(v);
                if (!display) return;
                html += `<div class="preview-section">
                    <div class="preview-section-title">${escHtml(k.replace(/_/g, ' '))}</div>
                    <div class="preview-value">${display}</div>
                </div>`;
            });

            if (shortPairs.length > 0) {
                html += `<div class="preview-section">
                    <div class="preview-section-title">Details</div>
                    <div class="preview-kv-grid">`;
                shortPairs.forEach(([k, v]) => {
                    const display = renderAttrValue(v);
                    if (!display) return;
                    html += `<div>
                        <div class="preview-kv-key">${escHtml(k.replace(/_/g, ' '))}</div>
                        <div class="preview-kv-val">${display}</div>
                    </div>`;
                });
                html += `</div></div>`;
            }
        }
    }

    if (data.relationships && data.relationships.length > 0) {
        html += `<div class="preview-section">
            <div class="preview-section-title">Relationships</div>
            <div style="display:flex; flex-direction:column; gap:6px;">`;
        data.relationships.slice(0, 8).forEach(r => {
            const target = escHtml(r.target || '');
            const type   = escHtml(r.type   || '');
            const desc   = escHtml(r.desc   || r.nature || '');
            html += `<div style="font-size:0.86rem; padding:5px 10px; background:rgba(0,0,0,0.3); border-radius:5px; border-left:2px solid #444;">
                <span style="font-weight:700; color:#ddd;">${target}</span>
                ${type ? `<span style="color:#888; margin-left:6px; font-size:0.78rem;">(${type})</span>` : ''}
                ${desc ? `<div style="color:#888; font-size:0.8rem; margin-top:2px;">${desc}</div>` : ''}
            </div>`;
        });
        if (data.relationships.length > 8) {
            html += `<div style="font-size:0.78rem; color:#888; padding:4px 10px;">+ ${data.relationships.length - 8} more…</div>`;
        }
        html += `</div></div>`;
    }

    if (data.timeline && data.timeline.length > 0) {
        html += `<div class="preview-section">
            <div class="preview-section-title">History / Timeline</div>
            <div style="display:flex; flex-direction:column; gap:5px;">`;
        data.timeline.slice(0, 6).forEach(t => {
            const date = t.date ? `<span style="font-family:monospace; font-size:0.75rem; color:#888; margin-right:8px;">[${escHtml(String(t.date))}]</span>` : '';
            html += `<div style="font-size:0.85rem; padding:4px 10px; border-left:2px solid rgba(245,158,11,0.3); color:#ccc;">${date}${escHtml(t.text || '')}</div>`;
        });
        if (data.timeline.length > 6) {
            html += `<div style="font-size:0.78rem; color:#888; padding:4px 10px;">+ ${data.timeline.length - 6} more events…</div>`;
        }
        html += `</div></div>`;
    }

    const hasContent = (data.attributes && Object.keys(data.attributes).length)
                    || (data.relationships && data.relationships.length)
                    || (data.timeline && data.timeline.length)
                    || (data.roles && data.roles.length);
    if (!hasContent) {
        html += `<div class="preview-not-found" style="padding-top:10px;">No additional details available for this entity.</div>`;
    }

    container.innerHTML = html;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

function renderAttrValue(v) {
    if (v === null || v === undefined || v === '') return '';
    if (typeof v === 'string')  return escHtml(v);
    if (typeof v === 'number' || typeof v === 'boolean') return escHtml(String(v));
    if (Array.isArray(v)) {
        if (v.length === 0) return '';
        if (v.every(i => typeof i === 'string' || typeof i === 'number')) {
            return `<div class="preview-pill-row">${v.map(i => `<span class="preview-pill">${escHtml(String(i))}</span>`).join('')}</div>`;
        }
        return `<pre style="font-size:0.75rem; color:#888; white-space:pre-wrap; word-break:break-word; margin:0;">${escHtml(JSON.stringify(v, null, 2))}</pre>`;
    }
    if (typeof v === 'object') {
        return `<pre style="font-size:0.75rem; color:#888; white-space:pre-wrap; word-break:break-word; margin:0;">${escHtml(JSON.stringify(v, null, 2))}</pre>`;
    }
    return escHtml(String(v));
}

// Close preview modal when clicking its backdrop
document.getElementById('entity-preview-modal').addEventListener('click', function(e) {
    if (e.target === this) $(this).hide();
});

// ============================================================
// APPLY / RESET FILTER (V11 Wired)
// ============================================================
function applyAdvancedFilter() {
    let items = [];
    const freeText = $('#filterFreeText').val().trim();
    $('#filterPot .pot-item').each(function() {
        const cat  = $(this).attr('data-cat');
        const text = $(this).attr('data-text');
        if (text) items.push({ cat: cat, name: text });
    });
    
    currentFilter = { 
        text: freeText, 
        items: items,
        enable_kg_graph: document.getElementById('chkEnableKgGraph').checked,
        enable_sql_tags: document.getElementById('chkEnableSqlTags').checked,
        enable_chroma: document.getElementById('chkEnableChroma').checked
    };
    
    const count = items.length;
    const customEngine = currentFilter.enable_kg_graph || currentFilter.enable_sql_tags || !currentFilter.enable_chroma;
    
    if (count > 0 || freeText || customEngine) {
        $('#activeFilterBadge').show().text(`Active: ${count} entities + text` + (customEngine ? ' (V11 Hybrid Active)' : ''));
        log(`Filter Applied: ${count} entities set.`, 'info');
        if (customEngine) log(`V11 Options - KG: ${currentFilter.enable_kg_graph}, SQL: ${currentFilter.enable_sql_tags}, Chroma: ${currentFilter.enable_chroma}`, 'debug');
    } else {
        $('#activeFilterBadge').hide();
        currentFilter = null;
        log('Filter Cleared.', 'info');
    }
    $('#filter-modal').hide();
}

function resetFilter() {
    currentFilter = null;
    $('#activeFilterBadge').hide();
    $('#filterPot').empty();
    $('#filterFreeText').val('');
    document.getElementById('chkEnableKgGraph').checked = false;
    document.getElementById('chkEnableSqlTags').checked = false;
    document.getElementById('chkEnableChroma').checked = true;
}

// ============================================================
// UI HELPERS
// ============================================================
function log(msg, type = '') {
    const term = document.getElementById('monitorTerm');
    const div  = document.createElement('div');
    div.className = `log-line log-${type}`;
    div.innerText = `> ${msg}`;
    term.appendChild(div);
    term.scrollTop = term.scrollHeight;
}

function playbackLogs(logs, callback) {
    if (!logs || logs.length === 0) { if (callback) callback(); return; }
    let i = 0;
    const interval = setInterval(() => {
        if (i >= logs.length) { clearInterval(interval); if (callback) callback(); return; }

        // Colour-code log lines by prefix patterns
        const line = logs[i];
        let type   = '';
        if (/^AI →/i.test(line) || /^AI /i.test(line))            type = 'ai';
        else if (/^Vector/i.test(line) || /Chroma/i.test(line) || /^Stage 1/i.test(line)) type = 'vector';
        else if (/^Error|^Warning|broken/i.test(line))            type = 'warn';
        else if (/^Generated|^Seed|^Selected/i.test(line))        type = 'success';
        else if (/^Director|^AI Director|^Lore|^World|^V11/i.test(line)) type = 'info';
        else if (/^\[DEBUG\]/i.test(line))                         type = 'debug';

        log(line, type);
        i++;
    }, 50);
}

function renderForgeVisuals(items) {
    const container = $('#forgeVisuals');
    items.forEach((item, index) => {
        setTimeout(() => {
            const el = $(`
                <div class="forge-frame">
                    <img src="${item.thumb}">
                    <div style="position:absolute; bottom:0; width:100%; background:rgba(0,0,0,0.7); color:#fff; font-size:0.6rem; text-align:center;">#${item.id}</div>
                </div>
            `);
            container.append(el);
            container.animate({ scrollLeft: container[0].scrollWidth }, 200);
        }, index * 150);
    });
}

function refreshResultList() {
    // --- V11 API POINTER ---
    $.get('auto_narratives_api_v11.php?action=list_results', function(res) {
        const list = $('#resultsList');
        list.empty();
        if (!res.data || res.data.length === 0) {
            list.html('<div style="padding:15px; text-align:center; font-size:0.8rem; color:#444;">No generated sequences yet.</div>');
            return;
        }
        res.data.forEach(seq => {
            const isPromoted = seq.status === 'promoted';
            const el = $(`
                <div class="result-card ${isPromoted ? 'promoted' : ''}">
                    <div class="res-title">${seq.name}</div>
                    <div class="res-meta"><span>${seq.item_count} shots</span><span>S: ${seq.score}</span></div>
                    <div style="font-size:0.7rem; color:#666; margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${seq.description}</div>
                    <div class="res-actions">
                        ${isPromoted
                            ? '<span style="color:#10b981; font-size:0.7rem; flex:1; text-align:center; padding:5px; border:1px solid rgba(16,185,129,0.2); border-radius:4px;">✓ SAVED</span>'
                            : `<button class="res-btn promote" onclick="promoteSequence(${seq.id})">💾 SAVE</button>`
                        }
                        <button class="res-btn delete" onclick="deleteSequence(${seq.id})">✕</button>
                    </div>
                </div>`);
            list.append(el);
        });
    }, 'json');
}

function promoteSequence(id) {
    // --- V11 API POINTER ---
    $.post('auto_narratives_api_v11.php', { action: 'promote', id: id }, function(d) {
        if (d.status === 'success') { Toast.show('Sequence Saved to Editor!'); refreshResultList(); }
        else Toast.show(d.message, 'error');
    }, 'json');
}

function deleteSequence(id) {
    if (confirm('Delete this sequence?')) {
        // --- V11 API POINTER ---
        $.post('auto_narratives_api_v11.php', { action: 'delete', id: id }, refreshResultList, 'json');
    }
}
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>