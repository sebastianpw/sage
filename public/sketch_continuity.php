<?php
// public/sketch_continuity.php
// Character Continuity Processor (3-Column -> 2-Column + Flyout)
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\AIProvider;

$conn = $spw->getEntityManager()->getConnection();

// --- HELPERS ---
function fetchSequencesWithSketches($conn, $limit, $offset) {
    $sequences = $conn->fetchAllAssociative("SELECT id, name, sequence_data, created_at FROM narrative_sequences ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    
    $allSketchIds =[];
    foreach ($sequences as &$seq) {
        $data = json_decode($seq['sequence_data'], true) ?? [];
        $seq['parsed_sketches'] =[];
        foreach ($data as $item) {
            if (is_numeric($item)) $seq['parsed_sketches'][] = (int)$item;
            elseif (is_array($item) && isset($item['sketch_id'])) $seq['parsed_sketches'][] = (int)$item['sketch_id'];
            elseif (is_array($item) && isset($item['id'])) $seq['parsed_sketches'][] = (int)$item['id'];
        }
        $allSketchIds = array_merge($allSketchIds, $seq['parsed_sketches']);
    }
    unset($seq);

    $sketchMap =[];
    if (!empty($allSketchIds)) {
        $idsStr = implode(',', array_unique($allSketchIds));
        if(!empty($idsStr)) {
            $rows = $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches WHERE id IN ($idsStr)");
            foreach ($rows as $r) $sketchMap[$r['id']] = $r;
        }
    }

    $result = [];
    foreach ($sequences as $seq) {
        $items =[];
        foreach ($seq['parsed_sketches'] as $sid) {
            if (isset($sketchMap[$sid])) $items[] = $sketchMap[$sid];
        }
        $seq['items'] = $items;
        $result[] = $seq;
    }
    return $result;
}

function fetchFlatSketches($conn, $limit, $offset) {
    return $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
}

// --- API HANDLER ---
if (isset($_POST['action'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    try {
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = 15; 
        $offset = ($page - 1) * $limit;

        // 1. FETCH SIDEBAR
        if ($_POST['action'] === 'fetch_sidebar') {
            $mode = $_POST['mode'] ?? 'flat';
            $search = trim($_POST['search'] ?? '');

            if ($mode === 'sequences') {
                $total = $conn->fetchOne("SELECT COUNT(*) FROM narrative_sequences");
                $items = fetchSequencesWithSketches($conn, $limit, $offset);
            } else {
                if ($search !== '') {
                    $searchParam = "%$search%";
                    if (is_numeric($search)) {
                        $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches WHERE id = ? OR name LIKE ?", [$search, $searchParam]);
                        $items = $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches WHERE id = ? OR name LIKE ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset", [$search, $searchParam]);
                    } else {
                        $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches WHERE name LIKE ?", [$searchParam]);
                        $items = $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches WHERE name LIKE ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset", [$searchParam]);
                    }
                } else {
                    $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches");
                    $items = fetchFlatSketches($conn, $limit, $offset);
                }
            }
            
            echo json_encode([
                'ok' => true, 'mode' => $mode, 'items' => $items, 
                'total_pages' => ceil($total/$limit), 'current_page' => $page, 'total_items' => $total
            ]);
            exit;
        }

        // 2. GET SKETCH + FRAMES
        if ($_POST['action'] === 'get_sketch') {
            $id = (int)$_POST['id'];
            $sk = $conn->fetchAssociative("SELECT * FROM sketches WHERE id = ?", [$id]);
            if(!$sk) throw new Exception("Sketch not found");
            
            // Fetch Frames
            $frames = $conn->fetchAllAssociative("
                SELECT id, filename FROM frames 
                WHERE (entity_type = 'sketches' AND entity_id = ?)
                ORDER BY id ASC
            ", [$id]);
            
            $sk['frames'] = $frames;

            echo json_encode(['ok' => true, 'data' => $sk]);
            exit;
        }

        // 3. RUN CONTINUITY
        if ($_POST['action'] === 'process_continuity') {
            $sketchId = (int)$_POST['sketch_id'];
            $charIds = $_POST['character_ids'] ?? [];
            $genConfigId = (int)$_POST['generator_id'];
            $currentDesc = $_POST['current_description'];

            if (empty($charIds)) throw new Exception("No characters selected.");

            $idsStr = implode(',', array_map('intval', $charIds));
            $characters = $conn->fetchAllAssociative("SELECT name, description FROM characters WHERE id IN ($idsStr)");

            $charContext = "";
            foreach ($characters as $c) {
                $desc = trim(strip_tags($c['description']));
                $charContext .= "CHARACTER: {$c['name']}\nDESCRIPTION: {$desc}\n\n";
            }

            $prompt = "ORIGINAL SCENE DESCRIPTION:\n$currentDesc\n\n";
            $prompt .= "REQUIRED CHARACTER CONTINUITY DETAILS:\n$charContext\n";
            $prompt .= "TASK: Rewrite the scene description incorporating the exact character details provided above while maintaining the original scene's action and mood.";

            $em = $spw->getEntityManager();
            $genConfig = $em->getRepository(GeneratorConfig::class)->find($genConfigId);
            
            $logger = $spw->getFileLogger();
            $aiProvider = $spw->getAIProvider();
            $generatorService = new GeneratorService($aiProvider, new SchemaValidator(), new ResponseNormalizer(), $logger);

            $result = $generatorService->generate($genConfig, ['entity_name' => $prompt]);
            if (!$result->isSuccess()) throw new Exception("AI Generation failed");
            
            $data = $result->getData();
            if (is_array($data)) {
                $rawText = $data['scene_prompt'] ?? $data['description'] ?? $data['text'] ?? json_encode($data);
            } else {
                $rawText = (string)$data;
            }

            $newDescription = str_replace(["\u2014", "—"], "", $rawText);
            $newDescription = trim($newDescription);

            $conn->executeStatement("UPDATE sketches SET description = ?, updated_at = NOW() WHERE id = ?", [$newDescription, $sketchId]);

            echo json_encode(['ok' => true, 'new_description' => $newDescription]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// --- INITIAL VIEW DATA ---
$chars = $conn->fetchAllAssociative("SELECT id, name FROM characters ORDER BY name");
$gens = $conn->fetchAllAssociative("SELECT id, title FROM generator_config WHERE active = 1 ORDER BY title");
$defaultGenId = 111;
$initSketchId = (int)($_GET['sketch_id'] ?? 0);

$pageTitle = "Continuity Processor";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <title>Continuity Processor</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- PhotoSwipe CSS -->
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.css">
    
    <link rel="stylesheet" href="/css/base.css">
    
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <style>
        body { 
            background: #0a0a0f; color: #e2e2f0; font-family: 'DM Mono', monospace; 
            padding: 0; margin: 0; overflow: hidden; font-size: 14px; 
            height: 100vh;
        }
        
        /* ── LAYOUT ── */
        .app-container {
            display: flex;
            height: 90vh;
            width: 100vw;
            position: relative;
        }

        /* ── NAV TOGGLE (Fixed Top Left) ── */
        .nav-toggle-btn {
            position: absolute; top: 10px; left: 10px; z-index: 100;
            width: 40px; height: 40px; border-radius: 6px;
            background: #1a1a1a; border: 1px solid #333; color: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.2rem; transition: 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }
        .nav-toggle-btn:hover { border-color: #8b5cf6; color: #8b5cf6; }

        /* ── FLYOUT SIDEBAR ── */
        .flyout-sidebar {
            position: absolute; top: 0; left: 0; bottom: 0;
            width: 350px; background: #111; border-right: 1px solid #333;
            z-index: 90; transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            display: flex; flex-direction: column;
            padding-top: 60px; /* Space for toggle button */
            box-shadow: 10px 0 30px rgba(0,0,0,0.5);
        }
        .flyout-sidebar.active { transform: translateX(0); }

        .sidebar-header { padding: 10px 15px; border-bottom: 1px solid #333; background: #151515; }
        .sidebar-toggle { display: flex; background: #000; border-radius: 6px; padding: 2px; margin-bottom: 10px; }
        .toggle-btn { flex: 1; padding: 6px; text-align: center; cursor: pointer; border-radius: 4px; font-size: 0.8rem; color: #666; font-weight: bold; }
        .toggle-btn.active { background: #333; color: #fff; }
        
        .sidebar-list { flex: 1; overflow-y: auto; padding: 10px; }
        .item-row { padding: 10px; border-bottom: 1px solid #222; cursor: pointer; border-left: 3px solid transparent; }
        .item-row:hover { background: #222; }
        .item-row.active { background: #2a2a30; border-left-color: #8b5cf6; }
        .seq-group { border: 1px solid #333; margin-bottom: 5px; border-radius: 4px; overflow: hidden; }
        .seq-header { padding: 8px; background: #222; cursor: pointer; display: flex; justify-content: space-between; }
        .seq-body { display: none; background: #111; padding-left: 10px; }
        .seq-group.open .seq-body { display: block; }

        .pagination-bar { display: flex; justify-content: space-between; align-items: center; }
        .pager-btn { background: #333; border: 1px solid #444; color: #fff; width: 24px; height: 24px; cursor: pointer; }

        /* ── MAIN CONTENT (2 COLUMNS) ── */
        .main-grid {
            flex: 1; display: grid;
            grid-template-columns: 360px 1fr;
            height: 100%;
            margin-left: 0; /* Pushed by sidebar only visually via Z-index */
        }

        /* COL 1: CONFIG */
        .col-config {
            background: #141419; border-right: 1px solid #333;
            display: flex; flex-direction: column; padding: 15px; padding-top: 60px; /* Buffer for toggle */
            gap: 15px; overflow: hidden;
        }
        .char-list-container { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        .char-list-scroll { flex: 1; overflow-y: auto; background: #0f0f13; border: 1px solid #333; border-radius: 6px; padding: 5px; }
        .char-item { padding: 6px; border-bottom: 1px solid #222; display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .char-item input { accent-color: #8b5cf6; }
        
        .config-footer { flex-shrink: 0; padding-top: 10px; border-top: 1px solid #222; display: flex; flex-direction: column; gap: 10px; }

        /* COL 2: EDITOR (Split) */
        .col-editor {
            background: #0a0a0f; display: flex; flex-direction: column; padding: 20px;
            overflow: hidden; gap: 15px; min-width: 0;
        }
        
        /* Top Section: Visuals */
        .visual-stage {
            flex: 0 0 30%; /* 35% height for images */
            min-height: 250px; min-width: 0;
            background: #000; border: 1px solid #333; border-radius: 6px;
            overflow: hidden; position: relative;
            display: flex; flex-direction: column;
            padding-top:50px;
        }
        .visual-header { padding: 8px 15px; background: rgba(0,0,0,0.5); position: absolute; top: 0; left: 0; right: 0; z-index: 10; display: flex; justify-content: space-between; }
        
        /* Swiper constrained to square */
        .swiper { width: 100%; max-width: 360px; aspect-ratio: 1 / 1; margin: auto; margin-top: 40px; margin-bottom: 10px; }
        .swiper-slide { 
            display: flex; align-items: center; justify-content: center; 
            background: #000; border-radius: 4px; overflow: hidden; border: 1px solid #333; position: relative; box-sizing: border-box;
        }
        /* Ensure anchor fills slide for centering */
        .swiper-slide a {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
        }
        .swiper-slide img { 
            width: 100%; height: 100%; 
            object-fit: contain; 
            display: block; 
        }

        /* Bottom Section: Text */
        .text-stage {
            flex: 1; display: flex; flex-direction: column; min-height: 0;
        }
        .editor-header { margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        textarea { 
            flex: 1; width: 100%; background: #080808; border: 1px solid #333; color: #ddd; 
            padding: 20px; font-family: inherit; font-size: 1.1rem; line-height: 1.6; resize: none; border-radius: 6px; 
        }
        textarea:focus { outline: none; border-color: #8b5cf6; }

        /* UI Elements */
        h3 { margin: 0 0 8px 0; color: #8b5cf6; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .btn { padding: 15px; background: #8b5cf6; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; font-size: 1rem; transition: 0.2s; }
        .btn:hover { background: #7c3aed; }
        .btn:disabled { opacity: 0.5; }
        .select-input { width: 100%; padding: 10px; background: #222; border: 1px solid #333; color: #fff; border-radius: 4px; }

        /* Frame View Modal */
        .view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
        .view-modal.active { display: flex; }
        .view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid #444; box-shadow: 0 0 30px rgba(0,0,0,0.5); }
        .view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
        .view-close:hover { background: #fff; color: #000; }
        iframe.frame-viewer { width: 100%; height: 100%; border: none; }

        .f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
        .swiper-slide:hover .f-view-btn { opacity: 1; }
        .f-view-btn:hover { background: #fff; border-color: #fff; color: #000; }
    </style>
    
    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

</head>
<body>

<!-- Flyout Button -->
<div class="nav-toggle-btn" style="margin-left:70px;" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</div>

<div class="app-container">
    
    <!-- LEFT: FLYOUT NAVIGATOR -->
    <div class="flyout-sidebar" id="flyoutSidebar">
        <div class="sidebar-header">
            <div class="sidebar-toggle">
                <div class="toggle-btn active" id="btnModeFlat" onclick="setMode('flat')">All Sketches</div>
                <div class="toggle-btn" id="btnModeSeq" onclick="setMode('sequences')">Sequences</div>
            </div>
            <div id="searchBarContainer" style="margin-bottom: 10px;">
                <input type="text" id="sidebarSearch" placeholder="Search by ID or Name..." style="width: 100%; padding: 5px; background: #222; border: 1px solid #333; color: #fff; border-radius: 4px; box-sizing: border-box;">
            </div>
            <div class="pagination-bar">
                <button class="pager-btn" onclick="changePage(-1)">❮</button>
                <span style="font-size:0.8rem; color:#888;">Page <input type="number" id="pageInput" style="width: 40px; background: #222; color: #fff; border: 1px solid #444; text-align: center; border-radius: 4px; margin: 0 4px;" value="1" min="1"> / <span id="totPage">1</span></span>
                <button class="pager-btn" onclick="changePage(1)">❯</button>
            </div>
        </div>
        <div class="sidebar-list" id="sidebarContent">
            <div style="text-align:center; padding:20px; color:#666;">Loading...</div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="main-grid">
        
        <!-- CENTER: CONFIG -->
        <div class="col-config">
            <div class="char-list-container">
                <h3>1. Select Characters</h3>
                <div class="char-list-scroll">
                    <?php foreach($chars as $c): ?>
                    <label class="char-item">
                        <input type="checkbox" name="chars[]" value="<?= $c['id'] ?>">
                        <span><?= htmlspecialchars($c['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="config-footer">
                <h3>2. Settings</h3>
                <select id="genConfig" class="select-input">
                    <?php foreach($gens as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= $g['id'] == $defaultGenId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" id="runBtn" onclick="processContinuity()" disabled>Rewrite Scene</button>
            </div>
        </div>

        <!-- RIGHT: VISUALS & EDITOR -->
        <div class="col-editor">
            
            <!-- Top: Swiper Gallery -->
            <div class="visual-stage">
                <div class="visual-header">
                    <span style="color:#fff; font-weight:bold; font-size:0.9rem;" id="wkTitle">Select Sketch</span>
                    <span style="color:#aaa; font-size:0.8rem;" id="wkId">#0</span>
                </div>
                <!-- ID is used for PhotoSwipe delegation as well -->
                <div class="swiper" id="sketchSwiper">
                    <div class="swiper-wrapper" id="swiperWrapper">
                        <div class="swiper-slide" style="color:#555;">No frames available</div>
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>

            <!-- Bottom: Text Editor -->
            <div class="text-stage">
                <div class="editor-header">
                    <h3>Scene Description</h3>
                    <span id="wkMood" style="color:#888; font-size:0.8rem;"></span>
                </div>
                <textarea id="sceneDesc" placeholder="Select a sketch..."></textarea>
            </div>
        </div>

    </div>
</div>

<!-- Frame View Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<script>
// STATE
let sidebarMode = 'flat';
let currentPage = 1;
let currentSketchId = <?= $initSketchId ?>;
let swiperInst = null;
let searchQuery = '';

// PhotoSwipe Helper to update dimensions on load
window.updatePswpDims = function(img) {
    const a = img.closest('a');
    if(a) {
        a.setAttribute('data-pswp-width', img.naturalWidth);
        a.setAttribute('data-pswp-height', img.naturalHeight);
    }
}

$(function() {
    loadSidebar();
    
    // Init Swiper
    swiperInst = new Swiper('#sketchSwiper', {
        slidesPerView: 1,
        spaceBetween: 10,
        loop: true,
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        pagination: { el: '.swiper-pagination' },
    });

    // Load Config
    const savedGen = localStorage.getItem('continuity_gen_id');
    if(savedGen) $('#genConfig').val(savedGen);
    $('#genConfig').change(function() { localStorage.setItem('continuity_gen_id', $(this).val()); });

    // Initial Load
    if(currentSketchId > 0) loadSketch(currentSketchId);
    
    // Expand sidebar if no sketch selected initially
    if(currentSketchId === 0) $('#flyoutSidebar').addClass('active');

    // Sidebar Delegation
    $(document).on('click', '.seq-header', function() { $(this).closest('.seq-group').toggleClass('open'); });

    // Page Input
    $('#pageInput').on('change', function() {
        let p = parseInt($(this).val());
        if (!isNaN(p) && p > 0) {
            currentPage = p;
            loadSidebar();
        }
    });

    // Search Input
    let searchTimeout = null;
    $('#sidebarSearch').on('input', function() {
        searchQuery = $(this).val();
        currentPage = 1;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadSidebar, 300);
    });
});

function toggleSidebar() {
    $('#flyoutSidebar').toggleClass('active');
}

function setMode(mode) {
    if(sidebarMode === mode) return;
    sidebarMode = mode;
    currentPage = 1;
    $('.toggle-btn').removeClass('active');
    $(`#btnMode${mode === 'flat' ? 'Flat' : 'Seq'}`).addClass('active');
    
    if (mode === 'flat') {
        $('#searchBarContainer').show();
    } else {
        $('#searchBarContainer').hide();
        searchQuery = '';
        $('#sidebarSearch').val('');
    }

    loadSidebar();
}

function changePage(d) {
    currentPage += d;
    if(currentPage < 1) currentPage = 1;
    loadSidebar();
}

function loadSidebar() {
    $('#sidebarContent').css('opacity', '0.5');
    $.post('', { action: 'fetch_sidebar', mode: sidebarMode, page: currentPage, search: searchQuery }, function(res) {
        $('#sidebarContent').css('opacity', '1');
        if (res.ok) {
            $('#pageInput').val(res.current_page);
            $('#totPage').text(res.total_pages);
            
            let html = '';
            if (res.mode === 'flat') {
                res.items.forEach(item => {
                    const active = (item.id == currentSketchId) ? 'active' : '';
                    html += `<div class="item-row ${active}" onclick="loadSketch(${item.id})">
                        <div style="font-weight:bold">${escapeHtml(item.name)}</div>
                        <div style="font-size:0.7rem;color:#888">${escapeHtml(item.mood || '-')}</div>
                    </div>`;
                });
            } else {
                res.items.forEach(seq => {
                    let itemsHtml = '';
                    seq.items.forEach(item => {
                        const active = (item.id == currentSketchId) ? 'active' : '';
                        itemsHtml += `<div class="item-row ${active}" onclick="loadSketch(${item.id})">
                            <div style="font-weight:bold">${escapeHtml(item.name)}</div>
                        </div>`;
                    });
                    const openClass = (seq.parsed_sketches && seq.parsed_sketches.includes(currentSketchId)) ? 'open' : '';
                    html += `<div class="seq-group ${openClass}">
                        <div class="seq-header"><span style="font-weight:bold;color:#ccc;">${escapeHtml(seq.name)}</span><span>▼</span></div>
                        <div class="seq-body">${itemsHtml}</div>
                    </div>`;
                });
            }
            $('#sidebarContent').html(html);
        }
    });
}

function loadSketch(id) {
    currentSketchId = id;
    $('.item-row').removeClass('active');
    $(`.item-row[onclick="loadSketch(${id})"]`).addClass('active');
    $('#runBtn').prop('disabled', true).text('Loading...');
    
    $.post('', { action: 'get_sketch', id: id }, function(res) {
        $('#runBtn').prop('disabled', false).text('Rewrite Scene');
        if (res.ok) {
            const s = res.data;
            $('#wkTitle').text(s.name);
            $('#wkId').text('#' + s.id);
            $('#wkMood').text(s.mood || 'No Mood');
            $('#sceneDesc').val(s.description);
            
            // Render Frames in Swiper with PhotoSwipe integration
            const swWrapper = $('#swiperWrapper');
            swWrapper.empty();
            if(s.frames && s.frames.length > 0) {
                s.frames.forEach(f => {
                    // We wrap image in anchor, use dummy dims initially, update on load
                    swWrapper.append(`
                        <div class="swiper-slide">
                            <a href="${f.filename}" 
                               data-pswp-width="800" 
                               data-pswp-height="600" 
                               target="_blank"
                               class="pswp-link">
                               <img src="${f.filename}" loading="lazy" onload="updatePswpDims(this)">
                            </a>
                            <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openFrameModal(${f.id})"><i class="bi bi-arrows-fullscreen"></i></div>
                        </div>
                    `);
                });
            } else {
                swWrapper.html('<div class="swiper-slide" style="color:#555">No images found</div>');
            }
            swiperInst.update();
            swiperInst.slideTo(0);

            const newUrl = window.location.pathname + '?sketch_id=' + id;
            window.history.pushState({path:newUrl}, '', newUrl);
        }
    });
}

function processContinuity() {
    const charIds = Array.from(document.querySelectorAll('input[name="chars[]"]:checked')).map(cb => cb.value);
    if (charIds.length === 0) return alert("Select at least one character.");
    if (!currentSketchId) return alert("Select a sketch.");

    const btn = $('#runBtn');
    const origText = btn.text();
    btn.text("Processing...").prop('disabled', true);

    $.post('', {
        action: 'process_continuity',
        sketch_id: currentSketchId,
        character_ids: charIds,
        generator_id: $('#genConfig').val(),
        current_description: $('#sceneDesc').val()
    }, function(res) {
        btn.text(origText).prop('disabled', false);
        if(res.ok) {
            $('#sceneDesc').val(res.new_description);
            $('#sceneDesc').css('border-color', '#10b981');
            setTimeout(() => $('#sceneDesc').css('border-color', '#333'), 1000);
        } else alert("Error: " + res.error);
    }, 'json').fail(function() {
        btn.text(origText).prop('disabled', false);
        alert("Server Error");
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// ── FRAME MODAL ──
function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeFrameModal(); });
</script>

<!-- PhotoSwipe Init -->
<script type="module">
import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe-lightbox.esm.js';
import PhotoSwipe from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.esm.js';

const lightbox = new PhotoSwipeLightbox({
  gallery: '#swiperWrapper',
  children: 'a',
  pswpModule: PhotoSwipe
});
lightbox.init();
</script>

<?php echo $eruda; ?>

</body>
</html>