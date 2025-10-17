<?php
// storyboard_import.php - AJAX endpoint for importing frames into storyboards
require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Load the helper class
require_once __DIR__ . '/StoryboardHelper.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

try {
    // Get parameters
    $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
    $frameIdsJson = $_POST['frame_ids'] ?? '';
    
    // Validate storyboard ID
    if (!$storyboardId) {
        throw new Exception('Storyboard ID is required');
    }
    
    // Parse frame IDs
    if (is_string($frameIdsJson)) {
        $frameIds = json_decode($frameIdsJson, true);
    } else {
        $frameIds = $frameIdsJson;
    }
    
    // Also support single frame_id parameter
    if (empty($frameIds) && isset($_POST['frame_id'])) {
        $frameIds = [(int)$_POST['frame_id']];
    }
    
    if (!is_array($frameIds) || empty($frameIds)) {
        throw new Exception('At least one frame ID is required');
    }
    
    // Validate frame IDs are integers
    $frameIds = array_map('intval', $frameIds);
    $frameIds = array_filter($frameIds, function($id) {
        return $id > 0;
    });
    
    if (empty($frameIds)) {
        throw new Exception('No valid frame IDs provided');
    }
    
    // Verify storyboard exists
    $stmt = $pdo->prepare("SELECT id, name FROM storyboards WHERE id = ?");
    $stmt->execute([$storyboardId]);
    $storyboard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$storyboard) {
        throw new Exception('Storyboard not found');
    }
    
    // Use the helper to import frames
    $helper = new \App\Helper\StoryboardHelper($pdo);
    $result = $helper->importFramesToStoryboard($storyboardId, $frameIds);
    
    // Build response
    $response = [
        'success' => true,
        'storyboard_id' => $storyboardId,
        'storyboard_name' => $storyboard['name'],
        'imported_count' => count($result['imported']),
        'imported_frame_ids' => $result['imported'],
        'error_count' => count($result['errors']),
        'errors' => $result['errors']
    ];
    
    // If there were errors but some succeeded
    if (!empty($result['errors']) && !empty($result['imported'])) {
        $response['message'] = sprintf(
            'Imported %d frame(s) successfully. %d error(s) occurred.',
            count($result['imported']),
            count($result['errors'])
        );
    } 
    // If all succeeded
    elseif (empty($result['errors'])) {
        $response['message'] = sprintf(
            'Successfully imported %d frame(s) to "%s"',
            count($result['imported']),
            $storyboard['name']
        );
    }
    // If all failed
    else {
        $response['success'] = false;
        $response['message'] = 'All frame imports failed. See errors for details.';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'imported_count' => 0,
        'error_count' => 1,
        'errors' => [$e->getMessage()]
    ]);
}
