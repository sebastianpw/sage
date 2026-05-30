// public/vedtriccs/js/vt-transitions.js
// THE NEW MODULE — Transition panel, connector pip management, type grid
// This is the MuviTriccs brain inside the VED shell.

'use strict';

// ─── Transition Definitions Cache ────────────────────────────────────────────
let VT_TRANSITIONS = {};  // family → [ { name, icon, label, description } ]

const VT_TRANS_ICONS = {
    'cross_dissolve': '⊕', 'fade_to_black': '⬛', 'fade_to_white': '⬜', 'luma_wipe': '◑',
    'slide_left': '←', 'slide_right': '→', 'slide_up': '↑', 'slide_down': '↓',
    'push_left': '⇦', 'push_right': '⇨',
    'zoom_in': '⊕', 'zoom_out': '⊖', 'spin_cw': '↻', 'spin_ccw': '↺',
    'whip_pan_left': '⇇', 'whip_pan_right': '⇉',
    'motion_blur_cut': '〰', 'radial_blur_cut': '❂', 'defocus_cut': '◍',
    'flash': '⚡', 'glitch': '▒', 'rgb_split': '⫷', 'wave_warp': '〜',
    'lens_distortion': '◉', 'film_burn': '🔥', 'light_leak': '☀', 'scanline_tear': '▤', 'vhs_dropout': '📼',
    'optical_flow_warp': '≚', 'depth_parallax': '⇕',
    'pixel_sort': '▚', 'ink_wash': '💧', 'shatter': '💥', 'smear_frame': '💨',
    'cube_rotate_left': '◧', 'cube_rotate_right': '◨', 'page_curl': '⎘',
    'kaleidoscope': '💠', 'ripple_water': '🌊', 'dream_blur': '✨',
    'speed_ramp': '⏱', 'shockwave': '💢', 'strobe_cut': '🔦',
    'motion_trail': '👻', 'glare_hit': '🌟', 'iris_wipe': '⭕',
    'venetian_blind': '🪟', 'cross_zoom': '🔍', 'tilt_shift_cut': '🔬',
    'cinematic_bars': '🎞', 'whip_zoom': '🌀',
};
function vtTransIcon(name) { return VT_TRANS_ICONS[name] || '✨'; }
function vtTransLabel(name) {
    return name.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

// ─── Load Transitions from PyAPI ─────────────────────────────────────────────
function vtLoadTransitions() {
    vtApi('list_transitions').then(res => {
        if (res.status !== 'success') { vtBuildOfflineTransitionGrid(); return; }
        VT_TRANSITIONS = {};
        (res.transitions || []).forEach(tr => {
            if (!VT_TRANSITIONS[tr.family]) VT_TRANSITIONS[tr.family] = [];
            VT_TRANSITIONS[tr.family].push({
                name:        tr.name,
                icon:        vtTransIcon(tr.name),
                label:       vtTransLabel(tr.name),
                description: tr.description || '',
            });
        });
        vtBuildTransitionGrid();
    }).catch(() => vtBuildOfflineTransitionGrid());
}

function vtBuildOfflineTransitionGrid() {
    VT_TRANSITIONS = {
        core:     [{ name:'cross_dissolve', icon:'⊕', label:'Cross Dissolve', description:'Classic crossfade' },
                   { name:'fade_to_black',  icon:'⬛', label:'Fade Black',    description:'Fade through black' }],
        motion:   [{ name:'slide_left',     icon:'←',  label:'Slide Left',    description:'Outgoing slides left' },
                   { name:'zoom_in',        icon:'⊕',  label:'Zoom In',       description:'Zoom into incoming' }],
        stylized: [{ name:'glitch',         icon:'▒',  label:'Glitch',        description:'Digital glitch' },
                   { name:'flash',          icon:'⚡',  label:'Flash',         description:'White flash cut' }],
    };
    vtBuildTransitionGrid();
}

function vtBuildTransitionGrid() {
    const container = document.getElementById('vtTransFamilyScroll');
    if (!container) return;
    container.innerHTML = '';

    const FAMILY_ORDER = ['core','motion','optical','stylized','creative','flow','depth'];
    const families = Object.keys(VT_TRANSITIONS).sort((a, b) => {
        const ia = FAMILY_ORDER.indexOf(a); const ib = FAMILY_ORDER.indexOf(b);
        return (ia < 0 ? 999 : ia) - (ib < 0 ? 999 : ib);
    });

    families.forEach(family => {
        const lbl = document.createElement('div');
        lbl.className   = 'vt-trans-family-label';
        lbl.textContent = family.charAt(0).toUpperCase() + family.slice(1);
        container.appendChild(lbl);

        const grid = document.createElement('div');
        grid.className = 'vt-trans-grid';
        VT_TRANSITIONS[family].forEach(tr => {
            const card = document.createElement('div');
            card.className   = 'vt-trans-card';
            card.dataset.name = tr.name;
            card.title       = tr.description;
            card.innerHTML   = `<span class="tc-icon">${tr.icon}</span>${tr.label}`;
            card.addEventListener('click', () => vtSelectTransitionCard(tr.name));
            grid.appendChild(card);
        });
        container.appendChild(grid);
    });

    // Re-select current if panel is already showing a connector
    if (VT_STATE.selectedConnKey) {
        const conn = VT_STATE.connectors[VT_STATE.selectedConnKey];
        if (conn) vtSelectTransitionCard(conn.transitionName || 'cross_dissolve', false);
    }
}

function vtSelectTransitionCard(name, saveToConn = true) {
    document.querySelectorAll('.vt-trans-card').forEach(c =>
        c.classList.toggle('sel', c.dataset.name === name));

    if (saveToConn && VT_STATE.selectedConnKey) {
        const conn = vtGetOrCreateConnector(VT_STATE.selectedConnKey);
        conn.transitionName = name;
        // Live-update the pip label on clip
        vtUpdatePipLabel(VT_STATE.selectedConnKey);
    }
}

function vtGetSelectedTransitionName() {
    return document.querySelector('.vt-trans-card.sel')?.dataset?.name || 'cross_dissolve';
}

// ─── Connector Get/Create ─────────────────────────────────────────────────────
function vtGetOrCreateConnector(key) {
    if (!VT_STATE.connectors[key]) {
        VT_STATE.connectors[key] = {
            transitionName:  'cross_dissolve',
            durationFrames:  24,
            intensity:       1.0,
            easing:          'ease_in_out_cubic',
            seed:            42,
            jobId:           null,
            jobStatus:       null,
            videoId:         null,
        };
    }
    return VT_STATE.connectors[key];
}

// ─── Connector Panel — open/select ───────────────────────────────────────────
function vtSelectConnector(key) {
    // Deactivate previous pip
    if (VT_STATE.selectedConnKey && VT_STATE.selectedConnKey !== key) {
        vtUpdatePipActiveState(VT_STATE.selectedConnKey, false);
    }

    VT_STATE.selectedConnKey = key;
    vtUpdatePipActiveState(key, true);

    const conn = vtGetOrCreateConnector(key);

    // Open panel if not open
    vtOpenTransPanel();

    // Populate panel title
    const titleEl = document.getElementById('vtTransPanelTitle');
    if (titleEl) {
        titleEl.classList.add('active');
        titleEl.querySelector('span').textContent =
            vtTransLabel(conn.transitionName || 'cross_dissolve') + ' — click type to change';
    }

    // Show action buttons
    document.getElementById('vtTransPanelActions').style.display = 'flex';

    // Populate params
    const durRange = document.getElementById('tcDurRange');
    const durNum   = document.getElementById('tcDurNum');
    if (durRange) durRange.value = conn.durationFrames || 24;
    if (durNum)   durNum.value   = conn.durationFrames || 24;
    const intRange = document.getElementById('tcIntRange');
    if (intRange) {
        intRange.value = conn.intensity || 1.0;
        document.getElementById('tcIntVal').textContent = parseFloat(conn.intensity || 1.0).toFixed(2);
    }
    const easing = document.getElementById('tcEasing');
    if (easing) easing.value = conn.easing || 'ease_in_out_cubic';
    const seed = document.getElementById('tcSeed');
    if (seed) seed.value = conn.seed || 42;

    // Select type card
    vtSelectTransitionCard(conn.transitionName || 'cross_dissolve', false);

    // Update status
    vtUpdateConnectorStatus(key);
}

function vtUpdateConnectorStatus(key) {
    const el   = document.getElementById('vtConnStatus');
    if (!el) return;
    const conn = VT_STATE.connectors[key];
    if (!conn) { el.textContent = ''; el.className = 'vt-tp-status'; return; }

    const status = conn.jobStatus;
    if (!status) { el.textContent = ''; el.className = 'vt-tp-status'; return; }

    el.className = `vt-tp-status ${status}`;
    const dot = '<span class="st-dot"></span>';
    if (status === 'queued')     el.innerHTML = dot + 'Render queued…';
    else if (status === 'processing') el.innerHTML = dot + 'Rendering…';
    else if (status === 'completed')  el.innerHTML = dot + 'Render ready — Video #' + (conn.videoId || '?');
    else if (status === 'failed')     el.innerHTML = dot + 'Render failed';
    else el.textContent = '';
}

// ─── Panel open/close/toggle ──────────────────────────────────────────────────
let vtTransPanelOpen = false;

function vtOpenTransPanel() {
    if (vtTransPanelOpen) return;
    vtTransPanelOpen = true;
    const panel = document.getElementById('vtTransPanel');
    if (panel) panel.style.display = 'flex';
    document.getElementById('mbBtnTrans')?.classList.add('active');
    document.getElementById('vtTransPanelChevron').className = 'bi bi-chevron-down';
}

function vtCloseTransPanel() {
    vtTransPanelOpen = false;
    const panel = document.getElementById('vtTransPanel');
    if (panel) panel.style.display = 'none';
    document.getElementById('mbBtnTrans')?.classList.remove('active');
    const chevron = document.getElementById('vtTransPanelChevron');
    if (chevron) chevron.className = 'bi bi-chevron-up';
    // Deactivate pip
    if (VT_STATE.selectedConnKey) {
        vtUpdatePipActiveState(VT_STATE.selectedConnKey, false);
        VT_STATE.selectedConnKey = null;
    }
    // Reset title
    const titleEl = document.getElementById('vtTransPanelTitle');
    if (titleEl) {
        titleEl.classList.remove('active');
        titleEl.querySelector('span').textContent = 'Select connector to configure transition';
    }
    document.getElementById('vtTransPanelActions').style.display = 'none';
}

function vtToggleTransPanel() {
    if (vtTransPanelOpen) vtCloseTransPanel();
    else vtOpenTransPanel();
}

// ─── Pip management ──────────────────────────────────────────────────────────
// Each clip that has a right-adjacent clip on the same track gets a connector pip
// rendered on its right edge. Clicking it selects the connector.

function vtRefreshAllConnectorPips() {
    // Remove all existing pips first
    document.querySelectorAll('.vt-connector-pip').forEach(el => el.remove());
    document.querySelectorAll('.vt-clip').forEach(el => {
        el.classList.remove('has-connector-r', 'has-connector-l');
    });

    const sorted = vtGetSortedClipsPerTrack();
    for (const [, clips] of Object.entries(sorted)) {
        for (let i = 0; i < clips.length - 1; i++) {
            const clipA = clips[i];
            const clipB = clips[i + 1];
            vtCreateConnectorPip(clipA, clipB);
        }
    }
}

function vtCreateConnectorPip(clipA, clipB) {
    if (!clipA.el || !clipB.el) return;

    const key  = vtConnectorKey(clipA, clipB);
    const conn = VT_STATE.connectors[key];

    const pip  = document.createElement('div');
    pip.className  = 'vt-connector-pip';
    pip.dataset.key = key;
    if (VT_STATE.selectedConnKey === key) pip.classList.add('active');

    // Icon: shuffle by default, play-fill if rendered
    const hasRender = conn?.videoId;
    pip.innerHTML   = `<span class="pip-icon">${hasRender ? '▶' : '⇌'}</span>`;
    if (hasRender) {
        const dot = document.createElement('div');
        dot.className = 'pip-rendered-dot';
        pip.appendChild(dot);
    }

    // Label tooltip
    const transName = conn?.transitionName || 'cross_dissolve';
    pip.title = vtTransLabel(transName);

    pip.addEventListener('click', e => {
        e.stopPropagation();
        vtSelectConnector(key);
    });

    // Prevent drag from propagating when touching pip
    pip.addEventListener('mousedown',  e => e.stopPropagation());
    pip.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });

    clipA.el.appendChild(pip);
    clipA.el.classList.add('has-connector-r');
    clipB.el.classList.add('has-connector-l');
}

function vtUpdatePipActiveState(key, active) {
    document.querySelectorAll(`.vt-connector-pip[data-key="${key}"]`).forEach(pip => {
        pip.classList.toggle('active', active);
    });
}

function vtUpdatePipLabel(key) {
    const conn = VT_STATE.connectors[key];
    if (!conn) return;
    document.querySelectorAll(`.vt-connector-pip[data-key="${key}"]`).forEach(pip => {
        pip.title = vtTransLabel(conn.transitionName || 'cross_dissolve');
    });
}

// ─── Save Connector Params ────────────────────────────────────────────────────
function vtSaveConnector() {
    const key = VT_STATE.selectedConnKey;
    if (!key) { Toast.show('No connector selected', 'info'); return; }

    const conn = vtGetOrCreateConnector(key);
    conn.transitionName  = vtGetSelectedTransitionName();
    conn.durationFrames  = parseInt(document.getElementById('tcDurNum')?.value  || 24);
    conn.intensity       = parseFloat(document.getElementById('tcIntRange')?.value || 1.0);
    conn.easing          = document.getElementById('tcEasing')?.value  || 'ease_in_out_cubic';
    conn.seed            = parseInt(document.getElementById('tcSeed')?.value || 42);

    vtUpdatePipLabel(key);

    // Persist to server if we have a file context
    if (VT_STATE.currentFileId) {
        vtApi('save_connector', {
            file_id:       VT_STATE.currentFileId,
            connector_key: key,
            params:        JSON.stringify(conn),
        }, 'POST').then(res => {
            Toast.show(res.status === 'success' ? 'Connector saved' : 'Save error: ' + res.message,
                res.status === 'success' ? 'success' : 'error');
        });
    } else {
        Toast.show('Connector saved (in session — save project to persist)', 'info');
    }
}

// ─── Render Connector ─────────────────────────────────────────────────────────
function vtRenderConnector() {
    const key = VT_STATE.selectedConnKey;
    if (!key) { Toast.show('No connector selected', 'info'); return; }

    // Find the two clips for this connector
    let clipA = null, clipB = null;
    const sorted = vtGetSortedClipsPerTrack();
    outer:
    for (const [, clips] of Object.entries(sorted)) {
        for (let i = 0; i < clips.length - 1; i++) {
            if (vtConnectorKey(clips[i], clips[i+1]) === key) {
                clipA = clips[i]; clipB = clips[i+1]; break outer;
            }
        }
    }
    if (!clipA || !clipB) { Toast.show('Could not find clips for this connector', 'error'); return; }
    if (!clipA.url || !clipB.url) { Toast.show('Clips must have valid URLs', 'error'); return; }

    const conn = vtGetOrCreateConnector(key);
    conn.transitionName  = vtGetSelectedTransitionName();
    conn.durationFrames  = parseInt(document.getElementById('tcDurNum')?.value  || 24);
    conn.intensity       = parseFloat(document.getElementById('tcIntRange')?.value || 1.0);
    conn.easing          = document.getElementById('tcEasing')?.value  || 'ease_in_out_cubic';
    conn.seed            = parseInt(document.getElementById('tcSeed')?.value || 42);

    // Extract exact trim bounds and playback speeds
    const trimStartA = clipA.trimStart || 0;
    const trimEndA   = clipA.trimEnd !== null ? clipA.trimEnd : (clipA.duration || 30);
    const trimStartB = clipB.trimStart || 0;
    const trimEndB   = clipB.trimEnd !== null ? clipB.trimEnd : (clipB.duration || 30);
    const speedA     = clipA.playbackSpeed || 1.0;
    const speedB     = clipB.playbackSpeed || 1.0;

    if (!confirm(`Render "${vtTransLabel(conn.transitionName)}" transition between these two clips?`)) return;

    conn.jobStatus = 'queued';
    vtUpdateConnectorStatus(key);
    Toast.show('Queuing render…', 'info');

    vtApi('queue_transition_render', {
        connector_key:   key,
        file_id:         VT_STATE.currentFileId || 0,
        url_a:           clipA.url,
        url_b:           clipB.url,
        trim_start_a:    trimStartA,
        trim_end_a:      trimEndA,
        trim_start_b:    trimStartB,
        trim_end_b:      trimEndB,
        speed_a:         speedA,
        speed_b:         speedB,
        transition_name: conn.transitionName,
        duration_frames: conn.durationFrames,
        fps:             VT_STATE.fps,
        output_w:        VT_PROJECT.canvasW,
        output_h:        VT_PROJECT.canvasH,
        intensity:       conn.intensity,
        easing:          conn.easing,
        seed:            conn.seed,
    }, 'POST').then(res => {
        if (res.status !== 'success') {
            conn.jobStatus = 'failed';
            vtUpdateConnectorStatus(key);
            Toast.show('Render error: ' + res.message, 'error');
            return;
        }
        conn.jobId     = res.job_id;
        conn.jobStatus = 'processing';
        vtUpdateConnectorStatus(key);
        Toast.show('Render queued — Job #' + res.job_id, 'success');
        vtPollRenderJob(key, res.job_id);
    }).catch(err => {
        conn.jobStatus = 'failed';
        vtUpdateConnectorStatus(key);
        Toast.show('Network error queuing render', 'error');
    });
}

// ─── Poll Render Job ──────────────────────────────────────────────────────────
const _vtPolling = {};

function vtPollRenderJob(key, jobId) {
    if (_vtPolling[jobId]) return;
    _vtPolling[jobId] = setInterval(() => {
        vtApi('poll_transition_render', { job_id: jobId }).then(res => {
            if (res.status !== 'success') return;
            const conn = VT_STATE.connectors[key];
            if (!conn) { clearInterval(_vtPolling[jobId]); delete _vtPolling[jobId]; return; }

            conn.jobStatus = res.job_status;

            if (res.job_status === 'completed') {
                clearInterval(_vtPolling[jobId]); delete _vtPolling[jobId];
                conn.videoId = res.video_id;
                vtRefreshAllConnectorPips();
                if (VT_STATE.selectedConnKey === key) vtUpdateConnectorStatus(key);
                Toast.show(`✓ Render complete — Video #${res.video_id}`, 'success');
            } else if (res.job_status === 'failed') {
                clearInterval(_vtPolling[jobId]); delete _vtPolling[jobId];
                if (VT_STATE.selectedConnKey === key) vtUpdateConnectorStatus(key);
                Toast.show('Render failed: ' + (res.error || 'unknown'), 'error');
            } else {
                if (VT_STATE.selectedConnKey === key) vtUpdateConnectorStatus(key);
            }
        });
    }, 2000);
}

// ─── Browse Demos ─────────────────────────────────────────────────────────────
let vtBrowseTransName = '';
let vtBrowsePage = 1;
let vtBrowseTotalPages = 1;
let vtBrowseSearchTimer = null;

function vtOpenBrowseDemos() {
    const key = VT_STATE.selectedConnKey;
    const conn = key ? VT_STATE.connectors[key] : null;
    vtBrowseTransName = conn?.transitionName || vtGetSelectedTransitionName();
    vtBrowsePage = 1;

    document.getElementById('browseModalTitle').textContent =
        'Renders — ' + vtTransLabel(vtBrowseTransName);
    document.getElementById('browseSearch').value = '';
    document.getElementById('browseBackdrop').classList.add('open');
    vtLoadBrowsePage(1);
}

function vtCloseBrowseModal() {
    document.getElementById('browseBackdrop').classList.remove('open');
}

function vtDebouncedBrowseSearch() {
    clearTimeout(vtBrowseSearchTimer);
    vtBrowseSearchTimer = setTimeout(() => vtLoadBrowsePage(1), 280);
}

function vtChangeBrowsePage(d) {
    const next = vtBrowsePage + d;
    if (next < 1 || next > vtBrowseTotalPages) return;
    vtLoadBrowsePage(next);
}

function vtLoadBrowsePage(page) {
    vtBrowsePage = page;
    const q      = document.getElementById('browseSearch')?.value?.trim() || '';
    const list   = document.getElementById('browseList');
    const pgBar  = document.getElementById('browsePagination');
    if (list) list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-dim);font-size:.72rem;">Loading…</div>';
    if (pgBar) pgBar.style.display = 'none';

    vtApi('browse_transition_demos', {
        transition_name: vtBrowseTransName,
        page, q,
    }).then(res => {
        if (res.status !== 'success' || !res.data?.length) {
            if (list) list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-dim);font-size:.72rem;">No renders found for this transition type yet.<br><small>Render a transition first using the Render button.</small></div>';
            return;
        }
        vtBrowseTotalPages = res.total_pages || 1;
        document.getElementById('browsePgInfo').textContent =
            `Page ${res.page} of ${vtBrowseTotalPages}`;
        document.getElementById('browsePrev').disabled = res.page <= 1;
        document.getElementById('browseNext').disabled = res.page >= vtBrowseTotalPages;
        if (pgBar && vtBrowseTotalPages > 1) pgBar.style.display = 'flex';

        if (list) list.innerHTML = '';
        res.data.forEach(item => vtRenderBrowseItem(list, item));
    }).catch(() => {
        if (list) list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-dim);">Network error</div>';
    });
}

function vtRenderBrowseItem(container, item) {
    const div = document.createElement('div');
    div.className = 'browse-item';
    const thumbHtml = item.thumbnail
        ? `<img src="/${vtEsc(item.thumbnail)}" class="browse-thumb" title="Play">`
        : `<div class="browse-thumb" style="display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:20px;">⬛</div>`;
    const date = item.rendered_at ? new Date(item.rendered_at).toLocaleDateString() : '';
    div.innerHTML = `
        ${thumbHtml}
        <div class="browse-info">
            <strong>#${item.video_id} ${vtEsc(item.video_name || '')}</strong>
            <small>Job #${item.job_id} · ${date}</small>
        </div>
        <div class="browse-actions">
            <button class="browse-btn" data-vid="${item.video_id}">▶ Play</button>
            ${item.is_primary
                ? `<button class="browse-btn" data-unassign="${item.video_id}">Unassign</button>`
                : `<button class="browse-btn primary" data-assign="${item.video_id}" data-job="${item.job_id}">★ Primary</button>`
            }
        </div>
    `;
    div.querySelector('.browse-thumb, .browse-btn[data-vid]')?.addEventListener('click', () => {
        vtPreviewVideo(item.video_id, 'Transition: ' + vtTransLabel(vtBrowseTransName));
    });
    div.querySelector('.browse-btn[data-vid]')?.addEventListener('click', () => {
        vtPreviewVideo(item.video_id, 'Transition: ' + vtTransLabel(vtBrowseTransName));
    });
    const assignBtn = div.querySelector('.browse-btn[data-assign]');
    if (assignBtn) assignBtn.addEventListener('click', () => {
        vtApi('assign_demo', { transition_name: vtBrowseTransName, video_id: item.video_id, job_id: item.job_id, set_primary: 1 }, 'POST')
            .then(() => { Toast.show('Set as primary demo', 'success'); vtLoadBrowsePage(vtBrowsePage); });
    });
    const unBtn = div.querySelector('.browse-btn[data-unassign]');
    if (unBtn) unBtn.addEventListener('click', () => {
        vtApi('unassign_demo', { transition_name: vtBrowseTransName, video_id: item.video_id }, 'POST')
            .then(() => { Toast.show('Unassigned', 'info'); vtLoadBrowsePage(vtBrowsePage); });
    });
    if (container) container.appendChild(div);
}

function vtPreviewVideo(videoId, title) {
    vtApi('get_video_url', { video_id: videoId }).then(res => {
        if (res.status !== 'success') return;
        vtOpenPreviewModal();
        const v = document.getElementById('vtPreviewVideo');
        if (v) {
            v.src = '/' + res.url;
            v.play().catch(() => {});
        }
        document.getElementById('vtFvInfo').textContent = title || '';
    });
}