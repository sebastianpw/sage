<?php
// public/view_editorial_sequences.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();

$episodeId = (int)($_GET['episode_id'] ?? 0);
$pageTitle = "Editorial: Sequences";
ob_start();
?>
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/toast.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
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
    --forge-red:         #dc2626;
    --forge-red-dim:     rgba(220,38,38,0.1);
    --forge-blue:        #2563eb;
    --forge-blue-dim:    rgba(37,99,235,0.1);
}

/* ── PAGE ── */
.view-wrap {
    padding: 10px;
    font-family: var(--sans);
    color: var(--forge-text);
}

/* ── FORGE HEADER BAR ── */
.forge-header-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.forge-logo {
    font-family: var(--mono);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--forge-amber);
    letter-spacing: 2px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 1;
    min-width: 0;
    overflow: hidden;
}
.forge-logo span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.forge-logo-icon {
    width: 26px; height: 26px;
    background: var(--forge-amber-mid);
    border: 1px solid var(--forge-amber-glow);
    border-radius: var(--forge-radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
}
.forge-breadcrumb {
    font-family: var(--mono);
    font-size: 0.7rem;
    color: var(--forge-text-dim);
    display: flex;
    align-items: center;
    gap: 5px;
    flex: 1;
    min-width: 0;
    flex-wrap: wrap;
}
.forge-breadcrumb a {
    color: var(--forge-text-dim);
    text-decoration: none;
    transition: color 0.15s;
}
.forge-breadcrumb a:hover { color: var(--forge-amber); }
.forge-breadcrumb .sep { opacity: 0.4; }

.btn-forge-new {
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
.btn-forge-new:hover { filter: brightness(1.12); transform: translateY(-1px); }

/* ── GRID ── */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 0;
}

/* ── CARD ── */
.card {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: border-color 0.2s, background 0.2s, transform 0.15s;
    position: relative;
}
.card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 2px;
    background: var(--forge-amber);
    opacity: 0;
    transition: opacity 0.2s;
    border-radius: 2px 0 0 2px;
}
.card:hover {
    border-color: var(--forge-amber);
    background: var(--forge-card-hover);
    transform: translateY(-1px);
}
.card:hover::before { opacity: 1; }

.thumb {
    height: 120px;
    background: var(--forge-surface);
    position: relative;
    cursor: pointer;
    overflow: hidden;
    border-bottom: 1px solid var(--forge-border);
}
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.thumb-empty {
    display: flex; align-items: center; justify-content: center;
    height: 100%; color: var(--forge-text-dim);
    font-family: var(--mono); font-size: 0.72rem;
}
.sort-badge {
    position: absolute; top: 6px; left: 6px;
    background: rgba(0,0,0,0.7);
    color: var(--forge-amber);
    padding: 2px 6px;
    border-radius: 3px;
    font-family: var(--mono);
    font-size: 0.65rem;
    font-weight: 700;
    border: 1px solid var(--forge-amber-glow);
}

.info { padding: 10px 12px; flex: 1; cursor: pointer; }
.title {
    font-family: var(--sans);
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--forge-text-bright);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.meta {
    display: flex;
    justify-content: space-between;
    font-family: var(--mono);
    font-size: 0.65rem;
    color: var(--forge-text-dim);
}
.desc-preview {
    font-family: var(--mono);
    font-size: 0.65rem;
    color: var(--forge-text-dim);
    margin-top: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.actions {
    padding: 7px 10px;
    border-top: 1px solid var(--forge-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
    background: var(--forge-surface);
}
.btn-group { display: flex; gap: 4px; }

/* Forge action buttons */
.forge-btn {
    display: flex; align-items: center; justify-content: center;
    padding: 4px 9px;
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
.forge-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); background: var(--forge-amber-dim); }
.forge-btn.danger:hover { border-color: var(--forge-red); color: var(--forge-red); background: var(--forge-red-dim); }

/* Drag handle */
.handle {
    cursor: grab;
    padding: 4px 7px;
    border-radius: var(--forge-radius);
    border: 1px solid var(--forge-border);
    color: var(--forge-text-dim);
    font-size: 1rem;
    line-height: 1;
    user-select: none;
    background: transparent;
    transition: all 0.15s;
}
.handle:hover { border-color: var(--forge-border-glow); color: var(--forge-text); }
.handle:active { cursor: grabbing; }

/* ── EMPTY STATE ── */
#empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.8rem;
}
#empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.3; display: block; }

/* ── MODAL ── */
.modal {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    display: none; align-items: center; justify-content: center;
    z-index: 1000;
    backdrop-filter: blur(3px);
}
.modal.active { display: flex; }
.modal-content {
    background: var(--forge-surface);
    border: 1px solid var(--forge-border-glow);
    padding: 20px;
    border-radius: 10px;
    width: 92%;
    max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    font-family: var(--sans);
    color: var(--forge-text);
}
.modal-forge-title {
    font-family: var(--mono);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--forge-amber);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 16px;
}
.form-group { margin-bottom: 13px; }
.form-group label {
    display: block;
    font-family: var(--mono);
    font-size: 0.67rem;
    color: var(--forge-text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 5px;
}
.form-group label .hint {
    font-weight: normal;
    color: var(--forge-text-dim);
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.62rem;
    opacity: 0.7;
    margin-left: 4px;
}
.form-control {
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
.form-control:focus { outline: none; border-color: var(--forge-amber); }
textarea.form-control { resize: vertical; }
.modal-footer {
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

@media (max-width: 480px) {
    .grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
    .thumb { height: 90px; }
}
</style>

<div class="view-wrap">

    <!-- FORGE HEADER -->
    <div class="forge-header-bar">
        <div class="forge-logo">
            <div class="forge-logo-icon">🎞</div>
            <span id="page-title">Sequences</span>
        </div>
        <div class="forge-breadcrumb" id="breadcrumb">
            <a href="view_editorial_episodes.php">Episodes</a>
            <span class="sep">›</span>
            <span>Sequences</span>
        </div>
        <button class="btn-forge-new" id="btn-create">+ New Sequence</button>
    </div>

    <div id="grid" class="grid"></div>
    <div id="empty-state" style="display:none;">
        <span class="empty-icon">🎞️</span>
        No sequences found. Create one to get started.
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-forge-title" id="modal-title">Create Sequence</div>
        <input type="hidden" id="edit-id">
        <div class="form-group">
            <label>Name</label>
            <input type="text" id="inp-name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea id="inp-desc" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>Sort Order <span class="hint">(Optional, 0 = NULL)</span></label>
            <input type="number" id="inp-sort" class="form-control" placeholder="Default: NULL">
        </div>
        <div class="modal-footer">
            <button class="btn-forge-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn-forge-primary" id="btn-save">Save</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="js/toast.js"></script>
<script>
const EPISODE_ID = <?php echo $episodeId; ?>;
let sequences = [];

// Init
(async () => {
    const epData = await fetch(`editorial_api.php?action=get_episode_details&episode_id=${EPISODE_ID}`).then(r=>r.json());
    if(epData.success) {
        document.getElementById('page-title').textContent = `${epData.data.name} (Ep ${epData.data.number})`;
    }
    loadItems();
})();

async function loadItems() {
    const res = await fetch(`editorial_api.php?action=list_sequences&episode_id=${EPISODE_ID}`).then(r=>r.json());
    if(res.success) {
        sequences = res.data;
        render();
    }
}

function render() {
    const grid = document.getElementById('grid');
    const empty = document.getElementById('empty-state');
    grid.innerHTML = '';

    if(!sequences.length) {
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';

    sequences.forEach(seq => {
        const thumb = seq.thumbnail ? `<img src="${seq.thumbnail}">` : `<div class="thumb-empty">No preview</div>`;
        const sortDisp = (seq.sort_order !== null && seq.sort_order !== undefined) ? seq.sort_order : '-';

        const el = document.createElement('div');
        el.className = 'card';
        el.dataset.id = seq.id;
        el.innerHTML = `
            <div class="thumb">
                ${thumb}
                <div class="sort-badge" title="Sort Order">#${sortDisp}</div>
            </div>
            <div class="info">
                <div class="title">${esc(seq.name)}</div>
                <div class="meta">
                    <span>${seq.scene_count} scenes</span>
                    <span>ID: ${seq.id}</span>
                </div>
                ${seq.description ? `<div class="desc-preview">${esc(seq.description)}</div>` : ''}
            </div>
            <div class="actions">
                <div class="handle" title="Drag to reorder">☰</div>
                <div class="btn-group">
                    <button class="forge-btn btn-edit">Edit</button>
                    <button class="forge-btn danger btn-del">Delete</button>
                </div>
            </div>
        `;

        const nav = () => window.location.href = `view_editorial_scenes.php?sequence_id=${seq.id}`;
        el.querySelector('.thumb').onclick = nav;
        el.querySelector('.info').onclick = nav;

        el.querySelector('.btn-edit').onclick = (e) => { e.stopPropagation(); openModal(seq); };
        el.querySelector('.btn-del').onclick = (e) => { e.stopPropagation(); deleteItem(seq.id); };

        grid.appendChild(el);
    });
}

// Drag & Drop
new Sortable(document.getElementById('grid'), {
    animation: 150,
    handle: '.handle',
    onEnd: () => {
        const ids = Array.from(document.querySelectorAll('.card')).map(el => el.dataset.id);
        fetch('editorial_api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `action=reorder_sequences&ids=${JSON.stringify(ids)}`
        });
    }
});

// Modal Logic
const modal = document.getElementById('modal');
const btnCreate = document.getElementById('btn-create');
const btnSave = document.getElementById('btn-save');

function openModal(item = null) {
    document.getElementById('edit-id').value = item ? item.id : '';
    document.getElementById('inp-name').value = item ? item.name : '';
    document.getElementById('inp-desc').value = item ? item.description || '' : '';
    let sortVal = '';
    if (item && item.sort_order !== null) sortVal = item.sort_order;
    document.getElementById('inp-sort').value = sortVal;
    document.getElementById('modal-title').textContent = item ? 'Edit Sequence' : 'New Sequence';
    modal.classList.add('active');
}
function closeModal() { modal.classList.remove('active'); }
btnCreate.onclick = () => openModal();

btnSave.onclick = async () => {
    const id = document.getElementById('edit-id').value;
    const name = document.getElementById('inp-name').value;
    const desc = document.getElementById('inp-desc').value;
    const sort = document.getElementById('inp-sort').value;

    const action = id ? 'update_sequence' : 'create_sequence';
    const body = new URLSearchParams({
        action, id, name, description: desc, sort_order: sort, episode_id: EPISODE_ID
    });

    const res = await fetch('editorial_api.php', { method:'POST', body }).then(r=>r.json());
    if(res.success) {
        closeModal();
        loadItems();
        if(typeof Toast !== 'undefined') Toast.show('Saved successfully', 'success');
    } else {
        alert(res.message);
    }
};

async function deleteItem(id) {
    if(!confirm('Delete this sequence?')) return;
    const res = await fetch('editorial_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=delete_sequence&id=${id}`
    }).then(r=>r.json());
    if(res.success) loadItems();
    else alert(res.message);
}

function esc(t) { return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
<?php
$spw->renderLayout(ob_get_clean(), $pageTitle);
?>
