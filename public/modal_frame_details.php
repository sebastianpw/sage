<style>
    #frameDetailsModal {
        display: none;
        position: fixed;
        z-index: 1000000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.85);
    }
    #frameDetailsModal .modal-content {
        position: relative;
        margin: 2% auto;
        width: 95%;
        height: 95%;
        max-width: 1400px;
        background: #111;
        border: 1px solid #555;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
    }
    #frameDetailsModal .close {
        color: #aaa;
        position: absolute;
        top: 5px;
        right: 20px;
        font-size: 35px;
        font-weight: bold;
        cursor: pointer;
        z-index: 1010; /* Above iframe */
    }
    #frameDetailsModal .close:hover,
    #frameDetailsModal .close:focus {
        color: white;
    }
    #frameDetailsModal .loader {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #fff;
        font-size: 20px;
        z-index: 1009;
    }
    #frameDetailsIframe {
        width: 100%;
        height: 100%;
        border: none;
        border-radius: 8px;
    }
</style>

<div id="frameDetailsModal">
    <span class="close">&times;</span>
    <div class="modal-content">
        <div class="loader">Loading frame...</div>
        <iframe id="frameDetailsIframe" src="about:blank"></iframe>
    </div>
</div>

<script>
$(document).ready(function() {
    const $modal = $('#frameDetailsModal');
    const $iframe = $('#frameDetailsIframe');
    const $loader = $modal.find('.loader');
    const $closeBtn = $modal.find('.close');


    /**
     * Shows the frame details modal.
     * @param {number} frameId The ID of the frame to load.
     * @param {number} [zoomLevel=0.5] The initial zoom level for the iframe content.
     */
    window.showFrameDetailsModal = function(frameId, zoomLevel = 0.1) {
        if (!frameId) return;

        $loader.text('Loading frame #' + frameId + '...').show();
        $iframe.css('opacity', '0');
        $modal.fadeIn(200);

        // Append both view=modal and the new zoom parameter
        const url = `/view_frame.php?frame_id=${frameId}&view=modal&zoom=${zoomLevel}`;
        $iframe.attr('src', url);
    };


    // Hide loader and show iframe once content is loaded
    $iframe.on('load', function() {
        $loader.hide();
        $iframe.animate({ opacity: 1 }, 200);
    });

    // Close modal actions
    function closeModal() {
        $modal.fadeOut(200, function() {
            // Reset the iframe to stop any scripts and free up memory
            $iframe.attr('src', 'about:blank');
        });
    }

    $closeBtn.on('click', closeModal);
    $modal.on('click', function(event) {
        // Close if clicking on the background overlay, but not the content
        if ($(event.target).is($modal)) {
            closeModal();
        }
    });
    $(document).on('keydown', function(event) {
        if (event.key === "Escape" && $modal.is(':visible')) {
            closeModal();
        }
    });
});
</script>

<?php
/*

// example invocation

require 'modal_frame_details.php'; 
?>

    <a href="javascript:void(0);" onclick="showFrameDetailsModal(1234);">View Frame 1234</a>
    
    <!-- Or on an image click in a gallery -->
    <img src="..." data-frame-id="5678" class="gallery-thumb">
    
    <script>
    $('.gallery-thumb').on('click', function() {
        const frameId = $(this).data('frame-id');
        showFrameDetailsModal(frameId);
    });
    </script>

<a href="javascript:void(0);" onclick="showFrameDetailsModal(1949,0.5);"  href="view_frame.php?entity=generatives&entity_id=143&frame_id=1949">
<img style="width: 200px; height: 200px;" src="/frames_starlightguardians_nu/frame0004455.jpg" alt="Observatory" class="frame-image">
</a>

*/ ?>
