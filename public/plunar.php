<?php
// public/plunar.php
// PluNar: PLUSH ⟷ Narrative Sequence Bridge Module

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// =========================================================================================
// API HANDLER (Self-contained)
// =========================================================================================
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? $_GET['action'] ?? null;

if ($action) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($action) {
            
            // ── Search Sequences ──────────────────────────────────────────────────────────
            case 'search_sequences':
                $q = trim($input['q'] ?? '');
                if (is_numeric($q)) {
                    $stmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE id = ? OR name LIKE ? ORDER BY id DESC LIMIT 20");
                    $stmt->execute([(int)$q, "%$q%"]);
                } else {
                    $stmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE name LIKE ? ORDER BY id DESC LIMIT 20");
                    $stmt->execute(["%$q%"]);
                }
                echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            // ── Search PLUSH Stories ──────────────────────────────────────────────────────
            case 'search_plush':
                $q = trim($input['q'] ?? '');
                if (is_numeric($q)) {
                    $stmt = $pdo->prepare("SELECT id, title as name FROM plush_stories WHERE id = ? OR title LIKE ? ORDER BY id DESC LIMIT 20");
                    $stmt->execute([(int)$q, "%$q%"]);
                } else {
                    $stmt = $pdo->prepare("SELECT id, title as name FROM plush_stories WHERE title LIKE ? ORDER BY id DESC LIMIT 20");
                    $stmt->execute(["%$q%"]);
                }
                echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            // ── Direction 1: Narrative Sequence ➔ PLUSH ──────────────────────────────────
            case 'migrate_to_plush':
                $seqId = (int)($input['sequence_id'] ?? 0);
                if (!$seqId) throw new Exception('Sequence ID required.');

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
                $stmt->execute([$seqId]);
                $seq = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$seq) throw new Exception('Sequence not found.');

                // Create Story
                $storyTitle = "Imported: " . $seq['name'];
                $sIns = $pdo->prepare("INSERT INTO plush_stories (title, description) VALUES (?, ?)");
                $sIns->execute([$storyTitle, $seq['description']]);
                $storyId = (int)$pdo->lastInsertId();

                // Create Scene
                $scIns = $pdo->prepare("INSERT INTO plush_scenes (story_id, title, scene_order) VALUES (?, 'Scene 1', 0)");
                $scIns->execute([$storyId]);
                $sceneId = (int)$pdo->lastInsertId();

                // Create Default Group
                $gIns = $pdo->prepare("INSERT INTO plush_highlight_groups (scene_id, label, group_order) VALUES (?, 'Main', 0)");
                $gIns->execute([$sceneId]);
                $groupId = (int)$pdo->lastInsertId();

                $items = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
                $displayOrder = 0;

                $bIns = $pdo->prepare("INSERT INTO plush_highlight_blocks (scene_id, group_id, text_content, display_order) VALUES (?, ?, ?, ?)");
                $eIns = $pdo->prepare("INSERT INTO plush_highlight_block_entities (block_id, entity_type, entity_id, entity_label) VALUES (?, 'sketches', ?, ?)");
                
                $skStmt = $pdo->prepare("SELECT name, description FROM sketches WHERE id = ?");
                $ovStmt = $pdo->prepare("SELECT text_content FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en' ORDER BY display_order ASC");

                foreach ($items as $item) {
                    $sketchId = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
                    if ($sketchId <= 0) continue;

                    $skStmt->execute([$sketchId]);
                    $sk = $skStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$sk) continue;

                    $ovStmt->execute([$sketchId]);
                    $overlays = $ovStmt->fetchAll(PDO::FETCH_COLUMN);

                    // Concatenate all overlay texts into a single string to avoid 1:N fan-out on reverse migration
                    $finalText = empty($overlays) ? ($sk['description'] ?? '') : implode("\n\n", $overlays);

                    $bIns->execute([$sceneId, $groupId, (string)$finalText, $displayOrder++]);
                    $blockId = (int)$pdo->lastInsertId();
                    $eIns->execute([$blockId, $sketchId, $sk['name']]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'new_story_id' => $storyId]);
                break;

            // ── Direction 2: PLUSH ➔ Narrative Sequence (Analysis Phase) ────────────────
            case 'analyze_plush_to_narrative':
                $storyId = (int)($input['story_id'] ?? 0);
                if (!$storyId) throw new Exception('Story ID required.');

                // Fetch chronological sketch mentions
                $stmt = $pdo->prepare("
                    SELECT 
                        b.id as block_id, b.text_content, 
                        e.entity_id as sketch_id, s.name as sketch_name
                    FROM plush_highlight_blocks b
                    JOIN plush_highlight_block_entities e ON e.block_id = b.id AND e.entity_type = 'sketches'
                    JOIN plush_scenes sc ON sc.id = b.scene_id
                    JOIN sketches s ON s.id = e.entity_id
                    WHERE sc.story_id = ? AND b.language_code = 'en'
                    ORDER BY sc.scene_order ASC, sc.id ASC, b.group_id ASC, b.display_order ASC, e.id ASC
                ");
                $stmt->execute([$storyId]);
                $mentions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($mentions)) throw new Exception('No sketches found linked to this PLUSH story.');

                $uniqueSketches = [];
                $newTexts = []; // sketch_id => array of texts

                foreach ($mentions as $m) {
                    $sid = (int)$m['sketch_id'];
                    $uniqueSketches[$sid] = $m['sketch_name'];
                    $newTexts[$sid][] = $m['text_content'];
                }

                // Check for existing overlay texts
                $in = implode(',', array_fill(0, count($uniqueSketches), '?'));
                $ovStmt = $pdo->prepare("SELECT sketch_id, text_content FROM sketch_overlay_texts WHERE language_code = 'en' AND sketch_id IN ($in) ORDER BY display_order ASC");
                $ovStmt->execute(array_keys($uniqueSketches));
                
                $existingTexts = [];
                foreach ($ovStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $existingTexts[(int)$row['sketch_id']][] = $row['text_content'];
                }

                $conflicts = [];
                foreach ($existingTexts as $sid => $texts) {
                    $conflicts[] = [
                        'sketch_id'     => $sid,
                        'sketch_name'   => $uniqueSketches[$sid],
                        'existing_text' => implode("\n---\n", $texts),
                        'new_text'      => implode("\n---\n", $newTexts[$sid])
                    ];
                }

                echo json_encode(['success' => true, 'conflicts' => $conflicts]);
                break;

            // ── Direction 2: PLUSH ➔ Narrative Sequence (Execute Phase) ────────────────
            case 'migrate_to_narrative':
                $storyId = (int)($input['story_id'] ?? 0);
                $overwriteIds = array_map('intval', (array)($input['overwrite_sketch_ids'] ?? []));
                if (!$storyId) throw new Exception('Story ID required.');

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT title FROM plush_stories WHERE id = ?");
                $stmt->execute([$storyId]);
                $storyTitle = $stmt->fetchColumn();

                // Re-fetch chronological sketches
                $mStmt = $pdo->prepare("
                    SELECT b.text_content, e.entity_id as sketch_id
                    FROM plush_highlight_blocks b
                    JOIN plush_highlight_block_entities e ON e.block_id = b.id AND e.entity_type = 'sketches'
                    JOIN plush_scenes sc ON sc.id = b.scene_id
                    WHERE sc.story_id = ? AND b.language_code = 'en'
                    ORDER BY sc.scene_order ASC, sc.id ASC, b.group_id ASC, b.display_order ASC, e.id ASC
                ");
                $mStmt->execute([$storyId]);
                $mentions = $mStmt->fetchAll(PDO::FETCH_ASSOC);

                $sequenceData = [];
                $sketchTexts = [];
                $sketchIds = [];

                foreach ($mentions as $m) {
                    $sid = (int)$m['sketch_id'];
                    $sequenceData[] = ['sketch_id' => $sid, 'frame_id' => null];
                    $sketchTexts[$sid][] = $m['text_content'];
                    $sketchIds[] = $sid;
                }

                // Find latest frames
                $sketchIds = array_unique($sketchIds);
                if (!empty($sketchIds)) {
                    $in = implode(',', array_fill(0, count($sketchIds), '?'));
                    $fStmt = $pdo->prepare("
                        SELECT f.id, f.entity_id as sketch_id FROM frames f WHERE f.entity_type = 'sketches' AND f.entity_id IN ($in)
                        UNION
                        SELECT f.id, fs.to_id as sketch_id FROM frames f JOIN frames_2_sketches fs ON fs.from_id = f.id WHERE fs.to_id IN ($in)
                        ORDER BY id DESC
                    ");
                    $fStmt->execute(array_merge($sketchIds, $sketchIds));
                    $frameMap = [];
                    foreach ($fStmt->fetchAll(PDO::FETCH_ASSOC) as $fr) {
                        $sid = (int)$fr['sketch_id'];
                        if (!isset($frameMap[$sid])) $frameMap[$sid] = (int)$fr['id'];
                    }
                    foreach ($sequenceData as &$item) {
                        $sid = $item['sketch_id'];
                        if (isset($frameMap[$sid])) $item['frame_id'] = $frameMap[$sid];
                    }
                    unset($item);
                }

                // Create Sequence
                $seqName = "Migrated from PLUSH: " . $storyTitle;
                $seqIns = $pdo->prepare("INSERT INTO narrative_sequences (name, sequence_data) VALUES (?, ?)");
                $seqIns->execute([$seqName, json_encode($sequenceData, JSON_UNESCAPED_UNICODE)]);
                $newSeqId = (int)$pdo->lastInsertId();

                // Handle Overlay Texts
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en'");
                $delStmt   = $pdo->prepare("DELETE FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en'");
                $insStmt   = $pdo->prepare("INSERT INTO sketch_overlay_texts (sketch_id, text_content, language_code, display_order) VALUES (?, ?, 'en', ?)");

                foreach ($sketchTexts as $sid => $texts) {
                    $checkStmt->execute([$sid]);
                    $exists = (int)$checkStmt->fetchColumn() > 0;

                    if ($exists && !in_array($sid, $overwriteIds)) {
                        continue; // Keep existing
                    }

                    if ($exists) {
                        $delStmt->execute([$sid]);
                    }

                    foreach ($texts as $order => $txt) {
                        $insStmt->execute([$sid, $txt, $order]);
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'new_sequence_id' => $newSeqId]);
                break;

            default:
                throw new Exception("Unknown action.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$pageTitle = "PluNar Bridge";
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* Forge UI Styles */
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-amber:       #f5a623;
    --pl-teal:        #3ab5c8;
    --pl-purple:      #a78bfa;
    --pl-red:         #f05060;
}
[data-theme="light"] {
    --pl-bg:          #f4f6fa;
    --pl-surface:     #ffffff;
    --pl-card:        #ffffff;
    --pl-border:      #d0d8e8;
    --pl-text:        #1a2233;
    --pl-text-dim:    #7a8aaa;
    --pl-amber:       #c8880a;
    --pl-teal:        #1a8090;
    --pl-purple:      #7c3aed;
    --pl-red:         #d03040;
}

body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', sans-serif; margin: 0; padding: 0; }

.pl-nav { display:flex; align-items:center; gap:10px; padding:10px 16px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); }
[data-theme="light"] .pl-nav { background:rgba(244,246,250,.92); }
.pl-nav-title { font-family:'Space Mono',monospace; font-size:.85rem; color:var(--pl-purple); flex:1; }

.workspace { max-width:1000px; margin:40px auto; padding:0 20px; display:grid; grid-template-columns:1fr 1fr; gap:30px; }
@media(max-width:768px) { .workspace { grid-template-columns:1fr; } }

.bridge-card { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:8px; padding:25px; box-shadow:0 8px 30px rgba(0,0,0,.3); display:flex; flex-direction:column; }
[data-theme="light"] .bridge-card { box-shadow:0 4px 15px rgba(0,0,0,.05); }

.card-title { font-family:'Space Mono',monospace; font-size:1.1rem; text-transform:uppercase; letter-spacing:1px; margin:0 0 10px 0; display:flex; align-items:center; gap:10px; }
.card-title.dir1 { color:var(--pl-teal); }
.card-title.dir2 { color:var(--pl-amber); }
.card-desc { font-size:0.85rem; color:var(--pl-text-dim); line-height:1.5; margin-bottom:20px; }

.su-input { width:100%; box-sizing:border-box; background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:10px 12px; font-family:'Syne',sans-serif; font-size:.9rem; outline:none; transition:border-color .2s; }
.su-input:focus { border-color:var(--pl-purple); }

.ac-wrap { position:relative; margin-bottom:20px; }
.ac-dropdown { position:absolute; top:100%; left:0; right:0; z-index:10; background:var(--pl-card); border:1px solid var(--pl-border); border-top:none; border-radius:0 0 4px 4px; max-height:200px; overflow-y:auto; display:none; box-shadow:0 4px 12px rgba(0,0,0,.5); }
.ac-item { padding:10px 12px; font-size:.85rem; cursor:pointer; transition:background .1s; border-bottom:1px solid var(--pl-border); }
.ac-item:hover { background:rgba(167,139,250,.1); color:var(--pl-purple); }
.ac-item:last-child { border-bottom:none; }

.selected-chip { display:none; align-items:center; justify-content:space-between; padding:12px 16px; background:rgba(0,0,0,.2); border:1px solid var(--pl-border); border-radius:6px; margin-bottom:20px; font-family:'Space Mono',monospace; font-size:.85rem; }
.selected-chip.active { display:flex; }
.selected-chip button { background:none; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; line-height:1; }
.selected-chip button:hover { color:var(--pl-red); }

.pl-btn { padding:12px; border-radius:6px; border:none; font-family:'Space Mono',monospace; font-size:.85rem; font-weight:bold; cursor:pointer; transition:all .15s; text-transform:uppercase; letter-spacing:1px; width:100%; }
.pl-btn:disabled { opacity:0.5; cursor:not-allowed; }
.pl-btn.dir1-btn { background:var(--pl-teal); color:#000; }
.pl-btn.dir1-btn:hover:not(:disabled) { filter:brightness(1.15); }
.pl-btn.dir2-btn { background:var(--pl-amber); color:#000; }
.pl-btn.dir2-btn:hover:not(:disabled) { filter:brightness(1.15); }

/* Modals */
.su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.8); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
.su-modal-backdrop.active { display:flex; }
.su-modal-box { width:100%; max-width:700px; max-height:85vh; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; display:flex; flex-direction:column; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
.su-modal-header { display:flex; justify-content:space-between; align-items:center; padding:15px 20px; border-bottom:1px solid var(--pl-border); background:var(--pl-card); flex-shrink:0; border-radius:8px 8px 0 0; }
.su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-amber); text-transform:uppercase; letter-spacing:1px; }
.su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }

.conflict-list { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:15px; }
.conflict-item { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:15px; }
.conflict-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-family:'Space Mono',monospace; font-size:0.8rem; color:var(--pl-text); border-bottom:1px dashed var(--pl-border); padding-bottom:8px; }
.conflict-text-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; font-size:0.85rem; line-height:1.5; color:var(--pl-text-dim); }
.conflict-text-box { background:rgba(0,0,0,.15); padding:10px; border-radius:4px; border:1px solid var(--pl-border); white-space:pre-wrap; }
.conflict-title { font-weight:bold; margin-bottom:5px; color:var(--pl-text); font-size:0.75rem; text-transform:uppercase; }

.su-check-label { display:flex; align-items:center; gap:8px; font-size:.85rem; cursor:pointer; user-select:none; color:var(--pl-amber); font-weight:bold; }
.su-check-label input[type="checkbox"] { accent-color:var(--pl-amber); width:18px; height:18px; cursor:pointer; }
</style>

<div class="pl-nav">
    <span class="pl-nav-title"><i class="bi bi-arrow-left-right"></i> PluNar Bridge</span>
</div>

<div class="workspace">
    <!-- Direction 1: Seq -> PLUSH -->
    <div class="bridge-card">
        <h3 class="card-title dir1"><i class="bi bi-film"></i> Sequence ➔ PLUSH</h3>
        <div class="card-desc">Convert a Narrative Sequence into a new PLUSH Story, generating highlight blocks from existing overlay texts.</div>
        
        <div class="ac-wrap" id="wrapSeq">
            <input type="text" id="searchSeq" class="su-input" placeholder="Search Narrative Sequences..." oninput="PlunarApp.search('sequences', this.value)">
            <div id="acSeq" class="ac-dropdown"></div>
        </div>

        <div id="selectedSeq" class="selected-chip">
            <span id="lblSeq"></span>
            <button onclick="PlunarApp.clearSelection('seq')">✕</button>
        </div>

        <div style="margin-top:auto;">
            <button id="btnDir1" class="pl-btn dir1-btn" onclick="PlunarApp.migrateToPlush()" disabled>Migrate to PLUSH</button>
        </div>
    </div>

    <!-- Direction 2: PLUSH -> Seq -->
    <div class="bridge-card">
        <h3 class="card-title dir2">PLUSH ➔ Sequence <i class="bi bi-journal-richtext"></i></h3>
        <div class="card-desc">Convert a PLUSH Story into a Narrative Sequence, flattening chronological sketch references and transferring texts.</div>
        
        <div class="ac-wrap" id="wrapPlush">
            <input type="text" id="searchPlush" class="su-input" placeholder="Search PLUSH Stories..." oninput="PlunarApp.search('plush', this.value)">
            <div id="acPlush" class="ac-dropdown"></div>
        </div>

        <div id="selectedPlush" class="selected-chip">
            <span id="lblPlush"></span>
            <button onclick="PlunarApp.clearSelection('plush')">✕</button>
        </div>

        <div style="margin-top:auto;">
            <button id="btnDir2" class="pl-btn dir2-btn" onclick="PlunarApp.analyzePlush()" disabled>Migrate to Sequence</button>
        </div>
    </div>
</div>

<!-- Conflict Modal -->
<div id="conflictModal" class="su-modal-backdrop" onmousedown="if(event.target===this) document.getElementById('conflictModal').classList.remove('active')">
    <div class="su-modal-box">
        <div class="su-modal-header">
            <div class="su-modal-title"><i class="bi bi-exclamation-triangle"></i> Text Collisions Detected</div>
            <button onclick="document.getElementById('conflictModal').classList.remove('active')" class="su-modal-close">✕</button>
        </div>
        
        <div style="padding:15px 20px 0; font-size:0.85rem; color:var(--pl-text-dim);">
            Some sketches referenced in this PLUSH story already possess overlay texts. Select which ones should be overwritten with the new PLUSH highlight texts. Unchecked sketches will retain their existing texts.
            <div style="margin-top:10px;">
                <label class="su-check-label" style="color:var(--pl-text);">
                    <input type="checkbox" id="checkAllConflicts" onchange="PlunarApp.toggleAllConflicts(this.checked)"> Select All Overwrites
                </label>
            </div>
        </div>

        <div class="conflict-list" id="conflictList"></div>

        <div style="padding:15px 20px; border-top:1px solid var(--pl-border); background:var(--pl-card); display:flex; justify-content:flex-end; gap:10px; border-radius:0 0 8px 8px;">
            <button onclick="document.getElementById('conflictModal').classList.remove('active')" style="padding:10px 15px; border-radius:6px; border:1px solid var(--pl-border); background:transparent; color:var(--pl-text); cursor:pointer;">Cancel</button>
            <button onclick="PlunarApp.executePlushMigration()" style="padding:10px 20px; border-radius:6px; border:none; background:var(--pl-amber); color:#000; font-weight:bold; cursor:pointer;">Confirm & Migrate</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script>
const PlunarApp = (() => {
    let timers = {};
    let state = {
        seq: { id: null, name: '' },
        plush: { id: null, name: '' }
    };

    function search(type, q) {
        clearTimeout(timers[type]);
        const ac = document.getElementById(type === 'sequences' ? 'acSeq' : 'acPlush');
        if (!q.trim()) { ac.style.display = 'none'; return; }
        
        timers[type] = setTimeout(() => {
            fetch('plunar.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'search_' + type, q })
            }).then(r=>r.json()).then(res => {
                if (res.success) {
                    if (!res.results.length) { ac.style.display = 'none'; return; }
                    ac.innerHTML = res.results.map(r => `
                        <div class="ac-item" onclick="PlunarApp.select('${type}', ${r.id}, '${r.name.replace(/'/g, "\\'")}')">
                            <span style="color:var(--pl-text-dim); font-family:monospace; margin-right:6px;">#${r.id}</span>
                            ${r.name}
                        </div>
                    `).join('');
                    ac.style.display = 'block';
                }
            });
        }, 250);
    }

    function select(type, id, name) {
        const isSeq = type === 'sequences';
        const key = isSeq ? 'seq' : 'plush';
        
        state[key] = { id, name };
        
        document.getElementById(isSeq ? 'wrapSeq' : 'wrapPlush').style.display = 'none';
        document.getElementById(isSeq ? 'acSeq' : 'acPlush').style.display = 'none';
        
        const chip = document.getElementById(isSeq ? 'selectedSeq' : 'selectedPlush');
        document.getElementById(isSeq ? 'lblSeq' : 'lblPlush').innerHTML = `<span style="color:var(--pl-${isSeq?'teal':'amber'}); margin-right:8px;">#${id}</span> ${name}`;
        chip.classList.add('active');

        document.getElementById(isSeq ? 'btnDir1' : 'btnDir2').disabled = false;
    }

    function clearSelection(key) {
        state[key] = { id: null, name: '' };
        
        const isSeq = key === 'seq';
        document.getElementById(isSeq ? 'selectedSeq' : 'selectedPlush').classList.remove('active');
        document.getElementById(isSeq ? 'wrapSeq' : 'wrapPlush').style.display = 'block';
        const inp = document.getElementById(isSeq ? 'searchSeq' : 'searchPlush');
        inp.value = ''; inp.focus();
        
        document.getElementById(isSeq ? 'btnDir1' : 'btnDir2').disabled = true;
    }

    function migrateToPlush() {
        if (!state.seq.id) return;
        const btn = document.getElementById('btnDir1');
        const orig = btn.innerHTML;
        btn.innerHTML = 'Migrating...'; btn.disabled = true;

        fetch('plunar.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'migrate_to_plush', sequence_id: state.seq.id })
        }).then(r=>r.json()).then(res => {
            if (res.success) {
                Toast.show('Migration complete!', 'success');
                setTimeout(() => window.location.href = `plush.php?id=${res.new_story_id}`, 1000);
            } else {
                Toast.show(res.message || 'Error', 'error');
                btn.innerHTML = orig; btn.disabled = false;
            }
        });
    }

    function analyzePlush() {
        if (!state.plush.id) return;
        const btn = document.getElementById('btnDir2');
        const orig = btn.innerHTML;
        btn.innerHTML = 'Analyzing...'; btn.disabled = true;

        fetch('plunar.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'analyze_plush_to_narrative', story_id: state.plush.id })
        }).then(r=>r.json()).then(res => {
            btn.innerHTML = orig; btn.disabled = false;
            if (res.success) {
                if (res.conflicts && res.conflicts.length > 0) {
                    showConflicts(res.conflicts);
                } else {
                    executePlushMigration([]); // No conflicts, proceed immediately
                }
            } else {
                Toast.show(res.message || 'Error', 'error');
            }
        });
    }

    function showConflicts(conflicts) {
        document.getElementById('checkAllConflicts').checked = false;
        const list = document.getElementById('conflictList');
        list.innerHTML = conflicts.map(c => `
            <div class="conflict-item">
                <div class="conflict-header">
                    <span>#${c.sketch_id} — ${c.sketch_name}</span>
                    <label class="su-check-label">
                        <input type="checkbox" class="conflict-cb" value="${c.sketch_id}"> Overwrite
                    </label>
                </div>
                <div class="conflict-text-grid">
                    <div>
                        <div class="conflict-title">Existing Texts</div>
                        <div class="conflict-text-box">${escHtml(c.existing_text)}</div>
                    </div>
                    <div>
                        <div class="conflict-title" style="color:var(--pl-amber);">New PLUSH Texts</div>
                        <div class="conflict-text-box">${escHtml(c.new_text)}</div>
                    </div>
                </div>
            </div>
        `).join('');
        document.getElementById('conflictModal').classList.add('active');
    }

    function toggleAllConflicts(checked) {
        document.querySelectorAll('.conflict-cb').forEach(cb => cb.checked = checked);
    }

    function executePlushMigration(forceOverwriteIds = null) {
        let overwriteIds = forceOverwriteIds;
        if (overwriteIds === null) {
            overwriteIds = Array.from(document.querySelectorAll('.conflict-cb:checked')).map(cb => parseInt(cb.value));
        }

        document.getElementById('conflictModal').classList.remove('active');
        
        const btn = document.getElementById('btnDir2');
        const orig = btn.innerHTML;
        btn.innerHTML = 'Migrating...'; btn.disabled = true;

        fetch('plunar.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                action: 'migrate_to_narrative', 
                story_id: state.plush.id,
                overwrite_sketch_ids: overwriteIds
            })
        }).then(r=>r.json()).then(res => {
            if (res.success) {
                Toast.show('Migration complete!', 'success');
                setTimeout(() => window.location.href = `narseq.php?id=${res.new_sequence_id}`, 1000);
            } else {
                Toast.show(res.message || 'Error', 'error');
                btn.innerHTML = orig; btn.disabled = false;
            }
        });
    }

    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    return { search, select, clearSelection, migrateToPlush, analyzePlush, toggleAllConflicts, executePlushMigration };
})();

// Hide AC on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('.ac-wrap')) {
        document.querySelectorAll('.ac-dropdown').forEach(el => el.style.display = 'none');
    }
});
</script>

<?php
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>