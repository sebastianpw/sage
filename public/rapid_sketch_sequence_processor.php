<?php
// public/rapid_sketch_sequence_processor.php
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
    $search = ["\xC2\xAB", "\xC2\xBB", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x93", "\xE2\x80\x94", "“", "”", "‘", "’", "—", "–"];
    $replace = ["<<", ">>", "'", "'", '"', '"', "-", "-", '"', '"', "'", "'", "-", "-"];
    $str = str_replace($search, $replace, $str);
    return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
}

// --- HELPER: FETCH SEQUENCES WITH SKETCHES ---
function fetchSequencesWithSketches($conn, $limit, $offset) {
    // 1. Fetch Sequences
    $sequences = $conn->fetchAllAssociative("
        SELECT id, name, description, sequence_data, created_at
        FROM narrative_sequences 
        ORDER BY created_at DESC 
        LIMIT $limit OFFSET $offset
    ");

    // 2. Extract Sketch IDs
    $allSketchIds = [];
    foreach ($sequences as &$seq) {
        $data = json_decode($seq['sequence_data'], true);
        $seq['parsed_sketches'] = [];
        
        if (is_array($data)) {
            foreach ($data as $item) {
                // HANDLE FORMAT: Simple Array of IDs [101, 102]
                if (is_numeric($item)) {
                    $sid = (int)$item;
                    $allSketchIds[] = $sid;
                    $seq['parsed_sketches'][] = $sid;
                }
                // HANDLE FORMAT: Array of Objects [{"sketch_id": 101}, ...]
                elseif (is_array($item) && isset($item['sketch_id'])) {
                    $sid = (int)$item['sketch_id'];
                    $allSketchIds[] = $sid;
                    $seq['parsed_sketches'][] = $sid;
                }
                // HANDLE FORMAT: Array of Objects [{"id": 101}, ...]
                elseif (is_array($item) && isset($item['id'])) {
                    $sid = (int)$item['id'];
                    $allSketchIds[] = $sid;
                    $seq['parsed_sketches'][] = $sid;
                }
            }
        }
    }
    unset($seq); // Break reference

    // 3. Fetch Sketch Details
    $sketchMap = [];
    if (!empty($allSketchIds)) {
        $uniqueIds = array_unique($allSketchIds);
        $idsStr = implode(',', $uniqueIds);
        
        // Fetch details for all IDs found in the page's sequences
        if (!empty($idsStr)) {
            $rows = $conn->fetchAllAssociative("
                SELECT id, name, mood, description, seed 
                FROM sketches 
                WHERE id IN ($idsStr)
            ");
            foreach ($rows as $r) {
                $sketchMap[$r['id']] = $r;
            }
        }
    }

    // 4. Hydrate Sequences
    $result = [];
    foreach ($sequences as $seq) {
        $hydratedSketches = [];
        foreach ($seq['parsed_sketches'] as $sid) {
            if (isset($sketchMap[$sid])) {
                $hydratedSketches[] = $sketchMap[$sid];
            }
        }
        
        $result[] = [
            'id' => $seq['id'],
            'name' => $seq['name'],
            'created_at' => $seq['created_at'],
            'items' => $hydratedSketches
        ];
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

    // A. FETCH SEQUENCES (PAGINATION)
    if ($input['action'] === 'fetch_sketches') { // Name kept for compatibility, fetches sequences
        try {
            $page = max(1, intval($input['page'] ?? 1));
            $limit = 10; // Fewer items per page since they are groups
            $offset = ($page - 1) * $limit;

            $totalCount = $conn->fetchOne("SELECT COUNT(*) FROM narrative_sequences");
            $totalPages = ceil($totalCount / $limit);

            $items = fetchSequencesWithSketches($conn, $limit, $offset);

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

    // B. GENERATE MD (Main Processor)
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

    // C. BRAINSTORM BEATS
    if ($input['action'] === 'brainstorm_beats') {
        set_time_limit(300); // 5 minutes max
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

    // D. PUBLISH TO SHOWCASE
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
}

// Initial Fetch (Page 1)
$limit = 10;
$totalCount = $conn->fetchOne("SELECT COUNT(*) FROM narrative_sequences");
$totalPages = ceil($totalCount / $limit);
$initialSequences = fetchSequencesWithSketches($conn, $limit, 0);

// Populate cache for initial render
$initialSketchCache = [];
foreach ($initialSequences as $seq) {
    foreach ($seq['items'] as $sk) {
        $initialSketchCache[$sk['id']] = $sk;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sequence Processor</title>
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="css/base.css">
    <style>
        .layout { display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 350px; background: #111; border-right: 1px solid #333; display: flex; flex-direction: column; }
        
        .sidebar-header { 
            padding: 10px; border-bottom: 1px solid #333; background: #1a1a1a; 
            display: flex; flex-direction: column; gap: 10px; 
        }
        .header-top { display: flex; justify-content: space-between; align-items: center; }
        .pagination-bar { display: flex; justify-content: space-between; align-items: center; background: #000; padding: 5px; border-radius: 4px; }
        .pager-btn { background: #333; border: none; color: #fff; width: 25px; height: 25px; border-radius: 3px; cursor: pointer; }
        .pager-btn:hover { background: #555; }
        .pager-btn:disabled { opacity: 0.3; cursor: default; }
        .page-input { width: 40px; text-align: center; background: #111; border: 1px solid #444; color: #fff; padding: 3px; font-size: 0.8rem; }
        
        .sidebar-list { flex: 1; overflow-y: auto; padding: 10px; }
        
        /* Sequence Group Styles */
        .seq-group { margin-bottom: 8px; border: 1px solid #333; border-radius: 4px; overflow: hidden; }
        
        .seq-header { 
            background: #222; padding: 10px; cursor: pointer; user-select: none;
            display: flex; justify-content: space-between; align-items: center;
            transition: background 0.2s;
        }
        .seq-header:hover { background: #2a2a2a; }
        
        .seq-title { font-weight: bold; font-size: 0.9rem; color: #ddd; }
        .seq-date { font-size: 0.7rem; color: #777; }
        
        .seq-body { display: none; background: #151515; padding: 5px 0; border-top: 1px solid #333; }
        .seq-group.open .seq-body { display: block; }
        
        .seq-caret { font-size: 0.8rem; color: #888; transition: transform 0.2s; }
        .seq-group.open .seq-caret { transform: rotate(90deg); }

        .item-row { 
            padding: 8px 10px 8px 25px; /* Indent sketches */
            border-bottom: 1px solid #222; cursor: pointer; transition: 0.2s; 
            display: flex; gap: 10px; align-items: center;
            border-left: 3px solid transparent;
        }
        .item-row:hover { background: #333; }
        .item-row.active { background: #333; border-left-color: var(--accent); }
        .item-row.active .sketch-name { color: var(--accent); }

        .main { flex: 1; padding: 20px; overflow-y: auto; background: #1a1a1a; display: flex; flex-direction: column; }
        
        .item-row { 
            padding: 10px; border-bottom: 1px solid #333; cursor: pointer; transition: 0.2s; 
            border-radius: 4px; margin-bottom: 4px; display: flex; gap: 10px; align-items: center;
        }
        .item-row:hover { background: #333; }
        .item-row.active { background: var(--accent); color: white; }

        .editor-box { display: flex; gap: 20px; height: 100%; }
        .col { flex: 1; display: flex; flex-direction: column; }
        textarea { flex: 1; background: #000; color: #0f0; border: 1px solid #444; padding: 10px; font-family: monospace; resize: none; font-size: 0.9rem; }
        
        #rawContent { border: 1px solid #555; background: #080808; }
        #rawContent:focus { border-color: var(--accent); }

        .toolbar { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #333; }
        .toolbar-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .toolbar-controls { display: flex; gap: 15px; align-items: flex-end; background: #222; padding: 15px; border-radius: 6px; }
        
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
    </style>
</head>
<body style="margin:0">

<div class="layout">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="header-top">
                <h3 style="margin:0; color:#fff;">Narrative Sequences</h3>
                <span style="font-size:0.7rem; color:#888;">Seqs: <?php echo $totalCount; ?></span>
            </div>
            
            <!-- Pagination Controls -->
            <div class="pagination-bar">
                <button class="pager-btn" id="btnPrev" onclick="changePage(-1)">❮</button>
                <div style="display:flex; align-items:center; gap:5px; font-size:0.8rem; color:#888;">
                    Page <input type="number" id="pageInput" class="page-input" value="1" min="1" onchange="jumpToPage()">
                    of <span id="totalDisplay"><?php echo $totalPages; ?></span>
                </div>
                <button class="pager-btn" id="btnNext" onclick="changePage(1)">❯</button>
            </div>
        </div>
        
        <div class="sidebar-list" id="sidebarList">
            <!-- Initial Render -->
            <?php foreach($initialSequences as $seq): ?>
                <div class="seq-group" id="seq_<?php echo $seq['id']; ?>">
                    <div class="seq-header" data-id="<?php echo $seq['id']; ?>">
                        <div>
                            <div class="seq-title"><?php echo htmlspecialchars($seq['name']); ?></div>
                            <div class="seq-date"><?php echo substr($seq['created_at'], 0, 10); ?></div>
                        </div>
                        <div class="seq-caret">▶</div>
                    </div>
                    <div class="seq-body">
                        <?php if(empty($seq['items'])): ?>
                            <div style="padding:10px; font-size:0.8rem; color:#666; text-align:center;">No sketches found</div>
                        <?php else: ?>
                            <?php foreach($seq['items'] as $item): ?>
                                <div class="item-row" 
                                     id="row_<?php echo $item['id']; ?>"
                                     onclick="selectItem(<?php echo $item['id']; ?>, this)">
                                    <div style="flex:1">
                                        <div class="sketch-name" style="font-weight:bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div style="font-size:0.7rem; opacity:0.7">
                                            <?php echo $item['mood'] ? htmlspecialchars($item['mood']) : 'No Mood'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN WORKSPACE -->
    <div class="main">
        <div id="workspace" style="display:none; height:100%; flex-direction:column;">
            
            <div class="toolbar">
                <div class="toolbar-title-row">
                    <div>
                        <h1 id="wkTitle" style="margin:0; font-size:1.5rem">Select Item</h1>
                        <span id="wkType" class="badge" style="background:#444; padding:2px 6px; border-radius:4px; font-size:0.8rem">Mood</span>
                    </div>
                </div>

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
                    <h3>Scene Beats / Description (Editable)</h3>
                    <textarea id="rawContent"></textarea>
                </div>
                <div class="col">
                    <h3>Generated Output</h3>
                    <textarea id="mdOutput" placeholder="Select 'Processor' logic above and click Generate..."></textarea>
                </div>
            </div>
        </div>
        
        <div id="emptyState" style="text-align:center; padding-top:100px; color:#555;">
            <h2>Expand a sequence and select a sketch</h2>
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
    <div class="modal-card" style="width: 700px;">
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

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<script>
// --- GLOBAL CACHE ---
window.sketchCache = <?php echo json_encode($initialSketchCache); ?>;
let currentEntity = null;
let currentPage = 1;
let totalPages = <?php echo $totalPages; ?>;

$(document).ready(function() {
    // Event delegation: Handles clicks on .seq-header for existing AND future elements
    $(document).on('click', '.seq-header', function() {
        $(this).closest('.seq-group').toggleClass('open');
    });
});

document.getElementById('rawContent').addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        runAI();
    }
});

// --- UI HELPERS ---
function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// --- PAGINATION ---
function changePage(delta) {
    let newPage = currentPage + delta;
    if (newPage < 1 || newPage > totalPages) return;
    loadPage(newPage);
}

function jumpToPage() {
    let val = parseInt($('#pageInput').val());
    if (val >= 1 && val <= totalPages) {
        loadPage(val);
    } else {
        $('#pageInput').val(currentPage);
    }
}

function loadPage(page) {
    $('#sidebarList').css('opacity', '0.5');
    // Using same action 'fetch_sketches' but it returns sequences now
    $.post('', { action: 'fetch_sketches', page: page }, function(res) {
        $('#sidebarList').css('opacity', '1');
        if (res.ok) {
            currentPage = res.current_page;
            totalPages = res.total_pages;
            
            $('#pageInput').val(currentPage);
            $('#totalDisplay').text(totalPages);
            $('#btnPrev').prop('disabled', currentPage === 1);
            $('#btnNext').prop('disabled', currentPage === totalPages);

            let html = '';
            window.sketchCache = {}; // Reset cache for new page
            
            res.items.forEach(seq => {
                const dateStr = seq.created_at ? seq.created_at.substring(0, 10) : '';
                let itemsHtml = '';
                
                if (seq.items.length === 0) {
                    itemsHtml = `<div style="padding:10px; font-size:0.8rem; color:#666; text-align:center;">No sketches found</div>`;
                } else {
                    seq.items.forEach(item => {
                        window.sketchCache[item.id] = item;
                        const mood = item.mood ? item.mood : 'No Mood';
                        const activeClass = (currentEntity && currentEntity.id == item.id) ? 'active' : '';
                        
                        itemsHtml += `
                            <div class="item-row ${activeClass}" 
                                 id="row_${item.id}"
                                 onclick="selectItem(${item.id}, this)">
                                <div style="flex:1">
                                    <div class="sketch-name" style="font-weight:bold">${escapeHtml(item.name)}</div>
                                    <div style="font-size:0.7rem; opacity:0.7">${escapeHtml(mood)}</div>
                                </div>
                            </div>
                        `;
                    });
                }

                html += `
                <div class="seq-group" id="seq_${seq.id}">
                    <div class="seq-header" data-id="${seq.id}">
                        <div>
                            <div class="seq-title">${escapeHtml(seq.name)}</div>
                            <div class="seq-date">${dateStr}</div>
                        </div>
                        <div class="seq-caret">▶</div>
                    </div>
                    <div class="seq-body">
                        ${itemsHtml}
                    </div>
                </div>`;
            });
            $('#sidebarList').html(html);
        }
    });
}

function selectItem(id, el) {
    const item = window.sketchCache[id];
    if (!item) return;

    currentEntity = item;
    $('#emptyState').hide();
    $('#workspace').css('display', 'flex');
    $('.item-row').removeClass('active');
    $(el).addClass('active');
    
    $('#wkTitle').text(item.name);
    $('#wkType').text(item.mood || 'N/A');
    $('#rawContent').val(item.description);
    $('#mdOutput').val('');
    $('#btnPublish').hide();
    $('#btnAddScript').hide();
}

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

// --- BRAINSTORM LOGIC ---
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

// --- SCRIPT DOCS LOGIC ---
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
</script>
</body>
</html>