// public/ved/js/ved-tracks.js
// Track lane and clip CRUD, serialization/deserialization, trim modal wiring

'use strict';

// ─── Add Track ────────────────────────────────────────────────────────────────
function addTrackLane(name = 'Video Track') {
    const id    = ++STATE.trackIdSeq;
    const color = nextColor();
    const track = { id, name, color, vol: 1, muted: false };
    STATE.tracks.push(track);

    const row = document.createElement('div');
    row.className = 'ved-track';
    row.id = 'track-' + id;

    row.innerHTML = `
        <div class="track-head">
            <div class="track-head-top">
                <div class="track-color-strip" style="background:${color};"></div>
                <div class="track-name" title="${esc(name)}">${esc(name)}</div>
                <div class="track-btns">
                    <button class="tk-btn" id="mute-${id}" onclick="toggleMute(${id})" title="Mute">M</button>
                    <button class="tk-btn" onclick="removeTrack(${id})" title="Remove Track" style="font-size:9px;">✕</button>
                </div>
            </div>
            <div class="track-vol-wrap">
                <i class="bi bi-volume-up" style="color:var(--text-dim);font-size:9px;"></i>
                <input type="range" class="track-vol" min="0" max="1" step="0.01" value="1"
                    oninput="setTrackVol(${id}, this.value)">
            </div>
        </div>
        <div class="track-lane" id="lane-${id}"></div>
    `;

    document.getElementById('vedTimelineContent').appendChild(row);

    if (!_historyGuard()) {
        historyPush(
            'Add track "' + name + '"',
            () => removeTrack(id),
            () => { /* redo re-adds from serialization — no-op needed */ }
        );
    }

    updateMasterLayout();
    return id;
}

// ─── Add Clip ─────────────────────────────────────────────────────────────────
function addClip(trackId, videoData, startTime = 0) {
    const track = STATE.tracks.find(t => t.id === trackId);
    if (!track) return null;

    const clipId = ++STATE.clipIdSeq;
    const clip = {
        id:            clipId,
        trackId,
        url:           videoData.url,
        name:          videoData.name || videoData.url,
        thumbnail:     videoData.thumbnail || '',
        duration:      parseFloat(videoData.duration) || 0,
        startTime,
        trimStart:     0,
        trimEnd:       null,   // null = use full duration
        playbackSpeed: 1.0,
        color:         track.color,
        el:            null,
    };
    STATE.clips.push(clip);

    const lane = document.getElementById('lane-' + trackId);
    if (!lane) return null;

    const el = document.createElement('div');
    el.className = 'ved-clip';
    el.id = 'clip-' + clipId;

    const visW = Math.max(12, clipVisualDuration(clip) * STATE.masterZoom);
    el.style.left  = (startTime * STATE.masterZoom) + 'px';
    el.style.width = visW + 'px';

    el.innerHTML = `
        <div class="clip-color-bar" style="background:${track.color};"></div>
        <div class="clip-header">
            <span class="clip-header-name">${esc(clip.name)}</span>
            <i class="bi bi-scissors clip-trim-btn" onclick="openTrimModal(${clipId})" title="Trim / Speed"></i>
            <i class="bi bi-x clip-del" onclick="removeClip(${clipId}, event)" title="Remove"></i>
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

    el.addEventListener('mousedown',  e => startClipDrag(e, clip));
    el.addEventListener('touchstart', e => startClipDrag(e, clip), { passive: false });
    el.addEventListener('click',      e => selectClip(clip, e));

    lane.appendChild(el);
    clip.el = el;

    _updateClipTrimIndicators(clip);

    if (!_historyGuard()) {
        historyPush(
            'Add clip "' + clip.name + '"',
            () => removeClipInternal(clipId),
            () => addClipNoHistory(trackId, videoData, startTime, clip)
        );
    }

    updateMasterLayout();
    return clipId;
}

// Internal variant that restores full clip state (for redo)
function addClipNoHistory(trackId, videoData, startTime, clipSnap = null) {
    HISTORY._skipPush = true;
    const id = addClip(trackId, videoData, startTime);
    if (id && clipSnap) {
        const c = STATE.clips.find(x => x.id === id);
        if (c) {
            c.trimStart     = clipSnap.trimStart || 0;
            c.trimEnd       = clipSnap.trimEnd   || null;
            c.playbackSpeed = clipSnap.playbackSpeed || 1.0;
            _refreshClipEl(c);
        }
    }
    HISTORY._skipPush = false;
    return id;
}

function addTrackLaneNoHistory(name) {
    HISTORY._skipPush = true;
    const id = addTrackLane(name);
    HISTORY._skipPush = false;
    return id;
}

// ─── Clip from Bin asset ──────────────────────────────────────────────────────
function addClipFromBin(assetIdx) {
    const a = STATE.binData[assetIdx];
    if (!a) return;

    // Auto-create a track if none exist
    let trackId;
    if (!STATE.tracks.length) {
        trackId = addTrackLaneNoHistory(a.name || 'Video Track');
    } else {
        trackId = STATE.tracks[STATE.tracks.length - 1].id;
    }

    // Place at end of existing content on this track
    let startTime = 0;
    STATE.clips.filter(c => c.trackId === trackId).forEach(c => {
        const end = c.startTime + clipVisualDuration(c);
        if (end > startTime) startTime = end;
    });

    addClip(trackId, a, startTime);
    Toast.show('Added: ' + trunc(a.name || a.url, 28), 'success');
}

// ─── Select Clip ──────────────────────────────────────────────────────────────
function selectClip(clip, e) {
    if (e && (e.target.classList.contains('clip-del') ||
              e.target.classList.contains('clip-trim-btn') ||
              e.target.closest('.clip-resize-l') ||
              e.target.closest('.clip-resize-r'))) return;

    STATE.selectedClipId = clip.id;
    document.querySelectorAll('.ved-clip').forEach(el => el.classList.remove('selected'));
    if (clip.el) clip.el.classList.add('selected');
    previewClip(clip);
}

// ─── Trim Indicators ──────────────────────────────────────────────────────────
function _updateClipTrimIndicators(clip) {
    if (!clip.el) return;
    const startBar = clip.el.querySelector('.clip-trim-start');
    const endBar   = clip.el.querySelector('.clip-trim-end');
    if (!startBar || !endBar) return;
    startBar.style.display = clip.trimStart > 0 ? '' : 'none';
    endBar.style.display   = clip.trimEnd !== null ? '' : 'none';
}

function _refreshClipEl(clip) {
    if (!clip.el) return;
    clip.el.style.left  = (clip.startTime * STATE.masterZoom) + 'px';
    clip.el.style.width = Math.max(12, clipVisualDuration(clip) * STATE.masterZoom) + 'px';
    _updateClipTrimIndicators(clip);
}

// ─── Remove Track / Clip ──────────────────────────────────────────────────────
function removeTrack(id) {
    const track = STATE.tracks.find(t => t.id === id);
    if (!track) return;

    const clipsSnap = STATE.clips.filter(c => c.trackId === id).map(c => ({...c}));
    const trackSnap = { ...track };

    [...STATE.clips].filter(c => c.trackId === id).forEach(c => removeClipInternal(c.id));
    STATE.tracks.splice(STATE.tracks.findIndex(t => t.id === id), 1);
    const el = document.getElementById('track-' + id);
    if (el) el.remove();

    updateMasterLayout();

    if (!_historyGuard()) {
        historyPush(
            'Remove track "' + trackSnap.name + '"',
            () => {
                const nid = addTrackLaneNoHistory(trackSnap.name);
                clipsSnap.forEach(cs => addClipNoHistory(nid, { url: cs.url, name: cs.name, thumbnail: cs.thumbnail, duration: cs.duration }, cs.startTime, cs));
            },
            () => {
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

    const snap = { ...clip };
    removeClipInternal(id);
    updateMasterLayout();

    if (!_historyGuard()) {
        historyPush(
            'Remove clip "' + snap.name + '"',
            () => addClipNoHistory(snap.trackId, { url: snap.url, name: snap.name, thumbnail: snap.thumbnail, duration: snap.duration }, snap.startTime, snap),
            () => { const c = STATE.clips.find(x => x.startTime === snap.startTime && x.trackId === snap.trackId); if (c) removeClipInternal(c.id); updateMasterLayout(); }
        );
    }
}

function removeClipInternal(id) {
    const idx = STATE.clips.findIndex(c => c.id === id);
    if (idx === -1) return;
    const clip = STATE.clips[idx];
    if (clip.el) clip.el.remove();
    STATE.clips.splice(idx, 1);
    if (STATE.selectedClipId === id) STATE.selectedClipId = null;
}

// ─── Split Clip ───────────────────────────────────────────────────────────────
function splitClipAtTime(clip, globalTime) {
    const splitLocal = globalTime - clip.startTime; // seconds into the clip's visual timeline
    if (splitLocal <= 0.05 || splitLocal >= clipVisualDuration(clip) - 0.05) return;

    // Convert visual split point back to media time
    const speed = clip.playbackSpeed || 1.0;
    const mediaSplit = clip.trimStart + (splitLocal * speed); // media time of split

    // Left half: trimEnd = mediaSplit
    const origTrimEnd    = clip.trimEnd;
    const origDuration   = clip.duration;
    clip.trimEnd = mediaSplit;
    _refreshClipEl(clip);

    // Right half: starts at mediaSplit, startTime adjusted
    const rightVideoData = { url: clip.url, name: clip.name, thumbnail: clip.thumbnail, duration: clip.duration };
    const rightStart     = clip.startTime + splitLocal;

    if (!_historyGuard()) {
        const leftId = clip.id;
        const snapLeft  = { trimEnd: origTrimEnd };
        historyPush(
            'Split clip "' + clip.name + '"',
            () => {
                const l = STATE.clips.find(x => x.id === leftId);
                if (l) { l.trimEnd = snapLeft.trimEnd; _refreshClipEl(l); }
                // Remove all clips that were added after (crude: remove by startTime)
                const toRemove = STATE.clips.filter(c => c.url === clip.url && c.startTime === rightStart);
                toRemove.forEach(c => removeClipInternal(c.id));
                updateMasterLayout();
            },
            () => {
                const l = STATE.clips.find(x => x.id === leftId);
                if (l) { l.trimEnd = mediaSplit; _refreshClipEl(l); }
                addClipNoHistory(clip.trackId, rightVideoData, rightStart, {
                    trimStart: mediaSplit, trimEnd: origTrimEnd,
                    playbackSpeed: clip.playbackSpeed, duration: origDuration,
                });
            }
        );
    }

    addClipNoHistory(clip.trackId, rightVideoData, rightStart, {
        trimStart: mediaSplit,
        trimEnd:   origTrimEnd,
        playbackSpeed: clip.playbackSpeed,
        duration: origDuration,
    });
}

// ─── Track Vol / Mute ─────────────────────────────────────────────────────────
function toggleMute(id) {
    const t = STATE.tracks.find(t => t.id === id);
    if (!t) return;
    t.muted = !t.muted;
    const btn = document.getElementById('mute-' + id);
    if (btn) btn.classList.toggle('muted', t.muted);
}

function setTrackVol(id, val) {
    const t = STATE.tracks.find(t => t.id === id);
    if (t) t.vol = parseFloat(val);
}

// ─── Clear All ────────────────────────────────────────────────────────────────
function vedClearAll(force = false) {
    if (!STATE.tracks.length) return;
    if (!force && !confirm('Remove all tracks and clips?')) return;
    [...STATE.clips].forEach(c => removeClipInternal(c.id));
    STATE.clips  = [];
    [...STATE.tracks].forEach(t => {
        const el = document.getElementById('track-' + t.id);
        if (el) el.remove();
    });
    STATE.tracks     = [];
    STATE.trackIdSeq = 0;
    STATE.clipIdSeq  = 0;
    STATE.curTime    = 0;
    historyReset();
    updateMasterLayout();
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
    const binLbl = document.getElementById('binAnimaticName');
    const name   = STATE.animaticName || (STATE.animaticId ? '#' + STATE.animaticId : '— no animatic —');
    if (lbl)    lbl.textContent = name;
    if (binLbl) binLbl.textContent = name;
}