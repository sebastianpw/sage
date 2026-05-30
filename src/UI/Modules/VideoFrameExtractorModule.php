<?php
namespace App\UI\Modules;

class VideoFrameExtractorModule
{
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_endpoint' => '/video_extraction_api.php',
        ], $config);
    }

    public function render(): string
    {
        return $this->renderCSS() . $this->renderHTML() . $this->renderJS();
    }

    private function renderHTML(): string
    {
        // Reuses ie-modal classes for consistent look with Image Editor
        return <<<HTML
<div id="videoExtractorModal" class="ie-modal" style="display: none;">
    <div class="ie-modal-overlay"></div>
    <div class="ie-modal-container" style="max-width: 1000px;">
        <div class="ie-modal-header">
            <h2 class="ie-modal-title">Extract Video Frame</h2>
            <button class="ie-close-btn" title="Close">×</button>
        </div>
        <div class="ie-modal-body">
            <div class="ie-editor-layout">
                <!-- Video Area -->
                <div class="ie-canvas-wrapper" style="background:#000; position:relative; display:flex; flex-direction:column;">
                    <video id="vePlayer" style="max-width:100%; max-height: 60vh; width:auto; height:auto; box-shadow:none;" controls crossorigin="anonymous"></video>
                </div>
                
                <!-- Controls Area -->
                <div class="ie-tools-panel">
                    <div class="ie-tool-group">
                        <div class="ie-coords-display" id="veTimeDisplay" style="font-size:16px; margin-bottom:10px;">
                            00:00.000
                        </div>
                    </div>
                    
                    <div class="ie-tool-group">
                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                            <button class="btn btn-secondary" data-seek="-1.0">-1sec</button>
                            <button class="btn btn-secondary" data-seek="-0.04">&lt; Frm</button>
                            <button class="btn btn-secondary" data-seek="0.04">Frm &gt;</button>
                            <button class="btn btn-secondary" data-seek="1.0">+1sec</button>


<button class="btn btn-primary" id="veExtractBtn" style="font-size:1.1em; padding:12px;"> ✂️ xt</button>

                        </div>
                    </div>

                    <div style="margin-top:auto;">
                         <div class="ie-tool-group">
                            <label>Status</label>
                            <div id="veStatus" style="color:#888; font-size:12px; min-height:1.5em; margin-bottom:5px;">Ready</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ie-modal-footer">
            <button class="ie-btn ie-btn-secondary" id="veCloseBtn">Close</button>
        </div>
    </div>
</div>
HTML;
    }

    private function renderCSS(): string
    {
        // Styling specific to video player interactions
        return '<style>#vePlayer:focus { outline:none; }</style>';
    }

    private function renderJS(): string
    {
        $api = $this->config['api_endpoint'];
        return <<<JS
<script>
(function() {
    let currentVideoId = null;
    const modal = document.getElementById('videoExtractorModal');
    const player = document.getElementById('vePlayer');
    const timeDisplay = document.getElementById('veTimeDisplay');
    const statusDiv = document.getElementById('veStatus');
    const extractBtn = document.getElementById('veExtractBtn');

    function fmtTime(s) {
        if(isNaN(s)) return "00:00.000";
        const m = Math.floor(s / 60);
        const sc = Math.floor(s % 60);
        const ms = Math.floor((s % 1) * 1000);
        return m + ":" + sc.toString().padStart(2,'0') + "." + ms.toString().padStart(3,'0');
    }

    function updateTime() {
        timeDisplay.textContent = fmtTime(player.currentTime);
    }

    function open(url, videoId) {
        currentVideoId = videoId;
        player.src = url;
        player.load();
        modal.style.display = 'flex';
        statusDiv.textContent = "Ready";
        extractBtn.disabled = false;
        
        // Auto-play to verify load then pause
        player.play().then(() => {
            player.pause();
        }).catch(e => console.log("Auto-play prevented"));
    }

    function close() {
        player.pause();
        player.src = "";
        modal.style.display = 'none';
        currentVideoId = null;
    }

    function seek(delta) {
        player.currentTime = Math.max(0, Math.min(player.duration, player.currentTime + parseFloat(delta)));
    }

    async function extract() {
        if(!currentVideoId) return;
        
        const time = player.currentTime;
        player.pause();
        
        extractBtn.disabled = true;
        extractBtn.textContent = "Extracting...";
        statusDiv.textContent = "Processing on server...";

        try {
            const res = await fetch('{$api}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    video_id: currentVideoId,
                    timestamp: time
                })
            });
            const data = await res.json();
            
            if(data.status === 'ok') {
                statusDiv.textContent = "Saved: " + data.filename;
                if(typeof Toast !== 'undefined') Toast.show('Frame Extracted!', 'success');
                
                // Offer to open in Image Editor
                if(window.ImageEditorModal && confirm("Frame extracted successfully! Open in Image Editor?")) {
                    close();
                    // Small delay to ensure modal close animation finishes
                    setTimeout(() => {
                        window.ImageEditorModal.open({
                            src: data.url,
                            frameId: data.frame_id,
                            entityType: 'animatics', // As we linked it to animatics
                            entityId: currentVideoId // Use video ID or map to animatic ID if available on frontend
                        });
                    }, 100);
                    return;
                }
            } else {
                throw new Error(data.message);
            }
        } catch(e) {
            console.error(e);
            statusDiv.textContent = "Error: " + e.message;
            if(typeof Toast !== 'undefined') Toast.show(e.message, 'error');
        } finally {
            extractBtn.disabled = false;
            extractBtn.textContent = "✂️ Extract Frame";
        }
    }

    // Listeners
    player.addEventListener('timeupdate', updateTime);
    player.addEventListener('loadedmetadata', updateTime);
    
    // Close buttons
    modal.querySelector('.ie-close-btn').onclick = close;
    modal.querySelector('.ie-modal-overlay').onclick = close;
    document.getElementById('veCloseBtn').onclick = close;
    
    extractBtn.onclick = extract;
    
    modal.querySelectorAll('button[data-seek]').forEach(b => {
        b.onclick = (e) => seek(e.target.dataset.seek);
    });

    // Expose API
    window.VideoFrameExtractor = { open: open, close: close };
})();
</script>
JS;
    }
}
