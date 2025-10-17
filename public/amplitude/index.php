<?php require '../bootstrap.php'; ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peter Sebring — LUNATICS Player</title>

<!-- Include font -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Google Fonts via CDN -->
    <link href="https://fonts.googleapis.com/css?family=Lato:400,400i" rel="stylesheet">
<?php else: ?>
    <!-- Local Lato font (downloaded version) -->
    <link rel="stylesheet" href="/vendor/fonts/lato/lato.css">
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- AmplitudeJS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/amplitudejs@5.3.2/dist/amplitude.min.js"></script>

    <!-- AmplitudeJS Visualizations via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/amplitudejs@5.3.2/dist/visualizations/michaelbromley.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/amplitudejs@5.3.2/dist/visualizations/frequencyanalyzer.js"></script>
<?php else: ?>
    <!-- AmplitudeJS via local copy -->
    <script src="/vendor/amplitude/amplitude.min.js"></script>

    <!-- AmplitudeJS Visualizations via local copy -->
    <script src="/vendor/amplitude/visualizations/michaelbromley.js"></script>
    <script src="/vendor/amplitude/visualizations/frequencyanalyzer.js"></script>
<?php endif; ?>

<!-- Include Style Sheet -->
<link rel="stylesheet" type="text/css" href="css/app.css"/>
</head>
<body>
<div id="visualizations-player">
  <div class="top-container">
    <img class="now-playing-album-art" id="large-now-playing-album-art" data-amplitude-song-info="cover_art_url"/>
    <div class="amplitude-visualization" id="large-visualization"></div>
    <div class="visualization-toggle visualization-on"></div>
    <div class="amplitude-shuffle"></div>
    <div class="amplitude-repeat"></div>
  </div>

  <div class="meta-data-container">
    <span class="now-playing-name" data-amplitude-song-info="name"></span>
    <span class="now-playing-artist-album">
      <span class="now-playing-artist" data-amplitude-song-info="artist"></span> - 
      <span class="now-playing-album" data-amplitude-song-info="album"></span>
    </span>
  </div>

  <div class="amplitude-wave-form"></div>
  <input type="range" class="amplitude-song-slider" id="global-large-song-slider"/>

  <div>
    <span class="amplitude-current-time"></span>
    <span class="amplitude-time-remaining"></span>
  </div>

  <div class="control-container">
    <div class="amplitude-prev"></div>
    <div class="amplitude-play-pause amplitude-paused"></div>
    <div class="amplitude-next"></div>
  </div>

  <div class="song-navigation">
    <input type="range" class="amplitude-song-slider"/>
  </div>

  <div class="arrow-up">
    <img src="img/arrow-up.svg" class="arrow-up-icon"/>
  </div>

  <div id="visualizations-player-playlist">
    <div class="top-arrow">
      <img src="img/arrow-down.svg" class="arrow-down-icon"/>
    </div>

    <div class="top">
      <span class="playlist-title">Songs</span>
      <div class="amplitude-repeat"></div>
      <div class="amplitude-shuffle"></div>
    </div>

    <div class="songs-container">
      <!-- SONGS -->
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="0">
        <span class="song-position">01</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="0"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="0"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="0"></span>
        </div>
      </div>
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="1">
        <span class="song-position">02</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="1"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="1"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="1"></span>
        </div>
      </div>
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="2">
        <span class="song-position">03</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="2"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="2"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="2"></span>
        </div>
      </div>
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="3">
        <span class="song-position">04</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="3"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="3"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="3"></span>
        </div>
      </div>
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="4">
        <span class="song-position">05</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="4"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="4"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="4"></span>
        </div>
      </div>
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="5">
        <span class="song-position">06</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="5"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="5"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="5"></span>
        </div>
      </div>
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="6">
        <span class="song-position">07</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="6"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="6"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="6"></span>
        </div>
      </div>
      <div class="song amplitude-song-container amplitude-play-pause" data-amplitude-song-index="7">
        <span class="song-position">08</span>
        <img class="song-album-art" data-amplitude-song-info="cover_art_url" data-amplitude-song-index="7"/>
        <div class="song-meta-data-container">
          <span class="song-name" data-amplitude-song-info="name" data-amplitude-song-index="7"></span>
          <span class="song-artist" data-amplitude-song-info="artist" data-amplitude-song-index="7"></span>
        </div>
      </div>
    </div>

    <div class="active-audio">
      <img class="cover-art-small" data-amplitude-song-info="cover_art_url"/>
      <div class="active-audio-right">
        <span class="song-name" data-amplitude-song-info="name"></span>
        <div class="active-audio-controls">
          <div class="amplitude-prev"></div>
          <div class="amplitude-play-pause"></div>
          <div class="amplitude-next"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="pre-load-img-container">
  <img src="img/play.svg"/>
  <img src="img/pause.svg"/>
  <img src="img/next.svg"/>
  <img src="img/prev.svg"/>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  Amplitude.init({
    songs: [
      { name: "Bang Bang",          artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/01-bang-bang.mp3", cover_art_url: "img/lunatics_cover.jpg" },
      { name: "Lunatics",           artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/02-lunatics.mp3", cover_art_url: "img/lunatics_cover.jpg" },
      { name: "Face of Delight",    artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/03-face-of-delight.mp3", cover_art_url: "img/lunatics_cover.jpg" },
      { name: "The Last",           artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/04-the-last.mp3", cover_art_url: "img/lunatics_cover.jpg" },
      { name: "Predicted",          artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/05-predicted.mp3", cover_art_url: "img/lunatics_cover.jpg" },
      { name: "Pictures",           artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/06-pictures.mp3", cover_art_url: "img/lunatics_cover.jpg" },
      { name: "Slowly Gettin Back", artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/07-slowly-gettin-back.mp3", cover_art_url: "img/lunatics_cover.jpg" },
      { name: "Rock On",            artist: "Peter Sebring", album: "LUNATICS", url: "https://sebastianpw.github.io/sg_showcase_01/lunatics/08-rock-on.mp3", cover_art_url: "img/lunatics_cover.jpg" }
    ]
  });
});
</script>

<script type="text/javascript" src="js/functions.js"></script>
</body>
</html>
