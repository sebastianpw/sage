<?php
// public/timelines.php
// SAGE AI — Story Timelines
// Presents PLUSH highlight beats and Narrative Sequences via TimelineJS (JSON mode).
// Two timeline sources: plush (stories / collections) and cinemagic (sequences / cinemagics).

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Pre-load selectors for PHP-rendered dropdowns
$stories     = $pdo->query("SELECT id, title FROM plush_stories ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
$collections = $pdo->query("SELECT id, title FROM plush_collections ORDER BY title ASC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$cinemagics  = $pdo->query("SELECT id, name FROM cinemagics ORDER BY name ASC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$sequences   = $pdo->query("SELECT id, name FROM narrative_sequences ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.8, maximum-scale=1.0">
<title>Timelines — SAGE AI</title>



    <script>
        (function() {
            try {
                var theme = localStorage.getItem('spw_theme');
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (theme === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>




<!-- TimelineJS -->
<link  rel="stylesheet" href="https://cdn.knightlab.com/libs/timeline3/latest/css/timeline.css">
<script src="https://cdn.knightlab.com/libs/timeline3/latest/js/timeline.js"></script>

<!-- Swiper -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<!-- PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.initPhotoSwipe = function() {
        if (window.pswpLightbox) {
            try { window.pswpLightbox.destroy(); } catch(e){}
        }
        window.pswpLightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery', 
            children: 'a', 
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        window.pswpLightbox.init();
    };
    window.initPhotoSwipe();
</script>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<!-- Project base -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   SAGE TIMELINES — Design System
   Palette: deep obsidian bg / electric teal accent / warm amber highlight
   Fonts:  'Cinzel' display (timeline headings) / 'Barlow' body
   Mobile-first, responsive
   ═══════════════════════════════════════════════════════════════════════════ */

@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Barlow:wght@300;400;500;600&family=Barlow+Condensed:wght@400;600&display=swap');

:root {
    --tl-bg:         #060a0f;
    --tl-surface:    #0b1118;
    --tl-card:       #0f1722;
    --tl-border:     #182030;
    --tl-text:       #b8ccdf;
    --tl-dim:        #4a5a70;
    --tl-teal:       #00e5cc;
    --tl-amber:      #f5a623;
    --tl-red:        #f05060;
    --tl-plush:      #a78bfa;   /* purple — plush mode */
    --tl-cine:       #38bdf8;   /* sky blue — cinemagic mode */

    --tl-char:       #fb923c;   /* orange — character tags */
    --tl-fact:       #4ade80;   /* green — faction tags */
    --tl-loc:        #60a5fa;   /* blue — location tags */
    --tl-anim:       #f43f5e;   /* pink — anima tags */
    --tl-sket:       #a855f7;   /* purple — sketch tags */



    
    --tl-h: 'Cinzel', serif;
    --tl-b: 'Barlow', sans-serif;
    --tl-bc: 'Barlow Condensed', sans-serif;
    --nav-h: 56px;
}
[data-theme="light"] {
    --tl-bg:         #f0f4f8;
    --tl-surface:    #ffffff;
    --tl-card:       #ffffff;
    --tl-border:     #d0dcea;
    --tl-text:       #1a2a3a;
    --tl-dim:        #7a90a8;
    --tl-teal:       #007a88;
    --tl-amber:      #c07800;
    --tl-plush:      #7c3aed;
    --tl-cine:       #0369a1;
    

    --tl-char:       #ea580c;
    --tl-fact:       #16a34a;
    --tl-loc:        #2563eb;
    --tl-anim:       #e11d48;
    --tl-sket:       #9333ea;
}



*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; background: var(--tl-bg); color: var(--tl-text); font-family: var(--tl-b); }

/* ─── NOISE OVERLAY ─────────────────────────────────────────────────────── */
body::before {
    content: '';
    position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    opacity: 0.4;
}

/* ─── NAVIGATION ─────────────────────────────────────────────────────────── */
.tl-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 9000;
    height: var(--nav-h);
    display: flex; align-items: center; gap: 8px;
    padding: 0 14px;
    background: rgba(6,10,15,0.92);
    border-bottom: 1px solid var(--tl-border);
    backdrop-filter: blur(8px);
}
[data-theme="light"] .tl-nav { background: rgba(240,244,248,0.95); }

.tl-nav-brand {
    font-family: var(--tl-h);
    font-size: .75rem;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--tl-teal);
    white-space: nowrap;
    user-select: none;
}
.tl-nav-sep { width: 1px; height: 24px; background: var(--tl-border); flex-shrink: 0; }
.tl-nav-mode-grp { display: flex; gap: 4px; }

.tl-mode-btn {
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid var(--tl-border);
    background: transparent;
    color: var(--tl-dim);
    font-family: var(--tl-bc);
    font-size: .75rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.tl-mode-btn.active-plush   { border-color: var(--tl-plush); color: var(--tl-plush); background: rgba(167,139,250,.1); }
.tl-mode-btn.active-cine    { border-color: var(--tl-cine);  color: var(--tl-cine);  background: rgba(56,189,248,.1);  }
.tl-mode-btn:hover:not(.active-plush):not(.active-cine) { border-color: var(--tl-teal); color: var(--tl-teal); }

.tl-nav-right { margin-left: auto; display: flex; gap: 6px; align-items: center; }
.tl-icon-btn {
    width: 34px; height: 34px; border-radius: 6px;
    border: 1px solid var(--tl-border); background: transparent;
    color: var(--tl-dim); font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all .2s;
}
.tl-icon-btn:hover { border-color: var(--tl-teal); color: var(--tl-teal); }

/* ─── SOURCE SELECTOR PANEL ─────────────────────────────────────────────── */
.tl-source-panel {
    position: fixed; top: var(--nav-h); left: 0; right: 0; z-index: 8500;
    background: var(--tl-surface);
    border-bottom: 1px solid var(--tl-border);
    padding: 10px 14px;
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    transform: translateY(0);
    transition: transform .3s cubic-bezier(.4,0,.2,1), opacity .2s;
}
.tl-source-panel.hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; }

.tl-src-label {
    font-family: var(--tl-bc);
    font-size: .65rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--tl-dim);
    white-space: nowrap;
}
.tl-src-select {
    flex: 1; min-width: 140px; max-width: 280px;
    background: var(--tl-card); color: var(--tl-text);
    border: 1px solid var(--tl-border); border-radius: 4px;
    padding: 7px 10px;
    font-family: var(--tl-b); font-size: .8rem;
}
.tl-src-select:focus { outline: none; border-color: var(--tl-teal); }

.tl-src-or {
    font-family: var(--tl-bc); font-size: .65rem;
    color: var(--tl-dim); letter-spacing: 2px; text-transform: uppercase;
    white-space: nowrap;
}

.tl-load-btn {
    padding: 7px 18px; border-radius: 4px;
    border: 1px solid var(--tl-teal);
    background: rgba(0,229,204,.1); color: var(--tl-teal);
    font-family: var(--tl-bc); font-size: .75rem; letter-spacing: 1px;
    text-transform: uppercase; cursor: pointer; font-weight: 600;
    transition: all .2s; white-space: nowrap;
}
.tl-load-btn:hover { background: var(--tl-teal); color: #000; }
.tl-load-btn:disabled { opacity: .4; pointer-events: none; }

/* ─── TIMELINE WRAPPER ──────────────────────────────────────────────────── */
.tl-stage {
    position: fixed;
    top: var(--nav-h);
    left: 0; right: 0; bottom: 0;
    transition: top .3s cubic-bezier(.4,0,.2,1);
}
.tl-stage.panel-open { top: calc(var(--nav-h) + 58px); }

#timeline-embed {
    width: 100%; height: 100%; touch-action: pan-y pinch-zoom;
}

/* ─── EMPTY / LOADING STATE ─────────────────────────────────────────────── */
.tl-splash {
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 20px; padding: 40px 20px;
    text-align: center;
}
.tl-splash-icon { font-size: 3.5rem; opacity: .18; }
.tl-splash-title {
    font-family: var(--tl-h); font-size: 1.3rem;
    letter-spacing: 2px; color: var(--tl-dim);
}
.tl-splash-sub { font-size: .8rem; color: var(--tl-dim); max-width: 320px; line-height: 1.6; }

.tl-spinner {
    width: 48px; height: 48px; border-radius: 50%;
    border: 3px solid var(--tl-border);
    border-top-color: var(--tl-teal);
    animation: tl-spin .8s linear infinite;
}
@keyframes tl-spin { to { transform: rotate(360deg); } }


/* ─── TIMELINE JS THEME OVERRIDES ────────────────────────────────────────── */
.tl-timeline { font-family: var(--tl-b) !important; background: var(--tl-bg) !important; }
.tl-storyslider { background: var(--tl-surface) !important; }
.tl-slide { background: var(--tl-card) !important; }
.tl-slide-background { background: transparent !important; }

.tl-slide .tl-headline { font-family: var(--tl-h) !important; color: var(--tl-text) !important; text-shadow: none !important; }
.tl-slide .tl-text-content { color: var(--tl-text) !important; font-family: var(--tl-b) !important; text-shadow: none !important; }

.tl-timenav { background: var(--tl-surface) !important; }
.tl-timenav-background { background: var(--tl-bg) !important; }
.tl-timenav-line { background: var(--tl-teal) !important; }

.tl-timemarker .tl-timemarker-content-container {
    background: var(--tl-card) !important;
    border-color: var(--tl-border) !important;
}
.tl-timemarker.tl-timemarker-active .tl-timemarker-content-container {
    border-color: var(--tl-teal) !important;
    background: rgba(0,229,204,.08) !important;
}
.tl-timemarker .tl-headline {
    font-family: var(--tl-bc) !important;
    color: var(--tl-text) !important;
    font-size: .7rem !important;
}
.tl-timemarker.sage-has-color .tl-timemarker-content-container {
    border-color: transparent !important;
}

.tl-timemarker .tl-timemarker-content-container,
.tl-timemarker .tl-timemarker-line-left,
.tl-timemarker .tl-timemarker-timespan {
    transition: opacity 0.3s, filter 0.3s;
}
.tl-timemarker.sage-dimmed .tl-timemarker-content-container,
.tl-timemarker.sage-dimmed .tl-timemarker-line-left,
.tl-timemarker.sage-dimmed .tl-timemarker-timespan {
    opacity: 0.25; 
    filter: grayscale(100%);
}

.tl-era { background: rgba(0,229,204,.06) !important; border-color: rgba(0,229,204,.25) !important; }
.tl-era-title { font-family: var(--tl-bc) !important; color: var(--tl-teal) !important; }

/* ─── DISABLE TIMELINEJS SLIDE NAVIGATION ARROWS / TAP ZONES ────────────── */
#timeline-embed .tl-navigation,
#timeline-embed .tl-slidenav-next,
#timeline-embed .tl-slidenav-previous,
#timeline-embed .tl-slidenav-icon {
    display: none !important;
    pointer-events: none !important;
    width: 0 !important;
    height: 0 !important;
    opacity: 0 !important;
    visibility: hidden !important;
    z-index: -9999 !important;
}



[data-theme="light"] .tl-slide .tl-headline,
[data-theme="light"] .tl-slide .tl-text-content,
[data-theme="light"] .tl-timemarker .tl-headline { color: var(--tl-text) !important; }

.tl-entity-tag {
    display: inline-block;
    padding: 2px 8px; border-radius: 10px;
    font-family: var(--tl-bc); font-size: .7rem; letter-spacing: .5px;
    cursor: pointer; transition: filter .15s;
    border: 1px solid;
}
.tl-entity-tag:hover { filter: brightness(1.3); }

.tl-entity-characters { background: rgba(251,146,60,.15); border-color: var(--tl-char); color: var(--tl-char); }
.tl-entity-factions   { background: rgba(74,222,128,.15); border-color: var(--tl-fact); color: var(--tl-fact); }
.tl-entity-locations  { background: rgba(96,165,250,.15); border-color: var(--tl-loc);  color: var(--tl-loc);  }
.tl-entity-animas     { background: rgba(244,63,94,.15);  border-color: var(--tl-anim); color: var(--tl-anim); }
.tl-entity-sketches   { background: rgba(168,85,247,.15); border-color: var(--tl-sket); color: var(--tl-sket); }

#timeline-embed,
#timeline-embed #tl-inner,
#timeline-embed .tl-timeline,
#timeline-embed .tl-storyslider,
#timeline-embed .tl-slider-container-mask,
#timeline-embed .tl-slide-content-container,
#timeline-embed .tl-slide-scrollable-container,
#timeline-embed .tl-slide,
#timeline-embed .tl-slide-background,
#timeline-embed .tl-media,
#timeline-embed .tl-media-content,
#timeline-embed .tl-timeaxis,
#timeline-embed .tl-timeaxis-background,
#timeline-embed .tl-timenav-background {
    background: var(--tl-bg) !important;
}

#timeline-embed .tl-timeline {
    color: var(--tl-text) !important;
}

#timeline-embed .tl-timeaxis-background,
#timeline-embed .tl-timeaxis-background .tl-interval-background,
#timeline-embed .tl-timeaxis-background .tl-interval-background.even,
#timeline-embed .tl-timeaxis-background .tl-interval-background.odd,
#timeline-embed .tl-timeaxis .tl-timeaxis-content-container,
#timeline-embed .tl-timeaxis .tl-timeaxis-major,
#timeline-embed .tl-timeaxis .tl-timeaxis-minor {
    background: var(--tl-surface) !important;
}

#timeline-embed .tl-timeaxis .tl-timeaxis-major,
#timeline-embed .tl-timeaxis .tl-timeaxis-minor {
    border-color: var(--tl-border) !important;
}

#timeline-embed .tl-timenav,
#timeline-embed .tl-timenav-background,
#timeline-embed .tl-timenav-content-container,
#timeline-embed .tl-timeaxis,
#timeline-embed .tl-timeaxis-background,
#timeline-embed .tl-timeaxis-content-container,
#timeline-embed .tl-timeaxis-major,
#timeline-embed .tl-timeaxis-minor,
#timeline-embed .tl-timeaxis-tick,
#timeline-embed .tl-timeaxis-tick:before,
#timeline-embed .tl-timeaxis-background *,
#timeline-embed .tl-timenav-background * {
    background: var(--tl-surface) !important;
    background-color: var(--tl-surface) !important;
    background-image: none !important;
    box-shadow: none !important;
}

html[data-theme="dark"] #timeline-embed .tl-timegroup,
html[data-theme="dark"] #timeline-embed .tl-tilegroup {
    background-color: #0b1118 !important;
    background-image: none !important;
    color: #333;
}

html[data-theme="dark"] #timeline-embed .tl-timegroup-alternate,
html[data-theme="dark"] #timeline-embed .tl-tilegroup-alternate {
    background-color: #0f1722 !important;
    background-image: none !important;
    color: #333;
}

html[data-theme="dark"] #timeline-embed .tl-timegroup *,
html[data-theme="dark"] #timeline-embed .tl-timegroup-alternate *,
html[data-theme="dark"] #timeline-embed .tl-tilegroup *,
html[data-theme="dark"] #timeline-embed .tl-tilegroup-alternate * {
    background-color: transparent !important;
    background-image: none !important;
    color: #333;
}

html[data-theme="dark"] #timeline-embed .tl-menubar,
html[data-theme="dark"] #timeline-embed .tl-menubar-button {
    background-color: #0f1722 !important;
}

.tl-attribution {
    display:none !important;
}

html[data-theme="dark"] #timeline-embed * {
    text-shadow: none !important;
}

/* ─── GALLERY OVERRIDES ─────────────────────────────────────────────────── */
#timeline-embed .visual-container { 
    flex-direction: column; 
    border-top: 1px solid var(--tl-border); 
    padding-top: 10px; 
    margin-top: 10px; 
    min-width: 0; 
    display:flex; 
}
#timeline-embed .sage-sketch-swiper { 
    width: 100%; 
    max-width: 320px; 
    aspect-ratio: 1 / 1; 
    margin-bottom: 10px; 
    margin-left: 0; 
    overflow: hidden; 
}
#timeline-embed .swiper-slide { 
    width: 100%; 
    height: 100%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    background: #000; 
    border-radius: 4px; 
    overflow: hidden; 
    border: 1px solid var(--tl-border); 
    position: relative; 
    box-sizing: border-box; 
}
#timeline-embed .swiper-slide a {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
#timeline-embed .swiper-slide img { 
    width: 100%; 
    height: 100%; 
    display: block; 
    object-fit: contain; 
}

#timeline-embed .f-view-btn { 
    position: absolute; 
    top: 5px; 
    right: 5px; 
    width: 32px; 
    height: 32px; 
    background: rgba(0,0,0,0.65); 
    color: #fff; 
    border: 1px solid rgba(255,255,255,0.3); 
    border-radius: 4px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    z-index: 5000; /* Ensure it stays above PhotoSwipe intercepts */
    opacity: 0.85; /* Made visible by default for touch devices! */
    transition: all 0.2s; 
    font-size: 16px; 
}
#timeline-embed .swiper-slide:hover .f-view-btn { opacity: 1; background: rgba(0,0,0,0.9); }
#timeline-embed .f-view-btn:hover { background: #fff; border-color: #fff; color: #000; transform: scale(1.05); }


/* Frame View Modal */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 200005; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid #444; box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }

/* ─── ENTITY DETAIL MODAL (iframe) ──────────────────────────────────────── */
#entity-modal-backdrop {
    position: fixed; inset: 0; z-index: 200000;
    background: rgba(0,0,0,.78); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
}
#entity-modal-backdrop.active { display: flex; }

#entity-modal-box {
    width: 100%; max-width: 700px; height: 88vh;
    background: var(--tl-card);
    border: 1px solid var(--tl-border); border-radius: 8px;
    overflow: hidden; display: flex; flex-direction: column;
    margin: 10px;
}
.entity-modal-header {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--tl-border);
    background: var(--tl-surface);
    flex-shrink: 0;
}
.entity-modal-title {
    font-family: var(--tl-bc); font-size: .75rem;
    letter-spacing: 2px; text-transform: uppercase; color: var(--tl-teal);
    flex: 1;
}
.entity-modal-close {
    background: transparent; border: none;
    color: var(--tl-dim); cursor: pointer; font-size: 1.2rem;
    transition: color .15s;
}
.entity-modal-close:hover { color: var(--tl-text); }
#entity-iframe {
    flex: 1; border: none; width: 100%; background: var(--tl-card);
}

/* ─── BEAT / PLUSH EDITOR MODAL ─────────────────────────────────────────── */
#beat-modal-backdrop {
    position: fixed; inset: 0; z-index: 200001;
    background: rgba(0,0,0,.8); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
}
#beat-modal-backdrop.active { display: flex; }
#beat-modal-box {
    width: 100%; max-width: 100%; height: 100%; max-height: 100%;
    background: var(--tl-card); border: none;
    border-radius: 0; overflow: hidden;
    display: flex; flex-direction: column; margin: 0;
}
.beat-modal-header {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-bottom: 1px solid var(--tl-border);
    background: var(--tl-surface); flex-shrink: 0;
}
.beat-modal-title {
    font-family: var(--tl-h); font-size: .85rem;
    color: var(--tl-amber); flex: 1;
}

/* Plush iframe inside beat modal */
#beat-plush-iframe {
    flex: 1; border: none; width: 100%;
    background: var(--tl-card);
}

/* ─── DATE EDITOR PANEL ─────────────────────────────────────────────────── */
#date-panel {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 7000;
    background: var(--tl-surface); border-top: 1px solid var(--tl-border);
    padding: 10px 14px;
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    transform: translateY(100%); transition: transform .3s cubic-bezier(.4,0,.2,1);
}
#date-panel.open { transform: translateY(0); }

.date-panel-label { font-family: var(--tl-bc); font-size: .65rem; letter-spacing: 2px; text-transform: uppercase; color: var(--tl-dim); }
.date-panel-input {
    flex: 1; min-width: 90px; max-width: 160px;
    background: var(--tl-card); color: var(--tl-text);
    border: 1px solid var(--tl-border); border-radius: 4px;
    padding: 6px 8px; font-family: var(--tl-b); font-size: .75rem;
}
.date-panel-input:focus { outline: none; border-color: var(--tl-amber); }

/* ─── HIGHLIGHT / FILTER BAR ─────────────────────────────────────────────── */
#hl-panel {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 7500;
    background: var(--tl-surface); border-top: 1px solid var(--tl-border);
    padding: 10px 14px;
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    transform: translateY(100%); transition: transform .3s cubic-bezier(.4,0,.2,1);
}
#hl-panel.open { transform: translateY(0); }
.hl-logic-btn {
    padding: 4px 8px; border-radius: 4px; border: 1px solid var(--tl-border);
    background: var(--tl-card); color: var(--tl-dim); font-family: var(--tl-bc);
    font-size: .65rem; cursor: pointer; transition: all .15s; text-transform: uppercase;
}
.hl-logic-btn.active { border-color: var(--tl-teal); color: var(--tl-teal); background: rgba(0,229,204,.1); }

.tl-timemarker .tl-timemarker-content-container,
.tl-timemarker .tl-timemarker-line-left,
.tl-timemarker .tl-timemarker-timespan {
    transition: opacity 0.3s, filter 0.3s;
}
.tl-timemarker.sage-dimmed .tl-timemarker-content-container,
.tl-timemarker.sage-dimmed .tl-timemarker-line-left,
.tl-timemarker.sage-dimmed .tl-timemarker-timespan {
    opacity: 0.25; 
    filter: grayscale(100%);
}

/* ─── TOAST ─────────────────────────────────────────────────────────────── */
.tl-message,
.tl-message-full,
.tl-mobile-message { display: none !important; }

/* ─── RESPONSIVE ─────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
    .tl-nav-brand { display: none; }
    .tl-src-label { display: none; }
    .tl-src-select { font-size: .75rem; }
    .tl-mode-btn { padding: 4px 8px; font-size: .65rem; }
}

/* Entity chip styles (for beat entity chips inside tl modals, kept for hl panel) */
.beat-entity-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 12px; font-size: .7rem; font-family: var(--tl-bc); border: 1px solid; }
.chip-char { background: rgba(251,146,60,.1); border-color: var(--tl-char); color: var(--tl-char); }
.chip-fact { background: rgba(74,222,128,.1); border-color: var(--tl-fact); color: var(--tl-fact); }
.chip-loc  { background: rgba(96,165,250,.1); border-color: var(--tl-loc);  color: var(--tl-loc);  }
.chip-anim { background: rgba(244,63,94,.1);  border-color: var(--tl-anim); color: var(--tl-anim); }
.chip-sket { background: rgba(168,85,247,.1); border-color: var(--tl-sket); color: var(--tl-sket); }
.chip-remove { background: none; border: none; cursor: pointer; color: inherit; opacity: .6; font-size: .75rem; padding: 0; line-height: 1; transition: opacity .15s; }
.chip-remove:hover { opacity: 1; }
.entity-type-tab { padding: 3px 8px; border-radius: 10px; border: 1px solid var(--tl-border); background: transparent; color: var(--tl-dim); font-family: var(--tl-bc); font-size: .65rem; cursor: pointer; transition: all .15s; text-transform: uppercase; letter-spacing: 1px; }
.active-char { border-color: var(--tl-char); color: var(--tl-char); background: rgba(251,146,60,.1); }
.active-fact { border-color: var(--tl-fact); color: var(--tl-fact); background: rgba(74,222,128,.1); }
.active-loc  { border-color: var(--tl-loc);  color: var(--tl-loc);  background: rgba(96,165,250,.1);  }
.active-anim { border-color: var(--tl-anim); color: var(--tl-anim); background: rgba(244,63,94,.1);  }
.active-sket { border-color: var(--tl-sket); color: var(--tl-sket); background: rgba(168,85,247,.1); }
.entity-search-wrap { position: relative; flex: 1; min-width: 140px; }
.entity-search-input { width: 100%; background: var(--tl-surface); color: var(--tl-text); border: 1px solid var(--tl-border); border-radius: 4px; padding: 6px 10px; font-family: var(--tl-b); font-size: .8rem; }
.entity-search-input:focus { outline: none; border-color: var(--tl-teal); }
.entity-autocomplete { position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: var(--tl-card); border: 1px solid var(--tl-border); border-top: none; border-radius: 0 0 4px 4px; max-height: 160px; overflow-y: auto; }
.entity-ac-item { padding: 7px 10px; font-size: .8rem; cursor: pointer; transition: background .1s; }
.entity-ac-item:hover { background: rgba(0,229,204,.07); color: var(--tl-teal); }


/* ─── CUSTOM IN-SLIDE NAVIGATION BUTTONS ────────────────────────────────── */
.sage-slide-nav {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    /* Optional: justify-content: space-between; if you want them on far edges */
}
.sage-nav-btn {
    background: var(--tl-surface);
    border: 1px solid var(--tl-border);
    color: var(--tl-dim);
    border-radius: 20px;
    padding: 4px 14px;
    font-family: var(--tl-bc);
    font-size: 0.7rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}
.sage-nav-btn:hover {
    border-color: var(--tl-teal);
    color: var(--tl-teal);
    background: rgba(0,229,204,.05);
}






</style>
</head>
<body>

<!-- ─── Navigation ──────────────────────────────────────────────────────── -->
<nav class="tl-nav">
    <span class="tl-nav-brand">SAGE Timelines</span>
    <div class="tl-nav-sep"></div>

    <div class="tl-nav-mode-grp">
        <button class="tl-mode-btn active-plush" id="modeBtn-plush" onclick="setMode('plush')">
            <i class="bi bi-journal-richtext"></i> Plush
        </button>
        <button class="tl-mode-btn" id="modeBtn-cine" onclick="setMode('cine')">
            <i class="bi bi-film"></i> Cinemagic
        </button>
    </div>

    <div class="tl-nav-right">
        <button class="tl-icon-btn" id="toggleSourceBtn" onclick="toggleSourcePanel()" title="Source selector">
            <i class="bi bi-sliders"></i>
        </button>
        <button class="tl-icon-btn" id="highlightToggleBtn" onclick="toggleHighlightPanel()" title="Highlight entities" style="display:none;">
            <i class="bi bi-funnel"></i>
        </button>
        <button class="tl-icon-btn" id="beatEditorBtn" onclick="openBeatEditor()" title="Open PLUSH editor" style="display:none;">
            <i class="bi bi-tags"></i>
        </button>
        <button class="tl-icon-btn" id="dateEditorBtn" onclick="toggleDatePanel()" title="Edit timeline dates" style="display:none;">
            <i class="bi bi-calendar3"></i>
        </button>
        <a style="display:none;" href="plush.php" class="tl-icon-btn" title="Back to PLUSH editor">
            <i class="bi bi-pencil-square"></i>
        </a>
    </div>
</nav>

<!-- ─── Source Selector ─────────────────────────────────────────────────── -->
<div class="tl-source-panel" id="sourcePanel">
    <!-- PLUSH controls -->
    <div id="plush-src-controls" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;">
        <span class="tl-src-label" style="color:var(--tl-plush);">&#128221; Story</span>
        <select class="tl-src-select" id="storySelect">
            <option value="">— pick a story —</option>
            <?php foreach ($stories as $s): ?>
                <option value="story:<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
            <?php endforeach; ?>
        </select>

        <span class="tl-src-or">or</span>

        <span class="tl-src-label">Collection</span>
        <select class="tl-src-select" id="collectionSelect">
            <option value="">— pick a collection —</option>
            <?php foreach ($collections as $c): ?>
                <option value="collection:<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- CINEMAGIC controls (hidden initially) -->
    <div id="cine-src-controls" style="display:none;gap:8px;align-items:center;flex-wrap:wrap;flex:1;">
        <span class="tl-src-label" style="color:var(--tl-cine);">&#127916; Cinemagic</span>
        <select class="tl-src-select" id="cinemagicSelect">
            <option value="">— pick a cinemagic —</option>
            <?php foreach ($cinemagics as $c): ?>
                <option value="cinemagic:<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <span class="tl-src-or">or</span>

        <span class="tl-src-label">Sequence</span>
        <select class="tl-src-select" id="sequenceSelect">
            <option value="">— pick a sequence —</option>
            <?php foreach ($sequences as $s): ?>
                <option value="sequence:<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="tl-load-btn" id="loadTimelineBtn" onclick="loadTimeline()">
        <i class="bi bi-play-fill"></i> Load
    </button>
</div>

<!-- ─── Timeline Stage ──────────────────────────────────────────────────── -->
<div class="tl-stage panel-open" id="tlStage">
    <div id="timeline-embed">
        <div class="tl-splash" id="tlSplash">
            <div class="tl-splash-icon"><i class="bi bi-film"></i></div>
            <div class="tl-splash-title">Story Timeline</div>
            <div class="tl-splash-sub">Select a source above and press Load to visualise your story beats or narrative sequences on the timeline.</div>
        </div>
    </div>
</div>

<!-- ─── Date Editor Panel ────────────────────────────────────────────────── -->
<div id="date-panel">
    <span class="date-panel-label">&#128337; Timeline Sort</span>
    <span id="datePanelContext" style="font-family:var(--tl-bc);font-size:.7rem;color:var(--tl-dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;"></span>
    <input class="date-panel-input" type="number" id="dpSortStart" placeholder="Sort start (int)">
    <input class="date-panel-input" type="number" id="dpSortEnd"   placeholder="Sort end (int, opt)">
    <input class="date-panel-input" type="text"   id="dpLabelStart" placeholder="Start label (e.g. Year 1 AE)" style="max-width:180px;">
    <button class="tl-load-btn" onclick="saveDateEntry()"><i class="bi bi-floppy"></i> Save</button>
    <button class="tl-icon-btn" onclick="toggleDatePanel()"><i class="bi bi-x-lg"></i></button>
</div>

<!-- ─── Highlight / Filter Panel ─────────────────────────────────────────── -->
<div id="hl-panel">
    <span class="date-panel-label"><i class="bi bi-funnel"></i> Highlight</span>
    
    <button class="hl-logic-btn active" id="hl-logic-or" onclick="setHighlightLogic('OR')">OR</button>
    <button class="hl-logic-btn" id="hl-logic-and" onclick="setHighlightLogic('AND')">AND</button>
    
    <div class="entity-type-tabs" style="margin-left:10px;">
        <button class="entity-type-tab active-char" id="hl-tab-char" onclick="setHlTab('characters')">Char</button>
        <button class="entity-type-tab" id="hl-tab-fact" onclick="setHlTab('factions')">Fac</button>
        <button class="entity-type-tab" id="hl-tab-loc"  onclick="setHlTab('locations')">Loc</button>
        <button class="entity-type-tab" id="hl-tab-anim" onclick="setHlTab('animas')">Anim</button>
        <button class="entity-type-tab" id="hl-tab-sket" onclick="setHlTab('sketches')">Sket</button>
    </div>

    <div class="entity-search-wrap" style="flex:0; min-width:160px;">
        <input class="entity-search-input" type="text" placeholder="Search entity..." id="hl-search-input"
               oninput="hlSearchInput(this.value)" onfocus="hlSearchInput(this.value)" onblur="setTimeout(()=>hideHlAC(),200)">
        <div class="entity-autocomplete" id="hl-ac" style="display:none; bottom:100%; top:auto; border-radius:4px 4px 0 0; border-top:1px solid var(--tl-border); border-bottom:none;"></div>
    </div>

    <div class="beat-entity-chips" id="hl-chips-wrap" style="margin:0 0 0 10px; flex:1;"></div>
    
    <button class="tl-icon-btn" onclick="toggleHighlightPanel()"><i class="bi bi-x-lg"></i></button>
</div>

<!-- ─── Entity Detail Modal (iframe) ────────────────────────────────────── -->
<div id="entity-modal-backdrop" onmousedown="if(event.target===this)closeEntityModal()">
    <div id="entity-modal-box">
        <div class="entity-modal-header">
            <span class="entity-modal-title" id="entityModalTitle">Entity Details</span>
            <button class="entity-modal-close" onclick="closeEntityModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <iframe id="entity-iframe" src="about:blank"></iframe>
    </div>
</div>

<!-- ─── Beat / PLUSH Editor Modal ───────────────────────────────────────── -->
<div id="beat-modal-backdrop" onmousedown="if(event.target===this)closeBeatModal()">
    <div id="beat-modal-box">
        <div class="beat-modal-header">
            <span class="beat-modal-title" id="beatModalTitle">PLUSH Editor</span>
            <button class="entity-modal-close" onclick="closeBeatModal()" style="color:var(--tl-dim);font-size:1.2rem;background:transparent;border:none;cursor:pointer;"><i class="bi bi-x-lg"></i></button>
        </div>
        <!-- Full PLUSH editor iframe — src set dynamically when modal opens -->
        <iframe id="beat-plush-iframe" src="about:blank"></iframe>
    </div>
</div>

<!-- ─── Frame View Modal ────────────────────────────────────────────────── -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>


<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>

<script>
(function() {
'use strict';

// ── State ─────────────────────────────────────────────────────────────────
let currentMode      = 'plush';
let currentTL        = null;
let currentSourceKey = null;
let sourcePanelOpen  = true;
let datePanelOpen    = false;
let datePanelTarget  = null;

// ── Mode switching ────────────────────────────────────────────────────────
window.setMode = function(mode) {
    currentMode = mode;
    document.getElementById('modeBtn-plush').className = 'tl-mode-btn' + (mode === 'plush' ? ' active-plush' : '');
    document.getElementById('modeBtn-cine').className  = 'tl-mode-btn' + (mode === 'cine'  ? ' active-cine'  : '');
    document.getElementById('plush-src-controls').style.display = mode === 'plush' ? 'flex' : 'none';
    document.getElementById('cine-src-controls').style.display  = mode === 'cine'  ? 'flex' : 'none';
    document.getElementById('highlightToggleBtn').style.display = mode === 'plush' ? 'flex' : 'none';
    document.getElementById('beatEditorBtn').style.display      = mode === 'plush' ? 'flex' : 'none';
    document.getElementById('dateEditorBtn').style.display      = mode === 'plush' ? 'flex' : 'none';
};

// ── Source panel ──────────────────────────────────────────────────────────
window.toggleSourcePanel = function() {
    sourcePanelOpen = !sourcePanelOpen;
    const panel = document.getElementById('sourcePanel');
    const stage = document.getElementById('tlStage');
    panel.classList.toggle('hidden', !sourcePanelOpen);
    stage.classList.toggle('panel-open', sourcePanelOpen);
};

// ── Timeline load ─────────────────────────────────────────────────────────
window.loadTimeline = function() {
    let sourceVal = '';
    if (currentMode === 'plush') {
        sourceVal = document.getElementById('storySelect').value
                 || document.getElementById('collectionSelect').value;
    } else {
        sourceVal = document.getElementById('cinemagicSelect').value
                 || document.getElementById('sequenceSelect').value;
    }
    if (!sourceVal) { Toast.show('Pick a source first.', 'warn'); return; }

    currentSourceKey = sourceVal;

    const embed = document.getElementById('timeline-embed');
    embed.innerHTML = '<div class="tl-splash"><div class="tl-spinner"></div></div>';
    if (currentTL) { try { currentTL.destroy?.(); } catch(e){} currentTL = null; }

    let url = 'timelines_api.php?';
    const [kind, id] = sourceVal.split(':');
    if (currentMode === 'plush') {
        url += 'action=plush_timeline&' + (kind === 'story' ? 'story_id=' : 'collection_id=') + id;
    } else {
        url += 'action=cinemagic_timeline&' + (kind === 'cinemagic' ? 'cinemagic_id=' : 'sequence_id=') + id;
    }

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showSplash('Load failed: ' + (res.message || 'Unknown error')); return; }
            renderTimeline(res.timeline);
            initPinchZoom(embed);
        })
        .catch(e => { showSplash('Network error: ' + e.message); });
};

function showSplash(msg) {
    const embed = document.getElementById('timeline-embed');
    embed.innerHTML = `<div class="tl-splash"><div class="tl-splash-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="tl-splash-title">${escHtml(msg)}</div></div>`;
}

function renderTimeline(tlData) {
    const embed = document.getElementById('timeline-embed');
    embed.innerHTML = '<div id="tl-inner" style="width:100%;height:100%;"></div>';

    const sageMeta = {};
    const cleanData = JSON.parse(JSON.stringify(tlData));
    if (cleanData.events) {
        cleanData.events.forEach((ev, i) => {
            if (ev._sage) { sageMeta[i] = ev._sage; delete ev._sage; }
        });
    }

    window._tlSageMeta = sageMeta;

    try {
        localStorage.setItem('tl-timenav-shown', 'true');
        localStorage.setItem('tl-intro-shown',   'true');
    } catch(e) {}

    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const bgColor = isLight ? { r: 240, g: 244, b: 248 } : { r: 11, g: 17, b: 24 };

    try {
        currentTL = new TL.Timeline('tl-inner', cleanData, {
            hash_bookmark: false,
            start_at_slide: 0,
            timenav_height: 180,
            timenav_height_percentage: 25,
            optimal_tick_width: 60,
            default_bg_color: bgColor,
        });

        const finalizeRender = () => {
            bindEntityTagClicks();
            applyFlagColors();
            initSlideGalleries();
            injectCustomSlideNav(); // <-- Inject custom navigation buttons

            // Intercept and kill drag/swipe events on the slide content 
            // so TimelineJS never sees them.
            document.querySelectorAll('.tl-slide-scrollable-container, .tl-slide-content').forEach(container => {
                if (container.dataset.dragKilled) return;
                container.dataset.dragKilled = "1";
                
                const haltDrag = (e) => {
                    if (e.touches && e.touches.length >= 2) return; // Allow pinch-zoom to bubble
                    e.stopPropagation(); // Hide the gesture from TimelineJS
                };
                
                ['touchstart', 'touchmove', 'touchend', 'mousedown', 'mousemove', 'mouseup', 'pointerdown'].forEach(evt => {
                    container.addEventListener(evt, haltDrag, { passive: false });
                });
            });
        };

        setTimeout(finalizeRender, 400);
        currentTL.on('change', () => setTimeout(finalizeRender, 200));

    } catch(e) {
        showSplash('Timeline render error: ' + e.message);
    }
}

// ── Custom In-Slide Navigation ────────────────────────────────────────────
function injectCustomSlideNav() {
    // Find all text containers inside timeline slides
    document.querySelectorAll('.tl-slide .tl-text').forEach(textContainer => {
        // Only inject once per slide
        if (textContainer.querySelector('.sage-slide-nav')) return;

        const navWrap = document.createElement('div');
        navWrap.className = 'sage-slide-nav';
        
        // Prev Button
        const btnPrev = document.createElement('button');
        btnPrev.className = 'sage-nav-btn';
        btnPrev.innerHTML = '<i class="bi bi-chevron-left"></i> Prev';
        const goPrev = (e) => { e.stopPropagation(); e.preventDefault(); if(currentTL) currentTL.goToPrev(); };
        btnPrev.addEventListener('click', goPrev);
        btnPrev.addEventListener('pointerdown', (e) => e.stopPropagation()); // Protect from parent drag listeners
        
        // Next Button
        const btnNext = document.createElement('button');
        btnNext.className = 'sage-nav-btn';
        btnNext.innerHTML = 'Next <i class="bi bi-chevron-right"></i>';
        const goNext = (e) => { e.stopPropagation(); e.preventDefault(); if(currentTL) currentTL.goToNext(); };
        btnNext.addEventListener('click', goNext);
        btnNext.addEventListener('pointerdown', (e) => e.stopPropagation()); // Protect from parent drag listeners

        navWrap.appendChild(btnPrev);
        navWrap.appendChild(btnNext);

        // Insert at the very top of the text container (above the headline)
        if (textContainer.firstChild) {
            textContainer.insertBefore(navWrap, textContainer.firstChild);
        } else {
            textContainer.appendChild(navWrap);
        }
    });
}





// ── Galleries & Modals ────────────────────────────────────────────────────
function initSlideGalleries() {
    // 1. Initialize Swiper galleries
    document.querySelectorAll('.sage-sketch-swiper:not(.swiper-initialized)').forEach(el => {
        
        // Fiercely isolate the Swiper from parent swipe gestures
        const halt = (e) => e.stopPropagation();
        ['touchstart', 'touchmove', 'touchend', 'mousedown', 'mousemove', 'mouseup', 'pointerdown', 'pointermove', 'pointerup', 'click'].forEach(evt => {
            el.addEventListener(evt, halt, { passive: false });
        });

        new Swiper(el, {
            slidesPerView: 1,
            spaceBetween: 10,
            observer: true,
            observeParents: true,
            observeSlideChildren: true,
            nested: true,             // Enables native Swiper nested logic
            touchReleaseOnEdges: true, 
            navigation: {
                nextEl: el.querySelector('.swiper-button-next'),
                prevEl: el.querySelector('.swiper-button-prev'),
            },
        });
    });
    
    // 2. Initialize PhotoSwipe
    if (window.initPhotoSwipe) {
        window.initPhotoSwipe();
    }

    // 3. Bind Top-Right Frame Detail Buttons
    // (We must bind dynamically here because TimelineJS's DOMPurify deletes inline click handlers)
    document.querySelectorAll('.f-view-btn').forEach(btn => {
        if (btn.dataset.boundFrameBtn) return;
        btn.dataset.boundFrameBtn = "1";
        
        const triggerModal = (e) => {
            e.stopPropagation();
            e.preventDefault();
            window.openIframeModal('view_frame.php?frame_id=' + btn.dataset.frameId + '&view=modal');
        };
        
        btn.addEventListener('click', triggerModal);
        btn.addEventListener('pointerdown', triggerModal);
    });
}



window.openIframeModal = function(url) {
    document.getElementById('frameViewer').src = url;
    document.getElementById('viewModal').classList.add('active');
};
window.closeFrameModal = function() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
};
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeFrameModal(); });


// ── Pinch to Zoom ─────────────────────────────────────────────────────────
let pinchZoomBound = false;
function initPinchZoom(embed) {
    if (pinchZoomBound) return;
    pinchZoomBound = true;
    let initialPinchDist = null;
    let lastZoomTime = 0;
    
    embed.addEventListener('touchstart', e => {
        if (e.touches.length === 2) {
            initialPinchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
        }
    }, {passive: true});
    
    embed.addEventListener('touchmove', e => {
        if (e.touches.length === 2 && initialPinchDist && currentTL && currentTL._timenav) {
            const dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
            const diff = dist - initialPinchDist;
            const now = Date.now();
            if (Math.abs(diff) > 40 && now - lastZoomTime > 300) {
                if (diff > 0) currentTL._timenav.zoomIn();
                else currentTL._timenav.zoomOut();
                initialPinchDist = dist; 
                lastZoomTime = now;
            }
        }
    }, {passive: true});
    
    embed.addEventListener('touchend', e => {
        if (e.touches.length < 2) initialPinchDist = null;
    });
}

// ── Flag color application ────────────────────────────────────────────────
function parseBgColor(rgba) {
    const m = rgba.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    return m ? { r: +m[1], g: +m[2], b: +m[3] } : null;
}

function relativeLuminance(r, g, b) {
    const chan = [r, g, b].map(c => {
        c /= 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * chan[0] + 0.7152 * chan[1] + 0.0722 * chan[2];
}

function contrastColor(r, g, b) {
    const lum = relativeLuminance(r, g, b);
    return (lum + 0.05) / 0.05 > 1.05 / (lum + 0.05) ? '#000' : '#fff';
}

function applyFlagColors() {
    if (!window._tlSageMeta) return;
    const markers = document.querySelectorAll('.tl-timenav .tl-timemarker');
    markers.forEach((marker, i) => {
        const meta = window._tlSageMeta[i];
        if (!meta || !meta.bg_color) return;
        const col = parseBgColor(meta.bg_color);
        if (!col) return;
        const container = marker.querySelector('.tl-timemarker-content-container');
        const headline  = marker.querySelector('.tl-headline');
        if (!container) return;
        const bgSolid = `rgba(${col.r},${col.g},${col.b},0.55)`;
        container.style.setProperty('background', bgSolid, 'important');
        container.style.setProperty('border-color', `rgba(${col.r},${col.g},${col.b},0.9)`, 'important');
        marker.classList.add('sage-has-color');
        marker.dataset.sageBg = meta.bg_color; 
        if (headline) {
            headline.style.setProperty('color', contrastColor(col.r, col.g, col.b), 'important');
        }
    });
    applySlideColor(); 
    evaluateHighlights();
}

function applySlideColor() {
    document.querySelectorAll('.tl-slide').forEach(slide => {
        slide.style.removeProperty('background-color');
    });
    const activeMarker = document.querySelector('.tl-timemarker-active');
    const activeSlide = document.querySelector('.tl-slide-active');
    if (activeMarker && activeMarker.dataset.sageBg && activeSlide) {
        activeSlide.style.setProperty('background-color', activeMarker.dataset.sageBg, 'important');
    }
}

function bindEntityTagClicks() {
    document.querySelectorAll('.tl-entity-tag').forEach(el => {
        if (el.dataset.boundTag) return;
        el.dataset.boundTag = '1';
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            openEntityModal(this.dataset.entityType, this.dataset.entityId, this.textContent.trim());
        });
    });
}

// ── Highlight / Filter Logic ──────────────────────────────────────────────
let hlPanelOpen = false;
let hlLogic = 'OR';
let hlTags = []; 
let hlSearchTimer = null;
let hlActiveType = 'characters';

window.toggleHighlightPanel = function() {
    hlPanelOpen = !hlPanelOpen;
    document.getElementById('hl-panel').classList.toggle('open', hlPanelOpen);
};

window.setHighlightLogic = function(logic) {
    hlLogic = logic;
    document.getElementById('hl-logic-or').classList.toggle('active', logic === 'OR');
    document.getElementById('hl-logic-and').classList.toggle('active', logic === 'AND');
    evaluateHighlights();
};

window.setHlTab = function(type) {
    hlActiveType = type;
    document.getElementById('hl-tab-char').className = 'entity-type-tab' + (type === 'characters' ? ' active-char' : '');
    document.getElementById('hl-tab-fact').className = 'entity-type-tab' + (type === 'factions' ? ' active-fact' : '');
    document.getElementById('hl-tab-loc').className  = 'entity-type-tab' + (type === 'locations' ? ' active-loc' : '');
    document.getElementById('hl-tab-anim').className = 'entity-type-tab' + (type === 'animas' ? ' active-anim' : '');
    document.getElementById('hl-tab-sket').className = 'entity-type-tab' + (type === 'sketches' ? ' active-sket' : '');
    const inp = document.getElementById('hl-search-input');
    inp.value = '';
    inp.focus();
};

window.hlSearchInput = function(q) {
    clearTimeout(hlSearchTimer);
    hlSearchTimer = setTimeout(() => {
        fetch(`timelines_api.php?action=search_entities&entity_type=${encodeURIComponent(hlActiveType)}&q=${encodeURIComponent(q)}`)
            .then(r=>r.json()).then(res => {
                if (!res.success || !res.results.length) { hideHlAC(); return; }
                const ac = document.getElementById('hl-ac');
                ac.innerHTML = res.results.map(r =>
                    `<div class="entity-ac-item" onpointerdown="addHlTag('${hlActiveType}', ${r.id}, '${escHtml(r.name)}')">
                        <span style="color:var(--tl-dim);font-family:var(--tl-bc);font-size:.7rem;margin-right:6px;">#${r.id}</span>${escHtml(r.name)}
                    </div>`
                ).join('');
                ac.style.display = 'block';
            });
    }, 280);
};

window.hideHlAC = function() { document.getElementById('hl-ac').style.display = 'none'; };

window.addHlTag = function(type, id, label) {
    hideHlAC();
    document.getElementById('hl-search-input').value = '';
    if (!hlTags.find(t => t.type === type && t.id === id)) {
        hlTags.push({ type, id, label });
        renderHlChips();
        evaluateHighlights();
    }
};

window.removeHlTag = function(type, id) {
    hlTags = hlTags.filter(t => !(t.type === type && t.id === id));
    renderHlChips();
    evaluateHighlights();
};

function renderHlChips() {
    const wrap = document.getElementById('hl-chips-wrap');
    wrap.innerHTML = hlTags.map(t => {
        const cls = t.type === 'characters' ? 'chip-char' : t.type === 'factions' ? 'chip-fact' : t.type === 'locations' ? 'chip-loc' : t.type === 'animas' ? 'chip-anim' : 'chip-sket';
        return `<span class="beat-entity-chip ${cls}">
            <span class="chip-label">${escHtml(t.label)}</span>
            <button class="chip-remove" onclick="removeHlTag('${t.type}', ${t.id})"><i class="bi bi-x"></i></button>
        </span>`;
    }).join('');
}

window.evaluateHighlights = function() {
    if (!window._tlSageMeta) return;
    const markers = document.querySelectorAll('.tl-timenav .tl-timemarker');
    
    if (hlTags.length === 0) {
        markers.forEach(m => m.classList.remove('sage-dimmed'));
        return;
    }

    const reqs = hlTags.map(t => `${t.type}:${t.id}`);

    markers.forEach((marker, i) => {
        const meta = window._tlSageMeta[i];
        if (!meta) return;
        
        const eventEntities = meta.entities || [];
        let isMatch = false;

        if (hlLogic === 'OR') {
            isMatch = reqs.some(r => eventEntities.includes(r));
        } else {
            isMatch = reqs.every(r => eventEntities.includes(r));
        }

        marker.classList.toggle('sage-dimmed', !isMatch);
    });
};

// ── Entity detail modal ───────────────────────────────────────────────────
window.openEntityModal = function(entityType, entityId, label) {
    const url = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
    document.getElementById('entity-iframe').src = url;
    document.getElementById('entityModalTitle').textContent = label + ' — ' + entityType;
    document.getElementById('entity-modal-backdrop').classList.add('active');
};
window.closeEntityModal = function() {
    document.getElementById('entity-modal-backdrop').classList.remove('active');
    document.getElementById('entity-iframe').src = 'about:blank';
};

// ── Date editor panel ─────────────────────────────────────────────────────
window.toggleDatePanel = function() {
    datePanelOpen = !datePanelOpen;
    document.getElementById('date-panel').classList.toggle('open', datePanelOpen);
};

window.openDateEditor = function(type, id, label) {
    datePanelTarget = { type, id };
    document.getElementById('datePanelContext').textContent = label || '';
    document.getElementById('dpSortStart').value  = '';
    document.getElementById('dpSortEnd').value    = '';
    document.getElementById('dpLabelStart').value = '';
    if (!datePanelOpen) toggleDatePanel();
};

window.saveDateEntry = function() {
    if (!datePanelTarget) { Toast.show('No target selected.', 'warn'); return; }
    const fd = new URLSearchParams();
    fd.append('action', datePanelTarget.type === 'scene' ? 'save_scene_date' : 'save_story_date');
    fd.append(datePanelTarget.type + '_id', datePanelTarget.id);
    fd.append('sort_start',  document.getElementById('dpSortStart').value  || '0');
    fd.append('sort_end',    document.getElementById('dpSortEnd').value    || '');
    fd.append('start_label', document.getElementById('dpLabelStart').value || '');
    fetch('timelines_api.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res => {
            if (res.success) { Toast.show('Date saved. Reload timeline to see changes.', 'success'); }
            else             { Toast.show(res.message || 'Save failed.', 'error'); }
        });
};

// ── Beat / PLUSH Editor Modal ─────────────────────────────────────────────
// Derives the story_id from currentSourceKey (story:N or collection:N).
// For collections the PLUSH editor loads via story_id=0 which shows the story
// list — user can then navigate in the iframe. If a story is selected directly
// we load plush.php?id=N so the editor opens on that story immediately.

function getPlushStoryId() {
    if (!currentSourceKey) return 0;
    const [kind, id] = currentSourceKey.split(':');
    return kind === 'story' ? parseInt(id, 10) : 0;
}

function buildPlushUrl(filterTerm) {
    const storyId = getPlushStoryId();
    let url = 'plush.php';
    if (storyId) {
        url += '?id=' + storyId;
        if (filterTerm && filterTerm.trim()) {
            // Pass filter as hash so plush.php can read it via JS without a page reload
            url += '#scene_filter=' + encodeURIComponent(filterTerm.trim());
        }
    }
    return url;
}

let beatModalOpen = false;

window.openBeatEditor = function() {
    if (currentMode !== 'plush') return;
    beatModalOpen = true;
    document.getElementById('beat-modal-backdrop').classList.add('active');

    const storyId = getPlushStoryId();
    const title   = storyId ? 'PLUSH Editor — Story #' + storyId : 'PLUSH Editor';
    document.getElementById('beatModalTitle').textContent = title;

    // Load the PLUSH iframe
    const iframe = document.getElementById('beat-plush-iframe');
    iframe.src = buildPlushUrl('');
};

window.closeBeatModal = function() {
    beatModalOpen = false;
    document.getElementById('beat-modal-backdrop').classList.remove('active');
    // Blank the iframe to stop any pending loads / free memory
    document.getElementById('beat-plush-iframe').src = 'about:blank';
};

// ── Beat modal iframe filter (called externally if ever needed) ───────────
// The PLUSH iframe carries its own scene filter input; no filter wiring needed here.

// On iframe load — nothing extra required; plush.php manages its own state.
document.getElementById('beat-plush-iframe').addEventListener('load', function() {});

// ── Helpers ───────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Init ──────────────────────────────────────────────────────────────────
setMode('plush');

})();
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php echo $eruda ?? ''; ?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>