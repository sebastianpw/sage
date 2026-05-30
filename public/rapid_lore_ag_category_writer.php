<?php
declare(strict_types=1);

// public/rapid_lore_ag_category_writer.php
// Forge-style AG category writer for doc-scoped dry-run + apply.
// Purpose: create/reconcile AG categories and assign existing ag_nodes into the
// same top-level taxonomy used by the rapid view.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: /login.php');
    exit;
}

$pdo = $spw->getPdo();

function esc(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(array $payload): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function norm_key(?string $s): string {
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9]+/i', '', $s) ?? '';
    return $s;
}

function doc_categories_catalog(): array {
    return [
        'episodes' => [
            'label' => 'Episodes',
            'sort_order' => 10,
            'node_types' => ['episode', 'episodes', 'episode_concept', 'episode_concepts'],
            'aliases' => ['episode', 'episodes', 'episode concept', 'episode concepts'],
        ],
        'scene_hooks' => [
            'label' => 'Scene hooks',
            'sort_order' => 20,
            'node_types' => ['scene_hook', 'scene hooks', 'scenehook', 'hook', 'hooks'],
            'aliases' => ['scene hook', 'scene hooks', 'hook', 'hooks'],
        ],
        'characters' => [
            'label' => 'Characters',
            'sort_order' => 30,
            'node_types' => ['character', 'characters'],
            'aliases' => ['character', 'characters'],
        ],
        'locations' => [
            'label' => 'Locations',
            'sort_order' => 40,
            'node_types' => ['location', 'locations'],
            'aliases' => ['location', 'locations'],
        ],
        'factions' => [
            'label' => 'Factions',
            'sort_order' => 50,
            'node_types' => ['faction', 'factions', 'group', 'groups'],
            'aliases' => ['faction', 'factions', 'group', 'groups'],
        ],
    ];
}

function build_alias_map(): array {
    $map = [];
    foreach (doc_categories_catalog() as $bucket => $cfg) {
        $aliases = array_merge([$bucket, $cfg['label']], $cfg['node_types'], $cfg['aliases']);
        foreach ($aliases as $alias) {
            $map[norm_key((string)$alias)] = $bucket;
        }
    }
    return $map;
}

function ensure_ag_category(PDO $pdo, int $docId, string $bucketKey, array $cfg): array {
    $label = $cfg['label'];
    $sortOrder = (int)$cfg['sort_order'];

    $stmt = $pdo->prepare("
        SELECT id, doc_id, parent_id, name, description, sort_order
        FROM ag_categories
        WHERE doc_id = ? AND LOWER(name) = LOWER(?)
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([$docId, $label]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $desc = "Auto-generated AG category for {$label}.";
    if ($row) {
        $updates = [];
        $params = [];
        if ((int)($row['parent_id'] ?? 0) !== 0) {
            $updates[] = "parent_id = NULL";
        }
        if ((int)($row['sort_order'] ?? 0) !== $sortOrder) {
            $updates[] = "sort_order = ?";
            $params[] = $sortOrder;
        }
        if (trim((string)($row['description'] ?? '')) === '') {
            $updates[] = "description = ?";
            $params[] = $desc;
        }
        if (!empty($updates)) {
            $params[] = (int)$row['id'];
            $sql = "UPDATE ag_categories SET " . implode(', ', $updates) . " WHERE id = ?";
            $pdo->prepare($sql)->execute($params);
            $stmt->execute([$docId, $label]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;
        }
        $row['_bucket'] = $bucketKey;
        return $row;
    }

    $pdo->prepare("
        INSERT INTO ag_categories (doc_id, parent_id, name, description, sort_order)
        VALUES (?, NULL, ?, ?, ?)
    ")->execute([$docId, $label, $desc, $sortOrder]);

    $id = (int)$pdo->lastInsertId();
    return [
        'id' => $id,
        'doc_id' => $docId,
        'parent_id' => null,
        'name' => $label,
        'description' => $desc,
        'sort_order' => $sortOrder,
        '_bucket' => $bucketKey,
    ];
}

function load_doc_categories(PDO $pdo, int $docId): array {
    $stmt = $pdo->prepare("
        SELECT id, parent_id, name, description, sort_order
        FROM ag_categories
        WHERE doc_id = ?
        ORDER BY sort_order ASC, name ASC, id ASC
    ");
    $stmt->execute([$docId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function load_doc_nodes(PDO $pdo, int $docId): array {
    $stmt = $pdo->prepare("
        SELECT id, doc_id, category_id, name, node_type, status, sort_order
        FROM ag_nodes
        WHERE doc_id = ? AND status = 'active'
        ORDER BY node_type ASC, sort_order ASC, id ASC
    ");
    $stmt->execute([$docId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function resolve_bucket_for_node(array $node, array $aliasMap): array {
    $name = (string)($node['name'] ?? '');
    $nodeType = (string)($node['node_type'] ?? '');

    $typeKey = norm_key($nodeType);
    if ($typeKey !== '' && isset($aliasMap[$typeKey])) {
        return [$aliasMap[$typeKey], 'node_type'];
    }

    $nameKey = norm_key($name);
    if ($nameKey !== '' && isset($aliasMap[$nameKey])) {
        return [$aliasMap[$nameKey], 'name'];
    }

    return [null, 'unmatched'];
}

function category_label_map(array $categories): array {
    $map = [];
    foreach ($categories as $cat) {
        $map[(int)$cat['id']] = $cat['name'];
    }
    return $map;
}

function bucket_summary(PDO $pdo, int $docId, array $categoryRows): array {
    $summary = [];
    $nodesByType = [];
    $stmt = $pdo->prepare("
        SELECT node_type, COUNT(*) AS cnt
        FROM ag_nodes
        WHERE doc_id = ? AND status = 'active'
        GROUP BY node_type
        ORDER BY node_type ASC
    ");
    $stmt->execute([$docId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $nodesByType[(string)$row['node_type']] = (int)$row['cnt'];
    }

    $catMap = category_label_map($categoryRows);
    $catalog = doc_categories_catalog();

    foreach ($catalog as $bucket => $cfg) {
        $label = $cfg['label'];
        $sort = (int)$cfg['sort_order'];
        $existingId = null;
        foreach ($categoryRows as $cat) {
            if (strcasecmp((string)$cat['name'], $label) === 0) {
                $existingId = (int)$cat['id'];
                break;
            }
        }

        $cnt = 0;
        foreach ($nodesByType as $type => $num) {
            if (in_array(norm_key($type), array_map('norm_key', $cfg['node_types']), true)) {
                $cnt += $num;
            }
        }

        $summary[] = [
            'bucket' => $bucket,
            'label' => $label,
            'sort_order' => $sort,
            'category_id' => $existingId,
            'node_count' => $cnt,
            'status' => $existingId ? 'exists' : 'missing',
        ];
    }

    return $summary;
}

function scan_doc(PDO $pdo, int $docId): array {
    $catalog = doc_categories_catalog();
    $aliasMap = build_alias_map();

    $existingCategories = load_doc_categories($pdo, $docId);
    $categoryRows = [];
    $plannedCreates = [];
    $createdOrExisting = [];

    foreach ($catalog as $bucket => $cfg) {
        $existing = null;
        foreach ($existingCategories as $cat) {
            if (strcasecmp((string)$cat['name'], $cfg['label']) === 0) {
                $existing = $cat;
                break;
            }
        }
        if ($existing) {
            $existing['_bucket'] = $bucket;
            $categoryRows[$bucket] = $existing;
        } else {
            $plannedCreates[] = [
                'bucket' => $bucket,
                'name' => $cfg['label'],
                'sort_order' => (int)$cfg['sort_order'],
                'description' => "Auto-generated AG category for {$cfg['label']}.",
            ];
            $categoryRows[$bucket] = [
                'id' => null,
                'name' => $cfg['label'],
                'sort_order' => (int)$cfg['sort_order'],
                'description' => "Auto-generated AG category for {$cfg['label']}.",
                '_bucket' => $bucket,
            ];
        }
        $createdOrExisting[] = $categoryRows[$bucket];
    }

    $nodes = load_doc_nodes($pdo, $docId);
    $rows = [];
    $stats = [
        'total_nodes' => count($nodes),
        'matched' => 0,
        'unmatched' => 0,
        'to_create_categories' => count($plannedCreates),
        'to_update_nodes' => 0,
        'to_keep_nodes' => 0,
    ];
    $bucketCounts = [];
    $unmatchedNodes = [];

    foreach ($catalog as $bucket => $cfg) {
        $bucketCounts[$bucket] = 0;
    }

    foreach ($nodes as $node) {
        [$bucket, $reason] = resolve_bucket_for_node($node, $aliasMap);
        $currentCategoryId = isset($node['category_id']) ? (int)$node['category_id'] : null;
        $currentCategoryName = null;
        foreach ($existingCategories as $cat) {
            if ((int)$cat['id'] === (int)$currentCategoryId) {
                $currentCategoryName = (string)$cat['name'];
                break;
            }
        }

        $targetCategoryId = null;
        $targetCategoryName = null;
        if ($bucket !== null && isset($categoryRows[$bucket])) {
            $targetCategoryId = $categoryRows[$bucket]['id'] ? (int)$categoryRows[$bucket]['id'] : null;
            $targetCategoryName = $categoryRows[$bucket]['name'];
            $bucketCounts[$bucket]++;
        }

        $action = 'unmatched';
        if ($bucket !== null) {
            $stats['matched']++;
            if ($currentCategoryId === $targetCategoryId && $targetCategoryId !== null) {
                $action = 'keep';
                $stats['to_keep_nodes']++;
            } else {
                $action = $currentCategoryId ? 'reassign' : 'assign';
                $stats['to_update_nodes']++;
            }
        } else {
            $stats['unmatched']++;
            $unmatchedNodes[] = $node;
        }

        $rows[] = [
            'id' => (int)$node['id'],
            'name' => (string)$node['name'],
            'node_type' => (string)$node['node_type'],
            'current_category_id' => $currentCategoryId,
            'current_category_name' => $currentCategoryName,
            'target_bucket' => $bucket,
            'target_category_id' => $targetCategoryId,
            'target_category_name' => $targetCategoryName,
            'reason' => $reason,
            'action' => $action,
        ];
    }

    $summary = bucket_summary($pdo, $docId, $existingCategories);

    return [
        'doc_id' => $docId,
        'summary' => $summary,
        'rows' => $rows,
        'stats' => $stats,
        'existing_categories' => $existingCategories,
        'planned_categories' => $createdOrExisting,
        'planned_creates' => $plannedCreates,
        'bucket_counts' => $bucketCounts,
        'unmatched_nodes' => $unmatchedNodes,
    ];
}

function apply_scan(PDO $pdo, int $docId): array {
    $catalog = doc_categories_catalog();
    $aliasMap = build_alias_map();

    $pdo->beginTransaction();
    try {
        $categoryMap = [];
        $categoryActions = [];
        foreach ($catalog as $bucket => $cfg) {
            $cat = ensure_ag_category($pdo, $docId, $bucket, $cfg);
            $categoryMap[$bucket] = $cat;
            $categoryActions[] = [
                'bucket' => $bucket,
                'id' => (int)$cat['id'],
                'name' => (string)$cat['name'],
            ];
        }

        $nodes = load_doc_nodes($pdo, $docId);
        $updated = 0;
        $kept = 0;
        $unmatched = 0;
        $nodeRows = [];

        foreach ($nodes as $node) {
            [$bucket, $reason] = resolve_bucket_for_node($node, $aliasMap);
            $targetCategoryId = $bucket !== null ? (int)$categoryMap[$bucket]['id'] : null;
            $currentCategoryId = isset($node['category_id']) ? (int)$node['category_id'] : null;

            $action = 'unmatched';
            if ($targetCategoryId !== null) {
                if ($currentCategoryId === $targetCategoryId) {
                    $action = 'keep';
                    $kept++;
                } else {
                    $action = $currentCategoryId ? 'reassign' : 'assign';
                    $pdo->prepare("UPDATE ag_nodes SET category_id = ?, updated_at = NOW() WHERE id = ? AND doc_id = ?")
                        ->execute([$targetCategoryId, (int)$node['id'], $docId]);
                    $updated++;
                }
            } else {
                $unmatched++;
            }

            $nodeRows[] = [
                'id' => (int)$node['id'],
                'name' => (string)$node['name'],
                'node_type' => (string)$node['node_type'],
                'bucket' => $bucket,
                'reason' => $reason,
                'action' => $action,
                'current_category_id' => $currentCategoryId,
                'target_category_id' => $targetCategoryId,
            ];
        }

        $pdo->commit();

        return [
            'categories' => $categoryActions,
            'nodes' => $nodeRows,
            'stats' => [
                'created_or_confirmed_categories' => count($categoryActions),
                'updated_nodes' => $updated,
                'kept_nodes' => $kept,
                'unmatched_nodes' => $unmatched,
                'total_nodes' => count($nodes),
            ],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_input();
    $action = (string)($input['action'] ?? '');
    $docId = (int)($input['doc_id'] ?? 0);

    try {
        if ($action === 'scan') {
            if ($docId <= 0) respond(['ok' => false, 'error' => 'Please select a document.']);
            respond(['ok' => true, 'data' => scan_doc($pdo, $docId)]);
        }

        if ($action === 'apply') {
            if ($docId <= 0) respond(['ok' => false, 'error' => 'Please select a document.']);
            $result = apply_scan($pdo, $docId);
            $preview = scan_doc($pdo, $docId);
            respond(['ok' => true, 'data' => $result, 'preview' => $preview]);
        }

        if ($action === 'docs') {
            $docs = $pdo->query("
                SELECT d.id, d.name, d.updated_at, COALESCE(da.analyzed_at, d.updated_at) AS analyzed_at
                FROM documentations d
INNER JOIN md_doc_analysis da ON da.doc_id = d.id
WHERE d.is_active = 1
ORDER BY d.updated_at DESC, d.id DESC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            respond(['ok' => true, 'data' => $docs]);
        }

        respond(['ok' => false, 'error' => 'Unknown action.']);
    } catch (Throwable $e) {
        respond(['ok' => false, 'error' => $e->getMessage()]);
    }
}



$docs = $pdo->query("
    SELECT d.id, d.name, d.updated_at, COALESCE(da.analyzed_at, d.updated_at) AS analyzed_at
    FROM documentations d
INNER JOIN md_doc_analysis da ON da.doc_id = d.id
WHERE d.is_active = 1
ORDER BY d.updated_at DESC, d.id DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];



$viewportScale = '0.8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= esc($viewportScale) ?>, viewport-fit=cover">
<title>AG Category Forge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script>
(function() {
    try {
        var theme = localStorage.getItem('spw_theme');
        if (theme === 'dark' || theme === 'light') {
            document.documentElement.setAttribute('data-theme', theme);
        }
    } catch (e) {}
})();
</script>
<style>
:root {
    --bg:#080b10; --surface:#0e1319; --card:#111820; --card-hover:#141e28;
    --border:#1c2535; --border-glow:#2a3a52; --text:#c8d4e8; --text-dim:#5a6a80;
    --text-bright:#e8f0ff; --amber:#f5a623; --green:#22d3a0; --green-dim:rgba(34,211,160,.1);
    --red:#f05060; --red-dim:rgba(240,80,96,.1); --blue:#4da6ff; --blue-dim:rgba(77,166,255,.1);
    --mono:'Space Mono', monospace; --sans:'Syne', system-ui, sans-serif; --radius:6px;
}
:root[data-theme="light"], html[data-theme="light"], body[data-theme="light"] {
    --bg:#f6f8fa; --surface:#e1e4e8; --card:#ffffff; --card-hover:#f3f4f6;
    --border:#d1d5db; --border-glow:#9ca3af; --text:#111827; --text-dim:#4b5563;
    --text-bright:#000000; --amber:#d97706; --green:#059669; --green-dim:rgba(5,150,105,.1);
    --red:#dc2626; --red-dim:rgba(220,38,38,.1); --blue:#2563eb; --blue-dim:rgba(37,99,235,.1);
}
*{box-sizing:border-box} html,body{height:100%;margin:0;background:var(--bg);color:var(--text);font-family:var(--sans);overflow:hidden}
::-webkit-scrollbar{width:4px;height:4px} ::-webkit-scrollbar-thumb{background:var(--border-glow);border-radius:4px}
.layout{display:flex;flex-direction:column;height:100vh}
.header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;background:var(--surface);border-bottom:1px solid var(--border);flex-wrap:wrap}
.title{display:flex;align-items:center;gap:10px;font-family:var(--mono);font-size:1rem;font-weight:700;color:var(--amber);text-transform:uppercase;letter-spacing:1.2px}
.actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.btn{padding:5px 12px;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.75rem;transition:.15s;display:inline-flex;align-items:center;gap:6px}
.btn:hover{border-color:var(--border-glow);color:var(--text)}
.btn.primary{border-color:var(--amber);color:var(--amber)}
.btn.primary:hover{background:rgba(245,166,35,.08)}
.btn.success{border-color:var(--green);color:var(--green)}
.btn.success:hover{background:rgba(34,211,160,.08)}
.btn.icon{width:34px;height:34px;justify-content:center;padding:0}
.body{flex:1;overflow:hidden;padding:14px;display:flex;flex-direction:column;gap:12px}
.panel{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.panel-head{padding:12px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.panel-title{font-family:var(--mono);font-size:.75rem;font-weight:700;color:var(--amber);text-transform:uppercase;letter-spacing:1.2px}
.panel-sub{font-family:var(--mono);font-size:.72rem;color:var(--text-dim)}
.form-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:5px;min-width:220px;flex:1}
.field label{font-family:var(--mono);font-size:.68rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.6px}
.field select,.field input{
    width:100%; background:var(--bg); color:var(--text); border:1px solid var(--border);
    border-radius:var(--radius); padding:9px 10px; font-family:var(--mono); font-size:.78rem;
}
.field select:focus,.field input:focus{outline:none;border-color:var(--amber)}
.stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
.stat-card{padding:12px 14px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius)}
.stat-card .k{font-family:var(--mono);font-size:.68rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.7px}
.stat-card .v{margin-top:5px;font-family:var(--mono);font-size:1.05rem;color:var(--text-bright);font-weight:700}
.stat-card .s{margin-top:4px;font-family:var(--mono);font-size:.68rem;color:var(--text-dim)}
.table-wrap{flex:1;overflow:auto;background:var(--card);border:1px solid var(--border);border-radius:var(--radius)}
table{width:100%;border-collapse:collapse;font-family:var(--mono);font-size:.75rem;text-align:left}
th,td{padding:11px 12px;border-bottom:1px solid var(--border);vertical-align:top}
th{position:sticky;top:0;z-index:2;background:var(--surface);color:var(--text-dim);font-weight:400;text-transform:uppercase;letter-spacing:1px}
tr:hover td{background:var(--card-hover)}
.badge{display:inline-block;padding:2px 7px;border-radius:4px;border:1px solid;font-size:.64rem;text-transform:uppercase}
.badge.ok{color:var(--green);border-color:var(--green);background:var(--green-dim)}
.badge.warn{color:var(--amber);border-color:var(--amber);background:rgba(245,166,35,.1)}
.badge.err{color:var(--red);border-color:var(--red);background:var(--red-dim)}
.badge.info{color:var(--blue);border-color:var(--blue);background:var(--blue-dim)}
.small{font-family:var(--mono);font-size:.72rem;color:var(--text-dim)}
.toast-wrap{position:fixed;right:16px;bottom:16px;display:flex;flex-direction:column;gap:8px;z-index:9999;pointer-events:none}
.toast{pointer-events:auto;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;font-family:var(--mono);font-size:.75rem;box-shadow:0 8px 22px rgba(0,0,0,.35)}
.toast.success{border-color:var(--green)}
.toast.error{border-color:var(--red);color:var(--red)}
.toast.info{border-color:var(--blue)}
.hidden{display:none !important}
.note{padding:10px 14px;color:var(--text-dim);font-family:var(--mono);font-size:.74rem;line-height:1.55}
.card-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
@media (max-width: 900px){
    .stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .card-list{grid-template-columns:1fr}
}
@media (max-width: 760px){
    html,body{overflow:auto}
    .body{overflow:visible}
    .table-wrap{overflow:visible}
    table, thead, tbody, th, td, tr{display:block;width:100%}
    thead{display:none}
    tr{border-bottom:1px solid var(--border)}
    td{
        display:flex;justify-content:space-between;gap:14px;
        border-bottom:1px solid rgba(28,37,53,.7);
    }
    td::before{
        content:attr(data-label);
        color:var(--text-dim);
        text-transform:uppercase;
        letter-spacing:.8px;
        font-size:.64rem;
        flex:0 0 46%;
    }
    td:last-child{border-bottom:none}
    .header{position:sticky;top:0;z-index:50}
    .field{min-width:0}
}
</style>
</head>
<body>
<div class="layout">
    <header class="header">
        <div class="title">
            <i class="bi bi-diagram-3"></i>
            AG Category Forge
            <span id="docHint" class="small"></span>
        </div>
        <div class="actions">
            <button class="btn primary" onclick="Forge.scan()"><i class="bi bi-search"></i> Scan</button>
            <button class="btn success" onclick="Forge.apply()"><i class="bi bi-check2-circle"></i> Apply</button>
            <button class="btn icon" onclick="Forge.refreshDocs()" title="Refresh docs"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
    </header>

    <div class="body">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Document Selector</div>
                    <div class="panel-sub">Dry-run first, then write AG categories and node assignments for one document.</div>
                </div>
            </div>
            <div class="note">
                The writer targets these top-level categories only: <strong>Episodes</strong>, <strong>Scene hooks</strong>, <strong>Characters</strong>, <strong>Locations</strong>, and <strong>Factions</strong>.
                Matching prefers <strong>node_type</strong>, with a normalized name fallback for safety.
            </div>
            <div style="padding:14px;">
                <div class="form-row">
                    <div class="field" style="min-width:320px;">
                        <label>Curated Document</label>
                        <select id="docSelect" onchange="Forge.scan()">
                            <option value="">-- Select document --</option>
                            <?php foreach ($docs as $d): ?>
                                <option value="<?= (int)$d['id'] ?>">
                                    #<?= (int)$d['id'] ?> — <?= esc($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="min-width:220px; flex:0.8;">
                        <label>Mode</label>
                        <input type="text" value="Dry run + apply" readonly>
                    </div>
                    <div class="field" style="min-width:220px; flex:0.8;">
                        <label>Operation</label>
                        <input type="text" value="Doc-scoped category sync" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="stat-grid" id="statGrid">
            <div class="stat-card"><div class="k">Document</div><div class="v" id="statDoc">—</div><div class="s">selected doc</div></div>
            <div class="stat-card"><div class="k">Nodes</div><div class="v" id="statNodes">—</div><div class="s">active AG nodes</div></div>
            <div class="stat-card"><div class="k">Matched</div><div class="v" id="statMatched">—</div><div class="s">bucketed nodes</div></div>
            <div class="stat-card"><div class="k">Unmatched</div><div class="v" id="statUnmatched">—</div><div class="s">left untouched</div></div>
            <div class="stat-card"><div class="k">Category Writes</div><div class="v" id="statCats">—</div><div class="s">created or confirmed</div></div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Category Plan</div>
                    <div class="panel-sub">Existing category rows and what the writer will do.</div>
                </div>
                <div class="small" id="categoryPlanState">Awaiting scan…</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th>Category ID</th>
                            <th>Target Nodes</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTableBody">
                        <tr><td data-label="Category" colspan="6" style="text-align:center; color:var(--text-dim); padding:26px;">Select a document to begin.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel" style="flex:1; min-height:0;">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Dry Run Node List</div>
                    <div class="panel-sub">Each node is classified before any write happens.</div>
                </div>
                <div class="small" id="nodeListState">Awaiting scan…</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Node Type</th>
                            <th>Current Category</th>
                            <th>Target Category</th>
                            <th>Action</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody id="nodeTableBody">
                        <tr><td data-label="ID" colspan="7" style="text-align:center; color:var(--text-dim); padding:26px;">No scan yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const API = '';
let lastPreview = null;

function toast(msg, type='info', duration=3000) {
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(() => { el.remove(); }, duration);
}

async function apiCall(action, payload = {}) {
    const res = await fetch(API, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action, ...payload })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str === null || str === undefined ? '' : String(str);
    return div.innerHTML;
}

const Forge = (() => {
    'use strict';

    function selectedDocId() {
        return parseInt(document.getElementById('docSelect').value || '0', 10) || 0;
    }

    function setStats(data) {
        const stats = data?.stats || {};
        document.getElementById('statDoc').textContent = data?.doc_id ? `#${data.doc_id}` : '—';
        document.getElementById('statNodes').textContent = stats.total_nodes ?? '—';
        document.getElementById('statMatched').textContent = stats.matched ?? '—';
        document.getElementById('statUnmatched').textContent = stats.unmatched ?? '—';
        document.getElementById('statCats').textContent = stats.to_create_categories ?? stats.created_or_confirmed_categories ?? '—';
        document.getElementById('docHint').textContent = data?.doc_id ? ` · doc #${data.doc_id}` : '';
    }

    function renderCategoryTable(data) {
        const body = document.getElementById('categoryTableBody');
        const rows = data?.summary || [];
        const planned = new Map((data?.planned_creates || []).map(x => [x.bucket, x]));
        if (!rows.length) {
            body.innerHTML = '<tr><td data-label="Category" colspan="6" style="text-align:center; color:var(--text-dim); padding:26px;">No categories found.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(row => {
            const create = planned.get(row.bucket);
            const status = row.status === 'exists'
                ? '<span class="badge ok">exists</span>'
                : '<span class="badge warn">missing</span>';
            const notes = create ? `Will create "${escHtml(create.name)}"` : 'Ready';
            return `
                <tr>
                    <td data-label="Category"><strong>${escHtml(row.label)}</strong><div class="small">${escHtml(row.bucket)}</div></td>
                    <td data-label="Sort">${escHtml(row.sort_order)}</td>
                    <td data-label="Status">${status}</td>
                    <td data-label="Category ID">${row.category_id ? '#' + escHtml(row.category_id) : '<span class="badge err">new</span>'}</td>
                    <td data-label="Target Nodes">${escHtml(row.node_count)}</td>
                    <td data-label="Notes">${escHtml(notes)}</td>
                </tr>
            `;
        }).join('');
    }

    function renderNodeTable(data) {
        const body = document.getElementById('nodeTableBody');
        const rows = data?.rows || [];
        if (!rows.length) {
            body.innerHTML = '<tr><td data-label="ID" colspan="7" style="text-align:center; color:var(--text-dim); padding:26px;">No AG nodes in this document.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(row => {
            let actionBadge = '<span class="badge info">unmatched</span>';
            if (row.action === 'keep') actionBadge = '<span class="badge ok">keep</span>';
            if (row.action === 'assign') actionBadge = '<span class="badge warn">assign</span>';
            if (row.action === 'reassign') actionBadge = '<span class="badge warn">reassign</span>';
            if (row.action === 'unmatched') actionBadge = '<span class="badge err">skip</span>';

            return `
                <tr>
                    <td data-label="ID">#${escHtml(row.id)}</td>
                    <td data-label="Name"><strong>${escHtml(row.name)}</strong></td>
                    <td data-label="Node Type">${escHtml(row.node_type || '—')}</td>
                    <td data-label="Current Category">${row.current_category_name ? escHtml(row.current_category_name) : '<span class="small">none</span>'}</td>
                    <td data-label="Target Category">${row.target_category_name ? escHtml(row.target_category_name) : '<span class="small">none</span>'}</td>
                    <td data-label="Action">${actionBadge}</td>
                    <td data-label="Reason"><span class="small">${escHtml(row.reason || '')}</span></td>
                </tr>
            `;
        }).join('');
    }

    async function scan() {
        const docId = selectedDocId();
        if (!docId) {
            lastPreview = null;
            document.getElementById('categoryTableBody').innerHTML = '<tr><td data-label="Category" colspan="6" style="text-align:center; color:var(--text-dim); padding:26px;">Select a document to begin.</td></tr>';
            document.getElementById('nodeTableBody').innerHTML = '<tr><td data-label="ID" colspan="7" style="text-align:center; color:var(--text-dim); padding:26px;">No scan yet.</td></tr>';
            document.getElementById('categoryPlanState').textContent = 'Awaiting scan…';
            document.getElementById('nodeListState').textContent = 'Awaiting scan…';
            setStats(null);
            return;
        }

        document.getElementById('categoryPlanState').textContent = 'Scanning…';
        document.getElementById('nodeListState').textContent = 'Scanning…';

        try {
            const res = await apiCall('scan', { doc_id: docId });
            if (!res.ok) throw new Error(res.error || 'Scan failed');
            lastPreview = res.data;
            setStats(res.data);
            renderCategoryTable(res.data);
            renderNodeTable(res.data);
            document.getElementById('categoryPlanState').textContent = `${res.data.stats.to_create_categories} category writes planned`;
            document.getElementById('nodeListState').textContent = `${res.data.stats.matched} matched · ${res.data.stats.unmatched} unmatched`;
            toast('Dry run loaded.', 'success', 1800);
        } catch (e) {
            toast(e.message, 'error', 3600);
            document.getElementById('categoryPlanState').textContent = 'Scan failed';
            document.getElementById('nodeListState').textContent = 'Scan failed';
        }
    }

    async function apply() {
        const docId = selectedDocId();
        if (!docId) {
            toast('Select a document first.', 'error');
            return;
        }
        if (!confirm('Apply category creation and AG node assignments for this document?')) return;

        try {
            const res = await apiCall('apply', { doc_id: docId });
            if (!res.ok) throw new Error(res.error || 'Apply failed');
            lastPreview = res.preview || null;
            toast(`Applied: ${res.data.stats.updated_nodes} node updates, ${res.data.stats.created_or_confirmed_categories} categories.`, 'success', 4500);
            if (res.preview) {
                setStats(res.preview);
                renderCategoryTable(res.preview);
                renderNodeTable(res.preview);
                document.getElementById('categoryPlanState').textContent = 'Applied and rescanned';
                document.getElementById('nodeListState').textContent = 'Applied and rescanned';
            } else {
                await scan();
            }
        } catch (e) {
            toast(e.message, 'error', 4000);
        }
    }

    async function refreshDocs() {
        window.location.reload();
    }

    return { scan, apply, refreshDocs };
})();

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('docSelect').value) {
        Forge.scan();
    }
});
</script>
</body>
</html>
