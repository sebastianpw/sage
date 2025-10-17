<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$name = $_POST['name'] ?? null;

if (!$name) {
    echo json_encode(['ok' => false, 'error' => 'Missing name']);
    exit;
}

try {
    $stmt = $pdoSys->prepare("INSERT INTO pages_dashboard (name, level) VALUES (:name, 1)");
    $stmt->execute([':name' => $name]);
    $insertedId = $pdoSys->lastInsertId();
    echo json_encode(['ok' => true, 'id' => $insertedId]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
