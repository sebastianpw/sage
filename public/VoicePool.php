<?php

class VoicePool
{
    private string $apiUrl;
    private \App\Core\SpwBase $spw;

    public function __construct(string $apiUrl = 'http://localhost:8009') // Updated port to 8009
    {
        $this->spw = \App\Core\SpwBase::getInstance();
        $this->apiUrl = rtrim($apiUrl, '/');
    }

    /**
     * List available TTS models (voices)
     * Returns array of models: [['id' => '...', 'name' => '...', ...], ...]
     */
    public function listModels(): array
    {
        // Now pointing to the voicepool endpoint
        $url = $this->apiUrl . '/voicepool/models'; 
        
        try {
            $response = $this->sendGetRequest($url);
        } catch (Exception $e) {
            // Fallback empty if API is down, to prevent page crash
            return [];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception("Failed to decode JSON response from TTS API.");
        }

        // Handle the new PyApi response format { count: X, models: [...] }
        if (isset($data['models']) && is_array($data['models'])) {
            return $data['models'];
        }

        return [];
    }

    /**
     * Synchronize API models into the audio_voice_identity table.
     * Keeps local descriptions but adds new files found on the server.
     */
    public function syncFromApiToDb(PDO $pdo): array 
    {
        $apiModels = $this->listModels();
        $stats = ['added' => 0, 'updated' => 0, 'total' => count($apiModels)];

        if (empty($apiModels)) {
            return $stats;
        }

        // Prepare statements
        // We map API 'id' (e.g. en_US-amy-medium) to DB 'name'
        // We map API 'path' to DB 'model_path'
        $stmtCheck = $pdo->prepare("SELECT id FROM audio_voice_identity WHERE name = :name");
        $stmtInsert = $pdo->prepare("INSERT INTO audio_voice_identity (name, description, model_path) VALUES (:name, :desc, :path)");
        $stmtUpdate = $pdo->prepare("UPDATE audio_voice_identity SET model_path = :path WHERE id = :id");

        foreach ($apiModels as $model) {
            // Identifier from API
            $modelName = $model['id']; 
            $modelDesc = $model['name'] . " (" . $model['language'] . " - " . $model['quality'] . ")";
            $modelPath = $model['path'] ?? ''; // or use $model['id'] if path is relative

            // Check existence
            $stmtCheck->execute(['name' => $modelName]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update technical path just in case
                $stmtUpdate->execute(['path' => $modelPath, 'id' => $existing['id']]);
                $stats['updated']++;
            } else {
                // Insert new
                $stmtInsert->execute([
                    'name' => $modelName,
                    'desc' => $modelDesc,
                    'path' => $modelPath
                ]);
                $stats['added']++;
            }
        }

        return $stats;
    }

    /**
     * Generate TTS audio file (WAV)
     */
    public function synthesize(string $text, string $model, string $outputFile): bool
    {
        // Updated URL for voicepool
        $url = $this->apiUrl . '/voicepool/synthesize?model=' . urlencode($model) . '&text=' . urlencode($text);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Important for binary audio data
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("CURL error: " . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            // Try to read error message from response
            $msg = substr(strip_tags($response), 0, 200);
            throw new Exception("TTS API returned HTTP $status: $msg");
        }

        // Save output
        $written = file_put_contents($outputFile, $response);
        if ($written === false) {
            throw new Exception("Failed to write audio file to $outputFile");
        }

        return true;
    }

    private function sendGetRequest(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5s timeout for listing

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("CURL error: " . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            throw new Exception("TTS API returned HTTP $status");
        }

        return $response;
    }
}
