<?php
// public/sketch_templates_api.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

function jsonResp($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

$action = $_GET['action'] ?? $_REQUEST['action'] ?? null;
$body = json_decode(file_get_contents('php://input'), true);

if (!$action) {
    jsonResp(['status' => 'error', 'message' => 'Action not specified']);
}

try {
    switch ($action) {
        case 'load':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            
            $stmt = $pdo->prepare("SELECT * FROM sketch_templates WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) throw new Exception('Template not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'save':
            $id = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');

            // Validate JSON fields
            json_decode($body['entity_slots'] ?? '[]');
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Entity Slots is not valid JSON.');
            
            json_decode($body['tags'] ?? '[]');
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Tags is not valid JSON.');

            $params = [
                ':name' => $name,
                ':core_idea' => trim($body['core_idea'] ?? ''),
                ':shot_type' => trim($body['shot_type'] ?? ''),
                ':camera_angle' => trim($body['camera_angle'] ?? ''),
                ':perspective' => trim($body['perspective'] ?? ''),
                ':entity_slots' => $body['entity_slots'],
                ':tags' => $body['tags'],
                ':example_prompt' => trim($body['example_prompt'] ?? ''),
                ':entity_type' => trim($body['entity_type'] ?? 'sketches'),
                ':active' => (int)($body['active'] ?? 1),
            ];

            if ($id > 0) { // Update
                $params[':id'] = $id;
                $sql = "UPDATE sketch_templates SET name=:name, core_idea=:core_idea, shot_type=:shot_type, camera_angle=:camera_angle, perspective=:perspective, entity_slots=:entity_slots, tags=:tags, example_prompt=:example_prompt, entity_type=:entity_type, active=:active WHERE id=:id";
            } else { // Insert
                $sql = "INSERT INTO sketch_templates (name, core_idea, shot_type, camera_angle, perspective, entity_slots, tags, example_prompt, entity_type, active) VALUES (:name, :core_idea, :shot_type, :camera_angle, :perspective, :entity_slots, :tags, :example_prompt, :entity_type, :active)";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $newId = $id > 0 ? $id : $pdo->lastInsertId();

            jsonResp(['status' => 'ok', 'id' => $newId]);
            break;

        case 'delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("DELETE FROM sketch_templates WHERE id = :id");
            $stmt->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        case 'toggle':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("UPDATE sketch_templates SET active = NOT active WHERE id = :id");
            $stmt->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonResp(['status' => 'error', 'message' => $e->getMessage()]);
}

