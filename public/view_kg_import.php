<?php
// public/view_kg_import.php
// KG Import Bridge — Promotes md_doc_analysis entities into kg_nodes
// -----------------------------------------------------------------------
// Reuses the Advanced Filter UI pattern from auto_narratives.php:
//   Col 1: Category browser
//   Col 2: Entity items (with Peek preview)
//   Col 3: Staging pot → right panel shows promotion cards
//
// On Promote: calls kg_import_api.php which:
//   1. Calls md_filter_entity_enricher_v1 to author MD content
//   2. Creates kg_nodes row
//   3. Creates kg_node_items back-link to source doc
//
// UI: Hamburger + flyout left column (matches kg_view.php pattern)
// -----------------------------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = 'KG Import Bridge';

// Load docs that have analysis
$docsRaw = $pdo->query("
    SELECT d.id, d.name
    FROM documentations d
    JOIN md_doc_analysis da ON d.id = da.doc_id
    WHERE d.is_active = 1
    ORDER BY d.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Load KG categories for folder assignment
$kgCats = $pdo->query("
    SELECT id, name FROM kg_categories ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
/* ── Variables ── */
:root {
    --imp-bg: #0c0c0c;
    --imp-card: #161616;
    --imp-border: #2a2a2a;
    --imp-accent: #8b5cf6;
    --imp-green: #10b981;
    --imp-amber: #f59e0b;
    --imp-muted: #666;
    --imp-text: #e5e5e5;
    --sidebar-w: 280px;
}

html, body { height: 100%; margin: 0; overflow: hidden; background: var(--imp-bg); color: var(--imp-text); }

/* ── Main Layout ── */
.imp-layout {
    display: flex;
    flex-direction: column;
    height: 100vh;
    width: 100vw;
}

/* ── Top Bar ── */
.imp-topbar {
    height: 52px;
    background: #1a1a1a;
    border-bottom: 1px solid var(--imp-border);
    display: flex;
    align-items: center;
    padding: 0 14px;
    gap: 12px;
    flex-shrink: 0;
    position: relative;
    z-index: 10;
}
.imp-topbar-left {
    margin-left: 58px; /* room for hamburger */
    display: flex;
    align-items: center;
    gap: 10px;
}
.imp-topbar-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--imp-accent);
    display: flex;
    align-items: center;
    gap: 8px;
}
.imp-topbar-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

/* ── Hamburger (fixed, always visible) ── */
.imp-hamburger {
    position: fixed;
    top: 8px;
    left: 70px;
    z-index: 1100;
    width: 38px; height: 38px;
    background: #1a1a1a;
    border: 1px solid var(--imp-border);
    border-radius: 8px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 5px; cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,.3);
    transition: background 0.2s;
}
.imp-hamburger:hover { background: #2a2a2a; }
.imp-hamburger span {
    display: block; width: 20px; height: 2px;
    background: var(--imp-text); border-radius: 2px;
    transition: all 0.25s;
}
.imp-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.imp-hamburger.open span:nth-child(2) { opacity: 0; }
.imp-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Flyout Overlay ── */
.imp-flyout-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 1050;
    display: none;
    pointer-events: none;
}
.imp-flyout-overlay.open { display: block; pointer-events: auto; }

/* ── Sidebar Flyout ── */
.imp-sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: min(var(--sidebar-w), 88vw);
    background: #111;
    border-right: 1px solid var(--imp-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 1060;
    transform: translateX(-110%);
    transition: transform 0.27s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 4px 0 24px rgba(0,0,0,.5);
}
.imp-sidebar.open { transform: translateX(0); }

.imp-sidebar-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--imp-border);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    min-height: 52px;
    background: #1a1a1a;
}
.imp-sidebar-header h2 { margin: 0; font-size: 0.95rem; flex: 1; color: var(--imp-accent); font-weight: 800; }

/* ── Sidebar inner content ── */
.imp-section-head {
    padding: 9px 12px;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--imp-muted);
    letter-spacing: 0.06em;
    border-bottom: 1px solid var(--imp-border);
    background: #0e0e0e;
    flex-shrink: 0;
}
.imp-doc-select {
    margin: 10px;
    width: calc(100% - 20px);
    padding: 8px;
    background: #222;
    border: 1px solid #444;
    color: var(--imp-text);
    border-radius: 5px;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.imp-doc-select:focus { outline: none; border-color: var(--imp-accent); }

.imp-cat-list { flex: 1; overflow-y: auto; }
.imp-cat-item {
    padding: 10px 14px;
    font-size: 0.88rem;
    cursor: pointer;
    border-bottom: 1px solid var(--imp-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.15s;
    color: #bbb;
}
.imp-cat-item:hover { background: #1e1e1e; color: #fff; }
.imp-cat-item.active {
    background: rgba(139, 92, 246, 0.15);
    color: var(--imp-accent);
    border-left: 3px solid var(--imp-accent);
}
.imp-cat-count {
    font-size: 0.72rem;
    background: #333;
    padding: 2px 7px;
    border-radius: 10px;
    color: var(--imp-muted);
    flex-shrink: 0;
}
.imp-cat-item.active .imp-cat-count {
    background: rgba(139, 92, 246, 0.3);
    color: var(--imp-accent);
}

/* ── Body (middle + right) ── */
.imp-body {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 260px;
    overflow: hidden;
    min-height: 0;
}

/* ── Middle Column: Entity Items ── */
.imp-middle {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #0e0e0e;
    border-right: 1px solid var(--imp-border);
}
.imp-middle-head {
    padding: 10px 14px;
    border-bottom: 1px solid var(--imp-border);
    background: #111;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.imp-search {
    flex: 1;
    padding: 6px 10px;
    background: #222;
    border: 1px solid #444;
    color: var(--imp-text);
    border-radius: 5px;
    font-size: 0.83rem;
}
.imp-search:focus { outline: none; border-color: var(--imp-accent); }

.imp-items-list { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 5px; }

.imp-entity-item {
    padding: 9px 12px;
    background: #191919;
    border: 1px solid var(--imp-border);
    border-radius: 5px;
    color: #ddd;
    font-size: 0.88rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: border-color 0.15s, background 0.15s;
}
.imp-entity-item:hover { border-color: #555; background: #222; }
.imp-entity-item.already-in-kg {
    border-left: 3px solid var(--imp-green);
    opacity: 0.65;
}
.imp-entity-item.staged {
    border-left: 3px solid var(--imp-accent);
    background: rgba(139, 92, 246, 0.08);
}

.imp-entity-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.imp-already-badge {
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 8px;
    background: rgba(16, 185, 129, 0.15);
    color: var(--imp-green);
    border: 1px solid rgba(16, 185, 129, 0.3);
    white-space: nowrap;
    margin-right: 6px;
}

.imp-item-btns {
    display: flex;
    gap: 4px;
    align-items: center;
    flex-shrink: 0;
    margin-left: 8px;
}
.imp-peek-btn, .imp-stage-btn {
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.15s;
    flex-shrink: 0;
}
.imp-peek-btn:hover { color: var(--imp-amber); border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.1); }
.imp-stage-btn { color: #888; }
.imp-stage-btn:hover { color: var(--imp-accent); border-color: rgba(139,92,246,0.4); background: rgba(139,92,246,0.1); }
.imp-stage-btn.staged { color: var(--imp-accent); border-color: var(--imp-accent); background: rgba(139,92,246,0.15); }

/* ── Right Column: Staging + Promotion ── */
.imp-right {
    border-left: 1px solid var(--imp-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #111;
}
.imp-right-head {
    padding: 10px 14px;
    border-bottom: 1px solid var(--imp-border);
    background: #0e0e0e;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}
.imp-right-head h3 { margin: 0; font-size: 0.88rem; flex: 1; color: var(--imp-accent); }

.imp-staged-list { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 8px; min-height: 0; }

/* ── Promotion Card ── */
.imp-promo-card {
    background: #1a1a1a;
    border: 1px solid var(--imp-border);
    border-radius: 7px;
    overflow: hidden;
    position: relative;
}
.imp-promo-card.promoting {
    border-color: var(--imp-amber);
    opacity: 0.7;
}
.imp-promo-card.promoted {
    border-color: var(--imp-green);
    background: rgba(16, 185, 129, 0.05);
}

.imp-promo-header {
    padding: 8px 10px;
    background: #222;
    border-bottom: 1px solid var(--imp-border);
    display: flex;
    align-items: center;
    gap: 6px;
}
.imp-promo-cat-pill {
    font-size: 0.65rem;
    padding: 2px 7px;
    border-radius: 8px;
    background: rgba(139, 92, 246, 0.15);
    color: var(--imp-accent);
    border: 1px solid rgba(139, 92, 246, 0.3);
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
    flex-shrink: 0;
}
.imp-promo-dismiss {
    margin-left: auto;
    background: none;
    border: none;
    color: var(--imp-muted);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 2px 5px;
    border-radius: 3px;
    flex-shrink: 0;
}
.imp-promo-dismiss:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

.imp-promo-body { padding: 10px; display: flex; flex-direction: column; gap: 7px; }

.imp-promo-input {
    width: 100%;
    padding: 6px 8px;
    background: #111;
    border: 1px solid #444;
    color: var(--imp-text);
    border-radius: 4px;
    font-size: 0.83rem;
    box-sizing: border-box;
}
.imp-promo-input:focus { outline: none; border-color: var(--imp-accent); }

.imp-promo-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}
.imp-promo-select {
    padding: 5px 7px;
    background: #111;
    border: 1px solid #444;
    color: var(--imp-text);
    border-radius: 4px;
    font-size: 0.78rem;
    width: 100%;
}
.imp-promo-select:focus { outline: none; border-color: var(--imp-accent); }

.imp-promo-footer {
    padding: 8px 10px;
    border-top: 1px solid var(--imp-border);
    display: flex;
    gap: 6px;
    align-items: center;
}
.imp-promo-status {
    flex: 1;
    font-size: 0.72rem;
    color: var(--imp-muted);
    font-style: italic;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.imp-promo-status.ok { color: var(--imp-green); }
.imp-promo-status.err { color: #ef4444; }
.imp-promo-status.working { color: var(--imp-amber); }

.btn-promote {
    padding: 5px 12px;
    background: var(--imp-accent);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
    transition: background 0.15s, opacity 0.15s;
    flex-shrink: 0;
}
.btn-promote:hover { background: #7c3aed; }
.btn-promote:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-promote-all {
    padding: 9px;
    background: linear-gradient(135deg, var(--imp-accent), #6d28d9);
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 800;
    transition: opacity 0.15s;
    margin: 8px;
    width: calc(100% - 16px);
}
.btn-promote-all:hover { opacity: 0.9; }
.btn-promote-all:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── AI Badge ── */
.ai-badge {
    font-size: 0.65rem;
    padding: 2px 7px;
    border-radius: 8px;
    background: rgba(192,132,252,0.15);
    color: #c084fc;
    border: 1px solid rgba(192,132,252,0.3);
    font-weight: 700;
}

/* ── Empty States ── */
.imp-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    color: var(--imp-muted);
    font-size: 0.85rem;
    text-align: center;
    padding: 20px;
}
.imp-empty .hint { font-size: 0.75rem; opacity: 0.7; }

/* ── Peek Modal ── */
.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.85);
    z-index: 5000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.modal-content {
    background: #1a1a1a;
    width: 95%;
    max-width: 680px;
    max-height: 82vh;
    padding: 22px;
    border-radius: 10px;
    border: 1px solid #333;
    overflow-y: auto;
    box-shadow: 0 15px 50px rgba(0,0,0,0.6);
    position: relative;
}
.modal-close {
    position: absolute; top: 14px; right: 16px;
    font-size: 1.7rem; cursor: pointer;
    color: #888; line-height: 1;
    background: none; border: none;
}
.modal-close:hover { color: #fff; }

/* Peek sections */
.preview-header {
    display: flex; align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 16px; padding-bottom: 14px;
    border-bottom: 1px solid #333; gap: 12px;
}
.preview-cat-badge {
    font-size: 0.7rem; padding: 3px 9px; border-radius: 12px;
    background: rgba(245,158,11,0.15); color: var(--imp-amber);
    border: 1px solid rgba(245,158,11,0.3);
    text-transform: uppercase; font-weight: 700;
    white-space: nowrap; flex-shrink: 0; margin-top: 4px;
}
.preview-section { margin-bottom: 14px; }
.preview-section-title {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    color: #888; letter-spacing: 0.05em; margin-bottom: 6px;
}
.preview-value { font-size: 0.9rem; line-height: 1.55; color: #ddd; }
.preview-pill-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
.preview-pill {
    font-size: 0.78rem; padding: 2px 10px; border-radius: 10px;
    background: rgba(255,255,255,0.06); border: 1px solid #333; color: #ccc;
}
.preview-kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
.preview-kv-key { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #888; margin-bottom: 2px; }
.preview-kv-val { font-size: 0.85rem; color: #ddd; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
.preview-loading {
    display: flex; align-items: center; justify-content: center;
    padding: 40px 0; gap: 12px; color: #888; font-size: 0.9rem;
}
.preview-spinner {
    width: 22px; height: 22px;
    border: 3px solid rgba(255,255,255,0.1);
    border-top-color: var(--imp-accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { 100% { transform: rotate(360deg); } }
.preview-not-found { padding: 30px 0; text-align: center; color: #888; font-size: 0.9rem; }

/* ── Buttons generic ── */
.btn {
    padding: 6px 12px; border-radius: 5px; border: none; cursor: pointer;
    font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 5px;
    white-space: nowrap; transition: opacity 0.15s;
}
.btn:hover { opacity: 0.85; }
.btn-ghost {
    background: transparent; border: 1px solid var(--imp-border);
    color: var(--imp-text);
}
.btn-ghost:hover { border-color: var(--imp-accent); color: var(--imp-accent); }
.btn-sm { padding: 4px 9px; font-size: 0.75rem; }

/* ── Toast override ── */
#kg-imp-toast {
    position: fixed; bottom: 20px; right: 20px; z-index: 99999;
    background: #1a1a1a; color: var(--imp-text);
    border: 1px solid var(--imp-border);
    border-left: 4px solid var(--imp-green);
    border-radius: 6px; padding: 11px 16px; font-size: 0.88rem;
    display: none; box-shadow: 0 4px 16px rgba(0,0,0,0.4);
}
</style>

<!-- ═══════ HAMBURGER (fixed, always visible) ═══════ -->
<button class="imp-hamburger" id="imp-hamburger" onclick="toggleSidebar()" title="Browse categories">
    <span></span><span></span><span></span>
</button>

<!-- ═══════ FLYOUT OVERLAY ═══════ -->
<div class="imp-flyout-overlay" id="imp-flyout-overlay" onclick="closeSidebar()"></div>

<!-- ═══════ SIDEBAR FLYOUT ═══════ -->
<div class="imp-sidebar" id="imp-sidebar">
    <div class="imp-sidebar-header">
        <span style="font-size:1.1rem;">🔀</span>
        <h2>KG Import Bridge</h2>
        <button class="btn btn-ghost btn-sm" onclick="closeSidebar()" title="Close">&#x2715;</button>
    </div>

    <div class="imp-section-head">Source Document</div>
    <select id="docSelect" class="imp-doc-select" onchange="onDocChange()">
        <option value="">— Select a document —</option>
        <?php foreach ($docsRaw as $d): ?>
            <option value="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['name']) ?>">
                <?= htmlspecialchars($d['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div class="imp-section-head">Categories</div>
    <div class="imp-cat-list" id="catList">
        <div class="imp-empty" style="padding:20px; font-size:0.8rem;">Select a document first</div>
    </div>
</div>

<!-- ═══════ LAYOUT ═══════ -->
<div class="imp-layout">

    <!-- Top Bar -->
    <div class="imp-topbar">
        <div class="imp-topbar-left">
            <div class="imp-topbar-title">
                🔀 KG Import Bridge
            </div>
            <span style="font-size:0.75rem; color:var(--imp-muted);">
                Browse AI-generated lore · Peek · Stage · Promote to Knowledge Graph
            </span>
        </div>
        <div class="imp-topbar-right">
            <span class="ai-badge">✨ AI MD Authoring</span>
            <button class="btn btn-ghost btn-sm" onclick="toggleSidebar()" title="Browse categories &amp; documents">
                ☰ Browse
            </button>
            <a href="kg_view.php" class="btn btn-ghost btn-sm">Open KG →</a>
        </div>
    </div>

    <div class="imp-body">

        <!-- ── MIDDLE: Entity Items ── -->
        <div class="imp-middle">
            <div class="imp-middle-head">
                <span style="font-size:0.8rem; color:var(--imp-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em;" id="middleHeadLabel">
                    Entities
                </span>
                <input type="text" id="entitySearch" class="imp-search" placeholder="Filter entities…" oninput="filterEntities()" style="display:none;">
            </div>

            <div class="imp-items-list" id="entityList">
                <div class="imp-empty">
                    <span>☰ Open the sidebar</span>
                    <span class="hint">select a document, then pick a category</span>
                </div>
            </div>
        </div>

        <!-- ── RIGHT: Staging & Promotion ── -->
        <div class="imp-right">
            <div class="imp-right-head">
                <h3>📥 Staged for Promotion</h3>
                <span id="stagedCount" style="font-size:0.75rem; color:var(--imp-muted);"></span>
                <button class="btn btn-ghost btn-sm" onclick="clearAllStaged()" title="Clear all staged items">Clear</button>
            </div>

            <div class="imp-staged-list" id="stagedList">
                <div class="imp-empty" id="stagedEmptyMsg">
                    <span>🧩 Nothing staged yet</span>
                    <span class="hint">Click + on any entity to stage it for import</span>
                </div>
            </div>

            <button class="btn-promote-all" id="btnPromoteAll" onclick="promoteAll()" disabled>
                ⚡ Promote All to Knowledge Graph
            </button>
        </div>

    </div>
</div>

<!-- ── Peek Modal ── -->
<div id="peekModal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close" onclick="closePeek()">&times;</button>
        <div id="peekBody">
            <div class="preview-loading"><div class="preview-spinner"></div> Loading...</div>
        </div>
    </div>
</div>

<div id="kg-imp-toast"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// ═══════════════════════════════════════════════════════
// SIDEBAR FLYOUT
// ═══════════════════════════════════════════════════════
function toggleSidebar() {
    const sidebar   = document.getElementById('imp-sidebar');
    const overlay   = document.getElementById('imp-flyout-overlay');
    const hamburger = document.getElementById('imp-hamburger');
    if (sidebar.classList.contains('open')) {
        closeSidebar();
    } else {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        hamburger.classList.add('open');
    }
}
function closeSidebar() {
    document.getElementById('imp-sidebar').classList.remove('open');
    document.getElementById('imp-flyout-overlay').classList.remove('open');
    document.getElementById('imp-hamburger').classList.remove('open');
}

// ═══════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════
let currentDocId   = null;
let currentDocName = '';
let currentCat     = null;
let allEntities    = [];       // raw list for current category
let stagedItems    = [];       // { name, cat, nodeType, categoryId, status, nodeId }
let kgNodeTypeMap  = {
    characters:  'character',
    locations:   'location',
    factions:    'concept',
    episodes:    'episode',
    scene_hooks: 'note',
    artifacts:   'note',
};
let kgCats = <?= json_encode($kgCats) ?>;

// Category display config
const CAT_META = {
    characters:  { icon: '👤', label: 'Characters' },
    locations:   { icon: '📍', label: 'Locations' },
    factions:    { icon: '⚔️', label: 'Factions' },
    episodes:    { icon: '🎬', label: 'Episodes' },
    scene_hooks: { icon: '🎭', label: 'Scene Hooks' },
    artifacts:   { icon: '💎', label: 'Artifacts' },
};
const ALL_CATS = Object.keys(CAT_META);

// ═══════════════════════════════════════════════════════
// DOCUMENT CHANGE
// ═══════════════════════════════════════════════════════
function onDocChange() {
    const sel = document.getElementById('docSelect');
    currentDocId   = sel.value || null;
    currentDocName = sel.options[sel.selectedIndex]?.getAttribute('data-name') || '';
    currentCat     = null;
    allEntities    = [];

    document.getElementById('entitySearch').style.display = 'none';
    document.getElementById('entityList').innerHTML = '<div class="imp-empty"><span>👈 Select a category</span><span class="hint">to browse entities</span></div>';
    document.getElementById('middleHeadLabel').textContent = 'Entities';

    if (!currentDocId) {
        document.getElementById('catList').innerHTML = '<div class="imp-empty" style="padding:20px; font-size:0.8rem;">Select a document first</div>';
        return;
    }

    // Render category list immediately (counts loaded async)
    renderCategories();
}

function renderCategories() {
    const list = document.getElementById('catList');
    let html = '';
    ALL_CATS.forEach(cat => {
        const m = CAT_META[cat];
        html += `<div class="imp-cat-item" id="cat-${cat}" onclick="selectCat('${cat}')">
            <span>${m.icon} ${m.label}</span>
            <span class="imp-cat-count" id="count-${cat}">…</span>
        </div>`;
    });
    list.innerHTML = html;

    // Load counts async
    ALL_CATS.forEach(cat => {
        $.get(`kg_import_api.php?action=get_filter_items&doc_id=${currentDocId}&cat=${cat}`, res => {
            if (res.status === 'success') {
                const el = document.getElementById('count-' + cat);
                if (el) el.textContent = res.data.length;
            }
        }, 'json');
    });
}

// ═══════════════════════════════════════════════════════
// CATEGORY SELECT
// ═══════════════════════════════════════════════════════
function selectCat(cat) {
    currentCat = cat;

    // Update active state
    document.querySelectorAll('.imp-cat-item').forEach(el => el.classList.remove('active'));
    const catEl = document.getElementById('cat-' + cat);
    if (catEl) catEl.classList.add('active');

    const m = CAT_META[cat] || { icon: '📦', label: cat };
    document.getElementById('middleHeadLabel').textContent = `${m.icon} ${m.label}`;
    document.getElementById('entitySearch').style.display = 'block';
    document.getElementById('entitySearch').value = '';
    document.getElementById('entityList').innerHTML = '<div class="imp-empty"><div class="preview-spinner"></div></div>';

    $.get(`kg_import_api.php?action=get_filter_items&doc_id=${currentDocId}&cat=${cat}`, res => {
        if (res.status !== 'success') {
            document.getElementById('entityList').innerHTML = '<div class="imp-empty">Failed to load</div>';
            return;
        }

        allEntities = res.data;
        renderEntityList(allEntities);
    }, 'json');

    // Close sidebar after selecting category so the entity list is visible
    closeSidebar();
}

// ═══════════════════════════════════════════════════════
// ENTITY LIST RENDER
// ═══════════════════════════════════════════════════════
function renderEntityList(entities) {
    const list = document.getElementById('entityList');
    if (!entities.length) {
        list.innerHTML = '<div class="imp-empty">No entities in this category</div>';
        return;
    }

    let html = '';
    entities.forEach(name => {
        const isStaged  = stagedItems.some(s => s.name === name && s.cat === currentCat);
        const safeName  = escHtml(name);
        const safeAttr  = name.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        html += `
        <div class="imp-entity-item ${isStaged ? 'staged' : ''}" id="ent-${slugify(name)}">
            <span class="imp-entity-name" title="${safeName}">${safeName}</span>
            <div class="imp-item-btns">
                <button class="imp-peek-btn" title="Preview entity"
                    onclick="peekEntity(event, '${safeAttr.replace(/'/g,"\\'")}', '${currentCat}')">👁</button>
                <button class="imp-stage-btn ${isStaged ? 'staged' : ''}" title="${isStaged ? 'Already staged' : 'Stage for import'}"
                    onclick="toggleStage('${safeAttr.replace(/'/g,"\\'")}', '${currentCat}', this)">
                    ${isStaged ? '✓' : '+'}
                </button>
            </div>
        </div>`;
    });
    list.innerHTML = html;
}

function filterEntities() {
    const q = document.getElementById('entitySearch').value.toLowerCase();
    const filtered = allEntities.filter(n => n.toLowerCase().includes(q));
    renderEntityList(filtered);
}

// ═══════════════════════════════════════════════════════
// STAGING
// ═══════════════════════════════════════════════════════
function toggleStage(name, cat, btn) {
    const idx = stagedItems.findIndex(s => s.name === name && s.cat === cat);
    if (idx !== -1) {
        // Remove from staged
        stagedItems.splice(idx, 1);
        if (btn) { btn.textContent = '+'; btn.classList.remove('staged'); }
        const entEl = document.getElementById('ent-' + slugify(name));
        if (entEl) entEl.classList.remove('staged');
        renderStagedList();
        return;
    }

    // Add
    stagedItems.push({
        name:       name,
        cat:        cat,
        nodeType:   kgNodeTypeMap[cat] || 'note',
        categoryId: '',
        status:     'pending',
        nodeId:     null,
        statusMsg:  'Ready to promote',
    });

    if (btn) { btn.textContent = '✓'; btn.classList.add('staged'); }
    const entEl = document.getElementById('ent-' + slugify(name));
    if (entEl) entEl.classList.add('staged');

    renderStagedList();
    imp_toast('Staged: ' + name);
}

function clearAllStaged() {
    stagedItems = [];
    renderStagedList();
    document.querySelectorAll('.imp-stage-btn').forEach(btn => {
        btn.textContent = '+'; btn.classList.remove('staged');
    });
    document.querySelectorAll('.imp-entity-item').forEach(el => el.classList.remove('staged'));
}

// ═══════════════════════════════════════════════════════
// STAGED LIST RENDER
// ═══════════════════════════════════════════════════════
function renderStagedList() {
    const list   = document.getElementById('stagedList');
    const count  = document.getElementById('stagedCount');
    const btnAll = document.getElementById('btnPromoteAll');

    const pending = stagedItems.filter(s => s.status !== 'promoted');
    count.textContent = stagedItems.length ? `(${stagedItems.length})` : '';
    btnAll.disabled = pending.length === 0;

    if (!stagedItems.length) {
        list.innerHTML = `<div class="imp-empty" id="stagedEmptyMsg">
            <span>🧩 Nothing staged yet</span>
            <span class="hint">Click + on any entity to stage it for import</span>
        </div>`;
        return;
    }

    let html = '';
    stagedItems.forEach((item, idx) => {
        const safeName = escHtml(item.name);
        const catMeta  = CAT_META[item.cat] || { icon: '📦', label: item.cat };

        let folderOpts = '<option value="">— No folder —</option>';
        kgCats.forEach(c => {
            const sel = (String(c.id) === String(item.categoryId)) ? 'selected' : '';
            folderOpts += `<option value="${c.id}" ${sel}>${escHtml(c.name)}</option>`;
        });

        let cardClass = '';
        if (item.status === 'promoting') cardClass = 'promoting';
        if (item.status === 'promoted')  cardClass = 'promoted';

        let statusClass = '';
        if (item.status === 'promoted')  statusClass = 'ok';
        if (item.status === 'error')     statusClass = 'err';
        if (item.status === 'promoting') statusClass = 'working';

        const isPromoted = item.status === 'promoted';
        const btnLabel   = item.status === 'promoting' ? '⏳…' : (isPromoted ? '✓ Done' : 'Promote');

        html += `
        <div class="imp-promo-card ${cardClass}" id="promo-card-${idx}">
            <div class="imp-promo-header">
                <span class="imp-promo-cat-pill">${catMeta.icon} ${item.cat}</span>
                <span style="font-size:0.82rem; color:#ccc; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; margin:0 6px;" title="${safeName}">${safeName}</span>
                ${!isPromoted ? `<button class="imp-promo-dismiss" onclick="removeStaged(${idx})" title="Remove">×</button>` : ''}
                ${isPromoted && item.nodeId ? `<a href="kg_view.php?node_id=${item.nodeId}" target="_blank" style="font-size:0.7rem; color:var(--imp-green); white-space:nowrap; text-decoration:none;" title="Open in KG">Open →</a>` : ''}
            </div>
            <div class="imp-promo-body">
                <input type="text" class="imp-promo-input" value="${safeName}"
                    placeholder="KG Node name…"
                    onchange="stagedItems[${idx}].name = this.value"
                    ${isPromoted ? 'readonly' : ''}>
                <div class="imp-promo-row">
                    <select class="imp-promo-select" onchange="stagedItems[${idx}].nodeType = this.value" ${isPromoted ? 'disabled' : ''}>
                        <option value="note"         ${item.nodeType==='note'         ? 'selected':''}>📝 Note</option>
                        <option value="character"    ${item.nodeType==='character'    ? 'selected':''}>👤 Character</option>
                        <option value="location"     ${item.nodeType==='location'     ? 'selected':''}>📍 Location</option>
                        <option value="concept"      ${item.nodeType==='concept'      ? 'selected':''}>💡 Concept</option>
                        <option value="episode"      ${item.nodeType==='episode'      ? 'selected':''}>🎬 Episode</option>
                        <option value="arc"          ${item.nodeType==='arc'          ? 'selected':''}>🌀 Arc</option>
                        <option value="event"        ${item.nodeType==='event'        ? 'selected':''}>📅 Event</option>
                        <option value="relationship" ${item.nodeType==='relationship' ? 'selected':''}>🔗 Relationship</option>
                    </select>
                    <select class="imp-promo-select" onchange="stagedItems[${idx}].categoryId = this.value" ${isPromoted ? 'disabled' : ''}>
                        ${folderOpts}
                    </select>
                </div>
            </div>
            <div class="imp-promo-footer">
                <span class="imp-promo-status ${statusClass}" id="promo-status-${idx}">${escHtml(item.statusMsg || '')}</span>
                <button class="btn-promote" id="promo-btn-${idx}"
                    onclick="promoteOne(${idx})"
                    ${(isPromoted || item.status === 'promoting') ? 'disabled' : ''}>
                    ${btnLabel}
                </button>
            </div>
        </div>`;
    });

    list.innerHTML = html;
}

function removeStaged(idx) {
    const item = stagedItems[idx];
    if (item && item.cat === currentCat) {
        const entEl = document.getElementById('ent-' + slugify(item.name));
        if (entEl) {
            entEl.classList.remove('staged');
            const btn = entEl.querySelector('.imp-stage-btn');
            if (btn) { btn.textContent = '+'; btn.classList.remove('staged'); }
        }
    }
    stagedItems.splice(idx, 1);
    renderStagedList();
}

// ═══════════════════════════════════════════════════════
// PROMOTE
// ═══════════════════════════════════════════════════════
function promoteOne(idx) {
    const item = stagedItems[idx];
    if (!item || item.status === 'promoted') return;

    item.status    = 'promoting';
    item.statusMsg = '✨ AI authoring MD…';
    updatePromoCardStatus(idx);

    $.post('kg_import_api.php', {
        action:      'promote_entity',
        doc_id:      currentDocId,
        doc_name:    currentDocName,
        entity_name: item.name,
        entity_cat:  item.cat,
        node_type:   item.nodeType,
        category_id: item.categoryId || '',
    }, res => {
        if (res.status === 'success') {
            item.status    = 'promoted';
            item.nodeId    = res.node_id;
            item.statusMsg = '✓ Promoted — node #' + res.node_id;
        } else {
            item.status    = 'error';
            item.statusMsg = '✗ ' + (res.message || 'Error');
        }
        renderStagedList();
        imp_toast(item.status === 'promoted' ? '✓ ' + item.name + ' promoted!' : '✗ Failed: ' + item.name, item.status !== 'promoted');
    }, 'json').fail(() => {
        item.status    = 'error';
        item.statusMsg = '✗ Network error';
        renderStagedList();
    });
}

function promoteAll() {
    const pending = stagedItems
        .map((s, i) => ({s, i}))
        .filter(({s}) => s.status === 'pending' || s.status === 'error');

    if (!pending.length) return;

    pending.forEach(({s, i}, order) => {
        setTimeout(() => promoteOne(i), order * 600);
    });
}

function updatePromoCardStatus(idx) {
    const item   = stagedItems[idx];
    const status = document.getElementById('promo-status-' + idx);
    const btn    = document.getElementById('promo-btn-'    + idx);
    if (status) {
        status.textContent = item.statusMsg || '';
        status.className   = 'imp-promo-status ' + (item.status === 'promoted' ? 'ok' : item.status === 'error' ? 'err' : 'working');
    }
    if (btn) {
        btn.disabled     = true;
        btn.textContent  = '⏳…';
    }
}

// ═══════════════════════════════════════════════════════
// PEEK MODAL
// ═══════════════════════════════════════════════════════
function peekEntity(event, name, cat) {
    event.stopPropagation();
    event.preventDefault();

    const body = document.getElementById('peekBody');
    body.innerHTML = '<div class="preview-loading"><div class="preview-spinner"></div> Loading preview…</div>';
    document.getElementById('peekModal').style.display = 'flex';

    if (!currentDocId) {
        body.innerHTML = '<div class="preview-not-found">No context document selected.</div>';
        return;
    }

    fetch(`kg_import_api.php?action=get_entity_preview&doc_id=${encodeURIComponent(currentDocId)}&cat=${encodeURIComponent(cat)}&name=${encodeURIComponent(name)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                renderEntityPreview(res.data, name, cat, body);
            } else {
                body.innerHTML = `<div class="preview-not-found">
                    <div style="font-size:2rem; margin-bottom:8px;">🔍</div>
                    <div><strong>${escHtml(name)}</strong></div>
                    <div style="margin-top:6px; font-size:0.82rem;">${escHtml(res.message || 'No detailed data found.')}</div>
                </div>`;
            }
        })
        .catch(err => {
            body.innerHTML = `<div class="preview-not-found">Failed to load: ${escHtml(err.message)}</div>`;
        });
}

function closePeek() {
    document.getElementById('peekModal').style.display = 'none';
}
document.getElementById('peekModal').addEventListener('click', e => {
    if (e.target === document.getElementById('peekModal')) closePeek();
});

function renderEntityPreview(data, name, cat, container) {
    let html = '';
    const catColor = { episodes:'#93c5fd', scene_hooks:'#fcd34d', characters:'#f9a8d4', factions:'#c4b5fd', locations:'#6ee7b7', artifacts:'#fca5a5' }[cat] || '#e5e7eb';

    html += `<div class="preview-header">
        <div>
            <h3 style="margin:0; font-size:1.15rem; color:#fff; line-height:1.3;">${escHtml(data.name || name)}</h3>
            ${data.roles && data.roles.length ? `<div style="margin-top:5px;">${data.roles.map(r=>`<span class="preview-pill">${escHtml(r)}</span>`).join(' ')}</div>` : ''}
        </div>
        <span class="preview-cat-badge" style="color:${catColor}; border-color:${catColor}50;">${escHtml(cat)}</span>
    </div>`;

    if (data.aliases && data.aliases.length) {
        html += `<div class="preview-section">
            <div class="preview-section-title">Also Known As</div>
            <div class="preview-pill-row">${data.aliases.map(a=>`<span class="preview-pill">${escHtml(String(a))}</span>`).join('')}</div>
        </div>`;
    }

    if (data.attributes && typeof data.attributes === 'object') {
        const attrs = Object.entries(data.attributes).filter(([k,v]) => v !== null && v !== undefined && v !== '');
        if (attrs.length) {
            const longFields = ['description','summary','backstory','purpose','function','personality','motivation','production_notes','significance','visual','appearance','logline','act_structure'];
            const longPairs  = attrs.filter(([k]) => longFields.includes(k));
            const shortPairs = attrs.filter(([k]) => !longFields.includes(k));

            longPairs.forEach(([k,v]) => {
                const disp = renderAttrVal(v);
                if (!disp) return;
                html += `<div class="preview-section">
                    <div class="preview-section-title">${escHtml(k.replace(/_/g,' '))}</div>
                    <div class="preview-value">${disp}</div>
                </div>`;
            });

            if (shortPairs.length) {
                html += `<div class="preview-section"><div class="preview-section-title">Details</div><div class="preview-kv-grid">`;
                shortPairs.forEach(([k,v]) => {
                    const disp = renderAttrVal(v);
                    if (!disp) return;
                    html += `<div><div class="preview-kv-key">${escHtml(k.replace(/_/g,' '))}</div><div class="preview-kv-val">${disp}</div></div>`;
                });
                html += `</div></div>`;
            }
        }
    }

    if (data.relationships && data.relationships.length) {
        html += `<div class="preview-section"><div class="preview-section-title">Relationships</div><div style="display:flex;flex-direction:column;gap:5px;">`;
        data.relationships.slice(0,8).forEach(r => {
            html += `<div style="font-size:0.85rem; padding:5px 10px; background:rgba(0,0,0,0.3); border-radius:5px; border-left:2px solid #444;">
                <span style="font-weight:700; color:#ddd;">${escHtml(r.target||'')}</span>
                ${r.type ? `<span style="color:#888; margin-left:6px; font-size:0.78rem;">(${escHtml(r.type)})</span>` : ''}
                ${r.desc ? `<div style="color:#888; font-size:0.8rem; margin-top:2px;">${escHtml(r.desc)}</div>` : ''}
            </div>`;
        });
        if (data.relationships.length > 8) html += `<div style="font-size:0.78rem;color:#888;padding:4px;">+${data.relationships.length-8} more…</div>`;
        html += `</div></div>`;
    }

    if (data.timeline && data.timeline.length) {
        html += `<div class="preview-section"><div class="preview-section-title">History / Timeline</div><div style="display:flex;flex-direction:column;gap:5px;">`;
        data.timeline.slice(0,6).forEach(t => {
            const date = t.date ? `<span style="font-family:monospace;font-size:0.75rem;color:#888;margin-right:8px;">[${escHtml(String(t.date))}]</span>` : '';
            html += `<div style="font-size:0.85rem;padding:4px 10px;border-left:2px solid rgba(245,158,11,0.3);color:#ccc;">${date}${escHtml(t.text||'')}</div>`;
        });
        html += `</div></div>`;
    }

    container.innerHTML = html;
}

function renderAttrVal(v) {
    if (v === null || v === undefined || v === '') return '';
    if (typeof v === 'string')  return escHtml(v);
    if (typeof v === 'number' || typeof v === 'boolean') return escHtml(String(v));
    if (Array.isArray(v)) {
        if (!v.length) return '';
        if (v.every(i => typeof i === 'string' || typeof i === 'number')) {
            return `<div class="preview-pill-row">${v.map(i=>`<span class="preview-pill">${escHtml(String(i))}</span>`).join('')}</div>`;
        }
        return `<pre style="font-size:0.75rem;color:#888;white-space:pre-wrap;word-break:break-word;margin:0;">${escHtml(JSON.stringify(v,null,2))}</pre>`;
    }
    if (typeof v === 'object') {
        return `<pre style="font-size:0.75rem;color:#888;white-space:pre-wrap;word-break:break-word;margin:0;">${escHtml(JSON.stringify(v,null,2))}</pre>`;
    }
    return escHtml(String(v));
}

// ═══════════════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════════════
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function slugify(s) {
    return String(s).toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/(^_|_$)/g,'');
}

let toastTimer;
function imp_toast(msg, isError = false) {
    const el = document.getElementById('kg-imp-toast');
    el.textContent = msg;
    el.style.borderLeftColor = isError ? '#ef4444' : 'var(--imp-green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display = 'none', 2800);
}

// Keyboard
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closePeek();
        closeSidebar();
    }
});
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content . ($eruda ?? ''), $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
