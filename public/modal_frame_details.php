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
</style>

<div id="frameDetailsModal" class="ie-modal" style="display: none;">
    <div class="ie-modal-overlay"></div>
    <div class="ie-modal-container">
        <button class="ie-close-btn" title="Close">×</button>
        <div class="ie-modal-body">
            <div id="ieLoadingOverlay" class="ie-loading-overlay" style="display: none;">
                <p>Loading...</p>
            </div>
            <iframe id="frameDetailsIframe" src="about:blank"></iframe>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const modal = document.getElementById('frameDetailsModal');
    const iframe = document.getElementById('frameDetailsIframe');
    const loader = document.getElementById('ieLoadingOverlay');
    const closeBtn = modal.querySelector('.ie-close-btn');
    const overlay = modal.querySelector('.ie-modal-overlay');
    
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
        // clear iframe to stop any running scripts / keep privacy
        try { iframe.contentWindow.location.href = 'about:blank'; } catch (e) { /* ignore cross-origin */ }
        iframe.src = 'about:blank';
    };
    
    /**
     * Show frame details in modal
     */
    window.showFrameDetailsModal = function(frameId, zoomLevel = 0.1) {
        if (!frameId) return;
        const url = `/view_frame.php?frame_id=${frameId}&view=modal&zoom=${zoomLevel}`;
        openModal(url, `Loading frame #${frameId}...`);
    };
    
    /**
     * Show entity form in modal
     */
    window.showEntityFormInModal = function(entity, entityId) {
        if (!entity || !entityId) return;
        const url = `/entity_form.php?entity_type=${entity}&entity_id=${entityId}`;
        openModal(url, `Loading ${entity} editor...`);
    };
    
    /**
     * Show frame chain in modal
     */
    window.showFrameChainInModal = function(startFrameId) {
        if (!startFrameId) return;
        const url = `/view_frame_chain.php?start_frame_id=${startFrameId}&view=modal`;
        openModal(url, `Loading chain for frame #${startFrameId}...`);
    };

    /**
     * Show regenerate frames page in modal
     *
     * Usage:
     *   window.showRegenerateFramesModal(); // opens regenerate_frames_set.php
     *   window.showRegenerateFramesModal('my_entities', 100, 50); // adds ?entity=my_entities&start_id=100&limit=50
     */
    window.showRegenerateFramesModal = function(entity = '', start_id = undefined, limit = undefined) {
        const params = new URLSearchParams();
        if (entity) params.set('entity', entity);
        if (typeof start_id !== 'undefined') params.set('start_id', String(start_id));
        if (typeof limit !== 'undefined') params.set('limit', String(limit));
        const qs = params.toString();
        const url = `/regenerate_frames_set.php${qs ? '?' + qs : ''}`;
        openModal(url, 'Loading Regenerate Frames...');
    };
    
    /**
     * Show import entity form in modal (strict snake_case params)
     */
    window.showImportEntityModal = function(params) {
        // Validate required params
        if (!params || !params.source || !params.target) {
            console.error('showImportEntityModal: source and target are required');
            if (typeof Toast !== 'undefined') {
                Toast.show('Missing required parameters', 'error');
            }
            return;
        }
        
        // Build URL with all parameters (snake_case only)
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
        
        // Determine loading message based on mode
        let loadingMsg = 'Loading import form...';
        if (params.controlnet) {
            loadingMsg = 'Loading ControlNet assignment...';
        } else if (params.composite) {
            loadingMsg = 'Loading Composite assignment...';
        }
        
        openModal(url, loadingMsg);
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