<?php
// delete_pages.php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getSysPDO();

// Optional: restrict to POST for safety
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM pages_dashboard WHERE parent_id = :pid");
    $stmt->execute(['pid' => 1001]);
    $deleted = $stmt->rowCount();

    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'deleted' => $deleted]);

} catch (\PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
