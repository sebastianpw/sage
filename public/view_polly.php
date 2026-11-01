<?php
// public/view_polli.php
// ─────────────────────────────────────────────────────────────────────────────
// Pollinations Activity Dashboard Clone
// Replicates https://enter.pollinations.ai/activity via API endpoints
// Features: Chatty-style UI, Server Token Auto-discovery, Today Default Filter,
// Deep-Search Cost Extraction, 4-Digit Precise Pricing, Stacked Activity Cards.
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
<title>Pollinations Activity Dashboard</title>
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
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

.wi-header { height: 52px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; background: var(--surface); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.wi-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.82rem; font-weight: 700; color: var(--violet); letter-spacing: 2px; text-transform: uppercase; }
.wi-logo-icon { width: 28px; height: 28px; background: var(--violet-mid); border: 1px solid var(--violet-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; }
.btn-icon-sm { width: 32px; height: 32px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.btn-icon-sm:hover { border-color: var(--violet); color: var(--violet); background: var(--violet-dim); }

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
    <span>Activity</span>
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

<div class="container">

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

<div class="toast-container" id="toastContainer"></div>

<script>
window.App = (function() {
    'use strict';

    const serverToken = <?= json_encode($serverToken) ?>;
    let currentToken = '';
    let chartInstance = null;

    function init() {
        setThemeIcon();
        
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
        toggleTheme
    };
})();

document.addEventListener('DOMContentLoaded', window.App.init);
</script>
</body>
</html>