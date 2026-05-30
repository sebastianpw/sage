<?php
// public/webtoon_editor.php
// Drag and Drop Text Overlay Editor for Webtoon Cinematic Sequences
// Includes Gear Menu and PhotoSwipe Lightbox

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . '/entity_icons.php';

use App\UI\Modules\ModuleRegistry;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$seqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── UI Modules Setup ──────────────────────────────────────────────────────────
$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'sketches', 'frames'];
$gearMenu = $registry->create('gear_menu', [
    'position'          => 'top-right',
    'icon'              => '&#9881;',
    'icon_size'         => '1.3em',
    'show_for_entities' => $entities_with_menu,
]);
foreach ($entities_with_menu as $entity_name) {
    $gearMenu->addStandardActions($entity_name);
}
$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// Helper Functions
function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = '';
    foreach (['thumb', 'thumbnail', 'image', 'image_url', 'image_path', 'file_path', 'path', 'src', 'url', 'filename', 'file_name'] as $key) {
        if (!empty($row[$key]) && is_string($row[$key])) {
            $candidate = $row[$key];
            break;
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

// ── Sequence Picker (If no ID provided) ─────────────────────────────────────────
if (!$seqId) {
    $seqs = $pdo->query("SELECT id, name, created_at FROM narrative_sequences ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <div style="max-width:700px;margin:60px auto;padding:20px;">
        <h2 style="font-family:'Space Mono',monospace;color:var(--accent);">📚 Overlay Editor Selection</h2>
        <p style="color:var(--text-muted);">Select a sequence to edit overlays:</p>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
        <?php foreach ($seqs as $s): ?>
            <a href="?id=<?= $s['id'] ?>"
               style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--card);border:1px solid var(--border);border-radius:6px;text-decoration:none;color:var(--text);font-family:'Space Mono',monospace;font-size:.85rem;transition:border-color .2s;"
               onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                <span>#<?= $s['id'] ?> — <?= htmlspecialchars($s['name']) ?></span>
                <span style="color:var(--text-muted);"><?= date('Y-m-d', strtotime($s['created_at'])) ?></span>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Sequence - Webtoon Editor', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Fetch Data ────────────────────────────────────────────────────────────
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
$overlayTexts = [];

if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    
    // Load Sketches
    $stmtS = $pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }

    // Load Overlays
    try {
        $stmtO = $pdo->prepare("SELECT * FROM sketch_overlay_texts WHERE sketch_id IN ($inClause) ORDER BY display_order ASC, id ASC");
        $stmtO->execute($pureSketchIds);
        foreach ($stmtO->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $overlayTexts[(int)$row['sketch_id']][] = $row;
        }
    } catch (PDOException $e) {} // Ignore if table doesn't exist yet
}

// Map explicit frames (capture both thumb url AND frame_id)
$selectedFrameMap = [];
$activeFrameIds = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $selectedFrameMap[(int)$row['id']] = [
            'thumb'    => resolveFrameThumb($row, (int)$row['id']),
            'frame_id' => (int)$row['id']
        ];
    }
}

// Map fallback frames (capture both thumb url AND frame_id)
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
        $sketchId = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sketchId])) {
            $latestFrameBySketch[$sketchId] = [
                'thumb'    => resolveFrameThumb($row, (int)$row['id']),
                'frame_id' => (int)$row['id']
            ];
        }
    }
}

$pageTitle = "Overlay Editor: " . htmlspecialchars($seq['name']);
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<!-- PhotoSwipe CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
/* ── UI Design Tokens ── */
:root {
    --forge-bg:          #080b10;
    --forge-surface:     #0e1319;
    --forge-card:        #111820;
    --forge-border:      #1c2535;
    --forge-text:        #c8d4e8;
    --forge-text-dim:    #5a6a80;
    --forge-amber:       #f5a623;
    --forge-red:         #f05060;
    --mono: 'Space Mono', 'Courier New', monospace;
    --sans: 'Syne', system-ui, sans-serif;
    --forge-radius: 6px;
}

body {
    background: var(--forge-bg); color: var(--forge-text);
    font-family: var(--sans);
    margin: 0; padding: 0;
}

/* ── Top Nav Bar ── */
.editor-nav {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; background: rgba(0,0,0,0.6);
    border-bottom: 1px solid var(--forge-border);
    position: sticky; top: 0; z-index: 100;
    backdrop-filter: blur(6px);
}
.editor-nav-title {
    font-family: var(--mono); font-size: 0.8rem;
    color: var(--forge-text); flex: 1;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.editor-nav-link {
    font-family: var(--mono); font-size: 0.7rem;
    padding: 6px 12px; border: 1px solid var(--forge-border);
    border-radius: 4px; color: var(--forge-text-dim);
    text-decoration: none; transition: all 0.2s;
    background: var(--forge-surface);
}
.editor-nav-link:hover { color: var(--forge-amber); border-color: var(--forge-amber); }
.editor-nav-link.primary { color: #000; background: var(--forge-amber); border-color: var(--forge-amber); font-weight: bold; }

/* ── Workspace Area ── */
.workspace {
    max-width: 900px; margin: 0 auto;
    padding: 40px 15px 100px;
}

/* ── Sketch Block ── */
.sketch-block {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    padding: 16px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
}

.sketch-header {
    display: flex; gap: 15px; margin-bottom: 15px;
    border-bottom: 1px solid var(--forge-border);
    padding-bottom: 15px;
}
.sketch-thumb {
    position: relative; /* Crucial for gear menu */
    width: 120px; height: 120px; flex-shrink: 0;
    background: #000; border-radius: 4px; overflow: hidden;
    border: 1px solid var(--forge-border);
}
.sketch-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sketch-info { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.sketch-id { font-family: var(--mono); font-size: 0.65rem; color: var(--forge-amber); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 5px; }
.sketch-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 5px 0; color: #fff; }
.sketch-desc { font-size: 0.8rem; color: var(--forge-text-dim); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

/* ── Overlay Text Blocks ── */
.overlays-wrap {
    display: flex; flex-direction: column; gap: 12px;
}

.overlay-block {
    position: relative;
    padding-left: 28px;
    padding-right: 40px;
    transition: transform 0.2s, opacity 0.2s;
}

.overlay-text {
    width: 100%; box-sizing: border-box;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    color: var(--forge-text);
    font-family: var(--sans); font-size: 0.95rem; line-height: 1.5;
    padding: 10px 12px; border-radius: 4px;
    resize: none; overflow: hidden;
    transition: border-color 0.2s, background 0.2s;
}
.overlay-text:focus { outline: none; border-color: var(--forge-amber); background: rgba(255,255,255,0.03); }

/* ── Drag & Drop & Actions ── */
.drag-handle {
    position: absolute; left: 0; top: 50%; transform: translateY(-50%);
    width: 28px; height: 100%;
    display: flex; align-items: center; justify-content: center;
    color: var(--forge-text-dim); font-size: 1rem;
    cursor: grab; opacity: 0.3; transition: opacity 0.2s;
    touch-action: none; user-select: none;
}
.overlay-block:hover .drag-handle { opacity: 1; }
.drag-handle:active { cursor: grabbing; }

.action-btns {
    position: absolute; right: 0; top: 50%; transform: translateY(-50%);
    display: flex; flex-direction: column; gap: 6px;
    opacity: 0; transition: opacity 0.2s;
}
.overlay-block:hover .action-btns, .overlay-text:focus ~ .action-btns { opacity: 1; }
@media (hover: none) { .action-btns { opacity: 1; } }

.del-btn {
    width: 28px; height: 28px; border-radius: 4px; border: 1px solid transparent;
    background: transparent; color: var(--forge-red);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.85rem; transition: all 0.15s;
}
.del-btn:hover { border-color: var(--forge-red); background: rgba(240, 80, 96, 0.1); }

/* Drag States */
.overlay-block.drag-over-top { border-top: 2px solid var(--forge-amber); padding-top: 2px; }
.overlay-block.drag-over-bottom { border-bottom: 2px solid var(--forge-amber); padding-bottom: 2px; }
.overlay-block.dragging { opacity: 0.4; }

/* ── Add Button ── */
.add-line-btn {
    background: transparent; border: 1px dashed var(--forge-border);
    color: var(--forge-text-dim); padding: 10px 12px; border-radius: 4px;
    font-family: var(--mono); font-size: 0.75rem; text-transform: uppercase;
    cursor: pointer; display: block; margin: 10px auto 0;
    transition: all 0.2s; width: 100%; max-width: 200px;
}
.add-line-btn:hover { border-color: var(--forge-amber); color: var(--forge-amber); background: rgba(245,166,35,0.05); }

</style>

<!-- PhotoSwipe Module Setup -->
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
</script>

<div class="editor-nav">
    <a href="view_narrative_sequence_analysis.php?id=<?= $seqId ?>" class="editor-nav-link">◀ Analysis</a>
    <span class="editor-nav-title">Editing Overlays: <?= htmlspecialchars($seq['name']) ?></span>
    <a href="webtoon_cinematic.php?id=<?= $seqId ?>" class="editor-nav-link primary" target="_blank">▶ View Cinematic</a>
</div>

<div class="workspace" id="editor-workspace">
    <?php foreach ($itemIds as $idx => $item): 
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid <= 0 || !isset($sketchesData[$sid])) continue;
        
        $sketchRow = $sketchesData[$sid];
        $activeFrameId = $selectedFrameIds[$idx] ?? null;
        $thumb = '';

        // Resolve frame_id and thumb
        if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
            $thumb = $selectedFrameMap[$activeFrameId]['thumb'];
        } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
            $thumb = $latestFrameBySketch[$sid]['thumb'];
            $activeFrameId = $latestFrameBySketch[$sid]['frame_id'];
        }

        $lines = $overlayTexts[$sid] ?? [];

        // Setup gear menu attributes
        $gearAttr = '';
        if ($activeFrameId) {
            $gearAttr = 'data-gear-menu data-entity="frames" data-entity-id="'.$activeFrameId.'" data-frame-id="'.$activeFrameId.'" data-img-url="'.htmlspecialchars($thumb).'"';
        }
    ?>
    <div class="sketch-block" data-sketch-id="<?= $sid ?>">
        <div class="sketch-header">
            <!-- Gear Menu & PhotoSwipe Container -->
            <div class="sketch-thumb editor-pswp-gallery" <?= $gearAttr ?>>
                <?php if ($thumb): ?>
                    <a href="<?= htmlspecialchars($thumb) ?>" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="<?= htmlspecialchars($thumb) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                    </a>
                <?php else: ?>
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#333;">No Image</div>
                <?php endif; ?>
            </div>
            
            <div class="sketch-info">
                <div class="sketch-id">Frame <?= sprintf('%02d', $idx + 1) ?> • Sketch #<?= $sid ?></div>
                <h3 class="sketch-title"><?= htmlspecialchars($sketchRow['name']) ?></h3>
                <div class="sketch-desc"><?= htmlspecialchars($sketchRow['description'] ?? 'No original description.') ?></div>
            </div>
        </div>

        <div class="overlays-wrap">
            <div class="overlay-list">
                <?php foreach ($lines as $line): ?>
                    <div class="overlay-block" data-overlay-id="<?= $line['id'] ?>" draggable="true">
                        <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                        <textarea class="overlay-text" placeholder="Type narrative text..."><?= htmlspecialchars($line['text_content']) ?></textarea>
                        <div class="action-btns">
                            <button class="del-btn" title="Delete Text"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="add-line-btn">+ Add Text Block</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // ── Modules Initialization ───────────────────────────────────────────────
    function attachGearMenu() {
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            window.GearMenu.attach(document.body);
        } else { setTimeout(attachGearMenu, 200); }
    }
    attachGearMenu();

    if (window.initLightbox) window.initLightbox();


    // ── Editor Logic ─────────────────────────────────────────────────────────
    let saveTimer = null;

    // Helper: Build a new DOM element for a line
    function createOverlayElement(overlayId, textContent = '') {
        const div = document.createElement('div');
        div.className = 'overlay-block';
        div.draggable = true;
        div.dataset.overlayId = overlayId;

        div.innerHTML = `
            <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
            <textarea class="overlay-text" placeholder="Type narrative text..."></textarea>
            <div class="action-btns">
                <button class="del-btn" title="Delete Text"><i class="bi bi-trash"></i></button>
            </div>
        `;
        
        const txt = div.querySelector('textarea');
        txt.value = textContent;
        
        // Auto-resize listener
        txt.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
            debounceSave(overlayId, this.value);
        });

        // Delete listener
        div.querySelector('.del-btn').addEventListener('click', () => deleteOverlay(overlayId, div));

        return div;
    }

    // Auto-save logic
    function debounceSave(overlayId, text) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            const fd = new URLSearchParams();
            fd.append('action', 'update_overlay');
            fd.append('overlay_id', overlayId);
            fd.append('text', text);
            fetch('webtoon_editor_api.php', { method: 'POST', body: fd });
        }, 500);
    }

    // Add API logic
    function addOverlay(sketchId, listContainer) {
        const fd = new URLSearchParams();
        fd.append('action', 'add_overlay');
        fd.append('sketch_id', sketchId);

        fetch('webtoon_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const el = createOverlayElement(res.overlay_id, '');
                    listContainer.appendChild(el);
                    const txt = el.querySelector('textarea');
                    if (txt) {
                        txt.style.height = 'auto';
                        txt.style.height = txt.scrollHeight + 'px';
                        txt.focus();
                    }
                } else {
                    Toast.show("Failed to add text block.", "error");
                }
            });
    }

    // Delete API logic
    function deleteOverlay(overlayId, element) {
        if (!confirm("Delete this text block?")) return;
        const fd = new URLSearchParams();
        fd.append('action', 'delete_overlay');
        fd.append('overlay_id', overlayId);
        
        fetch('webtoon_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if(res.success) {
                    element.style.opacity = '0';
                    setTimeout(() => element.remove(), 200);
                } else {
                    Toast.show("Failed to delete.", "error");
                }
            });
    }

    // --- Drag & Drop Reorder Logic (Touch Supported) ---
    function initDragSort(container, sketchId) {
        let dragSrc = null;

        // Desktop Drag
        container.addEventListener('dragstart', e => {
            const block = e.target.closest('.overlay-block');
            if (!block) return;
            dragSrc = block;
            block.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        container.addEventListener('dragend', e => {
            const block = e.target.closest('.overlay-block');
            if (block) block.classList.remove('dragging');
            container.querySelectorAll('.overlay-block').forEach(b => {
                b.classList.remove('drag-over-top', 'drag-over-bottom');
            });
            dragSrc = null;
            persistSortOrder(container, sketchId);
        });

        container.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const block = e.target.closest('.overlay-block');
            if (!block || block === dragSrc) return;
            container.querySelectorAll('.overlay-block').forEach(b => {
                b.classList.remove('drag-over-top', 'drag-over-bottom');
            });
            const rect = block.getBoundingClientRect();
            const mid = rect.top + rect.height / 2;
            block.classList.add(e.clientY < mid ? 'drag-over-top' : 'drag-over-bottom');
        });

        container.addEventListener('drop', e => {
            e.preventDefault();
            if (!dragSrc) return;
            const block = e.target.closest('.overlay-block');
            if (!block || block === dragSrc) return;
            const rect = block.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                container.insertBefore(dragSrc, block);
            } else {
                container.insertBefore(dragSrc, block.nextSibling);
            }
        });

        // Touch Drag (Pointer Events)
        let pointerDragSrc = null;
        let pointerClone = null;
        let pointerOffsetY = 0;

        container.addEventListener('pointerdown', e => {
            const handle = e.target.closest('.drag-handle');
            if (!handle) return;
            const block = handle.closest('.overlay-block');
            if (!block) return;
            
            pointerDragSrc = block;
            const rect = block.getBoundingClientRect();
            pointerOffsetY = e.clientY - rect.top;

            pointerClone = block.cloneNode(true);
            pointerClone.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;opacity:0.8;pointer-events:none;z-index:9999;background:var(--forge-surface);border:1px solid var(--forge-amber);box-shadow:0 10px 30px rgba(0,0,0,0.8);`;
            document.body.appendChild(pointerClone);
            block.classList.add('dragging');
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('pointermove', e => {
            if (!pointerDragSrc || !pointerClone) return;
            pointerClone.style.top = (e.clientY - pointerOffsetY) + 'px';

            container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top', 'drag-over-bottom'));
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const block = target ? target.closest('.overlay-block') : null;
            
            if (block && block !== pointerDragSrc && block.parentNode === container) {
                const rect = block.getBoundingClientRect();
                block.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
            }
        });

        document.addEventListener('pointerup', e => {
            if (!pointerDragSrc) return;
            if (pointerClone) { pointerClone.remove(); pointerClone = null; }
            pointerDragSrc.classList.remove('dragging');
            container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top', 'drag-over-bottom'));

            const target = document.elementFromPoint(e.clientX, e.clientY);
            const block = target ? target.closest('.overlay-block') : null;
            if (block && block !== pointerDragSrc && block.parentNode === container) {
                const rect = block.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) {
                    container.insertBefore(pointerDragSrc, block);
                } else {
                    container.insertBefore(pointerDragSrc, block.nextSibling);
                }
            }

            persistSortOrder(container, sketchId);
            pointerDragSrc = null;
        });
    }

    function persistSortOrder(container, sketchId) {
        const ids = Array.from(container.querySelectorAll('.overlay-block'))
            .map(b => b.dataset.overlayId)
            .filter(Boolean);
        if (!ids.length) return;
        
        const fd = new URLSearchParams();
        fd.append('action', 'reorder_overlays');
        fd.append('sketch_id', sketchId);
        fd.append('order', ids.join(','));
        fetch('webtoon_editor_api.php', { method: 'POST', body: fd });
    }

    // Initialization bindings
    document.querySelectorAll('.sketch-block').forEach(block => {
        const sketchId = block.dataset.sketchId;
        const list = block.querySelector('.overlay-list');
        const addBtn = block.querySelector('.add-line-btn');

        // Bind existing overlays
        list.querySelectorAll('.overlay-block').forEach(el => {
            const overlayId = el.dataset.overlayId;
            const txt = el.querySelector('textarea');
            
            // Initial auto-resize
            setTimeout(() => { txt.style.height = 'auto'; txt.style.height = txt.scrollHeight + 'px'; }, 50);

            // Text change binding
            txt.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
                debounceSave(overlayId, this.value);
            });

            // Delete binding
            el.querySelector('.del-btn').addEventListener('click', () => deleteOverlay(overlayId, el));
        });

        // Add line binding
        addBtn.addEventListener('click', () => addOverlay(sketchId, list));

        // Setup Drag & Drop
        initDragSort(list, sketchId);
    });
});
</script>

<?php
echo $eruda ?? "";
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>