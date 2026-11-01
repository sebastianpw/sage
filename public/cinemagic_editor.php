<?php
// public/cinemagic_editor.php
// Drag and Drop Text Overlay Editor for Cinemagic / Cinematic Sequences
// Includes Gear Menu, PhotoSwipe Lightbox, and Cinemagic assignment panel

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . '/entity_icons.php';

use App\UI\Modules\ModuleRegistry;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$seqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editLang = $_GET['lang'] ?? 'en';

$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'sketches', 'frames'];
$gearMenu = $registry->create('gear_menu', [
    'position'          => 'top-right',
    'icon'              => '&#9881;',
    'icon_size'         => '1.3em',
    'show_for_entities' => $entities_with_menu,
]);
foreach ($entities_with_menu as $entity_name) {
    $gearMenu->addStandardActions($entity_name);
}
$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = '';
    foreach (['thumb', 'thumbnail', 'image', 'image_url', 'image_path', 'file_path', 'path', 'src', 'url', 'filename', 'file_name'] as $key) {
        if (!empty($row[$key]) && is_string($row[$key])) {
            $candidate = $row[$key];
            break;
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

// Global System Languages Fetch
$allLanguages = [];
try {
    $allLanguages = $pdo->query("SELECT * FROM system_languages ORDER BY is_main DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

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
    .su-modal-box { width:100%; max-width:440px; max-height:90vh; overflow-y:auto; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; padding:20px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
    .su-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
    .su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-amber); text-transform:uppercase; letter-spacing:1px; }
    .su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
    .su-input { width:100%; box-sizing:border-box; background:var(--pl-card); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s; }
    .su-input:focus { border-color:var(--pl-amber); }
    .pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
    .pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-card); color:var(--pl-text-dim); }
    .pl-btn-secondary:hover { border-color:var(--pl-amber); color:var(--pl-amber); }
    .pl-btn-primary { border-color:var(--pl-amber); background:var(--pl-amber); color:#000; font-weight:bold; }
    .pl-btn-primary:hover { filter:brightness(1.1); }

    /* Autocomplete dropdown styling */
    .ff-dropdown { border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card); max-height: 240px; overflow-y: auto; display: none; position: absolute; z-index: 100; width: 100%; left: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.3); overscroll-behavior: contain; touch-action: pan-y; }
    .ff-dropdown.open { display: block; }
    .ff-dropdown-item { padding: 8px 10px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--pl-text); display: flex; justify-content: space-between; align-items:center; }
    .ff-dropdown-item:hover { background: rgba(245,166,35,0.1); color: var(--pl-amber); }

    /* Category modal with higher z-index to overlay any active modal */
    #categoryManageModal.su-modal-backdrop { z-index: 350000 !important; }
    </style>

    <div style="max-width:800px;margin:60px auto;padding:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
            <h2 style="font-family:'Space Mono',monospace;color:var(--pl-amber); margin:0;">📚 Cinemagic Editor — Select Sequence</h2>
        </div>
        
        <!-- Filters & Pagination Bar -->
        <div style="display:flex; gap:12px; margin-bottom: 20px; align-items:center; flex-wrap:wrap; background:var(--pl-surface); padding:12px; border:1px solid var(--pl-border); border-radius:6px;">
            <div style="position:relative; flex:1; min-width: 200px;">
                <input type="text" id="mainSeqSearch" class="su-input" placeholder="Search sequences... (type for quick jump)" oninput="debounceMainSearch(this.value)" autocomplete="off">
                <div id="mainSeqDrop" class="ff-dropdown" style="top:40px;"></div>
            </div>
            <div style="flex:1; min-width: 170px; max-width: 240px; display:flex; gap:6px; align-items:center;">
                <select id="mainSeqCat" class="su-input" style="flex:1;" onchange="goToMainPage(1)">
                    <option value="">All Categories</option>
                </select>
                <button type="button" class="pl-btn pl-btn-secondary" style="padding:7px 10px;" onclick="openCategoryManageModal()" title="Manage Categories">
                    <i class="bi bi-gear"></i>
                </button>
            </div>
            <div style="display:flex; align-items:center; gap:8px; margin-left:auto;">
                <button class="pl-btn pl-btn-secondary" onclick="resetMainFilter()" title="Clear filters & reset">↻ Reset</button>
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

    <!-- Category Manager Modal (High z-index: 350000) -->
    <div id="categoryManageModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeCategoryManageModal()">
        <div class="su-modal-box" style="max-width:480px;">
            <div class="su-modal-header">
                <div class="su-modal-title" style="color:var(--pl-amber);"><i class="bi bi-tags"></i> Manage Categories</div>
                <button onclick="closeCategoryManageModal()" class="su-modal-close">✕</button>
            </div>

            <!-- Create Category Input -->
            <div style="display:flex; gap:8px; margin-bottom:14px;">
                <input type="text" id="catCreateInput" class="su-input" placeholder="New category name…" onkeydown="if(event.key==='Enter') createCategoryAjax()">
                <button class="pl-btn pl-btn-primary" style="flex-shrink:0;" onclick="createCategoryAjax()">＋ Add</button>
            </div>

            <!-- Category Search / Filter -->
            <div style="position:relative; margin-bottom:12px;">
                <input type="text" id="catFilterInput" class="su-input" placeholder="Filter categories…" oninput="debounceCategorySearch(this.value)" style="font-size:0.75rem; padding:6px 10px;">
            </div>

            <!-- Paginated Categories List Container -->
            <div id="catListContainer" style="display:flex; flex-direction:column; gap:6px; min-height:140px; margin-bottom:14px;">
                <div style="text-align:center; padding:20px; color:var(--pl-text-dim); font-size:0.8rem;">Loading categories…</div>
            </div>

            <!-- Pagination Bar for Categories -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--pl-border); padding-top:12px;">
                <button class="pl-btn pl-btn-secondary" id="catPrevBtn" onclick="changeCategoryPage(-1)">« Prev</button>
                <span id="catPaginationLabel" style="font-size:0.75rem; color:var(--pl-text-dim); font-family:'Space Mono',monospace;">Page 1 of 1</span>
                <button class="pl-btn pl-btn-secondary" id="catNextBtn" onclick="changeCategoryPage(1)">Next »</button>
            </div>
        </div>
    </div>

    <!-- ── Sequence Overlay Modal ───────────────────────────────────────── -->
    <div id="seqOverlayBackdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:300000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);">
        <div style="width:100%;max-width:460px;background:var(--pl-card);border:1px solid var(--pl-border);border-radius:8px;padding:20px;box-shadow:0 10px 40px rgba(0,0,0,0.5);margin:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div style="font-family:'Space Mono',monospace;font-size:0.85rem;color:var(--pl-amber);text-transform:uppercase;letter-spacing:1px;">
                    Sequence Overlay Texts
                </div>
                <button onclick="closeSeqOverlayModal()" style="background:transparent;border:none;color:var(--pl-text-dim);cursor:pointer;font-size:1.2rem;line-height:1;">✕</button>
            </div>

            <div style="font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--pl-text-dim);margin-bottom:12px;" id="seqOverlaySeqName"></div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                <label style="font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--pl-text-dim);white-space:nowrap;">Language:</label>
                <select id="seqOverlayLang" style="background:var(--pl-surface);color:var(--pl-text);border:1px solid var(--pl-border);border-radius:4px;padding:5px 8px;font-family:'Space Mono',monospace;font-size:0.75rem;flex:1;" onchange="loadSeqOverlay()">
                    <?php foreach ($allLanguages as $l): ?>
                        <option value="<?= $l['code'] ?>"><?= htmlspecialchars($l['name']) ?> (<?= strtoupper($l['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Name Overlay</label>
                    <input type="text" id="seqOverlayName" placeholder="Override sequence name for this language…"
                           style="width:100%;box-sizing:border-box;background:var(--pl-surface);color:var(--pl-text);border:1px solid var(--pl-border);border-radius:4px;padding:8px 10px;font-family:'Space Mono',monospace;font-size:0.8rem;">
                </div>
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Description Overlay</label>
                    <textarea id="seqOverlayDesc" rows="4" placeholder="Override sequence description for this language…"
                              style="width:100%;box-sizing:border-box;background:var(--pl-surface);color:var(--pl-text);border:1px solid var(--pl-border);border-radius:4px;padding:8px 10px;font-family:'Space Mono',monospace;font-size:0.8rem;resize:vertical;"></textarea>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <button onclick="closeSeqOverlayModal()" style="padding:7px 16px;border-radius:4px;border:1px solid var(--pl-border);background:transparent;color:var(--pl-text-dim);font-family:'Space Mono',monospace;font-size:0.75rem;cursor:pointer;">Cancel</button>
                <button onclick="saveSeqOverlay()" style="padding:7px 16px;border-radius:4px;border:1px solid var(--pl-amber);background:var(--pl-amber);color:#000;font-family:'Space Mono',monospace;font-size:0.75rem;font-weight:bold;cursor:pointer;">Save</button>
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
        restoreMainState();
        loadMainCategories().then(() => {
            const stored = localStorage.getItem('cinemagic_list_state');
            if (stored) {
                try {
                    const state = JSON.parse(stored);
                    if (state.category) document.getElementById('mainSeqCat').value = state.category;
                } catch(e) {}
            }
            loadMainSequences();
        });
    });

    function saveMainState() {
        const state = {
            page: curMainPage,
            search: document.getElementById('mainSeqSearch').value.trim(),
            category: document.getElementById('mainSeqCat').value
        };
        localStorage.setItem('cinemagic_list_state', JSON.stringify(state));
    }

    function restoreMainState() {
        try {
            const stored = localStorage.getItem('cinemagic_list_state');
            if (stored) {
                const state = JSON.parse(stored);
                if (state.page) curMainPage = parseInt(state.page) || 1;
                if (state.search) document.getElementById('mainSeqSearch').value = state.search;
                document.getElementById('mainPageInput').value = curMainPage;
            }
        } catch (e) {}
    }

    function resetMainFilter() {
        localStorage.removeItem('cinemagic_list_state');
        window.location.reload();
    }

    function loadMainCategories() {
        const activeMain = document.getElementById('mainSeqCat').value;
        return fetch('cinemagic_editor_api.php?action=get_sequence_categories')
            .then(r=>r.json()).then(res => {
                const sel = document.getElementById('mainSeqCat');
                sel.innerHTML = '<option value="">All Categories</option>';
                if(res.success && res.data && res.data.length > 0) {
                    res.data.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        sel.appendChild(opt);
                    });
                }
                sel.value = activeMain || '';
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
        saveMainState();
        const search = document.getElementById('mainSeqSearch').value.trim();
        const catId = document.getElementById('mainSeqCat').value;
        const url = `cinemagic_editor_api.php?action=get_sequences_list&page=${curMainPage}&search=${encodeURIComponent(search)}&category_id=${catId}`;
        
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
                    const dateShort = s.created_at ? s.created_at.replace(/\D/g, '').slice(2, 8) : '';
                    return `<div style="display:flex;align-items:stretch;background:var(--pl-card);border:1px solid var(--pl-border);border-radius:6px;overflow:hidden;transition:border-color .2s;" onmouseover="this.style.borderColor='var(--pl-amber)'" onmouseout="this.style.borderColor='var(--pl-border)'">
                        <a href="?id=${s.id}" style="display:flex;justify-content:space-between;align-items:flex-start;flex:1;min-width:0;padding:10px 12px;text-decoration:none;color:var(--pl-text);font-family:'Space Mono',monospace;font-size:.85rem;gap:8px;">
                            <span style="flex:1;min-width:0;white-space:normal;word-break:break-word;line-height:1.35;">
                                #${s.id} 
                                <span style="color:var(--pl-amber);opacity:0.8;margin:0 4px;">[${s.skt_count}]</span> 
                                ${s.name}
                            </span>
                            <span style="color:var(--pl-text-dim);font-size:0.75rem;flex-shrink:0;margin-top:1px;">${dateShort}</span>
                        </a>
                        <div style="display:flex; border-left:1px solid var(--pl-border); align-self:stretch;">
                            <a href="cinemagic.php?id=${s.id}" target="_blank" title="View Cinematic" style="display:flex;align-items:center;justify-content:center;text-decoration:none;border-right:1px solid var(--pl-border);padding:0 12px;cursor:pointer;color:var(--pl-text-dim);font-size:.9rem;transition:all .2s;" onmouseover="this.style.color='var(--pl-amber)';this.style.background='rgba(245,166,35,0.07)'" onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'"><i class="bi bi-film"></i></a>
                            <button onclick="openSeqOverlayModal(${s.id}, '${safeName}')" title="Edit sequence overlay texts" style="background:transparent;border:none;padding:0 12px;cursor:pointer;color:var(--pl-text-dim);font-size:.9rem;display:flex;align-items:center;transition:all .2s;" onmouseover="this.style.color='var(--pl-amber)';this.style.background='rgba(245,166,35,0.07)'" onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'"><i class="bi bi-pencil"></i></button>
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
            curMainPage = 1;
            loadMainSequences();

            if(!val.trim()) {
                drop.classList.remove('open');
                return;
            }
            drop.innerHTML = '<div style="padding:8px 10px; font-size:0.75rem; color:var(--pl-text-dim);">Searching proposals...</div>';
            drop.classList.add('open');

            fetch('cinemagic_editor_api.php?action=search_sequences&q=' + encodeURIComponent(val))
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
    
    document.addEventListener('click', e => {
        const drop = document.getElementById('mainSeqDrop');
        if(drop && !e.target.closest('#mainSeqSearch') && !e.target.closest('#mainSeqDrop')) {
            drop.classList.remove('open');
        }
    });

    // --- CATEGORY MANAGER MODAL LOGIC ---
    let curCatPage = 1;
    let totalCatPages = 1;
    let catSearchTimer = null;

    function openCategoryManageModal() {
        document.getElementById('catCreateInput').value = '';
        document.getElementById('catFilterInput').value = '';
        document.getElementById('categoryManageModal').classList.add('active');
        curCatPage = 1;
        loadCategoryManagerList();
        setTimeout(() => document.getElementById('catCreateInput').focus(), 50);
    }

    function closeCategoryManageModal() {
        document.getElementById('categoryManageModal').classList.remove('active');
    }

    function changeCategoryPage(delta) {
        const target = curCatPage + delta;
        if (target >= 1 && target <= totalCatPages) {
            curCatPage = target;
            loadCategoryManagerList();
        }
    }

    function debounceCategorySearch(val) {
        clearTimeout(catSearchTimer);
        catSearchTimer = setTimeout(() => {
            curCatPage = 1;
            loadCategoryManagerList();
        }, 300);
    }

    function loadCategoryManagerList() {
        const search = document.getElementById('catFilterInput').value.trim();
        const cont = document.getElementById('catListContainer');
        cont.innerHTML = '<div style="text-align:center; padding:15px; color:var(--pl-text-dim); font-size:0.8rem;">Loading…</div>';

        fetch(`cinemagic_editor_api.php?action=list_sequence_categories&page=${curCatPage}&per_page=8&search=${encodeURIComponent(search)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    totalCatPages = res.meta.total_pages;
                    curCatPage = res.meta.page;
                    document.getElementById('catPaginationLabel').textContent = `Page ${curCatPage} of ${totalCatPages}`;
                    document.getElementById('catPrevBtn').disabled = (curCatPage <= 1);
                    document.getElementById('catNextBtn').disabled = (curCatPage >= totalCatPages);

                    if (!res.data.length) {
                        cont.innerHTML = '<div style="text-align:center; padding:15px; color:var(--pl-text-dim); font-size:0.8rem;">No categories found.</div>';
                        return;
                    }

                    cont.innerHTML = res.data.map(c => {
                        const safeName = c.name.replace(/"/g, '&quot;').replace(/'/g, "\\'");
                        return `
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:7px 10px; background:var(--pl-card); border:1px solid var(--pl-border); border-radius:4px; gap:8px;">
                            <div style="flex:1; min-width:0; display:flex; align-items:center; gap:8px;">
                                <input type="text" class="su-input cat-name-input" value="${safeName}" style="padding:4px 8px; font-size:0.8rem; display:none;" onkeydown="if(event.key==='Enter') saveCatInline(${c.id}, this)">
                                <span class="cat-name-display" style="font-size:0.8rem; color:var(--pl-text); font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.name}</span>
                                <span style="font-size:0.65rem; color:var(--pl-text-dim); flex-shrink:0;">(${c.seq_count || 0} seq)</span>
                            </div>
                            <div style="display:flex; gap:4px; flex-shrink:0;">
                                <button type="button" class="pl-btn pl-btn-secondary cat-edit-btn" style="padding:4px 8px; font-size:0.75rem;" onclick="startCatInline(this)"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="pl-btn pl-btn-primary cat-save-btn" style="padding:4px 8px; font-size:0.75rem; display:none;" onclick="saveCatInline(${c.id}, this)"><i class="bi bi-check-lg"></i></button>
                                <button type="button" class="pl-btn pl-btn-secondary cat-cancel-btn" style="padding:4px 8px; font-size:0.75rem; display:none;" onclick="cancelCatInline(this, '${safeName}')"><i class="bi bi-x-lg"></i></button>
                                <button type="button" class="pl-btn pl-btn-secondary" style="padding:4px 8px; font-size:0.75rem; color:#ff4444; border-color:rgba(255,68,68,0.3);" onclick="deleteCategoryAjax(${c.id}, '${safeName}')"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>`;
                    }).join('');
                } else {
                    cont.innerHTML = `<div style="text-align:center; padding:15px; color:#ff4444; font-size:0.8rem;">Error: ${res.message}</div>`;
                }
            }).catch(() => {
                cont.innerHTML = '<div style="text-align:center; padding:15px; color:#ff4444; font-size:0.8rem;">Network error</div>';
            });
    }

    function createCategoryAjax() {
        const inp = document.getElementById('catCreateInput');
        const name = inp.value.trim();
        if (!name) return Toast.show('Category name required', 'warn');

        const fd = new URLSearchParams();
        fd.append('action', 'create_sequence_category');
        fd.append('name', name);

        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Toast.show(`Category "${name}" created`, 'success');
                    inp.value = '';
                    loadCategoryManagerList();
                    loadMainCategories();
                } else {
                    Toast.show(res.message || 'Creation failed', 'error');
                }
            });
    }

    function startCatInline(btn) {
        const row = btn.closest('div').parentElement;
        row.querySelector('.cat-name-display').style.display = 'none';
        const input = row.querySelector('.cat-name-input');
        input.style.display = 'block';
        input.focus();
        row.querySelector('.cat-edit-btn').style.display = 'none';
        row.querySelector('.cat-save-btn').style.display = 'inline-block';
        row.querySelector('.cat-cancel-btn').style.display = 'inline-block';
    }

    function cancelCatInline(btn, origVal) {
        const row = btn.closest('div').parentElement;
        const input = row.querySelector('.cat-name-input');
        input.value = origVal;
        input.style.display = 'none';
        row.querySelector('.cat-name-display').style.display = 'block';
        row.querySelector('.cat-edit-btn').style.display = 'inline-block';
        row.querySelector('.cat-save-btn').style.display = 'none';
        row.querySelector('.cat-cancel-btn').style.display = 'none';
    }

    function saveCatInline(id, el) {
        const row = el.closest('div').parentElement;
        const newName = row.querySelector('.cat-name-input').value.trim();
        if (!newName) return Toast.show('Name cannot be empty', 'warn');

        const fd = new URLSearchParams();
        fd.append('action', 'update_sequence_category');
        fd.append('id', id);
        fd.append('name', newName);

        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Toast.show('Category updated', 'success');
                    row.querySelector('.cat-name-display').textContent = newName;
                    cancelCatInline(row.querySelector('.cat-cancel-btn'), newName);
                    loadMainCategories();
                } else {
                    Toast.show(res.message || 'Update failed', 'error');
                }
            });
    }

    function deleteCategoryAjax(id, name) {
        if (!confirm(`Delete category "${name}"? Sequences assigned to it will become uncategorized.`)) return;

        const fd = new URLSearchParams();
        fd.append('action', 'delete_sequence_category');
        fd.append('id', id);

        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Toast.show('Category deleted', 'info');
                    loadCategoryManagerList();
                    loadMainCategories();
                } else {
                    Toast.show(res.message || 'Delete failed', 'error');
                }
            });
    }

    // ── Sequence Overlay Modal Logic ──
    var _seqOverlayActiveId = null;

    function openSeqOverlayModal(id, name) {
        _seqOverlayActiveId = id;
        document.getElementById('seqOverlaySeqName').textContent = '#' + id + ' — ' + name;
        document.getElementById('seqOverlayLang').value = 'en';
        document.getElementById('seqOverlayName').value = '';
        document.getElementById('seqOverlayDesc').value = '';
        document.getElementById('seqOverlayBackdrop').style.display = 'flex';
        loadSeqOverlay();
    }

    document.getElementById('seqOverlayBackdrop').addEventListener('mousedown', function(e) {
        if (e.target === this) closeSeqOverlayModal();
    });

    function closeSeqOverlayModal() {
        document.getElementById('seqOverlayBackdrop').style.display = 'none';
        _seqOverlayActiveId = null;
    }

    function loadSeqOverlay() {
        if (!_seqOverlayActiveId) return;
        var lang = document.getElementById('seqOverlayLang').value;
        fetch('cinemagic_editor_api.php?action=get_sequence_overlay&sequence_id=' + _seqOverlayActiveId + '&lang=' + encodeURIComponent(lang))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    document.getElementById('seqOverlayName').value = res.name_overlay || '';
                    document.getElementById('seqOverlayDesc').value = res.description_overlay || '';
                }
            });
    }

    function saveSeqOverlay() {
        if (!_seqOverlayActiveId) return;
        var lang = document.getElementById('seqOverlayLang').value;
        var fd = new URLSearchParams();
        fd.append('action', 'save_sequence_overlay');
        fd.append('sequence_id', _seqOverlayActiveId);
        fd.append('lang', lang);
        fd.append('name_overlay', document.getElementById('seqOverlayName').value);
        fd.append('description_overlay', document.getElementById('seqOverlayDesc').value);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    Toast.show('Overlay saved.', 'success');
                    closeSeqOverlayModal();
                } else {
                    Toast.show(res.message || 'Save failed.', 'error');
                }
            });
    }
    </script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Sequence - Cinemagic Editor', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

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
$sketchesData  = [];
$overlayTexts  = [];
$overlayTextsLang = [];

if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));

    $stmtS = $pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }

    try {
        $stmtO = $pdo->prepare("SELECT id, sketch_id, text_content, language_code, display_order FROM sketch_overlay_texts WHERE sketch_id IN ($inClause) AND language_code IN ('en', ?) ORDER BY display_order ASC, id ASC");
        $stmtO->execute([...$pureSketchIds, $editLang]);
        foreach ($stmtO->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['language_code'] === 'en') {
                $overlayTexts[(int)$row['sketch_id']][] = $row;
            } else {
                $overlayTextsLang[(int)$row['sketch_id']][$row['display_order']] = $row;
            }
        }
    } catch (PDOException $e) {}
}

$selectedFrameMap = [];
$activeFrameIds   = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $selectedFrameMap[(int)$row['id']] = [
            'thumb'    => resolveFrameThumb($row, (int)$row['id']),
            'frame_id' => (int)$row['id'],
        ];
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
        $sketchId = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sketchId])) {
            $latestFrameBySketch[$sketchId] = [
                'thumb'    => resolveFrameThumb($row, (int)$row['id']),
                'frame_id' => (int)$row['id'],
            ];
        }
    }
}

$currentCinemagics = [];
try {
    $stmtCM = $pdo->prepare(
        "SELECT c.id, c.name, cs.sort_order, cs.chapter_label
         FROM cinemagics c
         JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = c.id
         WHERE cs.sequence_id = ?
         ORDER BY c.name ASC"
    );
    $stmtCM->execute([$seqId]);
    $currentCinemagics = $stmtCM->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {} 

$allCinemagics = [];
try {
    $allCinemagics = $pdo->query("SELECT id, name FROM cinemagics ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$pageTitle = "Cinemagic Editor: " . htmlspecialchars($seq['name']);
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
:root, [data-theme="dark"] {
    --cm-bg:          #080b10;
    --cm-surface:     #0e1319;
    --cm-card:        #111820;
    --cm-border:      #1c2535;
    --cm-text:        #c8d4e8;
    --cm-text-dim:    #5a6a80;
    --cm-amber:       #f5a623;
    --cm-red:         #f05060;
    --cm-green:       #4caf80;
}
[data-theme="light"] {
    --cm-bg:          #f4f6fa;
    --cm-surface:     #ffffff;
    --cm-card:        #ffffff;
    --cm-border:      #d0d8e8;
    --cm-text:        #1a2233;
    --cm-text-dim:    #7a8aaa;
    --cm-amber:       #c8880a;
    --cm-red:         #d03040;
    --cm-green:       #2e8a58;
}

--mono: 'Space Mono', 'Courier New', monospace;
--sans: 'Syne', system-ui, sans-serif;
--cm-radius: 6px;

body { background: var(--cm-bg); color: var(--cm-text); font-family: var(--sans); margin: 0; padding: 0; transition: background 0.2s, color 0.2s; }

.editor-nav { display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: rgba(0,0,0,0.6); border-bottom: 1px solid var(--cm-border); position: sticky; top: 0; z-index: 100; backdrop-filter: blur(6px); }
[data-theme="light"] .editor-nav { background: rgba(244,246,250,0.92); }
.editor-nav-title { font-family: var(--mono); font-size: 0.8rem; color: var(--cm-text); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.editor-nav-link { font-family: var(--mono); font-size: 0.7rem; padding: 6px 12px; border: 1px solid var(--cm-border); border-radius: 4px; color: var(--cm-text-dim); text-decoration: none; transition: all 0.2s; background: var(--cm-surface); cursor: pointer; }
.editor-nav-link:hover { color: var(--cm-amber); border-color: var(--cm-amber); }
.editor-nav-link.primary { color: #000; background: var(--cm-amber); border-color: var(--cm-amber); font-weight: bold; }

.workspace { max-width: 900px; margin: 0 auto; padding: 40px 15px 100px; }
.lang-not-en .drag-handle, .lang-not-en .add-line-btn, .lang-not-en .del-btn { display: none !important; }
.lang-not-en .overlay-block { padding-left: 12px; padding-right: 12px; }

.sketch-block { background: var(--cm-card); border: 1px solid var(--cm-border); border-radius: var(--cm-radius); padding: 16px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
[data-theme="light"] .sketch-block { box-shadow: 0 2px 10px rgba(0,0,0,0.08); }

.sketch-header { display: flex; gap: 15px; margin-bottom: 15px; border-bottom: 1px solid var(--cm-border); padding-bottom: 15px; }
.sketch-thumb { position: relative; width: 120px; height: 120px; flex-shrink: 0; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--cm-border); }
[data-theme="light"] .sketch-thumb { background: #eef0f4; }
.sketch-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sketch-info { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.sketch-id { font-family: var(--mono); font-size: 0.65rem; color: var(--cm-amber); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 5px; }
.sketch-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 5px 0; color: var(--cm-text); }
.sketch-desc { font-size: 0.8rem; color: var(--cm-text-dim); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.overlays-wrap { display: flex; flex-direction: column; gap: 12px; }
.overlay-block { position: relative; padding-left: 28px; padding-right: 40px; transition: transform 0.2s, opacity 0.2s; }

.overlay-text { width: 100%; box-sizing: border-box; background: var(--cm-surface); border: 1px solid var(--cm-border); color: var(--cm-text); font-family: var(--sans); font-size: 0.95rem; line-height: 1.5; padding: 10px 12px; border-radius: 4px; resize: none; overflow: hidden; transition: border-color 0.2s, background 0.2s; }
.overlay-text:focus { outline: none; border-color: var(--cm-amber); background: var(--cm-card); }

.drag-handle { position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 28px; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--cm-text-dim); font-size: 1rem; cursor: grab; opacity: 0.3; transition: opacity 0.2s; touch-action: none; user-select: none; }
.overlay-block:hover .drag-handle { opacity: 1; }
.drag-handle:active { cursor: grabbing; }

.action-btns { position: absolute; right: 0; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 6px; opacity: 0; transition: opacity 0.2s; }
.overlay-block:hover .action-btns, .overlay-text:focus ~ .action-btns { opacity: 1; }
@media (hover: none) { .action-btns { opacity: 1; } }

.del-btn { width: 28px; height: 28px; border-radius: 4px; border: 1px solid transparent; background: transparent; color: var(--cm-red); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.85rem; transition: all 0.15s; }
.del-btn:hover { border-color: var(--cm-red); background: rgba(240,80,96,0.1); }

.overlay-block.drag-over-top    { border-top: 2px solid var(--cm-amber); padding-top: 2px; }
.overlay-block.drag-over-bottom { border-bottom: 2px solid var(--cm-amber); padding-bottom: 2px; }
.overlay-block.dragging         { opacity: 0.4; }

.add-line-btn { background: transparent; border: 1px dashed var(--cm-border); color: var(--cm-text-dim); padding: 10px 12px; border-radius: 4px; font-family: var(--mono); font-size: 0.75rem; text-transform: uppercase; cursor: pointer; display: block; margin: 10px auto 0; transition: all 0.2s; width: 100%; max-width: 200px; }
.add-line-btn:hover { border-color: var(--cm-amber); color: var(--cm-amber); background: rgba(245,166,35,0.05); }

/* Cinemagic Panel */
.cinemagic-panel { max-width: 900px; margin: 0 auto 0; padding: 0 15px 30px; }
.cinemagic-section { background: var(--cm-card); border: 1px solid var(--cm-border); border-radius: var(--cm-radius); padding: 16px; margin-bottom: 20px; }
.cinemagic-section-title { font-family: var(--mono); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: var(--cm-amber); margin: 0; display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
.cinemagic-section-title .cm-chevron { margin-left: auto; font-size: 0.6rem; transition: transform 0.25s cubic-bezier(0.4,0,0.2,1); opacity: 0.6; }
.cinemagic-section.collapsed .cm-chevron { transform: rotate(-90deg); }
.cm-collapsible { overflow: hidden; max-height: 600px; transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.25s ease; opacity: 1; margin-top: 14px; }
.cinemagic-section.collapsed .cm-collapsible { max-height: 0; opacity: 0; }
.cinemagic-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border: 1px solid var(--cm-border); border-radius: 4px; background: var(--cm-surface); font-family: var(--mono); font-size: 0.75rem; color: var(--cm-text); margin: 0 6px 6px 0; }
.cinemagic-badge .cm-remove { background: none; border: none; color: var(--cm-red); cursor: pointer; padding: 0; font-size: 0.85rem; line-height: 1; opacity: 0.6; transition: opacity 0.15s; }
.cinemagic-badge .cm-remove:hover { opacity: 1; }
.cinemagic-badge .cm-label { font-size: 0.65rem; color: var(--cm-text-dim); border-left: 1px solid var(--cm-border); padding-left: 8px; margin-left: 2px; }

.cm-assign-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin-top: 12px; }
.cm-assign-row select, .cm-assign-row input[type="text"] { background: var(--cm-surface); color: var(--cm-text); border: 1px solid var(--cm-border); border-radius: 4px; padding: 7px 10px; font-family: var(--mono); font-size: 0.75rem; flex: 1; min-width: 120px; }
.cm-assign-row select:focus, .cm-assign-row input[type="text"]:focus { outline: none; border-color: var(--cm-amber); }
.cm-btn { padding: 7px 14px; border-radius: 4px; border: 1px solid; font-family: var(--mono); font-size: 0.75rem; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.cm-btn-primary { border-color: var(--cm-amber); background: var(--cm-amber); color:#000; font-weight: bold; }
.cm-btn-primary:hover { filter: brightness(1.1); }
.cm-btn-secondary { border-color: var(--cm-border); background: var(--cm-surface); color: var(--cm-text-dim); }
.cm-btn-secondary:hover { border-color: var(--cm-amber); color: var(--cm-amber); }
.cm-new-form { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; align-items: flex-end; }
.cm-new-form input[type="text"], .cm-new-form textarea { background: var(--cm-surface); color: var(--cm-text); border: 1px solid var(--cm-border); border-radius: 4px; padding: 7px 10px; font-family: var(--mono); font-size: 0.75rem; flex: 1; min-width: 120px; }
.cm-new-form input[type="text"]:focus, .cm-new-form textarea:focus { outline: none; border-color: var(--cm-amber); }
.cm-empty { font-size: 0.8rem; color: var(--cm-text-dim); font-style: italic; }
.cm-divider { border: none; border-top: 1px solid var(--cm-border); margin: 14px 0; }

/* Language Modal */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 300000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-backdrop.active { display: flex; }
.modal-box { width: 100%; max-width: 400px; background: var(--cm-surface); border: 1px solid var(--cm-border); border-radius: 8px; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.modal-title { font-size: 1rem; font-weight: bold; font-family: var(--mono); color: var(--cm-amber); text-transform: uppercase; letter-spacing: 1px; }
.modal-close { background: transparent; border: none; color: var(--cm-text-dim); cursor: pointer; font-size: 1.2rem; }
.lang-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--cm-card); border: 1px solid var(--cm-border); border-radius: 4px; }
</style>

<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
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
</script>

<div class="editor-nav">
    <a href="view_narrative_sequence_analysis.php?id=<?= $seqId ?>" class="editor-nav-link">◀ Analysis</a>
    <span class="editor-nav-title">Cinemagic Editor: <?= htmlspecialchars($seq['name']) ?></span>
    
    <select id="editor-lang-select" onchange="window.location.href='?id=<?= $seqId ?>&lang='+this.value" class="editor-nav-link" style="appearance:auto; background:var(--cm-card);">
        <?php foreach ($allLanguages as $l): ?>
            <option value="<?= $l['code'] ?>" <?= $l['code'] === $editLang ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?> (<?= strtoupper($l['code']) ?>)</option>
        <?php endforeach; ?>
    </select>
    <button class="editor-nav-link" onclick="openLangModal()" title="System Languages">
        <i class="bi bi-globe"></i>
    </button>
    <a href="cinemagic.php?id=<?= $seqId ?>&lang=<?= $editLang ?>" class="editor-nav-link primary" target="_blank">▶ View Cinematic</a>
</div>

<!-- ── Cinemagic Assignment Panel ───────────────────────────────────────── -->
<div class="cinemagic-panel">
    <div class="cinemagic-section">
        <h3 class="cinemagic-section-title" id="cm-section-title">&#127916; Cinemagic Collections <span class="cm-chevron">&#9660;</span></h3>

        <div class="cm-collapsible" id="cm-collapsible">

        <!-- Current memberships -->
        <div id="cm-badges-wrap">
            <?php if (empty($currentCinemagics)): ?>
                <span class="cm-empty" id="cm-empty-msg">Not assigned to any Cinemagic yet.</span>
            <?php else: ?>
                <?php foreach ($currentCinemagics as $cm): ?>
                    <span class="cinemagic-badge" data-cinemagic-id="<?= $cm['id'] ?>">
                        <span>#<?= $cm['id'] ?> <?= htmlspecialchars($cm['name']) ?></span>
                        <?php if (!empty($cm['chapter_label'])): ?>
                            <span class="cm-label"><?= htmlspecialchars($cm['chapter_label']) ?></span>
                        <?php endif; ?>
                        <button class="cm-remove" title="Remove from this Cinemagic" data-cinemagic-id="<?= $cm['id'] ?>">&#10005;</button>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <hr class="cm-divider">

        <!-- Assign to existing Cinemagic -->
        <div class="cm-assign-row" id="cm-assign-row"<?= empty($allCinemagics) ? ' style="display:none"' : '' ?>>
            <select id="cm-select">
                <option value="">— Select Cinemagic —</option>
                <?php foreach ($allCinemagics as $cm): ?>
                    <option value="<?= $cm['id'] ?>"><?= htmlspecialchars($cm['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="cm-label-input" placeholder="Chapter label (optional)" style="max-width:180px;">
            <button class="cm-btn cm-btn-primary" id="cm-assign-btn">+ Assign</button>
        </div>

        <hr class="cm-divider">

        <!-- Create new Cinemagic -->
        <div style="font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--cm-text-dim);margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Create New Cinemagic</div>
        <div class="cm-new-form">
            <input type="text" id="cm-new-name" placeholder="Cinemagic name…" style="flex:2;">
            <input type="text" id="cm-new-desc" placeholder="Description (optional)">
            <button class="cm-btn cm-btn-secondary" id="cm-create-btn">Create &amp; Assign</button>
        </div>

        </div><!-- /.cm-collapsible -->
    </div>
</div>

<!-- ── Overlay Editor Workspace ────────────────────────────────────────── -->
<div class="workspace <?= $editLang !== 'en' ? 'lang-not-en' : '' ?>" id="editor-workspace">
    <?php foreach ($itemIds as $idx => $item):
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid <= 0 || !isset($sketchesData[$sid])) continue;

        $sketchRow    = $sketchesData[$sid];
        $activeFrameId = $selectedFrameIds[$idx] ?? null;
        $thumb = '';

        if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
            $thumb = $selectedFrameMap[$activeFrameId]['thumb'];
        } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
            $thumb         = $latestFrameBySketch[$sid]['thumb'];
            $activeFrameId = $latestFrameBySketch[$sid]['frame_id'];
        }

        $lines    = $overlayTexts[$sid] ?? [];
        $gearAttr = '';
        if ($activeFrameId) {
            $gearAttr = 'data-gear-menu data-entity="frames" data-entity-id="'.$activeFrameId.'" data-frame-id="'.$activeFrameId.'" data-img-url="'.htmlspecialchars($thumb).'"';
        }
    ?>
    <div class="sketch-block" data-sketch-id="<?= $sid ?>">
        <div class="sketch-header">
            <div class="sketch-thumb editor-pswp-gallery" <?= $gearAttr ?>>
                <?php if ($thumb): ?>
                    <a href="<?= htmlspecialchars($thumb) ?>" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="<?= htmlspecialchars($thumb) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                    </a>
                <?php else: ?>
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--cm-text-dim);">No Image</div>
                <?php endif; ?>
            </div>

            <div class="sketch-info">
                <div class="sketch-id">Frame <?= sprintf('%02d', $idx + 1) ?> • Sketch #<?= $sid ?></div>
                <h3 class="sketch-title"><?= htmlspecialchars($sketchRow['name']) ?></h3>
                <div class="sketch-desc"><?= htmlspecialchars($sketchRow['description'] ?? 'No description.') ?></div>
            </div>
        </div>

        <div class="overlays-wrap">
            <div class="overlay-list">
                <?php foreach ($lines as $enLine): 
                    $dispOrder = $enLine['display_order'];
                    $textVal   = $enLine['text_content'];
                    $overlayId = $enLine['id'];
                    
                    if ($editLang !== 'en') {
                        $langRow = $overlayTextsLang[$sid][$dispOrder] ?? null;
                        $textVal = $langRow ? $langRow['text_content'] : '';
                    }
                ?>
                    <div class="overlay-block" data-overlay-id="<?= $overlayId ?>" data-display-order="<?= $dispOrder ?>">
                        <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                        <textarea class="overlay-text" placeholder="<?= $editLang === 'en' ? 'Type narrative text...' : htmlspecialchars($enLine['text_content']) ?>"><?= htmlspecialchars($textVal) ?></textarea>
                        <div class="action-btns">
                            <button class="del-btn" title="Delete Text"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="add-line-btn">+ Add Text Block</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Language Manager Modal ── -->
<div class="modal-backdrop" id="langModalBackdrop" onmousedown="if(event.target===this) closeLangModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">System Languages</div>
            <button class="modal-close" onclick="closeLangModal()">✕</button>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <input type="text" id="lang-code" placeholder="Code" maxlength="2" style="width:60px;text-transform:lowercase;background:var(--cm-card);color:var(--cm-text);border:1px solid var(--cm-border);border-radius:4px;padding:6px;font-family:var(--mono);">
            <input type="text" id="lang-name" placeholder="Language Name" style="flex:1;background:var(--cm-card);color:var(--cm-text);border:1px solid var(--cm-border);border-radius:4px;padding:6px;font-family:var(--sans);">
            <button class="cm-btn cm-btn-primary" onclick="saveLanguage()">Save</button>
        </div>
        <div id="lang-list" style="display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto;"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>

<script>
document.addEventListener('DOMContentLoaded', () => {

    function attachGearMenu() {
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            window.GearMenu.attach(document.body);
        } else { setTimeout(attachGearMenu, 200); }
    }
    attachGearMenu();
    if (window.initLightbox) window.initLightbox();

    const SEQ_ID = <?= $seqId ?>;
    const EDIT_LANG = '<?= $editLang ?>';

    const CM_STORAGE_KEY = 'sage_cm_panel_collapsed';
    const cmSection = document.querySelector('.cinemagic-section');
    const cmTitle   = document.getElementById('cm-section-title');

    function setCmCollapsed(collapsed) {
        cmSection.classList.toggle('collapsed', collapsed);
        try { localStorage.setItem(CM_STORAGE_KEY, collapsed ? '1' : '0'); } catch(e) {}
    }

    try {
        if (localStorage.getItem(CM_STORAGE_KEY) === '1') cmSection.classList.add('collapsed');
    } catch(e) {}

    cmTitle.addEventListener('click', () => { setCmCollapsed(!cmSection.classList.contains('collapsed')); });

    // ── Cinemagic Logic ────────────────────────────────────────────────
    function refreshEmptyMsg() {
        const wrap  = document.getElementById('cm-badges-wrap');
        let emptyEl = document.getElementById('cm-empty-msg');
        const hasBadges = wrap.querySelectorAll('.cinemagic-badge').length > 0;
        if (hasBadges && emptyEl) { emptyEl.remove(); }
        if (!hasBadges && !emptyEl) {
            emptyEl = document.createElement('span');
            emptyEl.className = 'cm-empty';
            emptyEl.id = 'cm-empty-msg';
            emptyEl.textContent = 'Not assigned to any Cinemagic yet.';
            wrap.appendChild(emptyEl);
        }
    }

    function addBadge(id, name, label) {
        const wrap = document.getElementById('cm-badges-wrap');
        if (wrap.querySelector(`.cinemagic-badge[data-cinemagic-id="${id}"]`)) return;
        const span = document.createElement('span');
        span.className = 'cinemagic-badge';
        span.dataset.cinemagicId = id;
        span.innerHTML = `<span>#${id} ${escHtml(name)}</span>${label ? `<span class="cm-label">${escHtml(label)}</span>` : ''}<button class="cm-remove" data-cinemagic-id="${id}" title="Remove from this Cinemagic">&#10005;</button>`;
        wrap.appendChild(span);
        bindRemove(span.querySelector('.cm-remove'));
        refreshEmptyMsg();
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function bindRemove(btn) {
        btn.addEventListener('click', () => {
            const cmId = parseInt(btn.dataset.cinemagicId, 10);
            const fd = new URLSearchParams();
            fd.append('action', 'remove_from_cinemagic');
            fd.append('cinemagic_id', cmId);
            fd.append('sequence_id', SEQ_ID);
            fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(res => {
                    if (res.success) {
                        const badge = document.querySelector(`.cinemagic-badge[data-cinemagic-id="${cmId}"]`);
                        if (badge) badge.remove();
                        refreshEmptyMsg();
                        Toast.show('Removed from Cinemagic.', 'info');
                    } else { Toast.show(res.message || 'Remove failed.', 'error'); }
                });
        });
    }

    document.querySelectorAll('.cm-remove').forEach(bindRemove);

    document.getElementById('cm-assign-btn').addEventListener('click', () => {
        const cmId  = parseInt(document.getElementById('cm-select').value, 10);
        const label = document.getElementById('cm-label-input').value.trim();
        if (!cmId) { Toast.show('Please select a Cinemagic.', 'warn'); return; }
        const name = document.getElementById('cm-select').selectedOptions[0]?.text || '';

        const fd = new URLSearchParams();
        fd.append('action', 'assign_to_cinemagic');
        fd.append('cinemagic_id', cmId);
        fd.append('sequence_id', SEQ_ID);
        fd.append('chapter_label', label);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    addBadge(cmId, name, label);
                    document.getElementById('cm-label-input').value = '';
                    Toast.show('Assigned to Cinemagic!', 'success');
                } else { Toast.show(res.message || 'Assignment failed.', 'error'); }
            });
    });

    document.getElementById('cm-create-btn').addEventListener('click', () => {
        const name = document.getElementById('cm-new-name').value.trim();
        const desc = document.getElementById('cm-new-desc').value.trim();
        if (!name) { Toast.show('Enter a name for the new Cinemagic.', 'warn'); return; }

        const fd = new URLSearchParams();
        fd.append('action', 'create_cinemagic');
        fd.append('name', name);
        fd.append('description', desc);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (!res.success) { Toast.show(res.message || 'Create failed.', 'error'); return; }
                const newId = res.cinemagic_id;

                const fd2 = new URLSearchParams();
                fd2.append('action', 'assign_to_cinemagic');
                fd2.append('cinemagic_id', newId);
                fd2.append('sequence_id', SEQ_ID);
                return fetch('cinemagic_editor_api.php', { method: 'POST', body: fd2 })
                    .then(r2 => r2.json()).then(res2 => {
                        if (res2.success) {
                            addBadge(newId, name, '');
                            const sel = document.getElementById('cm-select');
                            if (sel) {
                                const opt = document.createElement('option');
                                opt.value = newId; opt.text = name;
                                sel.appendChild(opt);
                            }
                            const assignRow = document.getElementById('cm-assign-row');
                            if (assignRow) assignRow.style.display = '';
                            document.getElementById('cm-new-name').value = '';
                            document.getElementById('cm-new-desc').value = '';
                            Toast.show('Cinemagic created and assigned!', 'success');
                        }
                    });
            });
    });

    // ── Overlay Editor Logic ─────────────────────────────────────────────────
    let saveTimer = null;

    function createOverlayElement(overlayId, displayOrder, textContent = '') {
        const div = document.createElement('div');
        div.className = 'overlay-block';
        div.draggable = true;
        div.dataset.overlayId = overlayId;
        div.dataset.displayOrder = displayOrder;
        div.innerHTML = `
            <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
            <textarea class="overlay-text" placeholder="Type narrative text..."></textarea>
            <div class="action-btns">
                <button class="del-btn" title="Delete Text"><i class="bi bi-trash"></i></button>
            </div>`;
        const txt = div.querySelector('textarea');
        txt.value = textContent;
        txt.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
            const sketchId = div.closest('.sketch-block').dataset.sketchId;
            debounceSave(overlayId, sketchId, displayOrder, this.value);
        });
        div.querySelector('.del-btn').addEventListener('click', () => deleteOverlay(overlayId, div));
        return div;
    }

    function debounceSave(overlayId, sketchId, displayOrder, text) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            const fd = new URLSearchParams();
            fd.append('action', 'update_overlay');
            fd.append('overlay_id', overlayId);
            fd.append('sketch_id', sketchId);
            fd.append('display_order', displayOrder);
            fd.append('lang', EDIT_LANG);
            fd.append('text', text);
            fetch('cinemagic_editor_api.php', { method: 'POST', body: fd });
        }, 500);
    }

    function addOverlay(sketchId, listContainer) {
        if (EDIT_LANG !== 'en') { Toast.show('Add blocks in English first.', 'warn'); return; }
        const fd = new URLSearchParams();
        fd.append('action', 'add_overlay');
        fd.append('sketch_id', sketchId);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const el = createOverlayElement(res.overlay_id, res.display_order, '');
                    listContainer.appendChild(el);
                    const txt = el.querySelector('textarea');
                    if (txt) {
                        txt.style.height = 'auto';
                        txt.style.height = txt.scrollHeight + 'px';
                        txt.focus();
                    }
                } else { Toast.show('Failed to add text block.', 'error'); }
            });
    }

    function deleteOverlay(overlayId, element) {
        if (EDIT_LANG !== 'en') { Toast.show('Delete blocks in English first.', 'warn'); return; }
        if (!confirm('Delete this text block?')) return;
        const fd = new URLSearchParams();
        fd.append('action', 'delete_overlay');
        fd.append('overlay_id', overlayId);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    element.style.opacity = '0';
                    setTimeout(() => element.remove(), 200);
                } else { Toast.show('Failed to delete.', 'error'); }
            });
    }

    // ── Drag & Drop ──────────────────────────────────────────────────────────
    function initDragSort(container, sketchId) {
        if (EDIT_LANG !== 'en') return;
        let dragSrc = null;

        container.addEventListener('dragstart', e => {
            const block = e.target.closest('.overlay-block');
            if (!block) return;
            dragSrc = block;
            block.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        container.addEventListener('dragend', e => {
            const block = e.target.closest('.overlay-block');
            if (block) block.classList.remove('dragging');
            container.querySelectorAll('.overlay-block').forEach(b => {
                b.classList.remove('drag-over-top', 'drag-over-bottom');
            });
            dragSrc = null;
            persistSortOrder(container, sketchId);
        });

        container.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const block = e.target.closest('.overlay-block');
            if (!block || block === dragSrc) return;
            container.querySelectorAll('.overlay-block').forEach(b => {
                b.classList.remove('drag-over-top', 'drag-over-bottom');
            });
            const rect = block.getBoundingClientRect();
            block.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
        });

        container.addEventListener('drop', e => {
            e.preventDefault();
            if (!dragSrc) return;
            const block = e.target.closest('.overlay-block');
            if (!block || block === dragSrc) return;
            const rect = block.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                container.insertBefore(dragSrc, block);
            } else {
                container.insertBefore(dragSrc, block.nextSibling);
            }
        });

        let pointerDragSrc = null, pointerClone = null, pointerOffsetY = 0;

        container.addEventListener('pointerdown', e => {
            const handle = e.target.closest('.drag-handle');
            if (!handle) return;
            const block = handle.closest('.overlay-block');
            if (!block) return;
            pointerDragSrc = block;
            const rect = block.getBoundingClientRect();
            pointerOffsetY = e.clientY - rect.top;
            pointerClone = block.cloneNode(true);
            pointerClone.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;opacity:0.8;pointer-events:none;z-index:9999;background:var(--cm-surface);border:1px solid var(--cm-amber);box-shadow:0 10px 30px rgba(0,0,0,0.8);`;
            document.body.appendChild(pointerClone);
            block.classList.add('dragging');
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('pointermove', e => {
            if (!pointerDragSrc || !pointerClone) return;
            pointerClone.style.top = (e.clientY - pointerOffsetY) + 'px';
            container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top', 'drag-over-bottom'));
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const block  = target ? target.closest('.overlay-block') : null;
            if (block && block !== pointerDragSrc && block.parentNode === container) {
                const rect = block.getBoundingClientRect();
                block.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
            }
        });

        document.addEventListener('pointerup', e => {
            if (!pointerDragSrc) return;
            if (pointerClone) { pointerClone.remove(); pointerClone = null; }
            pointerDragSrc.classList.remove('dragging');
            container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top', 'drag-over-bottom'));
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const block  = target ? target.closest('.overlay-block') : null;
            if (block && block !== pointerDragSrc && block.parentNode === container) {
                const rect = block.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) {
                    container.insertBefore(pointerDragSrc, block);
                } else {
                    container.insertBefore(pointerDragSrc, block.nextSibling);
                }
            }
            persistSortOrder(container, sketchId);
            pointerDragSrc = null;
        });
    }

    function persistSortOrder(container, sketchId) {
        const ids = Array.from(container.querySelectorAll('.overlay-block'))
            .map(b => b.dataset.overlayId).filter(Boolean);
        if (!ids.length) return;
        const fd = new URLSearchParams();
        fd.append('action', 'reorder_overlays');
        fd.append('sketch_id', sketchId);
        fd.append('order', ids.join(','));
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res=>{
                if(res.success) {
                    container.querySelectorAll('.overlay-block').forEach((b, i) => b.dataset.displayOrder = i);
                }
            });
    }

    document.querySelectorAll('.sketch-block').forEach(block => {
        const sketchId = block.dataset.sketchId;
        const list     = block.querySelector('.overlay-list');
        const addBtn   = block.querySelector('.add-line-btn');

        list.querySelectorAll('.overlay-block').forEach(el => {
            const overlayId = el.dataset.overlayId;
            const displayOrder = el.dataset.displayOrder;
            const txt = el.querySelector('textarea');
            setTimeout(() => { txt.style.height = 'auto'; txt.style.height = txt.scrollHeight + 'px'; }, 50);
            txt.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
                debounceSave(overlayId, sketchId, displayOrder, this.value);
            });
            el.querySelector('.del-btn').addEventListener('click', () => deleteOverlay(overlayId, el));
        });

        addBtn.addEventListener('click', () => addOverlay(sketchId, list));
        initDragSort(list, sketchId);
    });
});

// ── Language Modal JS ────────────────────────────────────────────────────────
window.openLangModal = function() {
    document.getElementById('langModalBackdrop').classList.add('active');
    loadLanguages();
};
window.closeLangModal = function() {
    document.getElementById('langModalBackdrop').classList.remove('active');
};
function loadLanguages() {
    fetch('cinemagic_editor_api.php?action=get_languages')
        .then(r => r.json()).then(res => {
            if (res.success) {
                document.getElementById('lang-list').innerHTML = res.languages.map(l => `
                    <div class="lang-row">
                        <div><strong style="color:var(--cm-amber);text-transform:uppercase;margin-right:8px;">${l.code}</strong> ${escHtml(l.name)}</div>
                        ${l.is_main == 1 ? '<span style="font-size:10px;color:var(--cm-text-dim);font-weight:bold;">MAIN</span>' : `<button class="del-btn" onclick="deleteLanguage('${l.code}')"><i class="bi bi-trash"></i></button>`}
                    </div>
                `).join('');
            }
        });
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
window.saveLanguage = function() {
    const code = document.getElementById('lang-code').value.trim();
    const name = document.getElementById('lang-name').value.trim();
    if(code.length!==2 || !name) return Toast.show('Invalid 2-letter code or name', 'warn');
    const fd = new URLSearchParams();
    fd.append('action', 'save_language'); fd.append('code', code); fd.append('name', name);
    fetch('cinemagic_editor_api.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(res=>{
            if(res.success) {
                document.getElementById('lang-code').value=''; document.getElementById('lang-name').value='';
                loadLanguages(); Toast.show('Language saved','success');
                setTimeout(()=>window.location.reload(), 1000);
            } else Toast.show(res.error || 'Failed','error');
        });
}
window.deleteLanguage = function(code) {
    if(!confirm('Delete this system language?')) return;
    const fd = new URLSearchParams();
    fd.append('action', 'delete_language'); fd.append('code', code);
    fetch('cinemagic_editor_api.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(res=>{
            if(res.success) { loadLanguages(); Toast.show('Language deleted','info'); setTimeout(()=>window.location.reload(), 1000); }
        });
}
</script>

<?php
echo $eruda ?? "";
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>