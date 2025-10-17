<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
header('Content-Type: application/json');

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$stmt = $pdo->prepare("SELECT id, name, filename FROM frames ORDER BY id DESC LIMIT :offset, :limit");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($frames as &$frame) $frame['url'] = $frame['filename'];
unset($frame);

echo json_encode($frames);


