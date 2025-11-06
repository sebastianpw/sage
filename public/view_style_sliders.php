<?php
// public/view_style_sliders.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Visual Style Slider Panel";

// fetch all axes
$stmt = $pdo->prepare("SELECT id, axis_name, pole_left, pole_right, notes FROM design_axes ORDER BY id ASC");
$stmt->execute();
$axes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<link rel="stylesheet" href="css/toast.css">
<style>
/* Minimal, mobile-first slider view styling */
.container-small { max-width:1100px; margin:0 auto; padding:16px; }
.header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
.header h1 { margin:0; font-size:1.1rem; font-weight:600; }
.header .sub { color:#666; font-size:0.9rem; }

.controls { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; align-items:center; }
.input-text { flex:1 1 20px; min-width:160px; padding:8px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); background:#fff; }
.btn { padding:8px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); background:#fff; color:#222; cursor:pointer; font-size:0.95rem; }
.btn-prim { background:#0d6efd; color:#fff; border-color:#0d6efd; }
.btn-prim:disabled { opacity:0.6; cursor:default; }
.btn-ghost { background:transparent; border:1px solid rgba(0,0,0,0.06); }
.small-muted { color:#666; font-size:0.86rem; }

/* axes list */
.axes { display:flex; flex-direction:column; gap:10px; }
.axis-row { display:flex; gap:10px; align-items:center; padding:10px; border-radius:8px; background:#fff; border:1px solid rgba(0,0,0,0.04); }
.axis-left { min-width:110px; font-size:0.92rem; color:#333; font-weight:600; }
.axis-range {
  flex: 1;
  display: flex;
  flex-wrap: wrap; /* allow items to wrap */
  align-items: center;
  gap: 8px;
}

.axis-inputnum {
  flex-basis: 100%; /* force new line */
  width: 100%;      /* full width of new line */
  max-width: 76px;  /* optional: max width */
}

/* responsive: stack axis details on narrow screens */
@media (max-width:720px) {
  .axis-row { flex-direction:column; align-items:stretch; }
  .axis-left { display:block; margin-bottom:6px; }
  .controls { flex-direction:column; align-items:stretch; }
  .input-text { width:97%; }
}

/* result area */
.result-area { margin-top:12px; }
.result-pre { background:#f7f7f7; padding:10px; border-radius:6px; font-family:monospace; font-size:0.92rem; white-space:pre-wrap; max-height:260px; overflow:auto; }
</style>

<div class="container-small">
  <div class="header">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <div class="sub">Dial the visual axes — save profiles, download JSON, or load existing profiles.</div>

<div style="display:flex;gap:8px;align-items:center;">
<a class="btn btn-outline-primary btn-sm" href="view_style_profiles_admin.php">&larr; Open Sliders Admin</a>
<a class="btn btn-sm" href="view_style_sliders.php?create_new=1">Create new</a>
</div>

  </div>

  <div class="controls">
    <input id="profileName" class="input-text" placeholder="Profile name (optional)" />
    <input type="hidden" id="currentProfileId" value="">

    <button id="saveDbBtn" class="btn btn-prim">Save (DB)</button>
    <button style="display:none;" id="saveBtn" class="btn">Save (file)</button>
    <button id="downloadBtn" class="btn btn-ghost">Download JSON</button>

    <div style="display:flex; gap:8px; align-items:center;">
      <select id="savedProfilesSelect" style="min-width:220px; max-width: 280px; padding:8px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); background:#fff;"></select>
      <button id="loadProfileBtn" class="btn">Load</button>
    </div>
  </div>

  <div class="axes" id="axesList">
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
  </div>

  <div class="result-area" id="resultArea"></div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
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

  function collectPayload(profileName) {
    const axes = [];
    document.querySelectorAll('.axis-slider').forEach(function(slider){
      const axisId = parseInt(slider.dataset.axisId, 10);
      const axisName = slider.dataset.axisName;
      const poleLeft = slider.dataset.poleLeft;
      const poleRight = slider.dataset.poleRight;
      const value = parseInt(slider.value, 10);
      axes.push({
        id: axisId,
        key: axisName,
        pole_left: poleLeft,
        pole_right: poleRight,
        value: value
      });
    });
    return {
      profile_name: profileName || null,
      created_at: new Date().toISOString(),
      axes: axes
    };
  }

  function populateProfilesDropdown() {
    fetch('style_profiles_api.php?action=list')
      .then(r => r.json())
      .then(data => {
        const sel = document.getElementById('savedProfilesSelect');
        sel.innerHTML = '';
        if (data && data.status === 'ok' && data.profiles && data.profiles.length) {
          data.profiles.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = (p.profile_name ? p.profile_name + ' — ' : '') + p.created_at + ' (id:' + p.id + ')';
            sel.appendChild(opt);
          });
          sel.disabled = false;
        } else {
          const opt = document.createElement('option');
          opt.value = '';
          opt.textContent = '-- no saved profiles --';
          sel.appendChild(opt);
          sel.disabled = true;
        }
      })
      .catch(err => {
        console.error('list profiles error', err);
        showToast('Could not list profiles', 'error');
      });
  }

  function applyProfileToUI(payload) {
    if (!payload || !payload.axes) return;
    document.getElementById('currentProfileId').value = payload.id ? String(payload.id) : '';
    document.getElementById('profileName').value = payload.profile_name || '';
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
          if (showToastOnSuccess) showToast('Profile loaded: ' + (data.payload.profile_name || 'id:' + id), 'success');
        } else {
          showToast('Could not load profile: ' + (data && data.message ? data.message : 'unknown'), 'error');
          console.error('load error', data);
        }
      })
      .catch(err => {
        console.error('load profile failed', err);
        showToast('Network or parse error while loading profile: ' + (err.message || ''), 'error');
      });
  }

  function saveProfileFile(profileName) {
    const payload = collectPayload(profileName);
    showToast('Saving profile file…', 'info');
    fetch('style_profiles_api.php?action=save_json', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.status === 'ok') {
        showToast('Profile saved to file: ' + (data.filename || 'saved'), 'success');
        document.getElementById('resultArea').innerHTML = '<div class="result-pre"><strong>Saved file:</strong> ' + (data.filename || '') + '\n\n' + JSON.stringify(data.payload, null, 2) + '</div>';
      } else {
        showToast('Error saving file', 'error');
        console.error('save file error', data);
      }
    })
    .catch(err => {
      console.error('save file network error', err);
      showToast('Network error saving file', 'error');
    });
  }

  // save to DB (will update when currentProfileId is set)
  function saveProfileDB(profileName) {
    const payload = collectPayload(profileName);
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
        populateProfilesDropdown();
        document.getElementById('resultArea').innerHTML = '<div class="result-pre"><strong>Saved profile id:</strong> ' + data.profile_id + '\n\n' + JSON.stringify(data.payload, null, 2) + '</div>';
      } else {
        showToast('Save (DB) failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
        console.error('save db error', data);
      }
    }).catch(err => {
      console.error('save db network error', err);
      showToast('Network error saving to DB', 'error');
    });
  }

  function downloadJson(profileName) {
    const payload = collectPayload(profileName);
    const filename = (profileName ? profileName.replace(/\s+/g,'_') : 'style_profile') + '_' + (new Date()).toISOString().replace(/[:.]/g,'-') + '.json';
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
    populateProfilesDropdown();

    document.getElementById('saveBtn').addEventListener('click', function(ev){
      ev.preventDefault();
      const name = document.getElementById('profileName').value.trim();
      saveProfileFile(name);
    });

    document.getElementById('saveDbBtn').addEventListener('click', function(ev){
      ev.preventDefault();
      const name = document.getElementById('profileName').value.trim();
      saveProfileDB(name);
    });

    document.getElementById('downloadBtn').addEventListener('click', function(ev){
      ev.preventDefault();
      const name = document.getElementById('profileName').value.trim();
      downloadJson(name);
    });

    document.getElementById('loadProfileBtn').addEventListener('click', function(ev){
      ev.preventDefault();
      const sel = document.getElementById('savedProfilesSelect');
      const id = sel.value;
      if (!id) { showToast('No profile selected', 'info'); return; }
      loadProfileById(id, true);
    });

    // Auto-load via URL param ?load_profile_id=ID
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
