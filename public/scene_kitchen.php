<?php
// public/scene_kitchen_v2.php
// Scene Kitchen V2 -- New UI Port
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// 1. Fetch Interaction Groups
$stmt = $pdo->query("SELECT DISTINCT interaction_group FROM interactions WHERE active=1 ORDER BY interaction_group");
$interactionGroups = $stmt->fetchAll(\PDO::FETCH_COLUMN);

// 2. Fetch Sketch Categories (Directorial Groups)
try {
    $catStmt = $pdo->query("SELECT id, name FROM sketch_categories ORDER BY id ASC");
    $sketchCategories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sketchCategories = [];
}

// 3. Fetch Generators
$genStmt = $pdo->prepare("SELECT id, title FROM generator_config WHERE active=1 ORDER BY title");
$genStmt->execute();
$generators = $genStmt->fetchAll(\PDO::FETCH_ASSOC);

$pageTitle = 'Scene Kitchen';
ob_start();
?>
<!-- Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<style>
    :root {
        --bg: #0a0a0f;
        --card: #111118;
        --border: #1e1e2e;
        --text: #e2e2f0;
        --text-muted: #555570;
        --orange: #f97316;
        --orange-dim: rgba(249, 115, 22, 0.1);
        --green: #10b981;
        --red: #ef4444;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

    /* ── HEADER ── */
    .eh-header {
        flex-shrink: 0; padding: 0 16px; height: 50px;
        background: var(--card); border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--orange); display: flex; align-items: center; gap: 8px; }
    
    .config-btn { 
        background: transparent; border: 1px solid var(--border); color: var(--text-muted); 
        padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;
    }
    .config-btn:hover { border-color: var(--orange); color: var(--orange); }

    /* ── TOP PANEL: TABS & FILTERS ── */
    .eh-top-panel {
        flex-shrink: 0; display: flex; flex-direction: column;
        border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2);
        padding-bottom: 10px;
    }
    
    /* Tabs Scroll */
    .source-tabs {
        display: flex; gap: 8px; overflow-x: auto; padding: 10px 16px;
        scrollbar-width: none; -ms-overflow-style: none;
    }
    .source-tabs::-webkit-scrollbar { display: none; }
    
    .tab-btn {
        white-space: nowrap; padding: 6px 14px; border-radius: 20px;
        background: var(--card); border: 1px solid var(--border);
        color: var(--text-muted); font-size: 0.75rem; font-weight: 600; cursor: pointer;
        transition: all 0.2s;
    }
    .tab-btn:hover { border-color: var(--orange); color: var(--text); }
    .tab-btn.active { background: var(--orange); color: #000; border-color: var(--orange); }

    /* Controls Row */
    .controls-row {
        display: flex; gap: 10px; padding: 0 16px; align-items: center;
    }
    .search-input {
        flex: 1; padding: 8px 12px; border-radius: 4px; border: 1px solid var(--border);
        background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem;
    }
    .search-input:focus { outline: none; border-color: var(--orange); }
    
    .filter-select {
        padding: 8px 12px; border-radius: 4px; border: 1px solid var(--border);
        background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem;
        max-width: 200px; display: none; /* Hidden by default */
    }
    .filter-select:focus { outline: none; border-color: var(--orange); }

    /* ── GRID AREA ── */
    .eh-grid-area {
        flex: 1; overflow-y: auto; padding: 15px; position: relative;
        background: #000; min-height: 0; padding-bottom: 220px; /* Space for Pot */
    }
    .ingredients-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px;
    }
    
    /* Card Style */
    .ing-card {
        background: var(--card); border: 1px solid var(--border); border-radius: 6px;
        padding: 10px; position: relative; 
        /* Remove cursor grab from card body to allow scrolling on mobile */
        user-select: none;
        transition: transform 0.1s, border-color 0.2s;
        display: flex; flex-direction: column; gap: 6px; height: 100px;
    }
    .ing-card:hover { border-color: var(--orange); transform: translateY(-2px); }
    
    .ing-icon { font-size: 24px; line-height: 1; pointer-events: none; }
    .ing-label { font-size: 0.75rem; color: var(--text); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; line-height: 1.3; pointer-events: none; }
    
    /* Drag Handle - Made larger for touch */
    .ing-drag-handle {
        position: absolute; top: 4px; right: 4px; color: var(--text-muted); font-size: 14px;
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,0.05); border-radius: 4px; cursor: grab;
    }
    .ing-drag-handle:active { cursor: grabbing; color: var(--orange); background: var(--orange-dim); }

    /* ── THE POT (Bottom Drawer) ── */
    .pot-container {
        position: absolute; bottom: 0; left: 0; right: 0;
        background: var(--card); border-top: 1px solid var(--orange);
        height: 200px; display: flex; flex-direction: column;
        z-index: 50; transition: height 0.3s ease;
        box-shadow: 0 -5px 20px rgba(0,0,0,0.5);
    }
    .pot-container.expanded { height: 60vh; }
    
    .pot-header {
        padding: 8px 16px; background: rgba(249, 115, 22, 0.1); 
        display: flex; justify-content: space-between; align-items: center;
        border-bottom: 1px solid var(--border); cursor: pointer;
    }
    .pot-title { font-weight: 700; color: var(--orange); font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
    .pot-count { background: var(--orange); color: #000; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; }
    
    .pot-actions { display: flex; gap: 8px; }
    .icon-btn {
        width: 28px; height: 28px; border-radius: 4px; border: 1px solid var(--border);
        background: var(--bg); color: var(--text-muted); display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.2s;
    }
    .icon-btn:hover { border-color: var(--orange); color: var(--orange); }
    .icon-btn.active { background: var(--orange); color: #000; border-color: var(--orange); }

    .pot-body { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 10px; }
    
    /* Notes Area */
    .pot-notes { display: none; }
    .pot-notes textarea {
        width: 100%; height: 80px; background: #1a1a20; border: 1px solid var(--border);
        color: var(--text); padding: 8px; border-radius: 4px; font-family: inherit; font-size: 0.8rem;
        resize: none;
    }
    .pot-notes textarea:focus { outline: none; border-color: var(--orange); }

    /* Pot List (Drop Target) */
    .pot-list {
        flex: 1; border: 2px dashed var(--border); border-radius: 6px;
        padding: 8px; display: flex; flex-wrap: wrap; gap: 8px; align-content: flex-start;
        min-height: 60px; transition: background 0.2s;
    }
    .pot-list.drag-over { background: rgba(249, 115, 22, 0.05); border-color: var(--orange); }
    
    /* Pot Item (Mini Card) */
    .pot-list .ing-card {
        width: auto; height: auto; padding: 6px 10px; flex-direction: row; align-items: center; 
        background: var(--bg); border-color: var(--border); cursor: default;
    }
    .pot-list .ing-icon { font-size: 16px; }
    .pot-list .ing-label { font-size: 0.75rem; white-space: nowrap; -webkit-line-clamp: 1; }
    .pot-list .ing-drag-handle { display: none; }
    .pot-list .remove-btn {
        margin-left: 8px; color: var(--red); cursor: pointer; font-size: 14px; display: flex; align-items: center;
    }
    
    /* Cook Bar */
    .cook-bar {
        padding: 10px 16px; background: var(--bg); border-top: 1px solid var(--border);
        display: flex; gap: 10px;
    }
    .cook-btn {
        flex: 1; padding: 12px; background: var(--orange); color: #000; border: none;
        border-radius: 4px; font-weight: 700; text-transform: uppercase; cursor: pointer;
        display: flex; justify-content: center; align-items: center; gap: 8px; transition: filter 0.2s;
    }
    .cook-btn:hover { filter: brightness(1.1); }
    .cook-btn:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
    
    .auto-btn {
        width: 50px; background: var(--card); border: 1px solid var(--border); color: var(--text);
        border-radius: 4px; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
    .auto-btn:hover { border-color: var(--orange); color: var(--orange); }

    /* ── MODALS ── */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000;
        display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px);
    }
    .modal-content {
        background: var(--card); border: 1px solid var(--border); border-radius: 8px;
        width: 90%; max-width: 400px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .modal-title { font-size: 1rem; font-weight: 700; color: var(--orange); margin-bottom: 15px; }
    
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; }
    .form-select, .form-input {
        width: 100%; padding: 8px; background: var(--bg); border: 1px solid var(--border);
        color: var(--text); border-radius: 4px; font-family: inherit; font-size: 0.85rem;
    }
    .form-select:focus, .form-input:focus { outline: none; border-color: var(--orange); }
    
    .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
    .modal-btn { flex: 1; padding: 8px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 600; border: none; }
    .btn-cancel { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
    .btn-primary { background: var(--orange); color: #000; }

    /* Saved Pots List */
    .saved-pot-item {
        padding: 8px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background 0.1s;
    }
    .saved-pot-item:hover { background: rgba(255,255,255,0.05); }
    .pot-meta { font-size: 0.7rem; color: var(--text-muted); }
    .del-pot-btn { color: var(--red); background: transparent; border: none; cursor: pointer; padding: 4px; }

</style>

<div class="eh-layout">
    <!-- Header -->
    <div class="eh-header">
        <div class="eh-title"><span>🍳</span> SCENE KITCHEN</div>
        <button class="config-btn" onclick="$('#configModal').fadeIn(200)">⚙️ Config</button>
    </div>

    <!-- Top Panel: Sources -->
    <div class="eh-top-panel">
        <div class="source-tabs" id="groupTabs">
            <button class="tab-btn active" data-source="templates">Templates</button>
            <button class="tab-btn" data-source="interactions">Interactions</button>
            <button class="tab-btn" data-source="style_profiles">Styles</button>
            
            <div style="width:1px; background:var(--border); margin:0 4px;"></div>
            
            <button class="tab-btn" data-source="characters">Characters</button>
            <button class="tab-btn" data-source="locations">Locations</button>
            <button class="tab-btn" data-source="vehicles">Vehicles</button>
            <button class="tab-btn" data-source="artifacts">Artifacts</button>
            
            <div style="width:1px; background:var(--border); margin:0 4px;"></div>

            <button class="tab-btn" data-source="anivoc_expressions">Expr</button>
            <button class="tab-btn" data-source="anivoc_lighting">Light</button>
            <button class="tab-btn" data-source="anivoc_color_coding">Color</button>
            <button class="tab-btn" data-source="anivoc_motion_impact">Motion</button>
        </div>
        
        <div class="controls-row">
            <input type="text" id="ingredientSearch" class="search-input" placeholder="Search ingredients...">
            
            <!-- Filters (Visible based on tab) -->
            <select class="filter-select" id="filterTemplateCategory">
                <option value="">All Categories</option>
                <?php foreach($sketchCategories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <select class="filter-select" id="filterInteractionGroup">
                <option value="">All Groups</option>
                <?php foreach($interactionGroups as $g): ?>
                    <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Grid Area -->
    <div class="eh-grid-area">
        <div class="ingredients-grid" id="sourceContainer">
            <div style="grid-column:1/-1; text-align:center; color:var(--text-muted); padding:20px;">Loading...</div>
        </div>
    </div>

    <!-- The Pot (Bottom Drawer) -->
    <div class="pot-container">
        <div class="pot-header" onclick="$('.pot-container').toggleClass('expanded')">
            <div class="pot-title">
                <span>🫕 The Pot</span>
                <span class="pot-count" id="potCount">0</span>
            </div>
            <div class="pot-actions" onclick="event.stopPropagation()">
                <button class="icon-btn" id="btnToggleNotes" onclick="toggleNotes()" title="Notes"><i class="bi bi-pencil-square"></i></button>
                <button class="icon-btn" onclick="openSaveModal()" title="Save"><i class="bi bi-save"></i></button>
                <button class="icon-btn" onclick="openLoadModal()" title="Load"><i class="bi bi-folder2-open"></i></button>
            </div>
        </div>
        
        <div class="pot-body">
            <div class="pot-notes" id="potNotesArea">
                <textarea id="chefNotes" placeholder="Add specific instructions or notes for the AI here..."></textarea>
            </div>
            <div class="pot-list" id="potList">
                <div style="width:100%; text-align:center; color:var(--text-muted); font-size:0.8rem; padding-top:10px;">Drag ingredients here</div>
            </div>
        </div>
        
        <div class="cook-bar">
            <button class="auto-btn" title="Randomize" onclick="autoPilot()"><i class="bi bi-dice-5"></i></button>
            <button class="cook-btn" onclick="cookRecipe()"><i class="bi bi-fire"></i> COOK SCENE</button>
        </div>
    </div>
</div>

<!-- Config Modal -->
<div class="modal-overlay" id="configModal">
    <div class="modal-content">
        <div class="modal-title">Kitchen Settings</div>
        <div class="form-group">
            <label>Description Generator</label>
            <select id="descGen" class="form-select">
                <?php foreach($generators as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Name Generator</label>
            <select id="nameGen" class="form-select">
                <?php foreach($generators as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="modal-actions">
            <button class="modal-btn btn-primary" onclick="$('#configModal').fadeOut(200)">Done</button>
        </div>
    </div>
</div>

<!-- Save Modal -->
<div class="modal-overlay" id="savePotModal">
    <div class="modal-content">
        <div class="modal-title">Save Recipe</div>
        <input type="text" id="potNameInput" class="form-input" placeholder="Recipe Name">
        <div class="modal-actions">
            <button class="modal-btn btn-cancel" onclick="$('#savePotModal').fadeOut(200)">Cancel</button>
            <button class="modal-btn btn-primary" onclick="savePot()">Save</button>
        </div>
    </div>
</div>

<!-- Load Modal -->
<div class="modal-overlay" id="loadPotModal">
    <div class="modal-content" style="max-height:80vh; display:flex; flex-direction:column;">
        <div class="modal-title">Load Recipe</div>
        <div id="savedPotsList" style="flex:1; overflow-y:auto; border:1px solid var(--border); border-radius:4px; margin-bottom:10px;"></div>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <button class="config-btn" onclick="changeLoadPage(-1)">Prev</button>
            <span id="pageIndicator" style="font-size:0.75rem; color:var(--text-muted);">Page 1</span>
            <button class="config-btn" onclick="changeLoadPage(1)">Next</button>
        </div>
        
        <button class="modal-btn btn-cancel" onclick="$('#loadPotModal').fadeOut(200)">Close</button>
    </div>
</div>

<script>
let currentLoadPage = 1;

$(function() {
    // 1. Config Persistence
    const savedDesc = localStorage.getItem('sk_desc_gen');
    const savedName = localStorage.getItem('sk_name_gen');
    if (savedDesc && $(`#descGen option[value="${savedDesc}"]`).length) $('#descGen').val(savedDesc);
    if (savedName && $(`#nameGen option[value="${savedName}"]`).length) $('#nameGen').val(savedName);

    $('#descGen').change(function(){ localStorage.setItem('sk_desc_gen', $(this).val()); });
    $('#nameGen').change(function(){ localStorage.setItem('sk_name_gen', $(this).val()); });

    // 2. Drag & Drop Initialization
    const sourceEl = document.getElementById('sourceContainer');
    const potEl = document.getElementById('potList');

    new Sortable(sourceEl, {
        group: { name: 'ingredients', pull: 'clone', put: false },
        sort: false, animation: 150,
        handle: '.ing-drag-handle', // RESTRICT DRAG TO HANDLE
        onStart: function() { $('.pot-container').addClass('expanded'); }
    });

    new Sortable(potEl, {
        group: 'ingredients', animation: 150,
        onAdd: function(evt) {
            updatePotCount();
            const el = evt.item;
            // Add remove button if missing
            if (!el.querySelector('.remove-btn')) {
                const btn = document.createElement('i');
                btn.className = 'bi bi-x-lg remove-btn';
                btn.onclick = function(e) { e.stopPropagation(); el.remove(); updatePotCount(); };
                el.appendChild(btn);
            }
        }
    });

    // 3. Tab Switching
    $('.tab-btn').click(function() {
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        
        const source = $(this).data('source');
        
        // Toggle Filters
        $('.filter-select').hide();
        if (source === 'templates') $('#filterTemplateCategory').show();
        if (source === 'interactions') $('#filterInteractionGroup').show();
        
        $('#ingredientSearch').val('');
        loadIngredients(source);
    });

    // 4. Filters Change
    $('#filterTemplateCategory').change(function() { loadIngredients('templates', { category_id: $(this).val() }); });
    $('#filterInteractionGroup').change(function() { loadIngredients('interactions', { group: $(this).val() }); });

    // 5. Search
    $('#ingredientSearch').on('keyup input', function() {
        const term = $(this).val().toLowerCase();
        $('#sourceContainer .ing-card').each(function() {
            const keys = ($(this).data('keywords') || '').toLowerCase();
            const label = $(this).find('.ing-label').text().toLowerCase();
            $(this).toggle(label.includes(term) || keys.includes(term));
        });
    });

    // Initial Load
    loadIngredients('templates');

    // ── FUNCTIONS ──

    function loadIngredients(type, filters = {}) {
        $('#sourceContainer').html('<div style="grid-column:1/-1;text-align:center;color:#666;padding:20px;">Loading...</div>');
        
        $.post('/api/kitchen_ajax.php', { 
            action: 'fetch_ingredients', type: type, filters: filters 
        }, function(res) {
            if(res.ok) {
                let html = '';
                if(res.data.length === 0) {
                    html = '<div style="grid-column:1/-1;text-align:center;color:#666;padding:20px;">No ingredients found.</div>';
                } else {
                    res.data.forEach(item => {
                        let keywords = item.type;
                        if(item.data) {
                            if(item.data.core_idea) keywords += ' ' + item.data.core_idea;
                            if(item.data.description) keywords += ' ' + item.data.description;
                            if(item.data.example_prompt) keywords += ' ' + item.data.example_prompt;
                        }
                        keywords = keywords.replace(/"/g, '&quot;');

                        html += `
                            <div class="ing-card" data-type="${item.type}" data-id="${item.id}" data-keywords="${keywords}">
                                <div class="ing-drag-handle"><i class="bi bi-grip-vertical"></i></div>
                                <span class="ing-icon">${item.icon}</span>
                                <div class="ing-label">${item.label}</div>
                            </div>
                        `;
                    });
                }
                $('#sourceContainer').html(html);
            } else {
                $('#sourceContainer').html('<div style="grid-column:1/-1;text-align:center;color:var(--red);">Error loading data.</div>');
            }
        }, 'json');
    }

    window.toggleNotes = function() {
        const area = $('.pot-notes');
        const btn = $('#btnToggleNotes');
        if (area.is(':visible')) {
            area.slideUp('fast'); btn.removeClass('active');
        } else {
            $('.pot-container').addClass('expanded');
            area.slideDown('fast'); btn.addClass('active');
            $('#chefNotes').focus();
        }
    };

    function updatePotCount() {
        const count = $('#potList .ing-card').length;
        $('#potCount').text(count);
        if(count > 0 && $('#potList').text().includes('Drag ingredients')) {
            // Remove placeholder if items exist
            $('#potList').contents().filter(function(){ return this.nodeType === 3; }).remove();
            $('#potList div:contains("Drag ingredients")').remove();
        }
    }

    function collectIngredients() {
        const ingredients = [];
        $('#potList .ing-card').each(function() {
            ingredients.push({ type: $(this).data('type'), id: $(this).data('id') });
        });
        return ingredients;
    }

    // Cook
    window.cookRecipe = function() {
        const ingredients = collectIngredients();
        const notes = $('#chefNotes').val().trim();
        if (ingredients.length === 0 && notes === '') { Toast.show("Pot is empty!", "error"); return; }
        
        const btn = $('.cook-btn'); const orig = btn.html();
        btn.html('<div class="spinner" style="width:16px;height:16px;"></div> Cooking...').prop('disabled', true);
        
        $.post('/api/kitchen_ajax.php', {
            action: 'cook', ingredients: ingredients, custom_instruction: notes,
            desc_gen_id: $('#descGen').val(), name_gen_id: $('#nameGen').val()
        }, function(res) {
            btn.html(orig).prop('disabled', false);
            if(res.ok) Toast.show("Cooked! Sketch #" + res.sketch_id, "success");
            else Toast.show("Error: " + res.error, "error");
        }, 'json');
    };

    // Auto Pilot
    window.autoPilot = function() {
        if(!confirm("Replace current pot with random recipe?")) return;
        $('#potList').empty(); $('#chefNotes').val('');
        $.post('/api/kitchen_ajax.php', { action: 'auto_recipe' }, function(res) {
            if(res.ok && res.ingredients) { 
                res.ingredients.forEach(item => addToPot(item));
                updatePotCount();
                Toast.show("Randomized!", "success"); 
            }
        }, 'json');
    };

    function addToPot(item) {
        const el = $(`
            <div class="ing-card" data-type="${item.type}" data-id="${item.id}">
                <span class="ing-icon">${item.icon}</span>
                <div class="ing-label">${item.label}</div>
                <i class="bi bi-x-lg remove-btn" onclick="this.parentElement.remove(); updatePotCount()"></i>
            </div>
        `);
        $('#potList').append(el);
    }

    // Save/Load
    window.openSaveModal = function() {
        if(collectIngredients().length === 0 && $('#chefNotes').val().trim() === '') { Toast.show("Nothing to save!", "error"); return; }
        $('#savePotModal').fadeIn(200); $('#potNameInput').focus();
    };

    window.savePot = function() {
        const name = $('#potNameInput').val().trim();
        if(!name) { alert("Enter a name"); return; }
        $.post('/api/kitchen_ajax.php', { 
            action: 'save_pot', name: name, 
            ingredients: collectIngredients(), notes: $('#chefNotes').val().trim() 
        }, function(res) {
            if(res.ok) { Toast.show("Saved!", "success"); $('#savePotModal').fadeOut(); $('#potNameInput').val(''); }
            else Toast.show("Error: " + res.error, "error");
        }, 'json');
    };

    window.openLoadModal = function() { $('#loadPotModal').fadeIn(200); currentLoadPage = 1; fetchSavedPots(); };
    window.changeLoadPage = function(d) { currentLoadPage += d; if(currentLoadPage < 1) currentLoadPage = 1; fetchSavedPots(); };

    function fetchSavedPots() {
        $('#savedPotsList').html('<div style="padding:10px;text-align:center;">Loading...</div>');
        $.post('/api/kitchen_ajax.php', { action: 'list_pots', page: currentLoadPage }, function(res) {
            if(res.ok) {
                let html = '';
                if(res.rows.length === 0) html = '<div style="padding:10px;text-align:center;">No saved pots.</div>';
                else {
                    res.rows.forEach(row => {
                        html += `
                            <div class="saved-pot-item">
                                <div onclick="loadPot(${row.id})" style="flex:1;">
                                    <div style="font-weight:600; color:var(--text);">${row.name} ${row.notes ? '📝' : ''}</div>
                                    <div class="pot-meta">${row.created_at}</div>
                                </div>
                                <button class="del-pot-btn" onclick="deletePot(${row.id})"><i class="bi bi-trash"></i></button>
                            </div>
                        `;
                    });
                }
                $('#savedPotsList').html(html);
                $('#pageIndicator').text(`Page ${res.page} / ${res.pages || 1}`);
            }
        }, 'json');
    }

    window.loadPot = function(id) {
        if(!confirm("Load this recipe? Current pot will be cleared.")) return;
        $.post('/api/kitchen_ajax.php', { action: 'load_pot', id: id }, function(res) {
            if(res.ok) {
                $('#potList').empty();
                res.ingredients.forEach(item => addToPot(item));
                updatePotCount();
                $('#chefNotes').val(res.notes || '');
                if (res.notes && !$('#potNotesArea').is(':visible')) toggleNotes();
                $('#loadPotModal').fadeOut();
                Toast.show("Loaded!", "success");
            }
        }, 'json');
    };

    window.deletePot = function(id) {
        if(!confirm("Delete this saved pot?")) return;
        $.post('/api/kitchen_ajax.php', { action: 'delete_pot', id: id }, function() { fetchSavedPots(); }, 'json');
    };
});
</script>
<?php
$content = ob_get_clean();
// Updated template to gallery.php
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/gallery.php');
?>