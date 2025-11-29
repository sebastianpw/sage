<?php
// public/view_shot_types_admin.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$pageTitle = "Shot Types Admin";
ob_start();

try {
    $stmt = $pdo->query("SELECT * FROM shot_types ORDER BY name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $rows = []; }
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
.admin-wrap { max-width: 800px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; }
.list-container { background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.08); border-radius: 8px; padding: 12px; box-shadow: var(--card-elevation); }
.list-item { background: var(--bg); border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 8px; padding: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 16px; }
.list-item:last-child { margin-bottom: 0; }
.item-info { flex: 1; min-width: 0; }
.item-name { font-weight: 600; color: var(--text); font-size: 1rem; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 500px; background: var(--card); border-radius: 10px; box-shadow: var(--card-elevation); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); }
.modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; }
.modal-body { padding: 20px; }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); display: flex; justify-content: flex-end; gap: 8px; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-input { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); }
@media (max-width: 768px) { .list-item { flex-direction: column; align-items: flex-start; } .actions { width: 100%; margin-top: 12px; } .actions .btn { flex: 1; text-align: center; } }
</style>

<div class="admin-wrap">
    <div class="admin-head">
        <h2>Manage Shot Types</h2>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="view_sketch_templates_admin.php">&larr; Back to Templates</a>
            <button class="btn btn-sm btn-primary" onclick="openCreateModal()">+ New Shot Type</button>
        </div>
    </div>
    <div class="list-container">
        <?php foreach ($rows as $row): ?>
            <div class="list-item" data-id="<?= (int)$row['id'] ?>">
                <div class="item-info">
                    <span class="item-name"><?= htmlspecialchars($row['name']) ?></span>
                </div>
                <div class="actions">
                    <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= (int)$row['id'] ?>)">Edit</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?= (int)$row['id'] ?>)">Delete</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="formModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Create Shot Type</h3>
            <button class="btn btn-sm" onclick="closeFormModal()">Close</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="itemId" name="id">
            <div>
                <label class="form-label">Shot Type Name</label>
                <input type="text" id="name" name="name" class="form-input" required>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeFormModal()">Cancel</button>
            <button class="btn btn-sm btn-primary" onclick="saveItem()">Save Shot Type</button>
        </div>
    </div>
</div>

<script src="js/toast.js"></script>
<script>
const API_URL = '/shot_types_api.php';
const ENTITY_NAME = 'Shot Type';
async function apiCall(action, data = {}) { const response = await fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }); return response.json(); }
function showToast(message, type = 'info') { if (typeof Toast !== 'undefined' && Toast.show) { Toast.show(message, type); } else { console.log(`[${type}] ${message}`); } }
function openCreateModal() { document.getElementById('modalTitle').textContent = `Create ${ENTITY_NAME}`; document.getElementById('itemId').value = ''; document.getElementById('name').value = ''; document.getElementById('formModal').classList.add('active'); }
async function openEditModal(id) { const result = await apiCall('load', { id }); if (result.status === 'ok' && result.data) { document.getElementById('modalTitle').textContent = `Edit ${ENTITY_NAME}`; document.getElementById('itemId').value = result.data.id; document.getElementById('name').value = result.data.name; document.getElementById('formModal').classList.add('active'); } else { showToast(`Failed to load ${ENTITY_NAME.toLowerCase()}`, 'error'); } }
function closeFormModal() { document.getElementById('formModal').classList.remove('active'); }
async function saveItem() { const data = { id: document.getElementById('itemId').value, name: document.getElementById('name').value }; const result = await apiCall('save', data); if (result.status === 'ok') { showToast(`${ENTITY_NAME} saved`, 'success'); location.reload(); } else { showToast(result.message || `Failed to save ${ENTITY_NAME.toLowerCase()}`, 'error'); } }
async function deleteItem(id) { if (!confirm(`Are you sure you want to delete this ${ENTITY_NAME.toLowerCase()}?`)) return; const result = await apiCall('delete', { id }); if (result.status === 'ok') { showToast(`${ENTITY_NAME} deleted`, 'success'); location.reload(); } else { showToast(result.message || `Failed to delete ${ENTITY_NAME.toLowerCase()}`, 'error'); } }
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>

