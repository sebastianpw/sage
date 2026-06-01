<?php
// public/sbcut.php
// Storyboard Sequencer: Split, Copy & Reorder Tool (Forge UI)

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$sbId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper to safely resolve a storyboard frame thumbnail
function resolveStoryboardThumb($frame) {
    if (!empty($frame['filename'])) {
        return $frame['filename'];
    }
    if (!empty($frame['original_filename'])) {
        if (strpos($frame['original_filename'], 'http') !== 0 && strpos($frame['original_filename'], 'view_frame.php') === false) {
            $parts = array_map('rawurlencode', explode('/', ltrim($frame['original_filename'], '/')));
            return '/' . implode('/', $parts);
        }
        return $frame['original_filename'];
    }
    if (!empty($frame['frame_id'])) {
        return 'view_frame.php?frame_id=' . (int)$frame['frame_id'];
    }
    return '';
}

// ── Storyboard List View (if no ID is provided) ───────────────────────────────
if (!$sbId) {
    $sbs = $pdo->query("
        SELECT s.id, s.name, s.created_at, 
               (SELECT COUNT(*) FROM storyboard_frames sf WHERE sf.storyboard_id = s.id) as frame_count 
        FROM storyboards s 
        WHERE s.is_archived = 0 
        ORDER BY s.updated_at DESC 
        LIMIT 300
    ")->fetchAll(PDO::FETCH_ASSOC);
    
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
        <h2 style="font-family:'Space Mono',monospace;color:var(--pl-teal);">✂️ Storyboard Split & Copy Tool</h2>
        <p style="color:var(--pl-text-dim);font-size:.85rem;">Select a storyboard to split, reorder, or copy:</p>

        <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
        <?php foreach ($sbs as $s): ?>
            <div style="display:flex;align-items:center;background:var(--pl-card);border:1px solid var(--pl-border);border-radius:6px;overflow:hidden;transition:border-color .2s;"
                 onmouseover="this.style.borderColor='var(--pl-teal)'" onmouseout="this.style.borderColor='var(--pl-border)'">
                <a href="?id=<?= $s['id'] ?>"
                   style="display:flex;justify-content:space-between;align-items:center;flex:1;padding:12px 14px;text-decoration:none;color:var(--pl-text);font-family:'Space Mono',monospace;font-size:.85rem;">
                    <span>
                        #<?= $s['id'] ?> 
                        <span style="color:var(--pl-teal);opacity:0.8;margin:0 6px;">[<?= $s['frame_count'] ?> frames]</span> 
                        <?= htmlspecialchars($s['name'] ?: 'Untitled') ?>
                    </span>
                    <span style="color:var(--pl-text-dim);"><?= date('Y-m-d', strtotime($s['created_at'])) ?></span>
                </a>
                <div style="display:flex; flex-direction:column; border-left:1px solid var(--pl-border); align-self:stretch; width:50px;">
                    <button
                        onclick="openCopyModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'] ?: 'Untitled')) ?>')"
                        title="Copy this storyboard"
                        style="flex:1; background:transparent; border:none; border-bottom:1px solid var(--pl-border); cursor:pointer; color:var(--pl-text-dim); font-size:1rem; display:flex; align-items:center; justify-content:center; transition:color .2s,background .2s;"
                        onmouseover="this.style.color='var(--pl-teal)';this.style.background='rgba(58,181,200,0.07)'"
                        onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'">
                        <i class="bi bi-files"></i>
                    </button>
                    <button
                        onclick="deleteStoryboard(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'] ?: 'Untitled')) ?>')"
                        title="Delete this storyboard"
                        style="flex:1; background:transparent; border:none; cursor:pointer; color:var(--pl-text-dim); font-size:1rem; display:flex; align-items:center; justify-content:center; transition:color .2s,background .2s;"
                        onmouseover="this.style.color='var(--pl-red)';this.style.background='rgba(240,80,96,0.07)'"
                        onmouseout="this.style.color='var(--pl-text-dim)';this.style.background='transparent'">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Copy Modal -->
    <div id="copyModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeCopyModal()">
        <div class="su-modal-box">
            <div class="su-modal-header">
                <div class="su-modal-title">Copy Storyboard</div>
                <button onclick="closeCopyModal()" class="su-modal-close">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" id="copySbId">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">New Storyboard Name</label>
                    <input type="text" id="copySbName" class="su-input" placeholder="New storyboard name…">
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
        document.getElementById('copySbId').value = id;
        document.getElementById('copySbName').value = currentName + ' copy';
        document.getElementById('copyModal').classList.add('active');
        setTimeout(() => document.getElementById('copySbName').focus(), 50);
    }
    function closeCopyModal() {
        document.getElementById('copyModal').classList.remove('active');
    }
function deleteStoryboard(id, name) {
        if(!confirm('Are you sure you want to completely delete storyboard "' + name + '"?')) return;
        
        const fd = new URLSearchParams();
        fd.append('action', 'delete_storyboard');
        fd.append('storyboard_id', id);

        fetch('sbcut_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res => {
                if (res.success) {
                    Toast.show('Storyboard deleted', 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    Toast.show(res.message || 'Delete failed', 'error');
                }
            });
    }

    function submitCopy() {
        const id = document.getElementById('copySbId').value;



        const name = document.getElementById('copySbName').value.trim();
        if(!name) return Toast.show('Name required', 'warn');
        
        const fd = new URLSearchParams();
        fd.append('action', 'copy_storyboard');
        fd.append('storyboard_id', id);
        fd.append('new_name', name);

        fetch('sbcut_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res => {
                if (res.success) window.location.href = '?id=' + res.new_storyboard_id;
                else Toast.show(res.message || 'Copy failed', 'error');
            });
    }
    </script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Storyboard - SB Splitter', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Storyboard Editor View ───────────────────────────────────────────────────
$sbStmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
$sbStmt->execute([$sbId]);
$sb = $sbStmt->fetch(PDO::FETCH_ASSOC);
if (!$sb) die("<div style='padding:40px;color:red;'>Storyboard #$sbId not found.</div>");



$framesStmt = $pdo->prepare("
    SELECT sf.*, f.entity_type, f.entity_id 
    FROM storyboard_frames sf 
    LEFT JOIN frames f ON sf.frame_id = f.id 
    WHERE sf.storyboard_id = ? 
    ORDER BY sf.sort_order ASC, sf.id ASC
");


$framesStmt->execute([$sbId]);
$frames = $framesStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Split Storyboard: " . htmlspecialchars($sb['name']);
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
    opacity: 0.85; /* Always visible for mobile */
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
</style>

<div class="pl-nav">
    <a href="sbcut.php" class="pl-nav-link">&#9664; Storyboards</a>
    <span class="pl-nav-title"><i class="bi bi-scissors"></i> Split Storyboard: <?= htmlspecialchars($sb['name']) ?></span>
    <button class="pl-nav-link" onclick="exportStoryboard(event)" title="Export JSON">
        <i class="bi bi-download"></i> JSON
    </button>
</div>

<div class="workspace">
    <div style="font-family:'Space Mono',monospace; font-size:0.75rem; color:var(--pl-text-dim); text-align:center; margin-bottom:30px;">
        Drag and drop items to reorder, or click "SPLIT HERE" to divide this storyboard into two.
    </div>

    <?php if (empty($frames)): ?>
        <div style="text-align:center;color:var(--pl-text-dim);padding:40px;font-style:italic;">This storyboard is empty.</div>
    <?php endif; ?>
    
    
    
    
    <div id="storyboardList" class="editor-pswp-gallery">
    <?php foreach ($frames as $idx => $frame):
        $thumb = resolveStoryboardThumb($frame);
    ?>
        <div class="seq-item-wrap" data-id="<?= $frame['id'] ?>">
            <div class="scene-block">
                <div class="item-remove-btn" title="Remove from storyboard" onclick="removeStoryboardItem(this)"><i class="bi bi-x"></i></div>
                <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                <div class="sketch-flex">



                
                
                
                
                    <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                        <div class="sketch-thumb">
                            <?php if ($thumb): ?>
                                <a href="<?= htmlspecialchars($thumb) ?>" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                    <img src="<?= htmlspecialchars($thumb) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                </a>
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--pl-text-dim); font-size:0.7rem;">No Image</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sketch-info">
                        <?php 
                        $eType = $frame['entity_type'] ?? 'sketches'; 
                        $eId = (int)($frame['entity_id'] ?? 0);
                        $clickAttr = ($eId > 0) ? "onclick=\"openEntityModal('".htmlspecialchars($eType)."', $eId, '".htmlspecialchars(addslashes($frame['name'] ?: 'Untitled'))."')\"" : '';
                        $cursorStyle = ($eId > 0) ? 'cursor:pointer;' : '';
                        ?>
                        <div class="sketch-id">Frame <span class="sketch-id-num"><?= sprintf('%02d', $idx + 1) ?></span> &bull; <?= $eId > 0 ? htmlspecialchars(ucfirst($eType)) . ' #' . $eId : 'DB #' . $frame['id'] ?></div>
                        <div class="sketch-title" style="<?= $cursorStyle ?>" <?= $clickAttr ?>>
                            <?= htmlspecialchars($frame['name'] ?: 'Untitled') ?>
                        </div>
                        <div class="sketch-desc"><?= htmlspecialchars($frame['description'] ?? 'No description.') ?></div>
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

<!-- Split Modal -->
<div id="splitModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeSplitModal()">
    <div class="su-modal-box" style="max-width:500px;">
        <div class="su-modal-header">
            <div class="su-modal-title"><i class="bi bi-scissors"></i> Split Storyboard</div>
            <button onclick="closeSplitModal()" class="su-modal-close">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <p style="font-size:0.85rem; color:var(--pl-text-dim); margin:0;">
                The original storyboard will keep all frames <strong>before</strong> the split point.<br>
                A new storyboard will be created containing all frames <strong>after</strong> the split point.
            </p>
            <input type="hidden" id="splitIndex">
            <div>
                <label style="display:block;font-family:'Space Mono',monospace;font-size:.7rem;color:var(--pl-text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">New Storyboard Name (Part 2)</label>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>

<!-- PhotoSwipe Lightbox Module -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
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
    document.addEventListener('DOMContentLoaded', () => {
        if (window.initLightbox) window.initLightbox();
    });
</script>

<script>
const originalName = <?= json_encode($sb['name']) ?>;
const SB_ID = <?= $sbId ?>;

// ── Export Storyboard ──────────────────────────────────────────────────────────
function exportStoryboard(e) {
    const btn = e.currentTarget;
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Exporting...';
    btn.disabled = true;

    const fd = new URLSearchParams();
    fd.append('action', 'export_storyboard');
    fd.append('storyboard_id', SB_ID);

    fetch('sbcut_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const dataStr = JSON.stringify(res.export, null, 2);
                const blob = new Blob([dataStr], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `storyboard_${SB_ID}_export.json`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                if (window.Toast) Toast.show('Storyboard exported.', 'success');
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

// ── Drag & Drop Items ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('storyboardList');
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
        e.dataTransfer.dropEffect = 'move';
        const wrap = e.target.closest('.seq-item-wrap');
        if (!wrap || wrap === dragSrc) return;
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
        e.preventDefault(); // Prevent scrolling while dragging
    }, { passive: false });

    document.addEventListener('pointermove', e => {
        if (!pointerDragSrc || !pointerClone) return;
        pointerClone.style.top = (e.clientY - pointerOffsetY) + 'px';
        container.querySelectorAll('.seq-item-wrap').forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));
        const target = document.elementFromPoint(e.clientX, e.clientY);
        const wrap = target ? target.closest('.seq-item-wrap') : null;
        if (wrap && wrap !== pointerDragSrc && wrap.parentNode === container) {
            const rect = wrap.getBoundingClientRect();
            wrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
        }
    });

    document.addEventListener('pointerup', e => {
        if (!pointerDragSrc) return;
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
    });

    function persistSortOrder() {
        const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
        const frameIds = wraps.map(w => w.dataset.id);

        const fd = new URLSearchParams();
        fd.append('action', 'reorder_storyboard');
        fd.append('storyboard_id', SB_ID);
        fd.append('order', frameIds.join(','));

        fetch('sbcut_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    // Update DOM numbering seamlessly
                    wraps.forEach((w, i) => {
                        const numLabel = w.querySelector('.sketch-id-num');
                        if (numLabel) numLabel.textContent = String(i + 1).padStart(2, '0');
                    });
                    if (window.Toast) Toast.show('Storyboard reordered.', 'success');
                } else {
                    if (window.Toast) Toast.show(res.message || 'Reorder failed', 'error');
                }
            });
    }
    
       
    
});
    
    
   function removeStoryboardItem(btn) {
        if (!confirm('Remove this frame from the storyboard?')) return;
        
        const wrap = btn.closest('.seq-item-wrap');
        if (!wrap) return;
        
        // Disable button to prevent double-clicks
        btn.style.pointerEvents = 'none';
        btn.innerHTML = '⋯';
        
        const frameRecordId = wrap.dataset.id; // DB ID of the storyboard_frames record
        
        const fd = new URLSearchParams();
        fd.append('action', 'remove_storyboard_item');
        fd.append('storyboard_id', SB_ID);
        fd.append('frame_record_id', frameRecordId);
        
        fetch('sbcut_api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    // Smooth fade out
                    wrap.style.transition = 'all 0.3s ease';
                    wrap.style.opacity = '0';
                    wrap.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        wrap.remove();
                        // Re-index remaining frames
                        const wraps = Array.from(document.querySelectorAll('.seq-item-wrap'));
                        wraps.forEach((w, i) => {
                            const numLabel = w.querySelector('.sketch-id-num');
                            if (numLabel) numLabel.textContent = String(i + 1).padStart(2, '0');
                        });
                        
                        if (wraps.length === 0) {
                            window.location.reload(); // Quick refresh to show empty state
                        }
                        if (window.Toast) Toast.show('Frame removed.', 'success');
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

function submitSplit(loadPart) {

    const splitIndex = document.getElementById('splitIndex').value;
    const newName = document.getElementById('splitNewName').value.trim();
    
    if (!newName) return Toast.show('Name is required.', 'warn');

    const fd = new URLSearchParams();
    fd.append('action', 'split_storyboard');
    fd.append('storyboard_id', SB_ID);
    fd.append('split_index', splitIndex);
    fd.append('new_name', newName);

    fetch('sbcut_api.php', { method: 'POST', body: fd })
        .then(r=>r.json()).then(res => {
            if (res.success) {
                if (loadPart === 'part2') {
                    window.location.href = '?id=' + res.new_storyboard_id;
                } else {
                    window.location.reload();
                }
            } else {
                Toast.show(res.message || 'Split failed', 'error');
            }
        });
}
</script>

<?php
echo $eruda ?? '';
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>