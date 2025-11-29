<?php
// public/view_style_profiles_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Style Profiles Admin";
ob_start();

// fetch profiles
try {
    
    
    
    
    
    $stmt = $pdo->query("
        SELECT id, IFNULL(name, '') AS name, IFNULL(description, '') AS description, 
               IFNULL(axis_group, 'default') AS axis_group, IFNULL(filename, '') AS filename, created_at
        FROM style_profiles
        ORDER BY axis_group ASC, created_at DESC
        LIMIT 500
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    


    
    
    
    
} catch (Exception $e) {
    $rows = [];
    if (isset($fileLogger) && is_callable([$fileLogger, 'error'])) {
        $fileLogger->error('view_style_profiles_admin fetch error: '.$e->getMessage());
    }
}
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* Styles updated to use base theme CSS variables (dark/light aware)
   Do not change behavior in JS / markup; only visual variables here. */

.admin-wrap { max-width:1100px; margin:0 auto; padding:18px; color: var(--text); }
.admin-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.admin-head h2 { margin:0; font-weight:600; font-size:1.15rem; color: var(--text); }

/* Use global .btn from base.css; only supply "outline" variants here so markup remains unchanged */
.btn-outline-primary { background: transparent; border: 1px solid var(--accent); color: var(--accent); }
.btn-outline-primary:hover { background: rgba(59,130,246,0.06); }
.btn-outline-secondary { background: transparent; border: 1px solid rgba(var(--muted-border-rgb), 0.18); color: var(--text); }
.btn-outline-secondary:hover { background: rgba(var(--muted-border-rgb), 0.03); }
.btn-outline-danger { background: transparent; border: 1px solid var(--red); color: var(--red); }
.btn-outline-danger:hover { background: rgba(218,54,51,0.06); }

/* fallback small sizing if base doesn't exist */
.btn-sm { padding:6px 8px; font-size:0.85rem; border-radius:6px; }

/* NEW Card List Layout - inspired by generator_admin.php */
.profile-list-container {
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 12px;
    box-shadow: var(--card-elevation);
    margin-top: 16px;
}
.profile-item {
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
.profile-item:last-child { margin-bottom: 0; }
.profile-item:hover { border-color: var(--accent); }
.profile-info { flex: 1; min-width: 0; }
.profile-name { font-weight: 600; font-size: 1rem; color: var(--text); margin-bottom: 4px; }
.profile-meta { font-size: 0.85rem; color: var(--text-muted); }
.profile-meta span { margin-right: 12px; display: inline-block; }
.profile-meta .description { display: block; margin-top: 4px; font-style: italic; }
.actions { display:flex; gap:8px; flex-wrap:wrap; }
.empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
/* End of New Card List Layout */

.small-muted { color: var(--text-muted); font-size:0.85rem; }

/* Card */
.card { background: var(--card); border: 1px solid rgba(var(--muted-border-rgb),0.06); border-radius:8px; padding:16px; box-shadow: var(--card-elevation); color: var(--text); }
.card h3 { margin:0 0 12px 0; font-size:1rem; font-weight:600; }

/* Inputs inside the card, keep consistent with base form styles */
.card select, .card input {
    width:100%;
    padding:8px 10px;
    border-radius:6px;
    border: 1px solid rgba(var(--muted-border-rgb), 0.12);
    background: var(--bg);
    color: var(--text);
}

/* Responsive transforms for new card list */
@media (max-width: 768px) {
    .profile-item { flex-direction: column; align-items: flex-start; }
    .actions { width: 100%; justify-content: flex-start; }
    .actions .btn { flex-grow: 1; text-align: center; }
}

@media (max-width: 480px) {
    .profile-meta span { display: block; margin: 4px 0; }
}


/* Loader dot — theme-aware */
.loader-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--blue-light-bg);
    position: relative;
    box-shadow: inset 0 0 0 3px rgba(59,130,246,0.06);
    animation: loader-scale 1s infinite;
}
@keyframes loader-scale {
    0% { transform: scale(1); opacity: 0.6; }
    50% { transform: scale(1.4); opacity: 1; }
    100% { transform: scale(1); opacity: 0.6; }
}

/* Modal — use variables for backgrounds / borders */
.modal-overlay { position:fixed; inset:0; background: rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; z-index:120000; padding:12px; }
.modal-card { width:100%; max-width:900px; background: var(--card); border-radius:10px; box-shadow: 0 8px 30px rgba(2,6,23,0.35); overflow:hidden; display:flex; flex-direction:column; max-height:90vh; color:var(--text); border:1px solid rgba(var(--muted-border-rgb),0.06); }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid rgba(var(--muted-border-rgb),0.06); }
.modal-body { padding:12px 16px; overflow:auto; font-family:monospace; font-size:0.92rem; background: var(--bg); color: var(--text); }
.modal-footer { padding:10px 16px; border-top:1px solid rgba(var(--muted-border-rgb),0.06); display:flex; gap:8px; justify-content:flex-end; background: var(--bg); }

/* Pre blocks inside modal: make them readable in both themes */
.modal-body pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    margin: 0;
    padding: 8px;
    background: rgba(var(--muted-border-rgb), 0.03);
    color: var(--text);
    border-radius: 6px;
    overflow: auto;
    font-family: system-ui, monospace;
}

/* Buttons inside modal: keep them compact but theme-aware */
.modal-header .btn, .modal-footer .btn { margin-left: 6px; }

/* tiny helpers to match previous visual sizes */
.admin-wrap .card h3, .admin-wrap .small-muted { color: var(--text); }

/* ensure any remaining hard-coded inline whites are visually corrected */
a, button { color: inherit; }





</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2 style="margin-left:45px;">Style Profiles</h2>
    <div style="display:flex;gap:8px;align-items:center;">
      <a class="btn btn-outline-primary btn-sm" href="view_style_sliders.php">&larr; Open Sliders</a>
      <a class="btn btn-outline-secondary btn-sm" href="view_design_axes_admin.php">Design Axes</a>
      <a class="btn btn-sm" href="view_style_sliders.php?create_new=1">Create new</a>
    </div>
  </div>

  <p class="small-muted">Manage saved style profiles. Preview JSON, download the saved file, open the profile in sliders, or delete it.</p>

  <!-- Generator Config Settings -->
  <div class="card" style="padding:16px; border-radius:8px; margin-bottom:16px;">
    <h3 style="margin:0 0 12px 0; font-size:1rem; font-weight:600;">AI Generator Configuration</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:12px;">
      <div>
        <label style="display:block; font-weight:600; margin-bottom:6px; font-size:0.9rem;">Axes Translation Config:</label>
        <select id="axesGeneratorSelect" style="width:100%; padding:8px 10px; border-radius:6px; border:1px solid rgba(var(--muted-border-rgb),0.12);">
          <option value="">Loading...</option>
        </select>
      </div>
      <div>
        <label style="display:block; font-weight:600; margin-bottom:6px; font-size:0.9rem;">Prompt Polish Config:</label>
        <select id="polishGeneratorSelect" style="width:100%; padding:8px 10px; border-radius:6px; border:1px solid rgba(var(--muted-border-rgb),0.12);">
          <option value="">Loading...</option>
        </select>
      </div>
    </div>
    <p class="small-muted" style="margin:8px 0 0 0;">These configs control how style profiles are converted to AI prompts. Changes save automatically.</p>
  </div>

  <div class="profile-list-container">
      <?php if (empty($rows)): ?>
          <div class="empty-state">No profiles saved yet.</div>
      <?php else: ?>
          <?php foreach ($rows as $r): ?>
              <div class="profile-item" data-profile-id="<?= (int)$r['id'] ?>">
                  <div class="profile-info">
                      <div class="profile-name"><?= htmlspecialchars($r['name'] ?: '(untitled)') ?></div>
                      <div class="profile-meta">
                          <span><strong>ID:</strong> <?= (int)$r['id'] ?></span>
                          <span><strong>File:</strong> <?= htmlspecialchars($r['filename'] ?: 'N/A') ?></span>
                          <span><strong>Created:</strong> <?= htmlspecialchars($r['created_at']) ?></span>
                          <?php if (!empty($r['description'])): ?>
                            <div class="description"><?= htmlspecialchars($r['description']) ?></div>
                          <?php endif; ?>
                      </div>
                  </div>
                  <div class="actions">
                      <a class="btn btn-sm btn-outline-primary" href="style_profiles_api.php?action=download&id=<?= (int)$r['id'] ?>">Download</a>
                      <button class="btn btn-sm btn-outline-primary btn-open-sliders" data-id="<?= (int)$r['id'] ?>">Open</button>
                      <button class="btn btn-sm btn-outline-primary btn-convert" data-id="<?= (int)$r['id'] ?>">Convert</button>
                      <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= (int)$r['id'] ?>">Delete</button>
                      <button class="btn btn-sm btn-outline-success btn-preview" data-id="<?= (int)$r['id'] ?>">Preview</button>
                  </div>
              </div>
          <?php endforeach; ?>
      <?php endif; ?>
  </div>
</div>

<!-- preview modal -->
<div id="profilePreviewModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-card" role="document">
    <div class="modal-header">
      <strong id="modalTitle">Profile Preview</strong>
      <div style="display:flex; gap:8px; align-items:center;">
        <button id="modalCopyBtn" class="btn btn-sm btn-outline-secondary" type="button">Copy JSON</button>
        <a id="modalDownload" class="btn btn-sm btn-outline-secondary" href="#" download>Download</a>
        <button id="modalConvertBtn" class="btn btn-sm btn-outline-primary" type="button">Convert to Prompt</button>
        <button id="modalCloseBtn" class="btn btn-sm">Close</button>
      </div>
    </div>

    <div class="modal-body">
      <!-- MOVED: Conversion result area is now at the top for better visibility -->
      <div id="convertedArea" style="display:none; margin-bottom:12px;">
        <div style="display:flex; gap:8px; margin-top:8px; margin-bottom: 8px;">
          <button id="convertCopyBtn" class="btn btn-sm btn-outline-secondary">Copy Prompt</button>
          <a id="convertDownload" class="btn btn-sm btn-outline-secondary" href="#" download>Download .txt</a>
        </div>
        <strong style="display:block;margin-bottom:6px;">Converted Prompt</strong>
        <pre id="convertedPrompt" style="white-space:pre-wrap; word-wrap:break-word; margin:0; padding:8px; border-radius:6px; font-family:system-ui,monospace;"></pre>
      </div>
      
      <div id="convertStatus" style="display:none; margin-bottom:12px;" class="small-muted">Convertingâ€¦</div>
      
      <!-- JSON preview is now second -->
      <div style="margin-bottom:12px;">
        <strong style="display:block;margin-bottom:6px;">Profile JSON</strong>
        <pre id="profilePreviewJson" style="white-space:pre-wrap; word-wrap:break-word; margin:0; padding:8px; border-radius:6px;"></pre>
      </div>

    </div>

    <div class="modal-footer">
      <button id="modalCloseBtn2" class="btn btn-sm">Close</button>
    </div>
  </div>
</div>

<script src="js/toast.js"></script>
<script>
/* (same JS as before) */
(function(){
  function showToast(msg, type) {
    if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
      const mapType = (type === 'danger' || type === 'error') ? 'error' : (type === 'warning' ? 'info' : (type || 'info'));
      Toast.show(msg, mapType);
    } else {
      console.log('[toast]', msg);
    }
  }

  // Load generator configs for dropdowns
  function loadGeneratorConfigs() {
    fetch('style_profiles_api.php?action=get_generator_configs')
      .then(r => r.json())
      .then(data => {
        if (data && data.status === 'ok' && data.configs) {
          const axesSel = document.getElementById('axesGeneratorSelect');
          const polishSel = document.getElementById('polishGeneratorSelect');
          
          axesSel.innerHTML = '<option value="">-- Select Config --</option>';
          polishSel.innerHTML = '<option value="">-- Select Config --</option>';
          
          data.configs.forEach(cfg => {
            const opt1 = document.createElement('option');
            opt1.value = cfg.config_id;
            opt1.textContent = cfg.title;
            axesSel.appendChild(opt1);
            
            const opt2 = document.createElement('option');
            opt2.value = cfg.config_id;
            opt2.textContent = cfg.title;
            polishSel.appendChild(opt2);
          });
          
          // Load current config values
          loadCurrentConfig();
        }
      })
      .catch(err => console.error('Failed to load generator configs', err));
  }

  function loadCurrentConfig() {
    fetch('style_profiles_api.php?action=get_config')
      .then(r => r.json())
      .then(data => {
        if (data && data.status === 'ok' && data.config) {
          if (data.config.axes_generator_config_id) {
            document.getElementById('axesGeneratorSelect').value = data.config.axes_generator_config_id;
          }
          if (data.config.polish_generator_config_id) {
            document.getElementById('polishGeneratorSelect').value = data.config.polish_generator_config_id;
          }
        }
      })
      .catch(err => console.error('Failed to load config', err));
  }

  function saveConfigValue(key, value) {
    const payload = {};
    payload[key] = value;
    
    fetch('style_profiles_api.php?action=save_config', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    }).then(r => r.json())
      .then(data => {
        if (data && data.status === 'ok') {
          showToast('Config saved', 'success');
        } else {
          showToast('Failed to save config', 'error');
        }
      })
      .catch(err => {
        console.error('Config save failed', err);
        showToast('Network error saving config', 'error');
      });
  }

  // Wire up config dropdowns for auto-save
  document.getElementById('axesGeneratorSelect').addEventListener('change', function(){
    saveConfigValue('axes_generator_config_id', this.value);
  });
  
  document.getElementById('polishGeneratorSelect').addEventListener('change', function(){
    saveConfigValue('polish_generator_config_id', this.value);
  });


function createEl(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
        if (k === 'class') el.className = v;
        else if (k === 'text') el.textContent = v;
        else el.setAttribute(k, v);
    });
    children.forEach(c => el.appendChild(c));
    return el;
}


  // Modal controls
  const modalOverlay = document.getElementById('profilePreviewModal');
  const modalJson = document.getElementById('profilePreviewJson');
  const modalTitle = document.getElementById('modalTitle');
  const modalDownload = document.getElementById('modalDownload');
  const modalCopyBtn = document.getElementById('modalCopyBtn');
  const modalConvertBtn = document.getElementById('modalConvertBtn');
  const closeButtons = Array.from(document.querySelectorAll('#modalCloseBtn, #modalCloseBtn2'));

  const convertedArea = document.getElementById('convertedArea');
  const convertedPromptEl = document.getElementById('convertedPrompt');
  const convertStatus = document.getElementById('convertStatus');
  const dot = createEl('div', { class: 'loader-dot' });
  const convtxt = createEl('div', { text: 'Converting...' });
  const convertCopyBtn = document.getElementById('convertCopyBtn');
  const convertDownload = document.getElementById('convertDownload');
  
  // NEW: Helper to setup copy/download actions for a converted prompt
  function setupConvertedResultActions(promptText, profilePayload) {
      convertCopyBtn.onclick = function(){
        const t = promptText || '';
        if (!t) return showToast('Nothing to copy', 'info');
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(t).then(function(){ showToast('Prompt copied', 'success'); }, function(){ showToast('Could not copy', 'error'); });
        } else {
          try { const ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); showToast('Prompt copied', 'success'); } catch(e){ showToast('Copy failed', 'error'); }
        }
      };
      
      const blob = new Blob([promptText], {type:'text/plain;charset=utf-8'});
      const url = URL.createObjectURL(blob);
      convertDownload.href = url;
      convertDownload.setAttribute('download', ((profilePayload.name || 'prompt') + '_' + (profilePayload.id || '') + '.txt').replace(/[^a-zA-Z0-9_\-\.]/g,'_'));
  }
  
  // NEW: Save converted result to the DB
  function saveConvertedResult(profileId, resultText) {
      fetch('style_profiles_api.php?action=save_convert_result', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ id: profileId, result: resultText })
      })
      .then(r => r.json())
      .then(data => {
          if(data.status !== 'ok') {
              console.error('Failed to save conversion result:', data.message);
              showToast('Could not save prompt to DB', 'warning');
          }
      })
      .catch(err => {
          console.error('Network error saving conversion result', err);
          showToast('Network error saving prompt', 'error');
      });
  }

  function showModal(payload) {
    modalTitle.textContent = (payload.name || 'Profile Preview') + ' â€” ' + (payload.created_at || '');
    modalJson.textContent = JSON.stringify(payload, null, 2);
    modalDownload.href = 'style_profiles_api.php?action=download&id=' + (payload.id || '');
    modalDownload.setAttribute('download', (payload.name || 'style_profile') + '_' + (payload.id || '') + '.json');

    convertedArea.style.display = 'none';
    convertedPromptEl.textContent = '';
    convertStatus.style.display = 'none';
    
    // NEW: Preload and show existing conversion result
    if (payload.convert_result) {
        convertedPromptEl.textContent = payload.convert_result;
        convertedArea.style.display = 'block';
        setupConvertedResultActions(payload.convert_result, payload);
    }

    modalCopyBtn.onclick = function(){
      const text = modalJson.textContent || '';
      if (!text) return showToast('Nothing to copy', 'info');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){ showToast('JSON copied', 'success'); }, function(){ showToast('Could not copy', 'error'); });
      } else {
        try { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); showToast('JSON copied', 'success'); } catch(e){ showToast('Copy failed', 'error'); }
      }
    };

    modalConvertBtn.onclick = function(){
      let parsed = null;
      try { parsed = JSON.parse(modalJson.textContent || '{}'); } catch(e){ showToast('Invalid JSON', 'error'); return; }
      convertProfile(parsed);
    };

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
    modalCopyBtn.onclick = null;
    modalConvertBtn.onclick = null;
  }

  closeButtons.forEach(btn => btn.addEventListener('click', hideModal));
  modalOverlay.addEventListener('click', function(e){ if (e.target === modalOverlay) hideModal(); });

  function showPreview(id) {
    if (!id) { showToast('Missing profile id', 'error'); return; }
    fetch('style_profiles_api.php?action=load&id=' + encodeURIComponent(id))
      .then(function(resp){
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        return resp.json();
      })
      .then(function(data){
        if (!data || data.status !== 'ok' || !data.payload) {
          showToast('Could not load profile', 'error');
          return;
        }
        showModal(data.payload);
      })
      .catch(function(err){
        console.error('preview load failed', err);
        showToast('Network error: ' + (err.message || ''), 'error');
      });
  }

  function convertProfile(profilePayload) {
    convertStatus.style.display = 'block';
    //convertStatus.textContent = 'Convertingâ€¦';
    convertStatus.textContent = '';
    convertStatus.appendChild(dot);
    convertStatus.appendChild(convtxt);
    convertedArea.style.display = 'none';
    convertedPromptEl.textContent = '';

    fetch('style_profiles_api.php?action=convert_proxy', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ profiles: [ profilePayload ] })
    }).then(function(resp){
      if (!resp.ok) {
        return resp.text().then(function(text){
          throw new Error('Proxy error: ' + resp.status + ' ' + text);
        });
      }
      return resp.json();
    }).then(function(json){
      let promptText = null;
      if (typeof json === 'string') {
        promptText = json;
      } else if (json.prompt) {
        promptText = json.prompt;
      } else if (json.payload && json.payload.prompt) {
        promptText = json.payload.prompt;
      } else if (json.result) {
        promptText = json.result;
      } else {
        promptText = JSON.stringify(json, null, 2);
      }

      convertedPromptEl.textContent = promptText;
      convertedArea.style.display = 'block';
      convertStatus.style.display = 'none';

      // Setup actions and save the result
      setupConvertedResultActions(promptText, profilePayload);
      if (profilePayload.id) {
          saveConvertedResult(profilePayload.id, promptText);
      }

    }).catch(function(err){
      console.error('convert failed', err);
      convertStatus.style.display = 'block';
      convertStatus.textContent = 'Conversion failed: ' + (err.message || 'unknown');
      showToast('Conversion failed: ' + (err.message || ''), 'error');
    });
  }

  function deleteProfile(id, rowEl) {
    if (!confirm('Delete profile id ' + id + '? This action cannot be undone.')) return;
    fetch('style_profiles_api.php?action=delete', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: id})
    }).then(function(resp){ return resp.json(); }).then(function(data){
      if (data && data.status === 'ok') {
        showToast('Deleted profile ' + id, 'success');
        if (rowEl && rowEl.parentNode) rowEl.parentNode.removeChild(rowEl);
      } else {
        showToast('Delete failed', 'error');
      }
    }).catch(function(err){ console.error('delete failed', err); showToast('Network error while deleting', 'error'); });
  }

  document.addEventListener('click', function(ev){
    const el = ev.target;
    if (el.matches('.btn-preview')) {
      const id = el.dataset.id; showPreview(id); return;
    }
    if (el.matches('.btn-delete')) {
      const id = el.dataset.id; const row = el.closest('[data-profile-id]'); deleteProfile(id, row); return;
    }
    if (el.matches('.btn-open-sliders')) {
      const id = el.dataset.id;
      window.open('view_style_sliders.php?load_profile_id=' + encodeURIComponent(id), '_self'); return;
    }
    if (el.matches('.btn-convert')) {
      const id = el.dataset.id;
      fetch('style_profiles_api.php?action=load&id=' + encodeURIComponent(id))
        .then(function(resp){ if (!resp.ok) throw new Error('HTTP ' + resp.status); return resp.json(); })
        .then(function(data){
          if (!data || data.status !== 'ok' || !data.payload) {
            showToast('Could not load profile', 'error'); return;
          }
          showModal(data.payload);
          setTimeout(function(){ convertProfile(data.payload); }, 500);
        }).catch(function(err){ console.error(err); showToast('Could not load: ' + (err.message||''), 'error'); });
      return;
    }
  }, false);

  // Initialize on load
  loadGeneratorConfigs();
})();
</script>
<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
