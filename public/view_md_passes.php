<?php
// public/view_md_passes.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();

// Pre-fetch categories for the profile/category modal
$categories = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Pre-fetch all active documents so JS can filter & match passes dynamically on the client
$stmt = $pdo->query("SELECT id, name, category_id, updated_at FROM documentations WHERE is_active = 1 ORDER BY id DESC");
$allDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Fast Pass Exporter (1–30)";
ob_start();
?>
<!-- THEME INIT SCRIPT -->
<script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        } catch (e) {}
    })();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    :root {
        --bg: #f6f8fa;
        --card: #ffffff;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.6);
        --card-elevation: 0 4px 14px rgba(0,0,0,0.08);
        --blue-light-bg: rgba(9, 105, 218, 0.08);
        --blue-light-border: rgba(9, 105, 218, 0.25);
    }
    :root[data-theme="dark"] {
        --bg: #0d1117;
        --card: #161b22;
        --border: #30363d;
        --text: #c9d1d9;
        --text-muted: #8b949e;
        --accent: #58a6ff;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.75);
        --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
        --blue-light-bg: rgba(88, 166, 255, 0.12);
        --blue-light-border: rgba(88, 166, 255, 0.3);
    }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --green: #238636;
            --red: #da3633;
            --orange: #f59e0b;
            --overlay-bg: rgba(0,0,0,0.75);
            --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
            --blue-light-bg: rgba(88, 166, 255, 0.12);
            --blue-light-border: rgba(88, 166, 255, 0.3);
        }
    }

    * { box-sizing: border-box; }
    body { 
        background-color: var(--bg) !important; 
        color: var(--text) !important; 
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 6px 8px; 
        margin: 0;
    }
    .container { max-width: 680px; margin: 0 auto; }

    .header-row { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 8px; 
        border-bottom: 1px solid var(--border); 
        padding-bottom: 6px; 
        gap: 6px;
    }

    .btn {
        padding: 5px 9px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
        height: 30px;
        flex: 0 0 auto;
    }
    .btn:active { transform: scale(0.97); }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-save { background: var(--green); color: #fff; }
    .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
    .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
    .btn-sm { padding: 3px 6px; font-size: 0.74rem; height: 24px; }

    .input-control {
        width: 100%;
        padding: 5px 8px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.82rem;
        font-family: inherit;
        box-sizing: border-box;
    }
    .input-control:focus {
        outline: none;
        border-color: var(--accent);
    }

    .panel-box {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 8px 10px;
        margin-bottom: 8px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .section-title {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }

    .passes-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
    }
    .pass-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--bg);
        border: 1px solid var(--border);
        padding: 3px 6px;
        border-radius: 5px;
        font-size: 0.75rem;
        cursor: pointer;
        user-select: none;
    }
    .pass-chip input[type=checkbox] {
        margin: 0;
        width: 13px;
        height: 13px;
        accent-color: var(--accent);
        cursor: pointer;
    }
    .pass-chip.active {
        background: var(--blue-light-bg);
        border-color: var(--blue-light-border);
    }
    .badge-pass {
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 0.68rem;
        font-weight: 700;
        color: #fff;
    }

    /* 10-column mobile-friendly grid */
    .num-grid {
        display: grid;
        grid-template-columns: repeat(10, 1fr);
        gap: 3px;
    }
    .num-btn {
        background: var(--bg);
        border: 1px solid var(--border);
        color: var(--text);
        padding: 4px 0;
        text-align: center;
        border-radius: 4px;
        font-size: 0.74rem;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
    }
    .num-btn.selected {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }

    /* Compact Mobile Matrix Table */
    .table-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }
    .matrix-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
        text-align: center;
        table-layout: fixed;
    }
    .matrix-table th {
        background: var(--bg);
        padding: 5px 2px;
        font-weight: 700;
        border-bottom: 1px solid var(--border);
        color: var(--text);
        font-size: 0.72rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .matrix-table td {
        padding: 4px 1px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        height: 28px;
    }
    .matrix-table tr:hover {
        background: var(--blue-light-bg);
    }
    .doc-icon-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: var(--green);
        font-size: 0.95rem;
        line-height: 1;
        width: 100%;
        height: 100%;
    }
    .cell-empty {
        color: var(--text-muted);
        opacity: 0.25;
        font-weight: bold;
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: var(--overlay-bg);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        padding: 10px;
    }
    .modal-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        width: 100%;
        max-width: 520px;
        max-height: 88vh;
        display: flex;
        flex-direction: column;
        box-shadow: var(--card-elevation);
    }
    .modal-header {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-body {
        padding: 12px 14px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .modal-footer {
        padding: 8px 14px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 6px;
    }

    .pass-def-item {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .pass-def-row {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    #toast {
        position: fixed;
        bottom: 16px;
        right: 16px;
        z-index: 999999;
        background: var(--card);
        color: var(--text);
        border: 1px solid var(--border);
        border-left: 4px solid var(--green);
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.8rem;
        display: none;
        box-shadow: var(--card-elevation);
    }
</style>

<div class="container">
    <!-- Header -->
    <div class="header-row">
        <div style="display:flex; align-items:center; gap:6px;">
            <a href="view_md.php" class="btn btn-ghost btn-sm" title="Back">
                <i class="bi bi-arrow-left"></i>
            </a>
            <strong style="font-size:0.92rem;" id="headerTitle">Passes (1–30)</strong>
            <button class="btn btn-ghost btn-sm" onclick="openProfileModal()" title="Profiles & Configuration" style="padding:2px 6px;">
                <i class="bi bi-gear-fill" style="color:var(--accent);"></i>
            </button>
        </div>
        <div style="display:flex; gap:5px; align-items:center;">
            <button class="btn btn-ghost btn-sm" onclick="copyJsonToClipboard()" title="Copy JSON">
                <i class="bi bi-clipboard"></i>
            </button>
            <button class="btn btn-primary btn-sm" id="btnDownloadJson" onclick="exportPassesJson()">
                <i class="bi bi-download"></i> JSON (<span id="selectedCountBadge">0</span>)
            </button>
        </div>
    </div>

    <!-- Active Profile & Category Summary Bar -->
    <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <span>Profile: <strong id="lblActiveProfile" style="color:var(--accent);">Default</strong></span>
        <span>Category: <strong id="lblActiveCategory">All</strong></span>
    </div>

    <!-- Filter & Selection Panel -->
    <div class="panel-box">
        <!-- PASSES SELECTION -->
        <div>
            <div class="section-title">
                <span>Passes</span>
                <div style="display:flex; gap:4px;">
                    <button class="btn btn-ghost btn-sm" style="height:20px; padding:0 5px; font-size:0.68rem;" onclick="setAllPasses(true)">All</button>
                    <button class="btn btn-ghost btn-sm" style="height:20px; padding:0 5px; font-size:0.68rem;" onclick="setAllPasses(false)">None</button>
                </div>
            </div>
            <div class="passes-bar" id="passContainer"></div>
        </div>

        <!-- DOCUMENT NUMBER QUICK PICKER -->
        <div>
            <div class="section-title">
                <span id="lblDocsRange">Docs 1–30</span>
                <div style="display:flex; gap:4px;" id="rangeBtnsWrap">
                    <button class="btn btn-ghost btn-sm" style="height:20px; padding:0 4px; font-size:0.68rem;" onclick="setAllNumbers(true)">All</button>
                    <button class="btn btn-ghost btn-sm" style="height:20px; padding:0 4px; font-size:0.68rem;" onclick="setAllNumbers(false)">None</button>
                </div>
            </div>

            <!-- Number Grid -->
            <div class="num-grid" id="numGrid"></div>
        </div>
    </div>

    <!-- Matrix Table -->
    <div class="table-card">
        <table class="matrix-table" id="matrixTable">
            <thead id="matrixHead"></thead>
            <tbody id="matrixBody"></tbody>
        </table>
    </div>
</div>

<!-- ================= DEFINITION & PROFILE MODAL ================= -->
<div class="modal-overlay" id="profileModal">
    <div class="modal-card">
        <div class="modal-header">
            <h4 style="margin:0; font-size:0.95rem; display:flex; align-items:center; gap:6px;">
                <i class="bi bi-sliders" style="color:var(--accent);"></i> Configure Passes & Profiles
            </h4>
            <button class="btn btn-ghost btn-sm" onclick="closeProfileModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <!-- Profile Selector Row -->
            <div style="background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:8px;">
                <label style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:4px;">Profile Manager</label>
                <div style="display:flex; gap:6px; margin-bottom:6px;">
                    <select id="modalProfileSelect" class="input-control" onchange="onSelectProfile(this.value)" style="flex:1; font-weight:600;"></select>
                    <button class="btn btn-ghost btn-sm" onclick="createNewProfilePrompt()" title="New Profile"><i class="bi bi-plus-lg"></i> New</button>
                    <button class="btn btn-ghost btn-sm" onclick="deleteCurrentProfile()" style="color:var(--red);" title="Delete Profile"><i class="bi bi-trash"></i></button>
                </div>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="text" id="modalProfileName" class="input-control" placeholder="Profile Name">
                </div>
            </div>

            <!-- Category & Max Docs Config -->
            <div style="display:flex; gap:8px;">
                <div style="flex:1;">
                    <label style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:3px;">Category</label>
                    <select id="modalCategory" class="input-control">
                        <option value="">All Categories</option>
                        <option value="0">Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="width:110px;">
                    <label style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:3px;">Max Docs</label>
                    <input type="number" id="modalMaxDocs" class="input-control" min="1" max="100" value="30">
                </div>
            </div>

            <!-- Passes Definition Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:4px; margin-top:4px;">
                <strong style="font-size:0.8rem;">Configured Passes</strong>
                <button type="button" class="btn btn-sm btn-ghost" onclick="addNewPassRow()" style="color:var(--accent);">
                    <i class="bi bi-plus-circle"></i> Add Pass
                </button>
            </div>

            <!-- Dynamic Pass Rows Container -->
            <div id="modalPassesList" style="display:flex; flex-direction:column; gap:8px;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost btn-sm" onclick="closeProfileModal()">Cancel</button>
            <button class="btn btn-save btn-sm" onclick="saveAndApplyProfile()"><i class="bi bi-check2"></i> Apply & Save</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<!-- ================= CLIENT ENGINE ================= -->
<script>
    const allDocs = <?= json_encode($allDocs) ?>;
    const catMap = {
        <?php foreach ($categories as $c): ?>
            "<?= $c['id'] ?>": "<?= addslashes($c['name']) ?>",
        <?php endforeach; ?>
    };

    const passColors = ['#0969da', '#8250df', '#1a7f37', '#bf8700', '#cf222e', '#099268', '#d946ef', '#0284c7', '#ea580c'];

    // Seed Profile with all 7 passes
    const defaultProfiles = {
        "Pirates (Cat 45)": {
            name: "Pirates (Cat 45)",
            categoryId: "45",
            maxDocs: 30,
            passes: [
                { id: 1, label: "P1", sublabel: "Consolidation", mode: "wildcard", pattern: "Consolidation*", color: "#0969da" },
                { id: 2, label: "P2", sublabel: "Wortex", mode: "wildcard", pattern: "WORTEX*", color: "#8250df" },
                { id: 3, label: "P3", sublabel: "Pass 3", mode: "wildcard", pattern: "PASS3*", color: "#1a7f37" },
                { id: 4, label: "P4", sublabel: "ANIVOC", mode: "wildcard", pattern: "ANIVOC_OUTLINE*", color: "#bf8700" },
                { id: 5, label: "P5", sublabel: "Pass 5", mode: "wildcard", pattern: "PASS5_OUTLINE*", color: "#cf222e" },
                { id: 6, label: "P6", sublabel: "MASOUT", mode: "wildcard", pattern: "MASOUT*", color: "#099268" },
                { id: 7, label: "P7", sublabel: "Prose Script", mode: "wildcard", pattern: "PROutline*", color: "#d946ef" }
            ]
        }
    };

    let profiles = {};
    let activeProfileName = "Pirates (Cat 45)";
    let currentConfig = {};
    let matrixData = {};
    let activePasses = new Set();
    let activeNumbers = new Set([1]);

    // Bumped storage version so fresh fixed profile loads automatically
    const LS_PROFILES = 'spw_passes_profiles_v4';
    const LS_ACTIVE = 'spw_passes_active_v4';

    document.addEventListener('DOMContentLoaded', () => {
        loadProfilesFromStorage();
        applyConfig(currentConfig);
    });

    function loadProfilesFromStorage() {
        try {
            const raw = localStorage.getItem(LS_PROFILES);
            if (raw) {
                profiles = JSON.parse(raw);
            }
        } catch (e) {
            profiles = {};
        }

        if (!profiles || Object.keys(profiles).length === 0) {
            profiles = JSON.parse(JSON.stringify(defaultProfiles));
        }

        const savedActive = localStorage.getItem(LS_ACTIVE);
        if (savedActive && profiles[savedActive]) {
            activeProfileName = savedActive;
        } else {
            activeProfileName = Object.keys(profiles)[0];
        }

        currentConfig = profiles[activeProfileName];
    }

    function saveProfilesToStorage() {
        localStorage.setItem(LS_PROFILES, JSON.stringify(profiles));
        localStorage.setItem(LS_ACTIVE, activeProfileName);
    }

    // -----------------------------------------------------------------------
    // Accurate Multi-Digit Number Matching Logic (1..10..30)
    // -----------------------------------------------------------------------
    function computeMatrix() {
        const max = parseInt(currentConfig.maxDocs, 10) || 30;
        const catId = currentConfig.categoryId;

        matrixData = {};
        for (let i = 1; i <= max; i++) {
            matrixData[i] = {};
            currentConfig.passes.forEach(p => {
                matrixData[i][p.id] = null;
            });
        }

        // Filter active docs by Category
        const filteredDocs = allDocs.filter(d => {
            if (!catId || catId === '') return true;
            if (catId === '0') return d.category_id === null;
            return String(d.category_id) === String(catId);
        });

        // Match passes
        filteredDocs.forEach(doc => {
            const title = doc.name.trim();

            for (const pass of currentConfig.passes) {
                let docNum = null;

                if (pass.mode === 'regex') {
                    try {
                        const rx = new RegExp(pass.pattern, 'i');
                        const m = rx.exec(title);
                        if (m && m[1]) {
                            docNum = parseInt(m[1], 10);
                        }
                    } catch (e) {}
                } else {
                    // Wildcard mode: e.g. "Consolidation*", "PROutline*", "WORTEX*", "PASS3*"
                    const prefix = pass.pattern.replace(/\*/g, '').trim().toLowerCase();
                    const lowerTitle = title.toLowerCase();
                    const idx = lowerTitle.indexOf(prefix);
                    
                    if (idx !== -1) {
                        // Extract text directly following prefix
                        const sub = title.substring(idx + prefix.length);
                        // Eagerly grab full sequence of digits (e.g. "01" -> 1, "10" -> 10, "30" -> 30)
                        const m = /(\d+)/.exec(sub);
                        if (m && m[1]) {
                            docNum = parseInt(m[1], 10);
                        }
                    }
                }

                if (docNum !== null && docNum >= 1 && docNum <= max) {
                    const existing = matrixData[docNum][pass.id];
                    // Keep clean titles over draft titles (_DS_) and newest docs
                    if (!existing || (!title.includes('_DS_') && existing.name.includes('_DS_')) || doc.id > existing.id) {
                        matrixData[docNum][pass.id] = {
                            id: doc.id,
                            name: doc.name
                        };
                    }
                    break;
                }
            }
        });
    }

    // -----------------------------------------------------------------------
    // Render UI
    // -----------------------------------------------------------------------
    function applyConfig(cfg) {
        currentConfig = cfg;
        computeMatrix();

        activePasses = new Set(cfg.passes.map(p => p.id));
        activeNumbers = new Set([1]);

        document.getElementById('headerTitle').textContent = `Passes (1–${cfg.maxDocs})`;
        document.getElementById('lblDocsRange').textContent = `Docs 1–${cfg.maxDocs}`;
        document.getElementById('lblActiveProfile').textContent = activeProfileName;
        document.getElementById('lblActiveCategory').textContent = cfg.categoryId ? (catMap[cfg.categoryId] || (cfg.categoryId === '0' ? 'Uncategorized' : cfg.categoryId)) : 'All';

        renderRangeButtons();
        renderPassesBar();
        renderNumberGrid();
        renderMatrixTable();
        updateSelectionUI();
    }

    function renderRangeButtons() {
        const wrap = document.getElementById('rangeBtnsWrap');
        wrap.innerHTML = '';
        const max = currentConfig.maxDocs;

        for (let s = 1; s <= max; s += 10) {
            const e = Math.min(s + 9, max);
            const b = document.createElement('button');
            b.className = 'btn btn-ghost btn-sm';
            b.style.cssText = 'height:20px; padding:0 4px; font-size:0.68rem;';
            b.textContent = `${s}–${e}`;
            b.onclick = () => selectRange(s, e);
            wrap.appendChild(b);
        }

        const bAll = document.createElement('button');
        bAll.className = 'btn btn-ghost btn-sm';
        bAll.style.cssText = 'height:20px; padding:0 4px; font-size:0.68rem;';
        bAll.textContent = 'All';
        bAll.onclick = () => setAllNumbers(true);
        wrap.appendChild(bAll);

        const bNone = document.createElement('button');
        bNone.className = 'btn btn-ghost btn-sm';
        bNone.style.cssText = 'height:20px; padding:0 4px; font-size:0.68rem;';
        bNone.textContent = 'None';
        bNone.onclick = () => setAllNumbers(false);
        wrap.appendChild(bNone);
    }

    function renderPassesBar() {
        const bar = document.getElementById('passContainer');
        bar.innerHTML = '';
        currentConfig.passes.forEach(p => {
            const lbl = document.createElement('label');
            lbl.className = 'pass-chip active';
            lbl.id = 'chip_pass_' + p.id;
            lbl.title = p.sublabel;
            lbl.innerHTML = `
                <input type="checkbox" class="pass-chk" value="${p.id}" checked onchange="onPassToggle(this)">
                <span class="badge-pass" style="background:${p.color || '#0969da'}">${escapeHtml(p.label)}</span>
                <span style="font-size:0.72rem; font-weight:600;">${escapeHtml(p.sublabel)}</span>
            `;
            bar.appendChild(lbl);
        });
    }

    function renderNumberGrid() {
        const grid = document.getElementById('numGrid');
        grid.innerHTML = '';
        for (let i = 1; i <= currentConfig.maxDocs; i++) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'num-btn' + (activeNumbers.has(i) ? ' selected' : '');
            b.dataset.num = i;
            b.textContent = i;
            b.onclick = (e) => toggleNumber(i, e);
            grid.appendChild(b);
        }
    }

    function renderMatrixTable() {
        const head = document.getElementById('matrixHead');
        const body = document.getElementById('matrixBody');

        let headHtml = `<tr>
            <th style="width:24px;">#</th>
            <th style="width:24px;">
                <input type="checkbox" id="chkToggleAllRows" onchange="toggleAllRowSelections(this.checked)" style="margin:0; width:13px; height:13px;">
            </th>`;

        currentConfig.passes.forEach(p => {
            headHtml += `<th title="${escapeHtml(p.sublabel)}">${escapeHtml(p.label)}</th>`;
        });
        headHtml += `</tr>`;
        head.innerHTML = headHtml;

        let bodyHtml = '';
        for (let num = 1; num <= currentConfig.maxDocs; num++) {
            bodyHtml += `<tr id="row_num_${num}">
                <td style="font-weight:bold; color:var(--text-muted); font-size:0.74rem;">${num}</td>
                <td>
                    <input type="checkbox" class="row-selector-chk" data-num="${num}" ${activeNumbers.has(num) ? 'checked' : ''} onchange="onRowCheckboxChange(${num}, this.checked)" style="margin:0; width:13px; height:13px;">
                </td>`;

            currentConfig.passes.forEach(p => {
                const cell = matrixData[num] ? matrixData[num][p.id] : null;
                bodyHtml += `<td data-pass-col="${p.id}">`;
                if (cell) {
                    bodyHtml += `<a href="view_md.php?id=${cell.id}" class="doc-icon-link" title="${escapeHtml(cell.name)}" target="_blank">
                        <i class="bi bi-file-earmark-check-fill"></i>
                    </a>`;
                } else {
                    bodyHtml += `<span class="cell-empty">&middot;</span>`;
                }
                bodyHtml += `</td>`;
            });

            bodyHtml += `</tr>`;
        }
        body.innerHTML = bodyHtml;
    }

    // -----------------------------------------------------------------------
    // Matrix Interactions
    // -----------------------------------------------------------------------
    function onPassToggle(chk) {
        const pId = parseInt(chk.value, 10);
        const chip = document.getElementById('chip_pass_' + pId);
        if (chk.checked) {
            activePasses.add(pId);
            chip.classList.add('active');
        } else {
            activePasses.delete(pId);
            chip.classList.remove('active');
        }
        updateSelectionUI();
    }

    function setAllPasses(state) {
        document.querySelectorAll('.pass-chk').forEach(chk => {
            chk.checked = state;
            const pId = parseInt(chk.value, 10);
            const chip = document.getElementById('chip_pass_' + pId);
            if (state) {
                activePasses.add(pId);
                chip.classList.add('active');
            } else {
                activePasses.delete(pId);
                chip.classList.remove('active');
            }
        });
        updateSelectionUI();
    }

    function toggleNumber(num, ev) {
        if (!ev.ctrlKey && !ev.metaKey && !ev.shiftKey) {
            activeNumbers.clear();
            activeNumbers.add(num);
        } else {
            if (activeNumbers.has(num)) activeNumbers.delete(num);
            else activeNumbers.add(num);
        }
        updateSelectionUI();
    }

    function onRowCheckboxChange(num, checked) {
        if (checked) activeNumbers.add(num);
        else activeNumbers.delete(num);
        updateSelectionUI();
    }

    function setAllNumbers(state) {
        activeNumbers.clear();
        if (state) {
            for (let i = 1; i <= currentConfig.maxDocs; i++) activeNumbers.add(i);
        }
        updateSelectionUI();
    }

    function selectRange(start, end) {
        activeNumbers.clear();
        for (let i = start; i <= end; i++) activeNumbers.add(i);
        updateSelectionUI();
    }

    function toggleAllRowSelections(state) {
        setAllNumbers(state);
    }

    function getSelectedDocIds() {
        const ids = [];
        activeNumbers.forEach(num => {
            const row = matrixData[num];
            if (!row) return;
            activePasses.forEach(passId => {
                const item = row[passId];
                if (item && item.id) ids.push(item.id);
            });
        });
        return ids;
    }

    function updateSelectionUI() {
        document.querySelectorAll('.num-btn').forEach(btn => {
            const num = parseInt(btn.dataset.num, 10);
            if (activeNumbers.has(num)) btn.classList.add('selected');
            else btn.classList.remove('selected');
        });

        for (let num = 1; num <= currentConfig.maxDocs; num++) {
            const rowEl = document.getElementById('row_num_' + num);
            if (!rowEl) continue;
            const chkEl = rowEl.querySelector('.row-selector-chk');
            const isSelected = activeNumbers.has(num);
            if (chkEl) chkEl.checked = isSelected;

            rowEl.style.backgroundColor = isSelected ? 'var(--blue-light-bg)' : '';

            rowEl.querySelectorAll('td[data-pass-col]').forEach(td => {
                const pId = parseInt(td.getAttribute('data-pass-col'), 10);
                td.style.opacity = activePasses.has(pId) ? '1' : '0.2';
            });
        }

        const count = getSelectedDocIds().length;
        document.getElementById('selectedCountBadge').textContent = count;
    }

    // -----------------------------------------------------------------------
    // Export Handlers
    // -----------------------------------------------------------------------
    async function fetchExportData() {
        const ids = getSelectedDocIds();
        if (ids.length === 0) {
            alert('No matching documents found.');
            return null;
        }

        const resp = await fetch('api_md.php?action=export_docs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ doc_ids: ids, with_meta: false })
        });
        const res = await resp.json();
        if (res.status !== 'success') throw new Error(res.message || 'Export error');
        return res.snapshot;
    }

    async function exportPassesJson() {
        const btn = document.getElementById('btnDownloadJson');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

        try {
            const snapshot = await fetchExportData();
            if (!snapshot) return;

            const blob = new Blob([JSON.stringify(snapshot, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');

            const numPart = activeNumbers.size === 1 ? `doc${Array.from(activeNumbers)[0]}` : `docs_${activeNumbers.size}`;
            a.href = url;
            a.download = `md_passes_${numPart}_${new Date().toISOString().slice(0, 10)}.json`;
            a.click();
            URL.revokeObjectURL(url);

            showToast(`Exported ${snapshot.documents.length} docs`);
        } catch (e) {
            alert('Export failed: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
            updateSelectionUI();
        }
    }

    async function copyJsonToClipboard() {
        try {
            const snapshot = await fetchExportData();
            if (!snapshot) return;
            const str = JSON.stringify(snapshot, null, 2);
            await navigator.clipboard.writeText(str);
            showToast('JSON copied!');
        } catch (e) {
            alert('Copy failed: ' + e.message);
        }
    }

    // -----------------------------------------------------------------------
    // Profile Modal Handlers
    // -----------------------------------------------------------------------
    function openProfileModal() {
        document.getElementById('profileModal').style.display = 'flex';
        renderModalProfiles();
        syncModalFields(currentConfig);
    }

    function closeProfileModal() {
        document.getElementById('profileModal').style.display = 'none';
    }

    function renderModalProfiles() {
        const sel = document.getElementById('modalProfileSelect');
        sel.innerHTML = '';
        Object.keys(profiles).forEach(pName => {
            const opt = document.createElement('option');
            opt.value = pName;
            opt.textContent = pName;
            if (pName === activeProfileName) opt.selected = true;
            sel.appendChild(opt);
        });
        document.getElementById('modalProfileName').value = activeProfileName;
    }

    function onSelectProfile(pName) {
        if (!profiles[pName]) return;
        activeProfileName = pName;
        document.getElementById('modalProfileName').value = pName;
        syncModalFields(profiles[pName]);
    }

    function syncModalFields(cfg) {
        document.getElementById('modalCategory').value = cfg.categoryId ?? '';
        document.getElementById('modalMaxDocs').value = cfg.maxDocs ?? 30;

        const list = document.getElementById('modalPassesList');
        list.innerHTML = '';
        cfg.passes.forEach((p) => {
            addPassRowDOM(p);
        });
    }

    function addPassRowDOM(p = null) {
        const list = document.getElementById('modalPassesList');
        const passId = p ? p.id : Date.now();
        const label = p ? p.label : `P${list.children.length + 1}`;
        const sublabel = p ? p.sublabel : `Pass ${list.children.length + 1}`;
        const mode = p ? p.mode : 'wildcard';
        const pattern = p ? p.pattern : '';
        const color = p ? p.color : passColors[list.children.length % passColors.length];

        const div = document.createElement('div');
        div.className = 'pass-def-item';
        div.dataset.passId = passId;
        div.innerHTML = `
            <div class="pass-def-row">
                <input type="color" class="pass-color" value="${color}" style="width:26px; height:26px; border:none; padding:0; background:none; cursor:pointer;" title="Badge Color">
                <input type="text" class="input-control pass-lbl" style="width:50px; font-weight:700;" value="${escapeHtml(label)}" placeholder="P1">
                <input type="text" class="input-control pass-sublbl" style="flex:1;" value="${escapeHtml(sublabel)}" placeholder="Description / Name">
                <button type="button" class="btn btn-ghost btn-sm" style="color:var(--red);" onclick="this.closest('.pass-def-item').remove()" title="Delete Pass"><i class="bi bi-trash"></i></button>
            </div>
            <div class="pass-def-row">
                <select class="input-control pass-mode" style="width:110px;">
                    <option value="wildcard" ${mode === 'wildcard' ? 'selected' : ''}>Wildcard (*)</option>
                    <option value="regex" ${mode === 'regex' ? 'selected' : ''}>Regex</option>
                </select>
                <input type="text" class="input-control pass-pat" style="flex:1;" value="${escapeHtml(pattern)}" placeholder="e.g. PROutline* or ^PASS3.*">
            </div>
        `;
        list.appendChild(div);
    }

    function addNewPassRow() {
        addPassRowDOM();
    }

    function createNewProfilePrompt() {
        const name = prompt("Enter new profile name:", "New Profile");
        if (!name || !name.trim()) return;
        const cleanName = name.trim();
        profiles[cleanName] = {
            name: cleanName,
            categoryId: document.getElementById('modalCategory').value,
            maxDocs: parseInt(document.getElementById('modalMaxDocs').value, 10) || 30,
            passes: [
                { id: 1, label: "P1", sublabel: "Pass 1", mode: "wildcard", pattern: "Pass1*", color: "#0969da" }
            ]
        };
        activeProfileName = cleanName;
        renderModalProfiles();
        syncModalFields(profiles[cleanName]);
    }

    function deleteCurrentProfile() {
        const keys = Object.keys(profiles);
        if (keys.length <= 1) {
            alert("Cannot delete the only remaining profile.");
            return;
        }
        if (!confirm(`Delete profile "${activeProfileName}"?`)) return;
        delete profiles[activeProfileName];
        activeProfileName = Object.keys(profiles)[0];
        renderModalProfiles();
        syncModalFields(profiles[activeProfileName]);
    }

    function saveAndApplyProfile() {
        const newName = document.getElementById('modalProfileName').value.trim() || activeProfileName;
        const catId = document.getElementById('modalCategory').value;
        const maxDocs = parseInt(document.getElementById('modalMaxDocs').value, 10) || 30;

        const passItems = document.querySelectorAll('.pass-def-item');
        const passes = [];
        passItems.forEach((el, idx) => {
            passes.push({
                id: idx + 1,
                label: el.querySelector('.pass-lbl').value.trim() || `P${idx + 1}`,
                sublabel: el.querySelector('.pass-sublbl').value.trim() || `Pass ${idx + 1}`,
                color: el.querySelector('.pass-color').value || passColors[idx % passColors.length],
                mode: el.querySelector('.pass-mode').value,
                pattern: el.querySelector('.pass-pat').value.trim()
            });
        });

        if (passes.length === 0) {
            alert("Please configure at least one pass.");
            return;
        }

        if (newName !== activeProfileName) {
            delete profiles[activeProfileName];
            activeProfileName = newName;
        }

        profiles[activeProfileName] = {
            name: activeProfileName,
            categoryId: catId,
            maxDocs: maxDocs,
            passes: passes
        };

        saveProfilesToStorage();
        closeProfileModal();
        applyConfig(profiles[activeProfileName]);
        showToast("Profile applied and saved!");
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    let toastTimer;
    function showToast(msg) {
        const el = document.getElementById('toast');
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'block';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.style.display = 'none'; }, 2200);
    }
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);