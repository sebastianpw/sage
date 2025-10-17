<?php
// view_scrollmagic.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "ScrollMagic Viewer";
$content = "";

$startFrame = isset($_GET['start']) ? max(1, (int)$_GET['start']) : 1;

// db table parameter (optional) - allow alnum + underscore only for safety; default "frames"
$dbtRaw = isset($_GET['dbt']) ? (string)$_GET['dbt'] : 'frames';
$dbt = preg_replace('/[^A-Za-z0-9_]/', '', $dbtRaw) ?: 'frames';

ob_start();
echo $eruda;	
?>

<link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />

<style>
  body { background:#0f0f0f; color:#ddd; margin:0; padding:0; font-family:system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;}
  .view-container { max-width:1100px; margin:18px auto; padding:10px; }
  .controls { display:flex; gap:12px; align-items:center; margin-bottom:12px; }
  .viewer { background:#111; padding:12px; border-radius:8px; }
  .frames-column { width:100%; margin: 0 auto; max-width:900px; }
  .frame {
    margin: 0;
    display:block;
    text-align:center;
    min-height: 200px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(180deg,#0b0b0b,#111);
    border-radius: 0;
    box-shadow: 0;
  }
  .frame .placeholder {
    padding: 36px 12px;
    color:#666;
    font-size:14px;
  }
  .frame img {
    width:100%;
    height: auto;
    display:block;
    opacity:0;
    transition: opacity .45s ease;
    max-width:100%;
  }
  .frame.loaded img { opacity: 1; }
  .frame .caption {
    padding:8px 10px;
    font-size:13px;
    color:#bbb;
    text-align:left;
    background: rgba(0,0,0,0.45);
  }
  .status { color:#9aa; font-size:13px; margin-left:auto; }
  .loading-indicator { color:#888; font-size:13px; }
</style>

<div class="view-container">
  <h2 style="margin:0 0 12px 0; color:#eee; font-weight:600;">ScrollMagic vertical viewer</h2>

  <div id="gallery-config" style="display:none"
       data-api-url="/scrollmagic_images.php"
       data-batch-size="200"
       data-preload-window="6"
       data-dbt="<?= htmlspecialchars($dbt, ENT_QUOTES) ?>"
       data-start-frame="<?= (int)$startFrame ?>"></div>

  <div class="controls" style="display: none;">
    <div>
      <label>Batch size:
        <input id="batch-size" type="number" value="200" style="width:90px"/>
      </label>
      <label style="margin-left:8px">Preload window:
        <input id="preload-window" type="number" value="6" style="width:70px"/>
      </label>
    </div>
    <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
      <div class="loading-indicator" id="loading-indicator" style="display:none">loading…</div>
      <div class="status" id="status">0 / 0</div>
    </div>
  </div>

  <div class="viewer">
    <div id="frames" class="frames-column" aria-live="polite"></div>
  </div>
</div>

<!-- ScrollMagic: assume vendor/ScrollMagic/ScrollMagic.min.js exists; fallback to CDN if not -->
<script>
(function(){
  // inject ScrollMagic if local vendor not present (graceful fallback)
  function loadScript(src, cb){
    const s = document.createElement('script');
    s.src = src;
    s.onload = cb;
    s.onerror = cb;
    document.head.appendChild(s);
  }
  // try to use local copy first
  if (typeof ScrollMagic === 'undefined') {
    loadScript('/vendor/ScrollMagic/ScrollMagic.min.js', function(){
      if (typeof ScrollMagic === 'undefined') {
        // final fallback to CDN
        loadScript('https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.8/ScrollMagic.min.js', function(){ /* ok */});
      }
    });
  }
})();
</script>

<script>
(function(){
  const cfg = document.getElementById('gallery-config');
  const apiUrl = cfg.dataset.apiUrl;
  const dbt = cfg.dataset.dbt || '';               // new db table param
  let batchSize = parseInt(cfg.dataset.batchSize, 10) || 200;
  let preloadWindow = parseInt(cfg.dataset.preloadWindow, 10) || 6;
  const startFrame = Math.max(1, parseInt(cfg.dataset.startFrame, 10) || 1);
  let offset = 0;
  let total = null;
  let loading = false;
  let images = []; // metadata array
  const framesContainer = document.getElementById('frames');
  const loadingIndicator = document.getElementById('loading-indicator');
  const statusEl = document.getElementById('status');

  // allow controls to override defaults
  document.getElementById('batch-size').addEventListener('change', e => { batchSize = Math.max(1, parseInt(e.target.value,10)||1); });
  document.getElementById('preload-window').addEventListener('change', e => { preloadWindow = Math.max(1, parseInt(e.target.value,10)||1); });

  function showLoading(v){ loadingIndicator.style.display = v ? 'inline-block' : 'none'; }
  function setStatus(idx){ statusEl.textContent = `${(idx||0)+1} / ${total ?? '…'}`; }

  // helper to create DOM frame placeholder
  function createFrameNode(item, index){
    const div = document.createElement('div');
    div.className = 'frame';
    div.dataset.index = index;
    div.dataset.url = item.url;
    div.dataset.w = item.w || 0;
    div.dataset.h = item.h || 0;

    const placeholder = document.createElement('div');
    placeholder.className = 'placeholder';
    placeholder.textContent = `Frame #${index+1} — Loading when scrolled into view…`;
    div.appendChild(placeholder);

    // image element (not set src yet)
    const img = document.createElement('img');
    img.setAttribute('data-src', item.url);
    img.loading = 'lazy';
    img.alt = item.caption || '';
    div.appendChild(img);

    return div;
  }

  // ScrollMagic controller (create once ScrollMagic is loaded)
  let controller = null;
  function getController(){
    if (controller) return controller;
    if (typeof ScrollMagic === 'undefined') {
      // fallback to basic IntersectionObserver if ScrollMagic missing
      controller = { fallback: true };
      return controller;
    }
    controller = new ScrollMagic.Controller();
    return controller;
  }

  // we'll keep scenes in an array to be able to remove them if needed
  const scenes = [];

  function attachScene(frameEl, idx){
    const ctrl = getController();
    if (ctrl.fallback) {
      // IntersectionObserver fallback
      if (!attachScene._io) {
        attachScene._io = new IntersectionObserver(entries => {
          entries.forEach(entry => {
            const el = entry.target;
            const i = parseInt(el.dataset.index, 10);
            if (entry.isIntersecting) {
              onEnter(i, el);
            } else {
              onLeave(i, el);
            }
          });
        }, { root: null, rootMargin: '400px 0px 400px 0px', threshold: 0.01 });
      }
      attachScene._io.observe(frameEl);
      return null;
    } else {
      const scene = new ScrollMagic.Scene({
        triggerElement: frameEl,
        triggerHook: 0.9,
        offset: 0,
        reverse: true
      });
      scene.on('enter', function(e){
        onEnter(idx, frameEl);
      });
      scene.on('leave', function(e){
        onLeave(idx, frameEl);
      });
      scene.addTo(controller);
      scenes.push(scene);
      return scene;
    }
  }

  // load image into DOM (sets src)
  function loadImage(el){
    const img = el.querySelector('img');
    if (!img) return;
    if (img.dataset.src && img.src !== img.dataset.src) {
      img.src = img.dataset.src;
      img.addEventListener('load', function onL(){
        el.classList.add('loaded');
        const ph = el.querySelector('.placeholder');
        if (ph) ph.style.display = 'none';
        img.removeEventListener('load', onL);
      });
    }
  }

  // unload image to free memory (remove src but keep data-src)
  function unloadImage(el){
    const img = el.querySelector('img');
    if (!img) return;
    // remove src to free memory (only if outside window)
    if (img.src) {
      img.removeAttribute('src');
      el.classList.remove('loaded');
      const ph = el.querySelector('.placeholder');
      if (ph) ph.style.display = '';
    }
  }

  // called when a frame enters viewport region
  function onEnter(index, el){
    setStatus(index);
    ensureWindowLoaded(index);
  }

  function onLeave(index, el){
    // do nothing immediate — unloading is handled by ensureWindowLoaded when window moves
  }

  function ensureWindowLoaded(centerIndex){
    // load frames in [centerIndex - preloadWindow , centerIndex + preloadWindow]
    const lo = Math.max(0, centerIndex - preloadWindow);
    const hi = Math.min(images.length - 1, centerIndex + preloadWindow);

    for (let i = 0; i < images.length; i++){
      const node = framesContainer.querySelector(`.frame[data-index="${i}"]`);
      if (!node) continue;
      if (i >= lo && i <= hi) {
        loadImage(node);
      } else {
        unloadImage(node);
      }
    }

    // If approaching end of loaded list, fetch more
    if ((total === null || offset < total) && (centerIndex + preloadWindow + 2 >= images.length)) {
      loadNextBatch();
    }
  }

  // append a batch of images
  function appendBatch(items){
    const startIndex = images.length;
    items.forEach((it, i) => {
      const idx = startIndex + i;
      images.push(it);
      const node = createFrameNode(it, idx);
      framesContainer.appendChild(node);
      // attach ScrollMagic scene / IO
      attachScene(node, idx);
    });
  }

  // fetch next batch
  function loadNextBatch(){
    if (loading) return Promise.resolve();
    loading = true;
    showLoading(true);
    let url = `${apiUrl}?offset=${offset}&limit=${batchSize}`;
    if (dbt) url += `&dbt=${encodeURIComponent(dbt)}`;
    return fetch(url).then(r => {
      if (!r.ok) throw new Error('fetch failed');
      return r.json();
    }).then(json => {
      total = json.total ?? total;
      const items = json.images || [];
      appendBatch(items);
      offset += items.length;
    }).catch(err => {
      console.error('Batch load error', err);
    }).finally(() => {
      loading = false;
      showLoading(false);
      // if initial startFrame specified, jump to that index
      if (startFrame > 1 && images.length >= startFrame && !ensureWindowLoaded._jumped) {
        // scroll to the startFrame element
        ensureWindowLoaded._jumped = true;
        const el = framesContainer.querySelector(`.frame[data-index="${startFrame-1}"]`);
        if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 40, behavior: 'auto' });
        ensureWindowLoaded(startFrame-1);
      }
    });
  }

  // init: iterative loading until we hit at least startFrame
  (async function init(){
    // progressively load until we have at least a handful or reach requested start
    while ((total === null || offset < total) && images.length < Math.max(10, startFrame)) {
      // eslint-disable-next-line no-await-in-loop
      await loadNextBatch();
    }
    // ensure first few are loaded
    ensureWindowLoaded(Math.max(0, startFrame-1));
  })();

})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
