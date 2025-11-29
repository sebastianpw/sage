<?php
// src/Gallery/AbstractNuGallery.php
namespace App\Gallery;

/**
 * AbstractNuGallery - New modular gallery base class
 * Works with GearMenuModule and ImageEditorModule
 */
abstract class AbstractNuGallery
{
    public const URL_PREFIX = "";
    
    protected \App\Core\SpwBase $spw;
    protected \mysqli $mysqli;
    protected array $filters = [];
    protected int $page = 1;
    protected int $limit = 12;
    protected int $offset = 0;
    protected bool $gridOn = false;
    protected string $albumClass = 'album';
    protected array $filterOptions = [];
    
    // Module integration
    protected ?\App\UI\Modules\GearMenuModule $gearMenu = null;
    protected ?\App\UI\Modules\ImageEditorModule $imageEditor = null;

    public function __construct()
    {
        $this->spw = \App\Core\SpwBase::getInstance();
        $this->mysqli = $this->spw->getMysqli();
        $this->page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $this->limit = $this->getLimit();
        $this->offset = ($this->page - 1) * $this->limit;
        $this->gridOn = !isset($_GET['grid']) || $_GET['grid'] !== '0';
        $this->albumClass = $this->gridOn ? 'album grid' : 'album';
        $this->filters = $this->getFiltersFromRequest();
        $this->filterOptions = $this->getFilterOptions();
    }

    // Abstract methods
    abstract protected function getFiltersFromRequest(): array;
    abstract protected function getFilterOptions(): array;
    abstract protected function getWhereClause(): string;
    abstract protected function getBaseQuery(): string;
    abstract protected function getCaptionFields(): array;
    abstract protected function getGalleryEntity(): string;
    abstract protected function getGalleryTitle(): string;
    abstract protected function getToggleButtonLeft(): int;

    /**
     * Get the AJAX endpoint URL. Can be overridden by child classes.
     */
    protected function getAjaxEndpoint(): string
    {
        return '/ajax_gallery.php';
    }

    /**
     * Set the gear menu module
     */
    public function setGearMenu(\App\UI\Modules\GearMenuModule $gearMenu): self
    {
        $this->gearMenu = $gearMenu;
        return $this;
    }

    /**
     * Set the image editor module
     */
    public function setImageEditor(\App\UI\Modules\ImageEditorModule $imageEditor): self
    {
        $this->imageEditor = $imageEditor;
        return $this;
    }

    /**
     * Get PhotoSwipe gear menu items - override in concrete galleries
     */
    protected function getPhotoSwipeGearMenuItems(): array
    {
        return [];
    }

    protected function getLimit(): int
    {
        return 12;
    }

    protected function getOrderBy(): string
    {
        return 'frame_id DESC';
    }

    protected function fetchDistinct(string $column): array
    {
        $res = $this->mysqli->query("SELECT DISTINCT $column FROM v_gallery_" . $this->getGalleryEntity() . " ORDER BY $column ASC");
        $arr = [];
        while ($row = $res->fetch_assoc()) {
            $arr[] = $row[$column];
        }
        return $arr;
    }

    /**
     * Render a single gallery item
     */
    protected function renderItem(array $row): string
    {
        $fields = $this->getCaptionFields();
        $fileRelRaw = $row['filename'] ?? '';
        $fileRelRaw = ltrim($fileRelRaw, "/");

        $imgSrcWeb = '/' . $fileRelRaw;
        $linkHref = '/' . $fileRelRaw;
        $imgSrcEsc = htmlspecialchars($imgSrcWeb);

        $prompt = htmlspecialchars($row['prompt'] ?? '');
        $entity = $this->getGalleryEntity();
        $entityId = (int)($row['entity_id'] ?? 0);
        $frameId = (int)($row['frame_id'] ?? 0);

        $captionParts = [];
        if (!empty($row['prompt'])) {
            $captionParts[] = $prompt;
        }
        foreach ($fields as $label => $field) {
            if (!empty($row[$field])) {
                $captionParts[] = '<strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($row[$field]);
            }
        }
        $captionHtml = implode('<br>', $captionParts);

        ob_start();
        ?>
        <div class="img-wrapper" data-entity="<?= $entity ?>" data-entity-id="<?= $entityId ?>" data-frame-id="<?= $frameId ?>">
            <a href="<?= htmlspecialchars($linkHref) ?>" 
               class="pswp-gallery-item" 
               data-pswp-src="<?= self::URL_PREFIX . htmlspecialchars($linkHref) ?>"
               data-pswp-width="1024" 
               data-pswp-height="1024"
               title="<?= htmlspecialchars(strip_tags($captionHtml)) ?>">
                <img src="<?= self::URL_PREFIX . $imgSrcEsc ?>" alt="">
            </a>

            <div class="caption" style="max-height: 50px;">
                <button class="show-full-text" title="View full description">📖</button>
                <?= $prompt ?><br>
                <?php foreach ($fields as $label => $field): ?>
                    <strong><?= htmlspecialchars($label) ?>:</strong> <?= htmlspecialchars($row[$field] ?? '') ?><br>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Main render method
     */
    public function render(): string
    {
        require "entity_icons.php";

        if (isset($_GET['ajax_gallery']) && $_GET['ajax_gallery'] == '1') {
            return $this->renderAjaxResponse();
        }

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
        <div class="album-container <?= $this->gridOn ? 'grid-view' : '' ?>">
            <div style="padding-left: 35px;" class="gallery-main-header">
                <?php 
                    $entity = $this->getGalleryEntity();
                    echo '<h2><a href="sql_crud_' . $entity . '.php">' . $entityIcons[$entity] . '</a></h2>';
                ?>
                <h2 class="gallery-title"><?= $this->getGalleryTitle() ?></h2>
                <h2 class="gallery-refresh-link-wrapper"><a href="<?= $this->getGalleryUrl() ?>">↻</a></h2>
            </div>

            <form id="galleryFilterForm" class="gallery-header" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="grid" value="<?= $this->gridOn ? '1' : '0' ?>">
                <?php $this->renderFilters(); ?>
                <button style="position: absolute; top: 0; left: <?= $this->getToggleButtonLeft() ?>px;" id="toggleView" type="button"><?php echo $this->gridOn ? '⬜ Pic' : '⠿ Grid'; ?></button>
            </form>

            <div class="swiper" id="<?= $this->getGalleryEntity() ?>Swiper">
                <div class="swiper-wrapper">
                    <div class="swiper-slide" data-page="<?= $this->page ?>" data-loaded="1">
                        <div class="slide-inner">
                            <div class="<?= htmlspecialchars($this->albumClass) ?> pswp-gallery">
                                <?= $itemsHtml ?>
                            </div>
                        </div>
                    </div>

                    <?php for ($p = 1; $p <= $total_pages; $p++): if ($p == $this->page) continue; ?>
                        <div class="swiper-slide" data-page="<?= $p ?>" data-loaded="0">
                            <div class="slide-inner">
                                <div class="<?= htmlspecialchars($this->albumClass) ?> pswp-gallery">
                                    <div class="page-loading">Loading...</div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>

            <?= $this->renderJsCss() ?>
        </div>

        <div id="fullTextModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <div id="modalText"></div>
            </div>
        </div>

        <?php
        return ob_get_clean() . $this->renderSequel();
    }

    protected function renderAjaxResponse(): string
    {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = $this->limit;
        $offset = ($page - 1) * $limit;
        $where = $this->getWhereClause();
        
        $sql = "SELECT * FROM " . $this->getBaseQuery() . " $where ORDER BY " . $this->getOrderBy() . " LIMIT $limit OFFSET $offset";
        $result = $this->mysqli->query($sql);

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

    protected function renderFilters(): void
    {
        foreach ($this->filterOptions as $name => $options) {
            $left = $options['left'] ?? 0;
            $selectedVal = $this->filters[$name] ?? 'all';
            echo "<select name=\"{$name}\" style=\"width:50px; position: absolute; top:0; left: {$left}px;\" onchange=\"document.getElementById('galleryFilterForm').submit();\">";
            echo "<option value=\"all\" " . ($selectedVal === 'all' ? 'selected' : '') . ">All {$options['label']}</option>";
            foreach ($options['values'] as $val) {
                $valEsc = htmlspecialchars($val);
                $sel = ($selectedVal === $val) ? 'selected' : '';
                echo "<option value=\"$valEsc\" $sel>$valEsc</option>";
            }
            echo "</select>";
        }
    }

    protected function getPageUrl(int $page): string
    {
        $params = $this->filters;
        $params['page'] = $page;
        $params['grid'] = $this->gridOn ? '1' : '0';
        return "?" . http_build_query($params);
    }

    protected function getGalleryUrl(): string
    {
        return 'gallery_' . $this->getGalleryEntity() . '_nu.php';
    }

    protected function showFloatool(): bool
    {
        return true;
    }

    /**
     * Render PhotoSwipe gear menu integration JS
     */
    protected function renderPhotoSwipeGearMenuJS(): string
    {
        $menuItems = $this->getPhotoSwipeGearMenuItems();
        if (empty($menuItems)) {
            return '';
        }
        
        $menuItemsJson = json_encode($menuItems);
        
        return <<<JS

// PhotoSwipe Gear Menu Integration
pswpLightbox.on('uiRegister', function() {
    const pswp = pswpLightbox.pswp;
    
    // Add details button (🎯)
    pswp.ui.registerElement({
        name: 'details',
        order: 9,
        isButton: true,
        html: '<button class="pswp__button pswp__button--custom" title="Frame Details">🎯</button>',
        onClick: (e, el) => {
            e.preventDefault();
            const slideData = pswp.currSlide.data;
            const wrapper = slideData.element?.closest('.img-wrapper, .wall-item');
            if (!wrapper) return;
            const frameId = wrapper.dataset.frameId;
            if (frameId && typeof window.showFrameDetailsModal === 'function') {
                window.showFrameDetailsModal(frameId, 0.7);
            }
        }
    });
    
    // Add gear menu button (⚙️)
    pswp.ui.registerElement({
        name: 'gear',
        order: 10,
        isButton: true,
        html: '<button class="pswp__button pswp__button--custom" title="Actions">⚙️</button>',
        onClick: (e, el) => {
            e.preventDefault();
            const slideData = pswp.currSlide.data;
            const wrapper = slideData.element?.closest('.img-wrapper, .wall-item');
            if (!wrapper) return;
            
            const entity = wrapper.dataset.entity;
            const entityId = wrapper.dataset.entityId;
            const frameId = wrapper.dataset.frameId;
            
            showPhotoSwipeMenu(pswp, el, {entity, entityId, frameId});
        }
    });
});

// PhotoSwipe gear menu implementation
function showPhotoSwipeMenu(pswp, anchor, ctx) {
    const menuItems = {$menuItemsJson};
    
    document.querySelector('.pswp-gear-menu')?.remove();
    document.querySelector('.sb-menu')?.remove();
    
    const root = pswp.element;
    const menu = document.createElement('div');
    menu.className = 'pswp-gear-menu';
    
    menu.innerHTML = menuItems.map((item, i) => 
        `<div class="pswp-gear-menu-item" data-idx="\${i}">
            \${item.icon || ''} \${item.label}
        </div>`
    ).join('');
    
    root.appendChild(menu);
    
    const aR = anchor.getBoundingClientRect();
    const rR = root.getBoundingClientRect();
    menu.style.top = `\${aR.bottom - rR.top + 6}px`;
    menu.style.left = `\${aR.right - rR.left - menu.offsetWidth}px`;
    
    const clickHandler = (e) => {
        const iEl = e.target.closest('.pswp-gear-menu-item');
        if (!iEl) return;
        
        const item = menuItems[parseInt(iEl.dataset.idx, 10)];
        try {
            new Function('pswp', 'el', 'entity', 'entityId', 'frameId', 'wrapper', item.callback)
                .call(iEl, pswp, anchor, ctx.entity, ctx.entityId, ctx.frameId, iEl);
        } catch(err) {
            console.error('PhotoSwipe gear menu error:', err);
        }
        
        if (item.closeOnClick !== false) {
            menu.remove();
        }
    };
    
    menu.addEventListener('click', clickHandler);
    
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

JS;
    }

    protected function renderJsCss(): string
    {
        ob_start();
        ?>

        <link rel="stylesheet" href="/css/toast.css">
        <script src="/js/toast.js"></script>
        <link rel="stylesheet" href="/css/nugallery.css">

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
        
        <style>
        .pswp-gear-menu {
            position: absolute;
            z-index: 9999;
            min-width: 240px;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.35);
            overflow: hidden;
            background: #fff;
            color: #000;
            font-size: 14px;
        }
        .pswp-gear-menu-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pswp-gear-menu-item:hover {
            background: #f6f6f6;
        }
        .pswp__button--custom {
            background: none !important;
            font-size: 28px !important;
            width: 44px !important;
            height: 44px !important;
        }
        </style>

        <script>
        (function(){
            const galleryEntity = <?= json_encode($this->getGalleryEntity()) ?>;
            const ajaxEndpoint = <?= json_encode($this->getAjaxEndpoint()) ?>;
            const swiperContainer = '#' + galleryEntity + 'Swiper';
            let swiper = null;
            let photoswipeLightbox = null;

            const albumClassRaw = <?= json_encode(trim($this->albumClass)) ?>;
            const albumSelector = albumClassRaw.split(/\s+/).map(s => '.' + s).join('');

            function buildAjaxUrl(page) {
                const params = new URLSearchParams(new FormData(document.getElementById('galleryFilterForm')));
                params.set('ajax_gallery', '1');
                params.set('page', page);
                params.set('entity', galleryEntity);
                return ajaxEndpoint + '?' + params.toString();
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
                
                <?= $this->renderPhotoSwipeGearMenuJS() ?>
                
                photoswipeLightbox.init();
            }

            function loadPage(page, slideEl) {
                if (!slideEl || slideEl.getAttribute('data-loaded') === '1') return;
                
                const loadingEl = slideEl.querySelector('.page-loading');
                if (loadingEl) loadingEl.innerText = 'Loading...';
                
                fetch(buildAjaxUrl(page), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    let albumDiv = slideEl.querySelector(albumSelector) || slideEl.querySelector('.slide-inner') || slideEl;
                    albumDiv.innerHTML = json.itemsHtml;
                    slideEl.setAttribute('data-loaded', '1');
                    
                    if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
                        window.GearMenu.attach(slideEl);
                    }
                    
                    $(slideEl).find('.show-full-text').off('click').on('click', function(e){
                        e.stopPropagation();
                        const $caption = $(this).closest('.caption');
                        const fullHtml = $caption.clone();
                        fullHtml.find('.show-full-text').remove();
                        $('#modalText').html(fullHtml.html());
                        $('#fullTextModal').fadeIn(200);
                    });
                })
                .catch(err => {
                    console.error('Gallery AJAX error', err);
                    if (loadingEl) loadingEl.innerText = 'Failed to load';
                });
            }

            function initSwiper() {
                if (swiper) { swiper.update(); return; }
                
                swiper = new Swiper(swiperContainer, {
                    direction: 'horizontal',
                    slidesPerView: 1,
                    spaceBetween: 0,
                    pagination: { el: '.swiper-pagination', clickable: true },
                    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                    grabCursor: true,
                    on: {
                        slideChange: function() {
                            const slideEl = this.slides[this.activeIndex];
                            const pageAttr = parseInt(slideEl.dataset.page || (this.activeIndex + 1), 10);
                            loadPage(pageAttr, slideEl);
                        }
                    }
                });
            }

            $(document).ready(function(){
                initSwiper();
                initPhotoSwipe();

                if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
                    window.GearMenu.attach(document);
                }

                $(document).on('click', '.show-full-text', function(e){
                    e.stopPropagation();
                    const $caption = $(this).closest('.caption');
                    const fullHtml = $caption.clone();
                    fullHtml.find('.show-full-text').remove();
                    $('#modalText').html(fullHtml.html());
                    $('#fullTextModal').fadeIn(200);
                });

                $('#fullTextModal .close, #fullTextModal').off('click').on('click', function(e){
                    if(e.target !== this) return;
                    $('#fullTextModal').fadeOut(200);
                });

                $('#toggleView').off('click').on('click', function(e){
                    e.preventDefault();
                    const $container = $('.album-container').first();
                    
                    if (typeof window._galleryGridOn === 'undefined') {
                        const hidden = $('#galleryFilterForm input[name="grid"]').val();
                        window._galleryGridOn = (hidden === '1');
                    }

                    window._galleryGridOn = !window._galleryGridOn;

                    if (window._galleryGridOn) {
                        $container.addClass('grid-view');
                        $('.album').addClass('grid');
                        $(this).text('⬜ Pic');
                    } else {
                        $container.removeClass('grid-view');
                        $('.album').removeClass('grid');
                        $(this).text('⠿ Grid');
                    }

                    $('#galleryFilterForm input[name="grid"]').val(window._galleryGridOn ? '1' : '0');
                });
            });
        })();
        </script>

        <div id="toast-container"></div>
        
        <?php
        if ($this->showFloatool()) {
            require $this->spw->getPublicPath() . "/floatool.php";
        }
        
        return ob_get_clean();
    }

    protected function renderSequel(): string
    {
        return '';
    }
}
