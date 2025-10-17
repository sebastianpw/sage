<?php
// view_slideshow.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Slideshow";
$content = "";

// read optional start frame from query (1-based). default 1
$startFrame = isset($_GET['start']) ? max(1, (int)$_GET['start']) : 1;

ob_start();
?>

<!-- Styles -->
  <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
  <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />

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
.jump-input { width:80px; }
.play-btn {                                                 font-size: 28px;                                        padding:0;                                              background: none;                                       border: 0;                                              cursor:pointer;                                     }
</style>

<div class="view-container">

  <div id="gallery-config" style="display:none"
       data-api-url="/slideshow_images_sc.php"
       data-batch-size="500"
       data-autoplay-delay="5000"
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
      <button id="play-pause" class="play-btn" title="Pause/Play">⏸</button>

      <label>
        Autoplay (ms):
        <input id="autoplay-input" type="number" value="5000" style="width:100px"/>
      </label>

      <button style="display: none;" id="refresh-index">Refresh Index</button>

      <label>
        Jump:
        <input id="jump-to" class="jump-input" type="number" min="1" value="<?= (int)$startFrame ?>" />
      </label>
      <button id="jump-btn">Jump</button>
    </div>

    <div style="margin-left:auto;">
      <div class="loading-indicator" id="loading-indicator" aria-live="polite" style="display:none">loading…</div>
    </div>
  </div>

  <div class="slide-info">
    <span id="slide-info">0 / 0</span>
  </div>

</div>


<!-- PhotoSwipe v5 -->
  <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
  <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>

<!-- Swiper -->
  <script src="/vendor/swiper/swiper-bundle.min.js"></script>

<script>
(function(){
  const cfgEl = document.getElementById('gallery-config');
  const apiUrl = cfgEl.dataset.apiUrl;
  const batchSize = parseInt(cfgEl.dataset.batchSize, 10);
  const autoplayDelayDefault = parseInt(cfgEl.dataset.autoplayDelay, 10);
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

  function showLoading(v){ loadingIndicator.style.display = v ? 'block' : 'none'; }
  function updateSlideInfo(idx){
    slideInfo.textContent = `${idx+1} / ${total ?? '…'}`;
  }
  function escapeHtml(s){ return s ? s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) : ''; }

  if (Swiper.use) {
    try { Swiper.use([Swiper.Virtual, Swiper.Autoplay, Swiper.Pagination, Swiper.Navigation]); } catch(e){}
  }

  const swiper = new Swiper('#swiper-virtual', {
    slidesPerView: 1,
    spaceBetween: 10,
    loop: false,
    pagination: { el: '.swiper-pagination', clickable: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    autoplay: { delay: autoplayDelayDefault, disableOnInteraction: false },
    virtual: {
      slides: [],
      renderSlide: (slide, index) => {
        const item = images[index];
        if (!item) return `<div class="swiper-slide">Loading…</div>`;
        return `<div class="swiper-slide">
                  <a href="${item.url}" data-pswp-src="${item.url}" 
                     data-pswp-width="${item.w||1200}" data-pswp-height="${item.h||900}"
                     data-index="${index}" target="_blank">
                    <img src="${item.url}" alt="${escapeHtml(item.caption)}" />
                  </a>
                </div>`;
      }
    },
    on: {
      slideChange: function(){
        updateSlideInfo(this.activeIndex);
        if ((total===null || offset<total) && (this.activeIndex+preloadThreshold>=images.length)) {
          loadNextBatch();
        }
      }
    }
  });

  function loadNextBatch(){
    if (loading) return;
    loading=true; showLoading(true);
    return fetch(`${apiUrl}?offset=${offset}&limit=${batchSize}`).then(r=>r.json()).then(json=>{
      total=json.total;
      images=images.concat(json.images||[]);
      swiper.virtual.slides=new Array(images.length).fill(null);
      swiper.virtual.update(true);
      offset+= (json.images||[]).length;
      updateSlideInfo(swiper.activeIndex);
    }).finally(()=>{loading=false; showLoading(false);});
  }

  function ensureIndexLoaded(target){
    return (async()=>{
      while(images.length<=target && (total===null || offset<total)){
        await loadNextBatch();
      }
      return Math.min(target, images.length-1);
    })();
  }

  async function jumpToIndex(i){
    const wasPlaying=isPlaying();
    const actual=await ensureIndexLoaded(i);
    swiper.slideTo(actual,0,false);
    updateSlideInfo(actual);
    if(wasPlaying){swiper.autoplay.start();setPlayIcon();}else{swiper.autoplay.stop();setPauseIcon();}
  }

  function isPlaying(){return swiper.autoplay?.running;}
  function setPlayIcon(){playPauseBtn.textContent='⏸';}
  function setPauseIcon(){playPauseBtn.textContent='▶️';}

  playPauseBtn.onclick=()=>{if(isPlaying()){swiper.autoplay.stop();setPauseIcon();}else{swiper.autoplay.start();setPlayIcon();}};
  document.getElementById('autoplay-input').onchange=e=>{const v=parseInt(e.target.value,10);if(v>200){swiper.params.autoplay.delay=v;if(isPlaying()){swiper.autoplay.stop();swiper.autoplay.start();}}};
  document.getElementById('refresh-index').onclick=()=>{offset=0;images=[];total=null;loadNextBatch();};
  jumpBtn.onclick=()=>{jumpToIndex(parseInt(jumpInput.value,10)-1||0);};

  // PhotoSwipe v5 Lightbox
  const lightbox=new PhotoSwipeLightbox({
    gallery:'#swiper-virtual',
    children:'a',
    pswpModule:PhotoSwipe
  });
  lightbox.init();

  //(async()=>{await loadNextBatch();if(startIndex>0){await jumpToIndex(startIndex);}})();

  (async () => {
  await loadNextBatch();     
  // pick a random start index within loaded total  
  const randomIndex = Math.floor(Math.random() * total)
  await jumpToIndex(randomIndex);                         })();


})();
</script>

<?php
//require "floatool.php";
$content = ob_get_clean();
//$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
