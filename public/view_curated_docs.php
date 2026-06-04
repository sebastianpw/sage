<?php
// public/view_curated_docs.php
// Showrunner V9.6 Merge — Restored full rendering features from V9.2 + V9.6 embed/deep-linking
// - Full recursive episode/story rendering restored
// - Capture-phase TTS interception restored
// - Robust indexing (aliases, raw chunks, episode numbers)
// - Modal history + deep-link auto-open + embed mode
// - Added: Visual Sketch Preview Gallery integration inside modal
// - Added: Sketch Analysis Curation Pill and Modal
// ----------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// --- AJAX HANDLER FOR VISUALS FETCH ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_visuals') {
    header('Content-Type: application/json');
    try {
        $docId = (int)$_POST['doc_id'];
        $entityName = $_POST['entity_name'];
        $entityType = $_POST['entity_type'] ?? '';
        
        $sqlHistory = "
            SELECT slh.sketch_id, s.name, s.description,
                   sa.overall_quality, sa.classification, sa.scoring, sa.entities, sa.thematics, sa.recommendations
            FROM sketch_lore_history slh
            JOIN sketches s ON slh.sketch_id = s.id
            LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE slh.doc_id = ? 
              AND slh.entity_type = ? 
              AND slh.entity_name = ?
            ORDER BY slh.id DESC LIMIT 1
        ";
        $stmt = $pdo->prepare($sqlHistory);
        $stmt->execute([$docId, $entityType, $entityName]);
        $historyRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sketchData = null;
        if ($historyRow) {
            $sketchId = $historyRow['sketch_id'];
            $sqlFrames = "
                SELECT f.id, f.filename
                FROM frames f
                WHERE (f.entity_type = 'sketches' AND f.entity_id = ?)
                   OR f.id IN (SELECT from_id FROM frames_2_sketches WHERE to_id = ?)
                ORDER BY f.id DESC
            ";
            $fStmt = $pdo->prepare($sqlFrames);
            $fStmt->execute([$sketchId, $sketchId]);
            $frames = $fStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sketchData =[
                'id' => $sketchId,
                'name' => $historyRow['name'],
                'description' => $historyRow['description'],
                'frames' => $frames
            ];

            if (!empty($historyRow['classification'])) {
                $sketchData['curation'] =[
                    'score' => $historyRow['overall_quality'],
                    'class' => json_decode($historyRow['classification'], true),
                    'score_breakdown' => json_decode($historyRow['scoring'], true),
                    'entities' => json_decode($historyRow['entities'], true),
                    'themes' => json_decode($historyRow['thematics'], true),
                    'recs' => json_decode($historyRow['recommendations'], true)
                ];
            }
        }
        echo json_encode(['ok' => true, 'sketch' => $sketchData]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
// --- END AJAX HANDLER ---

function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$pageTitle = "Story Bible (Showrunner V9) 📜";
$filterCat = $_GET['category_id'] ?? '';
$minScore = $_GET['min_score'] ?? 0;

// --- DEEP LINKING / EMBED PARAMS ---
$filterDocId = $_GET['doc_id'] ?? null;
$isEmbed = isset($_GET['embed']); // If true, hides global UI
$focusType = $_GET['focus_type'] ?? '';
$focusEntity = $_GET['focus_entity'] ?? '';

$where =["da.id IS NOT NULL"];
$params =[];

if ($filterDocId) {
    // Deep Link Mode: Show only this document
    $where[] = "da.doc_id = :doc_id";
    $params['doc_id'] = $filterDocId;
} else {
    // List Mode
    if ($filterCat) { $where[] = "d.category_id = :cat"; $params['cat'] = $filterCat; }
    if ($minScore > 0) { $where[] = "da.narrative_utility >= :score"; $params['score'] = $minScore; }
}

$whereSql = implode(" AND ", $where);
$sql = "
    SELECT da.*, d.name as doc_name, c.name as category_name
    FROM md_doc_analysis da
    JOIN documentations d ON da.doc_id = d.id
    LEFT JOIN documentation_categories c ON d.category_id = c.id
    WHERE $whereSql
    ORDER BY da.narrative_utility DESC, d.updated_at DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cats = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!-- Dependencies for Gallery -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    const lightbox = new PhotoSwipeLightbox({
        gallery: '.pswp-gallery', children: 'a', pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
        initialZoomLevel: 'fit',
        secondaryZoomLevel: 1
    });
    lightbox.init();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<style>

html { font-size: 130% !important; }

/* --- CORE THEME (V9) --- */
:root {
    --fold-bg: rgba(0,0,0,0.02);
    --fold-border: rgba(0,0,0,0.08);
    --accent-subtle: rgba(139, 92, 246, 0.1);
    --story-color: #8b5cf6;
    --world-color: #3b82f6;
    --curator-color: #10b981;
    --card-hover: translateY(-2px);
}

.lore-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(500px, 1fr)); gap: 24px; padding: 24px; }
.lore-card { 
    background: var(--card); border: 1px solid var(--border); border-radius: 12px; 
    display:flex; flex-direction:column; box-shadow: var(--card-elevation); 
    transition: all 0.2s ease; position: relative; overflow: hidden;
}
.lore-card:hover { border-color: var(--accent); transform: var(--card-hover); }

/* --- HEADER & TOGGLES --- */
.card-header { 
    padding: 14px 18px; border-bottom: 1px solid var(--border); 
    display: flex; justify-content: space-between; align-items: center; 
    background: var(--fold-bg); cursor: pointer; user-select: none;
}
.card-header:hover { background: rgba(0,0,0,0.04); }

.header-main { flex: 1; display: flex; align-items: center; gap: 10px; }
.toggle-icon { font-size: 0.8rem; color: var(--text-muted); transition: transform 0.2s; }
.lore-card.collapsed .toggle-icon { transform: rotate(-90deg); }
.lore-card.collapsed .card-body, 
.lore-card.collapsed .card-footer { display: none; }

.doc-direct-link {
    text-decoration: none; font-size: 1.2rem; padding: 4px 8px; border-radius: 6px;
    color: var(--text-muted); transition: all 0.2s; margin-left: 10px; border: 1px solid transparent;
}
.doc-direct-link:hover { background: var(--accent); color: white; border-color: var(--accent); }

.card-body { padding: 18px; flex:1; font-size: 0.9rem; display: flex; flex-direction: column; gap: 12px; }
.card-footer { padding: 12px 18px; border-top: 1px solid var(--border); background: var(--fold-bg); }

/* --- INSIGHT BUTTON --- */
.insight-btn {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
    border: 1px solid rgba(16, 185, 129, 0.3); color: var(--curator-color);
    padding: 12px; border-radius: 8px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; gap: 10px; transition: all 0.2s;
    text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;
}
.insight-btn:hover { background: rgba(16, 185, 129, 0.15); transform: translateY(-1px); }

/* --- DOWNLOAD BUTTON (Added Feature) --- */
.download-btn {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: var(--accent);
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: 10px;
}
.download-btn:hover {
    background: rgba(139, 92, 246, 0.15);
    transform: translateY(-1px);
}

/* --- FOLDERS --- */
.cat-header { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin: 12px 0 6px 0; letter-spacing: 0.05em; border-bottom: 1px dashed var(--border); padding-bottom: 4px; }
.cat-group { margin-bottom: 8px; border: 1px solid var(--fold-border); border-radius: 8px; overflow: hidden; }
.cat-summary { 
    padding: 8px 14px; background: rgba(0,0,0,0.015); cursor: pointer; 
    font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;
    transition: background 0.2s; user-select: none;
}
.cat-summary:hover { background: rgba(0,0,0,0.05); color: var(--text); }
.cat-content { padding: 10px; display: flex; flex-wrap: wrap; gap: 8px; background: var(--card); display:none; }

.entity-btn {
    border: 1px solid var(--border); background: var(--card); padding: 5px 10px; 
    border-radius: 5px; font-size: 0.85rem; cursor: pointer; color: var(--text);
    display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s;
    max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.entity-btn:hover { border-color: var(--accent); background: var(--accent-subtle); color: var(--accent); transform: translateY(-1px); }
.entity-btn.story-item { border-left: 3px solid var(--story-color); }
.entity-btn.world-item { border-left: 3px solid var(--world-color); }

/* --- INLINE TIMELINE --- */
.cat-content.inline-list { display: block; padding: 15px 15px 5px 25px; } 
.inline-tl-item { position: relative; margin-bottom: 15px; border-left: 2px solid var(--border); padding-left: 15px; }
.inline-tl-item::before { 
    content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; 
    background: var(--text-muted); border-radius: 50%; 
}
.inline-tl-meta { font-size: 0.75rem; font-weight: 700; color: var(--accent); text-transform: uppercase; margin-bottom: 2px; }
.inline-tl-text { font-size: 0.9rem; color: var(--text); line-height: 1.4; }

/* --- MODAL --- */
.modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); z-index: 1000; animation: fadeIn 0.2s; }
.modal-window { 
    display: none; position: fixed; top: 2vh; bottom: 2vh; left: 50%; transform: translateX(-50%);
    width: 95%; max-width: 1100px; background: var(--card); 
    border-radius: 12px; box-shadow: 0 25px 60px rgba(0,0,0,0.5); z-index: 1001; 
    flex-direction: column; overflow: hidden; border: 1px solid var(--border); animation: slideUp 0.3s;
    font-size: 130% !important;
}
.modal-head { 
    padding: 15px 20px; border-bottom: 1px solid var(--border); background: var(--fold-bg); 
    display: flex; justify-content: space-between; align-items: center; flex-shrink:0; 
    gap: 15px;
}
.modal-title-group { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
.modal-title-group h2 { 
    margin: 0; font-size: 1.5rem; color: var(--text); line-height: 1.2; 
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
}
.modal-title-group .subtitle { margin-top: 4px; color: var(--text-muted); font-size: 0.85rem; font-family: monospace; text-transform:uppercase; letter-spacing:0.05em; }

.modal-controls { display: flex; align-items: center; gap: 15px; flex-shrink: 0; }
.modal-nav { display: flex; gap: 6px; padding-right: 15px; border-right: 1px solid var(--border); }
.nav-btn {
    background: transparent; border: 1px solid var(--border); color: var(--text-muted);
    border-radius: 6px; width: 36px; height: 36px; cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; transition: all 0.2s;
}
.nav-btn:hover:not(:disabled) { background: var(--accent); color: white; border-color: var(--accent); }
.nav-btn:disabled { opacity: 0.3; cursor: default; border-color: transparent; }

.modal-close { background: none; border: none; font-size: 2.2rem; line-height: 0.8; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; }
.modal-scroll { flex: 1; overflow-y: auto; padding: 30px; scroll-behavior: smooth; }

/* --- RENDERERS --- */
.detail-section { margin-bottom: 35px; }
.section-head { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--accent); font-weight: 800; border-bottom: 2px solid var(--accent-subtle); padding-bottom: 8px; margin-bottom: 16px; }
.studio-note { background: rgba(255,255,255,0.03); border-left: 4px solid var(--curator-color); padding: 15px 20px; font-family: 'Courier New', monospace; font-size: 0.95rem; line-height: 1.6; border-radius: 0 8px 8px 0; margin-bottom: 20px; position:relative; }
.bible-text { font-family: serif; font-size: 1.1rem; line-height: 1.7; white-space: pre-wrap; color: var(--text); }
.bible-header { font-weight: 800; font-size: 1.2rem; margin-top: 20px; margin-bottom: 10px; color: var(--accent); border-bottom: 1px dashed var(--border); display: inline-block; }
.attr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.attr-card { background: var(--fold-bg); border: 1px solid var(--fold-border); border-radius: 8px; padding: 12px; font-size: 0.9rem; break-inside: avoid; }
.attr-key { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight:700; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center; }
.attr-val { color: var(--text); white-space: pre-wrap; line-height: 1.6; }
.rel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.rel-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 14px; display: flex; flex-direction: column; gap: 4px; border-left: 4px solid var(--border); }
.rel-card.positive { border-left-color: var(--green); } .rel-card.negative { border-left-color: var(--red); } .rel-card.neutral { border-left-color: var(--orange); }
.rel-target { font-weight: 700; font-size: 1.05rem; color: var(--accent); cursor: pointer; border-bottom: 1px dotted var(--accent); align-self: flex-start; }
.rel-desc { font-size: 0.9rem; color: var(--text-muted); font-style: italic; margin-top:4px; }
.timeline { border-left: 2px solid var(--border); padding-left: 24px; margin-left: 10px; }
.t-event { position: relative; margin-bottom: 20px; }
.t-event::before { content: ''; position: absolute; left: -29px; top: 6px; width: 10px; height: 10px; background: var(--accent); border-radius: 50%; box-shadow: 0 0 0 4px var(--card); }
.t-content { font-size: 1rem; line-height: 1.5; }
.raw-foldable { margin-top: 0; border: none; }
.raw-summary { font-family: monospace; cursor: pointer; color: var(--text-muted); font-weight: 700; font-size: 0.75rem; text-align:right; }
.raw-content { background: #111; color: #0f0; padding: 20px; border-radius: 8px; overflow: auto; max-height: 400px; white-space: pre-wrap; font-family: monospace; font-size: 0.8rem; margin-top: 10px; }
.lore-link { color: var(--accent); font-weight: 600; cursor: pointer; text-decoration: none; border-bottom: 1px dotted var(--accent); transition:0.2s; }
.lore-link:hover { background: var(--accent-subtle); border-bottom-style: solid; }
.tts-select-icon { cursor: pointer; font-size: 0.9em; margin-left: 8px; opacity: 0.5; transition: all 0.15s; border-radius: 50%; padding:2px; user-select:none; }
.tts-select-icon:hover { opacity: 1; transform: scale(1.08); background: rgba(0,0,0,0.04); }

/* --- VISUAL GALLERY --- */
.visual-container { display: none; flex-direction: column; background: var(--fold-bg); border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 25px; }
.swiper-slide { width: auto; height: 100%; display: flex; align-items: center; justify-content: center; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--border); position: relative; }
.swiper-slide img { width: 240px; height: 240px; display: block; object-fit: contain; }

/* Frame View Modal */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }

.f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
.swiper-slide:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--text); border-color: var(--text); color: #000; }

/* --- CURATION MODAL STYLES --- */
.badge-curator {
    background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(52,211,153,0.1));
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.3);
    cursor: pointer;
    display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; margin-left: 10px;
    vertical-align: middle;
}
.badge-curator:hover { background: rgba(16,185,129,0.15); }
.pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin-right: 4px; margin-bottom: 4px; color: var(--text); border: 1px solid transparent; }
.pill-theme { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.1); }
.pill-char { border-color: #f59e0b; color: #f59e0b; background: rgba(245,159,11,0.1); }

.curation-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; }
.curation-modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
.curation-modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: var(--text-muted); background: none; border: none; line-height: 1; }
.curation-modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; align-items: flex-start; }
.curation-modal-label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; display: block; margin-bottom: 4px; min-width: 100px; }
.curation-modal-value { font-size: 0.95rem; display: block; flex: 1; }

@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes slideUp { from { transform: translate(-50%, 30px); opacity:0; } to { transform: translate(-50%, 0); opacity:1; } }

/* --- EMBED MODE STYLES --- */
<?php if ($isEmbed): ?>
    /* Hide Global Nav when embedded in Explorer */
    body { background: transparent !important; padding: 0 !important; }
    #page-header, .header-main-page, #debug-bar { display: none !important; }
    
    /* Ensure card fits frame */
    .lore-grid { padding: 0 !important; gap: 0 !important; display: block !important; }
    .lore-card { 
        border: none !important; box-shadow: none !important; border-radius: 0 !important; 
        min-height: 100vh; margin: 0; 
    }
    .card-header { display: none !important; } /* Hide card toggle in embed */
    .card-footer { display: none !important; }
    .card-body { padding: 20px !important; }
    
    /* Modal Override for Embed */
    .modal-window { 
        top: 0; bottom: 0; left: 0; right: 0; width: 100%; max-width: none; 
        transform: none; border-radius: 0; border: none; 
    }
    .modal-backdrop { opacity: 1 !important; background: var(--card); }
<?php endif; ?>
</style>

<!-- GLOBAL HEADER (Hidden if Embedded) -->
<?php if (!$isEmbed): ?>
<div class="header-main-page" style="padding: 20px; border-bottom: 1px solid var(--border); display:flex; gap:20px; align-items:center; flex-wrap:wrap; background: var(--card);">
    <div style="font-size:1.4rem; font-weight:800;">📜 Story Bible <span style="font-weight:400; opacity:0.6;">v9.6</span></div>
    <form method="GET" style="display:flex; gap:10px; flex:1;">
        <select name="category_id" style="padding:10px; border-radius:6px; border:1px solid var(--border); background: var(--card); color: var(--text);">
            <option value="">All Categories</option>
            <?php foreach($cats as $c): ?>
                <option value="<?= h($c['id']) ?>" <?= $filterCat == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="entity-btn" style="background:var(--accent); color:#fff; border:none; padding: 10px 20px;">Filter View</button>
    </form>
</div>
<?php endif; ?>

<!-- MAIN GRID -->
<div class="lore-grid">
    <?php foreach($rows as $row):
        $docId = $row['doc_id'];
        
        $entities = json_decode($row['entities'] ?? '{}', true) ??[];
        $showrunner = json_decode($row['showrunner_analysis'] ?? '{}', true) ??[];
        $lorePoints = json_decode($row['lore_points'] ?? '{}', true) ??[];
        $thematics = json_decode($row['thematics'] ?? '{}', true) ??[];

        // FLATTEN / HOIST Logic (same robust hoist as V9.2)
        $hoistData = function($parent, $key) {
            if (!isset($parent[$key]) || !is_array($parent[$key])) return $parent;
            $inner = $parent[$key];
            unset($parent[$key]);
            foreach ($inner as $k => $v) {
                // If both are arrays, merge them (Appends lists, Updates objects)
                if (isset($parent[$k]) && is_array($parent[$k]) && is_array($v)) {
                    $parent[$k] = array_merge($parent[$k], $v);
                } else {
                    // Otherwise, just set/overwrite (Newer data wins)
                    $parent[$k] = $v;
                }
            }
            return $parent;
        };

        $entities = $hoistData($entities, 'extraction'); 
        $entities = $hoistData($entities, 'entities');
        $showrunner = $hoistData($showrunner, 'chunk_analysis');
        
        $curatorData = [
            'bible' => $row['series_bible'] ?? '',
            'production_notes' => $showrunner['production_notes'] ?? ($showrunner['production_notes_by_chunk'] ?? []),
            'themes' => $thematics['themes'] ??[],
            'mood' => $thematics['mood'] ?? '',
            'summary' => $row['summary'] ?? ''
        ];

        foreach ($lorePoints as $key => $val) {
            if (!empty($val)) {
                $catName = ($key === 'timeline_events') ? 'timeline' : (($key === 'technology_magic') ? 'technology' : $key);
                $list =[];
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v)) $list[] =['description' => $v, 'name' => 'Entry'];
                        else $list[] = $v;
                    }
                }
                if (!empty($list)) {
                    if (isset($entities[$catName])) $entities[$catName] = array_merge($entities[$catName], $list);
                    else $entities[$catName] = $list;
                }
            }
        }

        $storyData =[];
        if (!empty($showrunner['episode_concepts'])) $storyData['episodes'] = $showrunner['episode_concepts'];
        if (!empty($showrunner['narrative_engines_all'])) $storyData['narrative_engine'] = $showrunner['narrative_engines_all'];
        if (!empty($showrunner['visual_keywords'])) $storyData['visual_keywords'] = $showrunner['visual_keywords'];
        if (!empty($showrunner['scene_hooks'])) $storyData['scene_hooks'] = $showrunner['scene_hooks'];

        $masterJson =[
            'curator' => $curatorData,
            'world' => $entities,
            'story' => $storyData,
            'meta' =>[ 'doc_id' => $docId, 'name' => $row['doc_name'] ]
        ];
    ?>
    <div class="lore-card" id="card-<?= h($docId) ?>">
        <div class="card-header" onclick="toggleDocCard(<?= h($docId) ?>)">
            <div class="header-main">
                <div class="toggle-icon">▼</div>
                <div>
                    <div class="doc-title"><?= h($row['doc_name']) ?></div>
                    <div class="doc-cat"><?= h($row['category_name'] ?? 'General') ?></div>
                </div>
            </div>
            
            <div style="display:flex; align-items:center;">
                <div style="font-weight:bold; color:var(--accent); font-size:1.2rem; margin-right:15px;"><?php /* number_format((float)$row['narrative_utility'], 1); */ ?></div>
                <a href="view_md.php?id=<?= h($docId) ?>" class="doc-direct-link" onclick="event.stopPropagation()" title="Read Full Document">📖</a>
            </div>
        </div>
        
        <div class="card-body">

            <div class="doc-title" style="font-weight: bold; font-style: italic;"><?= h($row['doc_name']) ?></div>

            <!-- Curator's Insight Button -->
            <button class="insight-btn" onclick="openModalFromGrid('<?= h($docId) ?>', 'curator', 'main', 0)">
                <span class="insight-icon">👁️</span> Curator's Insight & Vision
            </button>

            <!-- Folders Rendered via JS -->
            <div class="js-folders" data-doc-id="<?= h($docId) ?>"></div>
        </div>

        <div class="card-footer">
            <details class="raw-foldable">
                <summary class="raw-summary" style="display:flex; justify-content:space-between; align-items:center;">
                    <button class="download-btn" onclick="copyCardJson('<?= h($docId) ?>', this, event)" style="margin:0;">📋 Copy JSON</button>
                    <span>RAW JSON SOURCE</span>
                </summary>
                <div class="raw-content"><?= h(json_encode($masterJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></div>
            </details>
        </div>

        <!-- PAYLOAD -->
        <script type="application/json" id="payload-<?= h($docId) ?>">
            <?= json_encode($masterJson, JSON_UNESCAPED_UNICODE) ?>
        </script>
    </div>
    <?php endforeach; ?>
</div>

<!-- MODAL -->
<div class="modal-backdrop" id="modalBackdrop"></div>
<div class="modal-window" id="modalWindow">
    <div class="modal-head">
        <div class="modal-title-group">
            <h2 id="mTitle" title="">Title</h2>
            <div class="subtitle" id="mSubtitle">Subtitle</div>
        </div>
        
        <div class="modal-controls">
            <!-- Navigation History Controls -->
            <div class="modal-nav">
                <button class="nav-btn" id="navBackBtn" onclick="modalGoBack()" title="Back">&lsaquo;</button>
                <button class="nav-btn" id="navFwdBtn" onclick="modalGoFwd()" title="Forward">&rsaquo;</button>
            </div>
            
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
    </div>
    
    <div class="modal-scroll">
        <!-- Visual Gallery Container -->
        <div id="mVisuals" class="visual-container">
            <h3 style="margin:0 0 10px 0; font-size:1rem; display:flex; justify-content:space-between; align-items:center; color: var(--accent);">
                <span>🖼️ Visual Sketch Preview</span>
                <span id="sketchTitle" style="font-weight:normal; color:var(--text-muted); font-size:0.9rem; display:flex; align-items:center;"></span>
            </h3>
            <div class="swiper pswp-gallery" id="sketchSwiper" style="width:100%; height:240px; margin-bottom:10px;">
                <div class="swiper-wrapper" id="sketchWrapper"></div>
                <div class="swiper-button-next" style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.8);"></div>
                <div class="swiper-button-prev" style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.8);"></div>
            </div>
            <textarea id="sketchDesc" readonly style="width:100%; font-size:0.85rem; color:var(--text-muted); background:rgba(0,0,0,0.03); border:1px solid var(--border); padding: 8px; border-radius: 4px; resize:vertical; height:60px;" placeholder="Sketch Description..."></textarea>
        </div>

        <div id="mContent"></div>
    </div>
</div>

<!-- CURATION ANALYSIS MODAL -->
<div id="curation-modal" class="curation-modal-overlay">
    <div class="curation-modal-content">
        <button class="curation-modal-close" onclick="document.getElementById('curation-modal').style.display='none'">&times;</button>
        <h3 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Narrative Analysis</h3>
        <div id="curation-modal-body"></div>
    </div>
</div>

<!-- Frame View Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<script>
/**
 * SHOWRUNNER ENGINE V9.6 MERGED
 * - Combined robust features from V9.2 and V9.6
 * - Indexing, fence-aware rendering, capture-phase TTS interception,
 *   full recursive story rendering, modal history, embed & deep-link.
 */

const loreIndex = {};           // lookup name -> { docId, type, cat, idx }
const payloadStore = {};       // store full parsed payload per docId for reliable openModal access
let modalHistory =[];         // stack of visited states {docId, type, cat, idx}
let modalHistoryIdx = -1;      // current position in stack
let visualSwiper = null;


// --- CLIPBOARD HELPERS ---
window.copyCardJson = function(docId, btnElement, event) {
    if(event) { event.stopPropagation(); event.preventDefault(); }
    const payloadEl = document.getElementById('payload-' + docId);
    if(payloadEl) {
        let text = payloadEl.textContent;
        try {
            // Re-stringify to ensure clean formatting
            text = JSON.stringify(JSON.parse(text), null, 2);
        } catch(e) {}
        copyToClipboardAction(text, btnElement);
    }
};

window.copyToClipboardAction = function(text, btnElement, event) {
    if(event) { event.stopPropagation(); event.preventDefault(); }
    
    const onSuccess = () => {
        if(!btnElement) return;
        const origText = btnElement.innerHTML;
        btnElement.innerHTML = "✅ Copied!";
        btnElement.style.background = "var(--curator-color)";
        btnElement.style.color = "#fff";
        setTimeout(() => { 
            btnElement.innerHTML = origText; 
            btnElement.style.background = "";
            btnElement.style.color = "";
        }, 2000);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(onSuccess).catch(err => console.error(err));
    } else {
        // Fallback for older browsers / non-HTTPS
        let textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try { document.execCommand('copy'); onSuccess(); } catch (err) { console.error('Copy fallback failed', err); }
        textArea.remove();
    }
};




function init() {
    console.log("Initializing V9.6 Engine (merged)...");
    
    const urlParams = new URLSearchParams(window.location.search);
    const isEmbed = urlParams.has('embed');

    // Restore global state
    document.querySelectorAll('.lore-card').forEach(card => {
        const id = card.id.replace('card-', '');
        const state = localStorage.getItem('sr_v9_doc_' + id);
        if (isEmbed) {
            card.classList.remove('collapsed'); // Always expanded in embed
        } else {
            if (state === 'closed') {
                card.classList.add('collapsed');
            }
        }
    });

    // Parse payloads and render folders, then index
    document.querySelectorAll('script[type="application/json"]').forEach(el => {
        try {
            const id = el.id.replace('payload-', '');
            const data = JSON.parse(el.textContent || '{}');
            payloadStore[id] = data; // keep for openModal
            renderFolders(id, data);
            indexDocument(id, data);
        } catch(e) { console.error("payload parse error", e); }
    });

// AUTO-OPEN / DEEP LINK LOGIC
const ENABLE_DEBUG_PANEL = false; // Set to true to show debug info

const focusEntity = urlParams.get('focus_entity');
const focusType = urlParams.get('focus_type');
const docId = urlParams.get('doc_id');

if (focusEntity && docId) {
    if (ENABLE_DEBUG_PANEL) {
        // Create a visible debug panel
        const debugPanel = document.createElement('div');
        debugPanel.id = 'debugPanel';
        debugPanel.style.cssText = 'position:fixed; top:10px; left:10px; right:10px; background:#000; color:#0f0; padding:15px; z-index:9999; font-family:monospace; font-size:12px; max-height:200px; overflow:auto; border:2px solid #0f0;';
        debugPanel.innerHTML = `
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <strong>🔍 Deep Link Debug</strong>
                <button onclick="copyDebugInfo()" style="padding:3px 8px; background:#0f0; color:#000; border:none; border-radius:3px; cursor:pointer;">📋 Copy</button>
            </div>
            <div>Entity: "${focusEntity}"</div>
            <div>Type: "${focusType}"</div>
            <div>Doc: ${docId}</div>
            <div>Normalized key: "${normKey(focusEntity)}"</div>
            <div>Index has ${Object.keys(loreIndex).length} entries</div>
            <div id="debug-result">⏳ Searching...</div>
            <button onclick="this.parentElement.remove()" style="margin-top:10px; padding:5px 10px;">Close</button>
        `;
        document.body.appendChild(debugPanel);
        
        // Copy function
        window.copyDebugInfo = function() {
            const panel = document.getElementById('debugPanel');
            const text = panel.innerText;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Debug info copied!');
                }).catch(() => {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        };
        
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('Debug info copied!');
            } catch (err) {
                alert('Copy failed - please screenshot');
            }
            document.body.removeChild(textarea);
        }
    }
    
    setTimeout(() => {
        const key = normKey(focusEntity);
        const resultDiv = ENABLE_DEBUG_PANEL ? document.getElementById('debug-result') : null;
        
        if (loreIndex[key]) {
            const e = loreIndex[key];
            if (resultDiv) resultDiv.innerHTML = `✅ Found in index!<br>Type: ${e.type}<br>Cat: ${e.cat}<br>Idx: ${e.idx}`;
            openModalFromGrid(e.docId, e.type, e.cat, e.idx);
        } else {
            if (resultDiv) {
                const allKeys = Object.keys(loreIndex);
                const matches = allKeys.filter(k => k.includes(key.split(' ')[0]));
                resultDiv.innerHTML = `❌ NOT in index<br>Similar keys: ${matches.slice(0,5).join(', ') || 'none'}<br><br>Trying fallback...`;
            }
            
            // Try fallback
            const payload = payloadStore[docId];
            if (payload && payload.story) {
                const storyKeys = Object.keys(payload.story);
                if (resultDiv) resultDiv.innerHTML += `<br>Story cats: ${storyKeys.join(', ')}`;
                
                // Try each story category
                for (let cat of storyKeys) {
                    const items = payload.story[cat];
                    if (!Array.isArray(items)) continue;
                    
                    for (let idx = 0; idx < items.length; idx++) {
                        const item = items[idx];
                        if (typeof item === 'string' && normKey(item) === key) {
                            if (resultDiv) resultDiv.innerHTML += `<br>✅ Found via fallback in ${cat}[${idx}]`;
                            openModalFromGrid(docId, 'story', cat, idx);
                            return;
                        }
                        if (item && typeof item === 'object') {
                            const checkFields =[item.name, item.title, item.episode_number ? 'Episode ' + item.episode_number : null].filter(Boolean);
                            if (checkFields.some(f => normKey(f) === key)) {
                                if (resultDiv) resultDiv.innerHTML += `<br>✅ Found via fallback in ${cat}[${idx}]`;
                                openModalFromGrid(docId, 'story', cat, idx);
                                return;
                            }
                        }
                    }
                }
            }
            
            if (resultDiv) resultDiv.innerHTML += `<br>⚠️ Fallback failed, showing curator view`;
            openModalFromGrid(docId, 'curator', 'main', 0);
        }
    }, 150);
}



}

/* Normalize a display key for indexing */
function normKey(name) {
    if(!name) return '';
    return String(name).trim().toLowerCase();
}

/* Index a single document: world + story categories with raw chunk indexing */
function indexDocument(docId, data) {
    if (!data) return;
    // world
    if (data.world && typeof data.world === 'object') {
        Object.keys(data.world).forEach(cat => {
            const items = data.world[cat];
            if (!Array.isArray(items)) return;
            items.forEach((item, idx) => {
                // allow string items too
                let candidates =[];
                if (typeof item === 'string') {
                    candidates.push(item);
                } else if (item && typeof item === 'object') {
                    if (item.name) candidates.push(item.name);
                    if (item.title) candidates.push(item.title);
                    if (item.id) candidates.push(item.id);
                    if (Array.isArray(item.aliases)) item.aliases.forEach(a => candidates.push(a));
                    if (Array.isArray(item.raw)) {
                        item.raw.forEach(r => {
                            if (r && typeof r === 'object') {
                                if (r.name) candidates.push(r.name);
                                if (r.title) candidates.push(r.title);
                            }
                        });
                    }
                }
                candidates = candidates.filter(Boolean);
                candidates.forEach(c => {
                    const k = normKey(c);
                    if (!k) return;
                    // store first match mapping (but allow later override intentionally)
                    loreIndex[k] = { docId: String(docId), type: 'world', cat, idx };
                });
            });
        });
    }

    // story
    if (data.story && typeof data.story === 'object') {
        Object.keys(data.story).forEach(cat => {
            const items = data.story[cat];
            if (!Array.isArray(items)) return;
            items.forEach((item, idx) => {
                let candidates =[];
                if (typeof item === 'string') candidates.push(item);
                else if (item && typeof item === 'object') {
                    if (item.title) candidates.push(item.title);
                    if (item.name) candidates.push(item.name);
                    if (item.episode_number) {
                        candidates.push(String(item.episode_number));
                        candidates.push("Episode " + item.episode_number);
                    }
                    if (item.id) candidates.push(item.id);
                    if (Array.isArray(item.aliases)) item.aliases.forEach(a => candidates.push(a));
                }
                candidates = candidates.filter(Boolean);
                candidates.forEach(c => {
                    const k = normKey(c);
                    if (!k) return;
                    loreIndex[k] = { docId: String(docId), type: 'story', cat, idx };
                });
            });
        });
    }
}

/* ------------------------
   fence-aware renderer
   - finds ```lang\n...``` blocks
   - if lang === 'json' will try JSON.parse + pretty print
   - otherwise prints escaped content inside .raw-content block
   - non-fenced text uses linkify(...) so cross-links remain active
   ------------------------ */
function renderTextWithFences(text) {
    if (text === null || text === undefined) return '';
    text = String(text);
    const fenceRegex = /```(\w*)\n([\s\S]*?)\n```/g;
    let lastIndex = 0;
    let out = '';
    let m;
    while ((m = fenceRegex.exec(text)) !== null) {
        const lang = (m[1] || '').toLowerCase();
        const inner = m[2] || '';
        out += linkify(text.slice(lastIndex, m.index)); // non-fenced part (linkified & escaped inside)
        if (lang === 'json') {
            try {
                const obj = JSON.parse(inner);
                const pretty = JSON.stringify(obj, null, 2);
                out += `<div class="raw-content">${escapeHtml(pretty)}</div>`;
            } catch (e) {
                // invalid json -> show raw content escaped
                out += `<div class="raw-content">${escapeHtml(inner)}</div>`;
            }
        } else {
            // generic fenced block: show as raw-pre
            out += `<div class="raw-content">${escapeHtml(inner)}</div>`;
        }
        lastIndex = fenceRegex.lastIndex;
    }
    out += linkify(text.slice(lastIndex));
    return out;
}

/* TTS handling: robust capture-phase intercept across pointer/touch/mouse/click
   Restored from V9.2 to avoid duplicate UI and ensure selection works on touch devices.
*/
window.selectTextForTts = function(btn, evt) {
    try {
        if (evt && typeof evt.stopImmediatePropagation === 'function') evt.stopImmediatePropagation();
        if (evt && typeof evt.stopPropagation === 'function') evt.stopPropagation();
        if (evt && typeof evt.preventDefault === 'function') evt.preventDefault();
    } catch (e) {}

    const parent = btn && btn.parentElement ? btn.parentElement.parentElement : null;
    const content = parent ? parent.querySelector('.attr-val, .studio-note, .bible-text, .t-content, .inline-tl-text') : null;
    const target = content || (btn && btn.parentElement ? btn.parentElement.nextElementSibling : null) || (btn && btn.parentElement ? btn.parentElement : null);

    if (target) {
        try {
            const range = document.createRange();
            range.selectNodeContents(target);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } catch (e) {
            console.warn('Selection failed', e);
        }

        const status = document.getElementById('tts-status-text');
        const widget = document.getElementById('tts-float-widget');
        if (status && widget) {
            status.textContent = "Text marked! Click Play ▶️";
            widget.classList.add('tts-pulse');
            setTimeout(() => widget.classList.remove('tts-pulse'), 1000);
        }
    }

    if (btn) btn.setAttribute('data-sr-tts-handled', '1');

    try {
        const ev = new CustomEvent('sr:tts:selection', { detail: { element: btn, text: (target ? target.innerText : '') } });
        document.dispatchEvent(ev);
    } catch (e) {}

    // Attempt to notify parent frame if present (safe/caught)
    try {
        if (window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
            window.parent.postMessage({ sr_tts_selection: target ? target.innerText : '' }, '*');
        }
    } catch (ex) {}

    return false;
};

// Intercept speaker interactions early (capture) to avoid duplicate UI from other listeners
const ttsIntercept = (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('.tts-select-icon') : null;
    if (!btn) return;
    try { e.stopImmediatePropagation(); } catch(ex) {}
    try { e.stopPropagation(); } catch(ex) {}
    try { if (e.cancelable) e.preventDefault(); } catch(ex) {}
    try { selectTextForTts(btn, e); } catch(ex) { console.error(ex); }
};['pointerdown','mousedown','touchstart','click'].forEach(evt => {
    document.addEventListener(evt, ttsIntercept, { capture: true, passive: false });
});

/* UI rendering helpers */
window.toggleDocCard = function(docId) {
    const card = document.getElementById('card-' + docId);
    if(!card) return;
    card.classList.toggle('collapsed');
    const state = card.classList.contains('collapsed') ? 'closed' : 'open';
    localStorage.setItem('sr_v9_doc_' + docId, state);
};

function renderFolders(docId, data) {
    const container = document.querySelector(`.js-folders[data-doc-id="${docId}"]`);
    if(!container) return;

    if (data.story && Object.keys(data.story).length > 0) {
        container.appendChild(createHeader('Story Engine'));
        Object.keys(data.story).forEach(cat => {
            const items = data.story[cat];
            if(items && items.length) container.appendChild(createFolder(docId, 'story', cat, items));
        });
    }

    if (data.world && Object.keys(data.world).length > 0) {
        container.appendChild(createHeader('World Elements'));
        const order =['characters', 'factions', 'locations', 'technology', 'magic', 'objects', 'history', 'timeline', 'lore_rules'];
        const keys = Object.keys(data.world).sort((a,b) => {
            let ia = order.indexOf(a), ib = order.indexOf(b);
            if(ia===-1) ia=99; if(ib===-1) ib=99; return ia-ib;
        });

        keys.forEach(cat => {
            const items = data.world[cat];
            if(Array.isArray(items) && items.length) {
                container.appendChild(createFolder(docId, 'world', cat, items));
            }
        });
    }
}

function createHeader(text) {
    const el = document.createElement('div');
    el.className = 'cat-header';
    el.innerText = text;
    return el;
}

function createFolder(docId, type, cat, items) {
    const group = document.createElement('div');
    group.className = 'cat-group';

    const summary = document.createElement('div');
    summary.className = 'cat-summary';
    const icon = type === 'story' ? '🎬' : '🌍';
    summary.innerHTML = `
        <span>${icon} ${cat.replace(/_/g, ' ')}</span> 
        <div style="display:flex; align-items:center; gap:10px;">
            <span class="entity-count" style="background:var(--accent); color:white; padding:2px 8px; border-radius:12px; font-size:0.75rem;">${items.length}</span>
            <button class="download-btn" onclick="window.downloadEntityJSON('${docId}', '${type}', '${cat}', event)" title="Download JSON">⬇️</button>
            <button class="download-btn" onclick="window.downloadEntityMD('${docId}', '${type}', '${cat}', event)" title="Download Markdown">📝</button>
        </div>`;

    const content = document.createElement('div');
    content.className = 'cat-content';

    const stateKey = `sr_v9_cat_${docId}_${cat}`;
    const savedState = localStorage.getItem(stateKey);
    if (savedState === 'open') content.style.display = 'flex';
    else content.style.display = 'none';

    if (cat === 'timeline' || cat === 'history' || cat === 'historical_events') {
        content.classList.add('inline-list');
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'inline-tl-item';

            let time = item.time || item.date || item.year || item.period || '';
            let text = item.event || item.description || item.name || (typeof item === 'string' ? item : JSON.stringify(item));
            if (typeof time === 'object') time = JSON.stringify(time);

            div.innerHTML = (time ? `<div class="inline-tl-meta">${escapeHtml(String(time))}</div>` : '') +
                            `<div class="inline-tl-text">${linkify(String(text))}</div>`;
            content.appendChild(div);
        });
    } else {
        items.forEach((item, idx) => {
            const btn = document.createElement('button');
            btn.className = `entity-btn ${type}-item`;
            let label = 'Item';
            if (typeof item === 'string') label = item;
            else if (item && typeof item === 'object') label = item.name || item.title || item.event || item.description || item.hook || item.rule || item.id || JSON.stringify(item).slice(0,60);
            if (label.length > 80) label = label.substring(0, 77) + '...';
            btn.innerText = label;
            // Attach dataset so openModal can be more robust
            btn.dataset.docId = String(docId);
            btn.dataset.type = type;
            btn.dataset.cat = cat;
            btn.dataset.idx = String(idx);
            btn.onclick = (e) => {
                e.stopPropagation();
                openModalFromGrid(btn.dataset.docId, btn.dataset.type, btn.dataset.cat, parseInt(btn.dataset.idx, 10));
            };
            content.appendChild(btn);
        });
    }

    summary.onclick = () => {
        const isOpen = content.style.display !== 'none';
        content.style.display = isOpen ? 'none' : (content.classList.contains('inline-list') ? 'block' : 'flex');
        localStorage.setItem(stateKey, isOpen ? 'closed' : 'open');
    };

    group.appendChild(summary);
    group.appendChild(content);
    return group;
}

// Curation Safe HTML Attribute Escaper
function escapeHtmlAttr(str) {
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Visual Gallery Fetching
function fetchVisuals(docId, cat, name) {
    const container = document.getElementById('mVisuals');
    const wrapper = document.getElementById('sketchWrapper');
    container.style.display = 'none';
    wrapper.innerHTML = '';
    
    const formData = new FormData();
    formData.append('action', 'fetch_visuals');
    formData.append('doc_id', docId);
    formData.append('entity_type', cat); 
    formData.append('entity_name', name);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok && data.sketch && data.sketch.frames && data.sketch.frames.length > 0) {
            
            let curationBadge = '';
            if (data.sketch.curation) {
                const cData = escapeHtmlAttr(JSON.stringify(data.sketch.curation));
                curationBadge = `<span class="badge-curator curation-pill-trigger" data-curation="${cData}" title="Quality Score: ${data.sketch.curation.score}">🕵️ Analysis (${data.sketch.curation.score})</span>`;
            }

            document.getElementById('sketchTitle').innerHTML = escapeHtml(data.sketch.name || '') + curationBadge;
            document.getElementById('sketchDesc').value = data.sketch.description || '';
            
            let slides = '';
            data.sketch.frames.forEach(f => {
                const safeUrl = escapeHtml(f.filename);
                slides += `
                    <div class="swiper-slide">
                        <a href="${safeUrl}" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                            <img src="${safeUrl}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                        </a>
                        <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openFrameModal(${f.id})"><i class="bi bi-arrows-fullscreen"></i></div>
                    </div>
                `;
            });
            wrapper.innerHTML = slides;
            container.style.display = 'flex';

            if (visualSwiper) {
                visualSwiper.destroy(true, true);
            }
            
            visualSwiper = new Swiper('#sketchSwiper', {
                slidesPerView: 'auto',
                spaceBetween: 10,
                freeMode: true,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });
        }
    })
    .catch(err => console.error('Error fetching visuals:', err));
}

// Curation Modal Event Listeners
document.getElementById('curation-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});

document.addEventListener('click', function(e) {
    const trigger = e.target.closest('.curation-pill-trigger');
    if (!trigger) return;
    e.stopPropagation();
    
    const raw = trigger.dataset.curation;
    if (!raw) return;
    const data = JSON.parse(raw);
    const body = document.getElementById('curation-modal-body');
    
    let html = `
        <div style="margin-bottom:15px;">
            <div class="score-badge" style="display:inline-block; padding:4px 10px; background:#10b981; color:white; border-radius:6px; font-weight:800; font-size:1.2em; margin-right:10px;">${data.score}</div>
            <strong style="font-size:1.1em;">Overall Quality</strong>
        </div>
    `;
    
    // Classification
    if(data.class) {
        if(data.class.narrative_function) html += `<div class="curation-modal-row"><span class="curation-modal-label">Function</span><span class="curation-modal-value">${escapeHtml(data.class.narrative_function)}</span></div>`;
        if(data.class.emotional_tone) html += `<div class="curation-modal-row"><span class="curation-modal-label">Tone</span><span class="curation-modal-value">${escapeHtml(data.class.emotional_tone)}</span></div>`;
    }

    // Themes
    if (data.themes && data.themes.primary_themes) {
        html += `<div class="curation-modal-row"><span class="curation-modal-label">Themes</span><div style="margin-top:4px;">`;
        let themes = Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes :[data.themes.primary_themes];
        themes.forEach(t => html += `<span class="pill pill-theme">${escapeHtml(t)}</span> `);
        html += `</div></div>`;
    }

    // Characters / Entities
    if (data.entities) {
         if(data.entities.characters && data.entities.characters.length > 0) {
            html += `<div class="curation-modal-row"><span class="curation-modal-label">Characters</span><div style="margin-top:4px;">`;
            data.entities.characters.forEach(c => html += `<span class="pill pill-char">${escapeHtml(c)}</span> `);
            html += `</div></div>`;
         }
         if(data.entities.artifacts && data.entities.artifacts.length > 0) {
            html += `<div class="curation-modal-row"><span class="curation-modal-label">Artifacts</span><div style="margin-top:4px;">${escapeHtml(data.entities.artifacts.join(', '))}</div></div>`;
         }
    }

    // Recommendation
    if(data.recs && data.recs.potential_use) {
         html += `<div style="margin-top:15px; background:rgba(245,159,11,0.1); padding:10px; border-radius:6px; border:1px dashed rgba(245,159,11,0.4);">
                    <span class="curation-modal-label" style="color:#f59e0b; border:none; margin:0;">Suggestion</span>
                    <div style="font-style:italic; margin-top:4px;">${escapeHtml(data.recs.potential_use)}</div>
                  </div>`;
    }

    // Score Breakdown
    if(data.score_breakdown) {
         html += `<div style="margin-top:15px; border-top:1px dashed var(--border); padding-top:10px;">
                    <span class="curation-modal-label">Score Breakdown</span>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:0.9em; margin-top:5px;">
                        <div>Narrative: <b>${data.score_breakdown.narrative_completeness || '-'}</b></div>
                        <div>Visual: <b>${data.score_breakdown.visual_impact || '-'}</b></div>
                        <div>Production: <b>${data.score_breakdown.production_readiness || '-'}</b></div>
                        <div>Distinctiveness: <b>${data.score_breakdown.visual_distinctiveness || '-'}</b></div>
                    </div>
                  </div>`;
    }
    
    body.innerHTML = html;
    document.getElementById('curation-modal').style.display = 'flex';
});

// Entry point from Main Grid: Resets History
window.openModalFromGrid = function(docId, type, cat, idx) {
    modalHistory =[];
    modalHistoryIdx = -1;
    openModal(docId, type, cat, idx, true);
};

// Internal Navigation
function openModal(docId, type, cat, idx, recordHistory = true) {
    const el = document.getElementById('payload-' + docId);
    if(!el) return;
    // Prefer payloadStore which is populated at init
    const master = payloadStore[docId] || JSON.parse(el.textContent || '{}');

    // HISTORY LOGIC
    if (recordHistory) {
        // If we are navigating while "back" in history, chop off the future
        if (modalHistoryIdx < modalHistory.length - 1) {
            modalHistory = modalHistory.slice(0, modalHistoryIdx + 1);
        }
        modalHistory.push({ docId, type, cat, idx });
        modalHistoryIdx++;
    }
    updateNavButtons();

    // RENDER LOGIC
    if (type === 'curator') {
        document.getElementById('mVisuals').style.display = 'none'; // Hide gallery in curator view
        renderCuratorView(master.curator, master.meta ? master.meta.name : '');
    } else {
        const bucket = master[type] && master[type][cat] ? master[type][cat] : null;
        if (!Array.isArray(bucket) || bucket.length <= idx) {
            console.warn("openModal fallback: bucket missing or index OOB", {docId, type, cat, idx});
            document.getElementById('mTitle').innerText = `${cat}`;
            document.getElementById('mSubtitle').innerText = `${type.toUpperCase()} / ${cat.toUpperCase()}`;
            document.getElementById('mContent').innerHTML = `<div class="studio-note">Item not found (index ${idx}).</div>`;
            document.getElementById('modalBackdrop').style.display = 'block';
            document.getElementById('modalWindow').style.display = 'flex';
            document.getElementById('mVisuals').style.display = 'none';
            return;
        }
        const item = bucket[idx];
        
        // --- HEADER NAME RESOLUTION ---
        let name = cat;
        if (typeof item === 'string') name = item;
        else if (item && typeof item === 'object') name = item.name || item.title || item.event || item.hook || cat;
        
        // SAFE HEADER LOGIC: Truncate long headers
        let headerTitle = name;
        if (String(headerTitle).length > 60) {
            headerTitle = cat.charAt(0).toUpperCase() + cat.slice(1).replace(/_/g, ' ');
        }

        const titleEl = document.getElementById('mTitle');
        titleEl.innerText = headerTitle;
        titleEl.title = name; // Tooltip gets the full text
        document.getElementById('mSubtitle').innerText = `${type.toUpperCase()} / ${cat.toUpperCase()}`;
        
        // Fetch Sketch Visuals (using the exact resolved name)
        fetchVisuals(docId, cat, name);

        const content = document.getElementById('mContent');
        content.innerHTML = '';

        // --- RENDER CONTENT ---
        if (typeof item === 'string') {
            // *** TTS Speaker to simple text items (like Visual Keywords) ***
            const div = document.createElement('div');
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:15px;">
                    <div class="attr-val" style="font-size:1.1rem; line-height:1.6; flex:1;">${linkify(item)}</div>
                    <span class="tts-select-icon" style="font-size:1.2rem; margin-top:4px; cursor:pointer;" onclick="selectTextForTts(this,event)">🔈</span>
                </div>`;
            content.appendChild(div);
        } else if (type === 'world') {
            renderRichEntity(content, item); 
        } else if (cat.includes('episode') || cat === 'episodes' || cat === 'episode_concepts') {
            renderEpisode(content, item);
        } else if (cat === 'narrative_engine') {
            renderNarrative(content, item);
        } else {
            renderGenericRecursive(content, item);
        }
        
        renderRawData(content, item);
    }

    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalWindow').style.display = 'flex';
}

function updateNavButtons() {
    document.getElementById('navBackBtn').disabled = (modalHistoryIdx <= 0);
    document.getElementById('navFwdBtn').disabled = (modalHistoryIdx >= modalHistory.length - 1);
}

window.modalGoBack = function() {
    if (modalHistoryIdx > 0) {
        modalHistoryIdx--;
        const state = modalHistory[modalHistoryIdx];
        openModal(state.docId, state.type, state.cat, state.idx, false); // false = don't push new history
    }
};

window.modalGoFwd = function() {
    if (modalHistoryIdx < modalHistory.length - 1) {
        modalHistoryIdx++;
        const state = modalHistory[modalHistoryIdx];
        openModal(state.docId, state.type, state.cat, state.idx, false);
    }
};

function renderCuratorView(curator, docName) {
    document.getElementById('mTitle').innerText = "Curator's Insight";
    document.getElementById('mSubtitle').innerText = docName;
    const content = document.getElementById('mContent');
    content.innerHTML = '';

    if ((curator.themes && curator.themes.length) || curator.mood) {
        const sec = createSection("Thematic Core");
        if (curator.mood) sec.appendChild(createDetailBlock("Mood & Tone", curator.mood));
        if (curator.themes && curator.themes.length) {
            sec.appendChild(createDetailBlock("Themes", curator.themes.join(" • ")));
        }
        content.appendChild(sec);
    }

    if (curator.production_notes) {
        const sec = createSection("Production & Direction Notes");
        const notes = Array.isArray(curator.production_notes) ? curator.production_notes : [curator.production_notes];
        
        notes.forEach(note => {
            const div = document.createElement('div');
            div.className = 'studio-note';
            
            // TTS Button
            const tts = document.createElement('span');
            tts.className = 'tts-select-icon';
            tts.innerHTML = '🔈';
            tts.style.position = 'absolute'; tts.style.right = '10px'; tts.style.top = '10px';
            tts.onclick = function(e) { selectTextForTts(this, e); };
            div.appendChild(tts);

            if (typeof note === 'object' && note !== null) {
                if (note.note) {
                    div.innerHTML += renderTextWithFences(note.note);
                } else {
                    div.innerHTML += formatComplexNote(note);
                }
            } else {
                div.innerHTML += renderTextWithFences(String(note));
            }
            sec.appendChild(div);
        });
        content.appendChild(sec);
    }

    if (curator.bible) {
        const sec = createSection("Series Bible & Narrative Architecture");
        const div = document.createElement('div');
        div.className = 'bible-text';
        // use fence-aware renderer, then apply simple markdown-like transforms
        let formatted = renderTextWithFences(curator.bible);
        formatted = formatted.replace(/=== (.*?) ===/g, '<div class="bible-header">$1</div>');
        formatted = formatted.replace(/\n- /g, '<br>• ');
        div.innerHTML = formatted;
        
        // TTS Button for Bible
        const tts = document.createElement('div');
        tts.style.textAlign = 'right';
        tts.innerHTML = '<span class="tts-select-icon" onclick="selectTextForTts(this,event)">🔈 Select All</span>';
        sec.appendChild(tts);

        sec.appendChild(div);
        content.appendChild(sec);
    }
}

function formatComplexNote(obj) {
    let html = '';
    Object.keys(obj).forEach(k => {
        if (k === '__src_chunk') return;
        const val = obj[k];
        html += `<div style="margin-bottom:10px;"><strong style="text-transform:uppercase; font-size:0.8rem; color:var(--accent);">${k.replace(/_/g,' ')}:</strong> `;
        if (typeof val === 'object' && val !== null) {
            html += `<div style="padding-left:15px; border-left:2px solid rgba(255,255,255,0.1); margin-top:4px;">${formatComplexNote(val)}</div>`;
        } else {
            html += `<span style="color:var(--text);">${renderTextWithFences(String(val))}</span>`;
        }
        html += `</div>`;
    });
    return html;
}

/* ------------------
   Aggregation + Rendering (restored full logic)
   ------------------ */
function aggregateEntityData(entity) {
    const final = {
        name: entity.name || entity.title || entity.rule || entity.event || 'Unknown',
        aliases: new Set(entity.aliases ||[]),
        roles: new Set(entity.roles ||[]),
        attributes: { ... (entity.attributes || {}) },
        relationships:[],
        timeline: [],
        raw_source: entity.raw ||[]
    };

    const addTime = (txt, type, date=null) => { if(txt) final.timeline.push({ text: txt, type, date }); };
    
    const addRel = (r) => {
        if(!r) return;
        if (!r.target && !r.entity_2 && !r.object) {
            // object keyed relationships (map)
            if (typeof r === 'object') {
                Object.keys(r).forEach(k => {
                    final.relationships.push({ target: k.replace(/_/g,' '), type: r[k] });
                });
                return;
            }
            return;
        }
        final.relationships.push({
            target: r.target || r.entity_2 || r.object,
            type: r.type || r.role || r.relationship_type,
            nature: r.nature || r.context || r.relationship,
            desc: r.description || r.action || r.details
        });
    };

    if (entity.actions) entity.actions.forEach(a => addTime(a, 'action'));
    if (entity.events) entity.events.forEach(e => addTime(e.event||e.name||e.description, 'event', e.time||e.chapter||e.year));
    if (entity.history) entity.history.forEach(h => addTime(h.event||h.description, 'history', h.time));
    if (entity.relationships) {
        if (Array.isArray(entity.relationships)) {
            entity.relationships.forEach(r => addRel(r));
        } else if (typeof entity.relationships === 'object') {
            Object.keys(entity.relationships).forEach(k => {
                final.relationships.push({ target: k.replace(/_/g,' '), type: entity.relationships[k] });
            });
        }
    }

    // Omni-Capture
    const excludeKeys =['name','title','id','type','raw','entities','relationships','actions','events','history','aliases','roles'];
    Object.keys(entity).forEach(k => {
        if (!excludeKeys.includes(k) && !final.attributes[k]) {
            if (k === 'timeline_events' || k === 'key_moments') {
                if (Array.isArray(entity[k])) entity[k].forEach(e => addTime(e.name||e, 'event'));
            } else {
                final.attributes[k] = entity[k];
            }
        }
    });

    // Process RAW Chunks
    if (Array.isArray(entity.raw)) {
        entity.raw.forEach(chunk => {
            if (!chunk) return;
            if (chunk.aliases) chunk.aliases.forEach(x => final.aliases.add(x));
            if (chunk.roles) chunk.roles.forEach(x => final.roles.add(x));
            if (chunk.attributes) {
                Object.keys(chunk.attributes).forEach(k => {
                    const v = chunk.attributes[k];
                    if (final.attributes[k]) {
                        if (!Array.isArray(final.attributes[k])) final.attributes[k] =[final.attributes[k]];
                        final.attributes[k].push(v);
                    } else final.attributes[k] = v;
                });
            }
            if (chunk.relationships) {
                if (Array.isArray(chunk.relationships)) chunk.relationships.forEach(r => addRel(r));
                else if (typeof chunk.relationships === 'object') Object.keys(chunk.relationships).forEach(k => final.relationships.push({target:k, type:chunk.relationships[k]}));
            }
            if (chunk.actions) chunk.actions.forEach(a => addTime(a, 'action'));
            Object.keys(chunk).forEach(k => {
                if (!excludeKeys.includes(k) && k !== 'attributes') final.attributes[k] = chunk[k];
            });
        });
    }
    return final;
}

function renderRichEntity(container, rawItem) {
    const data = aggregateEntityData(rawItem);

    if (Object.keys(data.attributes).length > 0 || data.aliases.size > 0) {
        const sec = createSection("Identity & Data");
        if(data.aliases.size) sec.appendChild(createDetailBlock("Aliases", Array.from(data.aliases).join(', ')));
        
        const grid = document.createElement('div');
        grid.className = 'attr-grid';
        Object.keys(data.attributes).forEach(k => {
            const val = data.attributes[k];
            const card = document.createElement('div');
            card.className = 'attr-card';
            let html = '';
            
            if(Array.isArray(val)) {
                html = '<ul>' + val.flat().map(v => typeof v === 'object' ? formatComplexNote(v) : `<li>${renderTextWithFences(String(v))}</li>`).join('') + '</ul>';
            } else if (typeof val === 'object' && val !== null) {
                html = formatComplexNote(val);
            } else {
                html = renderTextWithFences(String(val));
            }
            // attr-key includes a speaker button
            card.innerHTML = `<div class="attr-key"><span>${k.replace(/_/g,' ')}</span><span class="tts-select-icon" onclick="selectTextForTts(this,event)">🔈</span></div><div class="attr-val">${html}</div>`;
            grid.appendChild(card);
        });
        sec.appendChild(grid);
        container.appendChild(sec);
    }

    if (data.relationships.length > 0) {
        const sec = createSection("Network & Connections");
        const grid = document.createElement('div');
        grid.className = 'rel-grid';
        const grouped = {};
        data.relationships.forEach(r => { if(!r.target) return; if(!grouped[r.target]) grouped[r.target] = []; grouped[r.target].push(r); });

        Object.keys(grouped).forEach(target => {
            const rels = grouped[target];
            const card = document.createElement('div');
            let sentiment = 'neutral';
            const combined = JSON.stringify(rels).toLowerCase();
            if(combined.match(/enemy|hostile|conflict|antagonist|war|hates/)) sentiment = 'negative';
            if(combined.match(/friend|ally|love|bond|partner|respect/)) sentiment = 'positive';
            card.className = `rel-card ${sentiment}`;
            
            let details = rels.map(r => {
                let s = '';
                if(r.type) s += `<strong>${r.type}</strong>`;
                if(r.nature) s += ` (${r.nature})`;
                if(r.desc) s += `: ${r.desc}`;
                return `<div>${linkify(s)}</div>`;
            }).join('');

            card.innerHTML = `<div class="rel-target" onclick="clickLink('${escapeHtml(target)}')">${escapeHtml(target)}</div><div class="rel-desc">${details}</div>`;
            grid.appendChild(card);
        });
        sec.appendChild(grid);
        container.appendChild(sec);
    }

    if (data.timeline.length > 0) {
        const sec = createSection("Chronicle & Events");
        const tl = document.createElement('div');
        tl.className = 'timeline';
        const seen = new Set();
        data.timeline.forEach(item => {
            const key = item.text + item.date;
            if(seen.has(key)) return;
            seen.add(key);
            const row = document.createElement('div');
            row.className = 't-event';
            let meta = item.date ? `<div class="t-meta">${escapeHtml(item.date)}</div>` : '';
            row.innerHTML = `${meta}<div class="t-content">${linkify(item.text)}</div>`;
            tl.appendChild(row);
        });
        sec.appendChild(tl);
        container.appendChild(sec);
    }
}

// RESTORED: Full Recursive Logic for Episodes & Narrative
function renderEpisode(container, item) {
    if(item.logline) container.appendChild(createDetailBlock('Logline', item.logline));
    renderGenericRecursive(container, item, ['logline', 'title', 'episode']);
}

function renderNarrative(container, item) {
    if(item.core_conflict) container.appendChild(createDetailBlock("Core Conflict", item.core_conflict));
    renderGenericRecursive(container, item, ['core_conflict']);
}

// RESTORED: The "Heavy Duty" Recursive Engine (handles arrays of objects)
function renderGenericRecursive(container, item, skipKeys=[]) {
    if (typeof item === 'string') {
        container.appendChild(createDetailBlock("Detail", item));
        return;
    }
    
    // Hoist specific fields
    ['description', 'background', 'summary'].forEach(k => {
        if(item[k] && typeof item[k] === 'string' && !skipKeys.includes(k)) {
            container.appendChild(createDetailBlock(k, item[k]));
            skipKeys.push(k);
        }
    });
    
    Object.keys(item).forEach(key => {
        if(skipKeys.includes(key) || key === 'name' || key === 'raw') return;
        const val = item[key];
        
        // RE-ADDED: Array Handling Logic for Lists (e.g. Scenes, Beats)
        if (Array.isArray(val)) {
            if(val.length === 0) return;
            // Case 1: Simple strings (e.g. Keywords)
            if(typeof val[0] === 'string') {
                container.appendChild(createDetailBlock(key, val.join(', ')));
            } else {
                // Case 2: Complex Objects (e.g. Scene List)
                const sec = createSection(key);
                val.forEach(subItem => {
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'margin-bottom:15px; padding-left:15px; border-left:2px solid var(--border);';
                    renderGenericRecursive(wrapper, subItem);
                    sec.appendChild(wrapper);
                });
                container.appendChild(sec);
            }
        } 
        // RE-ADDED: Complex Object Handling (Tables vs Recursion)
        else if (typeof val === 'object' && val !== null) {
             // If object is small/flat, use table, else recurse
             const keys = Object.keys(val);
             if (keys.every(k => typeof val[k] !== 'object')) {
                 renderTable(container, key, val);
             } else {
                 container.appendChild(createSection(key));
                 renderGenericRecursive(container, val);
             }
        } 
        else {
            container.appendChild(createDetailBlock(key, val));
        }
    });
}

function createSection(title) {
    const d = document.createElement('div');
    d.className = 'detail-section';
    d.innerHTML = `<div class="section-head">${title.replace(/_/g, ' ')}</div>`;
    return d;
}

function createDetailBlock(label, content) {
    const div = document.createElement('div');
    div.style.marginBottom = '16px';
    div.innerHTML = `<div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; display:flex; justify-content:space-between;">
            <span>${label.replace(/_/g,' ')}</span>
            <span class="tts-select-icon" onclick="selectTextForTts(this,event)">🔈</span>
        </div>
        <div style="line-height:1.6; white-space:pre-wrap;">${renderTextWithFences(content)}</div>`;
    return div;
}

function renderTable(container, title, obj) {
    const sec = createSection(title);
    const table = document.createElement('table');
    table.className = 'kv-table';
    table.style.width = '100%';
    Object.keys(obj).forEach(k => {
        const row = document.createElement('tr');
        row.style.borderBottom = '1px solid rgba(0,0,0,0.05)';
        const val = typeof obj[k] === 'object' ? JSON.stringify(obj[k],null,2) : renderTextWithFences(String(obj[k]));
        row.innerHTML = `<td style="width:140px; padding:8px 0; color:var(--text-muted); font-weight:600;">${k.replace(/_/g,' ')}</td><td style="padding:8px 0 8px 12px;">${val}</td>`;
        table.appendChild(row);
    });
    sec.appendChild(table);
    container.appendChild(sec);
}

function formatComplexNote(obj) {
    let html = '';
    Object.keys(obj).forEach(k => {
        if (k === '__src_chunk') return;
        const val = obj[k];
        html += `<div style="margin-bottom:10px;"><strong style="text-transform:uppercase; font-size:0.8rem; color:var(--accent);">${k.replace(/_/g,' ')}:</strong> `;
        if (typeof val === 'object' && val !== null) {
            html += `<div style="padding-left:15px; border-left:2px solid rgba(255,255,255,0.1); margin-top:4px;">${formatComplexNote(val)}</div>`;
        } else {
            html += `<span style="color:var(--text);">${renderTextWithFences(String(val))}</span>`;
        }
        html += `</div>`;
    });
    return html;
}

function renderRawData(container, data) {
    const det = document.createElement('details');
    det.className = 'raw-foldable';
    det.style.marginTop = '20px';
    
    const summary = document.createElement('summary');
    summary.className = 'raw-summary';
    summary.style.display = 'flex';
    summary.style.justifyContent = 'space-between';
    summary.style.alignItems = 'center';
    
    const btn = document.createElement('button');
    btn.className = 'download-btn';
    btn.style.margin = '0';
    btn.innerHTML = '📋 Copy JSON';
    btn.onclick = (e) => {
        copyToClipboardAction(JSON.stringify(data, null, 2), btn, e);
    };
    
    const span = document.createElement('span');
    span.innerHTML = 'INSPECT RAW JSON';
    
    summary.appendChild(btn);
    summary.appendChild(span);
    
    const pre = document.createElement('div');
    pre.className = 'raw-content';
    pre.textContent = JSON.stringify(data, null, 2);
    
    det.appendChild(summary);
    det.appendChild(pre);
    container.appendChild(det);
}

// LINKING ENGINE - uses normalized lower-case keys
function linkify(text) {
    if(!text) return '';
    let out = String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    if(Object.keys(loreIndex).length === 0) return out;
    // match capitalized words or multi-word names; try to be conservative
    return out.replace(/([A-Z][a-zA-Z0-9\-\']+(?:\s[A-Z][a-zA-Z0-9\-\']+)*)/g, (match) => {
        const key = match.toLowerCase().trim();
        if(loreIndex[key]) {
            const entry = loreIndex[key];
            return `<a href="#" class="lore-link" onclick="clickLink('${escapeHtml(match)}'); return false;">${match}</a>`;
        }
        return match;
    });
}

function clickLink(name) {
    const key = name.toLowerCase().trim();
    if(loreIndex[key]) {
        const e = loreIndex[key];
        // Trigger generic openModal, which handles history recording
        openModal(e.docId, e.type, e.cat, e.idx);
    } else {
        console.warn("clickLink: key not indexed", key);
    }
}

function closeModal() {
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalWindow').style.display = 'none';
}

function escapeHtml(text) { return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }

// JS JSON Downloader
window.downloadEntityJSON = function(docId, type, category, event) {
    event.stopPropagation();
    
    const payloadEl = document.getElementById('payload-' + docId);
    if (!payloadEl) {
        alert('Data not found');
        return;
    }
    
    try {
        const data = JSON.parse(payloadEl.textContent);
        const categoryData = data[type] && data[type][category] ? data[type][category] : null;
        
        if (!categoryData) {
            alert('Category data not found');
            return;
        }
        
        const json = JSON.stringify(categoryData, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${docId}_${type}_${category}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error('Download failed:', e);
        alert('Download failed: ' + e.message);
    }
}

/* --------------------------------------
   NEW: Markdown converter + downloader
   Minimal, defensive, readable MD output
   -------------------------------------- */
window.downloadEntityMD = function(docId, type, category, event) {
    event.stopPropagation();

    const payloadEl = document.getElementById('payload-' + docId);
    if (!payloadEl) {
        alert('Data not found');
        return;
    }

    try {
        const data = JSON.parse(payloadEl.textContent);
        const categoryData = data[type] && data[type][category] ? data[type][category] : null;

        if (!categoryData) {
            alert('Category data not found');
            return;
        }

        const title = (data.meta && data.meta.name) ? data.meta.name : String(docId);
        let md = `# ${title}\n\n`;
        md += `**Source:** \`${docId}\`  \n`;
        md += `**Section:** ${type.toUpperCase()} / ${category.replace(/_/g,' ')}\n\n---\n\n`;

        // if it's an array, iterate
        if (Array.isArray(categoryData)) {
            categoryData.forEach((item, idx) => {
                md += `## Item ${idx + 1}\n\n`;
                md += renderItemToMd(item, 0);
                md += `\n---\n\n`;
            });
        } else {
            md += renderItemToMd(categoryData, 0);
        }

        const blob = new Blob([md], { type: 'text/markdown;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${docId}_${type}_${category}.md`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error('MD download failed:', e);
        alert('MD download failed: ' + e.message);
    }
};

// Render an arbitrary item (primitive / array / object) to Markdown
function renderItemToMd(item, depth) {
    const indent = '  '.repeat(depth);
    if (item === null || item === undefined) return `${indent}- *null*\n`;
    if (typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean') {
        return `${indent}${escapeMd(String(item))}\n`;
    }
    if (Array.isArray(item)) {
        // array of primitives?
        if (item.length === 0) return `${indent}- (empty array)\n`;
        if (item.every(v => (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean'))) {
            return item.map(v => `${indent}- ${escapeMd(String(v))}\n`).join('');
        }
        // complex objects
        return item.map((sub, i) => {
            let header = `${indent}### - Item ${i + 1}\n\n`;
            header += renderItemToMd(sub, depth + 1);
            return header;
        }).join('\n');
    }
    if (typeof item === 'object') {
        // prefer to show "name" or "title" as header if present
        let out = '';
        if (item.name || item.title) {
            out += `${indent}**${escapeMd(String(item.name || item.title))}**\n\n`;
        }
        // iterate keys in stable order: name/title/id first
        const keys = Object.keys(item).sort((a,b) => {
            const order =['name','title','id','type','description','logline','summary','core_conflict'];
            const ia = order.indexOf(a) === -1 ? 99 : order.indexOf(a);
            const ib = order.indexOf(b) === -1 ? 99 : order.indexOf(b);
            if (ia !== ib) return ia - ib;
            return a.localeCompare(b);
        });
        keys.forEach(k => {
            if (k === 'name' || k === 'title') return; // already printed
            const v = item[k];
            if (v === null || v === undefined) {
                out += `${indent}- **${escapeMd(k)}:** _null_\n`;
            } else if (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean') {
                // short strings -> inline
                const valStr = String(v);
                if (valStr.indexOf('\n') === -1 && valStr.length < 200) {
                    out += `${indent}- **${escapeMd(k)}:** ${escapeMd(valStr)}\n`;
                } else {
                    // use blockquote style for multi-line or long text (more readable than code block)
                    out += `\n${indent}#### ${escapeMd(k)}\n\n`;
                    // Create blockquote: prefix each line with '> '
                    const lines = valStr.split(/\r?\n/).map(line => `${indent}> ${escapeMd(line)}`).join('\n') + '\n\n';
                    out += lines;
                }
            } else if (Array.isArray(v)) {
                if (v.length === 0) {
                    out += `${indent}- **${escapeMd(k)}:** (empty)\n`;
                } else if (v.every(x => typeof x === 'string' || typeof x === 'number' || typeof x === 'boolean')) {
                    out += `${indent}- **${escapeMd(k)}:**\n`;
                    v.forEach(x => { out += `${indent}  - ${escapeMd(String(x))}\n`; });
                } else {
                    out += `\n${indent}#### ${escapeMd(k)}\n\n`;
                    v.forEach((sub, i) => {
                        out += `${indent}- Item ${i + 1}:\n`;
                        out += renderItemToMd(sub, depth + 2);
                    });
                    out += `\n`;
                }
            } else if (typeof v === 'object') {
                out += `\n${indent}#### ${escapeMd(k)}\n\n`;
                out += renderItemToMd(v, depth + 1);
                out += `\n`;
            } else {
                out += `${indent}- **${escapeMd(k)}:** ${escapeMd(String(v))}\n`;
            }
        });
        return out;
    }
    return `${indent}- ${escapeMd(String(item))}\n`;
}

// Minimal Markdown sanitizer for plain content
function escapeMd(s) {
    if (!s) return '';
    // escape backticks and leading hashes to keep headings safe
    return s.replace(/`/g, '``').replace(/^#+/gm, match => match.replace(/#/g, '\\#'));
}

// --- FRAME MODAL ---
function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}

/* wire up events */
document.getElementById('modalBackdrop').addEventListener('click', closeModal);
window.addEventListener('keydown', e => { 
    if(e.key === 'Escape') {
        const viewModal = document.getElementById('viewModal');
        if (viewModal && viewModal.classList.contains('active')) {
            closeFrameModal();
        } else {
            closeModal();
        }
    } 
});
window.addEventListener('DOMContentLoaded', init);
</script>

<?php if (!$isEmbed) require_once __DIR__ . '/mod_floating_tts.php'; ?>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle,
    $spw->getProjectPath() . '/templates/gallery.php'
);