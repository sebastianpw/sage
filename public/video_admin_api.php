<?php
// public/video_admin_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // In video_admin_api.php, ADD this new case inside the switch statement
        


case 'update_playlist_video_order':
    $data = json_decode(file_get_contents('php://input'), true);
    $playlistId = (int)($data['playlist_id'] ?? 0);
    $videoIds = $data['video_ids'] ?? [];
    
    if (!$playlistId || empty($videoIds) || !is_array($videoIds)) {
        throw new Exception('Invalid order data');
    }
    
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("UPDATE playlist_videos SET sort_order = ? WHERE playlist_id = ? AND video_id = ?");
        
        foreach ($videoIds as $index => $videoId) {
            $stmt->execute([$index, $playlistId, (int)$videoId]);
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'ok', 'message' => 'Video order updated']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    break;

    
case 'update_playlist_order':
    $data = json_decode(file_get_contents('php://input'), true);
    $playlistIds = $data['playlist_ids'] ?? [];
    
    if (empty($playlistIds) || !is_array($playlistIds)) {
        throw new Exception('Invalid playlist order data');
    }
    
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("UPDATE video_playlists SET sort_order = ? WHERE id = ?");
        
        foreach ($playlistIds as $index => $id) {
            $stmt->execute([$index, (int)$id]);
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'ok', 'message' => 'Playlist order updated']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    break;


case 'get_playlist_json':
    $playlistId = (int)($_GET['id'] ?? 0);
    if (!$playlistId) {
        throw new Exception('Missing playlist ID');
    }

    // 1. Query for the videos in the specified playlist, ordering them correctly
    // We alias v.name to title to match your required JSON format
    $sql = "SELECT v.id, v.name as title, v.url, v.thumbnail, v.duration,
                   v.type, v.created_at
            FROM videos v
            INNER JOIN playlist_videos pv ON v.id = pv.video_id
            WHERE pv.playlist_id = ?
            ORDER BY pv.sort_order ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$playlistId]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Construct the absolute base URL for video files
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $scheme . '://' . $host;

    // 3. Process the results to create absolute URLs for the main video
    // The user example shows the thumbnail URL remaining relative, so we'll respect that.
    foreach ($videos as &$video) {
        // Ensure the URL starts with a slash and is not already absolute
        if (substr($video['url'], 0, 1) === '/' && !preg_match('/^https?:\/\//', $video['url'])) {
            $video['url'] = $baseUrl . $video['url'];
        }
    }
    unset($video); // Unset the reference

    // 4. Encode the final array into a nicely formatted JSON string
    $jsonString = json_encode($videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    echo json_encode(['status' => 'ok', 'json_string' => $jsonString]);
    break;



// In video_admin_api.php, ADD this new case inside the switch statement

// In video_admin_api.php, REPLACE the existing 'regenerate_thumbnail' case with this corrected version

case 'regenerate_thumbnail':
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        throw new Exception('Missing video ID');
    }

    // 1. Get the video's file paths from the database
    $stmt = $pdo->prepare("SELECT url, thumbnail, duration FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        throw new Exception('Video not found');
    }

    // 2. Construct the absolute paths
    $publicPathAbs = $spw->getPublicPath(); 
    $videoPath = $publicPathAbs . $video['url'];
    $thumbnailPath = $publicPathAbs . $video['thumbnail'];
    $generateThumbnailScript = $spw->getProjectPath() . '/bash/generate_thumbnail.sh'; 

    if (!file_exists($videoPath)) {
        throw new Exception('Source video file not found on server.');
    }
    if (!file_exists($generateThumbnailScript)) {
        throw new Exception('Thumbnail generation script not found.');
    }

    // 3. Delete the old thumbnail to ensure it's replaced
    if (file_exists($thumbnailPath)) {
        unlink($thumbnailPath);
    }
    
    // 4. Execute the same thumbnail generation script
    $duration = $video['duration'] ?? 1;
    $thumbTime = min(1, max(0, $duration - 1));
    $thumbCommand = 'sh ' . escapeshellarg($generateThumbnailScript) . ' '
                    . escapeshellarg($videoPath) . ' '
                    . escapeshellarg($thumbnailPath) . ' '
                    . escapeshellarg($thumbTime);

    $shellOutput = trim(shell_exec($thumbCommand . ' 2>&1'));

    // 5. Verify generation
    if (!file_exists($thumbnailPath) || filesize($thumbnailPath) === 0) {
        if (!empty($shellOutput)) {
            error_log("FFmpeg thumbnail regeneration failed for video $id: " . $shellOutput);
        }
        throw new Exception('Failed to create new thumbnail file. Check server logs.');
    }

    // 6. *** THE FIX IS HERE ***
    // Update the database with the new URL including a cache-busting timestamp
    $baseThumbnailUrl = strtok($video['thumbnail'], '?'); // Remove old timestamp if it exists
    $newThumbnailUrl = $baseThumbnailUrl . '?t=' . time();

    $stmt = $pdo->prepare("UPDATE videos SET thumbnail = ? WHERE id = ?");
    $stmt->execute([$newThumbnailUrl, $id]);
    // *** END OF FIX ***

    // Success! Send back the URL that is now saved in the DB
    echo json_encode([
        'status' => 'ok',
        'message' => 'Thumbnail regenerated and saved',
        'thumbnail_url' => $newThumbnailUrl
    ]);
    break;


        case 'sync_video_playlists':
            $data = json_decode(file_get_contents('php://input'), true);
            $videoId = (int)($data['video_id'] ?? 0);
            $playlistIds = $data['playlist_ids'] ?? [];

            if (!$videoId) {
                throw new Exception('Missing video ID');
            }
            if (!is_array($playlistIds)) {
                throw new Exception('Invalid playlist data');
            }

            // Use a transaction to ensure data integrity
            $pdo->beginTransaction();

            try {
                // Step 1: Delete all existing playlist associations for this video
                $stmt = $pdo->prepare("DELETE FROM playlist_videos WHERE video_id = ?");
                $stmt->execute([$videoId]);

                // Step 2: Insert the new associations if any are provided
                if (!empty($playlistIds)) {
                    $sql = "INSERT INTO playlist_videos (playlist_id, video_id, sort_order) VALUES ";
                    $params = [];
                    $placeholders = [];
                    $sortOrder = 0;

                    foreach ($playlistIds as $playlistId) {
                        $placeholders[] = "(?, ?, ?)";
                        $params[] = (int)$playlistId;
                        $params[] = $videoId;
                        $params[] = $sortOrder++;
                    }

                    $sql .= implode(', ', $placeholders);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }

                // If everything went well, commit the changes
                $pdo->commit();

                echo json_encode(['status' => 'ok', 'message' => 'Playlists updated successfully']);
            } catch (Exception $e) {
                // If something went wrong, roll back the transaction
                $pdo->rollBack();
                // Rethrow the exception to be caught by the main handler
                throw $e;
            }
            break;

        case 'list_videos':
            $categoryId = $_GET['category_id'] ?? null;
            $playlistId = $_GET['playlist_id'] ?? null;
            $search = $_GET['search'] ?? '';
            
            $sql = "SELECT v.*, c.name as category_name 
                    FROM videos v 
                    LEFT JOIN video_categories c ON v.category_id = c.id";
            
            $where = [];
            $params = [];
            
            if ($categoryId) {
                $where[] = "v.category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($playlistId) {
                $sql .= " INNER JOIN playlist_videos pv ON v.id = pv.video_id";
                $where[] = "pv.playlist_id = ?";
                $params[] = $playlistId;
            }
            
            if ($search) {
                $where[] = "(v.name LIKE ? OR v.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($where) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY v.created_at DESC LIMIT 500";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'ok', 'videos' => $videos]);
            break;
            
        case 'get_video':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing video ID');
            
            $stmt = $pdo->prepare("SELECT v.*, c.name as category_name 
                                   FROM videos v 
                                   LEFT JOIN video_categories c ON v.category_id = c.id 
                                   WHERE v.id = ?");
            $stmt->execute([$id]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$video) throw new Exception('Video not found');
            
            // Get playlists this video belongs to
            $stmt = $pdo->prepare("SELECT p.id, p.name FROM video_playlists p 
                                   INNER JOIN playlist_videos pv ON p.id = pv.playlist_id 
                                   WHERE pv.video_id = ?");
            $stmt->execute([$id]);
            $video['playlists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'ok', 'video' => $video]);
            break;
            
        case 'update_video':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('Missing video ID');
            
            $stmt = $pdo->prepare("UPDATE videos SET 
                                   name = ?, description = ?, category_id = ?, 
                                   is_active = ?, sort_order = ?
                                   WHERE id = ?");
            $stmt->execute([
                $data['name'] ?? '',
                $data['description'] ?? '',
                $data['category_id'] ?? null,
                $data['is_active'] ?? 1,
                $data['sort_order'] ?? 0,
                $id
            ]);
            
            echo json_encode(['status' => 'ok', 'message' => 'Video updated']);
            break;
            
        case 'delete_video':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('Missing video ID');
            
            // Get video info to delete files
            $stmt = $pdo->prepare("SELECT url, thumbnail FROM videos WHERE id = ?");
            $stmt->execute([$id]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($video) {
                // Delete video file
                $videoPath = $publicPathAbs . $video['url'];
                if (file_exists($videoPath)) {
                    unlink($videoPath);
                }
                
                // Delete thumbnail
                if ($video['thumbnail']) {
                    $thumbPath = $publicPathAbs . $video['thumbnail'];
                    if (file_exists($thumbPath)) {
                        unlink($thumbPath);
                    }
                }
            }
            
            // Delete from database (cascade will handle playlist_videos)
            $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['status' => 'ok', 'message' => 'Video deleted']);
            break;
            
        case 'upload_video':
            if (!isset($_FILES['video'])) {
                throw new Exception('No video file uploaded');
            }
            
            $file = $_FILES['video'];
            $name = $_POST['name'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
            $description = $_POST['description'] ?? '';
            // --- FIX START: Handle empty category value correctly ---
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            // --- FIX END ---
            
            // Validate file
            $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Invalid video type. Only MP4, WebM, and OGG are allowed.');
            }
            
            // Create upload directory
            $uploadDir = $publicPathAbs . '/videos/';
            $thumbDir = $publicPathAbs . '/videos/thumbnail/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!file_exists($thumbDir)) mkdir($thumbDir, 0755, true);
            
            // Generate unique filename
            $timestamp = date('Y-m-d-H-i-s');
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $safeName . '_' . $timestamp . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to save video file');
            }









// REPLACEMENT CODE for the thumbnail generation block

// Define paths to our new reliable shell scripts
//$scriptsDir = $spw->getProjectPath() '/bash/';

$scriptsDir = PROJECT_ROOT . '/bash/';
$getVideoInfoScript = $scriptsDir . 'get_video_info.sh';
$generateThumbnailScript = $scriptsDir . 'generate_thumbnail.sh';

// Generate thumbnail using ffmpeg via shell scripts
$thumbnailName = $safeName . '_' . $timestamp . '.jpg';
$thumbnailPath = $thumbDir . $thumbnailName;
$duration = 0;
$width = null;
$height = null;

// Check if our scripts exist and ffmpeg is likely available
if (file_exists($getVideoInfoScript) && file_exists($generateThumbnailScript)) {
    // Step 1: Get video duration and dimensions using ffprobe script
    $probeCommand = 'sh ' . escapeshellarg($getVideoInfoScript) . ' ' . escapeshellarg($filepath);
    $probeJson = trim(shell_exec($probeCommand . ' 2>&1'));
    $probeData = json_decode($probeJson, true);

    if ($probeData && isset($probeData['format'])) {
        if (isset($probeData['format']['duration'])) {
            $duration = (int)$probeData['format']['duration'];
        }
        foreach ($probeData['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $width = $stream['width'] ?? null;
                $height = $stream['height'] ?? null;
                break;
            }
        }
    }

    // Step 2: Generate thumbnail using ffmpeg script
    $thumbTime = min(1, max(0, $duration - 1));
    $thumbCommand = 'sh ' . escapeshellarg($generateThumbnailScript) . ' '
                    . escapeshellarg($filepath) . ' '
                    . escapeshellarg($thumbnailPath) . ' '
                    . escapeshellarg($thumbTime);
                    
    // The '2>&1' captures any error output for debugging
    $shellOutput = trim(shell_exec($thumbCommand . ' 2>&1'));
}

// Step 3: Verify thumbnail generation and create a fallback if it failed
if (!file_exists($thumbnailPath) || filesize($thumbnailPath) === 0) {
    // If ffmpeg failed or is not available, create a placeholder
    $img = imagecreatetruecolor(320, 180);
    $bg = imagecolorallocate($img, 200, 200, 200);
    imagefilledrectangle($img, 0, 0, 320, 180, $bg);
    imagejpeg($img, $thumbnailPath, 85);
    imagedestroy($img);
    
    // Log the error from ffmpeg if it exists
    if (!empty($shellOutput)) {
        error_log("FFmpeg thumbnail generation failed: " . $shellOutput);
    }
}













            
            // Insert into database
            $stmt = $pdo->prepare("INSERT INTO videos 
                                   (name, description, url, thumbnail, duration, type, 
                                    file_size, width, height, category_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $description,
                '/videos/' . $filename,
                '/videos/thumbnail/' . $thumbnailName,
                $duration,
                $file['type'],
                $file['size'],
                $width,
                $height,
                $categoryId
            ]);
            
            $videoId = $pdo->lastInsertId();
            
            echo json_encode([
                'status' => 'ok',
                'message' => 'Video uploaded successfully',
                'video_id' => $videoId
            ]);
            break;
            
        // Category management

        case 'list_categories':
            $stmt = $pdo->query("SELECT c.*, COUNT(v.id) as video_count
                                 FROM video_categories c
                                 LEFT JOIN videos v ON c.id = v.category_id
                                 GROUP BY c.id
                                 ORDER BY c.sort_order ASC, c.name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'ok', 'categories' => $categories]);
            break;




        case 'update_category':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('Missing category ID');

            $stmt = $pdo->prepare("UPDATE video_categories 
                                   SET name = ?, description = ?, sort_order = ? 
                                   WHERE id = ?");
            $stmt->execute([
                $data['name'] ?? '',
                $data['description'] ?? '',
                $data['sort_order'] ?? 0,
                $id
            ]);
            
            echo json_encode(['status' => 'ok']);
            break;


        case 'create_category':
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'] ?? '';
            $slug = $data['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
            
            $stmt = $pdo->prepare("INSERT INTO video_categories (name, slug, description, sort_order) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $slug,
                $data['description'] ?? '',
                $data['sort_order'] ?? 0
            ]);
            
            echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'delete_category':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            
            // Check if category has videos
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("Cannot delete category with $count videos. Reassign or delete videos first.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM video_categories WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['status' => 'ok']);
            break;
            
        // Playlist management
        case 'list_playlists':
            $stmt = $pdo->query("SELECT p.*, COUNT(pv.video_id) as video_count 
                                FROM video_playlists p 
                                LEFT JOIN playlist_videos pv ON p.id = pv.playlist_id 
                                GROUP BY p.id 
                                ORDER BY p.sort_order ASC, p.name ASC");
            $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'ok', 'playlists' => $playlists]);
            break;
            
        case 'create_playlist':
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'] ?? '';
            $slug = $data['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
            
            $stmt = $pdo->prepare("INSERT INTO video_playlists (name, slug, description) 
                                   VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $data['description'] ?? '']);
            
            echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'update_playlist':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE video_playlists SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$data['name'] ?? '', $data['description'] ?? '', $id]);
            
            echo json_encode(['status' => 'ok']);
            break;
            
        case 'delete_playlist':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            
            // Delete playlist (cascade will handle playlist_videos)
            $stmt = $pdo->prepare("DELETE FROM video_playlists WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['status' => 'ok']);
            break;
            
        case 'add_to_playlist':
            $data = json_decode(file_get_contents('php://input'), true);
            $playlistId = (int)($data['playlist_id'] ?? 0);
            $videoId = (int)($data['video_id'] ?? 0);
            
            // Get max sort order
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
                                   FROM playlist_videos WHERE playlist_id = ?");
            $stmt->execute([$playlistId]);
            $sortOrder = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO playlist_videos (playlist_id, video_id, sort_order) 
                                   VALUES (?, ?, ?)");
            $stmt->execute([$playlistId, $videoId, $sortOrder]);
            
            echo json_encode(['status' => 'ok']);
            break;
            
        case 'remove_from_playlist':
            $data = json_decode(file_get_contents('php://input'), true);
            $playlistId = (int)($data['playlist_id'] ?? 0);
            $videoId = (int)($data['video_id'] ?? 0);
            
            $stmt = $pdo->prepare("DELETE FROM playlist_videos WHERE playlist_id = ? AND video_id = ?");
            $stmt->execute([$playlistId, $videoId]);
            
            echo json_encode(['status' => 'ok']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    
    if (isset($fileLogger) && is_callable([$fileLogger, 'error'])) {
        $fileLogger->error(['video_admin_api error: ' => $e->getMessage()]);
    }
}
