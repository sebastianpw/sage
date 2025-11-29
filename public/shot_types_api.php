<?php
// public/shot_types_api.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

function jsonResp($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

$action = $_GET['action'] ?? null;
$body = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'load':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT * FROM shot_types WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Shot Type not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'save':
            $id = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');

            if ($id > 0) { // Update
                $stmt = $pdo->prepare("UPDATE shot_types SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
            } else { // Insert
                $stmt = $pdo->prepare("INSERT INTO shot_types (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
            }
            jsonResp(['status' => 'ok']);
            break;

        case 'delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');

            // Check if in use
            $stmt = $pdo->prepare("SELECT name FROM shot_types WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM sketch_templates WHERE shot_type = :name");
                $usageStmt->execute([':name' => $item['name']]);
                if ($usageStmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete: Shot Type is currently in use by sketch templates.');
                }
            }

            $stmt = $pdo->prepare("DELETE FROM shot_types WHERE id = :id");
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

