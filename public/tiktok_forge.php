<?php
// public/tiktok_forge.php
// ─────────────────────────────────────────────────────────────────────────────
// TIKTOK FORGE — Zero-persistence Reel Downloader
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

$isEmbed       = !empty($_GET['embed']);
$viewportScale = $isEmbed ? '1.0' : '0.9';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= $viewportScale ?>, viewport-fit=cover">
<title>TikTok Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<script>
// THEME RESTORATION
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t) document.documentElement.setAttribute('data-theme', t);
    } catch(e) {}
})();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   FORGE — Design System
═══════════════════════════════════════════════════════════════════════════ */
:root {
    --bg:           #080b10;
    --surface:      #0e1319;
    --card:         #111820;
    --card-hover:   #141e28;
    --border:       #1c2535;
    --border-glow:  #2a3a52;
    --text:         #c8d4e8;
    --text-dim:     #5a6a80;
    --text-bright:  #e8f0ff;
    --amber:        #f5a623;
    --amber-dim:    rgba(245,166,35,.08);
    --amber-mid:    rgba(245,166,35,.15);
    --amber-glow:   rgba(245,166,35,.4);
    --green:        #22d3a0;
    --green-dim:    rgba(34,211,160,.1);
    --red:          #f05060;
    --red-dim:      rgba(240,80,96,.1);
    --blue:         #4da6ff;
    --blue-dim:     rgba(77,166,255,.1);
    --mono:         'Space Mono','Fira Mono',monospace;
    --sans:         'Syne',system-ui,sans-serif;
    --radius:       6px;
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

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:var(--sans);font-size:14px;line-height:1.5;overflow:hidden}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-glow);border-radius:4px}

.forge-layout{display:grid;grid-template-rows:52px 1fr;grid-template-columns:320px 1fr;grid-template-areas:"header header" "sidebar main";height:100vh;overflow:hidden}
@media(max-width:768px){ .forge-layout{grid-template-columns:1fr;grid-template-rows:52px auto 1fr;grid-template-areas:"header" "sidebar" "main"} }

.forge-header{grid-area:header;display:flex;align-items:center;justify-content:space-between;padding:0 20px;background:var(--surface);border-bottom:1px solid var(--border);z-index:100}
.forge-logo{display:flex;align-items:center;gap:10px;font-family:var(--mono);font-size:.85rem;font-weight:700;color:var(--amber);letter-spacing:2px;text-transform:uppercase}
.forge-logo-icon{width:28px;height:28px;background:var(--amber-mid);border:1px solid var(--amber-glow);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:14px}

.forge-sidebar{grid-area:sidebar;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.sidebar-tabs{display:flex;border-bottom:1px solid var(--border);flex-shrink:0}
.sidebar-tab{flex:1;padding:10px 0;font-family:var(--mono);font-size:.7rem;text-transform:uppercase;letter-spacing:1px;text-align:center;color:var(--amber);border-bottom:2px solid var(--amber)}

/* Pagination specific constraints */
.sidebar-pane{flex:1; display:flex; flex-direction:column; padding:0;}
#queueList{max-height:200px; overflow-y:auto; padding:8px;}
.pagination-footer { display:flex; align-items:center; justify-content:space-between; padding:10px; border-top:1px solid var(--border); background:var(--surface); }
.page-btn { background:var(--card); border:1px solid var(--border); color:var(--text); padding:4px 12px; border-radius:4px; font-family:var(--mono); font-size:0.75rem; cursor:pointer; transition:all 0.15s; }
.page-btn:hover:not(:disabled) { border-color:var(--amber); color:var(--amber); }
.page-btn:disabled { opacity:0.5; cursor:not-allowed; }
.page-input { width:45px; background:var(--card); border:1px solid var(--border); color:var(--text); padding:4px; border-radius:4px; font-family:var(--mono); font-size:0.75rem; text-align:center; }
.page-input:focus { outline:none; border-color:var(--amber); }

.item-card{padding:10px 12px;border-radius:var(--radius);border:1px solid transparent;cursor:pointer;margin-bottom:3px;display:flex;align-items:center;gap:10px;background:transparent;transition:all .15s}
.item-card:hover{background:var(--card);border-color:var(--border)}
.item-card.active{background:var(--amber-dim);border-color:var(--amber)}
.item-title{font-family:var(--sans);font-weight:600;font-size:.85rem;color:var(--text-bright);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:4px}
.item-meta{display:flex;align-items:center;gap:5px;flex-wrap:wrap}

.badge{font-family:var(--mono);font-size:.65rem;padding:2px 6px;border-radius:4px;border:1px solid;white-space:nowrap}
.badge-pending{border-color:var(--border-glow);color:var(--text-dim)}
.badge-downloading{border-color:var(--amber);color:var(--amber);background:var(--amber-dim);animation:pulse 1.5s infinite}
.badge-downloaded{border-color:var(--green);color:var(--green);background:var(--green-dim)}
.badge-error{border-color:var(--red);color:var(--red);background:var(--red-dim)}

.forge-main{grid-area:main;display:flex;flex-direction:column;overflow:hidden;background:var(--bg)}
.forge-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:40px;color:var(--text-dim)}
.workspace-body{flex:1;padding:20px;overflow-y:auto;display:none;flex-direction:column;}
.workspace-body.active{display:flex;}

.form-input{width:100%;padding:10px 14px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--mono);font-size:.85rem;transition:all .15s;}
.form-input:focus{outline:none;border-color:var(--amber);background:var(--card-hover)}

.btn-primary{padding:10px 20px;background:var(--amber);color:#000;border:none;border-radius:var(--radius);font-family:var(--mono);font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .15s}
.btn-primary:hover:not(:disabled){filter:brightness(1.15);transform:translateY(-1px)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;}
.btn-secondary{padding:10px 20px;background:transparent;color:var(--text);border:1px solid var(--border);border-radius:var(--radius);font-family:var(--mono);font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;}
.btn-secondary:hover{border-color:var(--amber);color:var(--amber);}
.btn-success{padding:16px 20px;background:var(--green);color:#000;border:none;border-radius:var(--radius);font-family:var(--mono);font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:all .15s;text-decoration:none;}
.btn-success:hover{filter:brightness(1.15);transform:translateY(-1px)}
.btn-icon-sm{width:30px;height:30px;border-radius:var(--radius);border:1px solid var(--border);background:transparent;color:var(--text-dim);cursor:pointer;display:flex;align-items:center;justify-content:center;}
.btn-icon-sm:hover{border-color:var(--red);color:var(--red);background:var(--red-dim)}

.forge-toast-container{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;}
.forge-toast{padding:10px 16px;border-radius:var(--radius);background:var(--card);border:1px solid var(--border);font-family:var(--mono);font-size:.8rem;color:var(--text);box-shadow:0 4px 20px rgba(0,0,0,.5);animation:toastIn .2s ease;}

@keyframes pulse{0%,100%{box-shadow:0 0 0 0 var(--amber-glow)}50%{box-shadow:0 0 0 5px rgba(245,166,35,0)}}
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>

<div class="forge-layout">
    <header class="forge-header">
        <div class="forge-logo">
            <div class="forge-logo-icon"><i class="bi bi-tiktok"></i></div>
            <span>TikTok Forge</span>
        </div>
        <div class="forge-header-right">
            <button class="btn-icon-sm" onclick="TF.loadQueue()" style="border-color:var(--amber);color:var(--amber);">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </header>

    <aside class="forge-sidebar">
        <div class="sidebar-tabs">
            <div class="sidebar-tab active">Run History</div>
        </div>
        
        <div style="padding:15px; border-bottom: 1px solid var(--border);">
            <input type="text" id="newUrl" class="form-input" style="margin-bottom:8px;" placeholder="Paste Instagram Reel URL...">
            <button class="btn-primary" style="width:100%; justify-content:center; font-size: 0.75rem;" onclick="TF.fetchReel()" id="btnFetch">
                <i class="bi bi-cloud-arrow-down-fill"></i> ADD & FETCH
            </button>
        </div>

        <div class="sidebar-pane">
            <div id="queueList">
                <div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">Loading…</div>
            </div>
            
            <!-- AJAX Pagination Footer -->
            <div class="pagination-footer">
                <button class="page-btn" id="btnPrevPage" onclick="TF.prevPage()">Prev</button>
                <div style="font-family:var(--mono); font-size:0.75rem; color:var(--text-dim);">
                    Page <input type="number" id="pageInput" class="page-input" value="1" min="1" onchange="TF.goToPage(this.value)"> of <span id="totalPages">1</span>
                </div>
                <button class="page-btn" id="btnNextPage" onclick="TF.nextPage()">Next</button>
            </div>
        </div>
    </aside>

    <main class="forge-main">
        <div class="forge-empty" id="forgeEmpty">
            <div style="font-size:48px;opacity:.3;filter:grayscale(1)"><i class="bi bi-tiktok"></i></div>
            <div style="font-family:var(--mono);font-size:1rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:2px;">Select a Reel</div>
        </div>

        <div class="workspace-body" id="forgeWorkspace">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div style="flex:1;">
                    <div style="font-family:var(--mono);font-size:.65rem;color:var(--amber);text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;">Target URL</div>
                    <div id="wsUrl" style="font-family:var(--mono);font-size:.85rem;word-break:break-all;color:var(--text-bright);margin-bottom:6px;">—</div>
                    <div id="wsStatus" style="margin-bottom:20px;"></div>
                </div>
                <button class="btn-icon-sm" onclick="TF.deleteCurrent()"><i class="bi bi-trash"></i></button>
            </div>

            <!-- DYNAMIC ACTION AREA -->
            <div id="wsActions" style="padding:20px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); text-align:center;">
                <!-- Buttons injected via JS based on state -->
            </div>
            
            <div style="margin-top:auto; padding-top:20px; font-family:var(--mono);font-size:.7rem;color:var(--text-dim);text-align:center;">
                Videos are streamed to your device and instantly deleted from the server.<br>Zero local storage footprint.
            </div>
        </div>
    </main>
</div>

<div class="forge-toast-container" id="toastContainer"></div>

<script>
const PYAPI = '<?= rtrim($PYAPI_URL ?? "http://127.0.0.1:8009", "/") ?>';

const TF = (() => {
    'use strict';

    let _queue = [];
    let _currentItem = null;
    let _pollTimer = null;
    
    // Pagination State
    let _currentPage = 1;
    const _pageSize = 10;

    async function api(path, opts = {}) {
        const url = `${PYAPI}/tiktok${path}`;
        const res = await fetch(url, { headers: { 'Content-Type': 'application/json' }, ...opts });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    function toast(msg, isError = false) {
        const el = document.createElement('div');
        el.className = `forge-toast`;
        if (isError) el.style.borderColor = 'var(--red)';
        el.innerHTML = msg;
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 250); }, 3000);
    }

    async function loadQueue() {
        try {
            const r = await api('/queue');
            if (r.ok) {
                _queue = r.data || [];
                renderQueue();
                checkPollingNeeds();
                
                if (_currentItem) {
                    const updated = _queue.find(q => q.id === _currentItem.id);
                    if (updated) {
                        _currentItem = updated;
                        renderWorkspace(); 
                    } else {
                        closeWorkspace();
                    }
                }
            }
        } catch(e) { console.error(e); }
    }

    function renderQueue() {
        const el = document.getElementById('queueList');
        
        if (!_queue.length) {
            el.innerHTML = `<div style="padding:20px;text-align:center;color:var(--text-dim);font-family:var(--mono);font-size:.8rem;">History is empty.</div>`;
            document.getElementById('totalPages').textContent = '1';
            document.getElementById('pageInput').value = '1';
            document.getElementById('btnPrevPage').disabled = true;
            document.getElementById('btnNextPage').disabled = true;
            return;
        }

        const totalPages = Math.ceil(_queue.length / _pageSize);
        if (_currentPage > totalPages) _currentPage = totalPages;
        if (_currentPage < 1) _currentPage = 1;

        // Update Pagination Controls
        document.getElementById('totalPages').textContent = totalPages;
        document.getElementById('pageInput').value = _currentPage;
        document.getElementById('btnPrevPage').disabled = _currentPage === 1;
        document.getElementById('btnNextPage').disabled = _currentPage === totalPages;

        const startIdx = (_currentPage - 1) * _pageSize;
        const pagedQueue = _queue.slice(startIdx, startIdx + _pageSize);

        el.innerHTML = pagedQueue.map(q => {
            const isCur = _currentItem && _currentItem.id === q.id;
            let title = q.url.split('?')[0];
            return `
            <div class="item-card${isCur?' active':''}" onclick="TF.selectItem(${q.id})">
                <div style="flex:1; min-width:0;">
                    <div class="item-title">${esc(title)}</div>
                    <div class="item-meta"><span class="badge badge-${q.status}">${q.status.toUpperCase()}</span></div>
                </div>
            </div>`;
        }).join('');
    }

    // Pagination Actions
    function prevPage() {
        if (_currentPage > 1) {
            _currentPage--;
            renderQueue();
        }
    }

    function nextPage() {
        const totalPages = Math.ceil(_queue.length / _pageSize);
        if (_currentPage < totalPages) {
            _currentPage++;
            renderQueue();
        }
    }

    function goToPage(val) {
        let p = parseInt(val, 10);
        const totalPages = Math.ceil(_queue.length / _pageSize);
        if (isNaN(p) || p < 1) p = 1;
        if (p > totalPages) p = totalPages;
        _currentPage = p;
        document.getElementById('pageInput').value = _currentPage;
        renderQueue();
    }

    async function fetchReel() {
        const input = document.getElementById('newUrl');
        const url = input.value.trim();
        if (!url) return;

        document.getElementById('btnFetch').disabled = true;
        try {
            const r = await api('/download', { method: 'POST', body: JSON.stringify({ url }) });
            if (r.ok) {
                input.value = '';
                toast('Download queued!');
                _currentPage = 1; // reset to first page to see the new item
                await loadQueue();
                selectItem(r.data.id);
            }
        } catch(e) { toast('Error queueing download', true); } 
        finally { document.getElementById('btnFetch').disabled = false; }
    }

    async function retriggerReel() {
        if (!_currentItem) return;
        try {
            const r = await api(`/retrigger/${_currentItem.id}`, { method: 'POST' });
            if (r.ok) {
                toast('Re-fetch started!');
                await loadQueue();
            }
        } catch(e) { toast('Error restarting', true); }
    }

    function selectItem(id) {
        _currentItem = _queue.find(q => q.id === id);
        renderWorkspace();
        renderQueue();
    }

    function renderWorkspace() {
        if (!_currentItem) return;
        
        document.getElementById('forgeEmpty').style.display = 'none';
        document.getElementById('forgeWorkspace').classList.add('active');
        
        document.getElementById('wsUrl').textContent = _currentItem.url;
        document.getElementById('wsStatus').innerHTML = `<span class="badge badge-${_currentItem.status}">${_currentItem.status.toUpperCase()}</span>`;
        
        if (_currentItem.status === 'error') {
            document.getElementById('wsStatus').innerHTML += `<div style="color:var(--red); font-family:var(--mono); font-size:0.75rem; margin-top:8px;">${esc(_currentItem.error_msg)}</div>`;
        }

        const actions = document.getElementById('wsActions');
        if (_currentItem.status === 'pending' || _currentItem.status === 'error') {
            actions.innerHTML = `
                <div style="font-family:var(--mono); color:var(--text-dim); margin-bottom:15px; font-size:0.85rem;">Ready to pull from Instagram.</div>
                <button class="btn-secondary" onclick="TF.retriggerReel()"><i class="bi bi-cloud-download"></i> FETCH VIDEO FILE</button>`;
        } 
        else if (_currentItem.status === 'downloading') {
            actions.innerHTML = `
                <div style="font-family:var(--mono); color:var(--amber); margin-bottom:10px; font-size:0.85rem; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite;"></i> DOWNLOADING TO TERMUX...
                </div>
                <div style="font-size:0.75rem; color:var(--text-dim);">Please wait. This usually takes 5-10 seconds.</div>`;
        } 
        else if (_currentItem.status === 'downloaded') {
            const downloadUrl = `${PYAPI}/tiktok/serve/${_currentItem.id}`;
            actions.innerHTML = `
                <div style="font-family:var(--mono); color:var(--green); margin-bottom:15px; font-size:0.85rem;">File is ready on server!</div>
                <a href="${downloadUrl}" download onclick="setTimeout(TF.loadQueue, 2000)" class="btn-success">
                    <i class="bi bi-device-hdd"></i> SAVE VIDEO TO DEVICE
                </a>
                <div style="font-size:0.7rem; color:var(--text-dim); margin-top:10px;">Clicking this downloads the file and cleans up the server instantly.</div>`;
        }
    }

    function closeWorkspace() {
        _currentItem = null;
        document.getElementById('forgeEmpty').style.display = 'flex';
        document.getElementById('forgeWorkspace').classList.remove('active');
        renderQueue();
    }

    async function deleteCurrent() {
        if (!_currentItem || !confirm('Delete this record from history?')) return;
        try {
            const r = await api(`/queue/${_currentItem.id}`, { method: 'DELETE' });
            if (r.ok) {
                toast('Record deleted');
                closeWorkspace();
                await loadQueue();
            }
        } catch(e) { toast('Error deleting', true); }
    }

    function checkPollingNeeds() {
        const needsPolling = _queue.some(q => q.status === 'downloading');
        if (needsPolling && !_pollTimer) _pollTimer = setInterval(loadQueue, 2000);
        else if (!needsPolling && _pollTimer) {
            clearInterval(_pollTimer);
            _pollTimer = null;
        }
    }

    function esc(s) {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    const style = document.createElement('style');
    style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
    document.head.appendChild(style);

    loadQueue();
    return { fetchReel, retriggerReel, selectItem, deleteCurrent, loadQueue, prevPage, nextPage, goToPage };
})();
</script>
</body>
</html>