<?php
// public/view_md_categories.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; 

$spw = \App\Core\SpwBase::getInstance();

// ----------------------
// Inline API handler
// ----------------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    header('Content-Type: application/json; charset=utf-8');

    try {
        if ($action === 'get_category' && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            $stmt = $pdo->prepare("SELECT id, name FROM documentation_categories WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat) {
                echo json_encode(['status' => 'success', 'category' => $cat]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Category not found']);
            }
            exit;
        }

        if ($action === 'save_category') {
            $data = null;
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $data = $decoded;
            }
            if (!$data) $data = $_POST;

            $name = trim($data['name'] ?? '');
            $id = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;

            if ($name === '') {
                echo json_encode(['status' => 'error', 'message' => 'Name is required']);
                exit;
            }
            if (mb_strlen($name) > 255) {
                echo json_encode(['status' => 'error', 'message' => 'Name too long (max 255 chars)']);
                exit;
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE documentation_categories SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
                echo json_encode(['status' => 'success', 'message' => 'Category updated', 'id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO documentation_categories (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
                $newId = (int)$pdo->lastInsertId();
                echo json_encode(['status' => 'success', 'message' => 'Category created', 'id' => $newId]);
            }
            exit;
        }

        if ($action === 'delete_category') {
            $data = null;
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $data = $decoded;
            }
            if (!$data) $data = $_POST;

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if (!$id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid category id']);
                exit;
            }

            try {
                $pdo->beginTransaction();

                $upd = $pdo->prepare("UPDATE documentations SET category_id = NULL WHERE category_id = :id");
                $upd->execute([':id' => $id]);

                $del = $pdo->prepare("DELETE FROM documentation_categories WHERE id = :id");
                $del->execute([':id' => $id]);

                $affected = $del->rowCount();

                $pdo->commit();

                if ($affected > 0) {
                    echo json_encode(['status' => 'success', 'message' => 'Category deleted']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Category not found']);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// CATEGORIES LISTING MODE
// ==========================================

$cats = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$countsQuery = $pdo->query("
    SELECT c.id, COUNT(d.id) as doc_count 
    FROM documentation_categories c 
    LEFT JOIN documentations d ON c.id = d.category_id AND d.is_active = 1 
    GROUP BY c.id
")->fetchAll(PDO::FETCH_ASSOC);

$countMap = [];
foreach ($countsQuery as $row) {
    $countMap[$row['id']] = $row['doc_count'];
}

$uncatQuery = $pdo->query("
    SELECT COUNT(*) as count 
    FROM documentations 
    WHERE category_id IS NULL AND is_active = 1
")->fetch();
$uncatCount = $uncatQuery['count'] ?? 0;

$pageTitle = "Documentation Categories";
ob_start();
?>
<!-- THEME INIT SCRIPT -->
<script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        } catch (e) {}
    })();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    :root {
        --bg: #f6f8fa;
        --card: #ffffff;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.55);
        --focus-ring: rgba(9, 105, 218, 0.15);
        --card-elevation: 0 4px 14px rgba(0,0,0,0.08);
        --blue-light-bg: rgba(9, 105, 218, 0.08);
        --blue-light-border: rgba(9, 105, 218, 0.25);
    }
    :root[data-theme="dark"] {
        --bg: #0d1117;
        --card: #161b22;
        --border: #30363d;
        --text: #c9d1d9;
        --text-muted: #8b949e;
        --accent: #58a6ff;
        --green: #238636;
        --red: #da3633;
        --orange: #f59e0b;
        --overlay-bg: rgba(0,0,0,0.75);
        --focus-ring: rgba(88, 166, 255, 0.2);
        --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
        --blue-light-bg: rgba(88, 166, 255, 0.12);
        --blue-light-border: rgba(88, 166, 255, 0.3);
    }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --green: #238636;
            --red: #da3633;
            --orange: #f59e0b;
            --overlay-bg: rgba(0,0,0,0.75);
            --focus-ring: rgba(88, 166, 255, 0.2);
            --card-elevation: 0 6px 18px rgba(0,0,0,0.4);
            --blue-light-bg: rgba(88, 166, 255, 0.12);
            --blue-light-border: rgba(88, 166, 255, 0.3);
        }
    }

    * { box-sizing: border-box; }
    body { 
        background-color: var(--bg) !important; 
        color: var(--text) !important; 
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 10px; 
        margin: 0;
        transition: background-color 0.2s, color 0.2s; 
    }
    
    .container { 
        max-width: 820px; 
        margin: 0 auto; 
    }
    
    .header-row { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 16px; 
        border-bottom: 1px solid var(--border); 
        padding-bottom: 10px; 
        gap: 8px;
    }

    .btn {
        padding: 6px 11px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.15s, border-color 0.15s, color 0.15s, opacity 0.15s, transform 0.1s;
        height: 34px;
        flex: 0 0 auto;
    }
    .btn:active { transform: scale(0.97); }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { filter: brightness(1.08); }
    .btn-save { background: var(--green); color: #fff; }
    .btn-save:hover { filter: brightness(1.08); }
    .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
    .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
    .btn-sm { padding: 4px 8px; font-size: 0.78rem; height: 28px; }

    .input-control {
        width: 100%;
        padding: 7px 10px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.88rem;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .input-control:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--focus-ring);
    }

    .cat-list { 
        list-style: none; 
        padding: 0; 
        margin: 0;
    }
    
    .cat-item { 
        background: var(--card); 
        margin-bottom: 6px; 
        padding: 9px 12px; 
        border-radius: 6px; 
        border: 1px solid var(--border); 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s; 
    }
    .cat-item:hover { 
        border-color: var(--accent); 
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transform: translateY(-1px); 
    }
    
    .cat-link { 
        font-size: 0.92rem; 
        font-weight: 600; 
        color: var(--text); 
        text-decoration: none; 
        flex: 1; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        margin-right: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: color 0.15s;
    }
    .cat-link:hover { 
        color: var(--accent); 
    }
    
    .meta { 
        font-size: 12px; 
        color: var(--text-muted); 
        display: flex; 
        gap: 6px; 
        align-items: center; 
        flex-shrink: 0; 
    }
    
    .doc-count { 
        background: var(--blue-light-bg); 
        color: var(--accent); 
        padding: 2px 7px; 
        border-radius: 10px; 
        font-size: 11px; 
        font-weight: 600; 
        border: 1px solid var(--blue-light-border);
    }
    
    .icon-btn { 
        text-decoration: none; 
        border: none; 
        background: none; 
        cursor: pointer; 
        padding: 4px 6px; 
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: color 0.15s, background 0.15s, transform 0.1s; 
    }
    .icon-btn:hover { 
        color: var(--accent); 
        background: var(--bg); 
        transform: scale(1.08); 
    }
    .icon-btn.btn-delete:hover { 
        color: var(--red); 
    }
    
    .empty-state { 
        text-align: center; 
        padding: 40px 20px; 
        color: var(--text-muted); 
        font-style: italic; 
        font-size: 0.9rem;
    }

    /* Modal styles */
    .spw-modal-backdrop {
        position: fixed;
        inset: 0;
        background: var(--overlay-bg);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 12px;
    }
    .spw-modal {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        width: 100%;
        max-width: 420px;
        padding: 18px 20px;
        box-shadow: var(--card-elevation);
    }
    .spw-modal h3 { 
        margin: 0 0 14px 0; 
        font-size: 1.05rem; 
        font-weight: 600; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
    }
    .spw-modal .field { margin-bottom: 12px; }
    .spw-modal .actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 14px;
        padding-top: 10px;
        border-top: 1px solid var(--border);
    }

    @media (max-width: 600px) {
        .cat-item { padding: 8px 10px; }
        .cat-link { font-size: 0.88rem; }
    }
</style>

<div class="container">
    <div class="header-row">
        <div style="display:flex; align-items:center; gap:8px; margin-left: 20px;">
            <a href="view_md.php" class="btn btn-ghost btn-sm" title="Back to Documents"><i class="bi bi-arrow-left"></i> Docs</a>
            <h2 style="margin:0; font-size:1.05rem; font-weight:600;">Categories</h2>
        </div>
        <div style="display:flex; gap:6px; align-items:center;">
            <button id="btnExportMd" class="btn btn-ghost btn-sm" onclick="openMdExportModal()"><i class="bi bi-download"></i> Export</button>
            <a href="edit_category.php" class="btn btn-save btn-sm btn-create"><i class="bi bi-plus-lg"></i> New</a>
        </div>
    </div>

    <!-- Categories List -->
    <div id="catsContainer">
        <?php if(empty($cats) && $uncatCount == 0): ?>
            <div class="empty-state">No categories found. Create your first one!</div>
        <?php else: ?>
            <ul class="cat-list">
                <!-- Uncategorized category -->
                <?php if($uncatCount > 0): ?>
                <li class="cat-item">
                    <a href="view_md.php?category_id=0" class="cat-link">
                        <i class="bi bi-folder" style="color:var(--text-muted); font-size:1.05rem;"></i>
                        <span>Uncategorized</span>
                    </a>
                    <div class="meta">
                        <span class="doc-count"><?php echo $uncatCount; ?> doc<?php echo $uncatCount != 1 ? 's' : ''; ?></span>
                    </div>
                </li>
                <?php endif; ?>
                
                <!-- Regular categories -->
                <?php foreach($cats as $cat): 
                    $docCount = $countMap[$cat['id']] ?? 0;
                ?>
                    <li class="cat-item" id="cat-row-<?= $cat['id'] ?>">
                        <a href="view_md.php?category_id=<?= $cat['id'] ?>" class="cat-link">
                            <i class="bi bi-folder-fill" style="color:var(--orange); font-size:1.05rem;"></i>
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        </a>
                        <div class="meta">
                            <span class="doc-count"><?php echo $docCount; ?> doc<?php echo $docCount != 1 ? 's' : ''; ?></span>
                            <a href="edit_category.php?id=<?= $cat['id'] ?>" class="icon-btn" title="Edit Category"><i class="bi bi-pencil"></i></a>
                            <button onclick="deleteCategory(<?= $cat['id'] ?>)" class="icon-btn btn-delete" title="Delete Category"><i class="bi bi-trash"></i></button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Modal overlay -->
<div id="spwModalBackdrop" class="spw-modal-backdrop" role="dialog" aria-hidden="true">
    <div class="spw-modal" role="document" aria-labelledby="spwModalTitle">
        <h3 id="spwModalTitle"><i class="bi bi-folder-plus" style="color:var(--accent);"></i> New Category</h3>
        <div class="field">
            <label style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:5px; color:var(--text-muted)">Name</label>
            <input type="text" id="spwCategoryName" class="input-control" placeholder="Category name">
        </div>
        <div id="spwModalError" style="display:none; color:var(--red); font-size:0.82rem; margin-bottom:6px;"></div>
        <div class="actions">
            <button class="btn btn-ghost btn-sm" onclick="closeSpwModal()">Cancel</button>
            <button class="btn btn-save btn-sm" id="spwSaveBtn" onclick="saveCategory()"><i class="bi bi-save2"></i> Save</button>
        </div>
    </div>
</div>

<!-- ===== MD EXPORT TREE MODAL ===== -->
<style>
.mde-modal-bg { 
    position: fixed; 
    inset: 0; 
    background: var(--overlay-bg); 
    backdrop-filter: blur(4px); 
    -webkit-backdrop-filter: blur(4px);
    display: none; 
    align-items: center; 
    justify-content: center; 
    z-index: 99999; 
    padding: 12px;
}
.mde-modal-bg.open { display: flex; }
.mde-modal { 
    width: min(780px, 96vw); 
    max-height: 90vh; 
    background: var(--card); 
    border: 1px solid var(--border); 
    border-radius: 10px; 
    display: flex; 
    flex-direction: column; 
    overflow: hidden; 
    box-shadow: var(--card-elevation); 
}
.mde-header { 
    padding: 14px 18px; 
    border-bottom: 1px solid var(--border); 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    flex-shrink: 0; 
}
.mde-header h3 { margin: 0; font-size: 1.05rem; flex: 1; display: flex; align-items: center; gap: 8px; }
.mde-close { 
    background: none; 
    border: none; 
    cursor: pointer; 
    color: var(--text-muted); 
    font-size: 1rem; 
    padding: 4px; 
    border-radius: 4px; 
    transition: color 0.15s, background 0.15s; 
    display: flex;
    align-items: center;
    justify-content: center;
}
.mde-close:hover { color: var(--text); background: var(--bg); }
.mde-body { 
    padding: 14px 18px; 
    display: flex; 
    flex-direction: column; 
    gap: 12px; 
    overflow-y: auto; 
    flex: 1; 
    min-height: 0; 
}
.mde-desc { font-size: 0.8rem; color: var(--text-muted); line-height: 1.45; padding: 0 2px; margin: 0; }
.mde-picker-header { 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    padding: 8px 12px; 
    border-bottom: 1px solid var(--border); 
    flex-shrink: 0; 
    background: var(--card); 
}
.mde-picker-header input { 
    flex: 1; 
    padding: 6px 10px; 
    border: 1px solid var(--border); 
    border-radius: 6px; 
    background: var(--bg); 
    color: var(--text); 
    font-size: 0.85rem; 
}
.mde-picker-header input:focus { outline: none; border-color: var(--accent); }
.mde-picker-select-all { 
    background: none; 
    border: none; 
    color: var(--accent); 
    font-size: 0.78rem; 
    cursor: pointer; 
    padding: 0; 
    font-weight: 600; 
}
.mde-picker-select-all:hover { text-decoration: underline; }
.mde-picker-tree-wrap { 
    flex: 1; 
    overflow-y: auto; 
    min-height: 200px; 
    padding: 4px 0; 
    background: var(--bg); 
    border: 1px solid var(--border); 
    border-radius: 6px; 
}
.mde-picker-loading { padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem; }
.mde-tree-node { 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    padding: 5px 10px; 
    cursor: pointer; 
    user-select: none; 
    transition: background 0.1s; 
    font-size: 0.86rem; 
    border-radius: 4px; 
    margin: 1px 4px; 
}
.mde-tree-node:hover { background: var(--blue-light-bg); }
.mde-tree-node input[type=checkbox] { width: 14px; height: 14px; accent-color: var(--accent); cursor: pointer; flex-shrink: 0; margin: 0; }
.mde-tree-node input[type=checkbox]:indeterminate { opacity: 0.7; }
.mde-node-toggle { 
    width: 16px; 
    height: 16px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    flex-shrink: 0; 
    font-size: 0.65rem; 
    color: var(--text-muted); 
    cursor: pointer; 
    border-radius: 3px; 
    transition: background 0.1s, transform 0.15s; 
}
.mde-node-toggle:hover { background: rgba(59,130,246,0.12); }
.mde-node-toggle.open { transform: rotate(90deg); }
.mde-node-icon { font-size: 0.9rem; flex-shrink: 0; opacity: 0.75; }
.mde-node-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mde-tree-node.is-folder > .mde-node-label { font-weight: 600; color: var(--text); }
.mde-tree-node.is-node > .mde-node-label { color: var(--text-muted); }
.mde-tree-children { display: none; }
.mde-tree-children.open { display: block; }
.mde-options-strip { display: flex; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
.mde-option-row { 
    flex: 1; 
    min-width: 160px; 
    padding: 8px 12px; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    background: var(--bg); 
    border: 1px solid var(--border); 
    border-radius: 6px; 
    font-size: 0.85rem; 
}
.mde-option-row label { flex: 1; cursor: pointer; }
.mde-option-row input[type=checkbox] { width: 15px; height: 15px; cursor: pointer; accent-color: var(--accent); flex-shrink: 0; }
.mde-footer { 
    padding: 12px 18px; 
    border-top: 1px solid var(--border); 
    display: flex; 
    gap: 8px; 
    justify-content: flex-end; 
    align-items: center; 
    flex-shrink: 0; 
}
.mde-footer-left { flex: 1; font-size: 0.8rem; color: var(--text-muted); }
</style>

<div class="mde-modal-bg" id="mde-modal-bg">
    <div class="mde-modal">
        <div class="mde-header">
            <h3><i class="bi bi-download" style="color:var(--accent);"></i> Export Documents</h3>
            <button class="mde-close" onclick="mdeCloseModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="mde-body">
            <p class="mde-desc">
                Select documents and/or categories to export. MD Content is always included.
            </p>
            <div style="display:flex; flex-direction:column; flex:1; min-height:0; gap:0;">
                <div class="mde-picker-header">
                    <input type="text" id="mdeSearchInput" placeholder="Search docs & content..." oninput="mdeDebounceSearch()">
                    <span id="mdeSearchSpinner" style="display:none; font-size:0.8rem; margin-left:5px;"><i class="bi bi-hourglass-split"></i></span>
                    <button class="mde-picker-select-all" onclick="mdePickerToggleAll()" style="margin-left:auto;">Select all</button>
                </div>
                <div class="mde-picker-tree-wrap" id="mde-picker-tree-wrap">
                    <div class="mde-picker-loading">Loading tree…</div>
                </div>
            </div>
            <div class="mde-options-strip">
                <div class="mde-option-row">
                    <input type="checkbox" id="mde-with-meta" checked>
                    <label for="mde-with-meta">
                        <strong>Include Meta Info</strong>
                        <span style="color:var(--text-muted); font-size:0.75rem; display:block; margin-top:1px;">Keywords, short description, description.</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="mde-footer">
            <span class="mde-footer-left" id="mde-picker-count"></span>
            <button class="btn btn-ghost btn-sm" onclick="mdeCloseModal()">Cancel</button>
            <button class="btn btn-primary btn-sm" id="mde-export-btn" onclick="mdeDoExport()">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>
</div>

<script>
    let mdeTreeRaw = [];
    let mdeTreeChecked = new Set();
    let mdeSearchTimer = null;

    function openMdExportModal() {
        document.getElementById('mde-modal-bg').classList.add('open');
        mdeLoadTree();
    }

    function mdeCloseModal() {
        document.getElementById('mde-modal-bg').classList.remove('open');
        document.getElementById('mdeSearchInput').value = '';
    }

    function mdeLoadTree() {
        const wrap = document.getElementById('mde-picker-tree-wrap');
        wrap.innerHTML = '<div class="mde-picker-loading">Loading tree…</div>';
        
        fetch('api_md.php?action=export_tree_data')
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') {
                    wrap.innerHTML = '<div class="mde-picker-loading">Failed to load tree.</div>'; return;
                }
                
                mdeTreeRaw = [];
                mdeTreeRaw.push({ id: 'c_0', parent: '#', text: 'Uncategorized', type: 'folder' });
                
                res.categories.forEach(c => {
                    mdeTreeRaw.push({ id: 'c_' + c.id, parent: '#', text: c.name, type: 'folder' });
                });
                
                res.docs.forEach(d => {
                    const catId = d.category_id ? 'c_' + d.category_id : 'c_0';
                    mdeTreeRaw.push({ id: 'd_' + d.id, parent: catId, text: d.name, type: 'node', doc_id: d.id });
                });
                
                mdeTreeChecked = new Set(mdeTreeRaw.map(n => n.id));
                mdeRenderTree(null);
            })
            .catch(() => { wrap.innerHTML = '<div class="mde-picker-loading">Error loading tree.</div>'; });
    }

    function mdeRenderTree(hits = null) {
        const wrap = document.getElementById('mde-picker-tree-wrap');
        const childMap = {};
        mdeTreeRaw.forEach(n => {
            if (!childMap[n.parent]) childMap[n.parent] = [];
            childMap[n.parent].push(n);
        });
        wrap.innerHTML = mdeBuildLevel('#', childMap, 0, hits);
        mdeUpdatePickerCount();
    }

    function mdeGetDescendantDocs(folderId, childMap) {
        const docs = [];
        const children = childMap[folderId] || [];
        children.forEach(n => {
            if (n.type === 'node') docs.push(n.doc_id);
            if (n.type === 'folder') docs.push(...mdeGetDescendantDocs(n.id, childMap));
        });
        return docs;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function mdeBuildLevel(parentId, childMap, depth, hits) {
        const children = childMap[parentId] || [];
        if (!children.length) return '';
        const indent = depth * 14;
        let html = '';
        
        children.forEach(node => {
            const isFolder = node.type === 'folder';
            const jsId = node.id;
            const checked = mdeTreeChecked.has(jsId);
            const hasKids = !!(childMap[jsId] && childMap[jsId].length);
            const icon = isFolder ? '<i class="bi bi-folder-fill" style="color:var(--orange);"></i>' : '<i class="bi bi-file-earmark-text" style="color:var(--text-muted);"></i>';

            let isVisible = true;
            let labelHtml = escapeHtml(node.text);
            let excerptHtml = '';

            if (hits !== null) {
                if (isFolder) {
                    const descendantDocs = mdeGetDescendantDocs(jsId, childMap);
                    isVisible = descendantDocs.some(dId => hits[dId]);
                } else {
                    if (hits[node.doc_id]) {
                        isVisible = true;
                        labelHtml = hits[node.doc_id].name_hl;
                        if (hits[node.doc_id].excerpt) {
                            excerptHtml = `<div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px; margin-bottom:8px; margin-left:26px; padding-left:10px; border-left:2px solid var(--accent); white-space: normal;">${hits[node.doc_id].excerpt}</div>`;
                        }
                    } else {
                        isVisible = false;
                    }
                }
            }

            if (!isVisible) return;

            const isOpenClass = (isFolder && hasKids && hits !== null) ? 'open' : '';
            const toggleBtn = (isFolder && hasKids)
                ? `<span class="mde-node-toggle ${isOpenClass}" onclick="mdePickerToggleFolder('${jsId}', this)"><i class="bi bi-chevron-right"></i></span>`
                : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;

            html += `
            <div style="display:flex; flex-direction:column;">
                <div class="mde-tree-node ${isFolder ? 'is-folder' : 'is-node'}"
                     style="padding-left:${10 + indent}px;"
                     data-jid="${jsId}">
                    ${toggleBtn}
                    <input type="checkbox" ${checked ? 'checked' : ''} onchange="mdePickerCheck('${jsId}', this.checked)">
                    <span class="mde-node-icon">${icon}</span>
                    <span class="mde-node-label">${labelHtml}</span>
                </div>
                ${excerptHtml ? `<div style="padding-left:${10 + indent + 30}px;">${excerptHtml}</div>` : ''}
            </div>`;

            if (hasKids) {
                html += `<div class="mde-tree-children ${isOpenClass}" id="mde-kids-${jsId}">`;
                html += mdeBuildLevel(jsId, childMap, depth + 1, hits);
                html += `</div>`;
            }
        });
        return html;
    }

    function mdeDebounceSearch() {
        clearTimeout(mdeSearchTimer);
        mdeSearchTimer = setTimeout(mdeSearch, 300);
    }

    function mdeSearch() {
        const q = document.getElementById('mdeSearchInput').value.trim();
        if (q === '') {
            mdeRenderTree(null);
            return;
        }
        
        document.getElementById('mdeSearchSpinner').style.display = 'inline-block';
        
        fetch('api_md.php?action=export_search&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(res => {
                document.getElementById('mdeSearchSpinner').style.display = 'none';
                if (res.status === 'success') {
                    const hitMap = {};
                    res.hits.forEach(h => hitMap[h.id] = h);
                    mdeRenderTree(hitMap);
                }
            });
    }

    function mdePickerToggleFolder(jsId, btn) {
        const kids = document.getElementById('mde-kids-' + jsId);
        if (!kids) return;
        kids.classList.toggle('open');
        btn.classList.toggle('open');
    }

    function mdePickerCheck(jsId, checked) {
        const ids = mdePickerDescendants(jsId);
        ids.forEach(id => {
            if (checked) mdeTreeChecked.add(id);
            else mdeTreeChecked.delete(id);
        });
        ids.forEach(id => {
            const el = document.querySelector(`.mde-tree-node[data-jid="${id}"] input[type=checkbox]`);
            if (el) { el.checked = checked; el.indeterminate = false; }
        });
        mdePickerSyncAncestors(jsId);
        mdeUpdatePickerCount();
    }

    function mdePickerDescendants(jsId) {
        const result = [jsId];
        const queue = [jsId];
        while (queue.length) {
            const cur = queue.shift();
            mdeTreeRaw.filter(n => n.parent === cur).forEach(n => {
                result.push(n.id);
                queue.push(n.id);
            });
        }
        return result;
    }

    function mdePickerSyncAncestors(jsId) {
        const node = mdeTreeRaw.find(n => n.id === jsId);
        if (!node || !node.parent || node.parent === '#') return;
        const parentJid = node.parent;
        const siblings = mdeTreeRaw.filter(n => n.parent === parentJid);
        const allChecked = siblings.every(s => mdeTreeChecked.has(s.id));
        const noneChecked = siblings.every(s => !mdeTreeChecked.has(s.id));
        const el = document.querySelector(`.mde-tree-node[data-jid="${parentJid}"] input[type=checkbox]`);
        if (el) {
            if (allChecked) {
                el.checked = true; el.indeterminate = false;
                mdeTreeChecked.add(parentJid);
            } else if (noneChecked) {
                el.checked = false; el.indeterminate = false;
                mdeTreeChecked.delete(parentJid);
            } else {
                el.checked = false; el.indeterminate = true;
                mdeTreeChecked.delete(parentJid);
            }
        }
        mdePickerSyncAncestors(parentJid);
    }

    function mdePickerToggleAll() {
        const allChecked = mdeTreeRaw.every(n => mdeTreeChecked.has(n.id));
        if (allChecked) {
            mdeTreeChecked.clear();
        } else {
            mdeTreeRaw.forEach(n => mdeTreeChecked.add(n.id));
        }
        const q = document.getElementById('mdeSearchInput').value.trim();
        if (q === '') { mdeRenderTree(null); } else { mdeSearch(); }
        mdeUpdatePickerCount();
    }

    function mdeUpdatePickerCount() {
        const nodeCount = mdeTreeRaw.filter(n => n.type === 'node' && mdeTreeChecked.has(n.id)).length;
        const total = mdeTreeRaw.filter(n => n.type === 'node').length;
        const el = document.getElementById('mde-picker-count');
        if (el) el.textContent = `${nodeCount} of ${total} documents selected`;
    }

    function mdeDoExport() {
        const withMeta = document.getElementById('mde-with-meta').checked;
        const btn = document.getElementById('mde-export-btn');
        
        const selectedDocs = mdeTreeRaw.filter(n => n.type === 'node' && mdeTreeChecked.has(n.id)).map(n => n.doc_id);
        
        if (selectedDocs.length === 0) {
            alert('No documents selected.'); return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Building…';
        
        fetch('api_md.php?action=export_docs', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ doc_ids: selectedDocs, with_meta: withMeta })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                const blob = new Blob([JSON.stringify(res.snapshot, null, 2)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; 
                a.download = `md_export_${new Date().toISOString().slice(0, 10)}.json`;
                a.click();
                URL.revokeObjectURL(url);
                mdeCloseModal();
            } else {
                alert('Export failed: ' + res.message);
            }
        })
        .catch(err => alert('Network error during export: ' + err.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-download"></i> Export';
        });
    }

    document.getElementById('mde-modal-bg').addEventListener('click', function(e) {
        if (e.target === this) mdeCloseModal();
    });
</script>
<!-- ===== END MD EXPORT TREE MODAL ===== -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchHTML = `
            <div style="margin-bottom: 14px;">
                <input id="catSearch" class="input-control" placeholder="Search categories..." oninput="filterCategories()">
            </div>
        `;
        
        const catsContainer = document.getElementById('catsContainer');
        catsContainer.insertAdjacentHTML('afterbegin', searchHTML);

        document.querySelectorAll('a[href^="edit_category.php"], a.btn-create:not(#btnExportMd)').forEach(a => {
            a.addEventListener('click', function(ev) {
                ev.preventDefault();
                const href = a.getAttribute('href');
                const url = new URL(href, window.location.href);
                const id = url.searchParams.get('id');
                if (id) openSpwModalForEdit(parseInt(id, 10));
                else openSpwModalForCreate();
            });
        });

        document.querySelectorAll('.icon-btn[href^="edit_category.php"]').forEach(a => {
            a.addEventListener('click', function(ev){
                ev.preventDefault();
                const href = a.getAttribute('href');
                const url = new URL(href, window.location.href);
                const id = url.searchParams.get('id');
                if (id) openSpwModalForEdit(parseInt(id, 10));
            });
        });

        document.getElementById('spwModalBackdrop').addEventListener('click', function(e){
            if (e.target === this) closeSpwModal();
        });

        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') closeSpwModal();
        });
    });

    function filterCategories() {
        const searchInput = document.getElementById('catSearch');
        const searchTerm = searchInput.value.toLowerCase().trim();
        
        document.querySelectorAll('.cat-item').forEach(item => {
            const nameSpan = item.querySelector('.cat-link span:nth-child(2)');
            const catName = nameSpan ? nameSpan.textContent.toLowerCase() : '';
            
            if (catName.includes(searchTerm)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    let spwEditingId = null;
    function openSpwModalForCreate() {
        spwEditingId = null;
        document.getElementById('spwModalTitle').innerHTML = '<i class="bi bi-folder-plus" style="color:var(--accent);"></i> New Category';
        document.getElementById('spwCategoryName').value = '';
        document.getElementById('spwModalError').style.display = 'none';
        showSpwModal();
    }

    function openSpwModalForEdit(id) {
        spwEditingId = id;
        document.getElementById('spwModalTitle').innerHTML = '<i class="bi bi-pencil-square" style="color:var(--accent);"></i> Edit Category';
        document.getElementById('spwCategoryName').value = '';
        document.getElementById('spwModalError').style.display = 'none';
        fetch(window.location.pathname + '?action=get_category&id=' + encodeURIComponent(id), {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.category) {
                document.getElementById('spwCategoryName').value = res.category.name || '';
                showSpwModal();
            } else {
                alert('Failed to load category: ' + (res.message || 'unknown error'));
            }
        })
        .catch(err => {
            console.error('Get category error', err);
            alert('Network error while loading category');
        });
    }

    function showSpwModal() {
        const b = document.getElementById('spwModalBackdrop');
        b.style.display = 'flex';
        b.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('spwCategoryName').focus(), 100);
    }
    function closeSpwModal() {
        const b = document.getElementById('spwModalBackdrop');
        b.style.display = 'none';
        b.setAttribute('aria-hidden', 'true');
    }

    function saveCategory() {
        const name = document.getElementById('spwCategoryName').value.trim();
        const errorEl = document.getElementById('spwModalError');
        if (!name) {
            errorEl.textContent = 'Name is required';
            errorEl.style.display = 'block';
            return;
        }
        errorEl.style.display = 'none';
        const payload = { name: name };
        if (spwEditingId) payload.id = spwEditingId;

        const saveBtn = document.getElementById('spwSaveBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

        fetch(window.location.pathname + '?action=save_category', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-save2"></i> Save';
            if (res.status === 'success') {
                closeSpwModal();

                const toast = document.createElement('div');
                toast.style.cssText = 'position:fixed; bottom:24px; right:24px; padding:10px 16px; background:var(--card); color:var(--text); border:1px solid var(--border); border-left:4px solid var(--green); border-radius:6px; box-shadow:var(--card-elevation); z-index:10000; font-size:0.88rem;';
                toast.textContent = res.message || 'Saved';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2500);

                setTimeout(() => location.reload(), 600);
            } else {
                errorEl.textContent = res.message || 'Save failed';
                errorEl.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Save error', err);
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-save2"></i> Save';
            alert('Network error. Please try again.');
        });
    }

    function deleteCategory(id) {
        if(!confirm('Are you sure you want to delete this category?\n\nNote: Documents in this category will become uncategorized.')) return;
        
        fetch(window.location.pathname + '?action=delete_category', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({id: id})
        })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                const row = document.getElementById('cat-row-' + id);
                if(row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => row.remove(), 400);
                    
                    const alert = document.createElement('div');
                    alert.style.cssText = 'position:fixed; bottom:24px; right:24px; padding:10px 16px; background:var(--card); color:var(--text); border:1px solid var(--border); border-left:4px solid var(--green); border-radius:6px; box-shadow:var(--card-elevation); z-index:10000; font-size:0.88rem;';
                    alert.textContent = 'Category deleted successfully!';
                    document.body.appendChild(alert);
                    setTimeout(() => alert.remove(), 3000);
                } else {
                    setTimeout(() => location.reload(), 300);
                }
            } else {
                alert('Error: ' + (res.message || 'Failed to delete category'));
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            alert('Network error. Please try again.');
        });
    }
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);