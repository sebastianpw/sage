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

        /*
        $candidates[] = $this->projectRoot . '/.local/.config/kaggle';
        if (!empty($this->kaggleBin)) {
            $binDir = dirname($this->kaggleBin);
            $localRoot = dirname($binDir);
            $candidates[] = $localRoot . '/.config/kaggle';
        }
        $home = getenv('HOME') ?: null;
        if ($home) {
            $candidates[] = $home . '/.config/kaggle';
            $candidates[] = $home . '/.kaggle';
        }
         */

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

    public function listRemoteKernels(string $username)
    {
        // Use the API client which mirrors the CLI parameters
        $res = $this->kaggleClient->listKernels(null, false, $username, null, null, null, 'hotness', 1);

        if ($res === false) {
            return ['__error' => $this->kaggleClient->getLastError(), 'cmd' => 'API: /kaggle/kernels/list', 'hint' => 'Check if PyAPI server is running at ' . $this->apiBaseUrl];
        }

        if (isset($res['status']) && $res['status'] === 'success') {
            $csvData = $res['data'] ?? '';
            if (trim($csvData) === '') {
                return ['__raw' => $csvData, 'cmd' => 'API: /kaggle/kernels/list', 'hint' => ''];
            }

            $items = [];
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $csvData);
            rewind($stream);

            $headers = null;
            while (($row = fgetcsv($stream, 0, ",", '"', "\\")) !== false) {
                if ($headers === null) {
                    $headers = $row;
                    continue;
                }
                if ($row === [null] || count($row) === 0) continue;
                $item = [];
                foreach ($row as $k => $v) {
                    $key = $headers[$k] ?? $k;
                    $item[$key] = $v;
                }
                $items[] = $item;
            }
            fclose($stream);

            if (empty($items)) {
                return ['__raw' => $csvData, 'cmd' => 'API: /kaggle/kernels/list', 'hint' => ''];
            }

            return $items;
        }

        return [
            '__error' => 'Unexpected API response',
            'cmd' => 'API: /kaggle/kernels/list',
            'hint' => 'Check PyAPI server logs'
        ];
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

    /**
     * Check if both Kaggle and Zrok tokens are configured
     */
    public function hasRequiredTokens(): bool
    {
        $kaggleToken = $this->getApiToken();
        $zrokToken = $this->getZrokToken();

        return !empty($kaggleToken) && !empty($zrokToken);
    }

    /**
     * Create or update the private Zrok token dataset on Kaggle
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

        // Create temporary directory for dataset
        $tmpDir = sys_get_temp_dir() . '/kaggle_zrok_' . time();
        @mkdir($tmpDir, 0777, true);

        try {
            // Write .zrok_api_key file
            $zrokFile = $tmpDir . '/.zrok_api_key';
            file_put_contents($zrokFile, $zrokToken);

            // Create dataset-metadata.json
            $metadata = [
                'title' => 'sage-zrok-token',
                'id' => $datasetRef,
                'licenses' => [['name' => 'CC0-1.0']],
                'isPrivate' => true
            ];

            $metadataFile = $tmpDir . '/dataset-metadata.json';
            file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Check if dataset exists using API
            $checkResult = $this->kaggleClient->listDatasets(null, 'hottest', null, null, null, null, $username, true);

            $datasetExists = false;
            if ($checkResult !== false && isset($checkResult['status']) && $checkResult['status'] === 'success') {
                $datasetExists = stripos($checkResult['data'], $datasetSlug) !== false;
            }

            // Create or update dataset using API
            if ($datasetExists) {
                // Update dataset (call createDataset for simplicity, server may interpret as new version)
                $result = $this->kaggleClient->createDataset($tmpDir, false, false);
            } else {
                // Create new dataset
                $result = $this->kaggleClient->createDataset($tmpDir, false, false);
            }

            // Clean up temp directory
            @unlink($zrokFile);
            @unlink($metadataFile);
            @rmdir($tmpDir);

            if ($result !== false && isset($result['status']) && $result['status'] === 'success') {
                return [
                    'success' => true,
                    'message' => $datasetExists ? 'Zrok token dataset updated' : 'Zrok token dataset created',
                    'dataset_ref' => $datasetRef,
                    'output' => $result['stdout'] ?? $result['message'] ?? ''
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to sync Zrok dataset',
                    'output' => $this->kaggleClient->getLastError() ?: json_encode($result)
                ];
            }

        } catch (\Exception $e) {
            if (is_dir($tmpDir)) {
                $this->recursiveRemoveDirectory($tmpDir);
            }
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
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
}
