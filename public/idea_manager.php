<?php
// public/idea_manager.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Ensure tables exist (Foreign keys removed)
$pdo->exec("
CREATE TABLE IF NOT EXISTS idea_clusters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS ideas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cluster_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('unassigned', 'yes', 'no') DEFAULT 'unassigned',
    source_array VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ── AJAX BACKEND ACTIONS ───────────────────────────────────────────────────
if (isset($_REQUEST['action'])) {
    
    // DOWNLOAD JSON INSTRUCTIONS
    if ($_REQUEST['action'] === 'download_instructions') {
        $instructions = [
            "_comment" => "JSON Structure Instructions for Idea Ingestion. Can accept an array of clusters or an array of items.",
            "example_clustered_format" => [
                [
                    "cluster_name" => "Cluster Name",
                    "cluster_description" => "Cluster Description",
                    "items" => [
                        [
                            "title" => "Item Title",
                            "description" => "Item Description"
                        ]
                    ]
                ]
            ],
            "example_flat_format" => [
                "items" => [
                    [
                        "title" => "Item Title",
                        "description" => "Item Description",
                        "cluster_name" => "Cluster Name",
                        "cluster_description" => "Cluster Description"
                    ]
                ]
            ]
        ];
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="ingest_instructions.json"');
        echo json_encode($instructions, JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: application/json');
    $action = $_REQUEST['action'];

    // 1. FETCH OVERVIEW (Clusters + Uncategorized + Unanswered Count)
    if ($action === 'fetch_overview') {
        $clusters = $pdo->query("
            SELECT c.*, COUNT(i.id) as item_count 
            FROM idea_clusters c 
            LEFT JOIN ideas i ON c.id = i.cluster_id 
            GROUP BY c.id ORDER BY c.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $uncategorizedCount = $pdo->query("SELECT COUNT(*) FROM ideas WHERE cluster_id IS NULL")->fetchColumn();
        $unansweredCount = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'unassigned'")->fetchColumn();

        echo json_encode([
            'ok' => true, 
            'clusters' => $clusters, 
            'uncategorizedCount' => (int)$uncategorizedCount,
            'unansweredCount' => (int)$unansweredCount
        ]);
        exit;
    }

    // 2. SAVE CLUSTER (Create/Edit)
    if ($action === 'save_cluster') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        
        if (!$name) { echo json_encode(['ok' => false, 'error' => 'Name is required']); exit; }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE idea_clusters SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $desc, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO idea_clusters (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // 3. FETCH ITEMS (For a specific cluster, uncategorized, or global across all clusters)
    if ($action === 'fetch_items') {
        $all_clusters = !empty($_POST['all_clusters']);
        $cluster_id = isset($_POST['cluster_id']) && $_POST['cluster_id'] !== '' ? (int)$_POST['cluster_id'] : null;
        $statusFilter = $_POST['status_filter'] ?? 'all'; 

        $params = [];
        $where = [];

        if (!$all_clusters) {
            if ($cluster_id === null) {
                $where[] = "i.cluster_id IS NULL";
            } else {
                $where[] = "i.cluster_id = ?";
                $params[] = $cluster_id;
            }
        }

        if ($statusFilter !== 'all') {
            $where[] = "i.status = ?";
            $params[] = $statusFilter;
        }

        $clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $stmt = $pdo->prepare("
            SELECT i.*, c.name AS cluster_name 
            FROM ideas i 
            LEFT JOIN idea_clusters c ON i.cluster_id = c.id 
            $clause 
            ORDER BY i.id ASC
        ");
        $stmt->execute($params);

        echo json_encode(['ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 4. CREATE ITEM
    if ($action === 'create_item') {
        $cluster_id = isset($_POST['cluster_id']) && $_POST['cluster_id'] !== '' ? (int)$_POST['cluster_id'] : null;
        $title = trim($_POST['title'] ?? 'New Idea');
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'unassigned';

        $stmt = $pdo->prepare("INSERT INTO ideas (title, description, cluster_id, status, source_array) VALUES (?, ?, ?, ?, 'Manual')");
        $stmt->execute([$title, $desc, $cluster_id, $status]);
        $newId = $pdo->lastInsertId();

        echo json_encode(['ok' => true, 'id' => (int)$newId]);
        exit;
    }

    // 5. UPDATE ITEM STATUS, CLUSTER, TITLE, OR DESCRIPTION
    if ($action === 'update_item') {
        $id = (int)$_POST['id'];
        
        if (isset($_POST['status'])) {
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE ideas SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }
        
        if (isset($_POST['cluster_id'])) {
            $c_id = $_POST['cluster_id'] === '' ? null : (int)$_POST['cluster_id'];
            $stmt = $pdo->prepare("UPDATE ideas SET cluster_id = ? WHERE id = ?");
            $stmt->execute([$c_id, $id]);
        }

        if (isset($_POST['title'])) {
            $title = trim($_POST['title']);
            if ($title !== '') {
                $stmt = $pdo->prepare("UPDATE ideas SET title = ? WHERE id = ?");
                $stmt->execute([$title, $id]);
            }
        }

        if (isset($_POST['description'])) {
            $desc = trim($_POST['description']);
            $stmt = $pdo->prepare("UPDATE ideas SET description = ? WHERE id = ?");
            $stmt->execute([$desc, $id]);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    // 6. INGEST JSON (Handles both clustered array and flat item structures)
    if ($action === 'ingest_json') {
        $json = $_POST['json_data'] ?? '';
        $data = json_decode($json, true);

        if (!is_array($data)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON string.']);
            exit;
        }

        $normalizedItems = [];

        // Format A: Clustered array [ { "cluster_name": "...", "cluster_description": "...", "items": [...] } ]
        if (isset($data[0]) && is_array($data[0]) && (isset($data[0]['items']) || isset($data[0]['cluster_name']))) {
            foreach ($data as $cluster) {
                $cName = $cluster['cluster_name'] ?? null;
                $cDesc = $cluster['cluster_description'] ?? '';
                $items = $cluster['items'] ?? [];
                
                if (is_array($items)) {
                    foreach ($items as $it) {
                        $normalizedItems[] = [
                            'title'               => $it['title'] ?? 'Untitled',
                            'description'         => $it['description'] ?? '',
                            'status'              => $it['status'] ?? 'unassigned',
                            'cluster_name'        => $cName,
                            'cluster_description' => $cDesc
                        ];
                    }
                }
            }
        } 
        // Format B: Wrapped object { "items": [ ... ] }
        elseif (isset($data['items']) && is_array($data['items'])) {
            $normalizedItems = $data['items'];
        } 
        // Format C: Flat array [ { "title": "...", ... } ]
        elseif (isset($data[0]) && is_array($data[0]) && isset($data[0]['title'])) {
            $normalizedItems = $data;
        } 
        else {
            echo json_encode(['ok' => false, 'error' => 'Unrecognized JSON structure. Provide an array of clusters or an array of items.']);
            exit;
        }

        $inserted = 0;
        foreach ($normalizedItems as $item) {
            $title  = $item['title'] ?? 'Untitled';
            $desc   = $item['description'] ?? '';
            $status = in_array($item['status'] ?? '', ['yes', 'no', 'unassigned']) ? $item['status'] : 'unassigned';
            $c_name = $item['cluster_name'] ?? null;
            $c_desc = $item['cluster_description'] ?? '';

            $c_id = null;
            if ($c_name && $c_name !== 'Uncategorized') {
                $stmt = $pdo->prepare("SELECT id FROM idea_clusters WHERE name = ?");
                $stmt->execute([$c_name]);
                $c_id = $stmt->fetchColumn();

                if (!$c_id) {
                    $stmtIns = $pdo->prepare("INSERT INTO idea_clusters (name, description) VALUES (?, ?)");
                    $stmtIns->execute([$c_name, $c_desc]);
                    $c_id = $pdo->lastInsertId();
                } elseif ($c_desc) {
                    $stmtUp = $pdo->prepare("UPDATE idea_clusters SET description = ? WHERE id = ? AND (description IS NULL OR description = '')");
                    $stmtUp->execute([$c_desc, $c_id]);
                }
            }

            $stmtIdea = $pdo->prepare("INSERT INTO ideas (title, description, cluster_id, status, source_array) VALUES (?, ?, ?, ?, ?)");
            $stmtIdea->execute([$title, $desc, $c_id, $status, 'JSON Ingest']);
            $inserted++;
        }

        echo json_encode(['ok' => true, 'message' => "Successfully ingested $inserted items."]);
        exit;
    }

    // 7. EXPORT JSON (With flexible multi-status filter: e.g. yes, unassigned, no)
    if ($action === 'export_json') {
        $clustersToExport = $_POST['clusters'] ?? []; 
        $statuses = $_POST['statuses'] ?? ['yes', 'no', 'unassigned'];
        if (!is_array($statuses) || empty($statuses)) {
            $statuses = ['yes', 'no', 'unassigned'];
        }
        
        $allowed = ['yes', 'no', 'unassigned'];
        $statuses = array_values(array_intersect($statuses, $allowed));
        if (empty($statuses)) {
            $statuses = $allowed;
        }

        $exportData = [];
        $inPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
        $whereStatus = " AND status IN ($inPlaceholders)";
        
        foreach ($clustersToExport as $cId) {
            if ($cId === 'unassigned') {
                $stmt = $pdo->prepare("SELECT title, description, status, source_array FROM ideas WHERE cluster_id IS NULL $whereStatus ORDER BY id ASC");
                $stmt->execute($statuses);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $exportData[] = [
                    'cluster_name' => 'Uncategorized',
                    'cluster_description' => '',
                    'items' => $items
                ];
            } else {
                $stmtC = $pdo->prepare("SELECT name, description FROM idea_clusters WHERE id = ?");
                $stmtC->execute([(int)$cId]);
                $clusterInfo = $stmtC->fetch(PDO::FETCH_ASSOC);
                if ($clusterInfo) {
                    $itemParams = array_merge([(int)$cId], $statuses);
                    $stmtI = $pdo->prepare("SELECT title, description, status, source_array FROM ideas WHERE cluster_id = ? $whereStatus ORDER BY id ASC");
                    $stmtI->execute($itemParams);
                    $clusterInfo['items'] = $stmtI->fetchAll(PDO::FETCH_ASSOC);
                    $exportData[] = $clusterInfo;
                }
            }
        }

        // Build descriptive filename
        $clusterSlug = '';
        if (count($clustersToExport) === 1) {
            if ($clustersToExport[0] === 'unassigned') {
                $clusterSlug = 'uncategorized_';
            } else {
                $stmtName = $pdo->prepare("SELECT name FROM idea_clusters WHERE id = ?");
                $stmtName->execute([(int)$clustersToExport[0]]);
                $cName = $stmtName->fetchColumn();
                if ($cName) {
                    $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower(trim($cName)));
                    $clusterSlug = trim($slug, '_') . '_';
                }
            }
        }

        $filterLabel = (count($statuses) < 3) ? implode('_', $statuses) . '_' : '';
        $filename = "ideas_export_{$clusterSlug}{$filterLabel}" . date('Ymd_His') . ".json";

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 8. SELECTIVE BATCH DELETE IDEAS (Handed-off cleanup by status & cluster)
    if ($action === 'delete_ideas_batch') {
        $statuses = $_POST['statuses'] ?? [];
        if (!is_array($statuses) || empty($statuses)) {
            echo json_encode(['ok' => false, 'error' => 'Please select at least one status to delete.']);
            exit;
        }

        $allowed = ['yes', 'no', 'unassigned'];
        $statuses = array_values(array_intersect($statuses, $allowed));
        if (empty($statuses)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid status selection.']);
            exit;
        }

        $clusterScope = $_POST['cluster_scope'] ?? 'all';
        $where = [];
        $params = [];

        $inPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
        $where[] = "status IN ($inPlaceholders)";
        $params = array_merge($params, $statuses);

        if ($clusterScope === 'unassigned') {
            $where[] = "cluster_id IS NULL";
        } elseif ($clusterScope !== 'all' && is_numeric($clusterScope)) {
            $where[] = "cluster_id = ?";
            $params[] = (int)$clusterScope;
        }

        $whereClause = implode(' AND ', $where);

        // Count matching items first
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ideas WHERE $whereClause");
        $countStmt->execute($params);
        $matched = (int)$countStmt->fetchColumn();

        if ($matched === 0) {
            echo json_encode(['ok' => true, 'count' => 0, 'message' => 'No items matched the criteria.']);
            exit;
        }

        // Execute deletion safely
        $delStmt = $pdo->prepare("DELETE FROM ideas WHERE $whereClause");
        $delStmt->execute($params);

        echo json_encode(['ok' => true, 'count' => $matched, 'message' => "Successfully deleted $matched ideas."]);
        exit;
    }

    // 9. TRUNCATE TABLES (Full Wipe: Ideas, Clusters, or Both)
    if ($action === 'truncate_table') {
        $target = $_POST['target'] ?? '';
        
        if ($target === 'ideas') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE ideas; SET FOREIGN_KEY_CHECKS = 1;");
            echo json_encode(['ok' => true, 'message' => 'Ideas table truncated successfully.']);
            exit;
        } elseif ($target === 'clusters') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE idea_clusters; UPDATE ideas SET cluster_id = NULL; SET FOREIGN_KEY_CHECKS = 1;");
            echo json_encode(['ok' => true, 'message' => 'Clusters table truncated (ideas reset to Uncategorized).']);
            exit;
        } elseif ($target === 'both') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE ideas; TRUNCATE TABLE idea_clusters; SET FOREIGN_KEY_CHECKS = 1;");
            echo json_encode(['ok' => true, 'message' => 'Both tables truncated successfully.']);
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'Invalid truncate target.']);
        exit;
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.8, maximum-scale=0.8, user-scalable=no">
<title>Idea Cluster Manager</title>
<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {}
  })();
</script>
<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
:root {
    --bg: #0a0a0f; --card: #111118; --border: #1e1e2e;
    --text: #e2e2f0; --text-muted: #555570;
    --purple: #8b5cf6; --purple-dim: rgba(139, 92, 246, 0.1);
    --amber: #f59e0b; --teal: #14b8a6; --red: #ef4444; --green: #10b981;
    --code-bg: #050508; --code-text: #a6e22e;
}
[data-theme="light"] {
    --bg: #f4f4f8; --card: #ffffff; --border: #d0d0e0;
    --text: #1a2233; --text-muted: #777788;
    --purple: #7c3aed; --purple-dim: rgba(124, 58, 237, 0.08);
    --amber: #d97706; --teal: #0d9488; --red: #dc2626; --green: #059669;
    --code-bg: #f8f8fc; --code-text: #116b00;
}
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', monospace; height: 100vh; display: flex; flex-direction: column; overflow: hidden; transition: background 0.15s, color 0.15s; }

.cz-header { padding: 12px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; flex-shrink: 0; }
.cz-title { font-size: 1rem; font-weight: 700; color: var(--purple); }
.cz-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

.cz-btn { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; transition: all 0.15s; }
.cz-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-dim); }
.cz-btn.primary { border-color: var(--purple); color: #fff; background: var(--purple); }
.cz-btn.teal { border-color: var(--teal); color: var(--teal); }
.cz-btn.red { border-color: var(--red); color: var(--red); }
.cz-btn.green { border-color: var(--green); color: var(--green); }
.cz-btn.amber { border-color: var(--amber); color: var(--amber); }
.cz-btn.amber:hover { background: rgba(245, 158, 11, 0.15); }
.cz-btn.active-yes { background: var(--green); color: #fff; border-color: var(--green); }
.cz-btn.active-no { background: var(--red); color: #fff; border-color: var(--red); }
.cz-btn.active-un { background: var(--border); color: var(--text); border-color: var(--border); }
.cz-btn.active-amber { background: var(--amber); color: #fff; border-color: var(--amber); }

/* Segmented button group for status chips */
.cz-seg-group { display: inline-flex; background: var(--bg); border: 1px solid var(--border); border-radius: 20px; padding: 2px; gap: 2px; }
.cz-seg-btn { padding: 4px 10px; border-radius: 16px; font-size: 0.7rem; font-weight: 700; border: none; background: transparent; color: var(--text-muted); cursor: pointer; transition: all 0.15s; }
.cz-seg-btn.active { background: var(--purple); color: #fff; }
.cz-seg-btn.active-yes { background: var(--green); color: #fff; }
.cz-seg-btn.active-no { background: var(--red); color: #fff; }
.cz-seg-btn.active-amber { background: var(--amber); color: #fff; }

.cz-main { flex: 1; overflow-y: auto; padding: 16px; padding-bottom: 80px; display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); align-content: start; }
.cz-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.cz-card-title { font-weight: 700; color: var(--amber); font-size: 1.1rem; }
.cz-card-desc { font-size: 0.8rem; color: var(--text-muted); min-height: 36px; line-height: 1.4; }
.cz-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; border-top: 1px solid var(--border); padding-top: 10px; flex-wrap: wrap; gap: 8px; }

/* Item List / Questionnaire */
.cz-item-row { display: flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); margin-bottom: 8px; }
.cz-item-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.cz-item-title { font-weight: 700; font-size: 0.9rem; color: var(--text); flex: 1; line-height: 1.3; }
.cz-item-desc { font-size: 0.8rem; color: var(--text-muted); line-height: 1.4; }
.cz-item-actions { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
.cz-select { padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.75rem; }

/* In-place Editable Fields */
.cz-editable {
    cursor: text;
    border-radius: 4px;
    padding: 4px 6px;
    margin: -4px -6px;
    transition: background 0.15s, box-shadow 0.15s;
    min-height: 1.3em;
}
.cz-editable:hover {
    background: var(--purple-dim);
}
.cz-editable:focus {
    outline: none;
    background: var(--card);
    box-shadow: 0 0 0 1px var(--purple);
}
.cz-item-desc[contenteditable="true"]:empty::before,
#qDesc[contenteditable="true"]:empty::before {
    content: attr(placeholder);
    color: var(--text-muted);
    font-style: italic;
    opacity: 0.6;
}

/* Modals */
.cz-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 12px; }
.cz-modal { background: var(--card); border: 1px solid var(--border); border-radius: 8px; width: 100%; max-width: 600px; max-height: 90vh; padding: 20px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.cz-json-textarea, .cz-input { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; color: var(--text); font-family: inherit; font-size: 0.85rem; padding: 10px; resize: vertical; }

.q-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px; }
</style>
</head>
<body>

<div class="cz-header">
    <div class="cz-title">🧠 Idea Cluster Manager</div>
    <div class="cz-actions">
        <button class="cz-btn" onclick="toggleTheme()"><i class="bi bi-brightness-high"></i></button>
        <button class="cz-btn amber" onclick="openGlobalQuestionnaire()" title="Run Questionnaire for all unanswered ideas across all clusters">
            <i class="bi bi-lightning-charge-fill"></i> Global Questionnaire (<span id="globalUnansweredCount">0</span>)
        </button>
        <button class="cz-btn teal" onclick="openExportModal(null)"><i class="bi bi-download"></i> Export All</button>
        <button class="cz-btn primary" onclick="openIngestModal()"><i class="bi bi-file-earmark-arrow-up"></i> Ingest JSON</button>
        <button class="cz-btn" onclick="openClusterModal(0)"><i class="bi bi-plus-lg"></i> New Cluster</button>
        <button class="cz-btn" onclick="openSettingsModal()" title="Settings & Maintenance"><i class="bi bi-gear-fill"></i></button>
    </div>
</div>

<div class="cz-main" id="dashboard">
    <!-- Rendered via JS -->
</div>

<!-- Modals -->

<!-- 1. Cluster Edit/Create Modal -->
<div class="cz-modal-overlay" id="clusterModal">
    <div class="cz-modal">
        <h3 style="margin:0; color:var(--purple);" id="cModalTitle">Create Cluster</h3>
        <input type="hidden" id="cModalId">
        <input type="text" id="cModalName" class="cz-input" placeholder="Cluster Name">
        <textarea id="cModalDesc" class="cz-json-textarea" placeholder="Description..." rows="3"></textarea>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button class="cz-btn" onclick="closeModal('clusterModal')">Cancel</button>
            <button class="cz-btn primary" onclick="saveCluster()">Save</button>
        </div>
    </div>
</div>

<!-- 2. Ingest JSON Modal (Paste mode) -->
<div class="cz-modal-overlay" id="ingestModal">
    <div class="cz-modal">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; color:var(--teal);">Ingest JSON Ideas</h3>
            <button class="cz-btn teal" onclick="downloadInstructions()"><i class="bi bi-info-circle"></i> DL Instructions</button>
        </div>
        <textarea id="ingestJsonInput" class="cz-json-textarea" rows="10" placeholder='[{"cluster_name": "My Cluster", "cluster_description": "...", "items": [{"title": "Idea", "description": "..."}]}]'></textarea>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button class="cz-btn" onclick="closeModal('ingestModal')">Cancel</button>
            <button class="cz-btn primary" onclick="submitIngest()">Ingest</button>
        </div>
    </div>
</div>

<!-- 3. Export Modal (Supports both All Clusters and Single Cluster) -->
<div class="cz-modal-overlay" id="exportModal">
    <div class="cz-modal">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <h3 style="margin:0; color:var(--amber);" id="exportModalTitle">Export Clusters</h3>
            <div class="cz-seg-group">
                <button type="button" class="cz-seg-btn active" id="chipAll" onclick="clickAllStatuses()">All</button>
                <button type="button" class="cz-seg-btn active-yes" id="chipYes" onclick="toggleExportStatus('yes')"><i class="bi bi-check"></i> Yes</button>
                <button type="button" class="cz-seg-btn active-no" id="chipNo" onclick="toggleExportStatus('no')"><i class="bi bi-x"></i> No</button>
                <button type="button" class="cz-seg-btn active-amber" id="chipUn" onclick="toggleExportStatus('unassigned')"><i class="bi bi-question"></i> Undecided</button>
            </div>
        </div>

        <div id="exportSingleNotice" style="display:none; padding:12px; border:1px solid var(--border); border-radius:6px; background:var(--bg); font-size:0.85rem;">
            <div style="font-weight:700; color:var(--purple); margin-bottom:4px;" id="exportSingleName"></div>
            <div style="color:var(--text-muted); font-size:0.75rem; line-height:1.4;" id="exportSingleDesc"></div>
        </div>

        <div id="exportClusterSelectionBlock">
            <div style="display:flex; gap:8px; margin-top:2px; margin-bottom:4px;">
                <button class="cz-btn" onclick="toggleExportAll(true)">Select All</button>
                <button class="cz-btn" onclick="toggleExportAll(false)">Select None</button>
            </div>
            <div id="exportList" style="display:flex; flex-direction:column; gap:8px; max-height: 250px; overflow-y:auto; border:1px solid var(--border); padding:10px; background:var(--bg); border-radius:6px;"></div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button class="cz-btn" onclick="closeModal('exportModal')">Cancel</button>
            <button class="cz-btn primary" onclick="submitExport()"><i class="bi bi-download"></i> Download</button>
        </div>
    </div>
</div>

<!-- 4. Items List Modal -->
<div class="cz-modal-overlay" id="itemsModal">
    <div class="cz-modal" style="max-width:800px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h3 style="margin:0; color:var(--purple);" id="iModalTitle">Items</h3>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <select id="iModalFilter" class="cz-select" onchange="renderItemsList()">
                    <option value="all">All Items</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                    <option value="unassigned">Unanswered</option>
                </select>
                <button class="cz-btn green" onclick="createNewItem()"><i class="bi bi-plus-lg"></i> New Item</button>
                <button class="cz-btn primary" onclick="openClusterQuestionnaire()"><i class="bi bi-play-fill"></i> Run Questionnaire</button>
                <button class="cz-btn" onclick="closeModal('itemsModal')">Close</button>
            </div>
        </div>
        <div id="iModalList" style="display:flex; flex-direction:column; gap:8px; flex:1; overflow-y:auto; padding-right:4px;"></div>
    </div>
</div>

<!-- 5. Questionnaire Modal (With In-Place Tap-to-Edit Title & Description) -->
<div class="cz-modal-overlay" id="questModal">
    <div class="cz-modal" style="max-width:600px;">
        <div style="font-size:0.75rem; color:var(--text-muted); display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span id="qProgress" style="font-weight:700;">Item 1 of X</span>
                <span id="qClusterBadge" style="font-size:0.7rem; font-weight:700; color:var(--teal); background:var(--bg); border:1px solid var(--border); padding:2px 8px; border-radius:12px;"></span>
            </div>
            <button class="cz-btn" onclick="closeModal('questModal')"><i class="bi bi-x-lg"></i></button>
        </div>

        <h2 class="cz-editable" 
            id="qTitle" 
            contenteditable="true" 
            style="margin:10px 0; color:var(--amber); font-size:1.3rem; line-height:1.3;" 
            title="Click to edit title (Enter to save)"
            onkeydown="onTitleKeydown(event, this)"
            onblur="saveQuestionnaireField(this, 'title')"></h2>

        <div class="cz-editable" 
             id="qDesc" 
             contenteditable="true" 
             style="color:var(--text); font-size:0.95rem; line-height:1.5; min-height:60px;" 
             placeholder="Add description..."
             title="Click to edit description (saves on blur)"
             onblur="saveQuestionnaireField(this, 'description')"></div>
        
        <div id="qAssignmentBlock" style="margin-top:10px;">
            <label style="font-size:0.75rem; color:var(--purple); font-weight:700;">Cluster Assignment:</label>
            <select id="qClusterSelect" class="cz-select" style="width:100%; margin-top:4px;" onchange="qAssignCluster(this.value)"></select>
        </div>

        <div style="display:flex; justify-content:center; gap:12px; margin-top:20px;">
            <button id="qBtnUn" class="cz-btn" onclick="qSetStatus('unassigned')" title="Shortcut: Revoke">Revoke</button>
            <button id="qBtnNo" class="cz-btn red" onclick="qSetStatus('no')" title="Shortcut: N or 2"><i class="bi bi-x-circle"></i> NO</button>
            <button id="qBtnYes" class="cz-btn green" onclick="qSetStatus('yes')" title="Shortcut: Y or 1"><i class="bi bi-check-circle"></i> YES</button>
        </div>

        <div class="q-nav">
            <button class="cz-btn" onclick="qNavigate(-1)"><i class="bi bi-chevron-left"></i> Prev</button>
            <span style="font-size:0.7rem; color:var(--text-muted);">(Keys: Y=Yes, N=No, ←/→=Nav)</span>
            <button class="cz-btn" onclick="qNavigate(1)">Next <i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
</div>

<!-- 6. Settings & Maintenance Modal -->
<div class="cz-modal-overlay" id="settingsModal">
    <div class="cz-modal" style="max-width:580px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; color:var(--purple);"><i class="bi bi-gear-fill"></i> Settings & Maintenance</h3>
            <button class="cz-btn" onclick="closeModal('settingsModal')"><i class="bi bi-x-lg"></i></button>
        </div>

        <!-- Section 1: File Upload Ingestion -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 14px; background: var(--bg);">
            <div style="font-weight:700; color:var(--teal); margin-bottom:4px;"><i class="bi bi-file-earmark-arrow-up"></i> Ingest JSON File</div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:10px;">Select any exported JSON file directly from your disk to ingest ideas.</div>
            <input type="file" id="settingsFileInput" accept=".json,application/json" style="display:none;" onchange="handleSettingsFileUpload(this)">
            <button class="cz-btn teal" onclick="$('#settingsFileInput').click()"><i class="bi bi-folder2-open"></i> Choose .JSON File</button>
        </div>

        <!-- Section 2: Selective Purge / Handed-Off Batch Cleanup -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 14px; background: var(--bg); display:flex; flex-direction:column; gap:10px;">
            <div>
                <div style="font-weight:700; color:var(--amber); margin-bottom:2px;"><i class="bi bi-filter-circle"></i> Purge Handed-Off Ideas</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Delete ideas you have already exported & handed off so they won't repeat in later exports.</div>
            </div>

            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <!-- Scope selection -->
                <select id="purgeClusterScope" class="cz-select" style="flex:1; min-width:180px;">
                    <option value="all">All Clusters & Uncategorized</option>
                    <option value="unassigned">📌 Uncategorized Ideas Only</option>
                </select>

                <!-- Status filter chips (Defaults to YES + NO) -->
                <div class="cz-seg-group">
                    <button type="button" class="cz-seg-btn" id="purgeChipAll" onclick="clickAllPurgeStatuses()">All</button>
                    <button type="button" class="cz-seg-btn active-yes" id="purgeChipYes" onclick="togglePurgeStatus('yes')"><i class="bi bi-check"></i> Yes</button>
                    <button type="button" class="cz-seg-btn active-no" id="purgeChipNo" onclick="togglePurgeStatus('no')"><i class="bi bi-x"></i> No</button>
                    <button type="button" class="cz-seg-btn" id="purgeChipUn" onclick="togglePurgeStatus('unassigned')"><i class="bi bi-question"></i> Undecided</button>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top:2px;">
                <button class="cz-btn red" onclick="confirmBatchDelete()"><i class="bi bi-trash3"></i> Purge Matching Ideas</button>
            </div>
        </div>

        <!-- Section 3: Full Table Truncates -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 14px; background: var(--bg); display:flex; flex-direction:column; gap:10px;">
            <div>
                <div style="font-weight:700; color:var(--red); margin-bottom:2px;"><i class="bi bi-exclamation-triangle"></i> Full Database Truncate</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Completely empty entire tables.</div>
            </div>
            
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="cz-btn red" onclick="confirmTruncate('ideas')"><i class="bi bi-trash"></i> Truncate Ideas</button>
                <button class="cz-btn red" onclick="confirmTruncate('clusters')"><i class="bi bi-trash"></i> Truncate Clusters</button>
                <button class="cz-btn red" style="background:var(--red); color:#fff;" onclick="confirmTruncate('both')"><i class="bi bi-trash3-fill"></i> Truncate BOTH</button>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
            <button class="cz-btn" onclick="closeModal('settingsModal')">Close</button>
        </div>
    </div>
</div>

<script>
let globalClusters = [];
let currentListClusterId = null; 
let currentItems = [];
let qIndex = 0;
let selectedExportStatuses = ['yes', 'no', 'unassigned'];
let currentExportTarget = null;
let purgeSelectedStatuses = ['yes', 'no']; // Defaults to reviewed/handed-off decisions

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('spw_theme', next); } catch(e) {}
}

function loadOverview() {
    $.post('idea_manager.php', { action: 'fetch_overview' }, function(res) {
        if(!res.ok) return;
        globalClusters = res.clusters;
        $('#globalUnansweredCount').text(res.unansweredCount || 0);

        const db = $('#dashboard').empty();
        
        // Uncategorized Card
        db.append(`
            <div class="cz-card" style="border-color: var(--amber);">
                <div class="cz-card-title">📌 Uncategorized Ideas</div>
                <div class="cz-card-desc">Items needing assignment or review.</div>
                <div class="cz-card-footer">
                    <span style="font-size:0.8rem; color:var(--text-muted);"><i class="bi bi-list"></i> ${res.uncategorizedCount} items</span>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button class="cz-btn" title="Export uncategorized ideas" onclick="openExportModal('unassigned')"><i class="bi bi-download"></i> Export</button>
                        <button class="cz-btn primary" onclick="openItemsModal(null)">View & Assign</button>
                    </div>
                </div>
            </div>
        `);

        // Cluster Cards
        res.clusters.forEach(c => {
            db.append(`
                <div class="cz-card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                        <div class="cz-card-title">${esc(c.name)}</div>
                        <button class="cz-btn" onclick="openClusterModal(${c.id})"><i class="bi bi-pencil"></i></button>
                    </div>
                    <div class="cz-card-desc">${esc(c.description || 'No description.')}</div>
                    <div class="cz-card-footer">
                        <span style="font-size:0.8rem; color:var(--text-muted);"><i class="bi bi-list"></i> ${c.item_count} items</span>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button class="cz-btn" title="Export this cluster" onclick="openExportModal(${c.id})"><i class="bi bi-download"></i> Export</button>
                            <button class="cz-btn teal" onclick="openItemsModal(${c.id})">Manage Items</button>
                        </div>
                    </div>
                </div>
            `);
        });
    });
}

function esc(s) { 
    return s ? s.toString()
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;') : ''; 
}

function closeModal(id) { $(`#${id}`).hide(); }

// -- Cluster Modal --
function openClusterModal(id = 0) {
    if (id > 0) {
        const c = globalClusters.find(x => x.id == id);
        $('#cModalId').val(c ? c.id : 0);
        $('#cModalName').val(c ? c.name : '');
        $('#cModalDesc').val(c ? (c.description || '') : '');
        $('#cModalTitle').text('Edit Cluster');
    } else {
        $('#cModalId').val(0);
        $('#cModalName').val('');
        $('#cModalDesc').val('');
        $('#cModalTitle').text('New Cluster');
    }
    $('#clusterModal').css('display', 'flex');
}

function saveCluster() {
    $.post('idea_manager.php', {
        action: 'save_cluster',
        id: $('#cModalId').val(),
        name: $('#cModalName').val(),
        description: $('#cModalDesc').val()
    }, function(res) {
        if(res.ok) { Toast.show('Cluster saved', 'success'); closeModal('clusterModal'); loadOverview(); }
        else Toast.show(res.error, 'error');
    });
}

// -- Items Modal --
function openItemsModal(clusterId = null) {
    currentListClusterId = clusterId;
    if (clusterId === null) {
        $('#iModalTitle').text('📌 Uncategorized Ideas');
    } else {
        const c = globalClusters.find(x => x.id == clusterId);
        $('#iModalTitle').text(c ? c.name : 'Items');
    }
    $('#iModalFilter').val('all');
    $('#itemsModal').css('display', 'flex');
    renderItemsList();
}

function renderItemsList(focusId = null) {
    const filter = $('#iModalFilter').val();
    $.post('idea_manager.php', { action: 'fetch_items', cluster_id: currentListClusterId !== null ? currentListClusterId : '', status_filter: filter }, function(res) {
        if(!res.ok) return;
        currentItems = res.items;
        const list = $('#iModalList').empty();
        
        if(currentItems.length === 0) {
            list.append('<div style="padding:20px; text-align:center; color:var(--text-muted);">No items found.</div>');
            return;
        }

        let clusterOpts = `<option value="">-- Unassigned --</option>`;
        globalClusters.forEach(c => {
            clusterOpts += `<option value="${c.id}">${esc(c.name)}</option>`;
        });

        currentItems.forEach(item => {
            list.append(`
                <div class="cz-item-row" id="itemRow_${item.id}">
                    <div class="cz-item-head">
                        <div class="cz-item-title cz-editable" 
                             contenteditable="true" 
                             data-field="title" 
                             data-id="${item.id}" 
                             data-orig="${esc(item.title)}" 
                             title="Click to edit title (Enter to save)"
                             onkeydown="onTitleKeydown(event, this)"
                             onblur="saveItemField(this)">${esc(item.title)}</div>
                        <select class="cz-select" style="max-width:140px;" onchange="updateItemCluster(${item.id}, this.value)">
                            ${clusterOpts.replace(`value="${item.cluster_id || ''}"`, `value="${item.cluster_id || ''}" selected`)}
                        </select>
                    </div>
                    <div class="cz-item-desc cz-editable" 
                         contenteditable="true" 
                         data-field="description" 
                         data-id="${item.id}" 
                         data-orig="${esc(item.description)}" 
                         placeholder="Add description..."
                         title="Click to edit description (saves on blur)"
                         onblur="saveItemField(this)">${esc(item.description)}</div>
                    <div class="cz-item-actions">
                        <button class="cz-btn ${item.status === 'yes' ? 'active-yes' : ''}" onclick="updateItemStatus(${item.id}, 'yes')">Yes</button>
                        <button class="cz-btn ${item.status === 'no' ? 'active-no' : ''}" onclick="updateItemStatus(${item.id}, 'no')">No</button>
                        <button class="cz-btn ${item.status === 'unassigned' ? 'active-un' : ''}" onclick="updateItemStatus(${item.id}, 'unassigned')">Revoke</button>
                        <button class="cz-btn red" style="margin-left:auto;" title="Remove from Cluster" onclick="updateItemCluster(${item.id}, '')"><i class="bi bi-box-arrow-right"></i> Remove</button>
                    </div>
                </div>
            `);
        });

        if (focusId) {
            const $newRow = $(`#itemRow_${focusId}`);
            if ($newRow.length) {
                $newRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                const $title = $newRow.find('.cz-item-title');
                $title.focus();
                const range = document.createRange();
                range.selectNodeContents($title[0]);
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }
    });
}

// -- Create New Item --
function createNewItem() {
    $('#iModalFilter').val('all');
    $.post('idea_manager.php', {
        action: 'create_item',
        cluster_id: currentListClusterId !== null ? currentListClusterId : '',
        title: 'New Idea',
        description: ''
    }, function(res) {
        if (res.ok) {
            Toast.show('Item created', 'success');
            renderItemsList(res.id);
            loadOverview();
        } else {
            Toast.show(res.error || 'Failed to create item', 'error');
        }
    });
}

// -- Tap-to-Edit & On-Blur Autosave (List View) --
function onTitleKeydown(e, el) {
    if (e.key === 'Enter') {
        e.preventDefault();
        el.blur();
    }
}

function saveItemField(el) {
    const $el = $(el);
    const id = $el.data('id');
    const field = $el.data('field');
    const orig = $el.data('orig') || '';
    const current = $el.text().trim();

    if (current === orig) return;

    if (field === 'title' && current === '') {
        $el.text(orig);
        Toast.show('Title cannot be empty', 'error');
        return;
    }

    const payload = { action: 'update_item', id: id };
    payload[field] = current;

    $.post('idea_manager.php', payload, function(res) {
        if (res.ok) {
            $el.data('orig', current);
            const it = currentItems.find(x => x.id == id);
            if (it) it[field] = current;
            Toast.show('Saved', 'success');
        } else {
            Toast.show(res.error || 'Failed to save', 'error');
            $el.text(orig);
        }
    });
}

// -- Tap-to-Edit & On-Blur Autosave (Questionnaire View) --
function saveQuestionnaireField(el, field) {
    if (!currentItems[qIndex]) return;
    const item = currentItems[qIndex];
    const $el = $(el);
    const orig = $el.data('orig') || '';
    const current = $el.text().trim();

    if (current === orig) return;

    if (field === 'title' && current === '') {
        $el.text(orig);
        Toast.show('Title cannot be empty', 'error');
        return;
    }

    const payload = { action: 'update_item', id: item.id };
    payload[field] = current;

    $.post('idea_manager.php', payload, function(res) {
        if (res.ok) {
            $el.data('orig', current);
            item[field] = current;
            if ($('#itemsModal').is(':visible')) {
                const $row = $(`#itemRow_${item.id}`);
                if ($row.length) {
                    $row.find(`[data-field="${field}"]`).text(current).data('orig', current);
                }
            }
            Toast.show('Saved', 'success');
        } else {
            Toast.show(res.error || 'Failed to save', 'error');
            $el.text(orig);
        }
    });
}

function updateItemStatus(id, status) {
    $.post('idea_manager.php', { action: 'update_item', id: id, status: status }, function(res) {
        if(res.ok) {
            renderItemsList();
            loadOverview();
        }
    });
}

function updateItemCluster(id, cluster_id) {
    $.post('idea_manager.php', { action: 'update_item', id: id, cluster_id: cluster_id }, function(res) {
        if(res.ok) { Toast.show('Moved', 'success'); renderItemsList(); loadOverview(); }
    });
}

// -- Questionnaire Runners --
function openClusterQuestionnaire() {
    if(currentItems.length === 0) { Toast.show('No items to review', 'error'); return; }
    qIndex = 0;
    setupQuestionnaireModal();
}

function openGlobalQuestionnaire() {
    $.post('idea_manager.php', { action: 'fetch_items', all_clusters: 1, status_filter: 'unassigned' }, function(res) {
        if(!res.ok) return;
        if(!res.items || res.items.length === 0) {
            Toast.show('All ideas are already answered! 🎉', 'success');
            return;
        }
        currentItems = res.items;
        qIndex = 0;
        setupQuestionnaireModal();
    });
}

function setupQuestionnaireModal() {
    const sel = $('#qClusterSelect').empty().append('<option value="">-- Unassigned --</option>');
    globalClusters.forEach(c => sel.append(`<option value="${c.id}">${esc(c.name)}</option>`));
    $('#questModal').css('display', 'flex');
    renderQuestionnaire();
}

function renderQuestionnaire() {
    if(qIndex < 0) qIndex = 0;
    if(qIndex >= currentItems.length) qIndex = currentItems.length - 1;
    
    const item = currentItems[qIndex];
    $('#qProgress').text(`Item ${qIndex + 1} of ${currentItems.length}`);
    
    const cName = item.cluster_name || (item.cluster_id ? 'Assigned' : '📌 Uncategorized');
    $('#qClusterBadge').text(cName);

    $('#qTitle').text(item.title).data('orig', item.title);
    $('#qDesc').text(item.description || '').data('orig', item.description || '');

    $('#qBtnYes').toggleClass('active-yes', item.status === 'yes');
    $('#qBtnNo').toggleClass('active-no', item.status === 'no');
    $('#qBtnUn').toggleClass('active-un', item.status === 'unassigned');

    $('#qClusterSelect').val(item.cluster_id || '');
}

function qNavigate(delta) {
    qIndex += delta;
    if(qIndex >= currentItems.length) { Toast.show('End of list', 'info'); qIndex = currentItems.length - 1; }
    else if(qIndex < 0) { qIndex = 0; }
    else { renderQuestionnaire(); }
}

function qSetStatus(status) {
    if(!currentItems[qIndex]) return;
    const item = currentItems[qIndex];
    $.post('idea_manager.php', { action: 'update_item', id: item.id, status: status }, function(res) {
        if(res.ok) {
            item.status = status; 
            renderQuestionnaire();
            loadOverview(); 
            if($('#itemsModal').is(':visible')) renderItemsList();
            if(status !== 'unassigned') setTimeout(() => qNavigate(1), 250);
        }
    });
}

function qAssignCluster(cluster_id) {
    if(!currentItems[qIndex]) return;
    const item = currentItems[qIndex];
    $.post('idea_manager.php', { action: 'update_item', id: item.id, cluster_id: cluster_id }, function(res) {
        if(res.ok) {
            item.cluster_id = cluster_id;
            const cObj = globalClusters.find(x => x.id == cluster_id);
            item.cluster_name = cObj ? cObj.name : null;
            $('#qClusterBadge').text(item.cluster_name || '📌 Uncategorized');
            loadOverview();
            if($('#itemsModal').is(':visible')) renderItemsList();
            Toast.show('Cluster Assigned', 'success');
        }
    });
}

// Keyboard shortcuts for Questionnaire runner (safeguards text editing)
$(document).on('keydown', function(e) {
    if (!$('#questModal').is(':visible')) return;
    if ($(e.target).is('input, textarea, select, [contenteditable="true"]')) return;

    if (e.key === 'y' || e.key === 'Y' || e.key === '1') {
        qSetStatus('yes');
    } else if (e.key === 'n' || e.key === 'N' || e.key === '2') {
        qSetStatus('no');
    } else if (e.key === 'ArrowRight') {
        qNavigate(1);
    } else if (e.key === 'ArrowLeft') {
        qNavigate(-1);
    }
});

// -- Ingest JSON --
function openIngestModal() { $('#ingestJsonInput').val(''); $('#ingestModal').css('display', 'flex'); }
function downloadInstructions() { window.location.href = 'idea_manager.php?action=download_instructions'; }
function submitIngest() {
    $.post('idea_manager.php', { action: 'ingest_json', json_data: $('#ingestJsonInput').val() }, function(res) {
        if(res.ok) { Toast.show(res.message, 'success'); closeModal('ingestModal'); loadOverview(); }
        else Toast.show(res.error, 'error');
    });
}

// -- Settings & Maintenance Modal --
function openSettingsModal() { 
    // Populate cluster scope dropdown
    const $sel = $('#purgeClusterScope').empty();
    $sel.append('<option value="all">All Clusters & Uncategorized</option>');
    $sel.append('<option value="unassigned">📌 Uncategorized Ideas Only</option>');
    globalClusters.forEach(c => {
        $sel.append(`<option value="${c.id}">${esc(c.name)}</option>`);
    });

    // Reset purge chips to defaults: YES + NO (handed-off)
    purgeSelectedStatuses = ['yes', 'no'];
    updatePurgeStatusUI();

    $('#settingsModal').css('display', 'flex'); 
}

function handleSettingsFileUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const content = e.target.result;
        $.post('idea_manager.php', { action: 'ingest_json', json_data: content }, function(res) {
            if (res.ok) {
                Toast.show(res.message, 'success');
                closeModal('settingsModal');
                loadOverview();
            } else {
                Toast.show(res.error || 'Ingest failed', 'error');
            }
            $(input).val('');
        });
    };
    reader.readAsText(file);
}

// Purge status chips logic (mirrors export multi-select chips)
function updatePurgeStatusUI() {
    const hasYes = purgeSelectedStatuses.includes('yes');
    const hasNo = purgeSelectedStatuses.includes('no');
    const hasUn = purgeSelectedStatuses.includes('unassigned');
    const isAll = hasYes && hasNo && hasUn;

    $('#purgeChipAll').toggleClass('active', isAll);
    $('#purgeChipYes').toggleClass('active-yes', hasYes);
    $('#purgeChipNo').toggleClass('active-no', hasNo);
    $('#purgeChipUn').toggleClass('active-amber', hasUn);
}

function clickAllPurgeStatuses() {
    purgeSelectedStatuses = ['yes', 'no', 'unassigned'];
    updatePurgeStatusUI();
}

function togglePurgeStatus(status) {
    const isAll = (purgeSelectedStatuses.length === 3);
    if (isAll) {
        purgeSelectedStatuses = [status];
    } else {
        const idx = purgeSelectedStatuses.indexOf(status);
        if (idx >= 0) {
            if (purgeSelectedStatuses.length > 1) {
                purgeSelectedStatuses.splice(idx, 1);
            } else {
                purgeSelectedStatuses = ['yes', 'no', 'unassigned'];
            }
        } else {
            purgeSelectedStatuses.push(status);
        }
    }
    updatePurgeStatusUI();
}

function confirmBatchDelete() {
    if (purgeSelectedStatuses.length === 0) {
        Toast.show('Select at least one status', 'error');
        return;
    }

    const scopeVal = $('#purgeClusterScope').val();
    const scopeLabel = $('#purgeClusterScope option:selected').text();
    const statusLabels = purgeSelectedStatuses.map(s => s.toUpperCase()).join(' + ');

    const msg = `Are you sure you want to permanently DELETE all ideas marked [${statusLabels}] from [${scopeLabel}]?\n\nThis cannot be undone!`;
    if (!confirm(msg)) return;

    $.post('idea_manager.php', {
        action: 'delete_ideas_batch',
        statuses: purgeSelectedStatuses,
        cluster_scope: scopeVal
    }, function(res) {
        if (res.ok) {
            Toast.show(res.message, 'success');
            closeModal('settingsModal');
            loadOverview();
            if ($('#itemsModal').is(':visible')) renderItemsList();
        } else {
            Toast.show(res.error || 'Deletion failed', 'error');
        }
    });
}

function confirmTruncate(target) {
    let msg = 'Are you sure?';
    if (target === 'ideas') msg = 'Are you sure you want to delete ALL ideas? Clusters will remain.';
    else if (target === 'clusters') msg = 'Are you sure you want to delete ALL clusters? (Ideas will be unassigned)';
    else if (target === 'both') msg = 'WARNING: Are you sure you want to completely erase ALL clusters and ALL ideas?';

    if (!confirm(msg)) return;

    $.post('idea_manager.php', { action: 'truncate_table', target: target }, function(res) {
        if (res.ok) {
            Toast.show(res.message, 'success');
            closeModal('settingsModal');
            loadOverview();
        } else {
            Toast.show(res.error || 'Truncate failed', 'error');
        }
    });
}

// -- Multi-Status Filter Logic for Export --
function updateExportStatusUI() {
    const hasYes = selectedExportStatuses.includes('yes');
    const hasNo = selectedExportStatuses.includes('no');
    const hasUn = selectedExportStatuses.includes('unassigned');
    const isAll = hasYes && hasNo && hasUn;

    $('#chipAll').toggleClass('active', isAll);
    $('#chipYes').toggleClass('active-yes', hasYes);
    $('#chipNo').toggleClass('active-no', hasNo);
    $('#chipUn').toggleClass('active-amber', hasUn);
}

function clickAllStatuses() {
    selectedExportStatuses = ['yes', 'no', 'unassigned'];
    updateExportStatusUI();
}

function toggleExportStatus(status) {
    const isAll = (selectedExportStatuses.length === 3);
    if (isAll) {
        selectedExportStatuses = [status];
    } else {
        const idx = selectedExportStatuses.indexOf(status);
        if (idx >= 0) {
            if (selectedExportStatuses.length > 1) {
                selectedExportStatuses.splice(idx, 1);
            } else {
                selectedExportStatuses = ['yes', 'no', 'unassigned'];
            }
        } else {
            selectedExportStatuses.push(status);
        }
    }
    updateExportStatusUI();
}

// -- Open Export Modal (target = null for all clusters, or id/'unassigned' for single) --
function openExportModal(target = null) {
    currentExportTarget = target;
    selectedExportStatuses = ['yes', 'no', 'unassigned'];
    updateExportStatusUI();

    if (target === null) {
        $('#exportModalTitle').text('Export Clusters');
        $('#exportSingleNotice').hide();
        $('#exportClusterSelectionBlock').show();

        const list = $('#exportList').empty();
        list.append(`
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" class="ex-chk" value="unassigned" checked style="accent-color:var(--purple); width:16px; height:16px;">
                <span style="font-weight:700;">📌 Uncategorized Items</span>
            </label>
        `);
        globalClusters.forEach(c => {
            list.append(`
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" class="ex-chk" value="${c.id}" checked style="accent-color:var(--teal); width:16px; height:16px;">
                    <span>${esc(c.name)}</span>
                </label>
            `);
        });
    } else {
        $('#exportClusterSelectionBlock').hide();
        $('#exportSingleNotice').show();

        if (target === 'unassigned') {
            $('#exportModalTitle').text('Export: Uncategorized');
            $('#exportSingleName').text('📌 Uncategorized Ideas');
            $('#exportSingleDesc').text('Only items that have not yet been assigned to any cluster.');
        } else {
            const c = globalClusters.find(x => x.id == target);
            $('#exportModalTitle').text('Export: ' + (c ? c.name : 'Cluster'));
            $('#exportSingleName').text(c ? c.name : 'Cluster');
            $('#exportSingleDesc').text(c ? (c.description || 'No description.') : '');
        }
    }

    $('#exportModal').css('display', 'flex');
}

function toggleExportAll(check) { $('.ex-chk').prop('checked', check); }

function submitExport() {
    let selected = [];
    if (currentExportTarget !== null) {
        selected = [currentExportTarget];
    } else {
        $('.ex-chk:checked').each(function() { selected.push($(this).val()); });
    }
    
    if(selected.length === 0) { Toast.show('Select at least one cluster', 'error'); return; }

    const form = $('<form action="idea_manager.php" method="POST" style="display:none;"></form>');
    form.append('<input type="hidden" name="action" value="export_json">');
    selectedExportStatuses.forEach(s => form.append(`<input type="hidden" name="statuses[]" value="${s}">`));
    selected.forEach(v => form.append(`<input type="hidden" name="clusters[]" value="${v}">`));
    $('body').append(form);
    form.submit();
    form.remove();
    closeModal('exportModal');
}

$(document).ready(() => loadOverview());
</script>
</body>
</html>