<?php
// public/import_frames_to_storyboard.php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/StoryboardHelper.php';
require "sage_entities_items_array.php"; 

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- DEFAULTS ---
$defaults = [
    'storyboard_id'    => '',
    'source_entity'    => '',
    'source_entity_id' => '',
    'limit'            => '', // Default empty
];

$params = array_merge($defaults, $_GET);

// Cast inputs
$storyboardId   = (int)$params['storyboard_id'];
$sourceEntity   = $params['source_entity'];
$sourceEntityId = (int)$params['source_entity_id'];
$rawLimit       = $params['limit'];

// Logic: If limit is empty, default to 1 (Strict Mode: just the one entity).
// If limit > 0, it is the Count of Entities to process.
$limit = ($rawLimit !== '' && (int)$rawLimit > 0) ? (int)$rawLimit : 1;

$entitiesList = array_column($items, 'name');

// --- HANDLE IMPORT ---
$results = [];
$error = null;

if ($storyboardId > 0) {
    try {
        $frameIds = [];
        $modeDescription = "";

        // MODE A: ENTITY BATCH MODE (User selected an Entity Type + Start ID)
        if (!empty($sourceEntity) && $sourceEntityId > 0) {
            
            // Step 1: Find the relevant Entity IDs
            // FIXED: Injected integer directly into LIMIT to avoid SQL error '35'
            $sqlEntities = "SELECT DISTINCT entity_id 
                            FROM frames 
                            WHERE entity_type = ? 
                              AND entity_id >= ? 
                            ORDER BY entity_id ASC 
                            LIMIT " . (int)$limit;
            
            $stmt = $pdo->prepare($sqlEntities);
            $stmt->execute([$sourceEntity, $sourceEntityId]);
            $targetEntityIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($targetEntityIds)) {
                throw new Exception("No entities found starting from ID #$sourceEntityId.");
            }

            $countEntities = count($targetEntityIds);
            $minEnt = min($targetEntityIds);
            $maxEnt = max($targetEntityIds);
            $modeDescription = "Batch Mode: Processed <strong>$countEntities</strong> entities (IDs #$minEnt to #$maxEnt).";

            // Step 2: Fetch ALL frames for these Entity IDs
            // Dynamic placeholder generation for IN clause
            $placeholders = implode(',', array_fill(0, count($targetEntityIds), '?'));
            
            $sqlFrames = "SELECT id 
                          FROM frames 
                          WHERE entity_type = ? 
                            AND entity_id IN ($placeholders) 
                          ORDER BY entity_id ASC, id ASC";
            
            // Merge parameters: [Type, ID1, ID2, ID3...]
            $frameParams = array_merge([$sourceEntity], $targetEntityIds);
            
            $stmtF = $pdo->prepare($sqlFrames);
            $stmtF->execute($frameParams);
            $frameIds = $stmtF->fetchAll(PDO::FETCH_COLUMN);

        } 
        // MODE B: RAW FRAME MODE (Fallback if no Entity Type selected)
        elseif (isset($_GET['start_frame_id']) && (int)$_GET['start_frame_id'] > 0) {
            $startFrame = (int)$_GET['start_frame_id'];
            $limitFrames = ($limit > 1) ? $limit : 100; // Default to 100 if raw mode
            
            // FIXED: Injected integer directly into LIMIT
            $sql = "SELECT id FROM frames WHERE id >= ? ORDER BY id ASC LIMIT " . (int)$limitFrames;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startFrame]);
            $frameIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $modeDescription = "Raw Frame Mode: Imported next $limitFrames frames.";
        }

        // --- EXECUTE IMPORT ---
        if (empty($frameIds) && !empty($sourceEntity)) {
            $error = "Entities were found, but they contain no frames.";
        } elseif (empty($frameIds)) {
            // Only show error if form was actually submitted
            if (!empty($_GET)) $error = "No frames found matching criteria.";
        } else {
            $helper = new \App\Helper\StoryboardHelper($pdo);
            $importResult = $helper->importFramesToStoryboard($storyboardId, $frameIds);

            if (!empty($importResult['imported'])) {
                $count = count($importResult['imported']);
                
                $results[] = "Successfully imported <strong>{$count}</strong> frame(s) into Storyboard #{$storyboardId}.";
                $results[] = $modeDescription;
            }
            
            if (!empty($importResult['errors'])) {
                foreach ($importResult['errors'] as $errMsg) {
                    $results[] = "Error: " . htmlspecialchars($errMsg);
                }
            }
            
            if (empty($results)) {
                 $results[] = "No changes made (frames might already exist in storyboard).";
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Import to Storyboard</title>
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
        .hint { font-size: 0.85em; color: var(--text-muted, #888); margin-top: -10px; margin-bottom: 15px; display: block; line-height: 1.4; }
        .section-sep { border-top: 1px solid var(--border-color, #ccc); margin: 20px 0; padding-top: 10px; font-weight: bold; }
        .mode-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; margin-left: 5px; }
        .mode-entity { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
        [data-theme="dark"] .mode-entity { background: #004085; color: #cce5ff; border-color: #002752; }
    </style>
</head>
<body>

<div style="position: absolute; top: 50px; margin: 0 20px 80px 20px;">
    <h2 style="display:none;">Storyboard Importer</h2>          
</div>

<div style="margin: 0; padding: 0;"><br /></div>

<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">

    <form method="get">
        <h2>Import Frames to Storyboard</h2>

        <label for="storyboard_id">Target Storyboard:</label>
        <select name="storyboard_id" id="storyboard_id" required>
            <option value="">Loading storyboards...</option>
        </select>

        <div class="section-sep">1. Entity Selection</div>

        <label for="source_entity">Source Entity Type:</label>
        <select name="source_entity" id="source_entity">
            <option value="">-- Select Type --</option>
            <?php foreach ($entitiesList as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= ($sourceEntity === $e) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="source_entity_id">Start from Entity ID:</label>
        <input type="number" name="source_entity_id" id="source_entity_id" value="<?= htmlspecialchars($sourceEntityId ?: '') ?>" min="0" placeholder="e.g. 1032">

        <div class="section-sep">2. Range / Quantity</div>

        <label for="limit">Number of Entities to Process:</label>
        <input type="number" name="limit" id="limit" value="<?= htmlspecialchars($rawLimit) ?>" min="1" max="1000" placeholder="Default: 1 (Only the start entity)">
        
        <div id="mode-explainer" class="hint" style="margin-top: 5px;">
            Loading mode info...
        </div>

        <button type="submit">Mass Import</button>
        
        <div style="margin-top: 20px;">
            <a href="/view_storyboards.php" style="color: var(--link-color);">← Back to Storyboards</a>
        </div>
    </form>

</div>

<div style="display:none;clear:both;width:100%;"></div>

<?php if ($error): ?>
    <div class="result"><div class="error"><strong>Import Failed:</strong> <?= htmlspecialchars($error) ?></div></div>
<?php endif; ?>

<?php if (!empty($results)): ?>
    <div class="result">
        <?php foreach ($results as $line): ?>
            <?php $class = (stripos($line, 'Error') !== false || stripos($line, 'failed') !== false) ? 'error' : 'success'; ?>
            <div class="result <?= $class ?>"><?= $line ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    // 1. Load Storyboards
    const sbSelect = document.getElementById('storyboard_id');
    const selectedId = "<?= $storyboardId ?>"; 
    try {
        const resp = await fetch('/storyboards_api.php?action=list', { credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success && Array.isArray(data.data)) {
            sbSelect.innerHTML = '<option value="">-- Select Storyboard --</option>';
            data.data.forEach(sb => {
                const option = document.createElement('option');
                option.value = sb.id;
                option.textContent = `#${sb.id} - ${sb.name} (${sb.frame_count} frames)`;
                if (selectedId && sb.id == selectedId) option.selected = true;
                sbSelect.appendChild(option);
            });
        }
    } catch (err) { sbSelect.innerHTML = '<option value="">Connection failed</option>'; }

    // 2. Dynamic Hint Logic
    const limitInput = document.getElementById('limit');
    const entityIdInput = document.getElementById('source_entity_id');
    const typeInput = document.getElementById('source_entity');
    const explainer = document.getElementById('mode-explainer');

    function updateHint() {
        const count = parseInt(limitInput.value) || 1;
        const startId = entityIdInput.value || 'X';
        const type = typeInput.value || 'Entity';

        if (typeInput.value) {
            if (count === 1) {
                explainer.innerHTML = `
                    <span class="mode-badge mode-entity">Single Entity</span> 
                    Import <strong>ALL</strong> frames for ${type} #${startId} only.
                `;
            } else {
                explainer.innerHTML = `
                    <span class="mode-badge mode-entity">Entity Batch</span> 
                    Import <strong>ALL</strong> frames for <strong>${count}</strong> ${type}s,<br>
                    starting from #${startId} (e.g., #${startId} to #${parseInt(startId) + count - 1}).
                `;
            }
        } else {
            explainer.innerHTML = `Please select an Entity Type first.`;
        }
    }

    limitInput.addEventListener('input', updateHint);
    entityIdInput.addEventListener('input', updateHint);
    typeInput.addEventListener('change', updateHint);
    updateHint();
});
</script>

</body>
</html>
