<?php
// import_audio_to_composite.php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/AudioToCompositeImporter.php';

// --- DEFAULTS ---
$defaults = [
    'source'             => 'audio_dialogue_lines', 
    'source_entity_id'   => '',
    'audio_id'           => '',
    'target_composite_id'=> '',
    'ajax'               => 0
];

$params = array_merge($defaults, $_GET);

// Get the hardcoded list from the class
$allowedEntities = AudioToCompositeImporter::getAllowedEntities();

$results = [];
$errorMissing = [];

// --- PROCESS SUBMISSION ---
if (isset($_GET['do_import']) && $_GET['do_import'] == 1) {
    
    // Validation
    if (empty($params['source'])) { $errorMissing[] = 'Source Entity'; }
    if (empty($params['source_entity_id'])) { $errorMissing[] = 'Source Entity ID'; }
    if (empty($params['target_composite_id'])) { $errorMissing[] = 'Target Composite ID'; }
    
    // Audio ID is optional
    $audioId = !empty($params['audio_id']) ? (int)$params['audio_id'] : null;

    if (empty($errorMissing)) {
        $results = AudioToCompositeImporter::import(
            $params['source'],
            (int)$params['source_entity_id'],
            (int)$params['target_composite_id'],
            $audioId
        );
    }
}

// --- AJAX MODE ---
if (!empty($params['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => empty($errorMissing) ? 'ok' : 'error', 
        'results' => $results, 
        'missing' => $errorMissing
    ]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Audio to Composite</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
      (function() {
        try {
          var theme = localStorage.getItem('spw_theme');
          if (theme === 'dark') document.documentElement.setAttribute('data-theme','dark');
          else if (theme === 'light') document.documentElement.setAttribute('data-theme','light');
        } catch(e){}
      })();
    </script>
    <script src="/js/theme-manager.js"></script>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="css/form.css">
    <style>
        .importer-container { max-width: 600px; margin: 40px auto; padding: 20px; }
        .result-box { margin-top: 20px; padding: 15px; border-radius: 4px; border: 1px solid #ccc; }
        .result-box.success { background: #e8f5e9; border-color: #c8e6c9; color: #2e7d32; }
        .result-box.error { background: #ffebee; border-color: #ffcdd2; color: #c62828; }
        
        [data-theme="dark"] .result-box.success { background: #1b5e20; border-color: #2e7d32; color: #fff; }
        [data-theme="dark"] .result-box.error { background: #b71c1c; border-color: #d32f2f; color: #fff; }
    </style>
</head>
<body>

<div class="importer-container">
    <h2>Assign Audio to Composite</h2>
    <p>Select a source entity type and assign its linked audio to a Composite.</p>

    <form method="get" action="">
        <input type="hidden" name="do_import" value="1">

        <label for="source">Source Entity Type:</label>
        <select name="source" id="source" required>
            <option value="">-- Select Source --</option>
            <?php foreach ($allowedEntities as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" 
                    <?= ($params['source'] === $e) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="source_entity_id">Source Entity ID (Row ID):</label>
        <input type="number" name="source_entity_id" id="source_entity_id" 
               value="<?= htmlspecialchars($params['source_entity_id']) ?>" required placeholder="e.g. 15">

        <label for="audio_id">Specific Audio ID (Optional):</label>
        <div style="font-size: 0.8em; margin-bottom: 5px; opacity: 0.8;">
            Leave empty to automatically use the latest audio attached to the source entity.
        </div>
        <input type="number" name="audio_id" id="audio_id" 
               value="<?= htmlspecialchars($params['audio_id']) ?>" placeholder="e.g. 420">

        <label for="target_composite_id">Target Composite ID:</label>
        <input type="number" name="target_composite_id" id="target_composite_id" 
               value="<?= htmlspecialchars($params['target_composite_id']) ?>" required placeholder="e.g. 99">

        <button type="submit" style="margin-top: 15px;">Assign Audio</button>
    </form>

    <?php if (!empty($errorMissing)): ?>
        <div class="result-box error">
            <strong>Missing required fields:</strong> <?= implode(', ', $errorMissing) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <?php 
            // Simple heuristic to style the result box based on the first message
            $isError = (stripos($results[0], 'Error') !== false);
        ?>
        <div class="result-box <?= $isError ? 'error' : 'success' ?>">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach($results as $line): ?>
                    <li><?= htmlspecialchars($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
