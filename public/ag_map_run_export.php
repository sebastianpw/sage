<?php
// public/ag_map_run_export.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "AG Map Run Exporter";

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-teal:        #3ab5c8;
    --pl-amber:       #f5a623;
    --radius:         6px;
    --mono:           'Space Mono', 'Fira Mono', monospace;
}
body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', system-ui, sans-serif; margin: 0; padding: 0; }

.pl-nav { display:flex; align-items:center; gap:10px; padding:10px 16px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); }
.pl-nav-title { font-family:var(--mono); font-size:.8rem; color:var(--pl-text); flex:1; }
.pl-nav-btn { padding:6px 12px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); cursor:pointer; font-family:var(--mono); font-size:.7rem; display:inline-flex; align-items:center; gap:6px; background:var(--pl-surface); transition:all .2s; }
.pl-nav-btn.primary { color:#000; background:var(--pl-teal); border-color:var(--pl-teal); font-weight:bold; }
.pl-nav-btn.primary:hover:not(:disabled) { filter:brightness(1.1); }
.pl-nav-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.workspace { max-width:800px; margin:0 auto; padding:20px 12px 100px; }
.scene-block { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:18px; margin-bottom:20px; box-shadow:0 4px 20px rgba(0,0,0,.3); }
.scene-header { margin-bottom:14px; border-bottom:1px solid var(--pl-border); padding-bottom:12px; }
.scene-title { font-family:var(--mono); font-size:.82rem; color:var(--pl-teal); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:8px; }
.scene-sub { font-size:.78rem; color:var(--pl-text-dim); margin:6px 0 0; line-height:1.5; }
.kgn-step-tag { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:var(--pl-teal); color:#000; font-family:var(--mono); font-size:.68rem; font-weight:700; flex-shrink:0; }

.su-input { width:100%; background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; box-sizing:border-box; transition:border-color .2s; }
.su-input:focus { outline:none; border-color:var(--pl-teal); }

.su-ac-wrap { position:relative; margin-bottom: 4px; }
.su-ac-dropdown { position:absolute; top:100%; left:0; right:0; z-index:10; background:var(--pl-card); border:1px solid var(--pl-border); border-top:none; border-radius:0 0 4px 4px; max-height:200px; overflow-y:auto; display:none; box-shadow:0 4px 12px rgba(0,0,0,.5); }
.su-ac-item { padding:8px 12px; font-size:.8rem; cursor:pointer; transition:background .1s; border-bottom:1px solid var(--pl-border); display: flex; justify-content: space-between; }
.su-ac-item:hover { background:rgba(58,181,200,.1); color:var(--pl-teal); }

.kgn-pot { display:flex; flex-wrap:wrap; gap:6px; min-height:50px; padding:10px; border:1px dashed var(--pl-border); border-radius:var(--radius); margin-top:12px; }
.kgn-pot-empty { font-family:var(--mono); font-size:.72rem; color:var(--pl-text-dim); padding:6px; }
.beat-entity-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:12px; font-size:.75rem; font-family:var(--mono); border:1px solid var(--pl-teal); background:rgba(58,181,200,.1); color:var(--pl-teal); }
.chip-remove { background:none; border:none; cursor:pointer; color:inherit; opacity:.6; font-size:.8rem; padding:0; line-height:1; transition:opacity .15s; }
.chip-remove:hover { opacity:1; }

.radio-group { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
.radio-label { display: flex; align-items: flex-start; gap: 10px; background: var(--pl-surface); padding: 12px; border-radius: 6px; border: 1px solid var(--pl-border); cursor: pointer; transition: 0.2s; }
.radio-label:hover { border-color: var(--pl-teal); }
.radio-label input[type="radio"] { accent-color: var(--pl-teal); width: 16px; height: 16px; margin-top: 2px; }
.radio-text { font-size: 0.85rem; color: var(--pl-text); }
.radio-desc { font-size: 0.72rem; color: var(--pl-text-dim); font-family: var(--mono); margin-top: 4px; }
</style>

<div class="pl-nav">
    <span class="pl-nav-title"><i class="bi bi-box-arrow-up-right"></i> AG Map Run Exporter</span>
    <button class="pl-nav-btn primary" id="btnExport" onclick="MapRunApp.process()" disabled>
        <i class="bi bi-play-fill"></i> Execute
    </button>
</div>

<div class="workspace">
    <!-- Step 1 -->
    <div class="scene-block">
        <div class="scene-header">
            <h3 class="scene-title"><span class="kgn-step-tag">1</span> Select Map Runs</h3>
            <p class="scene-sub">Search by map_run ID, entity_type, or note. Add as many as you need.</p>
        </div>
        <div class="su-ac-wrap">
            <input type="text" id="runSearch" class="su-input" placeholder="Search map runs..." oninput="MapRunApp.searchRuns(this.value)">
            <div id="runAc" class="su-ac-dropdown"></div>
        </div>
        <div class="kgn-pot" id="runPot">
            <span class="kgn-pot-empty">No map runs selected. Search above to add them.</span>
        </div>
    </div>

    <!-- Step 2 -->
    <div class="scene-block">
        <div class="scene-header">
            <h3 class="scene-title"><span class="kgn-step-tag">2</span> Output Destination</h3>
            <p class="scene-sub">Choose what to do with the assembled sequence frames.</p>
        </div>
        <div class="radio-group">
            <label class="radio-label">
                <input type="radio" name="output_mode" value="database" checked>
                <div class="radio-text">
                    <strong>Write to Narrative Sequences (Database)</strong>
                    <div class="radio-desc">Creates a playable story sequence automatically. Instantly viewable. No manual authoring required.</div>
                </div>
            </label>
            <label class="radio-label">
                <input type="radio" name="output_mode" value="export">
                <div class="radio-text">
                    <strong>Export as JSON File</strong>
                    <div class="radio-desc">Downloads a flat JSON object mapping all frames to their underlying Lore Origins.</div>
                </div>
            </label>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>

<script>
window.MapRunApp = (function() {
    let selectedRuns = [];
    let searchTimeout = null;

    function searchRuns(q) {
        clearTimeout(searchTimeout);
        const ac = document.getElementById('runAc');
        if (!q.trim()) { ac.style.display = 'none'; return; }
        
        searchTimeout = setTimeout(() => {
            fetch('ag_map_run_export_api.php', { 
                method: 'POST', headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify({ action: 'search_map_runs', q }) 
            }).then(r => r.json()).then(res => {
                if (!res.ok || !res.results.length) { ac.style.display = 'none'; return; }
                ac.innerHTML = res.results.map(r => `
                    <div class="su-ac-item" onclick="MapRunApp.addRun(${r.id}, '${(r.note || r.entity_type || 'Run').replace(/'/g, "\\'")}')">
                        <span><strong style="color:var(--pl-teal);">#${r.id}</strong> — ${r.note || r.entity_type}</span>
                        <span style="color:var(--pl-text-dim); font-family:var(--mono); font-size:0.7rem;">${r.frame_count} frames</span>
                    </div>`).join('');
                ac.style.display = 'block';
            });
        }, 300);
    }

    function addRun(id, label) {
        id = parseInt(id, 10);
        if (!selectedRuns.find(r => r.id === id)) { selectedRuns.push({ id, label }); renderPot(); }
        document.getElementById('runSearch').value = '';
        document.getElementById('runAc').style.display = 'none';
    }

    function removeRun(id) {
        selectedRuns = selectedRuns.filter(r => r.id !== id);
        renderPot();
    }

    function renderPot() {
        const c = document.getElementById('runPot');
        document.getElementById('btnExport').disabled = selectedRuns.length === 0;
        if (selectedRuns.length === 0) { c.innerHTML = '<span class="kgn-pot-empty">No map runs selected.</span>'; return; }
        c.innerHTML = selectedRuns.map(r => `
            <span class="beat-entity-chip">
                <span>#${r.id} (${r.label})</span>
                <button class="chip-remove" onclick="MapRunApp.removeRun(${r.id})"><i class="bi bi-x"></i></button>
            </span>
        `).join('');
    }

    function process() {
        if (!selectedRuns.length) return;
        const btn = document.getElementById('btnExport');
        const mode = document.querySelector('input[name="output_mode"]:checked').value;
        const originalHtml = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';

        fetch('ag_map_run_export_api.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'process_runs', output_mode: mode, map_run_ids: selectedRuns.map(r => r.id) })
        })
        .then(r => {
            if (mode === 'export') {
                const cd = r.headers.get('content-disposition');
                let filename = 'ag_map_runs.json';
                if (cd && cd.includes('filename=')) filename = cd.split('filename=')[1].replace(/"/g, '');
                return r.blob().then(blob => ({ blob, filename, isFile: true }));
            }
            return r.json().then(data => ({ data, isFile: false }));
        })
        .then(res => {
            if (res.isFile) {
                const url = URL.createObjectURL(res.blob);
                const a = document.createElement('a'); a.href = url; a.download = res.filename;
                document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                if(window.Toast) Toast.show('Export downloaded!', 'success');
            } else {
                if (!res.data.ok) throw new Error(res.data.error);
                if(window.Toast) Toast.show('Saved to Database!', 'success');
                setTimeout(() => { window.location.href = `agnodeseq.php?id=${res.data.sequence_id}`; }, 1000);
            }
        })
        .catch(e => { if(window.Toast) Toast.show(e.message, 'error'); })
        .finally(() => { btn.disabled = false; btn.innerHTML = originalHtml; });
    }

    document.addEventListener('click', (e) => {
        const wrap = document.querySelector('.su-ac-wrap');
        if (wrap && !wrap.contains(e.target)) {
            const ac = document.getElementById('runAc');
            if (ac) ac.style.display = 'none';
        }
    });

    return { searchRuns, addRun, removeRun, process };
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>