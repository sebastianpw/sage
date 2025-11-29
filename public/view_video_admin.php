<?php
// public/view_video_admin.php
require_once __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Video Management Admin";
ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<style>
/* Admin wrapper - consistent with other admin pages */
.admin-wrap { max-width: 1100px; margin: 0 auto; padding: 18px; color: var(--text); }
.admin-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.admin-head h2 { margin: 0; font-weight: 600; font-size: 1.3rem; color: var(--text); }

/* Tabs */
.tabs { 
    display: flex; 
    gap: 0; 
    border-bottom: 2px solid rgba(var(--muted-border-rgb), 0.08); 
    margin-bottom: 20px; 
}
.tab-btn { 
    padding: 12px 20px; 
    background: transparent; 
    border: none; 
    cursor: pointer; 
    font-size: 0.95rem; 
    color: var(--text-muted); 
    border-bottom: 2px solid transparent; 
    margin-bottom: -2px; 
    font-weight: 500;
    transition: all 0.15s ease;
}
.tab-btn:hover { color: var(--text); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* Filter bar card */
.filter-bar { 
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 16px;
    box-shadow: var(--card-elevation);
    display: flex; 
    gap: 12px; 
    margin-bottom: 16px; 
    flex-wrap: wrap; 
    align-items: center; 
}
.filter-bar input, .filter-bar select { 
    padding: 8px 12px; 
    border-radius: 6px; 
    border: 1px solid rgba(var(--muted-border-rgb), 0.12); 
    font-size: 0.9rem;
    background: var(--bg);
    color: var(--text);
    transition: border-color 0.15s ease;
}
.filter-bar input:focus, .filter-bar select:focus {
    outline: none;
    border-color: var(--accent);
}
.filter-bar input { flex: 1; min-width: 200px; }
.filter-bar select { min-width: 150px; }

/* Video list container */
.video-list-container {
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--card-elevation);
}

/* Video items */
.video-item {
    background: var(--bg);
    border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.06);
    padding: 16px;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 16px;
}
.video-item:last-child { border-bottom: none; }
.video-item:hover { background: rgba(var(--muted-border-rgb), 0.02); }

.video-thumb { 
    width: 120px; 
    height: 68px; 
    object-fit: cover; 
    border-radius: 6px; 
    flex-shrink: 0;
    border: 1px solid rgba(var(--muted-border-rgb), 0.12);
}

.video-info { flex: 1; min-width: 0; }
.video-name { font-weight: 600; font-size: 1rem; color: var(--text); margin-bottom: 6px; }
.video-meta { font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; }
.video-meta span { margin-right: 16px; display: inline-block; }
.video-meta strong { color: var(--text); font-weight: 600; margin-right: 4px; }

.video-actions { display: flex; gap: 6px; flex-wrap: wrap; flex-shrink: 0; }

/* Empty & loading states */
.empty-state { 
    text-align: center; 
    padding: 60px 20px; 
    color: var(--text-muted); 
    font-size: 0.95rem;
}
.empty-state::before {
    content: "🎬";
    display: block;
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.loading-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}
.loading-spinner {
    width: 40px;
    height: 40px;
    margin: 0 auto 16px;
    border: 4px solid rgba(var(--muted-border-rgb), 0.2);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Playlist & Category grids */
.playlist-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); 
    gap: 16px; 
    margin-top: 16px; 
}

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
    display: inline-block; 
    padding: 4px 10px; 
    border-radius: 12px; 
    font-size: 0.75rem; 
    font-weight: 600; 
    background: rgba(59,130,246,0.12); 
    color: var(--accent); 
}

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

/* Upload area */
.upload-area { 
    border: 2px dashed rgba(var(--muted-border-rgb), 0.2); 
    border-radius: 8px; 
    padding: 32px; 
    text-align: center; 
    cursor: pointer; 
    transition: all 0.3s; 
    background: var(--bg);
}
.upload-area:hover { 
    border-color: var(--accent); 
    background: rgba(59,130,246,0.03); 
}
.upload-area.dragover { 
    border-color: var(--accent); 
    background: rgba(59,130,246,0.08); 
}

.progress-bar { 
    width: 100%; 
    height: 20px; 
    background: var(--bg); 
    border-radius: 10px; 
    overflow: hidden; 
    margin-top: 12px; 
    display: none; 
    border: 1px solid rgba(var(--muted-border-rgb), 0.12);
}
.progress-fill { 
    height: 100%; 
    background: var(--accent); 
    transition: width 0.3s; 
}

/* Modals - improved version */
.modal-overlay { 
    position: fixed; 
    inset: 0; 
    background: rgba(0,0,0,0.45); 
    display: none; 
    align-items: center; 
    justify-content: center; 
    z-index: 120000; 
    padding: 12px; 
}
.modal-overlay.active { display: flex; }

.modal-card { 
    width: 100%; 
    max-width: 600px; 
    background: var(--card); 
    border-radius: 10px; 
    box-shadow: 0 8px 30px rgba(2,6,23,0.35); 
    display: flex; 
    flex-direction: column; 
    max-height: 90vh; 
    border: 1px solid rgba(var(--muted-border-rgb),0.06); 
}

.modal-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 16px 20px; 
    border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); 
}
.modal-header h3,
.modal-header strong { 
    margin: 0; 
    font-size: 1.1rem; 
    font-weight: 600; 
    color: var(--text); 
}

.modal-body { 
    padding: 20px; 
    overflow-y: auto; 
    color: var(--text); 
}

.modal-footer { 
    padding: 12px 20px; 
    border-top: 1px solid rgba(var(--muted-border-rgb),0.08); 
    background: var(--bg); 
    display: flex; 
    justify-content: flex-end; 
    gap: 8px; 
}

/* Form elements */
.form-group { margin-bottom: 16px; }
.form-group label { 
    display: block; 
    font-size: 0.85rem; 
    font-weight: 600; 
    color: var(--text-muted); 
    margin-bottom: 6px; 
}
.form-control { 
    width: 100%; 
    padding: 10px 12px; 
    border-radius: 6px; 
    border: 1px solid rgba(var(--muted-border-rgb), 0.12); 
    font-size: 0.9rem; 
    background: var(--bg);
    color: var(--text);
    transition: border-color 0.15s ease;
}
.form-control:focus {
    outline: none;
    border-color: var(--accent);
}
textarea.form-control { 
    min-height: 100px; 
    resize: vertical; 
    font-family: inherit; 
}

/* Notification */
.notification { 
    padding: 12px 16px; 
    border-radius: 8px; 
    margin-bottom: 16px; 
    font-size: 0.9rem; 
}
.notification-info { 
    background: rgba(59,130,246,0.12); 
    color: var(--accent); 
    border-left: 4px solid var(--accent); 
}

/* Save indicator */
.save-indicator { 
    display: none; 
    align-items: center; 
    gap: 8px; 
    color: var(--text-muted); 
    font-size: 0.9rem; 
}
.save-indicator.show { display: flex; }

/* Small utilities */
.small-muted { color: var(--text-muted); font-size: 0.85rem; }

/* Mobile responsive */
@media (max-width: 768px) {
    .admin-head { flex-direction: column; align-items: flex-start; }
    .filter-bar { flex-direction: column; }
    .filter-bar input, .filter-bar select { width: 100%; }
    .video-item { flex-direction: column; align-items: flex-start; }
    .video-thumb { width: 100%; height: auto; aspect-ratio: 16/9; }
    .video-actions { width: 100%; }
    .video-actions .btn { flex: 1; }
    .playlist-drag-item { flex-wrap: wrap; }
    .playlist-main-info { flex-basis: 100%; order: -1; }
    .playlist-actions { flex-basis: 100%; justify-content: flex-end; }
    .modal-card { max-height: 95vh; border-radius: 10px 10px 0 0; }
    .playlist-grid { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .admin-wrap { padding: 12px; }
    .video-item { padding: 12px; }
    .modal-overlay { padding: 0; }
    .modal-card { border-radius: 10px 10px 0 0; max-height: 100vh; }
}
</style>

<div class="admin-wrap">
  <div class="admin-head">
    <h2>🎬 Video Management</h2>
    <button class="btn btn-sm btn-primary" id="uploadBtn">+ Upload Video</button>
  </div>

  <div class="tabs">
    <button class="tab-btn active" data-tab="videos">Videos</button>
    <button class="tab-btn" data-tab="playlists">Playlists</button>
    <button class="tab-btn" data-tab="categories">Categories</button>
  </div>

  <!-- Videos Tab -->
  <div class="tab-content active" id="videos-tab">
    <div class="filter-bar">
      <input type="text" id="searchInput" placeholder="Search videos..." class="form-control">
      <select id="categoryFilter" class="form-control">
        <option value="">All Categories</option>
      </select>
      <select id="playlistFilter" class="form-control">
        <option value="">All Videos</option>
      </select>
    </div>

    <div class="video-list-container" id="videoListContainer">
      <div class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading videos...</p>
      </div>
    </div>
  </div>

  <!-- Playlists Tab -->
  <div class="tab-content" id="playlists-tab">
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

  <!-- Categories Tab -->
  <div class="tab-content" id="categories-tab">
    <button class="btn btn-sm btn-primary" id="createCategoryBtn" style="margin-bottom:16px;">+ Create Category</button>
    <div class="playlist-grid" id="categoriesGrid">
      <div class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading categories...</p>
      </div>
    </div>
  </div>
</div>

<!-- All Modals -->
<!-- Upload Modal -->
<div id="uploadModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong>Upload Video</strong>
      <button class="btn btn-sm btn-outline-secondary" id="uploadCloseBtn">Close</button>
    </div>
    <form id="uploadForm" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="upload-area" id="uploadArea">
          <input type="file" id="videoFile" name="video" accept="video/mp4,video/webm,video/ogg" class="form-control" style="display:none;">
          <p style="margin:0 0 8px 0; font-weight: 600;">Click to select or drag & drop video</p>
          <p class="small-muted" style="margin:0;">Supported: MP4, WebM, OGG (max 500MB)</p>
        </div>
        <div id="fileInfo" style="display:none; margin-top:12px; padding:12px; background:var(--bg); border-radius:6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12);">
          <strong id="fileName" style="color: var(--text);"></strong>
          <div class="small-muted" id="fileSize"></div>
        </div>
        <div class="progress-bar" id="progressBar">
          <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="form-group">
          <label>Video Name</label>
          <input type="text" name="name" class="form-control" id="videoName" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" class="form-control" id="videoDescription"></textarea>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category_id" class="form-control" id="videoCategory">
            <option value="">None</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="uploadCancelBtn">Cancel</button>
        <button type="submit" class="btn btn-sm btn-success" id="uploadSubmitBtn">Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Video Modal -->
<div id="editModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong>Edit Video</strong>
      <button class="btn btn-sm btn-outline-secondary" id="editCloseBtn">Close</button>
    </div>
    <form id="editForm">
      <input type="hidden" id="editVideoId">
      <div class="modal-body">
        <div class="form-group">
          <label>Video Name</label>
          <input type="text" class="form-control" id="editName" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea class="form-control" id="editDescription"></textarea>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select class="form-control" id="editCategory">
            <option value="">None</option>
          </select>
        </div>
        <div class="form-group">
          <label style="display:flex; align-items:center; gap:8px; cursor: pointer;">
            <input type="checkbox" id="editActive">
            Active
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="editCancelBtn">Cancel</button>
        <button type="submit" class="btn btn-sm btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add to Playlist Modal -->
<div id="addToPlaylistModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong>Add to Playlist</strong>
      <button class="btn btn-sm btn-outline-secondary" id="addToPlaylistCloseBtn">Close</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="addToPlaylistVideoId">
      <p class="small-muted">Select playlists to add this video to:</p>
      <div id="playlistCheckboxes"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-sm btn-outline-secondary" id="addToPlaylistCancelBtn">Cancel</button>
      <button class="btn btn-sm btn-success" id="addToPlaylistSubmitBtn">Add to Playlists</button>
    </div>
  </div>
</div>

<!-- Playlist Create/Edit Modal -->
<div id="playlistModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong id="playlistModalTitle">Manage Playlist</strong>
      <button class="btn btn-sm btn-outline-secondary" id="playlistCloseBtn">Close</button>
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
        <button type="button" class="btn btn-sm btn-outline-secondary" id="playlistCancelBtn">Cancel</button>
        <button type="submit" class="btn btn-sm btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Category Create/Edit Modal -->
<div id="categoryModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <strong id="categoryModalTitle">Manage Category</strong>
      <button class="btn btn-sm btn-outline-secondary" id="categoryCloseBtn">Close</button>
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
        <button type="button" class="btn btn-sm btn-outline-secondary" id="categoryCancelBtn">Cancel</button>
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
      <button class="btn btn-sm btn-outline-secondary" id="jsonCloseBtn">Close</button>
    </div>
    <div class="modal-body">
      <p class="small-muted">Copy the JSON below to use in your post manager.</p>
      <textarea id="jsonOutput" class="form-control" readonly style="height: 300px; font-family: monospace; font-size: 0.8rem; white-space: pre;"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-sm btn-outline-secondary" id="jsonCancelBtn">Close</button>
      <button class="btn btn-sm btn-success" id="copyJsonBtn">📋 Copy to Clipboard</button>
    </div>
  </div>
</div>

<script src="js/toast.js"></script>
<script>
(function(){
  // Global state variables
  let videos = [];
  let categories = [];
  let playlists = [];

  // Modal element references
  const categoryModal = document.getElementById('categoryModal');
  const categoryForm = document.getElementById('categoryForm');
  const categoryCloseBtn = document.getElementById('categoryCloseBtn');
  const categoryCancelBtn = document.getElementById('categoryCancelBtn');
  const categoryModalTitle = document.getElementById('categoryModalTitle');

  const playlistModal = document.getElementById('playlistModal');
  const playlistForm = document.getElementById('playlistForm');
  const playlistCloseBtn = document.getElementById('playlistCloseBtn');
  const playlistCancelBtn = document.getElementById('playlistCancelBtn');
  const playlistModalTitle = document.getElementById('playlistModalTitle');

  const jsonExportModal = document.getElementById('jsonExportModal');
  const jsonOutput = document.getElementById('jsonOutput');
  const copyJsonBtn = document.getElementById('copyJsonBtn');
  const jsonCloseBtn = document.getElementById('jsonCloseBtn');
  const jsonCancelBtn = document.getElementById('jsonCancelBtn');
  
  // --- Helper Functions ---
  
  function showToast(msg, type) {
    if (typeof Toast !== 'undefined' && Toast.show) {
      Toast.show(msg, type === 'error' ? 'error' : type === 'success' ? 'success' : 'info');
    } else {
      console.log(`[toast-${type}]`, msg);
    }
  }
  
  function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }
  
  function formatFileSize(bytes) {
    if (!bytes) return '';
    const mb = bytes / (1024 * 1024);
    return mb.toFixed(2) + ' MB';
  }
  
  function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function openModal(modal) {
    modal.classList.add('active');
  }

  function closeModal(modal) {
    modal.classList.remove('active');
  }

  function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
      closeModal(modal);
    });
  }
  
  // --- Tab Management ---

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById(btn.dataset.tab + '-tab').classList.add('active');
    });
  });
  
  // --- Data Loading & Rendering ---

  function loadCategories() {
    fetch('video_admin_api.php?action=list_categories')
      .then(r => r.json())
      .then(data => {
        if (data.status === 'ok') {
          categories = data.categories;
          updateCategoryDropdowns();
          renderCategoriesTab();
        }
      })
      .catch(err => console.error('Failed to load categories', err));
  }
  
  function loadPlaylists() {
    fetch('video_admin_api.php?action=list_playlists')
      .then(r => r.json())
      .then(data => {
        if (data.status === 'ok') {
          playlists = data.playlists;
          updatePlaylistFilter();
          renderPlaylistsTab();
        }
      })
      .catch(err => console.error('Failed to load playlists', err));
  }

  function loadVideos() {
    const search = document.getElementById('searchInput').value;
    const categoryId = document.getElementById('categoryFilter').value;
    const playlistId = document.getElementById('playlistFilter').value;
    
    const params = new URLSearchParams({action: 'list_videos'});
    if (search) params.append('search', search);
    if (categoryId) params.append('category_id', categoryId);
    if (playlistId) params.append('playlist_id', playlistId);
    
    fetch('video_admin_api.php?' + params)
      .then(r => r.json())
      .then(data => {
        if (data.status === 'ok') {
          videos = data.videos;
          renderVideos();
        } else {
          showToast('Failed to load videos', 'error');
        }
      })
      .catch(err => {
        console.error('Failed to load videos', err);
        showToast('Network error', 'error');
      });
  }

  function renderVideos() {
    const container = document.getElementById('videoListContainer');
    if (!videos.length) {
      container.innerHTML = '<div class="empty-state">No videos found</div>';
      return;
    }
    
    container.innerHTML = videos.map(v => `
      <div class="video-item" data-video-id="${v.id}">
        <img src="${escapeHtml(v.thumbnail)}" class="video-thumb" alt="${escapeHtml(v.name)}">
        <div class="video-info">
          <div class="video-name">${escapeHtml(v.name)}</div>
          ${v.description ? `<div class="small-muted" style="margin-bottom: 6px;">${escapeHtml(v.description)}</div>` : ''}
          <div class="video-meta">
            <span><strong>ID:</strong> ${v.id}</span>
            <span><strong>Category:</strong> ${v.category_name || 'None'}</span>
            <span><strong>Duration:</strong> ${formatDuration(v.duration)}</span>
            <span><strong>Created:</strong> ${formatDate(v.created_at)}</span>
          </div>
        </div>
        <div class="video-actions">
          <button class="btn btn-sm btn-outline-primary edit-video-btn">Edit</button>
          <button class="btn btn-sm btn-outline-secondary add-playlist-btn">Playlists</button>
          <button class="btn btn-sm btn-outline-secondary regen-thumb-btn">Thumbnail</button>
          <a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(v.url)}" target="_blank">View</a>
          <button class="btn btn-sm btn-outline-danger delete-video-btn">Delete</button>
        </div>
      </div>
    `).join('');
  }

  function renderCategoriesTab() {
    const grid = document.getElementById('categoriesGrid');
    if (!categories.length) {
      grid.innerHTML = '<div class="empty-state">No categories created yet.</div>';
      return;
    }
    
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

  function updateCategoryDropdowns() {
    const selects = [
      document.getElementById('categoryFilter'),
      document.getElementById('videoCategory'),
      document.getElementById('editCategory')
    ];
    
    selects.forEach(select => {
      const current = select.value;
      const firstOption = select.options[0].outerHTML;
      select.innerHTML = firstOption;
      categories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.id;
        opt.textContent = cat.name;
        select.appendChild(opt);
      });
      select.value = current;
    });
  }

  function updatePlaylistFilter() {
    const select = document.getElementById('playlistFilter');
    const current = select.value;
    select.innerHTML = '<option value="">All Videos</option>';
    playlists.forEach(pl => {
      const opt = document.createElement('option');
      opt.value = pl.id;
      opt.textContent = `${pl.name} (${pl.video_count} videos)`;
      select.appendChild(opt);
    });
    select.value = current;
  }
  
  // --- Playlist Drag & Drop Logic ---
  let playlistDraggedElement = null;
  let playlistDraggedIndex = null;
  let playlistIsDragging = false;
  let playlistCurrentDropTarget = null;
  let playlistSaveTimeout = null;

  function renderPlaylistsTab() {
      const grid = document.getElementById('playlistsGrid');
      if (!playlists.length) {
          grid.innerHTML = '<div class="empty-state">No playlists created yet.</div>';
          return;
      }
      
      grid.innerHTML = playlists.map((pl, index) => `
          <div class="playlist-drag-item" draggable="true" data-playlist-id="${pl.id}" data-index="${index}">
              <button style="position:absolute;top:10px;right:10px;" class="btn btn-sm btn-danger delete-playlist-btn">✕</button>
              
              <div class="playlist-main-info">
                  <h4>${escapeHtml(pl.name)}</h4>
                  <p>${escapeHtml(pl.description || 'No description')} <span style="margin-left:8px;" class="badge">${pl.video_count} video${pl.video_count !== 1 ? 's' : ''}</span></p>
              </div>
              <div class="playlist-actions">
                  <div class="playlist-drag-handle">
                      <span></span>
                      <span></span>
                      <span></span>
                  </div>
                  <button class="btn btn-sm btn-outline-success view-playlist-btn">View</button>
                  <button class="btn btn-sm btn-outline-primary edit-playlist-btn">Edit</button>
                  <button class="btn btn-sm btn-outline-primary reorder-videos-btn">Sort</button>
                  <button class="btn btn-sm btn-outline-success get-playlist-json-btn">JSON</button>
              </div>
          </div>
      `).join('');
      
      attachPlaylistDragListeners();
  }

  function attachPlaylistDragListeners() {
      const items = document.querySelectorAll('.playlist-drag-item');

      items.forEach(item => {
          item.addEventListener('dragstart', handlePlaylistDragStart);
          item.addEventListener('dragend', handlePlaylistDragEnd);
          item.addEventListener('dragover', handlePlaylistDragOver);
          item.addEventListener('drop', handlePlaylistDrop);
          item.addEventListener('touchstart', handlePlaylistTouchStart, { passive: false });
          item.addEventListener('touchmove', handlePlaylistTouchMove, { passive: false });
          item.addEventListener('touchend', handlePlaylistTouchEnd);
          item.addEventListener('touchcancel', handlePlaylistTouchEnd);
      });
  }

  function handlePlaylistDragStart(e) {
      playlistIsDragging = true;
      playlistDraggedElement = this;
      playlistDraggedIndex = parseInt(this.dataset.index);
      this.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', playlistDraggedIndex);
  }

  function handlePlaylistDragEnd(e) {
      playlistIsDragging = false;
      this.classList.remove('dragging');
      document.querySelectorAll('.playlist-drag-item').forEach(item => item.classList.remove('drag-over'));
      playlistCurrentDropTarget = null;
  }

  function handlePlaylistDragOver(e) {
      e.preventDefault();
      if (!playlistIsDragging || this === playlistDraggedElement) return;
      if (playlistCurrentDropTarget && playlistCurrentDropTarget !== this) {
          playlistCurrentDropTarget.classList.remove('drag-over');
      }
      this.classList.add('drag-over');
      playlistCurrentDropTarget = this;
  }

  function handlePlaylistDrop(e) {
      e.preventDefault(); e.stopPropagation();
      if (playlistDraggedElement && this !== playlistDraggedElement) {
          reorderPlaylists(playlistDraggedIndex, parseInt(this.dataset.index));
      }
  }

  let playlistTouchStartY = 0;
  let playlistTouchCurrentY = 0;
  let playlistTouchStartX = 0;
  let playlistTouchElement = null;
  let playlistHasMoved = false;
  let playlistScrollThreshold = 10;
  
  function handlePlaylistTouchStart(e) {
      const touch = e.touches[0];
      playlistTouchElement = this;
      playlistDraggedElement = this;
      playlistDraggedIndex = parseInt(this.dataset.index);
      playlistTouchStartY = touch.clientY;
      playlistTouchStartX = touch.clientX;
      playlistTouchCurrentY = touch.clientY;
      playlistHasMoved = false;
      
      // Delay adding dragging class to prevent accidental drags
      setTimeout(() => {
          if (playlistTouchElement && playlistHasMoved) {
              playlistTouchElement.classList.add('dragging');
          }
      }, 100);
  }

  function handlePlaylistTouchMove(e) {
      if (!playlistTouchElement) return;
      
      const touch = e.touches[0];
      const deltaY = Math.abs(touch.clientY - playlistTouchStartY);
      const deltaX = Math.abs(touch.clientX - playlistTouchStartX);
      
      // Only treat as drag if vertical movement is significant and greater than horizontal
      if (deltaY > playlistScrollThreshold && deltaY > deltaX) {
          e.preventDefault();
          playlistHasMoved = true;
          playlistTouchElement.classList.add('dragging');
          
          playlistTouchCurrentY = touch.clientY;
          const deltaFromStart = playlistTouchCurrentY - playlistTouchStartY;

          // Visual feedback with smooth transform
          playlistTouchElement.style.transform = `translateY(${deltaFromStart}px)`;
          playlistTouchElement.style.transition = 'none';

          // Find and highlight drop target
          const items = [...document.querySelectorAll('.playlist-drag-item:not(.dragging)')];
          
          // Remove all highlights first
          items.forEach(item => item.classList.remove('drag-over'));
          
          // Find the item we're hovering over
          let targetItem = null;
          for (const item of items) {
              const rect = item.getBoundingClientRect();
              
              // Check if touch point is within this item's bounds
              if (playlistTouchCurrentY >= rect.top && playlistTouchCurrentY <= rect.bottom) {
                  targetItem = item;
                  break;
              }
          }
          
          if (targetItem) {
              targetItem.classList.add('drag-over');
              playlistCurrentDropTarget = targetItem;
          }
      }
  }

  function handlePlaylistTouchEnd(e) {
      if (!playlistTouchElement) return;

      playlistTouchElement.classList.remove('dragging');
      playlistTouchElement.style.transform = '';
      playlistTouchElement.style.transition = '';

      // Only reorder if we actually moved
      if (playlistHasMoved && playlistCurrentDropTarget && playlistCurrentDropTarget !== playlistTouchElement) {
          reorderPlaylists(playlistDraggedIndex, parseInt(playlistCurrentDropTarget.dataset.index));
      }

      // Clean up
      document.querySelectorAll('.playlist-drag-item').forEach(item => {
          item.classList.remove('drag-over');
      });

      playlistTouchElement = null;
      playlistDraggedElement = null;
      playlistCurrentDropTarget = null;
      playlistHasMoved = false;
  }

  function reorderPlaylists(fromIndex, toIndex) {
      if (fromIndex === toIndex) return;
      const [movedItem] = playlists.splice(fromIndex, 1);
      playlists.splice(toIndex, 0, movedItem);
      renderPlaylistsTab();
      clearTimeout(playlistSaveTimeout);
      playlistSaveTimeout = setTimeout(savePlaylistOrder, 500);
  }

  function savePlaylistOrder() {
      const saveIndicator = document.getElementById('playlistSaveIndicator');
      saveIndicator.classList.add('show');
      const orderedIds = playlists.map(pl => pl.id);

      fetch('video_admin_api.php?action=update_playlist_order', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ playlist_ids: orderedIds })
      })
      .then(r => r.json())
      .then(data => {
          if (data.status === 'ok') {
              showToast('Playlist order saved', 'success');
          } else {
              showToast(data.message || 'Failed to save order', 'error');
              loadPlaylists();
          }
      })
      .catch(err => {
          console.error('Failed to save order', err);
          showToast('Network error while saving order', 'error');
          loadPlaylists();
      })
      .finally(() => {
          setTimeout(() => { saveIndicator.classList.remove('show'); }, 500);
      });
  }

  // --- Modal & Form Logic ---
  
  // Upload modal
  const uploadModal = document.getElementById('uploadModal');
  const uploadBtn = document.getElementById('uploadBtn');
  const uploadCloseBtn = document.getElementById('uploadCloseBtn');
  const uploadCancelBtn = document.getElementById('uploadCancelBtn');
  const uploadArea = document.getElementById('uploadArea');
  const videoFile = document.getElementById('videoFile');
  const uploadForm = document.getElementById('uploadForm');
  const progressBar = document.getElementById('progressBar');
  const progressFill = document.getElementById('progressFill');
  
  uploadBtn.onclick = () => openModal(uploadModal);
  uploadCloseBtn.onclick = uploadCancelBtn.onclick = () => { closeModal(uploadModal); uploadForm.reset(); document.getElementById('fileInfo').style.display = 'none'; };
  uploadArea.onclick = () => videoFile.click();
  uploadArea.ondragover = (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); };
  uploadArea.ondragleave = () => uploadArea.classList.remove('dragover');
  uploadArea.ondrop = (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
      videoFile.files = e.dataTransfer.files;
      handleFileSelect();
    }
  };
  videoFile.onchange = handleFileSelect;
  
  function handleFileSelect() {
    const file = videoFile.files[0];
    if (file) {
      document.getElementById('fileName').textContent = file.name;
      document.getElementById('fileSize').textContent = formatFileSize(file.size);
      document.getElementById('fileInfo').style.display = 'block';
      if (!document.getElementById('videoName').value) {
        document.getElementById('videoName').value = file.name.replace(/\.[^/.]+$/, '');
      }
    }
  }
  
  uploadForm.onsubmit = (e) => {
    e.preventDefault();
    const formData = new FormData(uploadForm);
    formData.append('action', 'upload_video');
    progressBar.style.display = 'block';
    progressFill.style.width = '0%';
    
    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        progressFill.style.width = ((e.loaded / e.total) * 100) + '%';
      }
    };
    xhr.onload = () => {
      progressBar.style.display = 'none';
      if (xhr.status === 200) {
        const data = JSON.parse(xhr.responseText);
        if (data.status === 'ok') {
          showToast('Video uploaded successfully', 'success');
          closeModal(uploadModal);
          uploadForm.reset();
          document.getElementById('fileInfo').style.display = 'none';
          loadVideos();
        } else {
          showToast(data.message || 'Upload failed', 'error');
        }
      } else {
        showToast('Upload failed', 'error');
      }
    };
    xhr.onerror = () => {
      progressBar.style.display = 'none';
      showToast('Network error during upload', 'error');
    };
    xhr.open('POST', 'video_admin_api.php');
    xhr.send(formData);
  };
  
  // Edit Video modal
  const editModal = document.getElementById('editModal');
  const editForm = document.getElementById('editForm');
  const editCloseBtn = document.getElementById('editCloseBtn');
  const editCancelBtn = document.getElementById('editCancelBtn');
  
  editCloseBtn.onclick = editCancelBtn.onclick = () => closeModal(editModal);
  editForm.onsubmit = (e) => {
    e.preventDefault();
    fetch('video_admin_api.php?action=update_video', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        id: document.getElementById('editVideoId').value,
        name: document.getElementById('editName').value,
        description: document.getElementById('editDescription').value,
        category_id: document.getElementById('editCategory').value || null,
        is_active: document.getElementById('editActive').checked ? 1 : 0
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        showToast('Video updated', 'success');
        closeModal(editModal);
        loadVideos();
      } else {
        showToast(data.message, 'error');
      }
    });
  };
  
  // Add to Playlist modal
  const addToPlaylistModal = document.getElementById('addToPlaylistModal');
  const addToPlaylistCloseBtn = document.getElementById('addToPlaylistCloseBtn');
  const addToPlaylistCancelBtn = document.getElementById('addToPlaylistCancelBtn');
  const addToPlaylistSubmitBtn = document.getElementById('addToPlaylistSubmitBtn');
  
  addToPlaylistCloseBtn.onclick = addToPlaylistCancelBtn.onclick = () => closeModal(addToPlaylistModal);
  addToPlaylistSubmitBtn.onclick = () => {
    const videoId = document.getElementById('addToPlaylistVideoId').value;
    const checkedPlaylistIds = Array.from(document.querySelectorAll('#playlistCheckboxes input:checked')).map(cb => cb.value);

    fetch('video_admin_api.php?action=sync_video_playlists', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ video_id: videoId, playlist_ids: checkedPlaylistIds })
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        showToast('Playlists updated successfully', 'success');
        closeModal(addToPlaylistModal);
        loadPlaylists();
      } else {
        showToast(data.message || 'Failed to update playlists', 'error');
      }
    })
    .catch(err => showToast('A network error occurred. Please try again.', 'error'));
  };

  // Playlist Create/Edit Modal Logic
  playlistCloseBtn.onclick = playlistCancelBtn.onclick = () => closeModal(playlistModal);
  playlistForm.onsubmit = (e) => {
    e.preventDefault();
    const id = document.getElementById('editPlaylistId').value;
    const action = id ? 'update_playlist' : 'create_playlist';
    const body = {
      id: id,
      name: document.getElementById('editPlaylistName').value,
      description: document.getElementById('editPlaylistDescription').value
    };
    fetch('video_admin_api.php?action=' + action, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        showToast(`Playlist ${id ? 'updated' : 'created'}`, 'success');
        closeModal(playlistModal);
        loadPlaylists();
      } else {
        showToast(data.message, 'error');
      }
    }).catch(err => showToast('Network error', 'error'));
  };

  // Category Create/Edit Modal Logic
  categoryCloseBtn.onclick = categoryCancelBtn.onclick = () => closeModal(categoryModal);
  categoryForm.onsubmit = (e) => {
    e.preventDefault();
    const id = document.getElementById('editCategoryId').value;
    const action = id ? 'update_category' : 'create_category';
    const body = {
      id: id,
      name: document.getElementById('editCategoryName').value,
      description: document.getElementById('editCategoryDescription').value,
      sort_order: document.getElementById('editCategorySortOrder').value || 0
    };
    fetch('video_admin_api.php?action=' + action, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        showToast(`Category ${id ? 'updated' : 'created'}`, 'success');
        closeModal(categoryModal);
        loadCategories();
      } else {
        showToast(data.message, 'error');
      }
    }).catch(err => showToast('Network error', 'error'));
  };

  // JSON Export Modal Logic
  jsonCloseBtn.onclick = jsonCancelBtn.onclick = () => closeModal(jsonExportModal);
  copyJsonBtn.onclick = () => {
    navigator.clipboard.writeText(jsonOutput.value).then(() => {
      showToast('Copied to clipboard!', 'success');
      copyJsonBtn.textContent = '✓ Copied!';
      setTimeout(() => { copyJsonBtn.textContent = '📋 Copy to Clipboard'; }, 2000);
    }).catch(err => showToast('Failed to copy text', 'error'));
  };

  // --- Global Event Delegation ---
  
  document.addEventListener('click', (e) => {
    const el = e.target;
    const closest = (selector) => el.closest(selector);

    // Video actions
    if (closest('.edit-video-btn')) {
      const item = closest('.video-item');
      const video = videos.find(v => v.id == item.dataset.videoId);
      if (video) {
        document.getElementById('editVideoId').value = video.id;
        document.getElementById('editName').value = video.name;
        document.getElementById('editDescription').value = video.description || '';
        document.getElementById('editCategory').value = video.category_id || '';
        document.getElementById('editActive').checked = video.is_active == 1;
        openModal(editModal);
      }
    }
    if (closest('.delete-video-btn')) {
      const id = closest('.video-item').dataset.videoId;
      const video = videos.find(v => v.id == id);
      if (confirm(`Delete "${video.name}"? This cannot be undone.`)) {
        fetch('video_admin_api.php?action=delete_video', {
          method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id})
        }).then(r => r.json()).then(data => {
          if (data.status === 'ok') { 
            showToast('Video deleted', 'success'); 
            const item = closest('.video-item');
            item.style.transition = 'opacity 0.3s, transform 0.3s';
            item.style.opacity = '0';
            item.style.transform = 'scale(0.95)';
            setTimeout(() => loadVideos(), 300);
          } 
          else { showToast(data.message, 'error'); }
        });
      }
    }
    if (closest('.regen-thumb-btn')) {
      const id = closest('.video-item').dataset.videoId;
      if (!confirm('Regenerate the thumbnail?')) return;
      el.disabled = true; el.textContent = 'Generating...';
      fetch('video_admin_api.php?action=regenerate_thumbnail', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id})
      }).then(r => r.json()).then(data => {
        if (data.status === 'ok') {
          showToast('Thumbnail regenerated!', 'success');
          const thumbImg = closest('.video-item').querySelector('.video-thumb');
          if (thumbImg) thumbImg.src = data.thumbnail_url + `?t=${new Date().getTime()}`;
        } else { showToast(data.message, 'error'); }
      }).catch(err => showToast('A network error occurred.', 'error'))
        .finally(() => { el.disabled = false; el.textContent = 'Thumbnail'; });
    }
    if (closest('.add-playlist-btn')) {
        const id = closest('.video-item').dataset.videoId;
        document.getElementById('addToPlaylistVideoId').value = id;
        const checkboxDiv = document.getElementById('playlistCheckboxes');
        checkboxDiv.innerHTML = playlists.map(pl => `<label style="display:block; padding:8px; cursor:pointer; transition: background 0.15s;"><input type="checkbox" value="${pl.id}" style="margin-right:8px;"> ${escapeHtml(pl.name)}</label>`).join('');
        
        // Add hover effect
        checkboxDiv.querySelectorAll('label').forEach(label => {
          label.addEventListener('mouseenter', () => label.style.background = 'rgba(var(--muted-border-rgb), 0.03)');
          label.addEventListener('mouseleave', () => label.style.background = 'transparent');
        });
        
        fetch('video_admin_api.php?action=get_video&id=' + id).then(r => r.json()).then(data => {
          if (data.status === 'ok' && data.video.playlists) {
            data.video.playlists.forEach(pl => {
              const cb = checkboxDiv.querySelector(`input[value="${pl.id}"]`);
              if (cb) cb.checked = true;
            });
          }
        });
        openModal(addToPlaylistModal);
    }

    // Playlist actions
    if (el.id === 'createPlaylistBtn') {
      playlistForm.reset();
      document.getElementById('editPlaylistId').value = '';
      playlistModalTitle.textContent = 'Create Playlist';
      openModal(playlistModal);
    }
    if (closest('.edit-playlist-btn')) {
      const id = closest('.playlist-drag-item, .playlist-card')?.dataset.playlistId;
      const playlist = playlists.find(p => p.id == id);
      if (playlist) {
        document.getElementById('editPlaylistId').value = playlist.id;
        document.getElementById('editPlaylistName').value = playlist.name;
        document.getElementById('editPlaylistDescription').value = playlist.description || '';
        playlistModalTitle.textContent = 'Edit Playlist';
        openModal(playlistModal);
      }
    }
    if (closest('.delete-playlist-btn')) {
      const id = closest('.playlist-drag-item, .playlist-card')?.dataset.playlistId;
      const playlist = playlists.find(p => p.id == id);
      if (confirm(`Delete playlist "${playlist.name}"? Videos will not be deleted.`)) {
        fetch('video_admin_api.php?action=delete_playlist', {
          method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id})
        }).then(r => r.json()).then(data => {
          if (data.status === 'ok') { 
            showToast('Playlist deleted', 'success'); 
            const item = closest('.playlist-drag-item');
            if (item) {
              item.style.transition = 'opacity 0.3s, transform 0.3s';
              item.style.opacity = '0';
              item.style.transform = 'scale(0.95)';
              setTimeout(() => loadPlaylists(), 300);
            } else {
              loadPlaylists();
            }
          }
        });
      }
    }
    if (closest('.view-playlist-btn')) {
      window.location.href = 'view_video_playlist.php?playlist_id=' + closest('.playlist-drag-item, .playlist-card')?.dataset.playlistId;
    }
    if (closest('.reorder-videos-btn')) {
      window.location.href = 'view_playlist_videos_reorder.php?playlist_id=' + closest('.playlist-drag-item, .playlist-card')?.dataset.playlistId;
    }
    if (closest('.get-playlist-json-btn')) {
      const playlistId = closest('.playlist-drag-item, .playlist-card')?.dataset.playlistId;
      el.disabled = true; 
      const originalText = el.textContent;
      el.textContent = 'Loading...';
      fetch(`video_admin_api.php?action=get_playlist_json&id=${playlistId}`)
        .then(r => r.json())
        .then(data => {
          if (data.status === 'ok') {
            jsonOutput.value = data.json_string;
            openModal(jsonExportModal);
          } else { 
            showToast(data.message, 'error'); 
          }
        })
        .catch(err => showToast('A network error occurred.', 'error'))
        .finally(() => { 
          el.disabled = false; 
          el.textContent = originalText;
        });
    }

    // Category actions
    if (el.id === 'createCategoryBtn') {
      categoryForm.reset();
      document.getElementById('editCategoryId').value = '';
      categoryModalTitle.textContent = 'Create Category';
      openModal(categoryModal);
    }
    if (closest('.edit-category-btn')) {
      const id = closest('.playlist-card').dataset.categoryId;
      const category = categories.find(c => c.id == id);
      if (category) {
        document.getElementById('editCategoryId').value = category.id;
        document.getElementById('editCategoryName').value = category.name;
        document.getElementById('editCategoryDescription').value = category.description || '';
        document.getElementById('editCategorySortOrder').value = category.sort_order || 0;
        categoryModalTitle.textContent = 'Edit Category';
        openModal(categoryModal);
      }
    }
    if (closest('.delete-category-btn')) {
      const id = closest('.playlist-card').dataset.categoryId;
      const category = categories.find(c => c.id == id);
      if (confirm(`Delete category "${category.name}"?`)) {
        fetch('video_admin_api.php?action=delete_category', {
          method: 'POST', 
          headers: {'Content-Type': 'application/json'}, 
          body: JSON.stringify({id})
        })
        .then(r => r.json())
        .then(data => {
          if (data.status === 'ok') { 
            showToast('Category deleted', 'success'); 
            const item = closest('.playlist-card');
            item.style.transition = 'opacity 0.3s, transform 0.3s';
            item.style.opacity = '0';
            item.style.transform = 'scale(0.95)';
            setTimeout(() => loadCategories(), 300);
          } 
          else { 
            showToast(data.message, 'error'); 
          }
        })
        .catch(err => showToast('Network error', 'error'));
      }
    }
  });
  
  // --- Filter Event Listeners ---
  document.getElementById('searchInput').addEventListener('input', debounce(loadVideos, 400));
  document.getElementById('categoryFilter').addEventListener('change', loadVideos);
  document.getElementById('playlistFilter').addEventListener('change', loadVideos);
  
  // Close modals on overlay click
  document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModal(modal);
      }
    });
  });

  // Close modals on ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAllModals();
    }
  });
  
  // --- Initial Load ---
  function initialize() {
    loadCategories();
    loadPlaylists();
    loadVideos();

    // Activate tab from URL parameter
    const params = new URLSearchParams(window.location.search);
    const tabName = params.get('tab');
    if (tabName) {
        const tabToActivate = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
        if (tabToActivate) {
            tabToActivate.click();
        }
    }
  }

  initialize();
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>