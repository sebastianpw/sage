// public/vedtriccs/js/vt-init.js
// Bootstrap — wires all event listeners after DOM ready — VedTriccs Edition

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Swatches + History UI ─────────────────────────────────────────────────
    vtBuildSwatches();
    vtSyncHistoryUI();

    // ── Ruler scroll sync ─────────────────────────────────────────────────────
    const tlScroll  = document.getElementById('timelineScroll');
    const rulerWrap = document.getElementById('rulerWrap');
    if (tlScroll && rulerWrap) {
        tlScroll.addEventListener('scroll', e => { rulerWrap.scrollLeft = e.target.scrollLeft; });
    }

    // ── Timeline click to seek ────────────────────────────────────────────────
    const tlContent = document.getElementById('vtTimelineContent');
    if (tlContent) {
        tlContent.addEventListener('mousedown', e => {
            if (e.target.closest('.vt-clip')) return;
            if (e.target.closest('.track-head')) return;
            const rect = tlContent.getBoundingClientRect();
            let localX = e.clientX - rect.left - VT_TRACK_HEAD_W;
            if (localX < 0) localX = 0;
            vtSeekTimeline(vtSnapTime(localX / VT_STATE.masterZoom));
        });
    }
    if (rulerWrap) {
        rulerWrap.addEventListener('mousedown', e => {
            const rect  = rulerWrap.getBoundingClientRect();
            let localX  = (e.clientX - rect.left) + rulerWrap.scrollLeft;
            if (localX < 0) localX = 0;
            vtSeekTimeline(vtSnapTime(localX / VT_STATE.masterZoom));
        });
    }

    // ── Pinch-to-zoom ─────────────────────────────────────────────────────────
    let pinch = { active: false, startDist: 0, startZoom: 0 };
    function getDist(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx*dx + dy*dy);
    }
    if (tlScroll) {
        tlScroll.addEventListener('touchstart', e => {
            if (e.touches.length === 2) {
                pinch = { active: true, startDist: getDist(e.touches), startZoom: VT_STATE.masterZoom };
                e.preventDefault();
            }
        }, { passive: false });
        tlScroll.addEventListener('touchmove', e => {
            if (pinch.active && e.touches.length === 2) {
                e.preventDefault();
                vtSetZoom(Math.max(4, Math.min(500, pinch.startZoom * getDist(e.touches) / pinch.startDist)));
            }
        }, { passive: false });
        tlScroll.addEventListener('touchend', e => { if (e.touches.length < 2) pinch.active = false; });
    }

    // ── Fullscreen change ─────────────────────────────────────────────────────
    document.addEventListener('fullscreenchange',       _vtOnFsChange);
    document.addEventListener('webkitfullscreenchange', _vtOnFsChange);

    // ── Transition panel: param inputs live-update connector state ────────────
    document.getElementById('tcDurRange')?.addEventListener('input', function() {
        if (VT_STATE.selectedConnKey) {
            vtGetOrCreateConnector(VT_STATE.selectedConnKey).durationFrames = parseInt(this.value);
        }
    });
    document.getElementById('tcDurNum')?.addEventListener('input', function() {
        if (VT_STATE.selectedConnKey) {
            vtGetOrCreateConnector(VT_STATE.selectedConnKey).durationFrames = parseInt(this.value);
        }
    });
    document.getElementById('tcIntRange')?.addEventListener('input', function() {
        if (VT_STATE.selectedConnKey) {
            vtGetOrCreateConnector(VT_STATE.selectedConnKey).intensity = parseFloat(this.value);
        }
    });
    document.getElementById('tcEasing')?.addEventListener('change', function() {
        if (VT_STATE.selectedConnKey) {
            vtGetOrCreateConnector(VT_STATE.selectedConnKey).easing = this.value;
        }
    });
    document.getElementById('tcSeed')?.addEventListener('input', function() {
        if (VT_STATE.selectedConnKey) {
            vtGetOrCreateConnector(VT_STATE.selectedConnKey).seed = parseInt(this.value);
        }
    });

    // ── Initial layout ────────────────────────────────────────────────────────
    vtUpdateMasterLayout();

    // ── Load transitions from PyAPI ───────────────────────────────────────────
    vtLoadTransitions();

    // ── Auto-load animatic if passed via URL ──────────────────────────────────
    if (window.VT_INIT_ANIMATIC_ID) {
        vtApi('get_animatic', { animatic_id: window.VT_INIT_ANIMATIC_ID }).then(res => {
            if (res.status === 'success') {
                VT_STATE.animaticId   = res.data.id;
                VT_STATE.animaticName = res.data.name;
                vtUpdateAnimaticLabel();
                vtLoadBinPage();
            }
        });
    }

    // ── FPS select sync ───────────────────────────────────────────────────────
    const fpsSel = document.getElementById('fpsSelect');
    if (fpsSel) fpsSel.value = String(VT_STATE.fps);

    // ── Keyboard shortcuts ────────────────────────────────────────────────────
    document.addEventListener('keydown', e => {
        const tag = e.target.tagName;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;

        // Undo / Redo
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
            e.preventDefault(); vtHistoryUndo(); return;
        }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) {
            e.preventDefault(); vtHistoryRedo(); return;
        }

        switch (e.key) {
            case ' ':
                e.preventDefault(); vtPlayPause(); break;
            case 'Home':
                e.preventDefault(); vtStop(); break;
            case 'Escape':
                vtCloseSettingsModal();
                vtCloseSaveLoadModal();
                vtCloseTrimModal();
                vtCloseBrowseModal();
                if (document.getElementById('binBackdrop').classList.contains('open')) vtCloseBin();
                if (vtTransPanelOpen) vtCloseTransPanel();
                if (VT_STATE.editMode) vtToggleEditMode(VT_STATE.editMode);
                break;
            case 'f': case 'F':
                e.preventDefault(); vtToggleFullscreen(); break;
            case 'c': case 'C':
                e.preventDefault(); vtToggleEditMode('cut'); break;
            case 'r': case 'R':
                e.preventDefault(); vtToggleEditMode('rem'); break;
            case 't': case 'T':
                e.preventDefault(); vtToggleTransPanel(); break;
            case 'Delete': case 'Backspace':
                if (VT_STATE.selectedClipId) {
                    e.preventDefault(); vtRemoveClip(VT_STATE.selectedClipId);
                }
                break;
            case 'ArrowLeft':
                e.preventDefault();
                vtSeekTimeline(Math.max(0, VT_STATE.curTime - (VT_PROJECT.snapDiv || 0.25)));
                break;
            case 'ArrowRight':
                e.preventDefault();
                vtSeekTimeline(VT_STATE.curTime + (VT_PROJECT.snapDiv || 0.25));
                break;
            case 'Enter':
                // If a connector is selected and panel is open, quick-render
                if (vtTransPanelOpen && VT_STATE.selectedConnKey) {
                    e.preventDefault(); vtRenderConnector();
                }
                break;
        }
    });

    // ── Window resize: redraw ruler & grid ────────────────────────────────────
    window.addEventListener('resize', vtUpdateMasterLayout);

    // ── Resume any in-progress render polls ───────────────────────────────────
    // (noop until a project with active jobs is loaded)
    vtResumeRenderPolling();
});

function _vtOnFsChange() {
    const isFS = !!(document.fullscreenElement || document.webkitFullscreenElement);
    const btn  = document.getElementById('btnFullscreen');
    const icon = btn?.querySelector('i');
    if (icon) icon.className = isFS ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
}
