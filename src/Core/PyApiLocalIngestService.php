<?php
// src/Core/PyApiLocalIngestService.php
// ─────────────────────────────────────────────────────────────────────────────
// PyApiLocalIngestService
// Wraps the local PyAPI background job engine for high-volume DB ingestion.
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Core;

use Exception;

class PyApiLocalIngestService extends PyApiProxy
{
    public function __construct()
    {
        $this->spw = SpwBase::getInstance();
        // Force routing to LOCALHOST because only the local device has direct MariaDB access
        $this->apiUrl = 'http://127.0.0.1:8009';
    }

    /**
     * Start the async ingestion process.
     */
    public function startIngest(array $clusters, int $maxCandidates): array
    {
        $endpoint = $this->apiUrl . '/fuzz_ingest/start';
        $payload = [
            'clusters' => $clusters,
            'max_candidates' => $maxCandidates
        ];
        return $this->requestJson($endpoint, 'POST', $payload, 30);
    }

    /**
     * Poll ingestion status.
     */
    public function getIngestStatus(string $jobId): array
    {
        $endpoint = $this->apiUrl . '/fuzz_ingest/status/' . rawurlencode($jobId);
        return $this->requestJson($endpoint, 'GET', null, 30);
    }

    /**
     * Low-level JSON request helper.
     */
    protected function requestJson(string $endpoint, string $method = 'GET', ?array $payload = null, int $timeout = 30): array
    {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload !== null ? json_encode($payload) : '{}');
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error calling Local Ingest PyAPI: {$error}");
        }

        if ($httpCode !== 200) {
            $decoded = json_decode((string)$responseBody, true);
            if (is_array($decoded) && isset($decoded['detail'])) {
                $detail = is_array($decoded['detail']) ? json_encode($decoded['detail']) : (string)$decoded['detail'];
            } else {
                $detail = (string)$responseBody;
            }
            throw new Exception("Local PyAPI returned HTTP {$httpCode}: {$detail}");
        }

        $decoded = json_decode((string)$responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }

        return $decoded;
    }
}
