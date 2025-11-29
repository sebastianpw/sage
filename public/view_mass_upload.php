<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// Action dispatcher for AJAX requests
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $spw = \App\Core\SpwBase::getInstance();
    $mysqli = $spw->getMysqli();

    switch ($_REQUEST['action']) {
        // ACTION: Fetch data needed to build the import UI
        case 'get_import_data':
            require_once __DIR__ . '/SpawnGalleryManager.php';
            
            $importDir = PROJECT_ROOT . '/public/import/frames_2_spawns/';
            $files = glob($importDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
            $fileBasenames = array_map('basename', $files);
            
            $galleryManager = new SpawnGalleryManager($mysqli);
            $spawnTypes = $galleryManager->getSpawnTypes();
            $importableTypes = array_filter($spawnTypes, fn($t) => $t['batch_import_enabled']);
            
            echo json_encode([
                'success' => true,
                'files' => $fileBasenames,
                'spawnTypes' => array_values($importableTypes) // Re-index for JS
            ]);
            exit;

        // ACTION: Run the actual spawn import process
        case 'import_spawns':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Invalid request method']);
                exit;
            }
            require_once __DIR__ . '/SpawnBatchImporter.php';

            $selectedFiles = $_POST['files'] ?? [];
            $spawnTypeId = $_POST['spawn_type_id'] ?? null;
            $importDir = PROJECT_ROOT . '/public/import/frames_2_spawns/';

            if (empty($selectedFiles) || empty($spawnTypeId)) {
                echo json_encode(['success' => false, 'results' => ['No files or spawn type selected.']]);
                exit;
            }

            // Prepend full path to filenames
            $fullPathFiles = array_map(fn($f) => $importDir . $f, $selectedFiles);

            $importer = new SpawnBatchImporter($mysqli, (int)$spawnTypeId);
            $importResults = $importer->runImport($fullPathFiles);

            echo json_encode([
                'success' => true,
                'results' => $importResults
            ]);
            exit;
    }
}


// Handle file upload (original functionality)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    header('Content-Type: application/json');
    
    $uploadDir = PROJECT_ROOT . '/public/import/frames_2_spawns/';
    
    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $results = [];
    $files = $_FILES['images'];
    
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
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpName);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $results[] = ['success' => false, 'filename' => $fileName, 'error' => 'Invalid file type. Only images allowed.'];
            continue;
        }
        
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $uniqueName = $baseName . '_' . uniqid() . '.' . $extension;
        $destination = $uploadDir . $uniqueName;
        
        if (move_uploaded_file($fileTmpName, $destination)) {
            $results[] = [
                'success' => true, 'filename' => $fileName, 'saved_as' => $uniqueName,
                'size' => $fileSize, 'path' => '/import/frames_2_spawns/' . $uniqueName
            ];
        } else {
            $results[] = ['success' => false, 'filename' => $fileName, 'error' => 'Failed to move uploaded file'];
        }
    }
    
    echo json_encode([
        'status' => 'success', 'results' => $results, 'total' => count($results),
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
    <title>Mass Image Uploader & Importer</title>
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
        .file-preview { width: 60px; height: 60px; border-radius: 4px; object-fit: cover; background-color: var(--bg); flex-shrink: 0; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
        .file-size { font-size: 12px; color: var(--text-muted); }
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
        /* Styles for new import section */
        #importSection .file-list li { margin-bottom: 0.3em; list-style: none; }
        #importSection .spawn-type-selector { margin: 15px 0; padding: 10px; background: var(--card-weak); border-radius: 6px; }
        #importSection .spawn-type-selector label { font-weight: 600; margin-right: 10px; color: var(--muted); }
        .import-disabled { padding: 15px; border-radius: 6px; background: var(--card-weak); border: 1px solid rgba(var(--muted-border-rgb), 0.1); }
        /* NEW: Styles for import results list */
        .result { margin: 5px 0; padding: 8px 10px; border-radius: 6px; font-family: monospace; font-size: 14px; }
        .result.success { background: rgba(16,185,129,0.06); color: var(--success); border: 1px solid rgba(16,185,129,0.08); }
        .result.error { background: rgba(239,68,68,0.04); color: var(--danger); border: 1px solid rgba(239,68,68,0.06); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3 style="margin: 0 0 0 45px;">Mass Image Uploader & Importer</h3>
            <div class="flex-gap">
                <span class="badge badge-blue">frames_2_spawns</span>
            </div>
        </div>

        <div id="uploadSection" class="section">
            <div class="upload-area" id="uploadArea">
                <div class="upload-area-icon">📤</div>
                <div class="upload-area-text">Click to select images or drag and drop</div>
                <div class="upload-area-hint">Support for JPEG, PNG, GIF, WebP</div>
                <input type="file" id="fileInput" accept="image/*" multiple style="display: none;">
            </div>

            <div id="fileListSection" class="hidden">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 class="section-header" style="margin: 0;">Step 1: Select Files</h2>
                    <div class="flex-gap">
                        <button id="clearBtn" class="btn btn-secondary btn-sm">Clear All</button>
                        <button id="uploadBtn" class="btn btn-primary">
                            <span id="uploadBtnText">Upload All</span>
                            <span style="display:none;" id="uploadBtnSpinner" class="spinner"></span>
                        </button>
                    </div>
                </div>
                <div class="file-list" id="fileList"></div>
            </div>
        </div>

        <div id="summarySection" class="section hidden">
            <h2 class="section-header">Upload Summary</h2>
            <div class="upload-summary">
                <div class="summary-item"><div class="summary-value" id="totalFiles">0</div><div class="summary-label">Total Files</div></div>
                <div class="summary-item"><div class="summary-value" id="successFiles">0</div><div class="summary-label">Successful</div></div>
                <div class="summary-item"><div class="summary-value" id="failedFiles">0</div><div class="summary-label">Failed</div></div>
                <div class="summary-item"><div class="summary-value" id="totalSize">0 MB</div><div class="summary-label">Total Size</div></div>
            </div>
        </div>
        
        <div id="importSection" class="section hidden"></div>
        
        <!-- NEW: Placeholder for the import results list -->
        <div id="importResultsSection" class="section hidden"></div>

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
        const importResultsSection = document.getElementById('importResultsSection'); // Get results container
        
        let selectedFiles = [];
        let uploadInProgress = false;

        uploadArea.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
        uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
        uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.classList.remove('drag-over'); handleFiles(e.dataTransfer.files); });
        
        function handleFiles(files) {
            const newFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
            if (newFiles.length < files.length) {
                showToast(`Some files were not images and were ignored.`, 'error');
            }
            selectedFiles = [...selectedFiles, ...newFiles];
            renderFileList();
            updateSummary();
            fileListSection.classList.remove('hidden');
        }
        
        function renderFileList() {
            fileList.innerHTML = selectedFiles.map((file, index) => `
                <div class="file-card" data-index="${index}">
                    <img class="file-preview" alt="${file.name}" src="${URL.createObjectURL(file)}">
                    <div class="file-info">
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${formatFileSize(file.size)}</div>
                    </div>
                    <div class="file-progress">
                        <div class="progress-bar-container"><div class="progress-bar" style="width: 0%"></div></div>
                        <div class="progress-text">Waiting...</div>
                    </div>
                    <div class="file-status"><div class="status-icon">🕒</div></div>
                    <button class="btn btn-danger btn-sm" style="min-width: 32px;" onclick="removeFile(${index})">&times;</button>
                </div>
            `).join('');
        }
        
        window.removeFile = function(index) {
            if (uploadInProgress) return showToast('Cannot remove files during upload', 'error');
            selectedFiles.splice(index, 1);
            renderFileList();
            updateSummary();
            if (selectedFiles.length === 0) {
                fileListSection.classList.add('hidden');
                summarySection.classList.add('hidden');
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
        
        // --- UPDATED "Clear All" LOGIC ---
        clearBtn.addEventListener('click', () => {
            if (uploadInProgress) return showToast('Cannot clear files during upload', 'error');
            
            // Reset file selection
            selectedFiles = [];
            fileList.innerHTML = '';
            fileInput.value = '';

            // Hide all sections
            fileListSection.classList.add('hidden');
            summarySection.classList.add('hidden');
            importSection.classList.add('hidden');
            importResultsSection.classList.add('hidden');
            importSection.innerHTML = ''; // Clear content
            importResultsSection.innerHTML = ''; // Clear content

            // Reset the upload button
            uploadBtn.disabled = false;
            uploadBtnText.textContent = "Upload All";
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
                    formData.append('images', file);
                    const response = await fetch(window.location.pathname, { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.status === 'success' && result.results[0].success) {
                        progressBar.style.width = '100%';
                        progressText.textContent = `Complete! Saved as ${result.results[0].saved_as}`;
                        statusIcon.className = 'status-icon status-success';
                        statusIcon.textContent = '✓';
                        successCount++;
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
            // uploadBtn is kept disabled, but clearBtn is now available
            clearBtn.disabled = false; 
            uploadBtnText.textContent = "Uploaded";
            uploadBtnText.style.display = 'inline-block';
            uploadBtnSpinner.style.display = 'none';
            
            if (successCount > 0) {
                showToast(`Successfully uploaded ${successCount} file(s)`, 'success');
                loadImportUI();
            }
            if (failCount > 0) {
                showToast(`${failCount} file(s) failed to upload`, 'error');
            }
        });

        async function loadImportUI() {
            try {
                const response = await fetch('?action=get_import_data');
                const data = await response.json();
                if (!data.success) throw new Error('Could not fetch import data.');

                let content = `<h2 class="section-header">Step 2: Import Spawns</h2>`;
                if (data.files.length === 0) {
                    content += `<p>No files found in the import directory.</p>`;
                } else if (data.spawnTypes.length === 0) {
                    content += `<div class="import-disabled"><strong>⚠️ Batch import is not enabled for any spawn type.</strong><p style="margin:8px 0 0 0">Please enable batch import in the database for at least one spawn type.</p></div>`;
                } else {
                    const spawnOptions = data.spawnTypes.map(type => `<option value="${type.id}">${type.label}</option>`).join('');
                    const fileCheckboxes = data.files.map(file => `<li><label style="display:inline-flex;align-items:center;gap:8px;"><input type="checkbox" name="import_files[]" value="${file}" checked><span>${file}</span></label></li>`).join('');
                    content += `
                    <div class="card">
                        <form id="importForm">
                            <div class="spawn-type-selector"><label for="spawn_type_id">Import as:</label><select name="spawn_type_id" id="spawn_type_id" class="form-control">${spawnOptions}</select></div>
                            <label style="margin-bottom: 10px; display:block;"><input type="checkbox" onchange="toggleSelectAll(this, 'import_files[]')" checked> <strong>Select / Deselect All</strong></label>
                            <ul class="file-list" style="max-height: 300px; overflow-y: auto; padding: 10px; border: 1px solid rgba(var(--muted-border-rgb),0.2); border-radius: 6px;">${fileCheckboxes}</ul>
                            <div style="margin-top:20px;"><button type="submit" class="btn btn-primary"><span class="btn-text">Start Import</span><span class="spinner" style="display:none;"></span></button></div>
                        </form>
                    </div>`;
                }
                importSection.innerHTML = content;
                importSection.classList.remove('hidden');

                const importForm = document.getElementById('importForm');
                if (importForm) {
                    importForm.addEventListener('submit', handleImportSubmit);
                }
            } catch (error) { showToast(error.message, 'error'); }
        }

        // --- UPDATED Import Submit LOGIC ---
        async function handleImportSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const button = form.querySelector('button[type="submit"]');
            const btnText = button.querySelector('.btn-text');
            const btnSpinner = button.querySelector('.spinner');
            const formData = new FormData(form);
            const selectedFiles = formData.getAll('import_files[]');
            
            if (selectedFiles.length === 0) return showToast('Please select at least one file to import.', 'error');
            
            button.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline-block';

            try {
                const postData = new FormData();
                postData.append('action', 'import_spawns');
                postData.append('spawn_type_id', formData.get('spawn_type_id'));
                selectedFiles.forEach(file => postData.append('files[]', file));

                const response = await fetch(window.location.pathname, { method: 'POST', body: postData });
                const result = await response.json();

                if (result.success && result.results) {
                    // Show toasts
                    result.results.forEach(line => {
                        const isError = /(\[SKIP\]|failed|error)/i.test(line);
                        showToast(line, isError ? 'error' : 'success');
                    });
                    
                    // --- NEW: Display persistent results list ---
                    const resultsHtml = result.results.map(line => {
                        const isError = /(\[SKIP\]|failed|error)/i.test(line);
                        const cls = isError ? 'error' : 'success';
                        return `<div class="result ${cls}">${line}</div>`;
                    }).join('');

                    importResultsSection.innerHTML = `<h3 class="section-header" style="margin-bottom:12px;">Last Import Results</h3><div class="card">${resultsHtml}</div>`;
                    importResultsSection.classList.remove('hidden');
                    
                    // Refresh the import UI to show remaining files
                    loadImportUI();
                } else {
                    throw new Error(result.error || 'Import failed. Check server logs.');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                button.disabled = false;
                btnText.style.display = 'inline-block';
                btnSpinner.style.display = 'none';
            }
        }
        
        window.toggleSelectAll = function(source, name) {
            document.getElementsByName(name).forEach(cb => cb.checked = source.checked);
        }

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
