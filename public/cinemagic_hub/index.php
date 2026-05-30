<?php
/**
 * SAGE Cinemagic Hub — Magazine Series Dashboard
 * public/cinemagic_hub/index.php
 */
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/CinemagicHub/CinemagicHubManager.php';

use App\CinemagicHub\CinemagicHubManager;

$hub = new CinemagicHubManager($pdo);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Cinemagic Magazine Hub · SAGE AI</title>
<script>
(function(){
    try { var t=localStorage.getItem('spw_theme'); if(t) document.documentElement.setAttribute('data-theme',t); } catch(e){}
})();
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap');

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
    --green:  #22c55e;
    --purple: #a855f7;
    --red:    #ef4444;
    --blue:   #3b82f6;
    --text-bright: #f0f0f4;
    --text-body:   #b0b0be;
    --text-muted:  #6b6b80;
    --text-dim:    #3a3a4e;
    --font-mono:    'Space Mono', monospace;
    --font-display: 'Syne', sans-serif;
    --font-body:    'DM Sans', sans-serif;
    --radius-sm: 4px; --radius: 8px; --radius-lg: 12px;
}
[data-theme="light"] {
    --bg-void:    #f4f4f6; --bg-base: #ffffff; --bg-raised: #f9f9fb;
    --bg-float:   #ffffff; --bg-hover: #f0f0f5;
    --border:     rgba(0,0,0,0.07); --border-mid: rgba(0,0,0,0.12);
    --text-bright: #0d0d14; --text-body: #3a3a50;
    --text-muted:  #7878a0; --text-dim: #c0c0d0;
    --amber-glow:  rgba(245,158,11,0.08);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body { font-family: var(--font-body); background: var(--bg-void); color: var(--text-body); min-height: 100vh; line-height: 1.6; overflow-x:hidden;}

.hub-layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

.topbar { flex-shrink: 0; height: 52px; background: var(--bg-base); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 16px; gap: 12px; z-index: 100; position:relative; }
.hamburger { display: none; background: transparent; border: none; color: var(--text-bright); width: 32px; height: 32px; align-items: center; justify-content: center; cursor: pointer; padding: 0;}
.hamburger svg { width: 24px; height: 24px; stroke: currentColor; stroke-width: 2; fill: none; }
.topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.brand-icon { width: 28px; height: 28px; background: linear-gradient(135deg, var(--amber), #f97316); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;}
.brand-icon svg { width: 16px; height: 16px; fill: #000; }
.brand-text { display: flex; flex-direction: column; }
.brand-name { font-family: var(--font-display); font-weight: 800; font-size: 14px; color: var(--text-bright); line-height:1.1;}
.brand-sub  { font-family: var(--font-mono); font-size: 9px; color: var(--amber); letter-spacing: 0.12em; text-transform: uppercase; }
.topbar-right { margin-left: auto; display: flex; gap: 8px; }

.btn-icon { width: 32px; height: 32px; border-radius: var(--radius); background: transparent; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: all 0.15s; }
.btn-icon:hover { background: var(--bg-hover); color: var(--text-bright); border-color: var(--border-mid); }
.btn-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.5; }
.btn-primary { display: inline-flex; align-items: center; justify-content:center; gap: 6px; padding: 8px 16px; background: var(--amber); color: #000; font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase; border: none; border-radius: var(--radius); cursor: pointer; transition: 0.15s; text-decoration:none; white-space:nowrap; }
.btn-primary:hover { background: #fbbf24; transform: translateY(-1px); }
.btn-secondary { display: inline-flex; align-items: center; justify-content:center; gap: 6px; padding: 8px 14px; background: var(--bg-raised); color: var(--text-body); font-family: var(--font-mono); font-size: 11px; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: 0.15s; text-decoration:none; white-space:nowrap; }
.btn-secondary:hover { border-color: var(--border-mid); color: var(--text-bright); background: var(--bg-hover); }

.content-wrapper { display: flex; flex: 1; overflow: hidden; position: relative; }

.sidebar { width: 280px; background: var(--bg-base); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; flex-shrink:0; z-index: 200; }
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 150; opacity: 0; transition: opacity 0.3s; }
.sidebar-top { padding: 14px 16px 8px; flex-shrink: 0; }
.sidebar-label { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: var(--text-dim); font-weight: 700; margin-bottom: 8px; display:flex; justify-content:space-between; align-items:center; }

.nav-item { display: flex; align-items: center; gap: 9px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; font-size: 13px; border: none; background: none; width: 100%; text-align: left; cursor: pointer; transition: all 0.12s; position: relative; font-family: var(--font-body); border-bottom: 1px solid transparent; }
.nav-item svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.5; flex-shrink: 0; }
.nav-item:hover { background: var(--bg-hover); color: var(--text-bright); }
.nav-item.active { color: var(--amber); background: var(--amber-glow); }
.nav-item.active::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: var(--amber); border-radius: 0 3px 3px 0; }
.nav-badge { margin-left: auto; font-family: var(--font-mono); font-size: 9px; padding: 2px 6px; background: var(--bg-float); border: 1px solid var(--border); border-radius: 20px; color: var(--text-muted); }
.nav-item.active .nav-badge { background: var(--amber-dim); border-color: var(--amber); color: var(--amber); }

.main-content { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 24px; position:relative; }
.view { display: none; flex-direction: column; gap: 24px; }
.view.active { display: flex; }

.covers-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.cover-card { display: flex; flex-direction: column; background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; text-decoration: none; transition: transform 0.15s, border-color 0.15s; }
.cover-card:hover { transform: translateY(-2px); border-color: var(--amber); box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
.cover-image { width: 100%; aspect-ratio: 2/3; object-fit: cover; background: var(--bg-void); border-bottom: 1px solid var(--border); display: block; color: transparent; }
.cover-title { padding: 12px 8px; font-family: var(--font-display); font-size: 13px; font-weight: 700; color: var(--text-bright); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
@media (min-width: 600px) {
    .covers-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}

.card { background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.card-hdr { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border); flex-wrap:wrap; gap:12px; }
.card-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--text-bright); }
.card-actions { display:flex; gap:8px; flex-wrap:wrap; }
.card-body { padding: 20px; display: flex; flex-direction: column; gap: 16px;}

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); }
.form-control { background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 14px; font-family: var(--font-body); font-size: 14px; color: var(--text-bright); outline: none; transition: 0.15s; resize: vertical; width:100%; box-sizing:border-box;}
.form-control:focus { border-color: var(--amber); }
select.form-control { cursor: pointer; }

.assigned-season { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--radius); transition: 0.1s; gap:10px; }
.assigned-season:hover { border-color: var(--border-mid); background: var(--bg-hover); }
.assigned-season-title { font-size:14px; font-weight:600; color:var(--text-bright); line-height:1.3; }

.sb-picker-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 300000; display: none; align-items: flex-end; justify-content: center; }
.sb-picker-backdrop.active { display: flex; }
.sb-picker-sheet { width: 100%; max-width: 800px; background: var(--bg-raised); border: 1px solid var(--border); border-bottom: none; border-radius: 14px 14px 0 0; padding: 0 0 max(16px,env(safe-area-inset-bottom)); max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 -8px 40px rgba(0,0,0,0.6); animation: slideUp 0.22s ease; }
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.sb-picker-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.sb-picker-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.sb-picker-header { padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.sb-picker-title { font-size: 14px; font-weight: 700; color: var(--text-bright); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
.sb-picker-close { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.sb-picker-filters { padding: 10px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; flex-direction: column; gap: 8px; }

.asset-picker-tabs { display: flex; gap: 4px; background: var(--bg-float); padding: 3px; border-radius: var(--radius); }
.asset-tab { flex: 1; padding: 5px 8px; background: transparent; border: none; border-radius: var(--radius-sm); font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); cursor: pointer; transition: all 0.15s; letter-spacing: 0.06em; }
.asset-tab.active { background: var(--bg-raised); color: var(--amber); }

.container-list { display: flex; flex-direction: column; gap: 4px; overflow-y: auto; max-height: 150px; margin-top: 8px; border-top: 1px solid var(--border); padding-top: 8px; }
.container-item { padding: 6px 10px; border-radius: 4px; border: 1px solid transparent; background: var(--bg-float); cursor: pointer; transition: 0.15s; }
.container-item:hover { background: var(--bg-hover); border-color: var(--border); }
.container-item.active { background: var(--amber-glow); border-color: var(--amber); }
.container-item-name { font-size: 12px; font-weight: 600; color: var(--text-bright); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.container-item-meta { font-size: 10px; color: var(--text-muted); display: flex; justify-content: space-between; margin-top: 2px; }

.picker-grid-frames { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; padding: 10px; overflow-y: auto; flex: 1; }
.picker-grid-frames .f-card { aspect-ratio: 1; border: 2px solid var(--border); border-radius: 4px; overflow: hidden; position: relative; cursor: pointer; transition: 0.15s; background: var(--bg-void); }
.picker-grid-frames .f-card:hover { border-color: var(--amber); }
.picker-grid-frames .f-card.selected { border-color: var(--amber); box-shadow: 0 0 0 1px var(--amber); }
.picker-grid-frames .f-card img { width: 100%; height: 100%; object-fit: cover; }
.picker-grid-frames .f-label { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); font-size: 10px; padding: 2px 4px; text-align: center; color: #fff; font-family: var(--font-mono); pointer-events: none; }
.asset-check { position: absolute; top: 5px; left: 5px; width: 16px; height: 16px; background: var(--amber); border-radius: 50%; display: none; align-items: center; justify-content: center; z-index: 5; }
.f-card.selected .asset-check { display: flex; }
.asset-check svg { width: 10px; height: 10px; stroke: #000; fill: none; stroke-width: 2.5; }

.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--amber); border-radius: 50%; animation: spin 0.6s linear infinite; margin: 20px auto; }
@keyframes spin { to { transform: rotate(360deg); } }

#toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast { background: var(--bg-float); border: 1px solid var(--border-mid); border-radius: var(--radius); padding: 12px 20px; font-size: 13px; color: var(--text-bright); box-shadow: 0 10px 30px rgba(0,0,0,0.5); animation: slideIn 0.2s ease; }
.toast.success { border-left: 3px solid var(--green); }
.toast.error   { border-left: 3px solid var(--red); }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* PDF Export view */
.pdf-job-row { display:flex; align-items:center; gap:12px; padding:12px 16px; background:var(--bg-float); border:1px solid var(--border); border-radius:var(--radius); flex-wrap:wrap; }
.pdf-job-row + .pdf-job-row { margin-top:6px; }
.pdf-status-badge { font-family:var(--font-mono); font-size:9px; letter-spacing:0.1em; text-transform:uppercase; padding:3px 8px; border-radius:20px; flex-shrink:0; }
.pdf-status-badge.pending    { background:var(--bg-raised); color:var(--text-muted); border:1px solid var(--border); }
.pdf-status-badge.processing { background:rgba(59,130,246,0.15); color:var(--blue); border:1px solid var(--blue); }
.pdf-status-badge.done       { background:rgba(34,197,94,0.15);  color:var(--green); border:1px solid var(--green); }
.pdf-status-badge.error      { background:rgba(239,68,68,0.15);  color:var(--red);   border:1px solid var(--red); }

@media (max-width: 960px) {
    .hamburger { display: flex; }
    .brand-text { display: none; }
    .brand-text { display: flex; }
    .sidebar { position: fixed; left: -280px; top: 52px; bottom: 0; transition: left 0.3s ease; border-right: none; box-shadow: 4px 0 20px rgba(0,0,0,0.5); }
    .sidebar.open { left: 0; }
    .sidebar-overlay.active { display: block; opacity: 1; }
    .main-content { padding: 16px; gap: 16px; }
    .card-hdr { flex-direction: column; align-items: flex-start; }
    .card-actions { width: 100%; }
    .card-actions button { flex: 1; }
    .form-row { grid-template-columns: 1fr; gap: 12px; }
}
@media (max-width: 480px) {
    .assigned-season { flex-direction: column; align-items: flex-start; }
    .assigned-season button { align-self: flex-end; }
}
</style>
</head>
<body>

<div class="hub-layout">

<header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <a class="topbar-brand" href="index.php">
        <div class="brand-icon">
            <svg viewBox="0 0 16 16"><path d="M8 1l1.8 3.6 4 .6-2.9 2.8.7 4L8 10l-3.6 1.9.7-4L2.2 5.2l4-.6z"/></svg>
        </div>
        <div class="brand-text">
            <div class="brand-name">Magazine Hub</div>
            <div class="brand-sub">Series Manager</div>
        </div>
    </a>
    <div class="topbar-right">
        <button class="btn-icon" title="Refresh Dashboard" onclick="refreshCurrent()">
            <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </button>
    </div>
</header>

<div class="content-wrapper">
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-label">Overview</div>
            <button class="nav-item active" data-view="dashboard" onclick="switchView('dashboard', this)">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </button>
            <button class="nav-item" onclick="openLangManager()">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                System Languages
            </button>
        </div>
        <div class="sidebar-top" style="border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding-bottom:14px;">
            <div class="sidebar-label">
                Magazine Series
                <button class="btn-icon" style="width:28px;height:28px;" onclick="openSeriesEditor()" title="New Series">
                    <svg viewBox="0 0 24 24" style="width:16px;height:16px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
            </div>
        </div>
        <div id="sidebar-series-list" style="display:flex; flex-direction:column; overflow-y:auto; flex:1;"></div>
    </nav>

    <main class="main-content" id="main-content">

        <!-- Dashboard View -->
        <div class="view active" id="view-dashboard">
            <div class="covers-grid" id="dashboard-covers"></div>
        </div>

        <!-- Series Editor View -->
        <div class="view" id="view-series-editor">
            <div class="card">
                <div class="card-hdr">
                    <div class="card-title" id="editor-title">Edit Series</div>
                    <div class="card-actions">
                        <button class="btn-secondary" id="btn-preview" onclick="previewSeries()" style="display:none;" title="Preview Magazine Series">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;"><circle cx="12" cy="12" r="3"/><path d="M12 5C7 5 3.14 8.68 2 12c1.14 3.32 5 7 10 7s8.86-3.68 10-7c-1.14-3.32-5-7-10-7z"/></svg>
                            Preview
                        </button>
                        <button class="btn-secondary" id="btn-export" onclick="exportSeries()" style="display:none;" title="Download Standalone ZIP">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </button>
                        <button class="btn-secondary" id="btn-rollout" onclick="rolloutSeries()" style="display:none;" title="Rollout to GitHub Pages">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                        </button>
                        <!-- PDF Export button — shown only when series is loaded -->
                        <button class="btn-secondary" id="btn-pdf-export" onclick="openPdfExportView()" style="display:none;" title="Export PDF Issues">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.5;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            PDF
                        </button>
                        <button class="btn-primary" onclick="saveSeries()">Save</button>
                        <button class="btn-icon" style="color:var(--red);" onclick="deleteSeries()" id="btn-delete" style="display:none;" title="Delete Series">
                            <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <input type="hidden" id="series-id">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Magazine Title</label>
                            <input type="text" class="form-control" id="series-title" placeholder="e.g. The Anima Chronicles">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Publishing Status</label>
                            <select class="form-control" id="series-status">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cover Image URL</label>
                            <div style="display:flex;gap:8px;">
                                <input type="text" class="form-control" id="series-cover" placeholder="/img/cover.jpg" style="flex:1;">
                                <button type="button" class="btn-secondary" onclick="openAssetPickerSheet()">Select Frame</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Magazine Template</label>
                            <select class="form-control" id="series-template">
                                <option value="default">Classic Elegance (Centered)</option>
                                <option value="hero_backdrop">Cinematic Immersive (Backdrop)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supported Languages</label>
                        <div id="series-lang-checkboxes" style="display:flex;gap:12px;flex-wrap:wrap;padding:4px 0;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Asset URL Prefix (Optional)</label>
                        <input type="text" class="form-control" id="series-prefix" placeholder="https://cdn.example.com/assets/">
                        <small style="color:var(--text-muted);font-size:11px;margin-top:2px;">If empty, standalone export uses local relative assets.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Synopsis / Description</label>
                        <textarea class="form-control" id="series-desc" rows="4" placeholder="Overview of the series..."></textarea>
                    </div>
                </div>
            </div>

            <div class="card" id="seasons-card" style="display:none;">
                <div class="card-hdr">
                    <div class="card-title">Assigned Seasons (Cinemagics)</div>
                </div>
                <div class="card-body">
                    <div id="assigned-seasons-list" style="display:flex; flex-direction:column; gap:8px;"></div>
                    <div style="display:flex; gap:10px; align-items:center; margin-top:16px; border-top:1px solid var(--border); padding-top:16px; flex-wrap:wrap;">
                        <select class="form-control" id="unassigned-seasons" style="flex:1; min-width:200px;">
                            <option value="">— Select an unassigned Cinemagic to attach —</option>
                        </select>
                        <button class="btn-secondary" onclick="assignSeason()">+ Add Season</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- PDF Export View -->
        <div class="view" id="view-pdf-export">
            <div class="card">
                <div class="card-hdr">
                    <div class="card-title">
                        PDF Export &mdash; <span id="pdf-series-label" style="color:var(--amber);">…</span>
                    </div>
                    <div class="card-actions">
                        <button class="btn-secondary" id="btn-pdf-back" onclick="backFromPdfExport()">
                            ← Back to Series
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--text-muted);line-height:1.6;">
                        Select an episode (Sequence) and export it as a styled PDF per language.
                        Images are packaged directly on the server and sent to PyAPI.
                        You receive a ZIP with one PDF per language.
                    </p>

                    <div class="form-group">
                        <label class="form-label">PyAPI Base URL</label>
                        <input type="text" class="form-control" id="pdf-pyapi-url"
                               placeholder="http://192.168.x.x:8009">
                        <small style="color:var(--text-muted);font-size:11px;margin-top:2px;">
                            Persisted in localStorage. Use phone (8009) or tablet for heavier jobs.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Episode / Issue (Sequence)</label>
                        <select class="form-control" id="pdf-sequence-select">
                            <option value="">— Loading… —</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Languages to Export</label>
                        <div id="pdf-lang-checkboxes" style="display:flex;gap:12px;flex-wrap:wrap;padding:4px 0;"></div>
                    </div>

                    <button class="btn-primary" onclick="submitPdfExportJob()" id="pdf-submit-btn"
                            style="width:100%;justify-content:center;margin-top:4px;">
                        <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Generate PDF Export
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-hdr">
                    <div class="card-title">Export Jobs</div>
                    <button class="btn-icon" onclick="refreshPdfJobs()" title="Refresh jobs">
                        <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                </div>
                <div class="card-body" id="pdf-jobs-list">
                    <div style="color:var(--text-muted);font-size:13px;">No jobs yet.</div>
                </div>
            </div>
        </div>

    </main>
</div>
</div>

<!-- Asset Picker Bottom Sheet -->
<div class="sb-picker-backdrop" id="assetPickerBackdrop" onmousedown="onPickerBackdropClick(event)">
    <div class="sb-picker-sheet">
        <div class="sb-picker-handle" onclick="closeAssetPickerSheet()"><div class="sb-picker-handle-bar"></div></div>
        <div class="sb-picker-header">
            <div class="sb-picker-title">Select Cover Frame</div>
            <button class="sb-picker-close" onclick="closeAssetPickerSheet()">✕</button>
        </div>
        <div class="sb-picker-filters">
            <div class="asset-picker-tabs">
                <button class="asset-tab active" onclick="switchPickerTab('sequences', this)">Sequences</button>
                <button class="asset-tab" onclick="switchPickerTab('cinemagics', this)">Cinemagics</button>
            </div>
            <div>
                <div style="display:flex;gap:6px;margin-bottom:6px;">
                    <input type="text" id="picker-container-search" class="form-control" placeholder="Search…" oninput="debounceContainerSearch()" style="flex:1;padding:4px 8px;font-size:13px;">
                    <div style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
                        <button class="btn-icon" style="width:28px;height:28px;" onclick="changeContainerPage(-1)">←</button>
                        <input type="number" id="picker-container-page" value="1" style="width:36px;text-align:center;background:var(--bg-raised);border:1px solid var(--border);color:var(--amber);border-radius:4px;font-size:12px;height:28px;" onchange="containerCurPage=parseInt(this.value)||1;loadContainers(currentPickerTab)">
                        <span style="font-size:10px;color:var(--text-muted);" id="picker-container-total">/ 1</span>
                        <button class="btn-icon" style="width:28px;height:28px;" onclick="changeContainerPage(1)">→</button>
                    </div>
                </div>
                <div id="picker-container-list" class="container-list"></div>
            </div>
        </div>
        <div id="picker-asset-grid" class="picker-grid-frames">
            <div class="spinner" style="grid-column:1/-1;"></div>
        </div>
        <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <span id="picker-selection-count" style="font-size:12px;color:var(--text-muted);">0 selected</span>
            <button class="btn-primary" onclick="confirmAssetSelection()">Confirm Selection</button>
        </div>
    </div>
</div>

<!-- Language Manager Bottom Sheet -->
<div class="sb-picker-backdrop" id="langManagerBackdrop" onmousedown="if(event.target===this) closeLangManager()">
    <div class="sb-picker-sheet" style="max-width:500px; margin:auto;">
        <div class="sb-picker-handle" onclick="closeLangManager()"><div class="sb-picker-handle-bar"></div></div>
        <div class="sb-picker-header">
            <div class="sb-picker-title">System Languages</div>
            <button class="sb-picker-close" onclick="closeLangManager()">✕</button>
        </div>
        <div style="padding:16px;">
            <div style="display:flex;gap:8px;margin-bottom:16px;">
                <input type="text" id="lang-code" placeholder="Code (en)" class="form-control" maxlength="2" style="width:80px;text-transform:lowercase;">
                <input type="text" id="lang-name" placeholder="Language Name" class="form-control" style="flex:1;">
                <button class="btn-primary" onclick="saveLanguage()">Save</button>
            </div>
            <div id="lang-list" style="display:flex;flex-direction:column;gap:8px;max-height:40vh;overflow-y:auto;"></div>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script>
let currentPickerTab   = 'sequences';
let tempSelectedAsset  = null;
let containerCurPage   = 1;
let containerTotalPages = 1;
let selectedContainerId = null;
let containerSearchTimer = null;
let systemLanguages = [];

// PDF export runtime state
const pdfExport = {
    seriesId:    0,
    seriesTitle: '',
    seriesLangs: [],
    activeJobs:  {},  // pyapi_job_id → { job_db_id, pyapi_url, ready }
};

document.addEventListener('DOMContentLoaded', () => {
    refreshCurrent();
    initLangs();
    const saved = localStorage.getItem('sage_pyapi_url');
    if (saved) document.getElementById('pdf-pyapi-url').value = saved;
    document.getElementById('pdf-pyapi-url').addEventListener('change', function() {
        localStorage.setItem('sage_pyapi_url', this.value.trim());
    });
});

function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebar-overlay');
    sb.classList.toggle('open');
    ov.classList.toggle('active');
}

function switchView(view, btn = null) {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('view-' + view).classList.add('active');
    if (btn) btn.classList.add('active');
    if (window.innerWidth <= 960) {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebar-overlay').classList.remove('active');
    }
}

function refreshCurrent() {
    loadDashboard();
    loadSidebar();
}

async function loadDashboard() {
    const data = await api({ action: 'get_published_magazines_for_local' });
    if (data.success) {
        const grid = document.getElementById('dashboard-covers');
        if (!data.magazines || data.magazines.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; padding:40px; text-align:center; color:var(--text-muted); font-size:13px;">No published magazines found.</div>';
            return;
        }
        grid.innerHTML = data.magazines.map(m => `
            <a href="api.php?action=preview_series&id=${m.id}" target="_blank" class="cover-card" title="Preview ${escHtml(m.title)}">
                <img src="${m.resolved_cover ? escHtml(m.resolved_cover) : ''}" class="cover-image" alt="${escHtml(m.title)} Cover" loading="lazy">
                <div class="cover-title">${escHtml(m.title)}</div>
            </a>
        `).join('');
    }
}

async function loadSidebar() {
    const data = await api({ action: 'get_series_list' });
    if (data.success) {
        document.getElementById('sidebar-series-list').innerHTML = data.series.map(s => `
            <button class="nav-item" data-id="${s.id}" onclick="openSeriesEditor(${s.id}, this)">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <span style="flex:1;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(s.title)}</span>
                <span class="nav-badge">${s.status === 'published' ? 'PUB' : 'DRF'}</span>
            </button>
        `).join('');
    }
}

// ── Languages ────────────────────────────────────────────────────────────────
async function initLangs() {
    const res = await api({ action: 'get_languages' });
    if (res.success) {
        systemLanguages = res.languages;
        if (document.getElementById('series-id').value) renderLangCheckboxes();
    }
}

function renderLangCheckboxes(selectedCsv = null) {
    const supported = selectedCsv !== null
        ? selectedCsv.split(',')
        : Array.from(document.querySelectorAll('.lang-cb:checked')).map(cb => cb.value);
    document.getElementById('series-lang-checkboxes').innerHTML = systemLanguages.map(l => `
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;background:var(--bg-float);padding:6px 12px;border-radius:6px;border:1px solid var(--border);">
            <input type="checkbox" value="${l.code}" class="lang-cb"
                   ${supported.includes(l.code) || l.code === 'en' ? 'checked' : ''}
                   ${l.code === 'en' ? 'disabled' : ''}>
            ${escHtml(l.name)}
        </label>
    `).join('');
}

function openLangManager() {
    document.getElementById('langManagerBackdrop').classList.add('active');
    loadLanguages();
}

function closeLangManager() {
    document.getElementById('langManagerBackdrop').classList.remove('active');
    initLangs();
}

async function loadLanguages() {
    const res = await api({ action: 'get_languages' });
    if (res.success) {
        document.getElementById('lang-list').innerHTML = res.languages.map(l => `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:var(--bg-float);border:1px solid var(--border);border-radius:4px;">
                <div><strong style="color:var(--amber);text-transform:uppercase;margin-right:8px;">${l.code}</strong> ${escHtml(l.name)}</div>
                ${l.is_main == 1
                    ? '<span style="font-size:10px;color:var(--text-muted);font-weight:bold;">MAIN</span>'
                    : `<button class="btn-icon" style="color:var(--red);width:26px;height:26px;" onclick="deleteLanguage('${l.code}')"><svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>`}
            </div>
        `).join('');
    }
}

async function saveLanguage() {
    const code = document.getElementById('lang-code').value.trim();
    const name = document.getElementById('lang-name').value.trim();
    if (code.length !== 2 || !name) return toast('Invalid 2-letter code or name', 'warn');
    const res = await api({ action: 'save_language', code, name });
    if (res.success) {
        document.getElementById('lang-code').value = '';
        document.getElementById('lang-name').value = '';
        loadLanguages();
        toast('Language saved', 'success');
    } else toast(res.error || 'Save failed', 'error');
}

async function deleteLanguage(code) {
    if (!confirm('Delete this system language?')) return;
    const res = await api({ action: 'delete_language', code });
    if (res.success) { loadLanguages(); toast('Language deleted', 'info'); }
}

// ── Series Editor ─────────────────────────────────────────────────────────────
async function openSeriesEditor(id = null, btn = null) {
    if (!btn && id) btn = document.querySelector(`.nav-item[data-id="${id}"]`);
    switchView('series-editor', btn);

    const els = ['btn-preview', 'btn-export', 'btn-rollout', 'btn-delete', 'seasons-card', 'btn-pdf-export'];

    if (!id) {
        document.getElementById('editor-title').textContent = 'New Magazine Series';
        document.getElementById('series-id').value         = '';
        document.getElementById('series-title').value      = '';
        document.getElementById('series-status').value     = 'draft';
        document.getElementById('series-prefix').value     = '';
        document.getElementById('series-cover').value      = '';
        document.getElementById('series-template').value   = 'default';
        document.getElementById('series-desc').value       = '';
        renderLangCheckboxes('en');
        els.forEach(e => document.getElementById(e).style.display = 'none');
    } else {
        document.getElementById('editor-title').textContent = 'Edit Magazine Series';
        const data = await api({ action: 'get_series_details', id });
        if (!data.success) return toast(data.error, 'error');

        const s = data.series;
        document.getElementById('series-id').value       = s.id;
        document.getElementById('series-title').value    = s.title;
        document.getElementById('series-status').value   = s.status;
        document.getElementById('series-prefix').value   = s.asset_url_prefix || '';
        document.getElementById('series-cover').value    = s.cover_image_url || '';
        document.getElementById('series-template').value = s.template || 'default';
        document.getElementById('series-desc').value     = s.description || '';

        renderLangCheckboxes(s.supported_languages || 'en');

        const list = document.getElementById('assigned-seasons-list');
        list.innerHTML = data.seasons.map(ss => `
            <div class="assigned-season">
                <div class="assigned-season-title">#${ss.id} — ${escHtml(ss.name)}</div>
                <button class="btn-icon" style="color:var(--red);width:36px;height:36px;" title="Remove Season"
                        onclick="removeSeason(${s.id}, ${ss.id})">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        `).join('');
        if (!data.seasons.length)
            list.innerHTML = '<div style="font-size:13px;color:var(--text-muted);padding:10px 0;">No Cinemagics attached yet.</div>';

        loadUnassignedSeasons(s.id);
        els.forEach(e => document.getElementById(e).style.display = '');
    }
}

async function loadUnassignedSeasons(seriesId) {
    const data = await api({ action: 'get_unassigned_seasons', series_id: seriesId });
    if (!data.success) return;
    document.getElementById('unassigned-seasons').innerHTML =
        '<option value="">— Select an unassigned Cinemagic to attach —</option>' +
        data.seasons.map(ss => `<option value="${ss.id}">#${ss.id} — ${escHtml(ss.name)}</option>`).join('');
}

async function saveSeries() {
    const langs = Array.from(document.querySelectorAll('.lang-cb:checked')).map(cb => cb.value).join(',');
    const payload = {
        action:             'save_series',
        id:                 document.getElementById('series-id').value,
        title:              document.getElementById('series-title').value.trim(),
        description:        document.getElementById('series-desc').value.trim(),
        status:             document.getElementById('series-status').value,
        cover_image_url:    document.getElementById('series-cover').value.trim(),
        template:           document.getElementById('series-template').value,
        supported_languages: langs,
        asset_url_prefix:   document.getElementById('series-prefix').value.trim(),
    };
    if (!payload.title) return toast('Title required', 'error');
    const data = await api(payload);
    if (data.success) {
        toast('Series saved successfully', 'success');
        refreshCurrent();
        if (!payload.id) openSeriesEditor(data.id);
    } else toast(data.error || 'Failed to save', 'error');
}

async function deleteSeries() {
    const id = document.getElementById('series-id').value;
    if (!id || !confirm('Permanently delete this Series? Seasons and Episodes will NOT be deleted.')) return;
    const data = await api({ action: 'delete_series', id });
    if (data.success) {
        toast('Series deleted', 'info');
        refreshCurrent();
        switchView('dashboard', document.querySelector('.nav-item[data-view="dashboard"]'));
    }
}

async function assignSeason() {
    const sid = document.getElementById('series-id').value;
    const cid = document.getElementById('unassigned-seasons').value;
    if (!cid) return toast('Please select a season first', 'error');
    const data = await api({ action: 'assign_season', series_id: sid, cinemagic_id: cid });
    if (data.success) { toast('Season attached', 'success'); openSeriesEditor(sid); }
}

async function removeSeason(sid, cid) {
    if (!confirm('Detach this season from the series?')) return;
    const data = await api({ action: 'remove_season', series_id: sid, cinemagic_id: cid });
    if (data.success) { toast('Season removed', 'info'); openSeriesEditor(sid); }
}

function previewSeries() {
    const id = document.getElementById('series-id').value;
    window.open(`api.php?action=preview_series&id=${id}`, '_blank');
}

function exportSeries() {
    window.location.href = `api.php?action=export_series_zip&id=${document.getElementById('series-id').value}`;
}

async function rolloutSeries() {
    const id = document.getElementById('series-id').value;
    if (!confirm('Rollout this Magazine Series to GitHub Pages repository?')) return;
    const data = await api({ action: 'rollout_series', id });
    if (data.success) toast('Rollout queued to Forge Jobs!', 'success');
    else toast(data.error || 'Rollout failed', 'error');
}

// ── PDF Export ────────────────────────────────────────────────────────────────
async function openPdfExportView() {
    const id    = parseInt(document.getElementById('series-id').value);
    const title = document.getElementById('series-title').value.trim();
    const langs = Array.from(document.querySelectorAll('.lang-cb:checked')).map(cb => cb.value);

    pdfExport.seriesId    = id;
    pdfExport.seriesTitle = title;
    pdfExport.seriesLangs = langs;

    document.getElementById('pdf-series-label').textContent = title;

    // Language checkboxes limited to series' supported set
    document.getElementById('pdf-lang-checkboxes').innerHTML = systemLanguages
        .filter(l => langs.includes(l.code))
        .map(l => `
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;
                          background:var(--bg-float);padding:6px 12px;border-radius:6px;border:1px solid var(--border);">
                <input type="checkbox" value="${l.code}" class="pdf-lang-cb" checked>
                ${escHtml(l.name)}
            </label>
        `).join('');

    await loadPdfEpisodeList(id);
    await refreshPdfJobs();
    switchView('pdf-export');
}

function backFromPdfExport() {
    const btn = document.querySelector(`.nav-item[data-id="${pdfExport.seriesId}"]`);
    openSeriesEditor(pdfExport.seriesId, btn);
}

async function loadPdfEpisodeList(seriesId) {
    const sel = document.getElementById('pdf-sequence-select');
    sel.innerHTML = '<option value="">— Loading… —</option>';

    const res = await api({ action: 'get_series_episodes', series_id: seriesId });
    
    if (!res.success || !res.episodes || !res.episodes.length) {
        sel.innerHTML = '<option value="">— No episodes attached to this series —</option>';
        return;
    }

    sel.innerHTML = '<option value="">— Select Episode / Issue —</option>' +
        res.episodes.map(ep => `<option value="${ep.id}">${escHtml(ep.season_name)}: ${escHtml(ep.name)} (#${ep.id})</option>`).join('');
}

async function submitPdfExportJob() {
    const pyapiUrl = document.getElementById('pdf-pyapi-url').value.trim().replace(/\/$/, '');
    if (!pyapiUrl) return toast('Enter PyAPI base URL first', 'error');

    const seqId = parseInt(document.getElementById('pdf-sequence-select').value);
    if (!seqId) return toast('Select an episode first', 'error');

    const langs = Array.from(document.querySelectorAll('.pdf-lang-cb:checked')).map(cb => cb.value);
    if (!langs.length) return toast('Select at least one language', 'error');

    const btn = document.getElementById('pdf-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Submitting to PyAPI...';

    try {
        // PHP now does ALL the heavy lifting, file packaging, and PyAPI submission
        const res = await api({
            action:      'submit_pdf_export_job',
            series_id:   pdfExport.seriesId,
            sequence_id: seqId,
            languages:   langs.join(','),
            pyapi_url:   pyapiUrl
        });

        if (!res.success) throw new Error(res.error || 'Submission failed on server');

        const pyJobId = res.pyapi_job_id;
        const jobDbId = res.job_db_id;

        toast(`Job submitted — ID: ${pyJobId.slice(0, 8)}…`, 'success');

        // Track + poll
        pdfExport.activeJobs[pyJobId] = { job_db_id: jobDbId, pyapi_url: pyapiUrl, ready: false };
        schedulePdfPoll(pyJobId);
        await refreshPdfJobs();

    } catch (err) {
        toast('Export failed: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Generate PDF Export';
    }
}

function schedulePdfPoll(pyJobId) {
    setTimeout(() => pollPdfJob(pyJobId), 3000);
}

async function pollPdfJob(pyJobId) {
    const info = pdfExport.activeJobs[pyJobId];
    if (!info) return;
    try {
        const resp = await fetch(`${info.pyapi_url}/magazine-pdf/status/${pyJobId}`);
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const data = await resp.json();

        // ADDED missing pyapi_job_id and pyapi_url so the PHP backend knows how to fetch the zip when done
        await api({
            action:        'update_pdf_job_status',
            job_db_id:     info.job_db_id,
            status:        data.status,
            error_message: data.error_message || '',
            pyapi_job_id:  pyJobId,
            pyapi_url:     info.pyapi_url
        });

        await refreshPdfJobs();

        if (data.status === 'done') {
            info.ready = true;
            toast('PDF export ready! Download below.', 'success');
        } else if (data.status === 'error') {
            toast('PDF job error: ' + (data.error_message || 'unknown'), 'error');
            delete pdfExport.activeJobs[pyJobId];
        } else {
            schedulePdfPoll(pyJobId);
        }
    } catch (e) {
        console.warn('Poll error', pyJobId, e);
        schedulePdfPoll(pyJobId);
    }
}


async function refreshPdfJobs() {
    if (!pdfExport.seriesId) return;
    const data = await api({ action: 'get_pdf_jobs', series_id: pdfExport.seriesId });
    const container = document.getElementById('pdf-jobs-list');
    if (!data.success || !data.jobs || !data.jobs.length) {
        container.innerHTML = '<div style="color:var(--text-muted);font-size:13px;">No jobs yet.</div>';
        return;
    }

    container.innerHTML = data.jobs.map(job => {
        const pyJobId = Object.entries(pdfExport.activeJobs).find(([, v]) => v.job_db_id == job.id)?.[0];
        const isActive = !!pyJobId;
        const isReady  = isActive && pdfExport.activeJobs[pyJobId]?.ready;

        return `<div class="pdf-job-row">
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-bright);
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    Seq #${job.sequence_id} &middot; ${escHtml(job.languages)}
                </div>
                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">${escHtml(job.created_at)}</div>
                ${job.error_message
                    ? `<div style="font-size:10px;color:var(--red);margin-top:2px;">${escHtml(job.error_message)}</div>`
                    : ''}
            </div>
            <span class="pdf-status-badge ${job.status}">${job.status.toUpperCase()}</span>
            
            ${((job.status === 'processing' || job.status === 'pending') && isActive)
                ? `<div class="spinner" style="width:16px;height:16px;margin:0;"></div>`
                : ''}
        </div>`;
    }).join('');
}

// ── Asset Picker ──────────────────────────────────────────────────────────────
function openAssetPickerSheet() {
    tempSelectedAsset = null;
    document.getElementById('assetPickerBackdrop').classList.add('active');
    containerCurPage = 1; selectedContainerId = null;
    document.getElementById('picker-container-search').value = '';
    loadContainers(currentPickerTab);
}

function closeAssetPickerSheet() { document.getElementById('assetPickerBackdrop').classList.remove('active'); }

function onPickerBackdropClick(e) {
    if (e.target === document.getElementById('assetPickerBackdrop')) closeAssetPickerSheet();
}

function switchPickerTab(type, btn) {
    document.querySelectorAll('.asset-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    currentPickerTab = type;
    document.getElementById('picker-asset-grid').innerHTML = '';
    containerCurPage = 1; selectedContainerId = null;
    document.getElementById('picker-container-search').value = '';
    loadContainers(type);
}

function debounceContainerSearch() {
    clearTimeout(containerSearchTimer);
    containerSearchTimer = setTimeout(() => { containerCurPage = 1; loadContainers(currentPickerTab); }, 300);
}

function changeContainerPage(d) {
    const n = containerCurPage + d;
    if (n >= 1 && n <= containerTotalPages) { containerCurPage = n; loadContainers(currentPickerTab); }
}

async function loadContainers(type) {
    const list   = document.getElementById('picker-container-list');
    const search = document.getElementById('picker-container-search').value.trim();
    list.innerHTML = '<div style="padding:8px;text-align:center;"><div class="spinner" style="margin:0 auto;width:14px;height:14px;border-width:2px;"></div></div>';

    const action = type === 'sequences' ? 'search_sequences' : 'search_cinemagics';
    const data   = await api({ action, q: search, page: containerCurPage });
    if (!data.success) return;

    containerTotalPages = data.pages || 1;
    document.getElementById('picker-container-page').value = containerCurPage;
    document.getElementById('picker-container-total').textContent = `/ ${containerTotalPages}`;

    list.innerHTML = '';
    if (!data.items?.length) {
        list.innerHTML = '<div style="padding:8px;text-align:center;font-size:10px;color:var(--text-muted)">None found</div>';
        document.getElementById('picker-asset-grid').innerHTML = '';
        return;
    }
    data.items.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'container-item' + (item.id == selectedContainerId ? ' active' : '');
        itemEl.onclick   = () => selectContainer(item.id, itemEl, type);
        const count = item.frame_count ?? item.seq_count ?? 0;
        itemEl.innerHTML = `
            <div class="container-item-name">${escHtml(item.name)}</div>
            <div class="container-item-meta">
                <span>${escHtml(item.meta || '')}</span>
                <span>${count} ${type === 'cinemagics' ? 'seq' : 'fr'}</span>
            </div>`;
        list.appendChild(itemEl);
    });
    if (!selectedContainerId && data.items.length > 0)
        selectContainer(data.items[0].id, list.querySelector('.container-item'), type);
}

function selectContainer(id, elItem, type) {
    document.querySelectorAll('.container-item').forEach(i => i.classList.remove('active'));
    if (elItem) elItem.classList.add('active');
    selectedContainerId = id;
    loadContainerFrames(type, id);
}

async function loadContainerFrames(type, containerId) {
    const grid = document.getElementById('picker-asset-grid');
    grid.innerHTML = '<div class="spinner" style="grid-column:1/-1"></div>';

    if (type === 'cinemagics') {
        grid.innerHTML = '<div style="grid-column:1/-1;padding:20px;text-align:center;color:var(--text-muted);font-size:12px;">Use Sequences tab to pick frames.</div>';
        return;
    }

    const data = await api({ action: 'get_sequence_frames', sequence_id: containerId });
    if (!data.success || !data.assets.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;">No frames found</div>';
        return;
    }

    grid.innerHTML = data.assets.map(a =>
        `<div class="f-card" data-url="${escHtml(a.url)}" onclick="toggleAssetSelection(this)">
            <div class="asset-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            ${a.url ? `<img src="${escHtml(a.url)}" loading="lazy">` : ''}
            <div class="f-label">${escHtml(a.name) || '#' + a.id}</div>
        </div>`
    ).join('');
    updatePickerCount();
}

function toggleAssetSelection(itemEl) {
    const url = itemEl.getAttribute('data-url');
    if (!url) return;
    document.querySelectorAll('.f-card.selected').forEach(e => e.classList.remove('selected'));
    tempSelectedAsset = url;
    itemEl.classList.add('selected');
    updatePickerCount();
}

function updatePickerCount() {
    document.getElementById('picker-selection-count').textContent =
        tempSelectedAsset ? '1 selected' : '0 selected';
}

function confirmAssetSelection() {
    if (tempSelectedAsset) document.getElementById('series-cover').value = tempSelectedAsset;
    closeAssetPickerSheet();
}

// ── Core API helper ───────────────────────────────────────────────────────────
async function api(payload) {
    try {
        const body = new FormData();
        Object.entries(payload).forEach(([k, v]) => body.append(k, v));
        const r = await fetch('api.php', { method: 'POST', body });
        return await r.json();
    } catch (e) { return { success: false, error: e.message }; }
}

function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>


<?php echo $eruda ?? ''; ?>


</body>
</html>

