<?php
require_once __DIR__ . '/bootstrap.php';
require      __DIR__ . '/env_locals.php';

require_once PROJECT_ROOT . '/src/Dictionary/DictionaryManager.php';
require_once PROJECT_ROOT . '/src/Dictionary/TextParser.php';

use App\Dictionary\DictionaryManager;
use App\Dictionary\TextParser;

$dictManager = new DictionaryManager($pdo);
$parser = new TextParser($pdo);

// Get dictionary info
if (!isset($_GET['id'])) {
    die('No dictionary specified');
}

$dictId = (int)$_GET['id'];
$dictionary = $dictManager->getDictionaryById($dictId);

if (!$dictionary) {
    die('Dictionary not found');
}

// Action dispatcher for AJAX requests
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');

    switch ($_REQUEST['action']) {
        case 'get_files':
            $sourceFiles = $dictManager->getSourceFilesByDictId($dictId);
            $importDir = PROJECT_ROOT . '/public/import/text/';
            $files = glob($importDir . '*.{txt,pdf}', GLOB_BRACE);
            $fileBasenames = array_map('basename', $files);
            
            echo json_encode([
                'success' => true,
                'files' => $fileBasenames,
                'source_files' => $sourceFiles
            ]);
            exit;

        case 'parse_files':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Invalid request method']);
                exit;
            }

            $selectedFiles = $_POST['files'] ?? [];
            $importDir = PROJECT_ROOT . '/public/import/text/';
            
            if (empty($selectedFiles)) {
                echo json_encode(['success' => false, 'results' => ['No files selected.']]);
                exit;
            }

            $results = [];
            
            foreach ($selectedFiles as $filename) {
                $fullPath = $importDir . $filename;
                
                if (!file_exists($fullPath)) {
                    $results[] = "[ERROR] File not found: {$filename}";
                    continue;
                }
                
                $fileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($fileType, ['txt', 'pdf'])) {
                    $results[] = "[SKIP] Invalid file type: {$filename}";
                    continue;
                }
                
                // Add to source files table
                $fileSize = filesize($fullPath);
                $fileId = $dictManager->addSourceFile(
                    $dictId,
                    $filename,
                    $filename,
                    '/import/text/' . $filename,
                    $fileType,
                    $fileSize
                );
                
                // Update status to processing
                $dictManager->updateFileParseStatus($fileId, 'processing');
                
                // Parse the file
                $parseResult = $parser->parseFile(
                    $fullPath,
                    $fileType,
                    $dictId,
                    $dictionary['language_code']
                );
                
                if ($parseResult['success']) {
                    $dictManager->updateFileParseStatus(
                        $fileId,
                        'completed',
                        $parseResult['lemmas_added']
                    );
                    $results[] = "[SUCCESS] {$filename}: Added {$parseResult['lemmas_added']} lemma mappings ({$parseResult['total_unique_lemmas']} unique)";
                } else {
                    $dictManager->updateFileParseStatus(
                        $fileId,
                        'failed',
                        0,
                        $parseResult['error']
                    );
                    $results[] = "[ERROR] {$filename}: {$parseResult['error']}";
                }
            }
            
            // Update dictionary lemma count
            $dictManager->updateDictionaryLemmaCount($dictId);
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            exit;
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json');
    
    $uploadDir = PROJECT_ROOT . '/public/import/text/';
    
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
            $results[] = ['success' => false, 'filename' => $fileName, 'error' => 'Upload error: ' . $fileError];
            continue;
        }
        
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['txt', 'pdf'])) {
            $results[] = ['success' => false, 'filename' => $fileName, 'error' => 'Invalid file type. Only TXT and PDF allowed.'];
            continue;
        }
        
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $uniqueName = $baseName . '_' . uniqid() . '.' . $extension;
        $destination = $uploadDir . $uniqueName;
        
        if (move_uploaded_file($fileTmpName, $destination)) {
            $results[] = [
                'success' => true,
                'filename' => $fileName,
                'saved_as' => $uniqueName,
                'size' => $fileSize,
                'path' => '/import/text/' . $uniqueName
            ];
        } else {
            $results[] = ['success' => false, 'filename' => $fileName, 'error' => 'Failed to move file'];
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parse Texts - <?php echo htmlspecialchars($dictionary['title']); ?></title>
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
        .upload-area-icon { font-size: 48px; margin-bottom: 16px; }
        .file-list { display: flex; flex-direction: column; gap: 12px; }
        .file-card { background-color: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); border-radius: 6px; padding: 16px; display: flex; align-items: center; gap: 16px; }
        .file-info { flex: 1; }
        .file-name { font-weight: 600; margin-bottom: 4px; }
        .file-size { font-size: 12px; color: var(--text-muted); }
        .result { margin: 5px 0; padding: 8px 10px; border-radius: 6px; font-family: monospace; font-size: 14px; }
        .result.success { background: rgba(16,185,129,0.06); color: var(--success); border: 1px solid rgba(16,185,129,0.08); }
        .result.error { background: rgba(239,68,68,0.04); color: var(--danger); border: 1px solid rgba(239,68,68,0.06); }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(var(--muted-border-rgb), 0.3); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .parse-files-list li { list-style: none; margin-bottom: 0.3em; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Parse Texts: <?php echo htmlspecialchars($dictionary['title']); ?></h1>
            <div class="flex-gap">
                <a href="dictionaries_admin.php" class="btn btn-secondary">&larr; Back to Admin</a>
                <a href="lemma_viewer.php?dict_id=<?php echo $dictId; ?>" class="btn btn-primary">View Lemmas</a>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px;">
            <h3 style="margin-top: 0;">Dictionary Info</h3>
            <p><strong>Language:</strong> <?php echo strtoupper($dictionary['language_code']); ?></p>
            <?php if ($dictionary['source_author']): ?>
                <p><strong>Source:</strong> <?php echo htmlspecialchars($dictionary['source_author']); ?>
                <?php if ($dictionary['source_title']): ?>
                    — <?php echo htmlspecialchars($dictionary['source_title']); ?>
                <?php endif; ?>
                </p>
            <?php endif; ?>
            <p><strong>Current Lemmas:</strong> <span id="currentLemmaCount"><?php echo number_format($dictionary['total_lemmas']); ?></span></p>
        </div>

        <div id="uploadSection" class="section">
            <h2 class="section-header">Step 1: Upload Text/PDF Files</h2>
            <div class="upload-area" id="uploadArea">
                <div class="upload-area-icon">📄</div>
                <div style="margin-bottom: 8px;">Click to select files or drag and drop</div>
                <div style="font-size: 14px; color: var(--text-muted);">Supports TXT and PDF files (max ~1000 pages)</div>
                <input type="file" id="fileInput" accept=".txt,.pdf" multiple style="display: none;">
            </div>

            <div id="fileListSection" class="hidden">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3>Selected Files</h3>
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

        <div id="parseSection" class="section hidden"></div>
        <div id="parseResultsSection" class="section hidden"></div>
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
        const uploadBtnText = document.getElementById('uploadBtnText');
        const uploadBtnSpinner = document.getElementById('uploadBtnSpinner');
        const parseSection = document.getElementById('parseSection');
        const parseResultsSection = document.getElementById('parseResultsSection');
        
        let selectedFiles = [];
        let uploadInProgress = false;

        uploadArea.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
        uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
        uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.classList.remove('drag-over'); handleFiles(e.dataTransfer.files); });
        
        function handleFiles(files) {
            const newFiles = Array.from(files).filter(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                return ext === 'txt' || ext === 'pdf';
            });
            if (newFiles.length < files.length) {
                showToast('Some files were not TXT/PDF and were ignored.', 'error');
            }
            selectedFiles = [...selectedFiles, ...newFiles];
            renderFileList();
            fileListSection.classList.remove('hidden');
        }
        
        function renderFileList() {
            fileList.innerHTML = selectedFiles.map((file, index) => `
                <div class="file-card">
                    <div class="file-info">
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${formatFileSize(file.size)}</div>
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="removeFile(${index})">&times;</button>
                </div>
            `).join('');
        }
        
        window.removeFile = function(index) {
            if (uploadInProgress) return;
            selectedFiles.splice(index, 1);
            renderFileList();
            if (selectedFiles.length === 0) fileListSection.classList.add('hidden');
        }
        
        clearBtn.addEventListener('click', () => {
            if (uploadInProgress) return;
            selectedFiles = [];
            fileList.innerHTML = '';
            fileInput.value = '';
            fileListSection.classList.add('hidden');
            parseSection.classList.add('hidden');
            parseResultsSection.classList.add('hidden');
            uploadBtn.disabled = false;
            uploadBtnText.textContent = "Upload All";
        });
        
        uploadBtn.addEventListener('click', async () => {
            if (selectedFiles.length === 0 || uploadInProgress) return;
            
            uploadInProgress = true;
            uploadBtn.disabled = true;
            clearBtn.disabled = true;
            uploadBtnText.style.display = 'none';
            uploadBtnSpinner.style.display = 'inline-block';
            
            const formData = new FormData();
            selectedFiles.forEach(file => formData.append('files[]', file));
            
            try {
                const response = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.status === 'success') {
                    showToast(`Successfully uploaded ${result.successful} file(s)`, 'success');
                    loadParseUI();
                }
            } catch (error) {
                showToast('Upload failed: ' + error.message, 'error');
            } finally {
                uploadInProgress = false;
                clearBtn.disabled = false;
                uploadBtnText.textContent = "Uploaded";
                uploadBtnText.style.display = 'inline-block';
                uploadBtnSpinner.style.display = 'none';
            }
        });

        async function loadParseUI() {
            try {
                const response = await fetch('?action=get_files&id=<?php echo $dictId; ?>');
                const data = await response.json();
                
                if (!data.success) throw new Error('Could not fetch files.');
                
                if (data.files.length === 0) {
                    parseSection.innerHTML = '<p>No files found in import directory.</p>';
                } else {
                    const fileCheckboxes = data.files.map(file => 
                        `<li><label style="display:inline-flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="parse_files[]" value="${file}" checked>
                            <span>${file}</span>
                        </label></li>`
                    ).join('');
                    
                    parseSection.innerHTML = `
                        <h2 class="section-header">Step 2: Parse & Extract Lemmas</h2>
                        <div class="card">
                            <form id="parseForm">
                                <label style="margin-bottom: 10px; display:block;">
                                    <input type="checkbox" onchange="toggleSelectAll(this, 'parse_files[]')" checked> 
                                    <strong>Select / Deselect All</strong>
                                </label>
                                <ul class="parse-files-list" style="max-height: 300px; overflow-y: auto; padding: 10px; border: 1px solid rgba(var(--muted-border-rgb),0.2); border-radius: 6px;">
                                    ${fileCheckboxes}
                                </ul>
                                <div style="margin-top:20px;">
                                    <button type="submit" class="btn btn-primary">
                                        <span class="btn-text">Start Parsing</span>
                                        <span class="spinner" style="display:none;"></span>
                                    </button>
                                    <small style="margin-left: 12px; color: var(--text-muted);">
                                        Note: Large files may take 1-2 minutes to parse
                                    </small>
                                </div>
                            </form>
                        </div>
                    `;
                }
                
                parseSection.classList.remove('hidden');
                
                const parseForm = document.getElementById('parseForm');
                if (parseForm) {
                    parseForm.addEventListener('submit', handleParseSubmit);
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        }

        async function handleParseSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const button = form.querySelector('button[type="submit"]');
            const btnText = button.querySelector('.btn-text');
            const btnSpinner = button.querySelector('.spinner');
            const formData = new FormData(form);
            const selectedFiles = formData.getAll('parse_files[]');
            
            if (selectedFiles.length === 0) {
                return showToast('Please select at least one file to parse.', 'error');
            }
            
            button.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline-block';

            try {
                const postData = new FormData();
                postData.append('action', 'parse_files');
                selectedFiles.forEach(file => postData.append('files[]', file));

                const response = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: postData
                });
                const result = await response.json();

                if (result.success && result.results) {
                    result.results.forEach(line => {
                        const isError = /(\[ERROR\]|\[SKIP\])/i.test(line);
                        showToast(line, isError ? 'error' : 'success');
                    });
                    
                    const resultsHtml = result.results.map(line => {
                        const isError = /(\[ERROR\]|\[SKIP\])/i.test(line);
                        const cls = isError ? 'error' : 'success';
                        return `<div class="result ${cls}">${line}</div>`;
                    }).join('');

                    parseResultsSection.innerHTML = `
                        <h3 class="section-header">Parse Results</h3>
                        <div class="card">${resultsHtml}</div>
                    `;
                    parseResultsSection.classList.remove('hidden');
                    
                    loadParseUI();
                    
                    // Refresh lemma count
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    throw new Error(result.error || 'Parse failed');
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

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
