<?php
// public/sketches_viewer.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\ModuleRegistry;

// -----------------------------------------------------------
// 1. Setup UI Modules & Gear Menu
// -----------------------------------------------------------
$registry = ModuleRegistry::getInstance();

// We need menus for both Sketches (the container) and Frames (the visual items)
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '1.5em',
    'show_for_entities' => ['sketches', 'frames'],
]);

// Register standard actions for BOTH
$gearMenu->addStandardActions('sketches');
$gearMenu->addStandardActions('frames');

$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.8">
    <title>Semantic Sketch Viewer</title>
    
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
    
    <!-- Swiper & PhotoSwipe -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    
    <style>
        body { background: var(--bg); color: var(--text); padding-bottom: 100px; }
        
        /* --- Search Header --- */
        .search-header {
            position: sticky; top: 0; z-index: 100;
            background: var(--card); border-bottom: 1px solid var(--border);
            padding: 15px 20px;
            display: flex; gap: 15px; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .search-container {
            display: flex; align-items: center; background: var(--bg);
            border: 2px solid var(--border); border-radius: 50px;
            padding: 5px 15px; width: 73%; max-width: 800px;
            transition: border-color 0.2s; position: relative;
            margin-left:50px !important;
        }
        .search-container:focus-within { border-color: var(--accent); }

        .search-icon { font-size: 1.2rem; color: var(--text-muted); margin-right: 10px; }
        
        .search-input {
            border: none; background: transparent; color: var(--text);
            font-size: 1.1rem; flex: 1; padding: 10px; outline: none;
        }

        .img-search-btn {
            background: transparent; border: none; cursor: pointer;
            font-size: 1.3rem; padding: 5px 10px; opacity: 0.6; transition: opacity 0.2s;
        }
        .img-search-btn:hover { opacity: 1; transform: scale(1.1); }

        /* Image Preview in Search Bar */
        .search-img-preview {
            display: none; width: 40px; height: 40px; border-radius: 6px; 
            object-fit: cover; margin-right: 10px; border: 1px solid var(--border);
        }
        .search-img-preview.active { display: block; }
        .search-img-clear {
            display: none; cursor: pointer; color: #ef4444; font-weight: bold; margin-left: -8px; margin-right: 8px;
        }
        .search-img-clear.active { display: block; }

        /* Action Buttons */
        .header-actions { display: flex; gap: 10px; }
        .action-btn {
            background: var(--bg); border: 1px solid var(--border); color: var(--text);
            padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 0.9rem;
        }
        .action-btn:hover { background: var(--card); border-color: var(--accent); }

        /* --- Content Grid --- */
        #viewer-content { padding: 20px; max-width: 1600px; margin: 0 auto; }
        
        .gallery-container { 
            background: var(--card); border: 1px solid var(--border); border-radius: 12px;
            margin-bottom: 30px; padding: 0; overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            position: relative; /* For Sketch Gear Menu */
        }
        
        .gallery-header {
            padding: 15px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: flex-start;
            background: rgba(0,0,0,0.02);
            position: relative;
            padding-right: 40px; /* Space for Gear Menu */
        }

        .sketch-title { margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text); }
        .sketch-meta { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }
        
        .score-badge { 
            font-weight: 800; padding: 4px 8px; border-radius: 6px; color: #fff; 
            text-shadow: 0 1px 2px rgba(0,0,0,0.3); font-size: 1rem;
        }
        .score-high { background: #10b981; } 
        .score-mid { background: #f59e0b; }
        .score-low { background: #ef4444; }

        .analysis-pills { margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }
        .pill { font-size: 0.75rem; padding: 2px 8px; border-radius: 12px; background: rgba(0,0,0,0.05); color: var(--text-muted); border: 1px solid transparent; }
        .pill-theme { color: var(--accent); background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.2); }
        .pill-func { color: #8b5cf6; background: rgba(139,92,246,0.1); border-color: rgba(139,92,246,0.2); }

        /* Swiper */
        .frame-chain-swiper { width: 100%; padding: 15px 0; background: var(--bg); }
        .swiper-slide { width: 220px; }
        
        .chain-card {
            background: var(--card); border: 1px solid var(--border); border-radius: 8px;
            overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s; position: relative;
        }
        .chain-card:hover { transform: translateY(-3px); border-color: var(--accent); }
        
        .thumb-wrapper { position: relative; width: 100%; padding-top: 100%; background: #000; }
        .thumb-wrapper img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        
        .card-info { padding: 8px; font-size: 11px; }
        .card-prompt { color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        /* Distance Score (Search Result) */
        .dist-score {
            position: absolute; top: 6px; right: 6px; 
            background: rgba(0,0,0,0.7); color: #fff; 
            padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;
            backdrop-filter: blur(2px); z-index: 10;
        }

        /* Load More */
        .pagination-container { text-align: center; padding: 40px; }
        .load-more-btn {
            padding: 10px 30px; background: var(--card); border: 1px solid var(--border);
            border-radius: 20px; cursor: pointer; font-size: 1rem; color: var(--text);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.2s;
        }
        .load-more-btn:hover { border-color: var(--accent); transform: translateY(-2px); }

        /* Modals */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-content { background: var(--card); padding: 25px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .modal-close { position: absolute; top: 15px; right: 15px; font-size: 24px; cursor: pointer; color: var(--text-muted); transition: color 0.2s; }
        .modal-close:hover { color: var(--text); }
        
        /* Gear Menu Override */
        /* For Frames */
        .chain-card .gear-menu-btn { position: absolute; top: 6px; right: 6px; z-index: 50; cursor: pointer; background: rgba(255,255,255,0.9); border-radius: 4px; padding: 3px; line-height: 1; }
        /* For Sketches (Header) */
        .gallery-header .gear-menu-btn { 
            position: absolute; top: 15px; right: 15px; 
            background: var(--bg); border: 0px solid var(--border); 
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; cursor: pointer; z-index: 50;
        }
        .gallery-header .gear-menu-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

.sb-menu { position: absolute !important; }

    </style>
</head>
<body>

<div class="search-header">
    <div class="search-container">
        <span class="search-icon">🔍</span>
        
        <img id="searchImgPreview" class="search-img-preview">
        <span id="searchImgClear" class="search-img-clear" title="Remove image">✖</span>
        
        <input type="text" id="searchInput" class="search-input" placeholder="Search sketches (e.g. 'cyberpunk market', 'sad robot')...">
        
        <button class="img-search-btn" onclick="document.getElementById('imgUploadInput').click()" title="Search by Image">📷</button>
        <input type="file" id="imgUploadInput" accept="image/*" style="display:none">
    </div>

    <div class="header-actions">
        <button class="action-btn" onclick="resetSearch()">Reset</button>
        <button style="display:none;" class="action-btn" id="themeToggle">🌙</button>
    </div>
</div>

<div id="viewer-content">
    <div style="text-align:center; padding:50px; color:var(--text-muted);">
        Waiting for input... <br> (Or scroll down for recent sketches)
    </div>
</div>

<div class="pagination-container">
    <button id="loadMoreBtn" class="load-more-btn" onclick="loadNextPage()">Load More</button>
</div>

<!-- Hidden Modals -->
<div id="desc-modal" class="modal-overlay"><div class="modal-content"><span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span><h3 style="margin-top:0">Full Description</h3><div id="desc-modal-body" style="white-space: pre-wrap; font-family: monospace; font-size:0.9rem;"></div></div></div>

<!-- Scripts -->
<?= $eruda ?? '' ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>

<!-- PhotoSwipe -->
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
    let isSearching = false;
    let searchDebounce;
    let selectedImageFile = null;

    // --- Core Loader ---
    function loadContent(reset = false) {
        if (reset) {
            $('#viewer-content').html('<div style="text-align:center; padding:50px; color:var(--text-muted);">Loading...</div>');
            currentPage = 1;
        }

        const formData = new FormData();
        formData.append('action', 'fetch');
        formData.append('page', currentPage);
        
        // Add Search Params
        const textQuery = $('#searchInput').val().trim();
        if (textQuery || selectedImageFile) {
            isSearching = true;
            formData.append('search_mode', 'true');
            if (textQuery) formData.append('query', textQuery);
            if (selectedImageFile) formData.append('image', selectedImageFile);
        } else {
            isSearching = false;
        }

        $.ajax({
            url: 'sketches_viewer_api.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.ok) {
                    renderItems(res.items, reset);
                    
                    if (res.items.length < 10) {
                        $('#loadMoreBtn').hide();
                    } else {
                        $('#loadMoreBtn').show();
                        $('#loadMoreBtn').text(isSearching ? 'Load More Matches' : 'Load More Sketches');
                    }
                } else {
                    Toast.show('Error: ' + res.error, 'error');
                }
            },
            error: function() {
                Toast.show('Network Error', 'error');
            }
        });
    }

    // --- Renderer ---
    function renderItems(items, replace) {
        if (!items || items.length === 0) {
            if (replace) $('#viewer-content').html('<div style="text-align:center; padding:50px;">No results found.</div>');
            return;
        }

        let html = '';
        items.forEach(item => {
            // 1. Sketch Gear Menu Attribute
            const sketchGearAttr = `data-gear-menu data-entity="sketches" data-entity-id="${item.id}"`;

            // 2. Badges
            let scoreBadge = '';
            if (item.quality > 0) {
                let sClass = item.quality >= 8 ? 'score-high' : (item.quality >= 5 ? 'score-mid' : 'score-low');
                scoreBadge = `<span class="score-badge ${sClass}">${item.quality}</span>`;
            }
            
            // 3. Pills
            let pills = '';
            if (item.narrative) pills += `<span class="pill pill-func">${item.narrative}</span>`;
            if (item.themes && Array.isArray(item.themes)) {
                item.themes.slice(0, 3).forEach(t => pills += `<span class="pill pill-theme">${t}</span>`);
            }

            // 4. Frames Swiper
            let swiperHtml = '';
            if (item.frames && item.frames.length > 0) {
                swiperHtml = `<div class="swiper frame-chain-swiper"><div class="swiper-wrapper ps-gallery pswp-gallery">`;
                item.frames.forEach(f => {
                    const img = (f.filename || '').replace(/^\//, '');
                    const frameGearAttr = `data-gear-menu data-entity="frames" data-entity-id="${f.frame_id}" data-frame-id="${f.frame_id}" data-img-url="${img}"`;
                    
                    // Show Distance if searching
                    let distHtml = '';
                    if (f.distance !== undefined && f.distance !== null) {
                        const score = (1 - f.distance).toFixed(2); 
                        distHtml = `<span class="dist-score">Sim: ${score}</span>`;
                    }

                    swiperHtml += `
                    <div class="swiper-slide">
                        <div class="chain-card" ${frameGearAttr}>
                            <div class="gear-menu-btn"></div>
                            <div class="thumb-wrapper">
                                ${distHtml}
                                <a href="${img}" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                    <img src="${img}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                </a>
                            </div>
                            <div class="card-info">
                                <div style="font-weight:700; margin-bottom:2px;">#${f.frame_id}</div>
                                <div class="card-prompt full-desc-trigger" data-desc="${(f.prompt||'').replace(/"/g, '&quot;')}">${(f.prompt||'')}</div>
                            </div>
                        </div>
                    </div>`;
                });
                swiperHtml += `</div><div class="swiper-scrollbar"></div></div>`;
            } else {
                swiperHtml = `<div style="padding:15px; color:var(--text-muted); font-size:0.9rem;">No frames linked.</div>`;
            }

            // 5. Main Container Construction
            html += `
            <div class="gallery-container">
                <div class="gallery-header">
                    <!--
                    <div style="display:none;" class="gear-menu-btn" title="Sketch Actions"></div>
                    -->
                    <div>
                        <div style="display:flex; gap:10px; align-items:center;">
                            ${scoreBadge}
                            <h3 class="sketch-title">${item.name} <small style="font-weight:400; color:var(--text-muted);">#${item.id}</small></h3>
                        </div>
                        <div class="analysis-pills">${pills}</div>
                        <div class="sketch-meta full-desc-trigger" data-desc="${(item.description||'').replace(/"/g, '&quot;')}" style="cursor:pointer; margin-top:8px;">
                            ${(item.description||'').substring(0, 150)}...
                        </div>
                    </div>
                </div>
                ${swiperHtml}
            </div>`;
        });

        if (replace) {
            $('#viewer-content').html(html);
        } else {
            $('#viewer-content').append(html);
        }
        
        initComponents();
    }

    function initComponents() {
        document.querySelectorAll('.frame-chain-swiper').forEach(el => {
            if (el.swiper) return;
            new Swiper(el, {
                slidesPerView: 'auto', spaceBetween: 15, freeMode: true,
                scrollbar: { el: el.querySelector('.swiper-scrollbar'), hide: true },
                slidesOffsetBefore: 15, slidesOffsetAfter: 15
            });
        });
        if(window.initLightbox) window.initLightbox();
        
        // Re-attach Gear Menus
        setTimeout(() => { 
            if(window.GearMenu) {
                // We attach to viewer-content, it finds all [data-gear-menu] inside
                window.GearMenu.attach(document.getElementById('viewer-content')); 
            }
        }, 100);
    }

    // --- Search Handlers ---
    $('#searchInput').on('input', function() {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => loadContent(true), 500); 
    });

    $('#imgUploadInput').on('change', function(e) {
        if (this.files && this.files[0]) {
            selectedImageFile = this.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#searchImgPreview').attr('src', e.target.result).addClass('active');
                $('#searchImgClear').addClass('active');
            }
            reader.readAsDataURL(selectedImageFile);
            loadContent(true); 
        }
    });

    $('#searchImgClear').on('click', function() {
        selectedImageFile = null;
        $('#imgUploadInput').val('');
        $('#searchImgPreview').removeClass('active').attr('src', '');
        $(this).removeClass('active');
        loadContent(true);
    });

    window.resetSearch = function() {
        $('#searchInput').val('');
        $('#searchImgClear').click();
    };

    window.loadNextPage = function() {
        currentPage++;
        loadContent(false);
    };

    // --- Modals ---
    $(document).on('click', '.full-desc-trigger', function() {
        $('#desc-modal-body').text($(this).data('desc'));
        $('#desc-modal').css('display', 'flex');
    });
    window.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) $(e.target).hide(); });

    // --- Theme ---
    $('#themeToggle').on('click', function() {
        const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = (cur === 'dark') ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('spw_theme', next);
    });

    // Initial
    loadContent(true);
});
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>