<?php
// public/view_kg_docs.php
// Knowledge Graph Story Bible — UI clone of view_curated_docs.php (Showrunner V9.6)
// Reads from kg_categories, kg_nodes, kg_node_items instead of md_doc_analysis.
// Supports deep-linking: ?node_id=123  ?category_id=10  ?focus_node=NodeName
// Embed mode: ?embed=1
// - Added: Visual Sketch Preview Gallery integration inside modal
// - Added: Sketch Analysis Curation Pill and Modal
// ----------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// --- AJAX HANDLER FOR VISUALS FETCH ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_visuals') {
    header('Content-Type: application/json');
    try {
        $entityName = $_POST['entity_name'] ?? '';
        
        // Robust lookup by entity name for KG sketches
        $sqlHistory = "
            SELECT slh.sketch_id, s.name, s.description,
                   sa.overall_quality, sa.classification, sa.scoring, sa.entities, sa.thematics, sa.recommendations
            FROM sketch_lore_history slh
            JOIN sketches s ON slh.sketch_id = s.id
            LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE slh.entity_name = ?
            ORDER BY slh.id DESC LIMIT 1
        ";
        $stmt = $pdo->prepare($sqlHistory);
        $stmt->execute([$entityName]);
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

$pageTitle = "KG Story Bible 📜";

// --- PARAMS ---
$filterCatId  = (int)($_GET['category_id'] ?? 0);
$filterType   = $_GET['node_type'] ?? '';
$focusNodeId  = (int)($_GET['node_id'] ?? 0);       // deep-link to specific node
$focusNodeName = $_GET['focus_node'] ?? '';          // deep-link by name
$isEmbed      = isset($_GET['embed']);

// -----------------------------------------------------------------------
// FETCH ALL CATEGORIES (build tree)
// -----------------------------------------------------------------------
$allCats = $pdo->query("SELECT id, parent_id, name, sort_order FROM kg_categories ORDER BY sort_order ASC, name ASC")
               ->fetchAll(PDO::FETCH_ASSOC);

// Build parent->children map
$catChildren = [];
$catMap =[];
foreach ($allCats as $c) {
    $catMap[$c['id']] = $c;
    $catChildren[$c['parent_id'] ?? 0][] = $c['id'];
}

// Top-level categories (parent_id IS NULL)
$rootCatIds = array_map(fn($c) => $c['id'],
    array_filter($allCats, fn($c) => $c['parent_id'] === null)
);

// -----------------------------------------------------------------------
// FETCH NODES
// -----------------------------------------------------------------------
$nodeWhere = "WHERE n.status = 'active'";
$nodeParams =[];

if ($filterCatId > 0) {
    // Include the category and all its descendants
    $familyIds =[];
    $queue =[$filterCatId];
    while (!empty($queue)) {
        $cid = array_shift($queue);
        $familyIds[] = $cid;
        foreach ($catChildren[$cid] ??[] as $childId) {
            $queue[] = $childId;
        }
    }
    $placeholders = implode(',', array_fill(0, count($familyIds), '?'));
    $nodeWhere .= " AND n.category_id IN ($placeholders)";
    $nodeParams = $familyIds;
}

if ($filterType !== '') {
    $nodeWhere .= " AND n.node_type = ?";
    $nodeParams[] = $filterType;
}

$nodesSql = "
    SELECT n.*, c.name AS category_name, c.parent_id AS category_parent_id
    FROM kg_nodes n
    LEFT JOIN kg_categories c ON n.category_id = c.id
    $nodeWhere
    ORDER BY c.sort_order ASC, n.sort_order ASC, n.name ASC
";
$stmt = $pdo->prepare($nodesSql);
$stmt->execute($nodeParams);
$allNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------------------------------
// FETCH ALL LINKED ITEMS FOR ALL NODES (one query)
// -----------------------------------------------------------------------
if (!empty($allNodes)) {
    $nodeIds = array_column($allNodes, 'id');
    $ph = implode(',', array_fill(0, count($nodeIds), '?'));
    $itemsSql = "SELECT * FROM kg_node_items WHERE node_id IN ($ph) ORDER BY node_id, sort_order ASC";
    $istmt = $pdo->prepare($itemsSql);
    $istmt->execute($nodeIds);
    $allItems = $istmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by node_id
    $itemsByNode =[];
    foreach ($allItems as $item) {
        $itemsByNode[$item['node_id']][] = $item;
    }
} else {
    $itemsByNode =[];
}

// -----------------------------------------------------------------------
// BUILD MASTER PAYLOAD PER "DOCUMENT" = CATEGORY GROUP
// -----------------------------------------------------------------------
// We group nodes by their top-level category to create "cards" (like lore docs).
// Each top-level category becomes one lore-card.

function getTopLevelCat($catId, $catMap) {
    $visited =[];
    while ($catId !== null && !in_array($catId, $visited)) {
        $visited[] = $catId;
        $parent = $catMap[$catId]['parent_id'] ?? null;
        if ($parent === null) return $catId;
        $catId = $parent;
    }
    return $catId;
}

// Group all nodes by top-level category
$nodesByTopCat =[];
foreach ($allNodes as &$node) {
    $topCat = getTopLevelCat($node['category_id'], $catMap);
    if ($topCat === null) $topCat = 0;
    $nodesByTopCat[$topCat][] = $node;
}
unset($node);

// Determine which top-level cats to show
$topCatIdsToShow = array_filter(
    array_keys($nodesByTopCat),
    fn($id) => $id !== 0
);

// Also grab uncategorised nodes
$uncatNodes = $nodesByTopCat[0] ??[];

// -----------------------------------------------------------------------
// NODE TYPE ICONS
// -----------------------------------------------------------------------
function nodeTypeIcon($type) {
    return match($type) {
        'character'    => '👤',
        'location'     => '📍',
        'event'        => '📅',
        'concept'      => '💡',
        'arc'          => '🌀',
        'episode'      => '🎬',
        'relationship' => '🔗',
        default        => '📝',
    };
}

// -----------------------------------------------------------------------
// BUILD PAYLOAD FOR JS — mirrors the structure view_curated_docs.php uses
// For KG: world = { [category_name]: [nodes...] }
//          story = { [node_type]: [nodes...] } (within each top-level cat)
//          curator = summary text from node keywords/description
// -----------------------------------------------------------------------
function buildKgPayload($topCatId, $nodes, $catMap, $itemsByNode) {
    global $catMap; // ensure access

    // Group nodes by their immediate category (sub-folder)
    $bySubCat  = [];  // subcat_name => [nodes]
    $byType    =[];  // node_type   => [nodes]

    foreach ($nodes as $node) {
        $subCatName = $node['category_name'] ?? 'General';
        $bySubCat[$subCatName][] = buildNodeEntity($node, $itemsByNode);
        $byType[$node['node_type']][] = buildNodeEntity($node, $itemsByNode);
    }

    $topCatName = isset($GLOBALS['catMap'][$topCatId]) ? $GLOBALS['catMap'][$topCatId]['name'] : 'General';

    return [
        'meta' =>[
            'cat_id'   => $topCatId,
            'name'     => $topCatName,
            'node_count' => count($nodes),
        ],
        'world'   => $bySubCat,   // sub-category folders = world entities
        'story'   => $byType,     // node_type groups = story engine
        'curator' =>[
            'summary'          => "Knowledge Graph section: $topCatName. Contains " . count($nodes) . " nodes.",
            'themes'           => array_values(array_unique(array_column($nodes, 'node_type'))),
            'mood'             => '',
            'production_notes' => [],
            'bible'            => buildCatBible($nodes),
        ],
    ];
}

function buildNodeEntity($node, $itemsByNode) {
    $linked = $itemsByNode[$node['id']] ?? [];

    $relationships = [];
    foreach ($linked as $item) {
        $relationships[] = [
            'target'  => $item['item_label'] ?? ('ID:' . $item['item_id']),
            'type'    => $item['item_type'],
            'nature'  => $item['relationship'] ?? '',
            'desc'    => $item['note'] ?? '',
        ];
    }

    $keywords = [];
    if (!empty($node['keywords'])) {
        $keywords = array_map('trim', explode(',', $node['keywords']));
    }

    return[
        'id'            => $node['id'],
        'name'          => $node['name'],
        'node_type'     => $node['node_type'],
        'description'   => $node['description'] ?? '',
        'content'       => $node['content'] ?? '',
        'keywords'      => $keywords,
        'relationships' => $relationships,
        'category'      => $node['category_name'] ?? '',
        'status'        => $node['status'],
        'created_at'    => $node['created_at'],
        'updated_at'    => $node['updated_at'],
        'content_chars' => strlen($node['content'] ?? ''),
        // aliases = keywords for linking
        'aliases'       => $keywords,
        // roles = node_type label
        'roles'         => [$node['node_type']],
        'attributes'    =>[
            'type'         => $node['node_type'],
            'category'     => $node['category_name'] ?? '',
            'keywords'     => implode(', ', $keywords),
            'content_size' => strlen($node['content'] ?? '') . ' chars',
            'updated'      => $node['updated_at'],
        ],
    ];
}

function buildCatBible($nodes) {
    $lines =[];
    foreach ($nodes as $node) {
        if (!empty($node['content'])) {
            $lines[] = "=== " . $node['name'] . " ===";
            $lines[] = substr($node['content'], 0, 800) . (strlen($node['content']) > 800 ? '…' : '');
            $lines[] = "";
        }
    }
    return implode("\n", $lines);
}

// Build all payloads
$payloads =[];
foreach ($topCatIdsToShow as $catId) {
    $payloads[$catId] = buildKgPayload($catId, $nodesByTopCat[$catId], $catMap, $itemsByNode);
}

// Uncategorised nodes — group as a synthetic "General" card
if (!empty($uncatNodes)) {
    $payloads[0] = buildKgPayload(0, $uncatNodes, $catMap, $itemsByNode);
}

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

<link rel="stylesheet" href="/css/base.css">
<style>

html { font-size: 130% !important; }

/* --- CORE THEME --- */
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

.node-count-badge {
    background: var(--accent); color: #fff; padding: 2px 8px; border-radius: 12px;
    font-size: 0.75rem; font-weight: 700; white-space: nowrap;
}

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

/* --- DOWNLOAD BUTTON --- */
.download-btn {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
    border: 1px solid rgba(139, 92, 246, 0.3); color: var(--accent);
    padding: 4px 10px; border-radius: 6px; font-weight: 600; cursor: pointer;
    transition: all 0.2s; font-size: 0.75rem;
    display: inline-flex; align-items: center; gap: 6px; margin-left: 10px;
}
.download-btn:hover { background: rgba(139, 92, 246, 0.15); transform: translateY(-1px); }

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

/* External link button in modal header */
.modal-ext-btn {
    background: transparent; border: 1px solid var(--border); color: var(--text-muted);
    border-radius: 6px; padding: 6px 10px; cursor: pointer; font-size: 0.85rem;
    text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s;
}
.modal-ext-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }

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

/* Content renderer */
.node-content-md { font-size: 1rem; line-height: 1.7; color: var(--text); white-space: pre-wrap; }
.node-content-md h1, .node-content-md h2, .node-content-md h3 { color: var(--accent); margin-top: 1.4em; margin-bottom: 0.4em; }
.node-content-md strong, .node-content-md b { color: var(--text); }
.node-content-md hr { border: none; border-top: 1px dashed var(--border); margin: 1.5em 0; }

/* Node type badge in card header */
.node-type-badge {
    font-size: 0.7rem; padding: 2px 8px; border-radius: 10px;
    background: rgba(59,130,246,.12); color: var(--accent);
    border: 1px solid rgba(59,130,246,.25); font-weight: 600; white-space: nowrap;
}

/* Linked items in modal */
.linked-item-row {
    display: flex; align-items: center; gap: 8px; padding: 8px 12px;
    border-bottom: 1px solid var(--border); font-size: 0.88rem; transition: background 0.15s;
}
.linked-item-row:hover { background: rgba(59,130,246,0.04); }
.linked-item-row:last-child { border-bottom: none; }
.li-type-pill {
    font-size: 0.72rem; font-weight: 700; padding: 2px 7px; border-radius: 10px;
    background: rgba(139,92,246,0.12); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.25);
    white-space: nowrap; flex-shrink: 0;
}
.li-label { flex: 1; font-weight: 600; color: var(--accent); cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.li-label:hover { text-decoration: underline; }
.li-rel { font-size: 0.75rem; color: var(--text-muted); background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 2px 8px; white-space: nowrap; flex-shrink: 0; }

/* --- VISUAL GALLERY --- */
.visual-container { display: none; flex-direction: column; background: var(--fold-bg); border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 25px; }
.swiper-slide { width: auto; height: 100%; display: flex; align-items: center; justify-content: center; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--border); }
.swiper-slide img { width: 240px; height: 240px; display: block; object-fit: contain; }

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

.curation-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; justify-content: center; align-items: center; }
.curation-modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
.curation-modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: var(--text-muted); background: none; border: none; line-height: 1; }
.curation-modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; align-items: flex-start; }
.curation-modal-label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; display: block; margin-bottom: 4px; min-width: 100px; }
.curation-modal-value { font-size: 0.95rem; display: block; flex: 1; }

@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes slideUp { from { transform: translate(-50%, 30px); opacity:0; } to { transform: translate(-50%, 0); opacity:1; } }

<?php if ($isEmbed): ?>
    body { background: transparent !important; padding: 0 !important; }
    #page-header, .header-main-page, #debug-bar { display: none !important; }
    .lore-grid { padding: 0 !important; gap: 0 !important; display: block !important; }
    .lore-card { border: none !important; box-shadow: none !important; border-radius: 0 !important; min-height: 100vh; margin: 0; }
    .card-header { display: none !important; }
    .card-footer { display: none !important; }
    .card-body { padding: 20px !important; }
    .modal-window { top: 0; bottom: 0; left: 0; right: 0; width: 100%; max-width: none; transform: none; border-radius: 0; border: none; }
    .modal-backdrop { opacity: 1 !important; background: var(--card); }
<?php endif; ?>
</style>

<?php if (!$isEmbed): ?>
<div class="header-main-page" style="padding: 20px; border-bottom: 1px solid var(--border); display:flex; gap:20px; align-items:center; flex-wrap:wrap; background: var(--card);">
    <div style="font-size:1.4rem; font-weight:800;">🧠 KG Story Bible <span style="font-weight:400; opacity:0.6;">v1.0</span></div>
    <form method="GET" style="display:flex; gap:10px; flex:1; flex-wrap:wrap;">
        <select name="category_id" style="padding:10px; border-radius:6px; border:1px solid var(--border); background: var(--card); color: var(--text);">
            <option value="">All Categories</option>
            <?php foreach($allCats as $c): ?>
                <option value="<?= h($c['id']) ?>" <?= $filterCatId == $c['id'] ? 'selected' : '' ?>>
                    <?= str_repeat('&nbsp;&nbsp;', $c['parent_id'] ? 1 : 0) ?>
                    <?= h($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="node_type" style="padding:10px; border-radius:6px; border:1px solid var(--border); background: var(--card); color: var(--text);">
            <option value="">All Types</option>
            <?php foreach(['character','location','event','concept','arc','episode','relationship','note'] as $nt): ?>
                <option value="<?= h($nt) ?>" <?= $filterType === $nt ? 'selected' : '' ?>>
                    <?= nodeTypeIcon($nt) ?> <?= ucfirst($nt) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="entity-btn" style="background:var(--accent); color:#fff; border:none; padding: 10px 20px;">Filter</button>
        <a href="kg_view.php" class="entity-btn" style="text-decoration:none;">🗂 KG Editor</a>
    </form>
    <div style="color:var(--text-muted); font-size:0.85rem;">
        <?= count($allNodes) ?> nodes
    </div>
</div>
<?php endif; ?>

<!-- MAIN GRID -->
<div class="lore-grid">
    <?php foreach($payloads as $catId => $payload):
        $displayId = 'kg_' . $catId;
        $catName = $payload['meta']['name'];
        $nodeCount = $payload['meta']['node_count'];
    ?>
    <div class="lore-card" id="card-<?= h($displayId) ?>">
        <div class="card-header" onclick="toggleDocCard('<?= h($displayId) ?>')">
            <div class="header-main">
                <div class="toggle-icon">▼</div>
                <div>
                    <div class="doc-title" style="font-weight:700;"><?= h($catName) ?></div>
                    <div class="doc-cat" style="font-size:0.75rem; color:var(--text-muted);">Knowledge Graph Section</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="node-count-badge"><?= $nodeCount ?></span>
                <?php if ($catId > 0): ?>
                <a href="?category_id=<?= h($catId) ?>" class="doc-direct-link" onclick="event.stopPropagation()" title="Filter to this category">🔍</a>
                <?php endif; ?>
                <a href="kg_view.php" class="doc-direct-link" onclick="event.stopPropagation()" title="Open KG Editor">⚙️</a>
            </div>
        </div>

        <div class="card-body">
            <div style="font-weight: bold; font-style: italic; font-size:1rem;"><?= h($catName) ?></div>

            <!-- Curator Insight Button -->
            <button class="insight-btn" onclick="openModalFromGrid('<?= h($displayId) ?>', 'curator', 'main', 0)">
                <span>🧠</span> Section Overview &amp; Content Map
            </button>

            <!-- Folders rendered via JS -->
            <div class="js-folders" data-doc-id="<?= h($displayId) ?>"></div>
        </div>

        <div class="card-footer">
            <details class="raw-foldable">
                <summary class="raw-summary">RAW JSON SOURCE</summary>
                <div class="raw-content"><?= h(json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></div>
            </details>
        </div>

        <!-- PAYLOAD -->
        <script type="application/json" id="payload-<?= h($displayId) ?>">
            <?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?>
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
            <div class="modal-nav">
                <button class="nav-btn" id="navBackBtn" onclick="modalGoBack()" title="Back">&lsaquo;</button>
                <button class="nav-btn" id="navFwdBtn" onclick="modalGoFwd()" title="Forward">&rsaquo;</button>
            </div>
            <a id="modalKgLink" class="modal-ext-btn" href="#" target="_blank" title="Open in KG Editor" style="display:none;">
                ⚙️ Edit
            </a>
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
 * KG STORY BIBLE ENGINE v1.0
 * UI clone of Showrunner V9.6 — powered by KG graph data.
 * Each "document" = a top-level KG category.
 * "world" = sub-category folders.
 * "story" = node_type groups.
 * Each entity = a kg_node with its linked items.
 */

const loreIndex   = {};       // name -> { docId, type, cat, idx }
const payloadStore = {};      // docId -> payload
let modalHistory    =[];
let modalHistoryIdx = -1;
let currentNodeId   = null;   // kg_node id currently shown in modal
let visualSwiper = null;

// ── INIT ──────────────────────────────────────────────────────────────────────
function init() {
    const urlParams = new URLSearchParams(window.location.search);
    const isEmbed   = urlParams.has('embed');

    document.querySelectorAll('.lore-card').forEach(card => {
        const id    = card.id.replace('card-', '');
        const state = localStorage.getItem('kg_v1_doc_' + id);
        if (isEmbed) {
            card.classList.remove('collapsed');
        } else if (state === 'closed') {
            card.classList.add('collapsed');
        }
    });

    document.querySelectorAll('script[type="application/json"]').forEach(el => {
        try {
            const id   = el.id.replace('payload-', '');
            const data = JSON.parse(el.textContent || '{}');
            payloadStore[id] = data;
            renderFolders(id, data);
            indexDocument(id, data);
        } catch(e) { console.error('payload parse error', e); }
    });

    // Deep link: ?node_id=123
    const nodeIdParam = parseInt(urlParams.get('node_id') || '0');
    if (nodeIdParam) {
        setTimeout(() => {
            const key = 'node_id:' + nodeIdParam;
            if (loreIndex[key]) {
                const e = loreIndex[key];
                openModalFromGrid(e.docId, e.type, e.cat, e.idx);
            }
        }, 200);
    }

    // Deep link: ?focus_node=NodeName
    const focusName = urlParams.get('focus_node');
    if (focusName) {
        setTimeout(() => {
            const key = normKey(focusName);
            if (loreIndex[key]) {
                const e = loreIndex[key];
                openModalFromGrid(e.docId, e.type, e.cat, e.idx);
            }
        }, 200);
    }
}

function normKey(name) {
    return String(name || '').trim().toLowerCase();
}

// ── INDEXING ──────────────────────────────────────────────────────────────────
function indexDocument(docId, data) {
    // Index world items (sub-category buckets)
    if (data.world) {
        Object.keys(data.world).forEach(cat => {
            const items = data.world[cat];
            if (!Array.isArray(items)) return;
            items.forEach((item, idx) => {
                const candidates = gatherNames(item);
                candidates.forEach(c => {
                    const k = normKey(c);
                    if (!k) return;
                    loreIndex[k] = { docId, type: 'world', cat, idx };
                });
                // Index by node id too
                if (item.id) loreIndex['node_id:' + item.id] = { docId, type: 'world', cat, idx };
            });
        });
    }
    // Index story items (node_type buckets)
    if (data.story) {
        Object.keys(data.story).forEach(cat => {
            const items = data.story[cat];
            if (!Array.isArray(items)) return;
            items.forEach((item, idx) => {
                const candidates = gatherNames(item);
                candidates.forEach(c => {
                    const k = normKey(c);
                    if (!k) return;
                    // Don't overwrite world index (world = canonical)
                    if (!loreIndex[k]) loreIndex[k] = { docId, type: 'story', cat, idx };
                });
                if (item.id && !loreIndex['node_id:' + item.id]) {
                    loreIndex['node_id:' + item.id] = { docId, type: 'story', cat, idx };
                }
            });
        });
    }
}

function gatherNames(item) {
    if (typeof item === 'string') return [item];
    if (!item || typeof item !== 'object') return[];
    const out =[];
    if (item.name)    out.push(item.name);
    if (item.title)   out.push(item.title);
    if (Array.isArray(item.aliases)) item.aliases.forEach(a => out.push(a));
    if (Array.isArray(item.keywords)) item.keywords.forEach(k => out.push(k));
    return out.filter(Boolean);
}

// ── CARD TOGGLING ─────────────────────────────────────────────────────────────
window.toggleDocCard = function(docId) {
    const card = document.getElementById('card-' + docId);
    if (!card) return;
    card.classList.toggle('collapsed');
    localStorage.setItem('kg_v1_doc_' + docId, card.classList.contains('collapsed') ? 'closed' : 'open');
};

// ── FOLDER RENDERING ──────────────────────────────────────────────────────────
function renderFolders(docId, data) {
    const container = document.querySelector(`.js-folders[data-doc-id="${docId}"]`);
    if (!container) return;

    if (data.world && Object.keys(data.world).length > 0) {
        container.appendChild(createHeader('By Sub-Category'));
        Object.keys(data.world).forEach(cat => {
            const items = data.world[cat];
            if (items && items.length) container.appendChild(createFolder(docId, 'world', cat, items));
        });
    }

    if (data.story && Object.keys(data.story).length > 0) {
        container.appendChild(createHeader('By Node Type'));
        Object.keys(data.story).forEach(cat => {
            const items = data.story[cat];
            if (items && items.length) container.appendChild(createFolder(docId, 'story', cat, items));
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
    const group   = document.createElement('div');
    group.className = 'cat-group';

    const summary = document.createElement('div');
    summary.className = 'cat-summary';
    const icon = type === 'story' ? '🏷️' : '📂';
    summary.innerHTML = `
        <span>${icon} ${cat.replace(/_/g,' ')}</span>
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="background:var(--accent); color:white; padding:2px 8px; border-radius:12px; font-size:0.75rem;">${items.length}</span>
            <button class="download-btn" onclick="downloadCategoryJSON('${docId}','${type}','${cat}',event)" title="Download JSON">⬇️</button>
            <button class="download-btn" onclick="downloadCategoryMD('${docId}','${type}','${cat}',event)" title="Download Markdown">📝</button>
        </div>`;

    const content  = document.createElement('div');
    content.className = 'cat-content';

    const stateKey   = `kg_v1_cat_${docId}_${cat}`;
    const savedState = localStorage.getItem(stateKey);
    content.style.display = savedState === 'open' ? 'flex' : 'none';

    items.forEach((item, idx) => {
        const btn   = document.createElement('button');
        btn.className = `entity-btn ${type}-item`;

        let label = 'Item';
        if (typeof item === 'string') label = item;
        else if (item && typeof item === 'object') {
            const typeIcon = item.node_type ? getTypeIcon(item.node_type) : '';
            label = (typeIcon ? typeIcon + ' ' : '') + (item.name || item.title || 'Unnamed');
        }
        if (label.length > 80) label = label.substring(0, 77) + '…';
        btn.innerText = label;

        btn.dataset.docId = String(docId);
        btn.dataset.type  = type;
        btn.dataset.cat   = cat;
        btn.dataset.idx   = String(idx);
        btn.onclick = (e) => {
            e.stopPropagation();
            openModalFromGrid(btn.dataset.docId, btn.dataset.type, btn.dataset.cat, parseInt(btn.dataset.idx, 10));
        };
        content.appendChild(btn);
    });

    summary.onclick = () => {
        const isOpen = content.style.display !== 'none';
        content.style.display = isOpen ? 'none' : 'flex';
        localStorage.setItem(stateKey, isOpen ? 'closed' : 'open');
    };

    group.appendChild(summary);
    group.appendChild(content);
    return group;
}

function getTypeIcon(type) {
    const map = { character:'👤', location:'📍', event:'📅', concept:'💡', arc:'🌀', episode:'🎬', relationship:'🔗', note:'📝' };
    return map[type] || '';
}

// Curation Safe HTML Attribute Escaper
function escapeHtmlAttr(str) {
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ── VISUAL GALLERY FETCHING ───────────────────────────────────────────────────
function fetchVisuals(name) {
    const container = document.getElementById('mVisuals');
    const wrapper = document.getElementById('sketchWrapper');
    container.style.display = 'none';
    wrapper.innerHTML = '';
    
    const formData = new FormData();
    formData.append('action', 'fetch_visuals');
    formData.append('entity_name', name);

    fetch(window.location.pathname, {
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

// ── MODAL ─────────────────────────────────────────────────────────────────────
window.openModalFromGrid = function(docId, type, cat, idx) {
    modalHistory    =[];
    modalHistoryIdx = -1;
    openModal(docId, type, cat, idx, true);
};

function openModal(docId, type, cat, idx, recordHistory = true) {
    const master = payloadStore[docId];
    if (!master) return;

    if (recordHistory) {
        if (modalHistoryIdx < modalHistory.length - 1) modalHistory = modalHistory.slice(0, modalHistoryIdx + 1);
        modalHistory.push({ docId, type, cat, idx });
        modalHistoryIdx++;
    }
    updateNavButtons();

    currentNodeId = null;

    if (type === 'curator') {
        document.getElementById('mVisuals').style.display = 'none'; // Hide gallery in curator view
        renderCuratorView(master.curator, master.meta ? master.meta.name : '');
        document.getElementById('modalKgLink').style.display = 'none';
    } else {
        const bucket = master[type] && master[type][cat] ? master[type][cat] : null;
        if (!Array.isArray(bucket) || bucket.length <= idx) {
            document.getElementById('mTitle').innerText  = cat;
            document.getElementById('mSubtitle').innerText = type.toUpperCase() + ' / ' + cat.toUpperCase();
            document.getElementById('mContent').innerHTML = '<div class="studio-note">Item not found.</div>';
            document.getElementById('mVisuals').style.display = 'none';
        } else {
            const item = bucket[idx];
            currentNodeId = item.id || null;

            let name = (typeof item === 'string') ? item : (item.name || item.title || cat);
            const titleEl = document.getElementById('mTitle');
            titleEl.innerText = name.length > 60 ? cat.replace(/_/g,' ') : name;
            titleEl.title     = name;
            document.getElementById('mSubtitle').innerText = type.toUpperCase() + ' / ' + cat.replace(/_/g,' ').toUpperCase();

            // Fetch Sketch Visuals
            fetchVisuals(name);

            const extLink = document.getElementById('modalKgLink');
            if (currentNodeId) {
                extLink.href = 'kg_view.php?node_id=' + currentNodeId;
                extLink.style.display = 'inline-flex';
            } else {
                extLink.style.display = 'none';
            }

            const content = document.getElementById('mContent');
            content.innerHTML = '';
            renderNodeEntity(content, item);
            renderRawData(content, item);
        }
    }

    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('modalWindow').style.display   = 'flex';
}

function updateNavButtons() {
    document.getElementById('navBackBtn').disabled = (modalHistoryIdx <= 0);
    document.getElementById('navFwdBtn').disabled  = (modalHistoryIdx >= modalHistory.length - 1);
}
window.modalGoBack = function() {
    if (modalHistoryIdx > 0) { modalHistoryIdx--; const s = modalHistory[modalHistoryIdx]; openModal(s.docId, s.type, s.cat, s.idx, false); }
};
window.modalGoFwd = function() {
    if (modalHistoryIdx < modalHistory.length - 1) { modalHistoryIdx++; const s = modalHistory[modalHistoryIdx]; openModal(s.docId, s.type, s.cat, s.idx, false); }
};

// ── CURATOR VIEW ──────────────────────────────────────────────────────────────
function renderCuratorView(curator, docName) {
    document.getElementById('mTitle').innerText    = '🧠 Section Overview';
    document.getElementById('mSubtitle').innerText = docName;
    document.getElementById('modalKgLink').style.display = 'none';
    const content = document.getElementById('mContent');
    content.innerHTML = '';

    if (curator.themes && curator.themes.length) {
        const sec = createSection('Node Types Present');
        sec.appendChild(createDetailBlock('Types', curator.themes.join(' • ')));
        content.appendChild(sec);
    }

    if (curator.summary) {
        const sec = createSection('Summary');
        sec.appendChild(createDetailBlock('Overview', curator.summary));
        content.appendChild(sec);
    }

    if (curator.bible && curator.bible.trim()) {
        const sec = createSection('Content Excerpts');
        const div = document.createElement('div');
        div.className = 'bible-text';
        let formatted = renderTextWithFences(curator.bible);
        formatted = formatted.replace(/=== (.*?) ===/g, '<div class="bible-header">$1</div>');
        div.innerHTML = formatted;
        sec.appendChild(div);
        content.appendChild(sec);
    }
}

// ── NODE ENTITY RENDERER ──────────────────────────────────────────────────────
function renderNodeEntity(container, item) {
    if (typeof item === 'string') {
        container.appendChild(createDetailBlock('Content', item));
        return;
    }

    // Type badge
    if (item.node_type) {
        const badge = document.createElement('div');
        badge.style.cssText = 'margin-bottom:16px;';
        badge.innerHTML = `<span class="node-type-badge">${getTypeIcon(item.node_type)} ${item.node_type}</span>`;
        if (item.category) badge.innerHTML += ` <span style="font-size:0.8rem; color:var(--text-muted);">in ${escapeHtml(item.category)}</span>`;
        container.appendChild(badge);
    }

    // Description
    if (item.description) {
        container.appendChild(createDetailBlock('Description', item.description));
    }

    // Main content (markdown-ish)
    if (item.content && item.content.trim()) {
        const sec = createSection('Content');
        const div = document.createElement('div');
        div.className = 'bible-text';
        div.innerHTML = renderTextWithFences(item.content);
        const tts = document.createElement('div');
        tts.style.textAlign = 'right';
        tts.innerHTML = '<span class="tts-select-icon" onclick="selectTextForTts(this,event)">🔈 Select All</span>';
        sec.appendChild(tts);
        sec.appendChild(div);
        container.appendChild(sec);
    }

    // Keywords / attributes
    const attrKeys =['keywords', 'content_chars', 'updated_at', 'created_at'];
    const attrs = {};
    attrKeys.forEach(k => { if (item[k] !== undefined && item[k] !== '' && item[k] !== null) attrs[k] = item[k]; });
    if (item.attributes) Object.assign(attrs, item.attributes);

    if (Object.keys(attrs).length > 0) {
        const sec = createSection('Metadata');
        const grid = document.createElement('div');
        grid.className = 'attr-grid';
        Object.keys(attrs).forEach(k => {
            const v = attrs[k];
            if (v === null || v === undefined || v === '') return;
            const card = document.createElement('div');
            card.className = 'attr-card';
            card.innerHTML = `<div class="attr-key"><span>${k.replace(/_/g,' ')}</span><span class="tts-select-icon" onclick="selectTextForTts(this,event)">🔈</span></div><div class="attr-val">${renderTextWithFences(String(v))}</div>`;
            grid.appendChild(card);
        });
        sec.appendChild(grid);
        container.appendChild(sec);
    }

    // Relationships (linked items)
    if (Array.isArray(item.relationships) && item.relationships.length > 0) {
        const sec = createSection('Linked Entities');
        const grid = document.createElement('div');
        grid.className = 'rel-grid';

        // Group by target
        const grouped = {};
        item.relationships.forEach(r => {
            const target = r.target || '—';
            if (!grouped[target]) grouped[target] =[];
            grouped[target].push(r);
        });

        Object.keys(grouped).forEach(target => {
            const rels = grouped[target];
            const card = document.createElement('div');
            card.className = 'rel-card neutral';
            let details = rels.map(r => {
                let s = '';
                if (r.nature) s += `<strong>${escapeHtml(r.nature)}</strong>`;
                if (r.type)   s += (s ? ' · ' : '') + escapeHtml(r.type);
                if (r.desc)   s += `: ${linkify(r.desc)}`;
                return `<div style="margin-top:4px;">${s || '—'}</div>`;
            }).join('');
            card.innerHTML = `<div class="rel-target" onclick="clickLink('${escapeHtml(target)}')">${escapeHtml(target)}</div><div class="rel-desc">${details}</div>`;
            grid.appendChild(card);
        });
        sec.appendChild(grid);
        container.appendChild(sec);
    }
}

// ── HELPERS ───────────────────────────────────────────────────────────────────
function createSection(title) {
    const d = document.createElement('div');
    d.className = 'detail-section';
    d.innerHTML = `<div class="section-head">${title.replace(/_/g,' ')}</div>`;
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

function renderRawData(container, data) {
    const det = document.createElement('details');
    det.className = 'raw-foldable';
    det.innerHTML = `<summary class="raw-summary">INSPECT RAW JSON</summary>`;
    const pre = document.createElement('div');
    pre.className = 'raw-content';
    pre.textContent = JSON.stringify(data, null, 2);
    det.appendChild(pre);
    container.appendChild(det);
}

function renderTextWithFences(text) {
    if (!text) return '';
    text = String(text);
    const fenceRegex = /```(\w*)\n([\s\S]*?)\n```/g;
    let lastIndex = 0;
    let out = '';
    let m;
    while ((m = fenceRegex.exec(text)) !== null) {
        out += linkify(text.slice(lastIndex, m.index));
        const lang  = (m[1]||'').toLowerCase();
        const inner = m[2] || '';
        if (lang === 'json') {
            try { out += `<div class="raw-content">${escapeHtml(JSON.stringify(JSON.parse(inner),null,2))}</div>`; }
            catch(e) { out += `<div class="raw-content">${escapeHtml(inner)}</div>`; }
        } else {
            out += `<div class="raw-content">${escapeHtml(inner)}</div>`;
        }
        lastIndex = fenceRegex.lastIndex;
    }
    out += linkify(text.slice(lastIndex));
    return out;
}

// ── LINKIFICATION ─────────────────────────────────────────────────────────────
function linkify(text) {
    if (!text) return '';
    let out = String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    if (Object.keys(loreIndex).length === 0) return out;
    return out.replace(/([A-Z][a-zA-Z0-9\-\']+(?:\s[A-Z][a-zA-Z0-9\-\']+)*)/g, (match) => {
        const key = normKey(match);
        if (loreIndex[key]) {
            return `<a href="#" class="lore-link" onclick="clickLink('${escapeHtml(match)}'); return false;">${match}</a>`;
        }
        return match;
    });
}

function clickLink(name) {
    const key = normKey(name);
    if (loreIndex[key]) {
        const e = loreIndex[key];
        openModal(e.docId, e.type, e.cat, e.idx);
    }
}

function closeModal() {
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('modalWindow').style.display   = 'none';
    currentNodeId = null;
}

function escapeHtml(text) {
    return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── TTS ───────────────────────────────────────────────────────────────────────
window.selectTextForTts = function(btn, evt) {
    try { if (evt) { evt.stopImmediatePropagation(); evt.stopPropagation(); evt.preventDefault(); } } catch(e){}
    const parent  = btn && btn.parentElement ? btn.parentElement.parentElement : null;
    const content = parent ? parent.querySelector('.attr-val,.studio-note,.bible-text,.t-content') : null;
    const target  = content || (btn && btn.parentElement ? btn.parentElement.nextElementSibling : null) || (btn && btn.parentElement ? btn.parentElement : null);
    if (target) {
        try {
            const range = document.createRange();
            range.selectNodeContents(target);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } catch(e) {}
    }
    return false;
};['pointerdown','mousedown','touchstart','click'].forEach(evt => {
    document.addEventListener(evt, e => {
        const btn = e.target && e.target.closest ? e.target.closest('.tts-select-icon') : null;
        if (!btn) return;
        try { e.stopImmediatePropagation(); e.stopPropagation(); if (e.cancelable) e.preventDefault(); } catch(ex){}
        try { selectTextForTts(btn, e); } catch(ex){}
    }, { capture: true, passive: false });
});

// ── DOWNLOADS ─────────────────────────────────────────────────────────────────
window.downloadCategoryJSON = function(docId, type, cat, event) {
    event.stopPropagation();
    const p = payloadStore[docId];
    if (!p) return;
    const data = p[type] && p[type][cat] ? p[type][cat] : null;
    if (!data) { alert('No data found'); return; }
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `kg_${docId}_${type}_${cat}.json`;
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
};

window.downloadCategoryMD = function(docId, type, cat, event) {
    event.stopPropagation();
    const p = payloadStore[docId];
    if (!p) return;
    const data = p[type] && p[type][cat] ? p[type][cat] : null;
    if (!data) { alert('No data found'); return; }

    const title = (p.meta && p.meta.name) ? p.meta.name : docId;
    let md = `# ${title}\n\n**Section:** ${type.toUpperCase()} / ${cat.replace(/_/g,' ')}\n\n---\n\n`;

    if (Array.isArray(data)) {
        data.forEach((item, i) => {
            md += `## ${i+1}. ${item.name || item.title || 'Item ' + (i+1)}\n\n`;
            if (item.node_type) md += `**Type:** ${item.node_type}\n`;
            if (item.category)  md += `**Category:** ${item.category}\n`;
            if (item.description) md += `\n${item.description}\n`;
            if (item.content && item.content.trim()) md += `\n${item.content}\n`;
            if (Array.isArray(item.keywords) && item.keywords.length) md += `\n**Keywords:** ${item.keywords.join(', ')}\n`;
            if (Array.isArray(item.relationships) && item.relationships.length) {
                md += `\n**Linked:**\n`;
                item.relationships.forEach(r => {
                    md += `- ${r.target || '—'}`;
                    if (r.nature) md += ` (${r.nature})`;
                    if (r.desc)   md += `: ${r.desc}`;
                    md += '\n';
                });
            }
            md += `\n---\n\n`;
        });
    }

    const blob = new Blob([md], { type: 'text/markdown;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `kg_${docId}_${type}_${cat}.md`;
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
};

// ── WIRE UP ───────────────────────────────────────────────────────────────────
document.getElementById('modalBackdrop').addEventListener('click', closeModal);
window.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
window.addEventListener('DOMContentLoaded', init);
</script>

<?php if (!$isEmbed) require_once __DIR__ . '/mod_floating_tts.php'; ?>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle,
    $spw->getProjectPath() . '/templates/gallery.php'
);