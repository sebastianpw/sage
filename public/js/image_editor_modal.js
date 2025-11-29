/**
 * ImageEditorModal - Self-contained AJAX modal overlay for image editing
 * Now fully powered by the Python Image Service
 */

window.ImageEditorModal = (function() {
    let instance = null;
    let cropper = null;
    let currentData = {};
    let currentAbortController = null; // <-- For cancelling requests
    
    const template = `
        <div id="imageEditorModal" class="ie-modal" style="display: none;">
            <div class="ie-modal-overlay"></div>
            <div class="ie-modal-container">
                <!-- NEW: Loading Overlay -->
                <div id="ieLoadingOverlay" class="ie-loading-overlay" style="display: none;">
                    <div class="ie-loader"></div>
                    <p>Processing image...</p>
                    <button id="ieCancelRequestBtn" class="ie-btn ie-btn-secondary">Cancel</button>
                </div>
                <!-- End: Loading Overlay -->

                <div class="ie-modal-header">
                    <h2 class="ie-modal-title">Image Editor</h2>
                    <button class="ie-close-btn" title="Close">Ã—</button>
                </div>
                <div class="ie-modal-body">
                    <div class="ie-editor-layout">
                        <div class="ie-canvas-wrapper">
                            <img id="ieCanvas" class="ie-canvas-img" src="" alt="Edit canvas">
                        </div>
                        <div class="ie-tools-panel">
                            <div class="ie-tabs">
                                <button class="ie-tab active" data-tab="crop">Edit</button>
                                <button class="ie-tab" data-tab="transform">Transform</button>
                                <button class="ie-tab" data-tab="filters">Filters</button>
                            </div>
                            <div class="ie-tab-content active" data-tab-content="crop">
                                <div class="ie-tool-group">
                                    <label><strong>Mode</strong></label>
                                    <select id="ieEditMode" style="width:100%;padding:8px;background:#1a1a1a;border:1px solid #444;border-radius:4px;color:#fff">
                                        <option value="mask">Mask (Green Overlay)</option>
                                        <option value="crop">Crop (Reduce Image)</option>
                                    </select>
                                </div>
                                <div class="ie-tool-group">
                                    <label>Aspect Ratio</label>
                                    <select id="ieAspectRatio">
                                        <option value="free">Free</option>
                                        <option value="1">1:1</option>
                                        <option value="1.777">16:9</option>
                                        <option value="0.75">3:4</option>
                                    </select>
                                </div>
                                <div class="ie-coords-display" id="ieCoordsDisplay">X: 0, Y: 0<br>W: 0, H: 0</div>
                                <button class="ie-btn ie-btn-primary" id="ieApplyCrop">Apply Edit</button>
                            </div>
                            <div class="ie-tab-content" data-tab-content="transform">
                                <div class="ie-tool-group">
                                    <label>Rotate (degrees)</label>
                                    <input type="number" id="ieRotateAngle" value="0" min="-360" max="360">
                                </div>
                                <button class="ie-btn ie-btn-primary" id="ieApplyRotate">Apply Rotation</button>
                                <div class="ie-tool-group" style="margin-top: 20px;">
                                    <label>Width (px)</label>
                                    <input type="number" id="ieResizeWidth" value="1024">
                                </div>
                                <div class="ie-tool-group">
                                    <label>Height (px)</label>
                                    <input type="number" id="ieResizeHeight" value="1024">
                                </div>
                                <div class="ie-tool-group">
                                    <label><input type="checkbox" id="ieMaintainAspect" checked> Maintain aspect</label>
                                </div>
                                <button class="ie-btn ie-btn-primary" id="ieApplyResize">Apply Resize</button>
                            </div>
                            <div class="ie-tab-content" data-tab-content="filters">
                                <div class="ie-tool-group">
                                    <label>Brightness</label>
                                    <input type="range" id="ieBrightness" min="-100" max="100" value="0">
                                    <span id="ieBrightnessVal">0</span>
                                </div>
                                <div class="ie-tool-group">
                                    <label>Contrast</label>
                                    <input type="range" id="ieContrast" min="-100" max="100" value="0">
                                    <span id="ieContrastVal">0</span>
                                </div>
                                <button class="ie-btn ie-btn-primary" id="ieApplyFilters">Apply Adjustments</button>
                                <div class="ie-filter-presets">
                                    <button class="ie-btn ie-btn-secondary" data-filter="grayscale">Grayscale</button>
                                    <button class="ie-btn ie-btn-secondary" data-filter="blur">Blur</button>
                                    <button class="ie-btn ie-btn-secondary" data-filter="sharpen">Sharpen</button>
                                    <button class="ie-btn ie-btn-secondary" data-filter="vintage">Vintage</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ie-modal-footer">
                    <button class="ie-btn ie-btn-secondary" id="ieCancelBtn">Cancel</button>
                    <button class="ie-btn ie-btn-primary" id="ieSaveBtn">Save & Close</button>
                </div>
            </div>
        </div>
    `;
    
    // *** THIS IS THE CORRECT, FULL STYLES STRING WITH NEW STYLES FOR OVERLAY ***
    const styles = `<style>
.ie-modal{position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999999;display:flex;align-items:center;justify-content:center}
.ie-modal-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.85)}
.ie-modal-container{position:relative;background:#1a1a1a;border-radius:8px;width:95%;max-width:1400px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 10px 40px rgba(0,0,0,.5)}
.ie-modal-header{padding:15px 20px;border-bottom:1px solid #333;display:flex;justify-content:space-between;align-items:center}
.ie-modal-title{margin:0;color:#0cf;font-size:20px}
.ie-close-btn{background:none;border:none;color:#888;font-size:32px;cursor:pointer;padding:0;width:32px;height:32px;line-height:1}
.ie-close-btn:hover{color:#fff}
.ie-modal-body{flex:1;overflow:hidden;padding:20px}
.ie-editor-layout{display:grid;grid-template-columns:1fr 350px;gap:20px;height:100%}
.ie-canvas-wrapper{background:#2a2a2a;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;min-height:400px}
.ie-canvas-img{max-width:100%;max-height:60vh;display:block}
.ie-tools-panel{background:#2a2a2a;border-radius:8px;padding:15px;overflow-y:auto}
.ie-tabs{display:flex;gap:5px;margin-bottom:15px;border-bottom:1px solid #333}
.ie-tab{background:none;border:none;color:#888;padding:10px 15px;cursor:pointer;border-bottom:2px solid transparent;transition:all .3s}
.ie-tab:hover{color:#0cf}
.ie-tab.active{color:#0cf;border-bottom-color:#0cf}
.ie-tab-content{display:none}
.ie-tab-content.active{display:block}
.ie-tool-group{margin-bottom:15px}
.ie-tool-group label{display:block;color:#aaa;margin-bottom:6px;font-size:13px}
.ie-tool-group input[type=number],.ie-tool-group input[type=range],.ie-tool-group select{width:100%;padding:8px;background:#1a1a1a;border:1px solid #444;border-radius:4px;color:#fff}
.ie-coords-display{background:#1a1a1a;padding:10px;border-radius:4px;font-family:monospace;font-size:12px;color:#0cf;margin-bottom:15px}
.ie-btn{padding:10px 20px;border:none;border-radius:4px;cursor:pointer;font-size:14px;transition:all .3s;width:100%;margin-bottom:10px}
.ie-btn-primary{background:#0cf;color:#000}
.ie-btn-primary:hover{background:#0af}
.ie-btn-secondary{background:#444;color:#fff}
.ie-btn-secondary:hover{background:#555}
.ie-filter-presets{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px}
.ie-modal-footer{padding:15px 20px;border-top:1px solid #333;display:flex;gap:10px;justify-content:flex-end}
.ie-modal-footer .ie-btn{width:auto;margin:0}
@media(max-width:768px){.ie-editor-layout{grid-template-columns:1fr}.ie-modal-container{width:100%;max-width:100%;border-radius:0;max-height:100vh}}
/* NEW: Loading Overlay Styles */
.ie-loading-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(26,26,26,0.95);z-index:10;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;border-radius:8px;font-size:16px;}
.ie-loader{border:4px solid #444;border-top:4px solid #0cf;border-radius:50%;width:40px;height:40px;animation:ie-spin 1s linear infinite;margin-bottom:20px;}
@keyframes ie-spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
#ieCancelRequestBtn{width:auto;padding:8px 16px;margin-top:20px;margin-bottom:0;}
</style>`;
    
    function init() {
        if (instance) return;
        if (!document.getElementById('ie-modal-styles')) {
            $('head').append('<div id="ie-modal-styles">' + styles + '</div>');
        }
        $('body').append(template);
        instance = $('#imageEditorModal');
        bindEvents();
    }
    
    function bindEvents() {
        instance.find('.ie-close-btn, #ieCancelBtn, #ieSaveBtn').on('click', close);
        instance.find('.ie-modal-overlay').on('click', close);
        instance.find('.ie-tab').on('click', function() {
            const tab = $(this).data('tab');
            instance.find('.ie-tab').removeClass('active');
            instance.find('.ie-tab-content').removeClass('active');
            $(this).addClass('active');
            instance.find(`[data-tab-content="${tab}"]`).addClass('active');
        });
        instance.find('#ieAspectRatio').on('change', function() {
            if (!cropper) return;
            const ratio = $(this).val();
            cropper.setAspectRatio(ratio === 'free' ? NaN : parseFloat(ratio));
        });
        instance.find('#ieApplyCrop').on('click', applyCrop);
        instance.find('#ieApplyRotate').on('click', applyRotate);
        instance.find('#ieApplyResize').on('click', applyResize);
        instance.find('#ieApplyFilters').on('click', applyFilters);
        instance.find('.ie-filter-presets button').on('click', function() {
            applyFilter($(this).data('filter'));
        });
        instance.find('#ieBrightness').on('input', function() { $('#ieBrightnessVal').text($(this).val()); });
        instance.find('#ieContrast').on('input', function() { $('#ieContrastVal').text($(this).val()); });
        instance.find('#ieMaintainAspect').on('change', updateAspectRatio);
        
        // NEW: Cancel button event
        instance.find('#ieCancelRequestBtn').on('click', () => {
            if (currentAbortController) {
                currentAbortController.abort();
                if (typeof Toast !== 'undefined') Toast.show('Operation cancelled', 'info');
            }
        });
    }

    // --- NEW: Helper functions to show/hide loading overlay ---
    function showLoading() {
        instance.find('#ieLoadingOverlay').fadeIn(150);
    }
    function hideLoading() {
        instance.find('#ieLoadingOverlay').fadeOut(150);
    }
    
    function initCropper() {
        const canvas = document.getElementById('ieCanvas');
        if (!canvas) return;
        if (cropper) cropper.destroy();
        cropper = new Cropper(canvas, {
            viewMode: 1, dragMode: 'move', autoCropArea: 1,
            guides: true, center: true, highlight: true,
            cropBoxMovable: true, cropBoxResizable: true,
            crop: updateCoordsDisplay
        });
    }
    
    function updateCoordsDisplay() {
        if (!cropper) return;
        const data = cropper.getData(true);
        $('#ieCoordsDisplay').html(`X: ${Math.round(data.x)}, Y: ${Math.round(data.y)}<br>W: ${Math.round(data.width)}, H: ${Math.round(data.height)}`);
    }
    
    function updateAspectRatio() {
        const maintain = $('#ieMaintainAspect').is(':checked');
        if (maintain) {
            const width = parseInt($('#ieResizeWidth').val());
            const height = parseInt($('#ieResizeHeight').val());
            const aspectRatio = width / height;
            $('#ieResizeWidth').on('input', function() {
                $('#ieResizeHeight').val(Math.round(parseInt($(this).val()) / aspectRatio));
            });
            $('#ieResizeHeight').on('input', function() {
                $('#ieResizeWidth').val(Math.round(parseInt($(this).val()) * aspectRatio));
            });
        } else {
            $('#ieResizeWidth').off('input');
            $('#ieResizeHeight').off('input');
        }
    }
    
    async function applyCrop() {
        if (!cropper) return;
        const selectedMode = $('#ieEditMode').val();
        const data = cropper.getData(true);
        const payload = {
            entity: currentData.entity,
            entity_id: currentData.entityId,
            frame_id: currentData.frameId,
            coords: {
                x: Math.round(data.x),
                y: Math.round(data.y),
                width: Math.round(data.width),
                height: Math.round(data.height)
            },
            mode: selectedMode,
            tool: 'cropperjs-modal',
            note: `Applied ${selectedMode} via modal editor`,
            apply_immediately: 1
        };
        // Use the new reusable function
        await executeApiAction(payload, `Crop`, '/save_image_edit.php');
    }

    async function applyRotate() {
        const payload = {
            action: 'rotate',
            frame_id: currentData.frameId,
            entity: currentData.entity,
            angle: parseFloat($('#ieRotateAngle').val())
        };
        await executeApiAction(payload, 'Rotation');
    }
    
    async function applyResize() {
        const payload = {
            action: 'resize',
            frame_id: currentData.frameId,
            entity: currentData.entity,
            width: parseInt($('#ieResizeWidth').val()),
            height: parseInt($('#ieResizeHeight').val())
        };
        await executeApiAction(payload, 'Resize');
    }
    
    async function applyFilters() {
        const brightness = parseInt($('#ieBrightness').val());
        const contrast = parseInt($('#ieContrast').val());
        const params = {};
        if (brightness !== 0) params.brightness = brightness;
        if (contrast !== 0) params.contrast = contrast;

        if (Object.keys(params).length === 0) {
            if (typeof Toast !== 'undefined') Toast.show('No adjustments made.', 'info');
            return;
        }

        const payload = {
            action: 'filter',
            frame_id: currentData.frameId,
            entity: currentData.entity,
            filter_type: 'composite',
            params: params
        };
        await executeApiAction(payload, 'Adjustments');
    }
    
    async function applyFilter(filterType) {
        const payload = {
            action: 'filter',
            frame_id: currentData.frameId,
            entity: currentData.entity,
            filter_type: filterType
        };
        await executeApiAction(payload, filterType.charAt(0).toUpperCase() + filterType.slice(1));
    }

    // --- REBUILT: Reusable function handles all API calls, loading, and cancellation ---
    async function executeApiAction(payload, operationName, endpoint = '/image_editor_api.php') {
        if (!currentData.frameId) {
             if (typeof Toast !== 'undefined') Toast.show('Error: No image loaded.', 'error');
            return;
        }

        currentAbortController = new AbortController();
        showLoading();
        
        try {
            const resp = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: currentAbortController.signal // <-- Pass the signal for cancellation
            });

            // The 'ok' property checks for a successful HTTP status (200-299)
            if (!resp.ok) {
                const errorData = await resp.json().catch(() => ({ message: 'Invalid server response' }));
                throw new Error(errorData.message || `Request failed with status ${resp.status}`);
            }

            const result = await resp.json();

            if (result.success) {
                if (typeof Toast !== 'undefined') Toast.show(`${operationName} applied`, 'success');
                // Handle different response structures for filenames
                const filename = result.derived_filename || result.filename;
                const newSrc = '/' + filename + '?v=' + Date.now();
                
                if (cropper) cropper.destroy();
                $('#ieCanvas').attr('src', newSrc);
                
                $('#ieCanvas').one('load', () => {
                   initCropper();
                });
                
                currentData.frameId = result.new_frame_id;
                
                $(document).trigger('imageEdit.updated', [result]);
            } else {
                throw new Error(result.message || 'Unknown error');
            }
        } catch (err) {
            if (err.name === 'AbortError') {
                // This is not a real error, just the user cancelling.
                console.log('Fetch aborted by user.');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(`${operationName} failed: ` + err.message, 'error');
                console.error(`${operationName} failed:`, err.message);
            }
        } finally {
            // This block runs whether the try succeeded or failed
            hideLoading();
            currentAbortController = null;
        }
    }
    
    function open(opts) {
        init();
        currentData = {
            entity: opts.entity || opts.entityType,
            entityId: opts.entityId || opts.entity_id,
            frameId: opts.frameId || opts.frame_id,
            src: opts.src
        };
        
        if (instance) {
            $('#ieEditMode').val('mask');
        }

        $('#ieCanvas').attr('src', currentData.src);
        $('#ieCanvas').one('load', function() {
            const w = this.naturalWidth;
            const h = this.naturalHeight;
            $('#ieResizeWidth').val(w);
            $('#ieResizeHeight').val(h);
            initCropper();
        });
        instance.fadeIn(200);
    }
    
    function close() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        instance.fadeOut(150);
        currentData = {};
    }
    
    return { open, close };
})();

