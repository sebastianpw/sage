<?php
// public/backup_download.php
// ─────────────────────────────────────────────────────────────────────────────
// BACKUP RETRIEVAL — Browse and download backup files from remote destinations.
// New standalone page — no changes to backup_forge.php or any existing code.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Backup Retrieval</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t) document.documentElement.setAttribute('data-theme', t);
    } catch(e) {}
})();
</script>

<style>
/* ── Forge design tokens (identical to backup_forge.php) ── */
:root {
    --bg:          #080b10;
    --surface:     #0e1319;
    --card:        #111820;
    --card-hover:  #141e28;
    --border:      #1c2535;
    --border-glow: #2a3a52;
    --text:        #c8d4e8;
    --text-dim:    #5a6a80;
    --text-bright: #e8f0ff;
    --amber:       #f5a623;
    --amber-dim:   rgba(245,166,35,.08);
    --amber-mid:   rgba(245,166,35,.15);
    --amber-glow:  rgba(245,166,35,.4);
    --green:       #22d3a0;
    --green-dim:   rgba(34,211,160,.1);
    --red:         #f05060;
    --red-dim:     rgba(240,80,96,.1);
    --blue:        #4da6ff;
    --blue-dim:    rgba(77,166,255,.1);
    --mono:        'Space Mono','Fira Mono',monospace;
    --sans:        'Syne',system-ui,sans-serif;
    --radius:      6px;
    --radius-lg:   10px;
}
:root[data-theme="light"],html[data-theme="light"] {
    --bg:#f6f8fa; --surface:#e1e4e8; --card:#fff; --card-hover:#f3f4f6;
    --border:#d1d5db; --border-glow:#9ca3af; --text:#111827; --text-dim:#4b5563;
    --text-bright:#000; --amber:#d97706; --amber-dim:rgba(217,119,6,.1);
    --amber-mid:rgba(217,119,6,.2); --amber-glow:rgba(217,119,6,.4);
    --green:#059669; --green-dim:rgba(5,150,105,.1);
    --red:#dc2626; --red-dim:rgba(220,38,38,.1);
    --blue:#2563eb; --blue-dim:rgba(37,99,235,.1);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }
html, body {
    height:100%; background:var(--bg); color:var(--text);
    font-family:var(--sans); font-size:14px; line-height:1.5;
    -webkit-font-smoothing:antialiased;
}
::-webkit-scrollbar { width:4px; height:4px }
::-webkit-scrollbar-track { background:transparent }
::-webkit-scrollbar-thumb { background:var(--border-glow); border-radius:4px }

/* ── LAYOUT ── */
.page { display:flex; flex-direction:column; min-height:100vh; }

/* ── HEADER ── */
.page-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:0 16px; height:52px;
    background:var(--surface); border-bottom:1px solid var(--border);
    flex-shrink:0; position:sticky; top:0; z-index:100;
}
.logo { display:flex; align-items:center; gap:10px;
    font-family:var(--mono); font-size:.82rem; font-weight:700;
    color:var(--amber); letter-spacing:2px; text-transform:uppercase; }
.logo-icon { width:28px; height:28px; background:var(--amber-mid);
    border:1px solid var(--amber-glow); border-radius:var(--radius);
    display:flex; align-items:center; justify-content:center; font-size:14px; }
.header-right { display:flex; align-items:center; gap:8px; }

/* ── BODY ── */
.page-body { flex:1; display:flex; flex-direction:column; max-width:900px;
    width:100%; margin:0 auto; padding:20px 16px 40px; gap:16px; }

/* ── DESTINATION SELECTOR ── */
.dest-bar {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius-lg); padding:12px 16px;
}
.dest-bar-label { font-family:var(--mono); font-size:.68rem; color:var(--text-dim);
    text-transform:uppercase; letter-spacing:1.5px; flex-shrink:0; }
.dest-select {
    flex:1; min-width:180px; padding:7px 10px;
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--radius); color:var(--text);
    font-family:var(--mono); font-size:.8rem; appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 10px center; padding-right:28px;
    cursor:pointer;
}
.dest-select:focus { outline:none; border-color:var(--amber); }
.btn-connect {
    padding:7px 16px; background:var(--amber); color:#000;
    border:none; border-radius:var(--radius); cursor:pointer;
    font-family:var(--mono); font-size:.78rem; font-weight:700;
    text-transform:uppercase; letter-spacing:1px;
    display:flex; align-items:center; gap:6px;
    transition:filter .15s;
    flex-shrink:0;
}
.btn-connect:hover:not(:disabled) { filter:brightness(1.12); }
.btn-connect:disabled { opacity:.45; cursor:not-allowed; }
.btn-connect .spin {
    width:13px; height:13px; border:2px solid rgba(0,0,0,.3);
    border-top-color:#000; border-radius:50%;
    animation:spin .7s linear infinite; display:none;
}
.btn-connect.loading .spin { display:block; }
.btn-connect.loading .btn-connect-label { display:none; }

/* ── BREADCRUMB ── */
.breadcrumb {
    display:flex; align-items:center; gap:0;
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius-lg); padding:8px 12px;
    flex-wrap:wrap; gap:2px;
    font-family:var(--mono); font-size:.75rem;
}
.breadcrumb-item {
    color:var(--amber); cursor:pointer; padding:2px 5px;
    border-radius:4px; transition:background .12s;
}
.breadcrumb-item:hover { background:var(--amber-dim); }
.breadcrumb-item.current { color:var(--text); cursor:default; }
.breadcrumb-item.current:hover { background:transparent; }
.breadcrumb-sep { color:var(--text-dim); padding:0 2px; user-select:none; }

/* ── SEARCH BAR ── */
.search-row { display:flex; gap:8px; align-items:center; }
.search-input {
    flex:1; padding:8px 12px;
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--radius); color:var(--text);
    font-family:var(--mono); font-size:.8rem;
    transition:border-color .15s;
}
.search-input:focus { outline:none; border-color:var(--amber); }
.search-input::placeholder { color:var(--text-dim); }

/* ── QUICK FILTERS ── */
.quick-filters { display:flex; gap:6px; flex-wrap:wrap; }
.qf-btn {
    padding:5px 12px; border-radius:20px;
    border:1px solid var(--border); background:transparent;
    color:var(--text-dim); font-family:var(--mono); font-size:.7rem;
    cursor:pointer; transition:all .15s; white-space:nowrap;
}
.qf-btn:hover { border-color:var(--border-glow); color:var(--text); }
.qf-btn.active { background:var(--amber-dim); border-color:var(--amber); color:var(--amber); }

/* ── FILE BROWSER ── */
.file-browser {
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius-lg); overflow:hidden;
}
.browser-header {
    display:grid; grid-template-columns:1fr 100px 140px 44px;
    padding:7px 14px;
    background:var(--card); border-bottom:1px solid var(--border);
    font-family:var(--mono); font-size:.65rem; color:var(--text-dim);
    text-transform:uppercase; letter-spacing:1px;
}
.browser-header span:last-child { text-align:center; }
.browser-empty {
    padding:40px; text-align:center;
    font-family:var(--mono); font-size:.8rem; color:var(--text-dim);
}
.browser-spinner {
    padding:40px; text-align:center;
    font-family:var(--mono); font-size:.8rem; color:var(--amber);
    display:none;
}
.browser-spinner.visible { display:block; }

/* ── FILE ROW ── */
.file-row {
    display:grid; grid-template-columns:1fr 100px 140px 44px;
    padding:9px 14px; align-items:center;
    border-bottom:1px solid var(--border);
    transition:background .12s; cursor:pointer;
}
.file-row:last-child { border-bottom:none; }
.file-row:hover { background:var(--card-hover); }
.file-row.is-dir { cursor:pointer; }
.file-row.is-dir:hover .file-name { color:var(--amber); }

.file-icon { margin-right:8px; font-size:15px; flex-shrink:0; }
.dir-icon  { color:var(--amber); opacity:.8; }
.file-name {
    font-family:var(--mono); font-size:.8rem; color:var(--text-bright);
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    display:flex; align-items:center;
    transition:color .12s;
}
.file-size { font-family:var(--mono); font-size:.75rem; color:var(--text-dim); }
.file-mtime { font-family:var(--mono); font-size:.72rem; color:var(--text-dim); }
.file-dl-btn {
    width:30px; height:30px; border-radius:var(--radius);
    border:1px solid var(--border); background:transparent;
    color:var(--text-dim); cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    font-size:13px; transition:all .15s; margin:0 auto;
}
.file-dl-btn:hover { border-color:var(--green); color:var(--green); background:var(--green-dim); }
.file-dl-btn.downloading { border-color:var(--amber); color:var(--amber);
    animation:pulse 1s ease infinite; pointer-events:none; }

/* ── STATUS BAR ── */
.status-bar {
    padding:8px 14px;
    background:var(--card); border-top:1px solid var(--border);
    font-family:var(--mono); font-size:.7rem; color:var(--text-dim);
    display:flex; justify-content:space-between; align-items:center;
    border-radius:0 0 var(--radius-lg) var(--radius-lg);
}

/* ── PLACEHOLDER ── */
.placeholder-panel {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; gap:14px; padding:60px 20px;
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius-lg); text-align:center;
}
.placeholder-icon { font-size:44px; opacity:.25; filter:grayscale(1); }
.placeholder-title { font-family:var(--mono); font-size:.9rem; color:var(--text-dim);
    text-transform:uppercase; letter-spacing:2px; }
.placeholder-sub { font-size:.82rem; color:var(--text-dim); opacity:.6; }

/* ── TOAST ── */
.toast-container { position:fixed; bottom:20px; right:16px; z-index:9999;
    display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.toast { padding:9px 14px; border-radius:var(--radius);
    background:var(--card); border:1px solid var(--border);
    font-family:var(--mono); font-size:.78rem; color:var(--text);
    box-shadow:0 4px 20px rgba(0,0,0,.5); animation:toastIn .25s ease;
    pointer-events:all; cursor:pointer; max-width:300px;
    display:flex; align-items:center; gap:8px; }
.toast.success { border-color:var(--green); }
.toast.error   { border-color:var(--red); color:var(--red); }
.toast.info    { border-color:var(--amber); }
.toast.out     { animation:toastOut .25s ease forwards; }

/* ── DOWNLOAD PROGRESS OVERLAY ── */
.dl-progress {
    position:fixed; bottom:20px; left:50%; transform:translateX(-50%);
    background:var(--card); border:1px solid var(--amber);
    border-radius:var(--radius-lg); padding:14px 20px;
    font-family:var(--mono); font-size:.8rem; color:var(--amber);
    z-index:9998; display:none; align-items:center; gap:12px;
    box-shadow:0 8px 30px rgba(0,0,0,.5);
    min-width:280px;
}
.dl-progress.visible { display:flex; }
.dl-progress-spin {
    width:16px; height:16px; border:2px solid var(--amber-dim);
    border-top-color:var(--amber); border-radius:50%;
    animation:spin .7s linear infinite; flex-shrink:0;
}
.dl-progress-info { flex:1; min-width:0; }
.dl-progress-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dl-progress-sub { font-size:.68rem; color:var(--text-dim); margin-top:2px; }

/* ── UTILS ── */
.btn-icon-sm {
    width:32px; height:32px; border-radius:var(--radius);
    border:1px solid var(--border); background:transparent;
    color:var(--text-dim); cursor:pointer; transition:all .15s;
    display:flex; align-items:center; justify-content:center; font-size:13px;
}
.btn-icon-sm:hover { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }

@keyframes spin    { to { transform:rotate(360deg) } }
@keyframes toastIn  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
@keyframes toastOut { to{opacity:0;transform:translateY(10px)} }
@keyframes pulse {
    0%,100% { box-shadow:0 0 0 0 var(--amber-glow) }
    50%      { box-shadow:0 0 0 5px rgba(245,166,35,0) }
}

/* ── MOBILE ── */
@media (max-width:600px) {
    .browser-header { grid-template-columns:1fr 70px 0 44px; }
    .browser-header .col-mtime { display:none; }
    .file-row { grid-template-columns:1fr 70px 0 44px; }
    .file-mtime { display:none; }
    .quick-filters { gap:4px; }
    .qf-btn { font-size:.65rem; padding:4px 9px; }
}
</style>
</head>
<body>
<div class="page">

    <!-- HEADER -->
    <header class="page-header">
        <div class="logo">
            <div class="logo-icon">⬇</div>
            <span>Backup Retrieval</span>
        </div>
        <div class="header-right">
            <button class="btn-icon-sm" onclick="BR.refresh()" title="Refresh current folder">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <a href="/backup_forge.php" style="text-decoration:none;">
                <button class="btn-icon-sm" title="Go to Backup Forge">
                    <i class="bi bi-shield-check"></i>
                </button>
            </a>
        </div>
    </header>

    <!-- PAGE BODY -->
    <div class="page-body">

        <!-- DESTINATION SELECTOR -->
        <div class="dest-bar">
            <span class="dest-bar-label">Destination</span>
            <select class="dest-select" id="destSelect">
                <option value="">Loading destinations…</option>
            </select>
            <button class="btn-connect" id="btnConnect" onclick="BR.connect()">
                <div class="spin"></div>
                <span class="btn-connect-label"><i class="bi bi-plug-fill"></i> Connect</span>
            </button>
        </div>

        <!-- BREADCRUMB -->
        <div class="breadcrumb" id="breadcrumb" style="display:none;"></div>

        <!-- SEARCH + FILTERS -->
        <div id="controlsRow" style="display:none; flex-direction:column; gap:8px;">
            <div class="search-row">
                <input type="text" class="search-input" id="searchInput"
                    placeholder="Filter files by name…"
                    oninput="BR.onSearch(this.value)">
            </div>
            <div class="quick-filters" id="quickFilters">
                <button class="qf-btn active" data-filter="" onclick="BR.setFilter(this, '')">All</button>
                <button class="qf-btn" data-filter=".tar" onclick="BR.setFilter(this, '.tar')">Frames tar</button>
                <button class="qf-btn" data-filter="audios_" onclick="BR.setFilter(this, 'audios_')">Audios tar</button>
                <button class="qf-btn" data-filter="videos_" onclick="BR.setFilter(this, 'videos_')">Videos tar</button>
                <button class="qf-btn" data-filter=".sql" onclick="BR.setFilter(this, '.sql')">SQL dump</button>
                <button class="qf-btn" data-filter=".zip" onclick="BR.setFilter(this, '.zip')">ZIP codebase</button>
            </div>
        </div>

        <!-- FILE BROWSER -->
        <div id="fileBrowser" style="display:none;">
            <div class="file-browser" id="fileBrowserInner">
                <div class="browser-header">
                    <span>Name</span>
                    <span class="col-size">Size</span>
                    <span class="col-mtime">Modified</span>
                    <span>DL</span>
                </div>
                <div class="browser-spinner" id="browserSpinner">
                    <i class="bi bi-arrow-repeat"></i> Listing remote files…
                </div>
                <div id="fileRows"></div>
                <div class="status-bar" id="statusBar">
                    <span id="statusLeft">—</span>
                    <span id="statusRight"></span>
                </div>
            </div>
        </div>

        <!-- PLACEHOLDER (shown before connect) -->
        <div class="placeholder-panel" id="placeholderPanel">
            <div class="placeholder-icon">💾</div>
            <div class="placeholder-title">No Connection</div>
            <div class="placeholder-sub">Select a destination and press Connect to browse backups</div>
        </div>

    </div><!-- /page-body -->
</div><!-- /page -->

<!-- DOWNLOAD PROGRESS -->
<div class="dl-progress" id="dlProgress">
    <div class="dl-progress-spin"></div>
    <div class="dl-progress-info">
        <div class="dl-progress-name" id="dlProgressName">Transferring…</div>
        <div class="dl-progress-sub">Pulling file from tablet via SCP…</div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script>
const PYAPI = '<?= rtrim($PYAPI_URL ?? "http://127.0.0.1:8009", "/") ?>';

const BR = (() => {
    'use strict';

    let _destId     = null;
    let _currentPath = null;
    let _entries    = [];       // current directory full listing
    let _searchTerm = '';
    let _filterStr  = '';

    // ── Init ───────────────────────────────────────────────────────────────
    async function init() {
        await loadDestinations();
    }

    // ── Destinations ───────────────────────────────────────────────────────
    async function loadDestinations() {
        try {
            const r = await api('/destinations');
            const sel = document.getElementById('destSelect');
            if (!r.ok || !r.data.length) {
                sel.innerHTML = '<option value="">No destinations configured</option>';
                return;
            }
            sel.innerHTML = r.data.map(d =>
                `<option value="${d.id}">${esc(d.name)} — ${esc(d.host_mode === 'ap0_scan' ? 'ap0 scan' : d.host || '?')}</option>`
            ).join('');
        } catch(e) {
            toast('Could not load destinations: ' + e.message, 'error');
        }
    }

    // ── Connect ────────────────────────────────────────────────────────────
    async function connect() {
        const sel = document.getElementById('destSelect');
        _destId = parseInt(sel.value);
        if (!_destId) { toast('Select a destination first', 'error'); return; }

        const dest = sel.options[sel.selectedIndex].text;
        const btn  = document.getElementById('btnConnect');
        btn.disabled = true;
        btn.classList.add('loading');

        // Use remote_base as root — we start at 'sage_backup' by default.
        // The tree endpoint will tell us if it doesn't exist.
        const startPath = 'sage_backup';

        try {
            await browse(startPath);
            toast(`Connected — browsing ${dest}`, 'success', 2500);
        } catch(e) {
            toast('Connect failed: ' + e.message, 'error', 6000);
        } finally {
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    // ── Browse ─────────────────────────────────────────────────────────────
    async function browse(path) {
        if (!_destId) return;
        _currentPath = path;
        _searchTerm = '';
        document.getElementById('searchInput') && (document.getElementById('searchInput').value = '');

        showBrowser();
        setSpinner(true);

        try {
            const r = await api(`/destinations/${_destId}/tree?path=${encodeURIComponent(path)}`);
            if (!r.ok) throw new Error(r.detail || 'Tree listing failed');
            _entries = r.entries || [];
            renderBreadcrumb(path);
            renderRows(_entries);
            updateStatus(r.entries || []);
        } catch(e) {
            setSpinner(false);
            document.getElementById('fileRows').innerHTML =
                `<div class="browser-empty" style="color:var(--red);">${esc(e.message)}</div>`;
        }
    }

    function refresh() {
        if (_currentPath) browse(_currentPath);
    }

    // ── Render breadcrumb ──────────────────────────────────────────────────
    function renderBreadcrumb(path) {
        const crumb = document.getElementById('breadcrumb');
        const parts = path.split('/').filter(Boolean);
        let html = '';
        parts.forEach((part, i) => {
            const partial = parts.slice(0, i + 1).join('/');
            const isCurrent = i === parts.length - 1;
            html += `<span class="breadcrumb-item${isCurrent?' current':''}"
                        onclick="${isCurrent ? '' : `BR.browse('${partial}')`}"
                     >${esc(part)}</span>`;
            if (!isCurrent) html += `<span class="breadcrumb-sep">/</span>`;
        });
        crumb.innerHTML = html;
        crumb.style.display = 'flex';
    }

    // ── Render file rows ───────────────────────────────────────────────────
    function renderRows(entries) {
        setSpinner(false);
        const container = document.getElementById('fileRows');

        // Apply search + filter
        let filtered = entries;
        if (_searchTerm) {
            const q = _searchTerm.toLowerCase();
            filtered = filtered.filter(e => e.name.toLowerCase().includes(q));
        }
        if (_filterStr) {
            filtered = filtered.filter(e => e.type === 'dir' || e.name.includes(_filterStr));
        }

        if (!filtered.length) {
            container.innerHTML = `<div class="browser-empty">No files match your filter</div>`;
            updateStatus([]);
            return;
        }

        container.innerHTML = filtered.map(e => {
            if (e.type === 'dir') {
                const fullPath = `${_currentPath}/${e.name}`;
                return `<div class="file-row is-dir" onclick="BR.browse('${fullPath.replace(/'/g, "\\'")}')">
                    <div class="file-name">
                        <i class="bi bi-folder-fill file-icon dir-icon"></i>${esc(e.name)}
                    </div>
                    <div class="file-size">—</div>
                    <div class="file-mtime">${esc(e.mtime||'')}</div>
                    <div style="display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-chevron-right" style="color:var(--text-dim);font-size:12px;"></i>
                    </div>
                </div>`;
            }
            // It's a file
            const filePath = `${_currentPath}/${e.name}`;
            const fileId   = btoa(filePath).replace(/[^a-zA-Z0-9]/g,'').substring(0,12);
            return `<div class="file-row">
                <div class="file-name">
                    <i class="bi ${fileIcon(e.name)} file-icon" style="color:var(--text-dim);"></i>
                    ${esc(e.name)}
                </div>
                <div class="file-size">${fmtBytes(e.size)}</div>
                <div class="file-mtime">${esc(e.mtime||'')}</div>
                <div style="display:flex;align-items:center;justify-content:center;">
                    <button class="file-dl-btn" id="dlbtn_${fileId}"
                        onclick="BR.downloadFile('${filePath.replace(/'/g, "\\'")}', '${fileId}')"
                        title="Download ${esc(e.name)}">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
            </div>`;
        }).join('');

        updateStatus(filtered);
    }

    // ── Download a file ────────────────────────────────────────────────────
    async function downloadFile(remotePath, btnId) {
        const btn = document.getElementById('dlbtn_' + btnId);
        if (btn) btn.classList.add('downloading');

        const filename = remotePath.split('/').pop();
        showDlProgress(filename);

        const url = `${PYAPI}/backup-dl/destinations/${_destId}/fetch?path=${encodeURIComponent(remotePath)}`;

        try {
            // We use fetch + blob so we can detect errors before triggering the browser download
            const resp = await fetch(url);
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({ detail: `HTTP ${resp.status}` }));
                throw new Error(err.detail || `HTTP ${resp.status}`);
            }

            const blob = await resp.blob();
            const objUrl = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = objUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(objUrl), 5000);

            hideDlProgress();
            toast(`Downloaded: ${filename}`, 'success', 4000);

        } catch(e) {
            hideDlProgress();
            toast(`Download failed: ${e.message}`, 'error', 7000);
        } finally {
            if (btn) btn.classList.remove('downloading');
        }
    }

    // ── Search / filter ────────────────────────────────────────────────────
    function onSearch(val) {
        _searchTerm = val.trim();
        renderRows(_entries);
    }

    function setFilter(btn, val) {
        document.querySelectorAll('.qf-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        _filterStr = val;
        renderRows(_entries);
    }

    // ── UI helpers ─────────────────────────────────────────────────────────
    function showBrowser() {
        document.getElementById('placeholderPanel').style.display = 'none';
        document.getElementById('fileBrowser').style.display      = 'block';
        document.getElementById('breadcrumb').style.display       = 'flex';
        document.getElementById('controlsRow').style.display      = 'flex';
    }

    function setSpinner(on) {
        const sp = document.getElementById('browserSpinner');
        sp.classList.toggle('visible', on);
        if (on) document.getElementById('fileRows').innerHTML = '';
    }

    function updateStatus(entries) {
        const files = entries.filter(e => e.type === 'file');
        const dirs  = entries.filter(e => e.type === 'dir');
        const total = files.reduce((s, e) => s + (e.size||0), 0);
        document.getElementById('statusLeft').textContent =
            `${files.length} file${files.length!==1?'s':''}, ${dirs.length} folder${dirs.length!==1?'s':''}`;
        document.getElementById('statusRight').textContent =
            total > 0 ? fmtBytes(total) + ' total' : '';
    }

    function showDlProgress(name) {
        document.getElementById('dlProgressName').textContent = name;
        document.getElementById('dlProgress').classList.add('visible');
    }
    function hideDlProgress() {
        document.getElementById('dlProgress').classList.remove('visible');
    }

    // ── Toast ──────────────────────────────────────────────────────────────
    function toast(msg, type = 'info', dur = 3000) {
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.innerHTML = `<span>${{success:'✓',error:'✕',info:'◆'}[type]||'◆'}</span> ${esc(msg)}`;
        el.onclick = () => dismiss(el);
        document.getElementById('toastContainer').appendChild(el);
        const dismiss = e => { e.classList.add('out'); setTimeout(() => e.remove(), 300); };
        setTimeout(() => dismiss(el), dur);
    }

    // ── API ────────────────────────────────────────────────────────────────
    async function api(path, opts = {}) {
        const res = await fetch(`${PYAPI}/backup-dl${path}`, {
            headers: { 'Content-Type': 'application/json' },
            ...opts,
        });
        if (!res.ok && res.status >= 500) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // ── Utils ──────────────────────────────────────────────────────────────
    function esc(s) {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }
    function fmtBytes(b) {
        if (!b) return '—';
        if (b < 1024)       return b + ' B';
        if (b < 1048576)    return (b/1024).toFixed(1)    + ' KB';
        if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
        return (b/1073741824).toFixed(2) + ' GB';
    }
    function fileIcon(name) {
        const ext = name.split('.').pop().toLowerCase();
        if (ext === 'tar')  return 'bi-archive';
        if (ext === 'gz')   return 'bi-archive';
        if (ext === 'zip')  return 'bi-file-zip';
        if (ext === 'sql')  return 'bi-database';
        if (ext === 'mp4')  return 'bi-film';
        if (ext === 'webm') return 'bi-film';
        if (ext === 'png' || ext === 'jpg' || ext === 'jpeg') return 'bi-image';
        return 'bi-file-earmark';
    }

    return { init, connect, browse, refresh, downloadFile, onSearch, setFilter };
})();

document.addEventListener('DOMContentLoaded', () => BR.init());
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
