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

    // Corrected, flexible handler for saving tokens independently.
    if ($action === 'save_token') {
        $username = trim($_POST['kaggle_username'] ?? '');
        $key = trim($_POST['kaggle_key'] ?? '');
        $zrokTokenPosted = trim($_POST['zrok_token'] ?? '');

        // track results
        $kaggleSaved = false;
        $zrokSaved = false;
        $parts = [];

        // --- Kaggle handling (only if any kaggle field was provided) ---
        if ($username !== '' || $key !== '') {
            if ($username === '' || $key === '') {
                // partial input
                $parts[] = ['type' => 'error', 'text' => 'Kaggle: please provide both username and API key, or leave both empty.'];
            } else {
                $res = $kaggle->setApiToken($username, $key);
                $written = $res['written'] ?? [];
                $failed  = $res['failed'] ?? [];
                $okFlag  = !empty($written) || !empty($res['success']);

                if ($okFlag && empty($failed)) {
                    $kaggleSaved = true;
                    $parts[] = ['type' => 'success', 'text' => 'Kaggle token saved.' . (!empty($written) ? ' Saved to: ' . implode(', ', $written) : '')];
                } elseif ($okFlag && !empty($failed)) {
                    $kaggleSaved = true;
                    $parts[] = ['type' => 'warning', 'text' => 'Kaggle saved with warnings. Failed to write: ' . implode(', ', $failed)];
                } else {
                    $parts[] = ['type' => 'error', 'text' => 'Kaggle: failed to save (' . ($res['message'] ?? 'unknown') . ')'];
                }
            }
        }

        // --- Zrok handling (independent) ---
        if ($zrokTokenPosted !== '') {
            $zrokRes = $kaggle->setZrokToken($zrokTokenPosted);
            if (!empty($zrokRes['success'])) {
                $zrokSaved = true;
                $parts[] = ['type' => 'success', 'text' => 'Zrok token saved to: ' . ($zrokRes['path'] ?? 'token/.zrok_api_key')];
            } else {
                $parts[] = ['type' => 'error', 'text' => 'Zrok: failed to save (' . ($zrokRes['message'] ?? 'unknown') . ')'];
            }
        }

        // --- If nothing was provided, inform the user ---
        if (empty($parts)) {
            $messages[] = ['type' => 'error', 'text' => 'No credentials provided to save.'];
        } else {
            // push per-field parts into messages for display
            foreach ($parts as $p) $messages[] = $p;
        }

        // --- Auto-run sync_zrok_dataset ONLY if both Kaggle and Zrok were provided and saved successfully this time ---
        if ($kaggleSaved && $zrokSaved) {
            // perform sync and report
            $syncRes = $kaggle->syncZrokTokenDataset();
            if (!empty($syncRes['success'])) {
                // Use the clean message directly from the service
                $messages[] = ['type' => 'success', 'text' => $syncRes['message']];
            } else {
                $messages[] = ['type' => 'warning', 'text' => "Saved credentials but failed to sync Zrok dataset: " . ($syncRes['message'] ?? 'Unknown error')];
            }
        }

        
        
        
        
        
        
        
    }

    if ($action === 'sync_zrok_dataset') {
        $syncRes = $kaggle->syncZrokTokenDataset();
        if (!empty($syncRes['success'])) {
            // Use the clean message directly from the service
            $messages[] = ['type' => 'success', 'text' => $syncRes['message']];
        } else {
            $messages[] = ['type' => 'error', 'text' => $syncRes['message'] ?? '✘ Failed to sync Zrok dataset: Unknown error'];
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

<link rel="stylesheet" href="/css/base.css" />

<style>
/* Theme-aware Kaggle module styles using base.css variables (no layout changes) */

/* small fallbacks in case base.css isn't loaded */
:root {
    --kg-accent: var(--accent, #2563eb);
    --kg-accent-dark: color-mix(in srgb, var(--kg-accent) 70%, black 30%); /* graceful on modern browsers */
    --kg-success: var(--green, #10b981);
    --kg-warning: var(--orange, #f59e0b);
    --kg-error: var(--red, #ef4444);
    --kg-card-bg: var(--card, #ffffff);
    --kg-border: rgba(var(--muted-border-rgb, 48,54,61), 0.12);
    --kg-text: var(--text, #111);
    --kg-text-muted: var(--text-muted, #64748b);
    --kg-shadow: var(--card-elevation, 0 6px 18px rgba(0,0,0,0.06));
}

/* module container - keep background transparent so app background shows through */
.kaggle-module {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: var(--kg-text);
    min-height: 100vh;
    background: transparent;
}

/* header uses the accent color from base.css */
.header {
    background: linear-gradient(135deg, var(--kg-accent) 0%, var(--kg-accent-dark, var(--kg-accent)) 100%);
    color: var(--card) == '#ffffff' ? #fff : #fff; /* fallback */
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: var(--kg-shadow);
}
.header h1 { margin: 0 0 8px 0; font-size: 28px; font-weight: 700; }
.header p  { margin: 0; opacity: 0.95; font-size: 14px; color: #efefef; }

/* messages */
.message {
    padding: 12px 16px;
    margin: 12px 0;
    border-radius: 8px;
    border-left: 4px solid transparent;
    background: var(--kg-card-bg);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    white-space: pre-wrap;
    font-size: 14px;
    color: var(--kg-text);
}

/* specific message variants */
.message.success { border-color: var(--kg-success); background: rgba(var(--muted-border-rgb), 0.015); }
.message.error   { border-color: var(--kg-error);   background: rgba(var(--muted-border-rgb), 0.02); }
.message.warning { border-color: var(--kg-warning); background: rgba(var(--muted-border-rgb), 0.02); }

/* card */
.card {
    background: var(--kg-card-bg);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    border: 1px solid var(--kg-border);
}

/* card header */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    cursor: pointer;
    user-select: none;
}
.card-header h2 { margin: 0; font-size: 18px; font-weight: 600; color: var(--kg-text); display:flex; align-items:center; gap:8px; }

/* badge */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    background: linear-gradient(90deg, rgba(255,255,255,0.06), rgba(0,0,0,0.06)), var(--kg-accent);
    color: #fff;
}

/* toggle icon */
.toggle-icon {
    transition: transform 0.2s ease;
    font-size: 18px;
    color: var(--kg-text-muted);
}
.toggle-icon.collapsed { transform: rotate(-90deg); }

/* content visibility */
.card-content { overflow: visible; transition: opacity 0.3s ease; opacity: 1; display: block; }
.card-content.collapsed { display: none; opacity: 0; }

/* forms */
.form-group { margin-bottom: 16px; }
.form-group label { display:block; margin-bottom:6px; font-weight:500; font-size:14px; color: var(--kg-text); }
.form-input {
    width:100%;
    padding:10px 12px;
    border-radius:8px;
    border:1px solid var(--kg-border);
    background: color-mix(in srgb, var(--kg-card-bg) 92%, transparent 8%);
    font-size:14px;
    color: var(--kg-text);
    transition: border-color 0.12s ease;
}
.form-input:focus { outline: none; border-color: var(--kg-accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.08); }

/* buttons - use base .btn where possible, add small overrides for size */
.btn { padding: 10px 18px; border-radius: 8px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; border: none; }
.btn-primary { background: var(--kg-accent); color: #fff; }
.btn-primary:hover { filter: brightness(0.95); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
.btn-success { background: var(--kg-success); color: #fff; }
.btn-success:hover { filter: brightness(0.95); transform: translateY(-1px); }
.btn-secondary { background: transparent; color: var(--kg-text); border: 1px solid var(--kg-border); }
.btn-secondary:hover { border-color: var(--kg-accent); background: color-mix(in srgb, var(--kg-card-bg) 92%, var(--kg-accent) 8%); }

.btn-sm { padding: 6px 12px; font-size:13px; border-radius: 8px; }

/* table layout */
.table-container { overflow-x:auto; margin-top:16px; }
table { width:100%; border-collapse: collapse; font-size:14px; }
thead { background: transparent; }
th { padding:12px; text-align:left; font-weight:600; color: var(--kg-text); border-bottom: 2px solid var(--kg-border); }
td { padding:12px; border-bottom: 1px solid var(--kg-border); vertical-align: middle; }
tr:hover { background: rgba(var(--muted-border-rgb), 0.02); }

/* notebook metadata */
.notebook-name { font-weight:600; color: var(--kg-text); margin-bottom:2px; }
.notebook-ref { color: var(--kg-text-muted); font-size:12px; }

/* sync badges using base notification colors via variables */
.sync-badge {
    display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;
}
.sync-badge.synced { background: rgba(var(--muted-border-rgb), 0.02); color: var(--kg-success); border: 1px solid rgba(var(--muted-border-rgb), 0.04); }
.sync-badge.not-synced { background: rgba(245,159,11,0.06); color: var(--kg-warning); border: 1px solid rgba(var(--muted-border-rgb), 0.04); }
.sync-badge.local-only { background: rgba(56, 139, 253, 0.06); color: var(--accent); border: 1px solid rgba(var(--muted-border-rgb), 0.04); }

.sync-time { font-size:11px; color: var(--kg-text-muted); margin-top:2px; }

/* status indicators */
.status-indicator { display:inline-block; padding:6px 12px; border-radius:12px; font-size:12px; font-weight:700; }
.status-running { background: rgba(59,130,246,0.06); color: var(--accent); border: 1px solid rgba(var(--muted-border-rgb), 0.04); }
.status-complete { background: rgba(35,134,54,0.06); color: var(--green); border: 1px solid rgba(var(--muted-border-rgb), 0.04); }

/* empty state / info box / warning box / code */
.empty-state { text-align:center; padding:40px 20px; color: var(--kg-text-muted); }
.info-box { background: color-mix(in srgb, var(--kg-card-bg) 96%, transparent 4%); padding:12px; border-radius:8px; font-size:13px; color: var(--kg-text-muted); margin-bottom:16px; border: 1px solid var(--kg-border); }
.warning-box { background: rgba(245,159,11,0.06); padding:8px 12px; border-radius:6px; font-size:12px; color: var(--kg-warning); margin-top:6px; border-left:3px solid var(--kg-warning); }
.code { background: color-mix(in srgb, var(--kg-card-bg) 94%, transparent 6%); padding:2px 6px; border-radius:4px; font-family:monospace; font-size:12px; }

/* action buttons container */
.action-buttons { display:flex; gap:8px; flex-wrap:wrap; }

/* responsive tweaks */
@media (max-width: 768px) {
    .kaggle-module { padding:12px; }
    .header { padding:16px; }
    .header h1 { font-size:22px; }
    .card { padding:16px; }
    table { font-size:13px; }
    th, td { padding:8px 6px; }
    .btn { width:100%; justify-content:center; }
    .action-buttons { flex-direction:column; }
}

/* spinner uses accent color on top for visibility */
.spinner {
    display:inline-block;
    width:14px; height:14px;
    border:2px solid rgba(var(--muted-border-rgb), 0.18);
    border-radius:50%;
    border-top-color: var(--accent);
    animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
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
                        placeholder="yourusername" />
                </div>

                <div class="form-group">
                    <label>Kaggle API Key</label>
                    <input type="password" name="kaggle_key" class="form-input"
                        value="<?= htmlspecialchars($token['key'] ?? '', ENT_QUOTES) ?>"
                        placeholder="Your Kaggle API key" />
                </div>

                <div class="form-group">
                    <label>Zrok API Token</label>
                    <input type="password" name="zrok_token" class="form-input"
                        value="<?= htmlspecialchars($zrokToken ?? '', ENT_QUOTES) ?>"
                        placeholder="Your Zrok API token" />
                    <div class="info-box" style="margin-top: 6px; font-size: 12px;">
                        💡 Zrok token will be stored in <span class="code">token/.zrok_api_key</span> and synced to a private Kaggle dataset
                    </div>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary">💾 Save Credentials</button>

                    <?php if ($hasRequiredTokens): ?>
                        <!-- sync_zrok_dataset must not be nested inside the save_token form, so we render a separate form next to it -->
                        <noscript><!-- fallback: show separate button if JS disabled; still uses separate form below --></noscript>
                    <?php endif; ?>
                </div>
            </form>
            
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
                    <p style="color: var(--kg-text-muted); font-size: 16px;">🔒 Notebook operations are disabled</p>
                    <p style="color: var(--kg-text-muted); font-size: 14px; margin-top: 8px;">
                        Please configure both Kaggle and Zrok API tokens above to enable notebook management.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header" onclick="toggleSection('notebooks')">
                <h2>
                    <span>💻</span> Notebooks
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
                                                        ▶️ Push
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
                <div style="background: color-mix(in srgb, var(--kg-card-bg, #fff) 88%, transparent 12%); padding: 30px; height: 400px; width: 300px; overflow: auto; position: relative;">
        
                    <!-- 🔄 Reload button -->
                    <button
                        onclick="reloadIframeZrok()"
                        style="position: absolute; top: 10px; left: 40px; z-index: 10;
                            background: var(--kg-accent, #333); color: #fff; border: none; padding: 5px 10px;
                            cursor: pointer; font-size: 12px; border-radius: 6px;">
                        🔄 Reload
                    </button>
        
                    <div style="background: var(--kg-card-bg); transform: scale(0.5); transform-origin: 0 0; width: 800px; height: 800px;">
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

