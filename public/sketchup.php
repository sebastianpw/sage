<?php
// public/sketchup.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "SketchUp Export";

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* Forge Design System - SketchUp */
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

    /* Variable Mapping for included sketchup_graph.php so its modals aren't transparent */
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

    /* Light Theme Mapping overrides */
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
.pl-nav-btn { padding:6px 12px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); text-decoration:none; transition:all .2s; background:var(--pl-surface); cursor:pointer; font-family:'Space Mono',monospace; font-size:.7rem; display:inline-flex; align-items:center; gap:6px; }
.pl-nav-btn:hover { color:var(--pl-amber); border-color:var(--pl-amber); }
.pl-nav-btn.primary { color:#000; background:var(--pl-teal); border-color:var(--pl-teal); font-weight:bold; }
.pl-nav-btn.primary:hover { filter:brightness(1.1); color:#000; }

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
.chip-remove { background:none; border:none; cursor:pointer; color:inherit; opacity:.6; font-size:.8rem; padding:0; line-height:1; transition:opacity .15s; }
.chip-remove:hover { opacity:1; }

/* Labels & Checks */
.su-check-label { display:flex; align-items:center; gap:8px; font-size:.85rem; cursor:pointer; margin-bottom:10px; user-select:none; color:var(--pl-text); transition: color 0.15s; }
.su-check-label:hover { color:var(--pl-amber); }
.su-check-label input[type="checkbox"] { accent-color:var(--pl-teal); width:16px; height:16px; cursor:pointer; }
.su-check-label.purple input[type="checkbox"] { accent-color:var(--pl-purple); }
.su-check-label.purple:hover { color:var(--pl-purple); }

.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-surface); color:var(--pl-text-dim); }
.pl-btn-secondary:hover { border-color:var(--pl-amber); color:var(--pl-amber); }

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
</style>

<!-- 1. Define global SketchUpApp before including any nested script that might reference it! -->
<script>
window.SketchUpApp = (function() {
    let selectedSketches = [];
    let sketchRanges = [];
    let kgNodes = [];
    let searchTimeout = null;

    // ── Mode state ────────────────────────────────────────────────────────────
    // 'standard' | 'sequence' | 'plush'
    let currentMode = 'standard';

    // Resolved sketch IDs from sequence/plush modes (kept separate so standard mode is unaffected)
    let resolvedSketchIds = [];    // array of {id, name}
    let resolvedPlushEntities = []; // array of entity records (for plush mode passthrough)

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

    // ── Standard mode: Sketch search ─────────────────────────────────────────
    function searchSketches(q) {
        clearTimeout(searchTimeout);
        if (!q.trim()) { document.getElementById('sketchAc').style.display = 'none'; return; }
        searchTimeout = setTimeout(() => {
            fetch('sketchup_api.php', {
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
            <div class="su-ac-item" onclick="SketchUpApp.addSketch(${r.id}, '${r.name.replace(/'/g, "\\'")}')">
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
                <span style="cursor:pointer;" onclick="SketchUpApp.openEntityModal('sketches', ${s.id}, '${s.name.replace(/'/g, "\\'")}')">#${s.id} ${s.name}</span>
                <button class="chip-remove" onclick="SketchUpApp.removeSketch(${s.id})"><i class="bi bi-x"></i></button>
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
                <button class="chip-remove" onclick="SketchUpApp.removeRange(${i})"><i class="bi bi-x"></i></button>
            </span>
        `).join('');
    }

    // ── Narrative Sequence mode ───────────────────────────────────────────────
    let seqSearchTimeout = null;
    let selectedSequenceId = null;

    function searchSequences(q) {
        clearTimeout(seqSearchTimeout);
        const ac = document.getElementById('seqAc');
        if (!q.trim()) { ac.style.display = 'none'; return; }
        seqSearchTimeout = setTimeout(() => {
            fetch('sketchup_api.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'search_narrative_sequences', q})
            }).then(r=>r.json()).then(res => {
                if (!res.ok || !res.results.length) { ac.style.display = 'none'; return; }
                ac.innerHTML = res.results.map(r => `
                    <div class="su-ac-item" onclick="SketchUpApp.selectSequence(${r.id}, '${r.name.replace(/'/g, "\\'")}')">
                        <span style="color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:0.7rem; margin-right:6px;">#${r.id}</span>
                        ${r.name}
                    </div>
                `).join('');
                ac.style.display = 'block';
            });
        }, 250);
    }

    function selectSequence(id, name) {
        selectedSequenceId = id;
        const inp = document.getElementById('seqSearch');
        if (inp) inp.value = name;
        document.getElementById('seqAc').style.display = 'none';
        resolveSequenceSketches(id, name);
    }

    function resolveSequenceSketches(seqId, seqName) {
        const banner = document.getElementById('seqResolvedBanner');
        if (banner) { banner.style.display = 'flex'; banner.innerHTML = '<i class="bi bi-hourglass-split"></i> Resolving sketches from sequence…'; }
        resolvedSketchIds = [];
        fetch('sketchup_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'resolve_sequence_sketches', sequence_id: seqId})
        }).then(r=>r.json()).then(res => {
            if (!res.ok) { Toast.show(res.error || 'Failed to resolve sequence.', 'error'); if(banner) banner.style.display='none'; return; }
            resolvedSketchIds = res.sketches || [];
            if (banner) {
                banner.style.display = 'flex';
                banner.innerHTML = `<i class="bi bi-check2-circle"></i> <strong>${resolvedSketchIds.length}</strong> sketch(es) resolved from <em>${seqName}</em> <button class="su-banner-clear" onclick="SketchUpApp.clearSequenceResolution()">Clear</button>`;
            }
        });
    }

    function clearSequenceResolution() {
        resolvedSketchIds = [];
        selectedSequenceId = null;
        const inp = document.getElementById('seqSearch');
        if (inp) inp.value = '';
        const banner = document.getElementById('seqResolvedBanner');
        if (banner) banner.style.display = 'none';
    }

    // ── PLUSH mode ────────────────────────────────────────────────────────────
    let plushSearchTimeout = null;
    let selectedPlushId = null;
    let selectedPlushType = 'story'; // 'story' | 'collection'

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
            fetch('sketchup_api.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'search_plush', q, plush_type: selectedPlushType})
            }).then(r=>r.json()).then(res => {
                if (!res.ok || !res.results.length) { ac.style.display = 'none'; return; }
                ac.innerHTML = res.results.map(r => `
                    <div class="su-ac-item" onclick="SketchUpApp.selectPlush(${r.id}, '${r.name.replace(/'/g, "\\'")}')">
                        <span style="color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:0.7rem; margin-right:6px;">#${r.id}</span>
                        ${r.name}
                    </div>
                `).join('');
                ac.style.display = 'block';
            });
        }, 250);
    }

    function selectPlush(id, name) {
        selectedPlushId = id;
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
        fetch('sketchup_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'resolve_plush_entities', plush_id: plushId, plush_type: selectedPlushType})
        }).then(r=>r.json()).then(res => {
            if (!res.ok) { Toast.show(res.error || 'Failed to resolve PLUSH entities.', 'error'); if(banner) banner.style.display='none'; return; }
            resolvedSketchIds    = res.sketches || [];
            resolvedPlushEntities = res.entities || [];
            if (banner) {
                const parts = [];
                if (resolvedSketchIds.length) parts.push(`<strong>${resolvedSketchIds.length}</strong> sketch(es)`);
                if (resolvedPlushEntities.length) parts.push(`<strong>${resolvedPlushEntities.length}</strong> entity ref(s)`);
                banner.style.display = 'flex';
                banner.innerHTML = `<i class="bi bi-check2-circle"></i> ${parts.join(', ')} resolved from <em>${plushName}</em> <button class="su-banner-clear" onclick="SketchUpApp.clearPlushResolution()">Clear</button>`;
            }
        });
    }

    function clearPlushResolution() {
        resolvedSketchIds = [];
        resolvedPlushEntities = [];
        selectedPlushId = null;
        const inp = document.getElementById('plushSearch');
        if (inp) inp.value = '';
        const banner = document.getElementById('plushResolvedBanner');
        if (banner) banner.style.display = 'none';
    }

    // ── Export (all modes) ────────────────────────────────────────────────────
    function exportData(format) {
        const include_tables = Array.from(document.querySelectorAll('.chk-table:checked')).map(cb => cb.value);
        const include_frames = document.getElementById('chkFrames').checked;
        const include_kg = document.getElementById('chkKg').checked;

        // Merge sketch IDs from active mode
        let allSketchIds = [];
        if (currentMode === 'standard') {
            allSketchIds = selectedSketches.map(s => s.id);
        } else {
            // sequence or plush: use resolved IDs
            allSketchIds = resolvedSketchIds.map(s => s.id !== undefined ? s.id : s);
        }

        // Also include standard ranges always
        const sketch_ranges = sketchRanges;

        const config = {
            sketch_ids: allSketchIds,
            sketch_ranges,
            include_tables,
            include_frames,
            kg_nodes: include_kg ? kgNodes.map(n => n.id) : [],
            kg_include_edges: include_kg ? document.getElementById('kgIncludeEdges').checked : false,
            // Pass mode + resolved plush entities for PLUSH mode
            export_mode: currentMode,
            plush_entities: currentMode === 'plush' ? resolvedPlushEntities : []
        };

        if (!config.sketch_ids.length && !config.sketch_ranges.length && !config.kg_nodes.length && !config.plush_entities.length) {
            if(window.Toast) Toast.show('Please select at least one source to export.', 'warn');
            else alert('Please select at least one source to export.');
            return;
        }

        const btnJson = document.getElementById('btnExportJson');
        const btnSql = document.getElementById('btnExportSql');
        const targetBtn = format === 'sql' ? btnSql : btnJson;
        
        const originalText = targetBtn.innerHTML;
        btnJson.disabled = true; btnSql.disabled = true;
        targetBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';

        fetch('sketchup_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'export', config, format })
        })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            const cd = r.headers.get('content-disposition');
            let filename = `sketchup_export.${format}`;
            if (cd && cd.includes('filename=')) filename = cd.split('filename=')[1].replace(/"/g, '');
            return r.blob().then(blob => ({blob, filename}));
        })
        .then(({blob, filename}) => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = filename;
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(url);
            if(window.Toast) Toast.show('Export successful!', 'success');
        })
        .catch(e => {
            if(window.Toast) Toast.show(e.message, 'error');
            else alert(e.message);
        })
        .finally(() => {
            btnJson.disabled = false; btnSql.disabled = false;
            targetBtn.innerHTML = originalText;
        });
    }

    // KG Interface implementation
    function addKgNode(node) {
        if (kgNodes.find(n => n.id === node.id)) return;
        kgNodes.push(node);
        const cb = document.querySelector(`#kgTreeWrap input[data-dbid="${node.id}"]`);
        if (cb) { cb.checked = true; cb.closest('.sk2-tree-node')?.classList.add('is-checked'); }
        renderKgChips(); updateKgPreview();
        if (typeof SuGraph !== 'undefined') SuGraph.onKgNodesChanged();
    }

    function removeKgNode(id) {
        kgNodes = kgNodes.filter(n => n.id !== id);
        const cb = document.querySelector(`#kgTreeWrap input[data-dbid="${id}"]`);
        if (cb) { cb.checked = false; cb.closest('.sk2-tree-node')?.classList.remove('is-checked'); }
        renderKgChips(); updateKgPreview();
        if (typeof SuGraph !== 'undefined') SuGraph.onKgNodesChanged();
    }

    function renderKgChips() {
        const c = document.getElementById('kgSubpotChips');
        if (!kgNodes.length) {
            c.innerHTML = '<span style="font-family:var(--mono);font-size:0.7rem;color:var(--pl-text-dim);padding:4px;">No nodes selected — use tree or graph</span>';
            return;
        }
        c.innerHTML = kgNodes.map(n => `
            <span class="beat-entity-chip" style="border-color:var(--pl-purple); color:var(--pl-purple); background:rgba(167,139,250,0.1);">
                <span style="cursor:pointer;" onclick="SketchUpApp.openEntityModal('kg_nodes', ${n.id}, '${n.name.replace(/'/g, "\\'")}')">\uD83C\uDF3F ${n.name}</span>
                <button class="chip-remove" title="Mini Graph" onclick="SketchUpApp.openKgNodeGraph(${n.id})" type="button" style="margin-left:4px; opacity:1;"><i class="bi bi-diagram-2-fill"></i></button>
                <button class="chip-remove" onclick="SketchUpApp.removeKgNode(${n.id})" style="margin-left:4px;"><i class="bi bi-x"></i></button>
            </span>`).join('');
    }

    function openKgNodeGraph(nodeId) {
        if (typeof SuGraph !== 'undefined' && SuGraph.openModal) {
            SuGraph.openModal(parseInt(nodeId, 10), 1);
        }
    }

    function updateKgPreview() {
        if (!kgNodes.length) { document.getElementById('kgSubpotPreview').textContent = 'Select nodes to preview...'; return; }
        fetch('sketchup_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'kg_subpot_preview', node_ids: kgNodes.map(n => n.id), include_edges: document.getElementById('kgIncludeEdges').checked })
        }).then(r=>r.json()).then(res => {
            if (res.ok) document.getElementById('kgSubpotPreview').textContent = res.preview;
        });
    }

    let kgTreeRaw = [], kgTreeFilter = '';
    const KG_TREE_OPEN_KEY = 'su_kg_tree_open';

    function loadKgTree() {
        document.getElementById('kgTreeWrap').innerHTML = '<div style="padding:14px;color:var(--pl-text-dim);">Loading tree...</div>';
        fetch('sketchup_api.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action: 'fetch_tree'}) })
        .then(r=>r.json()).then(res => {
            if (res.ok) { kgTreeRaw = res.tree || []; renderKgTree(); }
        });
    }

    function kgNodeIcon(t) { const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' }; return map[t] || '📝'; }
    function kgTreeSaveOpenState() { const open = []; document.querySelectorAll('#kgTreeWrap .sk2-tree-children.open').forEach(el => open.push(el.id.replace('sk2kids-', ''))); try { localStorage.setItem(KG_TREE_OPEN_KEY, JSON.stringify(open)); } catch(e) {} }
    function kgTreeLoadOpenState() { try { const raw = localStorage.getItem(KG_TREE_OPEN_KEY); if (raw) return new Set(JSON.parse(raw)); } catch(e) {} return null; }

    function renderKgTree() {
        const wrap = document.getElementById('kgTreeWrap');
        if (!kgTreeRaw.length) { wrap.innerHTML = '<div style="padding:10px;font-size:0.75rem;color:var(--pl-text-dim);">No nodes in graph.</div>'; return; }

        const checkedIds = new Set(kgNodes.map(n => 'n_' + n.id)), filter = kgTreeFilter, childMap = {};
        kgTreeRaw.forEach(n => { const p = n.parent || '#'; if (!childMap[p]) childMap[p] = []; childMap[p].push(n); });

        let matchingJsIds = null;
        if (filter) {
            matchingJsIds = new Set();
            kgTreeRaw.forEach(n => {
                if (n.type === 'node' && n.text.toLowerCase().includes(filter)) {
                    matchingJsIds.add(n.id); let cur = n;
                    while (cur.parent && cur.parent !== '#') { matchingJsIds.add(cur.parent); cur = kgTreeRaw.find(x => x.id === cur.parent) || { parent: '#' }; }
                }
            });
        }

        function buildLevel(parentId, depth) {
            const children = (childMap[parentId] || []).filter(n => !matchingJsIds || matchingJsIds.has(n.id));
            if (!children.length) return '';
            let html = '';
            children.forEach(node => {
                const isFolder = node.type === 'folder', jsId = node.id, isChecked = !isFolder && checkedIds.has(jsId);
                const hasKids = !!(childMap[jsId] && childMap[jsId].filter(c => !matchingJsIds || matchingJsIds.has(c.id)).length);
                const icon = isFolder ? '📁' : kgNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');
                const toggleBtn = (isFolder && hasKids) ? `<span class="sk2-node-toggle" data-jid="${jsId}" onclick="SketchUpApp.kgToggleFolder(this)" style="cursor:pointer; display:inline-block; width:15px; text-align:center; font-size:0.7rem; color:var(--pl-text-dim);">▶</span>` : `<span style="width:15px;display:inline-block;flex-shrink:0;"></span>`;
                const dbId = (node.data && node.data.db_id) ? node.data.db_id : '', nodeType = (node.data && node.data.node_type) ? node.data.node_type : 'note';
                const cbOrSpacer = isFolder ? `<span style="width:13px;display:inline-block;flex-shrink:0;"></span>` : `<input type="checkbox" ${isChecked ? 'checked' : ''} data-jid="${jsId}" data-dbid="${dbId}" data-name="${node.text.replace(/"/g, '&quot;')}" data-type="${nodeType}" onchange="SketchUpApp.kgCheckNode(this)" style="accent-color:var(--pl-purple); cursor:pointer;">`;

                html += `
                <div class="sk2-tree-node ${isFolder ? 'is-folder' : 'is-node'}${isChecked ? ' is-checked' : ''}" style="padding-left:${8 + (depth * 12)}px; padding-top:4px; padding-bottom:4px; display:flex; align-items:center; gap:6px; font-size:0.8rem; user-select:none; ${isFolder ? 'font-weight:700;' : ''}">
                    ${toggleBtn} ${cbOrSpacer} <span class="sk2-node-icon">${icon}</span> <span class="sk2-node-label" style="${isChecked ? 'color:var(--pl-purple);' : 'color:var(--pl-text);'}">${node.text}</span>
                </div>`;

                if (isFolder && hasKids) { html += `<div class="sk2-tree-children" id="sk2kids-${jsId}" style="display:none;">${buildLevel(jsId, depth + 1)}</div>`; }
            });
            return html;
        }

        wrap.innerHTML = buildLevel('#', 0);

        if (!filter) {
            const savedOpen = kgTreeLoadOpenState();
            if (savedOpen && savedOpen.size > 0) {
                wrap.querySelectorAll('.sk2-tree-children').forEach(el => {
                    const jsId = el.id.replace('sk2kids-', '');
                    if (savedOpen.has(jsId)) {
                        el.style.display = 'block'; el.classList.add('open');
                        const tEl = wrap.querySelector(`.sk2-node-toggle[data-jid="${jsId}"]`);
                        if (tEl) { tEl.style.transform = 'rotate(90deg)'; tEl.classList.add('open'); }
                    }
                });
            }
        }
    }

    function kgToggleFolder(btn) {
        const jsId = btn.dataset.jid, kids = document.getElementById('sk2kids-' + jsId);
        if (!kids) return;
        const isOpen = kids.classList.contains('open');
        kids.style.display = isOpen ? 'none' : 'block'; kids.classList.toggle('open');
        btn.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)'; btn.classList.toggle('open');
        kgTreeSaveOpenState();
    }

    function kgCheckNode(cb) {
        const dbId = parseInt(cb.dataset.dbid), name = cb.dataset.name, type = cb.dataset.type;
        if (cb.checked) addKgNode({ id: dbId, name, node_type: type }); else removeKgNode(dbId);
    }

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

    return {
        setMode,
        searchSketches, addSketch, removeSketch,
        addRange, removeRange, exportData,
        addKgNode, removeKgNode, openKgNodeGraph,
        kgToggleFolder, kgCheckNode, updateKgPreview, loadKgTree,
        setKgTreeFilter: (f) => { kgTreeFilter = f.trim().toLowerCase(); renderKgTree(); },
        openEntityModal, closeEntityModal,
        _kgNodeAdded: (id) => kgNodes.some(n => n.id === id),
        _getKgNodes: () => kgNodes,
        // Sequence mode
        searchSequences, selectSequence, clearSequenceResolution,
        // PLUSH mode
        setPlushType, searchPlush, selectPlush, clearPlushResolution
    };
})();
</script>

<!-- 2. Nav -->
<div class="pl-nav">
    <span class="pl-nav-title"><i class="bi bi-box-seam"></i> SketchUp Export Module</span>
    <button class="pl-nav-btn" id="btnExportJson" onclick="SketchUpApp.exportData('json')" title="Export JSON">
        <i class="bi bi-braces"></i> JSON
    </button>
    <button class="pl-nav-btn primary" id="btnExportSql" onclick="SketchUpApp.exportData('sql')" title="Export SQL">
        <i class="bi bi-database"></i> SQL
    </button>
</div>

<!-- 3. Workspace -->
<div class="workspace" id="sketchup-workspace">

    <!-- ── Mode Switcher ──────────────────────────────────────────────────── -->
    <div class="su-mode-tabs">
        <button class="su-mode-tab active" id="su-tab-standard" onclick="SketchUpApp.setMode('standard')">
            <i class="bi bi-search"></i>
            <span>Standard</span>
        </button>
        <button class="su-mode-tab" id="su-tab-sequence" onclick="SketchUpApp.setMode('sequence')">
            <i class="bi bi-collection-play"></i>
            <span>Narrative Sequence</span>
        </button>
        <button class="su-mode-tab" id="su-tab-plush" onclick="SketchUpApp.setMode('plush')">
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
                <input type="text" id="sketchSearch" class="su-input" placeholder="Search sketch by ID or Name..." oninput="SketchUpApp.searchSketches(this.value)">
                <div id="sketchAc" class="su-ac-dropdown"></div>
            </div>
            <div id="sketchChips" class="beat-entity-chips"></div>
        </div>

        <div class="scene-block">
            <div class="scene-header" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                <label class="su-check-label" style="margin-bottom:0;">
                    <input type="checkbox" id="chkRanges" onchange="document.getElementById('rangesWrap').style.display = this.checked ? 'block' : 'none'">
                    <span class="scene-title" style="margin:0;"><i class="bi bi-arrow-left-right"></i> Include ID Ranges</span>
                </label>
            </div>
            <div id="rangesWrap" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--pl-border);">
                <div id="rangesList" class="beat-entity-chips" style="margin-bottom: 12px;"></div>
                <div class="row-flex">
                    <input type="number" id="rangeStart" class="su-input" placeholder="Start ID" style="width: 120px;">
                    <input type="number" id="rangeEnd" class="su-input" placeholder="End ID" style="width: 120px;">
                    <button class="pl-btn pl-btn-secondary" onclick="SketchUpApp.addRange()">+ Add Range</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Narrative Sequence Mode Panel ────────────────────────────────── -->
    <div class="su-mode-panel" id="su-panel-sequence">
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-collection-play"></i> Select Narrative Sequence</h3>
            </div>
            <p style="font-size:.8rem; color:var(--pl-text-dim); margin:0 0 12px; line-height:1.5;">
                Resolves all sketches that are part of the selected narrative sequence via its <code>sequence_data</code> JSON and <code>frames_2_sketches</code> linkage.
            </p>
            <div class="su-ac-wrap">
                <input type="text" id="seqSearch" class="su-input" placeholder="Search narrative sequence by ID or name…" oninput="SketchUpApp.searchSequences(this.value)">
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
                Resolves all entity references (characters, factions, locations, sketches, KG nodes) from highlight blocks in the selected PLUSH story or collection.
                Sketch entities are added to the sketch export; other entity types are included as reference data.
            </p>
            <!-- Type toggle -->
            <div class="row-flex" style="margin-bottom:14px;">
                <button class="pl-btn pl-btn-secondary active-plush-type" id="plushTypeBtn-story" onclick="SketchUpApp.setPlushType('story')"
                    style="border-color:var(--pl-purple); color:var(--pl-purple); background:rgba(167,139,250,.1);">
                    <i class="bi bi-journal-text"></i> Story
                </button>
                <button class="pl-btn pl-btn-secondary" id="plushTypeBtn-collection" onclick="SketchUpApp.setPlushType('collection')">
                    <i class="bi bi-collection"></i> Collection
                </button>
            </div>
            <div class="su-ac-wrap">
                <input type="text" id="plushSearch" class="su-input" placeholder="Search PLUSH story…" oninput="SketchUpApp.searchPlush(this.value)">
                <div id="plushAc" class="su-ac-dropdown"></div>
            </div>
            <!-- Resolved banner -->
            <div id="plushResolvedBanner" class="su-resolved-banner plush" style="display:none;"></div>
        </div>
    </div>

    <!-- ── Additional Options (all modes) ────────────────────────────────── -->
    <div class="scene-block" style="display: flex; flex-wrap: wrap; gap: 30px;">
        <div style="flex: 1; min-width: 250px;">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-table"></i> Additional Sketch Data</h3>
            </div>
            <label class="su-check-label"><input type="checkbox" class="chk-table" value="sketch_analysis"> sketch_analysis</label>
            <label class="su-check-label"><input type="checkbox" class="chk-table" value="sketch_sequence_analysis"> sketch_sequence_analysis</label>
            <label class="su-check-label"><input type="checkbox" class="chk-table" value="sketch_overlay_texts"> sketch_overlay_texts</label>
            <label class="su-check-label"><input type="checkbox" class="chk-table" value="sketch_ingredients"> sketch_ingredients</label>
        </div>
        <div style="flex: 1; min-width: 250px;">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-images"></i> Assigned Frames</h3>
            </div>
            <label class="su-check-label">
                <input type="checkbox" id="chkFrames"> Include mapped frames
            </label>
            <div style="font-size:0.75rem; color:var(--pl-text-dim); margin-left:24px; line-height:1.4;">
                Exports entries from <code>frames</code> &amp; <code>frames_2_sketches</code> directly linked to your selected sketches.
            </div>
        </div>
    </div>

    <div class="scene-block">
        <div class="scene-header" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
            <label class="su-check-label purple" style="margin-bottom:0; font-weight:bold;">
                <input type="checkbox" id="chkKg">
                <span class="scene-title" style="margin:0; color:var(--pl-purple);"><i class="bi bi-diagram-3"></i> Knowledge Graph Subpot Export</span>
            </label>
        </div>
        <div id="kgContainer" style="display:none; margin-top:15px; padding-top:15px; border-top: 1px solid var(--pl-border);">
            <?php include __DIR__ . '/sketchup_graph.php'; ?>
        </div>
    </div>

</div>

<!-- Entity Details Modal -->
<div class="su-modal-backdrop" id="entity-modal-backdrop" onmousedown="if(event.target===this)SketchUpApp.closeEntityModal()">
    <div class="su-modal-box">
        <div class="su-modal-header">
            <span class="su-modal-title" id="entityModalTitle">Entity Details</span>
            <button class="su-modal-close" onclick="SketchUpApp.closeEntityModal()">&#10005;</button>
        </div>
        <iframe id="entity-iframe" src="about:blank"></iframe>
    </div>
</div>

<!-- 4. Initialization Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Hide AC on click outside
    document.addEventListener('click', (e) => {
        // Standard sketch AC
        const wrap = document.querySelector('.su-ac-wrap');
        if (wrap && !wrap.contains(e.target)) {
            ['sketchAc','seqAc','plushAc'].forEach(id => {
                const ac = document.getElementById(id);
                if (ac) ac.style.display = 'none';
            });
        }
    });

    const kgSearch = document.getElementById('kgTreeSearch');
    if (kgSearch) kgSearch.addEventListener('input', e => SketchUpApp.setKgTreeFilter(e.target.value));
    
    const chkKg = document.getElementById('chkKg');
    if (chkKg) {
        chkKg.addEventListener('change', e => {
            document.getElementById('kgContainer').style.display = e.target.checked ? 'block' : 'none';
            if (e.target.checked) SketchUpApp.loadKgTree();
        });
    }

    const kgEdges = document.getElementById('kgIncludeEdges');
    if (kgEdges) kgEdges.addEventListener('change', SketchUpApp.updateKgPreview);

    // Style the active plush type button on init
    document.getElementById('plushTypeBtn-story').style.cssText = 'border-color:var(--pl-purple);color:var(--pl-purple);background:rgba(167,139,250,.1);';
});
</script>

<?php
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
