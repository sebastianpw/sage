<?php
// public/kg_narrative.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "KG Narrative Export / Import";

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* Forge Design System — KG Narrative (same tokens as SketchUp module, teal-led instead of amber-led) */
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
.pl-nav-btn { padding:6px 12px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); text-decoration:none; transition:all .2s; background:var(--pl-surface); cursor:pointer; font-family:'Space Mono',monospace; font-size:.7rem; display:inline-flex; align-items:center; gap:6px; }
.pl-nav-btn:hover { color:var(--pl-teal); border-color:var(--pl-teal); }
.pl-nav-btn.primary { color:#000; background:var(--pl-teal); border-color:var(--pl-teal); font-weight:bold; }
.pl-nav-btn.primary:hover { filter:brightness(1.1); color:#000; }

/* Workspace & Cards */
.workspace { max-width:900px; margin:0 auto; padding:20px 12px 100px; }
.scene-block { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:18px; margin-bottom:20px; box-shadow:0 4px 20px rgba(0,0,0,.3); }
[data-theme="light"] .scene-block { box-shadow:0 2px 10px rgba(0,0,0,.08); }
.scene-header { margin-bottom:14px; border-bottom:1px solid var(--pl-border); padding-bottom:12px; }
.scene-title { font-family:'Space Mono',monospace; font-size:.82rem; color:var(--pl-teal); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:8px; }
.scene-sub { font-size:.78rem; color:var(--pl-text-dim); margin:6px 0 0; line-height:1.5; }

/* Steps */
.kgn-step-tag { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:var(--pl-teal); color:#000; font-family:'Space Mono',monospace; font-size:.68rem; font-weight:700; flex-shrink:0; }

/* Form inputs & Autocomplete */
.su-input { width:100%; background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; box-sizing:border-box; transition:border-color .2s; }
.su-input:focus { outline:none; border-color:var(--pl-teal); }
.su-ac-wrap { position:relative; margin-bottom: 4px; }
.su-ac-dropdown { position:absolute; top:100%; left:0; right:0; z-index:10; background:var(--pl-card); border:1px solid var(--pl-border); border-top:none; border-radius:0 0 4px 4px; max-height:200px; overflow-y:auto; display:none; box-shadow:0 4px 12px rgba(0,0,0,.5); }
.su-ac-item { padding:8px 12px; font-size:.8rem; cursor:pointer; transition:background .1s; border-bottom:1px solid var(--pl-border); }
.su-ac-item:hover { background:rgba(58,181,200,.1); color:var(--pl-teal); }
.su-ac-item:last-child { border-bottom:none; }

/* Focal node display */
.kgn-focal-box { display:flex; align-items:center; gap:10px; padding:10px 12px; background:rgba(58,181,200,.07); border:1px solid rgba(58,181,200,.3); border-radius:5px; margin-top:10px; }
.kgn-focal-box .icon { font-size:1.1rem; }
.kgn-focal-box .name { font-family:'Space Mono',monospace; font-size:.82rem; color:var(--pl-teal); font-weight:700; flex:1; }
.kgn-focal-box .clear-btn { background:none; border:1px solid var(--pl-border); border-radius:3px; color:var(--pl-text-dim); cursor:pointer; padding:3px 8px; font-size:.7rem; font-family:'Space Mono',monospace; }
.kgn-focal-box .clear-btn:hover { color:var(--pl-red); border-color:var(--pl-red); }

/* Mode select */
.kgn-mode-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
.kgn-mode-btn { flex:1; min-width:90px; padding:10px 8px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:5px; color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.7rem; cursor:pointer; text-align:center; transition:all .15s; }
.kgn-mode-btn:hover { border-color:var(--pl-teal); color:var(--pl-text); }
.kgn-mode-btn.active { background:rgba(58,181,200,.12); border-color:var(--pl-teal); color:var(--pl-teal); font-weight:700; }

/* Tree */
.kgn-tree-wrap { background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:var(--radius); max-height:280px; overflow-y:auto; padding:4px 0; margin-top:8px; }
.sk2-tree-node:hover { background:rgba(58,181,200,.05); }
.sk2-tree-node.is-checked .sk2-node-label { color: var(--pl-teal) !important; }

/* Pot */
.kgn-pot { display:flex; flex-wrap:wrap; gap:6px; min-height:44px; padding:8px; border:1px dashed var(--pl-border); border-radius:var(--radius); margin-top:8px; }
.kgn-pot-empty { font-family:'Space Mono',monospace; font-size:.72rem; color:var(--pl-text-dim); padding:6px; }
.beat-entity-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:12px; font-size:.75rem; font-family:'Space Mono',monospace; border:1px solid; }
.chip-kg { background:rgba(58,181,200,.1); border-color:var(--pl-teal); color:var(--pl-teal); }
.chip-kg.is-focal { background:rgba(245,166,35,.12); border-color:var(--pl-amber); color:var(--pl-amber); }
.chip-remove { background:none; border:none; cursor:pointer; color:inherit; opacity:.6; font-size:.8rem; padding:0; line-height:1; transition:opacity .15s; }
.chip-remove:hover { opacity:1; }

.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-surface); color:var(--pl-text-dim); }
.pl-btn-secondary:hover { border-color:var(--pl-teal); color:var(--pl-teal); }
.pl-btn-teal { border-color:var(--pl-teal); background:var(--pl-teal); color:#000; font-weight:700; }
.pl-btn-teal:hover { filter:brightness(1.1); }
.pl-btn-teal:disabled { opacity:.5; cursor:not-allowed; filter:none; }

.row-flex { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

.su-check-label { display:flex; align-items:center; gap:8px; font-size:.85rem; cursor:pointer; user-select:none; color:var(--pl-text); }
.su-check-label input[type="checkbox"] { accent-color:var(--pl-teal); width:16px; height:16px; cursor:pointer; }

/* Preview panel */
.kgn-preview-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:8px; margin-top:10px; }
@media (max-width: 520px) { .kgn-preview-grid { grid-template-columns:repeat(2, 1fr); } }
.kgn-preview-cell { background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:5px; padding:10px 8px; text-align:center; }
.kgn-preview-cell .num { font-family:'Space Mono',monospace; font-size:1.3rem; font-weight:700; color:var(--pl-teal); }
.kgn-preview-cell .lbl { font-family:'Space Mono',monospace; font-size:.62rem; color:var(--pl-text-dim); text-transform:uppercase; letter-spacing:1px; margin-top:2px; }
.kgn-warning-line { display:flex; align-items:flex-start; gap:6px; padding:7px 10px; background:rgba(245,166,35,.08); border:1px solid rgba(245,166,35,.25); border-radius:4px; font-size:.75rem; color:var(--pl-amber); margin-top:8px; line-height:1.4; }
.kgn-warning-line i { margin-top:1px; flex-shrink:0; }

/* Import panel */
.kgn-textarea { width:100%; min-height:160px; background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:10px 12px; font-family:'Space Mono',monospace; font-size:.75rem; box-sizing:border-box; resize:vertical; line-height:1.5; }
.kgn-textarea:focus { outline:none; border-color:var(--pl-teal); }
.kgn-validation-box { margin-top:10px; border-radius:5px; padding:10px 12px; font-size:.78rem; line-height:1.6; display:none; }
.kgn-validation-box.errors { background:rgba(240,80,96,.08); border:1px solid rgba(240,80,96,.3); color:var(--pl-red); }
.kgn-validation-box.ok { background:rgba(76,175,128,.08); border:1px solid rgba(76,175,128,.3); color:var(--pl-green); }
.kgn-validation-box ul { margin:6px 0 0; padding-left:18px; }
.kgn-import-preview-table { width:100%; border-collapse:collapse; margin-top:10px; font-size:.72rem; font-family:'Space Mono',monospace; }
.kgn-import-preview-table th, .kgn-import-preview-table td { border:1px solid var(--pl-border); padding:5px 7px; text-align:left; }
.kgn-import-preview-table th { background:var(--pl-surface); color:var(--pl-text-dim); text-transform:uppercase; font-size:.62rem; letter-spacing:.5px; }
.kgn-role-pill { display:inline-block; padding:1px 7px; border-radius:8px; background:rgba(58,181,200,.12); color:var(--pl-teal); font-size:.65rem; }

/* Modals (entity iframe) */
.su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.su-modal-backdrop.active { display:flex; }
.su-modal-box { width:100%; max-width:700px; height:85vh; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
.su-modal-header { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid var(--pl-border); background:var(--pl-surface); flex-shrink:0; }
.su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-amber); text-transform:uppercase; letter-spacing:1px; }
.su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
#entity-iframe { flex:1; border:none; width:100%; background:var(--pl-card); }

/* Section tabs (Export / Import) */
.kgn-tabs { display:flex; gap:0; border:1px solid var(--pl-border); border-radius:6px; overflow:hidden; margin-bottom:20px; }
.kgn-tab { flex:1; padding:10px 8px; background:transparent; border:none; color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.72rem; cursor:pointer; transition:all .2s; text-align:center; border-right:1px solid var(--pl-border); display:flex; align-items:center; justify-content:center; gap:6px; }
.kgn-tab:last-child { border-right:none; }
.kgn-tab:hover { background:rgba(58,181,200,.06); color:var(--pl-text); }
.kgn-tab.active { background:rgba(58,181,200,.12); color:var(--pl-teal); border-bottom:2px solid var(--pl-teal); }
.kgn-section { display:none; }
.kgn-section.active { display:block; }

/* Reading mode helper */
.kgn-lens-note {
    margin-top: 8px;
    font-size: .72rem;
    line-height: 1.45;
    color: var(--pl-text-dim);
    font-family: 'Space Mono', monospace;
}
</style>

<script>
window.KgNarrativeApp = (function() {
    let potNodes = [];          // [{id, name, node_type}]
    let focalNode = null;       // {id, name, node_type}
    let currentMode = 'manual'; // manual | 1hop | 2hop
    let narrativeMode = 'atlas'; // atlas | tour | story
    let kgTreeRaw = [], kgTreeFilter = '';
    const KG_TREE_OPEN_KEY = 'kgn_tree_open';
    let focalSearchTimeout = null;
    let importPayload = null;
    let lastValidatedItems = null;

    function setNarrativeMode(mode) {
        narrativeMode = mode;
        ['atlas','tour','story'].forEach(m => {
            const btn = document.getElementById('lensBtn-' + m);
            if (btn) btn.classList.toggle('active', m === mode);
        });
    }

    // ── Focal node search ──
    function searchFocal(q) {
        clearTimeout(focalSearchTimeout);
        const ac = document.getElementById('focalAc');
        if (!q.trim()) { ac.style.display = 'none'; return; }
        focalSearchTimeout = setTimeout(() => {
            fetch('kg_narrative_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'search_nodes', q}) })
            .then(r=>r.json()).then(res => {
                if (!res.ok || !res.results.length) { ac.style.display = 'none'; return; }
                ac.innerHTML = res.results.map(r => `
                    <div class="su-ac-item" onclick="KgNarrativeApp.setFocal(${r.id}, '${r.name.replace(/'/g, "\\'")}', '${r.node_type}')">
                        <span style="color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:0.7rem; margin-right:6px;">#${r.id}</span>
                        ${r.name} <span style="color:var(--pl-text-dim); font-size:.68rem;">(${r.node_type})</span>
                    </div>`).join('');
                ac.style.display = 'block';
            });
        }, 250);
    }

    function setFocal(id, name, nodeType) {
        focalNode = { id: parseInt(id,10), name, node_type: nodeType };
        document.getElementById('focalSearch').value = '';
        document.getElementById('focalAc').style.display = 'none';
        renderFocalBox();
        applyMode(currentMode);
    }

    function clearFocal() {
        focalNode = null;
        renderFocalBox();
    }

    function renderFocalBox() {
        const box = document.getElementById('focalBox');
        if (!focalNode) { box.style.display = 'none'; return; }
        box.style.display = 'flex';
        box.querySelector('.name').textContent = `#${focalNode.id} ${focalNode.name}`;
    }

    // ── Mode ──
    function setMode(mode) {
        currentMode = mode;
        ['manual','1hop','2hop'].forEach(m => {
            const btn = document.getElementById('modeBtn-' + m);
            if (btn) btn.classList.toggle('active', m === mode);
        });
        applyMode(mode);
    }

    function applyMode(mode) {
        if (mode === 'manual') return; // manual mode never auto-populates
        if (!focalNode) { if(window.Toast) Toast.show('Select a focal node first.', 'warn'); return; }
        fetch('kg_narrative_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'hop_preview', focal_id: focalNode.id, hop_mode: mode}) })
        .then(r=>r.json()).then(res => {
            if (!res.ok) { if(window.Toast) Toast.show(res.error || 'Failed to expand hops.', 'error'); return; }
            // Replace pot contents with hop result (focal node included)
            potNodes = res.nodes.map(n => ({ id: parseInt(n.id,10), name: n.name, node_type: n.node_type }));
            renderPot(); syncTreeChecks(); updatePreview();
        });
    }

    // ── Pot management ──
    function addToPot(node) {
        if (potNodes.find(n => n.id === node.id)) return;
        potNodes.push(node);
        renderPot(); syncTreeChecks(); updatePreview();
        if (typeof KgnGraph !== 'undefined') KgnGraph.onPotChanged();
    }

    function removeFromPot(id) {
        potNodes = potNodes.filter(n => n.id !== id);
        renderPot(); syncTreeChecks(); updatePreview();
        if (typeof KgnGraph !== 'undefined') KgnGraph.onPotChanged();
    }

    function isInPot(id) { return potNodes.some(n => n.id === id); }

    function renderPot() {
        const c = document.getElementById('kgnPot');
        if (!potNodes.length) { c.innerHTML = '<span class="kgn-pot-empty">No nodes selected yet — search a focal node, pick a mode, or browse the tree below.</span>'; return; }
        c.innerHTML = potNodes.map(n => {
            const isFocal = focalNode && focalNode.id === n.id;
            return `<span class="beat-entity-chip chip-kg ${isFocal ? 'is-focal' : ''}">
                <span style="cursor:pointer;" onclick="KgNarrativeApp.openEntityModal(${n.id}, '${n.name.replace(/'/g, "\\'")}')">${isFocal ? '\u2299 ' : ''}${n.name}</span>
                <button class="chip-remove" title="Mini Graph" onclick="KgNarrativeApp.openNodeGraph(${n.id})" type="button"><i class="bi bi-diagram-2-fill"></i></button>
                <button class="chip-remove" onclick="KgNarrativeApp.removeFromPot(${n.id})"><i class="bi bi-x"></i></button>
            </span>`;
        }).join('');
    }

    function openNodeGraph(nodeId) {
        if (typeof KgnGraph !== 'undefined' && KgnGraph.openModal) KgnGraph.openModal(parseInt(nodeId,10), 1);
    }

    function openEntityModal(entityId, label) {
        const url = `entity_form.php?entity_type=kg_nodes&entity_id=${encodeURIComponent(entityId)}&view=modal`;
        document.getElementById('entity-iframe').src = url;
        document.getElementById('entityModalTitle').textContent = label + ' — kg_nodes';
        document.getElementById('entity-modal-backdrop').classList.add('active');
    }
    function closeEntityModal() {
        document.getElementById('entity-modal-backdrop').classList.remove('active');
        document.getElementById('entity-iframe').src = 'about:blank';
    }

    // ── Tree ──
    function loadKgTree() {
        document.getElementById('kgnTreeWrap').innerHTML = '<div style="padding:14px;color:var(--pl-text-dim);">Loading tree...</div>';
        fetch('kg_narrative_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'fetch_tree'}) })
        .then(r=>r.json()).then(res => { if (res.ok) { kgTreeRaw = res.tree || []; renderKgTree(); } });
    }

    function kgNodeIcon(t) { const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' }; return map[t] || '📝'; }
    function kgTreeSaveOpenState() { const open = []; document.querySelectorAll('#kgnTreeWrap .sk2-tree-children.open').forEach(el => open.push(el.id.replace('kgn-kids-', ''))); try { localStorage.setItem(KG_TREE_OPEN_KEY, JSON.stringify(open)); } catch(e) {} }
    function kgTreeLoadOpenState() { try { const raw = localStorage.getItem(KG_TREE_OPEN_KEY); if (raw) return new Set(JSON.parse(raw)); } catch(e) {} return null; }

    function renderKgTree() {
        const wrap = document.getElementById('kgnTreeWrap');
        if (!kgTreeRaw.length) { wrap.innerHTML = '<div style="padding:10px;font-size:0.75rem;color:var(--pl-text-dim);">No nodes in graph.</div>'; return; }

        const checkedIds = new Set(potNodes.map(n => 'n_' + n.id)), filter = kgTreeFilter, childMap = {};
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

        // Recursively collect every descendant LEAF node's db_id under a given folder jsId.
        // Cached per render pass since buildLevel/state computation both need it.
        const descendantLeafCache = {};
        function collectDescendantLeafIds(jsId) {
            if (descendantLeafCache[jsId]) return descendantLeafCache[jsId];
            const ids = [];
            (childMap[jsId] || []).forEach(child => {
                if (child.type === 'folder') {
                    ids.push(...collectDescendantLeafIds(child.id));
                } else {
                    const dbId = (child.data && child.data.db_id) ? child.data.db_id : null;
                    if (dbId) ids.push(dbId);
                }
            });
            descendantLeafCache[jsId] = ids;
            return ids;
        }

        // Folder tristate: 'checked' if every descendant leaf is in the pot,
        // 'unchecked' if none are, 'indeterminate' otherwise. A folder with no
        // leaf descendants at all (only empty subfolders) is treated as unchecked.
        function folderState(jsId) {
            const leafIds = collectDescendantLeafIds(jsId);
            if (!leafIds.length) return 'unchecked';
            const checkedCount = leafIds.filter(id => checkedIds.has('n_' + id)).length;
            if (checkedCount === 0) return 'unchecked';
            if (checkedCount === leafIds.length) return 'checked';
            return 'indeterminate';
        }

        function buildLevel(parentId, depth) {
            const children = (childMap[parentId] || []).filter(n => !matchingJsIds || matchingJsIds.has(n.id));
            if (!children.length) return '';
            let html = '';
            children.forEach(node => {
                const isFolder = node.type === 'folder', jsId = node.id, isChecked = !isFolder && checkedIds.has(jsId);
                const hasKids = !!(childMap[jsId] && childMap[jsId].filter(c => !matchingJsIds || matchingJsIds.has(c.id)).length);
                const icon = isFolder ? '📁' : kgNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');
                const toggleBtn = (isFolder && hasKids) ? `<span class="sk2-node-toggle" data-jid="${jsId}" onclick="KgNarrativeApp.toggleFolder(this)" style="cursor:pointer; display:inline-block; width:15px; text-align:center; font-size:0.7rem; color:var(--pl-text-dim);">▶</span>` : `<span style="width:15px;display:inline-block;flex-shrink:0;"></span>`;
                const dbId = (node.data && node.data.db_id) ? node.data.db_id : '', nodeType = (node.data && node.data.node_type) ? node.data.node_type : 'note';

                let cbOrSpacer, rowChecked;
                if (isFolder) {
                    const fState = folderState(jsId);
                    rowChecked = fState === 'checked';
                    const leafIds = collectDescendantLeafIds(jsId);
                    cbOrSpacer = `<input type="checkbox" class="kgn-folder-cb" ${rowChecked ? 'checked' : ''} data-jid="${jsId}" data-leafids="${leafIds.join(',')}" data-name="${node.text.replace(/"/g, '&quot;')}" onchange="KgNarrativeApp.checkFolder(this)" style="accent-color:var(--pl-teal); cursor:pointer;">`;
                } else {
                    rowChecked = isChecked;
                    cbOrSpacer = `<input type="checkbox" ${isChecked ? 'checked' : ''} data-jid="${jsId}" data-dbid="${dbId}" data-name="${node.text.replace(/"/g, '&quot;')}" data-type="${nodeType}" onchange="KgNarrativeApp.checkNode(this)" style="accent-color:var(--pl-teal); cursor:pointer;">`;
                }

                html += `
                <div class="sk2-tree-node ${isFolder ? 'is-folder' : 'is-node'}${rowChecked ? ' is-checked' : ''}" style="padding-left:${8 + (depth * 12)}px; padding-top:4px; padding-bottom:4px; display:flex; align-items:center; gap:6px; font-size:0.8rem; user-select:none; ${isFolder ? 'font-weight:700;' : ''}">
                    ${toggleBtn} ${cbOrSpacer} <span class="sk2-node-icon">${icon}</span> <span class="sk2-node-label" style="${rowChecked ? 'color:var(--pl-teal);' : 'color:var(--pl-text);'}">${node.text}</span>
                </div>`;

                if (isFolder && hasKids) { html += `<div class="sk2-tree-children" id="kgn-kids-${jsId}" style="display:none;">${buildLevel(jsId, depth + 1)}</div>`; }
            });
            return html;
        }

        wrap.innerHTML = buildLevel('#', 0);

        // Apply indeterminate state to folder checkboxes (must be set via JS property, not an HTML attribute)
        wrap.querySelectorAll('.kgn-folder-cb').forEach(cb => {
            const jsId = cb.dataset.jid;
            cb.indeterminate = (folderState(jsId) === 'indeterminate');
        });

        if (!filter) {
            const savedOpen = kgTreeLoadOpenState();
            if (savedOpen && savedOpen.size > 0) {
                wrap.querySelectorAll('.sk2-tree-children').forEach(el => {
                    const jsId = el.id.replace('kgn-kids-', '');
                    if (savedOpen.has(jsId)) {
                        el.style.display = 'block'; el.classList.add('open');
                        const tEl = wrap.querySelector(`.sk2-node-toggle[data-jid="${jsId}"]`);
                        if (tEl) { tEl.style.transform = 'rotate(90deg)'; tEl.classList.add('open'); }
                    }
                });
            }
        }
    }

    function toggleFolder(btn) {
        const jsId = btn.dataset.jid, kids = document.getElementById('kgn-kids-' + jsId);
        if (!kids) return;
        const isOpen = kids.classList.contains('open');
        kids.style.display = isOpen ? 'none' : 'block'; kids.classList.toggle('open');
        btn.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)'; btn.classList.toggle('open');
        kgTreeSaveOpenState();
    }

    function checkNode(cb) {
        const dbId = parseInt(cb.dataset.dbid), name = cb.dataset.name, type = cb.dataset.type;
        if (currentMode !== 'manual') { setMode('manual'); }
        if (cb.checked) addToPot({ id: dbId, name, node_type: type }); else removeFromPot(dbId);
    }

    // Folder checkbox toggled: add or remove every descendant leaf node in one batch.
    // We derive the *intended* action from the folder's state immediately before
    // the click (read from data-leafids vs. current pot contents) rather than
    // trusting the native checkbox's post-click `checked` property — a folder
    // that was indeterminate or fully unchecked always means "check everything",
    // a folder that was fully checked always means "uncheck everything". This
    // matches standard tristate-checkbox UX and avoids relying on browser-specific
    // indeterminate->checked transition quirks.
    function checkFolder(cb) {
        const leafIds = (cb.dataset.leafids || '').split(',').filter(Boolean).map(s => parseInt(s, 10));
        if (!leafIds.length) { cb.checked = false; cb.indeterminate = false; return; }

        const checkedCountBefore = leafIds.filter(id => isInPot(id)).length;
        const wasFullyChecked = checkedCountBefore === leafIds.length;
        const shouldCheckAll = !wasFullyChecked; // indeterminate or fully-unchecked -> check all; fully-checked -> uncheck all

        if (currentMode !== 'manual') { currentMode = 'manual'; ['manual','1hop','2hop'].forEach(m => { const b = document.getElementById('modeBtn-' + m); if (b) b.classList.toggle('active', m === 'manual'); }); }

        if (shouldCheckAll) {
            // Need node names/types for chips — pull them from the tree's checkbox rows already in the DOM
            leafIds.forEach(dbId => {
                if (isInPot(dbId)) return;
                const leafCb = document.querySelector(`#kgnTreeWrap input[data-dbid="${dbId}"]:not(.kgn-folder-cb)`);
                const name = leafCb ? leafCb.dataset.name : ('Node #' + dbId);
                const type = leafCb ? leafCb.dataset.type : 'note';
                potNodes.push({ id: dbId, name, node_type: type });
            });
        } else {
            const leafIdSet = new Set(leafIds);
            potNodes = potNodes.filter(n => !leafIdSet.has(n.id));
        }

        renderPot(); updatePreview();
        if (typeof KgnGraph !== 'undefined') KgnGraph.onPotChanged();
        // Defer the tree rebuild so the browser finishes its own native checkbox
        // change-event handling first; rebuilding synchronously here can replace
        // the very element whose event is still being processed on some mobile browsers.
        setTimeout(renderKgTree, 0);
    }

    function syncTreeChecks() {
        // Leaf nodes: simple checked/unchecked sync
        document.querySelectorAll('#kgnTreeWrap input[type=checkbox]:not(.kgn-folder-cb)').forEach(cb => {
            const dbId = parseInt(cb.dataset.dbid);
            const checked = isInPot(dbId);
            cb.checked = checked;
            cb.closest('.sk2-tree-node')?.classList.toggle('is-checked', checked);
            const lbl = cb.closest('.sk2-tree-node')?.querySelector('.sk2-node-label');
            if (lbl) lbl.style.color = checked ? 'var(--pl-teal)' : 'var(--pl-text)';
        });
        // Folder nodes: tristate sync based on their descendant leaf set
        document.querySelectorAll('#kgnTreeWrap .kgn-folder-cb').forEach(cb => {
            const leafIds = (cb.dataset.leafids || '').split(',').filter(Boolean).map(s => parseInt(s, 10));
            const checkedCount = leafIds.filter(id => isInPot(id)).length;
            const allChecked = leafIds.length > 0 && checkedCount === leafIds.length;
            const noneChecked = checkedCount === 0;
            cb.checked = allChecked;
            cb.indeterminate = !allChecked && !noneChecked;
            cb.closest('.sk2-tree-node')?.classList.toggle('is-checked', allChecked);
            const lbl = cb.closest('.sk2-tree-node')?.querySelector('.sk2-node-label');
            if (lbl) lbl.style.color = allChecked ? 'var(--pl-teal)' : 'var(--pl-text)';
        });
    }

    // ── Export preview ──
    function buildConfig() {
        return {
            focal_kg_node_id: focalNode ? focalNode.id : 0,
            hop_mode: currentMode,
            reading_mode: narrativeMode,
            manual_node_ids: potNodes.map(n => n.id),
            include_edges: document.getElementById('kgnIncludeEdges').checked
        };
    }

    function updatePreview() {
        const config = buildConfig();
        if (!potNodes.length) {
            document.getElementById('kgnPreviewGrid').style.display = 'none';
            document.getElementById('kgnWarnings').innerHTML = '';
            document.getElementById('btnExportJson').disabled = true;
            return;
        }
        fetch('kg_narrative_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'export_preview', config}) })
        .then(r=>r.json()).then(res => {
            if (!res.ok) return;
            document.getElementById('kgnPreviewGrid').style.display = 'grid';
            document.getElementById('cellNodes').textContent = res.counts.nodes;
            document.getElementById('cellEdges').textContent = res.counts.edges;
            document.getElementById('cellSketches').textContent = res.counts.sketches;
            document.getElementById('cellFrames').textContent = res.counts.frames;

            const warnEl = document.getElementById('kgnWarnings');
            let warnings = [];
            if (res.warnings.nodes_without_sketch.length) warnings.push(`${res.warnings.nodes_without_sketch.length} node(s) have no matching sketch.`);
            if (res.warnings.sketches_without_frame.length) warnings.push(`${res.warnings.sketches_without_frame.length} sketch(es) have no resolvable frame.`);
            if (res.warnings.over_node_threshold) warnings.push('Node count exceeds the recommended 25 — consider narrowing your selection.');
            if (res.warnings.over_sketch_threshold) warnings.push('Sketch count exceeds the recommended 15 — coherence may suffer.');
            if (res.warnings.over_frame_threshold) warnings.push('Frame count exceeds the recommended 40.');
            warnEl.innerHTML = warnings.map(w => `<div class="kgn-warning-line"><i class="bi bi-exclamation-triangle-fill"></i> ${w}</div>`).join('');

            document.getElementById('btnExportJson').disabled = false;
        });
    }

    function exportJson() {
        const config = buildConfig();
        if (!potNodes.length) { if(window.Toast) Toast.show('Select at least one node before exporting.', 'warn'); return; }
        const btn = document.getElementById('btnExportJson');
        const original = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';

        fetch('kg_narrative_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'export', config}) })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            const cd = r.headers.get('content-disposition');
            let filename = 'kg_narrative_export.json';
            if (cd && cd.includes('filename=')) filename = cd.split('filename=')[1].replace(/"/g, '');
            return r.blob().then(blob => ({blob, filename}));
        })
        .then(({blob, filename}) => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href = url; a.download = filename;
            document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
            if(window.Toast) Toast.show('Export successful! Paste this JSON to your AI of choice.', 'success');
        })
        .catch(e => { if(window.Toast) Toast.show(e.message, 'error'); })
        .finally(() => { btn.disabled = false; btn.innerHTML = original; });
    }

    // ── Import ──
    function validateImport() {
        const raw = document.getElementById('importTextarea').value.trim();
        const box = document.getElementById('importValidationBox');
        const previewWrap = document.getElementById('importPreviewWrap');
        previewWrap.style.display = 'none';
        document.getElementById('btnSaveSequence').disabled = true;
        lastValidatedItems = null;

        if (!raw) { box.style.display = 'none'; return; }

        let parsed;
        try { parsed = JSON.parse(raw); }
        catch(e) {
            box.className = 'kgn-validation-box errors'; box.style.display = 'block';
            box.innerHTML = `<strong><i class="bi bi-x-circle-fill"></i> Invalid JSON</strong><div style="margin-top:4px;">${e.message}</div>`;
            return;
        }
        importPayload = parsed;

        fetch('kg_narrative_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'validate_import', payload: parsed}) })
        .then(r=>r.json()).then(res => {
            box.style.display = 'block';
            if (!res.ok) {
                box.className = 'kgn-validation-box errors';
                box.innerHTML = `<strong><i class="bi bi-x-circle-fill"></i> ${res.errors.length} error(s) found</strong><ul>${res.errors.map(e=>`<li>${e}</li>`).join('')}</ul>`;
                return;
            }
            lastValidatedItems = res.items;
            let html = `<strong><i class="bi bi-check-circle-fill"></i> Valid — ${res.items.length} item(s) ready to import</strong>`;
            if (res.warnings.length) html += `<ul>${res.warnings.map(w=>`<li>${w}</li>`).join('')}</ul>`;
            box.className = 'kgn-validation-box ok';
            box.innerHTML = html;

            renderImportPreview(res.items);
            document.getElementById('btnSaveSequence').disabled = false;
        });
    }

    function renderImportPreview(items) {
        const wrap = document.getElementById('importPreviewWrap');
        const tbody = document.getElementById('importPreviewBody');
        tbody.innerHTML = items.map((it,i) => `
            <tr>
                <td>${i+1}</td>
                <td>${it.kg_node_id ?? '—'}</td>
                <td>${it.sketch_id ?? '—'}</td>
                <td>${it.frame_id ?? '—'}</td>
                <td>${it.role ? `<span class="kgn-role-pill">${it.role}</span>` : '—'}</td>
                <td style="color:var(--pl-text-dim);">${it.reason ?? ''}</td>
            </tr>`).join('');
        wrap.style.display = 'block';
    }

    function saveSequence() {
        if (!lastValidatedItems) return;
        const name = document.getElementById('importSeqName').value.trim() || (importPayload && importPayload.sequence_name) || 'Untitled Sequence';
        const desc = document.getElementById('importSeqDesc').value.trim() || (importPayload && importPayload.sequence_description) || null;

        const btn = document.getElementById('btnSaveSequence');
        const original = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

        fetch('kg_narrative_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'save_sequence', payload: importPayload, sequence_name: name, sequence_description: desc}) })
        .then(r=>r.json()).then(res => {
            if (!res.ok) {
                if(window.Toast) Toast.show((res.errors && res.errors[0]) || res.error || 'Save failed.', 'error');
                return;
            }
            if(window.Toast) Toast.show(`Saved as narrative_sequences #${res.id}`, 'success');
        })
        .catch(e => { if(window.Toast) Toast.show(e.message, 'error'); })
        .finally(() => { btn.disabled = false; btn.innerHTML = original; });
    }

    // ── Section tabs ──
    function setSection(section) {
        ['export','import'].forEach(s => {
            document.getElementById('kgnTab-' + s).classList.toggle('active', s === section);
            document.getElementById('kgnSection-' + s).classList.toggle('active', s === section);
        });
    }

    return {
        searchFocal, setFocal, clearFocal, setMode,
        setNarrativeMode,
        addToPot, removeFromPot, isInPot, openNodeGraph,
        openEntityModal, closeEntityModal,
        loadKgTree, toggleFolder, checkNode, checkFolder,
        setKgTreeFilter: (f) => { kgTreeFilter = f.trim().toLowerCase(); renderKgTree(); },
        updatePreview, exportJson,
        validateImport, saveSequence,
        setSection,
        _getPotNodes: () => potNodes
    };
})();
</script>

<!-- Nav -->
<div class="pl-nav">
    <span class="pl-nav-title"><i class="bi bi-signpost-split"></i> KG Narrative Export / Import</span>
    <button class="pl-nav-btn primary" id="btnExportJson" onclick="KgNarrativeApp.exportJson()" disabled title="Export JSON">
        <i class="bi bi-braces"></i> Export JSON
    </button>
</div>

<div class="workspace">

    <div class="kgn-tabs">
        <button class="kgn-tab active" id="kgnTab-export" onclick="KgNarrativeApp.setSection('export')">
            <i class="bi bi-upload"></i> Export to AI
        </button>
        <button class="kgn-tab" id="kgnTab-import" onclick="KgNarrativeApp.setSection('import')">
            <i class="bi bi-download"></i> Import from AI
        </button>
    </div>

    <!-- ══════════════════════ EXPORT SECTION ══════════════════════ -->
    <div class="kgn-section active" id="kgnSection-export">

        <!-- Step 1: Focal node -->
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><span class="kgn-step-tag">1</span> Focal Node</h3>
                <p class="scene-sub">Pick the center of your reading-sequence subgraph. Everything else is built outward from here.</p>
            </div>
            <div class="su-ac-wrap">
                <input type="text" id="focalSearch" class="su-input" placeholder="Search KG node by ID or name..." oninput="KgNarrativeApp.searchFocal(this.value)">
                <div id="focalAc" class="su-ac-dropdown"></div>
            </div>
            <div class="kgn-focal-box" id="focalBox" style="display:none;">
                <span class="icon">⦿</span>
                <span class="name"></span>
                <button class="clear-btn" onclick="KgNarrativeApp.clearFocal()">Clear</button>
            </div>
        </div>

        <!-- Step 2: Mode -->
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><span class="kgn-step-tag">2</span> Selection Mode</h3>
                <p class="scene-sub">Hop range is capped at 2 — larger exports get too broad for coherent AI reading-sequence generation.</p>
            </div>
            <div class="kgn-mode-row">
                <button class="kgn-mode-btn active" id="modeBtn-manual" onclick="KgNarrativeApp.setMode('manual')"><i class="bi bi-hand-index-thumb"></i><br>Manual</button>
                <button class="kgn-mode-btn" id="modeBtn-1hop" onclick="KgNarrativeApp.setMode('1hop')"><i class="bi bi-share"></i><br>1 Hop</button>
                <button class="kgn-mode-btn" id="modeBtn-2hop" onclick="KgNarrativeApp.setMode('2hop')"><i class="bi bi-diagram-3"></i><br>2 Hops</button>
            </div>

            <div style="margin-top:14px; padding-top:12px; border-top:1px solid var(--pl-border);">
                <div class="scene-title" style="font-size:.76rem; margin-bottom:8px;"><i class="bi bi-compass"></i> Reading Lens</div>
                <div class="kgn-mode-row">
                    <button class="kgn-mode-btn active" id="lensBtn-atlas" onclick="KgNarrativeApp.setNarrativeMode('atlas')"><i class="bi bi-map"></i><br>Atlas</button>
                    <button class="kgn-mode-btn" id="lensBtn-tour" onclick="KgNarrativeApp.setNarrativeMode('tour')"><i class="bi bi-signpost-2"></i><br>Tour</button>
                    <button class="kgn-mode-btn" id="lensBtn-story" onclick="KgNarrativeApp.setNarrativeMode('story')"><i class="bi bi-book"></i><br>Story</button>
                </div>
                <div class="kgn-lens-note">
                    Atlas = broad orientation and reference. Tour = guided spatial learning path. Story = stronger narrative ordering.
                </div>
            </div>
        </div>

        <!-- Step 3: Pot -->
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><span class="kgn-step-tag">3</span> Export Pot</h3>
                <p class="scene-sub">Tap a chip's name to view it, the graph icon to open the mini graph and add/remove neighbors, or × to remove it.</p>
            </div>
            <div class="kgn-pot" id="kgnPot"></div>
        </div>

        <!-- Step 4: Tree browser -->
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><span class="kgn-step-tag">4</span> Browse &amp; Curate</h3>
                <p class="scene-sub">Manually add or remove nodes via the tree. Checking a box here switches you to Manual mode.</p>
            </div>
            <input type="text" id="kgnTreeSearch" class="su-input" placeholder="Filter nodes in tree…">
            <div class="kgn-tree-wrap" id="kgnTreeWrap"></div>
        </div>

        <!-- Step 5: Options + Preview -->
        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><span class="kgn-step-tag">5</span> Export Preview</h3>
            </div>
            <label class="su-check-label">
                <input type="checkbox" id="kgnIncludeEdges" checked onchange="KgNarrativeApp.updatePreview()">
                Include relationship edges between selected nodes
            </label>

            <div class="kgn-preview-grid" id="kgnPreviewGrid" style="display:none;">
                <div class="kgn-preview-cell"><div class="num" id="cellNodes">0</div><div class="lbl">Nodes</div></div>
                <div class="kgn-preview-cell"><div class="num" id="cellEdges">0</div><div class="lbl">Edges</div></div>
                <div class="kgn-preview-cell"><div class="num" id="cellSketches">0</div><div class="lbl">Sketches</div></div>
                <div class="kgn-preview-cell"><div class="num" id="cellFrames">0</div><div class="lbl">Frames</div></div>
            </div>
            <div id="kgnWarnings"></div>
        </div>

    </div>

    <!-- ══════════════════════ IMPORT SECTION ══════════════════════ -->
    <div class="kgn-section" id="kgnSection-import">

        <div class="scene-block">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-clipboard-data"></i> Paste AI-Generated Sequence JSON</h3>
                <p class="scene-sub">Paste the narrative sequence JSON your AI produced from the export bundle. It will be validated against the database before saving.</p>
            </div>
            <textarea id="importTextarea" class="kgn-textarea" placeholder='{
  "sequence_name": "...",
  "sequence_description": "...",
  "items": [
    { "kg_node_id": 805, "sketch_id": 8369, "frame_id": 48328, "role": "anchor", "reason": "..." }
  ]
}'></textarea>
            <div class="row-flex" style="margin-top:10px;">
                <button class="pl-btn pl-btn-teal" onclick="KgNarrativeApp.validateImport()"><i class="bi bi-check2-square"></i> Validate</button>
            </div>
            <div class="kgn-validation-box" id="importValidationBox"></div>
        </div>

        <div class="scene-block" id="importPreviewWrap" style="display:none;">
            <div class="scene-header">
                <h3 class="scene-title"><i class="bi bi-table"></i> Sequence Preview</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="kgn-import-preview-table">
                    <thead><tr><th>#</th><th>KG Node</th><th>Sketch</th><th>Frame</th><th>Role</th><th>Reason</th></tr></thead>
                    <tbody id="importPreviewBody"></tbody>
                </table>
            </div>

            <div style="margin-top:16px; padding-top:14px; border-top:1px solid var(--pl-border);">
                <div class="scene-title" style="margin-bottom:10px;"><i class="bi bi-save"></i> Save to narrative_sequences</div>
                <input type="text" id="importSeqName" class="su-input" placeholder="Sequence name (defaults to AI-provided name)" style="margin-bottom:8px;">
                <textarea id="importSeqDesc" class="su-input" placeholder="Sequence description (optional)" style="min-height:60px; resize:vertical; margin-bottom:10px;"></textarea>
                <button class="pl-btn pl-btn-teal" id="btnSaveSequence" onclick="KgNarrativeApp.saveSequence()" disabled><i class="bi bi-save"></i> Save Sequence</button>
            </div>
        </div>

    </div>

</div>

<!-- Mini graph modal -->
<?php include __DIR__ . '/kg_narrative_graph.php'; ?>

<!-- Entity Details Modal -->
<div class="su-modal-backdrop" id="entity-modal-backdrop" onmousedown="if(event.target===this)KgNarrativeApp.closeEntityModal()">
    <div class="su-modal-box">
        <div class="su-modal-header">
            <span class="su-modal-title" id="entityModalTitle">Entity Details</span>
            <button class="su-modal-close" onclick="KgNarrativeApp.closeEntityModal()">&#10005;</button>
        </div>
        <iframe id="entity-iframe" src="about:blank"></iframe>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (e) => {
        const wrap = document.querySelector('.su-ac-wrap');
        if (wrap && !wrap.contains(e.target)) {
            const ac = document.getElementById('focalAc');
            if (ac) ac.style.display = 'none';
        }
    });

    const treeSearch = document.getElementById('kgnTreeSearch');
    if (treeSearch) treeSearch.addEventListener('input', e => KgNarrativeApp.setKgTreeFilter(e.target.value));

    KgNarrativeApp.loadKgTree();
});
</script>

<?php
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>