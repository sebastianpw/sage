<?php
// public/modal_audio_details.php
if (defined('SPW_MODAL_AUDIO_DETAILS_INCLUDED')) {
    return;
}
define('SPW_MODAL_AUDIO_DETAILS_INCLUDED', true);
?>
<style>
/* 
   Using 'ar-' prefix from existing Audio Recorder modal
   Merged to support Recorder, Importer, and Player
*/
.ar-modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 99999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ar-modal-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85);
}

.ar-modal-container {
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

.ar-close-btn {
    position: absolute;
    top: 10px; right: 15px;
    background: none; border: none;
    color: #aaa; font-size: 32px;
    cursor: pointer; padding: 0;
    width: 32px; height: 32px;
    line-height: 1; z-index: 10;
}
.ar-close-btn:hover { color: #fff; }

.ar-modal-body {
    flex: 1;
    overflow: hidden;
    padding: 0;
    display: flex;
    flex-direction: column;
}

.ar-loading-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(26, 26, 26, 0.9);
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    border-radius: 8px;
}

#audioRecorderIframe {
    width: 100%;
    height: 100%;
    border: none;
    border-radius: 8px;
    background: #111;
}
</style>

<div id="audioRecorderModal" class="ar-modal" style="display: none;">
    <div class="ar-modal-overlay"></div>
    <div class="ar-modal-container">
        <button class="ar-close-btn" title="Close">×</button>
        <div class="ar-modal-body">
            <div id="arLoadingOverlay" class="ar-loading-overlay" style="display: none;">
                <p>Loading...</p>
            </div>
            <iframe id="audioRecorderIframe" src="about:blank"></iframe>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const modal = document.getElementById('audioRecorderModal');
    const iframe = document.getElementById('audioRecorderIframe');
    const loader = document.getElementById('arLoadingOverlay');
    const closeBtn = modal.querySelector('.ar-close-btn');
    const overlay = modal.querySelector('.ar-modal-overlay');
    
    function openModal(url, loadingText = 'Loading...') {
        loader.querySelector('p').textContent = loadingText;
        loader.style.display = 'flex';
        iframe.style.opacity = '0';
        iframe.src = 'about:blank';
        modal.style.display = 'flex';
        iframe.src = url;
    }
    
    /**
     * Close modal - Exposed globally so the Iframe can call it
     */
    window.closeAudioModal = function() {
        modal.style.display = 'none';
        try { iframe.contentWindow.location.href = 'about:blank'; } catch (e) {}
        iframe.src = 'about:blank';
        
        // Check if on a swiper CRUD page to refresh
        if (typeof initSlides === 'function') {
            initSlides(); 
        }
    };
    
    /**
     * PUBLIC API: Show Audio Recorder
     */
    window.showAudioRecorderModal = function(entityType, entityId, wav2wav = 1) {
        if (!entityType || !entityId) {
            console.error('Audio Recorder: Missing entity type or ID');
            return;
        }
        const url = `/audio_recorder.php?entity_type=${entityType}&entity_id=${entityId}&wav2wav=${wav2wav}`;
        openModal(url, 'Initializing Microphone...');
    };

    /**
     * PUBLIC API: Show Import Audio to Composite UI
     */
    window.showImportAudioToCompositeModal = function(params) {
        if (!params || !params.source || !params.source_entity_id) {
            console.error('showImportAudioToCompositeModal: source and source_entity_id are required');
            return;
        }
        
        const urlParams = new URLSearchParams();
        urlParams.set('source', params.source);
        urlParams.set('source_entity_id', String(params.source_entity_id));
        
        if (params.target_composite_id) urlParams.set('target_composite_id', String(params.target_composite_id));
        if (params.audio_id !== undefined && params.audio_id !== null) urlParams.set('audio_id', String(params.audio_id));
        
        const url = `/import_audio_to_composite.php?${urlParams.toString()}`;
        openModal(url, 'Loading Audio Assignment...');
    };

    /**
     * NEW PUBLIC API: Show Audio Player List
     * Loads the generic playlist player for an entity.
     */
    window.showAudioPlayerModal = function(entityType, entityId) {
        if (!entityType || !entityId) {
            console.error('showAudioPlayerModal: Missing entity type or ID');
            return;
        }
        const url = `/player_audio_playlist.php?entity_type=${entityType}&entity_id=${entityId}`;
        openModal(url, 'Loading Playlist...');
    };
    
    // Iframe Load Event
    iframe.addEventListener('load', function() {
        loader.style.display = 'none';
        iframe.style.transition = 'opacity 0.3s';
        iframe.style.opacity = '1';
    });
    
    // Bind Closers
    closeBtn.addEventListener('click', window.closeAudioModal);
    overlay.addEventListener('click', window.closeAudioModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            window.closeAudioModal();
        }
    });
    
})();
</script>
