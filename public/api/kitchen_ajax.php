<?php
require_once __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../env_locals.php';
require __DIR__ . '/../entity_icons.php';

use App\SceneKitchen\KitchenChef;
use App\SceneKitchen\Ingredients\SketchTemplateIngredient;
use App\SceneKitchen\Ingredients\InteractionIngredient;
use App\SceneKitchen\Ingredients\StyleProfileIngredient;
use App\SceneKitchen\Ingredients\GenericEntityIngredient;
use App\Entity\GeneratorConfig;

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$chef = new KitchenChef($pdo);

// Map icons for new tables manually since they aren't in entity_icons.php yet
$anivocIcons = [
    'anivoc_expressions' => '😊', 'anivoc_backgrounds' => '🏙️', 'anivoc_motion_impact' => '💥',
    'anivoc_lighting' => '💡', 'anivoc_transitions' => '🎞️', 'anivoc_color_coding' => '🎨',
    'anivoc_scale_perspective' => '📐', 'anivoc_symbolic_objects' => '🗿',
    'anivoc_text_graphics' => '🗯️', 'anivoc_panel_frame' => '🖼️'
];

try {
    // --- FETCH INGREDIENTS LIST ---
    if ($action === 'fetch_ingredients') {
        $type = $_POST['type'] ?? '';
        $filters = $_POST['filters'] ?? [];
        $ingredients = [];
        
        if ($type === 'templates') {
            $ingredients = SketchTemplateIngredient::fetchAvailable($pdo, $filters);
        } elseif ($type === 'interactions') {
            $ingredients = InteractionIngredient::fetchAvailable($pdo, $filters);
        } elseif ($type === 'style_profiles') {
            $ingredients = StyleProfileIngredient::fetchAvailable($pdo, $filters);
        } else {
            // Generic fallback
            global $entityIcons; 
            $icon = $entityIcons[$type] ?? $anivocIcons[$type] ?? '📦';
            $ingredients = GenericEntityIngredient::fetchFromTable($pdo, $type, $icon);
        }

        $data = array_map(fn($i) => [
            'id' => $i->getId(),
            'type' => ($i instanceof GenericEntityIngredient) ? $i->getSpecificType() : $i::getType(),
            'label' => $i->getLabel(),
            'icon' => $i->getIcon(),
            'data' => $i->getSnapshotData()
        ], $ingredients);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // --- AUTO RECIPE ---
    if ($action === 'auto_recipe') {
        $ingredients = $chef->generateRandomRecipe();
        $data = array_map(fn($i) => [
            'id' => $i->getId(),
            'type' => ($i instanceof GenericEntityIngredient) ? $i->getSpecificType() : $i::getType(),
            'label' => $i->getLabel(),
            'icon' => $i->getIcon()
        ], $ingredients);
        echo json_encode(['ok' => true, 'ingredients' => $data]);
        exit;
    }

    // --- COOK ---
    if ($action === 'cook') {
        $rawIngredients = $_POST['ingredients'] ?? [];
        $descGenId = (int)($_POST['desc_gen_id'] ?? 0);
        $nameGenId = (int)($_POST['name_gen_id'] ?? 0);
        $customInstruction = trim($_POST['custom_instruction'] ?? '');

        if (empty($rawIngredients) && empty($customInstruction)) {
            throw new \Exception("The pot is empty and no instructions provided");
        }

        // Use Chef to hydrate
        $cookedIngredients = $chef->hydrateIngredients($rawIngredients);
        $promptSegments = array_map(fn($i) => $i->getPromptSegment(), $cookedIngredients);

        $em = $spw->getEntityManager();
        $genConfig = $em->getRepository(GeneratorConfig::class)->findOneBy(['id' => $descGenId]);
        if (!$genConfig) throw new \Exception("Description Generator not found");

        // Build the Prompt
        $prompt = "Create a scene based on these ingredients:\n" . implode("\n\n", $promptSegments);
        
        if (!empty($customInstruction)) {
            $prompt .= "\n\n### ADDITIONAL INSTRUCTIONS / CHEF'S NOTES:\n" . $customInstruction;
        }

        $genParams = ['entity_name' => $prompt];

        if ($genConfig->getOracleConfig() !== null) {
            $genParams['random_oracle_seed'] = rand(1, 99999999);
        }

        $validator = new \App\Service\Schema\SchemaValidator();
        $normalizer = new \App\Service\Schema\ResponseNormalizer();
        $aiProvider = $spw->getAIProvider();
        $service = new \App\Service\GeneratorService($aiProvider, $validator, $normalizer);

        $result = $service->generate($genConfig, $genParams);
        
        if (!$result->isSuccess()) {
            throw new \Exception("AI Generation failed");
        }
        
        $data = $result->getData();
        $finalDescription = is_array($data) ? ($data['description'] ?? json_encode($data)) : (string)$data;
        $finalName = "Sketch " . date('Y-m-d H:i');
        
        // Save
        $sketchId = $chef->saveSketch($finalName, $finalDescription, $cookedIngredients, $descGenId, $nameGenId);

        echo json_encode(['ok' => true, 'sketch_id' => $sketchId]);
        exit;
    }

    // --- SAVE POT ---
    if ($action === 'save_pot') {
        $name = trim($_POST['name'] ?? 'Untitled Pot');
        $ingredients = $_POST['ingredients'] ?? [];
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($ingredients) && empty($notes)) throw new \Exception("Cannot save an empty pot");
        
        $json = json_encode($ingredients);
        $stmt = $pdo->prepare("INSERT INTO scene_kitchen_pots (name, ingredients_json, notes) VALUES (?, ?, ?)");
        $stmt->execute([$name, $json, $notes]);
        
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // --- LIST POTS ---
    if ($action === 'list_pots') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM scene_kitchen_pots");
        $total = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT id, name, created_at, notes, LENGTH(ingredients_json) as size FROM scene_kitchen_pots ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true, 
            'rows' => $rows, 
            'total' => $total, 
            'page' => $page, 
            'pages' => ceil($total / $limit)
        ]);
        exit;
    }

    // --- LOAD POT ---
    if ($action === 'load_pot') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT ingredients_json, notes FROM scene_kitchen_pots WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) throw new \Exception("Pot not found");
        
        $rawIngredients = json_decode($row['ingredients_json'], true);
        if (!is_array($rawIngredients)) $rawIngredients = [];
        
        $ingredients = $chef->hydrateIngredients($rawIngredients);
        $data = array_map(fn($i) => [
            'id' => $i->getId(),
            'type' => ($i instanceof GenericEntityIngredient) ? $i->getSpecificType() : $i::getType(),
            'label' => $i->getLabel(),
            'icon' => $i->getIcon()
        ], $ingredients);
        
        echo json_encode(['ok' => true, 'ingredients' => $data, 'notes' => $row['notes']]);
        exit;
    }

    // --- DELETE POT ---
    if ($action === 'delete_pot') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM scene_kitchen_pots WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
