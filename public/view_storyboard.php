<?php
// view_storyboard.php - Individual storyboard view with frames
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Use statements for the UI modules
use App\UI\Modules\ModuleRegistry;

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
            $updateStmt = $pdo->prepare("
                UPDATE storyboard_frames 
                SET filename = ?, is_copied = 1, original_filename = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$destRelPath, $frame['source_filename'], $frame['id']]);
        }
    }
}

// --- MODULE CONFIGURATION ---

$registry = ModuleRegistry::getInstance();

$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'show_for_entities' => null,
]);

$allEntityTypes = [
    'characters', 'character_expressions', 'character_anima_poses', 'character_poses', 'animas', 'locations', 'backgrounds',
    'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns',
    'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites'
];

foreach ($allEntityTypes as $entityType) {
    $gearMenu->addStandardActions($entityType, [
        'overrides' => [
            'delete' => [
                'label' => 'Delete Original Frame',
                'condition' => 'frameId > 0'
            ]
        ]
    ]);
}

$imageEditor = $registry->create('image_editor', [
    'modes' => ['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true,
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' => ['grayscale', 'vintage', 'sepia', 'blur', 'sharpen'],
]);

$pageTitle = "Storyboard: " . htmlspecialchars($storyboard['name']);
ob_start();

echo '<link rel="stylesheet" href="/css/toast.css">';
echo '<script src="/js/toast.js"></script>';
echo '<script src="/js/gear_menu_globals.js"></script>';
echo $gearMenu->render();
echo $imageEditor->render();
require __DIR__ . '/modal_frame_details.php';
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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css" />

<style>
/* ── FORGE DESIGN TOKENS ── */
:root {
    --forge-bg:          #080b10;
    --forge-surface:     #0e1319;
    --forge-card:        #111820;
    --forge-card-hover:  #141e28;
    --forge-border:      #1c2535;
    --forge-border-glow: #2a3a52;
    --forge-text:        #c8d4e8;
    --forge-text-dim:    #5a6a80;
    --forge-text-bright: #e8f0ff;
    --forge-amber:       #f5a623;
    --forge-amber-dim:   rgba(245,166,35,0.08);
    --forge-amber-mid:   rgba(245,166,35,0.15);
    --forge-amber-glow:  rgba(245,166,35,0.4);
    --forge-red:         #f05060;
    --forge-red-dim:     rgba(240,80,96,0.1);
    --mono: 'Space Mono', 'Fira Mono', monospace;
    --sans: 'Syne', system-ui, sans-serif;
    --forge-radius: 6px;
}
[data-theme="light"], html[data-theme="light"] {
    --forge-bg:          #f6f8fa;
    --forge-surface:     #e1e4e8;
    --forge-card:        #ffffff;
    --forge-card-hover:  #f3f4f6;
    --forge-border:      #d1d5db;
    --forge-border-glow: #9ca3af;
    --forge-text:        #111827;
    --forge-text-dim:    #4b5563;
    --forge-text-bright: #000000;
    --forge-amber:       #d97706;
    --forge-amber-dim:   rgba(217,119,6,0.1);
    --forge-amber-mid:   rgba(217,119,6,0.2);
    --forge-amber-glow:  rgba(217,119,6,0.4);
    --forge-red:         #dc2626;
    --forge-red-dim:     rgba(220,38,38,0.1);
}

/* ── PAGE ── */
.storyboard-wrap {
    padding: 10px;
    font-family: var(--sans);
    color: var(--forge-text);
}

/* ── FORGE HEADER BAR ── */
.forge-header-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.forge-logo {
    font-family: var(--mono);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--forge-amber);
    letter-spacing: 2px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 1;
    min-width: 0;
    overflow: hidden;
}
.forge-logo-icon {
    width: 26px; height: 26px;
    background: var(--forge-amber-mid);
    border: 1px solid var(--forge-amber-glow);
    border-radius: var(--forge-radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}
.forge-logo span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.forge-back-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.72rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
    flex-shrink: 0;
    margin-left: auto;
}
.forge-back-btn:hover {
    border-color: var(--forge-amber);
    color: var(--forge-amber);
    background: var(--forge-amber-dim);
}

/* ── TOOLBAR STRIP ── */
.forge-toolbar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.forge-tool-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.72rem;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.forge-tool-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); background: var(--forge-amber-dim); }
.forge-tool-btn.primary {
    background: var(--forge-amber);
    color: #000;
    border-color: var(--forge-amber);
    font-weight: 700;
}
.forge-tool-btn.primary:hover { filter: brightness(1.1); color: #000; background: var(--forge-amber); }
.forge-save-status {
    font-family: var(--mono);
    font-size: 0.68rem;
    color: var(--forge-text-dim);
    margin-left: auto;
}

/* ── DESCRIPTION ── */
.sb-description {
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    font-family: var(--mono);
    font-size: 0.75rem;
    color: var(--forge-text-dim);
    line-height: 1.5;
}

/* ── GRID ── */
.storyboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 8px;
    align-items: start;
}

/* ── FRAME CARD ── */
.frame-card {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    padding: 5px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    user-select: none;
    touch-action: manipulation;
    position: relative;
    transition: border-color 0.2s, background 0.2s;
}
.frame-card:hover {
    border-color: var(--forge-border-glow);
    background: var(--forge-card-hover);
}

/* thumbnail */
.frame-thumb {
    width: 100%;
    height: 90px;
    object-fit: cover;
    border-radius: calc(var(--forge-radius) - 1px);
    cursor: pointer;
    display: block;
}

/* meta row */
.frame-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 4px;
    font-family: var(--mono);
    font-size: 0.62rem;
    color: var(--forge-text-dim);
}
.frame-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.frame-actions { display: flex; gap: 3px; align-items: center; }

/* handle */
.handle {
    cursor: grab;
    padding: 3px 5px;
    border-radius: 3px;
    border: 1px solid var(--forge-border);
    color: var(--forge-text-dim);
    font-size: 0.85rem;
    line-height: 1;
    background: transparent;
    transition: all 0.15s;
}
.handle:hover { border-color: var(--forge-border-glow); color: var(--forge-text); }
.handle:active { cursor: grabbing; }

/* delete button */
.delete-single-btn {
    padding: 3px 6px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: 3px;
    color: var(--forge-text-dim);
    font-size: 0.65rem;
    cursor: pointer;
    line-height: 1;
    transition: all 0.15s;
}
.delete-single-btn:hover { border-color: var(--forge-red); color: var(--forge-red); background: var(--forge-red-dim); }

/* sortable states */
.sortable-ghost { opacity: 0.35; border: 1px dashed var(--forge-amber); }
.sortable-drag  { opacity: 0.9; box-shadow: 0 6px 20px rgba(0,0,0,0.4); }

/* responsive */
@media (min-width: 600px) {
    .storyboard-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
    .frame-thumb { height: 115px; }
}

/* FIX: Force menu to absolute so it tracks with scroll */
.sb-menu { position: absolute !important; }
</style>

<div class="view-container storyboard-wrap">

    <!-- FORGE HEADER -->
    <div class="forge-header-bar">
        <div class="forge-logo">
            <div class="forge-logo-icon">⬛</div>
            <span><?php echo htmlspecialchars($storyboard['name']); ?></span>
        </div>
        <a href="view_storyboards.php" class="forge-back-btn">
            <i class="fa fa-arrow-left"></i> Storyboards
        </a>
    </div>

    <?php if ($storyboard['description']): ?>
    <div class="sb-description">
        <?php echo nl2br(htmlspecialchars($storyboard['description'])); ?>
    </div>
    <?php endif; ?>

    <!-- TOOLBAR -->
    <div class="forge-toolbar">
        <button id="btn-save" class="forge-tool-btn primary">
            <i class="fa fa-save"></i> Save Order
        </button>
        <button id="btn-auto-prefix" class="forge-tool-btn" title="Rename files with numeric prefixes">
            <i class="fa fa-sort-numeric-asc"></i> Auto-prefix
        </button>
        <button id="btn-export" class="forge-tool-btn" title="Export as ZIP">
            <i class="fa fa-download"></i> Export ZIP
        </button>
        <div class="forge-save-status" id="save-status">Ready</div>
    </div>

    <div id="storyboard" class="storyboard-grid pswp-gallery">
        <!-- Populated by JavaScript -->
    </div>

</div>

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
    $.get('storyboards_api.php', { action: 'get_frames', storyboard_id: storyboardId })
      .done(function(res) {
        if (res.success) {
          frames = res.data;
          renderFrames();
        } else {
          $('#save-status').text('Failed to load frames').css('color', 'var(--forge-red)');
        }
      })
      .fail(function() {
        $('#save-status').text('Server error').css('color', 'var(--forge-red)');
      });
  }

  function renderFrames() {
    const $grid = $('#storyboard');
    $grid.empty();

    frames.forEach(frame => {
      const safeName = escapeHtml(frame.name);

      const $card = $(`
        <div class="frame-card"
             data-id="${frame.id}"
             data-entity="${frame.entity_type || ''}"
             data-entity-id="${frame.entity_id || 0}"
             data-frame-id="${frame.frame_id || 0}"
             data-rating="${frame.rating || 0}">
          <a href="${frame.filename}"
             data-pswp-width="1024"
             data-pswp-height="1024"
             target="_blank"
             rel="noreferrer">
            <img src="${frame.filename}" alt="${safeName}" class="frame-thumb" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;" />
          </a>
          <div class="frame-meta">
            <div class="frame-name" title="${safeName}">${safeName}</div>
            <div class="frame-actions">
              <div class="handle" title="Drag to reorder">☰</div>
              <button class="delete-single-btn btn-delete" data-id="${frame.id}" title="Delete from storyboard">✕</button>
            </div>
          </div>
        </div>
      `);

      $grid.append($card);
    });

    initPhotoSwipe();

    if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
        window.GearMenu.attach($grid[0]);
    }
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
      secondaryZoomLevel: 1
    });
    window.lightbox.init();
  }

  function escapeHtml(text) {
    if (!text) return '';
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

    if (!frame || !confirm('Delete "' + frame.name + '" from this storyboard?')) return;

    $.post('storyboards_api.php', { action: 'delete_frame', frame_id: frameId })
      .done(function(res){
        if (res.success) {
          $card.remove();
          frames = frames.filter(f => f.id != frameId);
          $('#save-status').text('Deleted: ' + frame.name);
        } else {
          alert('Delete failed: ' + (res.message || 'unknown'));
        }
      })
      .fail(function(){ alert('Server request failed'); });
  });

  // Save order
  $('#btn-save').on('click', function(){
    const order = [];
    $('#storyboard .frame-card').each(function(){ order.push($(this).data('id')); });
    $('#save-status').text('Saving...');
    $.post('storyboards_api.php', {
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
      .fail(function(){ $('#save-status').text('Save failed: server error'); });
  });

  // Auto-prefix functionality
  $('#btn-auto-prefix').on('click', function(){
    if (!confirm('Auto-prefix will rename files on disk with numeric prefixes (001_filename). Proceed?')) return;
    const order = [];
    $('#storyboard .frame-card').each(function(){ order.push($(this).data('id')); });
    $('#save-status').text('Renaming files...');
    $.post('storyboards_api.php', {
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
      .fail(function(){ $('#save-status').text('Rename failed: server error'); });
  });

  // Export ZIP
  $('#btn-export').on('click', function(){
    if (!confirm('Export this storyboard as a ZIP file with all frames in order?')) return;
    $('#save-status').text('Creating ZIP...');
    $.post('storyboards_api.php', { action: 'export_zip', storyboard_id: storyboardId })
      .done(function(res){
        if (res.success && res.download_url) {
          $('#save-status').text('Downloading...');
          window.location.href = res.download_url;
          setTimeout(function(){ $('#save-status').text('Export complete'); }, 1000);
        } else {
          $('#save-status').text('Export failed: ' + (res.message||'unknown'));
        }
      })
      .fail(function(){ $('#save-status').text('Export failed: server error'); });
  });

  loadFrames();
});
</script>

<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
?>