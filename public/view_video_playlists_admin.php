<?php
// public/view_video_playlists_admin.php
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

/* Playlist drag & drop container */
.playlist-list-container { 
    background: var(--card); 
    border: 1px solid rgba(var(--muted-border-rgb), 0.08); 
    border-radius: 8px; 
    padding: 12px; 
    box-shadow: var(--card-elevation); 
}

.playlist-drag-item { 
    background: var(--bg); 
    border: 2px solid rgba(var(--muted-border-rgb), 0.12); 
    border-radius: 8px; 
    padding: 16px; 
    margin-bottom: 12px; 
    cursor: move; 
    transition: all 0.15s ease; 
    display: flex; 
    align-items: center; 
    gap: 16px; 
    touch-action: pan-y; 
    user-select: none; 
    position: relative;
}
.playlist-drag-item:last-child { margin-bottom: 0; }
.playlist-drag-item:hover:not(.dragging) { 
    border-color: var(--accent); 
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15); 
}
.playlist-drag-item.dragging { 
    opacity: 0.7; 
    cursor: grabbing; 
    transform: scale(1.03); 
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3); 
    z-index: 1000; 
    border-color: var(--accent); 
}
.playlist-drag-item.drag-over { 
    border-color: var(--green); 
    background: rgba(35, 134, 54, 0.08); 
    transform: scale(1.02); 
}

.playlist-drag-handle { 
    display: flex; 
    flex-direction: column; 
    gap: 3px; 
    cursor: grab; 
    padding: 8px; 
    color: var(--text-muted); 
    transition: color 0.2s; 
    flex-shrink: 0; 
}
.playlist-drag-handle:active { cursor: grabbing; }
.playlist-drag-handle:hover { color: var(--accent); }
.playlist-drag-handle span { 
    display: block; 
    width: 24px; 
    height: 3px; 
    background: currentColor; 
    border-radius: 2px; 
}

.playlist-main-info { flex: 1; min-width: 0; }
.playlist-main-info h4 { margin: 0 0 6px 0; font-size: 1rem; font-weight: 600; color: var(--text); }
.playlist-main-info p { margin: 0; font-size: 0.85rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.playlist-actions { display: flex; gap: 6px; flex-wrap: wrap; flex-shrink: 0; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: rgba(59,130,246,0.12); color: var(--accent); }

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

.notification { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
.notification-info { background: rgba(59,130,246,0.12); color: var(--accent); border-left: 4px solid var(--accent); }
.save-indicator { display: none; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.9rem; }
.save-indicator.show { display: flex; }
.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.loading-spinner { width: 40px; height: 40px; margin: 0 auto 16px; border: 4px solid rgba(var(--muted-border-rgb), 0.2); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) {
    .playlist-drag-item { flex-wrap: wrap; }
    .playlist-main-info { flex-basis: 100%; order: -1; }
    .playlist-actions { flex-basis: 100%; justify-content: flex-end; }
}
</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2>🎬 Video Management</h2>
  </div>

  <div class="tabs">
    <a href="view_video_admin.php" class="tab-btn">Videos</a>
    <a href="view_video_playlists_admin.php" class="tab-btn active">Playlists</a>
    <a href="view_video_categories_admin.php" class="tab-btn">Categories</a>
  </div>

  <!-- Playlists Tab Content -->
  <div>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
          <button class="btn btn-sm btn-primary" id="createPlaylistBtn">+ Create Playlist</button>
          <div class="save-indicator" id="playlistSaveIndicator">
              <div class="loading-spinner" style="width: 20px; height: 20px; margin: 0; border-width: 3px;"></div>
              <span>Saving order...</span>
          </div>
      </div>
      
      <div class="notification notification-info">
          <strong>💡 Tip:</strong> Drag and drop playlists to reorder them. Changes are saved automatically.
      </div>
      
      <div class="playlist-list-container" id="playlistsGrid">
          <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Loading playlists...</p>
          </div>
      </div>
  </div>
</div>

<!-- Playlist Create/Edit Modal -->
<div id="playlistModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong id="playlistModalTitle">Manage Playlist</strong>
      <button class="btn btn-sm btn-outline-secondary close-modal">Close</button>
    </div>
    <form id="playlistForm">
      <input type="hidden" id="editPlaylistId">
      <div class="modal-body">
        <div class="form-group">
          <label>Playlist Name</label>
          <input type="text" class="form-control" id="editPlaylistName" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea class="form-control" id="editPlaylistDescription"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary close-modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Playlist JSON Export Modal -->
<div id="jsonExportModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong>Playlist JSON Output</strong>
      <button class="btn btn-sm btn-outline-secondary close-modal">Close</button>
    </div>
    <div class="modal-body">
      <p class="small-muted">Copy the JSON below to use in your post manager.</p>
      <textarea id="jsonOutput" class="form-control" readonly style="height: 300px; font-family: monospace; font-size: 0.8rem; white-space: pre;"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-sm btn-outline-secondary close-modal">Close</button>
      <button class="btn btn-sm btn-success" id="copyJsonBtn">📋 Copy to Clipboard</button>
    </div>
  </div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
  let playlists = [];
  function showToast(msg, type) { if (typeof Toast !== 'undefined') Toast.show(msg, type === 'error' ? 'error' : 'success'); else console.log(msg); }
  function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  // Modals
  const closeModals = () => document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
  document.querySelectorAll('.close-modal, .modal-overlay').forEach(el => el.addEventListener('click', e => {
      if(e.target === el) closeModals();
  }));

  function loadPlaylists() {
    fetch('video_admin_api.php?action=list_playlists')
      .then(r => r.json())
      .then(data => {
        if (data.status === 'ok') {
          playlists = data.playlists;
          renderPlaylists();
        }
      });
  }

  function renderPlaylists() {
      const grid = document.getElementById('playlistsGrid');
      if (!playlists.length) { grid.innerHTML = '<div class="empty-state" style="padding:40px;text-align:center;">No playlists created yet.</div>'; return; }
      
      grid.innerHTML = playlists.map((pl, index) => `
          <div class="playlist-drag-item" draggable="true" data-playlist-id="${pl.id}" data-index="${index}">
              <button style="position:absolute;top:10px;right:10px;" class="btn btn-sm btn-danger delete-playlist-btn">✕</button>
              
              <div class="playlist-main-info">
                  <h4>${escapeHtml(pl.name)}</h4>
                  <p>${escapeHtml(pl.description || 'No description')} <span style="margin-left:8px;" class="badge">${pl.video_count} videos</span></p>
              </div>
              <div class="playlist-actions">
                  <div class="playlist-drag-handle"><span></span><span></span><span></span></div>
                  <a class="btn btn-sm btn-outline-success" href="view_video_playlist.php?playlist_id=${pl.id}">View</a>
                  <button class="btn btn-sm btn-outline-primary edit-playlist-btn">Edit</button>
                  <a class="btn btn-sm btn-outline-primary" href="view_playlist_videos_reorder.php?playlist_id=${pl.id}">Sort</a>
                  <button class="btn btn-sm btn-outline-success get-playlist-json-btn">JSON</button>
              </div>
          </div>
      `).join('');
      attachPlaylistDragListeners();
  }

  // --- Drag & Drop ---
  let dragEl = null;
  function attachPlaylistDragListeners() {
      document.querySelectorAll('.playlist-drag-item').forEach(item => {
          item.addEventListener('dragstart', function(e) {
              dragEl = this;
              e.dataTransfer.effectAllowed = 'move';
              this.classList.add('dragging');
          });
          item.addEventListener('dragend', function() {
              this.classList.remove('dragging');
              document.querySelectorAll('.playlist-drag-item').forEach(i => i.classList.remove('drag-over'));
          });
          item.addEventListener('dragover', function(e) {
              e.preventDefault();
              if (this === dragEl) return;
              this.classList.add('drag-over');
          });
          item.addEventListener('dragleave', function() {
              this.classList.remove('drag-over');
          });
          item.addEventListener('drop', function(e) {
              e.stopPropagation();
              if (dragEl && this !== dragEl) {
                  const from = parseInt(dragEl.dataset.index);
                  const to = parseInt(this.dataset.index);
                  const item = playlists.splice(from, 1)[0];
                  playlists.splice(to, 0, item);
                  renderPlaylists();
                  savePlaylistOrder();
              }
              return false;
          });
      });
  }

  function savePlaylistOrder() {
      const ind = document.getElementById('playlistSaveIndicator');
      ind.classList.add('show');
      fetch('video_admin_api.php?action=update_playlist_order', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ playlist_ids: playlists.map(p => p.id) })
      }).finally(() => setTimeout(() => ind.classList.remove('show'), 500));
  }

  // --- Actions ---
  document.getElementById('playlistsGrid').addEventListener('click', e => {
      const btn = e.target.closest('button');
      if(!btn) return;
      const el = btn.closest('.playlist-drag-item');
      const id = el.dataset.playlistId;
      const pl = playlists.find(p => p.id == id);

      if(btn.classList.contains('edit-playlist-btn')) {
        document.getElementById('editPlaylistId').value = pl.id;
        document.getElementById('editPlaylistName').value = pl.name;
        document.getElementById('editPlaylistDescription').value = pl.description || '';
        document.getElementById('playlistModalTitle').textContent = 'Edit Playlist';
        document.getElementById('playlistModal').classList.add('active');
      }
      if(btn.classList.contains('delete-playlist-btn')) {
        if(confirm(`Delete playlist "${pl.name}"?`)) {
            fetch('video_admin_api.php?action=delete_playlist', {method:'POST', body:JSON.stringify({id})})
            .then(r=>r.json()).then(d => { if(d.status==='ok') { showToast('Deleted'); loadPlaylists(); } });
        }
      }
      if(btn.classList.contains('get-playlist-json-btn')) {
        btn.disabled = true; btn.textContent = '...';
        fetch(`video_admin_api.php?action=get_playlist_json&id=${id}`).then(r=>r.json()).then(d=>{
            if(d.status==='ok') {
                document.getElementById('jsonOutput').value = d.json_string;
                document.getElementById('jsonExportModal').classList.add('active');
            } else showToast(d.message,'error');
        }).finally(() => { btn.disabled=false; btn.textContent='JSON'; });
      }
  });

  document.getElementById('createPlaylistBtn').onclick = () => {
      document.getElementById('playlistForm').reset();
      document.getElementById('editPlaylistId').value = '';
      document.getElementById('playlistModalTitle').textContent = 'Create Playlist';
      document.getElementById('playlistModal').classList.add('active');
  };

  document.getElementById('playlistForm').onsubmit = e => {
      e.preventDefault();
      const id = document.getElementById('editPlaylistId').value;
      const action = id ? 'update_playlist' : 'create_playlist';
      fetch('video_admin_api.php?action='+action, {method:'POST', body:JSON.stringify({
          id: id,
          name: document.getElementById('editPlaylistName').value,
          description: document.getElementById('editPlaylistDescription').value
      })}).then(r=>r.json()).then(d=>{
          if(d.status==='ok') { document.getElementById('playlistModal').classList.remove('active'); loadPlaylists(); showToast('Saved'); }
          else showToast(d.message, 'error');
      });
  };

  document.getElementById('copyJsonBtn').onclick = () => {
      navigator.clipboard.writeText(document.getElementById('jsonOutput').value);
      showToast('Copied');
  };

  loadPlaylists();
})();
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>