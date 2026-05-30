<?php
/**
 * Inline TTS API Endpoint
 * Receives text -> Calls PyApiVoiceService -> Saves Temp WAV -> Returns URL
 */
require_once __DIR__ . '/bootstrap.php'; // Adjust based on your actual bootstrap path
require __DIR__ . '/env_locals.php';

use App\Core\PyApiVoiceService;

header('Content-Type: application/json');

// Simple Garbage Collection (Delete temp files older than 1 hour)
$tempDir = __DIR__ . '/audios/temp_inline';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// 10% chance to cleanup old files on request
if (rand(10, 100) === 1) {
    foreach (glob("$tempDir/*.wav") as $file) {
        if (is_file($file) && (time() - filemtime($file) > 3600)) {
            unlink($file);
        }
    }
}

try {
    // Get JSON Input
    $input = json_decode(file_get_contents('php://input'), true);
    $text = trim($input['text'] ?? '');
    $model = $input['model'] ?? 'en_US-amy-medium'; // Default model

    if (empty($text)) {
        throw new Exception("No text provided");
    }

    // Initialize Service
    // Ensure PyApiProxy knows where to find Python (defaults to 127.0.0.1:8009)
    $ttsService = new PyApiVoiceService();

    // Call Synthesis (this handles the polling loop)
    $wavData = $ttsService->synthesize($text, $model);

    if (!$wavData) {
        throw new Exception("Received empty audio data");
    }

    // Save to Temporary File
    $filename = 'tts_' . md5($text . time() . rand()) . '.wav';
    $filePath = $tempDir . '/' . $filename;
    
    if (file_put_contents($filePath, $wavData) === false) {
        throw new Exception("Failed to write temporary audio file");
    }

    // Return Public URL
    echo json_encode([
        'status' => 'success',
        'url' => '/audios/temp_inline/' . $filename,
        'size' => strlen($wavData)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
