<?php

class VoicePool
{
	private string $apiUrl;
	private \App\Core\SpwBase $spw;

    public function __construct(string $apiUrl = 'http://localhost:8008')
    {
	$this->spw = \App\Core\SpwBase::getInstance();
        $this->apiUrl = rtrim($apiUrl, '/');
    }

    /**
     * List available TTS models (voices)
     *
     * @return array
     * @throws Exception
     */
    public function listModels(): array
    {
        $url = $this->apiUrl . '/models/';
        $response = $this->sendGetRequest($url);

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception("Failed to decode JSON response from TTS API.");
        }

        return $data;
    }

    /**
     * Generate TTS audio file
     *
     * @param string $text Text to synthesize
     * @param string $model Model/voice to use
     * @param string $outputFile Path to save MP3/WAV file
     * @return bool
     * @throws Exception
     */
    public function synthesize(string $text, string $model, string $outputFile): bool
    {
        $url = $this->apiUrl . '/synthesize/?model=' . urlencode($model) . '&text=' . urlencode($text);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("CURL error: " . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            throw new Exception("TTS API returned HTTP $status");
        }

        // Save output
        $written = file_put_contents($outputFile, $response);
        if ($written === false) {
            throw new Exception("Failed to write audio file to $outputFile");
        }

        return true;
    }

    private function sendPostRequest(string $url): string
    {
	// $this->spw->getFileLogger()->debug(['curl request to URL: '=>$url]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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

    private function sendGetRequest(string $url): string
{
    // Optional: log the request
    // $this->spw->getFileLogger()->debug(['curl GET request to URL:' => $url]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // get response as string

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


