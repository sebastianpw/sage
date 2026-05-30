<?php
/**
 * Mass Audio Uploader & Importer
 * Supports wav2wav=1 (Source) and wav2wav=0 (Result) modes.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// --- CONFIGURATION ---
$importRelPath = '/import/audios/';
$importAbsPath = PROJECT_ROOT . '/public' . $importRelPath;

// Ensure import directory exists
if (!is_dir($importAbsPath)) {
    mkdir($importAbsPath, 0777, true);
}

// --- AJAX HANDLER ---
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $spw = \App\Core\SpwBase::getInstance();
    $pdo = $spw->getPDO();

    switch ($_REQUEST['action']) {
        // 1. Get Import Data (Files & Entity Types)
        case 'get_import_data':
            // Scan for audio files
            $files = glob($importAbsPath . '*.{wav,mp3,ogg,m4a}', GLOB_BRACE);
            $fileBasenames = array_map('basename', $files ?: []);
            
            // Define available Audio Entities
            $audioEntities = [
                'audio_dialogue_lines' => 'Dialogue Lines',
                'audio_ambiences'      => 'Ambiences',
                'audio_cues'           => 'Cues',
                'audio_foleys'         => 'Foleys',
                'audio_fxsounds'       => 'FX Sounds',
                'audio_themes'         => 'Themes'
            ];
            
            echo json_encode([
                'success' => true,
                'files' => array_values($fileBasenames),
                'entities' => $audioEntities
            ]);
            exit;

        // 2. Execute Import
        case 'import_audios':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Invalid method']);
                exit;
            }
            
            require_once __DIR__ . '/AudioBatchImporter.php';

            $selectedFiles = $_POST['files'] ?? [];
            $targetEntity  = $_POST['target_entity'] ?? '';
            $mode          = (int)($_POST['import_mode'] ?? 1); // 1=Source, 0=Result

            if (empty($selectedFiles) || empty($targetEntity)) {
                echo json_encode(['success' => false, 'results' => ['No files or entity selected.']]);
                exit;
            }

            // Prep full paths
            $fullPathFiles = array_map(fn($f) => $importAbsPath . $f, $selectedFiles);

            $importer = new AudioBatchImporter($pdo, $targetEntity, $mode);
            $results = $importer->runImport($fullPathFiles);

            echo json_encode(['success' => true, 'results' => $results]);
            exit;
    }
}

// --- UPLOAD HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio_files'])) {
    header('Content-Type: application/json');
    
    $results = [];
    $files = $_FILES['audio_files'];
    $count = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $count; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        
        if ($err !== UPLOAD_ERR_OK) {
            $results[] = ['success' => false, 'filename' => $name, 'error' => 'Error code: ' . $err];
            continue;
        }
        
        // Validate Type
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['wav', 'mp3', 'ogg', 'm4a'])) {
            $results[] = ['success' => false, 'filename' => $name, 'error' => 'Invalid audio format'];
            continue;
        }
        
        // Move File
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name); // Basic sanitization
        // Check for duplicates in import folder
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
    <title>Mass Audio Import</title>
    
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        } catch (e) {}
    })();
    </script>
    
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>

    <style>
        .upload-area { border: 2px dashed var(--border); border-radius: 8px; padding: 40px; text-align: center; background-color: var(--card); transition: all 0.3s ease; cursor: pointer; margin-bottom: 24px; }
        .upload-area:hover, .upload-area.drag-over { border-color: var(--accent); background-color: rgba(var(--muted-border-rgb), 0.05); }
        .upload-icon { font-size: 48px; color: var(--text-muted); margin-bottom: 16px; }
        .upload-text { font-size: 1.1rem; color: var(--text); margin-bottom: 8px; }
        .upload-hint { font-size: 0.9rem; color: var(--text-muted); }
        
        .file-list-container { margin-bottom: 20px; }
        .file-item { display: flex; align-items: center; justify-content: space-between; padding: 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); margin-bottom: 8px; }
        
        /* Import UI Styles */
        .import-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-top: 20px; }
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-col { flex: 1; min-width: 200px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        .form-select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
        
        .result-log { font-family: monospace; font-size: 0.85rem; padding: 10px; border-radius: 4px; margin-top: 5px; }
        .res-success { background: rgba(35, 134, 54, 0.1); color: var(--green); border: 1px solid rgba(35, 134, 54, 0.2); }
        .res-error { background: rgba(218, 54, 51, 0.1); color: var(--red); border: 1px solid rgba(218, 54, 51, 0.2); }
    </style>
</head>
<body>

<div class="container">
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">🎧 Mass Audio Importer</h2>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Dashboard</a>
    </div>

    <!-- 1. UPLOAD SECTION -->
    <div id="uploadSection">
        <div class="upload-area" id="dropZone">
            <div class="upload-icon">📤</div>
            <div class="upload-text">Click or Drag & Drop audio files here</div>
            <div class="upload-hint">Supported: WAV, MP3, OGG</div>
        </div>
        <!-- FIX: Input moved outside dropZone to prevent recursion -->
        <input type="file" id="fileInput" multiple accept="audio/*" style="display:none;">
        
        <!-- Live Upload Progress List -->
        <div id="uploadList" class="file-list-container"></div>
    </div>

    <!-- 2. IMPORT SECTION (Loads dynamically) -->
    <div id="importSection" class="import-card" style="display:none;">
        <h3 style="margin-top:0;">Import Configuration</h3>
        <p class="small-muted">Found <span id="fileCountBadge" class="badge">0</span> files ready for import.</p>
        
        <form id="importForm">
            <div class="form-row">
                <div class="form-col">
                    <label class="form-label">Target Entity</label>
                    <select name="target_entity" id="targetEntitySelect" class="form-select">
                        <!-- Populated by JS -->
                    </select>
                </div>
                <div class="form-col">
                    <label class="form-label">Import Mode</label>
                    <select name="import_mode" class="form-select">
                        <option value="1">📝 Source (Wav2Wav)</option>
                        <option value="0">🆕 Result (New Entry)</option>
                    </select>
                    <div style="font-size:0.8rem; color:var(--text-muted); margin-top:5px;">
                        <strong>Source:</strong> Creates new entity & links audio as source.<br>
                        <strong>Result:</strong> Creates new entity, map run & result link.
                    </div>
                </div>
            </div>

            <!-- File Selection List -->
            <label class="form-label">Select Files to Process:</label>
            <div style="margin-bottom:10px;">
                <label><input type="checkbox" id="selectAllBox" checked> Select All</label>
            </div>
            <div id="importFileList" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border); padding: 10px; border-radius: 4px; background: var(--bg); margin-bottom: 20px;">
                <!-- Checkboxes populated by JS -->
            </div>

            <button type="submit" class="btn btn-primary" id="startImportBtn">Start Import</button>
        </form>
    </div>

    <!-- 3. RESULTS SECTION -->
    <div id="resultsSection" style="margin-top:20px;"></div>

</div>

<!-- Eruda -->
<?php echo $eruda; ?>

<script>
$(document).ready(function() {
    const dropZone = $('#dropZone');
    const fileInput = $('#fileInput');
    
    // --- File Handling ---
    dropZone.on('click', function(e) {
        e.stopPropagation();
        fileInput.trigger('click');
    });
    
    dropZone.on('dragover', (e) => {
        e.preventDefault();
        dropZone.addClass('drag-over');
    });
    
    dropZone.on('dragleave drop', (e) => {
        e.preventDefault();
        dropZone.removeClass('drag-over');
    });
    
    dropZone.on('drop', (e) => {
        handleUpload(e.originalEvent.dataTransfer.files);
    });
    
    fileInput.on('change', (e) => {
        handleUpload(e.target.files);
    });

    function handleUpload(files) {
        if(files.length === 0) return;
        
        const formData = new FormData();
        Array.from(files).forEach(file => {
            formData.append('audio_files[]', file);
        });

        // Show loading state
        dropZone.css('opacity', '0.5').css('pointer-events', 'none');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                dropZone.css('opacity', '1').css('pointer-events', 'auto');
                if(res.status === 'success') {
                    Toast.show(`Uploaded ${res.results.length} files.`, 'success');
                    reloadImportData(); // Refresh Step 2
                } else {
                    Toast.show('Upload failed', 'error');
                }
            },
            error: function() {
                dropZone.css('opacity', '1').css('pointer-events', 'auto');
                Toast.show('Network error', 'error');
            }
        });
    }

    // --- Import Logic ---
    
    function reloadImportData() {
        $.post(window.location.href, {action: 'get_import_data'}, function(res){
            if(res.success) {
                if(res.files.length > 0) {
                    $('#importSection').slideDown();
                    $('#fileCountBadge').text(res.files.length);
                    
                    // Fill Entity Select
                    const entSelect = $('#targetEntitySelect');
                    if(entSelect.children().length === 0) {
                        $.each(res.entities, function(key, label) {
                            entSelect.append(new Option(label, key));
                        });
                    }
                    
                    // Fill File List
                    const list = $('#importFileList');
                    list.empty();
                    res.files.forEach(f => {
                        list.append(`<div><label><input type="checkbox" name="files[]" value="${f}" checked> ${f}</label></div>`);
                    });
                } else {
                    $('#importSection').slideUp();
                }
            }
        }, 'json');
    }

    // Initial Load
    reloadImportData();

    $('#selectAllBox').change(function() {
        $('input[name="files[]"]').prop('checked', $(this).is(':checked'));
    });

    $('#importForm').submit(function(e) {
        e.preventDefault();
        
        const btn = $('#startImportBtn');
        const originalText = btn.text();
        btn.prop('disabled', true).text('Importing...');
        
        const formData = $(this).serialize() + '&action=import_audios';
        
        $.post(window.location.href, formData, function(res) {
            btn.prop('disabled', false).text(originalText);
            
            if(res.success) {
                // Clear selected checkboxes or refresh list
                reloadImportData();
                
                // Show results
                let html = '<h3>Import Results</h3>';
                res.results.forEach(msg => {
                    const cls = msg.toLowerCase().includes('failed') || msg.toLowerCase().includes('error') ? 'res-error' : 'res-success';
                    html += `<div class="result-log ${cls}">${msg}</div>`;
                });
                $('#resultsSection').prepend(html); // Add new results to top
                
                Toast.show('Import Process Finished', 'info');
            } else {
                Toast.show(res.results ? res.results[0] : 'Error', 'error');
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false).text(originalText);
            Toast.show('Server Error', 'error');
        });
    });
});
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
