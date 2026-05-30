<?php
// public/view_animatic_multiplane.php  v3
// SAGE AI — MultiVid 2.5D Multiplane Editor
require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
global $pdo;
if (!isset($pdo)) $pdo = $spw->getPDO();

$animaticId = isset($_GET['animatic_id']) ? (int)$_GET['animatic_id'] : 0;

$animatic = null;
if ($animaticId > 0) {
    $stmtA = $pdo->prepare("SELECT * FROM animatics WHERE id = ?");
    $stmtA->execute([$animaticId]);
    $animatic = $stmtA->fetch(PDO::FETCH_ASSOC);
    if (!$animatic) $animaticId = 0; // treat as not found
}

$pageTitle = $animatic ? "MultiVid: " . htmlspecialchars($animatic['name']) : "MultiVid";

// Latest arrangement (only if we have a valid animatic)
$latestArr = null;
if ($animaticId > 0) {
    $stmtArr = $pdo->prepare("SELECT * FROM multivid_arrangements WHERE animatic_id=? ORDER BY updated_at DESC LIMIT 1");
    $stmtArr->execute([$animaticId]);
    $latestArr = $stmtArr->fetch(PDO::FETCH_ASSOC);
}
$jsInitialConfig = $latestArr ? json_encode(json_decode($latestArr['layer_config'], true)) : 'null';
$jsInitialArrId  = $latestArr ? (int)$latestArr['id'] : 'null';
$jsAnimaticId    = $animaticId;
$jsAnimaticName  = $animatic ? htmlspecialchars($animatic['name'], ENT_QUOTES) : '';

// Scheduler task ID for the MultiVid job queue processor
const MULTIVID_TASK_ID = 57;

ob_start();
?>
<!-- NO zoom, NO scrollbars, accurate sizing for modern mobiles -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<?php else: ?>
<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css"/>
<?php endif; ?>
<link rel="stylesheet" href="/css/base.css"/>

<style>
/* ── DARK THEME (default) ── */
:root {
  --bg:       #0d0f14;
  --surf:     #151820;
  --panel:    #1c2030;
  --border:   #2a3048;
  --acc:      #00c8a0;
  --acc2:     #ff6b6b;
  --acc3:     #7fa0ff;
  --text:     #e0e6f0;
  --muted:    #5a657a;
  --mono:     'Courier New', monospace;
  --r:        8px;
  --handle:   26px;
  --sidebar-w:270px;
}

/* ── LIGHT THEME OVERRIDES ──
   Covers both: data-theme="light" attribute (set by base.css toggle)
   and the OS-level prefers-color-scheme: light media query.           */
[data-theme="light"],
html[data-theme="light"],
body[data-theme="light"] {
  --bg:     #f0f2f7;
  --surf:   #ffffff;
  --panel:  #e8ebf2;
  --border: #c8cedd;
  --text:   #1a1e2e;
  --muted:  #7a859a;
}
@media (prefers-color-scheme: light) {
  :root {
    --bg:     #f0f2f7;
    --surf:   #ffffff;
    --panel:  #e8ebf2;
    --border: #c8cedd;
    --text:   #1a1e2e;
    --muted:  #7a859a;
  }
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

html,body{
  width:100%;height:100%;
  background:var(--bg);color:var(--text);
  font-family:'Segoe UI',system-ui,sans-serif;
  overflow:hidden;
  touch-action:none;
  -webkit-user-select:none;user-select:none;
}
#mv-root{display:flex;flex-direction:column;width:100vw;height:100vh;overflow:hidden;}

/* ── TOP BAR: 2 rows ── */
#mv-topbar{
  display:flex;flex-direction:column;
  padding:5px 10px;
  background:var(--surf);border-bottom:1px solid var(--border);
  flex-shrink:0;
  z-index:200;
}
.mv-toprow{
  display:flex;align-items:center;gap:6px;
  flex-wrap:nowrap;overflow-x:auto;
  scrollbar-width:none;
  padding:2px 0;
}
.mv-toprow::-webkit-scrollbar{display:none;}
/* hamburger placeholder in row1 */
.mv-toprow-placeholder{width:36px;height:28px;flex-shrink:0;}
.mv-aname{font-size:11px;color:var(--muted);white-space:nowrap;max-width:140px;overflow:hidden;text-overflow:ellipsis;}
.mv-sep{width:1px;height:20px;background:var(--border);flex-shrink:0;}
/* FIX 2: Increased padding for icon-only touch targets */
.mv-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:5px;
  padding:7px 13px;border-radius:6px;border:1px solid var(--border);
  background:var(--panel);color:var(--text);font-size:13px;
  cursor:pointer;white-space:nowrap;flex-shrink:0;
  transition:background .12s,border-color .12s,color .12s;
}
.mv-btn:hover{background:var(--border);border-color:var(--acc);color:var(--acc);}
.mv-btn.acc{background:var(--acc);border-color:var(--acc);color:#000;font-weight:700;}
.mv-btn.acc:hover{background:#00a884;}
.mv-btn.danger{border-color:var(--acc2);color:var(--acc2);}
.mv-btn.danger:hover{background:rgba(255,107,107,.15);}
.mv-btn.rec-active{background:var(--acc2);border-color:var(--acc2);color:#fff;animation:pulse 1s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.65}}

/* ── WORKSPACE ── */
#mv-workspace{flex:1;position:relative;overflow:hidden;}

/* ── CANVAS ── */
#mv-canvas-wrap{
  position:absolute;inset:0;
  display:flex;align-items:center;justify-content:center;
  background:var(--bg);
  overflow:hidden;
}
#mv-stage{
  position:relative;
  background-image:conic-gradient(#191c25 90deg,#101318 90deg 180deg,#191c25 180deg 270deg,#101318 270deg);
  background-size:20px 20px;
  border-radius:4px;
  box-shadow:0 0 0 1px var(--border),0 0 30px rgba(0,200,160,.06);
  overflow:hidden;
}
/* Light theme: lighter checkerboard for the canvas stage */
[data-theme="light"] #mv-stage,
html[data-theme="light"] #mv-stage {
  background-image:conic-gradient(#d8dce8 90deg,#c8ccda 90deg 180deg,#d8dce8 180deg 270deg,#c8ccda 270deg);
}
@media (prefers-color-scheme: light) {
  #mv-stage {
    background-image:conic-gradient(#d8dce8 90deg,#c8ccda 90deg 180deg,#d8dce8 180deg 270deg,#c8ccda 270deg);
  }
}
#mv-canvas{
  display:block;
  touch-action:none;
}

/* ── SIDEBAR FLYOUT ── */
#mv-sidebar{
  position:absolute;
  top:0;bottom:0;left:0;
  width:var(--sidebar-w);
  background:var(--surf);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  z-index:150;
  transform:translateX(calc(-1 * var(--sidebar-w)));
  transition:transform .25s cubic-bezier(.4,0,.2,1);
  overflow:hidden;
}
#mv-sidebar.open{transform:translateX(0);}

#mv-hamburger{
  position:absolute;
  top:8px;left:12px;
  z-index:160;
  width:36px;height:36px;
  display:flex;align-items:center;justify-content:center;
  background:var(--panel);
  border:1px solid var(--border);
  border-radius:8px;
  cursor:pointer;
  color:var(--text);font-size:16px;
  flex-shrink:0;
  transition:background .12s,color .12s;
}
#mv-hamburger:hover{background:var(--border);color:var(--acc);}

#mv-sidebar-tabs{
  display:flex;border-bottom:1px solid var(--border);flex-shrink:0;
}
.mv-tab{
  flex:1;text-align:center;padding:9px 4px;font-size:10px;font-weight:700;
  color:var(--muted);cursor:pointer;letter-spacing:.06em;text-transform:uppercase;
  border-bottom:2px solid transparent;transition:color .12s,border-color .12s;
}
.mv-tab.active{color:var(--acc);border-bottom-color:var(--acc);}

#mv-sidebar-body{flex:1;overflow-y:auto;padding:10px;}

/* Layer items */
.mv-li{
  display:flex;align-items:center;gap:7px;padding:6px 8px;
  border-radius:6px;border:1px solid transparent;
  margin-bottom:4px;cursor:pointer;background:var(--panel);
  transition:border-color .12s,background .12s;
}
.mv-li:hover{border-color:var(--border);}
.mv-li.sel{border-color:var(--acc);background:rgba(0,200,160,.07);}
.mv-badge{
  font-size:9px;font-weight:800;padding:2px 5px;border-radius:4px;
  text-transform:uppercase;flex-shrink:0;
}
.mv-badge.frame{background:rgba(127,160,255,.2);color:var(--acc3);}
.mv-badge.video{background:rgba(255,107,107,.2);color:var(--acc2);}
.mv-lname{font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
.mv-lz{font-size:10px;color:var(--muted);font-family:var(--mono);flex-shrink:0;}
.mv-ledit{
  width:22px;height:22px;border-radius:4px;border:none;
  background:rgba(255,107,107,.15);color:var(--acc2);
  font-size:11px;cursor:pointer;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.mv-ledit:hover{background:rgba(255,107,107,.3);}
.mv-ldel{
  width:22px;height:22px;border-radius:4px;border:none;
  background:rgba(255,107,107,.1);color:var(--acc2);
  font-size:11px;cursor:pointer;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.mv-ldel:hover{background:rgba(255,107,107,.35);}

/* ── ENTITY FORM MODAL (fullscreen iframe) ── */
#modal-entity{position:fixed;inset:0;z-index:9500;display:none;flex-direction:column;background:var(--bg);}
#modal-entity.open{display:flex;}
#modal-entity-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:8px 14px;background:var(--surf);border-bottom:1px solid var(--border);
  flex-shrink:0;
}
#modal-entity-header h3{font-size:13px;font-weight:700;}
#modal-entity iframe{flex:1;border:none;width:100%;height:100%;}

/* Props */
.mv-pg{margin-bottom:14px;}
.mv-pl{font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);
       margin-bottom:6px;font-weight:700;}
.mv-pr{display:flex;gap:5px;margin-bottom:5px;align-items:center;}
.mv-pr label{font-size:10px;color:var(--muted);width:38px;flex-shrink:0;}
.mv-in{
  flex:1;background:var(--panel);border:1px solid var(--border);
  border-radius:5px;color:var(--text);font-size:11px;
  padding:3px 6px;outline:none;font-family:var(--mono);
}
.mv-in:focus{border-color:var(--acc);}
.mv-in[type=range]{padding:0;cursor:pointer;accent-color:var(--acc);}
.mv-in[type=checkbox]{width:14px;height:14px;cursor:pointer;accent-color:var(--acc);flex:none;}
.mv-note{font-size:10px;color:var(--muted);margin-top:3px;line-height:1.4;}
hr.mv-hr{border:none;border-top:1px solid var(--border);margin:10px 0;}

/* ── LAYER CONTROLS BAR ── */
#mv-lc{
  display:none;
  position:absolute;bottom:0;left:0;right:0;
  align-items:center;gap:6px;padding:6px 14px;
  background:rgba(21,24,32,.95);
  border-top:1px solid var(--border);
  z-index:100;flex-wrap:wrap;
  min-height:120px !important;
}
#mv-lc .lc-lbl{font-size:10px;color:var(--muted);flex-shrink:0;}

/* ── MODALS ── */
.mv-mbg{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.78);z-index:9000;
  align-items:center;justify-content:center;
}
.mv-mbg.open{display:flex;}
.mv-modal{
  background:var(--surf);border:1px solid var(--border);
  border-radius:var(--r);width:92%;max-width:460px;
  max-height:88vh;display:flex;flex-direction:column;
  box-shadow:0 20px 60px rgba(0,0,0,.5);overflow:hidden;
}
.mv-modal.wide{max-width:600px;}
.mv-mh{
  padding:12px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.mv-mh h3{font-size:13px;font-weight:700;}
.mv-mx{background:none;border:none;color:var(--muted);font-size:22px;cursor:pointer;line-height:1;padding:0;}
.mv-mx:hover{color:var(--text);}
.mv-mb{flex:1;overflow-y:auto;padding:14px;}
.mv-mf{padding:10px 14px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0;}

/* Arrangement list items */
.mv-ai{
  padding:9px 11px;border:1px solid var(--border);border-radius:6px;
  margin-bottom:7px;cursor:pointer;transition:border-color .12s,background .12s;
}
.mv-ai:hover{border-color:var(--acc);background:rgba(0,200,160,.05);}
.mv-ai strong{font-size:12px;display:block;}
.mv-ai small{font-size:10px;color:var(--muted);}

/* Render jobs list */
.mv-job{
  padding:8px 10px;border:1px solid var(--border);border-radius:6px;
  margin-bottom:6px;font-size:11px;
}
.mv-job .job-st{font-weight:700;font-size:10px;padding:2px 6px;border-radius:4px;margin-left:6px;}
.mv-job .job-st.queued{background:rgba(127,160,255,.2);color:var(--acc3);}
.mv-job .job-st.processing{background:rgba(0,200,160,.2);color:var(--acc);animation:pulse 1s infinite;}
.mv-job .job-st.completed{background:rgba(0,200,160,.15);color:var(--acc);}
.mv-job .job-st.failed{background:rgba(255,107,107,.15);color:var(--acc2);}

/* ── VIDEO CLIP EDITOR ── */
.mvc-player{width:100%;border-radius:6px;background:#000;display:block;max-height:200px;}
.mvc-timeline{position:relative;height:32px;margin:10px 0;background:var(--panel);border-radius:6px;border:1px solid var(--border);}
.mvc-track{position:absolute;top:6px;bottom:6px;background:var(--acc);border-radius:4px;opacity:.35;}
.mvc-range{position:absolute;top:0;bottom:0;left:0;right:0;}
.mvc-handle{
  position:absolute;top:50%;transform:translate(-50%,-50%);
  width:18px;height:26px;background:var(--acc);border-radius:5px;
  cursor:ew-resize;z-index:2;border:2px solid rgba(0,0,0,.4);
  display:flex;align-items:center;justify-content:center;
}
.mvc-handle::after{content:'';width:2px;height:10px;background:rgba(0,0,0,.4);border-radius:1px;}
.mvc-handle.end-h{background:var(--acc2);}
.mvc-labels{display:flex;justify-content:space-between;font-size:10px;color:var(--muted);font-family:var(--mono);}

/* ── BROWSER MODALS (animatic + asset) ── */
.mv-browser-search{
  display:flex;gap:6px;margin-bottom:10px;align-items:center;
}
.mv-browser-search input{
  flex:1;background:var(--panel);border:1px solid var(--border);
  border-radius:5px;color:var(--text);font-size:11px;
  padding:5px 8px;outline:none;font-family:var(--mono);
  -webkit-user-select:text;user-select:text;touch-action:auto;
}
.mv-browser-search input:focus{border-color:var(--acc);}
.mv-browser-list{min-height:120px;}
.mv-brow-item{
  display:flex;align-items:center;gap:9px;
  padding:7px 9px;border:1px solid var(--border);border-radius:6px;
  margin-bottom:6px;cursor:pointer;background:var(--panel);
  transition:border-color .12s,background .12s;
}
.mv-brow-item:hover{border-color:var(--acc);background:rgba(0,200,160,.05);}
.mv-brow-thumb{
  width:44px;height:44px;object-fit:cover;border-radius:4px;
  background:var(--bg);flex-shrink:0;border:1px solid var(--border);
}
.mv-brow-thumb-placeholder{
  width:44px;height:44px;border-radius:4px;
  background:var(--bg);flex-shrink:0;border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;color:var(--muted);
}
.mv-brow-info{flex:1;min-width:0;}
.mv-brow-info strong{font-size:11px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mv-brow-info small{font-size:9px;color:var(--muted);}
.mv-pagination{
  display:flex;align-items:center;gap:6px;justify-content:center;
  padding:8px 0 0;border-top:1px solid var(--border);margin-top:8px;
}
.mv-pagination button{
  padding:4px 10px;border-radius:5px;border:1px solid var(--border);
  background:var(--panel);color:var(--text);font-size:11px;cursor:pointer;
}
.mv-pagination button:hover{border-color:var(--acc);color:var(--acc);}
.mv-pagination button:disabled{opacity:.35;cursor:default;}
.mv-pg-idx{
  width:42px;text-align:center;
  background:var(--panel);border:1px solid var(--border);
  border-radius:5px;color:var(--text);font-size:11px;
  padding:3px 5px;outline:none;font-family:var(--mono);
  -webkit-user-select:text;user-select:text;touch-action:auto;
}
.mv-pg-idx:focus{border-color:var(--acc);}
.mv-pg-total{font-size:10px;color:var(--muted);}

/* Asset type chooser */
.mv-type-choice{
  display:flex;gap:8px;margin-bottom:12px;
}
.mv-type-btn{
  flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);
  background:var(--panel);color:var(--text);font-size:11px;font-weight:700;
  cursor:pointer;text-align:center;transition:border-color .12s,background .12s,color .12s;
}
.mv-type-btn.active{border-color:var(--acc);background:rgba(0,200,160,.1);color:var(--acc);}

/* ── TOAST ── */
#mv-toast{
  position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(8px);
  background:var(--panel);border:1px solid var(--acc);color:var(--text);
  padding:8px 16px;border-radius:7px;font-size:12px;
  z-index:99999;opacity:0;pointer-events:none;
  transition:opacity .2s,transform .2s;white-space:nowrap;
}
#mv-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
#mv-toast.err{border-color:var(--acc2);}

/* ── REC INDICATOR ── */
#mv-rec-ind{
  display:none;position:fixed;top:12px;left:50%;transform:translateX(-50%);
  background:rgba(220,50,50,.9);border-radius:20px;padding:4px 12px;
  font-size:11px;font-weight:700;color:#fff;z-index:9999;
  align-items:center;gap:6px;pointer-events:none;
}
.rec-dot{width:7px;height:7px;border-radius:50%;background:#fff;animation:pulse 1s infinite;}

/* Scrollbars */
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:var(--surf);}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px;}
</style>

<!-- Recording indicator -->
<div id="mv-rec-ind"><div class="rec-dot"></div><span id="mv-rec-time">REC 0:00</span></div>

<div id="mv-root">
  <!-- TOP BAR: 2 rows -->
  <div id="mv-topbar">
    <!-- Row 1: navigation + animatic name + core actions -->
    <!-- FIX 2: Icon-only buttons — text labels removed, title kept for tooltip -->
    <div class="mv-toprow">
      <div class="mv-toprow-placeholder"></div>
      <span class="mv-aname" id="mv-aname-lbl"><?= $animaticId > 0 ? htmlspecialchars($animatic['name']) : '— no animatic —' ?></span>
      <button class="mv-btn" id="btn-browse-animatic" title="Browse / switch animatic"><i class="fa fa-folder"></i></button>
      <div class="mv-sep"></div>
      <button class="mv-btn" id="btn-new" title="New arrangement"><i class="fa fa-file"></i></button>
      <button class="mv-btn" id="btn-save" title="Save arrangement"><i class="fa fa-floppy-disk"></i></button>
      <button class="mv-btn" id="btn-load" title="Load arrangement"><i class="fa fa-folder-open"></i></button>
      <div class="mv-sep"></div>
      <button class="mv-btn" id="btn-add-asset" title="Assign asset layer"><i class="fa fa-plus"></i></button>
    </div>
    <!-- Row 2: playback + render/jobs + settings + assets -->
    <div class="mv-toprow">
      <div class="mv-toprow-placeholder"></div>
      <button class="mv-btn acc" id="btn-preview" title="Preview animation"><i class="fa fa-play"></i></button>
      <button class="mv-btn" id="btn-record" title="Record to video"><i class="fa fa-circle"></i></button>
      <div class="mv-sep"></div>
      <button class="mv-btn" id="btn-jobs" title="Render &amp; Jobs"><i class="fa fa-film"></i></button>
      <div class="mv-sep"></div>
      <button class="mv-btn" id="btn-settings" title="Camera &amp; parallax settings"><i class="fa fa-sliders"></i></button>
      <button class="mv-btn" id="btn-reload" title="Reload assets"><i class="fa fa-rotate"></i></button>
      <button class="mv-btn" id="btn-entity-form" title="Edit animatic entity form">🎬</button>
    </div>
  </div>

  <!-- WORKSPACE -->
  <div id="mv-workspace">
    <!-- Canvas area -->
    <div id="mv-canvas-wrap">
      <div id="mv-stage"><canvas id="mv-canvas"></canvas></div>
    </div>

    <!-- Sidebar (flyout, left) -->
    <div id="mv-sidebar">
      <div id="mv-sidebar-tabs">
        <div class="mv-tab active" data-tab="layers">Layers</div>
        <div class="mv-tab" data-tab="props">Props</div>
        <div class="mv-tab" data-tab="camera">Camera</div>
      </div>
      <div id="mv-sidebar-body">
        <p style="font-size:11px;color:var(--muted);padding:6px 0;">Loading…</p>
      </div>
    </div>

    <!-- Hamburger (absolute, always visible) -->
    <div id="mv-hamburger"><i class="fa fa-bars"></i></div>

    <!-- Layer controls bar (bottom) -->
    <div id="mv-lc">
      <span class="lc-lbl">Layer:</span>
      <button class="mv-btn" id="lc-up" title="Move Up"><i class="fa fa-arrow-up"></i></button>
      <button class="mv-btn" id="lc-down" title="Move Down"><i class="fa fa-arrow-down"></i></button>
      <button class="mv-btn" id="lc-top" title="To Top"><i class="fa fa-angles-up"></i></button>
      <button class="mv-btn" id="lc-bot" title="To Bottom"><i class="fa fa-angles-down"></i></button>
      <div class="mv-sep"></div>
      <button class="mv-btn" id="lc-fit" title="Fit canvas"><i class="fa fa-expand"></i></button>
      <button class="mv-btn" id="lc-1" title="Scale 1:1"><i class="fa fa-compress"></i></button>
      <button class="mv-btn" id="lc-center" title="Center"><i class="fa fa-crosshairs"></i></button>
      <div class="mv-sep"></div>
      <button class="mv-btn" id="lc-phys" title="Layer Physics"><i class="fa fa-atom"></i> Physics</button>
    </div>
  </div>
</div>

<div id="mv-toast"></div>

<!-- ─── MODALS ─── -->

<!-- Load Arrangement -->
<div id="modal-load" class="mv-mbg">
  <div class="mv-modal">
    <div class="mv-mh"><h3>Load Arrangement</h3><button class="mv-mx" data-close="modal-load">×</button></div>
    <div class="mv-mb" id="arr-list"></div>
    <div class="mv-mf"><button class="mv-btn" data-close="modal-load">Cancel</button></div>
  </div>
</div>

<!-- Settings -->
<div id="modal-settings" class="mv-mbg">
  <div class="mv-modal">
    <div class="mv-mh"><h3>Camera &amp; Parallax Settings</h3><button class="mv-mx" data-close="modal-settings">×</button></div>
    <div class="mv-mb">
      <div class="mv-pg">
        <div class="mv-pl">Duration &amp; FPS</div>
        <div class="mv-pr"><label>Duration</label><input id="s-dur" type="number" class="mv-in" value="3000" min="500" step="100"> <span style="font-size:10px;color:var(--muted)">ms</span></div>
        <div class="mv-pr"><label>FPS</label><input id="s-fps" type="number" class="mv-in" value="30" min="10" max="60"></div>
      </div>
      <div class="mv-pg">
        <div class="mv-pl">Camera Movement</div>
        <div class="mv-pr"><label>Pan X</label><input id="s-mx" type="number" class="mv-in" value="80"> <span style="font-size:10px;color:var(--muted)">px</span></div>
        <div class="mv-pr"><label>Tilt Y</label><input id="s-my" type="number" class="mv-in" value="0"> <span style="font-size:10px;color:var(--muted)">px</span></div>
      </div>
      <div class="mv-pg">
        <div class="mv-pl">Zoom</div>
        <div class="mv-pr"><label>Start</label><input id="s-zs" type="number" class="mv-in" value="1.0" step="0.01" min="0.1"></div>
        <div class="mv-pr"><label>End</label><input id="s-ze" type="number" class="mv-in" value="1.04" step="0.01" min="0.1"></div>
        <p class="mv-note">⚠ Never set to 0.</p>
      </div>
      <div class="mv-pg" style="background:rgba(0,200,160,.05);border:1px solid rgba(0,200,160,.15);border-radius:7px;padding:10px;">
        <div class="mv-pl">Parallax Physics</div>
        <div class="mv-pr"><label>Focal</label><input id="s-focal" type="number" class="mv-in" value="10" step="0.5"> <span style="font-size:10px;color:var(--muted)">m</span></div>
        <div class="mv-pr"><label>Frustum</label><input id="s-frust" type="number" class="mv-in" value="10" step="0.5"> <span style="font-size:10px;color:var(--muted)">m</span></div>
        <div class="mv-pr"><label>Scale ref</label><input id="s-sref" type="number" class="mv-in" value="10" step="0.5"> <span style="font-size:10px;color:var(--muted)">m</span></div>
      </div>
      <div class="mv-pg">
        <div class="mv-pl">Canvas Resolution</div>
        <div class="mv-pr"><label>Width</label><input id="s-cw" type="number" class="mv-in" value="1024" step="64"></div>
        <div class="mv-pr"><label>Height</label><input id="s-ch" type="number" class="mv-in" value="1024" step="64"></div>
      </div>
    </div>
    <div class="mv-mf">
      <button class="mv-btn" data-close="modal-settings">Cancel</button>
      <button class="mv-btn acc" id="btn-save-settings">Save</button>
    </div>
  </div>
</div>

<!-- Layer Physics Modal -->
<div id="modal-phys" class="mv-mbg">
  <div class="mv-modal">
    <div class="mv-mh"><h3>Layer Physics &amp; Attributes</h3><button class="mv-mx" data-close="modal-phys">×</button></div>
    <div class="mv-mb">
      <input type="hidden" id="lp-type"><input type="hidden" id="lp-id">
      <div class="mv-pg" style="background:rgba(0,200,160,.05);border:1px solid rgba(0,200,160,.15);border-radius:7px;padding:10px;">
        <div class="mv-pl">Physical Distance &amp; Scale</div>
        <div class="mv-pr"><label>Distance</label><input id="lp-dist" type="number" class="mv-in" value="10" step="0.5" min="0.1"> <span style="font-size:10px;color:var(--muted)">m</span></div>
        <div class="mv-pr"><label>Real H</label><input id="lp-rh" type="number" class="mv-in" placeholder="e.g. 1.8 for human" step="0.1"> <span style="font-size:10px;color:var(--muted)">m</span></div>
        <button class="mv-btn" id="lp-calc" style="width:100%;justify-content:center;margin-top:6px;">
          <i class="fa fa-calculator"></i> Auto-Calculate Scale &amp; Speed from Physics
        </button>
        <p class="mv-note">Sets scale so the layer occupies the correct physical height, and speed = focal/distance.</p>
      </div>
      <hr class="mv-hr">
      <div class="mv-pg">
        <div class="mv-pl">Parallax Speed</div>
        <div class="mv-pr"><label>Speed</label><input id="lp-spd" type="number" class="mv-in" value="0.5" step="0.01" min="0" max="5"></div>
        <p class="mv-note">0 = static/infinite depth · 1 = full cam speed · Auto-set by distance calc.</p>
      </div>
      <div class="mv-pg">
        <div class="mv-pl">Z-Index (render order)</div>
        <div class="mv-pr"><label>Z</label><input id="lp-z" type="number" class="mv-in" value="0"></div>
      </div>
      <div class="mv-pg">
        <div class="mv-pl">Opacity</div>
        <div class="mv-pr">
          <label>Alpha</label>
          <input id="lp-op" type="range" class="mv-in" min="0" max="1" step="0.01" value="1">
          <span id="lp-opv" style="font-size:10px;font-family:var(--mono);flex-shrink:0;width:28px;text-align:right;">1.0</span>
        </div>
      </div>
    </div>
    <div class="mv-mf">
      <button class="mv-btn" data-close="modal-phys">Cancel</button>
      <button class="mv-btn acc" id="lp-save">Save Layer</button>
    </div>
  </div>
</div>

<!-- Video Clip Editor Modal -->
<div id="modal-clip" class="mv-mbg">
  <div class="mv-modal wide">
    <div class="mv-mh"><h3>Video Clip Editor</h3><button class="mv-mx" data-close="modal-clip">×</button></div>
    <div class="mv-mb">
      <input type="hidden" id="vc-id">
      <video id="vc-player" class="mvc-player" controls muted loop playsinline></video>

      <div style="margin:10px 0 4px;">
        <div class="mv-pl">Clip Range (trim in/out)</div>
        <div class="mvc-timeline" id="vc-timeline">
          <div class="mvc-track" id="vc-track"></div>
          <div class="mvc-handle" id="vc-hstart" title="Start"></div>
          <div class="mvc-handle end-h" id="vc-hend" title="End"></div>
        </div>
        <div class="mvc-labels">
          <span id="vc-t-start">0.00s</span>
          <span id="vc-t-dur" style="color:var(--acc);">● full</span>
          <span id="vc-t-end">–</span>
        </div>
      </div>

      <hr class="mv-hr">
      <div class="mv-pg">
        <div class="mv-pl">Playback Speed</div>
        <div class="mv-pr">
          <label>Speed</label>
          <input id="vc-spd" type="range" class="mv-in" min="0.1" max="3.0" step="0.05" value="1.0">
          <span id="vc-spdv" style="font-size:11px;font-family:var(--mono);flex-shrink:0;width:32px;text-align:right;">1.0×</span>
        </div>
        <div class="mv-pr">
          <label></label>
          <button class="mv-btn" id="vc-preview-btn" style="font-size:10px;"><i class="fa fa-play"></i> Test in Player</button>
        </div>
      </div>
      <div id="vc-info" style="font-size:10px;color:var(--muted);margin-top:4px;"></div>
    </div>
    <div class="mv-mf">
      <button class="mv-btn" id="vc-clear">Clear Trim</button>
      <button class="mv-btn" data-close="modal-clip">Cancel</button>
      <button class="mv-btn acc" id="vc-save">Apply Clip Settings</button>
    </div>
  </div>
</div>

<!-- Render / Jobs Modal (merged) -->
<div id="modal-jobs" class="mv-mbg">
  <div class="mv-modal">
    <div class="mv-mh"><h3>Render &amp; Jobs</h3><button class="mv-mx" data-close="modal-jobs">×</button></div>
    <div class="mv-mb">
      <!-- Render trigger section -->
      <div style="background:rgba(0,200,160,.05);border:1px solid rgba(0,200,160,.15);border-radius:7px;padding:10px;margin-bottom:12px;">
        <div class="mv-pl">Queue Offline Render</div>
        <p style="font-size:10px;color:var(--muted);margin-bottom:8px;line-height:1.5;">Composites all layers server-side via PyAPI and produces a clean MP4. Requires a saved arrangement.</p>
        <button class="mv-btn acc" id="btn-render-now" style="width:100%;justify-content:center;">
          <i class="fa fa-film"></i> Queue Render for Current Arrangement
        </button>
      </div>
      <!-- Jobs list -->
      <div class="mv-pl">Recent Jobs</div>
      <div id="jobs-list"><p style="font-size:11px;color:var(--muted);">Loading…</p></div>
    </div>
    <div class="mv-mf">
      <button class="mv-btn" id="btn-jobs-refresh"><i class="fa fa-rotate"></i> Refresh</button>
      <button class="mv-btn acc" id="btn-run-queue" title="Trigger job queue processor now"><i class="fa fa-bolt"></i> Run Now</button>
      <span id="run-queue-result" style="font-size:10px;font-family:var(--mono);align-self:center;"></span>
      <button class="mv-btn" data-close="modal-jobs">Close</button>
    </div>
  </div>
</div>

<!-- Animatic Browser Modal -->
<div id="modal-animatic-browser" class="mv-mbg">
  <div class="mv-modal wide">
    <div class="mv-mh"><h3>Animatic Browser</h3><button class="mv-mx" data-close="modal-animatic-browser">×</button></div>
    <div class="mv-mb">
      <div class="mv-browser-search">
        <input type="text" id="ab-search" placeholder="Search by ID or name…" autocomplete="off">
        <button class="mv-btn acc" id="ab-create-btn"><i class="fa fa-plus"></i> New</button>
      </div>
      <!-- New animatic form (hidden by default) -->
      <div id="ab-create-form" style="display:none;background:rgba(0,200,160,.05);border:1px solid rgba(0,200,160,.2);border-radius:6px;padding:10px;margin-bottom:10px;">
        <div class="mv-pl">Create New Animatic</div>
        <div class="mv-pr" style="margin-top:6px;">
          <label>Name</label>
          <input type="text" id="ab-new-name" class="mv-in" placeholder="Animatic name" style="-webkit-user-select:text;user-select:text;touch-action:auto;">
        </div>
        <div style="display:flex;gap:6px;margin-top:8px;justify-content:flex-end;">
          <button class="mv-btn" id="ab-create-cancel">Cancel</button>
          <button class="mv-btn acc" id="ab-create-save"><i class="fa fa-floppy-disk"></i> Create</button>
        </div>
      </div>
      <div class="mv-browser-list" id="ab-list"><p style="font-size:11px;color:var(--muted);">Loading…</p></div>
      <div class="mv-pagination" id="ab-pagination">
        <button id="ab-prev" disabled><i class="fa fa-chevron-left"></i></button>
        <input type="number" id="ab-page" class="mv-pg-idx" value="1" min="1">
        <span class="mv-pg-total" id="ab-total">/ 1</span>
        <button id="ab-next"><i class="fa fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="mv-mf"><button class="mv-btn" data-close="modal-animatic-browser">Cancel</button></div>
  </div>
</div>

<!-- Asset Assignment Modal (frame or video layer) -->
<div id="modal-assign" class="mv-mbg">
  <div class="mv-modal wide">
    <div class="mv-mh"><h3>Assign Layer</h3><button class="mv-mx" data-close="modal-assign">×</button></div>
    <div class="mv-mb">
      <div class="mv-type-choice">
        <div class="mv-type-btn active" id="assign-tab-frame" data-type="frame"><i class="fa fa-image"></i> Frame Layer</div>
        <div class="mv-type-btn" id="assign-tab-video" data-type="video"><i class="fa fa-film"></i> Video Layer</div>
      </div>
      <div class="mv-browser-search">
        <input type="text" id="as-search" placeholder="Search by ID or name…" autocomplete="off">
      </div>
      <div class="mv-browser-list" id="as-list"><p style="font-size:11px;color:var(--muted);">Loading…</p></div>
      <div class="mv-pagination" id="as-pagination">
        <button id="as-prev" disabled><i class="fa fa-chevron-left"></i></button>
        <input type="number" id="as-page" class="mv-pg-idx" value="1" min="1">
        <span class="mv-pg-total" id="as-total">/ 1</span>
        <button id="as-next"><i class="fa fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="mv-mf"><button class="mv-btn" data-close="modal-assign">Cancel</button></div>
  </div>
</div>

<!-- Entity Form Modal (fullscreen iframe) -->
<div id="modal-entity">
  <div id="modal-entity-header">
    <h3 id="modal-entity-title">Animatic Entity Form</h3>
    <button class="mv-mx" id="btn-entity-close">×</button>
  </div>
  <iframe id="modal-entity-iframe" src="" allowfullscreen></iframe>
</div>

<?= $spw->getJquery() ?>

<script>
(function(){
'use strict';

// ── CONFIG ───────────────────────────────────────────────────────────────────
let ANIMATIC_ID  = <?= (int)$jsAnimaticId ?>;
const API          = 'animatic_multiplane_api.php';
let CANVAS_W       = 1024;
let CANVAS_H       = 1024;
let activeArrId    = <?= $jsInitialArrId ?>;
let savedConfig    = <?= ($jsInitialConfig && $jsInitialConfig !== 'null') ? $jsInitialConfig : 'null' ?>;

let G = { focal:10, frustum:10, scaleref:10, movex:80, movey:0, zoomst:1.0, zoomen:1.04, durms:3000, fps:30 };

// ── LAYERS STATE ─────────────────────────────────────────────────────────────
let layers   = [];
let selKey   = null;
let activeTab= 'layers';
let sidebarOpen = false;

// ── CANVAS ───────────────────────────────────────────────────────────────────
const canvas = document.getElementById('mv-canvas');
const ctx    = canvas.getContext('2d');
let   raf    = null;
let   previewActive = false;
let   previewStart  = null;

function initCanvas(w, h){
  CANVAS_W = w; CANVAS_H = h;
  canvas.width  = w;
  canvas.height = h;
  const stage = document.getElementById('mv-stage');
  stage.style.width  = w+'px';
  stage.style.height = h+'px';
  fitCanvas();
}

function fitCanvas(){
  const wrap   = document.getElementById('mv-canvas-wrap');
  const aw     = wrap.clientWidth  - 20;
  const ah     = wrap.clientHeight - 20;
  const scale  = Math.min(aw / CANVAS_W, ah / CANVAS_H, 1.0);
  const stage  = document.getElementById('mv-stage');
  
  stage.style.transform       = `scale(${scale})`;
  stage.style.transformOrigin = 'top left';
  
  stage.style.position = 'absolute';
  const scaledW = CANVAS_W * scale;
  const scaledH = CANVAS_H * scale;
  stage.style.left = Math.round((wrap.clientWidth  - scaledW) / 2) + 'px';
  stage.style.top  = Math.round((wrap.clientHeight - scaledH) / 2) + 'px';
}
window.addEventListener('resize', fitCanvas);

// ── RENDER LOOP ───────────────────────────────────────────────────────────────
function render(t){
  t = t || 0;
  ctx.clearRect(0,0,CANVAS_W,CANVAS_H);

  const camX = G.movex * t;
  const camY = G.movey * t;
  const zoom = G.zoomst + (G.zoomen - G.zoomst) * t;

  const sorted = [...layers].sort((a,b) => a.zIndex - b.zIndex);

  for(const L of sorted){
    const el = L.el;
    if(!el) continue;
    if(el.tagName === 'IMG'   && !el.complete)     continue;
    if(el.tagName === 'VIDEO' && el.readyState < 2) continue;

    const nw = el.naturalWidth  || el.videoWidth  || 1;
    const nh = el.naturalHeight || el.videoHeight || 1;
    const sw = nw * L.scaleX;
    const sh = nh * L.scaleY;

    const shiftX = -(camX * L.speed);
    const shiftY = -(camY * L.speed);
    const dx = L.x + shiftX;
    const dy = L.y + shiftY;
    const cx = dx + sw / 2;
    const cy = dy + sh / 2;

    ctx.save();
    ctx.globalAlpha = L.opacity;
    if(zoom !== 1.0){
      ctx.translate(CANVAS_W/2, CANVAS_H/2);
      ctx.scale(zoom, zoom);
      ctx.translate(-CANVAS_W/2, -CANVAS_H/2);
    }
    ctx.translate(cx, cy);
    ctx.rotate(L.rotation * Math.PI / 180);
    ctx.translate(-cx, -cy);
    ctx.drawImage(el, dx, dy, sw, sh);
    ctx.restore();

    if(!previewActive && L.key === selKey){
      drawSel(dx, dy, sw, sh, L.rotation);
    }
  }
}

const HS = 26;

function drawSel(x, y, w, h, rot){
  const cx = x + w/2, cy = y + h/2;

  ctx.save();
  ctx.translate(cx, cy);
  ctx.rotate(rot * Math.PI / 180);
  ctx.translate(-cx, -cy);

  ctx.strokeStyle = 'rgba(0,200,160,.9)';
  ctx.lineWidth   = 1.5;
  ctx.setLineDash([5,3]);
  ctx.strokeRect(x,y,w,h);
  ctx.setLineDash([]);

  const s = HS;
  const corners = [[x,y],[x+w,y],[x,y+h]];
  ctx.fillStyle = '#00c8a0';
  for(const [hx,hy] of corners){
    ctx.fillRect(hx - s/2, hy - s/2, s, s);
    ctx.strokeStyle = 'rgba(0,0,0,.5)';
    ctx.lineWidth = 1.5;
    ctx.setLineDash([]);
    ctx.beginPath();
    ctx.moveTo(hx-5,hy-5); ctx.lineTo(hx+5,hy+5);
    ctx.moveTo(hx+5,hy-5); ctx.lineTo(hx-5,hy+5);
    ctx.stroke();
  }
  ctx.fillStyle = '#ff6b6b';
  ctx.fillRect(x+w - s/2, y+h - s/2, s, s);
  ctx.strokeStyle = 'rgba(0,0,0,.5)';
  ctx.lineWidth = 1.5;
  const bx = x+w, by = y+h;
  ctx.beginPath();
  ctx.moveTo(bx-5,by-5); ctx.lineTo(bx+5,by+5);
  ctx.moveTo(bx+5,by-5); ctx.lineTo(bx-5,by+5);
  ctx.stroke();

  ctx.restore();
}

function getCurrentZoom(){
  return G.zoomst;
}

// FIX 3: The RAF loop now NEVER exits via return — it always re-queues itself.
// Previously, when el >= 1.0 the loop returned without calling requestAnimationFrame
// again, killing the loop entirely. Subsequent preview clicks set previewActive=true
// but no loop was running to act on it. Fix: remove the early return; let the loop
// tick every frame regardless, only toggling previewActive when the clip ends.
function startLoop(){
  if(raf) cancelAnimationFrame(raf);
  function loop(){
    if(previewActive){
      const el = (performance.now() - previewStart) / G.durms;
      if(el >= 1.0){
        previewActive = false;
        restartVideos();
        render(0);
      } else {
        render(el);
      }
    } else {
      render(0);
    }
    raf = requestAnimationFrame(loop);
  }
  raf = requestAnimationFrame(loop);
}

// ── ASSET LOADING ─────────────────────────────────────────────────────────────
function loadAssets(){
  if(!ANIMATIC_ID) return Promise.resolve();
  return fetch(`${API}?action=get_assets&animatic_id=${ANIMATIC_ID}`)
    .then(r=>r.json())
    .then(data=>{
      if(!data.success) throw new Error(data.message);
      const newL =[];

      (data.frames||[]).forEach(f=>{
        const key  = `frame_${f.id}`;
        const prev = layers.find(l=>l.key===key);
        const el   = prev ? prev.el : mkImg('/'+f.filename);
        const cfg  = (savedConfig && savedConfig[key]) || {};
        newL.push({
          key, asset_type:'frame', asset_id:f.id,
          filename:f.filename, name:f.name||f.filename,
          x: cfg.x ?? 0, y: cfg.y ?? 0,
          scaleX: cfg.scaleX ?? 1, scaleY: cfg.scaleY ?? 1,
          rotation: cfg.rotation ?? 0,
          zIndex: cfg.zIndex ?? +f.z_index,
          opacity: cfg.opacity ?? +f.opacity,
          speed: +f.speed,
          start_offset: +f.start_offset, end_offset: f.end_offset,
          playback_speed: +f.playback_speed, duration_s: 0,
          el,
        });
      });

      (data.videos||[]).forEach(v=>{
        const key  = `video_${v.id}`;
        const prev = layers.find(l=>l.key===key);
        const el   = prev ? prev.el : mkVid('/'+v.filename, +v.start_offset, +v.playback_speed);
        const cfg  = (savedConfig && savedConfig[key]) || {};
        newL.push({
          key, asset_type:'video', asset_id:v.id,
          filename:v.filename, name:v.name||v.filename,
          mime_type: v.mime_type,
          x: cfg.x ?? 0, y: cfg.y ?? 0,
          scaleX: cfg.scaleX ?? 1, scaleY: cfg.scaleY ?? 1,
          rotation: cfg.rotation ?? 0,
          zIndex: cfg.zIndex ?? +v.z_index,
          opacity: cfg.opacity ?? +v.opacity,
          speed: +v.speed,
          start_offset: +v.start_offset, end_offset: v.end_offset,
          playback_speed: +v.playback_speed, duration_s: +v.duration_s,
          el,
        });
      });

      if(selKey && !newL.find(l=>l.key===selKey)) selKey=null;
      layers = newL;
      rebuildPanel();
      toast(`Loaded ${layers.length} assets`);
    })
    .catch(e=>toast('Asset load error: '+e.message, true));
}

function mkImg(src){
  const img = new Image();
  img.crossOrigin='anonymous';
  img.src = src;
  return img;
}

function mkVid(src, startOffset, pbSpeed){
  const v = document.createElement('video');
  v.src = src;
  v.loop = true; v.muted = true; v.playsInline = true;
  v.crossOrigin = 'anonymous';
  v.playbackRate = pbSpeed || 1.0;
  v.addEventListener('loadedmetadata', ()=>{
    if(startOffset && startOffset > 0) v.currentTime = startOffset;
    v.play().catch(()=>{});
  });
  v.play().catch(()=>{});
  return v;
}

function restartVideos(){
  layers.filter(l=>l.el.tagName==='VIDEO').forEach(L=>{
    const v = L.el;
    v.playbackRate = L.playback_speed || 1.0;
    v.currentTime  = L.start_offset  || 0;
    v.play().catch(()=>{});
  });
}

// ── DRAG & SCALE HANDLING ─────────────────────────────────────────────────────
let drag = null;

function stageToCanvas(clientX, clientY){
  const stage = document.getElementById('mv-stage');
  const rect   = stage.getBoundingClientRect();
  const scaleX = CANVAS_W / rect.width;
  const scaleY = CANVAS_H / rect.height;
  return {
    x: (clientX - rect.left) * scaleX,
    y: (clientY - rect.top)  * scaleY,
  };
}

function scaleHandleHit(L, cx, cy){
  const nw  = (L.el.naturalWidth  || L.el.videoWidth  || 1) * L.scaleX;
  const nh  = (L.el.naturalHeight || L.el.videoHeight || 1) * L.scaleY;
  const hx  = L.x + nw;
  const hy  = L.y + nh;
  const d   = HS * 1.5;
  return Math.abs(cx - hx) < d && Math.abs(cy - hy) < d;
}

function layerAtPoint(cx, cy){
  const sorted = [...layers].sort((a,b) => b.zIndex - a.zIndex);
  for(const L of sorted){
    if(!L.el) continue;
    const nw = (L.el.naturalWidth  || L.el.videoWidth  || 1) * L.scaleX;
    const nh = (L.el.naturalHeight || L.el.videoHeight || 1) * L.scaleY;
    if(cx >= L.x && cx <= L.x+nw && cy >= L.y && cy <= L.y+nh) return L;
  }
  return null;
}

canvas.addEventListener('pointerdown', e=>{
  if(previewActive) return;
  if(!selKey) return;
  const L = layers.find(l=>l.key===selKey);
  if(!L) return;

  const {x,y} = stageToCanvas(e.clientX, e.clientY);

  const nw = (L.el.naturalWidth  || L.el.videoWidth  || 1) * L.scaleX;
  const nh = (L.el.naturalHeight || L.el.videoHeight || 1) * L.scaleY;
  const isScale = scaleHandleHit(L, x, y);
  const inside = x >= L.x && x <= L.x+nw && y >= L.y && y <= L.y+nh;

  if(!isScale && !inside) return;

  e.preventDefault();
  canvas.setPointerCapture(e.pointerId);

  if(isScale){
    drag = {
      mode:'scale', key:selKey, ptId:e.pointerId,
      startX:x, startY:y,
      origScaleX:L.scaleX, origScaleY:L.scaleY,
      origNW: L.el.naturalWidth  || L.el.videoWidth  || 1,
      origNH: L.el.naturalHeight || L.el.videoHeight || 1,
    };
  } else {
    drag = {
      mode:'move', key:selKey, ptId:e.pointerId,
      startX:x, startY:y,
      origX:L.x, origY:L.y,
    };
  }
}, {passive:false});

canvas.addEventListener('pointermove', e=>{
  if(!drag) return;
  e.preventDefault();
  const {x,y} = stageToCanvas(e.clientX, e.clientY);
  const L = layers.find(l=>l.key===drag.key);
  if(!L) return;

  if(drag.mode==='move'){
    L.x = drag.origX + (x - drag.startX);
    L.y = drag.origY + (y - drag.startY);
  } else {
    const tlX = L.x, tlY = L.y;
    const newW = Math.max(20, x - tlX);
    const newH = Math.max(20, y - tlY);

    if(propKeepRatio()){
      const ratio = drag.origNW / drag.origNH;
      const baseW = newW;
      const scaleFromW = baseW / drag.origNW;
      L.scaleX = scaleFromW;
      L.scaleY = scaleFromW;
    } else {
      L.scaleX = newW / drag.origNW;
      L.scaleY = newH / drag.origNH;
    }
    syncPropsPanel();
  }
}, {passive:false});

canvas.addEventListener('pointerup', e=>{
  if(drag){ drag=null; rebuildPanel(); }
});
canvas.addEventListener('pointercancel', e=>{ drag=null; });

function propKeepRatio(){
  const cb = document.getElementById('p-kr');
  return cb ? cb.checked : true;
}

// ── CONFIG SNAPSHOT ───────────────────────────────────────────────────────────
function getConfig(){
  const c={};
  layers.forEach(L=>{
    c[L.key]={
      x:Math.round(L.x), y:Math.round(L.y),
      scaleX:+L.scaleX.toFixed(4), scaleY:+L.scaleY.toFixed(4),
      rotation:+L.rotation.toFixed(2),
      zIndex:L.zIndex, opacity:+L.opacity.toFixed(3),
    };
  });
  return c;
}

// ── SETTINGS ──────────────────────────────────────────────────────────────────
function loadSettings(){
  if(!ANIMATIC_ID) return Promise.resolve();
  return fetch(`${API}?action=get_settings&animatic_id=${ANIMATIC_ID}`)
    .then(r=>r.json())
    .then(d=>{
      if(!d.success) return;
      const s = d.data;
      G.focal    = parseFloat(s.focal_distance)  || 10;
      G.frustum  = parseFloat(s.frustum_height)  || 10;
      G.scaleref = parseFloat(s.scale_reference) || 10;
      G.movex    = parseInt(s.move_x)   || 80;
      G.movey    = parseInt(s.move_y)   || 0;
      G.zoomst   = parseFloat(s.zoom_start) || 1.0;
      G.zoomen   = parseFloat(s.zoom_end)   || 1.04;
      G.durms    = parseInt(s.duration_ms)  || 3000;
      G.fps      = parseInt(s.fps)          || 30;
      initCanvas(parseInt(s.canvas_width)||1024, parseInt(s.canvas_height)||1024);
      populateSettingsModal(s);
    });
}

function populateSettingsModal(s){
  document.getElementById('s-dur').value   = s.duration_ms || 3000;
  document.getElementById('s-fps').value   = s.fps || 30;
  document.getElementById('s-mx').value    = s.move_x || 80;
  document.getElementById('s-my').value    = s.move_y || 0;
  document.getElementById('s-zs').value    = s.zoom_start || 1.0;
  document.getElementById('s-ze').value    = s.zoom_end || 1.04;
  document.getElementById('s-focal').value = s.focal_distance || 10;
  document.getElementById('s-frust').value = s.frustum_height || 10;
  document.getElementById('s-sref').value  = s.scale_reference || 10;
  document.getElementById('s-cw').value    = s.canvas_width || 1024;
  document.getElementById('s-ch').value    = s.canvas_height || 1024;
}

// ── PANEL / SIDEBAR ───────────────────────────────────────────────────────────
function rebuildPanel(){
  if(activeTab==='layers')       rebuildLayers();
  else if(activeTab==='props')   rebuildProps();
  else                           rebuildCamera();
}

function rebuildLayers(){
  const body = document.getElementById('mv-sidebar-body');
  if(!layers.length){
    body.innerHTML='<p style="font-size:11px;color:var(--muted);padding:6px 0;">No assets assigned to this animatic.</p>';
    return;
  }
  const sorted=[...layers].sort((a,b)=>b.zIndex-a.zIndex);
  body.innerHTML='';
  sorted.forEach(L=>{
    const div=document.createElement('div');
    div.className='mv-li'+(L.key===selKey?' sel':'');
    div.dataset.key=L.key;

    const isVid = L.asset_type==='video';
    div.innerHTML=`
      <span class="mv-badge ${L.asset_type}">${isVid?'▶':'□'} ${L.asset_type}</span>
      <span class="mv-lname" title="${L.name}">${L.name}</span>
      <span class="mv-lz">z${L.zIndex}</span>
      ${isVid?`<button class="mv-ledit" data-vid="${L.asset_id}" title="Edit clip"><i class="fa fa-scissors"></i></button>`:''}
      <button class="mv-ldel" data-key="${L.key}" title="Remove layer"><i class="fa fa-trash"></i></button>
    `;
    div.addEventListener('click', e=>{
      if(e.target.closest('.mv-ledit')) return;
      if(e.target.closest('.mv-ldel')) return;
      selectLayer(L.key);
    });
    const editBtn = div.querySelector('.mv-ledit');
    if(editBtn) editBtn.addEventListener('click', e=>{ e.stopPropagation(); openClipEditor(L); });
    const delBtn = div.querySelector('.mv-ldel');
    if(delBtn) delBtn.addEventListener('click', e=>{ e.stopPropagation(); removeLayer(L); });
    body.appendChild(div);
  });
}

function rebuildProps(){
  const body = document.getElementById('mv-sidebar-body');
  const L = selLayer();
  if(!L){
    body.innerHTML='<p style="font-size:11px;color:var(--muted);padding:6px 0;">Select a layer first.</p>';
    return;
  }
  const nw = L.el ? (L.el.naturalWidth||L.el.videoWidth||1) : 1;
  const nh = L.el ? (L.el.naturalHeight||L.el.videoHeight||1) : 1;
  body.innerHTML=`
    <div style="font-size:11px;color:var(--acc);margin-bottom:8px;font-weight:700;">${L.name}</div>

    <div class="mv-pg">
      <div class="mv-pl">Position</div>
      <div class="mv-pr"><label>X</label><input type="number" class="mv-in" id="p-x" value="${Math.round(L.x)}"></div>
      <div class="mv-pr"><label>Y</label><input type="number" class="mv-in" id="p-y" value="${Math.round(L.y)}"></div>
    </div>

    <div class="mv-pg">
      <div class="mv-pl">Scale</div>
      <div class="mv-pr">
        <label>Keep ∝</label>
        <input type="checkbox" class="mv-in" id="p-kr" ${L._keepRatio!==false?'checked':''}>
      </div>
      <div class="mv-pr"><label>Sc X</label>
        <input type="range" class="mv-in" id="p-sx-r" min="0.05" max="5" step="0.01" value="${L.scaleX.toFixed(3)}">
        <input type="number" class="mv-in" id="p-sx" value="${L.scaleX.toFixed(3)}" step="0.05" style="width:58px;flex:none;">
      </div>
      <div class="mv-pr"><label>Sc Y</label>
        <input type="range" class="mv-in" id="p-sy-r" min="0.05" max="5" step="0.01" value="${L.scaleY.toFixed(3)}">
        <input type="number" class="mv-in" id="p-sy" value="${L.scaleY.toFixed(3)}" step="0.05" style="width:58px;flex:none;">
      </div>
    </div>

    <div class="mv-pg">
      <div class="mv-pl">Rotation (°)</div>
      <div class="mv-pr"><label>Rot</label>
        <input type="range" class="mv-in" id="p-rot-r" min="-180" max="180" step="1" value="${L.rotation.toFixed(1)}">
        <input type="number" class="mv-in" id="p-rot" value="${L.rotation.toFixed(1)}" step="1" style="width:58px;flex:none;">
      </div>
    </div>

    <div class="mv-pg">
      <div class="mv-pl">Opacity</div>
      <div class="mv-pr"><label>Alpha</label>
        <input type="range" class="mv-in" id="p-op-r" min="0" max="1" step="0.01" value="${L.opacity}">
        <span id="p-opv" style="font-size:10px;font-family:var(--mono);flex-shrink:0;width:30px;text-align:right;">${L.opacity.toFixed(2)}</span>
      </div>
    </div>

    <div class="mv-pg">
      <div class="mv-pl">Parallax Speed</div>
      <div class="mv-pr"><label>Speed</label><input type="number" class="mv-in" id="p-spd" value="${L.speed.toFixed(3)}" step="0.05" min="0" max="5"></div>
      <p class="mv-note">0=static · 1=full cam · set via Physics→Auto-Calc.</p>
    </div>

    <div class="mv-pg">
      <div class="mv-pl">Z-Index</div>
      <div class="mv-pr"><label>Z</label><input type="number" class="mv-in" id="p-z" value="${L.zIndex}"></div>
    </div>

    <p style="font-size:9px;color:var(--muted);margin-top:6px;">Native: ${nw}×${nh}px</p>
  `;

  const sync = (id, field, parse, mirror) => {
    const el = document.getElementById(id);
    if(!el) return;
    el.addEventListener('input', ()=>{
      const L2=selLayer(); if(!L2) return;
      L2[field]=parse(el.value);
      if(mirror){
        const mel=document.getElementById(mirror);
        if(mel) mel.value=el.value;
      }
      if((field==='scaleX'||field==='scaleY') && propKeepRatio()){
        if(field==='scaleX'){
          L2.scaleY=L2.scaleX;['p-sy','p-sy-r'].forEach(i=>{ const n=document.getElementById(i); if(n) n.value=L2.scaleY.toFixed(3); });
        } else {
          L2.scaleX=L2.scaleY;
          ['p-sx','p-sx-r'].forEach(i=>{ const n=document.getElementById(i); if(n) n.value=L2.scaleX.toFixed(3); });
        }
      }
    });
  };

  sync('p-x',     'x',        v=>parseFloat(v)||0);
  sync('p-y',     'y',        v=>parseFloat(v)||0);
  sync('p-sx',    'scaleX',   v=>Math.max(0.01,parseFloat(v)||1), 'p-sx-r');
  sync('p-sx-r',  'scaleX',   v=>Math.max(0.01,parseFloat(v)||1), 'p-sx');
  sync('p-sy',    'scaleY',   v=>Math.max(0.01,parseFloat(v)||1), 'p-sy-r');
  sync('p-sy-r',  'scaleY',   v=>Math.max(0.01,parseFloat(v)||1), 'p-sy');
  sync('p-rot',   'rotation', v=>parseFloat(v)||0, 'p-rot-r');
  sync('p-rot-r', 'rotation', v=>parseFloat(v)||0, 'p-rot');
  sync('p-spd',   'speed',    v=>Math.max(0,parseFloat(v)||0));
  sync('p-z',     'zIndex',   v=>parseInt(v)||0);

  const opR  = document.getElementById('p-op-r');
  const opV  = document.getElementById('p-opv');
  if(opR) opR.addEventListener('input',()=>{
    const L2=selLayer(); if(!L2) return;
    L2.opacity=parseFloat(opR.value);
    if(opV) opV.textContent=L2.opacity.toFixed(2);
  });

  const krCb = document.getElementById('p-kr');
  if(krCb) krCb.addEventListener('change',()=>{
    const L2=selLayer(); if(!L2) return;
    L2._keepRatio=krCb.checked;
  });
}

function syncPropsPanel(){
  if(activeTab!=='props') return;
  const L=selLayer(); if(!L) return;
  ['p-sx','p-sx-r'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=L.scaleX.toFixed(3); });
  ['p-sy','p-sy-r'].forEach(id=>{ const e=document.getElementById(id); if(e) e.value=L.scaleY.toFixed(3); });
}

function rebuildCamera(){
  const body=document.getElementById('mv-sidebar-body');
  body.innerHTML=`
    <p style="font-size:10px;color:var(--muted);margin-bottom:10px;line-height:1.5;">
      Camera &amp; parallax settings apply to all layers on export &amp; preview.
    </p>
    <div class="mv-pg">
      <div class="mv-pl">Current Globals</div>
      <div class="mv-pr"><label>Duration</label><span style="font-size:11px;font-family:var(--mono)">${G.durms}ms</span></div>
      <div class="mv-pr"><label>Pan X</label><span style="font-size:11px;font-family:var(--mono)">${G.movex}px</span></div>
      <div class="mv-pr"><label>Tilt Y</label><span style="font-size:11px;font-family:var(--mono)">${G.movey}px</span></div>
      <div class="mv-pr"><label>Zoom</label><span style="font-size:11px;font-family:var(--mono)">${G.zoomst}→${G.zoomen}</span></div>
      <div class="mv-pr"><label>Focal</label><span style="font-size:11px;font-family:var(--mono)">${G.focal}m</span></div>
    </div>
    <button class="mv-btn" id="cam-open" style="width:100%;justify-content:center;">
      <i class="fa fa-sliders"></i> Open Settings
    </button>
  `;
  const b=document.getElementById('cam-open');
  if(b) b.addEventListener('click',()=>openModal('modal-settings'));
}

// ── SELECTION ─────────────────────────────────────────────────────────────────
function selLayer(){ return layers.find(l=>l.key===selKey)||null; }

function selectLayer(key){
  selKey=key;
  rebuildPanel();
  document.getElementById('mv-lc').style.display='flex';
}
function deselect(){
  selKey=null;
  rebuildPanel();
  document.getElementById('mv-lc').style.display='none';
}

// ── LAYER CONTROLS BAR ────────────────────────────────────────────────────────
document.getElementById('lc-up')   .addEventListener('click',()=>{ const L=selLayer();if(!L)return; L.zIndex+=1;  rebuildLayers(); });
document.getElementById('lc-down') .addEventListener('click',()=>{ const L=selLayer();if(!L)return; L.zIndex=Math.max(0,L.zIndex-1); rebuildLayers(); });
document.getElementById('lc-top')  .addEventListener('click',()=>{ const L=selLayer();if(!L)return; L.zIndex=Math.max(...layers.map(l=>l.zIndex))+1; rebuildLayers(); });
document.getElementById('lc-bot')  .addEventListener('click',()=>{ const L=selLayer();if(!L)return; L.zIndex=0; rebuildLayers(); });
document.getElementById('lc-fit')  .addEventListener('click',()=>{
  const L=selLayer();if(!L)return;
  const nw=L.el.naturalWidth||L.el.videoWidth||1;
  const nh=L.el.naturalHeight||L.el.videoHeight||1;
  L.scaleX=CANVAS_W/nw; L.scaleY=CANVAS_H/nh; L.x=0; L.y=0;
  rebuildPanel(); toast('Fitted to canvas');
});
document.getElementById('lc-1')    .addEventListener('click',()=>{ const L=selLayer();if(!L)return; L.scaleX=1;L.scaleY=1;L.rotation=0; rebuildPanel(); toast('Scale 1:1'); });
document.getElementById('lc-center').addEventListener('click',()=>{
  const L=selLayer();if(!L)return;
  const nw=(L.el.naturalWidth||L.el.videoWidth||1)*L.scaleX;
  const nh=(L.el.naturalHeight||L.el.videoHeight||1)*L.scaleY;
  L.x=(CANVAS_W-nw)/2; L.y=(CANVAS_H-nh)/2;
  rebuildPanel(); toast('Centered');
});
document.getElementById('lc-phys').addEventListener('click',()=>{
  const L=selLayer();if(!L)return;
  openPhysicsModal(L);
});

// ── PHYSICS MODAL ─────────────────────────────────────────────────────────────
function openPhysicsModal(L){
  document.getElementById('lp-type').value=L.asset_type;
  document.getElementById('lp-id').value=L.asset_id;
  document.getElementById('lp-z').value=L.zIndex;
  document.getElementById('lp-spd').value=L.speed.toFixed(3);
  document.getElementById('lp-op').value=L.opacity;
  document.getElementById('lp-opv').textContent=L.opacity.toFixed(2);

  fetch(`${API}?action=get_layer_settings&animatic_id=${ANIMATIC_ID}&asset_type=${L.asset_type}&asset_id=${L.asset_id}`)
    .then(r=>r.json()).then(d=>{
      if(d.success){
        document.getElementById('lp-dist').value=d.data.distance||10;
        document.getElementById('lp-rh').value=d.data.real_height||'';
      }
      openModal('modal-phys');
    });
}

document.getElementById('lp-dist').addEventListener('input',function(){
  const d=parseFloat(this.value);
  if(d>0) document.getElementById('lp-spd').value=(G.focal/d).toFixed(4);
});

document.getElementById('lp-op').addEventListener('input',function(){
  document.getElementById('lp-opv').textContent=parseFloat(this.value).toFixed(2);
});

document.getElementById('lp-calc').addEventListener('click',()=>{
  const L=selLayer();if(!L)return;
  const dist  = parseFloat(document.getElementById('lp-dist').value);
  const realH = parseFloat(document.getElementById('lp-rh').value);
  const nativeH = (L.el.naturalHeight||L.el.videoHeight||1);
  if(!dist||dist<=0) return;

  const spd = G.focal / dist;
  document.getElementById('lp-spd').value=spd.toFixed(4);

  let newScale;
  if(realH&&realH>0){
    const frustumAtDist = G.frustum * (dist / G.focal);
    const fraction      = realH / frustumAtDist;
    newScale            = (fraction * CANVAS_H) / nativeH;
    toast(`Scale→${newScale.toFixed(3)} (${realH}m @ ${dist}m), speed→${spd.toFixed(3)}`);
  } else {
    newScale = G.scaleref / dist;
    toast(`Scale→${newScale.toFixed(3)} (fallback), speed→${spd.toFixed(3)}`);
  }
  L.scaleX=newScale; L.scaleY=newScale;
  rebuildPanel();
});

document.getElementById('lp-save').addEventListener('click',()=>{
  const type    = document.getElementById('lp-type').value;
  const id      = document.getElementById('lp-id').value;
  const speed   = parseFloat(document.getElementById('lp-spd').value);
  const zindex  = parseInt(document.getElementById('lp-z').value);
  const dist    = parseFloat(document.getElementById('lp-dist').value);
  const rh      = document.getElementById('lp-rh').value;
  const opacity = parseFloat(document.getElementById('lp-op').value);

  const L=layers.find(l=>l.asset_type===type&&l.asset_id===parseInt(id));
  if(L){L.speed=speed;L.zIndex=zindex;L.opacity=opacity;}

  const fd=new FormData();
  fd.append('action','save_layer_settings');
  fd.append('animatic_id',ANIMATIC_ID);
  fd.append('asset_type',type);
  fd.append('asset_id',id);
  fd.append('speed',speed);
  fd.append('z_index',zindex);
  fd.append('distance',dist);
  fd.append('real_height',rh);
  fd.append('opacity',opacity);

  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){toast('Layer saved');closeModal('modal-phys');rebuildPanel();}
    else toast('Error: '+d.message,true);
  });
});

// ── VIDEO CLIP EDITOR ─────────────────────────────────────────────────────────
let vcDur = 0;
let vcStart = 0;
let vcEnd   = null;
let vcDragHandle = null;

function openClipEditor(L){
  document.getElementById('vc-id').value = L.asset_id;
  vcDur   = L.duration_s || 30;
  vcStart = L.start_offset || 0;
  vcEnd   = L.end_offset || null;

  const player = document.getElementById('vc-player');
  player.src   = '/'+L.filename;
  player.playbackRate = L.playback_speed || 1.0;
  player.currentTime  = vcStart;

  document.getElementById('vc-spd').value  = L.playback_speed || 1.0;
  document.getElementById('vc-spdv').textContent = (L.playback_speed||1.0).toFixed(2)+'×';

  updateClipUI();
  openModal('modal-clip');
}

function updateClipUI(){
  const tl    = document.getElementById('vc-timeline');
  const width = tl.clientWidth || 300;
  const hStart= document.getElementById('vc-hstart');
  const hEnd  = document.getElementById('vc-hend');
  const track = document.getElementById('vc-track');
  const lblS  = document.getElementById('vc-t-start');
  const lblE  = document.getElementById('vc-t-end');
  const lblD  = document.getElementById('vc-t-dur');
  const info  = document.getElementById('vc-info');

  const dur = vcDur || 1;
  const s   = vcStart;
  const e   = vcEnd !== null ? vcEnd : dur;

  const pctS = (s / dur) * 100;
  const pctE = (e / dur) * 100;

  hStart.style.left  = pctS+'%';
  hEnd.style.left    = pctE+'%';
  track.style.left   = pctS+'%';
  track.style.width  = (pctE-pctS)+'%';

  lblS.textContent = s.toFixed(2)+'s';
  lblE.textContent = vcEnd!==null ? e.toFixed(2)+'s' : '(end)';
  lblD.textContent = `▶ ${(e-s).toFixed(2)}s clip`;

  const spd = parseFloat(document.getElementById('vc-spd').value)||1;
  info.textContent = `Duration at ${spd}× speed: ${((e-s)/spd).toFixed(2)}s — Full video: ${dur.toFixed(2)}s`;
}

(function setupTimelineDrag(){
  const tl = document.getElementById('vc-timeline');
  const hs = document.getElementById('vc-hstart');
  const he = document.getElementById('vc-hend');

  function startDrag(handle, e){
    e.preventDefault();
    e.stopPropagation();
    vcDragHandle = handle;
    tl.setPointerCapture(e.pointerId);
  }
  function onMove(e){
    if(!vcDragHandle) return;
    const rect  = tl.getBoundingClientRect();
    const pct   = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    const t     = pct * (vcDur||1);
    if(vcDragHandle==='start'){
      vcStart = Math.max(0, Math.min(t, (vcEnd||vcDur)-0.1));
    } else {
      vcEnd = Math.min(vcDur, Math.max(vcStart+0.1, t));
      if(vcEnd >= (vcDur - 0.05)) vcEnd = null;
    }
    updateClipUI();
  }
  function onUp(){ vcDragHandle=null; }

  hs.addEventListener('pointerdown', e=>startDrag('start',e));
  he.addEventListener('pointerdown', e=>startDrag('end',e));
  tl.addEventListener('pointermove', onMove);
  tl.addEventListener('pointerup',   onUp);
  tl.addEventListener('pointercancel',onUp);
})();

document.getElementById('vc-spd').addEventListener('input',function(){
  document.getElementById('vc-spdv').textContent=parseFloat(this.value).toFixed(2)+'×';
  const p=document.getElementById('vc-player');
  if(p) p.playbackRate=parseFloat(this.value)||1;
  updateClipUI();
});

document.getElementById('vc-preview-btn').addEventListener('click',()=>{
  const p=document.getElementById('vc-player');
  if(!p) return;
  p.currentTime=vcStart;
  p.playbackRate=parseFloat(document.getElementById('vc-spd').value)||1;
  p.play();
  if(vcEnd!==null){
    const end=vcEnd;
    const checkEnd=setInterval(()=>{
      if(p.currentTime>=end){ p.pause(); clearInterval(checkEnd); }
    }, 100);
  }
});

document.getElementById('vc-clear').addEventListener('click',()=>{
  vcStart=0; vcEnd=null;
  updateClipUI();
  toast('Trim cleared');
});

document.getElementById('vc-save').addEventListener('click',()=>{
  const assetId = document.getElementById('vc-id').value;
  const pbSpeed = parseFloat(document.getElementById('vc-spd').value)||1.0;
  const L = layers.find(l=>l.asset_type==='video'&&l.asset_id===parseInt(assetId));
  if(L){
    L.start_offset  = vcStart;
    L.end_offset    = vcEnd;
    L.playback_speed= pbSpeed;
    L.el.playbackRate=pbSpeed;
    L.el.currentTime=vcStart;
  }

  const fd=new FormData();
  fd.append('action','save_layer_settings');
  fd.append('animatic_id',ANIMATIC_ID);
  fd.append('asset_type','video');
  fd.append('asset_id',assetId);
  fd.append('speed',L?L.speed:0.7);
  fd.append('z_index',L?L.zIndex:50);
  fd.append('distance',10);
  fd.append('real_height','');
  fd.append('opacity',L?L.opacity:1.0);
  fd.append('start_offset',vcStart);
  fd.append('end_offset',vcEnd!==null?vcEnd:'');
  fd.append('playback_speed',pbSpeed);

  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){toast('Clip settings applied');closeModal('modal-clip');}
    else toast('Error: '+d.message,true);
  });
});

// ── ARRANGEMENT ───────────────────────────────────────────────────────────────
document.getElementById('btn-new').addEventListener('click',()=>{
  if(!confirm('Reset all layer positions to default?')) return;
  activeArrId=null; savedConfig=null;
  let zi=0;
  layers.forEach(L=>{L.x=0;L.y=0;L.scaleX=1;L.scaleY=1;L.rotation=0;L.zIndex=zi++;L.opacity=1;});
  rebuildPanel(); toast('Reset to default');
});

document.getElementById('btn-save').addEventListener('click',()=>{
  if(!ANIMATIC_ID){ toast('Select an animatic first.',true); return; }
  const cfg  = getConfig();
  const name = activeArrId?'Arrangement':(prompt('Arrangement name:','Arrangement')||'Arrangement');
  const fd=new FormData();
  fd.append('action','save_arrangement');
  fd.append('animatic_id',ANIMATIC_ID);
  fd.append('name',name);
  fd.append('config',JSON.stringify(cfg));
  if(activeArrId) fd.append('id',activeArrId);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){activeArrId=d.id;savedConfig=cfg;toast('Arrangement saved!');}
    else toast('Save error: '+d.message,true);
  });
});

document.getElementById('btn-load').addEventListener('click',()=>{
  if(!ANIMATIC_ID){ toast('Select an animatic first.',true); return; }
  const body=document.getElementById('arr-list');
  body.innerHTML='<p style="font-size:11px;color:var(--muted)">Loading…</p>';
  openModal('modal-load');
  fetch(`${API}?action=list_arrangements&animatic_id=${ANIMATIC_ID}`)
    .then(r=>r.json()).then(d=>{
      if(!d.data||!d.data.length){body.innerHTML='<p style="font-size:11px;color:var(--muted)">No saved arrangements.</p>';return;}
      body.innerHTML='';
      d.data.forEach(arr=>{
        const div=document.createElement('div');
        div.className='mv-ai';
        div.innerHTML=`<strong>${arr.name}</strong><small>${new Date(arr.updated_at).toLocaleString()}</small>`;
        div.addEventListener('click',()=>{
          fetch(`${API}?action=load_arrangement&id=${arr.id}`).then(r=>r.json()).then(ld=>{
            if(ld.success){
              activeArrId=ld.data.id;
              savedConfig=JSON.parse(ld.data.layer_config||'{}');
              layers.forEach(L=>{
                const cfg=savedConfig[L.key];
                if(cfg){
                  L.x=cfg.x??L.x;L.y=cfg.y??L.y;
                  L.scaleX=cfg.scaleX??L.scaleX;L.scaleY=cfg.scaleY??L.scaleY;
                  L.rotation=cfg.rotation??L.rotation;
                  L.zIndex=cfg.zIndex??L.zIndex;L.opacity=cfg.opacity??L.opacity;
                }
              });
              rebuildPanel();closeModal('modal-load');toast('Arrangement loaded');
            }
          });
        });
        body.appendChild(div);
      });
    });
});

// ── SETTINGS MODAL ────────────────────────────────────────────────────────────
document.getElementById('btn-settings').addEventListener('click',()=>openModal('modal-settings'));
document.getElementById('btn-save-settings').addEventListener('click',()=>{
  if(!ANIMATIC_ID){ toast('Select an animatic first.',true); return; }
  const fd=new FormData();
  fd.append('action','save_settings');
  fd.append('animatic_id',ANIMATIC_ID);
  fd.append('duration_ms',document.getElementById('s-dur').value);
  fd.append('fps',document.getElementById('s-fps').value);
  fd.append('move_x',document.getElementById('s-mx').value);
  fd.append('move_y',document.getElementById('s-my').value);
  fd.append('zoom_start',Math.max(0.1,parseFloat(document.getElementById('s-zs').value)));
  fd.append('zoom_end',Math.max(0.1,parseFloat(document.getElementById('s-ze').value)));
  fd.append('focal_distance',document.getElementById('s-focal').value);
  fd.append('frustum_height',document.getElementById('s-frust').value);
  fd.append('scale_reference',document.getElementById('s-sref').value);
  fd.append('canvas_width',document.getElementById('s-cw').value);
  fd.append('canvas_height',document.getElementById('s-ch').value);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      closeModal('modal-settings');
      loadSettings().then(()=>{loadAssets();rebuildPanel();});
      toast('Settings saved!');
    } else toast('Error: '+d.message,true);
  });
});

// ── PREVIEW ──────────────────────────────────────────────────────────────────
document.getElementById('btn-preview').addEventListener('click',()=>{
  restartVideos();
  previewActive = true;
  previewStart  = performance.now();
  toast(`▶ Preview ${G.durms}ms`);
});

// ── MEDIARECORDER ─────────────────────────────────────────────────────────────
let mediaRec=null, recChunks=[], isRecording=false, recTimers=[];

document.getElementById('btn-record').addEventListener('click',()=>{
  if(isRecording) stopRec(); else startRec();
});

function startRec(){
  restartVideos();
  previewActive=true; previewStart=performance.now();

  let stream;
  try{ stream=canvas.captureStream(G.fps); }
  catch(e){ toast('captureStream not supported',true); return; }

  const mimes=['video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm','video/mp4'];
  let mime='';
  for(const m of mimes) if(MediaRecorder.isTypeSupported(m)){ mime=m; break; }

  recChunks=[];
  mediaRec=new MediaRecorder(stream, mime?{mimeType:mime}:{});
  mediaRec.ondataavailable=e=>{ if(e.data.size>0) recChunks.push(e.data); };
  mediaRec.onstop=saveRec;
  mediaRec.start();
  isRecording=true;

  const recInd=document.getElementById('mv-rec-ind');
  const recTime=document.getElementById('mv-rec-time');
  recInd.style.display='flex';
  const t0=Date.now();
  const iv=setInterval(()=>{
    const s=Math.floor((Date.now()-t0)/1000);
    recTime.textContent=`REC ${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;
  },1000);
  recTimers=[iv];

  document.getElementById('btn-record').classList.add('rec-active');
  document.getElementById('btn-record').innerHTML='<i class="fa fa-stop"></i>';

  recTimers.push(setTimeout(()=>{ if(isRecording) stopRec(); }, G.durms+300));
}

function stopRec(){
  recTimers.forEach(t=>{ clearInterval(t); clearTimeout(t); });
  isRecording=false; previewActive=false;
  if(mediaRec&&mediaRec.state!=='inactive') mediaRec.stop();
  document.getElementById('mv-rec-ind').style.display='none';
  document.getElementById('btn-record').classList.remove('rec-active');
  document.getElementById('btn-record').innerHTML='<i class="fa fa-circle"></i>';
  restartVideos();
}

function saveRec(){
  const mime=mediaRec?mediaRec.mimeType:'video/webm';
  const blob=new Blob(recChunks,{type:mime});
  toast('Uploading preview recording…');
  const fd=new FormData();
  fd.append('action','register_video');
  fd.append('animatic_id',ANIMATIC_ID);
  fd.append('video',blob,'multivid_preview.webm');
  fd.append('duration_s',Math.round(G.durms/1000));
  fd.append('canvas_width',CANVAS_W);
  fd.append('canvas_height',CANVAS_H);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json())
    .then(d=>{ if(d.success) toast(`✓ Preview saved as Video #${d.video_id}`); else toast('Upload error: '+d.message,true); })
    .catch(e=>toast('Upload error: '+e.message,true));
}

// ── RENDER/JOBS (merged modal) ────────────────────────────────────────────────
document.getElementById('btn-jobs').addEventListener('click',openJobsModal);
document.getElementById('btn-jobs-refresh').addEventListener('click',loadJobsList);

document.getElementById('btn-render-now').addEventListener('click',()=>{
  if(!ANIMATIC_ID){ toast('Select an animatic first.',true); return; }
  if(!activeArrId){ toast('Save arrangement first, then render.',true); return; }
  if(!confirm(`Queue offline render for arrangement #${activeArrId}?`)) return;
  const fd=new FormData();
  fd.append('action','queue_render');
  fd.append('animatic_id',ANIMATIC_ID);
  fd.append('arrangement_id',activeArrId);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast(`Render job #${d.job_id} queued!`);
      loadJobsList();
    } else toast('Error: '+d.message,true);
  });
});

function openJobsModal(){
  openModal('modal-jobs');
  loadJobsList();
}
function loadJobsList(){
  if(!ANIMATIC_ID){
    document.getElementById('jobs-list').innerHTML='<p style="font-size:11px;color:var(--muted);">No animatic selected.</p>';
    return;
  }
  const body=document.getElementById('jobs-list');
  body.innerHTML='<p style="font-size:11px;color:var(--muted);">Loading…</p>';
  fetch(`${API}?action=list_render_jobs&animatic_id=${ANIMATIC_ID}`)
    .then(r=>r.json()).then(d=>{
      if(!d.jobs||!d.jobs.length){ body.innerHTML='<p style="font-size:11px;color:var(--muted);">No render jobs yet.</p>'; return; }
      body.innerHTML='';
      d.jobs.forEach(j=>{
        const div=document.createElement('div');
        div.className='mv-job';
        const videoLink = j.video_url ? `<a href="/${j.video_url}" target="_blank" style="color:var(--acc);font-size:10px;">▶ View</a>` : '';
        div.innerHTML=`
          <strong>Job #${j.id}</strong>
          <span class="job-st ${j.status}">${j.status}</span>
          ${videoLink}
          <div style="font-size:10px;color:var(--muted);margin-top:3px;">${new Date(j.created_at).toLocaleString()}</div>
          ${j.error_msg?`<div style="font-size:10px;color:var(--acc2);margin-top:2px;">${j.error_msg}</div>`:''}
        `;
        body.appendChild(div);
      });
    });
}

// ── TRIGGER JOB QUEUE (scheduler) ────────────────────────────────────────────
const MULTIVID_TASK_ID = <?= MULTIVID_TASK_ID ?>;

document.getElementById('btn-run-queue').addEventListener('click', () => triggerScheduledTask(MULTIVID_TASK_ID));

async function triggerScheduledTask(taskId) {
  const btn        = document.getElementById('btn-run-queue');
  const resultSpan = document.getElementById('run-queue-result');
  btn.disabled = true;
  resultSpan.style.color = '#f5a623';
  resultSpan.textContent = 'Triggering…';
  try {
    const response = await fetch('/api/scheduler_forge_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'run_task', id: taskId }),
    });
    const result = await response.json();
    if (result.ok) {
      resultSpan.style.color = 'var(--acc)';
      resultSpan.textContent = '✓ Triggered!';
      setTimeout(() => loadJobsList(), 1500); // refresh list after daemon picks it up
    } else {
      resultSpan.style.color = 'var(--acc2)';
      resultSpan.textContent = '✕ ' + (result.error || 'Unknown error');
    }
  } catch (error) {
    resultSpan.style.color = 'var(--acc2)';
    resultSpan.textContent = '✕ Network error: ' + error.message;
  } finally {
    setTimeout(() => { btn.disabled = false; }, 2000);
    setTimeout(() => { if (resultSpan.textContent.startsWith('✓')) resultSpan.textContent = ''; }, 5000);
  }
}
// ── REMOVE LAYER ──────────────────────────────────────────────────────────────
function removeLayer(L){
  if(!confirm(`Remove "${L.name}" from this animatic?\n\nThis unlinks the asset but does not delete it.`)) return;
  const action = L.asset_type === 'video' ? 'unassign_video' : 'unassign_frame';
  const idKey  = L.asset_type === 'video' ? 'video_id' : 'frame_id';
  const fd = new FormData();
  fd.append('action', action);
  fd.append('animatic_id', ANIMATIC_ID);
  fd.append(idKey, L.asset_id);
  fetch(API, {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      if(selKey === L.key){ selKey=null; document.getElementById('mv-lc').style.display='none'; }
      layers = layers.filter(l=>l.key !== L.key);
      rebuildPanel();
      toast(`Removed ${L.asset_type} layer: ${L.name}`);
    } else toast('Remove error: '+d.message, true);
  });
}

// ── ENTITY FORM (fullscreen iframe) ──────────────────────────────────────────
function openEntityForm(){
  if(!ANIMATIC_ID){ toast('Select an animatic first.', true); return; }
  const url = `entity_form.php?entity_type=animatics&entity_id=${ANIMATIC_ID}`;
  document.getElementById('modal-entity-iframe').src = url;
  document.getElementById('modal-entity-title').textContent = `Animatic #${ANIMATIC_ID} — Entity Form`;
  document.getElementById('modal-entity').classList.add('open');
}
document.getElementById('btn-entity-form').addEventListener('click', openEntityForm);
document.getElementById('btn-entity-close').addEventListener('click', ()=>{
  document.getElementById('modal-entity').classList.remove('open');
  document.getElementById('modal-entity-iframe').src = '';
});

document.getElementById('btn-reload').addEventListener('click',()=>loadAssets());

// ── SIDEBAR HAMBURGER ─────────────────────────────────────────────────────────
document.getElementById('mv-hamburger').addEventListener('click',()=>{
  sidebarOpen=!sidebarOpen;
  const sb  = document.getElementById('mv-sidebar');
  const hbg = document.getElementById('mv-hamburger');
  sb.classList.toggle('open', sidebarOpen);
  hbg.style.left = sidebarOpen ? 'calc(var(--sidebar-w) + 8px)' : '12px';
});

// ── TABS ──────────────────────────────────────────────────────────────────────
document.querySelectorAll('.mv-tab').forEach(t=>{
  t.addEventListener('click',()=>{
    document.querySelectorAll('.mv-tab').forEach(x=>x.classList.remove('active'));
    t.classList.add('active');
    activeTab=t.dataset.tab;
    rebuildPanel();
  });
});

// ── MODALS ────────────────────────────────────────────────────────────────────
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('[data-close]').forEach(btn=>{
  btn.addEventListener('click',()=>closeModal(btn.dataset.close));
});

// ── TOAST ─────────────────────────────────────────────────────────────────────
let toastTimer=null;
function toast(msg,isErr){
  const el=document.getElementById('mv-toast');
  el.textContent=msg;
  el.className='show'+(isErr?' err':'');
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>el.className='',3000);
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── ANIMATIC BROWSER ─────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════
let abPage = 1, abTotalPages = 1, abSearch = '', abSearchTimer = null;

function openAnimaticBrowser(){
  abPage = 1; abSearch = '';
  document.getElementById('ab-search').value = '';
  document.getElementById('ab-create-form').style.display = 'none';
  document.getElementById('ab-new-name').value = '';
  openModal('modal-animatic-browser');
  loadAnimaticBrowserPage();
}

function loadAnimaticBrowserPage(){
  const body = document.getElementById('ab-list');
  body.innerHTML = '<p style="font-size:11px;color:var(--muted);">Loading…</p>';
  fetch(`${API}?action=list_animatics&page=${abPage}&q=${encodeURIComponent(abSearch)}`)
    .then(r=>r.json()).then(d=>{
      if(!d.success){ body.innerHTML='<p style="font-size:11px;color:var(--acc2);">Error loading animatics.</p>'; return; }
      abTotalPages = d.total_pages || 1;
      document.getElementById('ab-page').value  = abPage;
      document.getElementById('ab-total').textContent = '/ '+abTotalPages;
      document.getElementById('ab-prev').disabled = abPage <= 1;
      document.getElementById('ab-next').disabled = abPage >= abTotalPages;

      body.innerHTML = '';
      if(!d.data || !d.data.length){
        body.innerHTML='<p style="font-size:11px;color:var(--muted);">No animatics found.</p>';
        return;
      }
      d.data.forEach(a=>{
        const div = document.createElement('div');
        div.className = 'mv-brow-item';
        div.innerHTML = `
          <div class="mv-brow-thumb-placeholder"><i class="fa fa-film"></i></div>
          <div class="mv-brow-info">
            <strong>#${a.id} — ${escHtml(a.name)}</strong>
            <small>${escHtml(a.description||'')}</small>
          </div>
        `;
        div.addEventListener('click',()=> selectAnimatic(a.id, a.name));
        body.appendChild(div);
      });
    }).catch(()=>{ body.innerHTML='<p style="font-size:11px;color:var(--acc2);">Load failed.</p>'; });
}

function selectAnimatic(id, name){
  ANIMATIC_ID = id;
  activeArrId = null;
  savedConfig = null;
  layers = [];
  document.getElementById('mv-aname-lbl').textContent = name;
  closeModal('modal-animatic-browser');
  // Redirect to same page with new animatic_id so URL stays consistent
  const url = new URL(window.location.href);
  url.searchParams.set('animatic_id', id);
  window.history.replaceState({}, '', url.toString());
  // Reload settings + assets
  loadSettings().then(()=>setTimeout(loadAssets,200));
  toast(`Animatic #${id}: ${name}`);
}

document.getElementById('btn-browse-animatic').addEventListener('click', openAnimaticBrowser);

document.getElementById('ab-search').addEventListener('input', function(){
  clearTimeout(abSearchTimer);
  abSearchTimer = setTimeout(()=>{ abSearch=this.value; abPage=1; loadAnimaticBrowserPage(); }, 350);
});

document.getElementById('ab-prev').addEventListener('click',()=>{ if(abPage>1){abPage--;loadAnimaticBrowserPage();} });
document.getElementById('ab-next').addEventListener('click',()=>{ if(abPage<abTotalPages){abPage++;loadAnimaticBrowserPage();} });
document.getElementById('ab-page').addEventListener('change', function(){
  const p = parseInt(this.value)||1;
  abPage = Math.max(1, Math.min(p, abTotalPages));
  this.value = abPage;
  loadAnimaticBrowserPage();
});

// Create new animatic
document.getElementById('ab-create-btn').addEventListener('click',()=>{
  const f = document.getElementById('ab-create-form');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
});
document.getElementById('ab-create-cancel').addEventListener('click',()=>{
  document.getElementById('ab-create-form').style.display='none';
});
document.getElementById('ab-create-save').addEventListener('click',()=>{
  const name = document.getElementById('ab-new-name').value.trim();
  if(!name){ toast('Enter a name.',true); return; }
  const fd = new FormData();
  fd.append('action','create_animatic');
  fd.append('name', name);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast(`Created animatic #${d.id}`);
      document.getElementById('ab-create-form').style.display='none';
      document.getElementById('ab-new-name').value='';
      abPage=1; abSearch='';
      document.getElementById('ab-search').value='';
      loadAnimaticBrowserPage();
      // Optionally auto-select
      selectAnimatic(d.id, name);
    } else toast('Error: '+d.message, true);
  });
});

// ═══════════════════════════════════════════════════════════════════════════════
// ── ASSET ASSIGNMENT BROWSER ─────────────════════════════════════════════════
// ═══════════════════════════════════════════════════════════════════════════════
let asPage=1, asTotalPages=1, asSearch='', asType='frame', asSearchTimer=null;

function openAssignModal(){
  if(!ANIMATIC_ID){ toast('Select an animatic first.',true); return; }
  asPage=1; asSearch=''; asType='frame';
  document.getElementById('as-search').value='';
  document.getElementById('as-page').value=1;
  // Reset tab
  document.querySelectorAll('.mv-type-btn').forEach(b=>b.classList.toggle('active', b.dataset.type==='frame'));
  openModal('modal-assign');
  loadAssignPage();
}

document.getElementById('btn-add-asset').addEventListener('click', openAssignModal);

document.querySelectorAll('.mv-type-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.mv-type-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    asType = btn.dataset.type;
    asPage=1; asSearch='';
    document.getElementById('as-search').value='';
    loadAssignPage();
  });
});

document.getElementById('as-search').addEventListener('input',function(){
  clearTimeout(asSearchTimer);
  asSearchTimer = setTimeout(()=>{ asSearch=this.value; asPage=1; loadAssignPage(); }, 350);
});

document.getElementById('as-prev').addEventListener('click',()=>{ if(asPage>1){asPage--;loadAssignPage();} });
document.getElementById('as-next').addEventListener('click',()=>{ if(asPage<asTotalPages){asPage++;loadAssignPage();} });
document.getElementById('as-page').addEventListener('change', function(){
  const p = parseInt(this.value)||1;
  asPage = Math.max(1, Math.min(p, asTotalPages));
  this.value = asPage;
  loadAssignPage();
});

function loadAssignPage(){
  const body = document.getElementById('as-list');
  body.innerHTML='<p style="font-size:11px;color:var(--muted);">Loading…</p>';
  fetch(`${API}?action=browse_assets&asset_type=${asType}&page=${asPage}&q=${encodeURIComponent(asSearch)}`)
    .then(r=>r.json()).then(d=>{
      if(!d.success){ body.innerHTML='<p style="font-size:11px;color:var(--acc2);">Error.</p>'; return; }
      asTotalPages = d.total_pages||1;
      document.getElementById('as-page').value = asPage;
      document.getElementById('as-total').textContent = '/ '+asTotalPages;
      document.getElementById('as-prev').disabled = asPage<=1;
      document.getElementById('as-next').disabled = asPage>=asTotalPages;

      body.innerHTML='';
      if(!d.data||!d.data.length){
        body.innerHTML='<p style="font-size:11px;color:var(--muted);">Nothing found.</p>';
        return;
      }
      d.data.forEach(item=>{
        const div = document.createElement('div');
        div.className='mv-brow-item';

        let thumbHtml='';
        if(asType==='frame' && item.filename){
          thumbHtml=`<img class="mv-brow-thumb" src="/${escHtml(item.filename)}" alt="" loading="lazy">`;
        } else if(asType==='video' && item.thumbnail){
          thumbHtml=`<img class="mv-brow-thumb" src="/${escHtml(item.thumbnail)}" alt="" loading="lazy">`;
        } else {
          thumbHtml=`<div class="mv-brow-thumb-placeholder"><i class="fa ${asType==='video'?'fa-film':'fa-image'}"></i></div>`;
        }

        div.innerHTML=`
          ${thumbHtml}
          <div class="mv-brow-info">
            <strong>#${item.id} — ${escHtml(item.name||item.filename||'')}</strong>
            <small>${escHtml(item.filename||item.url||'')}</small>
          </div>
        `;
        div.addEventListener('click',()=> assignAsset(asType, item.id, item.name||item.filename||('Asset #'+item.id)));
        body.appendChild(div);
      });
    }).catch(()=>{ body.innerHTML='<p style="font-size:11px;color:var(--acc2);">Load failed.</p>'; });
}

function assignAsset(type, assetId, assetName){
  const fd = new FormData();
  fd.append('action', type==='frame' ? 'assign_frame' : 'assign_video');
  fd.append('animatic_id', ANIMATIC_ID);
  fd.append(type==='frame' ? 'frame_id' : 'video_id', assetId);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      toast(`Assigned ${type} #${assetId}`);
      closeModal('modal-assign');
      loadAssets();
    } else toast('Error: '+d.message, true);
  });
}

// ── HELPERS ───────────────────────────────────────────────────────────────────
function escHtml(s){
  if(!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── BOOT ──────────────────────────────────────────────────────────────────────
initCanvas(1024,1024);
startLoop();

if(ANIMATIC_ID > 0){
  loadSettings().then(()=>setTimeout(loadAssets,200));
} else {
  // No animatic_id — open browser automatically
  setTimeout(()=>openAnimaticBrowser(), 300);
}

})();
</script>
<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
?>
