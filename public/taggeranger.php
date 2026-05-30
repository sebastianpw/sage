<?php
// public/taggeranger.php
// Taggeranger -- Auto-Tagging Review Interface
// "The file that will outlive us all"
// Companion to: taggerang.php (manual) | taggeranger_api.php (this file's brain)
// V2: Peek 👁️ button in Narratives Filter panel (port from narratives.php)
// V2: Two-step flow — filter → gallery preview → final submit
// V3: Confirm All button at bottom of review list
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = 'Taggeranger';

// Pagination
$perPage     = 20;
$showReviewed = isset($_GET['reviewed']) && $_GET['reviewed'] === '1';
$currentPage  = max(1, (int)($_GET['p'] ?? 1));

ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    :root {
        --bg: #0a0a0f;
        --card: #111118;
        --border: #1e1e2e;
        --text: #e2e2f0;
        --text-muted: #555570;
        --amber: #f59e0b;
        --amber-glow: rgba(245,158,11,0.25);
        --green: #10b981;
        --red: #ef4444;
        --terminal-bg: #020b02;
        --terminal-green: #00ff41;
        --terminal-dim: #005c18;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .tgr-layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

    /* ── TOP BAR ── */
    .top-bar {
        flex-shrink: 0; height: 48px;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center;
        padding: 0 10px 0 54px; gap: 6px;
    }
    .top-bar-divider { width: 1px; height: 20px; background: var(--border); flex-shrink: 0; }
    .tbar-btn {
        width: 36px; height: 36px; border-radius: 4px; font-size: 1rem;
        cursor: pointer; border: 1px solid var(--border); background: transparent;
        color: var(--text-muted); display: flex; align-items: center; justify-content: center;
        transition: all 0.15s; flex-shrink: 0; font-family: inherit; text-decoration: none;
    }
    .tbar-btn:hover { border-color: var(--amber); color: var(--amber); }
    .tbar-btn.active { border-color: var(--amber); color: var(--amber); background: rgba(245,158,11,0.08); }
    .tbar-btn.run { background: var(--amber); color: #000; border-color: var(--amber); font-size: 1.1rem; }
    .tbar-btn.run:hover { filter: brightness(1.1); }
    .tbar-btn.persist { background: var(--green); color: #000; border-color: var(--green); font-size: 1.1rem; }
    .tbar-btn.persist:hover { filter: brightness(1.1); }
    .top-bar-spacer { flex: 1; }
    .staged-count {
        font-size: 0.7rem; color: var(--text-muted); white-space: nowrap;
        font-family: inherit; letter-spacing: 0.3px;
    }
    .staged-count strong { color: var(--amber); }

    /* ── MAIN SCROLL AREA ── */
    .main-scroll { flex: 1; overflow-y: auto; overflow-x: hidden; }
    .frame-list { padding: 12px 16px; display: flex; flex-direction: column; gap: 2px; }

    /* ── FRAME ROW ── */
    .frame-row {
        display: grid;
        grid-template-columns: 220px 1fr 48px;
        gap: 0;
        border: 1px solid var(--border);
        border-radius: 6px;
        overflow: hidden;
        background: var(--card);
        transition: border-color 0.15s;
        min-height: 80px;
    }
    .frame-row:hover { border-color: #2e2e42; }
    .frame-row.reviewed-row { opacity: 0.45; }

    /* Thumbnail column */
    .frame-thumb {
        position: relative; overflow: hidden; cursor: zoom-in;
        background: #000; flex-shrink: 0;
    }
    .frame-thumb img {
        width: 100%; height: 100%; object-fit: cover;
        display: block; transition: opacity 0.2s;
    }
    .frame-thumb:hover img { opacity: 0.85; }
    .frame-meta-overlay {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.9));
        padding: 16px 8px 5px;
        display: flex; align-items: flex-end; justify-content: space-between;
    }
    .frame-id-badge {
        font-size: 0.65rem; color: rgba(255,255,255,0.5);
        font-family: inherit; letter-spacing: 0.5px;
    }
    .frame-edit-btn {
        width: 22px; height: 22px; border-radius: 3px;
        background: rgba(59,130,246,0.7); border: none;
        color: #fff; font-size: 0.65rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.15s; flex-shrink: 0;
    }
    .frame-edit-btn:hover { background: #3b82f6; }

    /* Score bar on thumb */
    .score-bar {
        position: absolute; top: 0; left: 0; right: 0;
        height: 2px; background: var(--border);
    }
    .score-fill { height: 100%; background: var(--amber); transition: width 0.3s; }

    /* Tags column */
    .frame-tags {
        padding: 10px 12px;
        display: flex; flex-wrap: wrap; gap: 5px;
        align-content: flex-start;
        border-left: 1px solid var(--border);
        border-right: 1px solid var(--border);
    }
    .tag-chip {
        padding: 3px 10px; border-radius: 3px; font-size: 0.72rem;
        font-weight: 700; cursor: pointer; border: 1px solid transparent;
        letter-spacing: 0.3px; font-family: inherit;
        transition: all 0.12s; user-select: none;
        display: flex; align-items: center; gap: 5px;
    }
    .tag-chip.on {
        background: rgba(245,158,11,0.15);
        border-color: rgba(245,158,11,0.5);
        color: var(--amber);
    }
    .tag-chip.on:hover { background: rgba(245,158,11,0.25); border-color: var(--amber); }
    .tag-chip.off {
        background: rgba(255,255,255,0.03);
        border-color: var(--border);
        color: var(--text-muted);
        text-decoration: line-through;
        opacity: 0.5;
    }
    .tag-chip.off:hover { opacity: 0.8; border-color: #333; }
    .tag-chip .chip-score {
        font-size: 0.62rem; opacity: 0.6; font-weight: 400;
    }
    .no-proposals {
        font-size: 0.75rem; color: var(--text-muted);
        font-style: italic; padding: 4px 0;
        font-family: inherit;
    }

    /* Review column */
    .frame-review {
        display: flex; align-items: center; justify-content: center;
        padding: 8px 4px;
    }
    .review-check {
        width: 38px; height: 38px; border-radius: 4px;
        border: 1px solid var(--border); background: transparent;
        cursor: pointer; appearance: none; -webkit-appearance: none;
        flex-shrink: 0; transition: all 0.15s; position: relative;
    }
    .review-check:hover { border-color: var(--green); }
    .review-check:checked { background: var(--green); border-color: var(--green); }
    .review-check:checked::after {
        content: ''; position: absolute; top: 4px; left: 12px;
        width: 10px; height: 20px;
        border: 3px solid #000; border-top: none; border-left: none;
        transform: rotate(45deg);
    }

    /* ── CONFIRM ALL BAR ── */
    .confirm-all-bar {
        margin: 4px 16px 12px;
        display: none; /* shown by JS when frames are present */
        align-items: center; gap: 12px;
        padding: 10px 14px;
        background: rgba(16,185,129,0.05);
        border: 1px solid rgba(16,185,129,0.2);
        border-radius: 5px;
    }
    .confirm-all-bar.visible { display: flex; }
    .confirm-all-info {
        flex: 1; font-size: 0.72rem; color: var(--text-muted);
        font-family: inherit; letter-spacing: 0.2px;
    }
    .confirm-all-info strong { color: var(--green); }
    .confirm-all-btn {
        padding: 7px 18px; border-radius: 3px;
        background: transparent; color: var(--green);
        border: 1px solid rgba(16,185,129,0.5);
        font-size: 0.78rem; font-weight: 700; letter-spacing: 0.5px;
        text-transform: uppercase; cursor: pointer; font-family: inherit;
        transition: all 0.15s; white-space: nowrap;
        display: flex; align-items: center; gap: 7px;
    }
    .confirm-all-btn:hover {
        background: rgba(16,185,129,0.1);
        border-color: var(--green);
        color: var(--green);
    }
    .confirm-all-btn:disabled {
        opacity: 0.35; cursor: not-allowed;
    }

    /* ── PAGINATION ── */
    .pagination {
        flex-shrink: 0; height: 48px;
        background: var(--card); border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .pg-btn {
        padding: 4px 14px; border-radius: 3px; font-size: 0.78rem;
        border: 1px solid var(--border); background: transparent;
        color: var(--text-muted); cursor: pointer; font-family: inherit;
        transition: all 0.15s;
    }
    .pg-btn:hover:not(:disabled) { border-color: var(--amber); color: var(--amber); }
    .pg-btn:disabled { opacity: 0.25; cursor: not-allowed; }
    .pg-btn.current { border-color: var(--amber); color: var(--amber); background: rgba(245,158,11,0.1); font-weight: 700; }
    .pg-info { font-size: 0.75rem; color: var(--text-muted); font-family: inherit; padding: 0 8px; }

    /* ── EMPTY STATE ── */
    .empty-state {
        padding: 60px 20px; text-align: center;
        color: var(--text-muted); font-size: 0.85rem;
    }
    .empty-state .big { font-size: 2rem; margin-bottom: 12px; }

    /* ── MODALS ── */
    .modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.88); z-index: 4000;
        align-items: center; justify-content: center;
        backdrop-filter: blur(3px);
    }
    .modal-box {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 8px; padding: 24px; position: relative;
        box-shadow: 0 24px 60px rgba(0,0,0,0.6);
        max-height: 90vh; overflow-y: auto;
    }
    .modal-close {
        position: absolute; top: 12px; right: 14px;
        font-size: 1.4rem; cursor: pointer; color: var(--text-muted);
        background: none; border: none; font-family: inherit;
        line-height: 1; transition: color 0.15s;
    }
    .modal-close:hover { color: var(--text); }
    .modal-title {
        margin: 0 0 16px; font-size: 0.9rem; font-weight: 700;
        letter-spacing: 1px; text-transform: uppercase; color: var(--amber);
        padding-right: 24px;
    }

    /* ── RUN MODAL ── */
    #run-modal .modal-box { width: 760px; max-width: 95vw; }
    .run-section { margin-bottom: 20px; }
    .run-label {
        font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;
        color: var(--text-muted); margin-bottom: 8px; font-weight: 700;
    }
    .run-tag-chips { display: flex; flex-wrap: wrap; gap: 5px; min-height: 32px; }
    .rtag-chip {
        padding: 4px 12px; border-radius: 3px; font-size: 0.75rem; font-weight: 700;
        cursor: pointer; border: 1px solid rgba(245,158,11,0.3);
        background: rgba(245,158,11,0.08); color: var(--amber);
        font-family: inherit; transition: all 0.12s;
    }
    .rtag-chip:hover { background: rgba(245,158,11,0.2); }
    .rtag-chip.excluded {
        background: rgba(255,255,255,0.03); border-color: var(--border);
        color: var(--text-muted); text-decoration: line-through; opacity: 0.45;
    }
    .rtag-chip.excluded:hover { opacity: 0.7; }
    .no-tags-hint { font-size: 0.78rem; color: var(--text-muted); font-style: italic; }

    .run-range { display: flex; gap: 10px; align-items: center; }
    .run-input {
        padding: 7px 10px; border-radius: 4px; font-size: 0.85rem;
        border: 1px solid var(--border); background: var(--bg);
        color: var(--text); font-family: inherit; width: 130px;
    }
    .run-input:focus { outline: none; border-color: var(--amber); }
    .run-range-sep { color: var(--text-muted); font-size: 0.8rem; }

    .threshold-row { display: flex; align-items: center; gap: 12px; }
    .threshold-slider {
        flex: 1; accent-color: var(--amber);
        height: 4px; cursor: pointer;
    }
    .threshold-val {
        font-size: 0.9rem; font-weight: 700; color: var(--amber);
        min-width: 36px; text-align: right;
    }

    .fire-btn {
        width: 100%; padding: 12px; border-radius: 4px;
        background: var(--amber); color: #000; border: none;
        font-size: 0.9rem; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; cursor: pointer; font-family: inherit;
        transition: filter 0.15s; margin-top: 4px;
    }
    .fire-btn:hover { filter: brightness(1.1); }
    .fire-btn:disabled { opacity: 0.4; cursor: not-allowed; filter: none; }

    /* ── NEW: FILTER SUBMIT BUTTON (intermediate step) ── */
    .filter-submit-btn {
        width: 100%; padding: 12px; border-radius: 4px;
        background: transparent; color: var(--amber); border: 1px solid var(--amber);
        font-size: 0.9rem; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; cursor: pointer; font-family: inherit;
        transition: all 0.15s; margin-top: 4px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .filter-submit-btn:hover { background: rgba(245,158,11,0.1); }
    .filter-submit-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ── RUN MODE TOGGLE ── */
    .run-mode-tabs {
        display: flex; gap: 0; margin-bottom: 20px;
        border: 1px solid var(--border); border-radius: 4px; overflow: hidden;
    }
    .run-mode-tab {
        flex: 1; padding: 8px 12px; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.5px; text-transform: uppercase; cursor: pointer;
        border: none; background: transparent; color: var(--text-muted);
        font-family: inherit; transition: all 0.15s;
    }
    .run-mode-tab:not(:last-child) { border-right: 1px solid var(--border); }
    .run-mode-tab.active { background: rgba(245,158,11,0.12); color: var(--amber); }
    .run-mode-tab:hover:not(.active) { background: rgba(255,255,255,0.04); color: var(--text); }

    /* ── MAP RUNS PANEL (inside run modal) ── */
    #mapRunsPanel { display: none; }
    #frameRangePanel { display: block; }
    #narrativesPanel { display: none; }

    .mr-list {
        display: flex; flex-direction: column; gap: 6px;
        max-height: 340px; overflow-y: auto;
        padding-right: 4px; margin-bottom: 12px;
    }
    .mr-item {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 12px; border-radius: 4px;
        border: 1px solid var(--border); background: rgba(255,255,255,0.02);
        cursor: pointer; transition: border-color 0.15s;
    }
    .mr-item:hover { border-color: rgba(245,158,11,0.4); }
    .mr-item.selected { border-color: var(--amber); background: rgba(245,158,11,0.07); }
    .mr-item-id {
        font-size: 0.7rem; font-weight: 700; color: var(--amber);
        min-width: 60px; font-family: inherit;
    }
    .mr-item-meta {
        flex: 1; font-size: 0.75rem; color: var(--text-muted);
        overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
    }
    .mr-item-count {
        font-size: 0.7rem; color: var(--text-muted);
        white-space: nowrap; flex-shrink: 0;
    }
    .mr-search-row {
        display: flex; gap: 8px; margin-bottom: 10px;
    }
    .mr-search-input {
        flex: 1; padding: 6px 10px; border-radius: 3px; font-size: 0.8rem;
        border: 1px solid var(--border); background: var(--bg);
        color: var(--text); font-family: inherit;
    }
    .mr-search-input:focus { outline: none; border-color: var(--amber); }
    .mr-pagination {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 10px; justify-content: center;
    }
    .mr-pg-btn {
        width: 28px; height: 28px; border-radius: 3px; font-size: 1rem;
        border: 1px solid var(--border); background: transparent;
        color: var(--text-muted); cursor: pointer; font-family: inherit;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.15s; flex-shrink: 0; padding: 0;
    }
    .mr-pg-btn:hover:not(:disabled) { border-color: var(--amber); color: var(--amber); }
    .mr-pg-btn:disabled { opacity: 0.25; cursor: not-allowed; }
    .mr-pg-index {
        width: 36px; text-align: center; padding: 4px 4px;
        border: 1px solid var(--border); border-radius: 3px;
        background: var(--bg); color: var(--amber);
        font-family: inherit; font-size: 0.8rem; font-weight: 700;
    }
    .mr-pg-index:focus { outline: none; border-color: var(--amber); }
    .mr-pg-total { font-size: 0.72rem; color: var(--text-muted); font-family: inherit; }

    /* Map Run Swiper (frame preview strip) */
    .mr-swiper-wrap {
        display: none; /* shown when a run is selected */
        margin-bottom: 14px; position: relative;
    }
    .mr-swiper-wrap.visible { display: block; }
    .mr-frame-swiper { width: 100%; padding: 8px 0 16px; }
    .mr-frame-swiper .swiper-slide { width: 110px; }
    .mr-frame-card {
        border-radius: 4px; overflow: hidden; border: 1px solid var(--border);
        background: #000; position: relative; cursor: pointer;
        transition: border-color 0.15s;
    }
    .mr-frame-card:hover { border-color: rgba(245,158,11,0.5); }
    .mr-frame-card.marked { border-color: var(--amber); }
    .mr-frame-card img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
    .mr-frame-card-label {
        position: absolute; bottom: 0; left: 0; right: 0;
        padding: 3px 5px; background: rgba(0,0,0,0.75);
        font-size: 0.6rem; color: rgba(255,255,255,0.6);
        font-family: inherit; line-height: 1.2;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .mr-frame-card.marked .mr-frame-card-label { color: var(--amber); }

    .mr-selection-bar {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 12px; background: rgba(245,158,11,0.06);
        border: 1px solid rgba(245,158,11,0.2); border-radius: 4px;
        margin-bottom: 12px; flex-wrap: wrap;
    }
    .mr-sel-info {
        flex: 1; font-size: 0.75rem; color: var(--amber);
        font-family: inherit; min-width: 120px;
    }
    .mr-sel-cb-label {
        font-size: 0.73rem; color: var(--text-muted);
        display: flex; align-items: center; gap: 5px;
        cursor: pointer; user-select: none; font-family: inherit;
    }
    .mr-sel-cb-label input[type=checkbox] {
        accent-color: var(--amber); cursor: pointer;
    }
    .mr-mark-all-btn {
        padding: 5px 12px; border-radius: 3px; font-size: 0.72rem; font-weight: 700;
        border: 1px solid rgba(245,158,11,0.4); background: transparent;
        color: var(--amber); font-family: inherit; cursor: pointer;
        transition: background 0.15s; white-space: nowrap;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .mr-mark-all-btn:hover { background: rgba(245,158,11,0.12); }

    /* Scrollbar for mr-list */
    .mr-list::-webkit-scrollbar { width: 3px; }
    .mr-list::-webkit-scrollbar-thumb { background: var(--border); }

    /* ── NARRATIVES FILTER PANEL ── */
    .nf-col {
        flex: 1; border: 1px solid var(--border); border-radius: 4px;
        display: flex; flex-direction: column; overflow: hidden;
        background: rgba(0,0,0,0.1); min-width: 0;
    }
    .nf-col-head {
        padding: 7px 10px; background: var(--card); font-weight: 700;
        border-bottom: 1px solid var(--border); font-size: 0.72rem;
        text-transform: uppercase; color: var(--text-muted);
    }
    .nf-col-list {
        flex: 1; overflow-y: auto; padding: 6px;
        display: flex; flex-direction: column; gap: 4px; max-height: 220px;
    }
    .nf-item {
        padding: 6px 10px; background: var(--card); border: 1px solid var(--border);
        border-radius: 4px; font-size: 0.8rem; cursor: pointer; transition: 0.1s;
        display: flex; justify-content: space-between; align-items: center;
        font-family: inherit;
    }
    .nf-item:hover { border-color: var(--amber); color: var(--amber); }
    .nf-item.active { background: rgba(245,158,11,0.12); border-color: var(--amber); color: var(--amber); }
    .nf-pot-item { background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.3); color: var(--amber); cursor: default; }
    .nf-pot-item:hover { border-color: rgba(245,158,11,0.5); color: var(--amber); }
    .nf-remove { cursor: pointer; color: var(--red); margin-left: 8px; font-weight: 700; }

    /* ── PEEK BUTTON — inside items column (port from narratives.php) ── */
    .nf-item-controls {
        display: flex; align-items: center; gap: 4px;
        flex-shrink: 0; margin-left: 6px;
    }
    .nf-peek-btn {
        width: 24px; height: 24px;
        display: flex; align-items: center; justify-content: center;
        color: var(--text-muted); cursor: pointer;
        background: rgba(0,0,0,0.1); border-radius: 3px;
        border: 1px solid rgba(0,0,0,0.15); font-size: 0.75rem;
        flex-shrink: 0; transition: all 0.15s; line-height: 1;
    }
    .nf-peek-btn:hover {
        color: #f59e0b;
        background: rgba(245,158,11,0.1);
        border-color: rgba(245,158,11,0.4);
    }

    /* Scrollbar for nf-col-list */
    .nf-col-list::-webkit-scrollbar { width: 3px; }
    .nf-col-list::-webkit-scrollbar-thumb { background: var(--border); }

    /* ── ENTITY PEEK MODAL (above run-modal at z-index 5000) ── */
    #nf-peek-modal {
        z-index: 5000;
    }
    #nf-peek-modal .modal-box {
        width: 580px; max-width: 95vw; max-height: 82vh; overflow-y: auto;
    }
    .peek-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        margin-bottom: 16px; padding-bottom: 14px;
        border-bottom: 1px solid var(--border); gap: 12px;
    }
    .peek-cat-badge {
        font-size: 0.68rem; padding: 3px 9px; border-radius: 12px;
        background: rgba(245,158,11,0.12); color: var(--amber);
        border: 1px solid rgba(245,158,11,0.3);
        text-transform: uppercase; font-weight: 700;
        white-space: nowrap; flex-shrink: 0; margin-top: 4px;
    }
    .peek-section { margin-bottom: 14px; }
    .peek-section-title {
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 6px;
    }
    .peek-value { font-size: 0.9rem; line-height: 1.55; color: var(--text); }
    .peek-pill-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
    .peek-pill {
        font-size: 0.76rem; padding: 2px 9px; border-radius: 10px;
        background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text);
    }
    .peek-kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
    .peek-kv-key { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 2px; }
    .peek-kv-val { font-size: 0.83rem; color: var(--text); line-height: 1.4; }
    .peek-loading {
        display: flex; align-items: center; justify-content: center;
        padding: 40px 0; gap: 12px; color: var(--text-muted); font-size: 0.88rem;
    }
    .peek-spinner {
        width: 20px; height: 20px;
        border: 3px solid rgba(255,255,255,0.1);
        border-top-color: var(--amber);
        border-radius: 50%; animation: spin 0.8s linear infinite; flex-shrink: 0;
    }
    .peek-not-found { padding: 30px 0; text-align: center; color: var(--text-muted); font-size: 0.88rem; }

    /* ── GALLERY PREVIEW MODAL (two-step) ── */
    #gallery-preview-modal .modal-box {
        width: 92vw; max-width: 1200px; height: 90vh;
        display: flex; flex-direction: column; padding: 0; overflow: hidden;
    }
    .gp-header {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 20px; border-bottom: 1px solid var(--border);
        background: var(--card); flex-shrink: 0;
    }
    .gp-title { font-size: 0.85rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--amber); flex: 1; }
    .gp-meta { font-size: 0.72rem; color: var(--text-muted); }
    .gp-close-btn {
        width: 32px; height: 32px; border-radius: 4px; font-size: 1.1rem;
        cursor: pointer; border: 1px solid var(--border); background: transparent;
        color: var(--text-muted); display: flex; align-items: center; justify-content: center;
        transition: all 0.15s; flex-shrink: 0; font-family: inherit;
    }
    .gp-close-btn:hover { border-color: var(--red); color: var(--red); }

    /* Gallery toolbar */
    .gp-toolbar {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 16px; background: rgba(0,0,0,0.2);
        border-bottom: 1px solid var(--border); flex-shrink: 0; flex-wrap: wrap;
    }
    .gp-toolbar-info { font-size: 0.72rem; color: var(--text-muted); flex: 1; }
    .gp-toolbar-info strong { color: var(--amber); }
    .gp-select-all-btn {
        padding: 5px 12px; border-radius: 3px; font-size: 0.7rem; font-weight: 700;
        border: 1px solid rgba(245,158,11,0.4); background: transparent;
        color: var(--amber); font-family: inherit; cursor: pointer;
        transition: background 0.15s; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .gp-select-all-btn:hover { background: rgba(245,158,11,0.1); }

    /* Gallery grid */
    .gp-grid-wrap { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 12px; }
    .gp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 8px;
    }

    /* Gallery card */
    .gp-card {
        border-radius: 5px; overflow: hidden;
        border: 2px solid var(--border); background: #000;
        position: relative; cursor: pointer; transition: border-color 0.12s;
    }
    .gp-card:hover { border-color: rgba(245,158,11,0.4); }
    .gp-card.selected { border-color: var(--amber); }
    .gp-card.selected::after {
        content: '✓'; position: absolute; top: 5px; right: 5px;
        width: 20px; height: 20px; border-radius: 50%;
        background: var(--amber); color: #000;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 900;
    }
    .gp-card img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; }
    .gp-card-label {
        padding: 4px 7px; background: rgba(0,0,0,0.8);
        font-size: 0.6rem; color: rgba(255,255,255,0.5);
        font-family: inherit; white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis;
    }
    .gp-card.selected .gp-card-label { color: var(--amber); }

    /* Gallery footer with final fire button */
    .gp-footer {
        padding: 12px 16px; border-top: 1px solid var(--border);
        background: var(--card); flex-shrink: 0;
        display: flex; align-items: center; gap: 12px;
    }
    .gp-footer-info { flex: 1; font-size: 0.75rem; color: var(--text-muted); }
    .gp-footer-info strong { color: var(--green); }
    .gp-fire-btn {
        padding: 10px 24px; border-radius: 4px;
        background: var(--green); color: #000; border: none;
        font-size: 0.88rem; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; cursor: pointer; font-family: inherit;
        transition: filter 0.15s; white-space: nowrap;
    }
    .gp-fire-btn:hover { filter: brightness(1.1); }
    .gp-fire-btn:disabled { opacity: 0.4; cursor: not-allowed; filter: none; }
    .gp-cancel-btn {
        padding: 10px 18px; border-radius: 4px;
        background: transparent; color: var(--text-muted);
        border: 1px solid var(--border);
        font-size: 0.88rem; font-weight: 700;
        cursor: pointer; font-family: inherit; transition: all 0.15s;
    }
    .gp-cancel-btn:hover { border-color: var(--text-muted); color: var(--text); }

    .gp-grid-wrap::-webkit-scrollbar { width: 4px; }
    .gp-grid-wrap::-webkit-scrollbar-thumb { background: var(--border); }

    /* Loading inside gallery */
    .gp-loading {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        height: 200px; gap: 12px; color: var(--text-muted); font-size: 0.8rem;
    }

    /* ── TERMINAL MODAL ── */
    #terminal-modal .modal-box {
        width: 760px; max-width: 95vw;
        background: var(--terminal-bg);
        border-color: var(--terminal-dim);
    }
    #terminal-modal .modal-title { color: var(--terminal-green); }
    .terminal-screen {
        background: var(--terminal-bg);
        border: 1px solid var(--terminal-dim);
        border-radius: 3px;
        padding: 14px;
        height: 340px;
        overflow-y: auto;
        font-family: inherit;
        font-size: 0.75rem;
        line-height: 1.6;
        color: var(--terminal-green);
        scroll-behavior: smooth;
    }
    .terminal-screen .t-line { display: block; }
    .terminal-screen .t-dim { color: var(--terminal-dim); }
    .terminal-screen .t-bright { color: #7fff7f; font-weight: 700; }
    .terminal-screen .t-warn { color: #ffcc00; }
    .terminal-screen .t-done { color: var(--terminal-green); }
    .terminal-screen .cursor {
        display: inline-block; width: 8px; height: 13px;
        background: var(--terminal-green); animation: blink 1s step-end infinite;
        vertical-align: text-bottom; margin-left: 2px;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
    .terminal-stats {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;
        margin-top: 12px;
    }
    .t-stat {
        background: rgba(0,255,65,0.05); border: 1px solid var(--terminal-dim);
        border-radius: 3px; padding: 8px; text-align: center;
    }
    .t-stat-val { font-size: 1.1rem; font-weight: 700; color: var(--terminal-green); }
    .t-stat-label { font-size: 0.65rem; color: var(--terminal-dim); margin-top: 2px; }
    .terminal-done-btn {
        width: 100%; padding: 10px; margin-top: 12px;
        background: transparent; border: 1px solid var(--terminal-green);
        color: var(--terminal-green); font-family: inherit; font-size: 0.8rem;
        font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
        cursor: pointer; border-radius: 3px; transition: all 0.15s;
    }
    .terminal-done-btn:hover { background: rgba(0,255,65,0.1); }
    .terminal-done-btn:disabled { opacity: 0.3; cursor: not-allowed; }

    /* ── ENTITY IFRAME MODAL ── */
    #iframe-modal .modal-box {
        width: 95vw; max-width: 1200px; height: 88vh;
        padding: 8px; display: flex; flex-direction: column;
    }
    #iframe-modal .modal-title { margin-bottom: 8px; }
    #entity-iframe { flex: 1; border: none; border-radius: 3px; background: var(--bg); width: calc(100% / 0.8); height: calc(100% / 0.8); transform: scale(0.8); transform-origin: top left; }

    /* ── PERSIST CONFIRM MODAL ── */
    #persist-modal .modal-box { width: 420px; max-width: 95vw; }
    .persist-info { font-size: 0.82rem; color: var(--text-muted); margin-bottom: 16px; line-height: 1.6; }
    .persist-info strong { color: var(--text); }
    .btn-row { display: flex; gap: 8px; }
    .btn-confirm { flex: 1; padding: 10px; border-radius: 4px; border: none; font-family: inherit; font-size: 0.85rem; font-weight: 700; cursor: pointer; letter-spacing: 0.5px; }
    .btn-confirm.go { background: var(--green); color: #000; }
    .btn-confirm.go:hover { filter: brightness(1.1); }
    .btn-confirm.cancel { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
    .btn-confirm.cancel:hover { border-color: var(--text-muted); color: var(--text); }

    /* ── LOADING OVERLAY ── */
    .loading-overlay {
        display: none; position: absolute; inset: 0;
        background: rgba(10,10,15,0.7); z-index: 100;
        align-items: center; justify-content: center;
        flex-direction: column; gap: 12px;
    }
    .loading-overlay.visible { display: flex; }
    .spinner {
        width: 32px; height: 32px;
        border: 3px solid rgba(245,158,11,0.2);
        border-top-color: var(--amber);
        border-radius: 50%; animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Scrollbar */
    .main-scroll::-webkit-scrollbar { width: 4px; }
    .main-scroll::-webkit-scrollbar-track { background: transparent; }
    .main-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
    .terminal-screen::-webkit-scrollbar { width: 3px; }
    .terminal-screen::-webkit-scrollbar-thumb { background: var(--terminal-dim); }

    /* PhotoSwipe override */
    .pswp { z-index: 9999; }

    /* ── LIGHTBOX MODAL for map run frames ── */
    #mr-lightbox-modal .modal-box {
        background: rgba(0,0,0,0.97); border-color: #222;
        padding: 8px; max-width: 95vw; max-height: 95vh;
        display: flex; align-items: center; justify-content: center;
    }
    #mr-lightbox-img {
        max-width: calc(95vw - 60px); max-height: calc(95vh - 60px);
        object-fit: contain; border-radius: 3px;
    }
    #mr-lightbox-modal .modal-close { color: #fff; font-size: 2rem; top: 8px; right: 12px; }
</style>

<div class="tgr-layout">

    <!-- ── TOP BAR ── -->
    <div class="top-bar">
        <!-- View toggle -->
        <a href="?reviewed=0&p=1" class="tbar-btn <?= !$showReviewed ? 'active' : '' ?>" title="Unreviewed frames">&#9633;</a>
        <a href="?reviewed=1&p=1" class="tbar-btn <?= $showReviewed ? 'active' : '' ?>" title="Reviewed frames">&#10003;</a>

        <div class="top-bar-divider"></div>

        <button class="tbar-btn" onclick="openTagManagerModal()" title="Manage tags (show/hide)">&#9881;</button>
        <button class="tbar-btn run" onclick="openRunModal()" title="Run Auto-Tag">&#9889;</button>

        <div class="top-bar-spacer"></div>

        <div class="staged-count" id="stagedCount"></div>

        <div class="top-bar-divider"></div>

        <button class="tbar-btn persist" onclick="openPersistModal()" title="Persist staged tags to database">&#8659;</button>
    </div>

    <!-- ── MAIN SCROLL ── -->
    <div class="main-scroll" id="mainScroll" style="position:relative;">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <div style="font-size:0.75rem; color:var(--text-muted); letter-spacing:1px;">LOADING</div>
        </div>
        <div class="frame-list" id="frameList">
            <!-- populated by JS -->
        </div>

        <!-- ── CONFIRM ALL BAR — sits below the frame list ── -->
        <div class="confirm-all-bar" id="confirmAllBar">
            <div class="confirm-all-info" id="confirmAllInfo">
                Mark all <strong id="confirmAllCount">0</strong> visible frames as reviewed
            </div>
            <button class="confirm-all-btn" id="confirmAllBtn" onclick="confirmAllReviewed()">
                &#10003; Confirm All
            </button>
        </div>

        <div id="emptyState" class="empty-state" style="display:none;">
            <div class="big"><?= $showReviewed ? '&#10003;' : '&#9632;' ?></div>
            <div><?= $showReviewed ? 'No reviewed frames yet.' : 'No unreviewed staged frames. Run the auto-tagger first.' ?></div>
        </div>
    </div>

    <!-- ── PAGINATION ── -->
    <div class="pagination" id="paginationBar">
        <!-- populated by JS -->
    </div>
</div>

<!-- ═══════════════════════════════════════ RUN MODAL ═══ -->
<div id="run-modal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('run-modal')">&times;</button>
        <div class="modal-title">&#9654; Configure Auto-Tag Run</div>

        <!-- Mode Tabs -->
        <div class="run-mode-tabs">
            <button class="run-mode-tab active" id="tab-frame-range" onclick="switchRunMode('frame-range')">&#9632; Frame Range</button>
            <button class="run-mode-tab" id="tab-map-run" onclick="switchRunMode('map-run')">&#9654; Sketches Map Run</button>
            <button class="run-mode-tab" id="tab-narratives" onclick="switchRunMode('narratives')">&#9670; Narratives Filter</button>
        </div>

        <!-- Tag set (shared) -->
        <div class="run-section">
            <div class="run-label">Tags to score against <span style="color:var(--text-muted); font-weight:400;">(click to exclude)</span></div>
            <div class="run-tag-chips" id="runTagChips">
                <span class="no-tags-hint">Loading tags...</span>
            </div>
        </div>

        <!-- ── FRAME RANGE PANEL ── -->
        <div id="frameRangePanel">
            <div class="run-section">
                <div class="run-label">Frame ID range</div>
                <div class="run-range">
                    <input type="number" class="run-input" id="runFromId" placeholder="From ID (oldest)">
                    <span class="run-range-sep">&#8594;</span>
                    <input type="number" class="run-input" id="runToId" placeholder="To ID (newest)">
                    <span style="font-size:0.75rem; color:var(--text-muted);">Leave blank = all frames</span>
                </div>
            </div>
        </div>

        <!-- ── MAP RUN PANEL ── -->
        <div id="mapRunsPanel">
            <div class="run-section">
                <div class="run-label">Select a Sketches Map Run</div>

                <!-- Search -->
                <div class="mr-search-row">
                    <input type="text" class="mr-search-input" id="mrSearchInput" placeholder="Search by ID or note..." oninput="debouncedMrSearch()">
                </div>

                <!-- Run list -->
                <div class="mr-list" id="mrList">
                    <div style="font-size:0.75rem; color:var(--text-muted); padding:8px; font-style:italic;">Loading map runs...</div>
                </div>
                <div class="mr-pagination" id="mrPagination" style="display:none;">
                    <button class="mr-pg-btn" id="mrPgPrev" onclick="mrGoPage(mrPage - 1)" title="Previous page">&#8592;</button>
                    <input type="number" class="mr-pg-index" id="mrPgIndex" value="1" min="1"
                           onchange="mrGoPage(parseInt(this.value))"
                           onkeydown="if(event.key==='Enter') mrGoPage(parseInt(this.value))">
                    <span class="mr-pg-total" id="mrPgTotal">/ 1 &nbsp;&bull;&nbsp; 0 runs</span>
                    <button class="mr-pg-btn" id="mrPgNext" onclick="mrGoPage(mrPage + 1)" title="Next page">&#8594;</button>
                </div>

                <!-- Frame preview swiper (shown after selecting a run) -->
                <div class="mr-swiper-wrap" id="mrSwiperWrap">
                    <div class="run-label" style="margin-bottom:6px;">
                        Frames in run &mdash; click to zoom &nbsp;
                        <span id="mrSwiperRunLabel" style="color:var(--amber);"></span>
                    </div>
                    <div class="swiper mr-frame-swiper" id="mrFrameSwiper">
                        <div class="swiper-wrapper" id="mrFrameSwiperInner"></div>
                        <div class="swiper-button-next" style="color:var(--amber);"></div>
                        <div class="swiper-button-prev" style="color:var(--amber);"></div>
                        <div class="swiper-scrollbar"></div>
                    </div>
                </div>

                <!-- Selection bar (shown after selecting a run) -->
                <div class="mr-selection-bar" id="mrSelectionBar" style="display:none;">
                    <div class="mr-sel-info" id="mrSelInfo">No run selected</div>
                    <label class="mr-sel-cb-label">
                        <input type="checkbox" id="mrTagAllFrames" onchange="onTagAllFramesToggle()">
                        Tag all frames of each sketch
                    </label>
                    <button class="mr-mark-all-btn" id="mrMarkAllBtn" onclick="toggleMarkAllFrames()">&#10003; Mark All</button>
                </div>
            </div>
        </div>

        <!-- ── NARRATIVES FILTER PANEL ── -->
        <div id="narrativesPanel">
            <div class="run-section">
                <div class="run-label">Context Document</div>
                <select class="run-input" id="nfContextDoc" style="width:100%; max-width:420px;">
                    <option value="">-- Select Context --</option>
                    <!-- populated by loadNarrativeDocs() -->
                </select>
            </div>
            <div class="run-section">
                <div class="run-label">Filter <span style="color:var(--text-muted); font-weight:400;">(same as Narratives Advanced Filter)</span></div>
                <div id="nfFilterColumns" style="display:flex; gap:10px; max-height:320px;">
                    <div class="nf-col" id="nfCats">
                        <div class="nf-col-head">Categories</div>
                        <div class="nf-col-list" id="nfCatList">
                            <div style="padding:8px; color:var(--text-muted); font-style:italic; font-size:0.75rem;">Select a context doc first</div>
                        </div>
                    </div>
                    <div class="nf-col" id="nfItemsCol">
                        <!-- Updated head: now shows the peek icon hint -->
                        <div class="nf-col-head">Items (click to add &nbsp;·&nbsp; 👁 Peek)</div>
                        <div class="nf-col-list" id="nfItemList"></div>
                    </div>
                    <div class="nf-col" id="nfPotCol" style="border-color:var(--amber);">
                        <div class="nf-col-head" style="color:var(--amber);">Filter Pot</div>
                        <div class="nf-col-list" id="nfPotList">
                            <div style="padding:8px; color:var(--text-muted); font-style:italic; font-size:0.75rem;">Click items to add</div>
                        </div>
                    </div>
                </div>
                <textarea id="nfFreeText" class="run-input"
                    style="width:100%; margin-top:8px; height:56px; resize:vertical;"
                    placeholder="Optional: free text instructions (e.g. 'dark mood, industrial atmosphere')..."></textarea>
            </div>
            <div id="nfSelectionBar" style="display:none;" class="mr-selection-bar">
                <div class="mr-sel-info" id="nfSelInfo">No items in filter pot</div>
            </div>
        </div>

        <!-- Threshold (shared) -->
        <div class="run-section">
            <div class="run-label">Confidence threshold &mdash; only score &ge; this value gets staged</div>
            <div class="threshold-row">
                <input type="range" class="threshold-slider" id="thresholdSlider" min="0.40" max="0.95" step="0.01" value="0.70" oninput="document.getElementById('thresholdVal').textContent = parseFloat(this.value).toFixed(2)">
                <div class="threshold-val" id="thresholdVal">0.70</div>
            </div>
        </div>

        <!-- Max tags per frame (shared) -->
        <div class="run-section">
            <div class="run-label">Max tags per frame (top N by score)</div>
            <div class="run-range">
                <input type="number" class="run-input" id="runMaxTags" value="5" min="1" max="20">
            </div>
        </div>

        <!-- Skip validation — shown in map run and narratives modes -->
        <div id="skipValidationRow" style="display:none; margin-bottom:12px;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none;
                           padding:10px 12px; border-radius:4px;
                           border:1px solid rgba(245,158,11,0.2); background:rgba(245,158,11,0.04);">
                <input type="checkbox" id="skipValidation" style="accent-color:var(--amber); width:15px; height:15px; cursor:pointer;">
                <span style="font-size:0.75rem; color:var(--text-muted); font-family:inherit; line-height:1.4;">
                    <strong style="color:var(--amber); font-size:0.8rem;">Skip vector validation</strong>
                    &mdash; write all marked frames directly to staging at score&nbsp;1.0
                    without querying Chroma
                </span>
            </label>
        </div>

        <!-- ── TWO-STEP: for map-run and narratives show "Preview Frames" button first ── -->
        <button class="filter-submit-btn" id="filterSubmitBtn" style="display:none;" onclick="openGalleryPreview()">
            👁 Preview Frames &amp; Select
        </button>

        <button class="fire-btn" id="fireBtn" onclick="fireRun()">&#9889; START AUTO-TAG RUN</button>
    </div>
</div>

<!-- ═══════════════════════════ GALLERY PREVIEW MODAL ═══ -->
<div id="gallery-preview-modal" class="modal-overlay" style="z-index:4500;">
    <div class="modal-box">
        <div class="gp-header">
            <div class="gp-title">&#9670; Frame Preview &mdash; Select to Include</div>
            <div class="gp-meta" id="gpMeta"></div>
            <button class="gp-close-btn" onclick="closeModal('gallery-preview-modal')" title="Close preview">&times;</button>
        </div>

        <div class="gp-toolbar">
            <div class="gp-toolbar-info" id="gpToolbarInfo">Loading frames...</div>
            <button class="gp-select-all-btn" id="gpSelectAllBtn" onclick="gpToggleSelectAll()">&#10003; Select All</button>
        </div>

        <div class="gp-grid-wrap" id="gpGridWrap">
            <div class="gp-loading">
                <div class="spinner"></div>
                <div>Resolving frames...</div>
            </div>
        </div>

        <div class="gp-footer">
            <div class="gp-footer-info" id="gpFooterInfo">
                Select the frames you want to include in this run.
            </div>
            <button class="gp-cancel-btn" onclick="closeModal('gallery-preview-modal')">Cancel</button>
            <button class="gp-fire-btn" id="gpFireBtn" onclick="fireRunFromPreview()">
                &#9889; Run Auto-Tag on Selected
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════ TERMINAL MODAL ═══ -->
<div id="terminal-modal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('terminal-modal')" id="terminalCloseBtn" disabled>&times;</button>
        <div class="modal-title">&#9632; TAGGERANGER // AUTO-TAG LOG</div>
        <div class="terminal-screen" id="terminalScreen">
            <span class="t-line t-dim">// waiting for run...</span>
            <span class="cursor"></span>
        </div>
        <div class="terminal-stats" id="terminalStats" style="display:none;">
            <div class="t-stat"><div class="t-stat-val" id="statFrames">0</div><div class="t-stat-label">FRAMES</div></div>
            <div class="t-stat"><div class="t-stat-val" id="statTags">0</div><div class="t-stat-label">TAGS SCORED</div></div>
            <div class="t-stat"><div class="t-stat-val" id="statProposed">0</div><div class="t-stat-label">PROPOSALS</div></div>
            <div class="t-stat"><div class="t-stat-val" id="statTime">0s</div><div class="t-stat-label">ELAPSED</div></div>
        </div>
        <button class="terminal-done-btn" id="terminalDoneBtn" disabled onclick="onRunComplete()">VIEW RESULTS</button>
    </div>
</div>

<!-- ════════════════════════════════════ PERSIST MODAL ═══ -->
<div id="persist-modal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('persist-modal')">&times;</button>
        <div class="modal-title">&#8659; Persist Staged Tags</div>
        <div class="persist-info" id="persistInfo">
            This will write all <strong id="persistCount">?</strong> approved staged assignments
            to the real <code>tags_2_frames</code> table.<br><br>
            Only <strong>active chips</strong> (not toggled off) from <strong>reviewed frames</strong> are persisted.
            Unreviewed frames are skipped.<br><br>
            After writing, <strong>all reviewed staging rows are removed</strong> (active ones are now live;
            inactive ones were consciously rejected). Unreviewed frames remain in staging untouched.<br><br>
            Duplicates in the live table are ignored &mdash; safe to run multiple times.
        </div>
        <div class="btn-row">
            <button class="btn-confirm cancel" onclick="closeModal('persist-modal')">Cancel</button>
            <button class="btn-confirm go" onclick="doPersist()">&#8659; Persist Now</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════ ENTITY IFRAME ═══ -->
<div id="iframe-modal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('iframe-modal'); document.getElementById('entity-iframe').src='';">&times;</button>
        <div class="modal-title">Edit Entity</div>
        <iframe id="entity-iframe" src=""></iframe>
    </div>
</div>

<!-- ══════════════════════════════════ TAG MANAGER MODAL ═══ -->
<div id="tag-manager-modal" class="modal-overlay">
    <div class="modal-box" style="width:480px; max-width:95vw;">
        <button class="modal-close" onclick="closeModal('tag-manager-modal')">&times;</button>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <div class="modal-title" style="margin:0;">&#9881; Manage Tags</div>
            <button onclick="clearAllTagsFromUI()" title="Hide all from UI — nothing deleted from database"
                style="padding:4px 10px; border-radius:3px; font-size:0.75rem; font-weight:700; cursor:pointer;
                       border:1px solid rgba(239,68,68,0.3); background:transparent; color:#ef4444;
                       font-family:inherit; transition:all 0.15s;">
                &#10005; Clear all from UI
            </button>
        </div>

        <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border);">
            <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:5px;">Load Keywords from Document:</label>
            <select id="tmDocSelect" style="width:100%; cursor:pointer; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem;" onchange="applyDocKeywords(this.value)">
                <option value="">-- Select a Document --</option>
            </select>
        </div>

        <p style="font-size:0.78rem; color:var(--text-muted); margin:0 0 12px; line-height:1.5;">
            &#10005; hides a tag from UI only &mdash; never deletes from database.<br>
            Re-add by typing the name below &mdash; existing tags resurface, no duplicates.
        </p>
        <div id="tmTagList" style="display:flex; flex-direction:column; gap:6px; max-height:320px; overflow-y:auto; margin-bottom:12px;"></div>
        <button onclick="addTmTagRow()"
            style="width:100%; padding:9px; border-radius:3px; background:transparent;
                   border:1px dashed rgba(245,158,11,0.4); color:var(--amber); font-family:inherit;
                   font-size:0.82rem; font-weight:700; cursor:pointer; transition:background 0.15s;"
            onmouseover="this.style.background='rgba(245,158,11,0.08)'" onmouseout="this.style.background='transparent'">
            + Add Tag
        </button>
        <div style="margin-top:12px; display:flex; gap:8px;">
            <button onclick="saveTmTags()"
                style="flex:1; padding:10px; border-radius:3px; background:#10b981; color:#000;
                       border:none; font-family:inherit; font-size:0.85rem; font-weight:700; cursor:pointer;">
                &#10003; Save Tags
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ MAP RUN FRAME LIGHTBOX ═══ -->
<div id="mr-lightbox-modal" class="modal-overlay" style="z-index:9000;">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('mr-lightbox-modal')">&times;</button>
        <img id="mr-lightbox-img" src="" alt="">
    </div>
</div>

<!-- ═══════════════ NF ENTITY PEEK MODAL (z:5000) ═══ -->
<div id="nf-peek-modal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('nf-peek-modal')">&times;</button>
        <div id="nf-peek-body">
            <div class="peek-loading"><div class="peek-spinner"></div> Loading preview...</div>
        </div>
    </div>
</div>

<!-- photoswipe container -->
<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
// ═══════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════
const SHOW_REVIEWED = <?= $showReviewed ? 'true' : 'false' ?>;
let currentPage     = <?= $currentPage ?>;
let totalPages      = 1;
let allTags         =[];       // {id, name} from DB (show_in_ui=1)
let excludedTagIds  = new Set(); // tags excluded from the current run config
let frameData       =[];       // current page rows

// ── Map Run state ──
let runMode           = 'frame-range'; // 'frame-range' | 'map-run' | 'narratives'
let mrPage            = 1;
let mrTotalPages      = 1;
let mrTotalRuns       = 0;
let mrSearch          = '';
let mrDebounceTimer   = null;
let selectedMapRunId  = null;
let selectedMapRunFrames = []; //[{frame_id, filename, sketch_id, entity_id}]
let markedFrameIds    = new Set(); // frame IDs explicitly marked for this run
let mrSwiperInstance  = null;

// ── Narratives Filter state ──
let nfDocsLoaded      = false;
let nfCurrentCat      = '';  // active category in the items column

// ── Gallery Preview state ──
let gpSelectedFrameIds = new Set();  // frame IDs selected in gallery preview
let gpAllFrames        =[];         // all resolved frames for current preview
let gpPendingPayload   = null;       // run payload waiting for gallery confirmation

// ═══════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadPage(currentPage);
    loadTagsForRun();
    loadStagedCount();
    loadDocSources();
});

// ═══════════════════════════════════════════════════════
// LOAD PAGE
// ═══════════════════════════════════════════════════════
function loadPage(page) {
    document.getElementById('loadingOverlay').classList.add('visible');
    fetch(`taggeranger_api.php?action=get_staged_frames&page=${page}&per_page=20&reviewed=${SHOW_REVIEWED ? 1 : 0}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                frameData  = res.data;
                totalPages = res.meta.total_pages;
                currentPage = res.meta.current_page;
                renderFrameList(res.data);
                renderPagination();
            } else {
                Toast.show(res.message || 'Error loading frames', 'error');
            }
        })
        .finally(() => {
            document.getElementById('loadingOverlay').classList.remove('visible');
        });
}

function loadStagedCount() {
    fetch('taggeranger_api.php?action=staged_count')
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                const el = document.getElementById('stagedCount');
                el.innerHTML = '<strong>' + res.total + '</strong> st &nbsp;|&nbsp; <strong>' + res.reviewed + '</strong> rw &nbsp;|&nbsp; <strong>' + res.pending + '</strong> pdg';
            }
        });
}

// ═══════════════════════════════════════════════════════
// RENDER FRAME LIST
// ═══════════════════════════════════════════════════════
function renderFrameList(rows) {
    const list  = document.getElementById('frameList');
    const empty = document.getElementById('emptyState');
    const bar   = document.getElementById('confirmAllBar');

    if (!rows || rows.length === 0) {
        list.innerHTML = '';
        empty.style.display = 'block';
        bar.classList.remove('visible');
        return;
    }
    empty.style.display = 'none';
    list.innerHTML = '';

    rows.forEach(row => {
        const div = document.createElement('div');
        div.className = 'frame-row' + (row.reviewed ? ' reviewed-row' : '');
        div.dataset.frameId = row.frame_id;

        // Build tag chips
        let chipsHtml = '';
        if (row.proposals && row.proposals.length > 0) {
            row.proposals.forEach(p => {
                const isOn = p.active ? 'on' : 'off';
                const scoreLabel = p.score ? '<span class="chip-score">' + Math.round(p.score * 100) + '%</span>' : '';
                chipsHtml += `<span class="tag-chip ${isOn}" data-staged-id="${p.staged_id}" data-frame-id="${row.frame_id}" onclick="toggleChip(this)">${p.tag_name}${scoreLabel}</span>`;
            });
        } else {
            chipsHtml = '<span class="no-proposals">No proposals — run auto-tagger to generate</span>';
        }

        // Best score for the score bar
        const bestScore = row.proposals && row.proposals.length > 0
            ? Math.max(...row.proposals.map(p => p.score || 0))
            : 0;

        div.innerHTML = `
            <div class="frame-thumb">
                <div class="score-bar"><div class="score-fill" style="width:${Math.round(bestScore*100)}%"></div></div>
                <a href="${row.filename}" data-pswp-width="1024" data-pswp-height="1024" target="_blank" class="pswp-trigger">
                    <img src="${row.filename}" loading="lazy" alt="Frame ${row.frame_id}">
                </a>
                <div class="frame-meta-overlay">
                    <span class="frame-id-badge">#${row.frame_id}</span>
                    <button class="frame-edit-btn" onclick="openEntityForm(${row.entity_id}, '${row.entity_type}')" title="Edit entity">&#9998;</button>
                </div>
            </div>
            <div class="frame-tags" id="tags_${row.frame_id}">${chipsHtml}</div>
            <div class="frame-review">
                <input type="checkbox" class="review-check" ${row.reviewed ? 'checked' : ''}
                    onchange="toggleReviewed(${row.frame_id}, this.checked)" title="Mark reviewed">
            </div>`;

        list.appendChild(div);
    });

    // Show/update the Confirm All bar
    updateConfirmAllBar();

    // Init PhotoSwipe for this page
    initPhotoSwipe();
}

// ═══════════════════════════════════════════════════════
// CONFIRM ALL BAR
// ═══════════════════════════════════════════════════════

// Update label + show/hide based on how many unreviewed rows remain on this page
function updateConfirmAllBar() {
    const bar        = document.getElementById('confirmAllBar');
    const countEl   = document.getElementById('confirmAllCount');
    const btn        = document.getElementById('confirmAllBtn');

    // Count frame rows that are NOT yet reviewed (no .reviewed-row class AND checkbox unchecked)
    const unreviewed = document.querySelectorAll('.frame-row:not(.reviewed-row) .review-check:not(:checked)').length;

    if (unreviewed === 0) {
        bar.classList.remove('visible');
        return;
    }

    countEl.textContent = unreviewed;
    bar.classList.add('visible');
    btn.disabled = false;
}

// Mark every visible unreviewed frame as reviewed, same logic as individual toggleReviewed()
function confirmAllReviewed() {
    const btn = document.getElementById('confirmAllBtn');
    btn.disabled = true;
    btn.textContent = '⌛ Confirming…';

    // Collect all unchecked checkboxes currently visible
    const unchecked = Array.from(
        document.querySelectorAll('.frame-row:not(.reviewed-row) .review-check:not(:checked)')
    );

    if (unchecked.length === 0) {
        updateConfirmAllBar();
        return;
    }

    // Fire all requests in parallel
    const promises = unchecked.map(checkbox => {
        const row     = checkbox.closest('.frame-row');
        const frameId = parseInt(row.dataset.frameId);
        return fetch('taggeranger_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_reviewed', frame_id: frameId, reviewed: 1 })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                checkbox.checked = true;
                if (!SHOW_REVIEWED) {
                    // Fade out like individual toggleReviewed does
                    row.style.transition = 'opacity 0.35s, transform 0.35s';
                    row.style.opacity    = '0';
                    row.style.transform  = 'translateX(20px)';
                    setTimeout(() => row.remove(), 370);
                } else {
                    row.classList.add('reviewed-row');
                }
            }
        });
    });

    Promise.all(promises).then(() => {
        loadStagedCount();
        // Small delay so fade-out animations finish before hiding the bar
        setTimeout(updateConfirmAllBar, 450);
        btn.innerHTML = '&#10003; Confirm All';
    });
}

// ═══════════════════════════════════════════════════════
// CHIP TOGGLE
// ═══════════════════════════════════════════════════════
function toggleChip(chip) {
    const stagedId = chip.dataset.stagedId;
    const isOn     = chip.classList.contains('on');
    const newState = isOn ? 0 : 1;

    chip.classList.toggle('on', !isOn);
    chip.classList.toggle('off', isOn);

    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle_staged', staged_id: parseInt(stagedId), active: newState })
    }).then(r => r.json()).then(res => {
        if (res.status !== 'success') {
            // Revert on failure
            chip.classList.toggle('on', isOn);
            chip.classList.toggle('off', !isOn);
            Toast.show('Failed to update', 'error');
        }
    });
}

// ═══════════════════════════════════════════════════════
// REVIEWED TOGGLE
// ═══════════════════════════════════════════════════════
function toggleReviewed(frameId, checked) {
    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'set_reviewed', frame_id: frameId, reviewed: checked ? 1 : 0 })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            const row = document.querySelector(`.frame-row[data-frame-id="${frameId}"]`);
            if (row) {
                if (checked && !SHOW_REVIEWED) {
                    // Fade out and remove from unreviewed view
                    row.style.transition = 'opacity 0.4s, transform 0.4s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        row.remove();
                        updateConfirmAllBar();
                    }, 420);
                } else {
                    row.classList.toggle('reviewed-row', checked);
                    updateConfirmAllBar();
                }
            }
            loadStagedCount();
        } else {
            Toast.show('Failed to update', 'error');
        }
    });
}

// ═══════════════════════════════════════════════════════
// PAGINATION
// ═══════════════════════════════════════════════════════
function renderPagination() {
    const bar = document.getElementById('paginationBar');
    if (totalPages <= 1) { bar.innerHTML = ''; return; }

    let html = '';
    html += `<button class="pg-btn" onclick="goPage(${currentPage-1})" ${currentPage<=1?'disabled':''}>&#8592;</button>`;

    // Show window of pages
    const start = Math.max(1, currentPage - 3);
    const end   = Math.min(totalPages, currentPage + 3);
    if (start > 1) html += `<button class="pg-btn" onclick="goPage(1)">1</button><span class="pg-info">...</span>`;
    for (let i = start; i <= end; i++) {
        html += `<button class="pg-btn ${i===currentPage?'current':''}" onclick="goPage(${i})">${i}</button>`;
    }
    if (end < totalPages) html += `<span class="pg-info">...</span><button class="pg-btn" onclick="goPage(${totalPages})">${totalPages}</button>`;

    html += `<button class="pg-btn" onclick="goPage(${currentPage+1})" ${currentPage>=totalPages?'disabled':''}>&#8594;</button>`;
    html += `<span class="pg-info">${currentPage} / ${totalPages}</span>`;
    bar.innerHTML = html;
}

function goPage(p) {
    if (p < 1 || p > totalPages) return;
    const url = new URL(window.location.href);
    url.searchParams.set('p', p);
    url.searchParams.set('reviewed', SHOW_REVIEWED ? '1' : '0');
    window.location.href = url.toString();
}

// ═══════════════════════════════════════════════════════
// RUN MODAL
// ═══════════════════════════════════════════════════════
function loadTagsForRun() {
    fetch('taggeranger_api.php?action=get_tags')
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                allTags = res.data;
                renderRunTagChips();
            }
        });
}

function renderRunTagChips() {
    const wrap = document.getElementById('runTagChips');
    if (allTags.length === 0) {
        wrap.innerHTML = '<span class="no-tags-hint">No tags defined yet &mdash; add tags in Taggerang first.</span>';
        return;
    }
    wrap.innerHTML = '';
    allTags.forEach(tag => {
        const chip = document.createElement('span');
        chip.className = 'rtag-chip' + (excludedTagIds.has(tag.id) ? ' excluded' : '');
        chip.textContent = tag.name;
        chip.dataset.tagId = tag.id;
        chip.addEventListener('click', () => {
            if (excludedTagIds.has(tag.id)) excludedTagIds.delete(tag.id);
            else excludedTagIds.add(tag.id);
            chip.classList.toggle('excluded', excludedTagIds.has(tag.id));
        });
        wrap.appendChild(chip);
    });
}

function openRunModal() {
    excludedTagIds.clear();
    renderRunTagChips();
    // Reset to current mode visually
    switchRunMode(runMode);
    document.getElementById('run-modal').style.display = 'flex';
    if (runMode === 'map-run' && document.getElementById('mrList').children.length <= 1) {
        loadMapRuns(1);
    }
    if (runMode === 'narratives' && !nfDocsLoaded) {
        loadNarrativeDocs();
    }
}

// ═══════════════════════════════════════════════════════
// RUN MODE SWITCH
// ═══════════════════════════════════════════════════════
function switchRunMode(mode) {
    runMode = mode;

    // Tab active states
    document.getElementById('tab-frame-range').classList.toggle('active', mode === 'frame-range');
    document.getElementById('tab-map-run').classList.toggle('active', mode === 'map-run');
    document.getElementById('tab-narratives').classList.toggle('active', mode === 'narratives');

    // Panel visibility
    document.getElementById('frameRangePanel').style.display  = mode === 'frame-range' ? 'block' : 'none';
    document.getElementById('mapRunsPanel').style.display     = mode === 'map-run'      ? 'block' : 'none';
    document.getElementById('narrativesPanel').style.display  = mode === 'narratives'   ? 'block' : 'none';

    // Skip validation visible for map-run and narratives (not frame-range)
    document.getElementById('skipValidationRow').style.display =
        (mode === 'map-run' || mode === 'narratives') ? 'block' : 'none';

    // Two-step: show "Preview Frames" button for map-run and narratives modes;
    // hide direct fire button for those modes (fire comes from gallery preview footer)
    const isTwoStep = (mode === 'map-run' || mode === 'narratives');
    document.getElementById('filterSubmitBtn').style.display = isTwoStep ? '' : 'none';
    document.getElementById('fireBtn').style.display         = isTwoStep ? 'none' : '';

    if (mode === 'map-run' && document.getElementById('mrList').children.length <= 1) {
        loadMapRuns(1);
    }

    // Load docs when narratives tab opens for the first time
    if (mode === 'narratives' && !nfDocsLoaded) {
        loadNarrativeDocs();
    }
}

// ═══════════════════════════════════════════════════════
// MAP RUNS LOADING
// ═══════════════════════════════════════════════════════
function debouncedMrSearch() {
    clearTimeout(mrDebounceTimer);
    mrDebounceTimer = setTimeout(() => {
        mrSearch = document.getElementById('mrSearchInput').value.trim();
        loadMapRuns(1);
    }, 320);
}

function mrGoPage(p) {
    p = Math.max(1, Math.min(mrTotalPages, parseInt(p) || 1));
    loadMapRuns(p);
}

function loadMapRuns(page) {
    mrPage = Math.max(1, page || 1);
    const limit  = 15;
    const offset = (mrPage - 1) * limit;
    const url = `taggeranger_api.php?action=get_map_runs&limit=${limit}&offset=${offset}&search=${encodeURIComponent(mrSearch)}`;

    const list = document.getElementById('mrList');
    list.innerHTML = '<div style="font-size:0.75rem; color:var(--text-muted); padding:8px; font-style:italic;">Loading...</div>';

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            list.innerHTML = '';

            mrTotalRuns  = res.total || 0;
            mrTotalPages = Math.max(1, Math.ceil(mrTotalRuns / limit));

            if (res.data.length === 0) {
                list.innerHTML = '<div style="font-size:0.75rem; color:var(--text-muted); padding:8px; font-style:italic;">No map runs found.</div>';
            } else {
                res.data.forEach(run => {
                    const item = document.createElement('div');
                    item.className = 'mr-item' + (run.id === selectedMapRunId ? ' selected' : '');
                    item.dataset.id = run.id;
                    item.innerHTML = `
                        <div class="mr-item-id">Run #${run.id}</div>
                        <div class="mr-item-meta">${run.note ? escHtml(run.note) : '<em style="opacity:0.5">no note</em>'} &mdash; ${run.created_at}</div>
                        <div class="mr-item-count">${run.frame_count} fr</div>`;
                    item.addEventListener('click', () => selectMapRun(run.id));
                    list.appendChild(item);
                });
            }

            // Update pagination bar
            const pg = document.getElementById('mrPagination');
            pg.style.display = 'flex';
            document.getElementById('mrPgIndex').value = mrPage;
            document.getElementById('mrPgIndex').max   = mrTotalPages;
            document.getElementById('mrPgTotal').textContent = `/ ${mrTotalPages} \u00a0\u2022\u00a0 ${mrTotalRuns} runs`;
            document.getElementById('mrPgPrev').disabled = mrPage <= 1;
            document.getElementById('mrPgNext').disabled = mrPage >= mrTotalPages;
        });
}

function selectMapRun(runId) {
    selectedMapRunId = runId;
    markedFrameIds.clear();

    // Highlight selection in list
    document.querySelectorAll('.mr-item').forEach(el => {
        el.classList.toggle('selected', parseInt(el.dataset.id) === runId);
    });

    // Load frames for this run
    fetch(`taggeranger_api.php?action=get_map_run_frames&map_run_id=${runId}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') { Toast.show('Error loading run frames', 'error'); return; }
            selectedMapRunFrames = res.data;

            // Pre-mark all frames by default
            selectedMapRunFrames.forEach(f => markedFrameIds.add(f.frame_id));

            renderMrFrameSwiper();
            updateMrSelectionBar();

            document.getElementById('mrSwiperWrap').classList.add('visible');
            document.getElementById('mrSelectionBar').style.display = 'flex';
            document.getElementById('mrSwiperRunLabel').textContent = `#${runId} (${res.data.length} frames)`;
        });
}

function renderMrFrameSwiper() {
    const inner = document.getElementById('mrFrameSwiperInner');
    inner.innerHTML = '';

    selectedMapRunFrames.forEach(f => {
        const slide = document.createElement('div');
        slide.className = 'swiper-slide';

        const isMarked = markedFrameIds.has(f.frame_id);
        slide.innerHTML = `
            <div class="mr-frame-card ${isMarked ? 'marked' : ''}" data-fid="${f.frame_id}">
                <img src="${escHtml(f.filename)}" loading="lazy" alt="Frame #${f.frame_id}"
                     onclick="openMrLightbox('${escHtml(f.filename)}', event)">
                <div class="mr-frame-card-label">#${f.frame_id}${f.sketch_name ? ' · ' + escHtml(f.sketch_name.substring(0,18)) : ''}</div>
            </div>`;

        // Toggle mark on card click (but not on the img directly — handled separately)
        slide.querySelector('.mr-frame-card').addEventListener('click', (e) => {
            if (e.target.tagName === 'IMG') return; // let lightbox handle
            toggleMrFrameMark(f.frame_id, slide.querySelector('.mr-frame-card'));
        });

        inner.appendChild(slide);
    });

    // Re-init swiper
    if (mrSwiperInstance) { mrSwiperInstance.destroy(true, true); }
    mrSwiperInstance = new Swiper('#mrFrameSwiper', {
        slidesPerView: 'auto',
        spaceBetween: 8,
        freeMode: true,
        navigation: {
            nextEl: '#mrFrameSwiper .swiper-button-next',
            prevEl: '#mrFrameSwiper .swiper-button-prev'
        },
        scrollbar: { el: '#mrFrameSwiper .swiper-scrollbar', hide: true },
        slidesOffsetBefore: 4,
        slidesOffsetAfter: 4
    });
}

function toggleMrFrameMark(frameId, cardEl) {
    if (markedFrameIds.has(frameId)) {
        markedFrameIds.delete(frameId);
        cardEl.classList.remove('marked');
    } else {
        markedFrameIds.add(frameId);
        cardEl.classList.add('marked');
    }
    updateMrSelectionBar();
}

function toggleMarkAllFrames() {
    const allMarked = markedFrameIds.size === selectedMapRunFrames.length;
    if (allMarked) {
        markedFrameIds.clear();
    } else {
        selectedMapRunFrames.forEach(f => markedFrameIds.add(f.frame_id));
    }
    // Refresh swiper cards
    document.querySelectorAll('.mr-frame-card').forEach(card => {
        const fid = parseInt(card.dataset.fid);
        card.classList.toggle('marked', markedFrameIds.has(fid));
    });
    updateMrSelectionBar();
}

function onTagAllFramesToggle() {
    updateMrSelectionBar();
}

function updateMrSelectionBar() {
    const info = document.getElementById('mrSelInfo');
    const markBtn = document.getElementById('mrMarkAllBtn');
    const allMarked = selectedMapRunFrames.length > 0 && markedFrameIds.size === selectedMapRunFrames.length;

    info.textContent = `${markedFrameIds.size} / ${selectedMapRunFrames.length} frames selected`;
    markBtn.textContent = allMarked ? '✕ Unmark All' : '✓ Mark All';
}

function openMrLightbox(src, e) {
    e.stopPropagation();
    document.getElementById('mr-lightbox-img').src = src;
    document.getElementById('mr-lightbox-modal').style.display = 'flex';
}

// ═══════════════════════════════════════════════════════
// NARRATIVES FILTER PANEL
// ═══════════════════════════════════════════════════════

function loadNarrativeDocs() {
    fetch('taggeranger_api.php?action=get_narrative_docs')
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            nfDocsLoaded = true;
            const sel = document.getElementById('nfContextDoc');
            res.data.forEach(d => {
                const o = document.createElement('option');
                o.value = d.id;
                o.textContent = d.name;
                sel.appendChild(o);
            });
            sel.addEventListener('change', () => {
                // Reset item list and pot on doc change
                document.getElementById('nfCatList').innerHTML = '<div style="padding:8px; color:var(--text-muted); font-size:0.75rem;">Loading...</div>';
                document.getElementById('nfItemList').innerHTML = '';
                document.getElementById('nfPotList').innerHTML = '';
                updateNfSelBar();
                loadNfCats();
            });
        });
}

function loadNfCats() {
    const docId = document.getElementById('nfContextDoc').value;
    if (!docId) return;
    fetch(`narratives_api.php?action=get_filter_cats&doc_id=${docId}`)
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('nfCatList');
            list.innerHTML = '';
            (res.data ||[]).forEach(cat => {
                const el = document.createElement('div');
                el.className = 'nf-item';
                el.textContent = cat;
                el.addEventListener('click', () => loadNfItems(cat, el));
                list.appendChild(el);
            });
        });
}

function loadNfItems(cat, catEl) {
    const docId = document.getElementById('nfContextDoc').value;
    nfCurrentCat = cat;
    // Highlight active category
    document.querySelectorAll('#nfCatList .nf-item').forEach(el => el.classList.remove('active'));
    if (catEl) catEl.classList.add('active');

    const list = document.getElementById('nfItemList');
    list.innerHTML = '<div style="padding:8px; color:var(--text-muted); font-size:0.75rem;">Loading...</div>';

    fetch(`narratives_api.php?action=get_filter_items&doc_id=${docId}&cat=${encodeURIComponent(cat)}`)
        .then(r => r.json())
        .then(res => {
            list.innerHTML = '';
            (res.data ||[]).forEach(item => {
                const label = typeof item === 'string' ? item : (item.name || item.title || 'Unknown');
                const el = document.createElement('div');
                el.className = 'nf-item';
                el.style.cursor = 'default';  // clicking the row text still adds, but we separate peek btn
                el.dataset.cat  = cat;
                el.dataset.name = label;

                // Item label (clicking it adds to pot)
                const labelSpan = document.createElement('span');
                labelSpan.textContent = label;
                labelSpan.style.cssText = 'flex:1; cursor:pointer; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;';
                labelSpan.addEventListener('click', () => addNfPotItem(label, cat));

                // Controls: peek button
                const controls = document.createElement('div');
                controls.className = 'nf-item-controls';

                const peekBtn = document.createElement('button');
                peekBtn.className = 'nf-peek-btn';
                peekBtn.title = 'Peek at this entity';
                peekBtn.innerHTML = '👁';
                peekBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    nfPeekEntity(label, cat);
                });

                controls.appendChild(peekBtn);
                el.appendChild(labelSpan);
                el.appendChild(controls);
                list.appendChild(el);
            });
        });
}

function addNfPotItem(name, cat) {
    const potList = document.getElementById('nfPotList');

    // Prevent duplicates
    const existing = Array.from(potList.querySelectorAll('.nf-pot-item')).map(el => el.dataset.name);
    if (existing.includes(name)) return;

    // Remove placeholder text if present
    const placeholder = potList.querySelector('[style*="font-style:italic"]');
    if (placeholder) placeholder.remove();

    const el = document.createElement('div');
    el.className = 'nf-item nf-pot-item';
    el.dataset.cat  = cat;
    el.dataset.name = name;
    el.innerHTML = `${escHtml(name)} <span class="nf-remove" onclick="this.parentElement.remove(); updateNfSelBar();">×</span>`;
    potList.appendChild(el);
    updateNfSelBar();
}

function updateNfSelBar() {
    const count = document.getElementById('nfPotList').querySelectorAll('.nf-pot-item').length;
    const bar   = document.getElementById('nfSelectionBar');
    bar.style.display = count > 0 ? 'flex' : 'none';
    document.getElementById('nfSelInfo').textContent = `${count} item${count !== 1 ? 's' : ''} in filter pot`;
}

// ═══════════════════════════════════════════════════════
// NF PEEK MODAL (port from narratives.php)
// ═══════════════════════════════════════════════════════
function nfPeekEntity(name, cat) {
    const docId = document.getElementById('nfContextDoc').value;
    const body  = document.getElementById('nf-peek-body');

    body.innerHTML = '<div class="peek-loading"><div class="peek-spinner"></div> Loading preview...</div>';
    document.getElementById('nf-peek-modal').style.display = 'flex';

    if (!docId) {
        body.innerHTML = '<div class="peek-not-found">No context document selected.</div>';
        return;
    }

    fetch(`narratives_api.php?action=get_entity_preview&doc_id=${encodeURIComponent(docId)}&cat=${encodeURIComponent(cat)}&name=${encodeURIComponent(name)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                renderNfPeek(res.data, name, cat, body);
            } else {
                body.innerHTML = `<div class="peek-not-found">
                    <div style="font-size:1.8rem; margin-bottom:8px;">🔍</div>
                    <div><strong>${escHtml(name)}</strong></div>
                    <div style="margin-top:6px; font-size:0.8rem;">${res.message || 'No detailed data found.'}</div>
                </div>`;
            }
        })
        .catch(err => {
            body.innerHTML = `<div class="peek-not-found">Failed to load: ${escHtml(err.message)}</div>`;
        });
}

function renderNfPeek(data, name, cat, container) {
    let html = '';

    html += `<div class="peek-header">
        <div>
            <h3 style="margin:0; font-size:1.15rem; line-height:1.3;">${escHtml(data.name || name)}</h3>
            ${data.roles && data.roles.length ? `<div style="margin-top:5px;">${data.roles.map(r => `<span class="peek-pill">${escHtml(r)}</span>`).join(' ')}</div>` : ''}
        </div>
        <span class="peek-cat-badge">${escHtml(cat)}</span>
    </div>`;

    if (data.aliases && data.aliases.length > 0) {
        html += `<div class="peek-section">
            <div class="peek-section-title">Also Known As</div>
            <div class="peek-pill-row">${data.aliases.map(a => `<span class="peek-pill">${escHtml(String(a))}</span>`).join('')}</div>
        </div>`;
    }

    if (data.attributes && typeof data.attributes === 'object') {
        const attrs = Object.entries(data.attributes).filter(([, v]) => v !== null && v !== undefined && v !== '');
        const longFields =['description','summary','backstory','purpose','function','personality','motivation',
                            'production_notes','significance','visual','appearance','logline','act_structure'];
        const longPairs  = attrs.filter(([k]) => longFields.includes(k));
        const shortPairs = attrs.filter(([k]) => !longFields.includes(k));

        longPairs.forEach(([k, v]) => {
            const display = peekRenderAttr(v);
            if (!display) return;
            html += `<div class="peek-section">
                <div class="peek-section-title">${escHtml(k.replace(/_/g,' '))}</div>
                <div class="peek-value">${display}</div>
            </div>`;
        });

        if (shortPairs.length > 0) {
            html += `<div class="peek-section"><div class="peek-section-title">Details</div><div class="peek-kv-grid">`;
            shortPairs.forEach(([k, v]) => {
                const display = peekRenderAttr(v);
                if (!display) return;
                html += `<div><div class="peek-kv-key">${escHtml(k.replace(/_/g,' '))}</div><div class="peek-kv-val">${display}</div></div>`;
            });
            html += `</div></div>`;
        }
    }

    if (data.relationships && data.relationships.length > 0) {
        html += `<div class="peek-section"><div class="peek-section-title">Relationships</div><div style="display:flex;flex-direction:column;gap:5px;">`;
        data.relationships.slice(0, 8).forEach(r => {
            html += `<div style="font-size:0.84rem;padding:4px 10px;background:rgba(0,0,0,0.15);border-radius:4px;border-left:2px solid var(--border);">
                <span style="font-weight:700;">${escHtml(r.target||'')}</span>
                ${r.type ? `<span style="color:var(--text-muted);margin-left:5px;font-size:0.76rem;">(${escHtml(r.type)})</span>` : ''}
                ${(r.desc||r.nature) ? `<div style="color:var(--text-muted);font-size:0.78rem;margin-top:2px;">${escHtml(r.desc||r.nature)}</div>` : ''}
            </div>`;
        });
        if (data.relationships.length > 8) html += `<div style="font-size:0.75rem;color:var(--text-muted);padding:4px 10px;">+ ${data.relationships.length-8} more…</div>`;
        html += `</div></div>`;
    }

    if (data.timeline && data.timeline.length > 0) {
        html += `<div class="peek-section"><div class="peek-section-title">History / Timeline</div><div style="display:flex;flex-direction:column;gap:4px;">`;
        data.timeline.slice(0,6).forEach(t => {
            const date = t.date ? `<span style="font-family:monospace;font-size:0.72rem;color:var(--text-muted);margin-right:6px;">[${escHtml(String(t.date))}]</span>` : '';
            html += `<div style="font-size:0.83rem;padding:3px 10px;border-left:2px solid rgba(245,158,11,0.3);">${date}${escHtml(t.text||'')}</div>`;
        });
        if (data.timeline.length > 6) html += `<div style="font-size:0.76rem;color:var(--text-muted);padding:4px 10px;">+ ${data.timeline.length-6} more…</div>`;
        html += `</div></div>`;
    }

    const hasContent = (data.attributes && Object.keys(data.attributes).length)
                    || (data.relationships && data.relationships.length)
                    || (data.timeline && data.timeline.length)
                    || (data.roles && data.roles.length);
    if (!hasContent) {
        html += `<div class="peek-not-found" style="padding-top:10px;">No additional details available.</div>`;
    }

    container.innerHTML = html;
}

function peekRenderAttr(v) {
    if (v === null || v === undefined || v === '') return '';
    if (typeof v === 'string')  return escHtml(v);
    if (typeof v === 'number' || typeof v === 'boolean') return escHtml(String(v));
    if (Array.isArray(v)) {
        if (v.length === 0) return '';
        if (v.every(i => typeof i === 'string' || typeof i === 'number')) {
            return `<div class="peek-pill-row">${v.map(i => `<span class="peek-pill">${escHtml(String(i))}</span>`).join('')}</div>`;
        }
        return `<pre style="font-size:0.72rem;color:var(--text-muted);white-space:pre-wrap;word-break:break-word;margin:0;">${escHtml(JSON.stringify(v,null,2))}</pre>`;
    }
    if (typeof v === 'object') {
        return `<pre style="font-size:0.72rem;color:var(--text-muted);white-space:pre-wrap;word-break:break-word;margin:0;">${escHtml(JSON.stringify(v,null,2))}</pre>`;
    }
    return escHtml(String(v));
}

// ═══════════════════════════════════════════════════════
// TWO-STEP: GALLERY PREVIEW
// Opens a full-frame grid gallery so user can deselect false positives
// before hitting the final run.
// ═══════════════════════════════════════════════════════
function openGalleryPreview() {
    const activeTags = allTags.filter(t => !excludedTagIds.has(t.id));
    if (activeTags.length === 0) { Toast.show('No tags selected for run', 'warn'); return; }

    // Build the same payload we'd fire, just to resolve the frame set
    const threshold = parseFloat(document.getElementById('thresholdSlider').value);
    const maxTags   = parseInt(document.getElementById('runMaxTags').value) || 5;
    let payload = {
        tag_ids: activeTags.map(t => t.id),
        threshold,
        max_tags_per_frame: maxTags
    };

    if (runMode === 'map-run') {
        if (!selectedMapRunId) { Toast.show('Please select a map run first', 'warn'); return; }
        if (markedFrameIds.size === 0) { Toast.show('No frames marked', 'warn'); return; }

        const tagAllFrames   = document.getElementById('mrTagAllFrames').checked;
        const skipValidation = document.getElementById('skipValidation').checked;
        payload.map_run_id               = selectedMapRunId;
        payload.frame_ids                = Array.from(markedFrameIds);
        payload.tag_all_frames_of_sketch = tagAllFrames;
        payload.skip_validation          = skipValidation;

        // For map-run we already have frame data
        const framesForPreview = selectedMapRunFrames.filter(f => markedFrameIds.has(f.frame_id));
        _openGalleryPreviewWithFrames(framesForPreview, payload, `Map Run #${selectedMapRunId}`);

    } else if (runMode === 'narratives') {
        const nfDocId = document.getElementById('nfContextDoc').value;
        if (!nfDocId) { Toast.show('Please select a context document', 'warn'); return; }

        const potItems = Array.from(document.querySelectorAll('#nfPotList .nf-pot-item')).map(el => ({
            cat:  el.dataset.cat,
            name: el.dataset.name
        }));
        const freeText = document.getElementById('nfFreeText').value.trim();

        if (potItems.length === 0 && !freeText) {
            Toast.show('Add at least one item to the filter pot', 'warn'); return;
        }

        const skipValidation     = document.getElementById('skipValidation').checked;
        payload.narratives_doc_id = nfDocId;
        payload.narratives_filter = { text: freeText, items: potItems };
        payload.skip_validation   = skipValidation;

        // For narratives we need to resolve frame IDs from the API first
        _resolveNarrativeFrames(payload, nfDocId, potItems, freeText);
    }
}

// Helper: resolve frames for narratives mode via a lightweight API call
function _resolveNarrativeFrames(payload, docId, potItems, freeText) {
    document.getElementById('filterSubmitBtn').disabled = true;
    document.getElementById('filterSubmitBtn').textContent = '⌛ Resolving frames…';

    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'resolve_narrative_frames',
            narratives_doc_id: docId,
            narratives_filter: { text: freeText, items: potItems }
        })
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('filterSubmitBtn').disabled = false;
        document.getElementById('filterSubmitBtn').innerHTML = '👁 Preview Frames &amp; Select';

        if (res.status !== 'success') {
            Toast.show(res.message || 'Error resolving frames', 'error');
            return;
        }

        const docName = document.getElementById('nfContextDoc').selectedOptions[0]?.text || docId;
        _openGalleryPreviewWithFrames(res.data, payload, `Narratives: ${docName}`);
    })
    .catch(err => {
        document.getElementById('filterSubmitBtn').disabled = false;
        document.getElementById('filterSubmitBtn').innerHTML = '👁 Preview Frames &amp; Select';
        Toast.show('Network error: ' + err.message, 'error');
    });
}

// Helper: open gallery modal with a list of frames
function _openGalleryPreviewWithFrames(frames, payload, label) {
    gpAllFrames        = frames;
    gpSelectedFrameIds = new Set(frames.map(f => f.frame_id));
    gpPendingPayload   = payload;

    document.getElementById('gpMeta').textContent = label;
    document.getElementById('gallery-preview-modal').style.display = 'flex';

    _renderGalleryGrid();
}

function _renderGalleryGrid() {
    const wrap = document.getElementById('gpGridWrap');

    if (gpAllFrames.length === 0) {
        wrap.innerHTML = '<div class="gp-loading" style="color:var(--text-muted);">No frames resolved for the current filter.</div>';
        _updateGpToolbar();
        return;
    }

    const grid = document.createElement('div');
    grid.className = 'gp-grid';

    gpAllFrames.forEach(f => {
        const card = document.createElement('div');
        card.className = 'gp-card' + (gpSelectedFrameIds.has(f.frame_id) ? ' selected' : '');
        card.dataset.fid = f.frame_id;
        card.innerHTML = `
            <img src="${escHtml(f.filename)}" loading="lazy" alt="Frame #${f.frame_id}">
            <div class="gp-card-label">#${f.frame_id}${f.sketch_name ? ' · ' + escHtml(f.sketch_name.substring(0,20)) : ''}</div>`;
        card.addEventListener('click', () => {
            const fid = f.frame_id;
            if (gpSelectedFrameIds.has(fid)) {
                gpSelectedFrameIds.delete(fid);
                card.classList.remove('selected');
            } else {
                gpSelectedFrameIds.add(fid);
                card.classList.add('selected');
            }
            _updateGpToolbar();
        });
        grid.appendChild(card);
    });

    wrap.innerHTML = '';
    wrap.appendChild(grid);
    _updateGpToolbar();
}

function _updateGpToolbar() {
    const total    = gpAllFrames.length;
    const selected = gpSelectedFrameIds.size;
    const allSel   = (total > 0 && selected === total);

    document.getElementById('gpToolbarInfo').innerHTML =
        `<strong>${selected}</strong> / ${total} frames selected &mdash; click to deselect false positives`;
    document.getElementById('gpSelectAllBtn').textContent = allSel ? '✕ Deselect All' : '✓ Select All';
    document.getElementById('gpFooterInfo').innerHTML =
        `<strong>${selected}</strong> frame${selected !== 1 ? 's' : ''} will be tagged.`;
    document.getElementById('gpFireBtn').disabled = selected === 0;
}

function gpToggleSelectAll() {
    const allSel = gpSelectedFrameIds.size === gpAllFrames.length;
    if (allSel) {
        gpSelectedFrameIds.clear();
    } else {
        gpAllFrames.forEach(f => gpSelectedFrameIds.add(f.frame_id));
    }
    // Re-render cards
    document.querySelectorAll('.gp-card').forEach(card => {
        const fid = parseInt(card.dataset.fid);
        card.classList.toggle('selected', gpSelectedFrameIds.has(fid));
    });
    _updateGpToolbar();
}

// Called when the user hits the green "Run" button inside the gallery preview footer
function fireRunFromPreview() {
    if (!gpPendingPayload) { Toast.show('No pending payload', 'error'); return; }
    if (gpSelectedFrameIds.size === 0) { Toast.show('No frames selected', 'warn'); return; }

    // Patch the payload to override frame_ids with gallery selection
    const finalPayload = Object.assign({}, gpPendingPayload, {
        action: 'run_autotag',
        frame_ids: Array.from(gpSelectedFrameIds)
    });

    // For narratives mode we also need to tell the API to use explicit frame_ids
    // (bypass the vector resolution step since we already resolved them)
    if (runMode === 'narratives') {
        finalPayload.use_explicit_frame_ids = true;
    }

    closeModal('gallery-preview-modal');
    closeModal('run-modal');
    _doFireRun(finalPayload);
}

// ═══════════════════════════════════════════════════════
// FIRE RUN — original direct fire (frame-range mode only now)
// ═══════════════════════════════════════════════════════
function fireRun() {
    // Only frame-range mode reaches here now
    const activeTags = allTags.filter(t => !excludedTagIds.has(t.id));
    if (activeTags.length === 0) { Toast.show('No tags selected for run', 'warn'); return; }

    const threshold = parseFloat(document.getElementById('thresholdSlider').value);
    const maxTags   = parseInt(document.getElementById('runMaxTags').value) || 5;

    const fromId = document.getElementById('runFromId').value.trim();
    const toId   = document.getElementById('runToId').value.trim();

    const payload = {
        action: 'run_autotag',
        tag_ids: activeTags.map(t => t.id),
        threshold,
        max_tags_per_frame: maxTags,
        from_id: fromId ? parseInt(fromId) : null,
        to_id:   toId   ? parseInt(toId)   : null
    };

    closeModal('run-modal');
    _doFireRun(payload);
}

// ═══════════════════════════════════════════════════════
// SHARED RUN EXECUTION (streaming SSE)
// ═══════════════════════════════════════════════════════
function _doFireRun(payload) {
    const activeTags = allTags.filter(t => !excludedTagIds.has(t.id));

    document.getElementById('terminal-modal').style.display = 'flex';
    document.getElementById('terminalCloseBtn').disabled = true;
    document.getElementById('terminalDoneBtn').disabled  = true;
    document.getElementById('terminalStats').style.display = 'none';
    document.getElementById('fireBtn').disabled = true;

    const screen = document.getElementById('terminalScreen');
    screen.innerHTML = '';
    const startTime = Date.now();
    let totalFrames = 0, totalProposed = 0;

    function tlog(text, cls) {
        const line = document.createElement('span');
        line.className = 't-line' + (cls ? ' ' + cls : '');
        line.textContent = text;
        screen.appendChild(line);
        screen.scrollTop = screen.scrollHeight;
    }

    tlog('// TAGGERANGER AUTO-TAG RUN', 't-dim');
    tlog('// Tags: ' + activeTags.map(t => t.name).join(', '), 't-dim');
    tlog('// Threshold: ' + payload.threshold + ' | Max per frame: ' + payload.max_tags_per_frame, 't-dim');

    if (payload.map_run_id) {
        tlog('// Mode: Map Run #' + payload.map_run_id + ' | Frames: ' + (payload.frame_ids||[]).length +
             (payload.tag_all_frames_of_sketch ? ' | Tag all frames of sketch: YES' : '') +
             (payload.skip_validation ? ' | SKIP VALIDATION: YES' : ''), 't-dim');
    } else if (payload.narratives_doc_id) {
        const potCount = payload.narratives_filter?.items?.length || 0;
        tlog('// Mode: Narratives Filter | Filter items: ' + potCount +
             (payload.use_explicit_frame_ids ? ' | Frames from gallery: ' + (payload.frame_ids||[]).length : '') +
             (payload.skip_validation ? ' | SKIP VALIDATION: YES' : ''), 't-dim');
    } else {
        tlog('// Mode: Frame Range', 't-dim');
    }

    tlog('', '');
    tlog('Initialising...', 't-bright');

    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
        body: JSON.stringify(payload)
    }).then(response => {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        function read() {
            reader.read().then(({ done, value }) => {
                if (done) {
                    const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
                    document.getElementById('terminalStats').style.display = 'grid';
                    document.getElementById('statFrames').textContent   = totalFrames;
                    document.getElementById('statTags').textContent     = activeTags.length;
                    document.getElementById('statProposed').textContent = totalProposed;
                    document.getElementById('statTime').textContent     = elapsed + 's';
                    document.getElementById('terminalCloseBtn').disabled = false;
                    document.getElementById('terminalDoneBtn').disabled  = false;
                    document.getElementById('fireBtn').disabled = false;
                    tlog('', '');
                    tlog('// RUN COMPLETE — ' + totalProposed + ' proposals staged in ' + elapsed + 's', 't-bright');
                    return;
                }

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop();

                lines.forEach(line => {
                    if (!line.startsWith('data: ')) return;
                    try {
                        const evt = JSON.parse(line.slice(6));
                        if (evt.type === 'frame') {
                            totalFrames++;
                            tlog('[frame #' + evt.frame_id + '] ' + evt.proposals + ' proposal(s)', evt.proposals > 0 ? 't-done' : 't-dim');
                            totalProposed += evt.proposals;
                        } else if (evt.type === 'batch') {
                            tlog('--- batch ' + evt.batch + ' (' + evt.count + ' frames) ---', 't-dim');
                        } else if (evt.type === 'error') {
                            tlog('ERR: ' + evt.message, 't-warn');
                        }
                    } catch(e) {}
                });

                read();
            });
        }
        read();
    }).catch(err => {
        tlog('FATAL: ' + err.message, 't-warn');
        document.getElementById('terminalCloseBtn').disabled = false;
        document.getElementById('fireBtn').disabled = false;
    });
}

function onRunComplete() {
    closeModal('terminal-modal');
    loadStagedCount();
    loadPage(1);
}

// ═══════════════════════════════════════════════════════
// PERSIST
// ═══════════════════════════════════════════════════════
function openPersistModal() {
    fetch('taggeranger_api.php?action=staged_count')
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                document.getElementById('persistCount').textContent = res.reviewed + ' reviewed';
                document.getElementById('persist-modal').style.display = 'flex';
            }
        });
}

function doPersist() {
    closeModal('persist-modal');
    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'persist_staged' })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            Toast.show('&#8659; Persisted ' + res.written + ' tag assignments to tags_2_frames');
            loadStagedCount();
        } else {
            Toast.show(res.message || 'Error persisting', 'error');
        }
    });
}

// ═══════════════════════════════════════════════════════
// MISC
// ═══════════════════════════════════════════════════════
function openEntityForm(entityId, entityType) {
    if (!entityId) return;
    document.getElementById('entity-iframe').src = `entity_form.php?entity_type=${entityType}&entity_id=${entityId}&view=modal`;
    document.getElementById('iframe-modal').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

window.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        if (e.target.id === 'terminal-modal' && document.getElementById('terminalCloseBtn').disabled) return;
        e.target.style.display = 'none';
        if (e.target.id === 'iframe-modal') document.getElementById('entity-iframe').src = '';
    }
});

function initPhotoSwipe() {
    if (typeof PhotoSwipeLightbox === 'undefined') return;
    const lb = new PhotoSwipeLightbox({
        gallery: '#frameList',
        children: 'a.pswp-trigger',
        pswpModule: PhotoSwipe
    });
    lb.init();
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ═══════════════════════════════════════════════════════
// TAG MANAGER (same show_in_ui logic as taggerang.php)
// ═══════════════════════════════════════════════════════
function loadDocSources() {
    fetch('taggeranger_api.php?action=get_doc_sources')
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                const sel = document.getElementById('tmDocSelect');
                res.data.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name;
                    sel.appendChild(opt);
                });
            }
        });
}

function applyDocKeywords(docId) {
    if (!docId) return;
    if (!confirm('This will hide all currently visible tags and replace them with the document keywords. Continue?')) {
        document.getElementById('tmDocSelect').value = "";
        return;
    }
    
    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'apply_doc_keywords', doc_id: docId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            allTags = res.data;
            renderTmTagList();
            renderRunTagChips();
            Toast.show('Tags loaded from document');
        } else {
            Toast.show('Error loading tags', 'error');
        }
        document.getElementById('tmDocSelect').value = ""; // reset
    });
}

function openTagManagerModal() {
    renderTmTagList();
    document.getElementById('tag-manager-modal').style.display = 'flex';
}

function renderTmTagList() {
    const list = document.getElementById('tmTagList');
    list.innerHTML = '';
    if (allTags.length === 0) {
        list.innerHTML = '<div style="font-size:0.78rem; color:var(--text-muted); font-style:italic; padding:8px;">No tags loaded.</div>';
        return;
    }
    allTags.forEach(tag => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:8px; padding:7px 10px; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:3px;';
        row.dataset.id = tag.id;

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.value = tag.name;
        nameInput.className = 'tm-name-input';
        nameInput.style.cssText = 'flex:1; padding:5px 8px; border:1px solid var(--border); border-radius:3px; background:var(--bg); color:var(--text); font-family:inherit; font-size:0.82rem;';

        const hideBtn = document.createElement('button');
        hideBtn.title = 'Hide from UI (does not delete)';
        hideBtn.innerHTML = '&#10005;';
        hideBtn.style.cssText = 'background:transparent; border:none; color:#ef4444; font-size:1.1rem; cursor:pointer; padding:0 4px; line-height:1;';
        hideBtn.addEventListener('click', () => hideTmTag(tag.id, tag.name, hideBtn));

        row.appendChild(nameInput);
        row.appendChild(hideBtn);
        list.appendChild(row);
    });
}

function addTmTagRow() {
    allTags.push({ id: null, name: '' });
    renderTmTagList();
    // Focus the last input
    const inputs = document.querySelectorAll('#tmTagList .tm-name-input');
    if (inputs.length) inputs[inputs.length - 1].focus();
}

function hideTmTag(tagId, tagName, btn) {
    if (!tagId) { allTags = allTags.filter(t => t.id !== tagId); renderTmTagList(); return; }
    btn.disabled = true;
    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'hide_tag', tag_id: tagId })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            allTags = allTags.filter(t => t.id !== tagId);
            renderTmTagList();
            renderRunTagChips();
            Toast.show('"' + tagName + '" hidden from UI');
        } else {
            Toast.show(res.message || 'Error', 'error');
            btn.disabled = false;
        }
    });
}

function clearAllTagsFromUI() {
    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'hide_all_tags' })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            allTags =[];
            renderTmTagList();
            renderRunTagChips();
            Toast.show('All tags hidden from UI');
        }
    });
}

function saveTmTags() {
    const rows = document.querySelectorAll('#tmTagList [data-id]');
    const defs =[];
    rows.forEach(row => {
        const name = row.querySelector('.tm-name-input').value.trim();
        const id   = row.dataset.id || null;
        if (name) defs.push({ id: id ? parseInt(id) : null, name });
    });
    fetch('taggeranger_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_tag_defs', tags: defs })
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            allTags = res.data;
            renderTmTagList();
            renderRunTagChips();
            closeModal('tag-manager-modal');
            Toast.show('Tags saved');
        } else {
            Toast.show(res.message || 'Error', 'error');
        }
    });
}
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php echo $eruda ?? ''; ?>
</body>
</html>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
