<?php
// public/view_scrollmagic_prm.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\GearMenuModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "ScrollMagic";

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
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
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
        'callback' => 'const $w = $(wrapper); ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find(\'img\').attr(\'src\') });'
    ]);
    
    $gearMenu->addAction($entity, [
        'label' => 'View Frame Chain',
        'icon' => '🔗', // A chain link icon
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
        'callback' => 'window.showImportEntityModal({
            source: entity,
            target: "composites",
            source_entity_id: entityId,
            frame_id: frameId,
            target_entity_id: null,
            limit: 1,
            copy_name_desc: 0,
            composite: 1
        });'
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

// Add special action for generatives
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

<link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

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
  .frame .placeholder { padding: 36px 12px; color:#666; font-size:14px; }
  .frame img { width:100%; height: auto; display:block; opacity:0; transition: opacity .45s ease; max-width:100%; }
  .frame.loaded img { opacity: 1; }
  .frame .caption { padding:8px 10px; font-size:13px; color:#bbb; text-align:left; background: rgba(0,0,0,0.45); }
  .status { color:#9aa; font-size:13px; margin-left:auto; }
  .loading-indicator { color:#888; font-size:13px; }
</style>

<!-- Gear Menu Module -->
<?= $gearMenu->render() ?>

<!-- Image Editor Module -->
<?= $imageEditor->render() ?>

<!-- Frame Details Modal -->
<?= $frameDetailsModal ?>

<!-- Gear Menu Global Functions -->
<script src="/js/gear_menu_globals.js"></script>

<div class="view-container">
  <h2 style="display:none; margin:0 0 12px 0; color:#eee; font-weight:600;">ScrollMagic vertical viewer (Parametrized)</h2>

  <div id="gallery-config" style="display:none"
       data-api-url="/scrollmagic_images_prm.php"
       data-batch-size="200"
       data-preload-window="6"
       data-entity-type="<?= htmlspecialchars($entity_type, ENT_QUOTES) ?>"
       data-entity-id="<?= htmlspecialchars($entity_id, ENT_QUOTES) ?>"
       data-from-frame-id="<?= htmlspecialchars($from_frame_id, ENT_QUOTES) ?>"
       data-limit="<?= htmlspecialchars($limit, ENT_QUOTES) ?>"
       data-query="<?= htmlspecialchars($query, ENT_QUOTES) ?>"
       data-sort-by="<?= htmlspecialchars($sort_by, ENT_QUOTES) ?>" 
       data-sort-order="<?= htmlspecialchars($sort_order, ENT_QUOTES) ?>"></div>

  <div class="controls" style="display: none;">
    <div>
      <label>Batch size: <input id="batch-size" type="number" value="200" style="width:90px"/></label>
      <label style="margin-left:8px">Preload window: <input id="preload-window" type="number" value="6" style="width:70px"/></label>
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

<script>
(function(){
  function loadScript(src, cb){ const s = document.createElement('script'); s.src = src; s.onload = cb; s.onerror = cb; document.head.appendChild(s); }
  if (typeof ScrollMagic === 'undefined') { loadScript('/vendor/ScrollMagic/ScrollMagic.min.js', function(){ if (typeof ScrollMagic === 'undefined') { loadScript('https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.8/ScrollMagic.min.js', function(){}); } }); }
})();
</script>

<script>
(function(){
  const cfg = document.getElementById('gallery-config');
  const apiUrl = cfg.dataset.apiUrl;
  
  const entityType = cfg.dataset.entityType || '';
  const entityId = cfg.dataset.entityId || '';
  const fromFrameId = cfg.dataset.fromFrameId || '';
  const queryLimit = cfg.dataset.limit || '';
  const rawQuery = cfg.dataset.query || '';
  const sortBy = cfg.dataset.sortBy || '';
  const sortOrder = cfg.dataset.sortOrder || '';

  let batchSize = parseInt(cfg.dataset.batchSize, 10) || 200;
  let preloadWindow = parseInt(cfg.dataset.preloadWindow, 10) || 6;
  let offset = 0;
  let total = null;
  let loading = false;
  let images = [];
  const framesContainer = document.getElementById('frames');
  const loadingIndicator = document.getElementById('loading-indicator');
  const statusEl = document.getElementById('status');

  document.getElementById('batch-size').addEventListener('change', e => { batchSize = Math.max(1, parseInt(e.target.value,10)||1); });
  document.getElementById('preload-window').addEventListener('change', e => { preloadWindow = Math.max(1, parseInt(e.target.value,10)||1); });

  function showLoading(v){ loadingIndicator.style.display = v ? 'inline-block' : 'none'; }
  function setStatus(idx){ statusEl.textContent = `${(idx||0)+1} / ${total ?? '…'}`; }

  function createFrameNode(item, index){
    const div = document.createElement('div');
    div.className = 'frame';
    div.dataset.index = index;
    div.dataset.url = item.url;
    div.dataset.w = item.w || 0;
    div.dataset.h = item.h || 0;
    
    // Add data attributes for gear menu
    div.dataset.entity = item.entity || entityType;
    div.dataset.entityId = item.entity_id || entityId;
    div.dataset.frameId = item.id;
    
    const placeholder = document.createElement('div');
    placeholder.className = 'placeholder';
    placeholder.textContent = `Frame #${item.id || index+1} — Loading…`;
    div.appendChild(placeholder);
    
    const img = document.createElement('img');
    img.setAttribute('data-src', item.url);
    img.loading = 'lazy';
    img.alt = item.caption || '';
    div.appendChild(img);
    
    // Add gear icon
    const gearIcon = document.createElement('span');
    gearIcon.className = 'gear-icon';
    gearIcon.innerHTML = '&#9881;';
    div.appendChild(gearIcon);
    
    return div;
  }

  let controller = null;
  function getController(){ if (controller) return controller; if (typeof ScrollMagic === 'undefined') { return (controller = { fallback: true }); } return (controller = new ScrollMagic.Controller()); }

  function attachScene(frameEl, idx){
    const ctrl = getController();
    if (ctrl.fallback) {
      if (!attachScene._io) { attachScene._io = new IntersectionObserver(entries => { entries.forEach(entry => { const el = entry.target; const i = parseInt(el.dataset.index, 10); if (entry.isIntersecting) onEnter(i, el); }); }, { root: null, rootMargin: '400px 0px 400px 0px', threshold: 0.01 }); }
      attachScene._io.observe(frameEl);
    } else { const scene = new ScrollMagic.Scene({ triggerElement: frameEl, triggerHook: 0.9, reverse: true }); scene.on('enter', (e) => onEnter(idx, frameEl)); scene.addTo(controller); }
  }

  function loadImage(el){ const img = el.querySelector('img'); if (img && img.dataset.src && img.src !== img.dataset.src) { img.src = img.dataset.src; img.addEventListener('load', function onL(){ el.classList.add('loaded'); const ph = el.querySelector('.placeholder'); if (ph) ph.style.display = 'none'; img.removeEventListener('load', onL); }); } }
  function unloadImage(el){ const img = el.querySelector('img'); if (img && img.src) { img.removeAttribute('src'); el.classList.remove('loaded'); const ph = el.querySelector('.placeholder'); if (ph) ph.style.display = ''; } }

  function onEnter(index, el){ setStatus(index); ensureWindowLoaded(index); }

  function ensureWindowLoaded(centerIndex){
    const lo = Math.max(0, centerIndex - preloadWindow); const hi = Math.min(images.length - 1, centerIndex + preloadWindow);
    for (let i = 0; i < images.length; i++){ const node = framesContainer.querySelector(`.frame[data-index="${i}"]`); if (node) { if (i >= lo && i <= hi) loadImage(node); else unloadImage(node); } }
    if ((total === null || offset < total) && (centerIndex + preloadWindow + 2 >= images.length)) { loadNextBatch(); }
  }

  function appendBatch(items){ 
    const startIndex = images.length; 
    items.forEach((it, i) => { 
      const idx = startIndex + i; 
      images.push(it); 
      const node = createFrameNode(it, idx); 
      framesContainer.appendChild(node); 
      attachScene(node, idx);
      
      // Attach gear menu to this new node
      if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
        window.GearMenu.attach(node);
      }
    }); 
  }

  function loadNextBatch(){
    if (loading || (total !== null && offset >= total)) return Promise.resolve();
    loading = true; showLoading(true);

    const params = new URLSearchParams({ offset: offset, batch_size: batchSize });
    if (entityType) params.set('entity_type', entityType);
    if (entityId) params.set('entity_id', entityId);
    if (fromFrameId) params.set('from_frame_id', fromFrameId);
    if (queryLimit) params.set('limit', queryLimit);
    if (rawQuery) params.set('query', rawQuery);
    if (sortBy) params.set('sort_by', sortBy);
    if (sortOrder) params.set('sort_order', sortOrder);
    const url = `${apiUrl}?${params.toString()}`;

    return fetch(url).then(r => { if (!r.ok) throw new Error(`Fetch failed: ${r.statusText}`); return r.json(); }).then(json => {
      if (json.error) throw new Error(json.error + (json.details ? `: ${json.details}`: ''));
      total = json.total ?? total; const items = json.images || []; appendBatch(items); offset += items.length;
    }).catch(err => {
      console.error('Batch load error', err);
      const errorDiv = document.createElement('div'); errorDiv.style.color = 'red'; errorDiv.style.padding = '20px'; errorDiv.textContent = `Error loading images: ${err.message}`; framesContainer.appendChild(errorDiv);
    }).finally(() => { loading = false; showLoading(false); if (offset > 0 && offset <= batchSize) { setStatus(0); } });
  }

  (async function init(){ 
    await loadNextBatch(); 
    ensureWindowLoaded(0);
    
    // Attach gear menus to initial batch
    if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
      window.GearMenu.attach(framesContainer);
    }
  })();
})();
</script>

<div id="toast-container"></div>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);