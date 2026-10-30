<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = 'Popkorn 🍿 - Player & Downloader';

ob_start();
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="/js/toast.js"></script>

<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Variables ── */
:root {
    --bg:        #07070d;
    --card:      #0f0f1a;
    --border:    #1a1a2e;
    --text:      #d4d4e8;
    --muted:     #4a4a6a;
    --accent:    #facc15;
    --accent-dim: rgba(250, 204, 21, 0.15);
    --green:     #00e5a0;
    --danger:    #ff6584;
    --tap:       48px;
}

html, body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Mono', 'Fira Mono', monospace;
    height: 100dvh;
    overflow: hidden;
}

/* ════════════════════════════════════
   LAYOUT WRAPPER
════════════════════════════════════ */
.rv-layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
.rv-left-col { flex-shrink: 0; display: flex; flex-direction: column; }
.rv-right-col { flex: 1; min-width: 0; display: flex; flex-direction: column; min-height: 0; }
@media (min-width: 900px) {
    .rv-layout { flex-direction: row; align-items: stretch; }
    .rv-left-col { width: 400px; max-height: 100dvh; border-right: 1px solid var(--border); }
}

/* ════════════════════════════════════
   PLAYER
════════════════════════════════════ */
.rv-player-wrap { position: relative; background: #000; width: 100%; flex-shrink: 0; }
.rv-player-wrap video { width: 100%; aspect-ratio: 16/9; display: block; background: #000; }
.rv-player-placeholder {
    aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center;
    background: #000; color: var(--muted); font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase;
}

/* ════════════════════════════════════
   INFO BAR
════════════════════════════════════ */
.rv-info { background: var(--card); border-bottom: 1px solid var(--border); padding: 8px 12px 6px; flex-shrink: 0; }
.rv-video-name { font-size: 0.78rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; color: var(--accent); }
.rv-meta-row { display: flex; gap: 10px; font-size: 0.65rem; color: var(--muted); flex-wrap: wrap; }
.rv-progress-track { height: 4px; background: var(--border); cursor: pointer; margin-top: 7px; border-radius: 2px; overflow: hidden; }
.rv-progress-fill { height: 100%; background: var(--accent); width: 0%; pointer-events: none; transition: width 0.15s linear; }

/* ════════════════════════════════════
   ACTION BUTTONS
════════════════════════════════════ */
.rv-actions {
    display: grid; gap: 4px; padding: 6px 8px;
    background: var(--card); border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.rv-btn {
    min-height: var(--tap); border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text); font-family: inherit; font-size: 0.75rem;
    font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 4px;
    transition: all 0.1s; -webkit-tap-highlight-color: transparent; user-select: none;
}
.rv-btn:disabled { opacity: 0.3; pointer-events: none; }
.rv-btn:active { transform: scale(0.96); }

.rv-btn-nav:active { border-color: var(--accent); color: var(--accent); }
.rv-btn-sel { border-color: var(--border); color: var(--text); }
.rv-btn-sel.active-sel { border-color: var(--green); color: var(--green); background: rgba(0,229,160,0.15); }
.rv-btn-pot { border-color: var(--accent); color: var(--accent); }
.rv-btn-dl { border-color: var(--border); color: var(--text); }
.rv-btn-dl:not(:disabled) { border-color: var(--green); color: var(--green); }

/* ════════════════════════════════════
   BATCH TOOLBAR & PAGINATION
════════════════════════════════════ */
.rv-pg-bar {
    position: sticky; top: 0; z-index: 50; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 6px; padding: 6px 10px; flex-wrap: wrap; flex-shrink: 0; min-height: 44px;
}
.rv-pg-btn {
    min-width: 50px; min-height: 30px; background: transparent; border: 1px solid var(--border);
    color: var(--text); border-radius: 4px; cursor: pointer; font-size: 0.75rem;
    display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.1s; padding: 0 10px;
}
.rv-pg-btn:active, .rv-pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }

/* ════════════════════════════════════
   VIDEO GRID
════════════════════════════════════ */
.rv-grid-section { padding: 6px; padding-bottom: calc(12px + env(safe-area-inset-bottom)); flex: 1; overflow-y: auto; min-height: 0; }
.rv-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
@media (min-width: 500px) { .rv-grid { grid-template-columns: repeat(3, 1fr); } }

.rv-card {
    position: relative; aspect-ratio: 16/9; background: #111; border-radius: 3px; overflow: hidden;
    cursor: pointer; border: 2px solid transparent; -webkit-tap-highlight-color: transparent; transition: all 0.15s;
}
.rv-card.active { border-color: #fff; z-index: 2; }
.rv-card.selected-for-dl { border-color: var(--green) !important; box-shadow: 0 0 0 2px var(--green) inset !important; opacity: 1 !important; filter: none !important;}
.rv-card.selected-for-dl::after { content: '✅'; position: absolute; top: 2px; right: 3px; font-size: 14px; filter: drop-shadow(0 0 4px rgba(0,0,0,0.8)); z-index: 5; }
.rv-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.rv-card-id { position: absolute; bottom: 2px; left: 3px; font-size: 0.5rem; color: rgba(255,255,255,0.7); pointer-events: none; background: rgba(0,0,0,0.5); padding: 1px 4px; border-radius: 2px; z-index: 4;}

/* ════════════════════════════════════
   STATE MESSAGES
════════════════════════════════════ */
.rv-state { padding: 40px 20px; text-align: center; color: var(--muted); font-size: 0.75rem; display: flex; flex-direction: column; align-items: center; gap: 10px; grid-column: 1/-1;}
.rv-spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ════════════════════════════════════
   MODALS (Pots)
════════════════════════════════════ */
.rv-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 200; display: none; align-items: flex-end; justify-content: center; padding: 0; }
.rv-modal-overlay.active { display: flex; }
@media (min-width: 600px) { .rv-modal-overlay { align-items: center; padding: 20px; } }

.rv-modal-sheet { width: 100%; max-width: 520px; background: var(--card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; display: flex; flex-direction: column; max-height: 85dvh; overflow: hidden; }
@media (min-width: 600px) { .rv-modal-sheet { border-radius: 10px; max-height: 80dvh; } }

.rv-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.rv-modal-title { font-size: 0.85rem; font-weight: 700; color: var(--text); letter-spacing: 1px; text-transform: uppercase; }
.rv-modal-close { width: 32px; height: 32px; background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; }
.rv-modal-close:active { color: var(--danger); border-color: var(--danger); }

.rv-fuzz-search { padding: 8px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.rv-fuzz-search input { width: 100%; padding: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; font-family: inherit; font-size: 0.8rem; }
.rv-fuzz-search input:focus { outline: none; border-color: var(--accent); }

.rv-fuzz-list { flex: 1; overflow-y: auto; padding: 6px; background: var(--bg); }
.rv-fuzz-list::-webkit-scrollbar { width: 3px; }
.rv-fuzz-list::-webkit-scrollbar-thumb { background: var(--border); }

.filter-pg { flex-shrink: 0; border-top: 1px solid var(--border); padding: 8px 10px; display: flex; align-items: center; gap: 4px; background: var(--bg); }
.filter-pg-btn { width: 32px; height: 32px; background: transparent; border: 1px solid var(--border); border-radius: 3px; color: var(--text); font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.filter-pg-btn:disabled { opacity: 0.3; pointer-events: none; }
.filter-pg-input { width: 40px; text-align: center; background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 3px; color: var(--accent); font-family: inherit; font-size: 0.8rem; font-weight: 700; padding: 4px; }
.filter-pg-of { font-size: 0.75rem; color: var(--muted); white-space: nowrap; flex: 1; text-align: center; }

/* Pot List inside Modal */
.pot-item { display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--border); background: var(--card); border-radius: 4px; margin-bottom: 6px; transition: 0.2s;}
.pot-item.active-pot { border-color: var(--accent); background: var(--accent-dim); }
.pot-item-info { flex: 1; min-width: 0; cursor: pointer; }
.pot-item-title { font-size: 0.8rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pot-item-meta { font-size: 0.65rem; color: var(--muted); margin-top: 2px; }
.pot-btn { background: transparent; border: 1px solid var(--border); color: var(--muted); padding: 5px 8px; border-radius: 3px; cursor: pointer; font-size: 0.7rem; transition: 0.1s;}
.pot-btn:hover { color: var(--text); border-color: var(--text); }
.pot-btn.del:hover { color: var(--danger); border-color: var(--danger); }

.rv-confirm-btn { flex: 1; min-height: var(--tap); background: var(--accent); border: none; color: #000; font-family: inherit; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; border-radius: 4px; cursor: pointer; transition: 0.1s; }
.rv-confirm-btn:active { opacity: 0.8; }
</style>

<div class="rv-layout">

    <!-- LEFT COL / TOP -->
    <div class="rv-left-col">

        <div class="rv-player-wrap">
            <div class="rv-player-placeholder" id="playerPlaceholder">select a video</div>
            <video id="mainPlayer" style="display:none;" controls playsinline preload="metadata"></video>
        </div>

        <div class="rv-info">
            <div class="rv-video-name" id="videoName">—</div>
            <div class="rv-meta-row">
                <span id="videoIdEl">—</span>
                <span id="videoDurEl">—</span>
                <span id="videoSizeEl">—</span>
            </div>
            <div class="rv-progress-track" id="progressTrack">
                <div class="rv-progress-fill" id="progressFill"></div>
            </div>
        </div>

        <div class="rv-actions" style="grid-template-columns: repeat(6, 1fr);">
            <button class="rv-btn rv-btn-nav" id="btnPrev" disabled onclick="navigate(-1)">◀</button>
            <button class="rv-btn rv-btn-sel" id="btnSelectMode" onclick="toggleSelectMode()" title="Mass Selection">✅</button>
            <button class="rv-btn rv-btn-pot" id="btnChoosePot" onclick="openPotsModal()" title="Choose Pot">🍿</button>
            <button class="rv-btn rv-btn-dl" id="btnDownload" onclick="downloadSelected()" title="Download" disabled>⬇️</button>
            <a href="popkorn.php" class="rv-btn" style="text-decoration:none; border-color: var(--accent); color: var(--accent);" title="Switch to Manager">🛠️</a>
            <button class="rv-btn rv-btn-nav" id="btnNext" disabled onclick="navigate(1)">▶</button>
        </div>

    </div><!-- /.rv-left-col -->

    <!-- RIGHT COL / BOTTOM -->
    <div class="rv-right-col">

        <!-- BATCH TOOLBAR & PAGINATION -->
        <div class="rv-pg-bar">
            <button class="rv-pg-btn" id="btnSelectAll" style="display:none; border-color:var(--green); color:var(--green);" onclick="selectAll()">ALL</button>
            <span style="font-size:0.75rem; color:var(--accent); font-weight:bold; margin-left:10px;" id="activePotDisplay">All Videos</span>
            <span style="font-size:0.75rem; color:var(--muted); margin-left:10px;" id="gridCounter">0 videos</span>
            
            <div id="selectionCounter" style="display:none; align-items:center; margin-left:auto; font-size:0.75rem; color:var(--green); font-weight:bold;">
                0 selected
            </div>
            
            <div id="gridPagination" style="display:none; align-items:center; gap:2px; margin-left:auto;">
                <button class="rv-pg-btn" id="gpPrev" onclick="changeGridPage(-1)" style="min-width:28px;">‹</button>
                <input type="number" class="filter-pg-input" id="gpInput" value="1" onchange="jumpToGridPage()" style="margin: 0 4px; border-color:var(--border);">
                <span class="filter-pg-of" id="gpTotalPages" style="margin-right: 8px;">/ 1</span>
                <button class="rv-pg-btn" id="gpNext" onclick="changeGridPage(1)" style="min-width:28px;">›</button>
            </div>
        </div>

        <div class="rv-grid-section">
            <div class="rv-state" id="gridState" style="display:none;">
                <div class="rv-spinner"></div>
                <span>Loading…</span>
            </div>
            <div class="rv-grid" id="videoGrid">
                <div class="rv-state" style="grid-column: 1/-1;">Open (🍿) to select a pot to play.</div>
            </div>
        </div>

    </div><!-- /.rv-right-col -->

</div><!-- /.rv-layout -->


<!-- ══ POTS MANAGER MODAL ══ -->
<div class="rv-modal-overlay" id="potsModal">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">🍿 Choose Pot</span>
            <button class="rv-modal-close" onclick="closePotsModal()">✕</button>
        </div>
        
        <div style="padding: 10px; border-bottom: 1px solid var(--border);">
            <button class="rv-confirm-btn" style="width:100%; background:transparent; border:1px solid var(--accent); color:var(--accent);" onclick="selectAllVideosMode()">🌍 View All Videos (Exit Pot)</button>
        </div>
        
        <div style="padding: 10px; border-bottom: 1px solid var(--border); display:flex; gap: 8px;">
            <input type="text" id="newPotName" class="rv-pg-btn" style="flex:1; text-align:left; border-color:var(--border);" placeholder="Create new pot name..." onkeydown="if(event.key==='Enter') createPot()">
            <button class="rv-confirm-btn" style="flex: 0.3; min-height:32px;" onclick="createPot()">Create</button>
        </div>
        
        <div class="rv-fuzz-search">
            <input type="text" id="potsSearchInput" placeholder="Search pots..." oninput="debouncePotsSearch(this.value)">
        </div>
        
        <div class="rv-fuzz-list" id="potsList">
            <div style="color:var(--muted);font-size:0.75rem;padding:12px;text-align:center;">Loading…</div>
        </div>
        
        <div class="filter-pg" id="pots-pg" style="display:none;">
            <button class="filter-pg-btn" id="pots-prev" disabled>‹</button>
            <input type="number" class="filter-pg-input" id="pots-pg-input" value="1" min="1">
            <span class="filter-pg-of" id="pots-pg-of">/ 1</span>
            <button class="filter-pg-btn" id="pots-next" disabled>›</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Global State ──
    let videos = [];
    let curIndex = -1;
    let selectMode = false;
    let selectedVideos = new Set();
    
    // Grid Pagination
    let gridPage = 1;
    let gridTotalPages = 1;
    
    // Pot State
    let activePotId = localStorage.getItem('popkorn_player_pot_id') || null;
    let activePotName = localStorage.getItem('popkorn_player_pot_name') || 'All Videos';
    
    // Modal Pagination
    let potsCurPage = 1, potsTotalPages = 1, potsDebounceTimer = null;

    // ── DOM Elements ──
    const player        = document.getElementById('mainPlayer');
    const placeholder   = document.getElementById('playerPlaceholder');
    const videoNameEl   = document.getElementById('videoName');
    const videoIdEl     = document.getElementById('videoIdEl');
    const videoDurEl    = document.getElementById('videoDurEl');
    const videoSizeEl   = document.getElementById('videoSizeEl');
    const progressFill  = document.getElementById('progressFill');
    const progressTrack = document.getElementById('progressTrack');
    const btnPrev       = document.getElementById('btnPrev');
    const btnNext       = document.getElementById('btnNext');
    const btnDownload   = document.getElementById('btnDownload');
    const gridEl        = document.getElementById('videoGrid');
    const gridState     = document.getElementById('gridState');

    // ── Helpers ──
    function fmtDur(s) { if (!s) return '0:00'; const m = Math.floor(s/60), sc = Math.floor(s%60); return `${m}:${sc.toString().padStart(2,'0')}`; }
    function fmtSize(b) { return b ? (b/1024/1024).toFixed(1)+' MB' : ''; }
    function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    
    async function apiCall(action, data = null) {
        const options = data ? { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) } : {};
        const url = 'popkorn_api.php?action=' + action;
        try {
            const res = await fetch(url, options);
            const json = await res.json();
            if (json.status !== 'success') throw new Error(json.message);
            return json;
        } catch (e) {
            Toast.show(e.message || 'API Error', 'error');
            throw e;
        }
    }

    // ════════════════════════════════════
    // SELECTION MODE & DOWNLOAD
    // ════════════════════════════════════
    window.toggleSelectMode = () => {
        selectMode = !selectMode;
        document.getElementById('btnSelectMode').classList.toggle('active-sel', selectMode);
        
        if (!selectMode) {
            selectedVideos.clear();
            renderGrid();
        }
        updateSelectionUI();
    };

    window.selectAll = () => {
        if (selectedVideos.size === videos.length) {
            selectedVideos.clear();
        } else {
            videos.forEach(v => selectedVideos.add(v.id));
        }
        renderGrid();
        updateSelectionUI();
    };

    function updateSelectionUI() {
        const selCount = selectedVideos.size;
        const counterEl = document.getElementById('selectionCounter');
        const selectAllBtn = document.getElementById('btnSelectAll');
        const pgEl = document.getElementById('gridPagination');
        
        if (selectMode) {
            selectAllBtn.style.display = 'flex';
            counterEl.style.display = 'flex';
            if(pgEl) pgEl.style.display = 'none'; // hide pg while selecting
            counterEl.textContent = `${selCount} selected`;
            btnDownload.disabled = selCount === 0;
            btnDownload.textContent = `⬇️ (${selCount})`;
        } else {
            selectAllBtn.style.display = 'none';
            counterEl.style.display = 'none';
            if(pgEl && !activePotId && gridTotalPages > 1) pgEl.style.display = 'flex'; // restore pg
            const canDownloadCurrent = curIndex >= 0;
            btnDownload.disabled = !canDownloadCurrent;
            btnDownload.textContent = '⬇️';
        }
    }

    window.downloadSelected = async () => {
        if (!selectMode) {
            if (curIndex >= 0) downloadVideo(videos[curIndex]);
            return;
        }

        if (selectedVideos.size === 0) return;
        
        Toast.show(`Starting download for ${selectedVideos.size} videos...`);
        for (let id of selectedVideos) {
            const v = videos.find(vid => vid.id === id);
            if (v) {
                downloadVideo(v);
                await new Promise(r => setTimeout(r, 600)); // slight stagger
            }
        }
        
        selectedVideos.clear();
        renderGrid();
        updateSelectionUI();
    };

    function downloadVideo(v) {
        const a = document.createElement('a');
        a.href = v.url;
        a.download = v.name ? (v.name + '.mp4') : ('video_' + v.id + '.mp4');
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // ════════════════════════════════════
    // VIDEO LOADING & GRID
    // ════════════════════════════════════
    function loadPotVideos(potId) {
        if (!potId) return;
        
        gridState.style.display = 'flex';
        gridEl.style.display = 'none';
        resetPlayer();
        selectedVideos.clear();
        updateSelectionUI();

        fetch(`popkorn_api.php?action=get_pot_videos&pot_id=${potId}`)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') throw new Error();
                
                videos = res.data || [];
                document.getElementById('gridCounter').textContent = `${videos.length} videos`;
                document.getElementById('gridPagination').style.display = 'none'; // Pots aren't paginated
                
                renderGrid();
                if (videos.length > 0) playVideo(0);
            })
            .catch(() => {
                gridState.style.display = 'none';
                gridEl.style.display = 'grid';
                gridEl.innerHTML = '<div class="rv-state" style="grid-column: 1/-1; color:var(--danger);">Failed to load pot videos.</div>';
            });
    }

    function loadAllVideos(page = 1) {
        gridState.style.display = 'flex';
        gridEl.style.display = 'none';
        resetPlayer();
        selectedVideos.clear();
        updateSelectionUI();

        fetch(`popkorn_api.php?action=get_videos&page=${page}`)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') throw new Error();
                
                videos = res.data || [];
                gridPage = res.page;
                gridTotalPages = res.total_pages;

                document.getElementById('gridCounter').textContent = `${res.total} videos`;
                document.getElementById('gpInput').value = gridPage;
                document.getElementById('gpTotalPages').textContent = '/ ' + gridTotalPages;
                document.getElementById('gpPrev').disabled = (gridPage <= 1);
                document.getElementById('gpNext').disabled = (gridPage >= gridTotalPages);
                document.getElementById('gridPagination').style.display = (gridTotalPages > 1 && !selectMode) ? 'flex' : 'none';

                renderGrid();
                if (videos.length > 0) playVideo(0);
            }).catch(() => {
                gridState.style.display = 'none';
                gridEl.style.display = 'grid';
                gridEl.innerHTML = '<div class="rv-state" style="grid-column: 1/-1; color:var(--danger);">Failed to load videos.</div>';
            });
    }

    window.selectAllVideosMode = (init = false) => {
        activePotId = null;
        activePotName = 'All Videos';
        localStorage.removeItem('popkorn_player_pot_id');
        localStorage.removeItem('popkorn_player_pot_name');
        document.getElementById('activePotDisplay').textContent = 'All Videos';
        if (!init) window.closePotsModal();
        loadAllVideos(1);
    };

    window.changeGridPage = (dir) => {
        if (activePotId) return;
        const next = gridPage + dir;
        if (next >= 1 && next <= gridTotalPages) loadAllVideos(next);
    };

    window.jumpToGridPage = () => {
        if (activePotId) return;
        const v = parseInt(document.getElementById('gpInput').value, 10);
        if (!isNaN(v) && v >= 1 && v <= gridTotalPages) loadAllVideos(v);
        else document.getElementById('gpInput').value = gridPage;
    };

    function renderGrid() {
        gridState.style.display = 'none';
        gridEl.style.display = 'grid';

        if (!videos.length) {
            gridEl.innerHTML = '<div class="rv-state" style="grid-column: 1/-1;">No videos found.</div>';
            return;
        }

        gridEl.innerHTML = videos.map((v, i) => {
            const isSelected = selectedVideos.has(v.id);
            return `
            <div class="rv-card ${i === curIndex ? 'active' : ''} ${isSelected ? 'selected-for-dl' : ''}"
                 data-index="${i}" data-id="${v.id}">
                <img src="${escH(v.thumbnail||'')}" loading="lazy">
                <span class="rv-card-id">#${v.id}</span>
            </div>
            `;
        }).join('');

        gridEl.querySelectorAll('.rv-card').forEach(card => {
            card.addEventListener('click', () => {
                const idx = parseInt(card.dataset.index);
                const vid = videos[idx];
                if (selectMode) {
                    if (selectedVideos.has(vid.id)) selectedVideos.delete(vid.id);
                    else selectedVideos.add(vid.id);
                    renderGrid();
                    updateSelectionUI();
                } else {
                    playVideo(idx);
                }
            });
        });
    }

    // ════════════════════════════════════
    // PLAYER & NAVIGATION
    // ════════════════════════════════════
    function playVideo(index) {
        if (index < 0 || index >= videos.length) return;
        curIndex = index;
        const v = videos[index];

        placeholder.style.display = 'none';
        player.style.display = 'block';
        player.src = v.url;
        player.load();
        player.play().catch(() => {});

        videoNameEl.textContent = v.name || ('Video #' + v.id);
        videoIdEl.textContent = '#' + v.id;
        videoDurEl.textContent = fmtDur(v.duration);
        videoSizeEl.textContent = fmtSize(v.file_size);

        updateNavButtons();
        highlightCard(index);
        updateSelectionUI(); // Updates download button state
    }

    function resetPlayer() {
        curIndex = -1;
        player.pause();
        player.src = '';
        player.style.display = 'none';
        placeholder.style.display = 'flex';
        videoNameEl.textContent = '—';
        videoIdEl.textContent = '—';
        videoDurEl.textContent = '—';
        videoSizeEl.textContent = '—';
        updateNavButtons();
        updateSelectionUI();
    }

    function highlightCard(index) {
        gridEl.querySelectorAll('.rv-card').forEach((c, i) => c.classList.toggle('active', i === index));
        const card = gridEl.querySelector(`.rv-card[data-index="${index}"]`);
        if (card) card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function updateNavButtons() {
        if (activePotId) {
            btnPrev.disabled = curIndex <= 0;
            btnNext.disabled = curIndex < 0 || curIndex >= videos.length - 1;
        } else {
            btnPrev.disabled = curIndex <= 0 && gridPage <= 1;
            btnNext.disabled = (curIndex < 0 || curIndex >= videos.length - 1) && gridPage >= gridTotalPages;
        }
    }

    window.navigate = (dir) => {
        const next = curIndex + dir;
        if (next >= 0 && next < videos.length) {
            playVideo(next);
        } else if (!activePotId && dir > 0 && gridPage < gridTotalPages) {
            loadAllVideos(gridPage + 1); // auto-plays first video on new page
        } else if (!activePotId && dir < 0 && gridPage > 1) {
            loadAllVideos(gridPage - 1); // auto-plays first video on previous page
        }
    };

    player.addEventListener('timeupdate', () => {
        if (player.duration) progressFill.style.width = (player.currentTime / player.duration * 100) + '%';
    });
    progressTrack.addEventListener('click', e => {
        if (!player.duration) return;
        const r = progressTrack.getBoundingClientRect();
        player.currentTime = ((e.clientX - r.left) / r.width) * player.duration;
    });

    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        switch (e.key) {
            case 'ArrowRight': e.preventDefault(); window.navigate(1); break;
            case 'ArrowLeft':  e.preventDefault(); window.navigate(-1); break;
            case 's': case 'S': e.preventDefault(); window.toggleSelectMode(); break;
            case 'd': case 'D': e.preventDefault(); window.downloadSelected(); break;
            case 'Escape': window.closePotsModal(); break;
            case ' ': e.preventDefault(); player.paused ? player.play() : player.pause(); break;
        }
    });

    // ════════════════════════════════════
    // POTS MANAGER
    // ════════════════════════════════════
    window.openPotsModal = () => {
        document.getElementById('potsModal').classList.add('active');
        loadPotsList(1);
    };
    window.closePotsModal = () => document.getElementById('potsModal').classList.remove('active');

    window.debouncePotsSearch = (val) => {
        clearTimeout(potsDebounceTimer);
        potsDebounceTimer = setTimeout(() => loadPotsList(1, val), 300);
    };

    function loadPotsList(page = 1, search = '') {
        potsCurPage = page;
        const listEl = document.getElementById('potsList');
        listEl.innerHTML = '<div style="color:var(--muted);font-size:0.75rem;padding:20px;text-align:center;"><div class="rv-spinner" style="margin:0 auto 10px;"></div>Loading Pots…</div>';

        fetch(`popkorn_api.php?action=list_pots&page=${page}&search=${encodeURIComponent(search)}`)
            .then(r => r.json())
            .then(d => {
                if (d.status !== 'success') throw new Error();
                const pots = d.data || [];
                potsTotalPages = d.total_pages || 1;

                document.getElementById('pots-pg-input').value = potsCurPage;
                document.getElementById('pots-pg-of').textContent = '/ ' + potsTotalPages;
                document.getElementById('pots-prev').disabled = potsCurPage <= 1;
                document.getElementById('pots-next').disabled = potsCurPage >= potsTotalPages;
                document.getElementById('pots-pg').style.display = potsTotalPages > 1 ? 'flex' : 'none';

                if (!pots.length) {
                    listEl.innerHTML = '<div style="color:var(--muted);font-size:0.75rem;padding:20px;text-align:center;">No pots found. Create one!</div>';
                    return;
                }
                listEl.innerHTML = pots.map(p => `
                    <div class="pot-item ${activePotId == p.id ? 'active-pot' : ''}">
                        <div class="pot-item-info" onclick="selectPot(${p.id}, '${escH(p.name)}')">
                            <div class="pot-item-title">${escH(p.name)}</div>
                            <div class="pot-item-meta">${p.video_count} videos • Updated ${p.updated_at.substr(0,10)}</div>
                        </div>
                        <button class="pot-btn" onclick="renamePot(${p.id}, '${escH(p.name)}')"><i class="bi bi-pencil"></i></button>
                        <button class="pot-btn del" onclick="deletePot(${p.id})"><i class="bi bi-trash"></i></button>
                    </div>
                `).join('');
            }).catch(() => {
                listEl.innerHTML = '<div style="color:var(--danger);font-size:0.75rem;padding:20px;text-align:center;">Error loading pots.</div>';
            });
    }

    document.getElementById('pots-prev').addEventListener('click', () => loadPotsList(potsCurPage - 1, document.getElementById('potsSearchInput').value));
    document.getElementById('pots-next').addEventListener('click', () => loadPotsList(potsCurPage + 1, document.getElementById('potsSearchInput').value));
    document.getElementById('pots-pg-input').addEventListener('change', function() {
        const v = parseInt(this.value, 10);
        if (!isNaN(v) && v >= 1 && v <= potsTotalPages) loadPotsList(v, document.getElementById('potsSearchInput').value);
        else this.value = potsCurPage;
    });

    window.createPot = async () => {
        const name = document.getElementById('newPotName').value.trim();
        if (!name) return;
        try {
            const res = await apiCall('create_pot', { name });
            Toast.show('Pot created', 'success');
            document.getElementById('newPotName').value = '';
            window.selectPot(res.id, res.name);
        } catch(e){}
    };

    window.renamePot = async (id, oldName) => {
        const name = prompt("Rename pot:", oldName);
        if (!name || name === oldName) return;
        try {
            await apiCall('rename_pot', { id, name });
            Toast.show('Renamed', 'success');
            if (activePotId == id) {
                activePotName = name;
                localStorage.setItem('popkorn_player_pot_name', name);
                document.getElementById('activePotDisplay').textContent = name;
            }
            loadPotsList(potsCurPage, document.getElementById('potsSearchInput').value);
        } catch(e){}
    };

    window.deletePot = async (id) => {
        if (!confirm('Delete this pot and its video mappings? (Videos will NOT be deleted)')) return;
        try {
            await apiCall('delete_pot', { id });
            Toast.show('Pot deleted');
            if (activePotId == id) {
                window.selectAllVideosMode();
            }
            loadPotsList(potsCurPage, document.getElementById('potsSearchInput').value);
        } catch(e){}
    };

    window.selectPot = (id, name) => {
        activePotId = id;
        activePotName = name;
        localStorage.setItem('popkorn_player_pot_id', id);
        localStorage.setItem('popkorn_player_pot_name', name);
        document.getElementById('activePotDisplay').textContent = name;
        window.closePotsModal();
        loadPotVideos(id);
        Toast.show(`Loaded Pot: ${name}`);
    };

    // ════════════════════════════════════
    // INIT
    // ════════════════════════════════════
    if (activePotId) {
        document.getElementById('activePotDisplay').textContent = activePotName;
        loadPotVideos(activePotId);
    } else {
        window.selectAllVideosMode(true);
    }

})();
</script>

<?php
$content = ob_get_clean();
echo $content;
?>