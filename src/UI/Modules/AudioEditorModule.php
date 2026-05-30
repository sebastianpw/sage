<?php
// src/UI/Modules/AudioEditorModule.php
namespace App\UI\Modules;

/**
 * AudioEditorModule - Visual Audio Cutter using WaveSurfer.js
 */
class AudioEditorModule
{
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_endpoint' => '/audio_editor_api.php',
        ], $config);
    }

    public function render(): string
    {
        return $this->renderCSS() . $this->renderHTML() . $this->renderJS();
    }

    public function renderCSS(): string
    {
        return <<<'CSS'
<style id="ae-modal-styles">
.ae-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999999; display: flex; align-items: center; justify-content: center; visibility: hidden; opacity: 0; transition: all 0.2s ease; pointer-events: none; }
.ae-modal.active { visibility: visible; opacity: 1; pointer-events: auto; }
.ae-modal-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(4px); }
.ae-modal-container { position: relative; background: var(--card); border: 1px solid var(--border); border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 10; overflow: hidden; }
.ae-modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: rgba(var(--muted-border-rgb), 0.2); }
.ae-modal-title { margin: 0; font-size: 18px; font-weight: 600; color: var(--text); }
.ae-close-btn { background: none; border: none; color: var(--text-muted); font-size: 24px; cursor: pointer; line-height: 1; padding: 0; }
.ae-close-btn:hover { color: var(--text); }
.ae-modal-body { padding: 20px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; }
.ae-waveform-container { background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; border: 1px solid var(--border); min-height: 150px; position: relative; }
.ae-controls { display: flex; gap: 10px; align-items: center; justify-content: center; margin-top: 10px; }
.ae-btn { padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border); background: var(--card); color: var(--text); cursor: pointer; font-size: 14px; transition: all 0.2s; }
.ae-btn:hover { border-color: var(--accent); color: var(--accent); }
.ae-btn-primary { background: var(--accent); color: white; border-color: var(--accent); }
.ae-btn-primary:hover { filter: brightness(1.1); color: white; }
.ae-btn:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
.ae-info-bar { font-family: monospace; font-size: 13px; color: var(--text-muted); text-align: center; }
.ae-loader { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; color: white; z-index: 20; border-radius: 8px; font-weight: 600; }
</style>
CSS;
    }

    private function renderHTML(): string
    {
        return <<<HTML
<div id="audioEditorModal" class="ae-modal">
    <div class="ae-modal-overlay"></div>
    <div class="ae-modal-container">
        <div class="ae-modal-header">
            <h3 class="ae-modal-title">Audio Editor</h3>
            <button class="ae-close-btn" title="Close">×</button>
        </div>
        <div class="ae-modal-body">
            <div id="aeWaveform" class="ae-waveform-container">
                <div id="aeLoader" class="ae-loader" style="display:none;">Processing...</div>
            </div>
            
            <div class="ae-info-bar">
                Selection: <span id="aeSelectionStart">0.00</span>s - <span id="aeSelectionEnd">0.00</span>s
                (<span id="aeSelectionDur">0.00</span>s)
            </div>

            <div class="ae-controls">
                <button id="aePlayBtn" class="ae-btn">▶ Play / Pause</button>
                <button id="aeZoomIn" class="ae-btn" title="Zoom In">+</button>
                <button id="aeZoomOut" class="ae-btn" title="Zoom Out">-</button>
            </div>
        </div>
        <div class="ae-modal-header" style="background: var(--card); border-top: 1px solid var(--border); border-bottom: none; justify-content: flex-end; gap: 10px;">
            <button class="ae-btn" id="aeCancelBtn">Cancel</button>
            <button class="ae-btn ae-btn-primary" id="aeSaveCutBtn">✂ Cut & Save as New</button>
        </div>
    </div>
</div>
HTML;
    }

    private function renderJS(): string
    {
        return <<<'JS'
<script type="module">
import WaveSurfer from 'https://unpkg.com/wavesurfer.js@7/dist/wavesurfer.esm.js'
import RegionsPlugin from 'https://unpkg.com/wavesurfer.js@7/dist/plugins/regions.esm.js'
import ZoomPlugin from 'https://unpkg.com/wavesurfer.js@7/dist/plugins/zoom.esm.js'

(function() {
    let ws = null;
    let wsRegions = null;
    let currentParams = null;
    let isPlaying = false;

    const modal = document.getElementById('audioEditorModal');
    const playBtn = document.getElementById('aePlayBtn');
    const saveBtn = document.getElementById('aeSaveCutBtn');
    const loader = document.getElementById('aeLoader');

    // UI helpers
    const showLoader = (msg) => { if(loader) { loader.style.display = 'flex'; loader.textContent = msg; } };
    const hideLoader = () => { if(loader) loader.style.display = 'none'; };
    const formatTime = (s) => s.toFixed(3);

    window.AudioEditorModal = {
        open: function(params) {
            currentParams = params; // { url, entityType, entityId, audioId }
            modal.classList.add('active');
            
            // Wait for transition or immediate init
            setTimeout(() => initWaveSurfer(params.url), 100);
        },
        close: function() {
            if (ws) {
                ws.destroy();
                ws = null;
            }
            modal.classList.remove('active');
            currentParams = null;
        }
    };

    function initWaveSurfer(url) {
        if (ws) ws.destroy();

        // Check theme for color
        const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
        const waveColor = isDark ? '#4b5563' : '#9ca3af';
        const progressColor = isDark ? '#3b82f6' : '#2563eb';

        ws = WaveSurfer.create({
            container: '#aeWaveform',
            waveColor: waveColor,
            progressColor: progressColor,
            cursorColor: '#ff5500',
            barWidth: 2,
            height: 150,
            url: url,
            plugins: [
                RegionsPlugin.create(),
                ZoomPlugin.create()
            ]
        });

        wsRegions = ws.registerPlugin(RegionsPlugin.create());

        ws.on('ready', () => {
            // Create a default region in the middle
            const dur = ws.getDuration();
            const start = dur * 0.25;
            const end = dur * 0.75;
            
            wsRegions.addRegion({
                start: start,
                end: end,
                content: 'Cut Region',
                color: 'rgba(59, 130, 246, 0.3)',
                drag: true,
                resize: true
            });
            updateRegionInfo(start, end);
        });

        wsRegions.on('region-updated', (region) => {
            updateRegionInfo(region.start, region.end);
        });

        wsRegions.on('region-clicked', (region, e) => {
            e.stopPropagation();
            region.play();
        });

        ws.on('play', () => { isPlaying = true; playBtn.innerText = '⏸ Pause'; });
        ws.on('pause', () => { isPlaying = false; playBtn.innerText = '▶ Play'; });
    }

    function updateRegionInfo(start, end) {
        document.getElementById('aeSelectionStart').innerText = formatTime(start);
        document.getElementById('aeSelectionEnd').innerText = formatTime(end);
        document.getElementById('aeSelectionDur').innerText = formatTime(end - start);
    }

    // Event Listeners
    if (modal) {
        const closeBtn = modal.querySelector('.ae-close-btn');
        const overlay = modal.querySelector('.ae-modal-overlay');
        const cancelBtn = document.getElementById('aeCancelBtn');
        const zoomInBtn = document.getElementById('aeZoomIn');
        const zoomOutBtn = document.getElementById('aeZoomOut');

        if(closeBtn) closeBtn.onclick = window.AudioEditorModal.close;
        if(overlay) overlay.onclick = window.AudioEditorModal.close;
        if(cancelBtn) cancelBtn.onclick = window.AudioEditorModal.close;

        if(playBtn) {
            playBtn.onclick = () => {
                if(!ws) return;
                ws.playPause();
            };
        }

        if(zoomInBtn) zoomInBtn.onclick = () => { if(ws) ws.zoom(ws.options.minPxPerSec * 2); };
        if(zoomOutBtn) zoomOutBtn.onclick = () => { if(ws) ws.zoom(ws.options.minPxPerSec / 2); };

        if(saveBtn) {
            saveBtn.onclick = async () => {
                if (!wsRegions) return;
                
                // Get first region (we assume single region for cutting)
                const regions = wsRegions.getRegions();
                if (regions.length === 0) {
                    alert("Please select a region to cut.");
                    return;
                }
                
                const region = regions[0];
                const start = region.start;
                const end = region.end;

                if (end - start < 0.1) {
                    alert("Selection too short.");
                    return;
                }

                if (!confirm(`Create new audio from ${formatTime(start)}s to ${formatTime(end)}s?`)) return;

                showLoader('Cutting audio...');
                saveBtn.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('source_file', currentParams.url);
                    formData.append('start', start);
                    formData.append('end', end);
                    formData.append('entity_type', currentParams.entityType);
                    formData.append('entity_id', currentParams.entityId);
                    formData.append('parent_audio_id', currentParams.audioId);

                    const resp = await fetch('/audio_editor_api.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const res = await resp.json();
                    
                    if (res.status === 'ok') {
                        if(typeof Toast !== 'undefined') Toast.show('Audio cut created!', 'success');
                        window.AudioEditorModal.close();
                        // Reload playlist to show new item
                        if(typeof loadPlaylist === 'function') loadPlaylist();
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Request failed');
                } finally {
                    hideLoader();
                    saveBtn.disabled = false;
                }
            };
        }
    }
})();
</script>
JS;
    }
}