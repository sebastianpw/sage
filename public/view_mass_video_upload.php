<?php
// public/view_mass_video_upload.php
/**
 * Mass Video Uploader & Importer
 * Assigns videos to 'animatics' entities.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// CONFIGURATION
$importRelPath = '/import/videos/';
$importAbsPath = PROJECT_ROOT . '/public' . $importRelPath;

if (!is_dir($importAbsPath)) {
    mkdir($importAbsPath, 0777, true);
}

// AJAX HANDLER
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $spw = \App\Core\SpwBase::getInstance();
    $pdo = $spw->getPDO();

    switch ($_REQUEST['action']) {
        case 'get_import_data':
            $files = glob($importAbsPath . '*.{mp4,webm,ogg}', GLOB_BRACE);
            $fileBasenames = array_map('basename', $files ?: []);
            
            // Only 'animatics' for now based on prompt
            $entities = [
                'animatics' => 'Animatics (Result Mode)'
            ];
            
            echo json_encode([
                'success' => true,
                'files' => array_values($fileBasenames),
                'entities' => $entities
            ]);
            exit;

        case 'import_videos':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Invalid method']);
                exit;
            }
            
            require_once __DIR__ . '/VideoBatchImporter.php';

            $selectedFiles = $_POST['files'] ?? [];
            $targetEntity  = $_POST['target_entity'] ?? 'animatics';
            
            if (empty($selectedFiles)) {
                echo json_encode(['success' => false, 'results' => ['No files selected.']]);
                exit;
            }

            $fullPathFiles = array_map(fn($f) => $importAbsPath . $f, $selectedFiles);

            $importer = new VideoBatchImporter($pdo, $targetEntity);
            $results = $importer->runImport($fullPathFiles);

            echo json_encode(['success' => true, 'results' => $results]);
            exit;
    }
}

// UPLOAD HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video_files'])) {
    header('Content-Type: application/json');
    
    $results = [];
    $files = $_FILES['video_files'];
    
    // Normalize file array structure whether single or multiple
    $count = is_array($files['name']) ? count($files['name']) : 1;
    $fileArray = [];
    
    if (is_array($files['name'])) {
        for($i=0; $i<$count; $i++) {
            $fileArray[] = [
                'name' => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i]
            ];
        }
    } else {
        $fileArray[] = $files;
    }
    
    foreach ($fileArray as $file) {
        $name = $file['name'];
        $tmp = $file['tmp_name'];
        $err = $file['error'];
        
        if ($err !== UPLOAD_ERR_OK) {
            $msg = ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) ? 'File too large' : 'Error code: ' . $err;
            $results[] = ['success' => false, 'filename' => $name, 'error' => $msg];
            continue;
        }
        
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'webm', 'ogg'])) {
            $results[] = ['success' => false, 'filename' => $name, 'error' => 'Invalid format'];
            continue;
        }
        
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
        if(file_exists($importAbsPath . $safeName)) {
            $safeName = pathinfo($safeName, PATHINFO_FILENAME) . '_' . time() . '.' . $ext;
        }
        
        if (move_uploaded_file($tmp, $importAbsPath . $safeName)) {
            $results[] = ['success' => true, 'filename' => $name, 'saved_as' => $safeName];
        } else {
            $results[] = ['success' => false, 'filename' => $name, 'error' => 'Move failed'];
        }
    }
    
    echo json_encode(['status' => 'success', 'results' => $results]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Video Import</title>
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    <style>
        .upload-area { border: 2px dashed var(--border); border-radius: 8px; padding: 40px; text-align: center; background-color: var(--card); transition: all 0.3s ease; cursor: pointer; margin-bottom: 24px; position: relative; }
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
        
        .import-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-top: 20px; }
        .form-select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
        .result-log { font-family: monospace; font-size: 0.85rem; padding: 10px; border-radius: 4px; margin-top: 5px; }
        .res-success { background: rgba(35, 134, 54, 0.1); color: var(--green); border: 1px solid rgba(35, 134, 54, 0.2); }
        .res-error { background: rgba(218, 54, 51, 0.1); color: var(--red); border: 1px solid rgba(218, 54, 51, 0.2); }
    </style>
</head>
<body>
<div class="container" style="max-width:1100px; margin:0 auto; padding:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">🎬 Mass Video Importer</h2>
        <div>
            <a href="view_video_admin.php" class="btn btn-sm btn-secondary">Video Admin</a>
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
            <div class="upload-text">Click or Drag & Drop MP4/WebM files here</div>
        </div>
        <input type="file" id="fileInput" multiple accept="video/*" style="display:none;">
    </div>

    <div id="importSection" class="import-card" style="display:none;">
        <h3 style="margin-top:0;">Import Configuration</h3>
        <p class="small-muted">Found <span id="fileCountBadge" class="badge">0</span> files.</p>
        
        <form id="importForm">
            <div style="margin-bottom:15px;">
                <label>Target Entity</label>
                <select name="target_entity" id="targetEntitySelect" class="form-select"></select>
            </div>
            <div style="margin-bottom:10px;">
                <label><input type="checkbox" id="selectAllBox" checked> Select All</label>
            </div>
            <div id="importFileList" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border); padding: 10px; border-radius: 4px; background: var(--bg); margin-bottom: 20px;"></div>
            <button type="submit" class="btn btn-primary" id="startImportBtn">Start Import</button>
        </form>
    </div>

    <div id="resultsSection" style="margin-top:20px;"></div>
</div>

<?php echo $eruda ?? ''; ?>

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
        if(files.length === 0) return;
        
        // Show progress UI
        progOverlay.css('display', 'flex');
        const total = files.length;
        let successes = 0;
        let errors = 0;

        // SEQUENTIAL UPLOAD LOOP
        // Uploads one by one to avoid post_max_size server limits
        for (let i = 0; i < total; i++) {
            const file = files[i];
            const currentNum = i + 1;
            
            // Update UI
            progText.text(`Uploading ${currentNum}/${total}: ${file.name}`);
            const pct = ((i) / total) * 100;
            progFill.css('width', pct + '%');
            
            // Prepare Data
            const fd = new FormData();
            fd.append('video_files[]', file); // Use array syntax to match PHP expectation

            try {
                await new Promise((resolve) => {
                    $.ajax({
                        url: window.location.href,
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

        // Finish
        progFill.css('width', '100%');
        setTimeout(() => {
            progOverlay.hide();
            Toast.show(`Upload complete: ${successes} saved, ${errors} failed.`, errors > 0 ? 'warning' : 'success');
            fileInput.val(''); // reset
            reloadImportData();
        }, 500);
    }

    function reloadImportData() {
        $.post(window.location.href, {action: 'get_import_data'}, function(res){
            if(res.success) {
                if(res.files.length > 0) {
                    $('#importSection').slideDown();
                    $('#fileCountBadge').text(res.files.length);
                    const entSelect = $('#targetEntitySelect');
                    if(entSelect.children().length === 0) {
                        $.each(res.entities, function(key, label) { entSelect.append(new Option(label, key)); });
                    }
                    const list = $('#importFileList');
                    list.empty();
                    res.files.forEach(f => list.append(`<div><label><input type="checkbox" name="files[]" value="${f}" checked> ${f}</label></div>`));
                } else $('#importSection').slideUp();
            }
        }, 'json');
    }
    reloadImportData();

    $('#selectAllBox').change(function() { $('input[name="files[]"]').prop('checked', $(this).is(':checked')); });

    $('#importForm').submit(function(e) {
        e.preventDefault();
        const btn = $('#startImportBtn');
        const txt = btn.text();
        btn.prop('disabled', true).text('Importing...');
        
        $.post(window.location.href, $(this).serialize() + '&action=import_videos', function(res) {
            btn.prop('disabled', false).text(txt);
            if(res.success) {
                reloadImportData();
                let html = '<h3>Results</h3>';
                res.results.forEach(msg => {
                    const cls = msg.toLowerCase().includes('failed') || msg.toLowerCase().includes('error') ? 'res-error' : 'res-success';
                    html += `<div class="result-log ${cls}">${msg}</div>`;
                });
                $('#resultsSection').prepend(html);
                Toast.show('Finished', 'info');
            } else Toast.show(res.results ? res.results[0] : 'Error', 'error');
        }, 'json');
    });
});
</script>
</body>
</html>