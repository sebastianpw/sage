<?php
// public/gallery_nu_generator.php
// PHP-based generator for modular gallery view files.
// Uses public/gallery_characters_nu.php as a master template.

require_once __DIR__ . '/bootstrap.php';

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
    
    // --- Perform universal replacements ---
    
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

    // --- Handle Gear Menu Actions ---
    // The template uses $gearMenu->addStandardActions($entity);
    // This is valid for almost all entities.
    // However, 'controlnet_maps' has a completely different logic.
    
    if ($entity === 'controlnet_maps') {
        // Define custom actions and append standard actions at the end
        $customActionsCode = <<<PHP
\$gearMenu->addAction('{$entity}', [
    'label' => 'Assign to Character',
    'icon' => '👤',
    'callback' => 'window.showImportEntityModal({ source: "{$entity}", target: "characters", source_entity_id: entityId, frame_id: frameId, limit: 1, copy_name_desc: 0, controlnet: 1 });'
]);

\$gearMenu->addAction('{$entity}', [
    'label' => 'Assign to Generative',
    'icon' => '⚡',
    'callback' => 'window.showImportEntityModal({ source: "{$entity}", target: "generatives", source_entity_id: entityId, frame_id: frameId, limit: 1, copy_name_desc: 0, controlnet: 1 });'
]);

\$gearMenu->addAction('{$entity}', [
    'label' => 'Assign to Sketch',
    'icon' => '✏️',
    'callback' => 'window.showImportEntityModal({ source: "{$entity}", target: "sketches", source_entity_id: entityId, frame_id: frameId, limit: 1, copy_name_desc: 0, controlnet: 1 });'
]);

\$gearMenu->addStandardActions(\$entity);
PHP;
        // Replace the standard call with the custom block
        $content = str_replace('$gearMenu->addStandardActions($entity);', $customActionsCode, $content);

        // Also remove Image Editor for controlnet maps
        $content = preg_replace('/\/\/ Configure image editor module.*\$imageEditor->render\(\);/s', '', $content);
        $content = str_replace('. $imageEditor->render()', '', $content);
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
