// public/ved/js/ved-engine.js
// Core state, zoom, ruler drawing, grid generation, master layout
// Timeline is time-based (seconds), NOT beat-based.

'use strict';

// ─── Project Settings ─────────────────────────────────────────────────────────
const PROJECT = {
    gridVisible:  true,
    snapEnabled:  false,
    snapDiv:      0.25,        // seconds per snap unit
    gridColor:    '#f59e0b',
    gridOpacity:  18,
    canvasW:      1024,
    canvasH:      1024,
};

const GRID_COLORS = [
    '#f59e0b','#14b8a6','#8b5cf6','#ef4444',
    '#22c55e','#3b82f6','#ec4899','#f97316','#ffffff','#a0a0c0'
];
let selectedSwatchIdx = 0;

// ─── Core App State ───────────────────────────────────────────────────────────
const STATE = {
    animaticId:   window.VED_INIT_ANIMATIC_ID || null,
    animaticName: '',

    tracks:       [],   // { id, name, color, vol, muted }
    clips:        [],   // { id, trackId, url, name, thumbnail, duration,
                        //   startTime, trimStart, trimEnd, playbackSpeed,
                        //   el, videoEl }
    trackIdSeq:   0,
    clipIdSeq:    0,

    masterZoom:   60,   // pixels per second
    fps:          30,
    projectDuration: 120,

    isPlaying:    false,
    curTime:      0,
    lastFrameTime: 0,
    rafId:        null,

    // Asset bin
    binPage:      1,
    binTotalPages: 1,
    binSearch:    '',
    binDebounce:  null,
    binData:      [],
    binPreviewEl: null,  // currently previewing video element

    // Animatic browser
    abPage:       1,
    abTotalPages: 1,
    abSearch:     '',
    abDebounce:   null,

    editMode:     null, // 'cut' | 'rem' | null

    selectedClipId: null,
};

const COLORS = [
    '#f59e0b','#14b8a6','#8b5cf6','#ef4444',
    '#22c55e','#3b82f6','#ec4899','#f97316','#06b6d4','#a855f7'
];
let colorIdx = 0;
function nextColor() { return COLORS[colorIdx++ % COLORS.length]; }

// ─── Constants ─────────────────────────────────────────────────────────────────
const TRACK_HEAD_W = window.VED_TRACK_HEAD_W || 160;

// ─── Helpers ──────────────────────────────────────────────────────────────────
function esc(s) {
    return s ? String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
}
function trunc(s, n) { if (!s) return ''; s = String(s); return s.length > n ? s.slice(0, n) + '…' : s; }
function fmtTime(secs) {
    if (isNaN(secs) || secs < 0) secs = 0;
    const h  = Math.floor(secs / 3600);
    const m  = Math.floor((secs % 3600) / 60);
    const s  = secs % 60;
    return h + ':' + String(m).padStart(2,'0') + ':' + s.toFixed(3).padStart(6,'0');
}

function api(action, params = {}, method = 'GET') {
    const base = `?api_action=${action}`;
    if (method === 'GET') {
        const qs = Object.entries(params).map(([k,v]) => `${k}=${encodeURIComponent(v)}`).join('&');
        return fetch(base + (qs ? '&' + qs : '')).then(r => r.json());
    }
    const body = new URLSearchParams(params);
    return fetch(base, { method: 'POST', body }).then(r => r.json());
}

// ─── Snap helper ──────────────────────────────────────────────────────────────
function snapTime(t) {
    if (!PROJECT.snapEnabled) return Math.max(0, t);
    const div = PROJECT.snapDiv || 0.25;
    return Math.max(0, Math.round(t / div) * div);
}

// ─── Master Layout ────────────────────────────────────────────────────────────
function updateMasterLayout() {
    let maxTime = 60;
    STATE.clips.forEach(c => {
        const end = c.startTime + clipVisualDuration(c);
        if (end > maxTime) maxTime = end;
    });
    STATE.projectDuration = maxTime + 20;

    const canvasW = STATE.projectDuration * STATE.masterZoom;
    const totalPx = TRACK_HEAD_W + canvasW;

    const content = document.getElementById('vedTimelineContent');
    if (content) content.style.width = totalPx + 'px';

    const rulerContent = document.getElementById('rulerContent');
    if (rulerContent) rulerContent.style.width = canvasW + 'px';

    generateGrid();
    drawRuler();
    _applyPlayheadPos();

    const empty = document.getElementById('vedEmpty');
    if (empty) empty.style.display = STATE.tracks.length ? 'none' : 'flex';
}

function clipVisualDuration(c) {
    // Visual width = (trimEnd - trimStart) / playbackSpeed
    const rawDur = c.trimEnd !== null ? (c.trimEnd - c.trimStart) : (c.duration - c.trimStart);
    return Math.max(0.05, rawDur / (c.playbackSpeed || 1.0));
}

function _applyPlayheadPos() {
    const ph = document.getElementById('playhead');
    if (ph) ph.style.transform = `translateX(${STATE.curTime * STATE.masterZoom}px)`;
    const tp = document.getElementById('tpTime');
    if (tp) tp.textContent = fmtTime(STATE.curTime);
}

// ─── CSS Grid Background ──────────────────────────────────────────────────────
function generateGrid() {
    const content = document.getElementById('vedTimelineContent');
    if (!content) return;
    if (!PROJECT.gridVisible) { content.style.backgroundImage = 'none'; return; }

    const dpr     = window.devicePixelRatio || 1;
    const ppSec   = STATE.masterZoom;  // px per second

    // Major grid: every second; minor: every snapDiv
    const ppMinor = ppSec * (PROJECT.snapDiv || 0.25);
    const ppMajor = ppSec;

    // Tile width = 1 second
    const tileW   = Math.max(1, ppMajor);
    const canvas  = document.createElement('canvas');
    canvas.width  = Math.round(tileW * dpr);
    canvas.height = 100 * dpr;
    const ctx     = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    function hexA(hex, alpha) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    const op    = PROJECT.gridOpacity / 100;
    const color = PROJECT.gridColor;

    // Major line at x=0 (start of each second)
    ctx.fillStyle = hexA(color, op);
    ctx.fillRect(0, 0, 1.5, 100);

    // Minor lines
    const divsPerSec = Math.round(1 / (PROJECT.snapDiv || 0.25));
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
    content.style.backgroundPosition = `${TRACK_HEAD_W}px 0`;
}

// ─── Ruler ────────────────────────────────────────────────────────────────────
function drawRuler() {
    const canvas = document.getElementById('rulerCanvas');
    const wrap   = document.getElementById('rulerWrap');
    if (!canvas || !wrap) return;

    const dpr  = window.devicePixelRatio || 1;
    const W    = STATE.projectDuration * STATE.masterZoom;
    const H    = 28;

    canvas.width        = W * dpr;
    canvas.height       = H * dpr;
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';

    const ctx    = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    ctx.fillStyle = isDark ? '#14141f' : '#f0f0f8';
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = isDark ? '#252538' : '#c8c8de';
    ctx.fillRect(0, H - 1, W, 1);

    const ppSec     = STATE.masterZoom;
    const barColor  = isDark ? '#f59e0b' : '#b87200';
    const tickColor = isDark ? '#3a3a58' : '#b0b0d0';

    ctx.font = 'bold 9px "Space Mono", monospace';

    // Decide label interval (keep readable at zoom levels)
    let labelEvery = 1; // every N seconds
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
            const label = s >= 60
                ? Math.floor(s/60) + ':' + String(s%60).padStart(2,'0')
                : s + 's';
            ctx.fillText(label, x + 3, H - 5);
        } else {
            ctx.fillStyle = tickColor;
            ctx.fillRect(x, H * 0.4, 1, H * 0.6);
        }
    }
}

// ─── Zoom ─────────────────────────────────────────────────────────────────────
function vedSetZoom(val) {
    STATE.masterZoom = Math.max(4, Math.min(500, parseFloat(val)));
    updateMasterLayout();
    STATE.clips.forEach(c => {
        if (!c.el) return;
        c.el.style.left  = (c.startTime * STATE.masterZoom) + 'px';
        c.el.style.width = (clipVisualDuration(c) * STATE.masterZoom) + 'px';
    });
    const sl = document.getElementById('zoomSlider');
    if (sl) sl.value = STATE.masterZoom;
}

// ─── Edit Mode ────────────────────────────────────────────────────────────────
function toggleEditMode(mode) {
    STATE.editMode = (STATE.editMode === mode) ? null : mode;
    const btnCut = document.getElementById('mbBtnCut');
    const btnRem = document.getElementById('mbBtnRem');
    if (btnCut) btnCut.classList.toggle('active', STATE.editMode === 'cut');
    if (btnRem) btnRem.classList.toggle('active', STATE.editMode === 'rem');
    document.body.classList.toggle('edit-mode-cut', STATE.editMode === 'cut');
    document.body.classList.toggle('edit-mode-rem', STATE.editMode === 'rem');
}

// ─── Fullscreen ───────────────────────────────────────────────────────────────
function toggleFullscreen() {
    const shell = document.querySelector('.ved-shell');
    const btn   = document.getElementById('btnFullscreen');
    const icon  = btn ? btn.querySelector('i') : null;
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        const el = shell || document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen;
        if (req) req.call(el).catch(() => {});
        if (icon) icon.className = 'bi bi-fullscreen-exit';
    } else {
        const ex = document.exitFullscreen || document.webkitExitFullscreen;
        if (ex) ex.call(document).catch(() => {});
        if (icon) icon.className = 'bi bi-fullscreen';
    }
}

// ─── Preview Panel ────────────────────────────────────────────────────────────
function togglePreviewPanel() {
    const panel = document.getElementById('vedPreviewPanel');
    const btn   = document.getElementById('mbBtnPreview');
    if (!panel) return;
    const open = panel.style.display !== 'none';
    panel.style.display = open ? 'none' : 'flex';
    if (btn) btn.classList.toggle('active', !open);
}

function previewClip(clip) {
    const panel = document.getElementById('vedPreviewPanel');
    if (!panel || panel.style.display === 'none') togglePreviewPanel();

    const vid  = document.getElementById('vedPreviewVideo');
    const info = document.getElementById('vedPreviewInfo');
    if (!vid) return;

    vid.src = clip.url;
    vid.currentTime = clip.trimStart || 0;
    vid.playbackRate = clip.playbackSpeed || 1.0;
    vid.play().catch(() => {});
    if (info) info.textContent = clip.name + (clip.duration ? ' (' + clip.duration.toFixed(1) + 's)' : '');
}

// ─── Serialization ────────────────────────────────────────────────────────────
function serializeVedState() {
    return {
        project: {
            gridVisible:  PROJECT.gridVisible,
            snapEnabled:  PROJECT.snapEnabled,
            snapDiv:      PROJECT.snapDiv,
            gridColor:    PROJECT.gridColor,
            gridOpacity:  PROJECT.gridOpacity,
            canvasW:      PROJECT.canvasW,
            canvasH:      PROJECT.canvasH,
        },
        animaticId:   STATE.animaticId,
        animaticName: STATE.animaticName,
        masterZoom:   STATE.masterZoom,
        fps:          STATE.fps,
        tracks: STATE.tracks.map(t => ({
            id: t.id, name: t.name, color: t.color, vol: t.vol, muted: t.muted,
        })),
        clips: STATE.clips.map(c => ({
            trackId:       c.trackId,
            url:           c.url,
            name:          c.name,
            thumbnail:     c.thumbnail,
            duration:      c.duration,
            startTime:     c.startTime,
            trimStart:     c.trimStart,
            trimEnd:       c.trimEnd,
            playbackSpeed: c.playbackSpeed,
        })),
    };
}

function deserializeVedState(data) {
    vedClearAll(true);

    if (data.project) Object.assign(PROJECT, data.project);
    if (data.masterZoom) STATE.masterZoom = data.masterZoom;
    if (data.fps)        STATE.fps        = data.fps;
    if (data.animaticId) {
        STATE.animaticId   = data.animaticId;
        STATE.animaticName = data.animaticName || '';
        _updateAnimaticLabel();
    }

    const trackMap = {};
    (data.tracks || []).forEach(oldT => {
        const nid  = addTrackLaneNoHistory(oldT.name);
        trackMap[oldT.id] = nid;
        const nt   = STATE.tracks.find(t => t.id === nid);
        if (nt) {
            nt.color = oldT.color; nt.vol = oldT.vol; nt.muted = oldT.muted;
            const strip = document.querySelector(`#track-${nid} .track-color-strip`);
            const vol   = document.querySelector(`#track-${nid} .track-vol`);
            if (strip) strip.style.background = nt.color;
            if (vol)   vol.value = nt.vol;
            if (nt.muted) {
                const btn = document.getElementById('mute-' + nid);
                if (btn) btn.classList.add('muted');
            }
        }
    });

    (data.clips || []).forEach(cs => {
        const tid = trackMap[cs.trackId];
        if (!tid) return;
        addClipNoHistory(tid, { url: cs.url, name: cs.name, thumbnail: cs.thumbnail, duration: cs.duration }, cs.startTime, cs);
    });

    vedSetZoom(STATE.masterZoom);
    historyReset();
    updateMasterLayout();
}

// ─── Animatic label helpers ───────────────────────────────────────────────────
function _updateAnimaticLabel() {
    const lbl    = document.getElementById('mbAnimaticLbl');
    const name   = STATE.animaticName || (STATE.animaticId ? '#' + STATE.animaticId : '— no animatic —');
    if (lbl)    lbl.textContent = name;
}




