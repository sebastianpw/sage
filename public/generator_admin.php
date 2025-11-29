<?php
// public/generator_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die('Not authenticated');
}

$pageTitle = "Generator Admin";
ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">

<style>
/* Admin wrapper - consistent with other admin pages */
.admin-wrap { max-width: 1100px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; color: var(--text); }
.admin-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* Generator list container */
.generator-list-container {
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 12px;
    box-shadow: var(--card-elevation);
}

/* Generator items */
.generator-item {
    background: var(--bg);
    border: 1px solid rgba(var(--muted-border-rgb), 0.12);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 16px;
}
.generator-item:last-child { margin-bottom: 0; }
.generator-item:hover { border-color: var(--accent); }

.generator-info { flex: 1; min-width: 0; }
.generator-name { 
    font-weight: 600; 
    font-size: 1rem; 
    color: var(--text); 
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.generator-meta { font-size: 0.85rem; color: var(--text-muted); }
.generator-meta span { margin-right: 12px; display: inline-block; vertical-align: middle; }
.generator-meta .area-badge {
    background: rgba(var(--muted-border-rgb), 0.1);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-right: 4px;
    display: inline-block;
    margin-bottom: 4px;
}

.generator-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* Status badges */
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
.status-badge.active { background: rgba(35,134,54,0.12); color: var(--green); }
.status-badge.inactive { background: rgba(var(--muted-border-rgb), 0.12); color: var(--text-muted); }
.status-badge.public-badge { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.status-badge.owner-badge { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }

/* Empty state & Modals */
.empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 700px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(2,6,23,0.35); display: flex; flex-direction: column; max-height: 90vh; border: 1px solid rgba(var(--muted-border-rgb),0.06); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); }
.modal-body { padding: 20px; overflow-y: auto; color: var(--text); }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); background: var(--bg); display: flex; justify-content: flex-end; gap: 8px; }

/* Form elements */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); transition: border-color 0.15s ease; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--accent); }
.form-textarea { min-height: 300px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace; resize: vertical; font-size: 0.85rem; }
.form-grid { display: grid; gap: 16px; grid-template-columns: repeat(3, 1fr); }
.form-checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.form-checkbox-label input[type="checkbox"] { cursor: pointer; }

.form-section {
    border-top: 1px solid rgba(var(--muted-border-rgb), 0.1);
    margin-top: 20px;
    padding-top: 20px;
}
.form-section-header {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 16px;
}

/* Test parameters */
.test-params { display: grid; gap: 16px; margin-bottom: 20px; }
.test-result { background: var(--bg); padding: 16px; border-radius: 8px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); margin-top: 20px; }
.test-result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.test-result pre { margin: 0; font-size: 0.8rem; max-height: 300px; overflow: auto; white-space: pre-wrap; word-break: break-word; background: var(--card); padding: 12px; border-radius: 6px; }

/* Loading spinner */
.loading-container { text-align: center; padding: 40px 20px; }
.spinner { width: 40px; height: 40px; margin: 0 auto 16px; border: 4px solid rgba(var(--muted-border-rgb), 0.2); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Drag and Drop */
.drag-handle { cursor: grab; color: var(--text-muted); }
.drag-handle:hover { color: var(--accent); }
.dragging-ghost { opacity: 0.4; background: var(--accent-translucent); border: 1px dashed var(--accent); }
.sortable-chosen .drag-handle { cursor: grabbing; }

/* Multi-select component styles */
.multi-select-container { position: relative; }
.multi-select-button { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); cursor: pointer; user-select: none; }
.multi-select-button:after { content: '▼'; font-size: 0.7rem; color: var(--text-muted); }
.multi-select-dropdown { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: var(--card); border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 6px; box-shadow: var(--card-elevation); z-index: 10; max-height: 200px; overflow-y: auto; display: none; }
.multi-select-dropdown.visible { display: block; }
.multi-select-dropdown label { display: flex; align-items: center; gap: 8px; padding: 8px 12px; cursor: pointer; font-size: 0.9rem; }
.multi-select-dropdown label:hover { background: rgba(var(--muted-border-rgb), 0.08); }
.multi-select-dropdown input[type="checkbox"] { cursor: pointer; }

/* Mobile responsive */
@media (max-width: 768px) {
    .admin-head { flex-direction: column; align-items: flex-start; }
    .form-grid { grid-template-columns: 1fr; }
    .generator-item { flex-direction: column; align-items: flex-start; }
    .generator-actions { width: 100%; }
    .generator-actions .btn { flex: 1; }
    .modal-card { max-height: 95vh; }
}
@media (max-width: 480px) { .admin-wrap { padding: 12px; } .generator-meta span { display: block; margin: 4px 0; } }
</style>

<div class="admin-wrap">
    <div class="admin-head">
        <h2>Generator Admin</h2>
        <div class="admin-head-actions">
            <a href="/generator_display_areas_admin.php" class="btn btn-sm btn-outline-secondary">Manage Display Areas</a>
            <button class="btn btn-sm btn-primary" onclick="openCreateModal()">+ New Generator</button>
        </div>
    </div>
    <div id="noticeContainer"></div>
    <div class="generator-list-container" id="generatorListContainer">
        <div class="empty-state">Loading generators...</div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="formModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Create Generator</h3>
            <button class="btn btn-sm modal-close-btn" onclick="closeFormModal()">Close</button>
        </div>
        <div class="modal-body">
            <form id="generatorForm">
                <input type="hidden" id="generatorId" name="id">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" id="title" name="title" class="form-input" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Model</label>
                        <select id="model" name="model" class="form-select" required></select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Area(s)</label>
                        <div class="multi-select-container" id="displayAreaMultiSelect">
                            <div class="multi-select-button" id="multiSelectButton">Select areas...</div>
                            <div class="multi-select-dropdown" id="multiSelectDropdown"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Access Control</label>
                        <label class="form-checkbox-label">
                            <input type="checkbox" id="isPublic" name="is_public">
                            <span>Public</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Configuration JSON</label>
                    <textarea id="configJson" name="config_json" class="form-textarea" required></textarea>
                </div>
                
                <div class="form-section">
                    <h4 class="form-section-header">🔮 Creative Oracle (Optional)</h4>
                    <div class="form-group">
                        <label class="form-label">Source Dictionaries</label>
                        <select id="oracleDictionaries" name="oracle_dictionaries" class="form-select" multiple size="4"></select>
                        <small>Select one or more dictionaries to source inspirational words from. Hold Ctrl/Cmd to select multiple.</small>
                    </div>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="form-group">
                            <label class="form-label">Words to Sample</label>
                            <input type="number" id="oracleNumWords" class="form-input" value="200" placeholder="e.g., 200">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Error Rate</label>
                            <input type="number" id="oracleErrorRate" class="form-input" value="0.01" step="0.001" placeholder="e.g., 0.01">
                        </div>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeFormModal()">Cancel</button>
            <button class="btn btn-sm btn-primary" onclick="saveGenerator()">Save Generator</button>
        </div>
    </div>
</div>

<!-- Test Modal -->
<div id="testModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="testModalTitle">Test Generator</h3>
            <button class="btn btn-sm modal-close-btn" onclick="closeTestModal()">Close</button>
        </div>
        <div class="modal-body">
            <div class="test-params" id="testParams"></div>
            <div id="testLoading" class="loading-container" style="display:none;">
                <div class="spinner"></div>
                <p style="color:var(--text-muted);">Generating...</p>
            </div>
            <div id="testResult" class="test-result" style="display:none;">
                <div class="test-result-header">
                    <strong>Result:</strong>
                    <button class="btn btn-sm btn-outline-success" onclick="copyTestResult()">📋 Copy</button>
                </div>
                <pre id="testResultContent"></pre>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary" onclick="closeTestModal()">Close</button>
            <button class="btn btn-sm btn-success" id="runTestBtn" onclick="runTest()">⚗️ Generate</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="js/toast.js"></script>
<script>
const API_URL = '/generator_actions.php';
const defaultTemplate = { system: { role: 'Content Generator', instructions: [ 'You are an expert content generator.', 'Always return valid JSON matching the output schema.', 'If you cannot comply, return {"error": "schema_noncompliant", "reason": "brief explanation"}' ] }, parameters: { mode: { type: 'string', enum: ['simple', 'detailed'], default: 'simple', label: 'Generation Mode' } }, output: { type: 'object', properties: { result: { type: 'string' }, metadata: { type: 'object' } }, required: ['result', 'metadata'] }, examples: [] };
let currentEditId = null;
let currentTestConfig = null;
let isAdmin = false;
let sortableInstance = null;
let allDisplayAreas = [];
let allDictionaries = [];

document.addEventListener('DOMContentLoaded', () => {
    loadModels();
    loadDisplayAreas();
    loadDictionaries();
    checkAdminStatus();
    loadGenerators();
    initMultiSelect();
});

async function apiCall(action, data = {}) {
    const response = await fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...data }) });
    return response.json();
}

function showToast(message, type = 'info') { if (typeof Toast !== 'undefined' && Toast.show) { Toast.show(message, type); } }
async function checkAdminStatus() { const result = await apiCall('check_admin'); if (result.ok) isAdmin = result.is_admin; }

async function loadModels() {
    const result = await apiCall('get_models');
    if (result.ok) document.getElementById('model').innerHTML = result.data.map(m => `<option value="${m}">${m}</option>`).join('');
}

async function loadDictionaries() {
    try {
        const result = await apiCall('get_dictionaries');
        if (result.ok) {
            allDictionaries = result.data;
            const select = document.getElementById('oracleDictionaries');
            select.innerHTML = allDictionaries.map(d => `<option value="${d.id}">${escapeHtml(d.title)}</option>`).join('');
        }
    } catch (e) { console.error('Failed to load dictionaries:', e); }
}

async function loadDisplayAreas() {
    try {
        const result = await apiCall('get_display_areas');
        if (result.ok) {
            allDisplayAreas = result.data;
            document.getElementById('multiSelectDropdown').innerHTML = allDisplayAreas.map(area => `<label><input type="checkbox" value="${area.key}" data-label="${escapeHtml(area.label)}"><span>${escapeHtml(area.label)}</span></label>`).join('');
        }
    } catch (e) { console.error('Failed to load display areas:', e); }
}

async function loadGenerators() {
    try {
        const result = await apiCall('list');
        if (result.ok) {
            renderGenerators(result.data);
            initSortable();
        }
    } catch (e) { showToast('Failed to load generators', 'error'); }
}

function renderGenerators(generators) {
    const container = document.getElementById('generatorListContainer');
    if (generators.length === 0) { container.innerHTML = '<div class="empty-state">No generators yet. Create one!</div>'; return; }
    container.innerHTML = generators.map(gen => {
        const publicBadge = gen.is_public ? `<span class="status-badge public-badge">PUBLIC</span>` : '';
        const ownerBadge = gen.is_owner ? `<span class="status-badge owner-badge">MY GENERATOR</span>` : '';
        const canEdit = gen.is_owner || (gen.is_public && isAdmin);
        const canCopy = gen.is_owner || gen.is_public;
        const dragHandle = gen.is_owner ? `<span class="drag-handle" title="Drag to reorder">☰</span>` : ``;
        const displayAreasHtml = gen.display_areas.length > 0 ? gen.display_areas.map(a => `<span class="area-badge">${escapeHtml(a.label)}</span>`).join('') : `<span class="area-badge" style="opacity:0.6;">None</span>`;

        return `
        <div class="generator-item" data-id="${gen.id}">
            <div class="generator-info">
                <div class="generator-name">${dragHandle} ${escapeHtml(gen.title)} ${publicBadge} ${ownerBadge}</div>
                <div class="generator-meta">
                    <span>${displayAreasHtml}</span>
                    <span><strong>Model:</strong> ${escapeHtml(gen.model)}</span>
                    <span class="status-badge ${gen.active ? 'active' : 'inactive'}">${gen.active ? 'Active' : 'Inactive'}</span>
                </div>
            </div>
            <div class="generator-actions">
                ${canEdit ? `<button class="btn btn-sm btn-outline-primary" onclick="openEditModal(${gen.id})">Edit</button>
                             <button class="btn btn-sm btn-outline-secondary" onclick="openCopyModal(${gen.id})">Copy</button>
                             <button class="btn btn-sm btn-outline-secondary" onclick="toggleGenerator(${gen.id})">${gen.active ? 'Disable' : 'Enable'}</button>
                             <button class="btn btn-sm btn-outline-danger" onclick="deleteGenerator(${gen.id})">Delete</button>`
                          : (canCopy ? `<button class="btn btn-sm btn-outline-secondary" onclick="openCopyModal(${gen.id})">Copy</button>` : '')}
                <button class="btn btn-sm btn-outline-success" onclick="openTestModal(${gen.id})">⚗️ Test</button>
            </div>
        </div>`;
    }).join('');
}

function initSortable() {
    const listContainer = document.getElementById('generatorListContainer');
    if (sortableInstance) sortableInstance.destroy();
    sortableInstance = new Sortable(listContainer, { animation: 150, handle: '.drag-handle', ghostClass: 'dragging-ghost', onEnd: (evt) => saveOrder(Array.from(evt.to.children).map(item => item.dataset.id)) });
}

async function saveOrder(ids) {
    const result = await apiCall('update_order', { ids });
    if (result.ok) showToast('Order saved!', 'success');
    else { showToast('Failed to save order: ' + (result.error || 'Unknown error'), 'error'); loadGenerators(); }
}

function initMultiSelect() {
    const container = document.getElementById('displayAreaMultiSelect');
    const button = document.getElementById('multiSelectButton');
    const dropdown = document.getElementById('multiSelectDropdown');
    button.addEventListener('click', () => dropdown.classList.toggle('visible'));
    document.addEventListener('click', (e) => { if (!container.contains(e.target)) dropdown.classList.remove('visible'); });
    dropdown.addEventListener('change', updateMultiSelectButtonText);
}

function updateMultiSelectButtonText() {
    const button = document.getElementById('multiSelectButton');
    const checkboxes = document.querySelectorAll('#multiSelectDropdown input:checked');
    if (checkboxes.length === 0) button.textContent = 'Select areas...';
    else if (checkboxes.length <= 2) button.textContent = Array.from(checkboxes).map(cb => cb.dataset.label).join(', ');
    else button.textContent = `${checkboxes.length} areas selected`;
}

async function openEditModal(id) {
    currentEditId = id;
    const result = await apiCall('get', { id });
    if (result.ok) {
        const data = result.data;
        document.getElementById('modalTitle').textContent = 'Edit Generator';
        document.getElementById('generatorId').value = data.id;
        document.getElementById('title').value = data.title;
        document.getElementById('model').value = data.model;
        document.getElementById('isPublic').checked = data.is_public;
        document.getElementById('isPublic').disabled = !isAdmin;
        document.getElementById('configJson').value = data.config_json;
        
        document.querySelectorAll('#multiSelectDropdown input[type="checkbox"]').forEach(cb => { cb.checked = data.display_area_keys.includes(cb.value); });
        updateMultiSelectButtonText();

        const oracleConfig = data.oracle_config || {};
        document.getElementById('oracleNumWords').value = oracleConfig.num_words || 200;
        document.getElementById('oracleErrorRate').value = oracleConfig.error_rate || 0.01;
        const dictSelect = document.getElementById('oracleDictionaries');
        const selectedIds = (oracleConfig.dictionary_ids || []).map(String);
        Array.from(dictSelect.options).forEach(opt => {
            opt.selected = selectedIds.includes(opt.value);
        });
        
        document.getElementById('formModal').classList.add('active');
    } else { showToast('Failed to load generator: ' + result.error, 'error'); }
}

function openCreateModal() {
    currentEditId = null;
    document.getElementById('generatorForm').reset();
    document.getElementById('configJson').value = JSON.stringify(defaultTemplate, null, 2);
    document.getElementById('modalTitle').textContent = 'Create Generator';
    document.getElementById('isPublic').disabled = !isAdmin;
    document.querySelectorAll('#multiSelectDropdown input:checked').forEach(cb => cb.checked = false);
    updateMultiSelectButtonText();
    
    document.getElementById('oracleDictionaries').selectedIndex = -1;
    document.getElementById('oracleNumWords').value = 200;
    document.getElementById('oracleErrorRate').value = 0.01;
    
    document.getElementById('formModal').classList.add('active');
}

async function openCopyModal(id) {
    showToast('Creating a copy...', 'info');
    const result = await apiCall('copy', { id });
    if (result.ok && result.data.new_id) {
        showToast('Copy created successfully!', 'success');
        loadGenerators();
        openEditModal(result.data.new_id);
    } else { showToast('Failed to create copy: ' + (result.error || 'Unknown error'), 'error'); }
}

function closeFormModal() { document.getElementById('formModal').classList.remove('active'); }

async function saveGenerator() {
    const selectedAreaKeys = Array.from(document.querySelectorAll('#multiSelectDropdown input:checked')).map(cb => cb.value);
    const data = {
        title: document.getElementById('title').value,
        model: document.getElementById('model').value,
        config_json: document.getElementById('configJson').value,
        is_public: document.getElementById('isPublic').checked,
        display_area_keys: selectedAreaKeys
    };
    
    const selectedDicts = Array.from(document.getElementById('oracleDictionaries').selectedOptions).map(opt => parseInt(opt.value));
    if (selectedDicts.length > 0) {
        data.oracle_config = {
            dictionary_ids: selectedDicts,
            num_words: parseInt(document.getElementById('oracleNumWords').value) || 200,
            error_rate: parseFloat(document.getElementById('oracleErrorRate').value) || 0.01
        };
    } else {
        data.oracle_config = null;
    }
    
    const action = currentEditId ? 'update' : 'create';
    if (currentEditId) data.id = currentEditId;

    try {
        const result = await apiCall(action, data);
        if (result.ok) {
            showToast(result.message, 'success');
            closeFormModal();
            loadGenerators();
        } else { showToast(result.error, 'error'); }
    } catch (e) { showToast('Failed to save generator: ' + e.message, 'error'); }
}

async function deleteGenerator(id) {
    if (!confirm('Delete this generator? This cannot be undone.')) return;
    const result = await apiCall('delete', { id });
    if (result.ok) { showToast(result.message, 'success'); loadGenerators(); }
    else { showToast(result.error, 'error'); }
}

async function toggleGenerator(id) {
    try {
        const result = await apiCall('toggle', { id });
        if (result.ok) {
            showToast(result.message, 'success');
            loadGenerators();
        } else { showToast(result.error, 'error'); }
    } catch (e) { showToast('Failed to toggle generator', 'error'); }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Test Modal Functions
async function openTestModal(id) {
    currentTestConfig = { id, params: {} };
    document.getElementById('testResult').style.display = 'none';
    document.getElementById('testLoading').style.display = 'none';
    
    const result = await apiCall('get', { id });
    if (result.ok) {
        const configData = JSON.parse(result.data.config_json);
        document.getElementById('testModalTitle').textContent = `Test: ${escapeHtml(result.data.title)}`;
        renderTestParams(configData.parameters || {});
        document.getElementById('testModal').classList.add('active');
    } else { showToast('Failed to load generator config', 'error'); }
}

function renderTestParams(params) {
    const container = document.getElementById('testParams');
    container.innerHTML = '';
    for (const [key, def] of Object.entries(params)) {
        const formGroup = document.createElement('div'); formGroup.className = 'form-group';
        const label = document.createElement('label'); label.className = 'form-label'; label.textContent = def.label || key;
        formGroup.appendChild(label);
        let input;
        if (def.type === 'string' && def.enum) {
            input = document.createElement('select'); input.className = 'form-select'; input.name = key;
            def.enum.forEach(val => { const opt = document.createElement('option'); opt.value = val; opt.textContent = val; if (val === def.default) opt.selected = true; input.appendChild(opt); });
        } else if (def.type === 'string' && def.multiline) {
            input = document.createElement('textarea'); input.className = 'form-textarea'; input.name = key; input.value = def.default || ''; input.style.minHeight = '100px';
        } else {
            input = document.createElement('input'); input.type = 'text'; input.className = 'form-input'; input.name = key; input.value = def.default || '';
        }
        formGroup.appendChild(input); container.appendChild(formGroup);
    }
}

function closeTestModal() { document.getElementById('testModal').classList.remove('active'); }

async function runTest() {
    const params = {};
    document.querySelectorAll('#testParams input, #testParams select, #testParams textarea').forEach(input => { params[input.name] = input.value; });
    document.getElementById('testResult').style.display = 'none';
    document.getElementById('testLoading').style.display = 'block';
    document.getElementById('runTestBtn').disabled = true;
    try {
        const result = await apiCall('test', { id: currentTestConfig.id, params });
        if (result.ok && result.result) {
            document.getElementById('testResultContent').textContent = JSON.stringify(result.result, null, 2);
            document.getElementById('testResult').style.display = 'block';
        } else { showToast('Test failed: ' + (result.error || 'Unknown error'), 'error'); }
    } catch (e) { showToast('Test request failed: ' + e.message, 'error'); }
    finally { document.getElementById('testLoading').style.display = 'none'; document.getElementById('runTestBtn').disabled = false; }
}

function copyTestResult() {
    navigator.clipboard.writeText(document.getElementById('testResultContent').textContent).then(() => showToast('Copied to clipboard!', 'success')).catch(() => showToast('Failed to copy', 'error'));
}

// Modal event listeners
document.getElementById('formModal').addEventListener('click', (e) => { if (e.target.id === 'formModal') closeFormModal(); });
document.getElementById('testModal').addEventListener('click', (e) => { if (e.target.id === 'testModal') closeTestModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeFormModal(); closeTestModal(); } });
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
?>
