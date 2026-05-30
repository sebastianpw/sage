<?php
// public/view_video_playlist.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Include Modules
require_once __DIR__ . '/src/UI/Modules/VideoFrameExtractorModule.php';
require_once __DIR__ . '/src/UI/Modules/ImageEditorModule.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$playlistId = $_GET['playlist_id'] ?? null;
$categoryId = $_GET['category_id'] ?? null;

$pageTitle = "Videos";
if ($playlistId) {
    $stmt = $pdo->prepare("SELECT name FROM video_playlists WHERE id = ?");
    $stmt->execute([$playlistId]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($playlist) $pageTitle = $playlist['name'] . " - Videos";
} elseif ($categoryId) {
    $stmt = $pdo->prepare("SELECT name FROM video_categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($category) $pageTitle = $category['name'] . " - Videos";
}

// Instantiate Modules
$videoExtractor = new \App\UI\Modules\VideoFrameExtractorModule();
$imageEditor = new \App\UI\Modules\ImageEditorModule();

ob_start();
?>
<link rel="stylesheet" href="/css/base.css" />
<link rel="stylesheet" href="/vendor/video-js.css" />
<style>
.video { width:100%; }
.view-container { max-width: 1100px; margin: 18px auto; padding: 8px; }
.video-player-container { background: #000; margin-bottom: 20px; border-radius: 4px; overflow: hidden; }
.playlist-wrapper { height: 220px; margin-top: 12px; overflow-y: auto; padding: 6px; background: var(--bg); border-radius: 4px; border: 1px solid var(--border); }
.playlist-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; align-content: start; }
.playlist-item { cursor: pointer; border: 2px solid transparent; border-radius: 4px; overflow: hidden; transition: all 0.3s ease; background: var(--bg); color: var(--text); }
.playlist-item:hover { border-color: #3f51b5; transform: translateY(-2px); }
.playlist-item.active { border-color: #818181; }
.playlist-thumbnail { width: 100%; height: 120px; object-fit: cover; background: var(--bg); }
.playlist-info { padding: 8px; }
.playlist-title { font-weight: bold; margin-bottom: 4px; font-size: 14px; color: var(--text); }
.controls { margin: 0; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; float: right; }
.video-info { margin: 0; padding: 0; background: none; border-radius: 4px; float: left; width: 200px; }
.video-info h4 { font-size: 0.7em; margin: 0 0 5px 0; color: var(--text); }
.header-nav { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
</style>

<div class="view-container">
  <div class="header-nav">
    <a href="view_video_admin.php" class="btn btn-outline-primary">Admin</a>
    <?php
    $stmt = $pdo->query("SELECT id, name, (SELECT COUNT(*) FROM playlist_videos WHERE playlist_id = video_playlists.id) as video_count FROM video_playlists ORDER BY name ASC");
    $pls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($pls) > 1) {
        echo '<select id="playlistSelector" class="btn" style="padding:6px 10px;"><option value="">All Videos</option>';
        foreach ($pls as $pl) {
            $sel = ($playlistId == $pl['id']) ? 'selected' : '';
            echo '<option value="' . $pl['id'] . '" ' . $sel . '>' . htmlspecialchars($pl['name']) . ' (' . $pl['video_count'] . ')</option>';
        }
        echo '</select>';
    }
    ?>
  </div>
  
  <div id="playlist-config" style="display:none" data-api-url="/video_admin_api.php" data-playlist-id="<?= htmlspecialchars($playlistId ?? '') ?>" data-category-id="<?= htmlspecialchars($categoryId ?? '') ?>"></div>

  <div class="video-player-container">
    <video id="main-video-player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="400" height="400" data-setup='{}'></video>
  </div>

  <div class="video-info" id="current-video-info">
    <h4 style="padding: 5px;" id="video-title" class="notification notification-info">Video Title</h4>
  </div>

  <div class="controls">
    <!-- NEW: Extract Button -->
    <button id="extract-current-btn" class="btn btn-sm btn-outline-success">✂️ Extract Frame</button>
    <button id="prev-video" class="btn btn-sm" disabled>Previous</button>
    <button id="next-video" class="btn btn-sm" disabled>Next</button>
  </div>
  <div style="clear:both;"></div>

  <div class="playlist-wrapper"><div class="playlist-container" id="playlist-container"></div></div>
</div>

<!-- Render Modules -->
<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script src="/vendor/video.min.js"></script>
<script>
(function() {
  const cfg = document.getElementById('playlist-config');
  const apiUrl = cfg.dataset.apiUrl;
  
  let videos = [], curIdx = 0, player = null;
  const plId = cfg.dataset.playlistId;
  const catId = cfg.dataset.categoryId;
  
  const sel = document.getElementById('playlistSelector');
  if(sel) sel.onchange = () => window.location.href = sel.value ? 'view_video_playlist.php?playlist_id='+sel.value : 'view_video_playlist.php';

  function fmtTime(s) { const m=Math.floor(s/60), sc=Math.floor(s%60); return `${m}:${sc.toString().padStart(2,'0')}`; }

  function load() {
    let url = 'video_admin_api.php?';
    if(plId) url = 'video_admin_api.php?action=get_playlist_json&id='+plId;
    else {
        url += 'action=list_videos';
        if(catId) url += '&category_id='+catId;
    }

    fetch(url).then(r=>r.json()).then(d => {
        if(d.status==='ok') {
            if(d.json_string) videos = JSON.parse(d.json_string);
            else if(d.videos) {
                videos = d.videos.map(v => ({
                    id: v.id, // Ensure ID is mapped
                    title: v.name, 
                    url: v.url, 
                    thumbnail: v.thumbnail, 
                    duration: v.duration, 
                    type: v.type
                }));
            }
            render();
            if(videos.length) playVideo(0, false);
        }
    });
  }

  function render() {
      const c = document.getElementById('playlist-container');
      if(!videos.length) { c.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:20px;">No videos</div>'; return; }
      c.innerHTML = videos.map((v,i) => `
        <div class="playlist-item" data-index="${i}">
            <img src="${v.thumbnail}" class="playlist-thumbnail">
            <div class="playlist-info">
                <div class="playlist-title">${v.title}</div>
                <div style="font-size:12px;">${fmtTime(v.duration)}</div>
            </div>
        </div>
      `).join('');
      document.querySelectorAll('.playlist-item').forEach(el => el.onclick = () => playVideo(parseInt(el.dataset.index), true));
  }

  function playVideo(idx, auto) {
      if(idx<0 || idx>=videos.length) return;
      curIdx = idx;
      const v = videos[idx];
      
      if(!player) {
          player = videojs('main-video-player', { fluid:true });
          player.on('ended', () => playVideo(curIdx+1, true));
      }
      
      player.src({ src: v.url, type: v.type || 'video/mp4' });
      document.getElementById('video-title').textContent = v.title;
      
      document.querySelectorAll('.playlist-item').forEach(el => el.classList.toggle('active', parseInt(el.dataset.index)===idx));
      document.getElementById('prev-video').disabled = idx===0;
      document.getElementById('next-video').disabled = idx===videos.length-1;
      
      if(auto) player.play();
  }

  document.getElementById('prev-video').onclick = () => playVideo(curIdx-1, true);
  document.getElementById('next-video').onclick = () => playVideo(curIdx+1, true);

  // LOGIC FOR EXTRACT BUTTON
  document.getElementById('extract-current-btn').onclick = () => {
      if(curIdx < 0 || curIdx >= videos.length) return;
      const v = videos[curIdx];
      if(v.id) {
          window.VideoFrameExtractor.open(v.url, v.id);
      } else {
          alert("Video ID not available, cannot extract.");
      }
  };

  load();
})();
</script>
<?php $content = ob_get_clean(); $spw->renderLayout($content, $pageTitle); ?>