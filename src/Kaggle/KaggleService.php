<?php

namespace App\Kaggle;

class KaggleService
{
    private string $projectRoot;
    private string $primaryConfigDir;
    private ?string $kaggleBin = null;
    private ?KaggleAPI $kaggleClient = null;
    private string $apiBaseUrl;

    public function __construct(string $projectRoot, string $apiBaseUrl = 'http://127.0.0.1:8009/kaggle')
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->primaryConfigDir = $this->projectRoot . '/token/.kaggle';
        $this->kaggleBin = $this->findKaggleBinary();

        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->kaggleClient = new KaggleAPI($this->apiBaseUrl);
    }

    public function getPrimaryConfigDir(): string
    {
        return $this->primaryConfigDir;
    }

    public function getKaggleBinaryPath(): ?string
    {
        return $this->kaggleBin;
    }

    public function isKaggleCliAvailable(): bool
    {
        // keep compatibility: prefer checking local binary, but also check API status
        if ($this->kaggleBin !== null) return true;

        // fallback: if API reachable and operational -> treat as available (API-backed)
        if ($this->kaggleClient) {
            $status = $this->kaggleClient->getStatus();
            if ($status !== false && isset($status['status']) && $status['status'] === 'operational') {
                return true;
            }
        }
        return false;
    }

    private function findKaggleBinary(): ?string
    {
        $which = trim(shell_exec('which kaggle 2>/dev/null') ?: '');
        if ($which !== '' && is_executable($which)) {
            return $which;
        }

        $candidates = [
            $this->projectRoot . '/.local/bin/kaggle',
            $this->projectRoot . '/.local/bin/kaggle-cli',
            ($home = getenv('HOME')) ? $home . '/.local/bin/kaggle' : null,
            '/usr/local/bin/kaggle',
            '/usr/bin/kaggle',
        ];

        foreach ($candidates as $c) {
            if (empty($c)) continue;
            if (is_executable($c)) return $c;
        }

        return null;
    }

    private function getConfigCandidates(): array
    {
        $candidates = [];
        $candidates[] = $this->primaryConfigDir;

        $unique = [];
        foreach ($candidates as $c) {
            if (empty($c)) continue;
            $unique[$c] = true;
        }
        return array_keys($unique);
    }

    private function getPreferredConfigDir(): string
    {
        $candidates = $this->getConfigCandidates();
        foreach ($candidates as $c) {
            if (is_dir($c)) return $c;
        }
        if (!is_dir($this->primaryConfigDir)) {
            @mkdir($this->primaryConfigDir, 0700, true);
        }
        return $this->primaryConfigDir;
    }

    /**
     * Write token file to all candidate locations (best-effort). Returns array with written/failed lists.
     */
    public function setApiToken(string $username, string $key): array
    {
        $token = ['username' => $username, 'key' => $key];
        $json = json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $results = ['written' => [], 'failed' => []];
        foreach ($this->getConfigCandidates() as $dir) {
            try {
                if (!is_dir($dir)) {
                    if (!@mkdir($dir, 0700, true)) {
                        $results['failed'][] = $dir;
                        continue;
                    }
                }
                $path = rtrim($dir, '/') . '/kaggle.json';
                $bytes = @file_put_contents($path, $json);
                if ($bytes === false) {
                    $results['failed'][] = $dir;
                    continue;
                }
                @chmod($path, 0600);
                $results['written'][] = $path;
            } catch (\Throwable $e) {
                $results['failed'][] = $dir;
            }
        }
        return $results;
    }

    /**
     * Read token from first valid location found.
     */
    public function getApiToken(): ?array
    {
        foreach ($this->getConfigCandidates() as $dir) {
            $path = rtrim($dir, '/') . '/kaggle.json';
            if (!file_exists($path)) continue;
            $json = @file_get_contents($path);
            $arr = json_decode($json, true);
            if (is_array($arr) && !empty($arr['username']) && !empty($arr['key'])) return $arr;
        }
        return null;
    }

    /**
     * Return array of candidate project-local python site-package paths and related paths.
     */
    private function findProjectPythonPaths(): array
    {
        $candidates = [];

        // .local root & lib
        $candidates[] = $this->projectRoot . '/.local';
        $candidates[] = $this->projectRoot . '/.local/lib';

        // site-packages glob
        $glob = $this->projectRoot . '/.local/lib/python*/site-packages';
        $matches = glob($glob, GLOB_NOSORT);
        if (!empty($matches)) {
            foreach ($matches as $m) $candidates[] = $m;
        }

        // if package placed directly under .local/kaggle etc.
        $candidates[] = $this->projectRoot . '/.local/kaggle';

        // only existing directories
        $out = [];
        foreach (array_unique($candidates) as $c) {
            if (!empty($c) && is_dir($c)) $out[] = $c;
        }
        return $out;
    }

    public function getInstallCommandSuggestion(): string
    {
        // prefer original suggestion for local installs, but include API hint
        $sitePackages = glob($this->projectRoot . '/.local/lib/python*/site-packages', GLOB_NOSORT);
        if (!empty($sitePackages)) {
            if (preg_match('/python([0-9\.]+)/', $sitePackages[0], $m)) {
                $py = 'python' . ($m[1] ?? '3');
                return escapeshellcmd($py) . ' -m pip install --prefix=' . escapeshellarg($this->projectRoot . '/.local') . ' kaggle';
            }
        }
        return 'Use PyAPI at ' . $this->apiBaseUrl . ' or: python3 -m pip install --prefix=' . escapeshellarg($this->projectRoot . '/.local') . ' kaggle';
    }

    // ------------------------
    // Helper to normalize API responses into CLI-like run results
    // ------------------------
    private function apiToRunResult($apiResult, string $endpointLabel = ''): array
    {
        if ($apiResult === false) {
            return [
                'ok' => false,
                'cmd' => 'API: ' . $endpointLabel,
                'output' => 'API call failed',
                'hint' => $this->getInstallCommandSuggestion()
            ];
        }

        // If the API returns the 'status' wrapper
        if (is_array($apiResult) && isset($apiResult['status'])) {
            if ($apiResult['status'] === 'success') {
                // prefer stdout or data
                $out = '';
                if (isset($apiResult['stdout'])) $out = $apiResult['stdout'];
                elseif (isset($apiResult['data'])) $out = $apiResult['data'];
                elseif (isset($apiResult['message'])) $out = $apiResult['message'];

                return [
                    'ok' => true,
                    'cmd' => 'API: ' . $endpointLabel,
                    'output' => is_string($out) ? $out : json_encode($out),
                    'hint' => $this->getInstallCommandSuggestion()
                ];
            } else {
                $err = $apiResult['error'] ?? ($apiResult['stderr'] ?? json_encode($apiResult));
                return [
                    'ok' => false,
                    'cmd' => 'API: ' . $endpointLabel,
                    'output' => is_string($err) ? $err : json_encode($err),
                    'hint' => $this->getInstallCommandSuggestion()
                ];
            }
        }

        // fallback: return json-encoded result
        return [
            'ok' => true,
            'cmd' => 'API: ' . $endpointLabel,
            'output' => is_string($apiResult) ? $apiResult : json_encode($apiResult),
            'hint' => $this->getInstallCommandSuggestion()
        ];
    }

    // =============================================================================
    // LOCAL NOTEBOOKS (unchanged)
    // =============================================================================

    /**
     * Local notebooks scanning
     */
    public function listLocalNotebooks(string $relativeDir = '/notebooks/kaggle'): array
    {
        $dir = $this->projectRoot . $relativeDir;
        $result = [];
        if (!is_dir($dir)) return $result;

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'ipynb') {
                $path = $file->getRealPath();
                $result[] = [
                    'name' => $file->getBasename(),
                    'path' => $path,
                    'relative' => str_replace($this->projectRoot, '', $path),
                    'platform' => 'kaggle',
                    'folder' => dirname($path),
                ];
            }
        }
        return $result;
    }

    // =============================================================================
    // REMOTE KERNELS (migrated to use KaggleAPI)
    // =============================================================================

    /**
     * List remote kernels.
     * If $username is empty or "me", we request mine=true (authenticated user).
     * Otherwise we request user=<username> (public kernels only).
     *
     * Returns array of parsed kernel rows or debug keys when empty.
     */
    public function listRemoteKernels(?string $username = null)
    {
        // prepare parameters
        $page = 1;
        $sort_by = 'hotness';
        $page_size = 100;

        $useMine = false;
        if (empty($username) || strtolower($username) === 'me') {
            $useMine = true;
        }

        
        $res = $this->kaggleClient->listKernels(
            search: null,
            mine: $useMine,
            user: $useMine ? null : $username,
            dataset: null,
            competition: null,
            parent_kernel: null,
            sort_by: $sort_by,
            page: $page
        );
        
        if ($res === false) {
            return ['__error' => $this->kaggleClient->getLastError(), 'cmd' => 'API: /kaggle/kernels/list', 'hint' => 'Check PyAPI server and token'];
        }

        if (!isset($res['status']) || $res['status'] !== 'success') {
            return ['__error' => 'Unexpected API status', 'raw' => $res, 'cmd' => 'API: /kaggle/kernels/list'];
        }

        // Prefer parsed JSON if server provided it
        if (isset($res['parsed']) && is_array($res['parsed']) && count($res['parsed']) > 0) {
            return $res['parsed'];
        }

        // Get CSV text (cleaned by server)
        $csvData = $res['data'] ?? ($res['stdout'] ?? '');
        $cmd = $res['cmd'] ?? $res['command'] ?? 'API: /kaggle/kernels/list';
        $raw = $res['raw_stdout'] ?? ($res['raw'] ?? null);

        if (trim((string)$csvData) === '') {
            // nothing returned: attempt fallback if we originally requested user=<name>
            if (!$useMine && !empty($username)) {
                // try again with mine=true (maybe kernel is private)
                $fallback = $this->kaggleClient->listKernels($page, true, null, null, null, null, $sort_by, $page_size);
                if ($fallback !== false && isset($fallback['status']) && $fallback['status'] === 'success') {
                    // prefer parsed fallback
                    if (isset($fallback['parsed']) && is_array($fallback['parsed']) && count($fallback['parsed']) > 0) {
                        return $fallback['parsed'];
                    }
                    $csvData = $fallback['data'] ?? ($fallback['stdout'] ?? '');
                    $cmd = $fallback['cmd'] ?? $fallback['command'] ?? $cmd;
                    $raw = $fallback['raw_stdout'] ?? $raw;
                }
            }
            // still empty -> return debug info
            return ['__raw' => $csvData, '__raw_original' => $raw, '__cmd' => $cmd, 'hint' => 'No rows returned (private kernels require mine=true + correct token)'];
        }

        // Clean CSV: remove any leading lines that start with "Warning:" and find header
        $lines = preg_split("/\r\n|\n|\r/", $csvData);
        $filtered = [];
        foreach ($lines as $ln) {
            if (trim($ln) === '') continue;
            if (preg_match('/^\s*Warning:/i', $ln)) continue;
            $filtered[] = $ln;
        }

        // find header index (line that contains 'ref' and 'title' ideally)
        $headerIndex = null;
        foreach ($filtered as $i => $ln) {
            if (stripos($ln, 'ref') !== false && stripos($ln, 'title') !== false) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex !== null) {
            $filtered = array_slice($filtered, $headerIndex);
        }

        if (count($filtered) <= 1) {
            return ['__raw_cleaned' => implode("\n", $filtered), '__cmd' => $cmd];
        }

        // parse CSV
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, implode("\n", $filtered));
        rewind($stream);

        $headers = null;
        $items = [];
        while (($row = fgetcsv($stream, 0, ",", '"', "\\")) !== false) {
            if ($headers === null) {
                $headers = $row;
                continue;
            }
            if ($row === [null] || count($row) === 0) continue;
            $first = isset($row[0]) ? trim((string)$row[0]) : '';
            if ($first === '' || preg_match('/^[-\s]+$/', $first)) continue;

            $item = [];
            foreach ($row as $k => $v) {
                $key = $headers[$k] ?? $k;
                $item[$key] = $v;
            }
            $items[] = $item;
        }
        fclose($stream);

        if (empty($items)) {
            return ['__raw_cleaned' => implode("\n", $filtered), '__cmd' => $cmd];
        }

        return $items;
    }

    
    public function pushAndRunKernelFolder(string $folderPath): array
    {
        $folderPath = realpath($folderPath);
        if ($folderPath === false || !is_dir($folderPath)) {
            return ['success' => false, 'output' => 'Folder not found: ' . $folderPath, 'cmd' => '', 'hint' => ''];
        }
        $metaPath = $folderPath . '/kernel-metadata.json';
        if (!file_exists($metaPath)) {
            return ['success' => false, 'output' => "kernel-metadata.json not found in folder. Create kernel-metadata.json according to Kaggle's spec.", 'cmd' => '', 'hint' => ''];
        }

        $res = $this->kaggleClient->pushKernel($folderPath);

        if ($res === false) {
            return [
                'success' => false,
                'output' => $this->kaggleClient->getLastError() ?: 'API push failed',
                'cmd' => 'API: /kaggle/kernels/push',
                'hint' => $this->getInstallCommandSuggestion()
            ];
        }

        return [
            'success' => isset($res['status']) && $res['status'] === 'success',
            'output' => $res['stdout'] ?? $res['message'] ?? 'Push completed',
            'cmd' => 'API: /kaggle/kernels/push',
            'hint' => ''
        ];
    }

    public function fetchKernelOutput(string $kernelRef, string $destFolder): array
    {
        if (!is_dir($destFolder)) @mkdir($destFolder, 0777, true);

        $res = $this->kaggleClient->downloadKernelOutput($kernelRef, $destFolder);

        if ($res === false) {
            return [
                'success' => false,
                'output' => $this->kaggleClient->getLastError() ?: 'API output download failed',
                'cmd' => 'API: /kaggle/kernels/output',
                'hint' => 'Check if PyAPI server is running'
            ];
        }

        return [
            'success' => isset($res['status']) && $res['status'] === 'success',
            'output' => $res['stdout'] ?? $res['message'] ?? 'Output downloaded',
            'cmd' => 'API: /kaggle/kernels/output',
            'hint' => ''
        ];
    }

    public function getKernelStatus(string $kernelRef): array
    {
        $res = $this->kaggleClient->getKernelStatus($kernelRef);

        if ($res === false) {
            return [
                'ok' => false,
                'output' => $this->kaggleClient->getLastError(),
                'cmd' => 'API: /kaggle/kernels/status',
                'hint' => 'Check if PyAPI server is running'
            ];
        }

        return [
            'ok' => isset($res['status']) && $res['status'] === 'success',
            'output' => $res['info'] ?? $res['stdout'] ?? 'Status retrieved',
            'cmd' => 'API: /kaggle/kernels/status',
            'hint' => ''
        ];
    }

    /**
     * Push and run a remote kernel (triggers "Save Version & Run All")
     */
    public function pushAndRunRemoteKernel(string $kernelRef): array
    {
        if (empty($kernelRef)) {
            return ['success' => false, 'output' => 'Kernel reference is required', 'cmd' => '', 'hint' => ''];
        }

        // Create a temporary directory for the kernel
        $tmpDir = sys_get_temp_dir() . '/kaggle_run_' . md5($kernelRef . time());
        @mkdir($tmpDir, 0777, true);

        try {
            // Step 1: Pull the kernel using API
            $pullResult = $this->kaggleClient->pullKernel($kernelRef, $tmpDir, true);

            if ($pullResult === false || !isset($pullResult['status']) || $pullResult['status'] !== 'success') {
                $this->recursiveRemoveDirectory($tmpDir);
                return [
                    'success' => false,
                    'output' => "Failed to pull kernel:\n" . ($this->kaggleClient->getLastError() ?: json_encode($pullResult)),
                    'cmd' => 'API: /kaggle/kernels/pull',
                    'hint' => 'Check if PyAPI server is running'
                ];
            }

            // Step 2: Push it back using API (creates new version / run)
            $pushResult = $this->kaggleClient->pushKernel($tmpDir);

            // Clean up temp directory
            $this->recursiveRemoveDirectory($tmpDir);

            if ($pushResult === false) {
                return [
                    'success' => false,
                    'output' => $this->kaggleClient->getLastError(),
                    'cmd' => 'API: /kaggle/kernels/push',
                    'hint' => 'Check if PyAPI server is running'
                ];
            }

            return [
                'success' => isset($pushResult['status']) && $pushResult['status'] === 'success',
                'output' => $pushResult['stdout'] ?? $pushResult['message'] ?? 'Kernel run initiated',
                'cmd' => 'API: /kaggle/kernels/push',
                'hint' => ''
            ];
        } catch (\Exception $e) {
            if (is_dir($tmpDir)) {
                $this->recursiveRemoveDirectory($tmpDir);
            }
            return [
                'success' => false,
                'output' => 'Exception: ' . $e->getMessage(),
                'cmd' => '',
                'hint' => ''
            ];
        }
    }

    /**
     * Helper to recursively remove a directory
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Pull a kernel from Kaggle to local notebooks folder
     */
    public function pullKernelToLocal(string $kernelRef): array
    {
        if (empty($kernelRef)) {
            return ['success' => false, 'output' => 'Kernel reference is required', 'folder' => null];
        }

        $basename = basename($kernelRef);
        $targetDir = $this->projectRoot . '/notebooks/kaggle/' . $basename;

        // Create target directory
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $result = $this->kaggleClient->pullKernel($kernelRef, $targetDir, true);

        if ($result === false) {
            return [
                'success' => false,
                'output' => $this->kaggleClient->getLastError() ?: 'Failed to pull kernel',
                'folder' => null
            ];
        }

        if (isset($result['status']) && $result['status'] === 'success') {
            return [
                'success' => true,
                'output' => $result['stdout'] ?? 'Kernel pulled successfully',
                'folder' => $targetDir
            ];
        } else {
            return [
                'success' => false,
                'output' => $result['message'] ?? 'Failed to pull kernel',
                'folder' => null
            ];
        }
    }

    /**
     * Set Zrok API token
     */
    public function setZrokToken(string $token): array
    {
        $tokenDir = $this->projectRoot . '/token';
        if (!is_dir($tokenDir)) {
            @mkdir($tokenDir, 0700, true);
        }

        $zrokPath = $tokenDir . '/.zrok_api_key';
        $result = @file_put_contents($zrokPath, $token);

        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to write Zrok token'];
        }

        @chmod($zrokPath, 0600);
        return ['success' => true, 'message' => 'Zrok token saved', 'path' => $zrokPath];
    }

    /**
     * Get Zrok API token
     */
    public function getZrokToken(): ?string
    {
        $zrokPath = $this->projectRoot . '/token/.zrok_api_key';
        if (!file_exists($zrokPath)) {
            return null;
        }

        $token = @file_get_contents($zrokPath);
        return $token !== false ? trim($token) : null;
    }
    
    
    
    
    
    
    
    
    
    
    public function hasRequiredTokens(): bool
    {
        $kaggleToken = $this->getApiToken();
        $zrokToken = $this->getZrokToken();

        return !empty($kaggleToken) && !empty($zrokToken);
    }
    
    
    
    
    
    
    /**
     * Syncs the Zrok token dataset to Kaggle using a robust "update, then create" strategy.
     * It first attempts to update (version) the dataset. If that fails for any reason
     * (e.g., it doesn't exist), it falls back to creating it fresh.
     * This is handled entirely in PHP and provides a clean, definitive success message.
     */
    public function syncZrokTokenDataset(): array
    {
        $token = $this->getApiToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No Kaggle API token configured'];
        }
    
        $zrokToken = $this->getZrokToken();
        if (!$zrokToken) {
            return ['success' => false, 'message' => 'No Zrok token configured'];
        }
    
        $username = $token['username'];
        $datasetSlug = 'sage-zrok-token';
        $datasetRef = $username . '/' . $datasetSlug;
    
        // Create temporary directory and prepare dataset files
        $tmpDir = sys_get_temp_dir() . '/kaggle_zrok_' . time() . '_' . bin2hex(random_bytes(4));
        @mkdir($tmpDir, 0777, true);
    
        try {
            file_put_contents($tmpDir . '/.zrok_api_key', $zrokToken);
            $metadata = [
                'title' => 'sage-zrok-token',
                'id' => $datasetRef,
                'licenses' => [['name' => 'CC0-1.0']],
                'isPrivate' => true
            ];
            file_put_contents($tmpDir . '/dataset-metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // --- STEP 1: Attempt to UPDATE (version) the dataset first. ---
            $message = 'Automated zrok token sync at ' . date('Y-m-d H:i:s');
            $versionResult = $this->kaggleClient->versionDataset($tmpDir, $message);

            // --- STEP 2: If the update was successful, we are done. ---
            if ($versionResult !== false && isset($versionResult['status']) && $versionResult['status'] === 'success') {
                $this->recursiveRemoveDirectory($tmpDir);
                return [
                    'success' => true,
                    'message' => '✔ Zrok token dataset was UPDATED successfully on Kaggle.',
                ];
            }

            // --- STEP 3: If update failed, FALLBACK to CREATING the dataset. ---
            $createResult = $this->kaggleClient->createDataset($tmpDir);

            // --- STEP 4: If the creation was successful, we are done. ---
            if ($createResult !== false && isset($createResult['status']) && $createResult['status'] === 'success') {
                $this->recursiveRemoveDirectory($tmpDir);
                return [
                    'success' => true,
                    'message' => '✔ Zrok token dataset was CREATED successfully on Kaggle.',
                ];
            }

            // --- STEP 5: If BOTH attempts failed, report a final error. ---
            $this->recursiveRemoveDirectory($tmpDir);
            return [
                'success' => false,
                'message' => '✘ Failed to sync Zrok dataset. Both update and create attempts failed.',
                'output' => $this->kaggleClient->getLastError() ?: json_encode($createResult), // Show error from the last attempt
            ];

        } catch (\Exception $e) {
            if (is_dir($tmpDir)) {
                $this->recursiveRemoveDirectory($tmpDir);
            }
            return [
                'success' => false,
                'message' => 'Exception during dataset sync: ' . $e->getMessage()
            ];
        }
    }

    
    
    
    
    
    
    
    
    
    
    
    
    
    

    /**
     * Fix kernel metadata: ensure GPU, internet, and Zrok dataset are configured
     */
    public function fixKernelMetadata(string $folderPath): array
    {
        $metadataPath = rtrim($folderPath, '/') . '/kernel-metadata.json';

        if (!file_exists($metadataPath)) {
            return [
                'success' => false,
                'fixed' => [],
                'message' => 'kernel-metadata.json not found in folder'
            ];
        }

        $json = @file_get_contents($metadataPath);
        if ($json === false) {
            return [
                'success' => false,
                'fixed' => [],
                'message' => 'Failed to read kernel-metadata.json'
            ];
        }

        $metadata = json_decode($json, true);
        if (!is_array($metadata)) {
            return [
                'success' => false,
                'fixed' => [],
                'message' => 'Invalid JSON in kernel-metadata.json'
            ];
        }

        $fixed = [];
        $modified = false;

        // Auto-create id from username and title if missing
        $kaggleTokenForId = $this->getApiToken();
        if ($kaggleTokenForId && isset($kaggleTokenForId['username'])) {
            $username = $kaggleTokenForId['username'];
            if (!isset($metadata['id']) || trim((string)$metadata['id']) === '') {
                $titleForSlug = isset($metadata['title']) && trim((string)$metadata['title']) !== ''
                    ? $metadata['title']
                    : basename(rtrim($folderPath, '/'));

                // Slugify
                $slug = strtolower((string)$titleForSlug);
                $slug = preg_replace('/[^a-z0-9-_]+/', '-', $slug);
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-_');
                if ($slug === '') {
                    $slug = 'notebook';
                }

                $metadata['id'] = $username . '/' . $slug;
                $fixed[] = 'id';
                $modified = true;
            }
        }

        // Ensure enable_gpu is true
        if (!isset($metadata['enable_gpu']) || $metadata['enable_gpu'] !== true) {
            $metadata['enable_gpu'] = true;
            $fixed[] = 'enable_gpu';
            $modified = true;
        }

        // Ensure enable_internet is true
        if (!isset($metadata['enable_internet']) || $metadata['enable_internet'] !== true) {
            $metadata['enable_internet'] = true;
            $fixed[] = 'enable_internet';
            $modified = true;
        }

        // Add Zrok token dataset if we have tokens configured
        $kaggleToken = $this->getApiToken();
        $zrokToken = $this->getZrokToken();

        if ($kaggleToken && $zrokToken) {
            $username = $kaggleToken['username'];
            $zrokDatasetRef = $username . '/sage-zrok-token';

            if (!isset($metadata['dataset_sources'])) {
                $metadata['dataset_sources'] = [];
            }

            $hasZrokDataset = false;
            foreach ($metadata['dataset_sources'] as $ds) {
                if (is_string($ds) && stripos($ds, 'sage-zrok-token') !== false) {
                    $hasZrokDataset = true;
                    break;
                }
            }

            if (!$hasZrokDataset) {
                $metadata['dataset_sources'][] = $zrokDatasetRef;
                $fixed[] = 'zrok_dataset';
                $modified = true;
            }
        }

        if ($modified) {
            $newJson = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $writeResult = @file_put_contents($metadataPath, $newJson);

            if ($writeResult === false) {
                return [
                    'success' => false,
                    'fixed' => [],
                    'message' => 'Failed to write updated kernel-metadata.json'
                ];
            }

            return [
                'success' => true,
                'fixed' => $fixed,
                'message' => 'Fixed: ' . implode(', ', $fixed)
            ];
        }

        return [
            'success' => true,
            'fixed' => [],
            'message' => 'Metadata already correct'
        ];
    }

    /**
     * Create default kernel metadata with GPU, internet, and Zrok dataset
     */
    public function createDefaultMetadata(string $folderPath): array
    {
        $folderPath = rtrim($folderPath, '/');
        $metadataPath = $folderPath . '/kernel-metadata.json';

        if (file_exists($metadataPath)) {
            return [
                'success' => false,
                'message' => 'Metadata file already exists'
            ];
        }

        // Find the .ipynb file
        $ipynbFiles = glob($folderPath . '/*.ipynb');
        if (empty($ipynbFiles)) {
            return [
                'success' => false,
                'message' => 'No .ipynb file found in folder'
            ];
        }

        $notebookFile = basename($ipynbFiles[0]);
        $slug = basename($folderPath);
        $title = basename($ipynbFiles[0], '.ipynb');

        // Get username from token
        $token = $this->getApiToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => 'No API token configured'
            ];
        }

        $username = $token['username'];

        // Create dataset sources array
        $datasetSources = [];

        // Add Zrok dataset if token exists
        $zrokToken = $this->getZrokToken();
        if ($zrokToken) {
            $datasetSources[] = $username . '/sage-zrok-token';
        }

        // Create default metadata
        $metadata = [
            'id' => $username . '/' . $slug,
            'title' => $title,
            'code_file' => $notebookFile,
            'language' => 'python',
            'kernel_type' => 'notebook',
            'is_private' => true,
            'enable_gpu' => true,
            'enable_tpu' => false,
            'enable_internet' => true,
            'dataset_sources' => $datasetSources,
            'competition_sources' => [],
            'kernel_sources' => [],
            'model_sources' => []
        ];

        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $writeResult = @file_put_contents($metadataPath, $json);

        if ($writeResult === false) {
            return [
                'success' => false,
                'message' => 'Failed to write metadata file'
            ];
        }

        return [
            'success' => true,
            'message' => 'Created default metadata with GPU, internet, and Zrok dataset'
        ];
    }
    
    
    
    
    
   /**
 * Slugify a string to a Kaggle-like kernel slug.
 */
private function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    // replace non-alphanumeric with hyphen
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    // collapse hyphens
    $s = preg_replace('/-+/', '-', $s);
    $s = trim($s, '-');
    if ($s === '') $s = 'notebook';
    return $s;
}

/**
 * Build canonical kernel id for a local notebook folder.
 * - If kernel-metadata.json contains an 'id', return that.
 * - Otherwise use username/slug (username from token if available).
 * Returns null if folder not accessible.
 */
private function localKernelIdFromFolder(string $folderPath): ?string
{
    $folderPath = rtrim($folderPath, '/');
    $metaPath = $folderPath . '/kernel-metadata.json';
    if (file_exists($metaPath)) {
        $json = @file_get_contents($metaPath);
        if ($json !== false) {
            $arr = json_decode($json, true);
            if (is_array($arr) && !empty($arr['id']) && is_string($arr['id'])) {
                return trim($arr['id']);
            }
        }
    }

    // fallback: use username/slug from folder name or first ipynb
    $username = null;
    $token = $this->getApiToken();
    if ($token && isset($token['username'])) {
        $username = $token['username'];
    }

    // try folder basename
    $slugSource = basename($folderPath);
    // if there's a single ipynb with a different name, prefer it as title
    $ipynb = glob($folderPath . '/*.ipynb');
    if (!empty($ipynb)) {
        $base = basename($ipynb[0], '.ipynb');
        // if base looks meaningful and differs, use it
        if ($base !== '' && strlen($base) > 1) {
            $slugSource = $base;
        }
    }

    $slug = $this->slugify($slugSource);
    if ($username) {
        return $username . '/' . $slug;
    }
    return $slug; // best-effort without username
}

/**
 * Merge local notebook entries with remote parsed kernel rows.
 *
 * $localList: array of local notebook entries (structure like listLocalNotebooks returns)
 * $remoteParsed: array of parsed remote kernel rows (each row contains 'ref', 'title', etc.)
 *
 * Returns local list enriched with:
 *   - 'kernel_id' (derived)
 *   - 'is_remote' => bool
 *   - 'remote_ref' => matched remote ref or null
 *   - 'remote_row' => full remote row (if matched)
 */
public function mergeLocalWithRemote(array $localList, array $remoteParsed = []): array
{
    // build lookup maps for remote refs
    $remoteByRef = [];
    $remoteBySlug = []; // slug -> list of refs
    foreach ($remoteParsed as $r) {
        if (!is_array($r)) continue;
        $ref = isset($r['ref']) ? trim($r['ref']) : null;
        if (!$ref) continue;
        $remoteByRef[strtolower($ref)] = $r;

        // slug is the part after slash
        $parts = explode('/', $ref, 2);
        $slug = (count($parts) === 2) ? $parts[1] : $parts[0];
        $slug = strtolower($slug);
        if (!isset($remoteBySlug[$slug])) $remoteBySlug[$slug] = [];
        $remoteBySlug[$slug][] = $r;
    }

    $out = [];
    foreach ($localList as $item) {
        // existing local fields remain
        $entry = $item;
        $folder = isset($item['folder']) ? $item['folder'] : null;

        $kernelId = null;
        if ($folder && is_dir($folder)) {
            $kernelId = $this->localKernelIdFromFolder($folder);
        } else {
            // try to derive from local 'relative' or 'path' or 'name'
            $maybe = $item['relative'] ?? $item['path'] ?? $item['name'] ?? '';
            if ($maybe) {
                $kernelId = $this->slugify(basename((string)$maybe));
                $token = $this->getApiToken();
                if ($token && isset($token['username'])) {
                    $kernelId = $token['username'] . '/' . $kernelId;
                }
            }
        }

        $entry['kernel_id'] = $kernelId;
        $entry['is_remote'] = false;
        $entry['remote_ref'] = null;
        $entry['remote_row'] = null;

        if ($kernelId) {
            $lk = strtolower($kernelId);
            // direct exact match owner/slug -> remote
            if (isset($remoteByRef[$lk])) {
                $entry['is_remote'] = true;
                $entry['remote_ref'] = $remoteByRef[$lk]['ref'];
                $entry['remote_row'] = $remoteByRef[$lk];
            } else {
                // try slug-only match (basename)
                $parts = explode('/', $lk, 2);
                $slugOnly = (count($parts) === 2) ? $parts[1] : $parts[0];
                if (isset($remoteBySlug[$slugOnly])) {
                    // if multiple matches, pick the one with same author if available
                    $pick = $remoteBySlug[$slugOnly][0];
                    // try match by username if we have it
                    $token = $this->getApiToken();
                    if ($token && !empty($token['username'])) {
                        $expectedRef = strtolower($token['username'] . '/' . $slugOnly);
                        if (isset($remoteByRef[$expectedRef])) {
                            $pick = $remoteByRef[$expectedRef];
                        }
                    }
                    $entry['is_remote'] = true;
                    $entry['remote_ref'] = $pick['ref'];
                    $entry['remote_row'] = $pick;
                } else {
                    // no match found - maybe local metadata uses different slug; try last-resort fuzzy match on title
                    $localName = strtolower($item['name'] ?? '');
                    foreach ($remoteParsed as $r) {
                        if (isset($r['title']) && strtolower($r['title']) === $localName) {
                            $entry['is_remote'] = true;
                            $entry['remote_ref'] = $r['ref'];
                            $entry['remote_row'] = $r;
                            break;
                        }
                    }
                }
            }
        }

        $out[] = $entry;
    }

    return $out;
}
    
    
    
    
    
    
    
    
    
}
