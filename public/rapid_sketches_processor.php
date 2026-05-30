<?php
// public/rapid_sketches_processor.php
require_once __DIR__ . '/bootstrap.php';
use App\Core\AIProvider;
use App\Core\SpwBase;

$em = $spw->getEntityManager();
$conn = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) die('Not authenticated');

// --- HELPER: SANITIZE ---
function sanitizeText($str) {
    if (!$str) return '';
    $search = ["\xC2\xAB", "\xC2\xBB", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x93", "\xE2\x80\x94", "\u201c", "\u201d", "\u2018", "\u2019", "\u2014", "\u2013"];
    $replace = ["<<", ">>", "'", "'", '"', '"', "-", "-", '"', '"', "'", "'", "-", "-"];
    $str = str_replace($search, $replace, $str);
    return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
}

// --- HELPER: SEQUENCES WITH SKETCHES (from continuity) ---
function fetchSequencesWithSketches($conn, $limit, $offset) {
    $sequences = $conn->fetchAllAssociative("SELECT id, name, sequence_data, created_at FROM narrative_sequences ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

    $allSketchIds = [];
    foreach ($sequences as &$seq) {
        $data = json_decode($seq['sequence_data'], true) ?? [];
        $seq['parsed_sketches'] = [];
        foreach ($data as $item) {
            if (is_numeric($item)) $seq['parsed_sketches'][] = (int)$item;
            elseif (is_array($item) && isset($item['sketch_id'])) $seq['parsed_sketches'][] = (int)$item['sketch_id'];
            elseif (is_array($item) && isset($item['id'])) $seq['parsed_sketches'][] = (int)$item['id'];
        }
        $allSketchIds = array_merge($allSketchIds, $seq['parsed_sketches']);
    }
    unset($seq);

    $sketchMap = [];
    if (!empty($allSketchIds)) {
        $idsStr = implode(',', array_unique($allSketchIds));
        if (!empty($idsStr)) {
            $rows = $conn->fetchAllAssociative("SELECT id, name, mood FROM sketches WHERE id IN ($idsStr)");
            foreach ($rows as $r) $sketchMap[$r['id']] = $r;
        }
    }

    $result = [];
    foreach ($sequences as $seq) {
        $items = [];
        foreach ($seq['parsed_sketches'] as $sid) {
            if (isset($sketchMap[$sid])) $items[] = $sketchMap[$sid];
        }
        $seq['items'] = $items;
        $result[] = $seq;
    }
    return $result;
}

// --- FETCH CONFIGS ---
$genConfigs = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC");
$docCats = $conn->fetchAllAssociative("SELECT id, name FROM documentation_categories ORDER BY name ASC");

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = $_POST;

    // A. FETCH SIDEBAR (NEW — mirrors continuity's fetch_sidebar)
    if ($input['action'] === 'fetch_sidebar') {
        try {
            $page = max(1, intval($input['page'] ?? 1));
            $limit = 25;
            $offset = ($page - 1) * $limit;
            $mode = $input['mode'] ?? 'flat';
            $search = trim($input['search'] ?? '');

            if ($mode === 'sequences') {
                $total = $conn->fetchOne("SELECT COUNT(*) FROM narrative_sequences");
                $items = fetchSequencesWithSketches($conn, $limit, $offset);
            } else {
                if ($search !== '') {
                    $searchParam = "%$search%";
                    if (is_numeric($search)) {
                        $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches WHERE id = ? OR name LIKE ?", [$search, $searchParam]);
                        $items = $conn->fetchAllAssociative("SELECT id, name, mood, description, seed FROM sketches WHERE id = ? OR name LIKE ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset", [$search, $searchParam]);
                    } else {
                        $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches WHERE name LIKE ?", [$searchParam]);
                        $items = $conn->fetchAllAssociative("SELECT id, name, mood, description, seed FROM sketches WHERE name LIKE ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset", [$searchParam]);
                    }
                } else {
                    $total = $conn->fetchOne("SELECT COUNT(*) FROM sketches");
                    $items = $conn->fetchAllAssociative("SELECT id, name, mood, description, seed FROM sketches ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
                }
            }

            echo json_encode([
                'ok' => true, 'mode' => $mode, 'items' => $items,
                'total_pages' => ceil($total / $limit), 'current_page' => $page, 'total_items' => $total
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // B. FETCH SKETCHES (PAGINATION) — kept for backward compat
    if ($input['action'] === 'fetch_sketches') {
        try {
            $page = max(1, intval($input['page'] ?? 1));
            $limit = 25;
            $offset = ($page - 1) * $limit;

            $totalCount = $conn->fetchOne("SELECT COUNT(*) FROM sketches");
            $totalPages = ceil($totalCount / $limit);

            $items = $conn->fetchAllAssociative("
                SELECT id, name, mood, description, seed
                FROM sketches 
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset
            ");

            echo json_encode([
                'ok' => true, 
                'items' => $items, 
                'current_page' => $page,
                'total_pages' => $totalPages
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // C. GENERATE MD (Main Processor)
    if ($input['action'] === 'generate_md') {
        set_time_limit(600); 
        session_write_close();

        try {
            $id = $input['id'];
            $configId = $input['config_id'];
            $customText = $input['custom_text']; 

            $sketch = $conn->fetchAssociative("SELECT * FROM sketches WHERE id = ?", [$id]);
            if (!$sketch) throw new Exception("Sketch not found");

            $genConfig = $conn->fetchAssociative("SELECT * FROM generator_config WHERE config_id = ?", [$configId]);
            if (!$genConfig) throw new Exception("Generator configuration not found");

            $ai = new AIProvider();
            $instructionsArr = json_decode($genConfig['instructions'], true) ?? [];
            $sysPrompt = $genConfig['system_role'] . "\n\n" . implode("\n", $instructionsArr);
            
            $userPrompt = "SCENE NAME: {$sketch['name']}\n";
            $userPrompt .= "MOOD: " . ($sketch['mood'] ?? 'N/A') . "\n";
            $userPrompt .= "SEED: " . ($sketch['seed'] ?? 'N/A') . "\n";
            $userPrompt .= "\n--- SCENE BEATS / DESCRIPTION ---\n" . $customText;

            $model = $genConfig['model'] && $genConfig['model'] !== 'openai' ? $genConfig['model'] : AIProvider::getDefaultModel();
            
            $generatedMd = $ai->sendPrompt($model, $userPrompt, $sysPrompt, ['temperature' => 0.7]);
            echo json_encode(['ok' => true, 'md' => $generatedMd]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // D. BRAINSTORM BEATS
    if ($input['action'] === 'brainstorm_beats') {
        set_time_limit(300);
        session_write_close();

        try {
            $id = $input['id'];
            $configId = $input['config_id'];
            $contextText = $input['context_text'];

            $sketch = $conn->fetchAssociative("SELECT * FROM sketches WHERE id = ?", [$id]);
            if (!$sketch) throw new Exception("Sketch not found");

            $genConfig = $conn->fetchAssociative("SELECT * FROM generator_config WHERE config_id = ?", [$configId]);
            if (!$genConfig) throw new Exception("Generator configuration not found");

            $ai = new AIProvider();
            $instructionsArr = json_decode($genConfig['instructions'], true) ?? [];
            $sysPrompt = $genConfig['system_role'] . "\n\n" . implode("\n", $instructionsArr);

            $userPrompt = "--- CONTEXT FOR BRAINSTORMING ---\n";
            $userPrompt .= "SCENE NAME: {$sketch['name']}\n";
            $userPrompt .= "MOOD: " . ($sketch['mood'] ?? 'N/A') . "\n";
            $userPrompt .= "EXISTING DESCRIPTION/NOTES:\n" . $contextText . "\n\n";
            $userPrompt .= "--- TASK ---\n";
            $userPrompt .= "Please provide beat proposals or narrative expansion based on the context above.";

            $model = $genConfig['model'] && $genConfig['model'] !== 'openai' ? $genConfig['model'] : AIProvider::getDefaultModel();

            $resultText = $ai->sendPrompt($model, $userPrompt, $sysPrompt, ['temperature' => 0.8]);
            echo json_encode(['ok' => true, 'text' => $resultText]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // E. PUBLISH TO SHOWCASE
    if ($input['action'] === 'publish_showcase') {
        try {
            $mdContent = $input['md'];
            $stagingId = $input['staging_id'];
            $targetConfigId = $input['target_config_id']; 
            
            $sketch = $conn->fetchAssociative("SELECT name FROM sketches WHERE id = ?", [$stagingId]);
            $categoryName = $sketch ? sanitizeText($sketch['name']) : 'Imported Sketch';

            $pattern = '/###\s*([A-Z0-9\-_ ]+):\s*(.*?)\s*\n.*?```(.*?)```/s';
            $stats = ['inserted' => 0];

            if (preg_match_all($pattern, $mdContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $ref = trim($m[1]);
                    $title = sanitizeText(trim($m[2]));
                    $prompt = sanitizeText(preg_replace('/\s+/', ' ', trim($m[3])));

                    $conn->insert('rapid_showcase', [
                        'reference_code' => $ref,
                        'title' => $title,
                        'category' => $categoryName, 
                        'description_prompt' => $prompt,
                        'generator_config_id' => $targetConfigId, 
                        'is_generated' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    $stats['inserted']++;
                }
            }
            echo json_encode(['ok' => true, 'stats' => $stats]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // F. FETCH SKETCH FRAMES AND LOOKUP REFS
    if ($input['action'] === 'fetch_frames') {
        try {
            $sketchId = (int)($input['sketch_id'] ?? 0);
            
            // 1. Fetch frames
            $sqlFrames = "
                SELECT f.id, f.filename
                FROM frames f
                WHERE (f.entity_type = 'sketches' AND f.entity_id = ?)
                   OR f.id IN (SELECT from_id FROM frames_2_sketches WHERE to_id = ?)
                ORDER BY f.id DESC
            ";
            $frames = $conn->fetchAllAssociative($sqlFrames, [$sketchId, $sketchId]);
            
            // 2. Fetch references for the Mini Graph
            $agNodeId = 0;
            $kgNodeId = 0;
            $loreDocId = null;

            $sqlHistory = "
                 SELECT slh.doc_id, slh.entity_type, slh.entity_name
                 FROM sketch_lore_history slh
                 WHERE slh.sketch_id = ?
                 ORDER BY slh.id DESC
            ";
            $histRows = $conn->fetchAllAssociative($sqlHistory, [$sketchId]);

            $anyEntityName  = null;
            $loreEntityName = null;

            foreach ($histRows as $row) {
                if ($anyEntityName === null) {
                    $anyEntityName = $row['entity_name'];
                }
                if ($loreDocId === null && substr($row['entity_type'], -1) === 's') {
                    $loreDocId      = (int)$row['doc_id'];
                    $loreEntityName = $row['entity_name'];
                }
                if ($anyEntityName !== null && $loreDocId !== null) break;
            }

            if ($loreDocId && $loreEntityName) {
                try {
                    $agNodeId = (int)$conn->fetchOne("SELECT id FROM ag_nodes WHERE doc_id = ? AND LOWER(name) = LOWER(?) AND status='active' LIMIT 1", [$loreDocId, $loreEntityName]);
                } catch (\Exception $e) {}
            }
            if ($anyEntityName) {
                try {
                    $kgNodeId = (int)$conn->fetchOne("SELECT id FROM kg_nodes WHERE LOWER(name) = LOWER(?) AND status='active' LIMIT 1", [$anyEntityName]);
                } catch (\Exception $e) {}
            }
            
            echo json_encode([
                'ok' => true, 
                'frames' => $frames,
                'ag_node_id' => $agNodeId,
                'kg_node_id' => $kgNodeId,
                'doc_id' => $loreDocId
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Initial Fetch (Page 1)
$limit = 25;
$totalCount = $conn->fetchOne("SELECT COUNT(*) FROM sketches");
$totalPages = ceil($totalCount / $limit);
$initialItems = $conn->fetchAllAssociative("SELECT id, name, mood, description, seed FROM sketches ORDER BY created_at DESC LIMIT $limit");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" id="viewportMeta" content="width=device-width, initial-scale=0.7">
    <title>Sketches Processor</title>
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="css/base.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Dependencies for Gallery -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <script type="module">
        import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery', children: 'a', pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    </script>

    <style>
        /* ── BASE ── */
        body { margin: 0; overflow: hidden; }

        /* ── LAYOUT ── */
        .layout { display: flex; height: 100vh; overflow: hidden; position: relative; }

        /* ── NAV TOGGLE (Fixed Top Left) ── */
        .nav-toggle-btn {
            position: fixed; top: 10px; left: 70px; z-index: 200;
            width: 40px; height: 40px; border-radius: 6px;
            background: #1a1a1a; border: 1px solid #333; color: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.2rem; transition: 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }
        .nav-toggle-btn:hover { border-color: var(--accent, #8b5cf6); color: var(--accent, #8b5cf6); }

        /* ── FLYOUT SIDEBAR ── */
        .flyout-sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 40%; background: #111; border-right: 1px solid #333;
            z-index: 150; transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            display: flex; flex-direction: column;
            padding-top: 0;
            box-shadow: 10px 0 30px rgba(0,0,0,0.5);
        }
        .flyout-sidebar.active { transform: translateX(0); }

        .sidebar-header {
            padding: 10px 15px; border-bottom: 1px solid #333; background: #151515;
            padding-top: 15px;
        }
        .sidebar-toggle { display: flex; background: #000; border-radius: 6px; padding: 2px; margin-bottom: 10px; }
        .toggle-btn { flex: 1; padding: 6px; text-align: center; cursor: pointer; border-radius: 4px; font-size: 0.8rem; color: #666; font-weight: bold; }
        .toggle-btn.active { background: #333; color: #fff; }

        .sidebar-list { flex: 1; overflow-y: auto; padding: 10px; }

        .item-row {
            padding: 10px; border-bottom: 1px solid #222; cursor: pointer;
            border-left: 3px solid transparent; border-radius: 4px; margin-bottom: 4px;
        }
        .item-row:hover { background: #222; }
        .item-row.active { background: #2a2a30; border-left-color: var(--accent, #8b5cf6); }

        .seq-group { border: 1px solid #333; margin-bottom: 5px; border-radius: 4px; overflow: hidden; }
        .seq-header { padding: 8px; background: #222; cursor: pointer; display: flex; justify-content: space-between; }
        .seq-body { display: none; background: #111; padding-left: 10px; }
        .seq-group.open .seq-body { display: block; }

        .pagination-bar { display: flex; justify-content: space-between; align-items: center; }
        .pager-btn { background: #333; border: 1px solid #444; color: #fff; width: 24px; height: 24px; cursor: pointer; border-radius: 3px; }
        .pager-btn:hover { background: #555; }

        /* ── MAIN WORKSPACE ── */
        .main { flex: 1; padding: 20px; overflow-y: auto; background: #1a1a1a; display: flex; flex-direction: column; padding-top: 60px; transition: margin-left 0.3s cubic-bezier(0.25, 1, 0.5, 1); }

        .layout.sidebar-open .main { margin-left: 40%; }
        .layout.sidebar-open #colRight { display: none; }

        /* Explicit min-width: 0 added to prevent Flexbox blowout */
        .editor-box { display: flex; gap: 20px; height: 100%; min-height: 0; min-width: 0; }
        .col { flex: 1; display: flex; flex-direction: column; min-height: 0; min-width: 0; }
        
        textarea { flex: 1; background: #000; color: #0f0; border: 1px solid #444; padding: 10px; font-family: monospace; resize: none; font-size: 0.9rem; }
        
        #rawContent { border: 1px solid #555; background: #080808; }
        #rawContent:focus { border-color: var(--accent, #8b5cf6); }

        .toolbar { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #333; }
        .toolbar-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .toolbar-controls { display: flex; gap: 15px; align-items: flex-end; background: #222; padding: 5px 15px 5px 15px; border-radius: 6px; }
        
        .control-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .control-group label { font-size: 0.7rem; color: #888; text-transform: uppercase; font-weight: bold; }
        
        select { background: #111; color: #eee; border: 1px solid #444; padding: 8px; border-radius: 4px; width: 100%; }
        
        .spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-right: 5px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 2000; display: none; align-items: center; justify-content: center; }
        .modal-card { background: #1a1a1a; width: 600px; padding: 25px; border-radius: 12px; border: 1px solid #444; box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: flex; flex-direction: column; gap: 15px; }
        .modal-card h2 { margin-top: 0; color: #fff; border-bottom: 1px solid #444; padding-bottom: 10px; margin-bottom: 0; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; color: #aaa; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group input, .form-group select { width: 100%; padding: 10px; background: #222; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
        
        /* Brainstorm specific textarea */
        #brainstormOutput { height: 300px; margin-top: 10px; }

        /* Gallery Styles with square confinement */
        .visual-container { height: 45%; display: none; flex-direction: column; border-top: 1px solid #333; padding-top: 10px; margin-top: 10px; min-width: 0; }
        
        #sketchSwiper { width: 100%; max-width: 320px; aspect-ratio: 1 / 1; margin-bottom: 10px; margin-left: 0; }
        
        .swiper-slide { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid #333; position: relative; box-sizing: border-box; }
        .swiper-slide img { width: 100%; height: 100%; display: block; object-fit: contain; }

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
</head>
<body style="margin:0">

<!-- Flyout Toggle Button -->
<div class="nav-toggle-btn" onclick="toggleSidebar()" title="Browse Sketches">
    <i class="bi bi-list"></i>
</div>

<div class="layout">

    <!-- FLYOUT SIDEBAR -->
    <div class="flyout-sidebar" id="flyoutSidebar">
        <div class="sidebar-header">
            <div class="sidebar-toggle">
                <div class="toggle-btn active" id="btnModeFlat" onclick="setMode('flat')">All Sketches</div>
                <div class="toggle-btn" id="btnModeSeq" onclick="setMode('sequences')">Sequences</div>
            </div>
            <div id="searchBarContainer" style="margin-bottom: 10px;">
                <input type="text" id="sidebarSearch" placeholder="Search by ID or Name..." 
                       style="width: 100%; padding: 5px; background: #222; border: 1px solid #333; color: #fff; border-radius: 4px; box-sizing: border-box;">
            </div>
            <div class="pagination-bar">
                <button class="pager-btn" onclick="changeSidebarPage(-1)">❮</button>
                <span style="font-size:0.8rem; color:#888;">
                    Page <input type="number" id="sidebarPageInput" 
                                style="width: 40px; background: #222; color: #fff; border: 1px solid #444; text-align: center; border-radius: 4px; margin: 0 4px;" 
                                value="1" min="1"> / <span id="sidebarTotPage"><?php echo $totalPages; ?></span>
                </span>
                <button class="pager-btn" onclick="changeSidebarPage(1)">❯</button>
            </div>
        </div>
        <div class="sidebar-list" id="sidebarContent">
            <!-- Initial Render (flat mode) -->
            <?php foreach($initialItems as $item): ?>
                <div class="item-row" onclick="selectItemFromSidebar(<?php echo $item['id']; ?>)">
                    <div style="font-weight:bold"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div style="font-size:0.7rem; opacity:0.7">
                        <?php echo $item['mood'] ? htmlspecialchars($item['mood']) : 'No Mood'; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    
   <div style="position:absolute;top:10px;left:150px;" class="toolbar-title-row">
                    <div>
                        <h3 id="wkTitle" style="margin:0; font-size:1rem">Select Item</h3>
                        <span id="wkType" class="badge" style="background:#444; padding:2px 6px; border-radius:4px; font-size:0.8rem">Mood</span>
                    </div>
                </div>


    <!-- MAIN WORKSPACE -->
    <div class="main">
    
        <div id="workspace" style="display:none; height:90%; flex-direction:column;">
            
            <div class="toolbar">
                
                <div class="toolbar-controls">
                    <!-- 1. Config -->
                    <div class="control-group">
                        <label>1. Processor</label>
                        <select id="genConfig">
                            <?php foreach($genConfigs as $g): ?>
                                <option value="<?php echo $g['config_id']; ?>" <?php echo strpos($g['config_id'], 'md_showcase') !== false ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Target -->
                    <div class="control-group">
                        <label>2. Target Generator</label>
                        <select id="targetConfig">
                            <?php foreach($genConfigs as $g): ?>
                                <option value="<?php echo $g['config_id']; ?>" <?php echo strpos($g['config_id'], 'desc_gen') !== false ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="toolbar-controls">

                    <!-- Actions -->
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-primary" id="btnGen" onclick="runAI()">✨ Generate</button>
                        <button class="btn btn-warning" id="btnBrainstorm" onclick="openBrainstormModal()">💡 Beats Brainstorm</button>
                        <button class="btn btn-success" id="btnPublish" style="display:none" onclick="publish()">🚀 Publish</button>
                        <button class="btn btn-secondary" id="btnAddScript" style="display:none" onclick="openScriptModal()">📜 Save to Docs</button>
                    </div>
                </div>
            </div>

            <div class="editor-box">
                <div class="col">
                    <div style="flex:1; display:flex; flex-direction:column; min-height:0;">
                        <h3 style="margin:0;">Scene Beats / Description (Editable)</h3>
                        <textarea id="rawContent"></textarea>
                    </div>
                    
                    <!-- Visual Gallery (Bottom Half) -->
                    <div id="visualContainer" class="visual-container">
                        
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div id="miniGraphTriggerContainer" style="display:none; gap:6px; flex-wrap:wrap; align-items:center;">
                                <!-- Buttons injected via JS -->
                            </div>
                            <span id="sketchTitle" style="font-weight:normal; color:#aaa; font-size:0.9rem;"></span>
                        </div>
                        
                        <!-- Fixed size square 1-item carousel -->
                        <div class="swiper pswp-gallery" id="sketchSwiper">
                            <div class="swiper-wrapper" id="sketchWrapper"></div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                        
                        <div style="flex:1; display:flex; flex-direction:column;">
                            <label style="font-size:0.7rem; color:#666; font-weight:bold; text-transform:uppercase;">Visual Description (Seed)</label>
                            <textarea id="sketchDesc" readonly style="flex:1; font-size:0.85rem; color:#bbb; background:#151515; border-color:#333;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="col" id="colRight">
                    <h3 style="margin:0;">Generated Output</h3>
                    <textarea id="mdOutput" placeholder="Select 'Processor' logic above and click Generate..."></textarea>
                </div>
            </div>
        </div>
        
        <div id="emptyState" style="text-align:center; padding-top:100px; color:#555;">
            <h2>Select a sketch from the sidebar</h2>
        </div>
    </div>
</div>

<!-- SCRIPT SAVING MODAL -->
<div id="scriptModal" class="modal-overlay">
    <div class="modal-card">
        <h2>Save to Documentation</h2>
        <div class="form-group">
            <label>Document Title</label>
            <input type="text" id="scriptTitle">
        </div>
        <div class="form-group">
            <label>Category</label>
            <div style="display:flex; gap:10px;">
                <select id="scriptCat">
                    <?php foreach($docCats as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-secondary" onclick="toggleNewCat()" title="New Category">+</button>
            </div>
        </div>
        <div class="form-group" id="newCatGroup" style="display:none; background:#222; padding:10px; border-radius:4px;">
            <label style="color:#fff;">Create New Category</label>
            <div style="display:flex; gap:5px;">
                <input type="text" id="newCatName" placeholder="Category Name">
                <button class="btn btn-sm btn-primary" onclick="createCategory()">Create</button>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeScriptModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveScript()">💾 Save Document</button>
        </div>
    </div>
</div>

<!-- BRAINSTORM MODAL -->
<div id="brainstormModal" class="modal-overlay">
    <div class="modal-card" style="width: 90%;">
        <div class="modal-header">
            <h2>💡 Beats Brainstorming</h2>
            <button class="btn btn-sm btn-secondary" onclick="closeBrainstormModal()">✕</button>
        </div>
        
        <div style="display:flex; gap: 10px; align-items: flex-end;">
            <div class="form-group" style="flex: 1;">
                <label>Select Brainstorming Config</label>
                <select id="brainstormConfigSelect">
                    <?php foreach($genConfigs as $g): ?>
                        <option value="<?php echo $g['config_id']; ?>">
                            <?php echo htmlspecialchars($g['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" id="btnRunBrainstorm" onclick="runBrainstorm()">Generate Ideas</button>
        </div>

        <textarea id="brainstormOutput" placeholder="Brainstorming results will appear here..."></textarea>
        
        <div class="modal-actions" style="justify-content: space-between;">
             <span style="font-size:0.8rem; color:#666; align-self:center;">Copy result and paste into the main editor.</span>
             <div style="display:flex; gap:10px;">
                <button class="btn btn-secondary" onclick="copyBrainstorm()">📋 Copy to Clipboard</button>
                <button class="btn btn-secondary" onclick="closeBrainstormModal()">Close</button>
             </div>
        </div>
    </div>
</div>

<!-- Frame View Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeIframeModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<script>
// --- GLOBAL CACHE ---
window.sketchCache = {};
<?php foreach($initialItems as $item): ?>
window.sketchCache[<?php echo $item['id']; ?>] = <?php echo json_encode($item); ?>;
<?php endforeach; ?>

let currentEntity = null;
let visualSwiper = null;

// ── FLYOUT SIDEBAR STATE ──
let sidebarMode = 'flat';
let sidebarPage = 1;
let sidebarTotalPages = <?php echo $totalPages; ?>;
let sidebarSearchQuery = '';
let sidebarSearchTimeout = null;

document.getElementById('rawContent').addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        runAI();
    }
});

$(function() {
    // Sidebar page input
    $('#sidebarPageInput').on('change', function() {
        let p = parseInt($(this).val());
        if (!isNaN(p) && p > 0) { sidebarPage = p; loadSidebar(); }
    });

    // Search
    $('#sidebarSearch').on('input', function() {
        sidebarSearchQuery = $(this).val();
        sidebarPage = 1;
        clearTimeout(sidebarSearchTimeout);
        sidebarSearchTimeout = setTimeout(loadSidebar, 300);
    });

    // Sequence group toggle delegation
    $(document).on('click', '.seq-header', function() {
        $(this).closest('.seq-group').toggleClass('open');
    });
});

// ── SIDEBAR CONTROLS ──
function toggleSidebar() {
    $('#flyoutSidebar').toggleClass('active');
    $('.layout').toggleClass('sidebar-open');
}

function setMode(mode) {
    if (sidebarMode === mode) return;
    sidebarMode = mode;
    sidebarPage = 1;
    $('.toggle-btn').removeClass('active');
    $('#btnMode' + (mode === 'flat' ? 'Flat' : 'Seq')).addClass('active');
    if (mode === 'flat') {
        $('#searchBarContainer').show();
    } else {
        $('#searchBarContainer').hide();
        sidebarSearchQuery = '';
        $('#sidebarSearch').val('');
    }
    loadSidebar();
}

function changeSidebarPage(d) {
    let newPage = sidebarPage + d;
    if (newPage < 1 || newPage > sidebarTotalPages) return;
    sidebarPage = newPage;
    loadSidebar();
}

function loadSidebar() {
    $('#sidebarContent').css('opacity', '0.5');
    $.post('', {
        action: 'fetch_sidebar',
        mode: sidebarMode,
        page: sidebarPage,
        search: sidebarSearchQuery
    }, function(res) {
        $('#sidebarContent').css('opacity', '1');
        if (!res.ok) return;

        sidebarPage = res.current_page;
        sidebarTotalPages = res.total_pages;
        $('#sidebarPageInput').val(sidebarPage);
        $('#sidebarTotPage').text(sidebarTotalPages);

        let html = '';

        if (res.mode === 'flat') {
            // Cache all items
            res.items.forEach(item => {
                window.sketchCache[item.id] = item;
            });
            res.items.forEach(item => {
                const mood = item.mood ? item.mood : 'No Mood';
                const activeClass = (currentEntity && currentEntity.id == item.id) ? 'active' : '';
                html += `<div class="item-row ${activeClass}" onclick="selectItemFromSidebar(${item.id})">
                    <div style="font-weight:bold">${escapeHtml(item.name)}</div>
                    <div style="font-size:0.7rem; opacity:0.7">${escapeHtml(mood)}</div>
                </div>`;
            });
        } else {
            // Sequences mode
            res.items.forEach(seq => {
                let itemsHtml = '';
                (seq.items || []).forEach(item => {
                    const activeClass = (currentEntity && currentEntity.id == item.id) ? 'active' : '';
                    itemsHtml += `<div class="item-row ${activeClass}" onclick="selectItemFromSidebar(${item.id})">
                        <div style="font-weight:bold">${escapeHtml(item.name)}</div>
                        <div style="font-size:0.7rem; opacity:0.7">${escapeHtml(item.mood || '-')}</div>
                    </div>`;
                });
                const openClass = (seq.parsed_sketches && currentEntity && seq.parsed_sketches.includes(currentEntity.id)) ? 'open' : '';
                html += `<div class="seq-group ${openClass}">
                    <div class="seq-header">
                        <span style="font-weight:bold;color:#ccc;">${escapeHtml(seq.name)}</span>
                        <span>▼</span>
                    </div>
                    <div class="seq-body">${itemsHtml}</div>
                </div>`;
            });
        }

        $('#sidebarContent').html(html);
    });
}

// ── SELECT ITEM FROM SIDEBAR ──
function selectItemFromSidebar(id) {
    const cached = window.sketchCache[id];
    if (cached) {
        selectItem(id, cached);
    } else {
        $.post('', { action: 'fetch_sidebar', mode: 'flat', page: 1, search: String(id) }, function(res) {
            if (res.ok && res.items.length) {
                const item = res.items[0];
                window.sketchCache[item.id] = item;
                selectItem(item.id, item);
            }
        });
    }
}

function selectItem(id, item) {
    if (!item) return;

    currentEntity = item;
    $('#emptyState').hide();
    $('#workspace').css('display', 'flex');

    // Update active state in sidebar
    $('.item-row').removeClass('active');
    $(`.item-row[onclick="selectItemFromSidebar(${id})"]`).addClass('active');

    $('#wkTitle').text(item.name);
    $('#wkType').text(item.mood || 'N/A');
    $('#rawContent').val(item.description);
    $('#mdOutput').val('');
    $('#btnPublish').hide();
    $('#btnAddScript').hide();

    // Clear Visuals Area
    $('#visualContainer').hide();
    $('#sketchWrapper').empty();
    $('#sketchDesc').val('');
    $('#miniGraphTriggerContainer').hide().empty();
    $('#sketchTitle').text('');

    $.post('', { action: 'fetch_frames', sketch_id: id }, function(res) {
        if(res.ok) {
            renderVisuals({
                name: item.name,
                seed: item.seed,
                frames: res.frames,
                kg_node_id: res.kg_node_id,
                ag_node_id: res.ag_node_id,
                doc_id: res.doc_id
            });
        }
    });
}

function renderVisuals(sketch) {
    $('#sketchDesc').val(sketch.seed || 'No seed data available.');
    $('#sketchTitle').text(sketch.name || '');
    
    // Build Mini Graph Triggers
    let mgHtml = '';
    if(sketch.kg_node_id > 0) {
        const kgUrl = `mini_graph.php?graph=kg&node_id=${sketch.kg_node_id}`;
        mgHtml += `<a href="${kgUrl}" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">🔮 KG</a>
                   <button onclick="openIframeModal('${kgUrl}')" style="background:none; border:1px solid #444; border-radius:4px; padding:2px 7px; cursor:pointer; color:#aaa; font-size:0.78em;">⤢ modal</button>`;
    }
    if(sketch.ag_node_id > 0) {
        const agUrl = `mini_graph.php?graph=ag&doc_id=${sketch.doc_id}&node_id=${sketch.ag_node_id}`;
        if(mgHtml !== '') mgHtml += '<span style="color:#444;">|</span>';
        mgHtml += `<a href="${agUrl}" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">📜 AG</a>
                   <button onclick="openIframeModal('${agUrl}')" style="background:none; border:1px solid #444; border-radius:4px; padding:2px 7px; cursor:pointer; color:#aaa; font-size:0.78em;">⤢ modal</button>`;
    }
    
    if(mgHtml !== '') {
        $('#miniGraphTriggerContainer').html(`<span style="font-size:0.7rem; color:#666; text-transform:uppercase; font-weight:bold;">Mini Graph</span> ${mgHtml}`).css('display', 'flex');
    } else {
        $('#miniGraphTriggerContainer').hide().empty();
    }
    
    const wrapper = $('#sketchWrapper');
    wrapper.empty();

    if (sketch.frames && sketch.frames.length > 0) {
        sketch.frames.forEach(f => {
            const safeUrl = f.filename;
            const slide = `
                <div class="swiper-slide">
                    <a href="${safeUrl}" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="${safeUrl}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                    </a>
                    <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openIframeModal('view_frame.php?frame_id=${f.id}&view=modal')"><i class="bi bi-arrows-fullscreen"></i></div>
                </div>
            `;
            wrapper.append(slide);
        });
        
        $('#visualContainer').css('display', 'flex');

        if (visualSwiper) {
            visualSwiper.destroy(true, true);
        }
        
        visualSwiper = new Swiper('#sketchSwiper', {
            slidesPerView: 1, /* Show only one at a time */
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });
    } else {
        $('#visualContainer').hide();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// ── AI GENERATION ──
function runAI() {
    if(!currentEntity) return;
    
    const configId = $('#genConfig').val();
    const customText = $('#rawContent').val();
    const btn = $('#btnGen');
    
    const originalText = btn.text();
    btn.html('<span class="spinner"></span> Working...');
    btn.prop('disabled', true);
    
    $('#mdOutput').val('Sending request to AI...');
    
    $.post('', { 
        action: 'generate_md', 
        id: currentEntity.id,
        config_id: configId,
        custom_text: customText
    }, function(res) {
        btn.html(originalText);
        btn.prop('disabled', false);

        if(res.ok) {
            $('#mdOutput').val(res.md);
            $('#btnPublish').show();
            $('#btnAddScript').show();
        } else {
            $('#mdOutput').val('Error: ' + res.error);
        }
    }).fail(function() {
        btn.html(originalText);
        btn.prop('disabled', false);
        $('#mdOutput').val('Network Error / Timeout.');
    });
}

// ── BRAINSTORM ──
function openBrainstormModal() {
    if(!currentEntity) return;
    $('#brainstormOutput').val('');
    $('#brainstormModal').fadeIn(200);
}

function closeBrainstormModal() {
    $('#brainstormModal').fadeOut(200);
}

function runBrainstorm() {
    const configId = $('#brainstormConfigSelect').val();
    const contextText = $('#rawContent').val();
    const btn = $('#btnRunBrainstorm');
    
    const originalText = btn.text();
    btn.html('<span class="spinner"></span> Thinking...');
    btn.prop('disabled', true);
    
    $('#brainstormOutput').val('Generating ideas...');

    $.post('', { 
        action: 'brainstorm_beats', 
        id: currentEntity.id, 
        config_id: configId,
        context_text: contextText 
    }, function(res) {
        btn.html(originalText);
        btn.prop('disabled', false);

        if(res.ok) {
            $('#brainstormOutput').val(res.text);
        } else {
            $('#brainstormOutput').val('Error: ' + res.error);
        }
    }).fail(function() {
        btn.html(originalText);
        btn.prop('disabled', false);
        $('#brainstormOutput').val('Network Error.');
    });
}

function copyBrainstorm() {
    const copyText = document.getElementById("brainstormOutput");
    copyText.select();
    copyText.setSelectionRange(0, 99999); 
    navigator.clipboard.writeText(copyText.value).then(() => {
        alert("Copied to clipboard!");
    });
}

// ── PUBLISH ──
function publish() {
    let md = $('#mdOutput').val();
    if(!md || md.length < 10) return alert('No Markdown content to publish');
    
    const targetId = $('#targetConfig').val();

    if(!confirm('Publish items to Showcase DB using selected Target Generator?')) return;

    $.post('', { 
        action: 'publish_showcase', 
        md: md, 
        staging_id: currentEntity.id,
        target_config_id: targetId 
    }, function(res) {
        if(res.ok) {
            alert(`Success! Created ${res.stats.inserted} showcase items.`);
        } else {
            alert('Error: ' + res.error);
        }
    });
}

// ── SCRIPT DOCS ──
function openScriptModal() {
    if(!currentEntity) return;
    const title = `${currentEntity.name} (${currentEntity.mood || 'Sketch'})`;
    $('#scriptTitle').val(title);
    $('#newCatGroup').hide();
    $('#scriptModal').fadeIn(200);
}

function closeScriptModal() {
    $('#scriptModal').fadeOut(200);
}

function toggleNewCat() {
    $('#newCatGroup').slideToggle();
}

function createCategory() {
    const name = $('#newCatName').val().trim();
    if(!name) return alert("Enter a name");
    
    fetch('api_md.php?action=create_category', {
        method: 'POST',
        body: JSON.stringify({ name: name })
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            const opt = new Option(res.name, res.id);
            $('#scriptCat').append(opt).val(res.id);
            $('#newCatGroup').slideUp();
            $('#newCatName').val('');
        } else {
            alert(res.message);
        }
    });
}

function saveScript() {
    const title = $('#scriptTitle').val().trim();
    const catId = $('#scriptCat').val();
    const content = $('#mdOutput').val();
    
    if(!title) return alert("Title required");
    if(!content) return alert("No content generated");

    fetch('api_md.php?action=save', {
        method: 'POST',
        body: JSON.stringify({
            name: title,
            category_id: catId,
            content: content
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            alert("Document Saved to Library! (ID: " + res.id + ")");
            closeScriptModal();
        } else {
            alert("Error: " + res.message);
        }
    });
}

// ── IFRAME MODAL ──
function openIframeModal(url) {
    document.getElementById('frameViewer').src = url;
    document.getElementById('viewModal').classList.add('active');
}
function closeIframeModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeIframeModal(); });

</script>

<?php echo $eruda; ?>

</body>
</html>