<?php
// public/cli_kg_to_sketch_generator.php
// CLI Tool: Automates conversion of Knowledge Graph Nodes into Visual Sketches via AI
// Run via: php public/cli_kg_to_sketch_generator.php

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Core/AIProvider.php';

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
    echo sprintf("[%d] %-35s %s\n", $id, substr($label, 0, 35), $info);
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

// Helper: fetch family of category IDs (category + all descendants)
function getKgCatFamily($conn, $topCatId) {
    if ($topCatId <= 0) return[];
    $allCats = $conn->fetchAllAssociative("SELECT id, parent_id FROM kg_categories");
    $childrenMap =[];
    foreach ($allCats as $c) {
        $childrenMap[$c['parent_id'] ?? 0][] = $c['id'];
    }
    $family =[(int)$topCatId];
    $queue =[(int)$topCatId];
    while (!empty($queue)) {
        $curr = array_shift($queue);
        if (isset($childrenMap[$curr])) {
            foreach ($childrenMap[$curr] as $childId) {
                $family[] = $childId;
                $queue[] = $childId;
            }
        }
    }
    return $family;
}

// Helper: count new/hist for a given node list by entity_name + entity_type
function getHistCounts($conn, array $nodeNames, $entityType) {
    if (empty($nodeNames)) return ['new' => 0, 'hist' => 0];
    $placeholders = implode(',', array_fill(0, count($nodeNames), '?'));
    $params = array_merge($nodeNames, [$entityType]);
    $histNames = $conn->fetchFirstColumn(
        "SELECT DISTINCT entity_name FROM sketch_lore_history WHERE entity_name IN ($placeholders) AND entity_type = ?",
        $params
    );
    $histSet = array_flip($histNames);
    $hist = 0;
    foreach ($nodeNames as $n) {
        if (isset($histSet[$n])) $hist++;
    }
    return ['hist' => $hist, 'new' => count($nodeNames) - $hist];
}

// --- MAIN LOGIC ---

try {
    global $spw;
    $conn = $spw->getEntityManager()->getConnection();
    $ai = new AIProvider();

    printHeader("KG Node -> Sketch Auto-Generator");

    // 1. SELECT MODE
    // --------------------------------------------------------
    echo "[1] By Category (Filter by KG Folder, then select Node Type)\n";
    echo "[2] By Node Type (Process all nodes of a specific type globally)\n";
    
    $modeIndex = (int)input("\nSelect Mode[1 or 2]: ");
    if (!in_array($modeIndex, [1, 2])) {
        die("Invalid mode selection.\n");
    }

    $selectedCatId = 0;
    $selectedType = '';
    $where = "WHERE status = 'active'";

    if ($modeIndex === 1) {
        // MODE 1: BY CATEGORY
        $cats = $conn->fetchAllAssociative("SELECT id, name FROM kg_categories ORDER BY name ASC");
        array_unshift($cats,['id' => 0, 'name' => '-- ALL KNOWLEDGE GRAPH CATEGORIES --']);

        printHeader("Select KG Category");
        foreach ($cats as $i => $c) {
            $catId = (int)$c['id'];
            if ($catId > 0) {
                $fam = getKgCatFamily($conn, $catId);
                $famIn = implode(',', $fam);
                $catTotal = (int)$conn->fetchOne("SELECT COUNT(*) FROM kg_nodes WHERE status = 'active' AND category_id IN ($famIn)");
                $catNodeNames = $conn->fetchFirstColumn("SELECT name FROM kg_nodes WHERE status = 'active' AND category_id IN ($famIn)");
                // For new/hist we need a type-agnostic check: a node is "hist" if entity_name exists in sketch_lore_history at all
                $placeholders = implode(',', array_fill(0, count($catNodeNames), '?'));
                $histCount = empty($catNodeNames) ? 0 : (int)$conn->fetchOne(
                    "SELECT COUNT(DISTINCT entity_name) FROM sketch_lore_history WHERE entity_name IN ($placeholders)",
                    $catNodeNames
                );
                $newCount = $catTotal - $histCount;
                printRow($i + 1, $c['name']);
                echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $catTotal, $newCount, $histCount);
            } else {
                // "ALL" entry — sum across everything
                $catTotal = (int)$conn->fetchOne("SELECT COUNT(*) FROM kg_nodes WHERE status = 'active'");
                $allNodeNames = $conn->fetchFirstColumn("SELECT name FROM kg_nodes WHERE status = 'active'");
                $placeholders = implode(',', array_fill(0, count($allNodeNames), '?'));
                $histCount = empty($allNodeNames) ? 0 : (int)$conn->fetchOne(
                    "SELECT COUNT(DISTINCT entity_name) FROM sketch_lore_history WHERE entity_name IN ($placeholders)",
                    $allNodeNames
                );
                $newCount = $catTotal - $histCount;
                printRow($i + 1, $c['name']);
                echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $catTotal, $newCount, $histCount);
            }
        }

        $catIndex = (int)input("\nSelect Category Number: ") - 1;
        if (!isset($cats[$catIndex])) die("Invalid selection.\n");

        $selectedCat = $cats[$catIndex];
        $selectedCatId = (int)$selectedCat['id'];
        echo "Selected Context: " . $selectedCat['name'] . "\n";

        if ($selectedCatId > 0) {
            $family = getKgCatFamily($conn, $selectedCatId);
            $where .= " AND category_id IN (" . implode(',', $family) . ")";
        }

        $types = $conn->fetchAllAssociative("SELECT node_type, COUNT(*) as cnt FROM kg_nodes $where GROUP BY node_type ORDER BY cnt DESC");
        if (empty($types)) die("No active nodes found in this category.\n");

        // Compute new/hist counts per type for category mode
        printHeader("Select Node Type in Category");
        foreach ($types as $i => $t) {
            $nodeNames = $conn->fetchFirstColumn(
                "SELECT name FROM kg_nodes $where AND node_type = ?",
                [$t['node_type']]
            );
            $hc = getHistCounts($conn, $nodeNames, $t['node_type']);
            printRow($i + 1, ucfirst($t['node_type']));
            echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $t['cnt'], $hc['new'], $hc['hist']);
        }

        $typeIndex = (int)input("\nSelect Type Number: ") - 1;
        if (!isset($types[$typeIndex])) die("Invalid selection.\n");

        $selectedType = $types[$typeIndex]['node_type'];

    } else {
        // MODE 2: BY GLOBAL NODE TYPE
        $types = $conn->fetchAllAssociative("SELECT node_type, COUNT(*) as cnt FROM kg_nodes WHERE status = 'active' GROUP BY node_type ORDER BY cnt DESC");
        if (empty($types)) die("No active nodes found in the Knowledge Graph.\n");

        printHeader("Select Global Node Type");
        foreach ($types as $i => $t) {
            $nodeNames = $conn->fetchFirstColumn(
                "SELECT name FROM kg_nodes WHERE status = 'active' AND node_type = ?",
                [$t['node_type']]
            );
            $hc = getHistCounts($conn, $nodeNames, $t['node_type']);
            printRow($i + 1, ucfirst($t['node_type']));
            echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $t['cnt'], $hc['new'], $hc['hist']);
        }

        $typeIndex = (int)input("\nSelect Type Number: ") - 1;
        if (!isset($types[$typeIndex])) die("Invalid selection.\n");

        $selectedType = $types[$typeIndex]['node_type'];
        $selectedCatId = 0; // Represents "Global"
    }


    // 3. DEFINE RANGE
    // --------------------------------------------------------
    $sql = "SELECT * FROM kg_nodes $where AND node_type = :type ORDER BY name ASC";
    $entityList = $conn->fetchAllAssociative($sql, ['type' => $selectedType]);

    $total = count($entityList);
    $scopeDesc = $modeIndex === 1 ? "in selected category" : "globally";

    // Compute new/hist for the full list
    $allNames = array_column($entityList, 'name');
    $hcFull = getHistCounts($conn, $allNames, $selectedType);
    echo "\nFound {$total} '{$selectedType}' nodes {$scopeDesc}.  [new: {$hcFull['new']}] [hist: {$hcFull['hist']}]\n";

    // 3a. SELECT HISTORY FILTER
    // --------------------------------------------------------
    echo "\nProcess which nodes?\n";
    echo "[1] new  - Only nodes without a sketch_lore_history entry (default)\n";
    echo "[2] all  - All nodes regardless of history\n";
    echo "[3] hist - Only nodes that already have a sketch_lore_history entry\n";

    $filterInput = trim(input("Select filter [1/2/3, default 1]: "));
    if ($filterInput === '') $filterInput = '1';
    if (!in_array($filterInput, ['1', '2', '3'])) die("Invalid filter selection.\n");

    // Build the history-filtered list
    $histNames = $conn->fetchFirstColumn(
        "SELECT DISTINCT entity_name FROM sketch_lore_history WHERE entity_type = ?",
        [$selectedType]
    );
    $histSet = array_flip($histNames);

    if ($filterInput === '1') {
        $filterLabel = 'new';
        $entityList = array_values(array_filter($entityList, fn($n) => !isset($histSet[$n['name']])));
    } elseif ($filterInput === '3') {
        $filterLabel = 'hist';
        $entityList = array_values(array_filter($entityList, fn($n) => isset($histSet[$n['name']])));
    } else {
        $filterLabel = 'all';
        // entityList unchanged
    }

    $total = count($entityList);
    echo "Filter: $filterLabel — {$total} nodes to consider.\n";

    if ($total === 0) die("No nodes match the selected filter. Nothing to process.\n");

    $offset = (int)input("Starting Offset (default 0): ");
    if ($offset < 0) $offset = 0;
    
    $amountInput = input("Amount to process (default All): ");
    $amount = ($amountInput === '') ? ($total - $offset) : (int)$amountInput;
    if ($amount <= 0 || $amount > ($total - $offset)) $amount = $total - $offset;

    $processList = array_slice($entityList, $offset, $amount);
    echo "\nQueue: Processing " . count($processList) . " nodes (from index $offset).\n";


    // 4. SELECT GENERATOR CONFIG
    // --------------------------------------------------------
    $configs = $conn->fetchAllAssociative("SELECT config_id, title, output_schema FROM generator_config WHERE active = 1 ORDER BY title ASC");
    
    printHeader("Select Generator Config");
    foreach ($configs as $i => $c) {
        printRow($i + 1, $c['title']);
    }

    $genConfigRow = null;
    while ($genConfigRow === null) {
        $cfgInput = trim(input("\nSelect Config Number (or 'l' to list queued nodes): "));
        if (strtolower($cfgInput) === 'l') {
            echo "\n--- Queued Nodes (" . count($processList) . ") ---\n";
            foreach ($processList as $idx => $n) {
                echo sprintf("  %4d. %s\n", $idx + 1, $n['name']);
            }
            echo "--- End of List ---\n";
            continue;
        }
        $cfgIndex = (int)$cfgInput - 1;
        if (!isset($configs[$cfgIndex])) {
            echo "Invalid selection, please try again.\n";
            continue;
        }
        $genConfigRow = $configs[$cfgIndex];
    }
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


    // 5. TAGS SELECTION
    // --------------------------------------------------------
    printHeader("Tagging");
    
    echo "Enter tags for these sketches (comma separated).\n";
    echo "Press ENTER to skip.\n";
    $tagsInput = input("Tags: ");
    
    $finalTagIds =[];
    if (trim($tagsInput) !== '') {
        $rawTags = explode(',', $tagsInput);
        echo "\nResolving tags...\n";
        foreach ($rawTags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            // Check if tag exists
            $tagId = $conn->fetchOne("SELECT id FROM tags WHERE name = ?",[$tagName]);
            
            if (!$tagId) {
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

    foreach ($processList as $index => $node) {
        $count++;
        $name = $node['name'];

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

        // A. Build Context directly from KG Node and Linked Items
        $items = $conn->fetchAllAssociative("SELECT * FROM kg_node_items WHERE node_id = ? ORDER BY sort_order ASC", [$node['id']]);
        
        $network =[];
        foreach ($items as $it) {
            $lbl = $it['item_label'] ?? ('ID:' . $it['item_id']);
            $relStr = $lbl . " (" . $it['item_type'] . ")";
            if ($it['relationship']) $relStr .= " - " . $it['relationship'];
            if ($it['note']) $relStr .= ": " . $it['note'];
            $network[] = $relStr;
        }

        $contextData = [
            'identity' =>[
                'name' => $node['name'],
                'type' => $node['node_type'],
                'description' => $node['description'] ?? '',
                'keywords' => $node['keywords'] ?? ''
            ]
        ];

        $contextStr = "IDENTITY:\n" . json_encode($contextData['identity'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n\n";
        if (!empty($network)) {
            $contextStr .= "RELATIONSHIPS:\n" . implode("\n", $network) . "\n\n";
        }
        if (!empty($node['content'])) {
            $contextStr .= "CONTENT:\n" . $node['content'];
        }

        // B. Generate
        $userPrompt = "Please generate a rich, visual sketch description for the following entity.\n\nENTITY: $name\nTYPE: $selectedType\n\nCONTEXT:\n$contextStr";
        
        try {
            $rawOutput = $ai->sendPrompt($model, $userPrompt, $systemPrompt, ['temperature' => 0.7]);
            
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

            // D. Insert History (Reusing doc_id as the selected category space for logging)
            $conn->insert('sketch_lore_history',[
                'sketch_id' => $newSketchId,
                'doc_id' => $selectedCatId, 
                'entity_type' => $selectedType,
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
        
        usleep(500000); // Politeness delay
    }

    printHeader("Batch Complete");
    echo "Generated $count sketches.\n";

} catch (Exception $e) {
    echo "\nCRITICAL ERROR: " . $e->getMessage() . "\n";
    if (isset($conn)) $conn->close();
}
