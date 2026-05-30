<?php
// public/fuzz_forge_landing.php
// ─────────────────────────────────────────────────────────────────────────────
// FUZZ FORGE LANDING
// Dedicated read-only browsing interface for a specific Fuzz Candidate.
// Deep link: /fuzz_forge_landing.php?id=123
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

$candidateId = $_GET['id'] ?? $_GET['candidate_id'] ?? null;
if (!$candidateId) {
    die("Error: Missing Candidate ID. Please provide ?id=123 in the URL.");
}

$viewportScale = !empty($_GET['embed']) ? '0.9' : '0.9';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Candidate #<?= htmlspecialchars($candidateId) ?> — Fuzz Forge Landing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Bebas+Neue&family=Barlow+Condensed:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- PHOTOSWIPE -->
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
    --green:        #00e5a0;
    --green-dim:    rgba(0,229,160,0.09);
    --red:          #ff3d5a;
    --red-dim:      rgba(255,61,90,0.09);
    --purple:       #a855f7;
    --purple-dim:   rgba(168,85,247,0.09);
    
    --mono:         'DM Mono', 'Fira Mono', monospace;
    --head:         'Bebas Neue', 'Barlow Condensed', sans-serif;
    --sans:         'Barlow Condensed', system-ui, sans-serif;
    --radius:       5px;
    --radius-lg:    10px;
    --surface-header: rgba(5,7,13,0.95);
}

@media (prefers-color-scheme: light) {
    :root {
        --bg: #f0f4f8; --surface: #ffffff; --card: #ffffff; --card-hover: #f5f8fc;
        --border: #d0d8e4; --border-glow: #aab8cc; --text: #1a2533; --text-dim: #7a8fa8;
        --text-bright: #0d1824; --cyan: #0094b3; --cyan-dim: rgba(0,148,179,0.09);
        --orange: #c94a00; --orange-dim: rgba(201,74,0,0.08);
        --green: #007a50; --green-dim: rgba(0,122,80,0.09); --red: #c0162f; --red-dim: rgba(192,22,47,0.08);
        --purple: #7c3aed; --purple-dim: rgba(124,58,237,0.09); --surface-header: rgba(240,244,248,0.96);
    }
}
:root[data-theme="light"],html[data-theme="light"],body[data-theme="light"] {
    --bg: #f0f4f8; --surface: #ffffff; --card: #ffffff; --card-hover: #f5f8fc;
    --border: #d0d8e4; --border-glow: #aab8cc; --text: #1a2533; --text-dim: #7a8fa8;
    --text-bright: #0d1824; --cyan: #0094b3; --cyan-dim: rgba(0,148,179,0.09);
    --orange: #c94a00; --orange-dim: rgba(201,74,0,0.08);
    --green: #007a50; --green-dim: rgba(0,122,80,0.09); --red: #c0162f; --red-dim: rgba(192,22,47,0.08);
    --purple: #7c3aed; --purple-dim: rgba(124,58,237,0.09); --surface-header: rgba(240,244,248,0.96);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html,body { height:100%; background:var(--bg); color:var(--text); font-family:var(--sans); font-size:15px; line-height:1.5; -webkit-font-smoothing:antialiased; overflow-x:hidden; }

/* ─── SCANLINE OVERLAY ─── */
body::before {
    content: '';
    position: fixed; inset: 0; pointer-events: none; z-index: 1000;
    background: repeating-linear-gradient(
        0deg, transparent, transparent 2px, rgba(0,212,255,0.012) 2px, rgba(0,212,255,0.012) 4px
    );
}
html[data-theme="light"] body::before, body[data-theme="light"]::before { display: none; }

::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--border-glow); border-radius:4px; }
::-webkit-scrollbar-thumb:hover { background:var(--text-dim); }

/* ── LAYOUT ── */
.landing-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ── HEADER ── */
.forge-header { display:flex; align-items:center; justify-content:space-between; padding:0 20px; background:var(--surface-header); backdrop-filter: blur(12px); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:200; height:56px; }
.forge-logo { display:flex; align-items:center; gap:10px; font-family:var(--head); font-size:1.4rem; letter-spacing:4px; color:var(--text-bright); text-decoration: none;}
.forge-logo-icon { width:32px; height:32px; background:var(--cyan-dim); border:1px solid var(--cyan-mid); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; font-size:15px; color:var(--cyan); box-shadow:0 0 12px var(--cyan-glow); }
.forge-header-right { display:flex; align-items:center; gap:8px; }

/* ── MAIN ── */
.landing-main {
    padding: 24px 20px 60px;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    display: flex; flex-direction: column; gap: 20px;
}

/* ── HERO / INFO CARD ── */
.info-card {
    background: var(--surface);
    border: 1px solid var(--border-glow);
    border-radius: var(--radius-lg);
    padding: 12px 16px;
    position: relative;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.cand-title { font-family: var(--head); font-size: 1.2rem; letter-spacing: 2px; color: var(--text-bright); line-height:1.1; margin-bottom: 6px; word-break: break-word; }
.cand-meta { display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin-bottom: 0; }

.info-expand-toggle {
    display: inline-flex; align-items: center; gap: 5px;
    font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim);
    text-transform: uppercase; letter-spacing: 1px;
    cursor: pointer; border: none; background: none; padding: 7px 0 0;
    transition: color 0.15s;
}
.info-expand-toggle:hover { color: var(--cyan); }
.info-expand-toggle i { transition: transform 0.2s; }
.info-expand-toggle.open i { transform: rotate(180deg); }

.info-expandable { display: none; margin-top: 10px; border-top: 1px solid var(--border); padding-top: 10px; }
.info-expandable.open { display: block; }

.alias-list { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.alias-tag { display:inline-flex; align-items:center; padding:4px 12px; background:var(--bg); border:1px solid var(--border-glow); border-radius:20px; font-family:var(--mono); font-size:0.75rem; color:var(--text-bright); }

.notes-block {
    background: var(--bg);
    border: 1px dashed var(--border-glow);
    border-radius: var(--radius);
    padding: 12px;
    font-family: var(--sans);
    font-size: 0.9rem;
    color: var(--text-dim);
    white-space: pre-wrap;
    min-height: 36px;
}

/* ── TABS ── */
.tabs-container {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    min-height: 500px;
    overflow: hidden;
}
.rp-tabs { padding:9px 14px; border-bottom:1px solid var(--border); background:var(--surface); display:flex; gap:5px; flex-wrap:nowrap; overflow-x:auto; }
.rp-tab { padding:4px 10px; border-radius:20px; border:1px solid var(--border-glow); background:transparent; color:var(--text-dim); font-family:var(--mono); font-size:0.65rem; cursor:pointer; transition:all 0.15s; white-space:nowrap; text-transform: uppercase; letter-spacing: 0.5px; }
.rp-tab.active { background:var(--cyan-dim); border-color:var(--cyan); color:var(--cyan); }
.rp-tab:hover:not(.active) { border-color:var(--cyan); color:var(--text-bright); }

.rp-view { display:none; flex:1; flex-direction:column; padding: 0; }
.rp-view.active { display:flex; }
.rp-view-scroll { flex:1; padding: 20px; overflow-x: auto;}

/* ── MENTIONS TABLE — now a flat list of blocks, not a <table> ── */
.mention-list { display: flex; flex-direction: column; gap: 0; }

/* Each mention is a "row block" that contains the info row + optional frames strip */
.mention-block {
    border-bottom: 1px solid var(--border);
}
.mention-block:last-child { border-bottom: none; }

/* The top info row inside a block — mimics old table row layout */
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

/* Frames strip embedded inside a mention block */
.mention-frames-strip {
    padding: 10px 14px 14px;
    background: rgba(0,212,255,0.025);
    border-top: 1px solid var(--border);
    /* subtle left accent line to visually connect to the row above */
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

/* Inline frames grid — same style as the Frames tab but tighter */
.mention-frames-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px;
}

/* Shared table-header bar above the mention list */
.mention-list-header {
    display: grid;
    grid-template-columns: 18% 44% 38%;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    position: sticky;
    top: 0;
    z-index: 10;
}
.mention-list-header span {
    font-family: var(--mono);
    font-size: 0.7rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 2px;
    padding: 0 6px;
}
.mention-list-header span:first-child { padding-left: 0; }
.mention-list-header span:last-child  { padding-right: 0; }

/* ── BADGES ── */
.gen-badge { font-family:var(--mono); font-size:0.65rem; padding:3px 8px; border-radius:3px; border:1px solid; white-space:nowrap; text-transform:uppercase; letter-spacing: 1px; display:inline-flex; align-items:center; gap:4px;}
.badge-stage { border-color:var(--orange); color:var(--orange); background:var(--orange-dim); }
.badge-canon { border-color:var(--green); color:var(--green); background:var(--green-dim); }
.badge-rejected { border-color:var(--red); color:var(--red); background:var(--red-dim); }
.badge-deferred { border-color:var(--text-dim); color:var(--text-dim); background:var(--bg); }
.badge-promoted { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
.badge-extracted { border-color:var(--purple); color:var(--purple); background:var(--purple-dim); }
.badge-type { border-color:var(--border-glow); color:var(--text-dim); background:var(--bg); }
.badge-count { border-color:var(--purple); color:var(--purple); background:var(--purple-dim); }

.source-label { display:inline-block; padding:2px 6px; border-radius:3px; font-size:0.68rem; font-family:var(--mono); background:var(--bg); border:1px solid var(--border-glow); color:var(--text-dim); text-transform: uppercase; letter-spacing: 1px;}

.mtype-sketch_name { color:var(--cyan); }
.mtype-sketch_desc { color:var(--cyan); }
.mtype-analysis_entity { color:var(--orange); }
.mtype-analysis_thematic { color:var(--purple); }
.mtype-kg_node { color:var(--green); }
.mtype-lore_history { color:var(--red); }
.mtype-ingredient { color:var(--text-dim); }

/* shared cell styles */
.cell-mono { font-family:var(--mono); color:var(--text-dim); font-size:0.72rem; }
.cell-meta-stack { display:flex; flex-direction:column; gap:6px; }
.cell-meta-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.cell-text-stack { display:flex; flex-direction:column; gap:5px; }
.cell-text-name { color: var(--text); font-family: var(--sans); font-size: 0.78rem; line-height: 1.2; opacity: 0.98; word-break: break-word; overflow-wrap: anywhere; }
.cell-text-main { color:var(--text-bright); font-weight:500; font-family:var(--sans); font-size:1.05rem; letter-spacing: 0.5px; line-height:1.2; word-break:break-word; overflow-wrap:anywhere; }
.cell-text-ctx { color:var(--text-dim); font-size:0.82rem; font-family:var(--sans); line-height:1.35; white-space: normal; overflow: visible; text-overflow: unset; word-break: break-word; overflow-wrap:anywhere; }

/* ── FRAMES GRID (Frames tab) ── */
.frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; padding-bottom: 20px; }

/* ── FRAME CARD (shared by both the Frames tab and inline mention strips) ── */
.f-card { aspect-ratio: 1; background: var(--bg); border: 1px solid var(--border-glow); border-radius: 4px; position: relative; overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s; }
.f-card:hover { border-color: var(--cyan); box-shadow: 0 0 8px var(--cyan-glow); }
.f-link { display: block; width: 100%; height: calc(100% - 22px); overflow: hidden; cursor: pointer; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.05); }
.f-view-btn { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; background: rgba(0,0,0,0.7); color: #fff; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; display: flex; align-items:center; justify-content:center; cursor:pointer; z-index:10; opacity:0; transition: all 0.2s; font-size:12px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--cyan); border-color: var(--cyan); color: #000; }
.f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 22px; background: rgba(5,7,13,0.92); padding: 0 8px; font-size: 0.65rem; color: var(--text-dim); border-top: 1px solid var(--border-glow); display: flex; align-items:center; justify-content: space-between; z-index: 2; font-family: var(--mono); }

/* ── TIMELINE (REVIEWS) ── */
.review-timeline { padding-top: 10px; }
.review-event { display:flex; gap:16px; margin-bottom:20px; position:relative; }
.review-event::before { content:''; position:absolute; left:16px; top:36px; bottom:-20px; width:2px; background:var(--border); }
.review-event:last-child::before { display:none; }
.review-dot { width:34px; height:34px; border-radius:50%; border:1px solid var(--border-glow); background:var(--bg); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; position:relative; z-index:2; }
.review-dot.confirmed { border-color:var(--green); background:var(--green-dim); color:var(--green); box-shadow: 0 0 8px rgba(0,229,160,0.3); }
.review-dot.rejected { border-color:var(--red); background:var(--red-dim); color:var(--red); }
.review-dot.promoted { border-color:var(--cyan); background:var(--cyan-dim); color:var(--cyan); box-shadow: 0 0 8px var(--cyan-glow); }
.review-dot.deferred { border-color:var(--text-dim); }
.review-body { flex:1; padding-top:4px; }
.review-action { font-family:var(--mono); font-size:0.8rem; color:var(--text-bright); text-transform: uppercase; letter-spacing: 1.5px; font-weight:500; }
.review-note { font-size:0.9rem; color:var(--text-dim); margin-top:6px; font-family: var(--sans); }
.review-time { font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); margin-top:6px; opacity:0.7; }

/* ── BUTTONS ── */
.btn-forge-primary { padding:8px 16px; background:var(--cyan); color:#000; border:none; border-radius:var(--radius); cursor:pointer; font-family:var(--mono); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; transition:all 0.15s; display:inline-flex; align-items:center; justify-content:center; gap:6px; box-shadow: 0 0 8px var(--cyan-glow); text-decoration: none;}
.btn-forge-primary:hover { background:var(--text-bright); box-shadow: 0 0 12px var(--cyan-glow); }
.btn-icon-sm { width:32px; height:32px; border-radius:var(--radius); border:1px solid var(--border-glow); background:var(--card); color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; text-decoration: none;}
.btn-icon-sm:hover { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); box-shadow: 0 0 8px var(--cyan-glow); }
.btn-entity { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid var(--border-glow); background: var(--bg); border-radius: 3px; color: var(--text-dim); cursor: pointer; font-size: 12px; transition: all 0.12s; flex-shrink: 0; text-decoration: none; }
.btn-entity:hover { border-color: var(--cyan); color: var(--cyan); background: var(--cyan-dim); box-shadow: 0 0 6px var(--cyan-glow);}

/* ── ENTITY IFRAME MODAL (For Deep Dive) ── */
.ef-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.88); backdrop-filter: blur(4px); z-index: 10500; display: none; align-items: flex-end; justify-content: center; padding: 0; }
.ef-overlay.open { display: flex; }
@media (min-width: 600px) { .ef-overlay { align-items: center; padding: 16px; } }
.ef-sheet { width: 100%; max-width: 900px; background: var(--surface); border: 1px solid var(--border-glow); border-radius: 14px 14px 0 0; display: flex; flex-direction: column; height: 92dvh; overflow: hidden; box-shadow: 0 -10px 60px rgba(0,0,0,0.7); animation: modalIn 0.22s cubic-bezier(0.25,1,0.5,1); }
@media (min-width: 600px) { .ef-sheet { border-radius: 14px; height: 88dvh; } }
.ef-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--surface-header); }
.ef-title { font-family: var(--head); font-size: 1.4rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-bright); flex: 1; }
.ef-body { flex: 1; overflow: hidden; }
.ef-body iframe { width: 100%; height: 100%; border: none; background: var(--bg); display: block; }
.forge-modal-close { width:32px; height:32px; border-radius:4px; border:1px solid var(--border-glow); background:transparent; color:var(--text-dim); cursor:pointer; transition:all 0.15s; display:flex; align-items:center; justify-content:center; font-size:16px; }
.forge-modal-close:hover { border-color:var(--red); color:var(--red); background:var(--red-dim); }

@keyframes modalIn { from { opacity:0; transform:scale(0.96) translateY(20px); } to { opacity:1; transform:none; } }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>

<div class="landing-layout">

    <!-- ══════════════════════════════════════════════════════════ HEADER -->
    <header class="forge-header">
        <a href="/fuzz_forge.php" class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-diagram-3-fill"></i></div>
            FUZZ FORGE
        </a>
        <div class="forge-header-right">
            <a href="/fuzz_forge.php?candidate_id=<?= urlencode($candidateId) ?>" class="btn-forge-primary">
                <i class="bi bi-pencil-square"></i> Edit in Forge
            </a>
            <a href="/dashboard.php" class="btn-icon-sm" title="Dashboard">
                <i class="bi bi-house"></i>
            </a>
        </div>
    </header>

    <!-- ══════════════════════════════════════════════════════════ MAIN -->
    <main class="landing-main">

        <!-- INFO CARD -->
        <div class="info-card" id="infoCard" style="display:none;">
            <div class="cand-title" id="lblTitle">—</div>
            <div class="cand-meta" id="lblMeta">
                <!-- Badges populated via JS -->
            </div>
            <button class="info-expand-toggle" id="infoExpandToggle" onclick="ForgeLandingExpand.toggle()">
                <i class="bi bi-chevron-down"></i> aliases &amp; notes
            </button>
            <div class="info-expandable" id="infoExpandable">
                <div class="alias-list" id="lblAliases"></div>
                <div class="notes-block" id="lblNotes"></div>
            </div>
        </div>

        <div id="loadingIndicator" style="text-align:center; padding: 40px; font-family:var(--mono); color:var(--text-dim); text-transform:uppercase; letter-spacing:2px;">
            <i class="bi bi-arrow-repeat" style="display:inline-block; animation:spin 1s linear infinite; margin-right:8px;"></i> Loading Candidate Data...
        </div>

        <!-- TABS CONTAINER -->
        <div class="tabs-container" id="tabsContainer" style="display:none;">
            <div class="rp-tabs" id="rpTabs">
                <button class="rp-tab active" data-view="mentions">Mentions <span id="countMentions" class="gen-badge badge-count" style="margin-left:4px; padding:1px 6px;">0</span></button>
                <button class="rp-tab" data-view="frames">Frames <span id="countFrames" class="gen-badge badge-count" style="margin-left:4px; padding:1px 6px;">0</span></button>
                <button class="rp-tab" data-view="links">Fuzzy Links</button>
                <button class="rp-tab" data-view="reviews">Review History</button>
            </div>
            
            <!-- MENTIONS -->
            <div class="rp-view active" id="viewMentions">
                <div class="rp-view-scroll" style="padding:0;">
                    <div class="mention-list" id="mentionList"></div>
                </div>
            </div>

            <!-- FRAMES -->
            <div class="rp-view" id="viewFrames">
                <div class="rp-view-scroll">
                    <div class="frames-grid" id="framesGridContainer">
                        <!-- JS populated -->
                    </div>
                </div>
            </div>

            <!-- FUZZY LINKS -->
            <div class="rp-view" id="viewLinks">
                <div class="rp-view-scroll">
                    <div id="linksContainer"></div>
                </div>
            </div>

            <!-- REVIEWS -->
            <div class="rp-view" id="viewReviews">
                <div class="rp-view-scroll">
                    <div class="review-timeline" id="reviewTimeline"></div>
                </div>
            </div>

        </div>

    </main>
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

<script>
const CANDIDATE_ID = <?= json_encode((int)$candidateId) ?>;

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

const ForgeLanding = (() => {
    'use strict';

    const API = '/api/fuzz_forge_api.php';
    // Track lightboxes so we can destroy/re-init them
    let _lightboxFramesTab = null;
    // Map of per-mention lightboxes: mentionIndex -> PhotoSwipeLightbox
    const _mentionLightboxes = {};

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    async function fetchCandidate() {
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_candidate', id: CANDIDATE_ID })
            });
            const data = await res.json();
            
            if (!data.ok) {
                document.getElementById('loadingIndicator').innerHTML = `<span style="color:var(--red);"><i class="bi bi-exclamation-triangle"></i> Error: ${escHtml(data.error)}</span>`;
                return;
            }

            renderAll(data.data);

        } catch (e) {
            document.getElementById('loadingIndicator').innerHTML = `<span style="color:var(--red);"><i class="bi bi-exclamation-triangle"></i> Request failed.</span>`;
        }
    }

    function renderAll(data) {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('infoCard').style.display = 'block';
        document.getElementById('tabsContainer').style.display = 'flex';

        const c = data.candidate;
        const aliases = data.aliases || [];
        const mentions = data.mentions || [];
        const frames = data.frames || [];
        const links = data.links || [];
        const reviews = data.reviews || [];

        // 1. Identity Card
        document.getElementById('lblTitle').textContent = c.label || `Candidate #${c.id}`;
        
        const stageClass = {
            extracted: 'badge-extracted', grouped: 'badge-stage', reviewed: 'badge-stage',
            promoted: 'badge-promoted', canonized: 'badge-canon',
            rejected: 'badge-rejected', deferred: 'badge-deferred'
        }[c.status] || 'badge-type';

        let metaHtml = `
            <span class="gen-badge ${stageClass}">${escHtml(c.status)}</span>
            ${c.concept_type ? `<span class="gen-badge badge-type">${escHtml(c.concept_type)}</span>` : ''}
            <span class="gen-badge badge-type">ID: ${c.id}</span>
            <span class="gen-badge" style="border-color:var(--cyan); color:var(--cyan);"><i class="bi bi-bar-chart"></i> Conf: ${c.confidence}%</span>
        `;
        if (c.kg_node_id) {
            metaHtml += `<span class="gen-badge badge-promoted" style="cursor:pointer;" onclick="window.openEntityModal('kg_nodes', ${c.kg_node_id})"><i class="bi bi-diagram-3-fill"></i> KG Node #${c.kg_node_id}</span>`;
        }
        document.getElementById('lblMeta').innerHTML = metaHtml;

        if (aliases.length > 0) {
            document.getElementById('lblAliases').innerHTML = aliases.map(a => `<span class="alias-tag">${escHtml(a)}</span>`).join('');
        } else {
            document.getElementById('lblAliases').innerHTML = `<span style="font-family:var(--mono); font-size:0.75rem; color:var(--text-dim); font-style:italic;">No known aliases</span>`;
        }

        if (c.notes) {
            document.getElementById('lblNotes').textContent = c.notes;
        } else {
            document.getElementById('lblNotes').innerHTML = `<span style="font-style:italic; opacity:0.6;">No curator notes provided.</span>`;
        }

        // Update Tab Counts
        document.getElementById('countMentions').textContent = mentions.length;
        document.getElementById('countFrames').textContent = new Set(frames.map(f => f.id)).size;

        // 2. Mentions (with per-row frame strips)
        renderMentions(mentions, frames);

        // 3. Frames tab
        renderFrames(frames);

        // 4. Links
        renderLinks(links);

        // 5. Reviews
        renderReviews(reviews);
    }

    /**
     * Build a lookup: source_row_id => [frame, frame, ...]
     * We key by source_row_id when source_table is a sketch/entity table
     * so we can attach relevant frames to each mention row.
     *
     * The frames array from the API has entity_type / entity_id fields
     * (same convention as everywhere else in the app).
     */
    function buildFrameIndex(frames) {
        // Index by entity_id (frame's owning entity)
        const byEntityId = {};
        frames.forEach(f => {
            const key = f.entity_id;
            if (key == null) return;
            if (!byEntityId[key]) byEntityId[key] = [];
            byEntityId[key].push(f);
        });
        return byEntityId;
    }

    function renderMentions(mentions, frames) {
        const list = document.getElementById('mentionList');

        if (!mentions.length) {
            list.innerHTML = `<div style="text-align:center; color:var(--text-dim); padding:40px; font-family:var(--mono); text-transform:uppercase; letter-spacing:2px;">No mentions recorded</div>`;
            return;
        }

        // Build frame lookup by entity_id
        const frameIndex = buildFrameIndex(frames);

        list.innerHTML = mentions.map((m, idx) => {
            const typeClass = 'mtype-' + (m.mention_type || '').replace(/ /g, '_');
            const sourceEntityName = m.source_entity_name || m.sketches_name || m.sketch_name || m.source_name || m.source_label || m.name || m.entity_name || '';

            let btnEntity = '';
            if (m.source_row_id && ['sketches', 'sketch_analysis', 'sketch_lore_history', 'sketch_ingredients', 'kg_nodes'].includes(m.source_table)) {
                btnEntity = `
                    <button class="btn-entity" onclick="window.openEntityModal('${escHtml(m.source_table)}', ${m.source_row_id})" title="Deep Dive Source Entity">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </button>`;
            }

            // Determine frames for this mention row.
            // We match on source_row_id (i.e. the entity that produced this mention).
            const rowFrames = (m.source_row_id && frameIndex[m.source_row_id]) ? frameIndex[m.source_row_id] : [];

            const framesStrip = rowFrames.length > 0
                ? buildMentionFramesStrip(rowFrames, idx)
                : '';

            return `
            <div class="mention-block">
                <div class="mention-row">
                    <div class="mention-col">
                        <div class="cell-meta-row" style="justify-content:flex-start;">
                            ${btnEntity}
                            <span class="cell-mono">${m.source_row_id ? '#' + m.source_row_id : '—'}</span>
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
                            <div>
                                <span class="source-label">${escHtml(m.source_table)}</span>
                            </div>
                            <div>
                                <span class="gen-badge badge-type ${typeClass}" style="border-color:currentColor;">${escHtml(m.mention_type || '—')}</span>
                            </div>
                        </div>
                    </div>
                </div>
                ${framesStrip}
            </div>`;
        }).join('');

        // Init PhotoSwipe for each mention frames strip
        if (typeof PhotoSwipeLightbox !== 'undefined') {
            mentions.forEach((m, idx) => {
                const stripId = `mention-strip-${idx}`;
                const stripEl = document.getElementById(stripId);
                if (!stripEl) return;

                if (_mentionLightboxes[idx]) {
                    _mentionLightboxes[idx].destroy();
                }
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

    /**
     * Build the inline frames strip HTML for a given set of frames.
     * Uses the same f-card / f-link / f-label markup as the Frames tab.
     */
    function buildMentionFramesStrip(rowFrames, idx) {
        const stripId = `mention-strip-${idx}`;
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

    function renderFrames(frames) {
        const container = document.getElementById('framesGridContainer');
        
        // Deduplicate frames for the grid
        const uniqueFrames = [];
        const seen = new Set();
        frames.forEach(f => {
            if (!seen.has(f.id)) {
                seen.add(f.id);
                uniqueFrames.push(f);
            }
        });

        if (!uniqueFrames.length) {
            container.style.display = 'block';
            container.innerHTML = `<div style="color:var(--text-dim); font-family:var(--mono); font-size:0.85rem; text-align:center; padding:40px; text-transform:uppercase; letter-spacing:2px; grid-column: 1 / -1;">No image frames linked to this candidate.</div>`;
            return;
        }
        
        container.style.display = 'grid';
        container.innerHTML = uniqueFrames.map(f => {
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
            </div>
        `}).join('');

        if (typeof PhotoSwipeLightbox !== 'undefined') {
            if (_lightboxFramesTab) { _lightboxFramesTab.destroy(); }
            _lightboxFramesTab = new PhotoSwipeLightbox({
                gallery: '#framesGridContainer',
                children: 'a.f-link', 
                pswpModule: PhotoSwipe
            });
            _lightboxFramesTab.init();
        }
    }

    function renderLinks(links) {
        const container = document.getElementById('linksContainer');
        if (!links.length) {
            container.innerHTML = `<div style="color:var(--text-dim); font-family:var(--mono); font-size:0.85rem; text-align:center; padding:40px; text-transform:uppercase; letter-spacing:2px;">No fuzzy relationships recorded.</div>`;
            return;
        }
        
        const relColors = {
            may_refer_to: 'var(--orange)', likely_variant_of: 'var(--purple)',
            contextually_related: 'var(--cyan)', possible_alias_of: 'var(--cyan)',
            probable_family_member: 'var(--green)', contradicts: 'var(--red)'
        };
        
        container.innerHTML = links.map(l => {
            const col = relColors[l.relationship_type] || 'var(--text-dim)';
            const conf = l.confidence ? Math.round(l.confidence) : 0;
            return `
            <div style="display:flex; align-items:center; gap:14px; padding:16px 20px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:10px;">
                <div style="flex:1;">
                    <div style="font-family:var(--mono); font-size:0.75rem; color:${col}; margin-bottom:6px; text-transform:uppercase; letter-spacing:1px;">${escHtml(l.relationship_type?.replace(/_/g, ' '))}</div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <a href="/fuzz_forge_landing.php?id=${l.target_candidate_id}" style="font-weight:600; font-family:var(--head); font-size:1.5rem; letter-spacing:1px; color:var(--text-bright); line-height:1.1; text-decoration:none; transition:color 0.15s;" onmouseover="this.style.color='var(--cyan)'" onmouseout="this.style.color='var(--text-bright)'">
                            ${escHtml(l.target_label || 'Candidate #' + l.target_candidate_id)}
                        </a>
                        <a href="/fuzz_forge_landing.php?id=${l.target_candidate_id}" class="btn-entity" style="width:24px; height:24px; font-size:11px;" title="Go to Candidate Landing">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                    ${l.note ? `<div style="font-size:0.9rem; color:var(--text-dim); margin-top:8px;">${escHtml(l.note)}</div>` : ''}
                </div>
                <div style="font-family:var(--head); font-size:1.8rem; color:var(--cyan); min-width:50px; text-align:right;">${conf}%</div>
            </div>`;
        }).join('');
    }

    function renderReviews(reviews) {
        const container = document.getElementById('reviewTimeline');
        if (!reviews.length) {
            container.innerHTML = `<div style="color:var(--text-dim); font-family:var(--mono); font-size:0.85rem; text-align:center; padding:40px; text-transform:uppercase; letter-spacing:2px;">No review events found.</div>`;
            return;
        }
        
        const dotMap = { confirmed: 'confirmed', rejected: 'rejected', promoted: 'promoted', deferred: 'deferred' };
        const iconMap = { confirmed: 'bi-check-lg', rejected: 'bi-x-lg', promoted: 'bi-arrow-up-circle', deferred: 'bi-clock', split: 'bi-scissors', linked: 'bi-link-45deg', unresolved: 'bi-question-circle' };
        
        container.innerHTML = reviews.map(rv => `
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

    function bindEvents() {
        // Tab switching logic
        document.getElementById('rpTabs').addEventListener('click', e => {
            const btn = e.target.closest('.rp-tab');
            if (!btn) return;
            
            document.querySelectorAll('.rp-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            document.querySelectorAll('.rp-view').forEach(el => el.style.display = 'none');
            
            const viewId = 'view' + btn.dataset.view.charAt(0).toUpperCase() + btn.dataset.view.slice(1);
            const viewEl = document.getElementById(viewId);
            if (viewEl) viewEl.style.display = 'flex';
        });

        // Escape to close entity modal
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                window.closeEntityModal();
            }
        });
    }

    return {
        init: () => {
            bindEvents();
            fetchCandidate();
        }
    };
})();

document.addEventListener('DOMContentLoaded', ForgeLanding.init);

const ForgeLandingExpand = {
    toggle() {
        const btn = document.getElementById('infoExpandToggle');
        const panel = document.getElementById('infoExpandable');
        const open = panel.classList.toggle('open');
        btn.classList.toggle('open', open);
        btn.querySelector('i').className = open ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
    }
};
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>


<?php // echo $eruda; ?>


</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>