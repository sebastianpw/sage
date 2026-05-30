<?php
// public/sketch_tag_extractor.php
// Sketch Tag Extractor (Pass 1) -- Parses JSON analysis into hidden tags
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// --- HELPER: Sanitize AI strings into valid Tags ---
function sanitizeTag($rawTag) {
    if (!is_string($rawTag)) return '';
    
    // 1. Remove parenthetical explanations e.g. "Chrono Echoes (silver threads)" -> "Chrono Echoes "
    $tag = preg_replace('/\(.*?\)/', '', $rawTag);
    
    // 2. Strip special characters except spaces, hyphens, and standard alphanumeric
    $tag = preg_replace('/[^a-zA-Z0-9\-\s]/u', '', $tag);
    
    // 3. Compress spaces and trim
    $tag = trim(preg_replace('/\s+/', ' ', $tag));
    
    // 4. Lowercase for normalization
    $tag = mb_strtolower($tag);
    
    // 5. Truncate to 50 chars to fit DB schema (varchar 50)
    return mb_substr($tag, 0, 50);
}

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        if ($action === 'get_sketch_ids') {
            $from = (int)$_POST['from_id'];
            $to = (int)$_POST['to_id'];
            
            $sql = "SELECT id FROM sketches WHERE id >= ? AND id <= ? ORDER BY id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$from, $to]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode(['status' => 'success', 'data' => $ids]);
            exit;
        }

        if ($action === 'process_batch') {
            $sketchIds = json_decode($_POST['sketch_ids'], true);
            if (empty($sketchIds)) {
                echo json_encode(['status' => 'success', 'inserted_tags' => 0, 'inserted_links' => 0, 'log' => 'Empty batch']);
                exit;
            }

            $inClause = implode(',', array_fill(0, count($sketchIds), '?'));
            
            // Fetch analysis rows
            $sql = "
                SELECT s.id as sketch_id, 
                       sa.entities, sa.thematics,
                       ssa.narrative_function, ssa.energy, ssa.position
                FROM sketches s
                LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
                LEFT JOIN sketch_sequence_analysis ssa ON s.id = ssa.sketch_id
                WHERE s.id IN ($inClause)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($sketchIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tagsToCreate = []; // [tag_name => true]
            $sketchToTags = []; // [sketch_id => [tag_name1, tag_name2]]

            foreach ($rows as $row) {
                $sId = $row['sketch_id'];
                $sketchToTags[$sId] = [];

                $rawTags = [];

                // 1. Defensively parse Entities (Catch-all for any AI key)
                $entities = json_decode($row['entities'] ?? '{}', true);
                if (is_array($entities)) {
                    foreach ($entities as $category => $items) {
                        if (is_array($items)) {
                            foreach ($items as $item) {
                                $rawTags[] = $item;
                            }
                        }
                    }
                }

                // 2. Parse Thematics
                $thematics = json_decode($row['thematics'] ?? '{}', true);
                if (is_array($thematics) && !empty($thematics['primary_themes']) && is_array($thematics['primary_themes'])) {
                    foreach ($thematics['primary_themes'] as $theme) {
                        $rawTags[] = $theme;
                    }
                }

                // 3. Parse Narrative Functions
                $navFuncs = json_decode($row['narrative_function'] ?? '[]', true);
                if (is_array($navFuncs)) {
                    foreach ($navFuncs as $nf) {
                        $rawTags[] = $nf;
                    }
                }

                // 4. Sequence structure tags
                if (!empty($row['energy'])) $rawTags[] = "energy " . $row['energy'];
                if (!empty($row['position'])) $rawTags[] = "pos " . $row['position'];

                // Sanitize and deduplicate for this sketch
                foreach ($rawTags as $rt) {
                    $cleanTag = sanitizeTag($rt);
                    if (strlen($cleanTag) > 2) {
                        $tagsToCreate[$cleanTag] = true;
                        $sketchToTags[$sId][$cleanTag] = true;
                    }
                }
            }

            $newTagsCount = 0;
            $newLinksCount = 0;

            if (!empty($tagsToCreate)) {
                // A. Bulk Insert Tags (IGNORE duplicates)
                $tagInsertSql = "INSERT IGNORE INTO tags (name, show_in_ui, created_at, updated_at) VALUES (?, 0, NOW(), NOW())";
                $tagStmt = $pdo->prepare($tagInsertSql);
                
                $pdo->beginTransaction();
                foreach (array_keys($tagsToCreate) as $tagName) {
                    $tagStmt->execute([$tagName]);
                    if ($tagStmt->rowCount() > 0) $newTagsCount++;
                }
                $pdo->commit();

                // B. Fetch Tag IDs for linking
                $tagNames = array_keys($tagsToCreate);
                $tagIn = implode(',', array_fill(0, count($tagNames), '?'));
                $tagMapStmt = $pdo->prepare("SELECT id, name FROM tags WHERE name IN ($tagIn)");
                $tagMapStmt->execute($tagNames);
                $tagMap = [];
                foreach ($tagMapStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                    $tagMap[$t['name']] = $t['id'];
                }

                // C. Bulk Insert Links
                $linkInsertSql = "INSERT IGNORE INTO tags_2_sketches (from_id, to_id) VALUES (?, ?)";
                $linkStmt = $pdo->prepare($linkInsertSql);
                
                $pdo->beginTransaction();
                foreach ($sketchToTags as $sId => $tags) {
                    foreach (array_keys($tags) as $tagName) {
                        if (isset($tagMap[$tagName])) {
                            $linkStmt->execute([$tagMap[$tagName], $sId]);
                            if ($linkStmt->rowCount() > 0) $newLinksCount++;
                        }
                    }
                }
                $pdo->commit();
            }

            $minId = min($sketchIds);
            $maxId = max($sketchIds);
            $logMsg = "Sketches #$minId to #$maxId: Created $newTagsCount new hidden tags. Mapped $newLinksCount tags to sketches.";

            echo json_encode([
                'status' => 'success', 
                'inserted_tags' => $newTagsCount, 
                'inserted_links' => $newLinksCount, 
                'log' => $logMsg
            ]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'Sketch Tag Extractor (Pass 1)';
ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<style>
    :root {
        --bg: #0a0a0f; --card: #111118; --border: #1e1e2e;
        --text: #e2e2f0; --text-muted: #555570;
        --cyan: #06b6d4; --green: #10b981; --amber: #f59e0b;
        --purple: #8b5cf6; --red: #ef4444; --console-bg: #0d0d0d;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    .eh-layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
    .eh-header { flex-shrink: 0; padding: 10px 16px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--cyan); display: flex; align-items: center; gap: 8px; }

    .eh-controls { flex-shrink: 0; padding: 20px; background: rgba(0,0,0,0.2); border-bottom: 1px solid var(--border); display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap; }
    .input-group { display: flex; flex-direction: column; gap: 6px; }
    .input-group label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; }
    .run-input { padding: 10px 14px; border-radius: 4px; font-size: 1rem; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; width: 180px; }
    .run-input:focus { outline: none; border-color: var(--cyan); }

    .action-btn { padding: 11px 24px; border-radius: 4px; font-size: 0.9rem; font-weight: 700; border: 1px solid var(--border); background: var(--card); color: var(--text); cursor: pointer; text-transform: uppercase; font-family: inherit; transition: all 0.2s; letter-spacing: 1px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .action-btn.primary { background: var(--cyan); color: #000; border-color: var(--cyan); }
    .action-btn.primary:hover:not(:disabled) { filter: brightness(1.1); }
    .action-btn:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

    .eh-console-area { flex: 1; display: flex; flex-direction: column; background: var(--console-bg); min-height: 0; }
    .console-header { padding: 8px 16px; background: #111; border-bottom: 1px solid #333; font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .console-scroll { flex: 1; overflow-y: auto; padding: 16px; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.85rem; color: #ccc; line-height: 1.5; }
    
    .log-line { margin-bottom: 6px; border-bottom: 1px dashed rgba(255,255,255,0.05); padding-bottom: 4px;}
    .log-time { color: #555; margin-right: 12px; }
    .log-info { color: var(--cyan); }
    .log-success { color: var(--green); }
    .log-error { color: var(--red); }
    .log-warn { color: var(--amber); }

    .stats-bar { flex-shrink: 0; padding: 12px 20px; background: var(--card); border-top: 1px solid var(--border); display: flex; gap: 30px; }
    .stat-item { display: flex; flex-direction: column; gap: 4px; }
    .stat-val { font-size: 1.4rem; font-weight: 700; color: var(--cyan); }
    .stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
</style>

<div class="eh-layout">
    <div class="eh-header">
        <div class="eh-title"><span>🏷️</span> SKETCH TAG EXTRACTOR (PASS 1)</div>
    </div>

    <div class="eh-controls">
        <div class="input-group">
            <label>From Sketch ID</label>
            <input type="number" id="fromId" class="run-input" placeholder="e.g. 1000">
        </div>
        <div class="input-group">
            <label>To Sketch ID</label>
            <input type="number" id="toId" class="run-input" placeholder="e.g. 2000">
        </div>
        <button class="action-btn primary" id="btnRun" onclick="startExtraction()">▶ Run Extraction</button>
    </div>

    <div class="eh-console-area">
        <div class="console-header">
            <span>Operations Log</span>
            <span id="statusIndicator">IDLE</span>
        </div>
        <div class="console-scroll" id="consoleOut">
            <div class="log-line"><span class="log-time">[SYSTEM]</span> Ready. Enter a sketch ID range to parse JSON AI metadata into hidden tags.</div>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-val" id="statSketches">0</div>
            <div class="stat-label">Sketches Processed</div>
        </div>
        <div class="stat-item">
            <div class="stat-val" id="statTags" style="color: var(--amber);">0</div>
            <div class="stat-label">New Tags Created</div>
        </div>
        <div class="stat-item">
            <div class="stat-val" id="statLinks" style="color: var(--green);">0</div>
            <div class="stat-label">New Links Written</div>
        </div>
    </div>
</div>

<script>
let totalSketches = 0;
let totalTags = 0;
let totalLinks = 0;

function log(msg, type='info') {
    const consoleEl = document.getElementById('consoleOut');
    const time = new Date().toLocaleTimeString('en-GB', {hour12:false});
    const cls = {success:'log-success', error:'log-error', warn:'log-warn'}[type] || 'log-info';
    
    const div = document.createElement('div');
    div.className = 'log-line';
    div.innerHTML = `<span class="log-time">[${time}]</span> <span class="${cls}">${msg}</span>`;
    consoleEl.appendChild(div);
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

function updateStats() {
    document.getElementById('statSketches').innerText = totalSketches;
    document.getElementById('statTags').innerText = totalTags;
    document.getElementById('statLinks').innerText = totalLinks;
}

async function startExtraction() {
    const fromId = parseInt(document.getElementById('fromId').value);
    const toId = parseInt(document.getElementById('toId').value);

    if (isNaN(fromId) || isNaN(toId) || fromId > toId) {
        Toast.show('Please enter a valid ID range.', 'error');
        return;
    }

    const btn = document.getElementById('btnRun');
    const status = document.getElementById('statusIndicator');
    
    btn.disabled = true;
    btn.innerText = 'Running...';
    status.innerText = 'RUNNING';
    status.style.color = 'var(--amber)';
    
    totalSketches = 0;
    totalTags = 0;
    totalLinks = 0;
    updateStats();

    log(`Fetching sketches in range ${fromId} - ${toId}...`);

    try {
        const fd = new FormData();
        fd.append('from_id', fromId);
        fd.append('to_id', toId);

        const r = await fetch('?api_action=get_sketch_ids', {method: 'POST', body: fd});
        const res = await r.json();

        if (res.status !== 'success') throw new Error(res.message);

        const sketchIds = res.data;
        if (sketchIds.length === 0) {
            log('No sketches found in that range.', 'warn');
            resetUI();
            return;
        }

        log(`Found ${sketchIds.length} sketches. Commencing JSON extraction...`, 'info');

        // Chunking array into batches of 50
        const chunkSize = 50;
        for (let i = 0; i < sketchIds.length; i += chunkSize) {
            const chunk = sketchIds.slice(i, i + chunkSize);
            
            const batchFd = new FormData();
            batchFd.append('sketch_ids', JSON.stringify(chunk));

            const bReq = await fetch('?api_action=process_batch', {method: 'POST', body: batchFd});
            const bRes = await bReq.json();

            if (bRes.status === 'success') {
                log(bRes.log, bRes.inserted_links > 0 ? 'success' : 'info');
                totalSketches += chunk.length;
                totalTags += bRes.inserted_tags;
                totalLinks += bRes.inserted_links;
                updateStats();
            } else {
                log(`Batch error: ${bRes.message}`, 'error');
            }
        }

        log('Finished extraction successfully.', 'success');
        Toast.show('Extraction Complete!', 'success');

    } catch (e) {
        log(`System Error: ${e.message}`, 'error');
        Toast.show('An error occurred.', 'error');
    }

    resetUI();
}

function resetUI() {
    const btn = document.getElementById('btnRun');
    const status = document.getElementById('statusIndicator');
    btn.disabled = false;
    btn.innerHTML = '▶ Run Extraction';
    status.innerText = 'IDLE';
    status.style.color = '#888';
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>