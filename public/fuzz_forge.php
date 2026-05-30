<?php
// public/fuzz_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// FUZZ FORGE
// Lore Concept Consolidation & Reference Aggregation System
// Collects, stages, reviews, and canonizes references to the same underlying
// world concept across many tables and content sources.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

$viewportScale = !empty($_GET['embed']) ? '0.7' : '0.7';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Fuzz Forge — Lore Concept Consolidation</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Bebas+Neue&family=Barlow+Condensed:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- ADD THESE 3 LINES FOR PHOTOSWIPE -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>


<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {}
  })();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   FORGE — Design System (Signal Intelligence Theme)
═══════════════════════════════════════════════════════════════════════════ */
:root {
    --bg:           #05070d;
    --surface:      #090c14;
    --card:         #0c1020;
    --card-hover:   #0f1428;
    --border:       #161e30;
    --border-glow:  #1e2c48;
    --text:         #b8c8e0;
    --text-dim:     #3a4a62;
    --text-bright:  #ddeeff;
    --cyan:         #00d4ff;
    --cyan-dim:     rgba(0,212,255,0.08);
    --cyan-mid:     rgba(0,212,255,0.18);
    --cyan-glow:    rgba(0,212,255,0.45);
    --orange:       #ff6b2b;
    --orange-dim:   rgba(255,107,43,0.09);
    --orange-mid:   rgba(255,107,43,0.18);
    --green:        #00e5a0;
    --green-dim:    rgba(0,229,160,0.09);
    --red:          #ff3d5a;
    --red-dim:      rgba(255,61,90,0.09);
    --purple:       #a855f7;
    --purple-dim:   rgba(168,85,247,0.09);
    --yellow:       #f5c400;
    --yellow-dim:   rgba(245,196,0,0.10);
    --yellow-mid:   rgba(245,196,0,0.22);
    
    --mono:         'DM Mono', 'Fira Mono', monospace;
    --head:         'Bebas Neue', 'Barlow Condensed', sans-serif;
    --sans:         'Barlow Condensed', system-ui, sans-serif;
    --radius:       5px;
    --radius-lg:    10px;
    --surface-header: rgba(5,7,13,0.95);
    
    /* Mapping old fuzz vars to new aesthetic */
    --amber: var(--orange);
    --amber-dim: var(--orange-dim);
    --amber-mid: var(--orange-mid);
    --blue: var(--cyan);
    --blue-dim: var(--cyan-dim);
    --teal: var(--cyan);
    --teal-dim: var(--cyan-dim);
}

@media (prefers-color-scheme: light) {
    :root {
        --bg: #f0f4f8; --surface: #ffffff; --card: #ffffff; --card-hover: #f5f8fc;
        --border: #d0d8e4; --border-glow: #aab8cc; --text: #1a2533; --text-dim: #7a8fa8;
        --text-bright: #0d1824; --cyan: #0094b3; --cyan-dim: rgba(0,148,179,0.09);
        --cyan-mid: rgba(0,148,179,0.2); --cyan-glow: rgba(0,148,179,0.3);
        --orange: #c94a00; --orange-dim: rgba(201,74,0,0.08); --orange-mid: rgba(201,74,0,0.18);
        --green: #007a50; --green-dim: rgba(0,122,80,0.09); --red: #c0162f; --red-dim: rgba(192,22,47,0.08);
        --purple: #7c3aed; --purple-dim: rgba(124,58,237,0.09); --surface-header: rgba(240,244,248,0.96);
        --amber: var(--orange); --amber-dim: var(--orange-dim); --amber-mid: var(--orange-mid);
        --blue: var(--cyan); --blue-dim: var(--cyan-dim); --teal: var(--cyan); --teal-dim: var(--cyan-dim);
    }
}
:root[data-theme="light"],html[data-theme="light"],body[data-theme="light"] {
    --bg: #f0f4f8; --surface: #ffffff; --card: #ffffff; --card-hover: #f5f8fc;
    --border: #d0d8e4; --border-glow: #aab8cc; --text: #1a2533; --text-dim: #7a8fa8;
    --text-bright: #0d1824; --cyan: #0094b3; --cyan-dim: rgba(0,148,179,0.09);
    --cyan-mid: rgba(0,148,179,0.2); --cyan-glow: rgba(0,148,179,0.3);
    --orange: #c94a00; --orange-dim: rgba(201,74,0,0.08); --orange-mid: rgba(201,74,0,0.18);
    --green: #007a50; --green-dim: rgba(0,122,80,0.09); --red: #c0162f; --red-dim: rgba(192,22,47,0.08);
    --purple: #7c3aed; --purple-dim: rgba(124,58,237,0.09); --surface-header: rgba(240,244,248,0.96);
    --amber: var(--orange); --amber-dim: var(--orange-dim); --amber-mid: var(--orange-mid);
    --blue: var(--cyan); --blue-dim: var(--cyan-dim); --teal: var(--cyan); --teal-dim: var(--cyan-dim);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html,body { height:100%; background:var(--bg); color:var(--text); font-family:var(--sans); font-size:15px; line-height:1.5; -webkit-font-smoothing:antialiased; overflow-x:hidden; }

/* ─── SCANLINE OVERLAY ─── */
body::before {
    content: '';
    position: fixed; inset: 0; pointer-events: none; z-index: 1000;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,212,255,0.012) 2px,
        rgba(0,212,255,0.012) 4px
    );
}
html[data-theme="light"] body::before, body[data-theme="light"]::before { display: none; }

::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--border-glow); border-radius:4px; }
::-webkit-scrollbar-thumb:hover { background:var(--text-dim); }

/* ── LAYOUT ── */
.stats-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ── HEADER ── */
.forge-header { display:flex; align-items:center; justify-content:space-between; padding:0 20px; background:var(--surface-header); backdrop-filter: blur(12px); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:200; height:56px; }
.forge-logo { display:flex; align-items:center; gap:10px; font-family:var(--head); font-size:1.4rem; letter-spacing:4px; color:var(--text-bright); }
.forge-logo-icon { width:32px; height:32px; background:var(--cyan-dim); border:1px solid var(--cyan-mid); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; font-size:15px; color:var(--cyan); box-shadow:0 0 12px var(--cyan-glow); animation: iconPulse 4s ease-in-out infinite; }
@keyframes iconPulse {
    0%,100% { box-shadow: 0 0 8px var(--cyan-glow); }
    50%      { box-shadow: 0 0 20px var(--cyan-glow), 0 0 40px rgba(0,212,255,0.2); }
}
html[data-theme="light"] .forge-logo-icon { box-shadow: 0 0 8px var(--cyan-glow); animation: none; }
.forge-header-right { display:flex; align-items:center; gap:8px; }
.forge-header-stat { display:flex; align-items:center; font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); padding:4px 10px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); gap:4px; text-transform: uppercase; letter-spacing: 1px; }
.forge-header-stat .val { color:var(--cyan); font-weight: 500; }

/* ── MAIN ── */
.stats-main {
    padding: 20px 16px 60px;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    display: flex; flex-direction: column; gap: 16px;
}

/* ── FILTER TOOLBAR ── */
.filter-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.sidebar-search-wrap { position:relative; flex: 1; min-width: 250px; }
.sidebar-search-wrap::before { content:'⌕'; position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--cyan); font-size:16px; pointer-events:none; z-index:1; }
.sidebar-search-input { width:100%; padding:9px 10px 9px 36px; background:var(--bg); border:1px solid var(--border-glow); border-radius:var(--radius); color:var(--text-bright); font-family:var(--mono); font-size:0.85rem; transition:border-color 0.2s; }
.sidebar-search-input:focus { outline:none; border-color:var(--cyan); box-shadow: 0 0 8px var(--cyan-dim); }
.sidebar-filters { display:flex; gap:6px; flex-wrap:wrap; }
.filter-chip { padding:5px 12px; border-radius:20px; border:1px solid var(--border-glow); background:var(--bg); color:var(--text-dim); font-family:var(--mono); font-size:0.68rem; cursor:pointer; transition:all 0.15s; text-transform: uppercase; letter-spacing: 1px; }
.filter-chip.active { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
.filter-chip:hover:not(.active) { border-color:var(--cyan); color:var(--text-bright); }

/* ── DATA PANELS (From Stats View) ── */
.data-panel { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; min-width: 0; width: 100%; }
.data-panel-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: var(--surface); border-bottom: 1px solid var(--border); cursor: pointer; user-select: none; -webkit-tap-highlight-color: transparent; gap: 10px; }
.data-panel-head:hover { background: var(--card-hover); }
.data-panel-title { font-family: var(--head); font-size: 1.3rem; color: var(--text-bright); letter-spacing: 2px; display: flex; align-items: center; gap: 10px; flex: 1; }
.data-panel-title i { font-size: 1.1rem; }
.data-panel-title .dp-count { font-family: var(--mono); font-size: 0.75rem; color: var(--cyan); background: var(--cyan-dim); border: 1px solid var(--cyan-mid); padding: 2px 8px; border-radius: 20px; letter-spacing: 1px; vertical-align: middle; margin-left: 4px; }
.data-panel-chevron { color: var(--text-dim); font-size: 16px; transition: transform 0.2s; }
.data-panel.open .data-panel-chevron { transform: rotate(180deg); }
.data-panel-body { display: none; overflow: hidden; }
.data-panel.open .data-panel-body { display: block; }
.tbl-scroll-wrap { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
.tbl-scroll-wrap::-webkit-scrollbar { height: 4px; }
.tbl-scroll-wrap::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }

/* ── PAGINATION ── */
.panel-footer { display: flex; align-items: center; justify-content: center; gap: 14px; padding: 10px 18px; border-top: 1px solid var(--border); background: var(--surface); }
.page-info { font-family: var(--mono); font-size: 0.75rem; color: var(--text-dim); display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 1px; }
.page-info input { width: 46px; padding: 4px; background: var(--bg); border: 1px solid var(--border-glow); border-radius: 4px; color: var(--cyan); text-align: center; font-family: var(--mono); font-weight: 500; }
.page-info input:focus { outline: none; border-color: var(--cyan); box-shadow: 0 0 6px var(--cyan-dim); }

/* ── MENTIONS / CANDIDATES TABLE ── */
.forge-table { width:100%; border-collapse:collapse; font-family:var(--mono); font-size:0.72rem; text-align:left; min-width: 800px; }
.forge-table th { padding:10px 14px; border-bottom:1px solid var(--border); color:var(--text-dim); font-weight:normal; text-transform:uppercase; letter-spacing:2px; font-size:0.65rem; background:var(--surface); }
.forge-table td { padding:12px 14px; border-bottom:1px solid var(--border); color:var(--text); vertical-align:middle; }
.forge-table tr:last-child td { border-bottom: none; }
.forge-table tr:hover td { background:var(--card-hover); }
.forge-table .cell-mono { font-family:var(--mono); color:var(--text-dim); font-size:0.68rem; }
.forge-table .cell-source { color:var(--cyan); }
.forge-table .cell-text { color:var(--text-bright); font-weight:500; font-family:var(--sans); font-size:1rem; letter-spacing: 0.5px; }
.forge-table .cell-ctx { color:var(--text-dim); max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.forge-table .cell-actions { white-space:nowrap; display:flex; gap:6px; }

/* ── TABLE SORTING ── */
.sortable { cursor: pointer; transition: color 0.15s; }
.sortable:hover { color: var(--cyan); }
.sort-icon { display: inline-block; width: 12px; margin-left: 4px; font-size: 0.9em; opacity: 0.3; }
.sortable:hover .sort-icon { opacity: 0.7; }
.sort-icon.active { opacity: 1; color: var(--cyan); }

/* ── BADGES ── */
.gen-badge { font-family:var(--mono); font-size:0.62rem; padding:2px 6px; border-radius:3px; border:1px solid; white-space:nowrap; text-transform:uppercase; letter-spacing: 1px; }
.badge-stage { border-color:var(--orange); color:var(--orange); background:var(--orange-dim); }
.badge-canon { border-color:var(--green); color:var(--green); background:var(--green-dim); }
.badge-rejected { border-color:var(--red); color:var(--red); background:var(--red-dim); }
.badge-deferred { border-color:var(--text-dim); color:var(--text-dim); background:var(--bg); }
.badge-promoted { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
.badge-extracted { border-color:var(--purple); color:var(--purple); background:var(--purple-dim); }
.badge-type { border-color:var(--border-glow); color:var(--text-dim); background:var(--bg); }
.badge-count { border-color:var(--purple); color:var(--purple); background:var(--purple-dim); }

.mtype-sketch_name { color:var(--cyan); }
.mtype-sketch_desc { color:var(--cyan); }
.mtype-character_name { color:var(--purple); }
.mtype-character_desc { color:var(--purple); }
.mtype-location_name { color:var(--orange); }
.mtype-location_desc { color:var(--orange); }
.mtype-anima_name { color:var(--green); }
.mtype-anima_desc { color:var(--green); }
.mtype-background_name { color:var(--text-dim); }
.mtype-background_desc { color:var(--text-dim); }
.mtype-artifact_name { color:var(--yellow); }
.mtype-artifact_desc { color:var(--yellow); }
.mtype-vehicle_name { color:var(--red); }
.mtype-vehicle_desc { color:var(--red); }
.mtype-analysis_entity { color:var(--orange); }
.mtype-analysis_thematic { color:var(--purple); }
.mtype-kg_node { color:var(--green); }
.mtype-lore_history { color:var(--red); }
.mtype-ingredient { color:var(--text-dim); }

/* ── DOSSIER DEEP DIVE MODAL ── */
.dd-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(4px); z-index: 5000; display: none; align-items: flex-end; justify-content: center; padding: 0; }
.dd-overlay.open { display: flex; }
@media (min-width: 600px) { .dd-overlay { align-items: center; padding: 16px; } }
.dd-sheet { width: 100%; max-width: 1300px; background: var(--surface); border: 1px solid var(--border-glow); border-radius: 14px 14px 0 0; display: flex; flex-direction: column; height: 92dvh; overflow: hidden; box-shadow: 0 -10px 60px rgba(0,0,0,0.7); animation: ddSlideUp 0.22s cubic-bezier(0.25,1,0.5,1); }
@media (min-width: 600px) { .dd-sheet { border-radius: 14px; height: 88dvh; animation: ddFadeIn 0.18s ease; } }
@keyframes ddSlideUp { from { transform: translateY(40px); opacity:0; } to { transform:none; opacity:1; } }
@keyframes ddFadeIn  { from { opacity:0; transform:scale(0.97); } to { opacity:1; transform:none; } }

.dd-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 18px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--surface-header); }
.dd-title-block { flex: 1; min-width:0; }
.dd-title { font-family: var(--head); font-size: 1.8rem; letter-spacing: 2px; color: var(--text-bright); line-height:1.1; margin-bottom: 2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
.dd-meta { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.dd-close { width: 32px; height: 32px; border: 1px solid var(--border-glow); background: transparent; border-radius: var(--radius); color: var(--text-dim); cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; -webkit-tap-highlight-color: transparent; }
.dd-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

.dd-body { flex: 1; display:flex; flex-direction:column; min-height:0; background: var(--bg); }

/* Left / Right Pane inside Deep Dive */
.workspace-body { display:flex; flex-direction:row; flex:1; min-height:0; overflow:hidden; }
.left-panel { width: 380px; border-right:1px solid var(--border); background:var(--surface); display:flex; flex-direction:column; overflow-y:auto; flex-shrink: 0; }
.right-panel { display:flex; flex-direction:column; overflow:hidden; background: var(--bg); flex: 1; min-width: 0; }

@media (max-width: 900px) { 
    .workspace-body { flex-direction: column; } 
    .left-panel { width: 100%; flex: 0 0 50%; max-height: 50%; border-right:none; border-bottom:1px solid var(--border); overflow-y:auto; } 
    .right-panel { flex: 1; min-height: 0; overflow: hidden; }
}

/* Left panel sections */
.left-panel-section { border-bottom:1px solid var(--border); flex-shrink:0; }
.left-panel-section.grow { flex:1; display:flex; flex-direction:column; }
.section-header { padding:12px 18px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; transition:background 0.15s; background: var(--card); }
.section-header:hover { background:var(--card-hover); }
.section-title { font-family:var(--mono); font-size:0.7rem; color:var(--text-bright); text-transform:uppercase; letter-spacing:2px; display:flex; align-items:center; gap:8px; }
.section-title i { color:var(--cyan); font-size: 14px; }
.section-body { padding:14px 18px; }

/* Form elements */
.form-group { margin-bottom:14px; }
.form-label { display:block; font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-bottom:5px; }
.form-label .param-type { color:var(--cyan); margin-left:4px; font-size:0.62rem; }
.form-input, .form-select, .form-textarea { width:100%; padding:10px 12px; background:var(--bg); border:1px solid var(--border-glow); border-radius:var(--radius); color:var(--text-bright); font-family:var(--mono); font-size:0.8rem; transition:border-color 0.15s, box-shadow 0.15s; appearance:none; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline:none; border-color:var(--cyan); box-shadow: 0 0 8px var(--cyan-dim); }
.form-textarea { resize:vertical; min-height:80px; line-height:1.5; font-family:var(--sans); font-size:0.9rem; }
.form-select { cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; padding-right:32px; }

/* ── BUTTONS ── */
.btn-forge-primary { padding:8px 16px; background:var(--cyan); color:#000; border:none; border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; transition:all 0.15s; display:inline-flex; align-items:center; justify-content:center; gap:6px; box-shadow: 0 0 8px var(--cyan-glow); }
.btn-forge-primary:hover:not(:disabled) { background:var(--text-bright); box-shadow: 0 0 12px var(--cyan-glow); }
.btn-forge-primary:disabled { opacity:0.5; cursor:not-allowed; box-shadow:none; }
.btn-forge-secondary { padding:8px 16px; background:transparent; color:var(--text-dim); border:1px solid var(--border-glow); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; transition:all 0.15s; display:inline-flex; align-items:center; justify-content:center; gap:6px; text-transform:uppercase; letter-spacing:1px; }
.btn-forge-secondary:hover { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
.btn-forge-amber { padding:8px 16px; background:var(--orange-dim); color:var(--orange); border:1px solid var(--orange-mid); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; transition:all 0.15s; display:inline-flex; align-items:center; gap:6px; }
.btn-forge-amber:hover { background:var(--orange-mid); border-color:var(--orange); }
.btn-forge-green { padding:8px 16px; background:var(--green-dim); color:var(--green); border:1px solid var(--green); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; transition:all 0.15s; display:inline-flex; align-items:center; gap:6px; }
.btn-forge-green:hover { background:var(--green); color:#000; border-color:var(--green); }
.btn-forge-danger { padding:8px 16px; background:var(--red-dim); color:var(--red); border:1px solid var(--red); border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; transition:all 0.15s; display:inline-flex; align-items:center; gap:6px; text-transform:uppercase; letter-spacing:1px; }
.btn-forge-danger:hover { background:var(--red); color:#fff; border-color:var(--red); }
.btn-icon-sm { width:32px; height:32px; border-radius:var(--radius); border:1px solid var(--border-glow); background:var(--card); color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; -webkit-tap-highlight-color: transparent;}
.btn-icon-sm:hover { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); box-shadow: 0 0 8px var(--cyan-glow); }
.btn-icon-sm.danger:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); box-shadow: 0 0 8px rgba(255,61,90,0.4); }

.btn-entity { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid var(--border-glow); background: var(--card); border-radius: 3px; color: var(--text-dim); cursor: pointer; font-size: 12px; transition: all 0.12s; flex-shrink: 0; -webkit-tap-highlight-color: transparent; text-decoration: none; }
.btn-entity:hover { border-color: var(--cyan); color: var(--cyan); background: var(--cyan-dim); box-shadow: 0 0 6px var(--cyan-glow);}

/* ── RIGHT PANEL TABS ── */
.right-panel-toolbar { padding:12px 18px; border-bottom:1px solid var(--border); background:var(--card); flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.rp-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.rp-tab { padding:6px 14px; border-radius:20px; border:1px solid var(--border-glow); background:transparent; color:var(--text-dim); font-family:var(--mono); font-size:0.68rem; cursor:pointer; transition:all 0.15s; white-space:nowrap; text-transform: uppercase; letter-spacing: 1px; }
.rp-tab.active { background:var(--cyan-dim); border-color:var(--cyan); color:var(--cyan); }
.rp-tab:hover:not(.active) { border-color:var(--cyan); color:var(--text-bright); }

.right-panel-body { flex:1; overflow:hidden; display:flex; flex-direction:column; min-height:0; }
.rp-view { display:none; flex:1; overflow:hidden; flex-direction:column; min-height:0; }
.rp-view.active { display:flex; }
.rp-view-scroll { flex:1; overflow-y:auto; padding:18px; }
.rp-view-scroll::-webkit-scrollbar { width:4px; }
.rp-view-scroll::-webkit-scrollbar-thumb { background:var(--border-glow); border-radius:4px; }

/* ── DOSSIER SPECIFICS ── */
.dossier-section { margin-bottom:24px; }
.dossier-label { font-family:var(--head); font-size:1.3rem; color:var(--text-bright); letter-spacing:2px; margin-bottom:12px; display:flex; align-items:center; gap:10px; text-transform: uppercase; }
.dossier-label::after { content:''; flex:1; height:1px; background:linear-gradient(90deg, var(--border-glow), transparent); }
.dossier-label i { color:var(--cyan); font-size:1.1rem; }

.alias-list { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.alias-tag { display:inline-flex; align-items:center; gap:6px; padding:4px 10px 4px 12px; background:var(--bg); border:1px solid var(--border-glow); border-radius:20px; font-family:var(--mono); font-size:0.75rem; color:var(--text-bright); }
.alias-tag .remove-alias { width:18px; height:18px; border:none; background:none; color:var(--text-dim); cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.15s; padding:0; }
.alias-tag .remove-alias:hover { background:var(--red-dim); color:var(--red); }

.add-alias-row { display:flex; gap:6px; }

.confidence-bar-wrap { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
.confidence-bar-bg { flex:1; height:4px; background:var(--border); border-radius:2px; overflow:hidden; }
.confidence-bar-fill { height:100%; background:var(--cyan); border-radius:2px; transition:width 0.3s; box-shadow:0 0 8px var(--cyan-glow); }
.confidence-val { font-family:var(--mono); font-size:0.75rem; color:var(--cyan); min-width:36px; text-align:right; font-weight: 500;}

.decision-bar { padding:14px 18px; border-top:1px solid var(--border); background:var(--card); flex-shrink:0; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.decision-bar-label { font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-right:4px; }

/* Icon-only decision buttons */
.btn-decide {
    width: 36px; height: 36px;
    border-radius: var(--radius);
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 16px;
    transition: all 0.15s;
    flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
}
.btn-decide-confirm { border-color: var(--green);  color: var(--green);  background: var(--green-dim); }
.btn-decide-confirm:hover  { background: var(--green);  color: #000; }
.btn-decide-promote { border-color: var(--orange); color: var(--orange); background: var(--orange-dim); }
.btn-decide-promote:hover  { background: var(--orange); color: #000; }
.btn-decide-defer   { border-color: var(--text-dim); color: var(--text-dim); background: var(--bg); }
.btn-decide-defer:hover    { border-color: var(--cyan); color: var(--cyan); background: var(--cyan-dim); }
.btn-decide-reject  { border-color: var(--red);    color: var(--red);    background: var(--red-dim); }
.btn-decide-reject:hover   { background: var(--red);    color: #fff; }



.link-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; background:var(--green-dim); border:1px solid var(--green); border-radius:var(--radius); font-family:var(--mono); font-size:0.75rem; color:var(--green); text-transform: uppercase; letter-spacing: 1px;}

.kg-search-result-list { max-height:200px; overflow-y:auto; border:1px solid var(--border-glow); border-radius:var(--radius); background:var(--bg); margin-top:4px; }
.kg-result-item { padding:10px 12px; cursor:pointer; border-bottom:1px solid var(--border); font-family:var(--mono); font-size:0.8rem; transition:background 0.15s; display:flex; align-items:center; gap:8px; color:var(--text-bright); }
.kg-result-item:last-child { border-bottom:none; }
.kg-result-item:hover { background:var(--card-hover); border-color:var(--cyan); }
.kg-result-item .kg-type { color:var(--cyan); font-size:0.65rem; text-transform: uppercase; }

.review-timeline { }
.review-event { display:flex; gap:14px; margin-bottom:16px; position:relative; }
.review-event::before { content:''; position:absolute; left:15px; top:32px; bottom:-16px; width:2px; background:var(--border); }
.review-event:last-child::before { display:none; }
.review-dot { width:32px; height:32px; border-radius:50%; border:1px solid var(--border-glow); background:var(--surface); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; position:relative; z-index:2; }
.review-dot.confirmed { border-color:var(--green); background:var(--green-dim); color:var(--green); box-shadow: 0 0 8px rgba(0,229,160,0.3); }
.review-dot.rejected { border-color:var(--red); background:var(--red-dim); color:var(--red); }
.review-dot.promoted { border-color:var(--cyan); background:var(--cyan-dim); color:var(--cyan); box-shadow: 0 0 8px var(--cyan-glow); }
.review-dot.deferred { border-color:var(--text-dim); }
.review-body { flex:1; padding-top:4px; }
.review-action { font-family:var(--mono); font-size:0.75rem; color:var(--text-bright); text-transform: uppercase; letter-spacing: 1.5px; font-weight:500; }
.review-note { font-size:0.85rem; color:var(--text-dim); margin-top:4px; font-family: var(--sans); }
.review-time { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); margin-top:4px; opacity:0.7; }

/* ── FRAMES GRID CLONED FROM REGENERATOR ── */
.frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; padding-bottom: 20px; }
.f-card { aspect-ratio: 1; background: var(--surface); border: 1px solid var(--border-glow); border-radius: 4px; position: relative; overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s; }
.f-card:hover { border-color: var(--cyan); box-shadow: 0 0 8px var(--cyan-glow); }
.f-link { display: block; width: 100%; height: 100%; overflow: hidden; cursor: pointer; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.05); }
.f-view-btn { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; background: rgba(0,0,0,0.7); color: #fff; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 12px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--cyan); border-color: var(--cyan); color: #000; }

/* ── FRAME CARD SELECTION (cloned from Regenerator) ── */
.f-card.selected { border-color: var(--cyan); box-shadow: 0 0 10px var(--cyan-glow); }
.f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 22px; background: rgba(5,7,13,0.92); padding: 0 6px; font-size: 0.62rem; color: var(--text-dim); border-top: 1px solid var(--border-glow); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; transition: background 0.12s; font-family: var(--mono); }
.f-label:hover { background: rgba(0,212,255,0.08); color: var(--text-bright); }
.f-card.selected .f-label { background: var(--cyan-dim); color: var(--cyan); border-top-color: var(--cyan-mid); }
.f-select-trigger { width: 16px; height: 16px; border: 1px solid var(--text-dim); border-radius: 2px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); flex-shrink: 0; transition: all 0.1s; font-size: 0; }
.f-card.selected .f-select-trigger { background: var(--cyan); border-color: var(--cyan); color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }
/* Shrink image area to make room for label */
.f-link { height: calc(100% - 22px); }

/* ── MODALS ── */
.forge-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); z-index:10000; display:none; align-items:center; justify-content:center; padding:16px; }
.forge-modal-overlay.open { display:flex; }
.forge-modal { background:var(--surface); border:1px solid var(--border-glow); border-radius:var(--radius-lg); width:100%; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.7); animation:modalIn 0.2s cubic-bezier(0.25,1,0.5,1); max-height:90vh; }
.forge-modal-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0; background: var(--surface-header); }
.forge-modal-title { font-family:var(--head); font-size:1.4rem; color:var(--text-bright); text-transform:uppercase; letter-spacing:3px; display:flex; align-items:center; gap:10px; }
.forge-modal-title i { color: var(--cyan); }
.forge-modal-close { width:32px; height:32px; border-radius:4px; border:1px solid var(--border-glow); background:transparent; color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:16px; }
.forge-modal-close:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); }
.forge-modal-body { padding:20px; overflow-y:auto; flex:1; min-height:0; }
.forge-modal-footer { padding:16px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; flex-shrink:0; background: var(--surface-header); }

/* ── ENTITY FORM MODAL (Deep Dive Style) ── */
.ef-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.88); backdrop-filter: blur(4px); z-index: 10500; display: none; align-items: flex-end; justify-content: center; padding: 0; }
.ef-overlay.open { display: flex; }
@media (min-width: 600px) { .ef-overlay { align-items: center; padding: 16px; } }
.ef-sheet { width: 100%; max-width: 900px; background: var(--surface); border: 1px solid var(--border-glow); border-radius: 14px 14px 0 0; display: flex; flex-direction: column; height: 92dvh; overflow: hidden; box-shadow: 0 -10px 60px rgba(0,0,0,0.7); animation: modalIn 0.22s cubic-bezier(0.25,1,0.5,1); }
@media (min-width: 600px) { .ef-sheet { border-radius: 14px; height: 88dvh; } }
.ef-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--surface-header); }
.ef-title { font-family: var(--head); font-size: 1.4rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-bright); flex: 1; }
.ef-body { flex: 1; overflow: hidden; }
.ef-body iframe { width: 100%; height: 100%; border: none; background: var(--bg); display: block; }

/* ── TOAST ── */
.forge-toast-container { position:fixed; bottom:20px; right:20px; z-index:11000; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.forge-toast { padding:12px 18px; border-radius:var(--radius); background:var(--card); border:1px solid var(--border-glow); font-family:var(--mono); font-size:0.78rem; color:var(--text-bright); box-shadow:0 4px 20px rgba(0,0,0,0.5); animation:toastIn 0.25s ease; pointer-events:all; cursor:pointer; max-width:340px; display:flex; align-items:center; gap:10px; text-transform: uppercase; letter-spacing: 1px;}
.forge-toast.success { border-color:var(--green); color: var(--green); }
.forge-toast.error { border-color:var(--red); color:var(--red); }
.forge-toast.info { border-color:var(--cyan); color: var(--cyan); }
.forge-toast.out { animation:toastOut 0.25s ease forwards; }

/* ── SSE LOG STREAM ── */
.sse-stream-box { flex:1; font-family:var(--mono); font-size:0.75rem; line-height:1.6; white-space:pre-wrap; word-break:break-word; padding:14px; background:#020306; border-radius:var(--radius); overflow-y:auto; color:#9ba8c0; border:1px solid var(--border-glow); min-height:120px; max-height:40vh; box-shadow: inset 0 0 20px rgba(0,0,0,0.8); }
.sse-line-info { color:#9ba8c0; }
.sse-line-ok { color:var(--green); }
.sse-line-warn { color:var(--orange); }
.sse-line-err { color:var(--red); }
.sse-line-found { color:var(--cyan); text-shadow: 0 0 5px var(--cyan-glow); }
.sse-line-stage { color:var(--purple); }

/* Row-type label in mentions */
.source-label { display:inline-block; padding:1px 5px; border-radius:3px; font-size:0.62rem; font-family:var(--mono); background:var(--card); border:1px solid var(--border); color:var(--text-dim); text-transform: uppercase; letter-spacing: 1px;}



/* ── MENTION BLOCK LIST (replaces flat table rows in Mentions tab) ── */
.mention-list { display: flex; flex-direction: column; gap: 0; }

.mention-block {
    border-bottom: 1px solid var(--border);
}
.mention-block:last-child { border-bottom: none; }

.mention-row {
    display: grid;
    grid-template-columns: 18% 44% 38%;
    align-items: start;
    padding: 12px 14px;
    gap: 0;
    transition: background 0.12s;
}
.mention-row:hover { background: var(--card-hover); }

.mention-col { padding: 0 6px; }
.mention-col:first-child { padding-left: 0; }
.mention-col:last-child  { padding-right: 0; }

/* Inline frames strip beneath each mention row */
.mention-frames-strip {
    padding: 10px 14px 14px;
    background: rgba(0,212,255,0.025);
    border-top: 1px solid var(--border);
    border-left: 3px solid var(--cyan-mid);
}
.mention-frames-label {
    font-family: var(--mono);
    font-size: 0.65rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.mention-frames-label i { color: var(--cyan); opacity: 0.7; }

/* 2-col on mobile, expands wider on desktop */
.mention-frames-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px;
}

/* Richer cell typography (also used in the new mention rows) */
.cell-text-stack { display:flex; flex-direction:column; gap:5px; }
.cell-text-name  { color: var(--text); font-family: var(--sans); font-size: 0.78rem; line-height: 1.2; opacity: 0.98; word-break: break-word; overflow-wrap: anywhere; }
.cell-text-main  { color:var(--text-bright); font-weight:500; font-family:var(--sans); font-size:1.05rem; letter-spacing: 0.5px; line-height:1.2; word-break:break-word; overflow-wrap:anywhere; }
.cell-text-ctx   { color:var(--text-dim); font-size:0.82rem; font-family:var(--sans); line-height:1.35; white-space:normal; overflow:visible; text-overflow:unset; word-break:break-word; overflow-wrap:anywhere; }
.cell-meta-stack { display:flex; flex-direction:column; gap:6px; }
.cell-meta-row   { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }






/* Spinner */
.spinner { display:inline-block; width:16px; height:16px; border:2px solid var(--border-glow); border-top-color:var(--cyan); border-radius:50%; animation:spin 0.7s linear infinite; vertical-align:middle; }
@keyframes spin { to { transform:rotate(360deg); } }

@keyframes toastIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
@keyframes toastOut { to { opacity:0; transform:translateY(10px); } }
@keyframes modalIn { from { opacity:0; transform:scale(0.96) translateY(20px); } to { opacity:1; transform:none; } }

/* Stats mini-grid */
.stats-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-bottom:16px; }
.stat-tile { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:14px; text-align:center; transition: border-color 0.2s, background 0.2s; }
.stat-tile:hover { background: var(--card-hover); border-color: var(--border-glow); }
.stat-tile .val { font-family:var(--head); font-size:2.2rem; line-height: 1; color:var(--cyan); letter-spacing: 2px; margin-bottom:6px; }
.stat-tile .lbl { font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1.5px; }
</style>
</head>
<body>

<div class="stats-layout">

    <!-- ══════════════════════════════════════════════════════════ HEADER -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-diagram-3-fill"></i></div>
            FUZZ FORGE
        </div>
        <div class="forge-header-right">
            <div class="forge-header-stat" title="Total candidates">
                <i class="bi bi-collection" style="color:var(--cyan);"></i>
                <span class="val" id="statCandidates">—</span>
            </div>
            <div class="forge-header-stat" title="Pending review">
                <i class="bi bi-hourglass-split" style="color:var(--orange);"></i>
                <span class="val" style="color:var(--orange);" id="statPending">—</span>
            </div>
            <button class="btn-icon-sm" onclick="FuzzForge.openExtractModal()" title="Run Extraction Job">
                <i class="bi bi-cpu"></i>
            </button>
            <button class="btn-icon-sm" onclick="FuzzForge.openStatsModal()" title="System Stats">
                <i class="bi bi-bar-chart"></i>
            </button>
            <button class="btn-icon-sm" onclick="FuzzForge.newCandidate()" title="New Candidate">
                <i class="bi bi-plus-lg"></i>
            </button>
            <a href="/dashboard.php" class="btn-icon-sm" title="Back to Dashboard">
                <i class="bi bi-house"></i>
            </a>
        </div>
    </header>

    <!-- ══════════════════════════════════════════════════════════ MAIN -->
    <main class="stats-main">

        <!-- FILTER TOOLBAR -->
        <div class="filter-toolbar">
            <div class="sidebar-search-wrap">
                <input type="text" class="sidebar-search-input" id="searchInput" placeholder="Search concepts..." autocomplete="off">
            </div>
            <div class="sidebar-filters" id="topFilters">
                <button class="filter-chip active" data-filter="all">All</button>
                <button class="filter-chip" data-filter="character">Character</button>
                <button class="filter-chip" data-filter="location">Location</button>
                <button class="filter-chip" data-filter="faction">Faction</button>
                <button class="filter-chip" data-filter="artifact">Artifact</button>
                <button class="filter-chip" data-filter="concept">Concept</button>
                <button class="filter-chip" data-filter="event">Event</button>
            </div>
        </div>

        <!-- PENDING PANEL -->
        <div class="data-panel open" id="panelPending">
            <div class="data-panel-head" onclick="FuzzForge.togglePanel(this)">
                <div class="data-panel-title">
                    <i class="bi bi-hourglass-split" style="color:var(--orange);"></i>
                    Pending Candidates
                    <span class="dp-count" id="countPending" style="color:var(--orange); border-color:var(--orange-mid); background:var(--orange-dim);">—</span>
                </div>
                <i class="bi bi-chevron-down data-panel-chevron"></i>
            </div>
            <div class="data-panel-body">
                <div class="tbl-scroll-wrap">
                    <table class="forge-table">
                        <thead id="theadPending"></thead>
                        <tbody id="tbodyPending"></tbody>
                    </table>
                </div>
                <div class="panel-footer">
                    <button class="btn-forge-secondary btn-icon-sm" onclick="FuzzForge.changePage('Pending', -1)"><i class="bi bi-chevron-left"></i></button>
                    <span class="page-info">
                        Page <input type="number" id="pageInpPending" value="1" min="1" onchange="FuzzForge.goToPage('Pending', this.value)"> of <span id="pageTotalPending">1</span>
                    </span>
                    <button class="btn-forge-secondary btn-icon-sm" onclick="FuzzForge.changePage('Pending', 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- RESOLVED PANEL -->
        <div class="data-panel open" id="panelResolved">
            <div class="data-panel-head" onclick="FuzzForge.togglePanel(this)">
                <div class="data-panel-title">
                    <i class="bi bi-check-circle" style="color:var(--green);"></i>
                    Resolved & Promoted
                    <span class="dp-count" id="countResolved" style="color:var(--green); border-color:var(--green-mid); background:var(--green-dim);">—</span>
                </div>
                <i class="bi bi-chevron-down data-panel-chevron"></i>
            </div>
            <div class="data-panel-body">
                <div class="tbl-scroll-wrap">
                    <table class="forge-table">
                        <thead id="theadResolved"></thead>
                        <tbody id="tbodyResolved"></tbody>
                    </table>
                </div>
                <div class="panel-footer">
                    <button class="btn-forge-secondary btn-icon-sm" onclick="FuzzForge.changePage('Resolved', -1)"><i class="bi bi-chevron-left"></i></button>
                    <span class="page-info">
                        Page <input type="number" id="pageInpResolved" value="1" min="1" onchange="FuzzForge.goToPage('Resolved', this.value)"> of <span id="pageTotalResolved">1</span>
                    </span>
                    <button class="btn-forge-secondary btn-icon-sm" onclick="FuzzForge.changePage('Resolved', 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- REJECTED PANEL -->
        <div class="data-panel" id="panelRejected">
            <div class="data-panel-head" onclick="FuzzForge.togglePanel(this)">
                <div class="data-panel-title">
                    <i class="bi bi-x-circle" style="color:var(--text-dim);"></i>
                    Rejected Concepts
                    <span class="dp-count" id="countRejected" style="color:var(--text-dim); border-color:var(--border); background:transparent;">—</span>
                </div>
                <i class="bi bi-chevron-down data-panel-chevron"></i>
            </div>
            <div class="data-panel-body">
                <div class="tbl-scroll-wrap">
                    <table class="forge-table">
                        <thead id="theadRejected"></thead>
                        <tbody id="tbodyRejected"></tbody>
                    </table>
                </div>
                <div class="panel-footer">
                    <button class="btn-forge-secondary btn-icon-sm" onclick="FuzzForge.changePage('Rejected', -1)"><i class="bi bi-chevron-left"></i></button>
                    <span class="page-info">
                        Page <input type="number" id="pageInpRejected" value="1" min="1" onchange="FuzzForge.goToPage('Rejected', this.value)"> of <span id="pageTotalRejected">1</span>
                    </span>
                    <button class="btn-forge-secondary btn-icon-sm" onclick="FuzzForge.changePage('Rejected', 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

    </main>

</div><!-- /stats-layout -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     DEEP DIVE MODAL (The Dossier Editor)
═══════════════════════════════════════════════════════════════════════════ -->
<div class="dd-overlay" id="dossierModal">
    <div class="dd-sheet">
        <div class="dd-header">
            <div class="dd-title-block">
                <div class="dd-title" id="wsTitle">—</div>
                <div class="dd-meta" id="wsMeta" style="margin-top:6px;"></div>
            </div>
            <!-- ── CHANGE 1: separator + safety gap before dangerous buttons ── -->
            <div style="display:flex; gap:6px; align-items:center;">
                <div style="width:1px; height:24px; background:var(--border); margin:0 10px;"></div>
                <button class="btn-icon-sm danger" onclick="FuzzForge.deleteCandidate()" title="Delete Candidate" id="btnDeleteCandidate"><i class="bi bi-trash"></i></button>
                <button class="dd-close" onclick="FuzzForge.closeDossier()"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="dd-body">
            
            <div class="workspace-body">
                <!-- ── LEFT PANEL: DOSSIER EDITOR ── -->
                <div class="left-panel">
                    
                    <!-- Concept Identity -->
                    <div class="left-panel-section">
                        <div class="section-header" onclick="FuzzForge.toggleSection('secIdentity')">
                            <span class="section-title"><i class="bi bi-fingerprint"></i> Concept Identity</span>
                            <i class="bi bi-chevron-down" id="secIdentityChevron" style="font-size:14px; color:var(--text-dim);"></i>
                        </div>
                        <div class="section-body" id="secIdentity">
                            <div class="form-group">
                                <label class="form-label">Canonical Label</label>
                                <input type="text" id="cand_label" class="form-input" placeholder="e.g. The Crater City Mainframe">
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                <div class="form-group">
                                    <label class="form-label">Concept Type</label>
                                    <select id="cand_concept_type" class="form-select">
                                        <option value="">— unclassified —</option>
                                        <option value="character">Character</option>
                                        <option value="location">Location</option>
                                        <option value="faction">Faction</option>
                                        <option value="artifact">Artifact</option>
                                        <option value="event">Event</option>
                                        <option value="concept">Concept</option>
                                        <option value="relationship">Relationship</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Review Status</label>
                                    <select id="cand_status" class="form-select">
                                        <option value="extracted">Extracted</option>
                                        <option value="grouped">Grouped</option>
                                        <option value="reviewed">Reviewed</option>
                                        <option value="promoted">Promoted</option>
                                        <option value="canonized">Canonized</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="deferred">Deferred</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-label">Confidence <span class="param-type" id="confLabel">0%</span></div>
                                <input type="range" id="cand_confidence" min="0" max="100" value="50" style="width:100%; accent-color:var(--cyan);" oninput="document.getElementById('confLabel').textContent=this.value+'%'">
                            </div>
                        </div>
                    </div>

                    <!-- Aliases -->
                    <div class="left-panel-section">
                        <div class="section-header" onclick="FuzzForge.toggleSection('secAliases')">
                            <span class="section-title"><i class="bi bi-tags"></i> Aliases & Variants</span>
                            <i class="bi bi-chevron-down" id="secAliasesChevron" style="font-size:14px; color:var(--text-dim);"></i>
                        </div>
                        <div class="section-body" id="secAliases">
                            <div class="alias-list" id="aliasList"></div>
                            <div class="add-alias-row">
                                <input type="text" id="newAliasInput" class="form-input" placeholder="Add variant or alias…" style="font-size:0.8rem;">
                                <button class="btn-icon-sm" onclick="FuzzForge.addAlias()" title="Add Alias" style="border-color:var(--cyan); color:var(--cyan);">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- KG Node Link -->
                    <div class="left-panel-section">
                        <div class="section-header" onclick="FuzzForge.toggleSection('secKGLink')">
                            <span class="section-title"><i class="bi bi-link-45deg"></i> KG Node Resolution</span>
                            <i class="bi bi-chevron-down" id="secKGLinkChevron" style="font-size:14px; color:var(--text-dim);"></i>
                        </div>
                        <div class="section-body" id="secKGLink">
                            <div id="kgLinkStatus" style="margin-bottom:12px;"></div>
                            <div class="form-group" id="kgSearchGroup">
                                <label class="form-label">Search KG Nodes</label>
                                <input type="text" id="kgSearchInput" class="form-input" placeholder="Search kg_nodes…" oninput="FuzzForge.debounceKGSearch()" style="margin-bottom:8px;">
                                <div id="kgSearchResults" class="kg-search-result-list" style="display:none;"></div>
                            </div>
                            <div id="kgLinkedNode" style="display:none;">
                                <div class="link-badge" id="kgLinkedBadge"></div>
                                <button class="btn-forge-secondary" style="margin-top:10px; font-size:0.7rem; padding:6px 12px;" onclick="FuzzForge.unlinkKGNode()">
                                    <i class="bi bi-x-lg"></i> Unlink
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="left-panel-section grow">
                        <div class="section-header" onclick="FuzzForge.toggleSection('secNotes')">
                            <span class="section-title"><i class="bi bi-journal-text"></i> Curator Notes</span>
                            <i class="bi bi-chevron-down" id="secNotesChevron" style="font-size:14px; color:var(--text-dim);"></i>
                        </div>
                        <div class="section-body" id="secNotes" style="flex:1; display:flex; flex-direction:column;">
                            <textarea id="cand_notes" class="form-textarea" placeholder="Notes, reasoning, evidence summary…" style="flex:1; min-height:100px;"></textarea>
                        </div>
                    </div>

                    <!-- Save bar -->
                    <div style="padding:14px 18px; border-top:1px solid var(--border); background:var(--card); flex-shrink:0; display:flex; gap:10px;">
                        <button class="btn-forge-primary" onclick="FuzzForge.saveCandidate()" style="flex:1;">
                            <i class="bi bi-floppy"></i> SAVE
                        </button>
                        <button class="btn-forge-secondary" onclick="FuzzForge.runSemanticSearch()" title="Find Related via Chroma">
                            <i class="bi bi-search"></i> FIND RELATED
                        </button>
                    </div>

                    <!-- Decision bar -->
                    <div class="decision-bar">
                        <span class="decision-bar-label">Decide:</span>
                        <button class="btn-decide btn-decide-confirm" onclick="FuzzForge.reviewAction('confirmed')" title="Confirm">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn-decide btn-decide-promote" onclick="FuzzForge.reviewAction('promoted')" title="Promote to KG">
                            <i class="bi bi-arrow-up-circle-fill"></i>
                        </button>
                        <button class="btn-decide btn-decide-defer" onclick="FuzzForge.reviewAction('deferred')" title="Defer">
                            <i class="bi bi-clock"></i>
                        </button>
                        <button class="btn-decide btn-decide-reject" onclick="FuzzForge.reviewAction('rejected')" title="Reject">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                </div><!-- /left-panel -->

                <!-- ── RIGHT PANEL: EVIDENCE & HISTORY ── -->
                <div class="right-panel">
                    <div class="right-panel-toolbar">
                        <div class="rp-tabs" id="rpTabs">
                            <button class="rp-tab active" data-view="mentions" title="Mentions"><i class="bi bi-chat-left-text"></i> <span id="mentionCount" class="gen-badge badge-count" style="margin-left:2px; padding:1px 5px;"></span></button>
                            <button class="rp-tab" data-view="frames" title="Frames"><i class="bi bi-grid-3x3-gap"></i> <span id="frameCount" class="gen-badge badge-count" style="margin-left:2px; padding:1px 5px;"></span></button>
                            <button class="rp-tab" data-view="links" title="Fuzzy Links"><i class="bi bi-shuffle"></i></button>
                            <button class="rp-tab" data-view="reviews" title="Review History"><i class="bi bi-clock-history"></i></button>
                            <button class="rp-tab" data-view="semantic" title="Semantic Matches"><i class="bi bi-search-heart"></i></button>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-icon-sm" onclick="FuzzForge.refreshRightPanel()" title="Refresh">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                            <button class="btn-icon-sm" onclick="FuzzForge.openAddMentionModal()" title="Add Mention Manually">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                            <!-- Frame selection controls — shown only when Frames tab is active -->
                            <div id="frameSelectionControls" style="display:none; align-items:center; gap:6px;">
                                <div style="width:1px; background:var(--border); height:20px; margin:0 2px;"></div>
                                <button class="btn-forge-secondary" style="padding:4px 10px; font-size:0.65rem;" onclick="FuzzForge.toggleAllFrames(false)" title="Deselect all frames">None</button>
                                <button class="btn-forge-secondary" style="padding:4px 10px; font-size:0.65rem;" onclick="FuzzForge.toggleAllFrames(true)" title="Select all frames">All</button>
                                <button class="btn-forge-danger" id="btnRemoveFrames" style="padding:4px 12px; font-size:0.65rem; display:none;" onclick="FuzzForge.removeSelectedFrameEntities()" title="Remove all mentions for selected frame entities">
                                    <i class="bi bi-trash"></i> Remove Selected
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="right-panel-body">

                        <!-- ── MENTIONS VIEW ── -->
                        <div class="rp-view active" id="viewMentions">
                            <div class="rp-view-scroll" style="padding:0; overflow-y:auto;">
                                <div class="mention-list" id="mentionList"></div>
                            </div>
                        </div>

                        <!-- ── FRAMES VIEW ── -->
                        <div class="rp-view" id="viewFrames">
                            <div class="rp-view-scroll">
                                <div class="frames-grid" id="framesGridContainer">
                                    <!-- JS populated -->
                                </div>
                            </div>
                        </div>

                        <!-- ── FUZZY LINKS VIEW ── -->
                        <div class="rp-view" id="viewLinks">
                            <div class="rp-view-scroll">
                                <div class="dossier-section">
                                    <div class="dossier-label"><i class="bi bi-shuffle"></i> Fuzzy Relationships</div>
                                    <div id="linksContainer">
                                        <div style="color:var(--text-dim); font-family:var(--mono); font-size:0.78rem; text-align:center; padding:30px;">No fuzzy links recorded yet.</div>
                                    </div>
                                    <button class="btn-forge-secondary" style="margin-top:16px; font-size:0.75rem;" onclick="FuzzForge.openAddLinkModal()">
                                        <i class="bi bi-plus-lg"></i> Add Fuzzy Link
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ── REVIEWS VIEW ── -->
                        <div class="rp-view" id="viewReviews">
                            <div class="rp-view-scroll">
                                <div class="dossier-label"><i class="bi bi-clock-history"></i> Review Timeline</div>
                                <div class="review-timeline" id="reviewTimeline">
                                    <div style="color:var(--text-dim); font-family:var(--mono); font-size:0.78rem; text-align:center; padding:30px;">No review events yet.</div>
                                </div>
                            </div>
                        </div>

                        <!-- ── SEMANTIC MATCHES VIEW ── -->
                        <div class="rp-view" id="viewSemantic">
                            <div class="rp-view-scroll">
                                <div class="dossier-label"><i class="bi bi-search-heart"></i> Chroma Semantic Matches</div>
                                <div id="semanticMatchesContainer">
                                    <div style="color:var(--text-dim); font-family:var(--mono); font-size:0.78rem; text-align:center; padding:30px;">
                                        Click "FIND RELATED" to run a semantic search against Chroma for this concept.
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /right-panel-body -->
                </div><!-- /right-panel -->

            </div><!-- /workspace-body -->
            
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════ MODALS -->

<!-- Extraction Job Modal -->
<div class="forge-modal-overlay" id="extractModal">
    <div class="forge-modal" style="max-width:720px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title"><i class="bi bi-cpu"></i> Run Extraction Job</div>
            <button class="forge-modal-close" onclick="FuzzForge.closeModal('extractModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <!-- ── PHASE 1: Config (shown before/between jobs) ── -->
            <div id="extractPhase1">
                <p style="font-size:0.85rem; color:var(--text-dim); margin-bottom:20px; font-family: var(--sans);">
                    Extracts and deduplicates lore mentions, then submits them to the tablet PyAPI for 
                    TF-IDF sparse clustering. The clustering job runs async — you can close this dialog 
                    and return later to apply the results.
                </p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                    <div class="form-group">
                        <label class="form-label">Source Tables</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_sketches" checked> sketches (name + regex noun phrase)
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_analysis" checked> sketch_analysis (entities + thematics)
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_lore" checked> sketch_lore_history (entity_name)
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_kg"> kg_nodes (name)
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_ingredients"> sketch_ingredients (prompt_fragment)
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_characters" checked> characters
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_animas"> animas
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_locations" checked> locations
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_backgrounds"> backgrounds
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_artifacts"> artifacts
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="src_vehicles"> vehicles
                            </label>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label class="form-label">Similarity Threshold <span class="param-type" id="extThreshLabel">82%</span></label>
                            <input type="range" id="ext_threshold" min="50" max="99" value="82" style="width:100%; accent-color:var(--cyan);" oninput="document.getElementById('extThreshLabel').textContent=this.value+'%'">
                            <div style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); margin-top:4px;">Higher = stricter grouping. 82% catches most typos/variants.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Candidates to Create</label>
                            <input type="number" id="ext_max_candidates" class="form-input" value="500000">
                        </div>
                        <div>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-family:var(--mono); font-size:0.78rem; color:var(--text-bright);">
                                <input type="checkbox" id="ext_skip_existing" checked> Skip already-grouped mentions
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── JOB STATUS PANEL (shown while polling / resuming) ── -->
            <div id="extractJobPanel" style="display:none; margin-bottom:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px;">
                    <div style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px;">Active Job</div>
                    <div style="display:flex; gap:6px;">
                        <button class="btn-forge-secondary" style="padding:4px 10px; font-size:0.68rem;" onclick="FuzzForge.copyExtractionResumeUrl()" title="Copy resume URL to clipboard">
                            <i class="bi bi-clipboard"></i> Copy URL
                        </button>
                        <button class="btn-forge-danger" style="padding:4px 10px; font-size:0.68rem;" onclick="FuzzForge.clearExtractionJob()" title="Forget this job and start fresh">
                            <i class="bi bi-x-lg"></i> Clear Job
                        </button>
                    </div>
                </div>
                <div style="background:var(--bg); border:1px solid var(--border-glow); border-radius:var(--radius); padding:10px 14px; font-family:var(--mono); font-size:0.72rem; margin-bottom:10px;">
                    <div style="color:var(--text-dim); margin-bottom:4px;">JOB ID</div>
                    <div id="extractJobId" style="color:var(--cyan); word-break:break-all;"></div>
                </div>
                <!-- Progress bar -->
                <div id="extractProgressWrap" style="margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); margin-bottom:4px; text-transform:uppercase; letter-spacing:1px;">
                        <span id="extractProgressStage">Queued</span>
                        <span id="extractProgressPct">0%</span>
                    </div>
                    <div style="height:4px; background:var(--border); border-radius:2px; overflow:hidden;">
                        <div id="extractProgressBar" style="height:100%; width:0%; background:var(--cyan); border-radius:2px; transition:width 0.4s; box-shadow:0 0 8px var(--cyan-glow);"></div>
                    </div>
                </div>
                <!-- Apply button — shown only when CLUSTERING is complete -->
                <div id="extractApplyRow" style="display:none; padding:12px; background:var(--green-dim); border:1px solid var(--green); border-radius:var(--radius); display:none; align-items:center; gap:12px;">
                    <i class="bi bi-check-circle-fill" style="color:var(--green); font-size:1.2rem;"></i>
                    <span style="font-family:var(--mono); font-size:0.78rem; color:var(--green); flex:1;">Clustering complete! Apply results to create candidates and mentions.</span>
                    <button class="btn-forge-green" style="padding:8px 18px;" onclick="FuzzForge.applyExtractionClusters()">
                        <i class="bi bi-database-fill-add"></i> Apply Clusters
                    </button>
                </div>
            </div>

            <!-- ── LOG STREAM ── -->
            <div id="extractionJobLog" style="display:none; margin-top:4px;">
                <div class="dossier-label" style="font-size:1rem; margin-bottom:8px;"><i class="bi bi-terminal"></i> Job Stream</div>
                <div class="sse-stream-box" id="extractionStreamBox">Waiting…</div>
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="FuzzForge.closeModal('extractModal')">Close</button>
            <button class="btn-forge-primary" id="btnRunExtraction" onclick="FuzzForge.runExtraction()">
                <i class="bi bi-cpu"></i> Extract &amp; Submit
            </button>
        </div>
    </div>
</div>

<!-- Stats Modal -->
<div class="forge-modal-overlay" id="statsModal">
    <div class="forge-modal" style="max-width:680px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title"><i class="bi bi-bar-chart"></i> Fuzz System Stats</div>
            <button class="forge-modal-close" onclick="FuzzForge.closeModal('statsModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body" id="statsModalBody">
            <div style="text-align:center; padding:40px; color:var(--text-dim); font-family:var(--mono); text-transform:uppercase; letter-spacing:2px;">Loading stats…</div>
        </div>
    </div>
</div>

<!-- Add Mention Modal -->
<div class="forge-modal-overlay" id="addMentionModal">
    <div class="forge-modal" style="max-width:580px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title"><i class="bi bi-plus-circle"></i> Add Mention</div>
            <button class="forge-modal-close" onclick="FuzzForge.closeModal('addMentionModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Source Table</label>
                    <select id="mention_source_table" class="form-select">
                        <option value="sketches">sketches</option>
                        <option value="characters">characters</option>
                        <option value="locations">locations</option>
                        <option value="animas">animas</option>
                        <option value="backgrounds">backgrounds</option>
                        <option value="artifacts">artifacts</option>
                        <option value="vehicles">vehicles</option>
                        <option value="sketch_analysis">sketch_analysis</option>
                        <option value="sketch_lore_history">sketch_lore_history</option>
                        <option value="kg_nodes">kg_nodes</option>
                        <option value="sketch_ingredients">sketch_ingredients</option>
                        <option value="manual">manual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Source Row ID</label>
                    <input type="number" id="mention_source_id" class="form-input" placeholder="Row ID">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Source Field</label>
                    <input type="text" id="mention_source_field" class="form-input" placeholder="e.g. name, description">
                </div>
                <div class="form-group">
                    <label class="form-label">Mention Type</label>
                    <select id="mention_type" class="form-select">
                        <option value="sketch_name">sketch_name</option>
                        <option value="sketch_desc">sketch_desc</option>
                        <option value="character_name">character_name</option>
                        <option value="character_desc">character_desc</option>
                        <option value="location_name">location_name</option>
                        <option value="location_desc">location_desc</option>
                        <option value="anima_name">anima_name</option>
                        <option value="anima_desc">anima_desc</option>
                        <option value="background_name">background_name</option>
                        <option value="background_desc">background_desc</option>
                        <option value="artifact_name">artifact_name</option>
                        <option value="artifact_desc">artifact_desc</option>
                        <option value="vehicle_name">vehicle_name</option>
                        <option value="vehicle_desc">vehicle_desc</option>
                        <option value="analysis_entity">analysis_entity</option>
                        <option value="analysis_thematic">analysis_thematic</option>
                        <option value="kg_node">kg_node</option>
                        <option value="lore_history">lore_history</option>
                        <option value="ingredient">ingredient</option>
                        <option value="manual">manual</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Extracted Text</label>
                <input type="text" id="mention_text" class="form-input" placeholder="The raw mention text">
            </div>
            <div class="form-group">
                <label class="form-label">Context Snippet <span class="param-type">Optional</span></label>
                <textarea id="mention_context" class="form-textarea" placeholder="Surrounding context or full sentence…" style="min-height:70px;"></textarea>
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="FuzzForge.closeModal('addMentionModal')">Cancel</button>
            <button class="btn-forge-primary" onclick="FuzzForge.saveMention()">
                <i class="bi bi-plus-lg"></i> Add Mention
            </button>
        </div>
    </div>
</div>

<!-- Add Fuzzy Link Modal -->
<div class="forge-modal-overlay" id="addLinkModal">
    <div class="forge-modal" style="max-width:560px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title"><i class="bi bi-shuffle"></i> Add Fuzzy Link</div>
            <button class="forge-modal-close" onclick="FuzzForge.closeModal('addLinkModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <div class="form-group">
                <label class="form-label">Target Candidate ID or Label</label>
                <input type="text" id="link_target_input" class="form-input" placeholder="Search candidate label…" oninput="FuzzForge.debounceTargetSearch()">
                <div id="linkTargetResults" class="kg-search-result-list" style="display:none; margin-top:8px;"></div>
            </div>
            <div class="form-group">
                <input type="hidden" id="link_target_id">
                <div id="linkTargetPreview" style="display:none; margin-bottom:10px;"></div>
                <label class="form-label">Relationship Type</label>
                <select id="link_rel_type" class="form-select">
                    <option value="may_refer_to">may refer to</option>
                    <option value="likely_variant_of">likely variant of</option>
                    <option value="contextually_related">contextually related to</option>
                    <option value="possible_alias_of">possible alias of</option>
                    <option value="probable_family_member">probable conceptual family member</option>
                    <option value="contradicts">contradicts</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Confidence <span class="param-type" id="linkConfLabel">50%</span></label>
                <input type="range" id="link_confidence" min="0" max="100" value="50" style="width:100%; accent-color:var(--cyan);" oninput="document.getElementById('linkConfLabel').textContent=this.value+'%'">
            </div>
            <div class="form-group">
                <label class="form-label">Note <span class="param-type">Optional</span></label>
                <input type="text" id="link_note" class="form-input" placeholder="Why this relationship exists…">
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-forge-secondary" onclick="FuzzForge.closeModal('addLinkModal')">Cancel</button>
            <button class="btn-forge-primary" onclick="FuzzForge.saveFuzzyLink()">
                <i class="bi bi-shuffle"></i> Add Link
            </button>
        </div>
    </div>
</div>

<!-- ── ENTITY FORM MODAL (Deep Dive) ── -->
<div class="ef-overlay" id="efOverlay" onclick="if(event.target===this)window.closeEntityModal()">
    <div class="ef-sheet">
        <div class="ef-header">
            <span class="ef-title" id="efTitle">Entity Details</span>
            <button class="forge-modal-close" onclick="window.closeEntityModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ef-body">
            <iframe id="efIframe" src="about:blank" allowfullscreen></iframe>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════ TOAST CONTAINER -->
<div class="forge-toast-container" id="toastContainer"></div>

<!-- ══════════════════════════════════════════ MAIN JS -->
<script>
// Expose Entity Modal globally for inline onclicks
window.openEntityModal = function(entityType, entityId) {
    const overlay = document.getElementById('efOverlay');
    const iframe  = document.getElementById('efIframe');
    const title   = document.getElementById('efTitle');
    
    // Map dependent tables to their valid root entity
    let resolvedType = entityType;
    if (['sketch_analysis', 'sketch_ingredients', 'sketch_lore_history'].includes(entityType)) {
        resolvedType = 'sketches';
    }
    
    title.textContent = `${resolvedType} #${entityId}`;
    iframe.src = `/entity_form.php?entity_type=${encodeURIComponent(resolvedType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
    overlay.classList.add('open');
};
window.closeEntityModal = function() {
    const overlay = document.getElementById('efOverlay');
    const iframe  = document.getElementById('efIframe');
    overlay.classList.remove('open');
    iframe.src = 'about:blank';
};

const FuzzForge = (() => {
    'use strict';

    const API = '/api/fuzz_forge_api.php';
    let _allCandidates = [];
    let _currentCandidate = null;
    let _isNew = false;
    let _currentAliases = [];
    let _currentKGLink = null;
    let _typeFilter = 'all';
    let _searchTimeout = null;
    let _kgSearchTimeout = null;
    let _targetSearchTimeout = null;
    let _currentMentions = [];
    let _currentFrames = [];
    let _currentLinks = [];
    let _currentReviews = [];
    let _selectedFrameIds = new Set(); // tracks selected frame card ids (frame.id)
    const _mentionLightboxes = {};  // per-mention-row PhotoSwipe instances

    const UI_STATE_KEY = 'fuzz_forge_ui_state';

    // State
    let _pages = { 'Pending': 1, 'Resolved': 1, 'Rejected': 1 };
    let _totalPages = { 'Pending': 1, 'Resolved': 1, 'Rejected': 1 };
    let _sorts = {
        'Pending': { col: 'mention_count', dir: 'desc' },
        'Resolved': { col: 'mention_count', dir: 'desc' },
        'Rejected': { col: 'mention_count', dir: 'desc' }
    };

    function _getUIState() {
        try { return JSON.parse(localStorage.getItem(UI_STATE_KEY)) || {}; } catch(e) { return {}; }
    }

    function _saveUIState(state) {
        try { localStorage.setItem(UI_STATE_KEY, JSON.stringify(state)); } catch(e) {}
    }

    function _restoreUIState() {
        const state = _getUIState();
        // Main Panels
        ['panelPending', 'panelResolved', 'panelRejected'].forEach(id => {
            if (state[id] !== undefined) {
                const el = document.getElementById(id);
                if (el) {
                    if (state[id]) el.classList.add('open');
                    else el.classList.remove('open');
                }
            }
        });
        // Left Panel Sections
        ['secIdentity', 'secAliases', 'secKGLink', 'secNotes'].forEach(id => {
            if (state[id] !== undefined) {
                const el = document.getElementById(id);
                const chevron = document.getElementById(id + 'Chevron');
                if (el) {
                    el.style.display = state[id] ? '' : 'none';
                    if (chevron) chevron.style.transform = state[id] ? '' : 'rotate(-90deg)';
                }
            }
        });
    }

    // ── API helper ──────────────────────────────────────────────
    async function api(action, data = {}) {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data })
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // ── Toast ────────────────────────────────────────────────────
    function toast(msg, type = 'info', duration = 3000) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        const icons = { success: '✓', error: '✕', info: '◆' };
        el.innerHTML = `<span>${icons[type] || '◆'}</span> ${msg}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        const dismiss = (e) => { e.classList.add('out'); setTimeout(() => e.remove(), 300); };
        setTimeout(() => dismiss(el), duration);
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // ── Init ─────────────────────────────────────────────────────
    async function init() {
        _restoreUIState();
        await loadAllPanels();
        bindEvents();
        
        // Handle deep linking to candidate from URL
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('candidate_id') || urlParams.get('id');
        if (id) {
            setTimeout(() => selectCandidate(parseInt(id)), 300);
        }
    }

    async function loadAllPanels() {
        const search = document.getElementById('searchInput').value.trim();
        const filter = _typeFilter;

        try {
            const [pRes, rRes, xRes] = await Promise.all([
                api('list_candidates', { tab: 'candidates', filter, search, sort: _sorts['Pending'].col, dir: _sorts['Pending'].dir, page: _pages['Pending'] }),
                api('list_candidates', { tab: 'resolved', filter, search, sort: _sorts['Resolved'].col, dir: _sorts['Resolved'].dir, page: _pages['Resolved'] }),
                api('list_candidates', { tab: 'rejected', filter, search, sort: _sorts['Rejected'].col, dir: _sorts['Rejected'].dir, page: _pages['Rejected'] })
            ]);

            if (pRes.ok) {
                renderTable('Pending', pRes.data.candidates);
                document.getElementById('statCandidates').textContent = pRes.data.total_count ?? '—';
                document.getElementById('statPending').textContent = pRes.data.pending_count ?? '—';
                document.getElementById('countPending').textContent = pRes.data.tab_count;
                updatePagination('Pending', pRes.data);
            }
            if (rRes.ok) {
                renderTable('Resolved', rRes.data.candidates);
                document.getElementById('countResolved').textContent = rRes.data.tab_count;
                updatePagination('Resolved', rRes.data);
            }
            if (xRes.ok) {
                renderTable('Rejected', xRes.data.candidates);
                document.getElementById('countRejected').textContent = xRes.data.tab_count;
                updatePagination('Rejected', xRes.data);
            }
            
        } catch (e) {
            toast('Failed to load tables', 'error');
        }
    }

    function updatePagination(type, data) {
        _pages[type] = data.current_page;
        _totalPages[type] = data.total_pages;
        const inp = document.getElementById(`pageInp${type}`);
        const tot = document.getElementById(`pageTotal${type}`);
        if (inp) inp.value = data.current_page;
        if (tot) tot.textContent = data.total_pages;
    }

    function changePage(type, delta) {
        let nPage = _pages[type] + delta;
        if (nPage < 1) nPage = 1;
        if (nPage > _totalPages[type]) nPage = _totalPages[type];
        if (nPage !== _pages[type]) {
            _pages[type] = nPage;
            loadAllPanels();
        }
    }

    function goToPage(type, val) {
        let nPage = parseInt(val) || 1;
        if (nPage < 1) nPage = 1;
        if (nPage > _totalPages[type]) nPage = _totalPages[type];
        if (nPage !== _pages[type]) {
            _pages[type] = nPage;
            loadAllPanels();
        } else {
            document.getElementById(`pageInp${type}`).value = nPage;
        }
    }

    function cycleSort(type, colKey) {
        const current = _sorts[type];
        if (current.col === colKey) {
            if (current.dir === 'asc') current.dir = 'desc';
            else if (current.dir === 'desc') { current.col = null; current.dir = null; }
        } else {
            current.col = colKey;
            current.dir = 'asc';
        }
        _pages[type] = 1; // Reset to page 1 on sort
        loadAllPanels();
    }

    function renderTable(type, candidates) {
        const thead = document.getElementById(`thead${type}`);
        const tbody = document.getElementById(`tbody${type}`);
        if (!thead || !tbody) return;

        // Render Header with sorting
        const cols = [
            { label: 'ID', key: 'id' },
            { label: 'Concept', key: 'label' },
            { label: 'Type', key: 'concept_type' },
            { label: 'Status', key: 'status' },
            { label: 'Mentions', key: 'mention_count' },
            type === 'Resolved' ? { label: 'KG Node', key: 'kg_node_id' } : { label: 'Conf', key: 'confidence' },
            { label: 'Action', key: null }
        ];

        thead.innerHTML = `<tr>` + cols.map(c => {
            if (!c.key) return `<th>${c.label}</th>`;
            const isSorted = _sorts[type].col === c.key;
            const sortIcon = isSorted ? (_sorts[type].dir === 'asc' ? '▲' : '▼') : '▼';
            const iconCls = isSorted ? 'sort-icon active' : 'sort-icon';
            return `<th class="sortable" onclick="FuzzForge.cycleSort('${type}', '${c.key}')">${c.label} <span class="${iconCls}">${sortIcon}</span></th>`;
        }).join('') + `</tr>`;

        // Render Body
        if (candidates.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; font-size:0.7rem;">No ${type} concepts found</td></tr>`;
            return;
        }

        tbody.innerHTML = candidates.map(c => {
            const stageClass = {
                extracted: 'badge-extracted', grouped: 'badge-stage', reviewed: 'badge-stage',
                promoted: 'badge-promoted', canonized: 'badge-canon',
                rejected: 'badge-rejected', deferred: 'badge-deferred'
            }[c.status] || 'badge-type';

            const typeIcon = {
                character: '🦸', location: '🗺️', faction: '⚔️', artifact: '🏺',
                event: '⚡', concept: '💡', relationship: '🔗', other: '◆'
            }[c.concept_type] || '◆';

            const kgBadge = c.kg_node_id ? `<span class="gen-badge badge-promoted" style="cursor:pointer;" onclick="window.openEntityModal('kg_nodes', ${c.kg_node_id})"><i class="bi bi-box-arrow-up-right"></i> KG#${c.kg_node_id}</span>` : '<span style="color:var(--text-dim);">—</span>';
            const confPct = c.confidence ? Math.round(c.confidence) : 0;

            return `
            <tr>
                <td class="cell-mono">#${c.id}</td>
                <td class="cell-text">${typeIcon} &nbsp;${escHtml(c.label)}</td>
                <td><span class="gen-badge badge-type" style="border-color:transparent;">${escHtml(c.concept_type || '—')}</span></td>
                <td><span class="gen-badge ${stageClass}">${escHtml(c.status)}</span></td>
                <td class="cell-mono">${c.mention_count} mentions</td>
                ${type === 'Resolved' 
                    ? `<td>${kgBadge}</td>`
                    : `<td class="cell-mono" style="color:var(--cyan);">${confPct}%</td>`
                }
                <td class="cell-actions">
                    <button class="btn-entity" onclick="FuzzForge.selectCandidate(${c.id})" title="Deep Dive Dossier">
                        <i class="bi bi-layers"></i>
                    </button>
                    <!-- ── CHANGE 2: landing deep link ── -->
                    <a href="/fuzz_forge_landing.php?id=${c.id}" target="_blank" class="btn-entity" title="Open Candidate Landing">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </td>
            </tr>`;
        }).join('');
    }

    function togglePanel(headEl) {
        const panel = headEl.closest('.data-panel');
        const isOpen = panel.classList.toggle('open');
        if (panel.id) {
            const state = _getUIState();
            state[panel.id] = isOpen;
            _saveUIState(state);
        }
    }

    // ── Select Candidate (Deep Dive Modal) ───────────────────────
    async function selectCandidate(id) {
        _isNew = false;
        const r = await api('get_candidate', { id });
        if (!r.ok) { toast('Failed to load candidate', 'error'); return; }

        _currentCandidate = r.data.candidate;
        _currentAliases = r.data.aliases || [];
        _currentKGLink = r.data.kg_node || null;
        _currentMentions = r.data.mentions || [];
        _currentFrames = r.data.frames || [];
        _currentLinks = r.data.links || [];
        _currentReviews = r.data.reviews || [];

        populateForm();
        renderAliases();
        renderKGLink();
        renderMentions();
        renderFrames();
        renderLinks();
        renderReviews();
        
        document.getElementById('dossierModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function populateForm() {
        const c = _currentCandidate;
        document.getElementById('wsTitle').textContent = c.label;
        document.getElementById('cand_label').value = c.label || '';
        document.getElementById('cand_concept_type').value = c.concept_type || '';
        document.getElementById('cand_status').value = c.status || 'extracted';
        document.getElementById('cand_confidence').value = c.confidence || 50;
        document.getElementById('confLabel').textContent = (c.confidence || 50) + '%';
        document.getElementById('cand_notes').value = c.notes || '';

        const stageClass = {
            extracted: 'badge-extracted', grouped: 'badge-stage', reviewed: 'badge-stage',
            promoted: 'badge-promoted', canonized: 'badge-canon',
            rejected: 'badge-rejected', deferred: 'badge-deferred'
        }[c.status] || 'badge-type';

        document.getElementById('wsMeta').innerHTML = `
            <span class="gen-badge ${stageClass}">${escHtml(c.status)}</span>
            ${c.concept_type ? `<span class="gen-badge badge-type">${escHtml(c.concept_type)}</span>` : ''}
            <span class="gen-badge badge-type">ID: ${c.id}</span>
            <span class="gen-badge badge-count">${_currentMentions.length} mentions</span>
            ${c.kg_node_id ? `<span class="gen-badge badge-promoted">KG#${c.kg_node_id}</span>` : ''}
        `;
        document.getElementById('mentionCount').textContent = _currentMentions.length || '';
    }

    // ── New Candidate ────────────────────────────────────────────
    function newCandidate() {
        _isNew = true;
        _currentCandidate = null;
        _currentAliases = [];
        _currentKGLink = null;
        _currentMentions = [];
        _currentFrames = [];
        _currentLinks = [];
        _currentReviews = [];

        document.getElementById('wsTitle').textContent = 'New Concept Candidate';
        document.getElementById('wsMeta').innerHTML = '';
        document.getElementById('cand_label').value = '';
        document.getElementById('cand_concept_type').value = '';
        document.getElementById('cand_status').value = 'extracted';
        document.getElementById('cand_confidence').value = 50;
        document.getElementById('confLabel').textContent = '50%';
        document.getElementById('cand_notes').value = '';
        document.getElementById('mentionCount').textContent = '';
        
        renderAliases();
        renderKGLink();
        renderMentions();
        renderFrames();
        renderLinks();
        renderReviews();
        
        document.getElementById('dossierModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDossier() {
        document.getElementById('dossierModal').classList.remove('open');
        document.body.style.overflow = '';
        _currentCandidate = null;
    }

    // ── Save Candidate ───────────────────────────────────────────
    async function saveCandidate() {
        const label = document.getElementById('cand_label').value.trim();
        if (!label) { toast('Label is required', 'error'); return; }

        const payload = {
            id: _isNew ? 0 : _currentCandidate?.id,
            label,
            concept_type: document.getElementById('cand_concept_type').value,
            status: document.getElementById('cand_status').value,
            confidence: parseInt(document.getElementById('cand_confidence').value),
            notes: document.getElementById('cand_notes').value.trim(),
            aliases: _currentAliases,
            kg_node_id: _currentKGLink ? _currentKGLink.id : null
        };

        const r = await api('save_candidate', payload);
        if (r.ok) {
            toast('Concept saved', 'success');
            await loadAllPanels();
            selectCandidate(r.data.id);
        } else {
            toast(r.error || 'Save failed', 'error');
        }
    }

    // ── Delete Candidate ─────────────────────────────────────────
    async function deleteCandidate() {
        if (!_currentCandidate || !confirm('Delete this concept candidate? All mentions and links will also be removed.')) return;
        const r = await api('delete_candidate', { id: _currentCandidate.id });
        if (r.ok) {
            toast('Candidate deleted', 'success');
            closeDossier();
            loadAllPanels();
        } else {
            toast('Delete failed', 'error');
        }
    }

    // ── Aliases ──────────────────────────────────────────────────
    function renderAliases() {
        const container = document.getElementById('aliasList');
        if (_currentAliases.length === 0) {
            container.innerHTML = `<span style="font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); font-style:italic;">No aliases yet</span>`;
            return;
        }
        container.innerHTML = _currentAliases.map((a, i) => `
            <span class="alias-tag">
                ${escHtml(a)}
                <button class="remove-alias" onclick="FuzzForge.removeAlias(${i})" title="Remove"><i class="bi bi-x"></i></button>
            </span>
        `).join('');
    }

    function addAlias() {
        const input = document.getElementById('newAliasInput');
        const val = input.value.trim();
        if (!val) return;
        if (_currentAliases.includes(val)) { toast('Alias already added', 'info'); return; }
        _currentAliases.push(val);
        input.value = '';
        renderAliases();
    }

    function removeAlias(idx) {
        _currentAliases.splice(idx, 1);
        renderAliases();
    }

    // ── KG Node Link ─────────────────────────────────────────────
    function renderKGLink() {
        const statusEl = document.getElementById('kgLinkStatus');
        const linkedEl = document.getElementById('kgLinkedNode');
        const searchGroup = document.getElementById('kgSearchGroup');

        if (_currentKGLink) {
            statusEl.innerHTML = `<div class="resolve-pill linked"><i class="bi bi-link-45deg"></i> Linked to KG Node</div>`;
            linkedEl.style.display = 'block';
            document.getElementById('kgLinkedBadge').innerHTML = `
                <i class="bi bi-diagram-3-fill"></i> #${_currentKGLink.id}: ${escHtml(_currentKGLink.name)}
                ${_currentKGLink.node_type ? `<span style="color:var(--text-dim); margin-left:4px; font-size:0.65rem;">[${_currentKGLink.node_type}]</span>` : ''}
            `;
            searchGroup.style.display = 'none';
        } else {
            statusEl.innerHTML = `<div class="resolve-pill unresolved"><i class="bi bi-question-circle"></i> Unresolved</div>`;
            linkedEl.style.display = 'none';
            searchGroup.style.display = 'block';
        }
    }

    function debounceKGSearch() {
        clearTimeout(_kgSearchTimeout);
        _kgSearchTimeout = setTimeout(runKGSearch, 300);
    }

    async function runKGSearch() {
        const q = document.getElementById('kgSearchInput').value.trim();
        const resultsEl = document.getElementById('kgSearchResults');
        if (q.length < 2) { resultsEl.style.display = 'none'; return; }
        const r = await api('search_kg_nodes', { q });
        if (r.ok && r.data.length > 0) {
            resultsEl.style.display = 'block';
            resultsEl.innerHTML = r.data.map(n => `
                <div class="kg-result-item" onclick="FuzzForge.linkKGNode(${n.id}, '${escHtml(n.name).replace(/'/g,"\\'")}', '${escHtml(n.node_type || '')}')">
                    <span>#${n.id}</span>
                    <span style="flex:1;">${escHtml(n.name)}</span>
                    <span class="kg-type">[${escHtml(n.node_type || 'note')}]</span>
                </div>
            `).join('');
        } else {
            resultsEl.style.display = 'block';
            resultsEl.innerHTML = `<div class="kg-result-item" style="color:var(--text-dim); cursor:default;">No matches found</div>`;
        }
    }

    function linkKGNode(id, name, type) {
        _currentKGLink = { id, name, node_type: type };
        document.getElementById('kgSearchInput').value = '';
        document.getElementById('kgSearchResults').style.display = 'none';
        renderKGLink();
        toast(`Linked to KG: ${name}`, 'success');
    }

    function unlinkKGNode() {
        _currentKGLink = null;
        renderKGLink();
        toast('KG link removed', 'info');
    }

    // ── Mentions ─────────────────────────────────────────────────
    function renderMentions() {
        const list = document.getElementById('mentionList');
        document.getElementById('mentionCount').textContent = _currentMentions.length || '';

        if (!_currentMentions.length) {
            list.innerHTML = `<div style="text-align:center; color:var(--text-dim); padding:40px; font-family:var(--mono); text-transform:uppercase; letter-spacing:2px;">No mentions recorded</div>`;
            return;
        }

        // Build per-entity frame lookup so we can show inline strips
        const frameIndex = buildFrameIndex(_currentFrames);

        list.innerHTML = _currentMentions.map((m, idx) => {
            const typeClass = 'mtype-' + (m.mention_type || '').replace(/ /g, '_');
            const sourceEntityName = m.source_entity_name || m.sketches_name || m.sketch_name || m.source_name || m.source_label || m.name || m.entity_name || '';

            let btnEntity = '';
            if (m.source_row_id && ['sketches', 'sketch_analysis', 'sketch_lore_history', 'sketch_ingredients', 'kg_nodes', 'characters', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles'].includes(m.source_table)) {
                btnEntity = `
                    <button class="btn-entity" onclick="window.openEntityModal('${escHtml(m.source_table)}', ${m.source_row_id})" title="Deep Dive Source Entity">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </button>`;
            }

            const rowFrames = (m.source_row_id && frameIndex[m.source_row_id]) ? frameIndex[m.source_row_id] : [];
            const framesStrip = rowFrames.length > 0 ? buildMentionFramesStrip(rowFrames, idx) : '';

            // Delete button still present — right-aligned in the action col
            const btnDelete = `
                <button class="btn-icon-sm danger" style="width:26px; height:26px; font-size:11px; margin-top:2px;" onclick="FuzzForge.deleteMention(${m.id})" title="Remove Mention">
                    <i class="bi bi-x-lg"></i>
                </button>`;

            return `
            <div class="mention-block">
                <div class="mention-row">
                    <div class="mention-col">
                        <div class="cell-meta-row" style="justify-content:flex-start;">
                            ${btnEntity}
                            ${btnDelete}
                            <span class="cell-mono" style="font-size:0.68rem;">${m.source_row_id ? '#' + m.source_row_id : '—'}</span>
                        </div>
                    </div>
                    <div class="mention-col">
                        <div class="cell-text-stack">
                            ${sourceEntityName ? `<div class="cell-text-name" title="${escHtml(sourceEntityName)}">${escHtml(sourceEntityName)}</div>` : ''}
                            <div class="cell-text-main" title="${escHtml(m.extracted_text)}">${escHtml(m.extracted_text)}</div>
                            <div class="cell-text-ctx" title="${escHtml(m.context_snippet)}">${escHtml(m.context_snippet || '—')}</div>
                        </div>
                    </div>
                    <div class="mention-col">
                        <div class="cell-meta-stack">
                            <div><span class="source-label">${escHtml(m.source_table)}</span></div>
                            <div><span class="gen-badge badge-type ${typeClass}" style="border-color:currentColor;">${escHtml(m.mention_type || '—')}</span></div>
                        </div>
                    </div>
                </div>
                ${framesStrip}
            </div>`;
        }).join('');

        // Init a PhotoSwipe lightbox scoped to each mention's strip
        if (typeof PhotoSwipeLightbox !== 'undefined') {
            _currentMentions.forEach((m, idx) => {
                const stripId = `dossier-strip-${idx}`;
                const stripEl = document.getElementById(stripId);
                if (!stripEl) return;
                if (_mentionLightboxes[idx]) { _mentionLightboxes[idx].destroy(); }
                const lb = new PhotoSwipeLightbox({
                    gallery: `#${stripId}`,
                    children: 'a.f-link',
                    pswpModule: PhotoSwipe
                });
                lb.init();
                _mentionLightboxes[idx] = lb;
            });
        }
    }
    
    
       // ── Frame index helper ───────────────────────────────────────
    function buildFrameIndex(frames) {
        const byEntityId = {};
        frames.forEach(f => {
            const key = f.entity_id;
            if (key == null) return;
            if (!byEntityId[key]) byEntityId[key] = [];
            byEntityId[key].push(f);
        });
        return byEntityId;
    }

    function buildMentionFramesStrip(rowFrames, idx) {
        const stripId = `dossier-strip-${idx}`;
        const count = rowFrames.length;
        const cards = rowFrames.map(f => {
            const btnEntityType = f.entity_type || 'sketches';
            const btnEntityId   = f.entity_id   || f.id;
            return `
            <div class="f-card">
                <a href="${escHtml(f.filename)}" class="f-link" target="_blank" data-pswp-width="1024" data-pswp-height="1024">
                    <img src="${escHtml(f.filename)}" loading="lazy" onload="this.parentNode.dataset.pswpWidth = this.naturalWidth; this.parentNode.dataset.pswpHeight = this.naturalHeight;">
                </a>
                <div class="f-view-btn" onclick="window.openEntityModal('${escHtml(btnEntityType)}', ${btnEntityId})" title="Open ${escHtml(btnEntityType)} Entity">
                    <i class="bi bi-box-arrow-up-right"></i>
                </div>
                <div class="f-label">
                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">#${f.id}</span>
                </div>
            </div>`;
        }).join('');
        return `
        <div class="mention-frames-strip">
            <div class="mention-frames-label">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                ${count} frame${count !== 1 ? 's' : ''} from this source
            </div>
            <div class="mention-frames-grid" id="${stripId}">
                ${cards}
            </div>
        </div>`;
    }
    

// ── Frames ───────────────────────────────────────────────────
    let _lightboxFramesTab = null;

    function initLightbox() {
        if (typeof PhotoSwipeLightbox === 'undefined') return;
        if (_lightboxFramesTab) { _lightboxFramesTab.destroy(); }
        _lightboxFramesTab = new PhotoSwipeLightbox({
            gallery: '#framesGridContainer',
            children: 'a.f-link', 
            pswpModule: PhotoSwipe
        });
        _lightboxFramesTab.init();
    }

    function renderFrames() {
        const container = document.getElementById('framesGridContainer');
        const countEl = document.getElementById('frameCount');
        countEl.textContent = _currentFrames.length || '';
        _selectedFrameIds.clear();
        _updateFrameRemoveBtn();

        if (!_currentFrames.length) {
            container.style.display = 'block';
            container.innerHTML = `<div style="color:var(--text-dim); font-family:var(--mono); font-size:0.78rem; text-align:center; padding:30px; text-transform:uppercase; letter-spacing:2px; grid-column: 1 / -1;">No frames found for these mentions.</div>`;
            return;
        }
        
        container.style.display = 'grid';
        container.innerHTML = _currentFrames.map(f => {
            const btnEntityType = f.entity_type || 'sketches';
            const btnEntityId   = f.entity_id   || f.id;
            return `
            <div class="f-card" data-fid="${f.id}" data-entity-type="${escHtml(btnEntityType)}" data-entity-id="${btnEntityId}">
                <a href="${escHtml(f.filename)}" class="f-link" target="_blank" data-pswp-width="1024" data-pswp-height="1024">
                    <img src="${escHtml(f.filename)}" loading="lazy" onload="this.parentNode.dataset.pswpWidth = this.naturalWidth; this.parentNode.dataset.pswpHeight = this.naturalHeight;">
                </a>
                <div class="f-view-btn" onclick="window.openEntityModal('${escHtml(btnEntityType)}', ${btnEntityId})" title="Open ${escHtml(btnEntityType)} Entity">
                    <i class="bi bi-box-arrow-up-right"></i>
                </div>
                <div class="f-label" onclick="FuzzForge.toggleFrameCard(${f.id}, this.closest('.f-card'))">
                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">#${f.id}</span>
                    <div class="f-select-trigger"></div>
                </div>
            </div>
        `}).join('');

        initLightbox();
    }

    function toggleFrameCard(fid, card) {
        if (_selectedFrameIds.has(fid)) {
            _selectedFrameIds.delete(fid);
            card.classList.remove('selected');
        } else {
            _selectedFrameIds.add(fid);
            card.classList.add('selected');
        }
        _updateFrameRemoveBtn();
    }

    function toggleAllFrames(select) {
        _selectedFrameIds.clear();
        document.querySelectorAll('#framesGridContainer .f-card').forEach(card => {
            if (select) {
                _selectedFrameIds.add(parseInt(card.dataset.fid));
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
        _updateFrameRemoveBtn();
    }

    function _updateFrameRemoveBtn() {
        const btn = document.getElementById('btnRemoveFrames');
        if (btn) btn.style.display = _selectedFrameIds.size > 0 ? 'inline-flex' : 'none';
    }

    async function removeSelectedFrameEntities() {
        if (!_currentCandidate || _selectedFrameIds.size === 0) return;
        if (!confirm(`Remove all mentions for the ${_selectedFrameIds.size} selected frame(s)? All frames from the same source entities will also disappear from this grid.`)) return;

        const entities = new Map();
        document.querySelectorAll('#framesGridContainer .f-card.selected').forEach(card => {
            const key = `${card.dataset.entityType}:${card.dataset.entityId}`;
            if (!entities.has(key)) {
                entities.set(key, {
                    source_table:  card.dataset.entityType,
                    source_row_id: parseInt(card.dataset.entityId)
                });
            }
        });

        let anyFailed = false;
        for (const ent of entities.values()) {
            try {
                const r = await api('delete_mentions_by_entity', {
                    candidate_id:  _currentCandidate.id,
                    source_table:  ent.source_table,
                    source_row_id: ent.source_row_id
                });
                if (!r.ok) { toast(`Failed to remove ${ent.source_table}#${ent.source_row_id}: ${r.error}`, 'error'); anyFailed = true; }
            } catch (e) {
                toast(`Error: ${e.message}`, 'error'); anyFailed = true;
            }
        }

        if (!anyFailed) toast(`Removed mentions for ${entities.size} entit${entities.size === 1 ? 'y' : 'ies'}`, 'success');
        selectCandidate(_currentCandidate.id);
    }

    async function saveMention() {
        if (!_currentCandidate && !_isNew) { toast('Save the candidate first', 'error'); return; }
        if (_isNew) { toast('Save the candidate first, then add mentions', 'info'); closeModal('addMentionModal'); return; }

        const data = {
            candidate_id: _currentCandidate.id,
            source_table: document.getElementById('mention_source_table').value,
            source_row_id: document.getElementById('mention_source_id').value || null,
            source_field: document.getElementById('mention_source_field').value.trim() || null,
            mention_type: document.getElementById('mention_type').value,
            extracted_text: document.getElementById('mention_text').value.trim(),
            context_snippet: document.getElementById('mention_context').value.trim() || null
        };
        if (!data.extracted_text) { toast('Extracted text required', 'error'); return; }

        const r = await api('add_mention', data);
        if (r.ok) {
            toast('Mention added', 'success');
            closeModal('addMentionModal');
            selectCandidate(_currentCandidate.id);
        } else {
            toast(r.error || 'Failed', 'error');
        }
    }

    async function deleteMention(id) {
        if (!confirm('Remove this mention?')) return;
        const r = await api('delete_mention', { id });
        if (r.ok) {
            toast('Mention removed', 'success');
            selectCandidate(_currentCandidate.id);
        }
    }

    // ── Fuzzy Links ──────────────────────────────────────────────
    function renderLinks() {
        const container = document.getElementById('linksContainer');
        if (!_currentLinks.length) {
            container.innerHTML = `<div style="color:var(--text-dim); font-family:var(--mono); font-size:0.78rem; text-align:center; padding:30px; text-transform:uppercase; letter-spacing:2px;">No fuzzy links recorded yet.</div>`;
            return;
        }
        const relColors = {
            may_refer_to: 'var(--orange)', likely_variant_of: 'var(--purple)',
            contextually_related: 'var(--cyan)', possible_alias_of: 'var(--cyan)',
            probable_family_member: 'var(--green)', contradicts: 'var(--red)'
        };
        container.innerHTML = _currentLinks.map(l => {
            const col = relColors[l.relationship_type] || 'var(--text-dim)';
            const conf = l.confidence ? Math.round(l.confidence) : 0;
            return `
            <div style="display:flex; align-items:center; gap:10px; padding:12px 14px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:8px;">
                <div style="flex:1;">
                    <div style="font-family:var(--mono); font-size:0.72rem; color:${col}; margin-bottom:4px; text-transform:uppercase; letter-spacing:1px;">${escHtml(l.relationship_type?.replace(/_/g, ' '))}</div>
                    <div style="font-weight:600; font-family:var(--head); font-size:1.3rem; letter-spacing:1px; color:var(--text-bright); line-height:1.1;">${escHtml(l.target_label || 'Candidate #' + l.target_candidate_id)}</div>
                    ${l.note ? `<div style="font-size:0.8rem; color:var(--text-dim); margin-top:6px;">${escHtml(l.note)}</div>` : ''}
                </div>
                <div style="font-family:var(--head); font-size:1.4rem; color:var(--cyan); min-width:40px; text-align:right;">${conf}%</div>
                <button class="btn-icon-sm danger" style="width:28px; height:28px; font-size:12px; margin-left:8px;" onclick="FuzzForge.deleteLink(${l.id})">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>`;
        }).join('');
    }

    async function saveFuzzyLink() {
        if (!_currentCandidate) { toast('Save the candidate first', 'error'); return; }
        const targetId = document.getElementById('link_target_id').value;
        if (!targetId) { toast('Select a target candidate', 'error'); return; }

        const data = {
            candidate_id: _currentCandidate.id,
            target_candidate_id: parseInt(targetId),
            relationship_type: document.getElementById('link_rel_type').value,
            confidence: parseInt(document.getElementById('link_confidence').value),
            note: document.getElementById('link_note').value.trim() || null
        };
        const r = await api('add_link', data);
        if (r.ok) {
            toast('Link added', 'success');
            closeModal('addLinkModal');
            selectCandidate(_currentCandidate.id);
        } else {
            toast(r.error || 'Failed', 'error');
        }
    }

    async function deleteLink(id) {
        if (!confirm('Remove this link?')) return;
        const r = await api('delete_link', { id });
        if (r.ok) { toast('Link removed', 'success'); selectCandidate(_currentCandidate.id); }
    }

    // ── Reviews ──────────────────────────────────────────────────
    function renderReviews() {
        const container = document.getElementById('reviewTimeline');
        if (!_currentReviews.length) {
            container.innerHTML = `<div style="color:var(--text-dim); font-family:var(--mono); font-size:0.78rem; text-align:center; padding:30px; text-transform:uppercase; letter-spacing:2px;">No review events yet.</div>`;
            return;
        }
        const dotMap = { confirmed: 'confirmed', rejected: 'rejected', promoted: 'promoted', deferred: 'deferred' };
        const iconMap = { confirmed: 'bi-check-lg', rejected: 'bi-x-lg', promoted: 'bi-arrow-up-circle', deferred: 'bi-clock', split: 'bi-scissors', linked: 'bi-link-45deg', unresolved: 'bi-question-circle' };
        container.innerHTML = _currentReviews.map(rv => `
            <div class="review-event">
                <div class="review-dot ${dotMap[rv.decision] || ''}">
                    <i class="bi ${iconMap[rv.decision] || 'bi-dot'}"></i>
                </div>
                <div class="review-body">
                    <div class="review-action">${escHtml(rv.decision)}</div>
                    ${rv.note ? `<div class="review-note">${escHtml(rv.note)}</div>` : ''}
                    <div class="review-time">${escHtml(rv.created_at)}</div>
                </div>
            </div>
        `).join('');
    }

    async function reviewAction(decision) {
        if (!_currentCandidate) { toast('Select a candidate first', 'error'); return; }

        let note = '';
        if (['promoted', 'rejected'].includes(decision)) {
            note = prompt(`${decision.toUpperCase()} — add a note (optional):`) ?? '';
            if (note === null) return;
        }

        const r = await api('add_review', {
            candidate_id: _currentCandidate.id,
            decision,
            note: note.trim() || null
        });
        if (r.ok) {
            const statusMap = { confirmed: 'reviewed', promoted: 'promoted', rejected: 'rejected', deferred: 'deferred' };
            if (statusMap[decision]) {
                document.getElementById('cand_status').value = statusMap[decision];
            }
            toast(`Decision recorded: ${decision}`, 'success');
            selectCandidate(_currentCandidate.id);
            loadAllPanels();
        } else {
            toast(r.error || 'Failed', 'error');
        }
    }

    // ── Semantic Search ──────────────────────────────────────────
    async function runSemanticSearch() {
        if (!_currentCandidate) { toast('Select a candidate first', 'error'); return; }
        document.querySelectorAll('.rp-tab').forEach(b => b.classList.remove('active'));
        document.querySelector('.rp-tab[data-view="semantic"]').classList.add('active');
        activateRPView('semantic');

        const container = document.getElementById('semanticMatchesContainer');
        container.innerHTML = `<div style="text-align:center; padding:30px; font-family:var(--mono); color:var(--text-dim); text-transform:uppercase; letter-spacing:2px;"><div class="spinner"></div> Querying Chroma…</div>`;

        const r = await api('semantic_search', { candidate_id: _currentCandidate.id });
        if (!r.ok) {
            container.innerHTML = `<div style="text-align:center; padding:30px; font-family:var(--mono); color:var(--red);">${escHtml(r.error || 'Search failed')}</div>`;
            return;
        }
        if (!r.data.matches || r.data.matches.length === 0) {
            container.innerHTML = `<div style="text-align:center; padding:30px; font-family:var(--mono); color:var(--text-dim); text-transform:uppercase; letter-spacing:2px;">No semantic matches found in Chroma.</div>`;
            return;
        }
        container.innerHTML = r.data.matches.map(m => {
            const score = m.score ? (m.score * 100).toFixed(1) : '—';
            const barW = m.score ? Math.round(m.score * 100) : 0;
            return `
            <div style="padding:14px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:8px;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:8px;">
                    <div>
                        <div style="font-weight:600; font-family:var(--head); font-size:1.3rem; letter-spacing:1px; color:var(--text-bright); margin-bottom:2px; line-height:1.1;">${escHtml(m.label || m.id)}</div>
                        <div style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px;">${escHtml(m.source_table || '')} ${m.source_id ? '#'+m.source_id : ''}</div>
                    </div>
                    <div style="font-family:var(--head); font-size:1.4rem; color:var(--cyan); flex-shrink:0;">${score}%</div>
                </div>
                <div class="confidence-bar-wrap">
                    <div class="confidence-bar-bg"><div class="confidence-bar-fill" style="width:${barW}%;"></div></div>
                </div>
                ${m.snippet ? `<div style="font-size:0.8rem; color:var(--text-dim); margin-top:8px; font-style:italic;">"${escHtml(m.snippet)}"</div>` : ''}
                <div style="margin-top:10px; display:flex; gap:6px;">
                    <button class="btn-forge-secondary" style="font-size:0.68rem; padding:4px 10px;" onclick="FuzzForge.importSemanticMatch(${m.source_id || 0}, '${escHtml(m.source_table||'')}', '${escHtml((m.label||'').replace(/'/g,"\\'")||'')}')">
                        <i class="bi bi-plus-circle"></i> Add as Mention
                    </button>
                    ${m.source_id ? `
                    <button class="btn-icon-sm" style="height:26px; width:26px; font-size:11px;" onclick="window.openEntityModal('${escHtml(m.source_table||'')}', ${m.source_id})" title="Deep Dive Entity">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </button>
                    ` : ''}
                </div>
            </div>`;
        }).join('');
    }

    async function importSemanticMatch(sourceId, sourceTable, label) {
        if (!_currentCandidate) return;
        const r = await api('add_mention', {
            candidate_id: _currentCandidate.id,
            source_table: sourceTable,
            source_row_id: sourceId || null,
            mention_type: 'sketch_name',
            extracted_text: label,
            context_snippet: 'Imported from Chroma semantic match'
        });
        if (r.ok) { toast('Mention added from semantic match', 'success'); selectCandidate(_currentCandidate.id); }
        else toast(r.error || 'Failed', 'error');
    }

    // ── Extraction Job ───────────────────────────────────────────
    const EXT_JOB_KEY = 'fuzzforge_ext_job';
    const ING_JOB_KEY = 'fuzzforge_ingest_job';
    let _extPollTimer = null;
    let _extCurrentJobId = null;
    let _ingestPollTimer = null;
    let _ingestCurrentJobId = null;

    function _extAppendLine(text, cls = '') {
        const logBox = document.getElementById('extractionStreamBox');
        const line = document.createElement('div');
        line.className = cls;
        line.textContent = text;
        logBox.appendChild(line);
        logBox.scrollTop = logBox.scrollHeight;
    }

    function _extShowLog() {
        const sec = document.getElementById('extractionJobLog');
        if (sec) sec.style.display = 'block';
    }

    function _extSetProgress(stage, pct, color) {
        const bar = document.getElementById('extractProgressBar');
        const stageEl = document.getElementById('extractProgressStage');
        const pctEl = document.getElementById('extractProgressPct');
        if (bar) { bar.style.width = pct + '%'; if (color) bar.style.background = color; }
        if (stageEl) stageEl.textContent = stage;
        if (pctEl) pctEl.textContent = pct + '%';
    }

    function _extActivateJobPanel(jobId) {
        _extCurrentJobId = jobId;
        document.getElementById('extractJobId').textContent = jobId;
        document.getElementById('extractJobPanel').style.display = 'block';
        document.getElementById('extractApplyRow').style.display = 'none';
        document.getElementById('btnRunExtraction').style.display = 'none';
        document.getElementById('extractPhase1').style.display = 'none';
    }

    function _extMarkComplete() {
        _extSetProgress('Complete', 100, 'var(--green)');
        const applyRow = document.getElementById('extractApplyRow');
        if (applyRow) applyRow.style.display = 'flex';
        document.getElementById('btnRunExtraction').style.display = 'none';
    }

    function _extSaveJob(jobId, maxCandidates) {
        try { localStorage.setItem(EXT_JOB_KEY, JSON.stringify({ jobId, maxCandidates, ts: Date.now() })); } catch(e) {}
    }

    function _extClearSavedJob() {
        try { 
            localStorage.removeItem(EXT_JOB_KEY); 
            localStorage.removeItem(ING_JOB_KEY); 
        } catch(e) {}
        _extCurrentJobId = null;
        _ingestCurrentJobId = null;
    }

    function _extLoadSavedJob() {
        try {
            const raw = localStorage.getItem(EXT_JOB_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch(e) { return null; }
    }
    
    function _extLoadSavedIngestJob() {
        try {
            const raw = localStorage.getItem(ING_JOB_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch(e) { return null; }
    }

    async function _extPollJob(jobId) {
        clearTimeout(_extPollTimer);
        let lastMsg = '';
        const poll = async () => {
            if (_extCurrentJobId !== jobId) return;
            try {
                const r = await api('get_extraction_job_status', { job_id: jobId });
                if (!r.ok) { _extAppendLine('Status error: ' + (r.error || '?'), 'sse-line-err'); return; }
                const d = r.data;
                const status = d.status || '';
                const progress = d.progress || {};
                const msg = progress.message || '';
                const processed = progress.processed || 0;
                const total = d.total_items || progress.total || 0;
                const pct = total > 0 ? Math.round((processed / total) * 100) : (status === 'running' ? 30 : 0);

                if (msg && msg !== lastMsg) {
                    _extAppendLine(msg, status === 'error' ? 'sse-line-err' : 'sse-line-info');
                    lastMsg = msg;
                }

                if (status === 'success') {
                    _extAppendLine('Clustering complete! ' + (d.clusters ? d.clusters.length + ' clusters returned.' : ''), 'sse-line-ok');
                    _extMarkComplete();
                    return;
                }
                if (status === 'error' || status === 'failed') {
                    _extAppendLine('Job failed: ' + (d.error || 'Unknown'), 'sse-line-err');
                    _extSetProgress('Failed', pct, 'var(--red)');
                    return;
                }

                _extSetProgress(progress.stage || status, pct, null);
                _extPollTimer = setTimeout(poll, 1800);
            } catch(e) {
                _extAppendLine('Poll error: ' + e.message, 'sse-line-err');
                _extPollTimer = setTimeout(poll, 4000);
            }
        };
        poll();
    }
    
    async function _ingestPollJob(jobId) {
        clearTimeout(_ingestPollTimer);
        let lastMsg = '';
        const poll = async () => {
            if (_ingestCurrentJobId !== jobId) return;
            try {
                const r = await api('get_ingest_job_status', { job_id: jobId });
                if (!r.ok) { _extAppendLine('Ingest status error: ' + (r.error || '?'), 'sse-line-err'); return; }
                const d = r.data;
                const status = d.status || '';
                const progress = d.progress || {};
                const msg = progress.message || '';
                const processed = progress.processed || 0;
                const total = progress.total || 0;
                const pct = total > 0 ? Math.round((processed / total) * 100) : 0;

                if (msg && msg !== lastMsg) {
                    _extAppendLine(msg, status === 'error' ? 'sse-line-err' : 'sse-line-info');
                    lastMsg = msg;
                }

                _extSetProgress('Ingesting...', pct, 'var(--cyan)');

                if (status === 'success') {
                    _extAppendLine('Ingestion complete!', 'sse-line-ok');
                    _extSetProgress('Complete', 100, 'var(--green)');
                    _extClearSavedJob();
                    toast('Clusters applied successfully!', 'success');
                    
                    document.getElementById('extractApplyRow').style.display = 'none';
                    document.getElementById('extractJobPanel').style.display = 'none';
                    document.getElementById('extractPhase1').style.display = 'block';
                    document.getElementById('btnRunExtraction').style.display = '';
                    document.getElementById('btnRunExtraction').disabled = false;
                    document.getElementById('btnRunExtraction').innerHTML = '<i class="bi bi-cpu"></i> Extract &amp; Submit';
                    
                    _pages = { Pending: 1, Resolved: 1, Rejected: 1 };
                    await loadAllPanels();
                    return;
                }
                if (status === 'error' || status === 'failed') {
                    _extAppendLine('Ingestion failed: ' + (d.error || 'Unknown'), 'sse-line-err');
                    _extSetProgress('Failed', pct, 'var(--red)');
                    return;
                }

                _ingestPollTimer = setTimeout(poll, 1500);
            } catch(e) {
                _extAppendLine('Poll error: ' + e.message, 'sse-line-err');
                _ingestPollTimer = setTimeout(poll, 4000);
            }
        };
        poll();
    }

    async function runExtraction() {
        const btn = document.getElementById('btnRunExtraction');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner"></div> Extracting…';

        const config = {
            src_sketches:   document.getElementById('src_sketches').checked,
            src_analysis:   document.getElementById('src_analysis').checked,
            src_lore:       document.getElementById('src_lore').checked,
            src_kg:         document.getElementById('src_kg').checked,
            src_ingredients:document.getElementById('src_ingredients').checked,
            src_characters: document.getElementById('src_characters').checked,
            src_animas:     document.getElementById('src_animas').checked,
            src_locations:  document.getElementById('src_locations').checked,
            src_backgrounds:document.getElementById('src_backgrounds').checked,
            src_artifacts:  document.getElementById('src_artifacts').checked,
            src_vehicles:   document.getElementById('src_vehicles').checked,
            threshold:      parseInt(document.getElementById('ext_threshold').value) / 100,
            max_candidates: parseInt(document.getElementById('ext_max_candidates').value),
            skip_existing:  document.getElementById('ext_skip_existing').checked
        };

        _extShowLog();
        document.getElementById('extractionStreamBox').innerHTML = '';
        _extAppendLine('Starting extraction phase…', 'sse-line-stage');

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'run_extraction', config })
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let jobId = null;

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                const parts = buffer.split('\n');
                buffer = parts.pop();
                for (const part of parts) {
                    const trimmed = part.trim();
                    if (!trimmed) continue;
                    let parsed;
                    try { parsed = JSON.parse(trimmed); } catch { _extAppendLine(trimmed); continue; }
                    const cls = {ok:'sse-line-ok', err:'sse-line-err', warn:'sse-line-warn',
                                 stage:'sse-line-stage', found:'sse-line-found', job:'sse-line-found'}[parsed.type] || 'sse-line-info';
                    _extAppendLine(parsed.msg || trimmed, cls);

                    if (parsed.type === 'job' && parsed.msg && parsed.msg.startsWith('JOB_ID:')) {
                        jobId = parsed.msg.replace('JOB_ID:', '').trim();
                    }
                }
            }

            if (jobId) {
                _extSaveJob(jobId, config.max_candidates);
                _extActivateJobPanel(jobId);
                _extPollJob(jobId);
                toast('Job submitted — polling tablet…', 'info');
            } else {
                _extAppendLine('No job ID received. Check stream above for errors.', 'sse-line-err');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cpu"></i> Extract &amp; Submit';
            }
        } catch(e) {
            _extAppendLine('Error: ' + e.message, 'sse-line-err');
            toast('Extraction failed: ' + e.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cpu"></i> Extract &amp; Submit';
        }
    }

    async function applyExtractionClusters() {
        if (!_extCurrentJobId) { toast('No active job to apply', 'error'); return; }
        const jobId = _extCurrentJobId;
        const maxCand = parseInt(document.getElementById('ext_max_candidates').value) || 200000;

        const applyBtn = document.querySelector('#extractApplyRow button');
        if (applyBtn) { applyBtn.disabled = true; applyBtn.innerHTML = '<div class="spinner"></div> Sending to Local PyAPI...'; }

        _extShowLog();
        _extAppendLine('─── Delegating DB Insertion to Local PyAPI Task Manager ───', 'sse-line-stage');

        try {
            const res = await api('apply_clusters', { job_id: jobId, max_candidates: maxCand });
            if (!res.ok) throw new Error(res.error);

            const ingestJobId = res.data.ingest_job_id;
            
            _extAppendLine('Local Ingest Job Started: ' + ingestJobId, 'sse-line-ok');
            document.getElementById('extractApplyRow').style.display = 'none';
            document.getElementById('extractJobId').textContent = "INGEST: " + ingestJobId;
            
            try { localStorage.setItem(ING_JOB_KEY, JSON.stringify({ jobId: ingestJobId, ts: Date.now() })); } catch(e) {}
            
            _ingestCurrentJobId = ingestJobId;
            _ingestPollJob(ingestJobId);

        } catch(e) {
            _extAppendLine('Apply error: ' + e.message, 'sse-line-err');
            toast('Apply failed: ' + e.message, 'error');
            if (applyBtn) { applyBtn.disabled = false; applyBtn.innerHTML = '<i class="bi bi-database-fill-add"></i> Apply Clusters'; }
        }
    }

    function copyExtractionResumeUrl() {
        const url = window.location.href.split('?')[0];
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => toast('URL copied — re-open Fuzz Forge to resume polling', 'info'));
        } else {
            toast('Copy: ' + url, 'info');
        }
    }

    function clearExtractionJob() {
        if (!confirm('Clear the active clustering job? You will lose the ability to apply its results.')) return;
        clearTimeout(_extPollTimer);
        clearTimeout(_ingestPollTimer);
        _extClearSavedJob();
        document.getElementById('extractJobPanel').style.display = 'none';
        document.getElementById('extractApplyRow').style.display = 'none';
        document.getElementById('extractPhase1').style.display = 'block';
        document.getElementById('btnRunExtraction').style.display = '';
        document.getElementById('btnRunExtraction').disabled = false;
        document.getElementById('btnRunExtraction').innerHTML = '<i class="bi bi-cpu"></i> Extract &amp; Submit';
        document.getElementById('extractionStreamBox').innerHTML = '';
        document.getElementById('extractionJobLog').style.display = 'none';
        toast('Job cleared', 'info');
    }

    function openExtractModal() {
        document.getElementById('extractModal').classList.add('open');
        const savedIngest = _extLoadSavedIngestJob();
        if (savedIngest && savedIngest.jobId && !_ingestCurrentJobId) {
            _extShowLog();
            document.getElementById('extractPhase1').style.display = 'none';
            document.getElementById('extractJobPanel').style.display = 'block';
            document.getElementById('extractJobId').textContent = "INGEST: " + savedIngest.jobId;
            _extAppendLine('Resuming local ingest job ' + savedIngest.jobId + '…', 'sse-line-stage');
            _ingestCurrentJobId = savedIngest.jobId;
            _ingestPollJob(savedIngest.jobId);
            return;
        }
        
        const saved = _extLoadSavedJob();
        if (saved && saved.jobId && !_extCurrentJobId) {
            _extShowLog();
            document.getElementById('extractionStreamBox').innerHTML = '';
            _extActivateJobPanel(saved.jobId);
            _extAppendLine('Resuming tablet cluster job ' + saved.jobId + '…', 'sse-line-stage');
            _extPollJob(saved.jobId);
        }
    }

    // ── Stats Modal ──────────────────────────────────────────────
    async function openStatsModal() {
        document.getElementById('statsModal').classList.add('open');
        const r = await api('get_stats');
        const body = document.getElementById('statsModalBody');
        if (!r.ok) { body.innerHTML = `<div style="color:var(--red);">${r.error}</div>`; return; }
        const s = r.data;
        body.innerHTML = `
            <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
                <div class="stat-tile"><div class="val">${s.total_candidates||0}</div><div class="lbl">Candidates</div></div>
                <div class="stat-tile"><div class="val" style="color:var(--orange);">${s.pending||0}</div><div class="lbl">Pending</div></div>
                <div class="stat-tile"><div class="val" style="color:var(--green);">${s.canonized||0}</div><div class="lbl">Canonized</div></div>
                <div class="stat-tile"><div class="val" style="color:var(--red);">${s.rejected||0}</div><div class="lbl">Rejected</div></div>
            </div>
            <div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-top:0;">
                <div class="stat-tile"><div class="val" style="color:var(--cyan);">${s.total_mentions||0}</div><div class="lbl">Mentions</div></div>
                <div class="stat-tile"><div class="val" style="color:var(--cyan);">${s.total_links||0}</div><div class="lbl">Fuzz Links</div></div>
                <div class="stat-tile"><div class="val" style="color:var(--purple);">${s.kg_linked||0}</div><div class="lbl">KG Linked</div></div>
            </div>
            <div class="dossier-label" style="margin-top:20px;"><i class="bi bi-pie-chart"></i> By Concept Type</div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                ${(s.by_type||[]).map(t => `
                    <div style="padding:8px 14px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); font-family:var(--mono); font-size:0.75rem; text-transform:uppercase;">
                        <span style="color:var(--purple); font-weight:700;">${t.count}</span> <span style="color:var(--text-dim);">${escHtml(t.concept_type||'unclassified')}</span>
                    </div>
                `).join('')}
            </div>
            <div class="dossier-label" style="margin-top:20px;"><i class="bi bi-funnel"></i> By Status</div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                ${(s.by_status||[]).map(t => `
                    <div style="padding:8px 14px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); font-family:var(--mono); font-size:0.75rem; text-transform:uppercase;">
                        <span style="color:var(--orange); font-weight:700;">${t.count}</span> <span style="color:var(--text-dim);">${escHtml(t.status)}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // ── Target Search (Add Link modal) ───────────────────────────
    function debounceTargetSearch() {
        clearTimeout(_targetSearchTimeout);
        _targetSearchTimeout = setTimeout(runTargetSearch, 300);
    }

    async function runTargetSearch() {
        const q = document.getElementById('link_target_input').value.trim();
        const resultsEl = document.getElementById('linkTargetResults');
        if (q.length < 2) { resultsEl.style.display = 'none'; return; }
        const r = await api('search_candidates', { q });
        if (r.ok && r.data.length > 0) {
            resultsEl.style.display = 'block';
            resultsEl.innerHTML = r.data.filter(c => !_currentCandidate || c.id !== _currentCandidate.id).map(c => `
                <div class="kg-result-item" onclick="FuzzForge.selectLinkTarget(${c.id}, '${escHtml(c.label).replace(/'/g,"\\'")}')">
                    <span>#${c.id}</span>
                    <span style="flex:1; font-family:var(--head); font-size:1.1rem; letter-spacing:1px; line-height:1;">${escHtml(c.label)}</span>
                    <span class="kg-type">[${escHtml(c.concept_type||'—')}]</span>
                </div>
            `).join('');
        } else {
            resultsEl.style.display = 'block';
            resultsEl.innerHTML = `<div class="kg-result-item" style="color:var(--text-dim); cursor:default; text-transform:uppercase; letter-spacing:2px;">No matches</div>`;
        }
    }

    function selectLinkTarget(id, label) {
        document.getElementById('link_target_id').value = id;
        document.getElementById('link_target_input').value = label;
        document.getElementById('linkTargetResults').style.display = 'none';
        document.getElementById('linkTargetPreview').style.display = 'block';
        document.getElementById('linkTargetPreview').innerHTML = `<span class="gen-badge badge-count">→ ${escHtml(label)} #${id}</span>`;
    }

    // ── UI Helpers ───────────────────────────────────────────────
    function toggleSection(id) {
        const el = document.getElementById(id);
        const chevron = document.getElementById(id + 'Chevron');
        const hidden = el.style.display === 'none';
        el.style.display = hidden ? '' : 'none';
        if (chevron) chevron.style.transform = hidden ? '' : 'rotate(-90deg)';
        
        const state = _getUIState();
        state[id] = hidden;
        _saveUIState(state);
    }

    function activateRPView(view) {
        document.querySelectorAll('.rp-view').forEach(el => el.style.display = 'none');
        const el = document.getElementById('view' + view.charAt(0).toUpperCase() + view.slice(1));
        if (el) el.style.display = 'flex';
    }

    function refreshRightPanel() {
        if (_currentCandidate) selectCandidate(_currentCandidate.id);
    }

    function openAddMentionModal() {
        document.getElementById('mention_source_id').value = '';
        document.getElementById('mention_text').value = '';
        document.getElementById('mention_context').value = '';
        document.getElementById('addMentionModal').classList.add('open');
    }

    function openAddLinkModal() {
        document.getElementById('link_target_id').value = '';
        document.getElementById('link_target_input').value = '';
        document.getElementById('link_note').value = '';
        document.getElementById('link_confidence').value = 50;
        document.getElementById('linkConfLabel').textContent = '50%';
        document.getElementById('linkTargetResults').style.display = 'none';
        document.getElementById('linkTargetPreview').style.display = 'none';
        document.getElementById('addLinkModal').classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // ── Bind events ──────────────────────────────────────────────
    function bindEvents() {
        document.getElementById('searchInput').addEventListener('input', () => {
            clearTimeout(_searchTimeout);
            _pages = { Pending: 1, Resolved: 1, Rejected: 1 };
            _searchTimeout = setTimeout(loadAllPanels, 300);
        });

        document.getElementById('topFilters').addEventListener('click', e => {
            const chip = e.target.closest('.filter-chip');
            if (!chip) return;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            _typeFilter = chip.dataset.filter;
            _pages = { Pending: 1, Resolved: 1, Rejected: 1 };
            loadAllPanels();
        });

        document.getElementById('rpTabs').addEventListener('click', e => {
            const btn = e.target.closest('.rp-tab');
            if (!btn) return;
            document.querySelectorAll('.rp-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activateRPView(btn.dataset.view);
            const frameControls = document.getElementById('frameSelectionControls');
            if (frameControls) frameControls.style.display = btn.dataset.view === 'frames' ? 'flex' : 'none';
        });

        document.getElementById('newAliasInput').addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); addAlias(); }
        });

        document.querySelectorAll('.forge-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) overlay.classList.remove('open');
            });
        });
        
        document.getElementById('dossierModal').addEventListener('click', e => {
            if (e.target.id === 'dossierModal') closeDossier();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                window.closeEntityModal();
                closeDossier();
                document.querySelectorAll('.forge-modal-overlay.open').forEach(el => el.classList.remove('open'));
            }
        });
    }

    return {
        init, selectCandidate, newCandidate, saveCandidate, deleteCandidate,
        addAlias, removeAlias, linkKGNode, unlinkKGNode, debounceKGSearch,
        renderMentions, saveMention, deleteMention,
        saveFuzzyLink, deleteLink, reviewAction, runSemanticSearch,
        importSemanticMatch, runExtraction, applyExtractionClusters,
        copyExtractionResumeUrl, clearExtractionJob,
        openExtractModal, openStatsModal,
        openAddMentionModal, openAddLinkModal, toggleSection, closeModal,
        refreshRightPanel, debounceTargetSearch, selectLinkTarget, togglePanel, closeDossier, cycleSort,
        changePage, goToPage,
        toggleFrameCard, toggleAllFrames, removeSelectedFrameEntities
    };
})();

document.addEventListener('DOMContentLoaded', () => FuzzForge.init());
</script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php //echo $eruda; ?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
