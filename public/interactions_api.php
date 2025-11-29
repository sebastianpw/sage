<?php
// public/interactions_api.php
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
            
            $stmt = $pdo->prepare("SELECT * FROM interactions WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) throw new Exception('Interaction not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'save':
            $id = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');

            $params = [
                ':name' => $name,
                ':description' => trim($body['description'] ?? ''),
                ':interaction_group' => trim($body['interaction_group'] ?? ''),
                ':category' => trim($body['category'] ?? '') ?: null,
                ':example_prompt' => trim($body['example_prompt'] ?? ''),
                ':active' => (int)($body['active'] ?? 1),
            ];

            if (empty($params[':interaction_group'])) {
                throw new Exception('Group is required');
            }

            if ($id > 0) { // Update
                $params[':id'] = $id;
                $sql = "UPDATE interactions SET name=:name, description=:description, interaction_group=:interaction_group, category=:category, example_prompt=:example_prompt, active=:active WHERE id=:id";
            } else { // Insert
                $sql = "INSERT INTO interactions (name, description, interaction_group, category, example_prompt, active) VALUES (:name, :description, :interaction_group, :category, :example_prompt, :active)";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $newId = $id > 0 ? $id : $pdo->lastInsertId();

            jsonResp(['status' => 'ok', 'id' => $newId]);
            break;

        case 'delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("DELETE FROM interactions WHERE id = :id");
            $stmt->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        case 'toggle':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("UPDATE interactions SET active = NOT active WHERE id = :id");
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
