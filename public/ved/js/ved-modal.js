// public/ved/js/ved-modal.js
// Settings modal, Save/Load modal, Clip Trim modal, Bounce/Export modal

'use strict';

// ─── Grid Swatches ────────────────────────────────────────────────────────────
function buildSwatches() {
    const row = document.getElementById('pmSwatchRow');
    if (!row) return;
    GRID_COLORS.forEach((c, i) => {
        const s = document.createElement('div');
        s.className = 'pm-swatch' + (i === selectedSwatchIdx ? ' active' : '');
        s.style.background = c;
        s.title   = c;
        s.onclick = () => {
            selectedSwatchIdx = i;
            document.querySelectorAll('.pm-swatch').forEach((el, j) => el.classList.toggle('active', i === j));
        };
        row.appendChild(s);
    });
}

// ─── Settings Modal ───────────────────────────────────────────────────────────
function openSettingsModal() {
    document.getElementById('pmGridVisible').checked = PROJECT.gridVisible;
    document.getElementById('pmSnapEnabled').checked = PROJECT.snapEnabled;
    document.getElementById('pmSnapDiv').value        = String(PROJECT.snapDiv);
    document.getElementById('pmGridOpacity').value    = PROJECT.gridOpacity;
    document.getElementById('pmOpacityVal').textContent = PROJECT.gridOpacity + '%';

    const resParts = PROJECT.canvasW + 'x' + PROJECT.canvasH;
    const resSel   = document.getElementById('pmResolution');
    if (resSel) {
        const opt = [...resSel.options].find(o => o.value === resParts);
        resSel.value = opt ? resParts : 'custom';
    }

    selectedSwatchIdx = Math.max(0, GRID_COLORS.indexOf(PROJECT.gridColor));
    document.querySelectorAll('.pm-swatch').forEach((s, j) => s.classList.toggle('active', j === selectedSwatchIdx));

    document.getElementById('settingsBackdrop').classList.add('open');
    document.getElementById('mbBtnSettings').classList.add('active');
}

function closeSettingsModal() {
    document.getElementById('settingsBackdrop').classList.remove('open');
    document.getElementById('mbBtnSettings').classList.remove('active');
}

function onSettingsBackdropClick(e) {
    if (e.target === document.getElementById('settingsBackdrop')) closeSettingsModal();
}

function applySettings() {
    PROJECT.gridVisible = document.getElementById('pmGridVisible').checked;
    PROJECT.snapEnabled = document.getElementById('pmSnapEnabled').checked;
    PROJECT.snapDiv     = parseFloat(document.getElementById('pmSnapDiv').value) || 0.25;
    PROJECT.gridColor   = GRID_COLORS[selectedSwatchIdx] || '#f59e0b';
    PROJECT.gridOpacity = parseInt(document.getElementById('pmGridOpacity').value) || 18;

    const res = document.getElementById('pmResolution').value;
    if (res && res !== 'custom') {
        const [w, h]    = res.split('x').map(Number);
        PROJECT.canvasW = w;
        PROJECT.canvasH = h;
    }

    closeSettingsModal();
    updateMasterLayout();
    Toast.show('Settings applied', 'success');
}

// ─── Save / Load Modal ────────────────────────────────────────────────────────
function openSaveLoadModal() {
    document.getElementById('fileBackdrop').classList.add('open');
    document.getElementById('mbBtnSaveLoad').classList.add('active');
    _fetchProjectsList();
}

function closeSaveLoadModal() {
    document.getElementById('fileBackdrop').classList.remove('open');
    document.getElementById('mbBtnSaveLoad').classList.remove('active');
}

function onFileBackdropClick(e) {
    if (e.target === document.getElementById('fileBackdrop')) closeSaveLoadModal();
}

function _fetchProjectsList() {
    api('get_projects').then(res => {
        if (res.status !== 'success') return;
        const sel = document.getElementById('fileProjectSelect');
        const cur = sel.value;
        sel.innerHTML = '<option value="">-- Select Project --</option>' +
            res.data.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
        if (cur && res.data.find(p => String(p.id) === cur)) sel.value = cur;
        loadProjectFilesList();
    });
}

function createNewProject() {
    const name = document.getElementById('newProjectName').value.trim();
    if (!name) return;
    api('create_project', { name }, 'POST').then(res => {
        if (res.status === 'success') {
            document.getElementById('newProjectName').value = '';
            _fetchProjectsList();
            Toast.show('Project created', 'success');
        } else Toast.show(res.message || 'Error', 'error');
    });
}

function loadProjectFilesList() {
    const pid  = document.getElementById('fileProjectSelect').value;
    const list = document.getElementById('fileList');
    if (!pid) {
        list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">Select a project above</div>';
        return;
    }
    list.innerHTML = '<div style="padding:10px;color:var(--text-dim);text-align:center;font-size:.72rem;">Loading…</div>';
    api('get_project_files', { project_id: pid }).then(res => {
        if (res.status !== 'success') return;
        if (!res.data.length) {
            list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">No saves yet</div>';
            return;
        }
        list.innerHTML = res.data.map(f =>
            `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;border-bottom:1px solid var(--border);">
                <span style="font-family:var(--font-sans);font-size:.72rem;">${esc(f.filename)}</span>
                <div style="display:flex;gap:4px;">
                    <button class="pm-btn pm-btn-cancel" style="height:22px;padding:0 8px;font-size:.62rem;" onclick="overwriteVedFile(${f.id},'${esc(f.filename)}')">
                        <i class="bi bi-floppy"></i>
                    </button>
                    <button class="pm-btn pm-btn-apply" style="height:22px;padding:0 8px;font-size:.62rem;" onclick="loadVedFile(${f.id})">
                        <i class="bi bi-play-fill"></i> Load
                    </button>
                </div>
            </div>`
        ).join('');
    });
}

function saveCurrentProjectFile() {
    const pid   = document.getElementById('fileProjectSelect').value;
    const fname = document.getElementById('newFileName').value.trim();
    if (!pid)   { Toast.show('Select a project first', 'error'); return; }
    if (!fname) { Toast.show('Enter a filename', 'error'); return; }
    const state = JSON.stringify(serializeVedState());
    api('save_project_file', { project_id: pid, filename: fname, state_data: state }, 'POST')
        .then(res => {
            if (res.status === 'success') {
                document.getElementById('newFileName').value = '';
                loadProjectFilesList();
                Toast.show('Saved', 'success');
            } else Toast.show(res.message || 'Error', 'error');
        });
}

function overwriteVedFile(fileId, filename) {
    if (!confirm('Overwrite "' + filename + '" with current state?')) return;
    const state = JSON.stringify(serializeVedState());
    api('update_project_file', { file_id: fileId, state_data: state }, 'POST')
        .then(res => {
            if (res.status === 'success') { loadProjectFilesList(); Toast.show('Overwritten', 'success'); }
            else Toast.show(res.message || 'Error', 'error');
        });
}

function loadVedFile(fileId) {
    api('load_project_file', { file_id: fileId }).then(res => {
        if (res.status === 'success' && res.data) {
            try {
                deserializeVedState(JSON.parse(res.data.state_data));
                closeSaveLoadModal();
                Toast.show('Project loaded', 'success');
            } catch(e) {
                Toast.show('Error parsing project data', 'error');
            }
        } else Toast.show(res.message || 'Load failed', 'error');
    });
}

// ─── Clip Trim Modal ──────────────────────────────────────────────────────────
let _trimClipId = null;
let _trimDur    = 0;
let _trimDragH  = null; // 'start' | 'end'

function openTrimModal(clipId) {
    const clip = STATE.clips.find(c => c.id === clipId);
    if (!clip) return;

    _trimClipId = clipId;
    _trimDur    = clip.duration || 30;

    document.getElementById('trimModalTitle').textContent = 'Trim: ' + clip.name;
    document.getElementById('trimVideo').src = clip.url;
    document.getElementById('trimVideo').currentTime = clip.trimStart || 0;
    document.getElementById('trimSpeed').value = clip.playbackSpeed || 1.0;
    document.getElementById('trimSpeedVal').textContent = (clip.playbackSpeed || 1.0).toFixed(2) + '×';

    _renderTrimUI(clip);
    document.getElementById('trimBackdrop').classList.add('open');
}

function closeTrimModal() {
    document.getElementById('trimBackdrop').classList.remove('open');
    document.getElementById('trimVideo').pause();
    document.getElementById('trimVideo').src = '';
    _trimClipId = null;
}

function onTrimBackdropClick(e) {
    if (e.target === document.getElementById('trimBackdrop')) closeTrimModal();
}

function _renderTrimUI(clip) {
    const dur = _trimDur || 1;
    const s   = clip.trimStart || 0;
    const e   = clip.trimEnd   !== null ? clip.trimEnd : dur;
    const pctS = (s / dur) * 100;
    const pctE = (e / dur) * 100;

    const track = document.getElementById('trimTrack');
    const hs    = document.getElementById('trimHandleStart');
    const he    = document.getElementById('trimHandleEnd');
    if (!track || !hs || !he) return;

    track.style.left  = pctS + '%';
    track.style.width = (pctE - pctS) + '%';
    hs.style.left     = pctS + '%';
    he.style.left     = pctE + '%';

    document.getElementById('trimLblStart').textContent = s.toFixed(2) + ' s';
    document.getElementById('trimLblEnd').textContent   = clip.trimEnd !== null ? e.toFixed(2) + ' s' : '(end)';
    document.getElementById('trimLblDur').textContent   = (e - s).toFixed(2) + ' s clip';
}

function clearTrim() {
    const clip = STATE.clips.find(c => c.id === _trimClipId);
    if (!clip) return;
    clip.trimStart = 0;
    clip.trimEnd   = null;
    _renderTrimUI(clip);
    Toast.show('Trim cleared', 'info');
}

function applyTrim() {
    const clip = STATE.clips.find(c => c.id === _trimClipId);
    if (!clip) return;
    clip.playbackSpeed = parseFloat(document.getElementById('trimSpeed').value) || 1.0;
    _refreshClipEl(clip);
    updateMasterLayout();
    closeTrimModal();
    Toast.show('Trim applied', 'success');
}

// Trim drag
(function _setupTrimDrag() {
    const tl = document.getElementById('trimTimeline');
    const hs = document.getElementById('trimHandleStart');
    const he = document.getElementById('trimHandleEnd');
    if (!tl || !hs || !he) return;

    function startDrag(side, e) {
        e.preventDefault(); e.stopPropagation();
        _trimDragH = side;
        tl.setPointerCapture(e.pointerId);
    }

    function onMove(e) {
        if (!_trimDragH || !_trimClipId) return;
        const clip = STATE.clips.find(c => c.id === _trimClipId);
        if (!clip) return;
        const rect = tl.getBoundingClientRect();
        const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        const t    = pct * (_trimDur || 1);
        if (_trimDragH === 'start') {
            clip.trimStart = Math.max(0, Math.min(t, (clip.trimEnd || _trimDur) - 0.1));
        } else {
            clip.trimEnd = Math.min(_trimDur, Math.max(clip.trimStart + 0.1, t));
            if (clip.trimEnd >= (_trimDur - 0.05)) clip.trimEnd = null;
        }
        // Sync video preview
        const vid = document.getElementById('trimVideo');
        if (vid) vid.currentTime = clip.trimStart;
        _renderTrimUI(clip);
    }

    function onUp() { _trimDragH = null; }

    hs.addEventListener('pointerdown', e => startDrag('start', e));
    he.addEventListener('pointerdown', e => startDrag('end', e));
    tl.addEventListener('pointermove', onMove);
    tl.addEventListener('pointerup',   onUp);
    tl.addEventListener('pointercancel', onUp);
})();

// Speed sync
document.addEventListener('DOMContentLoaded', () => {
    const spd = document.getElementById('trimSpeed');
    if (spd) spd.addEventListener('input', function() {
        document.getElementById('trimSpeedVal').textContent = parseFloat(this.value).toFixed(2) + '×';
        const vid = document.getElementById('trimVideo');
        if (vid) vid.playbackRate = parseFloat(this.value) || 1.0;
    });
});


// ─── Bounce / Export ──────────────────────────────────────────────────────────
let _bouncePolling = null;

function _openBounceModal() {
    document.getElementById('bounceBackdrop').classList.add('open');
    document.getElementById('mbBtnBounce').classList.add('active');
}
function _closeBounceModal() {
    document.getElementById('bounceBackdrop').classList.remove('open');
    document.getElementById('mbBtnBounce').classList.remove('active');
}
function _setBounceStatus(msg) {
    const el = document.getElementById('bounceStatusMsg');
    if (el) el.textContent = msg;
}

function cancelBounce() {
    if (_bouncePolling) { clearInterval(_bouncePolling); _bouncePolling = null; }
    _closeBounceModal();
    Toast.show('Export cancelled', 'info');
}

async function bounceProject() {
    if (!STATE.clips.length) { Toast.show('Timeline is empty', 'error'); return; }

    _openBounceModal();
    _setBounceStatus('Collecting video assets…');

    const stateData = serializeVedState();

    // Collect unique video URLs
    const uniqueUrls   = [...new Set(STATE.clips.map(c => c.url).filter(Boolean))];
    const urlToFilename = {};
    const formData      = new FormData();

    for (let i = 0; i < uniqueUrls.length; i++) {
        const url = uniqueUrls[i];
        
        // Ensure absolute paths so we don't accidentally fetch relative HTML pages from the php router
        const fetchUrl = typeof _absoluteUrl === 'function' ? _absoluteUrl(url) : (url.startsWith('http') || url.startsWith('blob:') ? url : '/' + url.replace(/^\//, ''));
        
        _setBounceStatus(`Fetching asset ${i + 1} / ${uniqueUrls.length}…`);
        try {
            const blob = await fetch(fetchUrl).then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                
                const ct = r.headers.get('content-type') || '';
                if (ct.includes('text/html')) {
                    throw new Error('Received HTML instead of valid media.');
                }
                
                return r.blob();
            });
            const ext = url.split('.').pop().split('?')[0] || 'mp4';
            const fname = `asset_${i}.${ext}`;
            urlToFilename[url] = fname;
            formData.append('files', blob, fname);
        } catch(err) {
            _closeBounceModal();
            console.error('Fetch error for', url, err);
            Toast.show('Failed to fetch: ' + url, 'error');
            return;
        }
    }

    // Wire bounce_filename into clips for PyAPI
    stateData.clips.forEach(c => {
        if (c.url) c.bounce_filename = urlToFilename[c.url];
    });

    formData.append('state_json', JSON.stringify(stateData));
    formData.append('canvas_w', PROJECT.canvasW);
    formData.append('canvas_h', PROJECT.canvasH);

    _setBounceStatus('Uploading & queueing render…');

    try {
       const baseUrl = await api('get_pyapi_url').then(res => {
            if (res.status !== 'success' || !res.url) throw new Error('PyAPI URL not available');
            return res.url.replace(/\/$/, '') + '/ved';
        });
        
        
        const res = await fetch(`${baseUrl}/compose-async`, {
            method: 'POST', body: formData
        }).then(r => r.json());

        if (res.status === 'queued' || res.status === 'processing') {
            _setBounceStatus('Rendering on server…');
            _pollBounce(baseUrl, res.task_id);
        } else {
            _closeBounceModal();
            Toast.show('Export failed: ' + (res.detail || res.status), 'error');
        }
    } catch(err) {
        _closeBounceModal();
        Toast.show('Could not reach PyAPI on port 8009', 'error');
    }
}


function _pollBounce(baseUrl, taskId) {
    if (_bouncePolling) clearInterval(_bouncePolling);

    _bouncePolling = setInterval(async () => {
        try {
            const res = await fetch(`${baseUrl}/status/${taskId}`).then(r => r.json());

            if (res.status === 'completed') {
                clearInterval(_bouncePolling); _bouncePolling = null;
                _setBounceStatus('Done! Saving to database…');

                const body = new URLSearchParams({
                    api_action:  'register_bounce',
                    task_id:     taskId,
                    animatic_id: STATE.animaticId || 0,
                    name:        'VED Export ' + new Date().toLocaleTimeString(),
                    canvas_w:    PROJECT.canvasW,
                    canvas_h:    PROJECT.canvasH,
                });

                fetch('?api_action=register_bounce', { method: 'POST', body })
                    .then(r => r.json())
                    .then(dbRes => {
                        _closeBounceModal();
                        if (dbRes.status === 'success') {
                            Toast.show(`Export saved as Video #${dbRes.video_id}`, 'success');
                        } else {
                            Toast.show('DB error: ' + (dbRes.message || 'unknown'), 'error');
                        }
                    })
                    .catch(() => { _closeBounceModal(); Toast.show('Network error saving to DB', 'error'); });

            } else if (res.status === 'failed') {
                clearInterval(_bouncePolling); _bouncePolling = null;
                _closeBounceModal();
                Toast.show('Export failed: ' + (res.error || 'unknown'), 'error');
            }
        } catch(e) { /* transient — keep polling */ }
    }, 1500);
}