<?php
// public/view_video_admin.php
require_once __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Video Management Admin";

// Instantiate Modules
$videoExtractor = new \App\UI\Modules\VideoFrameExtractorModule();
$imageEditor = new \App\UI\Modules\ImageEditorModule();

ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">

<!-- Swiper -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<style>
/* Admin wrapper */
.admin-wrap { max-width: 1100px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; color: var(--text); }

/* Tabs */
.tabs { display: flex; gap: 0; border-bottom: 2px solid rgba(var(--muted-border-rgb), 0.08); margin-bottom: 20px; }
.tab-btn { padding: 12px 20px; background: transparent; border: none; cursor: pointer; font-size: 0.95rem; color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -2px; font-weight: 500; transition: all 0.15s ease; text-decoration: none; display: inline-block; }
.tab-btn:hover { color: var(--text); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }

/* Filter bar */
.filter-bar { background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.08); border-radius: 8px; padding: 16px; box-shadow: var(--card-elevation); display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.filter-bar input, .filter-bar select { padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); transition: border-color 0.15s ease; }
.filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: var(--accent); }
.filter-bar input { flex: 1; min-width: 200px; }

/* Video Container */
.video-list-container { background: transparent; border: none; overflow: visible; padding-bottom: 20px; }

/* Swiper Tweaks */
.swiper { width: 100%; padding-bottom: 0 !important; }
.swiper-slide { height: auto; opacity: 0; transition: opacity 0.3s; }
.swiper-slide-active { opacity: 1; }

/* Pagination */
#videoSwiperPagination { position: static !important; margin-top: 20px; padding: 10px; width: 100%; display: flex; justify-content: center; flex-wrap: wrap; gap: 4px; background: var(--card); border-radius: 8px; }
.swiper-pagination-bullet { width: 10px; height: 10px; margin: 0 5px !important; background: var(--text-muted); opacity: 0.3; display: inline-block; }
.swiper-pagination-bullet-active { background: var(--accent); opacity: 1; }

/* Grid */
.video-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 0; }
.video-grid-item { position: relative; aspect-ratio: 16/9; background: #000; border-radius: 6px; overflow: hidden; cursor: pointer; border: 1px solid rgba(var(--muted-border-rgb), 0.15); transition: transform 0.15s; }
.video-grid-item:active { transform: scale(0.98); }
.video-grid-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.video-grid-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 20px 4px 4px 4px; display: flex; justify-content: space-between; align-items: flex-end; pointer-events: none; }
.video-dur-badge { background: rgba(0,0,0,0.7); color: #fff; font-size: 0.7rem; padding: 2px 4px; border-radius: 4px; }

/* Standard Modals */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 600px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(2,6,23,0.35); display: flex; flex-direction: column; max-height: 90vh; border: 1px solid rgba(var(--muted-border-rgb),0.06); overflow: hidden; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); flex-shrink: 0; }
.modal-body { padding: 20px; overflow-y: auto; color: var(--text); }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); background: var(--bg); display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); transition: border-color 0.15s ease; }
.small-muted { color: var(--text-muted); font-size: 0.85rem; }

/* Upload Area */
.upload-area { border: 2px dashed rgba(var(--muted-border-rgb), 0.2); border-radius: 8px; padding: 32px; text-align: center; cursor: pointer; transition: all 0.3s; background: var(--bg); }
.upload-area:hover { border-color: var(--accent); background: rgba(59,130,246,0.03); }
.progress-bar { width: 100%; height: 20px; background: var(--bg); border-radius: 10px; overflow: hidden; margin-top: 12px; display: none; border: 1px solid rgba(var(--muted-border-rgb), 0.12); }
.progress-fill { height: 100%; background: var(--accent); transition: width 0.3s; }

/* Loader */
.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.loading-spinner { width: 40px; height: 40px; margin: 0 auto 16px; border: 4px solid rgba(var(--muted-border-rgb), 0.2); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) {
    .admin-head { flex-direction: column; align-items: flex-start; }
    .filter-bar input, .filter-bar select { width: 100%; }
}

/* ════════════════════════════════════
   REMBG MODAL STYLES
════════════════════════════════════ */
.rembg-color-row { display: flex; align-items: center; gap: 10px; padding: 14px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); flex-shrink: 0; }
.rembg-swatch { width: 36px; height: 36px; border-radius: 4px; border: 2px solid rgba(255,255,255,0.15); flex-shrink: 0; cursor: pointer; transition: border-color 0.15s; }
.rembg-swatch:active { border-color: var(--accent); }
.rembg-hex-input { flex: 1; padding: 8px 10px; background: var(--bg); border: 1px solid rgba(var(--muted-border-rgb),0.12); color: var(--text); border-radius: 4px; font-family: inherit; font-size: 0.9rem; letter-spacing: 1px; }
.rembg-hex-input:focus { outline: none; border-color: var(--accent); }
.rembg-pick-btn { padding: 8px 12px; background: transparent; border: 1px solid var(--accent); color: var(--accent); border-radius: 4px; font-family: inherit; font-size: 0.7rem; font-weight: 700; cursor: pointer; white-space: nowrap; -webkit-tap-highlight-color: transparent; }
.rembg-pick-btn:active { background: rgba(108,99,255,0.15); }
.rembg-info-row { padding: 8px 20px; font-size: 0.7rem; color: var(--text-muted); flex-shrink: 0; }

/* ════════════════════════════════════
   COLOR SAMPLER MODAL STYLES
════════════════════════════════════ */
.sampler-canvas-wrap { flex: 1; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #000; min-height: 180px; cursor: crosshair; touch-action: none; }
#samplerCanvas { display: block; max-width: 100%; max-height: 100%; }
.sampler-result-row { display: flex; align-items: center; gap: 10px; padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); flex-shrink: 0; }
.sampler-result-swatch { width: 40px; height: 40px; border-radius: 4px; border: 2px solid rgba(255,255,255,0.15); flex-shrink: 0; }
.sampler-result-hex { font-size: 1.1rem; font-weight: 700; letter-spacing: 2px; color: var(--text); }
.sampler-hint { font-size: 0.65rem; color: var(--text-muted); padding: 6px 20px; flex-shrink: 0; }
</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2>
      <a style="margin-left:45px;" href="animatics_crud.php" class="entity-icon-link" title="Animatics Entity">🎥</a> 
      <a style="color:var(--text);margin:0 5px;" href="animatics_gallery.php">▦</a>
      <a style="color:var(--text);margin:0 5px;" href="view_global_playlist.php">📃</a>
      Video Management
      </h2>
    <div style="display:flex; gap:10px;">
        <a href="view_mass_video_upload.php" class="btn btn-sm btn-outline-primary">Mass Upload</a>
        <button class="btn btn-sm btn-primary" id="uploadBtn">+ Upload</button>
    </div>
  </div>
  
  <div class="tabs">
    <a href="view_video_admin.php" class="tab-btn active">Videos</a>
    <a href="view_video_playlists_admin.php" class="tab-btn">Playlists</a>
    <a href="view_video_categories_admin.php" class="tab-btn">Categories</a>
  </div>

  <div style="display:none;" class="filter-bar">
    <input type="text" id="searchInput" placeholder="Search videos..." class="form-control">
    <select id="categoryFilter" class="form-control">
      <option value="">All Categories</option>
    </select>
    <select id="playlistFilter" class="form-control">
      <option value="">All Videos</option>
    </select>
  </div>

  <div class="video-list-container" id="videoListContainer">
      <div class="swiper" id="videoSwiper">
          <div class="swiper-wrapper">
              <div class="swiper-slide" data-page="1">
                  <div class="slide-inner">
                      <div class="loading-state"><div class="loading-spinner"></div><p>Loading...</p></div>
                  </div>
              </div>
          </div>
      </div>
      <div id="videoSwiperPagination" class="swiper-pagination"></div>
  </div>
</div>

<!-- ══ REMBG CONFIRMATION MODAL ══ -->
<div id="rembgModal" class="modal-overlay" style="z-index: 120010;">
    <div class="modal-card">
        <div class="modal-header">
            <strong>◩ Remove Background</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeRembgModal()">✕</button>
        </div>
        <div class="rembg-color-row">
            <div class="rembg-swatch" id="rembgSwatch" onclick="syncSwatchFromInput()" title="Current color"></div>
            <input type="text" class="rembg-hex-input" id="rembgHexInput" value="#00FB00" maxlength="7"
                   oninput="onRembgHexInput()" placeholder="#00FB00">
            <button class="rembg-pick-btn" onclick="openSamplerModal()">Pick from<br>Thumb</button>
        </div>
        <div class="rembg-info-row">
            Target animatic: <span id="rembgAnimaticId" style="color:var(--accent); font-weight:700;">—</span>
            &nbsp;|&nbsp; Source video: <span id="rembgVideoId" style="color:var(--accent); font-weight:700;">—</span>
        </div>
        <div class="modal-footer" style="gap:8px;">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeRembgModal()">Cancel</button>
            <button class="btn btn-sm btn-success" id="btnRembgConfirm" onclick="confirmRembg()">Queue Removal</button>
        </div>
    </div>
</div>

<!-- ══ COLOR SAMPLER MODAL ══ -->
<div id="samplerModal" class="modal-overlay" style="z-index: 120015;">
    <div class="modal-card" style="max-height:92dvh;">
        <div class="modal-header">
            <strong>🎨 Pick Green Color</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeSamplerModal()">✕</button>
        </div>
        <div class="sampler-hint">Tap the green area on the thumbnail to sample its color.</div>
        <div class="sampler-canvas-wrap" id="samplerCanvasWrap">
            <canvas id="samplerCanvas"></canvas>
        </div>
        <div class="sampler-result-row">
            <div class="sampler-result-swatch" id="samplerSwatch" style="background:#00FB00;"></div>
            <span class="sampler-result-hex" id="samplerHex">#00FB00</span>
            <span style="font-size:0.65rem; color:var(--text-muted); margin-left:auto;">Tap to retap</span>
        </div>
        <div class="modal-footer" style="gap:8px;">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeSamplerModal()">Cancel</button>
            <button class="btn btn-sm btn-success" onclick="useSampledColor()">Use This Color</button>
        </div>
    </div>
</div>

<!-- Upload/Edit/Playlist Modals -->
<div id="uploadModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header"><strong>Upload Video</strong><button class="btn btn-sm btn-outline-secondary close-modal">Close</button></div>
    <form id="uploadForm" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="upload-area" id="uploadArea">
          <input type="file" id="videoFile" name="video" accept="video/mp4,video/webm,video/ogg" class="form-control" style="display:none;">
          <p style="margin:0 0 8px 0; font-weight: 600;">Click/Drag video</p>
          <p class="small-muted" style="margin:0;">MP4, WebM, OGG</p>
        </div>
        <div id="fileInfo" style="display:none; margin-top:12px; padding:12px; background:var(--bg); border-radius:6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12);"><strong id="fileName" style="color: var(--text);"></strong><div class="small-muted" id="fileSize"></div></div>
        <div class="progress-bar" id="progressBar"><div class="progress-fill" id="progressFill"></div></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" id="videoDescription"></textarea></div>
        <div class="form-group"><label>Category</label><select name="category_id" class="form-control" id="videoCategory"><option value="">None</option></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary close-modal">Cancel</button><button type="submit" class="btn btn-sm btn-success" id="uploadSubmitBtn">Upload</button></div>
    </form>
  </div>
</div>

<div id="editModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header"><strong>Edit Video</strong><button class="btn btn-sm btn-outline-secondary close-modal">Close</button></div>
    <form id="editForm">
      <input type="hidden" id="editVideoId">
      <div class="modal-body">
        <div class="form-group"><label>Internal Name</label><input type="text" class="form-control" id="editName" required></div>
        <div class="form-group"><label>Description</label><textarea class="form-control" id="editDescription"></textarea></div>
        <div class="form-group"><label>Category</label><select class="form-control" id="editCategory"><option value="">None</option></select></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="editActive"> Active</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary close-modal">Cancel</button><button type="submit" class="btn btn-sm btn-success">Save</button></div>
    </form>
  </div>
</div>

<div id="addToPlaylistModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header"><strong>Add to Playlist</strong><button class="btn btn-sm btn-outline-secondary close-modal">Close</button></div>
    <div class="modal-body">
      <input type="hidden" id="addToPlaylistVideoId">
      <div id="playlistCheckboxes"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary close-modal">Cancel</button><button type="submit" class="btn btn-sm btn-success" id="addToPlaylistSubmitBtn">Save</button></div>
  </div>
</div>

<!-- Render Modules -->
<?php include __DIR__ . '/modal_frame_details.php'; ?>
<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script src="js/toast.js"></script>
<script>
(function(){
  // --- Global State ---
  let categories = [], playlists = [], videos = [];
  let swiper = null;
  const rowsPerPage = 45;

  // --- Rembg / Sampler State ---
  let rembgTargetVideoId    = null;
  let rembgTargetAnimaticId = null;
  let rembgThumbnailUrl     = null;
  let samplerPickedColor    = '#00FB00';
  let samplerImg            = null;

  // --- Helpers ---
  function showToast(msg, type) { if (typeof Toast !== 'undefined') Toast.show(msg, type === 'error' ? 'error' : 'success'); else console.log(msg); }
  function formatDuration(s) { if (!s) return '0:00'; const m = Math.floor(s / 60); const sc = Math.floor(s % 60); return `${m}:${sc.toString().padStart(2, '0')}`; }
  function formatFileSize(b) { if (!b) return ''; return (b / (1024 * 1024)).toFixed(2) + ' MB'; }
  function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
  function debounce(f, w) { let t; return function(...a) { clearTimeout(t); t = setTimeout(() => f(...a), w); }; }

  // Close Modals Logic
  const closeModals = () => {
      document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
  };

  document.querySelectorAll('.close-modal, .modal-overlay').forEach(el => el.addEventListener('click', e => {
      if (e.target === el || e.target.classList.contains('close-modal')) closeModals();
  }));

  // --- Initialization ---
  Promise.all([
    fetch('video_admin_api.php?action=list_categories').then(r => r.json()),
    fetch('video_admin_api.php?action=list_playlists').then(r => r.json())
  ]).then(([catData, plData]) => {
    if (catData.status === 'ok') { categories = catData.categories; updateDropdowns(); }
    if (plData.status === 'ok') { playlists = plData.playlists; updatePlaylistFilter(); }
    initSlides();
  });

  function updateDropdowns() {
    ['categoryFilter', 'videoCategory', 'editCategory'].forEach(id => {
        const el = document.getElementById(id);
        const curr = el.value;
        el.innerHTML = el.options[0].outerHTML + categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        el.value = curr;
    });
  }
  function updatePlaylistFilter() {
      const el = document.getElementById('playlistFilter');
      el.innerHTML = '<option value="">All Videos</option>' + playlists.map(p => `<option value="${p.id}">${escapeHtml(p.name)} (${p.video_count})</option>`).join('');
  }

  // --- Swiper Logic ---
  function initSlides() {
      const search = document.getElementById('searchInput').value;
      const catId = document.getElementById('categoryFilter').value;
      const plId = document.getElementById('playlistFilter').value;
      const params = new URLSearchParams({action: 'list_videos', page: 1, limit: rowsPerPage, search: search});
      if(catId) params.append('category_id', catId);
      if(plId) params.append('playlist_id', plId);

      fetch('video_admin_api.php?' + params).then(r => r.json()).then(data => {
          if(data.status === 'ok') {
              videos = data.videos;
              const firstSlide = document.querySelector('#videoSwiper .swiper-slide:first-child .slide-inner');
              firstSlide.innerHTML = `<div class="video-grid">${buildGridItems(data.videos)}</div>`;

              const wrapper = document.querySelector('#videoSwiper .swiper-wrapper');
              while(wrapper.children.length > 1) { wrapper.removeChild(wrapper.lastChild); }

              for(let p = 2; p <= data.total_pages; p++) {
                  const div = document.createElement('div');
                  div.className = 'swiper-slide';
                  div.setAttribute('data-page', p);
                  div.setAttribute('data-loaded', '0');
                  div.innerHTML = '<div class="slide-inner"><div class="loading-state"><div class="loading-spinner"></div></div></div>';
                  wrapper.appendChild(div);
              }

              if(swiper) swiper.destroy();
              swiper = new Swiper('#videoSwiper', {
                  autoHeight: true,
                  pagination: { el: '#videoSwiperPagination', clickable: true },
                  on: { slideChange: function() { loadPageData(this.activeIndex + 1); } }
              });
          }
      });
  }

  function loadPageData(page) {
      const slide = document.querySelector(`.swiper-slide[data-page="${page}"]`);
      if(!slide || slide.getAttribute('data-loaded') === '1') return;

      const search = document.getElementById('searchInput').value;
      const catId = document.getElementById('categoryFilter').value;
      const plId = document.getElementById('playlistFilter').value;
      const params = new URLSearchParams({action: 'list_videos', page: page, limit: rowsPerPage, search: search});
      if(catId) params.append('category_id', catId);
      if(plId) params.append('playlist_id', plId);

      fetch('video_admin_api.php?' + params).then(r => r.json()).then(data => {
          if(data.status === 'ok') {
              data.videos.forEach(v => { if(!videos.find(ex => ex.id == v.id)) videos.push(v); });
              slide.querySelector('.slide-inner').innerHTML = `<div class="video-grid">${buildGridItems(data.videos)}</div>`;
              slide.setAttribute('data-loaded', '1');
              swiper.updateAutoHeight();
          }
      });
  }

  function buildGridItems(list) {
    if(!list.length) return '<div class="empty-state" style="grid-column: 1/-1;">No videos found</div>';
    return list.map(v => `
    <div class="video-grid-item" data-video-id="${v.id}" title="${escapeHtml(v.name)}">
        <img src="${escapeHtml(v.thumbnail)}" class="video-grid-img" loading="lazy" alt="${escapeHtml(v.name)}">
        <div class="video-grid-overlay">
            <span class="video-dur-badge" style="background:rgba(255,255,255,0.2);color:#fff;">#${v.id}</span>
            <span class="video-dur-badge">${formatDuration(v.duration)}</span>
        </div>
    </div>
    `).join('');
  }

  // --- Open Video Detail — uses encapsulated iframe modal ---
  function openVideoDetail(id) {
      if (typeof window.showVideoDetailsModal === 'function') {
          window.showVideoDetailsModal(id);
      }
  }

  // --- Event Delegation ---
  document.addEventListener('click', e => {
      const gridItem = e.target.closest('.video-grid-item');
      if (gridItem) { openVideoDetail(gridItem.dataset.videoId); return; }

      const btn = e.target.closest('button');
      if (!btn) return;

      let id = btn.dataset.id;

      if (!id && !btn.dataset.animaticId && !btn.classList.contains('close-modal')) return;

      if (btn.classList.contains('rembg-video-btn')) {
          openRembgModal(id, btn.dataset.animaticId || null, btn.dataset.thumbnail || null);
      }
  });

  // Listen for video_updated messages from the iframe so we can refresh if needed
  window.addEventListener('message', e => {
      if (e.data && e.data.type === 'video_updated') initSlides();
  });

  // --- Filter Listeners ---
  document.getElementById('searchInput').addEventListener('input', debounce(initSlides, 400));
  document.getElementById('categoryFilter').addEventListener('change', initSlides);
  document.getElementById('playlistFilter').addEventListener('change', initSlides);

  // --- Upload & Forms ---
  const upModal = document.getElementById('uploadModal'), upForm = document.getElementById('uploadForm'), upArea = document.getElementById('uploadArea'), vFile = document.getElementById('videoFile');
  document.getElementById('uploadBtn').onclick = () => upModal.classList.add('active');
  upArea.onclick = () => vFile.click();
  upArea.ondragover = e => { e.preventDefault(); upArea.classList.add('dragover'); };
  upArea.ondragleave = () => upArea.classList.remove('dragover');
  upArea.ondrop = e => { e.preventDefault(); upArea.classList.remove('dragover'); if(e.dataTransfer.files.length) { vFile.files = e.dataTransfer.files; handleFile(); }};
  vFile.onchange = handleFile;
  function handleFile() { const f = vFile.files[0]; if(f) { document.getElementById('fileName').textContent = f.name; document.getElementById('fileSize').textContent = formatFileSize(f.size); document.getElementById('fileInfo').style.display = 'block'; } }
  upForm.onsubmit = e => {
      e.preventDefault(); const fd = new FormData(upForm); fd.append('action', 'upload_video');
      document.getElementById('progressBar').style.display = 'block';
      const xhr = new XMLHttpRequest();
      xhr.upload.onprogress = e => { if(e.lengthComputable) document.getElementById('progressFill').style.width = ((e.loaded/e.total)*100)+'%'; };
      xhr.onload = () => { document.getElementById('progressBar').style.display = 'none'; const d = JSON.parse(xhr.responseText); if(d.status==='ok') { upModal.classList.remove('active'); upForm.reset(); document.getElementById('fileInfo').style.display='none'; showToast('Uploaded', 'success'); initSlides(); } else showToast(d.message || 'Error', 'error'); };
      xhr.open('POST', 'video_admin_api.php'); xhr.send(fd);
  };
  document.getElementById('editForm').onsubmit = e => {
      e.preventDefault();
      fetch('video_admin_api.php?action=update_video', {method:'POST', body:JSON.stringify({id: document.getElementById('editVideoId').value, name: document.getElementById('editName').value, description: document.getElementById('editDescription').value, category_id: document.getElementById('editCategory').value || null, is_active: document.getElementById('editActive').checked ? 1:0})}).then(r=>r.json()).then(d=>{ if(d.status==='ok') { document.getElementById('editModal').classList.remove('active'); initSlides(); showToast('Saved'); } else showToast(d.message, 'error'); });
  };
  document.getElementById('addToPlaylistSubmitBtn').onclick = () => {
      const vid = document.getElementById('addToPlaylistVideoId').value;
      const pids = Array.from(document.querySelectorAll('#playlistCheckboxes input:checked')).map(cb => cb.value);
      fetch('video_admin_api.php?action=sync_video_playlists', {method:'POST', body:JSON.stringify({video_id:vid, playlist_ids:pids})}).then(r=>r.json()).then(d=>{ if(d.status==='ok') { document.getElementById('addToPlaylistModal').classList.remove('active'); showToast('Playlists updated'); } else showToast(d.message, 'error'); });
  };

  // ════════════════════════════════════
  // REMBG MODAL
  // ════════════════════════════════════

  function openRembgModal(videoId, animaticId, thumbnailUrl) {
      rembgTargetVideoId    = videoId;
      rembgTargetAnimaticId = animaticId;
      rembgThumbnailUrl     = thumbnailUrl;
      setRembgColor('#00FB00');
      document.getElementById('rembgVideoId').textContent    = '#' + videoId;
      document.getElementById('rembgAnimaticId').textContent = animaticId ? '#' + animaticId : 'none';
      document.getElementById('rembgModal').classList.add('active');
  }

  function closeRembgModal() { document.getElementById('rembgModal').classList.remove('active'); }

  function setRembgColor(hex) {
      hex = hex.toUpperCase();
      if (!/^#[0-9A-F]{6}$/.test(hex)) return;
      document.getElementById('rembgHexInput').value = hex;
      document.getElementById('rembgSwatch').style.background = hex;
  }

  function onRembgHexInput() {
      const val = document.getElementById('rembgHexInput').value.trim();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) document.getElementById('rembgSwatch').style.background = val;
  }

  function syncSwatchFromInput() { onRembgHexInput(); }

  function confirmRembg() {
      const hex = document.getElementById('rembgHexInput').value.trim().toUpperCase();
      if (!/^#[0-9A-F]{6}$/.test(hex)) { showToast('Invalid hex color', 'error'); return; }
      if (!rembgTargetVideoId) return;
      const btn = document.getElementById('btnRembgConfirm');
      const origText = btn.textContent;
      btn.disabled = true; btn.textContent = 'Queuing…';
      fetch('video_admin_api.php?action=queue_rembg', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: rembgTargetVideoId, chromakey_color: hex })
      })
      .then(r => r.json())
      .then(d => {
          if (d.status === 'ok') { closeRembgModal(); showToast('Background removal queued ✓', 'success'); }
          else { showToast(d.message || 'Error', 'error'); }
      })
      .finally(() => { btn.disabled = false; btn.textContent = origText; });
  }

  // ════════════════════════════════════
  // COLOR SAMPLER MODAL
  // ════════════════════════════════════

  const SAMPLE_RADIUS = 10;

  function openSamplerModal() {
      if (!rembgThumbnailUrl) { showToast('No thumbnail available', 'error'); return; }
      samplerPickedColor = document.getElementById('rembgHexInput').value.trim() || '#00FB00';
      document.getElementById('samplerSwatch').style.background = samplerPickedColor;
      document.getElementById('samplerHex').textContent = samplerPickedColor.toUpperCase();
      document.getElementById('samplerModal').classList.add('active');
      requestAnimationFrame(() => { loadSamplerImage(rembgThumbnailUrl); });
  }

  function closeSamplerModal() { document.getElementById('samplerModal').classList.remove('active'); }

  function loadSamplerImage(url) {
      const canvas = document.getElementById('samplerCanvas');
      const wrap   = document.getElementById('samplerCanvasWrap');
      const ctx    = canvas.getContext('2d');
      const img    = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = function () {
          samplerImg = img;
          const scale = Math.min(wrap.clientWidth / img.naturalWidth, wrap.clientHeight / img.naturalHeight);
          canvas.width  = Math.round(img.naturalWidth  * scale);
          canvas.height = Math.round(img.naturalHeight * scale);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      };
      img.onerror = function () { showToast('Could not load thumbnail', 'error'); };
      img.src = url;
  }

  function sampleCanvasAt(canvasX, canvasY) {
      const canvas = document.getElementById('samplerCanvas');
      const ctx    = canvas.getContext('2d');
      const r = SAMPLE_RADIUS;
      let totalR = 0, totalG = 0, totalB = 0, count = 0;
      const x0 = Math.max(0, Math.round(canvasX - r)), y0 = Math.max(0, Math.round(canvasY - r));
      const x1 = Math.min(canvas.width - 1, Math.round(canvasX + r)), y1 = Math.min(canvas.height - 1, Math.round(canvasY + r));
      const imageData = ctx.getImageData(x0, y0, x1 - x0 + 1, y1 - y0 + 1);
      const data = imageData.data;
      for (let py = y0; py <= y1; py++) {
          for (let px = x0; px <= x1; px++) {
              const dx = px - canvasX, dy = py - canvasY;
              if (dx * dx + dy * dy <= r * r) {
                  const idx = ((py - y0) * (x1 - x0 + 1) + (px - x0)) * 4;
                  totalR += data[idx]; totalG += data[idx + 1]; totalB += data[idx + 2]; count++;
              }
          }
      }
      if (count === 0) return null;
      return '#' + [Math.round(totalR/count), Math.round(totalG/count), Math.round(totalB/count)].map(v => v.toString(16).padStart(2,'0')).join('').toUpperCase();
  }

  function drawIndicator(canvasX, canvasY) {
      const canvas = document.getElementById('samplerCanvas');
      const ctx    = canvas.getContext('2d');
      if (samplerImg) ctx.drawImage(samplerImg, 0, 0, canvas.width, canvas.height);
      ctx.beginPath(); ctx.arc(canvasX, canvasY, SAMPLE_RADIUS + 2, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(0,0,0,0.7)'; ctx.lineWidth = 2.5; ctx.stroke();
      ctx.beginPath(); ctx.arc(canvasX, canvasY, SAMPLE_RADIUS, 0, Math.PI * 2);
      ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 1.5; ctx.stroke();
  }

  function handleSamplerTap(clientX, clientY) {
      const canvas = document.getElementById('samplerCanvas');
      const rect   = canvas.getBoundingClientRect();
      const hex = sampleCanvasAt(clientX - rect.left, clientY - rect.top);
      if (!hex) return;
      samplerPickedColor = hex;
      drawIndicator(clientX - rect.left, clientY - rect.top);
      document.getElementById('samplerSwatch').style.background = hex;
      document.getElementById('samplerHex').textContent = hex;
  }

  document.getElementById('samplerCanvas').addEventListener('click', e => handleSamplerTap(e.clientX, e.clientY));
  document.getElementById('samplerCanvas').addEventListener('touchend', function(e) {
      e.preventDefault();
      const t = e.changedTouches[0];
      handleSamplerTap(t.clientX, t.clientY);
  }, { passive: false });

  function useSampledColor() { setRembgColor(samplerPickedColor); closeSamplerModal(); }

  // Expose globals for inline onclick handlers
  window.closeRembgModal   = closeRembgModal;
  window.onRembgHexInput   = onRembgHexInput;
  window.syncSwatchFromInput = syncSwatchFromInput;
  window.confirmRembg      = confirmRembg;
  window.openSamplerModal  = openSamplerModal;
  window.closeSamplerModal = closeSamplerModal;
  window.useSampledColor   = useSampledColor;

})();
</script>
<?php
require_once "forge_tool.php";
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>
