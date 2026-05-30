<!-- Floating TTS Player Module -->
<!-- Inject this into any page to enable "Select Text -> Play" -->

<style>
    #tts-float-widget {
        position: fixed;
        bottom: 120px;
        left: 20px; /* Left side to avoid conflict with TOC/Scroll buttons */
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--card, #ffffff);
        border: 1px solid var(--border, #ccc);
        padding: 8px 12px;
        border-radius: 50px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: transform 0.2s, opacity 0.2s;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: var(--text, #333);
        backdrop-filter: blur(5px);
    }

    /* Minimized state */
    #tts-float-widget.minimized {
        width: 40px;
        height: 40px;
        padding: 0;
        justify-content: center;
        overflow: hidden;
        border-radius: 50%;
    }
    #tts-float-widget.minimized .tts-controls,
    #tts-float-widget.minimized .tts-status {
        display: none;
    }

    .tts-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 4px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text, #333);
        transition: background 0.2s;
    }
    .tts-btn:hover {
        background: rgba(125,125,125,0.1);
        color: var(--accent, #007bff);
    }
    .tts-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .tts-status {
        font-size: 0.8rem;
        color: var(--text-muted, #666);
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .tts-pulse {
        animation: tts-pulse-anim 1.5s infinite;
    }
    @keyframes tts-pulse-anim {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.8; }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* Loader Spinner */
    .tts-loader {
        border: 2px solid rgba(125,125,125,0.2);
        border-top: 2px solid var(--accent, #007bff);
        border-radius: 50%;
        width: 16px;
        height: 16px;
        animation: spin 1s linear infinite;
        display: none;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<div id="tts-float-widget">
    <!-- Icon to Drag/Minimize -->
    <div class="tts-btn" id="tts-grip" title="Click to minimize/expand" onclick="toggleTtsWidget()">
        🗣️
    </div>

    <div class="tts-controls" style="display:flex; align-items:center; gap:8px;">
        <div class="tts-status" id="tts-status-text">Select text & click play</div>
        
        <div class="tts-loader" id="tts-loader"></div>

        <button class="tts-btn" id="tts-play-btn" onclick="playSelectedText()" title="Read Selected Text">
            ▶️
        </button>
        
        <button class="tts-btn" id="tts-stop-btn" onclick="stopTts()" style="display:none;" title="Stop">
            ⏹️
        </button>
    </div>

    <audio id="tts-audio-player" style="display:none;" onended="resetTtsUI()"></audio>
</div>

<script>
    const ttsWidget = document.getElementById('tts-float-widget');
    const ttsStatus = document.getElementById('tts-status-text');
    const ttsLoader = document.getElementById('tts-loader');
    const playBtn = document.getElementById('tts-play-btn');
    const stopBtn = document.getElementById('tts-stop-btn');
    const audioPlayer = document.getElementById('tts-audio-player');
    
    let isMinimized = false;

    // Persist UI state if needed
    function toggleTtsWidget() {
        isMinimized = !isMinimized;
        if(isMinimized) {
            ttsWidget.classList.add('minimized');
        } else {
            ttsWidget.classList.remove('minimized');
        }
    }

    function resetTtsUI() {
        playBtn.style.display = 'flex';
        stopBtn.style.display = 'none';
        ttsLoader.style.display = 'none';
        ttsStatus.textContent = "Select text & click play";
        playBtn.disabled = false;
        ttsWidget.classList.remove('tts-pulse');
    }

    function stopTts() {
        audioPlayer.pause();
        audioPlayer.currentTime = 0;
        resetTtsUI();
    }

    async function playSelectedText() {
        const selection = window.getSelection().toString().trim();
        
        if (!selection) {
            ttsStatus.textContent = "No text selected!";
            setTimeout(() => ttsStatus.textContent = "Select text & click play", 2000);
            return;
        }

        if (selection.length > 500000) {
            ttsStatus.textContent = "Text too long (>500000 chars)";
            return;
        }

        // UI Loading State
        playBtn.style.display = 'none';
        stopBtn.style.display = 'none';
        ttsLoader.style.display = 'block';
        ttsStatus.textContent = "Generating audio...";
        ttsWidget.classList.add('tts-pulse');

        try {
            const response = await fetch('/api_tts_inline.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    text: selection,
                    model: 'en_US-libritts_r-medium' // You can make this configurable via a select box if desired
                })
            });

            const data = await response.json();

            if (data.status === 'success' && data.url) {
                ttsLoader.style.display = 'none';
                stopBtn.style.display = 'flex';
                ttsStatus.textContent = "Playing...";
                
                audioPlayer.src = data.url;
                audioPlayer.play().catch(e => {
                    console.error("Autoplay failed:", e);
                    ttsStatus.textContent = "Autoplay blocked";
                });
            } else {
                throw new Error(data.message || 'Unknown error');
            }

        } catch (error) {
            console.error('TTS Error:', error);
            resetTtsUI();
            ttsStatus.textContent = "Error generating audio";
        }
    }

    // Optional: Draggable Logic
    // Using simple logic similar to your MD player
    (function() {
        const el = document.getElementById('tts-float-widget');
        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        el.addEventListener('mousedown', (e) => {
            if(e.target.tagName === 'BUTTON') return; // Don't drag if clicking buttons
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            const rect = el.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;
            el.style.bottom = 'auto'; // Switch to top/left positioning
            el.style.right = 'auto';
            el.style.left = initialLeft + 'px';
            el.style.top = initialTop + 'px';
            el.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            el.style.left = (initialLeft + dx) + 'px';
            el.style.top = (initialTop + dy) + 'px';
        });

        window.addEventListener('mouseup', () => {
            isDragging = false;
            el.style.cursor = 'default';
        });
    })();
</script>
