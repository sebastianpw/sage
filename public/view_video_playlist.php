<?php
// view_video_playlist.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Videos";
$content = "";

ob_start();
?>

<!-- Video.js CSS -->
<link rel="stylesheet" href="/vendor/video-js.css" />

<style>
.video {
   width:100%;
}

.view-container {
  max-width: 1100px;
  margin: 18px auto;
  padding: 8px;
}

.video-player-container {
  background: #000;
  margin-bottom: 20px;
  border-radius: 4px;
  overflow: hidden;
}

.playlist-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
  margin: 0;
}

/* Scroll wrapper around the playlist */
.playlist-wrapper {
  height: 220px;            /* fixed height you requested */
  max-width: 1100px;        /* match container width if desired */
  margin-top: 12px;
  overflow-y: auto;         /* vertical scrolling inside the box */
  -webkit-overflow-scrolling: touch; /* smooth scrolling on iOS */
  padding: 6px;             /* optional padding to separate items from edge */
  background: #fff;         /* keep same bg as items, or change */
  border-radius: 4px;
  border: 1px solid #e0e0e0;
}

/* keep the grid layout inside the wrapper; small tweak so items wrap nicely */
.playlist-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
  align-content: start; /* ensures items start at top when not full */
  padding-bottom: 8px;  /* extra space at bottom so last item isn't flush to edge */
}

.playlist-item {
  cursor: pointer;
  border: 2px solid transparent;
  border-radius: 4px;
  overflow: hidden;
  transition: all 0.3s ease;
  background: #fff;
}

.playlist-item:hover {
  border-color: #3f51b5;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.playlist-item.active {
  border-color: #818181;
}

.playlist-thumbnail {
  width: 100%;
  height: 120px;
  object-fit: cover;
  background: #f0f0f0;
}

.playlist-info {
  padding: 8px;
}

.playlist-title {
  font-weight: bold;
  margin-bottom: 4px;
  font-size: 14px;
  line-height: 1.3;
  color: #333;
}

.playlist-duration {
  color: #666;
  font-size: 12px;
}

.controls {
  margin: 0;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.control-group {
  display: flex;
  gap: 10px;
  align-items: center;
}

.loading-indicator {
  text-align: center;
  padding: 12px;
  color: #666;
  display: none;
}

.btn {
  padding: 8px 16px;
  background: #3f51b5;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.btn:hover:not(:disabled) {
  background: #303f9f;
}

.video-info {
  margin: 0;
  padding: 0;
  background: #f5f5f5;
  border-radius: 4px;
}

.video-info h4 {
  font-size: 0.7em;
  margin: 0 0 5px 0;
  color: #333;
}

.video-meta {
  font-size: 0.5em !important;
  color: #666;
  font-size: 14px;
  display: none;
}
</style>

<div class="view-container">
  
  <div id="playlist-config" style="display:none"
       data-api-url="/video_playlist_api.php"
       data-autoplay="false"
       data-start-index="0"></div>

  <!-- Main Video Player -->
  <div class="video-player-container">
    <video
      id="main-video-player"
      class="video-js vjs-default-skin vjs-big-play-centered"
      controls
      preload="auto"
      width="400"
      height="400"
      data-setup='{}'
    >
      <p class="vjs-no-js">
        To view this video please enable JavaScript, and consider upgrading to a
        web browser that
        <a href="https://videojs.com/html5-video-support/" target="_blank">
          supports HTML5 video
        </a>
      </p>
    </video>
  </div>

  <!-- Current Video Info -->
  <div class="video-info" id="current-video-info" style="float: left; width:150px;">
    <h4 id="video-title">Video Title</h4>
    <div class="video-meta">
      Duration: <span id="video-duration">0:00</span> | 
      Added: <span id="video-added">Unknown</span>
    </div>
  </div>

  <div class="controls" style="float:right;">
    <div class="control-group">
      <button id="prev-video" class="btn" disabled>Previous</button>
      <button id="next-video" class="btn" disabled>Next</button>
      <label style="display:none; align-items: center; gap: 5px;">
        <input style="display:none;" type="checkbox" id="autoplay-checkbox" checked="checked" />
	Autoplay
      </label>
    </div>
    
    <div class="control-group" style="display:none; margin-left: auto;">
      <label style="display: flex; align-items: center; gap: 5px;">
        Volume:
        <input type="range" id="volume-slider" min="0" max="100" value="80" />
      </label>
    </div>


  </div>

  <div style="display: none;" class="loading-indicator" id="loading-indicator">
    Loading video...
  </div>

<div style="clear:both;"> </div>

<?php /*
  <!-- Playlist -->
  <h3>Playlist (<?php 
    // Show video count from database
    try {
        $countStmt = $spw->getPDO()->query("SELECT COUNT(*) as count FROM videos");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo $count;
    } catch (Exception $e) {
        echo '?';
    }
?> videos)</h3>
*/ ?>

<!-- Playlist wrapper: fixed height, internal scroll -->
<div style="margin-bottom: 0;" class="playlist-wrapper" id="playlist-wrapper" role="region" aria-label="Video playlist">
  <div class="playlist-container" id="playlist-container">
    <!-- Playlist items will be loaded here -->
  </div>
</div>
</div>


<!-- Video.js -->
<script src="/vendor/video.min.js"></script>

<script>
(function() {
  const cfgEl = document.getElementById('playlist-config');
  const apiUrl = cfgEl.dataset.apiUrl;
  const startIndex = parseInt(cfgEl.dataset.startIndex, 10);
  const autoplay = cfgEl.dataset.autoplay === 'true';

  let videos = [];
  let currentVideoIndex = startIndex;
  let player = null;
  let userInteracted = false; // Track if user has interacted

  const loadingIndicator = document.getElementById('loading-indicator');
  const playlistContainer = document.getElementById('playlist-container');
  const prevBtn = document.getElementById('prev-video');
  const nextBtn = document.getElementById('next-video');
  const autoplayCheckbox = document.getElementById('autoplay-checkbox');
  const volumeSlider = document.getElementById('volume-slider');
  const currentVideoInfo = document.getElementById('current-video-info');
  const videoTitle = document.getElementById('video-title');
  const videoDuration = document.getElementById('video-duration');
  const videoAdded = document.getElementById('video-added');

  function showLoading(show) {
    loadingIndicator.style.display = show ? 'block' : 'none';
  }

  function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }

  function formatDate(dateString) {
    if (!dateString) return 'Unknown';
    const date = new Date(dateString);
    return date.toLocaleDateString();
  }

  function loadPlaylist() {
    showLoading(true);
    
    fetch(apiUrl)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          videos = data.videos || [];
          renderPlaylist();
          if (videos.length > 0) {
            loadVideo(currentVideoIndex, false); // Don't autoplay on initial load
          }
          updateButtonStates();
        } else {
          console.error('API error:', data.error);
          playlistContainer.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:#666;">Error loading playlist</div>';
        }
      })
      .catch(error => {
        console.error('Error loading playlist:', error);
        playlistContainer.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:#666;">Failed to load playlist</div>';
      })
      .finally(() => {
        showLoading(false);
      });
  }

  function renderPlaylist() {
    playlistContainer.innerHTML = '';
    
    if (videos.length === 0) {
      playlistContainer.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:#666;">No videos found</div>';
      return;
    }
    
    videos.forEach((video, index) => {
      const item = document.createElement('div');
      item.className = `playlist-item ${index === currentVideoIndex ? 'active' : ''}`;
      item.innerHTML = `
        <img src="${video.thumbnail}" 
             alt="${video.title}" 
             class="playlist-thumbnail"
             onerror="this.src='/vendor/video-js.png'">
        <div class="playlist-info">
          <div class="playlist-title" title="${video.title}">${video.title}</div>
          <div class="playlist-duration">${formatDuration(video.duration)}</div>
        </div>
      `;
      
      item.addEventListener('click', () => {
        loadVideo(index, true); // Autoplay when clicking playlist items
      });
      
      playlistContainer.appendChild(item);
    });
  }

  function updateButtonStates() {
    prevBtn.disabled = currentVideoIndex === 0 || videos.length === 0;
    nextBtn.disabled = currentVideoIndex === videos.length - 1 || videos.length === 0;
  }

  function updateVideoInfo(video) {
    if (video) {
      videoTitle.textContent = video.title;
      videoDuration.textContent = formatDuration(video.duration);
      videoAdded.textContent = formatDate(video.created_at);
      currentVideoInfo.style.display = 'block';
    } else {
      currentVideoInfo.style.display = 'none';
    }
  }

function loadVideo(index, shouldAutoplay = true) {
  if (index < 0 || index >= videos.length) return;

  currentVideoIndex = index;
  const video = videos[index];

  showLoading(true);

  // If player doesn't exist yet, create it first
  if (!player) {
    player = videojs('main-video-player', {
      controls: true,
      autoplay: false, // Don't autoplay initially
      preload: 'auto',
      fluid: true,
      responsive: true,
      playbackRates: [0.5, 1, 1.5, 2]
    });

    // Track user interaction
    player.on('play', () => { userInteracted = true; });
    player.on('click', () => { userInteracted = true; });

    player.on('ended', () => {
      if (autoplayCheckbox.checked) {
        playNextVideo();
      }
    });

    player.on('error', () => {
      showLoading(false);
      console.error('Error loading video');
    });

    // set source immediately so browser can preload, but don't call play()
    player.src({
      src: video.url,
      type: video.type || 'video/mp4'
    });

    // load the source (this tells the browser to fetch / buffer)
    player.ready(() => {
      // explicit load to encourage preloading
      try { player.load(); } catch (e) { /* ignore if not supported */ }

      // update UI
      updateVideoInfo(video);
      updateButtonStates();
      document.querySelectorAll('.playlist-item').forEach((item, i) => {
        item.classList.toggle('active', i === currentVideoIndex);
      });

      showLoading(false);

      // Only autoplay if requested AND user has interacted
      if (shouldAutoplay && userInteracted) {
        player.play().catch(e => {
          console.log('Autoplay prevented, requiring user interaction');
        });
      }
    });

  } else {
    // Player exists: just update the source and load it (no autoplay unless allowed)
    player.src({
      src: video.url,
      type: video.type || 'video/mp4'
    });

    // call load to start prefetching the new source
    try { player.load(); } catch (e) { /* ignore */ }

    // update UI immediately (no need to wait for ready)
    document.querySelectorAll('.playlist-item').forEach((item, i) => {
      item.classList.toggle('active', i === currentVideoIndex);
    });
    updateVideoInfo(video);
    updateButtonStates();

    // When the player reports ready / loadeddata, honor autoplay + userInteracted
    player.ready(() => {
      showLoading(false);
      if (shouldAutoplay && userInteracted) {
        player.play().catch(e => {
          console.log('Autoplay prevented, requiring user interaction');
        });
      }
    });
  }
}

  function playNextVideo() {
    if (currentVideoIndex < videos.length - 1) {
      loadVideo(currentVideoIndex + 1, true); // Autoplay next video
    }
  }

  function playPrevVideo() {
    if (currentVideoIndex > 0) {
      loadVideo(currentVideoIndex - 1, true); // Autoplay previous video
    }
  }

  // Event listeners
  prevBtn.addEventListener('click', () => {
    userInteracted = true; // Mark as interacted
    playPrevVideo();
  });
  
  nextBtn.addEventListener('click', () => {
    userInteracted = true; // Mark as interacted
    playNextVideo();
  });
  
  volumeSlider.addEventListener('input', (e) => {
    if (player) {
      player.volume(e.target.value / 100);
    }
  });

  // Mark interaction when user checks autoplay
  autoplayCheckbox.addEventListener('change', () => {
    userInteracted = true;
  });

  //autoplayCheckbox.checked = autoplay;

  // Initialize
  loadPlaylist();

  // Add click handler to the entire document to detect any user interaction
  document.addEventListener('click', () => {
    userInteracted = true;
  });

})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
