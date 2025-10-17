<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
header('Content-Type: application/json');

$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
$content_element_id = isset($_GET['content_element_id']) ? (int)$_GET['content_element_id'] : 0;

if (!$page_id) {
    echo json_encode(['error' => 'missing page_id']);
    exit;
}

if ($content_element_id > 0) {
    // Load existing element
    $stmt = $pdo->prepare("SELECT id, html, page_id FROM content_elements WHERE id = :id AND page_id = :page_id");
    $stmt->bindValue(':id', $content_element_id, PDO::PARAM_INT);
    $stmt->bindValue(':page_id', $page_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode($row);
        exit;
    }
}

// If content_element_id is 0 or not found, create new for this page
$stmt = $pdo->prepare("INSERT INTO content_elements (page_id, html) VALUES (:page_id, '')");
$stmt->bindValue(':page_id', $page_id, PDO::PARAM_INT);
$stmt->execute();
$new_id = $pdo->lastInsertId();

echo json_encode([
    'id' => $new_id,
    'html' => '',
    'page_id' => $page_id
]);
