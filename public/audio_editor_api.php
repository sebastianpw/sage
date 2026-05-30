<?php
// public/audio_editor_api.php
// Handles audio cutting: PHP does DB work, Shell does FFmpeg work.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;

header('Content-Type: application/json');

try {
    $spw = SpwBase::getInstance();
    $pdo = $spw->getPDO();

    // 1. Validate Inputs
    $sourceFile = $_POST['source_file'] ?? '';
    $start = (float)($_POST['start'] ?? 0);
    $end = (float)($_POST['end'] ?? 0);
    $entityType = $_POST['entity_type'] ?? '';
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $parentAudioId = (int)($_POST['parent_audio_id'] ?? 0);

    if (empty($sourceFile) || ($end <= $start)) {
        throw new Exception("Invalid parameters: Source file missing or end time before start.");
    }

    // 2. Register Map Run (Log the action)
    $stmt = $pdo->prepare("INSERT INTO map_runs (entity_type, note, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
    $stmt->execute([
        $entityType,
        "Audio Cut from Audio #{$parentAudioId} [{$start}-{$end}s]"
    ]);
    $mapRunId = $pdo->lastInsertId();

    // 3. Generate New Filename (Update Counter)
    // We use LAST_INSERT_ID to atomically increment and get the new ID
    $pdo->exec("UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1)");
    $nextAudioId = $pdo->lastInsertId();
    
    // Generate filename: audio0001234.wav
    $basename = 'audio' . str_pad($nextAudioId, 7, '0', STR_PAD_LEFT);
    $filename = $basename . '.wav';
    $relPath = 'audios/' . $filename;

    // 4. Resolve System Paths
    $projectRoot = $spw->getProjectPath();
    
    // Clean source path
    $sourceRel = ltrim(parse_url($sourceFile, PHP_URL_PATH), '/');
    $absSource = $projectRoot . '/public/' . $sourceRel;
    $absDest = $projectRoot . '/public/' . $relPath;
    $scriptPath = $projectRoot . '/bash/cut_audio.sh';

    if (!file_exists($absSource)) {
        throw new Exception("Source file not found on server: $sourceRel");
    }
    if (!file_exists($scriptPath)) {
        throw new Exception("Cut script not found at: $scriptPath");
    }

    // 5. Call Shell Script (FFmpeg Only)
    // Usage: sh cut_audio.sh <input> <start> <end> <output>
    $cmd = sprintf(
        'sh %s %s %s %s %s 2>&1',
        escapeshellarg($scriptPath),
        escapeshellarg($absSource),
        escapeshellarg($start),
        escapeshellarg($end),
        escapeshellarg($absDest)
    );

    $output = shell_exec($cmd);

    // 6. Verify Output
    if (!file_exists($absDest) || filesize($absDest) === 0) {
        error_log("Audio Cut Failed. Output: $output");
        throw new Exception("FFmpeg processing failed. Output: " . substr($output, 0, 200));
    }

    // 7. Insert into Database
    $safeName = "Cut_{$start}_{$end}";
    
    $stmt = $pdo->prepare("INSERT INTO audios (name, filename, entity_type, entity_id, map_run_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$safeName, $relPath, $entityType, $entityId, $mapRunId]);
    $newAudioId = $pdo->lastInsertId();

    // 8. Link to Entity (if table exists)
    $mappingTable = "audios_2_" . preg_replace('/[^a-zA-Z0-9_]/', '', $entityType);
    
    // Check if mapping table exists to prevent errors
    $checkTable = $pdo->query("SHOW TABLES LIKE '$mappingTable'");
    if ($checkTable->rowCount() > 0 && $entityId > 0) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO `$mappingTable` (from_id, to_id) VALUES (?, ?)");
        $stmt->execute([$newAudioId, $entityId]);
    }

    echo json_encode([
        'status' => 'ok', 
        'message' => 'Audio cut created successfully', 
        'id' => $newAudioId,
        'filename' => $relPath
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
