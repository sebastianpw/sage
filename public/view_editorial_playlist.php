<?php
// public/view_editorial_playlist.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$sceneId = $_GET['scene_id'] ?? null;
$pageTitle = "Scene Preview";

if ($sceneId) {
    $stmt = $pdo->prepare("SELECT name FROM editorial_scenes WHERE id = ?");
    $stmt->execute([$sceneId]);
    $scene = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($scene) $pageTitle = "Preview: " . $scene['name'];
}

ob_start();
?>
<!-- Video.js Styles -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/video-js.css" />
<?php endif; ?>

<style>
/* Simplified layout for modal usage */
.video-player-container { 
    background: #000; 
    width: 100%; 
    aspect-ratio: 16/9; 
    display: flex; justify-content: center;
}
#main-video-player { width: 100%; height: 100%; }

.playlist-wrapper { 
    height: 180px; 
    margin-top: 10px; 
    overflow-y: auto; 
    background: #111; 
    border-top: 1px solid #333;
}
.playlist-container { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); 
    gap: 8px; 
    padding: 10px;
}
.playlist-item { 
    cursor: pointer; 
    border: 2px solid transparent; 
    border-radius: 4px; 
    overflow: hidden; 
    background: #222; 
    transition: all 0.2s; 
}
.playlist-item:hover { border-color: #555; transform: scale(1.02); }
.playlist-item.active { border-color: var(--accent, #3b82f6); box-shadow: 0 0 8px rgba(59,130,246,0.5); }

.playlist-thumbnail { width: 100%; height: 80px; object-fit: cover; background: #000; }
.playlist-info { padding: 6px; }
.playlist-title { font-weight: 500; font-size: 0.8rem; color: #eee; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.playlist-dur { font-size: 0.7rem; color: #999; }

.controls-bar { 
    display: flex; justify-content: space-between; align-items: center; 
    padding: 8px 10px; background: #1a1a1a; color: #eee; border-bottom: 1px solid #333; 
}
.video-title-display { font-weight: 600; font-size: 0.95rem; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 15px;}
.nav-btns { display: flex; gap: 8px; }
.nav-btns button { 
    background: #333; border: 1px solid #444; color: #fff; 
    padding: 4px 10px; cursor: pointer; border-radius: 3px; font-size: 0.85rem;
}
.nav-btns button:disabled { opacity: 0.4; cursor: default; }
.nav-btns button:hover:not(:disabled) { background: #444; }

/* Internal Modal for JS Export */
.js-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 2000;
    display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px);
}
.js-overlay.active { display: flex; }
.js-box {
    background: #1a1a1a; border: 1px solid #444; border-radius: 6px;
    width: 90%; max-width: 600px; display: flex; flex-direction: column;
    box-shadow: 0 10px 25px rgba(0,0,0,0.8);
}
.js-header {
    padding: 10px 15px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center;
}
.js-body { padding: 15px; }
.js-textarea {
    width: 100%; height: 250px; background: #0a0a0a; color: #0f0; 
    font-family: monospace; font-size: 0.85rem; padding: 10px;
    border: 1px solid #333; border-radius: 4px; resize: vertical;
}
.js-footer {
    padding: 10px 15px; border-top: 1px solid #333; display: flex; justify-content: flex-end; gap: 10px;
}
.btn-primary { background: #3b82f6; border: none; color: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
.btn-primary:hover { background: #2563eb; }
.btn-close { background: transparent; border: none; color: #aaa; font-size: 1.5rem; cursor: pointer; line-height: 1; }
.btn-close:hover { color: #fff; }
</style>

<div style="display:flex; flex-direction:column; height:100%; background:#000;">
  <div class="video-player-container">
    <video id="main-video-player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" data-setup='{}'></video>
  </div>

  <div class="controls-bar">
    <div class="video-title-display" id="video-title">Loading...</div>
    <div class="nav-btns">
        <button id="btn-show-js" title="View JS Playlist Array">JS Array</button>
        <button id="prev-video" disabled>&laquo; Prev</button>
        <button id="next-video" disabled>Next &raquo;</button>
    </div>
  </div>

  <div class="playlist-wrapper">
    <div class="playlist-container" id="playlist-container"></div>
  </div>
</div>

<!-- Modal for JS Export -->
<div id="js-modal" class="js-overlay">
    <div class="js-box">
        <div class="js-header">
            <span style="font-weight:600; color:#eee;">Javascript Playlist Array</span>
            <button class="btn-close" id="btn-close-js">&times;</button>
        </div>
        <div class="js-body">
            <textarea id="js-output" class="js-textarea" readonly></textarea>
        </div>
        <div class="js-footer">
            <span id="copy-msg" style="color:#4ade80; margin-right:auto; display:none;">Copied to clipboard!</span>
            <button class="btn-primary" id="btn-copy-js">Copy to Clipboard</button>
        </div>
    </div>
</div>

<!-- Video.js Script -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
<?php else: ?>
  <script src="/vendor/video.min.js"></script>
<?php endif; ?>

<script>
(function() {
  const sceneId = <?php echo json_encode($sceneId); ?>;
  let videos = [], curIdx = 0, player = null;

  function fmtTime(s) { 
      if(!s) return '0:00';
      const m=Math.floor(s/60), sc=Math.floor(s%60); 
      return `${m}:${sc.toString().padStart(2,'0')}`; 
  }

  function load() {
    fetch('editorial_api.php?action=get_scene_playlist&scene_id=' + sceneId)
      .then(r => r.json())
      .then(d => {
        if(d.success) {
            videos = d.data.map(v => ({
                title: v.name,
                url: v.filename, // editorial_shots uses filename as the relative path
                thumbnail: v.video_thumbnail,
                duration: v.duration_est,
                type: 'video/mp4' 
            }));
            render();
            if(videos.length) playVideo(0, false); 
            else document.getElementById('video-title').textContent = "No videos in scene.";
        } else {
            document.getElementById('video-title').textContent = "Error loading scene.";
        }
      });
  }

  function render() {
      const c = document.getElementById('playlist-container');
      if(!videos.length) { c.innerHTML='<div style="color:#666; padding:20px;">No videos found.</div>'; return; }
      
      c.innerHTML = videos.map((v,i) => `
        <div class="playlist-item" data-index="${i}">
            <img src="${v.thumbnail || ''}" class="playlist-thumbnail" onerror="this.style.display='none'">
            <div class="playlist-info">
                <div class="playlist-title" title="${v.title.replace(/"/g, '&quot;')}">${v.title}</div>
                <div class="playlist-dur">${fmtTime(v.duration)}</div>
            </div>
        </div>
      `).join('');
      
      document.querySelectorAll('.playlist-item').forEach(el => {
          el.onclick = () => playVideo(parseInt(el.dataset.index), true);
      });
  }

  function playVideo(idx, autoPlay) {
      if(idx < 0 || idx >= videos.length) return;
      curIdx = idx;
      const v = videos[idx];
      
      if(!player) {
          player = videojs('main-video-player', { fluid: false, fill: true });
          player.on('ended', () => {
              if (curIdx + 1 < videos.length) {
                  playVideo(curIdx + 1, true);
              }
          });
      }
      
      player.src({ src: v.url, type: v.type });
      
      // Update UI
      document.getElementById('video-title').textContent = `${idx + 1}. ${v.title}`;
      document.querySelectorAll('.playlist-item').forEach(el => {
          el.classList.toggle('active', parseInt(el.dataset.index) === idx);
          if (parseInt(el.dataset.index) === idx) {
              el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
      });
      
      document.getElementById('prev-video').disabled = (idx === 0);
      document.getElementById('next-video').disabled = (idx === videos.length - 1);
      
      if(autoPlay) player.play();
  }

  // --- Modal & Clipboard Logic ---
  const modal = document.getElementById('js-modal');
  const txtArea = document.getElementById('js-output');
  const msg = document.getElementById('copy-msg');

  document.getElementById('btn-show-js').onclick = () => {
      // Format as a constant JS array
      const json = JSON.stringify(videos, null, 2);
      txtArea.value = `const playlist = ${json};`;
      msg.style.display = 'none';
      modal.classList.add('active');
  };

  document.getElementById('btn-close-js').onclick = () => {
      modal.classList.remove('active');
  };

  document.getElementById('btn-copy-js').onclick = () => {
      txtArea.select();
      txtArea.setSelectionRange(0, 99999); // Mobile fallback
      navigator.clipboard.writeText(txtArea.value).then(() => {
          msg.style.display = 'block';
          setTimeout(() => msg.style.display = 'none', 2000);
      });
  };

  // Close modal when clicking background
  modal.onclick = (e) => {
      if (e.target === modal) modal.classList.remove('active');
  };

  // Navigation
  document.getElementById('prev-video').onclick = () => playVideo(curIdx - 1, true);
  document.getElementById('next-video').onclick = () => playVideo(curIdx + 1, true);

  load();
})();
</script>
<?php $content = ob_get_clean(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>body { margin:0; padding:0; background:#000; color:#eee; font-family:sans-serif; overflow:hidden; }</style>
</head>
<body>
    <?= $content ?>
</body>
</html>