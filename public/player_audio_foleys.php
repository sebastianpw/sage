<?php
// Template Placeholder: audio_foleys
// Generated via rollout_audio_players.sh

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

$entity = 'audio_foleys';
$viewName = "v_player_" . $entity;
$selfScript = basename($_SERVER['PHP_SELF']);

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Icon Selection
$iconChar = $entityIcons[$entity] ?? '🎧';

// --- INIT UI MODULES ---
$registry = \App\UI\Modules\ModuleRegistry::getInstance();

$audioGear = $registry->get('audio_gear_menu', [
    'entity_types' => [$entity],
]);

$audioEditor = $registry->get('audio_editor');

// --- HANDLE AJAX REQUESTS ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action == 'fetch_playlist') {
        $search = $_POST['search'] ?? '';
        $limit  = (int)($_POST['limit'] ?? 100); 
        
        try {
            $sql = "SELECT * FROM `$viewName`";
            $where = [];
            $params = [];

            if ($search !== '') {
                $where[] = "(name LIKE :s OR description LIKE :s OR audio_name LIKE :s OR model LIKE :s OR filename LIKE :s)";
                $params['s'] = "%$search%";
            }

            if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);
            
            $sql .= " ORDER BY created_at DESC LIMIT :limit";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $songs = [];
            foreach ($rows as $row) {
                $title = $row['name'] ? $row['name'] : "Audio #".$row['audio_id'];
                if ($row['audio_name']) $title .= " (" . $row['audio_name'] . ")";
                
                $songs[] = [
                    "name" => $title,
                    "artist" => $row['model'] ?: 'Unknown Model',
                    "album" => $row['description'] ?: ucfirst(str_replace('_',' ',$entity)),
                    "url" => $row['filename'],
                    "cover_art_url" => "/img/audio_placeholder.png", 
                    "created_at" => $row['created_at'],
                    "id" => $row['audio_id'],
                    "entity_id" => $row['entity_id'] ?? 0 
                ];
            }

            echo json_encode(['status' => 'ok', 'songs' => $songs]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Player: <?php echo ucfirst(str_replace('_', ' ', $entity)); ?></title>

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {}
  })();
</script>

<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- Swiper -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<!-- AmplitudeJS -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/amplitudejs@5.3.2/dist/amplitude.js"></script>

<!-- Audio Modules CSS -->
<?php echo $audioGear->renderCSS(); ?>
<?php echo $audioEditor->renderCSS(); ?>

<style>
/* Modern styling using base.css vars */
body {
    padding: 20px;
    background-color: var(--bg);
    color: var(--text);
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden; 
    box-sizing: border-box;
}

/* HEADER */
.header-compact {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    margin-left: 40px; 
    flex-shrink: 0;
}

.entity-icon-link {
    font-size: 1.8rem;
    text-decoration: none;
    line-height: 1;
    transition: transform 0.2s;
}
.entity-icon-link:hover { transform: scale(1.15); }

.search-line {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-grow: 1;
}

.search-input {
    padding: 8px 12px;
    font-size: 0.9rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--card);
    color: var(--text);
    width: 100%;
    max-width: 400px;
}
.search-input:focus { outline: none; border-color: var(--accent); }

/* MAIN LAYOUT */
.player-container {
    display: flex;
    gap: 20px;
    flex-grow: 1;
    overflow: hidden;
}

/* PLAYER CARD (Left/Top) */
#amplitude-player {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--card-elevation);
    width: 100%;
    max-width: 350px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    padding: 20px;
    text-align: center;
    box-sizing: border-box;
    /* Center vertical if content is sparse */
    justify-content: center; 
}

.meta-container { margin-bottom: 10px; min-height: 60px; }
.song-name { font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.song-artist-album { font-size: 0.85rem; color: var(--text-muted); }

.time-container {
    display: flex;
    justify-content: center;
    gap: 5px;
    font-size: 0.9rem;
    color: var(--text-muted);
    font-family: monospace;
    margin-bottom: 15px;
}

/* Progress Bar */
input[type=range].amplitude-song-slider {
    width: 100%;
    -webkit-appearance: none;
    background: transparent;
    cursor: pointer;
    /* No margin bottom needed as controls are gone */
    margin-bottom: 0; 
}
input[type=range].amplitude-song-slider::-webkit-slider-runnable-track {
    width: 100%; height: 6px; background: var(--border); border-radius: 3px;
}
input[type=range].amplitude-song-slider::-webkit-slider-thumb {
    height: 14px; width: 14px; border-radius: 50%; background: var(--accent);
    margin-top: -4px; -webkit-appearance: none;
}

/* PLAYLIST (Right/Bottom) - Adapted for Swiper */
.playlist-wrapper {
    flex-grow: 1;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--card-elevation);
    overflow: hidden; /* Swiper handles scrolling */
    display: flex;
    flex-direction: column;
    position: relative;
}

/* Swiper specific overrides */
.swiper {
    width: 100%;
    height: 100%;
}
.swiper-slide {
    height: auto;
    overflow-y: auto;
    padding-bottom: 40px;
}

.playlist-row {
    display: flex;
    align-items: center;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
    width: 100%;
}
.playlist-row:hover { background: rgba(var(--muted-border-rgb), 0.05); }

/* The clickable play area */
.playlist-clickable {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    padding: 12px 15px;
    cursor: pointer;
    overflow: hidden;
    user-select: none;
}

/* The gear menu area */
.playlist-actions {
    padding: 0 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-left: 1px solid transparent;
}

/* Active State Styling */
.playlist-clickable.amplitude-active-song-container {
    background: rgba(var(--muted-border-rgb), 0.1);
    border-left: 4px solid var(--accent);
    padding-left: 11px; /* compensate for border */
}

/* Override Gear Icon Size locally */
.audio-gear-icon {
    font-size: 1.2rem !important; /* Larger icon */
    width: 40px !important;        /* Larger hit area */
    height: 40px !important;
}

.pl-meta { flex-grow: 1; overflow: hidden; }
.pl-name { font-weight: 600; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); }
.pl-details { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }

/* Swiper Navigation Customization */
.swiper-button-next, .swiper-button-prev {
    color: var(--accent);
    transform: scale(0.6);
}
.swiper-pagination-bullet-active {
    background: var(--accent);
}

/* Responsive */
@media (max-width: 900px) {
    .player-container { flex-direction: column; }
    #amplitude-player { width: 100%; max-width: 100%; height: auto; margin-bottom: 20px; padding: 15px; }
}
</style>
</head>
<body>

<div class="header-compact">
    <a href="audio_sql_crud_<?php echo $entity; ?>.php" class="entity-icon-link" title="Back to CRUD"><?php echo $iconChar; ?></a>
    <div class="search-line">
        <input type="text" id="searchInput" class="search-input" placeholder="Search audio by name, desc, model...">
        <button id="refreshBtn" class="btn btn-sm btn-outline-secondary">↻</button>
    </div>
</div>

<div class="player-container">
    <!-- Left: Player Controls (Minimalist: No Buttons) -->
    <div id="amplitude-player">
        <div class="meta-container" style="display:none;">
            <div class="song-name" amplitude-song-info="name" amplitude-main-song-info="true">Select Audio</div>
            <div class="song-artist-album">
                <span amplitude-song-info="artist" amplitude-main-song-info="true"></span>
                <span amplitude-song-info="album" amplitude-main-song-info="true"></span>
            </div>
        </div>

        <div class="time-container">
            <span class="amplitude-current-time" amplitude-main-current-time="true">0:00</span>
            <span>/</span>
            <!-- Manual ID for JS targeting -->
            <span class="amplitude-duration" id="player-total-duration" amplitude-main-duration="true">0:00</span>
        </div>
        
        <input type="range" class="amplitude-song-slider" amplitude-main-song-slider="true"/>
    </div>

    <!-- Right: Playlist with Swiper -->
    <div class="playlist-wrapper">
        <!-- Swiper -->
        <div class="swiper mySwiper" id="playlist-swiper">
            <div class="swiper-wrapper" id="playlist-list-wrapper">
                <!-- Slides injected here -->
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-pagination"></div>
        </div>
        <div id="playlist-loading" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:var(--text-muted);">
            Loading playlist...
        </div>
    </div>
</div>

<script>
const SELF_URL = '<?php echo $selfScript; ?>';
const ENTITY_TYPE = '<?php echo $entity; ?>';
const ITEMS_PER_PAGE = 10; // Constraint: 10 entries per page

function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

function formatTime(seconds) {
    if(!seconds || isNaN(seconds)) return "0:00";
    let min = Math.floor(seconds / 60);
    let sec = Math.floor(seconds % 60);
    if(sec < 10) sec = "0" + sec;
    return min + ":" + sec;
}

function chunkArray(myArray, chunk_size){
    var results = [];
    while (myArray.length) {
        results.push(myArray.splice(0, chunk_size));
    }
    return results;
}

function loadPlaylist() {
    const search = $('#searchInput').val();
    const wrapper = $('#playlist-list-wrapper');
    const loading = $('#playlist-loading');
    
    // Reset
    wrapper.empty();
    loading.show();
    
    // Destroy previous swiper instance if exists
    if(window.playlistSwiper && window.playlistSwiper.destroy) {
        window.playlistSwiper.destroy(true, true);
    }

    $.post(SELF_URL, { action: 'fetch_playlist', search: search }, function(res) {
        loading.hide();
        if (res.status === 'ok') {
            const songs = res.songs;
            
            if (songs.length === 0) {
                wrapper.html('<div class="swiper-slide"><div style="padding:20px; text-align:center;">No audio found.</div></div>');
                return;
            }

            // Create a copy for chunking so we don't destroy the original for Amplitude
            const songsForDom = JSON.parse(JSON.stringify(songs));
            const pages = chunkArray(songsForDom, ITEMS_PER_PAGE);
            
            let globalIndex = 0;

            pages.forEach((pageItems) => {
                let slideHtml = '<div class="swiper-slide">';
                
                pageItems.forEach((song) => {
                    // Note: globalIndex matches the index in the full 'songs' array used by Amplitude
                    
                    slideHtml += `
                    <div class="playlist-row">
                        <!-- Left: Clickable Play Area (Clean, no icons) -->
                        <div class="playlist-clickable amplitude-song-container amplitude-play-pause" 
                             data-amplitude-song-index="${globalIndex}">
                            <div class="pl-meta">
                                <div class="pl-name">${song.name}</div>
                                <div class="pl-details">${song.artist} • ${song.album}</div>
                            </div>
                        </div>

                        <!-- Right: Actions Area (Gear) -->
                        <div class="playlist-actions">
                            <div class="audio-gear-wrapper">
                                <span class="audio-gear-icon" 
                                      data-entity="${ENTITY_TYPE}" 
                                      data-audio-id="${song.id}" 
                                      data-entity-id="${song.entity_id}"
                                      data-url="${song.url}"
                                      onclick="window.AudioGearMenu.open(this, event)">
                                      &#9881;
                                </span>
                            </div>
                        </div>
                    </div>`;
                    
                    globalIndex++;
                });

                slideHtml += '</div>'; // Close swiper-slide
                wrapper.append(slideHtml);
            });

            // Initialize Swiper
            window.playlistSwiper = new Swiper(".mySwiper", {
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                    dynamicBullets: true,
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
                allowTouchMove: true,
                autoHeight: false, 
            });

            // Initialize Amplitude
            Amplitude.init({
                songs: songs,
                default_album_art: '/img/audio_placeholder.png',
                continue_next: true
            });
            
            // --- FIX: Force Duration Update when Metadata Loads ---
            const audioEl = Amplitude.getAudio();
            if(audioEl) {
                audioEl.preload = "metadata";
                audioEl.addEventListener('loadedmetadata', function() {
                    const dur = audioEl.duration;
                    const el = document.getElementById('player-total-duration');
                    if(el) el.innerText = formatTime(dur);
                });
                audioEl.addEventListener('durationchange', function() {
                     const el = document.getElementById('player-total-duration');
                     if(el) el.innerText = formatTime(audioEl.duration);
                });
            }
            
        } else {
            wrapper.html('<div class="swiper-slide"><div style="padding:20px; text-align:center; color:var(--red);">Error: ' + res.message + '</div></div>');
        }
    }, 'json');
}

$(document).ready(function() {
    loadPlaylist();

    $('#searchInput').on('keyup', debounce(function() {
        loadPlaylist();
    }, 500));

    $('#refreshBtn').click(function() {
        loadPlaylist();
        Toast.show('Playlist refreshed', 'info');
    });
});
</script>

<!-- Audio Modules JS -->
<?php echo $audioGear->renderJS(); ?>
<?php echo $audioEditor->render(); ?>
<?php require "modal_audio_details.php"; ?>

<?php require_once "forge_tool.php"; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>