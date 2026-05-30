<?php
// public/videosmatcher.php
// Videos Matcher -- Semantic Reconstruction for Imported Videos
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Core\PyApiVectorService;
use App\Core\PyApiCVService;

// Configuration
const DISTANCE_THRESHOLD = 0.126; // Max allowed distance

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    // Clear any previous output to prevent JSON corruption
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    $action = $_REQUEST['api_action'];

    try {
        // 1. GET CANDIDATES
        if ($action === 'get_candidates') {
            $limit = 100; 
            $sql = "SELECT a.id as anim_id, a.name, a.created_at, a.description,
                           v.thumbnail, v.url, v.id as video_id
                    FROM animatics a
                    JOIN videos_2_animatics va ON a.id = va.to_id
                    JOIN videos v ON va.from_id = v.id
                    WHERE (a.name = 'Batch Import' OR a.description = 'Batch Import') 
                      AND a.img2img_frame_id IS NULL
                    ORDER BY a.id DESC 
                    LIMIT $limit";
            
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$rows, 'count'=>count($rows)]);
            exit;
        }

        // Helper: Find Match
        function findBestMatch($thumbRelPath) {
            global $pdo;
            $docRoot = $_SERVER['DOCUMENT_ROOT'];
            $absPath = $docRoot . $thumbRelPath;
            
            if (!file_exists($absPath)) {
                $absPath = $docRoot . '/' . ltrim($thumbRelPath, '/');
                if (!file_exists($absPath)) throw new Exception("Thumbnail not found: $thumbRelPath");
            }

            $vectorService = new PyApiVectorService();
            $res = $vectorService->query(null, $absPath, 'sage_nu_images', 'image', 1);

            if (empty($res['result']['ids'][0])) {
                throw new Exception("No visual match found in Chroma.");
            }

            $matchIdRaw = $res['result']['ids'][0][0];
            $distance   = $res['result']['distances'][0][0]; 
            $frameId    = (int)str_replace('frame_', '', $matchIdRaw);

            $sqlInfo = "SELECT f.id, f.filename, 
                               s.name as sketch_name, s.description as sketch_desc,
                               f.prompt as frame_prompt
                        FROM frames f
                        LEFT JOIN frames_2_sketches f2s ON f.id = f2s.from_id
                        LEFT JOIN sketches s ON f2s.to_id = s.id
                        WHERE f.id = $frameId
                        LIMIT 1";
            
            $meta = $pdo->query($sqlInfo)->fetch(PDO::FETCH_ASSOC);
            if (!$meta) throw new Exception("Frame #$frameId in Chroma but not in MySQL.");

            return [
                'frame_id' => $frameId,
                'distance' => $distance,
                'filename' => $meta['filename'],
                'new_name' => $meta['sketch_name'] ?? ('Frame #' . $frameId),
                'new_desc' => $meta['sketch_desc'] ?? $meta['frame_prompt']
            ];
        }

        // 2. PREVIEW MATCH (For Modal)
        if ($action === 'preview_match') {
            $thumb = $_POST['thumbnail'];
            $match = findBestMatch($thumb);
            echo json_encode(['status'=>'success', 'data' => $match]);
            exit;
        }

        // 3. PROCESS MATCH (Execute)
        if ($action === 'match_item') {
            $animId = (int)$_POST['anim_id'];
            $thumb  = $_POST['thumbnail'];
            $force  = filter_var($_POST['force'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $match = findBestMatch($thumb);
            
            if (!$force && $match['distance'] > DISTANCE_THRESHOLD) {
                throw new Exception("Match too weak (Dist: " . round($match['distance'], 4) . " > " . DISTANCE_THRESHOLD . ")");
            }

            $updateSql = "UPDATE animatics 
                          SET name = ?, description = ?, 
                              img2img_frame_id = ?, img2img = 1,
                              updated_at = NOW()
                          WHERE id = ?";
            
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([$match['new_name'], $match['new_desc'], $match['frame_id'], $animId]);

            echo json_encode([
                'status'   => 'success',
                'frame_id' => $match['frame_id'],
                'distance' => round($match['distance'], 4),
                'new_name' => $match['new_name']
            ]);
            exit;
        }

        // 4. CREATE NEW SKETCH (AI Vision)
        if ($action === 'create_sketch') {
            // Increase timeout for AI processing
            set_time_limit(300);

            $animId = (int)$_POST['anim_id'];
            $thumb  = $_POST['thumbnail'];
            
            // A. Analyze Image
            $docRoot = $_SERVER['DOCUMENT_ROOT'];
            $absPath = $docRoot . $thumb;
            if (!file_exists($absPath)) {
                $absPath = $docRoot . '/' . ltrim($thumb, '/');
                if (!file_exists($absPath)) throw new Exception("Thumbnail not found: $thumb");
            }

            $cv = new PyApiCVService();
            $prompt = "Describe this image in a comma-separated format suitable for Stable Diffusion prompting. Focus on visual elements, style, lighting, and composition.";
            $rawResult = $cv->analyze($absPath, $prompt, "claude-large");

            // --- FIX: Handle Array Return from CV Service ---
            $description = '';
            if (is_array($rawResult)) {
                // Try common keys returned by API wrappers
                if (isset($rawResult['description'])) {
                    $description = $rawResult['description'];
                } elseif (isset($rawResult['text'])) {
                    $description = $rawResult['text'];
                } elseif (isset($rawResult['content'])) {
                    $description = $rawResult['content'];
                } elseif (isset($rawResult['caption'])) {
                    $description = $rawResult['caption'];
                } else {
                    // If parsing fails, dump json to DB so we don't get "Array" and can debug
                    $description = json_encode($rawResult, JSON_UNESCAPED_SLASHES);
                }
            } else {
                $description = (string)$rawResult;
            }
            $description = trim($description);

            if (empty($description)) throw new Exception("AI Analysis returned empty result.");

            // B. Create Entities
            $pdo->beginTransaction();
            
            // 1. Create Sketch
            $sketchName = "Batch_" . strtoupper(substr(md5(microtime()), 0, 8));
            $stmtS = $pdo->prepare("INSERT INTO sketches (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmtS->execute([$sketchName, $description]);
            $sketchId = $pdo->lastInsertId();

            // 2. Create Frame (Using thumbnail as image)
            $stmtF = $pdo->prepare("INSERT INTO frames (filename, name, prompt, entity_type, entity_id, created_at) VALUES (?, ?, ?, 'sketches', ?, NOW())");
            $stmtF->execute([$thumb, $sketchName, $description, $sketchId]);
            $frameId = $pdo->lastInsertId();

            // 3. Link Frame -> Sketch
            $stmtLink = $pdo->prepare("INSERT INTO frames_2_sketches (from_id, to_id) VALUES (?, ?)");
            $stmtLink->execute([$frameId, $sketchId]);

            // 4. Update Animatics
            $stmtA = $pdo->prepare("UPDATE animatics SET name = ?, description = ?, img2img_frame_id = ?, img2img = 1, updated_at = NOW() WHERE id = ?");
            $stmtA->execute([$sketchName, $description, $frameId, $animId]);

            $pdo->commit();

            echo json_encode([
                'status' => 'success',
                'new_name' => $sketchName,
                'frame_id' => $frameId,
                'desc_preview' => substr($description, 0, 50) . '...'
            ]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); // Signal error to fetch catch block
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'Videos Matcher';
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
        --red: #ef4444;
        --console-bg: #0d0d0d;
        --purple: #8b5cf6;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

    /* ── HEADER ── */
    .eh-header {
        flex-shrink: 0; padding: 10px 16px;
        background: var(--card); border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--cyan); display: flex; align-items: center; gap: 8px; }

    /* ── CONSOLE ── */
    .eh-top-panel {
        flex-shrink: 0; display: flex; flex-direction: column;
        border-bottom: 1px solid var(--border);
        background: var(--console-bg);
        height: 30vh; position: relative;
    }
    .console-header {
        padding: 5px 10px; background: #1a1a1a; border-bottom: 1px solid #333;
        font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 1px;
        display: flex; justify-content: space-between;
    }
    .console-scroll {
        flex: 1; overflow-y: auto; padding: 10px;
        font-family: 'Consolas', 'Monaco', monospace; font-size: 0.8rem;
        color: #ccc; line-height: 1.4;
    }
    .log-line { margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 2px;}
    .log-time { color: #555; margin-right: 8px; }
    .log-info { color: var(--cyan); }
    .log-success { color: var(--green); }
    .log-error { color: var(--red); }
    .log-warn { color: var(--amber); }

    /* ── TOOLBAR ── */
    .eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
    .grid-toolbar {
        padding: 10px 16px; background: rgba(0,0,0,0.2); 
        display: flex; align-items: center; justify-content: space-between;
    }
    .gt-info { font-size: 0.8rem; color: var(--text-muted); }
    
    .action-btn {
        padding: 6px 16px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;
        border: 1px solid var(--border); background: var(--card); color: var(--text);
        cursor: pointer; text-transform: uppercase; font-family: inherit; transition: all 0.2s;
        min-width: 140px;
    }
    .action-btn:hover { border-color: var(--cyan); color: var(--cyan); }
    .action-btn.primary { background: var(--cyan); color: #000; border-color: var(--cyan); }
    .action-btn.primary:hover { filter: brightness(1.1); }
    .action-btn.running { background: var(--amber); color: #000; border-color: var(--amber); }
    .action-btn:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

    /* ── GRID ── */
    .eh-grid-area {
        flex: 1; overflow-y: auto; padding: 15px; position: relative;
        background: #000; min-height: 0;
    }
    .frames-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;
        padding-bottom: 20px;
    }
    .v-card {
        background: #111; border: 1px solid var(--border); border-radius: 6px;
        overflow: hidden; transition: transform 0.2s; position: relative;
        display: flex; flex-direction: column; cursor: pointer;
    }
    .v-card:hover { border-color: #555; transform: translateY(-2px); }
    
    .v-thumb { width: 100%; aspect-ratio: 16/9; object-fit: cover; background: #000; border-bottom: 1px solid var(--border); }
    .v-meta { padding: 8px; font-size: 0.7rem; color: var(--text-muted); flex: 1; }
    .v-id { color: var(--cyan); font-weight: bold; margin-bottom: 4px; }
    .v-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.8rem; color: #fff; }

    .v-card.matched { border-color: var(--green); opacity: 0.5; }
    .v-card.matched::after {
        content: "MATCHED"; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-10deg);
        background: var(--green); color: #000; font-weight: 900; font-size: 0.8rem; padding: 4px 10px;
        border-radius: 4px; pointer-events: none;
    }
    
    .v-card.error { border-color: var(--red); box-shadow: 0 0 5px rgba(239, 68, 68, 0.4); }
    .v-card.processing { border-color: var(--cyan); box-shadow: 0 0 10px rgba(6,182,212,0.2); }

    /* ── MODAL (Mobile Optimized) ── */
    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 2000;
        align-items: center; justify-content: center; backdrop-filter: blur(5px);
    }
    .modal-content {
        background: var(--card); padding: 15px; border-radius: 8px; border: 1px solid var(--border);
        width: 95%; max-width: 600px;
        max-height: 95vh; overflow-y: auto; 
        display: flex; flex-direction: column; gap: 15px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    .modal-header { font-size: 1.1rem; font-weight: 700; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: 10px; }
    
    .compare-row { 
        display: flex; gap: 10px; align-items: start; justify-content: center; flex-wrap: wrap; 
    }
    .compare-col { 
        flex: 1; min-width: 130px; 
        display: flex; flex-direction: column; gap: 5px; align-items: center; text-align: center;
    }
    .compare-img { 
        width: 100%; max-height: 200px; 
        object-fit: contain; background: #000; 
        border-radius: 4px; border: 1px solid var(--border); 
    }
    .compare-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; }
    
    .match-info { 
        background: rgba(0,0,0,0.3); padding: 10px; border-radius: 6px; 
        font-family: monospace; font-size: 0.8rem; border: 1px solid var(--border);
        word-break: break-word;
    }
    .dist-bad { color: var(--red); }
    .dist-ok { color: var(--green); }

    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border); padding-top: 15px; flex-wrap: wrap; }
    .btn-cancel { background: transparent; border: 1px solid var(--border); color: var(--text); flex: 1; min-width: 80px; }
    .btn-create { background: var(--purple); color: #fff; border: none; flex: 2; min-width: 140px; }
    .btn-confirm { background: var(--green); color: #000; border: none; flex: 2; min-width: 140px; }
    
    .spinner {
        width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #fff; border-radius: 50%; animation: spin 1s linear infinite; display:inline-block; margin-right:5px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="eh-layout">
    <div class="eh-header">
        <div class="eh-title"><span>&#128065;</span> VIDEOS MATCHER</div>
    </div>

    <div class="eh-top-panel">
        <div class="console-header">
            <span>Operations Log (Limit: <?= DISTANCE_THRESHOLD ?>)</span>
            <span id="statusIndicator">IDLE</span>
        </div>
        <div class="console-scroll" id="consoleOut">
            <div class="log-line"><span class="log-time">[SYSTEM]</span> Ready. Load candidates to begin.</div>
        </div>
    </div>

    <div class="eh-mid-panel">
        <div class="grid-toolbar">
            <div class="gt-info" id="gridInfo">0 candidates loaded</div>
            <div style="display:flex; gap:10px;">
                <button class="action-btn" onclick="loadCandidates()">&#8635; Reload</button>
                <button class="action-btn primary" id="btnRun" onclick="toggleBatch()" disabled>&#9654; Auto Match All</button>
            </div>
        </div>
    </div>

    <div class="eh-grid-area">
        <div class="frames-grid" id="gridContainer"></div>
        <div class="state-msg" id="emptyState" style="display:none;">Click "Reload" to fetch.</div>
    </div>
</div>

<!-- RESOLVE MODAL -->
<div class="modal-overlay" id="resolveModal">
    <div class="modal-content">
        <div class="modal-header">Manual Resolve</div>
        
        <div id="modalBody" style="display:flex; flex-direction:column; gap:15px;">
            <!-- Content Injected via JS -->
            <div style="text-align:center;">Finding best match...</div>
        </div>

        <div class="modal-actions">
            <button class="action-btn btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="action-btn btn-create" id="btnCreateSketch" onclick="createSketch()">&#10024; Create New Sketch</button>
            <button class="action-btn btn-confirm" id="btnConfirmMatch" style="display:none;">Match Anyway</button>
        </div>
    </div>
</div>

<script>
const DIST_THRESHOLD = <?= DISTANCE_THRESHOLD ?>;
let candidates = [];
let isRunning = false;
let isPauseRequested = false;

let currentResolveAnimId = null;
let currentResolveThumb = null;

document.addEventListener('DOMContentLoaded', () => {
    loadCandidates();
});

function log(msg, type='info') {
    const consoleEl = document.getElementById('consoleOut');
    const time = new Date().toLocaleTimeString('en-GB', {hour12:false});
    const cls = {success:'log-success', error:'log-error', warn:'log-warn', dim:'log-dim'}[type] || 'log-info';
    
    const div = document.createElement('div');
    div.className = 'log-line';
    div.innerHTML = `<span class="log-time">[${time}]</span> <span class="${cls}">${msg}</span>`;
    consoleEl.appendChild(div);
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

function loadCandidates() {
    if(isRunning) return;
    const grid = document.getElementById('gridContainer');
    grid.innerHTML = '';
    document.getElementById('emptyState').innerText = 'Loading...';
    document.getElementById('emptyState').style.display = 'flex';
    document.getElementById('btnRun').disabled = true;

    fetch('?api_action=get_candidates').then(r=>r.json()).then(res => {
        if(res.status !== 'success') {
            document.getElementById('emptyState').innerText = 'Error.';
            log('Fetch error: '+res.message, 'error');
            return;
        }
        candidates = res.data;
        document.getElementById('gridInfo').innerText = `${candidates.length} candidates`;
        document.getElementById('emptyState').style.display = candidates.length ? 'none' : 'flex';
        document.getElementById('emptyState').innerText = 'No candidates.';
        document.getElementById('btnRun').disabled = candidates.length === 0;
        
        candidates.forEach(item => {
            const card = document.createElement('div');
            card.className = 'v-card';
            card.id = `card-${item.anim_id}`;
            card.onclick = () => openResolve(item.anim_id, item.thumbnail);
            card.innerHTML = `
                <img src="${item.thumbnail}" class="v-thumb" loading="lazy">
                <div class="v-meta">
                    <div class="v-id">#${item.anim_id}</div>
                    <div class="v-name" id="name-${item.anim_id}">${item.name}</div>
                </div>`;
            grid.appendChild(card);
        });
        log(`Loaded ${candidates.length} items.`, 'info');
    });
}

// ── MANUAL RESOLVE ──
function openResolve(animId, thumbnail) {
    if(isRunning) return; 
    const card = document.getElementById(`card-${animId}`);
    if(card.classList.contains('matched')) return;

    currentResolveAnimId = animId;
    currentResolveThumb = thumbnail;
    
    const modal = document.getElementById('resolveModal');
    const body = document.getElementById('modalBody');
    const btnConfirm = document.getElementById('btnConfirmMatch');
    const btnCreate = document.getElementById('btnCreateSketch');
    
    modal.style.display = 'flex';
    btnConfirm.style.display = 'none';
    btnCreate.style.display = 'none'; // Hide initially while searching
    
    body.innerHTML = '<div style="text-align:center; padding:30px;"><div class="spinner"></div> Finding best match...</div>';

    const fd = new FormData();
    fd.append('thumbnail', thumbnail);
    
    fetch('?api_action=preview_match', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res => {
        // Even if search fails, allow creation
        btnCreate.style.display = 'block';

        if(res.status !== 'success') {
            body.innerHTML = `<div style="color:var(--red); text-align:center;">No good match found in DB.<br>Create a new sketch below?</div>
            <img src="${thumbnail}" style="max-height:150px; object-fit:contain; margin:0 auto; display:block;">`;
            return;
        }
        
        const m = res.data;
        const distClass = m.distance > DIST_THRESHOLD ? 'dist-bad' : 'dist-ok';
        const distWarn = m.distance > DIST_THRESHOLD ? '⚠️ WEAK' : 'OK';

        body.innerHTML = `
            <div class="compare-row">
                <div class="compare-col">
                    <div class="compare-label">Source</div>
                    <img src="${thumbnail}" class="compare-img">
                </div>
                <div class="compare-col">
                    <div class="compare-label">Frame #${m.frame_id}</div>
                    <img src="${m.filename}" class="compare-img">
                </div>
            </div>
            <div class="match-info">
                <div><strong>Match:</strong> ${m.new_name}</div>
                <div><strong>Dist:</strong> <span class="${distClass}">${m.distance.toFixed(4)}</span> (${distWarn})</div>
            </div>
        `;
        
        btnConfirm.style.display = 'block';
        btnConfirm.onclick = () => forceMatch();
    })
    .catch(() => {
        btnCreate.style.display = 'block';
        body.innerHTML = '<div style="text-align:center;">Search failed. Network error?</div>';
    });
}

function forceMatch() {
    // Capture ID locally before closing modal
    const animId = currentResolveAnimId;
    const thumb = currentResolveThumb;
    
    closeModal();
    log(`[#${animId}] Forcing match...`, 'warn');
    
    const fd = new FormData();
    fd.append('anim_id', animId);
    fd.append('thumbnail', thumb);
    fd.append('force', 'true');

    fetch('?api_action=match_item', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res => {
        if(res.status === 'success') {
            log(`[#${animId}] FORCED -> Frame #${res.frame_id}`, 'success');
            const card = document.getElementById(`card-${animId}`);
            if(card) {
                card.classList.remove('error');
                card.classList.add('matched');
                document.getElementById(`name-${animId}`).innerText = res.new_name;
            }
        } else {
            log(`Error: ${res.message}`, 'error');
        }
    });
}

function createSketch() {
    const animId = currentResolveAnimId;
    const thumb = currentResolveThumb;
    
    // UI Feedback inside modal
    const btnCreate = document.getElementById('btnCreateSketch');
    const btnConfirm = document.getElementById('btnConfirmMatch');
    const body = document.getElementById('modalBody');
    
    btnCreate.disabled = true;
    btnCreate.innerHTML = '<div class="spinner"></div> Analyzing...';
    btnConfirm.style.display = 'none';
    body.innerHTML = '<div style="text-align:center; padding:30px;"><div class="spinner"></div> AI Vision Analysis running...<br>This may take 10-20 seconds.</div>';

    const fd = new FormData();
    fd.append('anim_id', animId);
    fd.append('thumbnail', thumb);
    
    fetch('?api_action=create_sketch', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res => {
        closeModal();
        if(res.status === 'success') {
            log(`[#${animId}] CREATED Sketch "${res.new_name}"`, 'success');
            log(`Description: ${res.desc_preview}`, 'dim');
            
            const card = document.getElementById(`card-${animId}`);
            if(card) {
                card.classList.remove('error');
                card.classList.add('matched');
                document.getElementById(`name-${animId}`).innerText = res.new_name;
            }
        } else {
            log(`[#${animId}] Create Failed: ${res.message}`, 'error');
            Toast.show('Creation failed', 'error');
        }
    })
    .catch(e => {
        closeModal();
        log(`[#${animId}] Network/Server Error`, 'error');
    });
}

function closeModal() {
    document.getElementById('resolveModal').style.display = 'none';
    // Reset buttons
    const btnCreate = document.getElementById('btnCreateSketch');
    btnCreate.disabled = false;
    btnCreate.innerHTML = '&#10024; Create New Sketch';
    
    currentResolveAnimId = null;
    currentResolveThumb = null;
}

// ── BATCH ──
function toggleBatch() {
    if(isRunning) {
        isPauseRequested = true;
        document.getElementById('btnRun').innerHTML = 'Pausing...';
    } else {
        runBatch();
    }
}

async function runBatch() {
    if(candidates.length === 0) return;
    isRunning = true;
    isPauseRequested = false;
    
    const btn = document.getElementById('btnRun');
    btn.innerHTML = '&#10074;&#10074; PAUSE';
    btn.classList.add('running');
    btn.classList.remove('primary');
    document.getElementById('statusIndicator').innerText = "RUNNING";
    document.getElementById('statusIndicator').style.color = "var(--green)";

    let processed = 0;

    for (const item of candidates) {
        if(isPauseRequested) break;
        
        const card = document.getElementById(`card-${item.anim_id}`);
        if(card.classList.contains('matched') || card.classList.contains('error')) continue;

        card.classList.add('processing');
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });

        try {
            const fd = new FormData();
            fd.append('anim_id', item.anim_id);
            fd.append('thumbnail', item.thumbnail);
            
            const r = await fetch('?api_action=match_item', {method:'POST', body:fd});
            const res = await r.json();

            card.classList.remove('processing');

            if(res.status === 'success') {
                log(`[#${item.anim_id}] Match -> #${res.frame_id} (D:${res.distance})`, 'success');
                card.classList.add('matched');
                document.getElementById(`name-${item.anim_id}`).innerText = res.new_name;
                processed++;
            } else {
                log(`[#${item.anim_id}] Skipped: ${res.message}`, 'error');
                card.classList.add('error');
            }
        } catch(e) {
            card.classList.remove('processing');
            card.classList.add('error');
        }
        await new Promise(r => setTimeout(r, 200));
    }

    isRunning = false;
    btn.innerHTML = '&#9654; Auto Match All';
    btn.classList.remove('running');
    btn.classList.add('primary');
    
    const statusEl = document.getElementById('statusIndicator');
    statusEl.innerText = isPauseRequested ? "PAUSED" : "IDLE";
    statusEl.style.color = isPauseRequested ? "var(--amber)" : "#888";
    
    if(!isPauseRequested) Toast.show(`Batch finished: ${processed} matched`);
}
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
