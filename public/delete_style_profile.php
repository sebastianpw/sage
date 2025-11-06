<?php
// public/delete_style_profile.php  (POST JSON { id: N })
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing id']);
    exit;
}

$id = (int)$data['id'];
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
    exit;
}

try {
    // fetch filename for cleanup
    $stmt = $pdo->prepare("SELECT filename FROM style_profiles WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $filename = $row ? $row['filename'] : null;

    // delete DB record (style_profile_axes rows cascade)
    $del = $pdo->prepare("DELETE FROM style_profiles WHERE id = :id");
    $del->execute([':id' => $id]);

    // delete file if present
    if ($filename) {
        $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__);
        $saveDir = $projectRoot . '/data/style_profiles';
        $filepath = $saveDir . '/' . $filename;
        if (is_file($filepath)) {
            @unlink($filepath);
        }
    }

    echo json_encode(['status' => 'ok']);
    exit;

} catch (Exception $e) {
    if (isset($fileLogger) && is_callable([$fileLogger, 'error'])) {
        $fileLogger->error('delete_style_profile error: ' . $e->getMessage());
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
