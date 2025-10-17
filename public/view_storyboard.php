<?php
// view_storyboard.php
require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();

$pageTitle = "Storyboard - Reorder Frames";
ob_start();

/**
 * Use only web-relative paths for `dir` and resolve abs paths using $_SERVER['DOCUMENT_ROOT'].
 * Accepts: "uploads/highlights" or "/uploads/highlights".
 * Rejects anything containing '..' to prevent traversal.
 */

// defaults (from bootstrap)
$defaultFramesDirRel = rtrim($framesDirRel ?? '/public/frames', '/'); // ensure a sane default if not set
if ($defaultFramesDirRel === '') $defaultFramesDirRel = '/';

$requestedDirRaw = $_GET['dir'] ?? '';
$requestedDir = trim((string)$requestedDirRaw);

// normalize and basic validation
if ($requestedDir !== '') {
    // strip leading slash(es) and ensure single leading slash for internal representation
    $requestedDir = '/' . ltrim($requestedDir, '/');
    $requestedDir = rtrim($requestedDir, '/');
} else {
    $requestedDir = '';
}

// reject dangerous patterns early
$dir_error = '';
if ($requestedDir !== '' && (strpos($requestedDir, '..') !== false || strpos($requestedDir, "\0") !== false)) {
    $dir_error = 'Invalid directory parameter.';
    $requestedDir = '';
}

// determine which web-relative dir to use
$useFramesDirRel = $defaultFramesDirRel;
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    // fallback: try to get a sensible docroot from SpwBase if available (but prefer server docroot)
    // we intentionally avoid using getProjectPath() here; this fallback is only if $_SERVER['DOCUMENT_ROOT'] missing
    $docRoot = rtrim($spw->getProjectPath() . '/public', '/'); // fallback only
}

if ($requestedDir !== '') {
    $candidateRel = $requestedDir; // e.g. /uploads/highlights
    $candidateAbs = realpath($docRoot . $candidateRel);
    if ($candidateAbs !== false && is_dir($candidateAbs)) {
        // ensure candidate is inside docroot
        $docPrefix = rtrim(realpath($docRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $candPrefix = rtrim($candidateAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($candPrefix, $docPrefix) === 0) {
            // set web-relative path for image src (preserve leading slash)
            $useFramesDirRel = $candidateRel;
        } else {
            $dir_error = 'Requested directory is outside the web document root and was ignored.';
        }
    } else {
        $dir_error = 'Requested directory not found; using default frames directory.';
    }
}

// ensure leading slash form for building URLs
if ($useFramesDirRel === '') $useFramesDirRel = '/';
if ($useFramesDirRel[0] !== '/') $useFramesDirRel = '/' . ltrim($useFramesDirRel, '/');

// build server absolute path for file operations
$useFramesDirAbs = rtrim($docRoot, '/') . $useFramesDirRel;
$useFramesDirAbs = rtrim($useFramesDirAbs, '/');

// gather images (web-allowed extensions)
$allowedExt = ['jpg','jpeg','png','webp','gif'];
$files = [];
if (is_dir($useFramesDirAbs)) {
    foreach (scandir($useFramesDirAbs) as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt, true) && is_file($useFramesDirAbs . '/' . $f)) {
            $files[] = $f;
        }
    }
    natcasesort($files);
    $files = array_values($files);
}

// apply saved order if present
$orderFile = $useFramesDirAbs . '/storyboard_order.json';
if (file_exists($orderFile)) {
    $json = @file_get_contents($orderFile);
    $saved = @json_decode($json, true);
    if (is_array($saved) && count($saved)) {
        $ordered = [];
        foreach ($saved as $name) {
            if (in_array($name, $files, true)) $ordered[] = $name;
        }
        foreach ($files as $f) {
            if (!in_array($f, $ordered, true)) $ordered[] = $f;
        }
        $files = $ordered;
    }
}
?>

<!-- Swiper CSS (for future use) -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
<?php endif; ?>

<!-- PhotoSwipe CSS -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<?php endif; ?>

<!-- Font Awesome -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
<?php endif; ?>

<style>
/* same mobile-first stylesheet as before (kept compact) */
.storyboard-wrap { padding: 12px; }
.storyboard-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(110px,1fr)); gap:8px; align-items:start; }
.frame-card { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,0.08); padding:6px; display:flex; flex-direction:column; gap:6px; user-select:none; touch-action:manipulation; }
.frame-thumb { width:100%; height:90px; object-fit:cover; border-radius:6px; cursor:pointer; }
.frame-meta { display:flex; justify-content:space-between; align-items:center; gap:6px; font-size:13px; }
.handle { cursor:grab; padding:6px; border-radius:6px; background: rgba(0,0,0,0.03); }
.btn { font-size:12px; padding:6px 8px; border-radius:6px; border:none; background:#f3f3f3; }
.toolbar { display:flex; gap:8px; align-items:center; margin-bottom:10px; flex-wrap:wrap; }
.save-status { font-size:13px; color:#666; }
@media(min-width:800px) { .storyboard-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); } .frame-thumb { height:120px; } }
</style>

<div class="view-container storyboard-wrap">
  <h2><?php echo htmlspecialchars($pageTitle); ?></h2>

  <div style="margin-bottom:6px; font-size:13px; color:#444;">
    Selected dir: <strong><?php echo htmlspecialchars($useFramesDirRel); ?></strong>
    <?php if (!empty($dir_error)): ?>
      <span style="color:#b00; margin-left:10px;"><?php echo htmlspecialchars($dir_error); ?></span>
    <?php endif; ?>
  </div>

  <div class="toolbar">
    <button id="btn-save" class="btn">Save Order</button>
    <button id="btn-auto-prefix" class="btn" title="(optional) rename files with numeric prefixes">Auto-prefix filenames (optional)</button>
    <div class="save-status" id="save-status">Order not saved.</div>
  </div>

  <div id="storyboard" class="storyboard-grid pswp-gallery">
    <?php foreach ($files as $idx => $f):
        $src = $useFramesDirRel . '/' . rawurlencode($f);
        $safeName = htmlspecialchars($f);
        // Get image dimensions for PhotoSwipe (fallback to 1200x800 if can't determine)
        $imgPath = $useFramesDirAbs . '/' . $f;
        $imgSize = @getimagesize($imgPath);
        $width = $imgSize[0] ?? 1200;
        $height = $imgSize[1] ?? 800;
    ?>
      <div class="frame-card" data-filename="<?php echo $safeName; ?>">
        <a href="<?php echo $src; ?>" 
           data-pswp-width="<?php echo $width; ?>" 
           data-pswp-height="<?php echo $height; ?>"
           target="_blank"
           rel="noreferrer">
          <img src="<?php echo $src; ?>" alt="<?php echo $safeName; ?>" class="frame-thumb" loading="lazy" />
        </a>
        <div class="frame-meta">
          <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo $safeName; ?></div>
          <div style="display:flex; gap:6px; align-items:center;">
            <div class="handle" title="drag to reorder">☰</div>
            <button class="btn btn-delete" data-filename="<?php echo $safeName; ?>">🗑</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
?>

<!-- jQuery -->
<?= $spw->getJquery() ?>

<!-- Sortable.js -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<?php else: ?>
  <script src="/vendor/sortable/Sortable.min.js"></script>
<?php endif; ?>

<!-- PhotoSwipe v5 -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
  <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
  <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>

<!-- Swiper (for future use) -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
  <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<script>
$(function(){
  // expose current web-relative dir for AJAX calls (leading slash form)
  var currentDir = <?php echo json_encode($useFramesDirRel); ?>;

  // Initialize PhotoSwipe Lightbox
  const lightbox = new PhotoSwipeLightbox({
    gallery: '.pswp-gallery',
    children: 'a',
    pswpModule: PhotoSwipe,
//    padding: { top: 50, bottom: 50, left: 50, right: 50 },
    wheelToZoom: true
  });


lightbox.addFilter('itemData', (itemData, index) => {
    // If no dimensions provided, PhotoSwipe will load image to detect them
    // This prevents stretching but may show loading state briefly
    return itemData;
});


  lightbox.init();

  // Initialize Sortable
  var el = document.getElementById('storyboard');
  var sortable = Sortable.create(el, {
    animation: 150,
    handle: '.handle',
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    onEnd: function () { $('#save-status').text('Order changed — click Save Order'); }
  });

  // Delete functionality
  $(document).on('click', '.btn-delete', function(){
    var $card = $(this).closest('.frame-card');
    var filename = $card.data('filename');
    if (!confirm('Delete "' + filename + '"? This will attempt to remove the file on the server.')) return;
    $.post('storyboard_order_save.php', { action: 'delete', filename: filename, dir: currentDir })
      .done(function(res){
        if (res.success) {
          $card.remove();
          $('#save-status').text('Deleted: ' + filename);
        } else {
          alert('Delete failed: ' + (res.message || 'unknown'));
        }
      })
      .fail(function(){ alert('Server request failed'); });
  });

  // Save order functionality
  $('#btn-save').on('click', function(){
    var order = [];
    $('#storyboard .frame-card').each(function(){ order.push($(this).data('filename')); });
    $('#save-status').text('Saving...');
    $.ajax({
      url: 'storyboard_order_save.php',
      method: 'POST',
      data: { action: 'save', order: JSON.stringify(order), dir: currentDir },
      dataType: 'json'
    }).done(function(res){
      if (res.success) {
        $('#save-status').text('Order saved (' + new Date().toLocaleTimeString() + ')');
      } else {
        $('#save-status').text('Save failed: ' + (res.message||'unknown'));
      }
    }).fail(function(){ $('#save-status').text('Save failed: server error'); });
  });

  // Auto-prefix functionality
  $('#btn-auto-prefix').on('click', function(){
    if (!confirm('Auto-prefix will rename files on disk by adding numeric prefixes (01_filename). Ensure backups & write permissions. Proceed?')) return;
    var order = [];
    $('#storyboard .frame-card').each(function(){ order.push($(this).data('filename')); });
    $('#save-status').text('Renaming files...');
    $.post('storyboard_order_save.php', { action: 'prefix', order: JSON.stringify(order), dir: currentDir }, function(res){
      if (res.success) {
        $('#save-status').text('Files renamed. Reloading...');
        setTimeout(function(){ location.reload(); }, 700);
      } else {
        $('#save-status').text('Rename failed: ' + (res.message||'unknown'));
      }
    }, 'json').fail(function(){ $('#save-status').text('Rename failed: server error'); });
  });

});
</script>
