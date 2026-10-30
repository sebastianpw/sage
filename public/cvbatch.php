<?php
// ============================================================================
// File: public/cvbatch.php
// ============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Batch CV Module";

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* Forge Design System - SketchUp Cloned Style */
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-amber:       #f5a623;
    --pl-red:         #f05060;
    --pl-green:       #4caf80;
    --pl-teal:        #3ab5c8;
    --pl-purple:      #a78bfa;

    --bg:             var(--pl-bg);
    --surface:        var(--pl-surface);
    --card:           var(--pl-card);
    --border:         var(--pl-border);
    --text:           var(--pl-text);
    --text-dim:       var(--pl-text-dim);
    --amber:          var(--pl-amber);
    --purple:         var(--pl-purple);
    --teal:           var(--pl-teal);
    --green:          var(--pl-green);
    --red:            var(--pl-red);
    --radius:         6px;
    --radius-lg:      10px;
    --mono:           'Space Mono', 'Fira Mono', monospace;
    --sans:           'Syne', system-ui, sans-serif;
    --border-glow:    #2a3a52;
    --text-bright:    #e8f0ff;
    --purple-dim:     rgba(167,139,250,0.1);
    --green-dim:      rgba(74,222,128,0.1);
}
[data-theme="light"] {
    --pl-bg:          #f4f6fa;
    --pl-surface:     #ffffff;
    --pl-card:        #ffffff;
    --pl-border:      #d0d8e8;
    --pl-text:        #1a2233;
    --pl-text-dim:    #7a8aaa;
    --pl-amber:       #c8880a;
    --pl-red:         #d03040;
    --pl-green:       #2e8a58;
    --pl-teal:        #1a8090;
    --pl-purple:      #7c3aed;

    --border-glow:    #9ca3af;
    --text-bright:    #000;
    --purple-dim:     rgba(124,58,237,0.1);
    --green-dim:      rgba(5,150,105,0.1);
}

body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', system-ui, sans-serif; margin: 0; padding: 0; }

/* Navigation */
.pl-nav { display:flex; align-items:center; gap:10px; padding:10px 16px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); flex-wrap:wrap; }
[data-theme="light"] .pl-nav { background:rgba(244,246,250,.92); }
.pl-nav-title { font-family:'Space Mono',monospace; font-size:.8rem; color:var(--pl-text); flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* Workspace & Cards */
.workspace { max-width:900px; margin:0 auto; padding:30px 15px 100px; }
.scene-block { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:20px; margin-bottom:24px; box-shadow:0 4px 20px rgba(0,0,0,.3); }
[data-theme="light"] .scene-block { box-shadow:0 2px 10px rgba(0,0,0,.08); }
.scene-header { margin-bottom:15px; border-bottom:1px solid var(--pl-border); padding-bottom:14px; }
.scene-title { font-family:'Space Mono',monospace; font-size:.85rem; color:var(--pl-teal); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:8px; }

/* Form inputs & Autocomplete */
.su-input { width:100%; background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; box-sizing:border-box; transition:border-color .2s; }
.su-input:focus { outline:none; border-color:var(--pl-amber); }
.su-ac-wrap { position:relative; margin-bottom: 12px; }
.su-ac-dropdown { position:absolute; top:100%; left:0; right:0; z-index:10; background:var(--pl-card); border:1px solid var(--pl-border); border-top:none; border-radius:0 0 4px 4px; max-height:200px; overflow-y:auto; display:none; box-shadow:0 4px 12px rgba(0,0,0,.5); }
.su-ac-item { padding:8px 12px; font-size:.8rem; cursor:pointer; transition:background .1s; border-bottom:1px solid var(--pl-border); }
.su-ac-item:hover { background:rgba(245,166,35,.1); color:var(--pl-amber); }
.su-ac-item:last-child { border-bottom:none; }

/* Chips */
.beat-entity-chips { display:flex; flex-wrap:wrap; gap:6px; }
.beat-entity-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:12px; font-size:.75rem; font-family:'Space Mono',monospace; border:1px solid; }
.chip-sket { background:rgba(58,181,200,.1); border-color:var(--pl-teal); color:var(--pl-teal); }
.chip-range { background:rgba(245,166,35,.1); border-color:var(--pl-amber); color:var(--pl-amber); }
.chip-maprun { background:rgba(76,175,128,.1); border-color:var(--pl-green); color:var(--pl-green); }
.chip-remove { background:none; border:none; cursor:pointer; color:inherit; opacity:.6; font-size:.8rem; padding:0; line-height:1; transition:opacity .15s; }
.chip-remove:hover { opacity:1; }

/* Labels & Checks */
.su-check-label { display:flex; align-items:center; gap:8px; font-size:.85rem; cursor:pointer; margin-bottom:10px; user-select:none; color:var(--pl-text); transition: color 0.15s; }
.su-check-label:hover { color:var(--pl-amber); }
.su-check-label input[type="checkbox"] { accent-color:var(--pl-teal); width:16px; height:16px; cursor:pointer; }
.su-check-label.purple input[type="checkbox"] { accent-color:var(--pl-purple); }
.su-check-label.purple:hover { color:var(--pl-purple); }

.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; }
.pl-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-surface); color:var(--pl-text-dim); }
.pl-btn-secondary:not(:disabled):hover { border-color:var(--pl-amber); color:var(--pl-amber); }
.pl-btn.primary { color:#000; background:var(--pl-teal); border-color:var(--pl-teal); font-weight:bold; }
.pl-btn.primary:not(:disabled):hover { filter:brightness(1.1); color:#000; }

.row-flex { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }

/* ── Mode Tabs ────────────────────────────────────────────────────────────── */
.su-mode-tabs { display:flex; gap:0; border:1px solid var(--pl-border); border-radius:6px; overflow:hidden; margin-bottom:24px; }
.su-mode-tab { flex:1; padding:10px 8px; background:transparent; border:none; color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.7rem; cursor:pointer; transition:all .2s; text-align:center; border-right:1px solid var(--pl-border); display:flex; flex-direction:column; align-items:center; gap:4px; line-height:1.2; }
.su-mode-tab:last-child { border-right:none; }
.su-mode-tab i { font-size:1rem; }
.su-mode-tab:hover { background:rgba(245,166,35,.06); color:var(--pl-text); }
.su-mode-tab.active { background:rgba(58,181,200,.12); color:var(--pl-teal); border-bottom:2px solid var(--pl-teal); }
.su-mode-tab.active-plush { background:rgba(167,139,250,.12); color:var(--pl-purple); border-bottom:2px solid var(--pl-purple); }

/* Mode panels */
.su-mode-panel { display:none; }
.su-mode-panel.active { display:block; }

/* Resolved sketches summary banner */
.su-resolved-banner { padding:10px 14px; background:rgba(58,181,200,.07); border:1px solid rgba(58,181,200,.25); border-radius:5px; font-family:'Space Mono',monospace; font-size:.72rem; color:var(--pl-teal); margin-bottom:14px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.su-resolved-banner.plush { background:rgba(167,139,250,.07); border-color:rgba(167,139,250,.25); color:var(--pl-purple); }
.su-resolved-banner .su-banner-clear { margin-left:auto; background:none; border:1px solid currentColor; border-radius:3px; color:inherit; font-family:'Space Mono',monospace; font-size:.65rem; cursor:pointer; padding:2px 7px; opacity:.7; transition:opacity .15s; }
.su-resolved-banner .su-banner-clear:hover { opacity:1; }

/* Modals */
.su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.su-modal-backdrop.active { display:flex; }
.su-modal-box { width:100%; max-width:700px; height:85vh; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
.su-modal-header { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid var(--pl-border); background:var(--pl-surface); flex-shrink:0; }
.su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-amber); text-transform:uppercase; letter-spacing:1px; }
.su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
#entity-iframe { flex:1; border:none; width:100%; background:var(--pl-card); }

/* Vision Clipboard Horizontal Scroll */
.vision-clipboard-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 6px;
    margin-top: 10px;
}
.vision-clipboard-chips {
    flex: 1;
    display: flex;
    gap: 5px;
    overflow-x: auto;
    scrollbar-width: none;
    min-width: 0;
    align-items: center;
}
.vision-clipboard-chips::-webkit-scrollbar { display: none; }
.vision-chip {
    flex-shrink: 0;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid var(--pl-border);
    background: rgba(255,255,255,0.04);
    color: var(--pl-text-dim);
    font-family: var(--mono);
    font-size: 0.72rem;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.vision-chip:hover {
    border-color: var(--pl-teal);
    color: var(--pl-teal);
    background: rgba(58,181,200,0.08);
}
.vision-chip.pinned {
    border-color: rgba(245, 166, 35, 0.4);
    color: var(--pl-amber);
}

/* Session Table Grid */
.session-table-wrap { max-height: 300px; overflow-y: auto; border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-surface); }
.session-table { width:100%; border-collapse: collapse; font-family:var(--mono); font-size:0.75rem; text-align:left; }
.session-table th { padding: 6px 10px; border-bottom: 1px solid var(--pl-border); background: var(--pl-card); position: sticky; top: 0; z-index: 10; color: var(--pl-text); font-weight: bold; }
.session-table td { padding: 6px 10px; border-bottom: 1px solid var(--pl-border); vertical-align: middle; }
.session-table tr:last-child td { border-bottom: none; }
.status-pending { color: var(--pl-text-dim); }
.status-running { color: var(--pl-teal); font-weight: bold; }
.status-success { color: var(--pl-green); }
.status-error { color: var(--pl-red); }
.action-btn { background: none; border: 1px solid var(--pl-border); color: var(--pl-text-dim); border-radius: 4px; padding: 2px 6px; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { color: var(--pl-amber); border-color: var(--pl-amber); }
</style>

<script>
window.CVBatchApp = (function() {
    // Basic Configuration State
    let selectedSketches = [];
    let sketchRanges = [];
    let frameRanges = [];
    let selectedMapRuns = [];
    let searchTimeout = null;
    let mapRunSearchTimeout = null;

    let currentMode = 'standard';
    let resolvedSketchIds = [];
    let resolvedPlushEntities = [];

    // Session / Batch State
    let currentSessionId = null;
    let sessionState = {}; // map: frameId -> { status: 'pending'|'success'|'error'|'running', message: '', sketches: [] }
    let frameQueue = [];
    let isRunning = false;
    let isHalted = false;

    function setMode(mode) {
        currentMode = mode;
        ['standard','sequence','plush'].forEach(m => {
            const tab = document.getElementById('su-tab-' + m);
            const panel = document.getElementById('su-panel-' + m);
            if (!tab || !panel) return;
            const isActive = m === mode;
            tab.className = 'su-mode-tab' + (isActive ? (m === 'plush' ? ' active-plush' : ' active') : '');
            panel.className = 'su-mode-panel' + (isActive ? ' active' : '');
        });
    }

    function searchSketches(q) {
        clearTimeout(searchTimeout);
        if (!q.trim()) { document.getElementById('sketchAc').style.display = 'none'; return; }
        searchTimeout = setTimeout(() => {
            fetch('cvbatch_api.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'search_sketches', q})
            }).then(r=>r.json()).then(res => {
                if (res.ok) renderAutocomplete(res.results);
            });
        }, 250);
    }

    function renderAutocomplete(results) {
        const ac = document.getElementById('sketchAc');
        if (!results.length) { ac.style.display = 'none'; return; }
        ac.innerHTML = results.map(r => `
            <div class="su-ac-item" onclick="CVBatchApp.addSketch(${r.id}, '${r.name.replace(/'/g, "\\'")}')">
                <span style="color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:0.7rem; margin-right:6px;">#${r.id}</span>
                ${r.name}
            </div>
        `).join('');
        ac.style.display = 'block';
    }

    function addSketch(id, name) {
        if (!selectedSketches.find(s => s.id === id)) {
            selectedSketches.push({id, name});
            renderSketchChips();
        }
        const inp = document.getElementById('sketchSearch');
        if(inp) inp.value = '';
        document.getElementById('sketchAc').style.display = 'none';
    }

    function removeSketch(id) {
        selectedSketches = selectedSketches.filter(s => s.id !== id);
        renderSketchChips();
    }

    function renderSketchChips() {
        document.getElementById('sketchChips').innerHTML = selectedSketches.map(s => `
            <span class="beat-entity-chip chip-sket">
                <span style="cursor:pointer;" onclick="CVBatchApp.openEntityModal('sketches', ${s.id}, '${s.name.replace(/'/g, "\\'")}')">#${s.id} ${s.name}</span>
                <button class="chip-remove" onclick="CVBatchApp.removeSketch(${s.id})"><i class="bi bi-x"></i></button>
            </span>
        `).join('');
    }

    function searchMapRuns(q) {
        clearTimeout(mapRunSearchTimeout);
        const ac = document.getElementById('mapRunAc');
        mapRunSearchTimeout = setTimeout(() => {
            fetch('cvbatch_api.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'search_map_runs', q})
            }).then(r=>r.json()).then(res => {
                if (res.ok) renderMapRunAutocomplete(res.results, ac);
            });
        }, 250);
    }

    function renderMapRunAutocomplete(results, ac) {
        if (!results.length) { ac.style.display = 'none'; return; }
        ac.innerHTML = results.map(r => {
            const label = r.note ? r.note : (r.created_at || 'No note');
            return `
            <div class="su-ac-item" onclick="CVBatchApp.addMapRun(${r.id}, '${label.replace(/'/g, "\\'")}')">
                <span style="color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:0.7rem; margin-right:6px;">#${r.id}</span>
                ${label}
            </div>
        `}).join('');
        ac.style.display = 'block';
    }

    function addMapRun(id, note) {
        if (!selectedMapRuns.find(m => m.id === id)) {
            selectedMapRuns.push({id, note});
            renderMapRunChips();
        }
        const inp = document.getElementById('mapRunSearch');
        if(inp) inp.value = '';
        document.getElementById('mapRunAc').style.display = 'none';
    }

    function removeMapRun(id) {
        selectedMapRuns = selectedMapRuns.filter(m => m.id !== id);
        renderMapRunChips();
    }

    function renderMapRunChips() {
        document.getElementById('mapRunChips').innerHTML = selectedMapRuns.map(m => `
            <span class="beat-entity-chip chip-maprun">
                <span>Run: #${m.id} ${m.note}</span>
                <button class="chip-remove" onclick="CVBatchApp.removeMapRun(${m.id})"><i class="bi bi-x"></i></button>
            </span>
        `).join('');
    }

    function addRange() {
        const start = parseInt(document.getElementById('rangeStart').value);
        const end = parseInt(document.getElementById('rangeEnd').value);
        if (!start || !end || start > end) {
            if(window.Toast) Toast.show('Invalid range', 'warn');
            else alert('Invalid range');
            return;
        }
        sketchRanges.push({start, end});
        renderRanges();
        document.getElementById('rangeStart').value = '';
        document.getElementById('rangeEnd').value = '';
    }

    function removeRange(idx) {
        sketchRanges.splice(idx, 1);
        renderRanges();
    }

    function renderRanges() {
        document.getElementById('rangesList').innerHTML = sketchRanges.map((r, i) => `
            <span class="beat-entity-chip chip-range">
                <span>Range: ${r.start} - ${r.end}</span>
                <button class="chip-remove" onclick="CVBatchApp.removeRange(${i})"><i class="bi bi-x"></i></button>
            </span>
        `).join('');
    }

    function addFrameRange() {
        const start = parseInt(document.getElementById('frameRangeStart').value);
        const end = parseInt(document.getElementById('frameRangeEnd').value);
        if (!start || !end || start > end) {
            if(window.Toast) Toast.show('Invalid frame range', 'warn');
            else alert('Invalid frame range');
            return;
        }
        frameRanges.push({start, end});
        renderFrameRanges();
        document.getElementById('frameRangeStart').value = '';
        document.getElementById('frameRangeEnd').value = '';
    }

    function removeFrameRange(idx) {
        frameRanges.splice(idx, 1);
        renderFrameRanges();
    }

    function renderFrameRanges() {
        document.getElementById('frameRangesList').innerHTML = frameRanges.map((r, i) => `
            <span class="beat-entity-chip chip-range" style="background:rgba(124,58,237,0.1); border-color:var(--pl-purple); color:var(--pl-purple);">
                <span>Frames: ${r.start} - ${r.end}</span>
                <button class="chip-remove" onclick="CVBatchApp.removeFrameRange(${i})"><i class="bi bi-x"></i></button>
            </span>
        `).join('');
    }

    // ── Narrative Sequence Mode ──
    let seqSearchTimeout = null;
    let selectedSequence = null;

    function searchSequences(q) {
        clearTimeout(seqSearchTimeout);
        const ac = document.getElementById('seqAc');
        if (!q.trim()) { ac.style.display = 'none'; return; }
        seqSearchTimeout = setTimeout(() => {
            fetch('cvbatch_api.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'search_narrative_sequences', q})
            }).then(r=>r.json()).then(res => {
                if (!res.ok || !res.results.length) { ac.style.display = 'none'; return; }
                ac.innerHTML = res.results.map(r => `
                    <div class="su-ac-item" onclick="CVBatchApp.selectSequence(${r.id}, '${r.name.replace(/'/g, "\\'")}')">
                        <span style="color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:0.7rem; margin-right:6px;">#${r.id}</span>
                        ${r.name}
                    </div>
                `).join('');
                ac.style.display = 'block';
            });
        }, 250);
    }

    function selectSequence(id, name) {
        selectedSequence = {id, name};
        const inp = document.getElementById('seqSearch');
        if (inp) inp.value = name;
        document.getElementById('seqAc').style.display = 'none';
        resolveSequenceSketches(id, name);
    }

    function resolveSequenceSketches(seqId, seqName) {
        const banner = document.getElementById('seqResolvedBanner');
        if (banner) { banner.style.display = 'flex'; banner.innerHTML = '<i class="bi bi-hourglass-split"></i> Resolving sketches from sequence…'; }
        resolvedSketchIds = [];
        fetch('cvbatch_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'resolve_sequence_sketches', sequence_id: seqId})
        }).then(r=>r.json()).then(res => {
            if (!res.ok) { Toast.show(res.error || 'Failed to resolve sequence.', 'error'); if(banner) banner.style.display='none'; return; }
            resolvedSketchIds = res.sketches || [];
            if (banner) {
                banner.style.display = 'flex';
                banner.innerHTML = `<i class="bi bi-check2-circle"></i> <strong>${resolvedSketchIds.length}</strong> sketch(es) resolved from <em>${seqName}</em> <button class="su-banner-clear" onclick="CVBatchApp.clearSequenceResolution()">Clear</button>`;
            }
        });
    }

    function clearSequenceResolution() {
        resolvedSketchIds = [];
        selectedSequence = null;
        const inp = document.getElementById('seqSearch');
        if (inp) inp.value = '';
        const banner = document.getElementById('seqResolvedBanner');
        if (banner) banner.style.display = 'none';
    }

    // ── PLUSH Mode ──
    let plushSearchTimeout = null;
    let selectedPlush = null;
    let selectedPlushType = 'story'; 

    function setPlushType(type) {
        selectedPlushType = type;
        clearPlushResolution();
        ['story','collection'].forEach(t => {
            const btn = document.getElementById('plushTypeBtn-' + t);
            if (btn) btn.className = 'pl-btn pl-btn-secondary' + (t === type ? ' active-plush-type' : '');
        });
        const inp = document.getElementById('plushSearch');
        if (inp) { inp.value = ''; inp.placeholder = type === 'story' ? 'Search PLUSH story…' : 'Search PLUSH collection…'; }
    }

    function searchPlush(q) {
        clearTimeout(plushSearchTimeout);
        const ac = document.getElementById('plushAc');
        if (!q.trim()) { ac.style.display = 'none'; return; }
        plushSearchTimeout = setTimeout(() => {
            fetch('cvbatch_api.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'search_plush', q, plush_type: selectedPlushType})
            }).then(r=>r.json()).then(res => {
                if (!res.ok || !res.results.length) { ac.style.display = 'none'; return; }
                ac.innerHTML = res.results.map(r => `
                    <div class="su-ac-item" onclick="CVBatchApp.selectPlush(${r.id}, '${r.name.replace(/'/g, "\\'")}')">
                        <span style="color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:0.7rem; margin-right:6px;">#${r.id}</span>
                        ${r.name}
                    </div>
                `).join('');
                ac.style.display = 'block';
            });
        }, 250);
    }

    function selectPlush(id, name) {
        selectedPlush = {id, name};
        const inp = document.getElementById('plushSearch');
        if (inp) inp.value = name;
        document.getElementById('plushAc').style.display = 'none';
        resolvePlushEntities(id, name);
    }

    function resolvePlushEntities(plushId, plushName) {
        const banner = document.getElementById('plushResolvedBanner');
        if (banner) { banner.style.display = 'flex'; banner.innerHTML = '<i class="bi bi-hourglass-split"></i> Resolving PLUSH linked entities…'; }
        resolvedSketchIds = [];
        resolvedPlushEntities = [];
        fetch('cvbatch_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'resolve_plush_entities', plush_id: plushId, plush_type: selectedPlushType})
        }).then(r=>r.json()).then(res => {
            if (!res.ok) { Toast.show(res.error || 'Failed to resolve PLUSH entities.', 'error'); if(banner) banner.style.display='none'; return; }
            resolvedSketchIds    = res.sketches || [];
            resolvedPlushEntities = res.entities || [];
            if (banner) {
                const parts = [];
                if (resolvedSketchIds.length) parts.push(`<strong>${resolvedSketchIds.length}</strong> sketch(es)`);
                banner.style.display = 'flex';
                banner.innerHTML = `<i class="bi bi-check2-circle"></i> ${parts.join(', ')} resolved from <em>${plushName}</em> <button class="su-banner-clear" onclick="CVBatchApp.clearPlushResolution()">Clear</button>`;
            }
        });
    }

    function clearPlushResolution() {
        resolvedSketchIds = [];
        resolvedPlushEntities = [];
        selectedPlush = null;
        const inp = document.getElementById('plushSearch');
        if (inp) inp.value = '';
        const banner = document.getElementById('plushResolvedBanner');
        if (banner) banner.style.display = 'none';
    }

    // ── Session Management ──
    function fetchSessionsList() {
        fetch('cvbatch_api.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'list_sessions'})
        })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                const sel = document.getElementById('sessionSelect');
                sel.innerHTML = '<option value="">-- New Session --</option>';
                res.sessions.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `#${s.id} - ${s.name}`;
                    sel.appendChild(opt);
                });
                if (currentSessionId) sel.value = currentSessionId;
            }
        });
    }

    function buildConfigPayload() {
        let allSketchIds = [];
        if (currentMode === 'standard') {
            allSketchIds = selectedSketches.map(s => s.id);
        } else {
            allSketchIds = resolvedSketchIds.map(s => s.id !== undefined ? s.id : s);
        }
        return {
            sketch_ids: allSketchIds,
            sketch_ranges: sketchRanges,
            frame_ranges: frameRanges,
            map_run_ids: selectedMapRuns.map(m => m.id),
            export_mode: currentMode,
            
            // Raw UI selections to restore later
            selected_sketches: selectedSketches,
            selected_map_runs: selectedMapRuns,
            selected_sequence: selectedSequence,
            selected_plush: selectedPlush,
            plush_type: selectedPlushType,
            model: document.getElementById('visionModelSelect').value,
            prompt: document.getElementById('visionPromptInput').value
        };
    }

    function saveSession() {
        const name = document.getElementById('sessionName').value.trim() || 'Untitled Session';
        const config = buildConfigPayload();
        
        fetch('cvbatch_api.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_session',
                id: currentSessionId,
                name: name,
                config: config,
                state: sessionState
            })
        })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                currentSessionId = res.id;
                Toast.show('Session saved!', 'success');
                fetchSessionsList();
            } else {
                Toast.show('Error saving session: ' + (res.error||''), 'error');
            }
        });
    }

    function loadSession() {
        const id = document.getElementById('sessionSelect').value;
        if (!id) {
            // Reset to new session
            currentSessionId = null;
            document.getElementById('sessionName').value = '';
            sessionState = {};
            renderSessionItems();
            return;
        }

        fetch('cvbatch_api.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'load_session', id: id})
        })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                currentSessionId = res.session.id;
                document.getElementById('sessionName').value = res.session.name;
                
                const conf = res.session.config || {};
                sessionState = res.session.state || {};
                
                // Restore UI Selections
                selectedSketches = conf.selected_sketches || [];
                sketchRanges = conf.sketch_ranges || [];
                frameRanges = conf.frame_ranges || [];
                selectedMapRuns = conf.selected_map_runs || [];
                selectedSequence = conf.selected_sequence || null;
                selectedPlush = conf.selected_plush || null;
                
                if (conf.model) document.getElementById('visionModelSelect').value = conf.model;
                if (conf.prompt) document.getElementById('visionPromptInput').value = conf.prompt;

                renderSketchChips();
                renderRanges();
                renderFrameRanges();
                renderMapRunChips();
                
                setMode(conf.export_mode || 'standard');

                if (conf.export_mode === 'sequence' && selectedSequence) {
                    selectSequence(selectedSequence.id, selectedSequence.name);
                }
                if (conf.export_mode === 'plush' && selectedPlush) {
                    setPlushType(conf.plush_type || 'story');
                    selectPlush(selectedPlush.id, selectedPlush.name);
                }

                renderSessionItems();
                Toast.show('Session loaded', 'success');
            }
        });
    }

    function updateSessionStateBackground() {
        if (!currentSessionId) return;
        fetch('cvbatch_api.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'update_session_state',
                id: currentSessionId,
                state: sessionState
            })
        }); // Fire and forget
    }

    // ── Grid & Status Management ──
    function renderSessionItems() {
        const tbody = document.getElementById('sessionItemsBody');
        tbody.innerHTML = '';
        
        const frameIds = Object.keys(sessionState).sort((a,b) => parseInt(a) - parseInt(b));
        
        if(frameIds.length > 0) {
            document.getElementById('sessionItemsContainer').style.display = 'block';
        } else {
            document.getElementById('sessionItemsContainer').style.display = 'none';
        }

        frameIds.forEach(fId => {
            const item = sessionState[fId];
            let statusClass = 'status-' + item.status;
            let statusIcon = 'bi-circle';
            if(item.status === 'success') statusIcon = 'bi-check-circle-fill';
            if(item.status === 'error') statusIcon = 'bi-x-circle-fill';
            if(item.status === 'running') statusIcon = 'bi-arrow-repeat';
            
            let actionBtn = `<button class="action-btn" onclick="CVBatchApp.flagForRerun(${fId})" title="Flag for rerun"><i class="bi bi-arrow-counterclockwise"></i></button>`;

            let shortMsg = item.message || '';
            if (shortMsg.length > 65) shortMsg = shortMsg.substring(0, 62) + '...';

            // Clickable frame ID
            let frameLink = `<a href="view_frame.php?frame_id=${fId}" target="_blank" style="color:var(--pl-teal); text-decoration:none;">#${fId}</a>`;

            tbody.innerHTML += `
                <tr>
                    <td>${frameLink}</td>
                    <td class="${statusClass}"><i class="bi ${statusIcon}"></i> ${item.status}</td>
                    <td title="${item.message ? item.message.replace(/"/g, '&quot;') : ''}">${shortMsg}</td>
                    <td>${actionBtn}</td>
                </tr>
            `;
        });
    }

    function flagForRerun(frameId) {
        if (sessionState[frameId]) {
            sessionState[frameId].status = 'pending';
            sessionState[frameId].message = 'Flagged for rerun';
            renderSessionItems();
            updateSessionStateBackground();
        }
    }

    function retryFailed() {
        let changed = false;
        Object.keys(sessionState).forEach(fId => {
            if (sessionState[fId].status === 'error') {
                sessionState[fId].status = 'pending';
                sessionState[fId].message = 'Queued for retry';
                changed = true;
            }
        });
        if (changed) {
            renderSessionItems();
            updateSessionStateBackground();
            Toast.show('Failed items queued for retry', 'success');
        }
    }

    // ── Batch Execution ──
    function startBatch() {
        const config = buildConfigPayload();
        
        if (!config.sketch_ids.length && !config.sketch_ranges.length && !config.map_run_ids.length && !config.frame_ranges.length) {
            if(window.Toast) Toast.show('Please select at least one source configuration.', 'warn');
            else alert('Please select at least one source configuration.');
            return;
        }
        
        document.getElementById('btnStartBatch').disabled = true;
        document.getElementById('btnStopBatch').disabled = false;
        document.getElementById('btnResumeBatch').disabled = true;
        
        logMonitor('Resolving frames...', 'info');
        
        fetch('cvbatch_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'resolve_frames', config })
        })
        .then(r => r.json())
        .then(res => {
            if (!res.ok) throw new Error(res.error || 'Failed to resolve frames');
            
            // Merge resolved frames into sessionState
            const newFrameIds = res.frame_ids || [];
            newFrameIds.forEach(fid => {
                if (!sessionState[fid]) {
                    sessionState[fid] = { status: 'pending', message: 'Queued', sketches: [] };
                }
            });
            
            renderSessionItems();
            
            // Auto-save session if name provided or just to ensure we have an ID
            const name = document.getElementById('sessionName').value.trim();
            if (name || !currentSessionId) {
                saveSession();
            }

            frameQueue = Object.keys(sessionState).filter(id => sessionState[id].status === 'pending');
            
            logMonitor(`Found ${frameQueue.length} pending frame(s) to process.`, 'success');
            
            isRunning = true;
            isHalted = false;
            
            if (frameQueue.length > 0) {
                processNextFrame();
            } else {
                logMonitor('No pending frames found to process.', 'warn');
                finishBatch();
            }
        })
        .catch(e => {
            logMonitor(`Error: ${e.message}`, 'error');
            finishBatch();
        });
    }

    function processNextFrame() {
        if (isHalted) {
            logMonitor('Batch halted. You can resume later.', 'warn');
            isRunning = false;
            document.getElementById('btnStopBatch').disabled = true;
            document.getElementById('btnResumeBatch').disabled = false;
            return;
        }

        // Re-evaluate queue in case user modified it (flagged rerun)
        frameQueue = Object.keys(sessionState).filter(id => sessionState[id].status === 'pending');
        
        if (frameQueue.length === 0) {
            logMonitor('Batch completed successfully.', 'success');
            finishBatch();
            return;
        }
        
        const frameId = frameQueue[0]; // Take the first pending
        const prompt = document.getElementById('visionPromptInput').value;
        const model = document.getElementById('visionModelSelect').value;
        
        sessionState[frameId].status = 'running';
        sessionState[frameId].message = 'Processing...';
        renderSessionItems();
        
        logMonitor(`Processing Frame #${frameId}...`, 'info');
        
        fetch('cvbatch_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'analyze_frame',
                frame_id: frameId,
                prompt: prompt,
                model: model
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data && res.data.description !== undefined) {
                let raw = res.data.description;
                if (typeof raw === 'object') {
                    raw = JSON.stringify(raw, null, 2);
                }
                
                // Fix JS side regex to avoid wiping contents inside codeblocks
                raw = raw.replace(/```(json|text|markdown)?/gi, "").replace(/```/g, "").replace(/\*\*/g, "").replace(/^(Here is|Sure|I can|Certainly).*?:/i, "").replace(/\s+/g, ' ').trim();
                
                if (raw.length === 0) {
                    sessionState[frameId].status = 'error';
                    sessionState[frameId].message = "API returned an empty description.";
                    logMonitor(`Frame #${frameId} failed: API returned an empty description.`, 'error');
                } else {
                    sessionState[frameId].status = 'success';
                    sessionState[frameId].message = raw;
                    sessionState[frameId].sketches = res.data.saved_to_sketches || [];
                    
                    let savedInfo = (res.data.saved_to_sketches && res.data.saved_to_sketches.length > 0) 
                        ? ` (Saved to Sketch #${res.data.saved_to_sketches.join(', #')})` 
                        : '';

                    logMonitor(`Frame #${frameId} Analysis${savedInfo}: ${raw.substring(0,100)}...`, 'result');
                }
            } else {
                let msg = res.message || res.error || "Missing description in API response";
                sessionState[frameId].status = 'error';
                sessionState[frameId].message = msg;
                logMonitor(`Frame #${frameId} failed: ${msg}`, 'error');
            }
        })
        .catch(e => {
            sessionState[frameId].status = 'error';
            sessionState[frameId].message = e.message;
            logMonitor(`Frame #${frameId} exception: ${e.message}`, 'error');
        })
        .finally(() => {
            renderSessionItems();
            updateSessionStateBackground();
            setTimeout(processNextFrame, 500);
        });
    }

    function stopBatch() {
        if (isRunning) {
            isHalted = true;
            logMonitor('Stop requested. Halting after current request completes...', 'warn');
            document.getElementById('btnStopBatch').disabled = true;
        }
    }

    function resumeBatch() {
        if (!isRunning) {
            isRunning = true;
            isHalted = false;
            document.getElementById('btnStartBatch').disabled = true;
            document.getElementById('btnStopBatch').disabled = false;
            document.getElementById('btnResumeBatch').disabled = true;
            logMonitor('Resuming batch...', 'info');
            processNextFrame();
        }
    }

    function finishBatch() {
        isRunning = false;
        isHalted = false;
        document.getElementById('btnStartBatch').disabled = false;
        document.getElementById('btnStopBatch').disabled = true;
        document.getElementById('btnResumeBatch').disabled = true;
    }

    // Modal Helpers
    function openEntityModal(entityType, entityId, label) {
        const url = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
        document.getElementById('entity-iframe').src = url;
        document.getElementById('entityModalTitle').textContent = label + ' — ' + entityType;
        document.getElementById('entity-modal-backdrop').classList.add('active');
    }

    function closeEntityModal() {
        document.getElementById('entity-modal-backdrop').classList.remove('active');
        document.getElementById('entity-iframe').src = 'about:blank';
    }

    function logMonitor(msg, type = 'info') {
        const monitor = document.getElementById('cliMonitor');
        const time = new Date().toLocaleTimeString();
        let color = '#a78bfa'; // default info
        if (type === 'error') color = '#f05060';
        if (type === 'warn') color = '#f5a623';
        if (type === 'success') color = '#4caf80';
        if (type === 'result') color = '#c8d4e8';
        
        monitor.innerHTML += `<span style="color: #5a6a80;">[${time}]</span> <span style="color: ${color};">${msg}</span>\n`;
        monitor.scrollTop = monitor.scrollHeight;
    }

    function clearMonitor() {
        document.getElementById('cliMonitor').innerHTML = '';
    }

    // Init call for sessions
    fetchSessionsList();

    return {
        setMode,
        searchSketches, addSketch, removeSketch,
        searchMapRuns, addMapRun, removeMapRun,
        addRange, removeRange,
        addFrameRange, removeFrameRange,
        openEntityModal, closeEntityModal,
        searchSequences, selectSequence, clearSequenceResolution,
        setPlushType, searchPlush, selectPlush, clearPlushResolution,
        startBatch, stopBatch, resumeBatch, clearMonitor,
        saveSession, loadSession, retryFailed, flagForRerun
    };
})();

// CV Model Select Management
const VisionModelsAPI = (() => {
    let _models = [];
    async function getModels() {
        if (_models.length > 0) return _models;
        try {
            const r = await fetch('https://gen.pollinations.ai/text/models');
            const d = await r.json();
            _models = d.map(x => x.name);
        } catch(e) {
            console.error('CV model fetch failed', e);
            _models = ['openai-fast', 'openai'];
        }
        return _models;
    }
    return { getModels };
})();

async function populateVisionModelSelect() {
    const sel = document.getElementById('visionModelSelect');
    if (!sel) return;
    const preferredDefault = 'openai-fast';
    const LS_KEY = 'cvbatch_last_model';
    let models = await VisionModelsAPI.getModels();
    if (!models || !models.length) models = ['openai-fast', 'openai'];

    sel.innerHTML = '';
    models.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m;
        sel.appendChild(opt);
    });

    let lastUsed = null;
    try { lastUsed = localStorage.getItem(LS_KEY); } catch(e) {}

    if (lastUsed && models.includes(lastUsed)) {
        sel.value = lastUsed;
    } else if (models.includes(preferredDefault)) {
        sel.value = preferredDefault;
    }

    sel.onchange = function() {
        try { localStorage.setItem(LS_KEY, sel.value); } catch(e) {}
    };
}

// Clipboard Management
const VISION_CB_AREA = 'cvbatch_prompts';

function loadVisionClipboardChips() {
    fetch('clipboard_manager.php?api_action=cb_get&view_area=' + VISION_CB_AREA)
        .then(r => r.json())
        .then(res => { if (res.status === 'success') renderVisionClipboardChips(res.data); })
        .catch(() => {});
}

function renderVisionClipboardChips(items) {
    const container = document.getElementById('visionClipboardChips');
    if (!container) return;
    container.innerHTML = '';
    if (!items || items.length === 0) {
        container.innerHTML = '<span style="font-size: 0.7rem; color: var(--pl-text-dim); font-style: italic;">Clipboard empty</span>';
        return;
    }
    items.forEach(item => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vision-chip' + (parseInt(item.pinned) ? ' pinned' : '');
        let displayTxt = item.label ? item.label : item.content;
        btn.textContent = displayTxt.length > 28 ? displayTxt.slice(0, 27) + '…' : displayTxt;
        btn.title = item.content;
        btn.onclick = () => { document.getElementById('visionPromptInput').value = item.content; };
        container.appendChild(btn);
    });
}

window.openVisionClipboardManager = function() {
    const url = `clipboard_manager.php?view_area=${VISION_CB_AREA}`;
    document.getElementById('entity-iframe').src = url;
    document.getElementById('entityModalTitle').textContent = 'Manage Clipboard';
    document.getElementById('entity-modal-backdrop').classList.add('active');
}

window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'clipboard_updated' && e.data.view_area === VISION_CB_AREA) {
        renderVisionClipboardChips(e.data.items);
    }
});
</script>

<div class="pl-nav">
    <span class="pl-nav-title"><i class="bi bi-eye"></i> Batch CV Module</span>
</div>

<div class="workspace" id="cvbatch-workspace">

    <!-- ── Batch Sessions ─────────────────────────────────────────────────── -->
    <div class="scene-block" style="border-color: var(--pl-purple);">
        <div class="scene-header" style="margin-bottom: 10px; padding-bottom: 10px;">
            <h3 class="scene-title" style="color: var(--pl-purple);"><i class="bi bi-save"></i> Batch Sessions</h3>
        </div>
        <div class="row-flex">
            <select id="sessionSelect" class="su-input" style="max-width: 200px;">
                <option value="">-- New Session --</option>
            </select>
            <button class="pl-btn pl-btn-secondary" onclick="CVBatchApp.loadSession()"><i class="bi bi-download"></i> Load</button>
            <div style="width: 1px; height: 24px; background: var(--pl-border); margin: 0 10px;"></div>
            <input type="text" id="sessionName" class="su-input" placeholder="Session Name..." style="max-width: 200px;">
            <button class="pl-btn pl-btn-secondary" onclick="CVBatchApp.saveSession()"><i class="bi bi-upload"></i> Save Config</button>
        </div>
    </div>

    <!-- ── Mode Switcher ──────────────────────────────────────────────────── -->
    <div class="su-mode-tabs">
        <button class="su-mode-tab active" id="su-tab-standard" onclick="CVBatchApp.setMode('standard')">
            <i class="bi bi-search"></i>
            <span>Standard</span>
        </button>
        <button class="su-mode-tab" id="su-tab-sequence" onclick="CVBatchApp.setMode('sequence')">
            <i class="bi bi-collection-play"></i>
            <span>Narrative Sequence</span>
        </button>
        <button class="su-mode-tab" id="su-tab-plush" onclick="CVBatchApp.setMode('plush')">
            <i class="bi bi-bookmark-heart"></i>
            <span>PLUSH</span>
        </button>
    </div>

    <!-- ── Standard Mode Panel ───────────────────────────────────────────── -->
    <div class="su-mode-panel active" id="su-panel-standard">
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-image"></i> Specific Sketches</h3>
            </div>
            <div class="su-ac-wrap">
                <input type="text" id="sketchSearch" class="su-input" placeholder="Search sketch by ID or Name..." oninput="CVBatchApp.searchSketches(this.value)">
                <div id="sketchAc" class="su-ac-dropdown"></div>
            </div>
            <div id="sketchChips" class="beat-entity-chips"></div>
        </div>

        <div class="scene-block">
            <div class="scene-header" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                <label class="su-check-label" style="margin-bottom:0;">
                    <input type="checkbox" id="chkRanges" onchange="document.getElementById('rangesWrap').style.display = this.checked ? 'block' : 'none'">
                    <span class="scene-title" style="margin:0;"><i class="bi bi-arrow-left-right"></i> Include Sketch ID Ranges</span>
                </label>
            </div>
            <div id="rangesWrap" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--pl-border);">
                <div id="rangesList" class="beat-entity-chips" style="margin-bottom: 12px;"></div>
                <div class="row-flex">
                    <input type="number" id="rangeStart" class="su-input" placeholder="Start Sketch ID" style="width: 140px;">
                    <input type="number" id="rangeEnd" class="su-input" placeholder="End Sketch ID" style="width: 140px;">
                    <button class="pl-btn pl-btn-secondary" onclick="CVBatchApp.addRange()">+ Add Sketch Range</button>
                </div>
            </div>
        </div>

        <div class="scene-block">
            <div class="scene-header" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                <label class="su-check-label" style="margin-bottom:0;">
                    <input type="checkbox" id="chkFrameRanges" onchange="document.getElementById('frameRangesWrap').style.display = this.checked ? 'block' : 'none'">
                    <span class="scene-title" style="margin:0;"><i class="bi bi-images"></i> Include Frame ID Ranges</span>
                </label>
            </div>
            <div id="frameRangesWrap" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--pl-border);">
                <div id="frameRangesList" class="beat-entity-chips" style="margin-bottom: 12px;"></div>
                <div class="row-flex">
                    <input type="number" id="frameRangeStart" class="su-input" placeholder="Start Frame ID" style="width: 140px;">
                    <input type="number" id="frameRangeEnd" class="su-input" placeholder="End Frame ID" style="width: 140px;">
                    <button class="pl-btn pl-btn-secondary" onclick="CVBatchApp.addFrameRange()">+ Add Frame Range</button>
                </div>
            </div>
        </div>

        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-play-btn"></i> Map Runs (Sketches)</h3>
            </div>
            <p style="font-size:.8rem; color:var(--pl-text-dim); margin:0 0 12px; line-height:1.5;">
                Resolves sketches linked to the specified map_runs, returning their latest generated frame.
            </p>
            <div class="su-ac-wrap">
                <input type="text" id="mapRunSearch" class="su-input" placeholder="Search map run by ID or note (or click for latest)..." oninput="CVBatchApp.searchMapRuns(this.value)" onclick="CVBatchApp.searchMapRuns(this.value)">
                <div id="mapRunAc" class="su-ac-dropdown"></div>
            </div>
            <div id="mapRunChips" class="beat-entity-chips"></div>
        </div>
    </div>

    <!-- ── Narrative Sequence Mode Panel ────────────────────────────────── -->
    <div class="su-mode-panel" id="su-panel-sequence">
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-collection-play"></i> Select Narrative Sequence</h3>
            </div>
            <p style="font-size:.8rem; color:var(--pl-text-dim); margin:0 0 12px; line-height:1.5;">
                Resolves all sketches that are part of the selected narrative sequence via its JSON structure.
            </p>
            <div class="su-ac-wrap">
                <input type="text" id="seqSearch" class="su-input" placeholder="Search narrative sequence by ID or name…" oninput="CVBatchApp.searchSequences(this.value)">
                <div id="seqAc" class="su-ac-dropdown"></div>
            </div>
            <!-- Resolved banner -->
            <div id="seqResolvedBanner" class="su-resolved-banner" style="display:none;"></div>
        </div>
    </div>

    <!-- ── PLUSH Mode Panel ──────────────────────────────────────────────── -->
    <div class="su-mode-panel" id="su-panel-plush">
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title" style="color:var(--pl-purple);"><i class="bi bi-bookmark-heart"></i> Select PLUSH Source</h3>
            </div>
            <p style="font-size:.8rem; color:var(--pl-text-dim); margin:0 0 14px; line-height:1.5;">
                Resolves all sketch references from highlight blocks in the selected PLUSH story or collection.
            </p>
            <!-- Type toggle -->
            <div class="row-flex" style="margin-bottom:14px;">
                <button class="pl-btn pl-btn-secondary active-plush-type" id="plushTypeBtn-story" onclick="CVBatchApp.setPlushType('story')"
                    style="border-color:var(--pl-purple); color:var(--pl-purple); background:rgba(167,139,250,.1);">
                    <i class="bi bi-journal-text"></i> Story
                </button>
                <button class="pl-btn pl-btn-secondary" id="plushTypeBtn-collection" onclick="CVBatchApp.setPlushType('collection')">
                    <i class="bi bi-collection"></i> Collection
                </button>
            </div>
            <div class="su-ac-wrap">
                <input type="text" id="plushSearch" class="su-input" placeholder="Search PLUSH story…" oninput="CVBatchApp.searchPlush(this.value)">
                <div id="plushAc" class="su-ac-dropdown"></div>
            </div>
            <!-- Resolved banner -->
            <div id="plushResolvedBanner" class="su-resolved-banner plush" style="display:none;"></div>
        </div>
    </div>

    <!-- ── Action: Batch Execution ────────────────────────────────── -->
    <div class="scene-block" style="border-color: var(--pl-amber);">
        <div class="scene-header">
            <h3 class="scene-title" style="color: var(--pl-amber);"><i class="bi bi-cpu"></i> Batch Execution</h3>
        </div>
        <div style="display:flex; flex-direction:column; gap: 14px;">
            <div>
                <label style="font-size:0.8rem; color:var(--pl-text-dim);">CV Model</label>
                <select id="visionModelSelect" class="su-input" style="width: 100%; margin-top: 4px;"></select>
            </div>
            
            <div>
                <div class="vision-clipboard-row">
                    <label style="font-size:0.8rem; color:var(--pl-text-dim); white-space:nowrap;">Instructions for AI</label>
                    <div id="visionClipboardChips" class="vision-clipboard-chips"></div>
                    <button type="button" class="pl-btn pl-btn-secondary" onclick="openVisionClipboardManager()" style="padding: 4px 8px; font-size:0.7rem; flex-shrink:0;"><i class="bi bi-clipboard"></i> Manage</button>
                </div>
                <textarea id="visionPromptInput" class="su-input" rows="3" style="font-family:var(--mono); resize:vertical;">Describe this image in a comma-separated format suitable for Stable Diffusion prompting. Describe every detail. Focus on visual elements, style, lighting, and composition. Do not include introductory text.</textarea>
            </div>

            <div style="display:flex; gap: 10px; margin-top: 10px; align-items: center; flex-wrap: wrap;">
                <button class="pl-btn primary" id="btnStartBatch" onclick="CVBatchApp.startBatch()">
                    <i class="bi bi-play-fill"></i> Start / Restart
                </button>
                <button class="pl-btn pl-btn-secondary" id="btnStopBatch" onclick="CVBatchApp.stopBatch()" disabled style="color:var(--pl-red); border-color:var(--pl-red);">
                    <i class="bi bi-stop-fill"></i> Stop
                </button>
                <button class="pl-btn pl-btn-secondary" id="btnResumeBatch" onclick="CVBatchApp.resumeBatch()" disabled style="color:var(--pl-amber); border-color:var(--pl-amber);">
                    <i class="bi bi-play-circle"></i> Resume
                </button>
                <button class="pl-btn pl-btn-secondary" onclick="CVBatchApp.clearMonitor()">Clear Log</button>
            </div>
            
            <!-- Session Items Table -->
            <div id="sessionItemsContainer" style="display: none; margin-top: 10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <h4 style="margin:0; font-family:var(--mono); font-size:0.8rem; color:var(--pl-text);">Queue & Results</h4>
                    <button class="pl-btn pl-btn-secondary" onclick="CVBatchApp.retryFailed()" style="padding: 3px 8px; font-size: 0.7rem;"><i class="bi bi-arrow-clockwise"></i> Retry Failed</button>
                </div>
                <div class="session-table-wrap">
                    <table class="session-table">
                        <thead>
                            <tr>
                                <th>Frame ID</th>
                                <th>Status</th>
                                <th style="width: 60%;">Message</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sessionItemsBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Terminal output log -->
            <div id="cliMonitor" style="background: #080b10; color: #a78bfa; font-family: var(--mono); font-size: 0.75rem; padding: 12px; height: 250px; overflow-y: auto; border-radius: 4px; border: 1px solid var(--pl-border); white-space: pre-wrap; line-height: 1.5; margin-top: 10px;"></div>
        </div>
    </div>

</div>

<!-- Details Modal -->
<div class="su-modal-backdrop" id="entity-modal-backdrop" onmousedown="if(event.target===this)CVBatchApp.closeEntityModal()">
    <div class="su-modal-box">
        <div class="su-modal-header">
            <span class="su-modal-title" id="entityModalTitle">Details</span>
            <button class="su-modal-close" onclick="CVBatchApp.closeEntityModal()">&#10005;</button>
        </div>
        <iframe id="entity-iframe" src="about:blank"></iframe>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (e) => {
        const wraps = document.querySelectorAll('.su-ac-wrap');
        let clickedInside = false;
        wraps.forEach(wrap => {
            if (wrap.contains(e.target)) clickedInside = true;
        });
        if (!clickedInside) {
            ['sketchAc','seqAc','plushAc','mapRunAc'].forEach(id => {
                const ac = document.getElementById(id);
                if (ac) ac.style.display = 'none';
            });
        }
    });

    document.getElementById('plushTypeBtn-story').style.cssText = 'border-color:var(--pl-purple);color:var(--pl-purple);background:rgba(167,139,250,.1);';

    populateVisionModelSelect();
    loadVisionClipboardChips();
});
</script>

<?php
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>