// daw/js/daw-drag.js
// Clip drag-within-timeline and bin→timeline drag

'use strict';

// ─── Clip Drag (move clips within the timeline) ──────────────────────────────
let clipDrag = { active: false, clip: null, startX: 0, initLeft: 0, initTrackId: 0, initTime: 0 };

function startClipDrag(e, clip) {
    if (e.target.classList.contains('clip-del')) return;

    if (STATE.editMode === 'rem') {
        e.preventDefault(); e.stopPropagation();
        removeClip(clip.id);
        return;
    }

    if (STATE.editMode === 'cut') {
        e.preventDefault(); e.stopPropagation();
        cutClipAtEvent(e, clip);
        return;
    }

    // Don't drag when envelope mode is active — user is editing points
    if (clip.el && clip.el.classList.contains('envelope-mode')) return;
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

function cutClipAtEvent(e, clip) {
    const pt = e.touches ? e.touches[0] : e;
    const clipRect = clip.el.getBoundingClientRect();
    const clickX = pt.clientX - clipRect.left;
    
    let splitTimeInClip = clickX / STATE.masterZoom;
    if (splitTimeInClip < 0.05 || splitTimeInClip > clip.duration - 0.05) return;

    let globalSplitTime = clip.startTime + splitTimeInClip;

    if (PROJECT.snapEnabled) {
        globalSplitTime = snapTime(globalSplitTime);
        splitTimeInClip = globalSplitTime - clip.startTime;
        if (splitTimeInClip <= 0 || splitTimeInClip >= clip.duration) return;
    }

    const origDuration = clip.duration;
    const leftDuration = splitTimeInClip;
    const rightDuration = origDuration - leftDuration;
    const rightTrimStart = (clip.trimStart || 0) + leftDuration;
    const rightStartTime = clip.startTime + leftDuration;

    clip.duration = leftDuration;
    clip.el.style.width = (clip.duration * STATE.masterZoom) + 'px';
    
    const envPointsClone = JSON.parse(JSON.stringify(clip.envelopePoints || []));

    if (!_historyGuard()) {
        const snapClipId = clip.id;
        const snapDur = origDuration;
        const rightId = STATE.clipIdSeq + 1;
        historyPush(
            'Cut clip "' + clip.name + '"',
            () => {
                removeClipInternal(rightId);
                const c = STATE.clips.find(x => x.id === snapClipId);
                if (c) {
                    c.duration = snapDur;
                    if (c.el) c.el.style.width = (c.duration * STATE.masterZoom) + 'px';
                }
                updateMasterLayout();
            },
            () => {
                const c = STATE.clips.find(x => x.id === snapClipId);
                if (c) {
                    c.duration = leftDuration;
                    if (c.el) c.el.style.width = (c.duration * STATE.masterZoom) + 'px';
                }
                addClipNoHistory(clip.trackId, clip.url, clip.name, rightStartTime, envPointsClone, rightTrimStart, rightDuration);
            }
        );
    }
    
    addClipNoHistory(clip.trackId, clip.url, clip.name, rightStartTime, envPointsClone, rightTrimStart, rightDuration);
    
    if (STATE.isPlaying) dawPause();
}

function _moveClipDrag(e) {
    if (!clipDrag.active || !clipDrag.clip) return;

    const pt = e.touches ? e.touches[0] : e;
    const dx = pt.clientX - clipDrag.startX;

    let newLeft = Math.max(0, clipDrag.initLeft + dx);
    let newTime = newLeft / STATE.masterZoom;
    newTime     = snapTime(newTime);
    newLeft     = newTime * STATE.masterZoom;

    clipDrag.clip.el.style.left  = newLeft + 'px';
    clipDrag.clip.startTime      = newTime;

    clipDrag.clip.el.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    clipDrag.clip.el.style.display = '';

    if (target) {
        const lane = target.closest('.track-lane');
        if (lane) {
            const targetTrackId = parseInt(lane.id.replace('lane-', ''));
            if (targetTrackId !== clipDrag.clip.trackId) {
                clipDrag.clip.trackId = targetTrackId;
                lane.appendChild(clipDrag.clip.el);
            }
        }
    }
}

function _endClipDrag() {
    if (!clipDrag.active) return;
    clipDrag.clip.el.classList.remove('dragging');
    clipDrag.active = false;

    const clip       = clipDrag.clip;
    const newTime    = clip.startTime;
    const newTrackId = clip.trackId;
    const oldTime    = clipDrag.initTime;
    const oldTrackId = clipDrag.initTrackId;

    // Only record history if something actually changed
    if (!_historyGuard() && (newTime !== oldTime || newTrackId !== oldTrackId)) {
        historyPush(
            'Move clip "' + clip.name + '"',
            () => {
                // undo: move back
                clip.startTime = oldTime;
                clip.trackId   = oldTrackId;
                clip.el.style.left = (oldTime * STATE.masterZoom) + 'px';
                const oldLane = document.getElementById('lane-' + oldTrackId);
                if (oldLane) oldLane.appendChild(clip.el);
                updateMasterLayout();
            },
            () => {
                // redo: move forward again
                clip.startTime = newTime;
                clip.trackId   = newTrackId;
                clip.el.style.left = (newTime * STATE.masterZoom) + 'px';
                const newLane = document.getElementById('lane-' + newTrackId);
                if (newLane) newLane.appendChild(clip.el);
                updateMasterLayout();
            }
        );
    }

    updateMasterLayout();
}

document.addEventListener('mousemove', _moveClipDrag);
document.addEventListener('mouseup',   _endClipDrag);
document.addEventListener('touchmove', _moveClipDrag, {passive: false});
document.addEventListener('touchend',  _endClipDrag);


// ─── Bin → Timeline Drag ──────────────────────────────────────────────────────
let binDrag = { active: false, clone: null, assetIdx: null, offsetX: 0, offsetY: 0 };

function startBinDrag(e) {
    const handle = e.target.closest('.ba-drag-handle');
    if (!handle) return;
    e.preventDefault();

    binDrag.assetIdx = handle.getAttribute('data-drag-idx');
    binDrag.active   = true;

    const item = handle.closest('.bin-asset-item');
    binDrag.clone = item.cloneNode(true);
    Object.assign(binDrag.clone.style, {
        position: 'fixed', zIndex: '9999',
        background: 'var(--surface)', border: '1px solid var(--amber)',
        width: item.offsetWidth + 'px', opacity: '0.9', pointerEvents: 'none',
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
    document.querySelectorAll('.daw-clip').forEach(c => c.classList.remove('drag-over'));

    binDrag.clone.style.display = 'none';
    const target = document.elementFromPoint(pt.clientX, pt.clientY);
    binDrag.clone.style.display = '';

    if (target) {
        const clip = target.closest('.daw-clip');
        const lane = target.closest('.track-lane');
        if (clip) clip.classList.add('drag-over');
        else if (lane) lane.classList.add('drag-over');
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
    document.querySelectorAll('.daw-clip').forEach(c => c.classList.remove('drag-over'));

    if (target) {
        const clipEl   = target.closest('.daw-clip');
        const laneEl   = target.closest('.track-lane');
        const scrollEl = target.closest('.daw-timeline-scroll');

        if (clipEl) {
            replaceClipAudio(parseInt(clipEl.id.replace('clip-', '')), binDrag.assetIdx);
        } else if (laneEl || scrollEl) {
            const asset = STATE.assetData[binDrag.assetIdx];
            if (!asset) return;

            const contentRect = document.getElementById('dawTimelineContent').getBoundingClientRect();
            let localX  = pt.clientX - contentRect.left - TRACK_HEAD_W;
            if (localX < 0) localX = 0;

            const dropTime = snapTime(localX / STATE.masterZoom);
            let trackId;
            if (laneEl) {
                trackId = parseInt(laneEl.id.replace('lane-', ''));
            } else {
                trackId = addTrackLane(asset.name);
            }
            addClip(trackId, asset.filename, asset.name, dropTime);
        } else {
            openBin();
        }
    } else {
        openBin();
    }
}

document.addEventListener('touchstart', startBinDrag, {passive: false});
document.addEventListener('mousedown',  startBinDrag);
document.addEventListener('touchmove',  _moveBinDrag, {passive: false});
document.addEventListener('mousemove',  _moveBinDrag);
document.addEventListener('touchend',   _endBinDrag);
document.addEventListener('mouseup',    _endBinDrag);



