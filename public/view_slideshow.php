<?php
// view_slideshow.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Swiper + PhotoSwipe Gallery";
$content = "";

// Get the directory to browse (from GET parameter or use system default)
$framesDir = isset($_GET['dir']) ? $_GET['dir'] : $framesDirRel;
// Basic security: remove dangerous characters
$framesDir = str_replace(['..', '\\'], '', $framesDir);

// Read optional start frame from query (1-based). Default 1
$startFrame = isset($_GET['start']) ? max(1, (int)$_GET['start']) : 1;

ob_start();
?>

<!-- Styles -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
  <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<?php endif; ?>

<!-- Styles -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<?php else: ?>
    <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
<?php endif; ?>

<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<style>
.view-container {
  max-width: 1100px;
  margin: 18px auto;
  padding: 8px;
}
.swiper {
  width: 100%;
  background: #111;
}
.swiper-slide {
  display:flex;
  align-items:center;
  justify-content:center;
}
.swiper-slide img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  display: block;
}
.swiper-pagination {
   display: none;
}
.loading-indicator {
  text-align:center;
  padding: 12px;
  color: #666;
}
.controls {
  margin-top: 8px;
  display:flex;
  gap:12px;
  align-items:center;
}
.slide-info {
  text-align:right;
  margin-top:8px;
  color:#999;
}
.control-left {
  display:flex;
  gap:8px;
  align-items:center;
}
.jump-input { width:100px; }
.play-btn { 
    font-size: 28px; 
    padding:0;
    background: none;
    border: 0; 
    cursor:pointer; 
}

/* Style for disabled buttons (grey them out) */
button:disabled, .disabled {
    opacity: 0.5;
    pointer-events: none;  /* Prevents interaction */
    cursor: not-allowed;   /* Shows a 'not-allowed' cursor */
}

.current-dir {
  color: #666;
  font-size: 0.9em;
  margin-top: 5px;
}
</style>

<div class="view-container">
<a style="font-size: 1.5em; float:left; display:none;" href="dashboard.php" class="back-link" title="Dashboard">🔮</a>
  <h2 style="float: left; margin: 0 0 20px 45px;">📽️  Slideshow</h2>
  <div style="clear: left;"> </div>
  
  
  <div id="gallery-config" style="display:none"
       data-api-url="/slideshow_images.php"
       data-frame-metadata-url="/frame_metadata.php"
       data-frames-dir="<?= htmlspecialchars($framesDir) ?>"
       data-batch-size="5000"
       data-preload-threshold="12"
       data-start-frame="<?= (int)$startFrame ?>"></div>

  <!-- Swiper -->
  <div class="swiper" id="swiper-virtual">
    <div class="swiper-wrapper"></div>
    <div class="swiper-pagination"></div>

    <!-- navigation -->
    <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div>
  </div>

  <div class="controls">
    <div class="control-left">
      <button id="play-pause" class="play-btn btn btn-sm" title="Pause/Play">⏸</button>

      <label>
        Autoplay (ms):
        <input class="form-control" id="autoplay-input" type="number" value="500" style="width:100px"/>
      </label>

      <button style="display: none;" id="refresh-index" class="btn btn-sm">Refresh Index</button>

      <label>
        Jump:
        <input id="jump-to" class="form-control jump-input" type="number" min="1" value="<?= (int)$startFrame ?>" />
      </label>
      <button id="jump-btn" class="btn btn-sm" style="margin-top: 20px;">Jump</button>
    </div>
  </div>


<div>
<div class="current-dir">
  📁 <?= htmlspecialchars($framesDir) ?>
</div>
</div>

<div>
   <!-- Import Buttons -->
    <div id="import-buttons" style="margin-top: 10px;">
        <button id="view-frame" class="import-btn btn btn-sm">View Frame</button>
        <button id="edit-entity" class="import-btn btn btn-sm">Edit Entity</button>
        <button id="import-generative" class="import-btn btn btn-sm">Import Generative</button>
        <button id="import-controlnet" class="import-btn btn btn-sm">Import ControlNet Map</button>
        <button id="use-prompt-matrix" class="import-btn btn btn-sm">Use Prompt Matrix</button>
        <button id="delete-frame" class="import-btn btn btn-sm">Delete Frame</button>
    </div>
</div>

  <div style="float: left;">
    <div class="loading-indicator" id="loading-indicator" aria-live="polite" style="display:none"><i class="fas fa-spinner fa-spin"></i> loading…</div>
  </div>

<div class="slide-info">
    <span id="slide-info">0 / 0</span>
    <div id="frame-meta-info" style="color: #888; font-size: 0.9em; margin-top: 10px;">
	<strong>Frame ID:</strong> <span id="frame-id">N/A</span><br>
        <strong>Entity ID:</strong> <span id="entity-id">N/A</span><br>
        <strong>Name:</strong> <span id="entity-name">N/A</span><br>
        <strong>Description:</strong> <span id="entity-description">No description available</span><br>
        <strong>Entity:</strong> <span id="entity-type">None</span><br>
    </div>
</div>

</div>

<!-- jQuery -->
<?= $spw->getJquery() ?>

<!-- PhotoSwipe v5 -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
  <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
  <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>

<!-- Swiper -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
  <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<script>
(function(){
  const cfgEl = document.getElementById('gallery-config');
  const apiUrl = cfgEl.dataset.apiUrl;
  const metadataUrl = cfgEl.dataset.frameMetadataUrl;
  const framesDir = cfgEl.dataset.framesDir;
  const batchSize = parseInt(cfgEl.dataset.batchSize, 10);
  const preloadThreshold = parseInt(cfgEl.dataset.preloadThreshold, 10);
  const startFrame = Math.max(1, parseInt(cfgEl.dataset.startFrame, 10));
  const startIndex = Math.max(0, startFrame - 1);
  let images = [];
  let total = null;
  let offset = 0;
  let loading = false;

  const loadingIndicator = document.getElementById('loading-indicator');
  const slideInfo = document.getElementById('slide-info');
  const playPauseBtn = document.getElementById('play-pause');
  const jumpInput = document.getElementById('jump-to');
  const jumpBtn = document.getElementById('jump-btn');
  const entityNameEl = document.getElementById('entity-name');
  const entityDescriptionEl = document.getElementById('entity-description');
  const entityTypeEl = document.getElementById('entity-type');

  function showLoading(v) { loadingIndicator.style.display = v ? 'block' : 'none'; }
  function updateSlideInfo(idx) { slideInfo.textContent = `${idx + 1} / ${total ?? '…'}`; }

  async function updateMetaInfo(filename) {
    const nameWithoutExtension = filename.replace(/\.[^/.]+$/, '');
    const response = await fetch(`${metadataUrl}?name=${encodeURIComponent(nameWithoutExtension)}`);
    const metadata = await response.json();

    const frameIdEl = document.getElementById('frame-id');
    frameIdEl.textContent = metadata.frame_id || 'N/A';

    const entityIdEl = document.getElementById('entity-id');
    entityIdEl.textContent = metadata.entity_id || 'N/A';     

    // Display metadata
    entityNameEl.textContent = metadata.entity_name || 'N/A';
    entityDescriptionEl.textContent = metadata.entity_description || 'No description available';
    entityTypeEl.textContent = metadata.entity_type || 'None';

    // Helper function to handle button enabling/disabling and attribute setting
    function updateButtonState(buttonId, metadata, isEnabled) {
        const button = document.getElementById(buttonId);
        
        // Enable or disable the button (visually greys out if disabled)
        button.disabled = !isEnabled;

        // Update the button's class (optional styling for disabled state)
        if (isEnabled) {
            button.classList.remove('disabled');
        } else {
            button.classList.add('disabled');
        }

        // Set button's data attributes if enabled
        if (isEnabled && metadata) {
            button.setAttribute('data-frame-id', metadata.frame_id);
            button.setAttribute('data-entity-id', metadata.entity_id);
            button.setAttribute('data-entity-type', metadata.entity_type);

            // Map button IDs to actual function names
            const functionMap = {
                'import-generative': 'importGenerative',
                'import-controlnet': 'importControlNetMap', 
                'use-prompt-matrix': 'usePromptMatrix',
                'delete-frame': 'deleteFrame'
            };

            // Set the onclick event handler
            button.onclick = function() {
                const entity = this.getAttribute('data-entity-type');
                const entityId = this.getAttribute('data-entity-id');
                const frameId = this.getAttribute('data-frame-id');

                // Special handling for the view-frame button
                if (buttonId === 'view-frame') {
                    if (entity && entityId && frameId) {
                        showFrameDetailsModal(frameId, 0.7);
                    }
                    return; // Stop further execution for this button
                }
                
                // Special handling for the edit-entity button
                if (buttonId === 'edit-entity') {
                    if (entity && entityId) {
                        // This function is defined in modal_frame_details.php
                        window.showEntityFormInModal(entity, entityId);
                    }
                    return; // Stop further execution for this button
                }
                
                const functionName = functionMap[buttonId];
                if (functionName && window[functionName]) {
                    window[functionName](entity, entityId, frameId);
                }
            };
        } else {
            // Remove onclick when disabled
            button.onclick = null;
        }
    }

    // Show/hide and enable/disable buttons based on metadata availability
    if (metadata.entity_id && metadata.frame_id) {
        // Update all buttons to be enabled with metadata and active
        updateButtonState('view-frame', metadata, true);
        updateButtonState('edit-entity', metadata, true);
        updateButtonState('import-generative', metadata, true);
        updateButtonState('import-controlnet', metadata, true);
        updateButtonState('use-prompt-matrix', metadata, true);
        updateButtonState('delete-frame', metadata, true);
    } else {
        // Update all buttons to be disabled (greyed out) when no metadata is available
        updateButtonState('view-frame', null, false);
        updateButtonState('edit-entity', null, false);
        updateButtonState('import-generative', null, false);
        updateButtonState('import-controlnet', null, false);
        updateButtonState('use-prompt-matrix', null, false);
        updateButtonState('delete-frame', null, false);
    }
  }

  const swiper = new Swiper('#swiper-virtual', {
    slidesPerView: 1,
    spaceBetween: 10,
    loop: false,
    pagination: { el: '.swiper-pagination', clickable: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    autoplay: { delay: 500, disableOnInteraction: false },
    virtual: {
      slides: [],
      renderSlide: (slide, index) => {
        const item = images[index];
        if (!item) return `<div class="swiper-slide">Loading…</div>`;
        return `<div class="swiper-slide">
                  <a href="${item.url}" data-pswp-src="${item.url}" 
                     data-pswp-width="${item.w || 1200}" data-pswp-height="${item.h || 900}"
                     data-index="${index}" target="_blank">
                    <img src="${item.url}" alt="${item.caption || ''}" />
                  </a>
                </div>`;
      }
    },
    on: {
      slideChange: function(){
        updateSlideInfo(this.activeIndex);
        if (images[this.activeIndex]) {
          updateMetaInfo(images[this.activeIndex].filename);
        }
        if ((total === null || offset < total) && (this.activeIndex + preloadThreshold >= images.length)) {
          loadNextBatch();
        }
      }
    }
  });

  function loadNextBatch() {
    if (loading) return;
    loading = true;
    showLoading(true);
    // Include the dir parameter in the API call
    const url = `${apiUrl}?offset=${offset}&limit=${batchSize}&dir=${encodeURIComponent(framesDir)}`;
    return fetch(url)
      .then(r => r.json())
      .then(json => {
        total = json.total;
        images = images.concat(json.images || []);
        swiper.virtual.slides = new Array(images.length).fill(null);
        swiper.virtual.update(true);
        offset += (json.images || []).length;
        updateSlideInfo(swiper.activeIndex);
        if (images[swiper.activeIndex]) {
          updateMetaInfo(images[swiper.activeIndex].filename);
        }
      })
      .finally(() => {
        loading = false;
        showLoading(false);
      });
  }

  function ensureIndexLoaded(target) {
    return (async () => {
      while (images.length <= target && (total === null || offset < total)) {
        await loadNextBatch();
      }
      return Math.min(target, images.length - 1);
    })();
  }

  async function jumpToIndex(i) {
    const wasPlaying = isPlaying();
    const actual = await ensureIndexLoaded(i);
    swiper.slideTo(actual, 0, false);
    updateSlideInfo(actual);
    if (images[actual]) {
      updateMetaInfo(images[actual].filename);
    }
    if (wasPlaying) {
      swiper.autoplay.start();
      setPlayIcon();
    } else {
      swiper.autoplay.stop();
      setPauseIcon();
    }
  }

  function isPlaying() {
    return swiper.autoplay?.running;
  }
  function setPlayIcon() { playPauseBtn.textContent = '⏸'; }
  function setPauseIcon() { playPauseBtn.textContent = '▶️'; }

  playPauseBtn.onclick = () => {
    if (isPlaying()) {
      swiper.autoplay.stop();
      setPauseIcon();
    } else {
      swiper.autoplay.start();
      setPlayIcon();
    }
  };
  document.getElementById('autoplay-input').onchange = e => {
    const v = parseInt(e.target.value, 10);
    if (v > 200) {
      swiper.params.autoplay.delay = v;
      if (isPlaying()) {
        swiper.autoplay.stop();
        swiper.autoplay.start();
      }
    }
  };
  document.getElementById('refresh-index').onclick = () => {
    offset = 0;
    images = [];
    total = null;
    loadNextBatch();
  };
  jumpBtn.onclick = () => {
    jumpToIndex(parseInt(jumpInput.value, 10) - 1 || 0);
  };

  // PhotoSwipe v5 Lightbox
  const lightbox = new PhotoSwipeLightbox({
    gallery: '#swiper-virtual',
    children: 'a',
    pswpModule: PhotoSwipe
  });
  lightbox.init();

  (async () => {
    await loadNextBatch();
    // pick a random start index within loaded total
    const randomIndex = Math.floor(Math.random() * total);
    await jumpToIndex(randomIndex);
  })();

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

  window.usePromptMatrix = window.usePromptMatrix || (async function(entity, entityId, frameId){
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

})();
</script>

<?php
require 'modal_frame_details.php';
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
?>
