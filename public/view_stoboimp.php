<?php
// public/view_test_import.php
// This file is a static UI component showcase for batch importing.
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Target storyboard from DB (using the one provided in the example)
$storyboardId = 140;

// Grab target frames
// Extracting specifically from the provided SQL example: entity_type='sketches', entity_id=3302
//$stmt = $pdo->prepare("SELECT id FROM frames WHERE entity_type = 'sketches' AND entity_id = ? ORDER BY id ASC");
//$stmt->execute([3302]);


$stmt = $pdo->prepare("SELECT id FROM frames WHERE model = 'gpt-image-2' ORDER BY id ASC");
$stmt->execute();

$frames = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($frames)) {
    // Fallback just to have data for testing if the specific entity doesn't exist
    $stmt = $pdo->query("SELECT id FROM frames ORDER BY id DESC LIMIT 200");
    $frames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $frames = array_reverse($frames);
}

// ── Deduplication: remove frame IDs already linked to this storyboard ─────────
if (!empty($frames)) {
    $placeholders = implode(',', array_fill(0, count($frames), '?'));
    $existStmt = $pdo->prepare(
        "SELECT frame_id
           FROM storyboard_frames
          WHERE storyboard_id = ?
            AND frame_id IN ({$placeholders})"
    );
    $existStmt->execute(array_merge([$storyboardId], $frames));
    $alreadyLinked = array_flip($existStmt->fetchAll(PDO::FETCH_COLUMN));
    $frames = array_values(array_filter($frames, fn($fid) => !isset($alreadyLinked[$fid])));
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Batch Importer UI Showcase</title>
    <script>
    (function() {
        try {
        var theme = localStorage.getItem('spw_theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
        } catch (e) { }
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <style>
        .progress-container {
            background: rgba(var(--muted-border-rgb), 0.2);
            border: 1px solid rgba(var(--muted-border-rgb), 0.5);
            border-radius: 4px;
            overflow: hidden;
            height: 24px;
            margin: 16px 0;
            position: relative;
        }
        .progress-bar {
            background: var(--primary-color, #007bff);
            width: 0%;
            height: 100%;
            transition: width 0.2s linear;
        }
        .progress-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 12px;
            line-height: 24px;
            font-weight: bold;
            color: var(--text-color, #fff);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        .log-container {
            background: rgba(0,0,0,0.05);
            border: 1px solid rgba(var(--muted-border-rgb), 0.5);
            color: var(--text-color);
            padding: 10px;
            height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            border-radius: 4px;
        }
        .log-entry { margin: 2px 0; border-bottom: 1px dotted rgba(128,128,128,0.3); padding-bottom: 2px; }
        .log-success { color: #4caf50; }
        .log-error { color: #f44336; }
        .log-info { color: #2196f3; }
        [data-theme="dark"] .log-container { background: #1e1e1e; }
        .stats-grid { display: flex; gap: 20px; margin-bottom: 16px; }
        .stat-box { background: rgba(var(--muted-border-rgb), 0.1); padding: 10px 15px; border-radius: 4px; flex: 1; }
        .stat-val { font-size: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Batch Storyboard Importer</h1>
            <div class="flex-gap">
                <span class="badge badge-green">Tools</span>
                <span class="badge badge-blue">Batch Process</span>
            </div>
        </div>

        <div class="section">
            <h2 class="section-header">Configuration & Progress</h2>
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Target Storyboard ID</label>
                            <input type="number" id="targetStoryboardId" class="form-control" value="<?= htmlspecialchars($storyboardId) ?>">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Frames per Batch</label>
                            <input type="number" id="batchSize" class="form-control" value="20" min="1" max="100">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Delay Between Batches (ms)</label>
                            <input type="number" id="delayMs" class="form-control" value="250" min="0" max="5000">
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div>Total Frames</div>
                            <div class="stat-val" id="statTotal"><?= count($frames) ?></div>
                        </div>
                        <div class="stat-box">
                            <div>Success</div>
                            <div class="stat-val log-success" id="statSuccess">0</div>
                        </div>
                        <div class="stat-box">
                            <div>Errors</div>
                            <div class="stat-val log-error" id="statError">0</div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div id="progressBar" class="progress-bar"></div>
                        <div id="progressText" class="progress-text">0 / <?= count($frames) ?> (0%)</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Process Log</label>
                        <div id="statusLog" class="log-container">
                            <div class="log-entry log-info">Ready. Found <?= count($frames) ?> new frame(s) to import (already-linked frames excluded).</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer flex-gap">
                    <button id="startImportBtn" class="btn btn-primary">Start / Resume</button>
                    <button id="stopImportBtn" class="btn btn-danger" disabled>Pause Process</button>
                    <button id="resetBtn" class="btn btn-secondary">Reset Progress</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TOAST CONTAINER -->
    <div id="toastContainer" class="toast-container"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Source Data Array (already deduplicated server-side)
        const allFrames = <?= json_encode($frames) ?>;
        
        // Tracking State
        let isImporting = false;
        let currentIndex = 0;
        let successCount = 0;
        let errorCount = 0;

        // UI Binding
        const startImportBtn = document.getElementById('startImportBtn');
        const stopImportBtn = document.getElementById('stopImportBtn');
        const resetBtn = document.getElementById('resetBtn');
        const targetStoryboardIdInput = document.getElementById('targetStoryboardId');
        const batchSizeInput = document.getElementById('batchSize');
        const delayMsInput = document.getElementById('delayMs');
        
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const statusLog = document.getElementById('statusLog');
        const toastContainer = document.getElementById('toastContainer');
        
        const statSuccess = document.getElementById('statSuccess');
        const statError = document.getElementById('statError');

        function showToast(message, type = 'success') {
            if (!toastContainer) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 3000);
        }

        function logMsg(msg, type = 'info') {
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            const time = new Date().toLocaleTimeString();
            entry.innerHTML = `[${time}] ${msg}`;
            statusLog.appendChild(entry);
            statusLog.scrollTop = statusLog.scrollHeight;
        }

        function updateUI() {
            const total = allFrames.length;
            const percent = total > 0 ? Math.round((currentIndex / total) * 100) : 0;
            
            progressBar.style.width = `${percent}%`;
            progressText.textContent = `${currentIndex} / ${total} (${percent}%)`;
            
            statSuccess.textContent = successCount;
            statError.textContent = errorCount;
        }

        async function processBatch() {
            if (!isImporting) return;
            
            if (currentIndex >= allFrames.length) {
                logMsg(`Import completed! Success: ${successCount}, Errors: ${errorCount}`, 'success');
                showToast('Import completed', 'success');
                stopImport();
                return;
            }

            const batchSize = parseInt(batchSizeInput.value, 10) || 20;
            const delayMs = parseInt(delayMsInput.value, 10) || 250;
            const sbId = targetStoryboardIdInput.value.trim();
            
            if (!sbId) {
                logMsg('Storyboard ID is required.', 'error');
                stopImport();
                return;
            }

            const chunk = allFrames.slice(currentIndex, currentIndex + batchSize);
            logMsg(`Sending batch of ${chunk.length} frames (IDs: ${chunk[0]} ... ${chunk[chunk.length-1]})...`, 'info');

            const fd = new URLSearchParams();
            fd.append('storyboard_id', sbId);
            fd.append('frame_ids', JSON.stringify(chunk));

            try {
                const response = await fetch('/storyboard_import.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString()
                });

                let data;
                try {
                    data = await response.json();
                } catch(e) {
                    throw new Error('Invalid JSON response from server');
                }

                if (data.success) {
                    const imported = data.imported_count || 0;
                    const errors = data.error_count || 0;
                    successCount += imported;
                    errorCount += errors;
                    
                    if (errors > 0) {
                        logMsg(`Batch partial: ${imported} imported, ${errors} failed.`, 'error');
                        console.warn('Import Errors:', data.errors);
                    } else {
                        logMsg(`Batch success: ${imported} frames imported.`, 'success');
                    }
                } else {
                    logMsg(`Batch failed: ${data.message || 'Unknown error'}`, 'error');
                    errorCount += chunk.length;
                }

            } catch (err) {
                logMsg(`Network/Server error: ${err.message}`, 'error');
                errorCount += chunk.length;
            }

            currentIndex += chunk.length;
            updateUI();

            // Continue to next batch
            setTimeout(processBatch, delayMs);
        }

        function startImport() {
            if (allFrames.length === 0) {
                showToast('No new frames to import.', 'error');
                return;
            }
            if (currentIndex >= allFrames.length) {
                // Restarting
                currentIndex = 0;
                successCount = 0;
                errorCount = 0;
                statusLog.innerHTML = '';
                updateUI();
            }
            isImporting = true;
            startImportBtn.disabled = true;
            stopImportBtn.disabled = false;
            targetStoryboardIdInput.disabled = true;
            batchSizeInput.disabled = true;
            
            logMsg('Import process started.', 'info');
            processBatch();
        }

        function stopImport() {
            isImporting = false;
            startImportBtn.disabled = false;
            stopImportBtn.disabled = true;
            targetStoryboardIdInput.disabled = false;
            batchSizeInput.disabled = false;
            
            if (currentIndex < allFrames.length) {
                logMsg('Import process paused.', 'info');
            }
        }

        function resetImport() {
            stopImport();
            currentIndex = 0;
            successCount = 0;
            errorCount = 0;
            updateUI();
            statusLog.innerHTML = '<div class="log-entry log-info">Progress reset. Ready to start over.</div>';
        }

        // Attach listeners
        startImportBtn.addEventListener('click', startImport);
        stopImportBtn.addEventListener('click', stopImport);
        resetBtn.addEventListener('click', resetImport);

        // Initial render
        updateUI();
    });
    </script>
</body>
</html>
