<?php
// public/webtoon_editor_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        
        case 'add_overlay':
            $sketchId = (int)($_POST['sketch_id'] ?? 0);
            if (!$sketchId) throw new Exception('Sketch ID required');
            
            $pdo->beginTransaction();
            
            // Find max display_order
            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), -1) FROM sketch_overlay_texts WHERE sketch_id = ?");
            $maxStmt->execute([$sketchId]);
            $maxOrder = (int)$maxStmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO sketch_overlay_texts (sketch_id, text_content, display_order) VALUES (?, '', ?)");
            $stmt->execute([$sketchId, $maxOrder + 1]);
            $overlayId = $pdo->lastInsertId();
            
            $pdo->commit();
            echo json_encode(['success' => true, 'overlay_id' => $overlayId]);
            break;

        case 'update_overlay':
            $overlayId = (int)($_POST['overlay_id'] ?? 0);
            $text = $_POST['text'] ?? '';
            if (!$overlayId) throw new Exception('Overlay ID required');
            
            $stmt = $pdo->prepare("UPDATE sketch_overlay_texts SET text_content = ? WHERE id = ?");
            $stmt->execute([$text, $overlayId]);
            
            echo json_encode(['success' => true]);
            break;

        case 'delete_overlay':
            $overlayId = (int)($_POST['overlay_id'] ?? 0);
            if (!$overlayId) throw new Exception('Overlay ID required');
            
            $stmt = $pdo->prepare("DELETE FROM sketch_overlay_texts WHERE id = ?");
            $stmt->execute([$overlayId]);
            
            echo json_encode(['success' => true]);
            break;

        case 'reorder_overlays':
            $sketchId = (int)($_POST['sketch_id'] ?? 0);
            $orderRaw = $_POST['order'] ?? '';
            if (!$sketchId || !$orderRaw) throw new Exception('Sketch ID and order required');

            $ids = array_filter(array_map('intval', explode(',', $orderRaw)));
            if (empty($ids)) throw new Exception('No valid IDs in order');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE sketch_overlay_texts SET display_order = ? WHERE sketch_id = ? AND id = ?");
            foreach ($ids as $i => $overlayId) {
                $stmt->execute([$i, $sketchId, $overlayId]);
            }
            $pdo->commit();

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}