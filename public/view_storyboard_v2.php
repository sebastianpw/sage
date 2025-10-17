<?php
// view_storyboard_v2.php - Individual storyboard view with frames
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$storyboardId = (int)($_GET['id'] ?? 0);
if (!$storyboardId) {
    die('Invalid storyboard ID');
}

// Get storyboard info
$stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
$stmt->execute([$storyboardId]);
$storyboard = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$storyboard) {
    die('Storyboard not found');
}

// Copy frames to storyboard directory if needed
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$storyboardDir = $docRoot . $storyboard['directory'];

if (!is_dir($storyboardDir)) {
    mkdir($storyboardDir, 0777, true);
}

// Get frames that need copying
$stmt = $pdo->prepare("
    SELECT sf.*, f.filename as source_filename 
    FROM storyboard_frames sf
    LEFT JOIN frames f ON sf.frame_id = f.id
    WHERE sf.storyboard_id = ? AND sf.is_copied = 0 AND sf.frame_id IS NOT NULL
");
$stmt->execute([$storyboardId]);
$framesToCopy = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($framesToCopy as $frame) {
    $sourceFile = $docRoot . '/' . ltrim($frame['source_filename'], '/');
    
    if (file_exists($sourceFile)) {
        $ext = pathinfo($sourceFile, PATHINFO_EXTENSION);
        $newFilename = 'frame' . str_pad($frame['id'], 7, '0', STR_PAD_LEFT) . '.' . $ext;
        $destFile = $storyboardDir . '/' . $newFilename;
        $destRelPath = $storyboard['directory'] . '/' . $newFilename;
        
        if (copy($sourceFile, $destFile)) {
            // Update database
            $updateStmt = $pdo->prepare("
                UPDATE storyboard_frames 
                SET filename = ?, is_copied = 1, original_filename = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$destRelPath, $frame['source_filename'], $frame['id']]);
        }
    }
}

$pageTitle = "Storyboard: " . htmlspecialchars($storyboard['name']);
ob_start();
?>

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
.storyboard-wrap { padding: 12px; }
.storyboard-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(110px,1fr)); gap:8px; align-items:start; }
.frame-card { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,0.08); padding:6px; display:flex; flex-direction:column; gap:6px; user-select:none; touch-action:manipulation; }
.frame-thumb { width:100%; height:90px; object-fit:cover; border-radius:6px; cursor:pointer; }
.frame-meta { display:flex; justify-content:space-between; align-items:center; gap:6px; font-size:13px; }
.handle { cursor:grab; padding:6px; border-radius:6px; background: rgba(0,0,0,0.03); }
.btn { font-size:12px; padding:6px 8px; border-radius:6px; border:none; background:#f3f3f3; cursor:pointer; }
.btn:hover { background:#e5e5e5; }
.btn-primary { background:#4CAF50; color:white; }
.btn-primary:hover { background:#45a049; }
.toolbar { display:flex; gap:8px; align-items:center; margin-bottom:10px; flex-wrap:wrap; }
.save-status { font-size:13px; color:#666; }
@media(min-width:800px) { .storyboard-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); } .frame-thumb { height:120px; } }
</style>

<div class="view-container storyboard-wrap">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
    <h3><?php echo htmlspecialchars($storyboard['name']); ?></h3>
    <a href="view_storyboards_v2.php" class="btn">
      <i class="fa fa-arrow-left"></i> Back to Overview
    </a>
  </div>

  <?php if ($storyboard['description']): ?>
  <div style="margin-bottom:12px; font-size:13px; color:#666; padding:8px; background:#f9f9f9; border-radius:6px;">
    <?php echo nl2br(htmlspecialchars($storyboard['description'])); ?>
  </div>
  <?php endif; ?>

  <div class="toolbar">
    <button id="btn-save" class="btn btn-primary">Save Order</button>
    <button id="btn-auto-prefix" class="btn" title="Rename files with numeric prefixes">Auto-prefix filenames</button>
    <button id="btn-export" class="btn" title="Export as ZIP">
      <i class="fa fa-download"></i> Export ZIP
    </button>
    <div class="save-status" id="save-status">Ready</div>
  </div>

  <div id="storyboard" class="storyboard-grid pswp-gallery">
    <!-- Populated by JavaScript -->
  </div>
</div>

<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
?>

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

<script>
$(function(){
  const storyboardId = <?php echo $storyboardId; ?>;
  let frames = [];

  function loadFrames() {
    $.get('storyboards_v2_api.php', { action: 'get_frames', storyboard_id: storyboardId })
      .done(function(res) {
        if (res.success) {
          frames = res.data;
          renderFrames();
        } else {
          $('#save-status').text('Failed to load frames').css('color', '#f44336');
        }
      })
      .fail(function() {
        $('#save-status').text('Server error').css('color', '#f44336');
      });
  }

  function renderFrames() {
    const $grid = $('#storyboard');
    $grid.empty();

    frames.forEach(frame => {
      const safeName = escapeHtml(frame.name);
      const safeFilename = escapeHtml(frame.filename);
      
      const $card = $(`
        <div class="frame-card" data-id="${frame.id}">
          <a href="${frame.filename}" 
             data-pswp-width="768" 
             data-pswp-height="768"
             target="_blank"
             rel="noreferrer">
            <img src="${frame.filename}" alt="${safeName}" class="frame-thumb" loading="lazy" />
          </a>
          <div class="frame-meta">
            <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${safeName}</div>
            <div style="display:flex; gap:6px; align-items:center;">
              <div class="handle" title="drag to reorder">☰</div>
              <button class="btn btn-delete" data-id="${frame.id}">🗑</button>
            </div>
          </div>
        </div>
      `);
      
      $grid.append($card);
    });

    initPhotoSwipe();
  }

  function initPhotoSwipe() {
    if (window.lightbox) {
      window.lightbox.destroy();
    }
    
    window.lightbox = new PhotoSwipeLightbox({
      gallery: '.pswp-gallery',
      children: 'a',
      pswpModule: PhotoSwipe,
      initialZoomLevel: 'fit',
      secondaryZoomLevel: 1,
      paddingFn: (viewportSize) => { 
          return {}
      }

    });
    
    window.lightbox.init();
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Initialize Sortable
  const el = document.getElementById('storyboard');
  const sortable = Sortable.create(el, {
    animation: 150,
    handle: '.handle',
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    onEnd: function () { 
      $('#save-status').text('Order changed — click Save Order'); 
    }
  });

  // Delete frame
  $(document).on('click', '.btn-delete', function(){
    const $card = $(this).closest('.frame-card');
    const frameId = $card.data('id');
    const frame = frames.find(f => f.id == frameId);
    
    if (!frame || !confirm('Delete "' + frame.name + '"?')) return;
    
    $.post('storyboards_v2_api.php', { 
      action: 'delete_frame', 
      frame_id: frameId 
    })
      .done(function(res){
        if (res.success) {
          $card.remove();
          frames = frames.filter(f => f.id != frameId);
          $('#save-status').text('Deleted: ' + frame.name);
        } else {
          alert('Delete failed: ' + (res.message || 'unknown'));
        }
      })
      .fail(function(){ 
        alert('Server request failed'); 
      });
  });

  // Save order
  $('#btn-save').on('click', function(){
    const order = [];
    $('#storyboard .frame-card').each(function(){ 
      order.push($(this).data('id')); 
    });
    
    $('#save-status').text('Saving...');
    
    $.post('storyboards_v2_api.php', { 
      action: 'save_order', 
      storyboard_id: storyboardId,
      order: JSON.stringify(order) 
    })
      .done(function(res){
        if (res.success) {
          $('#save-status').text('Order saved (' + new Date().toLocaleTimeString() + ')');
        } else {
          $('#save-status').text('Save failed: ' + (res.message||'unknown'));
        }
      })
      .fail(function(){ 
        $('#save-status').text('Save failed: server error'); 
      });
  });

  // Auto-prefix functionality
  $('#btn-auto-prefix').on('click', function(){
    if (!confirm('Auto-prefix will rename files on disk with numeric prefixes (001_filename). Proceed?')) {
      return;
    }
    
    const order = [];
    $('#storyboard .frame-card').each(function(){ 
      order.push($(this).data('id')); 
    });
    
    $('#save-status').text('Renaming files...');
    
    $.post('storyboards_v2_api.php', { 
      action: 'rename_in_order', 
      storyboard_id: storyboardId,
      order: JSON.stringify(order) 
    })
      .done(function(res){
        if (res.success) {
          $('#save-status').text('Files renamed. Reloading...');
          setTimeout(function(){ location.reload(); }, 700);
        } else {
          $('#save-status').text('Rename failed: ' + (res.message||'unknown'));
        }
      })
      .fail(function(){ 
        $('#save-status').text('Rename failed: server error'); 
      });
  });

  // Export ZIP
  $('#btn-export').on('click', function(){
    if (!confirm('Export this storyboard as a ZIP file with all frames in order?')) {
      return;
    }
    
    $('#save-status').text('Creating ZIP...');
    
    $.post('storyboards_v2_api.php', { 
      action: 'export_zip', 
      storyboard_id: storyboardId 
    })
      .done(function(res){
        if (res.success && res.download_url) {
          $('#save-status').text('Downloading...');
          window.location.href = res.download_url;
          setTimeout(function(){ 
            $('#save-status').text('Export complete'); 
          }, 1000);
        } else {
          $('#save-status').text('Export failed: ' + (res.message||'unknown'));
        }
      })
      .fail(function(){ 
        $('#save-status').text('Export failed: server error'); 
      });
  });

  loadFrames();
});
</script>
