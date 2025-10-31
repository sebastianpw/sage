<?php
namespace App\Gallery;
require_once "AbstractGallery.php";

class WallOfImagesGallery extends AbstractGallery {
    
    // Configuration constants
    public const IMAGE_SIZE = 122;        // width/height in pixels for square images
    public const COLUMNS = 5;            // number of columns
    public const ROWS = 10;                // number of rows per page
    public const TOP_PADDING = 0;        // padding at top in pixels
    
    protected function getLimit(): int {
        return self::COLUMNS * self::ROWS;
    }

    protected function getFiltersFromRequest(): array {
        // No filters in wall view
        return [];
    }

    protected function getFilterOptions(): array {
        // No filter options
        return [];
    }

    protected function getWhereClause(): string {
        // Get all images from all entities by querying the union view
        return '';
    }

    protected function getGalleryUrl() {
        return 'wall_of_images.php';
    }

    protected function showFloatool() {
        return false;
    }


    protected function getBaseQuery(): string
    {
        return "v_gallery_wall_of_images";
    }

    // Return the ORDER BY clause — seeded random
    protected function getOrderBy(): string
    {
        // Generate a seed in session if it doesn't exist yet
        if (!isset($_SESSION['gallery_seed'])) {
            $_SESSION['gallery_seed'] = mt_rand(1, 1000000);
        }

        $seed = $_SESSION['gallery_seed'];
        return "RAND($seed)";
    }

    // Optional: reset the seed if needed (e.g., "reshuffle" button)
    public function resetSeed(): void
    {
        $_SESSION['gallery_seed'] = mt_rand(1, 1000000);
    }





    protected function getCaptionFields(): array {
        // No captions in wall view
        return [];
    }

    protected function getGalleryEntity(): string {
        return "wall_of_images";
    }

    protected function getGalleryTitle(): string {
        return "Wall of Images";
    }

    protected function getToggleButtonLeft(): int {
        return 0; // no toggle button in wall view
    }


    // Override renderItem to show minimal image-only layout
    protected function renderItem(array $row): string {
        $fileRelRaw = $row['filename'] ?? '';
        $fileRelRaw = ltrim($fileRelRaw, "/");
        $fileRelEsc = htmlspecialchars($fileRelRaw);
        
        $pi = pathinfo($fileRelRaw);
        $extension = strtolower($pi['extension'] ?? '');
        
        $imgSrcWeb = '/' . ltrim($fileRelRaw, '/');
        $linkHref = $imgSrcWeb;
        
        $entity = $row['entity_type'] ?? 'unknown';
        $entityId = (int)($row['entity_id'] ?? 0);
        $frameId = (int)($row['frame_id'] ?? 0);
        
        $prompt = htmlspecialchars($row['prompt'] ?? '');
        $entityName = htmlspecialchars($row['entity_name'] ?? '');
        
        $title = $entityName . ($prompt ? ': ' . $prompt : '');
        
        ob_start();
        ?>
        <div class="wall-item" data-entity="<?= $entity ?>" data-entity-id="<?= $entityId ?>" data-frame-id="<?= $frameId ?>">
            <a href="<?= htmlspecialchars($linkHref) ?>" 
               class="pswp-gallery-item" 
               data-pswp-src="<?= self::URL_PREFIX . htmlspecialchars($linkHref) ?>"
               data-pswp-width="1024" 
               data-pswp-height="1024"
               title="<?= htmlspecialchars($title) ?>">
                <img src="<?= self::URL_PREFIX . htmlspecialchars($imgSrcWeb) ?>" 
                     alt=""
                     style="width: <?= self::IMAGE_SIZE ?>px; height: <?= self::IMAGE_SIZE ?>px; object-fit: cover;">
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    // Override render to provide minimal wall layout
    public function render(): string {
        // AJAX page fetch
        if (isset($_GET['ajax_gallery']) && $_GET['ajax_gallery'] == '1') {






            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = $this->limit;
            $offset = ($page - 1) * $limit;

            // Get WHERE clause from subclass/abstract
            $where = $this->getWhereClause();

            // Ensure session seed exists for consistent random pagination
            if (!isset($_SESSION['gallery_seed'])) {
                $_SESSION['gallery_seed'] = mt_rand(1, 1000000);
            }
            $seed = $_SESSION['gallery_seed'];

            // Build SQL — use RAND(seed) for stable random order
            $sql = "SELECT * FROM " . $this->getBaseQuery() . " $where ORDER BY RAND($seed) LIMIT $limit OFFSET $offset";

            // Execute query
            $result = $this->mysqli->query($sql);




            /*
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = $this->limit;
            $offset = ($page - 1) * $limit;
            $where = $this->getWhereClause();
            $sql = "SELECT * FROM " . $this->getBaseQuery() . " $where ORDER BY " . $this->getOrderBy() . " LIMIT $limit OFFSET $offset";
            $result = $this->mysqli->query($sql);
             */

            $itemsHtml = '';
            while ($row = $result->fetch_assoc()) {
                $itemsHtml .= $this->renderItem($row);
            }

            $result_count = $this->mysqli->query("SELECT COUNT(*) AS total FROM " . $this->getBaseQuery() . " $where");
            $total_rows = $result_count->fetch_assoc()['total'];
            $total_pages = (int)ceil($total_rows / $this->limit);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'page' => $page,
                'itemsHtml' => $itemsHtml,
                'totalPages' => $total_pages
            ]);
            exit;
        }

        // Regular page rendering
        $where = $this->getWhereClause();

        $result_count = $this->mysqli->query("SELECT COUNT(*) AS total FROM " . $this->getBaseQuery() . " $where");
        $total_rows = $result_count->fetch_assoc()['total'];
        $total_pages = max(1, (int)ceil($total_rows / $this->limit));

        $result = $this->mysqli->query("SELECT * FROM " . $this->getBaseQuery() . " $where ORDER BY " . $this->getOrderBy() . " LIMIT {$this->limit} OFFSET {$this->offset}");

        $itemsHtml = '';
        while ($row = $result->fetch_assoc()) {
            $itemsHtml .= $this->renderItem($row);
        }

        ob_start();
        ?>
        <div class="wall-container" style="padding-top: <?= self::TOP_PADDING ?>px; background: #000;">


<?php /*
            <div style="padding: 10px 0; text-align: center;">
                <h2 style="margin: 0; color: #666;">
                    <a href="dashboard.php" style="text-decoration: none; color: #666;">🌅</a>
                    <?= $this->getGalleryTitle() ?>
                    <a href="<?= $this->getGalleryUrl() ?>" style="text-decoration: none; color: #666;">↻</a>
                </h2>
            </div>
*/ ?>

            <!-- SWIPER for pagination -->
            <div class="swiper" id="wallSwiper">
                <div class="swiper-wrapper">
                    <?php
                    $firstPageNum = $this->page;
                    ?>
                    <div class="swiper-slide" data-page="<?= $firstPageNum ?>" data-loaded="1">
                        <div class="wall-grid pswp-gallery">
                            <?= $itemsHtml ?>
                        </div>
                    </div>

                    <?php
                    for ($p = 1; $p <= $total_pages; $p++) {
                        if ($p == $firstPageNum) continue;
                        ?>
                        <div class="swiper-slide" data-page="<?= $p ?>" data-loaded="0">
                            <div class="wall-grid pswp-gallery">
                                <div class="page-loading" style="color: #666; text-align: center; padding: 50px;">Loading...</div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>

            <?= $this->renderJsCss() ?>
        </div>

        <style>
        body {
            background: #000;
            margin: 0;
            padding: 0;
        }
        
        .wall-container {
            max-width: 100%;
            background: #000;
        }
        
        .wall-grid {
            display: grid;
            grid-template-columns: repeat(<?= self::COLUMNS ?>, <?= self::IMAGE_SIZE ?>px);
            grid-auto-rows: <?= self::IMAGE_SIZE ?>px;
            gap: 0;
            justify-content: center;
            background: #000;
        }
        
        .wall-item {
            width: <?= self::IMAGE_SIZE ?>px;
            height: <?= self::IMAGE_SIZE ?>px;
            overflow: hidden;
            background: #000;
        }
        
        .wall-item a {
            display: block;
            width: 100%;
            height: 100%;
        }
        
        .wall-item img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .swiper {
            background: #000;
        }
        
        .swiper-pagination {
            bottom: 10px;
        }
        
        .swiper-pagination-bullet {
            background: #666;
            opacity: 0.5;
        }
        
        .swiper-pagination-bullet-active {
            background: #fff;
            opacity: 1;
        }
        
        .swiper-button-prev,
        .swiper-button-next {
            color: #666;
        }


.sb-menu {
    color: #000;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    max-height: 400px;
    overflow-y: auto;
    font-size: 14px;
}

.sb-menu-item {
    padding: 10px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.sb-menu-item:hover {
    background: #f5f5f5;
}

.sb-menu-item:last-child {
    border-bottom: none;
}

.sb-menu-sep {
    height: 1px;
    background: #e0e0e0;
    margin: 4px 0;
}



        </style>

        <script>
        (function(){
            let swiper = null;
            let photoswipeLightbox = null;

            function buildAjaxUrl(page) {
                const params = new URLSearchParams();
                params.set('ajax_gallery', '1');
                params.set('page', page);
                params.set('entity', '<?php echo $this->getGalleryEntity(); ?>');
                return 'ajax_gallery.php?' + params.toString();
            }

            function initPhotoSwipe() {
                if (photoswipeLightbox) {
                    try { photoswipeLightbox.destroy(); } catch(e){}
                }
                
                photoswipeLightbox = new PhotoSwipeLightbox({
                    gallery: '.pswp-gallery',
                    children: 'a.pswp-gallery-item',
                    pswpModule: PhotoSwipe,
                    initialZoomLevel: 'fit',
                    secondaryZoomLevel: 1
                });
                
                // Add custom button to PhotoSwipe header with entity link
                photoswipeLightbox.on('uiRegister', function() {
                    photoswipeLightbox.pswp.ui.registerElement({
                        name: 'entity-link',
                        order: 9,
                        isButton: true,
                        html: '<a href="#" class="pswp__button" title="View Frame" style="width: auto; background: none; color: #fff; font-size: 40px; padding: 0; margin-top: 10px; white-space: nowrap;">🎯</a>',
                        onClick: (event, el, pswp) => {
                            event.preventDefault();
                            
                            const currSlide = pswp.currSlide;
                            if (!currSlide || !currSlide.data || !currSlide.data.element) return;
                            
                            const wrapper = currSlide.data.element.closest('.wall-item');
                            if (!wrapper) return;
                            
                            const entity = wrapper.dataset.entity;
                            const entityId = wrapper.dataset.entityId;
                            const frameId = wrapper.dataset.frameId;
                            
                            if (entity && entityId && frameId) {
                                window.open(`view_frame.php?entity=${entity}&entity_id=${entityId}&frame_id=${frameId}`,'_blank');
                                //window.location.href = `view_frame.php?entity=${entity}&entity_id=${entityId}&frame_id=${frameId}`;
                            }
                        }
                });










// --- PhotoSwipe gear menu: register element + menu helper ---
photoswipeLightbox.pswp.ui.registerElement({
    name: 'gear-menu',
    order: 10,
    isButton: true,
    html: '<button class="pswp__button pswp__gear-btn" title="Actions" style="width:auto;background:none;border:none;color:#fff;font-size:30px;padding:0;margin-top:6px;">⚙</button>',
    
    // CORRECTED onClick HANDLER
    onClick: (event, el) => { 
        event.preventDefault();
        
        // Get the pswp instance robustly from the parent scope
        const pswp = photoswipeLightbox.pswp; 
        if (!pswp) return; // Safety check

        const currSlide = pswp.currSlide;
        if (!currSlide || !currSlide.data || !currSlide.data.element) return;
        const wrapper = currSlide.data.element.closest('.wall-item');
        if (!wrapper) return;

        const entity   = wrapper.dataset.entity;
        const entityId = wrapper.dataset.entityId;
        const frameId  = wrapper.dataset.frameId;

        // menu items - keep same actions as your view_frame gear
        const items = [
            { label: '⚡ Import to Generative', onClick: () => window.importGenerative(entity, entityId, frameId) },
            { label: '🎬 Add to Storyboard',     onClick: () => window.selectStoryboard(frameId) },
            { label: '✏️ Edit Image',            onClick: () => {
                    if (typeof ImageEditorModal !== 'undefined') {
                        ImageEditorModal.open({
                            entity: entity,
                            entityId: entityId,
                            frameId: frameId,
                            src: currSlide.data.src || currSlide.data.src || (currSlide.data.element && currSlide.data.element.href) || ''
                        });
                    } else {
                        console.warn('ImageEditorModal not available');
                    }
                }
            },
            { label: '🧩 Assign to Composite',   onClick: () => window.assignToComposite(entity, entityId, frameId) },
            { label: '☠️ Import2CNetMap',        onClick: () => window.importControlNetMap(entity, entityId, frameId) },
            { label: '🌌 Use Prompt Matrix',     onClick: () => window.usePromptMatrix(entity, entityId, frameId) },
            { label: '🗑️ Delete Frame',         onClick: () => {
                    if (confirm('Delete this frame?')) {
                        window.deleteFrame(entity, entityId, frameId);
                        // close Photoswipe and then optionally redirect (keep behavior consistent)
                        try { pswp.close(); } catch(e){}
                        setTimeout(()=>{ window.location.href = '<?= htmlspecialchars($entityUrl ?? $this->getGalleryUrl()) ?>'; }, 800);
                    }
                }
            }
        ];

        // Show the menu inside PhotoSwipe UI
        showPhotoSwipeGearMenu(pswp, el, items);
    }
});






/*

// --- PhotoSwipe gear menu: register element + menu helper ---
// Place this inside your photoswipeLightbox.on('uiRegister', ...) or right after it
photoswipeLightbox.pswp.ui.registerElement({
    name: 'gear-menu',
    order: 10,
    isButton: true,
    html: '<button class="pswp__button pswp__gear-btn" title="Actions" style="width:auto;background:none;border:none;color:#fff;font-size:30px;padding:0;margin-top:6px;">⚙</button>',
    onClick: (event, el, pswp) => {
        event.preventDefault();

        const currSlide = pswp.currSlide;
        if (!currSlide || !currSlide.data || !currSlide.data.element) return;
        const wrapper = currSlide.data.element.closest('.wall-item');
        if (!wrapper) return;

        const entity   = wrapper.dataset.entity;
        const entityId = wrapper.dataset.entityId;
        const frameId  = wrapper.dataset.frameId;

        // menu items - keep same actions as your view_frame gear
        const items = [
            { label: '⚡ Import to Generative', onClick: () => window.importGenerative(entity, entityId, frameId) },
            { label: '🎬 Add to Storyboard',     onClick: () => window.selectStoryboard(frameId) },
            { label: '✏️ Edit Image',            onClick: () => {
                    if (typeof ImageEditorModal !== 'undefined') {
                        ImageEditorModal.open({
                            entity: entity,
                            entityId: entityId,
                            frameId: frameId,
                            src: currSlide.data.src || currSlide.data.src || (currSlide.data.element && currSlide.data.element.href) || ''
                        });
                    } else {
                        console.warn('ImageEditorModal not available');
                    }
                }
            },
            { label: '🧩 Assign to Composite',   onClick: () => window.assignToComposite(entity, entityId, frameId) },
            { label: '☠️ Import2CNetMap',        onClick: () => window.importControlNetMap(entity, entityId, frameId) },
            { label: '🌌 Use Prompt Matrix',     onClick: () => window.usePromptMatrix(entity, entityId, frameId) },
            { label: '🗑️ Delete Frame',         onClick: () => {
                    if (confirm('Delete this frame?')) {
                        window.deleteFrame(entity, entityId, frameId);
                        // close Photoswipe and then optionally redirect (keep behavior consistent)
                        try { pswp.close(); } catch(e){}
                        setTimeout(()=>{ window.location.href = '<?= htmlspecialchars($entityUrl ?? "wall_of_images.php") ?>'; }, 800);
                    }
                }
            }
        ];

        // Show the menu inside PhotoSwipe UI
        showPhotoSwipeGearMenu(pswp, el, items);
    }
                });


*/








// helper to show a simple absolute-positioned menu inside the Photoswipe root
function showPhotoSwipeGearMenu(pswp, anchorEl, items) {
    // inject CSS once (re-uses your .sb-menu styles if already present)
    if (!document.getElementById('pswp-gear-menu-style')) {
        const css = `
            .pswp-gear-menu { position:absolute; z-index:9999; min-width:220px; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.35); overflow:hidden; background:#fff; color:#000; font-size:14px; }
            .pswp-gear-menu .sb-menu-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid #eee; }
            .pswp-gear-menu .sb-menu-item:hover { background:#f6f6f6; }
            .pswp-gear-menu .sb-menu-item:last-child { border-bottom:none; }
        `;
        const s = document.createElement('style');
        s.id = 'pswp-gear-menu-style';
        s.innerHTML = css;
        document.head.appendChild(s);
    }

    // remove existing menu
    const root = pswp.element; // <-- CORRECTED LINE
    if (!root) return; // Safety check

    const existing = root.querySelector('.pswp-gear-menu');
    if (existing) existing.remove();

    // build menu
    const menu = document.createElement('div');
    menu.className = 'pswp-gear-menu';
    menu.setAttribute('role','menu');
    menu.innerHTML = items.map((it, idx) => `<div class="sb-menu-item" data-idx="${idx}">${it.label}</div>`).join('');
    root.appendChild(menu);

    // position menu right under the anchor element (within PhotoSwipe root coords)
    const anchorRect = anchorEl.getBoundingClientRect();
    const rootRect   = root.getBoundingClientRect();

    // default offsets
    const top = anchorRect.bottom - rootRect.top + 6;

    // align right edge of menu to anchor's right edge (clamp to viewport)
    menu.style.top = (top) + 'px';
    menu.style.left = (anchorRect.right - rootRect.left - menu.offsetWidth) + 'px';

    // If menu is off-screen to the left, clamp it
    const leftPx = parseFloat(menu.style.left);
    if (leftPx < 8) {
        menu.style.left = '8px';
    }

    // wire clicks
    menu.querySelectorAll('.sb-menu-item').forEach(el => {
        el.addEventListener('click', (ev) => {
            const idx = parseInt(el.dataset.idx, 10);
            try { items[idx].onClick(); } catch(err){ console.error('gear action failed', err); }
            menu.remove();
        });
    });

    // auto-remove on outside click or ESC
    function onDocClick(e) {
        if (!menu.contains(e.target) && e.target !== anchorEl) {
            menu.remove();
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keyup', onEsc);
        }
    }
    function onEsc(e) {
        if (e.key === 'Escape') {
            menu.remove();
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keyup', onEsc);
        }
    }
    // defer adding doc listener so the click that opened the menu doesn't immediately close it
    setTimeout(()=>{ document.addEventListener('click', onDocClick); document.addEventListener('keyup', onEsc); }, 0);
}





/*


// helper to show a simple absolute-positioned menu inside the Photoswipe root
function showPhotoSwipeGearMenu(pswp, anchorEl, items) {
    // inject CSS once (re-uses your .sb-menu styles if already present)
    if (!document.getElementById('pswp-gear-menu-style')) {
        const css = `
            .pswp-gear-menu { position:absolute; z-index:9999; min-width:220px; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.35); overflow:hidden; background:#fff; color:#000; font-size:14px; }
            .pswp-gear-menu .sb-menu-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid #eee; }
            .pswp-gear-menu .sb-menu-item:hover { background:#f6f6f6; }
            .pswp-gear-menu .sb-menu-item:last-child { border-bottom:none; }
        `;
        const s = document.createElement('style');
        s.id = 'pswp-gear-menu-style';
        s.innerHTML = css;
        document.head.appendChild(s);
    }

    // remove existing menu
    const root = pswp.ui.element;
    const existing = root.querySelector('.pswp-gear-menu');
    if (existing) existing.remove();

    // build menu
    const menu = document.createElement('div');
    menu.className = 'pswp-gear-menu';
    menu.setAttribute('role','menu');
    menu.innerHTML = items.map((it, idx) => `<div class="sb-menu-item" data-idx="${idx}">${it.label}</div>`).join('');
    root.appendChild(menu);

    // position menu right under the anchor element (within PhotoSwipe root coords)
    const anchorRect = anchorEl.getBoundingClientRect();
    const rootRect   = root.getBoundingClientRect();
    // default offsets
    const top = anchorRect.bottom - rootRect.top + 6;
    // align right edge of menu to anchor's right edge (clamp to viewport)
    menu.style.top = (top) + 'px';
    menu.style.left = (anchorRect.right - rootRect.left - menu.offsetWidth) + 'px';

    // If menu is off-screen to the left, clamp it
    const leftPx = parseFloat(menu.style.left);
    if (leftPx < 8) {
        menu.style.left = '8px';
    }

    // wire clicks
    menu.querySelectorAll('.sb-menu-item').forEach(el => {
        el.addEventListener('click', (ev) => {
            const idx = parseInt(el.dataset.idx, 10);
            try { items[idx].onClick(); } catch(err){ console.error('gear action failed', err); }
            menu.remove();
        });
    });

    // auto-remove on outside click or ESC
    function onDocClick(e) {
        if (!menu.contains(e.target) && e.target !== anchorEl) {
            menu.remove();
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keyup', onEsc);
        }
    }
    function onEsc(e) {
        if (e.key === 'Escape') {
            menu.remove();
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keyup', onEsc);
        }
    }
    // defer adding doc listener so the click that opened the menu doesn't immediately close it
    setTimeout(()=>{ document.addEventListener('click', onDocClick); document.addEventListener('keyup', onEsc); }, 0);
}



 */











                   /* 
                    photoswipeLightbox.pswp.ui.registerElement({
                        name: 'gallery-link',
                        order: 8,
                        isButton: true,
                        html: '<a href="#" class="pswp__button" title="View in Gallery" style="width: auto; background: none; color: #fff; font-size: 13px; padding: 0 10px; white-space: nowrap;">📂 Gallery</a>',
                        onClick: (event, el, pswp) => {
                            event.preventDefault();
                            
                            const currSlide = pswp.currSlide;
                            if (!currSlide || !currSlide.data || !currSlide.data.element) return;
                            
                            const wrapper = currSlide.data.element.closest('.wall-item');
                            if (!wrapper) return;
                            
                            const entity = wrapper.dataset.entity;
                            
                            if (entity) {
                                window.location.href = `gallery_${entity}.php`;
                            }
                        }
                    });
                    */


                });
                
                photoswipeLightbox.init();
            }

            function loadPage(page, slideEl) {
                if (!slideEl) return;
                if (slideEl.getAttribute('data-loaded') === '1') return;
                
                const loadingEl = slideEl.querySelector('.page-loading');
                if (loadingEl) loadingEl.innerText = 'Loading...';
                
                fetch(buildAjaxUrl(page), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    const wallGrid = slideEl.querySelector('.wall-grid');
                    if (wallGrid) {
                        wallGrid.innerHTML = json.itemsHtml;
                    }
                    slideEl.setAttribute('data-loaded', '1');
                    
                    // Just reinitialize PhotoSwipe listeners, don't auto-open
                    if (photoswipeLightbox) {
                        try {
                            photoswipeLightbox.destroy();
                            initPhotoSwipe();
                        } catch(e) {
                            console.warn('PhotoSwipe reinit:', e);
                        }
                    }
                })
                .catch(err => {
                    console.error('Wall AJAX error', err);
                    if (loadingEl) loadingEl.innerText = 'Failed to load';
                });
            }

            function initSwiper() {
                if (swiper) { swiper.update(); return; }
                
                swiper = new Swiper('#wallSwiper', {
                    direction: 'horizontal',
                    slidesPerView: 1,
                    spaceBetween: 0,
                    pagination: { 
                        el: '.swiper-pagination', 
                        clickable: true,
                        dynamicBullets: true,
                        dynamicMainBullets: 3
                    },
                    navigation: { 
                        nextEl: '.swiper-button-next', 
                        prevEl: '.swiper-button-prev' 
                    },
                    grabCursor: true,
                    on: {
                        slideChange: function() {
                            const slideEl = this.slides[this.activeIndex];
                            const pageAttr = parseInt(slideEl.dataset.page || (this.activeIndex + 1), 10);
                            loadPage(pageAttr, slideEl);
                        }
                    }
                });

                swiper.on('slideChangeTransitionEnd', function() {
                    const idx = swiper.activeIndex;
                    [idx-1, idx+1].forEach(i => {
                        const s = swiper.slides[i];
                        if (s) {
                            const p = parseInt(s.dataset.page || i+1, 10);
                            loadPage(p, s);
                        }
                    });
                });
            }











/*
  Global utilities and safe defaults:
  - window.importGenerative
  - window.deleteFrame
  - attachGearTo(context)
*/
window.importGenerative = window.importGenerative || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing parameters. Import aborted.', 'error');
        return;
    }
    const ajaxUrl = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=generatives&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const resp = await fetch(ajaxUrl, { credentials: 'same-origin' });
        const text = await resp.text(); let data;
        try { data = JSON.parse(text); } catch(e) { Toast.show('Import failed: invalid response','error'); console.error(text); return; }
        if ((data.status && data.status === 'ok') || Array.isArray(data.result)) {
            const msg = Array.isArray(data.result) ? data.result.join("\n") : (data.message || 'Import triggered');
            Toast.show(`Import triggered for ${entity} #${entityId}: ${msg}`, 'info');
        } else {
            Toast.show(`Import failed for ${entity} #${entityId}`, 'error');
            console.warn('importGenerative: unexpected payload', data);
        }
    } catch (err) {
        Toast.show('Import failed', 'error'); console.error(err);
    }
});










/*
 * STORYBOARD INTEGRATION
 */


window._storyboardsCache = null;

window.loadStoryboards = async function() {
    if (window._storyboardsCache) return window._storyboardsCache;
    try {
        const resp = await fetch('/storyboards_v2_api.php?action=list', { credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            window._storyboardsCache = data.data;
            return data.data;
        }
    } catch (err) {
        console.error('loadStoryboards error:', err);
    }
    return [];
};

window.importToStoryboard = async function(frameId, storyboardId) {
    if (!frameId || !storyboardId) {
        Toast.show('Missing parameters', 'error');
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('storyboard_id', storyboardId);
    formData.append('frame_id', frameId);
    
    try {
        const resp = await fetch('/storyboard_import.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
            Toast.show(data.message || 'Frame imported to storyboard', 'success');
            window._storyboardsCache = null; // Clear cache
            return data;
        } else {
            Toast.show('Import failed: ' + (data.message || 'unknown error'), 'error');
        }
    } catch (err) {
        Toast.show('Import failed', 'error');
        console.error(err);
    }
};


/*
window.selectStoryboard = async function(frameId) {
    const storyboards = await window.loadStoryboards();
    
    if (storyboards.length === 0) {
        Toast.show('No storyboards available. Create one first.', 'warning');
        if (confirm('No storyboards found. Create one now?')) {
            window.open('/view_storyboards_v2.php', '_blank');
        }
        return;
    }
    
    let msg = 'Select a storyboard:\n\n';
    storyboards.forEach((sb, i) => {
        msg += `${i+1}. ${sb.name} (${sb.frame_count} frames)\n`;
    });
    msg += '\nEnter number (1-' + storyboards.length + '):';
    
    const choice = prompt(msg);
    if (!choice) return;
    
    const idx = parseInt(choice) - 1;
    if (idx >= 0 && idx < storyboards.length) {
        await window.importToStoryboard(frameId, storyboards[idx].id);
    } else {
        Toast.show('Invalid selection', 'error');
    }
};
 */



window.selectStoryboard = async function(frameId, $trigger) {
    const storyboards = await window.loadStoryboards();

    if (storyboards.length === 0) {
        Toast.show('No storyboards available', 'warning');
        return;
    }

    // Remove existing menus
    $('.sb-menu').remove();

    // Build menu
    const $menu = $('<div class="sb-menu"></div>');

    storyboards.forEach(sb => {
        const $item = $(`
            <div class="sb-menu-item" data-sb-id="${sb.id}">
                ${sb.name} <span style="color:#999">(${sb.frame_count})</span>
            </div>
        `);

        $item.on('click', async function(e) {
            e.stopPropagation();
            $('.sb-menu').remove();
            await window.importToStoryboard(frameId, sb.id);
        });

        $menu.append($item);
    });

    // Add separator and manage link
    $menu.append('<div class="sb-menu-sep"></div>');
    $menu.append(`
        <div class="sb-menu-item" onclick="window.open('/view_storyboards_v2.php','_blank')">
            📋 Manage Storyboards
        </div>
    `);

    // Position menu
    const pos = $trigger ? $trigger.offset() : { top: 100, left: 100 };
    $menu.css({
        position: 'absolute',
        top: pos.top + 30,
        left: pos.left,
        zIndex: 10000000
    });

    $('body').append($menu);

    // Close on outside click
    setTimeout(() => {
        $(document).one('click', () => $('.sb-menu').remove());
    }, 100);
};















window.importControlNetMap = window.importControlNetMap || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing parameters. Import aborted.', 'error');
        return;
    }
    const ajaxUrl = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=controlnet_maps&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const resp = await fetch(ajaxUrl, { credentials: 'same-origin' });
        const text = await resp.text(); 
        let data;
        try { 
            data = JSON.parse(text); 
        } catch(e) { 
            Toast.show('Import failed: invalid response','error'); 
            console.error(text); 
            return; 
        }
        if ((data.status && data.status === 'ok') || Array.isArray(data.result)) {
            const msg = Array.isArray(data.result) ? data.result.join("\n") : (data.message || 'Import triggered');
            Toast.show(`Import triggered for ${entity} #${entityId}: ${msg}`, 'info');
        } else {
            Toast.show(`Import failed for ${entity} #${entityId}`, 'error');
            console.warn('importControlNetMap: unexpected payload', data);
        }
    } catch (err) {
        Toast.show('Import failed', 'error'); 
        console.error(err);
    }
});





window.assignControlNetMap = window.assignControlNetMap || (async function(entity, entityId, frameId, targetEntity){
    // Only allow controlnet_maps
    if (entity !== 'controlnet_maps') {
        Toast.show('Only controlnet_maps can be assigned.', 'error');
        return;
    }

    if (!entityId || !frameId) {
        Toast.show('Missing entityId or frameId.', 'error');
        return;
    }

    if (!targetEntity || !['characters','generatives'].includes(targetEntity)) {
        Toast.show('Invalid target entity.', 'error');
        return;
    }

    // Redirect to your import form with prefilled parameters
    const hrefUrl = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=${encodeURIComponent(targetEntity)}&copy_name_desc=0&controlnet=1`;
    window.location.href = hrefUrl;
});


window.assignToComposite = window.assignToComposite || (async function(entity, entityId, frameId){

    if (!entity || !entityId || !frameId) {
        Toast.show('Missing entity or entityId or frameId.', 'error');
        return;
    }

    /*
    if (!entity || !['characters','generatives'].includes(entity)) {
        Toast.show('Invalid source entity.', 'error');
        return;
    }
    */

    // Redirect to your import form with prefilled parameters
    const hrefUrl = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=${encodeURIComponent('composites')}&copy_name_desc=0&composite=1`;
    window.location.href = hrefUrl;
});



window.usePromptMatrix = window.usePromptMatrix || (async function(entity, entityId, frameId){
    // Only allow controlnet_maps
    //if (entity !== 'controlnet_maps') {
    //    Toast.show('Only controlnet_maps can be assigned.', 'error');
    //    return;
    //}

    if (!entityId || !entity) {
        Toast.show('Missing entity or entityId.', 'error');
        return;
    }

    // Redirect to prompt matrix
    const hrefUrl = `/view_prompt_matrix.php?entity_type=${encodeURIComponent(entity)}&entity_id=${encodeURIComponent(entityId)}`;
    window.location.href = hrefUrl;
});



window.deleteFrame = window.deleteFrame || (async function(entity, entityId, frameId) {
    if (!entity || !entityId || !frameId) { alert("Missing parameters. Cannot delete frame."); return; }
    if (!confirm("Are you sure you want to delete this frame?")) return;
    try {
        const response = await fetch(`/delete_frames_from_entity.php?ajax=1&method=single&frame_id=${frameId}`, { method: 'POST', credentials:'same-origin' });
        const text = await response.text(); let result;
        try { result = JSON.parse(text); } catch(e){ throw new Error("Invalid server response: "+text); }
        if (result.status === "ok") {
            $(`.img-wrapper[data-frame-id="${frameId}"]`).remove();
            Toast.show('Frame deleted','success');
        } else {
            alert("Failed to delete frame: " + (result.message || 'unknown'));
        }
    } catch (err) {
        alert("Delete failed: " + (err.message || err));
        console.error(err);
    }
});











            $(document).ready(function(){
                initSwiper();
                initPhotoSwipe();
            });
        })();
        </script>






<!-- Image editor scripts and logic -->

<script src="/js/image_editor_modal.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- CropperJS CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
<?php else: ?>
    <!-- CropperJS CSS via local copy -->
    <link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- CropperJS JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
<?php else: ?>
    <!-- CropperJS JS via local copy -->
    <script src="/vendor/cropper/cropper.min.js"></script>
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- jQuery-Cropper via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-cropper@1.0.1/dist/jquery-cropper.min.js"></script>
<?php else: ?>
    <!-- jQuery-Cropper via local copy -->
    <script src="/vendor/cropper/jquery-cropper.min.js"></script>
<?php endif; ?>





<script>
/* Image editor modal logic */
(function(){
    let cropper = null;
    let current = { entity: null, entityId: null, frameId: null, src: null, naturalWidth: null, naturalHeight: null };

    function openModal() { $('#imageEditModal').fadeIn(160); }
    function closeModal() {
        try { if (cropper) { $('#imageEditImg').cropper('destroy'); cropper = null; } } catch(e){}
        $('#imageEditImg').attr('src','');
        $('#imageEditPreviewImg').attr('src','');
        $('#imageEditModal').fadeOut(120);
    }

    window.openImageEditor = function(opts) {
        current.entity = opts.entity;
        current.entityId = opts.entityId;
        current.frameId = opts.frameId;
        current.src = opts.src;
        $('#imageEditTitle').text('Edit: ' + (opts.frameId ? ('frame #' + opts.frameId) : opts.src));
        $('#imageEditImg').attr('src', opts.src);
        $('#imageEditPreviewImg').attr('src', opts.src);
        $('#imageEditNote').val('');
        $('#imageEditApplyNow').prop('checked', false);
        $('#imageEditMode').val('crop');

        $('#imageEditImg').off('load').on('load', function(){
            try { $('#imageEditImg').cropper('destroy'); } catch(e){}
            $('#imageEditImg').cropper({
                viewMode: 1,
                autoCropArea: 0.6,
                movable: true,
                zoomable: true,
                scalable: false,
                cropBoxResizable: true,
                ready: function(){
                    current.naturalWidth = this.naturalWidth || this.naturalWidth;
                    current.naturalHeight = this.naturalHeight || this.naturalHeight;
                },
                crop: function(e) {
                    try {
                        const canvas = $('#imageEditImg').cropper('getCroppedCanvas', { width: 300, height: 300 });
                        if (canvas) { $('#imageEditPreviewImg').attr('src', canvas.toDataURL()); }
                    } catch(e){}
                }
            });
            cropper = $('#imageEditImg').data('cropper') || $('#imageEditImg');
        });

        openModal();
    };

    // wire modal buttons
    $(document).on('click', '#imageEditClose, #imageEditCancelBtn', function(e){
        e.preventDefault(); closeModal();
    });

    // Save (without apply) and Save & Apply
    $(document).on('click', '#imageEditSaveBtn, #imageEditSaveApplyBtn', async function(e){
        e.preventDefault();
        const doApply = $(this).attr('id') === 'imageEditSaveApplyBtn' || $('#imageEditApplyNow').is(':checked');
        if (!current.frameId || !current.entity) { Toast.show('Missing frame info', 'error'); return; }
        if (!$('#imageEditImg').attr('src')) { Toast.show('No image loaded', 'error'); return; }

        let cropData = null;
        try {
            const data = $('#imageEditImg').cropper('getData', true);
            cropData = {
                x: Math.round(data.x || 0),
                y: Math.round(data.y || 0),
                width: Math.round(data.width || 0),
                height: Math.round(data.height || 0),
                rotate: data.rotate || 0,
                scaleX: data.scaleX || 1,
                scaleY: data.scaleY || 1,
                imageNaturalWidth: $('#imageEditImg')[0].naturalWidth || current.naturalWidth || null,
                imageNaturalHeight: $('#imageEditImg')[0].naturalHeight || current.naturalHeight || null
            };
        } catch (err) {
            console.warn('cropper getData fail', err);
            cropData = { x:0, y:0, width:0, height:0, imageNaturalWidth: null, imageNaturalHeight: null };
        }

        const payload = {
            entity: current.entity,
            frame_id: current.frameId,
            entity_id: current.entityId,
            coords: cropData,
            mode: $('#imageEditMode').val() || 'crop',
            tool: 'jquery-cropper',
            note: $('#imageEditNote').val() || '',
            apply_immediately: doApply ? 1 : 0
        };

        try {
            const resp = await fetch('/save_image_edit.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const text = await resp.text();
            let json;
            try { json = JSON.parse(text); } catch(e) {
                Toast.show('Save failed: invalid response', 'error'); console.error('save_image_edit invalid json', text); return;
            }
            if (!json.success) {
                Toast.show('Save failed: ' + (json.message || 'unknown'), 'error');
                console.warn('save_image_edit error payload', json);
                return;
            }

            // success - update gallery image src in DOM for this frame
            const derived = json.derived_filename || json.filename || json.new_filename || null;
            if (derived) {
                const $wrapper = $(`.img-wrapper[data-frame-id="${current.frameId}"]`);
                const newSrc = (derived.charAt(0) === '/' ? derived : ('/' + derived)) + '?v=' + Date.now();
                $wrapper.find('img').each(function(){
                    $(this).attr('src', newSrc);
                });
            }

            Toast.show('Version created', 'info');
            closeModal();
            $(document).trigger('imageEdit.created', [json, current]);

        } catch (err) {
            console.error('save_image_edit fetch error', err);
            Toast.show('Save failed', 'error');
        }
    });

    // close modal when clicking overlay
    $(document).on('click', '#imageEditModal', function(e){
        if (e.target.id === 'imageEditModal') closeModal();
    });

})();
</script>




        <?php
        return ob_get_clean();
    }

    // Override renderFilters to show nothing
    protected function renderFilters(): void {
        // No filters
    }
}
