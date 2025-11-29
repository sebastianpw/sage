<?php
// public/view_db_migration_history.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$dbname = $spw->getDbName();

$pageTitle = "Migration History";
ob_start();

// Fetch migration history
$history = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM `migration_history`
        ORDER BY executed_at DESC
        LIMIT 100
    ");
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
    $history = [];
}
?>

<link rel="stylesheet" href="css/toast.css">
<style>
.admin-wrap { max-width:1200px; margin:0 auto; padding:18px; }
.admin-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.admin-head h2 { margin:0; font-weight:600; font-size:1.15rem; }
.btn { display:inline-block; padding:8px 10px; border-radius:6px; text-decoration:none; font-size:0.9rem; border:1px solid rgba(0,0,0,0.06); background:#fff; color:#222; cursor:pointer; }
.btn-outline-primary { background:transparent; border:1px solid #0d6efd; color:#0d6efd; }
.btn-outline-secondary { background:transparent; border:1px solid #6c757d; color:#6c757d; }
.btn-sm { padding:6px 8px; font-size:0.85rem; border-radius:6px; }
.table { width:100%; border-collapse:collapse; margin-top:12px; }
.table th, .table td { padding:10px 8px; border-bottom:1px solid rgba(0,0,0,0.06); text-align:left; vertical-align:middle; font-size:0.95rem; }
.table thead th { font-weight:600; font-size:0.9rem; color:#444; background:transparent; }
.small-muted { color:#666; font-size:0.85rem; }
.badge { display:inline-block; padding:4px 8px; border-radius:4px; font-size:0.8rem; font-weight:500; }
.badge-success { background:#d1e7dd; color:#0f5132; }
.badge-danger { background:#f8d7da; color:#842029; }

.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; z-index:120000; padding:12px; }
.modal-card { width:100%; max-width:900px; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; max-height:90vh; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid rgba(0,0,0,0.06); }
.modal-body { padding:12px 16px; overflow:auto; font-family:monospace; font-size:0.92rem; background:#fbfbfb; color:#222; max-height:70vh; }
.modal-footer { padding:10px 16px; border-top:1px solid rgba(0,0,0,0.06); display:flex; gap:8px; justify-content:flex-end; }

@media (max-width:700px) {
  .table thead { display:none; }
  .table, .table tbody, .table tr, .table td { display:block; width:97%; }
  .table tr { margin-bottom:12px; border-radius:8px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.03); padding:10px; }
  .table td { padding:8px 10px; border:0; }
  .table td .label { display:block; font-weight:600; margin-bottom:6px; color:#333; }
}
</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2>Migration History</h2>
    <div style="display:flex;gap:8px;align-items:center;">
      <a class="btn btn-outline-secondary btn-sm" href="view_db_migration_admin.php">&larr; Migration Manager</a>
    </div>
  </div>

  <p class="small-muted">
    Viewing migration history for database: <strong><?= htmlspecialchars($dbname) ?></strong>
  </p>

  <div style="overflow:auto;">
    <table class="table" role="table" aria-label="Migration history">
      <thead>
        <tr>
          <th style="width:8%">ID</th>
          <th style="width:20%">Executed At</th>
          <th style="width:15%">Type</th>
          <th style="width:12%">Statements</th>
          <th style="width:10%">Status</th>
          <th style="width:15%">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr><td colspan="6" class="small-muted">No migration history found. The migration history table may not exist yet.</td></tr>
        <?php else: ?>
          <?php foreach ($history as $row): ?>
            <tr>
              <td>
                <div class="label">ID</div>
                <?= (int)$row['id'] ?>
              </td>
              <td>
                <div class="label">Executed At</div>
                <?= htmlspecialchars($row['executed_at']) ?>
              </td>
              <td>
                <div class="label">Type</div>
                <?= htmlspecialchars($row['migration_type'] ?? 'unknown') ?>
              </td>
              <td>
                <div class="label">Statements</div>
                <?= (int)($row['statements_count'] ?? 0) ?>
              </td>
              <td>
                <div class="label">Status</div>
                <span class="badge badge-<?= $row['success'] ? 'success' : 'danger' ?>">
                  <?= $row['success'] ? 'Success' : 'Failed' ?>
                </span>
              </td>
              <td>
                <div class="label">Actions</div>
                <button class="btn btn-sm btn-outline-primary btn-view-log" 
                        data-id="<?= (int)$row['id'] ?>"
                        data-log="<?= htmlspecialchars($row['log_data'] ?? '{}') ?>">
                  View Log
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Log Modal -->
<div id="logModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-card" role="document">
    <div class="modal-header">
      <strong id="modalTitle">Migration Log</strong>
      <button id="modalCloseBtn" class="btn btn-sm">Close</button>
    </div>
    <div class="modal-body">
      <pre id="logContent" style="white-space:pre-wrap; word-wrap:break-word; margin:0;"></pre>
    </div>
    <div class="modal-footer">
      <button id="modalCloseBtn2" class="btn btn-sm">Close</button>
    </div>
  </div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
  const modal = document.getElementById('logModal');
  const logContent = document.getElementById('logContent');
  const modalTitle = document.getElementById('modalTitle');
  const closeButtons = Array.from(document.querySelectorAll('#modalCloseBtn, #modalCloseBtn2'));

  function showModal(id, logData) {
    modalTitle.textContent = 'Migration Log #' + id;
    
    try {
      const parsed = JSON.parse(logData);
      logContent.textContent = JSON.stringify(parsed, null, 2);
    } catch (e) {
      logContent.textContent = logData;
    }
    
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  }

  function hideModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  }

  closeButtons.forEach(btn => btn.addEventListener('click', hideModal));
  modal.addEventListener('click', function(e) {
    if (e.target === modal) hideModal();
  });

  document.addEventListener('click', function(e) {
    if (e.target.matches('.btn-view-log')) {
      const id = e.target.dataset.id;
      const log = e.target.dataset.log;
      showModal(id, log);
    }
  });
})();
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>
