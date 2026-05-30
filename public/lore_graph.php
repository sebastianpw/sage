<?php
// public/lore_graph.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Service\LoreAccessService;

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Lore Graph Visualizer";

ob_start();

$pdo = $spw->getPDO();

// 1. Fetch available collections for the filter dropdown
$collStmt = $pdo->query("SELECT DISTINCT target_collection FROM md_doc_analysis WHERE target_collection IS NOT NULL AND target_collection != '' ORDER BY target_collection ASC");
$collections = $collStmt->fetchAll(PDO::FETCH_COLUMN);

$activeCollection = $_GET['collection'] ?? '';

// 2. Fetch document IDs (filtered by collection if requested)
$sql = "
    SELECT d.id
    FROM documentations d
    JOIN md_doc_analysis da ON da.doc_id = d.id
    WHERE d.is_active = 1
";
$params =[];
if ($activeCollection !== '') {
    $sql .= " AND da.target_collection = ?";
    $params[] = $activeCollection;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$docIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../src/Service/LoreAccessService.php';
$service = new LoreAccessService($pdo);

// Pass 1: Build Nodes Map and Alias Resolution Map
$nodesMap = [];
$aliasMap =[];
$entityData =[]; // Store to process edges in Pass 2

foreach ($docIds as $docId) {
    try {
        $service->loadDoc($docId);
        $world = $service->getWorldData();
        foreach ($world as $category => $entities) {
            foreach ($entities as $ent) {
                $name = $ent['name'] ?? 'Unknown';
                $nodeId = strtolower(trim($name));
                if (empty($nodeId) || $nodeId === 'unknown') continue;

                // Map category to a singular node_type
                $nodeType = $category;
                if (str_ends_with($nodeType, 's')) {
                    $nodeType = substr($nodeType, 0, -1);
                }
                
                if (!isset($nodesMap[$nodeId])) {
                    $nodesMap[$nodeId] =[
                        'id' => $nodeId,
                        'name' => $name,
                        'node_type' => $nodeType,
                        'doc_ids' => [$docId]
                    ];
                } else {
                    if (!in_array($docId, $nodesMap[$nodeId]['doc_ids'])) {
                        $nodesMap[$nodeId]['doc_ids'][] = $docId;
                    }
                }

                // Register aliases for edge resolution
                $aliasMap[$nodeId] = $nodeId;
                if (!empty($ent['aliases']) && is_array($ent['aliases'])) {
                    foreach ($ent['aliases'] as $alias) {
                        $a = strtolower(trim($alias));
                        if ($a !== '') $aliasMap[$a] = $nodeId;
                    }
                }

                $entityData[] =[
                    'id' => $nodeId,
                    'ent' => $ent
                ];
            }
        }
    } catch (\Exception $e) {
        continue;
    }
}

// Helper: Resolve a string target to a strict Node ID
$resolveTarget = function($targetName) use (&$aliasMap) {
    $t = strtolower(trim($targetName));
    if (isset($aliasMap[$t])) return $aliasMap[$t];
    // Fuzzy match: check if the string contains a known alias
    foreach ($aliasMap as $alias => $primaryId) {
        if (strlen($alias) > 3 && strpos($t, $alias) !== false) {
            return $primaryId;
        }
    }
    return null;
};

// Pass 2: Calculate Connections on the fly (Explicit + Implicit)
$edgeSet =[]; // Keyed by Source||Target to aggregate relationships

foreach ($entityData as $data) {
    $sourceId = $data['id'];
    $ent = $data['ent'];

    // --- A. Explicit Relationships ---
    if (!empty($ent['relationships'])) {
        foreach ($ent['relationships'] as $rel) {
            $targetName = $rel['target'] ?? '';
            if (is_array($targetName)) $targetName = implode(' ', $targetName);
            $targetId = $resolveTarget($targetName);
            
            if (!$targetId || $targetId === $sourceId) continue;

            $relType = $rel['type'] ?? ($rel['nature'] ?? 'related_to');
            if (is_array($relType)) $relType = implode(', ', $relType);
            
            $edgeKey = $sourceId . '||' . $targetId;
            if (!isset($edgeSet[$edgeKey])) {
                $edgeSet[$edgeKey] =[
                    'source' => $sourceId,
                    'target' => $targetId,
                    'relationships' => [$relType],
                    'item_label' => $targetName
                ];
            } else {
                if (!in_array($relType, $edgeSet[$edgeKey]['relationships'])) {
                    $edgeSet[$edgeKey]['relationships'][] = $relType;
                }
            }
        }
    }
    
   
            
            
    
    // --- B. Implicit Mentions (On-The-Fly Context Scanning) ---
    // Extract timeline, history, and textual attributes to find soft mentions
    $textContent = "";
    if (!empty($ent['timeline'])) {
        foreach ($ent['timeline'] as $t) {
            $val = $t['text'] ?? '';
            $textContent .= " " . (is_array($val) ? implode(' ', $val) : $val);
        }
    }


    if (!empty($ent['attributes'])) {
        foreach ($ent['attributes'] as $v) {
            if (is_string($v)) $textContent .= " " . $v;
        }
    }

    if (!empty($textContent)) {
        // Fast word boundary hack for substring matching
        $normalizedText = " " . preg_replace('/[^\p{L}\p{N}]/u', ' ', strtolower($textContent)) . " ";
        
        foreach ($aliasMap as $alias => $targetId) {
            if ($targetId === $sourceId) continue;
            // Only scan for reasonably long aliases to prevent false positives (e.g., "a", "he")
            if (strlen($alias) > 4) {
                // Check if the exact alias appears as a whole word
                if (strpos($normalizedText, ' ' . $alias . ' ') !== false) {
                    $relType = 'mentions';
                    $edgeKey = $sourceId . '||' . $targetId;
                    
                    if (!isset($edgeSet[$edgeKey])) {
                        $edgeSet[$edgeKey] =[
                            'source' => $sourceId,
                            'target' => $targetId,
                            'relationships' => [$relType],
                            'item_label' => ucwords($alias)
                        ];
                    } else {
                        if (!in_array($relType, $edgeSet[$edgeKey]['relationships'])) {
                            $edgeSet[$edgeKey]['relationships'][] = $relType;
                        }
                    }
                }
            }
        }
    }
}

// Convert aggregated Edge Sets back into final flat array
$finalEdges =[];
foreach ($edgeSet as $e) {
    $finalEdges[] = [
        'source' => $e['source'],
        'target' => $e['target'],
        'relationship' => implode(', ', $e['relationships']),
        'item_label' => $e['item_label']
    ];
}

$dbNodes = array_values($nodesMap);
$dbEdges = $finalEdges;

$jsonNodes = json_encode($dbNodes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsonEdges = json_encode($dbEdges, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<script>
(function() {
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch(e) {}
})();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* ── Variables ── */
:root {
    --bg:#f6f8fa; --card:#ffffff; --border:#d0d7de;
    --text:#24292f; --text-muted:#57606a; --accent:#0969da;
    --green:#238636; --red:#da3633; --orange:#f59e0b;
}
:root[data-theme="dark"] {
    --bg:#0d1117; --card:#161b22; --border:#30363d;
    --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
}
@media (prefers-color-scheme:dark) {
    :root:not([data-theme="light"]) {
        --bg:#0d1117; --card:#161b22; --border:#30363d;
        --text:#c9d1d9; --text-muted:#8b949e; --accent:#58a6ff;
    }
}

body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; height:100vh; overflow:hidden; }

/* ── Layout ── */
.kg-layout { display: flex; height: 100vh; flex-direction: column; }
.kg-topbar {
    height: 52px; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; padding: 0 16px; gap: 10px; flex-shrink: 0; z-index: 10;
}
.kg-topbar h2 { margin:0; font-size:1rem; display: flex; align-items: center; gap: 8px; }

/* ── Graph Area ── */
.kg-main { flex: 1; position: relative; overflow: hidden; display: flex; }
#graph-container { flex: 1; height: 100%; background: var(--bg); outline: none; }

/* ── UI Panels ── */
.graph-panel {
    position: absolute;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    z-index: 100;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.panel-left { top: 10px; left: 10px; width: 220px; }
.panel-right { top: 10px; right: 10px; width: 280px; display: none; }

/* Panel Drag & Collapse Elements */
.panel-header {
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    padding: 8px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: move;
    user-select: none;
    touch-action: none;
}
.panel-header h3 { margin: 0; font-size: 0.9rem; pointer-events: none; }
.collapse-btn {
    background: none; border: none; color: var(--text-muted);
    cursor: pointer; padding: 4px; border-radius: 4px; line-height: 1;
}
.collapse-btn:hover { background: rgba(125,125,125,0.2); color: var(--text); }
.panel-content { padding: 12px; }

.graph-panel .stat { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px; }

/* ── Buttons ── */
.btn {
    padding: 6px 11px; border-radius:6px; border:none; cursor:pointer;
    font-weight:600; font-size:0.85rem; display:inline-flex; align-items:center; gap:5px;
    text-decoration:none; white-space:nowrap; justify-content: center;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.btn-primary { background:var(--accent); color:#fff; }
.btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text); }
.btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
.btn-block { width: 100%; margin-bottom: 8px; }
.btn-sm { padding:4px 8px; font-size:0.78rem; }

/* ── Collection Select ── */
.collection-select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    outline: none;
}
.collection-select:hover { border-color: var(--accent); }

/* ── Node type badge ── */
.kge-type-pill {
    font-size: 0.68rem; font-weight: 700;
    padding: 1px 6px; border-radius: 8px;
    white-space: nowrap; display: inline-block;
}
.kge-pill-character    { background:rgba(59,130,246,.12);  color:#3b82f6; border:1px solid rgba(59,130,246,.25); }
.kge-pill-location     { background:rgba(16,185,129,.12);  color:#10b981; border:1px solid rgba(16,185,129,.25); }
.kge-pill-concept      { background:rgba(245,158,11,.12);  color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
.kge-pill-event        { background:rgba(239,68,68,.12);   color:#ef4444; border:1px solid rgba(239,68,68,.25); }
.kge-pill-arc          { background:rgba(139,92,246,.12);  color:#8b5cf6; border:1px solid rgba(139,92,246,.25); }
.kge-pill-episode      { background:rgba(6,182,212,.12);   color:#06b6d4; border:1px solid rgba(6,182,212,.25); }
.kge-pill-note         { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }
.kge-pill-technology   { background:rgba(236,72,153,.12);  color:#ec4899; border:1px solid rgba(236,72,153,.25); }
.kge-pill-default      { background:rgba(100,116,139,.12); color:var(--text-muted); border:1px solid var(--border); }

/* ── Toast ── */
#kg-toast {
    position:fixed; bottom:24px; right:24px; z-index:99999;
    background:var(--card); color:var(--text); border:1px solid var(--border);
    border-left:4px solid var(--green); border-radius:6px;
    padding:12px 18px; font-size:0.9rem;
    display:none; box-shadow:0 4px 12px rgba(0,0,0,.2);
}
</style>

<div class="kg-layout">
    <div class="kg-topbar">
        <h2><i class="bi bi-compass" style="color:var(--accent);"></i> Lore Graph Visualizer</h2>
        
        <div style="margin-left: 15px; display: flex; align-items: center; gap: 8px;">
            <select class="collection-select" onchange="window.location.href='lore_graph.php?collection='+encodeURIComponent(this.value)">
                <option value="">— All Collections —</option>
                <?php foreach ($collections as $col): ?>
                    <option value="<?= htmlspecialchars($col) ?>" <?= $activeCollection === $col ? 'selected' : '' ?>>
                        <?= htmlspecialchars($col) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-left: auto; display: flex; gap: 8px;">
            <button class="btn btn-ghost btn-sm" onclick="window.location.href='view_lore_explorer.php'"><i class="bi bi-search"></i> Lore Explorer</button>
        </div>
    </div>

    <div class="kg-main">
        <div id="graph-container"></div>
        
        <div class="graph-panel panel-left" id="controls-panel">
            <div class="panel-header">
                <h3>Controls</h3>
                <button class="collapse-btn" onclick="togglePanel('controls-panel', this)"><i class="bi bi-dash"></i></button>
            </div>
            <div class="panel-content">
                <button class="btn btn-primary btn-block" id="btn-layout"><i class="bi bi-play-fill"></i> Run ForceAtlas2</button>
                <button class="btn btn-ghost btn-block" id="btn-reset"><i class="bi bi-arrows-collapse"></i> Reset Camera</button>
                
                <div style="margin-top:10px;">
                    <input type="search" id="graph-search"
                        placeholder="&#128269; Search nodes…"
                        style="width:100%; padding:6px 9px; border-radius:6px;
                               border:1px solid var(--border); background:var(--bg);
                               color:var(--text); font-size:0.83rem; box-sizing:border-box;
                               outline:none;"
                        autocomplete="off">
                    <div id="graph-search-count" style="font-size:0.75rem; color:var(--text-muted); margin-top:4px; min-height:16px;"></div>

                    <button class="btn btn-ghost btn-block" id="btn-search-export"
                            onclick="exportSearchMatches()"
                            style="margin-top:6px; opacity:0.4; pointer-events:none;">
                        <i class="bi bi-download"></i> Export Matches
                    </button>
                </div>

                <div style="margin-top:10px; padding-top:10px; border-top: 1px solid var(--border);">
                    <div class="stat">Nodes: <strong id="stat-nodes">0</strong></div>
                    <div class="stat">Edges: <strong id="stat-edges">0</strong></div>
                </div>
            </div>
        </div>

        <div class="graph-panel panel-right" id="node-panel">
            <div class="panel-header">
                <h3>Node Details</h3>
                <button class="collapse-btn" onclick="togglePanel('node-panel', this)"><i class="bi bi-dash"></i></button>
            </div>
            <div class="panel-content">
                <h3 id="np-name" style="margin-top:0;">Node Name</h3>
                <div style="margin-bottom: 15px;">
                    <span id="np-type" class="kge-type-pill kge-pill-note">note</span>
                </div>
                
                
                
                
                <!--
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="btn btn-ghost" id="btn-view-details"><i class="bi bi-file-text"></i> View Details</button>
                    <button class="btn btn-ghost" id="btn-close-panel">Close Panel</button>
                </div>
                -->
                
                
                
               <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="btn btn-ghost" id="btn-view-details"><i class="bi bi-file-text"></i> View Details</button>
                    <a class="btn btn-ghost" id="btn-open-docs" href="#" target="_blank" rel="noopener"
                       style="display:none;">
                        <i class="bi bi-box-arrow-up-right"></i> Open in Curated Docs
                    </a>
                    <button class="btn btn-ghost" id="btn-close-panel">Close Panel</button>
                </div>
                
                
            </div>
        </div>
    </div>
</div>

<!-- Node Details Full-Screen Modal -->
<div id="modalDetails" style="
    position:fixed; inset:0; background:rgba(0,0,0,0.75);
    display:none; align-items:flex-start; justify-content:center;
    z-index:9998; overflow-y:auto; padding:20px; box-sizing:border-box;
">
    <div style="
        background:var(--card); border:1px solid var(--border); border-radius:12px;
        width:100%; max-width:780px; min-height:200px;
        box-shadow:0 20px 60px rgba(0,0,0,0.5);
        display:flex; flex-direction:column; overflow:hidden;
        margin:auto;
    ">
        <div id="details-header" style="
            padding:10px 14px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:8px; flex-shrink:0;
            background:var(--bg); position:sticky; top:0; z-index:2;
        ">
            <button id="details-btn-back" onclick="detailsHistoryBack()" title="Back"
                style="background:none;border:1px solid var(--border);color:var(--text-muted);
                       border-radius:5px;padding:3px 8px;cursor:pointer;font-size:1rem;line-height:1;
                       opacity:0.35;" disabled>&#8592;</button>
            <button id="details-btn-fwd" onclick="detailsHistoryFwd()" title="Forward"
                style="background:none;border:1px solid var(--border);color:var(--text-muted);
                       border-radius:5px;padding:3px 8px;cursor:pointer;font-size:1rem;line-height:1;
                       opacity:0.35;" disabled>&#8594;</button>
            <span id="details-title" style="font-weight:700; font-size:0.95rem; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></span>
            <span id="details-type" class="kge-type-pill kge-pill-note" style="flex-shrink:0;"></span>
            <button onclick="document.getElementById('modalDetails').style.display='none'; detailsHistory=[]; detailsHistPos=-1;" style="
                background:none; border:none; color:var(--text-muted);
                font-size:1.4rem; cursor:pointer; line-height:1; padding:2px 6px;
                border-radius:4px; flex-shrink:0;
            " title="Close">&times;</button>
        </div>
        <div id="details-body" style="
            padding:20px 24px; overflow-y:auto; flex:1;
            font-size:0.95rem; line-height:1.7; color:var(--text);
        ">
            <div id="details-loading" style="color:var(--text-muted); font-style:italic;">Loading…</div>
        </div>
    </div>
</div>

<style>
/* Scoped styles inside details-body */
#details-body h1,#details-body h2,#details-body h3,
#details-body h4,#details-body h5,#details-body h6 {
    margin:1.2em 0 0.4em; line-height:1.3; font-weight:700; color:var(--text);
}
#details-body h1 { font-size:1.5rem; border-bottom:1px solid var(--border); padding-bottom:6px; }
#details-body h2 { font-size:1.2rem; border-bottom:1px solid var(--border); padding-bottom:4px; }
#details-body h3 { font-size:1rem; }
#details-body p  { margin:0 0 0.9em; }
#details-body ul,#details-body ol { margin:0 0 0.9em; padding-left:1.6em; }
#details-body li { margin-bottom:0.3em; }
#details-body .details-empty {
    color:var(--text-muted); font-style:italic; text-align:center; padding:30px 0;
}
.details-connections {
    margin-top:28px; border-top:1px solid var(--border); padding-top:16px;
}
.details-connections h4 {
    font-size:0.72rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.06em; color:var(--text-muted); margin:0 0 10px;
}
.details-conn-list {
    display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;
}
.details-conn-pill {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 10px; border-radius:20px; font-size:0.78rem; font-weight:600;
    border:1px solid var(--border); background:var(--bg);
    cursor:pointer; color:var(--text);
    transition:border-color 0.15s, color 0.15s, background 0.15s;
}
.details-conn-pill:hover {
    border-color:var(--accent); color:var(--accent);
    background:rgba(9,105,218,0.06);
}
.details-conn-pill .conn-rel {
    font-size:0.68rem; font-weight:400; color:var(--text-muted);
    padding-left:4px; border-left:1px solid var(--border); margin-left:2px;
}
</style>

<div id="kg-toast"></div>

<!-- Data dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

<script>
const dbNodes = <?php echo $jsonNodes; ?>;
const dbEdges = <?php echo $jsonEdges; ?>;

let graph, renderer;
let isLayoutRunning = false;
let fa2LoopId = null;
let selectedNode = null;
let hoveredNode = null;
let searchMatches = null; 

const typeColors = {
    'character': '#3b82f6',
    'location': '#10b981',
    'episode': '#06b6d4',
    'event': '#ef4444',
    'concept': '#f59e0b',
    'arc': '#8b5cf6',
    'technology': '#ec4899',
    'timeline': '#888888',
    'note': '#64748b',
    'default': '#888888'
};

function getMutedColor() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? '#30363d' : '#e2e8f0';
}

function updateSizes() {
    graph.forEachNode((node, attrs) => {
        const degree = graph.degree(node);
        graph.setNodeAttribute(node, 'size', 4 + Math.sqrt(degree) * 1.5);
    });
    document.getElementById('stat-nodes').textContent = graph.order;
    document.getElementById('stat-edges').textContent = graph.size;
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Draggable & Collapsible Panels
function makeDraggable(panelId) {
    const panel = document.getElementById(panelId);
    const header = panel.querySelector('.panel-header');
    let isDragging = false;
    let startX, startY, initialX, initialY;

    function start(e) {
        if (e.target.closest('.collapse-btn')) return; 
        isDragging = true;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        startX = clientX;
        startY = clientY;
        initialX = panel.offsetLeft;
        initialY = panel.offsetTop;
        
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', end);
        document.addEventListener('touchmove', move, {passive: false});
        document.addEventListener('touchend', end);
    }
    function move(e) {
        if (!isDragging) return;
        e.preventDefault(); 
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        panel.style.left = (initialX + (clientX - startX)) + 'px';
        panel.style.top = (initialY + (clientY - startY)) + 'px';
        panel.style.right = 'auto';
    }
    function end() {
        isDragging = false;
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', end);
        document.removeEventListener('touchmove', move);
        document.removeEventListener('touchend', end);
    }
    header.addEventListener('mousedown', start);
    header.addEventListener('touchstart', start, {passive: false});
}

function togglePanel(panelId, btn) {
    const content = document.querySelector(`#${panelId} .panel-content`);
    if (content.style.display === 'none') {
        content.style.display = 'block';
        btn.innerHTML = '<i class="bi bi-dash"></i>';
    } else {
        content.style.display = 'none';
        btn.innerHTML = '<i class="bi bi-plus"></i>';
    }
}



function openNodePanel(nodeId) {
    selectedNode = nodeId;
    const attrs = graph.getNodeAttributes(nodeId);
    const panel = document.getElementById('node-panel');
    document.getElementById('np-name').textContent = attrs.label;
    document.getElementById('np-type').textContent = attrs.node_type;
    document.getElementById('np-type').className = 'kge-type-pill kge-pill-' + (attrs.node_type || 'default');

    // Build deep-link to view_curated_docs
    const docsBtn = document.getElementById('btn-open-docs');
    const docIds = attrs.doc_ids || [];
    if (docIds.length > 0) {
        // Map node_type to focus_type parameter
        const storyTypes = new Set(['episode', 'narrative_engine', 'scene_hook', 'visual_keyword']);
        const focusType  = storyTypes.has(attrs.node_type) ? 'story' : 'world';
        const url = 'view_curated_docs.php'
            + '?doc_id='       + encodeURIComponent(docIds[0])
            + '&focus_type='   + encodeURIComponent(focusType)
            + '&focus_entity=' + encodeURIComponent(attrs.label);
        docsBtn.href = url;
        docsBtn.style.display = 'inline-flex';
    } else {
        docsBtn.style.display = 'none';
    }

    panel.style.display = 'flex';
    document.querySelector('#node-panel .panel-content').style.display = 'block';
    document.querySelector('#node-panel .collapse-btn').innerHTML = '<i class="bi bi-dash"></i>';

    renderer.refresh();
}


/*

function openNodePanel(nodeId) {
    selectedNode = nodeId;
    const attrs = graph.getNodeAttributes(nodeId);
    const panel = document.getElementById('node-panel');
    document.getElementById('np-name').textContent = attrs.label;
    document.getElementById('np-type').textContent = attrs.node_type;
    document.getElementById('np-type').className = 'kge-type-pill kge-pill-' + (attrs.node_type || 'default');
    
    panel.style.display = 'flex';
    document.querySelector('#node-panel .panel-content').style.display = 'block';
    document.querySelector('#node-panel .collapse-btn').innerHTML = '<i class="bi bi-dash"></i>';
    
    renderer.refresh();
}

*/




document.addEventListener('DOMContentLoaded', () => {
    makeDraggable('controls-panel');
    makeDraggable('node-panel');

    // Using MultiDirectedGraph so if you later decide to allow multiple separate edges it handles it perfectly.
    // Right now, PHP squashes duplicate relationships into a single edge definition.
    graph = new graphology.MultiDirectedGraph();

    dbNodes.forEach(n => {
        graph.addNode(n.id.toString(), {
            x: Math.random() * 100,
            y: Math.random() * 100,
            size: 4,
            label: n.name,
            color: typeColors[n.node_type] || typeColors['default'],
            node_type: n.node_type || 'note',
            doc_ids: n.doc_ids ||[]
        });
    });

    dbEdges.forEach(e => {
        const s = e.source.toString();
        const t = e.target.toString();
        if (graph.hasNode(s) && graph.hasNode(t)) {
            graph.addDirectedEdge(s, t, {
                label: e.relationship || '',
                size: 1,
                color: getMutedColor()
            });
        }
    });

    updateSizes();
    
    
    
    
    
   const container = document.getElementById('graph-container');
    
    // Helper to determine the correct text color based on the current theme
    const getLabelColor = () => document.documentElement.getAttribute('data-theme') === 'dark' ? '#c9d1d9' : '#24292f';

    
    
    
    /*
    renderer = new Sigma(graph, container, {
        renderEdgeLabels: true,
        defaultEdgeType: "arrow",
        allowInvalidContainer: true,
        labelColor: { color: getLabelColor() },
        edgeLabelColor: { color: getLabelColor() } // Fixes relationship text colors too!
    });
    */
    
   renderer = new Sigma(graph, container, {
        renderEdgeLabels: true,
        defaultEdgeType: "arrow",
        allowInvalidContainer: true,
        labelColor: { color: getLabelColor() },
        edgeLabelColor: { color: getLabelColor() },
        edgeLabelSize: 7
    });
    
    
    
    

    // Listen for theme toggles to update the canvas text live
    new MutationObserver(() => {
        renderer.setSetting('labelColor', { color: getLabelColor() });
        renderer.setSetting('edgeLabelColor', { color: getLabelColor() });
        renderer.refresh();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    
    
    
    /*

    const container = document.getElementById('graph-container');
    renderer = new Sigma(graph, container, {
        renderEdgeLabels: true,
        defaultEdgeType: "arrow",
        allowInvalidContainer: true
    });
    
    */
    
    

    const fa2 = graphologyLibrary.layoutForceAtlas2;
    const fa2Btn = document.getElementById('btn-layout');
    
    function toggleLayout() {
        if (isLayoutRunning) {
            cancelAnimationFrame(fa2LoopId);
            isLayoutRunning = false;
            fa2Btn.innerHTML = '<i class="bi bi-play-fill"></i> Run ForceAtlas2';
        } else {
            isLayoutRunning = true;
            fa2Btn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop ForceAtlas2';
            const settings = {
                barnesHutOptimize: graph.order > 100,
                strongGravityMode: true,
                gravity: 0.05,
                scalingRatio: 10,
                slowDown: 10
            };
            function step() {
                fa2.assign(graph, { iterations: 1, settings: settings });
                renderer.refresh();
                if (isLayoutRunning) fa2LoopId = requestAnimationFrame(step);
            }
            step();
        }
    }
    fa2Btn.addEventListener('click', toggleLayout);

    toggleLayout();
    setTimeout(() => { if (isLayoutRunning) toggleLayout(); }, 2500);

    document.getElementById('btn-reset').addEventListener('click', () => {
        renderer.getCamera().animatedReset({ duration: 500 });
    });

    let dragNode = null;
    let dragStartX = 0;
    let dragStartY = 0;
    const DRAG_THRESHOLD = 6; 

    renderer.on("downNode", (e) => {
        dragNode = e.node;
        const nativeEvent = e.event && e.event.original ? e.event.original : (e.event || {});
        if (nativeEvent.touches && nativeEvent.touches.length) {
            dragStartX = nativeEvent.touches[0].clientX;
            dragStartY = nativeEvent.touches[0].clientY;
        } else {
            dragStartX = nativeEvent.clientX || 0;
            dragStartY = nativeEvent.clientY || 0;
        }
        renderer.getCamera().disable();
    });

    renderer.getMouseCaptor().on("mousemovebody", (e) => {
        if (!dragNode) return;
        const pos = renderer.viewportToGraph(e);
        graph.setNodeAttribute(dragNode, "x", pos.x);
        graph.setNodeAttribute(dragNode, "y", pos.y);
        e.preventSigmaDefault();
        e.original.preventDefault();
        e.original.stopPropagation();
    });

    container.addEventListener('touchmove', (e) => {
        if (!dragNode) return;
        const rect = container.getBoundingClientRect();
        const touch = e.touches[0];
        const pos = renderer.viewportToGraph({
            x: touch.clientX - rect.left,
            y: touch.clientY - rect.top
        });
        graph.setNodeAttribute(dragNode, "x", pos.x);
        graph.setNodeAttribute(dragNode, "y", pos.y);
        e.preventDefault();
    }, { passive: false });

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
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (dist < DRAG_THRESHOLD) {
            openNodePanel(dragNode);
        }

        renderer.getCamera().enable();
        dragNode = null;
    }
    window.addEventListener('mouseup', releaseNode);
    window.addEventListener('touchend', releaseNode);

    renderer.on("clickNode", ({ node }) => {
        if (!selectedNode || selectedNode !== node) {
            openNodePanel(node);
        }
    });

    renderer.on("clickStage", () => {
        selectedNode = null;
        document.getElementById('node-panel').style.display = 'none';
        renderer.refresh();
    });

    renderer.on('enterNode', ({ node }) => { hoveredNode = node; renderer.refresh(); });
    renderer.on('leaveNode', () => { hoveredNode = null; renderer.refresh(); });

    renderer.setSetting('nodeReducer', (node, data) => {
        const res = { ...data };
        const muted = getMutedColor();

        if (searchMatches !== null) {
            if (!searchMatches.has(node)) {
                res.color = muted;
                res.label = '';
                res.zIndex = 0;
            } else {
                res.zIndex = 2;
                res.size = (data.size || 4) * 1.4;
            }
            return res;
        }

        if (hoveredNode && hoveredNode !== node && !graph.hasEdge(node, hoveredNode) && !graph.hasEdge(hoveredNode, node)) {
            res.color = muted;
            res.zIndex = 0;
        } else if (selectedNode && selectedNode !== node && !graph.hasEdge(node, selectedNode) && !graph.hasEdge(selectedNode, node)) {
            res.color = muted;
            res.zIndex = 0;
        } else {
            res.zIndex = 1;
        }
        if (node === hoveredNode || node === selectedNode) {
            res.zIndex = 2;
        }
        return res;
    });

    renderer.setSetting('edgeReducer', (edge, data) => {
        const res = { ...data };
        const source = graph.source(edge);
        const target = graph.target(edge);
        const muted = getMutedColor();

        if (searchMatches !== null) {
            if (!searchMatches.has(source) && !searchMatches.has(target)) {
                res.hidden = true;
            }
            return res;
        }

        if (hoveredNode && source !== hoveredNode && target !== hoveredNode) {
            res.color = muted;
            res.hidden = true;
        } else if (selectedNode && source !== selectedNode && target !== selectedNode) {
            res.color = muted;
            res.hidden = true;
        } else if (hoveredNode || selectedNode) {
            res.size = 2;
            res.color = document.documentElement.getAttribute('data-theme') === 'dark' ? '#6b7280' : '#94a3b8';
        }
        return res;
    });

    document.getElementById('btn-close-panel').addEventListener('click', () => {
        selectedNode = null;
        document.getElementById('node-panel').style.display = 'none';
        renderer.refresh();
    });

    document.getElementById('btn-view-details').addEventListener('click', () => {
        if (!selectedNode) return;
        openDetailsModal(selectedNode);
    });

    document.getElementById('graph-search').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        const countEl = document.getElementById('graph-search-count');
        if (!q) {
            searchMatches = null;
            countEl.textContent = '';
            const eb = document.getElementById('btn-search-export');
            eb.style.opacity = '0.4'; eb.style.pointerEvents = 'none';
            renderer.refresh();
            return;
        }
        searchMatches = new Set();
        graph.forEachNode((node, attrs) => {
            if (attrs.label && attrs.label.toLowerCase().includes(q)) {
                searchMatches.add(node);
            }
        });
        const n = searchMatches.size;
        countEl.textContent = n === 0 ? 'No matches' : n + ' node' + (n === 1 ? '' : 's') + ' matched';
        countEl.style.color = n === 0 ? 'var(--red)' : 'var(--text-muted)';
        const exportBtn = document.getElementById('btn-search-export');
        if (n > 0) {
            exportBtn.style.opacity = '1';
            exportBtn.style.pointerEvents = 'auto';
        } else {
            exportBtn.style.opacity = '0.4';
            exportBtn.style.pointerEvents = 'none';
        }
        renderer.refresh();
    });
});

let detailsHistory =[];
let detailsHistPos = -1;


function openDetailsModal(nodeId, addToHistory = true) {
    if (addToHistory) {
        detailsHistory = detailsHistory.slice(0, detailsHistPos + 1);
        detailsHistory.push(nodeId);
        detailsHistPos = detailsHistory.length - 1;
    }
    detailsUpdateNavButtons();

    // ─── NEW: Sync Graph Selection (No Camera Movement) ───
    // This updates the selected node variable, refreshes the side panel, 
    // and redraws the canvas to highlight the node safely.
    openNodePanel(nodeId);
    // ──────────────────────────────────────────────────────

    const attrs = graph.getNodeAttributes(nodeId);
    document.getElementById('details-title').textContent = attrs.label;
    const typeEl = document.getElementById('details-type');
    typeEl.textContent = attrs.node_type;
    typeEl.className = 'kge-type-pill kge-pill-' + (attrs.node_type || 'default');

    const body = document.getElementById('details-body');
    body.innerHTML = '<div class="details-empty" style="font-style:italic;">Loading…</div>';

    document.getElementById('modalDetails').style.display = 'flex';
    body.scrollTop = 0;

    const docIds = attrs.doc_ids ||[];
    if (docIds.length === 0) {
        body.innerHTML = '<div class="details-empty">No document references found.</div>';
        body.appendChild(detailsBuildConnections(nodeId));
        return;
    }
    
    // Fetch details strictly from the primary document owning this entity
    const docId = docIds[0];
    
    $.get(`api_lore.php?doc_id=${docId}&mode=entity&query=${encodeURIComponent(attrs.label)}`, res => {
        if (!res || res.status !== 'success' || !res.data) {
            body.innerHTML = '<div class="details-empty">Entity not found in lore details.</div>';
            body.appendChild(detailsBuildConnections(nodeId));
            return;
        }
        
        const ent = res.data;
        let html = '';
        
        if (ent.aliases && ent.aliases.length) {
            html += `<p><strong>Aliases:</strong> ${escHtml(ent.aliases.join(', '))}</p>`;
        }
        if (ent.roles && ent.roles.length) {
            html += `<p><strong>Roles:</strong> ${escHtml(ent.roles.join(', '))}</p>`;
        }
        if (ent.attributes && Object.keys(ent.attributes).length) {
            html += `<h4>Attributes</h4><ul>`;
            for (const [k, v] of Object.entries(ent.attributes)) {
                let valStr = Array.isArray(v) ? v.join(', ') : (typeof v === 'object' ? JSON.stringify(v) : v);
                html += `<li><strong>${escHtml(k)}:</strong> ${escHtml(valStr)}</li>`;
            }
            html += `</ul>`;
        }
        if (ent.timeline && ent.timeline.length) {
            html += `<h4>Timeline</h4><ul>`;
            ent.timeline.forEach(t => {
                const dateStr = t.date ? `[${t.date}] ` : '';
                html += `<li>${escHtml(dateStr + t.text)} <small style="color:var(--text-muted)">(${t.type})</small></li>`;
            });
            html += `</ul>`;
        }
        
        body.innerHTML = html || '<div class="details-empty">No extra details available.</div>';
        body.appendChild(detailsBuildConnections(nodeId));
        body.scrollTop = 0;
    }, 'json').fail(() => {
        body.innerHTML = '<div class="details-empty">Network error loading content.</div>';
        body.appendChild(detailsBuildConnections(nodeId));
    });
}

function detailsBuildConnections(nodeId) {
    const nid = nodeId.toString();
    const outgoing =[];
    const incoming =[];

    graph.forEachOutboundEdge(nid, (edge, attrs, source, target) => {
        if (target !== nid && graph.hasNode(target)) {
            outgoing.push({ id: target, label: graph.getNodeAttribute(target, 'label'),
                            type: graph.getNodeAttribute(target, 'node_type'),
                            rel: attrs.label || '' });
        }
    });
    graph.forEachInboundEdge(nid, (edge, attrs, source, target) => {
        if (source !== nid && graph.hasNode(source)) {
            incoming.push({ id: source, label: graph.getNodeAttribute(source, 'label'),
                            type: graph.getNodeAttribute(source, 'node_type'),
                            rel: attrs.label || '' });
        }
    });

    if (!outgoing.length && !incoming.length) return document.createDocumentFragment();

    const wrap = document.createElement('div');
    wrap.className = 'details-connections';

    function makeSection(title, items) {
        if (!items.length) return;
        const h4 = document.createElement('h4');
        h4.textContent = title + ' (' + items.length + ')';
        wrap.appendChild(h4);
        const list = document.createElement('div');
        list.className = 'details-conn-list';
        items.forEach(item => {
            const pill = document.createElement('button');
            pill.className = 'details-conn-pill';
            const dot = document.createElement('span');
            dot.style.cssText = 'width:7px;height:7px;border-radius:50%;flex-shrink:0;background:' +
                                 (typeColors[item.type] || typeColors['default']);
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

    makeSection('Outgoing Relationships', outgoing);
    makeSection('Incoming Relationships', incoming);
    return wrap;
}

function detailsUpdateNavButtons() {
    const back = document.getElementById('details-btn-back');
    const fwd  = document.getElementById('details-btn-fwd');
    const canBack = detailsHistPos > 0;
    const canFwd  = detailsHistPos < detailsHistory.length - 1;
    back.disabled = !canBack; back.style.opacity = canBack ? '1' : '0.35';
    fwd.disabled  = !canFwd;  fwd.style.opacity  = canFwd  ? '1' : '0.35';
}

function detailsHistoryBack() {
    if (detailsHistPos > 0) {
        detailsHistPos--;
        openDetailsModal(detailsHistory[detailsHistPos], false);
    }
}

function detailsHistoryFwd() {
    if (detailsHistPos < detailsHistory.length - 1) {
        detailsHistPos++;
        openDetailsModal(detailsHistory[detailsHistPos], false);
    }
}

let toastTimer;
function toast(msg, type='success') {
    const el = document.getElementById('kg-toast');
    el.textContent = msg;
    el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--green)';
    el.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.style.display='none', 2800);
}

document.getElementById('modalDetails').addEventListener('click', function(e) {
    if (e.target === this) { document.getElementById('modalDetails').style.display='none'; detailsHistory =[]; detailsHistPos = -1; }
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('modalDetails').style.display='none'; detailsHistory =[]; detailsHistPos = -1;
    }
});

function exportSearchMatches() {
    if (!searchMatches || searchMatches.size === 0) return;

    const exportData = {
        nodes:[],
        edges:[]
    };

    searchMatches.forEach(nodeId => {
        const attrs = graph.getNodeAttributes(nodeId);
        exportData.nodes.push({
            id: nodeId,
            name: attrs.label,
            node_type: attrs.node_type
        });
    });

    graph.forEachEdge((edge, attrs, source, target) => {
        if (searchMatches.has(source) && searchMatches.has(target)) {
            exportData.edges.push({
                source: source,
                target: target,
                relationship: attrs.label
            });
        }
    });

    const q = document.getElementById('graph-search').value.trim()
                     .toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
    const date   = new Date().toISOString().slice(0, 10);
    const fname  = 'lore_search_' + (q || 'export') + '_' + date + '.json';
    const blob   = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url    = URL.createObjectURL(blob);
    const a      = document.createElement('a');
    a.href = url; a.download = fname; a.click();
    URL.revokeObjectURL(url);
    toast('Exported ' + exportData.nodes.length + ' node(s) ✓');
}
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>