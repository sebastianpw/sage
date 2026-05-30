<?php
// public/cli_auto_curator.php
if (php_sapi_name() !== 'cli') die("CLI only");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\AIProvider;

// CONFIG
$limit           = isset($argv[1]) ? (int)$argv[1] : 1000; 
//$limit = 1000;
//$limit = 5; 
//$curatorConfigId = 'auto_curator_v1';
$curatorConfigId = 'auto_curator_sonnet_v1';

$em = $spw->getEntityManager();
$repo = $em->getRepository(GeneratorConfig::class);
$config = $repo->findOneBy(['configId' => $curatorConfigId]);

if (!$config) die("Error: Curator Generator Config ('$curatorConfigId') not found.\n");

$aiProvider = $spw->getAIProvider();
$service = new GeneratorService($aiProvider, new SchemaValidator(), new ResponseNormalizer(), $spw->getFileLogger());

echo "--- 🕵️ AUTO-CURATOR STARTED ---\n";

// 1. Find Unanalyzed Sketches
$sql = "
    SELECT s.id, s.name, s.description 
    FROM sketches s
    LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
    WHERE sa.id IS NULL
    ORDER BY s.id DESC
    LIMIT :limit
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$sketches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sketches)) die("✅ All sketches are currently analyzed.\n");

echo "Found " . count($sketches) . " sketches to analyze.\n\n";

foreach ($sketches as $sketch) {
    $id = $sketch['id'];
    echo "Processing Sketch #$id: " . substr($sketch['name'], 0, 30) . "... ";

    // 2. Fetch Ingredients
    $ingStmt = $pdo->prepare("SELECT ingredient_type, prompt_fragment FROM sketch_ingredients WHERE sketch_id = ?");
    $ingStmt->execute([$id]);
    $ingredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $contextParts = [];
    foreach ($ingredients as $ing) {
        $type = str_replace(['anivoc_', 'sketch_'], '', $ing['ingredient_type']);
        $contextParts[] = "[$type] " . $ing['prompt_fragment'];
    }
    $contextStr = implode("\n", $contextParts);

    // 3. Build Prompt (Reinforced with Schema)
    // We explicitly inject the schema into the user prompt to ensure the model sees it immediately
    $targetSchema = $config->getOutputSchema();
    $schemaJson = is_string($targetSchema) ? $targetSchema : json_encode($targetSchema, JSON_PRETTY_PRINT);

    $promptInput = <<<EOT
SCENE DESCRIPTION:
{$sketch['description']}

INGREDIENTS USED:
$contextStr

TASK:
Analyze this scene for a TV production pipeline. 
Do not critique the writing style. 
Focus on Narrative Function, Thematic Resonance, and Usage Utility.

REQUIRED OUTPUT FORMAT (JSON ONLY):
$schemaJson
EOT;

    try {
        // 4. Call AI
        $result = $service->generate($config, ['entity_name' => $promptInput]);
        $rawResponse = $result->getRawResponse();
        
        // --- MANUAL JSON REPAIR ---
        $jsonStr = $rawResponse;
        // Strip markdown blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $rawResponse, $m)) {
            $jsonStr = $m[1];
        } elseif (preg_match('/\{.*\}/s', $rawResponse, $m)) {
            $jsonStr = $m[0];
        }
        
        $data = json_decode($jsonStr, true);
        
        if (!$data && $result->isSuccess()) {
            $data = $result->getData();
        }

        if ($data) {
            // Extract sub-objects with safe fallbacks
            $entities = json_encode($data['entities'] ?? (object)[]);
            $classification = json_encode($data['classification'] ?? (object)[]);
            $scoring = json_encode($data['scoring'] ?? (object)[]);
            $thematics = json_encode($data['thematics'] ?? (object)[]);
            $recommendations = json_encode($data['recommendations'] ?? (object)[]);
            
            $score = $data['scoring']['overall_quality'] ?? 0;

            // 5. Save
            $ins = $pdo->prepare("
                INSERT INTO sketch_analysis 
                (sketch_id, entities, classification, scoring, thematics, recommendations, overall_quality, generator_config_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ins->execute([
                $id, $entities, $classification, $scoring, $thematics, $recommendations, 
                $score, $config->getId()
            ]);

            if ($score > 0) {
                echo "\033[32m[DONE] Score: $score\033[0m\n";
            } else {
                echo "\033[33m[SAVED] Score: 0 (Check Format)\033[0m\n";
                // Only print raw if it looks suspicious
                if(strlen($jsonStr) < 50) echo "    -> Raw: " . $jsonStr . "\n";
            }
        } else {
            echo "\033[31m[FAIL] Invalid JSON Response\033[0m\n";
            echo "    -> Raw Output: " . substr($rawResponse, 0, 500) . "...\n";
        }

        sleep(1); 

    } catch (Exception $e) {
        echo "\033[31m[ERROR] " . $e->getMessage() . "\033[0m\n";
    }
}

echo "\n--- Batch Complete ---\n";
