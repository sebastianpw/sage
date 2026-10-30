<?php
// public/griditor.php
// Photoshop-Inspired Layout UI for Image Grid Cutting
// Includes the full SAGE AI Filter Forge for frame selection.
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
:root, [data-theme="dark"] {
    --pl-bg: #080b10; --pl-surface: #0e1319; --pl-card: #111820; --pl-border: #1c2535;
    --pl-text: #c8d4e8; --pl-text-dim: #5a6a80; --pl-amber: #f5a623; --pl-teal: #3ab5c8;
}
[data-theme="light"] {
    --pl-bg: #f4f6fa; --pl-surface: #ffffff; --pl-card: #ffffff; --pl-border: #d0d8e8;
    --pl-text: #1a2233; --pl-text-dim: #7a8aaa; --pl-amber: #c8880a; --pl-teal: #1a8090;
}

html, body {
    margin: 0; padding: 0;
    height: 100%; width: 100%;
    overflow: hidden; /* App-like layout */
    background: #111; color: var(--pl-text);
    font-family: 'Syne', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    touch-action: none; /* Prevent browser pull-to-refresh on canvas */
}

/* Nav Bar */
.griditor-nav {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; background: var(--pl-surface);
    border-bottom: 1px solid var(--pl-border); height: 50px; box-sizing: border-box;
}
.griditor-nav-title { font-weight: bold; font-family: 'Space Mono', monospace; letter-spacing: 1px; color: var(--pl-teal); }
.btn-ui {
    background: var(--pl-card); color: var(--pl-text); border: 1px solid var(--pl-border);
    padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85rem;
    display: inline-flex; align-items: center; gap: 6px; font-weight: bold; transition: all 0.15s;
}
.btn-ui:hover { background: rgba(255,255,255,0.05); border-color: var(--pl-teal); }
.btn-action { background: var(--pl-teal); color: #000; border-color: var(--pl-teal); }
.btn-action:hover { filter: brightness(1.1); }
.btn-action:disabled { background: var(--pl-border); border-color: var(--pl-border); color: var(--pl-text-dim); cursor: not-allowed; }

/* Workspace Setup */
.griditor-workspace {
    display: grid;
    grid-template-rows: 24px 1fr;
    grid-template-columns: 24px 1fr;
    height: calc(100vh - 50px);
    width: 100%;
    background: #0a0a0a;
}
.ruler-corner { background: #222; border-right: 1px solid #444; border-bottom: 1px solid #444; z-index: 100; display:flex; align-items:center; justify-content:center; color:#555; font-size:12px; cursor:pointer;}
.ruler-corner:hover { color:var(--pl-teal); }

/* Rulers */
.ruler-h { background: #222; border-bottom: 1px solid #444; position: relative; overflow: hidden; cursor: crosshair; }
.ruler-v { background: #222; border-right: 1px solid #444; position: relative; overflow: hidden; cursor: crosshair; }

/* Canvas Area */
.canvas-area {
    position: relative; overflow: auto; 
    background-color: #111;
    background-image: 
        linear-gradient(45deg, #181818 25%, transparent 25%), 
        linear-gradient(-45deg, #181818 25%, transparent 25%), 
        linear-gradient(45deg, transparent 75%, #181818 75%), 
        linear-gradient(-45deg, transparent 75%, #181818 75%);
    background-size: 20px 20px;
    background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    display: flex; align-items: center; justify-content: center;
}

/* Image Wrapper */
.image-wrapper {
    position: relative;
    box-shadow: 0 0 20px rgba(0,0,0,0.8);
    display: none; /* hidden until image is selected */
    user-select: none;
    touch-action: none;
}
/* IMPORTANT: Forcing 100% so the visual image strictly matches the math bounds */
.image-wrapper img { 
    display: block; 
    width: 100%; 
    height: 100%; 
    pointer-events: none; 
}

/* Guides */
.guide { position: absolute; background: var(--pl-teal); box-shadow: 0 0 2px rgba(0,0,0,0.5); z-index: 50; }
.guide::after { content: ''; position: absolute; }
.guide-h { width: 100%; height: 1px; left: 0; cursor: row-resize; }
.guide-h::after { top: -10px; height: 21px; width: 100%; left: 0; }
.guide-v { height: 100%; width: 1px; top: 0; cursor: col-resize; }
.guide-v::after { left: -10px; width: 21px; height: 100%; top: 0; }
.guide:active { background: #fff; }
.guide-label {
    position: absolute; background: rgba(58, 181, 200, 0.85); color: #000; font-weight:bold;
    font-size: 9px; font-family: monospace; padding: 1px 3px; border-radius: 2px;
    pointer-events: none; white-space: nowrap; z-index: 51;
}

/* Loading */
.loading-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 500000; display: none; align-items: center; justify-content: center; flex-direction: column; color: var(--pl-teal); font-family: monospace; font-weight: bold; }
.spinner { border: 4px solid rgba(58, 181, 200, 0.2); border-left-color: var(--pl-teal); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-bottom: 15px; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* ========================================================================= */
/* FORGE MODAL (Directly from narseq)                                        */
/* ========================================================================= */
.compose-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.75);
    z-index: 300000; display: none; align-items: flex-end; justify-content: center;
}
.compose-modal-backdrop.active { display: flex; }
.compose-modal {
    width: 100%; max-width: 800px;
    background: var(--pl-surface); border: 1px solid var(--pl-border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
    height: 75vh; max-height: 90vh; resize: vertical; overflow: hidden; 
    display: flex; flex-direction: column;
}
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; flex-shrink:0; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--pl-border); border-radius: 2px; }
.cm-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--pl-border); flex-shrink: 0;
}
.cm-title { font-size: 0.9rem; font-weight: 700; color: var(--pl-teal); text-transform: uppercase; letter-spacing: 1px; font-family:'Space Mono', monospace; }
.cm-close-btn { background: transparent; border: 1px solid var(--pl-border); color: var(--pl-text-dim); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--pl-text); border-color: var(--pl-text); }

.forge-filters-bar { padding: 6px 12px; display: flex; gap: 6px; align-items: center; border-bottom: 1px solid var(--pl-border); overflow-x: auto; flex-shrink:0; min-height:34px; scrollbar-width:none; }
.forge-filters-bar::-webkit-scrollbar { display:none; }
.forge-pill { background: rgba(58,181,200,0.15); border: 1px solid rgba(58,181,200,0.3); color: var(--pl-teal); padding: 3px 8px; border-radius: 20px; font-size: 0.65rem; display: flex; align-items: center; gap: 6px; font-weight: bold; white-space:nowrap; }
.forge-pill-close { cursor: pointer; font-size: 0.8rem; opacity: 0.7; }
.forge-pill-close:hover { opacity: 1; color: #ef4444; }

.cm-body { display: flex; flex: 1; min-height: 0; padding: 0; }
.forge-sidebar {
    width: 120px; border-right: 1px solid var(--pl-border); padding: 8px 6px;
    display: flex; flex-direction: column; gap: 4px; overflow-y: auto; flex-shrink: 0;
}
.forge-sidebar-btn {
    width: 100%; padding: 8px; background: transparent; border: none; color: var(--pl-text-dim);
    text-align: left; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 0.75rem;
    font-family: 'Syne', sans-serif; transition: all 0.15s;
}
.forge-sidebar-btn:hover { background: rgba(255,255,255,0.05); color: var(--pl-text); }
.forge-sidebar-btn.active { background: rgba(58,181,200,0.15); color: var(--pl-teal); }

.forge-content { flex: 1; padding: 12px; overflow-y: auto; position: relative; }
.forge-tab-pane { display: none; flex-direction: column; gap: 8px; }
.forge-tab-pane.active { display: flex; }

.ff-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--pl-text-dim); letter-spacing: 1px; }

.su-input { width:100%; box-sizing:border-box; background:var(--pl-card); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s; }
.su-input:focus { border-color:var(--pl-teal); }

.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-card); color:var(--pl-text-dim); }
.pl-btn-secondary:hover { border-color:var(--pl-amber); color:var(--pl-amber); }
.pl-btn-teal { border-color:var(--pl-teal); background:var(--pl-teal); color:#000; font-weight:bold; }
.pl-btn-teal:hover { filter:brightness(1.1); }

.ff-dropdown { border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card); max-height: 140px; overflow-y: auto; display: none; position: absolute; z-index: 100; width: calc(100% - 40px); left: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); overscroll-behavior: contain; touch-action: pan-y; }
.ff-dropdown.open { display: block; }
.ff-dropdown-item { padding: 8px 10px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--pl-text); display: flex; justify-content: space-between; align-items:center; }
.ff-dropdown-item:hover { background: rgba(58,181,200,0.1); color: var(--pl-teal); }

.ff-result-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
@media (min-width: 600px) { .ff-result-grid { grid-template-columns: repeat(4, 1fr); } }
@media (min-width: 800px) { .ff-result-grid { grid-template-columns: repeat(5, 1fr); } }

.ff-result-card {
    border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card);
    overflow: hidden; position: relative; aspect-ratio: 1; transition: border-color 0.15s;
}
.ff-result-card:hover { border-color: var(--pl-teal); }
.ff-pswp-item { display: block; width: 100%; height: 100%; }
.ff-result-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ff-result-label {
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7);
    color: #fff; font-size: 0.6rem; padding: 3px 4px; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; pointer-events: none;
}
.ff-select-indicator {
    position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.6); color: #fff;
    border-radius: 4px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; z-index: 10; cursor: pointer; opacity: 0.85; transition: opacity 0.15s;
}
.ff-result-card:hover .ff-select-indicator { opacity: 1; background: var(--pl-teal); color: #000; }
.forge-result-empty { grid-column: 1 / -1; text-align: center; padding: 20px 0; color: var(--pl-text-dim); font-size: 0.8rem; }
.pswp { z-index: 400000 !important; }
</style>

<!-- Navbar -->
<div class="griditor-nav">
    <div class="griditor-nav-title"><i class="bi bi-grid-3x3"></i> GRIDITOR</div>
    <div style="display:flex; gap:10px;">
        <button class="btn-ui" onclick="openForgeModal()"><i class="bi bi-image"></i> Select Frame</button>
        <button class="btn-ui btn-action" id="cutBtn" onclick="performCut()" disabled><i class="bi bi-scissors"></i> Cut Grid</button>
    </div>
</div>

<!-- Workspace -->
<div class="griditor-workspace" id="workspace">
    <div class="ruler-corner" title="Reset Guides" onclick="clearGuides()"><i class="bi bi-arrow-counterclockwise"></i></div>
    <div class="ruler-h" id="rulerH"></div>
    <div class="ruler-v" id="rulerV"></div>
    
    <div class="canvas-area" id="canvasArea">
        <div class="image-wrapper" id="imgWrap">
            <img id="sourceImg" src="" alt="">
            <!-- Guides dynamically injected here -->
        </div>
        <div id="instructionText" style="color: var(--pl-text-dim); font-family: monospace; font-size: 14px; text-align:center;">
            Load an image to begin slicing.<br><br>
            • Double tap image to drop crosshairs<br>
            • Tap rulers to spawn a single axis line<br>
            • Drag lines to adjust<br>
            • Drag lines out of bounds to delete
        </div>
    </div>
</div>

<!-- FORGE MODAL -->
<div class="compose-modal-backdrop" id="ffBackdrop" onmousedown="if(event.target===this)closeForgeModal()">
    <div class="compose-modal" id="ffModal">
        <div class="cm-handle" onclick="closeForgeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-funnel"></i> Select Source Frame</div>
            <button class="cm-close-btn" onclick="closeForgeModal()">✕</button>
        </div>
        
        <!-- Active Filters Bar -->
        <div class="forge-filters-bar" id="ffActiveFilters">
            <div style="font-size:0.7rem; color:var(--pl-text-dim); font-style:italic;">No active filters.</div>
        </div>

        <div class="cm-body">
            <!-- Sidebar Tabs -->
            <div class="forge-sidebar">
                <button class="forge-sidebar-btn active" data-tab="fuzz" onclick="switchForgeTab('fuzz')">🧩 Fuzz</button>
                <button class="forge-sidebar-btn" data-tab="doc" onclick="switchForgeTab('doc')">📜 Doc</button>
                <button class="forge-sidebar-btn" data-tab="kg" onclick="switchForgeTab('kg')">🌳 KG</button>
                <button class="forge-sidebar-btn" data-tab="seq" onclick="switchForgeTab('seq')">🎬 Seq</button>
                <button class="forge-sidebar-btn" data-tab="storyboard" onclick="switchForgeTab('storyboard')">🖼️ Board</button>
                <button class="forge-sidebar-btn" data-tab="map_run" onclick="switchForgeTab('map_run')">🗺️ Run</button>
                <button class="forge-sidebar-btn" data-tab="vector" onclick="switchForgeTab('vector')">🔍 Semantic</button>
                <button class="forge-sidebar-btn" data-tab="id" onclick="switchForgeTab('id')">🔢 ID/Text</button>
                <hr style="border-color:var(--pl-border); margin:4px 0;">
                <button class="forge-sidebar-btn" data-tab="results" onclick="switchForgeTab('results')" style="color:var(--pl-amber); font-weight:bold;">▶ Results</button>
            </div>

            <!-- Content Area -->
            <div class="forge-content">
                <!-- FUZZ -->
                <div class="forge-tab-pane active" id="pane-fuzz">
                    <label class="ff-label">Fuzz Concept</label>
                    <input type="text" id="ffSearch-fuzz" class="su-input" placeholder="Search fuzz..." oninput="ffDebounceSearch('fuzz', this.value)">
                    <div class="ff-dropdown" id="ffDrop-fuzz"></div>
                </div>
                <!-- DOC -->
                <div class="forge-tab-pane" id="pane-doc">
                    <label class="ff-label">Lore Document</label>
                    <input type="text" id="ffSearch-doc" class="su-input" placeholder="Search docs..." oninput="ffDebounceSearch('doc', this.value)">
                    <div class="ff-dropdown" id="ffDrop-doc"></div>
                </div>
                <!-- KG -->
                <div class="forge-tab-pane" id="pane-kg">
                    <label class="ff-label">KG Node</label>
                    <input type="text" id="ffSearch-kg" class="su-input" placeholder="Search KG nodes..." oninput="ffDebounceSearch('kg', this.value)">
                    <div class="ff-dropdown" id="ffDrop-kg"></div>
                </div>
                <!-- SEQ -->
                <div class="forge-tab-pane" id="pane-seq">
                    <label class="ff-label">Narrative Sequence</label>
                    <input type="text" id="ffSearch-seq" class="su-input" placeholder="Search sequences..." onfocus="ffDebounceSearch('seq', this.value)" oninput="ffDebounceSearch('seq', this.value)">
                    <div class="ff-dropdown" id="ffDrop-seq"></div>
                </div>
                <!-- STORYBOARD -->
                <div class="forge-tab-pane" id="pane-storyboard">
                    <label class="ff-label">Storyboard</label>
                    <input type="text" id="ffSearch-storyboard" class="su-input" placeholder="Search storyboards..." onfocus="ffDebounceSearch('storyboard', this.value)" oninput="ffDebounceSearch('storyboard', this.value)">
                    <div class="ff-dropdown" id="ffDrop-storyboard"></div>
                </div>
                <!-- MAP RUN -->
                <div class="forge-tab-pane" id="pane-map_run">
                    <label class="ff-label">Map Run</label>
                    <input type="text" id="ffSearch-map_run" class="su-input" placeholder="Search map runs..." onfocus="ffDebounceSearch('map_run', this.value)" oninput="ffDebounceSearch('map_run', this.value)">
                    <div class="ff-dropdown" id="ffDrop-map_run"></div>
                </div>
                <!-- VECTOR -->
                <div class="forge-tab-pane" id="pane-vector">
                    <label class="ff-label">Semantic / Vector Search</label>
                    <textarea id="ffSearch-vector" class="su-input" style="height:80px; resize:none; margin-bottom:8px;" placeholder="Describe visually..."></textarea>
                    <button class="pl-btn pl-btn-teal" style="width:100%;" onclick="ffApplyVector()">Apply Semantic</button>
                </div>
                <!-- TEXT/ID -->
                <div class="forge-tab-pane" id="pane-id">
                    <label class="ff-label">Text Search</label>
                    <input type="text" id="ffSearch-text" class="su-input" placeholder="Name or description...">
                    
                    <label class="ff-label" style="margin-top:12px;">Sketch ID</label>
                    <input type="number" id="ffSearch-sketchId" class="su-input" placeholder="e.g. 1042">
                    
                    <label class="ff-label" style="margin-top:12px;">Frame ID</label>
                    <input type="number" id="ffSearch-frameId" class="su-input" placeholder="e.g. 5503">
                    
                    <button class="pl-btn pl-btn-teal" style="margin-top:12px; width:100%;" onclick="ffApplyTextId()">Apply Text/ID</button>
                </div>
                <!-- RESULTS (Grid) -->
                <div class="forge-tab-pane" id="pane-results">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:0.75rem; color:var(--pl-text-dim);" id="ffResultMeta">Results will appear here.</span>
                        <button class="pl-btn pl-btn-secondary" style="padding:4px 8px; font-size:0.65rem;" onclick="runForgeSearch(ffCurrentPage)">↻ Refresh</button>
                    </div>
                    
                    <div class="ff-result-grid" id="ffResultGrid"></div>
                    
                    <div id="ffPagination" style="display:none; justify-content:space-between; align-items:center; margin-top:12px;">
                        <button class="pl-btn pl-btn-secondary" id="ffPrevBtn" onclick="runForgeSearch(ffCurrentPage - 1)">« Prev</button>
                        <span style="font-size:0.75rem; color:var(--pl-text-dim);" id="ffPageLabel">Page 1</span>
                        <button class="pl-btn pl-btn-secondary" id="ffNextBtn" onclick="runForgeSearch(ffCurrentPage + 1)">Next »</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div id="loadingText">Processing Grid...</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>

<!-- PhotoSwipe Lightbox Module -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.forgeLightbox = null;
    window.initForgeLightbox = () => {
        if (window.forgeLightbox) window.forgeLightbox.destroy();
        window.forgeLightbox = new PhotoSwipeLightbox({
            gallery: '#ffResultGrid',
            children: 'a.ff-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        window.forgeLightbox.init();
    };
</script>

<script>
// --- Editor State ---
let currentFrame = null;
let imgNaturalWidth = 0;
let imgNaturalHeight = 0;
let scaleRatio = 1;
let guides = { h: [], v: [] }; // exact pixel coordinates on original image

const imgWrap = document.getElementById('imgWrap');
const sourceImg = document.getElementById('sourceImg');
const rulerH = document.getElementById('rulerH');
const rulerV = document.getElementById('rulerV');

// --- Initialization & Rulers ---
function drawRulers() {
    rulerH.style.backgroundImage = 'repeating-linear-gradient(to right, transparent, transparent 49px, #555 49px, #555 50px)';
    rulerV.style.backgroundImage = 'repeating-linear-gradient(to bottom, transparent, transparent 49px, #555 49px, #555 50px)';
}
drawRulers();

// --- Filter Forge State & Logic ---
let ffState = {
    fuzz: [], doc: null, kg: null, seq: null, storyboard: null, map_run: null,
    vectorText: '', textSearch: [], sketchId: '', frameId: ''
};
let ffCurrentPage = 1;
let ffTotalPages = 1;
let ffDebounceTimer;

function openForgeModal() {
    document.getElementById('ffBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    if(document.getElementById('ffResultGrid').innerHTML === '') {
        switchForgeTab('results');
    }
}

function closeForgeModal() {
    document.getElementById('ffBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function switchForgeTab(tabId) {
    document.querySelectorAll('.forge-sidebar-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.forge-sidebar-btn[data-tab="${tabId}"]`).classList.add('active');
    
    document.querySelectorAll('.forge-tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById(`pane-${tabId}`).classList.add('active');
    
    if (tabId === 'results') runForgeSearch(1);
}

function ffDebounceSearch(slot, q) {
    clearTimeout(ffDebounceTimer);
    ffDebounceTimer = setTimeout(() => {
        const dd = document.getElementById(`ffDrop-${slot}`);
        if (!q && !['storyboard', 'map_run', 'seq'].includes(slot)) { dd.classList.remove('open'); return; }
        
        dd.innerHTML = '<div style="padding:8px 10px; font-size:0.75rem; color:var(--pl-text-dim);">Searching...</div>';
        dd.classList.add('open');
        
        let url = `narseq_filter_forge_api.php?action=list_filter_options&mode=${slot}&q=${encodeURIComponent(q || '')}&entity_type=sketches`;
        
        fetch(url)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data || !res.data.length) {
                    dd.innerHTML = '<div style="padding: 8px 10px; font-size: 0.75rem; color: var(--pl-text-dim);">No results</div>';
                    return;
                }
                dd.innerHTML = res.data.map(item => {
                    const safe = JSON.stringify(item).replace(/"/g, '&quot;');
                    return `<div class="ff-dropdown-item" onclick="ffSelectItem('${slot}', ${safe})">
                        <div style="display:flex; flex-direction:column; flex:1; min-width:0; padding-right:8px;">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500;">${item.label}</span>
                            <span style="font-size:0.65rem; color:var(--pl-text-dim); margin-top:2px;">${item.meta||''}</span>
                        </div>
                    </div>`;
                }).join('');
            })
            .catch(err => {
                dd.innerHTML = '<div style="padding: 8px 10px; font-size: 0.75rem; color: #ef4444;">Error searching</div>';
            });
    }, 300);
}

function ffSelectItem(slot, item) {
    if (slot === 'fuzz') {
        if (!ffState.fuzz.some(f => f.id === item.id)) ffState.fuzz.push(item);
    } else {
        ffState[slot] = item;
    }
    document.getElementById(`ffSearch-${slot}`).value = '';
    document.getElementById(`ffDrop-${slot}`).classList.remove('open');
    renderActiveFilters();
    switchForgeTab('results');
}

function ffApplyVector() {
    ffState.vectorText = document.getElementById('ffSearch-vector').value.trim();
    renderActiveFilters();
    switchForgeTab('results');
}

function ffApplyTextId() {
    const txt = document.getElementById('ffSearch-text').value.trim();
    if (txt && !ffState.textSearch.includes(txt)) ffState.textSearch.push(txt);
    document.getElementById('ffSearch-text').value = '';
    
    ffState.sketchId = document.getElementById('ffSearch-sketchId').value.trim();
    ffState.frameId = document.getElementById('ffSearch-frameId').value.trim();
    
    renderActiveFilters();
    switchForgeTab('results');
}

function removeFfFilter(key, index = null) {
    if (key === 'fuzz' || key === 'textSearch') {
        if (index !== null) ffState[key].splice(index, 1);
        else ffState[key] = [];
    } else if (['vectorText', 'sketchId', 'frameId'].includes(key)) {
        ffState[key] = '';
        const el = document.getElementById(`ffSearch-${key.replace('vectorText','-vector').replace('sketchId','-sketchId').replace('frameId','-frameId')}`);
        if (el) el.value = '';
    } else {
        ffState[key] = null;
    }
    renderActiveFilters();
    runForgeSearch(1);
}

function renderActiveFilters() {
    const bar = document.getElementById('ffActiveFilters');
    bar.innerHTML = '';
    const labels = {
        fuzz: 'Fuzz', doc: 'Doc', kg: 'KG', seq: 'Seq', storyboard: 'Board', map_run: 'Run',
        vectorText: 'Semantic', textSearch: 'Text', sketchId: 'Sketch', frameId: 'Frame'
    };
    
    let hasAny = false;
    for (const [k, v] of Object.entries(ffState)) {
        if (Array.isArray(v)) {
            v.forEach((item, idx) => {
                hasAny = true;
                const display = (k === 'textSearch') ? item : item.label;
                bar.innerHTML += `<div class="forge-pill">${labels[k]}: ${display} <span class="forge-pill-close" onclick="removeFfFilter('${k}', ${idx})">×</span></div>`;
            });
        } else if (v && (typeof v === 'object' ? v.id : v.toString().length > 0)) {
            hasAny = true;
            const display = typeof v === 'object' ? v.label : v;
            bar.innerHTML += `<div class="forge-pill">${labels[k]}: ${display} <span class="forge-pill-close" onclick="removeFfFilter('${k}')">×</span></div>`;
        }
    }
    if (!hasAny) {
        bar.innerHTML = '<div style="font-size:0.7rem; color:var(--pl-text-dim); font-style:italic;">No active filters.</div>';
    }
}

function runForgeSearch(page) {
    ffCurrentPage = page;
    const p = new URLSearchParams();
    p.set('action', 'list_frames');
    p.set('entity_type', 'sketches');
    p.set('filter_mode', 'intersection');
    p.set('per_page', '12'); // Perfect for grid
    p.set('page', page);

    let hasFilter = false;

    if (ffState.fuzz.length > 0) { ffState.fuzz.forEach(f => p.append('fuzz_id[]', f.id)); hasFilter = true; }
    if (ffState.textSearch.length > 0) { ffState.textSearch.forEach(t => p.append('search[]', t)); hasFilter = true; }
    if (ffState.doc) { p.set('doc_id', ffState.doc.id); hasFilter = true; }
    if (ffState.kg) { p.set('kg_node_id', ffState.kg.id); hasFilter = true; }
    if (ffState.seq) { p.set('seq_id', ffState.seq.id); hasFilter = true; }
    if (ffState.storyboard) { p.set('storyboard_id', ffState.storyboard.id); hasFilter = true; }
    if (ffState.map_run) { p.set('map_run_id', ffState.map_run.id); hasFilter = true; }
    if (ffState.vectorText) { p.set('vector_text', ffState.vectorText); hasFilter = true; }
    if (ffState.sketchId) { p.set('entity_id', ffState.sketchId); hasFilter = true; }
    if (ffState.frameId) { p.set('frame_id', ffState.frameId); hasFilter = true; }

    if (!hasFilter) {
        p.set('sort', 'newest');
        p.set('sort_by', 'id');
        p.set('sort_order', 'desc');
    }

    const grid = document.getElementById('ffResultGrid');
    grid.innerHTML = '<div class="forge-result-empty">Searching Forge...</div>';
    document.getElementById('ffPagination').style.display = 'none';
    document.getElementById('ffResultMeta').textContent = 'Searching...';

    fetch('narseq_filter_forge_api.php?' + p.toString())
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') {
                grid.innerHTML = `<div class="forge-result-empty">Error: ${res.message}</div>`;
                return;
            }
            
            ffTotalPages = res.meta.pages;
            document.getElementById('ffResultMeta').textContent = `Found ${res.meta.total} frames.`;
            
            if (!res.data.length) {
                grid.innerHTML = '<div class="forge-result-empty">No results found.</div>';
                return;
            }
            
            grid.innerHTML = res.data.map(row => {
                const safeFile = row.filename.replace(/"/g, '&quot;');
                return `
                <div class="ff-result-card" data-sketch-id="${row.entity_id}" data-frame-id="${row.frame_id}">
                    <div class="ff-select-indicator" title="Load Frame into Griditor" onclick="event.preventDefault(); event.stopPropagation(); loadImage(${row.frame_id}, '${safeFile}')">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <a href="${row.filename}" class="ff-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="${row.filename}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                    </a>
                    <div class="ff-result-label">${row.entity_name || row.frame_name || ''}</div>
                </div>
            `}).join('');

            if (ffTotalPages > 1) {
                document.getElementById('ffPagination').style.display = 'flex';
                document.getElementById('ffPageLabel').innerHTML = `
                    <div style="display:flex; align-items:center; gap:4px;">
                        Pg <input type="number" value="${page}" min="1" max="${ffTotalPages}" 
                            style="width:40px; background:var(--pl-bg); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; text-align:center; padding:2px; font-size:0.75rem; font-family:'Space Mono', monospace;" 
                            onchange="if(this.value) runForgeSearch(parseInt(this.value))"> 
                        of ${ffTotalPages}
                    </div>
                `;
                document.getElementById('ffPrevBtn').disabled = (page <= 1);
                document.getElementById('ffNextBtn').disabled = (page >= ffTotalPages);
            }

            if (window.initForgeLightbox) window.initForgeLightbox();
        })
        .catch(err => {
            console.error(err);
            grid.innerHTML = '<div class="forge-result-empty">Network error.</div>';
        });
}

// --- Load Image to Editor ---
function loadImage(frameId, filename) {
    closeForgeModal();
    document.getElementById('instructionText').style.display = 'none';
    imgWrap.style.display = 'block';
    
    currentFrame = frameId;
    sourceImg.src = filename;
    
    sourceImg.onload = () => {
        imgNaturalWidth = sourceImg.naturalWidth;
        imgNaturalHeight = sourceImg.naturalHeight;
        clearGuides();
        updateScale();
        document.getElementById('cutBtn').disabled = false;
    };
}

// Fit image into view
function updateScale() {
    if (!imgNaturalWidth) return;
    const padding = 60;
    const viewW = document.getElementById('canvasArea').clientWidth - padding;
    const viewH = document.getElementById('canvasArea').clientHeight - padding;
    
    const ratioW = viewW / imgNaturalWidth;
    const ratioH = viewH / imgNaturalHeight;
    scaleRatio = Math.min(ratioW, ratioH, 1); // Max 100%
    
    imgWrap.style.width = (imgNaturalWidth * scaleRatio) + 'px';
    imgWrap.style.height = (imgNaturalHeight * scaleRatio) + 'px';
    
    renderGuides();
}
window.addEventListener('resize', updateScale);

// --- Guide Logic ---
function clearGuides() {
    guides = { h: [], v: [] };
    renderGuides();
}

function addGuide(type, origPx) {
    origPx = Math.max(1, Math.min(origPx, type==='v' ? imgNaturalWidth-1 : imgNaturalHeight-1));
    guides[type].push(origPx);
    renderGuides();
}

function removeGuide(type, index) {
    guides[type].splice(index, 1);
    renderGuides();
}

function renderGuides() {
    document.querySelectorAll('.guide, .guide-label').forEach(el => el.remove());
    guides.h.forEach((px, i) => drawGuideDom('h', px, i));
    guides.v.forEach((px, i) => drawGuideDom('v', px, i));
}

function drawGuideDom(type, origPx, index) {
    const isV = (type === 'v');
    const scaledPx = origPx * scaleRatio;
    
    const el = document.createElement('div');
    el.className = `guide guide-${type}`;
    if(isV) el.style.left = scaledPx + 'px';
    else el.style.top = scaledPx + 'px';
    
    const lbl = document.createElement('div');
    lbl.className = 'guide-label';
    lbl.textContent = Math.round(origPx) + 'px';
    if(isV) { lbl.style.left = (scaledPx + 4) + 'px'; lbl.style.top = '4px'; }
    else { lbl.style.top = (scaledPx + 4) + 'px'; lbl.style.left = '4px'; }
    
    el.addEventListener('pointerdown', (e) => startDrag(e, type, index, el, lbl));
    
    imgWrap.appendChild(el);
    imgWrap.appendChild(lbl);
}

// --- Dragging Engine ---
let dragContext = null;

function startDrag(e, type, index, lineEl, lblEl) {
    e.preventDefault(); e.stopPropagation();
    lineEl.setPointerCapture(e.pointerId);
    dragContext = { type, index, lineEl, lblEl, rect: imgWrap.getBoundingClientRect() };
    
    lineEl.addEventListener('pointermove', onDragMove);
    lineEl.addEventListener('pointerup', onDragEnd);
    lineEl.addEventListener('pointercancel', onDragEnd);
}

function onDragMove(e) {
    if(!dragContext) return;
    const { type, index, lineEl, lblEl, rect } = dragContext;
    
    let clientPos = type === 'v' ? e.clientX : e.clientY;
    let boundStart = type === 'v' ? rect.left : rect.top;
    let maxPx = type === 'v' ? imgNaturalWidth : imgNaturalHeight;
    let origPx = (clientPos - boundStart) / scaleRatio;
    
    if (origPx < -50 || origPx > maxPx + 50) {
        lblEl.textContent = 'Drop to delete';
        lblEl.style.background = '#ef4444';
        lineEl.style.opacity = '0.3';
    } else {
        origPx = Math.max(1, Math.min(origPx, maxPx - 1));
        guides[type][index] = origPx;
        
        let newScaled = origPx * scaleRatio;
        if(type === 'v') {
            lineEl.style.left = newScaled + 'px';
            lblEl.style.left = (newScaled + 4) + 'px';
        } else {
            lineEl.style.top = newScaled + 'px';
            lblEl.style.top = (newScaled + 4) + 'px';
        }
        
        lblEl.textContent = Math.round(origPx) + 'px';
        lblEl.style.background = 'rgba(58, 181, 200, 0.85)';
        lineEl.style.opacity = '1';
    }
}

function onDragEnd(e) {
    const { type, index, lineEl, rect } = dragContext;
    let origPx = ((type === 'v' ? e.clientX : e.clientY) - (type === 'v' ? rect.left : rect.top)) / scaleRatio;
    let maxPx = type === 'v' ? imgNaturalWidth : imgNaturalHeight;
    
    lineEl.releasePointerCapture(e.pointerId);
    lineEl.removeEventListener('pointermove', onDragMove);
    lineEl.removeEventListener('pointerup', onDragEnd);
    lineEl.removeEventListener('pointercancel', onDragEnd);
    dragContext = null;
    
    if (origPx < -50 || origPx > maxPx + 50) removeGuide(type, index);
    else renderGuides();
}

// --- Tap / Double Tap Logic ---
let lastTap = 0;
imgWrap.addEventListener('pointerdown', (e) => {
    if(e.target.classList.contains('guide')) return;
    const now = Date.now();
    if(now - lastTap < 300) {
        const rect = imgWrap.getBoundingClientRect();
        const origX = (e.clientX - rect.left) / scaleRatio;
        const origY = (e.clientY - rect.top) / scaleRatio;
        addGuide('v', origX);
        addGuide('h', origY);
        lastTap = 0;
    } else {
        lastTap = now;
    }
});

rulerH.addEventListener('pointerdown', (e) => {
    if(!imgNaturalWidth) return;
    const rect = imgWrap.getBoundingClientRect();
    const origX = (e.clientX - rect.left) / scaleRatio;
    if(origX > 0 && origX < imgNaturalWidth) addGuide('v', origX);
});
rulerV.addEventListener('pointerdown', (e) => {
    if(!imgNaturalHeight) return;
    const rect = imgWrap.getBoundingClientRect();
    const origY = (e.clientY - rect.top) / scaleRatio;
    if(origY > 0 && origY < imgNaturalHeight) addGuide('h', origY);
});

// --- Execution ---
function performCut() {
    if(!currentFrame) return;
    const hg = guides.h.map(v => Math.round(v));
    const vg = guides.v.map(v => Math.round(v));
    
    if(hg.length === 0 && vg.length === 0) {
        if(!confirm('No guides placed. Proceeding will just copy the image. Are you sure?')) return;
    }
    
    document.getElementById('loadingOverlay').style.display = 'flex';
    
    fetch('griditor_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'cut_grid', frame_id: currentFrame, h_guides: hg, v_guides: vg })
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('loadingOverlay').style.display = 'none';
        if(res.success) {
            Toast.show(res.message, 'success');
            setTimeout(() => { window.location.href = `view_scrollmagic_map_run.php?map_run_id=${res.map_run_id}`; }, 1000);
        } else {
            Toast.show(res.message || 'Error processing grid.', 'error');
        }
    })
    .catch(e => {
        document.getElementById('loadingOverlay').style.display = 'none';
        Toast.show('Network error.', 'error');
        console.error(e);
    });
}
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, 'Griditor', $spw->getProjectPath() . '/templates/curation.php');
?>