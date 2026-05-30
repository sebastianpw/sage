<?php
// import_generatives_from_mouthshapes.php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/EntityToEntityImporter.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// --- GET PARAMETERS ---
$sourceEntity   = $_GET['source'] ?? null;
$sourceEntityId = isset($_GET['source_entity_id']) ? (int)$_GET['source_entity_id'] : 0;
$frameId        = isset($_GET['frame_id']) ? (int)$_GET['frame_id'] : 0;

// Basic Validation
$missingParams = [];
if (!$sourceEntity) $missingParams[] = 'source';
if (!$sourceEntityId) $missingParams[] = 'source_entity_id';
if (!$frameId) $missingParams[] = 'frame_id';

$results = [];
$error = null;

// --- HANDLE POST (IMPORT PROCESS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($missingParams)) {
    
    $selectedShapes = $_POST['shapes'] ?? [];
    
    if (empty($selectedShapes)) {
        $error = "No mouth shapes were selected.";
    } else {
        // Prepare statement to get specific mouthshape data
        $shapeStmt = $mysqli->prepare("SELECT name, description FROM animation_mouthshapes WHERE id = ?");
        
        // Prepare statement to find the newly created generative ID
        // Strategy: Find the highest ID in generatives that matches this frame_id
        $findIdStmt = $mysqli->prepare("SELECT id FROM generatives WHERE img2img_frame_id = ? ORDER BY id DESC LIMIT 1");
        
        // Prepare statement to update the new generative
        $updateStmt = $mysqli->prepare("UPDATE generatives SET name = ?, description = ? WHERE id = ?");

        foreach ($selectedShapes as $shapeId) {
            // 1. Get Mouthshape Data
            $shapeStmt->bind_param('i', $shapeId);
            $shapeStmt->execute();
            $shapeRes = $shapeStmt->get_result();
            $shapeData = $shapeRes->fetch_assoc();
            
            if (!$shapeData) {
                $results[] = "<span class='error'>Mouthshape #$shapeId not found. Skipped.</span>";
                continue;
            }

            try {
                // 2. Run the Standard Import
                // We perform 1 import, and we do NOT copy name/desc (we will set them manually)
                // Source -> Generatives
                $importLog = EntityToEntityImporter::import(
                    $sourceEntity, 
                    'generatives', 
                    $sourceEntityId, 
                    1,      // Limit 1
                    false,  // Copy Name/Desc = False (we will overwrite)
                    $frameId, 
                    null    // Target Entity ID (null = insert new)
                );

                // Check if import returned a success message in the log
                // The importer returns an array of strings.
                $importSuccess = false;
                foreach($importLog as $logLine) {
                    if (stripos($logLine, 'imported into') !== false) {
                        $importSuccess = true;
                        break;
                    }
                }

                if (!$importSuccess) {
                     $results[] = "<span class='error'>Import failed for '{$shapeData['name']}': " . implode('; ', $importLog) . "</span>";
                     continue;
                }

                // 3. Find the ID of the Generative we just made
                $findIdStmt->bind_param('i', $frameId);
                $findIdStmt->execute();
                $findRes = $findIdStmt->get_result();
                $newRow = $findRes->fetch_assoc();
                
                if (!$newRow) {
                    $results[] = "<span class='error'>Could not retrieve new ID for '{$shapeData['name']}' (Frame #$frameId).</span>";
                    continue;
                }
                
                $newGenerativeId = $newRow['id'];

                // 4. Update the Generative with Mouthshape Name/Prompt
                $generativeName = $shapeData['name']; // e.g. "Mouth_A"
                $generativePrompt = $shapeData['description']; 
                
                $updateStmt->bind_param('ssi', $generativeName, $generativePrompt, $newGenerativeId);
                $updateStmt->execute();

                $results[] = "<span class='success'>Created Generative #$newGenerativeId for <strong>{$shapeData['name']}</strong>.</span>";

            } catch (Exception $e) {
                $results[] = "<span class='error'>Error processing '{$shapeData['name']}': " . $e->getMessage() . "</span>";
            }
        }
        
        $shapeStmt->close();
        $findIdStmt->close();
        $updateStmt->close();
    }
}

// --- FETCH MOUTH SHAPES FOR VIEW ---
$mouthShapes = [];
if (empty($missingParams)) {
    $res = $mysqli->query("SELECT * FROM animation_mouthshapes WHERE active = 1 ORDER BY name ASC");
    if ($res) {
        $mouthShapes = $res->fetch_all(MYSQLI_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Mouthshapes to Generatives</title>
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
        .shape-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .shape-item {
            background: var(--bg-secondary, #eee);
            padding: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color, #ccc);
        }
        .shape-item label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
        }
        .shape-desc {
            font-size: 0.85em;
            color: var(--text-muted, #666);
            margin-top: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color, #ccc);
        }
    </style>
</head>
<body>

<div style="position: absolute; top: 50px; margin: 0 20px 80px 20px;">
    <div style="position: absolute;">
        <h2 style="margin: 0; padding: 0 0 20px 0;">
            Import Mouthshapes
        </h2>          
    </div>
</div>

<div style="margin: 0; padding: 0;">
    <br /><br /><br /><br />
</div>

<div style="margin: 20px;">

    <?php if (!empty($missingParams)): ?>
        <div class="result error">
            Missing parameters: <?= implode(', ', $missingParams) ?>.
        </div>
    <?php else: ?>

        <div style="margin-bottom: 20px;">
            <strong>Source Entity:</strong> <?= htmlspecialchars($sourceEntity) ?> #<?= $sourceEntityId ?> <br>
            <strong>Frame ID:</strong> <?= $frameId ?>
        </div>

        <?php if (!empty($results)): ?>
            <div class="result" style="margin-bottom: 20px;">
                <?php foreach ($results as $line): ?>
                    <div class="line"><?= $line ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="result error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <!-- Data Grid -->
            <div class="shape-list">
                <?php if (empty($mouthShapes)): ?>
                    <div>No active mouthshapes found in database.</div>
                <?php else: ?>
                    <?php foreach ($mouthShapes as $shape): ?>
                        <div class="shape-item">
                            <label>
                                <input type="checkbox" name="shapes[]" value="<?= $shape['id'] ?>" checked>
                                <?= htmlspecialchars($shape['name']) ?>
                            </label>
                            <div class="shape-desc" title="<?= htmlspecialchars($shape['description']) ?>">
                                <?= htmlspecialchars($shape['description']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="actions">
                <button type="submit">Create Generatives</button>
                <button type="button" onclick="toggleAll(this)">Unselect All</button>
            </div>
        </form>

    <?php endif; ?>

</div>

<script>
    let allSelected = true;
    function toggleAll(btn) {
        const cbs = document.querySelectorAll('input[name="shapes[]"]');
        allSelected = !allSelected;
        cbs.forEach(cb => cb.checked = allSelected);
        btn.innerText = allSelected ? "Unselect All" : "Select All";
    }
</script>

</body>
</html>
