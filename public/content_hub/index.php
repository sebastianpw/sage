<?php
/**
 * SAGE Content Hub — Social Media Command Center
 * public/content_hub/index.php
 */

session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/ContentHub/ContentHubManager.php';

use App\ContentHub\ContentHubManager;

$hub = new ContentHubManager($pdo);
$statsRes = $hub->getDashboardStats();
$stats    = $statsRes['stats'] ?? [];
$upcoming = $hub->getUpcomingPosts(5);

// Include Modals at the top level so they are available in the DOM
require_once __DIR__ . '/../modal_frame_details.php';
require_once __DIR__ . '/../modal_video_details.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Content Hub · SAGE AI</title>
<script>
(function(){
    try {
        var t = localStorage.getItem('spw_theme');
        if (t) document.documentElement.setAttribute('data-theme', t);
    } catch(e){}
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
    --blue:   #3b82f6;
    --purple: #a855f7;
    --red:    #ef4444;
    --teal:   #14b8a6;

    --text-bright: #f0f0f4;
    --text-body:   #b0b0be;
    --text-muted:  #6b6b80;
    --text-dim:    #3a3a4e;

    --font-mono:    'Space Mono', monospace;
    --font-display: 'Syne', sans-serif;
    --font-body:    'DM Sans', sans-serif;

    --radius-sm: 4px;
    --radius:    8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
}

[data-theme="light"] {
    --bg-void:    #f4f4f6;
    --bg-base:    #ffffff;
    --bg-raised:  #f9f9fb;
    --bg-float:   #ffffff;
    --bg-hover:   #f0f0f5;
    --border:     rgba(0,0,0,0.07);
    --border-mid: rgba(0,0,0,0.12);
    --text-bright: #0d0d14;
    --text-body:   #3a3a50;
    --text-muted:  #7878a0;
    --text-dim:    #c0c0d0;
    --amber-glow:  rgba(245,158,11,0.08);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body { font-family: var(--font-body); background: var(--bg-void); color: var(--text-body); min-height: 100vh; line-height: 1.6; overflow-x: hidden; }

.hub-layout { display: grid; grid-template-columns: 220px 1fr; grid-template-rows: 52px 1fr; min-height: 100vh; }
.topbar { grid-column: 1 / -1; background: var(--bg-base); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 20px; gap: 16px; position: sticky; top: 0; z-index: 100; }
.topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
.brand-icon { width: 28px; height: 28px; background: linear-gradient(135deg, var(--amber), #f97316); border-radius: 6px; display: flex; align-items: center; justify-content: center; }
.brand-icon svg { width: 16px; height: 16px; fill: #000; }
.brand-name { font-family: var(--font-display); font-weight: 800; font-size: 14px; color: var(--text-bright); letter-spacing: 0.04em; }
.brand-sub { font-family: var(--font-mono); font-size: 10px; color: var(--amber); letter-spacing: 0.12em; }
.topbar-divider { width: 1px; height: 24px; background: var(--border); }
.topbar-center { flex: 1; display: flex; align-items: center; gap: 8px; }
.topbar-search { position: relative; max-width: 320px; width: 100%; }
.topbar-search input { width: 100%; background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius); padding: 6px 12px 6px 34px; font-family: var(--font-body); font-size: 13px; color: var(--text-bright); outline: none; transition: border-color 0.15s; }
.topbar-search input:focus { border-color: var(--amber); }
.topbar-search input::placeholder { color: var(--text-muted); }
.topbar-search .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; stroke: var(--text-muted); fill: none; }
.topbar-right { display: flex; align-items: center; gap: 8px; margin-left: auto; }

.btn-icon { width: 32px; height: 32px; border-radius: var(--radius); background: transparent; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: all 0.15s; }
.btn-icon:hover { background: var(--bg-hover); color: var(--text-bright); border-color: var(--border-mid); }
.btn-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.5; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: var(--amber); color: #000; font-family: var(--font-mono); font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; border: none; border-radius: var(--radius); cursor: pointer; text-decoration: none; transition: all 0.15s; white-space: nowrap; }
.btn-primary:hover { background: #fbbf24; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(245,158,11,0.3); }
.btn-primary svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; background: var(--bg-raised); color: var(--text-body); font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.04em; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; text-decoration: none; transition: all 0.15s; }
.btn-secondary:hover { border-color: var(--border-mid); color: var(--text-bright); background: var(--bg-hover); }

.sidebar { background: var(--bg-base); border-right: 1px solid var(--border); padding: 16px 0; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
.sidebar-section { padding: 12px 16px 4px; }
.sidebar-label { font-family: var(--font-mono); font-size: 9px; letter-spacing: 0.15em; text-transform: uppercase; color: var(--text-dim); font-weight: 700; }
.nav-item { display: flex; align-items: center; gap: 9px; padding: 8px 16px; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 400; border-radius: 0; cursor: pointer; transition: all 0.12s; border: none; background: none; width: 100%; text-align: left; position: relative; }
.nav-item svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.5; flex-shrink: 0; }
.nav-item:hover { background: var(--bg-hover); color: var(--text-bright); }
.nav-item.active { color: var(--amber); background: var(--amber-glow); }
.nav-item.active::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 2px; background: var(--amber); border-radius: 0 2px 2px 0; }
.nav-badge { margin-left: auto; font-family: var(--font-mono); font-size: 9px; padding: 2px 6px; background: var(--bg-float); border: 1px solid var(--border); border-radius: 20px; color: var(--text-muted); }
.nav-item.active .nav-badge { background: var(--amber-dim); border-color: var(--amber); color: var(--amber); }

.platform-pills { display: flex; flex-direction: column; gap: 3px; margin-top: 8px; }
.platform-pill { display: flex; align-items: center; gap: 8px; padding: 5px 8px; border-radius: var(--radius-sm); font-size: 12px; color: var(--text-muted); cursor: pointer; transition: all 0.12px; border: 1px solid transparent; }
.platform-pill:hover { background: var(--bg-hover); }
.platform-pill.sel { background: var(--bg-hover); border-color: var(--border); color: var(--text-bright); }
.platform-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.main-content { overflow-y: auto; padding: 0 20px 20px 20px; display: flex; flex-direction: column; gap: 20px; min-height: 0; }
.view { display: none; flex-direction: column; gap: 20px; }
.view.active { display: flex; }
.page-hdr { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.page-title { font-family: var(--font-display); font-size: 22px; font-weight: 800; color: var(--text-bright); line-height: 1.1; }
.page-sub { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.stat-card { background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px; position: relative; overflow: hidden; transition: border-color 0.15s, transform 0.15s; }
.stat-card:hover { border-color: var(--border-mid); transform: translateY(-2px); }
.stat-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--stat-color, var(--amber)); opacity: 0.6; }
.stat-label { font-family: var(--font-mono); font-size: 9px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
.stat-value { font-family: var(--font-display); font-size: 28px; font-weight: 800; color: var(--text-bright); line-height: 1; margin-bottom: 4px; }
.stat-delta { font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); }
.stat-icon { position: absolute; top: 14px; right: 14px; width: 32px; height: 32px; border-radius: var(--radius); background: var(--bg-float); display: flex; align-items: center; justify-content: center; }
.stat-icon svg { width: 16px; height: 16px; stroke: var(--stat-color, var(--amber)); fill: none; stroke-width: 1.5; }

.two-col { display: grid; grid-template-columns: 1fr 340px; gap: 16px; }
.card { background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.card-hdr { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border); }
.card-title { font-family: var(--font-display); font-size: 13px; font-weight: 700; color: var(--text-bright); display: flex; align-items: center; gap: 8px; }
.card-title svg { width: 14px; height: 14px; stroke: var(--amber); fill: none; stroke-width: 2; }
.card-body { padding: 16px; }

.calendar-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.cal-month { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--text-bright); }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.cal-dayname { font-family: var(--font-mono); font-size: 9px; letter-spacing: 0.1em; text-align: center; padding: 6px 0; color: var(--text-muted); text-transform: uppercase; }
.cal-day { aspect-ratio: 1; border-radius: var(--radius-sm); display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding: 4px 2px 2px; cursor: pointer; transition: background 0.1s; position: relative; min-height: 36px; border: 1px solid transparent; }
.cal-day:hover { background: var(--bg-hover); }
.cal-day.today { border-color: var(--amber); background: var(--amber-glow); }
.cal-day.today .cal-daynum { color: var(--amber); font-weight: 700; }
.cal-day.other-month .cal-daynum { color: var(--text-dim); }
.cal-daynum { font-family: var(--font-mono); font-size: 10px; color: var(--text-body); line-height: 1; }
.cal-dots { display: flex; flex-wrap: wrap; gap: 2px; justify-content: center; margin-top: 2px; }
.cal-dot { width: 4px; height: 4px; border-radius: 50%; flex-shrink: 0; }

.upcoming-list { display: flex; flex-direction: column; gap: 8px; }
.upcoming-item { display: flex; gap: 10px; align-items: flex-start; padding: 10px; background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: all 0.12s; }
.upcoming-item:hover { border-color: var(--border-mid); transform: translateX(2px); }
.upcoming-thumb { width: 44px; height: 44px; border-radius: var(--radius-sm); object-fit: cover; background: var(--bg-hover); flex-shrink: 0; border: 1px solid var(--border); }
.upcoming-info { flex: 1; min-width: 0; }
.upcoming-title { font-size: 12px; font-weight: 500; color: var(--text-bright); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.upcoming-meta { font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-family: var(--font-mono); font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700; border: 1px solid; }
.badge-draft     { color: var(--text-muted); border-color: var(--border); background: var(--bg-float); }
.badge-scheduled { color: var(--blue);   border-color: rgba(59,130,246,0.25); background: rgba(59,130,246,0.08); }
.badge-published { color: var(--green);  border-color: rgba(34,197,94,0.25);  background: rgba(34,197,94,0.08); }
.badge-archived  { color: var(--text-dim); border-color: var(--border); background: var(--bg-float); }
.badge-instagram { color: #e1306c; border-color: rgba(225,48,108,0.25); background: rgba(225,48,108,0.08); }
.badge-tiktok    { color: #69c9d0; border-color: rgba(105,201,208,0.25); background: rgba(105,201,208,0.08); }
.badge-youtube   { color: #ff0000; border-color: rgba(255,0,0,0.2);      background: rgba(255,0,0,0.06); }
.badge-twitter   { color: #1d9bf0; border-color: rgba(29,155,240,0.25);  background: rgba(29,155,240,0.08); }
.badge-facebook  { color: #1877f2; border-color: rgba(24,119,242,0.25);  background: rgba(24,119,242,0.08); }
.badge-website   { color: var(--teal); border-color: rgba(20,184,166,0.25); background: rgba(20,184,166,0.08); }

.post-list { display: flex; flex-direction: column; gap: 1px; }
.post-row { display: grid; grid-template-columns: 48px 1fr auto auto; align-items: center; gap: 12px; padding: 10px 16px; background: var(--bg-raised); border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.1s; }
.post-row:hover { background: var(--bg-hover); }
.post-row:first-child { border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
.post-row:last-child { border-bottom: none; border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
.post-thumb-sm { width: 48px; height: 48px; border-radius: var(--radius-sm); object-fit: cover; background: var(--bg-float); border: 1px solid var(--border); flex-shrink: 0; }
.post-info-col { min-width: 0; }
.post-name { font-size: 13px; font-weight: 500; color: var(--text-bright); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
.post-meta-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.post-date-inline { font-family: var(--font-mono); font-size: 9px; color: var(--text-dim); margin-top: 2px; }
.post-actions-col { display: flex; gap: 4px; opacity: 1; }

.filter-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.filter-select { background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius); padding: 6px 10px; font-family: var(--font-mono); font-size: 11px; color: var(--text-body); cursor: pointer; outline: none; transition: border-color 0.15s; }
.filter-select:focus { border-color: var(--amber); }
.filter-search { flex: 1; min-width: 180px; background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius); padding: 6px 12px; font-family: var(--font-body); font-size: 13px; color: var(--text-bright); outline: none; transition: border-color 0.15s; }
.filter-search:focus { border-color: var(--amber); }
.filter-search::placeholder { color: var(--text-muted); }
.distrib-bar { display: flex; gap: 0; height: 8px; border-radius: 20px; overflow: hidden; margin-top: 8px; }
.distrib-segment { transition: flex 0.4s ease; }
.distrib-legend { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-muted); }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(4px); z-index: 200; display: flex; align-items: flex-start; justify-content: center; padding: 20px; overflow-y: auto; opacity: 0; pointer-events: none; transition: opacity 0.2s; }
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal { background: var(--bg-raised); border: 1px solid var(--border-mid); border-radius: var(--radius-xl); width: 100%; max-width: 680px; margin: auto; transform: translateY(20px); transition: transform 0.25s; }
.modal-overlay.open .modal { transform: translateY(0); }
.modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid var(--border); }
.modal-title { font-family: var(--font-display); font-size: 16px; font-weight: 700; color: var(--text-bright); }
.modal-body { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.modal-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }

.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); }
.form-control { background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--radius); padding: 8px 12px; font-family: var(--font-body); font-size: 13px; color: var(--text-bright); width: 100%; outline: none; transition: border-color 0.15s; resize: vertical; }
.form-control:focus { border-color: var(--amber); }
.form-control::placeholder { color: var(--text-muted); }
select.form-control { cursor: pointer; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Previews (Strictly Top Right) */
.preview-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transition: opacity 0.15s, background 0.15s; z-index: 10; }
.asset-item:hover .preview-btn, .f-card:hover .preview-btn, .list-item:hover .preview-btn, .attached-asset-item:hover .preview-btn { opacity: 1; }
@media (hover: none) { .preview-btn { opacity: 1; } }
.preview-btn:hover { background: var(--amber); border-color: var(--amber); color: #000; }
.preview-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }

/* Selection Checkmark (Strictly Top Left) */
.asset-check { position: absolute; top: 5px; left: 5px; width: 16px; height: 16px; background: var(--amber); border-radius: 50%; display: none; align-items: center; justify-content: center; z-index: 5;}
.asset-item.selected .asset-check, .f-card.selected .asset-check, .list-item.selected .asset-check { display: flex; }
.asset-check svg { width: 10px; height: 10px; stroke: #000; fill: none; stroke-width: 2.5; }

/* Bottom Sheet Picker Grids */
.sb-picker-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 300000; display: none; align-items: flex-end; justify-content: center; }
.sb-picker-backdrop.active { display: flex; }
.sb-picker-sheet { width: 100%; max-width: 800px; background: var(--bg-raised); border: 1px solid var(--border); border-bottom: none; border-radius: 14px 14px 0 0; padding: 0 0 max(16px, env(safe-area-inset-bottom)); font-family: var(--font-body); max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 -8px 40px rgba(0,0,0,0.6); animation: slideUp 0.22s ease; }
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.sb-picker-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.sb-picker-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.sb-picker-header { padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.sb-picker-title { font-size: 14px; font-weight: 700; color: var(--text-bright); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
.sb-picker-close { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.sb-picker-close:hover { color: var(--text-bright); border-color: var(--text-bright); }
.sb-picker-filters { padding: 10px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; flex-direction: column; gap: 8px; }

.picker-grid-frames { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; padding: 10px; overflow-y: auto; flex: 1;}
.picker-grid-frames .f-card { min-height: 120px; aspect-ratio: 1; border: 2px solid var(--border); border-radius: 4px; overflow: hidden; position: relative; cursor: pointer; transition: 0.15s; background: var(--bg-void); }
.picker-grid-frames .f-card:hover { border-color: var(--amber); }
.picker-grid-frames .f-card.selected { border-color: var(--amber); box-shadow: 0 0 0 1px var(--amber); }
.picker-grid-frames .f-card img { width: 100%; height: 100%; object-fit: cover; }
.picker-grid-frames .f-label { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); font-size: 10px; padding: 2px 4px; text-align: center; color: #fff; font-family: var(--font-mono); pointer-events: none;}

.picker-grid-list { display: flex; flex-direction: column; gap: 4px; padding: 10px; overflow-y: auto; flex: 1;}
.picker-grid-list .list-item { display: flex; align-items: center; gap: 10px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; cursor: pointer; transition: 0.15s; background: var(--bg-void); position: relative;}
.picker-grid-list .list-item:hover { background: var(--bg-hover); }
.picker-grid-list .list-item.selected { border-color: var(--amber); background: var(--amber-glow); }
.picker-grid-list .list-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 3px; }
.picker-grid-list .list-item-title { font-size: 13px; font-weight: 500; color: var(--text-bright); }

/* Asset Picker Tabs */
.asset-picker-tabs { display: flex; gap: 4px; background: var(--bg-float); padding: 3px; border-radius: var(--radius); margin-bottom: 0px; }
.asset-tab { flex: 1; padding: 5px 8px; background: transparent; border: none; border-radius: var(--radius-sm); font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); cursor: pointer; transition: all 0.15s; letter-spacing: 0.06em; }
.asset-tab.active { background: var(--bg-raised); color: var(--amber); }

/* Container List (For map runs and storyboards) */
.container-list { display: flex; flex-direction: column; gap: 4px; overflow-y: auto; max-height: 140px; margin-top: 8px; border-top: 1px solid var(--border); padding-top: 8px; }
.container-item { padding: 6px 10px; border-radius: 4px; border: 1px solid transparent; background: var(--bg-float); cursor: pointer; transition: 0.15s; }
.container-item:hover { background: var(--bg-hover); border-color: var(--border); }
.container-item.active { background: var(--amber-glow); border-color: var(--amber); }
.container-item-name { font-size: 12px; font-weight: 600; color: var(--text-bright); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.container-item-meta { font-size: 10px; color: var(--text-muted); display: flex; justify-content: space-between; margin-top: 2px; }

.platform-checks { display: flex; flex-wrap: wrap; gap: 6px; }
.platform-check { display: flex; align-items: center; gap: 5px; padding: 5px 10px; background: var(--bg-float); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-size: 12px; color: var(--text-muted); transition: all 0.12s; user-select: none; }
.platform-check input { display: none; }
.platform-check:has(input:checked) { border-color: var(--amber); color: var(--text-bright); background: var(--amber-glow); }

.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--amber); border-radius: 50%; animation: spin 0.6s linear infinite; margin: 20px auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
.empty-state svg { width: 40px; height: 40px; stroke: var(--text-dim); fill: none; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
.empty-state p { font-size: 13px; margin-top: 4px; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-mid); border-radius: 2px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

.mobile-tab-bar { display: none; position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-base); border-top: 1px solid var(--border); z-index: 100; padding-bottom: env(safe-area-inset-bottom); }
.mobile-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 8px 0; color: var(--text-muted); text-decoration: none; font-size: 10px; font-family: var(--font-mono); transition: 0.15s; }
.mobile-tab.active { color: var(--amber); }
.mobile-tab svg { width: 20px; height: 20px; margin-bottom: 4px; stroke: currentColor; fill: none; stroke-width: 1.5; }

@media (max-width: 960px) {
    .hub-layout { grid-template-columns: 1fr; padding-bottom: 60px; }
    .sidebar { display: none; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .two-col { grid-template-columns: 1fr; }
    .mobile-tab-bar { display: flex; }
}
@media (max-width: 600px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .form-row { grid-template-columns: 1fr; }
}

#toast-container { position: fixed; bottom: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
@media (min-width: 960px) { #toast-container { bottom: 20px; } }
.toast { background: var(--bg-float); border: 1px solid var(--border-mid); border-radius: var(--radius); padding: 10px 16px; font-size: 12px; color: var(--text-bright); display: flex; align-items: center; gap: 8px; animation: slideIn 0.2s ease; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
.toast.success { border-left: 3px solid var(--green); }
.toast.error   { border-left: 3px solid var(--red); }
.toast.info    { border-left: 3px solid var(--blue); }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.post-row:focus-visible, .nav-item:focus-visible, .btn-primary:focus-visible, .mobile-tab:focus-visible { outline: 2px solid var(--amber); outline-offset: 2px; }
</style>
</head>
<body>

<div class="hub-layout">

<!-- ═══ TOPBAR ═══ -->
<header class="topbar">
    <a class="topbar-brand" href="index.php">
        <div class="brand-icon">
            <svg viewBox="0 0 16 16"><path d="M8 1l1.8 3.6 4 .6-2.9 2.8.7 4L8 10l-3.6 1.9.7-4L2.2 5.2l4-.6z"/></svg>
        </div>
        <div>
            <div class="brand-name">The Hub</div>
            <div class="brand-sub">Starlight Guardians</div>
        </div>
    </a>
    <div class="topbar-divider"></div>
    <div class="topbar-center">
        <div class="topbar-search">
            <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="global-search" placeholder="Search…" autocomplete="off">
        </div>
    </div>
    <div class="topbar-right">
        <button class="btn-icon" title="Refresh" onclick="refreshCurrent()">
            <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </button>
    </div>
</header>

<!-- ═══ SIDEBAR (Desktop) ═══ -->
<nav class="sidebar">
    <div class="sidebar-section"><div class="sidebar-label">Navigation</div></div>
    <button class="nav-item active" data-view="dashboard" onclick="switchView('dashboard', this)">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
    </button>
    <button class="nav-item" data-view="posts" onclick="switchView('posts', this)">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        All Posts <span class="nav-badge" id="total-count">—</span>
    </button>

    <div class="sidebar-section" style="margin-top:8px;"><div class="sidebar-label">Platforms</div></div>
    <div class="platform-pills" style="padding: 0 8px;">
        <?php
        $platforms = [
            ['id'=>'instagram','label'=>'Instagram','color'=>'#e1306c'],
            ['id'=>'tiktok',   'label'=>'TikTok',   'color'=>'#69c9d0'],
            ['id'=>'youtube',  'label'=>'YouTube',  'color'=>'#ff0000'],
            ['id'=>'twitter',  'label'=>'Twitter/X','color'=>'#1d9bf0'],
            ['id'=>'facebook', 'label'=>'Facebook', 'color'=>'#1877f2'],
            ['id'=>'website',  'label'=>'Website',  'color'=>'#14b8a6'],
        ];
        foreach ($platforms as $p): ?>
        <div class="platform-pill" data-platform="<?= $p['id'] ?>" onclick="filterByPlatform('<?= $p['id'] ?>')">
            <div class="platform-dot" style="background:<?= $p['color'] ?>"></div>
            <?= $p['label'] ?>
        </div>
        <?php endforeach; ?>
    </div>
</nav>

<!-- ═══ MOBILE BOTTOM NAV ═══ -->
<div class="mobile-tab-bar">
    <a href="javascript:void(0)" class="mobile-tab active" data-view="dashboard" onclick="switchView('dashboard', this)">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dash
    </a>
    <a href="javascript:void(0)" class="mobile-tab" data-view="posts" onclick="switchView('posts', this)">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Posts
    </a>
</div>

<!-- ═══ MAIN CONTENT ═══ -->
<main class="main-content">

    <!-- ── DASHBOARD VIEW ────────────────────────────────── -->
    <div class="view active" id="view-dashboard">
        <div class="two-col">
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div class="card">
                    <div class="card-hdr">
                        <div class="card-title">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Content Calendar
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="calendar-nav">
                            <button class="btn-icon" onclick="shiftMonth(-1)"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
                            <div class="cal-month" id="cal-month-label">—</div>
                            <button class="btn-icon" onclick="shiftMonth(1)"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
                        </div>
                        <div class="cal-grid" id="cal-grid"><div class="spinner"></div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-hdr">
                    <div class="card-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Upcoming Posts</div>
                    <span class="badge badge-scheduled" id="upcoming-count">—</span>
                </div>
                <div class="card-body">
                    <div class="upcoming-list" id="upcoming-list"><div class="spinner"></div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-hdr">
                    <div class="card-title">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        Platform Distribution
                    </div>
                </div>
                <div class="card-body">
                    <div class="distrib-bar" id="distrib-bar"><div class="distrib-segment" style="flex:1;background:var(--bg-float)"></div></div>
                    <div class="distrib-legend" id="distrib-legend"><div class="spinner"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── CALENDAR VIEW ──────────────────────────────────── -->
    <div class="view" id="view-calendar">
        <div class="page-hdr">
            <div>
                <div class="page-title">Content Calendar</div>
                <div class="page-sub">Full month view — click any day to view or schedule posts</div>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="btn-secondary" onclick="shiftMonth(-1)"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg> Prev</button>
                <button class="btn-secondary" onclick="goToToday()">Today</button>
                <button class="btn-secondary" onclick="shiftMonth(1)">Next <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
                <button class="btn-primary" onclick="openPostModal()"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Schedule Post</button>
            </div>
        </div>
        <div class="card">
            <div class="card-hdr"><div class="card-title" id="full-cal-title">—</div></div>
            <div class="card-body" style="padding:12px;">
                <div class="cal-grid" id="full-cal-daynames" style="margin-bottom:4px;">
                    <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?><div class="cal-dayname"><?= $d ?></div><?php endforeach; ?>
                </div>
                <div id="full-cal-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;"></div>
            </div>
        </div>
    </div>





<!-- ── ALL POSTS VIEW ─────────────────────────────────── -->
    <div class="view" id="view-posts">
        <div class="page-hdr" style="display:none;">
            <div>
                <div class="page-title">All Posts</div>
                <div class="page-sub">Manage and organize all your content</div>
            </div>
        </div>
        
        <div class="filter-bar">
            <select style="max-width:110px;" class="filter-select" id="post-platform" onchange="loadPosts()">
                <option value="">Platforms [all]</option>
                <option value="instagram">Instagram</option>
                <option value="tiktok">TikTok</option>
                <option value="youtube">YouTube</option>
                <option value="twitter">Twitter/X</option>
                <option value="facebook">Facebook</option>
                <option value="website">Website</option>
            </select>
            <select style="max-width:110px;" class="filter-select" id="post-status" onchange="loadPosts()">
                <option value="">Status [all]</option>
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
            </select>
            
            <div style="margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="api.php?action=preview_grid" target="_blank" class="btn-secondary">Preview Grid</a>
                <button class="btn-secondary" onclick="doExport('export_grid')">Download Grid HTML</button>
                <button class="btn-secondary" onclick="doExport('export_all_html')">Download All HTML</button>
                
                <!-- NEW: HIGHLIGHT EPISODE BUTTON -->
                <button class="btn-primary" onclick="openHighlightModal()" style="background:var(--purple); color:#fff; border:none; box-shadow:0 0 10px rgba(168, 85, 247, 0.4);">
                    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Add Highlight
                </button>

                <button class="btn-primary" onclick="openPostModal()"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> New Post</button>
            </div>
        </div>
        
        <div id="post-list-container"><div class="spinner"></div></div>
    </div>

</main>
</div>

<!-- ═══ HIGHLIGHT EPISODE MODAL ═══ -->
<div class="modal-overlay" id="highlight-modal-overlay" onclick="closeHighlightModalOnOverlay(event)">
<div class="modal" id="highlight-modal">
    <div class="modal-hdr">
        <div class="modal-title">Select Episode to Highlight</div>
        <button class="btn-icon" onclick="closeHighlightModal()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body" style="max-height: 60vh; overflow-y: auto; padding: 10px;">
        <div id="highlight-episodes-list"><div class="spinner"></div></div>
    </div>
</div>
</div>

<!-- ═══ POST MODAL ═══ -->
<div class="modal-overlay" id="post-modal-overlay" onclick="closeModalOnOverlay(event)">
<div class="modal" id="post-modal">
    <div class="modal-hdr">
        <div class="modal-title" id="modal-title">Create New Post</div>
        <button class="btn-icon" onclick="closePostModal()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="post-id" value="">
        <div class="form-row">
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" id="post-title" placeholder="Post title…">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" id="post-status-select">
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Post Type</label>
                
  <select class="form-control" id="post-type-select">
      <option value="image_grid">Image Grid</option>
      <option value="image_swiper">Image Swiper</option>
      <option value="video_playlist">Video Playlist</option>
      <option value="youtube_playlist">YouTube Playlist</option>
      <option value="url_reference">URL Reference</option>
      <option value="scrollmagic_gallery">ScrollMagic Gallery</option>
      <option value="cinematic_story">Cinematic Story</option>
      <option value="anime_gallery">Anime Gallery</option>
      <option value="narrative_gallery">Narrative Gallery</option>
      <option value="spatial_viewer">Spatial Viewer</option>
      <option value="magazine_highlight">Magazine Highlight</option>
  </select>
  
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Scheduled Date</label>
                <input type="datetime-local" class="form-control" id="post-scheduled">
            </div>
            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input type="number" class="form-control" id="post-sort" value="0">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Platforms</label>
            <div class="platform-checks">
                <?php foreach ($platforms as $p): ?>
                <label class="platform-check">
                    <input type="checkbox" name="platforms[]" value="<?= $p['id'] ?>">
                    <div class="platform-dot" style="background:<?= $p['color'] ?>"></div>
                    <?= $p['label'] ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Preview Image URL</label>
            <div style="display:flex; gap:8px;">
                <input type="text" class="form-control" id="post-preview" placeholder="/img/preview.jpg or full URL">
                <button type="button" class="btn-secondary" onclick="openAssetPickerSheet('preview')">Select</button>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Asset URL Prefix</label>
            <input type="text" class="form-control" id="post-asset-prefix" placeholder="e.g. https://cdn.example.com/assets/">
            <small style="color:var(--text-muted);font-size:11px;">Prepended to asset paths in Complete HTML export.</small>
        </div>
        <div class="form-group">
            <label class="form-label">Caption / Content</label>
            <textarea class="form-control" id="post-content" rows="4" placeholder="Write your caption or post content…"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Attached Assets</label>
            <div id="attached-assets-list" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;"></div>
            <button type="button" class="btn-secondary" style="align-self: flex-start;" onclick="openAssetPickerSheet('media')">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add Assets
            </button>
        </div>

        <div class="form-group">
            <label class="form-label">Hashtags</label>
            <input type="text" class="form-control" id="post-hashtags" placeholder="#StarlightGuardians #anime …">
        </div>

        <textarea id="post-media" style="display: none;"></textarea>

    </div>
    <div class="modal-footer">
        <button class="btn-secondary" onclick="closePostModal()">Cancel</button>
        <button class="btn-primary" onclick="savePost()">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Save Post
        </button>
    </div>
</div>
</div>

<!-- ═══ ASSET PICKER BOTTOM SHEET ═══ -->
<div class="sb-picker-backdrop" id="assetPickerBackdrop" onmousedown="onAssetPickerBackdropClick(event)">
    <div class="sb-picker-sheet" id="assetPickerSheet">
        <div class="sb-picker-handle" onclick="closeAssetPickerSheet()"><div class="sb-picker-handle-bar"></div></div>
        <div class="sb-picker-header">
            <div class="sb-picker-title"><svg style="width:16px;height:16px;stroke:currentColor;fill:none;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Select Assets</div>
            <button class="sb-picker-close" onclick="closeAssetPickerSheet()">X</button>
        </div>
        <div class="sb-picker-filters">
             <div class="asset-picker-tabs">
                <button class="asset-tab active" onclick="switchPickerAssetTab('map_runs',this)">Map Runs</button>
                <button class="asset-tab" onclick="switchPickerAssetTab('storyboards',this)">Storyboards</button>
                <button class="asset-tab" onclick="switchPickerAssetTab('videos',this)">Videos</button>
            </div>
            
            <div id="picker-controls-container" class="picker-controls" style="display:block;">
                <div style="display:flex; gap:6px; margin-bottom:8px;">
                    <input type="text" id="picker-container-search" class="form-control" placeholder="Search..." oninput="debounceContainerSearch()" style="flex:1; padding:4px 8px;">
                    <div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">
                        <button class="btn-icon" style="width:28px;height:28px;" onclick="changeContainerPage(-1)">&#8592;</button>
                        <input type="number" id="picker-container-page" value="1" style="width:36px;text-align:center;background:var(--bg-raised);border:1px solid var(--border);color:var(--amber);border-radius:4px;font-size:12px;height:28px;" onchange="containerCurPage=parseInt(this.value)||1; loadContainers(currentPickerTab)">
                        <span style="font-size:10px;color:var(--text-muted);" id="picker-container-total">/ 1</span>
                        <button class="btn-icon" style="width:28px;height:28px;" onclick="changeContainerPage(1)">&#8594;</button>
                    </div>
                </div>
                <div id="picker-container-list" class="container-list"></div>
            </div>
            <div id="picker-controls-search" class="picker-controls" style="display: none;">
                <input type="text" id="picker-search-input" class="form-control" placeholder="Search..." oninput="debouncePickerSearch()">
            </div>
        </div>
        <div id="picker-asset-grid" class="picker-grid-frames">
            <div class="spinner" style="grid-column:1/-1;"></div>
        </div>
        <div style="padding: 12px 16px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <span id="picker-selection-count" style="font-size: 12px; color: var(--text-muted);">0 selected</span>
            <button class="btn-primary" onclick="confirmAssetSelection()">Add Selected</button>
        </div>
    </div>
</div>

<!-- ═══ DAY VIEW MODAL ═══ -->
<div class="modal-overlay" id="day-modal-overlay" onclick="closeDayModalOnOverlay(event)">
    <div class="modal" id="day-modal" style="max-width: 400px;">
        <div class="modal-hdr">
            <div class="modal-title" id="day-modal-title">Day View</div>
            <button class="btn-icon" onclick="closeDayModal()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" id="day-modal-body" style="padding: 0; max-height: 400px; overflow-y: auto;"></div>
        <div class="modal-footer">
            <button class="btn-primary" onclick="triggerScheduleFromDay()">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Schedule Post
            </button>
        </div>
    </div>
</div>

<!-- ═══ TOAST ═══ -->
<div id="toast-container"></div>

<script>
const API = 'api.php';

// State
let calYear  = <?= date('Y') ?>;
let calMonth = <?= date('n') ?>;
let calData  = {};
let currentView = 'dashboard';
let currentPickerTab = 'map_runs';
let postSearchTimer = null;
let pickerSearchTimer = null;

let tempSelectedAssets = []; 
let currentDayStr = null;
let currentExportPostId = null;

let containerCurPage = 1;
let containerTotalPages = 1;
let containerSearchTimer = null;
let selectedContainerId = null;

let pickerMode = 'media'; // 'media' or 'preview'

// hidden search state (driven by global topbar search only)
let _postSearchValue = '';

document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadCalendar();
    loadUpcoming();
    initAttachedAssetsSortable();
});

function switchView(view, btn) {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.querySelectorAll('.mobile-tab').forEach(n => n.classList.remove('active'));
    
    document.getElementById('view-' + view).classList.add('active');
    
    const sbBtn = document.querySelector(`.nav-item[data-view=${view}]`);
    if(sbBtn) sbBtn.classList.add('active');

    const mbBtn = document.querySelector(`.mobile-tab[data-view=${view}]`);
    if(mbBtn) mbBtn.classList.add('active');

    currentView = view;
    if (view === 'posts')   loadPosts();
    if (view === 'calendar') renderFullCalendar();
}

function refreshCurrent() {
    if (currentView === 'dashboard') { loadStats(); loadCalendar(); loadUpcoming(); }
    if (currentView === 'posts')     loadPosts();
    if (currentView === 'calendar')  loadCalendar();
    toast('Refreshed', 'info');
}

// Stats
async function loadStats() {
    const data = await api({ action: 'get_stats' });
    if (!data.success) { renderDistrib({}); return; }
    const s = data.stats;
    const eTotal     = el('total-count');
    const eScheduled = el('scheduled-count');
    if (eTotal)     eTotal.textContent     = s.total     ?? 0;
    if (eScheduled) eScheduled.textContent = s.scheduled ?? 0;
    renderDistrib(s.by_platform ?? {});
}

function renderDistrib(byPlatform) {
    const total = Object.values(byPlatform).reduce((a,b)=>a+b,0) || 1;
    const colors = { instagram:'#e1306c', tiktok:'#69c9d0', youtube:'#ff0000', twitter:'#1d9bf0', facebook:'#1877f2', website:'#14b8a6' };
    const bar = el('distrib-bar');
    bar.innerHTML = '';
    const legend = el('distrib-legend');
    legend.innerHTML = '';

    let hasData = false;
    Object.entries(byPlatform).forEach(([p, cnt]) => {
        if(cnt > 0) hasData = true;
        const pct = cnt / total;
        const color = colors[p] ?? 'var(--text-dim)';
        const seg = document.createElement('div');
        seg.className = 'distrib-segment';
        seg.style.flex = pct.toFixed(4);
        seg.style.background = color;
        bar.appendChild(seg);
        legend.innerHTML += `<div class="legend-item"><div class="legend-dot" style="background:${color}"></div>${capitalize(p)} <span style="color:var(--text-bright);margin-left:4px">${cnt}</span></div>`;
    });

    if (!hasData) {
        bar.innerHTML = '<div class="distrib-segment" style="flex:1;background:var(--bg-float)"></div>';
        legend.innerHTML = '<span style="color:var(--text-muted);font-size:12px">No posts yet</span>';
    }
}

// Calendar
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const PLATFORM_COLORS = { instagram:'#e1306c', tiktok:'#69c9d0', youtube:'#ff0000', twitter:'#1d9bf0', facebook:'#1877f2', website:'#14b8a6', draft:'var(--text-dim)', scheduled:'var(--blue)', published:'var(--green)' };

async function loadCalendar() {
    const data = await api({ action: 'get_calendar', year: calYear, month: calMonth });
    if (data.success) {
        calData = data.calendar ?? {};
        renderMiniCalendar();
        if (currentView === 'calendar') renderFullCalendar();
    }
}

function renderMiniCalendar() {
    el('cal-month-label').textContent = `${MONTHS[calMonth-1]} ${calYear}`;
    el('cal-grid').innerHTML = buildCalGridHTML(false);
}

function renderFullCalendar() {
    el('full-cal-title').textContent = `${MONTHS[calMonth-1]} ${calYear}`;
    el('full-cal-grid').innerHTML = buildCalGridHTML(true);
}

function buildCalGridHTML(full) {
    const dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    let html = '';
    if (!full) dayNames.forEach(d => { html += `<div class="cal-dayname">${d}</div>`; });
    const firstDay = new Date(calYear, calMonth - 1, 1);
    let startDow = firstDay.getDay(); 
    startDow = startDow === 0 ? 6 : startDow - 1;
    const daysInMonth = new Date(calYear, calMonth, 0).getDate();
    const prevDays    = new Date(calYear, calMonth - 1, 0).getDate();
    const today       = new Date();
    const todayStr    = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

    for (let i = startDow - 1; i >= 0; i--) html += buildDayCell(calYear, calMonth - 1 || 12, prevDays - i, true, todayStr, full);
    for (let d = 1; d <= daysInMonth; d++)  html += buildDayCell(calYear, calMonth, d, false, todayStr, full);
    const rem = (7 - ((startDow + daysInMonth) % 7)) % 7;
    for (let d = 1; d <= rem; d++)          html += buildDayCell(calYear, calMonth + 1 > 12 ? 1 : calMonth + 1, d, true, todayStr, full);

    return html;
}

function buildDayCell(y, m, d, otherMonth, todayStr, full) {
    const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const posts   = calData[dateStr] ?? [];
    const isToday = dateStr === todayStr;
    const cls     = ['cal-day', otherMonth ? 'other-month' : '', isToday ? 'today' : ''].filter(Boolean).join(' ');

    let dots = '';
    if (posts.length) {
        const shown = full ? posts.slice(0, 4) : posts.slice(0, 3);
        dots = shown.map(p => {
            const color = PLATFORM_COLORS[p.platform] ?? PLATFORM_COLORS[p.status] ?? 'var(--amber)';
            return `<div class="cal-dot" style="background:${color}" title="${escHtml(p.title)}"></div>`;
        }).join('');
        if (posts.length > (full ? 4 : 3)) dots += `<div style="font-size:8px;color:var(--text-muted)">+${posts.length - (full ? 4 : 3)}</div>`;
    }

    return `<div class="${cls}" onclick="onCalDayClick('${dateStr}')" title="${dateStr}">
        <div class="cal-daynum">${d}</div>
        <div class="cal-dots">${dots}</div>
    </div>`;
}

function onCalDayClick(dateStr) {
    currentDayStr = dateStr;
    const posts = calData[dateStr] || [];
    el('day-modal-title').textContent = formatDate(dateStr);
    
    const body = el('day-modal-body');
    if (posts.length === 0) {
        body.innerHTML = '<div class="empty-state" style="padding:20px;">No posts scheduled</div>';
    } else {
        body.innerHTML = '<div class="post-list">' + posts.map(p => {
            let platformBadges = '';
            let allPlatforms = [];
            try {
                const pj = p.platforms_json ? JSON.parse(p.platforms_json) : null;
                if (Array.isArray(pj) && pj.length) allPlatforms = pj;
            } catch(e) {}
            if (!allPlatforms.length && p.platform) allPlatforms = [p.platform];
            platformBadges = allPlatforms.map(pl => `<span class="badge badge-${pl}">${capitalize(pl)}</span>`).join('');
            return `
            <div class="post-row" style="grid-template-columns: 1fr auto auto; padding: 12px 16px;" onclick="closeDayModal(); openPostModal(${p.id})">
                <div class="post-name" style="margin:0;">${escHtml(p.title)}</div>
                <span class="badge badge-${p.status}">${capitalize(p.status)}</span>
                ${platformBadges}
            </div>
        `}).join('') + '</div>';
    }
    
    el('day-modal-overlay').classList.add('open');
}

function closeDayModal() { el('day-modal-overlay').classList.remove('open'); }
function closeDayModalOnOverlay(e) { if (e.target === el('day-modal-overlay')) closeDayModal(); }
function triggerScheduleFromDay() { closeDayModal(); openPostModal(null, currentDayStr); }

function shiftMonth(delta) {
    calMonth += delta;
    if (calMonth > 12) { calMonth = 1;  calYear++; }
    if (calMonth < 1)  { calMonth = 12; calYear--; }
    loadCalendar();
}
function goToToday() {
    const t = new Date();
    calYear  = t.getFullYear();
    calMonth = t.getMonth() + 1;
    loadCalendar();
}

// Upcoming
async function loadUpcoming() {
    const data = await api({ action: 'get_posts', status: 'scheduled', page: 1 });
    const list = el('upcoming-list');
    if (!data.success || !data.posts.length) {
        list.innerHTML = `<div class="empty-state"><p>No scheduled posts</p></div>`;
        el('upcoming-count').textContent = '0';
        return;
    }
    el('upcoming-count').textContent = data.total ?? data.posts.length;
    list.innerHTML = data.posts.slice(0,5).map(p => {
        let platformBadges = '';
        let allPlatforms = [];
        try {
            const pj = p.platforms_json ? JSON.parse(p.platforms_json) : null;
            if (Array.isArray(pj) && pj.length) allPlatforms = pj;
        } catch(e) {}
        if (!allPlatforms.length && p.platform) allPlatforms = [p.platform];
        platformBadges = allPlatforms.map(pl => `<span class="badge badge-${pl}">${capitalize(pl)}</span>`).join('');
        return `
        <div class="upcoming-item" onclick="openPostModal(${p.id})">
            ${p.preview_image_url
                ? `<img class="upcoming-thumb" src="${escHtml(p.preview_image_url)}" alt="" onerror="this.style.display='none'">`
                : `<div class="upcoming-thumb" style="display:flex;align-items:center;justify-content:center;"><svg style="width:18px;height:18px;stroke:var(--text-dim);fill:none;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>`}
            <div class="upcoming-info">
                <div class="upcoming-title">${escHtml(p.title)}</div>
                <div class="upcoming-meta">
                    ${p.scheduled_at ? `<span>${formatDate(p.scheduled_at)}</span>` : ''}
                    ${platformBadges}
                </div>
            </div>
            <div style="display:flex;gap:4px;">
                <button class="btn-icon" title="Rollout to GitHub" onclick="event.stopPropagation(); doRollout(${p.id}, '${escHtml(p.title)}')" style="flex-shrink:0;">
                    <svg viewBox="0 0 24 24"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                </button>
                <button class="btn-icon" title="Download HTML Only" onclick="event.stopPropagation(); doExportHtml(${p.id})" style="flex-shrink:0;">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
                </button>
                <button class="btn-icon" title="Export Post ZIP" onclick="event.stopPropagation(); doExportPost(${p.id})" style="flex-shrink:0;">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </button>
            </div>
        </div>`
    }).join('');
}

// Posts
async function loadPosts() {
    const container = el('post-list-container');
    container.innerHTML = '<div class="spinner"></div>';
    const data = await api({
        action:   'get_posts',
        search:   _postSearchValue,
        platform: el('post-platform')?.value ?? '',
        status:   el('post-status')?.value ?? '',
        page: 1
    });

    if (!data.success || !data.posts.length) {
        container.innerHTML = `<div class="empty-state"><p>No posts found</p></div>`;
        return;
    }

    container.innerHTML = `<div class="post-list">${
        data.posts.map(p => {
            let platformBadges = '';
            let allPlatforms = [];
            try {
                const pj = p.platforms_json ? JSON.parse(p.platforms_json) : null;
                if (Array.isArray(pj) && pj.length) allPlatforms = pj;
            } catch(e) {}
            if (!allPlatforms.length && p.platform) allPlatforms = [p.platform];
            platformBadges = allPlatforms.map(pl => `<span class="badge badge-${pl}">${capitalize(pl)}</span>`).join('');
            
            const isHighlight = p.post_type === 'magazine_highlight';

            return `
        <div class="post-row" onclick="openPostModal(${p.id})" tabindex="0">
            ${p.preview_image_url
                ? `<img class="post-thumb-sm" src="${escHtml(p.preview_image_url)}" alt="" onerror="this.style.display='none'">`
                : `<div class="post-thumb-sm" style="display:flex;align-items:center;justify-content:center;"><svg style="width:18px;height:18px;stroke:var(--text-dim);fill:none;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>`}
            <div class="post-info-col">
                <div class="post-name">${isHighlight ? '<span style="color:var(--amber);">[Highlight]</span> ' : ''}${escHtml(p.title)}</div>
                <div class="post-meta-row">
                    <span class="badge badge-${p.status ?? 'draft'}">${capitalize(p.status ?? 'draft')}</span>
                    ${platformBadges}
                    <span style="font-family:var(--font-mono);font-size:10px;color:var(--text-muted)">${escHtml(p.post_type?.replace(/_/g,' ') ?? '')}</span>
                </div>
                <div class="post-date-inline">${p.scheduled_at ? formatDate(p.scheduled_at) : formatDate(p.created_at)} &nbsp;#${p.sort_order ?? 0}</div>
            </div>
            <div class="post-actions-col" onclick="event.stopPropagation()">
                ${!isHighlight ? `
                <button class="btn-icon" title="Rollout to GitHub" onclick="event.stopPropagation(); doRollout(${p.id}, '${escHtml(p.title)}')">
                    <svg viewBox="0 0 24 24"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                </button>
                <button class="btn-icon" title="Download HTML Only" onclick="event.stopPropagation(); doExportHtml(${p.id})">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
                </button>
                <button class="btn-icon" title="Export Post ZIP" onclick="event.stopPropagation(); doExportPost(${p.id})">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </button>
                ` : ''}
                <button class="btn-icon" title="Edit" onclick="openPostModal(${p.id})"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                <button class="btn-icon" title="Delete" style="color:var(--red)" onclick="confirmDelete(${p.id},'${escHtml(p.title)}')"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
            </div>
        </div>`; }).join('')
    }</div>`;
}

function debounceLoadPosts() { clearTimeout(postSearchTimer); postSearchTimer = setTimeout(loadPosts, 300); }
function filterByPlatform(platform) { switchView('posts', document.querySelector('[data-view=posts]')); const sel = el('post-platform'); if (sel) sel.value = platform; loadPosts(); document.querySelectorAll('.platform-pill').forEach(p => p.classList.toggle('sel', p.dataset.platform === platform)); }

function showGenericImagePreview(url) {
    if (typeof showIframeModal === 'function') {
        showIframeModal(url, 'Image Preview');
    } else {
        window.open(url, '_blank');
    }
}

// Asset Picker Bottom Sheet Container Selection
function debounceContainerSearch() {
    clearTimeout(containerSearchTimer);
    containerSearchTimer = setTimeout(() => {
        containerCurPage = 1;
        loadContainers(currentPickerTab);
    }, 300);
}

function changeContainerPage(d) {
    const n = containerCurPage + d;
    if (n >= 1 && n <= containerTotalPages) {
        containerCurPage = n;
        loadContainers(currentPickerTab);
    }
}

async function loadContainers(type) {
    const list = el('picker-container-list');
    const search = el('picker-container-search').value.trim();
    
    list.innerHTML = '<div style="padding:10px;text-align:center;"><div class="spinner" style="margin:0 auto;width:14px;height:14px;border-width:2px;"></div></div>';
    
    const data = await api({ 
        action: 'search_containers', 
        container_type: type, 
        q: search, 
        page: containerCurPage 
    });

    if (!data.success) return;
    
    containerCurPage = data.page || containerCurPage;
    containerTotalPages = data.pages || 1;
    el('picker-container-page').value = containerCurPage;
    el('picker-container-total').textContent = `/ ${containerTotalPages}`;
    
    list.innerHTML = '';
    if (!data.items || !data.items.length) {
        list.innerHTML = '<div style="padding:10px;text-align:center;font-size:10px;color:var(--text-muted);">No items found</div>';
        el('picker-asset-grid').innerHTML = '';
        return;
    }

    data.items.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'container-item' + (item.id == selectedContainerId ? ' active' : '');
        itemEl.onclick = () => selectContainer(item.id, itemEl, type);
        itemEl.innerHTML = `
            <div class="container-item-name" title="${escHtml(item.name)}">${escHtml(item.name || 'Unnamed')}</div>
            <div class="container-item-meta"><span>${escHtml(item.meta || '')}</span><span>${item.frame_count} fr</span></div>
        `;
        list.appendChild(itemEl);
    });

    // Auto-select first if none selected
    if (!selectedContainerId && data.items.length > 0) {
        selectContainer(data.items[0].id, list.querySelector('.container-item'), type);
    }
}

function selectContainer(id, elItem, type) {
    document.querySelectorAll('.container-item').forEach(i => i.classList.remove('active'));
    if (elItem) elItem.classList.add('active');
    selectedContainerId = id;
    loadContainerAssets(type, id);
}

// Asset Picker Bottom Sheet
async function openAssetPickerSheet(mode = 'media') {
    pickerMode = mode;
    tempSelectedAssets = [];
    
    if (pickerMode === 'media') {
        try {
            const existing = JSON.parse(el('post-media').value);
            if (Array.isArray(existing)) tempSelectedAssets = existing;
        } catch(e) {}
        document.querySelector('.asset-tab[onclick*="videos"]').style.display = 'inline-block';
    } else {
        document.querySelector('.asset-tab[onclick*="videos"]').style.display = 'none';
        if (currentPickerTab === 'videos') {
            switchPickerAssetTab('map_runs', document.querySelector('.asset-tab[onclick*="map_runs"]'));
            return; // switchPickerAssetTab calls loadContainers
        }
    }
    
    el('assetPickerBackdrop').classList.add('active');
    
    // reset
    containerCurPage = 1;
    selectedContainerId = null;
    el('picker-container-search').value = '';

    el('picker-controls-container').style.display = 'block';
    el('picker-controls-search').style.display = 'none';
    loadContainers(currentPickerTab);
}

function closeAssetPickerSheet() { el('assetPickerBackdrop').classList.remove('active'); }
function onAssetPickerBackdropClick(e) { if (e.target === el('assetPickerBackdrop')) closeAssetPickerSheet(); }

function switchPickerAssetTab(type, btn) {
    document.querySelectorAll('#assetPickerSheet .asset-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    currentPickerTab = type;
    
    document.querySelectorAll('.picker-controls').forEach(c => c.style.display = 'none');
    el('picker-asset-grid').innerHTML = '';
    
    el('picker-controls-container').style.display = 'block';
    containerCurPage = 1;
    selectedContainerId = null;
    el('picker-container-search').value = '';
    loadContainers(type);
}

function debouncePickerSearch() {
    clearTimeout(pickerSearchTimer);
    pickerSearchTimer = setTimeout(() => loadPickerAssets(currentPickerTab, el('picker-search-input').value), 300);
}

async function loadContainerAssets(containerType, containerId) {
    const grid = el('picker-asset-grid');
    if (!containerId) {
        grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;">Please select an option above</div>';
        return;
    }
    
    grid.innerHTML = '<div class="spinner" style="grid-column:1/-1"></div>';

    if (containerType === 'videos') {
        const data = await api({ action: 'get_assets', type: 'videos', map_run_id: containerId });
        if (!data.success || !data.assets.length) {
            grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">No videos found</div>`;
            return;
        }
        grid.className = 'picker-grid-list';
        grid.innerHTML = data.assets.map(a => {
            const isSel = tempSelectedAssets.find(s => s.src === a.url || s.original_src === a.url);
            const previewBtn = `<button class="preview-btn" title="Preview" onclick="event.stopPropagation(); typeof showVideoDetailsModal === 'function' && ${a.id} ? showVideoDetailsModal(${a.id}) : window.open('${escHtml(a.url)}')"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>`;
            return `<div class="list-item ${isSel ? 'selected' : ''}" data-url="${escHtml(a.url)}" data-name="${escHtml(a.name)}" data-id="${a.id}" data-thumb="${escHtml(a.thumb || '')}" onclick="toggleAssetSelection(this)">
                <div class="asset-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                ${a.thumb ? `<img src="${escHtml(a.thumb)}" loading="lazy">` : `<div style="width:60px;height:60px;background:var(--bg-float);border-radius:3px;"></div>`}
                <div class="list-item-title">${escHtml(a.name)}</div>
                ${previewBtn}
            </div>`;
        }).join('');
    } else {
        const data = await api({ action: 'get_container_frames', container_type: containerType, container_id: containerId });
        if (!data.success || !data.assets.length) {
            grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">No frames found</div>`;
            return;
        }
        grid.className = 'picker-grid-frames';
        grid.innerHTML = data.assets.map(a => {
            const isSel = tempSelectedAssets.find(s => s.src === a.url || s.original_src === a.url);
            const previewBtn = `<button class="preview-btn" title="Preview" onclick="event.stopPropagation(); typeof showFrameDetailsModal === 'function' && ${a.id} ? showFrameDetailsModal(${a.id}) : showGenericImagePreview('${escHtml(a.url)}')"><svg viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></button>`;
            return `<div class="f-card ${isSel ? 'selected' : ''}" data-url="${escHtml(a.url)}" data-name="${escHtml(a.name)}" data-id="${a.id}" onclick="toggleAssetSelection(this)">
                <div class="asset-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                ${a.url ? `<img src="${escHtml(a.url)}" loading="lazy">` : ''}
                <div class="f-label">${escHtml(a.name) || '#'+a.id}</div>
                ${previewBtn}
            </div>`;
        }).join('');
    }
    updatePickerCount();
}

async function loadPickerAssets(type, query = '') {
    const grid = el('picker-asset-grid');
    grid.innerHTML = '<div class="spinner" style="grid-column:1/-1"></div>';
    const data = await api({ action: 'get_assets', type, q: query });
    
    if (!data.success || !data.assets.length) {
        grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">No ${type} found</div>`;
        return;
    }

    if (type === 'sketches') {
        grid.className = 'picker-grid-frames';
        grid.innerHTML = data.assets.map(a => {
            const isSel = tempSelectedAssets.find(s => s.src === a.url || s.original_src === a.url);
            const previewBtn = `<button class="preview-btn" title="Preview" onclick="event.stopPropagation(); typeof showFrameDetailsModal === 'function' && ${a.id} ? showFrameDetailsModal(${a.id}) : showGenericImagePreview('${escHtml(a.url)}')"><svg viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></button>`;
            
            return `<div class="f-card ${isSel ? 'selected' : ''}" data-url="${escHtml(a.url)}" data-name="${escHtml(a.name)}" data-id="${a.id}" onclick="toggleAssetSelection(this)">
                <div class="asset-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                ${a.url ? `<img src="${escHtml(a.url)}" loading="lazy">` : ''}
                <div class="f-label">${escHtml(a.name) || '#'+a.id}</div>
                ${previewBtn}
            </div>`;
        }).join('');
    } else {
        grid.className = 'picker-grid-list';
        grid.innerHTML = data.assets.map(a => {
            const isSel = tempSelectedAssets.find(s => s.src === a.url || s.original_src === a.url);
            const previewBtn = `<button class="preview-btn" title="Preview" onclick="event.stopPropagation(); typeof showVideoDetailsModal === 'function' && ${a.id} ? showVideoDetailsModal(${a.id}) : window.open('${escHtml(a.url)}')"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>`;
            
            return `<div class="list-item ${isSel ? 'selected' : ''}" data-url="${escHtml(a.url)}" data-name="${escHtml(a.name)}" data-id="${a.id}" data-thumb="${escHtml(a.thumb || '')}" onclick="toggleAssetSelection(this)">
                <div class="asset-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                ${a.thumb ? `<img src="${escHtml(a.thumb)}" loading="lazy">` : `<div style="width:60px;height:60px;background:var(--bg-float);border-radius:3px;"></div>`}
                <div class="list-item-title">${escHtml(a.name)}</div>
                ${previewBtn}
            </div>`;
        }).join('');
    }
    updatePickerCount();
}

function toggleAssetSelection(itemEl) {
    const url = itemEl.getAttribute('data-url');
    const name = itemEl.getAttribute('data-name');
    const id = itemEl.getAttribute('data-id');
    const thumb = itemEl.getAttribute('data-thumb') || '';
    if (!url) return;
    
    if (itemEl.classList.contains('selected')) {
        itemEl.classList.remove('selected');
        tempSelectedAssets = tempSelectedAssets.filter(s => s.src !== url && s.original_src !== url);
    } else {
        itemEl.classList.add('selected');
        if (pickerMode === 'preview') {
            document.querySelectorAll('.picker-grid-frames .selected, .picker-grid-list .selected').forEach(el => el.classList.remove('selected'));
            itemEl.classList.add('selected');
            tempSelectedAssets = [{ src: url, alt: name, id: id, thumb: thumb }];
        } else {
            tempSelectedAssets.push({ src: url, alt: name, id: id, thumb: thumb });
        }
    }
    updatePickerCount();
}

function updatePickerCount() {
    el('picker-selection-count').textContent = `${tempSelectedAssets.length} selected`;
}

function confirmAssetSelection() {
    if (pickerMode === 'preview') {
        if (tempSelectedAssets.length > 0) {
            el('post-preview').value = tempSelectedAssets[0].src;
        }
    } else {
        el('post-media').value = JSON.stringify(tempSelectedAssets, null, 2);
        renderAttachedAssets();
    }
    closeAssetPickerSheet();
}

function renderAttachedAssets() {
    let items = [];
    try { items = JSON.parse(el('post-media').value); } catch(e){}
    const container = el('attached-assets-list');
    
    if (!items || !items.length) {
        container.innerHTML = '<span style="font-size:11px;color:var(--text-muted);align-self:center;">No assets attached</span>';
        return;
    }

    container.innerHTML = items.map((itm, idx) => {
        const isVideo = (itm.src || '').match(/\.(mp4|webm|ogg)$/i) || (itm.src || '').includes('videos/');
        // For videos use thumb field; for images use src directly
        const displaySrc = isVideo ? (itm.thumb || itm.thumbnail || '') : (itm.src || '');
        const previewBtn = isVideo 
            ? `<button class="preview-btn" type="button" title="Preview" onclick="event.stopPropagation(); typeof showVideoDetailsModal === 'function' && ${itm.id||0} ? showVideoDetailsModal(${itm.id}) : window.open('${escHtml(itm.src)}')"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>`
            : `<button class="preview-btn" type="button" title="Preview" onclick="event.stopPropagation(); typeof showFrameDetailsModal === 'function' && ${itm.id||0} ? showFrameDetailsModal(${itm.id}) : showGenericImagePreview('${escHtml(itm.src)}')"><svg viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></button>`;
            
        return `<div class="attached-asset-item" data-idx="${idx}" draggable="true" style="position:relative; width:100px; height:100px; border-radius:4px; overflow:hidden; border:1px solid var(--border); flex-shrink:0; cursor:grab;">
            ${displaySrc
                ? `<img src="${escHtml(displaySrc)}" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">`
                : `<div style="width:100%;height:100%;background:var(--bg-float);display:flex;align-items:center;justify-content:center;"><svg style="width:20px;height:20px;stroke:var(--text-dim);fill:none;" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>`}
            <button type="button" onclick="event.stopPropagation(); removeAttachedAsset(${idx})" style="position:absolute;top:2px;left:2px;background:rgba(0,0,0,0.6);border:none;color:#fff;border-radius:50%;width:16px;height:16px;font-size:10px;cursor:pointer;line-height:1;z-index:20;">X</button>
            ${previewBtn}
            <button class="drag-handle-btn" type="button" title="Drag to reorder" style="position:absolute;bottom:2px;left:2px;background:rgba(0,0,0,0.6);border:1px solid rgba(255,255,255,0.2);color:#fff;border-radius:4px;width:24px;height:24px;cursor:grab;z-index:20;display:flex;align-items:center;justify-content:center;touch-action:none;">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
            </button>
        </div>`;
    }).join('');
}

function removeAttachedAsset(idx) {
    let items = [];
    try { items = JSON.parse(el('post-media').value); } catch(e){}
    if (items[idx]) {
        items.splice(idx, 1);
        el('post-media').value = JSON.stringify(items, null, 2);
        renderAttachedAssets();
    }
}

function initAttachedAssetsSortable() {
    const list = el('attached-assets-list');
    if (!list) return;

    let dragEl = null;

    // Desktop DnD
    list.addEventListener('dragstart', e => {
        const item = e.target.closest('.attached-asset-item');
        if (!item) return;
        dragEl = item;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragEl.dataset.idx);
        setTimeout(() => { if (dragEl) dragEl.style.opacity = '0.5'; }, 0);
    });
    list.addEventListener('dragend', e => {
        if (dragEl) dragEl.style.opacity = '1';
        dragEl = null;
        Array.from(list.children).forEach(i => i.style.border = '1px solid var(--border)');
    });
    list.addEventListener('dragover', e => {
        e.preventDefault(); 
        const target = e.target.closest('.attached-asset-item');
        if (target && target !== dragEl) {
            target.style.border = '2px dashed var(--amber)';
        }
    });
    list.addEventListener('dragleave', e => {
        const target = e.target.closest('.attached-asset-item');
        if (target && target !== dragEl) {
            target.style.border = '1px solid var(--border)';
        }
    });
    list.addEventListener('drop', e => {
        e.preventDefault();
        const target = e.target.closest('.attached-asset-item');
        if (target) target.style.border = '1px solid var(--border)';
        
        if (dragEl && target && dragEl !== target) {
            const srcIdx = parseInt(dragEl.dataset.idx);
            const dstIdx = parseInt(target.dataset.idx);
            let items = [];
            try { items = JSON.parse(el('post-media').value || '[]'); } catch(err){}
            const [moved] = items.splice(srcIdx, 1);
            items.splice(dstIdx, 0, moved);
            el('post-media').value = JSON.stringify(items, null, 2);
            renderAttachedAssets();
        }
    });

    // Mobile Touch
    let touchEl = null;
    list.addEventListener('touchstart', e => {
        const handle = e.target.closest('.drag-handle-btn');
        if (!handle) return;
        touchEl = handle.closest('.attached-asset-item');
        if (touchEl) touchEl.style.opacity = '0.5';
    }, {passive: false});

    list.addEventListener('touchmove', e => {
        if (!touchEl) return;
        e.preventDefault(); // Stop scrolling while reordering
        const touch = e.touches[0];
        const target = document.elementFromPoint(touch.clientX, touch.clientY);
        if (target) {
            const targetItem = target.closest('.attached-asset-item');
            if (targetItem && targetItem !== touchEl && targetItem.parentNode === list) {
                const rect = targetItem.getBoundingClientRect();
                const overHalfX = (touch.clientX > rect.left + rect.width / 2);
                
                // Swap in DOM for visual feedback
                if (overHalfX) {
                    list.insertBefore(touchEl, targetItem.nextSibling);
                } else {
                    list.insertBefore(touchEl, targetItem);
                }
            }
        }
    }, {passive: false});

    const endTouch = () => {
        if (!touchEl) return;
        touchEl.style.opacity = '1';
        
        // Reconstruct items based on new DOM order
        const newOrderIdxs = Array.from(list.querySelectorAll('.attached-asset-item')).map(el => parseInt(el.dataset.idx));
        let oldItems = [];
        try { oldItems = JSON.parse(el('post-media').value || '[]'); } catch(err){}
        
        // Check if any order changed
        const changed = newOrderIdxs.some((oldIdx, currIdx) => oldIdx !== currIdx);
        
        if (changed && oldItems.length === newOrderIdxs.length) {
            let newItems = newOrderIdxs.map(i => oldItems[i]);
            el('post-media').value = JSON.stringify(newItems, null, 2);
        }
        renderAttachedAssets();
        
        touchEl = null;
    };

    list.addEventListener('touchend', endTouch);
    list.addEventListener('touchcancel', () => {
        if (touchEl) {
            touchEl.style.opacity = '1';
            touchEl = null;
            renderAttachedAssets();
        }
    });
}

// Post modal
async function openPostModal(id = null, dateStr = null) {
    resetPostModal();

    if (id) {
        const data = await api({ action: 'get_post', id });
        if (data.success && data.post) {
            const p = data.post;
            el('modal-title').textContent    = 'Edit Post';
            el('post-id').value              = p.id;
            el('post-title').value           = p.title ?? '';
            el('post-status-select').value   = p.status ?? 'draft';
            el('post-type-select').value     = p.post_type ?? 'image_grid';
            el('post-sort').value            = p.sort_order ?? 0;
            el('post-preview').value         = p.preview_image_url ?? '';
            el('post-content').value         = p.content ?? '';
            el('post-media').value           = p.media_items ?? '';
            el('post-hashtags').value        = p.hashtags ?? '';
            el('post-asset-prefix').value    = p.asset_url_prefix ?? '';
            if (p.scheduled_at) el('post-scheduled').value = p.scheduled_at.replace(' ','T').slice(0,16);

            let activePlatforms = [];
            try {
                const pj = p.platforms_json ? JSON.parse(p.platforms_json) : null;
                if (Array.isArray(pj) && pj.length) activePlatforms = pj;
            } catch(e) {}
            if (!activePlatforms.length && p.platform) activePlatforms = [p.platform];
            document.querySelectorAll('.platform-check input').forEach(cb => {
                cb.checked = activePlatforms.includes(cb.value);
            });
        }
    }

    if (dateStr) {
        el('post-scheduled').value = dateStr + 'T09:00';
        el('post-status-select').value = 'scheduled';
    }

    renderAttachedAssets();
    el('post-modal-overlay').classList.add('open');
}

function resetPostModal() {
    el('modal-title').textContent = 'Create New Post';
    el('post-id').value = '';
    el('post-title').value = '';
    el('post-status-select').value = 'draft';
    el('post-type-select').value = 'image_grid';
    el('post-sort').value = 0;
    el('post-preview').value = '';
    el('post-content').value = '';
    el('post-media').value = '[]';
    el('post-hashtags').value = '';
    el('post-asset-prefix').value = '';
    el('post-scheduled').value = '';
    document.querySelectorAll('.platform-check input').forEach(cb => cb.checked = false);
    renderAttachedAssets();
}

function closePostModal() { el('post-modal-overlay').classList.remove('open'); }
function closeModalOnOverlay(e) { if (e.target === el('post-modal-overlay')) closePostModal(); }

async function savePost() {
    const id    = el('post-id').value;
    const title = el('post-title').value.trim();
    if (!title) { toast('Title is required', 'error'); return; }

    const platforms = Array.from(document.querySelectorAll('.platform-check input:checked')).map(c => c.value);

    const payload = {
        action:            'save_post',
        id:                id || '',
        title,
        status:            el('post-status-select').value,
        post_type:         el('post-type-select').value,
        sort_order:        el('post-sort').value,
        preview_image_url: el('post-preview').value,
        content:           el('post-content').value,
        media_items:       el('post-media').value || '[]',
        hashtags:          el('post-hashtags').value,
        asset_url_prefix:  el('post-asset-prefix').value,
        scheduled_at:      el('post-scheduled').value || '',
        platform:          platforms[0] ?? '',
        platforms_json:    JSON.stringify(platforms),
    };

    const data = await api(payload);
    if (data.success) {
        toast(id ? 'Post updated!' : 'Post created!', 'success');
        closePostModal();
        loadStats();
        loadCalendar();
        loadUpcoming();
        if (currentView === 'posts') loadPosts();
    } else {
        toast(data.error ?? 'Save failed', 'error');
    }
}

// ── Highlight Modal Logic ──
function openHighlightModal() {
    el('highlight-modal-overlay').classList.add('open');
    loadPublishedEpisodesForHighlight();
}

function closeHighlightModal() {
    el('highlight-modal-overlay').classList.remove('open');
}

function closeHighlightModalOnOverlay(e) {
    if (e.target === el('highlight-modal-overlay')) closeHighlightModal();
}

async function loadPublishedEpisodesForHighlight() {
    const list = el('highlight-episodes-list');
    list.innerHTML = '<div class="spinner"></div>';
    
    const data = await api({ action: 'get_published_episodes' });
    if (!data.success || !data.episodes || !data.episodes.length) {
        list.innerHTML = '<div class="empty-state"><p>No published episodes available.</p></div>';
        return;
    }
    
    list.innerHTML = data.episodes.map(ep => {
        const cover = ep.resolved_cover || '';
        return `
        <div class="post-row" style="grid-template-columns: 48px 1fr auto; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 8px;" onclick="createHighlightPost(${ep.series_id}, ${ep.sequence_id}, '${escHtml(ep.episode_name)}', '${escHtml(cover)}', '${escHtml(ep.chapter_label || '')}', '${escHtml(ep.asset_url_prefix || '')}')">
            <img class="post-thumb-sm" src="${escHtml(cover)}" onerror="this.style.display='none'">
            <div class="post-info-col">
                <div class="post-name">${escHtml(ep.episode_name)}</div>
                <div class="post-meta-row">
                    <span style="font-size:11px;color:var(--text-muted); font-weight: 500;">${escHtml(ep.series_title)}</span>
                    ${ep.chapter_label ? `<span style="font-size:10px;color:var(--amber); border-left:1px solid var(--border); padding-left:8px;">${escHtml(ep.chapter_label)}</span>` : ''}
                </div>
            </div>
            <button class="btn-primary">Select</button>
        </div>`;
    }).join('');
}

async function createHighlightPost(seriesId, seqId, epName, coverUrl, chapterLabel, prefix) {
    const payload = {
        action: 'save_post',
        title: epName,
        status: 'published',
        post_type: 'magazine_highlight',
        preview_image_url: coverUrl,
        content: chapterLabel || 'Featured Episode',
        asset_url_prefix: prefix,
        media_items: JSON.stringify({ series_id: seriesId, sequence_id: seqId }),
        platforms_json: '["website"]',
        platform: 'website'
    };
    
    const res = await api(payload);
    if (res.success) {
        toast('Highlight added successfully!', 'success');
        closeHighlightModal();
        if (currentView === 'posts') loadPosts();
    } else {
        toast(res.error || 'Failed to add highlight', 'error');
    }
}
// ───────────────────────────

async function confirmDelete(id, title) {
    if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;
    const data = await api({ action: 'delete_post', id });
    if (data.success) {
        toast('Post deleted', 'info');
        loadStats();
        loadCalendar();
        if (currentView === 'posts') loadPosts();
    } else {
        toast('Delete failed', 'error');
    }
}

function doExportPost(id) {
    window.location.href = `api.php?action=export_post_zip&id=${id}`;
}

function doExportHtml(id) {
    window.location.href = `api.php?action=export_post_html&id=${id}`;
}

async function doRollout(id, title) {
    if (!confirm(`Rollout "${title}" to GitHub Pages?`)) return;
    const data = await api({ action: 'rollout_post', id });
    if (data.success) {
        toast('Post rollout scheduled to GitHub sync queue!', 'success');
    } else {
        toast(data.error ?? 'Rollout failed', 'error');
    }
}

function doExport(action) {
    window.location.href = `api.php?action=${action}`;
}

// Theme
function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') ?? 'dark';
    const next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('spw_theme', next); } catch(e) {}
}

// API helper
async function api(payload) {
    try {
        const body = new FormData();
        Object.entries(payload).forEach(([k,v]) => body.append(k, v));
        const r = await fetch(API, { method: 'POST', body });
        return await r.json();
    } catch (e) {
        console.error('API error', e);
        return { success: false, error: e.message };
    }
}

// Toast
function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    el('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// Utils
function el(id) { return document.getElementById(id); }
function escHtml(str) { return String(str ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function capitalize(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }
function formatDate(s) {
    if (!s) return '—';
    const d = new Date(s);
    return isNaN(d) ? s : d.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
}

// Global search drives post search state
document.getElementById('global-search').addEventListener('input', e => {
    const q = e.target.value;
    if (q.length < 2 && q.length !== 0) return;
    _postSearchValue = q;
    switchView('posts', document.querySelector('[data-view=posts]'));
    debounceLoadPosts();
});
</script>

<?php echo $eruda ?? ''; ?>
</body>
</html>