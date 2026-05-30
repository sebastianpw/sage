<?php
// public/testfuzz.php
// ─────────────────────────────────────────────────────────────────────────────
// SAGE - Fuzz Forge Async Clustering Test (UI & AJAX Stream)
// Read-only extraction & PyAPI performance test. NO DB WRITES.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Core\PyApiFuzzService;

/*
    Test-only extraction cap:
    - Set to 0 or false to use all items.
    - Set to an integer like 10000 to stop after that many unique items.
*/
define('LIMIT_ITEMS', 10000);

$initialJobId = isset($_GET['job_id']) ? trim((string)$_GET['job_id']) : '';

// Resolve PyAPI service for the UI and stream handler.
$pyApiFuzzService = new PyApiFuzzService();

// ─── API / SSE STREAM HANDLER ────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'run_stream') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    
    // Prevent PHP from timing out or hitting memory limits on 100k strings
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    while (ob_get_level()) ob_end_clean();

    function streamLine(string $msg, string $type = 'info') {
        echo json_encode(['type' => $type, 'msg' => $msg]) . "\n";
        flush();
    }

    $startTime = microtime(true);

    function normalizeText($text): string {
        // Safety catch: if an array or object slips through the JSON decoder, flatten it
        if (is_array($text) || is_object($text)) {
            $text = is_array($text) ? ($text['name'] ?? json_encode($text)) : json_encode($text);
        }
        
        $t = mb_strtolower(trim((string)$text));
        $t = preg_replace('/[^a-z0-9 ]/u', ' ', $t);
        $t = preg_replace('/^(the|a|an|in|at|on|of|to|from)\s+/u', '', $t);
        $t = preg_replace('/\s+/', ' ', $t);
        return trim($t);
    }

    global $pdo;
    $uniqueStrings = []; // Key: normalized, Value: original
    $rawCount = 0;
    $limitEnabled = (defined('LIMIT_ITEMS') && LIMIT_ITEMS !== false && (int)LIMIT_ITEMS > 0);
    $limitItems = $limitEnabled ? (int)LIMIT_ITEMS : 0;
    $reachedLimit = false;

    streamLine("1. Extracting data from database (READ ONLY)...", "stage");
    if ($limitEnabled) {
        streamLine("   Test limit active: " . number_format($limitItems) . " unique items maximum.", "warn");
    }

    // 1. Sketches
    try {
        $rows = $pdo->query("SELECT name, description FROM sketches LIMIT 20000")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if ($reachedLimit) { break; }

            if ($row['name']) {
                $norm = normalizeText($row['name']);
                if (strlen($norm) > 2) { 
                    $uniqueStrings[$norm] = $row['name']; 
                    $rawCount++; 
                    if ($limitEnabled && count($uniqueStrings) >= $limitItems) { $reachedLimit = true; break; }
                }
            }

            if ($reachedLimit) { break; }

            if ($row['description']) {
                $desc = strip_tags($row['description']);
                if (preg_match_all('/\b([A-Z][a-z\.]+(?:\s+[A-Z][a-z\.]+)+)\b/u', $desc, $matches)) {
                    foreach (array_unique($matches[1]) as $entity) {
                        $norm = normalizeText($entity);
                        if (strlen($norm) > 2 && strlen($norm) <= 60) { 
                            $uniqueStrings[$norm] = $entity; 
                            $rawCount++; 
                            if ($limitEnabled && count($uniqueStrings) >= $limitItems) { 
                                $reachedLimit = true; 
                                break 2; 
                            }
                        }
                    }
                }
            }
        }
        streamLine("  ✓ Sketches processed.", "ok");
        if ($reachedLimit) {
            streamLine("  [!] Test limit reached while processing Sketches.", "warn");
        }
    } catch (Exception $e) { streamLine("  [!] Sketches error: " . $e->getMessage(), "err"); }

    // 2. Sketch Analysis
    if (!$reachedLimit) {
        try {
            $rows = $pdo->query("SELECT entities, thematics FROM sketch_analysis WHERE entities IS NOT NULL OR thematics IS NOT NULL LIMIT 20000")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ($reachedLimit) { break; }

                if ($row['entities']) {
                    $dec = json_decode($row['entities'], true);
                    if (is_array($dec)) {
                        foreach (['characters', 'locations', 'artifacts', 'events', 'concepts'] as $cat) {
                            if (!empty($dec[$cat]) && is_array($dec[$cat])) {
                                foreach ($dec[$cat] as $item) {
                                    $name = is_string($item) ? $item : ($item['name'] ?? '');
                                    if ($name) {
                                        $norm = normalizeText($name);
                                        if (strlen($norm) > 2) { 
                                            $uniqueStrings[$norm] = $name; 
                                            $rawCount++; 
                                            if ($limitEnabled && count($uniqueStrings) >= $limitItems) { 
                                                $reachedLimit = true; 
                                                break 3; 
                                            }
                                        }
                                    }
                                }
                            }
                            if ($reachedLimit) { break; }
                        }
                    }
                }

                if ($reachedLimit) { break; }

                if ($row['thematics']) {
                    $dec = json_decode($row['thematics'], true);
                    if (is_array($dec) && !empty($dec['primary_themes']) && is_array($dec['primary_themes'])) {
                        foreach ($dec['primary_themes'] as $th) {
                            $norm = normalizeText($th);
                            if (strlen($norm) > 2) { 
                                // Store original string (if it's an array, json encode it for display)
                                $orig = is_array($th) ? json_encode($th) : (string)$th;
                                $uniqueStrings[$norm] = $orig; 
                                $rawCount++; 
                                if ($limitEnabled && count($uniqueStrings) >= $limitItems) { 
                                    $reachedLimit = true; 
                                    break 2; 
                                }
                            }
                        }
                    }
                }
            }
            streamLine("  ✓ Sketch Analysis processed.", "ok");
            if ($reachedLimit) {
                streamLine("  [!] Test limit reached while processing Sketch Analysis.", "warn");
            }
        } catch (Exception $e) { streamLine("  [!] Analysis error: " . $e->getMessage(), "err"); }
    }

    // 3. Lore History
    if (!$reachedLimit) {
        try {
            $rows = $pdo->query("SELECT entity_name FROM sketch_lore_history WHERE entity_name IS NOT NULL AND entity_name != '' LIMIT 10000")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $norm = normalizeText($row['entity_name']);
                if (strlen($norm) > 2) { 
                    $uniqueStrings[$norm] = $row['entity_name']; 
                    $rawCount++; 
                    if ($limitEnabled && count($uniqueStrings) >= $limitItems) { 
                        $reachedLimit = true; 
                        break; 
                    }
                }
            }
            streamLine("  ✓ Lore History processed.", "ok");
            if ($reachedLimit) {
                streamLine("  [!] Test limit reached while processing Lore History.", "warn");
            }
        } catch (Exception $e) { streamLine("  [!] Lore error: " . $e->getMessage(), "err"); }
    }

    // 4. KG Nodes
    if (!$reachedLimit) {
        try {
            $rows = $pdo->query("SELECT name FROM kg_nodes WHERE status = 'active' LIMIT 10000")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $norm = normalizeText($row['name']);
                if (strlen($norm) > 2) { 
                    $uniqueStrings[$norm] = $row['name']; 
                    $rawCount++; 
                    if ($limitEnabled && count($uniqueStrings) >= $limitItems) { 
                        $reachedLimit = true; 
                        break; 
                    }
                }
            }
            streamLine("  ✓ KG Nodes processed.", "ok");
            if ($reachedLimit) {
                streamLine("  [!] Test limit reached while processing KG Nodes.", "warn");
            }
        } catch (Exception $e) { streamLine("  [!] KG error: " . $e->getMessage(), "err"); }
    }

    // 5. Ingredients
    if (!$reachedLimit) {
        try {
            $rows = $pdo->query("SELECT prompt_fragment FROM sketch_ingredients WHERE prompt_fragment IS NOT NULL LIMIT 10000")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $frag = mb_substr(trim($row['prompt_fragment']), 0, 100);
                $norm = normalizeText($frag);
                if (strlen($norm) > 2) { 
                    $uniqueStrings[$norm] = $frag; 
                    $rawCount++; 
                    if ($limitEnabled && count($uniqueStrings) >= $limitItems) { 
                        $reachedLimit = true; 
                        break; 
                    }
                }
            }
            streamLine("  ✓ Ingredients processed.", "ok");
            if ($reachedLimit) {
                streamLine("  [!] Test limit reached while processing Ingredients.", "warn");
            }
        } catch (Exception $e) { streamLine("  [!] Ingredients error: " . $e->getMessage(), "err"); }
    }

    $extractTime = microtime(true) - $startTime;
    $uniqueCount = count($uniqueStrings);

    streamLine("----------------------------------------");
    streamLine("EXTRACTION STATS:", "stage");
    streamLine("- Raw Items Collected: " . number_format($rawCount));
    streamLine("- Unique Normalized Strings: " . number_format($uniqueCount));
    streamLine("- Extraction Time: " . number_format($extractTime, 2) . " seconds");
    if ($limitEnabled) {
        streamLine("- Test Item Limit: " . number_format($limitItems));
    }
    streamLine("----------------------------------------");

    if ($uniqueCount === 0) {
        streamLine("No data to send. Exiting.", "err");
        exit;
    }

    // Build Payload
    $payloadItems = [];
    foreach ($uniqueStrings as $norm => $orig) {
        $payloadItems[] = ["id" => $norm, "text" => $norm];
    }

    streamLine("\n2. Sending " . number_format($uniqueCount) . " items to PyAPI for TF-IDF Sparse Clustering...", "stage");

    // Use the new wrapper class for submit + polling.
    try {
        $submitData = $pyApiFuzzService->clusterAsync($payloadItems, 0.82);
    } catch (Exception $e) {
        streamLine("\n[!] Async job submission failed: " . $e->getMessage(), "err");
        exit;
    }

    if (!$submitData || empty($submitData['job_id'])) {
        streamLine("\n[!] Invalid JSON response from async submit.", "err");
        exit;
    }

    $jobId = (string)$submitData['job_id'];
    $statusEndpoint = rtrim($pyApiFuzzService->getApiUrl(), '/') . '/fuzz/cluster/status/' . rawurlencode($jobId);

    streamLine("   Job ID: $jobId", "ok");
    streamLine("   Status Endpoint: $statusEndpoint", "info");
    streamLine("   (The job is now async. The client will poll status updates instead of waiting on one long tablet connection.)", "warn");

    $pyapiStart = microtime(true);

    $lastStatusLine = '';
    $clusters = null;

    while (true) {
        try {
            $statusData = $pyApiFuzzService->getClusterJobStatus($jobId);
        } catch (Exception $e) {
            streamLine("\n[!] Error while polling job status: " . $e->getMessage(), "err");
            exit;
        }

        if (!$statusData || empty($statusData['status'])) {
            streamLine("\n[!] Invalid JSON response from status endpoint.", "err");
            exit;
        }

        $status = (string)$statusData['status'];
        $progress = $statusData['progress'] ?? [];
        $stage = $progress['stage'] ?? $status;
        $processed = isset($progress['processed']) ? (int)$progress['processed'] : null;
        $total = isset($progress['total']) ? (int)$progress['total'] : null;
        $message = $progress['message'] ?? '';
        $line = '';

        if ($message !== '') {
            $line = $message;
        } elseif ($processed !== null && $total !== null) {
            $line = "Progress: " . number_format($processed) . "/" . number_format($total) . " items";
        } else {
            $line = "Status: $status";
        }

        if ($line !== $lastStatusLine || $status === 'success' || $status === 'error') {
            $type = 'info';
            if ($status === 'success') {
                $type = 'ok';
            } elseif ($status === 'error' || $status === 'failed') {
                $type = 'err';
            } elseif ($stage === 'assembling' || $stage === 'vectorizing' || $stage === 'similarity' || $stage === 'clustering') {
                $type = 'stage';
            }
            streamLine("   $line", $type);
            $lastStatusLine = $line;
        }

        if ($status === 'success') {
            $clusters = $statusData['clusters'] ?? ($statusData['result']['clusters'] ?? null);
            break;
        }

        if ($status === 'error' || $status === 'failed') {
            $errMsg = $statusData['error'] ?? 'Unknown async job failure.';
            streamLine("\n[!] Async job failed: $errMsg", "err");
            exit;
        }

        usleep(1500000); // 1.5 seconds between polls
    }

    $pyapiTime = microtime(true) - $pyapiStart;

    if (!is_array($clusters)) {
        streamLine("\n[!] Invalid clustering result returned by PyAPI.", "err");
        exit;
    }

    $totalClusters = count($clusters);

    streamLine("\n----------------------------------------");
    streamLine("CLUSTERING STATS:", "stage");
    streamLine("- Total Clusters Returned: " . number_format($totalClusters));
    streamLine("- PyAPI Clustering Time: " . number_format($pyapiTime, 2) . " seconds");
    streamLine("- Total Run Time: " . number_format(microtime(true) - $startTime, 2) . " seconds");
    streamLine("----------------------------------------\n");

    streamLine("SAMPLE MERGED CLUSTERS (Items grouped together):", "found");

    $mergedCount = 0;
    foreach ($clusters as $cluster) {
        if (count($cluster) > 1) {
            $mergedCount++;
            if ($mergedCount <= 20) {
                streamLine("Cluster #$mergedCount:");
                foreach ($cluster as $item) {
                    $orig = $uniqueStrings[$item] ?? $item;
                    streamLine("  - \"$orig\" (normalized: '$item')");
                }
                streamLine(""); // spacer
            }
        }
    }

    streamLine("Total clusters containing >1 string (typos/variants caught): " . number_format($mergedCount), "ok");
    streamLine("\nTEST COMPLETE. (No databases were harmed during this test).", "ok");
    exit;
}

// Resolve PyAPI URL for the UI polling/resume mode.
$uiPyapiUrl = $pyApiFuzzService->getApiUrl();
if (!$uiPyapiUrl) {
    $script = __DIR__ . '/../bash/pyapi_echo.sh';
    if (file_exists($script)) {
        $uiPyapiUrl = trim(shell_exec('bash ' . escapeshellarg($script)));
    }
}
if (!$uiPyapiUrl) {
    $uiPyapiUrl = "http://10.116.108.8:8009"; // Fallback based on your echo script
}
$uiPyapiUrl = rtrim($uiPyapiUrl, '/');

// ─── UI RENDER ───────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fuzz Forge - Async Cluster Test</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Barlow+Condensed:wght@400;600&display=swap" rel="stylesheet">
<style>
    :root {
        --bg: #05070d;
        --card: #0c1020;
        --border: #161e30;
        --text: #b8c8e0;
        --cyan: #00d4ff;
        --green: #00e5a0;
        --orange: #ff6b2b;
        --red: #ff3d5a;
        --purple: #a855f7;
    }
    body {
        background: var(--bg);
        color: var(--text);
        font-family: 'Barlow Condensed', sans-serif;
        margin: 0; padding: 20px;
        display: flex; flex-direction: column; align-items: center;
        height: 100vh; box-sizing: border-box;
    }
    .header {
        text-align: center;
        margin-bottom: 16px;
    }
    .header h1 {
        font-size: 2rem; color: var(--cyan); letter-spacing: 2px; margin: 0 0 5px 0;
        text-transform: uppercase;
    }
    .header p {
        font-family: 'DM Mono', monospace; font-size: 0.8rem; color: var(--text); margin: 0;
        opacity: 0.7;
    }
    .controls {
        margin-bottom: 14px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
    }
    button {
        background: rgba(0, 212, 255, 0.1);
        border: 1px solid var(--cyan);
        color: var(--cyan);
        padding: 10px 24px;
        font-family: 'DM Mono', monospace;
        font-size: 1rem;
        font-weight: bold;
        text-transform: uppercase;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 0 10px rgba(0, 212, 255, 0.2);
    }
    button:hover:not(:disabled) {
        background: var(--cyan);
        color: #000;
        box-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
    }
    button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        box-shadow: none;
    }
    .job-panel {
        width: 100%;
        max-width: 900px;
        margin: 0 0 14px 0;
        border: 1px solid var(--border);
        background: rgba(12, 16, 32, 0.85);
        border-radius: 8px;
        padding: 12px 14px;
        box-sizing: border-box;
        font-family: 'DM Mono', monospace;
        font-size: 0.8rem;
        line-height: 1.5;
    }
    .job-panel .label {
        color: var(--purple);
        font-weight: bold;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .job-panel .value {
        color: var(--text);
        word-break: break-all;
        margin-bottom: 8px;
    }
    .job-panel .resume-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .job-panel input {
        flex: 1;
        min-width: 220px;
        background: #020306;
        border: 1px solid var(--border);
        color: var(--cyan);
        font-family: 'DM Mono', monospace;
        font-size: 0.8rem;
        padding: 10px 12px;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .job-panel .hint {
        margin-top: 8px;
        color: #9ba8c0;
    }
    .console-wrapper {
        width: 100%; max-width: 900px;
        flex: 1; min-height: 0;
        background: #020306;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 16px;
        overflow-y: auto;
        box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
    }
    .console {
        font-family: 'DM Mono', monospace;
        font-size: 0.85rem;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-word;
    }
    /* SSE Line Colors */
    .line-info { color: #9ba8c0; }
    .line-ok { color: var(--green); }
    .line-warn { color: var(--orange); }
    .line-err { color: var(--red); }
    .line-found { color: var(--cyan); font-weight: bold; }
    .line-stage { color: var(--purple); font-weight: bold; margin-top: 10px; display: block; }
    .line-job { color: var(--cyan); font-weight: bold; }
</style>
</head>
<body>

<div class="header">
    <h1>Fuzz Forge Clustering Test</h1>
    <p>Read-Only Database Extraction + Tablet PyAPI Sparse-Matrix Resolution</p>
</div>

<div class="job-panel" id="jobPanel" style="display:none;">
    <div class="label">Active Job</div>
    <div class="value" id="jobInfo">No job loaded.</div>
    <div class="resume-row">
        <input id="resumeUrlBox" type="text" readonly value="">
        <button id="btnCopyUrl" type="button" onclick="copyResumeUrl()" style="display:none;">Copy Resume URL</button>
        <button id="btnDownloadJson" type="button" onclick="downloadResultJson()" style="display:none;">Download Result JSON</button>
    </div>
    <div class="hint">You can close this page and reopen the resume URL later to continue polling the same job.</div>
</div>

<div class="controls">
    <button id="btnRun" onclick="startTest()">Start Clustering Test</button>
</div>

<div class="console-wrapper" id="consoleWrap">
    <div class="console" id="consoleBox">Waiting for command...</div>
</div>

<script>
const initialJobId = <?= json_encode($initialJobId) ?>;
const pyapiBase = <?= json_encode($uiPyapiUrl) ?>;

const btnRun = document.getElementById('btnRun');
const btnCopyUrl = document.getElementById('btnCopyUrl');
const btnDownloadJson = document.getElementById('btnDownloadJson');
const jobPanel = document.getElementById('jobPanel');
const jobInfo = document.getElementById('jobInfo');
const resumeUrlBox = document.getElementById('resumeUrlBox');
const logBox = document.getElementById('consoleBox');
const wrap = document.getElementById('consoleWrap');

let currentJobId = initialJobId || '';
let currentJobResult = null;
let activePollToken = 0;

function appendLine(text, type = 'info') {
    const div = document.createElement('span');
    div.className = 'line-' + type;
    div.textContent = text + '\n';
    logBox.appendChild(div);
    wrap.scrollTop = wrap.scrollHeight;
}

function getResumeUrl(jobId) {
    return window.location.origin + window.location.pathname + '?job_id=' + encodeURIComponent(jobId);
}

function applyJobState(jobId) {
    currentJobId = jobId;
    const resumeUrl = getResumeUrl(jobId);

    jobPanel.style.display = 'block';
    jobInfo.textContent = 'Job ID: ' + jobId;
    resumeUrlBox.value = resumeUrl;
    btnCopyUrl.style.display = 'inline-block';
}

function setResultState(jobId, resultData) {
    currentJobId = jobId;
    currentJobResult = resultData;

    try {
        // Keep a local cache when possible so a finished result can be recovered after reload.
        // Large payloads may exceed storage limits; that is fine because the download button
        // still works from the in-memory result during this session.
        localStorage.setItem('fuzzResult:' + jobId, JSON.stringify(resultData));
    } catch (e) {
        // Ignore quota errors and keep the in-memory result only.
    }

    btnDownloadJson.style.display = 'inline-block';
}

function loadCachedResult(jobId) {
    try {
        const raw = localStorage.getItem('fuzzResult:' + jobId);
        if (!raw) {
            return null;
        }
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}

function copyResumeUrl() {
    if (!currentJobId) {
        appendLine('No job id available to copy.', 'warn');
        return;
    }

    const resumeUrl = getResumeUrl(currentJobId);

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(resumeUrl).then(function () {
            appendLine('Resume URL copied to clipboard.', 'ok');
        }).catch(function () {
            fallbackCopy(resumeUrl);
        });
    } else {
        fallbackCopy(resumeUrl);
    }
}

function fallbackCopy(text) {
    try {
        const tmp = document.createElement('input');
        tmp.value = text;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        appendLine('Resume URL copied to clipboard.', 'ok');
    } catch (e) {
        appendLine('Copy failed. You can manually copy the URL from the field above.', 'warn');
    }
}

function downloadBlob(filename, content, mimeType) {
    const blob = new Blob([content], { type: mimeType || 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();

    setTimeout(function () {
        URL.revokeObjectURL(url);
    }, 1000);
}

function downloadResultJson() {
    if (!currentJobResult) {
        appendLine('No finished result is available to download yet.', 'warn');
        return;
    }

    const jobId = currentJobId || (currentJobResult.job_id ?? 'unknown');
    const filename = 'fuzz-cluster-result-' + jobId + '.json';

    try {
        const jsonText = JSON.stringify(currentJobResult, null, 2);
        downloadBlob(filename, jsonText, 'application/json;charset=utf-8');
        appendLine('Result JSON download started.', 'ok');
    } catch (e) {
        appendLine('Could not serialize result JSON for download.', 'err');
    }
}

function navigateToJob(jobId) {
    const resumeUrl = getResumeUrl(jobId);
    window.location.replace(resumeUrl);
}

async function pollJob(jobId) {
    const myToken = ++activePollToken;
    const statusEndpoint = pyapiBase.replace(/\/+$/, '') + '/fuzz/cluster/status/' + encodeURIComponent(jobId);

    appendLine('Resuming job ' + jobId + ' ...', 'stage');

    let lastStatusLine = '';

    while (myToken === activePollToken) {
        try {
            const response = await fetch(statusEndpoint, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();

            if (data.progress && data.progress.message) {
                const progressLine = data.progress.message;
                if (progressLine !== lastStatusLine) {
                    const stage = (data.progress.stage || data.status || 'info').toLowerCase();
                    let type = 'info';
                    if (data.status === 'success') {
                        type = 'ok';
                    } else if (data.status === 'error' || data.status === 'failed') {
                        type = 'err';
                    } else if (stage === 'vectorizing' || stage === 'similarity' || stage === 'clustering' || stage === 'assembling' || stage === 'starting' || stage === 'queued') {
                        type = 'stage';
                    }
                    appendLine(progressLine, type);
                    lastStatusLine = progressLine;
                }
            }

            if (data.status === 'success') {
                const clusters = data.clusters || (data.result && data.result.clusters) || [];
                appendLine('Job finished successfully.', 'ok');
                appendLine('Clusters: ' + clusters.length, 'ok');

                setResultState(jobId, data);

                // If a cached copy is already available or just created, offer download immediately.
                btnDownloadJson.style.display = 'inline-block';
                break;
            }

            if (data.status === 'error' || data.status === 'failed') {
                appendLine('ERROR: ' + (data.error || 'Unknown async job failure.'), 'err');
                break;
            }

            await new Promise(function (resolve) {
                setTimeout(resolve, 1500);
            });
        } catch (e) {
            appendLine('Polling error: ' + e.message, 'err');
            break;
        }
    }
}

async function startTest() {
    activePollToken++; // stop any active resume poll on this page
    btnRun.disabled = true;
    btnRun.textContent = 'Processing...';
    logBox.innerHTML = '';
    btnDownloadJson.style.display = 'none';
    currentJobResult = null;

    function streamAppend(text, type = 'info') {
        appendLine(text, type);
    }

    try {
        const response = await fetch('?action=run_stream');
        if (!response.ok) throw new Error('HTTP ' + response.status);

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            
            buffer += decoder.decode(value, { stream: true });
            const parts = buffer.split('\n');
            buffer = parts.pop();
            
            for (const part of parts) {
                const trimmed = part.trim();
                if (!trimmed) continue;
                
                try {
                    const parsed = JSON.parse(trimmed);
                    const message = parsed.msg || '';
                    const type = parsed.type || 'info';

                    streamAppend(message, type);

                    if (message.indexOf('Job ID:') === 0) {
                        const jobId = message.replace(/^Job ID:\s*/i, '').trim();
                        if (jobId) {
                            applyJobState(jobId);
                            appendLine('Reloading with resume URL...', 'warn');
                            navigateToJob(jobId);
                            return;
                        }
                    }
                } catch (e) {
                    streamAppend(trimmed, 'info'); // fallback for raw text
                }
            }
        }
    } catch (e) {
        appendLine('\n[!] Connection Error: ' + e.message, 'err');
    } finally {
        btnRun.disabled = false;
        btnRun.textContent = currentJobId ? 'Start New Test' : 'Run Test Again';
    }
}

function initFromJobId() {
    if (currentJobId) {
        applyJobState(currentJobId);

        const cached = loadCachedResult(currentJobId);
        if (cached) {
            currentJobResult = cached;
            btnDownloadJson.style.display = 'inline-block';
            appendLine('Cached finished result found for this job.', 'ok');
        }

        btnRun.textContent = 'Start New Test';
        pollJob(currentJobId);
    }
}

window.addEventListener('DOMContentLoaded', initFromJobId);
</script>

</body>
</html>