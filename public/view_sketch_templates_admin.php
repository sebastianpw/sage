<?php
// public/view_sketch_templates_admin.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$pageTitle = "Sketch Templates Admin";
ob_start();

// Fetch all sketch templates
try {
    $stmt = $pdo->query("SELECT * FROM sketch_templates ORDER BY name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $rows = []; }

// Fetch available lookup values for dropdowns
try {
    $shotTypeStmt = $pdo->query("SELECT name FROM shot_types ORDER BY name ASC");
    $availableShotTypes = $shotTypeStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $availableShotTypes = []; }
try {
    $angleStmt = $pdo->query("SELECT name FROM camera_angles ORDER BY name ASC");
    $availableAngles = $angleStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $availableAngles = []; }
try {
    $perspStmt = $pdo->query("SELECT name FROM camera_perspectives ORDER BY name ASC");
    $availablePerspectives = $perspStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $availablePerspectives = []; }
try {
    $entityTypeStmt = $pdo->query("SELECT DISTINCT entity_type FROM sketch_templates ORDER BY entity_type ASC");
    $availableEntityTypes = $entityTypeStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $availableEntityTypes = []; }

// Entity types and icons for display
$entityTypes = [ 'characters' => 'Characters', 'character_poses' => 'Character Poses', 'animas' => 'Animas', 'locations' => 'Locations', 'backgrounds' => 'Backgrounds', 'artifacts' => 'Artifacts', 'vehicles' => 'Vehicles', 'scene_parts' => 'Scene Parts', 'controlnet_maps' => 'Controlnet Maps', 'spawns' => 'Spawns', 'generatives' => 'Generatives', 'sketches' => 'Sketches', 'prompt_matrix_blueprints' => 'Prompt Matrix Blueprints', 'composites' => 'Composites' ];
$entityIcons = [ 'characters' => '🦸', 'character_poses' => '🤸', 'animas' => '🐾', 'locations' => '🗺️', 'backgrounds' => '🏞️', 'artifacts' => '🏺', 'vehicles' => '🛸', 'scene_parts' => '🎬', 'controlnet_maps' => '☠️', 'spawns' => '🌱', 'generatives' => '⚡', 'sketches' => '🪄', 'prompt_matrix_blueprints' => '🌌', 'composites' => '🧩' ];

?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* Core Styles */
.admin-wrap { max-width: 1200px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; }
.admin-head .actions-group { display: flex; gap: 8px; flex-wrap: wrap; } /* For grouping buttons */

/* List Container */
.template-list-container { background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.08); border-radius: 8px; padding: 12px; box-shadow: var(--card-elevation); }

/* List Items */
.template-item { background: var(--bg); border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 8px; padding: 16px; margin-bottom: 12px; transition: all 0.15s ease; display: flex; align-items: center; gap: 16px; }
.template-item:last-child { margin-bottom: 0; }
.template-item:hover { border-color: var(--accent); }
.template-info { flex: 1; min-width: 0; }
.template-name { font-weight: 600; font-size: 1rem; color: var(--text); margin-bottom: 4px; }
.template-meta { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; }
.template-meta span { margin-right: 12px; display: inline-block; }
.template-meta .idea { display: block; margin-top: 6px; font-style: italic; }
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
.form-textarea.json { font-family: ui-monospace, monospace; font-size: 0.85rem; }
.form-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }

/* Responsive: stack on mobile */
@media (max-width: 768px) { .template-item { flex-direction: column; align-items: flex-start; } .actions { width: 100%; margin-top: 12px; } .actions .btn { flex: 1; text-align: center; } }
</style>

<div class="admin-wrap">
    <div class="admin-head">
        <h2 style="margin-left: 45px;">Templates</h2>
        <div class="actions-group">
            <a href="view_shot_types_admin.php" class="btn btn-sm btn-outline-secondary">Manage Shot Types</a>
            <a href="view_camera_angles_admin.php" class="btn btn-sm btn-outline-secondary">Manage Angles</a>
            <a href="view_camera_perspectives_admin.php" class="btn btn-sm btn-outline-secondary">Manage Perspectives</a>
            <button class="btn btn-sm btn-primary" onclick="openCreateModal()">+ New Template</button>
        </div>
    </div>

    <div class="filterbox">
        <label>Filter:</label>
        <select id="entityFilter"><option value="">All Entity Types</option><?php foreach ($availableEntityTypes as $type): ?><option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($entityIcons[$type] ?? '▫️') ?> <?= htmlspecialchars($entityTypes[$type] ?? ucfirst($type)) ?></option><?php endforeach; ?></select>
        <select id="shotTypeFilter"><option value="">All Shot Types</option><?php foreach ($availableShotTypes as $type): ?><option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select>
        <select id="angleFilter"><option value="">All Angles</option><?php foreach ($availableAngles as $angle): ?><option value="<?= htmlspecialchars($angle) ?>"><?= htmlspecialchars($angle) ?></option><?php endforeach; ?></select>
        <select id="perspectiveFilter"><option value="">All Perspectives</option><?php foreach ($availablePerspectives as $p): ?><option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select>
        <input type="text" id="searchFilter" placeholder="Search..." style="min-width:150px; flex:1; max-width:250px;">
        <span class="small-muted" id="filterCount" style="margin-left:auto;"></span>
    </div>

    <div class="template-list-container">
        <?php foreach ($rows as $template): ?>
            <div class="template-item" 
                 data-id="<?= (int)$template['id'] ?>" 
                 data-entity-type="<?= htmlspecialchars($template['entity_type']) ?>"
                 data-shot-type="<?= htmlspecialchars($template['shot_type']) ?>"
                 data-camera-angle="<?= htmlspecialchars($template['camera_angle']) ?>"
                 data-perspective="<?= htmlspecialchars($template['perspective']) ?>">
                <div class="template-info">
                    <div class="template-name"><?= htmlspecialchars($template['name']) ?></div>
                    <div class="template-meta">
                        <span class="status-badge <?= $template['active'] ? 'active' : 'inactive' ?>"><?= $template['active'] ? 'Active' : 'Inactive' ?></span>
                        <span><strong>Shot:</strong> <?= htmlspecialchars($template['shot_type']) ?></span>
                        <span><strong>Angle:</strong> <?= htmlspecialchars($template['camera_angle']) ?></span>
                        <span><strong>Perspective:</strong> <?= htmlspecialchars($template['perspective']) ?></span>
                        <div class="idea">“<?= htmlspecialchars($template['core_idea']) ?>”</div>
                    </div>
                </div>
                <div class="actions">
                    <!-- --- NEW: Copy Button --- -->
                    <button class="btn btn-sm btn-outline-secondary" onclick="openCopyModal(<?= (int)$template['id'] ?>)">📋 Copy</button>
                    <!-- --- END NEW --- -->
                    <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= (int)$template['id'] ?>)">Edit</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleTemplate(<?= (int)$template['id'] ?>)"><?= $template['active'] ? 'Disable' : 'Enable' ?></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?= (int)$template['id'] ?>)">Delete</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="formModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Create Template</h3>
            <button class="btn btn-sm" onclick="closeFormModal()">Close</button>
        </div>
        <div class="modal-body">
            <form id="templateForm">
                <input type="hidden" id="templateId" name="id">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;"><label class="form-label">Name</label><input type="text" id="name" name="name" class="form-input" required></div>
                    <div class="form-group" style="grid-column: 1 / -1;"><label class="form-label">Core Idea</label><input type="text" id="core_idea" name="core_idea" class="form-input" required></div>
                    <div class="form-group"><label class="form-label">Shot Type</label><select id="shot_type" name="shot_type" class="form-select" required><?php foreach ($availableShotTypes as $type): ?><option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Camera Angle</label><select id="camera_angle" name="camera_angle" class="form-select" required><?php foreach ($availableAngles as $angle): ?><option value="<?= htmlspecialchars($angle) ?>"><?= htmlspecialchars($angle) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Perspective</label><select id="perspective" name="perspective" class="form-select" required><?php foreach ($availablePerspectives as $p): ?><option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Entity Type</label><select id="entity_type" name="entity_type" class="form-select" required><?php foreach ($entityTypes as $key => $displayName): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($key === 'sketches' ? 'selected' : '') ?>><?= htmlspecialchars($entityIcons[$key] ?? '▫️') ?> <?= htmlspecialchars($displayName) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group"><label class="form-label">Entity Slots (JSON)</label><textarea id="entity_slots" name="entity_slots" class="form-textarea json">["ENVIRONMENT"]</textarea></div>
                    <div class="form-group"><label class="form-label">Tags (JSON)</label><textarea id="tags" name="tags" class="form-textarea json">["tag1", "tag2"]</textarea></div>
                </div>
                <div class="form-group"><label class="form-label">Example Prompt</label><textarea id="example_prompt" name="example_prompt" class="form-textarea"></textarea></div>
                <div class="form-group"><label class="form-label" style="display:inline-flex; align-items:center; gap: 8px;"><input type="checkbox" id="active" name="active" checked> Active</label></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeFormModal()">Cancel</button>
            <button class="btn btn-sm btn-primary" onclick="saveTemplate()">Save Template</button>
        </div>
    </div>
</div>

<script src="js/toast.js"></script>
<script>
const API_URL = '/sketch_templates_api.php';
async function apiCall(action, data = {}) { const response = await fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }); return response.json(); }
function showToast(message, type = 'info') { if (typeof Toast !== 'undefined' && Toast.show) { Toast.show(message, type); } else { console.log(`[${type}] ${message}`); } }
function openCreateModal() { document.getElementById('modalTitle').textContent = 'Create Template'; document.getElementById('templateForm').reset(); document.getElementById('templateId').value = ''; document.getElementById('active').checked = true; document.getElementById('entity_slots').value = '["ENVIRONMENT"]'; document.getElementById('tags').value = '["tag1", "tag2"]'; document.getElementById('entity_type').value = 'sketches'; document.getElementById('formModal').classList.add('active'); }
async function openEditModal(id) { const result = await apiCall('load', { id }); if (result.status === 'ok' && result.data) { const data = result.data; document.getElementById('modalTitle').textContent = 'Edit Template'; for (const key in data) { const el = document.getElementById(key); if (el) { if (el.type === 'checkbox') el.checked = !!parseInt(data[key]); else el.value = data[key]; } } document.getElementById('templateId').value = data.id; document.getElementById('formModal').classList.add('active'); } else { showToast('Failed to load template', 'error'); } }

// --- NEW: openCopyModal Function ---
async function openCopyModal(id) {
    const result = await apiCall('load', { id });
    if (result.status === 'ok' && result.data) {
        const data = result.data;
        document.getElementById('modalTitle').textContent = 'Copy Template';
        
        // Populate form fields from the copied item
        for (const key in data) {
            const el = document.getElementById(key);
            if (el) {
                if (el.type === 'checkbox') el.checked = !!parseInt(data[key]);
                else el.value = data[key];
            }
        }
        
        // Modify for a new entry
        document.getElementById('name').value = '[Copy] ' + data.name;
        document.getElementById('templateId').value = ''; // CRITICAL: This makes it a new item
        
        document.getElementById('formModal').classList.add('active');
    } else {
        showToast('Failed to load template for copying', 'error');
    }
}
// --- END NEW ---

function closeFormModal() { document.getElementById('formModal').classList.remove('active'); }
async function saveTemplate() { const data = { id: document.getElementById('templateId').value, name: document.getElementById('name').value, core_idea: document.getElementById('core_idea').value, shot_type: document.getElementById('shot_type').value, camera_angle: document.getElementById('camera_angle').value, perspective: document.getElementById('perspective').value, entity_slots: document.getElementById('entity_slots').value, tags: document.getElementById('tags').value, example_prompt: document.getElementById('example_prompt').value, entity_type: document.getElementById('entity_type').value, active: document.getElementById('active').checked ? 1 : 0 }; const result = await apiCall('save', data); if (result.status === 'ok') { showToast('Template saved successfully', 'success'); location.reload(); } else { showToast(result.message || 'Failed to save template', 'error'); } }
async function deleteTemplate(id) { if (!confirm('Are you sure you want to delete this template?')) return; const result = await apiCall('delete', { id }); if (result.status === 'ok') { location.reload(); }  else { showToast(result.message || 'Failed to delete template', 'error'); } }
async function toggleTemplate(id) { const result = await apiCall('toggle', { id }); if (result.status === 'ok') { location.reload(); } else { showToast(result.message || 'Failed to update status', 'error'); } }
</script>
<script>
(function(){
  const entityFilter = document.getElementById('entityFilter');
  const shotTypeFilter = document.getElementById('shotTypeFilter');
  const angleFilter = document.getElementById('angleFilter');
  const perspectiveFilter = document.getElementById('perspectiveFilter');
  const searchFilter = document.getElementById('searchFilter');
  const filterCount = document.getElementById('filterCount');
  function updateFilter() {
    const selectedEntity = entityFilter.value.toLowerCase();
    const selectedShotType = shotTypeFilter.value.toLowerCase();
    const selectedAngle = angleFilter.value.toLowerCase();
    const selectedPerspective = perspectiveFilter.value.toLowerCase();
    const searchTerm = searchFilter.value.toLowerCase();
    const rows = document.querySelectorAll('.template-list-container .template-item[data-id]');
    let visibleCount = 0;
    rows.forEach(row => {
      let showRow = true;
      const rowEntity = (row.getAttribute('data-entity-type') || '').toLowerCase();
      const rowShotType = (row.getAttribute('data-shot-type') || '').toLowerCase();
      const rowAngle = (row.getAttribute('data-camera-angle') || '').toLowerCase();
      const rowPerspective = (row.getAttribute('data-perspective') || '').toLowerCase();
      const rowText = row.textContent.toLowerCase();
      if (selectedEntity && rowEntity !== selectedEntity) { showRow = false; }
      if (selectedShotType && rowShotType !== selectedShotType) { showRow = false; }
      if (selectedAngle && rowAngle !== selectedAngle) { showRow = false; }
      if (selectedPerspective && rowPerspective !== selectedPerspective) { showRow = false; }
      if (searchTerm && !rowText.includes(searchTerm)) { showRow = false; }
      if (showRow) { row.style.display = 'flex'; visibleCount++; } else { row.style.display = 'none'; }
    });
    if (filterCount) { const totalRows = rows.length; filterCount.textContent = `${visibleCount} of ${totalRows} templates shown`; }
  }
  [entityFilter, shotTypeFilter, angleFilter, perspectiveFilter].forEach(el => el.addEventListener('change', updateFilter));
  searchFilter.addEventListener('input', updateFilter);
  updateFilter();
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>

