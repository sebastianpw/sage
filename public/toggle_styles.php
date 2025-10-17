<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pdo = $spw->getPDO();

if (!isset($_POST['id'], $_POST['field'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$id = (int) $_POST['id'];
$field = $_POST['field'];

if (!in_array($field, ['active', 'visible'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid field']);
    exit;
}

$stmt = $pdo->prepare("UPDATE styles SET $field = NOT $field, updated_at = NOW() WHERE id = :id");
$stmt->execute([':id' => $id]);

$newValue = $pdo->query("SELECT $field FROM styles WHERE id = $id")->fetchColumn();

echo json_encode([
    'id' => $id,
    'field' => $field,
    'value' => (int)$newValue
]);

