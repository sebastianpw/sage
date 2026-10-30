<?php
// public/griditor_api.php
// Backend logic for the Griditor image cutting interface

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\FramesManager;
use App\Core\SpwBase;

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

try {
    if ($action === 'cut_grid') {
        $frameId = intval($data['frame_id'] ?? 0);
        $hGuides = $data['h_guides'] ?? [];
        $vGuides = $data['v_guides'] ?? [];
        
        if (!$frameId) {
            throw new Exception('Source frame_id is required');
        }

        $fm = FramesManager::getInstance();
        $spw = SpwBase::getInstance();
        $projectRoot = $spw->getProjectPath();

        // 1. Load the original frame to inherit mappings and metadata
        $orig = $fm->loadFrameRow($frameId);
        if (!$orig) throw new Exception("Source frame #$frameId not found.");

        $sourceFile = $orig['filename'];
        $sourceFullPath = $projectRoot . '/public/' . ltrim($sourceFile, '/');
        
        if (!file_exists($sourceFullPath)) {
            throw new Exception("Source image file missing: $sourceFile");
        }

        // 2. Dispatch to PyAPI
        $pyApiUrl = 'http://127.0.0.1:8009/griditor/cut';
        
        $postData = [
            'file'     => new CURLFile($sourceFullPath, mime_content_type($sourceFullPath), basename($sourceFullPath)),
            'h_guides' => json_encode($hGuides),
            'v_guides' => json_encode($vGuides)
        ];

        $ch = curl_init($pyApiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("PyAPI cURL error: $curlError");
        }
        if ($httpCode !== 200) {
            $err = json_decode($responseBody, true);
            throw new Exception("PyAPI Error ($httpCode): " . ($err['detail'] ?? $responseBody));
        }

        $pyRes = json_decode($responseBody, true);
        if (empty($pyRes['success']) || empty($pyRes['crops'])) {
            throw new Exception("Python API failed to return crops.");
        }

        // 3. Register slices in MariaDB
        // Create one map run for all cuts
        $mapRunId = $fm->createMapRun($orig['entity_type'] ?? 'sketches', "Grid Cut from Frame #$frameId");
        if (!$mapRunId) throw new Exception("Failed to create map run.");

        $newFrames = [];
        $framesDirRel = rtrim($spw->getFramesDirRel(), '/');

        foreach ($pyRes['crops'] as $idx => $tempCropPath) {
            $tempFullPath = $projectRoot . '/public/' . ltrim($tempCropPath, '/');
            if (!file_exists($tempFullPath)) continue;

            // Generate next canonical filename
            $basename = $fm->getNextFrameBasenameFromDB();
            $finalRel = $framesDirRel . '/' . $basename . '.png';
            $finalFull = $projectRoot . '/public/' . ltrim($finalRel, '/');

            // Move from PyAPI temp output to canonical frames dir
            if (!rename($tempFullPath, $finalFull)) {
                @unlink($tempFullPath);
                continue;
            }

            // Register frame
            $note = "Grid Slice " . ($idx + 1) . " of " . count($pyRes['crops']);
            $opts = [
                'tool'   => 'griditor',
                'mode'   => 'cut',
                'userId' => $userId,
                'note'   => $note,
                'coords' => ['h_guides' => $hGuides, 'v_guides' => $vGuides]
            ];

            // Uses existing PyAPI mapping copy mechanism
            $result = $fm->registerDerivedFrameFromOriginal($orig, $finalRel, $mapRunId, $opts);
            
            if (!empty($result['success'])) {
                // Instantly apply edit
                $fm->applyVersion($result['image_edit_id']);
                $newFrames[] = [
                    'frame_id' => $result['new_frame_id'],
                    'filename' => $finalRel
                ];
            }
        }

        echo json_encode([
            'success'    => true,
            'map_run_id' => $mapRunId,
            'count'      => count($newFrames),
            'frames'     => $newFrames,
            'message'    => "Successfully cut image into " . count($newFrames) . " pieces."
        ]);

    } else {
        throw new Exception("Unknown action: $action");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}