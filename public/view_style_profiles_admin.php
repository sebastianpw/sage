<?php
// public/view_style_profiles_admin.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Style Profiles Admin";
ob_start();

// fetch profiles (server-side list for initial render)
try {
    $stmt = $pdo->query("
        SELECT id, IFNULL(profile_name, '') AS profile_name, IFNULL(filename, '') AS filename, created_at
        FROM style_profiles
        ORDER BY created_at DESC
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

<link rel="stylesheet" href="css/toast.css">
<style>
/* keep your existing styles */
.admin-wrap { max-width:1100px; margin:0 auto; padding:18px; }
.admin-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.admin-head h2 { margin:0; font-weight:600; font-size:1.15rem; }
.btn { display:inline-block; padding:8px 10px; border-radius:6px; text-decoration:none; font-size:0.9rem; border:1px solid rgba(0,0,0,0.06); background:#fff; color:#222; cursor:pointer; }
.btn-outline-primary { background:transparent; border:1px solid #0d6efd; color:#0d6efd; }
.btn-outline-secondary { background:transparent; border:1px solid #6c757d; color:#6c757d; }
.btn-outline-danger { background:transparent; border:1px solid #dc3545; color:#dc3545; }
.btn-sm { padding:6px 8px; font-size:0.85rem; border-radius:6px; }
.table { width:100%; border-collapse:collapse; margin-top:12px; }
.table th, .table td { padding:10px 8px; border-bottom:1px solid rgba(0,0,0,0.06); text-align:left; vertical-align:middle; font-size:0.95rem; }
.table thead th { font-weight:600; font-size:0.9rem; color:#444; background:transparent; }
.actions { display:flex; gap:6px; flex-wrap:wrap; max-width: 300px; }
.small-muted { color:#666; font-size:0.85rem; }

/* preview modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; z-index:120000; padding:12px; }
.modal-card { width:100%; max-width:900px; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; max-height:90vh; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid rgba(0,0,0,0.06); }
.modal-body { padding:12px 16px; overflow:auto; font-family:monospace; font-size:0.92rem; background:#fbfbfb; color:#222; }
.modal-footer { padding:10px 16px; border-top:1px solid rgba(0,0,0,0.06); display:flex; gap:8px; justify-content:flex-end; }

@media (max-width:700px) {
  .table thead { display:none; }
  .table, .table tbody, .table tr, .table td { display:block; width:97%; }
  .table tr { margin-bottom:12px; border-radius:8px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.03); padding:10px; }
  .table td { padding:8px 10px; border:0; }
  .table td .label { display:block; font-weight:600; margin-bottom:6px; color:#333; }
  .actions { justify-content:flex-end; }
  .admin-head { gap:8px; }
}
</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2>Style Profiles Admin</h2>
    <div style="display:flex;gap:8px;align-items:center;">
      <a class="btn btn-outline-primary btn-sm" href="view_style_sliders.php">&larr; Open Sliders</a>
      <a class="btn btn-sm" href="view_style_sliders.php?create_new=1">Create new</a>
    </div>
  </div>

  <p class="small-muted">Manage saved style profiles. Preview JSON, download the saved file, open the profile in sliders, or delete it. Mobile-first and minimal.</p>

  <div style="overflow:auto;">
    <table class="table" role="table" aria-label="Style profiles">
      <thead>
        <tr>
          <th style="width:8%">ID</th>
          <th style="width:34%">Profile</th>
          <th style="width:28%">Filename</th>
          <th style="width:18%">Created</th>
          <th style="width:12%">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="5" class="small-muted">No profiles saved yet.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr data-profile-id="<?= (int)$r['id'] ?>">
              <td><div class="label"><?= (int)$r['id'] ?></div></td>
              <td>
                <div class="label"><?= htmlspecialchars($r['profile_name'] ?: '(untitled)') ?></div>
                <div class="small-muted"><?= htmlspecialchars($r['profile_name'] ? '' : 'no name') ?></div>
              </td>
              <td>
                <div><?= htmlspecialchars($r['filename']) ?></div>
              </td>
              <td>
                <div><?= htmlspecialchars($r['created_at']) ?></div>
              </td>
              <td>
                <div class="actions">
                  <button class="btn btn-sm btn-outline-secondary btn-preview" data-id="<?= (int)$r['id'] ?>">Preview</button>
                  <a class="btn btn-sm btn-outline-secondary" href="style_profiles_api.php?action=download&id=<?= (int)$r['id'] ?>">Download</a>
                  <button class="btn btn-sm btn-outline-primary btn-open-sliders" data-id="<?= (int)$r['id'] ?>">Open in Sliders</button>
                  <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= (int)$r['id'] ?>">Delete</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
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
        <button id="modalCloseBtn" class="btn btn-sm">Close</button>
      </div>
    </div>
    <div class="modal-body">
      <pre id="profilePreviewJson" style="white-space:pre-wrap; word-wrap:break-word; margin:0; padding:0; background:transparent;"></pre>
    </div>
    <div class="modal-footer">
      <button id="modalCloseBtn2" class="btn btn-sm">Close</button>
    </div>
  </div>
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

  // modal controls
  const modalOverlay = document.getElementById('profilePreviewModal');
  const modalJson = document.getElementById('profilePreviewJson');
  const modalTitle = document.getElementById('modalTitle');
  const modalDownload = document.getElementById('modalDownload');
  const modalCopyBtn = document.getElementById('modalCopyBtn');
  const closeButtons = Array.from(document.querySelectorAll('#modalCloseBtn, #modalCloseBtn2'));

  function showModal(payload) {
    modalTitle.textContent = (payload.profile_name || 'Profile Preview') + ' — ' + (payload.created_at || '');
    modalJson.textContent = JSON.stringify(payload, null, 2);
    modalDownload.href = 'style_profiles_api.php?action=download&id=' + (payload.id || '');
    modalDownload.setAttribute('download', (payload.profile_name || 'style_profile') + '_' + (payload.id || '') + '.json');

    modalCopyBtn.onclick = function(){
      const text = modalJson.textContent || '';
      if (!text) return showToast('Nothing to copy', 'info');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){
          showToast('JSON copied to clipboard', 'success');
        }, function(err){
          console.error('clipboard write failed', err);
          showToast('Could not copy to clipboard', 'error');
        });
      } else {
        try {
          const ta = document.createElement('textarea');
          ta.value = text;
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          ta.remove();
          showToast('JSON copied to clipboard', 'success');
        } catch (e) {
          console.error('fallback copy failed', e);
          showToast('Copy not supported', 'error');
        }
      }
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
  }

  closeButtons.forEach(btn => btn.addEventListener('click', hideModal));
  modalOverlay.addEventListener('click', function(e){
    if (e.target === modalOverlay) hideModal();
  });

  // fetch & show profile preview
  function showPreview(id) {
    if (!id) { showToast('Missing profile id', 'error'); return; }
    fetch('style_profiles_api.php?action=load&id=' + encodeURIComponent(id))
      .then(function(resp){
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        return resp.json();
      })
      .then(function(data){
        if (!data || data.status !== 'ok' || !data.payload) {
          const msg = (data && data.message) ? data.message : 'Invalid profile data';
          showToast('Could not load profile: ' + msg, 'error');
          return;
        }
        showModal(data.payload);
      })
      .catch(function(err){
        console.error('preview load failed', err);
        showToast('Network or parse error while loading profile: ' + (err.message || ''), 'error');
      });
  }

  // delete profile
  function deleteProfile(id, rowEl) {
    if (!confirm('Delete profile id ' + id + '? This action cannot be undone.')) return;
    fetch('style_profiles_api.php?action=delete', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: id})
    }).then(function(resp){
      return resp.json();
    }).then(function(data){
      if (data && data.status === 'ok') {
        showToast('Deleted profile ' + id, 'success');
        if (rowEl && rowEl.parentNode) rowEl.parentNode.removeChild(rowEl);
      } else {
        showToast('Delete failed: ' + (data && data.message ? data.message : 'unknown'), 'error');
      }
    }).catch(function(err){
      console.error('delete failed', err);
      showToast('Network error while deleting profile', 'error');
    });
  }

  // event delegation
  document.addEventListener('click', function(ev){
    const el = ev.target;
    if (el.matches('.btn-preview')) {
      const id = el.dataset.id;
      showPreview(id);
      return;
    }
    if (el.matches('.btn-delete')) {
      const id = el.dataset.id;
      const row = el.closest('tr[data-profile-id]');
      deleteProfile(id, row);
      return;
    }
    if (el.matches('.btn-open-sliders')) {
      const id = el.dataset.id;
      const url = 'view_style_sliders.php?load_profile_id=' + encodeURIComponent(id);
      window.open(url, '_self');
      return;
    }
  }, false);

})();
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
