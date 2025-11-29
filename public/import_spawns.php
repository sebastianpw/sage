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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

// --- handle form submission (PRG) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['files']) && $canImport) {
    $selectedFiles = $_POST['files'];

    // Update spawn type if changed in form
    if (isset($_POST['spawn_type_id'])) {
        $importer->setSpawnTypeId((int)$_POST['spawn_type_id']);
    }

    // Run import and capture results (array of strings)
    $importResults = $importer->runImport($selectedFiles);

    // Save flash into session and redirect to avoid stale file list / double submit
    $_SESSION['import_spawns_flash'] = [
        'spawn_type' => $selectedTypeCode,
        'results' => $importResults
    ];

    // Redirect back to the same page (PRG)
    $redirectUrl = 'import_spawns.php';
    if ($selectedTypeCode) {
        $redirectUrl .= '?spawn_type=' . urlencode($selectedTypeCode);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// --- GET: possibly show flash messages ---
$flash = $_SESSION['import_spawns_flash'] ?? null;
if ($flash) {
    // we'll pass this to the UI and then clear it
    unset($_SESSION['import_spawns_flash']);
}

// --- fetch files (fresh on each GET) ---
$files = glob($importDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spawn Importer</title>

<link rel="stylesheet" href="/css/base.css">
<!-- optional: your legacy form.css can be removed if base.css covers it -->
<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') document.documentElement.setAttribute('data-theme','dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme','light');
    } catch(e){}
  })();
</script>

<style>
    /* small helpers to keep previous look but using variables */
    .file-list { list-style: none; padding: 0; margin: 0; }
    .file-list li { margin-bottom: 0.3em; }
    .import-disabled { padding: 15px; border-radius: 6px; background: var(--card-weak); border: 1px solid rgba(0,0,0,0.04); }
    .spawn-type-tabs { margin: 20px 0; border-bottom: 2px solid rgba(0,0,0,0.06); }
    .spawn-tab { display: inline-block; padding: 10px 20px; margin-right: 5px; text-decoration: none; border: 1px solid rgba(0,0,0,0.06); border-bottom: none; border-radius: 6px 6px 0 0; background: var(--card-weak); color: var(--accent); }
    .spawn-tab.active { background: var(--accent-2); color: var(--text); border-bottom: 2px solid var(--accent-2); }
    .spawn-type-selector { margin: 15px 0; padding: 10px; background: var(--card-weak); border-radius: 6px; }
    .spawn-type-selector label { font-weight: 600; margin-right: 10px; color: var(--muted); }
    .result { margin: 5px 0; padding: 8px 10px; border-radius: 6px; }
    .result.success { background: rgba(16,185,129,0.06); color: var(--success); border: 1px solid rgba(16,185,129,0.08); }
    .result.error { background: rgba(239,68,68,0.04); color: var(--danger); border: 1px solid rgba(239,68,68,0.06); }
</style>

<?php echo $spw->getJquery(); ?>
<script src="/js/toast.js"></script>
</head>
<body>

<div class="container" style="padding:20px 20px 60px 20px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
    <!--
        <a href="/dashboard.php" title="Dashboard" style="text-decoration:none;font-size:24px;">&#x1F5C3;</a>
        -->
        <h2 style="margin:0">Batch Importer</h2>
    </div>

    <?php 
    // Render spawn type tabs
    $spawnTypes = $galleryManager->getSpawnTypes();
    if (count($spawnTypes) > 1): 
    ?>
        <div class="spawn-type-tabs">
            <?php foreach ($spawnTypes as $code => $type): 
                $active = ($code === $selectedTypeCode) ? 'active' : '';
                $disabled = !$type['batch_import_enabled'] ? ' (import disabled)' : '';
            ?>
                <a href="?spawn_type=<?= htmlspecialchars($code) ?>" class="spawn-tab <?= $active ?>">
                    <?= htmlspecialchars($type['label']) ?><?= $disabled ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" id="importForm">
            <h3 style="margin-top:0">Import <?= $activeType ? htmlspecialchars($activeType['label']) : 'Spawns' ?></h3>

            <?php if (!$canImport): ?>
                <div class="import-disabled">
                    <strong>⚠️ Batch import is disabled for this spawn type.</strong>
                    <p style="margin:8px 0 0 0">Please select a different spawn type or enable batch import in the database.</p>
                </div>
            <?php endif; ?>

            <p>
                Files available for import from
                <div style="width:100%; max-width:600px; background:var(--card-weak); overflow:auto; padding:8px; border-radius:6px;">
                    <code><?= htmlspecialchars($importDir) ?></code>
                </div>
            </p>

            <?php if (empty($files)): ?>
                <p style="color:var(--muted); font-style:italic;">No files found in the import folder.</p>
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
                        <select name="spawn_type_id" id="spawn_type_id" class="form-control">
                            <?php foreach ($importableTypes as $type): ?>
                                <option value="<?= (int)$type['id'] ?>" <?= ($activeType && $type['id'] === $activeType['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="spawn_type_id" value="<?= (int)$spawnTypeId ?>">
                <?php endif; ?>

                <?php if ($canImport): ?>
                    <label style="margin-bottom: 10px; display:block;">
                        <input type="checkbox" onclick="toggleSelectAll(this)"> 
                        <strong>Select / Deselect All</strong>
                    </label>

                    <ul class="file-list" aria-live="polite">
                        <?php foreach ($files as $file): ?>
                            <li>
                                <label style="display:inline-flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="files[]" value="<?= htmlspecialchars($file) ?>">
                                    <span><?= htmlspecialchars(basename($file)) ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div style="margin-top:15px;">
                        <button type="submit" class="btn btn-primary">Start Import</button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($flash['results'])): ?>
        <div style="margin-top:20px;">
            <h3 style="margin:0 0 8px 0">Import Results</h3>
            <?php foreach ($flash['results'] as $line): 
                $isError = (stripos($line, '[SKIP]') !== false) || (stripos($line, 'Failed') !== false) || (stripos($line, 'error') !== false);
                $cls = $isError ? 'error' : 'success';
            ?>
                <div class="result <?= $cls ?>"><?= htmlspecialchars($line) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function toggleSelectAll(source) {
    const checkboxes = document.getElementsByName('files[]');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

// If we have flash results from the server, show them via Toast
<?php if (!empty($flash['results']) && is_array($flash['results'])): ?>
(function() {
    const results = <?= json_encode(array_values($flash['results'])) ?>;
    results.forEach(function(line) {
        const isError = /(\[SKIP\]|failed|error)/i.test(line);
        try {
            if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
                Toast.show(line, isError ? 'error' : 'success');
            } else {
                // fallback: console
                console.log((isError ? 'ERROR:' : 'OK:'), line);
            }
        } catch (e) {
            console.log('Toast show failed', e);
        }
    });
})();
<?php endif; ?>
</script>

</body>
</html>