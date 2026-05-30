// public/vedtriccs/js/vt-playback.js
// Transport, RAF playback loop, preview modal — VedTriccs Edition

'use strict';

const _vtBlobCache = {};
const _vtAudioPool = {};

function _vtGetPlayableUrl(url) {
    const abs = _vtAbsoluteUrl(url);
    return _vtBlobCache[abs] || abs;
}
function _vtAbsoluteUrl(url) {
    if (!url) return '';
    if (/^(https?:|blob:)/i.test(url)) return url;
    return '/' + url.replace(/^\//, '');
}

function _vtVid() { return document.getElementById('vtPreviewVideo'); }
let _vtLoadedClipId = null;
let _vtSwitching    = false;
let _vtStalling     = false;

// ─── Preload ──────────────────────────────────────────────────────────────────
let _vtPreloadAbort = null;

function _vtShowPreloadOverlay(visible, msg) {
    let el = document.getElementById('vtPreloadOverlay');
    if (!el && visible) {
        el = document.createElement('div');
        el.id = 'vtPreloadOverlay';
        el.style.cssText = 'position:fixed;inset:0;z-index:9998;background:rgba(9,9,16,0.88);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;font-family:var(--font-mono);';
        el.innerHTML = '<div style="width:44px;height:44px;border:3px solid var(--border2);border-top-color:var(--teal);border-radius:50%;animation:vt-spin .8s linear infinite;"></div><div id="vtPreloadMsg" style="font-size:.72rem;color:var(--text-dim);letter-spacing:1px;text-transform:uppercase;text-align:center;max-width:260px;line-height:1.7;">Buffering…</div><button style="padding:6px 16px;background:transparent;border:1px solid var(--border2);border-radius:5px;color:var(--text-dim);font-family:var(--font-mono);font-size:.65rem;text-transform:uppercase;cursor:pointer;" onclick="vtPause()">Cancel</button>';
        document.body.appendChild(el);
    }
    if (!el) return;
    el.style.display = visible ? 'flex' : 'none';
    if (msg) { const m = document.getElementById('vtPreloadMsg'); if (m) m.textContent = msg; }
}

function _vtPreloadAllClips() {
    const urls = [...new Set(VT_STATE.clips.map(c => c.url).filter(Boolean).map(_vtAbsoluteUrl))];
    if (!urls.length) return Promise.resolve();
    if (_vtPreloadAbort) _vtPreloadAbort.abort();
    _vtPreloadAbort = new AbortController();
    const signal = _vtPreloadAbort.signal;
    _vtShowPreloadOverlay(true, 'Buffering 0 / ' + urls.length + ' video(s)…');
    function fetchNext(i) {
        if (signal.aborted || i >= urls.length) return Promise.resolve();
        const url = urls[i];
        if (_vtBlobCache[url]) return fetchNext(i + 1);
        const basename = url.split('/').pop();
        _vtShowPreloadOverlay(true, `Buffering ${i+1} / ${urls.length}\n${basename}`);
        return fetch(url, { signal, cache: 'default', credentials: 'include' })
            .then(r => { if (!r.ok) throw new Error('Network error'); return r.blob(); })
            .then(blob => { _vtBlobCache[url] = URL.createObjectURL(blob); return fetchNext(i+1); })
            .catch(err => { if (err.name === 'AbortError') return; return fetchNext(i+1); });
    }
    return fetchNext(0).then(() => _vtShowPreloadOverlay(false));
}

// ─── Preview Modal ────────────────────────────────────────────────────────────
function vtOpenPreviewModal() {
    const m   = document.getElementById('vtFvModal');
    const btn = document.getElementById('mbBtnPreview');
    if (!m) return;
    m.style.display = 'flex';
    if (btn) btn.classList.add('active');
    if (!m._positioned) {
        m.style.transform = 'none';
        m.style.left = Math.round((window.innerWidth - m.offsetWidth) / 2) + 'px';
        m.style.top  = Math.round((window.innerHeight - m.offsetHeight) / 2) + 'px';
        m._positioned = true;
    }
}

function vtClosePreviewModal() {
    const m   = document.getElementById('vtFvModal');
    const btn = document.getElementById('mbBtnPreview');
    if (!m) return;
    m.style.display = 'none';
    if (btn) btn.classList.remove('active');
    const v = _vtVid();
    if (v && !VT_STATE.isPlaying) v.pause();
}

// ─── Transport ────────────────────────────────────────────────────────────────
function vtPlayPause() { VT_STATE.isPlaying ? vtPause() : vtPlay(); }

function vtPlay() {
    if (VT_STATE.isPlaying) return;
    vtOpenPreviewModal();
    _vtPreloadAllClips().then(() => {
        if (VT_STATE.isPlaying) return;
        VT_STATE.isPlaying     = true;
        VT_STATE.lastFrameTime = performance.now();
        _vtUpdateTransportUI();
        VT_STATE.rafId = requestAnimationFrame(_vtPlayLoop);
    });
}

function vtPause() {
    VT_STATE.isPlaying = false;
    if (_vtPreloadAbort) { _vtPreloadAbort.abort(); _vtPreloadAbort = null; }
    _vtShowPreloadOverlay(false);
    cancelAnimationFrame(VT_STATE.rafId);
    _vtUpdateTransportUI();
    const v = _vtVid(); if (v) v.pause();
    Object.values(_vtAudioPool).forEach(a => a.pause());
}

function vtStop()   { vtPause(); vtSeekTimeline(0); }
function vtRewind() { const was = VT_STATE.isPlaying; vtStop(); if (was) setTimeout(vtPlay, 50); }

function vtSeekTimeline(t) {
    VT_STATE.curTime = Math.max(0, t);
    vtApplyPlayheadPos();
    const v = _vtVid();
    if (v && _vtLoadedClipId !== null) {
        const clip = VT_STATE.clips.find(c => c.id === _vtLoadedClipId);
        if (clip) {
            const mt = _vtMediaTime(clip, VT_STATE.curTime);
            if (mt >= 0 && mt <= (clip.duration || 99999)) v.currentTime = mt;
        }
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function _vtMediaTime(clip, globalT) {
    return (clip.trimStart || 0) + Math.max(0, globalT - clip.startTime) * (clip.playbackSpeed || 1.0);
}
function _vtHeroClip(t) {
    let best = null;
    VT_STATE.clips.forEach(clip => {
        if (t >= clip.startTime && t < clip.startTime + vtClipVisualDuration(clip)) {
            if (!best || clip.trackId > best.trackId) best = clip;
        }
    });
    return best;
}

// ─── RAF Loop ─────────────────────────────────────────────────────────────────
function _vtPlayLoop(now) {
    if (!VT_STATE.isPlaying) return;
    const dt = (now - VT_STATE.lastFrameTime) / 1000;
    VT_STATE.lastFrameTime = now;
    const v = _vtVid();
    _vtStalling = _vtSwitching || (!_vtSwitching && v && _vtLoadedClipId && v.readyState < 3);
    if (!_vtStalling) VT_STATE.curTime += dt;
    if (VT_STATE.curTime >= VT_STATE.projectDuration) { vtStop(); return; }
    vtApplyPlayheadPos();
    _vtAutoScroll();
    _vtTickPreview();
    VT_STATE.rafId = requestAnimationFrame(_vtPlayLoop);
}

function _vtAutoScroll() {
    const scroll = document.getElementById('timelineScroll');
    if (!scroll) return;
    const phX = VT_TRACK_HEAD_W + VT_STATE.curTime * VT_STATE.masterZoom - scroll.scrollLeft;
    if (phX > scroll.clientWidth * 0.82) scroll.scrollLeft += scroll.clientWidth * 0.4;
}

function _vtTickPreview() {
    const v    = _vtVid();
    if (!v) return;
    const hero = _vtHeroClip(VT_STATE.curTime);
    if (!hero) {
        if (!v.paused) v.pause();
        if (_vtLoadedClipId !== null) { _vtLoadedClipId = null; v.removeAttribute('src'); v.load(); }
        _vtSetFvInfo(''); return;
    }
    const playUrl = _vtGetPlayableUrl(hero.url);
    const mediaT  = _vtMediaTime(hero, VT_STATE.curTime);
    const track   = VT_STATE.tracks.find(t => t.id === hero.trackId);
    const vol     = (track && !track.muted) ? Math.max(0, Math.min(1, track.vol || 1)) : 0;

    if (_vtLoadedClipId !== hero.id && !_vtSwitching) {
        _vtSwitching = true; _vtLoadedClipId = hero.id;
        v.pause(); v.src = playUrl; v.load();
        _vtSetFvInfo(hero.name);
        v.addEventListener('loadedmetadata', () => {
            const target = Math.max(0, _vtMediaTime(hero, VT_STATE.curTime));
            const onSeeked = () => { v.playbackRate = hero.playbackSpeed || 1.0; v.volume = vol; _vtSwitching = false; };
            if (Math.abs(v.currentTime - target) > 0.1) { v.addEventListener('seeked', onSeeked, { once: true }); v.currentTime = target; }
            else onSeeked();
        }, { once: true });
        return;
    }
    if (_vtSwitching) return;
    if (Math.abs(v.currentTime - mediaT) > 0.35) {
        _vtSwitching = true;
        v.addEventListener('seeked', () => { _vtSwitching = false; }, { once: true });
        v.currentTime = Math.max(0, mediaT);
        return;
    }
    if (Math.abs(v.playbackRate - (hero.playbackSpeed || 1.0)) > 0.01) v.playbackRate = hero.playbackSpeed || 1.0;
    v.volume = vol;
    if (VT_STATE.isPlaying && !_vtStalling) { if (v.paused) v.play().catch(() => {}); }
    else { if (!v.paused) v.pause(); }
}

function _vtSetFvInfo(text) {
    const el = document.getElementById('vtFvInfo');
    if (el) el.textContent = text || '';
}

function _vtUpdateTransportUI() {
    document.getElementById('btnPP')?.classList.toggle('playing', VT_STATE.isPlaying);
    const icon = document.getElementById('ppIcon');
    if (icon) icon.className = VT_STATE.isPlaying ? 'bi bi-pause-fill' : 'bi bi-play-fill';
}

// ─── Clip preview (from select / bin) ────────────────────────────────────────
function vtPreviewClip(clip) {
    vtOpenPreviewModal();
    if (VT_STATE.isPlaying) return;
    const v = _vtVid();
    if (!v) return;
    const playUrl     = _vtGetPlayableUrl(clip.url);
    _vtLoadedClipId   = clip.id; _vtSwitching = true;
    v.pause(); v.src = playUrl; v.volume = 1; v.playbackRate = 1.0;
    v.addEventListener('loadedmetadata', () => {
        const target = clip.trimStart || 0;
        const onSeeked = () => { v.playbackRate = clip.playbackSpeed || 1.0; _vtSwitching = false; v.play().catch(() => {}); };
        if (Math.abs(v.currentTime - target) > 0.1) { v.addEventListener('seeked', onSeeked, { once: true }); v.currentTime = target; }
        else onSeeked();
    }, { once: true });
    v.load();
    _vtSetFvInfo(clip.name + (clip.duration ? ' · ' + clip.duration.toFixed(1) + 's' : ''));
}

// ─── Floating modal drag + resize ─────────────────────────────────────────────
function vtInitFvModal() {
    const modal    = document.getElementById('vtFvModal');
    const titlebar = document.getElementById('vtFvTitlebar');
    const resizeH  = document.getElementById('vtFvResize');
    if (!modal || modal.dataset.dragInit === 'true') return;
    modal.dataset.dragInit = 'true';

    const fvBody = document.getElementById('vtFvBody');
    let shield = null;
    if (fvBody) {
        shield = document.createElement('div');
        shield.style.cssText = 'position:absolute;inset:0;z-index:999;display:none;touch-action:none;';
        fvBody.appendChild(shield);
    }
    const shieldOn  = () => { if (shield) shield.style.display = 'block'; };
    const shieldOff = () => { if (shield) shield.style.display = 'none';  };

    if (titlebar) {
        let drag = { active: false, startX: 0, startY: 0, origLeft: 0, origTop: 0 };
        const onStart = (e) => {
            if (e.target.closest('.fv-btn')) return;
            drag.active = true;
            const pt = e.touches ? e.touches[0] : e;
            drag.startX = pt.clientX; drag.startY = pt.clientY;
            const rect = modal.getBoundingClientRect();
            drag.origLeft = rect.left; drag.origTop = rect.top;
            modal.style.transform = 'none';
            modal.style.left = rect.left + 'px'; modal.style.top = rect.top + 'px';
            shieldOn(); e.preventDefault();
        };
        const onMove = (e) => {
            if (!drag.active) return;
            const pt = e.touches ? e.touches[0] : e;
            const dx = pt.clientX - drag.startX; const dy = pt.clientY - drag.startY;
            let l = Math.max(0, Math.min(window.innerWidth - modal.offsetWidth,  drag.origLeft + dx));
            let t = Math.max(0, Math.min(window.innerHeight - modal.offsetHeight, drag.origTop + dy));
            modal.style.left = l + 'px'; modal.style.top = t + 'px'; e.preventDefault();
        };
        const onEnd = () => { if (drag.active) { drag.active = false; shieldOff(); } };
        titlebar.addEventListener('mousedown',  onStart, { passive: false });
        titlebar.addEventListener('touchstart', onStart, { passive: false });
        document.addEventListener('mousemove',  onMove,  { passive: false });
        document.addEventListener('touchmove',  onMove,  { passive: false });
        document.addEventListener('mouseup',    onEnd);
        document.addEventListener('touchend',   onEnd);
    }

    if (resizeH) {
        let rsz = { active: false, startX: 0, startY: 0, origW: 0, origH: 0 };
        const onStart = (e) => {
            rsz.active = true;
            const pt = e.touches ? e.touches[0] : e;
            rsz.startX = pt.clientX; rsz.startY = pt.clientY;
            rsz.origW = modal.offsetWidth; rsz.origH = modal.offsetHeight;
            shieldOn(); e.preventDefault(); e.stopPropagation();
        };
        const onMove = (e) => {
            if (!rsz.active) return;
            const pt = e.touches ? e.touches[0] : e;
            modal.style.width  = Math.max(180, rsz.origW + pt.clientX - rsz.startX) + 'px';
            modal.style.height = Math.max(140, rsz.origH + pt.clientY - rsz.startY) + 'px';
            modal.style.aspectRatio = 'unset'; e.preventDefault();
        };
        const onEnd = () => { if (rsz.active) { rsz.active = false; shieldOff(); } };
        resizeH.addEventListener('mousedown',  onStart, { passive: false });
        resizeH.addEventListener('touchstart', onStart, { passive: false });
        document.addEventListener('mousemove',  onMove,  { passive: false });
        document.addEventListener('touchmove',  onMove,  { passive: false });
        document.addEventListener('mouseup',    onEnd);
        document.addEventListener('touchend',   onEnd);
    }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', vtInitFvModal);
else vtInitFvModal();
