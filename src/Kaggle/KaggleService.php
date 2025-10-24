<?php

namespace App\Kaggle;

class KaggleService
{
    private string $projectRoot;
    private string $primaryConfigDir;
    private ?string $kaggleBin = null;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->primaryConfigDir = $this->projectRoot . '/token/.kaggle';
        $this->kaggleBin = $this->findKaggleBinary();
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
        return $this->kaggleBin !== null;
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
        $sitePackages = glob($this->projectRoot . '/.local/lib/python*/site-packages', GLOB_NOSORT);
        if (!empty($sitePackages)) {
            // infer python version from first match
            if (preg_match('/python([0-9\.]+)/', $sitePackages[0], $m)) {
                $py = 'python' . ($m[1] ?? '3');
                return escapeshellcmd($py) . ' -m pip install --prefix=' . escapeshellarg($this->projectRoot . '/.local') . ' kaggle';
            }
        }
        // generic suggestion
        return 'python3 -m pip install --prefix=' . escapeshellarg($this->projectRoot . '/.local') . ' kaggle';
    }

    /**
     * CORE: robust runner for kaggle CLI with extended PYTHONPATH injection and diagnostics.
     * Returns ['ok'=>bool,'cmd'=>string,'output'=>string,'hint'=>string]
     */
    private function runKaggleCommand(string $args): array
    {
        $cfg = $this->getPreferredConfigDir();
        $projLocalBin = $this->projectRoot . '/.local/bin';
        $projPaths = $this->findProjectPythonPaths();

        // build PYTHONPATH with all existing candidate paths
        $pythonpath = implode(':', $projPaths);
        $envParts = [];
        $envParts[] = 'KAGGLE_CONFIG_DIR=' . escapeshellarg($cfg);
        // ensure project-local bin is first on PATH
        $envParts[] = 'PATH=' . escapeshellarg($projLocalBin) . ':$PATH';
        if (!empty($pythonpath)) {
            $envParts[] = 'PYTHONPATH=' . escapeshellarg($pythonpath) . ':$PYTHONPATH';
        }
        $envPrefix = implode(' ', $envParts);

        $attempts = [];

        // 1) Preferred: run the binary directly (it should use the project's .local site-packages because PYTHONPATH is set)
        if ($this->kaggleBin !== null) {
            $cmd = $envPrefix . ' ' . escapeshellarg($this->kaggleBin) . ' ' . $args . ' 2>&1';
            $attempts[] = $cmd;
            $out = @shell_exec($cmd);
            $trim = is_string($out) ? trim($out) : '';
            // if successful output (and not ModuleNotFoundError) return
            if ($trim !== '' && stripos($trim, 'ModuleNotFoundError') === false) {
                return ['ok' => true, 'cmd' => $cmd, 'output' => $trim, 'hint' => $this->getInstallCommandSuggestion()];
            }
            // otherwise we'll gather diagnostics below
        }

        // 2) Try a list of absolute python executables (do not use bare 'python3')
        $absolutePythonCandidates = [
            '/data/data/com.termux/files/usr/bin/python3',
            '/data/data/com.termux/files/usr/bin/python',
            '/usr/bin/python3',
            '/usr/bin/python',
            '/usr/local/bin/python3',
            '/usr/local/bin/python',
            '/bin/python3',
            '/bin/python',
        ];
        foreach ($absolutePythonCandidates as $pyExec) {
            if (!is_executable($pyExec)) continue;
            $cmd = $envPrefix . ' ' . escapeshellarg($pyExec) . ' -m kaggle ' . $args . ' 2>&1';
            $attempts[] = $cmd;
            $out = @shell_exec($cmd);
            $trim = is_string($out) ? trim($out) : '';
            if ($trim !== '' && stripos($trim, 'ModuleNotFoundError') === false) {
                return ['ok' => true, 'cmd' => $cmd, 'output' => $trim, 'hint' => $this->getInstallCommandSuggestion()];
            }
        }

        // 3) As a last resort, attempt to run the script file with absolute python executables
        if ($this->kaggleBin !== null) {
            foreach ($absolutePythonCandidates as $pyExec) {
                if (!is_executable($pyExec)) continue;
                $cmd = $envPrefix . ' ' . escapeshellarg($pyExec) . ' ' . escapeshellarg($this->kaggleBin) . ' ' . $args . ' 2>&1';
                $attempts[] = $cmd;
                $out = @shell_exec($cmd);
                $trim = is_string($out) ? trim($out) : '';
                if ($trim !== '' && stripos($trim, 'ModuleNotFoundError') === false) {
                    return ['ok' => true, 'cmd' => $cmd, 'output' => $trim, 'hint' => $this->getInstallCommandSuggestion()];
                }
            }
        }

        // If nothing worked, gather diagnostics to help debug where the package actually lives and what python sees.
        $diagnostics = [];

        // 1) show the kaggle launcher shebang + head (if exists)
        if ($this->kaggleBin !== null && is_readable($this->kaggleBin)) {
            $headCmd = 'head -n 40 ' . escapeshellarg($this->kaggleBin) . ' 2>&1';
            $diagnostics[] = "=== LAUNCHER HEAD (" . $this->kaggleBin . ") ===\n" . trim(shell_exec($headCmd));
        }

        // 2) list project .local layout (helpful to see where packages were installed)
        $lsCmd = 'ls -la ' . escapeshellarg($this->projectRoot . '/.local') . ' 2>&1';
        $diagnostics[] = "=== PROJECT .local (ls) ===\n" . trim(shell_exec($lsCmd));

        // 3) for each absolute python candidate, capture sys.executable, sys.path and whether it can import 'kaggle'
        foreach ($absolutePythonCandidates as $pyExec) {
            if (!is_executable($pyExec)) {
                $diagnostics[] = "=== PYTHON CHECK: $pyExec (not executable) ===";
                continue;
            }
            // build a python one-liner that prints exe, paths and whether it can import kaggle
            $pyOneLiner = 'import sys, pkgutil, json; '
                . 'info = {"exe":sys.executable, "path": sys.path, "loader": None}; '
                . 'ldr = pkgutil.find_loader("kaggle"); '
                . 'info["loader"] = str(ldr); '
                . 'print(json.dumps(info, default=str))';
            $cmd = $envPrefix . ' ' . escapeshellarg($pyExec) . ' -c ' . escapeshellarg($pyOneLiner) . ' 2>&1';
            $out = @shell_exec($cmd);
            $diagnostics[] = "=== PYTHON CHECK: $pyExec ===\n" . trim($out);
        }

        $lastOutput = $trim ?? '';
        if ($lastOutput === '') {
            $lastOutput = "No usable output from any attempts. Tried commands:\n" . implode("\n", $attempts);
        }

        // aggregate result and diagnostics
        $fullOutput = trim($lastOutput) . "\n\nDIAGNOSTICS:\n" . implode("\n\n", $diagnostics);

        return [
            'ok' => false,
            'cmd' => end($attempts) ?: '',
            'output' => $fullOutput,
            'hint' => $this->getInstallCommandSuggestion()
        ];
    }

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

    public function listRemoteKernels(string $username)
    {
        $args = 'kernels list --user ' . escapeshellarg($username) . ' --csv';
        $res = $this->runKaggleCommand($args);

        if ($res['ok'] === false) {
            return ['__error' => $res['output'], 'cmd' => $res['cmd'] ?? '', 'hint' => $res['hint'] ?? $this->getInstallCommandSuggestion()];
        }

        $out = $res['output'] ?? '';
        if (trim($out) === '') {
            return ['__raw' => $out, 'cmd' => $res['cmd'] ?? '', 'hint' => $res['hint'] ?? $this->getInstallCommandSuggestion()];
        }

        $items = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $out);
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
            return ['__raw' => $out, 'cmd' => $res['cmd'] ?? '', 'hint' => $res['hint'] ?? $this->getInstallCommandSuggestion()];
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
        $args = 'kernels push -p ' . escapeshellarg($folderPath);
        $res = $this->runKaggleCommand($args);

        return ['success' => $res['ok'], 'output' => $res['output'] ?: 'No output from kaggle CLI.', 'cmd' => $res['cmd'] ?? '', 'hint' => $res['hint'] ?? $this->getInstallCommandSuggestion()];
    }

    public function fetchKernelOutput(string $kernelRef, string $destFolder): array
    {
        if (!is_dir($destFolder)) @mkdir($destFolder, 0777, true);
        $args = 'kernels output ' . escapeshellarg($kernelRef) . ' -p ' . escapeshellarg($destFolder);
        $res = $this->runKaggleCommand($args);
        return ['success' => $res['ok'], 'output' => $res['output'] ?: 'No output from kaggle CLI.', 'cmd' => $res['cmd'] ?? '', 'hint' => $res['hint'] ?? $this->getInstallCommandSuggestion()];
    }

    public function getKernelStatus(string $kernelRef): array
    {
        $args = 'kernels status ' . escapeshellarg($kernelRef);
        $res = $this->runKaggleCommand($args);
        return ['ok' => $res['ok'], 'output' => $res['output'], 'cmd' => $res['cmd'] ?? '', 'hint' => $res['hint'] ?? ''];
    }



/**
 * Push and run a remote kernel (triggers "Save Version & Run All")
 * This creates a new version of an existing kernel and runs it
 */
public function pushAndRunRemoteKernel(string $kernelRef): array
{
    // The kaggle kernels push command with --kernel-opt will trigger a new version run
    // Format: username/kernel-name
    if (empty($kernelRef)) {
        return ['success' => false, 'output' => 'Kernel reference is required', 'cmd' => '', 'hint' => ''];
    }

    // Use the kernels push command with metadata to trigger a run
    // Note: This requires a local copy with kernel-metadata.json
    // For remote-only runs, we use the "kernels pull" then "kernels push" approach
    
    // First, try direct push with --new-version flag if kernel exists locally
    // Otherwise, we need to use the API differently
    
    // The Kaggle CLI doesn't have a direct "run remote kernel" command
    // We need to use: kaggle kernels pull, then kaggle kernels push
    
    // Create a temporary directory for the kernel
    $tmpDir = sys_get_temp_dir() . '/kaggle_run_' . md5($kernelRef . time());
    @mkdir($tmpDir, 0777, true);
    
    try {
        // Step 1: Pull the kernel
        $pullArgs = 'kernels pull ' . escapeshellarg($kernelRef) . ' -p ' . escapeshellarg($tmpDir);
        $pullRes = $this->runKaggleCommand($pullArgs);
        
        if (!$pullRes['ok']) {
            return [
                'success' => false, 
                'output' => "Failed to pull kernel:\n" . ($pullRes['output'] ?? 'Unknown error'),
                'cmd' => $pullRes['cmd'] ?? '',
                'hint' => $pullRes['hint'] ?? $this->getInstallCommandSuggestion()
            ];
        }
        
        // Step 2: Push it back (this creates a new version and runs it)
        $pushArgs = 'kernels push -p ' . escapeshellarg($tmpDir);
        $pushRes = $this->runKaggleCommand($pushArgs);
        
        // Clean up temp directory
        $this->recursiveRemoveDirectory($tmpDir);
        
        return [
            'success' => $pushRes['ok'],
            'output' => $pushRes['output'] ?: 'Kernel run initiated',
            'cmd' => $pushRes['cmd'] ?? '',
            'hint' => $pushRes['hint'] ?? $this->getInstallCommandSuggestion()
        ];
        
    } catch (\Exception $e) {
        // Clean up on error
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
 * Pull a kernel from Kaggle to local notebooks folder using kpull.sh approach
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

    // Pull kernel with metadata
    $args = 'kernels pull ' . escapeshellarg($kernelRef) . ' -p ' . escapeshellarg($targetDir) . ' --metadata';
    $res = $this->runKaggleCommand($args);

    if ($res['ok']) {
        return [
            'success' => true,
            'output' => $res['output'] ?: 'Kernel pulled successfully',
            'folder' => $targetDir
        ];
    } else {
        return [
            'success' => false,
            'output' => $res['output'] ?: 'Failed to pull kernel',
            'folder' => null
        ];
    }
}

/**
 * Fix kernel metadata to ensure GPU and internet are enabled
 * Returns ['success' => bool, 'fixed' => array of fixed fields, 'message' => string]
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
 */





















/**
 * Create default kernel metadata for a notebook folder
 * Returns ['success' => bool, 'message' => string]
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
        'dataset_sources' => [],
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
        'message' => 'Created default metadata with GPU and internet enabled'
    ];
}

 */













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
        
        // Check if dataset exists
        $checkArgs = 'datasets list --user ' . escapeshellarg($username) . ' --csv';
        $checkRes = $this->runKaggleCommand($checkArgs);
        
        $datasetExists = false;
        if ($checkRes['ok']) {
            $datasetExists = stripos($checkRes['output'], $datasetSlug) !== false;
        }
        
        // Create or update dataset
        if ($datasetExists) {
            // Update existing dataset (create new version)
            $args = 'datasets version -p ' . escapeshellarg($tmpDir) . ' -m "Updated Zrok token"';
        } else {
            // Create new dataset
            $args = 'datasets create -p ' . escapeshellarg($tmpDir);
        }
        
        $res = $this->runKaggleCommand($args);
        
        // Clean up temp directory
        @unlink($zrokFile);
        @unlink($metadataFile);
        @rmdir($tmpDir);
        
        if ($res['ok']) {
            return [
                'success' => true,
                'message' => $datasetExists ? 'Zrok token dataset updated' : 'Zrok token dataset created',
                'dataset_ref' => $datasetRef,
                'output' => $res['output']
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to sync Zrok dataset',
                'output' => $res['output']
            ];
        }
        
    } catch (\Exception $e) {
        // Clean up on error
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
 * Add Zrok token dataset to kernel metadata
 * Modified version of fixKernelMetadata that also adds the dataset dependency
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
        
        // Initialize dataset_sources if not exists
        if (!isset($metadata['dataset_sources'])) {
            $metadata['dataset_sources'] = [];
        }
        
        // Check if zrok dataset is already in sources
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
 * Updated createDefaultMetadata to include Zrok dataset
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
