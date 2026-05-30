<?php
// public/curator_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\AIProvider;

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

try {
    if ($action === 'reanalyze' && $id) {
        
        // 1. Load Config
        $curatorConfigId = 'auto_curator_v1';
        $em = $spw->getEntityManager();
        $repo = $em->getRepository(GeneratorConfig::class);
        $config = $repo->findOneBy(['configId' => $curatorConfigId]);
        
        if (!$config) throw new Exception("Curator Config not found.");

        // 2. Fetch Sketch Data
        $stmt = $pdo->prepare("SELECT name, description FROM sketches WHERE id = ?");
        $stmt->execute([$id]);
        $sketch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sketch) throw new Exception("Sketch not found.");

        // 3. Fetch Ingredients
        $ingStmt = $pdo->prepare("SELECT ingredient_type, prompt_fragment FROM sketch_ingredients WHERE sketch_id = ?");
        $ingStmt->execute([$id]);
        $ingredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $contextParts = [];
        foreach ($ingredients as $ing) {
            $type = str_replace(['anivoc_', 'sketch_'], '', $ing['ingredient_type']);
            $contextParts[] = "[$type] " . $ing['prompt_fragment'];
        }
        $contextStr = implode("\n", $contextParts);

        // 4. Build Prompt (Same as CLI)
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

        // 5. Generate
        $aiProvider = $spw->getAIProvider();
        $service = new GeneratorService($aiProvider, new SchemaValidator(), new ResponseNormalizer());
        $result = $service->generate($config, ['entity_name' => $promptInput]);
        $rawResponse = $result->getRawResponse();

        // 6. JSON Repair
        $jsonStr = $rawResponse;
        if (preg_match('/```json\s*(.*?)\s*```/s', $rawResponse, $m)) {
            $jsonStr = $m[1];
        } elseif (preg_match('/\{.*\}/s', $rawResponse, $m)) {
            $jsonStr = $m[0];
        }
        
        $data = json_decode($jsonStr, true);
        if (!$data && $result->isSuccess()) $data = $result->getData();

        if ($data) {
            $entities = json_encode($data['entities'] ?? (object)[]);
            $classification = json_encode($data['classification'] ?? (object)[]);
            $scoring = json_encode($data['scoring'] ?? (object)[]);
            $thematics = json_encode($data['thematics'] ?? (object)[]);
            $recommendations = json_encode($data['recommendations'] ?? (object)[]);
            $score = $data['scoring']['overall_quality'] ?? 0;

            // 7. Upsert Analysis
            $ins = $pdo->prepare("
                INSERT INTO sketch_analysis 
                (sketch_id, entities, classification, scoring, thematics, recommendations, overall_quality, generator_config_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                entities=VALUES(entities), classification=VALUES(classification), scoring=VALUES(scoring), 
                thematics=VALUES(thematics), recommendations=VALUES(recommendations), overall_quality=VALUES(overall_quality), 
                analyzed_at=NOW()
            ");
            
            $ins->execute([
                $id, $entities, $classification, $scoring, $thematics, $recommendations, 
                $score, $config->getId()
            ]);
            
            echo json_encode(['ok' => true, 'score' => $score]);
        } else {
            throw new Exception("AI returned invalid JSON");
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
