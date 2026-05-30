<?php
// public/entity_viewer.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php'; // Access $entityIcons

use App\UI\Modules\ModuleRegistry;

// 1. Setup UI Modules
$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'character_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites', 'frames', 'videos'];

$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '1.5em',
    'show_for_entities' => $entities_with_menu,
]);
foreach ($entities_with_menu as $entity_name) { $gearMenu->addStandardActions($entity_name); }
$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// List of entities to show in dropdown
$availableEntities = [
    'characters', 'locations', 'backgrounds', 'animas', 
    'vehicles', 'artifacts', 'sketches', 'generatives', 'composites'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.8">
    <title>Entity Viewer</title>
    
    <script>
      (function() {
        try {
          var theme = localStorage.getItem('spw_theme');
          if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        } catch (e) {}
      })();
    </script>

    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    
    <!-- VideoJS -->
    <link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet" />
    <script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>

    <style>
        body { background: var(--bg); color: var(--text); padding-bottom: 100px; }
        
        /* Header */
        .viewer-header {
            position: sticky; top: 0; z-index: 100;
            background: var(--card); border-bottom: 1px solid var(--border);
            padding: 8px 12px;
            display: flex; gap: 8px; align-items: center; flex-wrap: nowrap;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow-x: auto; 
        }
        
        .header-title { margin: 0; font-size: 1.1rem; white-space: nowrap; margin-right: 5px; }
        
        /* Controls Container */
        .viewer-controls { display: flex; gap: 6px; align-items: center; flex: 1; min-width: 0; }
        
        .entity-select {
            padding: 6px 8px; border-radius: 6px; border: 1px solid var(--border); 
            background: var(--bg); color: var(--text); 
            min-width: 80px; max-width: 150px; flex: 1; 
            font-size: 13px; text-overflow: ellipsis;
        }

        .id-input {
            width: 70px; padding: 6px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--bg); color: var(--text); font-size: 13px; text-align: center;
        }

        .mode-switch {
            display: flex; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; flex-shrink: 0;
        }
        .mode-btn {
            padding: 6px 10px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 12px;
        }
        .mode-btn:hover { background: rgba(125,125,125,0.1); }
        .mode-btn.active {
            background: var(--accent); color: white; font-weight: 600;
        }
        
        .theme-btn { 
            margin-left: auto; padding: 6px; border-radius: 6px; background: transparent; border: 1px solid var(--border); color: var(--text); cursor: pointer; flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .header-title { display: none; }
            .id-input { width: 60px; }
            .entity-select { width: 100px; }
        }

        /* Content */
        #viewer-content { padding: 20px; max-width: 1600px; margin: 0 auto; }
        
        .gallery-container { margin-bottom: 40px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .gallery-meta { display: flex; justify-content: space-between; margin-bottom: 10px; align-items: center; }
        .gallery-title { font-size: 14px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        
        .scroll-magic-link { color: var(--text-muted); font-size: 1.2rem; cursor: pointer; margin-left: 10px; }
        .scroll-magic-link:hover { color: var(--accent); }

        /* Swiper / Cards */
        .frame-chain-swiper { width: 100%; padding: 10px 0; }
        .swiper-slide { width: 300px; display: flex; align-items: center; position: relative; }
        .swiper-slide:not(:last-child)::after { content: '→'; font-size: 24px; color: var(--text-muted); position: absolute; right: -25px; top: 50%; transform: translateY(-50%); z-index: 1; }

        .chain-card { 
            background: var(--card); border: 1px solid var(--border); border-radius: 8px; 
            overflow: visible !important;
            box-shadow: var(--card-elevation); width: 100%; display: flex; flex-direction: column; 
            transition: transform 0.2s; position: relative; 
        }
        .chain-card:hover { transform: translateY(-4px); }
        .chain-card-thumbnail { 
            position: relative; width: 100%; padding-top: 100%; background: var(--bg); 
            border-top-left-radius: 8px; border-top-right-radius: 8px; overflow: hidden;
        }
        .chain-card-thumbnail img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .chain-card-body { padding: 12px; flex-grow: 1; font-size: 13px; }
        .chain-card-title { font-weight: 600; color: var(--text); margin: 0 0 8px 0; font-size: 15px; }
        .chain-card-prompt { color: var(--text-muted); margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer; }
        
        /* Badges */
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; margin-right: 4px; margin-bottom: 4px; border: 1px solid transparent; }
        .badge-gray { background: rgba(100,100,100,0.1); color: var(--text-muted); border-color: var(--border); }
        .badge-blue { background: rgba(59,130,246,0.1); color: #3b82f6; border-color: rgba(59,130,246,0.2); }
        .badge-orange { background: rgba(245,159,11,0.1); color: #f59e0b; border-color: rgba(245,159,11,0.2); }
        .badge-meta { background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(168,85,247,0.1)); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.3); cursor: pointer; }
        
        /* Analysis Badge (New) */
        .badge-curator {
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(52,211,153,0.1));
            color: #10b981;
            border: 1px solid rgba(16,185,129,0.3);
            cursor: pointer;
        }
        .badge-curator:hover { background: rgba(16,185,129,0.15); }
        
        /* Pill Styles for Modal */
        .pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin-right: 4px; margin-bottom: 4px; color: var(--text); border: 1px solid transparent; }
        .pill-theme { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.1); }
        .pill-char { border-color: #f59e0b; color: #f59e0b; background: rgba(245,159,11,0.1); }

        /* Video */
        .video-run-grid { display: flex; flex-direction: row; gap: 15px; background: rgba(0,0,0,0.02); padding: 10px; border-radius: 8px; border: 1px solid var(--border); height: 450px; overflow: hidden; }
        .video-player-box { flex: 0 0 65%; background: #000; border-radius: 6px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; position: relative; }
        .video-js { width: 100%; height: 100%; }
        .video-playlist-strip { flex: 1; display: flex; flex-direction: row; flex-wrap: nowrap; align-content: flex-start; gap: 10px; overflow-x: auto; overflow-y: hidden; padding-bottom: 5px; }
        .video-thumb-card { flex: 0 0 160px; display: flex; flex-direction: column; background: var(--card); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; cursor: pointer; transition: all 0.2s; height: 140px; }
        .video-thumb-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .video-thumb-card.active { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59,130,246,0.3); }
        .vt-img { width: 100%; height: 90px; object-fit: cover; background: #000; }
        .vt-info { padding: 6px; font-size: 12px; }
        .vt-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
        .vt-dur { color: var(--text-muted); font-size: 10px; }
        
        /* Pagination */
        .pagination-container { display: flex; justify-content: center; align-items: center; gap: 12px; padding: 40px 0; }
        .pagination-btn { padding: 8px 16px; background: var(--card); border: 1px solid var(--border); color: var(--text); text-decoration: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .pagination-btn:hover { background: var(--bg-lighter); border-color: var(--accent); }
        .pagination-btn.disabled { opacity: 0.5; pointer-events: none; }
        
        .page-input-group { display: flex; align-items: center; gap: 6px; }
        .page-input { width: 60px; text-align: center; padding: 6px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text); font-weight: 600; }
        .page-input:focus { outline: none; border-color: var(--accent); }
        .page-total { font-size: 14px; color: var(--text-muted); }

        /* Modals */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
        .modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: var(--text-muted); }
        .modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; align-items: flex-start; }
        .modal-icon { font-size: 1.5em; margin-right: 12px; min-width: 30px; text-align: center; }
        .modal-info { flex: 1; }
        .modal-label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; display: block; }
        
        .curation-pre {
            white-space: pre-wrap; font-family: monospace; font-size: 0.9rem; background: rgba(0,0,0,0.03); padding: 10px; border-radius: 6px; border: 1px solid var(--border);
        }

        /* Gear Menu Fixes */

.sb-menu { position: absolute !important; }

        .gear-menu-btn { position: absolute; top: 8px; right: 8px; z-index: 50; cursor: pointer; background: rgba(255,255,255,0.9); border-radius: 4px; padding: 4px; line-height: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        [data-theme="dark"] .gear-menu-btn { background: rgba(0,0,0,0.6); color: #fff; }
    </style>
</head>
<body>

<div class="viewer-header">
    <h2 class="header-title">Entity Viewer</h2>
    <div class="viewer-controls">
        <select id="entitySelect" class="entity-select">
            <?php foreach ($availableEntities as $ent): 
                $icon = $entityIcons[$ent] ?? '📦';
                $label = ucfirst($ent);
            ?>
                <option value="<?= $ent ?>"><?= $icon ?> <?= $label ?></option>
            <?php endforeach; ?>
        </select>

        <input type="number" id="fromId" class="id-input" placeholder="From ID">
        <input type="number" id="toId" class="id-input" placeholder="To ID">
        
        <div class="mode-switch">
            <button class="mode-btn active" data-mode="entity">Ent.</button>
            <button class="mode-btn" data-mode="map_run">Map</button>
        </div>
        
        <button id="themeToggle" class="theme-btn" title="Toggle theme"><span id="themeIcon">🌙</span></button>
    </div>
</div>

<div id="viewer-content">
    <div style="text-align:center; padding:50px; color:var(--text-muted);">Loading...</div>
</div>

<div class="pagination-container" id="pagination">
    <!-- Populated by JS -->
</div>

<!-- Modals -->
<div id="meta-modal" class="modal-overlay"><div class="modal-content"><span class="modal-close" onclick="$('#meta-modal').hide()">&times;</span><h3 class="modal-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Ingredients</h3><div id="meta-modal-body"></div></div></div>
<div id="analysis-modal" class="modal-overlay"><div class="modal-content"><span class="modal-close" onclick="$('#analysis-modal').hide()">&times;</span><h3 class="modal-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Analysis & Curation</h3><div id="analysis-modal-body"></div></div></div>
<div id="desc-modal" class="modal-overlay"><div class="modal-content"><span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span><h3 class="modal-title" style="margin-top:0;">Description</h3><div id="desc-modal-body" style="white-space: pre-wrap; font-family: monospace; font-size:0.9rem;"></div></div></div>

<!-- UI Components -->
<?= $eruda ?? '' ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>

<!-- PhotoSwipe Modules -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.initLightbox = () => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery',
            children: '.pswp-gallery-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    };
</script>

<script>
$(function() {
    let currentPage = 1;
    
    // Config via UI
    function getConfig() {
        return {
            entity_type: $('#entitySelect').val(),
            mode: $('.mode-btn.active').data('mode'),
            page: currentPage,
            from_id: $('#fromId').val(),
            to_id: $('#toId').val()
        };
    }

    function loadContent() {
        const conf = getConfig();
        $('#viewer-content').html('<div style="text-align:center; padding:50px; color:var(--text-muted);">Loading...</div>');
        
        const url = new URL(window.location);
        url.searchParams.set('type', conf.entity_type);
        url.searchParams.set('mode', conf.mode);
        url.searchParams.set('page', conf.page);
        if(conf.from_id) url.searchParams.set('from', conf.from_id); else url.searchParams.delete('from');
        if(conf.to_id) url.searchParams.set('to', conf.to_id); else url.searchParams.delete('to');
        
        window.history.replaceState({}, '', url);

        $.post('entity_viewer_api.php?action=fetch_content', conf, function(res) {
            if(res.ok) {
                renderGalleries(res.items);
                renderPagination(res.current_page, res.total_pages);
            } else {
                Toast.show('Error: ' + res.error, 'error');
            }
        }, 'json').fail(() => Toast.show('Network Error', 'error'));
    }

    function renderGalleries(items) {
        if (!items || items.length === 0) {
            $('#viewer-content').html('<div style="text-align:center; padding:50px; color:var(--text-muted);">No items found.</div>');
            return;
        }

        let html = '<div class="pswp-gallery">';
        items.forEach(item => {
            html += `
            <div class="gallery-container">
                <div class="gallery-meta">
                    <span class="gallery-title">${item.title}</span>
                    <small style="color:var(--text-muted);">${item.meta}</small>
                </div>`;

            if (item.type === 'video' && item.videos.length > 0) {
                const firstVideo = item.videos[0];
                const cleanUrl = firstVideo.url.replace(/^\//, '');
                html += `
                <div class="video-run-grid">
                    <div class="video-player-box">
                        <video id="player-${item.id}" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto">
                            <source src="${cleanUrl}" type="video/mp4" />
                        </video>
                    </div>
                    <div class="video-playlist-strip">`;
                item.videos.forEach((vid, idx) => {
                    const vUrl = vid.url.replace(/^\//, '');
                    const thumb = (vid.thumbnail || '').replace(/^\//, '');
                    html += `<div class="video-thumb-card ${idx===0?'active':''}" onclick="changeVideo('${item.id}', '${vUrl}', this)">
                            <img src="${thumb}" class="vt-img" loading="lazy">
                            <div class="vt-info"><div class="vt-title">${vid.name}</div></div>
                        </div>`;
                });
                html += `</div></div>`;
            } else if (item.frames.length > 0) {
                html += `<div class="swiper frame-chain-swiper" id="swiper-${item.id}"><div class="swiper-wrapper">`;
                item.frames.forEach(frame => {
                    const img = (frame.filename || '').replace(/^\//, '');
                    const promptText = (frame.full_sketch_desc || frame.prompt || '').replace(/"/g, '&quot;');
                    
                    let entType = frame.entity_type || item.item_type || 'frames';
                    let entId = frame.entity_id || item.id || 0;
                    if(entType === 'map_run') entType = 'frames'; 
                    
                    const gearAttr = `data-gear-menu data-entity="${entType}" data-entity-id="${entId}" data-frame-id="${frame.frame_id}" data-img-url="${img}"`;
                    
                    const metaHtml = (frame.normalized_ingredients && frame.normalized_ingredients.length > 0)
                        ? `<span class="badge badge-meta meta-pill-trigger" data-ingredients='${JSON.stringify(frame.normalized_ingredients).replace(/'/g, "&#39;")}'>Meta</span>`
                        : '';
                    
                    // Curation Badge Rendering (FIXED)
                    const curationHtml = (frame.curation) 
                        ? `<span class="badge badge-curator curation-pill-trigger" data-curation='${JSON.stringify(frame.curation).replace(/'/g, "&#39;")}'>Analysis (${frame.curation.score})</span>`
                        : '';

                    html += `
                    <div class="swiper-slide">
                        <div class="chain-card" ${gearAttr}>
                            <div class="chain-card-thumbnail">
                                <a href="${img}" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                    <img src="${img}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                </a>
                            </div>
                            <div class="chain-card-body">
                                <h3 class="chain-card-title">Frame #${frame.frame_id}</h3>
                                <div style="margin-bottom:8px;">${metaHtml} ${curationHtml}</div>
                                <p class="chain-card-prompt full-desc-trigger" data-full-desc="${promptText}">${(frame.prompt||'').substring(0,50)}...</p>
                            </div>
                        </div>
                    </div>`;
                });
                html += `</div><div class="swiper-button-next"></div><div class="swiper-button-prev"></div><div class="swiper-scrollbar"></div></div>`;
            } else {
                html += `<div style="padding:20px; text-align:center; color:var(--text-muted); background:rgba(0,0,0,0.02); border-radius:8px;">No content found.</div>`;
            }
            html += `</div>`;
        });
        html += '</div>';
        
        $('#viewer-content').html(html);
        initComponents();
    }

    function renderPagination(curr, total) {
        if (total <= 1) { $('#pagination').html(''); return; }
        
        let html = `<button class="pagination-btn" onclick="changePage(${curr-1})" ${curr<=1?'disabled':''}>Previous</button>`;
        
        html += `<div class="page-input-group">
                    <input type="number" class="page-input" value="${curr}" min="1" max="${total}" onchange="changePage(this.value)">
                    <span class="page-total">/ ${total}</span>
                 </div>`;
        
        html += `<button class="pagination-btn" onclick="changePage(${curr+1})" ${curr>=total?'disabled':''}>Next</button>`;
        
        $('#pagination').html(html);
    }

    // --- Helpers ---
    function initComponents() {
        document.querySelectorAll('.frame-chain-swiper').forEach(el => {
            new Swiper(el, {
                slidesPerView: 'auto', spaceBetween: 40, freeMode: true,
                navigation: { nextEl: el.querySelector('.swiper-button-next'), prevEl: el.querySelector('.swiper-button-prev') },
                scrollbar: { el: el.querySelector('.swiper-scrollbar'), hide: true },
                slidesOffsetBefore: 20, slidesOffsetAfter: 20
            });
        });
        document.querySelectorAll('.video-js').forEach(el => {
            if(!el.player) videojs(el, { controls: true, preload: 'auto', fill: true });
        });
        if(window.initLightbox) window.initLightbox();
        setTimeout(() => { if(window.GearMenu) window.GearMenu.attach(document.getElementById('viewer-content')); }, 100);
    }

    window.changePage = function(p) { 
        const pageNum = parseInt(p);
        if(!isNaN(pageNum)) {
            currentPage = pageNum; 
            loadContent(); 
            window.scrollTo(0,0);
        }
    };
    
    window.changeVideo = function(id, url, card) {
        const pId = 'player-'+id; const p = document.getElementById(pId);
        $(card).siblings().removeClass('active'); $(card).addClass('active');
        if(p) { const v=videojs.getPlayer(pId); if(v){ v.src({type:'video/mp4',src:url}); v.play(); } else { p.src=url; p.play(); } }
    };

    // --- Events ---
    $('#entitySelect').change(() => { currentPage = 1; loadContent(); });
    $('#fromId, #toId').change(() => { currentPage = 1; loadContent(); }); // Reload on ID change
    $('.mode-btn').click(function() { 
        $('.mode-btn').removeClass('active'); $(this).addClass('active'); 
        currentPage = 1; loadContent(); 
    });

    // --- Restore URL Params ---
    const url = new URL(window.location);
    const uType = url.searchParams.get('type');
    const uMode = url.searchParams.get('mode');
    const uPage = url.searchParams.get('page');
    const uFrom = url.searchParams.get('from');
    const uTo = url.searchParams.get('to');
    
    if(uType) $('#entitySelect').val(uType);
    if(uMode) { $('.mode-btn').removeClass('active'); $(`.mode-btn[data-mode="${uMode}"]`).addClass('active'); }
    if(uPage) currentPage = parseInt(uPage);
    if(uFrom) $('#fromId').val(uFrom);
    if(uTo) $('#toId').val(uTo);

    // Initial Load
    loadContent();

    document.getElementById('themeToggle').addEventListener('click', function(){
        const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = (cur === 'dark') ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('spw_theme', next);
        document.getElementById('themeIcon').textContent = next === 'dark' ? '☀️' : '🌙';
    });
    
    // Modal Listeners
    $(document).on('click', '.meta-pill-trigger', function(e) {
        e.stopPropagation();
        const raw = this.dataset.ingredients;
        if (!raw) return;
        const ingredients = JSON.parse(raw);
        const body = document.getElementById('meta-modal-body');
        let html = '';
        const getIcon = (type) => { if (!type) return '📦'; if (type.includes('character')) return '🦸'; if (type.includes('location')) return '🗺️'; if (type.includes('template')) return '🎬'; if (type.includes('interaction')) return '🤝'; if (type.includes('style')) return '🎨'; if (type.includes('generator')) return '⚡'; if (type.includes('anivoc')) return '📘'; return '📦'; };
        ingredients.forEach(ing => { const icon = getIcon(ing.type); html += `<div class="modal-row"><div class="modal-icon">${icon}</div><div class="modal-info"><span class="modal-label">${ing.label}</span>${ing.detail ? `<span class="modal-detail">${ing.detail.substring(0, 150)}${ing.detail.length>150?'...':''}</span>` : ''}</div></div>`; });
        body.innerHTML = html; $('#meta-modal').css('display', 'flex');
    });

    $(document).on('click', '.curation-pill-trigger', function(e) {
        e.stopPropagation();
        const raw = this.dataset.curation;
        if (!raw) return;
        const data = JSON.parse(raw);
        const body = document.getElementById('analysis-modal-body');
        
        let html = `
            <div style="margin-bottom:15px;">
                <div class="score-badge score-high" style="display:inline-block; padding:4px 10px; background:#10b981; color:white; border-radius:6px; font-weight:800; font-size:1.2em; margin-right:10px;">${data.score}</div>
                <strong style="font-size:1.1em;">Overall Quality</strong>
            </div>
        `;
        
        if(data.class) {
            if(data.class.narrative_function) html += `<div class="modal-row"><span class="modal-label">Function</span><span class="modal-value">${data.class.narrative_function}</span></div>`;
            if(data.class.emotional_tone) html += `<div class="modal-row"><span class="modal-label">Tone</span><span class="modal-value">${data.class.emotional_tone}</span></div>`;
        }
        
        // Themes
        if (data.themes && data.themes.primary_themes) {
            html += `<div class="modal-row"><span class="modal-label">Themes</span><div style="margin-top:4px;">`;
            let themes = Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes : [data.themes.primary_themes];
            themes.forEach(t => html += `<span class="pill pill-theme">${t}</span> `);
            html += `</div></div>`;
        }
        
        // Characters
        if (data.entities) {
             if(data.entities.characters && data.entities.characters.length > 0) {
                html += `<div class="modal-row"><span class="modal-label">Characters</span><div style="margin-top:4px;">`;
                data.entities.characters.forEach(c => html += `<span class="pill pill-char">${c}</span> `);
                html += `</div></div>`;
             }
        }
        
        body.innerHTML = html;
        $('#analysis-modal').css('display', 'flex');
    });

    $(document).on('click', '.full-desc-trigger', function(e) {
        e.stopPropagation(); $('#desc-modal-body').text(this.dataset.fullDesc); $('#desc-modal').css('display', 'flex');
    });
    
    window.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) $(e.target).hide(); });
});
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
<?php require_once "forge_tool.php"; ?>
</body>
</html>