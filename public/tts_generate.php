<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/VoicePool.php';

// Get POST parameters
$text  = $_POST['text'] ?? '';
$model = $_POST['model'] ?? 'ramona'; // default model

if (empty(trim($text))) {
    http_response_code(400);
    echo json_encode(['error' => 'Text cannot be empty']);
    exit;
}

try {
    $vp = new VoicePool();
    
    // Instead of saving, we capture output in memory
    $tmpFile = tempnam(sys_get_temp_dir(), 'tts_') . '.mp3';
    $vp->synthesize($text, $model, $tmpFile);

    // Set headers for download / streaming
    header('Content-Type: audio/mpeg');
    header('Content-Disposition: attachment; filename="tts_output.mp3"');
    header('Content-Length: ' . filesize($tmpFile));

    // Read the file to output
    readfile($tmpFile);

    // Clean up temporary file
    unlink($tmpFile);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
