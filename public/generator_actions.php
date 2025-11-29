<?php
// public/generator_actions.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Core\AIProvider;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Entity\GeneratorConfigDisplayArea;
use App\Dictionary\DictionaryManager;

header('Content-Type: application/json; charset=utf-8');

$em = $spw->getEntityManager();
$repo = $em->getRepository(GeneratorConfig::class);
$userId = $_SESSION['user_id'] ?? null;

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
            $qb = $repo->createQueryBuilder('g')
                ->leftJoin('g.displayAreas', 'da')
                ->addSelect('da')
                ->where('g.userId = :userId OR g.isPublic = :isPublic')
                ->setParameter('userId', $userId)
                ->setParameter('isPublic', true)
                ->orderBy('g.listOrder', 'ASC')
                ->addOrderBy('g.createdAt', 'DESC');

            $configs = $qb->getQuery()->getResult();
            
            $items = array_map(function($cfg) use ($userId) {
                $areas = [];
                foreach ($cfg->getDisplayAreas() as $area) {
                    $areas[] = ['key' => $area->getAreaKey(), 'label' => $area->getLabel()];
                }
                return [
                    'id' => $cfg->getId(),
                    'config_id' => $cfg->getConfigId(),
                    'title' => $cfg->getTitle(),
                    'model' => $cfg->getModel(),
                    'display_areas' => $areas,
                    'active' => $cfg->isActive(),
                    'created_at' => $cfg->getCreatedAt()->format('Y-m-d H:i'),
                    'is_public' => $cfg->isPublic(),
                    'is_owner' => $cfg->getUserId() === $userId
                ];
            }, $configs);
            echo json_encode(['ok' => true, 'data' => $items]);
            break;

        case 'get':
            $id = (int)($input['id'] ?? 0);
            $config = $repo->find($id);
            if (!$config) { throw new Exception("Config not found"); }

            if (!$config->isPublic() && $config->getUserId() !== $userId) {
                http_response_code(403);
                throw new Exception("Access denied");
            }
            
            $displayAreaKeys = array_map(fn($area) => $area->getAreaKey(), $config->getDisplayAreas()->toArray());

            echo json_encode([
                'ok' => true,
                'data' => [
                    'id' => $config->getId(),
                    'title' => $config->getTitle(),
                    'model' => $config->getModel(),
                    'display_area_keys' => $displayAreaKeys,
                    'is_public' => $config->isPublic(),
                    'config_json' => json_encode($config->toConfigArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'oracle_config' => $config->getOracleConfig(),
                    'is_owner' => $config->getUserId() === $userId
                ]
            ]);
            break;
            
        case 'create':
        case 'update':
            $id = (int)($input['id'] ?? 0);
            $isUpdate = $id > 0;

            if ($isUpdate) {
                $config = $repo->find($id);
                if (!$config) { throw new Exception("Config not found"); }

                $isOwner = $config->getUserId() === $userId;
                $isPublic = $config->isPublic();
                if (!$isOwner && !($isPublic && isAdmin($userId))) {
                    http_response_code(403);
                    throw new Exception("You don't have permission to modify this generator");
                }
            } else {
                $config = GeneratorConfig::fromJson($input['config_json'] ?? '', $userId);
            }

            $config->setTitle(trim($input['title'] ?? ''));
            $config->setModel(trim($input['model'] ?? 'openai'));
            
            $configJson = $input['config_json'] ?? '';
            $data = json_decode($configJson, true);
            if (!$data) { throw new Exception("Invalid Configuration JSON"); }
            
            $config->setSystemRole($data['system']['role'] ?? '');
            $config->setInstructions($data['system']['instructions'] ?? []);
            $config->setParameters($data['parameters'] ?? []);
            $config->setOutputSchema($data['output'] ?? []);
            $config->setExamples($data['examples'] ?? null);
            $config->setOracleConfig($input['oracle_config'] ?? null);

            // Handle display areas
            $areaKeys = $input['display_area_keys'] ?? [];
            $areaRepo = $em->getRepository(GeneratorConfigDisplayArea::class);
            $config->getDisplayAreas()->clear();
            if (!empty($areaKeys)) {
                $areaObjects = $areaRepo->findBy(['areaKey' => $areaKeys]);
                foreach ($areaObjects as $areaObject) {
                    $config->addDisplayArea($areaObject);
                }
            }
            
            // Handle public status (only changeable by admins)
            $isPublicInput = isset($input['is_public']) ? (bool)$input['is_public'] : null;
            if ($isPublicInput !== null && $config->isPublic() !== $isPublicInput) {
                if (!isAdmin($userId)) {
                    throw new Exception("Only administrators can change public status.");
                }
                $config->setIsPublic($isPublicInput);
            }
            
            if (!$isUpdate) {
                $em->persist($config);
            }
            
            $em->flush();

            echo json_encode([
                'ok' => true, 
                'message' => $isUpdate ? "Updated: {$config->getTitle()}" : "Generator created: {$config->getTitle()}"
            ]);
            break;

        case 'update_order':
            $ids = $input['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) { throw new Exception("Invalid or empty list of IDs provided."); }
            $userConfigs = $repo->createQueryBuilder('g')->where('g.userId = :userId')->andWhere('g.id IN (:ids)')->setParameter('userId', $userId)->setParameter('ids', $ids)->getQuery()->getResult();
            $configMap = [];
            foreach ($userConfigs as $config) { $configMap[$config->getId()] = $config; }
            foreach (array_values($ids) as $order => $id) { if (isset($configMap[$id])) { $configMap[$id]->setListOrder($order); } }
            $em->flush();
            echo json_encode(['ok' => true, 'message' => 'Order updated successfully.']);
            break;

        case 'copy':
            $id = (int)($input['id'] ?? 0);
            $originalConfig = $repo->find($id);
            if (!$originalConfig) { throw new Exception("Generator to copy not found"); }
            if (!$originalConfig->isPublic() && $originalConfig->getUserId() !== $userId) {
                http_response_code(403);
                throw new Exception("Access denied: You can only copy your own or public generators.");
            }
            $newConfig = $originalConfig->duplicate($userId);
            $em->persist($newConfig);
            $em->flush();
            echo json_encode(['ok' => true, 'message' => "Copied '{$originalConfig->getTitle()}' successfully.", 'data' => ['new_id' => $newConfig->getId()]]);
            break;

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            $config = $repo->find($id);
            if (!$config) { throw new Exception("Config not found"); }
            if ($config->getUserId() !== $userId && !($config->isPublic() && isAdmin($userId))) {
                http_response_code(403);
                throw new Exception("You don't have permission to delete this generator");
            }
            $em->remove($config);
            $em->flush();
            echo json_encode(['ok' => true, 'message' => 'Generator deleted']);
            break;

        case 'toggle':
            $id = (int)($input['id'] ?? 0);
            $config = $repo->find($id);
            if (!$config) { throw new Exception("Config not found"); }
            if ($config->getUserId() !== $userId && !($config->isPublic() && isAdmin($userId))) {
                http_response_code(403);
                throw new Exception("You don't have permission to modify this generator");
            }
            $config->setActive(!$config->isActive());
            $em->flush();
            echo json_encode(['ok' => true, 'message' => $config->isActive() ? "Activated" : "Deactivated", 'active' => $config->isActive()]);
            break;

        case 'test':
            $id = (int)($input['id'] ?? 0);
            $config = $repo->find($id);
            if (!$config) { throw new Exception("Config not found"); }
            if (!$config->isPublic() && $config->getUserId() !== $userId) {
                http_response_code(403);
                throw new Exception("Access denied");
            }
            $params = $input['params'] ?? [];
            $service = new GeneratorService($spw->getAIProvider(), new SchemaValidator(), new ResponseNormalizer(), $spw->getFileLogger());
            $result = $service->generate($config, $params);
            echo json_encode(['ok' => true, 'result' => $result->toArray()]);
            break;
            
        case 'get_display_areas':
            $areaRepo = $em->getRepository(GeneratorConfigDisplayArea::class);
            $areas = $areaRepo->findBy(['isActive' => true], ['label' => 'ASC']);
            $items = array_map(fn($area) => ['key' => $area->getAreaKey(), 'label' => $area->getLabel()], $areas);
            echo json_encode(['ok' => true, 'data' => $items]);
            break;
        
        case 'get_dictionaries':
            $dictManager = new DictionaryManager($pdo); // Use the global $pdo
            $dictionaries = $dictManager->getAllDictionaries();
            $items = array_map(fn($d) => ['id' => $d['id'], 'title' => $d['title']], $dictionaries);
            echo json_encode(['ok' => true, 'data' => $items]);
            break;

        case 'get_models':
            echo json_encode(['ok' => true, 'data' => AIProvider::getAllModels()]);
            break;
            
        case 'check_admin':
            echo json_encode(['ok' => true, 'is_admin' => isAdmin($userId)]);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function isAdmin(int $userId): bool {
    $adminUserIds = [1];
    return in_array($userId, $adminUserIds, true);
}
