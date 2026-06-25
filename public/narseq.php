<?php
// public/narseq.php
// Narrative Sequencer: Split, Copy & Reorder Tool (Forge UI)

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$seqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper to resolve frame thumbnails identically to cinemagic_editor.php
function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = '';
    foreach (['thumb', 'thumbnail', 'image', 'image_url', 'image_path', 'file_path', 'path', 'src', 'url', 'filename', 'file_name'] as $key) {
        if (!empty($row[$key]) && is_string($row[$key])) {
            $candidate = $row[$key]; break;
        }
    }
    if ($candidate !== '') {
        if (strpos($candidate, 'http') !== 0 && strpos($candidate, 'view_frame.php') === false) {
            $parts = array_map('rawurlencode', explode('/', ltrim($candidate, '/')));
            return '/' . implode('/', $parts);
        }
        return $candidate;
    }
    return $frameId > 0 ? 'view_frame.php?frame_id=' . $frameId : '';
}

// ── Sequence List View (if no ID is provided) ─────────────────────────────────
if (!$seqId) {
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
    :root, [data-theme="dark"] {
        --pl-bg: #080b10; --pl-surface: #0e1319; --pl-card: #111820; --pl-border: #1c2535;
        --pl-text: #c8d4e8; --pl-text-dim: #5a6a80; --pl-amber: #f5a623; --pl-teal: #3ab5c8;
    }
    [data-theme="light"] {
        --pl-bg: #f4f6fa; --pl-surface: #ffffff; --pl-card: #ffffff; --pl-border: #d0d8e8;
        --pl-text: #1a2233; --pl-text-dim: #7a8aaa; --pl-amber: #c8880a; --pl-teal: #1a8090;
    }
    body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', sans-serif; }
    
    .su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
    .su-modal-backdrop.active { display:flex; }
    .su-modal-box { width:100%; max-width:440px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; padding:20px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
    .su-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
    .su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-teal); text-transform:uppercase; letter-spacing:1px; }
    .su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
    .su-input { width:100%; box-sizing:border-box; background:var(--pl-card); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s; }
    .su-input:focus { border-color:var(--pl-teal); }
    .pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
    .pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-card); color:var(--pl-text-dim); }
    .pl-btn-secondary:hover { border-color:var(--pl-teal); color:var(--pl-teal); }
    .pl-btn-primary { border-color:var(--pl-teal); background:var(--pl-teal); color:#000; font-weight:bold; }
    .pl-btn-primary:hover { filter:brightness(1.1); }
    
    .new-seq-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--pl-teal);
        color: #000;
        font-family: 'Space Mono', monospace;
        font-size: 0.8rem;
        font-weight: bold;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: filter 0.15s;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .new-seq-btn:hover { filter: brightness(1.12); }

    /* Autocomplete dropdown styling */
    .ff-dropdown { border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card); max-height: 240px; overflow-y: auto; display: none; position: absolute; z-index: 100; width: 100%; left: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.3); overscroll-behavior: contain; touch-action: pan-y; }
    .ff-dropdown.open { display: block; }
    .ff-dropdown-item { padding: 8px 10px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--pl-text); display: flex; justify-content: space-between; align-items:center; }
    .ff-dropdown-item:hover { background: rgba(58,181,200,0.1); color: var(--pl-teal); }
    </style>

    <div style="max-width:800px;margin:60px auto;padding:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
            <h2 style="font-family:'Space Mono',monospace;color:var(--pl-teal); margin:0;">✂️ Sequence Split & Copy Tool</h2>
            <button class="new-seq-btn" onclick="openNewModal()">＋ New Sequence</button>
        </div>
        
        <!-- Filters & Pagination Bar -->
        <div style="display:flex; gap:12px; margin-bottom: 20px; align-items:center; flex-wrap:wrap; background:var(--pl-surface); padding:12px; border:1px solid var(--pl-border); border-radius:6px;">
            <div style="position:relative; flex:1; min-width: 200px;">
                <input type="text" id="mainSeqSearch" class="su-input" placeholder="Search sequences... (type for quick jump)" oninput="debounceMainSearch(this.value)" autocomplete="off">
                <div id="mainSeqDrop" class="ff-dropdown" style="top:40px;"></div>
            </div>
            <div style="flex:1; min-width: 150px; max-width: 200px;">
                <select id="mainSeqCat" class="su-input" onchange="goToMainPage(1)">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:8px; margin-left:auto;">
                <button class="pl-btn pl-btn-secondary" onclick="changeMainPage(-1)">« Prev</button>
                <div style="font-size:0.8rem; color:var(--pl-text-dim); display:flex; align-items:center; gap:6px;">
                    <input type="number" id="mainPageInput" class="su-input" style="width:50px; text-align:center; padding:4px;" value="1" min="1" onchange="goToMainPage(this.value)"> 
                    <span id="mainTotalPages">of 1</span>
                </div>
                <button class="pl-btn pl-btn-secondary" onclick="changeMainPage(1)">Next »</button>
            </div>
        </div>

        <div id="mainSeqListContainer" style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
            <div style="text-align:center; padding:20px; color:var(--pl-text-dim); font-size:0.85rem;">Loading sequences...</div>
        </div>
    </div>

    <!-- New Sequence Modal -->
    <div id="newModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeNewModal()">
        <div class="su-modal-box">
            <div class="su-modal-header">
                <div class="su-modal-title" style="color:var(--pl-teal);">＋ New Sequence</div>
                <button onclick="closeNewModal()" class="su-modal-close">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Sequence Name</label>
                    <input type="text" id="newSeqName" class="su-input" placeholder="e.g. Act 1 — The Awakening"
                           onkeydown="if(event.key==='Enter') submitNew()">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <button onclick="closeNewModal()" class="pl-btn pl-btn-secondary">Cancel</button>
                <button onclick="submitNew()" class="pl-btn pl-btn-teal">Create & Open</button>
            </div>
        </div>
    </div>

    <!-- Copy Modal -->
    <div id="copyModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeCopyModal()">
        <div class="su-modal-box">
            <div class="su-modal-header">
                <div class="su-modal-title">Copy Sequence</div>
                <button onclick="closeCopyModal()" class="su-modal-close">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" id="copySeqId">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">New Sequence Name</label>
                    <input type="text" id="copySeqName" class="su-input" placeholder="New sequence name…">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <button onclick="closeCopyModal()" class="pl-btn pl-btn-secondary">Cancel</button>
                <button onclick="submitCopy()" class="pl-btn pl-btn-primary">Copy & Load</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeEditModal()">
        <div class="su-modal-box">
            <div class="su-modal-header">
                <div class="su-modal-title" style="color:var(--pl-teal);"><i class="bi bi-pencil"></i> Edit Sequence</div>
                <button onclick="closeEditModal()" class="su-modal-close">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" id="editSeqId">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Sequence Name</label>
                    <input type="text" id="editSeqName" class="su-input" onkeydown="if(event.key==='Enter') submitEdit()">
                </div>
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Category</label>
                    <select id="editSeqCat" class="su-input">
                        <option value="0">No Category</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <button onclick="closeEditModal()" class="pl-btn pl-btn-secondary">Cancel</button>
                <button onclick="submitEdit()" class="pl-btn pl-btn-teal">Save Changes</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/toast.js"></script>
    <script>
    
    // --- MAIN LIST LOGIC ---
    let curMainPage = 1;
    let totalMainPages = 1;

    document.addEventListener('DOMContentLoaded', () => {
        loadMainCategories();
        loadMainSequences();
    });

    function loadMainCategories() {
        fetch('narseq_api.php?action=get_sequence_categories')
            .then(r=>r.json()).then(res => {
                if(res.success && res.data && res.data.length > 0) {
                    const sel = document.getElementById('mainSeqCat');
                    res.data.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        sel.appendChild(opt);
                    });
                }
            }).catch(e=>console.log('Categories not initialized or unavailable'));
    }

    function goToMainPage(p) {
        p = parseInt(p);
        if(isNaN(p) || p < 1) p = 1;
        if(p > totalMainPages) p = totalMainPages;
        curMainPage = p;
        document.getElementById('mainPageInput').value = p;
        loadMainSequences();
    }

    function changeMainPage(delta) {
        goToMainPage(curMainPage + delta);
    }

    function loadMainSequences() {
        const search = document.getElementById('mainSeqSearch').value.trim();
        const catId = document.getElementById('mainSeqCat').value;
        const url = `narseq_api.php?action=get_sequences_list&page=${curMainPage}&search=${encodeURIComponent(search)}&category_id=${catId}`;
        
        const cont = document.getElementById('mainSeqListContainer');
        cont.innerHTML = '<div style="text-align:center; padding:20px; color:var(--pl-text-dim); font-size:0.85rem;">Loading...</div>';

        fetch(url).then(r=>r.json()).then(res => {
            if(res.success) {
                totalMainPages = res.meta.total_pages;
                document.getElementById('mainTotalPages').textContent = 'of ' + totalMainPages;
                document.getElementById('mainPageInput').value = res.meta.page;
                curMainPage = res.meta.page;

                if(!res.data.length) {
                    cont.innerHTML = '<div style="text-align:center; padding:20px; color:var(--pl-text-dim); font-size:0.85rem;">No sequences found.</div>';
                    return;
                }

                cont.innerHTML = res.data.map(s => {
                    const safeName = s.name.replace(/"/g, '&quot;').replace(/'/g, "\\'");
                    return `<div style="display:flex;align-items:center;background:var(--pl-card);border:1px solid var(--pl-border);border-radius:6px;overflow:hidden;transition:border-color .2s;" onmouseover="this.style.borderColor='var(--pl-teal)'" onmouseout="this.style.borderColor='var(--pl-border)'">
                        <a href="?id=${s.id}" style="display:flex;justify-content:space-between;align-items:center;flex:1;padding:12px 14px;text-decoration:none;color:var(--pl-text);font-family:'Space Mono',monospace;font-size:.85rem;">
                            <span>
                                #${s.id} 
                                <span style="color:var(--pl-teal);opacity:0.8;margin:0 6px;">[${s.skt_count} skts]</span> 
                                ${s.name}
                            </span>
                            <span style="color:var(--pl-text-dim);">${s.created_at}</span>
                        </a>
                        <div style="display:flex; border-left:1px solid var(--pl-border); align-self:stretch;">
                            <button onclick="openEditModal(${s.id}, '${safeName}', ${s.category_id || 0})" title="Edit" style="background:transparent;border:none;border-right:1px solid var(--pl-border);padding:0 12px;cursor:pointer;color:var(--pl-text-dim);font-size:.9rem;transition:all .2s;" onmouseover="this.style.color='var(--pl-teal)';this.style.background='rgba(58,181,200,0.07)'" onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'"><i class="bi bi-pencil"></i></button>
                            <button onclick="openCopyModal(${s.id}, '${safeName}')" title="Copy" style="background:transparent;border:none;border-right:1px solid var(--pl-border);padding:0 12px;cursor:pointer;color:var(--pl-text-dim);font-size:.9rem;transition:all .2s;" onmouseover="this.style.color='var(--pl-teal)';this.style.background='rgba(58,181,200,0.07)'" onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'"><i class="bi bi-files"></i></button>
                            <button onclick="deleteSequence(${s.id})" title="Delete" style="background:transparent;border:none;padding:0 12px;cursor:pointer;color:var(--pl-text-dim);font-size:.9rem;transition:all .2s;" onmouseover="this.style.color='#ff4444';this.style.background='rgba(255,68,68,0.07)'" onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>`;
                }).join('');
            } else {
                cont.innerHTML = `<div style="text-align:center; padding:20px; color:#ff4444; font-size:0.85rem;">Error: ${res.message}</div>`;
            }
        });
    }

    let mainSearchTimer;
    function debounceMainSearch(val) {
        clearTimeout(mainSearchTimer);
        const drop = document.getElementById('mainSeqDrop');
        
        mainSearchTimer = setTimeout(() => {
            // Instantly update the list based on search filter
            curMainPage = 1;
            loadMainSequences();

            // Populate the proposal dropdown for quick jumping
            if(!val.trim()) {
                drop.classList.remove('open');
                return;
            }
            drop.innerHTML = '<div style="padding:8px 10px; font-size:0.75rem; color:var(--pl-text-dim);">Searching proposals...</div>';
            drop.classList.add('open');

            fetch('narseq_api.php?action=search_sequences&q=' + encodeURIComponent(val))
                .then(r=>r.json()).then(res => {
                    if(res.status !== 'success' || !res.data || !res.data.length) {
                        drop.innerHTML = '<div style="padding:8px 10px; font-size:0.75rem; color:var(--pl-text-dim);">No quick proposals</div>';
                        return;
                    }
                    drop.innerHTML = res.data.map(item => {
                        return `<a href="?id=${item.id}" class="ff-dropdown-item" style="text-decoration:none; display:flex;">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">${item.label}</span>
                            <span style="font-size:0.65rem; color:var(--pl-text-dim); margin-left:8px;">Jump to #${item.id}</span>
                        </a>`;
                    }).join('');
                });
        }, 350);
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', e => {
        const drop = document.getElementById('mainSeqDrop');
        if(drop && !e.target.closest('#mainSeqSearch') && !e.target.closest('#mainSeqDrop')) {
            drop.classList.remove('open');
        }
    });

    // --- MODALS ---
    function openCopyModal(id, currentName) {
        document.getElementById('copySeqId').value = id;
        document.getElementById('copySeqName').value = currentName + ' copy';
        document.getElementById('copyModal').classList.add('active');
        setTimeout(() => document.getElementById('copySeqName').focus(), 50);
    }
    function closeCopyModal() {
        document.getElementById('copyModal').classList.remove('active');
    }
    function submitCopy() {
        const id = document.getElementById('copySeqId').value;
        const name = document.getElementById('copySeqName').value.trim();
        if(!name) return Toast.show('Name required', 'warn');
        
        const fd = new URLSearchParams();
        fd.append('action', 'copy_sequence');
        fd.append('sequence_id', id);
        fd.append('new_name', name);

        fetch('narseq_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res => {
                if (res.success) window.location.href = '?id=' + res.new_sequence_id;
                else Toast.show(res.message || 'Copy failed', 'error');
            });
    }
    
   function openNewModal() {
        document.getElementById('newSeqName').value = '';
        document.getElementById('newModal').classList.add('active');
        setTimeout(() => document.getElementById('newSeqName').focus(), 50);
    }
    function closeNewModal() {
        document.getElementById('newModal').classList.remove('active');
    }
    function submitNew() {
        const name = document.getElementById('newSeqName').value.trim();
        if (!name) return Toast.show('Name required', 'warn');

        const fd = new URLSearchParams();
        fd.append('action', 'create_sequence');
        fd.append('name', name);

        fetch('narseq_api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) window.location.href = '?id=' + res.new_sequence_id;
                else Toast.show(res.message || 'Create failed', 'error');
            });
    }

    // --- EDIT & DELETE LOGIC ---
    function openEditModal(id, name, catId) {
        document.getElementById('editSeqId').value = id;
        document.getElementById('editSeqName').value = name;
        
        // Clone categories from the main filter select
        const editCatSelect = document.getElementById('editSeqCat');
        editCatSelect.innerHTML = '<option value="0">No Category</option>';
        const mainOpts = document.querySelectorAll('#mainSeqCat option');
        mainOpts.forEach(opt => {
            if(opt.value !== '') {
                const newOpt = document.createElement('option');
                newOpt.value = opt.value;
                newOpt.textContent = opt.textContent;
                editCatSelect.appendChild(newOpt);
            }
        });
        editCatSelect.value = catId || 0;

        document.getElementById('editModal').classList.add('active');
        setTimeout(() => document.getElementById('editSeqName').focus(), 50);
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    function submitEdit() {
        const id = document.getElementById('editSeqId').value;
        const name = document.getElementById('editSeqName').value.trim();
        const catId = document.getElementById('editSeqCat').value;

        if(!name) return Toast.show('Name required', 'warn');

        const fd = new URLSearchParams();
        fd.append('action', 'edit_sequence');
        fd.append('sequence_id', id);
        fd.append('name', name);
        fd.append('category_id', catId);

        fetch('narseq_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res => {
                if (res.success) {
                    Toast.show('Sequence updated', 'success');
                    closeEditModal();
                    loadMainSequences();
                } else {
                    Toast.show(res.message || 'Update failed', 'error');
                }
            });
    }

    function deleteSequence(id) {
        if(!confirm('Are you sure you want to delete this narrative sequence? This action cannot be undone.')) return;
        
        const fd = new URLSearchParams();
        fd.append('action', 'delete_sequence');
        fd.append('sequence_id', id);

        fetch('narseq_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res => {
                if (res.success) {
                    Toast.show('Sequence deleted', 'success');
                    // Reload current page. If page is now empty, it will naturally show "No sequences found"
                    loadMainSequences();
                } else {
                    Toast.show(res.message || 'Delete failed', 'error');
                }
            });
    }
    </script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Sequence - Narrative Splitter', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Sequence Editor View ──────────────────────────────────────────────────────
$seqStmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
$seqStmt->execute([$seqId]);
$seq = $seqStmt->fetch(PDO::FETCH_ASSOC);
if (!$seq) die("<div style='padding:40px;color:red;'>Sequence #$seqId not found.</div>");

$itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];

$pureSketchIds = [];
$selectedFrameIds = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0) $pureSketchIds[] = $sid;
    $selectedFrameIds[$idx] = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : null;
}
$pureSketchIds = array_values(array_unique($pureSketchIds));

$sketchesData = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtS = $pdo->prepare("SELECT id, name, description FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }
}

// --- Fetch ALL frames belonging to the relevant sketches ---
$framesBySketch = [];
if (!empty($pureSketchIds)) {
    $inClauseF = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtAllF = $pdo->prepare("
        SELECT id, filename, entity_id AS sketch_id FROM frames WHERE entity_type='sketches' AND entity_id IN ($inClauseF)
        UNION
        SELECT f.id, f.filename, f2s.to_id AS sketch_id FROM frames f JOIN frames_2_sketches f2s ON f2s.from_id = f.id WHERE f2s.to_id IN ($inClauseF)
        ORDER BY id DESC
    ");
    $stmtAllF->execute(array_merge($pureSketchIds, $pureSketchIds));
    foreach ($stmtAllF->fetchAll(PDO::FETCH_ASSOC) as $fr) {
        $sid = (int)$fr['sketch_id'];
        $framesBySketch[$sid][] = [
            'id' => (int)$fr['id'],
            'filename' => resolveFrameThumb($fr, (int)$fr['id'])
        ];
    }
}

// Quick map for initial rendering
$selectedFrameMap = [];
$activeFrameIds   = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $selectedFrameMap[(int)$row['id']] = resolveFrameThumb($row, (int)$row['id']);
    }
}

$sketchIdsNeedingLatestFrame = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0 && empty($selectedFrameIds[$idx])) $sketchIdsNeedingLatestFrame[] = $sid;
}
$sketchIdsNeedingLatestFrame = array_values(array_unique($sketchIdsNeedingLatestFrame));

$latestFrameBySketch = [];
if (!empty($sketchIdsNeedingLatestFrame)) {
    $inClauseFb = implode(',', array_fill(0, count($sketchIdsNeedingLatestFrame), '?'));
    $stmtFb = $pdo->prepare("SELECT f.*, f.entity_id AS _sketch_id FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id IN ($inClauseFb) ORDER BY f.id DESC");
    $stmtFb->execute($sketchIdsNeedingLatestFrame);
    foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sid])) {
            $latestFrameBySketch[$sid] = [
                'filename' => resolveFrameThumb($row, (int)$row['id']),
                'id'       => (int)$row['id']
            ];
        }
    }
}

$pageTitle = "Split Sequence: " . htmlspecialchars($seq['name']);
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-amber:       #f5a623;
    --pl-teal:        #3ab5c8;
}
[data-theme="light"] {
    --pl-bg:          #f4f6fa;
    --pl-surface:     #ffffff;
    --pl-card:        #ffffff;
    --pl-border:      #d0d8e8;
    --pl-text:        #1a2233;
    --pl-text-dim:    #7a8aaa;
    --pl-amber:       #c8880a;
    --pl-teal:        #1a8090;
}

body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', system-ui, sans-serif; margin: 0; padding: 0; }

.pl-nav { display:flex; align-items:center; gap:10px; padding:10px 16px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); }
[data-theme="light"] .pl-nav { background:rgba(244,246,250,.92); }
.pl-nav-btn { font-family:'Space Mono',monospace; font-size:.7rem; padding:6px 12px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); text-decoration:none; transition:all .2s; background:var(--pl-surface); cursor:pointer; }
.pl-nav-btn:hover { color:var(--pl-teal); border-color:var(--pl-teal); }

.workspace { max-width:900px; margin:0 auto; padding:30px 15px 100px; }

/* Item Wrapper */
.seq-item-wrap { position: relative; transition: opacity 0.2s; }
.seq-item-wrap:last-child .inline-add-row { display: none !important; }

/* Drag Hover States */
.seq-item-wrap.drag-over-top .scene-block { border-top: 2px solid var(--pl-teal); padding-top: 14px; }
.seq-item-wrap.drag-over-bottom .scene-block { border-bottom: 2px solid var(--pl-teal); padding-bottom: 14px; }
.drag-over-container { outline: 2px dashed var(--pl-teal); outline-offset: 10px; border-radius: 8px; }
.seq-item-wrap.dragging { opacity: 0.4; z-index: 999; }

.scene-block { position:relative; background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:16px; padding-left:36px; margin-bottom:10px; box-shadow:0 4px 15px rgba(0,0,0,.2); transition: border 0.15s, padding 0.15s; }
[data-theme="light"] .scene-block { box-shadow:0 2px 8px rgba(0,0,0,.05); }

/* Drag Handle */
.drag-handle { position:absolute; left:0; top:0; bottom:0; width:32px; display:flex; align-items:center; justify-content:center; color:var(--pl-text-dim); font-size:1rem; cursor:grab; opacity:0.3; transition:opacity .2s; touch-action:none; user-select:none; border-right:1px solid var(--pl-border); }
.scene-block:hover .drag-handle { opacity:1; }
.drag-handle:active { cursor:grabbing; color:var(--pl-teal); background:rgba(58,181,200,0.05); }

/* Remove Button & Mass Checkbox */
.item-remove-btn {
    position: absolute; top: 8px; right: 8px; width: 30px; height: 30px;
    border-radius: 4px; background: rgba(0,0,0,0.1); color: var(--pl-text-dim);
    border: 1px solid rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s; font-size: 1.3rem; z-index: 10; 
    opacity: 0.85; 
}
[data-theme="light"] .item-remove-btn { background: rgba(0,0,0,0.03); border-color: rgba(0,0,0,0.05); }
.item-remove-btn:hover, .item-remove-btn:active { 
    background: rgba(255, 68, 68, 0.1); color: #ff4444 !important; 
    border-color: rgba(255, 68, 68, 0.3); opacity: 1; 
}
.item-mass-checkbox {
    display: none; position: absolute; top: 8px; right: 8px; width: 30px; height: 30px;
    z-index: 10; align-items: center; justify-content: center;
    background: var(--pl-card); border-radius: 4px; border: 1px solid var(--pl-border);
}
.item-mass-checkbox input { width: 16px; height: 16px; cursor: pointer; accent-color: var(--pl-teal); margin: 0; }
.mass-mode-active .item-remove-btn { display: none !important; }
.mass-mode-active .item-mass-checkbox { display: flex !important; }

.sketch-flex { display: flex; gap: 15px; align-items: center; }

/* PhotoSwipe Thumbnails */
.sketch-thumb { position: relative; width: 100px; height: 100px; flex-shrink: 0; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--pl-border); cursor: pointer; transition: filter 0.15s; }
.sketch-thumb:hover { filter: brightness(1.2); }
.sketch-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sketch-thumb a { display: block; width: 100%; height: 100%; }

.frame-cycle-btn { background: var(--pl-bg); border: 1px solid var(--pl-border); color: var(--pl-text-dim); border-radius: 4px; width: 46px; height: 26px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; font-size: 0.75rem; }
.frame-cycle-btn:hover { border-color: var(--pl-teal); color: var(--pl-teal); background: rgba(58,181,200,0.1); }

.sketch-info { flex: 1; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
.sketch-id { font-family: 'Space Mono', monospace; font-size: 0.65rem; color: var(--pl-teal); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 5px; }
.sketch-title { font-size: 1.05rem; font-weight: bold; margin: 0 0 5px 0; color: var(--pl-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; transition: color 0.15s; }
.sketch-title:hover { color: var(--pl-amber); }
.sketch-desc { font-size: 0.8rem; color: var(--pl-text-dim); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* Inline Split Divider */
.inline-add-row { display:flex; align-items:center; gap:8px; margin:8px 0; opacity:0.4; transition:opacity .2s; cursor:pointer; }
.inline-add-row:hover { opacity:1; }
.inline-add-row .add-divider { flex:1; height:1px; background:var(--pl-border); }
.inline-add-btn { display:flex; align-items:center; gap:6px; padding:4px 12px; border-radius:12px; border:1px dashed var(--pl-border); background:var(--pl-bg); color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.65rem; text-transform:uppercase; cursor:pointer; transition:all .2s; }
.inline-add-row:hover .inline-add-btn { border-color:var(--pl-amber); color:var(--pl-amber); background:rgba(245,166,35,.05); }

/* Modals */
.su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.su-modal-backdrop.active { display:flex; }
.su-modal-box { width:100%; max-width:440px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; padding:20px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
.su-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
.su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-amber); text-transform:uppercase; letter-spacing:1px; }
.su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
.su-input { width:100%; box-sizing:border-box; background:var(--pl-card); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s; }
.su-input:focus { border-color:var(--pl-teal); }
.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-card); color:var(--pl-text-dim); }
.pl-btn-secondary:hover { border-color:var(--pl-amber); color:var(--pl-amber); }
.pl-btn-primary { border-color:var(--pl-amber); background:var(--pl-amber); color:#000; font-weight:bold; }
.pl-btn-primary:hover { filter:brightness(1.1); }
.pl-btn-teal { border-color:var(--pl-teal); background:var(--pl-teal); color:#000; font-weight:bold; }
.pl-btn-teal:hover { filter:brightness(1.1); }

/* FORGE MODAL (Enhanimaticism Style) */
.compose-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.75);
    z-index: 300000; display: none; align-items: flex-end; justify-content: center;
}
.compose-modal-backdrop.active { display: flex; }
.compose-modal {
    width: 100%; max-width: 700px;
    background: var(--pl-surface); border: 1px solid var(--pl-border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
    height: 55vh; max-height: 80vh; resize: vertical; overflow: hidden; 
    display: flex; flex-direction: column;
}
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; flex-shrink:0; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--pl-border); border-radius: 2px; }
.cm-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--pl-border); flex-shrink: 0;
}
.cm-title { font-size: 0.9rem; font-weight: 700; color: var(--pl-teal); text-transform: uppercase; letter-spacing: 1px; font-family:'Space Mono', monospace; }
.cm-close-btn { background: transparent; border: 1px solid var(--pl-border); color: var(--pl-text-dim); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--pl-text); border-color: var(--pl-text); }

.forge-filters-bar { padding: 6px 12px; display: flex; gap: 6px; align-items: center; border-bottom: 1px solid var(--pl-border); overflow-x: auto; flex-shrink:0; min-height:34px; scrollbar-width:none; }
.forge-filters-bar::-webkit-scrollbar { display:none; }
.forge-pill { background: rgba(58,181,200,0.15); border: 1px solid rgba(58,181,200,0.3); color: var(--pl-teal); padding: 3px 8px; border-radius: 20px; font-size: 0.65rem; display: flex; align-items: center; gap: 6px; font-weight: bold; white-space:nowrap; }
.forge-pill-close { cursor: pointer; font-size: 0.8rem; opacity: 0.7; }
.forge-pill-close:hover { opacity: 1; color: #ef4444; }

.cm-body { display: flex; flex: 1; min-height: 0; padding: 0; }
.forge-sidebar {
    width: 120px; border-right: 1px solid var(--pl-border); padding: 8px 6px;
    display: flex; flex-direction: column; gap: 4px; overflow-y: auto; flex-shrink: 0;
}
.forge-sidebar-btn {
    width: 100%; padding: 8px; background: transparent; border: none; color: var(--pl-text-dim);
    text-align: left; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 0.75rem;
    font-family: 'Syne', sans-serif; transition: all 0.15s;
}
.forge-sidebar-btn:hover { background: rgba(255,255,255,0.05); color: var(--pl-text); }
.forge-sidebar-btn.active { background: rgba(58,181,200,0.15); color: var(--pl-teal); }

.forge-content { flex: 1; padding: 12px; overflow-y: auto; position: relative; }
.forge-tab-pane { display: none; flex-direction: column; gap: 8px; }
.forge-tab-pane.active { display: flex; }

.ff-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--pl-text-dim); letter-spacing: 1px; }

/* Enhanced scrolling for modal dropdown lists */
.ff-dropdown { border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card); max-height: 140px; overflow-y: auto; display: none; position: absolute; z-index: 100; width: calc(100% - 40px); left: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); overscroll-behavior: contain; touch-action: pan-y; }
.ff-dropdown.open { display: block; }
.ff-dropdown-item { padding: 8px 10px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--pl-text); display: flex; justify-content: space-between; align-items:center; }
.ff-dropdown-item:hover { background: rgba(58,181,200,0.1); color: var(--pl-teal); }

/* Grid for results */
.ff-result-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.ff-result-card {
    border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card);
    overflow: hidden; position: relative; aspect-ratio: 1; transition: border-color 0.15s;
}

.ff-result-card:hover { border-color: var(--pl-teal); }
.ff-pswp-item { display: block; width: 100%; height: 100%; }
.ff-result-card img { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; }
.ff-result-label {
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7);
    color: #fff; font-size: 0.6rem; padding: 3px 4px; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; pointer-events: none;
}
.ff-drag-indicator {
    position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.6); color: #fff;
    border-radius: 4px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; z-index: 10; cursor: grab; opacity: 0.85; transition: opacity 0.15s;
    touch-action: none; /* Prevents mobile scroll while dragging */
}
.ff-result-card:hover .ff-drag-indicator { opacity: 1; }
.ff-drag-indicator:active { background: var(--pl-teal); color: #000; cursor: grabbing; }
.forge-result-empty { grid-column: 1 / -1; text-align: center; padding: 20px 0; color: var(--pl-text-dim); font-size: 0.8rem; }

/* Ensure PhotoSwipe Lightbox pops over the 300,000 z-index modal */
.pswp { z-index: 400000 !important; }

    /* Empty sequence drop zone */
    #emptyDropZone {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        min-height: 200px;
        width: 100%;
        box-sizing: border-box;
        border: 2px dashed rgba(58, 181, 200, 0.35);
        border-radius: 6px;
        background: var(--pl-card);
        color: var(--pl-text-dim);
        font-family: 'Space Mono', monospace;
        font-size: 0.78rem;
        text-align: center;
        padding: 40px 20px;
        margin: 10px 0 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,.2);
        transition: border-color 0.15s, background 0.15s, color 0.15s, padding 0.15s;
        pointer-events: auto;
        -webkit-user-select: none;
        user-select: none;
    }
    #emptyDropZone .dz-icon {
        font-size: 2.4rem;
        line-height: 1;
        margin-bottom: 4px;
    }
    #emptyDropZone .dz-title {
        font-size: 0.85rem;
        font-weight: bold;
        color: var(--pl-teal);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    #emptyDropZone .dz-hint {
        font-size: 0.7rem;
        opacity: 0.6;
        line-height: 1.5;
    }
    /* Matches .seq-item-wrap.drag-over-top / drag-over-bottom border style */
    #emptyDropZone.drag-over {
        border-style: solid;
        border-color: var(--pl-teal);
        border-width: 2px;
        background: rgba(58, 181, 200, 0.07);
        padding-top: 44px;   /* mimics the padding-top bump on drag-over-top */
        color: var(--pl-teal);
    }
    #emptyDropZone.drag-over .dz-title {
        color: var(--pl-teal);
    }

</style>

<div class="pl-nav" style="padding-left: 70px;">
    <!-- Back Link (Visible in Default Mode) -->
    <a href="narseq.php" class="pl-nav-btn default-mode-el" style="margin-right:10px;">&#9664; Seq</a>

    <!-- Mass Action Buttons (Hidden by default) -->
    <button class="pl-nav-btn mass-action-btn" style="display:none; margin-right:5px;" onclick="massCheckAll()">All</button>
    <button class="pl-nav-btn mass-action-btn" style="display:none; color:#ff4444; border-color:rgba(255,68,68,0.3); margin-right:5px;" onclick="massDelete()">Del</button>
    <button class="pl-nav-btn mass-action-btn" style="display:none; margin-right:auto;" onclick="openMassCopyModal()">Copy</button>
    
    <!-- Mass Toggle -->
    <button class="pl-nav-btn" id="massToggleBtn" onclick="toggleMassMode()" style="margin-left:auto; margin-right:10px;">
        Mass
    </button>
    <!-- Add Button -->
    <button class="pl-nav-btn" onclick="openForgeModal()" style="margin-right:10px; background:var(--pl-teal); color:#000; border-color:var(--pl-teal); font-weight:bold;">
        <i class="bi bi-funnel"></i> Add
    </button>
    <!-- JSON Export -->
    <button class="pl-nav-btn" onclick="exportSequence(event)" title="Export JSON">
        <i class="bi bi-download"></i> JSON
    </button>
</div>

<div class="workspace">
    <div style="font-family:'Space Mono',monospace; font-size:0.75rem; color:var(--pl-text-dim); text-align:center; margin-bottom:30px;">
        Drag and drop items to reorder, use arrows to cycle frames, or click "SPLIT HERE" to divide this sequence.
    </div>

    <?php if (empty($itemIds)): ?>
        <div id="emptyDropZone">
            <div class="dz-icon">🎞</div>
            <div class="dz-title">Drop Frame Here</div>
            <div class="dz-hint">Open Add above,<br>find a frame and drag it onto this zone.</div>
        </div>
    <?php endif; ?>

    <div id="sequenceList" class="editor-pswp-gallery">
    <?php foreach ($itemIds as $idx => $item):
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid <= 0 || !isset($sketchesData[$sid])) continue;
        $sketchRow = $sketchesData[$sid];
        
        $activeFrameId = $selectedFrameIds[$idx] ?? null;
        $thumb = '';
        if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
            $thumb = $selectedFrameMap[$activeFrameId];
        } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
            $thumb         = $latestFrameBySketch[$sid]['filename'];
            $activeFrameId = $latestFrameBySketch[$sid]['id'];
        }
        
        // Use first frame available as fallback
        if (!$activeFrameId && !empty($framesBySketch[$sid])) {
            $activeFrameId = $framesBySketch[$sid][0]['id'];
            $thumb         = $framesBySketch[$sid][0]['filename'];
        }
    ?>
        <div class="seq-item-wrap" data-idx="<?= $idx ?>">
            <div class="scene-block">
                <div class="item-remove-btn" title="Remove from sequence" onclick="removeSequenceItem(this)"><i class="bi bi-x"></i></div>
                <div class="item-mass-checkbox" title="Select item"><input type="checkbox" class="mass-check" value="<?= $idx ?>"></div>
                <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                <div class="sketch-flex">
                    <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                        <div class="sketch-thumb" 
                             data-active-frame="<?= $activeFrameId ?>"
                             id="thumb-wrap-<?= $idx ?>">
                            <?php if ($thumb): ?>
                                <a href="<?= htmlspecialchars($thumb) ?>" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                    <img src="<?= htmlspecialchars($thumb) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                </a>
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--pl-text-dim); font-size:0.7rem;">No Image</div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($framesBySketch[$sid] ?? []) > 1): ?>
                        <div style="display:flex; gap:8px; margin-top:8px; width:100%; justify-content:space-between;">
                            <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, <?= $sid ?>, -1)" title="Previous Frame"><i class="bi bi-chevron-left"></i></button>
                            <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, <?= $sid ?>, 1)" title="Next Frame"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="sketch-info">
                        <div class="sketch-id">Item <span class="sketch-id-num"><?= sprintf('%02d', $idx + 1) ?></span> &bull; Sketch #<?= $sid ?></div>
                        <div class="sketch-title" onclick="openEntityModal('sketches', <?= $sid ?>, '<?= htmlspecialchars(addslashes($sketchRow['name'])) ?>')">
                            <?= htmlspecialchars($sketchRow['name']) ?>
                        </div>
                        <div class="sketch-desc"><?= htmlspecialchars($sketchRow['description'] ?? 'No description.') ?></div>
                    </div>
                </div>
            </div>
            
            <div class="inline-add-row" onclick="openSplitModal(this)">
                <div class="add-divider"></div>
                <button class="inline-add-btn"><i class="bi bi-scissors"></i> Split Here</button>
                <div class="add-divider"></div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- Split Modal -->
<div id="splitModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeSplitModal()">
    <div class="su-modal-box" style="max-width:500px;">
        <div class="su-modal-header">
            <div class="su-modal-title"><i class="bi bi-scissors"></i> Split Sequence</div>
            <button onclick="closeSplitModal()" class="su-modal-close">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <p style="font-size:0.85rem; color:var(--pl-text-dim); margin:0;">
                The original sequence will keep all items <strong>before</strong> the split point.<br>
                A new sequence will be created containing all items <strong>after</strong> the split point.
            </p>
            <input type="hidden" id="splitIndex">
            <div>
                <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">New Sequence Name (Part 2)</label>
                <input type="text" id="splitNewName" class="su-input">
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; gap:8px; margin-top:24px; flex-wrap:wrap;">
            <button onclick="closeSplitModal()" class="pl-btn pl-btn-secondary">Cancel</button>
            <div style="display:flex; gap:8px;">
                <button onclick="submitSplit('part1')" class="pl-btn pl-btn-primary">Save & Load Part 1</button>
                <button onclick="submitSplit('part2')" class="pl-btn pl-btn-teal">Save & Load Part 2</button>
            </div>
        </div>
    </div>
</div>

<!-- Mass Copy Modal -->
<div id="massCopyModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeMassCopyModal()">
    <div class="su-modal-box" style="max-width:500px;">
        <div class="su-modal-header">
            <div class="su-modal-title"><i class="bi bi-files"></i> Mass Copy / Move</div>
            <button onclick="closeMassCopyModal()" class="su-modal-close">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px; position:relative;">
            <input type="hidden" id="mcTargetSeqId">
            <div>
                <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Target Narrative Sequence</label>
                <input type="text" id="mcSearchSeq" class="su-input" placeholder="Type to search sequences..." oninput="mcDebounceSearch(this.value)" autocomplete="off">
                <div class="ff-dropdown" id="mcDropSeq" style="top: 55px;"></div>
            </div>
            <div>
                <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Insert after item position (0 = top)</label>
                <input type="number" id="mcOffset" class="su-input" value="0" min="0">
            </div>
            <div style="display:flex; align-items:center; gap:8px; margin-top:8px;">
                <input type="checkbox" id="mcIsMove" style="width:16px; height:16px; cursor:pointer;">
                <label for="mcIsMove" style="font-size:0.85rem; color:var(--pl-text); cursor:pointer;">Move items instead of copy</label>
            </div>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:24px; flex-wrap:wrap;">
            <button onclick="closeMassCopyModal()" class="pl-btn pl-btn-secondary">Cancel</button>
            <button onclick="submitMassCopy()" class="pl-btn pl-btn-primary">Apply Action</button>
        </div>
    </div>
</div>


<!-- FORGE MODAL (Enhanimaticism Style) -->
<div class="compose-modal-backdrop" id="ffBackdrop" onmousedown="if(event.target===this)closeForgeModal()">
    <div class="compose-modal" id="ffModal">
        <div class="cm-handle" onclick="closeForgeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-funnel"></i> Filter Forge</div>
            <button class="cm-close-btn" onclick="closeForgeModal()">✕</button>
        </div>
        
        <!-- Active Filters Bar -->
        <div class="forge-filters-bar" id="ffActiveFilters">
            <div style="font-size:0.7rem; color:var(--pl-text-dim); font-style:italic;">No active filters.</div>
        </div>

        <div class="cm-body">
            <!-- Sidebar Tabs -->
            <div class="forge-sidebar">
                <button class="forge-sidebar-btn active" data-tab="fuzz" onclick="switchForgeTab('fuzz')">🧩 Fuzz</button>
                <button class="forge-sidebar-btn" data-tab="doc" onclick="switchForgeTab('doc')">📜 Doc</button>
                <button class="forge-sidebar-btn" data-tab="kg" onclick="switchForgeTab('kg')">🌳 KG</button>
                <button class="forge-sidebar-btn" data-tab="seq" onclick="switchForgeTab('seq')">🎬 Seq</button>
                <button class="forge-sidebar-btn" data-tab="storyboard" onclick="switchForgeTab('storyboard')">🖼️ Board</button>
                <button class="forge-sidebar-btn" data-tab="map_run" onclick="switchForgeTab('map_run')">🗺️ Run</button>
                <button class="forge-sidebar-btn" data-tab="vector" onclick="switchForgeTab('vector')">🔍 Semantic</button>
                <button class="forge-sidebar-btn" data-tab="id" onclick="switchForgeTab('id')">🔢 ID/Text</button>
                <hr style="border-color:var(--pl-border); margin:4px 0;">
                <button class="forge-sidebar-btn" data-tab="results" onclick="switchForgeTab('results')" style="color:var(--pl-amber); font-weight:bold;">▶ Results</button>
            </div>

            <!-- Content Area -->
            <div class="forge-content">
                <!-- FUZZ -->
                <div class="forge-tab-pane active" id="pane-fuzz">
                    <label class="ff-label">Fuzz Concept</label>
                    <input type="text" id="ffSearch-fuzz" class="su-input" placeholder="Search fuzz..." oninput="ffDebounceSearch('fuzz', this.value)">
                    <div class="ff-dropdown" id="ffDrop-fuzz"></div>
                </div>
                <!-- DOC -->
                <div class="forge-tab-pane" id="pane-doc">
                    <label class="ff-label">Lore Document</label>
                    <input type="text" id="ffSearch-doc" class="su-input" placeholder="Search docs..." oninput="ffDebounceSearch('doc', this.value)">
                    <div class="ff-dropdown" id="ffDrop-doc"></div>
                </div>
                <!-- KG -->
                <div class="forge-tab-pane" id="pane-kg">
                    <label class="ff-label">KG Node</label>
                    <input type="text" id="ffSearch-kg" class="su-input" placeholder="Search KG nodes..." oninput="ffDebounceSearch('kg', this.value)">
                    <div class="ff-dropdown" id="ffDrop-kg"></div>
                </div>
                <!-- SEQ -->
                <div class="forge-tab-pane" id="pane-seq">
                    <label class="ff-label">Narrative Sequence</label>
                    <input type="text" id="ffSearch-seq" class="su-input" placeholder="Search sequences..." onfocus="ffDebounceSearch('seq', this.value)" oninput="ffDebounceSearch('seq', this.value)">
                    <div class="ff-dropdown" id="ffDrop-seq"></div>
                </div>
                <!-- STORYBOARD -->
                <div class="forge-tab-pane" id="pane-storyboard">
                    <label class="ff-label">Storyboard</label>
                    <input type="text" id="ffSearch-storyboard" class="su-input" placeholder="Search storyboards..." onfocus="ffDebounceSearch('storyboard', this.value)" oninput="ffDebounceSearch('storyboard', this.value)">
                    <div class="ff-dropdown" id="ffDrop-storyboard"></div>
                </div>
                <!-- MAP RUN -->
                <div class="forge-tab-pane" id="pane-map_run">
                    <label class="ff-label">Map Run</label>
                    <input type="text" id="ffSearch-map_run" class="su-input" placeholder="Search map runs..." onfocus="ffDebounceSearch('map_run', this.value)" oninput="ffDebounceSearch('map_run', this.value)">
                    <div class="ff-dropdown" id="ffDrop-map_run"></div>
                </div>
                <!-- VECTOR -->
                <div class="forge-tab-pane" id="pane-vector">
                    <label class="ff-label">Semantic / Vector Search</label>
                    <textarea id="ffSearch-vector" class="su-input" style="height:80px; resize:none; margin-bottom:8px;" placeholder="Describe visually..."></textarea>
                    <button class="pl-btn pl-btn-teal" style="width:100%;" onclick="ffApplyVector()">Apply Semantic</button>
                </div>
                <!-- TEXT/ID -->
                <div class="forge-tab-pane" id="pane-id">
                    <label class="ff-label">Text Search</label>
                    <input type="text" id="ffSearch-text" class="su-input" placeholder="Name or description...">
                    
                    <label class="ff-label" style="margin-top:12px;">Sketch ID</label>
                    <input type="number" id="ffSearch-sketchId" class="su-input" placeholder="e.g. 1042">
                    
                    <label class="ff-label" style="margin-top:12px;">Frame ID</label>
                    <input type="number" id="ffSearch-frameId" class="su-input" placeholder="e.g. 5503">
                    
                    <button class="pl-btn pl-btn-teal" style="margin-top:12px; width:100%;" onclick="ffApplyTextId()">Apply Text/ID</button>
                </div>
                <!-- RESULTS (3x3 Grid) -->
                <div class="forge-tab-pane" id="pane-results">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:0.75rem; color:var(--pl-text-dim);" id="ffResultMeta">Results will appear here.</span>
                        <button class="pl-btn pl-btn-secondary" style="padding:4px 8px; font-size:0.65rem;" onclick="runForgeSearch(ffCurrentPage)">↻ Refresh</button>
                    </div>
                    
                    <!-- Fixed 3x3 Grid Layout -->
                    <div class="ff-result-grid" id="ffResultGrid"></div>
                    
                    <!-- Pagination directly mapped to the 3x3 layout (9 per page) -->
                    <div id="ffPagination" style="display:none; justify-content:space-between; align-items:center; margin-top:12px;">
                        <button class="pl-btn pl-btn-secondary" id="ffPrevBtn" onclick="runForgeSearch(ffCurrentPage - 1)">« Prev</button>
                        <span style="font-size:0.75rem; color:var(--pl-text-dim);" id="ffPageLabel">Page 1</span>
                        <button class="pl-btn pl-btn-secondary" id="ffNextBtn" onclick="runForgeSearch(ffCurrentPage + 1)">Next »</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Entity Details Modal (Iframe) -->
<div class="su-modal-backdrop" id="entity-modal-backdrop" onmousedown="if(event.target===this)closeEntityModal()">
    <div class="su-modal-box" style="max-width:700px; height:85vh; padding:0; display:flex; flex-direction:column; overflow:hidden;">
        <div class="su-modal-header" style="padding:10px 14px; border-bottom:1px solid var(--pl-border); margin:0; flex-shrink:0;">
            <span class="su-modal-title" id="entityModalTitle" style="color:var(--pl-teal);">Entity Details</span>
            <button class="su-modal-close" onclick="closeEntityModal()">✕</button>
        </div>
        <iframe id="entity-iframe" src="about:blank" style="flex:1; border:none; width:100%; background:var(--pl-card);"></iframe>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>

<!-- PhotoSwipe Lightbox Module -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    
    // Main Sequence Lightbox
    window.initLightbox = () => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.editor-pswp-gallery',
            children: '.editor-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    };

    // Dedicated Filter Forge Grid Lightbox
    window.forgeLightbox = null;
    window.initForgeLightbox = () => {
        if (window.forgeLightbox) {
            window.forgeLightbox.destroy(); // Tear down old event listeners safely
        }
        window.forgeLightbox = new PhotoSwipeLightbox({
            gallery: '#ffResultGrid',
            children: 'a.ff-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        window.forgeLightbox.init();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (window.initLightbox) window.initLightbox();
    });
</script>

<script>
const originalName = <?= json_encode($seq['name']) ?>;
const SEQ_ID = <?= $seqId ?>;
const frameRegistry = <?= json_encode($framesBySketch, JSON_UNESCAPED_UNICODE) ?>;

// ── Export Sequence ──────────────────────────────────────────────────────────
function exportSequence(e) {
    const btn = e.currentTarget;
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Exporting...';
    btn.disabled = true;

    const fd = new URLSearchParams();
    fd.append('action', 'export_sequence');
    fd.append('sequence_id', SEQ_ID);

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const dataStr = JSON.stringify(res.export, null, 2);
                const blob = new Blob([dataStr], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `sequence_${SEQ_ID}_export.json`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                if (window.Toast) Toast.show('Sequence exported.', 'success');
            } else {
                if (window.Toast) Toast.show(res.message || 'Export failed', 'error');
                else alert(res.message || 'Export failed');
            }
        })
        .catch(err => {
            if (window.Toast) Toast.show('Network error', 'error');
            else alert('Network error');
        })
        .finally(() => {
            btn.innerHTML = origHTML;
            btn.disabled = false;
        });
}

// ── Frame Cycling Logic ──────────────────────────────────────────────────────
function cycleSeqFrame(btnEl, sketchId, direction) {
    const wrapEl = btnEl.closest('.seq-item-wrap');
    if (!wrapEl) return;
    const currentIdx = wrapEl.dataset.idx; // This dynamically updates during reorder
    
    const frames = frameRegistry[sketchId];
    if (!frames || frames.length < 2) return;
    
    const thumbWrap = wrapEl.querySelector('.sketch-thumb');
    const link = thumbWrap.querySelector('a.editor-pswp-item');
    const img = thumbWrap.querySelector('img');
    if (!img || !link) return;
    
    let currentFid = parseInt(thumbWrap.dataset.activeFrame) || frames[0].id;
    let fIndex = frames.findIndex(f => f.id === currentFid);
    if (fIndex === -1) fIndex = 0;
    
    let newIndex = fIndex + direction;
    if (newIndex < 0) newIndex = frames.length - 1;
    if (newIndex >= frames.length) newIndex = 0;
    
    const newFrame = frames[newIndex];
    
    // Update UI locally (link href targets lightbox, img src targets thumbnail)
    link.href = newFrame.filename;
    img.src = newFrame.filename;
    thumbWrap.dataset.activeFrame = newFrame.id;
    
    // Clear dimensions so PhotoSwipe recalculates them smoothly on next load
    delete link.dataset.pswpWidth;
    delete link.dataset.pswpHeight;
    
    // Auto-save via API so reload persists
    const fd = new URLSearchParams();
    fd.append('action', 'update_item_frame');
    fd.append('sequence_id', SEQ_ID);
    fd.append('item_index', currentIdx);
    fd.append('frame_id', newFrame.id);
    
    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.Toast) Toast.show('Frame saved.', 'info');
            } else {
                if (window.Toast) Toast.show(res.message || 'Frame save failed', 'error');
            }
        }).catch(e => {
            if (window.Toast) Toast.show('Network error updating frame', 'error');
        });
}

// ── Drag & Drop Sequence Items ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('sequenceList');
    if (!container) return;

    // ── Empty drop zone wiring ──────────────────────────────
    const emptyZone = document.getElementById('emptyDropZone');

    function refreshEmptyZone() {
        if (!emptyZone) return;
        const hasItems = container.querySelectorAll('.seq-item-wrap').length > 0;
        emptyZone.style.display = hasItems ? 'none' : 'flex';
    }

    // Keep in sync after every insert / remove
    const _origInsert = window.insertForgeItemToSequence;
    // (refreshEmptyZone is also called inside renderInsertedItem — see below)

    if (emptyZone) {
        // Pointer drag — highlight when forge clone is hovering over the zone
        emptyZone.addEventListener('pointerenter', () => {
            emptyZone.classList.add('drag-over');
        });
        emptyZone.addEventListener('pointerleave', () => {
            emptyZone.classList.remove('drag-over');
        });
    }

    // ── Patch renderInsertedItem to hide the zone after first drop ──
    // Find the existing renderInsertedItem function and add ONE line at its end.
    // The cleanest way without rewriting it: wrap it.
    const _origRender = window.renderInsertedItem;
    window.renderInsertedItem = function(res, insertIndex) {
        _origRender(res, insertIndex);
        refreshEmptyZone();
    };

    // Also call refreshEmptyZone once on load to handle sequences that start empty
    refreshEmptyZone();

    // ── END empty drop zone wiring ──────────────────────────────────

    let dragSrc = null;

    container.addEventListener('dragstart', e => {
        const wrap = e.target.closest('.seq-item-wrap');
        if (!wrap) return;
        dragSrc = wrap;
        wrap.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragend', e => {
        if (dragSrc) dragSrc.classList.remove('dragging');
        container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
        dragSrc = null;
        persistSortOrder();
    });

    container.addEventListener('dragover', e => {
        e.preventDefault();
        if (dragSrc) e.dataTransfer.dropEffect = 'move';
        
        const wrap = e.target.closest('.seq-item-wrap');
        if (!wrap || (dragSrc && wrap === dragSrc)) return;
        
        container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
        const rect = wrap.getBoundingClientRect();
        wrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
    });

    container.addEventListener('drop', e => {
        e.preventDefault();
        if (!dragSrc) return;
        const wrap = e.target.closest('.seq-item-wrap');
        if (!wrap || wrap === dragSrc) return;
        const rect = wrap.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) {
            container.insertBefore(dragSrc, wrap);
        } else {
            container.insertBefore(dragSrc, wrap.nextSibling);
        }
    });

    // Touch/Pointer Fallback for mobile devices
    let pointerDragSrc = null, pointerClone = null, pointerOffsetY = 0;
    let pointerForgeDragSrc = null, pointerForgeClone = null, forgeOffsetX = 0, forgeOffsetY = 0;

    // 1. Existing Sequence Reorder Logic
    container.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;
        const wrap = handle.closest('.seq-item-wrap');
        if (!wrap) return;
        pointerDragSrc = wrap;
        const rect = wrap.getBoundingClientRect();
        pointerOffsetY = e.clientY - rect.top;
        pointerClone = wrap.cloneNode(true);
        pointerClone.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;opacity:0.8;pointer-events:none;z-index:9999;background:transparent;`;
        document.body.appendChild(pointerClone);
        wrap.classList.add('dragging');
        e.preventDefault(); 
    }, { passive: false });

    // 2. New Filter Forge Drag Logic
    document.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.ff-drag-indicator');
        if (!handle) return;
        const card = handle.closest('.ff-result-card');
        if (!card) return;

        pointerForgeDragSrc = card;
        const rect = card.getBoundingClientRect();
        forgeOffsetX = e.clientX - rect.left;
        forgeOffsetY = e.clientY - rect.top;

        pointerForgeClone = card.cloneNode(true);
        pointerForgeClone.style.cssText = `
            position: fixed; left: ${rect.left}px; top: ${rect.top}px; 
            width: ${rect.width}px; height: ${rect.height}px;
            opacity: 0.95; pointer-events: none; z-index: 999999; 
            border: 2px solid var(--pl-teal); border-radius: 4px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
        `;
        document.body.appendChild(pointerForgeClone);
        
        // Immediately close the modal so we can drop into the main UI underneath
        closeForgeModal();

        // Lock pointer to this touch to prevent scrolling
        handle.setPointerCapture(e.pointerId);
        e.preventDefault();
    }, { passive: false });

    document.addEventListener('pointermove', e => {
        // Prevent scroll on mobile while dragging anything
        if (pointerClone || pointerForgeClone) e.preventDefault();

        // Moving sequence items (reorder)
        if (pointerClone && pointerDragSrc) {
            pointerClone.style.top = (e.clientY - pointerOffsetY) + 'px';
            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const wrap = target ? target.closest('.seq-item-wrap') : null;
            if (wrap && wrap !== pointerDragSrc && wrap.parentNode === container) {
                const rect = wrap.getBoundingClientRect();
                wrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
            }
        }

        // Moving Forge results
        if (pointerForgeClone && pointerForgeDragSrc) {
            pointerForgeClone.style.left = (e.clientX - forgeOffsetX) + 'px';
            pointerForgeClone.style.top  = (e.clientY - forgeOffsetY) + 'px';

            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
            container.classList.remove('drag-over-container');

            const emptyZone = document.getElementById('emptyDropZone');

            // Use getBoundingClientRect for reliable hit-testing on Android
            const seqRect = container.getBoundingClientRect();
            const inSeq   = e.clientX >= seqRect.left && e.clientX <= seqRect.right &&
                            e.clientY >= seqRect.top  && e.clientY <= seqRect.bottom;

            // Check empty drop zone hit
            if (emptyZone && emptyZone.style.display !== 'none') {
                const zRect = emptyZone.getBoundingClientRect();
                const onZone = e.clientX >= zRect.left && e.clientX <= zRect.right &&
                               e.clientY >= zRect.top  && e.clientY <= zRect.bottom;
                emptyZone.classList.toggle('drag-over', onZone);
            }

            // Check existing seq-item-wrap hit via getBoundingClientRect
            const wraps = Array.from(container.querySelectorAll('.seq-item-wrap'));
            let hitWrap = null;
            for (const w of wraps) {
                const r = w.getBoundingClientRect();
                if (e.clientX >= r.left && e.clientX <= r.right &&
                    e.clientY >= r.top  && e.clientY <= r.bottom) {
                    hitWrap = w; break;
                }
            }

            if (hitWrap) {
                const rect = hitWrap.getBoundingClientRect();
                hitWrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
            } else if (inSeq && wraps.length > 0) {
                container.classList.add('drag-over-container');
            }
        }
    }, { passive: false });

    document.addEventListener('pointerup', e => {

        // ── Drop Sequence Item (reorder) ─────────────────────────────
        if (pointerDragSrc) {
            if (pointerClone) { pointerClone.remove(); pointerClone = null; }
            pointerDragSrc.classList.remove('dragging');
            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));

            const target = document.elementFromPoint(e.clientX, e.clientY);
            const wrap = target ? target.closest('.seq-item-wrap') : null;
            if (wrap && wrap !== pointerDragSrc && wrap.parentNode === container) {
                const rect = wrap.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) {
                    container.insertBefore(pointerDragSrc, wrap);
                } else {
                    container.insertBefore(pointerDragSrc, wrap.nextSibling);
                }
            }
            persistSortOrder();
            pointerDragSrc = null;
        }

        // ── Drop Forge Item ──────────────────────────────────────────
        if (pointerForgeDragSrc) {
            const card = pointerForgeDragSrc;
            pointerForgeDragSrc = null;
            if (pointerForgeClone) { pointerForgeClone.remove(); pointerForgeClone = null; }

            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
            container.classList.remove('drag-over-container');

            const emptyZone = document.getElementById('emptyDropZone');
            if (emptyZone) emptyZone.classList.remove('drag-over');

            // --- Hit detection via getBoundingClientRect (works on Android Chrome) ---
            let insertIndex = -1;
            let didLand = false;

            // 1. Check existing seq-item-wrap elements
            const wraps = Array.from(container.querySelectorAll('.seq-item-wrap'));
            for (let i = 0; i < wraps.length; i++) {
                const r = wraps[i].getBoundingClientRect();
                if (e.clientX >= r.left && e.clientX <= r.right &&
                    e.clientY >= r.top  && e.clientY <= r.bottom) {
                    const rect = wraps[i].getBoundingClientRect();
                    insertIndex = (e.clientY < rect.top + rect.height / 2) ? i : i + 1;
                    didLand = true;
                    break;
                }
            }

            // 2. Check empty drop zone
            if (!didLand && emptyZone && emptyZone.style.display !== 'none') {
                const zRect = emptyZone.getBoundingClientRect();
                if (e.clientX >= zRect.left && e.clientX <= zRect.right &&
                    e.clientY >= zRect.top  && e.clientY <= zRect.bottom) {
                    insertIndex = -1; // append
                    didLand = true;
                }
            }

            // 3. Fallback: anywhere inside the sequence container counts
            if (!didLand) {
                const seqRect = container.getBoundingClientRect();
                if (e.clientX >= seqRect.left && e.clientX <= seqRect.right &&
                    e.clientY >= seqRect.top  && e.clientY <= seqRect.bottom) {
                    insertIndex = -1;
                    didLand = true;
                }
            }

            if (didLand) {
                insertForgeItemToSequence(card.dataset.sketchId, card.dataset.frameId, insertIndex);
            }
        }
    });

    function persistSortOrder() {
        const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
        const originalIndices = wraps.map(w => w.dataset.idx);

        const fd = new URLSearchParams();
        fd.append('action', 'reorder_sequence');
        fd.append('sequence_id', SEQ_ID);
        fd.append('order', originalIndices.join(','));

        fetch('narseq_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    // Update DOM metadata seamlessly
                    reindexSequenceVisuals();
                    if (window.Toast) Toast.show('Sequence reordered.', 'success');
                } else {
                    if (window.Toast) Toast.show(res.message || 'Reorder failed', 'error');
                }
            });
    }
});

function insertForgeItemToSequence(sketchId, frameId, insertIndex) {
    if (window.Toast) Toast.show('Inserting item...', 'info');
    
    const fd = new URLSearchParams();
    fd.append('action', 'insert_sequence_item');
    fd.append('sequence_id', SEQ_ID);
    fd.append('sketch_id', sketchId);
    fd.append('frame_id', frameId);
    fd.append('insert_index', insertIndex);

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                renderInsertedItem(res, res.insert_index);
                if (window.Toast) Toast.show('Item added to sequence.', 'success');
                
                const emptyMsg = document.getElementById('emptyListMsg');
                if (emptyMsg) emptyMsg.style.display = 'none';
            } else {
                if (window.Toast) Toast.show(res.message || 'Insert failed', 'error');
            }
        })
        .catch(err => {
            if (window.Toast) Toast.show('Network error inserting item', 'error');
        });
}

function renderInsertedItem(res, insertIndex) {
    const container = document.getElementById('sequenceList');
    frameRegistry[res.sketch.id] = res.all_frames;
    
    const wrap = document.createElement('div');
    wrap.className = 'seq-item-wrap';
    
    let cycleBtns = '';
    if (res.all_frames.length > 1) {
        cycleBtns = `
            <div style="display:flex; gap:8px; margin-top:8px; width:100%; justify-content:space-between;">
                <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, ${res.sketch.id}, -1)" title="Previous Frame"><i class="bi bi-chevron-left"></i></button>
                <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, ${res.sketch.id}, 1)" title="Next Frame"><i class="bi bi-chevron-right"></i></button>
            </div>
        `;
    }
    
    const safeName = res.sketch.name ? res.sketch.name.replace(/"/g, '&quot;').replace(/'/g, "\\'") : '';
    const safeDesc = res.sketch.description ? res.sketch.description.replace(/</g, '&lt;').replace(/>/g, '&gt;') : 'No description.';
    
    wrap.innerHTML = `
        <div class="scene-block">
            <div class="item-remove-btn" title="Remove from sequence" onclick="removeSequenceItem(this)"><i class="bi bi-x"></i></div>
            <div class="item-mass-checkbox" title="Select item"><input type="checkbox" class="mass-check" value=""></div>
            <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
            <div class="sketch-flex">
                <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                    <div class="sketch-thumb" data-active-frame="${res.frame.id}">
                        <a href="${res.frame.filename}" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                            <img src="${res.frame.filename}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                        </a>
                    </div>
                    ${cycleBtns}
                </div>
                <div class="sketch-info">
                    <div class="sketch-id">Item <span class="sketch-id-num">--</span> &bull; Sketch #${res.sketch.id}</div>
                    <div class="sketch-title" onclick="openEntityModal('sketches', ${res.sketch.id}, '${safeName}')">
                        ${res.sketch.name}
                    </div>
                    <div class="sketch-desc">${safeDesc}</div>
                </div>
            </div>
        </div>
        <div class="inline-add-row" onclick="openSplitModal(this)">
            <div class="add-divider"></div>
            <button class="inline-add-btn"><i class="bi bi-scissors"></i> Split Here</button>
            <div class="add-divider"></div>
        </div>
    `;
    
    const existingWraps = Array.from(container.querySelectorAll('.seq-item-wrap'));
    if (insertIndex === -1 || insertIndex >= existingWraps.length) {
        container.appendChild(wrap);
    } else {
        container.insertBefore(wrap, existingWraps[insertIndex]);
    }
    
    reindexSequenceVisuals();
    if (window.initLightbox) window.initLightbox();
    closeForgeModal();
}

function reindexSequenceVisuals() {
    const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
    wraps.forEach((w, i) => {
        w.dataset.idx = i;
        const numLabel = w.querySelector('.sketch-id-num');
        if (numLabel) numLabel.textContent = String(i + 1).padStart(2, '0');
        const massCb = w.querySelector('.mass-check');
        if (massCb) massCb.value = i;
    });
}

function removeSequenceItem(btn) {
    if (!confirm('Remove this item from the sequence?')) return;
    
    const wrap = btn.closest('.seq-item-wrap');
    if (!wrap) return;
    
    // Disable button to prevent double-clicks
    btn.style.pointerEvents = 'none';
    btn.innerHTML = '⋯';
    
    const idx = wrap.dataset.idx;
    
    const fd = new URLSearchParams();
    fd.append('action', 'remove_sequence_item');
    fd.append('sequence_id', SEQ_ID);
    fd.append('item_index', idx);
    
    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Smooth fade out
                wrap.style.transition = 'all 0.3s ease';
                wrap.style.opacity = '0';
                wrap.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    wrap.remove();
                    reindexSequenceVisuals();
                    // Restore empty state message if sequence is empty
                    if (document.querySelectorAll('.seq-item-wrap').length === 0) {
                        const emptyMsg = document.getElementById('emptyListMsg');
                        if (emptyMsg) emptyMsg.style.display = 'block';
                    }
                    if (window.Toast) Toast.show('Item removed.', 'success');
                }, 300);
            } else {
                btn.style.pointerEvents = '';
                btn.innerHTML = '<i class="bi bi-x"></i>';
                if (window.Toast) Toast.show(res.message || 'Remove failed', 'error');
            }
        })
        .catch(err => {
            btn.style.pointerEvents = '';
            btn.innerHTML = '<i class="bi bi-x"></i>';
            if (window.Toast) Toast.show('Network error', 'error');
        });
}

// ── Mass Edit Logic ──────────────────────────────────────────────────────────

let isMassMode = false;
let isMassAllChecked = false;

function toggleMassMode() {
    isMassMode = !isMassMode;
    const seqList = document.getElementById('sequenceList');
    if (seqList) seqList.classList.toggle('mass-mode-active', isMassMode);
    
    document.querySelectorAll('.mass-action-btn').forEach(b => b.style.display = isMassMode ? 'block' : 'none');
    document.querySelectorAll('.default-mode-el').forEach(b => b.style.display = isMassMode ? 'none' : '');
    
    const toggleBtn = document.getElementById('massToggleBtn');
    if (isMassMode) {
        toggleBtn.classList.add('pl-btn-primary');
    } else {
        toggleBtn.classList.remove('pl-btn-primary');
        document.querySelectorAll('.mass-check').forEach(cb => cb.checked = false);
        isMassAllChecked = false;
    }
}

function massCheckAll() {
    isMassAllChecked = !isMassAllChecked;
    document.querySelectorAll('.mass-check').forEach(cb => cb.checked = isMassAllChecked);
}

function massDelete() {
    const checked = Array.from(document.querySelectorAll('.mass-check:checked')).map(cb => cb.value);
    if (!checked.length) return Toast.show('No items selected', 'warn');
    if (!confirm(`Remove ${checked.length} items from sequence?`)) return;

    const fd = new URLSearchParams();
    fd.append('action', 'mass_remove_sequence_items');
    fd.append('sequence_id', SEQ_ID);
    fd.append('indices', JSON.stringify(checked));

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Toast.show(`Removed ${checked.length} items.`, 'success');
                setTimeout(() => window.location.reload(), 500);
            } else {
                Toast.show(res.message || 'Remove failed', 'error');
            }
        }).catch(err => Toast.show('Network error', 'error'));
}

let mcDebounceTimer;
function mcDebounceSearch(q) {
    clearTimeout(mcDebounceTimer);
    const dd = document.getElementById('mcDropSeq');
    if (!q) { dd.classList.remove('open'); return; }
    
    mcDebounceTimer = setTimeout(() => {
        dd.innerHTML = '<div style="padding:8px 10px; font-size:0.75rem; color:var(--pl-text-dim);">Searching...</div>';
        dd.classList.add('open');
        fetch('narseq_api.php?action=search_sequences&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data || !res.data.length) {
                    dd.innerHTML = '<div style="padding:8px 10px; font-size:0.75rem; color:var(--pl-text-dim);">No results</div>';
                    return;
                }
                dd.innerHTML = res.data.map(item => {
                    const safeName = item.label.replace(/"/g, '&quot;');
                    return `<div class="ff-dropdown-item" onclick="mcSelectSeq(${item.id}, '${safeName}')">
                        <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.label}</span>
                        <span style="font-size:0.65rem; color:var(--pl-text-dim); margin-left:8px;">#${item.id}</span>
                    </div>`;
                }).join('');
            });
    }, 300);
}

function mcSelectSeq(id, name) {
    document.getElementById('mcTargetSeqId').value = id;
    document.getElementById('mcSearchSeq').value = name;
    document.getElementById('mcDropSeq').classList.remove('open');
}

function openMassCopyModal() {
    const checked = document.querySelectorAll('.mass-check:checked');
    if (!checked.length) return Toast.show('No items selected', 'warn');
    document.getElementById('mcTargetSeqId').value = '';
    document.getElementById('mcSearchSeq').value = '';
    document.getElementById('mcOffset').value = '0';
    document.getElementById('mcIsMove').checked = false;
    document.getElementById('massCopyModal').classList.add('active');
    setTimeout(() => document.getElementById('mcSearchSeq').focus(), 50);
}

function closeMassCopyModal() {
    document.getElementById('massCopyModal').classList.remove('active');
}

function submitMassCopy() {
    const targetId = document.getElementById('mcTargetSeqId').value;
    if (!targetId) return Toast.show('Please select a target sequence', 'warn');
    
    const offset = document.getElementById('mcOffset').value;
    const isMove = document.getElementById('mcIsMove').checked;
    const checked = Array.from(document.querySelectorAll('.mass-check:checked')).map(cb => cb.value);

    const fd = new URLSearchParams();
    fd.append('action', 'mass_copy_sequence_items');
    fd.append('source_id', SEQ_ID);
    fd.append('target_id', targetId);
    fd.append('indices', JSON.stringify(checked));
    fd.append('offset', offset);
    fd.append('is_move', isMove ? '1' : '0');

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                Toast.show(isMove ? 'Items moved successfully.' : 'Items copied successfully.', 'success');
                if (isMove || targetId == SEQ_ID) {
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    closeMassCopyModal();
                    toggleMassMode(); // Turn off and clear selection
                }
            } else {
                Toast.show(res.message || 'Operation failed', 'error');
            }
        }).catch(err => Toast.show('Network error', 'error'));
}

// ── Forge Filter Enhanimaticism Logic ──────────────────────────────────────────
let ffState = {
    fuzz: [], doc: null, kg: null, seq: null, storyboard: null, map_run: null,
    vectorText: '', textSearch: [], sketchId: '', frameId: ''
};
let ffCurrentPage = 1;
let ffTotalPages = 1;
let ffDebounceTimer;

function openForgeModal() {
    document.getElementById('ffBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeForgeModal() {
    document.getElementById('ffBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function switchForgeTab(tabId) {
    document.querySelectorAll('.forge-sidebar-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.forge-sidebar-btn[data-tab="${tabId}"]`).classList.add('active');
    
    document.querySelectorAll('.forge-tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById(`pane-${tabId}`).classList.add('active');
    
    if (tabId === 'results') runForgeSearch(1);
}

function ffDebounceSearch(slot, q) {
    clearTimeout(ffDebounceTimer);
    ffDebounceTimer = setTimeout(() => {
        const dd = document.getElementById(`ffDrop-${slot}`);
        if (!q && !['storyboard', 'map_run', 'seq'].includes(slot)) { dd.classList.remove('open'); return; }
        
        dd.innerHTML = '<div class="forge-dropdown-loading" style="padding:8px 10px; font-size:0.75rem; color:var(--pl-text-dim);">Searching...</div>';
        dd.classList.add('open');
        
        let url = `narseq_filter_forge_api.php?action=list_filter_options&mode=${slot}&q=${encodeURIComponent(q || '')}&entity_type=sketches`;
        
        fetch(url)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data || !res.data.length) {
                    dd.innerHTML = '<div class="forge-dropdown-loading" style="padding: 8px 10px; font-size: 0.75rem; color: var(--pl-text-dim);">No results</div>';
                    return;
                }
                dd.innerHTML = res.data.map(item => {
                    const safe = JSON.stringify(item).replace(/"/g, '&quot;');
                    return `<div class="ff-dropdown-item" onclick="ffSelectItem('${slot}', ${safe})">
                        <span>${item.label}</span>
                        <span class="forge-dropdown-item-meta" style="font-size:0.65rem; color:var(--pl-text-dim); margin-left:8px;">${item.meta||''}</span>
                    </div>`;
                }).join('');
            })
            .catch(err => {
                console.error("Filter Search Error", err);
                dd.innerHTML = '<div class="forge-dropdown-loading" style="padding: 8px 10px; font-size: 0.75rem; color: #ef4444;">Error searching</div>';
            });
    }, 300);
}

function ffSelectItem(slot, item) {
    if (slot === 'fuzz') {
        if (!ffState.fuzz.some(f => f.id === item.id)) {
            ffState.fuzz.push(item);
        }
    } else {
        ffState[slot] = item;
    }
    
    document.getElementById(`ffSearch-${slot}`).value = '';
    document.getElementById(`ffDrop-${slot}`).classList.remove('open');
    renderActiveFilters();
    switchForgeTab('results'); // Auto-switch to results
}

function ffApplyVector() {
    ffState.vectorText = document.getElementById('ffSearch-vector').value.trim();
    renderActiveFilters();
    switchForgeTab('results');
}

function ffApplyTextId() {
    const txt = document.getElementById('ffSearch-text').value.trim();
    if (txt && !ffState.textSearch.includes(txt)) {
        ffState.textSearch.push(txt);
    }
    document.getElementById('ffSearch-text').value = '';
    
    ffState.sketchId = document.getElementById('ffSearch-sketchId').value.trim();
    ffState.frameId = document.getElementById('ffSearch-frameId').value.trim();
    
    renderActiveFilters();
    switchForgeTab('results');
}

function removeFfFilter(key, index = null) {
    if (key === 'fuzz' || key === 'textSearch') {
        if (index !== null) {
            ffState[key].splice(index, 1);
        } else {
            ffState[key] = [];
        }
    } else if (['vectorText', 'sketchId', 'frameId'].includes(key)) {
        ffState[key] = '';
        const el = document.getElementById(`ffSearch-${key.replace('vectorText','-vector').replace('sketchId','-sketchId').replace('frameId','-frameId')}`);
        if (el) el.value = '';
    } else {
        ffState[key] = null;
    }
    renderActiveFilters();
    runForgeSearch(1);
}

function renderActiveFilters() {
    const bar = document.getElementById('ffActiveFilters');
    bar.innerHTML = '';
    const labels = {
        fuzz: 'Fuzz', doc: 'Doc', kg: 'KG', seq: 'Seq', storyboard: 'Board', map_run: 'Run',
        vectorText: 'Semantic', textSearch: 'Text', sketchId: 'Sketch', frameId: 'Frame'
    };
    
    let hasAny = false;
    for (const [k, v] of Object.entries(ffState)) {
        if (Array.isArray(v)) {
            v.forEach((item, idx) => {
                hasAny = true;
                const display = (k === 'textSearch') ? item : item.label;
                bar.innerHTML += `<div class="forge-pill">${labels[k]}: ${display} <span class="forge-pill-close" onclick="removeFfFilter('${k}', ${idx})">×</span></div>`;
            });
        } else if (v && (typeof v === 'object' ? v.id : v.toString().length > 0)) {
            hasAny = true;
            const display = typeof v === 'object' ? v.label : v;
            bar.innerHTML += `<div class="forge-pill">${labels[k]}: ${display} <span class="forge-pill-close" onclick="removeFfFilter('${k}')">×</span></div>`;
        }
    }
    if (!hasAny) {
        bar.innerHTML = '<div style="font-size:0.7rem; color:var(--pl-text-dim); font-style:italic;">No active filters.</div>';
    }
}

function runForgeSearch(page) {
    ffCurrentPage = page;
    const p = new URLSearchParams();
    p.set('action', 'list_frames');
    p.set('entity_type', 'sketches');
    p.set('filter_mode', 'intersection');
    p.set('per_page', '9'); // Fixed exactly 9 for 3x3 layout to fit comfortably
    p.set('page', page);

    let hasFilter = false;

    if (ffState.fuzz.length > 0) { 
        ffState.fuzz.forEach(f => p.append('fuzz_id[]', f.id)); 
        hasFilter = true; 
    }
    if (ffState.textSearch.length > 0) { 
        ffState.textSearch.forEach(t => p.append('search[]', t)); 
        hasFilter = true; 
    }
    if (ffState.doc) { p.set('doc_id', ffState.doc.id); hasFilter = true; }
    if (ffState.kg) { p.set('kg_node_id', ffState.kg.id); hasFilter = true; }
    if (ffState.seq) { p.set('seq_id', ffState.seq.id); hasFilter = true; }
    if (ffState.storyboard) { p.set('storyboard_id', ffState.storyboard.id); hasFilter = true; }
    if (ffState.map_run) { p.set('map_run_id', ffState.map_run.id); hasFilter = true; }
    if (ffState.vectorText) { p.set('vector_text', ffState.vectorText); hasFilter = true; }
    if (ffState.sketchId) { p.set('entity_id', ffState.sketchId); hasFilter = true; }
    if (ffState.frameId) { p.set('frame_id', ffState.frameId); hasFilter = true; }

    // If no specific filters are applied, request newest frames first (frames.id DESC)
    if (!hasFilter) {
        p.set('sort', 'newest');
        p.set('sort_by', 'id');
        p.set('sort_order', 'desc');
    }

    const grid = document.getElementById('ffResultGrid');
    
    grid.innerHTML = '<div class="forge-result-empty">Searching Forge...</div>';
    document.getElementById('ffPagination').style.display = 'none';
    document.getElementById('ffResultMeta').textContent = 'Searching...';

    const endpoint = 'narseq_filter_forge_api.php?';

    fetch(endpoint + p.toString())
        .then(async r => {
            if (!r.ok) throw new Error('HTTP status ' + r.status);
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("JSON Parse Error on Forge Search:", text);
                throw new Error("Invalid JSON returned by API");
            }
        })
        .then(res => {
            if (res.status !== 'success') {
                grid.innerHTML = `<div class="forge-result-empty">Error: ${res.message}</div>`;
                return;
            }
            
            ffTotalPages = res.meta.pages;
            document.getElementById('ffResultMeta').textContent = `Found ${res.meta.total} sketches.`;
            
            if (!res.data.length) {
                grid.innerHTML = '<div class="forge-result-empty">No results found.</div>';
                return;
            }
            
            grid.innerHTML = res.data.map(row => `
                <div class="ff-result-card" data-sketch-id="${row.entity_id}" data-frame-id="${row.frame_id}">
                    <div class="ff-drag-indicator" title="Drag to insert" onclick="event.preventDefault(); event.stopPropagation();"><i class="bi bi-arrows-move"></i></div>
                    <a href="${row.filename}" class="ff-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="${row.filename}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                    </a>
                    <div class="ff-result-label">${row.entity_name || row.frame_name || ''}</div>
                </div>
            `).join('');

            if (ffTotalPages > 1) {
                document.getElementById('ffPagination').style.display = 'flex';
                document.getElementById('ffPageLabel').innerHTML = `
                    <div style="display:flex; align-items:center; gap:4px;">
                        Pg <input type="number" value="${page}" min="1" max="${ffTotalPages}" 
                            style="width:40px; background:var(--pl-bg); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; text-align:center; padding:2px; font-size:0.75rem; font-family:'Space Mono', monospace;" 
                            onchange="if(this.value) runForgeSearch(parseInt(this.value))"> 
                        of ${ffTotalPages}
                    </div>
                `;
                document.getElementById('ffPrevBtn').disabled = (page <= 1);
                document.getElementById('ffNextBtn').disabled = (page >= ffTotalPages);
            }

            // Re-initialize lightbox for the new dynamically loaded grid elements
            if (window.initForgeLightbox) window.initForgeLightbox();
        })
        .catch(err => {
            console.error("Forge Search Network/Parse Error:", err);
            grid.innerHTML = '<div class="forge-result-empty">Network error. Check console.</div>';
        });
}

// ── Split Logic ──────────────────────────────────────────────────────────────
function openSplitModal(el) {
    const wrap = el.closest('.seq-item-wrap');
    const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
    // Split will happen AFTER this element
    const index = wraps.indexOf(wrap) + 1; 

    document.getElementById('splitIndex').value = index;
    document.getElementById('splitNewName').value = originalName + ' part 2';
    document.getElementById('splitModal').classList.add('active');
    setTimeout(() => document.getElementById('splitNewName').focus(), 50);
}

function closeSplitModal() {
    document.getElementById('splitModal').classList.remove('active');
}

function submitSplit(loadPart) {
    const splitIndex = document.getElementById('splitIndex').value;
    const newName = document.getElementById('splitNewName').value.trim();
    
    if (!newName) return Toast.show('Name is required.', 'warn');

    const fd = new URLSearchParams();
    fd.append('action', 'split_sequence');
    fd.append('sequence_id', SEQ_ID);
    fd.append('split_index', splitIndex);
    fd.append('new_name', newName);

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r=>r.json()).then(res => {
            if (res.success) {
                if (loadPart === 'part2') {
                    window.location.href = '?id=' + res.new_sequence_id;
                } else {
                    window.location.reload();
                }
            } else {
                Toast.show(res.message || 'Split failed', 'error');
            }
        });
}

// ── Entity Modals ────────────────────────────────────────────────────────────
function openEntityModal(entityType, entityId, label) {
    const url = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
    document.getElementById('entity-iframe').src = url;
    document.getElementById('entityModalTitle').textContent = label + ' — ' + entityType;
    document.getElementById('entity-modal-backdrop').classList.add('active');
}
function closeEntityModal() {
    document.getElementById('entity-modal-backdrop').classList.remove('active');
    document.getElementById('entity-iframe').src = 'about:blank';
}
</script>

<?php
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
