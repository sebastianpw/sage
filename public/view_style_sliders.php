<?php
// public/view_style_sliders.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Visual Style Slider Panel";

// Check if axis_group and category columns exist
$axisGroupColumnExists = false;
$categoryColumnExists = false;
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'axis_group'");
    $axisGroupColumnExists = $checkCol->rowCount() > 0;
    $checkCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'category'");
    $categoryColumnExists = $checkCol->rowCount() > 0;
} catch (Exception $e) {
    // column(s) might not exist
}

// Level 1: Determine current axis group (Entity Type)
$currentGroup = isset($_GET['axis_group']) ? trim($_GET['axis_group']) : null;
$availableGroups = [];

if ($axisGroupColumnExists) {
    $groupsStmt = $pdo->query("SELECT DISTINCT COALESCE(axis_group, 'default') as axis_group FROM design_axes ORDER BY axis_group ASC");
    $availableGroups = $groupsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!$currentGroup && !empty($availableGroups)) {
        $currentGroup = $availableGroups[0];
    }
} else {
    $currentGroup = 'default';
    $availableGroups = ['default'];
}

// Level 2: Determine current category based on the selected group
$currentCategory = isset($_GET['category']) ? trim($_GET['category']) : null;
$availableCategories = [];
if ($categoryColumnExists && $currentGroup) {
    $catStmt = $pdo->prepare("SELECT DISTINCT category FROM design_axes WHERE axis_group = :group AND category IS NOT NULL ORDER BY category ASC");
    $catStmt->execute([':group' => $currentGroup]);
    $availableCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$currentCategory && !empty($availableCategories)) {
        $currentCategory = $availableCategories[0];
    }
}

// Level 3: Fetch the axes based on both group and category
if ($axisGroupColumnExists) {
    $sql = "SELECT id, axis_name, pole_left, pole_right, notes FROM design_axes WHERE COALESCE(axis_group, 'default') = :group";
    $params = [':group' => $currentGroup ?: 'default'];
    
    if ($categoryColumnExists && $currentCategory) {
        $sql .= " AND category = :category";
        $params[':category'] = $currentCategory;
    } else if ($categoryColumnExists) {
        // If categories exist for this group, but none is selected, maybe show uncategorized ones
        $sql .= " AND category IS NULL";
    }

    $sql .= " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $axes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Fallback for old structure
    $stmt = $pdo->query("SELECT id, axis_name, pole_left, pole_right, notes FROM design_axes ORDER BY id ASC");
    $axes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* Theme-aware sliders view — uses base CSS variables only (no logic changes) */

.container-small { max-width:1100px; margin:0 auto; padding:16px; color: var(--text); }
.header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
.header h1 { margin:0; font-size:1.1rem; font-weight:600; color: var(--text); }
.header .sub { color: var(--text-muted); font-size:0.9rem; }

/* Controls and inputs — prefer global .btn from base.css; keep small helpers only */
.controls { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; align-items:flex-start; }
.input-text,
.input-textarea {
    flex:1 1 200px;
    min-width:160px;
    padding:8px 10px;
    border-radius:8px;
    border:1px solid rgba(var(--muted-border-rgb),0.12);
    background: var(--bg);
    color: var(--text);
}
.input-textarea { min-height:60px; resize:vertical; font-family:inherit; }

/* Buttons */
.btn { padding:8px 10px; border-radius:8px; cursor:pointer; font-size:0.95rem; border:1px solid rgba(var(--muted-border-rgb),0.12); background: transparent; color: var(--text); }
.btn-prim { background: var(--accent); color: #fff; border-color: rgba(240,246,252,0.06); }
.btn-prim:disabled { opacity:0.6; cursor:default; }
.btn-ghost { background: transparent; border:1px solid rgba(var(--muted-border-rgb),0.08); color: var(--text); }
.small-muted { color: var(--text-muted); font-size:0.86rem; }

/* Axes list */
.axes { display:flex; flex-direction:column; gap:10px; }
.axis-row {
    display:flex;
    gap:10px;
    align-items:center;
    padding:10px;
    border-radius:8px;
    background: color-mix(in srgb, var(--card) 92%, var(--bg) 8%);
    border:1px solid rgba(var(--muted-border-rgb),0.06);
    color: var(--text);
}
.axis-left { min-width:110px; font-size:0.92rem; color: var(--text); font-weight:600; }
.axis-range {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.axis-inputnum {
  flex-basis: 100%;
  width: 100%;
  max-width: 76px;
  background: var(--bg);
  color: var(--text);
  border: 1px solid rgba(var(--muted-border-rgb),0.12);
  border-radius:6px;
  padding:6px 8px;
}

/* Form group and labels */
.form-group { margin-bottom:12px; display:flex; flex-direction:column; gap:4px; }
.form-group label { font-weight:600; font-size:0.9rem; color: var(--text); }

/* Responsive */
@media (max-width:720px) {
  .axis-row { flex-direction:column; align-items:stretch; }
  .axis-left { display:block; margin-bottom:6px; }
  .controls { flex-direction:column; align-items:stretch; }
  .input-text, .input-textarea { width:97%; }
}

/* Result preview */
.result-area { margin-top:12px; color: var(--text); }
.result-pre {
    background: rgba(var(--muted-border-rgb), 0.03);
    padding:10px;
    border-radius:6px;
    font-family:monospace;
    font-size:0.92rem;
    white-space:pre-wrap;
    max-height:260px;
    overflow:auto;
    color: var(--text);
}

/* Filter bar */
.filter-bar {
    margin-bottom:16px;
    padding:12px;
    background: color-mix(in srgb, var(--card) 88%, var(--bg) 12%);
    border-radius:8px;
    display:flex;
    gap:16px;
    align-items:center;
    flex-wrap:wrap;
    border:1px solid rgba(var(--muted-border-rgb),0.06);
    color: var(--text);
}
.filter-group { display:flex; align-items:center; gap:8px; color: var(--text); }
.filter-group label { font-weight:600; color: var(--text); }
.filter-select {
    padding:6px 10px;
    border-radius:6px;
    border:1px solid rgba(var(--muted-border-rgb),0.12);
    background: var(--bg);
    color: var(--text);
}

/* Ensure links and buttons inherit theme */
a, button { color: inherit; }

/* Small loader / subtle accents (if used) */
.axis-range .small-muted { color: var(--text-muted); }

/* Keep any explicit inline panels theme-aware (the big white panel previously used) */
[style*="background:#fff"], .container-small > div[style*="background:#fff"] {
    background: var(--card) !important;
    border:1px solid rgba(var(--muted-border-rgb),0.06) !important;
    color: var(--text) !important;
}
</style>
<div class="container-small">
  <div class="header">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <div class="sub">Dial the visual axes — save profiles, download JSON, or load existing profiles.</div>

    <div style="display:flex;gap:8px;align-items:center;">
      <a class="btn btn-outline-primary btn-sm" href="view_style_profiles_admin.php">&larr; Admin</a>
      <a class="btn btn-sm" href="view_style_sliders.php?create_new=1">Create new</a>
    </div>
  </div>

  <!-- NEW: Filter bar for both Entity and Category -->
  <div class="filter-bar">
    <?php if ($axisGroupColumnExists && !empty($availableGroups)): ?>
    <div class="filter-group">
      <label for="axisGroupSelect">Entity:</label>
      <select id="axisGroupSelect" class="filter-select">
        <?php foreach ($availableGroups as $grp): ?>
          <option value="<?= htmlspecialchars($grp) ?>" <?= $grp === $currentGroup ? 'selected' : '' ?>>
            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $grp))) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <?php if ($categoryColumnExists && !empty($availableCategories)): ?>
    <div class="filter-group">
      <label for="categorySelect">Category:</label>
      <select id="categorySelect" class="filter-select">
        <?php foreach ($availableCategories as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $currentCategory ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <span class="small-muted" style="margin-left:8px;">
      (<?= count($axes) ?> axes found)
    </span>
  </div>
  
  <input type="hidden" id="currentAxisGroup" value="<?= htmlspecialchars($currentGroup) ?>">
  <input type="hidden" id="currentCategory" value="<?= htmlspecialchars($currentCategory) ?>">
  <input type="hidden" id="axisGroupsEnabled" value="<?= $axisGroupColumnExists ? '1' : '0' ?>">

  <div style="background:#fff; padding:16px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); margin-bottom:12px;">
    <div class="form-group">
      <label for="profileName">Profile Name <span class="small-muted">(optional)</span></label>
      <input id="profileName" class="input-text" placeholder="e.g., Modern Minimalist" style="max-height:20px !important;display:block;max-width:400px;" />
    </div>
    
    <div class="form-group">
      <label for="profileDescription">Description <span class="small-muted">(optional)</span></label>
      <textarea style="max-height:50px !important;" id="profileDescription" class="input-textarea" placeholder="Describe this style profile..." rows="3"></textarea>
    </div>
    
    <input type="hidden" id="currentProfileId" value="">

    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
      <button id="saveDbBtn" class="btn btn-prim">Save (DB)</button>
      <button id="downloadBtn" class="btn btn-ghost">Download JSON</button>
    </div>
  </div>

  <div class="axes" id="axesList">
    <?php if (empty($axes)): ?>
      <div style="padding:20px; text-align:center; color:#666;">
        No axes defined for this entity/category.
      </div>
    <?php else: ?>
      <?php foreach ($axes as $axis): 
          $id = (int)$axis['id'];
          $name = $axis['axis_name'];
          $left = $axis['pole_left'];
          $right = $axis['pole_right'];
          $notes = $axis['notes'];
      ?>
        <div class="axis-row" data-axis-id="<?= $id ?>">
          <div class="axis-left">
            <div><?= htmlspecialchars($name) ?></div>
            <?php if (!empty($notes)): ?><div class="small-muted"><?= htmlspecialchars($notes) ?></div><?php endif; ?>
          </div>

          <div class="axis-range">
            <span class="small-muted" style="min-width:84px;"><?= htmlspecialchars($left) ?></span>

            <input
              type="range"
              class="axis-slider"
              min="0" max="100" value="50"
              data-axis-id="<?= $id ?>"
              data-axis-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
              data-pole-left="<?= htmlspecialchars($left, ENT_QUOTES) ?>"
              data-pole-right="<?= htmlspecialchars($right, ENT_QUOTES) ?>"
              style="flex:1;"
            />

            <span class="small-muted" style="min-width:84px; text-align:right;"><?= htmlspecialchars($right) ?></span>

            <input type="number" class="axis-inputnum" min="0" max="100" value="50" data-axis-id="<?= $id ?>">
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="result-area" id="resultArea"></div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
  const axisGroupsEnabled = document.getElementById('axisGroupsEnabled').value === '1';
  
  function showToast(msg, type) {
    if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
      const mapType = (type === 'danger' || type === 'error') ? 'error' : (type === 'warning' ? 'info' : (type || 'info'));
      Toast.show(msg, mapType);
    } else {
      console.log('[toast]', msg);
      alert(msg);
    }
  }

  function setUpSync() {
    document.querySelectorAll('.axis-slider').forEach(function(slider){
      slider.addEventListener('input', function(){
        const id = this.dataset.axisId;
        const val = this.value;
        const num = document.querySelector('input.axis-inputnum[data-axis-id="'+id+'"]');
        if (num) num.value = val;
      });
    });

    document.querySelectorAll('input.axis-inputnum').forEach(function(num){
      num.addEventListener('input', function(){
        let v = parseInt(this.value || 0, 10);
        if (isNaN(v)) v = 0;
        if (v < 0) v = 0;
        if (v > 100) v = 100;
        this.value = v;
        const id = this.dataset.axisId;
        const slider = document.querySelector('.axis-slider[data-axis-id="'+id+'"]');
        if (slider) slider.value = v;
      });
    });
  }

  function collectPayload(name, description) {
    const payload = {
      name: name || null,
      description: description || null,
      created_at: new Date().toISOString(),
      axes: []
    };
    
    if (axisGroupsEnabled) {
      payload.axis_group = document.getElementById('currentAxisGroup').value || 'default';
    }
    
    document.querySelectorAll('.axis-slider').forEach(function(slider){
      const axisId = parseInt(slider.dataset.axisId, 10);
      const axisName = slider.dataset.axisName;
      const poleLeft = slider.dataset.poleLeft;
      const poleRight = slider.dataset.poleRight;
      const value = parseInt(slider.value, 10);
      payload.axes.push({
        id: axisId,
        key: axisName,
        pole_left: poleLeft,
        pole_right: poleRight,
        value: value
      });
    });
    
    return payload;
  }

  function applyProfileToUI(payload) {
    if (!payload || !payload.axes) return;
    document.getElementById('currentProfileId').value = payload.id ? String(payload.id) : '';
    document.getElementById('profileName').value = payload.name || '';
    document.getElementById('profileDescription').value = payload.description || '';
    
    if (axisGroupsEnabled && payload.axis_group) {
      const currentGroup = document.getElementById('currentAxisGroup').value;
      if (payload.axis_group !== currentGroup) {
        // When loading a profile from another group, we don't know the category yet,
        // so we redirect and let the backend figure out the first category.
        window.location.href = 'view_style_sliders.php?axis_group=' + encodeURIComponent(payload.axis_group) + '&load_profile_id=' + payload.id;
        return;
      }
    }
    
    payload.axes.forEach(function(ax){
      const slider = document.querySelector('.axis-slider[data-axis-id="'+ax.id+'"]');
      const num = document.querySelector('input.axis-inputnum[data-axis-id="'+ax.id+'"]');
      if (slider) slider.value = ax.value;
      if (num) num.value = ax.value;
    });
  }

  function loadProfileById(id, showToastOnSuccess = true) {
    if (!id) return;
    fetch('style_profiles_api.php?action=load&id=' + encodeURIComponent(id))
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(data => {
        if (data && data.status === 'ok' && data.payload) {
          applyProfileToUI(data.payload);
          if (showToastOnSuccess) showToast('Profile loaded: ' + (data.payload.name || 'id:' + id), 'success');
        } else {
          showToast('Could not load profile: ' + (data && data.message ? data.message : 'unknown'), 'error');
          console.error('load error', data);
        }
      })
      .catch(err => {
        console.error('load profile failed', err);
        showToast('Network error: ' + (err.message || ''), 'error');
      });
  }

  function saveProfileDB(name, description) {
    const payload = collectPayload(name, description);
    const curId = document.getElementById('currentProfileId').value || '';
    if (curId) payload.id = parseInt(curId, 10);

    showToast('Saving profile to DB…', 'info');
    fetch('style_profiles_api.php?action=save_db', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    }).then(r => r.json())
    .then(data => {
      if (data && data.status === 'ok') {
        if (data.profile_id) {
          document.getElementById('currentProfileId').value = String(data.profile_id);
        }
        showToast('Saved profile id: ' + data.profile_id, 'success');
        document.getElementById('resultArea').innerHTML = '<div class="result-pre"><strong>Saved profile id:</strong> ' + data.profile_id + '\n\n' + JSON.stringify(data.payload, null, 2) + '</div>';
      } else {
        showToast('Save failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
        console.error('save db error', data);
      }
    }).catch(err => {
      console.error('save db network error', err);
      showToast('Network error saving to DB', 'error');
    });
  }

  function downloadJson(name, description) {
    const payload = collectPayload(name, description);
    const filename = (name ? name.replace(/\s+/g,'_') : 'style_profile') + '_' + (new Date()).toISOString().replace(/[:.]/g,'-') + '.json';
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(payload, null, 2));
    const a = document.createElement('a');
    a.setAttribute('href', dataStr);
    a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();
    a.remove();
    showToast('Download started', 'info');
  }

  document.addEventListener('DOMContentLoaded', function(){
    setUpSync();

    const axisGroupSelect = document.getElementById('axisGroupSelect');
    if (axisGroupSelect) {
      axisGroupSelect.addEventListener('change', function(){
        const newGroup = this.value;
        // When changing entity, go to that group's page. The PHP will select the first category by default.
        window.location.href = 'view_style_sliders.php?axis_group=' + encodeURIComponent(newGroup);
      });
    }
    
    const categorySelect = document.getElementById('categorySelect');
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const newCategory = this.value;
            const currentGroup = document.getElementById('currentAxisGroup').value;
            const currentProfileId = document.getElementById('currentProfileId').value;

            let url = 'view_style_sliders.php?axis_group=' + encodeURIComponent(currentGroup) + '&category=' + encodeURIComponent(newCategory);
            
            // ** THE FIX IS HERE **
            // If a profile is currently loaded, make sure we pass its ID along
            // so the new sliders can be initialized with the correct values.
            if (currentProfileId) {
                url += '&load_profile_id=' + encodeURIComponent(currentProfileId);
            }
            
            window.location.href = url;
        });
    }

    document.getElementById('saveDbBtn').addEventListener('click', function(ev){
      ev.preventDefault();
      const name = document.getElementById('profileName').value.trim();
      const description = document.getElementById('profileDescription').value.trim();
      saveProfileDB(name, description);
    });

    document.getElementById('downloadBtn').addEventListener('click', function(ev){
      ev.preventDefault();
      const name = document.getElementById('profileName').value.trim();
      const description = document.getElementById('profileDescription').value.trim();
      downloadJson(name, description);
    });

    const params = new URLSearchParams(window.location.search);
    const loadId = params.get('load_profile_id') || params.get('load_profile') || params.get('id');
    if (loadId) {
      setTimeout(function(){ loadProfileById(loadId, true); }, 300);
    }
  });
})();
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
