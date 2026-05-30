// public/daw/js/daw-envelope.js
// Envelope plugin management — one EnvelopePlugin instance per clip.
// Points: clip.envelopePoints [{time, volume}] — independent of clip startTime.
// Enabling envelope mode RECREATES the WaveSurfer instance with interact:true
// and the EnvelopePlugin registered at creation, which is required for touch/
// mouse events to reach the plugin's SVG overlay.
// Disabling recreates back with interact:false (normal clip mode).

'use strict';

function _getEnvPlugin() {
    return (window.WaveSurfer && window.WaveSurfer.Envelope)
        ? window.WaveSurfer.Envelope
        : null;
}

/**
 * Recreate the WaveSurfer instance with interact:true and EnvelopePlugin.
 */
function attachEnvelope(clip) {
    const EnvPlugin = _getEnvPlugin();
    if (!EnvPlugin) { console.warn('WaveSurfer.Envelope not loaded'); return; }
    if (!clip.url) return;

    const track     = STATE.tracks.find(t => t.id === clip.trackId);
    const lineColor = track ? track.color : '#f59e0b';

    if (clip.ws) { clip.ws.pause(); clip.ws.destroy(); clip.ws = null; clip.isPlaying = false; }
    clip.envelope = null;

    const wsContainer = clip.el && clip.el.querySelector('.clip-ws');
    if (!wsContainer) return;

    wsContainer.classList.add('envelope-active');
    clip.el.classList.add('envelope-mode');

    const envPlugin = EnvPlugin.create({
        volume:        1.0,
        lineColor:     lineColor,
        lineWidth:     2,
        dragPointSize: 14,
        dragPointFill: lineColor,
        dragLineWidth: 2,
        points: (clip.envelopePoints && clip.envelopePoints.length)
            ? clip.envelopePoints.map(p => ({ time: p.time, volume: p.volume }))
            : [],
    });

    const ws = WaveSurfer.create({
        container:     wsContainer,
        url:           clip.url,
        waveColor:     clip.color,
        progressColor: clip.color + 'aa',
        height:        52,
        interact:      true,    // REQUIRED for envelope touch/mouse events
        normalize:     true,
        cursorWidth:   0,
        plugins:       [envPlugin],
    });

    ws.on('ready', () => {
        clip.ws       = ws;
        clip.envelope = envPlugin;
        clip.fullDuration = ws.getDuration();
        clip.el.style.width = (clip.duration * STATE.masterZoom) + 'px';
        
        const safeWsContainer = clip.el && clip.el.querySelector('.clip-ws');
        if (safeWsContainer) {
            safeWsContainer.style.width = (clip.fullDuration * STATE.masterZoom) + 'px';
            safeWsContainer.style.transform = `translateX(-${(clip.trimStart || 0) * STATE.masterZoom}px)`;
        }

        ws.zoom(STATE.masterZoom);
        updateAllTrackVolumes();
        if (typeof connectClipToTrack === 'function') connectClipToTrack(clip);
        _watchEnvelopePoints(clip, envPlugin);
    });

    // Stop envelope interaction from bubbling up to clip-drag / timeline seek
    wsContainer.addEventListener('click',     e => e.stopPropagation(), true);
    wsContainer.addEventListener('mousedown', e => e.stopPropagation(), true);
    wsContainer.addEventListener('touchstart',e => e.stopPropagation(), {capture: true, passive: false});
}

/**
 * Sync envelope points back to clip.envelopePoints.
 * EnvelopePlugin v7 has no reliable top-level change event so we poll on
 * pointer-up and on a 500ms interval while envelope is active.
 */
function _watchEnvelopePoints(clip, envPlugin) {
    function sync() {
        try {
            const pts = envPlugin.getPoints();
            if (pts) clip.envelopePoints = pts.map(p => ({ time: p.time, volume: p.volume }));
        } catch(e) {}
    }

    const wsContainer = clip.el && clip.el.querySelector('.clip-ws');
    if (wsContainer) {
        wsContainer.addEventListener('pointerup', sync);
        wsContainer.addEventListener('click',     sync);
    }

    clip._envSyncInterval = setInterval(() => {
        if (clip.envelope === envPlugin) sync();
        else clearInterval(clip._envSyncInterval);
    }, 500);
}

/**
 * Recreate the WaveSurfer instance back to normal interact:false, no plugin.
 * Points are preserved in clip.envelopePoints.
 */
function detachEnvelope(clip) {
    if (clip.envelope) {
        try {
            const pts = clip.envelope.getPoints();
            if (pts) clip.envelopePoints = pts.map(p => ({ time: p.time, volume: p.volume }));
        } catch(e) {}
    }

    if (clip._envSyncInterval) { clearInterval(clip._envSyncInterval); clip._envSyncInterval = null; }

    if (clip.ws) { clip.ws.pause(); clip.ws.destroy(); clip.ws = null; clip.isPlaying = false; }
    clip.envelope = null;

    const wsContainer = clip.el && clip.el.querySelector('.clip-ws');
    if (!wsContainer || !clip.url) return;

    wsContainer.classList.remove('envelope-active');
    clip.el.classList.remove('envelope-mode');

    const ws = WaveSurfer.create({
        container:     wsContainer,
        url:           clip.url,
        waveColor:     clip.color,
        progressColor: clip.color + 'aa',
        height:        52,
        interact:      false,
        normalize:     true,
        cursorWidth:   0,
    });

    ws.on('ready', () => {
        clip.ws       = ws;
        clip.fullDuration = ws.getDuration();
        clip.el.style.width = (clip.duration * STATE.masterZoom) + 'px';
        
        const safeWsContainer = clip.el && clip.el.querySelector('.clip-ws');
        if (safeWsContainer) {
            safeWsContainer.style.width = (clip.fullDuration * STATE.masterZoom) + 'px';
            safeWsContainer.style.transform = `translateX(-${(clip.trimStart || 0) * STATE.masterZoom}px)`;
        }

        ws.zoom(STATE.masterZoom);
        updateAllTrackVolumes();
        updateMasterLayout();
        if (typeof connectClipToTrack === 'function') connectClipToTrack(clip);
    });
}

/**
 * Called by the View select in the envelope tab.
 * "Envelope" = attach plugin (interactive, affects audio).
 * "Waveform"  = detach plugin, recreate plain WaveSurfer (points preserved).
 * The select is purely a view switch — audio follows the view state honestly.
 */
function setTrackEnvelopeMode(trackId, enabled, projectKey) {
    const track = STATE.tracks.find(t => t.id === trackId);
    if (!track) return;
    track.envelopeMode = enabled;

    const lsKey = 'daw_env_mode_' + (projectKey || 'default') + '_' + trackId;
    try { localStorage.setItem(lsKey, enabled ? '1' : '0'); } catch(e) {}

    STATE.clips.filter(c => c.trackId === trackId).forEach(c => {
        if (enabled) {
            if (c.ws && !c.envelope) attachEnvelope(c);
            else if (!c.ws) c._pendingEnvelope = true;
        } else {
            c._pendingEnvelope = false;
            if (c.envelope || c.ws) detachEnvelope(c);
        }
    });

    _refreshEnvelopeSelect(trackId, enabled);
}

function _refreshEnvelopeSelect(trackId, enabled) {
    const sel = document.getElementById('env-select-' + trackId);
    if (sel) sel.value = enabled ? 'envelope' : 'wave';
}

/**
 * Restore envelope modes from localStorage after project load.
 */
function restoreEnvelopeModes(projectKey) {
    STATE.tracks.forEach(t => {
        const lsKey = 'daw_env_mode_' + (projectKey || 'default') + '_' + t.id;
        let stored = null;
        try { stored = localStorage.getItem(lsKey); } catch(e) {}
        const enabled = stored === '1';
        t.envelopeMode = enabled;
        if (enabled) {
            STATE.clips.filter(c => c.trackId === t.id).forEach(c => {
                if (c.ws && !c.envelope) attachEnvelope(c);
            });
        }
        _refreshEnvelopeSelect(t.id, enabled);
    });
}

/** Project key from the save/load modal select */
function _envProjectKey() {
    const sel = document.getElementById('fileProjectSelect');
    return (sel && sel.value) ? 'proj_' + sel.value : 'default';
}