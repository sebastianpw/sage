<?php
// public/generator_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// GENERATOR FORGE
// World-class generator runner UI — PDO-only, no Doctrine.
// Parallel to existing generator_admin.php / floatool.php.
// Same DB table, backwards-compatible, never touches existing code.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId    = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

// When loaded inside the floatool iframe, use scale 1.0 — the iframe is
// already sized to exact screen pixels so the parent zoom is irrelevant.
// When loaded standalone, use 0.7 which fits the full UI on mobile.
$isEmbed       = !empty($_GET['embed']);
$viewportScale = $isEmbed ? '1.0' : '0.9';

$pageTitle = 'Generator Forge';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Generator Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    } catch (e) {
      // Fails gracefully
    }
  })();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   FORGE — Design System
   Aesthetic: Industrial Alchemist — dark forges, amber accents, monospace data
═══════════════════════════════════════════════════════════════════════════ */
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
    --amber-dim:    rgba(245, 166, 35, 0.08);
    --amber-mid:    rgba(245, 166, 35, 0.15);
    --amber-glow:   rgba(245, 166, 35, 0.4);
    --green:        #22d3a0;
    --green-dim:    rgba(34, 211, 160, 0.1);
    --red:          #f05060;
    --red-dim:      rgba(240, 80, 96, 0.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77, 166, 255, 0.1);
    --mono:         'Space Mono', 'Fira Mono', monospace;
    --sans:         'Syne', system-ui, sans-serif;
    --radius:       6px;
    --radius-lg:    10px;
}

/* -------------------------
   LIGHT THEME OVERRIDES
   ------------------------- */
@media (prefers-color-scheme: light) {
    :root {
        --bg:           #f6f8fa;
        --surface:      #e1e4e8;
        --card:         #ffffff;
        --card-hover:   #f3f4f6;
        --border:       #d1d5db;
        --border-glow:  #9ca3af;
        --text:         #111827;
        --text-dim:     #4b5563;
        --text-bright:  #000000;
        --amber:        #d97706;
        --amber-dim:    rgba(217, 119, 6, 0.1);
        --amber-mid:    rgba(217, 119, 6, 0.2);
        --amber-glow:   rgba(217, 119, 6, 0.4);
        --green:        #059669;
        --green-dim:    rgba(5, 150, 105, 0.1);
        --red:          #dc2626;
        --red-dim:      rgba(220, 38, 38, 0.1);
        --blue:         #2563eb;
        --blue-dim:     rgba(37, 99, 235, 0.1);
    }
}

:root[data-theme="light"],
html[data-theme="light"],
body[data-theme="light"] {
    --bg:           #f6f8fa;
    --surface:      #e1e4e8;
    --card:         #ffffff;
    --card-hover:   #f3f4f6;
    --border:       #d1d5db;
    --border-glow:  #9ca3af;
    --text:         #111827;
    --text-dim:     #4b5563;
    --text-bright:  #000000;
    --amber:        #d97706;
    --amber-dim:    rgba(217, 119, 6, 0.1);
    --amber-mid:    rgba(217, 119, 6, 0.2);
    --amber-glow:   rgba(217, 119, 6, 0.4);
    --green:        #059669;
    --green-dim:    rgba(5, 150, 105, 0.1);
    --red:          #dc2626;
    --red-dim:      rgba(220, 38, 38, 0.1);
    --blue:         #2563eb;
    --blue-dim:     rgba(37, 99, 235, 0.1);
}

:root[data-theme="dark"],
html[data-theme="dark"],
body[data-theme="dark"] {
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
    --amber-dim:    rgba(245, 166, 35, 0.08);
    --amber-mid:    rgba(245, 166, 35, 0.15);
    --amber-glow:   rgba(245, 166, 35, 0.4);
    --green:        #22d3a0;
    --green-dim:    rgba(34, 211, 160, 0.1);
    --red:          #f05060;
    --red-dim:      rgba(240, 80, 96, 0.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77, 166, 255, 0.1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    height: 100%; background: var(--bg); color: var(--text);
    font-family: var(--sans); font-size: 14px; line-height: 1.5;
    -webkit-font-smoothing: antialiased; overflow: hidden;
}

/* ── SCROLLBARS ── */
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

/* ── LAYOUT ── */
.forge-layout {
    display: grid;
    grid-template-rows: 52px 1fr;
    grid-template-columns: 300px 1fr;
    grid-template-areas:
        "header header"
        "sidebar main";
    height: 100vh; height: 100dvh;
    overflow: hidden;
}

/* ── HEADER ── */
.forge-header {
    grid-area: header;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 20px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    position: relative; z-index: 100;
}
.forge-logo {
    display: flex; align-items: center; gap: 10px;
    font-family: var(--mono); font-size: 0.85rem; font-weight: 700;
    color: var(--amber); letter-spacing: 2px; text-transform: uppercase;
}
.forge-logo-icon {
    width: 28px; height: 28px; background: var(--amber-mid);
    border: 1px solid var(--amber-glow); border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
}
.forge-header-right {
    display: flex; align-items: center; gap: 10px;
}
.forge-header-stat {
    font-family: var(--mono); font-size: 0.7rem; color: var(--text-dim);
    padding: 4px 10px; background: var(--card);
    border: 1px solid var(--border); border-radius: var(--radius);
}
.forge-header-stat span { color: var(--amber); }

/* ── SIDEBAR ── */
.forge-sidebar {
    grid-area: sidebar;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow: hidden;
}
.sidebar-search {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.sidebar-search-input {
    width: 100%; padding: 8px 10px 8px 32px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: var(--mono); font-size: 0.8rem;
    position: relative;
    transition: border-color 0.2s;
}
.sidebar-search-input:focus { outline: none; border-color: var(--amber); }
.sidebar-search-wrap { position: relative; }
.sidebar-search-wrap::before {
    content: '⌕'; position: absolute; left: 8px; top: 50%;
    transform: translateY(-50%); color: var(--text-dim); font-size: 16px;
    pointer-events: none; z-index: 1;
}
.sidebar-filter-row {
    display: flex; gap: 6px; padding: 0 12px 10px;
    flex-shrink: 0;
    overflow-x: auto; overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; -ms-overflow-style: none;
}
.sidebar-filter-row::-webkit-scrollbar { display: none; }
.filter-chip {
    padding: 3px 8px; border-radius: 20px; font-size: 0.7rem;
    font-family: var(--mono); cursor: pointer; border: 1px solid var(--border);
    background: transparent; color: var(--text-dim); transition: all 0.15s;
    white-space: nowrap;
}
.filter-chip.active {
    background: var(--amber-dim); border-color: var(--amber);
    color: var(--amber);
}
.filter-chip:hover:not(.active) { border-color: var(--border-glow); color: var(--text); }

.sidebar-list {
    flex: 1; overflow-y: auto; padding: 8px;
}
.sidebar-section-label {
    font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 1.5px;
    padding: 8px 4px 4px; margin-bottom: 4px;
}

/* Generator Card in Sidebar */
.gen-card {
    padding: 8px 10px 8px 12px; border-radius: var(--radius);
    border: 1px solid transparent;
    cursor: pointer; transition: all 0.15s;
    margin-bottom: 3px; position: relative;
    background: transparent;
    display: flex; align-items: center; gap: 8px;
}
.gen-card:hover {
    background: var(--card); border-color: var(--border);
}
.gen-card.active {
    background: var(--amber-dim); border-color: var(--amber);
}
.gen-card.active .gen-card-title { color: var(--amber); }
.gen-card-body {
    flex: 1; min-width: 0;
}
.gen-card-title {
    font-family: var(--sans); font-weight: 600; font-size: 0.85rem;
    color: var(--text-bright); line-height: 1.3; margin-bottom: 4px;
    transition: color 0.15s;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.gen-card-meta {
    display: flex; align-items: center; gap: 6px;
    flex-wrap: wrap;
}
/* Toggle button inside sidebar card */
.gen-card-toggle {
    flex-shrink: 0; width: 30px; height: 30px;
    border-radius: var(--radius); border: 1px solid var(--border);
    background: transparent; color: var(--text-dim);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; cursor: pointer; transition: all 0.15s;
    /* prevent card click from firing when tapping the button */
}
.gen-card-toggle:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.gen-card-toggle.is-active { border-color: var(--green); color: var(--green); background: var(--green-dim); }
.gen-card-toggle.is-active:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.gen-badge {
    font-family: var(--mono); font-size: 0.65rem;
    padding: 1px 5px; border-radius: 3px;
    border: 1px solid;
}
.gen-badge.model { border-color: var(--border-glow); color: var(--text-dim); background: var(--card); }
.gen-badge.active { border-color: var(--green); color: var(--green); background: var(--green-dim); }
.gen-badge.inactive { border-color: var(--border); color: var(--text-dim); }
.gen-badge.public { border-color: var(--blue); color: var(--blue); background: var(--blue-dim); }
.gen-badge.oracle { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }
.gen-card-indicator {
    position: absolute; left: 0; top: 50%; transform: translateY(-50%);
    width: 2px; height: 0; background: var(--amber);
    border-radius: 0 2px 2px 0; transition: height 0.2s;
}
.gen-card.active .gen-card-indicator { height: 60%; }

.sidebar-empty {
    text-align: center; padding: 40px 20px;
    color: var(--text-dim); font-family: var(--mono); font-size: 0.8rem;
}

/* ── MAIN AREA ── */
.forge-main {
    grid-area: main;
    display: flex; flex-direction: column;
    overflow: hidden; background: var(--bg);
}

/* Empty state */
.forge-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 16px; padding: 40px;
    color: var(--text-dim);
}
.forge-empty-icon {
    font-size: 48px; opacity: 0.3;
    filter: grayscale(1);
}
.forge-empty-title {
    font-family: var(--mono); font-size: 1rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 2px;
}
.forge-empty-sub { font-size: 0.85rem; color: var(--text-dim); opacity: 0.6; }

/* Generator workspace */
.forge-workspace {
    flex: 1; display: flex; flex-direction: column; overflow: hidden;
    display: none; /* shown via JS when generator selected */
}

/* Workspace header */
.workspace-header {
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    background: var(--surface); flex-shrink: 0;
    display: flex; align-items: flex-start; gap: 12px;
}
.workspace-title-block { flex: 1; min-width: 0; }
.workspace-title {
    font-family: var(--sans); font-size: 1.1rem; font-weight: 700;
    color: var(--text-bright); line-height: 1.2; margin-bottom: 4px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.workspace-meta {
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
}
.workspace-header-actions { display: flex; gap: 6px; flex-shrink: 0; }

/* Workspace body — two column scroll */
.workspace-body {
    flex: 1; overflow: hidden; display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}

/* PARAMS PANEL */
.params-panel {
    padding: 20px; overflow-y: auto;
    border-right: 1px solid var(--border);
}
.panel-label {
    font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 2px; margin-bottom: 14px;
    display: flex; align-items: center; gap: 6px;
}
.panel-label::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
}

/* Form elements */
.form-group { margin-bottom: 16px; }
.form-label {
    display: block; font-family: var(--mono); font-size: 0.7rem;
    color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;
    margin-bottom: 6px;
}
.form-label .param-type {
    color: var(--amber); margin-left: 4px; font-size: 0.65rem;
}
.form-input, .form-select, .form-textarea {
    width: 100%; padding: 9px 12px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: var(--mono); font-size: 0.8rem;
    transition: border-color 0.15s, background 0.15s;
    appearance: none;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none; border-color: var(--amber);
    background: var(--card-hover);
}
.form-textarea { min-height: 90px; resize: vertical; }
.form-select { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }

.form-hint { font-size: 0.72rem; color: var(--text-dim); margin-top: 4px; font-family: var(--mono); }

/* Enum options as pills */
.enum-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
.enum-pill {
    padding: 5px 12px; border-radius: 20px;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-dim); font-family: var(--mono); font-size: 0.75rem;
    cursor: pointer; transition: all 0.15s;
}
.enum-pill:hover { border-color: var(--amber); color: var(--text); }
.enum-pill.selected {
    background: var(--amber-dim); border-color: var(--amber); color: var(--amber);
}

/* Generate button area */
.generate-bar {
    padding: 16px 20px; border-top: 1px solid var(--border);
    background: var(--surface); flex-shrink: 0;
    grid-column: 1; /* only in params column */
    display: flex; gap: 8px; align-items: center;
}
.btn-generate {
    flex: 1; padding: 12px 20px;
    background: var(--amber); color: #000;
    border: none; border-radius: var(--radius);
    font-family: var(--mono); font-size: 0.85rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1.5px;
    cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-generate:hover:not(:disabled) { filter: brightness(1.15); transform: translateY(-1px); }
.btn-generate:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(0.5); transform: none; }
.btn-generate .spinner {
    width: 14px; height: 14px;
    border: 2px solid rgba(0,0,0,0.3);
    border-top-color: #000;
    border-radius: 50%; animation: spin 0.7s linear infinite;
    display: none;
}
.btn-generate.loading .btn-label { display: none; }
.btn-generate.loading .spinner { display: block; }

.btn-icon-sm {
    width: 40px; height: 40px; border-radius: var(--radius);
    border: 1px solid var(--border); background: var(--card);
    color: var(--text-dim); cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
}
.btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* ── RESULT PANEL ── */
.result-panel {
    display: flex; flex-direction: column; overflow: hidden;
    background: var(--bg);
}

.result-toolbar {
    padding: 12px 16px; border-bottom: 1px solid var(--border);
    background: var(--surface); flex-shrink: 0;
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
}
.result-toolbar-left { display: flex; align-items: center; gap: 8px; flex: 1; }
.result-toolbar-right { display: flex; gap: 6px; }

.result-tab {
    padding: 5px 12px; border-radius: 20px;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-dim); font-family: var(--mono); font-size: 0.72rem;
    cursor: pointer; transition: all 0.15s;
}
.result-tab.active {
    background: var(--amber-dim); border-color: var(--amber); color: var(--amber);
}
.result-tab:hover:not(.active) { border-color: var(--border-glow); color: var(--text); }

.result-elapsed {
    font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim);
    margin-left: auto;
}
.result-elapsed span { color: var(--green); }

.result-body { flex: 1; overflow-y: auto; padding: 16px; }

/* Result views */
.result-view { display: none; }
.result-view.active { display: block; }

/* Friendly string result */
.result-string {
    font-family: var(--sans); font-size: 1rem; line-height: 1.7;
    color: var(--text-bright);
    background: var(--card); padding: 16px; border-radius: var(--radius);
    border: 1px solid var(--border);
    white-space: pre-wrap; word-break: break-word;
}

/* JSON tree */
.json-tree { font-family: var(--mono); font-size: 0.8rem; line-height: 1.6; }
.json-tree .key { color: var(--amber); }
.json-tree .string { color: var(--green); }
.json-tree .number { color: var(--blue); }
.json-tree .boolean { color: var(--red); }
.json-tree .null { color: var(--text-dim); }
.json-tree .punctuation { color: var(--text-dim); }

/* Key selector */
.key-selector {
    display: flex; flex-direction: column; gap: 6px;
}
.key-row {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; background: var(--card); border-radius: var(--radius);
    border: 1px solid var(--border); cursor: pointer; transition: all 0.15s;
}
.key-row:hover { border-color: var(--amber); background: var(--card-hover); }
.key-row-key {
    font-family: var(--mono); font-size: 0.72rem; color: var(--amber);
    min-width: 100px; flex-shrink: 0;
}
.key-row-value {
    flex: 1; font-family: var(--mono); font-size: 0.78rem; color: var(--text);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.key-row-value.is-object { color: var(--text-dim); font-style: italic; }
.key-row-copy {
    width: 28px; height: 28px; border-radius: 4px;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-dim); cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; flex-shrink: 0;
}
.key-row-copy:hover { border-color: var(--green); color: var(--green); background: var(--green-dim); }
.key-row-copy.copied { border-color: var(--green); color: var(--green); background: var(--green-dim); }

/* Raw view */
.result-raw {
    font-family: var(--mono); font-size: 0.75rem; line-height: 1.6;
    color: var(--text); white-space: pre-wrap; word-break: break-word;
    background: var(--card); padding: 14px; border-radius: var(--radius);
    border: 1px solid var(--border);
}

/* Result empty / loading / error states */
.result-placeholder {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; gap: 12px; color: var(--text-dim); padding: 40px;
    text-align: center;
}
.result-placeholder-icon { font-size: 40px; opacity: 0.2; }
.result-placeholder-text { font-family: var(--mono); font-size: 0.8rem; letter-spacing: 1px; }

.result-loading {
    display: none;
    flex-direction: column; align-items: center; justify-content: center;
    height: 100%; gap: 16px; color: var(--amber);
}
.result-loading.active { display: flex; }
.forge-spinner {
    width: 36px; height: 36px;
    border: 2px solid var(--amber-dim);
    border-top-color: var(--amber);
    border-radius: 50%; animation: spin 0.8s linear infinite;
}
.result-loading-text { font-family: var(--mono); font-size: 0.75rem; letter-spacing: 2px; }

.result-error {
    background: var(--red-dim); border: 1px solid var(--red);
    border-radius: var(--radius); padding: 14px; color: var(--red);
    font-family: var(--mono); font-size: 0.8rem;
    display: none;
}
.result-error.active { display: block; }

/* Parse strategy badge */
.parse-badge {
    font-family: var(--mono); font-size: 0.65rem; padding: 2px 6px;
    border-radius: 3px; border: 1px solid var(--border);
    color: var(--text-dim);
}
.parse-badge.direct     { color: var(--green); border-color: var(--green); background: var(--green-dim); }
.parse-badge.repaired   { color: var(--amber); border-color: var(--amber); background: var(--amber-dim); }
.parse-badge.failed     { color: var(--red);   border-color: var(--red);   background: var(--red-dim); }

/* ── TOAST ── */
.forge-toast-container {
    position: fixed; bottom: 20px; right: 20px; z-index: 9999;
    display: flex; flex-direction: column; gap: 8px; pointer-events: none;
}
.forge-toast {
    padding: 10px 16px; border-radius: var(--radius);
    background: var(--card); border: 1px solid var(--border);
    font-family: var(--mono); font-size: 0.8rem; color: var(--text);
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    animation: toastIn 0.25s ease;
    pointer-events: all; cursor: pointer; max-width: 320px;
    display: flex; align-items: center; gap: 8px;
}
.forge-toast.success { border-color: var(--green); }
.forge-toast.error   { border-color: var(--red); color: var(--red); }
.forge-toast.info    { border-color: var(--amber); }
.forge-toast.out     { animation: toastOut 0.25s ease forwards; }

/* ── MODAL ── */
.forge-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.8);
    backdrop-filter: blur(3px); z-index: 10000;
    display: none; align-items: center; justify-content: center;
    padding: 16px;
}
.forge-modal-overlay.open { display: flex; }
.forge-modal {
    background: var(--surface); border: 1px solid var(--border-glow);
    border-radius: var(--radius-lg); width: 100%; max-width: 680px;
    max-height: 90vh; display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    animation: modalIn 0.2s ease;
}
.forge-modal-header {
    padding: 18px 20px; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
    flex-shrink: 0;
}
.forge-modal-title {
    font-family: var(--mono); font-size: 0.8rem; font-weight: 700;
    color: var(--amber); text-transform: uppercase; letter-spacing: 1.5px;
}
.forge-modal-close {
    width: 28px; height: 28px; border-radius: 4px;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-dim); cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.forge-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.forge-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.forge-modal-footer {
    padding: 14px 20px; border-top: 1px solid var(--border);
    display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0;
    background: var(--bg);
}

.btn-forge-primary {
    padding: 8px 18px; background: var(--amber); color: #000;
    border: none; border-radius: var(--radius); cursor: pointer;
    font-family: var(--mono); font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s;
}
.btn-forge-primary:hover { filter: brightness(1.1); }
.btn-forge-secondary {
    padding: 8px 18px; background: transparent; color: var(--text-dim);
    border: 1px solid var(--border); border-radius: var(--radius);
    cursor: pointer; font-family: var(--mono); font-size: 0.78rem;
    transition: all 0.15s;
}
.btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }
.btn-forge-danger {
    padding: 8px 18px; background: var(--red-dim); color: var(--red);
    border: 1px solid var(--red); border-radius: var(--radius);
    cursor: pointer; font-family: var(--mono); font-size: 0.78rem;
    transition: all 0.15s;
}
.btn-forge-danger:hover { background: var(--red); color: #fff; }

/* ── HISTORY LOG ── */
.history-list { display: flex; flex-direction: column; gap: 4px; }
.history-item {
    padding: 6px 10px; background: var(--card); border-radius: var(--radius);
    border: 1px solid var(--border); cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; gap: 8px;
}
.history-item:hover { border-color: var(--amber); }
.history-item-time { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); flex-shrink: 0; }
.history-item-preview { flex: 1; font-family: var(--mono); font-size: 0.73rem; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.history-item-ms { font-family: var(--mono); font-size: 0.65rem; color: var(--green); flex-shrink: 0; }

/* ── ANIMATIONS ── */
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes toastIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(10px); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-10px); } to { opacity: 1; transform: none; } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 var(--amber-glow); }
    50% { box-shadow: 0 0 0 6px rgba(245,166,35,0); }
}

/* ── MOBILE ── */
@media (max-width: 768px) {
    .forge-layout {
        grid-template-columns: 1fr;
        grid-template-rows: 52px auto 1fr;
        grid-template-areas:
            "header"
            "sidebar"
            "main";
    }
    .forge-sidebar {
        border-right: none; border-bottom: 1px solid var(--border);
        max-height: 220px;
    }
    .workspace-body { grid-template-columns: 1fr; }
    .params-panel { border-right: none; border-bottom: 1px solid var(--border); max-height: 50vh; }
    .forge-header-stat { display: none; }
}
@media (max-width: 480px) {
    .forge-modal { max-height: 95vh; }
}

/* Divider in workspace body */
.workspace-body {
    display: grid;
    grid-template-columns: 360px 1fr;
    grid-template-rows: 1fr auto;
}
.params-panel { grid-row: 1; grid-column: 1; }
.generate-bar { grid-row: 2; grid-column: 1; }
.result-panel { grid-row: 1 / 3; grid-column: 2; }

@media (max-width: 900px) {
    .workspace-body { grid-template-columns: 1fr; grid-template-rows: 1fr auto 1fr; }
    .result-panel { grid-row: 3; grid-column: 1; border-left: none; border-top: 1px solid var(--border); }
}
</style>
</head>
<body>

<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon">⚗</div>
            <span>Generator Forge</span>
        </div>
        <div class="forge-header-right">
            <div class="forge-header-stat" id="headerStat">
                <span id="statCount">—</span> generators loaded
            </div>
            <button class="btn-icon-sm" onclick="Forge.openNewGeneratorModal()" title="New Generator">
                <i class="bi bi-plus-lg"></i>
            </button>
            <a href="/generator_admin.php" class="btn-icon-sm" title="Classic Admin" style="text-decoration:none;">
                <i class="bi bi-gear"></i>
            </a>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="sidebarSearch"
                       placeholder="Search generators…" autocomplete="off">
            </div>
        </div>
        <div class="sidebar-filter-row" id="areaFilterRow">
            <button class="filter-chip active" data-area="">All</button>
            <!-- Area chips populated by JS -->
        </div>
        <div class="sidebar-list" id="sidebarList">
            <div class="sidebar-empty">
                <div style="font-size:2rem; margin-bottom:8px;">⚗</div>
                Loading generators…
            </div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main" id="forgeMain">

        <!-- Empty state -->
        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon">⚗</div>
            <div class="forge-empty-title">Select a Generator</div>
            <div class="forge-empty-sub">Choose from the sidebar to begin forging</div>
        </div>

        <!-- Workspace (shown when generator selected) -->
        <div class="forge-workspace" id="forgeWorkspace">

            <div class="workspace-header">
                <div class="workspace-title-block">
                    <div class="workspace-title" id="wsTitle">—</div>
                    <div class="workspace-meta" id="wsMeta"></div>
                </div>
                <div class="workspace-header-actions">
                    <button class="btn-icon-sm" id="btnHistory" onclick="Forge.toggleHistory()" title="History">
                        <i class="bi bi-clock-history"></i>
                    </button>
                    <button class="btn-icon-sm" onclick="Forge.editCurrentGenerator()" title="Edit Config">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>

            <div class="workspace-body">

                <!-- PARAMS PANEL -->
                <div class="params-panel" id="paramsPanel">
                    <div class="panel-label">Parameters</div>
                    <div id="paramsForm">
                        <!-- populated by JS -->
                    </div>
                </div>

                <!-- Generate bar (sticks below params) -->
                <div class="generate-bar">
                    <button class="btn-generate" id="btnGenerate" onclick="Forge.generate()">
                        <div class="spinner"></div>
                        <span class="btn-label"><i class="bi bi-fire"></i> FORGE</span>
                    </button>
                    <button class="btn-icon-sm" id="btnCopyResult" onclick="Forge.copyActiveResult()" title="Copy Result" style="display:none;">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <button class="btn-icon-sm" onclick="Forge.clearResult()" title="Clear Result">
                        <i class="bi bi-eraser"></i>
                    </button>
                </div>

                <!-- RESULT PANEL -->
                <div class="result-panel">
                    <div class="result-toolbar">
                        <div class="result-toolbar-left">
                            <button class="result-tab active" data-view="friendly">Result</button>
                            <button class="result-tab" data-view="keys">Keys</button>
                            <button class="result-tab" data-view="json">JSON</button>
                            <button class="result-tab" data-view="raw">Raw</button>
                        </div>
                        <div class="result-toolbar-right">
                            <span id="parseBadge" class="parse-badge" style="display:none;"></span>
                            <span class="result-elapsed" id="resultElapsed" style="display:none;">
                                <span id="elapsedMs">—</span>ms
                            </span>
                        </div>
                    </div>

                    <div class="result-body" id="resultBody">
                        <!-- Loading -->
                        <div class="result-loading" id="resultLoading">
                            <div class="forge-spinner"></div>
                            <div class="result-loading-text">FORGING…</div>
                        </div>
                        <!-- Error -->
                        <div class="result-error" id="resultError"></div>
                        <!-- Placeholder -->
                        <div class="result-placeholder" id="resultPlaceholder">
                            <div class="result-placeholder-icon">⚗</div>
                            <div class="result-placeholder-text">Awaiting generation</div>
                        </div>
                        <!-- Friendly view -->
                        <div class="result-view active" id="viewFriendly"></div>
                        <!-- Keys view -->
                        <div class="result-view" id="viewKeys"></div>
                        <!-- JSON tree view -->
                        <div class="result-view" id="viewJson"></div>
                        <!-- Raw view -->
                        <div class="result-view" id="viewRaw"></div>
                    </div>
                </div>

            </div><!-- /workspace-body -->
        </div><!-- /forge-workspace -->
    </main>
</div><!-- /forge-layout -->

<!-- ── HISTORY MODAL ── -->
<div class="forge-modal-overlay" id="historyModal">
    <div class="forge-modal" style="max-width:500px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title">Generation History</div>
            <button class="forge-modal-close" onclick="Forge.closeModal('historyModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <div id="historyListContainer"></div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="Forge.clearHistory()">Clear All</button>
            <button class="btn-forge-secondary" onclick="Forge.closeModal('historyModal')">Close</button>
        </div>
    </div>
</div>

<!-- ── NEW/EDIT GENERATOR MODAL ── -->
<div class="forge-modal-overlay" id="genEditModal">
    <div class="forge-modal">
        <div class="forge-modal-header">
            <div class="forge-modal-title" id="genEditModalTitle">New Generator</div>
            <button class="forge-modal-close" onclick="Forge.closeModal('genEditModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <input type="hidden" id="editGenId">
            <div style="display:grid; grid-template-columns:1fr 90px; gap:12px; align-items:end;">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" id="editTitle" class="form-input" placeholder="My Generator">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order
                        <span class="param-type" style="font-size:0.6rem;">↕</span>
                    </label>
                    <input type="number" id="editListOrder" class="form-input" value="0" min="0" step="1"
                           placeholder="0" title="Lower = appears first in the list">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <select id="editModel" class="form-select"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Display Areas</label>
                    <select id="editAreas" class="form-select" multiple size="3"></select>
                    <div class="form-hint">Ctrl/Cmd+click to multi-select</div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Config JSON
                    <span style="color:var(--text-dim); font-weight:normal; text-transform:none; letter-spacing:0; font-size:0.65rem; margin-left:6px;">
                        { system:{role,instructions[]}, parameters:{}, output:{}, examples:[] }
                    </span>
                </label>
                <textarea id="editConfigJson" class="form-textarea"
                          style="min-height:280px; font-size:0.75rem;" spellcheck="false"></textarea>
                <div class="form-hint" id="jsonValidHint"></div>
            </div>
            <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim);">
                    <input type="checkbox" id="editIsPublic"> Public
                </label>
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-family:var(--mono); font-size:0.75rem; color:var(--text-dim);">
                    <input type="checkbox" id="editIsActive"> Active
                </label>
            </div>

            <!-- Oracle Section -->
            <div style="border-top:1px solid var(--border); margin-top:18px; padding-top:18px;">
                <div style="font-family:var(--mono); font-size:0.7rem; color:var(--amber); text-transform:uppercase; letter-spacing:1.5px; margin-bottom:14px;">
                    ⚙ Creative Oracle <span style="color:var(--text-dim); text-transform:none; letter-spacing:0;">(optional)</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Source Dictionaries
                        <span class="param-type">multi-select</span>
                    </label>
                    <select id="editOracleDicts" class="form-select" multiple size="4" style="font-size:0.8rem;"></select>
                    <div class="form-hint">Hold Ctrl/Cmd to select multiple. Leave empty to disable Oracle.</div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Words to Sample</label>
                        <input type="number" id="editOracleNumWords" class="form-input" value="200" min="10" max="1000" placeholder="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Error Rate</label>
                        <input type="number" id="editOracleErrorRate" class="form-input" value="0.01" step="0.001" min="0" max="1" placeholder="0.01">
                    </div>
                </div>
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-danger" id="btnDeleteGen" onclick="Forge.deleteCurrentGenerator()" style="display:none; margin-right:auto;">
                <i class="bi bi-trash"></i> Delete
            </button>
            <button class="btn-forge-secondary" onclick="Forge.closeModal('genEditModal')">Cancel</button>
            <button class="btn-forge-primary" onclick="Forge.saveGenerator()">
                <i class="bi bi-check-lg"></i> Save Generator
            </button>
        </div>
    </div>
</div>

<!-- ── TOAST CONTAINER ── -->
<div class="forge-toast-container" id="toastContainer"></div>

<!-- ── MAIN JS ── -->
<script>
const API = '/api/generator_forge_api.php';

// ═══════════════════════════════════════════════════════════════════════════
// FORGE — Main Application
// ═══════════════════════════════════════════════════════════════════════════
const Forge = (() => {
    'use strict';

    let _generators    = [];
    let _areas         = [];
    let _models        =[];
    let _dictionaries  = [];     // [{id, title}, …] for Oracle select
    let _currentGen    = null;   // full generator record
    let _currentResult = null;   // last generate() response
    let _history       = [];     // [{time, genId, title, result, ms}]
    let _activeArea    = '';
    let _searchTimeout = null;

    // ── API helpers ──────────────────────────────────────────────────────
    async function api(action, data = {}) {
        const res = await fetch(API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action, ...data }),
        });
        if (!res.ok && res.status !== 400 && res.status !== 404) {
            throw new Error(`HTTP ${res.status}`);
        }
        return res.json();
    }

    // ── Toast ────────────────────────────────────────────────────────────
    function toast(msg, type = 'info', duration = 3000) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        const icons = { success: '✓', error: '✕', info: '◆' };
        el.innerHTML = `<span style="font-size:12px;">${icons[type] || '◆'}</span> ${msg}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        const dismiss = (e) => {
            e.classList.add('out');
            setTimeout(() => e.remove(), 300);
        };
        setTimeout(() => dismiss(el), duration);
    }

    // ── Init ─────────────────────────────────────────────────────────────
    async function init() {
        await Promise.all([
            loadAreas(),
            loadModels(),
            loadDictionaries(),
        ]);
        await loadGenerators();
        bindEvents();
    }

    async function loadAreas() {
        const r = await api('get_areas');
        if (r.ok) {
            _areas = r.data ||[];
            renderAreaChips();
        }
    }

    async function loadDictionaries() {
        try {
            const r = await api('get_dictionaries');
            if (r.ok) _dictionaries = r.data ||[];
        } catch(e) { /* non-fatal — oracle section just shows empty */ }
    }

    async function loadModels() {
        const r = await api('get_models');
        if (!r.ok) return;
        const catalog      = r.data?.catalog || {};
        const defaultModel = r.data?.default || '';
        _models =[];

        // Flat list for internal use
        for (const models of Object.values(catalog)) {
            for (const m of models) _models.push(m.id);
        }

        // Build <optgroup> HTML for the edit modal select
        const sel = document.getElementById('editModel');
        let html = '';
        for (const [groupLabel, models] of Object.entries(catalog)) {
            html += `<optgroup label="${escHtml(groupLabel)}">`;
            for (const m of models) {
                const sel_ = m.id === defaultModel ? ' selected' : '';
                html += `<option value="${escHtml(m.id)}"${sel_}>${escHtml(m.name)}</option>`;
            }
            html += '</optgroup>';
        }
        sel.innerHTML = html || '<option value="">No models available</option>';
    }

    async function loadGenerators() {
        const r = await api('list', { filter_area: _activeArea });
        if (r.ok) {
            _generators = r.data ||[];
            renderSidebar(_generators);
            document.getElementById('statCount').textContent = _generators.length;
        } else {
            toast('Failed to load generators: ' + r.error, 'error');
        }
    }

    // ── Sidebar ──────────────────────────────────────────────────────────
    function renderAreaChips() {
        const row = document.getElementById('areaFilterRow');
        const existing = row.querySelector('[data-area=""]');
        // Clear old area chips (keep "All")
        Array.from(row.querySelectorAll('[data-area]:not([data-area=""])')).forEach(el => el.remove());

        _areas.forEach(a => {
            const btn = document.createElement('button');
            btn.className = 'filter-chip';
            btn.dataset.area = a.area_key || a.key || '';
            btn.textContent = a.label || a.area_key;
            row.appendChild(btn);
        });
    }

    function renderSidebar(list) {
        const container = document.getElementById('sidebarList');
        const search    = document.getElementById('sidebarSearch').value.toLowerCase().trim();

        const filtered = search
            ? list.filter(g => g.title.toLowerCase().includes(search))
            : list;

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="sidebar-empty">
                    <div style="font-size:2rem; margin-bottom:8px;">⚗</div>
                    ${search ? 'No matches' : 'No generators yet'}
                </div>`;
            return;
        }

        container.innerHTML = filtered.map(g => {
            const isActive = _currentGen && _currentGen.id === g.id;
            const badges =[];
            if (g.is_public) badges.push('<span class="gen-badge public">PUB</span>');
            if (g.oracle_config) badges.push('<span class="gen-badge oracle">⚙ Oracle</span>');
            badges.push(`<span class="gen-badge model">${escHtml(g.model)}</span>`);

            const toggleCls  = g.active ? 'is-active' : '';
            const toggleIcon = g.active ? '<i class="bi bi-pause-fill"></i>' : '<i class="bi bi-play-fill"></i>';
            const toggleTitle = g.active ? 'Active — click to deactivate' : 'Inactive — click to activate';

            return `
            <div class="gen-card${isActive ? ' active' : ''}" data-id="${g.id}" onclick="Forge.selectGenerator(${g.id})">
                <div class="gen-card-indicator"></div>
                <div class="gen-card-body">
                    <div class="gen-card-title">${escHtml(g.title)}</div>
                    <div class="gen-card-meta">${badges.join('')}</div>
                </div>
                <button class="gen-card-toggle" title="Duplicate this generator"
                        onclick="event.stopPropagation(); Forge.copyGenerator(${g.id}, this)">
                    <i class="bi bi-copy"></i>
                </button>
                <button class="gen-card-toggle ${toggleCls}" title="${toggleTitle}"
                        onclick="event.stopPropagation(); Forge.toggleGenerator(${g.id}, this)">
                    ${toggleIcon}
                </button>
            </div>`;
        }).join('');
    }

    // ── Select generator ──────────────────────────────────────────────────
    async function selectGenerator(id) {
        if (_currentGen && _currentGen.id === id) return;

        const r = await api('get', { id });
        if (!r.ok) { toast('Failed to load generator', 'error'); return; }

        _currentGen = r.data;
        clearResult();
        renderWorkspace();
        renderSidebar(_generators); // Update active state
    }

    function renderWorkspace() {
        if (!_currentGen) return;
        const g = _currentGen;

        document.getElementById('forgeEmpty').style.display     = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';

        document.getElementById('wsTitle').textContent = g.title;

        // Meta badges
        const meta = document.getElementById('wsMeta');
        const statusCol = g.active ? 'var(--green)' : 'var(--red)';
        meta.innerHTML = `
            <span class="gen-badge ${g.active ? 'active' : 'inactive'}">${g.active ? 'ACTIVE' : 'INACTIVE'}</span>
            <span class="gen-badge model">${escHtml(g.model)}</span>
            ${g.is_public ? '<span class="gen-badge public">PUBLIC</span>' : ''}
            ${g.oracle_config ? '<span class="gen-badge oracle">⚙ Oracle</span>' : ''}
        `;

        // Show copy button if there's a result
        document.getElementById('btnCopyResult').style.display = _currentResult ? 'flex' : 'none';

        renderParams();
    }

    function renderParams() {
        const params = _currentGen.parameters || {};
        const form   = document.getElementById('paramsForm');

        if (Object.keys(params).length === 0) {
            form.innerHTML = `
                <div style="text-align:center; padding:30px; color:var(--text-dim); font-family:var(--mono); font-size:0.8rem;">
                    No parameters defined.<br>
                    <small style="opacity:0.6;">This generator runs with fixed prompts.</small>
                </div>`;
            return;
        }

        form.innerHTML = '';

        for (const [key, def] of Object.entries(params)) {
            const group = document.createElement('div');
            group.className = 'form-group';

            const label = document.createElement('label');
            label.className = 'form-label';
            label.innerHTML = escHtml(def.label || key) +
                (def.type ? `<span class="param-type">${def.type}</span>` : '');
            group.appendChild(label);

            // Enum → pills
            if (def.type === 'string' && def.enum && def.enum.length > 0) {
                const pillsWrap = document.createElement('div');
                pillsWrap.className = 'enum-pills';
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = key;
                hiddenInput.value = def.default || def.enum[0] || '';

                def.enum.forEach(val => {
                    const pill = document.createElement('button');
                    pill.type = 'button';
                    pill.className = 'enum-pill' + (val === hiddenInput.value ? ' selected' : '');
                    pill.textContent = val;
                    pill.onclick = () => {
                        pillsWrap.querySelectorAll('.enum-pill').forEach(p => p.classList.remove('selected'));
                        pill.classList.add('selected');
                        hiddenInput.value = val;
                    };
                    pillsWrap.appendChild(pill);
                });

                group.appendChild(pillsWrap);
                group.appendChild(hiddenInput);

            } else if (def.type === 'string' && (def.multiline || def.textarea)) {
                const ta = document.createElement('textarea');
                ta.className = 'form-textarea';
                ta.name  = key;
                ta.value = def.default || '';
                ta.placeholder = def.placeholder || '';
                group.appendChild(ta);

            } else if (def.type === 'integer' || def.type === 'number') {
                const inp = document.createElement('input');
                inp.type  = 'number';
                inp.className = 'form-input';
                inp.name  = key;
                inp.value = def.default ?? '';
                inp.placeholder = def.placeholder || '';
                if (def.min !== undefined) inp.min = def.min;
                if (def.max !== undefined) inp.max = def.max;
                group.appendChild(inp);

            } else {
                // Default: text input
                const inp = document.createElement('input');
                inp.type  = 'text';
                inp.className = 'form-input';
                inp.name  = key;
                inp.value = def.default || '';
                inp.placeholder = def.placeholder || (def.label || key);
                group.appendChild(inp);
            }

            if (def.hint || def.description) {
                const hint = document.createElement('div');
                hint.className = 'form-hint';
                hint.textContent = def.hint || def.description;
                group.appendChild(hint);
            }

            form.appendChild(group);
        }
    }

    // ── Generate ──────────────────────────────────────────────────────────
    async function generate() {
        if (!_currentGen) { toast('No generator selected', 'error'); return; }

        const btn = document.getElementById('btnGenerate');
        btn.disabled = true;
        btn.classList.add('loading');

        showResultState('loading');

        // Collect params
        const form = document.getElementById('paramsForm');
        const params = { config_id: _currentGen.config_id };
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach(el => {
            params[el.name] = el.value;
        });

        try {
            const startMs = Date.now();
            const r = await api('generate', params);
            const elapsed = r.elapsed_ms ?? (Date.now() - startMs);

            if (r.ok) {
                _currentResult = r;
                renderResult(r);
                logHistory(r, elapsed);
                document.getElementById('btnCopyResult').style.display = 'flex';
            } else {
                showResultState('error', r.error || 'Generation failed');
            }
        } catch (e) {
            showResultState('error', e.message);
        } finally {
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    // ── Result rendering ──────────────────────────────────────────────────
    function showResultState(state, errorMsg = '') {
        document.getElementById('resultLoading').classList.toggle('active', state === 'loading');
        document.getElementById('resultError').classList.toggle('active', state === 'error');
        document.getElementById('resultPlaceholder').style.display = (state === 'placeholder') ? 'flex' : 'none';

        ['viewFriendly', 'viewKeys', 'viewJson', 'viewRaw'].forEach(id => {
            document.getElementById(id).style.display = 'none';
        });

        if (state === 'error') {
            document.getElementById('resultError').textContent = '⚠ ' + errorMsg;
        }
    }

    function renderResult(r) {
        // Hide loading/error
        showResultState('none');

        // Parse badge
        const pb = document.getElementById('parseBadge');
        const strategy = r.parse_strategy || 'unknown';
        pb.textContent  = strategy.replace(/_/g, ' ');
        pb.className    = 'parse-badge ' + (strategy === 'direct' ? 'direct' : strategy === 'failed' ? 'failed' : 'repaired');
        pb.style.display = 'inline-block';

        // Elapsed
        const el = document.getElementById('resultElapsed');
        el.style.display = 'inline-block';
        document.getElementById('elapsedMs').textContent = r.elapsed_ms ?? '—';

        // ── Friendly view ──
        const vFriendly = document.getElementById('viewFriendly');
        let friendlyContent = '';
        if (r.friendly_result) {
            friendlyContent = `<div class="result-string">${escHtml(r.friendly_result)}</div>`;
        } else if (r.data) {
            // Render as formatted key-value
            friendlyContent = renderFriendlyData(r.data);
        } else {
            friendlyContent = `<div class="result-string" style="color:var(--text-dim);">${escHtml(r.raw_response || '')}</div>`;
        }
        vFriendly.innerHTML = friendlyContent;

        // ── Keys view ──
        const vKeys = document.getElementById('viewKeys');
        vKeys.innerHTML = renderKeySelector(r.data || {});

        // ── JSON view ──
        const vJson = document.getElementById('viewJson');
        if (r.data) {
            vJson.innerHTML = `<div class="json-tree">${syntaxHighlightJson(JSON.stringify(r.data, null, 2))}</div>`;
        } else {
            vJson.innerHTML = `<div style="color:var(--text-dim); font-family:var(--mono); font-size:0.8rem; padding:16px;">No parsed JSON available.</div>`;
        }

        // ── Raw view ──
        const vRaw = document.getElementById('viewRaw');
        vRaw.innerHTML = `<pre class="result-raw">${escHtml(r.raw_response || '')}</pre>`;

        // Show active view
        activateResultView(document.querySelector('.result-tab.active')?.dataset.view || 'friendly');
    }

    function renderFriendlyData(data) {
        if (!data || typeof data !== 'object') {
            return `<div class="result-string">${escHtml(String(data))}</div>`;
        }
        const parts = [];
        for (const [k, v] of Object.entries(data)) {
            if (typeof v === 'string') {
                parts.push(`
                    <div style="margin-bottom:14px;">
                        <div style="font-family:var(--mono);font-size:0.65rem;color:var(--amber);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">${escHtml(k)}</div>
                        <div class="result-string">${escHtml(v)}</div>
                    </div>`);
            } else {
                const preview = JSON.stringify(v, null, 2);
                parts.push(`
                    <div style="margin-bottom:14px;">
                        <div style="font-family:var(--mono);font-size:0.65rem;color:var(--amber);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">${escHtml(k)}</div>
                        <pre style="font-family:var(--mono);font-size:0.78rem;color:var(--text);background:var(--card);padding:10px;border-radius:var(--radius);border:1px solid var(--border);overflow:auto;white-space:pre-wrap;">${escHtml(preview)}</pre>
                    </div>`);
            }
        }
        return parts.join('') || `<div class="result-string" style="color:var(--text-dim);">Empty result</div>`;
    }

    function renderKeySelector(data, prefix = '') {
        if (!data || typeof data !== 'object') return '';
        const rows =[];
        flattenKeys(data, '', rows);

        if (rows.length === 0) {
            return `<div style="color:var(--text-dim);font-family:var(--mono);font-size:0.8rem;padding:16px;">No keys found.</div>`;
        }

        return `<div class="key-selector">` +
            rows.map(({key, value, isComplex}) => `
                <div class="key-row" onclick="Forge.copyKeyValue(this, ${JSON.stringify(String(value))})">
                    <div class="key-row-key">${escHtml(key)}</div>
                    <div class="key-row-value${isComplex ? ' is-object' : ''}">${isComplex ? '{…}' : escHtml(String(value)).substring(0, 120)}</div>
                    <button class="key-row-copy" title="Copy value">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>`).join('') +
        `</div>`;
    }

    function flattenKeys(obj, prefix, rows, depth = 0) {
        if (depth > 3) return;
        for (const [k, v] of Object.entries(obj || {})) {
            const fullKey = prefix ? `${prefix}.${k}` : k;
            const isComplex = v !== null && typeof v === 'object';
            rows.push({ key: fullKey, value: isComplex ? JSON.stringify(v) : v, isComplex });
            if (isComplex && !Array.isArray(v)) {
                flattenKeys(v, fullKey, rows, depth + 1);
            }
        }
    }

    function activateResultView(view) {['viewFriendly', 'viewKeys', 'viewJson', 'viewRaw'].forEach(id => {
            const el = document.getElementById(id);
            el.style.display = (id === 'view' + capitalize(view)) ? 'block' : 'none';
        });
    }

    function syntaxHighlightJson(json) {
        return json
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function(match) {
                let cls = 'number';
                if (/^"/.test(match)) {
                    cls = /:$/.test(match) ? 'key' : 'string';
                } else if (/true|false/.test(match)) {
                    cls = 'boolean';
                } else if (/null/.test(match)) {
                    cls = 'null';
                }
                return `<span class="${cls}">${match}</span>`;
            });
    }

    function clearResult() {
        _currentResult = null;
        showResultState('placeholder');
        document.getElementById('parseBadge').style.display = 'none';
        document.getElementById('resultElapsed').style.display = 'none';
        document.getElementById('btnCopyResult').style.display = 'none';
        ['viewFriendly','viewKeys','viewJson','viewRaw'].forEach(id => {
            document.getElementById(id).innerHTML = '';
        });
    }

    // ── Copy helpers ──────────────────────────────────────────────────────
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text)
                .then(() => toast('Copied!', 'success', 1500))
                .catch(() => fallbackCopy(text));
        }
        fallbackCopy(text);
    }

    function fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); toast('Copied!', 'success', 1500); }
        catch(e) { toast('Copy failed', 'error'); }
        document.body.removeChild(ta);
    }

    function copyActiveResult() {
        if (!_currentResult) return;
        const activeView = document.querySelector('.result-tab.active')?.dataset.view || 'friendly';
        let text = '';
        if (activeView === 'friendly' && _currentResult.friendly_result) {
            text = _currentResult.friendly_result;
        } else if (activeView === 'raw') {
            text = _currentResult.raw_response || '';
        } else if (_currentResult.data) {
            text = JSON.stringify(_currentResult.data, null, 2);
        } else {
            text = _currentResult.raw_response || '';
        }
        copyToClipboard(text);
    }

    function copyKeyValue(rowEl, value) {
        copyToClipboard(value);
        const btn = rowEl.querySelector('.key-row-copy');
        if (btn) {
            btn.classList.add('copied');
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = '<i class="bi bi-clipboard"></i>';
            }, 1500);
        }
    }

    // ── History ───────────────────────────────────────────────────────────
    function logHistory(r, elapsedMs) {
        if (!_currentGen) return;
        _history.unshift({
            time: new Date().toLocaleTimeString(),
            genId: _currentGen.id,
            title: _currentGen.title,
            result: r,
            ms: elapsedMs,
        });
        if (_history.length > 50) _history.pop();
    }

    function toggleHistory() {
        const modal = document.getElementById('historyModal');
        if (modal.classList.contains('open')) {
            closeModal('historyModal');
        } else {
            renderHistoryList();
            modal.classList.add('open');
        }
    }

    function renderHistoryList() {
        const c = document.getElementById('historyListContainer');
        if (_history.length === 0) {
            c.innerHTML = `<div style="text-align:center;padding:30px;color:var(--text-dim);font-family:var(--mono);font-size:0.8rem;">No generations yet</div>`;
            return;
        }
        c.innerHTML = `<div class="history-list">` +
            _history.map((h, i) => `
                <div class="history-item" onclick="Forge.loadHistoryItem(${i})">
                    <div class="history-item-time">${h.time}</div>
                    <div class="history-item-preview">${escHtml(h.title)}</div>
                    <div class="history-item-ms">${h.ms}ms</div>
                </div>`).join('') +
        `</div>`;
    }

    function loadHistoryItem(idx) {
        const h = _history[idx];
        if (!h) return;
        _currentResult = h.result;
        renderResult(h.result);
        closeModal('historyModal');
        toast('History item loaded', 'info', 1500);
    }

    function clearHistory() {
        _history =[];
        renderHistoryList();
        toast('History cleared', 'info', 1500);
    }

    // ── Toggle active from sidebar card ───────────────────────────────────
    async function toggleGenerator(id, btnEl) {
        btnEl.disabled = true;
        const r = await api('toggle', { id });
        btnEl.disabled = false;

        if (!r.ok) { toast(r.error || 'Toggle failed', 'error'); return; }

        const nowActive = r.active;

        // Update the button in-place — no full re-render needed
        if (nowActive) {
            btnEl.classList.add('is-active');
            btnEl.innerHTML = '<i class="bi bi-pause-fill"></i>';
            btnEl.title = 'Active — click to deactivate';
        } else {
            btnEl.classList.remove('is-active');
            btnEl.innerHTML = '<i class="bi bi-play-fill"></i>';
            btnEl.title = 'Inactive — click to activate';
        }

        // Also update the local _generators list so filter re-renders stay correct
        const idx = _generators.findIndex(g => g.id === id);
        if (idx !== -1) _generators[idx].active = nowActive;

        // If this is the currently open generator, refresh its workspace meta
        if (_currentGen && _currentGen.id === id) {
            _currentGen.active = nowActive;
            renderWorkspace();
        }

        toast(nowActive ? 'Generator activated' : 'Generator deactivated', 'success', 1800);
    }

    // ── Duplicate generator from sidebar card ─────────────────────────────
    async function copyGenerator(id, btnEl) {
        btnEl.disabled = true;
        const origHtml = btnEl.innerHTML;
        btnEl.innerHTML = '<i class="bi bi-hourglass-split"></i>';

        const r = await api('copy', { id });

        btnEl.disabled = false;
        btnEl.innerHTML = origHtml;

        if (!r.ok) { toast(r.error || 'Copy failed', 'error'); return; }

        toast('Duplicated! Opening copy…', 'success');
        await loadGenerators();
        // Open the new copy immediately so the user can rename/edit it
        _currentGen = null;
        await selectGenerator(r.data.new_id);
    }

    // ── Create/Edit Generator ──────────────────────────────────────────────
    function openNewGeneratorModal() {
        document.getElementById('genEditModalTitle').textContent = 'New Generator';
        document.getElementById('editGenId').value = '';
        document.getElementById('editTitle').value = '';
        document.getElementById('editIsPublic').checked = false;
        document.getElementById('editIsActive').checked = true;
        document.getElementById('editListOrder').value  = 0;
        document.getElementById('btnDeleteGen').style.display = 'none';
        document.getElementById('editConfigJson').value = JSON.stringify({
            system: {
                role: 'You are a helpful content generator.',
                instructions:[
                    'Always return valid JSON matching the output schema.',
                    'If you cannot comply, return {"error":"schema_noncompliant","reason":"..."}'
                ]
            },
            parameters: {
                topic: { type: 'string', label: 'Topic', default: '' }
            },
            output: {
                type: 'object',
                properties: { result: { type: 'string' } },
                required: ['result']
            },
            examples:[]
        }, null, 2);

        populateAreasSelect([]);
        populateOracleDicts([], null);
        document.getElementById('editOracleNumWords').value = 200;
        document.getElementById('editOracleErrorRate').value = 0.01;
        document.getElementById('jsonValidHint').textContent = '';
        document.getElementById('genEditModal').classList.add('open');
    }

    function editCurrentGenerator() {
        if (!_currentGen) return;
        const g = _currentGen;
        document.getElementById('genEditModalTitle').textContent = 'Edit Generator';
        document.getElementById('editGenId').value = g.id;
        document.getElementById('editTitle').value = g.title;
        document.getElementById('editModel').value = g.model;
        document.getElementById('editIsPublic').checked = !!g.is_public;
        document.getElementById('editIsActive').checked = !!g.active;
        document.getElementById('editListOrder').value  = g.list_order ?? 0;
        document.getElementById('btnDeleteGen').style.display = 'inline-flex';
        document.getElementById('editConfigJson').value = g.config_json || '{}';
        const selectedAreaIds = (g.display_area_keys ||[]).map(a => String(a.id));
        populateAreasSelect(selectedAreaIds);

        // Oracle
        const oracle = g.oracle_config || null;
        document.getElementById('editOracleNumWords').value  = oracle?.num_words  ?? 200;
        document.getElementById('editOracleErrorRate').value = oracle?.error_rate ?? 0.01;
        populateOracleDicts(oracle?.dictionary_ids ||[], null);

        document.getElementById('jsonValidHint').textContent = '';
        document.getElementById('genEditModal').classList.add('open');
    }

    function populateAreasSelect(selectedIds) {
        const sel = document.getElementById('editAreas');
        sel.innerHTML = _areas.map(a => {
            const val = String(a.id);
            const selected = selectedIds.includes(val) ? 'selected' : '';
            return `<option value="${val}" ${selected}>${escHtml(a.label || a.area_key)}</option>`;
        }).join('');
    }
    

    // Populate Oracle dictionaries select.
    // _dictionaries is loaded once at init. selectedIds = array of int/string ids.
    function populateOracleDicts(selectedIds, _unused) {
        const sel = document.getElementById('editOracleDicts');
        if (!sel) return;
        const ids = (selectedIds || []).map(String);
        sel.innerHTML = _dictionaries.map(d => {
            const selected = ids.includes(String(d.id)) ? 'selected' : '';
            return `<option value="${d.id}" ${selected}>${escHtml(d.title)}</option>`;
        }).join('');
        if (sel.innerHTML === '') {
            sel.innerHTML = '<option disabled style="color:var(--text-dim);">No dictionaries available</option>';
        }
    }

    async function saveGenerator() {
        const id          = document.getElementById('editGenId').value;
        const title       = document.getElementById('editTitle').value.trim();
        const model       = document.getElementById('editModel').value;
        const configJson  = document.getElementById('editConfigJson').value.trim();
        const isPublic    = document.getElementById('editIsPublic').checked;
        const isActive    = document.getElementById('editIsActive').checked;
        const listOrder   = parseInt(document.getElementById('editListOrder').value) || 0;
        const areaOpts    = document.getElementById('editAreas');
        const areaIds     = Array.from(areaOpts.selectedOptions).map(o => parseInt(o.value));

        if (!title) { toast('Title is required', 'error'); return; }

        // Validate JSON
        try {
            JSON.parse(configJson);
            document.getElementById('jsonValidHint').innerHTML = '<span style="color:var(--green);">✓ Valid JSON</span>';
        } catch(e) {
            document.getElementById('jsonValidHint').innerHTML = `<span style="color:var(--red);">✕ ${escHtml(e.message)}</span>`;
            toast('Config JSON is invalid', 'error');
            return;
        }

        // Oracle config — only send if dictionaries are selected
        const dictOpts     = document.getElementById('editOracleDicts');
        const dictIds      = Array.from(dictOpts.selectedOptions).map(o => parseInt(o.value));
        const oracleConfig = dictIds.length > 0 ? {
            dictionary_ids: dictIds,
            num_words:  parseInt(document.getElementById('editOracleNumWords').value)  || 200,
            error_rate: parseFloat(document.getElementById('editOracleErrorRate').value) || 0.01,
        } : null;

        const payload = {
            title, model, config_json: configJson,
            is_public: isPublic, is_active: isActive,
            list_order: listOrder,
            display_area_ids: areaIds,
            oracle_config: oracleConfig,
        };

        let r;
        if (id) {
            r = await api('update', { id: parseInt(id), ...payload });
        } else {
            r = await api('create', payload);
        }

        if (r.ok) {
            toast(r.message || 'Saved!', 'success');
            closeModal('genEditModal');
            await loadGenerators();

            // Determine which generator to (re)load into workspace
            const targetId = r.data?.id ? r.data.id : (id ? parseInt(id) : null);
            if (targetId) {
                // Force a fresh fetch so the workspace reflects the saved state
                _currentGen = null;
                await selectGenerator(targetId);
            }
        } else {
            toast('Save failed: ' + r.error, 'error');
        }
    }

    async function deleteCurrentGenerator() {
        const id = document.getElementById('editGenId').value;
        if (!id) return;
        if (!confirm('Delete this generator? This cannot be undone.')) return;

        const r = await api('delete', { id: parseInt(id) });
        if (r.ok) {
            toast(r.message || 'Deleted', 'success');
            closeModal('genEditModal');
            _currentGen = null;
            document.getElementById('forgeEmpty').style.display = 'flex';
            document.getElementById('forgeWorkspace').style.display = 'none';
            await loadGenerators();
        } else {
            toast('Delete failed: ' + r.error, 'error');
        }
    }

    // ── Modal helpers ─────────────────────────────────────────────────────
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }

    // ── Event bindings ────────────────────────────────────────────────────
    function bindEvents() {
        // Result tabs
        document.querySelectorAll('.result-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.result-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activateResultView(btn.dataset.view);
            });
        });

        // Sidebar search
        document.getElementById('sidebarSearch').addEventListener('input', () => {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(() => renderSidebar(_generators), 200);
        });

        // Area filter chips
        document.getElementById('areaFilterRow').addEventListener('click', async (e) => {
            const chip = e.target.closest('.filter-chip');
            if (!chip) return;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            _activeArea = chip.dataset.area;
            await loadGenerators();
        });

        // Config JSON live validation
        document.getElementById('editConfigJson').addEventListener('input', () => {
            const hint = document.getElementById('jsonValidHint');
            try {
                JSON.parse(document.getElementById('editConfigJson').value);
                hint.innerHTML = '<span style="color:var(--green);">✓ Valid JSON</span>';
            } catch(e) {
                hint.innerHTML = `<span style="color:var(--red);">✕ Invalid JSON</span>`;
            }
        });

        // Close modals on overlay click
        document.querySelectorAll('.forge-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('open');
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                generate();
            }
            if (e.key === 'Escape') {
                document.querySelectorAll('.forge-modal-overlay.open').forEach(m => m.classList.remove('open'));
            }
        });
    }

    // ── Utilities ─────────────────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }
    function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

    // ── Public API ────────────────────────────────────────────────────────
    return {
        init,
        selectGenerator,
        generate,
        clearResult,
        copyActiveResult,
        copyKeyValue,
        toggleHistory,
        loadHistoryItem,
        clearHistory,
        toggleGenerator,
        copyGenerator,
        editCurrentGenerator,
        openNewGeneratorModal,
        saveGenerator,
        deleteCurrentGenerator,
        closeModal,
    };
})();

document.addEventListener('DOMContentLoaded', () => Forge.init());
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
<?php
$content = ob_get_clean();
// Output directly — this page renders its own full HTML
echo $content;
?>
