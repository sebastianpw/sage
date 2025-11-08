<?php
// public/kaggle.php - Unified notebook management

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Kaggle\KaggleService;

$spw = \App\Core\SpwBase::getInstance();
$projectRoot = $spw->getProjectPath();
$kaggle = new KaggleService($projectRoot);

$pageTitle = 'Kaggle Notebooks - Stable Diffusion Control';

$messages = [];

// Handle AJAX requests
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'status') {
        $kernelRef = $_GET['kernel_ref'] ?? '';
        if ($kernelRef) {
            $result = $kaggle->getKernelStatus($kernelRef);
            echo json_encode($result);
        } else {
            echo json_encode(['ok' => false, 'output' => 'No kernel ref provided']);
        }
    } else {
        echo json_encode(['ok' => false, 'output' => 'Unknown ajax action']);
    }
    exit;
}

// Handle POST actions (single consolidated block)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_token') {
        $username = trim($_POST['kaggle_username'] ?? '');
        $key = trim($_POST['kaggle_key'] ?? '');
        $zrokToken = trim($_POST['zrok_token'] ?? '');

        // Validate fields
        if ($username === '' || $key === '' || $zrokToken === '') {
            $messages[] = ['type' => 'error', 'text' => 'Kaggle username, API key, and Zrok token are all required'];
        } else {
            // Save Kaggle token
            $res = $kaggle->setApiToken($username, $key);
            $written = $res['written'] ?? [];
            $failed = $res['failed'] ?? [];

            // Save Zrok token
            $zrokRes = $kaggle->setZrokToken($zrokToken);

            if (!empty($zrokRes['success'])) {
                // Sync Zrok token to Kaggle dataset
                $syncRes = $kaggle->syncZrokTokenDataset();

                if (!empty($syncRes['success'])) {
                    $txt = "✓ All credentials saved successfully!\n\n";
                    $txt .= "Kaggle token saved to:\n" . implode("\n", $written) . "\n\n";
                    $txt .= "Zrok token saved to:\n" . ($zrokRes['path'] ?? 'token/.zrok_api_key') . "\n\n";
                    $txt .= "✓ Zrok token dataset synced: " . ($syncRes['dataset_ref'] ?? 'sage-zrok-token');

                    if (!empty($failed)) {
                        $txt .= "\n\n⚠ Warning - Failed to write Kaggle token to:\n" . implode("\n", $failed);
                    }

                    $messages[] = ['type' => empty($failed) ? 'success' : 'warning', 'text' => $txt];
                } else {
                    $txt = "✓ Tokens saved locally\n\n";
                    $txt .= "⚠ Warning: Failed to sync Zrok dataset to Kaggle:\n" . ($syncRes['message'] ?? 'Unknown error');
                    $messages[] = ['type' => 'warning', 'text' => $txt];
                }
            } else {
                $txt = "✓ Kaggle token saved\n\n";
                $txt .= "✗ Failed to save Zrok token: " . ($zrokRes['message'] ?? 'Unknown error');
                $messages[] = ['type' => 'warning', 'text' => $txt];
            }
        }
    }

    if ($action === 'sync_zrok_dataset') {
        $syncRes = $kaggle->syncZrokTokenDataset();
        if (!empty($syncRes['success'])) {
            $messages[] = ['type' => 'success', 'text' => "✓ Zrok token dataset synced successfully!\n\n" . ($syncRes['output'] ?? '')];
        } else {
            $messages[] = ['type' => 'error', 'text' => "✗ Failed to sync Zrok dataset:\n" . ($syncRes['message'] ?? 'Unknown error')];
        }
    }

    if ($action === 'sync_and_run') {
        $kernelRef = trim($_POST['kernel_ref'] ?? '');
        if ($kernelRef === '') {
            $messages[] = ['type' => 'error', 'text' => 'No kernel reference provided'];
        } else {
            set_time_limit(180);

            try {
                $pullRes = $kaggle->pullKernelToLocal($kernelRef);
                if (empty($pullRes['success'])) {
                    $messages[] = ['type' => 'error', 'text' => "✗ Pull failed:\n" . ($pullRes['output'] ?? 'Unknown')];
                } else {
                    $fixRes = $kaggle->fixKernelMetadata($pullRes['folder']);
                    if (empty($fixRes['success'])) {
                        $messages[] = ['type' => 'warning', 'text' => "⚠ Metadata fix warning:\n" . ($fixRes['message'] ?? 'Unknown')];
                    }

                    $pushRes = $kaggle->pushAndRunKernelFolder($pullRes['folder']);
                    if (!empty($pushRes['success'])) {
                        $msg = "✓ Notebook synced and launched!\n\n";
                        $msg .= "📥 Pulled: $kernelRef\n";
                        if (!empty($fixRes['fixed'])) {
                            $msg .= "🔧 Fixed metadata: " . implode(', ', $fixRes['fixed']) . "\n";
                        }
                        $msg .= "🚀 Pushed and running\n\n";
                        $msg .= ($pushRes['output'] ?? '');
                        $messages[] = ['type' => 'success', 'text' => $msg];
                    } else {
                        $messages[] = ['type' => 'error', 'text' => "✗ Push failed:\n" . ($pushRes['output'] ?? 'Unknown')];
                    }
                }
            } catch (\Exception $e) {
                $messages[] = ['type' => 'error', 'text' => 'Sync failed: ' . $e->getMessage()];
            }
        }
    }

    if ($action === 'pull_notebook') {
        $kernelRef = trim($_POST['kernel_ref'] ?? '');
        if ($kernelRef === '') {
            $messages[] = ['type' => 'error', 'text' => 'No kernel reference provided'];
        } else {
            set_time_limit(120);
            try {
                $res = $kaggle->pullKernelToLocal($kernelRef);
                if (!empty($res['success'])) {
                    $fixRes = $kaggle->fixKernelMetadata($res['folder']);
                    $msg = "✓ Notebook pulled successfully!\n\nFolder: {$res['folder']}";
                    if (!empty($fixRes['fixed'])) {
                        $msg .= "\n🔧 Auto-fixed metadata: " . implode(', ', $fixRes['fixed']);
                    }
                    $messages[] = ['type' => 'success', 'text' => $msg];
                } else {
                    $messages[] = ['type' => 'error', 'text' => "✗ Pull failed:\n" . ($res['output'] ?? 'Unknown')];
                }
            } catch (\Exception $e) {
                $messages[] = ['type' => 'error', 'text' => 'Pull failed: ' . $e->getMessage()];
            }
        }
    }

    if ($action === 'run_local') {
        $folder = trim($_POST['notebook_folder'] ?? '');
        if ($folder === '') {
            $messages[] = ['type' => 'error', 'text' => 'No folder supplied'];
        } else {
            set_time_limit(120);
            try {
                // Create metadata if missing before fixing
                $metadataPath = rtrim($folder, '/') . '/kernel-metadata.json';
                if (!file_exists($metadataPath)) {
                    $createRes = $kaggle->createDefaultMetadata($folder);
                    if (!empty($createRes['success'])) {
                        $messages[] = ['type' => 'success', 'text' => "✓ Created default metadata with GPU and internet enabled"];
                    }
                }

                $fixRes = $kaggle->fixKernelMetadata($folder);

                $res = $kaggle->pushAndRunKernelFolder($folder);
                if (!empty($res['success'])) {
                    $msg = "✓ Notebook pushed and running!";
                    if (!empty($fixRes['fixed'])) {
                        $msg .= "\n🔧 Auto-fixed: " . implode(', ', $fixRes['fixed']);
                    }
                    $msg .= "\n\n" . ($res['output'] ?? '');
                    $messages[] = ['type' => 'success', 'text' => $msg];
                } else {
                    $messages[] = ['type' => 'error', 'text' => "✗ Error:\n" . ($res['output'] ?? 'Unknown')];
                }
            } catch (\Exception $e) {
                $messages[] = ['type' => 'error', 'text' => 'Push failed: ' . $e->getMessage()];
            }
        }
    }
}



// Before the unified notebooks section, add this check
$hasRequiredTokens = $kaggle->hasRequiredTokens();
$token = $kaggle->getApiToken();
$zrokToken = $kaggle->getZrokToken();
$unifiedNotebooks = [];

// Only build unified notebooks if we have both tokens
if ($hasRequiredTokens) {
    $locals = $kaggle->listLocalNotebooks();
    $localMap = [];
    $remoteMap = [];

    // Build local map - include ALL .ipynb files
    foreach ($locals as $local) {
        $slug = basename($local['folder']);
        $localMap[$slug] = $local;
    }

    // Get remote notebooks
    $remote = $kaggle->listRemoteKernels($token['username']);
    if (is_array($remote) && !isset($remote['__error']) && !isset($remote['__raw'])) {
        // Build remote map and add to unified list
        foreach ($remote as $r) {
            $ref = $r['Ref'] ?? ($r['ref'] ?? ($r['Id'] ?? ($r['id'] ?? '')));
            $title = $r['Title'] ?? ($r['title'] ?? '');
            $slug = basename($ref);

            $remoteMap[$slug] = true;

            $hasLocal = isset($localMap[$slug]);
            $localFolder = $hasLocal ? $localMap[$slug]['folder'] : null;
            $lastModified = null;

            if ($hasLocal && $localFolder) {
                $metadataPath = $localFolder . '/kernel-metadata.json';
                if (file_exists($metadataPath)) {
                    $lastModified = filemtime($metadataPath);
                }
            }

            $unifiedNotebooks[] = [
                'ref' => $ref,
                'title' => $title,
                'has_local' => $hasLocal,
                'has_remote' => true,
                'local_folder' => $localFolder,
                'last_synced' => $lastModified,
                'needs_metadata' => false,
            ];
        }
    }

    // Add local-only notebooks (not in remote account)
    foreach ($localMap as $slug => $local) {
        if (!isset($remoteMap[$slug])) {
            // This is a local-only notebook
            $metadataPath = $local['folder'] . '/kernel-metadata.json';
            $lastModified = file_exists($metadataPath) ? filemtime($metadataPath) : null;
            $needsMetadata = !file_exists($metadataPath);

            // Try to extract title from metadata or use notebook filename
            $title = $slug; // default
            if (file_exists($metadataPath)) {
                $metadata = json_decode(file_get_contents($metadataPath), true);
                if (isset($metadata['title'])) {
                    $title = $metadata['title'];
                }
            } else {
                // Try to use .ipynb filename as title
                $ipynbFiles = glob($local['folder'] . '/*.ipynb');
                if (!empty($ipynbFiles)) {
                    $title = basename($ipynbFiles[0], '.ipynb');
                }
            }

            $unifiedNotebooks[] = [
                'ref' => $token['username'] . '/' . $slug,
                'title' => $title,
                'has_local' => true,
                'has_remote' => false,
                'local_folder' => $local['folder'],
                'last_synced' => $lastModified,
                'needs_metadata' => $needsMetadata,
            ];
        }
    }
} else {
    $unifiedNotebooks = [];
}







ob_start();
?>
<style>
:root {
    --primary: #2563eb;
    --primary-dark: #1e40af;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --bg-light: #f8fafc;
    --bg-card: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --text-light: #64748b;
    --shadow: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
}

* { box-sizing: border-box; }

.kaggle-module {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: var(--text);
    background: var(--bg-light);
    min-height: 100vh;
}

.header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-lg);
}

.header h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 700;
}

.header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.message {
    padding: 12px 16px;
    margin: 12px 0;
    border-radius: 8px;
    border-left: 4px solid;
    background: white;
    box-shadow: var(--shadow);
    white-space: pre-wrap;
    font-size: 14px;
}

.message.success {
    border-color: var(--success);
    background: #f0fdf4;
    color: #166534;
}

.message.error {
    border-color: var(--error);
    background: #fef2f2;
    color: #991b1b;
}

.message.warning {
    border-color: var(--warning);
    background: #fffbeb;
    color: #92400e;
}

.card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    cursor: pointer;
    user-select: none;
}

.card-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    background: var(--primary);
    color: white;
}

.toggle-icon {
    transition: transform 0.2s ease;
    font-size: 20px;
    color: var(--text-light);
}

.toggle-icon.collapsed {
    transform: rotate(-90deg);
}

.card-content {
    overflow: visible;
    transition: opacity 0.3s ease;
    opacity: 1;
    display: block;
}

.card-content.collapsed {
    display: none;
    opacity: 0;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 14px;
    color: var(--text);
}

.form-input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--bg-light);
    color: var(--text);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    background: white;
    border-color: var(--primary);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.table-container {
    overflow-x: auto;
    margin-top: 16px;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

thead {
    background: var(--bg-light);
}

th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: var(--text);
    border-bottom: 2px solid var(--border);
}

td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

tr:hover {
    background: var(--bg-light);
}

.notebook-name {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 2px;
}

.notebook-ref {
    color: var(--text-light);
    font-size: 12px;
}

.sync-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.sync-badge.synced {
    background: #d1fae5;
    color: #065f46;
}

.sync-badge.not-synced {
    background: #fef3c7;
    color: #92400e;
}

.sync-badge.local-only {
    background: #e0e7ff;
    color: #3730a3;
}

.sync-time {
    font-size: 11px;
    color: var(--text-light);
    margin-top: 2px;
}

.status-indicator {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-running {
    background: #dbeafe;
    color: #1e40af;
}

.status-complete {
    background: #d1fae5;
    color: #065f46;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-light);
}

.info-box {
    background: var(--bg-light);
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-light);
    margin-bottom: 16px;
}

.warning-box {
    background: #fffbeb;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    color: #92400e;
    margin-top: 6px;
    border-left: 3px solid var(--warning);
}

.code {
    background: var(--bg-light);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .kaggle-module {
        padding: 12px;
    }
    
    .header {
        padding: 16px;
    }
    
    .header h1 {
        font-size: 22px;
    }
    
    .card {
        padding: 16px;
    }
    
    table {
        font-size: 13px;
    }
    
    th, td {
        padding: 8px 6px;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}

.spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="kaggle-module">
    <div class="header">
        <h1>🚀 Kaggle Notebooks Control</h1>
        <p>Unified notebook management - Sync, fix metadata, and run your Stable Diffusion notebooks</p>
    </div>

    <?php foreach ($messages as $m): ?>
        <div class="message <?= htmlspecialchars($m['type'], ENT_QUOTES) ?>"><?= nl2br(htmlspecialchars($m['text'])) ?></div>
    <?php endforeach; ?>

    <div class="card">
        <div class="card-header" onclick="toggleSection('token')">
            <h2><span>🔑</span> API Configuration</h2>
            <span class="toggle-icon" id="toggle-token">▼</span>
        </div>
        <div class="card-content" id="content-token">
            <div style="width: 320px; overflow: auto;" class="info-box">
                <strong>Binary:</strong> <span class="code"><?= htmlspecialchars($kaggle->getKaggleBinaryPath() ?? 'Not found') ?></span><br>
                <strong>Config:</strong> <span class="code"><?= htmlspecialchars($kaggle->getPrimaryConfigDir()) ?></span>
            </div>

            <?php if (!$hasRequiredTokens): ?>
                <div class="warning-box" style="margin: 16px 0;">
                    ⚠️ <strong>Both Kaggle and Zrok tokens are required</strong><br>
                    Notebook operations are disabled until both tokens are configured.
                </div>
            <?php endif; ?>

            <!-- Save credentials form -->
            <form method="post" style="max-width: 500px; margin-top: 16px;">
                <input type="hidden" name="action" value="save_token">

                <div class="form-group">
                    <label>Kaggle Username</label>
                    <input type="text" name="kaggle_username" class="form-input"
                        value="<?= htmlspecialchars($token['username'] ?? '', ENT_QUOTES) ?>"
                        placeholder="yourusername" required />
                </div>

                <div class="form-group">
                    <label>Kaggle API Key</label>
                    <input type="password" name="kaggle_key" class="form-input"
                        value="<?= htmlspecialchars($token['key'] ?? '', ENT_QUOTES) ?>"
                        placeholder="Your Kaggle API key" required />
                </div>

                <div class="form-group">
                    <label>Zrok API Token</label>
                    <input type="password" name="zrok_token" class="form-input"
                        value="<?= htmlspecialchars($zrokToken ?? '', ENT_QUOTES) ?>"
                        placeholder="Your Zrok API token" required />
                    <div class="info-box" style="margin-top: 6px; font-size: 12px;">
                        💡 Zrok token will be stored in <span class="code">token/.zrok_api_key</span> and synced to a private Kaggle dataset
                    </div>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary">💾 Save All Credentials</button>

                    <?php if ($hasRequiredTokens): ?>
                        <!-- sync_zrok_dataset must not be nested inside the save_token form, so we render a separate form next to it -->
                        <noscript><!-- fallback: show separate button if JS disabled; still uses separate form below --></noscript>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($hasRequiredTokens): ?>
                <form method="post" style="display:inline; margin-top:10px;">
                    <input type="hidden" name="action" value="sync_zrok_dataset">
                    <button type="submit" class="btn btn-secondary">🔄 Sync Zrok Dataset</button>
                </form>
            <?php endif; ?>
        </div>
    </div>




    <!-- Notebooks card / disabled state -->
    <?php if (!$hasRequiredTokens): ?>
        <div class="card">
            <div class="card-header">
                <h2><span>📚</span> Notebooks</h2>
            </div>
            <div class="card-content">
                <div class="empty-state">
                    <p style="color: var(--text-light); font-size: 16px;">🔒 Notebook operations are disabled</p>
                    <p style="color: var(--text-light); font-size: 14px; margin-top: 8px;">
                        Please configure both Kaggle and Zrok API tokens above to enable notebook management.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header" onclick="toggleSection('notebooks')">
                <h2>
                    <span>📚</span> Notebooks
                    <?php if (!empty($unifiedNotebooks)): ?>
                        <span class="badge"><?= count($unifiedNotebooks) ?></span>
                    <?php endif; ?>
                </h2>
                <span class="toggle-icon" id="toggle-notebooks">▼</span>
            </div>

            <div class="card-content" id="content-notebooks">
                <?php /*if (empty($kaggle->getKaggleBinaryPath())): */ ?>
                <?php if (false): ?>
                    <div class="empty-state"><p>⚠️ Kaggle CLI binary not found on this host.</p></div>
                <?php elseif (empty($unifiedNotebooks)): ?>
                    <div class="empty-state"><p>📭 No notebooks found for user <?= htmlspecialchars($token['username'] ?? '') ?></p></div>
                <?php else: ?>
                    <div class="info-box">
                        💡 <strong>Sync & Run:</strong> Pulls latest version, auto-fixes metadata (GPU/internet enabled), then launches the notebook.<br>
                        📊 <strong>Status:</strong> Check if notebook is running, queued, or complete.<br>
                        📥 <strong>Pull:</strong> Pulls latest version and auto-fixes metadata (GPU/internet enabled).
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Notebook</th>
                                    <th style="width: 120px;">Sync Status</th>
                                    <th style="width: 130px; overflow: auto;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($unifiedNotebooks as $nb):
                                $uniqueId = 'kernel-' . md5($nb['ref']);
                                $hasLocal = !empty($nb['has_local']);
                                $hasRemote = !empty($nb['has_remote']);
                            ?>
                                <tr>
                                    <td>
                                        <div class="notebook-name"><?= htmlspecialchars($nb['title'], ENT_QUOTES) ?></div>
                                        <div class="notebook-ref"><?= htmlspecialchars($nb['ref'], ENT_QUOTES) ?></div>
                                        <?php if (!empty($nb['needs_metadata'])): ?>
                                            <div class="warning-box">⚠ Missing metadata - will be auto-created on push</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$hasRemote): ?>
                                            <div class="sync-badge local-only">💻 Local only</div>
                                            <?php if (!empty($nb['last_synced'])): ?>
                                                <div class="sync-time"><?= date('M j, g:ia', $nb['last_synced']) ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($hasLocal): ?>
                                            <div class="sync-badge synced">✓ Synced</div>
                                            <?php if (!empty($nb['last_synced'])): ?>
                                                <div class="sync-time"><?= date('M j, g:ia', $nb['last_synced']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="sync-badge not-synced">☁ Remote only</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="width: 120px; max-width: 120px; overflow: auto;">
                                        <div class="action-buttons">
                                            <?php if ($hasRemote): ?>
                                                <form method="post" style="display:inline; margin:0;">
                                                    <input type="hidden" name="action" value="sync_and_run">
                                                    <input type="hidden" name="kernel_ref" value="<?= htmlspecialchars($nb['ref'], ENT_QUOTES) ?>">
                                                    <button type="submit" class="btn btn-success btn-sm"
                                                            onclick="return confirm('<?= $hasLocal ? 'Update local copy and run?' : 'Download and run this notebook?' ?>')">
                                                        🔄 Sync & Run
                                                    </button>
                                                </form>

                                                <button class="btn btn-secondary btn-sm" onclick="checkStatus('<?= htmlspecialchars($nb['ref'], ENT_QUOTES) ?>', '<?= $uniqueId ?>')">
                                                    📊 Status
                                                </button>

                                                <?php if (!$hasLocal): ?>
                                                    <form method="post" style="display:inline; margin:0;">
                                                        <input type="hidden" name="action" value="pull_notebook">
                                                        <input type="hidden" name="kernel_ref" value="<?= htmlspecialchars($nb['ref'], ENT_QUOTES) ?>">
                                                        <button type="submit" class="btn btn-secondary btn-sm">
                                                            📥 Pull
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" style="display:inline; margin:0;">
                                                        <input type="hidden" name="action" value="run_local">
                                                        <input type="hidden" name="notebook_folder" value="<?= htmlspecialchars($nb['local_folder'], ENT_QUOTES) ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm"
                                                                onclick="return confirm('Push local version (no sync)?')">
                                                            ▶️ Push
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form method="post" style="display:inline; margin:0;">
                                                    <input type="hidden" name="action" value="run_local">
                                                    <input type="hidden" name="notebook_folder" value="<?= htmlspecialchars($nb['local_folder'], ENT_QUOTES) ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm"
                                                            onclick="return confirm('<?= !empty($nb['needs_metadata']) ? 'Create metadata and push to Kaggle?' : 'Push this notebook to your Kaggle account?' ?>')">
                                                        ▶️ Push to Kaggle
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($hasRemote): ?>
                                            <div id="<?= $uniqueId ?>-status" style="margin-top: 8px;"></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    
    
    <?php if ($hasRequiredTokens): ?>

        <div class="card">
        
            <div class="card-header" onclick="toggleSection('zrok')">
                <h2><span>🌐</span> zrok</h2>
                <span class="toggle-icon" id="toggle-zrok">▼</span>
            </div>
        
            <div class="card-content" id="content-zrok">
                <div style="background: #ccc; padding: 30px; height: 400px; width: 300px; overflow: auto; position: relative;">
        
                    <!-- 🔄 Reload button -->
                    <button
                        onclick="reloadIframeZrok()"
                        style="position: absolute; top: 10px; left: 40px; z-index: 10;
                            background: #333; color: #fff; border: none; padding: 5px 10px;
                            cursor: pointer; font-size: 12px; border-radius: 3px;">
                        🔄 Reload
                    </button>
        
                    <div style="background: #fff; transform: scale(0.5); transform-origin: 0 0; width: 800px; height: 800px;">
                        <iframe id="zrokIframe" style="height: 800px; width: 800px;" src="https://api.zrok.io/"></iframe>
                    </div>
        
                </div>
            </div>
        
        </div>

        <script>
        function reloadIframeZrok() {
            const iframe = document.getElementById('zrokIframe');
            iframe.src = iframe.src; // reload only this iframe
        }
        </script>
        
    <?php endif; ?>

    
    

</div> <!-- .kaggle-module -->

<script>
function toggleSection(section) {
    const content = document.getElementById('content-' + section);
    const icon = document.getElementById('toggle-' + section);

    if (!content || !icon) return;

    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        icon.classList.remove('collapsed');
        localStorage.setItem('kaggle-' + section + '-collapsed', 'false');
    } else {
        content.classList.add('collapsed');
        icon.classList.add('collapsed');
        localStorage.setItem('kaggle-' + section + '-collapsed', 'true');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    ['token', 'notebooks', 'zrok'].forEach(function(section) {
        const collapsed = localStorage.getItem('kaggle-' + section + '-collapsed');
        if (collapsed === 'true') {
            const content = document.getElementById('content-' + section);
            const icon = document.getElementById('toggle-' + section);
            if (content && icon) {
                content.classList.add('collapsed');
                icon.classList.add('collapsed');
            }
        }
    });
});

function checkStatus(kernelRef, containerId) {
    const statusDiv = document.getElementById(containerId + '-status');
    if (!statusDiv) return;
    statusDiv.innerHTML = '<div class="status-indicator status-running"><span class="spinner"></span> Checking...</div>';

    fetch('?ajax=status&kernel_ref=' + encodeURIComponent(kernelRef))
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                const output = data.output || 'No status information available';
                const isRunning = output.toLowerCase().includes('running') || output.toLowerCase().includes('queued');
                const statusClass = isRunning ? 'status-running' : 'status-complete';
                statusDiv.innerHTML = '<div class="status-indicator ' + statusClass + '">' +
                    escapeHtml(output.substring(0, 200)) +
                    (output.length > 200 ? '...' : '') +
                    '</div>';
            } else {
                statusDiv.innerHTML = '<div class="status-indicator" style="background:#fee;color:#c00;">Error: ' +
                    escapeHtml(data.output || 'Unknown error') + '</div>';
            }
        })
        .catch(err => {
            statusDiv.innerHTML = '<div class="status-indicator" style="background:#fee;color:#c00;">Network error</div>';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
