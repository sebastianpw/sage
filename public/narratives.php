<?php
// public/narratives.php
// Showrunner V9 - Narrative Sequencer
// Features: Advanced Filter, Vector Search, Frame Flipping, Mixed-Sequence Data, Global Search
// V9.1: Player Frame Cycling — swap active frame per pot slot in play mode
// V9.2: Entity Preview ("Peek") button in Advanced Filter — peek into filter items without leaving the filter modal
// V9.3: Tablet drag-and-drop fix — forceFallback on all Sortable instances for consistent Android touch handling
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = 'Narratives';

// Context Docs for Dropdown
$docsRaw = $pdo->query("SELECT d.id, d.name FROM documentations d JOIN md_doc_analysis da ON d.id = da.doc_id WHERE da.narrative_utility IS NOT NULL ORDER BY da.narrative_utility DESC")->fetchAll(PDO::FETCH_ASSOC);
$contextDocs = $docsRaw; 

// Load Sequences
$seqRaw = $pdo->query("SELECT * FROM narrative_sequences ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>

<style>
    :root { --film-bg: #030303; --highlight: #10b981; --accent-glow: rgba(16, 185, 129, 0.4); --handle-color: #f59e0b; }
    html, body { overflow: hidden; height: 100%; margin:0; }
    .sequencer-layout { display: flex; flex-direction: column; height: 100vh; width: 100vw; background: var(--bg); overflow: hidden; }

    /* Top: Timeline */
    .timeline-area { flex: 0 0 30%; background: var(--film-bg); border-bottom: 4px solid var(--border); display: flex; flex-direction: column; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.5); z-index: 10; }
    .timeline-header { padding: 10px 15px; background: rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); height: 50px; }
    .timeline-track-container { flex: 1; overflow-x: auto; overflow-y: hidden; display: flex; align-items: center; padding: 0 15px; background-image: linear-gradient(90deg, transparent 50%, rgba(255,255,255,0.05) 50%), linear-gradient(transparent 50%, rgba(0,0,0,0.5) 50%); background-size: 20px 100%, 100% 4px; }
    .film-strip-list { display: flex; gap: 8px; height: 100%; align-items: center; min-width: 100%; padding: 10px 0; }
    .seq-title-display { font-weight: 700; color: var(--text); font-size: 1rem; opacity: 0.8; }

    /* Frame */
    .film-frame { height: 110px; aspect-ratio: 16/9; background: #000; border: 2px solid #444; border-radius: 6px; flex-shrink: 0; position: relative; cursor: grab; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s; }
    .film-frame:active { cursor: grabbing; transform: scale(0.95); }
    .film-frame img { width: 100%; height: 100%; object-fit: cover; opacity: 1; }
    .remove-frame { position: absolute; top: 4px; right: 4px; background: rgba(220, 38, 38, 0.9); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.5); }
    .frame-ord { position: absolute; bottom: 4px; left: 4px; background: rgba(0,0,0,0.7); color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-family: monospace; }

    /* Middle: Controls */
    .control-strip { padding: 10px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; gap: 10px; align-items: center; flex-shrink: 0; height: 60px; }
    .context-select { padding: 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); flex:1; max-width: 300px; font-weight: 700; border-left: 4px solid var(--highlight); }
    .btn-icon { padding: 8px 12px; border-radius: 6px; cursor: pointer; border: 1px solid var(--border); background: var(--bg); display: flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 0.9rem; color: var(--text); transition: background 0.2s; }
    .btn-icon:hover { background: var(--card); }
    .filter-btn { background: var(--highlight); color: #fff; border: none; }
    .filter-btn:hover { background: #059669; }
    .reset-load-btn { padding: 8px; min-width: 36px; justify-content: center; font-size: 1.1rem; }
    .reset-load-btn:hover { background: var(--card); border-color: var(--highlight); }
    .debug-btn { padding: 8px; min-width: 36px; justify-content: center; font-size: 1.1rem; border-color: rgba(139,92,246,0.4); color: #a78bfa; }
    .debug-btn:hover { background: rgba(139,92,246,0.1); border-color: #a78bfa; }

    /* Bottom: Library */
    .library-area { flex: 1; background: var(--bg); overflow: hidden; position: relative; display: flex; flex-direction: column; min-height: 0; }
    .pagination-bar { flex: 0 0 50px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: center; gap: 15px; padding: 0 20px; z-index: 30; }
    .p-btn { background: transparent; border: 1px solid var(--border); color: var(--text); width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s ease; }
    .p-btn:hover:not(:disabled) { background: var(--highlight); border-color: var(--highlight); color: white; }
    .p-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .p-input-wrapper { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: var(--text-muted); background: var(--bg); padding: 4px 12px; border-radius: 20px; border: 1px solid var(--border); }
    .p-input { width: 40px; background: transparent; border: none; border-bottom: 1px solid var(--text-muted); color: var(--highlight); font-weight: 700; text-align: center; font-size: 1rem; padding: 2px 0; }
    .p-input:focus { outline: none; border-bottom-color: var(--highlight); }

    .lib-swiper { width: 100%; flex: 1; min-height: 0; padding: 20px 0; display: block; opacity: 0; transition: opacity 0.3s; }
    .swiper-slide { width: 280px; height: auto; display: flex; flex-direction: column; justify-content: center; transition: transform 0.3s; align-self: flex-start; }
    
    .loading-state { position: absolute; top: 50px; bottom: 0; left: 0; right: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 50; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
    .spinner { width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top-color: var(--highlight); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; }
    @keyframes spin { 100% { transform: rotate(360deg); } }

    /* Card */
    .lib-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; flex-direction: column; position: relative; height: auto; }
    .lib-card.match { border-color: var(--highlight); box-shadow: 0 0 15px var(--accent-glow); transform: translateY(-2px); }
    .lib-thumb { width: 100%; aspect-ratio: 16/9; background: #000; position: relative; flex-shrink: 0; cursor: zoom-in; }
    .lib-thumb img { width: 100%; height: 100%; object-fit: cover; transition: opacity 0.2s; }
    
    /* Frame Navigation Arrows (Library) */
    .frame-nav-btn { position: absolute; bottom: 0; width: 40px; height: 40px; background: rgba(0,0,0,0.6); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 25; font-size: 1.2rem; transition: background 0.2s; opacity: 0; }
    .lib-thumb:hover .frame-nav-btn { opacity: 1; }
    .frame-nav-btn:hover { background: var(--highlight); }
    .frame-nav-left { left: 0; border-top-right-radius: 8px; }
    .frame-nav-right { right: 0; border-top-left-radius: 8px; }

    .drag-handle { position: absolute; top: 8px; right: 8px; width: 32px; height: 32px; border-radius: 8px; background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(4px); color: rgba(255, 255, 255, 0.7); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: grab; border: 1px solid rgba(255,255,255,0.1); z-index: 20; transition: all 0.2s; }
    .drag-handle:hover { background: var(--highlight); color: #fff; border-color: transparent; }
    .drag-handle:active { cursor: grabbing; transform: scale(0.95); }
    .lib-meta { padding: 10px; flex: 1; display: flex; flex-direction: column; justify-content:space-between; }
    .lib-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color:var(--text); }
    .lib-id { font-size: 0.7rem; color: var(--text-muted); font-family:monospace; margin-bottom: 0; }
    .match-reason { font-size: 0.7rem; color: var(--highlight); margin-top: 4px; display:block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace; }
    .lib-actions { display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid var(--border); gap: 8px; margin-top: 8px; }
    .action-btn { flex: 1; font-size: 1rem; padding: 6px 0; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content:center; gap: 6px; background: var(--bg); color: var(--text); transition: background 0.2s; }
    .action-btn:hover { background: var(--accent-subtle); }
    .action-btn.green { border-color: rgba(16, 185, 129, 0.3); color: #10b981; background: rgba(16, 185, 129, 0.05); }

    /* FILTER MODAL STYLES */
    .filter-modal-body { display: flex; flex-direction: column; height: 80vh; }
    .filter-input-area { margin-bottom: 15px; }
    .filter-columns { display: flex; gap: 15px; flex: 1; min-height: 0; }
    .filter-col { flex: 1; border: 1px solid var(--border); border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; background: rgba(0,0,0,0.1); }
    .filter-col-head { padding: 10px; background: var(--card); font-weight: 700; border-bottom: 1px solid var(--border); text-align: center; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); }
    .filter-list { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 6px; }
    
    .filter-item { 
        padding: 8px 12px; background: var(--card); border: 1px solid var(--border); 
        border-radius: 6px; font-size: 0.9rem; transition: 0.1s; 
        display: flex; justify-content: space-between; align-items: center;
        cursor: default;
        position: relative;
    }
    
    .filter-col:first-child .filter-item { cursor: pointer; }
    .filter-col:first-child .filter-item:hover { border-color: var(--highlight); transform: translateX(2px); }
    .filter-col:first-child .filter-item.active { background: var(--highlight); color: white; border-color: var(--highlight); }

    /* DRAG HANDLE + PEEK BUTTON ROW FOR FILTER ITEMS */
    .filter-item-controls {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
        margin-left: 8px;
    }

    /* DRAG HANDLE FOR FILTER */
    .filter-drag-handle {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted); 
        cursor: grab; 
        background: rgba(0,0,0,0.05);
        border-radius: 4px;
        font-family: monospace;
        border: 1px solid rgba(0,0,0,0.1);
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .filter-drag-handle:hover { color: var(--highlight); background: rgba(0,0,0,0.1); border-color: var(--highlight); }
    
    /* PEEK BUTTON — same size as drag handle */
    .filter-peek-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        cursor: pointer;
        background: rgba(0,0,0,0.05);
        border-radius: 4px;
        border: 1px solid rgba(0,0,0,0.1);
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
    
    .pot-item {
        background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; cursor: default;
    }
    .pot-item .remove-x { font-weight: bold; cursor: pointer; padding: 0 5px; color: #ef4444; margin-left: 8px; }

    /* Player */
    .player-modal { display: none; position: fixed; inset: 0; background: #000; z-index: 3000; }
    .player-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 2.5rem; z-index: 3005; cursor: pointer; opacity: 0.8; text-shadow: 0 2px 5px #000; }
    .player-swiper .swiper-slide { width: 100%; height: 100%; background: #000; display: flex; justify-content: center; align-items: center; position: relative; }
    .player-img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .player-controls { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); display: flex; gap: 15px; z-index: 3002; }
    .player-btn { background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 10px 20px; border-radius: 30px; cursor: pointer; backdrop-filter: blur(6px); font-weight: 700; display: flex; gap: 8px; align-items: center; transition: all 0.2s; font-size: 0.9rem; }
    .player-btn:hover { background: rgba(255,255,255,0.1); transform: scale(1.05); border-color: #fff; }
    .player-btn.green { border-color: rgba(16, 185, 129, 0.6); color: #6ee7b7; background: rgba(6, 78, 59, 0.6); }
    .swiper-button-next, .swiper-button-prev { color: var(--highlight); text-shadow: 0 2px 4px #000; }

    /* Player Frame Navigation Arrows — same unobtrusive corner style as library */
    .player-frame-nav {
        position: absolute;
        bottom: 100px; /* sits above the player-controls row */
        width: 50px;
        height: 60px;
        background: rgba(0,0,0,0.5);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 3002;
        font-size: 2rem;
        line-height: 1;
        border-radius: 6px;
        transition: background 0.2s, transform 0.15s;
        user-select: none;
    }
    .player-frame-nav:hover { background: var(--highlight); transform: scale(1.08); }
    .player-frame-nav-left  { left: 20px; }
    .player-frame-nav-right { right: 20px; }

    /* Modals & Forms */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-content { background: var(--card); width: 90%; max-width: 600px; max-height: 85vh; overflow-y: auto; padding: 25px; border-radius: 12px; position: relative; border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
    /* Wide modal for Filter */
    .modal-content.wide { max-width: 1000px; }
    
    .modal-close { position: absolute; top: 15px; right: 15px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); line-height: 1; }
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; color: var(--text-muted); text-transform: uppercase; }
    .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); background: var(--bg); color: var(--text); border-radius: 6px; font-size: 1rem; }
    .form-input:focus { outline: none; border-color: var(--highlight); }
    .form-textarea { height: 80px; resize: vertical; font-family: monospace; }
    .form-btn { width: 100%; padding: 12px; background: var(--highlight); color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 1rem; }
    
    .modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; }
    .modal-label { width: 100px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); flex-shrink: 0; }
    .pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin: 2px; }
    .pill-theme { color: #8b5cf6; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.2); }
    .pill-char { color: #f59e0b; background: rgba(245,159,11,0.1); border: 1px solid rgba(245,159,11,0.2); }
    .pill-func { color: #7c3aed; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.1); }
    .score-badge { font-weight: 800; font-size: 1.2rem; padding: 4px 10px; border-radius: 6px; color: #fff; }
    .score-high { background: #10b981; } .score-mid { background: #f59e0b; } .score-low { background: #ef4444; }
    
    .load-list { max-height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
    .load-item { padding: 12px; border: 1px solid var(--border); background: var(--bg); border-radius: 6px; cursor: pointer; transition: 0.2s; }
    .load-item:hover { border-color: var(--highlight); background: var(--accent-subtle); }
    .load-name { font-weight: 700; font-size: 1.1rem; color: var(--text); }
    .load-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; display: flex; justify-content: space-between; }

    /* IFRAME MODAL — minimal padding/frame and scaled view */
    #iframe-modal .modal-content {
        padding: 8px 8px 0 8px; border: none; box-shadow: none; max-width: 1200px; width: 95vw; height: 88vh; border-radius: 8px; background: var(--card);
    }
    #iframe-modal .modal-content h3 { margin: 0; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 1rem; }
    #iframe-modal .modal-close { top: 8px; right: 8px; }
    #iframe-modal iframe#entity-iframe {
        flex: 1; width: calc(100% / 0.8); height: calc(100% / 0.8); border: none; border-radius: 0; margin: 0; padding: 0; transform: scale(0.8); transform-origin: top left; background: var(--bg); display: block;
    }

    /* ENTITY PREVIEW MODAL (Peek) — sits above the filter modal */
    #entity-preview-modal {
        z-index: 5000; /* above filter modal at 4000 */
    }
    #entity-preview-modal .modal-content {
        max-width: 700px;
        max-height: 80vh;
        overflow-y: auto;
    }
    .preview-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border);
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
    .preview-section {
        margin-bottom: 14px;
    }
    .preview-section-title {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }
    .preview-value {
        font-size: 0.92rem;
        line-height: 1.55;
        color: var(--text);
    }
    .preview-value.mono {
        font-family: monospace;
        font-size: 0.82rem;
        color: var(--text-muted);
    }
    .preview-pill-row {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 2px;
    }
    .preview-pill {
        font-size: 0.78rem;
        padding: 2px 10px;
        border-radius: 10px;
        background: rgba(255,255,255,0.06);
        border: 1px solid var(--border);
        color: var(--text);
    }
    .preview-kv-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 16px;
    }
    .preview-kv-item {}
    .preview-kv-key {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 2px;
    }
    .preview-kv-val {
        font-size: 0.85rem;
        color: var(--text);
        line-height: 1.4;
    }
    .preview-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 0;
        gap: 12px;
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    .preview-spinner {
        width: 22px; height: 22px;
        border: 3px solid rgba(255,255,255,0.1);
        border-top-color: var(--highlight);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        flex-shrink: 0;
    }
    .preview-not-found {
        padding: 30px 0;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
    }
</style>

<div class="sequencer-layout">
    <!-- 1. TIMELINE AREA -->
    <div class="timeline-area">
        <div class="timeline-header">
            <div id="seqNameDisplay" class="seq-title-display">Untitled Sequence</div>
            <div style="display:flex; gap:8px;">
                <a href="/auto_narratives.php" class="btn-icon" style="text-decoration:none;" title="Auto-Narrative Lab">⚡</a>
                <button class="btn-icon" onclick="playSequence()" title="Play">▶️</button>
                <button class="btn-icon" onclick="openSaveModal()" title="Save">💾</button>
                <button class="btn-icon" onclick="openLoadModal()" title="Load">📂</button>
            </div>
        </div>
        <div class="timeline-track-container">
            <div class="film-strip-list" id="timelineSortable">
                <div style="color:rgba(255,255,255,0.2); font-size:0.9rem; padding:0 20px; pointer-events:none;" id="emptyMsg">Drag items here</div>
            </div>
        </div>
    </div>

    <!-- 2. CONTROL STRIP -->
    <div class="control-strip">
        <select id="contextDoc" class="context-select">
            <option value="">-- No Context --</option>
            <?php foreach($contextDocs as $d): ?>
                <option value="<?= htmlspecialchars($d['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        
        <button class="btn-icon filter-btn" onclick="openFilterModal()">🧠 Advanced Filter</button>
        <button class="btn-icon reset-load-btn" onclick="resetAndLoad()" title="Reset all filters and load gallery">🔄</button>
        <button class="btn-icon debug-btn" id="debugBtn" onclick="openDebugModal()" title="Show filter query debug log" style="display:none;">🔬</button>
        <div id="activeFilterBadge" style="display:none; font-size:0.8rem; color:var(--highlight); margin-left:10px;">Active Custom Filter</div>
    </div>

    <!-- 3. LIBRARY AREA -->
    <div class="library-area">
        <div class="pagination-bar" id="paginationBar">
            <button class="p-btn" id="btnPrev" onclick="changePage(-1)" disabled title="Previous Page">←</button>
            <div class="p-input-wrapper">
                <span>Page</span>
                <input type="number" id="pageInput" class="p-input" value="1" onchange="jumpToPage(this.value)">
                <span id="pageTotalLabel">of ...</span>
            </div>
            <button class="p-btn" id="btnNext" onclick="changePage(1)" disabled title="Next Page">→</button>
        </div>


        <div id="loadingState" class="loading-state" style="display:none;">
            <div class="spinner"></div>
            <div style="font-size:0.9rem; color:var(--text-muted);">Scanning Database...</div>
        </div>

        <div class="swiper lib-swiper" id="mainSwiper">
            <div class="swiper-wrapper" id="libWrapper"></div>
            <div class="swiper-scrollbar"></div>
        </div>
    </div>
</div>

<!-- FILTER MODAL -->
<div id="filter-modal" class="modal-overlay">
    <div class="modal-content wide">
        <span class="modal-close" onclick="$('#filter-modal').hide()">&times;</span>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="margin:0;">Advanced Context Filter</h3>
            <button class="form-btn" style="width:auto; padding:8px 20px;" onclick="applyAdvancedFilter()">APPLY FILTER</button>
        </div>
        
        <div class="filter-modal-body">
            <div class="filter-input-area">
                <textarea id="filterFreeText" class="form-input form-textarea" placeholder="Add custom instructions here (e.g. 'Dark mood, rainy neon streets')..."></textarea>
            </div>
            <div class="filter-columns">
                <div class="filter-col">
                    <div class="filter-col-head">1. Categories</div>
                    <div id="filterCats" class="filter-list"></div>
                </div>
                <div class="filter-col">
                    <div class="filter-col-head">2. Available Items (Drag to Pot · 👁 Peek)</div>
                    <div id="filterItems" class="filter-list"></div>
                </div>
                <div class="filter-col" style="border-color:var(--highlight);">
                    <div class="filter-col-head" style="color:var(--highlight);">3. Filter Pot</div>
                    <div id="filterPot" class="filter-list"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ENTITY PREVIEW MODAL (Peek) — z-index 5000, on top of filter modal -->
<div id="entity-preview-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close" onclick="$('#entity-preview-modal').hide()">&times;</span>
        <div id="entity-preview-body">
            <div class="preview-loading"><div class="preview-spinner"></div> Loading...</div>
        </div>
    </div>
</div>

<!-- OTHER MODALS -->
<div id="curation-modal" class="modal-overlay"><div class="modal-content"><span class="modal-close" onclick="$('#curation-modal').hide()">&times;</span><div id="curation-modal-body"></div></div></div>
<div id="desc-modal" class="modal-overlay"><div class="modal-content" style="max-width:500px;"><span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span><h3 id="desc-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;"></h3><div id="desc-body" style="font-family:serif; font-size:1.1rem; line-height:1.6; white-space:pre-wrap;"></div></div></div>
<div id="save-modal" class="modal-overlay"><div class="modal-content" style="max-width:500px;"><span class="modal-close" onclick="$('#save-modal').hide()">&times;</span><h3 style="margin-top:0;">Save Sequence</h3><div class="form-group"><label class="form-label">Sequence Name</label><input type="text" id="saveNameInput" class="form-input" placeholder="e.g. Heist Sequence V1"></div><div class="form-group"><label class="form-label">Editorial Description</label><textarea id="saveDescInput" class="form-input form-textarea" placeholder="Director's notes..."></textarea></div><button class="form-btn" onclick="performSave()">Save Sequence</button></div></div>

<div id="load-modal" class="modal-overlay"><div class="modal-content" style="max-width:600px;"><span class="modal-close" onclick="$('#load-modal').hide()">&times;</span><h3 style="margin-top:0; margin-bottom:20px;">Load Sequence</h3>
    <div id="loadList" class="load-list">
    <?php foreach($seqRaw as $seq): ?>
        <?php
            $jsonSeq = json_encode($seq, JSON_HEX_APOS | JSON_HEX_QUOT);
            $clipCount = 0;
            if (!empty($seq['sequence_data'])) {
                $decoded = json_decode($seq['sequence_data'], true);
                if (is_array($decoded)) $clipCount = count($decoded);
            }
            $updated = !empty($seq['updated_at']) ? date('M d, Y', strtotime($seq['updated_at'])) : '';
        ?>
        <div class="load-item" onclick='performLoad(<?= $jsonSeq ?>)'>
            <div class="load-name"><?= htmlspecialchars($seq['name'] ?? 'Untitled', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="load-meta">
                <span><?= htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= intval($clipCount) ?> clips</span>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div></div>

<div id="playerModal" class="player-modal">
    <div class="player-close" onclick="closePlayer()">✕</div>
    <div class="swiper player-swiper">
        <div class="swiper-wrapper" id="playerSlides"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>

<div id="debug-modal" class="modal-overlay">
    <div class="modal-content wide" style="max-height:85vh; display:flex; flex-direction:column;">
        <span class="modal-close" onclick="$('#debug-modal').hide()">&times;</span>
        <h3 style="margin-top:0; margin-bottom:4px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            🔬 Filter Query Debug Log
        </h3>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:14px;">
            Each entry below is the exact query string sent to Chroma for that filter item.
        </div>
        <div id="debugLogBody" style="flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:14px;"></div>
    </div>
</div>

<div id="iframe-modal" class="modal-overlay">
    <div class="modal-content wide" style="display: flex; flex-direction: column;">
        <span class="modal-close" onclick="$('#iframe-modal').hide(); document.getElementById('entity-iframe').src = '';">&times;</span>
        <h3 style="margin-top:0;">Edit Element</h3>
        <iframe id="entity-iframe" src=""></iframe>
    </div>
</div>

<script>
    // GLOBAL DATA
    let currentLibraryPage = [];
    let sketchRegistry = {}; // map id => item
    let frameRegistry = {}; // map sketch_id => array of frames
    let currentSeqId = null;
    let libSwiper = null;
    let playerSwiper = null;
    let photoSwipeLightbox = null;
    let currentPage = 1;
    let totalPages = 1;
    let currentCustomQuery = null;
    let lastDebugLog = null; // Stores debug data from last filtered API response

    // Shared Sortable options that enable reliable drag on Android tablets.
    // forceFallback: true — bypasses the browser's native HTML5 drag API (broken
    //   on many Android WebViews / Chrome-on-tablet) and uses SortableJS's own
    //   pointer/touch simulation instead.
    // fallbackTolerance: 3 — minimum pixel movement before a touch is treated as
    //   a drag rather than a tap; prevents accidental drags on tap.
    // touchStartThreshold: 3 — same guard at the touchstart level.
    const SORTABLE_TOUCH_OPTS = {
        forceFallback: true,
        fallbackTolerance: 3,
        touchStartThreshold: 3
    };

    document.addEventListener('DOMContentLoaded', () => {
        if(typeof PhotoSwipeLightbox !== 'undefined') {
            photoSwipeLightbox = new PhotoSwipeLightbox({
                gallery: '#libWrapper', children: 'a.pswp-link', pswpModule: PhotoSwipe
            });
            photoSwipeLightbox.init();
        }

        // Timeline Sortable
        new Sortable(document.getElementById('timelineSortable'), {
            ...SORTABLE_TOUCH_OPTS,
            group: 'shared', animation: 150, direction: 'horizontal',
            onAdd: function (evt) {
                const item = evt.item; const d = item.dataset;
                const id = d.id || item.getAttribute('data-id');
                // Get the frame ID that was active when dragged
                const frameId = d.activeFrameId || item.getAttribute('data-active-frame-id');
                
                setTimeout(() => {
                    item.className = 'film-frame';
                    item.dataset.id = id; item.dataset.name = d.name; item.dataset.desc = d.desc;
                    // Persist the specific frame choice to the timeline element
                    if(frameId) item.dataset.frameId = frameId; 
                    
                    item.innerHTML = `<img src="${item.querySelector('img').src}"><div class="remove-frame" onclick="this.parentElement.remove(); updateOrd();">✕</div><div class="frame-ord"></div>`;
                    item.style.width = ''; item.style.transform = '';
                    
                    // Remove gallery artifacts if they exist
                    const navs = item.querySelectorAll('.frame-nav-btn');
                    navs.forEach(n => n.remove());
                    const dh = item.querySelector('.drag-handle');
                    if(dh) dh.remove();
                    
                    document.getElementById('emptyMsg').style.display = 'none'; updateOrd();
                }, 10);
            }, onUpdate: updateOrd
        });

        // Context Change Listener
        document.getElementById('contextDoc').addEventListener('change', () => {
            currentCustomQuery = null;
            $('#activeFilterBadge').hide();
            currentPage = 1;
            loadLibraryPage(1);
        });
        
        // Gallery is NOT auto-loaded on init — use the 🔄 button or Apply Filter to load.

        // Filter Sortables
        new Sortable(document.getElementById('filterItems'), {
            ...SORTABLE_TOUCH_OPTS,
            group: { name: 'filter', pull: 'clone', put: false },
            sort: false, handle: '.filter-drag-handle', animation: 150
        });
        new Sortable(document.getElementById('filterPot'), {
            ...SORTABLE_TOUCH_OPTS,
            group: 'filter', animation: 150,
            onAdd: function(evt) {
                const item = evt.item;
                const cat = item.getAttribute('data-cat') || item.dataset.cat || '';
                item.classList.add('pot-item');
                const textEl = item.querySelector('.filter-text');
                const text = textEl ? textEl.innerText : item.textContent.replace('×', '').trim();
                if (cat) item.setAttribute('data-cat', cat);
                // Strip the controls div and rebuild as simple pot item
                item.innerHTML = `${text} <span class="remove-x" onclick="this.parentElement.remove()">×</span>`;
            }
        });
    });

    // --- LIBRARY LOGIC ---
    function loadLibraryPage(page) {
        document.getElementById('loadingState').style.display = 'flex';
        document.getElementById('mainSwiper').style.opacity = '0.3';
        
        const docId = document.getElementById('contextDoc').value;
        let url = `narratives_api.php?action=fetch_library&page=${page}&context_id=${docId}`;
        
        if(currentCustomQuery) url += `&filter_payload=${encodeURIComponent(JSON.stringify(currentCustomQuery))}`;

        fetch(url).then(r => r.json()).then(res => {
            if(res.status === 'success') {
                currentLibraryPage = res.data;
                currentPage = parseInt(res.meta.current_page);
                totalPages = parseInt(res.meta.total_pages);
                
                if (!sketchRegistry) sketchRegistry = {};
                
                currentLibraryPage.forEach(item => { 
                    if(item && item.id !== undefined) {
                        sketchRegistry[item.id] = item;
                        // Store frames for this sketch
                        if(item.frames && Array.isArray(item.frames)) {
                            frameRegistry[item.id] = item.frames;
                        }
                    }
                });

                // Store debug log and show/hide debug button
                if (res.debug && res.debug.length > 0) {
                    lastDebugLog = res.debug;
                    document.getElementById('debugBtn').style.display = '';
                } else {
                    lastDebugLog = null;
                    document.getElementById('debugBtn').style.display = 'none';
                }

                renderLibrary(currentLibraryPage);
                updatePaginationUI();
            } else { alert("Error: " + res.message); }
        }).finally(() => {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('mainSwiper').style.opacity = '1';
        });
    }

    function updatePaginationUI() {
        document.getElementById('pageInput').value = currentPage;
        document.getElementById('pageTotalLabel').innerText = `of ${totalPages}`;
        document.getElementById('btnPrev').disabled = (currentPage <= 1);
        document.getElementById('btnNext').disabled = (currentPage >= totalPages);
    }

    function changePage(delta) {
        const target = currentPage + delta;
        if(target >= 1 && target <= totalPages) loadLibraryPage(target);
    }
    
    function jumpToPage(val) {
        let p = parseInt(val); if(isNaN(p)) p = 1; if(p < 1) p = 1; if(p > totalPages) p = totalPages;
        loadLibraryPage(p);
    }

    function updateOrd() { document.querySelectorAll('.frame-ord').forEach((el, i) => el.innerText = i + 1); }

    function renderLibrary(items) {
        const wrapper = document.getElementById('libWrapper');
        wrapper.innerHTML = '';
        items.forEach(s => {
            const slide = document.createElement('div'); slide.className = 'swiper-slide';
            slide.setAttribute('data-id', s.id);
            // Default active frame
            const initialFrame = (s.frames && s.frames.length > 0) ? s.frames[0] : {id:null, filename: s.thumb};
            
            slide.dataset.id = s.id; 
            slide.dataset.thumb = initialFrame.filename; 
            slide.dataset.name = s.name; 
            slide.dataset.desc = s.desc;
            slide.dataset.activeFrameId = initialFrame.id; // STORE ACTIVE FRAME ID
            
            let matchHtml = '';
            if (s.isMatch) {
                if (s.score > 0 && s.score <= 1) matchHtml = `<span class="match-reason">Sim: ${Math.round(s.score * 100)}%</span>`;
                else if (s.matches && s.matches.length > 0) matchHtml = `<span class="match-reason">Matches: ${s.matches.slice(0, 3).join(', ')}</span>`;
            }
            
            // Frame Nav Logic
            let navHtml = '';
            if (s.frames && s.frames.length > 1) {
                navHtml = `
                    <div class="frame-nav-btn frame-nav-left" onclick="cycleFrame(${s.id}, -1)">‹</div>
                    <div class="frame-nav-btn frame-nav-right" onclick="cycleFrame(${s.id}, 1)">›</div>
                `;
            }
            
            slide.innerHTML = `<div class="lib-card ${s.isMatch ? 'match' : ''}" id="card_${s.id}">
                <div class="lib-thumb">
                    <a href="${initialFrame.filename}" class="pswp-link" id="link_${s.id}" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="${initialFrame.filename}" loading="lazy" id="img_${s.id}">
                    </a>
                    ${navHtml}
                    <div class="drag-handle" title="Drag to Timeline">⠿</div>
                </div>
                <div class="lib-meta">
                    <div><div class="lib-title">${s.name}</div><div class="lib-id">#${s.id}</div>${matchHtml}</div>
                    <div class="lib-actions">
                        <button class="action-btn" onclick="openDesc(${s.id})" title="Read">📖</button>
                        <button class="action-btn green" onclick="openAnalysis(${s.id})" title="Analysis">🕵️</button>
                        <button class="action-btn" style="border-color: rgba(59, 130, 246, 0.3); color: #3b82f6; background: rgba(59, 130, 246, 0.05);" onclick="openEntityForm(${s.id})" title="Edit">✏️</button>
                    </div>
                </div>
            </div>`;
            wrapper.appendChild(slide);
        });
        
        if (libSwiper) libSwiper.destroy();
        libSwiper = new Swiper('.lib-swiper', { slidesPerView: 'auto', spaceBetween: 20, centeredSlides: true, scrollbar: { el: '.swiper-scrollbar' }, freeMode: true, mousewheel: true, observer: true, observeParents: true });
        
        // Update Sortable to include the new frameId data transfer
        new Sortable(wrapper, { 
            ...SORTABLE_TOUCH_OPTS,
            group: { name: 'shared', pull: 'clone', put: false }, 
            sort: false, 
            handle: '.drag-handle', 
            onClone: function(evt) { 
                const s = evt.item; 
                const clone = evt.clone;
                // Explicitly copy custom data attributes
                clone.dataset.id = s.dataset.id;
                clone.dataset.thumb = s.dataset.thumb;
                clone.dataset.name = s.dataset.name;
                clone.dataset.desc = s.dataset.desc;
                clone.dataset.activeFrameId = s.dataset.activeFrameId; // Transfer current frame ID
            } 
        });
        
        if(photoSwipeLightbox) photoSwipeLightbox.init();
        document.querySelector('.lib-swiper').style.opacity = 1;
    }

    // --- FRAME CYCLING (Library) ---
    window.cycleFrame = function(sketchId, direction) {
        event.stopPropagation();
        
        const frames = frameRegistry[sketchId];
        if (!frames || frames.length < 2) return;
        
        const card = document.getElementById(`card_${sketchId}`).closest('.swiper-slide');
        const img = document.getElementById(`img_${sketchId}`);
        const link = document.getElementById(`link_${sketchId}`);
        
        const currentId = parseInt(card.dataset.activeFrameId);
        let idx = frames.findIndex(f => f.id == currentId);
        
        if (idx === -1) idx = 0;
        
        let newIdx = (idx + direction);
        if (newIdx < 0) newIdx = frames.length - 1;
        if (newIdx >= frames.length) newIdx = 0;
        
        const newFrame = frames[newIdx];
        
        img.src = newFrame.filename;
        link.href = newFrame.filename;
        card.dataset.activeFrameId = newFrame.id;
        card.dataset.thumb = newFrame.filename;
    };

    // --- FRAME CYCLING (Player) ---
    // Cycles to a different frame render for the sketch at potIndex.
    // Simultaneously updates the matching pot (timeline) slot so the
    // chosen frame is reflected when the sequence is saved.
    window.playerCycleFrame = function(potIndex, direction) {
        const slide = document.querySelector(`#playerSlides .swiper-slide[data-pot-index="${potIndex}"]`);
        if (!slide) return;

        const sketchId = parseInt(slide.dataset.sketchId);
        const frames = frameRegistry[sketchId];
        if (!frames || frames.length < 2) return;

        let idx = parseInt(slide.dataset.activeFrameIdx) || 0;
        idx = (idx + direction + frames.length) % frames.length;
        slide.dataset.activeFrameIdx = idx;

        const newFrame = frames[idx];

        // Update the player image
        const img = document.getElementById(`player-img-${potIndex}`);
        if (img) img.src = newFrame.filename;

        // Sync the pot slot so save picks up the new frame choice
        const potFrames = document.querySelectorAll('#timelineSortable .film-frame');
        if (potFrames[potIndex]) {
            const potEl = potFrames[potIndex];
            potEl.dataset.frameId = newFrame.id;
            const potImg = potEl.querySelector('img');
            if (potImg) potImg.src = newFrame.filename;
        }
    };

    // --- PLAY SEQUENCE ---
    // V9.1: Embeds pot index + sketch id per slide so playerCycleFrame can
    //       find and update the correct pot slot. Frame nav arrows are shown
    //       only when the sketch has multiple frames in frameRegistry.
    function playSequence() {
        const potFrames = document.querySelectorAll('#timelineSortable .film-frame');
        if (potFrames.length === 0) return;

        const wrap = document.getElementById('playerSlides');
        wrap.innerHTML = '';

        potFrames.forEach((el, potIndex) => {
            const src         = el.querySelector('img').src;
            const sketchId    = el.dataset.id;
            const currentFid  = el.dataset.frameId || el.getAttribute('data-frame-id') || '';
            const sketchFrames = frameRegistry[sketchId] || [];

            // Determine active frame index within registry
            let activeFrameIdx = 0;
            if (currentFid && sketchFrames.length > 1) {
                const fi = sketchFrames.findIndex(f => f.id == currentFid);
                if (fi !== -1) activeFrameIdx = fi;
            }

            // Frame nav arrows — only when multiple frames exist
            const frameNavHtml = sketchFrames.length > 1 ? `
                <div class="player-frame-nav player-frame-nav-left"
                     onclick="playerCycleFrame(${potIndex}, -1)"
                     title="Previous frame version">‹</div>
                <div class="player-frame-nav player-frame-nav-right"
                     onclick="playerCycleFrame(${potIndex}, 1)"
                     title="Next frame version">›</div>
            ` : '';

            wrap.innerHTML += `
                <div class="swiper-slide"
                     data-pot-index="${potIndex}"
                     data-sketch-id="${sketchId}"
                     data-active-frame-idx="${activeFrameIdx}">
                    <img src="${src}" class="player-img" id="player-img-${potIndex}">
                    ${frameNavHtml}
                    <div class="player-controls">
                        <button class="player-btn" onclick="openDesc(${sketchId})" title="Info">📖</button>
                        <button class="player-btn green" onclick="openAnalysis(${sketchId})" title="Analysis">🕵️</button>
                        <button class="player-btn" style="border-color: rgba(59, 130, 246, 0.6); color: #93c5fd; background: rgba(30, 58, 138, 0.6);" onclick="openEntityForm(${sketchId})" title="Edit">✏️</button>
                    </div>
                </div>`;
        });

        document.getElementById('playerModal').style.display = 'block';
        if (playerSwiper) playerSwiper.destroy();
        playerSwiper = new Swiper('.player-swiper', {
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            keyboard: true
        });
    }

    function closePlayer() { document.getElementById('playerModal').style.display = 'none'; }

    // --- RESET & LOAD ---
    // Clears context select, all filter state, and the filter modal contents,
    // then fires an unfiltered gallery load. Also callable from the filter modal
    // as a hard reset before opening.
    window.resetAndLoad = function() {
        // Reset context select
        document.getElementById('contextDoc').value = '';
        // Reset filter state
        currentCustomQuery = null;
        lastDebugLog = null;
        $('#activeFilterBadge').hide();
        document.getElementById('debugBtn').style.display = 'none';
        // Clear filter modal internals
        $('#filterFreeText').val('');
        $('#filterPot').html('');
        $('#filterItems').html('');
        $('#filterCats .filter-item').removeClass('active');
        // Load unfiltered gallery
        currentPage = 1;
        loadLibraryPage(1);
    };

    // --- DEBUG MODAL ---
    window.openDebugModal = function() {
        if (!lastDebugLog || lastDebugLog.length === 0) return;

        const body = document.getElementById('debugLogBody');
        body.innerHTML = '';

        lastDebugLog.forEach((entry, i) => {
            const catColor = {
                'text': '#6ee7b7', 'episodes': '#93c5fd', 'scene_hooks': '#fcd34d',
                'characters': '#f9a8d4', 'factions': '#c4b5fd', 'locations': '#6ee7b7',
                'artifacts': '#fca5a5'
            }[entry.cat] || '#e5e7eb';

            const warningHtml = entry.warning
                ? `<div style="margin-top:8px; padding:6px 10px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:4px; font-size:0.78rem; color:#fca5a5;">⚠️ ${entry.warning}</div>`
                : '';

            const enrichedBadge = entry.was_enriched
                ? `<span style="font-size:0.65rem; padding:2px 7px; border-radius:10px; background:rgba(16,185,129,0.15); color:#6ee7b7; border:1px solid rgba(16,185,129,0.3); margin-left:8px;">✨ enriched</span>`
                : `<span style="font-size:0.65rem; padding:2px 7px; border-radius:10px; background:rgba(255,255,255,0.05); color:var(--text-muted); border:1px solid var(--border); margin-left:8px;">raw</span>`;

            // Raw query block — only shown when enrichment happened, collapsed by default
            const rawHtml = (entry.was_enriched && entry.raw_query)
                ? `<details style="margin-top:10px;">
                    <summary style="font-size:0.75rem; color:var(--text-muted); cursor:pointer; user-select:none; padding:4px 0;">▶ Show raw lore dump (before enrichment)</summary>
                    <pre style="margin:6px 0 0 0; font-size:0.72rem; color:rgba(255,255,255,0.3); white-space:pre-wrap; word-break:break-word; font-family:monospace; line-height:1.5;">${entry.raw_query.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</pre>
                   </details>`
                : '';

            body.innerHTML += `
                <div style="border:1px solid var(--border); border-radius:8px; overflow:hidden;">
                    <div style="padding:8px 12px; background:var(--card); display:flex; align-items:center; gap:6px; border-bottom:1px solid var(--border); flex-wrap:wrap;">
                        <span style="font-size:0.7rem; color:var(--text-muted); font-family:monospace;">#${i + 1}</span>
                        <span style="font-size:0.7rem; padding:2px 8px; border-radius:10px; background:rgba(0,0,0,0.2); color:${catColor}; font-weight:700; text-transform:uppercase;">${entry.cat}</span>
                        <span style="font-weight:700; font-size:0.95rem; color:var(--text);">${entry.label}</span>
                        ${enrichedBadge}
                    </div>
                    <div style="padding:12px; background:rgba(0,0,0,0.2);">
                        <pre style="margin:0; font-size:0.78rem; color:var(--text-muted); white-space:pre-wrap; word-break:break-word; font-family:monospace; line-height:1.6;">${entry.query.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</pre>
                        ${rawHtml}
                        ${warningHtml}
                    </div>
                </div>`;
        });

        $('#debug-modal').css('display', 'flex');
    };

    // --- FILTER & MODAL LOGIC (Standard + Global) ---
    let currentActiveCat = '';
    
    function openFilterModal() {
        const docId = document.getElementById('contextDoc').value;
        $('#filter-modal').css('display', 'flex');
        
        if (!docId) {
             $('#filterCats').html('<div style="padding:10px; color:#888; font-style:italic;">Global Search Mode.<br>Select a Context Doc to enable categorical filters.</div>');
             $('#filterItems').html('');
             $('#filterPot').html('');
        } else {
             $('#filterCats').html('<div style="padding:10px; color:#888;">Loading...</div>');
             $('#filterItems').html('');
             fetch(`narratives_api.php?action=get_filter_cats&doc_id=${docId}`).then(r=>r.json()).then(res => {
                if(res.status === 'success') {
                    const cats = res.data ||[];
                    let html = '';
                    cats.forEach(c => { html += `<div class="filter-item" onclick="loadFilterItems('${c.replace(/'/g, "\\'")}', this)">${c} <span>›</span></div>`; });
                    $('#filterCats').html(html);
                }
             });
        }
    }
    
    window.loadFilterItems = function(category, el) {
        const docId = document.getElementById('contextDoc').value;
        if (!docId) return;
        
        currentActiveCat = category;
        $('#filterItems').html('<div style="padding:10px;">Loading...</div>');
        $('#filterCats .filter-item').removeClass('active');
        if (el) $(el).addClass('active');
        fetch(`narratives_api.php?action=get_filter_items&doc_id=${docId}&cat=${encodeURIComponent(category)}`).then(r=>r.json()).then(res => {
            if(res.status === 'success') {
                let html = '';
                if(Array.isArray(res.data)) {
                    res.data.forEach(item => {
                        let label = typeof item === 'string' ? item : (item.name || item.title || 'Unknown');
                        const safeLabel = label.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        html += `<div class="filter-item" data-cat="${category}" data-text="${safeLabel}">
                            <span class="filter-text">${label}</span>
                            <div class="filter-item-controls">
                                <button class="filter-peek-btn" title="Preview this entity" onclick="peekFilterEntity(event, '${safeLabel.replace(/'/g,"\\'")}', '${category}')">👁</button>
                                <span class="filter-drag-handle">::</span>
                            </div>
                        </div>`;
                    });
                }
                $('#filterItems').html(html);
            }
        });
    };

    // =====================================================================
    // ENTITY PEEK — opens the preview modal for a filter item
    // =====================================================================
    window.peekFilterEntity = function(event, name, cat) {
        // Stop the click from propagating to any drag or parent handlers
        event.stopPropagation();
        event.preventDefault();

        const docId = document.getElementById('contextDoc').value;
        const body  = document.getElementById('entity-preview-body');

        // Show loading state and open modal
        body.innerHTML = '<div class="preview-loading"><div class="preview-spinner"></div> Loading preview...</div>';
        $('#entity-preview-modal').css('display', 'flex');

        if (!docId) {
            body.innerHTML = '<div class="preview-not-found">No context document selected. Select a context doc to enable previews.</div>';
            return;
        }

        // Fetch full entity detail from the API
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

        // ── Header ──────────────────────────────────────────────────────
        const catColors = {
            episodes: '#93c5fd', scene_hooks: '#fcd34d', characters: '#f9a8d4',
            factions: '#c4b5fd', locations: '#6ee7b7', artifacts: '#fca5a5'
        };
        const catColor = catColors[cat] || '#e5e7eb';

        html += `<div class="preview-header">
            <div>
                <h3 style="margin:0; font-size:1.25rem; line-height:1.3;">${escHtml(data.name || name)}</h3>
                ${data.roles && data.roles.length ? `<div style="margin-top:6px; font-size:0.82rem; color:var(--text-muted);">${data.roles.map(r => `<span class="preview-pill">${escHtml(r)}</span>`).join(' ')}</div>` : ''}
            </div>
            <span class="preview-cat-badge" style="background:rgba(0,0,0,0.15); color:${catColor}; border-color:${catColor}40;">${escHtml(cat)}</span>
        </div>`;

        // ── Aliases ──────────────────────────────────────────────────────
        if (data.aliases && data.aliases.length > 0) {
            html += `<div class="preview-section">
                <div class="preview-section-title">Also Known As</div>
                <div class="preview-pill-row">${data.aliases.map(a => `<span class="preview-pill">${escHtml(String(a))}</span>`).join('')}</div>
            </div>`;
        }

        // ── Attributes — rendered as a responsive grid of key/value pairs ──
        if (data.attributes && typeof data.attributes === 'object') {
            const attrs = Object.entries(data.attributes).filter(([k, v]) => v !== null && v !== undefined && v !== '');
            if (attrs.length > 0) {
                // Separate long-form text fields from short ones
                const longFields = ['description', 'summary', 'backstory', 'purpose', 'function',
                                    'personality', 'motivation', 'production_notes', 'significance',
                                    'visual', 'appearance', 'logline', 'description', 'act_structure'];
                const longPairs = attrs.filter(([k]) => longFields.includes(k));
                const shortPairs = attrs.filter(([k]) => !longFields.includes(k));

                // Long fields first, rendered as prose blocks
                longPairs.forEach(([k, v]) => {
                    const display = renderAttrValue(v);
                    if (!display) return;
                    html += `<div class="preview-section">
                        <div class="preview-section-title">${escHtml(k.replace(/_/g, ' '))}</div>
                        <div class="preview-value">${display}</div>
                    </div>`;
                });

                // Short fields as a 2-col grid
                if (shortPairs.length > 0) {
                    html += `<div class="preview-section">
                        <div class="preview-section-title">Details</div>
                        <div class="preview-kv-grid">`;
                    shortPairs.forEach(([k, v]) => {
                        const display = renderAttrValue(v);
                        if (!display) return;
                        html += `<div class="preview-kv-item">
                            <div class="preview-kv-key">${escHtml(k.replace(/_/g, ' '))}</div>
                            <div class="preview-kv-val">${display}</div>
                        </div>`;
                    });
                    html += `</div></div>`;
                }
            }
        }

        // ── Relationships ────────────────────────────────────────────────
        if (data.relationships && data.relationships.length > 0) {
            html += `<div class="preview-section">
                <div class="preview-section-title">Relationships</div>
                <div style="display:flex; flex-direction:column; gap:6px;">`;
            data.relationships.slice(0, 8).forEach(r => {
                const target = escHtml(r.target || '');
                const type   = escHtml(r.type   || '');
                const desc   = escHtml(r.desc   || r.nature || '');
                html += `<div style="font-size:0.86rem; padding:5px 10px; background:rgba(0,0,0,0.15); border-radius:5px; border-left:2px solid var(--border);">
                    <span style="font-weight:700; color:var(--text);">${target}</span>
                    ${type ? `<span style="color:var(--text-muted); margin-left:6px; font-size:0.78rem;">(${type})</span>` : ''}
                    ${desc ? `<div style="color:var(--text-muted); font-size:0.8rem; margin-top:2px;">${desc}</div>` : ''}
                </div>`;
            });
            if (data.relationships.length > 8) {
                html += `<div style="font-size:0.78rem; color:var(--text-muted); padding:4px 10px;">+ ${data.relationships.length - 8} more…</div>`;
            }
            html += `</div></div>`;
        }

        // ── Timeline ─────────────────────────────────────────────────────
        if (data.timeline && data.timeline.length > 0) {
            html += `<div class="preview-section">
                <div class="preview-section-title">History / Timeline</div>
                <div style="display:flex; flex-direction:column; gap:5px;">`;
            data.timeline.slice(0, 6).forEach(t => {
                const date = t.date ? `<span style="font-family:monospace; font-size:0.75rem; color:var(--text-muted); margin-right:8px;">[${escHtml(String(t.date))}]</span>` : '';
                html += `<div style="font-size:0.85rem; padding:4px 10px; border-left:2px solid rgba(245,158,11,0.3);">${date}${escHtml(t.text || '')}</div>`;
            });
            if (data.timeline.length > 6) {
                html += `<div style="font-size:0.78rem; color:var(--text-muted); padding:4px 10px;">+ ${data.timeline.length - 6} more events…</div>`;
            }
            html += `</div></div>`;
        }

        // ── If nothing at all was found ───────────────────────────────────
        const hasContent = (data.attributes && Object.keys(data.attributes).length) 
                        || (data.relationships && data.relationships.length)
                        || (data.timeline && data.timeline.length)
                        || (data.roles && data.roles.length);
        if (!hasContent) {
            html += `<div class="preview-not-found" style="padding-top:10px;">No additional details available for this entity.</div>`;
        }

        container.innerHTML = html;
    }

    // Helper: safely escape HTML
    function escHtml(str) {
        return String(str)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;');
    }

    // Helper: render an attribute value as readable HTML
    function renderAttrValue(v) {
        if (v === null || v === undefined || v === '') return '';
        if (typeof v === 'string')  return escHtml(v);
        if (typeof v === 'number' || typeof v === 'boolean') return escHtml(String(v));
        if (Array.isArray(v)) {
            if (v.length === 0) return '';
            // Array of strings → pill row
            if (v.every(i => typeof i === 'string' || typeof i === 'number')) {
                return `<div class="preview-pill-row">${v.map(i => `<span class="preview-pill">${escHtml(String(i))}</span>`).join('')}</div>`;
            }
            // Array of objects → simple JSON dump
            return `<pre style="font-size:0.75rem; color:var(--text-muted); white-space:pre-wrap; word-break:break-word; margin:0;">${escHtml(JSON.stringify(v, null, 2))}</pre>`;
        }
        if (typeof v === 'object') {
            return `<pre style="font-size:0.75rem; color:var(--text-muted); white-space:pre-wrap; word-break:break-word; margin:0;">${escHtml(JSON.stringify(v, null, 2))}</pre>`;
        }
        return escHtml(String(v));
    }

    window.applyAdvancedFilter = function() {
        let items = [];
        const freeText = $('#filterFreeText').val().trim();
        $('#filterPot .filter-item').each(function() {
            const cat = $(this).attr('data-cat') || $(this).data('cat') || '';
            const text = $(this).find('.filter-text').text() || $(this).text().replace('×', '').trim();
            items.push({ cat: cat, name: text });
        });
        if(items.length === 0 && !freeText) { Toast.show("Filter Pot is empty", "warn"); return; }
        currentCustomQuery = { text: freeText, items: items };
        $('#filter-modal').hide();
        $('#activeFilterBadge').show().text(`Active: ${items.length} items + text`);
        currentPage = 1;
        loadLibraryPage(1);
    };

    // --- SAVE / LOAD (UPDATED FOR FRAMES) ---
    window.openSaveModal = function() { if(document.querySelectorAll('#timelineSortable .film-frame').length === 0) { Toast.show("Timeline empty", "error"); return; } $('#save-modal').css('display', 'flex'); };
    window.openLoadModal = function() { $('#load-modal').css('display', 'flex'); };

    window.performSave = function() {
        const frames = document.querySelectorAll('#timelineSortable .film-frame');
        
        const sequenceItems = Array.from(frames).map(el => {
            const sid = el.dataset.id || el.getAttribute('data-id');
            const fid = el.dataset.frameId || el.getAttribute('data-frame-id');
            if(!sid) return null;
            return {
                sketch_id: parseInt(sid),
                frame_id: fid ? parseInt(fid) : null
            };
        }).filter(item => item !== null);

        if(sequenceItems.length === 0) { Toast.show("Error: No valid items to save", "error"); return; }
        
        const name = document.getElementById('saveNameInput').value || 'Untitled Sequence';
        const desc = document.getElementById('saveDescInput').value;
        const docId = document.getElementById('contextDoc').value;
        const formData = new FormData();
        
        formData.append('action', 'save_sequence'); 
        formData.append('name', name); 
        formData.append('description', desc); 
        formData.append('sketch_ids', JSON.stringify(sequenceItems)); 
        formData.append('linked_doc_id', docId);
        
        if(currentSeqId) formData.append('sequence_id', currentSeqId);
        
        fetch('narratives_api.php', { method: 'POST', body: formData }).then(r => r.json()).then(d => {
            if(d.status === 'success') { Toast.show("Saved!"); currentSeqId = d.id; document.getElementById('seqNameDisplay').innerText = name; $('#save-modal').hide(); } else { Toast.show(d.message, "error"); }
        });
    };

    window.performLoad = function(seqData) {
        currentSeqId = seqData.id;
        document.getElementById('seqNameDisplay').innerText = seqData.name;
        document.getElementById('saveNameInput').value = seqData.name;
        document.getElementById('saveDescInput').value = seqData.description;
        
        if(seqData.linked_doc_id) { 
            const sel = document.getElementById('contextDoc');
            sel.value = seqData.linked_doc_id;
            if(sel.value) { currentPage = 1; loadLibraryPage(1); }
        }
        
        let rawIds = JSON.parse(seqData.sequence_data || '[]');
        if(rawIds && rawIds.length > 0) {
            fetch('narratives_api.php?action=hydrate_sequence', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ items: rawIds })
            })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    const track = document.getElementById('timelineSortable'); 
                    if(track) {
                        track.innerHTML = '';
                        if (!sketchRegistry || typeof sketchRegistry !== 'object') sketchRegistry = {};
                        res.data.forEach(item => {
                            const el = document.createElement('div'); el.className = 'film-frame'; 
                            el.dataset.id = item.id; 
                            el.dataset.name = item.name; 
                            el.dataset.desc = item.desc;
                            
                            if(item.active_frame_id) {
                                el.dataset.frameId = item.active_frame_id;
                            }
                            
                            el.innerHTML = `<img src="${item.thumb}"><div class="remove-frame" onclick="this.parentElement.remove(); updateOrd();">✕</div><div class="frame-ord"></div>`;
                            track.appendChild(el);
                            
                            
                            
                            if(item && item.id !== undefined) sketchRegistry[item.id] = item;
                            if(item.frames) frameRegistry[item.id] = item.frames;
                            if(item.active_frame_id) el.dataset.frameId = item.active_frame_id;

                            
                            
                        });
                        document.getElementById('emptyMsg').style.display = 'none'; updateOrd();
                    }
                }
            });
        }
        $('#load-modal').hide();
    };

    // --- STANDARD MODALS ---
    window.openEntityForm = function(activeSketchId) {
        const url = `entity_form.php?entity_type=sketches&entity_id=${activeSketchId}&view=modal`;
        document.getElementById('entity-iframe').src = url;
        $('#iframe-modal').css('display', 'flex');
    };
    window.openAnalysis = function(id) {
        const item = sketchRegistry[id];
        if(!item || !item.curation) { Toast.show("Data not loaded", "error"); return; }
        const data = item.curation; const body = document.getElementById('curation-modal-body');
        const scoreClass = data.score >= 8 ? 'score-high' : (data.score >= 5 ? 'score-mid' : 'score-low');
        let html = `<div style="margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:15px;"><div style="display:flex; justify-content:space-between; align-items:start;"><div><h2 style="margin:0; font-size:1.4em;">${data.name}</h2><div style="font-size:0.85em; color:var(--text-muted); margin-top:4px;">#${data.id}</div></div><div class="score-badge ${scoreClass}">${data.score}</div></div></div>`;
        if(data.class) html += `<div class="modal-row"><span class="modal-label">Class</span><div><span class="pill pill-func">${data.class.narrative_function}</span> <span class="pill">${data.class.emotional_tone}</span></div></div>`;
        if(data.themes && data.themes.primary_themes) html += `<div class="modal-row"><span class="modal-label">Themes</span><div>` + (Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes : []).map(t=>`<span class="pill pill-theme">${t}</span>`).join(' ') + `</div></div>`;
        if(data.entities && data.entities.characters) html += `<div class="modal-row"><span class="modal-label">Cast</span><div>` + (Array.isArray(data.entities.characters) ? data.entities.characters : []).map(c=>`<span class="pill pill-char">👤 ${c}</span>`).join(' ') + `</div></div>`;
        if(data.recs && data.recs.potential_use) html += `<div style="margin:15px 0; background:rgba(245,159,11,0.1); padding:12px; border-radius:6px; border:1px dashed rgba(245,159,11,0.4);"><span class="modal-label" style="color:#d97706; margin-bottom:5px; display:block;">💡 Potential Use</span><div style="font-size:0.95em;">${data.recs.potential_use}</div></div>`;
        body.innerHTML = html; $('#curation-modal').css('display', 'flex');
    };
    window.openDesc = function(id) { const item = sketchRegistry[id]; if(!item) return; document.getElementById('desc-title').innerText = item.name; document.getElementById('desc-body').innerText = item.desc; $('#desc-modal').css('display', 'flex'); };

    window.addEventListener('click', e => { 
        if (e.target.classList.contains('modal-overlay')) {
            $(e.target).hide(); 
            if (e.target.id === 'iframe-modal') document.getElementById('entity-iframe').src = '';
        } 
    });
    document.getElementById('pageInput').addEventListener("keypress", function(e) { if (e.key === "Enter") jumpToPage(this.value); });
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php echo $eruda ?? ''; ?>

<?php
// Surgical snippet to auto-load sequence for deep linking
$deepLinkSeqJson = 'null';
if (!empty($_GET['sequence_id'])) {
    $sid = (int)$_GET['sequence_id'];
    foreach ($seqRaw as $seq) {
        if ($seq['id'] == $sid) {
            $deepLinkSeqJson = json_encode($seq, JSON_HEX_APOS | JSON_HEX_QUOT);
            break;
        }
    }
}
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        let deepLinkSeq = <?= $deepLinkSeqJson ?>;
        if (deepLinkSeq) {
            setTimeout(() => performLoad(deepLinkSeq), 300);
        }
    });
</script>

</body>
</html>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
