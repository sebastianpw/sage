<?php
// public/gallery_nu_generator.php
// PHP-based generator for modular gallery view files.
// Uses public/gallery_characters_nu.php as a master template.

require_once __DIR__ . '/bootstrap.php';

/**
 * Generates the PHP code for gear menu actions for a specific entity.
 */
function generateGearActionsCode(string $entity): string
{
    // 'controlnet_maps' has a completely different set of actions.
    if ($entity === 'controlnet_maps') {
        // Hardcoded block for simplicity and clarity.
        // Note: \$entity is escaped to ensure it's treated as a literal PHP variable in the final output file.
        $code = <<<EOT
\$gearMenu->addAction('{$entity}', [
    'label' => 'Assign to Character',
    'icon' => '👤',
    'callback' => 'window.showImportEntityModal({
        source: "' . \$entity . '",
        target: "characters",
        source_entity_id: entityId,
        frame_id: frameId,
        limit: 1,
        copy_name_desc: 0,
        controlnet: 1
    });'
]);

\$gearMenu->addAction('{$entity}', [
    'label' => 'Assign to Generative',
    'icon' => '⚡',
    'callback' => 'window.showImportEntityModal({
        source: "' . \$entity . '",
        target: "generatives",
        source_entity_id: entityId,
        frame_id: frameId,
        limit: 1,
        copy_name_desc: 0,
        controlnet: 1
    });'
]);

\$gearMenu->addAction('{$entity}', [
    'label' => 'Assign to Sketch',
    'icon' => '✏️',
    'callback' => 'window.showImportEntityModal({
        source: "' . \$entity . '",
        target: "sketches",
        source_entity_id: entityId,
        frame_id: frameId,
        limit: 1,
        copy_name_desc: 0,
        controlnet: 1
    });'
]);
EOT;
        return $code;
    }

    return '';
}


/**
 * Generate gallery view file for a specific entity using gallery_characters_nu.php as a template.
 */
function generateGalleryView(string $entity): string
{
    $templatePath = __DIR__ . '/gallery_characters_nu.php';
    if (!file_exists($templatePath)) {
        throw new \RuntimeException("Master template file not found: {$templatePath}");
    }
    $content = file_get_contents($templatePath);

    // Convert entity name to PascalCase for class names and titles
    $pascalCaseName = str_replace(' ', '', ucwords(str_replace('_', ' ', $entity)));
    
    // --- Perform universal replacements first ---
    
    // 1. Replace the file header comment
    $content = str_replace('// public/gallery_characters_nu.php', "// public/gallery_{$entity}_nu.php", $content);
    
    // 2. Replace the Gallery class name
    $content = str_replace('use App\Gallery\CharactersNuGallery;', "use App\Gallery\\{$pascalCaseName}NuGallery;", $content);
    $content = str_replace('new CharactersNuGallery()', "new {$pascalCaseName}NuGallery()", $content);
    
    // 3. Replace the page title
    $content = str_replace('"Characters Gallery (Modular)"', "\"{$pascalCaseName} Gallery (Modular)\"", $content);
    
    // 4. Replace the entity variable declaration
    $content = str_replace("\$entity = 'characters';", "\$entity = '{$entity}';", $content);

    // 5. Replace gear menu entity config
    $content = str_replace("'show_for_entities' => ['characters']", "'show_for_entities' => ['{$entity}']", $content);
    $content = str_replace('// Configure gear menu module for characters', "// Configure gear menu module for {$entity}", $content);


    // --- Now, handle entity-specific sections ---

    // Define the boundaries for our code blocks
    $gearActionsStartSentinel = '$gearMenu->addAction';
    $gearActionsEndSentinel = '// Configure image editor module';
    $imageEditorConfigStartSentinel = '// Configure image editor module';
    $imageEditorConfigEndSentinel = '// Create the gallery instance';

    // Find the Gear Actions block
    $gearStartPos = strpos($content, $gearActionsStartSentinel);
    $gearEndPos = strpos($content, $gearActionsEndSentinel, $gearStartPos);
    
    if ($gearStartPos === false || $gearEndPos === false) {
        throw new \RuntimeException("Could not find the Gear Action block in the template.");
    }
    
    $gearActionsBlock = substr($content, $gearStartPos, $gearEndPos - $gearStartPos);

    // First, perform the standard replacement of 'characters' inside the action block for ALL entities.
    $newGearActionsBlock = str_replace("\$gearMenu->addAction('characters',", "\$gearMenu->addAction('{$entity}',", $gearActionsBlock);
    $content = str_replace($gearActionsBlock, $newGearActionsBlock, $content);
    
    // Now, handle special cases like 'controlnet_maps'.
    if ($entity === 'controlnet_maps') {
        // A. Generate the specific actions for controlnet_maps.
        $customActionsCode = generateGearActionsCode($entity);

        // B. Prepend the custom actions to the start of the gear actions section.
        // We find the first `$gearMenu->addAction` (which we know exists) and insert before it.
        $firstActionPos = strpos($content, $gearActionsStartSentinel);
        if ($firstActionPos !== false) {
            $content = substr_replace($content, $customActionsCode . "\n\n", $firstActionPos, 0);
        }

        // C. Also, completely remove the Image Editor configuration and render call.
        $editorStartPos = strpos($content, $imageEditorConfigStartSentinel);
        $editorEndPos = strpos($content, $imageEditorConfigEndSentinel, $editorStartPos);

        if ($editorStartPos !== false && $editorEndPos !== false) {
            $imageEditorBlock = substr($content, $editorStartPos, $editorEndPos - $editorStartPos);
            $content = str_replace($imageEditorBlock, '', $content);
        }
        
        // Remove the render call.
        $content = preg_replace('/^\s*\. \$imageEditor->render\(\)\R/m', '', $content);
    }
    
    return $content;
}


// --- Main execution logic (unchanged) ---

if (php_sapi_name() === 'cli') {
    // CLI mode - generate for all entities
    $entitiesJson = shell_exec('php -f sage_entities_items_json.php');
    $entities = json_decode($entitiesJson, true);

    if (!$entities) {
        die("Failed to fetch entities\n");
    }

    $generated = [];
    foreach ($entities as $entityData) {
        $entity = $entityData['name'];

        // Do not overwrite the template file itself
        if ($entity === 'characters') {
            continue;
        }
        
        $filename = __DIR__ . "/gallery_{$entity}_nu.php";
        
        try {
            $content = generateGalleryView($entity);
            file_put_contents($filename, $content);
            $generated[] = $entity;
            echo "Generated: {$filename}\n";
        } catch (\Exception $e) {
            echo "Error generating for '{$entity}': " . $e->getMessage() . "\n";
        }
    }

    echo "\nCompleted! Generated gallery files for: " . implode(', ', $generated) . "\n";
} else {
    // Web mode - show form or generate single entity
    if (isset($_GET['entity'])) {
        $entity = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['entity']));
        if ($entity) {
            header('Content-Type: text/plain; charset=utf-8');
            try {
                echo generateGalleryView($entity);
            } catch (\Exception $e) {
                http_response_code(500);
                echo "Error: " . $e->getMessage();
            }
        }
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Gallery Generator</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
                .form-group { margin-bottom: 15px; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input, select { width: 100%; padding: 8px; font-size: 14px; }
                button { padding: 10px 20px; font-size: 14px; cursor: pointer; background: #0d6efd; color: white; border: none; border-radius: 4px; }
                button:hover { background: #0b5ed7; }
                .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #0d6efd; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <h1>Modular Gallery Generator</h1>
            
            <div class="info">
                <strong>Generate modular gallery view files</strong><br>
                This script uses <code>public/gallery_characters_nu.php</code> as a template.<br>
                Run from CLI: <code>php gallery_nu_generator.php</code><br>
                Or use the form below to preview a single entity.
            </div>
            
            <form method="get">
                <div class="form-group">
                    <label>Entity Name:</label>
                    <input type="text" name="entity" placeholder="e.g., generatives, sketches, controlnet_maps" required>
                </div>
                <button type="submit">Generate Preview</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Run Full Rollout</h2>
            <p>To generate all gallery files at once (except for 'characters'), run from the command line:</p>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;">php public/gallery_nu_generator.php</pre>
        </body>
        </html>
        <?php
    }
}
