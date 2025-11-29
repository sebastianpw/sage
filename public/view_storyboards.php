<?php
// view_storyboards.php - Overview of all storyboards (CRUD interface)
require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Storyboards";
ob_start();
?>

<!-- PhotoSwipe CSS -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<?php endif; ?>

<!-- Font Awesome -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
<?php endif; ?>




<link rel="stylesheet" href="/css/base.css" />



<style>
.storyboards-wrap { padding: 12px; }
.storyboards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 16px; }

/* card */
.storyboard-card { 
    background: var(--card, #ffffff); 
    border-radius: var(--radius, 8px); 
    box-shadow: var(--shadow-sm, 0 2px 6px rgba(0,0,0,0.1)); 
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.storyboard-card:hover { 
    transform: translateY(-2px); 
    box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.15)); 
}

/* thumbnail */
.storyboard-thumb { 
    width: 100%; 
    height: 150px; 
    object-fit: cover; 
    background: var(--card-weak);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
}
.storyboard-thumb img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
}

/* info */
.storyboard-info { padding: 12px; }
.storyboard-title { 
    font-weight: 600; 
    font-size: 14px; 
    margin-bottom: 4px;
    color: var(--accent);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.storyboard-meta { 
    font-size: 12px; 
    color: var(--muted); 
    margin-bottom: 8px;
}
.storyboard-actions { 
    display: flex; 
    gap: 6px; 
    justify-content: space-between;
}

/* toolbar */
.toolbar { 
    display: flex; 
    gap: 10px; 
    align-items: center; 
    margin-bottom: 16px; 
    flex-wrap: wrap;
}

/* modal / overlay */
.modal { 
    display: none; 
    position: fixed; 
    z-index: 1000; 
    left: 0; 
    top: 0; 
    width: 100%; 
    height: 100%; 
    background: var(--overlay);
    align-items: center;
    justify-content: center;
}
.modal.active { display: flex; }
.modal-content { 
    background: var(--card); 
    padding: 24px; 
    border-radius: var(--radius, 12px); 
    max-width: 500px; 
    width: 90%;
    box-shadow: var(--shadow-lg, 0 4px 20px rgba(0,0,0,0.3));
    color: var(--accent, #111);
}
.modal-header { 
    font-size: 18px; 
    font-weight: 600; 
    margin-bottom: 16px;
}

/* forms */
.form-group { margin-bottom: 14px; }
.form-group label { 
    display: block; 
    font-size: 13px; 
    margin-bottom: 6px; 
    font-weight: 500;
    color: var(--text);
}
.form-group input, .form-group textarea { 
    width: 100%; 
    padding: 8px; 
    border: 1px solid var(--border, #ddd); 
    border-radius: var(--radius, 6px); 
    font-size: 14px;
    box-sizing: border-box;
    background: var(--input-bg, transparent);
    color: var(--accent, #111);
}
.form-group textarea { resize: vertical; min-height: 80px; }

/* modal actions */
.modal-actions { 
    display: flex; 
    gap: 8px; 
    justify-content: flex-end; 
    margin-top: 20px;
}

/* empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted, #999);
}
.empty-state i { font-size: 48px; margin-bottom: 16px; }

/* responsive tweak */
@media(min-width: 600px) {
    .storyboards-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
}
</style>

<div class="view-container storyboards-wrap">


<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;margin-left:45px;"> 
    <h2><?php echo htmlspecialchars($pageTitle); ?></h2>

<a style="display:none;" href="dashboard.php" class="btn">
<i class="fa fa-arrow-left"></i>
Dashboard
</a>

</div>

    
    <div class="toolbar">
        <button id="btn-create" class="btn btn-outline-primary">
            <i class="fa fa-plus"></i> Create New Storyboard
        </button>
        <div style="flex: 1;"></div>
        <div id="status-msg" style="font-size: 13px; color: #666;"></div>
    </div>

    <div id="storyboards-grid" class="storyboards-grid">
        <!-- Populated by JavaScript -->
    </div>

    <div id="empty-state" class="empty-state" style="display: none;">
        <i class="fa fa-film"></i>
        <p>No storyboards yet. Create your first one!</p>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="modal-edit" class="modal">
    <div class="modal-content card">
        <div class="modal-header" id="modal-title">Create Storyboard</div>
        <form id="form-storyboard">
            <input type="hidden" id="edit-id" value="">
            <div class="form-group">
                <label>Name *</label>
                <input class="form-control" type="text" id="edit-name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" id="edit-description"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" id="btn-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<?= $spw->getJquery() ?>

<!-- PhotoSwipe v5 -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
  <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
  <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>

<script>
$(function() {
    let storyboards = [];

    function loadStoryboards() {
        $.get('storyboards_v2_api.php?action=list')
            .done(function(res) {
                if (res.success) {
                    storyboards = res.data;
                    renderStoryboards();
                } else {
                    showStatus('Failed to load storyboards', true);
                }
            })
            .fail(function() {
                showStatus('Server error', true);
            });
    }

    function renderStoryboards() {
        const $grid = $('#storyboards-grid');
        const $empty = $('#empty-state');
        
        if (storyboards.length === 0) {
            $grid.hide();
            $empty.show();
            return;
        }
        
        $grid.show();
        $empty.hide();
        $grid.empty();
        
        storyboards.forEach(sb => {
            const thumbHtml = sb.thumbnail 
                ? `<img src="${sb.thumbnail}" alt="${escapeHtml(sb.name)}" loading="lazy">`
                : '<i class="fa fa-image"></i> &nbsp;No frames rendered (visit or add)';
            
            const updatedDate = new Date(sb.updated_at).toLocaleDateString();
            
            const $card = $(`
                <div class="storyboard-card" data-id="${sb.id}">
                    <div class="storyboard-thumb">${thumbHtml}</div>
                    <div class="storyboard-info">
                        <div class="storyboard-title" title="${escapeHtml(sb.name)}">
                            ${escapeHtml(sb.name)}
                        </div>
                        <div class="storyboard-meta">
                            ${sb.frame_count} frames · ${updatedDate}
                        </div>
                        <div class="storyboard-actions">
                            <button class="btn btn-detailview" data-id="${sb.id}">
                                <i class="fa fa-eye"></i> Edit View
				</button>





				<button class="btn btn-magic" data-id="${sb.id}">
                                <i class="fa fa-scroll"></i> View ScrollMagic                                                                                  </button>




                            <button class="btn btn-edit" data-id="${sb.id}">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-delete" data-id="${sb.id}">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            $grid.append($card);
        });
    }

    function showStatus(msg, isError = false) {
        $('#status-msg').text(msg).css('color', isError ? '#f44336' : '#666');
        if (!isError) {
            setTimeout(() => $('#status-msg').text(''), 3000);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $('#btn-create').on('click', function() {
        $('#modal-title').text('Create Storyboard');
        $('#edit-id').val('');
        $('#edit-name').val('');
        $('#edit-description').val('');
        $('#modal-edit').addClass('active');
    });







    $(document).on('click', '.btn-magic', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const sb = storyboards.find(s => s.id == id);
        if (!sb) return;
        window.location = "/view_scrollmagic_dir.php?dir="+sb.directory+"&refresh=true";
    });





    $(document).on('click', '.btn-edit', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const sb = storyboards.find(s => s.id == id);
        if (!sb) return;
        
        $('#modal-title').text('Edit Storyboard');
        $('#edit-id').val(sb.id);
        $('#edit-name').val(sb.name);
        $('#edit-description').val(sb.description || '');
        $('#modal-edit').addClass('active');
    });

    $('#btn-cancel').on('click', function() {
        $('#modal-edit').removeClass('active');
    });

    $('#form-storyboard').on('submit', function(e) {
        e.preventDefault();
        
        const id = $('#edit-id').val();
        const name = $('#edit-name').val().trim();
        const description = $('#edit-description').val().trim();
        
        if (!name) {
            alert('Name is required');
            return;
        }
        
        const action = id ? 'update' : 'create';
        const data = { action, name, description };
        if (id) data.id = id;
        
        $.post('storyboards_v2_api.php', data)
            .done(function(res) {
                if (res.success) {
                    $('#modal-edit').removeClass('active');
                    showStatus(id ? 'Storyboard updated' : 'Storyboard created');
                    loadStoryboards();
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Server error');
            });
    });

    $(document).on('click', '.btn-delete', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const sb = storyboards.find(s => s.id == id);
        if (!sb) return;
        
        if (!confirm(`Delete storyboard "${sb.name}"?\n\nThis will remove all frame references from the database.`)) {
            return;
        }
        
        $.post('storyboards_v2_api.php', { action: 'delete', id: id })
            .done(function(res) {
                if (res.success) {
                    showStatus('Storyboard deleted');
                    loadStoryboards();
                } else {
                    alert('Delete failed: ' + (res.message || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Server error');
            });
    });

    $(document).on('click', '.storyboard-card', function(e) {
        if ($(e.target).hasClass('btn') || $(e.target).closest('.btn').length) {
            return;
        }
        const id = $(this).data('id');
        window.location.href = 'view_storyboard.php?id=' + id;
    });




    $(document).on('click', '.btn-detailview', function(e) {

	    /*
        if ($(e.target).hasClass('btn') || $(e.target).closest('.btn').length) {
            return;
    }
	     */

        const id = $(this).data('id');
        window.location.href = 'view_storyboard.php?id=' + id;
    });








    loadStoryboards();
});
</script>

<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
?>
