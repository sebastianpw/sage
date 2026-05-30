<?php
// public/view_video_categories_admin.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Video Management Admin";
ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* FULL ORIGINAL CSS BLOCK RESTORED */
.admin-wrap { max-width: 1100px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; color: var(--text); }

.tabs { display: flex; gap: 0; border-bottom: 2px solid rgba(var(--muted-border-rgb), 0.08); margin-bottom: 20px; }
.tab-btn { 
    padding: 12px 20px; background: transparent; border: none; cursor: pointer; 
    font-size: 0.95rem; color: var(--text-muted); border-bottom: 2px solid transparent; 
    margin-bottom: -2px; font-weight: 500; transition: all 0.15s ease;
    text-decoration: none; display: inline-block;
}
.tab-btn:hover { color: var(--text); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }

.playlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; margin-top: 16px; }

.playlist-card { 
    background: var(--bg);
    border: 1px solid rgba(var(--muted-border-rgb), 0.12); 
    border-radius: 8px; 
    padding: 16px; 
    cursor: pointer; 
    transition: all 0.15s ease; 
}
.playlist-card:hover { 
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15); 
    transform: translateY(-2px); 
    border-color: var(--accent);
}
.playlist-card h4 { margin: 0 0 8px 0; font-size: 1rem; font-weight: 600; color: var(--text); }
.playlist-card p { margin: 0 0 12px 0; font-size: 0.85rem; color: var(--text-muted); }
.playlist-card .badge { 
    display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; 
    font-weight: 600; background: rgba(59,130,246,0.12); color: var(--accent); 
}

/* Modals */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 600px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(2,6,23,0.35); display: flex; flex-direction: column; max-height: 90vh; border: 1px solid rgba(var(--muted-border-rgb),0.06); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); }
.modal-header strong { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text); }
.modal-body { padding: 20px; overflow-y: auto; color: var(--text); }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); background: var(--bg); display: flex; justify-content: flex-end; gap: 8px; }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); }
textarea.form-control { min-height: 100px; resize: vertical; font-family: inherit; }

.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.loading-spinner { width: 40px; height: 40px; margin: 0 auto 16px; border: 4px solid rgba(var(--muted-border-rgb), 0.2); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) {
    .modal-card { max-height: 95vh; border-radius: 10px 10px 0 0; }
}
</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2>🎬 Video Management</h2>
  </div>

  <div class="tabs">
    <a href="view_video_admin.php" class="tab-btn">Videos</a>
    <a href="view_video_playlists_admin.php" class="tab-btn">Playlists</a>
    <a href="view_video_categories_admin.php" class="tab-btn active">Categories</a>
  </div>

  <!-- Categories Tab Content -->
  <div>
    <button class="btn btn-sm btn-primary" id="createCategoryBtn" style="margin-bottom:16px;">+ Create Category</button>
    <div class="playlist-grid" id="categoriesGrid">
      <div class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading categories...</p>
      </div>
    </div>
  </div>
</div>

<!-- Category Create/Edit Modal -->
<div id="categoryModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong id="categoryModalTitle">Manage Category</strong>
      <button class="btn btn-sm btn-outline-secondary close-modal">Close</button>
    </div>
    <form id="categoryForm">
      <input type="hidden" id="editCategoryId">
      <div class="modal-body">
        <div class="form-group">
          <label>Category Name</label>
          <input type="text" class="form-control" id="editCategoryName" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea class="form-control" id="editCategoryDescription"></textarea>
        </div>
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" class="form-control" id="editCategorySortOrder" value="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary close-modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
  let categories = [];
  function showToast(msg, type) { if (typeof Toast !== 'undefined') Toast.show(msg, type === 'error' ? 'error' : 'success'); else console.log(msg); }
  function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  // Modals
  const closeModals = () => document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
  document.querySelectorAll('.close-modal, .modal-overlay').forEach(el => el.addEventListener('click', e => {
      if(e.target === el) closeModals();
  }));

  function loadCategories() {
    fetch('video_admin_api.php?action=list_categories')
      .then(r => r.json())
      .then(data => {
        if (data.status === 'ok') {
          categories = data.categories;
          renderCategories();
        }
      });
  }

  function renderCategories() {
    const grid = document.getElementById('categoriesGrid');
    if (!categories.length) { grid.innerHTML = '<div class="empty-state" style="padding:40px;text-align:center;">No categories created yet.</div>'; return; }
    
    grid.innerHTML = categories.map(cat => `
      <div class="playlist-card" data-category-id="${cat.id}">
        <h4>${escapeHtml(cat.name)}</h4>
        <p class="small-muted">${escapeHtml(cat.description || 'No description')}</p>
        <div style="margin-bottom:12px;">
          <span class="badge">${cat.video_count} video${cat.video_count !== 1 ? 's' : ''}</span>
        </div>
        <div style="display:flex; gap:6px;">
          <button class="btn btn-sm btn-outline-primary edit-category-btn">Edit</button>
          <button class="btn btn-sm btn-outline-danger delete-category-btn">Delete</button>
        </div>
      </div>
    `).join('');
  }

  // --- Actions ---
  document.getElementById('categoriesGrid').addEventListener('click', e => {
      const btn = e.target.closest('button');
      if(!btn) return;
      const id = btn.closest('.playlist-card').dataset.categoryId;
      const cat = categories.find(c => c.id == id);

      if(btn.classList.contains('edit-category-btn')) {
        document.getElementById('editCategoryId').value = cat.id;
        document.getElementById('editCategoryName').value = cat.name;
        document.getElementById('editCategoryDescription').value = cat.description || '';
        document.getElementById('editCategorySortOrder').value = cat.sort_order || 0;
        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
        document.getElementById('categoryModal').classList.add('active');
      }
      if(btn.classList.contains('delete-category-btn')) {
        if(confirm(`Delete category "${cat.name}"?`)) {
            fetch('video_admin_api.php?action=delete_category', {method:'POST', body:JSON.stringify({id})})
            .then(r=>r.json()).then(d=>{ if(d.status==='ok') { showToast('Deleted'); loadCategories(); } else showToast(d.message,'error'); });
        }
      }
  });

  document.getElementById('createCategoryBtn').onclick = () => {
      document.getElementById('categoryForm').reset();
      document.getElementById('editCategoryId').value = '';
      document.getElementById('categoryModalTitle').textContent = 'Create Category';
      document.getElementById('categoryModal').classList.add('active');
  };

  document.getElementById('categoryForm').onsubmit = e => {
      e.preventDefault();
      const id = document.getElementById('editCategoryId').value;
      const action = id ? 'update_category' : 'create_category';
      fetch('video_admin_api.php?action='+action, {method:'POST', body:JSON.stringify({
          id: id,
          name: document.getElementById('editCategoryName').value,
          description: document.getElementById('editCategoryDescription').value,
          sort_order: document.getElementById('editCategorySortOrder').value
      })}).then(r=>r.json()).then(d=>{
          if(d.status==='ok') { document.getElementById('categoryModal').classList.remove('active'); loadCategories(); showToast('Saved'); }
          else showToast(d.message, 'error');
      });
  };

  loadCategories();
})();
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>