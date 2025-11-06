<?php
/**
 * Usage: require 'modal_entity_details.php';
 * Then call: showEntityDetailsModal('sketches', 123);
 */
?>
<style>
.entity-modal-overlay {
    display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.9); z-index:9999; overflow-y:auto; padding:20px;
}
.entity-modal-overlay.active { display:block; }
.entity-modal-container { max-width:1400px; margin:0 auto; background:#000; border-radius:8px; position:relative; min-height:200px; }
.entity-modal-close { position:fixed; top:30px; right:30px; width:40px; height:40px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:50%; color:#fff; font-size:24px; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:10001; }
.entity-modal-close:hover { transform:scale(1.06); background:rgba(135,206,235,0.08); border-color:#87CEEB; }
.entity-modal-content { padding:20px; }
.entity-modal-loading { text-align:center; padding:60px 20px; color:#888; }
.entity-modal-spinner { width:40px; height:40px; border:3px solid #333; border-top-color:#87CEEB; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 20px; }
@keyframes spin { to { transform: rotate(360deg); } }
.entity-modal-view-full { position:fixed; bottom:30px; right:30px; padding:12px 20px; background:#87CEEB; color:#000; border:none; border-radius:4px; font-weight:600; text-decoration:none; z-index:10001; display:inline-block; }
</style>

<div id="entityModalOverlay" class="entity-modal-overlay">
    <div class="entity-modal-close" onclick="closeEntityModal()">&times;</div>
    <div class="entity-modal-container">
        <div id="entityModalContent" class="entity-modal-content">
            <div class="entity-modal-loading">
                <div class="entity-modal-spinner"></div>
                <div>Loading details...</div>
            </div>
        </div>
    </div>
    <a id="entityModalViewFull" class="entity-modal-view-full" href="#" style="display:none;">View Full Page</a>
</div>

<script>
let currentEntityModal = { entity: null, id: null };

/**
 * Show generic entity details in modal
 * @param {string} entity - table name (plural)
 * @param {number} id
 */
function showEntityDetailsModal(entity, id) {
    currentEntityModal.entity = entity;
    currentEntityModal.id = id;

    const overlay = document.getElementById('entityModalOverlay');
    const content = document.getElementById('entityModalContent');
    const viewFull = document.getElementById('entityModalViewFull');

    content.innerHTML = `
        <div class="entity-modal-loading">
            <div class="entity-modal-spinner"></div>
            <div>Loading details...</div>
        </div>
    `;
    overlay.classList.add('active');
    viewFull.style.display = 'none';
    document.body.style.overflow = 'hidden';

    fetch(`view_entity.php?entity=${encodeURIComponent(entity)}&id=${encodeURIComponent(id)}&view=modal`)
        .then(r => {
            if (!r.ok) throw new Error('Failed to load');
            return r.text();
        })
        .then(html => {
            // try to extract .entity-details-container
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            const container = tmp.querySelector('.entity-details-container');
            if (container) {
                content.innerHTML = container.outerHTML;
                // init scripts if any
                if (typeof initializeEntityDetailsScripts === 'function') {
                    initializeEntityDetailsScripts();
                }
                viewFull.href = `view_entity.php?entity=${encodeURIComponent(entity)}&id=${encodeURIComponent(id)}`;
                viewFull.style.display = 'inline-block';
            } else {
                content.innerHTML = html;
            }
        })
        .catch(err => {
            content.innerHTML = `<div style="padding:30px;color:#c53030"><h2>Error</h2><p>${err.message}</p></div>`;
        });
}

function closeEntityModal() {
    document.getElementById('entityModalOverlay').classList.remove('active');
    document.body.style.overflow = '';
    currentEntityModal = { entity: null, id: null };
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && currentEntityModal.id !== null) closeEntityModal();
});

document.getElementById('entityModalOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) closeEntityModal();
});
</script>
