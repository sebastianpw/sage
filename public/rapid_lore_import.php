<?php
// public/rapid_lore_import.php - Step 1: Ingest Lore to Staging
require_once __DIR__ . '/bootstrap.php';
$em = $spw->getEntityManager();
$conn = $em->getConnection();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) die('Not authenticated');

// --- HELPER: REGEX PARSER ---
function parseLoreMarkdown($content) {
    $entities = [];
    $parts = preg_split('/^(###\s+.+)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $currentHeader = null;

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;

        if (strpos($part, '###') === 0) {
            $currentHeader = $part;
            continue;
        }

        if ($currentHeader) {
            // Title & Ref Logic
            $rawTitle = preg_replace('/^###\s*\d+\.\s*/', '', $currentHeader);
            $cleanTitle = preg_replace('/\s*\(.*?\)/', '', $rawTitle); 
            $refBase = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($cleanTitle)));

            // Type/Category Detection
            $type = 'General';
            if (preg_match('/\*\*Location\*\*:/i', $part)) $type = 'Location';
            elseif (preg_match('/\*\*Function\*\*:/i', $part)) $type = 'Function';
            elseif (preg_match('/\*\*Character\*\*:/i', $part)) $type = 'Character';

            // Clean Raw Content (preserve structure for AI, just trim edges)
            $entities[] = [
                'ref' => $refBase,
                'title' => trim($rawTitle),
                'type' => $type,
                'raw' => $part,
                'is_sub' => false
            ];

            // Sub-item Detection
            if (preg_match_all('/\*\*(\d+)\.\s+(.*?)\*\*.*?:(.*?)(?=\n\*\*\d+\.|\n###|$)/s', $part, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $subTitle = trim($m[2]);
                    $subRef = $refBase . '_' . strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', $subTitle));
                    $entities[] = [
                        'ref' => $subRef,
                        'title' => $subTitle,
                        'type' => 'Sub-Feature',
                        'raw' => $m[0], // Keep the whole sub-block
                        'is_sub' => true
                    ];
                }
            }
        }
    }
    return $entities;
}

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 1. Parse File
    if (isset($_FILES['lore_file'])) {
        try {
            $content = file_get_contents($_FILES['lore_file']['tmp_name']);
            $items = parseLoreMarkdown($content);
            echo json_encode(['ok' => true, 'items' => $items]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 2. Commit to Staging Table
    if (isset($_POST['action']) && $_POST['action'] === 'stage_items') {
        try {
            $payload = json_decode($_POST['payload'], true);
            $count = 0;
            $fileName = $_POST['filename'] ?? 'upload.md';

            foreach ($payload as $p) {
                if(empty($p['selected'])) continue;
                
                // Upsert into lore_entities
                $sql = "INSERT INTO lore_entities (ref_code, title, type, raw_content, source_file, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE title=VALUES(title), raw_content=VALUES(raw_content), type=VALUES(type)";
                
                $conn->executeStatement($sql, [
                    $p['ref'], $p['title'], $p['type'], $p['raw'], $fileName
                ]);
                $count++;
            }
            echo json_encode(['ok' => true, 'count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lore Ingest (Step 1)</title>
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
    <link rel="stylesheet" href="css/base.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .upload-hero {
            border: 2px dashed #555; background: rgba(0,0,0,0.2); padding: 50px;
            text-align: center; border-radius: 10px; cursor: pointer; transition: 0.2s;
        }
        .upload-hero:hover { border-color: var(--accent); background: rgba(0,0,0,0.3); }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 20px; }
        .card { 
            background: #1e1e1e; border: 1px solid #333; padding: 15px; border-radius: 6px; 
            display: flex; flex-direction: column; gap: 5px; font-size: 0.9rem;
        }
        .card.sub { border-left: 3px solid var(--accent); }
        .badge { background: #333; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; display: inline-block; }
        .actions { margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center">
        <h1>📥 Lore Ingest <small>(Step 1)</small></h1>
        <a href="rapid_lore_processor.php" class="btn btn-primary">Go to Step 2: Processor &rarr;</a>
    </div>
    
    <!-- Upload -->
    <div id="stageUpload">
        <input type="file" id="fInput" style="display:none">
        <div class="upload-hero" onclick="$('#fInput').click()">
            <h2>Click to Upload Lore MD</h2>
            <p>Extracts structure & saves to Staging DB</p>
        </div>
    </div>

    <!-- Review -->
    <div id="stageReview" style="display:none">
        <div class="actions">
            <button class="btn btn-secondary" onclick="location.reload()">Cancel</button>
            <button class="btn btn-primary" onclick="commitToStaging()">💾 Save to Staging DB</button>
        </div>
        <div id="grid" class="grid"></div>
    </div>
</div>

<script>
let currentFile = '';

$('#fInput').change(function() {
    if(this.files.length) {
        currentFile = this.files[0].name;
        let fd = new FormData();
        fd.append('lore_file', this.files[0]);
        
        $.ajax({
            url: '', type: 'POST', data: fd, contentType: false, processData: false,
            success: function(res) {
                if(res.ok) render(res.items);
                else alert(res.error);
            }
        });
    }
});

function render(items) {
    $('#stageUpload').hide();
    $('#stageReview').show();
    let html = '';
    items.forEach((i, idx) => {
        html += `
        <div class="card ${i.is_sub?'sub':''}" data-idx="${idx}">
            <div style="display:flex; justify-content:space-between">
                <strong><input type="checkbox" checked class="chk"> ${i.title}</strong>
                <span class="badge">${i.type}</span>
            </div>
            <div style="color:#777; font-size:0.8rem">${i.ref}</div>
            <div style="font-family:monospace; color:#aaa; font-size:0.75rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                ${i.raw.substring(0, 60)}...
            </div>
            <textarea style="display:none" class="raw-data">${i.raw}</textarea>
            <input type="hidden" class="ref-data" value="${i.ref}">
            <input type="hidden" class="title-data" value="${i.title}">
            <input type="hidden" class="type-data" value="${i.type}">
        </div>`;
    });
    $('#grid').html(html);
}

function commitToStaging() {
    let payload = [];
    $('.card').each(function() {
        if($(this).find('.chk').is(':checked')) {
            payload.push({
                selected: true,
                ref: $(this).find('.ref-data').val(),
                title: $(this).find('.title-data').val(),
                type: $(this).find('.type-data').val(),
                raw: $(this).find('.raw-data').val()
            });
        }
    });

    if(!payload.length) return alert('No items selected');

    $.post('', { action: 'stage_items', payload: JSON.stringify(payload), filename: currentFile }, function(res) {
        if(res.ok) {
            alert(`Success! ${res.count} items saved to Staging.`);
            window.location.href = 'rapid_lore_processor.php';
        } else {
            alert('Error: ' + res.error);
        }
    });
}
</script>
</body>
</html>