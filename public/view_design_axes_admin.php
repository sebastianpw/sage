<?php
// public/view_design_axes_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Design Axes Admin";

// Check if axis_group and category columns exist
$columnExists = false;
$categoryColumnExists = false;
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'axis_group'");
    $columnExists = $checkCol->rowCount() > 0;
    $checkCatCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'category'");
    $categoryColumnExists = $checkCatCol->rowCount() > 0;
} catch (Exception $e) {
    // column doesn't exist
}

ob_start();

// fetch axes
try {
    // Dynamically build the select statement based on column existence
    $selectFields = "id, axis_name, pole_left, pole_right, notes, created_at";
    if ($columnExists) {
        $selectFields .= ", COALESCE(axis_group, 'default') as axis_group";
    }
    if ($categoryColumnExists) {
        $selectFields .= ", COALESCE(category, '') as category";
    }

    $orderBy = $columnExists ? "ORDER BY axis_group ASC, id ASC" : "ORDER BY id ASC";
    
    $stmt = $pdo->query("SELECT $selectFields FROM design_axes $orderBy");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
    if (isset($fileLogger) && is_callable([$fileLogger, 'error'])) {
        $fileLogger->error('view_design_axes_admin fetch error: '.$e->getMessage());
    }
}

// fetch available groups and categories for dropdowns
$availableGroups = [];
$availableCategories = [];
if ($columnExists) {
    try {
        $groupsStmt = $pdo->query("SELECT DISTINCT COALESCE(axis_group, 'default') as axis_group FROM design_axes ORDER BY axis_group ASC");
        $availableGroups = $groupsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}
if ($categoryColumnExists) {
    try {
        $catStmt = $pdo->query("SELECT DISTINCT category FROM design_axes WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        $availableCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* Theme-aware Design Axes Admin — uses base CSS variables only (no logic changes) */

.admin-wrap { max-width:1200px; margin:0 auto; padding:18px; color: var(--text); }
.admin-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.admin-head h2 { margin:0; font-weight:600; font-size:1.15rem; color: var(--text); }

/* Keep .btn from base.css — only small page-specific helpers here */
.btn { display:inline-block; padding:8px 10px; border-radius:6px; text-decoration:none; font-size:0.9rem; cursor:pointer; border:1px solid rgba(var(--muted-border-rgb),0.12); background: transparent; color: var(--text); }
.btn-primary { background: var(--accent); color: #fff; border-color: rgba(240,246,252,0.06); }
.btn-outline-secondary { background: transparent; border:1px solid rgba(var(--muted-border-rgb),0.18); color: var(--text); }
.btn-outline-danger { background: transparent; border:1px solid var(--red); color: var(--red); }
.btn-sm { padding:6px 8px; font-size:0.85rem; border-radius:6px; }

/* NEW Card List Layout - inspired by generator_admin.php */
.axis-list-container {
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 12px;
    box-shadow: var(--card-elevation);
    margin-top: 16px;
}
.axis-item {
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
.axis-item:last-child { margin-bottom: 0; }
.axis-item:hover { border-color: var(--accent); }
.axis-info { flex: 1; min-width: 0; }
.axis-name { font-weight: 600; font-size: 1rem; color: var(--text); margin-bottom: 4px; }
.axis-meta { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; }
.axis-meta span { margin-right: 12px; display: inline-block; }
.axis-meta .notes { display: block; margin-top: 6px; font-style: italic; max-width: 90%; }
.actions { display:flex; gap:8px; flex-wrap:wrap; }
.empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
/* End of New Card List Layout */


/* Badges and small text */
.small-muted { color: var(--text-muted); font-size:0.85rem; }
.badge { display:inline-block; padding:2px 8px; background: var(--blue-light-bg); color: var(--blue-light-text); border-radius:4px; font-size:0.82rem; border:1px solid var(--blue-light-border); }
.cat-badge { background: var(--green-light-bg); color: var(--green-light-text); border-color: var(--green-light-border); }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; z-index:120000; padding:12px; }
.modal-card { width:100%; max-width:600px; background: var(--card); border-radius:10px; box-shadow: var(--card-elevation); overflow:hidden; display:flex; flex-direction:column; max-height:90vh; border:1px solid rgba(var(--muted-border-rgb),0.06); color: var(--text); }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid rgba(var(--muted-border-rgb),0.06); }
.modal-body { padding:16px; overflow:auto; background: var(--bg); color: var(--text); }
.modal-footer { padding:10px 16px; border-top:1px solid rgba(var(--muted-border-rgb),0.06); display:flex; gap:8px; justify-content:flex-end; background: var(--bg); }

/* Forms */
.form-group { margin-bottom:12px; }
.form-group label { display:block; margin-bottom:4px; font-weight:600; font-size:0.9rem; color: var(--text); }
.form-control { width:100%; padding:8px 10px; border-radius:6px; border:1px solid rgba(var(--muted-border-rgb),0.12); font-size:0.95rem; background: var(--bg); color: var(--text); }
textarea.form-control { min-height:80px; resize:vertical; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Responsive */
@media (max-width: 768px) {
    .axis-item { flex-direction: column; align-items: flex-start; }
    .actions { width: 100%; }
    .actions .btn { flex-grow: 1; text-align: center; }
}
@media (max-width: 480px) {
    .axis-meta span { display: block; margin: 4px 0; }
    .form-grid-2 { grid-template-columns: 1fr; }
}


/* Filter / toolbar background (was #f8f9fa) */
.filterbox {
    background-color: var(--card);
    border-radius:8px;
    padding:12px;
    border:1px solid rgba(var(--muted-border-rgb),0.06);
    color: var(--text);
}

/* Inline controls (selects / inputs) previously had hard-coded borders/colors */
input[type="text"], select[id="groupFilter"], #searchFilter {
    padding:6px 10px;
    border-radius:6px;
    border:1px solid rgba(var(--muted-border-rgb),0.12);
    background: var(--bg);
    color: var(--text);
    min-width:140px;
}

/* Ensure icons/links inherit theme color */
a, button { color: inherit; }

</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2 style="margin-left:45px;">Design Axes</h2>
    <div style="display:flex;gap:8px;align-items:center;">
      <a class="btn btn-outline-secondary btn-sm" href="view_style_sliders.php">&larr; Sliders</a>
      <a class="btn btn-outline-secondary btn-sm" href="view_style_profiles_admin.php">Profiles</a>
      <button class="btn btn-sm" id="btnCreateAxis">+ Create Axis</button>
    </div>
  </div>

  <p class="small-muted">Manage design axes: the sliders that define your style profiles.</p>

  <?php if ($columnExists && count($availableGroups) > 0): ?>
  <div class="filterbox" style="margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <label style="font-weight:600; margin:0;">Filter:</label>
    <select id="groupFilter">
      <option value="">All Groups</option>
      <?php foreach ($availableGroups as $grp): ?>
        <option value="<?= htmlspecialchars($grp) ?>"><?= htmlspecialchars(ucfirst($grp)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" id="searchFilter" placeholder="Search axes..." style="min-width:200px; flex:1; max-width:300px;">
    <span class="small-muted" id="filterCount" style="margin-left:auto;"></span>
  </div>
  <?php elseif (!$columnExists): ?>
  <div class="filterbox" style="margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <label style="font-weight:600; margin:0;">Search:</label>
    <input type="text" id="searchFilter" placeholder="Search axes..." style="min-width:200px; flex:1; max-width:300px;">
    <span class="small-muted" id="filterCount" style="margin-left:auto;"></span>
  </div>
  <?php endif; ?>

  <div class="axis-list-container">
      <?php if (empty($rows)): ?>
          <div class="empty-state">No axes defined yet.</div>
      <?php else: ?>
          <?php foreach ($rows as $r): ?>
              <div class="axis-item" data-axis-id="<?= (int)$r['id'] ?>" <?php if ($columnExists): ?>data-axis-group="<?= htmlspecialchars($r['axis_group']) ?>"<?php endif; ?>>
                  <div class="axis-info">
                      <div class="axis-name"><?= htmlspecialchars($r['axis_name']) ?></div>
                      <div class="axis-meta">
                          <span><strong>ID:</strong> <?= (int)$r['id'] ?></span>
                          <?php if ($columnExists): ?>
                          <span><strong>Group:</strong> <span class="badge"><?= htmlspecialchars($r['axis_group']) ?></span></span>
                          <?php endif; ?>
                          <?php if ($categoryColumnExists && !empty($r['category'])): ?>
                          <span><strong>Category:</strong> <span class="badge cat-badge"><?= htmlspecialchars($r['category']) ?></span></span>
                          <?php endif; ?>
                          <span><strong>Poles:</strong> <?= htmlspecialchars($r['pole_left']) ?> &rarr; <?= htmlspecialchars($r['pole_right']) ?></span>
                           <?php if (!empty($r['notes'])): ?>
                              <div class="notes" title="<?= htmlspecialchars($r['notes']) ?>">
                                  <strong>Notes:</strong> <?= htmlspecialchars(mb_strimwidth($r['notes'], 0, 100, "...")) ?>
                              </div>
                          <?php endif; ?>
                      </div>
                  </div>
                  <div class="actions">
                      <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="<?= (int)$r['id'] ?>">Edit</button>
                      <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= (int)$r['id'] ?>">Delete</button>
                  </div>
              </div>
          <?php endforeach; ?>
      <?php endif; ?>
  </div>
</div>

<!-- edit/create modal -->
<div id="axisModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-card" role="document">
    <div class="modal-header">
      <strong id="modalTitle">Create Axis</strong>
      <button id="modalCloseBtn" class="btn btn-sm">×</button>
    </div>
    <div class="modal-body">
      <form id="axisForm">
        <input type="hidden" id="axisId" value="">
        
        <div class="form-group">
          <label for="axisName">Axis Name *</label>
          <input type="text" class="form-control" id="axisName" required>
        </div>

        <div class="form-grid-2">
            <?php if ($columnExists): ?>
            <div class="form-group">
              <label for="axisGroup">Axis Group *</label>
              <input type="text" class="form-control" id="axisGroup" list="axisGroupList" value="default" required>
              <datalist id="axisGroupList">
                <?php foreach ($availableGroups as $grp): ?>
                  <option value="<?= htmlspecialchars($grp) ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <?php endif; ?>

            <?php if ($categoryColumnExists): ?>
            <div class="form-group">
              <label for="axisCategory">Category</label>
              <input type="text" class="form-control" id="axisCategory" list="axisCategoryList">
               <datalist id="axisCategoryList">
                <?php foreach ($availableCategories as $cat): ?>
                  <option value="<?= htmlspecialchars($cat) ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="poleLeft">Left Pole *</label>
          <input type="text" class="form-control" id="poleLeft" required>
        </div>

        <div class="form-group">
          <label for="poleRight">Right Pole *</label>
          <input type="text" class="form-control" id="poleRight" required>
        </div>

        <div class="form-group">
          <label for="axisNotes">Notes</label>
          <textarea class="form-control" id="axisNotes"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button id="modalCancelBtn" class="btn btn-sm">Cancel</button>
      <button id="modalSaveBtn" class="btn btn-primary btn-sm">Save</button>
    </div>
  </div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
  const columnExists = <?= $columnExists ? 'true' : 'false' ?>;
  const categoryColumnExists = <?= $categoryColumnExists ? 'true' : 'false' ?>;
  
  function showToast(msg, type) {
    if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
      const mapType = (type === 'danger' || type === 'error') ? 'error' : (type === 'warning' ? 'info' : (type || 'info'));
      Toast.show(msg, mapType);
    } else {
      console.log('[toast]', msg);
      alert(msg);
    }
  }

  const modalOverlay = document.getElementById('axisModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalCloseBtn = document.getElementById('modalCloseBtn');
  const modalCancelBtn = document.getElementById('modalCancelBtn');
  const modalSaveBtn = document.getElementById('modalSaveBtn');
  const axisForm = document.getElementById('axisForm');

  function showModal(title, axisData) {
    modalTitle.textContent = title;
    
    document.getElementById('axisId').value = axisData?.id || '';
    document.getElementById('axisName').value = axisData?.axis_name || '';
    if (columnExists) {
      document.getElementById('axisGroup').value = axisData?.axis_group || 'default';
    }
    if (categoryColumnExists) {
      document.getElementById('axisCategory').value = axisData?.category || '';
    }
    document.getElementById('poleLeft').value = axisData?.pole_left || '';
    document.getElementById('poleRight').value = axisData?.pole_right || '';
    document.getElementById('axisNotes').value = axisData?.notes || '';

    modalOverlay.style.display = 'flex';
    modalOverlay.setAttribute('aria-hidden','false');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  }

  function hideModal() {
    modalOverlay.style.display = 'none';
    modalOverlay.setAttribute('aria-hidden','true');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    axisForm.reset();
  }

  modalCloseBtn.addEventListener('click', hideModal);
  modalCancelBtn.addEventListener('click', hideModal);
  modalOverlay.addEventListener('click', function(e){
    if (e.target === modalOverlay) hideModal();
  });

  document.getElementById('btnCreateAxis').addEventListener('click', function(){
    showModal('Create Axis', null);
  });

  modalSaveBtn.addEventListener('click', function(){
    if (!axisForm.checkValidity()) {
      axisForm.reportValidity();
      return;
    }

    const payload = {
      axis_name: document.getElementById('axisName').value.trim(),
      pole_left: document.getElementById('poleLeft').value.trim(),
      pole_right: document.getElementById('poleRight').value.trim(),
      notes: document.getElementById('axisNotes').value.trim()
    };

    if (columnExists) {
      payload.axis_group = document.getElementById('axisGroup').value.trim() || 'default';
    }
    if (categoryColumnExists) {
      payload.category = document.getElementById('axisCategory').value.trim();
    }

    const axisId = document.getElementById('axisId').value;
    if (axisId) {
      payload.id = parseInt(axisId, 10);
    }

    fetch('design_axes_api.php?action=save', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    }).then(r => r.json())
    .then(data => {
      if (data && data.status === 'ok') {
        showToast(axisId ? 'Axis updated' : 'Axis created', 'success');
        hideModal();
        location.reload();
      } else {
        showToast('Save failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
      }
    }).catch(err => {
      console.error('save axis failed', err);
      showToast('Network error saving axis', 'error');
    });
  });

  function loadAxis(id) {
    fetch('design_axes_api.php?action=load&id=' + encodeURIComponent(id))
      .then(r => r.json())
      .then(data => {
        if (data && data.status === 'ok' && data.axis) {
          showModal('Edit Axis', data.axis);
        } else {
          showToast('Could not load axis', 'error');
        }
      })
      .catch(err => {
        console.error('load axis failed', err);
        showToast('Network error loading axis', 'error');
      });
  }

  function deleteAxis(id, rowEl) {
    if (!confirm('Delete this axis? This will also remove it from all profiles.')) return;
    
    fetch('design_axes_api.php?action=delete', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: id})
    }).then(r => r.json())
    .then(data => {
      if (data && data.status === 'ok') {
        showToast('Axis deleted', 'success');
        if (rowEl && rowEl.parentNode) rowEl.parentNode.removeChild(rowEl);
      } else {
        showToast('Delete failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
      }
    }).catch(err => {
      console.error('delete axis failed', err);
      showToast('Network error deleting axis', 'error');
    });
  }

  document.addEventListener('click', function(ev){
    const el = ev.target;
    if (el.matches('.btn-edit')) {
      const id = el.dataset.id;
      loadAxis(id);
      return;
    }
    if (el.matches('.btn-delete')) {
      const id = el.dataset.id;
      const row = el.closest('.axis-item[data-axis-id]');
      deleteAxis(id, row);
      return;
    }
  }, false);

})();
</script>

<script>
// Client-side filtering
(function(){
  const groupFilter = document.getElementById('groupFilter');
  const searchFilter = document.getElementById('searchFilter');
  const filterCount = document.getElementById('filterCount');
  
  if (!searchFilter && !groupFilter) return;
  
  function updateFilter() {
    const selectedGroup = groupFilter ? groupFilter.value.toLowerCase() : '';
    const searchTerm = searchFilter ? searchFilter.value.toLowerCase() : '';
    const rows = document.querySelectorAll('.axis-list-container .axis-item[data-axis-id]');
    let visibleCount = 0;
    
    rows.forEach(row => {
      let showRow = true;
      
      // Filter by group
      if (selectedGroup && groupFilter) {
        const rowGroup = (row.getAttribute('data-axis-group') || '').toLowerCase();
        if (rowGroup !== selectedGroup) {
          showRow = false;
        }
      }
      
      // Filter by search term (checks all text content)
      if (searchTerm && showRow) {
        const rowText = row.textContent.toLowerCase();
        if (!rowText.includes(searchTerm)) {
          showRow = false;
        }
      }
      
      if (showRow) {
        row.style.display = 'flex';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });
    
    if (filterCount) {
      const totalRows = rows.length;
      filterCount.textContent = visibleCount + ' of ' + totalRows + ' axes shown';
    }
  }
  
  if (groupFilter) groupFilter.addEventListener('change', updateFilter);
  if (searchFilter) searchFilter.addEventListener('input', updateFilter);
  
  // Initial count
  updateFilter();
})();
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
