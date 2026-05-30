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

window.importAnimatic = window.importAnimatic || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) {
        Toast.show('Missing parameters. Import aborted.', 'error');
        return;
    }
    const ajaxUrl = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=animatics&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const resp = await fetch(ajaxUrl, { credentials: 'same-origin' });
        const text = await resp.text(); let data;
        try { data = JSON.parse(text); } catch(e) { Toast.show('Import failed: invalid response','error'); console.error(text); return; }
        if ((data.status && data.status === 'ok') || Array.isArray(data.result)) {
            const msg = Array.isArray(data.result) ? data.result.join("\n") : (data.message || 'Import triggered');
            Toast.show(`Import triggered for ${entity} #${entityId}: ${msg}`, 'info');
        } else {
            Toast.show(`Import failed for ${entity} #${entityId}`, 'error');
            console.warn('importAnimatic: unexpected payload', data);
        }
    } catch (err) {
        Toast.show('Import failed', 'error'); console.error(err);
    }
});

window.importMouthshapes = window.importMouthshapes || (function(entity, entityId, frameId){
    if (typeof window.showImportMouthshapesModal === 'function') {
        window.showImportMouthshapesModal(entity, entityId, frameId);
    } else {
        console.error('showImportMouthshapesModal is not defined.');
        if (typeof Toast !== 'undefined') Toast.show('Modal component missing', 'error');
    }
});

window.editEntity = window.editEntity || (function(entity, entityId, frameId) {
    if (!entity || !entityId) {
        Toast.show('Missing entity. Cannot edit.', 'error');
        return;
    }
    let redirectUrl = window.location.pathname;
    if (frameId && window.location.pathname.includes('view_frame')) {
        redirectUrl = `view_frame.php?frame_id=${frameId}`;
    } else if (window.location.pathname.includes('gallery_')) {
        redirectUrl = `gallery_${entity}_nu.php`;
    }
    const editUrl = `/entity_form.php?entity_type=${encodeURIComponent(entity)}&entity_id=${encodeURIComponent(entityId)}&redirect_url=${encodeURIComponent(redirectUrl)}`;
    window.location.href = editUrl;
});

// --- CACHE & API HELPERS ---
window._sbCache = { boards: null, cats: null, eps: null };

async function sbFetch(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    try {
        const r = await fetch(`/storyboards_api.php?${qs}`, { credentials: 'same-origin' });
        const d = await r.json();
        return d.success ? d.data : [];
    } catch (e) { console.error(e); return []; }
}

window.loadStoryboards = async function() {
    if (!window._sbCache.boards) window._sbCache.boards = await sbFetch('list');
    return window._sbCache.boards;
};
window.importToStoryboard = async function(frameId, storyboardId) {
    if (!frameId || !storyboardId) { Toast.show('Missing params', 'error'); return; }
    const fd = new URLSearchParams(); fd.append('storyboard_id', storyboardId); fd.append('frame_id', frameId);
    try {
        const r = await fetch('/storyboard_import.php', { method: 'POST', body: fd, headers: {'Content-Type':'application/x-www-form-urlencoded'} });
        const d = await r.json();
        if (d.success) {
            Toast.show(d.message || 'Imported', 'success');
            window._sbCache.boards = null;
        } else { Toast.show('Failed: '+(d.message||'error'), 'error'); }
    } catch (e) { Toast.show('Error', 'error'); console.error(e); }
};

// --- RATING LOGIC (UPDATED WITH SMART FETCH) ---

window.openRatingMenu = async function(frameId, $wrapperObj, $trigger) {
    let currentRating = 0;
    
    // Check if we have the data in DOM
    // If $wrapperObj is passed and has data-rating defined (even if 0), use it.
    // If undefined, we need to fetch.
    if ($wrapperObj && typeof $wrapperObj.attr === 'function') {
        const attrVal = $wrapperObj.attr('data-rating');
        if (typeof attrVal !== 'undefined') {
            currentRating = parseInt(attrVal);
        } else {
            // Data missing from DOM, fetch from server
            try {
                const r = await fetch(`/ajax_frame_rating.php?frame_id=${frameId}`);
                const d = await r.json();
                if (d.success) {
                    currentRating = d.rating;
                    // Cache it so next time it's instant
                    $wrapperObj.attr('data-rating', currentRating);
                    $wrapperObj.data('rating', currentRating);
                }
            } catch(e) { console.error('Rating fetch failed', e); }
        }
    } else {
        // Fallback for direct value pass
        currentRating = parseInt($wrapperObj) || 0;
    }

    $('.sb-menu').remove();
    
    const $menu = $('<div class="sb-menu" style="min-width: 160px; max-width: 200px;"></div>');
    $menu.append('<div class="sb-cat-header">Rate Frame</div>');

    const $starsWrap = $('<div class="sb-rating-wrap"></div>');
    
    for (let i = 1; i <= 5; i++) {
        const isActive = i <= currentRating;
        const $star = $(`<span class="sb-star" data-val="${i}">${isActive ? '★' : '☆'}</span>`);
        
        $star.on('mouseenter', function() {
            const val = $(this).data('val');
            $starsWrap.find('.sb-star').each(function() {
                const sVal = $(this).data('val');
                $(this).text(sVal <= val ? '★' : '☆').addClass('hover');
            });
        });

        $star.on('click', async function(e) {
            e.stopPropagation();
            await saveRating(frameId, $(this).data('val'), $wrapperObj);
            $('.sb-menu').remove();
        });

        $starsWrap.append($star);
    }

    $starsWrap.on('mouseleave', function() {
        $starsWrap.find('.sb-star').each(function() {
            const sVal = $(this).data('val');
            $(this).text(sVal <= currentRating ? '★' : '☆').removeClass('hover');
        });
    });

    $menu.append($starsWrap);

    if (currentRating > 0) {
        $menu.append('<div class="sb-menu-sep"></div>');
        const $clear = $('<div class="sb-menu-item" style="color:#ff6b6b;"><i class="fa fa-ban"></i> &nbsp; Clear Rating</div>');
        $clear.on('click', async function(e) {
            e.stopPropagation();
            await saveRating(frameId, 0, $wrapperObj);
            $('.sb-menu').remove();
        });
        $menu.append($clear);
    }

    const pos = $trigger ? $trigger.offset() : { top: 100, left: 100 };
    let left = pos.left;
    if (left + 200 > $(window).width()) left = $(window).width() - 210;
    $menu.css({ top: pos.top + 30, left: left });
    $('body').append($menu);
    setTimeout(() => $(document).one('click', () => $('.sb-menu').remove()), 100);
};

async function saveRating(frameId, rating, $wrapper) {
    try {
        const fd = new URLSearchParams(); fd.append('frame_id', frameId); fd.append('rating', rating);
        const r = await fetch('/ajax_frame_rating.php', { method: 'POST', body: fd, headers: {'Content-Type': 'application/x-www-form-urlencoded'} });
        const d = await r.json();
        
        if (d.success) {
            Toast.show(d.message, 'success');
            // Update UI Data
            if ($wrapper && typeof $wrapper.attr === 'function') {
                $wrapper.attr('data-rating', rating);
                $wrapper.data('rating', rating);
            }
            // Update any other instances of this frame on page
            $(`[data-frame-id="${frameId}"]`).attr('data-rating', rating).data('rating', rating);
            
        } else { Toast.show(d.message, 'error'); }
    } catch(e) { Toast.show('Rating failed', 'error'); }
}


// --- STORYBOARD MENU LOGIC ---

window.selectStoryboard = async function(frameId, $trigger) {
    const [boards, cats, eps] = await Promise.all([
        window.loadStoryboards(),
        window._sbCache.cats || sbFetch('get_categories').then(d => window._sbCache.cats = d),
        window._sbCache.eps || sbFetch('get_episodes').then(d => window._sbCache.eps = d)
    ]);

    if (!boards || boards.length === 0) { Toast.show('No storyboards found.', 'warning'); return; }

    $('.sb-menu').remove();
    const $menu = $('<div class="sb-menu"></div>');
    
    // Filters
    const $filters = $('<div class="sb-filters"></div>');
    const $catSelect = $('<select class="sb-select"><option value="all">All Categories</option></select>');
    cats.forEach(c => $catSelect.append(`<option value="${c.id}" data-code="${c.code}">${c.name}</option>`));
    $filters.append($catSelect);

    const $edGroup = $('<div class="sb-editorial-group" style="display:none;"></div>');
    const $epSelect = $('<select class="sb-select"><option value="">All Episodes</option></select>');
    const $seqSelect = $('<select class="sb-select" disabled><option value="">All Sequences</option></select>');
    const $scSelect = $('<select class="sb-select" disabled><option value="">All Scenes</option></select>');
    eps.forEach(e => $epSelect.append(`<option value="${e.id}">Ep ${e.number}: ${e.name}</option>`));
    $edGroup.append($epSelect, $seqSelect, $scSelect);
    $filters.append($edGroup);
    $menu.append($filters);

    // List
    const $list = $('<div class="sb-list-container"></div>');
    $menu.append($list);
    $menu.append(`<div class="sb-menu-item sb-footer" onclick="window.location='/view_storyboards.php'"><i class="fa fa-th-large"></i> &nbsp; Manage Storyboards</div>`);

    function renderList() {
        $list.empty();
        const catId = $catSelect.val();
        const epId = $epSelect.val();
        const seqId = $seqSelect.val();
        const scId = $scSelect.val();
        const catCode = $catSelect.find(':selected').data('code');
        const isEd = (catCode === 'editorial');

        let items = boards;
        if (catId !== 'all') items = items.filter(b => b.category_id == catId);
        if (isEd) {
            if (epId) items = items.filter(b => b.episode_id == epId);
            if (seqId) items = items.filter(b => b.sequence_id == seqId);
            if (scId) items = items.filter(b => b.editorial_scene_id == scId);
        }

        if (items.length === 0) { $list.append('<div style="padding:20px;text-align:center;color:#666;font-size:12px;">No storyboards found</div>'); return; }

        items.forEach(sb => {
            let meta = '';
            if (sb.category_code === 'editorial' && sb.scene_name) meta = `Ep ${sb.episode_number} &bull; ${sb.scene_name}`;
            else meta = sb.custom_tag || '';

            const $el = $(`<div class="sb-menu-item"><div style="font-weight:500;">${sb.name}</div><div style="display:flex;justify-content:space-between;opacity:0.6;font-size:11px;margin-top:2px;"><span>${meta}</span><span>${sb.frame_count}</span></div></div>`);
            $el.click(async (e) => { e.stopPropagation(); $('.sb-menu').remove(); await window.importToStoryboard(frameId, sb.id); });
            $list.append($el);
        });
    }

    $catSelect.change(function() {
        const code = $(this).find(':selected').data('code');
        if (code === 'editorial') $edGroup.show(); else { $edGroup.hide(); $epSelect.val(''); $seqSelect.html('<option value="">All Sequences</option>').prop('disabled', true); $scSelect.html('<option value="">All Scenes</option>').prop('disabled', true); }
        renderList();
    });

    $epSelect.change(async function() {
        const val = $(this).val();
        $seqSelect.html('<option value="">All Sequences</option>').prop('disabled', true);
        $scSelect.html('<option value="">All Scenes</option>').prop('disabled', true);
        if (val) {
            const data = await sbFetch('get_sequences', { episode_id: val });
            $seqSelect.append('<option value="">All Sequences</option>');
            data.forEach(s => $seqSelect.append(`<option value="${s.id}">${s.name}</option>`));
            $seqSelect.prop('disabled', false);
        }
        renderList();
    });

    $seqSelect.change(async function() {
        const val = $(this).val();
        $scSelect.html('<option value="">All Scenes</option>').prop('disabled', true);
        if (val) {
            const data = await sbFetch('get_scenes', { sequence_id: val });
            $scSelect.append('<option value="">All Scenes</option>');
            data.forEach(s => $scSelect.append(`<option value="${s.id}">${s.name}</option>`));
            $scSelect.prop('disabled', false);
        }
        renderList();
    });
    $scSelect.change(renderList);
    renderList();

    const pos = $trigger ? $trigger.offset() : { top: 100, left: 100 };
    let left = pos.left;
    const w = 260; if (left + w > $(window).width()) left = $(window).width() - w - 10;

    $menu.css({ top: pos.top + 30, left: left });
    $('body').append($menu);
    setTimeout(() => $(document).one('click', () => $('.sb-menu').remove()), 100);
    $menu.click(e => e.stopPropagation());
};

window.importControlNetMap = window.importControlNetMap || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) { Toast.show('Missing params', 'error'); return; }
    const url = `/import_entity_from_entity.php?ajax=1&source=${encodeURIComponent(entity)}&target=controlnet_maps&source_entity_id=${entityId}&frame_id=${frameId}&limit=1&copy_name_desc=1`;
    try {
        const r = await fetch(url, { credentials: 'same-origin' });
        const d = await r.json();
        if (d.status === 'ok' || Array.isArray(d.result)) Toast.show('Import triggered', 'info'); else Toast.show('Import failed', 'error');
    } catch (e) { Toast.show('Error', 'error'); }
});

window.assignToComposite = window.assignToComposite || (async function(entity, entityId, frameId){
    if (!entity || !entityId || !frameId) return;
    window.location.href = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=composites&copy_name_desc=0&composite=1`;
});

window.usePromptMatrix = window.usePromptMatrix || (async function(entity, entityId, frameId){
    window.location.href = `/view_prompt_matrix.php?entity_type=${encodeURIComponent(entity)}&entity_id=${encodeURIComponent(entityId)}`;
});

window.deleteFrame = window.deleteFrame || (async function(entity, entityId, frameId) {
    if (!confirm("Delete this frame?")) return;
    try {
        const r = await fetch(`/delete_frames_from_entity.php?ajax=1&method=single&frame_id=${frameId}`, { method:'POST', credentials:'same-origin'});
        const d = await r.json();
        if (d.status === "ok") {
            $(`.img-wrapper[data-frame-id="${frameId}"], .frame-image-wrapper[data-frame-id="${frameId}"]`).remove();
            Toast.show('Deleted','success');
        } else { alert("Failed: " + d.message); }
    } catch (e) { alert("Error"); }
});

window.assignControlNetMap = window.assignControlNetMap || (async function(entity, entityId, frameId, target){
    if (entity !== 'controlnet_maps') return;
    window.location.href = `/import_entity_from_entity.php?source=${encodeURIComponent(entity)}&source_entity_id=${encodeURIComponent(entityId)}&frame_id=${encodeURIComponent(frameId)}&target=${encodeURIComponent(target)}&copy_name_desc=0&controlnet=1`;
});
