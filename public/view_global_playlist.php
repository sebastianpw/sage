<?php
// public/view_global_playlist.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Global Playlist";

// Instantiate Modules
$videoExtractor = new \App\UI\Modules\VideoFrameExtractorModule();
$imageEditor = new \App\UI\Modules\ImageEditorModule();

ob_start();
?>
<link rel="stylesheet" href="/css/base.css" />
<link rel="stylesheet" href="/vendor/video-js.css" />
<style>
.view-container { max-width: 1100px; margin: 18px auto; padding: 8px; }
.header-nav { display: flex; gap: 10px; margin-bottom: 12px; align-items: center; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
.header-nav h2 { margin: 0; font-size: 1.2rem; margin-right: auto; }

/* Player & Info */
.video-player-container { background: #000; margin-bottom: 15px; border-radius: 4px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
.video-info-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
.video-title-box { flex: 1; font-weight: 600; padding: 8px 12px; background: var(--card); border-radius: 4px; border: 1px solid var(--border); min-width: 200px; }

/* Playlist Grid */
.playlist-wrapper { min-height: 200px; }
.playlist-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.playlist-item { cursor: pointer; border: 2px solid transparent; border-radius: 6px; overflow: hidden; background: var(--card); transition: transform 0.2s, border-color 0.2s; }
.playlist-item:hover { border-color: var(--accent); transform: translateY(-3px); }
.playlist-item.active { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3); }
.playlist-thumbnail { width: 100%; height: 100px; object-fit: cover; background: #000; display: block; }
.playlist-meta { padding: 8px; font-size: 0.85rem; }
.playlist-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; margin-bottom: 2px; }
.playlist-dur { color: var(--text-muted); font-size: 0.75rem; }

/* Pagination */
.pagination-bar { margin-bottom: 20px; display: flex; justify-content: center; gap: 10px; align-items: center; }
.pg-btn { min-width: 40px; }
</style>

<div class="view-container">
  <div class="header-nav">
    <h2>🌍 Global Playlist</h2>
    <a href="view_video_admin.php" class="btn btn-sm btn-outline-primary">Admin</a>
    <a href="animatics_gallery.php" class="btn btn-sm btn-outline-secondary">Frames Gallery</a>
  </div>

  <!-- Player -->
  <div class="video-player-container">
    <video id="main-video-player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="800" height="450" data-setup='{"fluid": true}'></video>
  </div>

  <!-- Controls -->
  <div class="video-info-bar">
    <div id="video-title" class="video-title-box">Select a video...</div>
    <div class="controls" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
        <button id="extract-current-btn" class="btn btn-sm btn-outline-success" style="padding: 4px 8px; font-size: 0.8rem;" disabled>✂️ Frame</button>
        <button id="animatic-current-btn" class="btn btn-sm btn-outline-primary" style="padding: 4px 8px; font-size: 0.8rem; display:none;">🎬 Animatic</button>
        <a id="download-current-btn" class="btn btn-sm btn-outline-primary" style="padding: 4px 8px; font-size: 0.8rem; display:none; text-decoration:none;" download target="_blank">⬇️ Download</a>
        <button id="delete-current-btn" class="btn btn-sm btn-outline-danger" style="padding: 4px 8px; font-size: 0.8rem; display:none;">❌ Delete</button>
        
        <div class="btn-group" style="margin-left:auto;">
            <button id="prev-video" class="btn btn-sm btn-secondary" disabled>⏮ Prev</button>
            <button id="next-video" class="btn btn-sm btn-secondary" disabled>Next ⏭</button>
        </div>
    </div>
  </div>

  <!-- Pagination -->
  <div class="pagination-bar">
      <button id="pg-prev" class="btn btn-sm btn-secondary pg-btn" disabled>«</button>
      <span id="pg-info" style="font-weight:600; color:var(--text-muted);">Page 1</span>
      <button id="pg-next" class="btn btn-sm btn-secondary pg-btn" disabled>»</button>
  </div>

  <!-- Playlist -->
  <div class="playlist-wrapper">
      <div id="playlist-container" class="playlist-container">
          <!-- Items injected via JS -->
      </div>
  </div>
</div>

<!-- Modules -->
<?php include __DIR__ . '/modal_frame_details.php'; ?>
<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script src="/vendor/video.min.js"></script>
<script>
(function() {
    // Config
    const API_URL = 'video_admin_api.php';
    const LIMIT = 20;
    
    // State
    let state = {
        videos:[],
        page: 1,
        totalPages: 1,
        currentIndex: -1,
        autoPlayNext: false
    };
    
    // Player
    let player = null;

    // Elements
    const els = {
        container: document.getElementById('playlist-container'),
        title: document.getElementById('video-title'),
        btnPrev: document.getElementById('prev-video'),
        btnNext: document.getElementById('next-video'),
        btnExtract: document.getElementById('extract-current-btn'),
        btnAnimatic: document.getElementById('animatic-current-btn'),
        btnDownload: document.getElementById('download-current-btn'),
        btnDelete: document.getElementById('delete-current-btn'),
        pgPrev: document.getElementById('pg-prev'),
        pgNext: document.getElementById('pg-next'),
        pgInfo: document.getElementById('pg-info')
    };

    // --- Helpers ---
    function fmtTime(s) { const m=Math.floor(s/60), sc=Math.floor(s%60); return `${m}:${sc.toString().padStart(2,'0')}`; }

    // --- Logic ---
    function loadPage(page) {
        // Disable pagination while loading
        els.pgPrev.disabled = true; els.pgNext.disabled = true;
        els.container.style.opacity = '0.5';

        const params = new URLSearchParams({
            action: 'list_videos',
            page: page,
            limit: LIMIT
        });

        fetch(`${API_URL}?${params}`)
            .then(r => r.json())
            .then(data => {
                if(data.status === 'ok') {
                    state.videos = data.videos.map(v => ({
                        id: v.id,
                        title: v.name,
                        url: v.url,
                        thumbnail: v.thumbnail,
                        duration: v.duration,
                        type: v.type || 'video/mp4',
                        animatic_id: v.animatic_id
                    }));
                    state.page = parseInt(data.current_page);
                    state.totalPages = parseInt(data.total_pages);
                    
                    renderGrid();
                    updatePaginationUI();
                    
                    // If moving to next page automatically, play first video
                    if(state.autoPlayNext && state.videos.length > 0) {
                        playVideo(0, true);
                        state.autoPlayNext = false;
                    }
                }
            })
            .catch(err => console.error(err))
            .finally(() => {
                els.container.style.opacity = '1';
            });
    }

    function renderGrid() {
        if(!state.videos.length) {
            els.container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--text-muted);">No videos found on this page.</div>';
            return;
        }
        
        els.container.innerHTML = state.videos.map((v, i) => `
            <div class="playlist-item ${i === state.currentIndex ? 'active' : ''}" data-index="${i}">
                <img src="${v.thumbnail}" class="playlist-thumbnail" loading="lazy">
                <div class="playlist-meta">
                    <div class="playlist-title" title="${v.title}">${v.title}</div>
                    <div class="playlist-dur">⏱ ${fmtTime(v.duration)}</div>
                </div>
            </div>
        `).join('');

        // Attach clicks
        document.querySelectorAll('.playlist-item').forEach(el => {
            el.onclick = () => playVideo(parseInt(el.dataset.index), true);
        });
    }

    function updatePaginationUI() {
        els.pgInfo.textContent = `Page ${state.page} of ${state.totalPages || 1}`;
        els.pgPrev.disabled = state.page <= 1;
        els.pgNext.disabled = state.page >= state.totalPages;
    }

    function playVideo(index, autoPlay) {
        if(index < 0 || index >= state.videos.length) return;
        
        state.currentIndex = index;
        const v = state.videos[index];

        if(!player) {
            player = videojs('main-video-player');
            player.on('ended', onVideoEnded);
        }

        player.src({ src: v.url, type: v.type });
        els.title.textContent = v.title;
        
        // Update Action Buttons
        els.btnExtract.disabled = false;
        
        if (v.animatic_id) {
            els.btnAnimatic.style.display = 'inline-block';
            els.btnAnimatic.dataset.animaticId = v.animatic_id;
        } else {
            els.btnAnimatic.style.display = 'none';
            els.btnAnimatic.dataset.animaticId = '';
        }

        els.btnDownload.style.display = 'inline-block';
        els.btnDownload.href = v.url;

        els.btnDelete.style.display = 'inline-block';
        
        // Update UI active state
        document.querySelectorAll('.playlist-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.index) === index);
        });

        // Update nav buttons
        els.btnPrev.disabled = (index === 0 && state.page === 1);
        // We allow "Next" on the last item if there is a next page
        const isLastItem = index === state.videos.length - 1;
        const isLastPage = state.page === state.totalPages;
        els.btnNext.disabled = (isLastItem && isLastPage);

        if(autoPlay) player.play();
    }

    function onVideoEnded() {
        const nextIdx = state.currentIndex + 1;
        if(nextIdx < state.videos.length) {
            // Next in current list
            playVideo(nextIdx, true);
        } else if(state.page < state.totalPages) {
            // Load next page
            state.autoPlayNext = true;
            loadPage(state.page + 1);
        } else {
            // End of playlist
            console.log('Playlist ended');
        }
    }

    // --- Event Listeners ---
    els.pgPrev.onclick = () => loadPage(state.page - 1);
    els.pgNext.onclick = () => loadPage(state.page + 1);

    els.btnPrev.onclick = () => {
        if(state.currentIndex > 0) {
            playVideo(state.currentIndex - 1, true);
        } else if(state.page > 1) {
            loadPage(state.page - 1);
        }
    };

    els.btnNext.onclick = () => {
        if(state.currentIndex < state.videos.length - 1) {
            playVideo(state.currentIndex + 1, true);
        } else if(state.page < state.totalPages) {
            state.autoPlayNext = true;
            loadPage(state.page + 1);
        }
    };

    els.btnExtract.onclick = () => {
        if(state.currentIndex === -1) return;
        const v = state.videos[state.currentIndex];
        if(v && v.id) {
            if (player && !player.paused()) player.pause();
            window.VideoFrameExtractor.open(v.url, v.id);
        }
    };

    els.btnAnimatic.onclick = () => {
        const animId = els.btnAnimatic.dataset.animaticId;
        if(window.showEntityFormInModal && animId) {
             if (player && !player.paused()) player.pause();
             window.showEntityFormInModal('animatics', animId);
        } else {
             console.error('showEntityFormInModal not available or missing ID');
             alert('Animatic details not available.');
        }
    };

    els.btnDelete.onclick = () => {
        if(state.currentIndex === -1) return;
        const v = state.videos[state.currentIndex];
        if(!v || !v.id) return;
        
        if (confirm('Delete video?')) {
            fetch('video_admin_api.php?action=delete_video', {
                method:'POST', 
                body:JSON.stringify({id: v.id})
            })
            .then(r=>r.json())
            .then(d=>{ 
                if(d.status==='ok') { 
                    els.title.textContent = 'Select a video...';
                    els.btnExtract.disabled = true;
                    els.btnAnimatic.style.display = 'none';
                    els.btnDownload.style.display = 'none';
                    els.btnDelete.style.display = 'none';
                    if (player) {
                        player.pause();
                        player.src('');
                    }
                    state.currentIndex = -1;
                    loadPage(state.page);
                } else {
                    alert(d.message || 'Error deleting video');
                }
            })
            .catch(err => console.error(err));
        }
    };

    // Init
    loadPage(1);

})();
</script>
<?php
require_once "forge_tool.php";
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>