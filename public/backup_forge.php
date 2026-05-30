<?php
// public/backup_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// BACKUP FORGE
// Configure and run backup jobs — Forge design system, PyAPI-backed.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

$isEmbed       = !empty($_GET['embed']);
$viewportScale = $isEmbed ? '1.0' : '0.9';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>Backup Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t) document.documentElement.setAttribute('data-theme', t);
    } catch(e) {}
})();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   FORGE — Design System (Backup Forge edition)
   Amber accent, industrial aesthetic — identical token set to Generator Forge
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
    --amber-dim:    rgba(245,166,35,.08);
    --amber-mid:    rgba(245,166,35,.15);
    --amber-glow:   rgba(245,166,35,.4);
    --green:        #22d3a0;
    --green-dim:    rgba(34,211,160,.1);
    --red:          #f05060;
    --red-dim:      rgba(240,80,96,.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77,166,255,.1);
    --mono:         'Space Mono','Fira Mono',monospace;
    --sans:         'Syne',system-ui,sans-serif;
    --radius:       6px;
    --radius-lg:    10px;
}
:root[data-theme="light"],html[data-theme="light"] {
    --bg:#f6f8fa; --surface:#e1e4e8; --card:#fff; --card-hover:#f3f4f6;
    --border:#d1d5db; --border-glow:#9ca3af; --text:#111827; --text-dim:#4b5563;
    --text-bright:#000; --amber:#d97706; --amber-dim:rgba(217,119,6,.1);
    --amber-mid:rgba(217,119,6,.2); --amber-glow:rgba(217,119,6,.4);
    --green:#059669; --green-dim:rgba(5,150,105,.1);
    --red:#dc2626; --red-dim:rgba(220,38,38,.1);
    --blue:#2563eb; --blue-dim:rgba(37,99,235,.1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:var(--sans);font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased;overflow:hidden}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-glow);border-radius:4px}

/* ── LAYOUT ── */
.forge-layout{display:grid;grid-template-rows:52px 1fr;grid-template-columns:280px 1fr;grid-template-areas:"header header" "sidebar main";height:100vh;height:100dvh;overflow:hidden}

/* ── HEADER ── */
.forge-header{grid-area:header;display:flex;align-items:center;justify-content:space-between;padding:0 20px;background:var(--surface);border-bottom:1px solid var(--border);z-index:100}
.forge-logo{display:flex;align-items:center;gap:10px;font-family:var(--mono);font-size:.85rem;font-weight:700;color:var(--amber);letter-spacing:2px;text-transform:uppercase}
.forge-logo-icon{width:28px;height:28px;background:var(--amber-mid);border:1px solid var(--amber-glow);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:14px}
.forge-header-right{display:flex;align-items:center;gap:8px}
.forge-header-stat{font-family:var(--mono);font-size:.7rem;color:var(--text-dim);padding:4px 10px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius)}
.forge-header-stat span{color:var(--amber)}

/* ── SIDEBAR ── */
.forge-sidebar{grid-area:sidebar;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.sidebar-tabs{display:flex;border-bottom:1px solid var(--border);flex-shrink:0}
.sidebar-tab{flex:1;padding:10px 0;font-family:var(--mono);font-size:.7rem;text-transform:uppercase;letter-spacing:1px;text-align:center;cursor:pointer;color:var(--text-dim);border:none;background:transparent;transition:all .15s;border-bottom:2px solid transparent}
.sidebar-tab.active{color:var(--amber);border-bottom-color:var(--amber)}
.sidebar-tab:hover:not(.active){color:var(--text)}
.sidebar-pane{flex:1;overflow-y:auto;padding:8px;display:none}
.sidebar-pane.active{display:block}
.sidebar-section-label{font-family:var(--mono);font-size:.65rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:1.5px;padding:8px 4px 4px;margin-bottom:4px}

/* ── JOB / DEST CARDS ── */
.item-card{padding:8px 10px 8px 12px;border-radius:var(--radius);border:1px solid transparent;cursor:pointer;transition:all .15s;margin-bottom:3px;position:relative;background:transparent;display:flex;align-items:center;gap:8px}
.item-card:hover{background:var(--card);border-color:var(--border)}
.item-card.active{background:var(--amber-dim);border-color:var(--amber)}
.item-card.active .item-title{color:var(--amber)}
.item-card-indicator{position:absolute;left:0;top:50%;transform:translateY(-50%);width:2px;height:0;background:var(--amber);border-radius:0 2px 2px 0;transition:height .2s}
.item-card.active .item-card-indicator{height:60%}
.item-body{flex:1;min-width:0}
.item-title{font-family:var(--sans);font-weight:600;font-size:.85rem;color:var(--text-bright);line-height:1.3;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:color .15s}
.item-meta{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.item-card-actions{display:flex;gap:4px;flex-shrink:0}

/* ── BADGES ── */
.badge{font-family:var(--mono);font-size:.65rem;padding:1px 5px;border-radius:3px;border:1px solid;white-space:nowrap}
.badge-active{border-color:var(--green);color:var(--green);background:var(--green-dim)}
.badge-inactive{border-color:var(--border);color:var(--text-dim)}
.badge-type{border-color:var(--border-glow);color:var(--text-dim);background:var(--card)}
.badge-dest{border-color:var(--blue);color:var(--blue);background:var(--blue-dim)}
.badge-scp{border-color:var(--amber);color:var(--amber);background:var(--amber-dim)}
.badge-done{border-color:var(--green);color:var(--green);background:var(--green-dim)}
.badge-failed{border-color:var(--red);color:var(--red);background:var(--red-dim)}
.badge-running{border-color:var(--amber);color:var(--amber);background:var(--amber-dim)}
.badge-pending{border-color:var(--border-glow);color:var(--text-dim)}
.badge-partial{border-color:var(--blue);color:var(--blue);background:var(--blue-dim)}

/* ── BUTTON HELPERS ── */
.btn-icon-sm{width:30px;height:30px;border-radius:var(--radius);border:1px solid var(--border);background:transparent;color:var(--text-dim);cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;font-size:13px}
.btn-icon-sm:hover{border-color:var(--amber);color:var(--amber);background:var(--amber-dim)}
.btn-icon-sm.danger:hover{border-color:var(--red);color:var(--red);background:var(--red-dim)}

/* ── MAIN ── */
.forge-main{grid-area:main;display:flex;flex-direction:column;overflow:hidden;background:var(--bg)}
.forge-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:40px;color:var(--text-dim)}
.forge-empty-icon{font-size:48px;opacity:.3;filter:grayscale(1)}
.forge-empty-title{font-family:var(--mono);font-size:1rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:2px}
.forge-empty-sub{font-size:.85rem;color:var(--text-dim);opacity:.6}

/* ── WORKSPACE ── */
.forge-workspace{flex:1;display:flex;flex-direction:column;overflow:hidden;display:none}
.workspace-header{padding:14px 20px;border-bottom:1px solid var(--border);background:var(--surface);flex-shrink:0;display:flex;align-items:flex-start;gap:12px}
.workspace-title-block{flex:1;min-width:0}
.workspace-title{font-family:var(--sans);font-size:1.1rem;font-weight:700;color:var(--text-bright);line-height:1.2;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.workspace-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.workspace-header-actions{display:flex;gap:6px;flex-shrink:0}

/* Two column workspace */
.workspace-body{flex:1;overflow:hidden;display:grid;grid-template-columns:360px 1fr;grid-template-rows:1fr auto}
.params-panel{grid-row:1;grid-column:1;padding:20px;overflow-y:auto;border-right:1px solid var(--border)}
.action-bar{grid-row:2;grid-column:1;padding:14px 20px;border-top:1px solid var(--border);border-right:1px solid var(--border);background:var(--surface);display:flex;gap:8px;align-items:center}
.log-panel{grid-row:1/3;grid-column:2;display:flex;flex-direction:column;overflow:hidden}

/* ── FORM ELEMENTS ── */
.panel-label{font-family:var(--mono);font-size:.65rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.panel-label::after{content:'';flex:1;height:1px;background:var(--border)}
.form-group{margin-bottom:14px}
.form-label{display:block;font-family:var(--mono);font-size:.7rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px}
.form-label .opt{color:var(--text-dim);opacity:.5;font-weight:normal;text-transform:none;letter-spacing:0}
.form-input,.form-select,.form-textarea{width:100%;padding:8px 10px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--mono);font-size:.8rem;transition:border-color .15s;appearance:none}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--amber);background:var(--card-hover)}
.form-textarea{min-height:80px;resize:vertical}
.form-select{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:28px}
.form-hint{font-size:.7rem;color:var(--text-dim);margin-top:3px;font-family:var(--mono)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.toggle-row{display:flex;align-items:center;gap:8px;font-family:var(--mono);font-size:.75rem;color:var(--text-dim);cursor:pointer}
.toggle-row input{accent-color:var(--amber)}

/* JSON editor hint */
.json-hint{font-size:.65rem;font-family:var(--mono);color:var(--text-dim);margin-top:3px}
.json-hint.ok{color:var(--green)}
.json-hint.err{color:var(--red)}

/* ── RUN BUTTON ── */
.btn-run{flex:1;padding:10px 20px;background:var(--amber);color:#000;border:none;border-radius:var(--radius);font-family:var(--mono);font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-run:hover:not(:disabled){filter:brightness(1.15);transform:translateY(-1px)}
.btn-run:disabled{opacity:.4;cursor:not-allowed;transform:none}
.btn-run .spinner{width:14px;height:14px;border:2px solid rgba(0,0,0,.3);border-top-color:#000;border-radius:50%;animation:spin .7s linear infinite;display:none}
.btn-run.running .btn-label{display:none}
.btn-run.running .spinner{display:block}

/* ── LOG PANEL ── */
.log-toolbar{padding:10px 16px;border-bottom:1px solid var(--border);background:var(--surface);flex-shrink:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.log-tab{padding:4px 10px;border-radius:20px;border:1px solid var(--border);background:transparent;color:var(--text-dim);font-family:var(--mono);font-size:.7rem;cursor:pointer;transition:all .15s}
.log-tab.active{background:var(--amber-dim);border-color:var(--amber);color:var(--amber)}
.log-tab:hover:not(.active){border-color:var(--border-glow);color:var(--text)}
.log-body{flex:1;overflow-y:auto;padding:14px}
.log-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:12px;color:var(--text-dim);text-align:center;padding:30px}
.log-placeholder-icon{font-size:36px;opacity:.2}
.log-placeholder-text{font-family:var(--mono);font-size:.78rem;letter-spacing:1px}

/* Run log */
.run-log{font-family:var(--mono);font-size:.75rem;line-height:1.7;color:var(--text);white-space:pre-wrap;word-break:break-word}
.run-log .log-ts{color:var(--text-dim)}
.run-log .log-ok{color:var(--green)}
.run-log .log-warn{color:var(--amber)}
.run-log .log-err{color:var(--red)}

/* Run history table */
.runs-table{width:100%;border-collapse:collapse;font-family:var(--mono);font-size:.75rem}
.runs-table th{text-align:left;padding:6px 10px;color:var(--text-dim);font-size:.65rem;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
.runs-table td{padding:7px 10px;border-bottom:1px solid var(--border);color:var(--text);vertical-align:middle}
.runs-table tr:hover td{background:var(--card);cursor:pointer}
.run-status-dot{width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:5px}
.dot-done{background:var(--green)}
.dot-failed{background:var(--red)}
.dot-running{background:var(--amber);animation:pulse 1.2s ease infinite}
.dot-pending{background:var(--text-dim)}
.dot-partial{background:var(--blue)}

/* Artifact list */
.artifact-list{display:flex;flex-direction:column;gap:6px}
.artifact-item{padding:8px 12px;background:var(--card);border-radius:var(--radius);border:1px solid var(--border)}
.artifact-label{font-family:var(--mono);font-size:.65rem;color:var(--amber);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.artifact-detail{font-family:var(--mono);font-size:.72rem;color:var(--text-dim)}
.artifact-detail span{color:var(--text)}

/* ── MODAL ── */
.forge-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(3px);z-index:10000;display:none;align-items:center;justify-content:center;padding:16px}
.forge-modal-overlay.open{display:flex}
.forge-modal{background:var(--surface);border:1px solid var(--border-glow);border-radius:var(--radius-lg);width:100%;max-width:640px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.6);animation:modalIn .2s ease}
.forge-modal-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-shrink:0}
.forge-modal-title{font-family:var(--mono);font-size:.8rem;font-weight:700;color:var(--amber);text-transform:uppercase;letter-spacing:1.5px}
.forge-modal-close{width:26px;height:26px;border-radius:4px;border:1px solid var(--border);background:transparent;color:var(--text-dim);cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;font-size:13px}
.forge-modal-close:hover{border-color:var(--red);color:var(--red);background:var(--red-dim)}
.forge-modal-body{padding:20px;overflow-y:auto;flex:1}
.forge-modal-footer{padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;background:var(--bg)}
.btn-primary{padding:8px 18px;background:var(--amber);color:#000;border:none;border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;transition:all .15s}
.btn-primary:hover{filter:brightness(1.1)}
.btn-secondary{padding:8px 18px;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.78rem;transition:all .15s}
.btn-secondary:hover{border-color:var(--border-glow);color:var(--text)}
.btn-danger{padding:8px 18px;background:var(--red-dim);color:var(--red);border:1px solid var(--red);border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.78rem;transition:all .15s}
.btn-danger:hover{background:var(--red);color:#fff}

/* ── TOAST ── */
.forge-toast-container{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.forge-toast{padding:9px 14px;border-radius:var(--radius);background:var(--card);border:1px solid var(--border);font-family:var(--mono);font-size:.78rem;color:var(--text);box-shadow:0 4px 20px rgba(0,0,0,.5);animation:toastIn .25s ease;pointer-events:all;cursor:pointer;max-width:300px;display:flex;align-items:center;gap:8px}
.forge-toast.success{border-color:var(--green)}
.forge-toast.error{border-color:var(--red);color:var(--red)}
.forge-toast.info{border-color:var(--amber)}
.forge-toast.out{animation:toastOut .25s ease forwards}

/* ── SECTION DIVIDER ── */
.section-divider{border:none;border-top:1px solid var(--border);margin:16px 0}

/* ── ANIMATIONS ── */
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@keyframes toastOut{to{opacity:0;transform:translateY(10px)}}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(-10px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 var(--amber-glow)}50%{box-shadow:0 0 0 5px rgba(245,166,35,0)}}

/* ── MOBILE ── */
@media(max-width:768px){
    .forge-layout{grid-template-columns:1fr;grid-template-rows:52px auto 1fr;grid-template-areas:"header" "sidebar" "main"}
    .forge-sidebar{border-right:none;border-bottom:1px solid var(--border);max-height:200px}
    .workspace-body{grid-template-columns:1fr;grid-template-rows:auto auto 1fr}
    .action-bar{grid-row:2;grid-column:1;border-right:none}
    .log-panel{grid-row:3;grid-column:1;border-top:1px solid var(--border);min-height:250px}
    .forge-header-stat{display:none}
}
@media(max-width:900px){
    .workspace-body{grid-template-columns:1fr;grid-template-rows:auto auto 1fr}
    .params-panel{border-right:none;border-bottom:1px solid var(--border);max-height:45vh}
    .log-panel{grid-row:3;grid-column:1}
}
</style>
</head>
<body>

<div class="forge-layout">

    <!-- ── HEADER ── -->
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon">💾</div>
            <span>Backup Forge</span>
        </div>
        <div class="forge-header-right">
            <div class="forge-header-stat">
                Jobs: <span id="statJobs">—</span> &nbsp;|&nbsp; Runs today: <span id="statRuns">—</span>
            </div>
            <a href="/backup_download.php" style="text-decoration:none;">
                <button class="btn-icon-sm" title="Retrieve / Download Backups" style="color:var(--green);border-color:var(--green);">
                    <i class="bi bi-cloud-download"></i>
                </button>
            </a>
            <button class="btn-icon-sm" onclick="BF.openJobModal(null)" title="New Job">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button class="btn-icon-sm" onclick="BF.openDestModal(null)" title="New Destination">
                <i class="bi bi-hdd-network"></i>
            </button>
        </div>
    </header>

    <!-- ── SIDEBAR ── -->
    <aside class="forge-sidebar">
        <div class="sidebar-tabs">
            <button class="sidebar-tab active" data-pane="jobs">Jobs</button>
            <button class="sidebar-tab" data-pane="dests">Destinations</button>
            <button class="sidebar-tab" data-pane="history">History</button>
        </div>
        <div class="sidebar-pane active" id="paneJobs">
            <div id="jobList"><div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">Loading…</div></div>
        </div>
        <div class="sidebar-pane" id="paneDests">
            <div id="destList"><div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">Loading…</div></div>
        </div>
        <div class="sidebar-pane" id="paneHistory">
            <div id="globalHistory"><div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">Loading…</div></div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="forge-main">

        <div class="forge-empty" id="forgeEmpty">
            <div class="forge-empty-icon">💾</div>
            <div class="forge-empty-title">Select a Backup Job</div>
            <div class="forge-empty-sub">Choose from the sidebar, or create a new job</div>
        </div>

        <div class="forge-workspace" id="forgeWorkspace">

            <div class="workspace-header">
                <div class="workspace-title-block">
                    <div class="workspace-title" id="wsTitle">—</div>
                    <div class="workspace-meta" id="wsMeta"></div>
                </div>
                <div class="workspace-header-actions">
                    <button class="btn-icon-sm" onclick="BF.refreshRunHistory()" title="Refresh run history">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <button class="btn-icon-sm" onclick="BF.editCurrentJob()" title="Edit job config">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>

            <div class="workspace-body">

                <!-- PARAMS / INFO PANEL -->
                <div class="params-panel" id="paramsPanel">
                    <div class="panel-label">Job Configuration</div>
                    <div id="jobDetail"></div>
                </div>

                <!-- ACTION BAR -->
                <div class="action-bar">
                    <button class="btn-run" id="btnRun" onclick="BF.runJob()">
                        <div class="spinner"></div>
                        <span class="btn-label"><i class="bi bi-play-fill"></i> RUN BACKUP</span>
                    </button>
                    <button class="btn-icon-sm" onclick="BF.refreshRunHistory()" title="Refresh">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>

                <!-- LOG / HISTORY PANEL -->
                <div class="log-panel">
                    <div class="log-toolbar">
                        <button class="log-tab active" data-view="live">Live Log</button>
                        <button class="log-tab" data-view="runs">Run History</button>
                        <button class="log-tab" data-view="artifacts">Artifacts</button>
                    </div>
                    <div class="log-body" id="logBody">
                        <div class="log-placeholder" id="logPlaceholder">
                            <div class="log-placeholder-icon">📋</div>
                            <div class="log-placeholder-text">Run a job to see live output</div>
                        </div>
                        <div id="viewLive" style="display:none;"></div>
                        <div id="viewRuns" style="display:none;"></div>
                        <div id="viewArtifacts" style="display:none;"></div>
                    </div>
                </div>

            </div>
        </div><!-- /forge-workspace -->
    </main>
</div><!-- /forge-layout -->

<!-- ── JOB EDIT MODAL ── -->
<div class="forge-modal-overlay" id="jobModal">
    <div class="forge-modal">
        <div class="forge-modal-header">
            <div class="forge-modal-title" id="jobModalTitle">New Backup Job</div>
            <button class="forge-modal-close" onclick="BF.closeModal('jobModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <input type="hidden" id="jobId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Job Name</label>
                    <input type="text" id="jobName" class="form-input" placeholder="Media Incremental">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text" id="jobSlug" class="form-input" placeholder="media_incremental">
                    <div class="form-hint">Machine key — lowercase, underscores</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Job Type</label>
                    <select id="jobType" class="form-select" onchange="BF.onJobTypeChange()">
                        <option value="media_tar">media_tar — Incremental frames/audios/videos</option>
                        <option value="mysqldump">mysqldump — Database SQL export</option>
                        <option value="zip_paths">zip_paths — Codebase / file paths</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Destination</label>
                    <select id="jobDest" class="form-select"></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Remote Subfolder <span class="opt">(optional)</span></label>
                    <input type="text" id="jobRemoteSub" class="form-input" placeholder="e.g. media">
                    <div class="form-hint">Appended to destination remote_base</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Schedule Hint <span class="opt">(display only)</span></label>
                    <input type="text" id="jobSchedule" class="form-input" placeholder="e.g. daily 02:00">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Options JSON</label>
                <textarea id="jobOptions" class="form-textarea" style="min-height:160px;font-size:.75rem;" spellcheck="false"></textarea>
                <div class="json-hint" id="jobOptionsHint"></div>
            </div>

            <!-- Options quick-help per type -->
            <div id="optionsHelp" style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;font-family:var(--mono);font-size:.72rem;color:var(--text-dim);margin-top:-8px;margin-bottom:14px;"></div>

            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <label class="toggle-row"><input type="checkbox" id="jobActive" checked> Active</label>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Note <span class="opt">(optional)</span></label>
                <input type="text" id="jobNote" class="form-input" placeholder="Brief description">
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-danger" id="btnDeleteJob" onclick="BF.deleteJob()" style="display:none;margin-right:auto;">
                <i class="bi bi-trash"></i> Delete
            </button>
            <button class="btn-secondary" onclick="BF.closeModal('jobModal')">Cancel</button>
            <button class="btn-primary" onclick="BF.saveJob()"><i class="bi bi-check-lg"></i> Save Job</button>
        </div>
    </div>
</div>

<!-- ── DESTINATION EDIT MODAL ── -->
<div class="forge-modal-overlay" id="destModal">
    <div class="forge-modal" style="max-width:520px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title" id="destModalTitle">New Destination</div>
            <button class="forge-modal-close" onclick="BF.closeModal('destModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body">
            <input type="hidden" id="destId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" id="destName" class="form-input" placeholder="Tablet (Hotspot)">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text" id="destSlug" class="form-input" placeholder="tablet_scp">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select id="destType" class="form-select">
                        <option value="scp">SCP (SSH)</option>
                        <option value="local">Local path</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Host Mode</label>
                    <select id="destHostMode" class="form-select">
                        <option value="ap0_scan">ap0 Hotspot Scan (auto)</option>
                        <option value="static">Static IP</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Host <span class="opt">(static mode only)</span></label>
                    <input type="text" id="destHost" class="form-input" placeholder="192.168.43.8">
                </div>
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input type="number" id="destPort" class="form-input" value="8022">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Remote Base Path</label>
                <input type="text" id="destRemoteBase" class="form-input" placeholder="sage_backup">
            </div>
            <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:12px;">
                <label class="toggle-row"><input type="checkbox" id="destActive" checked> Active</label>
            </div>
            <div class="form-group">
                <label class="form-label">Note <span class="opt">(optional)</span></label>
                <input type="text" id="destNote" class="form-input">
            </div>
        </div>
        <div class="forge-modal-footer">
            <button class="btn-danger" id="btnDeleteDest" onclick="BF.deleteDest()" style="display:none;margin-right:auto;">
                <i class="bi bi-trash"></i> Delete
            </button>
            <button class="btn-secondary" onclick="BF.closeModal('destModal')">Cancel</button>
            <button class="btn-primary" onclick="BF.saveDest()"><i class="bi bi-check-lg"></i> Save</button>
        </div>
    </div>
</div>

<!-- ── RUN DETAIL MODAL ── -->
<div class="forge-modal-overlay" id="runModal">
    <div class="forge-modal" style="max-width:700px;">
        <div class="forge-modal-header">
            <div class="forge-modal-title">Run Detail</div>
            <button class="forge-modal-close" onclick="BF.closeModal('runModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="forge-modal-body" id="runModalBody"></div>
        <div class="forge-modal-footer">
            <button class="btn-secondary" onclick="BF.closeModal('runModal')">Close</button>
        </div>
    </div>
</div>

<!-- ── TOAST ── -->
<div class="forge-toast-container" id="toastContainer"></div>

<script>
const PYAPI = '<?= rtrim($PYAPI_URL ?? "http://127.0.0.1:8009", "/") ?>';

// ═══════════════════════════════════════════════════════════════════════════
// BACKUP FORGE — Main Application
// ═══════════════════════════════════════════════════════════════════════════
const BF = (() => {
    'use strict';

    let _jobs        = [];
    let _dests       = [];
    let _currentJob  = null;
    let _activeRunId = null;
    let _pollTimer   = null;
    let _activeLogView = 'live';
    let _lastLogText = null;

    // ── API ────────────────────────────────────────────────────────────────
    async function pyapi(path, opts = {}) {
        const url = `${PYAPI}/backup${path}`;
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json' },
            ...opts,
        });
        if (!res.ok && res.status >= 500) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // ── Toast ──────────────────────────────────────────────────────────────
    function toast(msg, type = 'info', dur = 3000) {
        const el = document.createElement('div');
        el.className = `forge-toast ${type}`;
        el.innerHTML = `<span>${{success:'✓',error:'✕',info:'◆'}[type]||'◆'}</span> ${esc(msg)}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        const dismiss = e => { e.classList.add('out'); setTimeout(() => e.remove(), 300); };
        setTimeout(() => dismiss(el), dur);
    }

    // ── Init ───────────────────────────────────────────────────────────────
    async function init() {
        bindTabs();
        bindLogTabs();
        await Promise.all([loadJobs(), loadDests(), loadGlobalHistory()]);
    }

    // ── Load data ──────────────────────────────────────────────────────────
    async function loadJobs() {
        try {
            const r = await pyapi('/jobs');
            if (r.ok) {
                _jobs = r.data || [];
                renderJobList();
                document.getElementById('statJobs').textContent = _jobs.length;
            }
        } catch(e) { toast('Could not load jobs: ' + e.message, 'error'); }
    }

    async function loadDests() {
        try {
            const r = await pyapi('/destinations');
            if (r.ok) {
                _dests = r.data || [];
                renderDestList();
            }
        } catch(e) { toast('Could not load destinations', 'error'); }
    }

    async function loadGlobalHistory() {
        try {
            const r = await pyapi('/runs?limit=30');
            if (r.ok) renderGlobalHistory(r.data || []);
        } catch(e) {}
    }

    // ── Sidebar renders ────────────────────────────────────────────────────
    function renderJobList() {
        const el = document.getElementById('jobList');
        if (!_jobs.length) {
            el.innerHTML = `<div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">No jobs yet.<br><small>Click + to create one.</small></div>`;
            return;
        }
        el.innerHTML = _jobs.map(j => {
            const isCur = _currentJob && _currentJob.id === j.id;
            return `
            <div class="item-card${isCur?' active':''}" onclick="BF.selectJob(${j.id})">
                <div class="item-card-indicator"></div>
                <div class="item-body">
                    <div class="item-title">${esc(j.name)}</div>
                    <div class="item-meta">
                        <span class="badge ${j.active?'badge-active':'badge-inactive'}">${j.active?'ON':'OFF'}</span>
                        <span class="badge badge-type">${esc(j.job_type)}</span>
                        ${j.destination_name ? `<span class="badge badge-dest">${esc(j.destination_name)}</span>` : ''}
                    </div>
                </div>
                <div class="item-card-actions">
                    <button class="btn-icon-sm" title="Edit" onclick="event.stopPropagation();BF.openJobModal(${j.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    function renderDestList() {
        const el = document.getElementById('destList');
        if (!_dests.length) {
            el.innerHTML = `<div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">No destinations yet.</div>`;
            return;
        }
        el.innerHTML = `<div class="sidebar-section-label">Destinations</div>` +
            _dests.map(d => `
            <div class="item-card" onclick="BF.openDestModal(${d.id})">
                <div class="item-card-indicator"></div>
                <div class="item-body">
                    <div class="item-title">${esc(d.name)}</div>
                    <div class="item-meta">
                        <span class="badge ${d.active?'badge-active':'badge-inactive'}">${d.active?'ON':'OFF'}</span>
                        <span class="badge badge-scp">${esc(d.type)}</span>
                        <span class="badge badge-type">${esc(d.host_mode)}</span>
                    </div>
                </div>
                <div class="item-card-actions">
                    <button class="btn-icon-sm" title="Edit" onclick="event.stopPropagation();BF.openDestModal(${d.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>`).join('');
    }

    function renderGlobalHistory(runs) {
        const el = document.getElementById('globalHistory');
        if (!runs.length) {
            el.innerHTML = `<div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">No runs yet.</div>`;
            // Update stat
            document.getElementById('statRuns').textContent = '0';
            return;
        }
        const today = new Date().toDateString();
        const todayCount = runs.filter(r => new Date(r.created_at).toDateString() === today).length;
        document.getElementById('statRuns').textContent = todayCount;

        el.innerHTML = `<div class="sidebar-section-label">Recent Runs</div>` +
            runs.map(r => `
            <div class="item-card" onclick="BF.showRunDetail(${r.id})">
                <div class="item-card-indicator"></div>
                <div class="item-body">
                    <div class="item-title">${esc(r.job_slug)}</div>
                    <div class="item-meta">
                        <span class="badge badge-${r.status}">${r.status}</span>
                        <span style="font-family:var(--mono);font-size:.65rem;color:var(--text-dim);">${fmtDate(r.created_at)}</span>
                    </div>
                </div>
            </div>`).join('');
    }

    // ── Select job → workspace ─────────────────────────────────────────────
    async function selectJob(id) {
        if (_currentJob && _currentJob.id === id) return;
        try {
            const r = await pyapi(`/jobs/${id}`);
            if (!r.ok) { toast('Failed to load job', 'error'); return; }
            _currentJob = r.data;
            renderWorkspace();
            renderJobList();
            await refreshRunHistory();
        } catch(e) { toast(e.message, 'error'); }
    }

    function renderWorkspace() {
        if (!_currentJob) return;
        const j = _currentJob;

        document.getElementById('forgeEmpty').style.display     = 'none';
        document.getElementById('forgeWorkspace').style.display = 'flex';

        document.getElementById('wsTitle').textContent = j.name;
        document.getElementById('wsMeta').innerHTML = `
            <span class="badge ${j.active?'badge-active':'badge-inactive'}">${j.active?'ACTIVE':'INACTIVE'}</span>
            <span class="badge badge-type">${esc(j.job_type)}</span>
            ${j.destination_name ? `<span class="badge badge-dest">${esc(j.destination_name)}</span>` : ''}
            ${j.schedule_hint ? `<span style="font-family:var(--mono);font-size:.65rem;color:var(--text-dim);">⏱ ${esc(j.schedule_hint)}</span>` : ''}
        `;

        renderJobDetail();
        
        // Only clear logs if we are not actively polling for this specific job
        if (!_pollTimer) {
            clearLiveLog();
            switchLogView('live');
        }
    }

    function renderJobDetail() {
        const j   = _currentJob;
        const opt = j.options || {};
        const el  = document.getElementById('jobDetail');

        const rows = [
            ['Type',        j.job_type],
            ['Destination', j.destination_name || '—'],
            ['Remote path', buildRemotePath(j)],
            ['Slug',        j.slug],
        ];
        if (j.note) rows.push(['Note', j.note]);

        let html = rows.map(([k, v]) => `
            <div style="display:flex;gap:10px;margin-bottom:8px;">
                <div style="font-family:var(--mono);font-size:.65rem;color:var(--amber);text-transform:uppercase;letter-spacing:1px;min-width:90px;padding-top:1px;">${esc(k)}</div>
                <div style="font-family:var(--mono);font-size:.78rem;color:var(--text);">${esc(String(v))}</div>
            </div>`).join('');

        html += `<hr class="section-divider">
            <div class="panel-label">Options</div>
            <pre style="font-family:var(--mono);font-size:.75rem;color:var(--text);background:var(--card);padding:12px;border-radius:var(--radius);border:1px solid var(--border);white-space:pre-wrap;word-break:break-all;">${esc(JSON.stringify(opt, null, 2))}</pre>`;

        el.innerHTML = html;
    }

    function buildRemotePath(j) {
        const base = j.remote_base || (j.destination_remote_base || 'sage_backup');
        const sub  = (j.remote_subfolder || '').replace(/^\/|\/$/g, '');
        return sub ? `${base}/${sub}` : base;
    }

    // ── Run history ────────────────────────────────────────────────────────
    async function refreshRunHistory() {
        if (!_currentJob) return;
        try {
            const r = await pyapi(`/runs?job_id=${_currentJob.id}&limit=20`);
            if (r.ok) {
                const runs = r.data || [];
                renderRunsTable(runs);

                // State Recovery: Automatically resume polling if job is running
                if (runs.length > 0 && runs[0].status === 'running') {
                    resumePolling(runs[0].id);
                } else {
                    const btn = document.getElementById('btnRun');
                    btn.disabled = false;
                    btn.classList.remove('running');
                    if (_pollTimer) {
                        clearInterval(_pollTimer);
                        _pollTimer = null;
                    }
                }
            }
        } catch(e) {}
    }

    function renderRunsTable(runs) {
        const el = document.getElementById('viewRuns');
        if (!runs.length) {
            el.innerHTML = `<div class="log-placeholder"><div class="log-placeholder-icon">📋</div><div class="log-placeholder-text">No runs for this job yet</div></div>`;
            return;
        }
        el.innerHTML = `
        <table class="runs-table">
            <thead><tr>
                <th>Status</th><th>Started</th><th>Elapsed</th>
                <th>Files</th><th>Bytes</th><th>Message</th>
            </tr></thead>
            <tbody>` +
            runs.map(r => `
            <tr onclick="BF.showRunDetail(${r.id})">
                <td><span class="run-status-dot dot-${r.status}"></span><span class="badge badge-${r.status}">${r.status}</span></td>
                <td>${fmtDate(r.started_at)}</td>
                <td>${r.elapsed_sec != null ? r.elapsed_sec + 's' : '—'}</td>
                <td>${r.files_ok ?? 0}/${r.files_total ?? 0}</td>
                <td>${fmtBytes(r.bytes_total)}</td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(r.message||'')}</td>
            </tr>`).join('') +
            `</tbody></table>`;
    }

    // ── Run job ────────────────────────────────────────────────────────────
    async function runJob() {
        if (!_currentJob) return;
        const btn = document.getElementById('btnRun');
        btn.disabled = true;
        btn.classList.add('running');

        clearLiveLog();
        switchLogView('live');
        appendLiveLog(`▶ Starting job: ${_currentJob.name}`, 'log-ok');

        try {
            const r = await pyapi(`/run/${_currentJob.id}`, {
                method: 'POST',
                body: JSON.stringify({ ssh_password: null }),
            });

            if (!r.ok) {
                appendLiveLog(`✕ Failed to queue: ${r.error}`, 'log-err');
                btn.disabled = false;
                btn.classList.remove('running');
                return;
            }

            appendLiveLog('⏳ Queued — waiting for DB lock…', '');
            
            // Wait 1 second for background thread to insert its row, then resume
            setTimeout(refreshRunHistory, 1000);

        } catch(e) {
            appendLiveLog(`✕ Error: ${e.message}`, 'log-err');
            btn.disabled = false;
            btn.classList.remove('running');
        }
    }

    async function resumePolling(runId) {
        if (_pollTimer) return; // Already polling

        _activeRunId = runId;
        const btn = document.getElementById('btnRun');
        btn.disabled = true;
        btn.classList.add('running');
        
        document.getElementById('logPlaceholder').style.display = 'none';
        switchLogView('live');

        // Immediately fetch full log to paint screen fast
        try {
            const full = await pyapi(`/runs/${runId}`);
            if (full.ok && full.data.log_text) {
                renderLiveLog(full.data.log_text);
            }
        } catch(e){}

        _pollTimer = setInterval(async () => {
            await pollRunStatus(btn);
        }, 1500);
    }

    async function pollRunStatus(btn) {
        if (!_activeRunId) return;
        try {
            // Fetch full run directly for logs
            const full = await pyapi(`/runs/${_activeRunId}`);
            if (!full.ok) return;
            const run = full.data;

            if (run.log_text) {
                renderLiveLog(run.log_text);
            }

            if (['done','failed','partial'].includes(run.status)) {
                clearInterval(_pollTimer);
                _pollTimer = null;
                btn.disabled = false;
                btn.classList.remove('running');

                toast(`Backup ${run.status}: ${run.message || run.job_slug}`,
                      run.status === 'done' ? 'success' : 'error', 5000);

                await refreshRunHistory();
                await loadGlobalHistory();
                renderArtifacts(run);
            }
        } catch(e) {
            console.warn('Poll error:', e);
        }
    }

    // ── Live log helpers ───────────────────────────────────────────────────
    function clearLiveLog() {
        _lastLogText = null;
        document.getElementById('logPlaceholder').style.display = 'none';
        const el = document.getElementById('viewLive');
        el.innerHTML = '';
    }

    function renderLiveLog(text) {
        if (!text || text === _lastLogText) return; // Prevent flicker
        _lastLogText = text;

        const el = document.getElementById('viewLive');
        const html = text.split('\n').map(line => {
            let cls = '';
            if (line.includes('OK') || line.includes('✓') || line.includes('done')) cls = 'log-ok';
            else if (line.includes('WARN') || line.includes('Warning')) cls = 'log-warn';
            else if (line.includes('FAIL') || line.includes('Error') || line.includes('failed')) cls = 'log-err';
            else cls = 'log-ts';
            return `<span class="${cls}">${esc(line)}</span>`;
        }).join('\n');
        el.innerHTML = `<div class="run-log">${html}</div>`;
        
        const logBody = document.getElementById('logBody');
        logBody.scrollTop = logBody.scrollHeight;
    }

    function appendLiveLog(msg, cls = '') {
        const el = document.getElementById('viewLive');
        const ts = new Date().toLocaleTimeString();
        el.innerHTML += `<div class="run-log"><span class="log-ts">[${ts}] </span><span class="${cls}">${esc(msg)}</span></div>`;
        const logBody = document.getElementById('logBody');
        logBody.scrollTop = logBody.scrollHeight;
    }

    // ── Artifacts view ─────────────────────────────────────────────────────
    function renderArtifacts(run) {
        const el = document.getElementById('viewArtifacts');
        const artifacts = run.artifacts || [];
        if (!artifacts.length) {
            el.innerHTML = `<div class="log-placeholder"><div class="log-placeholder-icon">📦</div><div class="log-placeholder-text">No artifacts yet</div></div>`;
            return;
        }
        el.innerHTML = `<div class="artifact-list">` +
            artifacts.map(a => `
            <div class="artifact-item">
                <div class="artifact-label">${esc(a.label)}</div>
                <div class="artifact-detail">File: <span>${esc(a.filename)}</span></div>
                <div class="artifact-detail">Remote: <span>${esc(a.remote_path)}</span></div>
                <div class="artifact-detail">Size: <span>${fmtBytes(a.bytes)}</span></div>
                <div class="artifact-detail">SHA256: <span style="font-size:.65rem;">${esc((a.sha256||'').substring(0,32))}…</span></div>
            </div>`).join('') +
            `</div>`;
    }

    // ── Log tabs ──────────────────────────────────────────────────────────
    function bindLogTabs() {
        document.querySelectorAll('.log-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.log-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                switchLogView(btn.dataset.view);
            });
        });
    }

    function switchLogView(view) {
        _activeLogView = view;
        ['live','runs','artifacts'].forEach(v => {
            document.getElementById('view' + cap(v)).style.display = (v === view) ? 'block' : 'none';
        });
        document.getElementById('logPlaceholder').style.display = 'none';
    }

    // ── Show run detail modal ──────────────────────────────────────────────
    async function showRunDetail(runId) {
        try {
            const r = await pyapi(`/runs/${runId}`);
            if (!r.ok) { toast('Failed to load run', 'error'); return; }
            const run = r.data;
            const artifacts = run.artifacts || [];

            let html = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;font-family:var(--mono);font-size:.78rem;">
                ${kv('Job', run.job_slug)}
                ${kv('Type', run.job_type)}
                ${kv('Status', `<span class="badge badge-${run.status}">${run.status}</span>`)}
                ${kv('Started', fmtDate(run.started_at))}
                ${kv('Elapsed', run.elapsed_sec != null ? run.elapsed_sec + 's' : '—')}
                ${kv('Files', `${run.files_ok}/${run.files_total}`)}
                ${kv('Bytes', fmtBytes(run.bytes_total))}
            </div>
            ${run.message ? `<div style="font-family:var(--mono);font-size:.8rem;color:var(--text-dim);margin-bottom:14px;">${esc(run.message)}</div>` : ''}`;

            if (artifacts.length) {
                html += `<div class="panel-label" style="margin-bottom:10px;">Artifacts</div>
                <div class="artifact-list" style="margin-bottom:16px;">` +
                artifacts.map(a => `
                <div class="artifact-item">
                    <div class="artifact-label">${esc(a.label)}</div>
                    <div class="artifact-detail">File: <span>${esc(a.filename)}</span></div>
                    <div class="artifact-detail">Size: <span>${fmtBytes(a.bytes)}</span></div>
                    <div class="artifact-detail" style="font-size:.65rem;">SHA: <span>${esc((a.sha256||'').substring(0,32))}…</span></div>
                </div>`).join('') + `</div>`;
            }

            if (run.log_text) {
                html += `<div class="panel-label" style="margin-bottom:8px;">Log</div>
                <pre style="font-family:var(--mono);font-size:.72rem;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:12px;max-height:280px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;color:var(--text);">${esc(run.log_text)}</pre>`;
            }

            document.getElementById('runModalBody').innerHTML = html;
            document.getElementById('runModal').classList.add('open');
        } catch(e) {
            toast(e.message, 'error');
        }
    }

    function kv(k, v) {
        return `<div><div style="font-size:.65rem;color:var(--amber);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">${esc(k)}</div><div>${v}</div></div>`;
    }

    // ── Job modal ──────────────────────────────────────────────────────────
    const JOB_TYPE_DEFAULTS = {
        media_tar: { sources: ['frames','audios','videos'], incremental: true, verify_sha256: true },
        mysqldump: { databases: ['main'], compress: true, verify_sha256: true },
        zip_paths: { paths: ['src','bash','pyapi','templates','public/*.php','public/js','public/css'], excludes: ['pyapi/venv','pyapi/__pycache__'], compress: true, verify_sha256: true },
    };
    const JOB_TYPE_HELP = {
        media_tar: 'sources: array of "frames","audios","videos"\nincremental: true = only new files since last run\nverify_sha256: true = SHA check after transfer',
        mysqldump: 'databases: array of aliases — "main", "sys", "wordnet"\ncompress: gzip the dump\nverify_sha256: true = SHA check after transfer',
        zip_paths: 'paths: paths to include (wildcards like *.php supported)\nexcludes: paths to skip (e.g. "pyapi/venv")\ncompress: deflate zip',
    };

    function openJobModal(id) {
        const j = id ? _jobs.find(x => x.id === id) : null;
        document.getElementById('jobModalTitle').textContent = j ? 'Edit Job' : 'New Backup Job';
        document.getElementById('jobId').value      = j ? j.id : '';
        document.getElementById('jobName').value    = j ? j.name : '';
        document.getElementById('jobSlug').value    = j ? j.slug : '';
        document.getElementById('jobType').value    = j ? j.job_type : 'media_tar';
        document.getElementById('jobRemoteSub').value = j ? (j.remote_subfolder||'') : '';
        document.getElementById('jobSchedule').value  = j ? (j.schedule_hint||'') : '';
        document.getElementById('jobActive').checked  = j ? !!j.active : true;
        document.getElementById('jobNote').value    = j ? (j.note||'') : '';
        document.getElementById('btnDeleteJob').style.display = j ? 'inline-flex' : 'none';

        // Populate destinations select
        const sel = document.getElementById('jobDest');
        sel.innerHTML = _dests.map(d =>
            `<option value="${d.id}"${j && j.destination_id === d.id ? ' selected':''}>${esc(d.name)}</option>`
        ).join('');

        // Options
        if (j && j.options_json) {
            try {
                document.getElementById('jobOptions').value = JSON.stringify(JSON.parse(j.options_json), null, 2);
            } catch(e) {
                document.getElementById('jobOptions').value = j.options_json;
            }
        } else {
            document.getElementById('jobOptions').value = JSON.stringify(
                JOB_TYPE_DEFAULTS[document.getElementById('jobType').value] || {}, null, 2
            );
        }
        updateOptionsHelp();
        document.getElementById('jobOptionsHint').textContent = '';
        document.getElementById('jobModal').classList.add('open');

        // Auto-slug from name
        document.getElementById('jobName').addEventListener('input', () => {
            if (!document.getElementById('jobId').value) {
                document.getElementById('jobSlug').value =
                    document.getElementById('jobName').value
                        .toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
            }
        }, { once: true });
    }

    function onJobTypeChange() {
        const type = document.getElementById('jobType').value;
        document.getElementById('jobOptions').value =
            JSON.stringify(JOB_TYPE_DEFAULTS[type] || {}, null, 2);
        updateOptionsHelp();
    }

    function updateOptionsHelp() {
        const type = document.getElementById('jobType').value;
        document.getElementById('optionsHelp').textContent = JOB_TYPE_HELP[type] || '';
    }

    async function saveJob() {
        const id   = document.getElementById('jobId').value;
        const name = document.getElementById('jobName').value.trim();
        const slug = document.getElementById('jobSlug').value.trim();
        if (!name || !slug) { toast('Name and slug are required', 'error'); return; }

        let options = {};
        try {
            options = JSON.parse(document.getElementById('jobOptions').value);
            document.getElementById('jobOptionsHint').className = 'json-hint ok';
            document.getElementById('jobOptionsHint').textContent = '✓ Valid JSON';
        } catch(e) {
            document.getElementById('jobOptionsHint').className = 'json-hint err';
            document.getElementById('jobOptionsHint').textContent = '✕ Invalid JSON';
            toast('Options JSON is invalid', 'error');
            return;
        }

        const payload = {
            id:              id ? parseInt(id) : undefined,
            name, slug,
            job_type:         document.getElementById('jobType').value,
            destination_id:   parseInt(document.getElementById('jobDest').value),
            remote_subfolder: document.getElementById('jobRemoteSub').value.trim() || null,
            schedule_hint:    document.getElementById('jobSchedule').value.trim() || null,
            active:           document.getElementById('jobActive').checked ? 1 : 0,
            note:             document.getElementById('jobNote').value.trim() || null,
            options,
        };

        try {
            const r = await pyapi('/jobs', { method: 'POST', body: JSON.stringify(payload) });
            if (r.ok) {
                toast(r.message || 'Saved', 'success');
                closeModal('jobModal');
                await loadJobs();
                if (r.data?.id) { _currentJob = null; await selectJob(r.data.id); }
            } else {
                toast('Save failed: ' + r.error, 'error');
            }
        } catch(e) { toast(e.message, 'error'); }
    }

    async function deleteJob() {
        const id = document.getElementById('jobId').value;
        if (!id || !confirm('Delete this job? Run history will be preserved.')) return;
        try {
            const r = await pyapi(`/jobs/${id}`, { method: 'DELETE' });
            if (r.ok) {
                toast('Job deleted', 'success');
                closeModal('jobModal');
                _currentJob = null;
                document.getElementById('forgeEmpty').style.display = 'flex';
                document.getElementById('forgeWorkspace').style.display = 'none';
                await loadJobs();
            } else {
                toast('Delete failed: ' + r.error, 'error');
            }
        } catch(e) { toast(e.message, 'error'); }
    }

    function editCurrentJob() {
        if (_currentJob) openJobModal(_currentJob.id);
    }

    // ── Destination modal ──────────────────────────────────────────────────
    function openDestModal(id) {
        const d = id ? _dests.find(x => x.id === id) : null;
        document.getElementById('destModalTitle').textContent = d ? 'Edit Destination' : 'New Destination';
        document.getElementById('destId').value         = d ? d.id : '';
        document.getElementById('destName').value       = d ? d.name : '';
        document.getElementById('destSlug').value       = d ? d.slug : '';
        document.getElementById('destType').value       = d ? d.type : 'scp';
        document.getElementById('destHostMode').value   = d ? d.host_mode : 'ap0_scan';
        document.getElementById('destHost').value       = d ? (d.host||'') : '';
        document.getElementById('destPort').value       = d ? d.port : 8022;
        document.getElementById('destRemoteBase').value = d ? d.remote_base : 'sage_backup';
        document.getElementById('destActive').checked   = d ? !!d.active : true;
        document.getElementById('destNote').value       = d ? (d.note||'') : '';
        document.getElementById('btnDeleteDest').style.display = d ? 'inline-flex' : 'none';
        document.getElementById('destModal').classList.add('open');

        document.getElementById('destName').addEventListener('input', () => {
            if (!document.getElementById('destId').value) {
                document.getElementById('destSlug').value =
                    document.getElementById('destName').value
                        .toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
            }
        }, { once: true });
    }

    async function saveDest() {
        const id   = document.getElementById('destId').value;
        const name = document.getElementById('destName').value.trim();
        const slug = document.getElementById('destSlug').value.trim();
        if (!name || !slug) { toast('Name and slug required', 'error'); return; }

        const payload = {
            id:          id ? parseInt(id) : undefined,
            name, slug,
            type:        document.getElementById('destType').value,
            host_mode:   document.getElementById('destHostMode').value,
            host:        document.getElementById('destHost').value.trim() || null,
            port:        parseInt(document.getElementById('destPort').value) || 8022,
            remote_base: document.getElementById('destRemoteBase').value.trim() || 'sage_backup',
            active:      document.getElementById('destActive').checked ? 1 : 0,
            note:        document.getElementById('destNote').value.trim() || null,
        };

        try {
            const r = await pyapi('/destinations', { method: 'POST', body: JSON.stringify(payload) });
            if (r.ok) {
                toast(r.message || 'Saved', 'success');
                closeModal('destModal');
                await loadDests();
            } else {
                toast('Save failed: ' + r.error, 'error');
            }
        } catch(e) { toast(e.message, 'error'); }
    }

    async function deleteDest() {
        const id = document.getElementById('destId').value;
        if (!id || !confirm('Delete this destination?')) return;
        try {
            const r = await pyapi(`/destinations/${id}`, { method: 'DELETE' });
            if (r.ok) {
                toast('Destination deleted', 'success');
                closeModal('destModal');
                await loadDests();
            } else {
                toast('Delete failed: ' + r.error, 'error');
            }
        } catch(e) { toast(e.message, 'error'); }
    }

    // ── Sidebar tab switching ──────────────────────────────────────────────
    function bindTabs() {
        document.querySelectorAll('.sidebar-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.sidebar-tab').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.sidebar-pane').forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('pane' + cap(btn.dataset.pane)).classList.add('active');
                if (btn.dataset.pane === 'history') loadGlobalHistory();
            });
        });

        // Close modals on overlay click
        document.querySelectorAll('.forge-modal-overlay').forEach(o => {
            o.addEventListener('click', e => {
                if (e.target === o) o.classList.remove('open');
            });
        });

        // Options JSON live validate
        document.getElementById('jobOptions').addEventListener('input', () => {
            const hint = document.getElementById('jobOptionsHint');
            try {
                JSON.parse(document.getElementById('jobOptions').value);
                hint.className = 'json-hint ok';
                hint.textContent = '✓ Valid JSON';
            } catch(e) {
                hint.className = 'json-hint err';
                hint.textContent = '✕ Invalid JSON';
            }
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.forge-modal-overlay.open')
                    .forEach(m => m.classList.remove('open'));
            }
        });
    }

    // ── Modal helpers ──────────────────────────────────────────────────────
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    // ── Utils ──────────────────────────────────────────────────────────────
    function esc(s) {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }
    function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    function fmtDate(d) {
        if (!d) return '—';
        const dt = new Date(d.replace(' ', 'T'));
        return isNaN(dt) ? d : dt.toLocaleString(undefined, { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
    }
    function fmtBytes(b) {
        if (!b) return '—';
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
        if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
        return (b/1073741824).toFixed(2) + ' GB';
    }

    // ── Public API ─────────────────────────────────────────────────────────
    return {
        init, selectJob, runJob, refreshRunHistory,
        openJobModal, saveJob, deleteJob, editCurrentJob, onJobTypeChange,
        openDestModal, saveDest, deleteDest,
        showRunDetail, closeModal,
    };
})();

document.addEventListener('DOMContentLoaded', () => BF.init());
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>