<?php
// public/kg_filter.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Knowledge Graph Filter";

ob_start();
?>
<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch(e) {}
})();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
    --staging-accent:#7c3aed;
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    --staging-accent:#a78bfa;
}
@media (prefers-color-scheme:dark) {
    :root:not([data-theme="light"]) {
        --bg:#0d1117; --card:#161b22; --border:#30363d;
        --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
        --staging-accent:#a78bfa;
    }
}

* { box-sizing: border-box; }
body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; min-height:100vh; }

/* ── Top bar ── */
.kgf-topbar {
    height: 52px; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; padding: 0 16px; gap: 10px;
    position: sticky; top: 0; z-index: 50;
}
.kgf-topbar h2 { margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 8px; flex: 1; }
.kgf-topbar-right { display: flex; gap: 6px; align-items: center; }

/* ── Tabs ── */
.kgf-tabs {
    display: flex; border-bottom: 1px solid var(--border);
    padding: 0 16px; background: var(--card); gap: 0;
    position: sticky; top: 52px; z-index: 49;
}
.kgf-tab {
    padding: 11px 18px; font-size: 0.88rem; font-weight: 600;
    color: var(--text-muted); cursor: pointer;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
    background: none; border-top: none; border-left: none; border-right: none;
    white-space: nowrap; display: flex; align-items: center; gap: 7px;
}
.kgf-tab:hover { color: var(--text); }
.kgf-tab.active-live     { color: var(--accent);          border-bottom-color: var(--accent); }
.kgf-tab.active-staging  { color: var(--staging-accent);  border-bottom-color: var(--staging-accent); }

.kgf-staging-badge {
    font-size: 0.62rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
    padding: 1px 6px; border-radius: 8px;
    background: rgba(124,58,237,0.12); color: var(--staging-accent);
    border: 1px solid rgba(124,58,237,0.3);
}

/* ── Main content ── */
.kgf-body {
    max-width: 680px; margin: 0 auto;
    padding: 16px;
    display: flex; flex-direction: column; gap: 12px;
    min-height: calc(100vh - 52px - 46px);
}

/* ── Info card ── */
.kgf-info {
    padding: 11px 14px;
    border-radius: 8px;
    font-size: 0.83rem;
    line-height: 1.55;
    color: var(--text-muted);
    border: 1px solid var(--border);
    background: var(--card);
    display: flex; align-items: flex-start; gap: 9px;
}
.kgf-info .icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

/* ── Picker header ── */
.kgf-picker-header {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 14px 7px;
    border-bottom: 1px solid var(--border);
    background: var(--card);
    border-radius: 8px 8px 0 0;
    border: 1px solid var(--border);
    border-bottom: none;
}
.kgf-picker-header span {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--text-muted); flex: 1;
}
.kgf-picker-actions { display: flex; gap: 8px; align-items: center; }
.kgf-select-btn {
    background: none; border: none; color: var(--accent);
    font-size: 0.75rem; cursor: pointer; padding: 0; font-weight: 600;
}
.kgf-select-btn:hover { text-decoration: underline; }
.kgf-select-btn.danger { color: var(--red); }

/* ── Tree wrap ── */
.kgf-tree-wrap {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 0 0 8px 8px;
    overflow-y: auto;
    max-height: calc(100vh - 280px);
    min-height: 120px;
    padding: 4px 0;
}
.kgf-picker-loading {
    padding: 28px; text-align: center;
    color: var(--text-muted); font-size: 0.85rem;
}

/* ── Tree rows ── */
.kgf-tree-node {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 10px; cursor: pointer; user-select: none;
    transition: background 0.1s; font-size: 0.86rem;
    border-radius: 4px; margin: 1px 4px;
}
.kgf-tree-node:hover { background: rgba(59,130,246,0.07); }
.kgf-tree-node input[type=checkbox] {
    width: 14px; height: 14px; accent-color: var(--accent);
    cursor: pointer; flex-shrink: 0; margin: 0;
}
.kgf-tree-node .kgf-node-toggle {
    width: 16px; height: 16px; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.65rem; color: var(--text-muted); cursor: pointer;
    border-radius: 3px; transition: background 0.1s, transform 0.15s;
}
.kgf-tree-node .kgf-node-toggle:hover { background: rgba(59,130,246,0.12); }
.kgf-tree-node .kgf-node-toggle.open { transform: rotate(90deg); }
.kgf-tree-node .kgf-node-icon { font-size: 0.85rem; flex-shrink: 0; opacity: 0.75; }
.kgf-tree-node .kgf-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kgf-tree-node.is-folder > .kgf-node-label { font-weight: 600; color: var(--text); }
.kgf-tree-node.is-node   > .kgf-node-label { color: var(--text-muted); }
.kgf-tree-children { display: none; }
.kgf-tree-children.open { display: block; }
.kgf-tree-node input[type=checkbox]:indeterminate { opacity: 0.7; }

/* ── Footer bar ── */
.kgf-footer {
    position: sticky; bottom: 0;
    background: var(--card); border-top: 1px solid var(--border);
    padding: 12px 16px;
    display: flex; align-items: center; gap: 10px;
    z-index: 49;
}
.kgf-footer-count { flex: 1; font-size: 0.82rem; color: var(--text-muted); }
.kgf-status-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
    background: var(--border); display: inline-block; margin-right: 4px;
}
.kgf-status-dot.active { background: var(--orange); }

/* ── Buttons ── */
.btn {
    padding: 8px 14px; border-radius: 7px; border: none; cursor: pointer;
    font-weight: 600; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 6px;
    white-space: nowrap; transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.btn-primary  { background: var(--accent); color: #fff; }
.btn-primary:hover { opacity: 0.9; }
.btn-staging  { background: var(--staging-accent); color: #fff; }
.btn-staging:hover { opacity: 0.88; }
.btn-ghost    { background: transparent; border: 1px solid var(--border); color: var(--text); }
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm { padding: 5px 10px; font-size: 0.78rem; }

/* ── Toast ── */
#kgf-toast {
    position: fixed; bottom: 72px; right: 16px; z-index: 99999;
    background: var(--card); color: var(--text); border: 1px solid var(--border);
    border-left: 4px solid var(--green); border-radius: 6px;
    padding: 10px 16px; font-size: 0.88rem;
    display: none; box-shadow: 0 4px 12px rgba(0,0,0,.2);
}
</style>

<!-- ═══════ LAYOUT ═══════ -->

<div class="kgf-topbar">
    <h2><i class="bi bi-funnel-fill" style="color:var(--accent);"></i> Graph Node Filter</h2>
    <div class="kgf-topbar-right">
        <button class="btn btn-ghost btn-sm" id="btn-launch" onclick="launchGraph()">
            <i class="bi bi-diagram-3-fill"></i> <span id="btn-launch-label">Open Graph</span>
        </button>
    </div>
</div>

<div class="kgf-tabs">
    <button class="kgf-tab active-live" id="tab-live" onclick="switchMode('live')">
        <i class="bi bi-diagram-3-fill"></i> Live Graph
    </button>
    <button class="kgf-tab" id="tab-staging" onclick="switchMode('staging')">
        <i class="bi bi-diagram-3"></i> Staging Graph
        <span class="kgf-staging-badge">Staging</span>
    </button>
</div>

<div class="kgf-body">
    <div class="kgf-info" id="kgf-info">
        <span class="icon">💡</span>
        <span id="kgf-info-text">
            Select which categories and nodes to include when the <strong>Live Graph</strong> opens.
            Your selection is saved automatically and applied on graph load — the graph never renders nodes you've excluded.
        </span>
    </div>

    <div>
        <div class="kgf-picker-header">
            <span id="kgf-tree-label">Categories &amp; Nodes</span>
            <div class="kgf-picker-actions">
                <button class="kgf-select-btn" onclick="kgfToggleAll()">Check all</button>
                <button class="kgf-select-btn danger" onclick="kgfUncheckAll()">Uncheck all</button>
            </div>
        </div>
        <div class="kgf-tree-wrap" id="kgf-tree-wrap">
            <div class="kgf-picker-loading">Loading…</div>
        </div>
    </div>
</div>

<div class="kgf-footer">
    <span class="kgf-footer-count">
        <span class="kgf-status-dot" id="kgf-dot"></span>
        <span id="kgf-count-text">—</span>
    </span>
    <button class="btn btn-ghost btn-sm" onclick="kgfSaveFilter()">
        <i class="bi bi-floppy"></i> Save
    </button>
    <button class="btn btn-primary btn-sm" id="btn-launch-footer" onclick="launchGraph()">
        <i class="bi bi-diagram-3-fill"></i> <span id="btn-launch-footer-label">Open Live Graph</span>
    </button>
</div>

<div id="kgf-toast"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// ══════════════════════════════════════════════
// CONFIG — per-mode settings
// ══════════════════════════════════════════════
const MODES = {
    live: {
        api:        'kg_api.php',
        graphUrl:   'kg_graph.php',
        storageKey: 'kg_live_graph_filter',
        openKey:    'kg_live_graph_filter_open',
        label:      'Live Graph',
        btnClass:   'btn-primary',
        tabClass:   'active-live',
        accent:     'var(--accent)',
    },
    staging: {
        api:        'kg_staging_api.php',
        graphUrl:   'kg_staging_graph.php',
        storageKey: 'kg_staging_graph_filter',
        openKey:    'kg_staging_graph_filter_open',
        label:      'Staging Graph',
        btnClass:   'btn-staging',
        tabClass:   'active-staging',
        accent:     'var(--staging-accent)',
    },
};

let currentMode = 'live';
let kgfRawTree  = [];
let kgfChecked  = new Set();

// ══════════════════════════════════════════════
// MODE SWITCHING
// ══════════════════════════════════════════════
function switchMode(mode) {
    currentMode = mode;
    const cfg = MODES[mode];

    // Tab styles
    document.getElementById('tab-live').className    = 'kgf-tab' + (mode === 'live'    ? ' active-live'    : '');
    document.getElementById('tab-staging').className = 'kgf-tab' + (mode === 'staging' ? ' active-staging' : '');

    // Info text
    document.getElementById('kgf-info-text').innerHTML =
        `Select which categories and nodes to include when the <strong>${cfg.label}</strong> opens.
         Your selection is saved automatically and applied on graph load — the graph never renders nodes you've excluded.`;

    // Launch buttons
    document.getElementById('btn-launch-label').textContent        = 'Open ' + cfg.label;
    document.getElementById('btn-launch-footer-label').textContent = 'Open ' + cfg.label;

    const footerBtn = document.getElementById('btn-launch-footer');
    footerBtn.className = 'btn btn-sm ' + cfg.btnClass;

    // Reload tree for this mode
    kgfLoadTree();
}

// ══════════════════════════════════════════════
// LOCALSTORAGE HELPERS
// ══════════════════════════════════════════════
function kgfLoadFromStorage() {
    try {
        const raw = localStorage.getItem(MODES[currentMode].storageKey);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) return new Set(parsed);
        }
    } catch(e) {}
    return null; // null = all
}

function kgfSaveToStorage(ids) {
    try {
        if (ids === null) {
            localStorage.removeItem(MODES[currentMode].storageKey);
        } else {
            localStorage.setItem(MODES[currentMode].storageKey, JSON.stringify(Array.from(ids)));
        }
    } catch(e) {}
}

function kgfSaveOpenState() {
    const open = [];
    document.querySelectorAll('#kgf-tree-wrap .kgf-tree-children.open').forEach(el => {
        open.push(el.id.replace('kgfkids-', ''));
    });
    try { localStorage.setItem(MODES[currentMode].openKey, JSON.stringify(open)); } catch(e) {}
}

function kgfLoadOpenState() {
    try {
        const raw = localStorage.getItem(MODES[currentMode].openKey);
        if (raw) return new Set(JSON.parse(raw));
    } catch(e) {}
    return null; // null = all open
}

// ══════════════════════════════════════════════
// TREE LOAD
// ══════════════════════════════════════════════
function kgfLoadTree() {
    const wrap = document.getElementById('kgf-tree-wrap');
    wrap.innerHTML = '<div class="kgf-picker-loading">Loading…</div>';

    fetch(MODES[currentMode].api + '?action=fetch_tree')
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { wrap.innerHTML = '<div class="kgf-picker-loading">Failed to load tree.</div>'; return; }
            kgfRawTree = res.tree;

            // Restore checked state from localStorage
            const stored = kgfLoadFromStorage();
            if (stored !== null) {
                kgfChecked = new Set();
                // Seed leaf nodes
                kgfRawTree.forEach(n => {
                    if (n.type === 'node' && stored.has(n.data.db_id.toString())) {
                        kgfChecked.add(n.id);
                    }
                });
                // Bottom-up folder sync (deepest first)
                const folders = kgfRawTree.filter(n => n.type === 'folder');
                folders.sort((a, b) => getFolderDepth(b.id) - getFolderDepth(a.id));
                folders.forEach(folder => {
                    const children = kgfRawTree.filter(n => n.parent === folder.id);
                    if (!children.length) return;
                    const cc = children.filter(c => kgfChecked.has(c.id)).length;
                    if (cc === children.length) kgfChecked.add(folder.id);
                    else kgfChecked.delete(folder.id);
                });
            } else {
                kgfChecked = new Set(kgfRawTree.map(n => n.id));
            }

            kgfRenderTree();
            kgfUpdateFooter();
        })
        .catch(() => { wrap.innerHTML = '<div class="kgf-picker-loading">Error loading tree.</div>'; });
}

function getFolderDepth(jsId) {
    let d = 0, cur = jsId;
    while (true) {
        const node = kgfRawTree.find(n => n.id === cur);
        if (!node || !node.parent || node.parent === '#') break;
        cur = node.parent; d++;
    }
    return d;
}

// ══════════════════════════════════════════════
// TREE RENDER
// ══════════════════════════════════════════════
function kgfNodeIcon(type) {
    const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' };
    return map[type] || '📝';
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function kgfRenderTree() {
    const wrap = document.getElementById('kgf-tree-wrap');
    const childMap = {};
    kgfRawTree.forEach(n => {
        const p = n.parent || '#';
        if (!childMap[p]) childMap[p] = [];
        childMap[p].push(n);
    });
    wrap.innerHTML = kgfBuildLevel('#', childMap, 0);

    const savedOpen = kgfLoadOpenState();
    wrap.querySelectorAll('.kgf-tree-children').forEach(el => {
        const jsId = el.id.replace('kgfkids-', '');
        el.classList.toggle('open', savedOpen === null || savedOpen.has(jsId));
    });
    wrap.querySelectorAll('.kgf-node-toggle').forEach(el => {
        const row  = el.closest('.kgf-tree-node');
        const jsId = row ? row.dataset.jid : null;
        const kids = jsId ? document.getElementById('kgfkids-' + jsId) : null;
        el.classList.toggle('open', kids ? kids.classList.contains('open') : false);
    });
    // Indeterminate pass for folders
    kgfRawTree.filter(n => n.type === 'folder').forEach(folder => {
        const el = wrap.querySelector(`.kgf-tree-node[data-jid="${folder.id}"] input[type=checkbox]`);
        if (!el) return;
        const children = kgfRawTree.filter(n => n.parent === folder.id);
        if (!children.length) return;
        const cc = children.filter(c => kgfChecked.has(c.id)).length;
        el.indeterminate = (cc > 0 && cc < children.length);
    });
}

function kgfBuildLevel(parentId, childMap, depth) {
    const children = childMap[parentId] || [];
    if (!children.length) return '';
    const indent = depth * 14;
    let html = '';
    children.forEach(node => {
        const isFolder = node.type === 'folder';
        const jsId     = node.id;
        const checked  = kgfChecked.has(jsId);
        const hasKids  = !!(childMap[jsId] && childMap[jsId].length);
        const icon     = isFolder ? '📁' : kgfNodeIcon(node.data && node.data.node_type ? node.data.node_type : 'note');

        const toggleBtn = (isFolder && hasKids)
            ? `<span class="kgf-node-toggle open" onclick="kgfToggleFolder('${jsId}',this)">▶</span>`
            : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

        html += `
        <div class="kgf-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
             style="padding-left:${10 + indent}px;" data-jid="${jsId}">
            ${toggleBtn}
            <input type="checkbox" ${checked ? 'checked' : ''}
                   onchange="kgfCheck('${jsId}', this.checked)">
            <span class="kgf-node-icon">${icon}</span>
            <span class="kgf-node-label">${escHtml(node.text)}</span>
        </div>`;

        if (hasKids) {
            html += `<div class="kgf-tree-children open" id="kgfkids-${jsId}">`;
            html += kgfBuildLevel(jsId, childMap, depth + 1);
            html += `</div>`;
        }
    });
    return html;
}

function kgfToggleFolder(jsId, btn) {
    const kids = document.getElementById('kgfkids-' + jsId);
    if (!kids) return;
    kids.classList.toggle('open');
    btn.classList.toggle('open');
    kgfSaveOpenState();
}

// ══════════════════════════════════════════════
// CHECKBOX LOGIC
// ══════════════════════════════════════════════
function kgfDescendants(jsId) {
    const result = [jsId], queue = [jsId];
    while (queue.length) {
        const cur = queue.shift();
        kgfRawTree.filter(n => n.parent === cur).forEach(n => { result.push(n.id); queue.push(n.id); });
    }
    return result;
}

function kgfCheck(jsId, checked) {
    const ids = kgfDescendants(jsId);
    ids.forEach(id => { if (checked) kgfChecked.add(id); else kgfChecked.delete(id); });
    ids.forEach(id => {
        const el = document.querySelector(`#kgf-tree-wrap .kgf-tree-node[data-jid="${id}"] input[type=checkbox]`);
        if (el) { el.checked = checked; el.indeterminate = false; }
    });
    kgfSyncAncestors(jsId);
    kgfUpdateFooter();
}

function kgfSyncAncestors(jsId) {
    const node = kgfRawTree.find(n => n.id === jsId);
    if (!node || !node.parent || node.parent === '#') return;
    const parentJid   = node.parent;
    const siblings    = kgfRawTree.filter(n => n.parent === parentJid);
    const allChecked  = siblings.every(s => kgfChecked.has(s.id));
    const noneChecked = siblings.every(s => !kgfChecked.has(s.id));
    const el = document.querySelector(`#kgf-tree-wrap .kgf-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);
    if (el) {
        if (allChecked)       { el.checked = true;  el.indeterminate = false; kgfChecked.add(parentJid); }
        else if (noneChecked) { el.checked = false; el.indeterminate = false; kgfChecked.delete(parentJid); }
        else                  { el.checked = false; el.indeterminate = true;  kgfChecked.delete(parentJid); }
    }
    kgfSyncAncestors(parentJid);
}

function kgfToggleAll() {
    kgfRawTree.forEach(n => kgfChecked.add(n.id));
    kgfRenderTree();
    kgfUpdateFooter();
}

function kgfUncheckAll() {
    kgfChecked.clear();
    kgfRenderTree();
    kgfUpdateFooter();
}

// ══════════════════════════════════════════════
// FOOTER COUNT + STATUS DOT
// ══════════════════════════════════════════════
function kgfUpdateFooter() {
    const total   = kgfRawTree.filter(n => n.type === 'node').length;
    const checked = kgfRawTree.filter(n => n.type === 'node' && kgfChecked.has(n.id)).length;
    const isAll   = checked === total;
    const dot     = document.getElementById('kgf-dot');
    dot.className = 'kgf-status-dot' + (isAll ? '' : ' active');
    document.getElementById('kgf-count-text').textContent = isAll
        ? `All ${total} nodes — no filter active`
        : `${checked} of ${total} nodes selected`;
}

// ══════════════════════════════════════════════
// SAVE & LAUNCH
// ══════════════════════════════════════════════
function kgfGetVisibleDbIds() {
    const ids = new Set();
    kgfRawTree.forEach(n => {
        if (n.type === 'node' && kgfChecked.has(n.id)) ids.add(n.data.db_id.toString());
    });
    return ids;
}

function kgfIsAllSelected() {
    const total   = kgfRawTree.filter(n => n.type === 'node').length;
    const checked = kgfRawTree.filter(n => n.type === 'node' && kgfChecked.has(n.id)).length;
    return checked === total;
}

function kgfSaveFilter() {
    if (kgfIsAllSelected()) {
        kgfSaveToStorage(null);
        toast('Filter cleared — all nodes will be shown');
    } else {
        const ids = kgfGetVisibleDbIds();
        kgfSaveToStorage(ids);
        toast(`Filter saved — ${ids.size} nodes selected`);
    }
    kgfUpdateFooter();
}

function launchGraph() {
    // Always save before launching so the graph gets the latest selection
    if (kgfIsAllSelected()) {
        kgfSaveToStorage(null);
    } else {
        kgfSaveToStorage(kgfGetVisibleDbIds());
    }
    window.location.href = MODES[currentMode].graphUrl;
}

// ══════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════
let toastTimer;
function toast(msg, type='success') {
    const el = document.getElementById('kgf-toast');
    el.textContent = msg;
    el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display = 'none', 2800);
}

// ══════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    kgfLoadTree();
});
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
