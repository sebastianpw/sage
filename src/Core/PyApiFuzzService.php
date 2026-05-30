<?php
// src/Core/PyApiFuzzService.php

namespace App\Core;

use Exception;

/**
 * PyApiFuzzService
 *
 * Wrapper around the tablet-based PyAPI fuzz service.
 * Supports synchronous clustering as well as async submit/status polling.
 */
class PyApiFuzzService extends PyApiProxy
{
    public function getApiUrl()
    {
        $script = $this->spw->getProjectPath() . '/bash/pyapi_echo.sh';
        $apiUrl = trim(shell_exec('sh ' . escapeshellarg($script)));
        return $apiUrl;
    }

    public function __construct()
    {
        $this->spw = SpwBase::getInstance();

        $envUrl = getenv('SAGE_TABLET_PYAPI_URL');
        if ($envUrl) {
            $this->apiUrl = rtrim($envUrl, '/');
            return;
        }

        $script = $this->spw->getProjectPath() . '/bash/pyapi_echo.sh';
        $apiUrl = trim(shell_exec('sh ' . escapeshellarg($script)));

        $this->apiUrl = $apiUrl !== ''
            ? rtrim($apiUrl, '/')
            : 'http://127.0.0.1:8009';
    }

    /**
     * Normalize input items into the shape expected by the PyAPI service.
     *
     * Accepted input forms:
     * - ['id' => 'abc', 'text' => 'abc']
     * - ['id' => 'abc', 'text' => 'normalized abc']
     * - plain strings ['abc', 'def']
     */
    protected function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $index => $item) {
            if (is_string($item)) {
                $text = trim($item);
                if ($text === '') {
                    continue;
                }

                $normalized[] = [
                    'id' => $text,
                    'text' => $text,
                ];
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? trim((string)$item['id']) : '';
            $text = isset($item['text']) ? trim((string)$item['text']) : '';

            if ($text === '' && isset($item['value'])) {
                $text = trim((string)$item['value']);
            }

            if ($id === '' && $text !== '') {
                $id = $text;
            }

            if ($text === '' && $id !== '') {
                $text = $id;
            }

            if ($id === '' && $text === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id !== '' ? $id : (string)$index,
                'text' => $text,
            ];
        }

        return $normalized;
    }

    /**
     * Low-level JSON request helper for the fuzz endpoints.
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
            throw new Exception("cURL Error calling Python API: {$error}");
        }

        if ($httpCode !== 200) {
            $decoded = json_decode((string)$responseBody, true);
            if (is_array($decoded) && isset($decoded['detail'])) {
                $detail = is_array($decoded['detail']) ? json_encode($decoded['detail']) : (string)$decoded['detail'];
            } else {
                $detail = (string)$responseBody;
            }
            throw new Exception("Python API returned HTTP {$httpCode}: {$detail}");
        }

        $decoded = json_decode((string)$responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Run synchronous clustering and return the full response.
     *
     * @param array $items    Item payload, strings or ['id' => ..., 'text' => ...]
     * @param float $threshold Similarity threshold, e.g. 0.82
     */
    public function cluster(array $items, float $threshold = 0.82): array
    {
        $endpoint = $this->apiUrl . '/fuzz/cluster';

        $payload = [
            'items' => $this->normalizeItems($items),
            'threshold' => $threshold,
        ];

        return $this->requestJson($endpoint, 'POST', $payload, 300);
    }

    /**
     * Submit an async clustering job.
     *
     * Returns:
     * - status
     * - job_id
     * - status_url
     */
    public function clusterAsync(array $items, float $threshold = 0.82): array
    {
        $endpoint = $this->apiUrl . '/fuzz/cluster/async';

        $payload = [
            'items' => $this->normalizeItems($items),
            'threshold' => $threshold,
        ];

        return $this->requestJson($endpoint, 'POST', $payload, 30);
    }

    /**
     * Legacy-friendly alias for async submit.
     */
    public function submitClusterJob(array $items, float $threshold = 0.82): array
    {
        return $this->clusterAsync($items, $threshold);
    }

    /**
     * Poll job status.
     */
    public function getClusterJobStatus(string $jobId): array
    {
        $endpoint = $this->apiUrl . '/fuzz/cluster/status/' . rawurlencode($jobId);
        return $this->requestJson($endpoint, 'GET', null, 30);
    }

    /**
     * Poll until the job reaches success or error.
     *
     * @param string $jobId
     * @param int $pollIntervalMs
     * @param int $timeoutSeconds 0 means no timeout
     */
    public function waitForClusterJob(string $jobId, int $pollIntervalMs = 1500, int $timeoutSeconds = 0): array
    {
        $start = microtime(true);

        while (true) {
            $status = $this->getClusterJobStatus($jobId);

            $jobStatus = $status['status'] ?? '';
            if ($jobStatus === 'success' || $jobStatus === 'error' || $jobStatus === 'failed') {
                return $status;
            }

            if ($timeoutSeconds > 0 && (microtime(true) - $start) >= $timeoutSeconds) {
                throw new Exception("Timed out while waiting for fuzz job {$jobId}.");
            }

            usleep($pollIntervalMs * 1000);
        }
    }

    /**
     * Convenience helper: submit async job and wait for completion.
     */
    public function clusterAsyncAndWait(array $items, float $threshold = 0.82, int $pollIntervalMs = 1500, int $timeoutSeconds = 0): array
    {
        $submitted = $this->clusterAsync($items, $threshold);

        if (empty($submitted['job_id'])) {
            throw new Exception('Async fuzz job submission did not return a job_id.');
        }

        return $this->waitForClusterJob((string)$submitted['job_id'], $pollIntervalMs, $timeoutSeconds);
    }
}
