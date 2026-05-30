<?php
// filepath: public/view_mass_upload.php

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
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    <style>
        .upload-area { border: 2px dashed rgba(var(--muted-border-rgb), 0.5); border-radius: 8px; padding: 40px; text-align: center; background-color: var(--card); transition: all 0.3s ease; cursor: pointer; margin-bottom: 24px; position: relative; }
        .upload-area:hover, .upload-area.drag-over { border-color: var(--accent); background-color: rgba(var(--muted-border-rgb), 0.05); }
        .upload-icon { font-size: 48px; color: var(--text-muted); margin-bottom: 16px; }
        .upload-text { font-size: 1.1rem; color: var(--text); margin-bottom: 8px; }
        
        /* Progress Overlay */
        .upload-progress-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.8); border-radius: 8px;
            display: none; flex-direction: column; align-items: center; justify-content: center; z-index: 10;
        }
        .progress-text { color: white; margin-bottom: 10px; font-weight: bold; }
        .progress-bar-wrap { width: 60%; height: 8px; background: #444; border-radius: 4px; overflow: hidden; }
        .progress-bar-fill { width: 0%; height: 100%; background: var(--accent); transition: width 0.2s; }
        
        .import-card { background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.3); border-radius: 8px; padding: 20px; margin-top: 20px; }
        .form-select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid rgba(var(--muted-border-rgb), 0.3); background: var(--bg); color: var(--text); }
        
        .result-log { font-family: monospace; font-size: 0.85rem; padding: 10px; border-radius: 4px; margin-top: 5px; }
        .res-success { background: rgba(16,185,129,0.06); color: var(--success, #10b981); border: 1px solid rgba(16,185,129,0.08); }
        .res-error { background: rgba(239,68,68,0.04); color: var(--danger, #ef4444); border: 1px solid rgba(239,68,68,0.06); }
        
        .badge { display: inline-block; padding: 0.25em 0.4em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; }
        .badge-blue { background-color: var(--blue, #3b82f6); color: #fff; }
    </style>
</head>
<body>
<div class="container" style="max-width:1100px; margin:0 auto; padding:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">🖼️ Mass Image Importer</h2>
        <div>
            <span class="badge badge-blue" style="margin-right:10px;">frames_2_spawns</span>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Dashboard</a>
        </div>
    </div>

    <div id="uploadSection">
        <div class="upload-area" id="dropZone">
            <div class="upload-progress-overlay" id="progOverlay">
                <div class="progress-text" id="progText">Uploading 1/5...</div>
                <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progFill"></div></div>
            </div>
            <div class="upload-icon">📤</div>
            <div class="upload-text">Click or Drag & Drop Images (JPEG, PNG, GIF, WebP) here</div>
        </div>
        <input type="file" id="fileInput" multiple accept="image/*" style="display:none;">
    </div>

    <div id="importSection" class="import-card" style="display:none;">
        <h3 style="margin-top:0;">Import Configuration</h3>
        <p class="small-muted">Found <span id="fileCountBadge" class="badge" style="background:#555;">0</span> files.</p>
        
        <form id="importForm">
            <div style="margin-bottom:15px;">
                <label>Import as (Spawn Type)</label>
                <select name="spawn_type_id" id="spawnTypeSelect" class="form-select"></select>
            </div>
            <div style="margin-bottom:10px;">
                <label><input type="checkbox" id="selectAllBox" checked> Select All</label>
            </div>
            <div id="importFileList" style="max-height: 250px; overflow-y: auto; border: 1px solid rgba(var(--muted-border-rgb), 0.3); padding: 10px; border-radius: 4px; background: var(--bg); margin-bottom: 20px;"></div>
            <button type="submit" class="btn btn-primary" id="startImportBtn">Start Import</button>
        </form>
    </div>

    <div id="resultsSection" style="margin-top:20px;"></div>
</div>

<script>
$(document).ready(function() {
    const dropZone = $('#dropZone');
    const fileInput = $('#fileInput');
    const progOverlay = $('#progOverlay');
    const progText = $('#progText');
    const progFill = $('#progFill');
    
    dropZone.on('click', () => fileInput.trigger('click'));
    dropZone.on('dragover', (e) => { e.preventDefault(); dropZone.addClass('drag-over'); });
    dropZone.on('dragleave drop', (e) => { e.preventDefault(); dropZone.removeClass('drag-over'); });
    dropZone.on('drop', (e) => handleFiles(e.originalEvent.dataTransfer.files));
    fileInput.on('change', (e) => handleFiles(e.target.files));

    async function handleFiles(files) {
        // filter images
        const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
        if (imageFiles.length < files.length) {
            Toast.show(`Some files were not images and were ignored.`, 'error');
        }
        if(imageFiles.length === 0) return;
        
        // Show progress UI
        progOverlay.css('display', 'flex');
        const total = imageFiles.length;
        let successes = 0;
        let errors = 0;

        // SEQUENTIAL UPLOAD LOOP (prevents max_post_size limitations)
        for (let i = 0; i < total; i++) {
            const file = imageFiles[i];
            const currentNum = i + 1;
            
            // Update UI
            progText.text(`Uploading ${currentNum}/${total}: ${file.name}`);
            const pct = ((i) / total) * 100;
            progFill.css('width', pct + '%');
            
            // Prepare Data for current file iteration
            const fd = new FormData();
            fd.append('images', file); // Mapped directly to the backend expectating $_FILES['images']

            try {
                await new Promise((resolve) => {
                    $.ajax({
                        url: window.location.pathname,
                        type: 'POST',
                        data: fd,
                        contentType: false,
                        processData: false,
                        success: function(res) {
                            if(res.status === 'success' && res.results[0].success) {
                                successes++;
                            } else {
                                errors++;
                                const msg = res.results ? res.results[0].error : 'Unknown error';
                                Toast.show(`Failed ${file.name}: ${msg}`, 'error');
                            }
                            resolve();
                        },
                        error: function() {
                            errors++;
                            Toast.show(`Network error: ${file.name}`, 'error');
                            resolve();
                        }
                    });
                });
            } catch (e) {
                console.error(e);
            }
        }

        // Finish Cycle
        progFill.css('width', '100%');
        setTimeout(() => {
            progOverlay.hide();
            Toast.show(`Upload complete: ${successes} saved, ${errors} failed.`, errors > 0 ? 'warning' : 'success');
            fileInput.val(''); // reset context
            reloadImportData();
        }, 500);
    }

    function reloadImportData() {
        $.post(window.location.pathname, {action: 'get_import_data'}, function(res){
            if(res.success) {
                if (res.spawnTypes && res.spawnTypes.length === 0) {
                    $('#importSection').html(`<div class="import-disabled" style="padding:15px; border-radius:6px; background:var(--card-weak); border:1px solid rgba(var(--muted-border-rgb),0.1);"><strong>⚠️ Batch import is not enabled for any spawn type.</strong><p style="margin:8px 0 0 0">Please enable batch import in the database for at least one spawn type.</p></div>`).slideDown();
                    return;
                }

                if(res.files.length > 0) {
                    $('#importSection').slideDown();
                    $('#fileCountBadge').text(res.files.length);
                    const typeSelect = $('#spawnTypeSelect');
                    
                    if(typeSelect.children().length === 0 && res.spawnTypes) {
                        res.spawnTypes.forEach(function(type) { 
                            typeSelect.append(new Option(type.label, type.id)); 
                        });
                    }
                    const list = $('#importFileList');
                    list.empty();
                    res.files.forEach(f => list.append(`<div><label style="cursor:pointer;"><input type="checkbox" name="files[]" value="${f}" checked> <span style="margin-left:4px;">${f}</span></label></div>`));
                } else {
                    $('#importSection').slideUp();
                }
            }
        }, 'json');
    }
    
    // Initializer routine logic
    reloadImportData();

    $('#selectAllBox').change(function() { 
        $('input[name="files[]"]').prop('checked', $(this).is(':checked')); 
    });

    $('#importForm').submit(function(e) {
        e.preventDefault();
        const btn = $('#startImportBtn');
        const txt = btn.text();
        
        const formData = $(this).serializeArray();
        let fileCount = formData.filter(item => item.name === 'files[]').length;
        if (fileCount === 0) {
            Toast.show('Please select at least one file to import.', 'error');
            return;
        }

        btn.prop('disabled', true).text('Importing...');
        
        $.post(window.location.pathname, $(this).serialize() + '&action=import_spawns', function(res) {
            btn.prop('disabled', false).text(txt);
            if(res.success) {
                reloadImportData();
                let html = '<div class="card" style="margin-bottom:20px;"><h3 style="margin-top:0;">Last Import Results</h3>';
                res.results.forEach(msg => {
                    const isError = /([\[]SKIP[\]]|failed|error)/i.test(msg);
                    const cls = isError ? 'res-error' : 'res-success';
                    html += `<div class="result-log ${cls}">${msg}</div>`;
                });
                html += '</div>';
                $('#resultsSection').prepend(html);
                Toast.show('Finished', 'info');
            } else {
                Toast.show(res.results ? res.results[0] : (res.error || 'Error'), 'error');
            }
        }, 'json');
    });
});
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

</body>
</html>