<?php
// public/rapid_config.php - Configuration for Rapid Showcase
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$em = $spw->getEntityManager();
$conn = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) { die('Not authenticated'); }

// --- AJAX ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
header('Content-Type: application/json');

// 1. Update Category Configuration
if ($_POST['action'] === 'update_category') {
$category = $_POST['category'] ?? '';
$configId = $_POST['config_id'] ?? '';
$doReset = !empty($_POST['reset_status']) && $_POST['reset_status'] === 'true';
$isArchived = isset($_POST['is_archived']) ? ($_POST['is_archived'] === 'true' ? 1 : 0) : 0;

if (empty($category) || empty($configId)) {
echo json_encode(['ok' => false, 'error' => 'Missing category or config ID']);
exit;
}

try {
$sql = "UPDATE rapid_showcase SET generator_config_id = ?, is_archived = ?";
if ($doReset) $sql .= ", is_generated = 0";
$sql .= " WHERE category = ?";

$stmt = $conn->prepare($sql);
$stmt->bindValue(1, $configId);
$stmt->bindValue(2, $isArchived);
$stmt->bindValue(3, $category);
$result = $stmt->executeStatement();

$msg = "Updated " . htmlspecialchars($category);
if ($doReset) $msg .= " (Reset)";
if ($isArchived) $msg .= " (Archived)";

echo json_encode(['ok' => true, 'message' => $msg, 'count' => $result]);
} catch (Exception $e) {
echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
exit;
}

// 2. Fetch Items for Modal
if ($_POST['action'] === 'get_category_items') {
$category = $_POST['category'] ?? '';
try {
$stmt = $conn->prepare("SELECT reference_code, title, description_prompt, is_generated FROM rapid_showcase WHERE category = ? ORDER BY id ASC");
$stmt->bindValue(1, $category);
$items = $stmt->executeQuery()->fetchAllAssociative();
echo json_encode(['ok' => true, 'items' => $items]);
} catch (Exception $e) {
echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
exit;
}
}

// --- FETCH DATA ---
$genStmt = $conn->prepare("SELECT id, config_id, title FROM generator_config WHERE (user_id = ? OR is_public = 1) AND active = 1 ORDER BY title ASC");
$genStmt->bindValue(1, $userId);
$generators = $genStmt->executeQuery()->fetchAllAssociative();

// Fetches category stats AND a sample prompt for the preview pill
$catSql = "
SELECT category, COUNT(*) as total_rows,
SUM(CASE WHEN is_generated = 1 THEN 1 ELSE 0 END) as generated_rows,
(SELECT generator_config_id FROM rapid_showcase r2 WHERE r2.category = r1.category LIMIT 1) as current_config_id,
(SELECT description_prompt FROM rapid_showcase r3 WHERE r3.category = r1.category ORDER BY id ASC LIMIT 1) as sample_prompt,
MAX(is_archived) as is_archived
FROM rapid_showcase r1
GROUP BY category
ORDER BY category ASC
";
$categories = $conn->executeQuery($catSql)->fetchAllAssociative();
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Rapid Config</title>


<script>
(function() {
    try {
        var theme = localStorage.getItem('spw_theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    } catch (e) {}
})();
</script>

<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="css/base.css">

<style>
    .container { max-width: 1100px; margin: 0 auto; padding-top: 20px; }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .header-left { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
    .header h1 { margin: 0; font-size: 1.5rem; color: var(--text); }
    .actions-group { display: flex; gap: 8px; }

    /* Toggle Switch */
    .toggle-switch {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--text);
        user-select: none;
        background: var(--card);
        padding: 6px 12px;
        border-radius: 20px;
        border: 1px solid rgba(128,128,128,0.2);
        transition: all 0.2s;
    }
    .toggle-switch:hover { border-color: var(--accent); }
    .toggle-switch input { margin: 0; width: 16px; height: 16px; accent-color: var(--accent); }

    /* Table Styling */
    .config-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--card);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        border: 1px solid rgba(128,128,128,0.15);
    }
    
    .config-table th {
        background: rgba(128,128,128,0.08);
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        text-align: left;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(128,128,128,0.1);
    }
    
    .config-table td {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(128,128,128,0.1);
        vertical-align: middle;
        color: var(--text);
        font-size: 0.95rem;
    }
    
    .config-table tr:last-child td { border-bottom: none; }
    .config-table tr:hover { background: rgba(var(--accent-rgb), 0.03); }

    /* Visibility Logic */
    tr.row-active { display: table-row; }
    tr.row-archived { display: none; }
    
    /* Show Archived logic */
    body.show-archived tr.row-active { display: none; }
    body.show-archived tr.row-archived { display: table-row; }
    
    /* NEW: Hide 100% Completed Logic */
    /* If 'Hide Completed' is ON, any row marked as completed is hidden, regardless of archive status */
    body.hide-completed tr.row-completed { display: none !important; }
    
    .row-archived { background: rgba(128,128,128,0.05); }

    /* Inputs & Pills */
    select { 
        width: 100%; max-width: 300px; padding: 8px 12px; 
        border-radius: 6px; border: 1px solid rgba(128,128,128,0.3); 
        background: var(--bg); color: var(--text); font-size: 0.9rem;
        cursor: pointer;
    }
    select:focus { outline: none; border-color: var(--accent); }

    .status-pill {
        display: inline-flex; align-items: center; padding: 2px 10px;
        border-radius: 12px; font-size: 0.75rem; font-weight: 600;
        margin-right: 5px; background: rgba(128,128,128,0.15);
        color: var(--text-muted); border: 1px solid transparent;
    }
    
    .pill-clickable {
        cursor: pointer;
        transition: all 0.2s;
        border-color: rgba(var(--accent-rgb), 0.3);
        color: var(--accent);
        background: rgba(var(--accent-rgb), 0.08);
    }
    .pill-clickable:hover {
        background: rgba(var(--accent-rgb), 0.2);
        transform: translateY(-1px);
    }

    .pill-done { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
    
    /* New Context Pill */
    .pill-context {
        cursor: pointer;
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #60a5fa;
        background: rgba(59, 130, 246, 0.08);
        font-family: monospace;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pill-context:hover { background: rgba(59, 130, 246, 0.15); }

    .category-title { font-weight: 600; display: block; margin-bottom: 4px; color: var(--text); }

    .options-cell { display: flex; flex-direction: column; gap: 6px; }
    .checkbox-label {
        display: flex; align-items: center; gap: 8px;
        font-size: 0.85rem; color: var(--text-muted);
        cursor: pointer; user-select: none;
    }
    .checkbox-label input { accent-color: var(--accent); cursor: pointer; width: 14px; height: 14px; }

    /* Item Modal Styles */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); z-index: 2000;
        display: none; align-items: center; justify-content: center;
    }
    .modal-card {
        background: var(--card); width: 90%; max-width: 600px;
        max-height: 80vh; border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        border: 1px solid rgba(128,128,128,0.2);
        display: flex; flex-direction: column;
    }
    .modal-header {
        padding: 16px 20px; border-bottom: 1px solid rgba(128,128,128,0.15);
        display: flex; justify-content: space-between; align-items: center;
    }
    .modal-header h3 { margin: 0; font-size: 1.1rem; }
    .modal-close { cursor: pointer; font-size: 1.5rem; line-height: 1; color: var(--text-muted); }
    .modal-body {
        padding: 0; overflow-y: auto;
    }
    .item-row {
        padding: 12px 20px;
        border-bottom: 1px solid rgba(128,128,128,0.1);
    }
    .item-row:last-child { border-bottom: none; }
    .item-row.generated { background: rgba(34, 197, 94, 0.03); }
    
    .item-top { display: flex; justify-content: space-between; margin-bottom: 4px; }
    .item-ref { font-weight: bold; color: var(--accent); font-size: 0.85rem; }
    .item-title { font-weight: 600; font-size: 0.9rem; margin-left: 8px; color: var(--text); }
    .item-check { color: #22c55e; font-size: 0.9rem; }
    
    .item-desc {
        font-size: 0.85rem; color: var(--text-muted);
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden; line-height: 1.4;
    }
    .context-viewer {
        padding: 20px;
        white-space: pre-wrap;
        font-family: monospace;
        color: var(--text);
        font-size: 0.9rem;
        line-height: 1.5;
    }

    /* Toast */
    .toast {
        position: fixed; top: 20px; right: 20px;
        background: #1f2937; color: white;
        padding: 12px 20px; border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
        z-index: 5000; opacity: 0; transition: opacity 0.3s;
        pointer-events: none; border: 1px solid rgba(255,255,255,0.1);
    }
    .toast.show { opacity: 1; }
    .toast.success { border-left: 4px solid var(--green); }
    .toast.error { border-left: 4px solid var(--red); }

    /* --- MOBILE RESPONSIVE --- */
    @media screen and (max-width: 768px) {
        .container { padding: 10px; }
        .header { flex-direction: column; align-items: flex-start; }
        .header-left { width: 100%; justify-content: space-between; }
        .actions-group { width: 100%; justify-content: space-between; }
        .btn { flex: 1; text-align: center; }

        .config-table, .config-table thead, .config-table tbody, .config-table tr, .config-table td {
            display: block; width: 100%;
        }
        .config-table thead { display: none; }
        
        .config-table tr {
            background: var(--card); margin-bottom: 16px;
            border: 1px solid rgba(128,128,128,0.2);
            border-radius: 10px; padding: 16px;
            display: flex; flex-direction: column; gap: 12px; 
        }

        /* Mobile Visibility Logic */
        tr.row-active { display: flex; }
        tr.row-archived { display: none; }
        body.show-archived tr.row-active { display: none; }
        body.show-archived tr.row-archived { display: flex; }
        
        body.hide-completed tr.row-completed { display: none !important; }

        .config-table td { padding: 0; border: none; text-align: left; width: 100%; display: block; }
        .config-table td::before { display: none; }

        .config-table td[data-label="Category"] {
            border-bottom: 1px solid rgba(128,128,128,0.1);
            padding-bottom: 12px; margin-bottom: 4px;
        }
        .category-title { font-size: 1.1rem; }
        select { max-width: 100%; padding: 12px; }
        .options-cell {
            flex-direction: row; justify-content: space-between;
            background: rgba(128,128,128,0.05); padding: 10px; border-radius: 6px;
        }
        .config-table td[data-label="Action"] { margin-top: 4px; }
        .config-table .btn-sm { width: 100%; display: block; padding: 12px; font-size: 1rem; }
    }
</style>
</head>
<body>
<div class="container">
<div class="header">
<div class="header-left">
<h1>⚙️ Rapid Config</h1>
<label class="toggle-switch">
<input type="checkbox" id="showArchivedToggle" onchange="toggleView()">
<span>Show Archived</span>
</label>
<label class="toggle-switch">
<input type="checkbox" id="hideCompletedToggle" onchange="toggleView()">
<span>Hide 100% Done</span>
</label>
</div>
<div class="actions-group">
<a href="rapid_import.php" class="btn btn-secondary">Importer</a>
<a href="rapid_scenes.php" class="btn btn-primary">Generator</a>
</div>
</div>


<?php if (empty($categories)): ?>
        <div style="background:var(--card); padding:40px; text-align:center; border-radius:8px; border:1px solid rgba(128,128,128,0.2);">
            <p style="color:var(--text-muted);">No categories found in <code>rapid_showcase</code> table.</p>
            <a href="rapid_import.php" class="btn btn-sm btn-primary">Import Content</a>
        </div>
    <?php else: ?>
        <table class="config-table">
            <thead>
                <tr>
                    <th width="30%">Category / Context</th>
                    <th width="35%">Default Generator</th>
                    <th width="20%">Options</th>
                    <th width="15%" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): 
                    $catHash = md5($cat['category']); 
                    $percent = $cat['total_rows'] > 0 ? round(($cat['generated_rows'] / $cat['total_rows']) * 100) : 0;
                    $isArchived = (bool)$cat['is_archived'];
                    $isComplete = ($percent == 100);
                    
                    $rowClass = $isArchived ? 'row-archived' : 'row-active';
                    if ($isComplete) $rowClass .= ' row-completed';
                    
                    // Handle the sample text for preview
                    $sample = $cat['sample_prompt'] ?? '';
                    $safeSample = htmlspecialchars(json_encode($sample), ENT_QUOTES, 'UTF-8');
                    $shortSample = mb_strimwidth($sample, 0, 60, "...");
                ?>
                    <tr class="<?= $rowClass ?>" id="row_<?= $catHash ?>">
                        <td data-label="Category">
                            <span class="category-title"><?= htmlspecialchars($cat['category']) ?></span>
                            <div style="margin-bottom:6px;">
                                <span class="status-pill pill-clickable" onclick="viewItems('<?= htmlspecialchars(addslashes($cat['category']), ENT_QUOTES) ?>')">
                                    <?= $cat['total_rows'] ?> Items
                                </span>
                                <span class="status-pill pill-done"><?= $percent ?>% Done</span>
                            </div>
                            <?php if($sample): ?>
                                <div>
                                    <span class="status-pill pill-context" onclick='viewContext(<?= $safeSample ?>)'>
                                        📄 <?= htmlspecialchars($shortSample) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Generator">
                            <select id="gen_<?= $catHash ?>">
                                <option value="">-- Manual Selection --</option>
                                <?php foreach ($generators as $gen): ?>
                                    <option value="<?= $gen['config_id'] ?>" 
                                        <?= ($cat['current_config_id'] === $gen['config_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($gen['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="Options">
                            <div class="options-cell">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="reset_<?= $catHash ?>">
                                    <span>Reset Progress</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" id="archive_<?= $catHash ?>" <?= $isArchived ? 'checked' : '' ?>>
                                    <span>Archive</span>
                                </label>
                            </div>
                        </td>
                        <td data-label="Action" style="text-align:right;">
                            <button class="btn btn-sm btn-primary" onclick="updateCategory('<?= htmlspecialchars(addslashes($cat['category']), ENT_QUOTES) ?>', '<?= $catHash ?>')">
                                Save
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Items Modal -->
<div id="itemsModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Items</h3>
            <span class="modal-close" onclick="closeModal()">×</span>
        </div>
        <div id="modalBody" class="modal-body">
            <div style="padding:20px; text-align:center;">Loading...</div>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const showArchived = localStorage.getItem('rapid_show_archived') === 'true';
        const hideCompleted = localStorage.getItem('rapid_hide_completed') === 'true';
        
        document.getElementById('showArchivedToggle').checked = showArchived;
        document.getElementById('hideCompletedToggle').checked = hideCompleted;
        toggleView();
    });

    function toggleView() {
        const showArchived = document.getElementById('showArchivedToggle').checked;
        const hideCompleted = document.getElementById('hideCompletedToggle').checked;
        
        localStorage.setItem('rapid_show_archived', showArchived);
        localStorage.setItem('rapid_hide_completed', hideCompleted);
        
        // Handle Archived Logic
        if (showArchived) {
            document.body.classList.add('show-archived');
        } else {
            document.body.classList.remove('show-archived');
        }
        
        // Handle Completed Logic
        if (hideCompleted) {
            document.body.classList.add('hide-completed');
        } else {
            document.body.classList.remove('hide-completed');
        }
    }

    // --- ITEMS MODAL LOGIC ---
    const modal = document.getElementById('itemsModal');
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');

    function closeModal() { modal.style.display = 'none'; }
    window.onclick = function(event) { if (event.target == modal) closeModal(); }

    // Added Context Viewer Logic
    function viewContext(text) {
        modalTitle.textContent = "Context Preview";
        modalBody.innerHTML = `<div class="context-viewer">${escapeHtml(text)}</div>`;
        modal.style.display = 'flex';
    }

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    async function viewItems(category) {
        modalTitle.textContent = category;
        modalBody.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">Loading items...</div>';
        modal.style.display = 'flex';

        const formData = new FormData();
        formData.append('action', 'get_category_items');
        formData.append('category', category);

        try {
            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.ok && data.items.length > 0) {
                let html = '';
                data.items.forEach(item => {
                    const check = item.is_generated == 1 ? '<span class="item-check">✓</span>' : '';
                    const rowClass = item.is_generated == 1 ? 'item-row generated' : 'item-row';
                    
                    html += `
                    <div class="${rowClass}">
                        <div class="item-top">
                            <div>
                                <span class="item-ref">${item.reference_code}</span>
                                <span class="item-title">${item.title}</span>
                            </div>
                            ${check}
                        </div>
                        <div class="item-desc">${item.description_prompt}</div>
                    </div>`;
                });
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = '<div style="padding:20px; text-align:center;">No items found.</div>';
            }
        } catch (e) {
            modalBody.innerHTML = '<div style="padding:20px; text-align:center; color:var(--red);">Error loading items.</div>';
        }
    }

    // --- SAVE ACTIONS ---
    async function updateCategory(categoryName, hash) {
        const select = document.getElementById('gen_' + hash);
        const resetChk = document.getElementById('reset_' + hash);
        const archiveChk = document.getElementById('archive_' + hash);
        const btn = select.closest('tr').querySelector('button'); 
        const row = document.getElementById('row_' + hash);

        const configId = select.value;
        const doReset = resetChk.checked;
        const doArchive = archiveChk.checked;

        if (!configId) {
            showToast("Please select a generator first.", "error");
            return;
        }

        const originalText = btn.textContent;
        btn.textContent = "Saving...";
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'update_category');
            formData.append('category', categoryName);
            formData.append('config_id', configId);
            formData.append('reset_status', doReset);
            formData.append('is_archived', doArchive);

            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.ok) {
                showToast(data.message, "success");
                resetChk.checked = false; 
                
                // Handle Visibility toggle based on Archive status
                // First remove both base classes to be safe
                row.classList.remove('row-active', 'row-archived');
                
                if (doArchive) {
                    row.classList.add('row-archived');
                } else {
                    row.classList.add('row-active');
                }

                if (doReset) setTimeout(() => location.reload(), 1000);
            } else {
                showToast("Error: " + data.error, "error");
            }
        } catch (e) {
            showToast("Request failed: " + e.message, "error");
        } finally {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }

    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = `toast show ${type}`;
        setTimeout(() => t.className = 'toast', 3000);
    }
</script>
<?php echo $eruda; ?>

</body>
</html>