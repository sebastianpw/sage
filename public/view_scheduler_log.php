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

// Default to the most recent file
$current = $_GET['file'] ?? ($files[0] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scheduler Logs (Ajax)</title>
<style>
  body { font-family: monospace; background: #111; color: #0f0; margin: 0; padding: 20px; }
  h2 { margin-top: 0; }
  #log { background: #000; padding: 10px; height: 420px; overflow-y: scroll; white-space: pre-wrap; }
  #logControls { margin-bottom: 10px; }
  select, button { padding: 5px 8px; margin-right: 5px; }
</style>
</head>
<body>

<a href="/dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px;">
    &#x1F5C3;
</a>
<h2>Scheduler Log Viewer</h2>


<div style="position: absolute; right:0;" id="logControls">                                                         <button id="toggleAjaxBtn">❚❚ </button>
</div>


<form id="logSelectForm" method="get" style="margin-bottom:10px;">
  <select id="file" name="file">
    <?php foreach ($files as $f): ?>
      <option value="<?= htmlspecialchars($f) ?>" <?= $f === $current ? 'selected' : '' ?>>
        <?= htmlspecialchars($f) ?>
      </option>
    <?php endforeach; ?>
  </select>
</form>


<div id="log">Loading...</div>

<script>
const logDiv = document.getElementById("log");
const logSelect = document.getElementById("file");
const toggleBtn = document.getElementById("toggleAjaxBtn");

let currentFile = "<?= addslashes($current) ?>";
let autoRefresh = true;
const refreshInterval = 2000;







function fetchLog() {
    fetch('scheduler_log_fetch.php?file=' + encodeURIComponent(currentFile))
        .then(res => res.text())
        .then(txt => {
            // Detect if user is near the bottom (allow 5px tolerance)
            const nearBottom = logDiv.scrollHeight - logDiv.scrollTop - logDiv.clientHeight < 5;

            logDiv.textContent = txt;

            // Only auto-scroll if in active mode AND user was near bottom
            if (autoRefresh && nearBottom) {
                logDiv.scrollTop = logDiv.scrollHeight;
            }
        })
        .catch(err => console.error(err));
}







/*
// Fetch log content
function fetchLog() {
    if (!autoRefresh) return;

    fetch('scheduler_log_fetch.php?file=' + encodeURIComponent(currentFile))
        .then(res => res.text())
        .then(txt => {
            // Check if user is at bottom
            const isAtBottom = logDiv.scrollHeight - logDiv.scrollTop === logDiv.clientHeight;

            logDiv.textContent = txt;

            // Only scroll if we were at bottom
            if (isAtBottom) logDiv.scrollTop = logDiv.scrollHeight;
        })
        .catch(err => console.error(err));
}
 */







// Initial load
fetchLog();
setInterval(fetchLog, refreshInterval);

// Toggle auto-refresh
toggleBtn.addEventListener('click', function() {
    autoRefresh = !autoRefresh;
    this.textContent = autoRefresh ? '❚❚' : '▶';
});

// Change log file
logSelect.addEventListener('change', function() {
    currentFile = this.value;

    // Reset scroll
    logDiv.scrollTop = 0;

    // Resume auto-refresh if it was paused
    if (!autoRefresh) {
        autoRefresh = true;
        toggleBtn.textContent = '❚❚';
    }

    // Immediately fetch new log
    fetchLog();
});
</script>

</body>
</html>
