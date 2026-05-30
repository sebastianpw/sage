// public/vedtriccs/js/vt-modal.js
// Settings, Save/Load, Trim, Bounce modals — VedTriccs Edition

'use strict';

// ─── Grid Swatches ────────────────────────────────────────────────────────────
function vtBuildSwatches() {
    const row = document.getElementById('pmSwatchRow');
    if (!row) return;
    VT_GRID_COLORS.forEach((c, i) => {
        const s = document.createElement('div');
        s.className = 'pm-swatch' + (i === vtSelectedSwatchIdx ? ' active' : '');
        s.style.background = c;
        s.title    = c;
        s.onclick  = () => {
            vtSelectedSwatchIdx = i;
            document.querySelectorAll('.pm-swatch').forEach((el, j) => el.classList.toggle('active', i === j));
        };
        row.appendChild(s);
    });
}

// ─── Settings Modal ───────────────────────────────────────────────────────────
function vtOpenSettingsModal() {
    document.getElementById('pmGridVisible').checked = VT_PROJECT.gridVisible;
    document.getElementById('pmSnapEnabled').checked = VT_PROJECT.snapEnabled;
    document.getElementById('pmSnapDiv').value        = String(VT_PROJECT.snapDiv);
    document.getElementById('pmGridOpacity').value    = VT_PROJECT.gridOpacity;
    document.getElementById('pmOpacityVal').textContent = VT_PROJECT.gridOpacity + '%';

    const resParts = VT_PROJECT.canvasW + 'x' + VT_PROJECT.canvasH;
    const resSel   = document.getElementById('pmResolution');
    if (resSel) {
        const opt = [...resSel.options].find(o => o.value === resParts);
        resSel.value = opt ? resParts : 'custom';
    }
    vtSelectedSwatchIdx = Math.max(0, VT_GRID_COLORS.indexOf(VT_PROJECT.gridColor));
    document.querySelectorAll('.pm-swatch').forEach((s, j) => s.classList.toggle('active', j === vtSelectedSwatchIdx));

    document.getElementById('settingsBackdrop').classList.add('open');
    document.getElementById('mbBtnSettings').classList.add('active');
}
function vtCloseSettingsModal() {
    document.getElementById('settingsBackdrop').classList.remove('open');
    document.getElementById('mbBtnSettings').classList.remove('active');
}
function vtApplySettings() {
    VT_PROJECT.gridVisible = document.getElementById('pmGridVisible').checked;
    VT_PROJECT.snapEnabled = document.getElementById('pmSnapEnabled').checked;
    VT_PROJECT.snapDiv     = parseFloat(document.getElementById('pmSnapDiv').value) || 0.25;
    VT_PROJECT.gridColor   = VT_GRID_COLORS[vtSelectedSwatchIdx] || '#f59e0b';
    VT_PROJECT.gridOpacity = parseInt(document.getElementById('pmGridOpacity').value) || 18;
    const res = document.getElementById('pmResolution').value;
    if (res && res !== 'custom') {
        const [w, h]      = res.split('x').map(Number);
        VT_PROJECT.canvasW = w; VT_PROJECT.canvasH = h;
    }
    vtCloseSettingsModal();
    vtUpdateMasterLayout();
    Toast.show('Settings applied', 'success');
}

// ─── Save / Load Modal ────────────────────────────────────────────────────────
function vtOpenSaveLoadModal() {
    document.getElementById('fileBackdrop').classList.add('open');
    document.getElementById('mbBtnSaveLoad').classList.add('active');
    vtFetchProjectsList();
}
function vtCloseSaveLoadModal() {
    document.getElementById('fileBackdrop').classList.remove('open');
    document.getElementById('mbBtnSaveLoad').classList.remove('active');
}
function vtFetchProjectsList() {
    vtApi('get_projects').then(res => {
        if (res.status !== 'success') return;
        const sel = document.getElementById('fileProjectSelect');
        const cur = sel.value;
        sel.innerHTML = '<option value="">-- Select Project --</option>' +
            res.data.map(p => `<option value="${p.id}">${vtEsc(p.name)}</option>`).join('');
        if (cur && res.data.find(p => String(p.id) === cur)) sel.value = cur;
        vtLoadProjectFilesList();
    });
}
function vtCreateNewProject() {
    const name = document.getElementById('newProjectName').value.trim();
    if (!name) return;
    vtApi('create_project', { name }, 'POST').then(res => {
        if (res.status === 'success') {
            document.getElementById('newProjectName').value = '';
            vtFetchProjectsList();
            Toast.show('Project created', 'success');
        } else Toast.show(res.message || 'Error', 'error');
    });
}
function vtLoadProjectFilesList() {
    const pid  = document.getElementById('fileProjectSelect').value;
    const list = document.getElementById('fileList');
    if (!pid) {
        list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">Select a project above</div>';
        return;
    }
    list.innerHTML = '<div style="padding:10px;color:var(--text-dim);text-align:center;font-size:.72rem;">Loading…</div>';
    vtApi('get_project_files', { project_id: pid }).then(res => {
        if (res.status !== 'success') return;
        if (!res.data.length) {
            list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">No saves yet</div>';
            return;
        }
        list.innerHTML = res.data.map(f =>
            `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;border-bottom:1px solid var(--border);">
                <span style="font-family:var(--font-sans);font-size:.72rem;">${vtEsc(f.filename)}</span>
                <div style="display:flex;gap:4px;">
                    <button class="pm-btn pm-btn-cancel" style="height:22px;padding:0 8px;font-size:.62rem;" onclick="vtOverwriteVedFile(${f.id}, '${vtEsc(f.filename)}')">
                        <i class="bi bi-floppy"></i>
                    </button>
                    <button class="pm-btn pm-btn-apply" style="height:22px;padding:0 8px;font-size:.62rem;" onclick="vtLoadVedFile(${f.id})">
                        <i class="bi bi-play-fill"></i> Load
                    </button>
                </div>
            </div>`
        ).join('');
    });
}
function vtSaveCurrentProjectFile() {
    const pid   = document.getElementById('fileProjectSelect').value;
    const fname = document.getElementById('newFileName').value.trim();
    if (!pid)   { Toast.show('Select a project first', 'error'); return; }
    if (!fname) { Toast.show('Enter a filename', 'error'); return; }
    const state = JSON.stringify(vtSerialize());
    vtApi('save_project_file', { project_id: pid, filename: fname, state_data: state }, 'POST').then(res => {
        if (res.status === 'success') {
            VT_STATE.currentFileId = res.id;
            document.getElementById('newFileName').value = '';
            vtLoadProjectFilesList();
            Toast.show('Saved', 'success');
        } else Toast.show(res.message || 'Error', 'error');
    });
}
function vtOverwriteVedFile(fileId, filename) {
    if (!confirm('Overwrite "' + filename + '" with current state?')) return;
    const state = JSON.stringify(vtSerialize());
    vtApi('update_project_file', { file_id: fileId, state_data: state }, 'POST').then(res => {
        if (res.status === 'success') {
            VT_STATE.currentFileId = fileId;
            vtLoadProjectFilesList();
            Toast.show('Overwritten', 'success');
        } else Toast.show(res.message || 'Error', 'error');
    });
}
function vtLoadVedFile(fileId) {
    vtApi('load_project_file', { file_id: fileId }).then(res => {
        if (res.status === 'success' && res.data) {
            try {
                vtDeserialize(JSON.parse(res.data.state_data));
                VT_STATE.currentFileId = fileId;
                vtCloseSaveLoadModal();
                Toast.show('Project loaded', 'success');
            } catch(e) {
                Toast.show('Error parsing project data', 'error');
            }
        } else Toast.show(res.message || 'Load failed', 'error');
    });
}

// ─── Clip Trim Modal ──────────────────────────────────────────────────────────
let _vtTrimClipId = null;
let _vtTrimDur    = 0;
let _vtTrimDragH  = null;

function vtOpenTrimModal(clipId) {
    const clip = VT_STATE.clips.find(c => c.id === clipId);
    if (!clip) return;
    _vtTrimClipId = clipId;
    _vtTrimDur    = clip.duration || 30;
    document.getElementById('trimModalTitle').textContent = 'Trim: ' + clip.name;
    document.getElementById('trimVideo').src              = clip.url;
    document.getElementById('trimVideo').currentTime      = clip.trimStart || 0;
    document.getElementById('trimSpeed').value            = clip.playbackSpeed || 1.0;
    document.getElementById('trimSpeedVal').textContent   = (clip.playbackSpeed || 1.0).toFixed(2) + '×';
    _vtRenderTrimUI(clip);
    document.getElementById('trimBackdrop').classList.add('open');
}
function vtCloseTrimModal() {
    document.getElementById('trimBackdrop').classList.remove('open');
    document.getElementById('trimVideo').pause();
    document.getElementById('trimVideo').src = '';
    _vtTrimClipId = null;
}
function _vtRenderTrimUI(clip) {
    const dur  = _vtTrimDur || 1;
    const s    = clip.trimStart || 0;
    const e    = clip.trimEnd !== null ? clip.trimEnd : dur;
    const pctS = (s / dur) * 100;
    const pctE = (e / dur) * 100;
    const track = document.getElementById('trimTrack');
    const hs    = document.getElementById('trimHandleStart');
    const he    = document.getElementById('trimHandleEnd');
    if (!track || !hs || !he) return;
    track.style.left   = pctS + '%';
    track.style.width  = (pctE - pctS) + '%';
    hs.style.left      = pctS + '%';
    he.style.left      = pctE + '%';
    document.getElementById('trimLblStart').textContent = s.toFixed(2) + ' s';
    document.getElementById('trimLblEnd').textContent   = clip.trimEnd !== null ? e.toFixed(2) + ' s' : '(end)';
    document.getElementById('trimLblDur').textContent   = (e - s).toFixed(2) + ' s clip';
}
function vtClearTrim() {
    const clip = VT_STATE.clips.find(c => c.id === _vtTrimClipId);
    if (!clip) return;
    clip.trimStart = 0; clip.trimEnd = null;
    _vtRenderTrimUI(clip);
    Toast.show('Trim cleared', 'info');
}
function vtApplyTrim() {
    const clip = VT_STATE.clips.find(c => c.id === _vtTrimClipId);
    if (!clip) return;
    clip.playbackSpeed = parseFloat(document.getElementById('trimSpeed').value) || 1.0;
    vtRefreshClipEl(clip);
    vtUpdateMasterLayout();
    vtCloseTrimModal();
    Toast.show('Trim applied', 'success');
}

// Trim drag via Pointer Events
(function _setupTrimDrag() {
    const tl = document.getElementById('trimTimeline');
    const hs = document.getElementById('trimHandleStart');
    const he = document.getElementById('trimHandleEnd');
    if (!tl || !hs || !he) return;
    function startDrag(side, e) { e.preventDefault(); e.stopPropagation(); _vtTrimDragH = side; tl.setPointerCapture(e.pointerId); }
    function onMove(e) {
        if (!_vtTrimDragH || !_vtTrimClipId) return;
        const clip = VT_STATE.clips.find(c => c.id === _vtTrimClipId);
        if (!clip) return;
        const rect = tl.getBoundingClientRect();
        const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        const t    = pct * (_vtTrimDur || 1);
        if (_vtTrimDragH === 'start') {
            clip.trimStart = Math.max(0, Math.min(t, (clip.trimEnd || _vtTrimDur) - 0.1));
        } else {
            clip.trimEnd = Math.min(_vtTrimDur, Math.max(clip.trimStart + 0.1, t));
            if (clip.trimEnd >= (_vtTrimDur - 0.05)) clip.trimEnd = null;
        }
        const vid = document.getElementById('trimVideo');
        if (vid) vid.currentTime = clip.trimStart;
        _vtRenderTrimUI(clip);
    }
    function onUp() { _vtTrimDragH = null; }
    hs.addEventListener('pointerdown', e => startDrag('start', e));
    he.addEventListener('pointerdown', e => startDrag('end', e));
    tl.addEventListener('pointermove', onMove);
    tl.addEventListener('pointerup',   onUp);
    tl.addEventListener('pointercancel', onUp);
})();

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('trimSpeed')?.addEventListener('input', function() {
        document.getElementById('trimSpeedVal').textContent = parseFloat(this.value).toFixed(2) + '×';
        const vid = document.getElementById('trimVideo');
        if (vid) vid.playbackRate = parseFloat(this.value) || 1.0;
    });
});

// ─── Bounce / Export ──────────────────────────────────────────────────────────
let _vtBouncePolling = null;

function _vtOpenBounceModal() {
    document.getElementById('bounceBackdrop').classList.add('open');
    document.getElementById('mbBtnBounce').classList.add('active');
}
function _vtCloseBounceModal() {
    document.getElementById('bounceBackdrop').classList.remove('open');
    document.getElementById('mbBtnBounce').classList.remove('active');
}
function _vtSetBounceStatus(msg) {
    const el = document.getElementById('bounceStatusMsg');
    if (el) el.textContent = msg;
}
function vtCancelBounce() {
    if (_vtBouncePolling) { clearInterval(_vtBouncePolling); _vtBouncePolling = null; }
    _vtCloseBounceModal();
    Toast.show('Export cancelled', 'info');
}

async function vtBounceProject() {
    if (!VT_STATE.clips.length) { Toast.show('Timeline is empty', 'error'); return; }
    _vtOpenBounceModal();
    _vtSetBounceStatus('Collecting video assets…');

    const stateData    = vtSerialize();
    const uniqueUrls   = [...new Set(VT_STATE.clips.map(c => c.url).filter(Boolean))];
    const urlToFilename = {};
    const formData     = new FormData();

    for (let i = 0; i < uniqueUrls.length; i++) {
        const url      = uniqueUrls[i];
        const fetchUrl = url.startsWith('http') || url.startsWith('blob:') ? url : '/' + url.replace(/^\//, '');
        _vtSetBounceStatus(`Fetching asset ${i + 1} / ${uniqueUrls.length}…`);
        try {
            const blob = await fetch(fetchUrl).then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                if ((r.headers.get('content-type') || '').includes('text/html'))
                    throw new Error('Received HTML instead of media');
                return r.blob();
            });
            const ext   = url.split('.').pop().split('?')[0] || 'mp4';
            const fname = `asset_${i}.${ext}`;
            urlToFilename[url] = fname;
            formData.append('files', blob, fname);
        } catch(err) {
            _vtCloseBounceModal();
            Toast.show('Failed to fetch: ' + url, 'error');
            return;
        }
    }

    stateData.clips.forEach(c => { if (c.url) c.bounce_filename = urlToFilename[c.url]; });
    formData.append('state_json', JSON.stringify(stateData));
    formData.append('canvas_w', VT_PROJECT.canvasW);
    formData.append('canvas_h', VT_PROJECT.canvasH);
    _vtSetBounceStatus('Uploading & queuing render…');

    try {
        const baseUrl = await vtApi('get_pyapi_url').then(res => {
            if (res.status !== 'success' || !res.url) throw new Error('PyAPI unavailable');
            return res.url.replace(/\/$/, '') + '/ved';
        });
        const res = await fetch(`${baseUrl}/compose-async`, { method: 'POST', body: formData }).then(r => r.json());
        if (res.status === 'queued' || res.status === 'processing') {
            _vtSetBounceStatus('Rendering on server…');
            _vtPollBounce(baseUrl, res.task_id);
        } else {
            _vtCloseBounceModal();
            Toast.show('Export failed: ' + (res.detail || res.status), 'error');
        }
    } catch(err) {
        _vtCloseBounceModal();
        Toast.show('Could not reach PyAPI', 'error');
    }
}

function _vtPollBounce(baseUrl, taskId) {
    if (_vtBouncePolling) clearInterval(_vtBouncePolling);
    _vtBouncePolling = setInterval(async () => {
        try {
            const res = await fetch(`${baseUrl}/status/${taskId}`).then(r => r.json());
            if (res.status === 'completed') {
                clearInterval(_vtBouncePolling); _vtBouncePolling = null;
                _vtSetBounceStatus('Done! Saving to database…');
                const body = new URLSearchParams({
                    api_action:  'register_bounce',
                    task_id:     taskId,
                    animatic_id: VT_STATE.animaticId || 0,
                    name:        'VedTriccs Export ' + new Date().toLocaleTimeString(),
                    canvas_w:    VT_PROJECT.canvasW,
                    canvas_h:    VT_PROJECT.canvasH,
                    state_json:  JSON.stringify(vtSerialize()),
                });
                fetch('?api_action=register_bounce', { method: 'POST', body })
                    .then(r => r.json()).then(dbRes => {
                        _vtCloseBounceModal();
                        if (dbRes.status === 'success') Toast.show(`Export saved as Video #${dbRes.video_id}`, 'success');
                        else Toast.show('DB error: ' + (dbRes.message || 'unknown'), 'error');
                    }).catch(() => { _vtCloseBounceModal(); Toast.show('Network error saving to DB', 'error'); });
            } else if (res.status === 'failed') {
                clearInterval(_vtBouncePolling); _vtBouncePolling = null;
                _vtCloseBounceModal();
                Toast.show('Export failed: ' + (res.error || 'unknown'), 'error');
            }
        } catch(e) { /* transient */ }
    }, 1500);
}
