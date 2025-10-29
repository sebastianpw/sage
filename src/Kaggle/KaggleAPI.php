<?php
namespace App\Kaggle;

/**
 * Kaggle API PHP Client (improved)
 * - Robust URL join
 * - Supports GET query params
 * - Returns structured diagnostics on HTTP/non-JSON responses
 * - Returns false only on curl-level errors
 */
class KaggleAPI
{
    private string $baseUrl;
    private int $timeout;
    private ?string $lastError = null;

    public function __construct(string $baseUrl = "http://127.0.0.1:8009/kaggle", int $timeout = 300)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->lastError = null;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Internal request helper
     *
     * @param string $endpoint e.g. '/kernels/list' or 'kernels/list'
     * @param array|null $data JSON body (for POST) or query params (for GET)
     * @param string $method 'GET' or 'POST'
     * @return array|false decoded JSON OR structured array {_http_code, _raw} OR false on curl error
     */
    private function call(string $endpoint, ?array $data = null, string $method = 'POST')
    {
        $this->lastError = null;

        // Robust join: ensure exactly one slash between base and endpoint
        $endpoint = '/' . ltrim($endpoint, '/');
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        // If GET and data provided -> append query string
        if (strtoupper($method) === 'GET' && !empty($data)) {
            $qs = http_build_query($data);
            $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Let us capture the body even for HTTP >= 400 so we can inspect it
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $headers = [
            'Accept: application/json',
        ];

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($data !== null) {
                $payload = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($payload);
            } else {
                // POST with empty body — still set content-type
                $headers[] = 'Content-Type: application/json';
            }
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            $this->lastError = "cURL error ({$curlErrNo}): {$curlError}";
            return false;
        }

        // Normalize response string
        $raw = $response === false ? '' : (string)$response;

        // Try to decode JSON
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Attach diagnostic metadata
            $decoded['_http_code'] = $httpCode;
            $decoded['_raw'] = $raw;
            // If HTTP error status, still return array (caller will inspect _http_code)
            if ($httpCode >= 400) {
                $this->lastError = "HTTP {$httpCode}";
            }
            return $decoded;
        }

        // Not valid JSON — return structured diagnostic so caller can decide
        $this->lastError = "Non-JSON response or JSON decode error: " . json_last_error_msg();
        return [
            '_http_code' => $httpCode,
            '_raw' => $raw
        ];
    }

    // -----------------------------
    // High-level wrappers (match your previous API)
    // -----------------------------

    // Competitions
    public function listCompetitions($search = null, $category = null, $sortBy = 'latestDeadline', $page = 1)
    {
        return $this->call('/competitions/list', [
            'search' => $search,
            'category' => $category,
            'sort_by' => $sortBy,
            'page' => (int)$page
        ], 'POST');
    }

    public function downloadCompetition($competition, $fileName = null, $force = false, $quiet = false)
    {
        return $this->call('/competitions/download', [
            'competition' => $competition,
            'file_name' => $fileName,
            'force' => (bool)$force,
            'quiet' => (bool)$quiet
        ], 'POST');
    }

    public function submitToCompetition($competition, $filePath, $message)
    {
        return $this->call('/competitions/submit', [
            'competition' => $competition,
            'file_path' => $filePath,
            'message' => $message
        ], 'POST');
    }

    // Datasets
    public function listDatasets($search = null, $sortBy = 'hottest', $size = null, $fileType = null, $license = null, $tags = null, $user = null, $mine = false, $page = 1, $maxSize = 20)
    {
        $payload = [
            'search' => $search,
            'sort_by' => $sortBy,
            'size' => $size,
            'file_type' => $fileType,
            'license' => $license,
            'tags' => $tags,
            'user' => $user,
            'mine' => (bool)$mine,
            'page' => (int)$page,
            'max_size' => (int)$maxSize
        ];
        return $this->call('/datasets/list', $payload, 'POST');
    }

    public function listDatasetFiles($dataset)
    {
        return $this->call('/datasets/files', ['dataset' => $dataset], 'POST');
    }

    public function downloadDataset($dataset, $fileName = null, $unzip = true, $force = false, $outputDir = null)
    {
        return $this->call('/datasets/download', [
            'dataset' => $dataset,
            'file_name' => $fileName,
            'unzip' => (bool)$unzip,
            'force' => (bool)$force,
            'output_dir' => $outputDir
        ], 'POST');
    }

    public function createDataset($path, $public = false, $quiet = false, $dirMode = 'zip')
    {
        return $this->call('/datasets/create', [
            'path' => $path,
            'public' => (bool)$public,
            'quiet' => (bool)$quiet,
            'dir_mode' => $dirMode
        ], 'POST');
    }

    // Kernels
    public function listKernels($search = null, $mine = false, $user = null, $dataset = null, $competition = null, $parent_kernel = null, $sort_by = 'hotness', $page = 1)
    {
        return $this->call('/kernels/list', [
            'search' => $search,
            'mine' => (bool)$mine,
            'user' => $user,
            'dataset' => $dataset,
            'competition' => $competition,
            'parent_kernel' => $parent_kernel,
            'sort_by' => $sort_by,
            'page' => (int)$page
        ], 'POST');
    }

    public function pushKernel($path)
    {
        return $this->call('/kernels/push', ['path' => $path], 'POST');
    }

    public function pullKernel($kernel, $path = null, $metadata = true)
    {
        return $this->call('/kernels/pull', [
            'kernel' => $kernel,
            'path' => $path,
            'metadata' => (bool)$metadata
        ], 'POST');
    }

    public function downloadKernelOutput($kernel, $path = null, $force = false, $quiet = false)
    {
        return $this->call('/kernels/output', [
            'kernel' => $kernel,
            'path' => $path,
            'force' => (bool)$force,
            'quiet' => (bool)$quiet
        ], 'POST');
    }

    public function getKernelStatus($kernel)
    {
        return $this->call('/kernels/status', ['kernel' => $kernel], 'POST');
    }

    // Config
    public function viewConfig()
    {
        return $this->call('/config/view', null, 'GET');
    }

    public function setConfig($key, $value)
    {
        return $this->call('/config/set', ['key' => $key, 'value' => $value], 'POST');
    }

    // Status
    public function getStatus()
    {
        return $this->call('/status', null, 'GET');
    }
}
