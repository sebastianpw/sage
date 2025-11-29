<?php
// src/UI/Modules/ImageEditorModule.php
namespace App\UI\Modules;

/**
 * ImageEditorModule - Enhanced with proper save workflow and undo
 * Changes from temp images to permanent frames only when user explicitly saves
 */
class ImageEditorModule
{
    private array $config = [];
    private bool $includeCSS = true;
    private bool $includeJS = true;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_endpoint' => '/image_editor_api.php',
            'save_endpoint' => '/save_image_edit.php',
            'modes' => ['mask', 'crop'],
            'show_transform_tab' => true,
            'show_filters_tab' => true,
            'enable_rotate' => true,
            'enable_resize' => true,
            'enable_brightness' => true,
            'enable_contrast' => true,
            'preset_filters' => ['grayscale', 'blur', 'sharpen', 'vintage'],
        ], $config);
    }

    public function render(): string
    {
        $html = '';
        if ($this->includeCSS) {
            $html .= $this->renderCSS();
        }
        $html .= $this->renderHTML();
        if ($this->includeJS) {
            $html .= $this->renderJS();
        }
        return $html;
    }

    private function renderHTML(): string
    {
        $modes = $this->renderModeOptions();
        $transformTab = $this->config['show_transform_tab'] ? '<button class="ie-tab" data-tab="transform">Transform</button>' : '';
        $adjustTab = $this->config['show_filters_tab'] ? '<button class="ie-tab" data-tab="adjust">Adjust</button>' : '';
        $presetsTab = !empty($this->config['preset_filters']) ? '<button class="ie-tab" data-tab="presets">Presets</button>' : '';
        
        return <<<HTML
<div id="imageEditorModal" class="ie-modal" style="display: none;">
    <div class="ie-modal-overlay"></div>
    <div class="ie-modal-container">
        <!-- Loading Overlay -->
        <div id="ieLoadingOverlay" class="ie-loading-overlay" style="display: none;">
            <div class="ie-loading-spinner"></div>
            <p>Processing image, please wait...</p>
            <button id="ieCancelActionBtn" class="ie-btn ie-btn-secondary">Cancel</button>
        </div>

        <div class="ie-modal-header">
            <h2 class="ie-modal-title">Image Editor</h2>
            <button class="ie-close-btn" title="Close">×</button>
        </div>
        <div class="ie-modal-body">
            <div class="ie-editor-layout">
                <div class="ie-canvas-wrapper">
                    <img id="ieCanvas" class="ie-canvas-img" src="" alt="Edit canvas">
                </div>
                <div class="ie-tools-panel">
                    <div class="ie-tabs">
                        <button class="ie-tab active" data-tab="crop">Edit</button>
                        {$transformTab}
                        {$adjustTab}
                        {$presetsTab}
                    </div>
                    
                    <!-- Tab 1: Crop/Edit -->
                    <div class="ie-tab-content active" data-tab-content="crop">
                        <div class="ie-tool-group">
                            <label><strong>Mode</strong></label>
                            <select id="ieEditMode" class="ie-select">
                                {$modes}
                            </select>
                        </div>
                        <div class="ie-tool-group">
                            <label>Aspect Ratio</label>
                            <select id="ieAspectRatio" class="ie-select">
                                <option value="free">Free</option>
                                <option value="1">1:1</option>
                                <option value="1.777">16:9</option>
                                <option value="0.75">3:4</option>
                            </select>
                        </div>
                        <div class="ie-coords-display" id="ieCoordsDisplay">
                            X: 0, Y: 0<br>W: 0, H: 0
                        </div>
                        <button class="ie-btn ie-btn-primary" id="ieApplyCrop">Apply Edit</button>
                    </div>
                    
                    <!-- Tab 2: Transform -->
                    {$this->renderTransformTabContent()}
                    
                    <!-- Tab 3: Adjust (Sliders) -->
                    {$this->renderAdjustTabContent()}

                    <!-- Tab 4: Presets (Buttons) -->
                    {$this->renderPresetsTabContent()}
                </div>
            </div>
        </div>
        <div class="ie-modal-footer">
            <button class="ie-btn ie-btn-secondary" id="ieUndoBtn" disabled>Undo</button>
            <button class="ie-btn ie-btn-primary" id="ieSaveBtn" disabled>Save</button>
            <button class="ie-btn ie-btn-secondary" id="ieCloseBtn">Close</button>
        </div>
    </div>
</div>
HTML;
    }

    private function renderModeOptions(): string
    {
        $html = '';
        $labels = [
            'mask' => 'Mask (Green Overlay)',
            'crop' => 'Crop (Reduce Image)',
        ];
        
        foreach ($this->config['modes'] as $mode) {
            $label = $labels[$mode] ?? ucfirst($mode);
            $html .= "<option value=\"{$mode}\">{$label}</option>\n";
        }
        return $html;
    }

    private function renderTransformTabContent(): string
    {
        if (!$this->config['show_transform_tab']) return '';
        
        $rotateSection = '';
        if ($this->config['enable_rotate']) {
            $rotateSection = <<<HTML
<div class="ie-tool-group">
    <label>Rotate (degrees)</label>
    <input type="number" id="ieRotateAngle" class="ie-input" value="0" min="-360" max="360">
</div>
<button class="ie-btn ie-btn-primary" id="ieApplyRotate">Apply Rotation</button>
HTML;
        }
        
        $resizeSection = '';
        if ($this->config['enable_resize']) {
            $resizeSection = <<<HTML
<div class="ie-tool-group" style="margin-top: 20px;">
    <label>Width (px)</label>
    <input type="number" id="ieResizeWidth" class="ie-input" value="1024">
</div>
<div class="ie-tool-group">
    <label>Height (px)</label>
    <input type="number" id="ieResizeHeight" class="ie-input" value="1024">
</div>
<div class="ie-tool-group">
    <label><input type="checkbox" id="ieMaintainAspect" checked> Maintain aspect</label>
</div>
<button class="ie-btn ie-btn-primary" id="ieApplyResize">Apply Resize</button>
HTML;
        }
        
        return <<<HTML
<div class="ie-tab-content" data-tab-content="transform">
    {$rotateSection}
    {$resizeSection}
</div>
HTML;
    }

    private function renderAdjustTabContent(): string
{
    if (!$this->config['show_filters_tab']) return '';

    // Brightness
    $brightnessControl = $this->config['enable_brightness'] ? <<<HTML
<div class="ie-tool-group">
    <div class="range-row" aria-hidden="false">
        <div class="range-label">Brightness</div>
        <input type="range" id="ieBrightness" min="-100" max="100" value="0" aria-label="Brightness">
        <div class="range-value" id="ieBrightnessVal">0</div>
    </div>
</div>
HTML : '';

    // Contrast
    $contrastControl = $this->config['enable_contrast'] ? <<<HTML
<div class="ie-tool-group">
    <div class="range-row">
        <div class="range-label">Contrast</div>
        <input type="range" id="ieContrast" min="-100" max="100" value="0" aria-label="Contrast">
        <div class="range-value" id="ieContrastVal">0</div>
    </div>
</div>
HTML : '';

    // Blur
    $blurControl = <<<HTML
<div class="ie-tool-group">
    <div class="range-row">
        <div class="range-label">Blur</div>
        <input type="range" id="ieBlurRadius" min="0" max="10" step="0.1" value="0" aria-label="Blur radius">
        <div class="range-value" id="ieBlurRadiusVal">0.0</div>
    </div>
</div>
HTML;

    // Sharpen
    $sharpenControl = <<<HTML
<div class="ie-tool-group">
    <div class="range-row">
        <div class="range-label">Sharpen</div>
        <input type="range" id="ieSharpenAmount" min="0" max="300" step="1" value="0" aria-label="Sharpen amount">
        <div class="range-value" id="ieSharpenAmountVal">0</div>
    </div>
</div>
HTML;

    $adjustments = <<<HTML
{$brightnessControl}
{$contrastControl}
{$blurControl}
{$sharpenControl}
<div class="ie-tool-group">
    <button class="ie-btn ie-btn-primary" id="ieApplyFilters">Apply Adjustments</button>
</div>
HTML;

    return <<<HTML
<div class="ie-tab-content" data-tab-content="adjust">
    {$adjustments}
</div>
HTML;
}

    private function renderPresetsTabContent(): string
    {
        if (empty($this->config['preset_filters'])) return '';

        $buttons = '';
        foreach ($this->config['preset_filters'] as $filter) {
            $label = ($filter === 'grayscale') ? 'Grayscale (Noir)' : ucfirst($filter);
            $filterName = ($filter === 'grayscale') ? 'noir' : $filter;
            $buttons .= "<button class=\"ie-btn ie-btn-secondary\" data-filter=\"{$filterName}\">{$label}</button>\n";
        }
        
        $presets = <<<HTML
<div class="ie-filter-presets">
    {$buttons}
</div>
HTML;
        
        return <<<HTML
<div class="ie-tab-content" data-tab-content="presets">
    {$presets}
</div>
HTML;
    }

    private function renderCSS(): string
    {
        $cropperCSS = '';
        if (\App\Core\SpwBase::CDN_USAGE) {
            $cropperCSS = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />';
        } else {
            $cropperCSS = '<link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />';
        }
        
        return $cropperCSS . <<<'CSS'
<style id="ie-modal-styles">
.ie-modal{position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999999;display:flex;align-items:center;justify-content:center}
.ie-modal-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.85)}
.ie-modal-container{position:relative;background:#1a1a1a;border-radius:8px;width:95%;max-width:1400px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 10px 40px rgba(0,0,0,.5)}
.ie-modal-header{padding:12px 16px;border-bottom:1px solid #333;display:flex;justify-content:space-between;align-items:center}
.ie-modal-title{margin:0;color:#0cf;font-size:18px}
.ie-close-btn{background:none;border:none;color:#888;font-size:28px;cursor:pointer;padding:0;width:32px;height:32px;line-height:1}
.ie-close-btn:hover{color:#fff}
.ie-modal-body{flex:1;overflow:hidden;padding:14px}
.ie-editor-layout{display:grid;grid-template-columns:1fr 320px;gap:14px;height:100%}
.ie-canvas-wrapper{background:#2a2a2a;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;min-height:360px}
.ie-canvas-img{max-width:100%;max-height:60vh;display:block}
.ie-tools-panel{background:#2a2a2a;border-radius:8px;padding:12px;overflow-y:auto}
.ie-tabs{display:flex;gap:6px;margin-bottom:12px;border-bottom:1px solid #333}
.ie-tab{background:none;border:none;color:#888;padding:8px 12px;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s}
.ie-tab:hover{color:#0cf}
.ie-tab.active{color:#0cf;border-bottom-color:#0cf}
.ie-tab-content{display:none}
.ie-tab-content.active{display:block}

/* compact tool groups */
.ie-tool-group{margin-bottom:8px}
.ie-tool-group label{display:block;color:#aaa;margin-bottom:4px;font-size:12px}
.ie-select,.ie-input,.ie-tool-group input[type=number],.ie-tool-group input[type=range]{width:100%;padding:6px;background:#1a1a1a;border:1px solid #444;border-radius:4px;color:#fff;font-size:13px}

/* slider row: label + slider + value on one line */
.range-row{display:flex;align-items:center;gap:8px}
.range-row .range-label{min-width:0;flex:0 0 auto;font-size:12px;color:#aaa}
.range-row input[type=range]{flex:1;margin:0;height:28px;cursor:pointer}
.range-row .range-value{min-width:40px;text-align:right;font-size:12px;color:#0cf;background:transparent;padding-left:4px}

/* fallback for older markup where label sits above control */
.ie-tool-group input[type=range]{height:28px}

/* smaller presets grid spacing */
.ie-filter-presets{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
.ie-btn{padding:8px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px;transition:all .2s;width:100%;margin-bottom:8px}
.ie-btn-primary{background:#0cf;color:#000}
.ie-btn-primary:hover:not(:disabled){background:#0af}
.ie-btn-primary:disabled{background:#555;color:#888;cursor:not-allowed}
.ie-btn-secondary{background:#444;color:#fff}
.ie-btn-secondary:hover:not(:disabled){background:#555}
.ie-btn-secondary:disabled{background:#333;color:#666;cursor:not-allowed}

.ie-coords-display{background:#1a1a1a;padding:8px;border-radius:4px;font-family:monospace;font-size:12px;color:#0cf;margin-bottom:10px}
.ie-modal-footer{padding:12px 16px;border-top:1px solid #333;display:flex;gap:10px;justify-content:flex-end}
.ie-modal-footer .ie-btn{width:auto;margin:0;min-width:90px}
.ie-loading-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(26,26,26,.9);z-index:100;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;border-radius:8px}
.ie-loading-spinner{border:5px solid #444;border-top:5px solid #0cf;border-radius:50%;width:44px;height:44px;animation:ie-spin 1s linear infinite;margin-bottom:18px}
.ie-loading-overlay p{margin:0 0 14px;font-size:15px}
.ie-loading-overlay .ie-btn{width:auto;min-width:110px}
@keyframes ie-spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
@media(max-width:768px){.ie-editor-layout{grid-template-columns:1fr}.ie-modal-container{width:100%;max-width:100%;border-radius:0;max-height:100vh}}
</style>
CSS;
    }

private function renderJS(): string
{
    $apiEndpoint = $this->config['api_endpoint'];
    $saveEndpoint = $this->config['save_endpoint'];

    $cropperJS = \App\Core\SpwBase::CDN_USAGE
        ? '<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>'
        : '<script src="/vendor/cropper/cropper.min.js"></script>';

    // Use a nowdoc so PHP doesn't attempt to interpolate ${...} (JS template literals)
    return $cropperJS . <<<'JS'
<script id="ie-modal-script">
(function() {
    'use strict';

    let cropper = null;
    let currentData = {};
    let abortController = null;
    let editHistory = []; // Stack of temporary image states
    let operationHistory = []; // Track what operations were performed
    let hasUnsavedChanges = false;
    let currentTempFile = null; // Current temporary filename

    const TEMP_API_ENDPOINT = '/image_editor_api.php';
    const SAVE_FINAL_ENDPOINT = '/save_final_image_edit.php';

    function initCropper() {
        const canvas = document.getElementById('ieCanvas');
        if (!canvas) return;
        if (cropper) cropper.destroy();

        cropper = new Cropper(canvas, {
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            guides: true,
            center: true,
            highlight: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            crop: updateCoordsDisplay
        });
    }

    function updateCoordsDisplay() {
        if (!cropper) return;
        const data = cropper.getData(true);
        const display = document.getElementById('ieCoordsDisplay');
        if (display) {
            display.innerHTML = `X: ${Math.round(data.x)}, Y: ${Math.round(data.y)}<br>W: ${Math.round(data.width)}, H: ${Math.round(data.height)}`;
        }
    }

    function showLoadingOverlay() {
        const overlay = document.getElementById('ieLoadingOverlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function hideLoadingOverlay() {
        const overlay = document.getElementById('ieLoadingOverlay');
        if (overlay) overlay.style.display = 'none';
    }

    function updateButtonStates() {
        const undoBtn = document.getElementById('ieUndoBtn');
        const saveBtn = document.getElementById('ieSaveBtn');

        if (undoBtn) {
            undoBtn.disabled = editHistory.length === 0;
        }
        if (saveBtn) {
            saveBtn.disabled = !hasUnsavedChanges;
        }
    }

    function pushToHistory(imageSrc, filename, operation) {
        editHistory.push({
            src: imageSrc,
            filename: filename
        });
        operationHistory.push(operation);
        hasUnsavedChanges = true;
        updateButtonStates();
    }

    function performUndo() {
        if (editHistory.length === 0) return;

        // Pop the most recent change
        editHistory.pop();
        operationHistory.pop();

        // Get the previous state (or original if stack is empty)
        let previousSrc, previousFilename;
        if (editHistory.length > 0) {
            const prev = editHistory[editHistory.length - 1];
            previousSrc = prev.src;
            previousFilename = prev.filename;
        } else {
            previousSrc = currentData.originalSrc;
            previousFilename = currentData.originalFilename;
        }

        currentTempFile = previousFilename;

        // Update canvas
        if (cropper) cropper.destroy();
        const canvas = document.getElementById('ieCanvas');
        canvas.src = previousSrc + '?v=' + Date.now();
        canvas.onload = initCropper;

        // Update state
        if (editHistory.length === 0) {
            hasUnsavedChanges = false;
        }
        updateButtonStates();
        showToast('Undone', 'success');
    }

    async function applyCrop() {
        if (!cropper) return;
        const mode = document.getElementById('ieEditMode').value;
        const data = cropper.getData(true);

        const payload = {
            action: 'crop',
            source_file: currentTempFile || currentData.originalFilename,
            coords: {
                x: Math.round(data.x),
                y: Math.round(data.y),
                width: Math.round(data.width),
                height: Math.round(data.height)
            },
            mode: mode
        };

        await executeAction(TEMP_API_ENDPOINT, payload, mode === 'crop' ? 'Crop' : 'Mask');
    }

    async function applyRotate() {
        const angle = parseFloat(document.getElementById('ieRotateAngle').value);
        const payload = {
            action: 'rotate',
            source_file: currentTempFile || currentData.originalFilename,
            angle: angle
        };
        await executeAction(TEMP_API_ENDPOINT, payload, `Rotate ${angle}°`);
    }

    async function applyResize() {
        const width = parseInt(document.getElementById('ieResizeWidth').value);
        const height = parseInt(document.getElementById('ieResizeHeight').value);
        const payload = {
            action: 'resize',
            source_file: currentTempFile || currentData.originalFilename,
            width: width,
            height: height
        };
        await executeAction(TEMP_API_ENDPOINT, payload, `Resize ${width}x${height}`);
    }

    async function applyFilters() {
        const brightness = parseInt(document.getElementById('ieBrightness')?.value || 0);
        const contrast = parseInt(document.getElementById('ieContrast')?.value || 0);
        const blur = parseFloat(document.getElementById('ieBlurRadius')?.value || 0);
        const sharpen = parseInt(document.getElementById('ieSharpenAmount')?.value || 0);

        const params = {};
        if (brightness !== 0) params.brightness = brightness;
        if (contrast !== 0) params.contrast = contrast;
        if (blur > 0) params.blur_radius = blur;
        if (sharpen > 0) params.sharpen_amount = sharpen;

        if (Object.keys(params).length === 0) {
            showToast('No adjustments made', 'info');
            return;
        }

        const payload = {
            action: 'filter',
            source_file: currentTempFile || currentData.originalFilename,
            filter_type: 'composite',
            params: params
        };
        await executeAction(TEMP_API_ENDPOINT, payload, 'Adjustments');
    }

    async function applyFilter(filterType) {
        const payload = {
            action: 'filter',
            source_file: currentTempFile || currentData.originalFilename,
            filter_type: filterType
        };
        await executeAction(TEMP_API_ENDPOINT, payload, filterType);
    }

    async function executeAction(endpoint, payload, operationName) {
        abortController = new AbortController();
        showLoadingOverlay();

        try {
            // Save current state to history before applying new change
            const canvas = document.getElementById('ieCanvas');
            const currentSrc = canvas.src.split('?')[0];
            const currentFile = currentTempFile || currentData.originalFilename;
            pushToHistory(currentSrc, currentFile, operationName);

            const resp = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: abortController.signal
            });
            const result = await resp.json();

            if (result.success) {
                showToast(`${operationName} applied`, 'success');
                const newSrc = '/' + result.filename;

                // Update current temp file
                currentTempFile = result.filename;

                if (cropper) cropper.destroy();
                canvas.src = newSrc + '?v=' + Date.now();
                canvas.onload = initCropper;
            } else {
                // Remove the history entry we just added since operation failed
                editHistory.pop();
                operationHistory.pop();
                hasUnsavedChanges = editHistory.length > 0;
                updateButtonStates();

                showToast(`${operationName} failed: ` + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            // Remove the history entry we just added since operation failed
            editHistory.pop();
            operationHistory.pop();
            hasUnsavedChanges = editHistory.length > 0;
            updateButtonStates();

            if (err.name === 'AbortError') {
                showToast('Operation cancelled by user.', 'info');
            } else {
                showToast(`${operationName} request failed.`, 'error');
                console.error(err);
            }
        } finally {
            hideLoadingOverlay();
            abortController = null;
        }
    }

    async function saveToDatabase() {
        if (!hasUnsavedChanges) {
            showToast('No changes to save', 'info');
            return;
        }

        if (!currentTempFile) {
            showToast('No temporary file to save', 'error');
            return;
        }

        showLoadingOverlay();

        try {
            const resp = await fetch(SAVE_FINAL_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    entity: currentData.entity,
                    original_frame_id: currentData.originalFrameId,
                    temp_filename: currentTempFile,
                    operations: operationHistory
                })
            });

            const result = await resp.json();

            if (result.success) {
                showToast('Saved successfully!', 'success');

                // Update current data to reflect the new saved frame
                currentData.frameId = result.new_frame_id;
                currentData.originalFilename = result.filename;
                currentTempFile = result.filename;

                // Clear history and mark as saved
                hasUnsavedChanges = false;
                editHistory = [];
                operationHistory = [];
                updateButtonStates();

                // Trigger event for gallery refresh
                $(document).trigger('imageEdit.saved', [result]);
            } else {
                showToast('Save failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch (err) {
            showToast('Save request failed', 'error');
            console.error(err);
        } finally {
            hideLoadingOverlay();
        }
    }

    function showToast(message, type) {
        if (typeof Toast !== 'undefined' && Toast.show) {
            Toast.show(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }

    function openModal(opts) {
        currentData = {
            entity: opts.entity || opts.entityType,
            entityId: opts.entityId || opts.entity_id,
            frameId: opts.frameId || opts.frame_id,
            originalFrameId: opts.frameId || opts.frame_id,
            src: opts.src,
            originalSrc: opts.src,
            originalFilename: extractFilenameFromSrc(opts.src)
        };

        // Reset state
        editHistory = [];
        operationHistory = [];
        hasUnsavedChanges = false;
        currentTempFile = null;
        updateButtonStates();

        const canvas = document.getElementById('ieCanvas');
        canvas.src = currentData.src;
        canvas.onload = function() {
            const w = this.naturalWidth;
            const h = this.naturalHeight;
            const widthInput = document.getElementById('ieResizeWidth');
            const heightInput = document.getElementById('ieResizeHeight');
            if (widthInput) widthInput.value = w;
            if (heightInput) heightInput.value = h;
            initCropper();
        };

        document.getElementById('imageEditorModal').style.display = 'flex';
    }

    function extractFilenameFromSrc(src) {
        // Extract just the filename part from a URL like '/frames/characters/frame0001234.png'
        return src.replace(/^\//, ''); // Remove leading slash if present
    }

    function closeModal() {
        if (hasUnsavedChanges) {
            if (!confirm('You have unsaved changes. Close anyway?')) {
                return;
            }
        }

        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        document.getElementById('imageEditorModal').style.display = 'none';
        currentData = {};
        editHistory = [];
        operationHistory = [];
        hasUnsavedChanges = false;
        currentTempFile = null;
    }

    // Event bindings
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('imageEditorModal');
        if (!modal) return;

        modal.querySelector('.ie-close-btn')?.addEventListener('click', closeModal);
        modal.querySelector('#ieCloseBtn')?.addEventListener('click', closeModal);
        modal.querySelector('#ieSaveBtn')?.addEventListener('click', saveToDatabase);
        modal.querySelector('#ieUndoBtn')?.addEventListener('click', performUndo);
        modal.querySelector('.ie-modal-overlay')?.addEventListener('click', closeModal);

        modal.querySelector('#ieCancelActionBtn')?.addEventListener('click', () => {
            if (abortController) {
                abortController.abort();
            }
        });

        // Tab switching
        modal.querySelectorAll('.ie-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.dataset.tab;
                modal.querySelectorAll('.ie-tab').forEach(t => t.classList.remove('active'));
                modal.querySelectorAll('.ie-tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                modal.querySelector(`[data-tab-content="${targetTab}"]`)?.classList.add('active');
            });
        });

        // Aspect ratio
        modal.querySelector('#ieAspectRatio')?.addEventListener('change', function() {
            if (!cropper) return;
            const ratio = this.value;
            cropper.setAspectRatio(ratio === 'free' ? NaN : parseFloat(ratio));
        });

        // Action buttons
        modal.querySelector('#ieApplyCrop')?.addEventListener('click', applyCrop);
        modal.querySelector('#ieApplyRotate')?.addEventListener('click', applyRotate);
        modal.querySelector('#ieApplyResize')?.addEventListener('click', applyResize);
        modal.querySelector('#ieApplyFilters')?.addEventListener('click', applyFilters);

        // Preset filters
        modal.querySelectorAll('.ie-filter-presets button').forEach(btn => {
            btn.addEventListener('click', function() {
                applyFilter(this.dataset.filter);
            });
        });

        // Range sliders
        modal.querySelector('#ieBrightness')?.addEventListener('input', function() {
            const val = document.getElementById('ieBrightnessVal');
            if (val) val.textContent = this.value;
        });
        modal.querySelector('#ieContrast')?.addEventListener('input', function() {
            const val = document.getElementById('ieContrastVal');
            if (val) val.textContent = this.value;
        });
        modal.querySelector('#ieBlurRadius')?.addEventListener('input', function() {
            document.getElementById('ieBlurRadiusVal').textContent = parseFloat(this.value).toFixed(1);
        });
        modal.querySelector('#ieSharpenAmount')?.addEventListener('input', function() {
            document.getElementById('ieSharpenAmountVal').textContent = this.value;
        });
    });

    // Global API
    window.ImageEditorModal = {
        open: openModal,
        close: closeModal
    };

})();
</script>
JS;
}

    public function withoutCSS(): self
    {
        $this->includeCSS = false;
        return $this;
    }

    public function withoutJS(): self
    {
        $this->includeJS = false;
        return $this;
    }
}
