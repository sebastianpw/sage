<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
header('Content-Type: application/json');

$html = $_POST['html'] ?? '';
$page_id = isset($_POST['page_id']) ? (int)$_POST['page_id'] : 0;
$content_element_id = isset($_POST['content_element_id']) ? (int)$_POST['content_element_id'] : 0;

if (!$page_id) {
    echo json_encode(['error' => 'missing page_id']);
    exit;
}

// If content_element_id exists, update; otherwise create new
if ($content_element_id > 0) {
    $stmt = $pdo->prepare("UPDATE content_elements SET html = :html WHERE id = :id AND page_id = :page_id");
    $stmt->bindValue(':html', $html);
    $stmt->bindValue(':id', $content_element_id, PDO::PARAM_INT);
    $stmt->bindValue(':page_id', $page_id, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode(['status' => 'ok', 'id' => $content_element_id, 'page_id' => $page_id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO content_elements (page_id, html) VALUES (:page_id, :html)");
    $stmt->bindValue(':page_id', $page_id, PDO::PARAM_INT);
    $stmt->bindValue(':html', $html);
    $stmt->execute();
    $new_id = $pdo->lastInsertId();
    echo json_encode(['status' => 'ok', 'id' => $new_id, 'page_id' => $page_id, 'message' => 'New content element created']);
}
