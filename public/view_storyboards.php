<?php
// view_storyboards.php - Overview of all storyboards
require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Storyboards";
ob_start();
?>

<!-- Dependencies -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css" />

<style>
/* ── FORGE DESIGN TOKENS ── */
:root {
    --forge-bg:          #080b10;
    --forge-surface:     #0e1319;
    --forge-card:        #111820;
    --forge-card-hover:  #141e28;
    --forge-border:      #1c2535;
    --forge-border-glow: #2a3a52;
    --forge-text:        #c8d4e8;
    --forge-text-dim:    #5a6a80;
    --forge-text-bright: #e8f0ff;
    --forge-amber:       #f5a623;
    --forge-amber-dim:   rgba(245,166,35,0.08);
    --forge-amber-mid:   rgba(245,166,35,0.15);
    --forge-amber-glow:  rgba(245,166,35,0.4);
    --forge-green:       #22d3a0;
    --forge-green-dim:   rgba(34,211,160,0.1);
    --forge-red:         #f05060;
    --forge-red-dim:     rgba(240,80,96,0.1);
    --forge-blue:        #4da6ff;
    --forge-blue-dim:    rgba(77,166,255,0.1);
    --mono: 'Space Mono', 'Fira Mono', monospace;
    --sans: 'Syne', system-ui, sans-serif;
    --forge-radius: 6px;
}

[data-theme="light"], html[data-theme="light"] {
    --forge-bg:          #f6f8fa;
    --forge-surface:     #e1e4e8;
    --forge-card:        #ffffff;
    --forge-card-hover:  #f3f4f6;
    --forge-border:      #d1d5db;
    --forge-border-glow: #9ca3af;
    --forge-text:        #111827;
    --forge-text-dim:    #4b5563;
    --forge-text-bright: #000000;
    --forge-amber:       #d97706;
    --forge-amber-dim:   rgba(217,119,6,0.1);
    --forge-amber-mid:   rgba(217,119,6,0.2);
    --forge-amber-glow:  rgba(217,119,6,0.4);
    --forge-green:       #059669;
    --forge-green-dim:   rgba(5,150,105,0.1);
    --forge-red:         #dc2626;
    --forge-red-dim:     rgba(220,38,38,0.1);
    --forge-blue:        #2563eb;
    --forge-blue-dim:    rgba(37,99,235,0.1);
}

/* ── PAGE WRAP ── */
.sb-forge-wrap {
    padding: 10px;
    font-family: var(--sans);
    color: var(--forge-text);
}

/* ── FORGE HEADER BAR ── */
.sb-forge-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
}
.sb-forge-logo {
    font-family: var(--mono);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--forge-amber);
    letter-spacing: 2px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
    flex-shrink: 0;
}
.sb-forge-logo-icon {
    width: 26px; height: 26px;
    background: var(--forge-amber-mid);
    border: 1px solid var(--forge-amber-glow);
    border-radius: var(--forge-radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
}

/* ── FILTER STRIP ── */
.sb-filter-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
}

/* Search */
.sb-search-wrap {
    position: relative;
    flex: 1;
    min-width: 120px;
    max-width: 220px;
}
.sb-search-icon {
    position: absolute;
    left: 8px; top: 50%;
    transform: translateY(-50%);
    color: var(--forge-text-dim);
    font-size: 12px;
    pointer-events: none;
}
.sb-search-input {
    width: 90%;
    padding: 6px 8px 6px 26px;
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text);
    font-family: var(--mono);
    font-size: 0.75rem;
    transition: border-color 0.2s;
}
.sb-search-input::placeholder { color: var(--forge-text-dim); }
.sb-search-input:focus { outline: none; border-color: var(--forge-amber); }

/* Category select */
.sb-filter-select {
    padding: 6px 10px;
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text);
    font-family: var(--mono);
    font-size: 0.75rem;
    min-width: 120px;
    transition: border-color 0.2s;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    padding-right: 24px;
    cursor: pointer;
}
.sb-filter-select:focus { outline: none; border-color: var(--forge-amber); }

/* Editorial cascading row */
.sb-editorial-row {
    display: none;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    width: 100%;
    padding-top: 4px;
    border-top: 1px solid var(--forge-border);
    margin-top: 2px;
}
.sb-editorial-row.visible { display: flex; }

/* Archive toggle */
.sb-archive-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-family: var(--mono);
    font-size: 0.72rem;
    color: var(--forge-text-dim);
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
.sb-archive-label input { margin: 0; accent-color: var(--forge-amber); }

/* Status */
#sb-status-msg {
    font-family: var(--mono);
    font-size: 0.7rem;
    color: var(--forge-text-dim);
    margin-left: auto;
}

/* New button */
.sb-btn-new {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--forge-amber);
    color: #000;
    border: none;
    border-radius: var(--forge-radius);
    font-family: var(--mono);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    transition: filter 0.15s, transform 0.15s;
    white-space: nowrap;
    flex-shrink: 0;
}
.sb-btn-new:hover { filter: brightness(1.12); transform: translateY(-1px); }

/* ── FORCED TWO-COLUMN GRID ── */
.sb-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

/* ── CARD ── */
.storyboard-card {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s, transform 0.15s;
    position: relative;
}
.storyboard-card:hover {
    border-color: var(--forge-amber);
    background: var(--forge-card-hover);
    transform: translateY(-1px);
}
.storyboard-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 2px;
    background: var(--forge-amber);
    opacity: 0;
    transition: opacity 0.2s;
    border-radius: 2px 0 0 2px;
}
.storyboard-card:hover::before { opacity: 1; }

.storyboard-thumb {
    width: 100%;
    height: 100px;
    background: var(--forge-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--forge-text-dim);
    border-bottom: 1px solid var(--forge-border);
    overflow: hidden;
}
.storyboard-thumb img { width: 100%; height: 100%; object-fit: cover; }

.storyboard-info { padding: 8px 10px; }
.storyboard-context {
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Context badges using forge palette */
.ctx-badge {
    font-family: var(--mono);
    font-size: 0.6rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 3px;
    border: 1px solid;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ctx-editorial { border-color: var(--forge-blue);  color: var(--forge-blue);  background: var(--forge-blue-dim); }
.ctx-location   { border-color: var(--forge-green); color: var(--forge-green); background: var(--forge-green-dim); }
.ctx-character  { border-color: var(--forge-amber); color: var(--forge-amber); background: var(--forge-amber-dim); }
.ctx-misc       { border-color: var(--forge-border-glow); color: var(--forge-text-dim); background: transparent; }

.storyboard-title {
    font-family: var(--sans);
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--forge-text-bright);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}
.storyboard-details {
    font-family: var(--mono);
    font-size: 0.65rem;
    color: var(--forge-text-dim);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.storyboard-actions {
    display: flex;
    gap: 4px;
    margin-top: 4px;
    border-top: 1px solid var(--forge-border);
    padding-top: 6px;
    flex-wrap: wrap;
}

/* Forge-style action buttons */
.sb-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 4px 7px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.65rem;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.sb-action-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); background: var(--forge-amber-dim); }
.sb-action-btn.danger:hover { border-color: var(--forge-red); color: var(--forge-red); background: var(--forge-red-dim); }
.sb-action-btn .spacer { flex: 1; }

/* ── EMPTY STATE ── */
#sb-empty-state {
    display: none;
    text-align: center;
    padding: 50px 20px;
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.8rem;
}
#sb-empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.3; display: block; }

/* ── MODAL ── */
.modal {
    display: none; position: fixed; z-index: 1000; left: 0; top: 0;
    width: 100%; height: 100%; background: rgba(0,0,0,0.75);
    align-items: center; justify-content: center;
    backdrop-filter: blur(3px);
}
.modal.active { display: flex; }
.modal-content {
    background: var(--forge-surface);
    border: 1px solid var(--forge-border-glow);
    padding: 20px;
    border-radius: 10px;
    max-width: 550px;
    width: 92%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    font-family: var(--sans);
    color: var(--forge-text);
}
.modal-content .form-group { margin-bottom: 14px; }
.modal-content label {
    display: block;
    font-family: var(--mono);
    font-size: 0.68rem;
    color: var(--forge-text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 5px;
}
.modal-content .form-control {
    width: 100%;
    padding: 8px 10px;
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text);
    font-family: var(--mono);
    font-size: 0.78rem;
    transition: border-color 0.15s;
}
.modal-content .form-control:focus { outline: none; border-color: var(--forge-amber); }
.modal-content textarea.form-control { height: 60px; resize: vertical; }
.form-row { display: flex; gap: 10px; }
.form-col { flex: 1; min-width: 0; }
.cascading-selects {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    padding: 10px;
    border-radius: var(--forge-radius);
    margin-bottom: 12px;
    display: none;
}

/* Modal title */
.modal-forge-title {
    font-family: var(--mono);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--forge-amber);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 16px;
}

/* Modal footer buttons */
.modal-footer-btns {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 18px;
}
.btn-forge-secondary {
    padding: 7px 14px;
    background: transparent;
    color: var(--forge-text-dim);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    cursor: pointer;
    font-family: var(--mono);
    font-size: 0.75rem;
    transition: all 0.15s;
}
.btn-forge-secondary:hover { border-color: var(--forge-border-glow); color: var(--forge-text); }
.btn-forge-primary {
    padding: 7px 16px;
    background: var(--forge-amber);
    color: #000;
    border: none;
    border-radius: var(--forge-radius);
    cursor: pointer;
    font-family: var(--mono);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: filter 0.15s;
}
.btn-forge-primary:hover { filter: brightness(1.1); }

/* Mobile: ensure two columns but reduce thumb height */
@media (max-width: 480px) {
    .storyboard-thumb { height: 75px; }
    .sb-search-wrap { max-width: 100%; flex: 1 1 100%; order: -1; }
}
</style>

<div class="sb-forge-wrap">

    <!-- FORGE HEADER -->
    <div class="sb-forge-header">
        <div class="sb-forge-logo">
            <div class="sb-forge-logo-icon">⬛</div>
            Storyboards
        </div>
        <div style="flex:1"></div>
        <div id="sb-status-msg"></div>
    </div>

    <!-- FILTER STRIP -->
    <div class="sb-filter-strip">

        <button id="btn-create" class="sb-btn-new">
            <i class="fa fa-plus"></i> New
        </button>

        <!-- Search -->
        <div class="sb-search-wrap">
            <i class="fa fa-search sb-search-icon"></i>
            <input type="text" id="filter-search" class="sb-search-input" placeholder="Search storyboards…" autocomplete="off">
        </div>

        <!-- Category -->
        <select id="filter-category" class="sb-filter-select">
            <option value="all">All Categories</option>
        </select>

        <!-- Archive toggle -->
        <label class="sb-archive-label">
            <input type="checkbox" id="filter-show-archived">
            Archived
        </label>

        <!-- Editorial cascading row (full-width, shown below when editorial selected) -->
        <div class="sb-editorial-row" id="filter-editorial-group">
            <select id="filter-episode" class="sb-filter-select"><option value="">All Episodes</option></select>
            <select id="filter-sequence" class="sb-filter-select" disabled><option value="">All Sequences</option></select>
            <select id="filter-scene" class="sb-filter-select" disabled><option value="">All Scenes</option></select>
        </div>

    </div>

    <!-- GRID -->
    <div id="storyboards-grid" class="sb-grid"></div>

    <div id="sb-empty-state">
        <i class="fa fa-film"></i>
        No storyboards found.
    </div>

</div>

<!-- Create/Edit Modal -->
<div id="modal-edit" class="modal">
    <div class="modal-content">
        <div class="modal-forge-title" id="modal-title">Create Storyboard</div>
        <form id="form-storyboard">
            <input type="hidden" id="edit-id" value="">

            <div class="form-group">
                <label>Name *</label>
                <input class="form-control" type="text" id="edit-name" required placeholder="e.g. Hero Close-up">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label>Category</label>
                    <select id="edit-category" class="form-control" style="width:100%"></select>
                </div>
                <div class="form-col form-group" id="group-custom-tag">
                    <label id="lbl-custom-tag">Tag / Context</label>
                    <input class="form-control" type="text" id="edit-custom-tag" placeholder="Optional tag...">
                </div>
            </div>

            <div id="group-editorial-selects" class="cascading-selects">
                <div class="form-group">
                    <label>Link to Scene</label>
                    <div style="display:flex; gap:8px; flex-direction:column;">
                        <select id="edit-episode" class="form-control"><option value="">Select Episode...</option></select>
                        <select id="edit-sequence" class="form-control" disabled><option value="">Select Sequence...</option></select>
                        <select id="edit-scene" class="form-control" disabled><option value="">Select Scene...</option></select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" id="edit-description" style="height:60px"></textarea>
            </div>

            <div class="modal-footer-btns">
                <button type="button" class="btn-forge-secondary" id="btn-cancel">Cancel</button>
                <button type="submit" class="btn-forge-primary">Save Storyboard</button>
            </div>
        </form>
    </div>
</div>

<?= $spw->getJquery() ?>

<script>
$(function() {
    let allStoryboards = [];
    let categories = [];
    let episodesData = [];

    // --- INITIALIZATION ---
    loadCategories().then(() => {
        loadStoryboards();
        loadEpisodes();
    });

    // --- DATA LOADING ---

    function loadCategories() {
        return $.get('storyboards_api.php?action=get_categories').done(function(res) {
            if(res.success) {
                categories = res.data;
                const $filter = $('#filter-category');
                const $edit = $('#edit-category');

                $filter.html('<option value="all">All Categories</option>');
                $edit.empty();

                categories.forEach(c => {
                    $filter.append(`<option value="${c.id}" data-code="${c.code}">${c.name}</option>`);
                    $edit.append(`<option value="${c.id}" data-code="${c.code}">${c.name}</option>`);
                });
            }
        });
    }

    function loadStoryboards() {
        const showArchived = $('#filter-show-archived').is(':checked');
        const url = 'storyboards_api.php?action=list' + (showArchived ? '&archived=true' : '');

        $.get(url).done(function(res) {
            if (res.success) {
                allStoryboards = res.data;
                filterAndRender();
            }
        });
    }

    function loadEpisodes() {
        $.get('storyboards_api.php?action=get_episodes').done(function(res) {
            if(res.success) {
                episodesData = res.data;
                populateSelect('#filter-episode', episodesData, 'All Episodes');
                populateSelect('#edit-episode', episodesData, 'Select Episode...');
            }
        });
    }

    function loadSequences(epId, targetSelect) {
        if(!epId) {
            $(targetSelect).html('<option value="">Select Sequence...</option>').prop('disabled', true);
            return;
        }
        $.get('storyboards_api.php?action=get_sequences&episode_id=' + epId).done(function(res) {
            populateSelect(targetSelect, res.data, 'Select Sequence...');
            $(targetSelect).prop('disabled', false);
        });
    }

    function loadScenes(seqId, targetSelect) {
        if(!seqId) {
            $(targetSelect).html('<option value="">Select Scene...</option>').prop('disabled', true);
            return;
        }
        $.get('storyboards_api.php?action=get_scenes&sequence_id=' + seqId).done(function(res) {
            populateSelect(targetSelect, res.data, 'Select Scene...');
            $(targetSelect).prop('disabled', false);
        });
    }

    function populateSelect(selector, data, defaultText) {
        const $el = $(selector);
        $el.empty().append(`<option value="">${defaultText}</option>`);
        data.forEach(item => {
            let label = item.name;
            if(item.number) label = "Ep " + item.number + ": " + label;
            $el.append(`<option value="${item.id}">${label}</option>`);
        });
    }

    function isEditorial(catId) {
        const c = categories.find(cat => cat.id == catId);
        return c && c.code === 'editorial';
    }

    // --- FILTER LOGIC ---

    function filterAndRender() {
        const catId = $('#filter-category').val();
        const isEd = (catId !== 'all') && isEditorial(catId);
        const isArchiveView = $('#filter-show-archived').is(':checked');
        const searchTerm = $('#filter-search').val().toLowerCase().trim();

        let filtered = allStoryboards;

        if (catId !== 'all') {
            filtered = filtered.filter(s => s.category_id == catId);
        }

        if (isEd) {
            const epId = $('#filter-episode').val();
            const seqId = $('#filter-sequence').val();
            const scId = $('#filter-scene').val();

            if (epId) filtered = filtered.filter(s => s.episode_id == epId);
            if (seqId) filtered = filtered.filter(s => s.sequence_id == seqId);
            if (scId) filtered = filtered.filter(s => s.editorial_scene_id == scId);
        }

        // Search filter (name, custom_tag, scene_name, sequence_name, episode_name)
        if (searchTerm) {
            filtered = filtered.filter(s => {
                return (s.name || '').toLowerCase().includes(searchTerm)
                    || (s.custom_tag || '').toLowerCase().includes(searchTerm)
                    || (s.scene_name || '').toLowerCase().includes(searchTerm)
                    || (s.sequence_name || '').toLowerCase().includes(searchTerm)
                    || (s.episode_name || '').toLowerCase().includes(searchTerm);
            });
        }

        renderGrid(filtered, isArchiveView);
    }

    function renderGrid(list, isArchiveView) {
        const $grid = $('#storyboards-grid');
        const $empty = $('#sb-empty-state');
        $grid.empty();

        if (list.length === 0) {
            $grid.hide(); $empty.show(); return;
        }
        $grid.show(); $empty.hide();

        $('#sb-status-msg').text(list.length + ' items');

        list.forEach(sb => {
            const thumbHtml = sb.thumbnail
                ? `<img src="${sb.thumbnail}" loading="lazy">`
                : '<i class="fa fa-image fa-2x"></i>';

            const catObj = categories.find(c => c.id == sb.category_id) || { code:'misc', name:'Misc' };
            const isEd = (catObj.code === 'editorial');

            let contextBadge = '';
            let contextText = '';

            if (isEd && sb.scene_name) {
                contextBadge = `<span class="ctx-badge ctx-editorial"><i class="fa fa-video"></i> ${catObj.name}</span>`;
                contextText = `Ep ${sb.episode_number} › ${sb.sequence_name} › ${sb.scene_name}`;
            } else if (catObj.code === 'location') {
                contextBadge = `<span class="ctx-badge ctx-location"><i class="fa fa-map-marker-alt"></i> ${catObj.name}</span>`;
                contextText = sb.custom_tag || 'Unspecified';
            } else if (catObj.code === 'character') {
                contextBadge = `<span class="ctx-badge ctx-character"><i class="fa fa-user"></i> ${catObj.name}</span>`;
                contextText = sb.custom_tag || 'Unspecified';
            } else {
                contextBadge = `<span class="ctx-badge ctx-misc">${catObj.name}</span>`;
                contextText = sb.custom_tag || '';
            }

            const archiveBtnTitle = isArchiveView ? 'Unarchive (Restore)' : 'Archive';
            const archiveBtnIcon = isArchiveView ? 'fa-box-open' : 'fa-box-archive';
            const archiveAction = isArchiveView ? 0 : 1;

            const $card = $(`
                <div class="storyboard-card" data-id="${sb.id}">
                    <div class="storyboard-thumb">${thumbHtml}</div>
                    <div class="storyboard-info">
                        <div class="storyboard-context">${contextBadge}</div>
                        <div class="storyboard-title" title="${escapeHtml(sb.name)}">${escapeHtml(sb.name)}</div>
                        <div class="storyboard-details" title="${escapeHtml(contextText)}">${escapeHtml(contextText) || '&nbsp;'}</div>
                        <div class="storyboard-details">${sb.frame_count} frames</div>
                        <div class="storyboard-actions">
                            <button class="sb-action-btn btn-detailview" data-id="${sb.id}" title="Open">
                                <i class="fa fa-eye"></i> View
                            </button>
                            <button class="sb-action-btn btn-magic" data-id="${sb.id}" title="ScrollMagic">
                                <i class="fa fa-scroll"></i>
                            </button>
                            <div style="flex:1"></div>
                            <button class="sb-action-btn btn-archive" data-id="${sb.id}" data-action="${archiveAction}" title="${archiveBtnTitle}">
                                <i class="fa ${archiveBtnIcon}"></i>
                            </button>
                            <button class="sb-action-btn btn-edit" data-id="${sb.id}" title="Edit"><i class="fa fa-edit"></i></button>
                            <button class="sb-action-btn btn-copy" data-id="${sb.id}" title="Duplicate"><i class="fa fa-copy"></i></button>
                            <button class="sb-action-btn danger btn-delete" data-id="${sb.id}" title="Delete"><i class="fa fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            `);
            $grid.append($card);
        });
    }

    // --- EVENTS: FILTERS ---

    $('#filter-category').on('change', function() {
        const val = $(this).val();
        const isEd = isEditorial(val);

        if (isEd) {
            $('#filter-editorial-group').addClass('visible');
        } else {
            $('#filter-editorial-group').removeClass('visible');
            $('#filter-episode').val('');
            $('#filter-sequence').html('<option value="">All Sequences</option>').prop('disabled',true);
            $('#filter-scene').html('<option value="">All Scenes</option>').prop('disabled',true);
        }
        filterAndRender();
    });

    $('#filter-episode').on('change', function() {
        loadSequences($(this).val(), '#filter-sequence');
        $('#filter-scene').html('<option value="">All Scenes</option>').prop('disabled',true);
        filterAndRender();
    });

    $('#filter-sequence').on('change', function() {
        loadScenes($(this).val(), '#filter-scene');
        filterAndRender();
    });

    $('#filter-scene').on('change', filterAndRender);

    // Live search (debounced)
    let _searchTimer = null;
    $('#filter-search').on('input', function() {
        clearTimeout(_searchTimer);
        _searchTimer = setTimeout(filterAndRender, 180);
    });

    $('#filter-show-archived').on('change', function() {
        loadStoryboards();
    });

    // --- EVENTS: MODAL & ACTIONS ---

    $('#btn-create').click(function() {
        resetModal();
        $('#modal-title').text('Create Storyboard');
        $('#modal-edit').addClass('active');
    });

    $(document).on('click', '.btn-edit', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const sb = allStoryboards.find(s => s.id == id);
        if(!sb) return;

        resetModal();
        $('#modal-title').text('Edit Storyboard');
        $('#edit-id').val(sb.id);
        $('#edit-name').val(sb.name);
        $('#edit-description').val(sb.description);
        $('#edit-category').val(sb.category_id).trigger('change');
        $('#edit-custom-tag').val(sb.custom_tag);

        const catObj = categories.find(c => c.id == sb.category_id);
        if(catObj && catObj.code === 'editorial' && sb.editorial_scene_id) {
            $('#edit-episode').val(sb.episode_id);
            $.get('storyboards_api.php?action=get_sequences&episode_id=' + sb.episode_id).done(function(res){
                populateSelect('#edit-sequence', res.data, 'Select Sequence...');
                $('#edit-sequence').prop('disabled', false).val(sb.sequence_id);
                $.get('storyboards_api.php?action=get_scenes&sequence_id=' + sb.sequence_id).done(function(res2){
                    populateSelect('#edit-scene', res2.data, 'Select Scene...');
                    $('#edit-scene').prop('disabled', false).val(sb.editorial_scene_id);
                });
            });
        }
        $('#modal-edit').addClass('active');
    });

    $('#btn-cancel').click(() => $('#modal-edit').removeClass('active'));

    $('#edit-category').change(function() {
        const val = $(this).val();
        const isEd = isEditorial(val);
        if (isEd) {
            $('#group-custom-tag').hide();
            $('#group-editorial-selects').slideDown();
        } else {
            $('#group-custom-tag').show();
            $('#group-editorial-selects').slideUp();
            const catObj = categories.find(c => c.id == val);
            const lbl = (catObj && catObj.code === 'location') ? 'Location Name'
                      : (catObj && catObj.code === 'character') ? 'Character Name'
                      : 'Tag / Context';
            $('#lbl-custom-tag').text(lbl);
        }
    });

    $('#edit-episode').change(function() {
        loadSequences($(this).val(), '#edit-sequence');
        $('#edit-scene').html('<option value="">Select Scene...</option>').prop('disabled',true);
    });

    $('#edit-sequence').change(function() {
        loadScenes($(this).val(), '#edit-scene');
    });

    $('#form-storyboard').submit(function(e) {
        e.preventDefault();
        const catId = $('#edit-category').val();
        const isEd = isEditorial(catId);
        const data = {
            action: $('#edit-id').val() ? 'update' : 'create',
            id: $('#edit-id').val(),
            name: $('#edit-name').val(),
            description: $('#edit-description').val(),
            category_id: catId,
            custom_tag: $('#edit-custom-tag').val(),
            editorial_scene_id: $('#edit-scene').val()
        };
        if (isEd && !data.editorial_scene_id) {
            alert('Please select a Scene for this Editorial storyboard.');
            return;
        }
        $.post('storyboards_api.php', data).done(function(res) {
            if(res.success) {
                $('#modal-edit').removeClass('active');
                loadStoryboards();
            } else {
                alert('Error: ' + res.message);
            }
        });
    });

    function resetModal() {
        $('#edit-id').val('');
        $('#form-storyboard')[0].reset();
        if(categories.length > 0) $('#edit-category').val(categories[0].id).trigger('change');
        $('#edit-episode').val('');
        $('#edit-sequence').empty().prop('disabled', true);
        $('#edit-scene').empty().prop('disabled', true);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $(document).on('click', '.btn-delete', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        if(!confirm('Delete this storyboard?')) return;
        $.post('storyboards_api.php', {action:'delete', id:id}, function() { loadStoryboards(); });
    });

    $(document).on('click', '.btn-copy', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        if(!confirm('Duplicate this storyboard?')) return;
        $.post('storyboards_api.php', { action: 'copy', id: id }).done(function(res) {
            if (res.success) loadStoryboards();
            else alert('Copy failed: ' + (res.message || 'Unknown error'));
        });
    });

    $(document).on('click', '.btn-archive', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const actionCode = $(this).data('action');
        const actionVerb = actionCode === 1 ? 'Archive' : 'Restore';

        if(!confirm(actionVerb + ' this storyboard?')) return;

        $.post('storyboards_api.php', { action: 'toggle_archive', id: id, is_archived: actionCode })
            .done(function(res) {
                if(res.success) loadStoryboards();
                else alert('Error: ' + res.message);
            });
    });

    $(document).on('click', '.storyboard-card', function(e) {
        if ($(e.target).closest('.sb-action-btn').length) return;
        window.location.href = 'view_storyboard.php?id=' + $(this).data('id');
    });

    $(document).on('click', '.btn-detailview', function(e) {
        window.location.href = 'view_storyboard.php?id=' + $(this).data('id');
    });

    $(document).on('click', '.btn-magic', function(e) {
        e.stopPropagation();
        window.location = "/view_scrollmagic_multi_prm.php?storyboard_ids="+$(this).data('id')+"&refresh=true";
    });
});
</script>
<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/storyboards.php');
?>
