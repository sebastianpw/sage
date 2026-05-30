<?php
// public/player_audio_playlist.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$entityType = $_GET['entity_type'] ?? '';
$entityId   = (int)($_GET['entity_id'] ?? 0);

if (!$entityType || !$entityId) {
    die("Error: Missing parameters.");
}

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Fetch Audio via Mapping Table
$mapTable = "audios_2_" . preg_replace('/[^a-z0-9_]/', '', $entityType);
$songs = [];

try {
    // Verify mapping table exists
    $check = $pdo->query("SHOW TABLES LIKE '$mapTable'");
    if ($check->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT a.* 
            FROM audios a 
            JOIN $mapTable m ON a.id = m.from_id 
            WHERE m.to_id = ? 
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$entityId]);
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $songs[] = [
                "name" => $row['name'] ?: 'Audio #' . $row['id'],
                "artist" => $row['rvc_model_name'] ?: 'Raw Audio',
                "album" => "ID: " . $row['id'],
                "url" => $row['filename'],
                "cover_art_url" => "/img/audio_placeholder.png"
            ];
        }
    }
} catch (Exception $e) {
    // Ignore db errors, show empty playlist
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Player</title>
    <link rel="stylesheet" href="/css/base.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/amplitudejs@5.3.2/dist/amplitude.js"></script>
    <style>
        body { background: var(--bg); color: var(--text); padding: 20px; font-family: sans-serif; display:flex; flex-direction:column; align-items:center; height:100vh; overflow:hidden; }
        .player-wrapper { width: 100%; max-width: 600px; display:flex; flex-direction:column; height:100%; gap:20px; }
        
        /* Player Card */
        #single-song-player { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--card-elevation); text-align: center; flex-shrink: 0; }
        .meta-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 5px; color:var(--text); }
        .meta-artist { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px; }
        .time-container { font-family: monospace; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 10px; }
        
        /* Controls */
        .controls { display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 10px; }
        .ctrl-btn { cursor: pointer; font-size: 2rem; color: var(--accent); user-select: none; }
        .ctrl-btn:hover { transform: scale(1.1); }
        .play-pause-btn { font-size: 3rem; }
        .play-pause-btn.amplitude-paused::before { content: "▶"; }
        .play-pause-btn.amplitude-playing::before { content: "⏸"; }

        input[type=range].amplitude-song-slider { width: 100%; cursor: pointer; }

        /* Playlist */
        .playlist { flex-grow: 1; overflow-y: auto; background: var(--card); border: 1px solid var(--border); border-radius: 12px; }
        .song-row { padding: 12px 15px; border-bottom: 1px solid var(--border); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; }
        .song-row:hover { background: rgba(255,255,255,0.05); }
        .song-row.amplitude-active-song-container { background: rgba(var(--accent-rgb), 0.15); border-left: 4px solid var(--accent); }
        .song-info { font-weight: 600; font-size: 0.95rem; }
        .song-details { font-size: 0.8rem; color: var(--text-muted); }
        
        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); }
    </style>
</head>
<body>

<div class="player-wrapper">
    <?php if(empty($songs)): ?>
        <div class="empty-state">
            <h3>No Audio Found</h3>
            <p>No audio files are attached to this entity.</p>
        </div>
    <?php else: ?>
        <div id="single-song-player">
            <div class="meta-title" amplitude-song-info="name" amplitude-main-song-info="true"></div>
            <div class="meta-artist" amplitude-song-info="artist" amplitude-main-song-info="true"></div>
            
            <div class="controls">
                <div class="ctrl-btn amplitude-prev">⏮</div>
                <div class="ctrl-btn play-pause-btn amplitude-play-pause" amplitude-main-play-pause="true"></div>
                <div class="ctrl-btn amplitude-next">⏭</div>
            </div>
            
            <div class="time-container">
                <span class="amplitude-current-time" amplitude-main-current-time="true">0:00</span> / 
                <span class="amplitude-duration" amplitude-main-duration="true">0:00</span>
            </div>
            <input type="range" class="amplitude-song-slider" amplitude-main-song-slider="true"/>
        </div>

        <div class="playlist">
            <?php foreach($songs as $i => $s): ?>
                <div class="song-row amplitude-song-container amplitude-play-pause" data-amplitude-song-index="<?= $i ?>">
                    <div>
                        <div class="song-info"><?= htmlspecialchars($s['name']) ?></div>
                        <div class="song-details"><?= htmlspecialchars($s['artist']) ?></div>
                    </div>
                    <div style="font-size:1.2rem;">▶</div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <script>
            Amplitude.init({
                songs: <?= json_encode($songs) ?>,
                default_album_art: '/img/audio_placeholder.png',
                continue_next: true
            });
            // Auto play first song if needed, or just prepare
        </script>
    <?php endif; ?>
    
    <button onclick="window.parent.closeAudioModal()" style="margin-top:10px; background:none; border:1px solid var(--border); color:var(--text); padding:8px 16px; border-radius:6px; cursor:pointer;">Close</button>
</div>

</body>
</html>
