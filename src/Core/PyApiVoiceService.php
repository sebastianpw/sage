<?php
namespace App\Core;

use App\Core\PyApiProxy;
use Exception;

/**
 * Service Wrapper for the Voicepool TTS Python Service.
 * Handles async task submission and polling.
 */
class PyApiVoiceService extends PyApiProxy
{
    
   public function __construct()
    {
        $this->spw = SpwBase::getInstance();
    
        $script = $this->spw->getProjectPath() . '/bash/pyapi_echo.sh';
        $apiUrl = trim(shell_exec('sh ' . escapeshellarg($script)));
    
        $this->apiUrl = $apiUrl !== ''
            ? rtrim($apiUrl, '/')
            : 'http://127.0.0.1:8009';
    }
    
    /**
     * Get list of available voice models from Python
     */
    public function getModels(): array
    {
        $endpoint = $this->apiUrl . '/voicepool/models';
        $response = $this->executeGetRequest($endpoint);
        return json_decode($response, true) ?? ['count' => 0, 'models' => []];
    }

    /**
     * Synthesize text to audio.
     * Submits task, polls for completion, and returns raw WAV audio bytes.
     * 
     * @param string $text The text to speak
     * @param string $model The voice model ID (e.g., 'en_US-amy-medium')
     * @param int $timeoutSeconds Max time to wait for generation
     * @return string|null Raw audio binary data
     * @throws Exception
     */
    public function synthesize(string $text, string $model = 'en_US-libritts_r-medium', int $timeoutSeconds = 300): ?string
    {
        // 1. Submit Synthesis Task
        $endpoint = $this->apiUrl . '/voicepool/synthesize';
        $postData = json_encode([
            'text' => $text,
            'model' => $model
        ]);

        // We use a custom curl call here because PyApiProxy expects array postData by default
        // and standard PyApiProxy logic is heavily tied to multipart/form-data for images.
        // We need application/json here.
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            throw new Exception("Failed to start TTS task: " . ($err ?: "HTTP $httpCode"));
        }

        $json = json_decode($response, true);
        $taskId = $json['task_id'] ?? null;

        if (!$taskId) {
            throw new Exception("No task ID returned from TTS service");
        }

        // 2. Poll for Completion
        $waited = 0;
        $pollInterval = 1; // 1 second

        while ($waited < $timeoutSeconds) {
            sleep($pollInterval);
            $waited += $pollInterval;

            $statusUrl = $this->apiUrl . "/voicepool/status/" . $taskId;
            
            $ch = curl_init($statusUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Don't fail on 404/500 immediately, let logic handle it
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            // If we get audio back (Content-Type header check or raw bytes check)
            if ($code === 200) {
                if (strpos($contentType, 'audio/') !== false) {
                    return $res; // This is the binary WAV data
                }

                // Still JSON? Check status
                $statusJson = json_decode($res, true);
                $status = $statusJson['status'] ?? 'UNKNOWN';

                if ($status === 'FAILED') {
                    throw new Exception("TTS Task failed: " . ($statusJson['error'] ?? 'Unknown error'));
                }
                // If PENDING or PROCESSING, continue loop
            } else {
                throw new Exception("Error polling TTS status: HTTP $code");
            }
        }

        throw new Exception("TTS generation timed out after $timeoutSeconds seconds.");
    }
}
