// daw/js/daw-playback.js
// Master playback engine — RAF loop, transport controls

'use strict';

async function dawPlayPause() { 
    if (window.Tone && Tone.context.state !== 'running') {
        try { await Tone.start(); } catch(e) { console.warn('Tone.start failed', e); }
    }
    STATE.isPlaying ? dawPause() : (stopPreview(), dawPlay()); 
}

function dawPlay() {
    if (STATE.isPlaying) return;
    STATE.isPlaying   = true;
    STATE.lastFrameTime = performance.now();
    updateTransportUI();
    STATE.rafId = requestAnimationFrame(playLoop);
}

function dawPause() {
    STATE.isPlaying = false;
    cancelAnimationFrame(STATE.rafId);
    updateTransportUI();
    STATE.clips.forEach(c => {
        if (c.isPlaying && c.ws) { c.ws.pause(); c.isPlaying = false; }
    });
}

function dawStop() {
    dawPause();
    seekTimeline(0);
}

function dawRewind() {
    const was = STATE.isPlaying;
    dawStop();
    if (was) setTimeout(dawPlay, 50);
}

function seekTimeline(timeSecs) {
    STATE.curTime = Math.max(0, timeSecs);
    _applyPlayheadPos();

    // Stop any clips that were playing at the old position
    STATE.clips.forEach(c => {
        if (c.isPlaying && c.ws) { c.ws.pause(); c.isPlaying = false; }
    });
}

function playLoop(now) {
    if (!STATE.isPlaying) return;

    const dt = (now - STATE.lastFrameTime) / 1000;
    STATE.lastFrameTime = now;
    STATE.curTime += dt;

    if (STATE.curTime >= STATE.projectDuration) { dawStop(); return; }

    _applyPlayheadPos();

    // Scroll playhead into view automatically
    const ph      = document.getElementById('playhead');
    const scroll  = document.getElementById('timelineScroll');
    if (ph && scroll) {
        // playhead left in the scroll viewport = TRACK_HEAD_W + curTime*zoom - scrollLeft
        const phVpX = TRACK_HEAD_W + STATE.curTime * STATE.masterZoom - scroll.scrollLeft;
        const vpW   = scroll.clientWidth;
        if (phVpX > vpW * 0.85) scroll.scrollLeft += vpW * 0.4;
    }

    // Trigger / stop clips
    STATE.clips.forEach(c => {
        if (!c.ws || !c.duration || !c.fullDuration) return;
        const end        = c.startTime + c.duration;
        const shouldPlay = STATE.curTime >= c.startTime && STATE.curTime < end;

        if (shouldPlay && !c.isPlaying) {
            c.isPlaying = true;
            // IMPORTANT FIX: Seek *first*, then play, to prevent buffer underrun crackle!
            const audioTime = (STATE.curTime - c.startTime) + (c.trimStart || 0);
            c.ws.seekTo(audioTime / c.fullDuration);
            c.ws.play();
        } else if (!shouldPlay && c.isPlaying) {
            c.isPlaying = false;
            c.ws.pause();
        }
    });

    STATE.rafId = requestAnimationFrame(playLoop);
}

function updateTransportUI() {
    const btn  = document.getElementById('btnPP');
    const icon = document.getElementById('ppIcon');
    if (btn)  btn.classList.toggle('playing', STATE.isPlaying);
    if (icon) icon.className = STATE.isPlaying ? 'bi bi-pause-fill' : 'bi bi-play-fill';
}

