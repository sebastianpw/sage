<?php
// public/cinemagic_editor.php
// Drag and Drop Text Overlay Editor for Cinemagic / Cinematic Sequences
// Includes Gear Menu, PhotoSwipe Lightbox, and Cinemagic assignment panel

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . '/entity_icons.php';

use App\UI\Modules\ModuleRegistry;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$seqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editLang = $_GET['lang'] ?? 'en';

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

// Global System Languages Fetch
$allLanguages = [];
try {
    $allLanguages = $pdo->query("SELECT * FROM system_languages ORDER BY is_main DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!$seqId) {
    $seqs = $pdo->query("SELECT id, name, created_at FROM narrative_sequences ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <div style="max-width:700px;margin:60px auto;padding:20px;">
        <h2 style="font-family:'Space Mono',monospace;color:var(--accent);">📚 Cinemagic Editor — Select Sequence</h2>
        <p style="color:var(--text-muted);">Select a sequence to edit overlays:</p>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
        <?php foreach ($seqs as $s): ?>
            <div style="display:flex;align-items:center;gap:0;background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;transition:border-color .2s;"
                 onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                <a href="?id=<?= $s['id'] ?>"
                   style="display:flex;justify-content:space-between;flex:1;padding:10px 14px;text-decoration:none;color:var(--text);font-family:'Space Mono',monospace;font-size:.85rem;">
                    <span>#<?= $s['id'] ?> — <?= htmlspecialchars($s['name']) ?></span>
                    <span style="color:var(--text-muted);"><?= date('Y-m-d', strtotime($s['created_at'])) ?></span>
                </a>
                <button
                    class="seq-overlay-btn"
                    data-seq-id="<?= $s['id'] ?>"
                    data-seq-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                    title="Edit sequence overlay texts"
                    style="background:transparent;border:none;border-left:1px solid var(--border);padding:0 14px;height:100%;cursor:pointer;color:var(--text-muted);font-size:1rem;display:flex;align-items:center;align-self:stretch;transition:color .2s,background .2s;"
                    onmouseover="this.style.color='var(--accent)';this.style.background='rgba(245,166,35,0.07)'"
                    onmouseout="this.style.color='var(--text-muted)';this.style.background='transparent'">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Sequence Overlay Modal ───────────────────────────────────────── -->
    <div id="seqOverlayBackdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:300000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);">
        <div style="width:100%;max-width:460px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:20px;box-shadow:0 10px 40px rgba(0,0,0,0.5);margin:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div style="font-family:'Space Mono',monospace;font-size:0.85rem;color:var(--accent);text-transform:uppercase;letter-spacing:1px;">
                    Sequence Overlay Texts
                </div>
                <button onclick="closeSeqOverlayModal()" style="background:transparent;border:none;color:var(--text-muted);cursor:pointer;font-size:1.2rem;line-height:1;">✕</button>
            </div>

            <div style="font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--text-muted);margin-bottom:12px;" id="seqOverlaySeqName"></div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                <label style="font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--text-muted);white-space:nowrap;">Language:</label>
                <select id="seqOverlayLang" style="background:var(--surface,var(--card));color:var(--text);border:1px solid var(--border);border-radius:4px;padding:5px 8px;font-family:'Space Mono',monospace;font-size:0.75rem;flex:1;" onchange="loadSeqOverlay()">
                    <?php foreach ($allLanguages as $l): ?>
                        <option value="<?= $l['code'] ?>"><?= htmlspecialchars($l['name']) ?> (<?= strtoupper($l['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Name Overlay</label>
                    <input type="text" id="seqOverlayName" placeholder="Override sequence name for this language…"
                           style="width:100%;box-sizing:border-box;background:var(--surface,var(--card));color:var(--text);border:1px solid var(--border);border-radius:4px;padding:8px 10px;font-family:'Space Mono',monospace;font-size:0.8rem;">
                </div>
                <div>
                    <label style="display:block;font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Description Overlay</label>
                    <textarea id="seqOverlayDesc" rows="4" placeholder="Override sequence description for this language…"
                              style="width:100%;box-sizing:border-box;background:var(--surface,var(--card));color:var(--text);border:1px solid var(--border);border-radius:4px;padding:8px 10px;font-family:'Space Mono',monospace;font-size:0.8rem;resize:vertical;"></textarea>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <button onclick="closeSeqOverlayModal()" style="padding:7px 16px;border-radius:4px;border:1px solid var(--border);background:transparent;color:var(--text-muted);font-family:'Space Mono',monospace;font-size:0.75rem;cursor:pointer;">Cancel</button>
                <button onclick="saveSeqOverlay()" style="padding:7px 16px;border-radius:4px;border:1px solid var(--accent);background:var(--accent);color:#000;font-family:'Space Mono',monospace;font-size:0.75rem;font-weight:bold;cursor:pointer;">Save</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/toast.js"></script>
    <script>
    var _seqOverlayActiveId = null;

    document.querySelectorAll('.seq-overlay-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            _seqOverlayActiveId = parseInt(this.dataset.seqId, 10);
            document.getElementById('seqOverlaySeqName').textContent = '#' + _seqOverlayActiveId + ' — ' + this.dataset.seqName;
            document.getElementById('seqOverlayLang').value = 'en';
            document.getElementById('seqOverlayName').value = '';
            document.getElementById('seqOverlayDesc').value = '';
            document.getElementById('seqOverlayBackdrop').style.display = 'flex';
            loadSeqOverlay();
        });
    });

    document.getElementById('seqOverlayBackdrop').addEventListener('mousedown', function(e) {
        if (e.target === this) closeSeqOverlayModal();
    });

    function closeSeqOverlayModal() {
        document.getElementById('seqOverlayBackdrop').style.display = 'none';
        _seqOverlayActiveId = null;
    }

    function loadSeqOverlay() {
        if (!_seqOverlayActiveId) return;
        var lang = document.getElementById('seqOverlayLang').value;
        fetch('cinemagic_editor_api.php?action=get_sequence_overlay&sequence_id=' + _seqOverlayActiveId + '&lang=' + encodeURIComponent(lang))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    document.getElementById('seqOverlayName').value = res.name_overlay || '';
                    document.getElementById('seqOverlayDesc').value = res.description_overlay || '';
                }
            });
    }

    function saveSeqOverlay() {
        if (!_seqOverlayActiveId) return;
        var lang = document.getElementById('seqOverlayLang').value;
        var fd = new URLSearchParams();
        fd.append('action', 'save_sequence_overlay');
        fd.append('sequence_id', _seqOverlayActiveId);
        fd.append('lang', lang);
        fd.append('name_overlay', document.getElementById('seqOverlayName').value);
        fd.append('description_overlay', document.getElementById('seqOverlayDesc').value);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    Toast.show('Overlay saved.', 'success');
                    closeSeqOverlayModal();
                } else {
                    Toast.show(res.message || 'Save failed.', 'error');
                }
            });
    }
    </script>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Select Sequence - Cinemagic Editor', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

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
$sketchesData  = [];
$overlayTexts  = [];
$overlayTextsLang = [];

if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));

    $stmtS = $pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }

    try {
        $stmtO = $pdo->prepare("SELECT id, sketch_id, text_content, language_code, display_order FROM sketch_overlay_texts WHERE sketch_id IN ($inClause) AND language_code IN ('en', ?) ORDER BY display_order ASC, id ASC");
        $stmtO->execute([...$pureSketchIds, $editLang]);
        foreach ($stmtO->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['language_code'] === 'en') {
                $overlayTexts[(int)$row['sketch_id']][] = $row;
            } else {
                $overlayTextsLang[(int)$row['sketch_id']][$row['display_order']] = $row;
            }
        }
    } catch (PDOException $e) {}
}

$selectedFrameMap = [];
$activeFrameIds   = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $selectedFrameMap[(int)$row['id']] = [
            'thumb'    => resolveFrameThumb($row, (int)$row['id']),
            'frame_id' => (int)$row['id'],
        ];
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
        $sketchId = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sketchId])) {
            $latestFrameBySketch[$sketchId] = [
                'thumb'    => resolveFrameThumb($row, (int)$row['id']),
                'frame_id' => (int)$row['id'],
            ];
        }
    }
}

$currentCinemagics = [];
try {
    $stmtCM = $pdo->prepare(
        "SELECT c.id, c.name, cs.sort_order, cs.chapter_label
         FROM cinemagics c
         JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = c.id
         WHERE cs.sequence_id = ?
         ORDER BY c.name ASC"
    );
    $stmtCM->execute([$seqId]);
    $currentCinemagics = $stmtCM->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {} 

$allCinemagics = [];
try {
    $allCinemagics = $pdo->query("SELECT id, name FROM cinemagics ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$pageTitle = "Cinemagic Editor: " . htmlspecialchars($seq['name']);
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
:root, [data-theme="dark"] {
    --cm-bg:          #080b10;
    --cm-surface:     #0e1319;
    --cm-card:        #111820;
    --cm-border:      #1c2535;
    --cm-text:        #c8d4e8;
    --cm-text-dim:    #5a6a80;
    --cm-amber:       #f5a623;
    --cm-red:         #f05060;
    --cm-green:       #4caf80;
}
[data-theme="light"] {
    --cm-bg:          #f4f6fa;
    --cm-surface:     #ffffff;
    --cm-card:        #ffffff;
    --cm-border:      #d0d8e8;
    --cm-text:        #1a2233;
    --cm-text-dim:    #7a8aaa;
    --cm-amber:       #c8880a;
    --cm-red:         #d03040;
    --cm-green:       #2e8a58;
}

--mono: 'Space Mono', 'Courier New', monospace;
--sans: 'Syne', system-ui, sans-serif;
--cm-radius: 6px;

body { background: var(--cm-bg); color: var(--cm-text); font-family: var(--sans); margin: 0; padding: 0; transition: background 0.2s, color 0.2s; }

.editor-nav { display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: rgba(0,0,0,0.6); border-bottom: 1px solid var(--cm-border); position: sticky; top: 0; z-index: 100; backdrop-filter: blur(6px); }
[data-theme="light"] .editor-nav { background: rgba(244,246,250,0.92); }
.editor-nav-title { font-family: var(--mono); font-size: 0.8rem; color: var(--cm-text); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.editor-nav-link { font-family: var(--mono); font-size: 0.7rem; padding: 6px 12px; border: 1px solid var(--cm-border); border-radius: 4px; color: var(--cm-text-dim); text-decoration: none; transition: all 0.2s; background: var(--cm-surface); cursor: pointer; }
.editor-nav-link:hover { color: var(--cm-amber); border-color: var(--cm-amber); }
.editor-nav-link.primary { color: #000; background: var(--cm-amber); border-color: var(--cm-amber); font-weight: bold; }

.workspace { max-width: 900px; margin: 0 auto; padding: 40px 15px 100px; }
.lang-not-en .drag-handle, .lang-not-en .add-line-btn, .lang-not-en .del-btn { display: none !important; }
.lang-not-en .overlay-block { padding-left: 12px; padding-right: 12px; }

.sketch-block { background: var(--cm-card); border: 1px solid var(--cm-border); border-radius: var(--cm-radius); padding: 16px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
[data-theme="light"] .sketch-block { box-shadow: 0 2px 10px rgba(0,0,0,0.08); }

.sketch-header { display: flex; gap: 15px; margin-bottom: 15px; border-bottom: 1px solid var(--cm-border); padding-bottom: 15px; }
.sketch-thumb { position: relative; width: 120px; height: 120px; flex-shrink: 0; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--cm-border); }
[data-theme="light"] .sketch-thumb { background: #eef0f4; }
.sketch-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sketch-info { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.sketch-id { font-family: var(--mono); font-size: 0.65rem; color: var(--cm-amber); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 5px; }
.sketch-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 5px 0; color: var(--cm-text); }
.sketch-desc { font-size: 0.8rem; color: var(--cm-text-dim); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.overlays-wrap { display: flex; flex-direction: column; gap: 12px; }
.overlay-block { position: relative; padding-left: 28px; padding-right: 40px; transition: transform 0.2s, opacity 0.2s; }

.overlay-text { width: 100%; box-sizing: border-box; background: var(--cm-surface); border: 1px solid var(--cm-border); color: var(--cm-text); font-family: var(--sans); font-size: 0.95rem; line-height: 1.5; padding: 10px 12px; border-radius: 4px; resize: none; overflow: hidden; transition: border-color 0.2s, background 0.2s; }
.overlay-text:focus { outline: none; border-color: var(--cm-amber); background: var(--cm-card); }

.drag-handle { position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 28px; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--cm-text-dim); font-size: 1rem; cursor: grab; opacity: 0.3; transition: opacity 0.2s; touch-action: none; user-select: none; }
.overlay-block:hover .drag-handle { opacity: 1; }
.drag-handle:active { cursor: grabbing; }

.action-btns { position: absolute; right: 0; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 6px; opacity: 0; transition: opacity 0.2s; }
.overlay-block:hover .action-btns, .overlay-text:focus ~ .action-btns { opacity: 1; }
@media (hover: none) { .action-btns { opacity: 1; } }

.del-btn { width: 28px; height: 28px; border-radius: 4px; border: 1px solid transparent; background: transparent; color: var(--cm-red); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.85rem; transition: all 0.15s; }
.del-btn:hover { border-color: var(--cm-red); background: rgba(240,80,96,0.1); }

.overlay-block.drag-over-top    { border-top: 2px solid var(--cm-amber); padding-top: 2px; }
.overlay-block.drag-over-bottom { border-bottom: 2px solid var(--cm-amber); padding-bottom: 2px; }
.overlay-block.dragging         { opacity: 0.4; }

.add-line-btn { background: transparent; border: 1px dashed var(--cm-border); color: var(--cm-text-dim); padding: 10px 12px; border-radius: 4px; font-family: var(--mono); font-size: 0.75rem; text-transform: uppercase; cursor: pointer; display: block; margin: 10px auto 0; transition: all 0.2s; width: 100%; max-width: 200px; }
.add-line-btn:hover { border-color: var(--cm-amber); color: var(--cm-amber); background: rgba(245,166,35,0.05); }

/* Cinemagic Panel */
.cinemagic-panel { max-width: 900px; margin: 0 auto 0; padding: 0 15px 30px; }
.cinemagic-section { background: var(--cm-card); border: 1px solid var(--cm-border); border-radius: var(--cm-radius); padding: 16px; margin-bottom: 20px; }
.cinemagic-section-title { font-family: var(--mono); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: var(--cm-amber); margin: 0; display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
.cinemagic-section-title .cm-chevron { margin-left: auto; font-size: 0.6rem; transition: transform 0.25s cubic-bezier(0.4,0,0.2,1); opacity: 0.6; }
.cinemagic-section.collapsed .cm-chevron { transform: rotate(-90deg); }
.cm-collapsible { overflow: hidden; max-height: 600px; transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.25s ease; opacity: 1; margin-top: 14px; }
.cinemagic-section.collapsed .cm-collapsible { max-height: 0; opacity: 0; }
.cinemagic-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border: 1px solid var(--cm-border); border-radius: 4px; background: var(--cm-surface); font-family: var(--mono); font-size: 0.75rem; color: var(--cm-text); margin: 0 6px 6px 0; }
.cinemagic-badge .cm-remove { background: none; border: none; color: var(--cm-red); cursor: pointer; padding: 0; font-size: 0.85rem; line-height: 1; opacity: 0.6; transition: opacity 0.15s; }
.cinemagic-badge .cm-remove:hover { opacity: 1; }
.cinemagic-badge .cm-label { font-size: 0.65rem; color: var(--cm-text-dim); border-left: 1px solid var(--cm-border); padding-left: 8px; margin-left: 2px; }

.cm-assign-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin-top: 12px; }
.cm-assign-row select, .cm-assign-row input[type="text"] { background: var(--cm-surface); color: var(--cm-text); border: 1px solid var(--cm-border); border-radius: 4px; padding: 7px 10px; font-family: var(--mono); font-size: 0.75rem; flex: 1; min-width: 120px; }
.cm-assign-row select:focus, .cm-assign-row input[type="text"]:focus { outline: none; border-color: var(--cm-amber); }
.cm-btn { padding: 7px 14px; border-radius: 4px; border: 1px solid; font-family: var(--mono); font-size: 0.75rem; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.cm-btn-primary { border-color: var(--cm-amber); background: var(--cm-amber); color: #000; font-weight: bold; }
.cm-btn-primary:hover { filter: brightness(1.1); }
.cm-btn-secondary { border-color: var(--cm-border); background: var(--cm-surface); color: var(--cm-text-dim); }
.cm-btn-secondary:hover { border-color: var(--cm-amber); color: var(--cm-amber); }
.cm-new-form { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; align-items: flex-end; }
.cm-new-form input[type="text"], .cm-new-form textarea { background: var(--cm-surface); color: var(--cm-text); border: 1px solid var(--cm-border); border-radius: 4px; padding: 7px 10px; font-family: var(--mono); font-size: 0.75rem; flex: 1; min-width: 120px; }
.cm-new-form input[type="text"]:focus, .cm-new-form textarea:focus { outline: none; border-color: var(--cm-amber); }
.cm-empty { font-size: 0.8rem; color: var(--cm-text-dim); font-style: italic; }
.cm-divider { border: none; border-top: 1px solid var(--cm-border); margin: 14px 0; }

/* Language Modal */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 300000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-backdrop.active { display: flex; }
.modal-box { width: 100%; max-width: 400px; background: var(--cm-surface); border: 1px solid var(--cm-border); border-radius: 8px; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.modal-title { font-size: 1rem; font-weight: bold; font-family: var(--mono); color: var(--cm-amber); text-transform: uppercase; letter-spacing: 1px; }
.modal-close { background: transparent; border: none; color: var(--cm-text-dim); cursor: pointer; font-size: 1.2rem; }
.lang-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--cm-card); border: 1px solid var(--cm-border); border-radius: 4px; }
</style>

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
    <span class="editor-nav-title">Cinemagic Editor: <?= htmlspecialchars($seq['name']) ?></span>
    
    <select id="editor-lang-select" onchange="window.location.href='?id=<?= $seqId ?>&lang='+this.value" class="editor-nav-link" style="appearance:auto; background:var(--cm-card);">
        <?php foreach ($allLanguages as $l): ?>
            <option value="<?= $l['code'] ?>" <?= $l['code'] === $editLang ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?> (<?= strtoupper($l['code']) ?>)</option>
        <?php endforeach; ?>
    </select>
    <button class="editor-nav-link" onclick="openLangModal()" title="System Languages">
        <i class="bi bi-globe"></i>
    </button>
    <a href="cinemagic.php?id=<?= $seqId ?>&lang=<?= $editLang ?>" class="editor-nav-link primary" target="_blank">▶ View Cinematic</a>
</div>

<!-- ── Cinemagic Assignment Panel ───────────────────────────────────────── -->
<div class="cinemagic-panel">
    <div class="cinemagic-section">
        <h3 class="cinemagic-section-title" id="cm-section-title">&#127916; Cinemagic Collections <span class="cm-chevron">&#9660;</span></h3>

        <div class="cm-collapsible" id="cm-collapsible">

        <!-- Current memberships -->
        <div id="cm-badges-wrap">
            <?php if (empty($currentCinemagics)): ?>
                <span class="cm-empty" id="cm-empty-msg">Not assigned to any Cinemagic yet.</span>
            <?php else: ?>
                <?php foreach ($currentCinemagics as $cm): ?>
                    <span class="cinemagic-badge" data-cinemagic-id="<?= $cm['id'] ?>">
                        <span>#<?= $cm['id'] ?> <?= htmlspecialchars($cm['name']) ?></span>
                        <?php if (!empty($cm['chapter_label'])): ?>
                            <span class="cm-label"><?= htmlspecialchars($cm['chapter_label']) ?></span>
                        <?php endif; ?>
                        <button class="cm-remove" title="Remove from this Cinemagic" data-cinemagic-id="<?= $cm['id'] ?>">&#10005;</button>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <hr class="cm-divider">

        <!-- Assign to existing Cinemagic -->
        <div class="cm-assign-row" id="cm-assign-row"<?= empty($allCinemagics) ? ' style="display:none"' : '' ?>>
            <select id="cm-select">
                <option value="">— Select Cinemagic —</option>
                <?php foreach ($allCinemagics as $cm): ?>
                    <option value="<?= $cm['id'] ?>"><?= htmlspecialchars($cm['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="cm-label-input" placeholder="Chapter label (optional)" style="max-width:180px;">
            <button class="cm-btn cm-btn-primary" id="cm-assign-btn">+ Assign</button>
        </div>

        <hr class="cm-divider">

        <!-- Create new Cinemagic -->
        <div style="font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--cm-text-dim);margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Create New Cinemagic</div>
        <div class="cm-new-form">
            <input type="text" id="cm-new-name" placeholder="Cinemagic name…" style="flex:2;">
            <input type="text" id="cm-new-desc" placeholder="Description (optional)">
            <button class="cm-btn cm-btn-secondary" id="cm-create-btn">Create &amp; Assign</button>
        </div>

        </div><!-- /.cm-collapsible -->
    </div>
</div>

<!-- ── Overlay Editor Workspace ────────────────────────────────────────── -->
<div class="workspace <?= $editLang !== 'en' ? 'lang-not-en' : '' ?>" id="editor-workspace">
    <?php foreach ($itemIds as $idx => $item):
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid <= 0 || !isset($sketchesData[$sid])) continue;

        $sketchRow    = $sketchesData[$sid];
        $activeFrameId = $selectedFrameIds[$idx] ?? null;
        $thumb = '';

        if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
            $thumb = $selectedFrameMap[$activeFrameId]['thumb'];
        } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
            $thumb         = $latestFrameBySketch[$sid]['thumb'];
            $activeFrameId = $latestFrameBySketch[$sid]['frame_id'];
        }

        $lines    = $overlayTexts[$sid] ?? [];
        $gearAttr = '';
        if ($activeFrameId) {
            $gearAttr = 'data-gear-menu data-entity="frames" data-entity-id="'.$activeFrameId.'" data-frame-id="'.$activeFrameId.'" data-img-url="'.htmlspecialchars($thumb).'"';
        }
    ?>
    <div class="sketch-block" data-sketch-id="<?= $sid ?>">
        <div class="sketch-header">
            <div class="sketch-thumb editor-pswp-gallery" <?= $gearAttr ?>>
                <?php if ($thumb): ?>
                    <a href="<?= htmlspecialchars($thumb) ?>" class="editor-pswp-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="<?= htmlspecialchars($thumb) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                    </a>
                <?php else: ?>
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--cm-text-dim);">No Image</div>
                <?php endif; ?>
            </div>

            <div class="sketch-info">
                <div class="sketch-id">Frame <?= sprintf('%02d', $idx + 1) ?> • Sketch #<?= $sid ?></div>
                <h3 class="sketch-title"><?= htmlspecialchars($sketchRow['name']) ?></h3>
                <div class="sketch-desc"><?= htmlspecialchars($sketchRow['description'] ?? 'No description.') ?></div>
            </div>
        </div>

        <div class="overlays-wrap">
            <div class="overlay-list">
                <?php foreach ($lines as $enLine): 
                    $dispOrder = $enLine['display_order'];
                    $textVal   = $enLine['text_content'];
                    $overlayId = $enLine['id'];
                    
                    if ($editLang !== 'en') {
                        $langRow = $overlayTextsLang[$sid][$dispOrder] ?? null;
                        $textVal = $langRow ? $langRow['text_content'] : '';
                    }
                ?>
                    <div class="overlay-block" data-overlay-id="<?= $overlayId ?>" data-display-order="<?= $dispOrder ?>">
                        <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
                        <textarea class="overlay-text" placeholder="<?= $editLang === 'en' ? 'Type narrative text...' : htmlspecialchars($enLine['text_content']) ?>"><?= htmlspecialchars($textVal) ?></textarea>
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

<!-- ── Language Manager Modal ── -->
<div class="modal-backdrop" id="langModalBackdrop" onmousedown="if(event.target===this) closeLangModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">System Languages</div>
            <button class="modal-close" onclick="closeLangModal()">✕</button>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <input type="text" id="lang-code" placeholder="Code" maxlength="2" style="width:60px;text-transform:lowercase;background:var(--cm-card);color:var(--cm-text);border:1px solid var(--cm-border);border-radius:4px;padding:6px;font-family:var(--mono);">
            <input type="text" id="lang-name" placeholder="Language Name" style="flex:1;background:var(--cm-card);color:var(--cm-text);border:1px solid var(--cm-border);border-radius:4px;padding:6px;font-family:var(--sans);">
            <button class="cm-btn cm-btn-primary" onclick="saveLanguage()">Save</button>
        </div>
        <div id="lang-list" style="display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto;"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>

<script>
document.addEventListener('DOMContentLoaded', () => {

    function attachGearMenu() {
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            window.GearMenu.attach(document.body);
        } else { setTimeout(attachGearMenu, 200); }
    }
    attachGearMenu();
    if (window.initLightbox) window.initLightbox();

    const SEQ_ID = <?= $seqId ?>;
    const EDIT_LANG = '<?= $editLang ?>';

    const CM_STORAGE_KEY = 'sage_cm_panel_collapsed';
    const cmSection = document.querySelector('.cinemagic-section');
    const cmTitle   = document.getElementById('cm-section-title');

    function setCmCollapsed(collapsed) {
        cmSection.classList.toggle('collapsed', collapsed);
        try { localStorage.setItem(CM_STORAGE_KEY, collapsed ? '1' : '0'); } catch(e) {}
    }

    try {
        if (localStorage.getItem(CM_STORAGE_KEY) === '1') cmSection.classList.add('collapsed');
    } catch(e) {}

    cmTitle.addEventListener('click', () => { setCmCollapsed(!cmSection.classList.contains('collapsed')); });

    // ── Cinemagic Logic ────────────────────────────────────────────────
    function refreshEmptyMsg() {
        const wrap  = document.getElementById('cm-badges-wrap');
        let emptyEl = document.getElementById('cm-empty-msg');
        const hasBadges = wrap.querySelectorAll('.cinemagic-badge').length > 0;
        if (hasBadges && emptyEl) { emptyEl.remove(); }
        if (!hasBadges && !emptyEl) {
            emptyEl = document.createElement('span');
            emptyEl.className = 'cm-empty';
            emptyEl.id = 'cm-empty-msg';
            emptyEl.textContent = 'Not assigned to any Cinemagic yet.';
            wrap.appendChild(emptyEl);
        }
    }

    function addBadge(id, name, label) {
        const wrap = document.getElementById('cm-badges-wrap');
        if (wrap.querySelector(`.cinemagic-badge[data-cinemagic-id="${id}"]`)) return;
        const span = document.createElement('span');
        span.className = 'cinemagic-badge';
        span.dataset.cinemagicId = id;
        span.innerHTML = `<span>#${id} ${escHtml(name)}</span>${label ? `<span class="cm-label">${escHtml(label)}</span>` : ''}<button class="cm-remove" data-cinemagic-id="${id}" title="Remove from this Cinemagic">&#10005;</button>`;
        wrap.appendChild(span);
        bindRemove(span.querySelector('.cm-remove'));
        refreshEmptyMsg();
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function bindRemove(btn) {
        btn.addEventListener('click', () => {
            const cmId = parseInt(btn.dataset.cinemagicId, 10);
            const fd = new URLSearchParams();
            fd.append('action', 'remove_from_cinemagic');
            fd.append('cinemagic_id', cmId);
            fd.append('sequence_id', SEQ_ID);
            fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(res => {
                    if (res.success) {
                        const badge = document.querySelector(`.cinemagic-badge[data-cinemagic-id="${cmId}"]`);
                        if (badge) badge.remove();
                        refreshEmptyMsg();
                        Toast.show('Removed from Cinemagic.', 'info');
                    } else { Toast.show(res.message || 'Remove failed.', 'error'); }
                });
        });
    }

    document.querySelectorAll('.cm-remove').forEach(bindRemove);

    document.getElementById('cm-assign-btn').addEventListener('click', () => {
        const cmId  = parseInt(document.getElementById('cm-select').value, 10);
        const label = document.getElementById('cm-label-input').value.trim();
        if (!cmId) { Toast.show('Please select a Cinemagic.', 'warn'); return; }
        const name = document.getElementById('cm-select').selectedOptions[0]?.text || '';

        const fd = new URLSearchParams();
        fd.append('action', 'assign_to_cinemagic');
        fd.append('cinemagic_id', cmId);
        fd.append('sequence_id', SEQ_ID);
        fd.append('chapter_label', label);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    addBadge(cmId, name, label);
                    document.getElementById('cm-label-input').value = '';
                    Toast.show('Assigned to Cinemagic!', 'success');
                } else { Toast.show(res.message || 'Assignment failed.', 'error'); }
            });
    });

    document.getElementById('cm-create-btn').addEventListener('click', () => {
        const name = document.getElementById('cm-new-name').value.trim();
        const desc = document.getElementById('cm-new-desc').value.trim();
        if (!name) { Toast.show('Enter a name for the new Cinemagic.', 'warn'); return; }

        const fd = new URLSearchParams();
        fd.append('action', 'create_cinemagic');
        fd.append('name', name);
        fd.append('description', desc);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (!res.success) { Toast.show(res.message || 'Create failed.', 'error'); return; }
                const newId = res.cinemagic_id;

                const fd2 = new URLSearchParams();
                fd2.append('action', 'assign_to_cinemagic');
                fd2.append('cinemagic_id', newId);
                fd2.append('sequence_id', SEQ_ID);
                return fetch('cinemagic_editor_api.php', { method: 'POST', body: fd2 })
                    .then(r2 => r2.json()).then(res2 => {
                        if (res2.success) {
                            addBadge(newId, name, '');
                            const sel = document.getElementById('cm-select');
                            if (sel) {
                                const opt = document.createElement('option');
                                opt.value = newId; opt.text = name;
                                sel.appendChild(opt);
                            }
                            const assignRow = document.getElementById('cm-assign-row');
                            if (assignRow) assignRow.style.display = '';
                            document.getElementById('cm-new-name').value = '';
                            document.getElementById('cm-new-desc').value = '';
                            Toast.show('Cinemagic created and assigned!', 'success');
                        }
                    });
            });
    });

    // ── Overlay Editor Logic ─────────────────────────────────────────────────
    let saveTimer = null;

    function createOverlayElement(overlayId, displayOrder, textContent = '') {
        const div = document.createElement('div');
        div.className = 'overlay-block';
        div.draggable = true;
        div.dataset.overlayId = overlayId;
        div.dataset.displayOrder = displayOrder;
        div.innerHTML = `
            <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>
            <textarea class="overlay-text" placeholder="Type narrative text..."></textarea>
            <div class="action-btns">
                <button class="del-btn" title="Delete Text"><i class="bi bi-trash"></i></button>
            </div>`;
        const txt = div.querySelector('textarea');
        txt.value = textContent;
        txt.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
            const sketchId = div.closest('.sketch-block').dataset.sketchId;
            debounceSave(overlayId, sketchId, displayOrder, this.value);
        });
        div.querySelector('.del-btn').addEventListener('click', () => deleteOverlay(overlayId, div));
        return div;
    }

    function debounceSave(overlayId, sketchId, displayOrder, text) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            const fd = new URLSearchParams();
            fd.append('action', 'update_overlay');
            fd.append('overlay_id', overlayId);
            fd.append('sketch_id', sketchId);
            fd.append('display_order', displayOrder);
            fd.append('lang', EDIT_LANG);
            fd.append('text', text);
            fetch('cinemagic_editor_api.php', { method: 'POST', body: fd });
        }, 500);
    }

    function addOverlay(sketchId, listContainer) {
        if (EDIT_LANG !== 'en') { Toast.show('Add blocks in English first.', 'warn'); return; }
        const fd = new URLSearchParams();
        fd.append('action', 'add_overlay');
        fd.append('sketch_id', sketchId);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const el = createOverlayElement(res.overlay_id, res.display_order, '');
                    listContainer.appendChild(el);
                    const txt = el.querySelector('textarea');
                    if (txt) {
                        txt.style.height = 'auto';
                        txt.style.height = txt.scrollHeight + 'px';
                        txt.focus();
                    }
                } else { Toast.show('Failed to add text block.', 'error'); }
            });
    }

    function deleteOverlay(overlayId, element) {
        if (EDIT_LANG !== 'en') { Toast.show('Delete blocks in English first.', 'warn'); return; }
        if (!confirm('Delete this text block?')) return;
        const fd = new URLSearchParams();
        fd.append('action', 'delete_overlay');
        fd.append('overlay_id', overlayId);
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    element.style.opacity = '0';
                    setTimeout(() => element.remove(), 200);
                } else { Toast.show('Failed to delete.', 'error'); }
            });
    }

    // ── Drag & Drop ──────────────────────────────────────────────────────────
    function initDragSort(container, sketchId) {
        if (EDIT_LANG !== 'en') return;
        let dragSrc = null;

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
            block.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
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

        let pointerDragSrc = null, pointerClone = null, pointerOffsetY = 0;

        container.addEventListener('pointerdown', e => {
            const handle = e.target.closest('.drag-handle');
            if (!handle) return;
            const block = handle.closest('.overlay-block');
            if (!block) return;
            pointerDragSrc = block;
            const rect = block.getBoundingClientRect();
            pointerOffsetY = e.clientY - rect.top;
            pointerClone = block.cloneNode(true);
            pointerClone.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.top}px;width:${rect.width}px;opacity:0.8;pointer-events:none;z-index:9999;background:var(--cm-surface);border:1px solid var(--cm-amber);box-shadow:0 10px 30px rgba(0,0,0,0.8);`;
            document.body.appendChild(pointerClone);
            block.classList.add('dragging');
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('pointermove', e => {
            if (!pointerDragSrc || !pointerClone) return;
            pointerClone.style.top = (e.clientY - pointerOffsetY) + 'px';
            container.querySelectorAll('.overlay-block').forEach(b => b.classList.remove('drag-over-top', 'drag-over-bottom'));
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const block  = target ? target.closest('.overlay-block') : null;
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
            const block  = target ? target.closest('.overlay-block') : null;
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
            .map(b => b.dataset.overlayId).filter(Boolean);
        if (!ids.length) return;
        const fd = new URLSearchParams();
        fd.append('action', 'reorder_overlays');
        fd.append('sketch_id', sketchId);
        fd.append('order', ids.join(','));
        fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(res=>{
                if(res.success) {
                    // Update display-order datasets internally
                    container.querySelectorAll('.overlay-block').forEach((b, i) => b.dataset.displayOrder = i);
                }
            });
    }

    document.querySelectorAll('.sketch-block').forEach(block => {
        const sketchId = block.dataset.sketchId;
        const list     = block.querySelector('.overlay-list');
        const addBtn   = block.querySelector('.add-line-btn');

        list.querySelectorAll('.overlay-block').forEach(el => {
            const overlayId = el.dataset.overlayId;
            const displayOrder = el.dataset.displayOrder;
            const txt = el.querySelector('textarea');
            setTimeout(() => { txt.style.height = 'auto'; txt.style.height = txt.scrollHeight + 'px'; }, 50);
            txt.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
                debounceSave(overlayId, sketchId, displayOrder, this.value);
            });
            el.querySelector('.del-btn').addEventListener('click', () => deleteOverlay(overlayId, el));
        });

        addBtn.addEventListener('click', () => addOverlay(sketchId, list));
        initDragSort(list, sketchId);
    });
});

// ── Language Modal JS ────────────────────────────────────────────────────────
window.openLangModal = function() {
    document.getElementById('langModalBackdrop').classList.add('active');
    loadLanguages();
};
window.closeLangModal = function() {
    document.getElementById('langModalBackdrop').classList.remove('active');
};
function loadLanguages() {
    fetch('cinemagic_editor_api.php?action=get_languages')
        .then(r => r.json()).then(res => {
            if (res.success) {
                document.getElementById('lang-list').innerHTML = res.languages.map(l => `
                    <div class="lang-row">
                        <div><strong style="color:var(--cm-amber);text-transform:uppercase;margin-right:8px;">${l.code}</strong> ${escHtml(l.name)}</div>
                        ${l.is_main == 1 ? '<span style="font-size:10px;color:var(--cm-text-dim);font-weight:bold;">MAIN</span>' : `<button class="del-btn" onclick="deleteLanguage('${l.code}')"><i class="bi bi-trash"></i></button>`}
                    </div>
                `).join('');
            }
        });
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
window.saveLanguage = function() {
    const code = document.getElementById('lang-code').value.trim();
    const name = document.getElementById('lang-name').value.trim();
    if(code.length!==2 || !name) return Toast.show('Invalid 2-letter code or name', 'warn');
    const fd = new URLSearchParams();
    fd.append('action', 'save_language'); fd.append('code', code); fd.append('name', name);
    fetch('cinemagic_editor_api.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(res=>{
            if(res.success) {
                document.getElementById('lang-code').value=''; document.getElementById('lang-name').value='';
                loadLanguages(); Toast.show('Language saved','success');
                // Reload page to reflect new languages in dropdown
                setTimeout(()=>window.location.reload(), 1000);
            } else Toast.show(res.error || 'Failed','error');
        });
}
window.deleteLanguage = function(code) {
    if(!confirm('Delete this system language?')) return;
    const fd = new URLSearchParams();
    fd.append('action', 'delete_language'); fd.append('code', code);
    fetch('cinemagic_editor_api.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(res=>{
            if(res.success) { loadLanguages(); Toast.show('Language deleted','info'); setTimeout(()=>window.location.reload(), 1000); }
        });
}
</script>

<?php
echo $eruda ?? "";
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
