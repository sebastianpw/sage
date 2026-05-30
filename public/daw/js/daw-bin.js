// public/daw/js/daw-bin.js
// Asset Bin — entity list, asset list, Howl preview, open/close

'use strict';

// ─── Entity List ──────────────────────────────────────────────────────────────
function onEntityTypeChange(entity) {
    STATE.entity   = entity;
    STATE.entityId = null;
    STATE.page     = 1;
    clearAssets();
    loadEntities(1);
}

function debouncedEntitySearch() {
    clearTimeout(STATE.entityDebounce);
    STATE.entityDebounce = setTimeout(() => loadEntities(1), 300);
}

function changePage(d) {
    const n = STATE.page + d;
    if (n >= 1 && n <= STATE.totalPages) loadEntities(n);
}

function loadEntities(page) {
    // Dynamically adjust page size if on mobile landscape
    const isLandscapeMobile = window.innerWidth <= 900 && window.matchMedia("(orientation: landscape)").matches;
    STATE.pageSize = isLandscapeMobile ? 1 : 6;
    STATE.page = page;
    
    const search = (document.getElementById('binSearch')?.value || '').trim();
    const offset = (page - 1) * STATE.pageSize;
    const list   = document.getElementById('binEntityList');
    list.innerHTML = '<div class="bin-state"><div class="spin-s"></div> Loading…</div>';

    api('get_entities', { limit: STATE.pageSize, offset, search })
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = '<div class="bin-state">Error loading entities</div>'; return; }
            STATE.totalPages = Math.ceil(res.total / STATE.pageSize) || 1;
            renderEntityList(res.data);
            updatePagination();
        })
        .catch(() => list.innerHTML = '<div class="bin-state">Network error</div>');
}

function renderEntityList(rows) {
    const list = document.getElementById('binEntityList');
    if (!rows.length) { list.innerHTML = '<div class="bin-state">No entities found</div>'; return; }
    list.innerHTML = rows.map(row => {
        const active = row.id == STATE.entityId ? 'active' : '';
        const date   = (row.created_at || '').substring(0, 10);
        return `<div class="bin-entity-item ${active}" onclick="selectEntity(${row.id}, this)">
            <span class="bi-eid">#${row.id}</span>
            <span class="bi-name">${esc(row.name || 'Unnamed')}</span>
            <span class="bi-date">${esc(date)}</span>
        </div>`;
    }).join('');
}

function updatePagination() {
    const el = document.getElementById('binPagination');
    el.style.display = STATE.totalPages > 1 ? 'flex' : 'none';
    document.getElementById('pgCur').textContent = STATE.page;
    document.getElementById('pgOf').textContent  = '/ ' + STATE.totalPages;
    document.getElementById('pgPrev').disabled   = STATE.page <= 1;
    document.getElementById('pgNext').disabled   = STATE.page >= STATE.totalPages;
}

function selectEntity(id, el) {
    document.querySelectorAll('.bin-entity-item').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
    STATE.entityId = id;
    loadAssetsForEntity(id);
}

function createNewEntity() {
    api('add_entity', {}, 'POST').then(res => {
        if (res.status === 'success') { Toast.show('Created entity #' + res.id, 'success'); loadEntities(1); }
        else Toast.show(res.message || 'Error', 'error');
    });
}

// ─── Asset List ───────────────────────────────────────────────────────────────
function clearAssets() {
    STATE.assetData  = [];
    STATE.previewIdx = -1;
    stopPreview();
    document.getElementById('binAssetList').innerHTML = '<div class="bin-state" style="color:var(--text-faint);">↑ Select an entity above</div>';
    document.getElementById('binAssetsCount').textContent = '–';
}

function debouncedAssetSearch() {
    clearTimeout(STATE.assetDebounce);
    STATE.assetDebounce = setTimeout(() => { if (STATE.entityId) loadAssetsForEntity(STATE.entityId); }, 250);
}

function loadAssetsForEntity(entityId) {
    STATE.entityId = entityId;
    const search   = (document.getElementById('binAssetSearch')?.value || '').trim();
    const list     = document.getElementById('binAssetList');
    list.innerHTML = '<div class="bin-state"><div class="spin-s"></div> Loading audios…</div>';

    api('get_playlist', { entity_id: entityId, search })
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = '<div class="bin-state">Error loading audios</div>'; return; }
            STATE.assetData = res.data.map(r => ({
                id: r.audio_id || r.id,
                name: r.name || ('Audio #' + (r.audio_id || r.id)),
                filename: r.filename || '',
            }));
            document.getElementById('binAssetsCount').textContent = STATE.assetData.length + ' audio' + (STATE.assetData.length !== 1 ? 's' : '');
            renderAssets();
        })
        .catch(() => list.innerHTML = '<div class="bin-state">Network error</div>');
}

function renderAssets() {
    const list = document.getElementById('binAssetList');
    if (!STATE.assetData.length) { list.innerHTML = '<div class="bin-state">No audio assets for this entity</div>'; return; }
    list.innerHTML = STATE.assetData.map((a, idx) => {
        const isPrev = idx === STATE.previewIdx;
        return `<div class="bin-asset-item" data-idx="${idx}">
            <div class="ba-drag-handle" data-drag-idx="${idx}" title="Drag to Timeline">
                <i class="bi bi-grip-vertical"></i>
            </div>
            <button class="ba-play ${isPrev ? 'previewing' : ''}" onclick="togglePreview(${idx})" title="Preview">
                <i class="bi ${isPrev ? 'bi-stop-fill' : 'bi-play-fill'}"></i>
            </button>
            <div class="ba-info">
                <div class="ba-name">${esc(a.name)}</div>
                <div class="ba-file" title="${esc(a.filename)}">${esc(trunc(a.filename.split('/').pop(), 36))}</div>
            </div>
            <button class="ba-add" onclick="addTrackFromAsset(${idx})" title="Add to Timeline">
                <i class="bi bi-plus-circle-fill"></i>
            </button>
        </div>`;
    }).join('');
}

// ─── Preview Engine ───────────────────────────────────────────────────────────
function stopPreview() {
    if (STATE.previewHowl) { STATE.previewHowl.stop(); STATE.previewHowl.unload(); STATE.previewHowl = null; }
    STATE.previewIdx = -1;
    updateAssetPlayUI();
}

function togglePreview(idx) {
    if (STATE.previewIdx === idx) { stopPreview(); return; }
    stopPreview();
    if (STATE.isPlaying) dawStop();
    const a = STATE.assetData[idx];
    if (!a?.filename) return;
    STATE.previewIdx   = idx;
    STATE.previewHowl  = new Howl({
        src: [a.filename], html5: true,
        onend: stopPreview,
        onloaderror: () => { Toast.show('Cannot load preview', 'error'); stopPreview(); }
    });
    STATE.previewHowl.play();
    updateAssetPlayUI();
}

function updateAssetPlayUI() {
    document.querySelectorAll('.bin-asset-item').forEach((el, i) => {
        const btn = el.querySelector('.ba-play');
        const ico = btn?.querySelector('i');
        if (!btn || !ico) return;
        const p = i === STATE.previewIdx;
        btn.classList.toggle('previewing', p);
        ico.className = p ? 'bi bi-stop-fill' : 'bi bi-play-fill';
    });
}

// ─── Open / Close ─────────────────────────────────────────────────────────────
function openBin() {
    document.getElementById('binPanel').classList.add('open');
    document.getElementById('binOverlay').classList.add('open');
    document.getElementById('btnBin').classList.add('active');
}

function closeBin() {
    document.getElementById('binPanel').classList.remove('open');
    document.getElementById('binOverlay').classList.remove('open');
    document.getElementById('btnBin').classList.remove('active');
}