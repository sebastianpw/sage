<?php
// src/Gallery/WallOfImagesGallery.php
namespace App\Gallery;

require_once "AbstractNuGallery.php";

class WallOfImagesGallery extends AbstractNuGallery {
    
    // Config
    public const IMAGE_SIZE = 122;
    public const COLUMNS = 5;
    public const ROWS = 10;
    public const TOP_PADDING = 0;

    protected function getLimit(): int { 
        return self::COLUMNS * self::ROWS; 
    }
    
    protected function getFiltersFromRequest(): array { 
        return []; 
    }
    
    protected function getFilterOptions(): array { 
        return []; 
    }
    
    protected function getWhereClause(): string { 
        return ''; 
    }
    
    protected function getGalleryUrl(): string { 
        return 'wall_of_images.php'; 
    }
    
    protected function showFloatool(): bool { 
        return false; 
    }
    
    protected function getBaseQuery(): string { 
        return "v_gallery_wall_of_images"; 
    }

    protected function getOrderBy(): string {
        if (!isset($_SESSION['gallery_seed'])) {
            $_SESSION['gallery_seed'] = mt_rand(1, 1000000);
        }
        return "RAND(" . (int)$_SESSION['gallery_seed'] . ")";
    }

    public function resetSeed(): void { 
        $_SESSION['gallery_seed'] = mt_rand(1, 1000000); 
    }
    
    protected function getCaptionFields(): array { 
        return []; 
    }
    
    protected function getGalleryEntity(): string { 
        return "wall_of_images"; 
    }
    
    protected function getGalleryTitle(): string { 
        return "Wall of Images"; 
    }
    
    protected function getToggleButtonLeft(): int { 
        return 0; 
    }

    /**
     * Override renderItem for wall-specific layout
     */
    protected function renderItem(array $row): string {
        $imgSrcWeb = '/' . ltrim($row['filename'] ?? '', "/");
        $title = htmlspecialchars($row['entity_name'] ?? '') . 
                 ($row['prompt'] ? ': ' . htmlspecialchars($row['prompt']) : '');
        
        $entity = $row['entity_type'] ?? 'unknown';
        $entityId = (int)($row['entity_id'] ?? 0);
        $frameId = (int)($row['frame_id'] ?? 0);
        
        ob_start();
        ?>
        <div class="wall-item" data-entity="<?= $entity ?>" data-entity-id="<?= $entityId ?>" data-frame-id="<?= $frameId ?>">
            <a href="<?= htmlspecialchars($imgSrcWeb) ?>" 
               class="pswp-gallery-item" 
               data-pswp-src="<?= self::URL_PREFIX . htmlspecialchars($imgSrcWeb) ?>" 
               data-pswp-width="1024" 
               data-pswp-height="1024" 
               title="<?= $title ?>">
                <img src="<?= self::URL_PREFIX . htmlspecialchars($imgSrcWeb) ?>" alt="">
            </a>
        </div>
        <?php 
        return ob_get_clean();
    }

    /**
     * Override render for wall-specific layout
     */
    public function render(): string {
        if (isset($_GET['ajax_gallery'])) {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = $this->getLimit();
            $offset = ($page - 1) * $limit;
            
            $result = $this->mysqli->query("SELECT * FROM {$this->getBaseQuery()} ORDER BY {$this->getOrderBy()} LIMIT $limit OFFSET $offset");
            $itemsHtml = '';
            while ($row = $result->fetch_assoc()) {
                $itemsHtml .= $this->renderItem($row);
            }
            
            $count = $this->mysqli->query("SELECT COUNT(*) AS total FROM {$this->getBaseQuery()}");
            $totalPages = (int)ceil($count->fetch_assoc()['total'] / $limit);
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'page' => $page,
                'itemsHtml' => $itemsHtml,
                'totalPages' => $totalPages
            ]);
            exit;
        }

        $count = $this->mysqli->query("SELECT COUNT(*) AS total FROM {$this->getBaseQuery()}");
        $totalPages = max(1, (int)ceil($count->fetch_assoc()['total'] / $this->limit));
        
        $result = $this->mysqli->query("SELECT * FROM {$this->getBaseQuery()} ORDER BY {$this->getOrderBy()} LIMIT {$this->limit} OFFSET {$this->offset}");
        $itemsHtml = '';
        while ($row = $result->fetch_assoc()) {
            $itemsHtml .= $this->renderItem($row);
        }

        ob_start();
        ?>
        <div class="wall-container">
            <div class="swiper" id="wallSwiper">
                <div class="swiper-wrapper">
                    <div class="swiper-slide" data-page="<?= $this->page ?>" data-loaded="1">
                        <div class="wall-grid pswp-gallery"><?= $itemsHtml ?></div>
                    </div>
                    <?php for ($p = 1; $p <= $totalPages; $p++): if ($p == $this->page) continue; ?>
                        <div class="swiper-slide" data-page="<?= $p ?>" data-loaded="0">
                            <div class="wall-grid pswp-gallery">
                                <div class="page-loading">Loading...</div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
            <?= $this->renderWallStyles() ?>
            <?= $this->renderWallJS() ?>
        </div>
        <?php 
        return ob_get_clean();
    }

    protected function renderWallStyles(): string {
        ob_start();
        ?>
        <style>
        body { background: #000; margin: 0; }
        .wall-container { padding-top: <?= self::TOP_PADDING ?>px; background: #000; }
        .wall-grid {
            display: grid;
            grid-template-columns: repeat(<?= self::COLUMNS ?>, <?= self::IMAGE_SIZE ?>px);
            grid-auto-rows: <?= self::IMAGE_SIZE ?>px;
            gap: 0;
            justify-content: center;
        }
        .wall-item, .wall-item a, .wall-item img {
            display: block;
            width: <?= self::IMAGE_SIZE ?>px;
            height: <?= self::IMAGE_SIZE ?>px;
            object-fit: cover;
        }
        .page-loading {
            color: #666;
            text-align: center;
            padding: 50px;
        }
        
        /* PhotoSwipe gear menu styles */
        .pswp-gear-menu {
            position: absolute;
            z-index: 9999;
            min-width: 240px;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.35);
            overflow: hidden;
            background: rgba(26, 26, 26, 0.98);
            color: #fff;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        .pswp-gear-menu-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.15s;
        }
        .pswp-gear-menu-item:hover {
            background: rgba(0, 255, 255, 0.1);
            color: rgba(0, 255, 255, 1);
        }
        .pswp-gear-menu-item:last-child {
            border-bottom: none;
        }
        .pswp-gear-menu-separator {
            height: 1px;
            background: rgba(255,255,255,0.15);
            margin: 4px 0;
        }
        .pswp__button--custom {
            background: none !important;
            font-size: 28px !important;
            width: 44px !important;
            height: 44px !important;
        }
        
        /* Storyboard submenu */
        .sb-menu {
            position: fixed;
            z-index: 20000;
            background: rgba(26, 26, 26, 0.98);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 220px;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        .sb-menu-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: background 0.2s;
            color: #fff;
        }
        .sb-menu-item:hover {
            background: rgba(0, 255, 255, 0.1);
            color: rgba(0, 255, 255, 1);
        }
        .sb-menu-sep {
            height: 1px;
            background: rgba(255,255,255,0.15);
            margin: 4px 0;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    protected function renderWallJS(): string {
        ob_start();
        ?>
        <link rel="stylesheet" href="/css/toast.css">
        <script src="/js/toast.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <?php if (\App\Core\SpwBase::CDN_USAGE): ?>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
            <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
        <?php else: ?>
            <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
            <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
            <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
        <?php endif; ?>

        <?php if (\App\Core\SpwBase::CDN_USAGE): ?>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
            <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
        <?php else: ?>
            <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
            <script src="/vendor/swiper/swiper-bundle.min.js"></script>
        <?php endif; ?>

        <script>
        (function(){
            let swiper = null;
            let pswpLightbox = null;

            function initPS() {
                if (pswpLightbox) {
                    try { pswpLightbox.destroy(); } catch(e){}
                }
                
                pswpLightbox = new PhotoSwipeLightbox({
                    gallery: '.pswp-gallery',
                    children: 'a.pswp-gallery-item',
                    pswpModule: PhotoSwipe
                });

                // Add PhotoSwipe UI buttons that use the modular gear menu system
                pswpLightbox.on('uiRegister', function() {
                    const pswp = pswpLightbox.pswp;
                    
                    // 🎯 Frame Details button
                    pswp.ui.registerElement({
                        name: 'details',
                        order: 9,
                        isButton: true,
                        html: '<button class="pswp__button pswp__button--custom" title="Frame Details">🎯</button>',
                        onClick: (e, el) => {
                            e.preventDefault();
                            const wrapper = pswp.currSlide.data.element?.closest('.wall-item');
                            if (!wrapper) return;
                            const frameId = wrapper.dataset.frameId;
                            if (frameId && typeof window.showFrameDetailsModal === 'function') {
                                window.showFrameDetailsModal(frameId, 0.7);
                            }
                        }
                    });
                    
                    
                    
                    
                    
                    
     

                    // ⚙️ Gear Menu button - delegates to GearMenuModule
                    pswp.ui.registerElement({
                        name: 'gear',
                        order: 10,
                        isButton: true,
                        html: '<button class="pswp__button pswp__button--custom" title="Actions">⚙️</button>',
                        onClick: (e, el) => {
                            e.preventDefault();
                            const wrapper = pswp.currSlide.data.element?.closest('.wall-item');
                            if (!wrapper) return;
                            
                            // MODIFICATION:
                            // The old `GearMenu.open()` call failed because no .gear-icon exists on the wall-item.
                            // The new approach gets the action data from the module and uses the local
                            // PhotoSwipe-specific menu renderer.
                            if (window.GearMenu && typeof window.GearMenu.getActionsFor === 'function') {
                                const menuItems = window.GearMenu.getActionsFor(wrapper);
                                const entity = wrapper.dataset.entity;
                                const entityId = wrapper.dataset.entityId;
                                const frameId = wrapper.dataset.frameId;
                                showPhotoSwipeGearMenu(pswp, el, menuItems, wrapper, entity, entityId, frameId);
                            }
                        }
                    });

                    

                    /*
                    // ⚙️ Gear Menu button - delegates to GearMenuModule
                    pswp.ui.registerElement({
                        name: 'gear',
                        order: 10,
                        isButton: true,
                        html: '<button class="pswp__button pswp__button--custom" title="Actions">⚙️</button>',
                        onClick: (e, el) => {
                            e.preventDefault();
                            const wrapper = pswp.currSlide.data.element?.closest('.wall-item');
                            if (!wrapper) return;
                            
                            // Use GearMenuModule's open method if available
                            if (window.GearMenu && typeof window.GearMenu.open === 'function') {
                                window.GearMenu.open(wrapper);
                            }
                        }
                    });
                    */
                    
                    
                    
                });

                pswpLightbox.init();
            }
            
            // Show PhotoSwipe gear menu (similar to GearMenuModule but for PhotoSwipe context)
            function showPhotoSwipeGearMenu(pswp, anchor, menuItems, wrapper, entity, entityId, frameId) {
                // Remove existing menus
                document.querySelector('.pswp-gear-menu')?.remove();
                document.querySelector('.sb-menu')?.remove();
                
                const root = pswp.element;
                const menu = document.createElement('div');
                menu.className = 'pswp-gear-menu';
                
                menu.innerHTML = menuItems.map((item, i) => {
                    // Check condition if present
                    if (item.condition) {
                        try {
                            const conditionMet = new Function('entity', 'entityId', 'frameId', 
                                'return ' + item.condition)(entity, entityId, frameId);
                            if (!conditionMet) return '';
                        } catch(e) {
                            console.error('Menu condition error:', e);
                            return '';
                        }
                    }
                    
                    if (item.separator) {
                        return '<div class="pswp-gear-menu-separator"></div>';
                    }
                    
                    return `<div class="pswp-gear-menu-item" data-idx="${i}">
                        ${item.icon || ''} ${item.label}
                    </div>`;
                }).filter(Boolean).join('');
                
                root.appendChild(menu);
                
                // Position menu
                const aR = anchor.getBoundingClientRect();
                const rR = root.getBoundingClientRect();
                menu.style.top = `${aR.bottom - rR.top + 6}px`;
                menu.style.left = `${aR.right - rR.left - menu.offsetWidth}px`;
                
                // Click handler
                const clickHandler = (e) => {
                    const iEl = e.target.closest('.pswp-gear-menu-item');
                    if (!iEl) return;
                    
                    const idx = parseInt(iEl.dataset.idx, 10);
                    const item = menuItems[idx];
                    
                    try {
                        const handler = new Function('wrapper', 'entity', 'entityId', 'frameId', 'pswp', 
                            item.callback);
                        handler.call(iEl, wrapper, entity, entityId, frameId, pswp);
                    } catch(err) {
                        console.error('Menu action error:', err);
                        if (typeof Toast !== 'undefined') {
                            Toast.show('Action failed: ' + err.message, 'error');
                        }
                    }
                    
                    // Close menu unless told not to
                    if (item.closeOnClick !== false) {
                        menu.remove();
                    }
                };
                
                menu.addEventListener('click', clickHandler);
                
                // Close on outside click
                const closeHandler = (e) => {
                    const subMenu = document.querySelector('.sb-menu');
                    if (!menu.contains(e.target) && (!subMenu || !subMenu.contains(e.target))) {
                        menu.remove();
                        subMenu?.remove();
                        document.removeEventListener('click', closeHandler, true);
                    }
                };
                setTimeout(() => document.addEventListener('click', closeHandler, true), 0);
            }

            function loadPage(page, el) {
                if (!el || el.dataset.loaded === '1') return;
                
                fetch(`ajax_gallery.php?ajax_gallery=1&page=${page}&entity=wall_of_images`)
                .then(r => r.json())
                .then(j => {
                    el.querySelector('.wall-grid').innerHTML = j.itemsHtml;
                    el.dataset.loaded = '1';
                    
                    // DON'T attach gear menus to wall items - only in PhotoSwipe
                    // if (window.GearMenu) { window.GearMenu.attach(el); }
                    
                    initPS();
                })
                .catch(err => console.error('AJAX fail', err));
            }

            $(document).ready(() => {
                swiper = new Swiper('#wallSwiper', {
                    pagination: { el: '.swiper-pagination', clickable: true },
                    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                    on: {
                        slideChange: s => loadPage(
                            parseInt(s.slides[s.activeIndex].dataset.page),
                            s.slides[s.activeIndex]
                        )
                    }
                });
                
                initPS();
                
                // DON'T attach gear menus to wall items - we only want them in PhotoSwipe
                // if (window.GearMenu) { window.GearMenu.attach(document); }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}