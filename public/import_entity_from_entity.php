<?php
// import_entity_from_entity.php
//
// Web GET example:
// http://localhost:8080/import_entity_from_entity.php?source=spawns&target=characters&source_entity_id=1&copy_name_desc=0&target_entity_id=123&frame_id=456
//
// CLI example:
// php import_entity_from_entity.php source=spawns target=characters source_entity_id=1 copy_name_desc=0 target_entity_id=123 frame_id=456
//
// AJAX example:
// fetch('/import_entity_from_entity.php?ajax=1&source=spawns&target=characters&source_entity_id=1&copy_name_desc=0&target_entity_id=123&frame_id=456')
//     .then(res => res.json())
//     .then(data => console.log(data.result));

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/EntityToEntityImporter.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// --- DEFAULTS ---
$defaults = [
    'source'             => null,
    'target'             => null,
    'source_entity_id'   => 0,
    'limit'              => 1,
    'copy_name_desc'     => 1,
    'target_entity_id'   => null,
    'frame_id'           => null,
    'controlnet'         => 0,
    'composite'          => 0
];

// --- GET PARAMETERS ---
$params = [];
if (php_sapi_name() === 'cli') {
    global $argv;
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
            $params[$key] = $value;
        }
    }
} else {
    $params = $_GET;
}

$params = array_merge($defaults, $params);

// --- MODE FLAGS ---
$controlNetMode = !empty($params['controlnet']) && (int)$params['controlnet'] === 1;
$compositeMode = !empty($params['composite']) && (int)$params['composite'] === 1;

// standard params (may be overridden in special modes)
$sourceEntity      = $params['source'];
$targetEntity      = $params['target'];
$sourceEntityId    = (int)$params['source_entity_id'];
$limit             = (int)$params['limit'];
$copyNameDesc      = (bool)$params['copy_name_desc'];

$targetEntityId = isset($params['target_entity_id']) && $params['target_entity_id'] !== '' 
    ? (int)$params['target_entity_id'] 
    : null;

$frameId = isset($params['frame_id']) && $params['frame_id'] !== '' 
    ? (int)$params['frame_id'] 
    : null;

// load $items array
require "sage_entities_items_array.php";

// Extract only the `name` column
$entitiesList = array_column($items, 'name');

// --- ORIGINAL VALIDATION (left intact) ---
if ($targetEntityId != null && $limit > 1) {
    throw new Exception("Cannot specify target_entity_id when limit > 1.");
}
if ($targetEntityId != null && $frameId === null) {
    throw new Exception("frame_id is required when updating an existing row via target_entity_id.");
}

// For updates, default copyNameDesc to false if not explicitly provided
if ($targetEntityId != null && !isset($params['copy_name_desc'])) {
    $copyNameDesc = false;
}

// --- CONTROLNET MODE ADJUSTMENTS ---
if ($controlNetMode) {
    // fixed mapping for controlnet assignment
    $sourceEntity = 'controlnet_maps';
    
    // enforce limit and copy_name_desc server-side
    $limit = 1;
    $copyNameDesc = false; // never copy name/description in controlnet mode
}

// --- COMPOSITE MODE ADJUSTMENTS ---
if ($compositeMode) {
    // target must be composites
    $targetEntity = 'composites';
    
    // enforce limit and copy_name_desc server-side
    $limit = 1;
    $copyNameDesc = false; // never copy name/description in composite mode
}

// --- HANDLE IMPORT ---
$importResult = [];
$performImport = false;
$controlNetMissing = []; // will hold names of missing params for clearer UX
$compositeMissing = []; // will hold names of missing params for composite mode

if ($controlNetMode) {
    // Required for ControlNet assignment
    if (empty($params['source_entity_id']) || (int)$params['source_entity_id'] === 0) {
        $controlNetMissing[] = 'source_entity_id';
    }
    if (!isset($params['frame_id']) || $params['frame_id'] === '') {
        $controlNetMissing[] = 'frame_id';
    }
    if (!isset($params['target_entity_id']) || $params['target_entity_id'] === '') {
        $controlNetMissing[] = 'target_entity_id';
    }

    if (empty($controlNetMissing)) {
        $performImport = true;
    } else {
        $performImport = false;
    }
} elseif ($compositeMode) {
    // Required for Composite assignment
    if (empty($params['source_entity_id']) || (int)$params['source_entity_id'] === 0) {
        $compositeMissing[] = 'source_entity_id';
    }
    if (!isset($params['frame_id']) || $params['frame_id'] === '') {
        $compositeMissing[] = 'frame_id';
    }
    if (!isset($params['target_entity_id']) || $params['target_entity_id'] === '') {
        $compositeMissing[] = 'target_entity_id';
    }

    if (empty($compositeMissing)) {
        $performImport = true;
    } else {
        $performImport = false;
    }
} else {
    // original behavior: if source and target present we import immediately
    if ($sourceEntity && $targetEntity) {
        $performImport = true;
    }
}

if ($performImport) {
    // In ControlNet mode we MUST call importControlNet
    if ($controlNetMode) {
        if (!method_exists('EntityToEntityImporter', 'importControlNet')) {
            throw new Exception("EntityToEntityImporter::importControlNet() is not implemented. ControlNet imports cannot continue without it.");
        }

        $importResult = EntityToEntityImporter::importControlNet(
            $sourceEntity,
            $targetEntity,
            $sourceEntityId,
            $limit,
            $copyNameDesc,
            $frameId,
            $targetEntityId
        );
    } elseif ($compositeMode) {
        // In Composite mode we MUST call importComposite
        if (!method_exists('EntityToEntityImporter', 'importComposite')) {
            throw new Exception("EntityToEntityImporter::importComposite() is not implemented. Composite imports cannot continue without it.");
        }

        $importResult = EntityToEntityImporter::importComposite(
            $sourceEntity,
            $targetEntity,
            $sourceEntityId,
            $limit,
            $copyNameDesc,
            $frameId,
            $targetEntityId
        );
    } else {
        // normal import path
        $importResult = EntityToEntityImporter::import(
            $sourceEntity,
            $targetEntity,
            $sourceEntityId,
            $limit,
            $copyNameDesc,
            $frameId,
            $targetEntityId
        );
    }
}

// --- AJAX DETECTION ---
$isAjax = !empty($params['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'result' => $importResult,
        'controlnet_missing' => $controlNetMissing,
        'composite_missing' => $compositeMissing
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Entity from Entity</title>

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

<!-- base styles -->
<link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="css/form.css">
</head>
<body>
<?php 
//require "floatool.php"; 
?>
<div style="position: absolute; top: 50px; margin: 0 20px 80px 20px;">
    <div style="position: absolute;">
<!--
        <a href="/dashboard.php" 
           title="Dashboard" 
           style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
            &#x1F5C3;
        </a>
-->
        <h2 style="display:none; margin: 0; padding: 0 0 20px 0; position: absolute; top: 10px; left: 50px;">
            Importer
        </h2>          
    </div>
</div>

<div style="margin: 0; padding: 0;">
    <br />
</div>

<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">

<form method="get">
    <h2 style="display:none;" >Import Entity from Entity</h2>

    <?php if ($controlNetMode): ?>
        <!-- persist controlnet flag on submit -->
        <input type="hidden" name="controlnet" value="1">
    <?php endif; ?>

    <?php if ($compositeMode): ?>
        <!-- persist composite flag on submit -->
        <input type="hidden" name="composite" value="1">
    <?php endif; ?>

<?php
$defaultSource = 'spawns';
$defaultTarget = 'generatives';
?>

<label for="source">Source Entity:</label>
<select name="source" id="source" required <?= ($controlNetMode || $compositeMode) ? 'disabled' : '' ?>>
    <option value="">-- Select Source --</option>
    <?php foreach ($entitiesList as $e): ?>
        <option value="<?= htmlspecialchars($e) ?>"
            <?= ($sourceEntity === $e || (!$sourceEntity && !$controlNetMode && !$compositeMode && $e === $defaultSource)) ? 'selected' : '' ?>>
            <?= htmlspecialchars($e) ?>
        </option>
    <?php endforeach; ?>
</select>
<?php if ($controlNetMode || $compositeMode): // include hidden input so disabled select's value is still submitted ?>
    <input type="hidden" name="source" value="<?= htmlspecialchars($sourceEntity) ?>">
<?php endif; ?>

<label for="target">Target Entity:</label>
<select name="target" id="target" required <?= ($controlNetMode || $compositeMode) ? 'disabled' : '' ?>>
    <option value="">-- Select Target --</option>
    <?php foreach ($entitiesList as $e): ?>
        <option value="<?= htmlspecialchars($e) ?>"
            <?= ($targetEntity === $e || (!$targetEntity && !$controlNetMode && !$compositeMode && $e === $defaultTarget)) ? 'selected' : '' ?>>
            <?= htmlspecialchars($e) ?>
        </option>
    <?php endforeach; ?>
</select>
<?php if ($controlNetMode || $compositeMode): // include hidden input so disabled select's value is still submitted ?>
    <input type="hidden" name="target" value="<?= htmlspecialchars($targetEntity) ?>">
<?php endif; ?>

    <label for="source_entity_id">Source Entity ID (equals first Entity ID in case of bulk import):</label>
    <input type="number" name="source_entity_id" id="source_entity_id" value="<?= htmlspecialchars($sourceEntityId) ?>" min="0" <?= ($controlNetMode || $compositeMode) ? 'readonly' : '' ?>>

    <label for="limit">Limit:</label>
    <input type="number" name="limit" id="limit" value="<?= htmlspecialchars($limit) ?>" min="1" <?= ($controlNetMode || $compositeMode) ? 'readonly' : '' ?>>

    <label for="frame_id">Frame ID (required if updating):</label>
    <input type="number" name="frame_id" id="frame_id" value="<?= htmlspecialchars($frameId ?? '') ?>" min="1" <?= ($controlNetMode || $compositeMode) ? 'readonly' : '' ?>>

    <label for="target_entity_id">
        Target Entity ID (optional, for update):
        <?php if ($controlNetMode): ?>
            <span style="color: red;">(required for ControlNet assignment)</span>
        <?php elseif ($compositeMode): ?>
            <span style="color: red;">(required for Composite assignment)</span>
        <?php endif; ?>
    </label>
    <input type="number" name="target_entity_id" id="target_entity_id" value="<?= htmlspecialchars($targetEntityId ?? '') ?>" min="1">

    <label for="copy_name_desc">
        <input type="checkbox" name="copy_name_desc" id="copy_name_desc" value="1" <?= ($controlNetMode || $compositeMode) ? 'disabled' : ($copyNameDesc ? 'checked' : '') ?>>
        Copy name and description
    </label>
    <?php if ($controlNetMode || $compositeMode): // ensure a value is submitted for copy_name_desc (explicit false) ?>
        <input type="hidden" name="copy_name_desc" value="0">
    <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const targetInput = document.getElementById('target_entity_id');
    const copyCheckbox = document.getElementById('copy_name_desc');

    targetInput.addEventListener('input', function() {
        if (targetInput.value.trim() !== '') {
            copyCheckbox.checked = false;
        }
    });
});
</script>

    <button type="submit">Start Import</button>
</form>




</div>




<div style="display:none;clear:both;width:100%;"></div>

<?php if ($controlNetMode && !empty($controlNetMissing)): ?>
    <div class="result">
        <div class="error">
            ControlNet assignment mode active. The following required parameters are missing: 
            <strong><?= htmlspecialchars(implode(', ', $controlNetMissing)) ?></strong>.
            Please provide them and submit again.
        </div>
    </div>
<?php endif; ?>

<?php if ($compositeMode && !empty($compositeMissing)): ?>
    <div class="result">
        <div class="error">
            Composite assignment mode active. The following required parameters are missing: 
            <strong><?= htmlspecialchars(implode(', ', $compositeMissing)) ?></strong>.
            Please provide them and submit again.
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($importResult)): ?>
    <div class="result">
        <?php foreach ($importResult as $line): ?>
            <?php $class = stripos($line, 'failed') !== false ? 'error' : 'success'; ?>
            <div class="result <?= $class ?>"><?= $line ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>







<!--
</div>
-->







</body>
</html>
