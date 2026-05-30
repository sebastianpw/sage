<?php
/**
 * chromatool.php - ChromaDB Administration Tool (API V2)
 * 
 * Connects to:
 * 1. Chroma Native API (:8000/api/v2) for CRUD/Admin
 * 2. PyApi (:8009) for Embedding Generation & Semantic Search
 */

// -----------------------------------------------------------------------------
// 1. BOOTSTRAP & SETUP
// -----------------------------------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Core\PyApiVectorService;

// Security: Ensure authentication
AccessManager::authenticate();

// Configuration
define('CHROMATOOL_VERSION', '1.6.1');
define('ITEMS_PER_PAGE', 20);
define('CHROMA_DEFAULT_TENANT', 'default_tenant');
define('CHROMA_DEFAULT_DB', 'default_database');

// -----------------------------------------------------------------------------
// 2. CHROMA CLIENT CLASS (Native API V2)
// -----------------------------------------------------------------------------
class ChromaAdminV2 {
    private $host;
    private $port;
    private $apiBase;
    private $dbPath;

    public function __construct() {
        // Resolve IP logic
        $script = dirname(__DIR__) . '/bash/pyapi_echo.sh';
        $apiUrl = trim(shell_exec('sh ' . escapeshellarg($script)));
        
        if (!$apiUrl) {
            $this->host = '127.0.0.1';
        } else {
            $parsed = parse_url($apiUrl);
            $this->host = $parsed['host'] ?? '127.0.0.1';
        }
        
        $this->port = '8000'; // Standard Chroma Port
        
        // V2 Base URL
        $this->apiBase = "http://{$this->host}:{$this->port}/api/v2";
        
        // Path to specific DB context
        $this->dbPath = "/tenants/" . CHROMA_DEFAULT_TENANT . "/databases/" . CHROMA_DEFAULT_DB;
    }

    public function getHost() { return $this->host; }

    /**
     * Generic cURL execution
     */
    private function req($method, $endpoint, $data = null) {
        $url = $this->apiBase . $endpoint;
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        }

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new Exception("cURL Error: $err");
        
        $json = json_decode($res, true);
        
        if ($code >= 400) {
            $msg = isset($json['detail']) ? (is_array($json['detail']) ? json_encode($json['detail']) : $json['detail']) : "HTTP $code";
            throw new Exception("Chroma API Error ($code): $msg");
        }

        return $json;
    }

    public function heartbeat() {
        return $this->req('GET', '/heartbeat');
    }

    public function version() {
        return $this->req('GET', '/version');
    }

    /**
     * List all collections in default db
     */
    public function listCollections() {
        return $this->req('GET', $this->dbPath . '/collections');
    }

    /**
     * Create collection
     */
    public function createCollection($name, $metadata = []) {
        return $this->req('POST', $this->dbPath . '/collections', [
            'name' => $name,
            'metadata' => (object)$metadata,
            'get_or_create' => false
        ]);
    }

    /**
     * Delete collection by Name
     * Note: Swagger says collection_id, but the API expects Name for this op.
     */
    public function deleteCollection($name) {
        // Use rawurlencode to handle spaces/special characters
        return $this->req('DELETE', $this->dbPath . '/collections/' . rawurlencode($name));
    }

    /**
     * Helper: Resolve Collection Name to ID
     * Chroma V2 browsing ops often require the UUID
     */
    public function getCollectionIdByName($name) {
        $colls = $this->listCollections();
        foreach ($colls as $c) {
            if ($c['name'] === $name) return $c['id'];
        }
        throw new Exception("Collection '$name' not found");
    }

    /**
     * Browse data (Get items)
     */
    public function getItems($collectionId, $limit = 20, $offset = 0) {
        return $this->req('POST', $this->dbPath . "/collections/$collectionId/get", [
            'limit' => $limit,
            'offset' => $offset,
            'include' => ['embeddings', 'metadatas', 'documents']
        ]);
    }

    /**
     * Delete items
     */
    public function deleteItems($collectionId, $ids) {
        return $this->req('POST', $this->dbPath . "/collections/$collectionId/delete", [
            'ids' => $ids
        ]);
    }
}

// -----------------------------------------------------------------------------
// 3. LOGIC & ROUTING
// -----------------------------------------------------------------------------

$chroma = new ChromaAdminV2();
$pyApi  = new PyApiVectorService(); 

$action = $_GET['action'] ?? 'home';
$currentCollectionName = $_GET['collection'] ?? '';
$message = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pAction = $_POST['action'] ?? '';

        if ($pAction === 'create_collection') {
            $name = trim($_POST['name'] ?? '');
            if (!$name) throw new Exception("Collection name required");
            $chroma->createCollection($name, ["created_via" => "chromatool.php"]);
            $message = ['type' => 'success', 'text' => "Collection '$name' created."];
            $currentCollectionName = $name;
            $action = 'browse';
        }

        if ($pAction === 'delete_collection') {
            $name = $_POST['name'] ?? '';
            // Pass name directly, do NOT lookup ID
            $chroma->deleteCollection($name);
            $message = ['type' => 'success', 'text' => "Collection '$name' deleted successfully."];
            $currentCollectionName = '';
            $action = 'home';
        }

        if ($pAction === 'delete_item') {
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $colId = $chroma->getCollectionIdByName($currentCollectionName);
            $chroma->deleteItems($colId, $ids);
            $message = ['type' => 'success', 'text' => count($ids) . " item(s) deleted."];
        }

        if ($pAction === 'add_text') {
            $text = $_POST['document'] ?? '';
            $metaStr = $_POST['metadata'] ?? '{}';
            $meta = json_decode($metaStr, true);
            if (!$meta && $metaStr) throw new Exception("Invalid Metadata JSON");
            
            // Manually hit PyApi add_text endpoint
            $endpoint = $pyApi->getApiUrl() . '/chroma/add_text';
            $postData = [
                'text' => $text,
                'collection' => $currentCollectionName,
                'metadata' => $meta ?: (object)[]
            ];
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $errJson = json_decode($res, true);
                $errMsg = $errJson['detail'] ?? $res;
                throw new Exception("PyApi Error ($httpCode): $errMsg");
            }
            
            $message = ['type' => 'success', 'text' => "Document added successfully via PyApi."];
            $action = 'browse';
        }

    } catch (Exception $e) {
        $message = ['type' => 'error', 'text' => $e->getMessage()];
    }
}

// Global Data Fetching
try {
    $collections = $chroma->listCollections();
    usort($collections, function($a, $b) { return strcmp($a['name'], $b['name']); });
} catch (Exception $e) {
    $collections = [];
    $message = ['type' => 'error', 'text' => "Could not connect to Chroma V2 at {$chroma->getHost()}:8000. Is the tablet on?"];
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.65">
    <title>ChromaTool - <?= htmlspecialchars($currentCollectionName ?: 'Home') ?></title>
    <!-- Reuse CSS from dbtool/base -->
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <style>
        /* Chromatool Styles */
        .ct-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .ct-nav { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .col-selector { padding: 0.5rem 1rem; border-radius: 6px; background: rgba(var(--card-rgb), 1); border: 1px solid rgba(var(--muted-border-rgb), 0.12); color: rgba(var(--text-rgb), 1); font-size: 0.9rem; min-width: 200px; }
        
        .json-block { font-family: 'Courier New', monospace; font-size: 0.85rem; color: rgba(var(--text-rgb), 0.8); white-space: pre-wrap; word-break: break-all; max-height: 100px; overflow-y: auto; }
        .id-badge { background: rgba(var(--accent-rgb), 0.1); color: rgba(var(--accent-rgb), 1); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.85rem; }
        .doc-preview { font-size: 0.9rem; color: rgba(var(--text-rgb), 0.9); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        
        .embedding-viz { display: flex; gap: 1px; height: 10px; width: 100px; background: rgba(0,0,0,0.1); margin-top: 4px;}
        .embedding-bar { flex: 1; background: rgba(var(--accent-rgb), 0.5); }
        
        /* Stats Cards */
        .stat-card { background: rgba(var(--card-rgb), 1); padding: 1rem; border-radius: 8px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); text-align: center; }
        .stat-val { font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { font-size: 0.85rem; color: rgba(var(--text-rgb), 0.6); }

        .search-result-card { background: rgba(var(--card-rgb), 1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid rgba(var(--accent-rgb), 0.5); }
        .search-score { float: right; font-weight: bold; font-family: monospace; }
        .score-good { color: #4ade80; } .score-med { color: #facc15; } .score-bad { color: #f87171; }

        /* High Contrast Zebra Striping */
        .data-table tbody tr:nth-child(even) { background: rgba(0,0,0,0.08); } 
        .data-table tbody tr:nth-child(odd) { background: transparent; }
        
        /* Dark Mode Contrast */
        [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: rgba(255,255,255,0.08); }
        [data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: transparent; }

        /* Hover effect stronger */
        .data-table tbody tr:hover { background: rgba(var(--accent-rgb), 0.15) !important; }
        
        .btn-icon-only { padding: 0.25rem 0.5rem; }
    </style>
</head>
<body>
<div class="container">

    <!-- Header -->
    <div class="ct-header">
        <div class="ct-nav">
            <select class="col-selector" onchange="window.location.href='?collection=' + this.value + '&action=browse'">
                <option value="" disabled <?= !$currentCollectionName ? 'selected' : '' ?>>Select Collection...</option>
                <?php foreach ($collections as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']) ?>" <?= $currentCollectionName === $c['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <a href="?action=home" class="btn btn-sm <?= $action === 'home' ? 'btn-accent' : '' ?>">Chroma Home</a>
            <?php if ($currentCollectionName): ?>
                <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=browse" class="btn btn-sm <?= $action === 'browse' ? 'btn-accent' : '' ?>">Browse</a>
                <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=search" class="btn btn-sm <?= $action === 'search' ? 'btn-accent' : '' ?>">Search</a>
                <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=insert" class="btn btn-sm <?= $action === 'insert' ? 'btn-accent' : '' ?>">Insert</a>
                <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=manage" class="btn btn-sm <?= $action === 'manage' ? 'btn-accent' : '' ?>">Manage</a>
            <?php endif; ?>
        </div>
        
        <div style="font-size: 0.8rem; opacity: 0.7; text-align: right;">
            <div>Chroma: <?= $chroma->getHost() ?>:8000</div>
            <div>PyAPI: <?= parse_url($pyApi->getApiUrl())['host'] ?? '?' ?>:8009</div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notification notification-<?= htmlspecialchars($message['type']) ?>">
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <!-- =================================================================== -->
    <!-- VIEW: HOME / DASHBOARD -->
    <!-- =================================================================== -->
    <?php if ($action === 'home'): ?>
        <div class="section">
            <h2 class="section-header">Server Status (API V2)</h2>
            
            <!--
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-val"><?= count($collections) ?></div>
                    <div class="stat-label">Collections</div>
                </div>
                <?php 
                    $hb = $chroma->heartbeat(); 
                    // FIX: Check if key exists using null coalescing
                    $hbVal = isset($hb['nanosecond']) ? number_format($hb['nanosecond'] / 1000000000, 0) : 'Active';
                    $ver = $chroma->version();
                    $verStr = is_string($ver) ? $ver : ($ver['version'] ?? 'Unknown');
                ?>
                <div class="stat-card">
                    <div class="stat-val" style="font-size: 1rem;"><?= $hbVal ?></div>
                    <div class="stat-label">Heartbeat</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-val" style="font-size: 1rem;">v<?= $verStr ?></div>
                    <div class="stat-label">Chroma Version</div>
                </div>
                
            </div>
            -->

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2 class="section-header">Collections</h2>
                <button onclick="document.getElementById('createColForm').style.display='block'" class="btn btn-sm btn-primary">+ Create</button>
            </div>
            
            <div id="createColForm" class="card" style="display:none; margin-bottom:1rem;">
                <form method="POST">
                    <input type="hidden" name="action" value="create_collection">
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Collection Name</label>
                            <input type="text" name="name" class="form-control" pattern="[a-zA-Z0-9_-]+" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Create</button>
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.card').style.display='none'">Cancel</button>
                    </div>
                </form>
            </div>

            <?php if (empty($collections)): ?>
                <div class="empty-state">No collections found.</div>
            <?php else: ?>
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>ID</th>
                                <th>Metadata</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections as $c): ?>
                            <tr>
                                <td>
                                    <a href="?collection=<?= urlencode($c['name']) ?>&action=browse" style="font-weight:bold; color:var(--accent-rgb); text-decoration:none;">
                                        <?= htmlspecialchars($c['name']) ?>
                                    </a>
                                </td>
                                <td style="font-family:monospace; font-size:0.8rem; opacity:0.7;"><?= htmlspecialchars($c['id']) ?></td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars(json_encode($c['metadata'])) ?></td>
                                <td style="text-align:right;">
                                    <div style="display:flex; justify-content:flex-end; gap:5px;">
                                        <a href="?collection=<?= urlencode($c['name']) ?>&action=browse" class="btn btn-sm" title="Browse">📂</a>
                                        <a href="?collection=<?= urlencode($c['name']) ?>&action=search" class="btn btn-sm" title="Search">🔍</a>
                                        <!-- DELETE BUTTON ADDED HERE -->
                                        <form method="POST" onsubmit="return confirm('⚠️ DANGER: Delete collection \'<?= htmlspecialchars($c['name']) ?>\' and ALL data?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_collection">
                                            <input type="hidden" name="name" value="<?= htmlspecialchars($c['name']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-icon-only" title="Delete Collection">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <!-- =================================================================== -->
    <!-- VIEW: BROWSE -->
    <!-- =================================================================== -->
    <?php elseif ($action === 'browse' && $currentCollectionName): ?>
        <?php
            $page = max(1, intval($_GET['page'] ?? 1));
            $offset = ($page - 1) * ITEMS_PER_PAGE;
            
            try {
                $colId = $chroma->getCollectionIdByName($currentCollectionName);
                // Get data including embeddings
                $data = $chroma->getItems($colId, ITEMS_PER_PAGE, $offset);
                
                // Chroma V2 returns separated arrays: ids[], embeddings[], etc.
                $count = isset($data['ids']) ? count($data['ids']) : 0;
                $rows = [];
                for ($i = 0; $i < $count; $i++) {
                    $rows[] = [
                        'id' => $data['ids'][$i],
                        'embedding' => $data['embeddings'][$i] ?? [],
                        'metadata' => $data['metadatas'][$i] ?? [],
                        'document' => $data['documents'][$i] ?? null
                    ];
                }
            } catch (Exception $e) {
                echo "<div class='notification notification-error'>Error loading data: {$e->getMessage()}</div>";
                $rows = [];
            }
        ?>
        <div class="section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h2 class="section-header">Browse: <?= htmlspecialchars($currentCollectionName) ?></h2>
                <button onclick="if(confirm('Delete selected?')) document.getElementById('bulkDeleteForm').submit()" class="btn btn-sm btn-danger">Delete Selected</button>
            </div>

            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <p>No items found in this page range.</p>
                    <?php if ($page > 1): ?>
                        <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=browse&page=1" class="btn btn-secondary">Back to Start</a>
                    <?php else: ?>
                        <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=insert" class="btn btn-primary">Insert Data</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" id="bulkDeleteForm">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="ids" id="deleteIdsInput">
                    
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="30"><input type="checkbox" onclick="toggleAll(this)"></th>
                                    <th width="150">ID</th>
                                    <th>Document</th>
                                    <th width="200">Metadata</th>
                                    <th width="100">Vector</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><input type="checkbox" class="row-check" value="<?= htmlspecialchars($r['id']) ?>"></td>
                                    <td style="vertical-align:top;"><span class="id-badge"><?= htmlspecialchars($r['id']) ?></span></td>
                                    <td style="vertical-align:top;">
                                        <div class="doc-preview" title="<?= htmlspecialchars($r['document']) ?>">
                                            <?= htmlspecialchars($r['document'] ?? '[No Document Text]') ?>
                                        </div>
                                    </td>
                                    <td style="vertical-align:top;">
                                        <div class="json-block"><?= htmlspecialchars(json_encode($r['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></div>
                                    </td>
                                    <td style="vertical-align:top;">
                                        <?php if (!empty($r['embedding'])): ?>
                                            <div style="font-size:0.7rem; color:rgba(var(--text-rgb),0.5)">
                                                Dim: <?= count($r['embedding']) ?>
                                            </div>
                                            <!-- Simple visualizer -->
                                            <div class="embedding-viz">
                                                <?php 
                                                // Draw a few bars based on first 10 dims
                                                for($k=0; $k<10; $k++) {
                                                    $val = isset($r['embedding'][$k]) ? (float)$r['embedding'][$k] : 0;
                                                    $opacity = min(1, max(0.1, abs($val) * 5));
                                                    echo "<div class='embedding-bar' style='opacity:{$opacity}'></div>";
                                                }
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="opacity:0.5">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=browse&page=<?= $page - 1 ?>" class="btn btn-sm btn-secondary">Previous</a>
                    <?php endif; ?>
                    <span class="btn btn-sm" style="pointer-events:none; background:transparent;">Page <?= $page ?></span>
                    <?php if (count($rows) >= ITEMS_PER_PAGE): ?>
                        <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=browse&page=<?= $page + 1 ?>" class="btn btn-sm btn-secondary">Next</a>
                    <?php endif; ?>
                </div>

                <script>
                    function toggleAll(source) {
                        document.querySelectorAll('.row-check').forEach(cb => cb.checked = source.checked);
                        updateDeleteInput();
                    }
                    document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateDeleteInput));
                    
                    function updateDeleteInput() {
                        const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
                        document.getElementById('deleteIdsInput').value = JSON.stringify(ids);
                    }
                </script>
            <?php endif; ?>
        </div>

    <!-- =================================================================== -->
    <!-- VIEW: SEARCH (SEMANTIC) -->
    <!-- =================================================================== -->
    <?php elseif ($action === 'search' && $currentCollectionName): ?>
        <div class="section">
            <h2 class="section-header">Semantic Search</h2>
            
            <div class="card" style="margin-bottom:2rem;">
                <form method="GET">
                    <input type="hidden" name="collection" value="<?= htmlspecialchars($currentCollectionName) ?>">
                    <input type="hidden" name="action" value="search">
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Query Text</label>
                            <input type="text" name="q" class="form-control" placeholder="Describe what you are looking for..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Results</label>
                            <input type="number" name="n" class="form-control" value="<?= intval($_GET['n'] ?? 5) ?>" min="1" max="50" style="width:100px;">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>

            <?php if (!empty($_GET['q'])): ?>
                <?php
                    // Perform Search via PyApi
                    try {
                        $results = $pyApi->query(
                            $_GET['q'], 
                            null, 
                            $currentCollectionName, 
                            'text', 
                            intval($_GET['n'] ?? 5)
                        );
                        
                        $resData = $results['result'] ?? [];
                        
                        if (empty($resData['ids'][0])) {
                            echo "<div class='empty-state'>No matches found.</div>";
                        } else {
                            // Chroma returns arrays of arrays for queries (batch support)
                            $ids = $resData['ids'][0];
                            $distances = $resData['distances'][0] ?? [];
                            $docs = $resData['documents'][0] ?? [];
                            $metas = $resData['metadatas'][0] ?? [];
                            
                            echo "<h3 style='margin-bottom:1rem;'>Results</h3>";
                            
                            for ($i = 0; $i < count($ids); $i++) {
                                $dist = $distances[$i];
                                // Cosine distance: 0 is exact match
                                $scoreClass = $dist < 0.3 ? 'score-good' : ($dist < 0.7 ? 'score-med' : 'score-bad');
                                
                                echo "<div class='search-result-card'>";
                                echo "<div class='search-score $scoreClass'>Dist: " . number_format($dist, 4) . "</div>";
                                echo "<div style='margin-bottom:0.5rem;'><span class='id-badge'>{$ids[$i]}</span></div>";
                                echo "<div style='margin-bottom:0.5rem; font-size:1.1rem;'>" . htmlspecialchars($docs[$i]) . "</div>";
                                echo "<div class='json-block'>" . json_encode($metas[$i], JSON_UNESCAPED_SLASHES) . "</div>";
                                echo "</div>";
                            }
                        }
                        
                    } catch (Exception $e) {
                        echo "<div class='notification notification-error'>Search Failed: " . $e->getMessage() . "</div>";
                    }
                ?>
            <?php endif; ?>
        </div>

    <!-- =================================================================== -->
    <!-- VIEW: INSERT -->
    <!-- =================================================================== -->
    <?php elseif ($action === 'insert' && $currentCollectionName): ?>
        <div class="section">
            <h2 class="section-header">Insert Data</h2>
            <div class="notification notification-info">
                This uses the Python API to automatically chunk long text and generate embeddings.
            </div>
            
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="action" value="add_text">
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Document Text</label>
                            <textarea name="document" class="form-control" rows="8" required placeholder="Enter content here..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Metadata (JSON)</label>
                            <textarea name="metadata" class="form-control" rows="4" style="font-family:monospace;">{
    "source": "manual_entry",
    "author": "admin"
}</textarea>
                        </div>
                    </div>
                    <div class="card-footer flex-gap">
                        <button type="submit" class="btn btn-primary">Add Document</button>
                        <a href="?collection=<?= urlencode($currentCollectionName) ?>&action=browse" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- VIEW: MANAGE -->
    <!-- =================================================================== -->
    <?php elseif ($action === 'manage' && $currentCollectionName): ?>
        <div class="section">
            <h2 class="section-header">Manage Collection: <?= htmlspecialchars($currentCollectionName) ?></h2>
            
            <div class="card" style="border-color: rgba(255, 0, 0, 0.3);">
                <div class="card-body">
                    <h3>Danger Zone</h3>
                    <p>Delete this entire collection and all its vectors.</p>
                </div>
                <div class="card-footer">
                    <form method="POST" onsubmit="return confirm('EXTREME DANGER: Are you sure you want to delete this ENTIRE collection? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete_collection">
                        <input type="hidden" name="name" value="<?= htmlspecialchars($currentCollectionName) ?>">
                        <button type="submit" class="btn btn-danger">Delete Collection</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>