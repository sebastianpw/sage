<?php
// public/view_interactions_admin.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$pageTitle = "Interactions Admin";
ob_start();

// Fetch all interactions
try {
    $stmt = $pdo->query("SELECT * FROM interactions ORDER BY interaction_group ASC, category ASC, name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $rows = []; }

// Fetch available groups and categories for dropdowns
try {
    $groupStmt = $pdo->query("SELECT DISTINCT interaction_group FROM interactions ORDER BY interaction_group ASC");
    $availableGroups = $groupStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $availableGroups = []; }

try {
    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM interactions WHERE category IS NOT NULL ORDER BY category ASC");
    $availableCategories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $availableCategories = []; }

?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* Core Styles */
.admin-wrap { max-width: 1200px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; }
.admin-head .actions-group { display: flex; gap: 8px; flex-wrap: wrap; }

/* List Container */
.interaction-list-container { background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.08); border-radius: 8px; padding: 12px; box-shadow: var(--card-elevation); }

/* List Items */
.interaction-item { background: var(--bg); border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 8px; padding: 16px; margin-bottom: 12px; transition: all 0.15s ease; display: flex; align-items: center; gap: 16px; }
.interaction-item:last-child { margin-bottom: 0; }
.interaction-item:hover { border-color: var(--accent); }
.interaction-info { flex: 1; min-width: 0; }
.interaction-name { font-weight: 600; font-size: 1rem; color: var(--text); margin-bottom: 4px; }
.interaction-meta { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; }
.interaction-meta span { margin-right: 12px; display: inline-block; }
.interaction-meta .description { display: block; margin-top: 6px; font-style: italic; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-badge.active { background: rgba(35,134,54,0.12); color: var(--green); }
.status-badge.inactive { background: rgba(var(--muted-border-rgb), 0.12); color: var(--text-muted); }

/* Filter Bar Styles */
.filterbox { background-color: var(--card); border-radius: 8px; padding: 12px; border: 1px solid rgba(var(--muted-border-rgb),0.06); margin-bottom: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.filterbox label { font-weight: 600; margin: 0; white-space: nowrap; }
.filterbox select, .filterbox input { padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb),0.12); background: var(--bg); color: var(--text); min-width: 140px; }
.small-muted { color: var(--text-muted); font-size:0.85rem; }

/* Modal and Form styles */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 800px; background: var(--card); border-radius: 10px; box-shadow: var(--card-elevation); display: flex; flex-direction: column; max-height: 90vh; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); }
.modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; }
.modal-body { padding: 20px; overflow-y: auto; }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); display: flex; justify-content: flex-end; gap: 8px; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); }
.form-textarea { min-height: 120px; resize: vertical; }
.form-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }

/* Responsive: stack on mobile */
@media (max-width: 768px) { 
    .interaction-item { flex-direction: column; align-items: flex-start; } 
    .actions { width: 100%; margin-top: 12px; } 
    .actions .btn { flex: 1; text-align: center; } 
}
</style>

<div class="admin-wrap">
    <div class="admin-head">
        <h2 style="margin-left: 45px;">Interactions</h2>
        <div class="actions-group">
            <button class="btn btn-sm btn-primary" onclick="openCreateModal()">+ New Interaction</button>
        </div>
    </div>

    <div class="filterbox">
        <label>Filter:</label>
        <select id="groupFilter">
            <option value="">All Groups</option>
            <?php foreach ($availableGroups as $group): ?>
                <option value="<?= htmlspecialchars($group) ?>"><?= htmlspecialchars($group) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="categoryFilter">
            <option value="">All Categories</option>
            <?php foreach ($availableCategories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="searchFilter" placeholder="Search..." style="min-width:150px; flex:1; max-width:250px;">
        <span class="small-muted" id="filterCount" style="margin-left:auto;"></span>
    </div>

    <div class="interaction-list-container">
        <?php foreach ($rows as $interaction): ?>
            <div class="interaction-item" 
                 data-id="<?= (int)$interaction['id'] ?>" 
                 data-group="<?= htmlspecialchars($interaction['interaction_group']) ?>"
                 data-category="<?= htmlspecialchars($interaction['category'] ?? '') ?>">
                <div class="interaction-info">
                    <div class="interaction-name"><?= htmlspecialchars($interaction['name']) ?></div>
                    <div class="interaction-meta">
                        <span class="status-badge <?= $interaction['active'] ? 'active' : 'inactive' ?>"><?= $interaction['active'] ? 'Active' : 'Inactive' ?></span>
                        <span><strong>Group:</strong> <?= htmlspecialchars($interaction['interaction_group']) ?></span>
                        <?php if (!empty($interaction['category'])): ?>
                            <span><strong>Category:</strong> <?= htmlspecialchars($interaction['category']) ?></span>
                        <?php endif; ?>
                        <div class="description">"<?= htmlspecialchars($interaction['description']) ?>"</div>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn btn-sm btn-outline-secondary" onclick="openCopyModal(<?= (int)$interaction['id'] ?>)">📋 Copy</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= (int)$interaction['id'] ?>)">Edit</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleInteraction(<?= (int)$interaction['id'] ?>)"><?= $interaction['active'] ? 'Disable' : 'Enable' ?></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteInteraction(<?= (int)$interaction['id'] ?>)">Delete</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="formModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Create Interaction</h3>
            <button class="btn btn-sm" onclick="closeFormModal()">Close</button>
        </div>
        <div class="modal-body">
            <form id="interactionForm">
                <input type="hidden" id="interactionId" name="id">
                
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" id="name" name="name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-textarea" required></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Group (Required)</label>
                        <input type="text" id="interaction_group" name="interaction_group" class="form-input" list="groupList" required>
                        <datalist id="groupList">
                            <?php foreach ($availableGroups as $group): ?>
                                <option value="<?= htmlspecialchars($group) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category (Optional)</label>
                        <input type="text" id="category" name="category" class="form-input" list="categoryList">
                        <datalist id="categoryList">
                            <?php foreach ($availableCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Example Prompt</label>
                    <textarea id="example_prompt" name="example_prompt" class="form-textarea" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:inline-flex; align-items:center; gap: 8px;">
                        <input type="checkbox" id="active" name="active" checked> Active
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeFormModal()">Cancel</button>
            <button class="btn btn-sm btn-primary" onclick="saveInteraction()">Save Interaction</button>
        </div>
    </div>
</div>

<script src="js/toast.js"></script>
<script>
const API_URL = '/interactions_api.php';

async function apiCall(action, data = {}) { 
    const response = await fetch(`${API_URL}?action=${action}`, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify(data) 
    }); 
    return response.json(); 
}

function showToast(message, type = 'info') { 
    if (typeof Toast !== 'undefined' && Toast.show) { 
        Toast.show(message, type); 
    } else { 
        console.log(`[${type}] ${message}`); 
    } 
}

function openCreateModal() { 
    document.getElementById('modalTitle').textContent = 'Create Interaction'; 
    document.getElementById('interactionForm').reset(); 
    document.getElementById('interactionId').value = ''; 
    document.getElementById('active').checked = true; 
    document.getElementById('formModal').classList.add('active'); 
}

async function openEditModal(id) { 
    const result = await apiCall('load', { id }); 
    if (result.status === 'ok' && result.data) { 
        const data = result.data; 
        document.getElementById('modalTitle').textContent = 'Edit Interaction'; 
        
        for (const key in data) { 
            const el = document.getElementById(key); 
            if (el) { 
                if (el.type === 'checkbox') el.checked = !!parseInt(data[key]); 
                else el.value = data[key] || ''; 
            } 
        } 
        
        document.getElementById('interactionId').value = data.id; 
        document.getElementById('formModal').classList.add('active'); 
    } else { 
        showToast('Failed to load interaction', 'error'); 
    } 
}

async function openCopyModal(id) {
    const result = await apiCall('load', { id });
    if (result.status === 'ok' && result.data) {
        const data = result.data;
        document.getElementById('modalTitle').textContent = 'Copy Interaction';
        
        for (const key in data) {
            const el = document.getElementById(key);
            if (el) {
                if (el.type === 'checkbox') el.checked = !!parseInt(data[key]);
                else el.value = data[key] || '';
            }
        }
        
        document.getElementById('name').value = '[Copy] ' + data.name;
        document.getElementById('interactionId').value = '';
        
        document.getElementById('formModal').classList.add('active');
    } else {
        showToast('Failed to load interaction for copying', 'error');
    }
}

function closeFormModal() { 
    document.getElementById('formModal').classList.remove('active'); 
}

async function saveInteraction() { 
    const data = { 
        id: document.getElementById('interactionId').value, 
        name: document.getElementById('name').value, 
        description: document.getElementById('description').value, 
        interaction_group: document.getElementById('interaction_group').value, 
        category: document.getElementById('category').value, 
        example_prompt: document.getElementById('example_prompt').value, 
        active: document.getElementById('active').checked ? 1 : 0 
    }; 
    
    const result = await apiCall('save', data); 
    if (result.status === 'ok') { 
        showToast('Interaction saved successfully', 'success'); 
        location.reload(); 
    } else { 
        showToast(result.message || 'Failed to save interaction', 'error'); 
    } 
}

async function deleteInteraction(id) { 
    if (!confirm('Are you sure you want to delete this interaction?')) return; 
    const result = await apiCall('delete', { id }); 
    if (result.status === 'ok') { 
        location.reload(); 
    } else { 
        showToast(result.message || 'Failed to delete interaction', 'error'); 
    } 
}

async function toggleInteraction(id) { 
    const result = await apiCall('toggle', { id }); 
    if (result.status === 'ok') { 
        location.reload(); 
    } else { 
        showToast(result.message || 'Failed to update status', 'error'); 
    } 
}

// Filter functionality
(function(){
    const groupFilter = document.getElementById('groupFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const searchFilter = document.getElementById('searchFilter');
    const filterCount = document.getElementById('filterCount');
    
    function updateFilter() {
        const selectedGroup = groupFilter.value.toLowerCase();
        const selectedCategory = categoryFilter.value.toLowerCase();
        const searchTerm = searchFilter.value.toLowerCase();
        
        const rows = document.querySelectorAll('.interaction-list-container .interaction-item[data-id]');
        let visibleCount = 0;
        
        rows.forEach(row => {
            let showRow = true;
            const rowGroup = (row.getAttribute('data-group') || '').toLowerCase();
            const rowCategory = (row.getAttribute('data-category') || '').toLowerCase();
            const rowText = row.textContent.toLowerCase();
            
            if (selectedGroup && rowGroup !== selectedGroup) { showRow = false; }
            if (selectedCategory && rowCategory !== selectedCategory) { showRow = false; }
            if (searchTerm && !rowText.includes(searchTerm)) { showRow = false; }
            
            if (showRow) { 
                row.style.display = 'flex'; 
                visibleCount++; 
            } else { 
                row.style.display = 'none'; 
            }
        });
        
        if (filterCount) { 
            const totalRows = rows.length; 
            filterCount.textContent = `${visibleCount} of ${totalRows} interactions shown`; 
        }
    }
    
    [groupFilter, categoryFilter].forEach(el => el.addEventListener('change', updateFilter));
    searchFilter.addEventListener('input', updateFilter);
    updateFilter();
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>