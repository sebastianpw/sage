<?php
// public/view_playlist_videos_reorder.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$playlistId = (int)($_GET['playlist_id'] ?? 0);

if (!$playlistId) {
    header('Location: view_video_admin.php');
    exit;
}

// Get playlist info
$stmt = $pdo->prepare("SELECT * FROM video_playlists WHERE id = ?");
$stmt->execute([$playlistId]);
$playlist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$playlist) {
    header('Location: view_video_admin.php');
    exit;
}

$pageTitle = "Reorder Videos - " . $playlist['name'];
ob_start();
?>

<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">

<style>
.reorder-wrap {
    max-width: 900px;
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

.playlist-title {
    color: var(--text-muted);
    font-size: 0.95rem;
    margin-top: 4px;
}

.video-list {
    background: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 12px;
    box-shadow: var(--card-elevation);
}

.video-item {
    background: var(--bg);
    border: 2px solid rgba(var(--muted-border-rgb), 0.12);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    cursor: move;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 12px;
    touch-action: pan-y;
    user-select: none;
    will-change: transform;
}

.video-item:last-child {
    margin-bottom: 0;
}

.video-item:hover:not(.dragging) {
    border-color: var(--accent);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.video-item.dragging {
    opacity: 0.7;
    cursor: grabbing;
    transform: scale(1.03);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
    z-index: 1000;
    border-color: var(--accent);
}

.video-item.drag-over {
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
    flex-shrink: 0;
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

.video-thumbnail {
    width: 120px;
    height: 68px;
    object-fit: cover;
    border-radius: 4px;
    flex-shrink: 0;
    background: rgba(0,0,0,0.1);
}

.video-info {
    flex: 1;
    min-width: 0;
}

.video-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text);
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.video-meta {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.sort-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-muted);
    min-width: 40px;
    text-align: center;
    flex-shrink: 0;
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
    
    .video-item {
        padding: 10px;
        gap: 10px;
    }
    
    .video-thumbnail {
        width: 80px;
        height: 45px;
    }
    
    .video-name {
        font-size: 0.9rem;
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
    .video-item {
        padding: 14px;
        margin-bottom: 14px;
    }
    
    .drag-handle {
        padding: 12px;
    }
}
</style>

<div class="reorder-wrap">
    <div class="reorder-header">
        <div>
            <h2>Reorder Videos</h2>
            <div class="playlist-title"><?= htmlspecialchars($playlist['name']) ?></div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            
            <a href="view_video_playlist.php?playlist_id=<?= $playlistId ?>" class="btn btn-secondary btn-sm">View Playlist</a>
            <a href="view_video_admin.php" class="btn btn-secondary btn-sm">Back to Admin</a>
            
            
           
        </div>
        
         <div style="background: var(--card); position:absolute; top:10px; left:10px;" class="save-indicator" id="saveIndicator">
                <div class="loader-dot"></div>
                <span>Saving...</span>
            </div>
        
        
    </div>

    <div class="notification notification-info" style="margin-bottom: 20px;">
        <strong>Drag and drop</strong> videos to reorder them in this playlist. Changes are saved automatically.
    </div>

    <div class="video-list" id="videoList">
        <div class="empty-state">Loading videos...</div>
    </div>
</div>

<script src="js/toast.js"></script>
<script>
(function() {
    const playlistId = <?= $playlistId ?>;
    let videos = [];
    let draggedElement = null;
    let draggedIndex = null;
    let saveTimeout = null;

    const videoList = document.getElementById('videoList');
    const saveIndicator = document.getElementById('saveIndicator');

    function showToast(msg, type) {
        if (typeof Toast !== 'undefined' && Toast.show) {
            Toast.show(msg, type === 'error' ? 'error' : 'success');
        } else {
            console.log('[toast]', msg);
        }
    }

    function formatDuration(seconds) {
        if (!seconds) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    function loadVideos() {
        fetch('video_playlist_api.php?playlist_id=' + playlistId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    videos = data.videos || [];
                    renderVideos();
                } else {
                    showToast('Failed to load videos', 'error');
                    videoList.innerHTML = '<div class="empty-state">Failed to load videos</div>';
                }
            })
            .catch(err => {
                console.error('Failed to load videos', err);
                showToast('Network error', 'error');
                videoList.innerHTML = '<div class="empty-state">Network error</div>';
            });
    }

    function renderVideos() {
        if (!videos.length) {
            videoList.innerHTML = '<div class="empty-state">No videos in this playlist yet. Add videos from the admin panel.</div>';
            return;
        }

        videoList.innerHTML = videos.map((video, index) => `
            <div class="video-item" draggable="true" data-id="${video.id}" data-index="${index}">
                <div class="sort-number">${index + 1}</div>
                <div class="drag-handle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <img src="${video.thumbnail}" 
                     alt="${video.title}" 
                     class="video-thumbnail"
                     onerror="this.src='/vendor/video-js.png'">
                <div class="video-info">
                    <div class="video-name" title="${video.title}">${video.title}</div>
                    <div class="video-meta">Duration: ${formatDuration(video.duration)}</div>
                </div>
            </div>
        `).join('');

        attachDragListeners();
    }

    let isDragging = false;
    let currentDropTarget = null;

    function attachDragListeners() {
        const items = videoList.querySelectorAll('.video-item');

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
        document.querySelectorAll('.video-item').forEach(item => {
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
            reorderVideos(draggedIndex, targetIndex);
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
            const items = [...videoList.querySelectorAll('.video-item:not(.dragging)')];
            
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
            reorderVideos(draggedIndex, targetIndex);
        }

        // Clean up
        document.querySelectorAll('.video-item').forEach(item => {
            item.classList.remove('drag-over');
        });

        touchElement = null;
        draggedElement = null;
        currentDropTarget = null;
        hasMoved = false;
    }

    function reorderVideos(fromIndex, toIndex) {
        if (fromIndex === toIndex) return;

        // Reorder array
        const [movedItem] = videos.splice(fromIndex, 1);
        videos.splice(toIndex, 0, movedItem);

        // Re-render
        renderVideos();

        // Save after a short delay (debounce)
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveOrder, 500);
    }

    function saveOrder() {
        saveIndicator.classList.add('show');

        const orderedIds = videos.map(v => v.id);

        fetch('video_admin_api.php?action=update_playlist_video_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                playlist_id: playlistId,
                video_ids: orderedIds 
            })
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
    loadVideos();
})();
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
?>
