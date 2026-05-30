// public/ved/js/ved-bin.js
// Video Asset Bin and Picker panel logic (Tree, Animatics, Sequences, Concepts, Storyboards)

'use strict';

// ─── Filter States ────────────────────────────────────────────────────────────
let pickerMode = 'animatic'; // 'animatic', 'tree', 'seq', 'fuzz', 'storyboard'
let pickerNodeId = null, pickerNodeName = '';
let pickerSeqId = null, pickerSeqName = '';
let pickerFuzzId = null, pickerFuzzName = '';
let pickerSbId = null, pickerSbName = '';

// Total pages states for input clamping
let pickerFuzzTotalPages = 1;
let pickerSbTotalPages = 1;

// ─── Bin Open / Close ─────────────────────────────────────────────────────────
function openBin() {
    document.getElementById('binBackdrop').classList.add('open');
    document.getElementById('btnBin').classList.add('active');
    
    if (STATE.animaticId && !pickerNodeId && !pickerSeqId && !pickerFuzzId && !pickerSbId) {
        switchPickerMode('animatic');
    } else {
        if (!pickerMode) switchPickerMode('animatic');
        else switchPickerMode(pickerMode);
    }
}

function closeBin() {
    document.getElementById('binBackdrop').classList.remove('open');
    document.getElementById('btnBin').classList.remove('active');
    stopBinPreview();
}

function openAnimaticBrowser() {
    openBin();
    switchPickerMode('animatic');
}

// ─── Mobile Left Panel Toggle ─────────────────────────────────────────────────
function togglePickerTree() {
    const panel    = document.getElementById('picker-tree-panel');
    const backdrop = document.getElementById('picker-tree-backdrop');
    if (panel.classList.contains('open')) {
        panel.classList.remove('open'); backdrop.classList.remove('active');
    } else {
        panel.classList.add('open'); backdrop.classList.add('active');
    }
}
function closePickerTree() {
    document.getElementById('picker-tree-panel').classList.remove('open');
    document.getElementById('picker-tree-backdrop').classList.remove('active');
}

// ─── Mode Switching ───────────────────────────────────────────────────────────
function switchPickerMode(mode) {
    pickerMode = mode;
    document.querySelectorAll('.picker-tab-panel').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.picker-mode-btn').forEach(el => el.classList.remove('active'));
    
    const btn = document.getElementById(`picker-mode-${mode}`);
    if (btn) btn.classList.add('active');
    
    if (mode === 'tree') {
        document.getElementById('picker-tree-panel-inner').classList.add('active');
        pickerSeqId = null; pickerFuzzId = null; pickerSbId = null; STATE.animaticId = null;
        _updateActiveLabel(pickerNodeId ? `🌳 ${pickerNodeName}` : '');
        if (!pickerTreeInited) initPickerTree();
    } else if (mode === 'seq') {
        document.getElementById('picker-seq-panel').classList.add('active');
        pickerNodeId = null; pickerFuzzId = null; pickerSbId = null; STATE.animaticId = null;
        _updateActiveLabel(pickerSeqId ? `🎬 ${pickerSeqName}` : '');
        if (!pickerSeqsLoaded) loadPickerSequences();
    } else if (mode === 'fuzz') {
        document.getElementById('picker-fuzz-panel').classList.add('active');
        pickerNodeId = null; pickerSeqId = null; pickerSbId = null; STATE.animaticId = null;
        _updateActiveLabel(pickerFuzzId ? `🧩 ${pickerFuzzName}` : '');
        if (!pickerFuzzLoaded) loadPickerFuzz(1);
    } else if (mode === 'storyboard') {
        document.getElementById('picker-storyboard-panel').classList.add('active');
        pickerNodeId = null; pickerSeqId = null; pickerFuzzId = null; STATE.animaticId = null;
        _updateActiveLabel(pickerSbId ? `🖼️ ${pickerSbName}` : '');
        if (!pickerSbLoaded) loadPickerStoryboards(1);
    } else if (mode === 'animatic') {
        document.getElementById('picker-animatic-panel').classList.add('active');
        pickerNodeId = null; pickerSeqId = null; pickerFuzzId = null; pickerSbId = null;
        _updateActiveLabel(STATE.animaticId ? `🎞️ ${STATE.animaticName || ('#'+STATE.animaticId)}` : '');
        _loadAbPage(); 
    }
    
    if (typeof _updateAnimaticLabel === 'function') _updateAnimaticLabel(); 
    STATE.binPage = 1;
    loadBinPage();
}

function _updateActiveLabel(txt) {
    const lbl = document.getElementById('picker-active-label');
    const clr = document.getElementById('picker-tree-clear');
    if (txt) {
        lbl.textContent = txt;
        lbl.style.display = 'inline';
        clr.style.display = 'inline-block';
    } else {
        lbl.style.display = 'none';
        clr.style.display = 'none';
    }
}

function clearPickerFilter() {
    if (pickerMode === 'tree') {
        if (typeof jQuery !== 'undefined' && $('#picker-tree').length) $('#picker-tree').jstree('deselect_all');
    } else if (pickerMode === 'seq') {
        pickerSeqId = null; pickerSeqName = '';
    } else if (pickerMode === 'fuzz') {
        pickerFuzzId = null; pickerFuzzName = '';
    } else if (pickerMode === 'storyboard') {
        pickerSbId = null; pickerSbName = '';
    } else if (pickerMode === 'animatic') {
        STATE.animaticId = null; STATE.animaticName = '';
        if (typeof _updateAnimaticLabel === 'function') _updateAnimaticLabel();
    }
    
    document.querySelectorAll('.picker-item').forEach(i => i.classList.remove('active'));
    _updateActiveLabel('');
    STATE.binPage = 1;
    loadBinPage();
}

// ─── Data Loading: Animatics ──────────────────────────────────────────────────
function debouncedAbSearch() {
    clearTimeout(STATE.abDebounce);
    STATE.abDebounce = setTimeout(() => {
        STATE.abSearch = (document.getElementById('abSearch')?.value || '').trim();
        STATE.abPage = 1;
        _loadAbPage();
    }, 280);
}

function changeAbPage(d) {
    gotoAbPage(STATE.abPage + d);
}

function gotoAbPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > STATE.abTotalPages) n = STATE.abTotalPages;
    if (n !== STATE.abPage) {
        STATE.abPage = n;
        _loadAbPage();
    } else {
        const inp = document.getElementById('abPageInput');
        if (inp) inp.value = STATE.abPage;
    }
}

function _loadAbPage() {
    const list = document.getElementById('abList');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';

    api('list_animatics', { page: STATE.abPage, q: STATE.abSearch })
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--red);">Error</div>'; return; }
            STATE.abTotalPages = res.total_pages || 1;

            const pg = document.getElementById('abPagination');
            pg.style.display = STATE.abTotalPages > 1 ? 'flex' : 'none';
            
            const inp = document.getElementById('abPageInput');
            if (inp) inp.value = STATE.abPage;
            
            document.getElementById('abOf').textContent  = '/ ' + STATE.abTotalPages;
            document.getElementById('abPrev').disabled   = STATE.abPage <= 1;
            document.getElementById('abNext').disabled   = STATE.abPage >= STATE.abTotalPages;

            if (!res.data.length) { list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No animatics found.</div>'; return; }

            list.innerHTML = res.data.map(a =>
                `<div class="picker-item ${a.id == STATE.animaticId ? 'active' : ''}" onclick="selectAnimatic(${a.id}, '${esc(a.name)}')">
                    <div class="picker-item-id">#${a.id}</div>
                    <div class="picker-item-name">${esc(a.name)}</div>
                    ${a.description ? `<div class="picker-item-desc">${esc(a.description)}</div>` : ''}
                </div>`
            ).join('');
        })
        .catch(() => { list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--red);">Network error</div>'; });
}

function selectAnimatic(id, name) {
    STATE.animaticId = id;
    STATE.animaticName = name;
    if (typeof _updateAnimaticLabel === 'function') _updateAnimaticLabel();
    _updateActiveLabel(`🎞️ ${name}`);
    _loadAbPage(); 
    
    if (window.innerWidth < 768) closePickerTree();
    
    STATE.binPage = 1;
    loadBinPage();
    if (typeof Toast !== 'undefined') Toast.show('Animatic: ' + name, 'success');
}

// ─── Data Loading: Tree ───────────────────────────────────────────────────────
let pickerTreeInited = false;
function initPickerTree() {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.jstree === 'undefined') return;
    pickerTreeInited = true;
    $('#picker-tree').jstree({
        core: {
            data: {
                url: '/view_video_review.php?api_action=tree_fetch',
                dataType: 'json',
                dataFilter: function(raw) {
                    try {
                        const j = JSON.parse(raw);
                        return JSON.stringify(j.status === 'ok' ? j.tree : []);
                    } catch(e) { return '[]'; }
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
        pickerNodeId   = data.node.data.db_id;
        pickerNodeName = data.node.text;
        _updateActiveLabel(`🌳 ${pickerNodeName}`);
        if (window.innerWidth < 768) closePickerTree();
        STATE.binPage = 1; loadBinPage();
    }).on('deselect_node.jstree', function() {
        pickerNodeId = null; pickerNodeName = '';
        _updateActiveLabel('');
        STATE.binPage = 1; loadBinPage();
    });
}

// ─── Data Loading: Sequences ──────────────────────────────────────────────────
let pickerSeqsLoaded = false;
function loadPickerSequences() {
    pickerSeqsLoaded = true;
    const list = document.getElementById('picker-seq-list');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';
    
    api('list_narrative_sequences').then(res => {
        if (res.status !== 'success' || !res.data || !res.data.length) {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No sequences found.</div>';
            return;
        }
        list.innerHTML = res.data.map(seq => `
            <div class="picker-item ${seq.id == pickerSeqId ? 'active' : ''}" onclick="selectPickerSeq(${seq.id}, '${esc(seq.name)}')">
                <div class="picker-item-id">#${seq.id}</div>
                <div class="picker-item-name">${esc(seq.name)}</div>
                ${seq.description ? `<div class="picker-item-desc">${esc(seq.description)}</div>` : ''}
            </div>
        `).join('');
    });
}

function selectPickerSeq(id, name) {
    pickerSeqId = id; pickerSeqName = name;
    _updateActiveLabel(`🎬 ${name}`);
    loadPickerSequences(); 
    if (window.innerWidth < 768) closePickerTree();
    STATE.binPage = 1; loadBinPage();
}

// ─── Data Loading: Fuzz / Concepts ────────────────────────────────────────────
let pickerFuzzLoaded = false, pickerFuzzPage = 1, pickerFuzzSearch = '';
let pickerFuzzDebounce = null;

function loadPickerFuzz(page) {
    pickerFuzzLoaded = true;
    pickerFuzzPage = page || 1;
    const list = document.getElementById('picker-fuzz-list');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';
    
    api('list_fuzz_candidates', { page: pickerFuzzPage, limit: 20, search: pickerFuzzSearch }).then(res => {
        if (res.status !== 'success' || !res.data || !res.data.length) {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No concepts found.</div>';
            document.getElementById('picker-fuzz-pg').style.display = 'none';
            return;
        }
        const pg = res.pagination || { pages: 1, page: 1 };
        pickerFuzzTotalPages = pg.pages;
        
        document.getElementById('picker-fuzz-page-input').value = pg.page;
        document.getElementById('picker-fuzz-pg-of').textContent = `/ ${pg.pages}`;
        document.getElementById('picker-fuzz-pg').style.display = pg.pages > 1 ? 'flex' : 'none';
        
        list.innerHTML = res.data.map(cand => `
            <div class="picker-item ${cand.id == pickerFuzzId ? 'active' : ''}" onclick="selectPickerFuzz(${cand.id}, '${esc(cand.label)}')">
                <div class="picker-item-id">#${cand.id} <span style="float:right;font-size:.6rem;color:var(--teal);">${esc(cand.status)}</span></div>
                <div class="picker-item-name">${esc(cand.label)}</div>
                ${cand.concept_type ? `<div class="picker-item-desc">${esc(cand.concept_type)}</div>` : ''}
            </div>
        `).join('');
    });
}

function gotoFuzzPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > pickerFuzzTotalPages) n = pickerFuzzTotalPages;
    if (n !== pickerFuzzPage) {
        loadPickerFuzz(n);
    } else {
        const inp = document.getElementById('picker-fuzz-page-input');
        if (inp) inp.value = pickerFuzzPage;
    }
}

function selectPickerFuzz(id, name) {
    pickerFuzzId = id; pickerFuzzName = name;
    _updateActiveLabel(`🧩 ${name}`);
    loadPickerFuzz(pickerFuzzPage);
    if (window.innerWidth < 768) closePickerTree();
    STATE.binPage = 1; loadBinPage();
}

// ─── Data Loading: Storyboards ────────────────────────────────────────────────
let pickerSbLoaded = false, pickerSbPage = 1, pickerSbSearch = '';
let pickerSbDebounce = null;

function loadPickerStoryboards(page) {
    pickerSbLoaded = true;
    pickerSbPage = page || 1;
    const list = document.getElementById('picker-storyboard-list');
    list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">Loading…</div>';
    
    api('list_storyboards', { page: pickerSbPage, search: pickerSbSearch }).then(res => {
        if (res.status !== 'success' || !res.data || !res.data.length) {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-dim);">No storyboards found.</div>';
            document.getElementById('picker-storyboard-pg').style.display = 'none';
            return;
        }
        const pg = res.pagination || { pages: 1, page: 1 };
        pickerSbTotalPages = pg.pages;
        
        document.getElementById('picker-storyboard-page-input').value = pg.page;
        document.getElementById('picker-storyboard-pg-of').textContent = `/ ${pg.pages}`;
        document.getElementById('picker-storyboard-pg').style.display = pg.pages > 1 ? 'flex' : 'none';
        
        list.innerHTML = res.data.map(sb => `
            <div class="picker-item ${sb.id == pickerSbId ? 'active' : ''}" onclick="selectPickerSb(${sb.id}, '${esc(sb.name || sb.title)}')">
                <div class="picker-item-id">#${sb.id}</div>
                <div class="picker-item-name">${esc(sb.name || sb.title)}</div>
                ${sb.description ? `<div class="picker-item-desc">${esc(sb.description)}</div>` : ''}
            </div>
        `).join('');
    });
}

function gotoSbPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > pickerSbTotalPages) n = pickerSbTotalPages;
    if (n !== pickerSbPage) {
        loadPickerStoryboards(n);
    } else {
        const inp = document.getElementById('picker-storyboard-page-input');
        if (inp) inp.value = pickerSbPage;
    }
}

function selectPickerSb(id, name) {
    pickerSbId = id; pickerSbName = name;
    _updateActiveLabel(`🖼️ ${name}`);
    loadPickerStoryboards(pickerSbPage);
    if (window.innerWidth < 768) closePickerTree();
    STATE.binPage = 1; loadBinPage();
}

// ─── Data Loading: Videos / Bin Right Panel ───────────────────────────────────
function debouncedBinSearch() {
    clearTimeout(STATE.binDebounce);
    STATE.binDebounce = setTimeout(() => {
        STATE.binSearch = (document.getElementById('binSearch')?.value || '').trim();
        STATE.binPage = 1;
        loadBinPage();
    }, 280);
}

function changeBinPage(d) {
    gotoBinPage(STATE.binPage + d);
}

function gotoBinPage(n) {
    n = parseInt(n);
    if (isNaN(n) || n < 1) n = 1;
    if (n > STATE.binTotalPages) n = STATE.binTotalPages;
    if (n !== STATE.binPage) {
        STATE.binPage = n;
        loadBinPage();
    } else {
        const inp = document.getElementById('binPageInput');
        if (inp) inp.value = STATE.binPage;
    }
}

function loadBinPage() {
    const list = document.getElementById('binAssetList');
    const loading = document.getElementById('picker-loading');
    const empty = document.getElementById('picker-empty');
    
    list.innerHTML = '';
    loading.style.display = 'block';
    empty.style.display = 'none';

    const params = {
        page: STATE.binPage,
        q:    STATE.binSearch,
    };
    
    if (pickerMode === 'animatic' && STATE.animaticId) params.animatic_id = STATE.animaticId;
    if (pickerMode === 'tree' && pickerNodeId) { params.node_id = pickerNodeId; params.include_descendants = 1; }
    if (pickerMode === 'seq' && pickerSeqId) params.seq_id = pickerSeqId;
    if (pickerMode === 'fuzz' && pickerFuzzId) params.fuzz_cand_id = pickerFuzzId;
    if (pickerMode === 'storyboard' && pickerSbId) params.storyboard_id = pickerSbId;

    api('list_videos', params)
        .then(res => {
            loading.style.display = 'none';
            if (res.status !== 'success' || !res.data.length) {
                empty.style.display = 'block';
                document.getElementById('binPagination').style.display = 'none';
                return;
            }
            
            STATE.binData = res.data;
            STATE.binTotalPages = res.total_pages || 1;
            
            _renderBinList();
            _updateBinPagination();
        })
        .catch(() => { loading.style.display = 'none'; empty.style.display = 'block'; empty.textContent = 'Network error'; });
}

function _renderBinList() {
    const list = document.getElementById('binAssetList');
    list.innerHTML = STATE.binData.map((v, idx) => {
        const thumb = v.thumbnail
            ? `<img class="vid-img" src="/${v.thumbnail}" alt="" loading="lazy">`
            : `<div class="vid-img" style="display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:24px;"><i class="bi bi-film"></i></div>`;
        const dur  = v.duration ? parseFloat(v.duration).toFixed(1) + 's' : '?';
        const dims = (v.width && v.height) ? ` · ${v.width}×${v.height}` : '';
        
        return `
        <div class="vid-item" data-idx="${idx}">
            <div class="vid-drag-handle" data-idx="${idx}" title="Drag to Timeline">
                <i class="bi bi-grip-vertical"></i>
            </div>
            ${thumb}
            <div class="vid-btns">
                <button class="vid-btn vid-btn-play" onclick="toggleBinPreview(${idx})" title="Preview">
                    <i class="bi bi-play-fill"></i>
                </button>
                <button class="vid-btn vid-btn-add" onclick="addClipFromBin(${idx})" title="Add to Timeline">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="vid-info">
                <div class="vid-name" title="${esc(v.name || v.url)}">${esc(v.name || v.url)}</div>
                <div class="vid-sub">${esc(dur)}${esc(dims)}</div>
            </div>
        </div>`;
    }).join('');
}

function _updateBinPagination() {
    const el = document.getElementById('binPagination');
    el.style.display = STATE.binTotalPages > 1 ? 'flex' : 'none';
    
    const inp = document.getElementById('binPageInput');
    if (inp) inp.value = STATE.binPage;
    
    document.getElementById('pgOf').textContent  = '/ ' + STATE.binTotalPages;
    document.getElementById('pgPrev').disabled   = STATE.binPage <= 1;
    document.getElementById('pgNext').disabled   = STATE.binPage >= STATE.binTotalPages;
}

// ─── Bin Preview ─────────────────────────────────────────────────────────────
function stopBinPreview() {
    if (STATE.binPreviewEl) {
        STATE.binPreviewEl.pause();
        STATE.binPreviewEl.src = '';
        STATE.binPreviewEl = null;
    }
    document.querySelectorAll('.vid-btn-play').forEach(btn => {
        btn.classList.remove('previewing');
        const ico = btn.querySelector('i');
        if (ico) ico.className = 'bi bi-play-fill';
    });
}

function toggleBinPreview(idx) {
    const v = STATE.binData[idx];
    if (!v?.url) return;

    const btns = document.querySelectorAll('.vid-btn-play');
    const btn  = btns[idx];

    if (STATE.binPreviewEl && btn && btn.classList.contains('previewing')) {
        stopBinPreview();
        return;
    }

    stopBinPreview();

    const el = document.createElement('video');
    el.src     = '/' + v.url;
    el.muted   = false;
    el.preload = 'auto';
    el.style.display = 'none';
    document.body.appendChild(el);
    el.play().catch(() => {});
    STATE.binPreviewEl = el;

    el.addEventListener('ended', stopBinPreview);

    if (btn) {
        btn.classList.add('previewing');
        const ico = btn.querySelector('i');
        if (ico) ico.className = 'bi bi-stop-fill';
    }
}

// ─── Event Wiring ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Fuzz
    const sFuzz = document.getElementById('picker-fuzz-search');
    if (sFuzz) sFuzz.addEventListener('input', function() {
        clearTimeout(pickerFuzzDebounce);
        pickerFuzzDebounce = setTimeout(() => { pickerFuzzSearch = this.value.trim(); loadPickerFuzz(1); }, 300);
    });
    
    const fPrev = document.getElementById('picker-fuzz-prev');
    const fNext = document.getElementById('picker-fuzz-next');
    if (fPrev) fPrev.addEventListener('click', () => loadPickerFuzz(pickerFuzzPage - 1));
    if (fNext) fNext.addEventListener('click', () => loadPickerFuzz(pickerFuzzPage + 1));
    
    // Storyboard
    const sSb = document.getElementById('picker-storyboard-search');
    if (sSb) sSb.addEventListener('input', function() {
        clearTimeout(pickerSbDebounce);
        pickerSbDebounce = setTimeout(() => { pickerSbSearch = this.value.trim(); loadPickerStoryboards(1); }, 300);
    });
    
    const sPrev = document.getElementById('picker-storyboard-prev');
    const sNext = document.getElementById('picker-storyboard-next');
    if (sPrev) sPrev.addEventListener('click', () => loadPickerStoryboards(pickerSbPage - 1));
    if (sNext) sNext.addEventListener('click', () => loadPickerStoryboards(pickerSbPage + 1));

    // Numeric inputs for jumping pages
    document.getElementById('abPageInput')?.addEventListener('change', function() { gotoAbPage(this.value); });
    document.getElementById('binPageInput')?.addEventListener('change', function() { gotoBinPage(this.value); });
    document.getElementById('picker-fuzz-page-input')?.addEventListener('change', function() { gotoFuzzPage(this.value); });
    document.getElementById('picker-storyboard-page-input')?.addEventListener('change', function() { gotoSbPage(this.value); });
});