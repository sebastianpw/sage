<?php
// public/modal_video_details.php
if (defined('SPW_MODAL_VIDEO_DETAILS_INCLUDED')) {
    return;
}
define('SPW_MODAL_VIDEO_DETAILS_INCLUDED', true);
?>
<style>
#videoDetailsModal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999999;
    display: none;
    align-items: center;
    justify-content: center;
}

#videoDetailsModal .vd-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(2px);
}

#videoDetailsModal .vd-container {
    position: relative;
    background: #000;
    border-radius: 8px;
    width: 95%;
    max-width: 1600px;
    height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 50px rgba(0, 0, 0, 0.7);
    border: 1px solid #333;
}

#videoDetailsModal .vd-header {
    padding: 10px 15px;
    background: #1a1a1a;
    border-bottom: 1px solid #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px 8px 0 0;
}

#videoDetailsModal .vd-title {
    color: #eee;
    font-weight: 600;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#videoDetailsModal .vd-close-btn {
    background: none;
    border: none;
    color: #aaa;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 0 5px;
    transition: color 0.2s;
}
#videoDetailsModal .vd-close-btn:hover { color: #fff; }

#videoDetailsModal .vd-body {
    flex: 1;
    position: relative;
    background: #000;
    border-radius: 0 0 8px 8px;
    overflow: hidden;
}

#videoDetailsModal iframe {
    width: 100%;
    height: 100%;
    border: none;
    background: #000;
}

#videoDetailsLoader {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #888;
    background: #000;
    z-index: 1;
}
</style>

<div id="videoDetailsModal">
    <div class="vd-overlay"></div>
    <div class="vd-container">
        <div class="vd-header">
            <div class="vd-title" id="videoDetailsTitle">Video Preview</div>
            <button class="vd-close-btn" title="Close">&times;</button>
        </div>
        <div class="vd-body">
            <div id="videoDetailsLoader">Loading...</div>
            <iframe id="videoDetailsIframe" allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const modal = document.getElementById('videoDetailsModal');
    const iframe = document.getElementById('videoDetailsIframe');
    const loader = document.getElementById('videoDetailsLoader');
    const titleEl = document.getElementById('videoDetailsTitle');
    const closeBtn = modal.querySelector('.vd-close-btn');
    const overlay = modal.querySelector('.vd-overlay');

    function openModal(url, titleText = 'Video Preview') {
        titleEl.textContent = titleText;
        loader.style.display = 'flex';
        iframe.style.opacity = '0';
        
        modal.style.display = 'flex';
        iframe.src = url;
    }

    window.closeVideoModal = function() {
        modal.style.display = 'none';
        iframe.src = 'about:blank';
    };

    window.showVideoPreview = function(videoUrl, videoName) {
        if (!videoUrl) return;
        openModal(videoUrl, videoName || 'Video Preview');
    };

    window.showVideoContextUrl = function(url, title) {
        if (!url) return;
        openModal(url, title || 'Details');
    };

    iframe.onload = () => {
        loader.style.display = 'none';
        iframe.style.opacity = '1';
    };

    closeBtn.onclick = window.closeVideoModal;
    overlay.onclick = window.closeVideoModal;
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            window.closeVideoModal();
        }
    });

})();
</script>