<?php
// public/api_json.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

function jsonResponse($status, $message, $data = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // 1. SAVE JSON
        if ($action === 'save') {
            $id = $input['id'] ?? null;
            $content = $input['content'] ?? '{}';
            $description = $input['description'] ?? '';
            $name = $input['name'] ?? 'Untitled JSON';
            $catId = $input['category_id'] ?? 1;
            
            if ($id) {
                // Update
                $stmt = $pdo->prepare("UPDATE json_files SET content = ?, description = ?, name = ?, category_id = ? WHERE id = ?");
                $stmt->execute([$content, $description, $name, $catId, $id]);
                jsonResponse('success', 'File updated', ['id' => $id]);
            } else {
                // Create
                $stmt = $pdo->prepare("INSERT INTO json_files (name, content, description, category_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $content, $description, $catId]);
                $newId = $pdo->lastInsertId();
                jsonResponse('success', 'File created', ['id' => $newId]);
            }
        }
        
        // 2. DELETE JSON
        if ($action === 'delete') {
            $id = $input['id'] ?? null;
            if ($id) {
                // Hard delete or Soft delete based on preference. Using Soft delete here for safety.
                $stmt = $pdo->prepare("UPDATE json_files SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                jsonResponse('success', 'File deleted');
            } else {
                jsonResponse('error', 'No ID provided');
            }
        }

        // 3. CREATE CATEGORY
        if ($action === 'create_category') {
            $name = trim($input['name'] ?? '');
            if ($name) {
                $stmt = $pdo->prepare("SELECT id FROM json_categories WHERE name = ?");
                $stmt->execute([$name]);
                if($stmt->fetch()) {
                    jsonResponse('error', 'Category already exists');
                }

                $stmt = $pdo->prepare("INSERT INTO json_categories (name) VALUES (?)");
                $stmt->execute([$name]);
                jsonResponse('success', 'Category created', ['id' => $pdo->lastInsertId(), 'name' => $name]);
            } else {
                jsonResponse('error', 'Invalid name');
            }
        }
    } 
    elseif ($method === 'GET') {
        
        // 4. GET JSON FILE
        if ($action === 'get' && isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT id, name, content, description, category_id FROM json_files WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                if(is_null($doc['content'])) $doc['content'] = '{}';
                if(is_null($doc['description'])) $doc['description'] = '';
                jsonResponse('success', 'Loaded', ['data' => $doc]);
            } else {
                jsonResponse('error', 'File not found');
            }
        }

        // 5. GET CATEGORIES
        if ($action === 'get_categories') {
            $stmt = $pdo->query("SELECT id, name FROM json_categories ORDER BY name ASC");
            jsonResponse('success', 'Loaded', ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // 6. SIMPLE SEARCH
        if ($action === 'search') {
            $q = $_GET['q'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM json_files WHERE is_active = 1 AND (name LIKE ? OR description LIKE ?)");
            $term = '%' . $q . '%';
            $stmt->execute([$term, $term]);
            jsonResponse('success', 'Search results', ['ids' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    jsonResponse('error', $e->getMessage());
}
