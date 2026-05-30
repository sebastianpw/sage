<?php
// public/view_kg_staging_import.php  (v2 — AG Hops Edition)
// -----------------------------------------------------------------------
// KG Staging Import Bridge — AG-source, hop-aware, preview-capable
//
// WHAT CHANGED FROM v1
// --------------------
//  * Source is now ag_nodes / ag_node_items directly (proper DB rows)
//    instead of md_doc_analysis JSON objects resolved through LoreAccessService.
//    The AG tables exist because the initial AG importer already populated them.
//
//  * "Hops" support (0–2):
//      0 = focal node only
//      1 = focal + direct neighbours  (default, same as before)
//      2 = two levels out
//    A hop-preview button opens mini_graph.php in a modal so the user can
//    see exactly what the subgraph looks like before committing to import.
//    Best practice rationale: hops > 2 causes rapid combinatorial explosion;
//    for an import bridge the goal is deliberate, curated promotion — not
//    bulk ingest.  0-hop is useful for "just this node"; 1-hop is the
//    sweet spot for bringing in a character with their direct relationships;
//    2-hop gives a richer cluster but should be used intentionally.
//
//  * Dry-run / Preview:
//      - "Preview subgraph" button per staged item opens mini_graph.php
//        (graph=ag, node_id=ag_node_id, hops=N) in a lightbox modal.
//      - "Preview all" counts how many nodes would be promoted (no DB write).
//
//  * Staging-first guarantee:
//      All DB writes go to kg_staging_nodes / kg_staging_node_items.
//      Nothing touches kg_nodes (live graph).  Promotion to live requires the
//      existing kg_staging.php "Promote to Live" workflow.
//
// LAYOUT
// -------
//  Left flyout  : AG document picker → AG category browser
//  Middle column: AG node list with Peek (entity preview) + Stage buttons
//  Right column : Staged nodes with per-node hops selector, Preview, Promote
// -----------------------------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = 'KG Staging Import Bridge (AG)';

// ── Load AG documents (docs that have ag_nodes) ─────────────────────────────
$agDocs = $pdo->query("
    SELECT DISTINCT d.id, d.name
    FROM documentations d
    JOIN ag_nodes n ON n.doc_id = d.id AND n.status = 'active'
    WHERE d.is_active = 1
    ORDER BY d.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Load KG Staging categories for folder assignment ────────────────────────
$kgCats = $pdo->query("
    SELECT id, name FROM kg_staging_categories ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<style>
/* ══ Variables ══════════════════════════════════════════════════════════ */
:root {
    --imp-bg:      #0c0c0c;
    --imp-card:    #161616;
    --imp-border:  #2a2a2a;
    --imp-accent:  #8b5cf6;
    --imp-green:   #10b981;
    --imp-amber:   #f59e0b;
    --imp-blue:    #3b82f6;
    --imp-muted:   #666;
    --imp-text:    #e5e5e5;
    --sidebar-w:   290px;
}
html, body { height: 100%; margin: 0; overflow: hidden;
             background: var(--imp-bg); color: var(--imp-text); }

/* ── Main Layout ── */
.imp-layout { display: flex; flex-direction: column; height: 100vh; width: 100vw; }

/* ── Top Bar ── */
.imp-topbar {
    height: 52px; background: #1a1a1a;
    border-bottom: 1px solid var(--imp-border);
    display: flex; align-items: center;
    padding: 0 14px; gap: 12px; flex-shrink: 0;
    position: relative; z-index: 10;
}
.imp-topbar-left {
    margin-left: 58px;
    display: flex; align-items: center; gap: 10px;
}
.imp-topbar-title {
    font-size: 1rem; font-weight: 800; color: var(--imp-accent);
    display: flex; align-items: center; gap: 8px;
}
.imp-topbar-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

/* ── Hamburger ── */
.imp-hamburger {
    position: fixed; top: 8px; left: 70px; z-index: 1100;
    width: 38px; height: 38px; background: #1a1a1a;
    border: 1px solid var(--imp-border); border-radius: 8px;
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 5px; cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,.3); transition: background 0.2s;
}
.imp-hamburger:hover { background: #2a2a2a; }
.imp-hamburger span { display: block; width: 20px; height: 2px;
    background: var(--imp-text); border-radius: 2px; transition: all 0.25s; }
.imp-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.imp-hamburger.open span:nth-child(2) { opacity: 0; }
.imp-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Flyout Overlay ── */
.imp-flyout-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.55);
    z-index: 1050; display: none; pointer-events: none;
}
.imp-flyout-overlay.open { display: block; pointer-events: auto; }

/* ── Sidebar Flyout ── */
.imp-sidebar {
    position: fixed; top: 0; left: 0; bottom: 0;
    width: min(var(--sidebar-w), 90vw);
    background: #111; border-right: 1px solid var(--imp-border);
    display: flex; flex-direction: column; overflow: hidden;
    z-index: 1060; transform: translateX(-110%);
    transition: transform 0.27s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 4px 0 24px rgba(0,0,0,.5);
}
.imp-sidebar.open { transform: translateX(0); }
.imp-sidebar-header {
    padding: 12px 14px; border-bottom: 1px solid var(--imp-border);
    display: flex; align-items: center; gap: 8px;
    flex-shrink: 0; min-height: 52px; background: #1a1a1a;
}
.imp-sidebar-header h2 { margin: 0; font-size: 0.95rem; flex: 1;
    color: var(--imp-accent); font-weight: 800; }

/* ── Sidebar inner ── */
.imp-section-head {
    padding: 9px 12px; font-size: 0.68rem; font-weight: 800;
    text-transform: uppercase; color: var(--imp-muted); letter-spacing: 0.06em;
    border-bottom: 1px solid var(--imp-border); background: #0e0e0e; flex-shrink: 0;
}
.imp-doc-select {
    margin: 10px; width: calc(100% - 20px); padding: 8px;
    background: #222; border: 1px solid #444; color: var(--imp-text);
    border-radius: 5px; font-size: 0.85rem; flex-shrink: 0;
}
.imp-doc-select:focus { outline: none; border-color: var(--imp-accent); }

.imp-cat-list { flex: 1; overflow-y: auto; }
.imp-cat-item {
    padding: 10px 14px; font-size: 0.88rem; cursor: pointer;
    border-bottom: 1px solid var(--imp-border);
    display: flex; justify-content: space-between; align-items: center;
    transition: background 0.15s; color: #bbb;
}
.imp-cat-item:hover { background: #1e1e1e; color: #fff; }
.imp-cat-item.active {
    background: rgba(139,92,246,0.15); color: var(--imp-accent);
    border-left: 3px solid var(--imp-accent);
}
.imp-cat-count {
    font-size: 0.72rem; background: #333; padding: 2px 7px;
    border-radius: 10px; color: var(--imp-muted); flex-shrink: 0;
}
.imp-cat-item.active .imp-cat-count {
    background: rgba(139,92,246,0.3); color: var(--imp-accent);
}

/* ── Body (middle + right) ── */
.imp-body {
    flex: 1; display: grid; grid-template-columns: 1fr 280px;
    overflow: hidden; min-height: 0;
}

/* ── Middle Column ── */
.imp-middle {
    display: flex; flex-direction: column; overflow: hidden;
    background: #0e0e0e; border-right: 1px solid var(--imp-border);
}
.imp-middle-head {
    padding: 10px 14px; border-bottom: 1px solid var(--imp-border);
    background: #111; display: flex; align-items: center; gap: 10px;
    flex-shrink: 0;
}
.imp-search {
    flex: 1; padding: 6px 10px; background: #222; border: 1px solid #444;
    color: var(--imp-text); border-radius: 5px; font-size: 0.83rem;
}
.imp-search:focus { outline: none; border-color: var(--imp-accent); }

.imp-items-list {
    flex: 1; overflow-y: auto; padding: 8px;
    display: flex; flex-direction: column; gap: 5px;
}

/* Entity row */
.imp-entity-item {
    padding: 9px 12px; background: #191919;
    border: 1px solid var(--imp-border); border-radius: 5px;
    color: #ddd; font-size: 0.88rem;
    display: flex; justify-content: space-between;
    align-items: center; transition: border-color 0.15s, background 0.15s;
}
.imp-entity-item:hover { border-color: #555; background: #222; }
.imp-entity-item.staged {
    border-left: 3px solid var(--imp-accent);
    background: rgba(139,92,246,0.08);
}
.imp-entity-item.promoted-exists {
    border-left: 3px solid var(--imp-green);
    opacity: 0.6;
}
.imp-entity-name {
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.imp-entity-meta {
    font-size: 0.7rem; color: var(--imp-muted); margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.imp-entity-wrap { flex: 1; min-width: 0; display: flex; flex-direction: column; }

.imp-type-badge {
    font-size: 0.62rem; padding: 1px 5px; border-radius: 5px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.05); color: #888;
    white-space: nowrap; flex-shrink: 0; margin-right: 6px;
}

.imp-item-btns {
    display: flex; gap: 4px; align-items: center;
    flex-shrink: 0; margin-left: 8px;
}
.imp-peek-btn, .imp-stage-btn, .imp-preview-btn {
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 4px; border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04); cursor: pointer;
    font-size: 0.8rem; transition: all 0.15s; flex-shrink: 0;
    color: #888;
}
.imp-peek-btn:hover    { color: var(--imp-amber); border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.1); }
.imp-preview-btn:hover { color: var(--imp-blue);  border-color: rgba(59,130,246,0.4);  background: rgba(59,130,246,0.1); }
.imp-stage-btn:hover   { color: var(--imp-accent); border-color: rgba(139,92,246,0.4); background: rgba(139,92,246,0.1); }
.imp-stage-btn.staged  { color: var(--imp-accent); border-color: var(--imp-accent); background: rgba(139,92,246,0.15); }

/* ── Right Column ── */
.imp-right {
    border-left: 1px solid var(--imp-border);
    display: flex; flex-direction: column;
    overflow: hidden; background: #111;
}
.imp-right-head {
    padding: 10px 14px; border-bottom: 1px solid var(--imp-border);
    background: #0e0e0e; display: flex; align-items: center;
    gap: 8px; flex-shrink: 0;
}
.imp-right-head h3 { margin: 0; font-size: 0.88rem; flex: 1; color: var(--imp-accent); }

.imp-staged-list {
    flex: 1; overflow-y: auto; padding: 8px;
    display: flex; flex-direction: column; gap: 8px; min-height: 0;
}

/* ── Promotion Card ── */
.imp-promo-card {
    background: #1a1a1a; border: 1px solid var(--imp-border);
    border-radius: 7px; overflow: hidden; position: relative;
}
.imp-promo-card.promoting { border-color: var(--imp-amber); opacity: 0.7; }
.imp-promo-card.promoted  { border-color: var(--imp-green); background: rgba(16,185,129,0.05); }
.imp-promo-card.error     { border-color: #ef4444; }

.imp-promo-header {
    padding: 8px 10px; background: #222;
    border-bottom: 1px solid var(--imp-border);
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.imp-promo-cat-pill {
    font-size: 0.62rem; padding: 2px 6px; border-radius: 8px;
    background: rgba(139,92,246,0.15); color: var(--imp-accent);
    border: 1px solid rgba(139,92,246,0.3);
    font-weight: 700; text-transform: uppercase; white-space: nowrap; flex-shrink: 0;
}
.imp-promo-name {
    font-size: 0.82rem; color: #ccc; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap; flex: 1; margin: 0 4px;
}
.imp-promo-dismiss {
    margin-left: auto; background: none; border: none;
    color: var(--imp-muted); cursor: pointer; font-size: 1rem;
    line-height: 1; padding: 2px 5px; border-radius: 3px; flex-shrink: 0;
}
.imp-promo-dismiss:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

/* Hops row */
.imp-hops-row {
    padding: 7px 10px; border-bottom: 1px solid var(--imp-border);
    background: #181818; display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
}
.imp-hops-label {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    color: var(--imp-muted); letter-spacing: 0.04em; white-space: nowrap;
}
.imp-hops-btns { display: flex; gap: 3px; }
.imp-hop-btn {
    width: 28px; height: 22px; border-radius: 4px;
    border: 1px solid var(--imp-border); background: #222;
    color: #888; font-size: 0.75rem; font-weight: 700; cursor: pointer;
    transition: all 0.12s; display: flex; align-items: center; justify-content: center;
}
.imp-hop-btn.active { background: var(--imp-accent); border-color: var(--imp-accent); color: #fff; }
.imp-hop-btn:hover:not(.active) { border-color: var(--imp-accent); color: var(--imp-accent); }

.imp-preview-subgraph-btn {
    margin-left: auto; font-size: 0.72rem; padding: 3px 8px;
    border-radius: 4px; border: 1px solid rgba(59,130,246,0.35);
    background: rgba(59,130,246,0.1); color: var(--imp-blue);
    cursor: pointer; white-space: nowrap; transition: all 0.15s;
}
.imp-preview-subgraph-btn:hover { background: rgba(59,130,246,0.2); }

/* Hops info text */
.imp-hops-info {
    padding: 4px 10px 6px; font-size: 0.68rem; color: var(--imp-muted);
    font-style: italic; border-bottom: 1px solid var(--imp-border);
    background: #161616;
}

.imp-promo-body { padding: 10px; display: flex; flex-direction: column; gap: 7px; }
.imp-promo-input {
    width: 100%; padding: 6px 8px; background: #111;
    border: 1px solid #444; color: var(--imp-text);
    border-radius: 4px; font-size: 0.83rem; box-sizing: border-box;
}
.imp-promo-input:focus { outline: none; border-color: var(--imp-accent); }
.imp-promo-row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.imp-promo-select {
    padding: 5px 7px; background: #111; border: 1px solid #444;
    color: var(--imp-text); border-radius: 4px; font-size: 0.78rem; width: 100%;
}
.imp-promo-select:focus { outline: none; border-color: var(--imp-accent); }

.imp-promo-footer {
    padding: 8px 10px; border-top: 1px solid var(--imp-border);
    display: flex; gap: 6px; align-items: center;
}
.imp-promo-status {
    flex: 1; font-size: 0.72rem; color: var(--imp-muted);
    font-style: italic; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.imp-promo-status.ok      { color: var(--imp-green); }
.imp-promo-status.err     { color: #ef4444; }
.imp-promo-status.working { color: var(--imp-amber); }

.btn-promote {
    padding: 5px 12px; background: var(--imp-accent); color: white;
    border: none; border-radius: 4px; cursor: pointer;
    font-size: 0.78rem; font-weight: 700; white-space: nowrap;
    transition: background 0.15s, opacity 0.15s; flex-shrink: 0;
}
.btn-promote:hover    { background: #7c3aed; }
.btn-promote:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-promote-all {
    padding: 9px;
    background: linear-gradient(135deg, var(--imp-accent), #6d28d9);
    color: white; border: none; border-radius: 5px; cursor: pointer;
    font-size: 0.85rem; font-weight: 800; transition: opacity 0.15s;
    margin: 8px; width: calc(100% - 16px);
}
.btn-promote-all:hover    { opacity: 0.9; }
.btn-promote-all:disabled { opacity: 0.4; cursor: not-allowed; }

/* Dry-run summary row */
.imp-dryrun-row {
    margin: 0 8px 4px; padding: 7px 10px;
    background: rgba(59,130,246,0.07); border: 1px solid rgba(59,130,246,0.2);
    border-radius: 5px; font-size: 0.75rem; color: var(--imp-blue);
    display: none; align-items: center; gap: 8px;
}
.imp-dryrun-row.visible { display: flex; }
.imp-dryrun-icon { font-size: 1rem; flex-shrink: 0; }

/* ── Misc ── */
.ai-badge {
    font-size: 0.65rem; padding: 2px 7px; border-radius: 8px;
    background: rgba(192,132,252,0.15); color: #c084fc;
    border: 1px solid rgba(192,132,252,0.3); font-weight: 700;
}
.imp-empty {
    flex: 1; display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 8px; color: var(--imp-muted);
    font-size: 0.85rem; text-align: center; padding: 20px;
}
.imp-empty .hint { font-size: 0.75rem; opacity: 0.7; }
.preview-spinner {
    width: 22px; height: 22px; border: 3px solid rgba(255,255,255,0.1);
    border-top-color: var(--imp-accent); border-radius: 50%;
    animation: spin 0.8s linear infinite; flex-shrink: 0;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

/* ── Modals ── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.85); z-index: 5000;
    align-items: center; justify-content: center;
    backdrop-filter: blur(4px);
}
.modal-overlay.active { display: flex; }

/* Peek modal */
.modal-content {
    background: #1a1a1a; width: 95%; max-width: 680px; max-height: 82vh;
    padding: 22px; border-radius: 10px; border: 1px solid #333;
    overflow-y: auto; box-shadow: 0 15px 50px rgba(0,0,0,0.6);
    position: relative;
}
.modal-close {
    position: absolute; top: 14px; right: 16px; font-size: 1.7rem;
    cursor: pointer; color: #888; line-height: 1;
    background: none; border: none;
}
.modal-close:hover { color: #fff; }

/* Mini-graph preview modal */
.mg-preview-modal {
    background: #111; width: 96%; max-width: 860px; height: 80vh;
    border-radius: 10px; border: 1px solid #333;
    overflow: hidden; box-shadow: 0 15px 50px rgba(0,0,0,0.6);
    display: flex; flex-direction: column; position: relative;
}
.mg-preview-header {
    padding: 10px 14px; border-bottom: 1px solid var(--imp-border);
    background: #1a1a1a; display: flex; align-items: center; gap: 8px;
    flex-shrink: 0;
}
.mg-preview-header h3 { margin: 0; font-size: 0.9rem; flex: 1; color: var(--imp-blue); }
.mg-preview-iframe { flex: 1; border: none; background: var(--imp-bg); }

/* Peek content styles */
.preview-header {
    display: flex; align-items: flex-start; justify-content: space-between;
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
.preview-loading { display: flex; align-items: center; justify-content: center;
    padding: 40px 0; gap: 12px; color: #888; font-size: 0.9rem; }
.preview-not-found { padding: 30px 0; text-align: center; color: #888; font-size: 0.9rem; }

/* Generic buttons */
.btn {
    padding: 6px 12px; border-radius: 5px; border: none; cursor: pointer;
    font-weight: 700; font-size: 0.82rem; display: inline-flex;
    align-items: center; gap: 5px; white-space: nowrap; transition: opacity 0.15s;
}
.btn:hover { opacity: 0.85; }
.btn-ghost {
    background: transparent; border: 1px solid var(--imp-border); color: var(--imp-text);
}
.btn-ghost:hover { border-color: var(--imp-accent); color: var(--imp-accent); }
.btn-sm { padding: 4px 9px; font-size: 0.75rem; }

/* Toast */
#kg-imp-toast {
    position: fixed; bottom: 20px; right: 20px; z-index: 99999;
    background: #1a1a1a; color: var(--imp-text);
    border: 1px solid var(--imp-border); border-left: 4px solid var(--imp-green);
    border-radius: 6px; padding: 11px 16px; font-size: 0.88rem;
    display: none; box-shadow: 0 4px 16px rgba(0,0,0,0.4);
}
</style>

<!-- ═══ HAMBURGER ═══ -->
<button class="imp-hamburger" id="imp-hamburger" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
</button>

<!-- ═══ FLYOUT OVERLAY ═══ -->
<div class="imp-flyout-overlay" id="imp-flyout-overlay" onclick="closeSidebar()"></div>

<!-- ═══ SIDEBAR ═══ -->
<div class="imp-sidebar" id="imp-sidebar">
    <div class="imp-sidebar-header">
        <span style="font-size:1.1rem;">🔀</span>
        <h2>Import Bridge (AG)</h2>
        <button class="btn btn-ghost btn-sm" onclick="closeSidebar()">✕</button>
    </div>

    <div class="imp-section-head">Source AG Document</div>
    <select id="docSelect" class="imp-doc-select" onchange="onDocChange()">
        <option value="">— Select a document —</option>
        <?php foreach ($agDocs as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <div class="imp-section-head">AG Categories</div>
    <div class="imp-cat-list" id="catList">
        <div class="imp-empty" style="padding:20px;font-size:0.8rem;">Select a document first</div>
    </div>
</div>

<!-- ═══ LAYOUT ═══ -->
<div class="imp-layout">
    <div class="imp-topbar">
        <div class="imp-topbar-left">
            <div class="imp-topbar-title">🔀 KG Staging Import Bridge</div>
            <span style="font-size:0.75rem;color:var(--imp-muted);">
                AG source · Hop-aware subgraph import · Preview before promoting
            </span>
        </div>
        <div class="imp-topbar-right">
            <span class="ai-badge">✨ AI MD Authoring</span>
            <button class="btn btn-ghost btn-sm" onclick="toggleSidebar()">☰ Browse</button>
            <a href="kg_staging.php" class="btn btn-ghost btn-sm">Open KG →</a>
        </div>
    </div>

    <div class="imp-body">

        <!-- ── MIDDLE: AG Node List ── -->
        <div class="imp-middle">
            <div class="imp-middle-head">
                <span id="middleHeadLabel" style="font-size:0.8rem;color:var(--imp-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">
                    AG Nodes
                </span>
                <input type="text" id="entitySearch" class="imp-search"
                    placeholder="Filter nodes…" oninput="filterEntities()" style="display:none;">
            </div>
            <div class="imp-items-list" id="entityList">
                <div class="imp-empty">
                    <span>☰ Open the sidebar</span>
                    <span class="hint">select a document, then pick an AG category</span>
                </div>
            </div>
        </div>

        <!-- ── RIGHT: Staging & Promotion ── -->
        <div class="imp-right">
            <div class="imp-right-head">
                <h3>📥 Staged</h3>
                <span id="stagedCount" style="font-size:0.75rem;color:var(--imp-muted);"></span>
                <button class="btn btn-ghost btn-sm" onclick="previewAll()" title="Dry-run: count nodes to be promoted">🔍 Dry Run</button>
                <button class="btn btn-ghost btn-sm" onclick="clearAllStaged()">Clear</button>
            </div>

            <!-- Dry-run summary banner -->
            <div class="imp-dryrun-row" id="dryrunRow">
                <span class="imp-dryrun-icon">🔍</span>
                <span id="dryrunText"></span>
            </div>

            <div class="imp-staged-list" id="stagedList">
                <div class="imp-empty" id="stagedEmptyMsg">
                    <span>🧩 Nothing staged yet</span>
                    <span class="hint">Click + on any node to stage it</span>
                </div>
            </div>

            <button class="btn-promote-all" id="btnPromoteAll" onclick="promoteAll()" disabled>
                ⚡ Promote All to Staging KG
            </button>
        </div>
    </div>
</div>

<!-- ── Peek Modal ── -->
<div id="peekModal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close" onclick="closePeek()">&times;</button>
        <div id="peekBody">
            <div class="preview-loading"><div class="preview-spinner"></div> Loading…</div>
        </div>
    </div>
</div>

<!-- ── Mini-Graph Preview Modal ── -->
<div id="mgPreviewModal" class="modal-overlay">
    <div class="mg-preview-modal">
        <div class="mg-preview-header">
            <span>🕸️</span>
            <h3 id="mgPreviewTitle">Subgraph Preview</h3>
            <span id="mgPreviewHopsLabel" style="font-size:0.72rem;color:var(--imp-muted);"></span>
            <button class="btn btn-ghost btn-sm" onclick="closeMgPreview()">✕ Close</button>
        </div>
        <iframe id="mgPreviewIframe" class="mg-preview-iframe" src="about:blank"></iframe>
    </div>
</div>

<div id="kg-imp-toast"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// ═══════════════════════════════════════════════════════════════════════
// SIDEBAR
// ═══════════════════════════════════════════════════════════════════════
function toggleSidebar() {
    const s = document.getElementById('imp-sidebar');
    const o = document.getElementById('imp-flyout-overlay');
    const h = document.getElementById('imp-hamburger');
    if (s.classList.contains('open')) {
        s.classList.remove('open'); o.classList.remove('open'); h.classList.remove('open');
    } else {
        s.classList.add('open'); o.classList.add('open'); h.classList.add('open');
    }
}
function closeSidebar() {
    document.getElementById('imp-sidebar').classList.remove('open');
    document.getElementById('imp-flyout-overlay').classList.remove('open');
    document.getElementById('imp-hamburger').classList.remove('open');
}

// ═══════════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════════
let currentDocId   = null;
let currentCatId   = null;
let currentCatName = '';
let allEntities    = [];   // array of {id, name, node_type, description, edge_count}
// stagedItems: [{agNodeId, agDocId, name, cat, nodeType, categoryId, hops, status, nodeId, statusMsg}]
let stagedItems    = [];

const kgCats = <?= json_encode($kgCats) ?>;

// Node type → default kg node type mapping
const NODE_TYPE_MAP = {
    'character':    'character',
    'location':     'location',
    'concept':      'concept',
    'episode':      'episode',
    'arc':          'arc',
    'event':        'event',
    'note':         'note',
    'relationship': 'relationship',
};

const HOPS_DESCRIPTIONS = {
    0: 'Focal node only — no neighbours imported',
    1: 'Focal + direct neighbours (recommended)',
    2: 'Two hops out — richer context, more nodes',
};

// ═══════════════════════════════════════════════════════════════════════
// DOCUMENT CHANGE
// ═══════════════════════════════════════════════════════════════════════
function onDocChange() {
    const sel = document.getElementById('docSelect');
    currentDocId   = sel.value ? parseInt(sel.value, 10) : null;
    currentCatId   = null;
    currentCatName = '';
    allEntities    = [];

    document.getElementById('entitySearch').style.display = 'none';
    document.getElementById('entityList').innerHTML =
        '<div class="imp-empty"><span>👈 Select a category</span></div>';
    document.getElementById('middleHeadLabel').textContent = 'AG Nodes';

    if (!currentDocId) {
        document.getElementById('catList').innerHTML =
            '<div class="imp-empty" style="padding:20px;font-size:0.8rem;">Select a document first</div>';
        return;
    }

    // Load AG categories for this doc
    fetch(`kg_staging_import_api.php?action=get_ag_categories&doc_id=${currentDocId}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') {
                document.getElementById('catList').innerHTML =
                    '<div class="imp-empty">Failed to load categories</div>';
                return;
            }
            renderCatList(res.data);
        });
}

function renderCatList(cats) {
    const list = document.getElementById('catList');
    if (!cats.length) {
        list.innerHTML = '<div class="imp-empty" style="padding:16px;font-size:0.8rem;">No categories found</div>';
        return;
    }
    let html = '';
    cats.forEach(c => {
        
        html += `<div class="imp-cat-item" id="cat-${c.id}" onclick='selectCat(${c.id}, ${JSON.stringify(escHtml(c.name))})'>
            <span>${escHtml(c.name)}</span>
            <span class="imp-cat-count">${c.node_count}</span>
        </div>`;
    });
    // "All nodes" option (cat id 0 = uncategorised or all)
    html = `<div class="imp-cat-item" id="cat-0" onclick="selectCat(0, 'All Nodes')">
        <span>📦 All Nodes</span>
        <span class="imp-cat-count">${cats.reduce((s,c) => s + c.node_count, 0)}</span>
    </div>` + html;
    list.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════════════
// CATEGORY SELECT
// ═══════════════════════════════════════════════════════════════════════
function selectCat(catId, catName) {
    currentCatId   = catId;
    currentCatName = catName;

    document.querySelectorAll('.imp-cat-item').forEach(el => el.classList.remove('active'));
    const catEl = document.getElementById('cat-' + catId);
    if (catEl) catEl.classList.add('active');

    document.getElementById('middleHeadLabel').textContent = catName;
    document.getElementById('entitySearch').style.display = 'block';
    document.getElementById('entitySearch').value = '';
    document.getElementById('entityList').innerHTML =
        '<div class="imp-empty"><div class="preview-spinner"></div></div>';

    const qs = `kg_staging_import_api.php?action=get_ag_nodes&doc_id=${currentDocId}&cat_id=${catId}`;
    fetch(qs).then(r => r.json()).then(res => {
        if (res.status !== 'success') {
            document.getElementById('entityList').innerHTML =
                '<div class="imp-empty">Failed to load nodes</div>';
            return;
        }
        allEntities = res.data;
        renderEntityList(allEntities);
    });

    closeSidebar();
}

// ═══════════════════════════════════════════════════════════════════════
// ENTITY LIST
// ═══════════════════════════════════════════════════════════════════════
function renderEntityList(entities) {
    const list = document.getElementById('entityList');
    if (!entities.length) {
        list.innerHTML = '<div class="imp-empty">No nodes in this category</div>';
        return;
    }
    let html = '';
    entities.forEach(n => {
        const isStaged  = stagedItems.some(s => s.agNodeId === n.id);
        const safeName  = escHtml(n.name);
        const nodeType  = n.node_type || 'note';
        const edgeCount = n.edge_count || 0;
        html += `
        <div class="imp-entity-item${isStaged ? ' staged' : ''}" id="ent-${n.id}">
            <div class="imp-entity-wrap">
                <div class="imp-entity-name" title="${safeName}">${safeName}</div>
                <div class="imp-entity-meta">${escHtml(nodeType)} · ${edgeCount} connection${edgeCount !== 1 ? 's' : ''}</div>
            </div>
            <div class="imp-item-btns">
                <button class="imp-peek-btn" title="Preview node content"
                    onclick="peekNode(event, ${n.id})">👁</button>
                <button class="imp-preview-btn" title="Preview subgraph (1-hop)"
                    onclick='openMgPreview(event, ${n.id}, ${JSON.stringify(safeName)}, 1)'>🕸</button>
                <button class="imp-stage-btn${isStaged ? ' staged' : ''}" title="${isStaged ? 'Already staged' : 'Stage for import'}"
                    onclick='toggleStage(${n.id}, ${JSON.stringify(n.name)}, ${JSON.stringify(nodeType)}, this)'>
                    ${isStaged ? '✓' : '+'}
                </button>
            </div>
        </div>`;
    });
    list.innerHTML = html;
}

function filterEntities() {
    const q = document.getElementById('entitySearch').value.toLowerCase();
    renderEntityList(allEntities.filter(n => n.name.toLowerCase().includes(q)));
}

// ═══════════════════════════════════════════════════════════════════════
// STAGING
// ═══════════════════════════════════════════════════════════════════════
function toggleStage(agNodeId, name, nodeType, btn) {
    const idx = stagedItems.findIndex(s => s.agNodeId === agNodeId);
    if (idx !== -1) {
        stagedItems.splice(idx, 1);
        if (btn) { btn.textContent = '+'; btn.classList.remove('staged'); }
        const el = document.getElementById('ent-' + agNodeId);
        if (el) el.classList.remove('staged');
        renderStagedList();
        return;
    }
    stagedItems.push({
        agNodeId:   agNodeId,
        agDocId:    currentDocId,
        name:       name,
        cat:        currentCatName,
        nodeType:   NODE_TYPE_MAP[nodeType] || 'note',
        categoryId: '',
        hops:       1,          // default 1-hop
        status:     'pending',
        nodeId:     null,
        statusMsg:  'Ready to promote',
    });
    if (btn) { btn.textContent = '✓'; btn.classList.add('staged'); }
    const el = document.getElementById('ent-' + agNodeId);
    if (el) el.classList.add('staged');
    renderStagedList();
    imp_toast('Staged: ' + name);
    hideDryrun();
}

function clearAllStaged() {
    stagedItems = [];
    renderStagedList();
    document.querySelectorAll('.imp-stage-btn').forEach(b => {
        b.textContent = '+'; b.classList.remove('staged');
    });
    document.querySelectorAll('.imp-entity-item').forEach(el => el.classList.remove('staged'));
    hideDryrun();
}

// ═══════════════════════════════════════════════════════════════════════
// STAGED LIST RENDER
// ═══════════════════════════════════════════════════════════════════════
function renderStagedList() {
    const list   = document.getElementById('stagedList');
    const count  = document.getElementById('stagedCount');
    const btnAll = document.getElementById('btnPromoteAll');

    const pending = stagedItems.filter(s => s.status !== 'promoted');
    count.textContent = stagedItems.length ? `(${stagedItems.length})` : '';
    btnAll.disabled   = pending.length === 0;

    if (!stagedItems.length) {
        list.innerHTML = `<div class="imp-empty" id="stagedEmptyMsg">
            <span>🧩 Nothing staged yet</span>
            <span class="hint">Click + on any node to stage it</span>
        </div>`;
        return;
    }

    let html = '';
    stagedItems.forEach((item, idx) => {
        const safeName = escHtml(item.name);
        let folderOpts = '<option value="">— No folder —</option>';
        kgCats.forEach(c => {
            const sel = String(c.id) === String(item.categoryId) ? 'selected' : '';
            folderOpts += `<option value="${c.id}" ${sel}>${escHtml(c.name)}</option>`;
        });

        const isPromoted  = item.status === 'promoted';
        const isPromoting = item.status === 'promoting';
        const cardClass   = isPromoted ? 'promoted' : (isPromoting ? 'promoting' : (item.status === 'error' ? 'error' : ''));
        const statusClass = isPromoted ? 'ok' : (item.status === 'error' ? 'err' : (isPromoting ? 'working' : ''));
        const btnLabel    = isPromoting ? '⏳…' : (isPromoted ? '✓ Done' : 'Promote');

        // Hops description
        const hopsDesc = HOPS_DESCRIPTIONS[item.hops] || '';

        html += `
        <div class="imp-promo-card ${cardClass}" id="promo-card-${idx}">
            <div class="imp-promo-header">
                <span class="imp-promo-cat-pill">${escHtml(item.cat)}</span>
                <span class="imp-promo-name" title="${safeName}">${safeName}</span>
                ${!isPromoted ? `<button class="imp-promo-dismiss" onclick='removeStaged(${idx})'>×</button>` : ''}
                ${isPromoted && item.nodeId ? `<a href="kg_staging.php?node_id=${item.nodeId}" target="_blank" style="font-size:0.7rem;color:var(--imp-green);text-decoration:none;white-space:nowrap;">Open →</a>` : ''}
            </div>

            <!-- Hops selector -->
            ${!isPromoted ? `
            <div class="imp-hops-row">
                <span class="imp-hops-label">🔗 Hops:</span>
                <div class="imp-hops-btns">
                    ${[0,1,2].map(h => `
                    <button class="imp-hop-btn${item.hops === h ? ' active' : ''}"
                        onclick="setHops(${idx}, ${h})" title="${HOPS_DESCRIPTIONS[h]}">${h}</button>`).join('')}
                </div>
                <button class="imp-preview-subgraph-btn"
                    onclick='openMgPreview(event, ${item.agNodeId}, ${JSON.stringify(safeName)}, ${item.hops})'>
                    🕸 Preview
                </button>
            </div>
            <div class="imp-hops-info" id="hops-info-${idx}">${escHtml(hopsDesc)}</div>
            ` : `<div class="imp-hops-info">Imported with ${item.hops} hop${item.hops !== 1 ? 's' : ''}</div>`}

            <div class="imp-promo-body">
                <input type="text" class="imp-promo-input"
                    value="${safeName}" placeholder="KG Node name…"
                    onchange="stagedItems[${idx}].name = this.value"
                    ${isPromoted ? 'readonly' : ''}>
                <div class="imp-promo-row">
                    <select class="imp-promo-select" onchange="stagedItems[${idx}].nodeType = this.value" ${isPromoted ? 'disabled' : ''}>
                        ${['note','character','location','concept','episode','arc','event','relationship'].map(t =>
                            `<option value="${t}" ${item.nodeType === t ? 'selected' : ''}>${t}</option>`
                        ).join('')}
                    </select>
                    <select class="imp-promo-select" onchange="stagedItems[${idx}].categoryId = this.value" ${isPromoted ? 'disabled' : ''}>
                        ${folderOpts}
                    </select>
                </div>
            </div>
            <div class="imp-promo-footer">
                <span class="imp-promo-status ${statusClass}" id="promo-status-${idx}">
                    ${escHtml(item.statusMsg || '')}
                </span>
                <button class="btn-promote" id="promo-btn-${idx}"
                    onclick="promoteOne(${idx})"
                    ${(isPromoted || isPromoting) ? 'disabled' : ''}>
                    ${btnLabel}
                </button>
            </div>
        </div>`;
    });

    list.innerHTML = html;
}

function setHops(idx, hops) {
    stagedItems[idx].hops = hops;
    renderStagedList();
    hideDryrun();
}

function removeStaged(idx) {
    const item = stagedItems[idx];
    if (item) {
        const el = document.getElementById('ent-' + item.agNodeId);
        if (el) {
            el.classList.remove('staged');
            const btn = el.querySelector('.imp-stage-btn');
            if (btn) { btn.textContent = '+'; btn.classList.remove('staged'); }
        }
    }
    stagedItems.splice(idx, 1);
    renderStagedList();
    hideDryrun();
}

// ═══════════════════════════════════════════════════════════════════════
// DRY RUN
// ═══════════════════════════════════════════════════════════════════════
async function previewAll() {
    if (!stagedItems.length) { imp_toast('Nothing staged yet', true); return; }
    const pending = stagedItems.filter(s => s.status === 'pending' || s.status === 'error');
    if (!pending.length) { imp_toast('All items already promoted'); return; }

    imp_toast('Running dry-run estimate…');

    // Ask API to count nodes that would be promoted (no DB write)
    const body = new FormData();
    body.append('action', 'dryrun_estimate');
    body.append('items', JSON.stringify(pending.map(s => ({
        ag_node_id: s.agNodeId,
        ag_doc_id:  s.agDocId,
        hops:       s.hops,
    }))));

    const res = await fetch('kg_staging_import_api.php', { method: 'POST', body }).then(r => r.json());
    if (res.status === 'success') {
        showDryrun(res.focal_count, res.total_nodes, res.total_edges, res.already_in_staging);
    } else {
        imp_toast('Dry-run failed: ' + (res.message || 'unknown error'), true);
    }
}

function showDryrun(focal, nodes, edges, alreadyIn) {
    const row  = document.getElementById('dryrunRow');
    const text = document.getElementById('dryrunText');
    text.innerHTML =
        `<strong>${focal}</strong> focal node${focal !== 1 ? 's' : ''} → ` +
        `<strong>${nodes}</strong> nodes total · <strong>${edges}</strong> edges · ` +
        `<span style="color:var(--imp-muted)">${alreadyIn} already in staging</span>`;
    row.classList.add('visible');
}

function hideDryrun() {
    document.getElementById('dryrunRow').classList.remove('visible');
}

// ═══════════════════════════════════════════════════════════════════════
// PROMOTE
// ═══════════════════════════════════════════════════════════════════════
function promoteOne(idx) {
    const item = stagedItems[idx];
    if (!item || item.status === 'promoted') return;

    item.status    = 'promoting';
    item.statusMsg = '⏳ Importing subgraph…';
    updateCardStatus(idx);

    const fd = new FormData();
    fd.append('action',      'promote_ag_node');
    fd.append('ag_node_id',  item.agNodeId);
    fd.append('ag_doc_id',   item.agDocId);
    fd.append('hops',        item.hops);
    fd.append('node_name',   item.name);
    fd.append('node_type',   item.nodeType);
    fd.append('category_id', item.categoryId || '');

    fetch('kg_staging_import_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                item.status    = 'promoted';
                item.nodeId    = res.focal_node_id;
                item.statusMsg = `✓ ${res.nodes_created} node${res.nodes_created !== 1 ? 's' : ''} · ${res.edges_created} edge${res.edges_created !== 1 ? 's' : ''} created`;
            } else {
                item.status    = 'error';
                item.statusMsg = '✗ ' + (res.message || 'Error');
            }
            renderStagedList();
            imp_toast(item.status === 'promoted'
                ? `✓ ${item.name} promoted (${res.nodes_created || 0} nodes)`
                : `✗ Failed: ${item.name}`, item.status !== 'promoted');
        })
        .catch(() => {
            item.status    = 'error';
            item.statusMsg = '✗ Network error';
            renderStagedList();
        });
}

function promoteAll() {
    const pending = stagedItems
        .map((s, i) => ({ s, i }))
        .filter(({ s }) => s.status === 'pending' || s.status === 'error');
    if (!pending.length) return;
    pending.forEach(({ s, i }, order) => {
        setTimeout(() => promoteOne(i), order * 800);
    });
}

function updateCardStatus(idx) {
    const item   = stagedItems[idx];
    const status = document.getElementById('promo-status-' + idx);
    const btn    = document.getElementById('promo-btn-'    + idx);
    if (status) {
        status.textContent = item.statusMsg || '';
        status.className   = 'imp-promo-status working';
    }
    if (btn) { btn.disabled = true; btn.textContent = '⏳…'; }
}

// ═══════════════════════════════════════════════════════════════════════
// PEEK MODAL (node content preview)
// ═══════════════════════════════════════════════════════════════════════
function peekNode(event, agNodeId) {
    event.stopPropagation();
    const body = document.getElementById('peekBody');
    body.innerHTML = '<div class="preview-loading"><div class="preview-spinner"></div> Loading…</div>';
    document.getElementById('peekModal').classList.add('active');

    fetch(`kg_staging_import_api.php?action=get_ag_node_preview&ag_node_id=${agNodeId}&doc_id=${currentDocId}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                renderPeek(res.data, body);
            } else {
                body.innerHTML = `<div class="preview-not-found">
                    <div style="font-size:2rem;margin-bottom:8px;">🔍</div>
                    <div>${escHtml(res.message || 'No data found.')}</div>
                </div>`;
            }
        })
        .catch(err => {
            body.innerHTML = `<div class="preview-not-found">Network error: ${escHtml(err.message)}</div>`;
        });
}

function renderPeek(node, container) {
    let html = `<div class="preview-header">
        <div>
            <h3 style="margin:0;font-size:1.15rem;color:#fff;">${escHtml(node.name)}</h3>
            ${node.description ? `<div style="margin-top:5px;font-size:0.85rem;color:#aaa;">${escHtml(node.description)}</div>` : ''}
        </div>
        <span class="preview-cat-badge">${escHtml(node.node_type || 'note')}</span>
    </div>`;

    if (node.content) {
        html += `<div class="preview-section">
            <div class="preview-section-title">Content</div>
            <div class="preview-value" style="max-height:240px;overflow-y:auto;white-space:pre-wrap;font-size:0.82rem;color:#bbb;">${escHtml(node.content)}</div>
        </div>`;
    }

    if (node.keywords) {
        html += `<div class="preview-section">
            <div class="preview-section-title">Keywords</div>
            <div class="preview-pill-row">
                ${node.keywords.split(',').map(k => `<span class="preview-pill">${escHtml(k.trim())}</span>`).join('')}
            </div>
        </div>`;
    }

    if (node.connections && node.connections.length) {
        html += `<div class="preview-section">
            <div class="preview-section-title">Connections (${node.connections.length})</div>
            <div style="display:flex;flex-direction:column;gap:4px;max-height:180px;overflow-y:auto;">`;
        node.connections.forEach(c => {
            html += `<div style="padding:4px 10px;border-left:2px solid #444;font-size:0.83rem;color:#ccc;">
                <span style="font-weight:700;">${escHtml(c.label)}</span>
                ${c.relationship ? `<span style="color:#666;margin-left:6px;font-size:0.75rem;">${escHtml(c.relationship)}</span>` : ''}
            </div>`;
        });
        html += `</div></div>`;
    }

    container.innerHTML = html;
}

function closePeek() {
    document.getElementById('peekModal').classList.remove('active');
}

// ═══════════════════════════════════════════════════════════════════════
// MINI-GRAPH PREVIEW MODAL
// ═══════════════════════════════════════════════════════════════════════
function openMgPreview(event, agNodeId, name, hops) {
    event.stopPropagation();
    const src = `mini_graph.php?graph=ag&node_id=${agNodeId}&doc_id=${currentDocId}&hops=${hops}`;
    document.getElementById('mgPreviewIframe').src = src;
    document.getElementById('mgPreviewTitle').textContent = 'Subgraph: ' + name;
    document.getElementById('mgPreviewHopsLabel').textContent = `${hops} hop${hops !== 1 ? 's' : ''}`;
    document.getElementById('mgPreviewModal').classList.add('active');
}

function closeMgPreview() {
    document.getElementById('mgPreviewModal').classList.remove('active');
    document.getElementById('mgPreviewIframe').src = 'about:blank';
}

// Click outside to close modals
document.getElementById('peekModal').addEventListener('click', e => {
    if (e.target === document.getElementById('peekModal')) closePeek();
});
document.getElementById('mgPreviewModal').addEventListener('click', e => {
    if (e.target === document.getElementById('mgPreviewModal')) closeMgPreview();
});

// ═══════════════════════════════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════════════════════════════
function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
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

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeMgPreview();
        closePeek();
        closeSidebar();
    }
});
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content . ($eruda ?? ''), $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
