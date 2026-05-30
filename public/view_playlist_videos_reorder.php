<?php
// public/view_playlist_videos_reorder.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$playlistId = (int)($_GET['playlist_id'] ?? 0);
if (!$playlistId) { header('Location: view_video_playlists_admin.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM video_playlists WHERE id = ?");
$stmt->execute([$playlistId]);
$playlist = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$playlist) { header('Location: view_video_playlists_admin.php'); exit; }

$pageTitle = "Reorder Videos - " . $playlist['name'];
ob_start();
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
.reorder-wrap { max-width: 900px; margin: 0 auto; padding: 18px; }
.reorder-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.video-list { background: var(--card); border-radius: 8px; padding: 12px; }
.video-item { background: var(--bg); border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 8px; padding: 12px; margin-bottom: 12px; cursor: move; display: flex; align-items: center; gap: 12px; }
.video-item.dragging { opacity: 0.7; transform: scale(1.02); }
.video-item.drag-over { border-color: var(--accent); }
.drag-handle { cursor: grab; padding: 8px; color: var(--text-muted); display:flex; flex-direction:column; gap:3px;}
.drag-handle span { width:20px; height:3px; background:currentColor; }
.video-thumb { width: 100px; height: 56px; object-fit: cover; border-radius: 4px; }
.video-info { flex: 1; }
.sort-number { font-weight: bold; width: 30px; text-align: center; }
.save-indicator { display: none; color: var(--accent); font-weight: bold; }
.save-indicator.show { display: block; }
</style>

<div class="reorder-wrap">
    <div class="reorder-header">
        <div>
            <h2 style="margin:0;">Reorder Videos</h2>
            <div style="color:var(--text-muted);">${playlist['name']}</div>
        </div>
        <div>
            <span class="save-indicator" id="saveInd">Saving...</span>
            <a href="view_video_playlists_admin.php" class="btn btn-sm btn-secondary">Back</a>
        </div>
    </div>
    <div class="video-list" id="list">Loading...</div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
    const pid = <?= $playlistId ?>;
    let videos = [];
    const list = document.getElementById('list');

    function load() {
        fetch('video_admin_api.php?action=get_playlist_json&id='+pid).then(r=>r.json()).then(d=>{
            if(d.status==='ok') { videos=JSON.parse(d.json_string); render(); }
        });
    }

    function render() {
        if(!videos.length) { list.innerHTML='<div style="padding:20px;text-align:center;">No videos in playlist</div>'; return; }
        list.innerHTML = videos.map((v,i) => `
            <div class="video-item" draggable="true" data-index="${i}" data-id="${v.id}">
                <div class="sort-number">${i+1}</div>
                <div class="drag-handle"><span></span><span></span><span></span></div>
                <img src="${v.thumbnail}" class="video-thumb">
                <div class="video-info"><strong>${v.title}</strong></div>
            </div>
        `).join('');
        setupDrag();
    }

    function setupDrag() {
        let dragEl = null;
        document.querySelectorAll('.video-item').forEach(item => {
            item.addEventListener('dragstart', function(e) { dragEl=this; e.dataTransfer.effectAllowed='move'; this.classList.add('dragging'); });
            item.addEventListener('dragend', function() { this.classList.remove('dragging'); document.querySelectorAll('.video-item').forEach(i=>i.classList.remove('drag-over')); });
            item.addEventListener('dragover', function(e) { e.preventDefault(); if(this===dragEl) return; this.classList.add('drag-over'); });
            item.addEventListener('drop', function(e) {
                e.stopPropagation();
                if(dragEl && this!==dragEl) {
                    const from = parseInt(dragEl.dataset.index);
                    const to = parseInt(this.dataset.index);
                    const m = videos.splice(from,1)[0];
                    videos.splice(to,0,m);
                    render();
                    save();
                }
            });
        });
    }

    function save() {
        document.getElementById('saveInd').classList.add('show');
        fetch('video_admin_api.php?action=update_playlist_video_order', {
            method:'POST', body:JSON.stringify({playlist_id: pid, video_ids: videos.map(v=>v.id)})
        }).then(r=>r.json()).then(d=>{
            if(d.status==='ok') {
                if(typeof Toast!=='undefined') Toast.show('Order saved');
            }
        }).finally(() => setTimeout(()=>document.getElementById('saveInd').classList.remove('show'), 500));
    }
    load();
})();
</script>
<?php $content = ob_get_clean(); $spw->renderLayout($content, $pageTitle); ?>