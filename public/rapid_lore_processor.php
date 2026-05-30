<?php
// public/rapid_lore_processor.php - Final Polish
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

// --- FETCH CONFIGS ---
$genConfigs = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC");

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = $_POST;

    // A. GENERATE MD
    if ($input['action'] === 'generate_md') {
        try {
            $id = $input['id'];
            $configId = $input['config_id'];

            $entity = $conn->fetchAssociative("SELECT * FROM lore_entities WHERE id = ?", [$id]);
            if (!$entity) throw new Exception("Entity not found");

            $genConfig = $conn->fetchAssociative("SELECT * FROM generator_config WHERE config_id = ?", [$configId]);
            if (!$genConfig) throw new Exception("Generator configuration not found");

            $ai = new AIProvider();
            $instructionsArr = json_decode($genConfig['instructions'], true) ?? [];
            $sysPrompt = $genConfig['system_role'] . "\n\n" . implode("\n", $instructionsArr);
            
            $userPrompt = "BASE CODE: {$entity['ref_code']}\nENTITY TITLE: {$entity['title']} ({$entity['type']})\n\n--- RAW LORE ---\n" . $entity['raw_content'];

            $model = $genConfig['model'] && $genConfig['model'] !== 'openai' ? $genConfig['model'] : AIProvider::getDefaultModel();
            
            $generatedMd = $ai->sendPrompt($model, $userPrompt, $sysPrompt, ['temperature' => 0.7]);
            echo json_encode(['ok' => true, 'md' => $generatedMd]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // B. PUBLISH (Using Target Config)
    if ($input['action'] === 'publish_showcase') {
        try {
            $mdContent = $input['md'];
            $stagingId = $input['staging_id'];
            $targetConfigId = $input['target_config_id']; // This is the crucial fix
            
            $stagingEntity = $conn->fetchAssociative("SELECT title FROM lore_entities WHERE id = ?", [$stagingId]);
            $categoryName = $stagingEntity ? sanitizeText($stagingEntity['title']) : 'Imported Entity';

            $pattern = '/###\s*([A-Z0-9-_]+):\s*(.*?)\s*\n.*?```(.*?)```/s';
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
                        'generator_config_id' => $targetConfigId, // Correct Config assigned here
                        'is_generated' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    $stats['inserted']++;
                }
            }
            $conn->update('lore_entities', ['processed' => 1], ['id' => $stagingId]);
            echo json_encode(['ok' => true, 'stats' => $stats]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // C. BULK DELETE STAGING
    if ($input['action'] === 'delete_staging') {
        try {
            $ids = json_decode($input['ids'], true);
            if (!empty($ids)) {
                $conn->executeQuery("DELETE FROM lore_entities WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");
            }
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

$stagingItems = $conn->fetchAllAssociative("SELECT * FROM lore_entities ORDER BY created_at DESC, processed ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lore Processor</title>
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="css/base.css">
    <style>
        .layout { display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 350px; background: #111; border-right: 1px solid #333; display: flex; flex-direction: column; }
        .sidebar-header { padding: 15px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; }
        .sidebar-list { flex: 1; overflow-y: auto; padding: 10px; }
        .sidebar-footer { padding: 15px; border-top: 1px solid #333; background: #1a1a1a; display: none; }
        
        .main { flex: 1; padding: 20px; overflow-y: auto; background: #1a1a1a; display: flex; flex-direction: column; }
        
        .item-row { 
            padding: 10px; border-bottom: 1px solid #333; cursor: pointer; transition: 0.2s; 
            border-radius: 4px; margin-bottom: 4px; display: flex; gap: 10px; align-items: center;
        }
        .item-row:hover { background: #333; }
        .item-row.active { background: var(--accent); color: white; }
        .item-row.processed { opacity: 0.6; }
        .item-row.processed::after { content: '✅'; margin-left: auto; }

        .item-chk { display: none; transform: scale(1.2); cursor: pointer; }
        .delete-mode .item-chk { display: block; }
        
        .editor-box { display: flex; gap: 20px; height: 100%; }
        .col { flex: 1; display: flex; flex-direction: column; }
        textarea { flex: 1; background: #000; color: #0f0; border: 1px solid #444; padding: 10px; font-family: monospace; resize: none; font-size: 0.9rem; }
        
        .toolbar { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #333; }
        .toolbar-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .toolbar-controls { display: flex; gap: 15px; align-items: flex-end; background: #222; padding: 15px; border-radius: 6px; }
        
        .control-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .control-group label { font-size: 0.7rem; color: #888; text-transform: uppercase; font-weight: bold; }
        
        select { background: #111; color: #eee; border: 1px solid #444; padding: 8px; border-radius: 4px; width: 100%; }
        
        /* Spinner */
        .spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-right: 5px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body style="margin:0">

<div class="layout">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3 style="margin:0; color:#fff;">Staging Items</h3>
            <button class="btn btn-sm btn-secondary" id="btnToggleDel" onclick="toggleDeleteMode()">🗑️</button>
        </div>
        
        <div class="sidebar-list" id="sidebarList">
            <?php foreach($stagingItems as $item): ?>
                <div class="item-row <?php echo $item['processed'] ? 'processed' : ''; ?>" 
                     id="row_<?php echo $item['id']; ?>"
                     onclick="loadEntity(<?php echo htmlspecialchars(json_encode($item)); ?>, this, event)">
                    <input type="checkbox" class="item-chk" value="<?php echo $item['id']; ?>">
                    <div style="flex:1">
                        <div style="font-weight:bold"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div style="font-size:0.7rem; opacity:0.7"><?php echo $item['ref_code']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-footer" id="deleteFooter">
            <button class="btn btn-danger" style="width:100%" onclick="deleteSelected()">Delete Selected</button>
        </div>
    </div>

    <!-- MAIN WORKSPACE -->
    <div class="main">
        <div id="workspace" style="display:none; height:100%; flex-direction:column;">
            
            <div class="toolbar">
                <!-- Row 1: Title -->
                <div class="toolbar-title-row">
                    <div>
                        <h1 id="wkTitle" style="margin:0; font-size:1.5rem">Select Item</h1>
                        <span id="wkType" class="badge" style="background:#444; padding:2px 6px; border-radius:4px; font-size:0.8rem">Type</span>
                    </div>
                </div>

                <!-- Row 2: Controls -->
                <div class="toolbar-controls">
                    <!-- 1. Converter -->
                    <div class="control-group">
                        <label>1. Processor (Lore → MD)</label>
                        <select id="genConfig">
                            <?php foreach($genConfigs as $g): ?>
                                <option value="<?php echo $g['config_id']; ?>" <?php echo strpos($g['config_id'], 'md_showcase') !== false ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Target Generator -->
                    <div class="control-group">
                        <label>2. Assign Target Generator (For CLI)</label>
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
                        <button class="btn btn-primary" id="btnGen" onclick="runAI()">✨ Generate MD</button>
                        <button class="btn btn-success" id="btnPublish" style="display:none" onclick="publish()">🚀 Publish</button>
                    </div>
                </div>
            </div>

            <div class="editor-box">
                <div class="col">
                    <h3>Raw Lore Source</h3>
                    <textarea id="rawContent" readonly></textarea>
                </div>
                <div class="col">
                    <h3>Generated Showcase Markdown</h3>
                    <textarea id="mdOutput" placeholder="Select 'Processor' logic above and click Generate..."></textarea>
                </div>
            </div>
        </div>
        
        <div id="emptyState" style="text-align:center; padding-top:100px; color:#555;">
            <h2>Select an entity from the sidebar</h2>
        </div>
    </div>
</div>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<script>
let currentEntity = null;
let deleteMode = false;

function toggleDeleteMode() {
    deleteMode = !deleteMode;
    const list = document.getElementById('sidebarList');
    const footer = document.getElementById('deleteFooter');
    const btn = document.getElementById('btnToggleDel');
    
    if (deleteMode) {
        list.classList.add('delete-mode');
        footer.style.display = 'block';
        btn.classList.add('btn-danger');
    } else {
        list.classList.remove('delete-mode');
        footer.style.display = 'none';
        btn.classList.remove('btn-danger');
    }
}

function loadEntity(item, el, event) {
    if (deleteMode && event.target.type !== 'checkbox') {
        // In delete mode, clicking row toggles checkbox
        const chk = el.querySelector('.item-chk');
        chk.checked = !chk.checked;
        return;
    }
    if (event.target.type === 'checkbox') return;

    currentEntity = item;
    $('#emptyState').hide();
    $('#workspace').css('display', 'flex');
    $('.item-row').removeClass('active');
    $(el).addClass('active');
    
    $('#wkTitle').text(item.title);
    $('#wkType').text(item.type);
    $('#rawContent').val(item.raw_content);
    $('#mdOutput').val('');
    $('#btnPublish').hide();
}

function runAI() {
    if(!currentEntity) return;
    
    const configId = $('#genConfig').val();
    const btn = $('#btnGen');
    
    // Thinking Indicator
    const originalText = btn.text();
    btn.html('<span class="spinner"></span> Working...');
    btn.prop('disabled', true);
    
    $('#mdOutput').val('AI is analyzing lore and structuring showcase...');
    
    $.post('', { 
        action: 'generate_md', 
        id: currentEntity.id,
        config_id: configId 
    }, function(res) {
        btn.html(originalText);
        btn.prop('disabled', false);

        if(res.ok) {
            $('#mdOutput').val(res.md);
            $('#btnPublish').show();
        } else {
            $('#mdOutput').val('Error: ' + res.error);
        }
    }).fail(function() {
        btn.html(originalText);
        btn.prop('disabled', false);
        $('#mdOutput').val('Network Error.');
    });
}

function publish() {
    let md = $('#mdOutput').val();
    if(!md || md.length < 10) return alert('No Markdown content to publish');
    
    const targetId = $('#targetConfig').val(); // Get the CLI target config

    if(!confirm('Publish items to Showcase DB using selected Target Generator?')) return;

    $.post('', { 
        action: 'publish_showcase', 
        md: md, 
        staging_id: currentEntity.id,
        target_config_id: targetId 
    }, function(res) {
        if(res.ok) {
            alert(`Success! Created ${res.stats.inserted} showcase items.`);
            $('.item-row.active').addClass('processed');
        } else {
            alert('Error: ' + res.error);
        }
    });
}

function deleteSelected() {
    const ids = [];
    $('.item-chk:checked').each(function() { ids.push($(this).val()); });
    
    if (ids.length === 0) return alert('No items selected.');
    if (!confirm(`Permanently delete ${ids.length} staging items?`)) return;
    
    $.post('', { action: 'delete_staging', ids: JSON.stringify(ids) }, function(res) {
        if (res.ok) {
            ids.forEach(id => $('#row_' + id).remove());
            toggleDeleteMode(); // Exit delete mode
            if (currentEntity && ids.includes(currentEntity.id)) {
                $('#workspace').hide();
                $('#emptyState').show();
            }
        } else {
            alert('Error: ' + res.error);
        }
    });
}
</script>
</body>
</html>