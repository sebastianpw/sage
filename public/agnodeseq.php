<?php
// public/agnodeseq.php
// Showrunner - The Narrative Gallery (AG Node Edition)

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

function firstExistingValue(array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) return $row[$key];
    }
    return $default;
}

function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = firstExistingValue($row, ['thumb','thumbnail','image','image_url','image_path','file_path','path','src','url','filename','file_name'], '');
    if (is_string($candidate) && $candidate !== '') return $candidate;
    if ($frameId > 0) return 'view_frame.php?frame_id=' . $frameId;
    return '';
}

$seqId = $_GET['id'] ?? null;
$table = 'narrative_sequences';

$seq = null;
if ($seqId) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$seqId]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $seq = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$seq) die("<div style='background:#050508; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Sequence not found.</div>");

// Prev/Next Nav
$stmtPrev = $pdo->prepare("SELECT id FROM $table WHERE id < ? ORDER BY id DESC LIMIT 1");
$stmtPrev->execute([$seq['id']]);
$prevSeqId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare("SELECT id FROM $table WHERE id > ? ORDER BY id ASC LIMIT 1");
$stmtNext->execute([$seq['id']]);
$nextSeqId = $stmtNext->fetchColumn();

// Parse items
$itemIds = json_decode($seq['sequence_data'] ?? '[]', true);
if (!is_array($itemIds)) $itemIds = [];

$agNodeIdsInOrder = [];
$sketchIdsInOrder = [];
$frameIdsInOrder = [];

foreach ($itemIds as $idx => $item) {
    if (is_array($item)) {
        $agNodeIdsInOrder[$idx] = !empty($item['ag_node_id']) ? (int)$item['ag_node_id'] : (!empty($item['kg_node_id']) ? (int)$item['kg_node_id'] : null);
        $sketchIdsInOrder[$idx] = !empty($item['sketch_id']) ? (int)$item['sketch_id'] : null;
        $frameIdsInOrder[$idx]  = !empty($item['frame_id']) ? (int)$item['frame_id'] : null;
    } else {
        $agNodeIdsInOrder[$idx] = null;
        $sketchIdsInOrder[$idx] = (int)$item ?: null;
        $frameIdsInOrder[$idx]  = null;
    }
}

// Fallback: Resolve missing ag_node_id via sketch_lore_history matching ag_nodes.name
$sketchIdsNeedingLookup = array_values(array_unique(array_filter(
    array_map(function ($idx) use ($agNodeIdsInOrder, $sketchIdsInOrder) {
        return (empty($agNodeIdsInOrder[$idx]) && !empty($sketchIdsInOrder[$idx])) ? $sketchIdsInOrder[$idx] : null;
    }, array_keys($itemIds))
)));

$agNodeIdBySketchId = [];
if (!empty($sketchIdsNeedingLookup)) {
    $inClause = implode(',', array_fill(0, count($sketchIdsNeedingLookup), '?'));
    $stmtLookup = $pdo->prepare(
        "SELECT slh.sketch_id, n.id AS ag_node_id
         FROM sketch_lore_history slh
         JOIN ag_nodes n ON LOWER(n.name) = LOWER(slh.entity_name)
         WHERE slh.sketch_id IN ($inClause)
         ORDER BY slh.id DESC"
    );
    $stmtLookup->execute($sketchIdsNeedingLookup);
    foreach ($stmtLookup->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isset($agNodeIdBySketchId[(int)$row['sketch_id']])) {
            $agNodeIdBySketchId[(int)$row['sketch_id']] = (int)$row['ag_node_id'];
        }
    }
    foreach ($agNodeIdsInOrder as $idx => $val) {
        if (empty($val) && !empty($sketchIdsInOrder[$idx]) && isset($agNodeIdBySketchId[$sketchIdsInOrder[$idx]])) {
            $agNodeIdsInOrder[$idx] = $agNodeIdBySketchId[$sketchIdsInOrder[$idx]];
        }
    }
}

$pureAgNodeIds = array_values(array_unique(array_filter($agNodeIdsInOrder)));

// Load AG Nodes
$agNodesData = [];
if (!empty($pureAgNodeIds)) {
    $inClause = implode(',', array_fill(0, count($pureAgNodeIds), '?'));
    $stmtN = $pdo->prepare("SELECT * FROM ag_nodes WHERE id IN ($inClause)");
    $stmtN->execute($pureAgNodeIds);
    foreach ($stmtN->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $agNodesData[(int)$row['id']] = $row;
    }
}

$pureSketchIds = array_values(array_unique(array_filter($sketchIdsInOrder)));

// Load Frames
$activeFrameIds = array_values(array_unique(array_filter($frameIdsInOrder)));
$selectedFrameMap = [];
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fid = (int)$row['id'];
        $selectedFrameMap[$fid] = ['row' => $row, 'thumb' => resolveFrameThumb($row, $fid)];
    }
}

// Fallback: Latest Frame by Sketch
$latestFrameBySketch = [];
if (!empty($pureSketchIds)) {
    $inClauseFb = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtFb = $pdo->prepare(
        "SELECT f.*, f.entity_id AS _sketch_id
         FROM frames f
         INNER JOIN frames_2_sketches m ON m.from_id = f.id
         WHERE f.entity_id IN ($inClauseFb)
         ORDER BY f.id DESC"
    );
    $stmtFb->execute($pureSketchIds);
    foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchId = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sketchId])) {
            $fid = (int)$row['id'];
            $latestFrameBySketch[$sketchId] = ['frame_id' => $fid, 'row' => $row, 'thumb' => resolveFrameThumb($row, $fid)];
        }
    }
}

$frames = [];
foreach ($itemIds as $idx => $item) {
    $agNodeId = $agNodeIdsInOrder[$idx] ?? null;
    if (empty($agNodeId) || !isset($agNodesData[$agNodeId])) continue;

    $nodeRow = $agNodesData[$agNodeId];
    $explicitFrameId = $frameIdsInOrder[$idx] ?? null;
    $itemSketchId = $sketchIdsInOrder[$idx] ?? null;

    $activeThumb = '';
    $activeFrameId = null;

    if ($explicitFrameId && isset($selectedFrameMap[$explicitFrameId])) {
        $activeThumb = $selectedFrameMap[$explicitFrameId]['thumb'];
        $activeFrameId = $explicitFrameId;
    } elseif ($itemSketchId && isset($latestFrameBySketch[$itemSketchId])) {
        $activeThumb = $latestFrameBySketch[$itemSketchId]['thumb'];
        $activeFrameId = $latestFrameBySketch[$itemSketchId]['frame_id'];
    }

    $frames[] = [
        'id' => $agNodeId,
        'doc_id' => $nodeRow['doc_id'],
        'name' => firstExistingValue($nodeRow, ['name'], 'Untitled Node'),
        'node_type' => firstExistingValue($nodeRow, ['node_type'], 'note'),
        'content_md' => firstExistingValue($nodeRow, ['content'], ''),
        'description' => firstExistingValue($nodeRow, ['description'], ''),
        'thumb' => $activeThumb,
        '_active_frame_id' => $activeFrameId,
        'role' => is_array($item) ? ($item['role'] ?? null) : null,
        'reason' => is_array($item) ? ($item['reason'] ?? null) : null,
    ];
}

$sequenceJson = json_encode([
    'id' => $seq['id'],
    'name' => $seq['name'],
    'description' => $seq['description'],
    'frames' => $frames,
], JSON_UNESCAPED_UNICODE);

ob_start();
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title><?= htmlspecialchars($seq['name']) ?> - Story Sequence</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Playfair+Display:ital,wght@0,600;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

    <style>
        :root {
            --bg-deep: #050508; --accent: #10b981; --accent-glow: rgba(16, 185, 129, 0.4);
            --text-main: #f8fafc; --text-muted: #94a3b8; --glass-bg: rgba(15, 23, 42, 0.4);
            --glass-border: rgba(255, 255, 255, 0.08);
            --font-ui: 'Inter', system-ui, sans-serif; --font-display: 'Playfair Display', serif;
            --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9;
        }

        body, html { margin: 0; padding: 0; width: 100%; height: 100%; background-color: var(--bg-deep); color: var(--text-main); font-family: var(--font-ui); overflow: hidden; overscroll-behavior: none; }
        
        #ambient-bg { position: fixed; inset: -10%; width: 120%; height: 120%; z-index: 0; pointer-events: none; filter: blur(80px) saturate(1.5) opacity(0.4); transform: translateZ(0); }
        .ambient-layer { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; will-change: opacity; }

        #layout { position: absolute; inset: 0; display: flex; flex-direction: column; z-index: 10; height: 100dvh; }
        #media-stage { position: relative; flex: none; width: 100%; aspect-ratio: 1 / 1; max-height: 55dvh; background: #000; z-index: 20; box-shadow: 0 10px 40px rgba(0,0,0,0.9); }
        .media-frame { position: absolute; inset: 0; width: 100%; height: 100%; overflow: hidden; background: #000; }
        .media-plane { position: absolute; inset: -5%; width: 110%; height: 110%; object-fit: cover; opacity: 0; will-change: transform, opacity; transform-origin: center center; }
        .media-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 10px; color: rgba(255,255,255,0.25); font-family: var(--font-display); font-size: 1.1rem; text-align: center; padding: 20px; z-index: 0; }

        #story-thread { flex: 1; overflow-y: auto; overflow-x: hidden; scroll-behavior: smooth; padding: 30px 20px 60vh 20px; position: relative; z-index: 15; background: linear-gradient(180deg, rgba(5,5,8,0.4) 0%, rgba(5,5,8,0.95) 15%); }

        @media(min-width: 1024px) {
            #layout { flex-direction: row; }
            #media-stage { flex: 0 0 55vw; height: 100dvh; max-height: none; aspect-ratio: auto; background: transparent; box-shadow: none; padding: 40px; display: flex; align-items: center; justify-content: center; }
            .media-frame { position: relative; width: 100%; height: 100%; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); }
            #story-thread { flex: 0 0 45vw; height: 100dvh; padding: 100px 60px 80vh 40px; background: transparent; }
        }

        .story-beat { position: relative; margin-bottom: 60px; padding-left: 25px; opacity: 0.2; transform: scale(0.95) translateX(-10px); transition: all 0.6s cubic-bezier(0.25, 1, 0.5, 1); will-change: transform, opacity; }
        .story-beat::before { content: ''; position: absolute; left: 0; top: 10px; bottom: -70px; width: 2px; background: rgba(255,255,255,0.08); }
        .story-beat::after { content: ''; position: absolute; left: -4px; top: 10px; width: 10px; height: 10px; border-radius: 50%; background: var(--text-muted); border: 2px solid var(--bg-deep); transition: all 0.4s; }
        .story-beat.active { opacity: 1; transform: scale(1) translateX(0); }
        .story-beat.active::after { background: var(--accent); box-shadow: 0 0 15px var(--accent); transform: scale(1.4); border-color: #000; }

        .beat-index { font-family: monospace; color: var(--accent); letter-spacing: 0.15em; font-size: 0.75rem; display: block; font-weight: 700; }
        .beat-type-pill { display: inline-block; margin-left: 8px; padding: 1px 8px; border-radius: 10px; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.08em; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: var(--text-muted); font-family: monospace; vertical-align: middle; }
        .beat-title { font-family: var(--font-display); font-size: clamp(1.8rem, 5vw, 2.8rem); margin: 0 0 12px 0; line-height: 1.1; color: #fff; }

        .beat-desc { font-size: 1rem; line-height: 1.7; color: var(--text-muted); margin-bottom: 20px; }
        .beat-desc h1, .beat-desc h2, .beat-desc h3 { color: #fff; font-family: var(--font-display); margin: 0.9em 0 0.4em; }
        .beat-desc p { margin: 0 0 0.9em; }
        .beat-desc blockquote { margin: 0 0 0.9em; padding: 8px 14px; border-left: 2px solid var(--accent); background: rgba(16,185,129,0.05); color: #cbd5e1; font-style: italic; border-radius: 0 6px 6px 0; }
        .beat-desc code { background: rgba(255,255,255,0.08); padding: 1px 6px; border-radius: 4px; font-size: 0.85em; color: #e2e8f0; }
        .beat-desc pre { background: rgba(0,0,0,0.4); border: 1px solid var(--glass-border); border-radius: 8px; padding: 12px 14px; overflow-x: auto; margin: 0 0 0.9em; }
        .beat-desc pre code { background: none; padding: 0; }
        .beat-desc a { color: var(--accent); }
        .beat-desc strong { color: #fff; }
        
        .desc-collapsed { max-height: 220px; overflow: hidden; position: relative; }
        .desc-collapsed::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 70px; background: linear-gradient(180deg, rgba(5,5,8,0) 0%, rgba(5,5,8,0.95) 90%); pointer-events: none; }
        .read-more-toggle { color: var(--accent); cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; transition: color 0.2s; }
        .read-more-toggle:hover { color: #fff; }

        .director-note { background: rgba(0,0,0,0.4); border-left: 2px solid #8b5cf6; padding: 10px 12px; font-size: 0.85rem; line-height: 1.5; color: #e2e8f0; font-style: italic; border-radius: 0 6px 6px 0; margin-bottom: 16px; }
        .director-note .role-label { display: block; font-style: normal; font-family: monospace; font-size: 0.65rem; text-transform: uppercase; color: #a78bfa; margin-bottom: 4px; }

        .btn-beat-action { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-muted); padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; text-transform: uppercase; }
        .btn-beat-action:hover { background: rgba(16, 185, 129, 0.1); color: var(--accent); border-color: rgba(16, 185, 129, 0.4); box-shadow: 0 0 10px var(--accent-glow); }

        .seq-header { margin-bottom: 80px; padding-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .seq-header-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .header-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        
        .pill { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; color: #cbd5e1; }
        .pill.hl { border-color: rgba(16, 185, 129, 0.4); color: #34d399; background: rgba(16, 185, 129, 0.05); }

        .seq-nav { display: flex; align-items: center; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 2px; }
        .btn-nav-small { background: transparent; border: none; color: var(--text-muted); width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; transition: 0.2s; line-height: 1; padding-bottom: 2px; }
        .btn-nav-small:hover:not(:disabled) { color: #fff; background: rgba(255,255,255,0.1); }
        .btn-nav-small:disabled { opacity: 0.3; cursor: not-allowed; }
        .seq-id-input { width: 50px; background: transparent; border: none; color: var(--accent); text-align: center; font-family: monospace; font-size: 0.85rem; font-weight: 700; -moz-appearance: textfield; }
        .seq-id-input:focus { outline: none; color: #fff; border-bottom: 1px solid var(--accent); }

        .seq-header h1 { font-family: var(--font-display); font-size: clamp(2.2rem, 6vw, 4rem); margin: 0; line-height: 1.1; }
        .seq-header p.sub { color: var(--accent); text-transform: uppercase; letter-spacing: 0.2em; font-size: 0.75rem; font-weight: 800; margin: 0 0 10px 0; }
        .seq-header .desc { color: var(--text-muted); line-height: 1.6; font-size: 1rem; }

        /* Media Frame Button */
        .f-view-btn { position: absolute; top: 15px; right: 15px; width: 40px; height: 40px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 50; opacity: 0.8; transition: all 0.2s; font-size: 18px; backdrop-filter: blur(5px); }
        .media-frame:hover .f-view-btn, .f-view-btn:hover { opacity: 1; background: var(--accent); color: #000; border-color: var(--accent); }

        .view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px); }
        .view-modal.active { display: flex; }
        .view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--glass-border); box-shadow: 0 0 40px rgba(0,0,0,0.8); border-radius: 8px; overflow: hidden; }
        .view-close { position: absolute; top: 15px; right: 15px; width: 40px; height: 40px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid var(--glass-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 24px; z-index: 200; transition: all 0.2s; backdrop-filter: blur(5px); }
        .view-close:hover { background: var(--accent); color: #000; border-color: var(--accent); }
        iframe.frame-viewer { width: 100%; height: 100%; border: none; display: block; }
        
        /* ----------------------------------------------------
           GRAPH OVERLAY & MODALS
           ---------------------------------------------------- */
        .mg-overlay { position: absolute; bottom: 20px; left: 20px; width: min(400px, 90vw); height: min(380px, 50vh); background: var(--card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.6); z-index: 1000; display: flex; flex-direction: column; overflow: hidden; transition: height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s; }
        .mg-overlay.collapsed { height: 42px; opacity: 0.9; }
        .mg-header { height: 42px; min-height: 42px; padding: 0 14px; border-bottom: 1px solid var(--border); font-size: 0.85rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center; background: var(--bg); cursor: move; user-select: none; touch-action: none; flex-shrink: 0; color: var(--text); }
        .mg-toolbar { padding: 6px 10px; background: var(--bg); border-bottom: 1px solid var(--border); display: flex; gap: 8px; align-items: center; flex-shrink: 0; overflow-x: auto; -webkit-overflow-scrolling: touch; color: var(--text); }
        .mg-toolbar::-webkit-scrollbar { display: none; }
        .mg-btn { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; border: 1px solid var(--border); background: var(--card); color: var(--text); font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: 0.15s; white-space: nowrap; }
        .mg-btn:hover { border-color: var(--accent); color: var(--accent); }
        .mg-btn.active { background: var(--accent); color: #000; border-color: var(--accent); }
        .mg-container { flex: 1; outline: none; background: var(--bg); min-height: 0; }
        
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; z-index:9998; padding:20px; box-sizing:border-box; }
        .modal-content { background:var(--card); border:1px solid var(--border); border-radius:12px; width:100%; max-width:800px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,0.6); color:var(--text); }
        .modal-header { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; background:var(--bg); flex-shrink:0; flex-wrap:wrap; }
        .modal-title { font-weight:700; flex:1; font-size:1.05rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .modal-body { padding:24px; overflow-y:auto; flex:1; font-size:0.95rem; line-height:1.6; }
        .btn { padding: 6px 12px; border-radius:6px; border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
        .btn-primary { background:var(--accent); color:#000; }
        
        .modal-body h1, .modal-body h2, .modal-body h3 { margin-top: 1em; margin-bottom: 0.5em; line-height: 1.3; color: #fff;}
        .modal-body h1 { font-size:1.4rem; border-bottom:1px solid var(--border); padding-bottom:5px; }
        .modal-body p { margin-bottom: 1em; }
        .modal-body img { max-width: 100%; border-radius:6px; }
        .modal-body code { background: rgba(125,125,125,0.2); padding: 2px 4px; border-radius: 4px; font-family: ui-monospace, monospace; font-size: 0.9em; }
        .modal-body pre { background: rgba(125,125,125,0.1); padding: 12px; border-radius: 6px; overflow-x: auto; border: 1px solid var(--border); }
        .modal-body pre code { background: none; border: none; padding: 0; }
        
        .conn-section { margin-top: 28px; border-top: 1px solid var(--border); padding-top: 16px; }
        .conn-section h4 { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); margin:0 0 10px; }
        .conn-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .conn-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; border: 1px solid var(--border); background: var(--bg); cursor: pointer; color: var(--text); transition: background 0.15s, border-color 0.15s; }
        .conn-pill:hover { border-color: var(--accent); color: var(--accent); background: rgba(16, 185, 129, 0.1); }
        .conn-rel { font-size: 0.7rem; font-weight: 400; color: var(--text-muted); padding-left: 5px; border-left: 1px solid var(--border); margin-left: 2px; }
        
        #ag-toast { position:fixed; bottom:24px; right:24px; z-index:99999; background:var(--card); color:var(--text); border:1px solid var(--border); border-left:4px solid var(--accent); border-radius:6px; padding:12px 18px; font-size:0.9rem; display:none; box-shadow:0 4px 12px rgba(0,0,0,.4); }
    </style>
</head>
<body>

    <div id="ambient-bg">
        <img id="amb-a" class="ambient-layer" src="" alt="">
        <img id="amb-b" class="ambient-layer" src="" alt="">
    </div>

    <div id="layout">
        <!-- MEDIA STAGE -->
        <div id="media-stage">
            <div class="media-frame" id="media-frame">
                <div class="f-view-btn" onclick="event.stopPropagation(); event.preventDefault(); openCurrentFrame()" title="View Frame Detail">
                    <i class="bi bi-arrows-fullscreen"></i>
                </div>
                <img id="img-a" class="media-plane" src="" alt="">
                <img id="img-b" class="media-plane" src="" alt="">
                <div class="media-empty" id="media-empty" style="display:none;">
                    <i class="bi bi-image"></i>
                    <span>No artwork linked to this element</span>
                </div>
            </div>
        </div>

        <!-- STORY THREAD -->
        <div id="story-thread">
            <div class="seq-header">
                <div class="seq-header-top">
                    <div>
                        <p class="sub">Narrative Sequence</p>
                        <h1 id="ui-seq-name">Title</h1>
                    </div>
                    <div class="header-actions">
                        <span class="pill hl" style="font-family: monospace; font-weight: 700;">
                            <?= count($frames) ?> ELEMENTS
                        </span>
                        <div class="seq-nav">
                            <button class="btn-nav-small" onclick="navSeq(<?= $prevSeqId ?: 'null' ?>)" <?= !$prevSeqId ? 'disabled' : '' ?> title="Previous Sequence">&#8249;</button>
                            <input type="number" class="seq-id-input" value="<?= $seq['id'] ?>" onchange="jumpToSeq(this.value)" title="Current Sequence ID (Type to jump)">
                            <button class="btn-nav-small" onclick="navSeq(<?= $nextSeqId ?: 'null' ?>)" <?= !$nextSeqId ? 'disabled' : '' ?> title="Next Sequence">&#8250;</button>
                        </div>
                    </div>
                </div>
                <div class="desc" id="ui-seq-desc"></div>
            </div>
            <div id="beats-container"></div>
        </div>
        
        <!-- GRAPH OVERLAY -->
        <div class="mg-overlay collapsed" id="mgOverlay">
            <div class="mg-header" id="mgHeader">
                <span><i class="bi bi-compass"></i> Local Context</span>
                <i class="bi bi-arrows-move"></i>
            </div>
            <div class="mg-toolbar">
                <span style="font-size:0.75rem; color:var(--text-muted);"><i class="bi bi-bezier2"></i> Hops</span>
                <select id="mgHopsSelect" onchange="if(currentGraphNodeId) loadLocalGraph(currentGraphNodeId, currentDocId)" style="padding: 2px 4px; border-radius: 4px; border: 1px solid var(--border); background: var(--card); color: var(--text); font-size: 0.75rem; cursor: pointer;">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
                <div style="width:1px; height:12px; background:var(--glass-border); margin:0 4px;"></div>
                <button class="mg-btn" id="btnLayout" onclick="toggleLayout()"><i class="bi bi-play-fill"></i> Lyt</button>
                <button class="mg-btn" onclick="resetGraphCamera()"><i class="bi bi-arrows-collapse"></i></button>
                
                <div id="mgActionSep" style="width:1px; height:12px; background:var(--glass-border); margin:0 4px; display:none;"></div>
                <button class="mg-btn" id="btnTravel" onclick="doTravelSelected()" style="display:none; color:var(--accent); border-color:var(--accent);">
                    <i class="bi bi-airplane"></i> Trv
                </button>
                <button class="mg-btn" id="btnDetails" onclick="doDetailsSelected()" style="display:none;">
                    <i class="bi bi-file-text"></i> Det
                </button>
            </div>
            <div class="mg-container" id="mgContainer"></div>
        </div>
    </div>

    <!-- Modals -->
    <div class="view-modal" id="viewModal">
        <div class="view-modal-content">
            <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
            <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
        </div>
    </div>
    
    <div class="modal-overlay" id="nodeContextModal" onclick="if(event.target===this) this.style.display='none'">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" id="modalTitle"></span>
                <button id="btnTravelHere" class="btn btn-primary"><i class="bi bi-airplane"></i> Travel</button>
                <button id="btnEditEntity" class="btn" style="background:rgba(255,255,255,0.1); color:#fff; border:1px solid rgba(255,255,255,0.2); display:none;"><i class="bi bi-pencil-square"></i> Ent</button>
                <a id="btnTravelView" class="btn" style="background:rgba(255,255,255,0.1); color:#fff; border:1px solid rgba(255,255,255,0.2);" href="#" target="_blank"><i class="bi bi-box-arrow-up-right"></i> TV</a>
                <button onclick="document.getElementById('nodeContextModal').style.display='none'" style="background:none; border:none; color:var(--text); font-size:1.6rem; cursor:pointer; line-height:1; margin-left:auto;">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modalTextContent"></div>
                <div id="modalConnections"></div>
            </div>
        </div>
    </div>
    
    <div id="ag-toast"></div>

    <?php require __DIR__ . '/modal_frame_details.php'; ?>

    <script>
        const sequenceData = <?= $sequenceJson ?>;
        const frames = sequenceData.frames || [];

        let activeIndex = -1;
        let activePlane = 'img-a';
        let continuousPanAnim = null;
        let transitionToken = 0;
        const imageLoadCache = new Map();

        const elImgA = document.getElementById('img-a');
        const elImgB = document.getElementById('img-b');
        const elAmbA = document.getElementById('amb-a');
        const elAmbB = document.getElementById('amb-b');
        const elMediaEmpty = document.getElementById('media-empty');
        const thread = document.getElementById('story-thread');
        const beatsContainer = document.getElementById('beats-container');

        let toastTimer;
        function toast(msg, type = 'success') {
            const el = document.getElementById('ag-toast');
            el.textContent = msg;
            el.style.borderLeftColor = type === 'error' ? 'var(--red, #f05060)' : 'var(--accent)';
            el.style.display = 'block';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => el.style.display = 'none', 3000);
        }

        function preloadFrameImage(src) {
            if (!src) return Promise.resolve('');
            if (imageLoadCache.has(src)) return imageLoadCache.get(src);
            const promise = new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve(src);
                img.onerror = () => resolve(src);
                img.src = src;
            });
            imageLoadCache.set(src, promise);
            return promise;
        }

        function nodeTypeIcon(t) {
            const map = { relationship:'🔗', character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', note:'📝' };
            return map[t] || '📝';
        }

        function init() {
            document.getElementById('ui-seq-name').innerText = sequenceData.name || 'Untitled';
            document.getElementById('ui-seq-desc').innerText = sequenceData.description || 'Scroll through the thread below to experience the sequence.';

            frames.forEach((frame, i) => {
                const hasPrev = i > 0;
                const hasNext = i < frames.length - 1;

                const md = (frame.content_md || '').trim();
                let bodyHtml = '';
                if (md) bodyHtml = marked.parse(md);
                else if (frame.description) bodyHtml = `<p>${escapeHtml(frame.description)}</p>`;
                else bodyHtml = `<p style="opacity:0.5;">No content available.</p>`;

                let directorHtml = '';
                if (frame.role || frame.reason) {
                    directorHtml = `
                    <div class="director-note">
                        ${frame.role ? `<span class="role-label">${frame.role}</span>` : ''}
                        ${frame.reason ? frame.reason : ''}
                    </div>`;
                }

                const beat = document.createElement('div');
                beat.className = 'story-beat';
                beat.id = `beat-${i}`;
                beat.dataset.index = i;

                beat.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span class="beat-index" style="margin-bottom:0;">BEAT ${String(i + 1).padStart(2, '0')}<span class="beat-type-pill">${nodeTypeIcon(frame.node_type)} ${frame.node_type || 'note'}</span></span>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top:0, behavior:'smooth'})" title="Back to Top"><i class="bi bi-arrow-up"></i> Top</button>
                            ${hasPrev ? `<button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i - 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Previous"><i class="bi bi-arrow-up"></i> Prev</button>` : ''}
                            ${hasNext ? `<button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i + 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Next">Next <i class="bi bi-arrow-down"></i></button>` : ''}
                        </div>
                    </div>
                    <h2 class="beat-title">${frame.name || 'Untitled Element'}</h2>
                    ${directorHtml}
                    <div class="beat-desc-wrap">
                        <div class="beat-desc desc-collapsed" id="desc-${i}">${bodyHtml}</div>
                        <span class="read-more-toggle" id="toggle-${i}" onclick="toggleDesc(event, ${i})" style="display:none;">Read more <i class="bi bi-chevron-down"></i></span>
                    </div>
                `;
                beatsContainer.appendChild(beat);
            });

            frames.forEach((f, i) => {
                const d = document.getElementById(`desc-${i}`);
                const t = document.getElementById(`toggle-${i}`);
                if (d && t && d.scrollHeight > d.clientHeight + 4) t.style.display = 'inline-flex';
                else if (d) d.classList.remove('desc-collapsed');
            });

            if (frames.length > 0 && frames[0].thumb) {
                elImgA.src = frames[0].thumb; elAmbA.src = frames[0].thumb;
                elImgA.style.opacity = 1; elAmbA.style.opacity = 1; elImgA.style.transform = "scale(1)";
            } else {
                elMediaEmpty.style.display = 'flex';
            }

            setupIntersectionObserver();
            makeDraggable('mgOverlay', 'mgHeader');
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        window.toggleDesc = (e, idx) => {
            e.stopPropagation();
            const d = document.getElementById(`desc-${idx}`);
            const t = document.getElementById(`toggle-${idx}`);
            if (d.classList.contains('desc-collapsed')) { d.classList.remove('desc-collapsed'); t.innerHTML = 'Show less <i class="bi bi-chevron-up"></i>'; }
            else { d.classList.add('desc-collapsed'); t.innerHTML = 'Read more <i class="bi bi-chevron-down"></i>'; }
        };

        function setupIntersectionObserver() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => { if (entry.isIntersecting) activateBeat(parseInt(entry.target.dataset.index)); });
            }, { root: thread, rootMargin: '-30% 0px -40% 0px', threshold: 0 });
            document.querySelectorAll('.story-beat').forEach(b => observer.observe(b));
        }

        async function activateBeat(index) {
            if (index === activeIndex) return;
            const beatEl = document.getElementById(`beat-${index}`);
            if (!beatEl) return;

            document.querySelectorAll('.story-beat').forEach(b => b.classList.remove('active'));
            beatEl.classList.add('active');

            const newFrame = frames[index];
            const previousIndex = activeIndex;
            activeIndex = index;
            
            // GRAPH OVERLAY SYNC
            if (newFrame && newFrame.id && newFrame.doc_id && newFrame.id !== currentGraphNodeId) {
                loadLocalGraph(newFrame.id, newFrame.doc_id);
            }

            if (!newFrame || !newFrame.thumb) {
                elMediaEmpty.style.display = 'flex';
                elImgA.style.opacity = 0; elImgB.style.opacity = 0; elAmbA.style.opacity = 0; elAmbB.style.opacity = 0;
                return;
            }
            elMediaEmpty.style.display = 'none';

            const myToken = ++transitionToken;
            await preloadFrameImage(newFrame.thumb);
            if (myToken !== transitionToken) return;

            const oldPlane = activePlane === 'img-a' ? elImgA : elImgB;
            const newPlane = activePlane === 'img-a' ? elImgB : elImgA;
            const oldAmb = activePlane === 'img-a' ? elAmbA : elAmbB;
            const newAmb = activePlane === 'img-a' ? elAmbB : elAmbA;
            activePlane = newPlane.id;

            newPlane.src = newFrame.thumb; newAmb.src = newFrame.thumb;
            newPlane.style.zIndex = 2; oldPlane.style.zIndex = 1;

            if (continuousPanAnim) continuousPanAnim.pause();
            
            const scrollingDown = index > previousIndex;
            const yOffset = scrollingDown ? 15 : -15;
            const rotateDir = scrollingDown ? 4 : -4;

            anime.remove([oldPlane, newPlane, oldAmb, newAmb, document.getElementById('media-frame')]);

            anime.set(newPlane, { opacity: 0, scale: 1.15, translateX: 0, translateY: 0, rotateZ: 0 });
            anime.set(oldPlane, { opacity: 1 });

            const tl = anime.timeline({ easing: 'easeOutCubic' });
            anime({ targets: newAmb, opacity: 1, duration: 1500, easing: 'linear' });
            anime({ targets: oldAmb, opacity: 0, duration: 1500, easing: 'linear' });

            tl.add({ targets: document.getElementById('media-frame'), scale: [0.98, 1], duration: 800 }, 0);
            tl.add({ targets: newPlane, opacity: [0, 1], translateY: [yOffset + '%', '0%'], scale: [1.1, 1], rotateZ: [rotateDir, 0], duration: 1000 }, 0);
            tl.add({ targets: oldPlane, opacity: [1, 0], translateY: ['0%', (-yOffset) + '%'], scale: [1, 0.9], duration: 900 }, 0);

            tl.finished.then(() => { if (myToken === transitionToken) startContinuousPan(newPlane); });
        }

        function startContinuousPan(el) {
            const panX = (Math.random() > 0.5 ? 2 : -2) + '%';
            const panY = (Math.random() > 0.5 ? 2 : -2) + '%';
            continuousPanAnim = anime({ targets: el, scale: [1, 1.05], translateX: [0, panX], translateY: [0, panY], duration: 15000, easing: 'linear', direction: 'alternate', loop: true });
        }

        window.navSeq = (id) => { if (id) window.location.href = '?id=' + id; };
        window.jumpToSeq = (id) => { const cid = parseInt(id); if (!isNaN(cid) && cid > 0) window.location.href = '?id=' + cid; };

        window.openFrameModal = (id) => { if (!id) return; document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`; document.getElementById('viewModal').classList.add('active'); };
        window.closeFrameModal = () => { document.getElementById('viewModal').classList.remove('active'); setTimeout(() => document.getElementById('frameViewer').src = '', 200); };
        window.openCurrentFrame = () => { if (activeIndex > -1 && frames[activeIndex]._active_frame_id) openFrameModal(frames[activeIndex]._active_frame_id); };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const viewModal = document.getElementById('viewModal');
                if (viewModal && viewModal.classList.contains('active')) closeFrameModal();
                const ctxModal = document.getElementById('nodeContextModal');
                if (ctxModal && ctxModal.style.display === 'flex') ctxModal.style.display = 'none';
            }
        });

        // -------------------------------------------------------------------------------------------------
        // GRAPH OVERLAY & LOGIC
        // -------------------------------------------------------------------------------------------------
        let graph = null, renderer = null, isLayoutRunning = false, fa2LoopId = null, selectedNodeId = null, currentGraphNodeId = null, currentDocId = null;

        const TYPE_COLORS = { note: '#64748b', relationship: '#ec4899', character: '#3b82f6', location: '#10b981', event: '#ef4444', concept: '#f59e0b', arc: '#8b5cf6', episode: '#06b6d4', default: '#888888' };

        function makeDraggable(panelId, handleId) {
            const panel = document.getElementById(panelId), handle = document.getElementById(handleId);
            let isDragging = false, startX, startY, initialX, initialY, lastTouchTime = 0;

            function start(e) {
                if(e.target.closest('button') || e.target.closest('select')) return;
                if (e.type === 'touchstart') lastTouchTime = Date.now();
                else if (e.type === 'mousedown' && Date.now() - lastTouchTime < 500) return;

                isDragging = false;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                startX = clientX; startY = clientY;
                const rect = panel.getBoundingClientRect();
                initialX = rect.left; initialY = rect.top;

                panel.style.bottom = 'auto'; panel.style.right = 'auto';
                panel.style.left = initialX + 'px'; panel.style.top = initialY + 'px';
                panel.style.transition = 'none';

                document.addEventListener('mousemove', move, {passive: false}); document.addEventListener('mouseup', end);
                document.addEventListener('touchmove', move, {passive: false}); document.addEventListener('touchend', end);
            }

            function move(e) {
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                const dx = clientX - startX, dy = clientY - startY;
                if (!isDragging && Math.sqrt(dx*dx + dy*dy) > 8) isDragging = true;
                if (isDragging) { e.preventDefault(); panel.style.left = (initialX + dx) + 'px'; panel.style.top  = (initialY + dy) + 'px'; }
            }

            function end(e) {
                document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', end);
                document.removeEventListener('touchmove', move); document.removeEventListener('touchend', end);
                panel.style.transition = 'height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s';
                if (!isDragging) {
                    panel.classList.toggle('collapsed');
                    // FIX: Force Sigma.js to recalculate canvas dimensions after the 0.3s CSS expansion finishes
                    if (renderer && !panel.classList.contains('collapsed')) setTimeout(() => renderer.refresh(), 320);
                }
            }
            handle.addEventListener('mousedown', start); handle.addEventListener('touchstart', start, {passive: true});
        }

        function loadLocalGraph(nodeId, docId) {
            currentDocId = docId; currentGraphNodeId = nodeId;
            const hops = parseInt(document.getElementById('mgHopsSelect').value) || 1;

            fetch(`ag_map_run_export_api.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'fetch_mini_graph', node_id: nodeId, doc_id: docId, hops: hops })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.ok || !res.graph) return;
                selectNode(null);

                if (graph) graph.clear(); else graph = new graphology.MultiDirectedGraph();
                
                res.graph.nodes.forEach(n => {
                    const isFocal = n.id == nodeId;
                    graph.addNode(n.id.toString(), { 
                        x: Math.random() * 10, y: Math.random() * 10, 
                        size: isFocal ? 16 : 8, label: n.name, 
                        color: isFocal ? '#10b981' : (TYPE_COLORS[n.node_type] || '#888888'), 
                        is_focal: isFocal, is_real_node: true, node_type: n.node_type
                    });
                });

                res.graph.edges.forEach(e => {
                    if (graph.hasNode(e.source.toString()) && graph.hasNode(e.target.toString())) {
                        try { graph.addDirectedEdge(e.source.toString(), e.target.toString(), { label: e.relationship, size:1, color:'#666' }); } catch(err){}
                    }
                });

                graph.forEachNode(node => {
                    const isFocal = graph.getNodeAttribute(node, 'is_focal');
                    const deg = graph.degree(node);
                    graph.setNodeAttribute(node, 'size', isFocal ? 16 : (8 + Math.sqrt(deg) * 2));
                });

                if (!renderer) {
                    const container = document.getElementById('mgContainer');
                    renderer = new Sigma(graph, container, { renderEdgeLabels: false, defaultEdgeType: 'arrow', allowInvalidContainer: true, labelRenderedSizeThreshold: 2, labelColor: { color: '#c9d1d9' } });
                    
                    renderer.on('clickStage', () => { selectNode(null); });
                    
                    let dragNode = null, dragFrame = null, dragStartX = 0, dragStartY = 0;
                    renderer.on('downNode', e => { dragNode = e.node; const ne = e.event.original || e.event; dragStartX = ne.touches ? ne.touches[0].clientX : (ne.clientX || 0); dragStartY = ne.touches ? ne.touches[0].clientY : (ne.clientY || 0); renderer.getCamera().disable(); });
                    
                    container.addEventListener('touchmove', e => {
                        if (!dragNode) return;
                        e.preventDefault(); const rect = container.getBoundingClientRect(); const touch = e.touches[0];
                        if (dragFrame) cancelAnimationFrame(dragFrame);
                        dragFrame = requestAnimationFrame(() => { const pos = renderer.viewportToGraph({ x: touch.clientX - rect.left, y: touch.clientY - rect.top }); graph.setNodeAttribute(dragNode, 'x', pos.x); graph.setNodeAttribute(dragNode, 'y', pos.y); dragFrame = null; });
                    }, { passive: false });

                    renderer.getMouseCaptor().on('mousemovebody', e => {
                        if(!dragNode) return;
                        e.preventSigmaDefault(); e.original.preventDefault();
                        if(dragFrame) cancelAnimationFrame(dragFrame);
                        dragFrame = requestAnimationFrame(() => { const pos = renderer.viewportToGraph(e); graph.setNodeAttribute(dragNode, 'x', pos.x); graph.setNodeAttribute(dragNode, 'y', pos.y); dragFrame = null; });
                    });
                    
                    function releaseNode(e) {
                        if (!dragNode) return;
                        let endX = 0, endY = 0;
                        if (e.changedTouches && e.changedTouches.length) { endX = e.changedTouches[0].clientX; endY = e.changedTouches[0].clientY; } 
                        else { endX = e.clientX || dragStartX; endY = e.clientY || dragStartY; }
                        if (Math.sqrt(Math.pow(endX - dragStartX, 2) + Math.pow(endY - dragStartY, 2)) < 6) selectNode(dragNode);
                        renderer.getCamera().enable(); dragNode = null;
                    }
                    window.addEventListener('mouseup', releaseNode); window.addEventListener('touchend', releaseNode);

                    let hoveredNode = null;
                    renderer.setSetting('nodeReducer', (node, data) => {
                        const res = { ...data }; let isDimmed = false;
                        if (hoveredNode && hoveredNode !== node && !graph.hasEdge(node, hoveredNode) && !graph.hasEdge(hoveredNode, node)) isDimmed = true;
                        else if (selectedNodeId && selectedNodeId !== node && !graph.hasEdge(node, selectedNodeId) && !graph.hasEdge(selectedNodeId, node)) isDimmed = true;
                        if (isDimmed) { res.color = '#444'; res.zIndex = 0; } else { res.zIndex = data.is_focal ? 3 : 1; }
                        if (node === hoveredNode || node === selectedNodeId) res.zIndex = 2;
                        if (data.is_focal || node === selectedNodeId) res.highlighted = true;
                        return res;
                    });
                    
                    renderer.setSetting('edgeReducer', (edge, data) => {
                        const res = { ...data };
                        if (hoveredNode && graph.source(edge) !== hoveredNode && graph.target(edge) !== hoveredNode) { res.color = '#333'; res.hidden = true; } 
                        else if (selectedNodeId && graph.source(edge) !== selectedNodeId && graph.target(edge) !== selectedNodeId) { res.color = '#333'; res.hidden = true; } 
                        else if (hoveredNode || selectedNodeId) { res.size = 2; res.color = '#888'; }
                        return res;
                    });

                    renderer.on('enterNode', ({ node }) => { hoveredNode = node; renderer.refresh(); });
                    renderer.on('leaveNode', () => { hoveredNode = null; renderer.refresh(); });
                } else {
                    renderer.refresh();
                }

                const fa2 = graphologyLibrary.layoutForceAtlas2;
                fa2.assign(graph, { iterations: 120, settings: { barnesHutOptimize: false, gravity: 0.1, scalingRatio: 2 } });
                renderer.refresh();
                resetGraphCamera();
            });
        }

        function resetGraphCamera() { if(renderer) { renderer.getCamera().animatedReset({ duration: 300 }); } }

        function toggleLayout() {
            if(!graph || !renderer) return;
            const btn = document.getElementById('btnLayout');
            if (isLayoutRunning) {
                cancelAnimationFrame(fa2LoopId); isLayoutRunning = false;
                btn.innerHTML = '<i class="bi bi-play-fill"></i> Lyt'; btn.classList.remove('active');
            } else {
                isLayoutRunning = true; btn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop'; btn.classList.add('active');
                function step() { graphologyLibrary.layoutForceAtlas2.assign(graph, { iterations: 1, settings: { barnesHutOptimize: false, gravity: 0.05 } }); renderer.refresh(); if (isLayoutRunning) fa2LoopId = requestAnimationFrame(step); }
                step();
            }
        }
        
        function selectNode(nodeId) {
            selectedNodeId = nodeId;
            if (renderer) renderer.refresh();
            const sep = document.getElementById('mgActionSep'), btnT = document.getElementById('btnTravel'), btnD = document.getElementById('btnDetails');
            if (nodeId) { sep.style.display = 'block'; btnT.style.display = 'inline-flex'; btnD.style.display = 'inline-flex'; } 
            else { sep.style.display = 'none'; btnT.style.display = 'none'; btnD.style.display = 'none'; }
        }

        function doTravelSelected() {
            if (selectedNodeId) {
                const attrs = graph.getNodeAttributes(selectedNodeId);
                if (attrs.is_real_node) loadLocalGraph(selectedNodeId, currentDocId);
                else toast('Cannot travel to an unresolved text node.', 'error');
            }
        }

        function doDetailsSelected() {
            if (selectedNodeId) openNodeContextModal(selectedNodeId);
        }
        
        function openNodeContextModal(nodeId) {
            const nodeAttrs = graph.getNodeAttributes(nodeId);
            document.getElementById('modalTitle').textContent = nodeAttrs.label;
            
            document.getElementById('btnTravelHere').onclick = () => {
                if (nodeAttrs.is_real_node) { loadLocalGraph(nodeId, currentDocId); document.getElementById('nodeContextModal').style.display = 'none'; }
                else toast('Cannot travel to an unresolved text node.', 'error');
            };
            
            document.getElementById('btnTravelView').href = nodeAttrs.is_real_node ? `ag_view.php?doc_id=${currentDocId}&node_id=${nodeId}` : '#';
            if(!nodeAttrs.is_real_node) document.getElementById('btnTravelView').style.opacity = '0.3'; else document.getElementById('btnTravelView').style.opacity = '1';

            const btnEditEntity = document.getElementById('btnEditEntity');
            btnEditEntity.style.display = 'none'; btnEditEntity.onclick = null;
            
            fetch('ag_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'fetch_visuals', doc_id: currentDocId, entity_name: nodeAttrs.label }) })
            .then(r => r.json())
            .then(res => {
                if (res.ok && res.sketch && res.sketch.id) {
                    btnEditEntity.onclick = () => { if(typeof window.showEntityFormInModal === 'function') window.showEntityFormInModal('sketches', res.sketch.id); };
                    btnEditEntity.style.display = 'inline-flex';
                }
            }).catch(e => console.error("Edit lookup failed", e));

            const textContent = document.getElementById('modalTextContent');
            const connContent = document.getElementById('modalConnections');
            
            if (!nodeAttrs.is_real_node) {
                textContent.innerHTML = '<i>This node is unresolved (string only) and has no deep content.</i>';
                connContent.innerHTML = '';
                document.getElementById('nodeContextModal').style.display = 'flex';
                return;
            }

            textContent.innerHTML = '<i>Loading context payload...</i>';
            connContent.innerHTML = '';
            document.getElementById('nodeContextModal').style.display = 'flex';

            fetch(`ag_map_run_export_api.php?action=get_node_context&node_id=${nodeId}&doc_id=${currentDocId}`)
                .then(r => r.json())
                .then(res => {
                    if(!res.ok) { textContent.innerHTML = 'Failed to load content.'; return; }
                    
                    const md = (res.node && res.node.content) ? res.node.content.trim() : '';
                    textContent.innerHTML = md ? marked.parse(md) : '<i>This node contains no markdown content.</i>';
                    
                    let html = '';
                    if(res.node.outgoing && res.node.outgoing.length > 0) {
                        html += `<div class="conn-section"><h4>Outgoing (${res.node.outgoing.length})</h4><div class="conn-list">`;
                        res.node.outgoing.forEach(n => html += `<button class="conn-pill" onclick="openNodeContextModal('${n.id}')">${escapeHtml(n.label)} ${n.relationship ? '<span class="conn-rel">'+escapeHtml(n.relationship)+'</span>' : ''}</button>`);
                        html += `</div></div>`;
                    }
                    if(res.node.incoming && res.node.incoming.length > 0) {
                        html += `<div class="conn-section"><h4>Incoming (${res.node.incoming.length})</h4><div class="conn-list">`;
                        res.node.incoming.forEach(n => html += `<button class="conn-pill" onclick="openNodeContextModal('${n.id}')">${escapeHtml(n.label)} ${n.relationship ? '<span class="conn-rel">'+escapeHtml(n.relationship)+'</span>' : ''}</button>`);
                        html += `</div></div>`;
                    }
                    connContent.innerHTML = html;
                });
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
<?php
$htmlContent = ob_get_clean();
echo $htmlContent;
?>