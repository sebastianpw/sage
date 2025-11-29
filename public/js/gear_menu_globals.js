// Global gear menu functions (extracted from AbstractGallery)
// Place this in public/js/gear_menu_globals.js

window.importGenerative = window.importGenerative || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing parameters. Import aborted.', 'error');
        return;
    }
    const ajaxUrl = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=generatives&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const resp = await fetch(ajaxUrl, { credentials: 'same-origin' });
        const text = await resp.text(); let data;
        try { data = JSON.parse(text); } catch(e) { Toast.show('Import failed: invalid response','error'); console.error(text); return; }
        if ((data.status && data.status === 'ok') || Array.isArray(data.result)) {
            const msg = Array.isArray(data.result) ? data.result.join("\n") : (data.message || 'Import triggered');
            Toast.show(`Import triggered for ${entity} #${entityId}: ${msg}`, 'info');
        } else {
            Toast.show(`Import failed for ${entity} #${entityId}`, 'error');
            console.warn('importGenerative: unexpected payload', data);
        }
    } catch (err) {
        Toast.show('Import failed', 'error'); console.error(err);
    }
});

window.editEntity = window.editEntity || (function(entity, entityId, frameId) {
    if (!entity || !entityId) {
        Toast.show('Missing entity or entityId. Cannot edit.', 'error');
        return;
    }
    
    // Build redirect URL based on current page
    let redirectUrl = window.location.pathname;
    
    // If we're on a frame detail page, preserve the frame_id
    if (frameId && window.location.pathname.includes('view_frame')) {
        redirectUrl = `view_frame.php?frame_id=${frameId}`;
    } 
    // If we're on a gallery page, go back to the gallery
    else if (window.location.pathname.includes('gallery_')) {
        redirectUrl = `gallery_${entity}_nu.php`;
    }
    
    // Navigate to entity form
    const editUrl = `/entity_form.php?entity_type=${encodeURIComponent(entity)}&entity_id=${encodeURIComponent(entityId)}&redirect_url=${encodeURIComponent(redirectUrl)}`;
    window.location.href = editUrl;
});

window._storyboardsCache = null;

window.loadStoryboards = async function() {
    if (window._storyboardsCache) return window._storyboardsCache;
    try {
        const resp = await fetch('/storyboards_v2_api.php?action=list', { credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            window._storyboardsCache = data.data;
            return data.data;
        }
    } catch (err) {
        console.error('loadStoryboards error:', err);
    }
    return [];
};

window.importToStoryboard = async function(frameId, storyboardId) {
    if (!frameId || !storyboardId) {
        Toast.show('Missing parameters', 'error');
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('storyboard_id', storyboardId);
    formData.append('frame_id', frameId);
    
    try {
        const resp = await fetch('/storyboard_import.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
            Toast.show(data.message || 'Frame imported to storyboard', 'success');
            window._storyboardsCache = null;
            return data;
        } else {
            Toast.show('Import failed: ' + (data.message || 'unknown error'), 'error');
        }
    } catch (err) {
        Toast.show('Import failed', 'error');
        console.error(err);
    }
};

window.selectStoryboard = async function(frameId, $trigger) {
    const storyboards = await window.loadStoryboards();

    if (storyboards.length === 0) {
        Toast.show('No storyboards available', 'warning');
        return;
    }

    $('.sb-menu').remove();
    const $menu = $('<div class="sb-menu"></div>');

    storyboards.forEach(sb => {
        const $item = $(`
            <div class="sb-menu-item" data-sb-id="${sb.id}">
                ${sb.name} <span style="color:#999">(${sb.frame_count})</span>
            </div>
        `);

        $item.on('click', async function(e) {
            e.stopPropagation();
            $('.sb-menu').remove();
            await window.importToStoryboard(frameId, sb.id);
        });

        $menu.append($item);
    });

    $menu.append('<div class="sb-menu-sep"></div>');
    $menu.append(`
        <div class="sb-menu-item" onclick="window.open('/view_storyboards.php','_self')">
            📋 Manage Storyboards
        </div>
    `);

    const pos = $trigger ? $trigger.offset() : { top: 100, left: 100 };
    $menu.css({
        position: 'absolute',
        top: pos.top + 30,
        left: pos.left,
        zIndex: 10000
    });

    $('body').append($menu);
    setTimeout(() => {
        $(document).one('click', () => $('.sb-menu').remove());
    }, 100);
};

window.importControlNetMap = window.importControlNetMap || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing parameters. Import aborted.', 'error');
        return;
    }
    const ajaxUrl = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=controlnet_maps&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const resp = await fetch(ajaxUrl, { credentials: 'same-origin' });
        const text = await resp.text(); 
        let data;
        try { 
            data = JSON.parse(text); 
        } catch(e) { 
            Toast.show('Import failed: invalid response','error'); 
            console.error(text); 
            return; 
        }
        if ((data.status && data.status === 'ok') || Array.isArray(data.result)) {
            const msg = Array.isArray(data.result) ? data.result.join("\n") : (data.message || 'Import triggered');
            Toast.show(`Import triggered for ${entity} #${entityId}: ${msg}`, 'info');
        } else {
            Toast.show(`Import failed for ${entity} #${entityId}`, 'error');
            console.warn('importControlNetMap: unexpected payload', data);
        }
    } catch (err) {
        Toast.show('Import failed', 'error'); 
        console.error(err);
    }
});

window.assignToComposite = window.assignToComposite || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing entity or entityId or frameId.', 'error');
        return;
    }
    const hrefUrl = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=${encodeURIComponent('composites')}&copy_name_desc=0&composite=1`;
    window.location.href = hrefUrl;
});

window.usePromptMatrix = window.usePromptMatrix || (async function(entity, entityId, frameId){
    if (!entityId || !entity) {
        Toast.show('Missing entity or entityId.', 'error');
        return;
    }
    const hrefUrl = `/view_prompt_matrix.php?entity_type=${encodeURIComponent(entity)}&entity_id=${encodeURIComponent(entityId)}`;
    window.location.href = hrefUrl;
});

window.deleteFrame = window.deleteFrame || (async function(entity, entityId, frameId) {
    if (!entity || !entityId || !frameId) { 
        alert("Missing parameters. Cannot delete frame."); 
        return; 
    }
    if (!confirm("Are you sure you want to delete this frame?")) return;
    try {
        const response = await fetch(`/delete_frames_from_entity.php?ajax=1&method=single&frame_id=${frameId}`, { 
            method: 'POST', 
            credentials:'same-origin' 
        });
        const text = await response.text(); 
        let result;
        try { 
            result = JSON.parse(text); 
        } catch(e){ 
            throw new Error("Invalid server response: "+text); 
        }
        if (result.status === "ok") {
            $(`.img-wrapper[data-frame-id="${frameId}"]`).remove();
            $(`.frame-image-wrapper[data-frame-id="${frameId}"]`).remove();
            Toast.show('Frame deleted','success');
        } else {
            alert("Failed to delete frame: " + (result.message || 'unknown'));
        }
    } catch (err) {
        alert("Delete failed: " + (err.message || err));
        console.error(err);
    }
});

window.assignControlNetMap = window.assignControlNetMap || (async function(entity, entityId, frameId, targetEntity){
    // Only allow controlnet_maps
    if (entity !== 'controlnet_maps') {
        Toast.show('Only controlnet_maps can be assigned.', 'error');
        return;
    }

    if (!entityId || !frameId) {
        Toast.show('Missing entityId or frameId.', 'error');
        return;
    }

    if (!targetEntity || !['characters','generatives','sketches'].includes(targetEntity)) {
        Toast.show('Invalid target entity.', 'error');
        return;
    }

    // Redirect to your import form with prefilled parameters
    const hrefUrl = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=${encodeURIComponent(targetEntity)}&copy_name_desc=0&controlnet=1`;
    window.location.href = hrefUrl;
});
