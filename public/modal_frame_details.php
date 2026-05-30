<?php
// public/modal_frame_details.php
// Prevent double inclusion on server-side
if (defined('SPW_MODAL_FRAME_DETAILS_INCLUDED')) {
    return;
}
define('SPW_MODAL_FRAME_DETAILS_INCLUDED', true);
?>
<style>
/* Full-screen modal overlay */
.ie-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ie-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
}

.ie-modal-container {
    position: relative;
    background: #1a1a1a;
    border-radius: 8px;
    width: 95%;
    max-width: 1400px;
    height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

/* ── Fullscreen variant — no margin, no rounding, true 100% ── */
.ie-modal-container.ie-fullscreen {
    width: 100%;
    height: 100%;
    max-width: 100%;
    border-radius: 0;
    box-shadow: none;
}
.ie-modal-container.ie-fullscreen #frameDetailsIframe {
    border-radius: 0;
}

.ie-close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    color: #aaa;
    font-size: 32px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    line-height: 1;
    z-index: 10;
}

.ie-close-btn:hover {
    color: #fff;
}

.ie-modal-body {
    flex: 1;
    overflow: hidden;
    padding: 0;
    display: flex;
    flex-direction: column;
}

.ie-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(26, 26, 26, 0.9);
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    border-radius: 8px;
}

#frameDetailsIframe {
    width: 100%;
    height: 100%;
    border: none;
    border-radius: 8px;
    background: #111;
}

/* Picker preview footer — injected below iframe when showVideoPickerPreview is used */
.ie-picker-footer {
    display: flex;
    gap: 10px;
    padding: 12px 16px;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: #1a1a1a;
    flex-shrink: 0;
    border-radius: 0 0 8px 8px;
}
.ie-picker-footer .btn {
    flex: 1;
    padding: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    font-family: inherit;
    -webkit-tap-highlight-color: transparent;
}
.ie-picker-footer .btn-add {
    background: var(--accent, #6c63ff);
    color: #fff;
}
.ie-picker-footer .btn-add:active { opacity: 0.85; }
.ie-picker-footer .btn-close {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2) !important;
    color: #aaa;
}
.ie-picker-footer .btn-close:active { background: rgba(255,255,255,0.07); }

/* Mini-graph modal variant — slightly narrower, taller */
.ie-modal-container.ie-mini-graph {
    max-width: 900px;
    height: 80vh;
}
</style>

<div id="frameDetailsModal" class="ie-modal" style="display: none;">
    <div class="ie-modal-overlay"></div>
    <div class="ie-modal-container" id="ieModalContainer">
        <button class="ie-close-btn" title="Close">×</button>
        <div class="ie-modal-body">
            <div id="ieLoadingOverlay" class="ie-loading-overlay" style="display: none;">
                <p>Loading...</p>
            </div>
            <iframe id="frameDetailsIframe" src="about:blank"></iframe>
        </div>
        <!-- Picker footer: hidden by default, shown only when opened via showVideoPickerPreview -->
        <div class="ie-picker-footer" id="iePickerFooter" style="display:none;">
            <button class="btn btn-add" id="iePickerAddBtn">Add to Shot</button>
            <button class="btn btn-close" onclick="window.closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const modal     = document.getElementById('frameDetailsModal');
    const iframe    = document.getElementById('frameDetailsIframe');
    const loader    = document.getElementById('ieLoadingOverlay');
    const closeBtn  = modal.querySelector('.ie-close-btn');
    const overlay   = modal.querySelector('.ie-modal-overlay');
    const pickerFooter = document.getElementById('iePickerFooter');
    const pickerAddBtn = document.getElementById('iePickerAddBtn');
    const container    = document.getElementById('ieModalContainer');

    // Internal: pending picker callback
    let _pickerCallback = null;
    
    /**
     * Open modal with specified URL
     */
    function openModal(url, loadingText = 'Loading...') {
        // Reset and prepare
        loader.querySelector('p').textContent = loadingText;
        loader.style.display = 'flex';
        iframe.style.opacity = '0';
        iframe.src = 'about:blank';
        
        // Show modal
        modal.style.display = 'flex';
        
        // Load content
        iframe.src = url;
    }
    
    /**
     * Close modal
     *
     * Expose on window so iframe pages can call window.parent.closeModal()
     */
    window.closeModal = function() {
        modal.style.display = 'none';
        // Restore default container size classes
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        // Hide and detach picker footer callback
        pickerFooter.style.display = 'none';
        pickerAddBtn.onclick = null;
        _pickerCallback = null;
        // clear iframe to stop any running scripts / keep privacy
        try { iframe.contentWindow.location.href = 'about:blank'; } catch (e) { /* ignore cross-origin */ }
        iframe.src = 'about:blank';
    };
    
    /**
     * Show frame details in modal
     */
    window.showFrameDetailsModal = function(frameId, zoomLevel = 0.1) {
        if (!frameId) return;
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        const url = `/view_frame.php?frame_id=${frameId}&view=modal&zoom=${zoomLevel}`;
        openModal(url, `Loading frame #${frameId}...`);
    };
    
    /**
     * Show entity form in modal
     */
    window.showEntityFormInModal = function(entity, entityId) {
        if (!entity || !entityId) return;
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        const url = `/entity_form.php?entity_type=${entity}&entity_id=${entityId}`;
        openModal(url, `Loading ${entity} editor...`);
    };
    
    /**
     * Show multiplane in modal
     */
    window.showMultiplaneInModal = function(compositeId) {
        if (!compositeId) return;
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        const url = `/view_multiplane.php?composite_id=${compositeId}`;
        openModal(url, `Loading multiplane...`);
    };
    
    /**
     * Show frame chain in modal
     */
    window.showFrameChainInModal = function(startFrameId) {
        if (!startFrameId) return;
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        const url = `/view_frame_chain.php?start_frame_id=${startFrameId}&view=modal`;
        openModal(url, `Loading chain for frame #${startFrameId}...`);
    };

    /**
     * Show regenerate frames page in modal
     */
    window.showRegenerateFramesModal = function(entity = '', start_id = undefined, limit = undefined) {
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        const params = new URLSearchParams();
        if (entity) params.set('entity', entity);
        if (typeof start_id !== 'undefined') params.set('start_id', String(start_id));
        if (typeof limit !== 'undefined') params.set('limit', String(limit));
        const qs = params.toString();
        const url = `/regenerate_frames_set.php${qs ? '?' + qs : ''}`;
        openModal(url, 'Loading Regenerate Frames...');
    };

    /**
     * Show Scheduler Runner (Control Deck) in Modal
     */
    window.showSchedulerRunnerModal = function() {
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        openModal('/scheduler_runner.php', 'Loading Control Deck...');
    };

    /**
     * Show rich video details in modal
     */
    window.showVideoDetailsModal = function(videoId) {
        if (!videoId) return;
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        const url = `/view_video_details.php?id=${videoId}`;
        openModal(url, `Loading details for video #${videoId}...`);
    };

    /**
     * Show video details as a picker preview.
     */
    window.showVideoPickerPreview = function(videoId, onConfirm) {
        if (!videoId) return;
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        _pickerCallback = onConfirm || null;
        pickerFooter.style.display = 'flex';
        pickerAddBtn.onclick = function() {
            const cb = _pickerCallback;
            window.closeModal();
            if (typeof cb === 'function') cb();
        };
        const url = `/view_video_details.php?id=${videoId}`;
        openModal(url, `Loading video #${videoId}...`);
    };
    
    /**
     * Show import entity form in modal (strict snake_case params)
     */
    window.showImportEntityModal = function(params) {
        if (!params || !params.source || !params.target) {
            console.error('showImportEntityModal: source and target are required');
            if (typeof Toast !== 'undefined') {
                Toast.show('Missing required parameters', 'error');
            }
            return;
        }
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        
        const urlParams = new URLSearchParams();
        urlParams.set('source', params.source);
        urlParams.set('target', params.target);
        urlParams.set('source_entity_id', params.source_entity_id !== undefined ? String(params.source_entity_id) : '0');
        if (params.limit !== undefined) urlParams.set('limit', String(params.limit));
        if (params.copy_name_desc !== undefined) urlParams.set('copy_name_desc', params.copy_name_desc ? '1' : '0');
        if (params.frame_id !== undefined) urlParams.set('frame_id', String(params.frame_id));
        if (params.target_entity_id !== undefined) urlParams.set('target_entity_id', String(params.target_entity_id));
        if (params.controlnet) urlParams.set('controlnet', '1');
        if (params.composite) urlParams.set('composite', '1');
        
        const url = `/import_entity_from_entity.php?${urlParams.toString()}`;
        
        let loadingMsg = 'Loading import form...';
        if (params.controlnet) {
            loadingMsg = 'Loading ControlNet assignment...';
        } else if (params.composite) {
            loadingMsg = 'Loading Composite assignment...';
        }
        
        openModal(url, loadingMsg);
    };

    /**
     * Show import mouthshapes modal
     */
    window.showImportMouthshapesModal = function(entity, entityId, frameId) {
        if (!entity || !entityId || !frameId) {
             console.error('Missing parameters for Import Mouthshapes');
             return;
        }
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        const url = `/import_generatives_from_mouthshapes.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}`;
        openModal(url, 'Loading Mouthshapes Import...');
    };

    /**
     * Show generic iframe modal
     */
    window.showIframeModal = function(url, loadingText) {
        if (!url) return;
        container.classList.remove('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        openModal(url, loadingText || 'Loading...');
    };

    /**
     * Show generic iframe modal in true fullscreen (no margin, no rounding).
     * Used for the DAW and any other full-viewport tools.
     */
    window.showFullscreenIframeModal = function(url, loadingText) {
        if (!url) return;
        container.classList.remove('ie-mini-graph');
        container.classList.add('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        openModal(url, loadingText || 'Loading...');
    };

    /**
     * Show mini_graph.php in a focused modal.
     */
    window.showMiniGraphModal = function(urlOrParams, loadingText) {
        let url;
        if (typeof urlOrParams === 'string') {
            url = urlOrParams.startsWith('/') ? urlOrParams : '/' + urlOrParams;
        } else if (urlOrParams && typeof urlOrParams === 'object') {
            const p = new URLSearchParams();
            p.set('graph',   urlOrParams.graph   || 'kg');
            p.set('node_id', String(urlOrParams.node_id || 0));
            if (urlOrParams.doc_id)  p.set('doc_id',  String(urlOrParams.doc_id));
            if (urlOrParams.hops)    p.set('hops',    String(urlOrParams.hops));
            url = '/mini_graph.php?' + p.toString();
        } else {
            console.error('showMiniGraphModal: invalid argument');
            return;
        }
        container.classList.add('ie-mini-graph');
        container.classList.remove('ie-fullscreen');
        pickerFooter.style.display = 'none';
        _pickerCallback = null;
        openModal(url, loadingText || 'Loading mini graph...');
    };

    /**
     * Aliases for deprecated modal_video_details functionality
     */
    window.showVideoContextUrl = window.showIframeModal;
    window.closeVideoModal = window.closeModal;
    window.showVideoPreview = function(url, title) {
        window.showIframeModal(url, title || 'Video Preview');
    };
    
    // Hide loader when iframe loads
    iframe.addEventListener('load', function() {
        loader.style.display = 'none';
        iframe.style.transition = 'opacity 0.3s';
        iframe.style.opacity = '1';
    });
    
    // Close button click
    closeBtn.addEventListener('click', window.closeModal);
    
    // Close on overlay click
    overlay.addEventListener('click', window.closeModal);
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            window.closeModal();
        }
    });
    
})();
</script>
