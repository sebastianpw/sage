<?php
// public/view_testing.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Testing Interface - Style Preview";
ob_start();
?>

<link rel="stylesheet" href="css/toast.css">
<style>
.testing-wrap { max-width:1100px; margin:0 auto; padding:18px; }
.testing-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.testing-head h2 { margin:0; font-weight:600; font-size:1.15rem; }
.btn { display:inline-block; padding:8px 10px; border-radius:6px; text-decoration:none; font-size:0.9rem; border:1px solid rgba(0,0,0,0.06); background:#fff; color:#222; cursor:pointer; }
.btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
.btn-outline-primary { background:transparent; border:1px solid #0d6efd; color:#0d6efd; }
.btn-outline-secondary { background:transparent; border:1px solid #6c757d; color:#6c757d; }
.btn-outline-danger { background:transparent; border:1px solid #dc3545; color:#dc3545; }
.btn-success { background:#198754; color:#fff; border-color:#198754; }
.btn-sm { padding:6px 8px; font-size:0.85rem; border-radius:6px; }
.card { background:#fff; border-radius:8px; border:1px solid rgba(0,0,0,0.06); margin-bottom:16px; }
.card-header { padding:12px 16px; border-bottom:1px solid rgba(0,0,0,0.06); font-weight:600; }
.card-body { padding:16px; }
.table { width:100%; border-collapse:collapse; margin-top:12px; }
.table th, .table td { padding:10px 8px; border-bottom:1px solid rgba(0,0,0,0.06); text-align:left; vertical-align:middle; font-size:0.95rem; }
.table thead th { font-weight:600; font-size:0.9rem; color:#444; background:transparent; }
.actions { display:flex; gap:6px; flex-wrap:wrap; max-width: 300px; }
.small-muted { color:#666; font-size:0.85rem; }
.grid { display:grid; gap:12px; }
.grid-2 { grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); }
.grid-3 { grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); }
.form-group { margin-bottom:12px; }
.form-label { display:block; font-weight:600; margin-bottom:6px; font-size:0.9rem; }
.form-control { width:100%; padding:8px 10px; border-radius:6px; border:1px solid rgba(0,0,0,0.1); font-size:0.9rem; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; z-index:120000; padding:12px; }
.modal-card { width:100%; max-width:600px; background:#fff; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; max-height:90vh; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid rgba(0,0,0,0.06); }
.modal-body { padding:16px; overflow:auto; }
.modal-footer { padding:12px 16px; border-top:1px solid rgba(0,0,0,0.06); display:flex; gap:8px; justify-content:flex-end; }
.demo-item { background:#f8f9fa; padding:12px; border-radius:6px; border-left:4px solid #0d6efd; }
.demo-image { width:100%; height:120px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius:4px; display:flex; align-items:center; justify-content:center; color:white; font-weight:600; }

@media (max-width:700px) {
  .table thead { display:none; }
  .table, .table tbody, .table tr, .table td { display:block; width:97%; }
  .table tr { margin-bottom:12px; border-radius:8px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.03); padding:10px; }
  .table td { padding:8px 10px; border:0; }
  .table td .label { display:block; font-weight:600; margin-bottom:6px; color:#333; }
  .actions { justify-content:flex-end; }
  .testing-head { gap:8px; }
}
</style>

<div class="testing-wrap">
  <div class="testing-head">
    <h2>Testing Interface - Style Preview</h2>
    <div style="display:flex;gap:8px;align-items:center;">
      <button class="btn btn-outline-secondary btn-sm" onclick="showDemoModal()">Open Demo Modal</button>
      <button class="btn btn-primary btn-sm" onclick="showSuccessToast()">Test Toast</button>
    </div>
  </div>

  <p class="small-muted">This is a testing interface with the same styling as the admin panel. Use this to test styles, layouts, and basic interactions.</p>

  <!-- Demo Cards Section -->
  <div class="grid grid-2" style="margin-bottom: 20px;">
    <div class="card">
      <div class="card-header">Demo Card 1 - Form Elements</div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Text Input</label>
          <input type="text" class="form-control" placeholder="Enter some text...">
        </div>
        <div class="form-group">
          <label class="form-label">Select Dropdown</label>
          <select class="form-control">
            <option>Option 1</option>
            <option>Option 2</option>
            <option>Option 3</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Textarea</label>
          <textarea class="form-control" rows="3" placeholder="Multi-line text..."></textarea>
        </div>
        <button class="btn btn-primary">Submit Form</button>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Demo Card 2 - Action Buttons</div>
      <div class="card-body">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
          <button class="btn btn-primary">Primary</button>
          <button class="btn btn-outline-primary">Outline</button>
          <button class="btn btn-outline-secondary">Secondary</button>
          <button class="btn btn-outline-danger">Danger</button>
          <button class="btn btn-success">Success</button>
        </div>
        <div class="demo-item">
          <strong>Interactive Element</strong>
          <p class="small-muted" style="margin:8px 0 0 0;">Hover over buttons to see hover states. Click to test interactions.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Demo Table Section -->
  <div class="card">
    <div class="card-header">Demo Data Table</div>
    <div class="card-body">
      <div style="overflow:auto;">
        <table class="table" role="table" aria-label="Demo data">
          <thead>
            <tr>
              <th style="width:10%">ID</th>
              <th style="width:25%">Name</th>
              <th style="width:35%">Description</th>
              <th style="width:20%">Status</th>
              <th style="width:10%">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td><strong>Test Item Alpha</strong></td>
              <td>This is a sample description for testing table styles</td>
              <td><span style="color:#198754;">Active</span></td>
              <td>
                <div class="actions">
                  <button class="btn btn-sm btn-outline-secondary">Edit</button>
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </div>
              </td>
            </tr>
            <tr>
              <td>2</td>
              <td><strong>Test Item Beta</strong></td>
              <td>Another sample item to demonstrate table layout</td>
              <td><span style="color:#6c757d;">Inactive</span></td>
              <td>
                <div class="actions">
                  <button class="btn btn-sm btn-outline-secondary">Edit</button>
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </div>
              </td>
            </tr>
            <tr>
              <td>3</td>
              <td><strong>Test Item Gamma</strong></td>
              <td>Third test item with different content length</td>
              <td><span style="color:#198754;">Active</span></td>
              <td>
                <div class="actions">
                  <button class="btn btn-sm btn-outline-secondary">Edit</button>
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Image Grid Demo -->
  <div class="card">
    <div class="card-header">Demo Image Grid</div>
    <div class="card-body">
      <div class="grid grid-3">
        <div>
          <div class="demo-image">Image 1</div>
          <p class="small-muted" style="margin:8px 0 0 0;text-align:center;">Sample frame</p>
        </div>
        <div>
          <div class="demo-image">Image 2</div>
          <p class="small-muted" style="margin:8px 0 0 0;text-align:center;">Demo content</p>
        </div>
        <div>
          <div class="demo-image">Image 3</div>
          <p class="small-muted" style="margin:8px 0 0 0;text-align:center;">Test item</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Demo Modal -->
<div id="demoModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-card" role="document">
    <div class="modal-header">
      <strong>Demo Modal</strong>
      <button class="btn btn-sm" onclick="hideDemoModal()">Close</button>
    </div>
    <div class="modal-body">
      <p>This is a demo modal with the same styling as the admin interface.</p>
      <div class="form-group">
        <label class="form-label">Modal Input Field</label>
        <input type="text" class="form-control" placeholder="Type something...">
      </div>
      <div class="demo-item">
        <strong>Modal Content Area</strong>
        <p class="small-muted" style="margin:8px 0 0 0;">This demonstrates how modals will look and behave.</p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline-secondary" onclick="hideDemoModal()">Cancel</button>
      <button class="btn btn-primary" onclick="handleModalAction()">Confirm Action</button>
    </div>
  </div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
  // Modal controls
  const demoModal = document.getElementById('demoModal');
  
  window.showDemoModal = function() {
    demoModal.style.display = 'flex';
    demoModal.setAttribute('aria-hidden','false');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  };
  
  window.hideDemoModal = function() {
    demoModal.style.display = 'none';
    demoModal.setAttribute('aria-hidden','true');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  };
  
  window.handleModalAction = function() {
    if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
      Toast.show('Modal action confirmed!', 'success');
    } else {
      alert('Modal action confirmed! (Toast not available)');
    }
    hideDemoModal();
  };
  
  // Toast demo
  window.showSuccessToast = function() {
    if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
      Toast.show('This is a success toast message!', 'success');
    } else {
      console.log('[toast] This is a success toast message!');
      alert('Toast message: This is a success toast message! (Toast lib not loaded)');
    }
  };
  
  // Demo table row interactions
  document.addEventListener('click', function(ev) {
    const el = ev.target;
    if (el.matches('.btn-outline-secondary') && el.textContent === 'Edit') {
      const row = el.closest('tr');
      const name = row.querySelector('td:nth-child(2) strong').textContent;
      if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
        Toast.show('Editing: ' + name, 'info');
      } else {
        console.log('Editing:', name);
      }
    }
    
    if (el.matches('.btn-outline-danger') && el.textContent === 'Delete') {
      const row = el.closest('tr');
      const name = row.querySelector('td:nth-child(2) strong').textContent;
      if (confirm('Are you sure you want to delete "' + name + '"?')) {
        if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
          Toast.show('Deleted: ' + name, 'error');
        } else {
          console.log('Deleted:', name);
        }
      }
    }
  });
  
  // Close modal on overlay click
  demoModal.addEventListener('click', function(e) {
    if (e.target === demoModal) hideDemoModal();
  });
})();
</script>
<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
