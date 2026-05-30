// public/daw/js/daw-tracks.js
// Track lane and clip CRUD operations, Save/Load serialization, Track FX

'use strict';

// ─── Default Track Plugin State ─────────────────────────────────────────────
function _getDefaultPluginState() {
    return {
        gain:       { gainDb: 0 },
        volume:     { volumeDb: 0 },
        compressor: { threshold: -24, ratio: 4, attack: 10, release: 100, knee: 6 },
        eq3band:    { low: 0, mid: 0, high: 0 },
        limiter:    { ceiling: -0.3, release: 50 }
    };
}

// ─── Tone.js Initialization for Track ───────────────────────────────────────
function _getTrackToneNode(track, pid) {
    if (track.nodes[pid]) return track.nodes[pid]; // Already instantiated!
    
    // Lazy instantion purely on demand
    const s = track.pluginState;
    if (pid === 'gain') track.nodes.gain = new Tone.Gain(Tone.dbToGain(s.gain.gainDb));
    else if (pid === 'volume') track.nodes.volume = new Tone.Volume(s.volume.volumeDb);
    else if (pid === 'compressor') track.nodes.compressor = new Tone.Compressor({
        threshold: s.compressor.threshold,
        ratio: s.compressor.ratio,
        attack: s.compressor.attack / 1000,
        release: s.compressor.release / 1000,
        knee: s.compressor.knee
    });
    else if (pid === 'eq3band') track.nodes.eq3band = new Tone.EQ3({
        low: s.eq3band.low,
        mid: s.eq3band.mid,
        high: s.eq3band.high
    });
    else if (pid === 'limiter') track.nodes.limiter = new Tone.Limiter(s.limiter.ceiling);
    
    return track.nodes[pid];
}

function _initTrackTone(track) {
    if (!window.Tone) return;
    if (!MASTER.inputNode) initToneFoundation();

    track.inputNode = new Tone.Gain(1);
    track.outNode   = new Tone.Gain(1);
    track.nodes     = {}; // Empty strictly until requested!

    _rebuildTrackToneChain(track);
    track.outNode.connect(MASTER.inputNode);
}

function _rebuildTrackToneChain(track) {
    if (!track.inputNode) return;
    
    track.inputNode.disconnect();
    Object.values(track.nodes).forEach(n => n.disconnect());
    
    let current = track.inputNode;
    track.fxChain.forEach(pid => {
        const node = _getTrackToneNode(track, pid);
        if (node) {
            current.connect(node);
            current = node;
        }
    });
    current.connect(track.outNode);
}

// ─── Route Clip to Track ────────────────────────────────────────────────────
function connectClipToTrack(clip) {
    if (!window.Tone) return;
    const track = STATE.tracks.find(t => t.id === clip.trackId);
    if (!track) return;
    
    if (!track.inputNode) _initTrackTone(track);

    if (clip.ws) {
        const mediaEl = clip.ws.getMediaElement();
        if (!mediaEl) return;
        
        try {
            if (clip.mediaEl !== mediaEl) {
                if (clip.mediaSource) clip.mediaSource.disconnect();
                try { mediaEl.crossOrigin = "anonymous"; } catch(e){}
                clip.mediaSource = Tone.context.createMediaElementSource(mediaEl);
                clip.mediaEl = mediaEl;
            }
            clip.mediaSource.disconnect();
            
            // Native nodes don't natively understand Tone.js objects, connect to native .input
            const dest = track.inputNode.input || track.inputNode;
            clip.mediaSource.connect(dest);
        } catch (e) {
            console.warn('Could not route clip to Track Tone.js bus', e);
        }
    }
}

// ─── Tab state per track ────────────────────────────────────────────────────
function _trackTabKey(trackId) { return 'daw_tab_' + trackId; }
function _getTrackTab(trackId) {
    try { return localStorage.getItem(_trackTabKey(trackId)) || 'main'; } catch(e) { return 'main'; }
}
function _setTrackTab(trackId, tab) {
    try { localStorage.setItem(_trackTabKey(trackId), tab); } catch(e) {}
}

function switchTrackTab(trackId, tab) {
    _setTrackTab(trackId, tab);

    const mainPane = document.getElementById('track-tab-main-' + trackId);
    const envPane  = document.getElementById('track-tab-env-' + trackId);
    const fxPane   = document.getElementById('track-tab-fx-'  + trackId);
    
    const dot0     = document.getElementById('track-dot-0-' + trackId);
    const dot1     = document.getElementById('track-dot-1-' + trackId);
    const dot2     = document.getElementById('track-dot-2-' + trackId);

    if (mainPane) mainPane.style.display = tab === 'main' ? '' : 'none';
    if (envPane)  envPane.style.display  = tab === 'env'  ? '' : 'none';
    if (fxPane)   fxPane.style.display   = tab === 'fx'   ? 'flex' : 'none';
    
    if (dot0) dot0.classList.toggle('active', tab === 'main');
    if (dot1) dot1.classList.toggle('active', tab === 'env');
    if (dot2) dot2.classList.toggle('active', tab === 'fx');
}

// ─── Track FX Fullscreen Modal Logic ─────────────────────────────────────────
let CURRENT_FX_TRACK_ID = null;

function openTrackFxModal(trackId) {
    const track = STATE.tracks.find(t => t.id === trackId);
    if (!track) return;
    if (!track.inputNode) _initTrackTone(track);
    
    CURRENT_FX_TRACK_ID = trackId;
    document.getElementById('trackFxTitle').textContent = track.name + ' - FX';
    document.getElementById('trackFxBackdrop').classList.add('open');
    
    _syncTrackVolSlider();
    _renderTrackFxChain();
    _renderTrackPluginUi(track.activePlugin);
}

function closeTrackFxModal() {
    document.getElementById('trackFxBackdrop').classList.remove('open');
    CURRENT_FX_TRACK_ID = null;
}

function onTrackFxBackdropClick(e) {
    if (e.target === document.getElementById('trackFxBackdrop')) closeTrackFxModal();
}

// Track Modal Volume
function _syncTrackVolSlider() {
    if (CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);
    if (!track) return;
    const slider = document.getElementById('trackVolSlider');
    const label  = document.getElementById('trackVolLabel');
    if (slider) slider.value = track.vol;
    if (label)  label.textContent = Math.round(track.vol * 100) + '%';
}

function setTrackFxVol(val) {
    if (CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);
    if (!track) return;
    track.vol = parseFloat(val);
    const label = document.getElementById('trackVolLabel');
    if (label) label.textContent = Math.round(track.vol * 100) + '%';
    
    // Sync the lane slider too
    const laneSlider = document.querySelector('#track-' + track.id + ' .track-vol');
    if (laneSlider) laneSlider.value = track.vol;
    
    updateAllTrackVolumes();
}

// Track FX Chain UI
function _renderTrackFxChain() {
    if (CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);
    const chain = document.getElementById('trackFxChain');
    if (!track || !chain) return;

    let html = track.fxChain.map((pid, idx) => {
        const plug = MASTER_PLUGINS.find(p => p.id === pid);
        const lbl  = plug ? plug.label : pid;
        const ico  = plug ? plug.icon  : 'bi-plug';
        const active = pid === track.activePlugin ? ' mc-slot--active' : '';
        return `<div class="mc-slot${active}" onclick="selectTrackPlugin('${pid}')">
            <i class="bi ${ico} mc-slot-icon"></i>
            <span class="mc-slot-lbl">${lbl}</span>
            <button class="mc-slot-del" onclick="removeTrackPlugin(${idx},event)" title="Remove"><i class="bi bi-x"></i></button>
        </div>`;
    }).join('');

    html += `<div class="mc-slot mc-slot--add" onclick="toggleTrackPluginPicker(event)">
        <i class="bi bi-plus-lg mc-slot-icon" style="color:var(--amber);"></i>
        <span class="mc-slot-lbl" style="color:var(--text-dim);">Add Plugin</span>
    </div>`;

    chain.innerHTML = html;
    
    const picker = document.getElementById('trackPluginPicker');
    if (picker) {
        picker.innerHTML = MASTER_PLUGINS.map(p =>
            `<div class="mc-picker-item" onclick="addTrackPlugin('${p.id}')">
                <i class="bi ${p.icon}"></i> ${p.label}
            </div>`
        ).join('');
    }
}

function toggleTrackPluginPicker(e) {
    if (e) { e.stopPropagation(); e.preventDefault(); }
    const picker = document.getElementById('trackPluginPicker');
    if (!picker) return;
    picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
}

function addTrackPlugin(pluginId) {
    if (CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);
    if (!track) return;
    if (!track.inputNode) _initTrackTone(track);

    if (!track.fxChain.includes(pluginId)) {
        track.fxChain.push(pluginId);
        _rebuildTrackToneChain(track);
    }
    track.activePlugin = pluginId;
    _renderTrackFxChain();
    _renderTrackPluginUi(pluginId);
    
    const picker = document.getElementById('trackPluginPicker');
    if (picker) picker.style.display = 'none';
}

function removeTrackPlugin(idx, e) {
    e.stopPropagation();
    if (CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);
    if (!track) return;
    
    const removed = track.fxChain.splice(idx, 1)[0];
    if (track.activePlugin === removed) {
        track.activePlugin = track.fxChain[0] || null;
    }
    _rebuildTrackToneChain(track);
    _renderTrackFxChain();
    _renderTrackPluginUi(track.activePlugin);
}

function selectTrackPlugin(pluginId) {
    if (CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);
    if (track) {
        track.activePlugin = pluginId;
        _renderTrackFxChain();
        _renderTrackPluginUi(pluginId);
    }
}

function _renderTrackPluginUi(pluginId) {
    const area = document.getElementById('trackPluginArea');
    if (!area || CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);

    if (!pluginId || !track.fxChain.includes(pluginId)) {
        area.innerHTML = `<div class="mc-plugin-empty">
            <i class="bi bi-plug" style="font-size:2rem;opacity:.2;"></i>
            <div style="margin-top:10px;font-size:.75rem;color:var(--text-faint);">Select or add a plugin from the FX chain below</div>
        </div>`;
        return;
    }

    const s = track.pluginState[pluginId];
    
    if (pluginId === 'gain') {
        area.innerHTML = `<div class="mc-plugin-panel">
            <div class="mc-plugin-title"><i class="bi bi-plus-slash-minus"></i> Gain</div>
            ${_trackParamSlider('Gain (dB)', 'gainDb', s.gainDb, -24, 24, 0.5, ' dB')}
            <div class="mc-plugin-note">Track output gain adjustment.</div>
        </div>`;
    } else if (pluginId === 'volume') {
        area.innerHTML = `<div class="mc-plugin-panel">
            <div class="mc-plugin-title"><i class="bi bi-speaker"></i> Volume</div>
            ${_trackParamSlider('Vol (dB)', 'volDb', s.volumeDb, -60, 12, 0.5, ' dB')}
        </div>`;
    } else if (pluginId === 'compressor') {
        area.innerHTML = `<div class="mc-plugin-panel">
            <div class="mc-plugin-title"><i class="bi bi-activity"></i> Compressor</div>
            ${_trackParamSlider('Threshold', 'compThreshold', s.threshold, -60, 0,  0.5, ' dB')}
            ${_trackParamSlider('Ratio',     'compRatio',     s.ratio,     1,   20, 0.5, ':1')}
            ${_trackParamSlider('Attack',    'compAttack',    s.attack,    0,   200, 1,  ' ms')}
            ${_trackParamSlider('Release',   'compRelease',   s.release,   10,  2000,10, ' ms')}
            ${_trackParamSlider('Knee',      'compKnee',      s.knee,      0,   40,  1,  ' dB')}
        </div>`;
    } else if (pluginId === 'eq3band') {
        area.innerHTML = `<div class="mc-plugin-panel">
            <div class="mc-plugin-title"><i class="bi bi-bar-chart-steps"></i> EQ 3-Band</div>
            ${_trackParamSlider('Low (80 Hz)',  'eqLow',  s.low,  -15, 15, 0.5, ' dB')}
            ${_trackParamSlider('Mid (1 kHz)', 'eqMid',  s.mid,  -15, 15, 0.5, ' dB')}
            ${_trackParamSlider('High (8 kHz)','eqHigh', s.high, -15, 15, 0.5, ' dB')}
        </div>`;
    } else if (pluginId === 'limiter') {
        area.innerHTML = `<div class="mc-plugin-panel">
            <div class="mc-plugin-title"><i class="bi bi-shield-shaded"></i> Limiter</div>
            ${_trackParamSlider('Ceiling', 'limCeiling', s.ceiling, -12, 0,   0.1, ' dB')}
            ${_trackParamSlider('Release', 'limRelease', s.release, 10,  500, 5,   ' ms')}
        </div>`;
    }
}

function _trackParamSlider(label, id, val, min, max, step, unit) {
    return `<div class="mc-param-row">
        <label class="mc-param-lbl">${label}</label>
        <input type="range" class="mc-param-slider" id="tk_slider_${id}"
            min="${min}" max="${max}" step="${step}" value="${val}"
            oninput="document.getElementById('tk_val_${id}').textContent=parseFloat(this.value)+'${unit}'; _trackParamInput('${id}', this.value);">
        <span class="mc-param-val" id="tk_val_${id}">${val}${unit}</span>
    </div>`;
}

function _trackParamInput(id, rawVal) {
    if (CURRENT_FX_TRACK_ID === null) return;
    const track = STATE.tracks.find(t => t.id === CURRENT_FX_TRACK_ID);
    if (!track) return;
    const v = parseFloat(rawVal);
    
    const map = {
        compThreshold: () => { track.pluginState.compressor.threshold = v; if (track.nodes?.compressor) track.nodes.compressor.threshold.value = v; },
        compRatio:     () => { track.pluginState.compressor.ratio     = v; if (track.nodes?.compressor) track.nodes.compressor.ratio.value = v; },
        compAttack:    () => { track.pluginState.compressor.attack    = v; if (track.nodes?.compressor) track.nodes.compressor.attack.value = v / 1000; },
        compRelease:   () => { track.pluginState.compressor.release   = v; if (track.nodes?.compressor) track.nodes.compressor.release.value = v / 1000; },
        compKnee:      () => { track.pluginState.compressor.knee      = v; if (track.nodes?.compressor) track.nodes.compressor.knee.value = v; },
        
        gainDb:        () => { track.pluginState.gain.gainDb          = v; if (track.nodes?.gain) track.nodes.gain.gain.value = Tone.dbToGain(v); },
        volDb:         () => { track.pluginState.volume.volumeDb      = v; if (track.nodes?.volume) track.nodes.volume.volume.value = v; },

        eqLow:         () => { track.pluginState.eq3band.low          = v; if (track.nodes?.eq3band) track.nodes.eq3band.low.value = v; },
        eqMid:         () => { track.pluginState.eq3band.mid          = v; if (track.nodes?.eq3band) track.nodes.eq3band.mid.value = v; },
        eqHigh:        () => { track.pluginState.eq3band.high         = v; if (track.nodes?.eq3band) track.nodes.eq3band.high.value = v; },

        limCeiling:    () => { track.pluginState.limiter.ceiling      = v; if (track.nodes?.limiter) track.nodes.limiter.threshold.value = v; },
        limRelease:    () => { track.pluginState.limiter.release      = v; },
    };
    if (map[id]) map[id]();
}


// ─── Add Track ───────────────────────────────────────────────────────────────
function addTrackLane(name = 'Audio Track') {
    const id    = ++STATE.trackIdSeq;
    const color = nextColor();
    
    const track = { 
        id, name, color, vol: 1, muted: false, solo: false, envelopeMode: false,
        fxChain: [], pluginState: _getDefaultPluginState(), activePlugin: null,
        inputNode: null, outNode: null, nodes: {}
    };
    STATE.tracks.push(track);

    const row = document.createElement('div');
    row.className = 'daw-track';
    row.id = 'track-' + id;
    
    row.innerHTML = `
        <div class="track-head">

            <!-- Tab dot navigation -->
            <div class="track-tabs">
                <span class="track-tab-dot active" id="track-dot-0-${id}" onclick="switchTrackTab(${id},'main')" title="Main"></span>
                <span class="track-tab-dot"         id="track-dot-1-${id}" onclick="switchTrackTab(${id},'env')"  title="Envelope"></span>
                <span class="track-tab-dot"         id="track-dot-2-${id}" onclick="switchTrackTab(${id},'fx')"   title="Track FX"></span>
            </div>

            <!-- Tab 0: Main controls -->
            <div id="track-tab-main-${id}">
                <div class="track-head-top">
                    <div class="track-color-strip" style="background:${color};"></div>
                    <div class="track-name" title="${esc(name)}">${esc(name)}</div>
                    <div class="track-btns">
                        <button class="tk-btn" id="solo-${id}" onclick="toggleSolo(${id})" title="Solo">S</button>
                        <button class="tk-btn" id="mute-${id}" onclick="toggleMute(${id})" title="Mute">M</button>
                        <button class="tk-btn tk-del" onclick="removeTrack(${id})" title="Remove Track"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="track-vol-wrap">
                    <i class="bi bi-volume-up" style="color:var(--text-dim);font-size:10px;"></i>
                    <input type="range" class="track-vol" min="0" max="1" step="0.01" value="1" oninput="setTrackVol(${id}, this.value)">
                </div>
            </div>

            <!-- Tab 1: Envelope controls -->
            <div id="track-tab-env-${id}" style="display:none;">
                <div class="track-head-top" style="margin-bottom:4px;">
                    <i class="bi bi-bezier2" style="color:var(--teal);font-size:11px;margin-right:4px;"></i>
                    <div class="track-name" style="font-size:.65rem;color:var(--text-dim);">Envelope</div>
                </div>
                <div class="track-env-row">
                    <label class="track-env-lbl">View</label>
                    <select class="track-env-select" id="env-select-${id}"
                        onchange="setTrackEnvelopeMode(${id}, this.value==='envelope', _envProjectKey())">
                        <option value="wave">Waveform</option>
                        <option value="envelope">Envelope</option>
                    </select>
                </div>
            </div>

            <!-- Tab 2: Track FX -->
            <div id="track-tab-fx-${id}" style="display:none; padding-top:4px; align-items:center;">
                <button class="pm-btn pm-btn-cancel" style="width:100%; justify-content:center; border-color:var(--amber-border); color:var(--amber);" onclick="openTrackFxModal(${id})">
                    <i class="bi bi-sliders"></i> Open Track FX
                </button>
            </div>

        </div>
        <div class="track-lane" id="lane-${id}"></div>
    `;
    document.getElementById('dawTimelineContent').appendChild(row);

    // Tone.js initialization for track
    _initTrackTone(track);

    // Restore tab state from localStorage
    const savedTab = _getTrackTab(id);
    if (savedTab !== 'main') switchTrackTab(id, savedTab);

    updateMasterLayout();
    return id;
}

// ─── Track Volume / Mute / Solo ──────────────────────────────────────────────
function updateAllTrackVolumes() {
    const anySolo   = STATE.tracks.some(t => t.solo);

    STATE.tracks.forEach(t => {
        const elMute = document.getElementById('mute-' + t.id);
        const elSolo = document.getElementById('solo-' + t.id);
        if (elMute) elMute.classList.toggle('muted', t.muted);
        if (elSolo) elSolo.classList.toggle('soloed', t.solo);

        let effectiveVol = t.vol;
        if (t.muted)                 effectiveVol = 0;
        else if (anySolo && !t.solo) effectiveVol = 0;

        if (window.Tone && t.outNode) {
            // Tone.js output node acts as the absolute gatekeeper for the track
            // Using setTargetAtTime to prevent clicking noises during instant volume changes
            t.outNode.gain.setTargetAtTime(effectiveVol, Tone.now(), 0.015);
        } else {
            // Fallback if Tone is not running
            STATE.clips.filter(c => c.trackId === t.id).forEach(c => {
                if (c.ws) c.ws.setVolume(effectiveVol);
            });
        }
    });
    
    // Sync Tone Master Volume if Tone is handling output
    if (typeof MASTER !== 'undefined' && MASTER.outNode && window.Tone) {
        MASTER.outNode.volume.value = Tone.gainToDb(Math.max(0.0001, MASTER.vol));
    }
}

// ─── Add Clip ────────────────────────────────────────────────────────────────
function addClip(trackId, url, name, startTime = 0, envelopePoints = null, trimStart = 0, duration = null) {
    const track = STATE.tracks.find(t => t.id === trackId);
    if (!track) return;

    const clipId = ++STATE.clipIdSeq;
    const clip   = {
        id: clipId, trackId, url, name, color: track.color,
        startTime, duration: duration || 1, trimStart: trimStart || 0, fullDuration: duration || 1,
        ws: null, isPlaying: false, el: null,
        envelope: null, envelopePoints: envelopePoints || [],
        _pendingDuration: duration,
    };
    STATE.clips.push(clip);

    const lane = document.getElementById('lane-' + trackId);
    const el   = document.createElement('div');
    el.className = 'daw-clip';
    el.id = 'clip-' + clipId;
    el.style.left  = (startTime * STATE.masterZoom) + 'px';
    el.style.width = (clip.duration * STATE.masterZoom) + 'px';
    el.innerHTML = `
        <div class="clip-header">
            <div style="flex:1;overflow:hidden;text-overflow:ellipsis;">${esc(name)}</div>
            <i class="bi bi-x clip-del" onclick="removeClip(${clipId}, event)" title="Remove Clip"></i>
        </div>
        <div class="clip-ws"></div>
    `;

    el.addEventListener('mousedown', (e) => startClipDrag(e, clip));
    el.addEventListener('touchstart', (e) => startClipDrag(e, clip), {passive: false});

    lane.appendChild(el);
    clip.el = el;

    const wsContainer = el.querySelector('.clip-ws');
    
    const ws = WaveSurfer.create({
        container:     wsContainer,
        url:           url,
        waveColor:     track.color,
        progressColor: track.color + 'aa',
        height:        52,
        interact:      false,
        normalize:     true,
        cursorWidth:   0,
    });

    ws.on('ready', () => {
        clip.ws       = ws;
        clip.fullDuration = ws.getDuration();
        if (clip._pendingDuration === null) clip.duration = clip.fullDuration;
        el.style.width = (clip.duration * STATE.masterZoom) + 'px';
        
        wsContainer.style.width = (clip.fullDuration * STATE.masterZoom) + 'px';
        wsContainer.style.transform = `translateX(-${clip.trimStart * STATE.masterZoom}px)`;
        
        ws.zoom(STATE.masterZoom);
        updateAllTrackVolumes();
        updateMasterLayout();
        connectClipToTrack(clip);
        // Attach envelope if track is in envelope mode OR was flagged pending
        if (track.envelopeMode || clip._pendingEnvelope) {
            clip._pendingEnvelope = false;
            attachEnvelope(clip);
        }
    });

    // History: record addClip (skip during undo/redo replay)
    if (!_historyGuard()) {
        historyPush(
            'Add clip "' + name + '"',
            () => removeClipInternal(clipId),         // undo = remove
            () => addClip(trackId, url, name, startTime, clip.envelopePoints, trimStart, duration) // redo = re-add
        );
    }
    
    return clipId;
}

function addTrackFromAsset(assetIdx) {
    const a = STATE.assetData[assetIdx];
    if (!a?.filename) return;
    stopPreview();

    if (!_historyGuard()) {
        // We'll push a compound "add track + clip" entry
        const trackId = addTrackLaneNoHistory(a.name);
        addClipNoHistory(trackId, a.filename, a.name, 0);
        const tid = trackId;
        historyPush(
            'Add track "' + a.name + '"',
            () => removeTrack(tid),
            () => {
                const nid = addTrackLaneNoHistory(a.name);
                addClipNoHistory(nid, a.filename, a.name, 0);
            }
        );
    } else {
        const trackId = addTrackLane(a.name);
        addClip(trackId, a.filename, a.name, 0);
    }
    Toast.show('Added: ' + trunc(a.name, 24), 'success');
}

// Internal no-history variants used by history replay paths
function addTrackLaneNoHistory(name) {
    HISTORY._skipPush = true;
    try { return addTrackLane(name); } finally { HISTORY._skipPush = false; }
}
function addClipNoHistory(trackId, url, name, startTime, pts, trimStart, duration) {
    HISTORY._skipPush = true;
    try { return addClip(trackId, url, name, startTime, pts || [], trimStart, duration); } finally { HISTORY._skipPush = false; }
}

// ─── Replace Clip Audio ──────────────────────────────────────────────────────
function replaceClipAudio(clipId, assetIdx) {
    const a = STATE.assetData[assetIdx];
    if (!a?.filename) return;
    const clip = STATE.clips.find(c => c.id === clipId);
    if (!clip || !clip.el) return;

    const prevUrl  = clip.url;
    const prevName = clip.name;

    stopPreview();
    _doReplaceClipAudio(clip, a.filename, a.name);

    if (!_historyGuard()) {
        historyPush(
            'Replace clip audio "' + a.name + '"',
            () => { const c = STATE.clips.find(x => x.id === clipId); if (c) _doReplaceClipAudio(c, prevUrl, prevName); },
            () => { const c = STATE.clips.find(x => x.id === clipId); if (c) _doReplaceClipAudio(c, a.filename, a.name); }
        );
    }
    Toast.show('Replaced with: ' + trunc(a.name, 24), 'success');
}

function _doReplaceClipAudio(clip, url, name) {
    clip.name = name;
    clip.url  = url;
    clip.trimStart = 0;
    clip._pendingDuration = null;
    
    const headerDiv = clip.el && clip.el.querySelector('.clip-header div');
    if (headerDiv) headerDiv.textContent = name;

    // Snapshot envelope points then destroy directly (don't recreate via detachEnvelope)
    if (clip.envelope) {
        try {
            const pts = clip.envelope.getPoints();
            if (pts) clip.envelopePoints = pts.map(p => ({ time: p.time, volume: p.volume }));
        } catch(e) {}
        clip.envelope = null;
    }
    if (clip._envSyncInterval) { clearInterval(clip._envSyncInterval); clip._envSyncInterval = null; }
    if (clip.ws) { clip.ws.pause(); clip.ws.destroy(); clip.ws = null; clip.isPlaying = false; }

    const wsContainer = clip.el && clip.el.querySelector('.clip-ws');
    if (wsContainer) {
        wsContainer.classList.remove('envelope-active');
        clip.el.classList.remove('envelope-mode');
    }

    const track = STATE.tracks.find(t => t.id === clip.trackId);
    const ws = WaveSurfer.create({
        container:     clip.el.querySelector('.clip-ws'),
        url:           url,
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
        clip.duration = clip.fullDuration;
        clip.el.style.width = (clip.duration * STATE.masterZoom) + 'px';
        
        const safeWsContainer = clip.el.querySelector('.clip-ws');
        if (safeWsContainer) {
            safeWsContainer.style.width = (clip.fullDuration * STATE.masterZoom) + 'px';
            safeWsContainer.style.transform = `translateX(0px)`;
        }
        
        ws.zoom(STATE.masterZoom);
        updateAllTrackVolumes();
        updateMasterLayout();
        connectClipToTrack(clip);
        if (track && track.envelopeMode) attachEnvelope(clip);
    });
}

// ─── Remove Track / Clip ─────────────────────────────────────────────────────
function removeTrack(id) {
    const track = STATE.tracks.find(t => t.id === id);
    if (!track) return;

    // Snapshot clips for potential undo
    const clipsOnTrack = STATE.clips
        .filter(c => c.trackId === id)
        .map(c => ({ url: c.url, name: c.name, startTime: c.startTime, envelopePoints: [...(c.envelopePoints || [])], trimStart: c.trimStart, duration: c.duration }));
    const trackSnap = { name: track.name, color: track.color, vol: track.vol, muted: track.muted, solo: track.solo };

    [...STATE.clips].filter(c => c.trackId === id).forEach(c => removeClipInternal(c.id));
    const idx = STATE.tracks.findIndex(t => t.id === id);
    if (idx > -1) STATE.tracks.splice(idx, 1);
    const el = document.getElementById('track-' + id);
    if (el) el.remove();
    
    // Dispose Tone nodes to prevent memory leaks!
    if (track.inputNode) {
        try {
            track.inputNode.dispose();
            track.outNode.dispose();
            Object.values(track.nodes).forEach(n => n.dispose());
        } catch(e) { console.warn('Tone node cleanup error', e); }
    }

    updateAllTrackVolumes();
    updateMasterLayout();

    if (!_historyGuard()) {
        historyPush(
            'Remove track "' + trackSnap.name + '"',
            () => {
                // Undo: recreate track + clips
                const nid = addTrackLaneNoHistory(trackSnap.name);
                const nt  = STATE.tracks.find(t => t.id === nid);
                if (nt) {
                    nt.color = trackSnap.color; nt.vol = trackSnap.vol;
                    nt.muted = trackSnap.muted; nt.solo = trackSnap.solo;
                    const strip = document.querySelector('#track-' + nid + ' .track-color-strip');
                    const vol   = document.querySelector('#track-' + nid + ' .track-vol');
                    if (strip) strip.style.background = nt.color;
                    if (vol)   vol.value = nt.vol;
                }
                clipsOnTrack.forEach(cs => addClipNoHistory(nid, cs.url, cs.name, cs.startTime, cs.envelopePoints, cs.trimStart, cs.duration));
                updateAllTrackVolumes();
            },
            () => {
                // Redo: remove again (find by name since id will differ)
                const t = STATE.tracks.find(t => t.name === trackSnap.name);
                if (t) removeTrack(t.id);
            }
        );
    }
}

function removeClip(id, event = null) {
    if (event) { event.stopPropagation(); event.preventDefault(); }
    const clip = STATE.clips.find(c => c.id === id);
    if (!clip) return;

    const snap = { trackId: clip.trackId, url: clip.url, name: clip.name, startTime: clip.startTime, envelopePoints: [...(clip.envelopePoints || [])], trimStart: clip.trimStart, duration: clip.duration };
    removeClipInternal(id);
    updateMasterLayout();

    if (!_historyGuard()) {
        historyPush(
            'Remove clip "' + snap.name + '"',
            () => addClipNoHistory(snap.trackId, snap.url, snap.name, snap.startTime, snap.envelopePoints, snap.trimStart, snap.duration),
            () => { const c = STATE.clips.find(c => c.startTime === snap.startTime && c.trackId === snap.trackId); if (c) removeClipInternal(c.id); updateMasterLayout(); }
        );
    }
}

function removeClipInternal(id) {
    const idx = STATE.clips.findIndex(c => c.id === id);
    if (idx === -1) return;
    const clip = STATE.clips[idx];
    
    if (clip.mediaSource) {
        try { clip.mediaSource.disconnect(); } catch(e){}
    }

    // Snapshot envelope points then destroy without recreating
    if (clip.envelope) {
        try {
            const pts = clip.envelope.getPoints();
            if (pts) clip.envelopePoints = pts.map(p => ({ time: p.time, volume: p.volume }));
        } catch(e) {}
        clip.envelope = null;
    }
    if (clip._envSyncInterval) { clearInterval(clip._envSyncInterval); clip._envSyncInterval = null; }
    if (clip.ws) { clip.ws.pause(); clip.ws.destroy(); clip.ws = null; }
    if (clip.el) clip.el.remove();
    STATE.clips.splice(idx, 1);
}

// ─── Mute / Solo / Volume ────────────────────────────────────────────────────
function toggleMute(id) {
    const t = STATE.tracks.find(t => t.id === id);
    if (!t) return;
    t.muted = !t.muted;
    updateAllTrackVolumes();
}

function toggleSolo(id) {
    const t = STATE.tracks.find(t => t.id === id);
    if (!t) return;
    t.solo = !t.solo;
    updateAllTrackVolumes();
}

function setTrackVol(id, val) {
    const t = STATE.tracks.find(t => t.id === id);
    if (!t) return;
    t.vol = parseFloat(val);
    if (CURRENT_FX_TRACK_ID === id) _syncTrackVolSlider();
    updateAllTrackVolumes();
}

// ─── Clear All ───────────────────────────────────────────────────────────────
function dawClearAll(force = false) {
    if (!STATE.tracks.length) return;
    if (!force && !confirm('Remove all tracks and clips?')) return;
    [...STATE.clips].forEach(c => removeClipInternal(c.id));
    STATE.clips = [];
    [...STATE.tracks].forEach(t => {
        const el = document.getElementById('track-' + t.id);
        if (el) el.remove();
        if (t.inputNode) {
            try { t.inputNode.dispose(); t.outNode.dispose(); Object.values(t.nodes).forEach(n => n.dispose()); } catch(e){}
        }
    });
    STATE.tracks = [];
    STATE.trackIdSeq = 0;
    STATE.clipIdSeq  = 0;
    STATE.curTime    = 0;
    seekTimeline(0);
    historyReset();
    updateMasterLayout();
}

// ─── Serialization ───────────────────────────────────────────────────────────
function serializeDawState() {
    return {
        project: PROJECT,
        master: { 
            vol: (typeof MASTER !== 'undefined') ? MASTER.vol : 1,
            fxChain: (typeof MASTER !== 'undefined') ? MASTER.fxChain : [],
            pluginState: (typeof PLUGIN_STATE !== 'undefined') ? PLUGIN_STATE : {}
        },
        tracks: STATE.tracks.map(t => ({
            id:           t.id,
            name:         t.name,
            color:        t.color,
            vol:          t.vol,
            muted:        t.muted,
            solo:         t.solo || false,
            envelopeMode: t.envelopeMode || false,
            fxChain:      t.fxChain || [],
            pluginState:  t.pluginState || _getDefaultPluginState(),
            activePlugin: t.activePlugin || null
        })),
        clips: STATE.clips.map(c => ({
            trackId:        c.trackId,
            url:            c.url,
            name:           c.name,
            startTime:      c.startTime,
            trimStart:      c.trimStart,
            duration:       c.duration,
            envelopePoints: c.envelopePoints || [],
        }))
    };
}

function deserializeDawState(data) {
    dawClearAll(true);
    if (data.project) Object.assign(PROJECT, data.project);
    
    // Master FX deserialization
    if (data.master && typeof MASTER !== 'undefined') {
        MASTER.vol = data.master.vol ?? 1;
        const slider = document.getElementById('masterVolSlider');
        const label  = document.getElementById('masterVolLabel');
        if (slider) slider.value = MASTER.vol;
        if (label)  label.textContent = Math.round(MASTER.vol * 100) + '%';
        
        if (data.master.fxChain) MASTER.fxChain = data.master.fxChain;
        if (data.master.pluginState && typeof PLUGIN_STATE !== 'undefined') {
            Object.assign(PLUGIN_STATE.gain, data.master.pluginState.gain || {});
            Object.assign(PLUGIN_STATE.volume, data.master.pluginState.volume || {});
            Object.assign(PLUGIN_STATE.compressor, data.master.pluginState.compressor || {});
            Object.assign(PLUGIN_STATE.eq3band, data.master.pluginState.eq3band || {});
            Object.assign(PLUGIN_STATE.limiter, data.master.pluginState.limiter || {});
        }
        if (typeof _rebuildToneChain === 'function') {
            if (!MASTER.inputNode) initToneFoundation();
            
            // Clear existing nodes so they recreate with new state lazily
            Object.values(MASTER.nodes).forEach(n => { try { n.dispose(); } catch(e){} });
            MASTER.nodes = {};
            
            _rebuildToneChain();
            if (typeof _renderFxChain === 'function') _renderFxChain();
            if (typeof _renderPluginUi === 'function') _renderPluginUi(MASTER.activePlugin);
        }
    }
    syncMenuBar();

    if (data.tracks && data.clips) {
        const trackMap = {};
        const defaultState = _getDefaultPluginState();
        
        data.tracks.forEach(oldT => {
            const newId = addTrackLane(oldT.name);
            trackMap[oldT.id] = newId;
            const newT = STATE.tracks.find(t => t.id === newId);
            if (newT) {
                newT.color        = oldT.color;
                newT.vol          = oldT.vol;
                newT.muted        = oldT.muted;
                newT.solo         = oldT.solo || false;
                newT.envelopeMode = oldT.envelopeMode || false;
                
                // Restore FX state
                newT.fxChain = [...(oldT.fxChain || [])];
                newT.activePlugin = oldT.activePlugin || null;
                newT.pluginState = {
                    gain:       { ...defaultState.gain,       ...(oldT.pluginState?.gain || {}) },
                    volume:     { ...defaultState.volume,     ...(oldT.pluginState?.volume || {}) },
                    compressor: { ...defaultState.compressor, ...(oldT.pluginState?.compressor || {}) },
                    eq3band:    { ...defaultState.eq3band,    ...(oldT.pluginState?.eq3band || {}) },
                    limiter:    { ...defaultState.limiter,    ...(oldT.pluginState?.limiter || {}) }
                };
                
                _initTrackTone(newT); // Re-initialize the audio nodes with loaded parameters
                
                const elStrip = document.querySelector('#track-' + newId + ' .track-color-strip');
                const elVol   = document.querySelector('#track-' + newId + ' .track-vol');
                if (elStrip) elStrip.style.background = newT.color;
                if (elVol)   elVol.value = newT.vol;
            }
        });

        updateAllTrackVolumes();

        data.clips.forEach(c => {
            if (trackMap[c.trackId]) {
                addClip(trackMap[c.trackId], c.url, c.name, c.startTime, c.envelopePoints || [], c.trimStart, c.duration);
            }
        });
    }

    // Restore envelope modes after clips are wired up
    setTimeout(() => restoreEnvelopeModes(_envProjectKey()), 200);

    historyReset();
    updateMasterLayout();
}





