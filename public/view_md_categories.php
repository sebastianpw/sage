<?php
// public/view_md_categories.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; 

$spw = \App\Core\SpwBase::getInstance();

// ----------------------
// Inline API handler
// ----------------------
// Handles AJAX calls for get_category, save_category and delete_category.
// Returns JSON and exits early.
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
            // Expect JSON body for POST or form-encoded data
            $data = null;
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $data = $decoded;
            }
            // fallback to POST
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
                // update
                $stmt = $pdo->prepare("UPDATE documentation_categories SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
                echo json_encode(['status' => 'success', 'message' => 'Category updated', 'id' => $id]);
            } else {
                // insert
                $stmt = $pdo->prepare("INSERT INTO documentation_categories (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
                $newId = (int)$pdo->lastInsertId();
                echo json_encode(['status' => 'success', 'message' => 'Category created', 'id' => $newId]);
            }
            exit;
        }

        if ($action === 'delete_category') {
            // Expect JSON body for POST or form-encoded data
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

            // Perform in-transaction: set documents to uncategorized, then delete category
            try {
                $pdo->beginTransaction();

                // Set documents to uncategorized
                $upd = $pdo->prepare("UPDATE documentations SET category_id = NULL WHERE category_id = :id");
                $upd->execute([':id' => $id]);

                // Delete category
                $del = $pdo->prepare("DELETE FROM documentation_categories WHERE id = :id");
                $del->execute([':id' => $id]);

                $affected = $del->rowCount();

                $pdo->commit();

                if ($affected > 0) {
                    echo json_encode(['status' => 'success', 'message' => 'Category deleted']);
                } else {
                    // If no row deleted, category likely didn't exist
                    echo json_encode(['status' => 'error', 'message' => 'Category not found']);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }

        // unknown action
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

// Count documents per category
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

// Add uncategorized count
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

<style>
    /* CSS PATCH for Dark Mode override (Categories View) */
    :root {
        --bg: #ffffff;
        --card: #f6f8fa;
        --border: #d0d7de;
        --text: #24292f;
        --text-muted: #57606a;
        --accent: #0969da;
        --blue-light-bg: rgba(84, 174, 255, 0.2);
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
        --blue-light-bg: rgba(56, 139, 253, 0.15);
        --blue-light-text: #79c0ff;
        --blue-light-border: rgba(59,130,246,0.3);
        --card-elevation: 0 6px 18px rgba(2,6,23,0.4);
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
            --blue-light-bg: rgba(56, 139, 253, 0.15);
            --blue-light-text: #79c0ff;
            --blue-light-border: rgba(59,130,246,0.3);
            --card-elevation: 0 6px 18px rgba(2,6,23,0.4);
        }
    }

    body { 
        background-color: var(--bg) !important; 
        color: var(--text) !important; 
        padding: 20px; 
        transition: background-color 0.2s, color 0.2s; 
    }
    
    .container { 
        max-width: 800px; 
        margin: 0 auto; 
    }
    
    .header-row { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 20px; 
        border-bottom: 1px solid var(--border); 
        padding-bottom: 10px; 
    }
    
    .btn-create { 
        background: #238636 !important; 
        color: #ffffff !important; 
        padding: 6px 12px; 
        border-radius: 6px; 
        text-decoration: none; 
        font-weight: 600; 
        display: inline-flex; 
        align-items: center; 
        gap: 5px;
        border: 1px solid rgba(255,255,255,0.1);
    }
    .btn-create:hover { 
        filter: brightness(0.9); 
    }

    .cat-list { 
        list-style: none; 
        padding: 0; 
    }
    
    .cat-item { 
        background: var(--card); 
        margin-bottom: 8px; 
        padding: 12px 15px; 
        border-radius: 8px; 
        border: 1px solid var(--border); 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        transition: border-color 0.2s, transform 0.1s; 
    }
    .cat-item:hover { 
        border-color: var(--text-muted); 
        transform: translateY(-1px); 
    }
    
    .cat-link { 
        font-size: 16px; 
        font-weight: 600; 
        color: var(--accent); 
        text-decoration: none; 
        flex: 1; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        margin-right: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .cat-link:hover { 
        text-decoration: underline; 
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
        padding: 2px 6px; 
        border-radius: 10px; 
        font-size: 11px; 
        font-weight: 600; 
        border: 1px solid var(--blue-light-border);
    }
    
    .icon-btn { 
        text-decoration: none; 
        font-size: 16px; 
        border: none; 
        background: none; 
        cursor: pointer; 
        padding: 0 2px; 
        filter: grayscale(100%); 
        transition: all 0.2s; 
        color: var(--text);
    }
    .icon-btn:hover { 
        filter: grayscale(0%); 
        transform: scale(1.1); 
    }
    
    .empty-state { 
        text-align: center; 
        padding: 40px 20px; 
        color: var(--text-muted); 
        font-style: italic; 
    }
    
    .back-link { 
        display: inline-flex; 
        align-items: center; 
        gap: 5px; 
        color: var(--text-muted); 
        text-decoration: none; 
        margin-bottom: 20px; 
        font-size: 14px; 
    }
    .back-link:hover { 
        color: var(--accent); 
    }

    /* Modal styles */
    .spw-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(2,6,23,0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    .spw-modal {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        width: 420px;
        max-width: calc(100% - 32px);
        padding: 18px;
        box-shadow: var(--card-elevation, 0 10px 30px rgba(2,6,23,0.25));
    }
    .spw-modal h3 { margin:0 0 10px 0; }
    .spw-modal .field { margin-bottom: 10px; }
    .spw-modal input[type="text"] {
        width:100%;
        padding:10px;
        border-radius:6px;
        border:1px solid var(--border);
        background: transparent;
        color:var(--text);
        box-sizing: border-box;
    }
    .spw-modal .actions {
        display:flex;
        justify-content:flex-end;
        gap:8px;
        margin-top: 12px;
    }
    .spw-btn {
        padding:8px 12px;
        border-radius:6px;
        border: none;
        cursor:pointer;
        font-weight:600;
    }
    .spw-btn.secondary { background: transparent; color: var(--text-muted); border:1px solid var(--border); }
    .spw-btn.primary { background: #0969da; color:#fff; }
</style>

<div class="container">
    <!-- Header with back button -->
    <div class="header-row">
        <div style="display:flex; align-items:center;">
            <h2 style="margin:0; margin-right: 10px;">Documentation Categories</h2>
        </div>
        <a href="edit_category.php" class="btn-create"><span>+</span> New Category</a>
    </div>

    <!-- Back to Docs link -->
    <a href="view_md.php" class="back-link">
        <span>&larr;</span>
        Back to Documents
    </a>

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
                        <span>📁</span>
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
                    if($docCount >= 0): // Only show categories with documents - nope: show all
                ?>
                    <li class="cat-item" id="cat-row-<?= $cat['id'] ?>">
                        <a href="view_md.php?category_id=<?= $cat['id'] ?>" class="cat-link">
                            <span>📁</span>
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        </a>
                        <div class="meta">
                            <span class="doc-count"><?php echo $docCount; ?> doc<?php echo $docCount != 1 ? 's' : ''; ?></span>
                            <a href="edit_category.php?id=<?= $cat['id'] ?>" class="icon-btn" title="Edit Category">✏️</a>
                            <button onclick="deleteCategory(<?= $cat['id'] ?>)" class="icon-btn" title="Delete Category">🗑️</button>
                        </div>
                    </li>
                <?php endif; endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Modal overlay (kept outside of container) -->
<div id="spwModalBackdrop" class="spw-modal-backdrop" role="dialog" aria-hidden="true">
    <div class="spw-modal" role="document" aria-labelledby="spwModalTitle">
        <h3 id="spwModalTitle">New Category</h3>
        <div class="field">
            <label style="display:block; font-size:13px; margin-bottom:6px; color:var(--text-muted)">Name</label>
            <input type="text" id="spwCategoryName" placeholder="Category name">
        </div>
        <div id="spwModalError" style="display:none; color:var(--red); font-size:13px; margin-bottom:6px;"></div>
        <div class="actions">
            <button class="spw-btn secondary" onclick="closeSpwModal()">Cancel</button>
            <button class="spw-btn primary" id="spwSaveBtn" onclick="saveCategory()">Save</button>
        </div>
    </div>
</div>

<script>
    // Search functionality for categories
    document.addEventListener('DOMContentLoaded', function() {
        // Add search input if you want it (optional)
        const searchHTML = `
            <div style="margin-bottom: 20px;">
                <input id="catSearch" placeholder="Search categories..." 
                       style="width:100%; padding:10px; background:var(--card); 
                              border:1px solid var(--border); color:var(--text); 
                              border-radius:6px; box-sizing:border-box;"
                       oninput="filterCategories()">
            </div>
        `;
        
        const catsContainer = document.getElementById('catsContainer');
        catsContainer.insertAdjacentHTML('afterbegin', searchHTML);

        // Intercept clicks on existing edit links (preserve markup, change behavior client-side)
        // Any anchor pointing to edit_category.php or the New Category button will open the modal instead.
        document.querySelectorAll('a[href^="edit_category.php"], a.btn-create').forEach(a => {
            a.addEventListener('click', function(ev) {
                // If this link has an id query parameter, open edit modal
                ev.preventDefault();
                const href = a.getAttribute('href');
                const url = new URL(href, window.location.href);
                const id = url.searchParams.get('id');
                if (id) openSpwModalForEdit(parseInt(id, 10));
                else openSpwModalForCreate();
            });
        });

        // Intercept clicks on the small edit icon anchors (they also point to edit_category.php?id=...)
        document.querySelectorAll('.icon-btn[href^="edit_category.php"]').forEach(a => {
            a.addEventListener('click', function(ev){
                ev.preventDefault();
                const href = a.getAttribute('href');
                const url = new URL(href, window.location.href);
                const id = url.searchParams.get('id');
                if (id) openSpwModalForEdit(parseInt(id, 10));
            });
        });

        // close modal on backdrop click
        document.getElementById('spwModalBackdrop').addEventListener('click', function(e){
            if (e.target === this) closeSpwModal();
        });

        // escape key to close
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

    // ------------------------
    // Modal functions
    // ------------------------
    let spwEditingId = null;
    function openSpwModalForCreate() {
        spwEditingId = null;
        document.getElementById('spwModalTitle').textContent = 'New Category';
        document.getElementById('spwCategoryName').value = '';
        document.getElementById('spwModalError').style.display = 'none';
        showSpwModal();
    }

    function openSpwModalForEdit(id) {
        spwEditingId = id;
        document.getElementById('spwModalTitle').textContent = 'Edit Category';
        document.getElementById('spwCategoryName').value = '';
        document.getElementById('spwModalError').style.display = 'none';
        // fetch existing data from this same file's API
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
        // focus input
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
        saveBtn.textContent = 'Saving...';

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
            saveBtn.textContent = 'Save';
            if (res.status === 'success') {
                // Close modal and reload to reflect server-side filtering and counts.
                closeSpwModal();

                // Small success toast
                const toast = document.createElement('div');
                toast.style.cssText = 'position:fixed; top:20px; right:20px; padding:12px 16px; background:var(--green); color:#fff; border-radius:6px; z-index:10000;';
                toast.textContent = res.message || 'Saved';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2500);

                // reload page to show updated list (keeps server-side behavior intact)
                setTimeout(() => location.reload(), 600);
            } else {
                errorEl.textContent = res.message || 'Save failed';
                errorEl.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Save error', err);
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
            alert('Network error. Please try again.');
        });
    }

    // deleteCategory now calls the inline API in this file (no external api_md.php)
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
                    
                    // Show success message
                    const alert = document.createElement('div');
                    alert.style.cssText = 'position:fixed; top:20px; right:20px; padding:12px 16px; background:var(--green); color:#fff; border-radius:6px; z-index:10000;';
                    alert.textContent = 'Category deleted successfully!';
                    document.body.appendChild(alert);
                    setTimeout(() => alert.remove(), 3000);
                } else {
                    // If row not present, refresh to reflect change
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
