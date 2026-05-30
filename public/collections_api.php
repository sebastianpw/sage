<?php
// public/collections_api.php
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
            $stmt = $pdo->prepare("SELECT * FROM chroma_collections WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Collection not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'save':
            $id = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            $type = $body['type'] ?? '';
            $dimension = (int)($body['dimension'] ?? 0);
            $description = trim($body['description'] ?? '');

            if (empty($name)) throw new Exception('Name is required');
            if (!in_array($type, ['text', 'image'])) throw new Exception('Type must be "text" or "image"');
            if ($dimension <= 0) throw new Exception('Dimension must be a positive integer');

            // Check uniqueness of name (except current id)
            $checkStmt = $pdo->prepare("SELECT id FROM chroma_collections WHERE name = :name AND id != :id");
            $checkStmt->execute([':name' => $name, ':id' => $id ?: 0]);
            if ($checkStmt->fetch()) throw new Exception('A collection with this name already exists');

            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE chroma_collections SET name = :name, type = :type, dimension = :dimension, description = :description WHERE id = :id");
                $stmt->execute([
                    ':name' => $name,
                    ':type' => $type,
                    ':dimension' => $dimension,
                    ':description' => $description,
                    ':id' => $id
                ]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO chroma_collections (name, type, dimension, description) VALUES (:name, :type, :dimension, :description)");
                $stmt->execute([
                    ':name' => $name,
                    ':type' => $type,
                    ':dimension' => $dimension,
                    ':description' => $description
                ]);
            }
            jsonResp(['status' => 'ok']);
            break;

        case 'delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');

            // Check if collection is used as target_collection in md_doc_analysis
            $stmt = $pdo->prepare("SELECT name FROM chroma_collections WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception('Collection not found');

            $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM md_doc_analysis WHERE target_collection = :name");
            $usageStmt->execute([':name' => $item['name']]);
            if ($usageStmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete: Collection is currently referenced in md_doc_analysis.target_collection');
            }

            $delStmt = $pdo->prepare("DELETE FROM chroma_collections WHERE id = :id");
            $delStmt->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonResp(['status' => 'error', 'message' => $e->getMessage()]);
}
