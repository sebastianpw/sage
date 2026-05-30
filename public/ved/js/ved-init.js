// public/ved/js/ved-init.js
// Bootstrap — wires all event listeners after DOM is ready

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Swatches ──────────────────────────────────────────────────────────────
    buildSwatches();
    _syncHistoryUI();

    // ── Ruler scroll sync ─────────────────────────────────────────────────────
    const tlScroll = document.getElementById('timelineScroll');
    const rulerWrap = document.getElementById('rulerWrap');
    if (tlScroll && rulerWrap) {
        tlScroll.addEventListener('scroll', e => {
            rulerWrap.scrollLeft = e.target.scrollLeft;
        });
    }

    // ── Timeline seek (click on empty area or ruler) ──────────────────────────
    const tlContent = document.getElementById('vedTimelineContent');
    if (tlContent) {
        tlContent.addEventListener('mousedown', e => {
            if (e.target.closest('.ved-clip')) return;
            if (e.target.closest('.track-head')) return;
            const rect  = tlContent.getBoundingClientRect();
            let localX  = e.clientX - rect.left - TRACK_HEAD_W;
            if (localX < 0) localX = 0;
            seekTimeline(snapTime(localX / STATE.masterZoom));
        });
    }

    if (rulerWrap) {
        rulerWrap.addEventListener('mousedown', e => {
            const rect   = rulerWrap.getBoundingClientRect();
            let localX   = (e.clientX - rect.left) + rulerWrap.scrollLeft;
            if (localX < 0) localX = 0;
            seekTimeline(snapTime(localX / STATE.masterZoom));
        });
    }

    // ── Pinch-to-zoom ─────────────────────────────────────────────────────────
    let pinch = { active: false, startDist: 0, startZoom: 0 };
    function getDist(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }
    if (tlScroll) {
        tlScroll.addEventListener('touchstart', e => {
            if (e.touches.length === 2) {
                pinch = { active: true, startDist: getDist(e.touches), startZoom: STATE.masterZoom };
                e.preventDefault();
            }
        }, { passive: false });
        tlScroll.addEventListener('touchmove', e => {
            if (pinch.active && e.touches.length === 2) {
                e.preventDefault();
                const scale = getDist(e.touches) / pinch.startDist;
                vedSetZoom(Math.max(4, Math.min(500, pinch.startZoom * scale)));
            }
        }, { passive: false });
        tlScroll.addEventListener('touchend', e => {
            if (e.touches.length < 2) pinch.active = false;
        });
    }

    // ── Fullscreen change ─────────────────────────────────────────────────────
    document.addEventListener('fullscreenchange',       _onFsChange);
    document.addEventListener('webkitfullscreenchange', _onFsChange);

    // ── Initial layout ────────────────────────────────────────────────────────
    updateMasterLayout();

    // ── Auto-load animatic if passed via URL ──────────────────────────────────
    if (window.VED_INIT_ANIMATIC_ID) {
        api('get_animatic', { animatic_id: window.VED_INIT_ANIMATIC_ID })
            .then(res => {
                if (res.status === 'success') {
                    STATE.animaticId   = res.data.id;
                    STATE.animaticName = res.data.name;
                    _updateAnimaticLabel();
                    loadBinPage();
                }
            });
    }

    // ── Keyboard shortcuts ────────────────────────────────────────────────────
    document.addEventListener('keydown', e => {
        const tag = e.target.tagName;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;

        // Undo / Redo
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
            e.preventDefault(); historyUndo(); return;
        }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) {
            e.preventDefault(); historyRedo(); return;
        }

        switch (e.key) {
            case ' ':
                e.preventDefault(); vedPlayPause(); break;

            case 'Home':
                e.preventDefault(); vedStop(); break;

            case 'Escape':
                closeSettingsModal();
                closeSaveLoadModal();
                closeTrimModal();
                if (document.getElementById('binBackdrop').classList.contains('open')) closeBin();
                if (STATE.editMode) toggleEditMode(STATE.editMode); // deactivate edit mode
                break;

            case 'f': case 'F':
                e.preventDefault(); toggleFullscreen(); break;

            case 'c': case 'C':
                e.preventDefault(); toggleEditMode('cut'); break;

            case 'r': case 'R':
                e.preventDefault(); toggleEditMode('rem'); break;

            case 'Delete': case 'Backspace':
                if (STATE.selectedClipId) {
                    e.preventDefault();
                    removeClip(STATE.selectedClipId);
                }
                break;

            case 'ArrowLeft':
                e.preventDefault();
                seekTimeline(Math.max(0, STATE.curTime - (PROJECT.snapDiv || 0.25)));
                break;

            case 'ArrowRight':
                e.preventDefault();
                seekTimeline(STATE.curTime + (PROJECT.snapDiv || 0.25));
                break;
        }
    });

    // ── FPS select sync ───────────────────────────────────────────────────────
    const fpsSel = document.getElementById('fpsSelect');
    if (fpsSel) fpsSel.value = String(STATE.fps);
});

function _onFsChange() {
    const isFS = !!(document.fullscreenElement || document.webkitFullscreenElement);
    const btn  = document.getElementById('btnFullscreen');
    const icon = btn ? btn.querySelector('i') : null;
    if (icon) icon.className = isFS ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
}
