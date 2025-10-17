<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// get last_seen
$stmt = $pdo->query("SELECT last_seen FROM scheduler_heartbeat WHERE id = 1");
$heartbeat = $stmt->fetchColumn();

// return server time too
echo json_encode([
    'last_seen' => $heartbeat,
    'server_time' => date('Y-m-d H:i:s')  // current server time
]);


