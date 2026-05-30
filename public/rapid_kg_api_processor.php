<?php
// public/rapid_kg_api_processor.php
// "The Curator's Studio" - Generates Showcases from Knowledge Graph Entities
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
    $search =["\xC2\xAB", "\xC2\xBB", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x93", "\xE2\x80\x94", "“", "”", "‘", "’", "—", "–"];
    $replace =["<<", ">>", "'", "'", '"', '"', "-", "-", '"', '"', "'", "'", "-", "-"];
    $str = str_replace($search, $replace, $str);
    return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
}

// --- HELPER: GET KG CATEGORY FAMILY ---
function getKgCatFamily($conn, $topCatId) {
    if ($topCatId <= 0) return[];
    $allCats = $conn->fetchAllAssociative("SELECT id, parent_id FROM kg_categories");
    $childrenMap =[];
    foreach ($allCats as $c) {
        $childrenMap[$c['parent_id'] ?? 0][] = $c['id'];
    }
    $family = [(int)$topCatId];
    $queue = [(int)$topCatId];
    while (!empty($queue)) {
        $curr = array_shift($queue);
        if (isset($childrenMap[$curr])) {
            foreach ($childrenMap[$curr] as $childId) {
                $family[] = $childId;
                $queue[] = $childId;
            }
        }
    }
    return $family;
}

// --- FETCH DATA ---
$genConfigs = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC");
$docCats = $conn->fetchAllAssociative("SELECT id, name FROM documentation_categories ORDER BY name ASC"); // For script modal

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = $_POST;

    // A. LOAD ENTITY LIST FROM KNOWLEDGE GRAPH
    if ($input['action'] === 'load_doc_entities') {
        try {
            $catId = (int)$input['doc_id'];
            
            $where = "WHERE status = 'active'";
            $params =[];

            if ($catId > 0) {
                $family = getKgCatFamily($conn, $catId);
                $ph = implode(',', array_fill(0, count($family), '?'));
                $where .= " AND category_id IN ($ph)";
                $params = $family;
            }

            $sql = "SELECT id, name, node_type FROM kg_nodes $where ORDER BY name ASC";
            $nodes = $conn->fetchAllAssociative($sql, $params);

            $payload = [];
            foreach ($nodes as $n) {
                $type = !empty($n['node_type']) ? $n['node_type'] : 'other';
                if (!isset($payload[$type])) $payload[$type] =[];
                $payload[$type][] = $n;
            }
            
            echo json_encode(['ok' => true, 'data' => $payload]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // B. FETCH SEMANTIC CONTEXT & SKETCH PREVIEW
    if ($input['action'] === 'fetch_context') {
        try {
            $nodeId = (int)($input['node_id'] ?? 0);
            $entityName = $input['entity_name'];
            $entityType = $input['entity_type'] ?? '';
            
            // 1. Semantic Data
            $node = $conn->fetchAssociative("SELECT * FROM kg_nodes WHERE id = ?", [$nodeId]);
            $contextStr = "";
            
            if ($node) {
                $items = $conn->fetchAllAssociative("SELECT * FROM kg_node_items WHERE node_id = ? ORDER BY sort_order ASC",[$nodeId]);
                $network = [];
                foreach ($items as $it) {
                    $lbl = $it['item_label'] ?? ('ID:' . $it['item_id']);
                    $relStr = $lbl . " (" . $it['item_type'] . ")";
                    if ($it['relationship']) $relStr .= " - " . $it['relationship'];
                    if ($it['note']) $relStr .= ": " . $it['note'];
                    $network[] = $relStr;
                }

                $contextData =[
                    'identity' => [
                        'name' => $node['name'],
                        'type' => $node['node_type'],
                        'description' => $node['description'] ?? '',
                        'keywords' => $node['keywords'] ?? ''
                    ],
                    'network' => $network,
                    'content' => $node['content'] ?? ''
                ];

                $contextStr = "IDENTITY:\n" . json_encode($contextData['identity'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n\n";
                if (!empty($network)) {
                    $contextStr .= "RELATIONSHIPS:\n" . implode("\n", $contextData['network']) . "\n\n";
                }
                if (!empty($contextData['content'])) {
                    $contextStr .= "CONTENT:\n" . $contextData['content'];
                }
            } else {
                $contextStr = "No semantic data found for this KG node.";
            }

            // 2. Visual Sketch Lookup
            $sketchData = null;
            if ($entityName) {
                // Find latest sketch generated for this entity name (independent of curated doc ID to ensure cross-compatibility)
                $sqlHistory = "
                    SELECT slh.sketch_id, s.name, s.description
                    FROM sketch_lore_history slh
                    JOIN sketches s ON slh.sketch_id = s.id
                    WHERE slh.entity_name = ?
                    ORDER BY slh.id DESC LIMIT 1
                ";
                $historyRow = $conn->fetchAssociative($sqlHistory, [$entityName]);
                
                if ($historyRow) {
                    $sketchId = $historyRow['sketch_id'];
                    // Fetch Frames
                    $sqlFrames = "
                        SELECT f.id, f.filename
                        FROM frames f
                        WHERE (f.entity_type = 'sketches' AND f.entity_id = ?)
                           OR f.id IN (SELECT from_id FROM frames_2_sketches WHERE to_id = ?)
                        ORDER BY f.id DESC
                    ";
                    $frames = $conn->fetchAllAssociative($sqlFrames, [$sketchId, $sketchId]);
                    
                    $sketchData = [
                        'id' => $sketchId,
                        'name' => $historyRow['name'],
                        'description' => $historyRow['description'],
                        'frames' => $frames
                    ];
                }
            }
            
            echo json_encode(['ok' => true, 'context' => $contextStr, 'sketch' => $sketchData]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // C. GENERATE SHOWCASE MD
    if ($input['action'] === 'generate_md') {
        try {
            $entityName = $input['entity_name'];
            $configId = $input['config_id'];
            $contextStr = $input['context_override'] ?? '';

            if (empty($contextStr)) throw new Exception("Context is empty. Please reload the entity.");

            $genConfig = $conn->fetchAssociative("SELECT * FROM generator_config WHERE config_id = ?", [$configId]);
            if (!$genConfig) throw new Exception("Generator configuration not found");

            $ai = new AIProvider();
            $instructionsArr = json_decode($genConfig['instructions'], true) ?? [];
            $sysPrompt = $genConfig['system_role'] . "\n\n" . implode("\n", $instructionsArr);
            $sysPrompt .= "\n\nCRITICAL OUTPUT FORMAT:\nFor each visual shot, use:\n### REF_CODE: Title\nContext: ...\n```\n(Visual Prompt)\n```";

            $refBase = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $entityName));
            $userPrompt = "BASE CODE: {$refBase}\nENTITY: {$entityName}\n\n--- SEMANTIC DATA ---\n" . $contextStr;

            $model = $genConfig['model'] && $genConfig['model'] !== 'openai' ? $genConfig['model'] : AIProvider::getDefaultModel();
            
            $generatedMd = $ai->sendPrompt($model, $userPrompt, $sysPrompt, ['temperature' => 0.7]);
            
            echo json_encode(['ok' => true, 'md' => $generatedMd]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // D. PUBLISH SHOWCASE
    if ($input['action'] === 'publish_showcase') {
        try {
            $mdContent = $input['md'];
            $targetConfigId = $input['target_config_id'];
            $categoryName = sanitizeText($input['category_name'] ?? 'KG Imported');

            $pattern = '/###\s*([A-Z0-9-_]+):\s*(.*?)\s*\n.*?```(.*?)```/s';
            $stats = ['inserted' => 0];

            if (preg_match_all($pattern, $mdContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $ref = trim($m[1]);
                    $title = sanitizeText(trim($m[2]));
                    $prompt = sanitizeText(preg_replace('/\s+/', ' ', trim($m[3])));

                    $conn->insert('rapid_showcase',[
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
}

// --- VIEW DATA: FETCH KG CATEGORIES INSTEAD OF DOCS ---
function buildCatOptions($cats, $parentId = null, $level = 0) {
    $html = '';
    foreach ($cats as $c) {
        if ($c['parent_id'] == $parentId) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
            $html .= '<option value="' . $c['id'] . '">' . $indent . htmlspecialchars($c['name']) . '</option>';
            $html .= buildCatOptions($cats, $c['id'], $level + 1);
        }
    }
    return $html;
}

$allKgCats = $conn->fetchAllAssociative("SELECT id, parent_id, name, sort_order FROM kg_categories ORDER BY sort_order ASC, name ASC");
$catOptionsHtml = buildCatOptions($allKgCats);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" id="viewportMeta" content="width=device-width, initial-scale=0.7">
    <title>KG Rapid API Processor</title>
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

        .sidebar-header { padding: 15px; border-bottom: 1px solid #333; background: #1a1a1a; }
        .sidebar-list { flex: 1; overflow-y: auto; padding: 0; }
        
        .main { flex: 1; padding: 20px; overflow-y: auto; background: #1a1a1a; display: flex; flex-direction: column; padding-top: 60px; transition: margin-left 0.3s cubic-bezier(0.25, 1, 0.5, 1); }
        
        .layout.sidebar-open .main { margin-left: 40%; }
        .layout.sidebar-open #colRight { display: none; }

        .doc-select-wrap { margin-bottom: 10px; }
        .doc-select { width: 100%; padding: 8px; background: #222; color: #fff; border: 1px solid #444; border-radius: 4px; }

        /* Sidebar KG Classes */
        .cat-header { 
            padding: 12px 15px; background: #1e1e1e; color: #ccc; font-size: 0.75rem; text-transform: uppercase; 
            font-weight: bold; border-bottom: 1px solid #333; border-top: 1px solid #333;
            cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none;
            transition: background 0.2s;
        }
        .cat-header:hover { background: #2a2a2a; color: #fff; }
        .cat-icon { transition: transform 0.2s ease; font-size: 0.8em; color: #666; }
        .entity-group { display: none; background: #151515; }
        .entity-row { 
            padding: 10px 15px 10px 25px; border-bottom: 1px solid #222; cursor: pointer; transition: 0.2s; 
            display: flex; justify-content: space-between; align-items: center; color: #aaa; font-size: 0.9rem;
            border-left: 3px solid transparent;
        }
        .entity-row:hover { background: #222; color: #fff; }
        .entity-row.active { background: #2a2a30; color: white; border-left-color: var(--accent, #8b5cf6); }
        
        /* explicit min-width: 0 added to prevent Flexbox blowout */
        .editor-box { display: flex; gap: 20px; height: 100%; min-height: 0; min-width: 0; }
        .col { flex: 1; display: flex; flex-direction: column; min-height: 0; min-width: 0; }
        
        textarea { flex: 1; background: #000; color: #0f0; border: 1px solid #444; padding: 10px; font-family: monospace; resize: none; font-size: 0.9rem; }
        
        .toolbar { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #333; }
        .toolbar-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .toolbar-controls { display: flex; gap: 15px; align-items: flex-end; background: #222; padding: 5px 15px 5px 15px; border-radius: 6px; }
        
        .control-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .control-group label { font-size: 0.7rem; color: #888; text-transform: uppercase; font-weight: bold; }
        select { background: #111; color: #eee; border: 1px solid #444; padding: 8px; border-radius: 4px; width: 100%; }
        
        .spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 2000; display: none; align-items: center; justify-content: center; }
        .modal-card { background: #1a1a1a; width: 500px; padding: 25px; border-radius: 12px; border: 1px solid #444; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .modal-card h2 { margin-top: 0; color: #fff; border-bottom: 1px solid #444; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; color: #aaa; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group input, .form-group select { width: 100%; padding: 10px; background: #222; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

        /* Gallery Styles with square confinement */
        .visual-container { height: 45%; display: none; flex-direction: column; border-top: 1px solid #333; padding-top: 10px; margin-top: 10px; min-width: 0; }
        
        /* Added hardcoded overflow:hidden */
        #sketchSwiper { width: 100%; max-width: 320px; aspect-ratio: 1 / 1; margin-bottom: 10px; margin-left: 0; overflow: hidden; }
        
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
<div class="nav-toggle-btn" onclick="toggleSidebar()" title="Browse KG Nodes">
    <i class="bi bi-list"></i>
</div>

<div class="layout">
    <!-- FLYOUT SIDEBAR -->
    <div class="flyout-sidebar" id="flyoutSidebar">
        <div class="sidebar-header">
            <h3 style="margin:0 0 10px 0; color:#fff;">🔮 KG Rapid Processor</h3>
            <div class="doc-select-wrap">
                <select id="docSelect" class="doc-select" onchange="loadEntities()">
                    <option value="">-- All KG Nodes --</option>
                    <?php echo $catOptionsHtml; ?>
                </select>
            </div>
            <input type="text" id="filterInput" placeholder="Filter entities..." 
                   style="width:100%; padding:6px; background:#111; border:1px solid #444; color:#fff; border-radius:4px;"
                   onkeyup="filterList()">
        </div>
        
        <div class="sidebar-list" id="entityList">
            <div style="padding:20px; text-align:center; color:#555;">Select a category or view all to load nodes.</div>
        </div>
    </div>
    
    
    <div style="position:absolute;left:150px;top:10px;" class="toolbar-title-row">
                    <div>
                        <h3 id="wkTitle" style="margin:0; font-size:1rem">Entity Name</h3>
                        <span id="wkType" class="badge" style="background:#444; padding:2px 6px; border-radius:4px; font-size:0.8rem">Type</span>
                    </div>
                </div>

    <!-- MAIN WORKSPACE -->
    <div class="main">
        <div id="workspace" style="display:none; height:90%; flex-direction:column;">
            
            <div class="toolbar">
                

                <div class="toolbar-controls">
                    <div class="control-group">
                        <label>1. Showcase Architect</label>
                        <select id="genConfig">
                            <?php foreach($genConfigs as $g): ?>
                                <option value="<?php echo $g['config_id']; ?>" <?php echo strpos($g['config_id'], 'md_showcase') !== false ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="control-group">
                        <label>2. Target Generator (CLI)</label>
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

                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-primary" id="btnGen" onclick="runAI()">✨ Generate</button>
                        <button class="btn btn-success" id="btnPublish" style="display:none" onclick="publish()">🚀 Publish</button>
                        <button class="btn btn-secondary" id="btnAddScript" style="display:none" onclick="openScriptModal()">📜 Add to Scripts</button>
                    </div>
                </div>
            </div>

            <div class="editor-box">
                <!-- Center Column -->
                <div class="col">
                    <!-- Semantic Data (Top Half) -->
                    <div style="flex:1; display:flex; flex-direction:column; min-height:0;">
                        <h3 style="margin-top:0;">Semantic Data Context</h3>
                        <textarea id="rawContent"></textarea>
                    </div>
                    
                    <!-- Visual Gallery (Bottom Half) -->
                    <div id="visualContainer" class="visual-container">
                        
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div id="miniGraphTriggerContainer" style="display:none; gap:6px; flex-wrap:wrap; align-items:center;"></div>
                            <span id="sketchTitle" style="font-weight:normal; color:#aaa; font-size:0.9rem;"></span>
                        </div>
                        

                        <!-- Fixed size square 1-item carousel -->
                        <div class="swiper pswp-gallery" id="sketchSwiper">
                            <div class="swiper-wrapper" id="sketchWrapper"></div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                        
                        <div style="flex:1; display:flex; flex-direction:column;">
                            <label style="font-size:0.7rem; color:#666; font-weight:bold; text-transform:uppercase;">Visual Description</label>
                            <textarea id="sketchDesc" readonly style="flex:1; font-size:0.85rem; color:#bbb; background:#151515; border-color:#333;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col" id="colRight">
                    <h3 style="margin-top:0;">Generated Showcase Markdown</h3>
                    <textarea id="mdOutput" placeholder="AI output will appear here..."></textarea>
                </div>
            </div>
        </div>
        
        <div id="emptyState" style="text-align:center; padding-top:100px; color:#555;">
            <h2>Select a KG node from the sidebar</h2>
        </div>
    </div>
</div>

<!-- SCRIPT SAVING MODAL -->
<div id="scriptModal" class="modal-overlay">
    <div class="modal-card">
        <h2>Save to Scripts Library</h2>
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

<!-- Frame View Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<script>
let currentEntity = null;
let currentDocId = null;
let currentDocName = '';
let visualSwiper = null;

// Initialization: load all entities directly if needed, or wait for selection
$(document).ready(function() {
    loadEntities(); // Auto-load all nodes initially
    toggleSidebar(); // Open sidebar by default to prompt user selection
});

// ── SIDEBAR CONTROLS ──
function toggleSidebar() {
    $('#flyoutSidebar').toggleClass('active');
    $('.layout').toggleClass('sidebar-open');
}

// --- ENTITY LOADING ---
function loadEntities() {
    const docId = $('#docSelect').val();
    currentDocName = $('#docSelect option:selected').text().trim().replace(/&nbsp;/g, '') || 'All Categories';
    currentDocId = docId;

    $('#entityList').html('<div style="padding:20px; text-align:center;">Loading...</div>');
    
    $.post('', { action: 'load_doc_entities', doc_id: docId }, function(res) {
        if(res.ok) renderList(res.data);
        else $('#entityList').html('<div style="color:red; padding:20px;">Error loading entities.</div>');
    });
}

function renderList(data) {
    let html = '';
    const types = Object.keys(data).sort(); // Dynamically load all node types returned from KG
    
    types.forEach(type => {
        if (data[type] && data[type].length > 0) {
            const displayType = type.replace(/_/g, ' ').toUpperCase();
            const count = data[type].length;
            
            // Default to open unless previously explicitly closed
            const isMsgOpen = localStorage.getItem('kg_cat_' + type) !== 'closed';
            const displayStyle = isMsgOpen ? 'display:block;' : 'display:none;';
            const iconStyle = isMsgOpen ? 'transform: rotate(90deg);' : '';
            
            html += `
            <div class="cat-section">
                <div class="cat-header" onclick="toggleCat('${type}')">
                    <span>${displayType} <span style="color:#666; font-size:0.8em; margin-left:5px;">${count}</span></span>
                    <span id="icon-${type}" class="cat-icon" style="${iconStyle}">▶</span>
                </div>
                <div id="group-${type}" class="entity-group" style="${displayStyle}">`;
                
            
            data[type].forEach(item => {
                const name = item.name || 'Unknown';
                // JS-escape double quotes & backslashes, HTML-escape single quotes for the attribute boundary, remove newlines
                const safeName = name.replace(/\\/g, "\\\\").replace(/'/g, "&#39;").replace(/"/g, '\\"').replace(/[\r\n]+/g, " ");
                // HTML-escape the display name to prevent UI breaking on < > &
                const displayLabel = name.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                const meta = `{ "id": ${item.id}, "name": "${safeName}", "type": "${type}" }`;
                html += `<div class="entity-row" onclick='selectEntity(this, ${meta})'><span>${displayLabel}</span></div>`;
            });

            
            
            

            html += `</div></div>`;
        }
    });

    if(html === '') html = '<div style="padding:20px; text-align:center; color:#555;">No nodes found.</div>';
    $('#entityList').html(html);
}

function toggleCat(type) {
    const group = $('#group-' + type);
    const icon = $('#icon-' + type);
    const isHidden = group.css('display') === 'none';
    
    if (isHidden) {
        group.slideDown(200);
        icon.css('transform', 'rotate(90deg)');
        localStorage.setItem('kg_cat_' + type, 'open');
    } else {
        group.slideUp(200);
        icon.css('transform', 'rotate(0deg)');
        localStorage.setItem('kg_cat_' + type, 'closed');
    }
}

function filterList() {
    const term = $('#filterInput').val().toLowerCase();
    $('.entity-row').each(function() {
        const match = $(this).text().toLowerCase().indexOf(term) > -1;
        $(this).toggle(match);
        if (term.length > 0 && match) {
            $(this).parent().show();
        }
    });
}

function selectEntity(el, meta) {
    currentEntity = meta;
    
    // Auto-hide the sidebar upon selection for better viewing
    // $('#flyoutSidebar').removeClass('active'); // Removed to keep sidebar open for quick browsing
    
    $('.entity-row').removeClass('active');
    $(el).addClass('active');
    
    $('#emptyState').hide();
    $('#workspace').css('display', 'flex');
    $('#wkTitle').text(meta.name);
    $('#wkType').text(meta.type.replace(/_/g, ' '));
    $('#btnPublish').hide();
    $('#btnAddScript').hide();
    $('#mdOutput').val('');
    
    $('#rawContent').val('Loading context from Knowledge Graph API...');
    
    // Clear Visuals Area
    $('#visualContainer').hide();
    $('#sketchWrapper').empty();
    $('#sketchDesc').val('');

    // Fetch Data
    $.post('', { action: 'fetch_context', doc_id: currentDocId, node_id: meta.id, entity_name: meta.name, entity_type: meta.type }, function(res) {
        if(res.ok) {
            
            
            
            const mgUrl = `mini_graph.php?graph=kg&node_id=${meta.id}`;
            $('#miniGraphTriggerContainer').html(`
                <span style="font-size:0.7rem; color:#666; text-transform:uppercase; font-weight:bold;">Mini Graph</span>
                <a href="${mgUrl}" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">🔮 KG</a>
                <button onclick="openIframeModal('${mgUrl}')" style="background:none; border:1px solid #444; border-radius:4px; padding:2px 7px; cursor:pointer; color:#aaa; font-size:0.78em;">⤢ modal</button>
            `).css('display', 'flex');
                        
            
            
            $('#rawContent').val(res.context);
            
            // Handle Visuals
            if (res.sketch) {
                renderVisuals(res.sketch);
            }
        } else {
            $('#rawContent').val('Error: ' + res.error);
        }
    });
}

function renderVisuals(sketch) {
    $('#sketchDesc').val(sketch.description || 'No visual description available.');
    
    const wrapper = $('#sketchWrapper');
    wrapper.empty();

    if (sketch.frames && sketch.frames.length > 0) {
        sketch.frames.forEach(f => {
            const safeUrl = f.filename;
            // Slide with PhotoSwipe Link and Modal Icon
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
        
        // Expose the display BEFORE running any Swiper updates
        $('#visualContainer').css('display', 'flex');

        // DO NOT DESTROY THE SWIPER
        // Initialize it only once, and leverage Swiper's native update method on subsequent calls.
        if (!visualSwiper) {
            visualSwiper = new Swiper('#sketchSwiper', {
                slidesPerView: 1, /* Show only one at a time */
                spaceBetween: 10,
                observer: true,
                observeParents: true,
                observeSlideChildren: true,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });
        } else {
            // Explicitly tell Swiper to recalculate dimensions and slide to the beginning
            visualSwiper.update();
            visualSwiper.slideTo(0);
        }
    } else {
        wrapper.html('<div class="swiper-slide" style="color:#666; font-size:0.9rem; border:none; background:transparent; display:flex; align-items:center; justify-content:center;">No frames generated yet</div>');
        $('#visualContainer').hide();
    }
}

// --- GENERATION ---
function runAI() {
    if(!currentEntity) return;
    const configId = $('#genConfig').val();
    const contextContent = $('#rawContent').val();
    const btn = $('#btnGen');
    const origText = btn.text();
    
    if (contextContent.length < 10) return alert("Please wait for context to load.");

    btn.html('<span class="spinner"></span> Working...');
    btn.prop('disabled', true);
    
    $.post('', { 
        action: 'generate_md', 
        entity_name: currentEntity.name,
        config_id: configId,
        context_override: contextContent 
    }, function(res) {
        btn.html(origText);
        btn.prop('disabled', false);

        if(res.ok) {
            $('#mdOutput').val(res.md);
            $('#btnPublish').show();
            $('#btnAddScript').show(); 
        } else {
            $('#mdOutput').val('Error: ' + res.error);
        }
    }).fail(function() {
        btn.html(origText);
        btn.prop('disabled', false);
        $('#mdOutput').val('Network Error.');
    });
}

function publish() {
    let md = $('#mdOutput').val();
    if(!md || md.length < 10) return alert('No content to publish');
    const targetId = $('#targetConfig').val();
    
    if(!confirm('Publish items to Showcase DB?')) return;

    $.post('', { 
        action: 'publish_showcase', 
        md: md, 
        target_config_id: targetId,
        category_name: currentDocName 
    }, function(res) {
        if(res.ok) alert(`Success! Created ${res.stats.inserted} showcase items.`);
        else alert('Error: ' + res.error);
    });
}

// --- SCRIPT LIBRARY INTEGRATION ---
function openScriptModal() {
    if(!currentEntity) return;
    const title = `${currentEntity.name} (${currentEntity.type})`;
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

// --- IFRAME MODAL ---
function openIframeModal(url) {
    document.getElementById('frameViewer').src = url;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeFrameModal(); });


</script>
<?php //echo $eruda ?? ''; ?>
</body>
</html>