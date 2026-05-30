<?php
// public/narratives_v11.php
// Showrunner V9 - Narrative Sequencer
// Features: Advanced Filter, Vector Search, Frame Flipping, Mixed-Sequence Data, Global Search
// V9.1: Player Frame Cycling — swap active frame per pot slot in play mode
// V9.2: Entity Preview ("Peek") button in Advanced Filter — peek into filter items without leaving the filter modal
// V9.3: Tablet drag-and-drop fix — forceFallback on all Sortable instances for consistent Android touch handling
// V11: Added V11 Hybrid Engine Settings to the Advanced Filter modal
//      (Enable Graph-Walker, Enable SQL Tag Striker, Enable Chroma Sweep)
//      Filter payload dynamically bundles these toggles.
// V11.1: Forge filter option added directly into the context select to replace the empty global state.
// V11.2: Frame switcher in forge results; direct Chroma collection query options; fuzz promoted-only.
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
    .context-select { padding: 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); flex:1; max-width: 300px; font-weight: 700; border-left: 4px solid var(--highlight); transition: border-color 0.2s; }
    .context-select.forge-active { border-left-color: #f59e0b; color: #f59e0b; }
    .context-select.chroma-active { border-left-color: #8b5cf6; color: #a78bfa; }

    .btn-icon { padding: 8px 12px; border-radius: 6px; cursor: pointer; border: 1px solid var(--border); background: var(--bg); display: flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 0.9rem; color: var(--text); transition: background 0.2s; }
    .btn-icon:hover { background: var(--card); }
    .filter-btn { background: var(--highlight); color: #fff; border: none; }
    .filter-btn:hover { background: #059669; }
    .reset-load-btn { padding: 8px; min-width: 36px; justify-content: center; font-size: 1.1rem; }
    .reset-load-btn:hover { background: var(--card); border-color: var(--highlight); }
    .debug-btn { padding: 8px; min-width: 36px; justify-content: center; font-size: 1.1rem; border-color: rgba(139,92,246,0.4); color: #a78bfa; }
    .debug-btn:hover { background: rgba(139,92,246,0.1); border-color: #a78bfa; }

    /* Chroma query bar */
    .chroma-query-bar { display: none; flex-shrink: 0; padding: 8px 10px; background: rgba(139,92,246,0.06); border-bottom: 1px solid rgba(139,92,246,0.2); gap: 8px; align-items: center; }
    .chroma-query-bar.visible { display: flex; }
    .chroma-query-input { flex: 1; padding: 7px 12px; border: 1px solid rgba(139,92,246,0.35); background: var(--bg); color: var(--text); border-radius: 6px; font-size: 0.9rem; font-family: monospace; }
    .chroma-query-input:focus { outline: none; border-color: #8b5cf6; }
    .chroma-query-btn { padding: 7px 16px; background: #8b5cf6; color: #fff; border: none; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer; white-space: nowrap; }
    .chroma-query-btn:hover { background: #7c3aed; }
    .chroma-collection-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 10px; background: rgba(139,92,246,0.15); color: #a78bfa; border: 1px solid rgba(139,92,246,0.3); white-space: nowrap; flex-shrink: 0; }

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
    

    .frame-nav-btn { position: absolute; bottom: 0; width: 40px; height: 40px; background: rgba(0,0,0,0.25); color: rgba(255,255,255,0.6); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 25; font-size: 1.2rem; transition: background 0.2s, color 0.2s; opacity: 1; }
    .frame-nav-btn:hover { background: var(--highlight); color: white; }
    
    
    
    
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

    /* V11 Engine toggles row */
    .v11-engine-settings {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
    }
    .v11-engine-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--highlight);
        margin-bottom: 8px;
        display: block;
    }
    .v11-check-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .v11-check-group {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        font-size: 0.82rem;
        color: var(--text-muted);
        user-select: none;
        white-space: nowrap;
    }
    .v11-check-group input[type=checkbox] {
        width: 15px;
        height: 15px;
        accent-color: var(--highlight);
        cursor: pointer;
        flex-shrink: 0;
    }
    .v11-check-group:hover { color: var(--text); }

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

    
    
    
    
    /* IFRAME MODAL — true full-screen, no chrome, close button floats over iframe */
    #iframe-modal {
        padding: 0;
        align-items: stretch;
        justify-content: stretch;
    }
    #iframe-modal .modal-content {
        padding: 0; border: none; box-shadow: none;
        max-width: 100%; width: 100vw; height: 100vh;
        border-radius: 0; background: transparent;
        display: flex; flex-direction: column;
        margin: 0;
    }
    #iframe-modal .modal-close {
        position: fixed; top: 12px; right: 14px; z-index: 5100;
        background: rgba(0,0,0,0.55); color: #fff;
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; line-height: 1;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.15);
    }
    #iframe-modal iframe#entity-iframe {
        flex: 1; width: 100%; height: 100%;
        border: none; border-radius: 0; margin: 0; padding: 0;
        transform: none; background: var(--bg); display: block;
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

    /* ================================================================
       FORGE FILTER MODAL STYLES
       ================================================================ */
    #forge-filter-modal .modal-content {
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 0;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .forge-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        background: rgba(245, 158, 11, 0.06);
        border-bottom: 1px solid rgba(245, 158, 11, 0.2);
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .forge-modal-header h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #f59e0b;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .forge-modal-body {
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* Forge filter section card */
    .forge-section {
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }
    .forge-section-head {
        padding: 10px 14px;
        background: var(--card);
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .forge-section-head .forge-section-icon {
        font-size: 1rem;
        opacity: 0.8;
    }
    .forge-section-body {
        padding: 12px 14px;
        background: rgba(0,0,0,0.1);
    }

    /* Forge search input */
    .forge-search-wrap {
        position: relative;
        margin-bottom: 8px;
    }
    .forge-search-input {
        width: 100%;
        padding: 9px 36px 9px 12px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        border-radius: 6px;
        font-size: 0.9rem;
        box-sizing: border-box;
    }
    .forge-search-input:focus { outline: none; border-color: #f59e0b; }
    .forge-search-clear {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 1rem;
        line-height: 1;
        padding: 0;
        display: none;
    }

    /* Forge tag chips — selected items */
    .forge-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-height: 32px;
    }
    .forge-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.35);
        color: #f59e0b;
        cursor: default;
    }
    .forge-chip-x {
        cursor: pointer;
        font-size: 0.9rem;
        opacity: 0.7;
        line-height: 1;
    }
    .forge-chip-x:hover { opacity: 1; color: #ef4444; }

    /* Forge dropdown list */
    .forge-dropdown {
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--card);
        max-height: 160px;
        overflow-y: auto;
        display: none;
    }
    .forge-dropdown.open { display: block; }
    .forge-dropdown-item {
        padding: 8px 12px;
        font-size: 0.88rem;
        cursor: pointer;
        border-bottom: 1px solid rgba(255,255,255,0.04);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.1s;
        color: var(--text);
    }
    .forge-dropdown-item:last-child { border-bottom: none; }
    .forge-dropdown-item:hover { background: rgba(245, 158, 11, 0.08); }
    .forge-dropdown-item.selected { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
    .forge-dropdown-item-meta {
        font-size: 0.72rem;
        color: var(--text-muted);
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .forge-dropdown-loading {
        padding: 12px;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    /* Forge vector text input */
    .forge-vector-input {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        border-radius: 6px;
        font-size: 0.9rem;
        font-family: monospace;
        resize: vertical;
        min-height: 60px;
        box-sizing: border-box;
    }
    .forge-vector-input:focus { outline: none; border-color: #f59e0b; }

    /* Forge filter mode toggle */
    .forge-mode-row {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
    }
    .forge-mode-btn {
        padding: 6px 14px;
        border-radius: 20px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s;
    }
    .forge-mode-btn.active {
        background: rgba(245, 158, 11, 0.15);
        border-color: rgba(245, 158, 11, 0.5);
        color: #f59e0b;
    }

    /* Forge entity type selector */
    .forge-entity-select {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        border-radius: 6px;
        font-size: 0.9rem;
        box-sizing: border-box;
    }
    .forge-entity-select:focus { outline: none; border-color: #f59e0b; }

    /* Forge pagination & results summary */
    .forge-results-meta {
        font-size: 0.78rem;
        color: var(--text-muted);
        text-align: center;
        padding: 4px 0;
    }

    /* Forge result grid */
    .forge-result-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 8px;
        max-height: 260px;
        overflow-y: auto;
    }
    .forge-result-card {
        border: 1px solid var(--border);
        border-radius: 6px;
        overflow: hidden;
        background: var(--card);
        cursor: pointer;
        transition: border-color 0.15s, transform 0.1s;
        position: relative;
    }
    .forge-result-card:hover { border-color: #f59e0b; transform: translateY(-1px); }
    .forge-result-card.selected { border-color: var(--highlight); box-shadow: 0 0 8px rgba(16,185,129,0.3); }
    .forge-result-card img {
        width: 100%;
        aspect-ratio: 16/9;
        object-fit: cover;
        display: block;
    }
    .forge-result-card-label {
        padding: 4px 6px;
        font-size: 0.7rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--text);
    }
    .forge-result-card-id {
        padding: 0 6px 4px;
        font-size: 0.65rem;
        color: var(--text-muted);
        font-family: monospace;
    }
    .forge-result-empty {
        grid-column: 1 / -1;
        text-align: center;
        padding: 30px 0;
        color: var(--text-muted);
        font-size: 0.88rem;
    }
    .forge-result-loading {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 30px 0;
        color: var(--text-muted);
        font-size: 0.88rem;
    }

    /* Forge action buttons row */
    .forge-action-row {
        display: flex;
        gap: 10px;
        align-items: center;
        padding: 14px 20px;
        border-top: 1px solid rgba(245, 158, 11, 0.15);
        background: rgba(245, 158, 11, 0.03);
        position: sticky;
        bottom: 0;
    }
    .forge-apply-btn {
        flex: 1;
        padding: 11px 20px;
        background: #f59e0b;
        color: #000;
        border: none;
        border-radius: 6px;
        font-weight: 800;
        font-size: 0.95rem;
        cursor: pointer;
        transition: background 0.15s;
    }
    .forge-apply-btn:hover { background: #d97706; }
    .forge-reset-btn {
        padding: 11px 16px;
        background: transparent;
        color: var(--text-muted);
        border: 1px solid var(--border);
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .forge-reset-btn:hover { border-color: #ef4444; color: #ef4444; background: rgba(239,68,68,0.06); }

    /* Forge pagination buttons */
    .forge-pag-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 8px;
    }
    .forge-pag-btn {
        padding: 5px 14px;
        border-radius: 5px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.15s;
    }
    .forge-pag-btn:hover:not(:disabled) { border-color: #f59e0b; color: #f59e0b; }
    .forge-pag-btn:disabled { opacity: 0.3; cursor: not-allowed; }
</style>

<div class="sequencer-layout">
    <!-- 1. TIMELINE AREA -->
    <div class="timeline-area">
        <div class="timeline-header">
            <div id="seqNameDisplay" class="seq-title-display">Untitled Sequence</div>
            <div style="display:flex; gap:8px;">
                <a href="/auto_narratives_v11.php" class="btn-icon" style="text-decoration:none;" title="Auto-Narrative Lab">⚡</a>
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
        <select id="contextDoc" class="context-select forge-active">
            <option value="Forge">⚙️ Forge Filter</option>
            <option value="ChromaSketches">🧬 Chroma: Sketches</option>
            <option value="ChromaImages">🧬 Chroma: Images</option>
            <option value="ChromaAll">🧬 Chroma: All Collections</option>
            <?php foreach($contextDocs as $d): ?>
                <option value="<?= htmlspecialchars($d['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        
        <button class="btn-icon filter-btn" onclick="openFilterModal()">🧠 Advanced Filter</button>
        <button class="btn-icon reset-load-btn" onclick="resetAndLoad()" title="Reset all filters and load gallery">🔄</button>
        <button class="btn-icon debug-btn" id="debugBtn" onclick="openDebugModal()" title="Show filter query debug log" style="display:none;">🔬</button>
        <div id="activeFilterBadge" style="display:none; font-size:0.8rem; color:var(--highlight); margin-left:10px;">Active Custom Filter</div>
    </div>

    <!-- 2b. CHROMA QUERY BAR (visible only when a Chroma collection option is selected) -->
    <div class="chroma-query-bar" id="chromaQueryBar">
        <span class="chroma-collection-badge" id="chromaCollectionBadge">sage_sketches_nu</span>
        <input type="text" class="chroma-query-input" id="chromaQueryInput"
               placeholder="Describe what you're looking for semantically…"
               onkeydown="if(event.key==='Enter') runChromaQuery()">
        <button class="chroma-query-btn" onclick="runChromaQuery()">Search</button>
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

                <!-- V11 HYBRID ENGINE SETTINGS -->
                <div class="v11-engine-settings">
                    <span class="v11-engine-label">V11 Hybrid Engine Settings</span>
                    <div class="v11-check-row">
                        <label class="v11-check-group" title="Walk 1-degree of Knowledge Graph edges for causal logic">
                            <input type="checkbox" id="chkEnableKgGraph">
                            Enable Graph-Walker (KG)
                        </label>
                        <label class="v11-check-group" title="Prioritize exact matches from the SQL tags table">
                            <input type="checkbox" id="chkEnableSqlTags">
                            Enable SQL Tag Striker
                        </label>
                        <label class="v11-check-group" title="Use Chroma vector database for semantic vibes">
                            <input type="checkbox" id="chkEnableChroma" checked>
                            Enable Chroma Sweep
                        </label>
                    </div>
                </div>
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

<!-- FORGE FILTER MODAL -->
<div id="forge-filter-modal" class="modal-overlay">
    <div class="modal-content">
       <div class="forge-modal-header">
            <h3>⚙️ Forge Filter</h3>
            <span class="modal-close" onclick="document.getElementById('forge-filter-modal').style.display='none'">&times;</span>
        </div>

        <div class="forge-modal-body">

            <!-- Entity Type -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🗂</span> Entity Type</div>
                <div class="forge-section-body">
                    <select id="forgeEntityType" class="forge-entity-select">
                        <option value="sketches">Sketches</option>
                        
                    </select>
                </div>
            </div>

            <!-- Filter Mode -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">⚡</span> Filter Combine Mode</div>
                <div class="forge-section-body">
                    <div class="forge-mode-row">
                        <button class="forge-mode-btn active" id="forgeModeIntersection" onclick="setForgeMode('intersection')">AND (intersection)</button>
                        <button class="forge-mode-btn" id="forgeModeUnion" onclick="setForgeMode('union')">OR (union)</button>
                        <span style="font-size:0.75rem; color:var(--text-muted); margin-left:4px;" id="forgeModeLabel">All filters must match</span>
                    </div>
                </div>
            </div>

            <!-- Fuzz Candidate -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🔮</span> Fuzz Concept</div>
                <div class="forge-section-body">
                    <div class="forge-search-wrap">
                        <input type="text" class="forge-search-input" id="forgeFuzzSearch"
                               placeholder="Search fuzz concepts…"
                               oninput="forgeSearchDebounced('fuzz', this.value)"
                               onfocus="forgeSearchDebounced('fuzz', this.value)">
                        <button class="forge-search-clear" id="forgeFuzzClear" onclick="forgeClearSearch('fuzz')">×</button>
                    </div>
                    <div class="forge-dropdown" id="forgeFuzzDropdown"></div>
                    <div class="forge-chips" id="forgeFuzzChips"></div>
                </div>
            </div>

            <!-- Lore Doc -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">📖</span> Lore Document</div>
                <div class="forge-section-body">
                    <div class="forge-search-wrap">
                        <input type="text" class="forge-search-input" id="forgeDocSearch"
                               placeholder="Search lore docs…"
                               oninput="forgeSearchDebounced('doc', this.value)"
                               onfocus="forgeSearchDebounced('doc', this.value)">
                        <button class="forge-search-clear" id="forgeDocClear" onclick="forgeClearSearch('doc')">×</button>
                    </div>
                    <div class="forge-dropdown" id="forgeDocDropdown"></div>
                    <div class="forge-chips" id="forgeDocChips"></div>
                    <!-- Entity names within doc (shown when doc is selected) -->
                    <div id="forgeDocEntityWrap" style="display:none; margin-top:10px;">
                        <div style="font-size:0.72rem; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; font-weight:700;">Filter by entity within doc</div>
                        <div class="forge-search-wrap">
                            <input type="text" class="forge-search-input" id="forgeDocEntitySearch"
                                   placeholder="Search entities in doc…"
                                   oninput="forgeSearchDebounced('doc_entity', this.value)"
                                   onfocus="forgeSearchDebounced('doc_entity', this.value)">
                            <button class="forge-search-clear" id="forgeDocEntityClear" onclick="forgeClearSearch('doc_entity')">×</button>
                        </div>
                        <div class="forge-dropdown" id="forgeDocEntityDropdown"></div>
                        <div class="forge-chips" id="forgeDocEntityChips"></div>
                    </div>
                </div>
            </div>

            <!-- KG Node -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🕸</span> Knowledge Graph Node</div>
                <div class="forge-section-body">
                    <div class="forge-search-wrap">
                        <input type="text" class="forge-search-input" id="forgeKgSearch"
                               placeholder="Search KG nodes…"
                               oninput="forgeSearchDebounced('kg', this.value)"
                               onfocus="forgeSearchDebounced('kg', this.value)">
                        <button class="forge-search-clear" id="forgeKgClear" onclick="forgeClearSearch('kg')">×</button>
                    </div>
                    <div class="forge-dropdown" id="forgeKgDropdown"></div>
                    <div class="forge-chips" id="forgeKgChips"></div>
                </div>
            </div>

            <!-- Narrative Sequence -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🎞</span> Narrative Sequence</div>
                <div class="forge-section-body">
                    <div class="forge-search-wrap">
                        <input type="text" class="forge-search-input" id="forgeSeqSearch"
                               placeholder="Search sequences…"
                               oninput="forgeSearchDebounced('seq', this.value)"
                               onfocus="forgeSearchDebounced('seq', this.value)">
                        <button class="forge-search-clear" id="forgeSeqClear" onclick="forgeClearSearch('seq')">×</button>
                    </div>
                    <div class="forge-dropdown" id="forgeSeqDropdown"></div>
                    <div class="forge-chips" id="forgeSeqChips"></div>
                </div>
            </div>

            <!-- Storyboard -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🖼</span> Storyboard</div>
                <div class="forge-section-body">
                    <div class="forge-search-wrap">
                        <input type="text" class="forge-search-input" id="forgeSbSearch"
                               placeholder="Search storyboards…"
                               oninput="forgeSearchDebounced('storyboard', this.value)"
                               onfocus="forgeSearchDebounced('storyboard', this.value)">
                        <button class="forge-search-clear" id="forgeSbClear" onclick="forgeClearSearch('storyboard')">×</button>
                    </div>
                    <div class="forge-dropdown" id="forgeSbDropdown"></div>
                    <div class="forge-chips" id="forgeSbChips"></div>
                </div>
            </div>

            <!-- Vector / Semantic Search -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🧬</span> Semantic / Vector Search</div>
                <div class="forge-section-body">
                    <textarea id="forgeVectorText" class="forge-vector-input"
                              placeholder="Describe what you're looking for semantically…"></textarea>
                </div>
            </div>

            <!-- Text Search -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🔍</span> Text Search</div>
                <div class="forge-section-body">
                    <input type="text" id="forgeTextSearch" class="forge-search-input"
                           placeholder="Search by name or frame name…">
                </div>
            </div>
            
            
            
            <!-- ID Search -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🔢</span> Search by ID</div>
                <div class="forge-section-body" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:120px;">
                        <label style="font-size:0.72rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:5px;">Sketch ID</label>
                        <input type="number" id="forgeSketchIdSearch" class="forge-search-input"
                               placeholder="e.g. 1042" min="1" style="width:100%; box-sizing:border-box;">
                    </div>
                    <div style="flex:1; min-width:120px;">
                        <label style="font-size:0.72rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:5px;">Frame ID</label>
                        <input type="number" id="forgeFrameIdSearch" class="forge-search-input"
                               placeholder="e.g. 5503" min="1" style="width:100%; box-sizing:border-box;">
                    </div>
                </div>
            </div>

            <!-- Preview Results -->
            <div class="forge-section">
                <div class="forge-section-head"><span class="forge-section-icon">🖼</span> Preview Results
                    <span id="forgePreviewMeta" style="font-weight:400; font-size:0.72rem; margin-left:8px; color:var(--text-muted);"></span>
                </div>
                <div class="forge-section-body" style="padding-bottom:8px;">
                    <button class="forge-mode-btn" style="margin-bottom:10px;" onclick="runForgePreview(1)">▶ Preview</button>
                    <div class="forge-result-grid" id="forgeResultGrid">
                        <div class="forge-result-empty">Configure filters above and tap Preview.</div>
                    </div>
                    <div class="forge-pag-row" id="forgePagRow" style="display:none;">
                        <button class="forge-pag-btn" id="forgePagPrev" onclick="runForgePreview(forgePreviewPage - 1)" disabled>←</button>
                        <span id="forgePagLabel" style="font-size:0.8rem; color:var(--text-muted);"></span>
                        <button class="forge-pag-btn" id="forgePagNext" onclick="runForgePreview(forgePreviewPage + 1)" disabled>→</button>
                    </div>
                </div>
            </div>

        </div><!-- /forge-modal-body -->

        <!-- Sticky action row -->
        <div class="forge-action-row">
            <button class="forge-reset-btn" onclick="resetForgeFilter()">↺ Reset</button>
            <button class="forge-apply-btn" onclick="applyForgeFilter()">APPLY FORGE FILTER</button>
        </div>

    </div>
</div>

<!-- ENTITY PREVIEW MODAL (Peek) — z-index 5000, on top of filter modal -->
<div id="entity-preview-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close" onclick="document.getElementById('entity-preview-modal').style.display='none'">&times;</span>
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
    <div class="modal-content">
        <span class="modal-close" onclick="$('#iframe-modal').hide(); document.getElementById('entity-iframe').src = '';">&times;</span>
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

    // ── FORGE FILTER STATE ────────────────────────────────────────────
    let forgeState = {
        fuzz:       { id: null, label: null },
        doc:        { id: null, label: null },
        doc_entity: [],
        kg:         { id: null, label: null },
        seq:        { id: null, label: null },
        storyboard: { id: null, label: null },
        vectorText: '',
        textSearch: '',
        filterMode: 'intersection',
        entityType: 'sketches',
    };
    let forgeActiveParams = null;
    let forgePreviewPage = 1;
    let forgePreviewTotal = 0;
    let forgePreviewPages = 1;
    const forgeDebounceTimers = {};

    // ── CHROMA COLLECTION STATE ───────────────────────────────────────
    // null = not in chroma mode; otherwise one of the collection constants
    let currentChromaCollection = null;
    const CHROMA_COLLECTION_MAP = {
        'ChromaSketches': 'sage_sketches_nu',
        'ChromaImages':   'sage_nu_images',
        'ChromaAll':      '__all__',
    };
    const CHROMA_LABEL_MAP = {
        'ChromaSketches': 'sage_sketches_nu',
        'ChromaImages':   'sage_nu_images',
        'ChromaAll':      'all collections',
    };

    // Shared Sortable options that enable reliable drag on Android tablets.
    const SORTABLE_TOUCH_OPTS = {
        forceFallback: true,
        fallbackTolerance: 3,
        touchStartThreshold: 3
    };

    document.addEventListener('DOMContentLoaded', () => {
        if(typeof PhotoSwipeLightbox !== 'undefined') {
            photoSwipeLightbox = new PhotoSwipeLightbox({
                gallery: '#libWrapper', children: 'a.pswp-link', pswpModule: PhotoSwipe,
                initialZoomLevel: 'fit',
                secondaryZoomLevel: 1
            });
            photoSwipeLightbox.init();
        }

        // Delegated handler for forge dropdown items
        document.addEventListener('click', function(e) {
            const item = e.target.closest('.forge-dropdown-item[data-item]');
            if (!item) return;
            const slot = item.dataset.slot;
            const data = JSON.parse(item.dataset.item);
            forgeSelectItem(slot, data);
        });

        document.addEventListener('click', function(e) {
            // forge dropdown item with JSON data
            const itemEl = e.target.closest('.forge-dropdown-item[data-item]');
            if (itemEl) {
                const slot = itemEl.dataset.slot;
                const data = JSON.parse(itemEl.dataset.item);
                forgeSelectItem(slot, data);
                return;
            }
            // forge doc_entity dropdown item
            const entityEl = e.target.closest('.forge-dropdown-item[data-entity-name]');
            if (entityEl) {
                forgeSelectDocEntity(entityEl.dataset.entityName);
                return;
            }
        });

        // Timeline Sortable
        new Sortable(document.getElementById('timelineSortable'), {
            ...SORTABLE_TOUCH_OPTS,
            group: 'shared', animation: 150, direction: 'horizontal',
            onAdd: function (evt) {
                const item = evt.item; const d = item.dataset;
                const id = d.id || item.getAttribute('data-id');
                const frameId = d.activeFrameId || item.getAttribute('data-active-frame-id');
                
                setTimeout(() => {
                    item.className = 'film-frame';
                    item.dataset.id = id; item.dataset.name = d.name; item.dataset.desc = d.desc;
                    if(frameId) item.dataset.frameId = frameId; 
                    
                    item.innerHTML = `<img src="${item.querySelector('img').src}"><div class="remove-frame" onclick="this.parentElement.remove(); updateOrd();">✕</div><div class="frame-ord"></div>`;
                    item.style.width = ''; item.style.transform = '';
                    
                    const navs = item.querySelectorAll('.frame-nav-btn');
                    navs.forEach(n => n.remove());
                    const dh = item.querySelector('.drag-handle');
                    if(dh) dh.remove();
                    
                    document.getElementById('emptyMsg').style.display = 'none'; updateOrd();
                }, 10);
            }, onUpdate: updateOrd
        });

        // Context Change Listener
        // NOTE: changing the select no longer fires an API request automatically.
        // Use the 🔄 reset button or the filter/search controls to load results.
        // Pagination buttons continue to fire immediately.
        document.getElementById('contextDoc').addEventListener('change', function() {
            const val = this.value;
            const isForge  = val === 'Forge';
            const isChroma = val in CHROMA_COLLECTION_MAP;

            this.classList.toggle('forge-active',  isForge);
            this.classList.toggle('chroma-active', isChroma);

            currentCustomQuery    = null;
            forgeActiveParams     = null;
            currentChromaCollection = null;
            $('#activeFilterBadge').hide();
            currentPage = 1;

            // Show/hide chroma query bar
            const bar = document.getElementById('chromaQueryBar');
            if (isChroma) {
                currentChromaCollection = CHROMA_COLLECTION_MAP[val];
                document.getElementById('chromaCollectionBadge').textContent = CHROMA_LABEL_MAP[val];
                document.getElementById('chromaQueryInput').value = '';
                bar.classList.add('visible');
                // Do NOT auto-load; user must run a query via the chroma query bar
            } else {
                bar.classList.remove('visible');
                // Do NOT auto-load; user triggers load via filter modal or 🔄 button
            }
        });
        
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
                item.innerHTML = `${text} <span class="remove-x" onclick="this.parentElement.remove()">×</span>`;
            }
        });
    });

    // --- LIBRARY LOGIC ---
    function loadLibraryPage(page) {
        document.getElementById('loadingState').style.display = 'flex';
        document.getElementById('mainSwiper').style.opacity = '0.3';
        
        let docId = document.getElementById('contextDoc').value;
        // Treat special non-doc option values as empty for the standard API
        if (docId === 'Forge' || docId in CHROMA_COLLECTION_MAP) docId = '';
        
        let url = `narratives_api_v11.php?action=fetch_library&page=${page}&context_id=${docId}`;
        
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
                        if(item.frames && Array.isArray(item.frames)) {
                            frameRegistry[item.id] = item.frames;
                        }
                    }
                });

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

    // --- FORGE LIBRARY LOAD ---
    // Groups rows by entity_id so that each entity card shows all its frames,
    // enabling the frame-switcher arrows (frame-nav-left / frame-nav-right).
    function loadForgeLibraryPage(page) {
        if (!forgeActiveParams) return;

        document.getElementById('loadingState').style.display = 'flex';
        document.getElementById('mainSwiper').style.opacity = '0.3';

        const params = new URLSearchParams(forgeActiveParams);
        params.set('action', 'list_frames');
        params.set('page', page);
        params.set('per_page', '50');
        params.set('include_membership', '0');
        params.set('sort', 'entity_id'); // group sibling frames together in result set

        fetch('filter_forge_api.php?' + params.toString())
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    currentPage = res.meta.page;
                    totalPages  = res.meta.pages;

                    // ── Group rows by entity_id ────────────────────────────────
                    // API returns one row per frame; we want one card per entity
                    // with all its frames collected so the switcher can cycle them.
                    const entityMap = new Map(); // entity_id -> item
                    res.data.forEach(row => {
                        const eid = row.entity_id;
                        if (!entityMap.has(eid)) {
                            entityMap.set(eid, {
                                id:      eid,
                                name:    row.entity_name || row.frame_name,
                                desc:    '',
                                thumb:   row.filename,
                                frames:  [],
                                isMatch: false,
                                score:   0,
                                curation: null,
                            });
                        }
                        entityMap.get(eid).frames.push({ id: row.frame_id, filename: row.filename });
                    });

                    const items = Array.from(entityMap.values());

                    currentLibraryPage = items;
                    if (!sketchRegistry) sketchRegistry = {};
                    items.forEach(item => {
                        if (item && item.id != null) {
                            sketchRegistry[item.id] = item;
                            frameRegistry[item.id]  = item.frames;
                        }
                    });

                    lastDebugLog = null;
                    document.getElementById('debugBtn').style.display = 'none';

                    renderLibrary(items);
                    updatePaginationUI();
                } else {
                    alert('Forge API error: ' + res.message);
                }
            })
            .finally(() => {
                document.getElementById('loadingState').style.display = 'none';
                document.getElementById('mainSwiper').style.opacity = '1';
            });
    }

    // --- CHROMA COLLECTION DIRECT QUERY ---
    window.runChromaQuery = function() {
        const q = document.getElementById('chromaQueryInput').value.trim();
        if (!q) { Toast.show('Enter a semantic query first', 'warn'); return; }

        // Build forge params with vector_text + chroma_collection
        const p = new URLSearchParams();
        p.set('entity_type', 'sketches');
        p.set('vector_text', q);
        p.set('chroma_collection', currentChromaCollection);
        p.set('filter_mode', 'intersection');

        forgeActiveParams  = p;
        currentCustomQuery = null;
        currentPage        = 1;

        const collLabel = document.getElementById('chromaCollectionBadge').textContent;
        $('#activeFilterBadge').show().text('Chroma: ' + collLabel + ' — "' + q.substring(0, 40) + (q.length > 40 ? '…' : '') + '"');

        loadForgeLibraryPage(1);
    };

    function updatePaginationUI() {
        document.getElementById('pageInput').value = currentPage;
        document.getElementById('pageTotalLabel').innerText = `of ${totalPages}`;
        document.getElementById('btnPrev').disabled = (currentPage <= 1);
        document.getElementById('btnNext').disabled = (currentPage >= totalPages);
    }

    function changePage(delta) {
        const target = currentPage + delta;
        if(target >= 1 && target <= totalPages) {
            if (forgeActiveParams) {
                loadForgeLibraryPage(target);
            } else {
                loadLibraryPage(target);
            }
        }
    }
    
    function jumpToPage(val) {
        let p = parseInt(val); if(isNaN(p)) p = 1; if(p < 1) p = 1; if(p > totalPages) p = totalPages;
        if (forgeActiveParams) {
            loadForgeLibraryPage(p);
        } else {
            loadLibraryPage(p);
        }
    }

    function updateOrd() { document.querySelectorAll('.frame-ord').forEach((el, i) => el.innerText = i + 1); }

    function renderLibrary(items) {
        const wrapper = document.getElementById('libWrapper');
        wrapper.innerHTML = '';
        items.forEach(s => {
            const slide = document.createElement('div'); slide.className = 'swiper-slide';
            slide.setAttribute('data-id', s.id);
            const initialFrame = (s.frames && s.frames.length > 0) ? s.frames[0] : {id:null, filename: s.thumb};
            
            slide.dataset.id = s.id; 
            slide.dataset.thumb = initialFrame.filename; 
            slide.dataset.name = s.name; 
            slide.dataset.desc = s.desc;
            slide.dataset.activeFrameId = initialFrame.id; 
            
            let matchHtml = '';
            if (s.isMatch) {
                if (s.score > 0 && s.score <= 1) matchHtml = `<span class="match-reason">Sim: ${Math.round(s.score * 100)}%</span>`;
                else if (s.matches && s.matches.length > 0) matchHtml = `<span class="match-reason">Matches: ${s.matches.slice(0, 3).join(', ')}</span>`;
            }
            
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
                        <img src="${initialFrame.filename}" loading="lazy" id="img_${s.id}" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
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
        
        new Sortable(wrapper, { 
            ...SORTABLE_TOUCH_OPTS,
            group: { name: 'shared', pull: 'clone', put: false }, 
            sort: false, 
            handle: '.drag-handle', 
            onClone: function(evt) { 
                const s = evt.item; 
                const clone = evt.clone;
                clone.dataset.id = s.dataset.id;
                clone.dataset.thumb = s.dataset.thumb;
                clone.dataset.name = s.dataset.name;
                clone.dataset.desc = s.dataset.desc;
                clone.dataset.activeFrameId = s.dataset.activeFrameId; 
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

        const img = document.getElementById(`player-img-${potIndex}`);
        if (img) img.src = newFrame.filename;

        const potFrames = document.querySelectorAll('#timelineSortable .film-frame');
        if (potFrames[potIndex]) {
            const potEl = potFrames[potIndex];
            potEl.dataset.frameId = newFrame.id;
            const potImg = potEl.querySelector('img');
            if (potImg) potImg.src = newFrame.filename;
        }
    };

    // --- PLAY SEQUENCE ---
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

            let activeFrameIdx = 0;
            if (currentFid && sketchFrames.length > 1) {
                const fi = sketchFrames.findIndex(f => f.id == currentFid);
                if (fi !== -1) activeFrameIdx = fi;
            }

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
    window.resetAndLoad = function() {
        const ctx = document.getElementById('contextDoc');
        ctx.value = 'Forge';
        ctx.classList.add('forge-active');
        ctx.classList.remove('chroma-active');

        currentCustomQuery      = null;
        lastDebugLog            = null;
        currentChromaCollection = null;
        $('#activeFilterBadge').hide();
        document.getElementById('debugBtn').style.display = 'none';
        document.getElementById('chromaQueryBar').classList.remove('visible');
        document.getElementById('chromaQueryInput').value = '';
        
        $('#filterFreeText').val('');
        $('#filterPot').html('');
        $('#filterItems').html('');
        $('#filterCats .filter-item').removeClass('active');
        
        document.getElementById('chkEnableKgGraph').checked = false;
        document.getElementById('chkEnableSqlTags').checked = false;
        document.getElementById('chkEnableChroma').checked = true;
        
        forgeActiveParams = null;
        resetForgeStateOnly();
        
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

        if (docId === 'Forge') {
            $('#forge-filter-modal').css('display', 'flex');
            return;
        }

        // Chroma collection options don't have a filter modal — show a toast hint
        if (docId in CHROMA_COLLECTION_MAP) {
            Toast.show('Use the query bar below the controls to search this collection', 'info');
            return;
        }

        $('#filter-modal').css('display', 'flex');
        
        if (!docId) {
             $('#filterCats').html('<div style="padding:10px; color:#888; font-style:italic;">Global Search Mode.<br>Select a Context Doc to enable categorical filters.</div>');
             $('#filterItems').html('');
             $('#filterPot').html('');
        } else {
             $('#filterCats').html('<div style="padding:10px; color:#888;">Loading...</div>');
             $('#filterItems').html('');
             fetch(`narratives_api_v11.php?action=get_filter_cats&doc_id=${docId}`).then(r=>r.json()).then(res => {
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
        let docId = document.getElementById('contextDoc').value;
        if (docId === 'Forge' || docId in CHROMA_COLLECTION_MAP) docId = '';
        if (!docId) return;
        
        currentActiveCat = category;
        $('#filterItems').html('<div style="padding:10px;">Loading...</div>');
        $('#filterCats .filter-item').removeClass('active');
        if (el) $(el).addClass('active');
        fetch(`narratives_api_v11.php?action=get_filter_items&doc_id=${docId}&cat=${encodeURIComponent(category)}`).then(r=>r.json()).then(res => {
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
        event.stopPropagation();
        event.preventDefault();

        let docId = document.getElementById('contextDoc').value;
        if (docId === 'Forge' || docId in CHROMA_COLLECTION_MAP) docId = '';
        const body  = document.getElementById('entity-preview-body');

        body.innerHTML = '<div class="preview-loading"><div class="preview-spinner"></div> Loading preview...</div>';
        $('#entity-preview-modal').css('display', 'flex');

        if (!docId) {
            body.innerHTML = '<div class="preview-not-found">No context document selected. Select a context doc to enable previews.</div>';
            return;
        }

        fetch(`narratives_api_v11.php?action=get_entity_preview&doc_id=${encodeURIComponent(docId)}&cat=${encodeURIComponent(cat)}&name=${encodeURIComponent(name)}`)
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
                <h3 style="margin:0; font-size:1.25rem; line-height:1.3;">${escHtml(data.name || name)}</h3>
                ${data.roles && data.roles.length ? `<div style="margin-top:6px; font-size:0.82rem; color:var(--text-muted);">${data.roles.map(r => `<span class="preview-pill">${escHtml(r)}</span>`).join(' ')}</div>` : ''}
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
                                    'visual', 'appearance', 'logline', 'description', 'act_structure'];
                const longPairs = attrs.filter(([k]) => longFields.includes(k));
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
                        html += `<div class="preview-kv-item">
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

        const enableKgGraph = document.getElementById('chkEnableKgGraph').checked;
        const enableSqlTags = document.getElementById('chkEnableSqlTags').checked;
        const enableChroma  = document.getElementById('chkEnableChroma').checked;

        currentCustomQuery = {
            text: freeText,
            items: items,
            enable_kg_graph: enableKgGraph,
            enable_sql_tags: enableSqlTags,
            enable_chroma:   enableChroma
        };

        $('#filter-modal').hide();
        const customEngine = enableKgGraph || enableSqlTags || !enableChroma;
        $('#activeFilterBadge').show().text(`Active: ${items.length} items + text` + (customEngine ? ' (V11 Hybrid Active)' : ''));
        forgeActiveParams = null; 
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
        let docId = document.getElementById('contextDoc').value;
        if (docId === 'Forge' || docId in CHROMA_COLLECTION_MAP) docId = '';
        const formData = new FormData();
        
        formData.append('action', 'save_sequence'); 
        formData.append('name', name); 
        formData.append('description', desc); 
        formData.append('sketch_ids', JSON.stringify(sequenceItems)); 
        formData.append('linked_doc_id', docId);
        
        if(currentSeqId) formData.append('sequence_id', currentSeqId);
        
        fetch('narratives_api_v11.php', { method: 'POST', body: formData }).then(r => r.json()).then(d => {
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
            sel.classList.remove('forge-active');
            sel.classList.remove('chroma-active');
            document.getElementById('chromaQueryBar').classList.remove('visible');
            currentChromaCollection = null;
            if(sel.value) { forgeActiveParams = null; currentPage = 1; loadLibraryPage(1); }
        }
        
        let rawIds = JSON.parse(seqData.sequence_data || '[]');
        if(rawIds && rawIds.length > 0) {
            fetch('narratives_api_v11.php?action=hydrate_sequence', {
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

    // =====================================================================
    // FORGE FILTER LOGIC
    // =====================================================================

    window.setForgeMode = function(mode) {
        forgeState.filterMode = mode;
        document.getElementById('forgeModeIntersection').classList.toggle('active', mode === 'intersection');
        document.getElementById('forgeModeUnion').classList.toggle('active', mode === 'union');
        document.getElementById('forgeModeLabel').textContent = mode === 'intersection'
            ? 'All filters must match'
            : 'Any filter may match';
    };

    window.forgeSearchDebounced = function(slot, q) {
        clearTimeout(forgeDebounceTimers[slot]);
        forgeDebounceTimers[slot] = setTimeout(() => forgeSearchSlot(slot, q), 280);
    };

    const FORGE_SLOTS = {
        fuzz:       { mode: 'fuzz',       dropdown: 'forgeFuzzDropdown',      clear: 'forgeFuzzClear' },
        doc:        { mode: 'doc',        dropdown: 'forgeDocDropdown',       clear: 'forgeDocClear' },
        doc_entity: { mode: 'doc_entity', dropdown: 'forgeDocEntityDropdown', clear: 'forgeDocEntityClear' },
        kg:         { mode: 'kg',         dropdown: 'forgeKgDropdown',        clear: 'forgeKgClear' },
        seq:        { mode: 'seq',        dropdown: 'forgeSeqDropdown',       clear: 'forgeSeqClear' },
        storyboard: { mode: 'storyboard', dropdown: 'forgeSbDropdown',        clear: 'forgeSbClear' },
    };

    window.forgeSearchSlot = function(slot, q) {
        const cfg = FORGE_SLOTS[slot];
        if (!cfg) return;

        const dd = document.getElementById(cfg.dropdown);
        const clearBtn = document.getElementById(cfg.clear);
        if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';

        dd.innerHTML = '<div class="forge-dropdown-loading">…</div>';
        dd.classList.add('open');

        let url = `filter_forge_api.php?action=list_filter_options&mode=${cfg.mode}&q=${encodeURIComponent(q)}`;
        if (slot === 'doc_entity') {
            if (!forgeState.doc.id) { dd.innerHTML = '<div class="forge-dropdown-loading">Select a doc first</div>'; return; }
            url += `&doc_id=${forgeState.doc.id}`;
        }
        url += `&entity_type=${document.getElementById('forgeEntityType').value}`;

        fetch(url)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data.length) {
                    dd.innerHTML = '<div class="forge-dropdown-loading">No results</div>';
                    return;
                }

                if (slot === 'doc_entity' && Array.isArray(res.data) && res.data[0] && res.data[0].section) {
                    let html = '';
                    res.data.forEach(sec => {
                        html += `<div style="padding:5px 12px 3px; font-size:0.68rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); border-top:1px solid var(--border);">${escHtml(sec.section)}</div>`;
                        sec.items.forEach(name => {
                            const alreadySel = forgeState.doc_entity.some(e => e.name === name);
                            html += `<div class="forge-dropdown-item ${alreadySel ? 'selected' : ''}"
                                data-entity-name="${escHtml(name)}">
                                <span>${escHtml(name)}</span>
                                ${alreadySel ? '<span style="color:#f59e0b; font-size:0.75rem;">✓</span>' : ''}
                            </div>`;
                        });
                    });
                    dd.innerHTML = html;
                    return;
                }

                let html = '';
                res.data.forEach(item => {
                    const safeItem = escHtml(JSON.stringify(item));
                    html += `<div class="forge-dropdown-item" data-slot="${escHtml(slot)}" data-item="${safeItem}">
                        <span>${escHtml(item.label)}</span>
                        <span class="forge-dropdown-item-meta">${escHtml(item.meta || '')}</span>
                    </div>`;
                });
                dd.innerHTML = html;
            })
            .catch(() => { dd.innerHTML = '<div class="forge-dropdown-loading">Error loading</div>'; });
    };

    window.forgeSelectItem = function(slot, item) {
        forgeState[slot] = { id: item.id, label: item.label };

        const chipsEl = document.getElementById('forge' + slot.charAt(0).toUpperCase() + slot.slice(1) + 'Chips');
        if (chipsEl) {
            chipsEl.innerHTML = `<div class="forge-chip">${escHtml(item.label)}<span class="forge-chip-x" onclick="forgeClearSlot('${slot}')">×</span></div>`;
        }

        const ddId = FORGE_SLOTS[slot] ? FORGE_SLOTS[slot].dropdown : null;
        if (ddId) document.getElementById(ddId).classList.remove('open');

        if (slot === 'doc') {
            document.getElementById('forgeDocEntityWrap').style.display = 'block';
        }
    };

    window.forgeSelectDocEntity = function(name) {
        if (!forgeState.doc_entity.some(e => e.name === name)) {
            forgeState.doc_entity.push({ name });
        }
        renderForgeDocEntityChips();
        const inp = document.getElementById('forgeDocEntitySearch');
        if (inp) forgeSearchSlot('doc_entity', inp.value);
    };

    function renderForgeDocEntityChips() {
        const chipsEl = document.getElementById('forgeDocEntityChips');
        if (!chipsEl) return;
        chipsEl.innerHTML = forgeState.doc_entity.map(e =>
            `<div class="forge-chip">${escHtml(e.name)}<span class="forge-chip-x" onclick="forgeRemoveDocEntity('${e.name.replace(/'/g,"\\'")}')">×</span></div>`
        ).join('');
    }

    window.forgeRemoveDocEntity = function(name) {
        forgeState.doc_entity = forgeState.doc_entity.filter(e => e.name !== name);
        renderForgeDocEntityChips();
    };

    window.forgeClearSlot = function(slot) {
        forgeState[slot] = { id: null, label: null };
        const chipsEl = document.getElementById('forge' + slot.charAt(0).toUpperCase() + slot.slice(1) + 'Chips');
        if (chipsEl) chipsEl.innerHTML = '';
        if (slot === 'doc') {
            forgeState.doc_entity = [];
            document.getElementById('forgeDocEntityWrap').style.display = 'none';
            document.getElementById('forgeDocEntityChips').innerHTML = '';
        }
    };

    window.forgeClearSearch = function(slot) {
        const inputMap = {
            fuzz: 'forgeFuzzSearch', doc: 'forgeDocSearch',
            doc_entity: 'forgeDocEntitySearch', kg: 'forgeKgSearch',
            seq: 'forgeSeqSearch', storyboard: 'forgeSbSearch',
        };
        const inp = document.getElementById(inputMap[slot]);
        if (inp) inp.value = '';
        const cfg = FORGE_SLOTS[slot];
        if (cfg) {
            const dd = document.getElementById(cfg.dropdown);
            if (dd) dd.classList.remove('open');
            const cl = document.getElementById(cfg.clear);
            if (cl) cl.style.display = 'none';
        }
    };

    function resetForgeStateOnly() {
        forgeState = {
            fuzz: { id: null, label: null },
            doc:  { id: null, label: null },
            doc_entity: [],
            kg:   { id: null, label: null },
            seq:  { id: null, label: null },
            storyboard: { id: null, label: null },
            vectorText: '',
            textSearch: '',
            filterMode: 'intersection',
            entityType: 'sketches',
        };
        ['forgeFuzzChips','forgeDocChips','forgeDocEntityChips','forgeKgChips','forgeSeqChips','forgeSbChips'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '';
        });
        ['forgeFuzzSearch','forgeDocSearch','forgeDocEntitySearch','forgeKgSearch','forgeSeqSearch','forgeSbSearch'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('forgeDocEntityWrap').style.display = 'none';
        document.querySelectorAll('.forge-dropdown').forEach(dd => dd.classList.remove('open'));
        setForgeMode('intersection');
        document.getElementById('forgeEntityType').value = 'sketches';
        
        
        
        document.getElementById('forgeVectorText').value = '';
        document.getElementById('forgeTextSearch').value = '';
        document.getElementById('forgeSketchIdSearch').value = '';
        document.getElementById('forgeFrameIdSearch').value = '';
        document.getElementById('forgeResultGrid').innerHTML = '<div class="forge-result-empty">Configure filters above and tap Preview.</div>';
        
        
        
        document.getElementById('forgePagRow').style.display = 'none';
        document.getElementById('forgePreviewMeta').textContent = '';
    }

    window.resetForgeFilter = function() {
        forgeActiveParams = null;
        resetForgeStateOnly();
        if (!currentCustomQuery) {
            $('#activeFilterBadge').hide();
        }
    };

    window.runForgePreview = function(page) {
        page = Math.max(1, page || 1);
        forgePreviewPage = page;

        const params = buildForgeParams();
        params.set('action', 'list_frames');
        params.set('page', page);
        params.set('per_page', '24');

        const grid = document.getElementById('forgeResultGrid');
        const pagRow = document.getElementById('forgePagRow');
        grid.innerHTML = '<div class="forge-result-loading"><div class="preview-spinner"></div> Loading…</div>';
        pagRow.style.display = 'none';

        fetch('filter_forge_api.php?' + params.toString())
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') {
                    grid.innerHTML = `<div class="forge-result-empty">Error: ${escHtml(res.message || 'Unknown')}</div>`;
                    return;
                }

                forgePreviewTotal = res.meta.total;
                forgePreviewPages = res.meta.pages;

                document.getElementById('forgePreviewMeta').textContent =
                    `${res.meta.total} result${res.meta.total !== 1 ? 's' : ''}`;

                if (!res.data.length) {
                    grid.innerHTML = '<div class="forge-result-empty">No results for these filters.</div>';
                    return;
                }

                grid.innerHTML = res.data.map(row =>
                    `<div class="forge-result-card">
                        <img src="${escHtml(row.filename)}" loading="lazy" onerror="this.style.background='#222';this.style.height='60px'">
                        <div class="forge-result-card-label">${escHtml(row.entity_name || row.frame_name || '')}</div>
                        <div class="forge-result-card-id">#${row.entity_id ?? row.frame_id}</div>
                    </div>`
                ).join('');

                if (forgePreviewPages > 1) {
                    pagRow.style.display = 'flex';
                    document.getElementById('forgePagLabel').textContent = `Page ${page} of ${forgePreviewPages}`;
                    document.getElementById('forgePagPrev').disabled = page <= 1;
                    document.getElementById('forgePagNext').disabled = page >= forgePreviewPages;
                }
            })
            .catch(err => {
                grid.innerHTML = `<div class="forge-result-empty">Fetch error: ${escHtml(err.message)}</div>`;
            });
    };

    function buildForgeParams() {
        const p = new URLSearchParams();
        p.set('entity_type', 'sketches');
        p.set('filter_mode', forgeState.filterMode);

        if (forgeState.fuzz.id)       p.set('fuzz_id',      forgeState.fuzz.id);
        if (forgeState.doc.id)        p.set('doc_id',       forgeState.doc.id);
        if (forgeState.kg.id)         p.set('kg_node_id',   forgeState.kg.id);
        if (forgeState.seq.id)        p.set('seq_id',       forgeState.seq.id);
        if (forgeState.storyboard.id) p.set('storyboard_id', forgeState.storyboard.id);

        if (forgeState.doc_entity.length === 1) {
            p.set('doc_entity_name', forgeState.doc_entity[0].name);
        } else if (forgeState.doc_entity.length > 1) {
            forgeState.doc_entity.forEach(e => p.append('doc_entity_names[]', e.name));
        }

        const vectorText = document.getElementById('forgeVectorText').value.trim();
        if (vectorText) p.set('vector_text', vectorText);

        const textSearch = document.getElementById('forgeTextSearch').value.trim();
        if (textSearch) p.set('search', textSearch);

        const sketchId = document.getElementById('forgeSketchIdSearch').value.trim();
        if (sketchId) p.set('entity_id', sketchId);

        const frameId = document.getElementById('forgeFrameIdSearch').value.trim();
        if (frameId) p.set('frame_id', frameId);

        return p;
    }

    window.applyForgeFilter = function() {
        const params = buildForgeParams();

                const hasFilter = forgeState.fuzz.id || forgeState.doc.id || forgeState.kg.id ||
                          forgeState.seq.id || forgeState.storyboard.id ||
                          forgeState.doc_entity.length > 0 ||
                          document.getElementById('forgeVectorText').value.trim() ||
                          document.getElementById('forgeTextSearch').value.trim() ||
                          document.getElementById('forgeSketchIdSearch').value.trim() ||
                          document.getElementById('forgeFrameIdSearch').value.trim();         
                          

        if (!hasFilter) {
            Toast.show('Set at least one filter', 'warn');
            return;
        }

        forgeActiveParams = params;
        currentCustomQuery = null;

        $('#forge-filter-modal').hide();

        const summary = [];
        if (forgeState.fuzz.id)       summary.push('Fuzz: ' + forgeState.fuzz.label);
        if (forgeState.doc.id)        summary.push('Doc: ' + forgeState.doc.label);
        if (forgeState.kg.id)         summary.push('KG: ' + forgeState.kg.label);
        if (forgeState.seq.id)        summary.push('Seq: ' + forgeState.seq.label);
        if (forgeState.storyboard.id) summary.push('SB: ' + forgeState.storyboard.label);
        if (forgeState.doc_entity.length) summary.push(`Entities: ${forgeState.doc_entity.length}`);
        
        $('#activeFilterBadge').show().text('Forge: ' + (summary.length ? summary.join(' · ') : 'active'));

        currentPage = 1;
        loadForgeLibraryPage(1);
    };

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.forge-search-wrap') && !e.target.closest('.forge-dropdown')) {
            document.querySelectorAll('.forge-dropdown.open').forEach(dd => dd.classList.remove('open'));
        }
    });
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
