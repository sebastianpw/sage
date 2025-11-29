<?php
// public/view_playlists_reorder.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Reorder Playlists";
ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">

<style>
.reorder-wrap {
    max-width: 800px;
    margin: 0 auto;
    padding: 18px;
}

.reorder-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}

.reorder-header h2 {
    margin: 0;
    font-weight: 600;
    font-size: 1.3rem;
    color: var(--text);
}

.playlist-list {
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 12px;
    box-shadow: var(--card-elevation);
}

.playlist-item {
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
    will-change: transform;
}

.playlist-item:last-child {
    margin-bottom: 0;
}

.playlist-item:hover:not(.dragging) {
    border-color: var(--accent);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.playlist-item.dragging {
    opacity: 0.7;
    cursor: grabbing;
    transform: scale(1.03);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
    z-index: 1000;
    border-color: var(--accent);
}

.playlist-item.drag-over {
    border-color: var(--green);
    background: rgba(35, 134, 54, 0.08);
    transform: scale(1.02);
}

.drag-handle {
    display: flex;
    flex-direction: column;
    gap: 3px;
    cursor: grab;
    padding: 8px;
    color: var(--text-muted);
    transition: color 0.2s;
}

.drag-handle:active {
    cursor: grabbing;
}

.drag-handle:hover {
    color: var(--accent);
}

.drag-handle span {
    display: block;
    width: 24px;
    height: 3px;
    background: currentColor;
    border-radius: 2px;
}

.playlist-info {
    flex: 1;
    min-width: 0;
}

.playlist-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--text);
    margin-bottom: 4px;
}

.playlist-meta {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.sort-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-muted);
    min-width: 40px;
    text-align: center;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.save-indicator {
    display: none;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--blue-light-bg);
    color: var(--blue-light-text);
    border: 1px solid var(--blue-light-border);
    border-radius: 6px;
    font-size: 0.9rem;
}

.save-indicator.show {
    display: flex;
}

.loader-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--accent);
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.4; transform: scale(0.8); }
    50% { opacity: 1; transform: scale(1); }
}

/* Mobile optimizations */
@media (max-width: 640px) {
    .reorder-wrap {
        padding: 12px;
    }
    
    .playlist-item {
        padding: 12px;
        gap: 12px;
    }
    
    .playlist-name {
        font-size: 0.95rem;
    }
    
    .sort-number {
        font-size: 1rem;
        min-width: 32px;
    }
    
    .drag-handle {
        padding: 4px;
    }
    
    .drag-handle span {
        width: 20px;
    }
}

/* Touch device improvements */
@media (hover: none) and (pointer: coarse) {
    .playlist-item {
        padding: 18px;
        margin-bottom: 16px;
    }
    
    .drag-handle {
        padding: 12px;
    }
}
</style>

<div class="reorder-wrap">
    <div class="reorder-header">
        <h2>Reorder Playlists</h2>
        <div style="display: flex; gap: 8px; align-items: center;">
            <div class="save-indicator" id="saveIndicator">
                <div class="loader-dot"></div>
                <span>Saving...</span>
            </div>
            <a href="view_video_admin.php" class="btn btn-secondary btn-sm">Back to Admin</a>
        </div>
    </div>

    <div class="notification notification-info" style="margin-bottom: 20px;">
        <strong>Drag and drop</strong> playlists to reorder them. Changes are saved automatically.
    </div>

    <div class="playlist-list" id="playlistList">
        <div class="empty-state">Loading playlists...</div>
    </div>
</div>

<script src="js/toast.js"></script>
<script>
(function() {
    let playlists = [];
    let draggedElement = null;
    let draggedIndex = null;
    let saveTimeout = null;
    let isDragging = false;
    let currentDropTarget = null;

    const playlistList = document.getElementById('playlistList');
    const saveIndicator = document.getElementById('saveIndicator');

    function showToast(msg, type) {
        if (typeof Toast !== 'undefined' && Toast.show) {
            Toast.show(msg, type === 'error' ? 'error' : 'success');
        } else {
            console.log('[toast]', msg);
        }
    }

    function loadPlaylists() {
        fetch('video_admin_api.php?action=list_playlists')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    playlists = data.playlists;
                    renderPlaylists();
                } else {
                    showToast('Failed to load playlists', 'error');
                }
            })
            .catch(err => {
                console.error('Failed to load playlists', err);
                showToast('Network error', 'error');
            });
    }

    function renderPlaylists() {
        if (!playlists.length) {
            playlistList.innerHTML = '<div class="empty-state">No playlists found. Create one in the admin panel.</div>';
            return;
        }

        playlistList.innerHTML = playlists.map((pl, index) => `
            <div class="playlist-item" draggable="true" data-id="${pl.id}" data-index="${index}">
                <div class="sort-number">${index + 1}</div>
                <div class="drag-handle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="playlist-info">
                    <div class="playlist-name">${pl.name}</div>
                    <div class="playlist-meta">${pl.video_count} video${pl.video_count !== 1 ? 's' : ''}</div>
                </div>
            </div>
        `).join('');

        attachDragListeners();
    }

    function attachDragListeners() {
        const items = playlistList.querySelectorAll('.playlist-item');

        items.forEach(item => {
            // Desktop drag events
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragend', handleDragEnd);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('drop', handleDrop);

            // Touch events for mobile
            item.addEventListener('touchstart', handleTouchStart, { passive: false });
            item.addEventListener('touchmove', handleTouchMove, { passive: false });
            item.addEventListener('touchend', handleTouchEnd);
            item.addEventListener('touchcancel', handleTouchEnd);
        });
    }

    // Desktop drag handlers
    function handleDragStart(e) {
        isDragging = true;
        draggedElement = this;
        draggedIndex = parseInt(this.dataset.index);
        this.classList.add('dragging');
        
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', draggedIndex);
        
        // Create a ghost image to improve dragging experience
        if (e.dataTransfer.setDragImage) {
            const ghost = this.cloneNode(true);
            ghost.style.opacity = '0.8';
            document.body.appendChild(ghost);
            e.dataTransfer.setDragImage(ghost, 0, 0);
            setTimeout(() => document.body.removeChild(ghost), 0);
        }
    }

    function handleDragEnd(e) {
        isDragging = false;
        this.classList.remove('dragging');
        document.querySelectorAll('.playlist-item').forEach(item => {
            item.classList.remove('drag-over');
        });
        currentDropTarget = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        if (!isDragging || this === draggedElement) return;
        
        // Remove previous highlight
        if (currentDropTarget && currentDropTarget !== this) {
            currentDropTarget.classList.remove('drag-over');
        }
        
        // Add highlight to current target
        this.classList.add('drag-over');
        currentDropTarget = this;
        
        return false;
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();

        if (draggedElement && this !== draggedElement) {
            const targetIndex = parseInt(this.dataset.index);
            reorderPlaylists(draggedIndex, targetIndex);
        }

        return false;
    }

    // Touch handlers for mobile with improved logic
    let touchStartY = 0;
    let touchCurrentY = 0;
    let touchElement = null;
    let touchStartX = 0;
    let hasMoved = false;
    let scrollThreshold = 10;

    function handleTouchStart(e) {
        const touch = e.touches[0];
        touchElement = this;
        draggedElement = this;
        draggedIndex = parseInt(this.dataset.index);
        touchStartY = touch.clientY;
        touchStartX = touch.clientX;
        touchCurrentY = touch.clientY;
        hasMoved = false;
        
        // Delay adding dragging class to prevent accidental drags
        setTimeout(() => {
            if (touchElement && hasMoved) {
                touchElement.classList.add('dragging');
            }
        }, 100);
    }

    function handleTouchMove(e) {
        if (!touchElement) return;
        
        const touch = e.touches[0];
        const deltaY = Math.abs(touch.clientY - touchStartY);
        const deltaX = Math.abs(touch.clientX - touchStartX);
        
        // Only treat as drag if vertical movement is significant and greater than horizontal
        if (deltaY > scrollThreshold && deltaY > deltaX) {
            e.preventDefault();
            hasMoved = true;
            touchElement.classList.add('dragging');
            
            touchCurrentY = touch.clientY;
            const deltaFromStart = touchCurrentY - touchStartY;

            // Visual feedback with smooth transform
            touchElement.style.transform = `translateY(${deltaFromStart}px)`;
            touchElement.style.transition = 'none';

            // Find and highlight drop target
            const items = [...playlistList.querySelectorAll('.playlist-item:not(.dragging)')];
            
            // Remove all highlights first
            items.forEach(item => item.classList.remove('drag-over'));
            
            // Find the item we're hovering over
            let targetItem = null;
            for (const item of items) {
                const rect = item.getBoundingClientRect();
                const itemMiddle = rect.top + rect.height / 2;
                
                // Check if touch point is within this item's bounds
                if (touchCurrentY >= rect.top && touchCurrentY <= rect.bottom) {
                    targetItem = item;
                    break;
                }
            }
            
            if (targetItem) {
                targetItem.classList.add('drag-over');
                currentDropTarget = targetItem;
            }
        }
    }

    function handleTouchEnd(e) {
        if (!touchElement) return;

        touchElement.classList.remove('dragging');
        touchElement.style.transform = '';
        touchElement.style.transition = '';

        // Only reorder if we actually moved
        if (hasMoved && currentDropTarget && currentDropTarget !== touchElement) {
            const targetIndex = parseInt(currentDropTarget.dataset.index);
            reorderPlaylists(draggedIndex, targetIndex);
        }

        // Clean up
        document.querySelectorAll('.playlist-item').forEach(item => {
            item.classList.remove('drag-over');
        });

        touchElement = null;
        draggedElement = null;
        currentDropTarget = null;
        hasMoved = false;
    }

    function reorderPlaylists(fromIndex, toIndex) {
        if (fromIndex === toIndex) return;

        // Reorder array
        const [movedItem] = playlists.splice(fromIndex, 1);
        playlists.splice(toIndex, 0, movedItem);

        // Re-render
        renderPlaylists();

        // Save after a short delay (debounce)
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveOrder, 500);
    }

    function saveOrder() {
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
                showToast('Order saved successfully', 'success');
            } else {
                showToast(data.message || 'Failed to save order', 'error');
            }
        })
        .catch(err => {
            console.error('Failed to save order', err);
            showToast('Network error', 'error');
        })
        .finally(() => {
            setTimeout(() => {
                saveIndicator.classList.remove('show');
            }, 1000);
        });
    }

    // Initialize
    loadPlaylists();
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
?>
