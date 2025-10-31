<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Get parameters
$frameId = isset($_GET['frame_id']) ? (int)$_GET['frame_id'] : 0;
$entity = isset($_GET['entity']) ? preg_replace('/[^a-z_]/i', '', $_GET['entity']) : '';
$entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

if (!$frameId) {
    die("Frame ID required");
}

// Fetch frame data from the appropriate view
$frame = null;
$entityName = '';
$entityUrl = '';

// Try to find the frame in different entity views
$entityViews = [
    'animas' => ['view' => 'v_gallery_animas', 'name_col' => 'anima_name', 'id_col' => 'anima_id'],
    'artifacts' => ['view' => 'v_gallery_artifacts', 'name_col' => 'artifact_name', 'id_col' => 'artifact_id'],
    'backgrounds' => ['view' => 'v_gallery_backgrounds', 'name_col' => 'background_name', 'id_col' => 'background_id'],
    'characters' => ['view' => 'v_gallery_characters', 'name_col' => 'character_name', 'id_col' => 'character_id'],
    'composites' => ['view' => 'v_gallery_composites', 'name_col' => 'composite_name', 'id_col' => 'composite_id'],
    'generatives' => ['view' => 'v_gallery_generatives', 'name_col' => 'name', 'id_col' => 'generative_id'],
    'locations' => ['view' => 'v_gallery_locations', 'name_col' => 'location_name', 'id_col' => 'location_id'],
    'sketches' => ['view' => 'v_gallery_sketches', 'name_col' => 'name', 'id_col' => 'sketch_id'],
    'vehicles' => ['view' => 'v_gallery_vehicles', 'name_col' => 'vehicle_name', 'id_col' => 'vehicle_id'],
];

// If entity is specified, try that first
if ($entity && isset($entityViews[$entity])) {
    $view = $entityViews[$entity]['view'];
    $stmt = $mysqli->prepare("SELECT * FROM $view WHERE frame_id = ? LIMIT 1");
    $stmt->bind_param('i', $frameId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $frame = $row;
        $entityName = $row[$entityViews[$entity]['name_col']] ?? '';
        $entityUrl = "gallery_$entity.php";
    }
} else {
    // Search all views
    foreach ($entityViews as $ent => $config) {
        $view = $config['view'];
        $stmt = $mysqli->prepare("SELECT * FROM $view WHERE frame_id = ? LIMIT 1");
        $stmt->bind_param('i', $frameId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $frame = $row;
            $entity = $ent;
            $entityId = $row[$config['id_col']] ?? 0;
            $entityName = $row[$config['name_col']] ?? '';
            $entityUrl = "gallery_$entity.php";
            break;
        }
    }
}

if (!$frame) {
    die("Frame not found");
}

$filename = $frame['filename'] ?? '';
$prompt = $frame['prompt'] ?? '';
$style = $frame['style'] ?? '';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <title>Frame #<?= $frameId ?> - <?= htmlspecialchars($entityName) ?></title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    
    <link rel="stylesheet" href="css/gallery_gearicon_menu.css">
    <script src="js/gallery_gearicon_menu.js"></script>
    <script src="js/gear_menu_globals.js"></script>  
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #000;
            color: #ccc;
            font-family: sans-serif;
        }
        
        .frame-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .frame-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        
        .frame-header h1 {
            margin: 0;
            font-size: 24px;
            color: #fff;
        }
        
        .frame-links {
            display: flex;
            gap: 15px;
        }
        
        .frame-links a {
            color: #0af;
            text-decoration: none;
            padding: 6px 12px;
            border: 1px solid #0af;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .frame-links a:hover {
            background: #0af;
            color: #000;
        }
        
        .frame-content {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        
        .frame-image-wrapper {
            flex: 1;
            position: relative;
        }
        
        .frame-image {
            width: 100%;
            height: auto;
            display: block;
            border: 1px solid #333;
        }
        
        .gear-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 2em;
            cursor: pointer;
            color: #fff;
            text-shadow: 0 0 4px #000;
            z-index: 10;
        }
        
        .frame-meta {
            flex: 0 0 300px;
            background: #111;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #333;
        }
        
        .meta-item {
            margin-bottom: 15px;
        }
        
        .meta-item strong {
            color: #0af;
            display: block;
            margin-bottom: 5px;
        }
        
        .meta-item p {
            margin: 0;
            word-wrap: break-word;
        }



.sb-menu {
    color: #000;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    max-height: 400px;
    overflow-y: auto;
    font-size: 14px;
}

.sb-menu-item {
    padding: 10px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.sb-menu-item:hover {
    background: #f5f5f5;
}

.sb-menu-item:last-child {
    border-bottom: none;
}

.sb-menu-sep {
    height: 1px;
    background: #e0e0e0;
    margin: 4px 0;
}



    </style>
</head>
<body>
<?php require "floatool.php"; ?>
    <div class="frame-container">
        <div class="frame-header">
            <h1>Frame #<?= $frameId ?></h1>
            <div class="frame-links">
                <a href="<?= htmlspecialchars($entityUrl) ?>">📂 <?= ucfirst($entity) ?> Gallery</a>
                <a href="sql_crud_<?= $entity ?>.php?search=<?= $entityId ?>">✏️ Edit Entity</a>
                <a href="wall_of_images.php">🎇 Wall</a>
            </div>
        </div>
        
        <div class="frame-content">
            <div class="frame-image-wrapper" 
                 data-entity="<?= htmlspecialchars($entity) ?>" 
                 data-entity-id="<?= $entityId ?>" 
                 data-frame-id="<?= $frameId ?>">
                <img src="/<?= htmlspecialchars($filename) ?>" 
                     alt="<?= htmlspecialchars($entityName) ?>" 
                     class="frame-image">
                <span class="gear-icon">⚙</span>
            </div>
            
            <div class="frame-meta">
                <div class="meta-item">
                    <strong>Entity</strong>
                    <p><?= htmlspecialchars($entityName) ?> (<?= ucfirst($entity) ?>)</p>
                </div>
                
                <div class="meta-item">
                    <strong>Frame ID</strong>
                    <p><?= $frameId ?></p>
                </div>
                
                <div class="meta-item">
                    <strong>Entity ID</strong>
                    <p><?= $entityId ?></p>
                </div>
                
                <?php if ($prompt): ?>
                <div class="meta-item">
                    <strong>Prompt</strong>
                    <p><?= htmlspecialchars($prompt) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($style): ?>
                <div class="meta-item">
                    <strong>Style</strong>
                    <p><?= htmlspecialchars($style) ?></p>
                </div>
                <?php endif; ?>
                
                <div class="meta-item">
                    <strong>Filename</strong>
                    <p><?= htmlspecialchars($filename) ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Import the global gear menu functions from AbstractGallery
    <?php //include 'gear_menu_globals.js'; ?>
    
    $(document).ready(function(){
        // Attach gear menu to the image
        $('.gear-icon').gearmenu([
            {
                label: '⚡ Import to Generative',
                onClick: function() {
                    const $wrapper = $(this).closest('.frame-image-wrapper');
                    window.importGenerative($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                }
            },
            {
                label: '🎬 Add to Storyboard',
                onClick: function() {
                    const $wrapper = $(this).closest('.frame-image-wrapper');
                    const frameId = $wrapper.data('frame-id');
                    if (frameId) {
                        window.selectStoryboard(frameId, $(this));
                    }
                }
            },
            {
                label: '✏️ Edit Image',
                onClick: function() {
                    const $w = $(this).closest('.frame-image-wrapper');
                    if (typeof ImageEditorModal !== 'undefined') {
                        ImageEditorModal.open({
                            entity: $w.data('entity'),
                            entityId: $w.data('entity-id'),
                            frameId: $w.data('frame-id'),
                            src: $w.find('img').attr('src')
                        });
                    }
                }
            },
            {
                label: '🧩 Assign to Composite',
                onClick: function() {
                    const $wrapper = $(this).closest('.frame-image-wrapper');
                    window.assignToComposite($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                }
            },
            {
                label: '☠️ Import2CNetMap',
                onClick: function() {
                    const $wrapper = $(this).closest('.frame-image-wrapper');
                    window.importControlNetMap($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                }
            },
            {
                label: '🌌 Use Prompt Matrix',
                onClick: function() {
                    const $wrapper = $(this).closest('.frame-image-wrapper');
                    window.usePromptMatrix($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                }
            },
            {
                label: '🗑️ Delete Frame',
                onClick: function() {
                    const $wrapper = $(this).closest('.frame-image-wrapper');
                    if (confirm('Delete this frame?')) {
                        window.deleteFrame($wrapper.data('entity'), $wrapper.data('entity-id'), $wrapper.data('frame-id'));
                        // Redirect after deletion
                        setTimeout(() => {
                            window.location.href = '<?= htmlspecialchars($entityUrl) ?>';
                        }, 1000);
                    }
                }
            }
        ]);
    });
    </script>


<!-- Image editor scripts and logic -->

<script src="/js/image_editor_modal.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- CropperJS CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
<?php else: ?>
    <!-- CropperJS CSS via local copy -->
    <link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- CropperJS JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
<?php else: ?>
    <!-- CropperJS JS via local copy -->
    <script src="/vendor/cropper/cropper.min.js"></script>
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- jQuery-Cropper via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-cropper@1.0.1/dist/jquery-cropper.min.js"></script>
<?php else: ?>
    <!-- jQuery-Cropper via local copy -->
    <script src="/vendor/cropper/jquery-cropper.min.js"></script>
<?php endif; ?>





<script>
/* Image editor modal logic */
(function(){
    let cropper = null;
    let current = { entity: null, entityId: null, frameId: null, src: null, naturalWidth: null, naturalHeight: null };

    function openModal() { $('#imageEditModal').fadeIn(160); }
    function closeModal() {
        try { if (cropper) { $('#imageEditImg').cropper('destroy'); cropper = null; } } catch(e){}
        $('#imageEditImg').attr('src','');
        $('#imageEditPreviewImg').attr('src','');
        $('#imageEditModal').fadeOut(120);
    }

    window.openImageEditor = function(opts) {
        current.entity = opts.entity;
        current.entityId = opts.entityId;
        current.frameId = opts.frameId;
        current.src = opts.src;
        $('#imageEditTitle').text('Edit: ' + (opts.frameId ? ('frame #' + opts.frameId) : opts.src));
        $('#imageEditImg').attr('src', opts.src);
        $('#imageEditPreviewImg').attr('src', opts.src);
        $('#imageEditNote').val('');
        $('#imageEditApplyNow').prop('checked', false);
        $('#imageEditMode').val('crop');

        $('#imageEditImg').off('load').on('load', function(){
            try { $('#imageEditImg').cropper('destroy'); } catch(e){}
            $('#imageEditImg').cropper({
                viewMode: 1,
                autoCropArea: 0.6,
                movable: true,
                zoomable: true,
                scalable: false,
                cropBoxResizable: true,
                ready: function(){
                    current.naturalWidth = this.naturalWidth || this.naturalWidth;
                    current.naturalHeight = this.naturalHeight || this.naturalHeight;
                },
                crop: function(e) {
                    try {
                        const canvas = $('#imageEditImg').cropper('getCroppedCanvas', { width: 300, height: 300 });
                        if (canvas) { $('#imageEditPreviewImg').attr('src', canvas.toDataURL()); }
                    } catch(e){}
                }
            });
            cropper = $('#imageEditImg').data('cropper') || $('#imageEditImg');
        });

        openModal();
    };

    // wire modal buttons
    $(document).on('click', '#imageEditClose, #imageEditCancelBtn', function(e){
        e.preventDefault(); closeModal();
    });

    // Save (without apply) and Save & Apply
    $(document).on('click', '#imageEditSaveBtn, #imageEditSaveApplyBtn', async function(e){
        e.preventDefault();
        const doApply = $(this).attr('id') === 'imageEditSaveApplyBtn' || $('#imageEditApplyNow').is(':checked');
        if (!current.frameId || !current.entity) { Toast.show('Missing frame info', 'error'); return; }
        if (!$('#imageEditImg').attr('src')) { Toast.show('No image loaded', 'error'); return; }

        let cropData = null;
        try {
            const data = $('#imageEditImg').cropper('getData', true);
            cropData = {
                x: Math.round(data.x || 0),
                y: Math.round(data.y || 0),
                width: Math.round(data.width || 0),
                height: Math.round(data.height || 0),
                rotate: data.rotate || 0,
                scaleX: data.scaleX || 1,
                scaleY: data.scaleY || 1,
                imageNaturalWidth: $('#imageEditImg')[0].naturalWidth || current.naturalWidth || null,
                imageNaturalHeight: $('#imageEditImg')[0].naturalHeight || current.naturalHeight || null
            };
        } catch (err) {
            console.warn('cropper getData fail', err);
            cropData = { x:0, y:0, width:0, height:0, imageNaturalWidth: null, imageNaturalHeight: null };
        }

        const payload = {
            entity: current.entity,
            frame_id: current.frameId,
            entity_id: current.entityId,
            coords: cropData,
            mode: $('#imageEditMode').val() || 'crop',
            tool: 'jquery-cropper',
            note: $('#imageEditNote').val() || '',
            apply_immediately: doApply ? 1 : 0
        };

        try {
            const resp = await fetch('/save_image_edit.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const text = await resp.text();
            let json;
            try { json = JSON.parse(text); } catch(e) {
                Toast.show('Save failed: invalid response', 'error'); console.error('save_image_edit invalid json', text); return;
            }
            if (!json.success) {
                Toast.show('Save failed: ' + (json.message || 'unknown'), 'error');
                console.warn('save_image_edit error payload', json);
                return;
            }

            // success - update gallery image src in DOM for this frame
            const derived = json.derived_filename || json.filename || json.new_filename || null;
            if (derived) {
                const $wrapper = $(`.img-wrapper[data-frame-id="${current.frameId}"]`);
                const newSrc = (derived.charAt(0) === '/' ? derived : ('/' + derived)) + '?v=' + Date.now();
                $wrapper.find('img').each(function(){
                    $(this).attr('src', newSrc);
                });
            }

            Toast.show('Version created', 'info');
            closeModal();
            $(document).trigger('imageEdit.created', [json, current]);

        } catch (err) {
            console.error('save_image_edit fetch error', err);
            Toast.show('Save failed', 'error');
        }
    });

    // close modal when clicking overlay
    $(document).on('click', '#imageEditModal', function(e){
        if (e.target.id === 'imageEditModal') closeModal();
    });

})();
</script>

<?php echo $eruda; ?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
