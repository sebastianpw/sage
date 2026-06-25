<?php
// public/mini_graph.php
// Standalone mini-graph view: renders a focused subgraph around a single node.
//
// GET params:
//   graph   = 'kg' (default) | 'ag'
//   node_id = int  — the focal node ID
//   doc_id  = int  — required when graph=ag
//   hops    = int  — neighbourhood depth (default 1, max 4)
//
// Designed to be embedded in an iframe (e.g. via showMiniGraphModal() in
// modal_frame_details.php).  It is also directly browsable as a standalone page.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw    = \App\Core\SpwBase::getInstance();
$pdo    = $spw->getPDO();

$graphType = trim($_GET['graph']  ?? 'kg');
$nodeId    = (int)($_GET['node_id'] ?? 0);
$docId     = (int)($_GET['doc_id']  ?? 0);
$hops      = max(1, min(4, (int)($_GET['hops'] ?? 1)));

if (!in_array($graphType, ['kg', 'ag'], true)) {
    $graphType = 'kg';
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * BFS: collect node IDs within $hops edges of $startId.
 * Works for both graph types by accepting PDO callables.
 */
function collectNeighbourhood(
    \PDO   $pdo,
    string $graphType,
    int    $startId,
    int    $hops,
    int    $docId
): array {
    $visited = [$startId => true];
    $frontier = [$startId];

    for ($h = 0; $h < $hops; $h++) {
        if (empty($frontier)) break;
        $ph = implode(',', array_fill(0, count($frontier), '?'));

        if ($graphType === 'kg') {
            // Outgoing (node_id → item_id)
            $stmt = $pdo->prepare(
                "SELECT DISTINCT item_id AS neighbour
                 FROM kg_node_items
                 WHERE item_type = 'kg_node'
                   AND item_id IS NOT NULL
                   AND node_id IN ($ph)"
            );
            $stmt->execute($frontier);
            $out = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Incoming (item_id → node_id)
            $stmt = $pdo->prepare(
                "SELECT DISTINCT node_id AS neighbour
                 FROM kg_node_items
                 WHERE item_type = 'kg_node'
                   AND item_id IN ($ph)"
            );
            $stmt->execute($frontier);
            $in = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            // AG — edges resolved by label match; use item_id when available
            $stmt = $pdo->prepare(
                "SELECT DISTINCT an.id AS neighbour
                 FROM ag_node_items ani
                 JOIN ag_nodes an ON an.doc_id = ani.doc_id
                    AND (
                          (ani.item_id IS NOT NULL AND an.id = ani.item_id)
                       OR (ani.item_label IS NOT NULL AND an.name = ani.item_label)
                    )
                 WHERE ani.doc_id = ?
                   AND ani.node_id IN ($ph)
                   AND an.status = 'active'"
            );
            $stmt->execute(array_merge([$docId], $frontier));
            $out = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare(
                "SELECT DISTINCT ani.node_id AS neighbour
                 FROM ag_node_items ani
                 JOIN ag_nodes src ON src.id IN ($ph) AND src.doc_id = ani.doc_id
                    AND (
                          (ani.item_id IS NOT NULL AND ani.item_id = src.id)
                       OR (ani.item_label IS NOT NULL AND ani.item_label = src.name)
                    )
                 WHERE ani.doc_id = ?"
            );
            $stmt->execute(array_merge($frontier, [$docId]));
            $in = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        $newFrontier = [];
        foreach (array_merge($out, $in) as $nid) {
            $nid = (int)$nid;
            if ($nid && !isset($visited[$nid])) {
                $visited[$nid] = true;
                $newFrontier[] = $nid;
            }
        }
        $frontier = $newFrontier;
    }

    return array_keys($visited);
}

// ── Data fetch ────────────────────────────────────────────────────────────────

$focalNode = null;
$dbNodes   = [];
$dbEdges   = [];
$errorMsg  = null;

if ($nodeId <= 0) {
    $errorMsg = 'No node_id specified.';
} elseif ($graphType === 'ag' && $docId <= 0) {
    $errorMsg = 'doc_id is required for AG graph.';
} else {
    // Verify focal node exists
    if ($graphType === 'kg') {
        $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id = ? AND status = 'active'");
        $stmt->execute([$nodeId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, node_type FROM ag_nodes WHERE id = ? AND doc_id = ? AND status = 'active'");
        $stmt->execute([$nodeId, $docId]);
    }
    $focalNode = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$focalNode) {
        $errorMsg = 'Node #' . $nodeId . ' not found.';
    } else {
        $ids = collectNeighbourhood($pdo, $graphType, $nodeId, $hops, $docId);

        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));

            if ($graphType === 'kg') {
                $stmt = $pdo->prepare(
                    "SELECT id, name, node_type
                     FROM kg_nodes
                     WHERE id IN ($ph) AND status = 'active'"
                );
                $stmt->execute($ids);
                $dbNodes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Only edges whose both endpoints are in the subgraph
                $stmt = $pdo->prepare(
                    "SELECT id, node_id AS source, item_id AS target, relationship, item_label
                     FROM kg_node_items
                     WHERE item_type = 'kg_node'
                       AND item_id IS NOT NULL
                       AND node_id IN ($ph)
                       AND item_id IN ($ph)"
                );
                $stmt->execute(array_merge($ids, $ids));
                $dbEdges = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT id, name, node_type, doc_id
                     FROM ag_nodes
                     WHERE doc_id = ? AND id IN ($ph) AND status = 'active'"
                );
                $stmt->execute(array_merge([$docId], $ids));
                $dbNodes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Build a name→id map for label-based edge resolution
                $nameToId = [];
                foreach ($dbNodes as $n) {
                    $nameToId[strtolower($n['name'])] = (int)$n['id'];
                }

                $stmt = $pdo->prepare(
                    "SELECT ani.id, ani.node_id AS source, ani.item_id, ani.item_label, ani.relationship
                     FROM ag_node_items ani
                     WHERE ani.doc_id = ? AND ani.node_id IN ($ph)"
                );
                $stmt->execute(array_merge([$docId], $ids));
                $rawEdges = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $idsSet = array_fill_keys($ids, true);
                foreach ($rawEdges as $e) {
                    $targetId = null;
                    if (!empty($e['item_id']) && isset($idsSet[(int)$e['item_id']])) {
                        $targetId = (int)$e['item_id'];
                    } elseif (!empty($e['item_label'])) {
                        $key = strtolower($e['item_label']);
                        if (isset($nameToId[$key]) && isset($idsSet[$nameToId[$key]])) {
                            $targetId = $nameToId[$key];
                        }
                    }
                    if ($targetId) {
                        $dbEdges[] = [
                            'id'           => $e['id'],
                            'source'       => (int)$e['source'],
                            'target'       => $targetId,
                            'relationship' => $e['relationship'] ?? '',
                            'item_label'   => $e['item_label'] ?? '',
                        ];
                    }
                }
            }
        }
    }
}

// === PRECOMPUTE LAYOUT VIA PYTHON TO SAVE CLIENT MEMORY ===
if (!empty($dbNodes)) {
    $pyapiUrl = $GLOBALS['WORDNET_PYAPI_URL'] ?? 'http://127.0.0.1:8009';
    $payload = json_encode(['nodes' => $dbNodes, 'edges' => $dbEdges, 'iterations' => 150]);
    $ch = curl_init(rtrim($pyapiUrl, '/') . '/graph/layout');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // Extremely fast timeout to prevent blocking if pyapi is disconnected
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $layoutData = json_decode($response, true);
        if (!empty($layoutData['positions'])) {
            $pos = $layoutData['positions'];
            foreach ($dbNodes as &$n) {
                if (isset($pos[$n['id']])) {
                    $n['x'] = $pos[$n['id']]['x'];
                    $n['y'] = $pos[$n['id']]['y'];
                }
            }
        }
    }
}

$jsonNodes    = json_encode($dbNodes,  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsonEdges    = json_encode($dbEdges,  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsonFocal    = json_encode($focalNode ? (int)$focalNode['id'] : null);
$pageTitle    = $focalNode ? 'Mini Graph — ' . htmlspecialchars($focalNode['name']) : 'Mini Graph';

// Build full-graph links (used in UI)
if ($graphType === 'kg') {
    $fullGraphUrl = 'kg_graph.php';
    $editorUrl    = $focalNode ? 'kg_view.php?node_id=' . $nodeId : 'kg_view.php';
} else {
    $fullGraphUrl = 'ag_graph.php?doc_id=' . $docId;
    $editorUrl    = $focalNode ? 'ag_view.php?doc_id=' . $docId . '&node_id=' . $nodeId : 'ag_view.php?doc_id=' . $docId;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>

<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        else if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch(e) {}
})();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* ── Variables ── */
:root {
    --bg: #f6f8fa; --card: #ffffff; --border: #d0d7de;
    --text: #24292f; --text-muted: #57606a; --accent: #0969da;
    --green: #238636; --red: #da3633; --orange: #f59e0b;
    --amber: #d97706;
}
:root[data-theme="dark"] {
    --bg: #0d1117; --card: #161b22; --border: #30363d;
    --text: #c9d1d9; --text-muted: #8b949e; --accent: #58a6ff;
}
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
        --bg: #0d1117; --card: #161b22; --border: #30363d;
        --text: #c9d1d9; --text-muted: #8b949e; --accent: #58a6ff;
    }
}

*, *::before, *::after { box-sizing: border-box; }

html, body {
    margin: 0; padding: 0;
    height: 100%; overflow: hidden;
    background: var(--bg); color: var(--text);
    font-family: system-ui, -apple-system, sans-serif;
    font-size: 14px;
}

/* ── Layout ── */
.mg-layout {
    display: flex; flex-direction: column; height: 100vh;
}

.mg-topbar {
    height: 44px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 12px; gap: 8px;
    flex-shrink: 0; z-index: 10;
}
.mg-topbar-title {
    font-size: 0.88rem; font-weight: 700;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    flex: 1;
    display: flex; align-items: center; gap: 6px;
}
.mg-topbar-title .focal-badge {
    font-size: 0.7rem; font-weight: 600;
    background: rgba(var(--accent-rgb, 9, 105, 218), 0.12);
    color: var(--accent);
    border: 1px solid rgba(var(--accent-rgb, 9, 105, 218), 0.25);
    padding: 1px 6px; border-radius: 8px;
}

.mg-graph-area {
    flex: 1; position: relative; overflow: hidden;
}
#graph-container {
    width: 100%; height: 100%; outline: none;
    background: var(--bg);
}

/* ── Toolbar overlay ── */
.mg-toolbar {
    position: absolute; bottom: 10px; left: 50%;
    transform: translateX(-50%);
    display: flex; gap: 6px; align-items: center;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 5px 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    z-index: 50;
    flex-wrap: nowrap;
    white-space: nowrap;
}

.mg-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 9px; border-radius: 12px;
    border: 1px solid var(--border);
    background: var(--bg); color: var(--text);
    font-size: 0.78rem; font-weight: 600;
    cursor: pointer; text-decoration: none;
    transition: border-color 0.15s, color 0.15s;
    white-space: nowrap;
}
.mg-btn:hover { border-color: var(--accent); color: var(--accent); }
.mg-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

.mg-sep { width: 1px; height: 16px; background: var(--border); margin: 0 2px; }

/* ── Hops selector ── */
.mg-hops-wrap {
    display: flex; align-items: center; gap: 4px;
    font-size: 0.78rem; color: var(--text-muted);
}
.mg-hops-wrap select {
    padding: 2px 4px; border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg); color: var(--text);
    font-size: 0.78rem; cursor: pointer;
}

/* ── Node type pills ── */
.mg-type-pill {
    font-size: 0.65rem; font-weight: 700;
    padding: 1px 5px; border-radius: 6px;
    white-space: nowrap; display: inline-block;
}
.mg-pill-character    { background:rgba(59,130,246,.12);  color:#3b82f6; border:1px solid rgba(59,130,246,.25); }
.mg-pill-location     { background:rgba(16,185,129,.12);  color:#10b981; border:1px solid rgba(16,185,129,.25); }
.mg-pill-concept      { background:rgba(245,158,11,.12);  color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
.mg-pill-event        { background:rgba(239,68,68,.12);   color:#ef4444; border:1px solid rgba(239,68,68,.25); }
.mg-pill-arc          { background:rgba(139,92,246,.12);  color:#8b5cf6; border:1px solid rgba(139,92,246,.25); }
.mg-pill-episode      { background:rgba(6,182,212,.12);   color:#06b6d4; border:1px solid rgba(6,182,212,.25); }
.mg-pill-note         { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }
.mg-pill-relationship { background:rgba(236,72,153,.12);  color:#ec4899; border:1px solid rgba(236,72,153,.25); }
.mg-pill-default      { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }

/* ── Node tooltip/panel ── */
.mg-node-panel {
    position: absolute; top: 10px; right: 10px;
    width: 210px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 8px; padding: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 100; display: none;
    font-size: 0.83rem;
}
.mg-node-panel-name {
    font-weight: 700; font-size: 0.9rem;
    margin-bottom: 6px; word-break: break-word;
}
.mg-node-panel-actions {
    display: flex; flex-direction: column; gap: 5px; margin-top: 10px;
}
.mg-node-panel-actions a,
.mg-node-panel-actions button {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 9px; border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg); color: var(--text);
    font-size: 0.78rem; font-weight: 600;
    cursor: pointer; text-decoration: none;
    transition: border-color 0.15s, color 0.15s;
    width: 100%;
}
.mg-node-panel-actions a:hover,
.mg-node-panel-actions button:hover {
    border-color: var(--accent); color: var(--accent);
}
.mg-node-panel-close {
    position: absolute; top: 6px; right: 8px;
    background: none; border: none; color: var(--text-muted);
    font-size: 1rem; cursor: pointer; line-height: 1; padding: 2px;
}
.mg-node-panel-close:hover { color: var(--text); }

/* ── Stats badge ── */
.mg-stats {
    font-size: 0.72rem; color: var(--text-muted);
    display: flex; gap: 8px;
}

/* ── Error state ── */
.mg-error {
    display: flex; align-items: center; justify-content: center;
    flex: 1; flex-direction: column; gap: 8px;
    color: var(--text-muted);
}
.mg-error i { font-size: 2rem; }

/* ── Focus ring on focal node (applied via Sigma nodeReducer) ── */

/* ── Details modal ── */
.mg-details-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    display: none; align-items: flex-start; justify-content: center;
    z-index: 9998; overflow-y: auto;
    padding: 20px; box-sizing: border-box;
}
.mg-details-bg.open { display: flex; }
.mg-details-inner {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 12px; width: 100%; max-width: 700px;
    min-height: 200px; margin: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    display: flex; flex-direction: column; overflow: hidden;
}
.mg-details-header {
    padding: 10px 14px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 8px;
    background: var(--bg); flex-shrink: 0;
    position: sticky; top: 0; z-index: 2;
}
.mg-details-header-nav {
    background: none; border: 1px solid var(--border);
    color: var(--text-muted); border-radius: 5px;
    padding: 3px 8px; cursor: pointer; font-size: 1rem;
    line-height: 1; opacity: 0.35;
}
.mg-details-header-nav:not(:disabled) { opacity: 1; }
.mg-details-title {
    font-weight: 700; font-size: 0.95rem;
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mg-details-close {
    background: none; border: none; color: var(--text-muted);
    font-size: 1.4rem; cursor: pointer; line-height: 1;
    padding: 2px 6px; border-radius: 4px; flex-shrink: 0;
}
.mg-details-close:hover { color: var(--text); }
.mg-details-body {
    padding: 20px 24px; overflow-y: auto; flex: 1;
    font-size: 0.93rem; line-height: 1.7; color: var(--text);
}
/* markdown styles scoped to details body */
.mg-details-body h1,.mg-details-body h2,.mg-details-body h3,
.mg-details-body h4,.mg-details-body h5,.mg-details-body h6 {
    margin: 1.2em 0 0.4em; font-weight: 700; line-height: 1.3;
}
.mg-details-body h1 { font-size:1.4rem; border-bottom:1px solid var(--border); padding-bottom:5px; }
.mg-details-body h2 { font-size:1.15rem; border-bottom:1px solid var(--border); padding-bottom:4px; }
.mg-details-body h3 { font-size:1rem; }
.mg-details-body p { margin: 0 0 0.9em; }
.mg-details-body ul,.mg-details-body ol { margin:0 0 0.9em; padding-left:1.6em; }
.mg-details-body li { margin-bottom: 0.3em; }
.mg-details-body blockquote {
    margin: 0 0 0.9em; padding: 8px 14px;
    border-left: 3px solid var(--accent);
    background: rgba(9,105,218,0.06); border-radius: 0 6px 6px 0;
    color: var(--text-muted);
}
.mg-details-body code {
    font-family: ui-monospace,monospace; font-size: 0.85em;
    background: var(--bg); border: 1px solid var(--border);
    padding: 1px 5px; border-radius: 4px;
}
.mg-details-body pre {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 6px; padding: 12px 14px; overflow-x: auto;
    margin: 0 0 0.9em;
}
.mg-details-body pre code { background: none; border: none; padding: 0; }
.mg-details-body hr { border: none; border-top: 1px solid var(--border); margin: 1.2em 0; }
.mg-details-body a { color: var(--accent); }
.mg-details-body table {
    border-collapse: collapse; width: 100%; margin-bottom: 0.9em; font-size: 0.88rem;
}
.mg-details-body th,.mg-details-body td {
    border: 1px solid var(--border); padding: 6px 10px; text-align: left;
}
.mg-details-body th { background: var(--bg); font-weight: 700; }
.mg-details-body .mg-empty {
    color: var(--text-muted); font-style: italic; text-align: center; padding: 30px 0;
}
/* connections section */
.mg-conn-section { margin-top: 24px; border-top: 1px solid var(--border); padding-top: 14px; }
.mg-conn-section h4 {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--text-muted); margin: 0 0 8px;
}
.mg-conn-list { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 14px; }
.mg-conn-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 20px; font-size: 0.76rem; font-weight: 600;
    border: 1px solid var(--border); background: var(--bg);
    cursor: pointer; color: var(--text);
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.mg-conn-pill:hover { border-color: var(--accent); color: var(--accent); background: rgba(9,105,218,0.06); }
.mg-conn-pill .conn-rel {
    font-size: 0.66rem; font-weight: 400; color: var(--text-muted);
    padding-left: 4px; border-left: 1px solid var(--border); margin-left: 2px;
}
</style>
</head>
<body>

<div class="mg-layout">
    <div class="mg-topbar">
        <i class="bi bi-diagram-2-fill" style="color:var(--accent); flex-shrink:0;"></i>
        <div class="mg-topbar-title">
            <?php if ($focalNode): ?>
                <?= htmlspecialchars($focalNode['name']) ?>
                <span class="focal-badge"><?= htmlspecialchars($focalNode['node_type'] ?? 'node') ?></span>
                <span class="focal-badge" style="background:rgba(217,119,6,0.1); color:var(--amber); border-color:rgba(217,119,6,0.25);">
                    <?= strtoupper(htmlspecialchars($graphType)) ?>
                </span>
            <?php else: ?>
                Mini Graph
            <?php endif; ?>
        </div>
        <div class="mg-stats">
            <span><strong id="stat-nodes">0</strong> nodes</span>
            <span><strong id="stat-edges">0</strong> edges</span>
        </div>
    </div>

    <?php if ($errorMsg): ?>
    <div class="mg-error">
        <i class="bi bi-exclamation-circle"></i>
        <span><?= htmlspecialchars($errorMsg) ?></span>
    </div>
    <?php else: ?>

    <div class="mg-graph-area">
        <div id="graph-container"></div>

        <!-- Node detail panel -->
        <div class="mg-node-panel" id="nodePanel">
            <button class="mg-node-panel-close" id="nodePanelClose" title="Close">×</button>
            <div class="mg-node-panel-name" id="panelName">—</div>
            <div id="panelType"></div>
            <div class="mg-node-panel-actions" id="panelActions"></div>
        </div>

        <!-- Details modal -->
        <div class="mg-details-bg" id="mgDetailsBg">
            <div class="mg-details-inner">
                <div class="mg-details-header">
                    <button class="mg-details-header-nav" id="mgDetailsBack" disabled title="Back">&#8592;</button>
                    <button class="mg-details-header-nav" id="mgDetailsFwd"  disabled title="Forward">&#8594;</button>
                    <span class="mg-details-title" id="mgDetailsTitle"></span>
                    <span id="mgDetailsType" class="mg-type-pill mg-pill-default" style="flex-shrink:0;"></span>
                    <button class="mg-details-close" id="mgDetailsClose" title="Close">&times;</button>
                </div>
                <div class="mg-details-body" id="mgDetailsBody">
                    <div class="mg-empty">Loading…</div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="mg-toolbar">
            <button class="mg-btn" id="btnLayout" title="Run/Stop layout">
                <i class="bi bi-play-fill"></i> Layout
            </button>
            <button class="mg-btn" id="btnReset" title="Reset camera">
                <i class="bi bi-arrows-collapse"></i>
            </button>
            <div class="mg-sep"></div>
            <div class="mg-hops-wrap">
                <i class="bi bi-bezier2"></i> Hops
                <select id="hopsSelect">
                    <?php for ($h = 1; $h <= 4; $h++): ?>
                    <option value="<?= $h ?>" <?= $h === $hops ? 'selected' : '' ?>><?= $h ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mg-sep"></div>
            <a class="mg-btn" href="<?= htmlspecialchars($fullGraphUrl) ?>" target="_blank" title="Open full graph">
                <i class="bi bi-box-arrow-up-right"></i> Full Graph
            </a>
            <?php if ($focalNode): ?>
            <a class="mg-btn" href="<?= htmlspecialchars($editorUrl) ?>" target="_blank" title="Open in editor">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php if (!$errorMsg): ?>
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

<script>
const MG_GRAPH_TYPE = <?= json_encode($graphType) ?>;
const MG_NODE_ID    = <?= json_encode($nodeId) ?>;
const MG_DOC_ID     = <?= json_encode($docId) ?>;
const MG_HOPS       = <?= json_encode($hops) ?>;
const MG_FOCAL_ID   = <?= $jsonFocal ?>;

const dbNodes = <?php echo $jsonNodes; ?>;
const dbEdges = <?php echo $jsonEdges; ?>;

// Check if Python pre-computed positions exist
const hasPrecomputed = dbNodes.some(n => n.x !== undefined && n.y !== undefined);

// Full-graph / editor URL templates (for node panel deep-links)
const FULL_GRAPH_URL = <?= json_encode($fullGraphUrl) ?>;
const EDITOR_BASE_KG = 'kg_view.php';
const EDITOR_BASE_AG = 'ag_view.php';

const TYPE_COLORS = {
    note: '#64748b', relationship: '#ec4899', character: '#3b82f6',
    location: '#10b981', event: '#ef4444', concept: '#f59e0b',
    arc: '#8b5cf6', episode: '#06b6d4', narrative: '#ec4899',
    scene_hook: '#f59e0b', default: '#888888'
};

function getMuted() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? '#30363d' : '#e2e8f0';
}
function getLabelColor() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? '#c9d1d9' : '#24292f';
}

let graph, renderer;
let isRunning = false, fa2LoopId = null;
let selectedNode = null, hoveredNode = null;

// ── Build graph ──────────────────────────────────────────────────────────────
graph = new graphology.MultiDirectedGraph();

dbNodes.forEach(n => {
    const isFocal = n.id === MG_FOCAL_ID;
    graph.addNode(n.id.toString(), {
        x: n.x !== undefined ? n.x : Math.random() * 100,
        y: n.y !== undefined ? n.y : Math.random() * 100,
        size: isFocal ? 16 : 8,
        label: n.name,
        color: isFocal ? '#f59e0b' : (TYPE_COLORS[n.node_type] || TYPE_COLORS.default),
        node_type: n.node_type || 'note',
        is_focal: isFocal,
    });
});

const validIds = new Set(dbNodes.map(n => n.id.toString()));
dbEdges.forEach(e => {
    const s = e.source.toString(), t = e.target.toString();
    if (graph.hasNode(s) && graph.hasNode(t) && s !== t) {
        try {
            graph.addDirectedEdge(s, t, {
                label: e.relationship || '',
                size: 1,
                color: getMuted(),
            });
        } catch(_) { /* skip duplicate edges */ }
    }
});

// Size by degree
graph.forEachNode(node => {
    const isFocal = graph.getNodeAttribute(node, 'is_focal');
    const deg = graph.degree(node);
    graph.setNodeAttribute(node, 'size', isFocal ? 16 : (8 + Math.sqrt(deg) * 2));
});

document.getElementById('stat-nodes').textContent = graph.order;
document.getElementById('stat-edges').textContent = graph.size;

// === MEMORY OPTIMIZATION: CLEAR DUPLICATE JSON ARRAYS FROM JS RAM ===
dbNodes.length = 0; 
dbEdges.length = 0;

// ── Sigma ────────────────────────────────────────────────────────────────────
const container = document.getElementById('graph-container');

renderer = new Sigma(graph, container, {
    // MEMORY OPTIMIZATION: Disable edge labels dynamically if too many edges (prevent texture explosion)
    renderEdgeLabels: graph.size <= 150, 
    defaultEdgeType: 'arrow',
    allowInvalidContainer: true,
    labelRenderedSizeThreshold: 2, // Always show labels
    labelColor:     { color: getLabelColor() },
    edgeLabelColor: { color: getLabelColor() },
    edgeLabelSize:  7,
    // MEMORY OPTIMIZATION: Cap Pixel Ratio to 1.5. Modern Android phones have DPR 3.0+. 
    // Capping at 1.5 reduces the massive WebGL memory buffer by 4x, preventing OOM kills.
    pixelRatio: Math.min(window.devicePixelRatio || 1, 1.5)
});

new MutationObserver(() => {
    renderer.setSetting('labelColor',     { color: getLabelColor() });
    renderer.setSetting('edgeLabelColor', { color: getLabelColor() });
    renderer.refresh();
}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

// ── Node reducer — highlight focal node + hover/select dim ───────────────────
renderer.setSetting('nodeReducer', (node, data) => {
    const res   = { ...data };
    const muted = getMuted();
    const isFocal = data.is_focal;

    if (hoveredNode && hoveredNode !== node &&
        !graph.hasEdge(node, hoveredNode) && !graph.hasEdge(hoveredNode, node)) {
        res.color  = muted;
        res.zIndex = 0;
    } else if (selectedNode && selectedNode !== node &&
               !graph.hasEdge(node, selectedNode) && !graph.hasEdge(selectedNode, node)) {
        res.color  = muted;
        res.zIndex = 0;
    } else {
        res.zIndex = isFocal ? 3 : 1;
    }
    if (node === hoveredNode || node === selectedNode) res.zIndex = 2;
    // Focal node always gets a border ring via highlighted flag
    if (isFocal) res.highlighted = true;
    return res;
});

renderer.setSetting('edgeReducer', (edge, data) => {
    const res    = { ...data };
    const source = graph.source(edge);
    const target = graph.target(edge);
    const muted  = getMuted();
    if (hoveredNode && source !== hoveredNode && target !== hoveredNode) {
        res.color = muted; res.hidden = true;
    } else if (selectedNode && source !== selectedNode && target !== selectedNode) {
        res.color = muted; res.hidden = true;
    } else if (hoveredNode || selectedNode) {
        res.size  = 2;
        res.color = document.documentElement.getAttribute('data-theme') === 'dark' ? '#6b7280' : '#94a3b8';
    }
    return res;
});

// ── ForceAtlas2 ──────────────────────────────────────────────────────────────
const fa2    = graphologyLibrary.layoutForceAtlas2;
const btnLyt = document.getElementById('btnLayout');

function toggleLayout() {
    if (isRunning) {
        cancelAnimationFrame(fa2LoopId);
        isRunning = false;
        btnLyt.innerHTML = '<i class="bi bi-play-fill"></i> Layout';
        btnLyt.classList.remove('active');
    } else {
        isRunning = true;
        btnLyt.innerHTML = '<i class="bi bi-stop-fill"></i> Stop';
        btnLyt.classList.add('active');
        const settings = {
            barnesHutOptimize: graph.order > 60,
            strongGravityMode: true,
            gravity: 0.05,
            scalingRatio: 8,
            slowDown: 8,
        };
        (function step() {
            fa2.assign(graph, { iterations: 1, settings });
            renderer.refresh();
            if (isRunning) fa2LoopId = requestAnimationFrame(step);
        })();
    }
}

btnLyt.addEventListener('click', toggleLayout);

function resetGraphCamera() {
    renderer.getCamera().animatedReset({ duration: 300 });
    setTimeout(() => {
        const cam = renderer.getCamera();
        //cam.animatedZoom({ ratio: cam.ratio * 0.5, duration: 300 });
    }, 320);
}

// Only auto-trigger client-side loop if Python failed to precompute
if (!hasPrecomputed) {
    toggleLayout();
    setTimeout(() => { 
        if (isRunning) toggleLayout(); 
        resetGraphCamera();
    }, 2000);
} else {
    setTimeout(resetGraphCamera, 100);
}

document.getElementById('btnReset').addEventListener('click', resetGraphCamera);

// ── Hops selector ─────────────────────────────────────────────────────────────
document.getElementById('hopsSelect').addEventListener('change', function() {
    const params = new URLSearchParams(window.location.search);
    params.set('hops', this.value);
    window.location.search = params.toString();
});

// ── Drag & Tap (with rAF throttling optimization) ────────────────────────────
let dragNode = null, dragStartX = 0, dragStartY = 0;
let dragFrame = null;

renderer.on('downNode', e => {
    dragNode = e.node;
    const ne = e.event && e.event.original ? e.event.original : (e.event || {});
    dragStartX = ne.touches ? ne.touches[0].clientX : (ne.clientX || 0);
    dragStartY = ne.touches ? ne.touches[0].clientY : (ne.clientY || 0);
    renderer.getCamera().disable();
});

renderer.getMouseCaptor().on('mousemovebody', e => {
    if (!dragNode) return;
    e.preventSigmaDefault();
    e.original.preventDefault();
    e.original.stopPropagation();

    if (dragFrame) cancelAnimationFrame(dragFrame);
    dragFrame = requestAnimationFrame(() => {
        const pos = renderer.viewportToGraph(e);
        graph.setNodeAttribute(dragNode, 'x', pos.x);
        graph.setNodeAttribute(dragNode, 'y', pos.y);
        dragFrame = null;
    });
});

container.addEventListener('touchmove', e => {
    if (!dragNode) return;
    e.preventDefault(); 
    
    const rect  = container.getBoundingClientRect();
    const touch = e.touches[0];
    const clientX = touch.clientX;
    const clientY = touch.clientY;

    if (dragFrame) cancelAnimationFrame(dragFrame);
    dragFrame = requestAnimationFrame(() => {
        const pos = renderer.viewportToGraph({ x: clientX - rect.left, y: clientY - rect.top });
        graph.setNodeAttribute(dragNode, 'x', pos.x);
        graph.setNodeAttribute(dragNode, 'y', pos.y);
        dragFrame = null;
    });
}, { passive: false });

function releaseNode(e) {
    if (!dragNode) return;
    if (dragFrame) {
        cancelAnimationFrame(dragFrame);
        dragFrame = null;
    }
    const endX = e.changedTouches ? e.changedTouches[0].clientX : (e.clientX || dragStartX);
    const endY = e.changedTouches ? e.changedTouches[0].clientY : (e.clientY || dragStartY);
    
    // mathematical distance check to differentiate between a pan/drag and a tap
    const dist = Math.sqrt(Math.pow(endX - dragStartX, 2) + Math.pow(endY - dragStartY, 2));
    if (dist < 6) { 
        openNodePanel(dragNode);
    }
    
    renderer.getCamera().enable();
    dragNode = null;
}

window.addEventListener('mouseup', releaseNode);
window.addEventListener('touchend', releaseNode);

renderer.on('clickStage', () => closeNodePanel());
renderer.on('enterNode', ({ node }) => { hoveredNode = node; renderer.refresh(); });
renderer.on('leaveNode', ()         => { hoveredNode = null; renderer.refresh(); });

// ── Node panel ───────────────────────────────────────────────────────────────
function openNodePanel(nodeId) {
    selectedNode = nodeId;
    const attrs  = graph.getNodeAttributes(nodeId);
    const panel  = document.getElementById('nodePanel');

    document.getElementById('panelName').textContent = attrs.label;

    const typeEl = document.getElementById('panelType');
    typeEl.innerHTML = `<span class="mg-type-pill mg-pill-${attrs.node_type || 'default'}">${attrs.node_type || 'note'}</span>`;

    // Build action links
    const actions = document.getElementById('panelActions');
    actions.innerHTML = '';

    // "Re-center here" — reload mini_graph with this node as focal
    const params = new URLSearchParams(window.location.search);
    params.set('node_id', nodeId);
    const recenterUrl = 'mini_graph.php?' + params.toString();

    const recenterBtn = document.createElement('a');
    recenterBtn.href = recenterUrl;
    recenterBtn.innerHTML = '<i class="bi bi-crosshair2"></i> Re-center here';
    actions.appendChild(recenterBtn);

    // View Details button — opens content modal
    const detailsBtn = document.createElement('button');
    detailsBtn.innerHTML = '<i class="bi bi-file-text"></i> View Details';
    detailsBtn.addEventListener('click', () => openDetailsModal(nodeId));
    actions.appendChild(detailsBtn);

    // Open in editor
    let editorUrl;
    if (MG_GRAPH_TYPE === 'kg') {
        editorUrl = EDITOR_BASE_KG + '?node_id=' + nodeId;
    } else {
        editorUrl = EDITOR_BASE_AG + '?doc_id=' + MG_DOC_ID + '&node_id=' + nodeId;
    }
    const edBtn = document.createElement('a');
    edBtn.href   = editorUrl;
    edBtn.target = '_blank';
    edBtn.rel    = 'noopener';
    edBtn.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Open in Editor';
    actions.appendChild(edBtn);

    panel.style.display = 'block';
    renderer.refresh();
}

function closeNodePanel() {
    selectedNode = null;
    document.getElementById('nodePanel').style.display = 'none';
    renderer.refresh();
}

document.getElementById('nodePanelClose').addEventListener('click', closeNodePanel);

// ── Details modal ─────────────────────────────────────────────────────────────
const API_URL = MG_GRAPH_TYPE === 'kg' ? 'kg_api.php' : 'ag_api.php';

let detailsHistory = [], detailsHistPos = -1;

const TYPE_DOT_COLORS = {
    character:'#3b82f6', location:'#10b981', concept:'#f59e0b',
    event:'#ef4444', arc:'#8b5cf6', episode:'#06b6d4',
    relationship:'#ec4899', narrative:'#ec4899', scene_hook:'#f59e0b',
    note:'#64748b'
};

function openDetailsModal(nodeId, addToHistory = true) {
    if (addToHistory) {
        detailsHistory = detailsHistory.slice(0, detailsHistPos + 1);
        detailsHistory.push(nodeId);
        detailsHistPos = detailsHistory.length - 1;
    }
    detailsUpdateNav();
    openNodePanel(nodeId); // keep side panel in sync

    const attrs = graph.getNodeAttributes(nodeId);
    document.getElementById('mgDetailsTitle').textContent = attrs.label;
    const typeEl = document.getElementById('mgDetailsType');
    typeEl.textContent  = attrs.node_type || 'note';
    typeEl.className    = 'mg-type-pill mg-pill-' + (attrs.node_type || 'default');

    const body = document.getElementById('mgDetailsBody');
    body.innerHTML = '<div class="mg-empty">Loading…</div>';

    document.getElementById('mgDetailsBg').classList.add('open');
    body.scrollTop = 0;

    // Fetch node content from the appropriate API
    const qs = MG_GRAPH_TYPE === 'kg'
        ? `kg_api.php?action=get_node&id=${nodeId}`
        : `ag_api.php?action=get_node&doc_id=${MG_DOC_ID}&id=${nodeId}`;

    fetch(qs)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { body.innerHTML = '<div class="mg-empty">Failed to load node content.</div>'; return; }
            const md = (res.node && res.node.content) ? res.node.content.trim() : '';
            const textDiv = document.createElement('div');
            textDiv.innerHTML = md ? marked.parse(md) : '<div class="mg-empty">This node has no content yet.</div>';
            body.innerHTML = '';
            body.appendChild(textDiv);
            body.appendChild(buildConnections(nodeId));
            body.scrollTop = 0;
        })
        .catch(() => { body.innerHTML = '<div class="mg-empty">Network error loading content.</div>'; });
}

function closeDetailsModal() {
    document.getElementById('mgDetailsBg').classList.remove('open');
    detailsHistory = []; detailsHistPos = -1;
    detailsUpdateNav();
}

function detailsUpdateNav() {
    const back   = document.getElementById('mgDetailsBack');
    const fwd    = document.getElementById('mgDetailsFwd');
    const canBack = detailsHistPos > 0;
    const canFwd  = detailsHistPos < detailsHistory.length - 1;
    back.disabled = !canBack; back.style.opacity = canBack ? '1' : '0.35';
    fwd.disabled  = !canFwd;  fwd.style.opacity  = canFwd  ? '1' : '0.35';
}

function buildConnections(nodeId) {
    const nid = nodeId.toString();
    const outgoing = [], incoming = [];

    graph.forEachOutboundEdge(nid, (edge, attrs, source, target) => {
        if (target !== nid && graph.hasNode(target))
            outgoing.push({ id: target, label: graph.getNodeAttribute(target, 'label'),
                            type: graph.getNodeAttribute(target, 'node_type'), rel: attrs.label || '' });
    });
    graph.forEachInboundEdge(nid, (edge, attrs, source, target) => {
        if (source !== nid && graph.hasNode(source))
            incoming.push({ id: source, label: graph.getNodeAttribute(source, 'label'),
                            type: graph.getNodeAttribute(source, 'node_type'), rel: attrs.label || '' });
    });

    if (!outgoing.length && !incoming.length) return document.createDocumentFragment();

    const wrap = document.createElement('div');
    wrap.className = 'mg-conn-section';

    function makeSection(title, items) {
        if (!items.length) return;
        const h4 = document.createElement('h4');
        h4.textContent = title + ' (' + items.length + ')';
        wrap.appendChild(h4);
        const list = document.createElement('div');
        list.className = 'mg-conn-list';
        items.forEach(item => {
            const pill = document.createElement('button');
            pill.className = 'mg-conn-pill';
            const dot = document.createElement('span');
            dot.style.cssText = 'width:7px;height:7px;border-radius:50%;flex-shrink:0;background:'
                + (TYPE_DOT_COLORS[item.type] || '#888');
            pill.appendChild(dot);
            const lbl = document.createElement('span');
            lbl.textContent = item.label;
            pill.appendChild(lbl);
            if (item.rel) {
                const rel = document.createElement('span');
                rel.className = 'conn-rel';
                rel.textContent = item.rel;
                pill.appendChild(rel);
            }
            pill.addEventListener('click', () => openDetailsModal(item.id));
            list.appendChild(pill);
        });
        wrap.appendChild(list);
    }

    makeSection('Outgoing', outgoing);
    makeSection('Incoming', incoming);
    return wrap;
}

document.getElementById('mgDetailsClose').addEventListener('click', closeDetailsModal);
document.getElementById('mgDetailsBack').addEventListener('click', () => {
    if (detailsHistPos > 0) { detailsHistPos--; openDetailsModal(detailsHistory[detailsHistPos], false); }
});
document.getElementById('mgDetailsFwd').addEventListener('click', () => {
    if (detailsHistPos < detailsHistory.length - 1) { detailsHistPos++; openDetailsModal(detailsHistory[detailsHistPos], false); }
});
document.getElementById('mgDetailsBg').addEventListener('click', function(e) {
    if (e.target === this) closeDetailsModal();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (document.getElementById('mgDetailsBg').classList.contains('open')) {
            closeDetailsModal();
        } else {
            closeNodePanel();
        }
    }
});
</script>
<?php endif; ?>

</body>
</html>