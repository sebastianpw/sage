<?php
// addition_create.php
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$entity_type = $_POST['entity_type'] ?? null;
$entity_id = isset($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;
$slot = isset($_POST['slot']) ? (int)$_POST['slot'] : 1;
$description = trim($_POST['description'] ?? '');

if ($description === '') {
    echo json_encode(['success'=>false, 'error'=>'Empty description']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO prompt_additions (entity_type, entity_id, slot, `order`, description, active, created_at, updated_at) VALUES (?, ?, ?, 0, ?, 1, NOW(), NOW())");
    $stmt->execute([$entity_type, $entity_id, $slot, $description]);
    $id = (int)$pdo->lastInsertId();
    echo json_encode(['success'=>true, 'id'=>$id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
