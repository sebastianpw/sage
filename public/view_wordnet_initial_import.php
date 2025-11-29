<?php
// public/view_wordnet_initial_import.php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>WordNet Initial Import</title>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <style>
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(var(--muted-border-rgb), 0.3); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .info-box { background-color: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 24px; }
        .info-box-title { font-weight: 600; color: var(--accent); margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .info-box-text { color: var(--text-muted); font-size: 14px; line-height: 1.6; }
        .import-section { background-color: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); border-radius: 8px; padding: 20px; margin-top: 24px; }
        .import-button { width: 100%; padding: 12px; font-size: 16px; font-weight: 600; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3 style="margin: 0 0 0 45px;">WordNet Initial Import</h3>
            <div class="flex-gap">
                <span class="badge badge-blue">One-Time Setup</span>
            </div>
        </div>

        <div class="info-box">
            <div class="info-box-title">
                <span>ℹ️</span>
                <span>About the WordNet Database Import</span>
            </div>
            <div class="info-box-text">
                Sage includes the necessary <code>wordnet.sql</code> file to populate the database.
                This is a one-time process that imports a large amount of lexical data.
                <br>
                <strong>Warning:</strong> This background task is very resource-intensive and may take <strong>15 to 30 minutes</strong> to complete, depending on your server's performance.
            </div>
        </div>

        <div id="importSection" class="section import-section">
            <h2 class="section-header">Trigger Import Process</h2>
            <p style="color: var(--text-muted); margin-bottom: 16px;">
                Click the button below to start the background import via the scheduler. You can close this page after starting the process.
            </p>
            <button id="importBtn" class="btn btn-success import-button">
                <span id="importBtnText">🚀 Start WordNet Database Import</span>
                <span style="display:none;" id="importBtnSpinner" class="spinner"></span>
            </button>
            <div id="importStatus" class="hidden" style="margin-top: 16px; padding: 12px; border-radius: 6px; background-color: rgba(35, 134, 54, 0.1); border: 1px solid rgba(35, 134, 54, 0.3); color: var(--green);"></div>
        </div>
    </div>
    <div id="toastContainer" class="toast-container"></div>
    
    <script>
    (function() {
        const importBtn = document.getElementById('importBtn');
        const importBtnText = document.getElementById('importBtnText');
        const importBtnSpinner = document.getElementById('importBtnSpinner');
        const importStatus = document.getElementById('importStatus');

        async function runScheduler(id) {
            console.log("Run scheduler triggered, id=", id);
            
            const formData = new FormData();
            formData.append('action', 'run_now');
            formData.append('id', id);
    
            try {
                const response = await fetch('scheduler_view.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`Scheduler trigger failed: Server responded with status ${response.status}`);
                }
                
                const resultText = await response.text();
                
                if (resultText.trim() === 'success') {
                    return { success: true, message: 'WordNet import task scheduled to run now!' };
                } else {
                    throw new Error('Failed to trigger import task. Server response: ' + resultText.trim());
                }
            } catch (error) {
                console.error('Error triggering scheduler:', error);
                throw new Error(error.message || 'A network error occurred while triggering the task.');
            }
        }
        
        importBtn.addEventListener('click', async () => {
            if (importBtn.disabled) return;

            importBtn.disabled = true;
            importBtnText.style.display = 'none';
            importBtnSpinner.style.display = 'inline-block';
            
            try {
                // Use task ID 32 for WordNet import
                const result = await runScheduler(32); 
                
                importStatus.innerHTML = '✓ ' + result.message + 
                    '<br><br>You can now return to the <a href="view_wordnet_admin.php" style="color: var(--accent);">WordNet Admin</a> page. ' +
                    'It may take some time for the data to appear.';
                importStatus.classList.remove('hidden');
                showToast(result.message, 'success');
                
                // Keep button disabled after successful trigger and update its text.
                importBtnText.textContent = '✓ Import Scheduled';
                importBtnText.style.display = 'inline-block';
                importBtnSpinner.style.display = 'none';

            } catch (error) {
                showToast(error.message, 'error');
                
                // Re-enable the button so the user can try again.
                importBtn.disabled = false;
                importBtnText.textContent = '🚀 Start WordNet Database Import';
                importBtnText.style.display = 'inline-block';
                importBtnSpinner.style.display = 'none';
            }
        });

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) {
                console.error('Toast container not found!');
                return;
            }
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 5000);
        }
    })();
    </script>
    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>

