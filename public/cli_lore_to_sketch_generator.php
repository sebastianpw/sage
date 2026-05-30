<?php
// public/cli_lore_to_sketch_generator.php
// CLI Tool: Automates conversion of Lore Entities into Visual Sketches via AI
// Run via: php public/cli_lore_to_sketch_generator.php

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';
require_once __DIR__ . '/../src/Core/AIProvider.php';

use App\Service\LoreAccessService;
use App\Core\AIProvider;

// --- UTILITIES ---

function input($prompt = '') {
    echo $prompt;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    return trim($line);
}

function printHeader($text) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo " " . strtoupper($text) . "\n";
    echo str_repeat("=", 50) . "\n";
}

function printRow($id, $label, $info = '') {
    echo sprintf("[%d] %-30s %s\n", $id, substr($label, 0, 30), $info);
}

// Helper to clean AI output (Markdown code blocks or JSON)
function cleanAIOutput($text, $schemaKey = null) {
    $originalText = trim($text);
    
    // 1. Try to isolate JSON block if conversational filler is present
    $jsonStr = $originalText;
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $originalText, $matches)) {
        $jsonStr = $matches[1];
    } elseif (preg_match('/\{.*\}/is', $originalText, $matches)) {
        $jsonStr = $matches[0];
    }

    // 2. Attempt formal JSON parsing
    $json = json_decode($jsonStr, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        if ($schemaKey && isset($json[$schemaKey])) return trim($json[$schemaKey]);
        foreach (['enriched_query', 'description', 'content', 'text', 'sketch'] as $key) {
            if (isset($json[$key])) return trim($json[$key]);
        }
        if (array_is_list($json)) return trim(implode("\n", $json));
        return trim(implode("\n", array_values($json)));
    }
    
    // 3. Fallback: Robust regex extraction if JSON is malformed/truncated
    $cleaned = $originalText;
    
    // Strip leading markdown, braces, and JSON keys (e.g. ```json { "description": " )
    $cleaned = preg_replace('/^\s*(?:```json\s*)?\{\s*"[^"]+"\s*:\s*"/is', '', $cleaned);
    
    // Strip trailing quotes, braces, and markdown (e.g. "} ``` )
    $cleaned = preg_replace('/"\s*\}\s*(?:```\s*)?$/is', '', $cleaned);
    
    // Catch-all for any leftover leading/trailing backticks
    $cleaned = preg_replace('/^\s*```(?:json)?\s*/is', '', $cleaned);
    $cleaned = preg_replace('/\s*```\s*$/is', '', $cleaned);
    
    // Since we bypassed json_decode, unescape common JSON control characters manually
    $cleaned = str_replace('\"', '"', $cleaned);
    $cleaned = str_replace('\n', "\n", $cleaned);
    $cleaned = str_replace('\\\\', '\\', $cleaned);
    
    return trim($cleaned);
}

// --- MAIN LOGIC ---

try {
    global $spw; // Access to Showrunner Core
    $conn = $spw->getEntityManager()->getConnection();
    $ai = new AIProvider();
    $loreService = new LoreAccessService($spw->getPdo());

    printHeader("Lore -> Sketch Auto-Generator");

    // 1. SELECT CONTEXT DOCUMENT
    // --------------------------------------------------------
    $docs = $conn->fetchAllAssociative("
        SELECT d.id, d.name, d.keywords, c.name as cat_name 
        FROM documentations d 
        JOIN md_doc_analysis da ON d.id = da.doc_id 
        LEFT JOIN documentation_categories c ON d.category_id = c.id
        WHERE d.is_active = 1
        ORDER BY d.updated_at DESC
    ");

    if (empty($docs)) die("No analyzed documentation found.\n");

    echo "Available Lore Contexts:\n";
    foreach ($docs as $i => $d) {
        printRow($i + 1, $d['name'], "(" . ($d['cat_name'] ?? 'Uncategorized') . ")");
    }

    $docIndex = (int)input("\nSelect Document Number: ") - 1;
    if (!isset($docs[$docIndex])) die("Invalid selection.\n");

    $selectedDoc = $docs[$docIndex];
    echo "Loading context for: " . $selectedDoc['name'] . "...\n";
    $loreService->loadDoc($selectedDoc['id']);


    // 2. SELECT CATEGORY
    // --------------------------------------------------------
    $categories =['characters', 'locations', 'factions', 'artifacts', 'episodes', 'scene_hooks'];
    $availableCats =[];
    $storyEngine = $loreService->getStoryEngine();
    
    foreach ($categories as $cat) {
        $count = 0;
        if (in_array($cat, ['episodes', 'scene_hooks'])) {
            $count = count($storyEngine[$cat] ??[]);
        } else {
            $items = $loreService->queryEntities($cat);
            $count = count($items);
        }
        
        if ($count > 0) {
            $availableCats[] = ['key' => $cat, 'count' => $count];
        }
    }

    if (empty($availableCats)) die("No entities found in this document.\n");

    printHeader("Select Entity Group");
    foreach ($availableCats as $i => $c) {
        printRow($i + 1, ucfirst($c['key']), "Count: " . $c['count']);
    }

    $catIndex = (int)input("\nSelect Group Number: ") - 1;
    if (!isset($availableCats[$catIndex])) die("Invalid selection.\n");

    $selectedCat = $availableCats[$catIndex];
    $catKey = $selectedCat['key'];


    // 3. DEFINE RANGE
    // --------------------------------------------------------
    $entityList =[];
    if (in_array($catKey, ['episodes', 'scene_hooks'])) {
        $entityList = $storyEngine[$catKey] ??[];
    } else {
        $entityList = $loreService->queryEntities($catKey);
    }

    $total = count($entityList);
    echo "\nGroup '{$catKey}' has {$total} items.\n";
    
    $offset = (int)input("Starting Offset (default 0): ");
    if ($offset < 0) $offset = 0;
    
    $amountInput = input("Amount to process (default All): ");
    $amount = ($amountInput === '') ? ($total - $offset) : (int)$amountInput;
    if ($amount <= 0 || $amount > ($total - $offset)) $amount = $total - $offset;

    $processList = array_slice($entityList, $offset, $amount);
    echo "\nQueue: Processing " . count($processList) . " items (from index $offset).\n";


    // 4. SELECT GENERATOR CONFIG
    // --------------------------------------------------------
    $configs = $conn->fetchAllAssociative("SELECT config_id, title, output_schema FROM generator_config WHERE active = 1 ORDER BY title ASC");
    
    printHeader("Select Generator Config");
    foreach ($configs as $i => $c) {
        printRow($i + 1, $c['title']);
    }

    $cfgIndex = (int)input("\nSelect Config Number: ") - 1;
    if (!isset($configs[$cfgIndex])) die("Invalid selection.\n");
    
    $genConfigRow = $configs[$cfgIndex];
    $genConfigId = $genConfigRow['config_id'];

    $targetSchemaKey = null;
    $schema = json_decode($genConfigRow['output_schema'] ?? '{}', true);
    if (isset($schema['required'][0])) {
        $targetSchemaKey = $schema['required'][0];
    }

    $genConfigFull = $conn->fetchAssociative("SELECT * FROM generator_config WHERE config_id = ?", [$genConfigId]);
    $instructionsArr = json_decode($genConfigFull['instructions'], true) ?? [];
    $systemPrompt = $genConfigFull['system_role'] . "\n\n" . implode("\n", $instructionsArr);
    $model = $genConfigFull['model'] && $genConfigFull['model'] !== 'openai' ? $genConfigFull['model'] : AIProvider::getDefaultModel();


    // 5. TAGS SELECTION (New Step)
    // --------------------------------------------------------
    printHeader("Tagging");
    
    $defaultKeywords = trim($selectedDoc['keywords'] ?? '');
    echo "The document has these keywords: \n> " . ($defaultKeywords ?: "(None)") . "\n";
    
    echo "\nEnter tags for these sketches (comma separated).\n";
    echo "Press ENTER to use document defaults.\n";
    $tagsInput = input("Tags: ");
    
    if (trim($tagsInput) === '') {
        $tagsInput = $defaultKeywords;
    }

    // Process Tags Immediately (Resolve to IDs)
    $finalTagIds =[];
    if (!empty($tagsInput)) {
        $rawTags = explode(',', $tagsInput);
        echo "\nResolving tags...\n";
        foreach ($rawTags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            // Check if tag exists
            $tagId = $conn->fetchOne("SELECT id FROM tags WHERE name = ?", [$tagName]);
            
            if (!$tagId) {
                // Create new tag
                echo "- Creating new tag: [$tagName]\n";
                $conn->insert('tags',[
                    'name' => $tagName,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'show_in_ui' => 1
                ]);
                $tagId = $conn->lastInsertId();
            } else {
                echo "- Found existing tag: [$tagName]\n";
            }
            $finalTagIds[] = $tagId;
        }
    }
    echo "Mapped " . count($finalTagIds) . " tags for this batch.\n";


    // 6. PROCESSING LOOP
    // --------------------------------------------------------
    printHeader("Starting Generation...");
    
    $confirm = input("Ready to generate " . count($processList) . " sketches? [y/N]: ");
    if (strtolower($confirm) !== 'y') die("Aborted.\n");

    $count = 0;
    $sqlSketch = "INSERT INTO sketches 
        (`name`, `description`, `created_at`, `updated_at`, `order`, `regenerate_images`, `img2img`, `cnmap`) 
        VALUES (:name, :desc, :created, :updated, 0, 0, 0, 0)";

    foreach ($processList as $index => $item) {
        $count++;
        
        $name = '';
        if (is_array($item)) {
            $name = $item['name'] ?? ($item['title'] ?? ($item['event'] ?? 'Unknown'));
        } else {
            $name = (string)$item;
        }

        // --- UNIQUE NAME GENERATION ---
        $baseName = substr($name, 0, 100);
        $uniqueName = $baseName;
        $counter = 1;

        while ($conn->fetchOne("SELECT id FROM sketches WHERE name = ?", [$uniqueName])) {
            $counter++;
            $suffix = " ($counter)";
            $maxBaseLen = 100 - strlen($suffix);
            $uniqueName = substr($baseName, 0, $maxBaseLen) . $suffix;
        }

        echo "\n[$count/" . count($processList) . "] Processing: $uniqueName ... ";

        // A. Build Context
        $contextData = $loreService->buildAgentContext($name);
        $contextStr = "";
        
        if ($contextData) {
            $contextStr = "IDENTITY:\n" . json_encode($contextData['identity'], JSON_UNESCAPED_UNICODE) . "\n";
            $contextStr .= "ATTRIBUTES:\n" . json_encode($contextData['identity']['core_attributes'] ??[], JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $contextStr = "DETAILS:\n" . json_encode($item, JSON_UNESCAPED_UNICODE);
        }

        // B. Generate
        $userPrompt = "Please generate a rich, visual sketch description for the following entity.\n\nENTITY: $name\nTYPE: $catKey\n\nCONTEXT:\n$contextStr";
        
        try {
            $rawOutput = $ai->sendPrompt($model, $userPrompt, $systemPrompt,['temperature' => 0.7]);
            
            if (empty($rawOutput)) {
                echo "Failed (Empty API Response).\n";
                continue;
            }

            $finalText = cleanAIOutput($rawOutput, $targetSchemaKey);

            // C. Insert Sketch
            $conn->executeStatement($sqlSketch,[
                'name' => $uniqueName,
                'desc' => $finalText,
                'created' => date('Y-m-d H:i:s'),
                'updated' => date('Y-m-d H:i:s')
            ]);
            $newSketchId = $conn->lastInsertId();

            // D. Insert History
            $conn->insert('sketch_lore_history',[
                'sketch_id' => $newSketchId,
                'doc_id' => $selectedDoc['id'],
                'entity_type' => $catKey,
                'entity_name' => substr($name, 0, 255),
                'generator_config_id' => $genConfigFull['id'] ?? 0,
                'prompt_used' => substr($userPrompt, 0, 2000),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // E. Link Tags
            foreach ($finalTagIds as $tid) {
                // Use INSERT IGNORE via straight SQL to be safe against race conditions
                $conn->executeStatement(
                    "INSERT IGNORE INTO tags_2_sketches (from_id, to_id) VALUES (?, ?)", 
                    [$tid, $newSketchId]
                );
            }

            echo "Done (ID: $newSketchId)";

        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
        
        usleep(500000); 
    }

    printHeader("Batch Complete");
    echo "Generated $count sketches.\n";

} catch (Exception $e) {
    echo "\nCRITICAL ERROR: " . $e->getMessage() . "\n";
    if (isset($conn)) $conn->close();
}