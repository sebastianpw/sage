<?php
// public/kgnodeseq.php
// Showrunner - The Split-Cinematic Gallery V8 (KG Node Edition)
// Clone of animejseq.php's scroll-driven Anime.js viewer, but the story thread
// is built from kg_nodes markdown content (rendered to HTML) instead of sketch
// descriptions/analysis. No sketch intel panels, no JSON/ZIP export — for that,
// use animejseq.php. The visual media stage still resolves sketch/frame imagery
// for each sequence item when present, since narrative_sequences items still
// carry sketch_id/frame_id.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

/**
 * Return the first non-empty value from a row by trying multiple keys.
 */
function firstExistingValue(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
            return $row[$key];
        }
    }
    return $default;
}

/**
 * Try to resolve a displayable image URL/path for a frame row without assuming a thumb column exists.
 */
function resolveFrameThumb(array $row, int $frameId = 0): string
{
    $candidate = firstExistingValue($row, [
        'thumb',
        'thumbnail',
        'image',
        'image_url',
        'image_path',
        'file_path',
        'path',
        'src',
        'url',
        'filename',
        'file_name',
    ], '');

    if (is_string($candidate) && $candidate !== '') {
        return $candidate;
    }

    if ($frameId > 0) {
        return 'view_frame.php?frame_id=' . $frameId;
    }

    return '';
}

// 1. Fetch the sequence
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

if (!$seq) {
    die("<div style='background:#050508; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Sequence not found.</div>");
}

// Find Previous and Next Sequence IDs
$prevSeqId = null;
$nextSeqId = null;
if ($seq) {
    $stmtPrev = $pdo->prepare("SELECT id FROM $table WHERE id < ? ORDER BY id DESC LIMIT 1");
    $stmtPrev->execute([$seq['id']]);
    $prevSeqId = $stmtPrev->fetchColumn();

    $stmtNext = $pdo->prepare("SELECT id FROM $table WHERE id > ? ORDER BY id ASC LIMIT 1");
    $stmtNext->execute([$seq['id']]);
    $nextSeqId = $stmtNext->fetchColumn();
}

// 2. Parse sequence_data — items are expected to carry kg_node_id (this module's
// own export/import format), but we also tolerate the plain sketch_id-keyed
// format used elsewhere, mapping sketch_id -> kg_nodes via name match so older
// sequences still resolve to something.
$itemIds = json_decode($seq['sequence_data'] ?? '[]', true);
if (!is_array($itemIds)) {
    $itemIds = [];
}

$kgNodeIdsInOrder = [];   // per-item kg_node_id (or null)
$sketchIdsInOrder = [];   // per-item sketch_id (or null)
$frameIdsInOrder = [];    // per-item frame_id (or null)

foreach ($itemIds as $idx => $item) {
    if (is_array($item)) {
        $kgNodeIdsInOrder[$idx] = !empty($item['kg_node_id']) ? (int)$item['kg_node_id'] : null;
        $sketchIdsInOrder[$idx] = !empty($item['sketch_id']) ? (int)$item['sketch_id'] : null;
        $frameIdsInOrder[$idx]  = !empty($item['frame_id']) ? (int)$item['frame_id'] : null;
    } else {
        // Legacy plain-integer sequence — historically a sketch_id, no kg_node_id known yet
        $kgNodeIdsInOrder[$idx] = null;
        $sketchIdsInOrder[$idx] = (int)$item ?: null;
        $frameIdsInOrder[$idx]  = null;
    }
}

// Resolve any missing kg_node_id via sketch name -> kg_nodes name match (best effort,
// for backward compatibility with sequences saved before this viewer existed).
$sketchIdsNeedingLookup = array_values(array_unique(array_filter(
    array_map(function ($idx) use ($kgNodeIdsInOrder, $sketchIdsInOrder) {
        return (empty($kgNodeIdsInOrder[$idx]) && !empty($sketchIdsInOrder[$idx])) ? $sketchIdsInOrder[$idx] : null;
    }, array_keys($itemIds))
)));

$kgNodeIdBySketchId = [];
if (!empty($sketchIdsNeedingLookup)) {
    $inClause = implode(',', array_fill(0, count($sketchIdsNeedingLookup), '?'));
    $stmtLookup = $pdo->prepare(
        "SELECT s.id AS sketch_id, n.id AS kg_node_id
         FROM sketches s
         JOIN kg_nodes n ON n.name = s.name
         WHERE s.id IN ($inClause)"
    );
    $stmtLookup->execute($sketchIdsNeedingLookup);
    foreach ($stmtLookup->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kgNodeIdBySketchId[(int)$row['sketch_id']] = (int)$row['kg_node_id'];
    }
    foreach ($kgNodeIdsInOrder as $idx => $val) {
        if (empty($val) && !empty($sketchIdsInOrder[$idx]) && isset($kgNodeIdBySketchId[$sketchIdsInOrder[$idx]])) {
            $kgNodeIdsInOrder[$idx] = $kgNodeIdBySketchId[$sketchIdsInOrder[$idx]];
        }
    }
}

$pureKgNodeIds = array_values(array_unique(array_filter($kgNodeIdsInOrder)));

// Load kg_nodes (this is the text source of truth for this viewer)
$kgNodesData = [];
if (!empty($pureKgNodeIds)) {
    $inClause = implode(',', array_fill(0, count($pureKgNodeIds), '?'));
    $stmtN = $pdo->prepare("SELECT * FROM kg_nodes WHERE id IN ($inClause)");
    $stmtN->execute($pureKgNodeIds);
    foreach ($stmtN->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kgNodesData[(int)$row['id']] = $row;
    }
}

// 3. Resolve imagery for the media stage. A sequence item may carry an explicit
// frame_id; otherwise fall back to the latest frame for its sketch_id (if any);
// otherwise fall back to the canonical sketch resolved from the kg_node name.
$pureSketchIds = array_values(array_unique(array_filter($sketchIdsInOrder)));

// Canonical sketch per kg_node (name match), for items that have a kg_node_id
// but no explicit sketch_id.
$canonicalSketchByNode = [];
if (!empty($pureKgNodeIds)) {
    $inClause = implode(',', array_fill(0, count($pureKgNodeIds), '?'));
    $stmtCanon = $pdo->prepare(
        "SELECT n.id AS kg_node_id, s.id AS sketch_id
         FROM kg_nodes n
         JOIN sketches s ON s.name = n.name
         WHERE n.id IN ($inClause)"
    );
    $stmtCanon->execute($pureKgNodeIds);
    foreach ($stmtCanon->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nid = (int)$row['kg_node_id'];
        if (!isset($canonicalSketchByNode[$nid])) {
            $canonicalSketchByNode[$nid] = (int)$row['sketch_id'];
            $pureSketchIds[] = (int)$row['sketch_id'];
        }
    }
}
$pureSketchIds = array_values(array_unique($pureSketchIds));

// Load explicit frames
$activeFrameIds = array_values(array_unique(array_filter($frameIdsInOrder)));
$selectedFrameMap = [];
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fid = (int)$row['id'];
        $selectedFrameMap[$fid] = [
            'row' => $row,
            'thumb' => resolveFrameThumb($row, $fid),
        ];
    }
}

// Latest frame per sketch, for sketches needing a fallback image
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
            $latestFrameBySketch[$sketchId] = [
                'frame_id' => $fid,
                'row' => $row,
                'thumb' => resolveFrameThumb($row, $fid),
            ];
        }
    }
}

// 4. Build viewer frames in the exact order of sequence_data.
// Each beat's TEXT comes from kg_nodes (name + content markdown -> HTML).
// Each beat's IMAGE comes from explicit frame_id, else latest frame for its
// sketch_id, else latest frame for the kg_node's canonical sketch.
$frames = [];
foreach ($itemIds as $idx => $item) {
    $kgNodeId = $kgNodeIdsInOrder[$idx] ?? null;
    if (empty($kgNodeId) || !isset($kgNodesData[$kgNodeId])) {
        continue; // no resolvable KG node text source — skip this item entirely
    }

    $nodeRow = $kgNodesData[$kgNodeId];

    $explicitFrameId = $frameIdsInOrder[$idx] ?? null;
    $itemSketchId = $sketchIdsInOrder[$idx] ?? null;
    $fallbackSketchId = $itemSketchId ?: ($canonicalSketchByNode[$kgNodeId] ?? null);

    $activeThumb = '';
    $activeFrameId = null;

    if ($explicitFrameId && isset($selectedFrameMap[$explicitFrameId])) {
        $activeThumb = $selectedFrameMap[$explicitFrameId]['thumb'];
        $activeFrameId = $explicitFrameId;
    } elseif ($fallbackSketchId && isset($latestFrameBySketch[$fallbackSketchId])) {
        $activeThumb = $latestFrameBySketch[$fallbackSketchId]['thumb'];
        $activeFrameId = $latestFrameBySketch[$fallbackSketchId]['frame_id'];
    }

    $frameNode = [
        'id' => $kgNodeId,
        'name' => firstExistingValue($nodeRow, ['name'], 'Untitled Node'),
        'node_type' => firstExistingValue($nodeRow, ['node_type'], 'note'),
        // Raw markdown content — rendered to HTML client-side via marked.js,
        // same library already used elsewhere in the KG tooling for this exact purpose.
        'content_md' => firstExistingValue($nodeRow, ['content'], ''),
        'description' => firstExistingValue($nodeRow, ['description'], ''),
        'thumb' => $activeThumb,
        '_active_frame_id' => $activeFrameId,
        'role' => is_array($item) ? ($item['role'] ?? null) : null,
        'reason' => is_array($item) ? ($item['reason'] ?? null) : null,
    ];

    $frames[] = $frameNode;
}

// Sanitize for JS UI
$sequenceJson = json_encode([
    'id' => $seq['id'],
    'name' => $seq['name'],
    'description' => $seq['description'],
    'frames' => $frames,
], JSON_UNESCAPED_UNICODE);

// Start output buffering for HTML
ob_start();
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title><?= htmlspecialchars($seq['name']) ?> - The Narrative Gallery (KG Nodes)</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Playfair+Display:ital,wght@0,600;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Anime.js -->
    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>
    <!-- marked.js: renders kg_nodes.content (markdown) to HTML client-side -->
    <script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
    
    <!-- Graph dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

    <style>
        :root {
            --bg-deep: #050508;
            --accent: #10b981;
            --accent-glow: rgba(16, 185, 129, 0.4);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(15, 23, 42, 0.4);
            --glass-border: rgba(255, 255, 255, 0.08);
            --font-ui: 'Inter', system-ui, sans-serif;
            --font-display: 'Playfair Display', serif;
            
            /* Variables mapping for the Graph Modal / Overlay */
            --bg: #0d1117;
            --card: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
        }

        body, html {
            margin: 0; padding: 0;
            width: 100%; height: 100%;
            background-color: var(--bg-deep);
            color: var(--text-main);
            font-family: var(--font-ui);
            overflow: hidden;
            overscroll-behavior: none;
        }

        /* --- 1. Ambient Background --- */
        #ambient-bg {
            position: fixed; inset: -10%;
            width: 120%; height: 120%;
            z-index: 0;
            pointer-events: none;
            filter: blur(80px) saturate(1.5) opacity(0.4);
            transform: translateZ(0);
        }
        .ambient-layer {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            opacity: 0;
            will-change: opacity;
        }

        /* --- 2. Split Layout Architecture --- */
        #layout {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            z-index: 10;
            height: 100dvh;
        }

        /* MEDIA STAGE */
        #media-stage {
            position: relative;
            flex: none;
            width: 100%;
            aspect-ratio: 1 / 1;
            max-height: 55dvh;
            background: #000;
            z-index: 20;
            box-shadow: 0 10px 40px rgba(0,0,0,0.9);
        }

        .media-frame {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #000;
        }

        .media-plane {
            position: absolute;
            inset: -5%;
            width: 110%;
            height: 110%;
            object-fit: cover;
            opacity: 0;
            will-change: transform, opacity;
            transform-origin: center center;
        }

        /* No-image placeholder for KG nodes without resolvable artwork */
        .media-empty {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            flex-direction: column; gap: 10px;
            color: rgba(255,255,255,0.25);
            font-family: var(--font-display);
            font-size: 1.1rem;
            text-align: center;
            padding: 20px;
            z-index: 0;
        }
        .media-empty i { font-size: 2.2rem; opacity: 0.5; }

        /* STORY THREAD */
        #story-thread {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            padding: 30px 20px 60vh 20px;
            position: relative;
            z-index: 15;
            background: linear-gradient(180deg, rgba(5,5,8,0.4) 0%, rgba(5,5,8,0.95) 15%);
        }

        @media(min-width: 1024px) {
            #layout { flex-direction: row; }
            #media-stage {
                flex: 0 0 55vw;
                height: 100dvh;
                max-height: none;
                aspect-ratio: auto;
                background: transparent;
                box-shadow: none;
                padding: 40px;
                display: flex; align-items: center; justify-content: center;
            }
            .media-frame {
                position: relative;
                width: 100%; height: 100%;
                border-radius: 16px;
                box-shadow: 0 30px 60px rgba(0,0,0,0.8);
            }
            #story-thread {
                flex: 0 0 45vw;
                height: 100dvh;
                padding: 100px 60px 80vh 40px;
                background: transparent;
            }
        }

        /* --- 3. Story Beats --- */
        .story-beat {
            position: relative;
            margin-bottom: 60px;
            padding-left: 25px;
            opacity: 0.2;
            transform: scale(0.95) translateX(-10px);
            transition: all 0.6s cubic-bezier(0.25, 1, 0.5, 1);
            will-change: transform, opacity;
        }

        .story-beat::before {
            content: ''; position: absolute;
            left: 0; top: 10px; bottom: -70px;
            width: 2px;
            background: rgba(255,255,255,0.08);
        }

        .story-beat::after {
            content: ''; position: absolute;
            left: -4px; top: 10px;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--text-muted);
            border: 2px solid var(--bg-deep);
            transition: all 0.4s;
        }

        .story-beat.active {
            opacity: 1;
            transform: scale(1) translateX(0);
        }
        .story-beat.active::after {
            background: var(--accent);
            box-shadow: 0 0 15px var(--accent);
            transform: scale(1.4);
            border-color: #000;
        }

        .beat-index {
            font-family: monospace;
            color: var(--accent);
            letter-spacing: 0.15em;
            font-size: 0.75rem;
            display: block;
            font-weight: 700;
        }

        .beat-type-pill {
            display: inline-block;
            margin-left: 8px;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: var(--text-muted);
            font-family: monospace;
            vertical-align: middle;
        }

        .beat-title {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 5vw, 2.8rem);
            margin: 0 0 12px 0;
            line-height: 1.1;
            color: #fff;
        }

        /* Rendered markdown body (from kg_nodes.content) */
        .beat-desc {
            font-size: 1rem;
            line-height: 1.7;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        .beat-desc h1, .beat-desc h2, .beat-desc h3 {
            color: #fff;
            font-family: var(--font-display);
            line-height: 1.25;
            margin: 0.9em 0 0.4em;
        }
        .beat-desc h1:first-child, .beat-desc h2:first-child, .beat-desc h3:first-child { margin-top: 0; }
        .beat-desc h1 { font-size: 1.4rem; }
        .beat-desc h2 { font-size: 1.2rem; }
        .beat-desc h3 { font-size: 1.05rem; }
        .beat-desc p { margin: 0 0 0.9em; }
        .beat-desc ul, .beat-desc ol { margin: 0 0 0.9em; padding-left: 1.3em; }
        .beat-desc li { margin-bottom: 0.3em; }
        .beat-desc blockquote {
            margin: 0 0 0.9em;
            padding: 8px 14px;
            border-left: 2px solid var(--accent);
            background: rgba(16,185,129,0.05);
            color: #cbd5e1;
            font-style: italic;
            border-radius: 0 6px 6px 0;
        }
        .beat-desc code {
            background: rgba(255,255,255,0.08);
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 0.85em;
            color: #e2e8f0;
        }
        .beat-desc pre {
            background: rgba(0,0,0,0.4);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 12px 14px;
            overflow-x: auto;
            margin: 0 0 0.9em;
        }
        .beat-desc pre code { background: none; padding: 0; }
        .beat-desc a { color: var(--accent); }
        .beat-desc strong { color: #fff; }
        .beat-desc hr { border: none; border-top: 1px solid var(--glass-border); margin: 1em 0; }

        /* Expandable Text Styles */
        .desc-collapsed { max-height: 220px; overflow: hidden; position: relative; }
        .desc-collapsed::after {
            content: '';
            position: absolute; left: 0; right: 0; bottom: 0; height: 70px;
            background: linear-gradient(180deg, rgba(5,5,8,0) 0%, rgba(5,5,8,0.95) 90%);
            pointer-events: none;
        }
        .read-more-toggle {
            color: var(--accent); cursor: pointer;
            font-weight: 600; font-size: 0.9rem;
            display: inline-flex; align-items: center; gap: 4px;
            margin-top: 4px; transition: color 0.2s;
        }
        .read-more-toggle:hover { color: #fff; }

        /* Optional director-style note for role/reason (from AI sequence item) */
        .director-note {
            background: rgba(0,0,0,0.4);
            border-left: 2px solid #8b5cf6;
            padding: 10px 12px;
            font-size: 0.85rem; line-height: 1.5;
            color: #e2e8f0; font-style: italic;
            border-radius: 0 6px 6px 0;
            margin-bottom: 16px;
        }
        .director-note .role-label {
            display: block; font-style: normal; font-family: monospace;
            font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em;
            color: #a78bfa; margin-bottom: 4px;
        }

        /* --- Beat Navigation Actions --- */
        .btn-beat-action {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        .btn-beat-action:hover {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent);
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 0 10px var(--accent-glow);
        }

        /* --- Intro Header & Navigation Controls --- */
        .seq-header {
            margin-bottom: 80px; padding-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .seq-header-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 15px;
            margin-bottom: 15px; flex-wrap: wrap;
        }
        .header-actions {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .pill {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 4px 10px; border-radius: 12px;
            font-size: 0.75rem; color: #cbd5e1;
            white-space: normal;
            word-break: break-word;
            max-width: 100%;
            line-height: 1.4;
            display: inline-block;
        }
        .pill.hl { border-color: rgba(16, 185, 129, 0.4); color: #34d399; background: rgba(16, 185, 129, 0.05); }

        /* Sequence Navigation */
        .seq-nav {
            display: flex; align-items: center;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px; padding: 2px;
            backdrop-filter: blur(4px);
        }
        .btn-nav-small {
            background: transparent; border: none; color: var(--text-muted);
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.2rem; transition: 0.2s; line-height: 1; padding-bottom: 2px;
        }
        .btn-nav-small:hover:not(:disabled) { color: #fff; background: rgba(255,255,255,0.1); }
        .btn-nav-small:disabled { opacity: 0.3; cursor: not-allowed; }

        .seq-id-input {
            width: 50px; background: transparent; border: none;
            color: var(--accent); text-align: center; font-family: monospace;
            font-size: 0.85rem; font-weight: 700; -moz-appearance: textfield;
        }
        .seq-id-input::-webkit-outer-spin-button, .seq-id-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .seq-id-input:focus { outline: none; color: #fff; border-bottom: 1px solid var(--accent); }

        .seq-header h1 {
            font-family: var(--font-display);
            font-size: clamp(2.2rem, 6vw, 4rem);
            margin: 0; line-height: 1.1;
        }
        .seq-header p.sub {
            color: var(--accent); text-transform: uppercase;
            letter-spacing: 0.2em; font-size: 0.75rem;
            font-weight: 800; margin: 0 0 10px 0;
        }
        .seq-header .desc { color: var(--text-muted); line-height: 1.6; font-size: 1rem; }

        /* --- Iframe Detail Modal --- */
        .f-view-btn {
            position: absolute;
            top: 15px; right: 15px;
            width: 40px; height: 40px;
            background: rgba(0,0,0,0.6); color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; z-index: 50;
            opacity: 0.8; transition: all 0.2s;
            font-size: 18px; backdrop-filter: blur(5px);
        }
        .media-frame:hover .f-view-btn, .f-view-btn:hover {
            opacity: 1; background: var(--accent); color: #000; border-color: var(--accent);
        }

        .view-modal {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.95);
            z-index: 100000; display: none;
            align-items: center; justify-content: center;
            backdrop-filter: blur(10px);
        }
        .view-modal.active { display: flex; }
        .view-modal-content {
            width: 95vw; height: 95vh;
            background: #000; position: relative;
            border: 1px solid var(--glass-border);
            box-shadow: 0 0 40px rgba(0,0,0,0.8);
            border-radius: 8px; overflow: hidden;
        }
        .view-close {
            position: absolute; top: 15px; right: 15px;
            width: 40px; height: 40px;
            background: rgba(0,0,0,0.8); color: #fff;
            border: 1px solid var(--glass-border);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 24px;
            z-index: 200; transition: all 0.2s; backdrop-filter: blur(5px);
        }
        .view-close:hover { background: var(--accent); color: #000; border-color: var(--accent); }
        iframe.frame-viewer { width: 100%; height: 100%; border: none; display: block; }
        
        /* --- Graph Overlay & Modals (from kg_travel) --- */
        .mg-overlay {
            position: absolute; bottom: 20px; left: 20px; width: min(400px, 90vw); height: min(380px, 50vh);
            background: var(--card); border: 1px solid var(--border); border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6); z-index: 1000; display: flex; flex-direction: column;
            overflow: hidden; transition: height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s;
        }
        .mg-overlay.collapsed { height: 42px; opacity: 0.9; }
        .mg-header {
            height: 42px; min-height: 42px; padding: 0 14px; border-bottom: 1px solid var(--border); 
            font-size: 0.85rem; font-weight: 700; display: flex; justify-content: space-between; 
            align-items: center; background: var(--bg); cursor: move; user-select: none; touch-action: none;
            flex-shrink: 0; color: var(--text);
        }
        .mg-toolbar {
            padding: 6px 10px; background: var(--bg); border-bottom: 1px solid var(--border);
            display: flex; gap: 8px; align-items: center; flex-shrink: 0;
            overflow-x: auto; -webkit-overflow-scrolling: touch; color: var(--text);
        }
        .mg-toolbar::-webkit-scrollbar { display: none; }
        .mg-btn {
            display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--card); color: var(--text);
            font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: 0.15s; white-space: nowrap;
        }
        .mg-btn:hover { border-color: var(--accent); color: var(--accent); }
        .mg-btn.active { background: var(--accent); color: #000; border-color: var(--accent); }
        .mg-toolbar select {
            padding: 2px 4px; border-radius: 4px; border: 1px solid var(--border);
            background: var(--card); color: var(--text); font-size: 0.75rem; cursor: pointer;
        }
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

    </style>
</head>
<body>

    <!-- 1. Ambient Background -->
    <div id="ambient-bg">
        <img id="amb-a" class="ambient-layer" src="" alt="">
        <img id="amb-b" class="ambient-layer" src="" alt="">
    </div>

    <!-- 2. Main Layout -->
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
                    <span>No artwork linked to this node</span>
                </div>
            </div>
        </div>

        <!-- STORY THREAD -->
        <div id="story-thread">
            <div class="seq-header">
                <div class="seq-header-top">
                    <div>
                        <p class="sub">Narrative Sequence &middot; KG Nodes</p>
                        <h1 id="ui-seq-name">Title</h1>
                    </div>
                    <div class="header-actions">

                        <span class="pill hl" style="font-family: monospace; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; margin-right: 4px;" title="Total Nodes in Sequence">
                            <?= count($frames) ?> NODES
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
                <span><i class="bi bi-compass"></i> Map</span>
                <i class="bi bi-arrows-move"></i>
            </div>
            <div class="mg-toolbar">
                <span style="font-size:0.75rem; color:var(--text-muted);"><i class="bi bi-bezier2"></i> Hops</span>
                <select id="mgHopsSelect" onchange="if(currentGraphNodeId) loadGraphForNode(currentGraphNodeId)">
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

    <!-- 3. Iframe Modal -->
    <div class="view-modal" id="viewModal">
        <div class="view-modal-content">
            <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
            <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
        </div>
    </div>
    
    <!-- Node Context Modal -->
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

    <!-- Frame Details Modal Included (contains window.showEntityFormInModal etc.) -->
    <?php require __DIR__ . '/modal_frame_details.php'; ?>

    <script>
        const sequenceData = <?= $sequenceJson ?>;
    </script>

    <script>
        const frames = sequenceData.frames || [];

        // State
        let activeIndex = -1;
        let activePlane = 'img-a';
        let continuousPanAnim = null;
        let transitionToken = 0;
        const imageLoadCache = new Map();

        // DOM Elements
        const elImgA = document.getElementById('img-a');
        const elImgB = document.getElementById('img-b');
        const elAmbA = document.getElementById('amb-a');
        const elAmbB = document.getElementById('amb-b');
        const elMediaEmpty = document.getElementById('media-empty');
        const thread = document.getElementById('story-thread');
        const beatsContainer = document.getElementById('beats-container');

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

        // --- 1. Init UI ---
        function init() {
            document.getElementById('ui-seq-name').innerText = sequenceData.name || 'Untitled';
            document.getElementById('ui-seq-desc').innerText = sequenceData.description || 'Scroll through the thread below to experience the sequence.';

            // Build Story Beats from kg_nodes markdown content
            frames.forEach((frame, i) => {
                const hasPrev = i > 0;
                const hasNext = i < frames.length - 1;

                // Render markdown -> HTML for this node's content
                const md = (frame.content_md || '').trim();
                let bodyHtml = '';
                if (md) {
                    bodyHtml = marked.parse(md);
                } else if (frame.description) {
                    bodyHtml = `<p>${escapeHtml(frame.description)}</p>`;
                } else {
                    bodyHtml = `<p style="opacity:0.5;">No content yet for this node.</p>`;
                }

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
                        <span class="beat-index" style="margin-bottom:0;">NODE ${String(i + 1).padStart(2, '0')}<span class="beat-type-pill">${nodeTypeIcon(frame.node_type)} ${frame.node_type || 'note'}</span></span>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top:0, behavior:'smooth'})" title="Back to Top">
                                <i class="bi bi-arrow-up"></i> Top
                            </button>
                            ${hasPrev ? `
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i - 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Previous Node">
                                <i class="bi bi-arrow-up"></i> Prev
                            </button>
                            ` : ''}
                            ${hasNext ? `
                            <button class="btn-beat-action" onclick="document.getElementById('story-thread').scrollTo({top: document.getElementById('beat-${i + 1}').offsetTop - 20, behavior:'smooth'})" title="Scroll to Next Node">
                                Next <i class="bi bi-arrow-down"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    <h2 class="beat-title">${frame.name || 'Untitled Node'}</h2>
                    ${directorHtml}
                    <div class="beat-desc-wrap">
                        <div class="beat-desc desc-collapsed" id="desc-${i}">${bodyHtml}</div>
                        <span class="read-more-toggle" id="toggle-${i}" onclick="toggleDesc(event, ${i})" style="display:none;">
                            Read more <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                `;
                beatsContainer.appendChild(beat);
            });

            // After render, check which beats actually overflow and need a toggle
            frames.forEach((frame, i) => {
                const descEl = document.getElementById(`desc-${i}`);
                const toggleEl = document.getElementById(`toggle-${i}`);
                if (descEl && toggleEl && descEl.scrollHeight > descEl.clientHeight + 4) {
                    toggleEl.style.display = 'inline-flex';
                } else if (descEl) {
                    descEl.classList.remove('desc-collapsed');
                }
            });

            if (frames.length > 0) {
                const firstThumb = frames[0].thumb || '';
                if (firstThumb) {
                    elImgA.src = firstThumb;
                    elAmbA.src = firstThumb;
                    elImgA.style.opacity = 1;
                    elAmbA.style.opacity = 1;
                    elImgA.style.transform = "scale(1)";
                } else {
                    elMediaEmpty.style.display = 'flex';
                }
            }

            setupIntersectionObserver();
            
            // Init draggable graph modal
            makeDraggable('mgOverlay', 'mgHeader');
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // --- 2. Expandable Text Logic ---
        window.toggleDesc = (e, idx) => {
            e.stopPropagation();
            const descEl = document.getElementById(`desc-${idx}`);
            const toggleEl = document.getElementById(`toggle-${idx}`);
            const isCollapsed = descEl.classList.contains('desc-collapsed');

            if (isCollapsed) {
                descEl.classList.remove('desc-collapsed');
                toggleEl.innerHTML = 'Show less <i class="bi bi-chevron-up"></i>';
            } else {
                descEl.classList.add('desc-collapsed');
                toggleEl.innerHTML = 'Read more <i class="bi bi-chevron-down"></i>';
            }
        };

        // --- 3. Scroll Observation ---
        function setupIntersectionObserver() {
            const options = {
                root: thread,
                rootMargin: '-30% 0px -40% 0px',
                threshold: 0
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const idx = parseInt(entry.target.dataset.index);
                        activateBeat(idx);
                    }
                });
            }, options);

            document.querySelectorAll('.story-beat').forEach(beat => observer.observe(beat));
        }

        // --- 4. Anime.js Visual Transitions ---
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
            if (newFrame && newFrame.id && newFrame.id !== currentGraphNodeId) {
                loadGraphForNode(newFrame.id);
            }

            if (!newFrame || !newFrame.thumb) {
                elMediaEmpty.style.display = 'flex';
                elImgA.style.opacity = 0;
                elImgB.style.opacity = 0;
                elAmbA.style.opacity = 0;
                elAmbB.style.opacity = 0;
                return;
            }
            elMediaEmpty.style.display = 'none';

            const myToken = ++transitionToken;

            await preloadFrameImage(newFrame.thumb);
            if (myToken !== transitionToken) return;

            const previousPlaneId = activePlane;
            const oldPlane = previousPlaneId === 'img-a' ? elImgA : elImgB;
            const newPlane = previousPlaneId === 'img-a' ? elImgB : elImgA;
            const oldAmb = previousPlaneId === 'img-a' ? elAmbA : elAmbB;
            const newAmb = previousPlaneId === 'img-a' ? elAmbB : elAmbA;

            activePlane = newPlane.id;

            newPlane.src = newFrame.thumb;
            newAmb.src = newFrame.thumb;

            newPlane.style.zIndex = 2;
            oldPlane.style.zIndex = 1;

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

            tl.finished.then(() => {
                if (myToken === transitionToken) {
                    startContinuousPan(newPlane);
                }
            });
        }

        function startContinuousPan(targetElement) {
            const panX = (Math.random() > 0.5 ? 2 : -2) + '%';
            const panY = (Math.random() > 0.5 ? 2 : -2) + '%';

            continuousPanAnim = anime({
                targets: targetElement,
                scale: [1, 1.05],
                translateX: [0, panX],
                translateY: [0, panY],
                duration: 15000,
                easing: 'linear',
                direction: 'alternate',
                loop: true
            });
        }

        // --- 5. Sequence Navigation ---
        window.navSeq = (id) => {
            if (id) window.location.href = '?id=' + id;
        };

        window.jumpToSeq = (id) => {
            const cleanId = parseInt(id);
            if (!isNaN(cleanId) && cleanId > 0) {
                window.location.href = '?id=' + cleanId;
            }
        };

        // --- 6. Iframe Modal Logic ---
        window.openFrameModal = (id) => {
            if (!id) return;
            document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
            document.getElementById('viewModal').classList.add('active');
        };

        window.closeFrameModal = () => {
            document.getElementById('viewModal').classList.remove('active');
            setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
        };

        window.openCurrentFrame = () => {
            if (activeIndex === -1) return;
            const f = frames[activeIndex];
            if (!f) return;
            const targetId = f._active_frame_id;
            if (!targetId) return;
            openFrameModal(targetId);
        };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const viewModal = document.getElementById('viewModal');
                if (viewModal && viewModal.classList.contains('active')) {
                    closeFrameModal();
                }
                const ctxModal = document.getElementById('nodeContextModal');
                if (ctxModal && ctxModal.style.display === 'flex') {
                    ctxModal.style.display = 'none';
                }
            }
        });
        
        // --- 7. Graph Overlay Logic ---
        let graph = null;
        let renderer = null;
        let isLayoutRunning = false;
        let fa2LoopId = null;
        let selectedNodeId = null;
        let currentGraphNodeId = null;

        const TYPE_COLORS = {
            note: '#64748b', relationship: '#ec4899', character: '#3b82f6',
            location: '#10b981', event: '#ef4444', concept: '#f59e0b',
            arc: '#8b5cf6', episode: '#06b6d4', default: '#888888'
        };

        function makeDraggable(panelId, handleId) {
            const panel = document.getElementById(panelId);
            const handle = document.getElementById(handleId);
            let isDragging = false;
            let startX, startY, initialX, initialY;
            let lastTouchTime = 0;

            function start(e) {
                if(e.target.closest('select') || e.target.closest('button')) return;
                
                if (e.type === 'touchstart') {
                    lastTouchTime = Date.now();
                } else if (e.type === 'mousedown' && Date.now() - lastTouchTime < 500) {
                    return;
                }

                isDragging = false;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                startX = clientX; startY = clientY;

                const rect = panel.getBoundingClientRect();
                initialX = rect.left; 
                initialY = rect.top;

                panel.style.bottom = 'auto';
                panel.style.right = 'auto';
                panel.style.left = initialX + 'px';
                panel.style.top = initialY + 'px';
                panel.style.transition = 'none';

                document.addEventListener('mousemove', move, {passive: false});
                document.addEventListener('mouseup', end);
                document.addEventListener('touchmove', move, {passive: false});
                document.addEventListener('touchend', end);
            }

            function move(e) {
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                const dx = clientX - startX;
                const dy = clientY - startY;

                if (!isDragging && Math.sqrt(dx*dx + dy*dy) > 8) {
                    isDragging = true;
                }

                if (isDragging) {
                    e.preventDefault();
                    panel.style.left = (initialX + dx) + 'px';
                    panel.style.top  = (initialY + dy) + 'px';
                }
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

            handle.addEventListener('mousedown', start);
            handle.addEventListener('touchstart', start, {passive: true});
        }
        
        function loadGraphForNode(nodeId) {
            const hops = parseInt(document.getElementById('mgHopsSelect').value) || 1;
            fetch('kg_travel_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'travel_context', node_id: nodeId, hops: hops })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return;
                currentGraphNodeId = res.focal_node.id;
                selectNode(null); 
                renderMiniGraph(res.graph, currentGraphNodeId);
            });
        }

        function renderMiniGraph(graphData, focalId) {
            if (graph) graph.clear();
            else graph = new graphology.MultiDirectedGraph();

            graphData.nodes.forEach(n => {
                const isFocal = n.id == focalId;
                graph.addNode(n.id.toString(), {
                    x: n.x !== undefined ? n.x : Math.random() * 10,
                    y: n.y !== undefined ? n.y : Math.random() * 10,
                    size: isFocal ? 16 : 8,
                    label: n.name,
                    color: isFocal ? '#10b981' : (TYPE_COLORS[n.node_type] || '#888888'),
                    is_focal: isFocal
                });
            });

            graphData.edges.forEach(e => {
                const s = e.source.toString();
                const t = e.target.toString();
                if (graph.hasNode(s) && graph.hasNode(t) && s !== t) {
                    try { graph.addDirectedEdge(s, t, { label: e.relationship, size:1, color:'#666' }); } catch(err){}
                }
            });

            graph.forEachNode(node => {
                const isFocal = graph.getNodeAttribute(node, 'is_focal');
                const deg = graph.degree(node);
                graph.setNodeAttribute(node, 'size', isFocal ? 16 : (8 + Math.sqrt(deg) * 2));
            });

            if (!renderer) {
                const container = document.getElementById('mgContainer');
                renderer = new Sigma(graph, container, {
                    renderEdgeLabels: false,
                    defaultEdgeType: 'arrow',
                    allowInvalidContainer: true,
                    labelRenderedSizeThreshold: 2, 
                    labelColor: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#c9d1d9' : '#24292f' }
                });
                
                renderer.on('clickStage', () => { selectNode(null); });
                
                let dragNode = null, dragFrame = null, dragStartX = 0, dragStartY = 0;
                
                renderer.on('downNode', e => { 
                    dragNode = e.node; 
                    const ne = e.event.original || e.event;
                    dragStartX = ne.touches ? ne.touches[0].clientX : (ne.clientX || 0);
                    dragStartY = ne.touches ? ne.touches[0].clientY : (ne.clientY || 0);
                    renderer.getCamera().disable(); 
                });
                
                container.addEventListener('touchmove', e => {
                    if (!dragNode) return;
                    e.preventDefault();
                    const rect = container.getBoundingClientRect();
                    const touch = e.touches[0];
                    if (dragFrame) cancelAnimationFrame(dragFrame);
                    dragFrame = requestAnimationFrame(() => {
                        const pos = renderer.viewportToGraph({ x: touch.clientX - rect.left, y: touch.clientY - rect.top });
                        graph.setNodeAttribute(dragNode, 'x', pos.x);
                        graph.setNodeAttribute(dragNode, 'y', pos.y);
                        dragFrame = null;
                    });
                }, { passive: false });

                renderer.getMouseCaptor().on('mousemovebody', e => {
                    if(!dragNode) return;
                    e.preventSigmaDefault(); e.original.preventDefault();
                    if(dragFrame) cancelAnimationFrame(dragFrame);
                    dragFrame = requestAnimationFrame(() => {
                        const pos = renderer.viewportToGraph(e);
                        graph.setNodeAttribute(dragNode, 'x', pos.x);
                        graph.setNodeAttribute(dragNode, 'y', pos.y);
                        dragFrame = null;
                    });
                });
                
                function releaseNode(e) {
                    if (!dragNode) return;
                    let endX = 0, endY = 0;
                    if (e.changedTouches && e.changedTouches.length) {
                        endX = e.changedTouches[0].clientX;
                        endY = e.changedTouches[0].clientY;
                    } else {
                        endX = e.clientX || dragStartX;
                        endY = e.clientY || dragStartY;
                    }
                    const dx = endX - dragStartX;
                    const dy = endY - dragStartY;
                    if (Math.sqrt(dx * dx + dy * dy) < 6) { 
                        selectNode(dragNode);
                    }
                    renderer.getCamera().enable();
                    dragNode = null;
                }
                window.addEventListener('mouseup', releaseNode);
                window.addEventListener('touchend', releaseNode);

                let hoveredNode = null;
                renderer.setSetting('nodeReducer', (node, data) => {
                    const res = { ...data };
                    let isDimmed = false;

                    if (hoveredNode && hoveredNode !== node && !graph.hasEdge(node, hoveredNode) && !graph.hasEdge(hoveredNode, node)) {
                        isDimmed = true;
                    } else if (selectedNodeId && selectedNodeId !== node && !graph.hasEdge(node, selectedNodeId) && !graph.hasEdge(selectedNodeId, node)) {
                        isDimmed = true;
                    }

                    if (isDimmed) {
                        res.color = '#444'; res.zIndex = 0;
                    } else {
                        res.zIndex = data.is_focal ? 3 : 1;
                    }
                    
                    if (node === hoveredNode || node === selectedNodeId) res.zIndex = 2;
                    if (data.is_focal || node === selectedNodeId) res.highlighted = true;
                    
                    return res;
                });
                
                renderer.setSetting('edgeReducer', (edge, data) => {
                    const res = { ...data };
                    if (hoveredNode && graph.source(edge) !== hoveredNode && graph.target(edge) !== hoveredNode) {
                        res.color = '#333'; res.hidden = true;
                    } else if (selectedNodeId && graph.source(edge) !== selectedNodeId && graph.target(edge) !== selectedNodeId) {
                        res.color = '#333'; res.hidden = true;
                    } else if (hoveredNode || selectedNodeId) {
                        res.size = 2; res.color = '#888';
                    }
                    return res;
                });

                renderer.on('enterNode', ({ node }) => { hoveredNode = node; renderer.refresh(); });
                renderer.on('leaveNode', () => { hoveredNode = null; renderer.refresh(); });
                
            } else {
                renderer.refresh();
            }

            if (!graphData.nodes.some(n => n.x !== undefined)) {
                const fa2 = graphologyLibrary.layoutForceAtlas2;
                fa2.assign(graph, { iterations: 120, settings: { barnesHutOptimize: false, gravity: 0.1, scalingRatio: 2 } });
                renderer.refresh();
            }
            
            resetGraphCamera();
        }

        function resetGraphCamera() {
            if(!renderer) return;
            renderer.getCamera().animatedReset({ duration: 300 });
            setTimeout(() => {
                const cam = renderer.getCamera();
                //cam.animatedZoom({ ratio: cam.ratio * 0.5, duration: 300 });
            }, 320);
        }

        function toggleLayout() {
            if(!graph || !renderer) return;
            const btn = document.getElementById('btnLayout');
            if (isLayoutRunning) {
                cancelAnimationFrame(fa2LoopId);
                isLayoutRunning = false;
                btn.innerHTML = '<i class="bi bi-play-fill"></i> Lyt';
                btn.classList.remove('active');
            } else {
                isLayoutRunning = true;
                btn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop';
                btn.classList.add('active');
                const fa2 = graphologyLibrary.layoutForceAtlas2;
                const settings = { barnesHutOptimize: graph.order > 50, gravity: 0.05, scalingRatio: 8, slowDown: 5 };
                
                function step() {
                    fa2.assign(graph, { iterations: 1, settings });
                    renderer.refresh();
                    if (isLayoutRunning) fa2LoopId = requestAnimationFrame(step);
                }
                step();
            }
        }
        
        function selectNode(nodeId) {
            selectedNodeId = nodeId;
            if (renderer) renderer.refresh();
            
            const sep = document.getElementById('mgActionSep');
            const btnT = document.getElementById('btnTravel');
            const btnD = document.getElementById('btnDetails');
            
            if (nodeId) {
                sep.style.display = 'block';
                btnT.style.display = 'inline-flex';
                btnD.style.display = 'inline-flex';
            } else {
                sep.style.display = 'none';
                btnT.style.display = 'none';
                btnD.style.display = 'none';
            }
        }

        function doTravelSelected() {
            if (selectedNodeId) loadGraphForNode(selectedNodeId);
        }

        function doDetailsSelected() {
            if (selectedNodeId) openNodeContextModal(selectedNodeId);
        }
        
        function openNodeContextModal(nodeId) {
            const nodeAttrs = graph.getNodeAttributes(nodeId);
            document.getElementById('modalTitle').textContent = nodeAttrs.label;
            
            document.getElementById('btnTravelHere').onclick = () => {
                loadGraphForNode(nodeId);
                document.getElementById('nodeContextModal').style.display = 'none';
            };
            
            document.getElementById('btnTravelView').href = 'kg_travel.php?node_id=' + nodeId;

            // Reset and hide the Edit button initially
            const btnEditEntity = document.getElementById('btnEditEntity');
            btnEditEntity.style.display = 'none';
            btnEditEntity.onclick = null;
            
            // Check if there's a sketch associated with this node to enable editing
            fetch('kg_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'fetch_visuals', entity_name: nodeAttrs.label })
            })
            .then(r => r.json())
            .then(res => {
                if (res.ok && res.sketch && res.sketch.id) {
                    btnEditEntity.onclick = () => {
                        if(typeof window.showEntityFormInModal === 'function') {
                            window.showEntityFormInModal('sketches', res.sketch.id);
                        }
                    };
                    btnEditEntity.style.display = 'inline-flex';
                }
            })
            .catch(e => console.error("Error fetching sketch details for edit button", e));

            const textContent = document.getElementById('modalTextContent');
            const connContent = document.getElementById('modalConnections');
            
            textContent.innerHTML = '<i>Loading context payload...</i>';
            connContent.innerHTML = '';
            document.getElementById('nodeContextModal').style.display = 'flex';

            fetch('kg_api.php?action=get_node&id=' + nodeId)
                .then(r => r.json())
                .then(res => {
                    if(!res.ok) { textContent.innerHTML = 'Failed to load content.'; return; }
                    
                    const md = (res.node && res.node.content) ? res.node.content.trim() : '';
                    textContent.innerHTML = md ? marked.parse(md) : '<i>This node contains no markdown content.</i>';
                    
                    const outgoing = [], incoming = [];
                    graph.forEachOutboundEdge(nodeId, (e, a, s, t) => {
                        if(t !== nodeId) outgoing.push({ id: t, label: graph.getNodeAttribute(t, 'label'), rel: a.label });
                    });
                    graph.forEachInboundEdge(nodeId, (e, a, s, t) => {
                        if(s !== nodeId) incoming.push({ id: s, label: graph.getNodeAttribute(s, 'label'), rel: a.label });
                    });
                    
                    let html = '';
                    if(outgoing.length > 0) {
                        html += `<div class="conn-section"><h4>Outgoing (${outgoing.length})</h4><div class="conn-list">`;
                        outgoing.forEach(n => html += `<button class="conn-pill" onclick="openNodeContextModal('${n.id}')">${escapeHtml(n.label)} ${n.rel ? '<span class="conn-rel">'+escapeHtml(n.rel)+'</span>' : ''}</button>`);
                        html += `</div></div>`;
                    }
                    if(incoming.length > 0) {
                        html += `<div class="conn-section"><h4>Incoming (${incoming.length})</h4><div class="conn-list">`;
                        incoming.forEach(n => html += `<button class="conn-pill" onclick="openNodeContextModal('${n.id}')">${escapeHtml(n.label)} ${n.rel ? '<span class="conn-rel">'+escapeHtml(n.rel)+'</span>' : ''}</button>`);
                        html += `</div></div>`;
                    }
                    connContent.innerHTML = html;
                });
        }

        // Start everything up
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
<?php
$htmlContent = ob_get_clean();
echo $htmlContent;
?>