<?php
// import_spawns.php
//
// Web GET example:
// http://localhost:8080/import_spawns.php
// http://localhost:8080/import_spawns.php?spawn_type=reference
//
// CLI example:
// php import_spawns.php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/SpawnBatchImporter.php';
require __DIR__ . '/SpawnGalleryManager.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Initialize gallery manager to get spawn types
$galleryManager = new SpawnGalleryManager($mysqli);

// Get selected spawn type from URL or default
$selectedTypeCode = $_GET['spawn_type'] ?? 'default';
$galleryManager->setActiveType($selectedTypeCode);
$activeType = $galleryManager->getActiveType();

// Check if batch import is enabled for this type
$canImport = $activeType && $activeType['batch_import_enabled'];

// Initialize importer with spawn type ID
$spawnTypeId = $activeType ? $activeType['id'] : null;
$importer = new SpawnBatchImporter($mysqli, $spawnTypeId);
$importDir = __DIR__ . '/import/frames_2_spawns/';

// --- fetch files ---
$files = glob($importDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

// --- handle form submission ---
$importResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['files']) && $canImport) {
    $selectedFiles = $_POST['files'];
    
    // Update spawn type if changed in form
    if (isset($_POST['spawn_type_id'])) {
        $importer->setSpawnTypeId((int)$_POST['spawn_type_id']);
    }
    
    $importResults = $importer->runImport($selectedFiles);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spawn Importer</title>
<link rel="stylesheet" href="css/form.css">
<style>
    .file-list { list-style: none; padding: 0; }
    .file-list li { margin-bottom: 0.3em; }
    .result.success { color: #1a7f37; font-weight: 600; }
    .result.error { color: #b42318; font-weight: 600; }
    
    .spawn-type-tabs {
        margin: 20px 0;
        border-bottom: 2px solid #ddd;
    }
    
    .spawn-tab {
        display: inline-block;
        padding: 10px 20px;
        margin-right: 5px;
        text-decoration: none;
        border: 1px solid #ddd;
        border-bottom: none;
        border-radius: 5px 5px 0 0;
        background: #f8f9fa;
        color: #333;
    }
    
    .spawn-tab.active {
        background: #007bff;
        color: white;
        border-bottom: 2px solid #007bff;
    }
    
    .spawn-type-selector {
        margin: 15px 0;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
    }
    
    .spawn-type-selector label {
        font-weight: 600;
        margin-right: 10px;
    }
    
    .spawn-type-selector select {
        padding: 5px 10px;
        font-size: 14px;
    }
    
    .import-disabled {
        padding: 15px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        margin: 20px 0;
    }
</style>
<script>
function toggleSelectAll(source) {
    checkboxes = document.getElementsByName('files[]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>
</head>
<body>

<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">
    <div style="position: absolute;">
        <a href="/dashboard.php" 
           title="Dashboard" 
           style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
            &#x1F5C3;
        </a>

        <h2 style="margin: 0; padding: 0 0 20px 0; position: absolute; top: 10px; left: 50px;">
            Batch Importer
        </h2>          
    </div>
</div>

<div style="margin: 0; padding: 0;">
    <br />
</div>

<div style="position: absolute; top: 50px; left: 20px; right: 20px;">

    <?php 
    // Render spawn type tabs
    $spawnTypes = $galleryManager->getSpawnTypes();
    if (count($spawnTypes) > 1): 
    ?>
        <div class="spawn-type-tabs">
            <?php foreach ($spawnTypes as $code => $type): ?>
                <?php 
                $active = ($code === $selectedTypeCode) ? 'active' : '';
                $disabled = !$type['batch_import_enabled'] ? ' (import disabled)' : '';
                ?>
                <a href="?spawn_type=<?= htmlspecialchars($code) ?>" 
                   class="spawn-tab <?= $active ?>">
                    <?= htmlspecialchars($type['label']) ?><?= $disabled ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <h2>
            Import <?= $activeType ? htmlspecialchars($activeType['label']) : 'Spawns' ?>
        </h2>

        <?php if (!$canImport): ?>
            <div class="import-disabled">
                <strong>⚠️ Batch import is disabled for this spawn type.</strong>
                <p>Please select a different spawn type or enable batch import in the database.</p>
            </div>
        <?php endif; ?>

        <p>
            Files available for import from 
            <div style="width: 300px; background: #eee; overflow: auto; padding: 5px;">
                <code><?= htmlspecialchars($importDir) ?></code>
            </div>
        </p>

        <?php if (empty($files)): ?>
            <p style="color: #666; font-style: italic;">No files found in the import folder.</p>
        <?php else: ?>
            
            <?php 
            // Show spawn type selector if multiple types support batch import
            $importableTypes = array_filter($spawnTypes, function($t) {
                return $t['batch_import_enabled'];
            });
            
            if (count($importableTypes) > 1 && $canImport): 
            ?>
                <div class="spawn-type-selector">
                    <label for="spawn_type_id">Import as:</label>
                    <select name="spawn_type_id" id="spawn_type_id">
                        <?php foreach ($importableTypes as $type): ?>
                            <option value="<?= (int)$type['id'] ?>" 
                                    <?= ($activeType && $type['id'] === $activeType['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="spawn_type_id" value="<?= (int)$spawnTypeId ?>">
            <?php endif; ?>

            <?php if ($canImport): ?>
                <label style="margin-bottom: 10px; display: block;">
                    <input type="checkbox" onclick="toggleSelectAll(this)"> 
                    <strong>Select / Deselect All</strong>
                </label>
                
                <ul class="file-list">
                    <?php foreach ($files as $file): ?>
                        <li>
                            <label>
                                <input type="checkbox" name="files[]" value="<?= htmlspecialchars($file) ?>">
                                <?= htmlspecialchars(basename($file)) ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <button type="submit" style="margin-top: 15px; padding: 10px 20px; font-size: 16px;">
                    Start Import
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </form>

    <?php if (!empty($importResults)): ?>
        <div class="result" style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <h3>Import Results</h3>
            <?php foreach ($importResults as $line): ?>
                <?php
                $class = stripos($line, '[SKIP]') !== false || 
                         stripos($line, 'Failed') !== false || 
                         stripos($line, 'error') !== false 
                         ? 'error' : 'success';
                ?>
                <div class="result <?= $class ?>" style="margin: 5px 0;">
                    <?= htmlspecialchars($line) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
