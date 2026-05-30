<?php
// public/view_scrollmagic_depth.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\GearMenuModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Depth Maps Viewer";

// Initialize Modules
$gearMenu = new GearMenuModule(['position' => 'manual', 'icon' => '&#9881;', 'icon_size' => '1.5em']);
require __DIR__ . '/entity_icons.php';

//$gearMenu->addStandardActions('frames', $options);

        $actions = [
            'view_frame' => [
                'label' => 'View Frame',
                'icon' => '👁️',
                'callback' => 'window.showFrameDetailsModal(frameId);',
                'condition' => 'frameId > 0'
            ]
        ];
        
        
        
        
        foreach ($actions as $key => $actionData) {
            $gearMenu->addAction('frames', $actionData);
        }

   
$imageEditor = new ImageEditorModule(['modes'=>['mask','crop'],'enable_resize'=>true]);

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

ob_start();
?>

<!-- Deps -->
<link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    /* Base & Theme */
    body { background: #0f0f0f; color: #ddd; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; overflow-anchor: none; }
    
    /* Topbar */
    .topbar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
        background: rgba(16, 16, 16, 0.96);
        border-bottom: 1px solid #333;
        backdrop-filter: blur(8px);
        padding: 8px 16px;
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between;
        min-height: 50px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }
    
    .topbar-brand { font-weight: 600; color: #eee; white-space: nowrap; display: flex; align-items: center; }
    .topbar-controls { display: flex; gap: 10px; align-items: center; }
    .topbar-input { background: #222; border: 1px solid #444; color: #eee; padding: 4px 8px; border-radius: 4px; font-size: 13px; text-align: center; width: 60px; }
    .topbar-input:focus { outline: none; border-color: #0cf; background: #2a2a2a; }
    
    /* Layout */
    .view-container { max-width: 1200px; margin: 0 auto; padding: 10px; min-height: 100vh; padding-top: 80px; transition: padding-top 0.2s; }
    .frames-column { width: 100%; max-width: 1000px; margin: 0 auto; padding-bottom: 100px; }
    
    /* Frame Cards */
    .frame { display: block; position: relative; background: #181818; border-radius: 6px; margin-bottom: 20px; min-height: 200px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
    .frame img.main-img { width: 100%; height: auto; display: block; opacity: 0; transition: opacity 0.3s; border-radius: 6px; position: relative; z-index: 2; }
    .frame.loaded img.main-img { opacity: 1; }
    .placeholder { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #181818; border-radius: 6px; z-index: 1; }
    .frame.loaded .placeholder { display: none !important; }
    .sage-spinner { width: 24px; height: 24px; border: 2px solid rgba(255,255,255,0.1); border-top-color: #888; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 8px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .gear-icon { position: absolute; top: 8px; right: 8px; z-index: 50; color: #fff; background: rgba(0,0,0,0.6); padding: 5px 8px; border-radius: 4px; cursor: pointer; opacity: 0.6; transition: 0.2s; }
    .frame:hover .gear-icon { opacity: 1; background: rgba(0,0,0,0.8); }
    
    .caption-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.95), transparent); padding: 20px 10px 10px; color: #ccc; font-size: 12px; pointer-events: none; border-radius: 0 0 6px 6px; opacity: 0; transition: opacity 0.2s; z-index: 3; }
    .frame:hover .caption-overlay { opacity: 1; }

    /* Scroll Player Overlay */
    .auto-scroll-player {
        position: fixed; bottom: 15px; right: 15px; z-index: 10000;
        background: rgba(0, 0, 0, 0.96); padding: 5px; border-radius: 6px;
        border: 1px solid rgba(255,255,255,0.1); width: 140px; 
        display: flex; flex-direction: column; gap: 4px; box-shadow: 0 4px 16px rgba(0,0,0,0.9);
        cursor: grab; user-select: none; overflow: hidden; 
    }
    .auto-scroll-player:active { cursor: grabbing; }
    .asc-row { display: flex; gap: 2px; justify-content: space-between; width: 100%; }
    .asc-btn { background: transparent; border: 1px solid rgba(255,255,255,0.1); color: #999; padding: 4px 0; border-radius: 3px; cursor: pointer; font-size: 10px; flex: 1; text-align: center; transition: all 0.1s; }
    .asc-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }
    .asc-btn.active { background: rgba(255, 255, 255, 0.25); color: #fff; border-color: rgba(255,255,255,0.4); }
    .asc-slider-row { width: 100%; display: flex; align-items: center; padding: 4px 2px 2px 2px; box-sizing: border-box; }
    .asc-slider { width: 96%; margin: 0 auto; height: 4px; accent-color: #666; cursor: pointer; opacity: 0.8; display: block; }
    .asc-slider:hover { opacity: 1; accent-color: #ccc; }

    /* Restore Button */
    .asc-restore-btn {
        position: fixed; bottom: 15px; right: 15px; z-index: 9999; width: 28px; height: 28px; background: rgba(0, 0, 0, 0.7);
        border: 1px solid rgba(255,255,255,0.15); border-radius: 50%; color: #aaa; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(4px); font-size: 14px;
    }
    .asc-restore-btn:hover { background: rgba(0, 0, 0, 0.95); color: #fff; transform: scale(1.1); box-shadow: 0 0 8px rgba(0,0,0,0.8); border-color: rgba(255,255,255,0.3); }

    .sb-menu { position: absolute !important; }
</style>

<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>
<script src="/js/gear_menu_globals.js"></script>

<!-- Top Bar -->
<div class="topbar" id="main-topbar">
    <div class="topbar-brand">
        <i class="bi bi-layers-fill me-2"></i> DEPTH MAPS
    </div>
    
    <div class="topbar-controls">
        <div style="display:flex; align-items:center; gap:8px;">
             <span id="status" style="font-size:12px; color:#888; font-family:monospace; white-space:nowrap;">...</span>
             <input id="jump-input" class="topbar-input" type="number" min="1" placeholder="Jump #">
        </div>
    </div>
</div>

<div class="view-container" id="view-container">
    <div id="top-sentinel"></div>
    <div id="frames" class="frames-column"></div>
    
    <div id="loader" style="text-align:center; padding:30px; display:none; color:#666;">
        <div class="sage-spinner" style="margin:0 auto;"></div>
        <small>Loading Depth Maps...</small>
    </div>

    <!-- Scroll Player Controls -->
    <div id="autoScrollControls" class="auto-scroll-player" style="display:none;">
        <div class="asc-row">
            <button id="asc-start" class="asc-btn" title="Start"><i class="bi bi-play-fill"></i></button>
            <button id="asc-pause" class="asc-btn active" title="Pause"><i class="bi bi-pause-fill"></i></button>
            <button id="asc-top" class="asc-btn" title="Top"><i class="bi bi-arrow-up"></i></button>
            <button id="asc-close" class="asc-btn" title="Close" style="flex:0; min-width:20px;">&times;</button>
        </div>
        <div class="asc-slider-row">
            <input type="range" id="asc-speed" class="asc-slider" min="1" max="100" value="50" title="Speed">
        </div>
    </div>
    
    <!-- Restore Button -->
    <div id="asc-restore" class="asc-restore-btn" style="display:none;" title="Show Player">
        <i class="bi bi-play-circle"></i>
    </div>
</div>

<script>
(function(){
    const header = document.getElementById('main-topbar');
    const container = document.getElementById('view-container');
    const resizeObs = new ResizeObserver(entries => {
        for (let entry of entries) { container.style.paddingTop = (entry.contentRect.height + 20) + 'px'; }
    });
    resizeObs.observe(header);

    const els = {
        frames: document.getElementById('frames'),
        sentinel: document.getElementById('top-sentinel'),
        status: document.getElementById('status'),
        loader: document.getElementById('loader'),
        jumpInput: document.getElementById('jump-input')
    };

    const API_URL = '/scrollmagic_depth.php';
    const STORAGE_KEY = 'sage_depthmaps_pos';
    const UI_CONFIG = { batch: 30, preload: 4, threshold: 8 };
    const state = { topOffset:0, displayOffset:0, total:null, observer:null, sentinelObserver:null, isLoading:false, allLoaded:false, abortCtrl:null };
    
    function attachGearMenuSafely(newDivs) {
        if (!window.GearMenu || typeof window.GearMenu.attach !== 'function') { setTimeout(() => attachGearMenuSafely(newDivs), 100); return; }
        const dummy = document.createElement('div');
        newDivs.forEach(div => dummy.appendChild(div));
        if (window.jQuery) window.GearMenu.attach(window.jQuery(dummy)); else window.GearMenu.attach(dummy);
        const frag = document.createDocumentFragment();
        while (dummy.firstChild) frag.appendChild(dummy.firstChild);
        return frag;
    }

    function handleJump(e){ if (e.type==='keydown' && e.key!=='Enter') return; let val=parseInt(els.jumpInput.value); if(isNaN(val)||val<1)return; els.jumpInput.blur(); jumpTo(val-1); }
    els.jumpInput.addEventListener('change', handleJump); els.jumpInput.addEventListener('keydown', handleJump);

    function jumpTo(index) {
        if (state.abortCtrl) state.abortCtrl.abort();
        els.frames.innerHTML = '';
        state.topOffset = index; state.displayOffset = index; state.allLoaded = false; state.isLoading = false;
        fetchNextBatch();
    }

    function lockHeight(div,img){ if(!div.style.height){ const h=img.getBoundingClientRect().height; if(h>50){ div.style.height=h+'px'; div.style.minHeight=h+'px'; } } }
    
    function loadImage(div){ 
        const img = div.querySelector('img.main-img'); 
        if(!img || div.classList.contains('loaded')) return; 
        const onLoaded = () => { requestAnimationFrame(() => { lockHeight(div, img); div.classList.add('loaded'); }); };
        if(img.complete && img.naturalHeight > 0) { onLoaded(); } else {
            img.onload = onLoaded;
            img.onerror = () => { const info = div.querySelector('.placeholder small'); if(info) info.innerText = 'Error'; div.querySelector('.sage-spinner')?.remove(); };
            if(img.getAttribute('src') !== img.dataset.src) img.src = img.dataset.src;
        }
    }
    
    function unloadImage(div){ if(!div.style.height) return; const img=div.querySelector('img.main-img'); if(img && img.src){ img.removeAttribute('src'); div.classList.remove('loaded'); } }

    function createDivs(items, startIdx) {
        const divs =[];
        items.forEach((item,i)=>{
            const globalIdx = startIdx + i;
            const div = document.createElement('div');
            div.className = 'frame';
            div.dataset.idx = globalIdx;
            div.dataset.frameId = item.id;
            div.dataset.entity = item.entity;
            div.dataset.entityId = item.entity_id;

            div.innerHTML = `
                <div class="placeholder">
                    <div class="sage-spinner"></div>
                    <small style="color:#666">#${globalIdx+1}</small>
                </div>
                <img class="main-img" data-src="${item.url}" loading="lazy" alt="frame">
                <div class="caption-overlay">
                    <span style="opacity:0.7; margin-right:5px">#${globalIdx+1}</span> ${item.caption}
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
        const res = await fetch(`${API_URL}?offset=${offset}&batch_size=${limit}`, { signal: state.abortCtrl.signal });
        const json = await res.json();
        if(json.error) throw new Error(json.error);
        return json;
    }

    function initObserver(){
        state.observer = new IntersectionObserver(entries=>{ entries.forEach(e=>{ if(e.isIntersecting){ const idx=parseInt(e.target.dataset.idx); updateStatus(idx); manageMemory(idx); } }); }, {rootMargin:'800px 0px'});
        state.sentinelObserver = new IntersectionObserver(e=>{ if(e[0].isIntersecting && state.topOffset>0 && !state.isLoading) fetchPreviousBatch(); }, {rootMargin:'200px 0px 0px 0px'});
        state.sentinelObserver.observe(els.sentinel);
    }
    
    function updateStatus(idx){ els.status.textContent = `${idx+1} / ${state.total||'?'}`; localStorage.setItem(STORAGE_KEY, idx); }
    
    function manageMemory(centerIdx){
        const min=centerIdx-UI_CONFIG.preload, max=centerIdx+UI_CONFIG.preload;
        const kids=els.frames.children;
        for(let i=0; i<kids.length; i++){
            const div=kids[i], idx=parseInt(div.dataset.idx);
            if(Math.abs(idx-centerIdx)<50){ (idx>=min && idx<=max) ? loadImage(div) : unloadImage(div); }
        }
        if(els.frames.lastElementChild){
            const lastIdx=parseInt(els.frames.lastElementChild.dataset.idx);
            if(centerIdx >= lastIdx - UI_CONFIG.threshold && !state.allLoaded) fetchNextBatch();
        }
    }

    async function fetchPreviousBatch(){
        state.isLoading=true;
        const target = Math.max(0, state.topOffset - UI_CONFIG.batch);
        const limit = state.topOffset - target;
        if(limit<=0) { state.isLoading=false; return; }
        try {
            const d = await fetchApi(target, limit);
            if(d.images.length){
                const f = els.frames.firstElementChild;
                const oldTop = f ? f.getBoundingClientRect().top : 0;
                const divs = createDivs(d.images, target);
                els.frames.prepend(attachGearMenuSafely(divs));
                state.topOffset = target;
                if(f) window.scrollBy(0, f.getBoundingClientRect().top - oldTop);
                divs.forEach(loadImage);
            }
        } catch(e){ console.warn(e); } finally { state.isLoading=false; }
    }

    async function fetchNextBatch(){
        if(state.isLoading || state.allLoaded) return;
        state.isLoading=true; els.loader.style.display='block';
        try {
            const d = await fetchApi(state.displayOffset, UI_CONFIG.batch);
            state.total = d.total;
            if(d.images.length) {
                const divs = createDivs(d.images, state.displayOffset);
                els.frames.appendChild(attachGearMenuSafely(divs));
                state.displayOffset += d.images.length;
                if(state.displayOffset >= state.total) state.allLoaded=true;
                if(state.displayOffset === d.images.length) divs.slice(0,5).forEach(loadImage);
            } else { state.allLoaded=true; }
        } catch(e){ if(e.name!=='AbortError') els.status.textContent='Err'; } finally { state.isLoading=false; els.loader.style.display='none'; state.abortCtrl=null; }
    }

    function initAutoScroll() {
        const player = document.getElementById('autoScrollControls');
        const startBtn = document.getElementById('asc-start');
        const pauseBtn = document.getElementById('asc-pause');
        const topBtn = document.getElementById('asc-top');
        const closeBtn = document.getElementById('asc-close');
        const restoreBtn = document.getElementById('asc-restore');
        const speedInput = document.getElementById('asc-speed');
        
        setTimeout(() => player.style.display = 'flex', 1000);

        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        
        player.addEventListener('mousedown', (e) => {
            if(e.target.closest('button') || e.target.closest('input')) return;
            isDragging = true; player.style.bottom = 'auto'; player.style.right = 'auto';
            const rect = player.getBoundingClientRect();
            player.style.left = rect.left + 'px'; player.style.top = rect.top + 'px';
            dragOffset.x = e.clientX - rect.left; dragOffset.y = e.clientY - rect.top;
            player.style.cursor = 'grabbing'; e.preventDefault(); 
        });
        
        window.addEventListener('mousemove', (e) => {
            if(!isDragging) return;
            player.style.left = (e.clientX - dragOffset.x) + 'px';
            player.style.top = (e.clientY - dragOffset.y) + 'px';
        });
        
        window.addEventListener('mouseup', () => { if(isDragging) { isDragging = false; player.style.cursor = 'grab'; } });

        let isPlaying = false, accumulator = 0;

        function animate() {
            if (!isPlaying) return;
            const moveAmt = Math.pow(parseInt(speedInput.value), 2.2) / 1400; 
            accumulator += moveAmt;
            if (accumulator >= 1) {
                const move = Math.floor(accumulator);
                window.scrollBy({ top: move, behavior: 'instant' });
                accumulator -= move;
            }
            requestAnimationFrame(animate);
        }

        function play() { if(isPlaying) return; isPlaying = true; startBtn.classList.add('active'); pauseBtn.classList.remove('active'); animate(); }
        function pause() { isPlaying = false; startBtn.classList.remove('active'); pauseBtn.classList.add('active'); }

        startBtn.onclick = play; pauseBtn.onclick = pause;
        topBtn.onclick = () => { pause(); window.scrollTo({ top: 0, behavior: 'instant' }); };
        closeBtn.onclick = () => { player.style.display = 'none'; restoreBtn.style.display = 'flex'; };
        restoreBtn.onclick = () => { restoreBtn.style.display = 'none'; player.style.display = 'flex'; };

        window.addEventListener('keydown', (e) => {
            if(e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if(e.code === 'Space') { e.preventDefault(); isPlaying ? pause() : play(); }
        });
    }

    initObserver();
    initAutoScroll();
    
    const saved = parseInt(localStorage.getItem(STORAGE_KEY));
    if(saved && saved > 0) { els.jumpInput.value = saved+1; jumpTo(saved); }
    else fetchNextBatch();

})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/scrollmagic.php');
?>