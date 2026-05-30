// public/vedtriccs/js/vt-engine.js
// Core state, zoom, ruler, grid — VedTriccs Edition
// Mirrors ved-engine.js with VT_ namespace and connector awareness

'use strict';

// ─── Project Settings ─────────────────────────────────────────────────────────
const VT_PROJECT = {
    gridVisible:  true,
    snapEnabled:  false,
    snapDiv:      0.25,
    gridColor:    '#f59e0b',
    gridOpacity:  18,
    canvasW:      1024,
    canvasH:      1024,
};

const VT_GRID_COLORS = [
    '#f59e0b','#14b8a6','#8b5cf6','#ef4444',
    '#22c55e','#3b82f6','#ec4899','#f97316','#ffffff','#a0a0c0'
];
let vtSelectedSwatchIdx = 0;

// ─── Core State ───────────────────────────────────────────────────────────────
const VT_STATE = {
    animaticId:   window.VT_INIT_ANIMATIC_ID || null,
    animaticName: '',

    tracks:       [],   // { id, name, color, vol, muted }
    clips:        [],   // { id, trackId, url, name, thumbnail, duration,
                        //   startTime, trimStart, trimEnd, playbackSpeed,
                        //   el, connectorPipEl }

    // Connector map: key → { transitionName, durationFrames, intensity, easing, seed,
    //                        jobId, jobStatus, videoId }
    // key = vtConnectorKey(clipA, clipB)
    connectors:   {},

    // Which connector is currently selected in the panel
    selectedConnKey: null,

    // Current save file id (for persisting connectors server-side)
    currentFileId: null,

    trackIdSeq:   0,
    clipIdSeq:    0,

    masterZoom:   60,
    fps:          30,
    projectDuration: 120,

    isPlaying:    false,
    curTime:      0,
    lastFrameTime: 0,
    rafId:        null,

    // Bin state
    binPage:      1,
    binTotalPages: 1,
    binSearch:    '',
    binDebounce:  null,
    binData:      [],
    binPreviewEl: null,

    abPage:       1,
    abTotalPages: 1,
    abSearch:     '',
    abDebounce:   null,

    editMode:     null,
    selectedClipId: null,
};

const VT_COLORS = [
    '#f59e0b','#14b8a6','#8b5cf6','#ef4444',
    '#22c55e','#3b82f6','#ec4899','#f97316','#06b6d4','#a855f7'
];
let vtColorIdx = 0;
function vtNextColor() { return VT_COLORS[vtColorIdx++ % VT_COLORS.length]; }

const VT_TRACK_HEAD_W = window.VT_TRACK_HEAD_W || 160;

// ─── Helpers ──────────────────────────────────────────────────────────────────
function vtEsc(s) {
    return s ? String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
}
function vtTrunc(s, n) { if (!s) return ''; s = String(s); return s.length > n ? s.slice(0,n)+'…' : s; }
function vtFmtTime(secs) {
    if (isNaN(secs) || secs < 0) secs = 0;
    const h = Math.floor(secs / 3600);
    const m = Math.floor((secs % 3600) / 60);
    const s = secs % 60;
    return h + ':' + String(m).padStart(2,'0') + ':' + s.toFixed(3).padStart(6,'0');
}

function vtApi(action, params = {}, method = 'GET') {
    const base = `?api_action=${action}`;
    if (method === 'GET') {
        const qs = Object.entries(params).map(([k,v]) => `${k}=${encodeURIComponent(v)}`).join('&');
        return fetch(base + (qs ? '&' + qs : '')).then(r => r.json());
    }
    const body = new URLSearchParams(params);
    return fetch(base, { method: 'POST', body }).then(r => r.json());
}

// ─── Connector Key ────────────────────────────────────────────────────────────
// Stable key identifying the boundary between two adjacent clips on the same track.
// Using clip IDs keeps it stable within a session; serialized state preserves url+startTime hash.
function vtConnectorKey(clipA, clipB) {
    // Session key (by id) — used for live UI
    return `conn_${clipA.id}_${clipB.id}`;
}

function vtConnectorKeyFromData(urlA, startA, urlB, startB) {
    // Deterministic key from serialized data — used when loading a project file
    const h = (s) => {
        let hash = 0;
        for (let i = 0; i < s.length; i++) { hash = (hash << 5) - hash + s.charCodeAt(i); hash |= 0; }
        return Math.abs(hash).toString(36);
    };
    return `conn_${h(String(urlA) + String(startA))}_${h(String(urlB) + String(startB))}`;
}

// ─── Snap ─────────────────────────────────────────────────────────────────────
function vtSnapTime(t) {
    if (!VT_PROJECT.snapEnabled) return Math.max(0, t);
    const div = VT_PROJECT.snapDiv || 0.25;
    return Math.max(0, Math.round(t / div) * div);
}

// ─── Clip Visual Duration ─────────────────────────────────────────────────────
function vtClipVisualDuration(c) {
    const rawDur = c.trimEnd !== null ? (c.trimEnd - c.trimStart) : (c.duration - c.trimStart);
    return Math.max(0.05, rawDur / (c.playbackSpeed || 1.0));
}

// ─── Master Layout ────────────────────────────────────────────────────────────
function vtUpdateMasterLayout() {
    let maxTime = 60;
    VT_STATE.clips.forEach(c => {
        const end = c.startTime + vtClipVisualDuration(c);
        if (end > maxTime) maxTime = end;
    });
    VT_STATE.projectDuration = maxTime + 20;

    const canvasW  = VT_STATE.projectDuration * VT_STATE.masterZoom;
    const totalPx  = VT_TRACK_HEAD_W + canvasW;
    const content  = document.getElementById('vtTimelineContent');
    if (content) content.style.width = totalPx + 'px';
    const rulerContent = document.getElementById('rulerContent');
    if (rulerContent) rulerContent.style.width = canvasW + 'px';

    vtGenerateGrid();
    vtDrawRuler();
    vtApplyPlayheadPos();

    const empty = document.getElementById('vtEmpty');
    if (empty) empty.style.display = VT_STATE.tracks.length ? 'none' : 'flex';

    // Refresh all connector pips on clips
    vtRefreshAllConnectorPips();
}

function vtApplyPlayheadPos() {
    const ph = document.getElementById('playhead');
    if (ph) ph.style.transform = `translateX(${VT_STATE.curTime * VT_STATE.masterZoom}px)`;
    const tp = document.getElementById('tpTime');
    if (tp) tp.textContent = vtFmtTime(VT_STATE.curTime);
}

// ─── Grid ─────────────────────────────────────────────────────────────────────
function vtGenerateGrid() {
    const content = document.getElementById('vtTimelineContent');
    if (!content) return;
    if (!VT_PROJECT.gridVisible) { content.style.backgroundImage = 'none'; return; }

    const dpr    = window.devicePixelRatio || 1;
    const ppSec  = VT_STATE.masterZoom;
    const ppMinor = ppSec * (VT_PROJECT.snapDiv || 0.25);
    const tileW  = Math.max(1, ppSec);
    const canvas = document.createElement('canvas');
    canvas.width  = Math.round(tileW * dpr);
    canvas.height = 100 * dpr;
    const ctx    = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    function hexA(hex, alpha) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return `rgba(${r},${g},${b},${alpha})`;
    }
    const op    = VT_PROJECT.gridOpacity / 100;
    const color = VT_PROJECT.gridColor;
    ctx.fillStyle = hexA(color, op);
    ctx.fillRect(0, 0, 1.5, 100);
    const divsPerSec = Math.round(1 / (VT_PROJECT.snapDiv || 0.25));
    for (let d = 1; d < divsPerSec; d++) {
        const x = d * ppMinor;
        if (x < tileW && ppMinor >= 3) {
            ctx.fillStyle = hexA(color, op * 0.4);
            ctx.fillRect(x, 0, 1, 100);
        }
    }
    const dataUrl = canvas.toDataURL();
    content.style.backgroundImage    = `url(${dataUrl})`;
    content.style.backgroundSize     = `${tileW}px 100px`;
    content.style.backgroundRepeat   = 'repeat';
    content.style.backgroundPosition = `${VT_TRACK_HEAD_W}px 0`;
}

// ─── Ruler ────────────────────────────────────────────────────────────────────
function vtDrawRuler() {
    const canvas = document.getElementById('rulerCanvas');
    const wrap   = document.getElementById('rulerWrap');
    if (!canvas || !wrap) return;

    const dpr  = window.devicePixelRatio || 1;
    const W    = VT_STATE.projectDuration * VT_STATE.masterZoom;
    const H    = 28;
    canvas.width        = W * dpr;
    canvas.height       = H * dpr;
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';
    const ctx  = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    ctx.fillStyle = isDark ? '#14141f' : '#f0f0f8';
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = isDark ? '#252538' : '#c8c8de';
    ctx.fillRect(0, H - 1, W, 1);

    const ppSec    = VT_STATE.masterZoom;
    const barColor = isDark ? '#f59e0b' : '#b87200';
    ctx.font = 'bold 9px "Space Mono", monospace';

    let labelEvery = 1;
    if (ppSec < 15) labelEvery = 10;
    else if (ppSec < 30) labelEvery = 5;
    else if (ppSec < 60) labelEvery = 2;

    const totalSecs = Math.ceil(W / ppSec) + 1;
    for (let s = 0; s <= totalSecs; s++) {
        const x = s * ppSec;
        if (s % labelEvery === 0) {
            ctx.fillStyle = isDark ? '#2c2c46' : '#b8b8d4';
            ctx.fillRect(x, 0, 1, H);
            ctx.fillStyle = barColor;
            const label = s >= 60 ? Math.floor(s/60) + ':' + String(s%60).padStart(2,'0') : s + 's';
            ctx.fillText(label, x + 3, H - 5);
        } else {
            ctx.fillStyle = isDark ? '#3a3a58' : '#b0b0d0';
            ctx.fillRect(x, H * 0.4, 1, H * 0.6);
        }
    }
}

// ─── Zoom ─────────────────────────────────────────────────────────────────────
function vtSetZoom(val) {
    VT_STATE.masterZoom = Math.max(4, Math.min(500, parseFloat(val)));
    vtUpdateMasterLayout();
    VT_STATE.clips.forEach(c => {
        if (!c.el) return;
        c.el.style.left  = (c.startTime * VT_STATE.masterZoom) + 'px';
        c.el.style.width = (vtClipVisualDuration(c) * VT_STATE.masterZoom) + 'px';
    });
    const sl = document.getElementById('zoomSlider');
    if (sl) sl.value = VT_STATE.masterZoom;
}

// ─── Edit Mode ────────────────────────────────────────────────────────────────
function vtToggleEditMode(mode) {
    VT_STATE.editMode = (VT_STATE.editMode === mode) ? null : mode;
    document.getElementById('mbBtnCut')?.classList.toggle('active', VT_STATE.editMode === 'cut');
    document.getElementById('mbBtnRem')?.classList.toggle('active', VT_STATE.editMode === 'rem');
    document.body.classList.toggle('edit-mode-cut', VT_STATE.editMode === 'cut');
    document.body.classList.toggle('edit-mode-rem', VT_STATE.editMode === 'rem');
}

// ─── Fullscreen ───────────────────────────────────────────────────────────────
function vtToggleFullscreen() {
    const shell = document.querySelector('.vt-shell');
    const btn   = document.getElementById('btnFullscreen');
    const icon  = btn?.querySelector('i');
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        const el  = shell || document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen;
        if (req) req.call(el).catch(() => {});
        if (icon) icon.className = 'bi bi-fullscreen-exit';
    } else {
        const ex = document.exitFullscreen || document.webkitExitFullscreen;
        if (ex) ex.call(document).catch(() => {});
        if (icon) icon.className = 'bi bi-fullscreen';
    }
}

// ─── Animatic Label ───────────────────────────────────────────────────────────
function vtUpdateAnimaticLabel() {
    const lbl  = document.getElementById('mbAnimaticLbl');
    const name = VT_STATE.animaticName || (VT_STATE.animaticId ? '#' + VT_STATE.animaticId : '— no animatic —');
    if (lbl) lbl.textContent = name;
}

// ─── Serialization ────────────────────────────────────────────────────────────
function vtSerialize() {
    const h = (s) => {
        let hash = 0;
        for (let i = 0; i < s.length; i++) { hash = (hash << 5) - hash + s.charCodeAt(i); hash |= 0; }
        return Math.abs(hash).toString(36);
    };
    return {
        project: { ...VT_PROJECT },
        animaticId:   VT_STATE.animaticId,
        animaticName: VT_STATE.animaticName,
        masterZoom:   VT_STATE.masterZoom,
        fps:          VT_STATE.fps,
        tracks: VT_STATE.tracks.map(t => ({
            id: t.id, name: t.name, color: t.color, vol: t.vol, muted: t.muted,
        })),
        clips: VT_STATE.clips.map(c => ({
            id:            c.id,
            trackId:       c.trackId,
            url:           c.url,
            name:          c.name,
            thumbnail:     c.thumbnail,
            duration:      c.duration,
            startTime:     c.startTime,
            trimStart:     c.trimStart,
            trimEnd:       c.trimEnd,
            playbackSpeed: c.playbackSpeed,
            // Pre-calculate exact hash for PyAPI boundary matching without floats going rogue
            stableHash:    h(String(c.url) + String(c.startTime))
        })),
        connectors: vtSerializeConnectors(),
    };
}

function vtSerializeConnectors() {
    const out = {};
    // Build a map from session key → serialized key
    const sorted = vtGetSortedClipsPerTrack();
    for (const [trackId, clips] of Object.entries(sorted)) {
        for (let i = 0; i < clips.length - 1; i++) {
            const a = clips[i], b = clips[i + 1];
            const sessionKey = vtConnectorKey(a, b);
            const conn = VT_STATE.connectors[sessionKey];
            if (conn) {
                const stableKey = vtConnectorKeyFromData(a.url, a.startTime, b.url, b.startTime);
                out[stableKey] = { ...conn };
            }
        }
    }
    return out;
}

function vtDeserialize(data) {
    vtClearAll(true);
    if (data.project) Object.assign(VT_PROJECT, data.project);
    if (data.masterZoom) VT_STATE.masterZoom = data.masterZoom;
    if (data.fps)        VT_STATE.fps        = data.fps;
    if (data.animaticId) {
        VT_STATE.animaticId   = data.animaticId;
        VT_STATE.animaticName = data.animaticName || '';
        vtUpdateAnimaticLabel();
    }
    const trackMap = {};
    (data.tracks || []).forEach(oldT => {
        const nid = vtAddTrackNoHistory(oldT.name);
        trackMap[oldT.id] = nid;
        const nt  = VT_STATE.tracks.find(t => t.id === nid);
        if (nt) {
            nt.color = oldT.color; nt.vol = oldT.vol; nt.muted = oldT.muted;
            const strip = document.querySelector(`#vt-track-${nid} .track-color-strip`);
            const vol   = document.querySelector(`#vt-track-${nid} .track-vol`);
            if (strip) strip.style.background = nt.color;
            if (vol)   vol.value = nt.vol;
            if (nt.muted) document.getElementById('mute-' + nid)?.classList.add('muted');
        }
    });
    (data.clips || []).forEach(cs => {
        const tid = trackMap[cs.trackId];
        if (!tid) return;
        vtAddClipNoHistory(tid, { url: cs.url, name: cs.name, thumbnail: cs.thumbnail, duration: cs.duration }, cs.startTime, cs);
    });

    // Restore connectors: map stable keys back to session keys
    if (data.connectors) {
        const sorted = vtGetSortedClipsPerTrack();
        for (const [, clips] of Object.entries(sorted)) {
            for (let i = 0; i < clips.length - 1; i++) {
                const a = clips[i], b = clips[i+1];
                const stableKey  = vtConnectorKeyFromData(a.url, a.startTime, b.url, b.startTime);
                const connData   = data.connectors[stableKey];
                if (connData) {
                    const sessionKey = vtConnectorKey(a, b);
                    VT_STATE.connectors[sessionKey] = { ...connData };
                }
            }
        }
    }

    vtSetZoom(VT_STATE.masterZoom);
    vtHistoryReset();
    vtUpdateMasterLayout();
}

// ─── Adjacent clip helpers ────────────────────────────────────────────────────
function vtGetSortedClipsPerTrack() {
    const byTrack = {};
    VT_STATE.clips.forEach(c => {
        if (!byTrack[c.trackId]) byTrack[c.trackId] = [];
        byTrack[c.trackId].push(c);
    });
    for (const tid in byTrack) {
        byTrack[tid].sort((a, b) => a.startTime - b.startTime);
    }
    return byTrack;
}

// Returns [clipA, clipB] if clip has an adjacent neighbour on right, else null
function vtRightNeighbour(clip) {
    const sorted = (vtGetSortedClipsPerTrack()[clip.trackId] || []);
    const idx    = sorted.findIndex(c => c.id === clip.id);
    if (idx < 0 || idx >= sorted.length - 1) return null;
    return [sorted[idx], sorted[idx + 1]];
}

// ─── Clear All ────────────────────────────────────────────────────────────────
function vtClearAll(force = false) {
    if (!VT_STATE.tracks.length) return;
    if (!force && !confirm('Remove all tracks and clips?')) return;
    [...VT_STATE.clips].forEach(c => vtRemoveClipInternal(c.id));
    VT_STATE.clips  = [];
    [...VT_STATE.tracks].forEach(t => {
        document.getElementById('vt-track-' + t.id)?.remove();
    });
    VT_STATE.tracks        = [];
    VT_STATE.trackIdSeq    = 0;
    VT_STATE.clipIdSeq     = 0;
    VT_STATE.curTime       = 0;
    VT_STATE.connectors    = {};
    VT_STATE.selectedConnKey = null;
    vtHistoryReset();
    vtUpdateMasterLayout();
}

function vtRemoveClipInternal(id) {
    const idx = VT_STATE.clips.findIndex(c => c.id === id);
    if (idx === -1) return;
    const clip = VT_STATE.clips[idx];
    if (clip.el) clip.el.remove();
    VT_STATE.clips.splice(idx, 1);
    if (VT_STATE.selectedClipId === id) VT_STATE.selectedClipId = null;
}