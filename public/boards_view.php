<?php
// public/boards_view.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\ModuleRegistry;

// 1. Setup UI Modules
$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'character_poses', 'character_expressions', 'character_anima_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites', 'frames', 'videos'];

$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '1.5em',
    'show_for_entities' => $entities_with_menu,
]);
foreach ($entities_with_menu as $entity_name) { $gearMenu->addStandardActions($entity_name); }
$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// 2. Fetch Boards
$boardsStmt = $pdo->query("SELECT id, name FROM boards WHERE status='active' ORDER BY name ASC");
$boards = $boardsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.8">
    <title>Production Boards 🎬</title>
    
    <script>
      (function() {
        try {
          var theme = localStorage.getItem('spw_theme');
          
          
         if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
          
        } catch (e) {}
      })();
    </script>

    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    
    <!-- VideoJS -->
    <link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet" />
    <script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>

    <style>
        body { background: var(--bg); color: var(--text); padding-bottom: 100px; }
        
        /* Header */
        .board-header {
            position: sticky; top: 0; z-index: 100;
            background: var(--card); border-bottom: 1px solid var(--border);
            padding: 15px 20px;
            display: flex; gap: 15px; align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .board-info { flex: 1; display: flex; align-items: center; gap: 10px; overflow: hidden; }
        .board-title { margin: 0; font-size: 1.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .board-subtitle { color: var(--text-muted); font-size: 0.9rem; }

        .board-actions { display: flex; gap: 10px; flex-shrink: 0; }
        .btn-action { 
            padding: 8px 12px; border-radius: 6px; background: var(--accent); color: white; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-action:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-action.secondary { background: var(--bg-lighter); color: var(--text); border: 1px solid var(--border); }
        .btn-tree { font-size: 1.2rem; padding: 4px 10px; }

        /* Board Content */
        #board-content { padding: 20px; max-width: 1600px; margin: 0 auto; }
        .run-container { margin-bottom: 40px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        
        .run-container.storyboard-container { background: rgba(255, 240, 200, 0.05); border-radius: 8px; padding: 10px; }
        .run-container.entity-container { background: rgba(200, 240, 255, 0.05); border-radius: 8px; padding: 10px; }
        
        .run-meta { display: flex; justify-content: space-between; margin-bottom: 10px; align-items: center; }
        .run-title { font-size: 14px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        
        .scroll-magic-link { color: var(--text-muted); font-size: 1.2rem; cursor: pointer; margin-left: 10px; }
        .scroll-magic-link:hover { color: var(--accent); }

        /* Swiper / Cards */
        .frame-chain-swiper { width: 100%; padding: 10px 0; }
        .swiper-slide { width: 300px; display: flex; align-items: center; position: relative; }
        .swiper-slide:not(:last-child)::after { content: '→'; font-size: 24px; color: var(--text-muted); position: absolute; right: -25px; top: 50%; transform: translateY(-50%); z-index: 1; }

        .chain-card { 
            background: var(--card); border: 1px solid var(--border); border-radius: 8px; 
            overflow: visible !important;
            box-shadow: var(--card-elevation); width: 100%; display: flex; flex-direction: column; 
            transition: transform 0.2s; position: relative; 
        }
        .chain-card:hover { transform: translateY(-4px); }
        .chain-card-thumbnail { 
            position: relative; width: 100%; padding-top: 100%; background: var(--bg); 
            border-top-left-radius: 8px; border-top-right-radius: 8px; 
            overflow: hidden;
        }
        .chain-card-thumbnail img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .chain-card-body { padding: 12px; flex-grow: 1; font-size: 13px; }
        .chain-card-title { font-weight: 600; color: var(--text); margin: 0 0 8px 0; font-size: 15px; }
        
        /* Badges */
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; margin-right: 4px; margin-bottom: 4px; border: 1px solid transparent; }
        .badge-gray { background: rgba(100,100,100,0.1); color: var(--text-muted); border-color: var(--border); }
        .badge-blue { background: rgba(59,130,246,0.1); color: #3b82f6; border-color: rgba(59,130,246,0.2); }
        .badge-orange { background: rgba(245,159,11,0.1); color: #f59e0b; border-color: rgba(245,159,11,0.2); }
        .badge-meta { 
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(168,85,247,0.1)); 
            color: #8b5cf6; 
            border: 1px solid rgba(139,92,246,0.3); 
            cursor: pointer; 
        }
        
        .chain-card-prompt { color: var(--text-muted); margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer; }
        
        .empty-board { text-align: center; padding: 50px; color: var(--text-muted); font-size: 18px; }
        
        /* --- Video Player Styles --- */
        .video-run-grid {
            display: flex;
            flex-direction: row; 
            gap: 15px;
            background: rgba(0,0,0,0.02);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            height: 450px;
            overflow: hidden;
        }

        .video-player-box {
            flex: 0 0 65%;
            background: #000;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .video-js { width: 100%; height: 100%; }
        .video-js video { object-fit: contain; }

        .video-playlist-strip {
            flex: 1;
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            align-content: flex-start;
            gap: 10px;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 5px; 
        }
        
        .video-thumb-card {
            flex: 0 0 160px;
            display: flex;
            flex-direction: column;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
            height: 140px;
        }
        .video-thumb-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .video-thumb-card.active { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59,130,246,0.3); }
        
        .vt-img { width: 100%; height: 90px; object-fit: cover; background: #000; }
        .vt-info { padding: 6px; font-size: 12px; }
        .vt-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
        .vt-dur { color: var(--text-muted); font-size: 10px; }

        /* --- Video Details Button --- */
        .btn-vid-details {
            padding: 3px 8px;
            font-size: 11px;
            border-radius: 4px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            margin-left: 8px;
            white-space: nowrap;
            transition: border-color 0.15s, color 0.15s;
            line-height: 1.4;
        }
        .btn-vid-details:hover { border-color: var(--accent); color: var(--accent); }

        /* --- Slim Reference Card Styles (docs, fuzz, narrative sequences, graphs) --- */
        .ref-scroll-container {
            display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; flex-wrap: wrap;
        }
        .ref-card {
            display: flex; flex-direction: column; justify-content: center;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 10px;
            min-width: 120px; max-width: 200px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text);
            transition: border-color 0.15s, transform 0.15s;
            position: relative;
        }
        .ref-card:hover { border-color: var(--accent); transform: translateY(-2px); color: var(--text); }
        .ref-card-top { display: flex; align-items: center; gap: 6px; }
        .ref-card-label { font-weight: 600; font-size: 0.82rem; white-space: nowrap; }
        .ref-card-icon { color: var(--text-muted); font-size: 0.75rem; flex-shrink: 0; }
        .ref-card-name { font-size: 0.68rem; color: var(--text-muted); margin-top: 3px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .ref-card-remove { position: absolute; top: 4px; right: 5px; font-size: 0.65rem; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 1px 2px; }
        .ref-card-remove:hover { color: #ef4444; }

        @media (max-width: 768px) {
            .video-run-grid { height: 280px; gap: 8px; padding: 8px; }
            .video-player-box { flex: 0 0 60%; }
            .video-thumb-card { flex: 0 0 110px; max-height: 120px; }
            .vt-img { height: 70px; }
        }
        
        /* Modals */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-box { background: var(--card); padding: 20px; border-radius: 10px; width: 300px; border: 1px solid var(--border); max-width: 90%; }
        .modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
        .modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: var(--text-muted); }
        .modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; align-items: flex-start; }
        .modal-icon { font-size: 1.5em; margin-right: 12px; min-width: 30px; text-align: center; }
        .modal-info { flex: 1; }
        .modal-label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; display: block; }
        
        /* Tree Specific */
        .tree-modal-box { width: 400px; height: 500px; display: flex; flex-direction: column; }
        #tree-container { flex: 1; overflow-y: auto; margin-top: 10px; border: 1px solid var(--border); border-radius: 4px; padding: 10px; background: var(--bg); }
        .jstree-default { color: var(--text); }
        .jstree-default .jstree-hovered { background: rgba(59,130,246,0.1) !important; color: var(--text) !important; }
        .jstree-default .jstree-clicked { background: rgba(59,130,246,0.2) !important; color: var(--accent) !important; }
        .jstree-default .jstree-anchor { line-height: 32px; height: 32px; font-size: 1.1rem; }
        .jstree-default .jstree-icon { width: 32px; height: 32px; line-height: 32px; font-size: 1.5rem; }
        .jstree-default .jstree-node { margin-bottom: 4px; }
        
        /* Gear Menu Absolute Positioning Fix */
        .sb-menu { z-index: 9999 !important; right: 8px !important; top: 8px !important; }
        .gear-menu-btn { position: absolute; top: 8px; right: 8px; z-index: 50; cursor: pointer; background: rgba(255,255,255,0.9); border-radius: 4px; padding: 4px; line-height: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        [data-theme="dark"] .gear-menu-btn { background: rgba(0,0,0,0.6); color: #fff; }

/*
.sb-menu { position: fixed !important; transform: none !important; }
*/

.sb-menu { position: fixed !important; transform: translate(50px,250px) !important; }

        /* DnD checkbox in tree modal */
        .tree-dnd-toggle { display: flex; align-items: center; gap: 5px; font-size: 0.8rem; color: var(--text-muted); cursor: pointer; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); white-space: nowrap; user-select: none; }
        .tree-dnd-toggle input[type="checkbox"] { cursor: pointer; accent-color: var(--accent); }
        .tree-dnd-toggle:has(input:checked) { border-color: var(--accent); color: var(--accent); }

        /* Frame View / Mini Graph Modal */
        .view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
        .view-modal.active { display: flex; }
        .view-modal-content { width: 95vw; height: 95vh; background: var(--bg); position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); border-radius: 8px; }
        .view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid var(--border); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 24px; z-index: 200; transition: all 0.2s; }
        .view-close:hover { background: #fff; color: #000; }
        iframe.frame-viewer { width: 100%; height: 100%; border: none; border-radius: 8px; }

    </style>
</head>
<body>

<div class="board-header">
    <div class="board-info">
        <button class="btn-action secondary btn-tree" onclick="openTreeModal()" title="Open Board Tree">🌳</button>
        <div>
            <h2 class="board-title" id="headerBoardTitle">Boards</h2>
            <div class="board-subtitle" id="headerBoardSub">Select a board from the tree</div>
        </div>
    </div>

    <div class="board-actions">
        <button id="themeToggle" class="btn-action secondary" title="Toggle theme"><span id="themeIcon">🌙</span></button>
        <button class="btn-action" onclick="showAddItem()" id="btnAddItem" style="display:none;">+ Add Item</button>
        <input type="hidden" id="currentBoardId">
        <input type="hidden" id="currentCategoryId"> 
    </div>
</div>

<div id="board-content">
    <div class="empty-board">Click 🌳 to open the board tree.</div>
</div>

<!-- Pagination UI -->
<div id="pagination-container" style="display:none; justify-content:center; align-items:center; gap:10px; padding: 20px;">
    <button class="btn-action secondary" id="btnPrevPage">Previous</button>
    <input type="number" id="inputPageIndex" style="width: 60px; text-align: center; padding: 5px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text);" min="1" value="1">
    <span id="pageCountDisplay" style="color: var(--text-muted); font-size: 0.9rem;">of 1</span>
    <button class="btn-action secondary" id="btnNextPage">Next</button>
</div>

<!-- Tree Modal -->
<div class="modal-overlay" id="treeModal">
    <div class="modal-box tree-modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3 style="margin:0;">Board Navigation</h3>
            <span class="modal-close" style="position:static;" onclick="$('#treeModal').hide()">&times;</span>
        </div>
        
        <div style="display:flex; gap:5px; margin-bottom:5px; flex-wrap:wrap; align-items:center;">
            <button class="btn-action secondary btn-sm" onclick="showCreateCategory()">+ Folder</button>
            <button class="btn-action secondary btn-sm" onclick="showCreateBoard()">+ Board</button>
            <button class="btn-action secondary btn-sm" onclick="refreshTree()" title="Refresh">↻</button>
            <label class="tree-dnd-toggle" id="dndToggleLabel">
                <input type="checkbox" id="treeDndCheckbox" onchange="applyTreeDnd(this.checked)">
                Drag &amp; Drop
            </label>
        </div>
        
        <div id="tree-container">Loading...</div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal-box">
        <h3>Add Item</h3>
        <select id="itemTypeSelect" style="width:100%; padding:8px; margin-bottom:10px; border-radius:6px; border:1px solid var(--border); background:var(--bg); color:var(--text);">
            <option value="map_run">Map Run</option>
            <option value="storyboard">Storyboard</option>
            <option value="md_doc">Document</option>
            <option value="fuzz_candidate">Fuzz Candidate</option>
            <option value="narrative_sequence">Narrative Sequence</option>
            <option value="kg_node">Knowledge Graph Node (KG)</option>
            <option value="ag_node">Auto Graph Node (AG)</option>
            <!-- ENTITIES -->
            <option value="characters">Character</option>
            <option value="locations">Location</option>
            <option value="backgrounds">Background</option>
            <option value="animas">Anima</option>
            <option value="vehicles">Vehicle</option>
            <option value="artifacts">Artifact</option>
            <option value="sketches">Sketch</option>
            <option value="generatives">Generative</option>
            <option value="composites">Composite</option>
        </select>
        <input type="number" id="inputItemId" placeholder="ID (e.g. Map Run ID or Doc ID)" style="width:100%; padding:8px; margin-bottom:10px;">
        <div style="display:flex; gap:10px;">
            <button class="btn-action secondary" onclick="$('#addItemModal').hide()" style="flex:1;">Cancel</button>
            <button class="btn-action" onclick="addItemToBoard()" style="flex:1;">Add</button>
        </div>
    </div>
</div>

<!-- Create Board Modal -->
<div class="modal-overlay" id="createBoardModal">
    <div class="modal-box">
        <h3>New Board</h3>
        <p style="font-size:12px; color:#888; margin-top:-10px;">Creating inside selected folder</p>
        <input type="text" id="inputBoardName" placeholder="Board Name" style="width:100%; padding:8px; margin-bottom:10px;">
        <div style="display:flex; gap:10px;">
            <button class="btn-action secondary" onclick="$('#createBoardModal').hide()" style="flex:1;">Cancel</button>
            <button class="btn-action" onclick="createBoard()" style="flex:1;">Create</button>
        </div>
    </div>
</div>

<!-- Create Category Modal -->
<div class="modal-overlay" id="createCatModal">
    <div class="modal-box">
        <h3>New Folder</h3>
        <input type="text" id="inputCatName" placeholder="Folder Name" style="width:100%; padding:8px; margin-bottom:10px;">
        <div style="display:flex; gap:10px;">
            <button class="btn-action secondary" onclick="$('#createCatModal').hide()" style="flex:1;">Cancel</button>
            <button class="btn-action" onclick="createCategory()" style="flex:1;">Create</button>
        </div>
    </div>
</div>

<!-- Meta Info Modal -->
<div id="meta-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close" onclick="$('#meta-modal').hide()">&times;</span>
        <h3 class="modal-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Ingredients</h3>
        <div id="meta-modal-body"></div>
    </div>
</div>

<!-- Full Description Modal -->
<div id="desc-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span>
        <h3 class="modal-title" style="margin-top:0;">Description</h3>
        <div id="desc-modal-body" style="white-space: pre-wrap; font-family: monospace; font-size:0.9rem;"></div>
    </div>
</div>

<!-- Frame View / Mini Graph Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeIframeModal()">&times;</div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<!-- UI Components -->
<?= $eruda ?? '' ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>

<!-- PhotoSwipe Modules -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.initLightbox = () => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery',
            children: '.pswp-gallery-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    };
</script>

<script>
$(function() {
    // Theme toggle UI wiring
    function updateThemeButton() {
        const t = document.documentElement.getAttribute('data-theme') || 'light';
        document.getElementById('themeIcon').textContent = t === 'dark' ? '☀️' : '🌙';
    }
    document.getElementById('themeToggle').addEventListener('click', function(){
        const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = (cur === 'dark') ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('spw_theme', next); } catch(e){}
        updateThemeButton();
    });
    updateThemeButton();

    // --- Robust attach helper ---
    function attachGearMenuSafely(context) {
        if (!context) return;
        if (!window.GearMenu || typeof window.GearMenu.attach !== 'function') {
            setTimeout(function(){ attachGearMenuSafely(context); }, 120);
            return;
        }
        try { window.GearMenu.attach(context); } 
        catch (e) { try { window.GearMenu.attach(document); } catch(ignore) {} }
    }
    window.attachGearMenu = function() { attachGearMenuSafely(document.getElementById('board-content')); };

    // --- Iframe Modal ---
    window.openIframeModal = function(url) {
        document.getElementById('frameViewer').src = url;
        document.getElementById('viewModal').classList.add('active');
    };
    window.closeIframeModal = function() {
        document.getElementById('viewModal').classList.remove('active');
        setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
    };

    // --- Video Details Modal ---
    window.openVideoDetails = function(videoId) {
        const url = 'view_video_details.php?id=' + videoId;
        const iframe = document.getElementById('frameDetailsIframe');
        const modal = document.getElementById('frameDetailsModal');
        const loader = document.getElementById('ieLoadingOverlay');

        if (iframe && modal) {
            if (loader) {
                loader.style.display = 'flex';
                const p = loader.querySelector('p');
                if (p) p.textContent = 'Loading Video Details...';
            }
            iframe.src = url;
            modal.style.display = 'flex';

            iframe.onload = function() {
                if (loader) loader.style.display = 'none';
            };
        } else {
            window.open(url, '_blank');
        }
    };

    // --- Video Logic ---
    window.changeVideo = function(runId, url, videoId, cardEl) {
        const playerId = 'player-' + runId;
        const playerEl = document.getElementById(playerId);
        
        $(cardEl).siblings().removeClass('active');
        $(cardEl).addClass('active');

        if (playerEl) {
            if(videojs.getPlayer(playerId)) {
                const vjsPlayer = videojs.getPlayer(playerId);
                vjsPlayer.src({type: 'video/mp4', src: url});
                vjsPlayer.play();
            } else {
                playerEl.src = url;
                playerEl.play();
            }
        }

        // Update the Video Details button for this run
        const detailsBtn = document.getElementById('vid-details-btn-' + runId);
        if (detailsBtn) {
            detailsBtn.dataset.vidId = videoId;
        }
    };

    // --- RESTORED MODAL OPENERS with Z-INDEX FIX ---
    window.showCreateBoard = function() {
        $('#createBoardModal').css('z-index', '2010').css('display', 'flex');
        $('#inputBoardName').focus();
    };

    window.showCreateCategory = function() {
        $('#createCatModal').css('z-index', '2010').css('display', 'flex');
        $('#inputCatName').focus();
    };

    // --- DnD checkbox persistence ---
    const TREE_DND_KEY = 'boards_tree_dnd_enabled';

    function loadDndState() {
        try {
            return localStorage.getItem(TREE_DND_KEY) === 'true';
        } catch(e) { return false; }
    }

    function saveDndState(enabled) {
        try { localStorage.setItem(TREE_DND_KEY, enabled ? 'true' : 'false'); } catch(e) {}
    }

    window.applyTreeDnd = function(enabled) {
        saveDndState(enabled);
        const $tree = $('#tree-container');
        if ($tree.jstree(true)) {
            $tree.jstree('destroy');
        }
        treeInitialized = false;
        initTree(enabled);
        treeInitialized = true;
    };

    // --- Tree Logic ---
    let treeInitialized = false;

    window.openTreeModal = function() {
        // Restore DnD checkbox state from localStorage before showing
        const dndEnabled = loadDndState();
        document.getElementById('treeDndCheckbox').checked = dndEnabled;

        $('#treeModal').css('display', 'flex');
        if (!treeInitialized) {
            initTree(dndEnabled);
            treeInitialized = true;
        }
    };

    window.refreshTree = function() {
        if($('#tree-container').jstree(true)) {
            $('#tree-container').jstree('refresh');
        } else {
            initTree(loadDndState());
        }
    };

    function initTree(dndEnabled) {
        const plugins = ['search', 'types', 'state'];
        if (dndEnabled) plugins.push('dnd');

        $('#tree-container').jstree({
            'core': {
                'data': {
                    'url': 'boards_api.php?action=fetch_tree',
                    'dataType': 'json',
                    'dataFilter': function(raw) {
                        const json = JSON.parse(raw);
                        if(json.ok) return JSON.stringify(json.tree);
                        return '[]';
                    }
                },
                'themes': { 'name': 'default', 'dots': true, 'icons': true },
                'check_callback': true 
            },
            'plugins': plugins,
            'types': {
                'folder': { 'icon': 'bi bi-folder2' },
                'board': { 'icon': 'bi bi-kanban' }
            }
        }).on('select_node.jstree', function(e, data) {
            if (data.event) {
                const node = data.node;
                if (node.type === 'board') {
                    const boardId = node.data.db_id;
                    const boardName = node.text;
                    $('#currentBoardId').val(boardId);
                    loadBoard(boardId, boardName, 1);
                    $('#treeModal').hide();
                } else {
                    $('#currentCategoryId').val(node.data.db_id);
                }
            }
        }).on('move_node.jstree', function(e, data) {
            $.post('boards_api.php', {
                action: 'move_node',
                id: data.node.id,
                parent: data.parent
            }).fail(function() {
                data.instance.refresh(); 
                Toast.show('Move failed', 'error');
            });
        });
    }

    window.loadBoard = function(id, name = null, page = 1) {
        const newUrl = window.location.pathname + '?board_id=' + id + '&page=' + page;
        window.history.pushState({path:newUrl}, '', newUrl);
        
        $('#board-content').html('<div class="empty-board">Loading...</div>');
        $('#btnAddItem').show();
        $('#currentBoardId').val(id); 

        $.post('boards_api.php', { action: 'fetch_board_content', board_id: id, page: page }, function(res) {
            if(res.ok) {
                $('#headerBoardTitle').text(res.board_name || name || 'Board #' + id);
                
                let html = '';
                
                // 1. Render Documents
                if(res.docs && res.docs.length > 0) {
                    html += '<div class="run-container"><div class="run-meta"><span class="run-title">DOCUMENTS</span></div><div class="ref-scroll-container">';
                    res.docs.forEach(function(doc) {
                        html += '<a href="view_md.php?id=' + doc.id + '" target="_blank" class="ref-card">'
                            + '<span class="ref-card-remove" onclick="event.preventDefault(); event.stopPropagation(); removeItem(' + doc.link_id + ')">✕</span>'
                            + '<div class="ref-card-top"><span class="ref-card-label">doc ' + doc.id + '</span><i class="bi bi-box-arrow-up-right ref-card-icon"></i></div>'
                            + (doc.name ? '<div class="ref-card-name" title="' + doc.name.replace(/"/g, '&quot;') + '">' + doc.name + '</div>' : '')
                            + '</a>';
                    });
                    html += '</div></div>';
                }

                // 2. Render Fuzz Candidates
                if(res.fuzz_candidates && res.fuzz_candidates.length > 0) {
                    html += '<div class="run-container"><div class="run-meta"><span class="run-title">FUZZ CANDIDATES</span></div><div class="ref-scroll-container">';
                    res.fuzz_candidates.forEach(function(fc) {
                        html += '<a href="/fuzz_forge_landing.php?id=' + fc.id + '" target="_blank" class="ref-card">'
                            + '<span class="ref-card-remove" onclick="event.preventDefault(); event.stopPropagation(); removeItem(' + fc.link_id + ')">✕</span>'
                            + '<div class="ref-card-top"><span class="ref-card-label">fuzz ' + fc.id + '</span><i class="bi bi-box-arrow-up-right ref-card-icon"></i></div>'
                            + (fc.label ? '<div class="ref-card-name" title="' + fc.label.replace(/"/g, '&quot;') + '">' + fc.label + '</div>' : '')
                            + '</a>';
                    });
                    html += '</div></div>';
                }

                // 3. Render Narrative Sequences
                if(res.narrative_sequences && res.narrative_sequences.length > 0) {
                    html += '<div class="run-container"><div class="run-meta"><span class="run-title">NARRATIVE SEQUENCES</span></div><div class="ref-scroll-container">';
                    res.narrative_sequences.forEach(function(ns) {
                        html += '<a href="/animejseq.php?id=' + ns.seq_id + '" target="_blank" class="ref-card">'
                            + '<span class="ref-card-remove" onclick="event.preventDefault(); event.stopPropagation(); removeItem(' + ns.link_id + ')">✕</span>'
                            + '<div class="ref-card-top"><span class="ref-card-label">seq ' + ns.seq_id + '</span><i class="bi bi-box-arrow-up-right ref-card-icon"></i></div>'
                            + (ns.name ? '<div class="ref-card-name" title="' + ns.name.replace(/"/g, '&quot;') + '">' + ns.name + '</div>' : '')
                            + '</a>';
                    });
                    html += '</div></div>';
                }

                // 4. Render KG Nodes
                if(res.kg_nodes && res.kg_nodes.length > 0) {
                    html += '<div class="run-container"><div class="run-meta"><span class="run-title">KNOWLEDGE GRAPH NODES</span></div><div class="ref-scroll-container">';
                    res.kg_nodes.forEach(function(kn) {
                        html += '<div class="ref-card">'
                            + '<span class="ref-card-remove" onclick="event.preventDefault(); event.stopPropagation(); removeItem(' + kn.link_id + ')">✕</span>'
                            + '<div class="ref-card-top"><span class="ref-card-label">kg ' + kn.id + '</span><i class="bi bi-diagram-3 ref-card-icon"></i></div>'
                            + (kn.name ? '<div class="ref-card-name" title="' + kn.name.replace(/"/g, '&quot;') + '">' + kn.name + '</div>' : '')
                            + '<div style="margin-top:8px;"><button onclick="event.stopPropagation(); openIframeModal(\'mini_graph.php?graph=kg&node_id=' + kn.id + '\')" style="background:none; border:1px solid var(--border); border-radius:4px; padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.75em; font-family:monospace; width:100%;">⤢ mini graph</button></div>'
                            + '</div>';
                    });
                    html += '</div></div>';
                }

                // 5. Render AG Nodes
                if(res.ag_nodes && res.ag_nodes.length > 0) {
                    html += '<div class="run-container"><div class="run-meta"><span class="run-title">AUTO GRAPH NODES</span></div><div class="ref-scroll-container">';
                    res.ag_nodes.forEach(function(an) {
                        html += '<div class="ref-card">'
                            + '<span class="ref-card-remove" onclick="event.preventDefault(); event.stopPropagation(); removeItem(' + an.link_id + ')">✕</span>'
                            + '<div class="ref-card-top"><span class="ref-card-label">ag ' + an.id + '</span><i class="bi bi-diagram-2 ref-card-icon"></i></div>'
                            + (an.name ? '<div class="ref-card-name" title="' + an.name.replace(/"/g, '&quot;') + '">' + an.name + '</div>' : '')
                            + '<div style="margin-top:8px;"><button onclick="event.stopPropagation(); openIframeModal(\'mini_graph.php?graph=ag&doc_id=' + an.doc_id + '&node_id=' + an.id + '\')" style="background:none; border:1px solid var(--border); border-radius:4px; padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.75em; font-family:monospace; width:100%;">⤢ mini graph</button></div>'
                            + '</div>';
                    });
                    html += '</div></div>';
                }

                // 6. Render Map Runs / Entity runs
                if(res.runs && res.runs.length > 0) {
                    html += buildRunsHtml(res.runs);
                }

                if(!html) {
                    html = '<div class="empty-board">This board is empty. Add a Map Run or Document.</div>';
                }

                $('#board-content').html(html);
                initComponents();

                // Pagination
                if (res.total_pages > 1 || res.page > 1) {
                    $('#pagination-container').css('display', 'flex');
                    $('#inputPageIndex').val(res.page).attr('max', res.total_pages);
                    $('#pageCountDisplay').text('of ' + res.total_pages);
                    
                    $('#btnPrevPage').prop('disabled', res.page <= 1);
                    $('#btnNextPage').prop('disabled', res.page >= res.total_pages);
                } else {
                    $('#pagination-container').hide();
                }

            } else {
                Toast.show('Error loading board', 'error');
            }
        }, 'json').fail(function(){ Toast.show('Network error', 'error'); });
    };

    window.openDocModal = function(id) {
        const url = `view_md.php?id=${id}`;
        const iframe = document.getElementById('frameDetailsIframe');
        const modal = document.getElementById('frameDetailsModal');
        const loader = document.getElementById('ieLoadingOverlay');
        
        if(iframe && modal) {
            if(loader) {
                loader.style.display = 'flex';
                const p = loader.querySelector('p');
                if(p) p.textContent = 'Loading Document...';
            }
            iframe.src = url;
            modal.style.display = 'flex';
            
            iframe.onload = function() {
                if(loader) loader.style.display = 'none';
            };
        } else {
            window.open(url, '_blank');
        }
    };

    function safeStr(s) { if (s === null || s === undefined) return ''; return String(s); }

    function ucfirst(str) { if(!str) return ''; return str.charAt(0).toUpperCase() + str.slice(1); }

    function buildRunsHtml(runs) {
        let html = '<div class="pswp-gallery">';
        runs.forEach(run => {
            const createdAt = safeStr(run.created_at);
            const runId = run.id;
            const isStory = (run.item_type === 'storyboard');
            const isEntity = !isStory && (run.item_type !== 'map_run');
            
            let runTitle = `Map Run #${runId} (${createdAt})`;
            if (isStory) runTitle = `Storyboard #${runId} (${run.name || ''})`;
            if (isEntity) runTitle = `${ucfirst(run.item_type)} #${runId} (${run.name || ''})`;

            let containerClass = 'run-container';
            if (isStory) containerClass += ' storyboard-container';
            if (isEntity) containerClass += ' entity-container';
            
            const editIcon = isStory 
                ? `<a href="view_storyboard.php?id=${runId}" target="_blank" class="scroll-magic-link" title="Edit Storyboard"><i class="bi bi-pencil-square"></i></a>` 
                : '';
                
            const scrollMagicIcon = (!isStory && !isEntity) 
                ? `<a href="view_scrollmagic_map_run.php?map_run_id=${runId}" target="_blank" class="scroll-magic-link" title="Open Scroll Magic"><i class="bi bi-collection-play"></i></a>`
                : '';
                
            const regenIcon = (!isStory && !isEntity)
                ? `<a href="#" class="scroll-magic-link regen-run-btn" data-id="${runId}" title="Regenerate Images"><i class="bi bi-arrow-repeat"></i></a>`
                : '';

            // Build Video Details button if this run has videos
            let videoDetailsBtnHtml = '';
            if (run.type === 'video' && run.videos && run.videos.length > 0) {
                const firstVidId = run.videos[0].id || '';
                videoDetailsBtnHtml = `<button id="vid-details-btn-${runId}" class="btn-vid-details" data-vid-id="${firstVidId}" onclick="openVideoDetails(this.dataset.vidId)" title="Video Details">🎬 Video Details</button>`;
            }

            html += `
            <div class="${containerClass}">
                <div class="run-meta">
                    <div style="display:flex; align-items:center;">
                        <span class="run-title">${runTitle}</span>
                        ${scrollMagicIcon}
                        ${editIcon}
                        ${isStory ? `<a href="view_scrollmagic_multi_prm.php?storyboard_ids=${runId}&refresh=true" target="_blank" class="scroll-magic-link" title="ScrollMagic (Storyboard)"><i class="bi bi-collection-play"></i></a>` : ''}
                        ${regenIcon}
                        ${videoDetailsBtnHtml}
                    </div>
                    <button class="btn-action secondary" style="padding:4px 8px; font-size:12px;" onclick="removeItem(${run.link_id})">Remove</button>
                </div>`;

            if (run.type === 'video' && run.videos && run.videos.length > 0) {
                const firstVideo = run.videos[0];
                const cleanUrl = firstVideo.url.replace(/^\//, '');
                html += `
                <div class="video-run-grid">
                    <div class="video-player-box">
                        <video id="player-${runId}" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto">
                            <source src="${cleanUrl}" type="video/mp4" />
                        </video>
                    </div>
                    <div class="video-playlist-strip">`;
                run.videos.forEach((vid, idx) => {
                    const vUrl = vid.url.replace(/^\//, '');
                    const thumb = (vid.thumbnail || '').replace(/^\//, '');
                    const vidId = vid.id || '';
                    html += `
                        <div class="video-thumb-card ${idx===0?'active':''}" onclick="changeVideo('${runId}', '${vUrl}', '${vidId}', this)">
                            <img src="${thumb}" class="vt-img" loading="lazy">
                            <div class="vt-info">
                                <div class="vt-title" title="${vid.name}">${vid.name}</div>
                                <div class="vt-dur">${vid.duration ? Math.floor(vid.duration)+'s' : ''}</div>
                            </div>
                        </div>`;
                });
                html += `</div></div>`; 
            } else {
                html += `<div class="swiper frame-chain-swiper" id="swiper-${runId}">
                            <div class="swiper-wrapper">`;
                (run.frames || []).forEach(frame => {
                    const img = (frame.filename || '').replace(/^\//, '');
                    let typeForGear = frame.entity_type || 'frames';
                    let idForGear = frame.entity_id || frame.frame_id || 0;
                    if (!idForGear || idForGear == '0') { 
                        typeForGear = 'frames'; 
                        idForGear = frame.frame_id || 0; 
                    }
                    const frameId = frame.frame_id || 0;
                    let badgeHtml = '<span class="badge badge-gray">Original</span>';
                    if(frame.edit_tool) badgeHtml = '<span class="badge badge-orange">Edit</span>';
                    else if(frame.img2img_frame_id) badgeHtml = '<span class="badge badge-blue">Img2Img</span>';
                    const ingredients = frame.normalized_ingredients || [];
                    const metaHtml = ingredients.length > 0 
                        ? `<span class="badge badge-meta meta-pill-trigger" data-ingredients='${JSON.stringify(ingredients).replace(/'/g, "&#39;")}'>Meta (${ingredients.length})</span>` : '';
                    const promptText = safeStr(frame.full_sketch_desc || frame.prompt || '');
                    const gearAttr = `data-gear-menu data-entity="${typeForGear}" data-entity-id="${idForGear}" data-frame-id="${frameId}" data-img-url="${img}"`;
                    html += `
                        <div class="swiper-slide">
                            <div class="chain-card" ${gearAttr}>
                                <div class="chain-card-thumbnail">
                                    <a href="${img}" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                        <img src="${img}" loading="lazy" alt="frame ${frameId}" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                    </a>
                                </div>
                                <div class="chain-card-body">
                                    <h3 class="chain-card-title">Frame #${frameId}</h3>
                                    <div style="margin-bottom:8px;">${badgeHtml} ${metaHtml}</div>
                                    <p class="chain-card-prompt full-desc-trigger" data-full-desc="${promptText.replace(/"/g, '&quot;')}">
                                        ${(safeStr(frame.prompt) || '').substring(0, 50)}...
                                    </p>
                                </div>
                            </div>
                        </div>`;
                });
                html += `   </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-scrollbar"></div>
                        </div>`;
            }
            html += `</div>`;
        });
        html += '</div>';
        return html;
    }

    function initComponents() {
        document.querySelectorAll('.frame-chain-swiper').forEach(el => {
            new Swiper(el, {
                slidesPerView: 'auto', spaceBetween: 40, freeMode: true,
                navigation: { nextEl: el.querySelector('.swiper-button-next'), prevEl: el.querySelector('.swiper-button-prev') },
                scrollbar: { el: el.querySelector('.swiper-scrollbar'), hide: true },
                slidesOffsetBefore: 20, slidesOffsetAfter: 20
            });
        });
        document.querySelectorAll('.video-js').forEach(el => {
            if(!el.player) { videojs(el, { controls: true, preload: 'auto', fill: true }); }
        });
        if(window.initLightbox) window.initLightbox();
        window.attachGearMenu();
    }

    // --- Action Logic ---

    window.showAddItem = function() { $('#addItemModal').css('display', 'flex'); $('#inputRunId').focus(); };

    window.addItemToBoard = function() {
        const boardId = $('#currentBoardId').val();
        const itemId = $('#inputItemId').val();
        const type = $('#itemTypeSelect').val();
        
        if(!itemId || !boardId) return;

        $.post('boards_api.php', { action: 'add_item', board_id: boardId, item_type: type, item_id: itemId }, function(res) {
            if(res.ok) {
                if(res.message) Toast.show(res.message, 'info'); else Toast.show('Added!', 'success');
                $('#addItemModal').hide(); $('#inputItemId').val(''); loadBoard(boardId, null, $('#inputPageIndex').val() || 1);
            } else Toast.show('Error: ' + res.error, 'error');
        }, 'json').fail(function(){ Toast.show('Network Error', 'error'); });
    };

    window.removeItem = function(linkId) {
        if(!confirm('Remove from board?')) return;
        $.post('boards_api.php', { action: 'remove_item', item_id: linkId }, function(res) {
            if(res.ok) loadBoard($('#currentBoardId').val(), null, $('#inputPageIndex').val() || 1);
        }, 'json').fail(function(){ Toast.show('Network Error', 'error'); });
    };

    window.createBoard = function() {
        const name = $('#inputBoardName').val();
        const catId = $('#currentCategoryId').val() || null;
        if(!name) return;
        $.post('boards_api.php', { action: 'create_board', name: name, category_id: catId }, function(res) {
            if(res.ok) { Toast.show('Board Created', 'success'); $('#createBoardModal').hide(); $('#inputBoardName').val(''); refreshTree(); } 
            else Toast.show('Error', 'error');
        }, 'json').fail(function(){ Toast.show('Network Error', 'error'); });
    };

    window.createCategory = function() {
        const name = $('#inputCatName').val();
        const parentId = $('#currentCategoryId').val() || null;
        if(!name) return;
        $.post('boards_api.php', { action: 'create_category', name: name, parent_id: parentId }, function(res) {
            if(res.ok) { Toast.show('Folder Created', 'success'); $('#createCatModal').hide(); $('#inputCatName').val(''); refreshTree(); } 
            else Toast.show('Error', 'error');
        }, 'json').fail(function(){ Toast.show('Network Error', 'error'); });
    };

    // --- On Load ---
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('board_id')) {
        const bid = urlParams.get('board_id');
        const page = urlParams.get('page') ? parseInt(urlParams.get('page'), 10) : 1;
        $('#currentBoardId').val(bid);
        loadBoard(bid, null, page);
    }

    // Modal Events
    $(document).on('click', '.meta-pill-trigger', function(e) {
        e.stopPropagation();
        const raw = this.dataset.ingredients;
        if (!raw) return;
        const ingredients = JSON.parse(raw);
        const body = document.getElementById('meta-modal-body');
        let html = '';
        const getIcon = (type) => { if (!type) return '📦'; if (type.includes('character')) return '🦸'; if (type.includes('location')) return '🗺️'; if (type.includes('template')) return '🎬'; if (type.includes('interaction')) return '🤝'; if (type.includes('style')) return '🎨'; if (type.includes('generator')) return '⚡'; if (type.includes('anivoc')) return '📘'; return '📦'; };
        ingredients.forEach(ing => { const icon = getIcon(ing.type); html += `<div class="modal-row"><div class="modal-icon">${icon}</div><div class="modal-info"><span class="modal-label">${ing.label}</span>${ing.detail ? `<span class="modal-detail">${ing.detail.substring(0, 150)}${ing.detail.length>150?'...':''}</span>` : ''}</div></div>`; });
        body.innerHTML = html; $('#meta-modal').css('display', 'flex');
    });

    $(document).on('click', '.full-desc-trigger', function(e) {
        e.stopPropagation(); $('#desc-modal-body').text(this.dataset.fullDesc); $('#desc-modal').css('display', 'flex');
    });

    $(document).on('click', '.regen-run-btn', function(e) {
        e.preventDefault(); const $btn = $(this); if (!confirm("Regenerate?")) return; const orig = $btn.html(); $btn.html('...').css('pointer-events', 'none');
        $.post('boards_api.php', { action: 'regenerate_run', map_run_id: $btn.data('id') }, function(res) { if (res.ok) { Toast.show('Marked', 'success'); $btn.html('✔'); } else { Toast.show('Error', 'error'); $btn.html(orig).css('pointer-events', 'auto'); } }, 'json').fail(function(){ Toast.show('Network Error', 'error'); $btn.html(orig).css('pointer-events', 'auto'); });
    });

    window.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) $(e.target).hide(); });

    // Iframe Modal Keydown
    document.addEventListener('keydown', e => { if(e.key === 'Escape' && typeof closeIframeModal === 'function') closeIframeModal(); });

    // Pagination Handlers
    $(document).on('click', '#btnPrevPage', function() {
        if ($(this).prop('disabled')) return;
        const boardId = $('#currentBoardId').val();
        let page = parseInt($('#inputPageIndex').val(), 10);
        if (page > 1) loadBoard(boardId, null, page - 1);
    });

    $(document).on('click', '#btnNextPage', function() {
        if ($(this).prop('disabled')) return;
        const boardId = $('#currentBoardId').val();
        let page = parseInt($('#inputPageIndex').val(), 10);
        let maxPage = parseInt($('#inputPageIndex').attr('max'), 10);
        if (page < maxPage) loadBoard(boardId, null, page + 1);
    });

    $(document).on('change', '#inputPageIndex', function() {
        const boardId = $('#currentBoardId').val();
        let page = parseInt($(this).val(), 10);
        let maxPage = parseInt($(this).attr('max'), 10) || 1;
        if (isNaN(page) || page < 1) page = 1;
        if (page > maxPage) page = maxPage;
        $(this).val(page);
        loadBoard(boardId, null, page);
    });
});
</script>
</body>
</html>