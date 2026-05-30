<?php
// public/narrative_sequencer.php
// Showrunner V9 - Narrative Sequencer
// Features: Save/Load Fix, Server-Side Context, AJAX Pagination, Mobile Opt, Lightbox
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pageTitle = "Narrative Sequencer 🎬";

// --- 1. API HANDLERS (AJAX) ---

// Helper to format sketch data consistently
function formatSketchData($row) {
    $searchable = strtolower($row['name'] . ' ' . $row['description']);
    $ent = json_decode($row['entities'] ?? '{}', true);
    if($ent) $searchable .= ' ' . strtolower(json_encode($ent));
    
    $curation = [
        'id' => $row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'created_at' => $row['created_at'],
        'score' => (float)$row['overall_quality'],
        'class' => json_decode($row['classification'] ?? '{}', true),
        'score_breakdown' => json_decode($row['scoring'] ?? '{}', true),
        'entities' => $ent,
        'themes' => json_decode($row['thematics'] ?? '{}', true),
        'recs' => json_decode($row['recommendations'] ?? '{}', true),
        'show' => isset($row['showrunner_analysis']) ? json_decode($row['showrunner_analysis'], true) : []
    ];

    return [
        'id' => $row['id'],
        'name' => $row['name'],
        'desc' => $row['description'],
        'thumb' => $row['thumb'] ?? '/placeholder.png',
        'quality' => (float)$row['overall_quality'],
        'search_blob' => $searchable,
        'curation' => $curation 
    ];
}

// A. Fetch Library (Paginated + Context Scored)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_library') {
    header('Content-Type: application/json');
    try {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 50; 
        $contextId = isset($_GET['context_id']) && $_GET['context_id'] !== '' ? (int)$_GET['context_id'] : null;

        // Smart Scoring Logic
        if ($contextId) {
            $stmt = $pdo->prepare("SELECT entities, thematics FROM md_doc_analysis WHERE doc_id = ?");
            $stmt->execute([$contextId]);
            $docRow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $tags = [];
            if($docRow) {
                $ent = json_decode($docRow['entities'], true) ?? [];
                $them = json_decode($docRow['thematics'], true) ?? [];
                if (isset($ent['characters'])) foreach($ent['characters'] as $c) { if(isset($c['name'])) $tags[] = strtolower($c['name']); }
                if (isset($ent['locations'])) foreach($ent['locations'] as $l) { if(isset($l['name'])) $tags[] = strtolower($l['name']); }
                if (isset($them['mood'])) $tags[] = strtolower($them['mood']);
                if (isset($them['themes']) && is_array($them['themes'])) foreach($them['themes'] as $t) $tags[] = strtolower($t);
                $tags = array_unique(array_filter($tags));
            }

            // Score ALL sketches to determine page order
            $allSketches = $pdo->query("SELECT s.id, s.name, s.description, sa.entities, sa.thematics FROM sketches s JOIN sketch_analysis sa ON s.id = sa.sketch_id WHERE sa.overall_quality > 0")->fetchAll(PDO::FETCH_ASSOC);

            $scored = [];
            foreach($allSketches as $s) {
                $score = 0; $matches = [];
                $haystack = strtolower($s['name'] . ' ' . $s['description'] . ' ' . $s['entities'] . ' ' . $s['thematics']);
                foreach($tags as $tag) { if (strpos($haystack, $tag) !== false) { $score++; $matches[] = $tag; } }
                $s['score'] = $score; $s['matches'] = array_slice($matches, 0, 3);
                $scored[] = $s;
            }

            usort($scored, function($a, $b) { return $b['score'] <=> $a['score']; });

            $totalItems = count($scored);
            $totalPages = ceil($totalItems / $limit);
            $offset = ($page - 1) * $limit;
            $pageItems = array_slice($scored, $offset, $limit);
            
            $idsToFetch = []; $scoreMap = [];
            foreach($pageItems as $item) {
                $idsToFetch[] = $item['id'];
                $scoreMap[$item['id']] = ['score' => $item['score'], 'matches' => $item['matches']];
            }
        } else {
            // Standard Sort
            $countStmt = $pdo->query("SELECT COUNT(*) FROM sketch_analysis WHERE overall_quality > 0");
            $totalItems = $countStmt->fetchColumn();
            $totalPages = ceil($totalItems / $limit);
            $offset = ($page - 1) * $limit;

            $stmt = $pdo->prepare("SELECT sketch_id as id FROM sketch_analysis WHERE overall_quality > 0 ORDER BY analyzed_at DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $idsToFetch = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $scoreMap = [];
        }

        if(empty($idsToFetch)) {
            echo json_encode(['status'=>'success', 'data'=>[], 'meta'=>['current_page'=>$page, 'total_pages'=>0]]);
            exit;
        }

        // Hydrate Page Items
        $placeholders = str_repeat('?,', count($idsToFetch) - 1) . '?';
        $sql = "
            SELECT s.id, s.name, s.description, s.created_at, sa.overall_quality, sa.entities, sa.thematics, sa.classification, sa.scoring, sa.recommendations,
                   (SELECT filename FROM frames WHERE entity_type='sketches' AND entity_id=s.id ORDER BY id DESC LIMIT 1) as thumb
            FROM sketches s
            JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE s.id IN ($placeholders)
            ORDER BY FIELD(s.id, " . implode(',', $idsToFetch) . ")";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($idsToFetch);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $library = [];
        foreach($rows as $r) {
            $formatted = formatSketchData($r);
            $matchInfo = $scoreMap[$r['id']] ?? ['score'=>0, 'matches'=>[]];
            
            $formatted['isMatch'] = $matchInfo['score'] > 0;
            $formatted['matches'] = $matchInfo['matches'];
            
            $library[] = $formatted;
        }

        echo json_encode(['status' => 'success', 'data' => $library, 'meta' => ['current_page' => $page, 'total_pages' => $totalPages, 'total_items' => $totalItems]]);
        exit;
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit; }
}

// B. Hydrate Sequence Items (Full Data for Timeline)
if (isset($_GET['action']) && $_GET['action'] === 'hydrate_sequence') {
    header('Content-Type: application/json');
    try {
        $idsStr = $_GET['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $idsStr)));
        
        if (empty($ids)) { echo json_encode(['status'=>'success', 'data'=>[]]); exit; }

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $sql = "
            SELECT s.id, s.name, s.description, s.created_at, sa.overall_quality, sa.entities, sa.thematics, sa.classification, sa.scoring, sa.recommendations,
                   (SELECT filename FROM frames WHERE entity_type='sketches' AND entity_id=s.id ORDER BY id DESC LIMIT 1) as thumb
            FROM sketches s
            JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE s.id IN ($placeholders)
            ORDER BY FIELD(s.id, " . implode(',', $ids) . ")";
            
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach($rows as $r) {
            $data[] = formatSketchData($r);
        }
        echo json_encode(['status'=>'success', 'data'=>$data]);
        exit;
    } catch (Exception $e) { echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]); exit; }
}

// C. Save Sequence
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'save_sequence') {
            $name = $_POST['name'] ?? 'Untitled Sequence';
            $desc = $_POST['description'] ?? '';
            $ids = json_decode($_POST['sketch_ids'] ?? '[]', true);
            $ids = array_filter($ids); // Remove any nulls
            $docId = !empty($_POST['linked_doc_id']) ? $_POST['linked_doc_id'] : null;
            $seqId = !empty($_POST['sequence_id']) ? $_POST['sequence_id'] : null;

            if ($seqId) {
                $stmt = $pdo->prepare("UPDATE narrative_sequences SET name=?, description=?, sequence_data=?, linked_doc_id=? WHERE id=?");
                $stmt->execute([$name, $desc, json_encode(array_values($ids)), $docId, $seqId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $desc, json_encode(array_values($ids)), $docId]);
                $seqId = $pdo->lastInsertId();
            }
            echo json_encode(['status' => 'success', 'id' => $seqId]);
        }
        exit;
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit; }
}

// --- 3. PAGE RENDER ---

$docsRaw = $pdo->query("SELECT d.id, d.name, da.entities, da.thematics, da.narrative_utility FROM documentations d JOIN md_doc_analysis da ON d.id = da.doc_id WHERE da.narrative_utility IS NOT NULL ORDER BY da.narrative_utility DESC")->fetchAll(PDO::FETCH_ASSOC);
$contextDocs = [];
foreach ($docsRaw as $d) {
    $tags = [];
    $ent = json_decode($d['entities'], true) ?? [];
    $them = json_decode($d['thematics'], true) ?? [];
    if (isset($ent['characters'])) foreach($ent['characters'] as $c) { if(isset($c['name'])) $tags[] = $c['name']; }
    if (isset($ent['locations'])) foreach($ent['locations'] as $l) { if(isset($l['name'])) $tags[] = $l['name']; }
    if (isset($them['mood'])) $tags[] = $them['mood'];
    $contextDocs[] = ['id' => $d['id'], 'name' => $d['name'], 'tags' => array_values(array_unique(array_filter($tags)))];
}

$sequences = $pdo->query("SELECT * FROM narrative_sequences ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!-- Dependencies -->
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<!-- Swiper -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<!-- PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>

<style>
    :root { --film-bg: #050505; --highlight: #8b5cf6; --accent-glow: rgba(139, 92, 246, 0.4); --handle-color: #f59e0b; }
    html, body { overflow: hidden; height: 100%; margin:0; }
    .sequencer-layout { display: flex; flex-direction: column; height: 100vh; width: 100vw; background: var(--bg); overflow: hidden; }

    /* Top: Timeline (30%) */
    .timeline-area { flex: 0 0 30%; background: var(--film-bg); border-bottom: 4px solid var(--border); display: flex; flex-direction: column; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.5); z-index: 10; }
    .timeline-header { padding: 10px 15px; background: rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); height: 50px; }
    .timeline-track-container { flex: 1; overflow-x: auto; overflow-y: hidden; display: flex; align-items: center; padding: 0 15px; background-image: linear-gradient(90deg, transparent 50%, rgba(255,255,255,0.05) 50%), linear-gradient(transparent 50%, rgba(0,0,0,0.5) 50%); background-size: 20px 100%, 100% 4px; }
    .film-strip-list { display: flex; gap: 8px; height: 100%; align-items: center; min-width: 100%; padding: 10px 0; }
    .seq-title-display { font-weight: 700; color: var(--text); font-size: 1rem; opacity: 0.8; }

    /* Frame */
    .film-frame { height: 110px; aspect-ratio: 16/9; background: #000; border: 2px solid #444; border-radius: 6px; flex-shrink: 0; position: relative; cursor: grab; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s; }
    .film-frame:active { cursor: grabbing; transform: scale(0.95); }
    .film-frame img { width: 100%; height: 100%; object-fit: cover; opacity: 1; }
    .remove-frame { position: absolute; top: 4px; right: 4px; background: rgba(220, 38, 38, 0.9); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.5); }
    .frame-ord { position: absolute; bottom: 4px; left: 4px; background: rgba(0,0,0,0.7); color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-family: monospace; }

    /* Middle: Controls */
    .control-strip { padding: 10px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; gap: 10px; align-items: center; flex-shrink: 0; height: 60px; }
    .context-select { padding: 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); flex:1; max-width: 250px; font-weight: 700; }
    .context-tags { flex: 1; overflow-x: auto; display: flex; gap: 6px; align-items: center; padding-right: 10px; scrollbar-width: none; }
    .context-tag { font-size: 0.7rem; white-space: nowrap; background: rgba(139, 92, 246, 0.1); color: var(--highlight); padding: 4px 8px; border-radius: 12px; border: 1px solid rgba(139, 92, 246, 0.2); }
    .btn-icon { padding: 8px 12px; border-radius: 6px; cursor: pointer; border: 1px solid var(--border); background: var(--bg); display: flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 0.9rem; color: var(--text); transition: background 0.2s; }
    .btn-icon:hover { background: var(--card); }

    /* Bottom: Library */
    .library-area { flex: 1; background: var(--bg); overflow: hidden; position: relative; display: flex; flex-direction: column; min-height: 0; }
    .pagination-bar { flex: 0 0 50px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: center; gap: 15px; padding: 0 20px; z-index: 30; }
    .p-btn { background: transparent; border: 1px solid var(--border); color: var(--text); width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s ease; }
    .p-btn:hover:not(:disabled) { background: var(--highlight); border-color: var(--highlight); color: white; }
    .p-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .p-input-wrapper { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: var(--text-muted); background: var(--bg); padding: 4px 12px; border-radius: 20px; border: 1px solid var(--border); }
    .p-input { width: 40px; background: transparent; border: none; border-bottom: 1px solid var(--text-muted); color: var(--highlight); font-weight: 700; text-align: center; font-size: 1rem; padding: 2px 0; }
    .p-input:focus { outline: none; border-bottom-color: var(--highlight); }

    .lib-swiper { width: 100%; flex: 1; min-height: 0; padding: 20px 0; display: block; opacity: 0; transition: opacity 0.3s; }
    .swiper-slide { width: 280px; height: auto; display: flex; flex-direction: column; justify-content: center; transition: transform 0.3s; align-self: flex-start; }
    
    .loading-state { position: absolute; top: 50px; bottom: 0; left: 0; right: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 50; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
    .spinner { width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top-color: var(--highlight); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; }
    @keyframes spin { 100% { transform: rotate(360deg); } }

    /* Card */
    .lib-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; flex-direction: column; position: relative; height: auto; }
    .lib-card.match { border-color: var(--highlight); box-shadow: 0 0 15px var(--accent-glow); transform: translateY(-2px); }
    .lib-thumb { width: 100%; aspect-ratio: 16/9; background: #000; position: relative; flex-shrink: 0; cursor: zoom-in; }
    .lib-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .drag-handle { position: absolute; top: 8px; right: 8px; width: 32px; height: 32px; border-radius: 8px; background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(4px); color: rgba(255, 255, 255, 0.7); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: grab; border: 1px solid rgba(255,255,255,0.1); z-index: 20; transition: all 0.2s; }
    .drag-handle:hover { background: var(--highlight); color: #fff; border-color: transparent; }
    .drag-handle:active { cursor: grabbing; transform: scale(0.95); }
    .lib-meta { padding: 10px; flex: 1; display: flex; flex-direction: column; justify-content:space-between; }
    .lib-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color:var(--text); }
    .lib-id { font-size: 0.7rem; color: var(--text-muted); font-family:monospace; margin-bottom: 0; }
    .match-reason { font-size: 0.7rem; color: var(--highlight); margin-top: 4px; display:block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lib-actions { display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid var(--border); gap: 8px; margin-top: 8px; }
    .action-btn { flex: 1; font-size: 0.8rem; padding: 6px 0; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content:center; gap: 6px; background: var(--bg); color: var(--text); transition: background 0.2s; }
    .action-btn:hover { background: var(--accent-subtle); }
    .action-btn.green { border-color: rgba(16, 185, 129, 0.3); color: #10b981; background: rgba(16, 185, 129, 0.05); }

    /* Player */
    .player-modal { display: none; position: fixed; inset: 0; background: #000; z-index: 3000; }
    .player-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 2.5rem; z-index: 3005; cursor: pointer; opacity: 0.8; text-shadow: 0 2px 5px #000; }
    .player-swiper .swiper-slide { width: 100%; height: 100%; background: #000; display: flex; justify-content: center; align-items: center; position: relative; }
    .player-img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .player-controls { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); display: flex; gap: 15px; z-index: 3002; }
    .player-btn { background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 10px 20px; border-radius: 30px; cursor: pointer; backdrop-filter: blur(6px); font-weight: 700; display: flex; gap: 8px; align-items: center; transition: all 0.2s; font-size: 0.9rem; }
    .player-btn:hover { background: rgba(255,255,255,0.1); transform: scale(1.05); border-color: #fff; }
    .player-btn.green { border-color: rgba(16, 185, 129, 0.6); color: #6ee7b7; background: rgba(6, 78, 59, 0.6); }
    .swiper-button-next, .swiper-button-prev { color: var(--highlight); text-shadow: 0 2px 4px #000; }

    /* Modals & Forms */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-content { background: var(--card); width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto; padding: 25px; border-radius: 12px; position: relative; border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
    .modal-close { position: absolute; top: 15px; right: 15px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted); line-height: 1; }
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; color: var(--text-muted); text-transform: uppercase; }
    .form-input { width: 100%; padding: 10px; border: 1px solid var(--border); background: var(--bg); color: var(--text); border-radius: 6px; font-size: 1rem; }
    .form-input:focus { outline: none; border-color: var(--highlight); }
    .form-textarea { height: 100px; resize: vertical; }
    .form-btn { width: 100%; padding: 12px; background: var(--highlight); color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 1rem; }
    
    .modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; }
    .modal-label { width: 100px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); flex-shrink: 0; }
    .pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin: 2px; }
    .pill-theme { color: #8b5cf6; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.2); }
    .pill-char { color: #f59e0b; background: rgba(245,159,11,0.1); border: 1px solid rgba(245,159,11,0.2); }
    .pill-func { color: #7c3aed; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.2); }
    .score-badge { font-weight: 800; font-size: 1.2rem; padding: 4px 10px; border-radius: 6px; color: #fff; }
    .score-high { background: #10b981; } .score-mid { background: #f59e0b; } .score-low { background: #ef4444; }
    
    .load-list { max-height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
    .load-item { padding: 12px; border: 1px solid var(--border); background: var(--bg); border-radius: 6px; cursor: pointer; transition: 0.2s; }
    .load-item:hover { border-color: var(--highlight); background: var(--accent-subtle); }
    .load-name { font-weight: 700; font-size: 1.1rem; color: var(--text); }
    .load-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; display: flex; justify-content: space-between; }
</style>

<div class="sequencer-layout">
    <!-- 1. TIMELINE AREA -->
    <div class="timeline-area">
        <div class="timeline-header">
            <div id="seqNameDisplay" class="seq-title-display">Untitled Sequence</div>
            <div style="display:flex; gap:10px;">
                <button class="btn-icon" onclick="playSequence()">▶ Play</button>
                <button class="btn-icon" onclick="openSaveModal()">💾 Save</button>
                <button class="btn-icon" onclick="openLoadModal()">📂 Load</button>
            </div>
        </div>
        <div class="timeline-track-container">
            <div class="film-strip-list" id="timelineSortable">
                <div style="color:rgba(255,255,255,0.2); font-size:0.9rem; padding:0 20px; pointer-events:none;" id="emptyMsg">Drag items here</div>
            </div>
        </div>
    </div>

    <!-- 2. CONTROL STRIP -->
    <div class="control-strip">
        <select id="contextDoc" class="context-select">
            <option value="">-- No Context (All) --</option>
            <?php foreach($contextDocs as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div id="contextTagCloud" class="context-tags"></div>
    </div>

    <!-- 3. LIBRARY AREA -->
    <div class="library-area">
        <div class="pagination-bar" id="paginationBar">
            <button class="p-btn" id="btnPrev" onclick="changePage(-1)" disabled title="Previous Page">←</button>
            <div class="p-input-wrapper">
                <span>Page</span>
                <input type="number" id="pageInput" class="p-input" value="1" onchange="jumpToPage(this.value)">
                <span id="pageTotalLabel">of ...</span>
            </div>
            <button class="p-btn" id="btnNext" onclick="changePage(1)" disabled title="Next Page">→</button>
        </div>

        <div id="loadingState" class="loading-state">
            <div class="spinner"></div>
            <div style="font-size:0.9rem; color:var(--text-muted);">Scanning Database...</div>
        </div>

        <div class="swiper lib-swiper" id="mainSwiper">
            <div class="swiper-wrapper" id="libWrapper"></div>
            <div class="swiper-scrollbar"></div>
        </div>
    </div>
</div>

<!-- MODALS -->
<div id="curation-modal" class="modal-overlay">
    <div class="modal-content"><span class="modal-close" onclick="$('#curation-modal').hide()">&times;</span><div id="curation-modal-body"></div></div>
</div>
<div id="desc-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:500px;"><span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span><h3 id="desc-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;"></h3><div id="desc-body" style="font-family:serif; font-size:1.1rem; line-height:1.6; white-space:pre-wrap;"></div></div>
</div>
<div id="save-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:500px;"><span class="modal-close" onclick="$('#save-modal').hide()">&times;</span><h3 style="margin-top:0;">Save Sequence</h3><div class="form-group"><label class="form-label">Sequence Name</label><input type="text" id="saveNameInput" class="form-input" placeholder="e.g. Heist Sequence V1"></div><div class="form-group"><label class="form-label">Editorial Description</label><textarea id="saveDescInput" class="form-input form-textarea" placeholder="Director's notes..."></textarea></div><button class="form-btn" onclick="performSave()">Save Sequence</button></div>
</div>
<div id="load-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:600px;"><span class="modal-close" onclick="$('#load-modal').hide()">&times;</span><h3 style="margin-top:0; margin-bottom:20px;">Load Sequence</h3><div id="loadList" class="load-list"><?php foreach($sequences as $seq): ?><div class="load-item" onclick='performLoad(<?= json_encode($seq) ?>)'><div class="load-name"><?= htmlspecialchars($seq['name']) ?></div><div class="load-meta"><span><?= date('M d, Y', strtotime($seq['updated_at'])) ?></span><span><?= count(json_decode($seq['sequence_data'])) ?> clips</span></div></div><?php endforeach; ?></div></div>
</div>
<div id="playerModal" class="player-modal">
    <div class="player-close" onclick="closePlayer()">✕</div><div class="swiper player-swiper"><div class="swiper-wrapper" id="playerSlides"></div><div class="swiper-button-next"></div><div class="swiper-button-prev"></div><div class="swiper-pagination"></div></div>
</div>

<script>
    // GLOBAL DATA
    let currentLibraryPage = [];
    let sketchRegistry = {};
    const contextData = <?= json_encode($contextDocs) ?>;
    let currentSeqId = null;
    let libSwiper = null;
    let playerSwiper = null;
    let photoSwipeLightbox = null;
    let currentPage = 1;
    let totalPages = 1;

    document.addEventListener('DOMContentLoaded', () => {
        if(typeof PhotoSwipeLightbox !== 'undefined') {
            photoSwipeLightbox = new PhotoSwipeLightbox({
                gallery: '#libWrapper', children: 'a.pswp-link', pswpModule: PhotoSwipe
            });
            photoSwipeLightbox.init();
        }

        new Sortable(document.getElementById('timelineSortable'), {
            group: 'shared', animation: 150, direction: 'horizontal',
            onAdd: function (evt) {
                const item = evt.item; const d = item.dataset;
                const id = d.id || item.getAttribute('data-id');
                item.className = 'film-frame';
                item.dataset.id = id; item.dataset.name = d.name; item.dataset.desc = d.desc;
                item.innerHTML = `<img src="${d.thumb}"><div class="remove-frame" onclick="this.parentElement.remove(); updateOrd();">✕</div><div class="frame-ord"></div>`;
                item.style.width = ''; item.style.transform = '';
                document.getElementById('emptyMsg').style.display = 'none'; updateOrd();
            }, onUpdate: updateOrd
        });

        document.getElementById('contextDoc').addEventListener('change', () => {
            currentPage = 1;
            loadLibraryPage(1);
        });
        
        loadLibraryPage(1);
    });

    function loadLibraryPage(page) {
        document.getElementById('loadingState').style.display = 'flex';
        document.getElementById('mainSwiper').style.opacity = '0.3';
        const docId = document.getElementById('contextDoc').value;
        const url = `?action=fetch_library&page=${page}&context_id=${docId}`;
        
        const cloud = document.getElementById('contextTagCloud');
        if(docId) {
            const doc = contextData.find(d => d.id == docId);
            if(doc) {
                cloud.innerHTML = doc.tags.slice(0, 10).map(t => `<span class="context-tag">${t}</span>`).join('');
            } else {
                cloud.innerHTML = '';
            }
        } else {
            cloud.innerHTML = '';
        }

        fetch(url).then(r => r.json()).then(res => {
            if(res.status === 'success') {
                currentLibraryPage = res.data;
                currentPage = parseInt(res.meta.current_page);
                totalPages = parseInt(res.meta.total_pages);
                currentLibraryPage.forEach(item => { sketchRegistry[item.id] = item; });
                renderLibrary(currentLibraryPage);
                updatePaginationUI();
            } else { alert("Error: " + res.message); }
        }).finally(() => {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('mainSwiper').style.opacity = '1';
        });
    }

    function updatePaginationUI() {
        document.getElementById('pageInput').value = currentPage;
        document.getElementById('pageTotalLabel').innerText = `of ${totalPages}`;
        document.getElementById('btnPrev').disabled = (currentPage <= 1);
        document.getElementById('btnNext').disabled = (currentPage >= totalPages);
    }

    function changePage(delta) {
        const target = currentPage + delta;
        if(target >= 1 && target <= totalPages) loadLibraryPage(target);
    }

    function jumpToPage(val) {
        let p = parseInt(val); if(isNaN(p)) p = 1; if(p < 1) p = 1; if(p > totalPages) p = totalPages;
        loadLibraryPage(p);
    }

    function updateOrd() { document.querySelectorAll('.frame-ord').forEach((el, i) => el.innerText = i + 1); }

    function renderLibrary(items) {
        const wrapper = document.getElementById('libWrapper');
        wrapper.innerHTML = '';
        items.forEach(s => {
            const slide = document.createElement('div'); slide.className = 'swiper-slide';
            slide.setAttribute('data-id', s.id);
            slide.dataset.id = s.id; slide.dataset.thumb = s.thumb; slide.dataset.name = s.name; slide.dataset.desc = s.desc;
            let matchHtml = '';
            if (s.isMatch && s.matches && s.matches.length > 0) {
                matchHtml = `<span class="match-reason">Matches: ${s.matches.slice(0, 3).join(', ')}</span>`;
            }
            slide.innerHTML = `<div class="lib-card ${s.isMatch ? 'match' : ''}"><div class="lib-thumb"><a href="${s.thumb}" class="pswp-link" data-pswp-width="1024" data-pswp-height="1024" target="_blank"><img src="${s.thumb}" loading="lazy"></a><div class="drag-handle" title="Drag to Timeline">⠿</div></div><div class="lib-meta"><div><div class="lib-title">${s.name}</div><div class="lib-id">#${s.id}</div>${matchHtml}</div><div class="lib-actions"><button class="action-btn" onclick="openDesc(${s.id})">📖 Read</button><button class="action-btn green" onclick="openAnalysis(${s.id})">🕵️ Analysis</button></div></div></div>`;
            wrapper.appendChild(slide);
        });
        if (libSwiper) libSwiper.destroy();
        libSwiper = new Swiper('.lib-swiper', { slidesPerView: 'auto', spaceBetween: 20, centeredSlides: true, scrollbar: { el: '.swiper-scrollbar' }, freeMode: true, mousewheel: true, observer: true, observeParents: true });
        new Sortable(wrapper, { group: { name: 'shared', pull: 'clone', put: false }, sort: false, handle: '.drag-handle', onClone: function(evt) { const s = evt.item; evt.clone.setAttribute('data-id', s.dataset.id); evt.clone.dataset.id = s.dataset.id; evt.clone.dataset.thumb = s.dataset.thumb; evt.clone.dataset.name = s.dataset.name; evt.clone.dataset.desc = s.dataset.desc; } });
        if(photoSwipeLightbox) photoSwipeLightbox.init();
    }

    // --- MODALS ---
    window.openAnalysis = function(id) {
        const item = sketchRegistry[id];
        if(!item || !item.curation) { Toast.show("Data not loaded", "error"); return; }
        const data = item.curation; const body = document.getElementById('curation-modal-body');
        const scoreClass = data.score >= 8 ? 'score-high' : (data.score >= 5 ? 'score-mid' : 'score-low');
        let html = `<div style="margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:15px;"><div style="display:flex; justify-content:space-between; align-items:start;"><div><h2 style="margin:0; font-size:1.4em;">${data.name}</h2><div style="font-size:0.85em; color:var(--text-muted); margin-top:4px;">#${data.id}</div></div><div class="score-badge ${scoreClass}">${data.score}</div></div></div>`;
        if(data.class) html += `<div class="modal-row"><span class="modal-label">Class</span><div><span class="pill pill-func">${data.class.narrative_function}</span> <span class="pill">${data.class.emotional_tone}</span></div></div>`;
        if(data.themes && data.themes.primary_themes) html += `<div class="modal-row"><span class="modal-label">Themes</span><div>` + (Array.isArray(data.themes.primary_themes)?data.themes.primary_themes:[data.themes.primary_themes]).map(t=>`<span class="pill pill-theme">${t}</span>`).join(' ') + `</div></div>`;
        if(data.entities && data.entities.characters) html += `<div class="modal-row"><span class="modal-label">Cast</span><div>` + data.entities.characters.map(c=>`<span class="pill pill-char">👤 ${c}</span>`).join(' ') + `</div></div>`;
        if(data.recs && data.recs.potential_use) html += `<div style="margin:15px 0; background:rgba(245,159,11,0.1); padding:12px; border-radius:6px; border:1px dashed rgba(245,159,11,0.4);"><span class="modal-label" style="color:#d97706; margin-bottom:5px; display:block;">💡 Potential Use</span><div style="font-size:0.95em;">${data.recs.potential_use}</div></div>`;
        body.innerHTML = html; $('#curation-modal').css('display', 'flex');
    };

    window.openDesc = function(id) {
        const item = sketchRegistry[id]; if(!item) return;
        document.getElementById('desc-title').innerText = item.name;
        document.getElementById('desc-body').innerText = item.desc;
        $('#desc-modal').css('display', 'flex');
    };

    window.openSaveModal = function() { if(document.querySelectorAll('#timelineSortable .film-frame').length === 0) { Toast.show("Timeline empty", "error"); return; } $('#save-modal').css('display', 'flex'); };
    window.openLoadModal = function() { $('#load-modal').css('display', 'flex'); };

    window.performSave = function() {
        const frames = document.querySelectorAll('#timelineSortable .film-frame');
        const ids = Array.from(frames).map(el => el.dataset.id || el.getAttribute('data-id')).filter(id => id);
        if(ids.length === 0) { Toast.show("Error: No valid items to save", "error"); return; }
        const name = document.getElementById('saveNameInput').value || 'Untitled Sequence';
        const desc = document.getElementById('saveDescInput').value;
        const docId = document.getElementById('contextDoc').value;
        const formData = new FormData();
        formData.append('action', 'save_sequence'); formData.append('name', name); formData.append('description', desc); formData.append('sketch_ids', JSON.stringify(ids)); formData.append('linked_doc_id', docId);
        if(currentSeqId) formData.append('sequence_id', currentSeqId);
        fetch('', { method: 'POST', body: formData }).then(r => r.json()).then(d => {
            if(d.status === 'success') { Toast.show("Saved!"); currentSeqId = d.id; document.getElementById('seqNameDisplay').innerText = name; $('#save-modal').hide(); } else { Toast.show(d.message, "error"); }
        });
    };

    window.performLoad = function(seqData) {
        currentSeqId = seqData.id;
        document.getElementById('seqNameDisplay').innerText = seqData.name;
        document.getElementById('saveNameInput').value = seqData.name;
        document.getElementById('saveDescInput').value = seqData.description;
        
        if(seqData.linked_doc_id) {
            document.getElementById('contextDoc').value = seqData.linked_doc_id;
            // FIX: Use correct function to refresh context
            currentPage = 1;
            loadLibraryPage(1);
        }

        const ids = JSON.parse(seqData.sequence_data);
        if(ids && ids.length > 0) {
            const cleanIds = ids.filter(id => id);
            if(cleanIds.length === 0) return;

            fetch('?action=hydrate_sequence&ids=' + cleanIds.join(','))
                .then(r => r.json())
                .then(res => {
                    if(res.status === 'success') {
                        const track = document.getElementById('timelineSortable'); track.innerHTML = '';
                        res.data.forEach(item => {
                            const el = document.createElement('div'); el.className = 'film-frame'; 
                            el.dataset.id = item.id; el.dataset.name = item.name; el.dataset.desc = item.desc;
                            el.innerHTML = `<img src="${item.thumb}"><div class="remove-frame" onclick="this.parentElement.remove(); updateOrd();">✕</div><div class="frame-ord"></div>`;
                            track.appendChild(el);
                            sketchRegistry[item.id] = item;
                        });
                        document.getElementById('emptyMsg').style.display = 'none'; 
                        updateOrd();
                    }
                });
        }
        $('#load-modal').hide();
    };

    function playSequence() {
        const frames = document.querySelectorAll('#timelineSortable .film-frame');
        if(frames.length === 0) return;
        const wrap = document.getElementById('playerSlides'); wrap.innerHTML = '';
        frames.forEach(el => {
            const src = el.querySelector('img').src; const id = el.dataset.id;
            wrap.innerHTML += `<div class="swiper-slide"><img src="${src}" class="player-img"><div class="player-controls"><button class="player-btn" onclick="openDesc(${id})">📖 Info</button><button class="player-btn green" onclick="openAnalysis(${id})">🕵️ Analysis</button></div></div>`;
        });
        document.getElementById('playerModal').style.display = 'block';
        if(playerSwiper) playerSwiper.destroy();
        playerSwiper = new Swiper('.player-swiper', { navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }, keyboard: true });
    }
    
    function closePlayer() { document.getElementById('playerModal').style.display = 'none'; }
    window.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) $(e.target).hide(); });
    document.getElementById('pageInput').addEventListener("keypress", function(e) { if (e.key === "Enter") jumpToPage(this.value); });
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');