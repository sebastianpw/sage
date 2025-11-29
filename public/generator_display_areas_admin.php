<?php
// public/generator_display_areas_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die('Not authenticated');
}

$pageTitle = "Display Area Admin";
ob_start();
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">

<style>
/* Re-using styles from generator_admin for consistency */
.admin-wrap { max-width: 800px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; color: var(--text); }
.list-container { background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.08); border-radius: 8px; padding: 12px; box-shadow: var(--card-elevation); }
.list-item { background: var(--bg); border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 8px; padding: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 16px; }
.list-item:last-child { margin-bottom: 0; }
.list-info { flex: 1; }
.list-actions { display: flex; gap: 8px; }
.empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }

/* Modal styles - copied from generator_admin */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 500px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(2,6,23,0.35); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.modal-body { padding: 20px; }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); background: var(--bg); display: flex; justify-content: flex-end; gap: 8px; }

/* Form styles - copied from generator_admin */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-input { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); }
.form-help-text { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }
</style>

<div class="admin-wrap">
    <div class="admin-head">
        <h2>Display Area Admin</h2>
        <button class="btn btn-sm btn-primary" onclick="openCreateModal()">+ New Area</button>
    </div>

    <div class="list-container" id="displayAreaList">
        <div class="empty-state">Loading...</div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="formModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Create Display Area</h3>
            <button class="btn btn-sm" onclick="closeFormModal()">Close</button>
        </div>
        <div class="modal-body">
            <form id="displayAreaForm">
                <input type="hidden" id="areaId" name="id">
                <div class="form-group">
                    <label class="form-label">Label</label>
                    <input type="text" id="label" name="label" class="form-input" required>
                    <span class="form-help-text">Human-readable name shown in dropdowns (e.g., "Floatool Interface").</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Key</label>
                    <input type="text" id="area_key" name="area_key" class="form-input" required>
                    <span class="form-help-text">Unique identifier stored in the database (e.g., "floatool"). No spaces or special characters.</span>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeFormModal()">Cancel</button>
            <button class="btn btn-sm btn-primary" onclick="saveArea()">Save</button>
        </div>
    </div>
</div>

<script src="js/toast.js"></script>
<script>
const API_URL = '/generator_display_areas_actions.php';
let currentEditId = null;

document.addEventListener('DOMContentLoaded', loadAreas);

async function apiCall(action, data = {}) {
    const response = await fetch(`${API_URL}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data })
    });
    return response.json();
}

async function loadAreas() {
    const container = document.getElementById('displayAreaList');
    try {
        const result = await apiCall('list');
        if (!result.ok) throw new Error(result.error);

        if (result.data.length === 0) {
            container.innerHTML = `<div class="empty-state">No display areas configured yet.</div>`;
            return;
        }

        container.innerHTML = result.data.map(area => `
            <div class="list-item" data-id="${area.id}">
                <div class="list-info">
                    <strong>${escapeHtml(area.label)}</strong><br>
                    <code style="color:var(--text-muted); font-size:0.85rem;">${escapeHtml(area.area_key)}</code>
                </div>
                <div class="list-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(${area.id})">Edit</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteArea(${area.id})">Delete</button>
                </div>
            </div>
        `).join('');
    } catch(e) {
        container.innerHTML = `<div class="empty-state">Error loading display areas: ${e.message}</div>`;
    }
}

function openCreateModal() {
    currentEditId = null;
    document.getElementById('displayAreaForm').reset();
    document.getElementById('modalTitle').textContent = 'Create Display Area';
    document.getElementById('formModal').classList.add('active');
    document.getElementById('label').focus();
}

async function openEditModal(id) {
    currentEditId = id;
    try {
        const result = await apiCall('get', { id });
        if (!result.ok) throw new Error(result.error);
        document.getElementById('areaId').value = result.data.id;
        document.getElementById('label').value = result.data.label;
        document.getElementById('area_key').value = result.data.area_key;
        document.getElementById('modalTitle').textContent = 'Edit Display Area';
        document.getElementById('formModal').classList.add('active');
    } catch(e) {
        Toast.show('Failed to load area: ' + e.message, 'error');
    }
}

function closeFormModal() {
    document.getElementById('formModal').classList.remove('active');
}

async function saveArea() {
    const action = currentEditId ? 'update' : 'create';
    const data = {
        label: document.getElementById('label').value,
        area_key: document.getElementById('area_key').value
    };
    if (currentEditId) data.id = currentEditId;

    try {
        const result = await apiCall(action, data);
        if (result.ok) {
            Toast.show(result.message, 'success');
            closeFormModal();
            loadAreas();
        } else {
            Toast.show(result.error, 'error');
        }
    } catch (e) {
        Toast.show('Failed to save: ' + e.message, 'error');
    }
}

async function deleteArea(id) {
    if (!confirm('Are you sure you want to delete this display area? This cannot be undone.')) return;
    try {
        const result = await apiCall('delete', { id });
        if (result.ok) {
            Toast.show(result.message, 'success');
            loadAreas();
        } else {
            Toast.show(result.error, 'error');
        }
    } catch (e) {
        Toast.show('Failed to delete: ' + e.message, 'error');
    }
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
?>

