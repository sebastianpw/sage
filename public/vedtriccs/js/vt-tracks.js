// public/vedtriccs/js/vt-tracks.js
// Track lane and clip CRUD — VedTriccs Edition

'use strict';

// ─── Add Track ────────────────────────────────────────────────────────────────
function vtAddTrack(name = 'Video Track') {
    const id    = ++VT_STATE.trackIdSeq;
    const color = vtNextColor();
    const track = { id, name, color, vol: 1, muted: false };
    VT_STATE.tracks.push(track);

    const row = document.createElement('div');
    row.className = 'vt-track';
    row.id        = 'vt-track-' + id;
    row.innerHTML = `
        <div class="track-head">
            <div class="track-head-top">
                <div class="track-color-strip" style="background:${color};"></div>
                <div class="track-name" title="${vtEsc(name)}">${vtEsc(name)}</div>
                <div class="track-btns">
                    <button class="tk-btn" id="mute-${id}" onclick="vtToggleMute(${id})" title="Mute">M</button>
                    <button class="tk-btn" onclick="vtRemoveTrack(${id})" title="Remove" style="font-size:9px;">✕</button>
                </div>
            </div>
            <div class="track-vol-wrap">
                <i class="bi bi-volume-up" style="color:var(--text-dim);font-size:9px;"></i>
                <input type="range" class="track-vol" min="0" max="1" step="0.01" value="1"
                    oninput="vtSetTrackVol(${id}, this.value)">
            </div>
        </div>
        <div class="track-lane" id="lane-${id}"></div>
    `;
    document.getElementById('vtTimelineContent').appendChild(row);

    if (!vtHistoryGuard()) {
        vtHistoryPush('Add track "' + name + '"',
            () => vtRemoveTrack(id),
            () => { /* redo is handled via full deserialization */ }
        );
    }
    vtUpdateMasterLayout();
    return id;
}

function vtAddTrackNoHistory(name) {
    VT_HISTORY._skipPush = true;
    const id = vtAddTrack(name);
    VT_HISTORY._skipPush = false;
    return id;
}

// ─── Add Clip ─────────────────────────────────────────────────────────────────
function vtAddClip(trackId, videoData, startTime = 0) {
    const track = VT_STATE.tracks.find(t => t.id === trackId);
    if (!track) return null;

    const clipId = ++VT_STATE.clipIdSeq;
    const clip   = {
        id:            clipId,
        trackId,
        url:           videoData.url,
        name:          videoData.name || videoData.url,
        thumbnail:     videoData.thumbnail || '',
        duration:      parseFloat(videoData.duration) || 0,
        startTime,
        trimStart:     0,
        trimEnd:       null,
        playbackSpeed: 1.0,
        color:         track.color,
        el:            null,
    };
    VT_STATE.clips.push(clip);

    const lane = document.getElementById('lane-' + trackId);
    if (!lane) return null;

    const el   = document.createElement('div');
    el.className = 'vt-clip';
    el.id        = 'clip-' + clipId;

    const visW = Math.max(12, vtClipVisualDuration(clip) * VT_STATE.masterZoom);
    el.style.left  = (startTime * VT_STATE.masterZoom) + 'px';
    el.style.width = visW + 'px';

    el.innerHTML = `
        <div class="clip-color-bar" style="background:${track.color};"></div>
        <div class="clip-header">
            <span class="clip-header-name">${vtEsc(clip.name)}</span>
            <i class="bi bi-scissors clip-trim-btn" onclick="vtOpenTrimModal(${clipId})" title="Trim/Speed"></i>
            <i class="bi bi-x clip-del" onclick="vtRemoveClip(${clipId}, event)" title="Remove"></i>
        </div>
        <div class="clip-thumb-area">
            ${clip.thumbnail ? `<img class="clip-thumb" src="/${clip.thumbnail}" alt="" draggable="false">` : ''}
            <div class="clip-thumb-icon"><i class="bi bi-film"></i></div>
        </div>
        <div class="clip-trim-start" style="display:none;"></div>
        <div class="clip-trim-end"   style="display:none;"></div>
        <div class="clip-resize-l" data-clipid="${clipId}" data-side="l"></div>
        <div class="clip-resize-r" data-clipid="${clipId}" data-side="r"></div>
    `;

    el.addEventListener('mousedown',  e => vtStartClipDrag(e, clip));
    el.addEventListener('touchstart', e => vtStartClipDrag(e, clip), { passive: false });
    el.addEventListener('click',      e => vtSelectClip(clip, e));

    lane.appendChild(el);
    clip.el = el;

    vtUpdateClipTrimIndicators(clip);

    if (!vtHistoryGuard()) {
        vtHistoryPush(
            'Add clip "' + clip.name + '"',
            () => vtRemoveClipInternal(clipId),
            () => vtAddClipNoHistory(trackId, videoData, startTime, clip)
        );
    }
    vtUpdateMasterLayout();
    return clipId;
}

function vtAddClipNoHistory(trackId, videoData, startTime, clipSnap = null) {
    VT_HISTORY._skipPush = true;
    const id = vtAddClip(trackId, videoData, startTime);
    if (id && clipSnap) {
        const c = VT_STATE.clips.find(x => x.id === id);
        if (c) {
            c.trimStart     = clipSnap.trimStart || 0;
            c.trimEnd       = clipSnap.trimEnd   || null;
            c.playbackSpeed = clipSnap.playbackSpeed || 1.0;
            vtRefreshClipEl(c);
        }
    }
    VT_HISTORY._skipPush = false;
    return id;
}

// ─── Add Clip from Bin ────────────────────────────────────────────────────────
function vtAddClipFromBin(assetIdx) {
    const a = VT_STATE.binData[assetIdx];
    if (!a) return;
    let trackId;
    if (!VT_STATE.tracks.length) {
        trackId = vtAddTrackNoHistory(a.name || 'Video Track');
    } else {
        trackId = VT_STATE.tracks[VT_STATE.tracks.length - 1].id;
    }
    let startTime = 0;
    VT_STATE.clips.filter(c => c.trackId === trackId).forEach(c => {
        const end = c.startTime + vtClipVisualDuration(c);
        if (end > startTime) startTime = end;
    });
    vtAddClip(trackId, a, startTime);
    Toast.show('Added: ' + vtTrunc(a.name || a.url, 28), 'success');
}

// ─── Select Clip ──────────────────────────────────────────────────────────────
function vtSelectClip(clip, e) {
    if (e && (e.target.classList.contains('clip-del') ||
              e.target.classList.contains('clip-trim-btn') ||
              e.target.classList.contains('vt-connector-pip') ||
              e.target.closest('.clip-resize-l') ||
              e.target.closest('.clip-resize-r'))) return;

    VT_STATE.selectedClipId = clip.id;
    document.querySelectorAll('.vt-clip').forEach(el => el.classList.remove('selected'));
    if (clip.el) clip.el.classList.add('selected');
    vtPreviewClip(clip);
}

// ─── Trim Indicators ─────────────────────────────────────────────────────────
function vtUpdateClipTrimIndicators(clip) {
    if (!clip.el) return;
    const startBar = clip.el.querySelector('.clip-trim-start');
    const endBar   = clip.el.querySelector('.clip-trim-end');
    if (startBar) startBar.style.display = clip.trimStart > 0 ? '' : 'none';
    if (endBar)   endBar.style.display   = clip.trimEnd !== null ? '' : 'none';
}

function vtRefreshClipEl(clip) {
    if (!clip.el) return;
    clip.el.style.left  = (clip.startTime * VT_STATE.masterZoom) + 'px';
    clip.el.style.width = Math.max(12, vtClipVisualDuration(clip) * VT_STATE.masterZoom) + 'px';
    vtUpdateClipTrimIndicators(clip);
}

// ─── Remove Track / Clip ──────────────────────────────────────────────────────
function vtRemoveTrack(id) {
    const track = VT_STATE.tracks.find(t => t.id === id);
    if (!track) return;
    const clipsSnap = VT_STATE.clips.filter(c => c.trackId === id).map(c => ({...c}));
    const trackSnap = { ...track };
    [...VT_STATE.clips].filter(c => c.trackId === id).forEach(c => vtRemoveClipInternal(c.id));
    VT_STATE.tracks.splice(VT_STATE.tracks.findIndex(t => t.id === id), 1);
    document.getElementById('vt-track-' + id)?.remove();
    vtUpdateMasterLayout();
    if (!vtHistoryGuard()) {
        vtHistoryPush(
            'Remove track "' + trackSnap.name + '"',
            () => {
                const nid = vtAddTrackNoHistory(trackSnap.name);
                clipsSnap.forEach(cs => vtAddClipNoHistory(nid, { url:cs.url, name:cs.name, thumbnail:cs.thumbnail, duration:cs.duration }, cs.startTime, cs));
            },
            () => { const t = VT_STATE.tracks.find(t => t.name === trackSnap.name); if (t) vtRemoveTrack(t.id); }
        );
    }
}

function vtRemoveClip(id, event = null) {
    if (event) { event.stopPropagation(); event.preventDefault(); }
    const clip = VT_STATE.clips.find(c => c.id === id);
    if (!clip) return;
    const snap = { ...clip };
    vtRemoveClipInternal(id);
    vtUpdateMasterLayout();
    if (!vtHistoryGuard()) {
        vtHistoryPush(
            'Remove clip "' + snap.name + '"',
            () => vtAddClipNoHistory(snap.trackId, { url:snap.url, name:snap.name, thumbnail:snap.thumbnail, duration:snap.duration }, snap.startTime, snap),
            () => { const c = VT_STATE.clips.find(x => x.startTime === snap.startTime && x.trackId === snap.trackId); if (c) { vtRemoveClipInternal(c.id); vtUpdateMasterLayout(); } }
        );
    }
}

// ─── Split Clip ───────────────────────────────────────────────────────────────
function vtSplitClipAtTime(clip, globalTime) {
    const splitLocal = globalTime - clip.startTime;
    if (splitLocal <= 0.05 || splitLocal >= vtClipVisualDuration(clip) - 0.05) return;
    const speed      = clip.playbackSpeed || 1.0;
    const mediaSplit = clip.trimStart + (splitLocal * speed);
    const origTrimEnd = clip.trimEnd;
    clip.trimEnd = mediaSplit;
    vtRefreshClipEl(clip);
    const rightVideoData = { url: clip.url, name: clip.name, thumbnail: clip.thumbnail, duration: clip.duration };
    const rightStart     = clip.startTime + splitLocal;
    if (!vtHistoryGuard()) {
        const leftId = clip.id;
        vtHistoryPush('Split clip "' + clip.name + '"',
            () => {
                const l = VT_STATE.clips.find(x => x.id === leftId);
                if (l) { l.trimEnd = origTrimEnd; vtRefreshClipEl(l); }
                VT_STATE.clips.filter(c => c.url === clip.url && c.startTime === rightStart).forEach(c => vtRemoveClipInternal(c.id));
                vtUpdateMasterLayout();
            },
            () => {
                const l = VT_STATE.clips.find(x => x.id === leftId);
                if (l) { l.trimEnd = mediaSplit; vtRefreshClipEl(l); }
                vtAddClipNoHistory(clip.trackId, rightVideoData, rightStart, { trimStart:mediaSplit, trimEnd:origTrimEnd, playbackSpeed:clip.playbackSpeed, duration:clip.duration });
            }
        );
    }
    vtAddClipNoHistory(clip.trackId, rightVideoData, rightStart, {
        trimStart: mediaSplit, trimEnd: origTrimEnd,
        playbackSpeed: clip.playbackSpeed, duration: clip.duration,
    });
}

// ─── Track Vol / Mute ─────────────────────────────────────────────────────────
function vtToggleMute(id) {
    const t = VT_STATE.tracks.find(t => t.id === id);
    if (!t) return;
    t.muted = !t.muted;
    document.getElementById('mute-' + id)?.classList.toggle('muted', t.muted);
}
function vtSetTrackVol(id, val) {
    const t = VT_STATE.tracks.find(t => t.id === id);
    if (t) t.vol = parseFloat(val);
}
