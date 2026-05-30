// public/ved/js/ved-drag.js
// Clip drag (move within timeline), clip edge resize, and bin→timeline drag

'use strict';

// ─── Clip Move Drag ───────────────────────────────────────────────────────────
let clipDrag = {
    active: false, clip: null,
    startX: 0, initLeft: 0, initTrackId: 0, initTime: 0,
};

function startClipDrag(e, clip) {
    // Route to edit modes first
    if (e.target.classList.contains('clip-del') ||
        e.target.classList.contains('clip-trim-btn') ||
        e.target.closest('.clip-resize-l') ||
        e.target.closest('.clip-resize-r')) return;

    if (STATE.editMode === 'rem') {
        e.preventDefault(); e.stopPropagation();
        removeClip(clip.id); return;
    }
    if (STATE.editMode === 'cut') {
        e.preventDefault(); e.stopPropagation();
        _cutClipAtEvent(e, clip); return;
    }

    e.preventDefault(); e.stopPropagation();

    clipDrag.active      = true;
    clipDrag.clip        = clip;
    clipDrag.initLeft    = clip.startTime * STATE.masterZoom;
    clipDrag.initTrackId = clip.trackId;
    clipDrag.initTime    = clip.startTime;

    const pt = e.touches ? e.touches[0] : e;
    clipDrag.startX = pt.clientX;

    clip.el.classList.add('dragging');
}

function _cutClipAtEvent(e, clip) {
    const pt      = e.touches ? e.touches[0] : e;
    const rect    = clip.el.getBoundingClientRect();
    const clickX  = pt.clientX - rect.left;
    const splitT  = snapTime(clip.startTime + clickX / STATE.masterZoom);
    splitClipAtTime(clip, splitT);
}

function _onClipDragMove(e) {
    if (!clipDrag.active || !clipDrag.clip) return;
    const pt = e.touches ? e.touches[0] : e;
    const dx = pt.clientX - clipDrag.startX;

    let newTime = snapTime((clipDrag.initLeft + dx) / STATE.masterZoom);
    if (newTime < 0) newTime = 0;

    clipDrag.clip.el.style.left = (newTime * STATE.masterZoom) + 'px';
    clipDrag.clip.startTime     = newTime;

    // Cross-track drop detection
    clipDrag.clip.el.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    clipDrag.clip.el.style.display = '';
    if (target) {
        const lane = target.closest('.track-lane');
        if (lane) {
            const tid = parseInt(lane.id.replace('lane-', ''));
            if (tid !== clipDrag.clip.trackId) {
                clipDrag.clip.trackId = tid;
                lane.appendChild(clipDrag.clip.el);
            }
        }
    }
}

function _onClipDragEnd() {
    if (!clipDrag.active) return;
    clipDrag.clip.el.classList.remove('dragging');
    clipDrag.active = false;

    const clip       = clipDrag.clip;
    const newTime    = clip.startTime;
    const newTrackId = clip.trackId;
    const oldTime    = clipDrag.initTime;
    const oldTrackId = clipDrag.initTrackId;

    if (!_historyGuard() && (newTime !== oldTime || newTrackId !== oldTrackId)) {
        historyPush(
            'Move clip "' + clip.name + '"',
            () => {
                clip.startTime = oldTime; clip.trackId = oldTrackId;
                clip.el.style.left = (oldTime * STATE.masterZoom) + 'px';
                const lane = document.getElementById('lane-' + oldTrackId);
                if (lane) lane.appendChild(clip.el);
                updateMasterLayout();
            },
            () => {
                clip.startTime = newTime; clip.trackId = newTrackId;
                clip.el.style.left = (newTime * STATE.masterZoom) + 'px';
                const lane = document.getElementById('lane-' + newTrackId);
                if (lane) lane.appendChild(clip.el);
                updateMasterLayout();
            }
        );
    }
    updateMasterLayout();
}

document.addEventListener('mousemove',  _onClipDragMove);
document.addEventListener('mouseup',    _onClipDragEnd);
document.addEventListener('touchmove',  _onClipDragMove, { passive: false });
document.addEventListener('touchend',   _onClipDragEnd);

// ─── Clip Edge Resize ─────────────────────────────────────────────────────────
let edgeDrag = {
    active: false, clipId: null, side: null,
    startX: 0, initTrimStart: 0, initTrimEnd: null, initStartTime: 0, initDuration: 0,
};

function _startEdgeDrag(e) {
    const handle = e.target.closest('.clip-resize-l, .clip-resize-r');
    if (!handle) return;
    e.preventDefault(); e.stopPropagation();

    const clipId = parseInt(handle.dataset.clipid);
    const side   = handle.dataset.side;
    const clip   = STATE.clips.find(c => c.id === clipId);
    if (!clip) return;

    const pt = e.touches ? e.touches[0] : e;
    edgeDrag = {
        active:        true,
        clipId,
        side,
        startX:        pt.clientX,
        initTrimStart: clip.trimStart,
        initTrimEnd:   clip.trimEnd,
        initStartTime: clip.startTime,
        initDuration:  clip.duration,
    };
}

function _onEdgeDragMove(e) {
    if (!edgeDrag.active) return;
    const pt   = e.touches ? e.touches[0] : e;
    const dx   = pt.clientX - edgeDrag.startX;
    const ds   = dx / STATE.masterZoom; // seconds delta
    const clip = STATE.clips.find(c => c.id === edgeDrag.clipId);
    if (!clip) return;

    const speed = clip.playbackSpeed || 1.0;

    if (edgeDrag.side === 'l') {
        // Dragging left edge: shift trimStart and startTime
        let newTrimStart = Math.max(0, edgeDrag.initTrimStart + ds * speed);
        const maxTrim = clip.trimEnd !== null
            ? clip.trimEnd - 0.1
            : clip.duration - 0.1;
        newTrimStart = Math.min(newTrimStart, maxTrim);
        const timeDelta = (newTrimStart - edgeDrag.initTrimStart) / speed;
        clip.trimStart = newTrimStart;
        clip.startTime = Math.max(0, edgeDrag.initStartTime + timeDelta);
    } else {
        // Dragging right edge: shift trimEnd
        let newTrimEnd = edgeDrag.initTrimEnd !== null
            ? edgeDrag.initTrimEnd + ds * speed
            : clip.duration + ds * speed;
        newTrimEnd = Math.max(clip.trimStart + 0.1, Math.min(clip.duration, newTrimEnd));
        clip.trimEnd = (newTrimEnd >= clip.duration - 0.02) ? null : newTrimEnd;
    }

    _refreshClipEl(clip);
}

function _onEdgeDragEnd() {
    if (!edgeDrag.active) return;
    edgeDrag.active = false;
    updateMasterLayout();
}

document.addEventListener('mousedown',  _startEdgeDrag);
document.addEventListener('touchstart', _startEdgeDrag, { passive: false });
document.addEventListener('mousemove',  _onEdgeDragMove);
document.addEventListener('mouseup',    _onEdgeDragEnd);
document.addEventListener('touchmove',  _onEdgeDragMove, { passive: false });
document.addEventListener('touchend',   _onEdgeDragEnd);

// ─── Bin → Timeline Drag ──────────────────────────────────────────────────────
let binDrag = {
    active: false, clone: null, assetIdx: null, offsetX: 0, offsetY: 0,
};

function startBinDrag(e) {
    const handle = e.target.closest('.vid-drag-handle');
    if (!handle) return;
    e.preventDefault();

    binDrag.assetIdx = parseInt(handle.dataset.idx);
    binDrag.active   = true;

    const item = handle.closest('.vid-item');
    binDrag.clone = item.cloneNode(true);
    Object.assign(binDrag.clone.style, {
        position: 'fixed', zIndex: '9999',
        background: 'var(--surface)', border: '1px solid var(--amber)',
        width: item.offsetWidth + 'px', opacity: '0.88', pointerEvents: 'none',
        borderRadius: '5px',
    });

    const rect = item.getBoundingClientRect();
    const pt   = e.touches ? e.touches[0] : e;
    binDrag.offsetX = pt.clientX - rect.left;
    binDrag.offsetY = pt.clientY - rect.top;
    binDrag.clone.style.left = (pt.clientX - binDrag.offsetX) + 'px';
    binDrag.clone.style.top  = (pt.clientY - binDrag.offsetY) + 'px';

    document.body.appendChild(binDrag.clone);
    closeBin();
}

function _moveBinDrag(e) {
    if (!binDrag.active || !binDrag.clone) return;
    e.preventDefault();
    const pt = e.touches ? e.touches[0] : e;
    binDrag.clone.style.left = (pt.clientX - binDrag.offsetX) + 'px';
    binDrag.clone.style.top  = (pt.clientY - binDrag.offsetY) + 'px';

    document.querySelectorAll('.track-lane').forEach(l => l.classList.remove('drag-over'));

    binDrag.clone.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    binDrag.clone.style.display = '';

    if (target) {
        const lane = target.closest('.track-lane');
        if (lane) lane.classList.add('drag-over');
    }
}

function _endBinDrag(e) {
    if (!binDrag.active) return;
    binDrag.active = false;

    const pt = e.changedTouches ? e.changedTouches[0] : e;
    binDrag.clone.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);

    if (binDrag.clone.parentNode) binDrag.clone.parentNode.removeChild(binDrag.clone);
    binDrag.clone = null;

    document.querySelectorAll('.track-lane').forEach(l => l.classList.remove('drag-over'));

    if (!target) { openBin(); return; }

    const laneEl   = target.closest('.track-lane');
    const scrollEl = target.closest('.ved-timeline-scroll');

    if (laneEl || scrollEl) {
        const asset = STATE.binData[binDrag.assetIdx];
        if (!asset) return;

        const content = document.getElementById('vedTimelineContent');
        const rect    = content.getBoundingClientRect();
        let localX    = pt.clientX - rect.left - TRACK_HEAD_W;
        if (localX < 0) localX = 0;

        const dropTime = snapTime(localX / STATE.masterZoom);
        let trackId;
        if (laneEl) {
            trackId = parseInt(laneEl.id.replace('lane-', ''));
        } else {
            trackId = addTrackLaneNoHistory(asset.name || 'Video Track');
        }
        addClip(trackId, asset, dropTime);
    } else {
        openBin();
    }
}

document.addEventListener('touchstart', startBinDrag, { passive: false });
document.addEventListener('mousedown',  startBinDrag);
document.addEventListener('touchmove',  _moveBinDrag, { passive: false });
document.addEventListener('mousemove',  _moveBinDrag);
document.addEventListener('touchend',   _endBinDrag);
document.addEventListener('mouseup',    _endBinDrag);


