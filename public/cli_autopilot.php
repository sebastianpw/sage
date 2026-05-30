<?php
// public/cli_autopilot.php
// Infinite Creative Autopilot for Sketches
// Usage: 
//   Interactive: php public/cli_autopilot.php
//   Batch:       php public/cli_autopilot.php [GenID] [Amount] [MaxChars] [MaxHops] [MaxRefs] [ContGenID]

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\AIProvider;

// --- DEFAULTS ---
const DEFAULT_NAME_GEN_HASH = '9bf6de291765e2ced28589de857a9f0b'; 
const DEFAULT_CONT_GEN_ID   = 126;

// ANSI Colors
const C_RESET   = "\033[0m";
const C_RED     = "\033[31m";
const C_GREEN   = "\033[32m";
const C_YELLOW  = "\033[33m";
const C_BLUE    = "\033[34m";
const C_CYAN    = "\033[36m";
const C_GRAY    = "\033[90m";
const C_WHITE   = "\033[1m";

// --- INIT ---
$em = $spw->getEntityManager();
$conn = $em->getConnection();
$repo = $em->getRepository(GeneratorConfig::class);

$logger = $spw->getFileLogger();
$aiProvider = $spw->getAIProvider(); 
if (!$aiProvider) $aiProvider = new AIProvider($logger);

$generatorService = new GeneratorService(
    $aiProvider, new SchemaValidator(), new ResponseNormalizer(), $logger
);

function getRapidGenerators($conn) {
    $sql = "
        SELECT g.id, g.title, g.model 
        FROM generator_config g
        JOIN generator_config_to_display_area map ON g.id = map.generator_config_id
        JOIN generator_config_display_area da ON map.display_area_id = da.id
        WHERE da.area_key = 'rapidcreate' AND g.active = 1
        ORDER BY g.title ASC
    ";
    return $conn->fetchAllAssociative($sql);
}

function ensureConfigRevision($conn, GeneratorConfig $config) {
    if (!$config) return null;
    $snapshot = [
        'system_role'   => $config->getSystemRole(),
        'instructions'  => $config->getInstructions(),
        'parameters'    => $config->getParameters(),
        'output_schema' => $config->getOutputSchema(),
        'oracle_config' => $config->getOracleConfig(),
        'model'         => $config->getModel()
    ];
    $jsonSnapshot = json_encode($snapshot);
    $hash = md5($jsonSnapshot);

    $hStmt = $conn->prepare("SELECT id FROM generator_config_history WHERE generator_config_id = ? AND config_hash = ?");
    $hStmt->bindValue(1, $config->getId());
    $hStmt->bindValue(2, $hash);
    $hResult = $hStmt->executeQuery();
    $existing = $hResult->fetchOne();

    if ($existing) return ['db_id' => $config->getId(), 'history_id' => $existing];

    $iStmt = $conn->prepare("INSERT INTO generator_config_history (generator_config_id, config_hash, snapshot_data, created_at) VALUES (?, ?, ?, NOW())");
    $iStmt->bindValue(1, $config->getId());
    $iStmt->bindValue(2, $hash);
    $iStmt->bindValue(3, $jsonSnapshot);
    $iStmt->executeStatement();

    return ['db_id' => $config->getId(), 'history_id' => $conn->lastInsertId()];
}

function fetchRandomRow($conn, $table) {
    return $conn->fetchAssociative("SELECT * FROM `$table` ORDER BY RAND() LIMIT 1");
}



function fetchRandomIngredientRow($conn, $table) {
    try {
        return $conn->fetchAssociative("SELECT * FROM `$table` WHERE is_ingredient = 1 ORDER BY RAND() LIMIT 1");
    } catch (\Throwable $e) {
        throw new \Exception("[Table: $table] " . $e->getMessage());
    }
}

function fetchRandomIngredientRows($conn, $table, $limit) {
    $limit = (int)$limit;
    try {
        return $conn->fetchAllAssociative("SELECT * FROM `$table` WHERE is_ingredient = 1 ORDER BY RAND() LIMIT $limit");
    } catch (\Throwable $e) {
        throw new \Exception("[Table: $table] " . $e->getMessage());
    }
}



function generateWithRetry($service, $config, $params, $maxTries = 3) {
    for ($i = 1; $i <= $maxTries; $i++) {
        try {
            $res = $service->generate($config, $params);
            if ($res->isSuccess()) return $res;
        } catch (\Throwable $e) {}
        
        if ($i < $maxTries) {
            echo C_YELLOW . " (Retry $i/$maxTries)... " . C_RESET;
            sleep(2);
        }
    }
    throw new Exception("AI Generation failed after $maxTries attempts.");
}

function buildKgSubpot($conn, $maxHops, $maxRefs) {
    $startNode = $conn->fetchAssociative("SELECT * FROM kg_nodes WHERE status='active' ORDER BY RAND() LIMIT 1");
    if (!$startNode) return null;

    $nodes = [$startNode['id'] => $startNode];
    $frontier = [$startNode['id']];
    $edges = [];

    for ($h = 0; $h < $maxHops; $h++) {
        if (empty($frontier) || count($nodes) >= $maxRefs) break;
        
        $ph = implode(',', array_fill(0, count($frontier), '?'));
        $sql = "
            SELECT kni.relationship, kni.item_label, kn_src.name AS src_name, kn.* 
            FROM kg_node_items kni 
            JOIN kg_nodes kn ON kn.id = kni.item_id 
            JOIN kg_nodes kn_src ON kn_src.id = kni.node_id
            WHERE kni.item_type = 'kg_node' AND kni.node_id IN ($ph) AND kn.status = 'active'
        ";
        
        $neighbors = $conn->fetchAllAssociative($sql, $frontier);
        $newFrontier = [];
        
        foreach ($neighbors as $n) {
            $edges[] = "→ {$n['src_name']} " . ($n['relationship'] ?: 'relates to') . " {$n['item_label']}";
            if (!isset($nodes[$n['id']])) {
                $nodes[$n['id']] = $n;
                $newFrontier[] = $n['id'];
                if (count($nodes) >= $maxRefs) break 2;
            }
        }
        $frontier = $newFrontier;
    }

    $lines = ['[Knowledge Graph Subplot]'];
    foreach ($nodes as $n) {
        $desc = trim(strip_tags($n['description'] ?? ''));
        $line = "• [{$n['node_type']}] {$n['name']}";
        if ($desc) $line .= ": $desc";
        $lines[] = $line;
    }
    
    if (!empty($edges)) {
        $lines[] = '';
        $lines[] = '[Relationships]';
        $lines = array_merge($lines, array_unique($edges));
    }

    return [
        'nodes' => array_values($nodes),
        'text' => implode("\n", $lines)
    ];
}

function extractTextFromJsonData($data, $fallbackKey) {
    if (is_array($data)) {
        foreach (['scene_prompt', 'description', 'text', 'content', 'result', $fallbackKey] as $k) {
            if (!empty($data[$k]) && is_string($data[$k])) return $data[$k];
        }
        $first = reset($data);
        if (is_string($first)) return $first;
        return json_encode($data);
    }
    $cleaned = trim(preg_replace('/\{.*?\}/s', '', (string)$data));
    return $cleaned ?: (string)$data;
}

// --- CONFIGURATION & INPUT ---

// 1. Define Ingredient Probabilities (Key => [Label, Default%])
$ingredientsConfig = [
    'template'    => ['Sketch Templates', 85],
    'interaction' => ['Interactions',     60],
    'style'       => ['Style Profiles',   50],
    'anivoc'      => ['Anime Visual Vocab', 70],
    'character'   => ['Characters',       40],
    'location'    => ['Locations',        30],
    'vehicle'     => ['Vehicles',         20],
    'artifact'    => ['Artifacts',        15],
    'kg_subpot'   => ['KG Graph Subpots', 25],
    'continuity'  => ['Auto Continuity (if chars)', 100],
];

$descGenId = null;
$contGenId = DEFAULT_CONT_GEN_ID;
$amount = 0; 
$probs = []; // Holds final probabilities

$maxCharacters = 3; 
$maxHops = 1;
$maxRefs = 3;

$args = array_slice($argv, 1);

// 2. INTERACTIVE MODE
if (empty($args)) {
    echo "\n" . C_CYAN . "==========================================" . C_RESET . "\n";
    echo C_CYAN . "   🤖 SKETCH AUTOPILOT (Kitchen Edition)" . C_RESET . "\n";
    echo C_CYAN . "==========================================" . C_RESET . "\n\n";

    // Select Generator
    $gens = getRapidGenerators($conn);
    if (empty($gens)) die(C_RED . "No generators found tagged with 'rapidcreate'.\n" . C_RESET);

    echo C_WHITE . "Available Generators:" . C_RESET . "\n";
    foreach ($gens as $gen) {
        printf("  [" . C_GREEN . "%3d" . C_RESET . "] %s " . C_GRAY . "(%s)" . C_RESET . "\n", $gen['id'], $gen['title'], $gen['model']);
    }
    echo "\n";

    while (!$descGenId) {
        $input = readline("Select Generator ID: ");
        if (is_numeric($input)) {
            foreach ($gens as $g) { if ($g['id'] == $input) $descGenId = (int)$input; }
        }
        if (!$descGenId) echo C_RED . "Invalid ID. Try again.\n" . C_RESET;
    }

    // Select Amount
    $inputAmt = readline("How many sketches? (0 for infinite): ");
    $amount = (int)$inputAmt;

    echo "\n" . C_WHITE . "Ingredient Probabilities (Press Enter for Default):" . C_RESET . "\n";
    
    // Dynamic Probability Questions
    foreach ($ingredientsConfig as $key => $conf) {
        $label = $conf[0];
        $default = $conf[1];
        
        $input = readline(sprintf("  %-28s [Default %3d%%]: ", $label, $default));
        $val = (trim($input) !== "" && is_numeric($input)) ? max(0, min(100, (int)$input)) : $default;
        $probs[$key] = $val;

        if ($key === 'character') {
            $inputMaxChars = readline(sprintf("    ↳ %-24s [Default %3d] : ", "Max Characters", 3));
            $maxCharacters = (trim($inputMaxChars) !== "" && is_numeric($inputMaxChars)) ? max(1, (int)$inputMaxChars) : 3;
        }
        
        if ($key === 'kg_subpot' && $probs['kg_subpot'] > 0) {
            $inHops = readline(sprintf("    ↳ %-24s [Default %3d] : ", "Graph Hops Depth", 1));
            $maxHops = (trim($inHops) !== "" && is_numeric($inHops)) ? max(1, (int)$inHops) : 1;
            
            $inRefs = readline(sprintf("    ↳ %-24s [Default %3d] : ", "Max KG References", 3));
            $maxRefs = (trim($inRefs) !== "" && is_numeric($inRefs)) ? max(1, (int)$inRefs) : 3;
        }

        if ($key === 'continuity' && $probs['continuity'] > 0) {
            $inCont = readline(sprintf("    ↳ %-24s [Default %3d] : ", "Continuity Gen ID", DEFAULT_CONT_GEN_ID));
            $contGenId = (trim($inCont) !== "" && is_numeric($inCont)) ? (int)$inCont : DEFAULT_CONT_GEN_ID;
        }
    }

} else {
    // 3. BATCH MODE (Defaults)
    $descGenId     = (int)$args[0];
    $amount        = isset($args[1]) ? (int)$args[1] : 0;
    $maxCharacters = isset($args[2]) ? max(1, (int)$args[2]) : 3;
    $maxHops       = isset($args[3]) ? max(1, (int)$args[3]) : 1;
    $maxRefs       = isset($args[4]) ? max(1, (int)$args[4]) : 3;
    $contGenId     = isset($args[5]) ? (int)$args[5] : DEFAULT_CONT_GEN_ID;
    
    foreach ($ingredientsConfig as $key => $conf) {
        $probs[$key] = $conf[1]; // Use defaults
    }
    
    echo "\n" . C_CYAN . "🤖 SKETCH AUTOPILOT (Batch Mode)" . C_RESET . "\n";
}

// --- VALIDATION ---
$descConfig = $repo->find($descGenId);
if (!$descConfig) die(C_RED . "Error: Generator ID $descGenId not found.\n" . C_RESET);

$contConfig = $repo->find($contGenId);
if (!$contConfig && $probs['continuity'] > 0) die(C_RED . "Error: Continuity Generator ID $contGenId not found.\n" . C_RESET);

$nameConfig = $repo->findOneBy(['configId' => DEFAULT_NAME_GEN_HASH]);
if (!$nameConfig) die(C_RED . "Error: Default Name Generator (Hash) not found.\n" . C_RESET);
$nameGenId = $nameConfig->getId();

echo C_GRAY . "------------------------------------------" . C_RESET . "\n";
echo " Config:      " . C_WHITE . $descConfig->getTitle() . C_RESET . "\n";
echo " Target:      " . C_WHITE . ($amount > 0 ? "$amount sketches" : "Infinite") . C_RESET . "\n";
echo " Ingredients: ";
foreach ($probs as $k => $v) { echo "$k=" . C_YELLOW . "$v%" . C_RESET . " "; }
echo "\n" . C_GRAY . "------------------------------------------" . C_RESET . "\n\n";

if ($amount === 0) echo C_YELLOW . "Starting Infinite Loop... (Ctrl+C to stop)" . C_RESET . "\n\n";

// --- EXECUTION LOOP ---
$counter = 0;

while (true) {
    if ($amount > 0 && $counter >= $amount) {
        echo C_GREEN . "\n✅ Target of $amount sketches reached. Exiting.\n" . C_RESET;
        break;
    }

    $counter++;
    $progressStr = ($amount > 0) ? "[$counter/$amount]" : "[$counter]";
    echo C_BLUE . "$progressStr Generating..." . C_RESET . "\n";

    try {
        // --- 1. GATHER INGREDIENTS ---
        $ingredients = [];
        $contextParts = [];
        $logParts = [];
        $pickedCharacters = [];
        
        $legacyTemplateId = null;
        $legacyInteractionId = null;

        // A. Template
        if (rand(1, 100) <= $probs['template']) {
            $row = fetchRandomIngredientRow($conn, 'sketch_templates');
            if ($row) {
                $legacyTemplateId = (int)$row['id'];
                $prompt = $row['example_prompt'] . (!empty($row['core_idea']) ? " (Core: {$row['core_idea']})" : "");
                $ingredients[] = ['type' => 'sketch_template', 'id' => (int)$row['id'], 'prompt' => $prompt, 'snapshot' => $row];
                $contextParts[] = "Visual Style/Template: " . $prompt;
                $logParts[] = "Tpl";
            }
        }

        // B. Interaction
        if (rand(1, 100) <= $probs['interaction']) {
            $row = fetchRandomIngredientRow($conn, 'interactions');
            if ($row) {
                $legacyInteractionId = (int)$row['id'];
                $prompt = $row['example_prompt'] . " ({$row['description']})";
                $ingredients[] = ['type' => 'interaction', 'id' => (int)$row['id'], 'prompt' => $prompt, 'snapshot' => $row];
                $contextParts[] = "Interaction: " . $prompt;
                $logParts[] = "Int";
            }
        }

        // C. Style Profile
        if (rand(1, 100) <= $probs['style']) {
            $row = $conn->fetchAssociative("SELECT * FROM style_profiles WHERE convert_result IS NOT NULL AND convert_result != '' AND is_ingredient = 1 ORDER BY RAND() LIMIT 1");
            if ($row) {
                $prompt = "Visual Style: " . $row['convert_result'];
                $json = json_decode($row['convert_result'], true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($json['textualStylePrompt'])) {
                    $prompt = "Visual Style: " . $json['textualStylePrompt'];
                }
                $ingredients[] = ['type' => 'style_profile', 'id' => (int)$row['id'], 'prompt' => $prompt, 'snapshot' => $row];
                $contextParts[] = $prompt;
                $logParts[] = "Style";
            }
        }

        // D. Anime Visual Vocabulary
        if (rand(1, 100) <= $probs['anivoc']) {
            $catRow = fetchRandomRow($conn, 'anivoc_categories');
            if ($catRow) {
                $tName = $catRow['table_name'];
                $row = fetchRandomIngredientRow($conn, $tName);
                if ($row) {
                    $prompt = $row['name'] . " - " . strip_tags($row['description'] ?? '');
                    $ingredients[] = ['type' => $tName, 'id' => (int)$row['id'], 'prompt' => $prompt, 'snapshot' => $row];
                    $contextParts[] = "Visual Element ({$catRow['name']}): " . $prompt;
                    $logParts[] = "AniVoc";
                }
            }
        }

        // E. KG Subpot
        if (rand(1, 100) <= $probs['kg_subpot']) {
            $subpot = buildKgSubpot($conn, $maxHops, $maxRefs);
            if ($subpot) {
                $nodeIds = array_map(fn($n) => $n['id'], $subpot['nodes']);
                $subpotId = 'kg_' . implode('_', $nodeIds);
                $label = 'KG Subplot: ' . implode(', ', array_map(fn($n) => $n['name'], $subpot['nodes']));
                $ingredients[] = [
                    'type' => '_kg_subpot', 'id' => $subpotId, 'prompt' => $subpot['text'], 
                    'snapshot' => ['label' => $label, 'icon' => '🌳']
                ];
                $contextParts[] = $subpot['text'];
                $logParts[] = "KG(" . count($subpot['nodes']) . ")";
            }
        }

        // F. Entities (Location, Vehicle, Artifact)
        $entityTypes = ['location' => 'locations', 'vehicle' => 'vehicles', 'artifact' => 'artifacts'];
        foreach ($entityTypes as $key => $table) {
            if (rand(1, 100) <= $probs[$key]) {
                $row = fetchRandomIngredientRow($conn, $table);
                if ($row) {
                    $desc = trim(strip_tags($row['description'] ?? ''));
                    $prompt = ucfirst($key) . ": " . $row['name'] . ($desc ? " - $desc" : "");
                    $ingredients[] = ['type' => $table, 'id' => (int)$row['id'], 'prompt' => $prompt, 'snapshot' => $row];
                    $contextParts[] = $prompt;
                    $logParts[] = ucfirst($key);
                }
            }
        }

        // G. Characters
        if (rand(1, 100) <= $probs['character']) {
            $numChars = rand(1, $maxCharacters);
            $charRows = fetchRandomIngredientRows($conn, 'characters', $numChars);
            foreach ($charRows as $row) {
                $pickedCharacters[] = $row;
                $desc = trim(strip_tags($row['description'] ?? ''));
                $prompt = "Character: " . $row['name'] . ($desc ? " - $desc" : "");
                $ingredients[] = ['type' => 'characters', 'id' => (int)$row['id'], 'prompt' => $prompt, 'snapshot' => $row];
                $contextParts[] = $prompt;
                $logParts[] = "Character";
            }
        }

        if (empty($contextParts)) {
            $contextParts[] = "Theme: A purely random scene.";
            $logParts[] = "Pure Random";
        }

        $finalContext = implode("\n\n", $contextParts);
        echo "    Recipe:  " . C_GRAY . implode(" + ", $logParts) . C_RESET . "\n";

        // --- 2. GENERATE RAW DESCRIPTION ---
        echo "    ⚡ Desc... ";
        $descResult = generateWithRetry($generatorService, $descConfig, [
            'entity_name' => $finalContext,
            'random_seed' => rand(1, 9999999)
        ], 3);

        $rawDescription = extractTextFromJsonData($descResult->getData(), 'description');
        echo C_GREEN . "OK" . C_RESET . "\n";

        $finalDescription = $rawDescription;

        // --- 3. AUTO CONTINUITY ---
        if (!empty($pickedCharacters) && rand(1, 100) <= $probs['continuity']) {
            echo "    ⚡ Continuity... ";
            
            $charContext = "";
            foreach ($pickedCharacters as $c) {
                $charDesc = trim(strip_tags($c['description'] ?? ''));
                $charContext .= "CHARACTER: {$c['name']}\n{$charDesc}\n\n";
            }

            $continuityPrompt = "You are a cinematic scene compiler. Your task is to rewrite a scene description so that the specified characters appear with their exact appearance as described, while preserving the full cinematic dynamism and action of the original scene.\n\n"
            . "CRITICAL RULES:\n"
            . "- Keep the original scene energy, action, and visual drama INTACT\n"
            . "- Do NOT reduce the scene to a static or posed composition\n"
            . "- Characters must match their exact physical descriptions below\n"
            . "- Preserve all environmental details, lighting, scale, and atmosphere from the original\n"
            . "- Place characters naturally within the scene's action, not posed for a portrait\n"
            . "- The scene prompt goes LAST in your response for maximum AI impact\n\n"
            . "CHARACTER REFERENCE (Use these EXACT descriptions):\n"
            . $charContext
            . "\n\n---\n\n"
            . "ORIGINAL SCENE TO REWRITE:\n"
            . $rawDescription
            . "\n\n---\n\n"
            . "Rewrite the scene with the characters above integrated naturally into the action. Return ONLY the final scene description as JSON: {\"scene_prompt\": \"...\"}";

            $contResult = generateWithRetry($generatorService, $contConfig, [
                'entity_name' => $continuityPrompt,
                'random_seed' => rand(1, 9999999)
            ], 3);

            $parsedCont = extractTextFromJsonData($contResult->getData(), 'scene_prompt');
            if (strlen($parsedCont) > 50) {
                $finalDescription = str_replace(["\u{2014}", "—"], "", $parsedCont);
                echo C_GREEN . "Applied" . C_RESET . "\n";
            } else {
                echo C_YELLOW . "Skipped (Invalid response)" . C_RESET . "\n";
            }
        }

        // --- 4. GENERATE NAME ---
        echo "    ⚡ Name... ";
        $nameContext = $finalDescription;
        if (!empty($logParts)) $nameContext .= " (" . implode(" + ", $logParts) . ")";

        $nameResult = generateWithRetry($generatorService, $nameConfig, [
            'entity_name' => $nameContext, 
            'entity_type' => 'sketch',
            'random_seed' => rand(1, 9999999)
        ], 3);

        $name = extractTextFromJsonData($nameResult->getData(), 'name');
        $name = trim($name, '"\'');
        echo C_GREEN . "OK: $name" . C_RESET . "\n";

        // --- 5. SAVE SKETCH ---
        echo "    💾 Saving... ";
        $conn->beginTransaction();
        
        try {
            // A. Insert Sketch
            $sql = "INSERT INTO sketches (name, description, description_raw, `order`, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(1, $name);
            $stmt->bindValue(2, $finalDescription);
            $stmt->bindValue(3, $rawDescription);
            $stmt->bindValue(4, 0);
            $stmt->bindValue(5, (new DateTime())->format('Y-m-d H:i:s'));
            $stmt->bindValue(6, (new DateTime())->format('Y-m-d H:i:s'));
            $stmt->executeStatement();
            $newId = $conn->lastInsertId();

            // B. Prepare Config Revisions
            $descRev = ensureConfigRevision($conn, $descConfig);
            $nameRev = ensureConfigRevision($conn, $nameConfig);

            // C. Insert Flexible Ingredients
            $iStmt = $conn->prepare("INSERT INTO sketch_ingredients (sketch_id, ingredient_type, source_id, prompt_fragment, snapshot_data, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $orderIdx = 0;

            $iStmt->executeStatement([$newId, 'generator_config_desc', $descGenId, 'Used for description', null, $orderIdx++]);
            $iStmt->executeStatement([$newId, 'generator_config_name', $nameGenId, 'Used for name', null, $orderIdx++]);

            foreach ($ingredients as $ing) {
                $type = $ing['type'];
                $id = ($type === '_kg_subpot') ? null : (int)$ing['id'];
                
                $iStmt->executeStatement([
                    $newId,
                    $type,
                    $id,
                    $ing['prompt'],
                    json_encode($ing['snapshot']),
                    $orderIdx++
                ]);
            }

            // D. Insert Legacy Meta
            $metaSql = "INSERT INTO meta_sketches 
                (sketch_id, desc_gen_config_id, desc_gen_history_id, name_gen_config_id, name_gen_history_id, sketch_template_id, interaction_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $mStmt = $conn->prepare($metaSql);
            $mStmt->bindValue(1, $newId);
            $mStmt->bindValue(2, $descRev ? $descRev['db_id'] : $descGenId);
            $mStmt->bindValue(3, $descRev ? $descRev['history_id'] : null);
            $mStmt->bindValue(4, $nameRev ? $nameRev['db_id'] : $nameGenId);
            $mStmt->bindValue(5, $nameRev ? $nameRev['history_id'] : null);
            $mStmt->bindValue(6, $legacyTemplateId);
            $mStmt->bindValue(7, $legacyInteractionId);
            $mStmt->executeStatement();

            $conn->commit();
            echo C_GREEN . "Saved ID: $newId" . C_RESET . "\n";

        } catch (Exception $saveEx) {
            $conn->rollBack();
            throw $saveEx;
        }

        // --- 6. COOLDOWN ---
        if ($counter < $amount || $amount === 0) {
            echo C_GRAY . "    Cooling down (2s)..." . C_RESET . "\n\n";
            sleep(2);
        }

    } catch (Exception $e) {
        if ($conn->isTransactionActive()) $conn->rollBack();
        echo C_RED . "\n    ❌ ERROR: " . $e->getMessage() . C_RESET . "\n";
        sleep(5); 
    }
}