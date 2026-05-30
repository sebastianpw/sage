<?php
// public/muvitriccs.php
// SAGE AI — MuviTriccs Video Transition Chain Editor
// A horizontal slot-chain interface where each slot is a media asset
// and each connector between slots is a parameterized transition.
// Renders via PyAPI muvitriccs_service.py.

require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
global $pdo;
if (!isset($pdo)) $pdo = $spw->getPDO();

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$project = null;
if ($projectId > 0) {
    $stmtP = $pdo->prepare("SELECT * FROM muvitriccs_projects WHERE id = ?");
    $stmtP->execute([$projectId]);
    $project = $stmtP->fetch(PDO::FETCH_ASSOC);
    if (!$project) $projectId = 0;
}

$pageTitle = $project ? 'MuviTriccs: ' . htmlspecialchars($project['name']) : 'MuviTriccs';

ob_start();
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<?php else: ?>
<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css"/>
<?php endif; ?>
<link rel="stylesheet" href="/css/base.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* ── MuviTriccs Design System ── */
:root {
  --bg:        #0b0d12;
  --surf:      #131620;
  --panel:     #1a1e2e;
  --panel2:    #202436;
  --border:    #252b3d;
  --acc:       #e8b84b;   /* warm amber — distinct from MultiVid's teal */
  --acc2:      #ff5f6d;   /* coral red */
  --acc3:      #5ec4ff;   /* sky blue */
  --acc4:      #9b7fe8;   /* violet */
  --text:      #dde3f0;
  --muted:     #5a6278;
  --mono:      'Courier New', monospace;
  --r:         8px;
  --chain-h:   160px;    /* height of the chain track */
  --slot-w:    140px;
  --conn-w:    90px;     /* connector/transition pill width */
  /* Fixed params panel height — always reserved at viewport bottom */
  --params-h:  210px;
}

[data-theme="light"], html[data-theme="light"], body[data-theme="light"] {
  --bg:     #f2f3f8;
  --surf:   #ffffff;
  --panel:  #eaecf4;
  --panel2: #e0e3ef;
  --border: #c8ccde;
  --text:   #1a1d2e;
  --muted:  #7a80a0;
}
@media (prefers-color-scheme: light) {
  :root {
    --bg:#f2f3f8;--surf:#fff;--panel:#eaecf4;--panel2:#e0e3ef;
    --border:#c8ccde;--text:#1a1d2e;--muted:#7a80a0;
  }
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  width: 100%; height: 100%;
  background: var(--bg); color: var(--text);
  font-family: 'Segoe UI', system-ui, sans-serif;
  overflow: hidden;
  -webkit-user-select: none; user-select: none;
}

/* ── ROOT LAYOUT ── */
#mt-root {
  display: flex; flex-direction: column;
  width: 100vw; height: 100vh; overflow: hidden;
}

/* ── TOP BAR ── */
#mt-topbar {
  display: flex; flex-direction: column; flex-shrink: 0;
  padding: 5px 10px;
  background: var(--surf); border-bottom: 1px solid var(--border);
  z-index: 200;
}
.mt-toprow {
  display: flex; align-items: center; gap: 6px;
  flex-wrap: nowrap; overflow-x: auto;
  scrollbar-width: none; padding: 2px 0;
}
.mt-toprow::-webkit-scrollbar { display: none; }
.mt-toprow-ph { width: 36px; height: 28px; flex-shrink: 0; }

.mt-projname {
  font-size: 11px; color: var(--muted);
  white-space: nowrap; max-width: 150px;
  overflow: hidden; text-overflow: ellipsis;
}
.mt-sep { width: 1px; height: 20px; background: var(--border); flex-shrink: 0; }

.mt-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 5px;
  padding: 7px 13px; border-radius: 6px; border: 1px solid var(--border);
  background: var(--panel); color: var(--text); font-size: 13px;
  cursor: pointer; white-space: nowrap; flex-shrink: 0;
  transition: background .12s, border-color .12s, color .12s;
}
.mt-btn:hover  { background: var(--border); border-color: var(--acc); color: var(--acc); }
.mt-btn.acc    { background: var(--acc); border-color: var(--acc); color: #0b0d12; font-weight: 700; }
.mt-btn.acc:hover { background: #d4a53c; }
.mt-btn.danger { border-color: var(--acc2); color: var(--acc2); }
.mt-btn.danger:hover { background: rgba(255,95,109,.15); }
.mt-btn.accent3 { border-color: var(--acc3); color: var(--acc3); }
.mt-btn.accent3:hover { background: rgba(94,196,255,.12); }

/* ── STATUS BAR ── */
#mt-statusbar {
  display: flex; align-items: center; gap: 10px;
  padding: 4px 14px;
  font-size: 10px; color: var(--muted);
  background: var(--bg); border-bottom: 1px solid var(--border);
  flex-shrink: 0; overflow: hidden;
}
#mt-statusbar .pyapi-dot {
  width: 7px; height: 7px; border-radius: 50%; background: var(--muted);
  flex-shrink: 0; transition: background .3s;
}
#mt-statusbar .pyapi-dot.online  { background: #4cdb8c; }
#mt-statusbar .pyapi-dot.offline { background: var(--acc2); }

/* ── WORKSPACE ── */
#mt-workspace {
  flex: 1; display: flex; flex-direction: column;
  overflow: hidden; position: relative;
  /* reserve space for fixed params panel */
  padding-bottom: var(--params-h);
}

/* ── CHAIN TRACK ── */
#mt-chain-wrap {
  height: calc(var(--chain-h) + 40px);
  flex-shrink: 0;
  background: var(--surf);
  border-bottom: 2px solid var(--border);
  overflow-x: auto; overflow-y: hidden;
  display: flex; align-items: center;
  padding: 10px 16px;
  gap: 0;
  scrollbar-width: thin;
  scrollbar-color: var(--border) transparent;
  position: relative;
}
#mt-chain-wrap::-webkit-scrollbar { height: 4px; }
#mt-chain-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

#mt-chain {
  display: flex; align-items: center;
  gap: 0; min-width: max-content;
}

/* ── SLOT ── */
.mt-slot {
  width: var(--slot-w);
  height: var(--chain-h);
  border-radius: var(--r);
  border: 2px solid var(--border);
  background: var(--panel);
  display: flex; flex-direction: column;
  align-items: center; justify-content: space-between;
  padding: 6px 5px;
  cursor: pointer; flex-shrink: 0;
  position: relative;
  transition: border-color .15s, background .15s, transform .12s;
}
.mt-slot:hover  { border-color: var(--acc); background: var(--panel2); transform: translateY(-2px); }
.mt-slot.sel    { border-color: var(--acc); background: rgba(232,184,75,.07); }
.mt-slot.adding { border-style: dashed; border-color: var(--acc3); opacity: .7; }

.mt-slot-badge {
  font-size: 8px; font-weight: 800; padding: 2px 6px; border-radius: 4px;
  text-transform: uppercase; letter-spacing: .06em; align-self: flex-start;
}
.mt-slot-badge.video { background: rgba(255,95,109,.2); color: var(--acc2); }
.mt-slot-badge.frame { background: rgba(94,196,255,.15); color: var(--acc3); }

.mt-slot-thumb {
  width: 100%; flex: 1;
  border-radius: 5px; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: var(--bg); margin: 4px 0;
  font-size: 24px; color: var(--muted);
}
.mt-slot-thumb img {
  width: 100%; height: 100%; object-fit: cover;
}

.mt-slot-name {
  font-size: 9px; color: var(--muted); text-align: center;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  width: 100%; line-height: 1.2;
}
.mt-slot-order {
  position: absolute; top: 4px; right: 6px;
  font-size: 9px; font-weight: 800; color: var(--muted);
  font-family: var(--mono);
}
.mt-slot-del {
  position: absolute; top: 4px; left: 4px;
  width: 18px; height: 18px; border-radius: 4px;
  background: rgba(255,95,109,.15); color: var(--acc2);
  border: none; cursor: pointer; font-size: 9px;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .12s;
}
.mt-slot:hover .mt-slot-del { opacity: 1; }

/* ── ADD SLOT BUTTON ── */
.mt-add-slot {
  width: 52px; height: var(--chain-h);
  border-radius: var(--r); border: 2px dashed var(--border);
  background: transparent; color: var(--muted); font-size: 22px;
  cursor: pointer; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: border-color .15s, color .15s;
  margin-left: 8px;
}
.mt-add-slot:hover { border-color: var(--acc); color: var(--acc); }

/* ── TRANSITION CONNECTOR ── */
.mt-connector {
  width: calc(var(--conn-w) + 20px); height: 90px;
  flex-shrink: 0; position: relative;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 6px; cursor: pointer;
}
.mt-conn-line {
  position: absolute; top: 50%; left: 0; right: 0;
  height: 2px; background: var(--border);
  transform: translateY(-50%);
  z-index: 1;
}
.mt-conn-pill {
  position: relative; z-index: 2;
  padding: 5px 9px; border-radius: 20px;
  border: 1px solid var(--acc); background: var(--panel);
  font-size: 9px; font-weight: 700; color: var(--acc);
  text-align: center; white-space: nowrap;
  letter-spacing: .04em; text-transform: uppercase;
  max-width: var(--conn-w); overflow: hidden; text-overflow: ellipsis;
  transition: background .12s, color .12s;
  line-height: 1.3;
}
.mt-connector:hover .mt-conn-pill {
  background: var(--acc); color: #0b0d12;
}
.mt-conn-dur {
  position: relative; z-index: 2;
  font-size: 8px; color: var(--muted); font-family: var(--mono);
}
.mt-conn-actions {
  position: relative;
  display: flex; gap: 4px; z-index: 10;
  pointer-events: auto;
}
.mt-conn-render-btn, .mt-conn-play-btn {
  padding: 5px 10px; border-radius: 10px; border: none; cursor: pointer; white-space: nowrap; font-size: 10px; font-weight: 700;
}
.mt-conn-render-btn { background: var(--panel2); color: var(--text); border: 1px solid var(--border); transition: background .12s; }
.mt-conn-render-btn:hover { background: var(--acc); color: #000; border-color: var(--acc); }
.mt-conn-play-btn { background: #4cdb8c; color: #000; transition: background .12s; }
.mt-conn-play-btn:hover { background: #3ab975; }

.mt-conn-status-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--muted); position: absolute; top: 6px; right: 6px;
  z-index: 3;
}
.mt-conn-status-dot.done    { background: #4cdb8c; }
.mt-conn-status-dot.working { background: var(--acc); animation: pulse .8s infinite; }
.mt-conn-status-dot.fail    { background: var(--acc2); }

@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }

/* ── SCROLLABLE DETAIL (upper area — transition picker) ── */
#mt-detail {
  flex: 1; overflow-y: auto; overflow-x: hidden;
  padding: 10px 14px 8px;
  display: flex; flex-direction: column; gap: 8px;
}
.mt-detail-empty {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 8px; color: var(--muted); text-align: center;
}
.mt-detail-empty i { font-size: 36px; opacity: .3; }
.mt-detail-empty p { font-size: 11px; line-height: 1.5; max-width: 240px; }

/* Transition connector detail */
#mt-conn-detail { display: none; }
#mt-conn-detail.open { display: block; }

.mt-detail-title {
  font-size: 11px; font-weight: 700; color: var(--acc);
  margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}

/* Transition type grid */
.mt-transition-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
  gap: 5px; margin-bottom: 12px;
}
.mt-trans-card {
  padding: 6px 8px; border-radius: 6px;
  border: 1px solid var(--border); background: var(--panel);
  font-size: 9px; font-weight: 700; text-align: center;
  cursor: pointer; color: var(--muted); letter-spacing: .04em;
  text-transform: uppercase; line-height: 1.4;
  transition: border-color .12s, background .12s, color .12s;
}
.mt-trans-card:hover { border-color: var(--acc); color: var(--acc); }
.mt-trans-card.sel { border-color: var(--acc); background: rgba(232,184,75,.1); color: var(--acc); }
.mt-trans-card-icon { font-size: 14px; display: block; margin-bottom: 3px; }

/* Transition family labels */
.mt-family-label {
  font-size: 9px; font-weight: 800; letter-spacing: .1em;
  text-transform: uppercase; color: var(--muted); margin: 8px 0 4px;
}

/* Props rows — used in both scroll area and fixed params panel */
.mt-pg { margin-bottom: 6px; }
.mt-pl { font-size: 9px; text-transform: uppercase; letter-spacing: .08em;
         color: var(--muted); margin-bottom: 4px; font-weight: 700; }
.mt-pr { display: flex; gap: 6px; margin-bottom: 4px; align-items: center; }
.mt-pr label { font-size: 10px; color: var(--muted); width: 52px; flex-shrink: 0; }
.mt-in {
  flex: 1; background: var(--panel); border: 1px solid var(--border);
  border-radius: 5px; color: var(--text); font-size: 11px;
  padding: 4px 7px; outline: none; font-family: var(--mono);
}
.mt-in:focus { border-color: var(--acc); }
.mt-in[type=range] { padding: 0; cursor: pointer; accent-color: var(--acc); }
.mt-in[type=number] { -webkit-user-select: text; user-select: text; touch-action: auto; }

/* Slot detail */
#mt-slot-detail { display: none; }
#mt-slot-detail.open { display: block; }

/* ════════════════════════════════════════════════════════════
   FIXED PARAMS PANEL — always visible at bottom of viewport
   ════════════════════════════════════════════════════════════ */
#mt-params-panel {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  height: var(--params-h);
  background: var(--surf);
  border-top: 2px solid var(--border);
  z-index: 300;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: border-color .2s;
}
#mt-params-panel.conn-active { border-top-color: var(--acc); }
#mt-params-panel.slot-active { border-top-color: var(--acc3); }

#mt-params-header {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 12px 4px;
  flex-shrink: 0;
  border-bottom: 1px solid var(--border);
  background: var(--panel);
}
#mt-params-header-title {
  font-size: 10px; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: .08em;
  flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
#mt-params-header.conn-active #mt-params-header-title { color: var(--acc); }
#mt-params-header.slot-active #mt-params-header-title { color: var(--acc3); }

#mt-params-body {
  flex: 1; overflow-y: auto; overflow-x: hidden;
  padding: 6px 12px 4px;
  display: flex; flex-direction: column; gap: 0;
}
#mt-params-body::-webkit-scrollbar { width: 3px; }
#mt-params-body::-webkit-scrollbar-thumb { background: var(--border); }

/* Params for connector — two-column grid on wide, single on narrow */
#mt-params-conn { display: none; }
#mt-params-conn.active { display: block; }
#mt-params-slot { display: none; }
#mt-params-slot.active { display: block; }
#mt-params-empty { display: block; }
#mt-params-empty.hidden { display: none; }

.mt-params-empty-msg {
  padding: 8px 0; color: var(--muted); font-size: 11px; text-align: center;
}

.mt-params-row {
  display: flex; gap: 10px; align-items: flex-start;
}
.mt-params-col { flex: 1; min-width: 0; }

.mt-params-actions {
  display: flex; gap: 6px; padding-top: 4px; flex-shrink: 0; align-items: center;
}

/* Render + Browse buttons in params header */
.mt-params-btn {
  padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border);
  background: var(--panel2); color: var(--text); font-size: 11px; font-weight: 700;
  cursor: pointer; white-space: nowrap; flex-shrink: 0;
  transition: background .12s, border-color .12s, color .12s;
}
.mt-params-btn:hover { background: var(--border); border-color: var(--acc); color: var(--acc); }
.mt-params-btn.acc { background: var(--acc); border-color: var(--acc); color: #0b0d12; }
.mt-params-btn.acc:hover { background: #d4a53c; }
.mt-params-btn.browse { border-color: var(--acc4); color: var(--acc4); }
.mt-params-btn.browse:hover { background: rgba(155,127,232,.15); }
.mt-params-btn.save { border-color: var(--acc3); color: var(--acc3); }
.mt-params-btn.save:hover { background: rgba(94,196,255,.12); }

/* ── PREVIEW MODAL ── */
.mt-mbg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.82); z-index: 9000;
  align-items: center; justify-content: center;
}
.mt-mbg.open { display: flex; }
.mt-modal {
  background: var(--surf); border: 1px solid var(--border);
  border-radius: var(--r); width: 92%; max-width: 500px;
  max-height: 90vh; display: flex; flex-direction: column;
  overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.mt-modal.wide { max-width: 640px; }
.mt-mh {
  padding: 11px 14px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
}
.mt-mh h3 { font-size: 13px; font-weight: 700; }
.mt-mx { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; line-height: 1; }
.mt-mx:hover { color: var(--text); }
.mt-mb { flex: 1; overflow-y: auto; padding: 14px; }
.mt-mf { padding: 10px 14px; border-top: 1px solid var(--border);
          display: flex; gap: 8px; justify-content: flex-end; flex-shrink: 0; }

/* Browser list (project/job browsers) */
.mt-browser-search {
  display: flex; gap: 6px; margin-bottom: 10px;
}
.mt-browser-search input {
  flex: 1; background: var(--panel); border: 1px solid var(--border);
  border-radius: 5px; color: var(--text); font-size: 11px;
  padding: 5px 8px; outline: none; font-family: var(--mono);
  -webkit-user-select: text; user-select: text; touch-action: auto;
}
.mt-browser-search input:focus { border-color: var(--acc); }
.mt-brow-item {
  display: flex; align-items: center; gap: 9px;
  padding: 7px 9px; border: 1px solid var(--border);
  border-radius: 6px; margin-bottom: 6px;
  cursor: pointer; background: var(--panel);
  transition: border-color .12s, background .12s;
}
.mt-brow-item:hover { border-color: var(--acc); background: rgba(232,184,75,.05); }
.mt-brow-thumb {
  width: 48px; height: 48px; object-fit: cover;
  border-radius: 5px; background: var(--bg); flex-shrink: 0;
  border: 1px solid var(--border);
}
.mt-brow-thumb-ph {
  width: 48px; height: 48px; border-radius: 5px;
  background: var(--bg); flex-shrink: 0;
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; color: var(--muted);
}
.mt-brow-info strong { font-size: 11px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mt-brow-info small  { font-size: 9px; color: var(--muted); }
.mt-pagination {
  display: flex; align-items: center; gap: 6px; justify-content: center;
  padding: 8px 0 0; border-top: 1px solid var(--border); margin-top: 8px;
}
.mt-pagination button {
  padding: 4px 10px; border-radius: 5px; border: 1px solid var(--border);
  background: var(--panel); color: var(--text); font-size: 11px; cursor: pointer;
}
.mt-pagination button:hover { border-color: var(--acc); color: var(--acc); }
.mt-pagination button:disabled { opacity: .35; cursor: default; }
.mt-pg-idx {
  width: 42px; text-align: center;
  background: var(--panel); border: 1px solid var(--border);
  border-radius: 5px; color: var(--text); font-size: 11px;
  padding: 3px 5px; outline: none; font-family: var(--mono);
  -webkit-user-select: text; user-select: text; touch-action: auto;
}
.mt-pg-idx:focus { border-color: var(--acc); }
.mt-type-choice { display: flex; gap: 8px; margin-bottom: 12px; }
.mt-type-btn {
  flex: 1; padding: 8px; border-radius: 6px; border: 1px solid var(--border);
  background: var(--panel); color: var(--text); font-size: 11px; font-weight: 700;
  cursor: pointer; text-align: center;
  transition: border-color .12s, background .12s, color .12s;
}
.mt-type-btn.active { border-color: var(--acc); background: rgba(232,184,75,.1); color: var(--acc); }

/* Project browser */
.mt-proj-item {
  display: flex; align-items: flex-start; gap: 10px; padding: 9px 10px;
  border: 1px solid var(--border); border-radius: 7px; margin-bottom: 7px;
  cursor: pointer; background: var(--panel);
  transition: border-color .12s, background .12s;
}
.mt-proj-item:hover { border-color: var(--acc); background: rgba(232,184,75,.05); }
.mt-proj-item strong { font-size: 12px; display: block; }
.mt-proj-item small  { font-size: 10px; color: var(--muted); }

/* Render jobs panel */
.mt-job {
  padding: 8px 10px; border: 1px solid var(--border);
  border-radius: 6px; margin-bottom: 6px; font-size: 11px;
}
.mt-job .job-st {
  font-weight: 700; font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-left: 6px;
}
.mt-job .job-st.queued     { background: rgba(94,196,255,.15); color: var(--acc3); }
.mt-job .job-st.processing { background: rgba(232,184,75,.2); color: var(--acc); animation: pulse .8s infinite; }
.mt-job .job-st.completed  { background: rgba(76,219,140,.15); color: #4cdb8c; }
.mt-job .job-st.failed     { background: rgba(255,95,109,.15); color: var(--acc2); }

/* ── PREVIEW MODAL — always on top of any other modal ── */
#modal-preview {
  z-index: 9500;  /* above browse demos (9000) */
}

/* Preview video */
.mt-prev-vid { width: 100%; border-radius: 7px; background: #000; display: block; max-height: 280px; }

/* ─────────────────────────────────────────────────────────────
   BROWSE TRANSITION RENDERS MODAL
   ───────────────────────────────────────────────────────────── */
#modal-browse-demos .mt-modal { max-width: 560px; }

/* Tab buttons */
.demo-tab-btn {
  flex: 1; padding: 9px 10px; font-size: 11px; font-weight: 700;
  background: transparent; border: none; border-bottom: 2px solid transparent;
  color: var(--muted); cursor: pointer; transition: color .12s, border-color .12s;
  letter-spacing: .04em;
}
.demo-tab-btn:hover { color: var(--text); }
.demo-tab-btn.active { color: var(--acc); border-bottom-color: var(--acc); }

.demo-search-bar {
  display: flex; gap: 6px; margin-bottom: 10px; align-items: center;
}
.demo-search-bar input {
  flex: 1; background: var(--panel); border: 1px solid var(--border);
  border-radius: 5px; color: var(--text); font-size: 11px;
  padding: 5px 8px; outline: none; font-family: var(--mono);
  -webkit-user-select: text; user-select: text; touch-action: auto;
}
.demo-search-bar input:focus { border-color: var(--acc); }

.demo-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border: 1px solid var(--border);
  border-radius: 7px; margin-bottom: 7px;
  background: var(--panel);
  transition: border-color .12s;
}
.demo-item.is-assigned { border-color: rgba(232,184,75,.4); }
.demo-item.is-primary  { border-color: var(--acc); background: rgba(232,184,75,.06); }

.demo-thumb {
  width: 72px; height: 48px; object-fit: cover; flex-shrink: 0;
  border-radius: 5px; border: 1px solid var(--border); background: #000;
  cursor: pointer;
}
.demo-thumb-ph {
  width: 72px; height: 48px; flex-shrink: 0;
  border-radius: 5px; border: 1px solid var(--border); background: var(--bg);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; color: var(--muted); cursor: pointer;
}
.demo-info { flex: 1; min-width: 0; }
.demo-info strong {
  font-size: 11px; display: block;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.demo-info small { font-size: 9px; color: var(--muted); }
.demo-badge {
  font-size: 8px; font-weight: 800; padding: 1px 5px; border-radius: 3px;
  margin-left: 4px; vertical-align: middle;
}
.demo-badge.primary { background: rgba(232,184,75,.25); color: var(--acc); }
.demo-badge.assigned { background: rgba(94,196,255,.15); color: var(--acc3); }

.demo-actions { display: flex; flex-direction: column; gap: 4px; flex-shrink: 0; }
.demo-action-btn {
  padding: 4px 8px; border-radius: 5px; font-size: 10px; font-weight: 700;
  border: 1px solid var(--border); background: var(--panel2); color: var(--text);
  cursor: pointer; white-space: nowrap;
  transition: background .12s, border-color .12s, color .12s;
}
.demo-action-btn:hover { border-color: var(--acc); color: var(--acc); }
.demo-action-btn.set-primary { border-color: var(--acc); color: var(--acc); }
.demo-action-btn.set-primary:hover { background: var(--acc); color: #000; }
.demo-action-btn.unassign { border-color: var(--acc2); color: var(--acc2); }
.demo-action-btn.unassign:hover { background: rgba(255,95,109,.15); }
.demo-action-btn.play-btn { border-color: #4cdb8c; color: #4cdb8c; }
.demo-action-btn.play-btn:hover { background: rgba(76,219,140,.15); }
.demo-action-btn.assign-btn { border-color: var(--acc3); color: var(--acc3); }
.demo-action-btn.assign-btn:hover { background: rgba(94,196,255,.12); }

.demo-no-renders {
  text-align: center; padding: 20px; color: var(--muted); font-size: 11px; line-height: 1.6;
}
.demo-no-renders i { font-size: 28px; display: block; margin-bottom: 8px; opacity: .3; }

.demo-pagination {
  display: flex; align-items: center; gap: 6px; justify-content: center;
  padding: 8px 0 0; border-top: 1px solid var(--border); margin-top: 8px;
}
.demo-pagination button {
  padding: 4px 12px; border-radius: 5px; border: 1px solid var(--border);
  background: var(--panel); color: var(--text); font-size: 11px; cursor: pointer;
  transition: border-color .12s, color .12s;
}
.demo-pagination button:hover { border-color: var(--acc); color: var(--acc); }
.demo-pagination button:disabled { opacity: .35; cursor: default; }
.demo-pg-info {
  font-size: 10px; color: var(--muted); font-family: var(--mono); white-space: nowrap;
}

/* ── TOAST ── */
#mt-toast {
  position: fixed; bottom: calc(var(--params-h) + 10px); left: 50%;
  transform: translateX(-50%) translateY(8px);
  background: var(--panel); border: 1px solid var(--acc); color: var(--text);
  padding: 8px 16px; border-radius: 7px; font-size: 12px;
  z-index: 99999; opacity: 0; pointer-events: none;
  transition: opacity .2s, transform .2s; white-space: nowrap;
}
#mt-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
#mt-toast.err  { border-color: var(--acc2); }

/* Scrollbars */
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: var(--surf); }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

/* ── HAMBURGER / SIDEBAR (projects flyout) ── */
:root { --sidebar-w: 260px; }
#mt-sidebar {
  position: absolute; top: 0; bottom: 0; left: 0;
  width: var(--sidebar-w);
  background: var(--surf); border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  z-index: 150;
  transform: translateX(calc(-1 * var(--sidebar-w)));
  transition: transform .25s cubic-bezier(.4,0,.2,1);
  overflow: hidden;
}
#mt-sidebar.open { transform: translateX(0); }
#mt-hamburger {
  position: absolute; top: 8px; left: 12px; z-index: 160;
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
  background: var(--panel); border: 1px solid var(--border);
  border-radius: 8px; cursor: pointer; color: var(--text); font-size: 16px;
  flex-shrink: 0; transition: background .12s, color .12s;
}
#mt-hamburger:hover { background: var(--border); color: var(--acc); }
#mt-sidebar-header {
  padding: 12px 14px; border-bottom: 1px solid var(--border);
  font-size: 11px; font-weight: 700; flex-shrink: 0;
  display: flex; align-items: center; justify-content: space-between;
}
#mt-sidebar-body { flex: 1; overflow-y: auto; padding: 10px; }
#mt-sidebar-new {
  padding: 10px; border-top: 1px solid var(--border); flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════════════
   VIDEO PICKER MODAL — four-tab fly-out (ported from editorial)
   ═══════════════════════════════════════════════════════════ */

/* Picker modal shell reuses .mt-mbg / .mt-modal.wide */
.picker-box {
  background: var(--surf); width: 95%; max-width: 1100px; height: 88vh;
  border-radius: var(--r); display: flex; flex-direction: column;
  position: relative; overflow: hidden; border: 1px solid var(--border);
}
.picker-head {
  padding: 10px 14px; padding-left: 110px;
  border-bottom: 1px solid var(--border);
  display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
  position: relative; background: var(--panel); flex-shrink: 0;
}
.picker-head h3 {
  margin: 0 10px 0 0; font-size: 12px; font-weight: 700;
  color: var(--acc); letter-spacing: 1.5px; text-transform: uppercase;
  font-family: var(--mono);
}
.picker-head-close {
  margin-left: auto; padding: 5px 12px;
  background: transparent; border: 1px solid var(--border);
  border-radius: var(--r); color: var(--muted);
  font-family: var(--mono); font-size: 11px; cursor: pointer;
  transition: all .15s;
}
.picker-head-close:hover { border-color: var(--acc2); color: var(--acc2); background: rgba(255,95,109,.1); }

/* Hamburger toggle — mobile only */
.picker-tree-toggle {
  position: absolute; left: 60px; top: 50%; transform: translateY(-50%);
  width: 36px; height: 36px; background: transparent;
  border: 1px solid var(--border); border-radius: 5px; color: var(--text);
  font-size: 1.1rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  -webkit-tap-highlight-color: transparent; z-index: 10;
}
.picker-tree-toggle:active { background: rgba(255,255,255,.07); }
@media (min-width: 700px) { .picker-tree-toggle { display: none; } }

.picker-body { flex: 1; overflow: hidden; display: flex; min-height: 0; position: relative; }

/* Backdrop — mobile only */
.picker-tree-backdrop {
  display: none; position: absolute; inset: 0;
  background: rgba(0,0,0,.5); z-index: 20;
}
.picker-tree-backdrop.active { display: block; }
@media (min-width: 700px) { .picker-tree-backdrop { display: none !important; } }

/* Left tree/filter panel */
.picker-tree-panel {
  width: 240px; flex-shrink: 0;
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column; overflow: hidden;
}
@media (max-width: 699px) {
  .picker-tree-panel {
    position: absolute; top: 0; left: 0; bottom: 0; z-index: 30;
    background: var(--surf); box-shadow: 4px 0 20px rgba(0,0,0,.5);
    transform: translateX(-100%); transition: transform .22s ease;
    width: 80%; max-width: 280px;
  }
  .picker-tree-panel.open { transform: translateX(0); }
}

.picker-tree-header {
  padding: 7px 8px; font-size: 11px; font-weight: 600; color: var(--muted);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  gap: 5px; flex-shrink: 0;
}
.picker-mode-btns { display: flex; gap: 3px; flex-shrink: 0; }
.picker-mode-btn {
  padding: 2px 7px; font-size: 10px; font-weight: 600; border-radius: 3px;
  border: 1px solid var(--border); background: transparent; color: var(--muted);
  cursor: pointer; white-space: nowrap; transition: all .15s;
  -webkit-tap-highlight-color: transparent;
}
.picker-mode-btn.active { background: rgba(232,184,75,.15); border-color: var(--acc); color: var(--acc); }
.picker-mode-btn:active { opacity: .7; }

.picker-tree-clear {
  font-size: 10px; padding: 2px 6px;
  border: 1px solid var(--border); background: transparent;
  color: var(--muted); border-radius: 3px; cursor: pointer; white-space: nowrap;
}
.picker-tree-clear:hover { border-color: var(--acc); color: var(--acc); }

/* jsTree scroll area */
.picker-tree-scroll {
  flex: 1; overflow-y: auto; padding: 6px 4px; background: var(--bg);
}
.picker-tree-scroll::-webkit-scrollbar { width: 3px; }
.picker-tree-scroll::-webkit-scrollbar-thumb { background: var(--border); }

/* jsTree dark overrides inside picker */
.picker-tree-scroll .jstree-default .jstree-anchor { color: var(--text) !important; line-height: 26px; height: 26px; }
.picker-tree-scroll .jstree-default .jstree-hovered { background: rgba(232,184,75,.1) !important; border-radius: 3px; }
.picker-tree-scroll .jstree-default .jstree-clicked { background: rgba(232,184,75,.22) !important; color: var(--acc) !important; border-radius: 3px; }
.picker-tree-scroll .jstree-default { background: transparent !important; }

/* Sequence panel */
.picker-seq-panel { flex: 1; overflow-y: auto; padding: 8px 6px; background: var(--bg); display: none; }
.picker-seq-panel.active { display: block; }
.picker-seq-item {
  padding: 8px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;
  color: var(--text); border: 1px solid transparent; margin-bottom: 3px; line-height: 1.3;
  -webkit-tap-highlight-color: transparent; transition: background .1s, border-color .1s;
}
.picker-seq-item:hover  { background: rgba(232,184,75,.08); border-color: rgba(232,184,75,.2); }
.picker-seq-item.active { background: rgba(232,184,75,.15); border-color: var(--acc); color: var(--acc); }
.picker-seq-item .seq-item-id   { font-size: 9px; color: var(--muted); font-family: monospace; margin-bottom: 2px; }
.picker-seq-item .seq-item-name { font-weight: 600; }
.picker-seq-item .seq-item-desc { font-size: 10px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Fuzz panel */
.picker-fuzz-panel { flex: 1; overflow-y: auto; padding: 8px 6px; background: var(--bg); display: none; }
.picker-fuzz-panel.active { display: block; }
.picker-fuzz-search { padding: 4px 6px 6px; flex-shrink: 0; }
.picker-fuzz-search-input {
  width: 100%; padding: 5px 8px; background: rgba(255,255,255,.04);
  border: 1px solid var(--border); border-radius: 4px;
  color: var(--text); font-size: 11px; font-family: inherit;
}
.picker-fuzz-search-input:focus { outline: none; border-color: var(--acc); }
.picker-fuzz-item {
  padding: 7px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;
  color: var(--text); border: 1px solid transparent; margin-bottom: 3px; line-height: 1.3;
  -webkit-tap-highlight-color: transparent; transition: background .1s, border-color .1s;
}
.picker-fuzz-item:hover  { background: rgba(232,184,75,.08); border-color: rgba(232,184,75,.2); }
.picker-fuzz-item.active { background: rgba(232,184,75,.15); border-color: var(--acc); color: var(--acc); }
.picker-fuzz-item .fuzz-item-id     { font-size: 9px; color: var(--muted); font-family: monospace; margin-bottom: 1px; }
.picker-fuzz-item .fuzz-item-name   { font-weight: 600; }
.picker-fuzz-item .fuzz-item-type   { font-size: 9px; color: var(--muted); margin-top: 1px; }
.picker-fuzz-item .fuzz-item-status {
  display: inline-block; padding: 1px 4px; border-radius: 2px;
  font-size: 8px; font-family: monospace; text-transform: uppercase;
  letter-spacing: .5px; margin-top: 2px;
}
.fuzz-item-status.promoted  { color: var(--acc4); background: rgba(155,127,232,.12); border: 1px solid rgba(155,127,232,.25); }
.fuzz-item-status.canonized { color: #4cdb8c; background: rgba(76,219,140,.1); border: 1px solid rgba(76,219,140,.25); }

/* Storyboard panel */
.picker-storyboard-panel { flex: 1; overflow-y: auto; padding: 8px 6px; background: var(--bg); display: none; }
.picker-storyboard-panel.active { display: block; }
.picker-storyboard-item {
  padding: 8px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;
  color: var(--text); border: 1px solid transparent; margin-bottom: 3px; line-height: 1.3;
  -webkit-tap-highlight-color: transparent; transition: background .1s, border-color .1s;
}
.picker-storyboard-item:hover  { background: rgba(232,184,75,.08); border-color: rgba(232,184,75,.2); }
.picker-storyboard-item.active { background: rgba(232,184,75,.15); border-color: var(--acc); color: var(--acc); }
.picker-storyboard-item .sb-item-id   { font-size: 9px; color: var(--muted); font-family: monospace; margin-bottom: 2px; }
.picker-storyboard-item .sb-item-name { font-weight: 600; }
.picker-storyboard-item .sb-item-desc { font-size: 10px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Picker pagination (fuzz / storyboard) */
.picker-fuzz-pg {
  flex-shrink: 0; border-top: 1px solid var(--border); padding: 5px 6px;
  display: flex; align-items: center; gap: 4px; background: var(--bg);
}
.picker-fuzz-pg-btn {
  width: 28px; height: 28px; background: transparent;
  border: 1px solid var(--border); border-radius: 3px; color: var(--muted);
  font-size: 14px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  -webkit-tap-highlight-color: transparent;
}
.picker-fuzz-pg-btn:disabled { opacity: .3; pointer-events: none; }
.picker-fuzz-pg-input {
  width: 34px; text-align: center; background: rgba(255,255,255,.05);
  border: 1px solid var(--border); border-radius: 3px; color: var(--acc);
  font-family: monospace; font-size: 11px; font-weight: 700;
  padding: 3px 2px; height: 28px; -moz-appearance: textfield;
}
.picker-fuzz-pg-input::-webkit-outer-spin-button,
.picker-fuzz-pg-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.picker-fuzz-pg-input:focus { outline: none; border-color: var(--acc); }
.picker-fuzz-pg-of { font-size: 9px; color: var(--muted); white-space: nowrap; flex: 1; text-align: center; }

/* Right videos panel */
.picker-videos-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
.picker-search-bar { padding: 8px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.picker-search-bar input {
  width: 100%; padding: 6px 9px; background: var(--panel); border: 1px solid var(--border);
  border-radius: 5px; color: var(--text); font-size: 11px; font-family: var(--mono); outline: none;
  -webkit-user-select: text; user-select: text; touch-action: auto;
}
.picker-search-bar input:focus { border-color: var(--acc); }
.picker-videos-scroll { flex: 1; overflow-y: auto; padding: 10px; }
.picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }

/* Video item card */
.vid-item {
  cursor: pointer; border: 2px solid transparent; border-radius: 6px;
  overflow: hidden; background: var(--panel); transition: transform .15s; position: relative;
}
.vid-item:hover { border-color: var(--acc); transform: scale(1.02); }
.vid-item-btns {
  position: absolute; bottom: 36px; right: 4px;
  display: flex; flex-direction: column; gap: 4px;
  opacity: 0; transition: opacity .15s; pointer-events: none;
}
.vid-item:hover .vid-item-btns { opacity: 1; pointer-events: auto; }
@media (hover: none) { .vid-item-btns { opacity: 1; pointer-events: auto; } }
.vid-item-btn {
  width: 28px; height: 28px; border-radius: 4px; border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; line-height: 1; -webkit-tap-highlight-color: transparent;
}
.vid-item-btn-add { background: rgba(76,219,140,.85); color: #000; }
.vid-item-btn-add:active { background: rgba(76,219,140,1); }
.vid-img { width: 100%; height: 80px; object-fit: cover; background: #000; display: block; }
.vid-info { padding: 5px 7px; }
.vid-name { font-size: 10px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vid-sub  { font-size: 9px; color: var(--muted); }

/* Picker footer pagination */
.picker-footer {
  padding: 8px 12px; border-top: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
  flex-shrink: 0; gap: 8px;
}
.picker-footer button {
  padding: 4px 10px; border-radius: 5px; border: 1px solid var(--border);
  background: var(--panel); color: var(--text); font-size: 11px; cursor: pointer;
}
.picker-footer button:hover { border-color: var(--acc); color: var(--acc); }
.picker-footer button:disabled { opacity: .35; cursor: default; }
.picker-page-jump {
  display: flex; align-items: center; gap: 5px;
  font-size: 10px; color: var(--muted); font-family: var(--mono);
}
.picker-page-input {
  width: 42px; text-align: center; padding: 3px 5px;
  background: var(--bg); border: 1px solid var(--border);
  color: var(--text); border-radius: 4px; font-size: 11px; font-family: var(--mono);
  -moz-appearance: textfield; -webkit-user-select: text; user-select: text; touch-action: auto;
}
.picker-page-input::-webkit-outer-spin-button,
.picker-page-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.picker-page-input:focus { outline: none; border-color: var(--acc); }
</style>

<!-- ── SIDEBAR ── -->
<div id="mt-sidebar">
  <div id="mt-sidebar-header">
    <span>Projects</span>
    <button class="mt-btn" id="btn-sb-new" title="New project">
      <i class="fa fa-plus"></i>
    </button>
  </div>
  <div id="mt-sidebar-body">
    <p style="font-size:11px;color:var(--muted)">Loading…</p>
  </div>
</div>
<div id="mt-hamburger"><i class="fa fa-bars"></i></div>

<!-- ── MAIN ── -->
<div id="mt-root">
  <!-- Top Bar -->
  <div id="mt-topbar">
    <div class="mt-toprow">
      <div class="mt-toprow-ph"></div>
      <span class="mt-projname" id="mt-projname-lbl">
        <?= $projectId > 0 ? htmlspecialchars($project['name']) : '— no project —' ?>
      </span>
      <div class="mt-sep"></div>
      <button class="mt-btn" id="btn-open-proj" title="Open project"><i class="fa fa-folder-open"></i></button>
      <button class="mt-btn" id="btn-edit-proj" title="Edit project settings"><i class="fa fa-gear"></i></button>
      <div class="mt-sep"></div>
      <button class="mt-btn" id="btn-add-slot" title="Add media slot"><i class="fa fa-plus"></i> Slot</button>
      <div class="mt-sep"></div>
      <button class="mt-btn accent3" id="btn-jobs" title="Render jobs"><i class="fa fa-film"></i></button>
    </div>
  </div>

  <!-- Status bar -->
  <div id="mt-statusbar">
    <div class="pyapi-dot" id="pyapi-dot"></div>
    <span id="pyapi-status-txt">PyAPI: checking…</span>
    <span class="mt-sep" style="width:1px;height:12px;background:var(--border);"></span>
    <span id="mt-proj-meta"><?= $projectId > 0 ? htmlspecialchars("{$project['canvas_w']}×{$project['canvas_h']} · {$project['fps']}fps") : '' ?></span>
  </div>

  <!-- Workspace -->
  <div id="mt-workspace">
    <!-- Chain track -->
    <div id="mt-chain-wrap">
      <div id="mt-chain">
        <div class="mt-add-slot" id="btn-chain-add" title="Add slot">
          <i class="fa fa-plus"></i>
        </div>
      </div>
    </div>

    <!-- Detail panel (scrollable — transition grid + slot settings) -->
    <div id="mt-detail" style="height:360px;">
      <div class="mt-detail-empty" id="mt-detail-empty">
        <i class="fa fa-film"></i>
        <p>Select a slot or a transition connector to configure it.<br>
           Press <strong>+ Slot</strong> to add your first media asset.</p>
      </div>

      <!-- Connector / Transition type picker (scrollable) -->
      <div id="mt-conn-detail" style="padding-bottom:50px;">
        <div class="mt-detail-title">
          <i class="fa fa-shuffle"></i>
          <span id="mt-conn-detail-title">Transition</span>
        </div>
        <div id="mt-transition-grids-container"></div>
      </div>

      <!-- Slot detail (scrollable) -->
      <div id="mt-slot-detail">
        <div class="mt-detail-title">
          <i class="fa fa-photo-film"></i>
          <span id="mt-slot-detail-title">Slot</span>
        </div>
        <div class="mt-pg">
          <div class="mt-pl">Label</div>
          <div class="mt-pr">
            <input type="text" class="mt-in" id="sd-label" placeholder="Optional label"
                   style="-webkit-user-select:text;user-select:text;touch-action:auto;">
          </div>
        </div>
        <div class="mt-pg">
          <div class="mt-pl">Trim (seconds)</div>
          <div class="mt-pr">
            <label>Start</label>
            <input type="number" class="mt-in" id="sd-trim-s" value="0" min="0" step="0.1"
                   style="-webkit-user-select:text;user-select:text;touch-action:auto;">
          </div>
          <div class="mt-pr">
            <label>End</label>
            <input type="number" class="mt-in" id="sd-trim-e" placeholder="(full)"
                   style="-webkit-user-select:text;user-select:text;touch-action:auto;">
          </div>
          <div class="mt-pr">
            <label>Speed</label>
            <input type="range" class="mt-in" id="sd-spd-r" min="0.1" max="3.0" step="0.05" value="1.0">
            <span id="sd-spd-v" style="font-size:10px;font-family:var(--mono);flex-shrink:0;width:30px;text-align:right;">1.0×</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         FIXED PARAMS PANEL — always at viewport bottom
         ════════════════════════════════════════════════════════════ -->
    <div id="mt-params-panel" style="height:150px;">
      <div id="mt-params-header">
        <span id="mt-params-header-title">Parameters</span>
        <!-- Buttons injected by JS depending on mode -->
        <div id="mt-params-header-btns" style="display:flex;gap:6px;"></div>
      </div>
      <div id="mt-params-body">

        <!-- Empty state -->
        <div id="mt-params-empty">
          <div class="mt-params-empty-msg">Select a transition connector or a slot above to edit parameters.</div>
        </div>

        <!-- Connector params -->
        <div id="mt-params-conn">
          <div class="mt-params-row">
            <div class="mt-params-col">
              <div class="mt-pr">
                <label>Duration</label>
                <input type="range" class="mt-in" id="tc-dur-r" min="2" max="90" step="1" value="24">
                <input type="number" class="mt-in" id="tc-dur" min="2" max="90" value="24" style="width:48px;flex:none;-webkit-user-select:text;user-select:text;touch-action:auto;">
                <span style="font-size:10px;color:var(--muted);flex-shrink:0;">fr</span>
              </div>
              <div class="mt-pr">
                <label>Intensity</label>
                <input type="range" class="mt-in" id="tc-int-r" min="0.1" max="3.0" step="0.05" value="1.0">
                <span id="tc-int-v" style="font-size:10px;font-family:var(--mono);flex-shrink:0;width:30px;text-align:right;">1.0</span>
              </div>
            </div>
            <div class="mt-params-col">
              <div class="mt-pr">
                <label>Easing</label>
                <select class="mt-in" id="tc-easing" style="-webkit-user-select:auto;user-select:auto;">
                  <option value="ease_in_out_cubic">Ease In/Out Cubic</option>
                  <option value="ease_in_cubic">Ease In</option>
                  <option value="ease_out_cubic">Ease Out</option>
                  <option value="ease_in_out_quart">Ease In/Out Quart</option>
                  <option value="ease_overshoot">Overshoot</option>
                  <option value="linear">Linear</option>
                </select>
              </div>
              <div class="mt-pr">
                <label>Seed</label>
                <input type="number" class="mt-in" id="tc-seed" value="42" style="-webkit-user-select:text;user-select:text;touch-action:auto;">
              </div>
            </div>
          </div>
        </div>

        <!-- Slot params (speed is in scroll area, just show a summary here) -->
        <div id="mt-params-slot">
          <div class="mt-pr" style="margin-top:4px;">
            <label>Trim start</label>
            <input type="number" class="mt-in" id="sd2-trim-s" value="0" min="0" step="0.1"
                   style="-webkit-user-select:text;user-select:text;touch-action:auto;">
            <label style="margin-left:6px;">End</label>
            <input type="number" class="mt-in" id="sd2-trim-e" placeholder="(full)"
                   style="-webkit-user-select:text;user-select:text;touch-action:auto;">
          </div>
          <div class="mt-pr">
            <label>Speed</label>
            <input type="range" class="mt-in" id="sd2-spd-r" min="0.1" max="3.0" step="0.05" value="1.0">
            <span id="sd2-spd-v" style="font-size:10px;font-family:var(--mono);flex-shrink:0;width:30px;text-align:right;">1.0×</span>
          </div>
        </div>

      </div>
    </div>
    <!-- /fixed params panel -->

  </div>
</div>

<div id="mt-toast"></div>

<!-- ─── MODALS ─── -->

<!-- Project browser modal -->
<div id="modal-projects" class="mt-mbg">
  <div class="mt-modal">
    <div class="mt-mh"><h3>Projects</h3><button class="mt-mx" data-close="modal-projects">×</button></div>
    <div class="mt-mb">
      <div class="mt-browser-search">
        <input type="text" id="pb-search" placeholder="Search projects…" autocomplete="off">
        <button class="mt-btn acc" id="pb-new-btn"><i class="fa fa-plus"></i></button>
      </div>
      <div id="pb-create-form" style="display:none;background:rgba(232,184,75,.05);border:1px solid rgba(232,184,75,.2);border-radius:6px;padding:10px;margin-bottom:10px;">
        <div class="mt-pl">New Project</div>
        <div class="mt-pr" style="margin-top:6px;">
          <label>Name</label>
          <input type="text" id="pb-new-name" class="mt-in" placeholder="Project name"
                 style="-webkit-user-select:text;user-select:text;touch-action:auto;">
        </div>
        <div class="mt-pr">
          <label>Width</label>
          <input type="number" id="pb-new-w" class="mt-in" value="1080"
                 style="-webkit-user-select:text;user-select:text;touch-action:auto;">
        </div>
        <div class="mt-pr">
          <label>Height</label>
          <input type="number" id="pb-new-h" class="mt-in" value="1080"
                 style="-webkit-user-select:text;user-select:text;touch-action:auto;">
        </div>
        <div class="mt-pr">
          <label>FPS</label>
          <input type="number" id="pb-new-fps" class="mt-in" value="30"
                 style="-webkit-user-select:text;user-select:text;touch-action:auto;">
        </div>
        <div style="display:flex;gap:6px;margin-top:8px;justify-content:flex-end;">
          <button class="mt-btn" id="pb-create-cancel">Cancel</button>
          <button class="mt-btn acc" id="pb-create-save"><i class="fa fa-floppy-disk"></i> Create</button>
        </div>
      </div>
      <div id="pb-list"><p style="font-size:11px;color:var(--muted)">Loading…</p></div>
      <div class="mt-pagination" id="pb-pagination">
        <button id="pb-prev" disabled><i class="fa fa-chevron-left"></i></button>
        <input type="number" id="pb-page" class="mt-pg-idx" value="1" min="1">
        <span class="mt-pg-total" id="pb-total">/ 1</span>
        <button id="pb-next"><i class="fa fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="mt-mf"><button class="mt-btn" data-close="modal-projects">Close</button></div>
  </div>
</div>

<!-- Edit project settings modal -->
<div id="modal-edit-proj" class="mt-mbg">
  <div class="mt-modal">
    <div class="mt-mh"><h3>Project Settings</h3><button class="mt-mx" data-close="modal-edit-proj">×</button></div>
    <div class="mt-mb">
      <input type="hidden" id="ep-id">
      <div class="mt-pr"><label>Name</label>
        <input type="text" id="ep-name" class="mt-in" style="-webkit-user-select:text;user-select:text;touch-action:auto;">
      </div>
      <div class="mt-pr"><label>Width</label>
        <input type="number" id="ep-w" class="mt-in" value="1080" style="-webkit-user-select:text;user-select:text;touch-action:auto;">
      </div>
      <div class="mt-pr"><label>Height</label>
        <input type="number" id="ep-h" class="mt-in" value="1080" style="-webkit-user-select:text;user-select:text;touch-action:auto;">
      </div>
      <div class="mt-pr"><label>FPS</label>
        <input type="number" id="ep-fps" class="mt-in" value="30" style="-webkit-user-select:text;user-select:text;touch-action:auto;">
      </div>
    </div>
    <div class="mt-mf">
      <button class="mt-btn" data-close="modal-edit-proj">Cancel</button>
      <button class="mt-btn acc" id="btn-ep-save"><i class="fa fa-floppy-disk"></i> Save</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     VIDEO PICKER MODAL — four-tab fly-out
     ═══════════════════════════════════════════════════════ -->
<div id="modal-assign" class="mt-mbg">
  <div class="picker-box">

    <div class="picker-head">
      <button class="picker-tree-toggle" id="picker-tree-toggle" title="Filters">☰</button>
      <h3>Add Slot — Select Video</h3>
      <span id="picker-active-label" style="font-size:11px;color:var(--acc);display:none;"></span>
      <button class="picker-head-close" onclick="closePicker()">Close</button>
    </div>

    <div class="picker-body">
      <!-- Mobile backdrop -->
      <div class="picker-tree-backdrop" id="picker-tree-backdrop" onclick="closePickerTree()"></div>

      <!-- Left filter panel -->
      <div class="picker-tree-panel" id="picker-tree-panel">
        <div class="picker-tree-header">
          <div class="picker-mode-btns">
            <button class="picker-mode-btn active" id="picker-mode-tree"      onclick="switchPickerMode('tree')">🌳</button>
            <button class="picker-mode-btn"        id="picker-mode-seq"       onclick="switchPickerMode('seq')">🎬</button>
            <button class="picker-mode-btn"        id="picker-mode-fuzz"      onclick="switchPickerMode('fuzz')">🧩</button>
            <button class="picker-mode-btn"        id="picker-mode-storyboard" onclick="switchPickerMode('storyboard')">🖼️</button>
          </div>
          <button class="picker-tree-clear" id="picker-tree-clear" style="display:none;" onclick="clearPickerFilter()">All</button>
        </div>

        <!-- Tree mode -->
        <div class="picker-tree-scroll" id="picker-tree-scroll">
          <div id="picker-tree">Loading…</div>
        </div>

        <!-- Sequence mode -->
        <div class="picker-seq-panel" id="picker-seq-panel">
          <div id="picker-seq-list" style="padding:4px 0;"></div>
        </div>

        <!-- Fuzz mode -->
        <div class="picker-fuzz-panel" id="picker-fuzz-panel">
          <div class="picker-fuzz-search">
            <input type="search" id="picker-fuzz-search" class="picker-fuzz-search-input" placeholder="Search candidates…" autocomplete="off">
          </div>
          <div id="picker-fuzz-list" style="padding:4px 0;"></div>
          <div class="picker-fuzz-pg" id="picker-fuzz-pg" style="display:none;">
            <button class="picker-fuzz-pg-btn" id="picker-fuzz-prev" disabled>‹</button>
            <input type="number" class="picker-fuzz-pg-input" id="picker-fuzz-page-input" value="1" min="1">
            <span class="picker-fuzz-pg-of" id="picker-fuzz-pg-of">/ 1</span>
            <button class="picker-fuzz-pg-btn" id="picker-fuzz-next" disabled>›</button>
          </div>
        </div>

        <!-- Storyboard mode -->
        <div class="picker-storyboard-panel" id="picker-storyboard-panel">
          <div class="picker-fuzz-search">
            <input type="search" id="picker-storyboard-search" class="picker-fuzz-search-input" placeholder="Search storyboards…" autocomplete="off">
          </div>
          <div id="picker-storyboard-list" style="padding:4px 0;"></div>
          <div class="picker-fuzz-pg" id="picker-storyboard-pg" style="display:none;">
            <button class="picker-fuzz-pg-btn" id="picker-storyboard-prev" disabled>‹</button>
            <input type="number" class="picker-fuzz-pg-input" id="picker-storyboard-page-input" value="1" min="1">
            <span class="picker-fuzz-pg-of" id="picker-storyboard-pg-of">/ 1</span>
            <button class="picker-fuzz-pg-btn" id="picker-storyboard-next" disabled>›</button>
          </div>
        </div>
      </div>

      <!-- Right videos panel -->
      <div class="picker-videos-panel">
        <div class="picker-search-bar">
          <input type="text" id="picker-search" placeholder="Search videos…" autocomplete="off">
        </div>
        <div class="picker-videos-scroll">
          <div id="picker-results" class="picker-grid"></div>
          <div id="picker-loading" style="display:none;text-align:center;padding:20px;color:var(--muted);font-size:11px;">Loading…</div>
          <div id="picker-empty"   style="display:none;text-align:center;padding:20px;color:var(--muted);font-size:11px;">No videos found.</div>
        </div>
        <div class="picker-footer">
          <button id="picker-prev" disabled>Previous</button>
          <div class="picker-page-jump">
            Page <input type="number" id="picker-page-input" class="picker-page-input" value="1" min="1">
            <span id="picker-page-of">of 1</span>
          </div>
          <button id="picker-next" disabled>Next</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Render jobs modal -->
<div id="modal-jobs" class="mt-mbg">
  <div class="mt-modal">
    <div class="mt-mh"><h3>Render Jobs</h3><button class="mt-mx" data-close="modal-jobs">×</button></div>
    <div class="mt-mb">
      <div id="jobs-list"><p style="font-size:11px;color:var(--muted)">Loading…</p></div>
    </div>
    <div class="mt-mf">
      <button class="mt-btn" id="btn-jobs-refresh"><i class="fa fa-rotate"></i> Refresh</button>
      <button class="mt-btn" data-close="modal-jobs">Close</button>
    </div>
  </div>
</div>

<!-- Preview modal -->
<div id="modal-preview" class="mt-mbg">
  <div class="mt-modal wide">
    <div class="mt-mh"><h3 id="prev-title">Preview</h3><button class="mt-mx" data-close="modal-preview">×</button></div>
    <div class="mt-mb" style="padding:10px;">
      <video id="prev-vid" class="mt-prev-vid" controls playsinline></video>
    </div>
    <div class="mt-mf"><button class="mt-btn" data-close="modal-preview">Close</button></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     BROWSE TRANSITION RENDERS MODAL — two tabs
     Tab 1 "By Type":     all renders ever made for this transition type (global)
     Tab 2 "This Slot":   renders from this specific slot connector instance
     ═══════════════════════════════════════════════════════════ -->
<div id="modal-browse-demos" class="mt-mbg">
  <div class="mt-modal wide">
    <div class="mt-mh">
      <h3 id="demo-modal-title">Renders — cross_dissolve</h3>
      <button class="mt-mx" data-close="modal-browse-demos">×</button>
    </div>
    <div class="mt-mb" style="padding:0;">

      <!-- Tab bar -->
      <div style="display:flex;border-bottom:1px solid var(--border);background:var(--panel);flex-shrink:0;">
        <button id="demo-tab-type" class="demo-tab-btn active" onclick="switchDemoTab('type')">
          🌐 By Type
        </button>
        <button id="demo-tab-inst" class="demo-tab-btn" onclick="switchDemoTab('inst')">
          🔗 This Slot
        </button>
      </div>

      <!-- Tab description strip -->
      <div id="demo-tab-desc" style="padding:5px 12px 3px;font-size:10px;color:var(--muted);background:var(--bg);border-bottom:1px solid var(--border);">
        Showing all renders of this transition type across all projects.
      </div>

      <!-- Search bar (shared) -->
      <div class="demo-search-bar" style="padding:8px 12px 0;">
        <input type="text" id="demo-search" placeholder="Search by video name or ID…" autocomplete="off">
        <button class="mt-params-btn" id="demo-search-btn" style="flex-shrink:0;">Search</button>
      </div>

      <!-- List area -->
      <div style="padding:8px 12px;">
        <div id="demo-list">
          <div class="demo-no-renders">
            <i class="fa fa-film"></i>
            Loading…
          </div>
        </div>
        <div class="demo-pagination" id="demo-pagination" style="display:none;">
          <button id="demo-prev" disabled>‹ Prev</button>
          <span class="demo-pg-info" id="demo-pg-info">Page 1 of 1</span>
          <button id="demo-next" disabled>Next ›</button>
        </div>
      </div>

    </div>
    <div class="mt-mf">
      <button class="mt-btn" data-close="modal-browse-demos">Close</button>
    </div>
  </div>
</div>

<?= $spw->getJquery() ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<script>
(function(){
'use strict';

const API = 'muvitriccs_api.php';
let PROJECT_ID = <?= (int)$projectId ?>;
let project    = <?= $project ? json_encode($project) : 'null' ?>;
let slots      = [];
let selSlotId  = null;
let selConnIdx = null;
let sidebarOpen = false;

// ── TRANSITION DEFINITIONS ────────────────────────────────────────────────────
let TRANSITIONS = {};

const ICONS = {
    'cross_dissolve': '⊕', 'fade_to_black': '⬛', 'fade_to_white': '⬜', 'luma_wipe': '◑',
    'slide_left': '←', 'slide_right': '→', 'slide_up': '↑', 'slide_down': '↓',
    'push_left': '⇦', 'push_right': '⇨',
    'zoom_in': '⊕', 'zoom_out': '⊖', 'spin_cw': '↻', 'spin_ccw': '↺',
    'whip_pan_left': '⇇', 'whip_pan_right': '⇉',
    'motion_blur_cut': '〰', 'radial_blur_cut': '❂', 'defocus_cut': '◍',
    'flash': '⚡', 'glitch': '▒', 'rgb_split': '⫷', 'wave_warp': '〜',
    'lens_distortion': '◉', 'film_burn': '🔥', 'light_leak': '☀️', 'scanline_tear': '▤', 'vhs_dropout': '📼',
    'optical_flow_warp': '≚', 'depth_parallax': '⇕',
    'pixel_sort': '▚', 'ink_wash': '💧', 'shatter': '💥', 'smear_frame': '💨',
    'cube_rotate_left': '◧', 'cube_rotate_right': '◨', 'page_curl': '⎘',
    'kaleidoscope': '💠', 'ripple_water': '🌊', 'dream_blur': '✨',
    'speed_ramp':     '⏱',
    'shockwave':      '💢',
    'strobe_cut':     '🔦',
    'motion_trail':   '👻',
    'glare_hit':      '🌟',
    'iris_wipe':      '⭕',
    'venetian_blind': '🪟',
    'cross_zoom':     '🔍',
    'tilt_shift_cut': '🔬',
    'cinematic_bars': '🎞',
    'whip_zoom':      '🌀'
};

function getIcon(name) { return ICONS[name] || '✨'; }
function formatLabel(name) {
    return name.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function loadTransitions() {
  fetch(`${API}?action=list_transitions`)
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        TRANSITIONS = {};
        d.transitions.forEach(tr => {
          if (!TRANSITIONS[tr.family]) TRANSITIONS[tr.family] = [];
          TRANSITIONS[tr.family].push({
            name: tr.name, icon: getIcon(tr.name),
            label: formatLabel(tr.name), description: tr.description
          });
        });
        buildTransitionGrids();
      }
    });
}

function buildTransitionGrids() {
  const container = document.getElementById('mt-transition-grids-container');
  if (!container) return;
  container.innerHTML = '';
  const order = ['core', 'motion', 'optical', 'stylized', 'creative', 'flow', 'depth'];
  const families = Object.keys(TRANSITIONS).sort((a, b) => {
      let ia = order.indexOf(a), ib = order.indexOf(b);
      if(ia === -1) ia = 999; if(ib === -1) ib = 999;
      return ia - ib;
  });
  families.forEach(family => {
    const lbl = document.createElement('div');
    lbl.className = 'mt-family-label';
    lbl.textContent = family.charAt(0).toUpperCase() + family.slice(1);
    container.appendChild(lbl);
    const grid = document.createElement('div');
    grid.className = 'mt-transition-grid';
    grid.id = `mt-transition-grid-${family}`;
    TRANSITIONS[family].forEach(tr => {
      const card = document.createElement('div');
      card.className = 'mt-trans-card';
      card.dataset.name = tr.name;
      card.title = tr.description;
      card.innerHTML = `<span class="mt-trans-card-icon">${tr.icon}</span>${tr.label}`;
      card.addEventListener('click', () => selectTransitionCard(tr.name));
      grid.appendChild(card);
    });
    container.appendChild(grid);
  });
  if (selConnIdx !== null) {
      const slot = slots.find(s => s.slot_order === selConnIdx);
      if (slot) selectTransitionCard(slot.transition_name || 'cross_dissolve');
  }
}

function selectTransitionCard(name) {
  document.querySelectorAll('.mt-trans-card').forEach(c => {
    c.classList.toggle('sel', c.dataset.name === name);
  });
  // If the browse demos modal is currently open on the "By Type" tab,
  // live-refresh it to show renders for the newly tapped transition type.
  if (document.getElementById('modal-browse-demos').classList.contains('open') &&
      demoTab === 'type') {
    demoTransitionName = name;
    document.getElementById('demo-modal-title').textContent = `Renders — ${formatLabel(name)}`;
    loadDemoPage(1);
  }
}
function getSelectedTransition() {
  const sel = document.querySelector('.mt-trans-card.sel');
  return sel ? sel.dataset.name : 'cross_dissolve';
}

// ── SLOT RENDERING ────────────────────────────────────────────────────────────
function buildChain() {
  const chain  = document.getElementById('mt-chain');
  const addBtn = document.getElementById('btn-chain-add');
  while (chain.firstChild && chain.firstChild !== addBtn) chain.removeChild(chain.firstChild);
  if (!slots.length) return;
  const slotsSorted = [...slots].sort((a,b) => a.slot_order - b.slot_order);
  slotsSorted.forEach((slot, i) => {
    chain.insertBefore(makeSlotEl(slot, i), addBtn);
    if (i < slotsSorted.length - 1) {
      chain.insertBefore(makeConnectorEl(slot, slotsSorted[i+1], i), addBtn);
    }
  });
}

function makeSlotEl(slot, idx) {
  const div = document.createElement('div');
  div.className = 'mt-slot' + (slot.id === selSlotId ? ' sel' : '');
  div.dataset.id = slot.id;
  let thumbHtml = '';
  if (slot.asset_type === 'frame' && slot.asset_filename)
    thumbHtml = `<img src="/${escHtml(slot.asset_filename)}" alt="" loading="lazy">`;
  else if (slot.asset_type === 'video' && slot.asset_thumbnail)
    thumbHtml = `<img src="/${escHtml(slot.asset_thumbnail)}" alt="" loading="lazy">`;
  else
    thumbHtml = `<i class="fa ${slot.asset_type === 'video' ? 'fa-film' : 'fa-image'}"></i>`;
  div.innerHTML = `
    <button class="mt-slot-del" data-id="${slot.id}" title="Remove slot"><i class="fa fa-times"></i></button>
    <span class="mt-slot-order">${idx}</span>
    <span class="mt-slot-badge ${slot.asset_type}">${slot.asset_type}</span>
    <div class="mt-slot-thumb">${thumbHtml}</div>
    <div class="mt-slot-name">${escHtml(slot.label || slot.asset_name || `Slot ${idx}`)}</div>
  `;
  div.addEventListener('click', e => {
    if (e.target.closest('.mt-slot-del')) return;
    selectSlot(slot.id);
  });
  div.querySelector('.mt-slot-del').addEventListener('click', e => {
    e.stopPropagation(); deleteSlot(slot.id);
  });
  return div;
}

function makeConnectorEl(slotA, slotB, idx) {
  const div = document.createElement('div');
  div.className = 'mt-connector';
  div.dataset.slotAId = slotA.id;
  div.dataset.slotBId = slotB.id;
  div.dataset.idx = idx;
  const shortName = (slotA.transition_name || 'dissolve').replace('_', ' ');
  const durFr = slotA.transition_duration_frames || 24;
  let stClass = '';
  if(slotA.job_status === 'completed') stClass = 'done';
  else if(slotA.job_status === 'processing' || slotA.job_status === 'queued') stClass = 'working';
  else if(slotA.job_status === 'failed') stClass = 'fail';
  let playBtn = '';
  let renderLbl = slotA.job_video_id ? '<i class="fa fa-rotate"></i>' : '▶ Render';
  if (slotA.job_video_id)
    playBtn = `<button class="mt-conn-play-btn" onclick="offerPreview(${slotA.job_video_id}); event.stopPropagation();" title="Play">▶ Play</button>`;
  div.innerHTML = `
    <div class="mt-conn-line"></div>
    <div class="mt-conn-status-dot ${stClass}" id="dot-conn-${idx}"></div>
    <div class="mt-conn-pill">${shortName}</div>
    <div class="mt-conn-actions">
      ${playBtn}
      <button class="mt-conn-render-btn" data-slot-a="${slotA.id}" data-slot-b="${slotB.id}" title="Render">${renderLbl}</button>
    </div>
    <div class="mt-conn-dur">${durFr}fr</div>
  `;
  div.addEventListener('click', e => {
    if (e.target.closest('.mt-conn-actions')) return;
    selectConnector(slotA.id, idx);
  });
  div.querySelector('.mt-conn-render-btn').addEventListener('click', e => {
    e.stopPropagation(); queueRender(slotA.id, slotB.id);
  });
  return div;
}

// ── SELECTION ─────────────────────────────────────────────────────────────────
function selectSlot(slotId) {
  selSlotId = slotId; selConnIdx = null;
  const slot = slots.find(s => s.id === slotId);
  if (!slot) return;
  document.querySelectorAll('.mt-slot').forEach(el =>
    el.classList.toggle('sel', parseInt(el.dataset.id) === slotId));
  showSlotDetail(slot);
  showParamsForSlot(slot);
}
function selectConnector(slotAId, idx) {
  selConnIdx = idx; selSlotId = null;
  document.querySelectorAll('.mt-slot').forEach(el => el.classList.remove('sel'));
  const slot = slots.find(s => s.id === slotAId);
  if (slot) {
    showConnectorDetail(slot);
    showParamsForConnector(slot);
  }
}
function deselect() {
  selSlotId = null; selConnIdx = null;
  document.querySelectorAll('.mt-slot').forEach(el => el.classList.remove('sel'));
  showEmptyDetail();
  showParamsEmpty();
}

// ── DETAIL PANEL (scrollable — transition grid) ──────────────────────────────
function showEmptyDetail() {
  document.getElementById('mt-detail-empty').style.display = '';
  document.getElementById('mt-conn-detail').style.display  = 'none';
  document.getElementById('mt-slot-detail').style.display  = 'none';
}
function showConnectorDetail(slotA) {
  document.getElementById('mt-detail-empty').style.display = 'none';
  document.getElementById('mt-conn-detail').style.display  = 'block';
  document.getElementById('mt-slot-detail').style.display  = 'none';
  document.getElementById('mt-conn-detail-title').textContent =
    `Transition: Slot ${slotA.slot_order} → ${slotA.slot_order + 1}`;
  selectTransitionCard(slotA.transition_name || 'cross_dissolve');
}
function showSlotDetail(slot) {
  document.getElementById('mt-detail-empty').style.display = 'none';
  document.getElementById('mt-conn-detail').style.display  = 'none';
  document.getElementById('mt-slot-detail').style.display  = 'block';
  document.getElementById('mt-slot-detail-title').textContent =
    `Slot ${slot.slot_order} — ${slot.asset_name || slot.asset_type + ' #' + slot.asset_id}`;
  document.getElementById('sd-label').value   = slot.label || '';
  document.getElementById('sd-trim-s').value  = slot.trim_start || 0;
  document.getElementById('sd-trim-e').value  = slot.trim_end || '';
  const spdVal = (slot.playback_speed || 1.0).toFixed(2);
  document.getElementById('sd-spd-r').value      = spdVal;
  document.getElementById('sd-spd-v').textContent = spdVal + '×';
}

// ── FIXED PARAMS PANEL ────────────────────────────────────────────────────────
function showParamsEmpty() {
  const panel  = document.getElementById('mt-params-panel');
  const header = document.getElementById('mt-params-header');
  panel.classList.remove('conn-active','slot-active');
  header.classList.remove('conn-active','slot-active');
  document.getElementById('mt-params-header-title').textContent = 'Parameters';
  document.getElementById('mt-params-header-btns').innerHTML = '';
  document.getElementById('mt-params-empty').classList.remove('hidden');
  document.getElementById('mt-params-conn').classList.remove('active');
  document.getElementById('mt-params-slot').classList.remove('active');
}

function showParamsForConnector(slotA) {
  const panel  = document.getElementById('mt-params-panel');
  const header = document.getElementById('mt-params-header');
  panel.classList.add('conn-active'); panel.classList.remove('slot-active');
  header.classList.add('conn-active'); header.classList.remove('slot-active');
  document.getElementById('mt-params-header-title').textContent =
    `Transition · Slot ${slotA.slot_order}→${slotA.slot_order+1} · ${formatLabel(slotA.transition_name||'cross_dissolve')}`;
  document.getElementById('mt-params-empty').classList.add('hidden');
  document.getElementById('mt-params-slot').classList.remove('active');
  document.getElementById('mt-params-conn').classList.add('active');

  // Populate connector params
  document.getElementById('tc-dur').value   = slotA.transition_duration_frames || 24;
  document.getElementById('tc-dur-r').value = slotA.transition_duration_frames || 24;
  const intVal = (slotA.transition_intensity || 1.0).toFixed(2);
  document.getElementById('tc-int-r').value      = intVal;
  document.getElementById('tc-int-v').textContent = intVal;
  document.getElementById('tc-easing').value = slotA.transition_easing || 'ease_in_out_cubic';
  document.getElementById('tc-seed').value   = slotA.transition_seed   || 42;

  // Header buttons
  const sorted = [...slots].sort((a,b)=>a.slot_order-b.slot_order);
  const nextIdx = sorted.findIndex(s=>s.id===slotA.id) + 1;
  const slotB   = sorted[nextIdx];
  const slotBId = slotB ? slotB.id : '';
  const transName = slotA.transition_name || 'cross_dissolve';

  document.getElementById('mt-params-header-btns').innerHTML = `
    <button class="mt-params-btn browse" id="phb-browse" title="Browse renders of this transition type"
            data-slot-a-id="${slotA.id}">
      <i class="fa fa-magnifying-glass"></i> Browse Renders
    </button>
    <button class="mt-params-btn acc" id="phb-render">
      <i class="fa fa-play"></i> Render
    </button>
    <button class="mt-params-btn save" id="phb-save">
      <i class="fa fa-floppy-disk"></i> Save
    </button>
  `;
  document.getElementById('phb-browse').addEventListener('click', function() {
    // Always read the CURRENTLY selected card at press time, not the stale closure value
    openBrowseDemos(getSelectedTransition(), parseInt(this.dataset.slotAId) || 0);
  });
  document.getElementById('phb-render').addEventListener('click', () => {
    if (slotA.id && slotBId) queueRender(slotA.id, slotBId);
  });
  document.getElementById('phb-save').addEventListener('click', () => saveConnectorParams(slotA.id));
}

function showParamsForSlot(slot) {
  const panel  = document.getElementById('mt-params-panel');
  const header = document.getElementById('mt-params-header');
  panel.classList.add('slot-active'); panel.classList.remove('conn-active');
  header.classList.add('slot-active'); header.classList.remove('conn-active');
  document.getElementById('mt-params-header-title').textContent =
    `Slot ${slot.slot_order} — ${escHtml(slot.label || slot.asset_name || 'Slot')}`;
  document.getElementById('mt-params-empty').classList.add('hidden');
  document.getElementById('mt-params-conn').classList.remove('active');
  document.getElementById('mt-params-slot').classList.add('active');

  // Populate slot mini-params (mirrors the scroll-area values)
  document.getElementById('sd2-trim-s').value  = slot.trim_start || 0;
  document.getElementById('sd2-trim-e').value  = slot.trim_end || '';
  const spdVal = (slot.playback_speed || 1.0).toFixed(2);
  document.getElementById('sd2-spd-r').value      = spdVal;
  document.getElementById('sd2-spd-v').textContent = spdVal + '×';

  // Header buttons
  document.getElementById('mt-params-header-btns').innerHTML = `
    <button class="mt-params-btn danger" id="phb-slot-remove">
      <i class="fa fa-trash"></i> Remove
    </button>
    <button class="mt-params-btn save" id="phb-slot-save">
      <i class="fa fa-floppy-disk"></i> Save
    </button>
  `;
  document.getElementById('phb-slot-remove').addEventListener('click', () => deleteSlot(slot.id));
  document.getElementById('phb-slot-save').addEventListener('click', () => saveSlotParams(slot.id));
}

// sync scroll-area and params panel slot fields
document.getElementById('sd-spd-r').addEventListener('input', function(){
  document.getElementById('sd-spd-v').textContent = parseFloat(this.value).toFixed(2)+'×';
  document.getElementById('sd2-spd-r').value = this.value;
  document.getElementById('sd2-spd-v').textContent = parseFloat(this.value).toFixed(2)+'×';
});
document.getElementById('sd2-spd-r').addEventListener('input', function(){
  document.getElementById('sd2-spd-v').textContent = parseFloat(this.value).toFixed(2)+'×';
  document.getElementById('sd-spd-r').value = this.value;
  document.getElementById('sd-spd-v').textContent = parseFloat(this.value).toFixed(2)+'×';
});

document.getElementById('tc-dur-r').addEventListener('input',function(){ document.getElementById('tc-dur').value=this.value; });
document.getElementById('tc-dur').addEventListener('input',function(){ document.getElementById('tc-dur-r').value=this.value; });
document.getElementById('tc-int-r').addEventListener('input',function(){
  document.getElementById('tc-int-v').textContent=parseFloat(this.value).toFixed(2);
});

// ── SAVE CONNECTOR PARAMS ─────────────────────────────────────────────────────
function saveConnectorParams(slotId) {
  const slot = slots.find(s => s.id === slotId);
  if (!slot) return;
  const fd = new FormData();
  fd.append('action','update_slot');
  fd.append('id', slotId);
  fd.append('label', slot.label||'');
  fd.append('trim_start', slot.trim_start||0);
  fd.append('trim_end', slot.trim_end||'');
  fd.append('playback_speed', slot.playback_speed||1);
  fd.append('transition_name', getSelectedTransition());
  fd.append('transition_duration_frames', document.getElementById('tc-dur').value||24);
  fd.append('transition_intensity', document.getElementById('tc-int-r').value||1.0);
  fd.append('transition_easing', document.getElementById('tc-easing').value||'ease_in_out_cubic');
  fd.append('transition_seed', document.getElementById('tc-seed').value||42);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){toast('Transition saved');loadSlots();}
    else toast('Error: '+d.message,true);
  });
}

// ── SAVE SLOT PARAMS ──────────────────────────────────────────────────────────
function saveSlotParams(slotId) {
  const slot = slots.find(s => s.id === slotId);
  if (!slot) return;
  const fd = new FormData();
  fd.append('action','update_slot');
  fd.append('id', slotId);
  fd.append('label', document.getElementById('sd-label').value);
  fd.append('trim_start', document.getElementById('sd2-trim-s').value||0);
  fd.append('trim_end', document.getElementById('sd2-trim-e').value||'');
  fd.append('playback_speed', document.getElementById('sd2-spd-r').value||1);
  fd.append('transition_name', slot.transition_name||'cross_dissolve');
  fd.append('transition_duration_frames', slot.transition_duration_frames||24);
  fd.append('transition_intensity', slot.transition_intensity||1.0);
  fd.append('transition_easing', slot.transition_easing||'ease_in_out_cubic');
  fd.append('transition_seed', slot.transition_seed||42);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){toast('Slot saved');loadSlots();}
    else toast('Error: '+d.message,true);
  });
}

// ── DELETE SLOT ───────────────────────────────────────────────────────────────
function deleteSlot(slotId) {
  if(!confirm('Remove this slot from the project?')) return;
  const fd=new FormData();
  fd.append('action','delete_slot');
  fd.append('id',slotId);
  fd.append('project_id',PROJECT_ID);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      if(selSlotId===slotId){selSlotId=null;showEmptyDetail();showParamsEmpty();}
      toast('Slot removed');
      loadSlots();
    } else toast('Error: '+d.message, true);
  });
}

// ── LOAD SLOTS ────────────────────────────────────────────────────────────────
function loadSlots() {
  if (!PROJECT_ID) return;
  fetch(`${API}?action=get_slots&project_id=${PROJECT_ID}`)
    .then(r=>r.json()).then(d=>{
      if (!d.success) { toast('Error loading slots: ' + d.message, true); return; }
      slots = d.slots || [];
      buildChain();
      if (selSlotId) {
        const s = slots.find(x=>x.id===selSlotId);
        if (s) { showSlotDetail(s); showParamsForSlot(s); } else deselect();
      } else if (selConnIdx !== null) {
        const s = slots.find(x=>x.slot_order===selConnIdx);
        if (s) { showConnectorDetail(s); showParamsForConnector(s); } else deselect();
      }
    });
}

// ══════════════════════════════════════════════════════════════════════
// VIDEO PICKER — four-tab fly-out (Tree | Seq | Fuzz | Storyboard)
// ══════════════════════════════════════════════════════════════════════

let pickerTreeInited = false;
let pickerMode = 'tree';

let pickerNodeId   = null, pickerNodeName   = '';
let pickerSeqId    = null, pickerSeqName    = '';
let pickerFuzzId   = null, pickerFuzzName   = '';
let pickerSbId     = null, pickerSbName     = '';

let pickerSeqsLoaded = false;
let pickerFuzzLoaded = false, pickerFuzzPage = 1, pickerFuzzTotalPages = 1;
let pickerFuzzSearch = '', pickerFuzzSearchTimer = null;
let pickerSbLoaded = false, pickerSbPage = 1, pickerSbTotalPages = 1;
let pickerSbSearch = '', pickerSbSearchTimer = null;

let pickerCurrentPage = 1, pickerTotalPages = 1;
const PICKER_PER_PAGE = 12;

const activeLabel   = document.getElementById('picker-active-label');
const treeClearBtn  = document.getElementById('picker-tree-clear');

function openPicker() {
  if (!PROJECT_ID) { toast('Open a project first.', true); return; }
  openModal('modal-assign');
  if (!pickerTreeInited && window.innerWidth >= 700) initPickerTree();
  searchVideos(true);
}
function closePicker() {
  closeModal('modal-assign');
  closePickerTree();
}
function togglePickerTree() {
  const panel    = document.getElementById('picker-tree-panel');
  const backdrop = document.getElementById('picker-tree-backdrop');
  const isOpen   = panel.classList.contains('open');
  if (isOpen) {
    panel.classList.remove('open'); backdrop.classList.remove('active');
  } else {
    panel.classList.add('open'); backdrop.classList.add('active');
    if (!pickerTreeInited) initPickerTree();
  }
}
function closePickerTree() {
  document.getElementById('picker-tree-panel').classList.remove('open');
  document.getElementById('picker-tree-backdrop').classList.remove('active');
}
document.getElementById('picker-tree-toggle').addEventListener('click', togglePickerTree);

function setPickerActiveLabel(text) {
  if (text) {
    activeLabel.textContent = text;
    activeLabel.style.display = 'inline';
    treeClearBtn.style.display = 'inline-block';
  } else {
    activeLabel.style.display = 'none';
    treeClearBtn.style.display = 'none';
  }
}

function clearPickerFilter() {
  if (pickerMode === 'tree') {
    $('#picker-tree').jstree('deselect_all');
  } else if (pickerMode === 'seq') {
    pickerSeqId = null; pickerSeqName = '';
    document.querySelectorAll('.picker-seq-item').forEach(i => i.classList.remove('active'));
    setPickerActiveLabel('');
    searchVideos(true);
  } else if (pickerMode === 'fuzz') {
    pickerFuzzId = null; pickerFuzzName = '';
    document.querySelectorAll('.picker-fuzz-item').forEach(i => i.classList.remove('active'));
    setPickerActiveLabel('');
    searchVideos(true);
  } else if (pickerMode === 'storyboard') {
    pickerSbId = null; pickerSbName = '';
    document.querySelectorAll('.picker-storyboard-item').forEach(i => i.classList.remove('active'));
    setPickerActiveLabel('');
    searchVideos(true);
  }
}

function switchPickerMode(mode) {
  pickerMode = mode;
  const treeScroll = document.getElementById('picker-tree-scroll');
  const seqPanel   = document.getElementById('picker-seq-panel');
  const fuzzPanel  = document.getElementById('picker-fuzz-panel');
  const sbPanel    = document.getElementById('picker-storyboard-panel');

  treeScroll.style.display = 'none';
  seqPanel.classList.remove('active');
  fuzzPanel.classList.remove('active');
  sbPanel.classList.remove('active');

  document.getElementById('picker-mode-tree').classList.toggle('active', mode === 'tree');
  document.getElementById('picker-mode-seq').classList.toggle('active', mode === 'seq');
  document.getElementById('picker-mode-fuzz').classList.toggle('active', mode === 'fuzz');
  document.getElementById('picker-mode-storyboard').classList.toggle('active', mode === 'storyboard');

  if (mode === 'tree') {
    treeScroll.style.display = 'block';
    pickerSeqId = null; pickerFuzzId = null; pickerSbId = null;
    setPickerActiveLabel(pickerNodeId ? '⬡ ' + pickerNodeName : '');
    if (!pickerTreeInited) initPickerTree();
  } else if (mode === 'seq') {
    seqPanel.classList.add('active');
    pickerNodeId = null; pickerFuzzId = null; pickerSbId = null;
    setPickerActiveLabel(pickerSeqId ? '🎬 ' + pickerSeqName : '');
    if (!pickerSeqsLoaded) loadPickerSequences();
  } else if (mode === 'fuzz') {
    fuzzPanel.classList.add('active');
    pickerNodeId = null; pickerSeqId = null; pickerSbId = null;
    setPickerActiveLabel(pickerFuzzId ? '🧩 ' + pickerFuzzName : '');
    if (!pickerFuzzLoaded) loadPickerFuzz(1);
  } else if (mode === 'storyboard') {
    sbPanel.classList.add('active');
    pickerNodeId = null; pickerSeqId = null; pickerFuzzId = null;
    setPickerActiveLabel(pickerSbId ? '🖼️ ' + pickerSbName : '');
    if (!pickerSbLoaded) loadPickerStoryboards(1);
  }
  searchVideos(true);
}
window.switchPickerMode = switchPickerMode;

function initPickerTree() {
  pickerTreeInited = true;
  $('#picker-tree').jstree({
    core: {
      data: {
        url: 'view_video_review.php?api_action=tree_fetch',
        dataType: 'json',
        dataFilter: function(raw) {
          try {
            const j = JSON.parse(raw);
            return JSON.stringify(j.status === 'ok' ? j.tree : []);
          } catch(e) { return '[]'; }
        }
      },
      themes: { name: 'default', dots: true, icons: true },
      check_callback: false,
    },
    plugins: ['types'],
    types: {
      folder:   { icon: 'bi bi-folder2' },
      episode:  { icon: 'bi bi-film' },
      sequence: { icon: 'bi bi-collection-play' },
      scene:    { icon: 'bi bi-camera-video' },
      other:    { icon: 'bi bi-tag' },
    },
  }).on('select_node.jstree', function(e, data) {
    pickerNodeId   = data.node.data.db_id;
    pickerNodeName = data.node.text;
    setPickerActiveLabel('⬡ ' + pickerNodeName);
    closePickerTree();
    searchVideos(true);
  }).on('deselect_node.jstree', function() {
    pickerNodeId = null; pickerNodeName = '';
    setPickerActiveLabel('');
    searchVideos(true);
  });
}

async function loadPickerSequences() {
  pickerSeqsLoaded = true;
  const list = document.getElementById('picker-seq-list');
  list.innerHTML = '<div style="padding:12px;font-size:10px;color:var(--muted);">Loading…</div>';
  const res = await fetch(`${API}?action=list_narrative_sequences`).then(r=>r.json());
  list.innerHTML = '';
  if (!res.success || !res.data || !res.data.length) {
    list.innerHTML = '<div style="padding:12px;font-size:10px;color:var(--muted);">No sequences found.</div>';
    return;
  }
  res.data.forEach(seq => {
    const el = document.createElement('div');
    el.className = 'picker-seq-item' + (seq.id == pickerSeqId ? ' active' : '');
    el.innerHTML = `
      <div class="seq-item-id">#${seq.id}</div>
      <div class="seq-item-name">${escHtml(seq.name)}</div>
      ${seq.description ? `<div class="seq-item-desc">${escHtml(seq.description)}</div>` : ''}
    `;
    el.addEventListener('click', () => {
      document.querySelectorAll('.picker-seq-item').forEach(i => i.classList.remove('active'));
      el.classList.add('active');
      pickerSeqId = seq.id; pickerSeqName = seq.name;
      setPickerActiveLabel('🎬 ' + seq.name);
      closePickerTree();
      searchVideos(true);
    });
    list.appendChild(el);
  });
}

const FUZZ_TYPE_ICONS = {
  character: '🦸', location: '🗺️', faction: '⚔️', artifact: '🏺',
  event: '⚡', concept: '💡', relationship: '🔗', other: '◆'
};

async function loadPickerFuzz(page) {
  page = parseInt(page) || 1;
  if (page < 1) page = 1;
  pickerFuzzLoaded = true;
  pickerFuzzPage = page;
  const list = document.getElementById('picker-fuzz-list');
  list.innerHTML = '<div style="padding:12px;font-size:10px;color:var(--muted);">Loading…</div>';
  const params = new URLSearchParams({ action: 'list_fuzz_candidates', page, limit: 20, search: pickerFuzzSearch });
  const res = await fetch(`${API}?` + params).then(r=>r.json()).catch(() => null);
  list.innerHTML = '';
  if (!res || !res.data || !res.data.length) {
    list.innerHTML = '<div style="padding:12px;font-size:10px;color:var(--muted);">No candidates found.</div>';
    document.getElementById('picker-fuzz-pg').style.display = 'none';
    return;
  }
  const pg = res.pagination || { pages: 1, page: 1 };
  pickerFuzzTotalPages = pg.pages;
  document.getElementById('picker-fuzz-page-input').value = pg.page;
  document.getElementById('picker-fuzz-page-input').max   = pg.pages;
  document.getElementById('picker-fuzz-pg-of').textContent = `/ ${pg.pages}`;
  document.getElementById('picker-fuzz-prev').disabled = pg.page <= 1;
  document.getElementById('picker-fuzz-next').disabled = pg.page >= pg.pages;
  document.getElementById('picker-fuzz-pg').style.display = pg.pages > 1 ? 'flex' : 'none';
  res.data.forEach(cand => {
    const el = document.createElement('div');
    el.className = 'picker-fuzz-item' + (cand.id == pickerFuzzId ? ' active' : '');
    const icon = FUZZ_TYPE_ICONS[cand.concept_type] || '◆';
    el.innerHTML = `
      <div class="fuzz-item-id">#${cand.id}</div>
      <div class="fuzz-item-name">${icon} ${escHtml(cand.label)}</div>
      ${cand.concept_type ? `<div class="fuzz-item-type">${escHtml(cand.concept_type)}</div>` : ''}
      <span class="fuzz-item-status ${escHtml(cand.status)}">${escHtml(cand.status)}</span>
    `;
    el.addEventListener('click', () => {
      document.querySelectorAll('.picker-fuzz-item').forEach(i => i.classList.remove('active'));
      el.classList.add('active');
      pickerFuzzId = cand.id; pickerFuzzName = cand.label;
      setPickerActiveLabel('🧩 ' + cand.label);
      closePickerTree();
      searchVideos(true);
    });
    list.appendChild(el);
  });
}

document.getElementById('picker-fuzz-prev').addEventListener('click', () => loadPickerFuzz(pickerFuzzPage - 1));
document.getElementById('picker-fuzz-next').addEventListener('click', () => loadPickerFuzz(pickerFuzzPage + 1));
document.getElementById('picker-fuzz-page-input').addEventListener('change', function() {
  const v = parseInt(this.value, 10);
  if (!isNaN(v) && v >= 1 && v <= pickerFuzzTotalPages) loadPickerFuzz(v);
  else this.value = pickerFuzzPage;
});
document.getElementById('picker-fuzz-search').addEventListener('input', function() {
  clearTimeout(pickerFuzzSearchTimer);
  pickerFuzzSearchTimer = setTimeout(() => {
    pickerFuzzSearch = this.value.trim();
    pickerFuzzPage = 1; pickerFuzzLoaded = false;
    loadPickerFuzz(1);
  }, 280);
});

async function loadPickerStoryboards(page) {
  page = parseInt(page) || 1;
  if (page < 1) page = 1;
  pickerSbLoaded = true;
  pickerSbPage = page;
  const list = document.getElementById('picker-storyboard-list');
  list.innerHTML = '<div style="padding:12px;font-size:10px;color:var(--muted);">Loading…</div>';
  const params = new URLSearchParams({ action: 'list_storyboards', page, search: pickerSbSearch });
  const res = await fetch(`${API}?` + params).then(r=>r.json()).catch(() => null);
  list.innerHTML = '';
  if (!res || !res.success || !res.data || !res.data.length) {
    list.innerHTML = '<div style="padding:12px;font-size:10px;color:var(--muted);">No storyboards found.</div>';
    document.getElementById('picker-storyboard-pg').style.display = 'none';
    return;
  }
  const pg = res.pagination || { pages: 1, page: 1 };
  pickerSbTotalPages = pg.pages;
  document.getElementById('picker-storyboard-page-input').value = pg.page;
  document.getElementById('picker-storyboard-page-input').max   = pg.pages;
  document.getElementById('picker-storyboard-pg-of').textContent = `/ ${pg.pages}`;
  document.getElementById('picker-storyboard-prev').disabled = pg.page <= 1;
  document.getElementById('picker-storyboard-next').disabled = pg.page >= pg.pages;
  document.getElementById('picker-storyboard-pg').style.display = pg.pages > 1 ? 'flex' : 'none';
  res.data.forEach(sb => {
    const el = document.createElement('div');
    el.className = 'picker-storyboard-item' + (sb.id == pickerSbId ? ' active' : '');
    el.innerHTML = `
      <div class="sb-item-id">#${sb.id}</div>
      <div class="sb-item-name">${escHtml(sb.name || sb.title || 'Untitled')}</div>
      ${sb.description ? `<div class="sb-item-desc">${escHtml(sb.description)}</div>` : ''}
    `;
    el.addEventListener('click', () => {
      document.querySelectorAll('.picker-storyboard-item').forEach(i => i.classList.remove('active'));
      el.classList.add('active');
      pickerSbId = sb.id; pickerSbName = sb.name || sb.title;
      setPickerActiveLabel('🖼️ ' + pickerSbName);
      closePickerTree();
      searchVideos(true);
    });
    list.appendChild(el);
  });
}

document.getElementById('picker-storyboard-prev').addEventListener('click', () => loadPickerStoryboards(pickerSbPage - 1));
document.getElementById('picker-storyboard-next').addEventListener('click', () => loadPickerStoryboards(pickerSbPage + 1));
document.getElementById('picker-storyboard-page-input').addEventListener('change', function() {
  const v = parseInt(this.value, 10);
  if (!isNaN(v) && v >= 1 && v <= pickerSbTotalPages) loadPickerStoryboards(v);
  else this.value = pickerSbPage;
});
document.getElementById('picker-storyboard-search').addEventListener('input', function() {
  clearTimeout(pickerSbSearchTimer);
  pickerSbSearchTimer = setTimeout(() => {
    pickerSbSearch = this.value.trim();
    pickerSbPage = 1; pickerSbLoaded = false;
    loadPickerStoryboards(1);
  }, 280);
});

let pickerSearchDebounce = null;
document.getElementById('picker-search').addEventListener('input', () => {
  clearTimeout(pickerSearchDebounce);
  pickerSearchDebounce = setTimeout(() => searchVideos(true), 300);
});

document.getElementById('picker-prev').addEventListener('click', () => {
  if (pickerCurrentPage > 1) { pickerCurrentPage--; searchVideos(false); }
});
document.getElementById('picker-next').addEventListener('click', () => {
  pickerCurrentPage++; searchVideos(false);
});
document.getElementById('picker-page-input').addEventListener('change', function() {
  const v = parseInt(this.value, 10);
  if (!isNaN(v) && v >= 1 && v <= pickerTotalPages && v !== pickerCurrentPage) {
    pickerCurrentPage = v; searchVideos(false);
  } else { this.value = pickerCurrentPage; }
});

async function searchVideos(resetPage) {
  if (resetPage) pickerCurrentPage = 1;
  const q = document.getElementById('picker-search').value;

  document.getElementById('picker-loading').style.display = 'block';
  document.getElementById('picker-results').innerHTML = '';
  document.getElementById('picker-empty').style.display = 'none';
  document.getElementById('picker-prev').disabled = true;
  document.getElementById('picker-next').disabled = true;

  const params = new URLSearchParams({
    action: 'browse_assets', asset_type: 'video',
    page: pickerCurrentPage, q
  });
  if (pickerMode === 'tree' && pickerNodeId) {
    params.set('node_id', pickerNodeId);
    params.set('include_descendants', '1');
  } else if (pickerMode === 'seq' && pickerSeqId) {
    params.set('seq_id', pickerSeqId);
  } else if (pickerMode === 'fuzz' && pickerFuzzId) {
    params.set('fuzz_cand_id', pickerFuzzId);
  } else if (pickerMode === 'storyboard' && pickerSbId) {
    params.set('storyboard_id', pickerSbId);
  }

  const res = await fetch(`${API}?` + params).then(r=>r.json()).catch(() => null);
  document.getElementById('picker-loading').style.display = 'none';

  if (!res || !res.success || !res.data || !res.data.length) {
    document.getElementById('picker-empty').style.display = 'block';
    pickerTotalPages = 1;
    document.getElementById('picker-page-input').value = 1;
    document.getElementById('picker-page-of').textContent = 'of 1';
    return;
  }

  pickerTotalPages = res.total_pages || 1;
  document.getElementById('picker-page-input').value = res.page || 1;
  document.getElementById('picker-page-input').max   = pickerTotalPages;
  document.getElementById('picker-page-of').textContent = `of ${pickerTotalPages}`;
  document.getElementById('picker-prev').disabled = (res.page || 1) <= 1;
  document.getElementById('picker-next').disabled = (res.page || 1) >= pickerTotalPages;

  const container = document.getElementById('picker-results');
  res.data.forEach(vid => {
    const d = document.createElement('div');
    d.className = 'vid-item';
    const thumb = vid.thumbnail
      ? `<img src="/${escHtml(vid.thumbnail)}" class="vid-img" loading="lazy">`
      : `<div class="vid-img" style="display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:24px;"><i class="fa fa-film"></i></div>`;
    d.innerHTML = `
      ${thumb}
      <div class="vid-item-btns">
        <button class="vid-item-btn vid-item-btn-add" title="Add to chain"><i class="fa fa-plus"></i></button>
      </div>
      <div class="vid-info">
        <div class="vid-name" title="${escHtml(vid.name)}">${escHtml(vid.name)}</div>
      </div>
    `;
    d.querySelector('.vid-item-btn-add').addEventListener('click', e => {
      e.stopPropagation();
      addSlot('video', vid.id, vid.name || ('Video #' + vid.id));
    });
    d.addEventListener('click', () => addSlot('video', vid.id, vid.name || ('Video #' + vid.id)));
    container.appendChild(d);
  });
}

function addSlot(type, assetId, name) {
  const fd = new FormData();
  fd.append('action',     'add_slot');
  fd.append('project_id', PROJECT_ID);
  fd.append('asset_type', type);
  fd.append('asset_id',   assetId);
  fd.append('label',      name);
  fetch(API, { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
    if (d.success) {
      toast(`Added ${type} slot`);
      closePicker();
      loadSlots();
    } else toast('Error: ' + d.message, true);
  });
}

function openAssignModal() {
  if (!PROJECT_ID) { toast('Open a project first.', true); return; }
  document.getElementById('picker-search').value = '';
  openPicker();
}

document.getElementById('btn-add-slot').addEventListener('click', openAssignModal);
document.getElementById('btn-chain-add').addEventListener('click', openAssignModal);

// ── QUEUE RENDER ──────────────────────────────────────────────────────────────
let pollingJobs = {};

function queueRender(slotAId, slotBId) {
  if(!PROJECT_ID){toast('Open a project first.',true);return;}
  const slotA=slots.find(s=>s.id===slotAId);
  const slotB=slots.find(s=>s.id===slotBId);
  if(!slotA||!slotB){toast('Slots not found',true);return;}
  if(!confirm(`Render: Video A + "${slotA.transition_name}" + Video B?`)) return;
  toast('Queuing render…');
  const fd=new FormData();
  fd.append('action','queue_render');
  fd.append('project_id',PROJECT_ID);
  fd.append('slot_a_id',slotAId);
  fd.append('slot_b_id',slotBId);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast(`Job #${d.job_id} queued!`);
      loadSlots();
      startPolling(d.job_id);
    } else toast('Error: '+d.message,true);
  });
}

function startPolling(jobId) {
  if(pollingJobs[jobId]) return;
  pollingJobs[jobId] = setInterval(()=>pollJob(jobId), 2500);
}
function pollJob(jobId) {
  fetch(`${API}?action=poll_render&job_id=${jobId}`)
    .then(r=>r.json()).then(d=>{
      if(!d.success) return;
      if(d.status==='completed'){
        clearInterval(pollingJobs[jobId]); delete pollingJobs[jobId];
        toast(`✓ Render complete — Video #${d.video_id}`);
        loadSlots();
        if(d.video_id) offerPreview(d.video_id);
      } else if(d.status==='failed'){
        clearInterval(pollingJobs[jobId]); delete pollingJobs[jobId];
        toast('Render failed: '+(d.error||'unknown'), true);
        loadSlots();
      }
    });
}

window.offerPreview = function(videoId) {
  fetch(`${API}?action=get_video_url&video_id=${videoId}`)
    .then(r=>r.json()).then(d=>{
      if(d.success && d.url){
        document.getElementById('prev-vid').src = '/' + d.url;
        document.getElementById('prev-title').textContent = `Preview — Video #${videoId}`;
        openModal('modal-preview');
      }
    });
};

// ── BROWSE TRANSITION RENDERS (demos) — two tabs ──────────────────────────────
// demoTab: 'type' = all renders of this transition type (global)
//          'inst' = renders from this specific slot connector instance
let demoTransitionName = '';
let demoSlotAId        = 0;    // set when opening from a connector
let demoTab            = 'type';
let demoPage           = 1, demoTotalPages = 1;
let demoSearchTimer    = null;

const TAB_DESC = {
  type: 'Showing all renders of this transition type across all projects.',
  inst: 'Showing only renders from this specific slot connector.'
};

function openBrowseDemos(transitionName, slotAId) {
  demoTransitionName = transitionName;
  demoSlotAId        = slotAId || 0;
  demoPage           = 1;
  document.getElementById('demo-search').value = '';
  document.getElementById('demo-modal-title').textContent =
    `Renders — ${formatLabel(transitionName)}`;

  // If no slotAId, force type tab and disable instance tab
  const instBtn = document.getElementById('demo-tab-inst');
  if (demoSlotAId) {
    instBtn.disabled = false;
    instBtn.title = '';
  } else {
    instBtn.disabled = true;
    instBtn.title = 'Open from a connector to see instance renders';
  }

  switchDemoTab('type');
  openModal('modal-browse-demos');
}

window.switchDemoTab = function(tab) {
  demoTab  = tab;
  demoPage = 1;
  document.getElementById('demo-tab-type').classList.toggle('active', tab === 'type');
  document.getElementById('demo-tab-inst').classList.toggle('active', tab === 'inst');
  document.getElementById('demo-tab-desc').textContent = TAB_DESC[tab];
  loadDemoPage(1);
};

async function loadDemoPage(page) {
  demoPage = Math.max(1, page);
  const q    = document.getElementById('demo-search').value.trim();
  const list = document.getElementById('demo-list');
  const pgBar = document.getElementById('demo-pagination');
  list.innerHTML = '<div class="demo-no-renders"><i class="fa fa-spinner fa-spin"></i><br>Loading…</div>';
  pgBar.style.display = 'none';

  const params = new URLSearchParams({
    action: 'browse_transition_demos',
    transition_name: demoTransitionName,
    page: demoPage, q
  });
  // Instance tab passes slot_a_id so the API filters to that specific connector
  if (demoTab === 'inst' && demoSlotAId) {
    params.set('slot_a_id', demoSlotAId);
  }

  const res = await fetch(`${API}?${params}`).then(r=>r.json()).catch(()=>null);

  if (!res || !res.success) {
    list.innerHTML = `<div class="demo-no-renders"><i class="fa fa-triangle-exclamation"></i><br>Error loading renders.</div>`;
    return;
  }

  demoTotalPages = res.total_pages || 1;
  document.getElementById('demo-pg-info').textContent = `Page ${res.page||1} of ${demoTotalPages}`;
  document.getElementById('demo-prev').disabled = (res.page||1) <= 1;
  document.getElementById('demo-next').disabled = (res.page||1) >= demoTotalPages;

  if (!res.data || !res.data.length) {
    const typeLabel = formatLabel(demoTransitionName);
    const emptyMsg = demoTab === 'type'
      ? `No renders of <strong>${escHtml(typeLabel)}</strong> found yet across any project.<br>
         <small style="margin-top:6px;display:block;">
           Render a transition of this type first, then come back to assign it as a demo.
         </small>`
      : `No renders found for this specific slot connector yet.<br>
         <small style="margin-top:6px;display:block;">
           Press <strong>Render</strong> in the parameters panel to create one.
         </small>`;
    list.innerHTML = `<div class="demo-no-renders"><i class="fa fa-film"></i><br>${emptyMsg}</div>`;
    return;
  }

  list.innerHTML = '';
  res.data.forEach(item => renderDemoItem(list, item));
  pgBar.style.display = demoTotalPages > 1 ? 'flex' : 'none';
}

function renderDemoItem(container, item) {
  const div = document.createElement('div');
  div.className = 'demo-item' +
    (item.is_primary ? ' is-primary' : (item.is_assigned ? ' is-assigned' : ''));

  const thumbHtml = item.thumbnail
    ? `<img src="/${escHtml(item.thumbnail)}" class="demo-thumb" title="Play preview">`
    : `<div class="demo-thumb-ph" title="Play preview"><i class="fa fa-film"></i></div>`;

  let badges = '';
  if (item.is_primary)  badges += `<span class="demo-badge primary">★ Primary</span>`;
  if (item.is_assigned && !item.is_primary) badges += `<span class="demo-badge assigned">Demo</span>`;

  // Show transition type name in instance tab (it may differ from current selection)
  const typeNote = (demoTab === 'inst' && item.slot_transition_name)
    ? `<small style="color:var(--acc4);"> · ${escHtml(formatLabel(item.slot_transition_name))}</small>` : '';

  const date = item.rendered_at ? new Date(item.rendered_at).toLocaleDateString() : '';

  div.innerHTML = `
    ${thumbHtml}
    <div class="demo-info">
      <strong>#${item.video_id} ${escHtml(item.video_name || '')}${badges}</strong>
      <small>Job #${item.job_id} · ${date}${typeNote ? typeNote.replace('<small','').replace('</small>','') : ''}</small>
    </div>
    <div class="demo-actions">
      <button class="demo-action-btn play-btn" data-vid="${item.video_id}">▶ Play</button>
      ${item.is_primary
        ? `<button class="demo-action-btn unassign" data-vid="${item.video_id}" data-job="${item.job_id}">Unassign</button>`
        : `<button class="demo-action-btn set-primary" data-vid="${item.video_id}" data-job="${item.job_id}">★ Primary</button>`
      }
      ${!item.is_assigned
        ? `<button class="demo-action-btn assign-btn" data-vid="${item.video_id}" data-job="${item.job_id}">+ Demo</button>`
        : ''
      }
    </div>
  `;

  const thumb = div.querySelector('.demo-thumb, .demo-thumb-ph');
  if (thumb) thumb.addEventListener('click', () => offerPreview(item.video_id));

  div.querySelector('.demo-action-btn.play-btn').addEventListener('click', () => offerPreview(item.video_id));

  const primaryBtn = div.querySelector('.demo-action-btn.set-primary');
  if (primaryBtn) primaryBtn.addEventListener('click', () => demoAssign(item.video_id, item.job_id, true));

  const unassignBtn = div.querySelector('.demo-action-btn.unassign');
  if (unassignBtn) unassignBtn.addEventListener('click', () => demoUnassign(item.video_id));

  const assignBtn = div.querySelector('.demo-action-btn.assign-btn');
  if (assignBtn) assignBtn.addEventListener('click', () => demoAssign(item.video_id, item.job_id, false));

  container.appendChild(div);
}

function demoAssign(videoId, jobId, setPrimary) {
  const fd = new FormData();
  fd.append('action', 'assign_demo');
  fd.append('transition_name', demoTransitionName);
  fd.append('video_id', videoId);
  fd.append('job_id', jobId);
  fd.append('set_primary', setPrimary ? 1 : 0);
  fetch(API, {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) {
      toast(setPrimary ? '★ Set as primary demo' : 'Assigned as demo');
      loadDemoPage(demoPage);
    } else toast('Error: ' + d.message, true);
  });
}

function demoUnassign(videoId) {
  const fd = new FormData();
  fd.append('action', 'unassign_demo');
  fd.append('transition_name', demoTransitionName);
  fd.append('video_id', videoId);
  fetch(API, {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) { toast('Demo unassigned'); loadDemoPage(demoPage); }
    else toast('Error: ' + d.message, true);
  });
}

document.getElementById('demo-prev').addEventListener('click', () => loadDemoPage(demoPage - 1));
document.getElementById('demo-next').addEventListener('click', () => loadDemoPage(demoPage + 1));
document.getElementById('demo-search').addEventListener('input', function() {
  clearTimeout(demoSearchTimer);
  demoSearchTimer = setTimeout(() => loadDemoPage(1), 320);
});
document.getElementById('demo-search-btn').addEventListener('click', () => loadDemoPage(1));

// ── RENDER JOBS MODAL ─────────────────────────────────────────────────────────
document.getElementById('btn-jobs').addEventListener('click',()=>{
  openModal('modal-jobs'); loadJobsList();
});
document.getElementById('btn-jobs-refresh').addEventListener('click',loadJobsList);

function loadJobsList() {
  if(!PROJECT_ID){
    document.getElementById('jobs-list').innerHTML='<p style="font-size:11px;color:var(--muted)">No project open.</p>';
    return;
  }
  const body=document.getElementById('jobs-list');
  body.innerHTML='<p style="font-size:11px;color:var(--muted)">Loading…</p>';
  fetch(`${API}?action=list_render_jobs&project_id=${PROJECT_ID}`)
    .then(r=>r.json()).then(d=>{
      if(!d.jobs||!d.jobs.length){body.innerHTML='<p style="font-size:11px;color:var(--muted)">No jobs yet.</p>';return;}
      body.innerHTML='';
      d.jobs.forEach(j=>{
        const div=document.createElement('div');
        div.className='mt-job';
        const autoPlay=j.status==='completed'?`<button class="mt-btn" style="font-size:9px;padding:3px 8px;" onclick="offerPreview(${j.video_id})">Preview</button>`:'';
        div.innerHTML=`
          <strong>Job #${j.id}</strong>
          <span class="job-st ${j.status}">${j.status}</span>
          ${autoPlay}
          <span style="font-size:10px;color:var(--muted);margin-left:6px;">${j.transition_name||''} · S${j.slot_a_order??'?'}→S${j.slot_b_order??'?'}</span>
          <div style="font-size:10px;color:var(--muted);margin-top:3px;">${new Date(j.created_at).toLocaleString()}</div>
          ${j.error_msg?`<div style="font-size:10px;color:var(--acc2);margin-top:2px;">${j.error_msg}</div>`:''}
        `;
        if(j.status==='processing'||j.status==='queued') startPolling(j.id);
        body.appendChild(div);
      });
    });
}

// ── PROJECT BROWSER ────────────────────────────────────────────────────────────
let pbPage=1, pbTotalPages=1, pbSearch='', pbTimer=null;

function openProjectBrowser() {
  pbPage=1; pbSearch='';
  document.getElementById('pb-search').value='';
  document.getElementById('pb-create-form').style.display='none';
  document.getElementById('pb-new-name').value='';
  openModal('modal-projects');
  loadProjectBrowserPage();
}
function loadProjectBrowserPage() {
  const body=document.getElementById('pb-list');
  body.innerHTML='<p style="font-size:11px;color:var(--muted)">Loading…</p>';
  fetch(`${API}?action=list_projects&page=${pbPage}&q=${encodeURIComponent(pbSearch)}`)
    .then(r=>r.json()).then(d=>{
      if(!d.success){body.innerHTML='<p style="color:var(--acc2);font-size:11px;">Error</p>';return;}
      pbTotalPages=d.total_pages||1;
      document.getElementById('pb-page').value=pbPage;
      document.getElementById('pb-total').textContent='/ '+pbTotalPages;
      document.getElementById('pb-prev').disabled=pbPage<=1;
      document.getElementById('pb-next').disabled=pbPage>=pbTotalPages;
      body.innerHTML='';
      if(!d.data||!d.data.length){body.innerHTML='<p style="font-size:11px;color:var(--muted)">No projects yet.</p>';return;}
      d.data.forEach(p=>{
        const div=document.createElement('div');
        div.className='mt-proj-item';
        div.innerHTML=`
          <div style="flex:1;min-width:0;">
            <strong>#${p.id} — ${escHtml(p.name)}</strong>
            <small>${p.canvas_w}×${p.canvas_h} · ${p.fps}fps · ${p.slot_count} slot(s)</small>
          </div>
        `;
        div.addEventListener('click',()=>selectProject(p));
        body.appendChild(div);
      });
    });
}
function selectProject(p) {
  PROJECT_ID=p.id; project=p;
  document.getElementById('mt-projname-lbl').textContent=p.name;
  document.getElementById('mt-proj-meta').textContent=`${p.canvas_w}×${p.canvas_h} · ${p.fps}fps`;
  closeModal('modal-projects');
  const url=new URL(window.location.href);
  url.searchParams.set('project_id',p.id);
  window.history.replaceState({},'', url.toString());
  slots=[]; deselect(); loadSlots();
  toast(`Project #${p.id}: ${p.name}`);
}

document.getElementById('btn-open-proj').addEventListener('click', openProjectBrowser);
document.getElementById('pb-search').addEventListener('input',function(){
  clearTimeout(pbTimer);
  pbTimer=setTimeout(()=>{pbSearch=this.value;pbPage=1;loadProjectBrowserPage();},350);
});
document.getElementById('pb-prev').addEventListener('click',()=>{if(pbPage>1){pbPage--;loadProjectBrowserPage();}});
document.getElementById('pb-next').addEventListener('click',()=>{if(pbPage<pbTotalPages){pbPage++;loadProjectBrowserPage();}});
document.getElementById('pb-page').addEventListener('change',function(){
  const p=parseInt(this.value)||1;pbPage=Math.max(1,Math.min(p,pbTotalPages));this.value=pbPage;loadProjectBrowserPage();
});
document.getElementById('pb-new-btn').addEventListener('click',()=>{
  const f=document.getElementById('pb-create-form');
  f.style.display=f.style.display==='none'?'block':'none';
});
document.getElementById('pb-create-cancel').addEventListener('click',()=>{
  document.getElementById('pb-create-form').style.display='none';
});
document.getElementById('pb-create-save').addEventListener('click',()=>{
  const name=document.getElementById('pb-new-name').value.trim();
  if(!name){toast('Enter a name.',true);return;}
  const fd=new FormData();
  fd.append('action','create_project');
  fd.append('name',name);
  fd.append('canvas_w',document.getElementById('pb-new-w').value||1080);
  fd.append('canvas_h',document.getElementById('pb-new-h').value||1080);
  fd.append('fps',document.getElementById('pb-new-fps').value||30);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast(`Project #${d.id} created`);
      document.getElementById('pb-create-form').style.display='none';
      document.getElementById('pb-new-name').value='';
      pbPage=1; pbSearch=''; document.getElementById('pb-search').value='';
      loadProjectBrowserPage();
      fetch(`${API}?action=get_project&id=${d.id}`).then(r=>r.json()).then(pd=>{
        if(pd.success) selectProject(pd.data);
      });
    } else toast('Error: '+d.message,true);
  });
});

// ── EDIT PROJECT ───────────────────────────────────────────────────────────────
document.getElementById('btn-edit-proj').addEventListener('click',()=>{
  if(!PROJECT_ID){toast('Open a project first.',true);return;}
  document.getElementById('ep-id').value=PROJECT_ID;
  document.getElementById('ep-name').value=project?project.name:'';
  document.getElementById('ep-w').value=project?project.canvas_w:1080;
  document.getElementById('ep-h').value=project?project.canvas_h:1080;
  document.getElementById('ep-fps').value=project?project.fps:30;
  openModal('modal-edit-proj');
});
document.getElementById('btn-ep-save').addEventListener('click',()=>{
  const fd=new FormData();
  fd.append('action','update_project');
  fd.append('id',document.getElementById('ep-id').value);
  fd.append('name',document.getElementById('ep-name').value);
  fd.append('canvas_w',document.getElementById('ep-w').value);
  fd.append('canvas_h',document.getElementById('ep-h').value);
  fd.append('fps',document.getElementById('ep-fps').value);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast('Project settings saved');
      document.getElementById('mt-proj-meta').textContent=
        `${document.getElementById('ep-w').value}×${document.getElementById('ep-h').value} · ${document.getElementById('ep-fps').value}fps`;
      if(project){
        project.name=document.getElementById('ep-name').value;
        project.canvas_w=parseInt(document.getElementById('ep-w').value);
        project.canvas_h=parseInt(document.getElementById('ep-h').value);
        project.fps=parseInt(document.getElementById('ep-fps').value);
      }
      document.getElementById('mt-projname-lbl').textContent=document.getElementById('ep-name').value;
      closeModal('modal-edit-proj');
    } else toast('Error: '+d.message,true);
  });
});

// ── SIDEBAR ────────────────────────────────────────────────────────────────────
document.getElementById('mt-hamburger').addEventListener('click',()=>{
  sidebarOpen=!sidebarOpen;
  if(sidebarOpen) document.getElementById('mt-sidebar').classList.add('open');
  else document.getElementById('mt-sidebar').classList.remove('open');
  document.getElementById('mt-hamburger').style.left=sidebarOpen?'calc(var(--sidebar-w) + 8px)':'12px';
  if(sidebarOpen) loadSidebarProjects();
});
function loadSidebarProjects() {
  const body=document.getElementById('mt-sidebar-body');
  body.innerHTML='<p style="font-size:11px;color:var(--muted);padding:6px">Loading…</p>';
  fetch(`${API}?action=list_projects&page=1&q=`)
    .then(r=>r.json()).then(d=>{
      if(!d.success){body.innerHTML='<p style="font-size:11px;color:var(--acc2);padding:6px">Error</p>';return;}
      body.innerHTML='';
      if(!d.data||!d.data.length){body.innerHTML='<p style="font-size:11px;color:var(--muted);padding:6px">No projects.</p>';return;}
      d.data.forEach(p=>{
        const div=document.createElement('div');
        div.className='mt-proj-item';
        div.style.cssText='border-radius:6px;padding:7px 9px;margin-bottom:5px;';
        div.innerHTML=`<div><strong style="font-size:11px;">#${p.id} — ${escHtml(p.name)}</strong><br><small style="font-size:9px;color:var(--muted);">${p.canvas_w}×${p.canvas_h} · ${p.slot_count}s</small></div>`;
        div.addEventListener('click',()=>{
          selectProject(p);
          sidebarOpen=false;
          document.getElementById('mt-sidebar').classList.remove('open');
          document.getElementById('mt-hamburger').style.left='12px';
        });
        body.appendChild(div);
      });
    });
}
document.getElementById('btn-sb-new').addEventListener('click',()=>{
  sidebarOpen=false;
  document.getElementById('mt-sidebar').classList.remove('open');
  document.getElementById('mt-hamburger').style.left='12px';
  setTimeout(()=>{openProjectBrowser(); document.getElementById('pb-new-btn').click();},100);
});

// ── PYAPI HEALTH ──────────────────────────────────────────────────────────────
function checkPyApi() {
  fetch(`${API}?action=pyapi_health`)
    .then(r=>r.json()).then(d=>{
      const dot=document.getElementById('pyapi-dot');
      const txt=document.getElementById('pyapi-status-txt');
      if(d.online){ dot.className='pyapi-dot online'; txt.textContent='PyAPI: online'; }
      else { dot.className='pyapi-dot offline'; txt.textContent='PyAPI: offline'; }
    }).catch(()=>{
      document.getElementById('pyapi-dot').className='pyapi-dot offline';
      document.getElementById('pyapi-status-txt').textContent='PyAPI: unreachable';
    });
}

// ── MODAL HELPERS ─────────────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('[data-close]').forEach(btn=>{
  btn.addEventListener('click',()=>closeModal(btn.dataset.close));
});
// Preview modal close: pause+clear video, but leave any parent modal untouched
document.querySelector('[data-close="modal-preview"]').addEventListener('click',()=>{
  const vid = document.getElementById('prev-vid');
  vid.pause();
  vid.src='';
});

// ── TOAST ─────────────────────────────────────────────────────────────────────
let toastTimer=null;
function toast(msg,isErr) {
  const el=document.getElementById('mt-toast');
  el.textContent=msg;
  el.className='show'+(isErr?' err':'');
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>el.className='',3200);
}

// ── HELPERS ───────────────────────────────────────────────────────────────────
function escHtml(s){
  if(!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── BOOT ──────────────────────────────────────────────────────────────────────
loadTransitions();
showEmptyDetail();
showParamsEmpty();
checkPyApi();
setInterval(checkPyApi, 30000);

if(PROJECT_ID > 0){ loadSlots(); }
else { setTimeout(()=>openProjectBrowser(), 350); }

})();
</script>
<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
