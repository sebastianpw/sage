// public/daw/js/daw-modal.js
// Project Settings param modal & File Save/Load modal & Master Channel modal

'use strict';

// ─── Project Settings Modal ─────────────────────────────────────────────────

function buildSwatches() {
    const row = document.getElementById('pmSwatchRow');
    if (!row) return;
    GRID_COLORS.forEach((c, i) => {
        const s = document.createElement('div');
        s.className = 'pm-swatch' + (i === selectedSwatchIdx ? ' active' : '');
        s.style.background = c;
        s.title   = c;
        s.onclick = () => selectSwatch(i);
        row.appendChild(s);
    });
}

function selectSwatch(i) {
    selectedSwatchIdx = i;
    document.querySelectorAll('.pm-swatch').forEach((s, j) => s.classList.toggle('active', i === j));
}

function openParamModal() {
    document.getElementById('pmBpm').value       = PROJECT.bpm;
    document.getElementById('pmSigNum').value    = PROJECT.sigNum;
    document.getElementById('pmSigDen').value    = String(PROJECT.sigDen);
    document.getElementById('pmGridDiv').value   = String(PROJECT.gridDiv);
    document.getElementById('pmGridVisible').checked  = PROJECT.gridVisible;
    document.getElementById('pmSnapEnabled').checked  = PROJECT.snapEnabled;
    document.getElementById('pmGridOpacity').value    = PROJECT.gridOpacity;
    document.getElementById('pmOpacityVal').textContent = PROJECT.gridOpacity + '%';

    selectedSwatchIdx = GRID_COLORS.indexOf(PROJECT.gridColor);
    if (selectedSwatchIdx < 0) selectedSwatchIdx = 0;
    document.querySelectorAll('.pm-swatch').forEach((s, j) => s.classList.toggle('active', j === selectedSwatchIdx));

    document.getElementById('paramBackdrop').classList.add('open');
    document.getElementById('mbBtnProject').classList.add('active');
}

function closeParamModal() {
    document.getElementById('paramBackdrop').classList.remove('open');
    document.getElementById('mbBtnProject').classList.remove('active');
}

function onBackdropClick(e) {
    if (e.target === document.getElementById('paramBackdrop')) closeParamModal();
}

function spinBpm(d)    { const el = document.getElementById('pmBpm');    el.value = Math.max(20,  Math.min(300, parseInt(el.value || 120) + d)); }
function spinSigNum(d) { const el = document.getElementById('pmSigNum'); el.value = Math.max(1,   Math.min(16,  parseInt(el.value || 4)  + d)); }
function clampBpm()    { const el = document.getElementById('pmBpm');    el.value = Math.max(20,  Math.min(300, parseInt(el.value) || 120)); }
function clampSig()    { const el = document.getElementById('pmSigNum'); el.value = Math.max(1,   Math.min(16,  parseInt(el.value) || 4)); }

function applyProjectSettings() {
    PROJECT.bpm         = parseInt(document.getElementById('pmBpm').value)    || 120;
    PROJECT.sigNum      = parseInt(document.getElementById('pmSigNum').value) || 4;
    PROJECT.sigDen      = parseInt(document.getElementById('pmSigDen').value) || 4;
    PROJECT.gridDiv     = parseInt(document.getElementById('pmGridDiv').value) || 16;
    PROJECT.gridVisible = document.getElementById('pmGridVisible').checked;
    PROJECT.snapEnabled = document.getElementById('pmSnapEnabled').checked;
    PROJECT.gridColor   = GRID_COLORS[selectedSwatchIdx] || '#f59e0b';
    PROJECT.gridOpacity = parseInt(document.getElementById('pmGridOpacity').value) || 15;

    closeParamModal();
    syncMenuBar();
    updateMasterLayout();
    Toast.show('Project settings applied', 'success');
}


// ─── File Save/Load Modal ───────────────────────────────────────────────────

function openSaveLoadModal() {
    document.getElementById('fileBackdrop').classList.add('open');
    document.getElementById('mbBtnSaveLoad').classList.add('active');
    fetchProjectsList();
}

function closeSaveLoadModal() {
    document.getElementById('fileBackdrop').classList.remove('open');
    document.getElementById('mbBtnSaveLoad').classList.remove('active');
}

function onFileBackdropClick(e) {
    if (e.target === document.getElementById('fileBackdrop')) closeSaveLoadModal();
}

function fetchProjectsList() {
    api('get_projects').then(res => {
        if (res.status === 'success') {
            const sel = document.getElementById('fileProjectSelect');
            const cur = sel.value;
            sel.innerHTML = '<option value="">-- Select Project --</option>' + 
                res.data.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
            if (cur && res.data.find(p => p.id == cur)) sel.value = cur;
            loadProjectFilesList();
        }
    });
}

function createNewProject() {
    const name = document.getElementById('newProjectName').value.trim();
    if (!name) return;
    api('create_project', { name }, 'POST').then(res => {
        if (res.status === 'success') {
            document.getElementById('newProjectName').value = '';
            fetchProjectsList();
            Toast.show('Project folder created', 'success');
        } else {
            Toast.show('Error creating project', 'error');
        }
    });
}

function loadProjectFilesList() {
    const pid = document.getElementById('fileProjectSelect').value;
    const list = document.getElementById('fileList');
    if (!pid) { list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">Select a project above</div>'; return; }
    
    list.innerHTML = '<div style="padding:10px;color:var(--text-dim);text-align:center;font-size:.72rem;">Loading files...</div>';
    api('get_project_files', { project_id: pid }).then(res => {
        if (res.status === 'success') {
            if (!res.data.length) {
                list.innerHTML = '<div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">No files saved yet</div>';
                return;
            }
            list.innerHTML = res.data.map(f => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 8px; border-bottom:1px solid var(--border);">
                    <span style="font-family:var(--font-sans); font-size:0.75rem;">${esc(f.filename)}</span>
                    <div style="display:flex; gap:4px;">
                        <button class="pm-btn pm-btn-cancel" style="height:24px; padding:0 8px; font-size:.65rem;" onclick="overwriteStateFile(${f.id}, '${esc(f.filename)}')">Save</button>
                        <button class="pm-btn pm-btn-apply" style="height:24px; padding:0 8px; font-size:.65rem;" onclick="loadStateFile(${f.id})">Load</button>
                    </div>
                </div>
            `).join('');
        }
    });
}

function saveCurrentProjectFile() {
    const pid = document.getElementById('fileProjectSelect').value;
    const fname = document.getElementById('newFileName').value.trim();
    if (!pid) { Toast.show('Select a project folder first', 'error'); return; }
    if (!fname) { Toast.show('Enter a filename', 'error'); return; }

    const state = serializeDawState();
    api('save_project_file', { project_id: pid, filename: fname, state_data: JSON.stringify(state) }, 'POST').then(res => {
        if (res.status === 'success') {
            document.getElementById('newFileName').value = '';
            loadProjectFilesList();
            Toast.show('Project state saved', 'success');
        } else {
            Toast.show('Error saving state', 'error');
        }
    });
}

function overwriteStateFile(fileId, filename) {
    if (!confirm('Overwrite existing file "' + filename + '"?')) return;
    const state = serializeDawState();
    api('update_project_file', { file_id: fileId, state_data: JSON.stringify(state) }, 'POST').then(res => {
        if (res.status === 'success') {
            loadProjectFilesList();
            Toast.show('File overwritten successfully', 'success');
        } else {
            Toast.show('Error saving state', 'error');
        }
    });
}

function loadStateFile(fileId) {
    api('load_project_file', { file_id: fileId }).then(res => {
        if (res.status === 'success' && res.data) {
            try {
                const state = JSON.parse(res.data.state_data);
                deserializeDawState(state);
                closeSaveLoadModal();
                Toast.show('Project loaded', 'success');
            } catch(e) {
                Toast.show('Error parsing project data', 'error');
            }
        } else {
            Toast.show('Failed to load file', 'error');
        }
    });
}


// ─── Master Channel Modal ───────────────────────────────────────────────────

// Master channel state
const MASTER = {
    vol: 1.0,
    fxChain: [],
    activePlugin: null,
    
    inputNode: null,
    outNode: null,
    meterIn: null,
    meterOut: null,
    nodes: {}
};

// Available built-in plugins
const MASTER_PLUGINS = [
    { id: 'gain',       label: 'Gain',       icon: 'bi-plus-slash-minus' },
    { id: 'volume',     label: 'Volume',     icon: 'bi-speaker' },
    { id: 'compressor', label: 'Compressor', icon: 'bi-activity' },
    { id: 'eq3band',    label: 'EQ 3-Band',  icon: 'bi-bar-chart-steps' },
    { id: 'limiter',    label: 'Limiter',    icon: 'bi-shield-shaded' },
];

// Per-plugin state
const PLUGIN_STATE = {
    gain:       { gainDb: 0 },
    volume:     { volumeDb: 0 },
    compressor: { threshold: -24, ratio: 4, attack: 10, release: 100, knee: 6 },
    eq3band:    { low: 0, mid: 0, high: 0 },
    limiter:    { ceiling: -0.3, release: 50 },
};

let _masterVuRaf = null;

function openMasterModal() {
    if (!MASTER.inputNode) initToneFoundation();
    document.getElementById('masterBackdrop').classList.add('open');
    document.getElementById('mbBtnMaster').classList.add('active');
    _syncMasterVolSlider();
    _renderFxChain();
    _renderPluginUi(MASTER.activePlugin);
    _startMasterVu();
}

function closeMasterModal() {
    document.getElementById('masterBackdrop').classList.remove('open');
    document.getElementById('mbBtnMaster').classList.remove('active');
    _stopMasterVu();
}

document.addEventListener('click', function(e) {
    const masterPicker = document.getElementById('mcPluginPicker');
    if (masterPicker && masterPicker.style.display === 'block') {
        if (!e.target.closest('#mcPluginPicker') && !e.target.closest('.mc-slot--add')) {
            masterPicker.style.display = 'none';
        }
    }
    const trackPicker = document.getElementById('trackPluginPicker');
    if (trackPicker && trackPicker.style.display === 'block') {
        if (!e.target.closest('#trackPluginPicker') && !e.target.closest('.mc-slot--add')) {
            trackPicker.style.display = 'none';
        }
    }
});

function onMasterBackdropClick(e) {
    if (e.target === document.getElementById('masterBackdrop')) closeMasterModal();
}

// ── Tone.js Foundation ──────────────────────────────────────────────────────
function _getToneNode(pid) {
    if (MASTER.nodes[pid]) return MASTER.nodes[pid];
    
    if (pid === 'gain') MASTER.nodes.gain = new Tone.Gain(Tone.dbToGain(PLUGIN_STATE.gain.gainDb));
    else if (pid === 'volume') MASTER.nodes.volume = new Tone.Volume(PLUGIN_STATE.volume.volumeDb);
    else if (pid === 'compressor') MASTER.nodes.compressor = new Tone.Compressor({
        threshold: PLUGIN_STATE.compressor.threshold,
        ratio: PLUGIN_STATE.compressor.ratio,
        attack: PLUGIN_STATE.compressor.attack / 1000,
        release: PLUGIN_STATE.compressor.release / 1000,
        knee: PLUGIN_STATE.compressor.knee
    });
    else if (pid === 'eq3band') MASTER.nodes.eq3band = new Tone.EQ3({
        low: PLUGIN_STATE.eq3band.low,
        mid: PLUGIN_STATE.eq3band.mid,
        high: PLUGIN_STATE.eq3band.high
    });
    else if (pid === 'limiter') MASTER.nodes.limiter = new Tone.Limiter(PLUGIN_STATE.limiter.ceiling);
    
    return MASTER.nodes[pid];
}

function initToneFoundation() {
    if (!window.Tone) return;
    
    MASTER.inputNode = new Tone.Gain(1);
    MASTER.outNode   = new Tone.Volume(0).toDestination();
    MASTER.outNode.volume.value = Tone.gainToDb(Math.max(0.0001, MASTER.vol));
    
    MASTER.meterIn  = new Tone.Meter({ normalRange: true });
    MASTER.meterOut = new Tone.Meter({ normalRange: true });
    
    MASTER.inputNode.connect(MASTER.meterIn);
    MASTER.outNode.connect(MASTER.meterOut);
    
    MASTER.nodes = {};
    
    _rebuildToneChain();
}

function _rebuildToneChain() {
    if (!MASTER.inputNode) return;
    
    MASTER.inputNode.disconnect();
    MASTER.inputNode.connect(MASTER.meterIn);
    
    Object.values(MASTER.nodes).forEach(n => n.disconnect());
    
    let current = MASTER.inputNode;
    
    MASTER.fxChain.forEach(pid => {
        const node = _getToneNode(pid);
        if (node) {
            current.connect(node);
            current = node;
        }
    });
    
    current.connect(MASTER.outNode);
}

function _syncMasterVolSlider() {
    const slider = document.getElementById('masterVolSlider');
    const label  = document.getElementById('masterVolLabel');
    if (slider) slider.value = MASTER.vol;
    if (label)  label.textContent = Math.round(MASTER.vol * 100) + '%';
}

function setMasterVol(val) {
    MASTER.vol = parseFloat(val);
    const label = document.getElementById('masterVolLabel');
    if (label) label.textContent = Math.round(MASTER.vol * 100) + '%';
    updateAllTrackVolumes();
}

let _vuInLevel = 0;
let _vuOutLevel = 0;

function _startMasterVu() {
    _stopMasterVu();
    _vuInLevel = 0;
    _vuOutLevel = 0;
    _masterVuRaf = requestAnimationFrame(_vuLoop);
}

function _stopMasterVu() {
    if (_masterVuRaf) { cancelAnimationFrame(_masterVuRaf); _masterVuRaf = null; }
    _drawVuBar('masterVuCanvasIn', 0);
    _drawVuBar('masterVuCanvasOut', 0);
}

function _vuLoop() {
    if (window.Tone && MASTER.meterIn && MASTER.meterOut) {
        let targetIn = MASTER.meterIn.getValue() * 1.5;
        let targetOut = MASTER.meterOut.getValue() * 1.5;
        
        _vuInLevel = targetIn > _vuInLevel ? Math.min(1, targetIn) : Math.max(0, _vuInLevel - 0.05);
        _vuOutLevel = targetOut > _vuOutLevel ? Math.min(1, targetOut) : Math.max(0, _vuOutLevel - 0.05);
        
        _drawVuBar('masterVuCanvasIn', _vuInLevel);
        _drawVuBar('masterVuCanvasOut', _vuOutLevel);
    }
    _masterVuRaf = requestAnimationFrame(_vuLoop);
}

function _drawVuBar(canvasId, level) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const W   = canvas.offsetWidth  || 40;
    const H   = canvas.offsetHeight || 280;

    if (canvas.width !== W * dpr || canvas.height !== H * dpr) {
        canvas.width  = W * dpr;
        canvas.height = H * dpr;
        canvas.style.width  = W + 'px';
        canvas.style.height = H + 'px';
    }

    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, W, H);

    const segH    = 4;
    const gap     = 2;
    const segUnit = segH + gap;
    const total   = Math.floor(H / segUnit);
    const lit     = Math.round(level * total);
    const padding = 6;
    const segW    = W - padding * 2;

    for (let i = 0; i < total; i++) {
        const y = H - (i + 1) * segUnit;
        const frac = i / total;
        let color;
        if      (frac > 0.85) color = '#ef4444';
        else if (frac > 0.65) color = '#f59e0b';
        else                  color = '#14b8a6';

        const alpha = i < lit ? 1.0 : 0.1;
        ctx.globalAlpha = alpha;
        ctx.fillStyle   = color;
        const rx = 2;
        ctx.beginPath();
        ctx.moveTo(padding + rx, y);
        ctx.lineTo(padding + segW - rx, y);
        ctx.quadraticCurveTo(padding + segW, y, padding + segW, y + rx);
        ctx.lineTo(padding + segW, y + segH - rx);
        ctx.quadraticCurveTo(padding + segW, y + segH, padding + segW - rx, y + segH);
        ctx.lineTo(padding + rx, y + segH);
        ctx.quadraticCurveTo(padding, y + segH, padding, y + segH - rx);
        ctx.lineTo(padding, y + rx);
        ctx.quadraticCurveTo(padding, y, padding + rx, y);
        ctx.closePath();
        ctx.fill();
    }

    ctx.globalAlpha = 1;
    ctx.scale(1 / dpr, 1 / dpr);
}

function _renderFxChain() {
    const chain = document.getElementById('masterFxChain');
    if (!chain) return;

    let html = MASTER.fxChain.map((pid, idx) => {
        const plug = MASTER_PLUGINS.find(p => p.id === pid);
        const lbl  = plug ? plug.label : pid;
        const ico  = plug ? plug.icon  : 'bi-plug';
        const active = pid === MASTER.activePlugin ? ' mc-slot--active' : '';
        return `<div class="mc-slot${active}" onclick="selectMasterPlugin('${pid}')">
            <i class="bi ${ico} mc-slot-icon"></i>
            <span class="mc-slot-lbl">${lbl}</span>
            <button class="mc-slot-del" onclick="removeMasterPlugin(${idx},event)" title="Remove"><i class="bi bi-x"></i></button>
        </div>`;
    }).join('');

    html += `<div class="mc-slot mc-slot--add" onclick="toggleAddPluginPicker(event)">
        <i class="bi bi-plus-lg mc-slot-icon" style="color:var(--amber);"></i>
        <span class="mc-slot-lbl" style="color:var(--text-dim);">Add Plugin</span>
    </div>`;

    chain.innerHTML = html;
    
    const picker = document.getElementById('mcPluginPicker');
    if (picker) {
        picker.innerHTML = MASTER_PLUGINS.map(p =>
            `<div class="mc-picker-item" onclick="addMasterPlugin('${p.id}')">
                <i class="bi ${p.icon}"></i> ${p.label}
            </div>`
        ).join('');
    }
}

function toggleAddPluginPicker(e) {
    if (e) { e.stopPropagation(); e.preventDefault(); }
    const picker = document.getElementById('mcPluginPicker');
    if (!picker) return;
    picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
}

function addMasterPlugin(pluginId) {
    if (!MASTER.inputNode) initToneFoundation();
    if (!MASTER.fxChain.includes(pluginId)) {
        MASTER.fxChain.push(pluginId);
        _rebuildToneChain();
    }
    MASTER.activePlugin = pluginId;
    _renderFxChain();
    _renderPluginUi(pluginId);
    
    const picker = document.getElementById('mcPluginPicker');
    if (picker) picker.style.display = 'none';
}

function removeMasterPlugin(idx, e) {
    e.stopPropagation();
    const removed = MASTER.fxChain.splice(idx, 1)[0];
    if (MASTER.activePlugin === removed) {
        MASTER.activePlugin = MASTER.fxChain[0] || null;
    }
    _rebuildToneChain();
    _renderFxChain();
    _renderPluginUi(MASTER.activePlugin);
}

function selectMasterPlugin(pluginId) {
    MASTER.activePlugin = pluginId;
    _renderFxChain();
    _renderPluginUi(pluginId);
}

function _renderPluginUi(pluginId) {
    const area = document.getElementById('masterPluginArea');
    if (!area) return;

    if (!pluginId || !MASTER.fxChain.includes(pluginId)) {
        area.innerHTML = `<div class="mc-plugin-empty">
            <i class="bi bi-plug" style="font-size:2rem;opacity:.2;"></i>
            <div style="margin-top:10px;font-size:.75rem;color:var(--text-faint);">Select or add a plugin from the FX chain below</div>
        </div>`;
        return;
    }

    switch (pluginId) {
        case 'gain':       area.innerHTML = _uiGain();       break;
        case 'volume':     area.innerHTML = _uiVolume();     break;
        case 'compressor': area.innerHTML = _uiCompressor(); break;
        case 'eq3band':    area.innerHTML = _uiEq3Band();    break;
        case 'limiter':    area.innerHTML = _uiLimiter();    break;
        default:
            area.innerHTML = `<div class="mc-plugin-empty"><div style="color:var(--text-dim);">No UI for "${pluginId}"</div></div>`;
    }
}

function _uiGain() {
    const s = PLUGIN_STATE.gain;
    return `<div class="mc-plugin-panel">
        <div class="mc-plugin-title"><i class="bi bi-plus-slash-minus"></i> Gain</div>
        ${_paramSlider('Gain (dB)', 'gainDb', s.gainDb, -24, 24, 0.5, ' dB', null)}
        <div class="mc-plugin-note">Adjusts the output gain of the master bus.</div>
    </div>`;
}

function _uiVolume() {
    const s = PLUGIN_STATE.volume;
    return `<div class="mc-plugin-panel">
        <div class="mc-plugin-title"><i class="bi bi-speaker"></i> Volume</div>
        ${_paramSlider('Volume (dB)', 'volDb', s.volumeDb, -60, 12, 0.5, ' dB', null)}
        <div class="mc-plugin-note">Basic volume adjustment node.</div>
    </div>`;
}

function _uiCompressor() {
    const s = PLUGIN_STATE.compressor;
    return `<div class="mc-plugin-panel">
        <div class="mc-plugin-title"><i class="bi bi-activity"></i> Compressor</div>
        ${_paramSlider('Threshold', 'compThreshold', s.threshold, -60, 0,  0.5, ' dB', null)}
        ${_paramSlider('Ratio',     'compRatio',     s.ratio,     1,   20, 0.5, ':1',  null)}
        ${_paramSlider('Attack',    'compAttack',    s.attack,    0,   200, 1,  ' ms', null)}
        ${_paramSlider('Release',   'compRelease',   s.release,   10,  2000,10, ' ms', null)}
        ${_paramSlider('Knee',      'compKnee',      s.knee,      0,   40,  1,  ' dB', null)}
        <div class="mc-plugin-note">Web Audio API compressor.</div>
    </div>`;
}

function _uiEq3Band() {
    const s = PLUGIN_STATE.eq3band;
    return `<div class="mc-plugin-panel">
        <div class="mc-plugin-title"><i class="bi bi-bar-chart-steps"></i> EQ 3-Band</div>
        ${_paramSlider('Low (80 Hz)',  'eqLow',  s.low,  -15, 15, 0.5, ' dB', null)}
        ${_paramSlider('Mid (1 kHz)', 'eqMid',  s.mid,  -15, 15, 0.5, ' dB', null)}
        ${_paramSlider('High (8 kHz)','eqHigh', s.high, -15, 15, 0.5, ' dB', null)}
        <div class="mc-plugin-note">Shelf filters at fixed frequency points.</div>
    </div>`;
}

function _uiLimiter() {
    const s = PLUGIN_STATE.limiter;
    return `<div class="mc-plugin-panel">
        <div class="mc-plugin-title"><i class="bi bi-shield-shaded"></i> Limiter</div>
        ${_paramSlider('Ceiling', 'limCeiling', s.ceiling, -12, 0,   0.1, ' dB', null)}
        ${_paramSlider('Release', 'limRelease', s.release, 10,  500, 5,   ' ms', null)}
        <div class="mc-plugin-note">Hard limiter ceiling.</div>
    </div>`;
}

function _paramSlider(label, id, val, min, max, step, unit, onInput) {
    return `<div class="mc-param-row">
        <label class="mc-param-lbl">${label}</label>
        <input type="range" class="mc-param-slider" id="slider_${id}"
            min="${min}" max="${max}" step="${step}" value="${val}"
            oninput="document.getElementById('val_${id}').textContent=parseFloat(this.value)+'${unit}'; _masterParamInput('${id}', this.value);">
        <span class="mc-param-val" id="val_${id}">${val}${unit}</span>
    </div>`;
}

function _masterParamInput(id, rawVal) {
    const v = parseFloat(rawVal);
    const map = {
        compThreshold: () => { PLUGIN_STATE.compressor.threshold = v; if (MASTER.nodes?.compressor) MASTER.nodes.compressor.threshold.value = v; },
        compRatio:     () => { PLUGIN_STATE.compressor.ratio     = v; if (MASTER.nodes?.compressor) MASTER.nodes.compressor.ratio.value = v; },
        compAttack:    () => { PLUGIN_STATE.compressor.attack    = v; if (MASTER.nodes?.compressor) MASTER.nodes.compressor.attack.value = v / 1000; },
        compRelease:   () => { PLUGIN_STATE.compressor.release   = v; if (MASTER.nodes?.compressor) MASTER.nodes.compressor.release.value = v / 1000; },
        compKnee:      () => { PLUGIN_STATE.compressor.knee      = v; if (MASTER.nodes?.compressor) MASTER.nodes.compressor.knee.value = v; },
        gainDb:        () => { PLUGIN_STATE.gain.gainDb          = v; if (MASTER.nodes?.gain) MASTER.nodes.gain.gain.value = Tone.dbToGain(v); },
        volDb:         () => { PLUGIN_STATE.volume.volumeDb      = v; if (MASTER.nodes?.volume) MASTER.nodes.volume.volume.value = v; },
        eqLow:         () => { PLUGIN_STATE.eq3band.low          = v; if (MASTER.nodes?.eq3band) MASTER.nodes.eq3band.low.value = v; },
        eqMid:         () => { PLUGIN_STATE.eq3band.mid          = v; if (MASTER.nodes?.eq3band) MASTER.nodes.eq3band.mid.value = v; },
        eqHigh:        () => { PLUGIN_STATE.eq3band.high         = v; if (MASTER.nodes?.eq3band) MASTER.nodes.eq3band.high.value = v; },
        limCeiling:    () => { PLUGIN_STATE.limiter.ceiling      = v; if (MASTER.nodes?.limiter) MASTER.nodes.limiter.threshold.value = v; },
        limRelease:    () => { PLUGIN_STATE.limiter.release      = v; },
    };
    if (map[id]) map[id]();
}


// ─── PyAPI Bounce Feature ───────────────────────────────────────────────────

let _bouncePollingInterval = null;

function _setBounceStatus(msg) {
    const el = document.getElementById('bounceStatusMsg');
    if (el) el.textContent = msg;
}

function _openBounceModal() {
    document.getElementById('bounceBackdrop').classList.add('open');
    const btn = document.getElementById('mbBtnBounce');
    if (btn) btn.classList.add('active');
}

function _closeBounceModal() {
    document.getElementById('bounceBackdrop').classList.remove('open');
    const btn = document.getElementById('mbBtnBounce');
    if (btn) btn.classList.remove('active');
}

function cancelBounce() {
    if (_bouncePollingInterval) {
        clearInterval(_bouncePollingInterval);
        _bouncePollingInterval = null;
    }
    _closeBounceModal();
    Toast.show('Bounce cancelled', 'info');
}

async function bounceProject() {
    if (!STATE.clips.length) { Toast.show('Project is empty', 'error'); return; }

    _openBounceModal();
    _setBounceStatus('Collecting audio assets…');

    const state = serializeDawState();

    // Deduplicate URLs
    const uniqueUrls = [...new Set(STATE.clips.map(c => c.url).filter(Boolean))];
    const urlToFilename = {};
    const formData = new FormData();

    for (let i = 0; i < uniqueUrls.length; i++) {
        const url = uniqueUrls[i];
        _setBounceStatus(`Fetching asset ${i + 1} of ${uniqueUrls.length}…`);
        try {
            const blob = await fetch(url).then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.blob();
            });
            const ext = url.split('.').pop().split('?')[0] || 'wav';
            const filename = `asset_${i}.${ext}`;
            urlToFilename[url] = filename;
            formData.append('files', blob, filename);
        } catch (e) {
            _closeBounceModal();
            Toast.show(`Failed to fetch: ${url}`, 'error');
            return;
        }
    }

    // Wire bounce_filename into clips
    state.clips.forEach(c => {
        if (c.url) c.bounce_filename = urlToFilename[c.url];
    });

    formData.append('state_json', JSON.stringify(state));

    _setBounceStatus('Uploading & queuing render…');

    try {
        const baseUrl = `${window.location.protocol}//${window.location.hostname}:8009/daw`;

        const res = await fetch(`${baseUrl}/bounce-async`, {
            method: 'POST',
            body: formData
        }).then(r => r.json());

        if (res.status === 'queued' || res.status === 'processing') {
            _setBounceStatus('Rendering on server…');
            _pollBounceStatus(baseUrl, res.task_id);
        } else {
            _closeBounceModal();
            Toast.show('Bounce failed: ' + (res.detail || res.status), 'error');
        }
    } catch (e) {
        _closeBounceModal();
        Toast.show('Could not reach PyAPI on port 8009', 'error');
        console.error(e);
    }
}

function _pollBounceStatus(baseUrl, taskId) {
    if (_bouncePollingInterval) clearInterval(_bouncePollingInterval);

    _bouncePollingInterval = setInterval(async () => {
        try {
            const res = await fetch(`${baseUrl}/status/${taskId}`).then(r => r.json());

            if (res.status === 'completed') {
                clearInterval(_bouncePollingInterval);
                _bouncePollingInterval = null;
                _setBounceStatus('Done! Saving to database…');
                
                // Determine entity linking for the mapping table
                let eType = '';
                let eId   = 0;
                
                if (window.DAW_INIT_SHOT_ID) {
                    eType = 'editorial_shots';
                    eId   = window.DAW_INIT_SHOT_ID;
                } else {
                    const projSel = document.getElementById('fileProjectSelect');
                    if (projSel && projSel.value) {
                        eType = 'daw_projects';
                        eId   = projSel.value;
                    }
                }

                // Call the PHP API register endpoint
                const body = new URLSearchParams({
                    api_action: 'register_bounce',
                    task_id: taskId,
                    entity_type: eType,
                    entity_id: eId,
                    name: 'DAW Mixdown ' + new Date().toLocaleTimeString()
                });

                fetch('?api_action=register_bounce', { method: 'POST', body })
                    .then(r => r.json())
                    .then(dbRes => {
                        _closeBounceModal();
                        if (dbRes.status === 'success') {
                            Toast.show(`Bounce saved to DB (Audio #${dbRes.audio_id})`, 'success');
                            // Optional: If you wanted to do something with the file locally
                            // window.open(dbRes.filename, '_blank');
                        } else {
                            Toast.show('DB Error: ' + (dbRes.message || 'unknown'), 'error');
                        }
                    }).catch(e => {
                        _closeBounceModal();
                        Toast.show('Network error while saving to DB', 'error');
                    });

            } else if (res.status === 'failed') {
                clearInterval(_bouncePollingInterval);
                _bouncePollingInterval = null;
                _closeBounceModal();
                Toast.show('Bounce failed: ' + (res.error || 'unknown error'), 'error');
            }
            // 'processing' → keep polling silently
        } catch (e) {
            // transient network error — keep polling
        }
    }, 1500);
}