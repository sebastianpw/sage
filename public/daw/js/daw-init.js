// public/daw/js/daw-init.js
// Bootstrap: wires up all event listeners after DOM is ready

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // Swatch builder
    buildSwatches();
    syncMenuBar();
    _syncHistoryUI();

    // Initial data load
    loadEntities(1);
    if (STATE.entityId) loadAssetsForEntity(STATE.entityId);

    // ── Sync ruler scroll to timeline scroll ──────────────────────────────
    document.getElementById('timelineScroll').addEventListener('scroll', (e) => {
        document.getElementById('rulerWrap').scrollLeft = e.target.scrollLeft;
    });

    // ── Seek by clicking the timeline background or ruler ─────────────────
    function seekFromPointer(e) {
        if (e.target.closest('.daw-clip')) return;
        if (e.target.closest('.track-head')) return;

        const content = document.getElementById('dawTimelineContent');
        const rect    = content.getBoundingClientRect();

        let contentX = (e.clientX - rect.left) - TRACK_HEAD_W;
        if (contentX < 0) contentX = 0;

        const targetTime = snapTime(contentX / STATE.masterZoom);
        seekTimeline(targetTime);
    }

    document.getElementById('dawTimelineContent').addEventListener('mousedown', seekFromPointer);

    document.getElementById('rulerWrap').addEventListener('mousedown', (e) => {
        const wrap   = document.getElementById('rulerWrap');
        const rect   = wrap.getBoundingClientRect();
        let contentX = (e.clientX - rect.left) + wrap.scrollLeft;
        if (contentX < 0) contentX = 0;
        const targetTime = snapTime(contentX / STATE.masterZoom);
        seekTimeline(targetTime);
    });

    // ── Pinch-to-zoom on the timeline ─────────────────────────────────────
    let pinch = { active: false, startDist: 0, startZoom: 0 };
    function getDist(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }
    const tl = document.getElementById('timelineScroll');
    tl.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            pinch.active    = true;
            pinch.startDist = getDist(e.touches);
            pinch.startZoom = STATE.masterZoom;
            e.preventDefault();
        }
    }, {passive: false});
    tl.addEventListener('touchmove', (e) => {
        if (pinch.active && e.touches.length === 2) {
            e.preventDefault();
            const scale = getDist(e.touches) / pinch.startDist;
            const newZ  = Math.max(10, Math.min(300, pinch.startZoom * scale));
            dawSetZoom(newZ);
        }
    }, {passive: false});
    tl.addEventListener('touchend', (e) => {
        if (e.touches.length < 2) pinch.active = false;
    });

    // ── Fullscreen change listener ────────────────────────────────────────
    document.addEventListener('fullscreenchange',       _onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', _onFullscreenChange);

    // ── Initial layout ────────────────────────────────────────────────────
    updateMasterLayout();

    // ── Keyboard shortcuts ────────────────────────────────────────────────
    document.addEventListener('keydown', (e) => {
        if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;

        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
            e.preventDefault(); historyUndo(); return;
        }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) {
            e.preventDefault(); historyRedo(); return;
        }

        if (e.key === ' ')      { e.preventDefault(); dawPlayPause(); }
        if (e.key === 'Escape') {
            closeParamModal();
            closeSaveLoadModal();
            closeMasterModal();
            closeShotSavesModal();
            if (document.getElementById('binPanel').classList.contains('open')) closeBin();
        }
        if (e.key === 'Home')   { e.preventDefault(); dawStop(); }
        if (e.key === 'f' || e.key === 'F') { e.preventDefault(); toggleFullscreen(); }
    });

    // ── Shot/Scene mode: auto-load lanes and wire video player ───────────
    if (window.DAW_INIT_SHOT_ID || window.DAW_INIT_SCENE_ID) {
        _initShotMode();
    }

    // ── Wrap openSaveLoadModal to also load shot saves when in shot mode ─
    // daw-modal.js defines openSaveLoadModal; we extend it here non-destructively.
    const _origOpenSaveLoad = window.openSaveLoadModal;
    window.openSaveLoadModal = function() {
        _origOpenSaveLoad();
        if (window.DAW_INIT_SHOT_ID) _loadShotSavesList();
    };

    // ── Floating video modal: drag + resize ───────────────────────────────
    _initFvModal();
});

function _onFullscreenChange() {
    const isFS = !!(document.fullscreenElement || document.webkitFullscreenElement);
    const shell = document.querySelector('.daw-shell');
    const btn   = document.getElementById('btnFullscreen');
    const icon  = btn ? btn.querySelector('i') : null;
    if (shell) shell.classList.toggle('is-fullscreen', isFS);
    if (icon)  icon.className = isFS ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
}

// ── Shot Mode Initialiser ────────────────────────────────────────────────────

async function _initShotMode() {
    const shotId = window.DAW_INIT_SHOT_ID;
    if (!shotId) return;

    let res;
    try {
        const r = await fetch(`?api_action=load_shot_audio&shot_id=${shotId}`);
        res = await r.json();
    } catch (e) {
        Toast.show('Failed to load shot audio', 'error');
        return;
    }

    if (!res || res.status !== 'success') {
        Toast.show(res?.message || 'Shot audio load error', 'error');
        return;
    }

    // ── Wire the docked video player ───────────────────────────────────────
    if (res.video_url) {
        const vid         = document.getElementById('dawShotVideo');
        const placeholder = document.getElementById('dawShotPlayerPlaceholder');
        if (vid) {
            vid.src = '/' + res.video_url.replace(/^\//, '');
            vid.classList.remove('hidden');
            if (placeholder) placeholder.style.display = 'none';

            vid.addEventListener('play',   () => { if (!STATE.isPlaying) dawPlay(); });
            vid.addEventListener('pause',  () => { if (STATE.isPlaying)  dawPause(); });
            vid.addEventListener('seeked', () => { seekTimeline(vid.currentTime); });
        }
    } else {
        const placeholder = document.getElementById('dawShotPlayerPlaceholder');
        if (placeholder) placeholder.querySelector('span').textContent = 'No video for this shot';
    }

    // ── Auto-populate timeline lanes ───────────────────────────────────────
    if (!res.lanes || res.lanes.length === 0) {
        Toast.show('No audio assets linked to this shot', 'info');
        return;
    }

    res.lanes.forEach(lane => {
        if (!lane.audio_filename) return;
        const trackId = addTrackLane(lane.lane_label);
        addClip(trackId, lane.audio_filename, lane.lane_label, 0);
    });

    Toast.show('Loaded ' + res.lanes.length + ' audio lane' + (res.lanes.length !== 1 ? 's' : ''), 'success');
}

// ── Shot Saves Modal ─────────────────────────────────────────────────────────
// Shot saves are now rendered inside the unified save/load modal.
// These stubs keep any existing callers working.

function openShotSavesModal()  { openSaveLoadModal(); }
function closeShotSavesModal() { closeSaveLoadModal(); }
function onShotSavesBackdropClick(e) { onFileBackdropClick(e); }

function _loadShotSavesList() {
    const shotId = window.DAW_INIT_SHOT_ID;
    const list   = document.getElementById('shotSavesList');
    if (!shotId || !list) return;

    list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">Loading…</div>';

    fetch(`?api_action=get_shot_daw_saves&shot_id=${shotId}`)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success' || !res.data.length) {
                list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">No saves yet for this shot.</div>';
                return;
            }
            list.innerHTML = res.data.map(s => {
                const ts = (s.updated_at || s.created_at || '').substring(0, 16).replace('T', ' ');
                return `<div style="display:flex;align-items:center;gap:6px;
                                    padding:7px 8px;border-bottom:1px solid var(--border);">
                    <div style="flex:1;min-width:0;">
                        <div style="font-family:var(--font-sans);font-size:.76rem;
                                    font-weight:600;color:var(--text);
                                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(s.name)}</div>
                        <div style="font-size:.62rem;color:var(--text-dim);margin-top:1px;">${esc(ts)}</div>
                    </div>
                    <button class="pm-btn pm-btn-cancel"
                            style="height:24px;padding:0 8px;font-size:.65rem;flex-shrink:0;"
                            onclick="overwriteShotDawSave(${s.id}, '${esc(s.name)}')">
                        <i class="bi bi-floppy"></i>
                    </button>
                    <button class="pm-btn pm-btn-apply"
                            style="height:24px;padding:0 8px;font-size:.65rem;flex-shrink:0;"
                            onclick="loadShotDawSave(${s.id})">
                        <i class="bi bi-play-fill"></i> Load
                    </button>
                    <button style="width:24px;height:24px;border-radius:4px;flex-shrink:0;
                                   border:1px solid var(--border2);background:transparent;
                                   color:var(--text-faint);cursor:pointer;display:flex;
                                   align-items:center;justify-content:center;font-size:11px;
                                   transition:all .12s;"
                            onmouseover="this.style.color='var(--red)';this.style.borderColor='var(--red)';"
                            onmouseout="this.style.color='var(--text-faint)';this.style.borderColor='var(--border2)';"
                            onclick="deleteShotDawSave(${s.id})"
                            title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
            }).join('');
        })
        .catch(() => {
            list.innerHTML = '<div style="padding:10px;color:var(--red);text-align:center;font-size:.72rem;">Load error</div>';
        });
}

function saveShotDaw() {
    const shotId = window.DAW_INIT_SHOT_ID;
    if (!shotId) return;
    const nameEl = document.getElementById('shotSaveName');
    const name   = nameEl ? nameEl.value.trim() : '';
    const state  = JSON.stringify(serializeDawState());

    const body = new URLSearchParams({ shot_id: shotId, name, state_data: state });
    fetch('?api_action=save_shot_daw', { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                if (nameEl) nameEl.value = '';
                Toast.show('Shot state saved', 'success');
                _loadShotSavesList();
            } else {
                Toast.show(res.message || 'Save failed', 'error');
            }
        });
}

function overwriteShotDawSave(saveId, saveName) {
    if (!confirm('Overwrite "' + saveName + '" with current state?')) return;
    const state = JSON.stringify(serializeDawState());
    const body  = new URLSearchParams({ save_id: saveId, state_data: state });
    fetch('?api_action=update_shot_daw_save', { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Toast.show('Save overwritten', 'success');
                _loadShotSavesList();
            } else {
                Toast.show(res.message || 'Overwrite failed', 'error');
            }
        });
}

function loadShotDawSave(saveId) {
    fetch(`?api_action=load_shot_daw_save&save_id=${saveId}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                try {
                    const state = JSON.parse(res.data.state_data);
                    deserializeDawState(state);
                    closeSaveLoadModal();
                    Toast.show('Shot state loaded', 'success');
                } catch(e) {
                    Toast.show('Error parsing save data', 'error');
                }
            } else {
                Toast.show(res.message || 'Load failed', 'error');
            }
        });
}

function deleteShotDawSave(saveId) {
    if (!confirm('Delete this save?')) return;
    const body = new URLSearchParams({ save_id: saveId });
    fetch('?api_action=delete_shot_daw_save', { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                Toast.show('Save deleted', 'success');
                _loadShotSavesList();
            } else {
                Toast.show(res.message || 'Delete failed', 'error');
            }
        });
}

// ── Floating Video Modal ─────────────────────────────────────────────────────
// State: 'docked' | 'floating' | 'hidden'
// The video element (#dawShotVideo) is physically moved between the docked
// container (#dawShotPlayerWrap > .fv-body) and the floating modal (#fvBody).

let _fvState = 'docked'; // default when shot mode is active

function _initFvModal() {
    const modal = document.getElementById('fvModal');
    if (!modal) return; // not in shot/scene mode

    _initFvDrag(modal);
    _initFvResize(modal);
}

/**
 * Toggle between docked (above header) and floating modal.
 * Called by the Video button in the menubar.
 */
function toggleVideoFloat() {
    if (_fvState === 'docked') {
        _floatVideo();
    } else if (_fvState === 'floating') {
        dockVideoBack();
    } else {
        // was hidden/closed — re-float
        _floatVideo();
    }
}

function _floatVideo() {
    const modal      = document.getElementById('fvModal');
    const dockedWrap = document.getElementById('dawShotPlayerWrap');
    const fvBody     = document.getElementById('fvBody');
    const vid        = document.getElementById('dawShotVideo');
    const btn        = document.getElementById('btnFloatVideo');
    if (!modal || !fvBody || !vid) return;

    // Move video element into floating body
    fvBody.appendChild(vid);

    // Collapse the docked slot so it takes no space
    if (dockedWrap) dockedWrap.classList.add('docked-hidden');

    // Show the modal
    modal.style.display = 'flex';
    _fvState = 'floating';

    if (btn) { btn.classList.add('active'); }
    updateMasterLayout();
}

function dockVideoBack() {
    const modal      = document.getElementById('fvModal');
    const dockedWrap = document.getElementById('dawShotPlayerWrap');
    const fvBody     = document.getElementById('fvBody');
    const vid        = document.getElementById('dawShotVideo');
    const btn        = document.getElementById('btnFloatVideo');
    if (!dockedWrap || !vid) return;

    // Move video back into docked container
    dockedWrap.appendChild(vid);

    // Restore docked slot
    dockedWrap.classList.remove('docked-hidden');

    // Hide modal
    if (modal) modal.style.display = 'none';
    if (fvBody) fvBody.innerHTML = ''; // clear placeholder content

    _fvState = 'docked';
    if (btn) btn.classList.remove('active');
    updateMasterLayout();
}

function closeFvModal() {
    const modal      = document.getElementById('fvModal');
    const dockedWrap = document.getElementById('dawShotPlayerWrap');
    const fvBody     = document.getElementById('fvBody');
    const vid        = document.getElementById('dawShotVideo');
    const btn        = document.getElementById('btnFloatVideo');

    // Pause video if playing
    if (vid && !vid.paused) vid.pause();

    // Move video back to docked container but keep it collapsed
    if (dockedWrap && vid) {
        dockedWrap.appendChild(vid);
        dockedWrap.classList.add('docked-hidden');
    }

    if (modal)  modal.style.display = 'none';
    if (fvBody) fvBody.innerHTML = '';

    _fvState = 'hidden';
    if (btn) btn.classList.remove('active');
    updateMasterLayout();
}

// ── Floating modal: drag ─────────────────────────────────────────────────────
function _initFvDrag(modal) {
    const titlebar = document.getElementById('fvTitlebar');
    if (!titlebar) return;

    let drag = { active: false, startX: 0, startY: 0, origLeft: 0, origTop: 0 };

    function onStart(e) {
        // Ignore if touch/click landed on a button inside titlebar
        if (e.target.closest('.fv-btn')) return;
        drag.active  = true;
        const pt     = e.touches ? e.touches[0] : e;
        drag.startX  = pt.clientX;
        drag.startY  = pt.clientY;
        // Resolve current position: use getBoundingClientRect for accuracy
        const rect   = modal.getBoundingClientRect();
        drag.origLeft = rect.left;
        drag.origTop  = rect.top;
        // Switch from transform-based centering to explicit left/top
        modal.style.transform = 'none';
        modal.style.left = rect.left + 'px';
        modal.style.top  = rect.top  + 'px';
        e.preventDefault();
    }

    function onMove(e) {
        if (!drag.active) return;
        const pt  = e.touches ? e.touches[0] : e;
        const dx  = pt.clientX - drag.startX;
        const dy  = pt.clientY - drag.startY;
        let newL  = drag.origLeft + dx;
        let newT  = drag.origTop  + dy;
        // Clamp to viewport
        newL = Math.max(0, Math.min(window.innerWidth  - modal.offsetWidth,  newL));
        newT = Math.max(0, Math.min(window.innerHeight - modal.offsetHeight, newT));
        modal.style.left = newL + 'px';
        modal.style.top  = newT + 'px';
        e.preventDefault();
    }

    function onEnd() { drag.active = false; }

    titlebar.addEventListener('mousedown',  onStart, { passive: false });
    titlebar.addEventListener('touchstart', onStart, { passive: false });
    document.addEventListener('mousemove',  onMove,  { passive: false });
    document.addEventListener('touchmove',  onMove,  { passive: false });
    document.addEventListener('mouseup',    onEnd);
    document.addEventListener('touchend',   onEnd);
}

// ── Floating modal: resize ───────────────────────────────────────────────────
function _initFvResize(modal) {
    const handle = document.getElementById('fvResizeHandle');
    if (!handle) return;

    let rsz = { active: false, startX: 0, startY: 0, origW: 0, origH: 0 };

    function onStart(e) {
        rsz.active  = true;
        const pt    = e.touches ? e.touches[0] : e;
        rsz.startX  = pt.clientX;
        rsz.startY  = pt.clientY;
        rsz.origW   = modal.offsetWidth;
        rsz.origH   = modal.offsetHeight;
        e.preventDefault();
        e.stopPropagation();
    }

    function onMove(e) {
        if (!rsz.active) return;
        const pt  = e.touches ? e.touches[0] : e;
        const dx  = pt.clientX - rsz.startX;
        const dy  = pt.clientY - rsz.startY;
        const newW = Math.max(160, rsz.origW + dx);
        const newH = Math.max(120, rsz.origH + dy);
        modal.style.width  = newW + 'px';
        modal.style.height = newH + 'px';
        // Remove aspect-ratio override if any
        modal.style.aspectRatio = 'unset';
        e.preventDefault();
    }

    function onEnd() { rsz.active = false; }

    handle.addEventListener('mousedown',  onStart, { passive: false });
    handle.addEventListener('touchstart', onStart, { passive: false });
    document.addEventListener('mousemove', onMove,  { passive: false });
    document.addEventListener('touchmove', onMove,  { passive: false });
    document.addEventListener('mouseup',   onEnd);
    document.addEventListener('touchend',  onEnd);
}
