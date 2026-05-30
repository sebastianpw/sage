<?php
// public/sceki/index.php
// Scene Kitchen v2 — Forge Edition
// PDO-only, no Doctrine. Forge design system.
// Subfolder module at public/sceki/
// ─────────────────────────────────────────────────────
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

// ── Data for UI dropdowns ─────────────────────────────

// Interaction groups
$igStmt = $pdo->query("SELECT DISTINCT interaction_group FROM interactions WHERE active=1 ORDER BY interaction_group");
$interactionGroups = $igStmt->fetchAll(PDO::FETCH_COLUMN);

// Sketch categories
$catStmt = $pdo->query("SELECT id, name FROM sketch_categories ORDER BY id ASC");
$sketchCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Generators (active, PDO)
$genStmt = $pdo->query("SELECT id, title, model FROM generator_config WHERE active=1 ORDER BY list_order ASC, title ASC");
$generators = $genStmt->fetchAll(PDO::FETCH_ASSOC);

// Characters for continuity panel
$charStmt = $pdo->query("SELECT id, name FROM characters ORDER BY name ASC");
$characters = $charStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Scene Kitchen v2';
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Scene Kitchen v2</title>

<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t) document.documentElement.setAttribute('data-theme', t);
    } catch(e) {}
})();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
/* ═══════════════════════════════════════════════════
   FORGE DESIGN SYSTEM — Scene Kitchen v2
═══════════════════════════════════════════════════ */
:root {
    --bg:           #080b10;
    --surface:      #0e1319;
    --card:         #111820;
    --card-hover:   #141e28;
    --border:       #1c2535;
    --border-glow:  #2a3a52;
    --text:         #c8d4e8;
    --text-dim:     #5a6a80;
    --text-bright:  #e8f0ff;
    --amber:        #f5a623;
    --amber-dim:    rgba(245,166,35,0.08);
    --amber-mid:    rgba(245,166,35,0.15);
    --amber-glow:   rgba(245,166,35,0.4);
    --green:        #22d3a0;
    --green-dim:    rgba(34,211,160,0.1);
    --red:          #f05060;
    --red-dim:      rgba(240,80,96,0.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77,166,255,0.1);
    --purple:       #a78bfa;
    --purple-dim:   rgba(167,139,250,0.1);
    --mono:         'Space Mono', 'Fira Mono', monospace;
    --sans:         'Syne', system-ui, sans-serif;
    --radius:       6px;
    --radius-lg:    10px;
}

html[data-theme="light"] {
    --bg:           #f6f8fa;
    --surface:      #e8eaed;
    --card:         #ffffff;
    --card-hover:   #f3f4f6;
    --border:       #d0d7de;
    --border-glow:  #9ca3af;
    --text:         #111827;
    --text-dim:     #6b7280;
    --text-bright:  #000000;
    --amber:        #d97706;
    --amber-dim:    rgba(217,119,6,0.08);
    --amber-mid:    rgba(217,119,6,0.15);
    --amber-glow:   rgba(217,119,6,0.4);
    --green:        #059669;
    --green-dim:    rgba(5,150,105,0.1);
    --red:          #dc2626;
    --red-dim:      rgba(220,38,38,0.1);
    --blue:         #2563eb;
    --blue-dim:     rgba(37,99,235,0.1);
    --purple:       #7c3aed;
    --purple-dim:   rgba(124,58,237,0.1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    height: 100%; background: var(--bg); color: var(--text);
    font-family: var(--sans); font-size: 14px;
    -webkit-font-smoothing: antialiased;
    overflow: hidden;
}

::-webkit-scrollbar { width: 3px; height: 3px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 3px; }

/* ── APP LAYOUT ── */
.sk-app {
    display: grid;
    grid-template-rows: 50px 1fr;
    height: 100vh; height: 100dvh;
    overflow: hidden;
}

/* ── HEADER ── */
.sk-header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 16px; gap: 12px;
    z-index: 100;
}
.sk-logo {
    display: flex; align-items: center; gap: 8px;
    font-family: var(--mono); font-size: 0.78rem; font-weight: 700;
    color: var(--amber); letter-spacing: 2px;
}
.sk-logo-icon {
    width: 26px; height: 26px;
    background: var(--amber-mid); border: 1px solid var(--amber-glow);
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
}
.sk-header-spacer { flex: 1; }
.sk-header-btn {
    width: 32px; height: 32px; border-radius: var(--radius);
    border: 1px solid var(--border); background: transparent;
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; transition: all 0.15s;
}
.sk-header-btn:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.sk-header-btn.active { border-color: var(--amber); color: var(--amber); background: var(--amber-mid); }

/* ── BODY: THREE COLUMNS ── */
.sk-body {
    display: grid;
    grid-template-columns: 260px 1fr 300px;
    overflow: hidden;
    min-height: 0;
}

/* ══════════════════════
   LEFT PANEL — INGREDIENTS
══════════════════════ */
.sk-left {
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow: hidden;
}

/* Source tabs */
.sk-source-tabs {
    display: flex; overflow-x: auto; flex-shrink: 0;
    scrollbar-width: none;
    border-bottom: 1px solid var(--border);
    padding: 0 8px;
}
.sk-source-tabs::-webkit-scrollbar { display: none; }
.sk-stab {
    flex-shrink: 0; padding: 10px 10px;
    font-family: var(--mono); font-size: 0.62rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--text-dim); border-bottom: 2px solid transparent;
    cursor: pointer; transition: all 0.15s; white-space: nowrap;
    user-select: none;
}
.sk-stab:hover { color: var(--text); }
.sk-stab.active { color: var(--amber); border-bottom-color: var(--amber); }

/* Search & filter */
.sk-search-row {
    padding: 8px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    display: flex; flex-direction: column; gap: 6px;
}
.sk-search-input {
    width: 100%; padding: 7px 10px;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: var(--mono); font-size: 0.75rem;
    transition: border-color 0.15s;
}
.sk-search-input:focus { outline: none; border-color: var(--amber); }
.sk-filter-select {
    width: 100%; padding: 5px 8px;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: var(--mono); font-size: 0.72rem;
    display: none; cursor: pointer;
}
.sk-filter-select:focus { outline: none; border-color: var(--amber); }

/* Ingredient grid */
.sk-ing-grid {
    flex: 1; overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px; padding: 8px;
    align-content: start;
}

/* Ingredient card */
.ing-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 8px;
    position: relative;
    transition: border-color 0.15s, transform 0.1s;
    user-select: none;
    display: flex; flex-direction: column; gap: 4px;
    min-height: 82px;
    cursor: default;
}
.ing-card:hover { border-color: var(--border-glow); transform: translateY(-1px); }
.ing-card.in-pot { border-color: var(--amber); background: var(--amber-dim); }

.ing-icon { font-size: 20px; line-height: 1; pointer-events: none; }
.ing-label {
    font-family: var(--sans); font-size: 0.72rem; color: var(--text);
    overflow: hidden; display: -webkit-box;
    -webkit-line-clamp: 3; -webkit-box-orient: vertical;
    line-height: 1.3; pointer-events: none; flex: 1;
}

.ing-drag-handle {
    position: absolute; top: 4px; right: 4px;
    width: 28px; height: 28px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: 4px; cursor: grab;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-dim); font-size: 12px;
    transition: all 0.15s;
}
.ing-drag-handle:active { cursor: grabbing; color: var(--amber); background: var(--amber-dim); }
.ing-card:hover .ing-drag-handle { border-color: var(--border-glow); }

/* Add-on-tap button */
.ing-add-btn {
    position: absolute; bottom: 4px; right: 4px;
    width: 22px; height: 22px;
    background: var(--amber-mid); border: 1px solid var(--amber-glow);
    border-radius: 4px; color: var(--amber); font-size: 12px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; opacity: 0; transition: opacity 0.15s;
}
.ing-card:hover .ing-add-btn { opacity: 1; }

/* KG Subpot card */
.ing-card.kg-subpot {
    border-color: var(--purple);
    background: var(--purple-dim);
}
.ing-card.kg-subpot .ing-drag-handle { border-color: var(--purple); }

/* ══════════════════════
   CENTER PANEL — THE POT + COOK
══════════════════════ */
.sk-center {
    display: flex; flex-direction: column;
    overflow: hidden; background: var(--bg);
}

/* Tab bar inside center */
.sk-center-tabs {
    display: flex; flex-shrink: 0;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    padding: 0 12px;
}
.sk-ctab {
    padding: 10px 14px;
    font-family: var(--mono); font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--text-dim); border-bottom: 2px solid transparent;
    cursor: pointer; transition: all 0.15s; white-space: nowrap;
}
.sk-ctab:hover { color: var(--text); }
.sk-ctab.active { color: var(--amber); border-bottom-color: var(--amber); }

/* Center panels */
.sk-center-panel { flex: 1; overflow: hidden; display: none; flex-direction: column; }
.sk-center-panel.active { display: flex; }

/* ── POT PANEL ── */
.pot-body {
    flex: 1; overflow-y: auto; padding: 12px;
    display: flex; flex-direction: column; gap: 8px;
}

/* Notes */
.pot-notes {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 10px;
}
.pot-notes-label {
    font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;
    display: flex; align-items: center; justify-content: space-between;
}
.pot-notes textarea {
    width: 100%; min-height: 70px; resize: vertical;
    background: transparent; border: none; color: var(--text);
    font-family: var(--mono); font-size: 0.8rem; line-height: 1.5;
    outline: none;
}

/* Pot drop zone */
.pot-drop-area {
    flex: 1;
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 8px;
    min-height: 100px;
    transition: background 0.2s, border-color 0.2s;
    display: flex; flex-wrap: wrap; gap: 6px; align-content: flex-start;
}
.pot-drop-area.drag-over {
    border-color: var(--amber); background: var(--amber-dim);
}
.pot-drop-area.empty::after {
    content: 'Drag or tap ingredients from the left panel';
    font-family: var(--mono); font-size: 0.72rem; color: var(--text-dim);
    display: flex; align-items: center; justify-content: center;
    width: 100%; padding: 20px; text-align: center;
}

/* Pot chips */
.pot-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 8px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 20px;
    font-family: var(--mono); font-size: 0.72rem; color: var(--text);
    cursor: default; transition: border-color 0.15s;
    max-width: 160px;
}
.pot-chip:hover { border-color: var(--border-glow); }
.pot-chip.chip-kg { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.pot-chip-icon { font-size: 12px; flex-shrink: 0; }
.pot-chip-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
.pot-chip-remove {
    flex-shrink: 0; width: 16px; height: 16px;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-dim); cursor: pointer; border-radius: 50%;
    font-size: 10px; transition: all 0.15s;
}
.pot-chip-remove:hover { background: var(--red-dim); color: var(--red); }

/* Section label in pot */
.pot-section-label {
    font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 1.5px;
    padding: 4px 0; border-top: 1px solid var(--border);
    margin-top: 2px; width: 100%;
}

/* Pot footer */
.pot-footer {
    flex-shrink: 0; padding: 10px 12px;
    border-top: 1px solid var(--border);
    background: var(--surface);
    display: flex; gap: 8px;
    align-items: center;
}
.btn-cook {
    flex: 1; padding: 11px;
    background: var(--amber); color: #000;
    border: none; border-radius: var(--radius);
    font-family: var(--mono); font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px;
    cursor: pointer; transition: filter 0.15s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    max-width: 200px;
}
.btn-cook:hover:not(:disabled) { filter: brightness(1.1); }
.btn-cook:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(0.5); }
.btn-cook .cook-spinner {
    width: 12px; height: 12px; border: 2px solid rgba(0,0,0,0.3);
    border-top-color: #000; border-radius: 50%;
    animation: spin 0.7s linear infinite; display: none;
}
.btn-cook.loading .cook-label { display: none; }
.btn-cook.loading .cook-spinner { display: block; }

.btn-sm-action {
    width: 38px; height: 38px; border-radius: var(--radius);
    border: 1px solid var(--border); background: var(--card);
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; transition: all 0.15s;
}
.btn-sm-action:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* ── KG SUBPOT PANEL ── */
.kg-info-box {
    background: var(--purple-dim); border: 1px solid var(--purple);
    border-radius: var(--radius); padding: 10px 12px;
    font-size: 0.8rem; line-height: 1.5; color: var(--text);
}
.kg-info-box strong { color: var(--purple); }

.kg-section-label {
    font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;
    display: flex; align-items: center; gap: 6px;
}
.kg-section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── KG TREE PICKER ── */
.kg-tree-header {
    display: flex; align-items: center; gap: 8px;
    padding: 0 0 6px 0;
    flex-shrink: 0;
}
.kg-tree-header span {
    font-family: var(--mono); font-size: 0.62rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--text-dim); flex: 1;
}
.kg-tree-search {
    width: 100%; padding: 6px 9px;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: var(--mono); font-size: 0.72rem;
    transition: border-color 0.15s; margin-bottom: 6px;
}
.kg-tree-search:focus { outline: none; border-color: var(--purple); }

.kg-tree-wrap {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    max-height: 280px; overflow-y: auto;
    padding: 4px 0;
}
.kg-tree-wrap .sk-loading {
    padding: 14px; text-align: center;
    color: var(--text-dim); font-size: 0.8rem;
}

.sk2-tree-node {
    display: flex; align-items: center; gap: 5px;
    padding: 4px 8px; cursor: pointer; user-select: none;
    font-size: 0.8rem; border-radius: 4px; margin: 1px 3px;
    transition: background 0.1s;
}
.sk2-tree-node:hover { background: rgba(167,139,250,0.08); }

.sk2-tree-node input[type=checkbox] {
    width: 13px; height: 13px;
    accent-color: var(--purple);
    cursor: pointer; flex-shrink: 0; margin: 0;
}
.sk2-tree-node .sk2-node-toggle {
    width: 15px; height: 15px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.6rem; color: var(--text-dim);
    cursor: pointer; border-radius: 3px;
    transition: background 0.1s, transform 0.15s;
}
.sk2-tree-node .sk2-node-toggle:hover { background: rgba(167,139,250,0.15); }
.sk2-tree-node .sk2-node-toggle.open { transform: rotate(90deg); }
.sk2-tree-node .sk2-node-icon { font-size: 0.8rem; flex-shrink: 0; opacity: 0.7; }
.sk2-tree-node .sk2-node-label {
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sk2-tree-node.is-folder > .sk2-node-label { font-weight: 600; color: var(--text); }
.sk2-tree-node.is-node > .sk2-node-label { color: var(--text-dim); }
.sk2-tree-node.is-node.is-checked > .sk2-node-label { color: var(--purple); }
.sk2-tree-children { display: none; }
.sk2-tree-children.open { display: block; }

/* KG subpot chips */
.kg-subpot-chips {
    display: flex; flex-wrap: wrap; gap: 6px;
    min-height: 36px; padding: 6px;
    border: 1px dashed var(--border); border-radius: var(--radius);
}

/* Mini graph trigger on kg chip */
.kg-chip-graph-btn {
    flex-shrink: 0; width: 18px; height: 18px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(167,139,250,0.15);
    border: 1px solid rgba(167,139,250,0.35);
    border-radius: 3px; color: var(--purple);
    font-size: 9px; cursor: pointer;
    transition: all 0.15s; line-height: 1;
}
.kg-chip-graph-btn:hover {
    background: var(--purple); color: #fff;
    border-color: var(--purple);
}

.kg-subpot-preview {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 10px;
    font-family: var(--mono); font-size: 0.72rem;
    color: var(--text-dim); min-height: 60px;
    white-space: pre-wrap; line-height: 1.5;
}

/* ══════════════════════
   RIGHT PANEL — CONTINUITY / SKETCHES
══════════════════════ */
.sk-right {
    background: var(--surface);
    border-left: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow: hidden;
}

.sk-right-section {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    border-bottom: none;
}
.sk-right-section-title {
    font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 1.5px;
    padding: 12px 12px 0 12px; margin-bottom: 6px;
    display: flex; align-items: center; gap: 6px;
}
.sk-right-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.form-row { margin-bottom: 10px; }
.form-label {
    display: block; font-family: var(--mono); font-size: 0.62rem;
    color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;
    margin-bottom: 4px;
}
.form-select, .form-input {
    width: 100%; padding: 7px 10px;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: var(--mono); font-size: 0.75rem;
    transition: border-color 0.15s; appearance: none;
}
.form-select:focus, .form-input:focus { outline: none; border-color: var(--amber); }

/* Continuity Panel */
.continuity-panel {
    display: flex; flex-direction: column; gap: 10px;
}
.char-list-scroll {
    min-height: 60px; max-height: 150px; overflow-y: auto;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 4px;
}
.char-item {
    padding: 5px 8px; display: flex; align-items: center; gap: 8px;
    border-bottom: 1px solid var(--border); cursor: pointer;
    font-family: var(--mono); font-size: 0.75rem; color: var(--text);
    transition: background 0.1s;
}
.char-item:last-child { border-bottom: none; }
.char-item:hover { background: var(--amber-dim); }
.char-item input[type="checkbox"] { accent-color: var(--amber); }

.cont-desc-label {
    font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
}
.cont-desc-area {
    width: 100%; min-height: 80px; resize: vertical;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: var(--mono); font-size: 0.75rem; padding: 8px;
    line-height: 1.5;
}
.cont-desc-area:focus { outline: none; border-color: var(--amber); }

.btn-continuity {
    width: 100%; padding: 10px;
    background: var(--purple); color: #fff;
    border: none; border-radius: var(--radius);
    font-family: var(--mono); font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px;
    cursor: pointer; transition: filter 0.15s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.btn-continuity:hover:not(:disabled) { filter: brightness(1.15); }
.btn-continuity:disabled { opacity: 0.5; cursor: not-allowed; }

/* Sketch history in right panel */
.recent-sketches {
    flex: 1; display: flex; flex-direction: column;
    padding: 12px; background: transparent;
}
.recent-sketch-item {
    padding: 6px 8px; border-radius: var(--radius);
    cursor: pointer; display: flex; align-items: center; gap: 8px;
    transition: background 0.1s;
    border: 1px solid transparent;
}
.recent-sketch-item:hover { background: var(--amber-dim); border-color: var(--border); }
.rsi-id { font-family: var(--mono); font-size: 0.62rem; color: var(--amber); flex-shrink: 0; }
.rsi-name { font-size: 0.78rem; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }

/* ── SAVED RECIPES PANEL ── */
.recipes-panel {
    flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; padding: 10px 12px;
}
.recipe-item {
    padding: 8px 10px; background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; gap: 8px;
}
.recipe-item:hover { border-color: var(--amber); background: var(--amber-dim); }
.recipe-name { flex: 1; font-family: var(--mono); font-size: 0.75rem; color: var(--text); }
.recipe-meta { font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim); }
.recipe-del {
    width: 24px; height: 24px; border-radius: 4px;
    border: 1px solid transparent; background: transparent;
    color: var(--text-dim); cursor: pointer; font-size: 11px;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.recipe-del:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

/* ── MODALS ── */
.forge-modal-overlay {
    position: fixed; inset: 0; z-index: 10000;
    background: rgba(0,0,0,0.8); backdrop-filter: blur(3px);
    display: none; align-items: center; justify-content: center; padding: 16px;
}
.forge-modal-overlay.open { display: flex; }
.forge-modal {
    background: var(--surface); border: 1px solid var(--border-glow);
    border-radius: var(--radius-lg);
    width: 100%; max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    animation: modalIn 0.2s ease;
    display: flex; flex-direction: column;
}
.forge-modal-header {
    padding: 16px 18px; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
}
.forge-modal-title {
    font-family: var(--mono); font-size: 0.75rem; font-weight: 700;
    color: var(--amber); text-transform: uppercase; letter-spacing: 1.5px;
}
.forge-modal-close {
    width: 26px; height: 26px; border-radius: 4px;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-dim); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
    transition: all 0.15s;
}
.forge-modal-close:hover { border-color: var(--red); color: var(--red); }
.forge-modal-body { padding: 18px; }
.forge-modal-footer {
    padding: 12px 18px; border-top: 1px solid var(--border);
    display: flex; gap: 8px; justify-content: flex-end;
}
.btn-forge-primary {
    padding: 7px 16px; background: var(--amber); color: #000;
    border: none; border-radius: var(--radius);
    font-family: var(--mono); font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; cursor: pointer; transition: filter 0.15s;
}
.btn-forge-primary:hover { filter: brightness(1.1); }
.btn-forge-secondary {
    padding: 7px 16px; background: transparent;
    border: 1px solid var(--border); color: var(--text-dim);
    border-radius: var(--radius); font-family: var(--mono); font-size: 0.75rem;
    cursor: pointer; transition: all 0.15s;
}
.btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }

/* Toast */
.forge-toasts {
    position: fixed; bottom: 20px; right: 20px; z-index: 99999;
    display: flex; flex-direction: column; gap: 6px;
    pointer-events: none;
}
.forge-toast {
    padding: 9px 14px; background: var(--card);
    border: 1px solid var(--border); border-radius: var(--radius);
    font-family: var(--mono); font-size: 0.75rem; color: var(--text);
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    animation: toastIn 0.25s ease; pointer-events: all;
    cursor: pointer; max-width: 300px;
    display: flex; align-items: center; gap: 8px;
}
.forge-toast.success { border-color: var(--green); }
.forge-toast.error   { border-color: var(--red); color: var(--red); }
.forge-toast.out     { animation: toastOut 0.2s ease forwards; }

/* Loading state */
.sk-loading {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; color: var(--text-dim); padding: 30px;
    grid-column: 1/-1;
}
.sk-loading .sk-spinner {
    width: 24px; height: 24px; border: 2px solid var(--border);
    border-top-color: var(--amber); border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* Animations */
@keyframes spin      { to { transform: rotate(360deg); } }
@keyframes modalIn   { from { opacity:0; transform:scale(0.96) translateY(-8px); } to { opacity:1; transform:none; } }
@keyframes toastIn   { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
@keyframes toastOut  { to   { opacity:0; transform:translateY(8px); } }

/* Mobile: single column stack */
@media (max-width: 768px) {
    .sk-body {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr auto;
        overflow: hidden;
    }
    .sk-left {
        border-right: none;
        border-bottom: 1px solid var(--border);
        max-height: 220px;
    }
    .sk-right {
        border-left: none;
        border-top: 1px solid var(--border);
        max-height: 280px;
    }
}
</style>

<div class="sk-app">
    <!-- ── HEADER ── -->
    <header class="sk-header">
        <div class="sk-logo">
            <div class="sk-logo-icon">🍳</div>
            <span>SCENE KITCHEN</span>
            <span style="font-size:0.6rem; opacity:0.5; letter-spacing:1px;">v2</span>
        </div>
        <div class="sk-header-spacer"></div>
        <button class="sk-header-btn" id="btnRandom" title="Random Recipe">
            <i class="bi bi-dice-5"></i>
        </button>
        <button class="sk-header-btn" id="btnSaveRecipe" title="Save Recipe">
            <i class="bi bi-bookmark"></i>
        </button>
        <button class="sk-header-btn" id="btnLoadRecipe" title="Load Recipe">
            <i class="bi bi-folder2-open"></i>
        </button>
        <button class="sk-header-btn" id="btnConfig" title="Kitchen Settings">
            <i class="bi bi-gear"></i>
        </button>
        <button class="sk-header-btn" id="btnTheme" title="Toggle Theme" onclick="Forge.toggleTheme()">
            <i class="bi bi-circle-half"></i>
        </button>
    </header>

    <!-- ── BODY ── -->
    <div class="sk-body">

        <!-- ═══ LEFT: INGREDIENTS ═══ -->
        <aside class="sk-left">
            <!-- Source tabs -->
            <div class="sk-source-tabs" id="sourceTabs">
                <div class="sk-stab active" data-source="templates">Templates</div>
                <div class="sk-stab" data-source="interactions">Interactions</div>
                <div class="sk-stab" data-source="style_profiles">Styles</div>
                <div style="width:1px;background:var(--border);margin:8px 2px;flex-shrink:0;"></div>
                <div class="sk-stab" data-source="characters">Characters</div>
                <div class="sk-stab" data-source="locations">Locations</div>
                <div class="sk-stab" data-source="vehicles">Vehicles</div>
                <div class="sk-stab" data-source="artifacts">Artifacts</div>
                <div style="width:1px;background:var(--border);margin:8px 2px;flex-shrink:0;"></div>
                <div class="sk-stab" data-source="anivoc_expressions">Expr</div>
                <div class="sk-stab" data-source="anivoc_lighting">Light</div>
                <div class="sk-stab" data-source="anivoc_color_coding">Color</div>
                <div class="sk-stab" data-source="anivoc_motion_impact">Motion</div>
            </div>

            <!-- Search & filters -->
            <div class="sk-search-row">
                <input type="text" class="sk-search-input" id="ingSearch" placeholder="Search ingredients…">
                <select class="sk-filter-select" id="filterCategory">
                    <option value="">All Categories</option>
                    <?php foreach($sketchCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="sk-filter-select" id="filterGroup">
                    <option value="">All Groups</option>
                    <?php foreach($interactionGroups as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ingredient grid -->
            <div class="sk-ing-grid" id="ingGrid">
                <div class="sk-loading"><div class="sk-spinner"></div></div>
            </div>
        </aside>

        <!-- ═══ CENTER: POT + KG ═══ -->
        <main class="sk-center">
            <div class="sk-center-tabs">
                <div class="sk-ctab active" data-ctab="pot">🫕 The Pot</div>
                <div class="sk-ctab" data-ctab="kg">🌳 KG Subpot</div>
            </div>

            <!-- POT PANEL -->
            <div class="sk-center-panel active" id="panelPot">
                <div class="pot-body">
                    <!-- Notes -->
                    <div class="pot-notes">
                        <div class="pot-notes-label">
                            <span>Chef's Notes</span>
                            <span style="font-size:0.6rem; opacity:0.5;">Optional instructions for the AI</span>
                        </div>
                        <textarea id="chefNotes" placeholder="Add specific scene instructions, mood, or narrative direction here…"></textarea>
                    </div>

                    <!-- Drop zone -->
                    <div class="pot-drop-area empty" id="potDropZone"></div>
                </div>

                <div class="pot-footer">
                    <button class="btn-sm-action" title="Clear Pot" onclick="Kitchen.clearPot()">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn-sm-action" title="Manual Continuity" onclick="Forge.openModal('continuityModal')">
                        <i class="bi bi-person-check"></i>
                    </button>
                    <label style="display:flex; align-items:center; gap:6px; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); cursor:pointer;">
                        <input type="checkbox" id="chkAutoCont" style="accent-color:var(--amber);"> Auto Continuity
                    </label>
                    
                    <div style="flex:1;"></div>

                    <button class="btn-cook" id="btnCook" onclick="Kitchen.cook()">
                        <div class="cook-spinner"></div>
                        <span class="cook-label"><i class="bi bi-fire"></i> Cook Scene</span>
                    </button>
                </div>
            </div>

            <!-- KG SUBPOT PANEL — content provided by graph.php -->
            <div class="sk-center-panel" id="panelKg" style="flex-direction:column;">
                <?php require __DIR__ . '/graph.php'; ?>
            </div>
        </main>

        <!-- ═══ RIGHT: RECENT SKETCHES ═══ -->
        <aside class="sk-right">
            <div class="recent-sketches" id="recentSketchesWrap">
                <div class="sk-right-section-title" id="recentSketchesToggle" style="margin-bottom:10px; cursor:pointer; justify-content:space-between; display:flex; align-items:center;">
                    <span><i class="bi bi-clock-history"></i> Recently Cooked</span>
                    <i class="bi bi-chevron-down" id="recentSketchesIcon" style="display:none;"></i>
                </div>
                <div id="recentSketchListContainer" style="flex:1; overflow-y:auto; padding-right:4px;">
                    <div id="recentSketchList" style="display:flex;flex-direction:column;gap:4px;"></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- ── MODALS ── -->

<!-- Continuity Modal -->
<div class="forge-modal-overlay" id="continuityModal">
    <div class="forge-modal" style="max-width:500px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title"><i class="bi bi-person-check"></i> Scene Continuity</div>
            <button class="forge-modal-close" onclick="Forge.closeModal('continuityModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="forge-modal-body" style="overflow-y:auto; max-height:75vh;">
            <div class="continuity-panel">
                <div>
                    <label class="cont-desc-label">Select Characters</label>
                    <div class="char-list-scroll">
                        <?php foreach($characters as $c): ?>
                        <label class="char-item">
                            <input type="checkbox" name="cont_chars[]" value="<?= (int)$c['id'] ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="cont-desc-label">Active Scene Description</label>
                    <textarea class="cont-desc-area" id="contSceneDesc" rows="4"
                              placeholder="Paste or load a scene description to apply continuity to…"></textarea>
                </div>

                <div>
                    <label class="form-label">Active Sketch ID</label>
                    <input type="number" class="form-input" id="contSketchId" placeholder="Sketch ID (optional)">
                </div>

                <button class="btn-continuity" id="btnContinuity" onclick="Kitchen.runContinuity()">
                    <i class="bi bi-person-check"></i> Apply Continuity
                </button>

                <!-- Quick result -->
                <div id="contResult" style="display:none;">
                    <label class="cont-desc-label">Rewritten Scene</label>
                    <textarea class="cont-desc-area" id="contResultText" rows="5" readonly></textarea>
                    <div style="display:flex;gap:6px;margin-top:6px;">
                        <button class="btn-forge-secondary" style="flex:1;font-size:0.7rem;" onclick="Kitchen.copyContinuityResult()">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                        <button class="btn-forge-secondary" style="flex:1;font-size:0.7rem;" onclick="Kitchen.saveContResult()">
                            <i class="bi bi-save"></i> Save to Sketch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Config Modal -->
<div class="forge-modal-overlay" id="configModal">
    <div class="forge-modal">
        <div class="forge-modal-header">
            <div class="forge-modal-title">Kitchen Settings</div>
            <button class="forge-modal-close" onclick="Forge.closeModal('configModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="forge-modal-body">
            <p style="font-family:var(--mono);font-size:0.75rem;color:var(--text-dim);margin-bottom:12px;">
                Settings persist in localStorage.
            </p>
            <div class="form-row">
                <label class="form-label">Scene Generator</label>
                <select id="descGenId" class="form-select">
                    <?php foreach($generators as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label class="form-label">Name Generator</label>
                <select id="nameGenId" class="form-select">
                    <?php foreach($generators as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label class="form-label">Continuity Generator</label>
                <select id="contGenId" class="form-select">
                    <?php foreach($generators as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" <?= $g['id'] == 111 ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label class="form-label">Max Characters in Random Recipe</label>
                <input type="number" class="form-input" id="cfgMaxChars" value="3" min="1" max="10">
            </div>
            <div class="form-row">
                <label class="form-label">Auto-Expand Pot on Drag</label>
                <select class="form-select" id="cfgAutoExpand">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-primary" onclick="Kitchen.saveConfig(); Forge.closeModal('configModal')">Save</button>
        </div>
    </div>
</div>

<!-- Save Recipe Modal -->
<div class="forge-modal-overlay" id="saveRecipeModal">
    <div class="forge-modal">
        <div class="forge-modal-header">
            <div class="forge-modal-title">Save Recipe</div>
            <button class="forge-modal-close" onclick="Forge.closeModal('saveRecipeModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="forge-modal-body">
            <div class="form-row">
                <label class="form-label">Recipe Name</label>
                <input type="text" class="form-input" id="recipeNameInput" placeholder="My Recipe Name">
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="Forge.closeModal('saveRecipeModal')">Cancel</button>
            <button class="btn-forge-primary" onclick="Kitchen.confirmSaveRecipe()">Save</button>
        </div>
    </div>
</div>

<!-- Load Recipe Modal -->
<div class="forge-modal-overlay" id="loadRecipeModal">
    <div class="forge-modal" style="max-width:500px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title">Load Recipe</div>
            <button class="forge-modal-close" onclick="Forge.closeModal('loadRecipeModal')"><i class="bi bi-x"></i></button>
        </div>
        <div class="forge-modal-body" style="max-height:60vh;overflow-y:auto;">
            <div id="recipeListContainer">
                <div class="sk-loading"><div class="sk-spinner"></div></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                <button class="btn-forge-secondary" onclick="Kitchen.changeRecipePage(-1)">← Prev</button>
                <span style="font-family:var(--mono);font-size:0.7rem;color:var(--text-dim);" id="recipePageInfo">Page 1</span>
                <button class="btn-forge-secondary" onclick="Kitchen.changeRecipePage(1)">Next →</button>
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="Forge.closeModal('loadRecipeModal')">Close</button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="forge-toasts" id="forgeToasts"></div>

<script>
// ═══════════════════════════════════════════════════════
// FORGE UTILITIES
// ═══════════════════════════════════════════════════════
const Forge = (() => {
    function toast(msg, type = 'info', ms = 3000) {
        const c = document.getElementById('forgeToasts');
        const t = document.createElement('div');
        t.className = `forge-toast ${type}`;
        t.innerHTML = `<i class="bi bi-${type==='success'?'check-circle':type==='error'?'exclamation-circle':'info-circle'}"></i> ${escHtml(msg)}`;
        t.onclick = () => dismiss(t);
        c.appendChild(t);
        setTimeout(() => dismiss(t), ms);
        function dismiss(el) { el.classList.add('out'); setTimeout(() => el.remove(), 250); }
    }

    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    function openModal(id)  { document.getElementById(id).classList.add('open'); }

    function toggleTheme() {
        const html = document.documentElement;
        const cur  = html.getAttribute('data-theme') || '';
        const next = cur === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        try { localStorage.setItem('spw_theme', next); } catch(e) {}
    }

    function escHtml(s) {
        const d = document.createElement('div'); d.textContent = String(s||''); return d.innerHTML;
    }

    // Close on overlay click
    document.querySelectorAll('.forge-modal-overlay').forEach(o => {
        o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.forge-modal-overlay.open').forEach(m => m.classList.remove('open'));
    });

    return { toast, closeModal, openModal, toggleTheme, escHtml };
})();

// ═══════════════════════════════════════════════════════
// KITCHEN — Main Application
// ═══════════════════════════════════════════════════════
const Kitchen = (() => {
    'use strict';

    const API = 'api.php';

    // ── State ────────────────────────────────────────────
    let currentSource  = 'templates';
    let potItems       = [];   // [{type, id, label, icon, isKgSubpot}]
    let kgNodes        = [];   // [{id, name, node_type}]
    let recipePage     = 1;
    let searchTimeout  = null;
    let config         = { maxChars: 3 };
    let recentSketches = [];

    // KG tree state
    let kgTreeRaw      = [];   // raw tree from api
    let kgTreeFilter   = '';   // current search filter

    const KG_TREE_OPEN_KEY = 'sk2_kg_tree_open';

    // ── Init ─────────────────────────────────────────────
    function init() {
        loadConfig();
        loadIngredients('templates');
        loadRecentSketches();
        bindEvents();
    }

    function loadConfig() {
        try {
            const saved = JSON.parse(localStorage.getItem('sk2_config') || '{}');
            config = Object.assign({ maxChars: 3 }, saved);

            const descGenId = localStorage.getItem('sk2_desc_gen');
            const nameGenId = localStorage.getItem('sk2_name_gen');
            const contGenId = localStorage.getItem('sk2_cont_gen');

            if (descGenId) $('#descGenId').val(descGenId);
            if (nameGenId) $('#nameGenId').val(nameGenId);
            if (contGenId) $('#contGenId').val(contGenId);

            $('#cfgMaxChars').val(config.maxChars);

            if (localStorage.getItem('sk2_auto_cont') === '1') {
                $('#chkAutoCont').prop('checked', true);
            }
        } catch(e) {}
    }

    function saveConfig() {
        config.maxChars = parseInt($('#cfgMaxChars').val()) || 3;
        try { localStorage.setItem('sk2_config', JSON.stringify(config)); } catch(e) {}
        Forge.toast('Config saved', 'success', 1500);
    }

    function bindEvents() {
        // Source tabs
        $('#sourceTabs').on('click', '.sk-stab', function() {
            const source = $(this).data('source');
            $('#sourceTabs .sk-stab').removeClass('active');
            $(this).addClass('active');
            currentSource = source;
            $('#filterCategory').toggle(source === 'templates').css('display', source === 'templates' ? 'block' : 'none');
            $('#filterGroup').toggle(source === 'interactions').css('display', source === 'interactions' ? 'block' : 'none');
            $('#ingSearch').val('');
            loadIngredients(source);
        });

        // Center tabs
        $('.sk-ctab').on('click', function() {
            const tab = $(this).data('ctab');
            $('.sk-ctab').removeClass('active');
            $(this).addClass('active');
            $('.sk-center-panel').removeClass('active');
            $(`#panel${tab.charAt(0).toUpperCase()+tab.slice(1)}`).addClass('active');
            // Load KG tree when switching to kg tab
            if (tab === 'kg' && kgTreeRaw.length === 0) {
                loadKgTree();
            }
        });

        // Search
        $('#ingSearch').on('input', function() {
            clearTimeout(searchTimeout);
            const term = $(this).val().toLowerCase();
            searchTimeout = setTimeout(() => {
                $('#ingGrid .ing-card').each(function() {
                    const kw = ($(this).data('kw') || '').toLowerCase();
                    $(this).toggle(kw.includes(term));
                });
            }, 150);
        });

        // Filters
        $('#filterCategory').on('change', () => loadIngredients('templates', { category_id: $('#filterCategory').val() }));
        $('#filterGroup').on('change',    () => loadIngredients('interactions', { group: $('#filterGroup').val() }));

        // Pot drop zone
        Sortable.create(document.getElementById('potDropZone'), {
            group: 'sk2',
            animation: 150,
            onAdd: function(evt) {
                const el = evt.item;
                const item = {
                    type:  el.dataset.type,
                    id:    el.dataset.id,
                    label: el.dataset.label,
                    icon:  el.dataset.icon
                };
                el.remove();
                addToPot(item);
            },
            onSort: function() { updatePotFromDOM(); },
        });

        // Header buttons
        $('#btnRandom').on('click',      () => randomRecipe());
        $('#btnSaveRecipe').on('click',  () => openSaveRecipe());
        $('#btnLoadRecipe').on('click',  () => openLoadRecipe());
        $('#btnConfig').on('click',      () => Forge.openModal('configModal'));

        // Generator selects persist
        $('#descGenId, #nameGenId, #contGenId').on('change', function() {
            let base = this.id.replace('GenId', '');
            localStorage.setItem('sk2_' + base + '_gen', $(this).val());
        });

        // Auto Continuity toggle save
        $('#chkAutoCont').on('change', function() {
            localStorage.setItem('sk2_auto_cont', $(this).is(':checked') ? '1' : '0');
        });

        // KG include edges
        $('#kgIncludeEdges').on('change', () => updateKgPreview());

        // KG tree search filter
        let kgSearchTimeout = null;
        $('#kgTreeSearch').on('input', function() {
            clearTimeout(kgSearchTimeout);
            const val = $(this).val();
            kgSearchTimeout = setTimeout(() => {
                kgTreeFilter = val.toLowerCase().trim();
                renderKgTree();
            }, 180);
        });

        // Recently Cooked Toggle
        const recentState = localStorage.getItem('sk2_recent_open');
        if (recentState === '0') {
            $('#recentSketchListContainer').hide();
            $('#recentSketchesIcon').removeClass('bi-chevron-up').addClass('bi-chevron-down');
        }

        $('#recentSketchesToggle').on('click', function() {
            const wrap = $('#recentSketchListContainer');
            const icon = $('#recentSketchesIcon');
            if (wrap.is(':visible')) {
                wrap.slideUp(150);
                icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                localStorage.setItem('sk2_recent_open', '0');
            } else {
                wrap.slideDown(150);
                icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
                localStorage.setItem('sk2_recent_open', '1');
            }
        });
    }

    // ── Ingredients ────────────────────────────────────
    function loadIngredients(type, filters = {}) {
        const grid = $('#ingGrid');
        grid.html('<div class="sk-loading"><div class="sk-spinner"></div></div>');

        $.post(API, { action: 'fetch_ingredients', type, filters }, function(res) {
            if (!res.ok) { grid.html('<div class="sk-loading" style="color:var(--red);">Error loading</div>'); return; }
            renderIngredients(res.data || []);
        }, 'json').fail(() => {
            grid.html('<div class="sk-loading" style="color:var(--red);">Network error</div>');
        });
    }

    function renderIngredients(items) {
        const grid = $('#ingGrid');
        if (!items.length) {
            grid.html('<div class="sk-loading" style="font-family:var(--mono);font-size:0.75rem;">No items found.</div>');
            return;
        }
        const inPot = new Set(potItems.map(p => `${p.type}__${p.id}`));
        let html = '';
        items.forEach(item => {
            const key = `${item.type}__${item.id}`;
            const kw  = [item.type, item.label, (item.data && item.data.core_idea) || ''].join(' ');
            html += `
            <div class="ing-card ${inPot.has(key) ? 'in-pot' : ''}"
                 data-type="${esc(item.type)}" data-id="${esc(item.id)}"
                 data-label="${esc(item.label)}" data-icon="${esc(item.icon)}"
                 data-kw="${esc(kw)}">
                <div class="ing-drag-handle"><i class="bi bi-grip-vertical"></i></div>
                <span class="ing-icon">${item.icon}</span>
                <div class="ing-label">${esc(item.label)}</div>
                <div class="ing-add-btn" onclick="Kitchen.tapAddIngredient(this)">
                    <i class="bi bi-plus"></i>
                </div>
            </div>`;
        });
        grid.html(html);

        Sortable.create(document.getElementById('ingGrid'), {
            group: { name: 'sk2', pull: 'clone', put: false },
            sort: false, animation: 150,
            handle: '.ing-drag-handle',
        });
    }

    function tapAddIngredient(el) {
        const card = el.closest('.ing-card');
        addToPot({
            type:  card.dataset.type,
            id:    card.dataset.id,
            label: card.dataset.label,
            icon:  card.dataset.icon,
        });
    }

    // ── Pot Management ─────────────────────────────────
    function addToPot(item) {
        const exists = potItems.some(p => p.type === item.type && p.id === item.id);
        if (exists) return;

        potItems.push(item);
        renderPotChip(item);
        updatePotEmptyState();
    }

    function renderPotChip(item, container) {
        const zone  = container || document.getElementById('potDropZone');
        const chip  = document.createElement('div');
        chip.className  = `pot-chip${item.isKgSubpot ? ' chip-kg' : ''}`;
        chip.dataset.type = item.type;
        chip.dataset.id   = item.id;

        chip.innerHTML = `
            <span class="pot-chip-icon">${item.icon}</span>
            <span class="pot-chip-label" title="${esc(item.label)}">${esc(item.label)}</span>
            <span class="pot-chip-remove"><i class="bi bi-x"></i></span>`;

        chip.querySelector('.pot-chip-remove').addEventListener('click', () => {
            chip.remove();
            potItems = potItems.filter(p => !(p.type === item.type && p.id === item.id));
            updatePotEmptyState();
        });

        zone.appendChild(chip);
    }

    function updatePotFromDOM() {
        const chips = document.querySelectorAll('#potDropZone .pot-chip');
        potItems = Array.from(chips).map(c => ({
            type:  c.dataset.type,
            id:    c.dataset.id,
            label: c.querySelector('.pot-chip-label').textContent,
            icon:  c.querySelector('.pot-chip-icon').textContent,
        }));
        updatePotEmptyState();
    }

    function updatePotEmptyState() {
        const zone = document.getElementById('potDropZone');
        zone.classList.toggle('empty', potItems.length === 0 && !document.querySelector('#potDropZone .pot-chip'));
    }

    function clearPot() {
        document.getElementById('potDropZone').innerHTML = '';
        potItems = [];
        updatePotEmptyState();
        $('#chefNotes').val('');
        kgNodes = [];
        renderKgChips();
        updateKgPreview();
    }

    function collectPotIngredients() {
        updatePotFromDOM();
        return potItems;
    }

    // ── KG Tree ─────────────────────────────────────────
    function loadKgTree() {
        $('#kgTreeWrap').html('<div class="sk-loading" style="padding:14px;"><div class="sk-spinner"></div></div>');
        $.get('../kg_api.php?action=fetch_tree', function(res) {
            if (!res.ok) {
                $('#kgTreeWrap').html('<div style="padding:10px;font-family:var(--mono);font-size:0.75rem;color:var(--red);">Failed to load tree</div>');
                return;
            }
            kgTreeRaw = res.tree || [];
            renderKgTree();
        }, 'json').fail(() => {
            $('#kgTreeWrap').html('<div style="padding:10px;font-family:var(--mono);font-size:0.75rem;color:var(--red);">Network error</div>');
        });
    }

    function kgNodeIcon(nodeType) {
        const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' };
        return map[nodeType] || '📝';
    }

    function kgTreeSaveOpenState() {
        const open = [];
        document.querySelectorAll('#kgTreeWrap .sk2-tree-children.open').forEach(el => {
            open.push(el.id.replace('sk2kids-', ''));
        });
        try { localStorage.setItem(KG_TREE_OPEN_KEY, JSON.stringify(open)); } catch(e) {}
    }

    function kgTreeLoadOpenState() {
        try {
            const raw = localStorage.getItem(KG_TREE_OPEN_KEY);
            if (raw) return new Set(JSON.parse(raw));
        } catch(e) {}
        return null;
    }

    function renderKgTree() {
        const wrap = document.getElementById('kgTreeWrap');
        if (!kgTreeRaw.length) {
            wrap.innerHTML = '<div style="padding:10px;font-family:var(--mono);font-size:0.75rem;color:var(--text-dim);">No nodes in graph.</div>';
            return;
        }

        const checkedIds = new Set(kgNodes.map(n => 'n_' + n.id));
        const filter = kgTreeFilter;

        // Build child map
        const childMap = {};
        kgTreeRaw.forEach(n => {
            const p = n.parent || '#';
            if (!childMap[p]) childMap[p] = [];
            childMap[p].push(n);
        });

        // If filter active, find which node jsIds match
        let matchingJsIds = null;
        if (filter) {
            matchingJsIds = new Set();
            kgTreeRaw.forEach(n => {
                if (n.type === 'node' && n.text.toLowerCase().includes(filter)) {
                    matchingJsIds.add(n.id);
                    // Add ancestors
                    let cur = n;
                    while (cur.parent && cur.parent !== '#') {
                        matchingJsIds.add(cur.parent);
                        cur = kgTreeRaw.find(x => x.id === cur.parent) || { parent: '#' };
                    }
                }
            });
        }

        function buildLevel(parentId, depth) {
            const children = (childMap[parentId] || []).filter(n => {
                if (!matchingJsIds) return true;
                return matchingJsIds.has(n.id);
            });
            if (!children.length) return '';
            const indent = depth * 12;
            let html = '';
            children.forEach(node => {
                const isFolder = node.type === 'folder';
                const jsId = node.id;
                const isChecked = !isFolder && checkedIds.has(jsId);
                const hasKids = !!(childMap[jsId] && childMap[jsId].filter(c => !matchingJsIds || matchingJsIds.has(c.id)).length);
                const icon = isFolder ? '📁' : kgNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');

                const toggleBtn = (isFolder && hasKids)
                    ? `<span class="sk2-node-toggle" data-jid="${jsId}" onclick="Kitchen.kgToggleFolder(this)">▶</span>`
                    : `<span style="width:15px;display:inline-block;flex-shrink:0;"></span>`;

                const cbOrSpacer = isFolder
                    ? `<span style="width:13px;display:inline-block;flex-shrink:0;"></span>`
                    : `<input type="checkbox" ${isChecked ? 'checked' : ''}
                             data-jid="${jsId}"
                             data-dbid="${node.data.db_id}"
                             data-name="${esc(node.text)}"
                             data-type="${esc(node.data.node_type || 'note')}"
                             onchange="Kitchen.kgCheckNode(this)">`;

                html += `
                <div class="sk2-tree-node ${isFolder ? 'is-folder' : 'is-node' + (isChecked ? ' is-checked' : '')}"
                     style="padding-left:${8 + indent}px;">
                    ${toggleBtn}
                    ${cbOrSpacer}
                    <span class="sk2-node-icon">${icon}</span>
                    <span class="sk2-node-label">${esc(node.text)}</span>
                </div>`;

                if (isFolder && hasKids) {
                    html += `<div class="sk2-tree-children" id="sk2kids-${jsId}">`;
                    html += buildLevel(jsId, depth + 1);
                    html += `</div>`;
                }
            });
            return html;
        }

        wrap.innerHTML = buildLevel('#', 0);

        // Restore fold state (only when no filter active).
        // Default is all closed; savedOpen is the set of jsIds that should be open.
        if (!filter) {
            const savedOpen = kgTreeLoadOpenState();
            if (savedOpen && savedOpen.size > 0) {
                wrap.querySelectorAll('.sk2-tree-children').forEach(el => {
                    const jsId = el.id.replace('sk2kids-', '');
                    if (savedOpen.has(jsId)) {
                        el.classList.add('open');
                        const toggleEl = wrap.querySelector(`.sk2-node-toggle[data-jid="${jsId}"]`);
                        if (toggleEl) toggleEl.classList.add('open');
                    }
                });
            }
        }
    }

    function kgToggleFolder(btn) {
        const jsId = btn.dataset.jid;
        const kids = document.getElementById('sk2kids-' + jsId);
        if (!kids) return;
        kids.classList.toggle('open');
        btn.classList.toggle('open');
        kgTreeSaveOpenState();
    }

    function kgCheckNode(cb) {
        const jsId  = cb.dataset.jid;
        const dbId  = parseInt(cb.dataset.dbid);
        const name  = cb.dataset.name;
        const type  = cb.dataset.type;

        const row = cb.closest('.sk2-tree-node');

        if (cb.checked) {
            if (!kgNodes.some(n => n.id === dbId)) {
                kgNodes.push({ id: dbId, name, node_type: type });
            }
            if (row) row.classList.add('is-checked');
        } else {
            kgNodes = kgNodes.filter(n => n.id !== dbId);
            if (row) row.classList.remove('is-checked');
        }
        renderKgChips();
        updateKgPreview();
    }

    // ── KG Subpot ──────────────────────────────────────
    function addKgNode(node) {
        if (kgNodes.some(n => n.id === node.id)) return;
        kgNodes.push(node);
        // Sync checkboxes in tree if available
        const cb = document.querySelector(`#kgTreeWrap input[data-dbid="${node.id}"]`);
        if (cb) {
            cb.checked = true;
            const row = cb.closest('.sk2-tree-node');
            if (row) row.classList.add('is-checked');
        }
        renderKgChips();
        updateKgPreview();
        if (typeof SkGraph !== 'undefined') SkGraph.onKgNodesChanged();
    }

    function removeKgNode(id) {
        kgNodes = kgNodes.filter(n => n.id !== id);
        // Uncheck in tree if rendered
        const cb = document.querySelector(`#kgTreeWrap input[data-dbid="${id}"]`);
        if (cb) {
            cb.checked = false;
            const row = cb.closest('.sk2-tree-node');
            if (row) row.classList.remove('is-checked');
        }
        renderKgChips();
        updateKgPreview();
        if (typeof SkGraph !== 'undefined') SkGraph.onKgNodesChanged();
    }

    function renderKgChips() {
        if (!kgNodes.length) {
            $('#kgSubpotChips').html('<span style="font-family:var(--mono);font-size:0.7rem;color:var(--text-dim);padding:4px;">No nodes selected — use tree or graph</span>');
            return;
        }
        let html = kgNodes.map(n => `
            <span class="pot-chip chip-kg" style="max-width:none;">
                <span class="pot-chip-icon">🌿</span>
                <span class="pot-chip-label" style="max-width:120px;">${esc(n.name)}</span>
                <button class="kg-chip-graph-btn" title="Mini Graph" onclick="Kitchen.openKgNodeGraph(${n.id})" type="button">
                    <i class="bi bi-diagram-2-fill"></i>
                </button>
                <span class="pot-chip-remove" onclick="Kitchen.removeKgNode(${n.id})"><i class="bi bi-x"></i></span>
            </span>`).join('');
        $('#kgSubpotChips').html(html);
    }

    function openKgNodeGraph(nodeId) {
        if (typeof SkGraph !== 'undefined' && SkGraph.openModal) {
            SkGraph.openModal(parseInt(nodeId, 10), 1);
        } else if (typeof window.showMiniGraphModal === 'function') {
            window.showMiniGraphModal({ graph: 'kg', node_id: nodeId, hops: 1 });
        } else {
            window.open('../mini_graph.php?graph=kg&node_id=' + nodeId, '_blank');
        }
    }

    function updateKgPreview() {
        if (!kgNodes.length) {
            $('#kgSubpotPreview').text('Select nodes to preview the subpot prompt…');
            return;
        }
        const includeEdges = $('#kgIncludeEdges').is(':checked');
        $.post(API, {
            action: 'kg_subpot_preview',
            node_ids: kgNodes.map(n => n.id),
            include_edges: includeEdges ? 1 : 0
        }, function(res) {
            if (res.ok) $('#kgSubpotPreview').text(res.preview);
        }, 'json');
    }

    function addKgSubpotToPot() {
        if (!kgNodes.length) { Forge.toast('No KG nodes selected', 'error'); return; }
        const label = 'KG Subplot: ' + kgNodes.map(n => n.name).join(', ');
        const item = {
            type: '_kg_subpot',
            id: 'kg_' + kgNodes.map(n => n.id).join('_'),
            label,
            icon: '🌳',
            isKgSubpot: true,
            nodeIds: kgNodes.map(n => n.id),
            includeEdges: $('#kgIncludeEdges').is(':checked'),
        };
        addToPot(item);
        Forge.toast('KG Subpot added to Pot', 'success');
        $('.sk-ctab[data-ctab="pot"]').click();
    }

    // ── Cook ───────────────────────────────────────────
    function cook() {
        const ingredients = collectPotIngredients();
        const notes       = $('#chefNotes').val().trim();
        if (!ingredients.length && !notes) { Forge.toast('The pot is empty!', 'error'); return; }

        const btn = document.getElementById('btnCook');
        btn.disabled = true;
        btn.classList.add('loading');

        $.post(API, {
            action:             'cook',
            ingredients:        ingredients,
            custom_instruction: notes,
            desc_gen_id:        $('#descGenId').val(),
            name_gen_id:        $('#nameGenId').val(),
        }, function(res) {
            if (res.ok) {
                const isAuto = $('#chkAutoCont').is(':checked');
                const charIds = potItems.filter(i => i.type === 'characters').map(i => parseInt(i.id));

                if (isAuto && charIds.length > 0) {
                    Forge.toast(`Cooked #${res.sketch_id}. Running auto-continuity...`, 'info');
                    
                    $.post(API, {
                        action: 'run_continuity',
                        character_ids: charIds,
                        generator_id: $('#contGenId').val(),
                        description: res.description,
                        sketch_id: res.sketch_id
                    }, function(cRes) {
                        btn.disabled = false;
                        btn.classList.remove('loading');
                        if (cRes.ok) {
                            Forge.toast(`Auto-Continuity applied to #${res.sketch_id}!`, 'success', 4000);
                            addRecentSketch(res.sketch_id, res.sketch_name);
                            $('#contSceneDesc').val(cRes.new_description);
                            $('#contSketchId').val(res.sketch_id);
                        } else {
                            Forge.toast('Auto-Continuity failed: ' + (cRes.error || ''), 'error');
                            addRecentSketch(res.sketch_id, res.sketch_name);
                        }
                    }, 'json').fail(() => {
                        btn.disabled = false;
                        btn.classList.remove('loading');
                        Forge.toast('Network error during auto-continuity', 'error');
                        addRecentSketch(res.sketch_id, res.sketch_name);
                    });
                } else {
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    Forge.toast(`Cooked! Sketch #${res.sketch_id} — "${res.sketch_name}"`, 'success', 4000);
                    if (res.description) {
                        $('#contSceneDesc').val(res.description);
                        $('#contSketchId').val(res.sketch_id);
                    }
                    addRecentSketch(res.sketch_id, res.sketch_name);
                }
            } else {
                btn.disabled = false;
                btn.classList.remove('loading');
                Forge.toast('Cook error: ' + (res.error || 'Unknown'), 'error', 5000);
            }
        }, 'json').fail(() => {
            btn.disabled = false;
            btn.classList.remove('loading');
            Forge.toast('Network error during cooking', 'error');
        });
    }

    // ── Continuity ──────────────────────────────────────
    function runContinuity() {
        const charIds = Array.from(document.querySelectorAll('input[name="cont_chars[]"]:checked'))
                            .map(c => parseInt(c.value));
        const genId   = parseInt($('#contGenId').val());
        const desc    = $('#contSceneDesc').val().trim();

        if (!charIds.length) { Forge.toast('Select at least one character', 'error'); return; }
        if (!desc)            { Forge.toast('Paste a scene description first', 'error'); return; }

        const btn = document.getElementById('btnContinuity');
        btn.disabled = true;

        $.post(API, {
            action:        'run_continuity',
            character_ids: charIds,
            generator_id:  genId,
            description:   desc,
            sketch_id:     parseInt($('#contSketchId').val()) || 0,
        }, function(res) {
            btn.disabled = false;
            if (res.ok) {
                $('#contResultText').val(res.new_description);
                $('#contResult').show();
                Forge.toast('Continuity applied!', 'success');
            } else {
                Forge.toast('Continuity error: ' + (res.error || 'Unknown'), 'error', 5000);
            }
        }, 'json').fail(() => {
            btn.disabled = false;
            Forge.toast('Network error', 'error');
        });
    }

    function copyContinuityResult() {
        const text = $('#contResultText').val();
        if (!text) return;
        navigator.clipboard.writeText(text)
            .then(() => Forge.toast('Copied!', 'success', 1500))
            .catch(() => Forge.toast('Copy failed', 'error'));
    }

    function saveContResult() {
        const text     = $('#contResultText').val().trim();
        const sketchId = parseInt($('#contSketchId').val()) || 0;
        if (!text)     { Forge.toast('No result to save', 'error'); return; }
        if (!sketchId) { Forge.toast('Set a Sketch ID first', 'error'); return; }

        $.post(API, { action: 'save_continuity_result', sketch_id: sketchId, description: text }, function(res) {
            if (res.ok) Forge.toast(`Saved to Sketch #${sketchId}`, 'success');
            else        Forge.toast('Save failed: ' + (res.error || ''), 'error');
        }, 'json');
    }

    // ── Random Recipe ──────────────────────────────────
    function randomRecipe() {
        if (!confirm('Replace current pot with a random recipe?')) return;
        $.post(API, { action: 'random_recipe', max_chars: config.maxChars }, function(res) {
            if (!res.ok) return;
            clearPot();
            (res.ingredients || []).forEach(i => addToPot(i));
            Forge.toast('Random recipe loaded!', 'success', 1500);
        }, 'json');
    }

    // ── Save / Load Recipes ────────────────────────────
    function openSaveRecipe() {
        if (!collectPotIngredients().length && !$('#chefNotes').val().trim()) {
            Forge.toast('Nothing to save', 'error');
            return;
        }
        Forge.openModal('saveRecipeModal');
        $('#recipeNameInput').focus();
    }

    function confirmSaveRecipe() {
        const name = $('#recipeNameInput').val().trim();
        if (!name) { Forge.toast('Enter a name', 'error'); return; }

        $.post(API, {
            action:      'save_recipe',
            name,
            ingredients: collectPotIngredients(),
            notes:       $('#chefNotes').val().trim(),
        }, function(res) {
            if (res.ok) {
                Forge.toast('Recipe saved!', 'success');
                Forge.closeModal('saveRecipeModal');
                $('#recipeNameInput').val('');
            } else {
                Forge.toast('Save failed: ' + (res.error || ''), 'error');
            }
        }, 'json');
    }

    function openLoadRecipe() {
        Forge.openModal('loadRecipeModal');
        recipePage = 1;
        loadRecipeList();
    }

    function loadRecipeList() {
        $('#recipeListContainer').html('<div class="sk-loading"><div class="sk-spinner"></div></div>');
        $.post(API, { action: 'list_recipes', page: recipePage }, function(res) {
            if (!res.ok) return;
            $('#recipePageInfo').text(`Page ${res.page} / ${Math.max(1, res.pages)}`);
            if (!res.rows.length) {
                $('#recipeListContainer').html('<div style="font-family:var(--mono);font-size:0.75rem;color:var(--text-dim);padding:16px;text-align:center;">No saved recipes.</div>');
                return;
            }
            let html = '';
            res.rows.forEach(r => {
                html += `
                <div class="recipe-item">
                    <div style="flex:1" onclick="Kitchen.loadRecipe(${r.id})">
                        <div class="recipe-name">${esc(r.name)}</div>
                        <div class="recipe-meta">${esc(r.created_at)}</div>
                    </div>
                    <button class="recipe-del" onclick="Kitchen.deleteRecipe(${r.id})"><i class="bi bi-trash"></i></button>
                </div>`;
            });
            $('#recipeListContainer').html(html);
        }, 'json');
    }

    function changeRecipePage(d) {
        recipePage = Math.max(1, recipePage + d);
        loadRecipeList();
    }

    function loadRecipe(id) {
        if (!confirm('Load this recipe? Current pot will be cleared.')) return;
        $.post(API, { action: 'load_recipe', id }, function(res) {
            if (!res.ok) return;
            clearPot();
            (res.ingredients || []).forEach(i => addToPot(i));
            if (res.notes) $('#chefNotes').val(res.notes);
            Forge.closeModal('loadRecipeModal');
            Forge.toast('Recipe loaded!', 'success', 1500);
        }, 'json');
    }

    function deleteRecipe(id) {
        if (!confirm('Delete this recipe?')) return;
        $.post(API, { action: 'delete_recipe', id }, function() { loadRecipeList(); }, 'json');
    }

    // ── Recent Sketches ────────────────────────────────
    function loadRecentSketches() {
        $.post(API, { action: 'recent_sketches' }, function(res) {
            if (!res.ok) return;
            const list = $('#recentSketchList');
            list.empty();
            (res.sketches || []).forEach(s => {
                const el = $(`
                <div class="recent-sketch-item">
                    <span class="rsi-id">#${s.id}</span>
                    <span class="rsi-name">${esc(s.name)}</span>
                </div>`);
                el.on('click', () => {
                    $('#contSceneDesc').val(s.description || '');
                    $('#contSketchId').val(s.id);
                    Forge.openModal('continuityModal');
                });
                list.append(el);
            });
        }, 'json');
    }

    function addRecentSketch(id, name) {
        recentSketches.unshift({ id, name });
        const list = $('#recentSketchList');
        const el = $(`
        <div class="recent-sketch-item">
            <span class="rsi-id">#${id}</span>
            <span class="rsi-name">${esc(name)}</span>
        </div>`);
        el.on('click', () => {
            $.post(API, { action: 'get_sketch_desc', id }, function(res) {
                if (res.ok) {
                    $('#contSceneDesc').val(res.description || '');
                    $('#contSketchId').val(id);
                    Forge.openModal('continuityModal');
                }
            }, 'json');
        });
        list.prepend(el);
        list.children().slice(5).remove();
    }

    // ── Utils ─────────────────────────────────────────
    function esc(s) {
        const d = document.createElement('div'); d.textContent = String(s||''); return d.innerHTML;
    }

    // ── Public API ────────────────────────────────────
    return {
        init, saveConfig,
        tapAddIngredient, clearPot,
        addKgSubpotToPot, updateKgPreview, removeKgNode, addKgNode,
        openKgNodeGraph,
        kgToggleFolder, kgCheckNode,
        cook, runContinuity, copyContinuityResult, saveContResult,
        randomRecipe,
        openSaveRecipe, confirmSaveRecipe, openLoadRecipe, loadRecipe, deleteRecipe, changeRecipePage,
        loadRecentSketches, addRecentSketch,
        // Exposed for SkGraph queries
        _kgNodeAdded: (id) => kgNodes.some(n => n.id === id),
        _getKgNodes:  ()   => kgNodes,
    };
})();

document.addEventListener('DOMContentLoaded', () => Kitchen.init());
</script>

<?php
// Include the mini-graph / iframe modal system so showMiniGraphModal() is available as fallback
require_once __DIR__ . '/../modal_frame_details.php';
?>

<?php echo $eruda ?? ''; ?>
</body>
</html>


