<?php
// video_playlist_api.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

try {
    // Fetch videos from database
    $stmt = $pdo->query("
        SELECT 
            id,
            name as title,
            url,
            thumbnail,
            duration,
            type,
            created_at
        FROM videos 
        ORDER BY created_at DESC
    ");
    
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add fallback thumbnail if none exists
    foreach ($videos as &$video) {
        if (!$video['thumbnail']) {
            $video['thumbnail'] = '/vendor/video-js.png'; // Fallback image
        }
    }
    
    echo json_encode([
        'success' => true,
        'videos' => $videos,
        'total' => count($videos)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'videos' => [],
        'total' => 0
    ]);
}
