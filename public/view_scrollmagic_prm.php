<?php
// public/view_scrollmagic_prm.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\GearMenuModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Sage Viewer PRM";

// --- Read all parameters from URL GET ---
$entity_type = $_GET['entity_type'] ?? '';
$entity_id = $_GET['entity_id'] ?? '';
$from_frame_id = $_GET['from_frame_id'] ?? '';
$limit = $_GET['limit'] ?? '';
$query = $_GET['query'] ?? '';
$sort_by = $_GET['sort_by'] ?? '';
$sort_order = $_GET['sort_order'] ?? '';

// Initialize GearMenu Module
$gearMenu = new GearMenuModule([
    'position' => 'manual', 
    'icon' => '&#9881;',
    'icon_size' => '1.5em',
]);

// Add actions for all entity types
require __DIR__ . '/entity_icons.php';
foreach (array_keys($entityIcons) as $entity) {
    $gearMenu->addAction($entity, [
        'label' => 'View Frame',
        'icon' => '👁️',
        'callback' => 'window.showFrameDetailsModal(frameId);'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Import to Generative',
        'icon' => '⚡',
        'callback' => 'window.importGenerative(entity, entityId, frameId);'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Edit Entity',
        'icon' => '✏️',
        'callback' => 'window.showEntityFormInModal(entity, entityId);'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Edit Image',
        'icon' => '🖌️',
        'callback' => 'const $w = $(wrapper); ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find(\'img.main-img\').attr(\'data-src\') || $w.find(\'img.main-img\').attr(\'src\') });'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'View Frame Chain',
        'icon' => '🔗',
        'callback' => 'window.showFrameChainInModal(frameId);'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Add to Storyboard',
        'icon' => '🎬',
        'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Assign to Composite',
        'icon' => '🧩',
        'callback' => 'window.showImportEntityModal({ source: entity, target: "composites", source_entity_id: entityId, frame_id: frameId, target_entity_id: null, limit: 1, copy_name_desc: 0, composite: 1 });'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Import to ControlNet Map',
        'icon' => '☠️',
        'callback' => 'window.importControlNetMap(entity, entityId, frameId);'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Use Prompt Matrix',
        'icon' => '🌌',
        'callback' => 'window.usePromptMatrix(entity, entityId, frameId);'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'Delete Frame',
        'icon' => '🗑️',
        'callback' => 'window.deleteFrame(entity, entityId, frameId);'
    ]);
}

$gearMenu->addAction('generatives', [
    'label' => 'View Frame Chain',
    'icon' => '🔗',
    'callback' => 'window.showFrameChainInModal(frameId);',
    'condition' => 'entity === "generatives"'
]);

// Initialize Image Editor Module
$imageEditor = new ImageEditorModule([
    'modes' => ['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true,
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' => [
        'grayscale', 'vintage', 'sepia', 'clarendon',
        'gingham', 'moon', 'lark', 'reyes', 'juno', 'slumber'
    ],
]);

// Load frame details modal
ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

ob_start();
?>

<!-- Dependencies -->
<link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    body { 
        background: #0f0f0f; 
        color: #ddd; 
        margin: 0; 
        padding: 0; 
        font-family: system-ui, -apple-system, sans-serif;
        /* CRITICAL: Manually handle scroll anchoring for infinite scroll */
        overflow-anchor: none;
    }

    /* Fixed Top Bar */
    .topbar { 
        display: flex; 
        gap: 12px; 
        align-items: center; 
        padding: 0 16px;
        background: rgba(20,20,20,0.95);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        
        position: fixed; 
        top: 0; 
        left: 0;
        right: 0;
        height: 50px; /* Fixed height */
        z-index: 9999; /* Always on top */
        backdrop-filter: blur(8px);
    }

    .view-container { 
        max-width: 1100px; 
        margin: 0 auto; 
        padding: 0 10px; 
        min-height: 100vh;
        /* Push content down so it starts below the fixed header */
        padding-top: 60px; 
    }

    .frames-column { 
        width: 100%; 
        max-width: 900px; 
        margin: 0 auto; 
        padding-bottom: 100px;
    }

    /* Top Sentinel for upward scroll detection */
    #top-sentinel {
        height: 10px;
        width: 100%;
        margin-bottom: 10px;
        opacity: 0;
        pointer-events: none;
    }

    .frame { 
        display: block; 
        position: relative; 
        background: #111;
        border-radius: 8px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.3); 
        margin-bottom: 24px;
        min-height: 250px; 
        overflow: visible; 
    }

    .frame .placeholder { 
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: linear-gradient(180deg, #161616 0%, #1a1a1a 100%);
        z-index: 1;
        border-radius: 8px;
    }
    
    .sage-spinner {
        width: 30px; height: 30px;
        border: 3px solid rgba(255,255,255,0.1);
        border-top-color: #777;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-bottom: 10px;
    }
    
    .frame-info { color: #555; font-size: 11px; font-family: monospace; }
    
    .frame img.main-img { 
        width: 100%; 
        height: auto; 
        display: block; 
        opacity: 0; 
        transition: opacity 0.3s ease; 
        position: relative;
        z-index: 2; 
        border-radius: 8px;
    }
    
    .frame.loaded img.main-img { opacity: 1; }
    .frame.loaded .placeholder { display: none; }

    .status { margin-left: auto; font-size: 13px; color: #888; font-family: monospace; }

    .topbar-input {
        background: #222; 
        border: 1px solid #444; 
        color: #ddd; 
        text-align: center; 
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 13px;
        width: 70px;
    }
    .topbar-input:focus {
        border-color: #0cf;
        outline: none;
        background: #333;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* --- GEAR MENU & OVERLAYS --- */
    .frame .gear-icon {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 100;
        color: #fff;
        background: rgba(0,0,0,0.5);
        padding: 6px 8px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1.4em;
        line-height: 1;
        opacity: 0.6;
        transition: opacity 0.2s, background 0.2s;
        text-shadow: 0 1px 3px rgba(0,0,0,0.8);
        pointer-events: auto;
    }
    .frame:hover .gear-icon {
        opacity: 1;
        background: rgba(0,0,0,0.8);
    }
    
    .caption-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
        color: #ccc;
        font-size: 13px;
        padding: 20px 12px 12px 12px;
        z-index: 10;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none; 
    }
    .frame:hover .caption-overlay { opacity: 1; }
</style>

<!-- Gear Menu Module -->
<?= $gearMenu->render() ?>

<!-- Image Editor Module -->
<?= $imageEditor->render() ?>

<!-- Frame Details Modal -->
<?= $frameDetailsModal ?>

<!-- Gear Menu Global Functions -->
<script src="/js/gear_menu_globals.js"></script>

<!-- Top Bar -->
<div class="topbar">
    <div style="color: #ddd; font-weight: 500;">
        <i class="bi bi-images me-2"></i>SAGE Viewer <span style="font-size:0.8em; opacity:0.5; margin-left:5px;">PRM Edition</span>
    </div>
    
    <div style="font-size: 12px; display:flex; gap:8px; align-items:center; margin-left:20px;">
        <label>Jump to:</label>
        <input id="jump-input" class="topbar-input" type="number" min="1" placeholder="#">
    </div>

    <div class="status" id="status">Initializing...</div>
</div>

<div class="view-container">
    <div id="top-sentinel"></div>
    <div id="frames" class="frames-column"></div>
    
    <div id="loader" style="text-align: center; padding: 20px; display: none; color: #666;">
        <div class="sage-spinner" style="margin: 0 auto;"></div>
        <div style="margin-top: 10px; font-size: 12px;">Fetching from PRM...</div>
    </div>

    <div id="gallery-config" style="display:none"
       data-api-url="/scrollmagic_images_prm.php"
       data-entity-type="<?= htmlspecialchars($entity_type, ENT_QUOTES) ?>"
       data-entity-id="<?= htmlspecialchars($entity_id, ENT_QUOTES) ?>"
       data-from-frame-id="<?= htmlspecialchars($from_frame_id, ENT_QUOTES) ?>"
       data-limit="<?= htmlspecialchars($limit, ENT_QUOTES) ?>"
       data-query="<?= htmlspecialchars($query, ENT_QUOTES) ?>"
       data-sort-by="<?= htmlspecialchars($sort_by, ENT_QUOTES) ?>" 
       data-sort-order="<?= htmlspecialchars($sort_order, ENT_QUOTES) ?>"></div>
</div>

<script>
    (function() {
        const cfgEl = document.getElementById('gallery-config');
        
        const API_CONFIG = {
            url: cfgEl.dataset.apiUrl,
            entityType: cfgEl.dataset.entityType,
            entityId: cfgEl.dataset.entityId,
            fromFrameId: cfgEl.dataset.fromFrameId,
            limit: cfgEl.dataset.limit,
            query: cfgEl.dataset.query,
            sortBy: cfgEl.dataset.sortBy,
            sortOrder: cfgEl.dataset.sortOrder
        };

        const STORAGE_KEY = `sage_pos_${API_CONFIG.entityType || 'all'}_${API_CONFIG.entityId || '0'}_${API_CONFIG.query ? 'q' : 'std'}`;

        const UI_CONFIG = {
            batch: 30,
            preload: 5,
            threshold: 8
        };

        const state = {
            topOffset: 0,
            displayOffset: 0,
            total: null,
            observer: null,
            sentinelObserver: null,
            isLoading: false,
            allLoaded: false,
            abortCtrl: null 
        };

        const els = {
            frames: document.getElementById('frames'),
            sentinel: document.getElementById('top-sentinel'),
            status: document.getElementById('status'),
            loader: document.getElementById('loader'),
            jumpInput: document.getElementById('jump-input')
        };

        function attachGearMenuSafely(newDivs) {
            if (!window.GearMenu || typeof window.GearMenu.attach !== 'function') {
                setTimeout(() => attachGearMenuSafely(newDivs), 100);
                return;
            }
            const dummy = document.createElement('div');
            newDivs.forEach(div => dummy.appendChild(div));
            
            if (window.jQuery) window.GearMenu.attach(window.jQuery(dummy));
            else window.GearMenu.attach(dummy);
            
            const frag = document.createDocumentFragment();
            while (dummy.firstChild) frag.appendChild(dummy.firstChild);
            
            return frag;
        }

        // --- 1. JUMP LOGIC ---
        function handleJump(e) {
            if (e.type === 'keydown' && e.key !== 'Enter') return;
            let val = parseInt(els.jumpInput.value);
            if (isNaN(val) || val < 1) return;
            els.jumpInput.blur();
            jumpTo(val - 1);
        }

        els.jumpInput.addEventListener('change', handleJump);
        els.jumpInput.addEventListener('keydown', handleJump);

        function jumpTo(index) {
            if (state.abortCtrl) state.abortCtrl.abort();

            els.frames.innerHTML = '';
            state.topOffset = index;
            state.displayOffset = index; 
            state.allLoaded = false;
            state.isLoading = false; 
            
            localStorage.setItem(STORAGE_KEY, index);
            fetchNextBatch();
        }

        // --- 2. ENGINE ---
        function lockHeight(div, img) {
            if (!div.style.height) {
                const h = img.getBoundingClientRect().height;
                if (h > 50) {
                    div.style.height = h + 'px';
                    div.style.minHeight = h + 'px';
                }
            }
        }

        function loadImage(div) {
            const img = div.querySelector('img.main-img');
            if (!img || div.classList.contains('loaded') || img.src) return;

            img.onload = () => {
                requestAnimationFrame(() => {
                    lockHeight(div, img);
                    div.classList.add('loaded');
                });
            };
            img.onerror = () => {
                const info = div.querySelector('.frame-info');
                if(info) info.textContent = 'Load Failed';
            };
            img.src = img.dataset.src;
        }

        function unloadImage(div) {
            if (!div.style.height) return;
            const img = div.querySelector('img.main-img');
            if (img && img.src) {
                img.removeAttribute('src');
                div.classList.remove('loaded');
            }
        }

        // --- 3. OBSERVER ---
        function initObserver() {
            // General Item Observer
            state.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const idx = parseInt(entry.target.dataset.idx);
                        updateStatus(idx);
                        manageMemory(idx);
                    }
                });
            }, { rootMargin: '800px 0px 800px 0px' });

            // Sentinel for Upward Scroll
            state.sentinelObserver = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    if (state.topOffset > 0 && !state.isLoading) {
                        fetchPreviousBatch();
                    }
                }
            }, { 
                // Increased margin to trigger earlier while scrolling up
                rootMargin: '200px 0px 0px 0px' 
            });
            
            state.sentinelObserver.observe(els.sentinel);
        }

        function updateStatus(absoluteIdx) {
            const current = absoluteIdx + 1;
            const totalStr = state.total ? state.total : '...';
            els.status.innerHTML = `<span style="color:#fff; font-weight:bold;">${current}</span> / ${totalStr}`;
            localStorage.setItem(STORAGE_KEY, absoluteIdx);
        }

        function manageMemory(centerAbsoluteIdx) {
            const min = centerAbsoluteIdx - UI_CONFIG.preload;
            const max = centerAbsoluteIdx + UI_CONFIG.preload;
            const children = els.frames.children;
            
            for (let i = 0; i < children.length; i++) {
                const div = children[i];
                const idx = parseInt(div.dataset.idx);
                if (Math.abs(idx - centerAbsoluteIdx) < 50) {
                    if (idx >= min && idx <= max) loadImage(div);
                    else unloadImage(div);
                }
            }

            const lastChild = els.frames.lastElementChild;
            if (lastChild) {
                const lastIdx = parseInt(lastChild.dataset.idx);
                if (centerAbsoluteIdx >= lastIdx - UI_CONFIG.threshold && !state.allLoaded) {
                    fetchNextBatch();
                }
            }
        }

        function createDivs(items, startIdx) {
            const divs = [];
            items.forEach((item, i) => {
                const globalIdx = startIdx + i;
                const div = document.createElement('div');
                div.className = 'frame';
                div.dataset.idx = globalIdx;
                div.dataset.entity = item.entity || API_CONFIG.entityType;
                div.dataset.entityId = item.entity_id || API_CONFIG.entityId;
                div.dataset.frameId = item.id;
                
                div.innerHTML = `
                    <div class="placeholder">
                        <div class="sage-spinner"></div>
                        <div class="frame-info">#${globalIdx + 1}</div>
                    </div>
                    <img class="main-img" data-src="${item.url}" loading="lazy" alt="Frame">
                    <div class="caption-overlay">
                        <span style="opacity:0.6; font-size:0.85em; margin-right:6px">#${globalIdx + 1}</span> 
                        ${item.caption || item.filename}
                    </div>
                    <span class="gear-icon">&#9881;</span>
                `;
                state.observer.observe(div);
                divs.push(div);
            });
            return divs;
        }

        async function fetchApi(offset, limit) {
            state.abortCtrl = new AbortController();
            const signal = state.abortCtrl.signal;

            const params = new URLSearchParams({ offset: offset, batch_size: limit });
            if (API_CONFIG.entityType) params.set('entity_type', API_CONFIG.entityType);
            if (API_CONFIG.entityId) params.set('entity_id', API_CONFIG.entityId);
            if (API_CONFIG.fromFrameId) params.set('from_frame_id', API_CONFIG.fromFrameId);
            if (API_CONFIG.limit) params.set('limit', API_CONFIG.limit);
            if (API_CONFIG.query) params.set('query', API_CONFIG.query);
            if (API_CONFIG.sortBy) params.set('sort_by', API_CONFIG.sortBy);
            if (API_CONFIG.sortOrder) params.set('sort_order', API_CONFIG.sortOrder);

            const res = await fetch(`${API_CONFIG.url}?${params.toString()}`, { signal });
            if (!res.ok) throw new Error(res.statusText);
            const json = await res.json();
            if (json.error) throw new Error(json.error);
            return json;
        }

        // --- PREPEND (UP) ---
        async function fetchPreviousBatch() {
            if (state.isLoading) return;
            state.isLoading = true;
            
            try {
                const batchSize = UI_CONFIG.batch;
                const targetOffset = Math.max(0, state.topOffset - batchSize);
                const limit = state.topOffset - targetOffset;
                
                if (limit <= 0) { state.isLoading = false; return; }

                const data = await fetchApi(targetOffset, limit);
                
                if (data.images.length > 0) {
                    prependItems(data.images, targetOffset);
                    state.topOffset = targetOffset;
                }
            } catch(e) {
                if (e.name !== 'AbortError') console.error(e);
            } finally {
                state.isLoading = false;
            }
        }

        function prependItems(items, startIdx) {
            const anchorNode = els.frames.firstElementChild;
            if (!anchorNode) return;

            const topBefore = anchorNode.getBoundingClientRect().top;
            const newDivs = createDivs(items, startIdx);
            const frag = attachGearMenuSafely(newDivs);

            els.frames.prepend(frag);

            const topAfter = anchorNode.getBoundingClientRect().top;
            const delta = topAfter - topBefore;

            // Manual adjustment for overflow-anchor: none
            window.scrollBy(0, delta);
            newDivs.forEach(d => loadImage(d));
        }

        // --- APPEND (DOWN) ---
        async function fetchNextBatch() {
            if (state.isLoading || state.allLoaded) return;
            state.isLoading = true;
            els.loader.style.display = 'block';

            try {
                const data = await fetchApi(state.displayOffset, UI_CONFIG.batch);
                state.total = data.total;
                if (data.images.length > 0) {
                    appendItems(data.images);
                } else {
                    state.allLoaded = true;
                }
                if (state.displayOffset >= state.total) state.allLoaded = true;
            } catch(e) {
                if (e.name !== 'AbortError') {
                    console.error(e);
                    els.status.innerHTML = 'Error loading';
                }
            } finally {
                state.isLoading = false;
                els.loader.style.display = 'none';
                state.abortCtrl = null;
            }
        }

        function appendItems(items) {
            const startIdx = state.displayOffset;
            const newDivs = createDivs(items, startIdx);
            const frag = attachGearMenuSafely(newDivs);

            els.frames.appendChild(frag);
            state.displayOffset += items.length;

            if (startIdx === state.topOffset) {
                 setTimeout(() => {
                    const firstItems = els.frames.querySelectorAll('.frame');
                    for(let i=0; i<Math.min(firstItems.length, 5); i++) loadImage(firstItems[i]);
                    updateStatus(startIdx);
                }, 50);
            }
        }

        function init() {
            initObserver();
            const savedPos = localStorage.getItem(STORAGE_KEY);
            if (savedPos) {
                const idx = parseInt(savedPos);
                if (!isNaN(idx) && idx > 0) {
                    els.jumpInput.value = idx + 1;
                    jumpTo(idx);
                } else {
                    fetchNextBatch();
                }
            } else {
                fetchNextBatch();
            }
        }

        init();
    })();
</script>

<div id="toast-container"></div>

<?php
$content = ob_get_clean();
$spw->renderLayout(
    $content, 
    $pageTitle,
    $spw->getProjectPath() . '/templates/scrollmagic.php'   
);
?>