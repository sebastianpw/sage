<?php
/**
 * SAGE Pytoon — Webtoon Rollout Kit
 * public/pytoon/index.php
 *
 * Forge UI design system (Space Mono + Syne, amber accents, dark/light via localStorage).
 * Mobile-first. No existing files modified.
 */
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/Pytoon/PytoonManager.php';

use App\Pytoon\PytoonManager;

$spw           = \App\Core\SpwBase::getInstance();
$publicPathAbs = $spw->getPublicPath();
$pyapiUrl      = getenv('PYTOON_PYAPI_URL') ?: 'http://127.0.0.1:8009';
$mgr           = new PytoonManager($pdo, $pyapiUrl, $publicPathAbs);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Pytoon · Webtoon Rollout Kit · SAGE AI</title>
<script>
(function(){
    try{var t=localStorage.getItem('spw_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}
})();
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap');

/* ── Design Tokens ─────────────────────────────────────────────────────── */
:root {
    --bg-void:    #08080a;
    --bg-base:    #0d0d11;
    --bg-raised:  #131318;
    --bg-float:   #1a1a22;
    --bg-hover:   #1f1f28;
    --border:     rgba(255,255,255,0.06);
    --border-mid: rgba(255,255,255,0.10);
    --amber:      #f59e0b;
    --amber-dim:  #92400e;
    --amber-glow: rgba(245,158,11,0.12);
    --green:      #22c55e;
    --red:        #ef4444;
    --blue:       #3b82f6;
    --text-bright:#f0f0f4;
    --text-body:  #b0b0be;
    --text-muted: #6b6b80;
    --text-dim:   #3a3a4e;
    --font-mono:    'Space Mono', monospace;
    --font-display: 'Syne', sans-serif;
    --font-body:    'DM Sans', sans-serif;
    --radius-sm: 4px; --radius: 8px; --radius-lg: 12px;
    --topbar-h: 52px;
}
[data-theme="light"] {
    --bg-void:    #f0f0f4; --bg-base: #ffffff; --bg-raised: #f8f8fb;
    --bg-float:   #ffffff; --bg-hover: #ededf4;
    --border:     rgba(0,0,0,0.07); --border-mid: rgba(0,0,0,0.12);
    --text-bright: #0d0d14; --text-body: #3a3a50;
    --text-muted:  #7878a0; --text-dim: #c0c0d0;
    --amber-glow:  rgba(245,158,11,0.08);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{font-size:14px;-webkit-tap-highlight-color:transparent;}
body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-body);min-height:100vh;overflow-x:hidden;}

/* ── Layout ─────────────────────────────────────────────────────────────── */
.app{display:flex;flex-direction:column;height:100vh;overflow:hidden;}

.topbar{flex-shrink:0;height:var(--topbar-h);background:var(--bg-base);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 16px;gap:12px;z-index:100;}
.hamburger{display:none;background:transparent;border:none;color:var(--text-bright);width:32px;height:32px;align-items:center;justify-content:center;cursor:pointer;padding:0;flex-shrink:0;}
.hamburger svg{width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;}
.topbar-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.brand-icon{width:28px;height:28px;background:linear-gradient(135deg,var(--amber),#f97316);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.brand-icon svg{width:16px;height:16px;fill:#000;}
.brand-text{display:flex;flex-direction:column;}
.brand-name{font-family:var(--font-display);font-weight:800;font-size:14px;color:var(--text-bright);line-height:1.1;}
.brand-sub{font-family:var(--font-mono);font-size:9px;color:var(--amber);letter-spacing:0.12em;text-transform:uppercase;}
.topbar-right{margin-left:auto;display:flex;gap:8px;align-items:center;}

.btn-icon{width:32px;height:32px;border-radius:var(--radius);background:transparent;border:1px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);transition:all 0.15s;}
.btn-icon:hover{background:var(--bg-hover);color:var(--text-bright);border-color:var(--border-mid);}
.btn-icon svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.5;}
.btn-primary{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;background:var(--amber);color:#000;font-family:var(--font-mono);font-size:11px;font-weight:700;text-transform:uppercase;border:none;border-radius:var(--radius);cursor:pointer;transition:0.15s;white-space:nowrap;}
.btn-primary:hover{background:#fbbf24;transform:translateY(-1px);}
.btn-primary:disabled{opacity:0.5;pointer-events:none;}
.btn-secondary{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 14px;background:var(--bg-raised);color:var(--text-body);font-family:var(--font-mono);font-size:11px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:0.15s;white-space:nowrap;}
.btn-secondary:hover{border-color:var(--border-mid);color:var(--text-bright);background:var(--bg-hover);}
.btn-danger{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:6px 12px;background:transparent;color:var(--red);font-family:var(--font-mono);font-size:11px;border:1px solid var(--red);border-radius:var(--radius);cursor:pointer;transition:0.15s;}
.btn-danger:hover{background:rgba(239,68,68,0.1);}

.content-wrap{display:flex;flex:1;overflow:hidden;position:relative;}

/* ── Sidebar ──────────────────────────────────────────────────────────── */
.sidebar{width:270px;background:var(--bg-base);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;flex-shrink:0;z-index:200;}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:150;}
.sidebar-top{padding:14px 16px 8px;flex-shrink:0;}
.sidebar-section-label{font-family:var(--font-mono);font-size:10px;letter-spacing:0.15em;text-transform:uppercase;color:var(--text-dim);font-weight:700;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;}

.nav-item{display:flex;align-items:center;gap:9px;padding:10px 16px;color:var(--text-muted);text-decoration:none;font-size:13px;border:none;background:none;width:100%;text-align:left;cursor:pointer;transition:all 0.12s;position:relative;font-family:var(--font-body);}
.nav-item svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.5;flex-shrink:0;}
.nav-item:hover{background:var(--bg-hover);color:var(--text-bright);}
.nav-item.active{color:var(--amber);background:var(--amber-glow);}
.nav-item.active::before{content:'';position:absolute;left:0;top:4px;bottom:4px;width:3px;background:var(--amber);border-radius:0 3px 3px 0;}
.nav-item .nav-meta{margin-left:auto;font-family:var(--font-mono);font-size:9px;color:var(--text-dim);}
.nav-item.active .nav-meta{color:var(--amber);}

.series-list{display:flex;flex-direction:column;overflow-y:auto;flex:1;}

/* ── Main content ────────────────────────────────────────────────────── */
.main{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:20px;}
.view{display:none;flex-direction:column;gap:20px;}
.view.active{display:flex;}

/* ── Cards ─────────────────────────────────────────────────────────── */
.card{background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;}
.card-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px;}
.card-title{font-family:var(--font-display);font-size:14px;font-weight:700;color:var(--text-bright);}
.card-actions{display:flex;gap:8px;flex-wrap:wrap;}
.card-body{padding:18px;display:flex;flex-direction:column;gap:14px;}

/* ── Forms ─────────────────────────────────────────────────────────── */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-label{font-family:var(--font-mono);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);}
.form-control{background:var(--bg-float);border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;font-family:var(--font-body);font-size:13px;color:var(--text-bright);outline:none;transition:0.15s;width:100%;}
.form-control:focus{border-color:var(--amber);}
select.form-control{cursor:pointer;}

/* ── Cover Canvas ────────────────────────────────────────────────── */
.canvas-section{display:flex;flex-direction:column;gap:14px;}
.canvas-controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.canvas-controls .form-group{min-width:80px;}
.canvas-wrap{position:relative;width:100%;background:#000;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);touch-action:none;user-select:none;}
.canvas-wrap canvas{display:block;width:100%;height:auto;}
.canvas-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;color:var(--text-dim);background:repeating-conic-gradient(rgba(255,255,255,0.03) 0% 25%,transparent 0% 50%) 0 0/24px 24px;}
.canvas-placeholder svg{width:40px;height:40px;stroke:currentColor;fill:none;stroke-width:1;opacity:0.4;}
.canvas-placeholder p{font-family:var(--font-mono);font-size:11px;text-align:center;opacity:0.5;}

#cover-canvas-img{position:absolute;top:0;left:0;transform-origin:0 0;cursor:move;max-width:none;}
#canvas-el{display:block;width:100%;height:auto;}

/* Cover thumb grid */
.covers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;}
.cover-thumb{position:relative;border-radius:var(--radius);overflow:hidden;border:2px solid var(--border);background:var(--bg-void);}
.cover-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.cover-thumb-actions{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,0.8));padding:8px 6px 4px;display:flex;gap:4px;justify-content:flex-end;opacity:0;transition:opacity 0.2s;}
.cover-thumb:hover .cover-thumb-actions{opacity:1;}
@media(hover:none){.cover-thumb-actions{opacity:1;}}

/* ── PDF Splitter ────────────────────────────────────────────────── */
.drop-zone{border:2px dashed var(--border-mid);border-radius:var(--radius-lg);padding:32px 20px;text-align:center;color:var(--text-muted);font-family:var(--font-mono);font-size:12px;cursor:pointer;transition:all 0.2s;position:relative;}
.drop-zone.drag-over{border-color:var(--amber);background:var(--amber-glow);color:var(--amber);}
.drop-zone svg{width:36px;height:36px;stroke:currentColor;fill:none;stroke-width:1;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;opacity:0.5;}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}

/* ── PDF Inbox list ─────────────────────────────────────────────── */
.pdf-row{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-float);border:1px solid var(--border);border-radius:var(--radius);flex-wrap:wrap;}
.pdf-row + .pdf-row{margin-top:6px;}
.pdf-name{font-family:var(--font-mono);font-size:11px;color:var(--text-bright);flex:1;word-break:break-all;}
.pdf-size{font-size:11px;color:var(--text-dim);flex-shrink:0;}

/* ── Jobs list ─────────────────────────────────────────────────── */
.job-row{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-float);border:1px solid var(--border);border-radius:var(--radius);flex-wrap:wrap;}
.job-row + .job-row{margin-top:6px;}
.job-label{font-size:12px;color:var(--text-bright);flex:1;min-width:0;word-break:break-all;}
.job-meta{font-family:var(--font-mono);font-size:10px;color:var(--text-dim);}
.badge{font-family:var(--font-mono);font-size:9px;letter-spacing:0.1em;text-transform:uppercase;padding:3px 8px;border-radius:20px;}
.badge-pending{background:var(--bg-raised);color:var(--text-muted);border:1px solid var(--border);}
.badge-processing{background:rgba(59,130,246,0.15);color:var(--blue);border:1px solid var(--blue);}
.badge-done{background:rgba(34,197,94,0.15);color:var(--green);border:1px solid var(--green);}
.badge-error{background:rgba(239,68,68,0.15);color:var(--red);border:1px solid var(--red);}

/* ── Series source panel ─────────────────────────────────────── */
.series-info-card{background:var(--bg-float);border:1px solid var(--border);border-radius:var(--radius);padding:14px;display:flex;gap:14px;align-items:flex-start;}
.series-cover-thumb{width:60px;height:90px;object-fit:cover;border-radius:4px;background:var(--bg-void);border:1px solid var(--border);flex-shrink:0;}
.series-meta{flex:1;min-width:0;}
.series-meta-title{font-family:var(--font-display);font-size:14px;font-weight:700;color:var(--text-bright);margin-bottom:4px;}
.series-meta-sub{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;}

/* ── Canvas Size Modal ────────────────────────────────────────── */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;display:none;align-items:flex-end;justify-content:center;}
.modal-backdrop.active{display:flex;}
.modal-sheet{width:100%;max-width:480px;background:var(--bg-raised);border:1px solid var(--border);border-bottom:none;border-radius:14px 14px 0 0;padding:0 0 max(16px,env(safe-area-inset-bottom));max-height:80vh;display:flex;flex-direction:column;box-shadow:0 -8px 40px rgba(0,0,0,0.6);animation:slideUp 0.22s ease;}
@keyframes slideUp{from{transform:translateY(60px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.modal-handle{text-align:center;padding:10px 0 4px;cursor:pointer;}
.modal-handle-bar{display:inline-block;width:40px;height:4px;background:var(--border);border-radius:2px;}
.modal-header{padding:6px 16px 10px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);flex-shrink:0;}
.modal-title{font-family:var(--font-display);font-size:14px;font-weight:700;color:var(--text-bright);}
.modal-close{background:transparent;border:1px solid var(--border);color:var(--text-muted);border-radius:4px;width:26px;height:26px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;}
.modal-body{padding:16px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:12px;}
.size-row{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg-float);border:1px solid var(--border);border-radius:var(--radius);}
.size-row-label{flex:1;font-size:13px;color:var(--text-bright);}
.size-row-dims{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);flex-shrink:0;}
.add-size-row{display:flex;gap:8px;align-items:flex-end;border-top:1px solid var(--border);padding-top:14px;flex-wrap:wrap;}
.add-size-row .form-group{flex:1;min-width:70px;}
.add-size-row .form-group:first-child{flex:2;min-width:120px;}

/* ── Cinemagic PDF browser ───────────────────────────────────── */
.section-divider{font-family:var(--font-mono);font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-dim);padding:4px 0 8px;border-bottom:1px solid var(--border);margin-bottom:4px;}

/* ── Spinner ─────────────────────────────────────────────────── */
.spinner{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--amber);border-radius:50%;animation:spin 0.6s linear infinite;display:inline-block;flex-shrink:0;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ── Toast ─────────────────────────────────────────────────────── */
#toast-container{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;}
.toast{background:var(--bg-float);border:1px solid var(--border-mid);border-radius:var(--radius);padding:10px 18px;font-size:12px;color:var(--text-bright);box-shadow:0 8px 24px rgba(0,0,0,0.5);animation:slideIn 0.2s ease;pointer-events:auto;}
.toast.success{border-left:3px solid var(--green);}
.toast.error{border-left:3px solid var(--red);}
.toast.info{border-left:3px solid var(--blue);}
@keyframes slideIn{from{transform:translateX(120%);opacity:0;}to{transform:translateX(0);opacity:1;}}

/* ── Responsive ─────────────────────────────────────────────── */
@media(max-width:800px){
    .hamburger{display:flex;}
    .sidebar{position:fixed;left:-270px;top:var(--topbar-h);bottom:0;transition:left 0.28s ease;box-shadow:4px 0 20px rgba(0,0,0,0.5);}
    .sidebar.open{left:0;}
    .sidebar-overlay.active{display:block;position:fixed;inset:0;top:var(--topbar-h);z-index:150;}
    .brand-text{display:none;}
    .main{padding:14px;}
    .form-row{grid-template-columns:1fr;}
    .card-hdr{flex-direction:column;align-items:flex-start;}
    .card-actions{width:100%;}
    .card-actions .btn-primary,.card-actions .btn-secondary{flex:1;}
}
@media(min-width:801px){
    .sidebar{position:static !important;left:auto !important;}
    .sidebar-overlay{display:none !important;}
}
</style>
</head>
<body>
<div class="app">

<!-- Topbar -->
<header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <a class="topbar-brand" href="index.php">
        <div class="brand-icon">
            <svg viewBox="0 0 16 16"><path d="M1 8h6V1H1zm0 7h6v-5H1zm8 0h6V8H9zm0-14v5h6V1z"/></svg>
        </div>
        <div class="brand-text">
            <div class="brand-name">Pytoon</div>
            <div class="brand-sub">Webtoon Rollout Kit</div>
        </div>
    </a>
    <div class="topbar-right">
        <input type="text" id="pyapi-url-input" class="form-control"
               placeholder="PyAPI URL e.g. http://192.168.x.x:8009"
               style="width:220px;font-size:11px;padding:6px 10px;"
               title="PyAPI base URL — persisted in localStorage">
        <button class="btn-icon" onclick="toggleTheme()" title="Toggle theme">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        </button>
    </div>
</header>

<div class="content-wrap">
    <div class="sidebar-overlay" id="sb-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-section-label">Tools</div>
            <button class="nav-item active" data-view="cover" onclick="switchView('cover',this)">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Cover Composer
            </button>
            <button class="nav-item" data-view="split" onclick="switchView('split',this)">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                PDF → Pages
            </button>
            <button class="nav-item" data-view="gallery" onclick="switchView('gallery',this)">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Saved Assets
            </button>
            <button class="nav-item" data-view="jobs" onclick="switchView('jobs',this)">
                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Job Queue
            </button>
        </div>

        <div class="sidebar-top" style="border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
            <div class="sidebar-section-label">Magazine Series</div>
        </div>
        <div class="series-list" id="sidebar-series-list">
            <div style="padding:14px;color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>
        </div>
    </nav>

    <!-- Main -->
    <main class="main">

        <!-- ── Cover Composer View ──────────────────────────────────────── -->
        <div class="view active" id="view-cover">
            <div class="card">
                <div class="card-hdr">
                    <div class="card-title" id="canvas-size-label">Cover Composer — 1080 × 1920</div>
                    <div class="card-actions">
                        <!-- Gear icon for canvas size settings -->
                        <button class="btn-icon" onclick="openSizeModal()" title="Canvas size settings">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        </button>
                        <button class="btn-secondary" onclick="resetCanvas()">Reset</button>
                        <button class="btn-primary" id="btn-save-cover" onclick="saveCover(false)" disabled>Save Cover</button>
                        <button class="btn-primary" id="btn-download-cover" onclick="saveCover(true)" disabled>Download</button>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Canvas size selector (quick pick) -->
                    <div class="form-group">
                        <label class="form-label">Canvas Size</label>
                        <select class="form-control" id="canvas-size-select" onchange="onCanvasSizeChange()">
                            <option value="">Loading…</option>
                        </select>
                    </div>

                    <!-- Source image picker -->
                    <div class="form-group">
                        <label class="form-label">Source Image</label>
                        <div class="drop-zone" id="cover-drop-zone">
                            <input type="file" id="cover-file-input" accept="image/*" onchange="onCoverFileSelected(this)">
                            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <p>Tap to choose image<br>or drag &amp; drop</p>
                        </div>
                    </div>

                    <!-- Also pick from series/episode covers -->
                    <div class="form-group" id="series-cover-picker" style="display:none;">
                        <label class="form-label">Or use an existing cover as source</label>
                        <div id="series-cover-option"></div>
                    </div>

                    <div class="canvas-section">

                        <!-- Controls -->
                        <div class="canvas-controls">
                            <div class="form-group">
                                <label class="form-label">Scale</label>
                                <input type="range" id="ctrl-scale" min="0.05" max="5" step="0.01" value="1"
                                       class="form-control" style="padding:4px 0;" oninput="applyTransform()">
                            </div>
                            <div class="form-group" style="min-width:60px;">
                                <label class="form-label">Scale val</label>
                                <input type="number" id="ctrl-scale-num" min="0.05" max="10" step="0.01" value="1"
                                       class="form-control" style="width:72px;" oninput="syncScaleFromNum()">
                            </div>
                            <div class="form-group" style="min-width:60px;">
                                <label class="form-label">X px</label>
                                <input type="number" id="ctrl-x" value="0" class="form-control" style="width:70px;" oninput="applyTransform()">
                            </div>
                            <div class="form-group" style="min-width:60px;">
                                <label class="form-label">Y px</label>
                                <input type="number" id="ctrl-y" value="0" class="form-control" style="width:70px;" oninput="applyTransform()">
                            </div>
                            <div class="form-group" style="min-width:70px;">
                                <label class="form-label">Quality</label>
                                <input type="number" id="ctrl-quality" min="60" max="100" value="92" class="form-control" style="width:70px;">
                            </div>
                            <div class="form-group" style="flex:1;min-width:120px;">
                                <label class="form-label">Label / filename</label>
                                <input type="text" id="ctrl-label" class="form-control" value="cover" placeholder="cover">
                            </div>
                        </div>
                        <small style="color:var(--text-dim);font-family:var(--font-mono);font-size:10px;">
                            Drag image on canvas to position · Pinch or use scale slider to zoom
                        </small>

                        <!-- Canvas -->
                        <div class="canvas-wrap" id="canvas-wrap">
                            <div class="canvas-placeholder" id="canvas-placeholder" style="aspect-ratio:9/16;">
                                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <p>Load an image above<br>to begin composing</p>
                            </div>
                            <canvas id="canvas-el" style="display:none;"></canvas>
                            <img id="cover-canvas-img" style="display:none;position:absolute;top:0;left:0;transform-origin:0 0;cursor:move;max-width:none;" draggable="false" alt="">
                        </div>

                        <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim);" id="canvas-dims-label">
                            Canvas: 1080 × 1920 px · All coordinates in canvas-space pixels
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── PDF Split View ──────────────────────────────────────────── -->
        <div class="view" id="view-split">
            <div class="card">
                <div class="card-hdr">
                    <div class="card-title">PDF → Webtoon Pages</div>
                    <div class="card-actions">
                        <button class="btn-secondary" onclick="refreshPdfInbox()">Refresh Inbox</button>
                    </div>
                </div>
                <div class="card-body">
                    <p style="font-size:12px;color:var(--text-muted);line-height:1.6;">
                        Upload a magazine PDF or pick one from the server inbox.
                        Each page is exported as a JPEG and bundled in a downloadable ZIP.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">DPI (quality)</label>
                            <input type="number" id="split-dpi" value="150" min="72" max="300" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">JPEG Quality (1-100)</label>
                            <input type="number" id="split-quality" value="88" min="50" max="100" class="form-control">
                        </div>
                    </div>

                    <!-- ── Cinemagic PDF Browser ─────────────────────────── -->
                    <div class="section-divider">Browse Cinemagic PDFs</div>
                    <p style="font-size:12px;color:var(--text-muted);line-height:1.5;">
                        Select a magazine series to browse its exported PDFs and split them directly.
                    </p>
                    <div class="form-group">
                        <label class="form-label">Magazine Series</label>
                        <select class="form-control" id="cm-series-select" onchange="loadCinemagicPdfs()">
                            <option value="">— Select a series —</option>
                        </select>
                    </div>
                    <div id="cinemagic-pdf-list" style="display:none;">
                        <div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>
                    </div>

                    <!-- ── Upload / Server Inbox ─────────────────────────── -->
                    <div class="section-divider">Upload or Server Inbox</div>

                    <div class="drop-zone" id="pdf-drop-zone">
                        <input type="file" id="pdf-file-input" accept="application/pdf" onchange="onPdfFileSelected(this)">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <p>Tap to upload PDF<br>or drag &amp; drop</p>
                    </div>

                    <div class="card" style="margin-top:4px;">
                        <div class="card-hdr" style="border-bottom:none;padding-bottom:8px;">
                            <div class="card-title" style="font-size:12px;">Server PDF Inbox</div>
                            <small style="font-family:var(--font-mono);font-size:10px;color:var(--text-muted);">media/webtoon/pdf_inbox/</small>
                        </div>
                        <div class="card-body" id="pdf-inbox-list" style="padding-top:4px;">
                            <div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Gallery View ──────────────────────────────────────────────── -->
        <div class="view" id="view-gallery">
            <div class="card">
                <div class="card-hdr">
                    <div class="card-title">Saved Covers</div>
                    <button class="btn-icon" onclick="loadGallery()" title="Refresh">
                        <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                </div>
                <div class="card-body">
                    <div class="covers-grid" id="covers-grid">
                        <div style="grid-column:1/-1;color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-hdr">
                    <div class="card-title">Split ZIPs</div>
                    <button class="btn-icon" onclick="loadJobs()" title="Refresh">
                        <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                </div>
                <div class="card-body" id="split-zips-list">
                    <div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>
                </div>
            </div>
        </div>

        <!-- ── Jobs View ──────────────────────────────────────────────── -->
        <div class="view" id="view-jobs">
            <div class="card">
                <div class="card-hdr">
                    <div class="card-title">Job Queue</div>
                    <button class="btn-icon" onclick="loadJobs()" title="Refresh">
                        <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                </div>
                <div class="card-body" id="jobs-list">
                    <div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>
                </div>
            </div>
        </div>

    </main>
</div>
</div>

<!-- ── Canvas Size Modal ──────────────────────────────────────────────── -->
<div class="modal-backdrop" id="size-modal-backdrop" onmousedown="onSizeModalBackdropClick(event)">
    <div class="modal-sheet">
        <div class="modal-handle" onclick="closeSizeModal()"><div class="modal-handle-bar"></div></div>
        <div class="modal-header">
            <div class="modal-title">Canvas Sizes</div>
            <button class="modal-close" onclick="closeSizeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div id="size-list">
                <div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>
            </div>

            <!-- Add new size form -->
            <div class="add-size-row">
                <div class="form-group">
                    <label class="form-label">Label (optional)</label>
                    <input type="text" id="new-size-label" class="form-control" placeholder="e.g. Tapas (960 × 1440)">
                </div>
                <div class="form-group">
                    <label class="form-label">Width px</label>
                    <input type="number" id="new-size-w" class="form-control" value="960" min="1" style="width:80px;">
                </div>
                <div class="form-group">
                    <label class="form-label">Height px</label>
                    <input type="number" id="new-size-h" class="form-control" value="1440" min="1" style="width:80px;">
                </div>
                <button class="btn-primary" onclick="addCanvasSize()" style="align-self:flex-end;flex-shrink:0;">
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add
                </button>
            </div>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script>
'use strict';

// ── Globals ──────────────────────────────────────────────────────────────────
let CANVAS_W = 1080, CANVAS_H = 1920;
let canvasSizes = []; // loaded from DB

let imgEl, imgNaturalW, imgNaturalH;
let imgX = 0, imgY = 0, imgScale = 1;
let isDragging = false, dragStartX = 0, dragStartY = 0, dragImgStartX = 0, dragImgStartY = 0;
let isPinching = false, pinchStartDist = 0, pinchStartScale = 1;
let canvasDisplayW = 1, canvasDisplayH = 1;
let activeJobs = {};
let sourceImageBlob = null;

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const savedUrl = localStorage.getItem('sage_pyapi_url') || '';
    document.getElementById('pyapi-url-input').value = savedUrl;
    document.getElementById('pyapi-url-input').addEventListener('change', function () {
        localStorage.setItem('sage_pyapi_url', this.value.trim());
    });

    loadCanvasSizes();
    loadSidebar();
    refreshPdfInbox();
    loadGallery();
    loadJobs();
    loadSeriesSelectForPdf();

    initDropZone('cover-drop-zone', 'cover-file-input', onCoverDrop);
    initDropZone('pdf-drop-zone', 'pdf-file-input', onPdfDrop);

    initCanvasInteraction();
});

// ── Theme ────────────────────────────────────────────────────────────────────
function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('spw_theme', next); } catch(e) {}
}

// ── Sidebar ──────────────────────────────────────────────────────────────────
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sb-overlay').classList.toggle('active');
}

function switchView(view, btn) {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('view-' + view).classList.add('active');
    if (btn) btn.classList.add('active');
    if (window.innerWidth <= 800) {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sb-overlay').classList.remove('active');
    }
    if (view === 'gallery') { loadGallery(); loadJobs(); }
    if (view === 'jobs') loadJobs();
}

async function loadSidebar() {
    const data = await api({ action: 'get_series_list' });
    const el = document.getElementById('sidebar-series-list');
    if (!data.success || !data.series.length) {
        el.innerHTML = '<div style="padding:12px 16px;color:var(--text-dim);font-size:11px;font-family:var(--font-mono);">No series found</div>';
        return;
    }
    el.innerHTML = data.series.map(s => `
        <button class="nav-item" onclick="loadSeriesSource(${s.id}, this)">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span style="flex:1;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(s.title)}</span>
            <span class="nav-meta">${s.status === 'published' ? 'PUB' : 'DRF'}</span>
        </button>
    `).join('');
}

async function loadSeriesSource(seriesId, btn) {
    document.querySelectorAll('#sidebar-series-list .nav-item').forEach(n => n.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const coverNavBtn = document.querySelector('[data-view="cover"]');
    switchView('cover', coverNavBtn);

    const detData = await api({ action: 'get_series_list' });
    const found = (detData.series || []).find(s => s.id == seriesId);
    if (!found) return;

    const coverUrl = found.cover_image_url
        ? (found.cover_image_url.startsWith('/') ? found.cover_image_url : '/' + found.cover_image_url)
        : '';

    const pickerEl = document.getElementById('series-cover-picker');
    const optEl    = document.getElementById('series-cover-option');

    let html = '';

    if (coverUrl) {
        html += `
            <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-float);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:8px;">
                <img src="${esc(coverUrl)}" style="width:48px;height:72px;object-fit:cover;border-radius:4px;border:1px solid var(--border);">
                <div style="flex:1;">
                    <div style="font-weight:600;color:var(--text-bright);font-size:13px;">${esc(found.title)}</div>
                    <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:2px;">Series Cover</div>
                </div>
                <button class="btn-secondary" onclick="loadImageFromUrl('${esc(coverUrl)}', '${esc(found.title)}')">Use</button>
            </div>
        `;
    }

    const epData = await api({ action: 'get_series_episodes', series_id: seriesId });
    if (epData.success && epData.episodes) {
        epData.episodes.forEach(ep => {
            if (ep.ep_cover) {
                const epCoverUrl = ep.ep_cover.startsWith('/') ? ep.ep_cover : '/' + ep.ep_cover;
                html += `
                    <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-float);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:8px;">
                        <img src="${esc(epCoverUrl)}" style="width:48px;height:72px;object-fit:cover;border-radius:4px;border:1px solid var(--border);">
                        <div style="flex:1;">
                            <div style="font-weight:600;color:var(--text-bright);font-size:13px;">${esc(ep.name)}</div>
                            <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:2px;">Episode Cover</div>
                        </div>
                        <button class="btn-secondary" onclick="loadImageFromUrl('${esc(epCoverUrl)}', '${esc(ep.name)}')">Use</button>
                    </div>
                `;
            }
        });
    }

    if (html) {
        pickerEl.style.display = 'block';
        optEl.innerHTML = '<div style="max-height:280px;overflow-y:auto;padding-right:6px;">' + html + '</div>';
    } else {
        pickerEl.style.display = 'none';
        optEl.innerHTML = '';
    }

    document.getElementById('ctrl-label').value = found.title.toLowerCase().replace(/[^a-z0-9]+/g, '_');
}

// ── Canvas Sizes ─────────────────────────────────────────────────────────────

async function loadCanvasSizes() {
    const data = await api({ action: 'get_canvas_sizes' });
    if (!data.success) return;
    canvasSizes = data.sizes || [];
    populateSizeSelect();
}

function populateSizeSelect() {
    const sel = document.getElementById('canvas-size-select');
    sel.innerHTML = canvasSizes.map(s =>
        `<option value="${s.id}" data-w="${s.width}" data-h="${s.height}">${esc(s.label)}</option>`
    ).join('');
    // Apply first option on initial load
    if (canvasSizes.length) {
        const first = canvasSizes[0];
        applyCanvasSize(first.width, first.height, first.label);
    }
}

function onCanvasSizeChange() {
    const sel = document.getElementById('canvas-size-select');
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    const w = parseInt(opt.getAttribute('data-w'));
    const h = parseInt(opt.getAttribute('data-h'));
    applyCanvasSize(w, h, opt.text);
}

function applyCanvasSize(w, h, label) {
    CANVAS_W = w;
    CANVAS_H = h;
    document.getElementById('canvas-size-label').textContent = `Cover Composer — ${w} × ${h}`;
    document.getElementById('canvas-dims-label').textContent = `Canvas: ${w} × ${h} px · All coordinates in canvas-space pixels`;
    // Update placeholder aspect ratio
    const ph = document.getElementById('canvas-placeholder');
    ph.style.aspectRatio = `${w} / ${h}`;
    // If an image is already loaded, re-fit it to the new canvas size
    if (imgEl && imgEl.style.display !== 'none') {
        const fitScale = Math.max(CANVAS_W / imgNaturalW, CANVAS_H / imgNaturalH);
        imgScale = fitScale;
        imgX = (CANVAS_W - imgNaturalW * imgScale) / 2;
        imgY = (CANVAS_H - imgNaturalH * imgScale) / 2;
        const canvasEl = document.getElementById('canvas-el');
        canvasEl.width  = CANVAS_W;
        canvasEl.height = CANVAS_H;
        canvasDisplayW = document.getElementById('canvas-wrap').clientWidth;
        canvasDisplayH = canvasDisplayW * (CANVAS_H / CANVAS_W);
        syncControls();
        applyTransform();
        drawCanvasPreview();
    }
}

// ── Canvas Size Modal ─────────────────────────────────────────────────────────

function openSizeModal() {
    document.getElementById('size-modal-backdrop').classList.add('active');
    renderSizeList();
}

function closeSizeModal() {
    document.getElementById('size-modal-backdrop').classList.remove('active');
}

function onSizeModalBackdropClick(e) {
    if (e.target === document.getElementById('size-modal-backdrop')) closeSizeModal();
}

function renderSizeList() {
    const el = document.getElementById('size-list');
    if (!canvasSizes.length) {
        el.innerHTML = '<div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">No sizes defined.</div>';
        return;
    }
    el.innerHTML = canvasSizes.map(s => `
        <div class="size-row">
            <span class="size-row-label">${esc(s.label)}</span>
            <span class="size-row-dims">${s.width} × ${s.height}</span>
            <button class="btn-icon" style="width:28px;height:28px;color:var(--red);" onclick="deleteCanvasSize(${s.id})" title="Delete">
                <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            </button>
        </div>
    `).join('');
}

async function addCanvasSize() {
    const label  = document.getElementById('new-size-label').value.trim();
    const width  = parseInt(document.getElementById('new-size-w').value) || 0;
    const height = parseInt(document.getElementById('new-size-h').value) || 0;
    if (!width || !height) { toast('Enter valid width and height', 'error'); return; }

    const data = await api({ action: 'add_canvas_size', label, width, height });
    if (!data.success) { toast(data.error || 'Add failed', 'error'); return; }

    document.getElementById('new-size-label').value = '';
    toast('Canvas size added', 'success');
    await loadCanvasSizes();
    renderSizeList();
}

async function deleteCanvasSize(id) {
    if (!confirm('Delete this canvas size?')) return;
    const data = await api({ action: 'delete_canvas_size', id });
    if (!data.success) { toast(data.error || 'Delete failed', 'error'); return; }
    toast('Size deleted', 'info');
    await loadCanvasSizes();
    renderSizeList();
}

// ── Cover Canvas ──────────────────────────────────────────────────────────────

function onCoverFileSelected(input) {
    const file = input.files[0];
    if (!file) return;
    loadImageFile(file);
}

function onCoverDrop(file) { loadImageFile(file); }

function loadImageFile(file) {
    const url = URL.createObjectURL(file);
    loadImageFromUrl(url, file.name.replace(/\.[^.]+$/, ''), file);
}

function loadImageFromUrl(url, label, blob = null) {
    sourceImageBlob = blob;
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => {
        imgNaturalW = img.naturalWidth;
        imgNaturalH = img.naturalHeight;

        const placeholder = document.getElementById('canvas-placeholder');
        const canvasEl    = document.getElementById('canvas-el');
        const canvasWrap  = document.getElementById('canvas-wrap');
        placeholder.style.display = 'none';
        canvasEl.style.display = 'block';

        canvasEl.width  = CANVAS_W;
        canvasEl.height = CANVAS_H;

        imgEl = document.getElementById('cover-canvas-img');
        imgEl.src = url;
        imgEl.style.display = 'block';

        canvasDisplayW = canvasWrap.clientWidth;
        canvasDisplayH = canvasDisplayW * (CANVAS_H / CANVAS_W);

        const fitScale = Math.max(CANVAS_W / imgNaturalW, CANVAS_H / imgNaturalH);
        imgScale = fitScale;
        imgX = (CANVAS_W - imgNaturalW * imgScale) / 2;
        imgY = (CANVAS_H - imgNaturalH * imgScale) / 2;

        syncControls();
        applyTransform();
        drawCanvasPreview();

        document.getElementById('btn-save-cover').disabled = false;
        document.getElementById('btn-download-cover').disabled = false;

        if (label) document.getElementById('ctrl-label').value = label.toLowerCase().replace(/[^a-z0-9]+/g, '_');
    };
    img.src = url;
}

function applyTransform() {
    if (!imgEl) return;
    imgX = parseFloat(document.getElementById('ctrl-x').value) || 0;
    imgY = parseFloat(document.getElementById('ctrl-y').value) || 0;
    imgScale = parseFloat(document.getElementById('ctrl-scale').value) || 1;
    document.getElementById('ctrl-scale-num').value = imgScale.toFixed(3);

    const ratio = canvasDisplayW / CANVAS_W;
    imgEl.style.transformOrigin = '0 0';
    imgEl.style.transform = `translate(${imgX * ratio}px,${imgY * ratio}px) scale(${imgScale * ratio})`;
    imgEl.style.width = imgNaturalW + 'px';
    imgEl.style.height = imgNaturalH + 'px';

    drawCanvasPreview();
}

function syncControls() {
    document.getElementById('ctrl-x').value = Math.round(imgX);
    document.getElementById('ctrl-y').value = Math.round(imgY);
    document.getElementById('ctrl-scale').value = imgScale;
    document.getElementById('ctrl-scale-num').value = imgScale.toFixed(3);
}

function syncScaleFromNum() {
    const v = parseFloat(document.getElementById('ctrl-scale-num').value) || 1;
    document.getElementById('ctrl-scale').value = v;
    imgScale = v;
    applyTransform();
}

function drawCanvasPreview() {
    const canvasEl = document.getElementById('canvas-el');
    if (canvasEl.style.display === 'none' || !imgEl || !imgEl.complete) return;
    const ctx = canvasEl.getContext('2d');
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);
    const dw = Math.round(imgNaturalW * imgScale);
    const dh = Math.round(imgNaturalH * imgScale);
    ctx.drawImage(imgEl, 0, 0, imgNaturalW, imgNaturalH, Math.round(imgX), Math.round(imgY), dw, dh);
}

function resetCanvas() {
    imgEl = null; sourceImageBlob = null;
    document.getElementById('canvas-placeholder').style.display = '';
    document.getElementById('canvas-el').style.display = 'none';
    document.getElementById('cover-canvas-img').style.display = 'none';
    document.getElementById('cover-canvas-img').src = '';
    document.getElementById('cover-file-input').value = '';
    document.getElementById('btn-save-cover').disabled = true;
    document.getElementById('btn-download-cover').disabled = true;
    imgX = 0; imgY = 0; imgScale = 1;
    syncControls();
}

// ── Canvas interaction (drag + pinch) ─────────────────────────────────────────

function initCanvasInteraction() {
    const wrap = document.getElementById('canvas-wrap');

    wrap.addEventListener('mousedown', e => {
        if (!imgEl || imgEl.style.display === 'none') return;
        isDragging = true;
        dragStartX = e.clientX; dragStartY = e.clientY;
        dragImgStartX = imgX; dragImgStartY = imgY;
        e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
        if (!isDragging) return;
        const ratio = CANVAS_W / (document.getElementById('canvas-wrap').clientWidth || 1);
        imgX = dragImgStartX + (e.clientX - dragStartX) * ratio;
        imgY = dragImgStartY + (e.clientY - dragStartY) * ratio;
        syncControls(); applyTransform();
    });
    document.addEventListener('mouseup', () => { isDragging = false; });

    wrap.addEventListener('touchstart', e => {
        if (!imgEl || imgEl.style.display === 'none') return;
        if (e.touches.length === 1) {
            isDragging = true; isPinching = false;
            dragStartX = e.touches[0].clientX; dragStartY = e.touches[0].clientY;
            dragImgStartX = imgX; dragImgStartY = imgY;
        } else if (e.touches.length === 2) {
            isDragging = false; isPinching = true;
            pinchStartDist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            pinchStartScale = imgScale;
        }
        e.preventDefault();
    }, { passive: false });

    wrap.addEventListener('touchmove', e => {
        if (!imgEl || imgEl.style.display === 'none') return;
        const ratio = CANVAS_W / (wrap.clientWidth || 1);
        if (isDragging && e.touches.length === 1) {
            imgX = dragImgStartX + (e.touches[0].clientX - dragStartX) * ratio;
            imgY = dragImgStartY + (e.touches[0].clientY - dragStartY) * ratio;
            syncControls(); applyTransform();
        } else if (isPinching && e.touches.length === 2) {
            const dist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            imgScale = Math.max(0.05, pinchStartScale * (dist / pinchStartDist));
            syncControls(); applyTransform();
        }
        e.preventDefault();
    }, { passive: false });

    wrap.addEventListener('touchend', e => {
        if (e.touches.length < 2) isPinching = false;
        if (e.touches.length === 0) isDragging = false;
    });

    window.addEventListener('resize', () => {
        canvasDisplayW = wrap.clientWidth;
        canvasDisplayH = canvasDisplayW * (CANVAS_H / CANVAS_W);
        if (imgEl && imgEl.style.display !== 'none') applyTransform();
    });
}

// ── Save / Download Cover ─────────────────────────────────────────────────────

async function saveCover(downloadOnly) {
    if (!sourceImageBlob && !imgEl) { toast('Load an image first', 'error'); return; }

    const btn = downloadOnly ? document.getElementById('btn-download-cover') : document.getElementById('btn-save-cover');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Processing…';

    try {
        let blob = sourceImageBlob;
        if (!blob) {
            const resp = await fetch(imgEl.src);
            blob = await resp.blob();
        }

        const fd = new FormData();
        fd.append('cover_file', blob, 'source.jpg');
        fd.append('x',        Math.round(imgX));
        fd.append('y',        Math.round(imgY));
        fd.append('scale',    imgScale);
        fd.append('canvas_w', CANVAS_W);
        fd.append('canvas_h', CANVAS_H);
        fd.append('quality',  document.getElementById('ctrl-quality').value);
        fd.append('label',    document.getElementById('ctrl-label').value || 'cover');
        fd.append('pyapi_url', getPyapiUrl());

        if (downloadOnly) {
            fd.append('action', 'compose_cover_download');
            const resp = await fetch('api.php', { method: 'POST', body: fd });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const resBlob = await resp.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(resBlob);
            a.download = (document.getElementById('ctrl-label').value || 'cover') + `_${CANVAS_W}x${CANVAS_H}.jpg`;
            a.click();
            toast('Cover downloaded!', 'success');
        } else {
            fd.append('action', 'compose_cover_save');
            const data = await apiRaw(fd);
            if (!data.success) throw new Error(data.error || 'Save failed');
            toast('Cover saved → ' + data.url, 'success');
            loadGallery();
        }
    } catch (e) {
        toast('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = downloadOnly ? 'Download' : 'Save Cover';
    }
}

// ── Cinemagic PDF Browser ─────────────────────────────────────────────────────

async function loadSeriesSelectForPdf() {
    const data = await api({ action: 'get_series_list' });
    const sel = document.getElementById('cm-series-select');
    if (!data.success || !data.series.length) return;
    sel.innerHTML = '<option value="">— Select a series —</option>' +
        data.series.map(s => `<option value="${s.id}">${esc(s.title)}</option>`).join('');
}

async function loadCinemagicPdfs() {
    const sid = document.getElementById('cm-series-select').value;
    const listEl = document.getElementById('cinemagic-pdf-list');
    if (!sid) { listEl.style.display = 'none'; return; }

    listEl.style.display = 'block';
    listEl.innerHTML = '<div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">Loading…</div>';

    const data = await api({ action: 'get_cinemagic_pdfs', series_id: sid });
    if (!data.success || !data.pdfs.length) {
        listEl.innerHTML = '<div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">No exported PDFs found for this series. Generate them first via Cinemagic Hub → PDF Export.</div>';
        return;
    }

    listEl.innerHTML = data.pdfs.map(p => `
        <div class="pdf-row">
            <span class="pdf-name">
                <strong style="color:var(--text-bright);">${esc(p.sequence_name)}</strong>
                <span style="color:var(--text-dim);font-size:10px;"> [${esc(p.lang)}]</span><br>
                <span style="font-size:10px;color:var(--text-dim);">${esc(p.filename)}</span>
            </span>
            <span class="pdf-size">${fmtBytes(p.size)}</span>
            <button class="btn-secondary" style="padding:5px 10px;font-size:10px;"
                    onclick="splitCinemagicPdf('${esc(p.rel_path)}')">Split</button>
        </div>
    `).join('');
}

async function splitCinemagicPdf(relPath) {
    const dpi     = parseInt(document.getElementById('split-dpi').value)     || 150;
    const quality = parseInt(document.getElementById('split-quality').value) || 88;

    const data = await api({
        action: 'split_cinemagic_pdf',
        rel_path: relPath,
        dpi,
        quality,
        pyapi_url: getPyapiUrl()
    });
    if (!data.success) { toast(data.error || 'Failed', 'error'); return; }
    toast('PDF split job started', 'success');
    activeJobs[data.pyapi_job_id] = { job_db_id: data.job_db_id };
    schedulePoll(data.pyapi_job_id);
    switchView('jobs', document.querySelector('[data-view="jobs"]'));
    loadJobs();
}

// ── PDF Split ─────────────────────────────────────────────────────────────────

function onPdfFileSelected(input) {
    const file = input.files[0];
    if (!file) return;
    uploadAndSplitPdf(file);
}

function onPdfDrop(file) { uploadAndSplitPdf(file); }

async function uploadAndSplitPdf(file) {
    const dpi     = parseInt(document.getElementById('split-dpi').value)     || 150;
    const quality = parseInt(document.getElementById('split-quality').value) || 89;

    toast('Uploading PDF…', 'info');

    const fd = new FormData();
    fd.append('action',    'upload_and_split');
    fd.append('pdf_file',  file, file.name);
    fd.append('dpi',       dpi);
    fd.append('quality',   quality);
    fd.append('pyapi_url', getPyapiUrl());

    try {
        const data = await apiRaw(fd);
        if (!data.success) throw new Error(data.error || 'Submission failed');
        toast('PDF submitted — splitting…', 'success');
        activeJobs[data.pyapi_job_id] = { job_db_id: data.job_db_id };
        schedulePoll(data.pyapi_job_id);
        switchView('jobs', document.querySelector('[data-view="jobs"]'));
        loadJobs();
    } catch (e) {
        toast('Error: ' + e.message, 'error');
    }
}

async function splitInboxPdf(relPath) {
    const dpi     = parseInt(document.getElementById('split-dpi').value)     || 150;
    const quality = parseInt(document.getElementById('split-quality').value) || 88;

    const data = await api({ action: 'split_inbox_pdf', rel_path: relPath, dpi, quality, pyapi_url: getPyapiUrl() });
    if (!data.success) { toast(data.error || 'Failed', 'error'); return; }
    toast('PDF split job started', 'success');
    activeJobs[data.pyapi_job_id] = { job_db_id: data.job_db_id };
    schedulePoll(data.pyapi_job_id);
    switchView('jobs', document.querySelector('[data-view="jobs"]'));
    loadJobs();
}

function schedulePoll(pyapiJobId) {
    setTimeout(() => pollJob(pyapiJobId), 3000);
}

async function pollJob(pyapiJobId) {
    const info = activeJobs[pyapiJobId];
    if (!info) return;
    try {
        const data = await api({
            action:        'poll_pdf_job',
            job_db_id:     info.job_db_id,
            pyapi_job_id:  pyapiJobId,
            pyapi_url:     getPyapiUrl(),
        });
        loadJobs();
        if (!data.success || data.status === 'error') {
            toast('Job error — check queue', 'error');
            delete activeJobs[pyapiJobId];
        } else if (data.status === 'done') {
            toast(`Split done! ${data.page_count} pages ready — see Saved Assets.`, 'success');
            delete activeJobs[pyapiJobId];
            loadGallery();
        } else {
            schedulePoll(pyapiJobId);
        }
    } catch (e) {
        schedulePoll(pyapiJobId);
    }
}

// ── PDF Inbox ─────────────────────────────────────────────────────────────────

async function refreshPdfInbox() {
    const data = await api({ action: 'get_pdf_inbox' });
    const el = document.getElementById('pdf-inbox-list');
    if (!data.success || !data.pdfs.length) {
        el.innerHTML = '<div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">No PDFs in inbox. Upload one above or drop files into media/webtoon/pdf_inbox/ on the server.</div>';
        return;
    }
    el.innerHTML = data.pdfs.map(p => `
        <div class="pdf-row">
            <span class="pdf-name">${esc(p.filename)}</span>
            <span class="pdf-size">${fmtBytes(p.size)}</span>
            <button class="btn-secondary" style="padding:5px 10px;font-size:10px;" onclick="splitInboxPdf('${esc(p.rel_path)}')">Split</button>
        </div>
    `).join('');
}

// ── Gallery ───────────────────────────────────────────────────────────────────

async function loadGallery() {
    const data = await api({ action: 'get_saved_covers' });
    const grid = document.getElementById('covers-grid');
    if (!data.success || !data.covers.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">No saved covers yet.</div>';
    } else {
        grid.innerHTML = data.covers.map(c => {
            // Infer aspect ratio from filename (e.g. _1080x1920.jpg) for display
            const dimMatch = c.filename.match(/_(\d+)x(\d+)\.jpg$/i);
            const ar = dimMatch ? `${dimMatch[1]}/${dimMatch[2]}` : '9/16';
            return `
                <div class="cover-thumb" style="aspect-ratio:${ar};">
                    <img src="${esc(c.url)}" loading="lazy" alt="${esc(c.filename)}">
                    <div class="cover-thumb-actions">
                        <a class="btn-icon" style="width:26px;height:26px;text-decoration:none;" href="api.php?action=download_cover&path=${encodeURIComponent(c.url.substring(1))}" download title="Download">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>
                        <button class="btn-icon" style="width:26px;height:26px;color:var(--red);" onclick="deleteAsset('${esc(c.url.substring(1))}', this.closest('.cover-thumb'))" title="Delete">
                            <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }
    loadSplitZips();
}

async function loadSplitZips() {
    const data = await api({ action: 'get_jobs' });
    const el = document.getElementById('split-zips-list');
    if (!el) return;
    const doneJobs = (data.jobs || []).filter(j => j.job_type === 'pdf_split' && j.status === 'done' && j.result_zip);
    if (!doneJobs.length) {
        el.innerHTML = '<div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">No completed splits yet.</div>';
        return;
    }
    el.innerHTML = doneJobs.map(j => {
        const relPath = j.result_zip.replace(/^.*?public\//, '').replace(/^\//, '');
        return `
            <div class="job-row">
                <div class="job-label">${esc(j.label)}<br>
                    <span style="font-size:10px;color:var(--text-dim);font-family:var(--font-mono);">${j.page_count} pages · ${j.created_at}</span>
                </div>
                <a class="btn-secondary" href="api.php?action=download_zip&path=${encodeURIComponent(relPath)}" download>Download ZIP</a>
                <button class="btn-danger" onclick="deleteAsset('${esc(relPath)}', this.closest('.job-row'))">Delete</button>
            </div>
        `;
    }).join('');
}

async function loadJobs() {
    const data = await api({ action: 'get_jobs' });
    const el = document.getElementById('jobs-list');
    if (!data.success || !data.jobs.length) {
        el.innerHTML = '<div style="color:var(--text-dim);font-size:12px;font-family:var(--font-mono);">No jobs yet.</div>';
        return;
    }
    el.innerHTML = data.jobs.map(j => {
        const relPath = j.result_zip
            ? j.result_zip.replace(/^.*?public\//, '').replace(/^\//, '')
            : '';
        const isActive = !!activeJobs[j.pyapi_job_id];
        return `
            <div class="job-row">
                <span class="badge badge-${j.status}">${j.status.toUpperCase()}</span>
                <div class="job-label">
                    <strong style="color:var(--text-bright);">${esc(j.label)}</strong><br>
                    <span class="job-meta">${esc(j.job_type)} · ${j.page_count} pages · ${j.created_at}</span>
                    ${j.error_msg ? `<br><span style="color:var(--red);font-size:10px;">${esc(j.error_msg)}</span>` : ''}
                </div>
                ${isActive ? '<span class="spinner"></span>' : ''}
                ${relPath && j.status === 'done'
                    ? `<a class="btn-secondary" style="font-size:10px;padding:5px 10px;" href="api.php?action=download_zip&path=${encodeURIComponent(relPath)}" download>ZIP</a>`
                    : ''}
            </div>
        `;
    }).join('');
}

async function deleteAsset(relPath, elToRemove) {
    if (!confirm('Delete this asset?')) return;
    const data = await api({ action: 'delete_asset', rel_path: relPath });
    if (data.success) {
        elToRemove?.remove();
        toast('Deleted', 'info');
    } else {
        toast(data.error || 'Delete failed', 'error');
    }
}

// ── Drop Zones ────────────────────────────────────────────────────────────────

function initDropZone(zoneId, inputId, onFileCb) {
    const zone  = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    if (!zone) return;

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('drag-over');
        const file = e.dataTransfer?.files?.[0];
        if (file) onFileCb(file);
    });
    if (input) {
        input.addEventListener('change', e => { const f = e.target.files[0]; if(f) onFileCb(f); });
    }
}

// ── API helpers ───────────────────────────────────────────────────────────────

function getPyapiUrl() {
    return (document.getElementById('pyapi-url-input').value.trim() || 'http://127.0.0.1:8009').replace(/\/$/, '');
}

async function api(payload) {
    try {
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => fd.append(k, v));
        const r = await fetch('api.php', { method: 'POST', body: fd });
        return await r.json();
    } catch (e) { return { success: false, error: e.message }; }
}

async function apiRaw(fd) {
    const r = await fetch('api.php', { method: 'POST', body: fd });
    return await r.json();
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function fmtBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}

function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>

<?php echo $eruda ?? ''; ?>

</body>
</html>
