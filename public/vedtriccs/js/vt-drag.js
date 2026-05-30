// public/vedtriccs/js/vt-drag.js
// Clip move drag, edge resize, bin→timeline drag — VedTriccs Edition

'use strict';

// ─── Clip Move Drag ───────────────────────────────────────────────────────────
let vtClipDrag = {
    active: false, clip: null,
    startX: 0, initLeft: 0, initTrackId: 0, initTime: 0,
};

function vtStartClipDrag(e, clip) {
    if (e.target.classList.contains('clip-del') ||
        e.target.classList.contains('clip-trim-btn') ||
        e.target.classList.contains('vt-connector-pip') ||
        e.target.closest('.clip-resize-l') ||
        e.target.closest('.clip-resize-r')) return;

    if (VT_STATE.editMode === 'rem') {
        e.preventDefault(); e.stopPropagation();
        vtRemoveClip(clip.id); return;
    }
    if (VT_STATE.editMode === 'cut') {
        e.preventDefault(); e.stopPropagation();
        _vtCutClipAtEvent(e, clip); return;
    }

    e.preventDefault(); e.stopPropagation();
    vtClipDrag.active      = true;
    vtClipDrag.clip        = clip;
    vtClipDrag.initLeft    = clip.startTime * VT_STATE.masterZoom;
    vtClipDrag.initTrackId = clip.trackId;
    vtClipDrag.initTime    = clip.startTime;
    const pt = e.touches ? e.touches[0] : e;
    vtClipDrag.startX = pt.clientX;
    clip.el.classList.add('dragging');
}

function _vtCutClipAtEvent(e, clip) {
    const pt    = e.touches ? e.touches[0] : e;
    const rect  = clip.el.getBoundingClientRect();
    const splitT = vtSnapTime(clip.startTime + (pt.clientX - rect.left) / VT_STATE.masterZoom);
    vtSplitClipAtTime(clip, splitT);
}

function _vtOnClipDragMove(e) {
    if (!vtClipDrag.active || !vtClipDrag.clip) return;
    const pt  = e.touches ? e.touches[0] : e;
    const dx  = pt.clientX - vtClipDrag.startX;
    let newTime = vtSnapTime((vtClipDrag.initLeft + dx) / VT_STATE.masterZoom);
    if (newTime < 0) newTime = 0;
    vtClipDrag.clip.el.style.left = (newTime * VT_STATE.masterZoom) + 'px';
    vtClipDrag.clip.startTime     = newTime;

    // Cross-track drop detection
    vtClipDrag.clip.el.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    vtClipDrag.clip.el.style.display = '';
    if (target) {
        const lane = target.closest('.track-lane');
        if (lane) {
            const tid = parseInt(lane.id.replace('lane-', ''));
            if (tid !== vtClipDrag.clip.trackId) {
                vtClipDrag.clip.trackId = tid;
                lane.appendChild(vtClipDrag.clip.el);
            }
        }
    }
}

function _vtOnClipDragEnd() {
    if (!vtClipDrag.active) return;
    vtClipDrag.clip.el.classList.remove('dragging');
    vtClipDrag.active = false;

    const clip       = vtClipDrag.clip;
    const newTime    = clip.startTime;
    const newTrackId = clip.trackId;
    const oldTime    = vtClipDrag.initTime;
    const oldTrackId = vtClipDrag.initTrackId;

    if (!vtHistoryGuard() && (newTime !== oldTime || newTrackId !== oldTrackId)) {
        vtHistoryPush(
            'Move clip "' + clip.name + '"',
            () => {
                clip.startTime = oldTime; clip.trackId = oldTrackId;
                clip.el.style.left = (oldTime * VT_STATE.masterZoom) + 'px';
                document.getElementById('lane-' + oldTrackId)?.appendChild(clip.el);
                vtUpdateMasterLayout();
            },
            () => {
                clip.startTime = newTime; clip.trackId = newTrackId;
                clip.el.style.left = (newTime * VT_STATE.masterZoom) + 'px';
                document.getElementById('lane-' + newTrackId)?.appendChild(clip.el);
                vtUpdateMasterLayout();
            }
        );
    }
    vtUpdateMasterLayout();
}

document.addEventListener('mousemove',  _vtOnClipDragMove);
document.addEventListener('mouseup',    _vtOnClipDragEnd);
document.addEventListener('touchmove',  _vtOnClipDragMove, { passive: false });
document.addEventListener('touchend',   _vtOnClipDragEnd);

// ─── Clip Edge Resize ─────────────────────────────────────────────────────────
let vtEdgeDrag = {
    active: false, clipId: null, side: null,
    startX: 0, initTrimStart: 0, initTrimEnd: null, initStartTime: 0, initDuration: 0,
};

function _vtStartEdgeDrag(e) {
    const handle = e.target.closest('.clip-resize-l, .clip-resize-r');
    if (!handle) return;
    e.preventDefault(); e.stopPropagation();
    const clipId = parseInt(handle.dataset.clipid);
    const side   = handle.dataset.side;
    const clip   = VT_STATE.clips.find(c => c.id === clipId);
    if (!clip) return;
    const pt = e.touches ? e.touches[0] : e;
    vtEdgeDrag = {
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

function _vtOnEdgeDragMove(e) {
    if (!vtEdgeDrag.active) return;
    const pt   = e.touches ? e.touches[0] : e;
    const dx   = pt.clientX - vtEdgeDrag.startX;
    const ds   = dx / VT_STATE.masterZoom;
    const clip = VT_STATE.clips.find(c => c.id === vtEdgeDrag.clipId);
    if (!clip) return;
    const speed = clip.playbackSpeed || 1.0;
    if (vtEdgeDrag.side === 'l') {
        let newTrimStart = Math.max(0, vtEdgeDrag.initTrimStart + ds * speed);
        const maxTrim    = clip.trimEnd !== null ? clip.trimEnd - 0.1 : clip.duration - 0.1;
        newTrimStart     = Math.min(newTrimStart, maxTrim);
        const timeDelta  = (newTrimStart - vtEdgeDrag.initTrimStart) / speed;
        clip.trimStart   = newTrimStart;
        clip.startTime   = Math.max(0, vtEdgeDrag.initStartTime + timeDelta);
    } else {
        let newTrimEnd = vtEdgeDrag.initTrimEnd !== null
            ? vtEdgeDrag.initTrimEnd + ds * speed
            : clip.duration + ds * speed;
        newTrimEnd = Math.max(clip.trimStart + 0.1, Math.min(clip.duration, newTrimEnd));
        clip.trimEnd = (newTrimEnd >= clip.duration - 0.02) ? null : newTrimEnd;
    }
    vtRefreshClipEl(clip);
}

function _vtOnEdgeDragEnd() {
    if (!vtEdgeDrag.active) return;
    vtEdgeDrag.active = false;
    vtUpdateMasterLayout();
}

document.addEventListener('mousedown',  _vtStartEdgeDrag);
document.addEventListener('touchstart', _vtStartEdgeDrag, { passive: false });
document.addEventListener('mousemove',  _vtOnEdgeDragMove);
document.addEventListener('mouseup',    _vtOnEdgeDragEnd);
document.addEventListener('touchmove',  _vtOnEdgeDragMove, { passive: false });
document.addEventListener('touchend',   _vtOnEdgeDragEnd);

// ─── Bin → Timeline Drag ──────────────────────────────────────────────────────
let vtBinDrag = {
    active: false, clone: null, assetIdx: null, offsetX: 0, offsetY: 0,
};

function vtStartBinDrag(e) {
    const handle = e.target.closest('.vid-drag-handle');
    if (!handle) return;
    e.preventDefault();
    vtBinDrag.assetIdx = parseInt(handle.dataset.idx);
    vtBinDrag.active   = true;
    const item = handle.closest('.vid-item');
    vtBinDrag.clone    = item.cloneNode(true);
    Object.assign(vtBinDrag.clone.style, {
        position: 'fixed', zIndex: '9999',
        background: 'var(--surface)', border: '1px solid var(--amber)',
        width: item.offsetWidth + 'px', opacity: '0.88',
        pointerEvents: 'none', borderRadius: '5px',
    });
    const rect = item.getBoundingClientRect();
    const pt   = e.touches ? e.touches[0] : e;
    vtBinDrag.offsetX = pt.clientX - rect.left;
    vtBinDrag.offsetY = pt.clientY - rect.top;
    vtBinDrag.clone.style.left = (pt.clientX - vtBinDrag.offsetX) + 'px';
    vtBinDrag.clone.style.top  = (pt.clientY - vtBinDrag.offsetY) + 'px';
    document.body.appendChild(vtBinDrag.clone);
    vtCloseBin();
}

function _vtMoveBinDrag(e) {
    if (!vtBinDrag.active || !vtBinDrag.clone) return;
    e.preventDefault();
    const pt = e.touches ? e.touches[0] : e;
    vtBinDrag.clone.style.left = (pt.clientX - vtBinDrag.offsetX) + 'px';
    vtBinDrag.clone.style.top  = (pt.clientY - vtBinDrag.offsetY) + 'px';
    document.querySelectorAll('.track-lane').forEach(l => l.classList.remove('drag-over'));
    vtBinDrag.clone.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    vtBinDrag.clone.style.display = '';
    if (target) { target.closest('.track-lane')?.classList.add('drag-over'); }
}

function _vtEndBinDrag(e) {
    if (!vtBinDrag.active) return;
    vtBinDrag.active = false;
    const pt = e.changedTouches ? e.changedTouches[0] : e;
    vtBinDrag.clone.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    if (vtBinDrag.clone.parentNode) vtBinDrag.clone.parentNode.removeChild(vtBinDrag.clone);
    vtBinDrag.clone = null;
    document.querySelectorAll('.track-lane').forEach(l => l.classList.remove('drag-over'));
    if (!target) { vtOpenBin(); return; }
    const laneEl   = target.closest('.track-lane');
    const scrollEl = target.closest('.vt-timeline-scroll');
    if (laneEl || scrollEl) {
        const asset = VT_STATE.binData[vtBinDrag.assetIdx];
        if (!asset) return;
        const content = document.getElementById('vtTimelineContent');
        const rect    = content.getBoundingClientRect();
        let localX    = pt.clientX - rect.left - VT_TRACK_HEAD_W;
        if (localX < 0) localX = 0;
        const dropTime = vtSnapTime(localX / VT_STATE.masterZoom);
        let trackId;
        if (laneEl) {
            trackId = parseInt(laneEl.id.replace('lane-', ''));
        } else {
            trackId = vtAddTrackNoHistory(asset.name || 'Video Track');
        }
        vtAddClip(trackId, asset, dropTime);
    } else {
        vtOpenBin();
    }
}

document.addEventListener('touchstart', vtStartBinDrag, { passive: false });
document.addEventListener('mousedown',  vtStartBinDrag);
document.addEventListener('touchmove',  _vtMoveBinDrag, { passive: false });
document.addEventListener('mousemove',  _vtMoveBinDrag);
document.addEventListener('touchend',   _vtEndBinDrag);
document.addEventListener('mouseup',    _vtEndBinDrag);
