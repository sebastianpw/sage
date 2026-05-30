// public/daw/js/daw-engine.js
// PROJECT state, zoom helpers, grid generation, ruler drawing, master layout

'use strict';

// ─── Project Settings ──────────────────────────────────────────────────────
const PROJECT = {
    bpm: 120, sigNum: 4, sigDen: 4, gridDiv: 16,
    gridVisible: true, snapEnabled: true,
    gridColor: '#f59e0b', gridOpacity: 15
};

const GRID_COLORS = ['#f59e0b','#14b8a6','#8b5cf6','#ef4444','#22c55e','#3b82f6','#ec4899','#f97316','#ffffff','#a0a0c0'];
let selectedSwatchIdx = 0;

// ─── Core App State ─────────────────────────────────────────────────────────
const STATE = {
    entity: window.DAW_INIT_ENTITY || 'audio_cues',
    entityId: window.DAW_INIT_ENTITY_ID || null,
    page: 1, totalPages: 1, pageSize: 6,
    assetData: [],

    tracks: [],     // { id, name, color, vol, muted, solo }
    clips: [],      // { id, trackId, url, name, startTime, duration, ws, isPlaying, el }
    trackIdSeq: 0,
    clipIdSeq: 0,

    masterZoom: 80, // pixels per second
    projectDuration: 60,

    isPlaying: false,
    curTime: 0,
    lastFrameTime: 0,
    rafId: null,
    previewHowl: null,
    previewIdx: -1,

    entityDebounce: null,
    assetDebounce: null,
    
    editMode: null, // 'cut' | 'rem' | null
};

const COLORS = ['#f59e0b','#14b8a6','#8b5cf6','#ef4444','#22c55e','#3b82f6','#ec4899','#f97316','#06b6d4','#a855f7'];
let colorIdx = 0;
function nextColor() { return COLORS[colorIdx++ % COLORS.length]; }

// ─── Helpers ────────────────────────────────────────────────────────────────
function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
function trunc(s,n){ if (!s) return ''; s=String(s); return s.length>n?s.slice(0,n)+'…':s; }
function fmt(secs){
    if (isNaN(secs)||secs<0) secs=0;
    const m=Math.floor(secs/60), s=secs%60;
    return m+':'+s.toFixed(3).padStart(6,'0');
}
function api(action, params={}, method='GET') {
    const base = `?api_action=${action}&entity=${encodeURIComponent(STATE.entity)}`;
    if (method==='GET') {
        const qs = Object.entries(params).map(([k,v])=>`${k}=${encodeURIComponent(v)}`).join('&');
        return fetch(base+(qs?'&'+qs:'')).then(r=>r.json());
    }
    const body = new URLSearchParams({entity:STATE.entity,...params});
    return fetch(base,{method:'POST',body}).then(r=>r.json());
}

// ─── Grid Math ──────────────────────────────────────────────────────────────
function pxPerBeat() { return STATE.masterZoom * (60 / PROJECT.bpm); }
function pxPerBar()  { return pxPerBeat() * PROJECT.sigNum; }
function pxPerDiv()  { return pxPerBeat() / (PROJECT.gridDiv / PROJECT.sigDen); }

// ─── Master Layout ──────────────────────────────────────────────────────────
const TRACK_HEAD_W = window.DAW_TRACK_HEAD_W || 150; // must match CSS --track-head-w

function updateMasterLayout() {
    let maxTime = 60;
    STATE.clips.forEach(c => {
        if (c.startTime + c.duration > maxTime) maxTime = c.startTime + c.duration;
    });
    STATE.projectDuration = maxTime + 15;

    // Total pixel width of the scrollable content.
    // The track-head occupies the left TRACK_HEAD_W px via sticky positioning inside each row.
    // The timeline canvas starts at TRACK_HEAD_W and extends to the right.
    const canvasW = STATE.projectDuration * STATE.masterZoom;
    const totalPx = TRACK_HEAD_W + canvasW;
    
    document.getElementById('dawTimelineContent').style.width = totalPx + 'px';
    // Ruler is inside a wrapper that's already offset by a 220px spacer, so it only needs the canvasW
    document.getElementById('rulerContent').style.width = canvasW + 'px';

    generateInfiniteGrid();
    drawRuler();

    // Playhead: CSS left = TRACK_HEAD_W (constant), JS sets translateX = curTime * masterZoom
    _applyPlayheadPos();

    document.getElementById('dawEmpty').style.display = STATE.tracks.length ? 'none' : 'flex';
}

function _applyPlayheadPos() {
    const ph = document.getElementById('playhead');
    if (ph) ph.style.transform = `translateX(${STATE.curTime * STATE.masterZoom}px)`;
    const tp = document.getElementById('tpTime');
    if (tp) tp.textContent = fmt(STATE.curTime);
}

// ─── CSS Grid Background ─────────────────────────────────────────────────────
function generateInfiniteGrid() {
    const content = document.getElementById('dawTimelineContent');
    if (!PROJECT.gridVisible) { content.style.backgroundImage = 'none'; return; }

    const dpr      = window.devicePixelRatio || 1;
    const ppBar    = pxPerBar();
    const ppDiv    = pxPerDiv();

    const canvas   = document.createElement('canvas');
    canvas.width   = Math.round(ppBar * dpr);
    canvas.height  = 100 * dpr;
    const ctx      = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const op    = PROJECT.gridOpacity / 100;
    const color = PROJECT.gridColor;

    function hexA(hex, alpha) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    const divsPerBar  = PROJECT.sigNum * (PROJECT.gridDiv / PROJECT.sigDen);
    const divsPerBeat = PROJECT.gridDiv / PROJECT.sigDen;

    for (let d = 0; d < divsPerBar; d++) {
        const x = d * ppDiv;
        if (d === 0) {
            ctx.fillStyle = hexA(color, op);
            ctx.fillRect(x, 0, 1.5, 100);
        } else if (d % divsPerBeat === 0) {
            ctx.fillStyle = hexA(color, op * 0.55);
            ctx.fillRect(x, 0, 1, 100);
        } else if (ppDiv >= 5) {
            ctx.fillStyle = hexA(color, op * 0.3);
            ctx.fillRect(x, 0, 1, 100);
        }
    }

    const dataUrl = canvas.toDataURL();
    // Grid starts exactly at TRACK_HEAD_W (right edge of sticky panel)
    content.style.backgroundImage    = `url(${dataUrl})`;
    content.style.backgroundSize     = `${ppBar}px 100px`;
    content.style.backgroundRepeat   = 'repeat';
    content.style.backgroundPosition = `${TRACK_HEAD_W}px 0`;
}

// ─── Ruler Canvas ────────────────────────────────────────────────────────────
function drawRuler() {
    const canvas = document.getElementById('rulerCanvas');
    const wrap   = document.getElementById('rulerWrap');
    if (!canvas || !wrap) return;

    const dpr    = window.devicePixelRatio || 1;
    // The ruler wrapper is preceded by ruler-spacer (220px), so canvas x=0 is timeline x=220
    const W      = STATE.projectDuration * STATE.masterZoom;
    const H      = 28;

    canvas.width       = W * dpr;
    canvas.height      = H * dpr;
    canvas.style.width = W + 'px';
    canvas.style.height= H + 'px';

    const ctx    = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    ctx.fillStyle = isDark ? '#14141f' : '#f0f0f8';
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = isDark ? '#252538' : '#c8c8de';
    ctx.fillRect(0, H-1, W, 1);

    const ppbar    = pxPerBar();
    const ppBeat   = pxPerBeat();
    const barColor = isDark ? '#f59e0b' : '#b87200';
    const beatColor= isDark ? '#3a3a58' : '#b0b0d0';

    ctx.font = 'bold 9px "Space Mono", monospace';

    const totalBars = Math.ceil(W / ppbar);

    for (let bar = 0; bar <= totalBars; bar++) {
        const barX = bar * ppbar;

        ctx.fillStyle = isDark ? '#2c2c46' : '#b8b8d4';
        ctx.fillRect(barX, 0, 1, H);
        ctx.fillStyle = barColor;
        ctx.fillText(String(bar + 1), barX + 3, H - 5);

        for (let beat = 1; beat < PROJECT.sigNum; beat++) {
            const bx = barX + (beat * ppBeat);
            ctx.fillStyle = beatColor;
            ctx.fillRect(bx, H * 0.35, 1, H * 0.65);
        }
    }
}

// ─── Zoom ───────────────────────────────────────────────────────────────────
function dawSetZoom(val) {
    STATE.masterZoom = parseInt(val);
    updateMasterLayout();
    STATE.clips.forEach(c => {
        if (!c.el) return;
        c.el.style.left  = (c.startTime * STATE.masterZoom) + 'px';
        c.el.style.width = (c.duration  * STATE.masterZoom) + 'px';
        const wsContainer = c.el.querySelector('.clip-ws');
        if (wsContainer) {
            wsContainer.style.width = (c.fullDuration * STATE.masterZoom) + 'px';
            wsContainer.style.transform = `translateX(-${(c.trimStart || 0) * STATE.masterZoom}px)`;
        }
        if (c.ws) c.ws.zoom(STATE.masterZoom);
    });
}

// ─── Fullscreen ──────────────────────────────────────────────────────────────
function toggleFullscreen() {
    const shell = document.querySelector('.daw-shell');
    const btn   = document.getElementById('btnFullscreen');
    const icon  = btn ? btn.querySelector('i') : null;

    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        const el = shell || document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen;
        if (req) req.call(el).catch(()=>{});
        if (icon) icon.className = 'bi bi-fullscreen-exit';
        shell.classList.add('is-fullscreen');
    } else {
        const ex = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen;
        if (ex) ex.call(document).catch(()=>{});
        if (icon) icon.className = 'bi bi-fullscreen';
        shell.classList.remove('is-fullscreen');
    }
}

// ─── Grid / Snap / Edit toggles ──────────────────────────────────────────────
function toggleGridSnap() {
    PROJECT.snapEnabled = !PROJECT.snapEnabled;
    syncMenuBar();
    Toast.show('Snap ' + (PROJECT.snapEnabled ? 'ON' : 'OFF'), 'info');
}
function toggleGridVisible() {
    PROJECT.gridVisible = !PROJECT.gridVisible;
    syncMenuBar();
    updateMasterLayout();
}
function toggleEditMode(mode) {
    STATE.editMode = (STATE.editMode === mode) ? null : mode;
    const btnCut = document.getElementById('mbBtnCut');
    const btnRem = document.getElementById('mbBtnRem');
    if (btnCut) btnCut.classList.toggle('active', STATE.editMode === 'cut');
    if (btnRem) btnRem.classList.toggle('active', STATE.editMode === 'rem');
    
    document.body.classList.toggle('edit-mode-cut', STATE.editMode === 'cut');
    document.body.classList.toggle('edit-mode-rem', STATE.editMode === 'rem');
}

function syncMenuBar() {
    document.getElementById('mbBpm').textContent  = PROJECT.bpm;
    document.getElementById('mbSig').textContent  = PROJECT.sigNum + '/' + PROJECT.sigDen;
    document.getElementById('mbGrid').textContent = '1/' + PROJECT.gridDiv;

    const sb = document.getElementById('snapBadge');
    if (sb) {
        sb.textContent = PROJECT.snapEnabled ? 'ON' : 'OFF';
        sb.classList.toggle('off', !PROJECT.snapEnabled);
    }

    const gb = document.getElementById('gridBadge');
    if (gb) {
        gb.textContent = PROJECT.gridVisible ? 'ON' : 'OFF';
        gb.classList.toggle('off', !PROJECT.gridVisible);
    }
}

// ─── Snap helper ─────────────────────────────────────────────────────────────
function snapTime(t) {
    if (!PROJECT.snapEnabled) return t;
    const snapSecs = (60 / PROJECT.bpm) / (PROJECT.gridDiv / PROJECT.sigDen);
    return Math.round(t / snapSecs) * snapSecs;
}




