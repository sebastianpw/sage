<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$logsDir = __DIR__ . '/../logs';

// Filter .log files excluding "err"
$files = array_values(array_filter(scandir($logsDir), function($f) use ($logsDir) {
    return is_file($logsDir . '/' . $f) 
        && preg_match('/\.log$/i', $f) 
        && stripos($f, 'err') === false;
}));

// Sort by modification time descending (newest first)
usort($files, function($a, $b) use ($logsDir) {
    return filemtime($logsDir . '/' . $b) <=> filemtime($logsDir . '/' . $a);
});

// JSON file list endpoint for live background polling without page refresh
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    header('Content-Type: application/json');
    echo json_encode($files);
    exit;
}

// Default to the most recent file
$current = $_GET['file'] ?? ($files[0] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Scheduler Logs (Ajax)</title>
<style>
  :root {
    --bg: #080b10;
    --surface: #0e1319;
    --surface-hover: #141e28;
    --border: #1c2535;
    --border-glow: #2a3a52;
    --text-cli: #00ff66;
    --text-dim: #5a6a80;
    --amber: #f5a623;
    --green: #22d3a0;
    --red: #f05060;
    --mono: 'Space Mono', 'SF Mono', 'Fira Code', 'Roboto Mono', Consolas, monospace;
    --radius: 6px;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }
  
  html, body {
    height: 100%;
    height: 100dvh;
    background: var(--bg);
    color: var(--text-cli);
    font-family: var(--mono);
    font-size: 13px;
    line-height: 1.5;
    overflow: hidden;
    -webkit-font-smoothing: antialiased;
  }

  body {
    display: flex;
    flex-direction: column;
    padding: 8px;
    gap: 8px;
  }

  /* Header & Bar - Single Line */
  .cli-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    flex-wrap: nowrap;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 6px 8px;
    min-width: 0;
  }

  .cli-title-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
  }

  .cli-home-link {
    text-decoration: none;
    font-size: 16px;
    line-height: 1;
    display: flex;
    align-items: center;
  }

  .cli-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #e8f0ff;
    white-space: nowrap;
  }

  .live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 5px var(--green);
    display: inline-block;
    flex-shrink: 0;
  }
  .live-dot.paused {
    background: var(--amber);
    box-shadow: 0 0 5px var(--amber);
  }

  .cli-controls {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: nowrap;
    min-width: 0;
    flex: 1;
    justify-content: flex-end;
  }

  /* Compact log selector */
  #logSelectForm {
    display: inline-flex;
    align-items: center;
    flex: 0 1 160px;
    min-width: 90px;
    max-width: 200px;
  }

  select, button {
    font-family: var(--mono);
    font-size: 11px;
    height: 32px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: #000;
    color: #e8f0ff;
    padding: 0 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
  }

  select {
    width: 100%;
    appearance: none;
    text-overflow: ellipsis;
    overflow: hidden;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='5' viewBox='0 0 8 5'%3E%3Cpath d='M1 1l3 3 3-3' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 6px center;
    padding-right: 18px;
  }

  select:focus, button:focus {
    outline: none;
    border-color: var(--green);
  }

  button:hover {
    background: var(--surface-hover);
    border-color: var(--border-glow);
  }

  button:active {
    transform: scale(0.97);
  }

  .btn-icon {
    min-width: 30px;
    padding: 0 6px;
    color: var(--text-cli);
    flex-shrink: 0;
  }

  .btn-action {
    color: #c8d4e8;
    flex-shrink: 0;
    padding: 0 8px;
  }

  .btn-copied {
    border-color: var(--green) !important;
    color: var(--green) !important;
  }

  /* Log screen */
  #log {
    flex: 1;
    background: #000;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 10px;
    overflow-y: auto;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
    user-select: text;
    -webkit-user-select: text;
    box-shadow: inset 0 0 12px rgba(0,0,0,0.8);
  }

  ::-webkit-scrollbar { width: 5px; height: 5px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
  ::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

  @media (max-width: 480px) {
    .cli-title { display: none; }
    #logSelectForm { flex: 1 1 auto; max-width: 150px; min-width: 70px; }
  }
</style>
</head>
<body>

<div class="cli-topbar">
  <div class="cli-title-wrap">
    <a href="/dashboard.php" title="Dashboard" class="cli-home-link">&#x1F5C3;</a>
    <span class="live-dot" id="liveDot" title="Live refresh active"></span>
    <span class="cli-title">Logs</span>
  </div>

  <div class="cli-controls">
    <form id="logSelectForm" method="get" onsubmit="return false;">
      <select id="file" name="file" title="Select log file">
        <?php foreach ($files as $f): ?>
          <option value="<?= htmlspecialchars($f) ?>" <?= $f === $current ? 'selected' : '' ?>>
            <?= htmlspecialchars($f) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <button id="toggleAjaxBtn" class="btn-icon" title="Toggle Auto-Refresh">❚❚</button>
    <button id="copyBtn" class="btn-action" title="Copy log to clipboard">📋 Copy</button>
    <button id="scrollBottomBtn" class="btn-icon" title="Scroll to bottom">▼</button>
  </div>
</div>

<div id="log">Loading...</div>

<script>
const logDiv = document.getElementById("log");
const logSelect = document.getElementById("file");
const toggleBtn = document.getElementById("toggleAjaxBtn");
const copyBtn = document.getElementById("copyBtn");
const scrollBottomBtn = document.getElementById("scrollBottomBtn");
const liveDot = document.getElementById("liveDot");

let currentFile = "<?= addslashes($current) ?>";
let autoRefresh = true;
const refreshInterval = 2000;
const fileCheckInterval = 8000;

function fetchLog() {
    if (!currentFile) return;
    fetch('scheduler_log_fetch.php?file=' + encodeURIComponent(currentFile))
        .then(res => res.text())
        .then(txt => {
            const nearBottom = (logDiv.scrollHeight - logDiv.scrollTop - logDiv.clientHeight) < 20;
            logDiv.textContent = txt;

            if (autoRefresh && nearBottom) {
                logDiv.scrollTop = logDiv.scrollHeight;
            }
        })
        .catch(err => console.error(err));
}

// Background polling for dynamically created log files
function checkNewLogFiles() {
    fetch('view_scheduler_log.php?action=list')
        .then(res => res.json())
        .then(files => {
            if (!Array.isArray(files) || files.length === 0) return;

            const existingFiles = Array.from(logSelect.options).map(o => o.value);
            const isIdentical = existingFiles.length === files.length &&
                                existingFiles.every((val, idx) => val === files[idx]);

            if (isIdentical) return;

            const selectedFile = logSelect.value || currentFile;
            logSelect.innerHTML = '';
            files.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f;
                opt.textContent = f;
                if (f === selectedFile) opt.selected = true;
                logSelect.appendChild(opt);
            });

            if (!files.includes(selectedFile)) {
                currentFile = files[0];
                logSelect.value = currentFile;
                fetchLog();
            }
        })
        .catch(err => console.error(err));
}

// Copy logs to clipboard
function copyLogs() {
    const text = logDiv.textContent || '';
    if (!text) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(showCopied).catch(fallbackCopy);
    } else {
        fallbackCopy();
    }

    function fallbackCopy() {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopied();
        } catch (e) {
            console.error(e);
        }
        document.body.removeChild(ta);
    }

    function showCopied() {
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '✓ Copied';
        copyBtn.classList.add('btn-copied');
        setTimeout(() => {
            copyBtn.innerHTML = originalText;
            copyBtn.classList.remove('btn-copied');
        }, 1800);
    }
}

// Initial load
fetchLog();
setInterval(fetchLog, refreshInterval);
setInterval(checkNewLogFiles, fileCheckInterval);

// Toggle auto-refresh
toggleBtn.addEventListener('click', function() {
    autoRefresh = !autoRefresh;
    this.textContent = autoRefresh ? '❚❚' : '▶';
    liveDot.classList.toggle('paused', !autoRefresh);
    liveDot.title = autoRefresh ? 'Live refresh active' : 'Live refresh paused';
    if (autoRefresh) fetchLog();
});

// Copy button
copyBtn.addEventListener('click', copyLogs);

// Scroll to bottom button
scrollBottomBtn.addEventListener('click', function() {
    logDiv.scrollTop = logDiv.scrollHeight;
});

// Change log file
logSelect.addEventListener('change', function() {
    currentFile = this.value;
    logDiv.scrollTop = 0;

    if (!autoRefresh) {
        autoRefresh = true;
        toggleBtn.textContent = '❚❚';
        liveDot.classList.remove('paused');
        liveDot.title = 'Live refresh active';
    }

    fetchLog();
});
</script>

</body>
</html>