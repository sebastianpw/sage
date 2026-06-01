<?php
// public/narseq.php
// Narrative Sequencer: Split, Copy & Reorder Tool (Forge UI)

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$seqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper to resolve frame thumbnails identically to cinemagic_editor.php
function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = '';
    foreach (['thumb', 'thumbnail', 'image', 'image_url', 'image_path', 'file_path', 'path', 'src', 'url', 'filename', 'file_name'] as $key) {
        if (!empty($row[$key]) && is_string($row[$key])) {
            $candidate = $row[$key]; break;
        }
    }
    if ($candidate !== '') {
        if (strpos($candidate, 'http') !== 0 && strpos($candidate, 'view_frame.php') === false) {
            $parts = array_map('rawurlencode', explode('/', ltrim($candidate, '/')));
            return '/' . implode('/', $parts);
        }
        return $candidate;
    }
    return $frameId > 0 ? 'view_frame.php?frame_id=' . $frameId : '';
}

// ── Sequence List View (if no ID is provided) ─────────────────────────────────
if (!$seqId) {
    $seqs = $pdo->query("SELECT id, name, created_at, sequence_data FROM narrative_sequences ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
    :root, [data-theme="dark"] {
        --pl-bg: #080b10; --pl-surface: #0e1319; --pl-card: #111820; --pl-border: #1c2535;
        --pl-text: #c8d4e8; --pl-text-dim: #5a6a80; --pl-amber: #f5a623; --pl-teal: #3ab5c8;
    }
    [data-theme="light"] {
        --pl-bg: #f4f6fa; --pl-surface: #ffffff; --pl-card: #ffffff; --pl-border: #d0d8e8;
        --pl-text: #1a2233; --pl-text-dim: #7a8aaa; --pl-amber: #c8880a; --pl-teal: #1a8090;
    }
    body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', sans-serif; }
    
    .su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
    .su-modal-backdrop.active { display:flex; }
    .su-modal-box { width:100%; max-width:440px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; padding:20px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
    .su-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
    .su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-teal); text-transform:uppercase; letter-spacing:1px; }
    .su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
    .su-input { width:100%; box-sizing:border-box; background:var(--pl-card); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s; }
    .su-input:focus { border-color:var(--pl-teal); }
    .pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
    .pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-card); color:var(--pl-text-dim); }
    .pl-btn-secondary:hover { border-color:var(--pl-teal); color:var(--pl-teal); }
    .pl-btn-primary { border-color:var(--pl-teal); background:var(--pl-teal); color:#000; font-weight:bold; }
    .pl-btn-primary:hover { filter:brightness(1.1); }
    </style>

    <div style="max-width:700px;margin:60px auto;padding:20px;">
        <h2 style="font-family:'Space Mono',monospace;color:var(--pl-teal);">✂️ Sequence Split & Copy Tool</h2>
        <p style="color:var(--pl-text-dim);font-size:.85rem;">Select a narrative sequence to split or copy:</p>

        <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
        <?php foreach ($seqs as $s): ?>
            <div style="display:flex;align-items:center;background:var(--pl-card);border:1px solid var(--pl-border);border-radius:6px;overflow:hidden;transition:border-color .2s;"
                 onmouseover="this.style.borderColor='var(--pl-teal)'" onmouseout="this.style.borderColor='var(--pl-border)'">
                <a href="?id=<?= $s['id'] ?>"
                   style="display:flex;justify-content:space-between;align-items:center;flex:1;padding:12px 14px;text-decoration:none;color:var(--pl-text);font-family:'Space Mono',monospace;font-size:.85rem;">
                    <?php $sktCount = count(json_decode($s['sequence_data'] ?? '[]', true) ?: []); ?>
                    <span>
                        #<?= $s['id'] ?> 
                        <span style="color:var(--pl-teal);opacity:0.8;margin:0 6px;">[<?= $sktCount ?> skts]</span> 
                        <?= htmlspecialchars($s['name']) ?>
                    </span>
                    <span style="color:var(--pl-text-dim);"><?= date('Y-m-d', strtotime($s['created_at'])) ?></span>
                </a>
                <button
                    onclick="openCopyModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')"
                    title="Copy this sequence"
                    style="background:transparent;border:none;border-left:1px solid var(--pl-border);padding:0 16px;height:100%;cursor:pointer;color:var(--pl-text-dim);font-size:1rem;display:flex;align-items:center;align-self:stretch;transition:color .2s,background .2s;"
                    onmouseover="this.style.color='var(--pl-teal)';this.style.background='rgba(58,181,200,0.07)'"
                    onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'">
                    <i class="bi bi-files"></i>
                </button>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Copy Modal -->
    <div id="copyModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeCopyModal()">
        <div class="su-modal-box">
            <div class="su-modal-header">
                <div class="su-modal-title">Copy Sequence</div>
                <button onclick="closeCopyModal()" class="su-modal-close">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" id="copySeqId">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">New Sequence Name</label>
                    <input type="text" id="copySeqName" class="su-input" placeholder="New sequence name…">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <button onclick="closeCopyModal()" class="pl-btn pl-btn-secondary">Cancel</button>
                <button onclick="submitCopy()" class="pl-btn pl-btn-primary">Copy & Load</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/toast.js"></script>
    <script>
    function openCopyModal(id, currentName) {
        document.getElementById('copySeqId').value = id;
        document.getElementById('copySeqName').value = currentName + ' copy';
        document.getElementById('copyModal').classList.add('active');
        setTimeout(() => document.getElementById('copySeqName').focus(), 50);
    }
    function closeCopyModal() {
        document.getElementById('copyModal').classList.remove('active');
    }
    function submitCopy() {
        const id = document.getElementById('copySeqId').value;
        const name = document.getElementById('copySeqName').value.trim();
        if(!name) return Toast.show('Name required', 'warn');
        
        const fd = new URLSearchParams();
        fd.append('action', 'copy_sequence');
        fd.append('sequence_id', id);
        fd.append('new_name', name);

        fetch('narseq_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res => {
                if (res.success) window.location.href = '?id=' + res.new_sequence_id;
                else Toast.show(res.message || 'Copy failed', 'error');
            });
    }
    </script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Sequence - Narrative Splitter', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Sequence Editor View ──────────────────────────────────────────────────────
$seqStmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
$seqStmt->execute([$seqId]);
$seq = $seqStmt->fetch(PDO::FETCH_ASSOC);
if (!$seq) die("<div style='padding:40px;color:red;'>Sequence #$seqId not found.</div>");

$itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];

$pureSketchIds = [];
$selectedFrameIds = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0) $pureSketchIds[] = $sid;
    $selectedFrameIds[$idx] = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : null;
}
$pureSketchIds = array_values(array_unique($pureSketchIds));

$sketchesData = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtS = $pdo->prepare("SELECT id, name, description FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }
}

// --- Fetch ALL frames belonging to the relevant sketches ---
$framesBySketch = [];
if (!empty($pureSketchIds)) {
    $inClauseF = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtAllF = $pdo->prepare("
        SELECT id, filename, entity_id AS sketch_id FROM frames WHERE entity_type='sketches' AND entity_id IN ($inClauseF)
        UNION
        SELECT f.id, f.filename, f2s.to_id AS sketch_id FROM frames f JOIN frames_2_sketches f2s ON f2s.from_id = f.id WHERE f2s.to_id IN ($inClauseF)
        ORDER BY id DESC
    ");
    $stmtAllF->execute(array_merge($pureSketchIds, $pureSketchIds));
    foreach ($stmtAllF->fetchAll(PDO::FETCH_ASSOC) as $fr) {
        $sid = (int)$fr['sketch_id'];
        $framesBySketch[$sid][] = [
            'id' => (int)$fr['id'],
            'filename' => resolveFrameThumb($fr, (int)$fr['id'])
        ];
    }
}

// Quick map for initial rendering
$selectedFrameMap = [];
$activeFrameIds   = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $selectedFrameMap[(int)$row['id']] = resolveFrameThumb($row, (int)$row['id']);
    }
}

$sketchIdsNeedingLatestFrame = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0 && empty($selectedFrameIds[$idx])) $sketchIdsNeedingLatestFrame[] = $sid;
}
$sketchIdsNeedingLatestFrame = array_values(array_unique($sketchIdsNeedingLatestFrame));

$latestFrameBySketch = [];
if (!empty($sketchIdsNeedingLatestFrame)) {
    $inClauseFb = implode(',', array_fill(0, count($sketchIdsNeedingLatestFrame), '?'));
    $stmtFb = $pdo->prepare("SELECT f.*, f.entity_id AS _sketch_id FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id IN ($inClauseFb) ORDER BY f.id DESC");
    $stmtFb->execute($sketchIdsNeedingLatestFrame);
    foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sid])) {
            $latestFrameBySketch[$sid] = [
                'filename' => resolveFrameThumb($row, (int)$row['id']),
                'id'       => (int)$row['id']
            ];
        }
    }
}

$pageTitle = "Split Sequence: " . htmlspecialchars($seq['name']);
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-amber:       #f5a623;
    --pl-teal:        #3ab5c8;
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
}

body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', system-ui, sans-serif; margin: 0; padding: 0; }

.pl-nav { display:flex; align-items:center; gap:10px; padding:10px 16px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); }
[data-theme="light"] .pl-nav { background:rgba(244,246,250,.92); }
.pl-nav-title { font-family:'Space Mono',monospace; font-size:.8rem; color:var(--pl-text); flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pl-nav-btn { font-family:'Space Mono',monospace; font-size:.7rem; padding:6px 12px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); text-decoration:none; transition:all .2s; background:var(--pl-surface); cursor:pointer; }
.pl-nav-btn:hover { color:var(--pl-teal); border-color:var(--pl-teal); }

.workspace { max-width:900px; margin:0 auto; padding:30px 15px 100px; }

/* Item Wrapper */
.seq-item-wrap { position: relative; transition: opacity 0.2s; }
.seq-item-wrap:last-child .inline-add-row { display: none !important; }

/* Drag Hover States */
.seq-item-wrap.drag-over-top .scene-block { border-top: 2px solid var(--pl-teal); padding-top: 14px; }
.seq-item-wrap.drag-over-bottom .scene-block { border-bottom: 2px solid var(--pl-teal); padding-bottom: 14px; }
.drag-over-container { outline: 2px dashed var(--pl-teal); outline-offset: 10px; border-radius: 8px; }
.seq-item-wrap.dragging { opacity: 0.4; z-index: 999; }

.scene-block { position:relative; background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:16px; padding-left:36px; margin-bottom:10px; box-shadow:0 4px 15px rgba(0,0,0,.2); transition: border 0.15s, padding 0.15s; }
[data-theme="light"] .scene-block { box-shadow:0 2px 8px rgba(0,0,0,.05); }




/* Drag Handle */
.drag-handle { position:absolute; left:0; top:0; bottom:0; width:32px; display:flex; align-items:center; justify-content:center; color:var(--pl-text-dim); font-size:1rem; cursor:grab; opacity:0.3; transition:opacity .2s; touch-action:none; user-select:none; border-right:1px solid var(--pl-border); }
.scene-block:hover .drag-handle { opacity:1; }
.drag-handle:active { cursor:grabbing; color:var(--pl-teal); background:rgba(58,181,200,0.05); }




/* Remove Button */
.item-remove-btn {
    position: absolute; top: 8px; right: 8px; width: 30px; height: 30px;
    border-radius: 4px; background: rgba(0,0,0,0.1); color: var(--pl-text-dim);
    border: 1px solid rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s; font-size: 1.3rem; z-index: 10; 
    opacity: 0.85; /* Always visible now */
}
[data-theme="light"] .item-remove-btn { background: rgba(0,0,0,0.03); border-color: rgba(0,0,0,0.05); }
.item-remove-btn:hover, .item-remove-btn:active { 
    background: rgba(255, 68, 68, 0.1); color: #ff4444 !important; 
    border-color: rgba(255, 68, 68, 0.3); opacity: 1; 
}



.sketch-flex { display: flex; gap: 15px; align-items: center; }

/* PhotoSwipe Thumbnails */
.sketch-thumb { position: relative; width: 100px; height: 100px; flex-shrink: 0; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--pl-border); cursor: pointer; transition: filter 0.15s; }
.sketch-thumb:hover { filter: brightness(1.2); }
.sketch-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sketch-thumb a { display: block; width: 100%; height: 100%; }

.frame-cycle-btn { background: var(--pl-bg); border: 1px solid var(--pl-border); color: var(--pl-text-dim); border-radius: 4px; width: 46px; height: 26px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; font-size: 0.75rem; }
.frame-cycle-btn:hover { border-color: var(--pl-teal); color: var(--pl-teal); background: rgba(58,181,200,0.1); }

.sketch-info { flex: 1; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
.sketch-id { font-family: 'Space Mono', monospace; font-size: 0.65rem; color: var(--pl-teal); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 5px; }
.sketch-title { font-size: 1.05rem; font-weight: bold; margin: 0 0 5px 0; color: var(--pl-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; transition: color 0.15s; }
.sketch-title:hover { color: var(--pl-amber); }
.sketch-desc { font-size: 0.8rem; color: var(--pl-text-dim); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* Inline Split Divider */
.inline-add-row { display:flex; align-items:center; gap:8px; margin:8px 0; opacity:0.4; transition:opacity .2s; cursor:pointer; }
.inline-add-row:hover { opacity:1; }
.inline-add-row .add-divider { flex:1; height:1px; background:var(--pl-border); }
.inline-add-btn { display:flex; align-items:center; gap:6px; padding:4px 12px; border-radius:12px; border:1px dashed var(--pl-border); background:var(--pl-bg); color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.65rem; text-transform:uppercase; cursor:pointer; transition:all .2s; }
.inline-add-row:hover .inline-add-btn { border-color:var(--pl-amber); color:var(--pl-amber); background:rgba(245,166,35,.05); }

/* Modals */
.su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.su-modal-backdrop.active { display:flex; }
.su-modal-box { width:100%; max-width:440px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; padding:20px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; }
.su-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
.su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-amber); text-transform:uppercase; letter-spacing:1px; }
.su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
.su-input { width:100%; box-sizing:border-box; background:var(--pl-card); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s; }
.su-input:focus { border-color:var(--pl-teal); }
.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-card); color:var(--pl-text-dim); }
.pl-btn-secondary:hover { border-color:var(--pl-amber); color:var(--pl-amber); }
.pl-btn-primary { border-color:var(--pl-amber); background:var(--pl-amber); color:#000; font-weight:bold; }
.pl-btn-primary:hover { filter:brightness(1.1); }
.pl-btn-teal { border-color:var(--pl-teal); background:var(--pl-teal); color:#000; font-weight:bold; }
.pl-btn-teal:hover { filter:brightness(1.1); }

/* FORGE MODAL (Enhanimaticism Style) */
.compose-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.75);
    z-index: 300000; display: none; align-items: flex-end; justify-content: center;
}
.compose-modal-backdrop.active { display: flex; }
.compose-modal {
    width: 100%; max-width: 700px;
    background: var(--pl-surface); border: 1px solid var(--pl-border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
    height: 55vh; max-height: 80vh; resize: vertical; overflow: hidden; 
    display: flex; flex-direction: column;
}
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; flex-shrink:0; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--pl-border); border-radius: 2px; }
.cm-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--pl-border); flex-shrink: 0;
}
.cm-title { font-size: 0.9rem; font-weight: 700; color: var(--pl-teal); text-transform: uppercase; letter-spacing: 1px; font-family:'Space Mono', monospace; }
.cm-close-btn { background: transparent; border: 1px solid var(--pl-border); color: var(--pl-text-dim); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--pl-text); border-color: var(--pl-text); }

.forge-filters-bar { padding: 6px 12px; display: flex; gap: 6px; align-items: center; border-bottom: 1px solid var(--pl-border); overflow-x: auto; flex-shrink:0; min-height:34px; scrollbar-width:none; }
.forge-filters-bar::-webkit-scrollbar { display:none; }
.forge-pill { background: rgba(58,181,200,0.15); border: 1px solid rgba(58,181,200,0.3); color: var(--pl-teal); padding: 3px 8px; border-radius: 20px; font-size: 0.65rem; display: flex; align-items: center; gap: 6px; font-weight: bold; white-space:nowrap; }
.forge-pill-close { cursor: pointer; font-size: 0.8rem; opacity: 0.7; }
.forge-pill-close:hover { opacity: 1; color: #ef4444; }

.cm-body { display: flex; flex: 1; min-height: 0; padding: 0; }
.forge-sidebar {
    width: 120px; border-right: 1px solid var(--pl-border); padding: 8px 6px;
    display: flex; flex-direction: column; gap: 4px; overflow-y: auto; flex-shrink: 0;
}
.forge-sidebar-btn {
    width: 100%; padding: 8px; background: transparent; border: none; color: var(--pl-text-dim);
    text-align: left; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 0.75rem;
    font-family: 'Syne', sans-serif; transition: all 0.15s;
}
.forge-sidebar-btn:hover { background: rgba(255,255,255,0.05); color: var(--pl-text); }
.forge-sidebar-btn.active { background: rgba(58,181,200,0.15); color: var(--pl-teal); }

.forge-content { flex: 1; padding: 12px; overflow-y: auto; position: relative; }
.forge-tab-pane { display: none; flex-direction: column; gap: 8px; }
.forge-tab-pane.active { display: flex; }

.ff-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--pl-text-dim); letter-spacing: 1px; }
.ff-dropdown { border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card); max-height: 140px; overflow-y: auto; display: none; }
.ff-dropdown.open { display: block; }
.ff-dropdown-item { padding: 8px 10px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--pl-text); display: flex; justify-content: space-between; align-items:center; }
.ff-dropdown-item:hover { background: rgba(58,181,200,0.1); }

/* Grid for results */
.ff-result-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.ff-result-card {
    border: 1px solid var(--pl-border); border-radius: 4px; background: var(--pl-card);
    overflow: hidden; position: relative; aspect-ratio: 1; transition: border-color 0.15s;
}

.ff-result-card:hover { border-color: var(--pl-teal); }
.ff-pswp-item { display: block; width: 100%; height: 100%; }
.ff-result-card img { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; }
.ff-result-label {


    
    
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7);
    color: #fff; font-size: 0.6rem; padding: 3px 4px; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; pointer-events: none;
}
.ff-drag-indicator {
    position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.6); color: #fff;
    border-radius: 4px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; z-index: 10; cursor: grab; opacity: 0.85; transition: opacity 0.15s;
    touch-action: none; /* Prevents mobile scroll while dragging */
}
.ff-result-card:hover .ff-drag-indicator { opacity: 1; }
.ff-drag-indicator:active { background: var(--pl-teal); color: #000; cursor: grabbing; }
.forge-result-empty { grid-column: 1 / -1; text-align: center; padding: 20px 0; color: var(--pl-text-dim); font-size: 0.8rem; }

/* Ensure PhotoSwipe Lightbox pops over the 300,000 z-index modal */
.pswp { z-index: 400000 !important; }
</style>

<div class="pl-nav">
    <a href="narseq.php" class="pl-nav-link">&#9664; Sequences</a>
    <span class="pl-nav-title"><i class="bi bi-scissors"></i> Split Sequence: <?= htmlspecialchars($seq['name']) ?></span>
    <button class="pl-nav-btn" onclick="openForgeModal()" style="margin-left:auto; margin-right:10px; background:var(--pl-teal); color:#000; border-color:var(--pl-teal); font-weight:bold;">
        <i class="bi bi-funnel"></i> Forge Add
    </button>
    <button class="pl-nav-link" onclick="exportSequence(event)" title="Export JSON">
        <i class="bi bi-download"></i> JSON
    </button>
</div>

<div class="workspace">
    <div style="font-family:'Space Mono',monospace; font-size:0.75rem; color:var(--pl-text-dim); text-align:center; margin-bottom:30px;">
        Drag and drop items to reorder, use arrows to cycle frames, or click "SPLIT HERE" to divide this sequence.
    </div>

    <?php if (empty($itemIds)): ?>
        <div id="emptyListMsg" style="text-align:center;color:var(--pl-text-dim);padding:40px;font-style:italic;">This sequence is empty.</div>
    <?php endif; ?>

    <div id="sequenceList" class="editor-pswp-gallery">
    <?php foreach ($itemIds as $idx => $item):
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid <= 0 || !isset($sketchesData[$sid])) continue;
        $sketchRow = $sketchesData[$sid];
        
        $activeFrameId = $selectedFrameIds[$idx] ?? null;
        $thumb = '';
        if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
            $thumb = $selectedFrameMap[$activeFrameId];
        } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
            $thumb         = $latestFrameBySketch[$sid]['filename'];
            $activeFrameId = $latestFrameBySketch[$sid]['id'];
        }
        
        // Use first frame available as fallback
        if (!$activeFrameId && !empty($framesBySketch[$sid])) {
            $activeFrameId = $framesBySketch[$sid][0]['id'];
            $thumb         = $framesBySketch[$sid][0]['filename'];
        }
    ?>
    
    
    
    
    
        <div class="seq-item-wrap" data-idx="<?= $idx ?>">
            <div class="scene-block">
                <div class="item-remove-btn" title="Remove from sequence" onclick="removeSequenceItem(this)"><i class="bi bi-x"></i></div>
                <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                <div class="sketch-flex">
                
                
                    <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                        <div class="sketch-thumb" 
                             data-active-frame="<?= $activeFrameId ?>"
                             id="thumb-wrap-<?= $idx ?>">
                            <?php if ($thumb): ?>
                                <a href="<?= htmlspecialchars($thumb) ?>" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                    <img src="<?= htmlspecialchars($thumb) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                </a>
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--pl-text-dim); font-size:0.7rem;">No Image</div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($framesBySketch[$sid] ?? []) > 1): ?>
                        <div style="display:flex; gap:8px; margin-top:8px; width:100%; justify-content:space-between;">
                            <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, <?= $sid ?>, -1)" title="Previous Frame"><i class="bi bi-chevron-left"></i></button>
                            <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, <?= $sid ?>, 1)" title="Next Frame"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="sketch-info">
                        <div class="sketch-id">Item <span class="sketch-id-num"><?= sprintf('%02d', $idx + 1) ?></span> &bull; Sketch #<?= $sid ?></div>
                        <div class="sketch-title" onclick="openEntityModal('sketches', <?= $sid ?>, '<?= htmlspecialchars(addslashes($sketchRow['name'])) ?>')">
                            <?= htmlspecialchars($sketchRow['name']) ?>
                        </div>
                        <div class="sketch-desc"><?= htmlspecialchars($sketchRow['description'] ?? 'No description.') ?></div>
                    </div>
                </div>
            </div>
            
            <div class="inline-add-row" onclick="openSplitModal(this)">
                <div class="add-divider"></div>
                <button class="inline-add-btn"><i class="bi bi-scissors"></i> Split Here</button>
                <div class="add-divider"></div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- Split Modal -->
<div id="splitModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeSplitModal()">
    <div class="su-modal-box" style="max-width:500px;">
        <div class="su-modal-header">
            <div class="su-modal-title"><i class="bi bi-scissors"></i> Split Sequence</div>
            <button onclick="closeSplitModal()" class="su-modal-close">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <p style="font-size:0.85rem; color:var(--pl-text-dim); margin:0;">
                The original sequence will keep all items <strong>before</strong> the split point.<br>
                A new sequence will be created containing all items <strong>after</strong> the split point.
            </p>
            <input type="hidden" id="splitIndex">
            <div>
                <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">New Sequence Name (Part 2)</label>
                <input type="text" id="splitNewName" class="su-input">
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; gap:8px; margin-top:24px; flex-wrap:wrap;">
            <button onclick="closeSplitModal()" class="pl-btn pl-btn-secondary">Cancel</button>
            <div style="display:flex; gap:8px;">
                <button onclick="submitSplit('part1')" class="pl-btn pl-btn-primary">Save & Load Part 1</button>
                <button onclick="submitSplit('part2')" class="pl-btn pl-btn-teal">Save & Load Part 2</button>
            </div>
        </div>
    </div>
</div>

<!-- FORGE MODAL (Enhanimaticism Style) -->
<div class="compose-modal-backdrop" id="ffBackdrop" onmousedown="if(event.target===this)closeForgeModal()">
    <div class="compose-modal" id="ffModal">
        <div class="cm-handle" onclick="closeForgeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-funnel"></i> Filter Forge</div>
            <button class="cm-close-btn" onclick="closeForgeModal()">✕</button>
        </div>
        
        <!-- Active Filters Bar -->
        <div class="forge-filters-bar" id="ffActiveFilters">
            <div style="font-size:0.7rem; color:var(--pl-text-dim); font-style:italic;">No active filters.</div>
        </div>

        <div class="cm-body">
            <!-- Sidebar Tabs -->
            <div class="forge-sidebar">
                <button class="forge-sidebar-btn active" data-tab="fuzz" onclick="switchForgeTab('fuzz')">🧩 Fuzz</button>
                <button class="forge-sidebar-btn" data-tab="doc" onclick="switchForgeTab('doc')">📜 Doc</button>
                <button class="forge-sidebar-btn" data-tab="kg" onclick="switchForgeTab('kg')">🌳 KG</button>
                <button class="forge-sidebar-btn" data-tab="seq" onclick="switchForgeTab('seq')">🎬 Seq</button>
                <button class="forge-sidebar-btn" data-tab="storyboard" onclick="switchForgeTab('storyboard')">🖼️ Board</button>
                <button class="forge-sidebar-btn" data-tab="map_run" onclick="switchForgeTab('map_run')">🗺️ Run</button>
                <button class="forge-sidebar-btn" data-tab="vector" onclick="switchForgeTab('vector')">🔍 Semantic</button>
                <button class="forge-sidebar-btn" data-tab="id" onclick="switchForgeTab('id')">🔢 ID/Text</button>
                <hr style="border-color:var(--pl-border); margin:4px 0;">
                <button class="forge-sidebar-btn" data-tab="results" onclick="switchForgeTab('results')" style="color:var(--pl-amber); font-weight:bold;">▶ Results</button>
            </div>

            <!-- Content Area -->
            <div class="forge-content">
                <!-- FUZZ -->
                <div class="forge-tab-pane active" id="pane-fuzz">
                    <label class="ff-label">Fuzz Concept</label>
                    <input type="text" id="ffSearch-fuzz" class="su-input" placeholder="Search fuzz..." oninput="ffDebounceSearch('fuzz', this.value)">
                    <div class="ff-dropdown" id="ffDrop-fuzz"></div>
                </div>
                <!-- DOC -->
                <div class="forge-tab-pane" id="pane-doc">
                    <label class="ff-label">Lore Document</label>
                    <input type="text" id="ffSearch-doc" class="su-input" placeholder="Search docs..." oninput="ffDebounceSearch('doc', this.value)">
                    <div class="ff-dropdown" id="ffDrop-doc"></div>
                </div>
                <!-- KG -->
                <div class="forge-tab-pane" id="pane-kg">
                    <label class="ff-label">KG Node</label>
                    <input type="text" id="ffSearch-kg" class="su-input" placeholder="Search KG nodes..." oninput="ffDebounceSearch('kg', this.value)">
                    <div class="ff-dropdown" id="ffDrop-kg"></div>
                </div>
                <!-- SEQ -->
                <div class="forge-tab-pane" id="pane-seq">
                    <label class="ff-label">Narrative Sequence</label>
                    <input type="text" id="ffSearch-seq" class="su-input" placeholder="Search sequences..." oninput="ffDebounceSearch('seq', this.value)">
                    <div class="ff-dropdown" id="ffDrop-seq"></div>
                </div>
                <!-- STORYBOARD -->
                <div class="forge-tab-pane" id="pane-storyboard">
                    <label class="ff-label">Storyboard</label>
                    <input type="text" id="ffSearch-storyboard" class="su-input" placeholder="Search storyboards..." oninput="ffDebounceSearch('storyboard', this.value)">
                    <div class="ff-dropdown" id="ffDrop-storyboard"></div>
                </div>
                <!-- MAP RUN -->
                <div class="forge-tab-pane" id="pane-map_run">
                    <label class="ff-label">Map Run</label>
                    <input type="text" id="ffSearch-map_run" class="su-input" placeholder="Search map runs..." oninput="ffDebounceSearch('map_run', this.value)">
                    <div class="ff-dropdown" id="ffDrop-map_run"></div>
                </div>
                <!-- VECTOR -->
                <div class="forge-tab-pane" id="pane-vector">
                    <label class="ff-label">Semantic / Vector Search</label>
                    <textarea id="ffSearch-vector" class="su-input" style="height:80px; resize:none; margin-bottom:8px;" placeholder="Describe visually..."></textarea>
                    <button class="pl-btn pl-btn-teal" style="width:100%;" onclick="ffApplyVector()">Apply Semantic</button>
                </div>
                <!-- TEXT/ID -->
                <div class="forge-tab-pane" id="pane-id">
                    <label class="ff-label">Text Search</label>
                    <input type="text" id="ffSearch-text" class="su-input" placeholder="Name or description...">
                    
                    <label class="ff-label" style="margin-top:12px;">Sketch ID</label>
                    <input type="number" id="ffSearch-sketchId" class="su-input" placeholder="e.g. 1042">
                    
                    <label class="ff-label" style="margin-top:12px;">Frame ID</label>
                    <input type="number" id="ffSearch-frameId" class="su-input" placeholder="e.g. 5503">
                    
                    <button class="pl-btn pl-btn-teal" style="margin-top:12px; width:100%;" onclick="ffApplyTextId()">Apply Text/ID</button>
                </div>
                <!-- RESULTS (3x3 Grid) -->
                <div class="forge-tab-pane" id="pane-results">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:0.75rem; color:var(--pl-text-dim);" id="ffResultMeta">Results will appear here.</span>
                        <button class="pl-btn pl-btn-secondary" style="padding:4px 8px; font-size:0.65rem;" onclick="runForgeSearch(ffCurrentPage)">↻ Refresh</button>
                    </div>
                    
                    <!-- Fixed 3x3 Grid Layout -->
                    <div class="ff-result-grid" id="ffResultGrid"></div>
                    
                    <!-- Pagination directly mapped to the 3x3 layout (9 per page) -->
                    <div id="ffPagination" style="display:none; justify-content:space-between; align-items:center; margin-top:12px;">
                        <button class="pl-btn pl-btn-secondary" id="ffPrevBtn" onclick="runForgeSearch(ffCurrentPage - 1)">« Prev</button>
                        <span style="font-size:0.75rem; color:var(--pl-text-dim);" id="ffPageLabel">Page 1</span>
                        <button class="pl-btn pl-btn-secondary" id="ffNextBtn" onclick="runForgeSearch(ffCurrentPage + 1)">Next »</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Entity Details Modal (Iframe) -->
<div class="su-modal-backdrop" id="entity-modal-backdrop" onmousedown="if(event.target===this)closeEntityModal()">
    <div class="su-modal-box" style="max-width:700px; height:85vh; padding:0; display:flex; flex-direction:column; overflow:hidden;">
        <div class="su-modal-header" style="padding:10px 14px; border-bottom:1px solid var(--pl-border); margin:0; flex-shrink:0;">
            <span class="su-modal-title" id="entityModalTitle" style="color:var(--pl-teal);">Entity Details</span>
            <button class="su-modal-close" onclick="closeEntityModal()">✕</button>
        </div>
        <iframe id="entity-iframe" src="about:blank" style="flex:1; border:none; width:100%; background:var(--pl-card);"></iframe>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>




<!-- PhotoSwipe Lightbox Module -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    
    // Main Sequence Lightbox
    window.initLightbox = () => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.editor-pswp-gallery',
            children: '.editor-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    };

    // Dedicated Filter Forge Grid Lightbox
    window.forgeLightbox = null;
    window.initForgeLightbox = () => {
        if (window.forgeLightbox) {
            window.forgeLightbox.destroy(); // Tear down old event listeners safely
        }
        window.forgeLightbox = new PhotoSwipeLightbox({
            gallery: '#ffResultGrid',
            children: 'a.ff-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        window.forgeLightbox.init();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (window.initLightbox) window.initLightbox();
    });
</script>




<script>
const originalName = <?= json_encode($seq['name']) ?>;
const SEQ_ID = <?= $seqId ?>;
const frameRegistry = <?= json_encode($framesBySketch, JSON_UNESCAPED_UNICODE) ?>;

// ── Export Sequence ──────────────────────────────────────────────────────────
function exportSequence(e) {
    const btn = e.currentTarget;
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Exporting...';
    btn.disabled = true;

    const fd = new URLSearchParams();
    fd.append('action', 'export_sequence');
    fd.append('sequence_id', SEQ_ID);

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const dataStr = JSON.stringify(res.export, null, 2);
                const blob = new Blob([dataStr], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `sequence_${SEQ_ID}_export.json`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                if (window.Toast) Toast.show('Sequence exported.', 'success');
            } else {
                if (window.Toast) Toast.show(res.message || 'Export failed', 'error');
                else alert(res.message || 'Export failed');
            }
        })
        .catch(err => {
            if (window.Toast) Toast.show('Network error', 'error');
            else alert('Network error');
        })
        .finally(() => {
            btn.innerHTML = origHTML;
            btn.disabled = false;
        });
}

// ── Frame Cycling Logic ──────────────────────────────────────────────────────
function cycleSeqFrame(btnEl, sketchId, direction) {
    const wrapEl = btnEl.closest('.seq-item-wrap');
    if (!wrapEl) return;
    const currentIdx = wrapEl.dataset.idx; // This dynamically updates during reorder
    
    const frames = frameRegistry[sketchId];
    if (!frames || frames.length < 2) return;
    
    const thumbWrap = wrapEl.querySelector('.sketch-thumb');
    const link = thumbWrap.querySelector('a.editor-pswp-item');
    const img = thumbWrap.querySelector('img');
    if (!img || !link) return;
    
    let currentFid = parseInt(thumbWrap.dataset.activeFrame) || frames[0].id;
    let fIndex = frames.findIndex(f => f.id === currentFid);
    if (fIndex === -1) fIndex = 0;
    
    let newIndex = fIndex + direction;
    if (newIndex < 0) newIndex = frames.length - 1;
    if (newIndex >= frames.length) newIndex = 0;
    
    const newFrame = frames[newIndex];
    
    // Update UI locally (link href targets lightbox, img src targets thumbnail)
    link.href = newFrame.filename;
    img.src = newFrame.filename;
    thumbWrap.dataset.activeFrame = newFrame.id;
    
    // Clear dimensions so PhotoSwipe recalculates them smoothly on next load
    delete link.dataset.pswpWidth;
    delete link.dataset.pswpHeight;
    
    // Auto-save via API so reload persists
    const fd = new URLSearchParams();
    fd.append('action', 'update_item_frame');
    fd.append('sequence_id', SEQ_ID);
    fd.append('item_index', currentIdx);
    fd.append('frame_id', newFrame.id);
    
    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.Toast) Toast.show('Frame saved.', 'info');
            } else {
                if (window.Toast) Toast.show(res.message || 'Frame save failed', 'error');
            }
        }).catch(e => {
            if (window.Toast) Toast.show('Network error updating frame', 'error');
        });
}

// ── Drag & Drop Sequence Items ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('sequenceList');
    if (!container) return;

    let dragSrc = null;

    container.addEventListener('dragstart', e => {
        const wrap = e.target.closest('.seq-item-wrap');
        if (!wrap) return;
        dragSrc = wrap;
        wrap.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragend', e => {
        if (dragSrc) dragSrc.classList.remove('dragging');
        container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
        dragSrc = null;
        persistSortOrder();
    });

    container.addEventListener('dragover', e => {
        e.preventDefault();
        if (dragSrc) e.dataTransfer.dropEffect = 'move';
        
        const wrap = e.target.closest('.seq-item-wrap');
        if (!wrap || (dragSrc && wrap === dragSrc)) return;
        
        container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
        const rect = wrap.getBoundingClientRect();
        wrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
    });

    container.addEventListener('drop', e => {
        e.preventDefault();
        if (!dragSrc) return;
        const wrap = e.target.closest('.seq-item-wrap');
        if (!wrap || wrap === dragSrc) return;
        const rect = wrap.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) {
            container.insertBefore(dragSrc, wrap);
        } else {
            container.insertBefore(dragSrc, wrap.nextSibling);
        }
    });

    // Touch/Pointer Fallback for mobile devices
    let pointerDragSrc = null, pointerClone = null, pointerOffsetY = 0;
    let pointerForgeDragSrc = null, pointerForgeClone = null, forgeOffsetX = 0, forgeOffsetY = 0;

    // 1. Existing Sequence Reorder Logic
    container.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;
        const wrap = handle.closest('.seq-item-wrap');
        if (!wrap) return;
        pointerDragSrc = wrap;
        const rect = wrap.getBoundingClientRect();
        pointerOffsetY = e.clientY - rect.top;
        pointerClone = wrap.cloneNode(true);
        pointerClone.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;opacity:0.8;pointer-events:none;z-index:9999;background:transparent;`;
        document.body.appendChild(pointerClone);
        wrap.classList.add('dragging');
        e.preventDefault(); 
    }, { passive: false });

    // 2. New Filter Forge Drag Logic
    document.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.ff-drag-indicator');
        if (!handle) return;
        const card = handle.closest('.ff-result-card');
        if (!card) return;

        pointerForgeDragSrc = card;
        const rect = card.getBoundingClientRect();
        forgeOffsetX = e.clientX - rect.left;
        forgeOffsetY = e.clientY - rect.top;

        pointerForgeClone = card.cloneNode(true);
        pointerForgeClone.style.cssText = `
            position: fixed; left: ${rect.left}px; top: ${rect.top}px; 
            width: ${rect.width}px; height: ${rect.height}px;
            opacity: 0.95; pointer-events: none; z-index: 999999; 
            border: 2px solid var(--pl-teal); border-radius: 4px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
        `;
        document.body.appendChild(pointerForgeClone);
        
        // Immediately close the modal so we can drop into the main UI underneath
        closeForgeModal();

        // Lock pointer to this touch to prevent scrolling
        handle.setPointerCapture(e.pointerId);
        e.preventDefault();
    }, { passive: false });

    document.addEventListener('pointermove', e => {
        // Prevent scroll on mobile while dragging anything
        if (pointerClone || pointerForgeClone) e.preventDefault();

        // Moving sequence items
        if (pointerClone && pointerDragSrc) {
            pointerClone.style.top = (e.clientY - pointerOffsetY) + 'px';
            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const wrap = target ? target.closest('.seq-item-wrap') : null;
            if (wrap && wrap !== pointerDragSrc && wrap.parentNode === container) {
                const rect = wrap.getBoundingClientRect();
                wrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
            }
        }
        
        // Moving Forge results from closed modal into sequence
        if (pointerForgeClone && pointerForgeDragSrc) {
            pointerForgeClone.style.left = (e.clientX - forgeOffsetX) + 'px';
            pointerForgeClone.style.top = (e.clientY - forgeOffsetY) + 'px';
            
            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
            container.classList.remove('drag-over-container');
            
            pointerForgeClone.style.display = 'none'; // hide briefly for accurate elementFromPoint
            const target = document.elementFromPoint(e.clientX, e.clientY);
            pointerForgeClone.style.display = 'block';

            const wrap = target ? target.closest('.seq-item-wrap') : null;
            const isInsideContainer = target ? target.closest('#sequenceList') : null;
            
            if (wrap && wrap.parentNode === container) {
                const rect = wrap.getBoundingClientRect();
                wrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
            } else if (isInsideContainer) {
                container.classList.add('drag-over-container');
            }
        }
    }, { passive: false });

    document.addEventListener('pointerup', e => {
        // Drop Sequence Item
        if (pointerDragSrc) {
            if (pointerClone) { pointerClone.remove(); pointerClone = null; }
            pointerDragSrc.classList.remove('dragging');
            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
            
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const wrap = target ? target.closest('.seq-item-wrap') : null;
            if (wrap && wrap !== pointerDragSrc && wrap.parentNode === container) {
                const rect = wrap.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) {
                    container.insertBefore(pointerDragSrc, wrap);
                } else {
                    container.insertBefore(pointerDragSrc, wrap.nextSibling);
                }
            }
            persistSortOrder();
            pointerDragSrc = null;
        }
        
        // Drop Forge Item
        if (pointerForgeDragSrc) {
            const card = pointerForgeDragSrc;
            pointerForgeDragSrc = null;
            if (pointerForgeClone) { pointerForgeClone.remove(); pointerForgeClone = null; }
            
            container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
            container.classList.remove('drag-over-container');
            
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const wrap = target ? target.closest('.seq-item-wrap') : null;
            const isInsideContainer = target ? target.closest('#sequenceList') : null;
            
            if (isInsideContainer || wrap) {
                let insertIndex = -1;
                if (wrap && wrap.parentNode === container) {
                    const rect = wrap.getBoundingClientRect();
                    const wraps = Array.from(container.querySelectorAll('.seq-item-wrap'));
                    if (e.clientY < rect.top + rect.height / 2) {
                        insertIndex = wraps.indexOf(wrap);
                    } else {
                        insertIndex = wraps.indexOf(wrap) + 1;
                    }
                }
                insertForgeItemToSequence(card.dataset.sketchId, card.dataset.frameId, insertIndex);
            }
        }
    });

    function persistSortOrder() {
        const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
        const originalIndices = wraps.map(w => w.dataset.idx);

        const fd = new URLSearchParams();
        fd.append('action', 'reorder_sequence');
        fd.append('sequence_id', SEQ_ID);
        fd.append('order', originalIndices.join(','));

        fetch('narseq_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    // Update DOM metadata seamlessly
                    reindexSequenceVisuals();
                    if (window.Toast) Toast.show('Sequence reordered.', 'success');
                } else {
                    if (window.Toast) Toast.show(res.message || 'Reorder failed', 'error');
                }
            });
    }
});

function insertForgeItemToSequence(sketchId, frameId, insertIndex) {
    if (window.Toast) Toast.show('Inserting item...', 'info');
    
    const fd = new URLSearchParams();
    fd.append('action', 'insert_sequence_item');
    fd.append('sequence_id', SEQ_ID);
    fd.append('sketch_id', sketchId);
    fd.append('frame_id', frameId);
    fd.append('insert_index', insertIndex);

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                renderInsertedItem(res, res.insert_index);
                if (window.Toast) Toast.show('Item added to sequence.', 'success');
                
                const emptyMsg = document.getElementById('emptyListMsg');
                if (emptyMsg) emptyMsg.style.display = 'none';
            } else {
                if (window.Toast) Toast.show(res.message || 'Insert failed', 'error');
            }
        })
        .catch(err => {
            if (window.Toast) Toast.show('Network error inserting item', 'error');
        });
}

function renderInsertedItem(res, insertIndex) {
    const container = document.getElementById('sequenceList');
    frameRegistry[res.sketch.id] = res.all_frames;
    
    const wrap = document.createElement('div');
    wrap.className = 'seq-item-wrap';
    
    let cycleBtns = '';
    if (res.all_frames.length > 1) {
        cycleBtns = `
            <div style="display:flex; gap:8px; margin-top:8px; width:100%; justify-content:space-between;">
                <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, ${res.sketch.id}, -1)" title="Previous Frame"><i class="bi bi-chevron-left"></i></button>
                <button class="frame-cycle-btn" onclick="cycleSeqFrame(this, ${res.sketch.id}, 1)" title="Next Frame"><i class="bi bi-chevron-right"></i></button>
            </div>
        `;
    }
    
    const safeName = res.sketch.name ? res.sketch.name.replace(/"/g, '&quot;').replace(/'/g, "\\'") : '';
    const safeDesc = res.sketch.description ? res.sketch.description.replace(/</g, '&lt;').replace(/>/g, '&gt;') : 'No description.';
    
    wrap.innerHTML = `
        <div class="scene-block">
            <div class="item-remove-btn" title="Remove from sequence" onclick="removeSequenceItem(this)"><i class="bi bi-x"></i></div>
            <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
            <div class="sketch-flex">


            
            
            
                <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                    <div class="sketch-thumb" data-active-frame="${res.frame.id}">
                        <a href="${res.frame.filename}" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                            <img src="${res.frame.filename}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                        </a>
                    </div>
                    ${cycleBtns}
                </div>
                <div class="sketch-info">
                    <div class="sketch-id">Item <span class="sketch-id-num">--</span> &bull; Sketch #${res.sketch.id}</div>
                    <div class="sketch-title" onclick="openEntityModal('sketches', ${res.sketch.id}, '${safeName}')">
                        ${res.sketch.name}
                    </div>
                    <div class="sketch-desc">${safeDesc}</div>
                </div>
            </div>
        </div>
        <div class="inline-add-row" onclick="openSplitModal(this)">
            <div class="add-divider"></div>
            <button class="inline-add-btn"><i class="bi bi-scissors"></i> Split Here</button>
            <div class="add-divider"></div>
        </div>
    `;
    
    const existingWraps = Array.from(container.querySelectorAll('.seq-item-wrap'));
    if (insertIndex === -1 || insertIndex >= existingWraps.length) {
        container.appendChild(wrap);
    } else {
        container.insertBefore(wrap, existingWraps[insertIndex]);
    }
    
    reindexSequenceVisuals();
    if (window.initLightbox) window.initLightbox();
    closeForgeModal();
}

function reindexSequenceVisuals() {
    const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
    wraps.forEach((w, i) => {
        w.dataset.idx = i;
        const numLabel = w.querySelector('.sketch-id-num');
        if (numLabel) numLabel.textContent = String(i + 1).padStart(2, '0');
    });
}




function removeSequenceItem(btn) {
    if (!confirm('Remove this item from the sequence?')) return;
    
    const wrap = btn.closest('.seq-item-wrap');
    if (!wrap) return;
    
    // Disable button to prevent double-clicks
    btn.style.pointerEvents = 'none';
    btn.innerHTML = '⋯';
    
    const idx = wrap.dataset.idx;
    
    const fd = new URLSearchParams();
    fd.append('action', 'remove_sequence_item');
    fd.append('sequence_id', SEQ_ID);
    fd.append('item_index', idx);
    
    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Smooth fade out
                wrap.style.transition = 'all 0.3s ease';
                wrap.style.opacity = '0';
                wrap.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    wrap.remove();
                    reindexSequenceVisuals();
                    // Restore empty state message if sequence is empty
                    if (document.querySelectorAll('.seq-item-wrap').length === 0) {
                        const emptyMsg = document.getElementById('emptyListMsg');
                        if (emptyMsg) emptyMsg.style.display = 'block';
                    }
                    if (window.Toast) Toast.show('Item removed.', 'success');
                }, 300);
            } else {
                btn.style.pointerEvents = '';
                btn.innerHTML = '<i class="bi bi-x"></i>';
                if (window.Toast) Toast.show(res.message || 'Remove failed', 'error');
            }
        })
        .catch(err => {
            btn.style.pointerEvents = '';
            btn.innerHTML = '<i class="bi bi-x"></i>';
            if (window.Toast) Toast.show('Network error', 'error');
        });
}








// ── Forge Filter Enhanimaticism Logic ──────────────────────────────────────────
let ffState = {
    fuzz: null, doc: null, kg: null, seq: null, storyboard: null, map_run: null,
    vectorText: '', textSearch: '', sketchId: '', frameId: ''
};
let ffCurrentPage = 1;
let ffTotalPages = 1;
let ffDebounceTimer;

function openForgeModal() {
    document.getElementById('ffBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeForgeModal() {
    document.getElementById('ffBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function switchForgeTab(tabId) {
    document.querySelectorAll('.forge-sidebar-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.forge-sidebar-btn[data-tab="${tabId}"]`).classList.add('active');
    
    document.querySelectorAll('.forge-tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById(`pane-${tabId}`).classList.add('active');
    
    if (tabId === 'results') runForgeSearch(1);
}

function ffDebounceSearch(slot, q) {
    clearTimeout(ffDebounceTimer);
    ffDebounceTimer = setTimeout(() => {
        const dd = document.getElementById(`ffDrop-${slot}`);
        if (!q) { dd.classList.remove('open'); return; }
        dd.innerHTML = '<div class="forge-dropdown-loading">Searching...</div>';
        dd.classList.add('open');
        
        fetch(`filter_forge_api.php?action=list_filter_options&mode=${slot}&q=${encodeURIComponent(q)}&entity_type=sketches`)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data.length) {
                    dd.innerHTML = '<div class="forge-dropdown-loading">No results</div>';
                    return;
                }
                dd.innerHTML = res.data.map(item => {
                    const safe = JSON.stringify(item).replace(/"/g, '&quot;');
                    return `<div class="ff-dropdown-item" onclick="ffSelectItem('${slot}', ${safe})">
                        <span>${item.label}</span>
                        <span class="forge-dropdown-item-meta">${item.meta||''}</span>
                    </div>`;
                }).join('');
            })
            .catch(err => console.error("Filter Search Error", err));
    }, 300);
}

function ffSelectItem(slot, item) {
    ffState[slot] = item;
    document.getElementById(`ffSearch-${slot}`).value = '';
    document.getElementById(`ffDrop-${slot}`).classList.remove('open');
    renderActiveFilters();
    switchForgeTab('results'); // Auto-switch to results
}

function ffApplyVector() {
    ffState.vectorText = document.getElementById('ffSearch-vector').value.trim();
    renderActiveFilters();
    switchForgeTab('results');
}

function ffApplyTextId() {
    ffState.textSearch = document.getElementById('ffSearch-text').value.trim();
    ffState.sketchId = document.getElementById('ffSearch-sketchId').value.trim();
    ffState.frameId = document.getElementById('ffSearch-frameId').value.trim();
    renderActiveFilters();
    switchForgeTab('results');
}

function removeFfFilter(key) {
    if (['vectorText', 'textSearch', 'sketchId', 'frameId'].includes(key)) {
        ffState[key] = '';
        const el = document.getElementById(`ffSearch-${key.replace('Text','-text').replace('Search','-text').replace('vectorText','-vector').replace('sketchId','-sketchId').replace('frameId','-frameId')}`);
        if (el) el.value = '';
    } else {
        ffState[key] = null;
    }
    renderActiveFilters();
    runForgeSearch(1);
}

function renderActiveFilters() {
    const bar = document.getElementById('ffActiveFilters');
    bar.innerHTML = '';
    const labels = {
        fuzz: 'Fuzz', doc: 'Doc', kg: 'KG', seq: 'Seq', storyboard: 'Board', map_run: 'Run',
        vectorText: 'Semantic', textSearch: 'Text', sketchId: 'Sketch', frameId: 'Frame'
    };
    
    let hasAny = false;
    for (const [k, v] of Object.entries(ffState)) {
        if (v && (typeof v === 'object' ? v.id : v.toString().length > 0)) {
            hasAny = true;
            const display = typeof v === 'object' ? v.label : v;
            bar.innerHTML += `<div class="forge-pill">${labels[k]}: ${display} <span class="forge-pill-close" onclick="removeFfFilter('${k}')">×</span></div>`;
        }
    }
    if (!hasAny) {
        bar.innerHTML = '<div style="font-size:0.7rem; color:var(--pl-text-dim); font-style:italic;">No active filters.</div>';
    }
}





function runForgeSearch(page) {
    ffCurrentPage = page;
    const p = new URLSearchParams();
    p.set('action', 'list_frames');
    p.set('entity_type', 'sketches');
    p.set('filter_mode', 'intersection');
    p.set('per_page', '9'); // Fixed exactly 9 for 3x3 layout to fit comfortably
    p.set('page', page);

    let hasFilter = false;

    if (ffState.fuzz) { p.set('fuzz_id', ffState.fuzz.id); hasFilter = true; }
    if (ffState.doc) { p.set('doc_id', ffState.doc.id); hasFilter = true; }
    if (ffState.kg) { p.set('kg_node_id', ffState.kg.id); hasFilter = true; }
    if (ffState.seq) { p.set('seq_id', ffState.seq.id); hasFilter = true; }
    if (ffState.storyboard) { p.set('storyboard_id', ffState.storyboard.id); hasFilter = true; }
    if (ffState.map_run) { p.set('map_run_id', ffState.map_run.id); hasFilter = true; }
    if (ffState.vectorText) { p.set('vector_text', ffState.vectorText); hasFilter = true; }
    if (ffState.textSearch) { p.set('search', ffState.textSearch); hasFilter = true; }
    if (ffState.sketchId) { p.set('entity_id', ffState.sketchId); hasFilter = true; }
    if (ffState.frameId) { p.set('frame_id', ffState.frameId); hasFilter = true; }

    // If no specific filters are applied, request newest frames first (frames.id DESC)
    if (!hasFilter) {
        p.set('sort', 'newest');
        p.set('sort_by', 'id');
        p.set('sort_order', 'desc');
    }

    const grid = document.getElementById('ffResultGrid');
    
    
    
    
    
    
    
    grid.innerHTML = '<div class="forge-result-empty">Searching Forge...</div>';
    document.getElementById('ffPagination').style.display = 'none';
    document.getElementById('ffResultMeta').textContent = 'Searching...';

    fetch('filter_forge_api.php?' + p.toString())
        .then(async r => {
            if (!r.ok) throw new Error('HTTP status ' + r.status);
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("JSON Parse Error on Forge Search:", text);
                throw new Error("Invalid JSON returned by filter_forge_api.php");
            }
        })
        .then(res => {
            if (res.status !== 'success') {
                grid.innerHTML = `<div class="forge-result-empty">Error: ${res.message}</div>`;
                return;
            }
            
            ffTotalPages = res.meta.pages;
            document.getElementById('ffResultMeta').textContent = `Found ${res.meta.total} matches.`;
            
            if (!res.data.length) {
                grid.innerHTML = '<div class="forge-result-empty">No results found.</div>';
                return;
            }
            
            
            
            
            
grid.innerHTML = res.data.map(row => `
                <div class="ff-result-card" data-sketch-id="${row.entity_id}" data-frame-id="${row.frame_id}">
                    <div class="ff-drag-indicator" title="Drag to insert" onclick="event.preventDefault(); event.stopPropagation();"><i class="bi bi-arrows-move"></i></div>
                    <a href="${row.filename}" class="ff-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="${row.filename}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                    </a>
                    <div class="ff-result-label">${row.entity_name || row.frame_name || ''}</div>
                </div>
            `).join('');

            if (ffTotalPages > 1) {
                document.getElementById('ffPagination').style.display = 'flex';
                document.getElementById('ffPageLabel').innerHTML = `
                    <div style="display:flex; align-items:center; gap:4px;">
                        Pg <input type="number" value="${page}" min="1" max="${ffTotalPages}" 
                            style="width:40px; background:var(--pl-bg); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; text-align:center; padding:2px; font-size:0.75rem; font-family:'Space Mono', monospace;" 
                            onchange="if(this.value) runForgeSearch(parseInt(this.value))"> 
                        of ${ffTotalPages}
                    </div>
                `;
                document.getElementById('ffPrevBtn').disabled = (page <= 1);
                document.getElementById('ffNextBtn').disabled = (page >= ffTotalPages);
            }

            // Re-initialize lightbox for the new dynamically loaded grid elements
            if (window.initForgeLightbox) window.initForgeLightbox();
        })
        .catch(err => {

            
            
            
            
            
            
            
            console.error("Forge Search Network/Parse Error:", err);
            grid.innerHTML = '<div class="forge-result-empty">Network error. Check console.</div>';
        });
}

// ── Split Logic ──────────────────────────────────────────────────────────────
function openSplitModal(el) {
    const wrap = el.closest('.seq-item-wrap');
    const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
    // Split will happen AFTER this element
    const index = wraps.indexOf(wrap) + 1; 

    document.getElementById('splitIndex').value = index;
    document.getElementById('splitNewName').value = originalName + ' part 2';
    document.getElementById('splitModal').classList.add('active');
    setTimeout(() => document.getElementById('splitNewName').focus(), 50);
}

function closeSplitModal() {
    document.getElementById('splitModal').classList.remove('active');
}

function submitSplit(loadPart) {
    const splitIndex = document.getElementById('splitIndex').value;
    const newName = document.getElementById('splitNewName').value.trim();
    
    if (!newName) return Toast.show('Name is required.', 'warn');

    const fd = new URLSearchParams();
    fd.append('action', 'split_sequence');
    fd.append('sequence_id', SEQ_ID);
    fd.append('split_index', splitIndex);
    fd.append('new_name', newName);

    fetch('narseq_api.php', { method: 'POST', body: fd })
        .then(r=>r.json()).then(res => {
            if (res.success) {
                if (loadPart === 'part2') {
                    window.location.href = '?id=' + res.new_sequence_id;
                } else {
                    window.location.reload();
                }
            } else {
                Toast.show(res.message || 'Split failed', 'error');
            }
        });
}

// ── Entity Modals ────────────────────────────────────────────────────────────
function openEntityModal(entityType, entityId, label) {
    const url = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
    document.getElementById('entity-iframe').src = url;
    document.getElementById('entityModalTitle').textContent = label + ' — ' + entityType;
    document.getElementById('entity-modal-backdrop').classList.add('active');
}
function closeEntityModal() {
    document.getElementById('entity-modal-backdrop').classList.remove('active');
    document.getElementById('entity-iframe').src = 'about:blank';
}
</script>

<?php
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>