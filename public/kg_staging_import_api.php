<?php
// public/kg_staging_import_api.php  (v2 — AG Hops Edition)
// -----------------------------------------------------------------------
// Actions:
//
//   GET  get_ag_categories    — list AG categories for a doc (with node counts)
//   GET  get_ag_nodes         — list AG nodes for a doc + optional category
//   GET  get_ag_node_preview  — node content + connections for Peek modal
//
//   POST dryrun_estimate      — BFS count for a list of focal nodes + hops (no DB write)
//   POST promote_ag_node      — BFS collect subgraph, write to kg_staging_nodes +
//                               kg_staging_node_items, optionally AI-author focal node MD
//
// HOPS semantics:
//   0 → focal node only (node + back-link, no neighbours)
//   1 → focal + direct neighbours (edges whose source or target = focal node id)
//   2 → focal + 2-level BFS expansion
//   Max 2 enforced by API; higher values clipped.
//
// DB writes (promote_ag_node):
//   kg_staging_nodes      — one row per unique node in the subgraph
//   kg_staging_node_items — edges within the subgraph stored as
//                           item_type='kg_node', item_id=target_staging_id
//                           + one back-link per node: item_type='ag_node', item_id=ag_node_id
//
// IDEMPOTENCY:
//   Before inserting a focal node we check for an existing kg_staging_nodes row
//   with the same name (case-insensitive) to avoid duplicate promotion.
//   Neighbour nodes are also dedup-checked to prevent double entries when two
//   staged items share neighbours.
// -----------------------------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// -----------------------------------------------------------------------
// AI SERVICES (optional — used only for focal-node MD authoring)
// -----------------------------------------------------------------------
$generatorService = null;
$enricherConfig   = null;
try {
    global $spw;
    $em             = $spw->getEntityManager();
    $repo           = $em->getRepository(GeneratorConfig::class);
    $enricherConfig = $repo->findOneBy(['configId' => 'md_filter_entity_enricher_v1']);
    if ($enricherConfig) {
        $aiProvider       = $spw->getAIProvider();
        $generatorService = new GeneratorService(
            $aiProvider,
            new SchemaValidator(),
            new ResponseNormalizer(),
            $spw->getFileLogger()
        );
    }
} catch (Exception $e) {
    error_log('[kg_import_api v2] AI service init: ' . $e->getMessage());
}

// -----------------------------------------------------------------------
// HELPERS
// -----------------------------------------------------------------------

/**
 * BFS over ag_nodes / ag_node_items.
 * Returns array of ag_node ids within $hops of $startId (inclusive).
 * $hops = 0 → only [$startId].
 */
function agBfs(\PDO $pdo, int $startId, int $docId, int $hops): array
{
    $hops    = max(0, min(2, $hops)); // hard cap
    $visited = [$startId => true];
    if ($hops === 0) return array_keys($visited);

    $frontier = [$startId];

    for ($h = 0; $h < $hops; $h++) {
        if (empty($frontier)) break;
        $ph = implode(',', array_fill(0, count($frontier), '?'));

        // Outgoing: focal node has items pointing to neighbours
        $stmt = $pdo->prepare(
            "SELECT DISTINCT an.id
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

        // Incoming: other nodes point to focal
        $stmt = $pdo->prepare(
            "SELECT DISTINCT ani.node_id
             FROM ag_node_items ani
             JOIN ag_nodes src ON src.id IN ($ph)
               AND src.doc_id = ani.doc_id
               AND (
                 (ani.item_id IS NOT NULL AND ani.item_id = src.id)
                 OR (ani.item_label IS NOT NULL AND ani.item_label = src.name)
               )
             WHERE ani.doc_id = ?"
        );
        $stmt->execute(array_merge($frontier, [$docId]));
        $in = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $newFrontier = [];
        foreach (array_merge($out, $in) as $nid) {
            $nid = (int)$nid;
            if ($nid > 0 && !isset($visited[$nid])) {
                $visited[$nid]  = true;
                $newFrontier[]  = $nid;
            }
        }
        $frontier = $newFrontier;
    }

    return array_keys($visited);
}

/**
 * Load full AG node rows for a list of IDs.
 * Returns assoc array keyed by id.
 */
function loadAgNodes(\PDO $pdo, array $ids, int $docId): array
{
    if (empty($ids)) return [];
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, name, node_type, content, description, keywords, category_id
         FROM ag_nodes
         WHERE doc_id = ? AND id IN ($ph) AND status = 'active'"
    );
    $stmt->execute(array_merge([$docId], $ids));
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $map  = [];
    foreach ($rows as $r) $map[(int)$r['id']] = $r;
    return $map;
}

/**
 * Load AG edges between a set of node IDs (within the same doc).
 * Returns array of [source_ag_id, target_ag_id, relationship, item_label].
 */
function loadAgEdges(\PDO $pdo, array $ids, int $docId): array
{
    if (empty($ids)) return [];
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT ani.node_id AS source, an.id AS target,
                ani.relationship, ani.item_label
         FROM ag_node_items ani
         JOIN ag_nodes an ON an.doc_id = ani.doc_id
           AND (
             (ani.item_id IS NOT NULL AND an.id = ani.item_id)
             OR (ani.item_label IS NOT NULL AND an.name = ani.item_label)
           )
         WHERE ani.doc_id = ?
           AND ani.node_id IN ($ph)
           AND an.id IN ($ph)
           AND an.status = 'active'"
    );
    $stmt->execute(array_merge([$docId], $ids, $ids));
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Check how many of the given names already exist in kg_staging_nodes (case-insensitive).
 * Returns count.
 */
function countAlreadyInStaging(\PDO $pdo, array $names): int
{
    if (empty($names)) return 0;
    $ph   = implode(',', array_fill(0, count($names), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM kg_staging_nodes WHERE LOWER(name) IN ($ph) AND status = 'active'"
    );
    $lowerNames = array_map('strtolower', $names);
    $stmt->execute($lowerNames);
    return (int)$stmt->fetchColumn();
}

/**
 * AI-author markdown for the focal node.
 * Falls back to structured plain text if AI unavailable.
 */
function authorMd(
    array $node,
    ?GeneratorService $gs,
    $cfg
): string {
    $name = $node['name'] ?? 'Unknown';
    $fallback  = "# {$name}\n\n";
    if (!empty($node['description'])) $fallback .= "> {$node['description']}\n\n";
    $fallback .= "---\n\n";
    if (!empty($node['content']))     $fallback .= $node['content'] . "\n";
    if (!empty($node['keywords']))    $fallback .= "\n**Keywords:** " . $node['keywords'] . "\n";

    if (!$gs || !$cfg) return $fallback;

    $prompt  = "=== AG NODE TO DOCUMENT ===\n";
    $prompt .= "Name: {$name}\n";
    $prompt .= "Type: " . ($node['node_type'] ?? 'note') . "\n";
    if (!empty($node['description'])) $prompt .= "Description: " . $node['description'] . "\n";
    if (!empty($node['content']))     $prompt .= "\n" . $node['content'];
    if (!empty($node['keywords']))    $prompt .= "\nKeywords: " . $node['keywords'];

    try {
        $res    = $gs->generate($cfg, ['entity_name' => $prompt]);
        $rawOut = is_object($res) && method_exists($res, 'getRawResponse')
            ? $res->getRawResponse()
            : (string)$res;

        // Try JSON envelope first
        $fb  = strpos($rawOut, '{');
        $lb  = strrpos($rawOut, '}');
        if ($fb !== false && $lb !== false && $lb > $fb) {
            $decoded = json_decode(substr($rawOut, $fb, $lb - $fb + 1), true);
            if (is_array($decoded)) {
                $keys = ['enriched_entity', 'content', 'markdown', 'md', 'narrative', 'enriched_query'];
                foreach ($keys as $k) {
                    if (!empty($decoded[$k])) {
                        $v = $decoded[$k];
                        if (is_array($v)) {
                            foreach (['content', 'markdown', 'md', 'narrative'] as $inner) {
                                if (!empty($v[$inner]) && is_string($v[$inner])) {
                                    return trim($v[$inner]);
                                }
                            }
                        } elseif (is_string($v)) {
                            return trim($v);
                        }
                    }
                }
            }
        }

        // Raw markdown fallback
        $stripped = preg_replace('/^```(?:markdown|md)?\s*/i', '', trim($rawOut));
        $stripped = preg_replace('/\s*```$/', '', $stripped);
        $stripped = trim($stripped);
        if (strlen($stripped) > 80 && str_contains($stripped, "\n")) {
            return $stripped;
        }
    } catch (Exception $e) {
        error_log('[kg_import_api v2] AI author error for "' . $name . '": ' . $e->getMessage());
    }

    return $fallback;
}

// -----------------------------------------------------------------------
// ROUTER
// -----------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ============================
// GET: get_ag_categories
// ============================
if ($method === 'GET' && $action === 'get_ag_categories') {
    $docId = (int)($_GET['doc_id'] ?? 0);
    if (!$docId) { echo json_encode(['status' => 'error', 'message' => 'Missing doc_id']); exit; }

    $stmt = $pdo->prepare("
        SELECT c.id, c.name,
               COUNT(n.id) AS node_count
        FROM ag_categories c
        LEFT JOIN ag_nodes n ON n.category_id = c.id AND n.doc_id = ? AND n.status = 'active'
        WHERE c.doc_id = ?
        GROUP BY c.id, c.name
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $stmt->execute([$docId, $docId]);
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $cats]);
    exit;
}

// ============================
// GET: get_ag_nodes
// ============================
if ($method === 'GET' && $action === 'get_ag_nodes') {
    $docId = (int)($_GET['doc_id'] ?? 0);
    $catId = isset($_GET['cat_id']) && $_GET['cat_id'] !== '' ? (int)$_GET['cat_id'] : null;

    if (!$docId) { echo json_encode(['status' => 'error', 'message' => 'Missing doc_id']); exit; }

    $params = [$docId];
    $catWhere = '';
    if ($catId) {
        $catWhere = ' AND n.category_id = ?';
        $params[] = $catId;
    }

    $stmt = $pdo->prepare("
        SELECT n.id, n.name, n.node_type, n.description,
               (SELECT COUNT(*) FROM ag_node_items ani WHERE ani.node_id = n.id) AS edge_count
        FROM ag_nodes n
        WHERE n.doc_id = ? AND n.status = 'active' {$catWhere}
        ORDER BY n.name ASC
    ");
    $stmt->execute($params);
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($nodes as &$n) {
        $n['id']         = (int)$n['id'];
        $n['edge_count'] = (int)$n['edge_count'];
    }

    echo json_encode(['status' => 'success', 'data' => $nodes]);
    exit;
}

// ============================
// GET: get_ag_node_preview
// ============================
if ($method === 'GET' && $action === 'get_ag_node_preview') {
    $agNodeId = (int)($_GET['ag_node_id'] ?? 0);
    $docId    = (int)($_GET['doc_id']     ?? 0);

    if (!$agNodeId) { echo json_encode(['status' => 'error', 'message' => 'Missing ag_node_id']); exit; }

    $stmt = $pdo->prepare(
        "SELECT id, name, node_type, content, description, keywords
         FROM ag_nodes WHERE id = ? AND status = 'active'"
    );
    $stmt->execute([$agNodeId]);
    $node = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$node) {
        echo json_encode(['status' => 'error', 'message' => 'Node not found']);
        exit;
    }

    // Load direct connections (outgoing)
    $stmt = $pdo->prepare(
        "SELECT ani.item_label AS label, ani.relationship
         FROM ag_node_items ani
         WHERE ani.node_id = ?
         ORDER BY ani.sort_order ASC, ani.id ASC
         LIMIT 30"
    );
    $stmt->execute([$agNodeId]);
    $node['connections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $node]);
    exit;
}

// ============================
// POST: dryrun_estimate
// ============================
if ($method === 'POST' && $action === 'dryrun_estimate') {
    $rawItems = $_POST['items'] ?? '[]';
    $items    = json_decode($rawItems, true) ?: [];

    if (empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'No items provided']);
        exit;
    }

    $allIds     = [];   // all unique ag_node ids across all BFS expansions
    $allNames   = [];
    $allEdges   = 0;
    $focalCount = count($items);

    foreach ($items as $item) {
        $agNodeId = (int)($item['ag_node_id'] ?? 0);
        $agDocId  = (int)($item['ag_doc_id']  ?? 0);
        $hops     = max(0, min(2, (int)($item['hops'] ?? 1)));
        if (!$agNodeId || !$agDocId) continue;

        $ids = agBfs($pdo, $agNodeId, $agDocId, $hops);
        foreach ($ids as $id) $allIds[$id] = $agDocId;

        $edges   = loadAgEdges($pdo, $ids, $agDocId);
        $allEdges += count($edges);
    }

    // Load names for all unique IDs so we can count staging duplicates
    $uniqueIds = array_keys($allIds);
    if (!empty($uniqueIds)) {
        $ph   = implode(',', array_fill(0, count($uniqueIds), '?'));
        $stmt = $pdo->prepare("SELECT name FROM ag_nodes WHERE id IN ($ph) AND status = 'active'");
        $stmt->execute($uniqueIds);
        $allNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $alreadyIn = countAlreadyInStaging($pdo, $allNames);

    echo json_encode([
        'status'          => 'success',
        'focal_count'     => $focalCount,
        'total_nodes'     => count($uniqueIds),
        'total_edges'     => $allEdges,
        'already_in_staging' => $alreadyIn,
    ]);
    exit;
}

// ============================
// POST: promote_ag_node
// ============================
if ($method === 'POST' && $action === 'promote_ag_node') {
    $agNodeId  = (int)($_POST['ag_node_id']  ?? 0);
    $agDocId   = (int)($_POST['ag_doc_id']   ?? 0);
    $hops      = max(0, min(2, (int)($_POST['hops'] ?? 1)));
    $nodeName  = trim($_POST['node_name']    ?? '');
    $nodeType  = trim($_POST['node_type']    ?? 'note');
    $catId     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if (!$agNodeId || !$agDocId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing ag_node_id or ag_doc_id']);
        exit;
    }

    $validTypes = ['note','relationship','character','location','event','concept','arc','episode'];
    if (!in_array($nodeType, $validTypes, true)) $nodeType = 'note';

    // ── 1. BFS collect subgraph ──────────────────────────────────────────
    $ids       = agBfs($pdo, $agNodeId, $agDocId, $hops);
    $agNodeMap = loadAgNodes($pdo, $ids, $agDocId);
    $agEdges   = loadAgEdges($pdo, $ids, $agDocId);

    if (!isset($agNodeMap[$agNodeId])) {
        echo json_encode(['status' => 'error', 'message' => 'Focal AG node not found or inactive']);
        exit;
    }

    // ── 2. Insert/find kg_staging_nodes rows ─────────────────────────────
    // Map: ag_node_id → staging_node_id
    $stagingIdMap  = [];
    $nodesCreated  = 0;
    $nodesExisting = 0;

    $pdo->beginTransaction();
    try {
        foreach ($agNodeMap as $agId => $agNode) {
            $isFocal     = ($agId === $agNodeId);
            $nameToUse   = $isFocal && $nodeName ? $nodeName : $agNode['name'];

            // Check for existing staging node (idempotency)
            $checkStmt = $pdo->prepare(
                "SELECT id FROM kg_staging_nodes
                 WHERE LOWER(name) = LOWER(?) AND status = 'active'
                 LIMIT 1"
            );
            $checkStmt->execute([$nameToUse]);
            $existingId = $checkStmt->fetchColumn();

            if ($existingId) {
                $stagingIdMap[$agId] = (int)$existingId;
                $nodesExisting++;
            } else {
                // AI-author MD only for focal node (keep neighbour imports lightweight)
                $content = '';
                if ($isFocal) {
                    $content = authorMd($agNode, $generatorService, $enricherConfig);
                } else {
                    // Lightweight content for neighbours
                    $content = "# " . $agNode['name'] . "\n\n";
                    if (!empty($agNode['description'])) {
                        $content .= "> " . $agNode['description'] . "\n\n";
                    }
                    if (!empty($agNode['content'])) {
                        $content .= $agNode['content'] . "\n";
                    }
                }

                $typeToUse = $isFocal ? $nodeType : ($agNode['node_type'] ?? 'note');
                if (!in_array($typeToUse, $validTypes, true)) $typeToUse = 'note';

                $insertStmt = $pdo->prepare("
                    INSERT INTO kg_staging_nodes
                        (name, node_type, content, description, keywords, category_id, status, sort_order)
                    VALUES
                        (:name, :node_type, :content, :description, :keywords, :category_id, 'active', 0)
                ");
                $insertStmt->execute([
                    ':name'        => $nameToUse,
                    ':node_type'   => $typeToUse,
                    ':content'     => $content,
                    ':description' => mb_substr($agNode['description'] ?? '', 0, 250),
                    ':keywords'    => $agNode['keywords'] ?? '',
                    ':category_id' => ($isFocal ? $catId : null),
                ]);
                $stagingId           = (int)$pdo->lastInsertId();
                $stagingIdMap[$agId] = $stagingId;
                $nodesCreated++;

                // Back-link to ag_node
                $blStmt = $pdo->prepare("
                    INSERT INTO kg_staging_node_items
                        (node_id, item_type, item_id, item_label, relationship, note, sort_order)
                    VALUES
                        (:node_id, 'ag_node', :item_id, :item_label, 'ag_source', :note, 0)
                ");
                $blStmt->execute([
                    ':node_id'    => $stagingId,
                    ':item_id'    => $agId,
                    ':item_label' => $agNode['name'],
                    ':note'       => json_encode([
                        'ag_doc_id'   => $agDocId,
                        'ag_node_id'  => $agId,
                        'hops_origin' => $hops,
                        'is_focal'    => $isFocal,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        // ── 3. Insert edges within subgraph ──────────────────────────────
        $edgesCreated = 0;
        // Build name→ag_id map for label-based resolution
        $nameToAgId = [];
        foreach ($agNodeMap as $agId => $agNode) {
            $nameToAgId[strtolower($agNode['name'])] = $agId;
        }

        foreach ($agEdges as $edge) {
            $srcAgId    = (int)($edge['source'] ?? 0);
            $tgtAgId    = (int)($edge['target'] ?? 0);
            $rel        = $edge['relationship'] ?? '';
            $itemLabel  = $edge['item_label']   ?? '';

            // Resolve target ag_id (may have been resolved by label join)
            if (!$tgtAgId && $itemLabel) {
                $key = strtolower($itemLabel);
                $tgtAgId = $nameToAgId[$key] ?? 0;
            }

            if (!$srcAgId || !$tgtAgId) continue;
            if (!isset($stagingIdMap[$srcAgId]) || !isset($stagingIdMap[$tgtAgId])) continue;

            $srcStagingId = $stagingIdMap[$srcAgId];
            $tgtStagingId = $stagingIdMap[$tgtAgId];

            // Avoid self-loops
            if ($srcStagingId === $tgtStagingId) continue;

            // Check for duplicate edge in this promotion batch
            $dupCheck = $pdo->prepare("
                SELECT COUNT(*) FROM kg_staging_node_items
                WHERE node_id = ? AND item_type = 'kg_node'
                  AND item_id = ? AND relationship = ?
            ");
            $dupCheck->execute([$srcStagingId, $tgtStagingId, $rel]);
            if ((int)$dupCheck->fetchColumn() > 0) continue;

            $edgeStmt = $pdo->prepare("
                INSERT INTO kg_staging_node_items
                    (node_id, item_type, item_id, item_label, relationship, note, sort_order)
                VALUES
                    (:node_id, 'kg_node', :item_id, :item_label, :relationship, :note, 0)
            ");
            $edgeStmt->execute([
                ':node_id'      => $srcStagingId,
                ':item_id'      => $tgtStagingId,
                ':item_label'   => $itemLabel ?: ($agNodeMap[$tgtAgId]['name'] ?? ''),
                ':relationship' => $rel,
                ':note'         => json_encode([
                    'src_ag_id' => $srcAgId,
                    'tgt_ag_id' => $tgtAgId,
                ]),
            ]);
            $edgesCreated++;
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'status'          => 'success',
        'focal_node_id'   => $stagingIdMap[$agNodeId] ?? null,
        'nodes_created'   => $nodesCreated,
        'nodes_existing'  => $nodesExisting,
        'edges_created'   => $edgesCreated,
        'total_ag_nodes'  => count($ids),
        'hops'            => $hops,
        'ai_used'         => ($generatorService && $enricherConfig),
        'message'         => "Promoted {$nodesCreated} new node(s) + {$edgesCreated} edge(s) from {$hops}-hop subgraph",
    ]);
    exit;
}

// -----------------------------------------------------------------------
// Unknown action
// -----------------------------------------------------------------------
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
exit;