<?php
// public/video_playlist_api.php - Updated to work with new playlist system
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

try {
    $playlistId = $_GET['playlist_id'] ?? null;
    $categoryId = $_GET['category_id'] ?? null;
    
    $sql = "SELECT v.id, v.name as title, v.url, v.thumbnail, v.duration, 
                   v.type, v.created_at, v.description
            FROM videos v";
    
    $where = [];
    $params = [];
    
    if ($playlistId) {
        // Get videos from specific playlist
        $sql .= " INNER JOIN playlist_videos pv ON v.id = pv.video_id";
        $where[] = "pv.playlist_id = ?";
        $params[] = $playlistId;
        $orderBy = " ORDER BY pv.sort_order ASC, v.created_at DESC";
    } elseif ($categoryId) {
        // Get videos from specific category
        $where[] = "v.category_id = ?";
        $params[] = $categoryId;
        $orderBy = " ORDER BY v.sort_order ASC, v.created_at DESC";
    } else {
        // Get all active videos
        $where[] = "v.is_active = 1";
        $orderBy = " ORDER BY v.created_at DESC";
    }
    
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= $orderBy . " LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get playlist info if specified
    $playlistInfo = null;
    if ($playlistId) {
        $stmt = $pdo->prepare("SELECT * FROM video_playlists WHERE id = ?");
        $stmt->execute([$playlistId]);
        $playlistInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'videos' => $videos,
        'playlist' => $playlistInfo,
        'total' => count($videos)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    if (isset($fileLogger) && is_callable([$fileLogger, 'error'])) {
        $fileLogger->error('video_playlist_api error: ' . $e->getMessage());
    }
}
