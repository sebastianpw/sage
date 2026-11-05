<?php
// public/view_polli.php
// ─────────────────────────────────────────────────────────────────────────────
// Pollinations Activity Dashboard & Models Browser
// Replicates https://enter.pollinations.ai/activity via API endpoints
// Features: Chatty-style UI, Server Token Auto-discovery, Today Default Filter,
// Deep-Search Cost Extraction, 4-Digit Precise Pricing, Stacked Activity Cards.
// Includes a detailed Models Browser clone with Tinybird integration via vanilla JS.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: /login.php');
    exit;
}

// Handle AJAX token saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_token') {
    header('Content-Type: application/json');
    $token = trim($_POST['token'] ?? '');
    $saveToFile = !empty($_POST['save_to_file']) && $_POST['save_to_file'] === 'true';
    
    if ($saveToFile && $token) {
        $tokenDir = defined('PROJECT_ROOT') ? PROJECT_ROOT . '/token' : realpath(__DIR__ . '/../token');
        if ($tokenDir) {
            if (!is_dir($tokenDir)) {
                mkdir($tokenDir, 0777, true);
            }
            file_put_contents($tokenDir . '/.pollinationsaitoken', $token);
            echo json_encode(['success' => true, 'msg' => 'Token saved to server successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not resolve token directory.']);
            exit;
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Auto-discover existing server token
$serverToken = getenv('POLLINATIONS_API_KEY');
if (!$serverToken) {
    $home = defined('PROJECT_ROOT') ? PROJECT_ROOT . '/token' : realpath(__DIR__ . '/../token');
    if ($home && is_readable($home . '/.pollinationsaitoken')) {
        $serverToken = trim(file_get_contents($home . '/.pollinationsaitoken'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Pollinations Dashboard & Models</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function() {
  try {
    var t = localStorage.getItem('spw_theme');
    if (t) document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>

<style>
:root {
  --bg:           #080b10;
  --surface:      #0e1319;
  --card:         #111820;
  --border:       #1c2535;
  --border-glow:  #2a3a52;
  --text:         #c8d4e8;
  --text-dim:     #5a6a80;
  --text-bright:  #e8f0ff;
  --amber:        #f5a623;
  --green:        #22d3a0;
  --red:          #f05060;
  --red-dim:      rgba(240,80,96,0.1);
  --blue:         #4da6ff;
  --violet:       #a78bfa;
  --violet-dim:   rgba(167,139,250,0.1);
  --violet-mid:   rgba(167,139,250,0.18);
  --violet-glow:  rgba(167,139,250,0.45);
  --mono:         'Space Mono', 'Fira Mono', monospace;
  --sans:         'Syne', system-ui, sans-serif;
  --radius:       6px;
  --radius-lg:    10px;
}

:root[data-theme="light"], html[data-theme="light"] {
  --bg:           #f6f8fa;
  --surface:      #e1e4e8;
  --card:         #ffffff;
  --border:       #d1d5db;
  --border-glow:  #9ca3af;
  --text:         #111827;
  --text-dim:     #4b5563;
  --text-bright:  #000000;
  --amber:        #d97706;
  --green:        #059669;
  --red:          #dc2626;
  --red-dim:      rgba(220,38,38,0.1);
  --blue:         #2563eb;
  --violet:       #7c3aed;
  --violet-dim:   rgba(124,58,237,0.1);
  --violet-mid:   rgba(124,58,237,0.18);
  --violet-glow:  rgba(124,58,237,0.4);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 18px; line-height: 1.5; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

.wi-header { height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.wi-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.82rem; font-weight: 700; color: var(--violet); letter-spacing: 2px; text-transform: uppercase; }
.wi-logo-icon { width: 28px; height: 28px; background: var(--violet-mid); border: 1px solid var(--violet-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 18px; }
.btn-icon-sm { width: 32px; height: 32px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.btn-icon-sm:hover { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }

/* Tabs CSS */
.tabs-header { display: flex; width: 100%; background: var(--surface); border-bottom: 1px solid var(--border); }
.tab-btn { flex: 1; padding: 14px 10px; text-align: center; border: none; background: transparent; color: var(--text-dim); font-family: var(--mono); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.2s; border-bottom: 2px solid transparent; }
.tab-btn:hover { color: var(--text); background: rgba(255,255,255,0.02); }
.tab-btn.active { color: var(--violet); border-bottom: 2px solid var(--violet); }
.tab-content { display: none; }
.tab-content.active { display: block; }

.container { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
.card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; }
.card-header { font-family: var(--mono); font-size: 0.85rem; font-weight: 700; color: var(--violet); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 1px; display: flex; justify-content: space-between; align-items: center; }

.ctx-group { margin-bottom: 16px; }
.ctx-label { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px; display: block; }
.ctx-input { width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.75rem; padding: 9px 12px; transition: border-color 0.15s; }
.ctx-input:focus { outline: none; border-color: var(--violet); }
input[type="date"] { color-scheme: dark; }
:root[data-theme="light"] input[type="date"] { color-scheme: light; }

.checkbox-wrap { display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer; }
.checkbox-wrap input { accent-color: var(--violet); width: 16px; height: 16px; cursor: pointer; }
.checkbox-wrap span { font-family: var(--mono); font-size: 0.7rem; color: var(--text-dim); }

.btn-primary { padding: 8px 18px; background: var(--violet); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px; justify-content: center; }
.btn-primary:hover { filter: brightness(1.1); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary { padding: 6px 12px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-size: 0.7rem; transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px; justify-content: center; }
.btn-secondary:hover { border-color: var(--border-glow); color: var(--text); }

.server-token-msg { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--violet-dim); border: 1px solid var(--violet-glow); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.75rem; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-box { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; text-align: center; transition: border-color 0.2s; }
.stat-box:hover { border-color: var(--border-glow); }
.stat-label { font-family: var(--mono); font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; }
.stat-value { font-family: var(--sans); font-size: 2rem; font-weight: 700; color: var(--text-bright); margin-top: 8px; word-break: break-word; }
.stat-value.green { color: var(--green); }

.activity-feed { display: flex; flex-direction: column; gap: 10px; max-height: 600px; overflow-y: auto; padding-right: 4px; }
.activity-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; transition: border-color 0.15s; }
.activity-card:hover { border-color: var(--violet); }
.activity-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.activity-model { font-family: var(--mono); font-size: 0.85rem; color: var(--violet); font-weight: 700; word-break: break-all; }
.activity-time { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); white-space: nowrap; }
.activity-details { display: flex; justify-content: space-between; align-items: flex-end; font-family: var(--mono); font-size: 0.75rem; }
.activity-usage { color: var(--text-dim); }
.activity-cost { color: var(--text-bright); font-weight: 700; }
.activity-cost.free { color: var(--green); }
.badge { display: inline-block; padding: 2px 6px; border-radius: 4px; background: var(--card); border: 1px solid var(--border); font-size: 0.7rem; color: var(--text-dim); }

.loading-state { text-align: center; padding: 60px 20px; color: var(--text-dim); font-family: var(--mono); font-size: 0.85rem; }
.loading-icon { font-size: 32px; color: var(--violet); margin-bottom: 12px; animation: pulse 1.5s infinite; }
.error-state { padding: 16px; border: 1px solid var(--red); background: var(--red-dim); border-radius: var(--radius); color: var(--red); font-family: var(--mono); font-size: 0.8rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.empty-state { text-align: center; padding: 40px 20px; color: var(--text-dim); font-family: var(--mono); font-size: 0.8rem; }

/* Models Card additions */
.model-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px; display: flex; flex-direction: column; transition: border-color 0.2s; }
.model-card:hover { border-color: var(--violet); }
.model-id { font-family: var(--mono); font-size: 0.9rem; font-weight: 700; color: var(--violet); word-break: break-word; line-height: 1.2; }
.model-desc { font-family: var(--sans); font-size: 0.8rem; color: var(--text-dim); word-break: break-word; }

@keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.95); } }

.toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 6px; pointer-events: none; }
.toast { padding: 8px 14px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--border); font-family: var(--mono); font-size: 0.76rem; color: var(--text); box-shadow: 0 4px 20px rgba(0,0,0,0.5); animation: toastIn 0.2s ease; pointer-events: all; cursor: pointer; display: flex; gap: 6px; align-items: center; max-width: 300px; }
.toast.success { border-color: var(--green); }
.toast.error   { border-color: var(--red); color: var(--red); }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(8px); } }
.toast.out     { animation: toastOut 0.2s forwards; }

@media (min-width: 768px) {
  .flex-row-md { display: flex; gap: 16px; align-items: flex-end; }
  .flex-row-md > div { flex: 1; margin-bottom: 0; }
  .btn-primary { padding: 9px 24px; }
}
</style>
</head>
<body>

<header class="wi-header">
  <div class="wi-logo">
    <div class="wi-logo-icon"><i class="bi bi-activity"></i></div>
    <span id="headerTitle">Activity</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;">
    <button style="display:none;" class="btn-icon-sm" onclick="App.toggleTheme()" id="themeBtn" title="Toggle Theme">
      <i class="bi bi-moon-stars"></i>
    </button>
    <button class="btn-icon-sm" onclick="location.reload()" id="refreshBtn" title="Refresh">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>
</header>

<div class="tabs-header">
  <button class="tab-btn active" id="tabBtnActivity" onclick="App.switchTab('activity')">Activity</button>
  <button class="tab-btn" id="tabBtnModels" onclick="App.switchTab('models')">Models Browser</button>
</div>

<!-- TAB 1: ACTIVITY -->
<div class="container tab-content active" id="tabActivity">

  <!-- CONFIG CARD -->
  <div class="card" id="configCard">
    <div class="card-header">API Configuration</div>
    
    <div id="serverTokenMsg" class="server-token-msg" style="display: none;">
      <div><i class="bi bi-shield-check" style="color:var(--green); margin-right:6px;"></i> Using secure server-configured token.</div>
      <button class="btn-secondary" onclick="App.showManualInput()">Change</button>
    </div>

    <div id="manualInputSection" style="display: none;">
      <div class="ctx-group">
        <label class="ctx-label">Pollinations API Key (Need proper scope to view all stats)</label>
        <div class="flex-row-md" style="display: flex; gap: 10px; flex-wrap: wrap;">
          <input type="password" id="apiKey" class="ctx-input" placeholder="sk_... or pk_..." value="" style="flex: 1; min-width: 250px;">
          <button class="btn-primary" onclick="App.saveAndFetch()"><i class="bi bi-cloud-download"></i> Load Data</button>
        </div>
        <label class="checkbox-wrap">
          <input type="checkbox" id="saveToServerChk">
          <span>Save this token permanently to server <code>/token/.pollinationsaitoken</code></span>
        </label>
      </div>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="card" id="filterCard" style="display: none;">
    <div class="card-header">
      <span>Date Range</span>
      <button class="btn-secondary" onclick="App.fetchData()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="flex-row-md ctx-group">
      <div>
        <label class="ctx-label">Start Date</label>
        <input type="date" id="startDate" class="ctx-input" onchange="App.fetchData()">
      </div>
      <div>
        <label class="ctx-label">End Date</label>
        <input type="date" id="endDate" class="ctx-input" onchange="App.fetchData()">
      </div>
    </div>
  </div>

  <!-- STATES -->
  <div id="loading" class="loading-state" style="display: none;">
    <div class="loading-icon"><i class="bi bi-hexagon-half"></i></div>
    Fetching telemetry from Pollinations API...
  </div>
  <div id="error" class="error-state" style="display: none;"></div>

  <!-- DASHBOARD -->
  <div id="dashboard" style="display: none;">

    <div class="stat-grid">
      <div class="stat-box">
        <div class="stat-label">Pollen Balance</div>
        <div class="stat-value green" id="valBalance">--</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Period Requests</div>
        <div class="stat-value" id="valRequests">--</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Period Cost</div>
        <div class="stat-value" id="valCost">--</div>
      </div>
    </div>

    <!-- MODEL SUMS -->
    <div class="card" id="modelSumsCard" style="display: none;">
      <div class="card-header">Sums Per Model</div>
      <div class="stat-grid" id="modelSumsGrid" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-bottom: 0;">
        <!-- Populated via JS -->
      </div>
    </div>

    <div class="card">
      <div class="card-header">Daily Trend</div>
      <div style="position: relative; height: 180px; width: 100%;">
        <canvas id="usageChart"></canvas>
      </div>
    </div>

    <!-- ACTIVITY FEED -->
    <div class="card">
      <div class="card-header">Activity Feed</div>
      <div class="activity-feed" id="activityFeed">
        <!-- Populated via JS -->
      </div>
    </div>
  </div>

</div>


<!-- TAB 2: MODELS BROWSER -->
<div class="container tab-content" id="tabModels">
  <div class="card" id="modelsFilterCard">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
      <div style="display:flex; align-items:center; gap:16px; flex-wrap: wrap;">
        <span>Search & Filter</span>
        <label class="checkbox-wrap" style="margin-top:0;">
          <input type="checkbox" id="includeCommunityChk" onchange="App.toggleCommunity()">
          <span style="font-size: 0.75rem;">Include Community</span>
        </label>
      </div>
      <button class="btn-secondary" onclick="App.loadModelsTab(true)"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="flex-row-md ctx-group">
      <div style="flex:2;">
        <label class="ctx-label">Search</label>
        <input type="text" id="modelSearch" class="ctx-input" placeholder="Search by name, ID, publisher..." oninput="App.renderModels()">
      </div>
      <div style="flex:1;">
        <label class="ctx-label">Category</label>
        <select id="modelCategory" class="ctx-input" onchange="App.renderModels()">
          <option value="">All</option>
          <option value="text">Text</option>
          <option value="image">Image</option>
          <option value="video">Video</option>
          <option value="audio">Audio</option>
          <option value="3d">3D</option>
          <option value="embedding">Embedding</option>
          <option value="decision">Decision</option>
          <option value="realtime">Realtime</option>
        </select>
      </div>
      <div style="flex:1;">
        <label class="ctx-label">Sort By</label>
        <select id="modelSort" class="ctx-input" onchange="App.renderModels()">
          <option value="popularity_desc">Popularity</option>
          <option value="name_asc">Name (A-Z)</option>
          <option value="name_desc">Name (Z-A)</option>
          <option value="price_asc">Price (Low to High)</option>
          <option value="price_desc">Price (High to Low)</option>
        </select>
      </div>
    </div>
  </div>

  <div id="modelsLoading" class="loading-state" style="display: none;">
    <div class="loading-icon"><i class="bi bi-hexagon-half"></i></div>
    Fetching models catalog...
  </div>

  <div id="modelsGrid" class="stat-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); margin-bottom: 24px;">
    <!-- Populated via JS -->
  </div>

  <!-- Models Legend -->
  <div class="card" style="margin-top: 24px; font-family: var(--mono); font-size: 0.75rem; color: var(--text-dim); padding: 20px;">
    <div style="font-weight: 700; color: var(--violet); margin-bottom: 12px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;">Legend & Pricing Units</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; line-height: 1.4;">
      <div><i class="bi bi-stars" style="color:var(--violet);"></i> <b>/gen:</b> flat rate per image or audio generation.</div>
      <div><i class="bi bi-123" style="color:var(--violet);"></i> <b>/K · /M:</b> rates per thousand or million tokens.</div>
      <div><i class="bi bi-clock" style="color:var(--violet);"></i> <b>/sec · /hr:</b> per second of video or audio.</div>
      <div><i class="bi bi-image" style="color:var(--violet);"></i> <b>/MP · /image:</b> per megapixel or reference image.</div>
      <div><i class="bi bi-speaker" style="color:var(--violet);"></i> <b>/K chars · /K bytes:</b> speech priced per thousand chars or UTF-8 bytes.</div>
      <div><i class="bi bi-activity" style="color:var(--green);"></i> <b>≈ requests /$1:</b> estimated from the median cost over 7 days (100 Pollen = $1).</div>
      <div style="grid-column: 1 / -1; margin-top: 4px; padding-top: 8px; border-top: 1px solid var(--border); font-size: 0.7rem;">
        Note: Pricing uses <b>P</b> to denote Pollen (1 Pollen = $0.01).
      </div>
    </div>
  </div>
</div>


<div class="toast-container" id="toastContainer"></div>

<script>
window.App = (function() {
    'use strict';

    const serverToken = <?= json_encode($serverToken) ?>;
    let currentToken = '';
    let chartInstance = null;
    let allModels = [];
    let statsMap = {};
    let communityEnabled = false;

    function init() {
        setThemeIcon();
        
        communityEnabled = localStorage.getItem('polli_include_community') === 'true';
        const chk = document.getElementById('includeCommunityChk');
        if(chk) chk.checked = communityEnabled;
        
        // Initialize Dates: Default exactly to Today (Local Time)
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        const todayStr = `${yyyy}-${mm}-${dd}`;
        
        document.getElementById('startDate').value = todayStr;
        document.getElementById('endDate').value = todayStr;
        
        if (serverToken) {
            currentToken = serverToken;
            document.getElementById('serverTokenMsg').style.display = 'flex';
            document.getElementById('manualInputSection').style.display = 'none';
            fetchData();
        } else {
            const savedLocal = localStorage.getItem('polli_api_key');
            if (savedLocal) {
                document.getElementById('apiKey').value = savedLocal;
                currentToken = savedLocal;
                fetchData();
            }
            document.getElementById('serverTokenMsg').style.display = 'none';
            document.getElementById('manualInputSection').style.display = 'block';
        }
    }

    function switchTab(tabId) {
        document.getElementById('tabBtnActivity').classList.remove('active');
        document.getElementById('tabBtnModels').classList.remove('active');
        document.getElementById('tabActivity').classList.remove('active');
        document.getElementById('tabModels').classList.remove('active');
        
        if (tabId === 'activity') {
            document.getElementById('tabBtnActivity').classList.add('active');
            document.getElementById('tabActivity').classList.add('active');
            document.getElementById('headerTitle').textContent = 'Activity';
        } else {
            document.getElementById('tabBtnModels').classList.add('active');
            document.getElementById('tabModels').classList.add('active');
            document.getElementById('headerTitle').textContent = 'Models Browser';
            loadModelsTab();
        }
    }

    function toggleCommunity() {
        const chk = document.getElementById('includeCommunityChk');
        if(chk) {
            communityEnabled = chk.checked;
            localStorage.setItem('polli_include_community', communityEnabled);
            renderModels();
        }
    }

    async function loadModelsTab(force = false) {
        if (!force && allModels.length > 0) {
            renderModels();
            return;
        }
        
        document.getElementById('modelsGrid').innerHTML = '';
        document.getElementById('modelsLoading').style.display = 'block';
        
        try {
            const [res, tbRes] = await Promise.all([
                fetch('https://gen.pollinations.ai/models'),
                fetch('https://api.europe-west2.gcp.tinybird.co/v0/pipes/public_model_stats.json?limit=1000&token=p.eyJ1IjogImFjYTYzZjc5LThjNTYtNDhlNC05NWJjLWEyYmFjMTY0NmJkMyIsICJpZCI6ICI5ZWZmMGM3Ni1kOTZkLTQwYjgtYWQwOC1mNDFlMmRiYjBmYTIiLCAiaG9zdCI6ICJnY3AtZXVyb3BlLXdlc3QyIn0.6VnVkAQ5h_fkcDZVDUoU38dzTxaw0xo3DnmKkhECbA8').catch(()=>null)
            ]);
            
            if (!res.ok) throw new Error(`Failed to fetch models (${res.status})`);
            const data = await res.json();
            
            if (data && Array.isArray(data.data)) {
                allModels = data.data;
            } else if (Array.isArray(data)) {
                allModels = data;
            } else {
                allModels = Object.values(data);
            }
            
            if (tbRes && tbRes.ok) {
                const tbData = await tbRes.json();
                if (tbData && tbData.data) {
                    tbData.data.forEach(row => {
                        if (row.model) {
                            statsMap[row.model] = {
                                median_cost: row.median_cost || 0,
                                request_count: row.request_count || 0
                            };
                        }
                    });
                }
            }
            
            document.getElementById('modelsLoading').style.display = 'none';
            renderModels();
        } catch (err) {
            document.getElementById('modelsLoading').style.display = 'none';
            document.getElementById('modelsGrid').innerHTML = `<div class="error-state" style="grid-column: 1 / -1;"><i class="bi bi-exclamation-triangle-fill"></i> <span>${err.message}</span></div>`;
        }
    }

    function renderModels() {
        const q = document.getElementById('modelSearch').value.toLowerCase().trim();
        const catFilter = document.getElementById('modelCategory').value.toLowerCase();
        const sortMode = document.getElementById('modelSort').value;
        const grid = document.getElementById('modelsGrid');
        
        let html = '';
        let filtered = allModels.filter(m => {
            if (!communityEnabled && m.community === true) return false;
            
            const mCat = (m.type || m.category || '').toLowerCase();
            if (catFilter && mCat !== catFilter) return false;
            if (q) {
                const searchStr = `${m.id || ''} ${m.name || ''} ${m.description || ''} ${m.publisher || ''}`.toLowerCase();
                if (!searchStr.includes(q)) return false;
            }
            return true;
        });
        
        // Sorting Logic
        filtered.sort((a, b) => {
            const idA = a.id || a.name || 'unknown';
            const idB = b.id || b.name || 'unknown';
            const statsA = statsMap[idA] || { median_cost: 0, request_count: 0 };
            const statsB = statsMap[idB] || { median_cost: 0, request_count: 0 };

            if (sortMode === 'popularity_desc') {
                return statsB.request_count - statsA.request_count;
            } else if (sortMode === 'name_asc') {
                return (a.title || a.name || idA).localeCompare(b.title || b.name || idB);
            } else if (sortMode === 'name_desc') {
                return (b.title || b.name || idB).localeCompare(a.title || a.name || idA);
            } else if (sortMode === 'price_asc' || sortMode === 'price_desc') {
                const getPriceScore = (m, stats) => {
                    if (stats.median_cost > 0) return stats.median_cost;
                    if (typeof m.pricing === 'number') return m.pricing;
                    if (m.price !== undefined) return m.price;
                    if (m.pricing && typeof m.pricing === 'object') {
                        let lowest = Infinity;
                        ['promptTextTokens', 'promptImageTokens', 'completionTextTokens'].forEach(k => {
                            if (m.pricing[k] !== undefined) {
                                lowest = Math.min(lowest, m.pricing[k] * 1000000);
                            }
                        });
                        if (lowest !== Infinity) return lowest;
                    }
                    return 0; // free models are 0
                };
                
                const pA = getPriceScore(a, statsA);
                const pB = getPriceScore(b, statsB);
                return sortMode === 'price_asc' ? pA - pB : pB - pA;
            }
            return 0;
        });

        filtered.forEach(m => {
            const id = m.id || m.name || 'unknown';
            const title = m.title || id;
            const desc = m.description || '';
            const publisher = m.publisher || '?';
            const publisherInitial = publisher.charAt(0).toUpperCase();
            
            // Icons row
            let icons = [];
            const inMods = m.input_modalities || [];
            const outMods = m.output_modalities || [];
            const caps = m.capabilities || [];
            
            if (inMods.includes('text') || outMods.includes('text') || (m.type||m.category) === 'text') icons.push('<i class="bi bi-chat-left-text" title="Text"></i>');
            if (inMods.includes('image') || outMods.includes('image') || (m.type||m.category) === 'image') icons.push('<i class="bi bi-image" title="Image"></i>');
            if (inMods.includes('video') || outMods.includes('video') || (m.type||m.category) === 'video') icons.push('<i class="bi bi-camera-reels" title="Video"></i>');
            if (inMods.includes('audio') || outMods.includes('audio') || (m.type||m.category) === 'audio') icons.push('<i class="bi bi-mic" title="Audio"></i>');
            if ((m.type||m.category) === '3d') icons.push('<i class="bi bi-box" title="3D"></i>');
            if (caps.includes('tool_calling')) icons.push('<i class="bi bi-wrench" title="Tool Calling"></i>');
            if (caps.includes('web_search')) icons.push('<i class="bi bi-search" title="Web Search"></i>');
            
            const iconsHtml = [...new Set(icons)].join(' ');
            
            // Health
            let healthHtml = '';
            if (m.health && m.health.status) {
                let color = 'var(--text-dim)';
                if (m.health.status === 'healthy') color = 'var(--green)';
                else if (m.health.status === 'degraded') color = 'var(--amber)';
                else if (m.health.status === 'down') color = 'var(--red)';
                
                let rate = m.health.success_rate != null ? ` (${Math.round(m.health.success_rate)}%)` : '';
                healthHtml = `<div style="margin-left:auto; display:flex; align-items:center; gap:4px; font-size:0.75rem; color:${color};" title="Status: ${m.health.status}${rate}">
                    <i class="bi bi-bar-chart-fill"></i>
                </div>`;
            }

            // Pricing
            const paidBadge = m.paid_only 
                ? `<span class="badge" style="background:rgba(245,166,35,0.15); color:var(--amber); border-color:var(--amber); font-weight:700;">Paid</span>` 
                : `<span class="badge" style="background:rgba(34,211,160,0.15); color:var(--green); border-color:var(--green); font-weight:700;">Quest</span>`;
            
            let priceRows = '';
            
            const stats = statsMap[id];
            const medCost = stats ? stats.median_cost : 0;
            if (medCost > 0) {
                const reqsPerDollar = Math.round(100 / medCost);
                priceRows += `<div title="Median cost: ${medCost.toFixed(4)} Pollen" style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-activity" style="color:var(--green);"></i></span><span>≈ <strong>${reqsPerDollar.toLocaleString()}</strong> / $1</span></div>`;
            }

            if (m.pricing) {
                if (typeof m.pricing === 'number') {
                    priceRows += `<div style="grid-column: span 2; display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-stars"></i> Flat rate</span><span><strong>${m.pricing}</strong> P /gen</span></div>`;
                } else if (typeof m.pricing === 'object') {
                    const p = m.pricing;
                    const formatP = (val, mult) => {
                        const v = parseFloat(val);
                        if (isNaN(v)) return val;
                        const total = v * mult;
                        return total < 0.01 ? total.toExponential(2) : parseFloat(total.toFixed(4));
                    };

                    if (p.promptTextTokens !== undefined) priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-chat-left-text"></i> Text in</span><span><strong>${formatP(p.promptTextTokens, 1000000)}</strong> P /M</span></div>`;
                    if (p.promptImageTokens !== undefined) priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-image"></i> Image in</span><span><strong>${formatP(p.promptImageTokens, 1000000)}</strong> P /M</span></div>`;
                    if (p.promptVideoTokens !== undefined) priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-camera-reels"></i> Video in</span><span><strong>${formatP(p.promptVideoTokens, 1000000)}</strong> P /M</span></div>`;
                    if (p.promptAudioTokens !== undefined) priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-mic"></i> Audio in</span><span><strong>${formatP(p.promptAudioTokens, 1000000)}</strong> P /M</span></div>`;
                    if (p.promptCacheWriteTokens !== undefined) priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-database"></i> Cache write</span><span><strong>${formatP(p.promptCacheWriteTokens, 1000000)}</strong> P /M</span></div>`;
                    if (p.promptCachedTokens !== undefined) priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-database"></i> Cached in</span><span><strong>${formatP(p.promptCachedTokens, 1000000)}</strong> P /M</span></div>`;
                    if (p.completionTextTokens !== undefined) priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-chat-left-text"></i> Text out</span><span><strong>${formatP(p.completionTextTokens, 1000000)}</strong> P /M</span></div>`;
                }
            } else if (m.price !== undefined) {
                priceRows += `<div style="grid-column: span 2; display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi bi-stars"></i> Flat rate</span><span><strong>${m.price}</strong> P /gen</span></div>`;
            }
            
            if (m.pricing_adjustments && Array.isArray(m.pricing_adjustments)) {
                m.pricing_adjustments.forEach(adj => {
                    let label = adj.label || adj.kind;
                    let icon = 'bi-plus-circle';
                    if (label.toLowerCase().includes('search')) icon = 'bi-search';
                    
                    let unitStr = adj.unit ? ` /${adj.quantity >= 1000 ? (adj.quantity/1000)+'K ' : (adj.quantity+' ')}${adj.unit}` : '';
                    priceRows += `<div style="display:flex; justify-content:space-between; align-items:center; gap:4px;"><span><i class="bi ${icon}"></i> ${label}</span><span><strong>${adj.price}</strong> P${unitStr}</span></div>`;
                });
            }

            if(!priceRows) priceRows = '<div style="color:var(--text-dim); grid-column: span 2;">Pricing info unavailable</div>';

            html += `
                <div class="model-card">
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="flex-shrink:0; width:40px; height:40px; border-radius:8px; background:var(--surface); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:var(--violet); font-family:var(--sans);">
                            ${publisherInitial}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                <div class="model-id" style="font-size:0.95rem; color:var(--text-bright);">
                                    ${escapeHtml(title)}
                                    <a style="display:none;" href="https://gen.pollinations.ai/models/${encodeURIComponent(id)}" target="_blank" style="color:var(--violet); margin-left:4px; font-size:0.75rem;"><i class="bi bi-box-arrow-up-right"></i></a>
                                </div>
                                ${paidBadge}
                            </div>
                            <div style="font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); margin-top:2px; display:flex; align-items:center; gap:6px;">
                                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width: 180px;" title="${escapeHtml(id)}">${escapeHtml(id)}</span>
                                <i class="bi bi-copy" style="cursor:pointer; color:var(--violet);" onclick="App.copyToClipboard('${escapeHtml(id).replace(/'/g, "\\'")}')" title="Copy ID"></i>
                            </div>
                        </div>
                    </div>
                    
                    ${desc ? `<div class="model-desc" style="margin-top:10px;">${escapeHtml(desc)}</div>` : ''}
                    
                    <div style="display:flex; gap:8px; color:var(--text-dim); font-size:0.95rem; margin-top:10px; align-items:center;">
                        ${iconsHtml}
                        ${healthHtml}
                    </div>
                    
                    <hr style="border:0; border-top:1px solid var(--border); margin:10px 0 8px 0;">
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-family:var(--mono); font-size:0.7rem; color:var(--text-dim);">
                        ${priceRows}
                    </div>
                </div>
            `;
        });
        
        if (filtered.length === 0) {
            grid.innerHTML = '<div class="empty-state" style="grid-column: 1 / -1;">No models found matching your filters.</div>';
        } else {
            grid.innerHTML = html;
        }
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                toast('Copied ID: ' + text, 'success', 2000);
            }).catch(err => {
                toast('Failed to copy', 'error');
            });
        } else {
            let textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                toast('Copied ID: ' + text, 'success', 2000);
            } catch (err) {
                toast('Failed to copy', 'error');
            }
            textArea.remove();
        }
    }

    function showManualInput() {
        document.getElementById('serverTokenMsg').style.display = 'none';
        document.getElementById('manualInputSection').style.display = 'block';
        if (currentToken) {
            document.getElementById('apiKey').value = currentToken;
        }
    }

    async function saveAndFetch() {
        const keyInput = document.getElementById('apiKey').value.trim();
        if (!keyInput) {
            toast('Please enter an API Key.', 'error');
            return;
        }

        currentToken = keyInput;
        localStorage.setItem('polli_api_key', currentToken);

        const saveToServer = document.getElementById('saveToServerChk').checked;
        if (saveToServer) {
            try {
                const fd = new FormData();
                fd.append('action', 'save_token');
                fd.append('token', currentToken);
                fd.append('save_to_file', 'true');

                const r = await fetch(window.location.href, { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    toast(d.msg || 'Token saved to server.', 'success');
                } else {
                    toast('Failed to save token to server: ' + (d.error || ''), 'error');
                }
            } catch (e) {
                toast('Network error saving token to server.', 'error');
            }
        }

        fetchData();
    }

    async function fetchData() {
        if (!currentToken) {
            showError('No API Key configured. Please enter one.');
            showManualInput();
            return;
        }

        document.getElementById('dashboard').style.display = 'none';
        document.getElementById('filterCard').style.display = 'none';
        document.getElementById('error').style.display = 'none';
        document.getElementById('loading').style.display = 'block';

        const headers = {
            'Authorization': `Bearer ${currentToken}`,
            'Content-Type': 'application/json'
        };

        const startDateStr = document.getElementById('startDate').value;
        const endDateStr = document.getElementById('endDate').value;
        
        // Exact boundaries in Local Time
        const startBoundary = new Date(`${startDateStr}T00:00:00`);
        const endBoundary = new Date(`${endDateStr}T23:59:59.999`);

        try {
            // 1. Fetch Balance
            const balRes = await fetch('https://gen.pollinations.ai/account/balance', { headers });
            if (!balRes.ok) {
                if (balRes.status === 401) throw new Error('Invalid API Key.');
                throw new Error(`Balance fetch failed (${balRes.status})`);
            }
            const balData = await balRes.json();
            document.getElementById('valBalance').textContent = balData.balance !== undefined ? parseFloat(balData.balance).toFixed(2) : '--';

            // 2. Fetch Daily Usage (Raw)
            let rawDailyData = [];
            const dailyRes = await fetch('https://gen.pollinations.ai/account/usage/daily', { headers });
            if (dailyRes.ok) {
                rawDailyData = await dailyRes.json();
            }

            // 3. Fetch Recent Usage (Raw)
            let rawUsageData = [];
            let usageRes = await fetch(`https://gen.pollinations.ai/account/usage?limit=1500`, { headers });
            if (usageRes.status === 403) {
                usageRes = await fetch(`https://gen.pollinations.ai/account/key/usage?limit=1500`, { headers });
            }

            if (usageRes.ok) {
                rawUsageData = await usageRes.json();
            } else {
                throw new Error(`Failed to fetch activities (${usageRes.status}).`);
            }

            const extractData = (obj) => Array.isArray(obj) ? obj : (obj.data || obj.usage || obj.items || []);
            const dailyDataArr = extractData(rawDailyData);
            const usageDataArr = extractData(rawUsageData);

            // Filter precisely to chosen date bounds
            const filteredDaily = dailyDataArr.filter(row => {
                const d = new Date(row.date);
                return d >= startBoundary && d <= endBoundary;
            });
            
            const filteredUsage = usageDataArr.filter(row => {
                const d = new Date(row.timestamp || row.created_at || row.date);
                return d >= startBoundary && d <= endBoundary;
            });

            renderDashboard(filteredDaily, filteredUsage);

            document.getElementById('loading').style.display = 'none';
            document.getElementById('filterCard').style.display = 'block';
            document.getElementById('dashboard').style.display = 'block';

        } catch (err) {
            document.getElementById('loading').style.display = 'none';
            showError(err.message);
        }
    }

    function showError(msg) {
        const el = document.getElementById('error');
        el.style.display = 'flex';
        el.innerHTML = `<i class="bi bi-exclamation-triangle-fill" style="font-size:18px;"></i> <span>${msg}</span>`;
    }

    function getThemeColors() {
        const style = getComputedStyle(document.documentElement);
        return {
            text: style.getPropertyValue('--text-dim').trim(),
            grid: style.getPropertyValue('--border').trim(),
            violet: style.getPropertyValue('--violet').trim(),
            violetDim: style.getPropertyValue('--violet-dim').trim(),
            green: style.getPropertyValue('--green').trim()
        };
    }
    
    // Deep Search Extractor: Scans the object aggressively for ANY property containing 'cost', 'price', or 'pollen'
    function deepExtractCost(obj) {
        if (!obj || typeof obj !== 'object') return 0;
        
        // 1. Direct priority fields
        const priorityKeys = ['cost', 'total_cost', 'price', 'pollen', 'spend', 'billed', 'amount'];
        for (let k of priorityKeys) {
            if (obj[k] !== undefined && obj[k] !== null && typeof obj[k] !== 'object') {
                let v = parseFloat(String(obj[k]).replace(/[^0-9.-]+/g, ''));
                if (!isNaN(v) && v > 0) return v;
            }
        }
        
        // 2. Scan all top-level keys for matches
        for (let k in obj) {
            if (typeof obj[k] !== 'object' && (k.toLowerCase().includes('cost') || k.toLowerCase().includes('price') || k.toLowerCase().includes('pollen'))) {
                let v = parseFloat(String(obj[k]).replace(/[^0-9.-]+/g, ''));
                if (!isNaN(v) && v > 0) return v;
            }
        }
        
        // 3. Scan 1-level deep (e.g., row.usage.cost or row.pricing.pollen)
        for (let k in obj) {
            if (typeof obj[k] === 'object' && obj[k] !== null) {
                for (let subK in obj[k]) {
                    if (subK.toLowerCase().includes('cost') || subK.toLowerCase().includes('price') || subK.toLowerCase().includes('pollen')) {
                        let v = parseFloat(String(obj[k][subK]).replace(/[^0-9.-]+/g, ''));
                        if (!isNaN(v) && v > 0) return v;
                    }
                }
            }
        }
        
        // Final fallback: if it's literally 0 and found in `.cost`, return it so we don't accidentally ignore valid 0s.
        if (obj.cost !== undefined && obj.cost !== null) {
            let v = parseFloat(String(obj.cost).replace(/[^0-9.-]+/g, ''));
            if (!isNaN(v)) return v;
        }
        
        return 0;
    }
    
    // Exact 4-digit formatting requirement with exception for ultra-micro costs
    function formatCost(val) {
        if (val === 0) return '0.0000';
        if (val > 0 && val < 0.0001) return '< 0.0001';
        return val.toFixed(4);
    }

    function renderDashboard(dailyData, usageData) {
        let totalRequests = 0;
        let totalCost = 0;
        const modelStats = {};
        
        const labels = [];
        const costs = [];
        const requests = [];

        if (Array.isArray(dailyData)) {
            dailyData.sort((a, b) => new Date(a.date) - new Date(b.date));
            dailyData.forEach(day => {
                labels.push(day.date);
                const c = deepExtractCost(day);
                costs.push(c);
                requests.push(day.requests || 0);
            });
        }

        if (Array.isArray(usageData)) {
            usageData.forEach(row => {
                totalRequests++;
                const c = deepExtractCost(row);
                totalCost += c;
                
                const m = row.model || 'unknown';
                if (!modelStats[m]) modelStats[m] = { reqs: 0, cost: 0 };
                modelStats[m].reqs++;
                modelStats[m].cost += c;
            });
        }

        document.getElementById('valRequests').textContent = totalRequests.toLocaleString();
        document.getElementById('valCost').textContent = formatCost(totalCost);

        const modelSumsCard = document.getElementById('modelSumsCard');
        const modelSumsGrid = document.getElementById('modelSumsGrid');
        
        if (Object.keys(modelStats).length > 0) {
            let sumsHtml = '';
            const sortedModels = Object.entries(modelStats).sort((a, b) => b[1].cost - a[1].cost);
            for (const [model, stats] of sortedModels) {
                sumsHtml += `
                    <div class="stat-box" style="padding: 12px;" title="Total cost: ${stats.cost}">
                        <div class="stat-label" style="font-size: 0.6rem; word-break: break-all;">${escapeHtml(model)}</div>
                        <div class="stat-value" style="font-size: 1.1rem; margin-top: 6px; color: ${stats.cost > 0 ? 'var(--violet)' : 'var(--text-bright)'}">${formatCost(stats.cost)}</div>
                        <div style="font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); margin-top: 4px;">${stats.reqs} reqs</div>
                    </div>
                `;
            }
            modelSumsGrid.innerHTML = sumsHtml;
            modelSumsCard.style.display = 'block';
        } else {
            modelSumsCard.style.display = 'none';
        }

        const colors = getThemeColors();
        const ctx = document.getElementById('usageChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Cost (Pollen)',
                        data: costs,
                        backgroundColor: colors.violetDim,
                        borderColor: colors.violet,
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Requests',
                        data: requests,
                        type: 'line',
                        borderColor: colors.green,
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.3,
                        pointBackgroundColor: colors.green,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { color: colors.grid }, ticks: { color: colors.text, font: { family: 'Space Mono' } } },
                    y: { 
                        type: 'linear', position: 'left', 
                        title: { display: true, text: 'Cost', color: colors.text, font: { family: 'Space Mono' } },
                        grid: { color: colors.grid },
                        ticks: { color: colors.violet, font: { family: 'Space Mono' } }
                    },
                    y1: { 
                        type: 'linear', position: 'right', 
                        title: { display: true, text: 'Requests', color: colors.text, font: { family: 'Space Mono' } },
                        grid: { drawOnChartArea: false },
                        ticks: { color: colors.green, font: { family: 'Space Mono', stepSize: 1 } }
                    }
                },
                plugins: {
                    legend: { labels: { color: colors.text, font: { family: 'Space Mono' } } }
                }
            }
        });

        // Render Activity Feed (Stacked Cards)
        const feed = document.getElementById('activityFeed');
        feed.innerHTML = '';
        if (Array.isArray(usageData) && usageData.length > 0) {
            usageData.sort((a, b) => {
                const ta = new Date(a.timestamp || a.created_at || a.date).getTime();
                const tb = new Date(b.timestamp || b.created_at || b.date).getTime();
                return tb - ta;
            });
            
            usageData.forEach(row => {
                const tsStr = row.timestamp || row.created_at || row.date;
                const d = tsStr ? new Date(tsStr).toLocaleString() : 'Unknown';
                
                let usageMetric = '<span class="badge">N/A</span>';
                if (row.total_tokens !== undefined && row.total_tokens !== null) {
                    usageMetric = `<span class="badge" title="In: ${row.prompt_tokens||0} | Out: ${row.completion_tokens||0}">${row.total_tokens.toLocaleString()} tk</span>`;
                } else if (row.duration !== undefined && row.duration !== null) {
                    usageMetric = `<span class="badge">${row.duration} sec</span>`;
                }
                
                const costVal = deepExtractCost(row);
                const costClass = costVal > 0 ? 'activity-cost' : 'activity-cost free';
                
                const card = document.createElement('div');
                card.className = 'activity-card';
                card.innerHTML = `
                    <div class="activity-card-header">
                        <div class="activity-model">${escapeHtml(row.model || 'unknown')}</div>
                        <div class="activity-time">${d}</div>
                    </div>
                    <div class="activity-details">
                        <div class="activity-usage">${usageMetric}</div>
                        <div class="${costClass}" title="Exact cost: ${costVal}">${formatCost(costVal)} <span>Pollen</span></div>
                    </div>
                `;
                feed.appendChild(card);
            });
        } else {
            feed.innerHTML = '<div class="empty-state">No activities found for this date range.</div>';
        }
    }

    function toggleTheme() {
        const html = document.documentElement;
        const curr = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', curr);
        try { localStorage.setItem('spw_theme', curr); } catch(e) {}
        setThemeIcon();
        if (document.getElementById('dashboard').style.display !== 'none' && chartInstance) {
            fetchData();
        }
    }

    function setThemeIcon() {
        const curr = document.documentElement.getAttribute('data-theme');
        const icon = document.getElementById('themeBtn').querySelector('i');
        icon.className = curr === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun';
    }

    function toast(msg, type, duration) {
        type = type || 'info';
        duration = duration || 3000;
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        const icons = { success: '✓', error: '✕', info: '✦' };
        el.innerHTML = `<span>${icons[type]||'💬'}</span> ${escapeHtml(msg)}`;
        el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); };
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(), 250); }, duration);
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    return {
        init,
        fetchData,
        showManualInput,
        saveAndFetch,
        toggleTheme,
        switchTab,
        loadModelsTab,
        renderModels,
        toggleCommunity,
        copyToClipboard
    };
})();

document.addEventListener('DOMContentLoaded', window.App.init);
</script>
</body>
</html>