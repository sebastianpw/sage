<?php
// public/sketchtagforwarder.php
// Sketch Tag Forwarder -- Mass propagates tags from Sketches down to their associated Frames
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

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
                echo json_encode(['status' => 'success', 'inserted' => 0, 'log' => 'Empty batch']);
                exit;
            }

            $inClause = implode(',', array_fill(0, count($sketchIds), '?'));
            
            // We use UNION DISTINCT to safely gather frames linked directly (entity_id) 
            // OR via the associative table (frames_2_sketches)
            $sql = "
                INSERT IGNORE INTO tags_2_frames (from_id, to_id)
                SELECT tag_id, frame_id FROM (
                    SELECT t2s.from_id AS tag_id, f.id AS frame_id
                    FROM tags_2_sketches t2s
                    JOIN frames f ON f.entity_type = 'sketches' AND f.entity_id = t2s.to_id
                    WHERE t2s.to_id IN ($inClause)
                    UNION DISTINCT
                    SELECT t2s.from_id AS tag_id, f2s.from_id AS frame_id
                    FROM tags_2_sketches t2s
                    JOIN frames_2_sketches f2s ON f2s.to_id = t2s.to_id
                    WHERE t2s.to_id IN ($inClause)
                ) AS combined
            ";
            
            $params = array_merge($sketchIds, $sketchIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $insertedCount = $stmt->rowCount();
            
            // Get meta info for the log
            $infoSql = "
                SELECT COUNT(DISTINCT tag_id) as t_count, COUNT(DISTINCT frame_id) as f_count FROM (
                    SELECT t2s.from_id AS tag_id, f.id AS frame_id
                    FROM tags_2_sketches t2s
                    JOIN frames f ON f.entity_type = 'sketches' AND f.entity_id = t2s.to_id
                    WHERE t2s.to_id IN ($inClause)
                    UNION DISTINCT
                    SELECT t2s.from_id AS tag_id, f2s.from_id AS frame_id
                    FROM tags_2_sketches t2s
                    JOIN frames_2_sketches f2s ON f2s.to_id = t2s.to_id
                    WHERE t2s.to_id IN ($inClause)
                ) AS combined
            ";
            $infoStmt = $pdo->prepare($infoSql);
            $infoStmt->execute($params);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

            $minId = min($sketchIds);
            $maxId = max($sketchIds);
            
            $logMsg = "Sketches #$minId to #$maxId: Forwarded {$info['t_count']} unique tags down to {$info['f_count']} frames. (New DB Rows: $insertedCount)";

            echo json_encode(['status' => 'success', 'inserted' => $insertedCount, 'log' => $logMsg]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'Sketch Tag Forwarder';
ob_start();
?>
<!-- Dependencies -->
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
        --cyan: #06b6d4;
        --green: #10b981;
        --amber: #f59e0b;
        --purple: #8b5cf6;
        --red: #ef4444;
        --console-bg: #0d0d0d;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

    .eh-header { 
        flex-shrink: 0; padding: 10px 16px; 
        background: var(--card); border-bottom: 1px solid var(--border); 
        display: flex; align-items: center; justify-content: space-between; 
    }
    .eh-title { 
        font-size: 1rem; font-weight: 700; letter-spacing: 1px; 
        color: var(--purple); display: flex; align-items: center; gap: 8px; 
    }

    /* ── CONTROLS PANEL ── */
    .eh-controls {
        flex-shrink: 0; padding: 20px; background: rgba(0,0,0,0.2); 
        border-bottom: 1px solid var(--border);
        display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap;
    }

    .input-group { display: flex; flex-direction: column; gap: 6px; }
    .input-group label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; }
    
    .run-input {
        padding: 10px 14px; border-radius: 4px; font-size: 1rem;
        border: 1px solid var(--border); background: var(--bg);
        color: var(--text); font-family: inherit; width: 180px;
    }
    .run-input:focus { outline: none; border-color: var(--purple); }

    .action-btn { 
        padding: 11px 24px; border-radius: 4px; font-size: 0.9rem; font-weight: 700; 
        border: 1px solid var(--border); background: var(--card); color: var(--text); 
        cursor: pointer; text-transform: uppercase; font-family: inherit; transition: all 0.2s; 
        letter-spacing: 1px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    }
    .action-btn.primary { background: var(--purple); color: #000; border-color: var(--purple); }
    .action-btn.primary:hover:not(:disabled) { filter: brightness(1.1); }
    .action-btn:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

    /* ── CONSOLE ── */
    .eh-console-area { 
        flex: 1; display: flex; flex-direction: column; background: var(--console-bg); min-height: 0; 
    }
    .console-header { 
        padding: 8px 16px; background: #111; border-bottom: 1px solid #333; 
        font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px; 
        display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
    }
    .console-scroll { 
        flex: 1; overflow-y: auto; padding: 16px; 
        font-family: 'Consolas', 'Monaco', monospace; font-size: 0.85rem; 
        color: #ccc; line-height: 1.5;
    }
    
    .log-line { margin-bottom: 6px; border-bottom: 1px dashed rgba(255,255,255,0.05); padding-bottom: 4px;}
    .log-time { color: #555; margin-right: 12px; }
    .log-info { color: var(--cyan); }
    .log-success { color: var(--green); }
    .log-error { color: var(--red); }
    .log-warn { color: var(--amber); }

    /* ── STATS BAR ── */
    .stats-bar {
        flex-shrink: 0; padding: 12px 20px; background: var(--card); 
        border-top: 1px solid var(--border); display: flex; gap: 30px;
    }
    .stat-item { display: flex; flex-direction: column; gap: 4px; }
    .stat-val { font-size: 1.4rem; font-weight: 700; color: var(--purple); }
    .stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
</style>

<div class="eh-layout">
    <div class="eh-header">
        <div class="eh-title"><span>⏩</span> SKETCH TAG FORWARDER</div>
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
        <button class="action-btn primary" id="btnRun" onclick="startForwarding()">▶ Run Forwarding</button>
    </div>

    <div class="eh-console-area">
        <div class="console-header">
            <span>Operations Log</span>
            <span id="statusIndicator">IDLE</span>
        </div>
        <div class="console-scroll" id="consoleOut">
            <div class="log-line"><span class="log-time">[SYSTEM]</span> Ready. Enter a sketch ID range to begin pushing tags down to frames.</div>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-val" id="statSketches">0</div>
            <div class="stat-label">Sketches Processed</div>
        </div>
        <div class="stat-item">
            <div class="stat-val" id="statInserted" style="color: var(--green);">0</div>
            <div class="stat-label">New Tag Assignments Written</div>
        </div>
    </div>
</div>

<script>
let totalSketches = 0;
let totalInserted = 0;

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
    document.getElementById('statInserted').innerText = totalInserted;
}

async function startForwarding() {
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
    totalInserted = 0;
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

        log(`Found ${sketchIds.length} sketches. Commencing batch forwarding...`, 'info');

        // Chunking array into batches of 50
        const chunkSize = 50;
        for (let i = 0; i < sketchIds.length; i += chunkSize) {
            const chunk = sketchIds.slice(i, i + chunkSize);
            
            const batchFd = new FormData();
            batchFd.append('sketch_ids', JSON.stringify(chunk));

            const bReq = await fetch('?api_action=process_batch', {method: 'POST', body: batchFd});
            const bRes = await bReq.json();

            if (bRes.status === 'success') {
                log(bRes.log, bRes.inserted > 0 ? 'success' : 'info');
                totalSketches += chunk.length;
                totalInserted += bRes.inserted;
                updateStats();
            } else {
                log(`Batch error: ${bRes.message}`, 'error');
            }
        }

        log('Finished all batches successfully.', 'success');
        Toast.show('Forwarding Complete!', 'success');

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
    btn.innerHTML = '▶ Run Forwarding';
    status.innerText = 'IDLE';
    status.style.color = '#888';
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>