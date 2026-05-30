// public/ved/js/ved-playback.js
// Transport + RAF loop for SAGE VED.

'use strict';

// ─── Preload Cache ────────────────────────────────────────────────────────────
const _blobCache = {}; // URL → blob: URL

function _getPlayableUrl(url) {
    const abs = _absoluteUrl(url);
    return _blobCache[abs] || abs;
}

// ─── Audio pool (non-hero tracks) ────────────────────────────────────────────
const _audioPool = {}; // clipId → <audio>

function _getAudioEl(clip) {
    if (!_audioPool[clip.id]) {
        const a     = document.createElement('audio');
        a.src       = _getPlayableUrl(clip.url);
        a.preload   = 'auto';
        a.style.cssText = 'position:absolute;width:0;height:0;opacity:0;pointer-events:none;';
        document.body.appendChild(a);
        _audioPool[clip.id] = a;
    }
    return _audioPool[clip.id];
}

function _releaseAudioEl(clipId) {
    const a = _audioPool[clipId];
    if (!a) return;
    a.pause(); a.src = ''; a.remove();
    delete _audioPool[clipId];
}

// ─── Preview video element ────────────────────────────────────────────────────
function _vid() { return document.getElementById('vedPreviewVideo'); }

let _loadedClipId = null;
let _switching    = false;
let _isStalling   = false; // Locks the timeline playhead if media is buffering

// ─── Preload system ───────────────────────────────────────────────────────────
let _preloadAbortController = null;

function _showPreloadOverlay(visible, msg) {
    let el = document.getElementById('vedPreloadOverlay');
    if (!el && visible) {
        el = document.createElement('div');
        el.id = 'vedPreloadOverlay';
        el.style.cssText = [
            'position:fixed;inset:0;z-index:9998',
            'background:rgba(9,9,16,0.88)',
            'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px',
            'font-family:var(--font-mono)',
        ].join(';');
        el.innerHTML = [
            '<div style="width:44px;height:44px;border:3px solid var(--border2);',
            'border-top-color:var(--teal);border-radius:50%;',
            'animation:bounce-spin .8s linear infinite;"></div>',
            '<div id="vedPreloadMsg"',
            'style="font-size:.72rem;color:var(--text-dim);',
            'letter-spacing:1px;text-transform:uppercase;',
            'text-align:center;max-width:260px;line-height:1.7;">Buffering…</div>',
            '<button id="vedPreloadCancel"',
            'style="margin-top:4px;padding:6px 16px;',
            'background:transparent;border:1px solid var(--border2);',
            'border-radius:5px;color:var(--text-dim);',
            'font-family:var(--font-mono);font-size:.65rem;',
            'text-transform:uppercase;letter-spacing:.6px;cursor:pointer;"',
            'onclick="vedPause()">Cancel</button>',
        ].join('');
        document.body.appendChild(el);
    }
    if (!el) return;
    el.style.display = visible ? 'flex' : 'none';
    if (msg) {
        const m = document.getElementById('vedPreloadMsg');
        if (m) m.textContent = msg;
    }
}

function _preloadAllClips() {
    const urls = [...new Set(
        STATE.clips.map(c => c.url).filter(Boolean).map(_absoluteUrl)
    )];

    if (!urls.length) return Promise.resolve();

    if (_preloadAbortController) _preloadAbortController.abort();
    _preloadAbortController = new AbortController();
    const signal = _preloadAbortController.signal;

    _showPreloadOverlay(true, 'Buffering 0 / ' + urls.length + ' video' + (urls.length > 1 ? 's' : '') + '\u2026');

    function fetchNext(i) {
        if (signal.aborted || i >= urls.length) return Promise.resolve();

        const url = urls[i];

        if (_blobCache[url]) return fetchNext(i + 1);

        const basename = url.split('/').pop();
        _showPreloadOverlay(true, 'Buffering ' + (i + 1) + ' / ' + urls.length + '\n' + basename);

        return fetch(url, { signal, cache: 'default', credentials: 'include' })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.blob();
        })          
        .then(blob => {
            _blobCache[url] = URL.createObjectURL(blob); 
            return fetchNext(i + 1);
        })
        .catch(err => {
            if (err.name === 'AbortError') return; 
            return fetchNext(i + 1);               
        });
    }

    return fetchNext(0).then(() => { _showPreloadOverlay(false); });
}

// ─── Floating modal open/close ────────────────────────────────────────────────
function openPreviewModal() {
    const m   = document.getElementById('vedFvModal');
    const btn = document.getElementById('mbBtnPreview');
    if (!m) return;
    m.style.display = 'flex';
    if (btn) btn.classList.add('active');
    if (!m._positioned) {
        m.style.transform = 'none';
        m.style.left = Math.round((window.innerWidth  - m.offsetWidth)  / 2) + 'px';
        m.style.top  = Math.round((window.innerHeight - m.offsetHeight) / 2) + 'px';
        m._positioned = true;
    }
}

function closePreviewModal() {
    const m   = document.getElementById('vedFvModal');
    const btn = document.getElementById('mbBtnPreview');
    if (!m) return;
    m.style.display = 'none';
    if (btn) btn.classList.remove('active');
    const v = _vid();
    if (v && !STATE.isPlaying) { v.pause(); }
}

// ─── Transport ────────────────────────────────────────────────────────────────
function vedPlayPause() {
    STATE.isPlaying ? vedPause() : vedPlay();
}

function vedPlay() {
    if (STATE.isPlaying) return;

    openPreviewModal();

    _preloadAllClips().then(() => {
        if (STATE.isPlaying) return;
        STATE.isPlaying     = true;
        STATE.lastFrameTime = performance.now();
        _updateTransportUI();
        STATE.rafId = requestAnimationFrame(_playLoop);
    });
}

function vedPause() {
    STATE.isPlaying = false;
    if (_preloadAbortController) {
        _preloadAbortController.abort();
        _preloadAbortController = null;
    }
    _showPreloadOverlay(false);
    cancelAnimationFrame(STATE.rafId);
    _updateTransportUI();
    const v = _vid();
    if (v) v.pause();
    Object.values(_audioPool).forEach(a => a.pause());
}

function vedStop() {
    vedPause();
    seekTimeline(0);
}

function vedRewind() {
    const was = STATE.isPlaying;
    vedStop();
    if (was) setTimeout(vedPlay, 50);
}

function seekTimeline(t) {
    STATE.curTime = Math.max(0, t);
    _applyPlayheadPos();

    const v = _vid();
    if (v && _loadedClipId !== null) {
        const clip = STATE.clips.find(c => c.id === _loadedClipId);
        if (clip) {
            const mt = _mediaTime(clip, STATE.curTime);
            if (mt >= 0 && mt <= (clip.duration || 99999)) v.currentTime = mt;
        }
    }
    Object.entries(_audioPool).forEach(([id, a]) => {
        const clip = STATE.clips.find(c => c.id === parseInt(id));
        if (clip) { const mt = _mediaTime(clip, STATE.curTime); if (mt >= 0) a.currentTime = mt; }
    });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function _mediaTime(clip, globalT) {
    const offset = Math.max(0, globalT - clip.startTime);
    return (clip.trimStart || 0) + offset * (clip.playbackSpeed || 1.0);
}

function _heroClip(t) {
    let best = null;
    STATE.clips.forEach(clip => {
        if (t >= clip.startTime && t < clip.startTime + clipVisualDuration(clip)) {
            if (!best || clip.trackId > best.trackId) best = clip;
        }
    });
    return best;
}

function _absoluteUrl(url) {
    if (!url) return '';
    if (/^(https?:|blob:)/i.test(url)) return url;
    return '/' + url.replace(/^\//, '');
}

// ─── RAF Loop ─────────────────────────────────────────────────────────────────
function _playLoop(now) {
    if (!STATE.isPlaying) return;
    
    const dt = (now - STATE.lastFrameTime) / 1000;
    STATE.lastFrameTime = now;

    const v = _vid();

    // -- MASTER SYNC ENGINE --
    // Check if the video engine needs the timeline to freeze and wait.
    _isStalling = _switching;
    if (!_switching && v && _loadedClipId && v.readyState < 3) {
        _isStalling = true; 
    }

    // Only advance the timeline playhead if the video engine is fully primed!
    if (!_isStalling) {
        STATE.curTime += dt;
    }

    if (STATE.curTime >= STATE.projectDuration) { vedStop(); return; }
    
    _applyPlayheadPos();
    _autoScrollPlayhead();
    _tickPreview();
    _tickAudioPool();
    
    STATE.rafId = requestAnimationFrame(_playLoop);
}

function _autoScrollPlayhead() {
    const scroll = document.getElementById('timelineScroll');
    if (!scroll) return;
    const phX = TRACK_HEAD_W + STATE.curTime * STATE.masterZoom - scroll.scrollLeft;
    if (phX > scroll.clientWidth * 0.82) scroll.scrollLeft += scroll.clientWidth * 0.4;
}

// ── Preview video (hero track) ────────────────────────────────────────────────
function _tickPreview() {
    const v = _vid();
    if (!v) return;

    const hero = _heroClip(STATE.curTime);

    if (!hero) {
        if (!v.paused) v.pause();
        if (_loadedClipId !== null) {
            _loadedClipId = null;
            v.removeAttribute('src');
            v.load();
        }
        _setFvInfo('');
        return;
    }

    const playUrl = _getPlayableUrl(hero.url);
    const mediaT  = _mediaTime(hero, STATE.curTime);
    const track   = STATE.tracks.find(t => t.id === hero.trackId);
    const vol     = (track && !track.muted) ? Math.max(0, Math.min(1, track.vol || 1)) : 0;

    // 1. Source Transition Setup
    if (_loadedClipId !== hero.id && !_switching) {
        _switching     = true; // This explicitly stalls the playhead
        _loadedClipId  = hero.id;
        
        v.pause();
        v.src = playUrl;
        v.load();
        _setFvInfo(hero.name);

        v.addEventListener('loadedmetadata', () => {
            const targetTime = Math.max(0, _mediaTime(hero, STATE.curTime));
            
            const onSeekFinished = () => {
                v.playbackRate = hero.playbackSpeed || 1.0;
                v.volume       = vol;
                _switching     = false; // Unlock playhead!
            };

            if (Math.abs(v.currentTime - targetTime) > 0.1) {
                v.addEventListener('seeked', onSeekFinished, { once: true });
                v.currentTime = targetTime;
            } else {
                onSeekFinished();
            }
        }, { once: true });
        return;
    }

    if (_switching) return;

    // 2. Drift Correction
    const drift = Math.abs(v.currentTime - mediaT);
    if (drift > 0.35) {
        _switching = true; // Stall playhead
        v.addEventListener('seeked', () => {
            _switching = false; // Unlock playhead
        }, { once: true });
        v.currentTime = Math.max(0, mediaT);
        return;
    }

    // 3. Continuous Sync
    if (Math.abs(v.playbackRate - (hero.playbackSpeed || 1.0)) > 0.01) {
        v.playbackRate = hero.playbackSpeed || 1.0;
    }
    v.volume = vol;
    
    // 4. Transport State - THE BUG FIX
    // If the master timeline is stalled waiting to buffer, we MUST pause the video element.
    // If we don't, the video inches forward, causing an infinite anti-drift seek loop!
    if (STATE.isPlaying && !_isStalling) {
        if (v.paused) v.play().catch(() => {});
    } else {
        if (!v.paused) v.pause();
    }
}

// ── Audio pool (non-hero secondary tracks) ────────────────────────────────────
function _tickAudioPool() {
    const hero = _heroClip(STATE.curTime);

    STATE.clips.forEach(clip => {
        if (hero && clip.id === hero.id) { _releaseAudioEl(clip.id); return; }

        const end    = clip.startTime + clipVisualDuration(clip);
        const active = STATE.curTime >= clip.startTime && STATE.curTime < end;
        const track  = STATE.tracks.find(t => t.id === clip.trackId);
        const vol    = (track && !track.muted) ? (track.vol || 1) : 0;

        if (active && vol > 0) {
            const a      = _getAudioEl(clip);
            const mediaT = _mediaTime(clip, STATE.curTime);
            
            if (a._isSeeking) return;

            const drift = Math.abs(a.currentTime - mediaT);
            if (drift > 0.35) {
                a._isSeeking = true;
                a.addEventListener('seeked', () => {
                    a._isSeeking = false;
                }, {once: true});
                a.currentTime = Math.max(0, mediaT);
                return;
            }
            
            a.volume       = vol;
            a.playbackRate = clip.playbackSpeed || 1.0;
            
            // Wait for main video engine before playing secondary tracks
            if (_isStalling || !STATE.isPlaying) {
                if (!a.paused) a.pause();
            } else {
                if (a.paused && STATE.isPlaying) a.play().catch(() => {});
            }
        } else {
            const a = _audioPool[clip.id];
            if (a && !a.paused) a.pause();
        }
    });

    Object.keys(_audioPool).forEach(id => {
        if (!STATE.clips.find(c => c.id === parseInt(id))) _releaseAudioEl(parseInt(id));
    });
}

// ─── Floating modal info bar ──────────────────────────────────────────────────
function _setFvInfo(text) {
    const el = document.getElementById('vedFvInfo');
    if (el) el.textContent = text || '';
}

// ─── Transport UI ─────────────────────────────────────────────────────────────
function _updateTransportUI() {
    const btn  = document.getElementById('btnPP');
    const icon = document.getElementById('ppIcon');
    if (btn)  btn.classList.toggle('playing', STATE.isPlaying);
    if (icon) icon.className = STATE.isPlaying ? 'bi bi-pause-fill' : 'bi bi-play-fill';
}

// ─── previewClip — called by selectClip() and bin preview ────────────────────
function previewClip(clip) {
    openPreviewModal();
    if (STATE.isPlaying) return;

    const v = _vid();
    if (!v) return;

    const playUrl = _getPlayableUrl(clip.url);
    _loadedClipId = clip.id;
    _switching    = true;
    v.pause();
    v.src          = playUrl;
    v.volume       = 1;
    v.playbackRate = 1.0;

    v.addEventListener('loadedmetadata', () => {
        const targetT = clip.trimStart || 0;
        
        const onSeeked = () => {
            v.playbackRate = clip.playbackSpeed || 1.0;
            _switching     = false;
            v.play().catch(() => {});
        };

        if (Math.abs(v.currentTime - targetT) > 0.1) {
            v.addEventListener('seeked', onSeeked, { once: true });
            v.currentTime = targetT;
        } else {
            onSeeked();
        }
    }, { once: true });
    v.load();

    _setFvInfo(clip.name + (clip.duration ? ' · ' + clip.duration.toFixed(1) + 's' : ''));
}

// ─── Floating modal drag + resize ─────────────────────────────────────────────
function initFvModal() {
    const modal    = document.getElementById('vedFvModal')    || document.querySelector('.fv-modal');
    const titlebar = document.getElementById('vedFvTitlebar') || document.querySelector('.fv-titlebar');
    const resizeH  = document.getElementById('vedFvResize')   || document.querySelector('.fv-resize-handle');
    
    if (!modal) return;
    if (modal.dataset.dragInit === 'true') return;
    modal.dataset.dragInit = 'true';

    const fvBody = document.getElementById('vedFvBody') || modal.querySelector('.fv-body');
    let shield = null;
    if (fvBody) {
        shield = document.createElement('div');
        shield.style.cssText = 'position:absolute;inset:0;z-index:999;display:none;touch-action:none;';
        fvBody.appendChild(shield);
    }
    function shieldOn()  { if (shield) shield.style.display = 'block'; }
    function shieldOff() { if (shield) shield.style.display = 'none';  }

    if (titlebar) {
        let drag = { active: false, startX: 0, startY: 0, origLeft: 0, origTop: 0 };
        function onDragStart(e) {
            if (e.target.closest('.fv-btn')) return;
            drag.active = true;
            const pt = e.touches ? e.touches[0] : e;
            drag.startX = pt.clientX; drag.startY = pt.clientY;
            const rect = modal.getBoundingClientRect();
            drag.origLeft = rect.left; drag.origTop  = rect.top;
            modal.style.transform = 'none';
            modal.style.left = rect.left + 'px'; modal.style.top  = rect.top  + 'px';
            shieldOn(); e.preventDefault();
        }
        function onDragMove(e) {
            if (!drag.active) return;
            const pt = e.touches ? e.touches[0] : e;
            const dx = pt.clientX - drag.startX; const dy = pt.clientY - drag.startY;
            let newL = drag.origLeft + dx; let newT = drag.origTop  + dy;
            newL = Math.max(0, Math.min(window.innerWidth - modal.offsetWidth, newL));
            newT = Math.max(0, Math.min(window.innerHeight - modal.offsetHeight, newT));
            modal.style.left = newL + 'px'; modal.style.top  = newT + 'px';
            e.preventDefault();
        }
        function onDragEnd() { if (drag.active) { drag.active = false; shieldOff(); } }
        titlebar.addEventListener('mousedown',  onDragStart, { passive: false });
        titlebar.addEventListener('touchstart', onDragStart, { passive: false });
        document.addEventListener('mousemove',  onDragMove,  { passive: false });
        document.addEventListener('touchmove',  onDragMove,  { passive: false });
        document.addEventListener('mouseup',    onDragEnd);
        document.addEventListener('touchend',   onDragEnd);
    }

    if (resizeH) {
        let rsz = { active: false, startX: 0, startY: 0, origW: 0, origH: 0 };
        function onResizeStart(e) {
            rsz.active = true;
            const pt = e.touches ? e.touches[0] : e;
            rsz.startX = pt.clientX; rsz.startY = pt.clientY;
            rsz.origW  = modal.offsetWidth; rsz.origH  = modal.offsetHeight;
            shieldOn(); e.preventDefault(); e.stopPropagation();
        }
        function onResizeMove(e) {
            if (!rsz.active) return;
            const pt = e.touches ? e.touches[0] : e;
            const dx = pt.clientX - rsz.startX; const dy = pt.clientY - rsz.startY;
            const newW = Math.max(180, rsz.origW + dx); const newH = Math.max(140, rsz.origH + dy);
            modal.style.width  = newW + 'px'; modal.style.height = newH + 'px';
            modal.style.aspectRatio = 'unset';
            e.preventDefault();
        }
        function onResizeEnd() { if (rsz.active) { rsz.active = false; shieldOff(); } }
        resizeH.addEventListener('mousedown',  onResizeStart, { passive: false });
        resizeH.addEventListener('touchstart', onResizeStart, { passive: false });
        document.addEventListener('mousemove',  onResizeMove,  { passive: false });
        document.addEventListener('touchmove',  onResizeMove,  { passive: false });
        document.addEventListener('mouseup',    onResizeEnd);
        document.addEventListener('touchend',   onResizeEnd);
    }
}

if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initFvModal); } 
else { initFvModal(); }