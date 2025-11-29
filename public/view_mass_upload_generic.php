<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json');
    
    $uploadDir = PROJECT_ROOT . '/public/import/generic/';
    
    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $results = [];
    $files = $_FILES['files'];
    
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        
        if ($fileError !== UPLOAD_ERR_OK) {
            $results[] = ['success' => false, 'filename' => $fileName, 'error' => 'Upload error code: ' . $fileError];
            continue;
        }
        
        // For conversations.json, keep the original name (don't add unique suffix)
        if ($fileName === 'conversations.json') {
            $uniqueName = 'conversations.json';
        } else {
            // For other files, add unique suffix
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $uniqueName = $baseName . '_' . uniqid() . '.' . $extension;
        }
        
        $destination = $uploadDir . $uniqueName;
        
        if (move_uploaded_file($fileTmpName, $destination)) {
            $results[] = [
                'success' => true,
                'filename' => $fileName,
                'saved_as' => $uniqueName,
                'size' => $fileSize,
                'path' => '/import/generic/' . $uniqueName
            ];
        } else {
            $results[] = ['success' => false, 'filename' => $fileName, 'error' => 'Failed to move uploaded file'];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'results' => $results,
        'total' => count($results),
        'successful' => count(array_filter($results, fn($r) => $r['success']))
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>ChatGPT Conversations Importer</title>
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
        .upload-area { border: 2px dashed rgba(var(--muted-border-rgb), 0.5); border-radius: 8px; padding: 40px; text-align: center; background-color: var(--card); transition: all 0.3s ease; cursor: pointer; margin-bottom: 24px; }
        .upload-area:hover, .upload-area.drag-over { border-color: var(--accent); background-color: rgba(59, 130, 246, 0.05); }
        .upload-area-icon { font-size: 48px; color: var(--text-muted); margin-bottom: 16px; }
        .upload-area-text { color: var(--text); font-size: 16px; margin-bottom: 8px; }
        .upload-area-hint { color: var(--text-muted); font-size: 14px; }
        .file-list { display: flex; flex-direction: column; gap: 12px; }
        .file-card { background-color: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); border-radius: 6px; padding: 16px; display: flex; align-items: center; gap: 16px; transition: all 0.2s ease; }
        .file-card:hover { box-shadow: var(--card-elevation); }
        .file-icon { width: 60px; height: 60px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background-color: var(--bg); flex-shrink: 0; font-size: 32px; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
        .file-size { font-size: 12px; color: var(--text-muted); }
        .file-type { font-size: 11px; color: var(--text-muted); text-transform: uppercase; background: var(--bg); padding: 2px 6px; border-radius: 3px; display: inline-block; margin-top: 4px; }
        .file-progress { flex: 1; min-width: 150px; }
        .progress-bar-container { width: 100%; height: 8px; background-color: var(--bg); border-radius: 4px; overflow: hidden; margin-bottom: 4px; }
        .progress-bar { height: 100%; background-color: var(--accent); transition: width 0.3s ease; border-radius: 4px; }
        .progress-text { font-size: 12px; color: var(--text-muted); }
        .file-status { flex-shrink: 0; }
        .status-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; }
        .status-uploading { background-color: var(--blue-light-bg); color: var(--blue-light-text); }
        .status-success { background-color: rgba(35, 134, 54, 0.2); color: var(--green); }
        .status-error { background-color: rgba(218, 54, 51, 0.2); color: var(--red); }
        .upload-summary { display: flex; justify-content: space-between; align-items: center; padding: 16px; background-color: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); border-radius: 6px; margin-top: 24px; }
        .summary-item { text-align: center; }
        .summary-value { font-size: 24px; font-weight: 600; color: var(--text); }
        .summary-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .hidden { display: none; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(var(--muted-border-rgb), 0.3); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .info-box { background-color: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 24px; }
        .info-box-title { font-weight: 600; color: var(--accent); margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .info-box-text { color: var(--text-muted); font-size: 14px; line-height: 1.6; }
        .import-section { background-color: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); border-radius: 8px; padding: 20px; margin-top: 24px; }
        .import-ready { border-color: var(--green); background-color: rgba(35, 134, 54, 0.05); }
        .import-button { width: 100%; padding: 12px; font-size: 16px; font-weight: 600; }
        .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background-color: var(--accent); color: white; font-weight: 700; font-size: 14px; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3 style="margin: 0 0 0 45px;">ChatGPT Conversations Importer</h3>
            <div class="flex-gap">
                <span class="badge badge-green">OpenAI Export</span>
                <span class="badge badge-blue">conversations.json</span>
            </div>
        </div>
        <div class="info-box">
            <div class="info-box-title">
                <span>ℹ️</span>
                <span>How to export your ChatGPT conversations</span>
            </div>
            <div class="info-box-text">
                1. Go to ChatGPT Settings → Data Controls → Export Data<br>
                2. Wait for the email from OpenAI with your data export<br>
                3. Download and extract the ZIP file<br>
                4. Upload the <code>conversations.json</code> file below
            </div>
        </div>
        <div id="uploadSection" class="section">
            <h2 class="section-header"><span class="step-number">1</span>Upload conversations.json</h2>
            <div class="upload-area" id="uploadArea">
                <div class="upload-area-icon">📤</div>
                <div class="upload-area-text">Click to select conversations.json or drag and drop</div>
                <div class="upload-area-hint">Only conversations.json files are accepted</div>
                <input type="file" id="fileInput" accept=".json,application/json" style="display: none;">
            </div>
            <div id="fileListSection" class="hidden">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0;">Selected File</h3>
                    <div class="flex-gap">
                        <button id="clearBtn" class="btn btn-secondary btn-sm">Clear</button>
                        <button id="uploadBtn" class="btn btn-primary">
                            <span id="uploadBtnText">Upload File</span>
                            <span style="display:none;" id="uploadBtnSpinner" class="spinner"></span>
                        </button>
                    </div>
                </div>
                <div class="file-list" id="fileList"></div>
            </div>
        </div>
        <div id="summarySection" class="section hidden">
            <h2 class="section-header">Upload Complete</h2>
            <div class="upload-summary">
                <div class="summary-item"><div class="summary-value" id="totalFiles">0</div><div class="summary-label">Files</div></div>
                <div class="summary-item"><div class="summary-value" id="successFiles">0</div><div class="summary-label">Successful</div></div>
                <div class="summary-item"><div class="summary-value" id="failedFiles">0</div><div class="summary-label">Failed</div></div>
                <div class="summary-item"><div class="summary-value" id="totalSize">0 MB</div><div class="summary-label">Size</div></div>
            </div>
        </div>
        <div id="importSection" class="section import-section hidden">
            <h2 class="section-header"><span class="step-number">2</span>Trigger Import Process</h2>
            <p style="color: var(--text-muted); margin-bottom: 16px;">
                The import process will run in the background via the scheduler. This may take 30+ minutes for large exports. 
                You can close this page and check the progress later in <a href="view_gpt.php" style="color: var(--accent);">GPT Conversations</a>.
            </p>
            <button id="importBtn" class="btn btn-success import-button">
                <span id="importBtnText">🚀 Start Background Import</span>
                <span style="display:none;" id="importBtnSpinner" class="spinner"></span>
            </button>
            <div id="importStatus" class="hidden" style="margin-top: 16px; padding: 12px; border-radius: 6px; background-color: rgba(35, 134, 54, 0.1); border: 1px solid rgba(35, 134, 54, 0.3); color: var(--green);"></div>
        </div>
    </div>
    <div id="toastContainer" class="toast-container"></div>
    <script>
    (function() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileListSection = document.getElementById('fileListSection');
        const fileList = document.getElementById('fileList');
        const uploadBtn = document.getElementById('uploadBtn');
        const clearBtn = document.getElementById('clearBtn');
        const summarySection = document.getElementById('summarySection');
        const uploadBtnText = document.getElementById('uploadBtnText');
        const uploadBtnSpinner = document.getElementById('uploadBtnSpinner');
        const importSection = document.getElementById('importSection');
        const importBtn = document.getElementById('importBtn');
        const importBtnText = document.getElementById('importBtnText');
        const importBtnSpinner = document.getElementById('importBtnSpinner');
        const importStatus = document.getElementById('importStatus');
        
        let selectedFiles = [];
        let uploadInProgress = false;
        let conversationsUploaded = false;

        uploadArea.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
        uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
        uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.classList.remove('drag-over'); handleFiles(e.dataTransfer.files); });
        
        function handleFiles(files) {
            const newFiles = Array.from(files).filter(file => {
                if (file.name !== 'conversations.json') {
                    showToast('Only conversations.json files are accepted', 'error');
                    return false;
                }
                return true;
            });
            
            if (newFiles.length === 0) return;
            
            selectedFiles = newFiles; // Replace, don't add
            renderFileList();
            updateSummary();
            fileListSection.classList.remove('hidden');
        }
        
        function renderFileList() {
            fileList.innerHTML = selectedFiles.map((file, index) => {
                return `
                <div class="file-card" data-index="${index}">
                    <div class="file-icon">📄</div>
                    <div class="file-info">
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${formatFileSize(file.size)}</div>
                        <span class="file-type">json</span>
                    </div>
                    <div class="file-progress">
                        <div class="progress-bar-container"><div class="progress-bar" style="width: 0%"></div></div>
                        <div class="progress-text">Waiting...</div>
                    </div>
                    <div class="file-status"><div class="status-icon">🕒</div></div>
                    <button class="btn btn-danger btn-sm" style="min-width: 32px;" onclick="removeFile(${index})">&times;</button>
                </div>
            `}).join('');
        }
        
        window.removeFile = function(index) {
            if (uploadInProgress) return showToast('Cannot remove files during upload', 'error');
            selectedFiles.splice(index, 1);
            renderFileList();
            updateSummary();
            if (selectedFiles.length === 0) {
                fileListSection.classList.add('hidden');
                summarySection.classList.add('hidden');
                importSection.classList.add('hidden');
            }
        }
        
        function updateSummary() {
            const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
            document.getElementById('totalFiles').textContent = selectedFiles.length;
            document.getElementById('totalSize').textContent = formatFileSize(totalSize);
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        clearBtn.addEventListener('click', () => {
            if (uploadInProgress) return showToast('Cannot clear files during upload', 'error');
            
            selectedFiles = [];
            fileList.innerHTML = '';
            fileInput.value = '';
            fileListSection.classList.add('hidden');
            summarySection.classList.add('hidden');
            importSection.classList.add('hidden');
            conversationsUploaded = false;
            
            uploadBtn.disabled = false;
            uploadBtnText.textContent = "Upload File";
            uploadBtnText.style.display = 'inline-block';
            uploadBtnSpinner.style.display = 'none';
        });
        
        uploadBtn.addEventListener('click', async () => {
            if (selectedFiles.length === 0 || uploadInProgress) return;
            
            uploadInProgress = true;
            uploadBtn.disabled = true;
            clearBtn.disabled = true;
            document.querySelectorAll('.file-card .btn-danger').forEach(b => b.disabled = true);
            uploadBtnText.style.display = 'none';
            uploadBtnSpinner.style.display = 'inline-block';
            
            let successCount = 0;
            let failCount = 0;
            
            for (let i = 0; i < selectedFiles.length; i++) {
                const file = selectedFiles[i];
                const card = fileList.children[i];
                const progressBar = card.querySelector('.progress-bar');
                const progressText = card.querySelector('.progress-text');
                const statusIcon = card.querySelector('.status-icon');
                
                statusIcon.className = 'status-icon status-uploading';
                statusIcon.innerHTML = '<div class="spinner"></div>';
                progressText.textContent = 'Uploading...';
                
                try {
                    const formData = new FormData();
                    formData.append('files', file);
                    const response = await fetch(window.location.pathname, { method: 'POST', body: formData });
                    
                    if (!response.ok) {
                        let errorMsg = `HTTP ${response.status}: ${response.statusText}`;
                        if (response.status === 413) {
                            errorMsg = 'File too large (exceeds server limit)';
                        } else if (response.status === 500) {
                            errorMsg = 'Server error';
                        }
                        throw new Error(errorMsg);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Server returned invalid response (not JSON)');
                    }
                    const result = await response.json();
                    
                    if (result.status === 'success' && result.results[0].success) {
                        progressBar.style.width = '100%';
                        progressText.textContent = `Complete! Saved to /import/generic/`;
                        statusIcon.className = 'status-icon status-success';
                        statusIcon.textContent = '✓';
                        successCount++;
                        conversationsUploaded = true;
                    } else {
                        throw new Error(result.results[0].error || 'Upload failed');
                    }
                } catch (error) {
                    progressBar.style.width = '100%';
                    progressBar.style.backgroundColor = 'var(--red)';
                    progressText.textContent = error.message;
                    statusIcon.className = 'status-icon status-error';
                    statusIcon.textContent = '✗';
                    failCount++;
                }
            }
            
            document.getElementById('successFiles').textContent = successCount;
            document.getElementById('failedFiles').textContent = failCount;
            summarySection.classList.remove('hidden');
            
            uploadInProgress = false;
            clearBtn.disabled = false; 
            uploadBtnText.textContent = "Uploaded";
            uploadBtnText.style.display = 'inline-block';
            uploadBtnSpinner.style.display = 'none';
            
            if (successCount > 0) {
                showToast(`Successfully uploaded conversations.json`, 'success');
                importSection.classList.remove('hidden');
                importSection.classList.add('import-ready');
            }
            if (failCount > 0) {
                showToast(`Upload failed`, 'error');
            }
        });

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
                    return { success: true, message: 'Import task scheduled to run now!' };
                } else {
                    throw new Error('Failed to trigger import task. Server response: ' + resultText.trim());
                }
            } catch (error) {
                console.error('Error triggering scheduler:', error);
                throw new Error(error.message || 'A network error occurred while triggering the task.');
            }
        }
        
        importBtn.addEventListener('click', async () => {
            if (!conversationsUploaded) {
                showToast('Please upload conversations.json first', 'error');
                return;
            }

            if (importBtn.disabled) return;

            importBtn.disabled = true;
            importBtnText.style.display = 'none';
            importBtnSpinner.style.display = 'inline-block';
            
            try {
                const result = await runScheduler(31);
                
                importStatus.textContent = '✓ ' + result.message + ' You can check progress in the GPT Conversations viewer.';
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
                importBtnText.textContent = '🚀 Start Background Import';
                importBtnText.style.display = 'inline-block';
                importBtnSpinner.style.display = 'none';
            }
        });

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
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
