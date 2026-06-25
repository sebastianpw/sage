// public/vedtriccs/js/vt-bin.js
// Asset Bin — VedTriccs Edition (full six-tab picker)

'use strict';

// ─── Filter States ────────────────────────────────────────────────────────────
let vtPickerMode  = 'animatic';
let vtPickerNodeId  = null, vtPickerNodeName   = '';
let vtPickerSeqId   = null, vtPickerSeqName    = '';
let vtPickerFuzzId  = null, vtPickerFuzzName   = '';
let vtPickerSbId    = null, vtPickerSbName     = '';
let vtPickerPotId   = null, vtPickerPotName    = '';
let vtPickerFuzzTotalPages = 1;
let vtPickerSbTotalPages   = 1;
let vtPickerPotTotalPages  = 1;
let vtPickerTreeInited    = false;
let vtPickerSeqsLoaded    = false;
let vtPickerFuzzLoaded    = false, vtPickerFuzzPage = 1, vtPickerFuzzSearch = '';
let vtPickerSbLoaded      = false, vtPickerSbPage   = 1, vtPickerSbSearch   = '';
let vtPickerPotLoaded     = false, vtPickerPotPage  = 1, vtPickerPotSearch  = '';
let vtFuzzDebounce = null, vtSbDebounce = null, vtPotDebounce = null;

// ─── Open / Close ─────────────────────────────────────────────────────────────
function vtOpenBin() {
    document.getElementById('binBackdrop').classList.add('open');
    document.getElementById('btnBin').classList.add('active');
    if (VT_STATE.animaticId && vtPickerMode === 'animatic') {
        vtSwitchPickerMode('animatic');
    } else {
        vtSwitchPickerMode(vtPickerMode || 'animatic');
    }
}

function vtCloseBin() {
    document.getElementById('binBackdrop').classList.remove('open');
    document.getElementById('btnBin').classList.remove('active');
    vtStopBinPreview();
}

function vtOpenAnimaticBrowser() { vtOpenBin(); vtSwitchPickerMode('animatic'); }

// ─── Mobile Left Panel ────────────────────────────────────────────────────────
function vtTogglePickerTree() {
    const panel    = document.getElementById('picker-tree-panel');
    const backdrop = document.getElementById('picker-tree-backdrop');
    if (panel.classList.contains('open')) {
        panel.classList.remove('open'); backdrop.classList.remove('active');
    } else {
        panel.classList.add('open'); backdrop.classList.add('active');
    }
}
function vtClosePickerTree() {
    document.getElementById('picker-tree-panel').classList.remove('open');
    document.getElementById('picker-tree-backdrop').classList.remove('active');
}

// ─── Mode Switch ──────────────────────────────────────────────────────────────
function vtSwitchPickerMode(mode) {
    vtPickerMode = mode;
    document.querySelectorAll('.picker-tab-panel').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.picker-mode-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(`picker-mode-${mode}`)?.classList.add('active');

    if (mode === 'tree') {
        document.getElementById('picker-tree-panel-inner').classList.add('active');
        vtPickerSeqId = null; vtPickerFuzzId = null; vtPickerSbId = null; vtPickerPotId = null; VT_STATE.animaticId = null;
        vtSetPickerActiveLabel(vtPickerNodeId ? `🌳 ${vtPickerNodeName}` : '');
        if (!vtPickerTreeInited) vtInitPickerTree();
    } else if (mode === 'seq') {
        document.getElementById('picker-seq-panel').classList.add('active');
        vtPickerNodeId = null; vtPickerFuzzId = null; vtPickerSbId = null; vtPickerPotId = null; VT_STATE.animaticId = null;
        vtSetPickerActiveLabel(vtPickerSeqId ? `🎬 ${vtPickerSeqName}` : '');
        if (!vtPickerSeqsLoaded) vtLoadPickerSequences();
    } else if (mode === 'fuzz') {
        document.getElementById('picker-fuzz-panel').classList.add('active');
        vtPickerNodeId = null; vtPickerSeqId = null; vtPickerSbId = null; vtPickerPotId = null; VT_STATE.animaticId = null;
        vtSetPickerActiveLabel(vtPickerFuzzId ? `🧩 ${vtPickerFuzzName}` : '');
        if (!vtPickerFuzzLoaded) vtLoadPickerFuzz(1);
    } else if (mode === 'storyboard') {
        document.getElementById('picker-storyboard-panel').classList.add('active');
        vtPickerNodeId = null; vtPickerSeqId = null; vtPickerFuzzId = null; vtPickerPotId = null; VT_STATE.animaticId = null;
        vtSetPickerActiveLabel(vtPickerSbId ? `🖼️ ${vtPickerSbName}` : '');
        if (!vtPickerSbLoaded) vtLoadPickerStoryboards(1);
    } else if (mode === 'popcorn') {
        document.getElementById('picker-popcorn-panel').classList.add('active');
        vtPickerNodeId = null; vtPickerSeqId = null; vtPickerFuzzId = null; vtPickerSbId = null; VT_STATE.animaticId = null;
        vtSetPickerActiveLabel(vtPickerPotId ? `🍿 ${vtPickerPotName}` : '');
        if (!vtPickerPotLoaded) vtLoadPickerPots(1);
    } else { // animatic
        document.getElementById('picker-animatic-panel').classList.add('active');
        vtPickerNodeId = null; vtPickerSeqId = null; vtPickerFuzzId = null; vtPickerSbId = null; vtPickerPotId = null;
        vtSetPickerActiveLabel(VT_STATE.animaticId ? `🎞️ ${VT_STATE.animaticName || '#'+VT_STATE.animaticId}` : '');
        vtLoadAbPage();
    }
    vtUpdateAnimaticLabel();
    VT_STATE.binPage = 1;
    vtLoadBinPage();
}

function vtSetPickerActiveLabel(txt) {
    const lbl = document.getElementById('picker-active-label');
    const clr = document.getElementById('picker-tree-clear');
    if (txt) { lbl.textContent = txt; lbl.style.display = 'inline'; clr.style.display = 'inline-block'; }
    else      { lbl.style.display = 'none';  clr.style.display = 'none'; }
}

function vtClearPickerFilter() {
    if (vtPickerMode === 'tree') {
        if (typeof jQuery !== 'undefined') $('#picker-tree').jstree('deselect_all');
    } else if (vtPickerMode === 'seq') {
        vtPickerSeqId = null; vtPickerSeqName = '';
    } else if (vtPickerMode === 'fuzz') {
        vtPickerFuzzId = null; vtPickerFuzzName = '';
    } else if (vtPickerMode === 'storyboard') {
        vtPickerSbId = null; vtPickerSbName = '';
    } else if (vtPickerMode === 'popcorn') {
        vtPickerPotId = null; vtPickerPotName = '';
        vtLoadPickerPots(vtPickerPotPage);
    } else {
        VT_STATE.animaticId = null; VT_STATE.animaticName = '';
        vtUpdateAnimaticLabel();
    }
    document.querySelectorAll('.picker-item').forEach(i => i.classList.remove('active'));
    vtSetPickerActiveLabel('');
    VT_STATE.binPage = 1; vtLoadBinPage();
}

// ─── Animatics ────────────────────────────────────────────────────────────────
function vtDebouncedAbSearch() {
    clearTimeout(VT_STATE.abDebounce);
    VT_STATE.abDebounce = setTimeout(() => {
        VT_STATE.abSearch = document.getElementById('abSearch')?.value?.trim() || '';
        VT_STATE.abPage   = 1;
        vtLoadAbPage();
    }, 280);
}
function vtChangeAbPage(d) { vtGotoAbPage(VT_STATE.abPage + d); }
function vtGotoAbPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > VT_STATE.abTotalPages) n = VT_STATE.abTotalPages;
    VT_STATE.abPage = n; vtLoadAbPage();
}
function vtLoadAbPage() {
    const list = document.getElementById('abList');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';
    vtApi('list_animatics', { page: VT_STATE.abPage, q: VT_STATE.abSearch }).then(res => {
        if (res.status !== 'success') { list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--red);">Error</div>'; return; }
        VT_STATE.abTotalPages = res.total_pages || 1;
        const pg = document.getElementById('abPagination');
        pg.style.display = VT_STATE.abTotalPages > 1 ? 'flex' : 'none';
        document.getElementById('abPageInput').value = VT_STATE.abPage;
        document.getElementById('abOf').textContent  = '/ ' + VT_STATE.abTotalPages;
        document.getElementById('abPrev').disabled   = VT_STATE.abPage <= 1;
        document.getElementById('abNext').disabled   = VT_STATE.abPage >= VT_STATE.abTotalPages;
        if (!res.data.length) { list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No animatics found.</div>'; return; }
        list.innerHTML = res.data.map(a =>
            `<div class="picker-item ${a.id == VT_STATE.animaticId ? 'active' : ''}" onclick="vtSelectAnimatic(${a.id}, '${vtEsc(a.name)}')">
                <div class="picker-item-id">#${a.id}</div>
                <div class="picker-item-name">${vtEsc(a.name)}</div>
                ${a.description ? `<div class="picker-item-desc">${vtEsc(a.description)}</div>` : ''}
             </div>`
        ).join('');
    });
}
function vtSelectAnimatic(id, name) {
    VT_STATE.animaticId = id; VT_STATE.animaticName = name;
    vtUpdateAnimaticLabel();
    vtSetPickerActiveLabel(`🎞️ ${name}`);
    vtLoadAbPage();
    if (window.innerWidth < 768) vtClosePickerTree();
    VT_STATE.binPage = 1; vtLoadBinPage();
    Toast.show('Animatic: ' + name, 'success');
}

// ─── Tree ─────────────────────────────────────────────────────────────────────
function vtInitPickerTree() {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.jstree === 'undefined') return;
    vtPickerTreeInited = true;
    $('#picker-tree').jstree({
        core: {
            data: {
                url: '/view_video_review.php?api_action=tree_fetch',
                dataType: 'json',
                dataFilter: function(raw) {
                    try { const j = JSON.parse(raw); return JSON.stringify(j.status === 'ok' ? j.tree : []); }
                    catch(e) { return '[]'; }
                }
            },
            themes: { name: 'default', dots: true, icons: true },
            check_callback: false,
        },
        plugins: ['types'],
        types: {
            folder:   { icon: 'bi bi-folder2' },
            episode:  { icon: 'bi bi-film' },
            sequence: { icon: 'bi bi-collection-play' },
            scene:    { icon: 'bi bi-camera-video' },
            other:    { icon: 'bi bi-tag' },
        },
    }).on('select_node.jstree', function(e, data) {
        vtPickerNodeId = data.node.data.db_id; vtPickerNodeName = data.node.text;
        vtSetPickerActiveLabel(`🌳 ${vtPickerNodeName}`);
        if (window.innerWidth < 768) vtClosePickerTree();
        VT_STATE.binPage = 1; vtLoadBinPage();
    }).on('deselect_node.jstree', function() {
        vtPickerNodeId = null; vtPickerNodeName = '';
        vtSetPickerActiveLabel('');
        VT_STATE.binPage = 1; vtLoadBinPage();
    });
}

// ─── Sequences ────────────────────────────────────────────────────────────────
function vtLoadPickerSequences() {
    vtPickerSeqsLoaded = true;
    const list = document.getElementById('picker-seq-list');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';
    vtApi('list_narrative_sequences').then(res => {
        if (res.status !== 'success' || !res.data?.length) {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No sequences found.</div>'; return;
        }
        list.innerHTML = res.data.map(seq =>
            `<div class="picker-item ${seq.id == vtPickerSeqId ? 'active' : ''}" onclick="vtSelectPickerSeq(${seq.id}, '${vtEsc(seq.name)}')">
                <div class="picker-item-id">#${seq.id}</div>
                <div class="picker-item-name">${vtEsc(seq.name)}</div>
                ${seq.description ? `<div class="picker-item-desc">${vtEsc(seq.description)}</div>` : ''}
             </div>`
        ).join('');
    });
}
function vtSelectPickerSeq(id, name) {
    vtPickerSeqId = id; vtPickerSeqName = name;
    vtSetPickerActiveLabel(`🎬 ${name}`);
    vtLoadPickerSequences();
    if (window.innerWidth < 768) vtClosePickerTree();
    VT_STATE.binPage = 1; vtLoadBinPage();
}

// ─── Fuzz ─────────────────────────────────────────────────────────────────────
function vtLoadPickerFuzz(page) {
    vtPickerFuzzLoaded = true; vtPickerFuzzPage = page || 1;
    const list = document.getElementById('picker-fuzz-list');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';
    vtApi('list_fuzz_candidates', { page: vtPickerFuzzPage, limit: 20, search: vtPickerFuzzSearch }).then(res => {
        if (res.status !== 'success' || !res.data?.length) {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No concepts found.</div>';
            document.getElementById('picker-fuzz-pg').style.display = 'none'; return;
        }
        const pg = res.pagination || { pages: 1, page: 1 };
        vtPickerFuzzTotalPages = pg.pages;
        document.getElementById('picker-fuzz-page-input').value    = pg.page;
        document.getElementById('picker-fuzz-pg-of').textContent   = `/ ${pg.pages}`;
        document.getElementById('picker-fuzz-pg').style.display    = pg.pages > 1 ? 'flex' : 'none';
        list.innerHTML = res.data.map(cand =>
            `<div class="picker-item ${cand.id == vtPickerFuzzId ? 'active' : ''}" onclick="vtSelectPickerFuzz(${cand.id}, '${vtEsc(cand.label)}')">
                <div class="picker-item-id">#${cand.id} <span style="float:right;font-size:.6rem;color:var(--teal);">${vtEsc(cand.status)}</span></div>
                <div class="picker-item-name">${vtEsc(cand.label)}</div>
                ${cand.concept_type ? `<div class="picker-item-desc">${vtEsc(cand.concept_type)}</div>` : ''}
             </div>`
        ).join('');
    });
}
function vtSelectPickerFuzz(id, name) {
    vtPickerFuzzId = id; vtPickerFuzzName = name;
    vtSetPickerActiveLabel(`🧩 ${name}`);
    vtLoadPickerFuzz(vtPickerFuzzPage);
    if (window.innerWidth < 768) vtClosePickerTree();
    VT_STATE.binPage = 1; vtLoadBinPage();
}
function vtGotoFuzzPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > vtPickerFuzzTotalPages) n = vtPickerFuzzTotalPages;
    vtLoadPickerFuzz(n);
}

// ─── Storyboards ──────────────────────────────────────────────────────────────
function vtLoadPickerStoryboards(page) {
    vtPickerSbLoaded = true; vtPickerSbPage = page || 1;
    const list = document.getElementById('picker-storyboard-list');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';
    vtApi('list_storyboards', { page: vtPickerSbPage, search: vtPickerSbSearch }).then(res => {
        if (res.status !== 'success' || !res.data?.length) {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No storyboards found.</div>';
            document.getElementById('picker-storyboard-pg').style.display = 'none'; return;
        }
        const pg = res.pagination || { pages: 1, page: 1 };
        vtPickerSbTotalPages = pg.pages;
        document.getElementById('picker-storyboard-page-input').value  = pg.page;
        document.getElementById('picker-storyboard-pg-of').textContent = `/ ${pg.pages}`;
        document.getElementById('picker-storyboard-pg').style.display  = pg.pages > 1 ? 'flex' : 'none';
        list.innerHTML = res.data.map(sb =>
            `<div class="picker-item ${sb.id == vtPickerSbId ? 'active' : ''}" onclick="vtSelectPickerSb(${sb.id}, '${vtEsc(sb.name || sb.title)}')">
                <div class="picker-item-id">#${sb.id}</div>
                <div class="picker-item-name">${vtEsc(sb.name || sb.title)}</div>
                ${sb.description ? `<div class="picker-item-desc">${vtEsc(sb.description)}</div>` : ''}
             </div>`
        ).join('');
    });
}
function vtSelectPickerSb(id, name) {
    vtPickerSbId = id; vtPickerSbName = name;
    vtSetPickerActiveLabel(`🖼️ ${name}`);
    vtLoadPickerStoryboards(vtPickerSbPage);
    if (window.innerWidth < 768) vtClosePickerTree();
    VT_STATE.binPage = 1; vtLoadBinPage();
}
function vtGotoSbPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > vtPickerSbTotalPages) n = vtPickerSbTotalPages;
    vtLoadPickerStoryboards(n);
}

// ─── Popcorn Pots ─────────────────────────────────────────────────────────────
function vtLoadPickerPots(page) {
    vtPickerPotLoaded = true; vtPickerPotPage = page || 1;
    const list = document.getElementById('picker-popcorn-list');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';

    const params = new URLSearchParams({
        action: 'list_pots',
        page:   vtPickerPotPage,
        search: vtPickerPotSearch,
    });
    fetch('/popkorn_api.php?' + params.toString())
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success' || !res.data?.length) {
                list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No pots found.</div>';
                document.getElementById('picker-popcorn-pg').style.display = 'none';
                return;
            }
            vtPickerPotTotalPages = res.total_pages || 1;
            document.getElementById('picker-popcorn-page-input').value  = vtPickerPotPage;
            document.getElementById('picker-popcorn-pg-of').textContent = `/ ${vtPickerPotTotalPages}`;
            document.getElementById('picker-popcorn-pg').style.display  = vtPickerPotTotalPages > 1 ? 'flex' : 'none';
            list.innerHTML = res.data.map(pot =>
                `<div class="picker-item ${pot.id == vtPickerPotId ? 'active' : ''}" onclick="vtSelectPickerPot(${pot.id}, '${vtEsc(pot.name)}')">
                    <div class="picker-item-id">🍿 #${pot.id}</div>
                    <div class="picker-item-name">${vtEsc(pot.name)}</div>
                    <div class="picker-item-desc">${pot.video_count} video${pot.video_count != 1 ? 's' : ''}</div>
                 </div>`
            ).join('');
        })
        .catch(() => {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--red);">Error loading pots.</div>';
        });
}

function vtSelectPickerPot(id, name) {
    vtPickerPotId = id; vtPickerPotName = name;
    vtSetPickerActiveLabel(`🍿 ${name}`);
    vtLoadPickerPots(vtPickerPotPage);
    if (window.innerWidth < 768) vtClosePickerTree();
    VT_STATE.binPage = 1; vtLoadBinPage();
    Toast.show('Pot: ' + name, 'success');
}

function vtGotoPotPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > vtPickerPotTotalPages) n = vtPickerPotTotalPages;
    vtLoadPickerPots(n);
}

// ─── Videos / Right Panel ─────────────────────────────────────────────────────
function vtDebouncedBinSearch() {
    clearTimeout(VT_STATE.binDebounce);
    VT_STATE.binDebounce = setTimeout(() => {
        VT_STATE.binSearch = document.getElementById('binSearch')?.value?.trim() || '';
        VT_STATE.binPage   = 1;
        vtLoadBinPage();
    }, 280);
}
function vtChangeBinPage(d) { vtGotoBinPage(VT_STATE.binPage + d); }
function vtGotoBinPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > VT_STATE.binTotalPages) n = VT_STATE.binTotalPages;
    VT_STATE.binPage = n; vtLoadBinPage();
}

function vtLoadBinPage() {
    const list    = document.getElementById('binAssetList');
    const loading = document.getElementById('picker-loading');
    const empty   = document.getElementById('picker-empty');
    list.innerHTML = ''; loading.style.display = 'block'; empty.style.display = 'none';

    // Popcorn pots use the popkorn_api directly, bypassing vtApi
    if (vtPickerMode === 'popcorn' && vtPickerPotId) {
        fetch('/popkorn_api.php?action=get_pot_videos&pot_id=' + vtPickerPotId)
            .then(r => r.json())
            .then(res => {
                loading.style.display = 'none';
                if (res.status !== 'success' || !res.data?.length) {
                    empty.style.display = 'block';
                    document.getElementById('binPagination').style.display = 'none';
                    return;
                }
                // Pot videos come back flat — no server-side pagination needed
                // (pots are typically small collections)
                VT_STATE.binData       = res.data;
                VT_STATE.binTotalPages = 1;
                vtRenderBinList();
                vtUpdateBinPagination();
            })
            .catch(() => { loading.style.display = 'none'; empty.style.display = 'block'; });
        return;
    }

    const params = { page: VT_STATE.binPage, q: VT_STATE.binSearch };
    if (vtPickerMode === 'animatic'   && VT_STATE.animaticId) params.animatic_id    = VT_STATE.animaticId;
    if (vtPickerMode === 'tree'       && vtPickerNodeId)       { params.node_id = vtPickerNodeId; params.include_descendants = 1; }
    if (vtPickerMode === 'seq'        && vtPickerSeqId)         params.seq_id         = vtPickerSeqId;
    if (vtPickerMode === 'fuzz'       && vtPickerFuzzId)        params.fuzz_cand_id   = vtPickerFuzzId;
    if (vtPickerMode === 'storyboard' && vtPickerSbId)          params.storyboard_id  = vtPickerSbId;

    vtApi('list_videos', params).then(res => {
        loading.style.display = 'none';
        if (res.status !== 'success' || !res.data?.length) {
            empty.style.display = 'block';
            document.getElementById('binPagination').style.display = 'none';
            return;
        }
        VT_STATE.binData       = res.data;
        VT_STATE.binTotalPages = res.total_pages || 1;
        vtRenderBinList();
        vtUpdateBinPagination();
    }).catch(() => { loading.style.display = 'none'; empty.style.display = 'block'; empty.textContent = 'Network error'; });
}

function vtRenderBinList() {
    const list = document.getElementById('binAssetList');
    list.innerHTML = VT_STATE.binData.map((v, idx) => {
        const thumb = v.thumbnail
            ? `<img class="vid-img" src="/${v.thumbnail}" alt="" loading="lazy">`
            : `<div class="vid-img" style="display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:24px;"><i class="bi bi-film"></i></div>`;
        const dur  = v.duration ? parseFloat(v.duration).toFixed(1) + 's' : '?';
        const dims = (v.width && v.height) ? ` · ${v.width}×${v.height}` : '';
        return `
        <div class="vid-item" data-idx="${idx}">
            <div class="vid-drag-handle" data-idx="${idx}" title="Drag to Timeline"><i class="bi bi-grip-vertical"></i></div>
            ${thumb}
            <div class="vid-btns">
                <button class="vid-btn vid-btn-play" onclick="vtToggleBinPreview(${idx})" title="Preview"><i class="bi bi-play-fill"></i></button>
                <button class="vid-btn vid-btn-add"  onclick="vtAddClipFromBin(${idx})"    title="Add"><i class="bi bi-plus-lg"></i></button>
            </div>
            <div class="vid-info">
                <div class="vid-name" title="${vtEsc(v.name || v.url)}">${vtEsc(v.name || v.url)}</div>
                <div class="vid-sub">${vtEsc(dur)}${vtEsc(dims)}</div>
            </div>
        </div>`;
    }).join('');
}

function vtUpdateBinPagination() {
    const el = document.getElementById('binPagination');
    el.style.display = VT_STATE.binTotalPages > 1 ? 'flex' : 'none';
    document.getElementById('binPageInput').value  = VT_STATE.binPage;
    document.getElementById('pgOf').textContent    = '/ ' + VT_STATE.binTotalPages;
    document.getElementById('pgPrev').disabled     = VT_STATE.binPage <= 1;
    document.getElementById('pgNext').disabled     = VT_STATE.binPage >= VT_STATE.binTotalPages;
}

// ─── Bin Preview ──────────────────────────────────────────────────────────────
function vtStopBinPreview() {
    if (VT_STATE.binPreviewEl) {
        VT_STATE.binPreviewEl.pause(); VT_STATE.binPreviewEl.src = '';
        VT_STATE.binPreviewEl = null;
    }
    document.querySelectorAll('.vid-btn-play').forEach(btn => {
        btn.classList.remove('previewing');
        const ico = btn.querySelector('i'); if (ico) ico.className = 'bi bi-play-fill';
    });
}
function vtToggleBinPreview(idx) {
    const v = VT_STATE.binData[idx];
    if (!v?.url) return;
    const btns = document.querySelectorAll('.vid-btn-play');
    const btn  = btns[idx];
    if (VT_STATE.binPreviewEl && btn?.classList.contains('previewing')) { vtStopBinPreview(); return; }
    vtStopBinPreview();
    const el = document.createElement('video');
    el.src = '/' + v.url; el.muted = false; el.preload = 'auto';
    el.style.display = 'none'; document.body.appendChild(el);
    el.play().catch(() => {});
    VT_STATE.binPreviewEl = el;
    el.addEventListener('ended', vtStopBinPreview);
    if (btn) { btn.classList.add('previewing'); const ico = btn.querySelector('i'); if (ico) ico.className = 'bi bi-stop-fill'; }
}

// ─── Event Wiring (DOMContentLoaded) ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Fuzz search + pagination
    document.getElementById('picker-fuzz-search')?.addEventListener('input', function() {
        clearTimeout(vtFuzzDebounce);
        vtFuzzDebounce = setTimeout(() => { vtPickerFuzzSearch = this.value.trim(); vtLoadPickerFuzz(1); }, 300);
    });
    document.getElementById('picker-fuzz-prev')?.addEventListener('click', () => vtLoadPickerFuzz(vtPickerFuzzPage - 1));
    document.getElementById('picker-fuzz-next')?.addEventListener('click', () => vtLoadPickerFuzz(vtPickerFuzzPage + 1));
    document.getElementById('picker-fuzz-page-input')?.addEventListener('change', function() { vtGotoFuzzPage(this.value); });

    // Storyboard search + pagination
    document.getElementById('picker-storyboard-search')?.addEventListener('input', function() {
        clearTimeout(vtSbDebounce);
        vtSbDebounce = setTimeout(() => { vtPickerSbSearch = this.value.trim(); vtLoadPickerStoryboards(1); }, 300);
    });
    document.getElementById('picker-storyboard-prev')?.addEventListener('click', () => vtLoadPickerStoryboards(vtPickerSbPage - 1));
    document.getElementById('picker-storyboard-next')?.addEventListener('click', () => vtLoadPickerStoryboards(vtPickerSbPage + 1));
    document.getElementById('picker-storyboard-page-input')?.addEventListener('change', function() { vtGotoSbPage(this.value); });

    // Popcorn pots search + pagination
    document.getElementById('picker-popcorn-search')?.addEventListener('input', function() {
        clearTimeout(vtPotDebounce);
        vtPotDebounce = setTimeout(() => { vtPickerPotSearch = this.value.trim(); vtLoadPickerPots(1); }, 300);
    });
    document.getElementById('picker-popcorn-prev')?.addEventListener('click', () => vtLoadPickerPots(vtPickerPotPage - 1));
    document.getElementById('picker-popcorn-next')?.addEventListener('click', () => vtLoadPickerPots(vtPickerPotPage + 1));
    document.getElementById('picker-popcorn-page-input')?.addEventListener('change', function() { vtGotoPotPage(this.value); });

    // Animatic + bin pagination inputs
    document.getElementById('abPageInput')?.addEventListener('change', function() { vtGotoAbPage(this.value); });
    document.getElementById('binPageInput')?.addEventListener('change', function() { vtGotoBinPage(this.value); });
});
