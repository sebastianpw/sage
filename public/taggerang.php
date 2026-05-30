<?php
// public/taggerang.php
// Showrunner — Taggerang 🪃
// "Hyper-tagging like bangerang" — tag frames casually while scrolling
// Inspired by the Narrative Sequencer UI (narratives.php)
// Architecture: filter pot becomes tag definition, gallery + drag into pot = tag assignment
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = 'Taggerang 🪃';

// Context Docs for Dropdown (same as narratives.php)
$docsRaw = $pdo->query("SELECT d.id, d.name FROM documentations d JOIN md_doc_analysis da ON d.id = da.doc_id WHERE da.narrative_utility IS NOT NULL ORDER BY da.narrative_utility DESC")->fetchAll(PDO::FETCH_ASSOC);
$contextDocs = $docsRaw;

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
    :root {
        --film-bg: #030303;
        --highlight: #f59e0b;       /* amber — distinct from narratives green */
        --highlight2: #10b981;      /* green accent for save actions */
        --accent-glow: rgba(245, 158, 11, 0.35);
        --tag-color: #f59e0b;
    }
    html, body { overflow: hidden; height: 100%; margin: 0; }
    .sequencer-layout { display: flex; flex-direction: column; height: 100vh; width: 100vw; background: var(--bg); overflow: hidden; }

    /* ── TOP: Tag Pot Area (replaces timeline) ── */
    .timeline-area {
        flex: 0 0 30%;
        background: var(--film-bg);
        border-bottom: 4px solid var(--border);
        display: flex;
        flex-direction: column;
        position: relative;
        box-shadow: 0 5px 20px rgba(0,0,0,0.5);
        z-index: 10;
    }
    .timeline-header {
        padding: 10px 15px;
        background: rgba(255,255,255,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        height: 50px;
        flex-shrink: 0;
    }
    .timeline-track-container {
        flex: 1;
        overflow-x: auto;
        overflow-y: hidden;
        display: flex;
        align-items: center;
        padding: 0 15px;
        background-image:
            linear-gradient(90deg, transparent 50%, rgba(255,255,255,0.03) 50%),
            linear-gradient(transparent 50%, rgba(0,0,0,0.5) 50%);
        background-size: 20px 100%, 100% 4px;
    }
    .film-strip-list {
        display: flex;
        gap: 8px;
        height: 100%;
        align-items: center;
        min-width: 100%;
        padding: 10px 0;
    }

    /* ── Pot "empty" hint ── */
    .pot-empty-hint {
        color: rgba(245,158,11,0.3);
        font-size: 0.9rem;
        padding: 0 20px;
        pointer-events: none;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pot-empty-hint .arrow { font-size: 1.5rem; animation: bounce 1.5s infinite; }
    @keyframes bounce { 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-4px); } }

    .seq-title-display { font-weight: 700; color: var(--highlight); font-size: 1rem; letter-spacing: 0.5px; }

    /* ── Frame tile (in pot) ── */
    .film-frame {
        height: 110px;
        aspect-ratio: 16/9;
        background: #000;
        border: 2px solid var(--highlight);
        border-radius: 6px;
        flex-shrink: 0;
        position: relative;
        cursor: grab;
        overflow: hidden;
        box-shadow: 0 0 12px var(--accent-glow);
        transition: transform 0.2s;
    }
    .film-frame:active { cursor: grabbing; transform: scale(0.95); }
    .film-frame img { width: 100%; height: 100%; object-fit: cover; }
    .remove-frame {
        position: absolute; top: 4px; right: 4px;
        background: rgba(220,38,38,0.9); color: white;
        border: none; border-radius: 50%; width: 20px; height: 20px;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 12px; z-index: 10;
        box-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }
    .frame-label {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.85));
        color: #fff; font-size: 9px; padding: 4px 5px 3px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        pointer-events: none;
    }

    /* ── Middle: Controls ── */
    .control-strip {
        padding: 10px;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        display: flex;
        gap: 10px;
        align-items: center;
        flex-shrink: 0;
        height: 60px;
    }
    .context-select {
        padding: 8px; border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg); color: var(--text);
        flex: 1; max-width: 300px; font-weight: 700;
        border-left: 4px solid var(--highlight);
    }
    .btn-icon {
        padding: 8px 12px; border-radius: 6px; cursor: pointer;
        border: 1px solid var(--border); background: var(--bg);
        display: flex; align-items: center; gap: 6px;
        white-space: nowrap; font-size: 0.9rem; color: var(--text);
        transition: background 0.2s;
    }
    .btn-icon:hover { background: var(--card); }
    .filter-btn { background: var(--highlight); color: #030303; border: none; font-weight: 700; }
    .filter-btn:hover { filter: brightness(1.1); }
    .save-btn { background: var(--highlight2); color: #fff; border: none; font-weight: 700; }
    .save-btn:hover { background: #059669; }

    /* Tag badge next to title */
    .tag-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(245,158,11,0.15);
        border: 1px solid rgba(245,158,11,0.4);
        color: var(--highlight);
        border-radius: 20px;
        padding: 3px 10px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s;
    }
    .tag-badge:hover { background: rgba(245,158,11,0.3); }
    .tag-badge .remove-tag { color: #ef4444; font-weight: 900; margin-left: 4px; }

    /* ── Bottom: Library ── */
    .library-area {
        flex: 1; background: var(--bg);
        overflow: hidden; position: relative;
        display: flex; flex-direction: column; min-height: 0;
    }
    .pagination-bar {
        flex: 0 0 50px; background: var(--card);
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        gap: 15px; padding: 0 20px; z-index: 30;
    }
    .p-btn {
        background: transparent; border: 1px solid var(--border);
        color: var(--text); width: 32px; height: 32px; border-radius: 50%;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; transition: all 0.2s ease;
    }
    .p-btn:hover:not(:disabled) { background: var(--highlight); border-color: var(--highlight); color: #030303; }
    .p-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .p-input-wrapper {
        display: flex; align-items: center; gap: 8px; font-size: 0.9rem;
        color: var(--text-muted); background: var(--bg);
        padding: 4px 12px; border-radius: 20px; border: 1px solid var(--border);
    }
    .p-input {
        width: 40px; background: transparent; border: none;
        border-bottom: 1px solid var(--text-muted); color: var(--highlight);
        font-weight: 700; text-align: center; font-size: 1rem; padding: 2px 0;
    }
    .p-input:focus { outline: none; border-bottom-color: var(--highlight); }

    .lib-swiper { width: 100%; flex: 1; min-height: 0; padding: 20px 0; display: block; opacity: 0; transition: opacity 0.3s; }
    .swiper-slide { width: 280px; height: auto; display: flex; flex-direction: column; justify-content: center; transition: transform 0.3s; align-self: flex-start; }

    .loading-state {
        position: absolute; top: 50px; bottom: 0; left: 0; right: 0;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        z-index: 50; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);
    }
    .spinner {
        width: 40px; height: 40px;
        border: 4px solid rgba(255,255,255,0.1);
        border-top-color: var(--highlight);
        border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }

    /* ── Library Card ── */
    .lib-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 10px; overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: flex; flex-direction: column; position: relative; height: auto;
    }
    .lib-card.match { border-color: var(--highlight); box-shadow: 0 0 15px var(--accent-glow); transform: translateY(-2px); }
    /* Cards that already have any pot-tag applied get a subtle tint */
    .lib-card.tagged { border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.04); }

    .lib-thumb { width: 100%; aspect-ratio: 16/9; background: #000; position: relative; flex-shrink: 0; cursor: zoom-in; }
    .lib-thumb img { width: 100%; height: 100%; object-fit: cover; transition: opacity 0.2s; }

    /* Frame navigation */
    .frame-nav-btn {
        position: absolute; bottom: 0; width: 40px; height: 40px;
        background: rgba(0,0,0,0.6); color: white;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; z-index: 25; font-size: 1.2rem; transition: background 0.2s; opacity: 0;
    }
    .lib-thumb:hover .frame-nav-btn { opacity: 1; }
    .frame-nav-btn:hover { background: var(--highlight); color: #030303; }
    .frame-nav-left { left: 0; border-top-right-radius: 8px; }
    .frame-nav-right { right: 0; border-top-left-radius: 8px; }

    .drag-handle {
        position: absolute; top: 8px; right: 8px; width: 32px; height: 32px;
        border-radius: 8px; background: rgba(0,0,0,0.3); backdrop-filter: blur(4px);
        color: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; cursor: grab; border: 1px solid rgba(255,255,255,0.1);
        z-index: 20; transition: all 0.2s;
    }
    .drag-handle:hover { background: var(--highlight); color: #030303; border-color: transparent; }
    .drag-handle:active { cursor: grabbing; transform: scale(0.95); }

    .lib-meta { padding: 10px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .lib-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); }
    .lib-id { font-size: 0.7rem; color: var(--text-muted); font-family: monospace; margin-bottom: 0; }
    .match-reason { font-size: 0.7rem; color: var(--highlight); margin-top: 4px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace; }

    /* Mini tag chips shown on each card */
    .card-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; min-height: 20px; }
    .card-tag-chip {
        font-size: 0.65rem; padding: 2px 7px;
        border-radius: 12px; background: rgba(245,158,11,0.15);
        border: 1px solid rgba(245,158,11,0.35); color: var(--highlight);
        font-weight: 700; cursor: default;
    }

    .lib-actions { display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid var(--border); gap: 8px; margin-top: 8px; }
    .action-btn {
        flex: 1; font-size: 1rem; padding: 6px 0; border-radius: 6px;
        border: 1px solid var(--border); cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 6px;
        background: var(--bg); color: var(--text); transition: background 0.2s;
    }
    .action-btn:hover { background: var(--accent-subtle); }
    .action-btn.amber { border-color: rgba(245,158,11,0.3); color: #f59e0b; background: rgba(245,158,11,0.05); }

    /* ── FILTER MODAL ── */
    .filter-modal-body { display: flex; flex-direction: column; height: 80vh; }
    .filter-input-area { margin-bottom: 15px; }
    .filter-columns { display: flex; gap: 15px; flex: 1; min-height: 0; }
    .filter-col { flex: 1; border: 1px solid var(--border); border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; background: rgba(0,0,0,0.1); }
    .filter-col-head { padding: 10px; background: var(--card); font-weight: 700; border-bottom: 1px solid var(--border); text-align: center; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); }

    /* Two checkboxes that control filter role */
    .filter-mode-row { padding: 8px 12px; background: rgba(245,158,11,0.05); border-bottom: 1px solid var(--border); display: flex; gap: 18px; align-items: center; font-size: 0.82rem; }
    .filter-mode-row label { display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-muted); }
    .filter-mode-row input[type=checkbox] { accent-color: var(--highlight); width: 14px; height: 14px; }
    .filter-mode-row label.active-mode { color: var(--highlight); font-weight: 700; }

    .filter-list { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 6px; }

    .filter-item {
        padding: 8px 12px; background: var(--card); border: 1px solid var(--border);
        border-radius: 6px; font-size: 0.9rem; transition: 0.1s;
        display: flex; justify-content: space-between; align-items: center;
        cursor: default; position: relative;
    }
    .filter-col:first-child .filter-item { cursor: pointer; }
    .filter-col:first-child .filter-item:hover { border-color: var(--highlight); transform: translateX(2px); }
    .filter-col:first-child .filter-item.active { background: var(--highlight); color: #030303; border-color: var(--highlight); }

    .filter-drag-handle {
        padding: 2px 8px; color: var(--text-muted); cursor: grab;
        background: rgba(0,0,0,0.05); border-radius: 4px; font-family: monospace;
        margin-left: 8px; border: 1px solid rgba(0,0,0,0.1);
    }
    .filter-drag-handle:hover { color: var(--highlight); background: rgba(0,0,0,0.1); border-color: var(--highlight); }

    .pot-item { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.4); color: var(--highlight); cursor: default; }
    .pot-item .remove-x { font-weight: bold; cursor: pointer; padding: 0 5px; color: #ef4444; margin-left: 8px; }

    /* Tag-as-pot highlight (3rd column when tag mode is ON) */
    .filter-col.tag-pot-mode { border-color: var(--highlight); box-shadow: 0 0 12px var(--accent-glow); }
    .filter-col.tag-pot-mode .filter-col-head { color: var(--highlight); background: rgba(245,158,11,0.08); }

    /* ── TAG DEFINITION MODAL ── */
    .tag-def-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; }
    .tag-def-row .tag-name-input { flex: 1; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--card); color: var(--text); font-weight: 700; font-size: 0.9rem; }
    .tag-def-row .tag-name-input:focus { outline: none; border-color: var(--highlight); }
    .tag-def-row .tag-del-btn { background: transparent; border: none; color: #ef4444; font-size: 1.4rem; cursor: pointer; padding: 0 6px; }
    .add-tag-btn { width: 100%; padding: 10px; background: transparent; border: 1px dashed var(--highlight); color: var(--highlight); border-radius: 6px; cursor: pointer; font-weight: 700; margin-top: 4px; transition: background 0.2s; }
    .add-tag-btn:hover { background: rgba(245,158,11,0.1); }

    /* ── Player Modal ── */
    .player-modal { display: none; position: fixed; inset: 0; background: #000; z-index: 3000; }
    .player-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 2.5rem; z-index: 3005; cursor: pointer; opacity: 0.8; text-shadow: 0 2px 5px #000; }
    .player-swiper .swiper-slide { width: 100%; height: 100%; background: #000; display: flex; justify-content: center; align-items: center; position: relative; }
    .player-img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .player-controls { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); display: flex; gap: 15px; z-index: 3002; }
    .player-btn { background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 10px 20px; border-radius: 30px; cursor: pointer; backdrop-filter: blur(6px); font-weight: 700; display: flex; gap: 8px; align-items: center; transition: all 0.2s; font-size: 0.9rem; }
    .player-btn:hover { background: rgba(255,255,255,0.1); transform: scale(1.05); border-color: #fff; }
    .player-btn.amber { border-color: rgba(245,158,11,0.6); color: #fcd34d; background: rgba(120,53,15,0.6); }
    .swiper-button-next, .swiper-button-prev { color: var(--highlight); text-shadow: 0 2px 4px #000; }

    /* ── Generic Modals ── */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-content { background: var(--card); width: 90%; max-width: 600px; max-height: 85vh; overflow-y: auto; padding: 25px; border-radius: 12px; position: relative; border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
    .modal-content.wide { max-width: 1000px; }
    .modal-close { position: absolute; top: 15px; right: 15px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); line-height: 1; }
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; color: var(--text-muted); text-transform: uppercase; }
    .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); background: var(--bg); color: var(--text); border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
    .form-input:focus { outline: none; border-color: var(--highlight); }
    .form-textarea { height: 80px; resize: vertical; font-family: monospace; }
    .form-btn { padding: 12px 20px; background: var(--highlight); color: #030303; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 1rem; }
    .form-btn:hover { filter: brightness(1.1); }
    .form-btn.green { background: var(--highlight2); color: #fff; }
    .form-btn.green:hover { background: #059669; }

    /* ── Iframe Modal ── */
    #iframe-modal .modal-content { padding: 8px 8px 0 8px; border: none; box-shadow: none; max-width: 1200px; width: 95vw; height: 88vh; border-radius: 8px; background: var(--card); display: flex; flex-direction: column; }
    #iframe-modal .modal-content h3 { margin: 0; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 1rem; flex-shrink: 0; }
    #iframe-modal .modal-close { top: 8px; right: 8px; }
    #iframe-modal iframe#entity-iframe { flex: 1; width: calc(100% / 0.8); height: calc(100% / 0.8); border: none; border-radius: 0; margin: 0; padding: 0; transform: scale(0.8); transform-origin: top left; background: var(--bg); display: block; }

    /* ── Pot count badge ── */
    #potCountBadge { display: none; background: var(--highlight); color: #030303; border-radius: 20px; padding: 2px 10px; font-size: 0.78rem; font-weight: 900; margin-left: 8px; }

    /* ── Tag selector chips (in pot header area) ── */
    .active-tag-area {
        padding: 4px 15px; background: rgba(245,158,11,0.06);
        border-bottom: 1px solid rgba(245,158,11,0.2);
        display: flex; align-items: center; gap: 8px;
        flex-shrink: 0; min-height: 34px; overflow: hidden;
        transition: max-height 0.25s ease;
        max-height: 34px; /* collapsed by default */
    }
    .active-tag-area.expanded { max-height: 200px; flex-wrap: wrap; align-items: flex-start; padding: 8px 15px; }
    .active-tag-area .label {
        font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;
        font-weight: 700; white-space: nowrap; flex-shrink: 0;
    }
    /* Toggle button to open/close the tag picker */
    .tag-picker-toggle {
        padding: 2px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 700;
        cursor: pointer; border: 1px dashed rgba(245,158,11,0.5);
        background: transparent; color: var(--highlight); white-space: nowrap;
        transition: all 0.15s; flex-shrink: 0;
    }
    .tag-picker-toggle:hover { background: rgba(245,158,11,0.15); border-style: solid; }
    .atag-chip {
        padding: 3px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700;
        cursor: pointer; border: 2px solid transparent;
        background: rgba(245,158,11,0.12); color: var(--highlight);
        transition: all 0.15s; display: none; /* hidden when collapsed */
    }
    .active-tag-area.expanded .atag-chip { display: inline-block; }
    .atag-chip:hover { background: rgba(245,158,11,0.25); }
    .atag-chip.selected { border-color: var(--highlight); background: rgba(245,158,11,0.25); box-shadow: 0 0 8px var(--accent-glow); }
    .atag-chip.no-tags { color: var(--text-muted); font-style: italic; cursor: default; background: transparent; }
</style>

<div class="sequencer-layout">

    <!-- ═══════════════════════════════════════════════════
         1. POT AREA (Top — replaces timeline)
    ════════════════════════════════════════════════════ -->
    <div class="timeline-area">
        <div class="timeline-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="seq-title-display">🪃 Taggerang</span>
                <span id="activePotTagLabel" class="tag-badge" style="display:none;">
                    <span id="activePotTagName">—</span>
                    <span class="remove-tag" onclick="clearActivePotTag()" title="Clear tag">✕</span>
                </span>
                <span id="potCountBadge">0 frames</span>
            </div>
            <div style="display:flex; gap:8px;">
                <!-- Play pot as slideshow -->
                <button class="btn-icon" onclick="playPot()" title="Play pot as slideshow">&#9654; Play</button>
                <!-- Manual tag input modal trigger -->
                <button class="btn-icon" onclick="openManualTagModal()" title="Add tags to all pot frames manually">🏷️ Tag All</button>
                <!-- Save tags button -->
                <button class="btn-icon save-btn" onclick="persistTags()" title="Save tags for all pot frames">💾 Save Tags</button>
            </div>
        </div>

        <!-- Active tag selector (which tag the pot is assigned to) -->
        <div class="active-tag-area" id="activeTagArea">
            <span class="label">Active Tag:</span>
            <span id="activeTagToggle" class="tag-picker-toggle" onclick="toggleTagPicker()">&#9660; Pick tag</span>
        </div>

        <!-- The drag-target strip -->
        <div class="timeline-track-container">
            <div class="film-strip-list" id="potSortable">
                <div class="pot-empty-hint" id="emptyMsg">
                    <span class="arrow">↓</span>
                    Drag frames from the gallery below into here to tag them
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         2. CONTROL STRIP
    ════════════════════════════════════════════════════ -->
    <div class="control-strip">
        <select id="contextDoc" class="context-select">
            <option value="">-- No Context --</option>
            <?php foreach($contextDocs as $d): ?>
                <option value="<?= htmlspecialchars($d['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn-icon filter-btn" onclick="openFilterModal()">🧠 Advanced Filter</button>
        <div id="activeFilterBadge" style="display:none; font-size:0.8rem; color:var(--highlight); margin-left:5px;"></div>
        <button class="btn-icon" onclick="openTagDefModal()" title="Manage tag definitions" style="border-color:rgba(245,158,11,0.4); color:var(--highlight);">⚙️ Tags</button>
    </div>

    <!-- ═══════════════════════════════════════════════════
         3. LIBRARY AREA (Bottom)
    ════════════════════════════════════════════════════ -->
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

        <div id="loadingState" class="loading-state">
            <div class="spinner"></div>
            <div style="font-size:0.9rem; color:var(--text-muted);">Loading Library...</div>
        </div>

        <div class="swiper lib-swiper" id="mainSwiper">
            <div class="swiper-wrapper" id="libWrapper"></div>
            <div class="swiper-scrollbar"></div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     FILTER MODAL  (same cols as narratives + 2 checkboxes)
════════════════════════════════════════════════════════════ -->
<div id="filter-modal" class="modal-overlay">
    <div class="modal-content wide">
        <span class="modal-close" onclick="$('#filter-modal').hide()">&times;</span>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="margin:0;">🧠 Advanced Filter</h3>
            <button class="form-btn" onclick="applyAdvancedFilter()">APPLY FILTER</button>
        </div>

        <div class="filter-modal-body">
            <div class="filter-input-area">
                <textarea id="filterFreeText" class="form-input form-textarea" placeholder="Free-text director instruction (e.g. 'Dark mood, neon rain')..."></textarea>
            </div>
            <div class="filter-columns">
                <!-- Col 1: Categories -->
                <div class="filter-col">
                    <div class="filter-col-head">1. Categories</div>
                    <div id="filterCats" class="filter-list"></div>
                </div>
                <!-- Col 2: Available Items -->
                <div class="filter-col">
                    <div class="filter-col-head">2. Available Items</div>
                    <div id="filterItems" class="filter-list"></div>
                </div>
                <!-- Col 3: Filter Pot — dual mode via checkboxes -->
                <div class="filter-col" id="filterPotCol">
                    <div class="filter-col-head" id="filterPotColHead">3. Filter Pot</div>
                    <!-- Mode switches live here -->
                    <div class="filter-mode-row">
                        <label id="labelModeFilter">
                            <input type="checkbox" id="chkModeFilter" checked onchange="onFilterModeChange(this)">
                            Apply to gallery filter
                        </label>
                        <label id="labelModeTag">
                            <input type="checkbox" id="chkModeTag" onchange="onTagModeChange(this)">
                            Define as Tag(s) for Pot
                        </label>
                    </div>
                    <div id="filterPot" class="filter-list"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAG DEFINITION MODAL  (create/edit named tags)
════════════════════════════════════════════════════════════ -->
<div id="tag-def-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:520px;">
        <span class="modal-close" onclick="$('#tag-def-modal').hide()">&times;</span>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="margin:0;">&#9881; Manage Tags</h3>
            <button class="btn-icon" onclick="clearTagsFromUI()" title="Hide all from UI — nothing deleted from database" style="font-size:0.8rem; padding:5px 10px; color:#ef4444; border-color:rgba(239,68,68,0.3);">&#10005; Clear all from UI</button>
        </div>
        <p style="font-size:0.82rem; color:var(--text-muted); margin-top:0; margin-bottom:12px;">
            &#10005; hides a tag from UI only &mdash; never deletes from database.<br>
            Re-add a tag by typing its name below &mdash; existing tags are surfaced, not duplicated.
        </p>
        <div id="tagDefList"></div>
        <button class="add-tag-btn" onclick="addTagDefRow()">+ Add Tag</button>
        <div style="margin-top:15px; display:flex; gap:10px;">
            <button class="form-btn green" style="flex:1;" onclick="saveTagDefs()">&#10003; Save Tags</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MANUAL TAG MODAL  (bulk-assign tag to all pot frames)
════════════════════════════════════════════════════════════ -->
<div id="manual-tag-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:480px;">
        <span class="modal-close" onclick="$('#manual-tag-modal').hide()">&times;</span>
        <h3 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">🏷️ Tag All Pot Frames</h3>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0;">
            Select or type a tag to assign to every frame currently in the pot.
            Existing tags on those frames are preserved.
        </p>
        <div class="form-group">
            <label class="form-label">Tag</label>
            <select id="manualTagSelect" class="form-input">
                <option value="">-- Choose a tag --</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Or create new tag</label>
            <input type="text" id="manualTagNew" class="form-input" placeholder="e.g. Protagonist Close-Up">
        </div>
        <button class="form-btn" style="width:100%;" onclick="applyManualTag()">Apply Tag to All Pot Frames</button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MISC MODALS
════════════════════════════════════════════════════════════ -->
<div id="desc-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:500px;">
        <span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span>
        <h3 id="desc-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;"></h3>
        <div id="desc-body" style="font-family:serif; font-size:1.1rem; line-height:1.6; white-space:pre-wrap;"></div>
    </div>
</div>

<div id="iframe-modal" class="modal-overlay">
    <div class="modal-content wide">
        <span class="modal-close" onclick="$('#iframe-modal').hide(); document.getElementById('entity-iframe').src = '';">&times;</span>
        <h3 style="margin-top:0;">Edit Element</h3>
        <iframe id="entity-iframe" src=""></iframe>
    </div>
</div>

<div id="playerModal" class="player-modal">
    <div class="player-close" onclick="closePlayer()">✕</div>
    <div class="swiper player-swiper">
        <div class="swiper-wrapper" id="playerSlides"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════ -->
<script>
// ─── State ───────────────────────────────────────────────────
let currentLibraryPage   = [];
let sketchRegistry       = {};
let frameRegistry        = {};
let libSwiper            = null;
let playerSwiper         = null;
let photoSwipeLightbox   = null;
let currentPage          = 1;
let totalPages           = 1;
let currentCustomQuery   = null;

// Tagging state
let tagDefinitions       = [];  // [{id, name}] — loaded from DB / defined by user
let activePotTagId       = null; // which tag the pot is "wired" to right now
let potFrameTags         = {};   // map: frame_id => Set of tag_ids assigned this session

// Filter-mode switches
let filterModePot        = true;  // chkModeFilter — pot drives gallery filter
let tagModePot           = false; // chkModeTag    — pot defines tag(s) for assignment

// ─── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    if (typeof PhotoSwipeLightbox !== 'undefined') {
        photoSwipeLightbox = new PhotoSwipeLightbox({
            gallery: '#libWrapper', children: 'a.pswp-link',
            pswpModule: PhotoSwipe
        });
        photoSwipeLightbox.init();
    }

    // Pot Sortable (drop zone)
    new Sortable(document.getElementById('potSortable'), {
        group: 'shared', animation: 150, direction: 'horizontal',
        onAdd: function(evt) {
            const item = evt.item;
            const d    = item.dataset;
            const id   = d.id || item.getAttribute('data-id');
            const frameId = d.activeFrameId || item.getAttribute('data-active-frame-id');

            setTimeout(() => {
                item.className = 'film-frame';
                item.dataset.id = id;
                item.dataset.name = d.name;
                if (frameId) item.dataset.frameId = frameId;

                const imgSrc = item.querySelector('img') ? item.querySelector('img').src : '';
                item.innerHTML = `
                    <img src="${imgSrc}">
                    <div class="remove-frame" onclick="removePotFrame(this)">✕</div>
                    <div class="frame-label">${d.name || ''}</div>`;

                item.style.width = ''; item.style.transform = '';
                document.getElementById('emptyMsg').style.display = 'none';
                updatePotCount();

                // Auto-assign activePotTag if one is selected
                if (activePotTagId !== null && frameId) {
                    assignFrameTag(frameId, activePotTagId);
                    markCardTagged(id);
                }
            }, 10);
        },
        onRemove: updatePotCount,
        onUpdate:  updatePotCount
    });

    document.getElementById('contextDoc').addEventListener('change', () => {
        currentCustomQuery = null;
        $('#activeFilterBadge').hide();
        currentPage = 1;
        loadLibraryPage(1);
    });

    loadTagsFromDB();
    loadLibraryPage(1);

    // Filter Sortables
    new Sortable(document.getElementById('filterItems'), {
        group: { name: 'filter', pull: 'clone', put: false },
        sort: false, handle: '.filter-drag-handle', animation: 150
    });
    new Sortable(document.getElementById('filterPot'), {
        group: 'filter', animation: 150,
        onAdd: function(evt) {
            const item = evt.item;
            const cat  = item.getAttribute('data-cat') || '';
            item.classList.add('pot-item');
            const textEl = item.querySelector('.filter-text');
            const text   = textEl ? textEl.innerText : item.textContent.replace('×', '').trim();
            if (cat) item.setAttribute('data-cat', cat);
            item.innerHTML = `<span class="filter-text">${text}</span><span class="remove-x" onclick="this.parentElement.remove()">×</span>`;
        }
    });

    document.getElementById('pageInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') jumpToPage(this.value);
    });
});

// ─── Remove frame from pot ────────────────────────────────────
window.removePotFrame = function(btn) {
    btn.parentElement.remove();
    updatePotCount();
};

function updatePotCount() {
    const frames = document.querySelectorAll('#potSortable .film-frame');
    const badge  = document.getElementById('potCountBadge');
    if (frames.length > 0) {
        badge.style.display = 'inline-block';
        badge.textContent   = frames.length + (frames.length === 1 ? ' frame' : ' frames');
    } else {
        badge.style.display = 'none';
        document.getElementById('emptyMsg').style.display = 'flex';
    }
}

// ─── Tags from DB ─────────────────────────────────────────────
function loadTagsFromDB() {
    fetch('taggerang_api.php?action=get_tags').then(r => r.json()).then(res => {
        if (res.status === 'success') {
            tagDefinitions = res.data || [];
            renderTagChips();
            populateManualTagSelect();
        }
    }).catch(() => {
        // DB may not have tags yet — start empty
        tagDefinitions = [];
        renderTagChips();
    });
}

function renderTagChips() {
    const area   = document.getElementById('activeTagArea');
    const toggle = document.getElementById('activeTagToggle');

    // Remove old chips only (keep label + toggle button)
    area.querySelectorAll('.atag-chip').forEach(c => c.remove());

    if (tagDefinitions.length === 0) {
        const hint = document.createElement('span');
        hint.className = 'atag-chip no-tags';
        hint.style.display = 'inline-block';
        hint.textContent = 'No tags yet - define via Tags or Filter';
        area.appendChild(hint);
        if (toggle) toggle.style.display = 'none';
        return;
    }

    // Update toggle label to show active tag name or generic prompt
    if (toggle) {
        const activeDef = tagDefinitions.find(t => t.id === activePotTagId);
        toggle.style.display = 'inline-block';
        toggle.textContent = activeDef ? String.fromCharCode(9660) + ' ' + activeDef.name : String.fromCharCode(9660) + ' Pick tag';
    }

    tagDefinitions.forEach(tag => {
        const chip = document.createElement('span');
        chip.className = 'atag-chip' + (activePotTagId === tag.id ? ' selected' : '');
        chip.textContent = tag.name;
        chip.dataset.tagId = tag.id;
        chip.addEventListener('click', () => {
            setActivePotTag(tag.id, tag.name);
            collapseTagPicker(); // auto-close after picking
        });
        area.appendChild(chip);
    });
}

window.toggleTagPicker = function() {
    const area = document.getElementById('activeTagArea');
    area.classList.toggle('expanded');
};

function collapseTagPicker() {
    document.getElementById('activeTagArea').classList.remove('expanded');
}


function setActivePotTag(tagId, tagName) {
    activePotTagId = tagId;
    document.getElementById('activePotTagName').textContent = tagName;
    document.getElementById('activePotTagLabel').style.display = 'inline-flex';
    renderTagChips();
}

window.clearActivePotTag = function() {
    activePotTagId = null;
    document.getElementById('activePotTagLabel').style.display = 'none';
    renderTagChips();
};

function populateManualTagSelect() {
    const sel = document.getElementById('manualTagSelect');
    sel.innerHTML = '<option value="">-- Choose a tag --</option>';
    tagDefinitions.forEach(t => {
        sel.innerHTML += `<option value="${t.id}">${t.name}</option>`;
    });
}

// ─── Assign frame→tag (local state) ──────────────────────────
function assignFrameTag(frameId, tagId) {
    if (!frameId || !tagId) return;
    if (!potFrameTags[frameId]) potFrameTags[frameId] = new Set();
    potFrameTags[frameId].add(tagId);
}

function markCardTagged(sketchId) {
    const card = document.getElementById(`card_${sketchId}`);
    if (card) card.classList.add('tagged');
    // Update chip display
    refreshCardTagChips(sketchId);
}

function refreshCardTagChips(sketchId) {
    const chipArea = document.querySelector(`#card_${sketchId} .card-tags`);
    if (!chipArea) return;
    chipArea.innerHTML = '';
    // Collect all frame tags for this sketch
    const sketch = sketchRegistry[sketchId];
    if (!sketch) return;
    const frames = frameRegistry[sketchId] || [];
    let tagIds = new Set();
    frames.forEach(f => {
        if (potFrameTags[f.id]) potFrameTags[f.id].forEach(t => tagIds.add(t));
    });
    tagIds.forEach(tid => {
        const tagDef = tagDefinitions.find(t => t.id === tid);
        if (tagDef) {
            chipArea.innerHTML += `<span class="card-tag-chip">${tagDef.name}</span>`;
        }
    });
}

// ─── Persist Tags to DB ───────────────────────────────────────
window.persistTags = function() {
    const potFrames = document.querySelectorAll('#potSortable .film-frame');
    if (potFrames.length === 0) { Toast.show("Pot is empty — drag frames first", "warn"); return; }
    if (activePotTagId === null) { Toast.show("No active tag selected — pick one above", "warn"); return; }

    const frameIds = [];
    potFrames.forEach(el => {
        const fid = el.dataset.frameId;
        if (fid) frameIds.push(parseInt(fid));
    });

    if (frameIds.length === 0) { Toast.show("Could not resolve frame IDs", "error"); return; }

    fetch('taggerang_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_frame_tags', tag_id: activePotTagId, frame_ids: frameIds })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            Toast.show(`✓ Tagged ${res.count} frame(s) as "${res.tag_name}"`, "success");
            // Update local card state
            frameIds.forEach(fid => {
                assignFrameTag(fid, activePotTagId);
            });
            potFrames.forEach(el => markCardTagged(el.dataset.id));
        } else {
            Toast.show(res.message || "Error saving tags", "error");
        }
    });
};

// ─── Tag Def Modal ────────────────────────────────────────────
window.openTagDefModal = function() {
    renderTagDefRows();
    $('#tag-def-modal').css('display', 'flex');
};

function renderTagDefRows() {
    const list = document.getElementById('tagDefList');
    list.innerHTML = '';
    if (tagDefinitions.length === 0) {
        list.innerHTML = '<div style="padding:10px; color:var(--text-muted); font-style:italic; font-size:0.85rem;">No tags loaded. Use Reload to fetch from database.</div>';
        return;
    }
    tagDefinitions.forEach((tag) => {
        const row = document.createElement('div');
        row.className = 'tag-def-row';
        row.dataset.id = tag.id || '';
        row.innerHTML =
            '<input type="text" class="tag-name-input" value="' + tag.name + '" placeholder="Tag name">' +
            '<button class="tag-del-btn" onclick="removeTagFromUI(' + tag.id + ')" title="Hide from UI (does not delete from database)">x</button>';
        list.appendChild(row);
    });
}

// Hide a tag from UI — sets visible=0 in DB, tag and its assignments are preserved
window.removeTagFromUI = function(tagId) {
    fetch('taggerang_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'hide_tag', tag_id: tagId })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            tagDefinitions = res.data; // fresh visible-only list
            if (activePotTagId === tagId) {
                activePotTagId = null;
                document.getElementById('activePotTagLabel').style.display = 'none';
            }
            renderTagDefRows();
            renderTagChips();
            populateManualTagSelect();
        } else {
            Toast.show(res.message || 'Error hiding tag', 'error');
        }
    });
};

// Hide ALL tags from UI — sets visible=0 for all in DB, nothing deleted
window.clearTagsFromUI = function() {
    fetch('taggerang_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'hide_all_tags' })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            tagDefinitions = [];
            activePotTagId = null;
            document.getElementById('activePotTagLabel').style.display = 'none';
            renderTagDefRows();
            renderTagChips();
            populateManualTagSelect();
            Toast.show("All tags hidden from UI");
        } else {
            Toast.show(res.message || 'Error', 'error');
        }
    });
};

window.addTagDefRow = function() {
    tagDefinitions.push({ id: null, name: '' });
    renderTagDefRows();
};

window.saveTagDefs = function() {
    const rows = document.querySelectorAll('#tagDefList .tag-def-row');
    const defs = [];
    rows.forEach(row => {
        const name = row.querySelector('.tag-name-input').value.trim();
        const id   = row.dataset.id || null;
        if (name) defs.push({ id: id ? parseInt(id) : null, name });
    });
    fetch('taggerang_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_tag_defs', tags: defs })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            tagDefinitions = res.data;
            renderTagChips();
            populateManualTagSelect();
            $('#tag-def-modal').hide();
            Toast.show("Tags saved");
        } else {
            Toast.show(res.message || "Error", "error");
        }
    });
};


// ─── Manual Tag Modal ─────────────────────────────────────────
window.openManualTagModal = function() {
    const potFrames = document.querySelectorAll('#potSortable .film-frame');
    if (potFrames.length === 0) { Toast.show("Pot is empty", "warn"); return; }
    populateManualTagSelect();
    $('#manual-tag-modal').css('display', 'flex');
};

window.applyManualTag = function() {
    const selVal  = document.getElementById('manualTagSelect').value;
    const newName = document.getElementById('manualTagNew').value.trim();

    if (!selVal && !newName) { Toast.show("Choose or type a tag", "warn"); return; }

    const doApply = (tagId, tagName) => {
        const potFrames = document.querySelectorAll('#potSortable .film-frame');
        const frameIds = [];
        potFrames.forEach(el => { if (el.dataset.frameId) frameIds.push(parseInt(el.dataset.frameId)); });

        fetch('taggerang_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_frame_tags', tag_id: tagId, frame_ids: frameIds })
        }).then(r => r.json()).then(res => {
            if (res.status === 'success') {
                Toast.show(`✓ Tagged ${res.count} frame(s) as "${tagName}"`);
                frameIds.forEach(fid => assignFrameTag(fid, tagId));
                potFrames.forEach(el => markCardTagged(el.dataset.id));
                $('#manual-tag-modal').hide();
            } else {
                Toast.show(res.message || "Error", "error");
            }
        });
    };

    if (newName) {
        // Create tag first, then apply
        fetch('taggerang_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_tag_defs', tags: [...tagDefinitions, { id: null, name: newName }] })
        }).then(r => r.json()).then(res => {
            if (res.status === 'success') {
                tagDefinitions = res.data;
                renderTagChips();
                populateManualTagSelect();
                const newTag = res.data.find(t => t.name === newName);
                if (newTag) doApply(newTag.id, newTag.name);
            }
        });
    } else {
        const tag = tagDefinitions.find(t => t.id == selVal);
        if (tag) doApply(tag.id, tag.name);
    }
};

// ─── Filter Mode Switches ─────────────────────────────────────
window.onFilterModeChange = function(chk) {
    filterModePot = chk.checked;
    document.getElementById('labelModeFilter').className = filterModePot ? 'active-mode' : '';
};

window.onTagModeChange = function(chk) {
    tagModePot = chk.checked;
    const col  = document.getElementById('filterPotCol');
    const head = document.getElementById('filterPotColHead');
    if (tagModePot) {
        col.classList.add('tag-pot-mode');
        head.textContent = '3. Tag Definition Pot';
    } else {
        col.classList.remove('tag-pot-mode');
        head.textContent = '3. Filter Pot';
    }
    document.getElementById('labelModeTag').className = tagModePot ? 'active-mode' : '';
};

// ─── Filter Logic ─────────────────────────────────────────────
let currentActiveCat = '';

function openFilterModal() {
    const docId = document.getElementById('contextDoc').value;
    $('#filter-modal').css('display', 'flex');

    if (!docId) {
        $('#filterCats').html('<div style="padding:10px; color:#888; font-style:italic;">Global Search Mode.<br>Select a Context Doc for categorical filters.</div>');
        $('#filterItems').html('');
    } else {
        $('#filterCats').html('<div style="padding:10px; color:#888;">Loading...</div>');
        $('#filterItems').html('');
        fetch(`taggerang_api.php?action=get_filter_cats&doc_id=${docId}`).then(r => r.json()).then(res => {
            if (res.status === 'success') {
                let html = '';
                (res.data || []).forEach(c => {
                    html += `<div class="filter-item" onclick="loadFilterItems('${c.replace(/'/g, "\\'")}', this)">${c} <span>›</span></div>`;
                });
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
    fetch(`taggerang_api.php?action=get_filter_items&doc_id=${docId}&cat=${encodeURIComponent(category)}`)
        .then(r => r.json()).then(res => {
            if (res.status === 'success') {
                let html = '';
                (Array.isArray(res.data) ? res.data : []).forEach(item => {
                    const label = typeof item === 'string' ? item : (item.name || item.title || 'Unknown');
                    html += `<div class="filter-item" data-cat="${category}" data-text="${label.replace(/"/g, '&quot;')}"><span class="filter-text">${label}</span><span class="filter-drag-handle">::</span></div>`;
                });
                $('#filterItems').html(html);
            }
        });
};

window.applyAdvancedFilter = function() {
    const freeText = $('#filterFreeText').val().trim();
    const items    = [];
    $('#filterPot .filter-item').each(function() {
        const cat  = $(this).attr('data-cat') || '';
        const text = $(this).find('.filter-text').text() || $(this).text().replace('×', '').trim();
        items.push({ cat, name: text });
    });

    // ── TAG MODE: convert pot items into tag definitions (independent of gallery) ──
    if (tagModePot) {
        if (items.length === 0) {
            Toast.show("Tag Mode is on but Filter Pot is empty — nothing to create tags from", "warn");
        } else {
            const newDefs = items.map(i => ({ id: null, name: i.name }));
            const merged  = [...tagDefinitions];
            newDefs.forEach(nd => {
                if (!merged.find(t => t.name === nd.name)) merged.push(nd);
            });
            fetch('taggerang_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_tag_defs', tags: merged })
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    tagDefinitions = res.data;
                    renderTagChips();
                    populateManualTagSelect();
                    Toast.show("Tags created from filter pot 🪃");
                }
            });
        }
    }

    // -- GALLERY MODE: checked = apply filter, unchecked = show everything --
    if (filterModePot) {
        if (items.length === 0 && !freeText) {
            Toast.show("Filter Pot is empty - nothing to filter by", "warn");
            return;
        }
        currentCustomQuery = { text: freeText, items };
        $('#activeFilterBadge').show().text('Active: ' + items.length + ' item(s)');
        $('#filter-modal').hide();
        currentPage = 1;
        loadLibraryPage(1, false);
    } else {
        // Unchecked -- reset to fully unfiltered library, context doc ignored for gallery
        currentCustomQuery = null;
        $('#activeFilterBadge').hide();
        $('#filter-modal').hide();
        currentPage = 1;
        loadLibraryPage(1, true); // forceUnfiltered = ignore context_id
    }
};

// --- Library Load + Render ---
function loadLibraryPage(page, forceUnfiltered) {
    document.getElementById('loadingState').style.display = 'flex';
    document.getElementById('mainSwiper').style.opacity = '0.3';

    const docId = forceUnfiltered ? '' : document.getElementById('contextDoc').value;
    let url = 'taggerang_api.php?action=fetch_library&page=' + page + '&context_id=' + docId;
    if (currentCustomQuery) url += '&filter_payload=' + encodeURIComponent(JSON.stringify(currentCustomQuery));

    fetch(url).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            currentLibraryPage = res.data;
            currentPage  = parseInt(res.meta.current_page);
            totalPages   = parseInt(res.meta.total_pages);
            if (!sketchRegistry) sketchRegistry = {};
            currentLibraryPage.forEach(item => {
                if (item && item.id !== undefined) {
                    sketchRegistry[item.id] = item;
                    if (item.frames) frameRegistry[item.id] = item.frames;
                }
            });
            renderLibrary(currentLibraryPage);
            updatePaginationUI();
        } else {
            Toast.show("Error: " + res.message, "error");
        }
    }).finally(() => {
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('mainSwiper').style.opacity = '1';
    });
}

function updatePaginationUI() {
    document.getElementById('pageInput').value   = currentPage;
    document.getElementById('pageTotalLabel').innerText = `of ${totalPages}`;
    document.getElementById('btnPrev').disabled  = (currentPage <= 1);
    document.getElementById('btnNext').disabled  = (currentPage >= totalPages);
}

function changePage(delta) {
    const target = currentPage + delta;
    if (target >= 1 && target <= totalPages) loadLibraryPage(target);
}

function jumpToPage(val) {
    let p = parseInt(val);
    if (isNaN(p)) p = 1;
    p = Math.max(1, Math.min(p, totalPages));
    loadLibraryPage(p);
}

function renderLibrary(items) {
    const wrapper = document.getElementById('libWrapper');
    wrapper.innerHTML = '';

    items.forEach(s => {
        const slide = document.createElement('div');
        slide.className = 'swiper-slide';
        const initialFrame = (s.frames && s.frames.length > 0) ? s.frames[0] : { id: null, filename: s.thumb };

        slide.dataset.id          = s.id;
        slide.dataset.thumb       = initialFrame.filename;
        slide.dataset.name        = s.name;
        slide.dataset.desc        = s.desc;
        slide.dataset.activeFrameId = initialFrame.id;

        let matchHtml = '';
        if (s.isMatch) {
            if (s.score > 0 && s.score <= 1)      matchHtml = `<span class="match-reason">Sim: ${Math.round(s.score * 100)}%</span>`;
            else if (s.matches && s.matches.length) matchHtml = `<span class="match-reason">Matches: ${s.matches.slice(0, 3).join(', ')}</span>`;
        }

        let navHtml = '';
        if (s.frames && s.frames.length > 1) {
            navHtml = `
                <div class="frame-nav-btn frame-nav-left" onclick="cycleFrame(${s.id}, -1)">‹</div>
                <div class="frame-nav-btn frame-nav-right" onclick="cycleFrame(${s.id}, 1)">›</div>`;
        }

        // Is this sketch already tagged (from this session)?
        const isTagged = (frameRegistry[s.id] || []).some(f => potFrameTags[f.id] && potFrameTags[f.id].size > 0);

        slide.innerHTML = `
            <div class="lib-card${s.isMatch ? ' match' : ''}${isTagged ? ' tagged' : ''}" id="card_${s.id}">
                <div class="lib-thumb">
                    <a href="${initialFrame.filename}" class="pswp-link" id="link_${s.id}" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="${initialFrame.filename}" loading="lazy" id="img_${s.id}">
                    </a>
                    ${navHtml}
                    <div class="drag-handle" title="Drag to Pot">⠿</div>
                </div>
                <div class="lib-meta">
                    <div>
                        <div class="lib-title">${s.name}</div>
                        <div class="lib-id">#${s.id}</div>
                        ${matchHtml}
                        <div class="card-tags" id="ctags_${s.id}"></div>
                    </div>
                    <div class="lib-actions">
                        <button class="action-btn" onclick="openDesc(${s.id})" title="Description">📖</button>
                        <button class="action-btn amber" onclick="quickTag(${s.id})" title="Quick Tag">🏷️</button>
                        <button class="action-btn" style="border-color:rgba(59,130,246,0.3); color:#3b82f6; background:rgba(59,130,246,0.05);" onclick="openEntityForm(${s.id})" title="Edit">✏️</button>
                    </div>
                </div>
            </div>`;

        wrapper.appendChild(slide);
        refreshCardTagChips(s.id);
    });

    if (libSwiper) libSwiper.destroy();
    libSwiper = new Swiper('.lib-swiper', {
        slidesPerView: 'auto', spaceBetween: 20, centeredSlides: true,
        scrollbar: { el: '.swiper-scrollbar' }, freeMode: true,
        mousewheel: true, observer: true, observeParents: true
    });

    new Sortable(wrapper, {
        group: { name: 'shared', pull: 'clone', put: false },
        sort: false, handle: '.drag-handle',
        onClone: function(evt) {
            const s = evt.item; const clone = evt.clone;
            clone.dataset.id          = s.dataset.id;
            clone.dataset.thumb       = s.dataset.thumb;
            clone.dataset.name        = s.dataset.name;
            clone.dataset.desc        = s.dataset.desc;
            clone.dataset.activeFrameId = s.dataset.activeFrameId;
        }
    });

    if (photoSwipeLightbox) photoSwipeLightbox.init();
    document.querySelector('.lib-swiper').style.opacity = 1;
}

// ─── Frame Cycling ────────────────────────────────────────────
window.cycleFrame = function(sketchId, direction) {
    event.stopPropagation();
    const frames = frameRegistry[sketchId];
    if (!frames || frames.length < 2) return;
    const card      = document.getElementById(`card_${sketchId}`).closest('.swiper-slide');
    const img       = document.getElementById(`img_${sketchId}`);
    const link      = document.getElementById(`link_${sketchId}`);
    const currentId = parseInt(card.dataset.activeFrameId);
    let idx = frames.findIndex(f => f.id == currentId);
    if (idx === -1) idx = 0;
    let newIdx = (idx + direction + frames.length) % frames.length;
    const newFrame  = frames[newIdx];
    img.src             = newFrame.filename;
    link.href           = newFrame.filename;
    card.dataset.activeFrameId = newFrame.id;
    card.dataset.thumb  = newFrame.filename;
};

// ─── Quick Tag (single card) ──────────────────────────────────
window.quickTag = function(sketchId) {
    if (activePotTagId === null) { Toast.show("Select an active tag first (chips above the pot)", "warn"); return; }
    const sketch = sketchRegistry[sketchId];
    if (!sketch) return;
    const card = document.getElementById(`card_${sketchId}`).closest('.swiper-slide');
    const frameId = parseInt(card.dataset.activeFrameId);
    if (!frameId) { Toast.show("No frame ID for this sketch", "error"); return; }

    fetch('taggerang_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_frame_tags', tag_id: activePotTagId, frame_ids: [frameId] })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            assignFrameTag(frameId, activePotTagId);
            markCardTagged(sketchId);
            Toast.show(`🏷️ ${sketch.name} → ${res.tag_name}`);
        } else {
            Toast.show(res.message || "Error", "error");
        }
    });
};

// ─── Player (view only — from pot) ───────────────────────────
window.playPot = function() {
    const frames = document.querySelectorAll('#potSortable .film-frame');
    if (frames.length === 0) return;
    const wrap = document.getElementById('playerSlides');
    wrap.innerHTML = '';
    frames.forEach(el => {
        const src = el.querySelector('img').src;
        const id  = el.dataset.id;
        wrap.innerHTML += `
            <div class="swiper-slide">
                <img src="${src}" class="player-img">
                <div class="player-controls">
                    <button class="player-btn" onclick="openDesc(${id})">📖</button>
                    <button class="player-btn amber" onclick="quickTag(${id})">🏷️</button>
                    <button class="player-btn" style="border-color:rgba(59,130,246,0.6); color:#93c5fd; background:rgba(30,58,138,0.6);" onclick="openEntityForm(${id})">✏️</button>
                </div>
            </div>`;
    });
    document.getElementById('playerModal').style.display = 'block';
    if (playerSwiper) playerSwiper.destroy();
    playerSwiper = new Swiper('.player-swiper', {
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        keyboard: true
    });
};
window.closePlayer = function() { document.getElementById('playerModal').style.display = 'none'; };

// ─── Misc Modals ──────────────────────────────────────────────
window.openEntityForm = function(id) {
    document.getElementById('entity-iframe').src = `entity_form.php?entity_type=sketches&entity_id=${id}&view=modal`;
    $('#iframe-modal').css('display', 'flex');
};
window.openDesc = function(id) {
    const item = sketchRegistry[id];
    if (!item) return;
    document.getElementById('desc-title').innerText = item.name;
    document.getElementById('desc-body').innerText  = item.desc;
    $('#desc-modal').css('display', 'flex');
};

window.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        $(e.target).hide();
        if (e.target.id === 'iframe-modal') document.getElementById('entity-iframe').src = '';
    }
});
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php echo $eruda ?? ''; ?>
</body>
</html>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
