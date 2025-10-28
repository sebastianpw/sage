<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Tools\ImageEditor;
use App\Core\FramesManager;

$spw = \App\Core\SpwBase::getInstance();
$fm = FramesManager::getInstance();

$entityType = $_GET['entity_type'] ?? null;
$entityId = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : null;
$frameId = isset($_GET['frame_id']) ? intval($_GET['frame_id']) : null;

$frame = null;
$imageInfo = null;
$error = null;

if ($frameId) {
    try {
        $frame = $fm->loadFrameRow($frameId, null, null);
        if (!$frame) {
            $error = "Frame not found";
        }
    } catch (Exception $e) {
        $error = "Error loading frame: " . $e->getMessage();
    }
} elseif ($entityType && $entityId) {
    try {
        $frame = $fm->loadFrameRow(null, $entityType, $entityId);
        if (!$frame) {
            $error = "No frame found for this entity";
        }
    } catch (Exception $e) {
        $error = "Error loading frame: " . $e->getMessage();
    }
}

if ($frame && !$error) {
    $imageEditor = new ImageEditor();
    $imageInfo = $imageEditor->getImageInfo($frame['filename']);
    if (!$imageInfo) {
        $error = $imageEditor->getLastError();
    }
}

$pageTitle = "Image Editor";
ob_start();
?>

<style>
* { box-sizing: border-box; }
body { margin: 0; padding: 0; background: #0a0a0a; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.editor-container { max-width: 100%; padding: 20px; background: #1a1a1a; min-height: 100vh; }
.editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background: #2a2a2a; border-radius: 8px; }
.editor-main { display: grid; grid-template-columns: 1fr 350px; gap: 20px; }
.canvas-area { background: #2a2a2a; border-radius: 8px; padding: 20px; min-height: 600px; display: flex; align-items: center; justify-content: center; overflow: auto; }
.image-canvas { max-width: 100%; max-height: 80vh; }
.tools-panel { background: #2a2a2a; border-radius: 8px; padding: 20px; overflow-y: auto; max-height: 80vh; }
.tool-section { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #3a3a3a; }
.tool-section:last-child { border-bottom: none; }
.tool-section h3 { margin: 0 0 15px 0; color: #0cf; font-size: 16px; }
.tool-group { margin-bottom: 15px; }
.tool-group label { display: block; margin-bottom: 6px; color: #aaa; font-size: 13px; }
.tool-group input[type="number"], .tool-group input[type="range"], .tool-group select { width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #444; border-radius: 4px; color: #fff; }
.tool-group input[type="range"] { padding: 0; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
.btn-primary { background: #0cf; color: #000; }
.btn-primary:hover { background: #0af; }
.btn-secondary { background: #444; color: #fff; }
.btn-secondary:hover { background: #555; }
.btn-danger { background: #c33; color: #fff; }
.btn-danger:hover { background: #d44; }
.btn-group { display: flex; gap: 10px; margin-top: 10px; }
.layer-list { list-style: none; padding: 0; margin: 0; }
.layer-item { background: #1a1a1a; padding: 10px; margin-bottom: 8px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
.layer-controls { display: flex; gap: 5px; }
.layer-controls button { padding: 5px 10px; font-size: 12px; }
.coords-display { background: #1a1a1a; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #0cf; }
.error-message { background: #c33; color: #fff; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
@media (max-width: 768px) { .editor-main { grid-template-columns: 1fr; } .tools-panel { max-height: none; } }
</style>

<div class="editor-container">
    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($frame && $imageInfo): ?>
        <div class="editor-header">
            <div>
                <h1 style="margin: 0; color: #0cf;">Image Editor</h1>
                <p style="margin: 5px 0 0 0; color: #888;">
                    Frame #<?= $frame['id'] ?> | <?= $imageInfo['width'] ?>x<?= $imageInfo['height'] ?>px | <?= round($imageInfo['size'] / 1024, 2) ?>KB
                </p>
            </div>
            <div class="btn-group">
                <button class="btn btn-secondary" onclick="history.back()">← Back</button>
                <button class="btn btn-primary" id="saveEditBtn">Save Changes</button>
            </div>
        </div>

        <div class="editor-main">
            <div class="canvas-area">
                <img id="mainCanvas" class="image-canvas" src="/<?= htmlspecialchars($frame['filename']) ?>" alt="Edit canvas" data-frame-id="<?= $frame['id'] ?>" data-entity-type="<?= htmlspecialchars($entityType ?? '') ?>" data-entity-id="<?= htmlspecialchars($entityId ?? '') ?>">
            </div>

            <div class="tools-panel">
                <div class="tool-section">
                    <h3>✂️ Crop</h3>
                    <div class="tool-group">
                        <label>Aspect Ratio</label>
                        <select id="aspectRatio">
                            <option value="free">Free</option>
                            <option value="1">1:1 (Square)</option>
                            <option value="1.777">16:9</option>
                            <option value="0.75">3:4</option>
                            <option value="1.333">4:3</option>
                        </select>
                    </div>
                    <div class="coords-display" id="coordsDisplay">X: 0, Y: 0<br>W: 0, H: 0</div>
                    <div class="btn-group">
                        <button class="btn btn-primary" id="applyCropBtn">Apply Crop</button>
                        <button class="btn btn-secondary" id="resetCropBtn">Reset</button>
                    </div>
                </div>

                <div class="tool-section">
                    <h3>🔄 Transform</h3>
                    <div class="tool-group">
                        <label>Rotate (degrees)</label>
                        <input type="number" id="rotateAngle" value="0" min="-360" max="360">
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-primary" id="applyRotateBtn">Apply Rotation</button>
                    </div>
                </div>

                <div class="tool-section">
                    <h3>📐 Resize</h3>
                    <div class="tool-group">
                        <label>Width (px)</label>
                        <input type="number" id="resizeWidth" value="<?= $imageInfo['width'] ?>">
                    </div>
                    <div class="tool-group">
                        <label>Height (px)</label>
                        <input type="number" id="resizeHeight" value="<?= $imageInfo['height'] ?>">
                    </div>
                    <div class="tool-group">
                        <label><input type="checkbox" id="maintainAspect" checked> Maintain aspect ratio</label>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-primary" id="applyResizeBtn">Apply Resize</button>
                    </div>
                </div>

                <div class="tool-section">
                    <h3>🎨 Filters</h3>
                    <div class="tool-group">
                        <label>Brightness</label>
                        <input type="range" id="brightness" min="-100" max="100" value="0">
                    </div>
                    <div class="tool-group">
                        <label>Contrast</label>
                        <input type="range" id="contrast" min="-100" max="100" value="0">
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-primary" id="applyFiltersBtn">Apply Filters</button>
                        <button class="btn btn-secondary" id="grayscaleBtn">Grayscale</button>
                        <button class="btn btn-secondary" id="blurBtn">Blur</button>
                    </div>
                </div>

                <div class="tool-section">
                    <h3>📚 Layers</h3>
                    <ul class="layer-list" id="layerList">
                        <li class="layer-item"><span>Base Layer</span></li>
                    </ul>
                    <div class="btn-group">
                        <button class="btn btn-secondary" id="addLayerBtn">+ Add Layer</button>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="error-message">No frame data available. Please provide entity_type + entity_id or frame_id.</div>
    <?php endif; ?>
</div>

<?php echo $spw->getJquery(); ?>
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />
    <script src="/vendor/cropper/cropper.min.js"></script>
<?php endif; ?>

<script>
(function() {
    let cropper = null;
    const $canvas = $('#mainCanvas');
    const frameId = $canvas.data('frame-id');
    const entityType = $canvas.data('entity-type');
    const entityId = $canvas.data('entity-id');
    
    function initCropper() {
        if (cropper) cropper.destroy();
        cropper = new Cropper($canvas[0], {
            viewMode: 1, dragMode: 'move', autoCropArea: 1, restore: false,
            guides: true, center: true, highlight: true, cropBoxMovable: true,
            cropBoxResizable: true, toggleDragModeOnDblclick: false,
            ready: updateCoordsDisplay, crop: updateCoordsDisplay
        });
    }
    
    function updateCoordsDisplay() {
        if (!cropper) return;
        const data = cropper.getData(true);
        $('#coordsDisplay').html(`X: ${data.x}, Y: ${data.y}<br>W: ${data.width}, H: ${data.height}`);
    }
    
    $('#aspectRatio').on('change', function() {
        const ratio = $(this).val();
        cropper.setAspectRatio(ratio === 'free' ? NaN : parseFloat(ratio));
    });
    
    $('#applyCropBtn').on('click', async function() {
        if (!cropper) return;
        const data = cropper.getData(true);
        const payload = {
            entity: entityType, entity_id: entityId, frame_id: frameId,
            coords: { x: Math.round(data.x), y: Math.round(data.y), width: Math.round(data.width), height: Math.round(data.height) },
            mode: 'crop', tool: 'cropper', note: 'Cropped via image editor', apply_immediately: 1
        };
        try {
            const resp = await fetch('/save_image_edit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await resp.json();
            if (result.success) {
                Toast.show('Crop applied successfully', 'success');
                $canvas.attr('src', '/' + result.derived_filename + '?v=' + Date.now());
                initCropper();
            } else {
                Toast.show('Crop failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            Toast.show('Crop failed', 'error');
            console.error(err);
        }
    });
    
    $('#resetCropBtn').on('click', function() { if (cropper) { cropper.reset(); updateCoordsDisplay(); } });
    
    $('#applyRotateBtn').on('click', async function() {
        const angle = parseFloat($('#rotateAngle').val());
        const payload = { action: 'rotate', entity: entityType, entity_id: entityId, frame_id: frameId, angle: angle };
        try {
            const resp = await fetch('/image_editor_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await resp.json();
            if (result.success) {
                Toast.show('Rotation applied', 'success');
                $canvas.attr('src', '/' + result.filename + '?v=' + Date.now());
                initCropper();
            } else {
                Toast.show('Rotation failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            Toast.show('Rotation failed', 'error');
            console.error(err);
        }
    });
    
    $('#applyResizeBtn').on('click', async function() {
        const width = parseInt($('#resizeWidth').val());
        const height = parseInt($('#resizeHeight').val());
        const maintain = $('#maintainAspect').is(':checked');
        const payload = { action: 'resize', entity: entityType, entity_id: entityId, frame_id: frameId, width: width, height: height, maintain_aspect: maintain };
        try {
            const resp = await fetch('/image_editor_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await resp.json();
            if (result.success) {
                Toast.show('Resize applied', 'success');
                $canvas.attr('src', '/' + result.filename + '?v=' + Date.now());
                initCropper();
            } else {
                Toast.show('Resize failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            Toast.show('Resize failed', 'error');
            console.error(err);
        }
    });
    
    $('#applyFiltersBtn').on('click', async function() {
        const brightness = parseInt($('#brightness').val());
        const contrast = parseInt($('#contrast').val());
        const payload = { action: 'filter', entity: entityType, entity_id: entityId, frame_id: frameId, filter_type: 'composite', params: { brightness: brightness, contrast: contrast } };
        try {
            const resp = await fetch('/image_editor_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await resp.json();
            if (result.success) {
                Toast.show('Filters applied', 'success');
                $canvas.attr('src', '/' + result.filename + '?v=' + Date.now());
                initCropper();
            } else {
                Toast.show('Filter failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            Toast.show('Filter failed', 'error');
            console.error(err);
        }
    });
    
    $('#grayscaleBtn').on('click', async function() {
        const payload = { action: 'filter', entity: entityType, entity_id: entityId, frame_id: frameId, filter_type: 'grayscale', params: {} };
        try {
            const resp = await fetch('/image_editor_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await resp.json();
            if (result.success) {
                Toast.show('Grayscale applied', 'success');
                $canvas.attr('src', '/' + result.filename + '?v=' + Date.now());
                initCropper();
            } else {
                Toast.show('Grayscale failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            Toast.show('Grayscale failed', 'error');
            console.error(err);
        }
    });
    
    $('#blurBtn').on('click', async function() {
        const payload = { action: 'filter', entity: entityType, entity_id: entityId, frame_id: frameId, filter_type: 'blur', params: {} };
        try {
            const resp = await fetch('/image_editor_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await resp.json();
            if (result.success) {
                Toast.show('Blur applied', 'success');
                $canvas.attr('src', '/' + result.filename + '?v=' + Date.now());
                initCropper();
            } else {
                Toast.show('Blur failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            Toast.show('Blur failed', 'error');
            console.error(err);
        }
    });
    
    $('#maintainAspect').on('change', function() {
        const maintain = $(this).is(':checked');
        if (maintain) {
            const width = parseInt($('#resizeWidth').val());
            const height = parseInt($('#resizeHeight').val());
            const aspectRatio = width / height;
            $('#resizeWidth').on('input', function() {
                const newWidth = parseInt($(this).val());
                $('#resizeHeight').val(Math.round(newWidth / aspectRatio));
            });
            $('#resizeHeight').on('input', function() {
                const newHeight = parseInt($(this).val());
                $('#resizeWidth').val(Math.round(newHeight * aspectRatio));
            });
        } else {
            $('#resizeWidth').off('input');
            $('#resizeHeight').off('input');
        }
    });
    
    $(document).ready(function() { initCropper(); });
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>
