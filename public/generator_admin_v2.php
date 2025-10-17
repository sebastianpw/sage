<?php
// public/generator_admin_v2.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . '/../vendor/autoload.php';

use App\Entity\GeneratorConfig;

$em = $spw->getEntityManager();
$repo = $em->getRepository(GeneratorConfig::class);

$errors = [];
$success = null;
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    die('Not authenticated');
}

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? 'New Generator');
    $model = trim($_POST['model'] ?? 'openai');
    $configJson = trim($_POST['config_json'] ?? '');

    if (empty($configJson)) {
        $errors[] = "Configuration JSON must not be empty";
    } else {
        try {
            $config = GeneratorConfig::fromJson($configJson, $userId);
            $config->setTitle($title);
            $config->setModel($model);

            $em->persist($config);
            $em->flush();

            $success = "Generator created: {$config->getTitle()} (ID: {$config->getConfigId()})";
        } catch (\Throwable $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $title = trim($_POST['title'] ?? '');
    $model = trim($_POST['model'] ?? 'openai');
    $configJson = trim($_POST['config_json'] ?? '');

    try {
        $config = $repo->find($id);
        if (!$config || $config->getUserId() !== $userId) {
            throw new \Exception("Config not found");
        }

        $data = json_decode($configJson, true);
        if (!$data) {
            throw new \Exception("Invalid JSON");
        }

        $config->setTitle($title);
        $config->setModel($model);
        $config->setSystemRole($data['system']['role'] ?? '');
        $config->setInstructions($data['system']['instructions'] ?? []);
        $config->setParameters($data['parameters'] ?? []);
        $config->setOutputSchema($data['output'] ?? []);
        $config->setExamples($data['examples'] ?? null);
        $config->setUpdatedAt(new \DateTime());

        $em->flush();
        $success = "Updated: {$config->getTitle()}";
    } catch (\Throwable $e) {
        $errors[] = "Update error: " . $e->getMessage();
    }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    try {
        $config = $repo->find($id);
        if ($config && $config->getUserId() === $userId) {
            $em->remove($config);
            $em->flush();
            $success = "Deleted generator";
        }
    } catch (\Throwable $e) {
        $errors[] = "Delete error: " . $e->getMessage();
    }
}

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    try {
        $config = $repo->find($id);
        if ($config && $config->getUserId() === $userId) {
            $config->setActive(!$config->isActive());
            $em->flush();
            $success = $config->isActive() ? "Activated" : "Deactivated";
        }
    } catch (\Throwable $e) {
        $errors[] = "Toggle error: " . $e->getMessage();
    }
}

// Fetch all configs for user
$configs = $repo->findBy(['userId' => $userId], ['createdAt' => 'DESC']);

// Edit mode
$editConfig = null;
if (($editId = (int)($_GET['id'] ?? 0)) > 0) {
    $editConfig = $repo->find($editId);
    if (!$editConfig || $editConfig->getUserId() !== $userId) {
        $editConfig = null;
    }
}

// Default template
$defaultTemplate = json_encode([
    'system' => [
        'role' => 'Content Generator',
        'instructions' => [
            'You are an expert content generator.',
            'Always return valid JSON matching the output schema.',
            'If you cannot comply, return {"error": "schema_noncompliant", "reason": "brief explanation"}'
        ]
    ],
    'parameters' => [
        'mode' => [
            'type' => 'string',
            'enum' => ['simple', 'detailed'],
            'default' => 'simple'
        ]
    ],
    'output' => [
        'type' => 'object',
        'properties' => [
            'result' => ['type' => 'string'],
            'metadata' => ['type' => 'object']
        ],
        'required' => ['result', 'metadata']
    ],
    'examples' => []
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generator Admin v2</title>
    <style>
        :root{
            --bg:#f3f5f8;
            --card:#fbfdff;           /* slightly off-white */
            --card-weak:#f7f9fc;      /* card alternative for contrast */
            --muted:#6b7280;
            --accent:#111827;
            --accent-2:#2563eb;
            --danger:#ef4444;
            --success:#10b981;
            --radius:10px;
            --mono:ui-monospace,SFMono-Regular,Menlo,Monaco,monospace;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,sans-serif;background:linear-gradient(180deg,var(--bg),#eef2f6);color:var(--accent);padding:18px}
        .container{max-width:1100px;margin:0 auto}
        header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px}
        h1{font-size:22px;margin:0;font-weight:600}
        .card{background:var(--card);border-radius:var(--radius);padding:18px;margin-bottom:18px;box-shadow:0 6px 20px rgba(18,21,26,0.04);border:1px solid rgba(2,6,23,0.03)}
        .card.soft{background:var(--card-weak)}
        .notice{padding:12px;border-radius:8px;margin-bottom:14px;font-size:14px}
        .ok{background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.12)}
        .err{background:rgba(239,68,68,0.06);color:var(--danger);border:1px solid rgba(239,68,68,0.08)}
        label{display:block;font-size:13px;font-weight:600;color:var(--muted);margin-bottom:6px}
        input[type=text],select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #e7e9ee;font-size:14px;margin-bottom:14px;background:white}
        textarea{width:100%;min-height:300px;padding:12px;border-radius:8px;border:1px solid #e7e9ee;font-family:var(--mono);font-size:13px;resize:vertical;margin-bottom:16px;background:white}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;background:var(--accent-2);color:#fff;border-radius:8px;border:0;cursor:pointer;font-size:14px;text-decoration:none;font-weight:600}
        .btn:hover{opacity:0.95}
        .btn.secondary{background:#fff;color:var(--accent);border:1px solid #e5e7eb}
        .btn.danger{background:var(--danger);color:#fff}
        .btn.success{background:var(--success);color:#fff}
        .btn.small{padding:6px 10px;font-size:13px}
        .grid{display:grid;gap:12px}
        .grid-2{grid-template-columns:1fr 1fr;gap:12px}
        .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
        table{width:100%;border-collapse:separate;border-spacing:0;background:transparent}
        th{text-align:left;padding:12px;font-size:13px;color:var(--muted);border-bottom:2px solid rgba(0,0,0,0.04);font-weight:700}
        td{padding:12px;border-bottom:1px solid rgba(0,0,0,0.03);font-size:14px;background:transparent}
        tbody tr:hover td{background:var(--card-weak)}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:600}
        .badge.active{background:rgba(16,185,129,0.08);color:#059669;border:1px solid rgba(16,185,129,0.08)}
        .badge.inactive{background:rgba(107,114,128,0.06);color:var(--muted);border:1px solid rgba(107,114,128,0.04)}
        code{background:#f3f4f6;padding:3px 6px;border-radius:4px;font-family:var(--mono);font-size:12px}
        .table-responsive{overflow:auto;border-radius:8px;padding:6px;background:linear-gradient(180deg, rgba(255,255,255,0.6), rgba(247,249,252,0.6));}
        /* Cards view (mobile) */
        .generators-cards{display:none}
        .generator-item{background:linear-gradient(180deg,#fff,#fbfdff);padding:12px;border-radius:10px;box-shadow:0 4px 14px rgba(11,20,40,0.04);border:1px solid rgba(0,0,0,0.03);display:flex;flex-direction:column;gap:8px}
        .generator-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
        .generator-left{display:flex;gap:12px;align-items:center}
        .generator-title{font-weight:700}
        .generator-sub{color:var(--muted);font-size:13px}
        .generator-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
        /* responsive behaviour */
        @media (max-width: 960px){
            .container{padding:0 14px}
            .grid-2{grid-template-columns:1fr}
            header{flex-direction:column;align-items:flex-start;gap:8px}
        }
        @media (max-width:768px){
            /* hide table, show cards */
            .table-responsive{display:none}
            .generators-cards{display:grid;grid-template-columns:1fr;gap:12px}
            .actions{justify-content:flex-start}
            .btn.small{width:100%}
            .generator-item .generator-actions .btn{flex:1 1 auto}
            textarea{min-height:220px}
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <h1>Generator Admin v2</h1>
            <p style="color:var(--muted);margin:4px 0 0 0">Manage JSON-driven generators</p>
        </div>
        <span style="color:var(--muted);font-size:14px">User #<?=htmlspecialchars($userId)?></span>
    </header>

    <?php if ($success): ?>
        <div class="notice ok"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
        <div class="notice err"><?=htmlspecialchars($err)?></div>
    <?php endforeach; ?>

    <?php if ($editConfig): ?>
    <div class="card">
        <h2 style="margin:0 0 12px 0;font-size:18px">Edit: <?=htmlspecialchars($editConfig->getTitle())?></h2>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?=htmlspecialchars($editConfig->getId())?>">
            
            <div class="grid grid-2">
                <div>
                    <label>Title</label>
                    <input type="text" name="title" value="<?=htmlspecialchars($editConfig->getTitle())?>" required>
                </div>
                <div>
                    <label>Model</label>
                    <input type="text" name="model" value="<?=htmlspecialchars($editConfig->getModel())?>">
                </div>
            </div>

            <label>Configuration JSON</label>
            <textarea name="config_json" required><?=htmlspecialchars(json_encode($editConfig->toConfigArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))?></textarea>

            <div class="actions">
                <a href="?" class="btn secondary">Cancel</a>
                <button type="submit" class="btn">Save Changes</button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="card">
        <h2 style="margin:0 0 12px 0;font-size:18px">Create New Generator</h2>
        <form method="post">
            <input type="hidden" name="action" value="create">
            
            <div class="grid grid-2">
                <div>
                    <label>Title</label>
                    <input type="text" name="title" value="New Generator" required>
                </div>
                <div>
                    <label>Model</label>
                    <input type="text" name="model" value="openai">
                </div>
            </div>

            <label>Configuration JSON</label>
            <textarea name="config_json" id="config_json" required><?=htmlspecialchars($defaultTemplate)?></textarea>

            <div class="actions">
                <button type="button" class="btn secondary" onclick="resetTemplate()">Reset Template</button>
                <button type="submit" class="btn">Create Generator</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card soft">
        <h2 style="margin:0 0 12px 0;font-size:18px">Existing Generators (<?=count($configs)?>)</h2>
        
        <?php if (empty($configs)): ?>
            <p style="color:var(--muted);text-align:center;padding:40px 0">No generators yet</p>
        <?php else: ?>
            <!-- Desktop: table view -->
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Config ID</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($configs as $cfg): ?>
                    <tr>
                        <td><strong><?=htmlspecialchars($cfg->getTitle())?></strong></td>
                        <td><code><?=htmlspecialchars(substr($cfg->getConfigId(), 0, 16))?>...</code></td>
                        <td><?=htmlspecialchars($cfg->getModel())?></td>
                        <td>
                            <span class="badge <?=$cfg->isActive() ? 'active' : 'inactive'?>">
                                <?=$cfg->isActive() ? 'Active' : 'Inactive'?>
                            </span>
                        </td>
                        <td style="color:var(--muted);font-size:13px"><?=htmlspecialchars($cfg->getCreatedAt()->format('Y-m-d H:i'))?></td>
                        <td>
                            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                                <a href="/api/generate.php?config_id=<?=urlencode($cfg->getConfigId())?>" 
                                   target="_blank" 
                                   class="btn small success">Test</a>
                                <a href="?action=edit&id=<?=$cfg->getId()?>" class="btn small secondary">Edit</a>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?=$cfg->getId()?>">
                                    <button type="submit" class="btn small secondary">
                                        <?=$cfg->isActive() ? 'Disable' : 'Enable'?>
                                    </button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this generator?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?=$cfg->getId()?>">
                                    <button type="submit" class="btn small danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Mobile: cards view -->
            <div class="generators-cards" aria-hidden="false">
                <?php foreach ($configs as $cfg): ?>
                    <div class="generator-item">
                        <div class="generator-meta">
                            <div class="generator-left">
                                <div>
                                    <div class="generator-title"><?=htmlspecialchars($cfg->getTitle())?></div>
                                    <div class="generator-sub"><code><?=htmlspecialchars(substr($cfg->getConfigId(), 0, 16))?>...</code> · <?=htmlspecialchars($cfg->getModel())?></div>
                                </div>
                            </div>
                            <div style="text-align:right">
                                <div class="badge <?=$cfg->isActive() ? 'active' : 'inactive'?>">
                                    <?=$cfg->isActive() ? 'Active' : 'Inactive'?>
                                </div>
                                <div class="generator-sub" style="margin-top:6px"><?=htmlspecialchars($cfg->getCreatedAt()->format('Y-m-d H:i'))?></div>
                            </div>
                        </div>

                        <div class="generator-actions">
                            <a href="/api/generate.php?config_id=<?=urlencode($cfg->getConfigId())?>" 
                               target="_blank" 
                               class="btn small success">Test</a>
                            <a href="?action=edit&id=<?=$cfg->getId()?>" class="btn small secondary">Edit</a>

                            <form method="post" style="display:inline-flex;flex:0 1 auto">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?=$cfg->getId()?>">
                                <button type="submit" class="btn small secondary">
                                    <?=$cfg->isActive() ? 'Disable' : 'Enable'?>
                                </button>
                            </form>

                            <form method="post" style="display:inline-flex;flex:0 1 auto" onsubmit="return confirm('Delete this generator?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?=$cfg->getId()?>">
                                <button type="submit" class="btn small danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const defaultTemplate = <?=json_encode($defaultTemplate)?>;
function resetTemplate() {
    document.getElementById('config_json').value = defaultTemplate;
}
</script>
</body>
</html>
