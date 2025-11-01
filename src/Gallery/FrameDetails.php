<?php

namespace App\Gallery;

class FrameDetails
{
    private $mysqli;
    public $frameData = null;
    public $entityName = '';
    public $error = null;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Loads the frame and its associated entity data.
     *
     * @param int $frameId
     * @return bool True on success, false on failure.
     */
    public function load(int $frameId): bool
    {
        if ($frameId <= 0) {
            $this->error = "Invalid Frame ID.";
            return false;
        }

        // 1. Fetch the frame data from the `frames` table.
        $stmt = $this->mysqli->prepare("SELECT * FROM frames WHERE id = ?");
        $stmt->bind_param('i', $frameId);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->frameData = $result->fetch_assoc();
        $stmt->close();

        if (!$this->frameData) {
            $this->error = "Frame #{$frameId} not found in the frames table.";
            return false;
        }

        // 2. Fetch the entity name using entity_type and entity_id.
        $entityType = $this->frameData['entity_type'];
        $entityId = $this->frameData['entity_id'];

        if ($entityType && $entityId) {
            // We trust entity_type is a valid table name as per your instructions.
            // Using prepared statement for entity_id is crucial for security.
            $query = "SELECT name FROM `" . $this->mysqli->real_escape_string($entityType) . "` WHERE id = ?";
            $stmt = $this->mysqli->prepare($query);
            if ($stmt) {
                $stmt->bind_param('i', $entityId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $this->entityName = $row['name'];
                }
                $stmt->close();
            } else {
                // This might happen if the table name is invalid, good to log.
                error_log("Failed to prepare statement for entity_type: " . $entityType);
            }
        }
        
        return true;
    }

    /**
     * Renders the frame details content into an HTML string.
     *
     * @return string The HTML content.
     */
    public function renderContent(): string
    {
        if (!$this->frameData) {
            return "<p>Error: Frame data not loaded.</p>";
        }

        // Make variables available to the included file
        $frameId = $this->frameData['id'];
        $entity = $this->frameData['entity_type'] ?? 'unknown';
        $entityId = $this->frameData['entity_id'] ?? 0;
        $entityName = $this->entityName ?: 'N/A';
        $filename = $this->frameData['filename'] ?? '';
        $prompt = $this->frameData['prompt'] ?? '';
        $style = $this->frameData['style'] ?? '';
        $entityUrl = "gallery_{$entity}.php";

        // Use output buffering to capture the HTML from a separate file
        ob_start();
        // This is our reusable view partial
        ?>



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
            margin-right: 50px;
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




<div class="frame-container">
    <div class="frame-header">
        <h1>Frame #<?= htmlspecialchars($frameId) ?></h1>



        <div class="frame-links">
            <a href="<?= htmlspecialchars($entityUrl) ?>">📂 <?= ucfirst(htmlspecialchars($entity)) ?> Gallery</a>
            <a href="sql_crud_<?= htmlspecialchars($entity) ?>.php?search=<?= htmlspecialchars($entityId) ?>">✏️Edit Entity</a>
            <a href="wall_of_images.php">🎇 Wall</a>
        </div>
    </div>
    
    <div class="frame-content">
        <div class="frame-image-wrapper" 
             data-entity="<?= htmlspecialchars($entity) ?>" 
             data-entity-id="<?= htmlspecialchars($entityId) ?>" 
             data-frame-id="<?= htmlspecialchars($frameId) ?>">
            <img src="/<?= htmlspecialchars($filename) ?>" 
                 alt="<?= htmlspecialchars($entityName) ?>" 
                 class="frame-image">
            <span class="gear-icon">⚙</span>
        </div>
        
        <div class="frame-meta">
            <div class="meta-item">
                <strong>Entity</strong>
                <p><?= htmlspecialchars($entityName) ?> (<?= ucfirst(htmlspecialchars($entity)) ?>)</p>
            </div>
            
            <div class="meta-item">
                <strong>Frame ID</strong>
                <p><?= htmlspecialchars($frameId) ?></p>
            </div>
            
            <div class="meta-item">
                <strong>Entity ID</strong>
                <p><?= htmlspecialchars($entityId) ?></p>
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
// We wrap this in a function so it can be called on-demand after AJAX loads.
function initializeFrameDetailsScripts() {
    // Ensure gearmenu is loaded
    if (typeof $.fn.gearmenu === 'undefined') {
        console.error('gearmenu() not available.');
        return;
    }

    // Detach any existing handlers to prevent duplicates
    $('.gear-icon').off('click.gearmenu');









   
    
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










}
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





<?php
        $content = ob_get_clean(); 
        return $content;
    }
}

