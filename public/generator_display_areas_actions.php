<?php
// public/generator_display_areas_actions.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfigDisplayArea;
use App\Entity\GeneratorConfig;

header('Content-Type: application/json; charset=utf-8');

$em = $spw->getEntityManager();
$repo = $em->getRepository(GeneratorConfigDisplayArea::class);
$userId = $_SESSION['user_id'] ?? null;

// Basic auth check - you might want a stronger admin check here
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? null;

try {
    switch ($action) {
        case 'list':
            $areas = $repo->findBy([], ['label' => 'ASC']);
            $items = array_map(fn($area) => [
                'id' => $area->getId(),
                'area_key' => $area->getAreaKey(),
                'label' => $area->getLabel(),
                'is_active' => $area->isActive()
            ], $areas);
            echo json_encode(['ok' => true, 'data' => $items]);
            break;

        case 'get':
            $id = (int)($input['id'] ?? 0);
            $area = $repo->find($id);
            if (!$area) { throw new Exception("Display area not found"); }
            echo json_encode(['ok' => true, 'data' => [
                'id' => $area->getId(),
                'area_key' => $area->getAreaKey(),
                'label' => $area->getLabel()
            ]]);
            break;

        case 'create':
            $key = trim($input['area_key'] ?? '');
            $label = trim($input['label'] ?? '');
            if (empty($key) || empty($label)) { throw new Exception("Key and Label cannot be empty."); }
            
            $existing = $repo->findOneBy(['areaKey' => $key]);
            if ($existing) { throw new Exception("The key '{$key}' already exists."); }

            $area = new GeneratorConfigDisplayArea();
            $area->setAreaKey($key);
            $area->setLabel($label);
            $em->persist($area);
            $em->flush();
            echo json_encode(['ok' => true, 'message' => "Created display area: {$label}"]);
            break;

        case 'update':
            $id = (int)($input['id'] ?? 0);
            $key = trim($input['area_key'] ?? '');
            $label = trim($input['label'] ?? '');
            if (empty($key) || empty($label)) { throw new Exception("Key and Label cannot be empty."); }

            $area = $repo->find($id);
            if (!$area) { throw new Exception("Display area not found."); }
            
            // Check if key is being changed to one that already exists
            if ($area->getAreaKey() !== $key) {
                $existing = $repo->findOneBy(['areaKey' => $key]);
                if ($existing) { throw new Exception("The key '{$key}' already exists."); }
            }

            $area->setAreaKey($key);
            $area->setLabel($label);
            $em->flush();
            echo json_encode(['ok' => true, 'message' => "Updated display area: {$label}"]);
            break;

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            $area = $repo->find($id);
            if (!$area) { throw new Exception("Display area not found."); }

            // Prevent deletion if a generator is using this area
            $generatorRepo = $em->getRepository(GeneratorConfig::class);
            $usageCount = $generatorRepo->count(['type' => $area->getAreaKey()]);
            if ($usageCount > 0) {
                throw new Exception("Cannot delete. This display area is in use by {$usageCount} generator(s).");
            }
            
            $em->remove($area);
            $em->flush();
            echo json_encode(['ok' => true, 'message' => 'Display area deleted.']);
            break;
            
        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

