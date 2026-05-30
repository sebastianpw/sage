<?php
// public/video_extraction_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\FramesManager;
use App\Core\SpwBase;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST required');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $videoId = (int)($input['video_id'] ?? 0);
    $timestamp = (float)($input['timestamp'] ?? 0);

    if (!$videoId) throw new Exception('Missing Video ID');

    $spw = SpwBase::getInstance();
    $pdo = $spw->getPDO();
    $fm = FramesManager::getInstance();

    // 1. Get Video & Linked Animatic Details
    // We join with videos_2_animatics to find the parent animatic entity
    $sql = "SELECT v.*, va.to_id as animatic_id, a.name as animatic_name 
            FROM videos v 
            LEFT JOIN videos_2_animatics va ON v.id = va.from_id 
            LEFT JOIN animatics a ON va.to_id = a.id
            WHERE v.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) throw new Exception('Video not found');

    // 2. Prepare Paths
    $projectRoot = $spw->getProjectPath();
    $videoRelPath = ltrim($video['url'], '/');
    $videoFullPath = $projectRoot . '/public/' . $videoRelPath;

    if (!file_exists($videoFullPath)) {
        throw new Exception("Video file not found on disk: $videoRelPath");
    }

    // 3. Generate New Frame Filename using standard counter
    $basename = $fm->getNextFrameBasenameFromDB(); // e.g. frame0012345
    $outputRel = 'frames/' . $basename . '.png';
    $outputFull = $projectRoot . '/public/' . $outputRel;

    // Ensure frames dir exists
    $framesDir = dirname($outputFull);
    if (!is_dir($framesDir)) mkdir($framesDir, 0755, true);

    // 4. Run Extraction Script
    $script = $projectRoot . '/bash/extract_video_frame.sh';
    if (!file_exists($script)) throw new Exception("Extraction script not found");

    $cmd = sprintf(
        'sh %s %s %s %s 2>&1',
        escapeshellarg($script),
        escapeshellarg($videoFullPath),
        escapeshellarg($outputFull),
        escapeshellarg($timestamp)
    );

    $output = shell_exec($cmd);

    if (!file_exists($outputFull) || filesize($outputFull) === 0) {
        throw new Exception("FFmpeg failed to extract frame. Output: " . $output);
    }

    // 5. Database Registration
    $pdo->beginTransaction();
    try {
        // A. Create Map Run
        $animaticId = $video['animatic_id'] ?? 0;
        $entityType = $animaticId ? 'animatics' : 'videos';
        $entityId = $animaticId ? $animaticId : $videoId;
        
        $mapRunNote = "Extracted from video '{$video['name']}' at {$timestamp}s";
        $mapRunId = $fm->createMapRun($entityType, $mapRunNote);

        // B. Insert Frame
        $stmtFrame = $pdo->prepare("
            INSERT INTO frames 
            (map_run_id, name, filename, prompt, entity_type, entity_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $frameName = ($video['animatic_name'] ?? $video['name']) . '_extract_' . round($timestamp);
        $prompt = "Frame extracted from video at {$timestamp}s";
        
        $stmtFrame->execute([
            $mapRunId,
            $frameName,
            $outputRel,
            $prompt,
            $entityType,
            $entityId
        ]);
        $newFrameId = $pdo->lastInsertId();

        // C. Link to Animatic (frames_2_animatics)
        if ($animaticId) {
            $stmtLink = $pdo->prepare("INSERT IGNORE INTO frames_2_animatics (from_id, to_id) VALUES (?, ?)");
            $stmtLink->execute([$newFrameId, $animaticId]);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'ok',
            'message' => 'Frame extracted successfully',
            'frame_id' => $newFrameId,
            'filename' => $outputRel,
            'url' => '/' . $outputRel
        ]);

    } catch (Exception $dbEx) {
        $pdo->rollBack();
        if (file_exists($outputFull)) @unlink($outputFull);
        throw $dbEx;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
