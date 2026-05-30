<?php
// public/view_json_categories.php
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
            $stmt = $pdo->prepare("SELECT id, name FROM json_categories WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($cat ? ['status' => 'success', 'category' => $cat] : ['status' => 'error', 'message' => 'Not found']);
            exit;
        }

        if ($action === 'save_category') {
            $data = json_decode(file_get_contents('php://input'), true);
            $name = trim($data['name'] ?? '');
            $id = isset($data['id']) ? (int)$data['id'] : null;

            if ($name === '') { echo json_encode(['status' => 'error', 'message' => 'Name required']); exit; }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE json_categories SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
                echo json_encode(['status' => 'success', 'message' => 'Updated']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO json_categories (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
                echo json_encode(['status' => 'success', 'message' => 'Created', 'id' => $pdo->lastInsertId()]);
            }
            exit;
        }

        if ($action === 'delete_category') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE json_files SET category_id = NULL WHERE category_id = ?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM json_categories WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            
            echo json_encode(['status' => 'success']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// VIEW MODE
// ==========================================

$cats = $pdo->query("SELECT id, name FROM json_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$countsQuery = $pdo->query("SELECT category_id, COUNT(*) as count FROM json_files WHERE is_active = 1 GROUP BY category_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$uncatCount = $pdo->query("SELECT COUNT(*) FROM json_files WHERE category_id IS NULL AND is_active = 1")->fetchColumn();

$pageTitle = "JSON Categories";
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

<style>
    :root[data-theme="dark"] {
        --bg: #0d1117;
        --card: #161b22;
        --border: #30363d;
        --text: #c9d1d9;
        --text-muted: #8b949e;
        --accent: #58a6ff;
        --green: #238636;
        --blue-light-bg: rgba(56, 139, 253, 0.15);
        --blue-light-border: rgba(59,130,246,0.3);
    }
    :root[data-theme="light"] {
        --bg: #ffffff;
        --card: #f6f8fa;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --green: #2da44e;
        --blue-light-bg: rgba(84, 174, 255, 0.2);
        --blue-light-border: transparent;
    }

    body { background-color: var(--bg) !important; color: var(--text) !important; padding: 20px; transition: background-color 0.2s, color 0.2s; }
    .container { max-width: 800px; margin: 0 auto; }
    
    .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
    
    .btn-create { background: var(--green); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .btn-create:hover { filter: brightness(0.9); }

    .cat-list { list-style: none; padding: 0; }
    .cat-item { background: var(--card); margin-bottom: 8px; padding: 12px 15px; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .cat-link { font-size: 16px; font-weight: 600; color: var(--accent); text-decoration: none; flex: 1; display: flex; align-items: center; gap: 8px; }
    .cat-link:hover { text-decoration: underline; }
    
    .meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 6px; align-items: center; }
    .doc-count { background: var(--blue-light-bg); color: var(--accent); padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; border: 1px solid var(--blue-light-border); }
    
    .icon-btn { text-decoration: none; font-size: 16px; border: none; background: none; cursor: pointer; padding: 0 2px; filter: grayscale(100%); transition: all 0.2s; color: var(--text); }
    .icon-btn:hover { filter: grayscale(0%); transform: scale(1.1); }
    
    /* Modal */
    .spw-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 9999; }
    .spw-modal { background: var(--card); border: 1px solid var(--border); border-radius: 10px; width: 400px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .spw-modal input { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); box-sizing: border-box; }
    .spw-modal .actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 15px; }
    .spw-btn { padding: 8px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; }
    .spw-btn.primary { background: var(--accent); color: white; }
    .spw-btn.secondary { background: transparent; border: 1px solid var(--border); color: var(--text); }
</style>

<div class="container">
    <div class="header-row">
        <h2 style="margin:0;">JSON Categories</h2>
        <button onclick="openModal()" class="btn-create" style="border:none; cursor:pointer;"><span>+</span> New Category</button>
    </div>

    <a href="view_json.php" style="display:inline-block; margin-bottom:20px; color:var(--text-muted); text-decoration:none;">&larr; Back to JSON Index</a>

    <ul class="cat-list">
        <?php if($uncatCount > 0): ?>
        <li class="cat-item">
            <a href="view_json.php?category_id=0" class="cat-link"><span>📁</span><span>Uncategorized</span></a>
            <div class="meta"><span class="doc-count"><?= $uncatCount ?></span></div>
        </li>
        <?php endif; ?>

        <?php foreach($cats as $cat): $cnt = $countsQuery[$cat['id']] ?? 0; ?>
            <li class="cat-item" id="cat-row-<?= $cat['id'] ?>">
                <a href="view_json.php?category_id=<?= $cat['id'] ?>" class="cat-link">
                    <span>📁</span><span><?= htmlspecialchars($cat['name']) ?></span>
                </a>
                <div class="meta">
                    <?php if($cnt > 0): ?><span class="doc-count"><?= $cnt ?></span><?php endif; ?>
                    <button onclick="openModal(<?= $cat['id'] ?>)" class="icon-btn" title="Edit">✏️</button>
                    <button onclick="deleteCategory(<?= $cat['id'] ?>)" class="icon-btn" title="Delete">🗑️</button>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- Modal -->
<div id="spwModalBackdrop" class="spw-modal-backdrop">
    <div class="spw-modal">
        <h3 id="modalTitle" style="margin-top:0;">New Category</h3>
        <input type="text" id="catName" placeholder="Category Name">
        <div class="actions">
            <button class="spw-btn secondary" onclick="closeModal()">Cancel</button>
            <button class="spw-btn primary" onclick="saveCategory()">Save</button>
        </div>
    </div>
</div>

<script>
    let editId = null;
    const modal = document.getElementById('spwModalBackdrop');
    const input = document.getElementById('catName');

    function openModal(id = null) {
        editId = id;
        document.getElementById('modalTitle').textContent = id ? 'Edit Category' : 'New Category';
        input.value = '';
        if(id) {
            fetch(`?action=get_category&id=${id}`).then(r=>r.json()).then(res => {
                if(res.status === 'success') input.value = res.category.name;
            });
        }
        modal.style.display = 'flex';
        setTimeout(() => input.focus(), 50);
    }

    function closeModal() { modal.style.display = 'none'; }
    modal.addEventListener('click', e => { if(e.target === modal) closeModal(); });

    function saveCategory() {
        const name = input.value.trim();
        if(!name) return alert('Name required');
        
        fetch('?action=save_category', {
            method: 'POST',
            body: JSON.stringify({ id: editId, name: name })
        }).then(r=>r.json()).then(res => {
            if(res.status === 'success') location.reload();
            else alert(res.message);
        });
    }

    function deleteCategory(id) {
        if(!confirm('Delete this category? Files will become uncategorized.')) return;
        fetch('?action=delete_category', {
            method: 'POST',
            body: JSON.stringify({ id: id })
        }).then(r=>r.json()).then(res => {
            if(res.status === 'success') document.getElementById('cat-row-'+id).remove();
        });
    }
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);