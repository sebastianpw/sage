<?php
// public/filter_forge_api.php
// ─────────────────────────────────────────────────────────────────────────────
// FILTER FORGE API — Unified, reusable filter + result API for SAGE AI
//
// All endpoints return the same JSON envelope:
// {
//   "status":  "success" | "error",
//   "action":  <string>,
//   "meta":    { total, page, per_page, pages, entity_type, filter_summary[] },
//   "data":    [ { frame_id, filename, entity_id, entity_name, entity_type,
//                  is_imported, is_enhanced, in_storyboard, in_narrative_seq,
//                  in_kg, map_run_id } ],
//   "message": <string|null>
// }
//
// Actions (via GET ?action=...):
//   list_frames          — filtered frame result set (primary consumer)
//   list_entities        — filtered entity list (no frames)
//   list_filter_options  — autocomplete / picker data for each filter type
//   check_membership     — check if frame/entity is in storyboard/seq/graph
//   resolve_relationships — resolve entity relationships via KG
//
// Filter parameters (all optional, combinable):
//   entity_type          — e.g. sketches, characters, locations …
//   fuzz_id              — fuzz_candidates.id
//   doc_id               — documentations.id
//   doc_entity_name      — specific entity name within doc
//   doc_entity_names[]   — multiple entity names (OR within doc, AND with other filters)
//   kg_node_id           — kg_nodes.id
//   seq_id               — narrative_sequences.id
//   storyboard_id        — storyboards.id
//   vector_text          — semantic search string (hits ChromaDB on tablet)
//   map_run_id           — direct map run filter
//   entity_id            — direct entity id filter
//   search               — full-text search (entity name or frame name)
//   sort                 — id | latest_frame | entity_id
//   page                 — 1-based page number (default 1)
//   per_page             — results per page (default 50, max 200)
//   include_membership   — 1 = add in_storyboard/in_narrative_seq/in_kg flags
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Powered-By: SAGE-FilterForge/1.0');

// ── SECURITY: only JSON output, no HTML on errors ────────────────────
set_exception_handler(function(\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'action'  => $_GET['action'] ?? 'unknown',
        'meta'    => null,
        'data'    => [],
        'message' => $e->getMessage(),
    ]);
    exit;
});

// ── ALLOWED ENTITIES ─────────────────────────────────────────────────
$allowedEntities = array_keys($entityIcons ?? []);

// ── REQUEST PARAMS ───────────────────────────────────────────────────
$action        = $_GET['action'] ?? 'list_frames';
$entityType    = $_GET['entity_type'] ?? 'sketches';
if (!in_array($entityType, $allowedEntities, true)) $entityType = 'sketches';

$fuzzId        = isset($_GET['fuzz_id'])       ? (int)$_GET['fuzz_id']       : null;
$docId         = isset($_GET['doc_id'])         ? (int)$_GET['doc_id']        : null;
$docEntityName = isset($_GET['doc_entity_name']) ? trim($_GET['doc_entity_name']) : null;
$docEntityNames= isset($_GET['doc_entity_names']) ? (array)$_GET['doc_entity_names'] : [];
$kgNodeId      = isset($_GET['kg_node_id'])     ? (int)$_GET['kg_node_id']    : null;
$seqId         = isset($_GET['seq_id'])         ? (int)$_GET['seq_id']        : null;
$storyboardId  = isset($_GET['storyboard_id'])  ? (int)$_GET['storyboard_id'] : null;
$vectorText    = isset($_GET['vector_text'])     ? trim($_GET['vector_text'])  : null;
$mapRunId      = isset($_GET['map_run_id'])      ? (int)$_GET['map_run_id']   : null;
$entityId      = isset($_GET['entity_id'])       ? (int)$_GET['entity_id']    : null;
$search        = isset($_GET['search'])          ? trim($_GET['search'])       : null;
$sort          = $_GET['sort']          ?? 'id';
$page          = max(1, (int)($_GET['page']     ?? 1));
$perPage       = min(200, max(1, (int)($_GET['per_page'] ?? 50)));
$inclMembership= !empty($_GET['include_membership']);
// filter mode: how to combine forge filters
// 'intersection' = AND across all active filters (default)
// 'union'        = OR across all active filters
$filterMode    = ($_GET['filter_mode'] ?? 'intersection') === 'union' ? 'union' : 'intersection';

// ── FILTER OPTIONS (picker / autocomplete) ───────────────────────────
if ($action === 'list_filter_options') {
    $mode = $_GET['mode'] ?? 'fuzz';
    $q    = trim($_GET['q'] ?? '');
    _listFilterOptions($pdo, $mode, $q, $entityType);
    exit;
}

// ── CHECK MEMBERSHIP ─────────────────────────────────────────────────
if ($action === 'check_membership') {
    $frameIds = isset($_GET['frame_ids']) ? array_map('intval', (array)$_GET['frame_ids']) : [];
    _checkMembership($pdo, $frameIds);
    exit;
}

// ── RESOLVE RELATIONSHIPS ─────────────────────────────────────────────
if ($action === 'resolve_relationships') {
    $nodeId = (int)($_GET['node_id'] ?? 0);
    $hops   = min(3, max(1, (int)($_GET['hops'] ?? 1)));
    _resolveRelationships($pdo, $nodeId, $hops, $entityType);
    exit;
}

// ── LIST ENTITIES (no frames, just entity rows) ───────────────────────
if ($action === 'list_entities') {
    _listEntities($pdo, $entityType, $fuzzId, $docId, $docEntityName, $docEntityNames,
                  $kgNodeId, $seqId, $storyboardId, $vectorText, $entityId, $search,
                  $sort, $page, $perPage, $filterMode);
    exit;
}

// ── LIST FRAMES (default) ─────────────────────────────────────────────
_listFrames($pdo, $entityType, $fuzzId, $docId, $docEntityName, $docEntityNames,
             $kgNodeId, $seqId, $storyboardId, $vectorText, $mapRunId, $entityId,
             $search, $sort, $page, $perPage, $inclMembership, $filterMode);
exit;


// ═════════════════════════════════════════════════════════════════════
// CORE: INTERSECTION ENGINE
// Accepts multiple optional filter sources; returns array of entity IDs
// (or null = no restriction from this set of filters).
// ═════════════════════════════════════════════════════════════════════
function computeForgeIntersection(
    PDO    $pdo,
    string $entityType,
    ?int   $fuzzId,
    ?int   $docId,
    ?string $docEntityName,
    array  $docEntityNames,
    ?int   $kgNodeId,
    ?int   $seqId,
    ?int   $storyboardId,
    ?string $vectorText,
    ?int   $entityId,
    string  $filterMode = 'intersection'
): ?array {

    // Collect all active per-source ID sets
    $sets = [];

    // ── 1. Direct entity_id ───────────────────────────────────────────
    if ($entityId) {
        $sets[] = [$entityId];
    }

    // ── 2. Fuzz candidate ─────────────────────────────────────────────
    if ($fuzzId) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT source_row_id FROM fuzz_mentions
             WHERE candidate_id = ? AND source_table = ? AND source_row_id IS NOT NULL"
        );
        $stmt->execute([$fuzzId, $entityType]);
        $sets[] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── 3. Lore Doc ───────────────────────────────────────────────────
    if ($docId) {
        if ($entityType === 'sketches') {
            if (!empty($docEntityNames)) {
                // Whole section: OR-match array of entity names
                $ph   = implode(',', array_fill(0, count($docEntityNames), '?'));
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT sketch_id FROM sketch_lore_history
                     WHERE doc_id = ? AND entity_name IN ($ph)"
                );
                $stmt->execute(array_merge([$docId], $docEntityNames));
                $sets[] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($docEntityName) {
                // Exact match first, then LIKE fallback
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT sketch_id FROM sketch_lore_history
                     WHERE doc_id = ? AND entity_name = ?"
                );
                $stmt->execute([$docId, $docEntityName]);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (empty($ids)) {
                    $stmt2 = $pdo->prepare(
                        "SELECT DISTINCT sketch_id FROM sketch_lore_history
                         WHERE doc_id = ? AND entity_name LIKE ?"
                    );
                    $stmt2->execute([$docId, '%' . $docEntityName . '%']);
                    $ids = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                }
                $sets[] = $ids;
            } else {
                // Whole document
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE doc_id = ?"
                );
                $stmt->execute([$docId]);
                $sets[] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        // For non-sketch entities: no sketch_lore_history link → no restriction
    }

    // ── 4. KG Node ────────────────────────────────────────────────────
    if ($kgNodeId) {
        $foundIds = [];
        $singularMap = [
            'sketches'   => 'sketch',   'characters' => 'character',
            'locations'  => 'location', 'artifacts'  => 'artifact',
            'animatics'  => 'animatic',
        ];
        $singularType = $singularMap[$entityType] ?? rtrim($entityType, 's');

        // Primary: sketch_lore_history.entity_name = kg_nodes.name
        if ($entityType === 'sketches') {
            $nodeNameStmt = $pdo->prepare("SELECT name FROM kg_nodes WHERE id = ?");
            $nodeNameStmt->execute([$kgNodeId]);
            $nodeName = $nodeNameStmt->fetchColumn();
            if ($nodeName) {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE entity_name = ?"
                );
                $stmt->execute([$nodeName]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        // Secondary: direct kg_node_items links
        $stmt2 = $pdo->prepare(
            "SELECT DISTINCT item_id FROM kg_node_items
             WHERE node_id = ? AND item_type = ? AND item_id IS NOT NULL"
        );
        $stmt2->execute([$kgNodeId, $singularType]);
        $direct = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($direct)) {
            $foundIds = array_unique(array_merge($foundIds, $direct));
        }

        // Tertiary: via fuzz_candidates linked to this kg_node
        $stmt3 = $pdo->prepare(
            "SELECT DISTINCT m.source_row_id FROM fuzz_mentions m
             JOIN fuzz_candidates c ON m.candidate_id = c.id
             WHERE c.kg_node_id = ? AND m.source_table = ? AND m.source_row_id IS NOT NULL"
        );
        $stmt3->execute([$kgNodeId, $entityType]);
        $viaFuzz = $stmt3->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($viaFuzz)) {
            $foundIds = array_unique(array_merge($foundIds, $viaFuzz));
        }

        $sets[] = $foundIds;
    }

    // ── 5. Narrative Sequence ─────────────────────────────────────────
    if ($seqId && $entityType === 'sketches') {
        $stmt = $pdo->prepare(
            "SELECT CASE
               WHEN JSON_TYPE(jt.val) = 'INTEGER'
                 THEN JSON_VALUE(jt.val, '$')
                 ELSE JSON_VALUE(jt.val, '$.sketch_id')
             END
             FROM narrative_sequences ns,
             JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
             WHERE ns.id = ?"
        );
        $stmt->execute([$seqId]);
        $sets[] = array_filter($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // ── 6. Storyboard ─────────────────────────────────────────────────
    if ($storyboardId) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT f.entity_id FROM frames f
             JOIN storyboard_frames sf ON f.id = sf.frame_id
             WHERE sf.storyboard_id = ? AND f.entity_type = ? AND f.entity_id IS NOT NULL"
        );
        $stmt->execute([$storyboardId, $entityType]);
        $sets[] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── 7. Vector / Semantic Text (ChromaDB on tablet) ────────────────
    if ($vectorText) {
        // PyApiVectorService may not exist in all environments — degrade gracefully
        $vectorIds = [];
        if (class_exists('\App\Core\PyApiVectorService')) {
            try {
                $vectorService = new \App\Core\PyApiVectorService();
                $collection    = ($entityType === 'sketches')
                    ? 'sage_sketches_nu'
                    : 'sage_lore_entities_draft';
                $chromaRes = $vectorService->query($vectorText, null, $collection, 'text', 30);
                if (!empty($chromaRes['result']['ids'][0])) {
                    foreach ($chromaRes['result']['ids'][0] as $rid) {
                        if (preg_match('/(?:sketch|entity)_(\d+)/', $rid, $m)) {
                            $vectorIds[] = (int)$m[1];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Tablet may be offline — no restriction
            }
        }
        $sets[] = $vectorIds;
    }
    
    
    // ── Strip non-searchable sketches from every source set ───────────
    if ($entityType === 'sketches' && !empty($sets)) {
        // Collect all unique candidate IDs across sets
        $allCandidates = array_unique(array_merge(...array_map(fn($s) => array_map('intval', $s), $sets)));
        if (!empty($allCandidates)) {
            $ph = implode(',', $allCandidates);
            $searchableIds = $pdo->query(
                "SELECT id FROM sketches WHERE id IN ($ph) AND searchable = 1"
            )->fetchAll(PDO::FETCH_COLUMN);
            $searchableSet = array_flip(array_map('intval', $searchableIds));
            $sets = array_map(
                fn($s) => array_values(array_filter(array_map('intval', $s), fn($id) => isset($searchableSet[$id]))),
                $sets
            );
        }
    }

    // ── COMBINE SETS ──────────────────────────────────────────────────
    if (empty($sets)) return null; // No active filters

    if (count($sets) === 1) {
        return array_values(array_map('intval', $sets[0]));
    }

    if ($filterMode === 'union') {
        $merged = array_merge(...$sets);
        return array_values(array_unique(array_map('intval', $merged)));
    }

    // Intersection (AND): successive intersections
    $result = array_map('intval', $sets[0]);
    for ($i = 1; $i < count($sets); $i++) {
        $result = array_values(array_intersect($result, array_map('intval', $sets[$i])));
        if (empty($result)) return []; // Short-circuit: nothing passes
    }
    return $result;
}


// ═════════════════════════════════════════════════════════════════════
// FILTER OPTIONS — for picker / autocomplete in UI
// ═════════════════════════════════════════════════════════════════════
function _listFilterOptions(PDO $pdo, string $mode, string $q, string $entityType): void {
    $items  = [];
    $params = [];

    switch ($mode) {

        case 'fuzz':
            $sql = "SELECT id, label, concept_type, status FROM fuzz_candidates WHERE status = 'promoted' ";
            if ($q) { $sql .= "AND label LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 80";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'    => (int)$r['id'],
                'label' => $r['label'],
                'meta'  => ($r['concept_type'] ?? '') . ' · ' . ($r['status'] ?? ''),
                'type'  => 'fuzz',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'doc':
            $sql = "SELECT d.id, d.name, dc.name as cat_name, da.narrative_utility
                    FROM documentations d
                    JOIN md_doc_analysis da ON d.id = da.doc_id
                    LEFT JOIN documentation_categories dc ON d.category_id = dc.id
                    WHERE d.is_active = 1 ";
            if ($q) { $sql .= "AND d.name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY d.updated_at DESC LIMIT 80";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'    => (int)$r['id'],
                'label' => $r['name'],
                'meta'  => ($r['cat_name'] ? $r['cat_name'] . ' · ' : '') .
                           'utility: ' . round((float)$r['narrative_utility'], 1),
                'type'  => 'doc',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'doc_entities':
            // Returns grouped entity sections for a specific doc
            $docId = (int)($_GET['doc_id'] ?? 0);
            if (!$docId) {
                _respond('list_filter_options', [], 0, 1, 1, $entityType, []);
                return;
            }
            require_once __DIR__ . '/../src/Service/LoreAccessService.php';
            $service = new \App\Service\LoreAccessService($pdo);
            try {
                $service->loadDoc($docId);
                $story = $service->getStoryEngine();
                $world = $service->getWorldData();
                $sections = [];

                foreach (['episodes' => 'Episodes', 'scene_hooks' => 'Scene Hooks'] as $key => $label) {
                    $list  = $story[$key] ?? [];
                    $names = [];
                    foreach ($list as $item) {
                        $name = is_array($item)
                            ? ($item['name'] ?? $item['title'] ?? $item['event'] ?? null)
                            : (string)$item;
                        if ($name) $names[] = $name;
                    }
                    if (!empty($names)) $sections[] = ['section' => $label, 'items' => $names];
                }
                foreach (['characters', 'locations', 'factions', 'artifacts'] as $cat) {
                    $list  = $world[$cat] ?? [];
                    $names = [];
                    foreach ($list as $ent) {
                        $name = $ent['name'] ?? null;
                        if ($name && $name !== 'Unknown') $names[] = $name;
                    }
                    if (!empty($names)) $sections[] = ['section' => ucfirst($cat), 'items' => $names];
                }
                _respond('list_filter_options', $sections, count($sections), 1, 1, $entityType, [
                    ['type' => 'doc', 'doc_id' => $docId],
                ]);
            } catch (\Throwable $e) {
                _error('list_filter_options', $e->getMessage());
            }
            return;

        case 'kg':
            // Only nodes with sketch links (same logic as enhanimaticism)
            $sql = "SELECT kn.id, kn.name, kn.node_type
                    FROM kg_nodes kn
                    WHERE kn.status = 'active'
                      AND EXISTS (
                        SELECT 1 FROM sketch_lore_history slh
                        WHERE slh.entity_name = kn.name LIMIT 1
                      ) ";
            if ($q) { $sql .= "AND kn.name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY kn.name ASC LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'    => (int)$r['id'],
                'label' => $r['name'],
                'meta'  => 'type: ' . ($r['node_type'] ?? ''),
                'type'  => 'kg',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'seq':
            $sql = "SELECT id, name, description FROM narrative_sequences ";
            if ($q) { $sql .= "WHERE name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY id DESC LIMIT 80";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'    => (int)$r['id'],
                'label' => $r['name'],
                'meta'  => mb_substr($r['description'] ?? '', 0, 60),
                'type'  => 'seq',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'storyboard':
            $sql = "SELECT id, name, description FROM storyboards WHERE is_archived = 0 ";
            if ($q) { $sql .= "AND name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 80";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'    => (int)$r['id'],
                'label' => $r['name'] ?: 'Untitled',
                'meta'  => mb_substr($r['description'] ?? '', 0, 60),
                'type'  => 'storyboard',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'map_run':
            $sql = "SELECT id, note, entity_type, created_at FROM map_runs
                    WHERE entity_type = ? ";
            $params = [$entityType];
            if ($q) { $sql .= "AND (note LIKE ? OR id = ?) "; $params[] = "%$q%"; $params[] = (int)$q; }
            $sql .= "ORDER BY id DESC LIMIT 80";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'    => (int)$r['id'],
                'label' => '#' . $r['id'] . ' — ' . ($r['note'] ?: 'No note'),
                'meta'  => substr($r['created_at'], 0, 10),
                'type'  => 'map_run',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'entity':
            $table = '`' . str_replace('`', '', $entityType) . '`';
            $where = '1=1';
            $params = [];
            if ($q) { $where = '(name LIKE ? OR id = ?)'; $params = ["%$q%", (int)$q]; }
            $stmt  = $pdo->prepare("SELECT id, name FROM $table WHERE $where ORDER BY id DESC LIMIT 80");
            $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'    => (int)$r['id'],
                'label' => $r['name'],
                'meta'  => $entityType . ' #' . $r['id'],
                'type'  => 'entity',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
    }

    _respond('list_filter_options', $items, count($items), 1, 1, $entityType, []);
}


// ═════════════════════════════════════════════════════════════════════
// LIST FRAMES — main result producer
// ═════════════════════════════════════════════════════════════════════
function _listFrames(
    PDO     $pdo,
    string  $entityType,
    ?int    $fuzzId,
    ?int    $docId,
    ?string $docEntityName,
    array   $docEntityNames,
    ?int    $kgNodeId,
    ?int    $seqId,
    ?int    $storyboardId,
    ?string $vectorText,
    ?int    $mapRunId,
    ?int    $entityId,
    ?string $search,
    string  $sort,
    int     $page,
    int     $perPage,
    bool    $inclMembership,
    string  $filterMode
): void {

    $entityTable = '`' . str_replace('`', '', $entityType) . '`';

    // ── Build forge-intersection entity ID constraint ─────────────────
    $intersectIds = computeForgeIntersection(
        $pdo, $entityType,
        $fuzzId, $docId, $docEntityName, $docEntityNames,
        $kgNodeId, $seqId, $storyboardId, $vectorText,
        $entityId, $filterMode
    );

    // ── Build WHERE clause ────────────────────────────────────────────
    $where  = "f.entity_type = " . $pdo->quote($entityType);
    $params = [];

    // Never return non-searchable sketches in any result set
    if ($entityType === 'sketches') {
        $where .= " AND e.searchable = 1";
    }

    if ($mapRunId) {
        $where .= " AND f.map_run_id = " . (int)$mapRunId;
    }

    if ($intersectIds !== null) {
        if (empty($intersectIds)) {
            // No entity passes all filters → empty result
            _respond('list_frames', [], 0, $page, 0, $entityType,
                     _buildFilterSummary($fuzzId, $docId, $docEntityName, $docEntityNames,
                                         $kgNodeId, $seqId, $storyboardId, $vectorText, $entityId));
            return;
        }
        $in     = implode(',', array_map('intval', $intersectIds));
        $where .= " AND f.entity_id IN ($in)";
    }

    if ($search) {
        $safeSrch = $pdo->quote('%' . $search . '%');
        $safeId   = (int)$search;
        $where   .= " AND (f.name LIKE $safeSrch OR e.name LIKE $safeSrch OR f.id = $safeId)";
    }

    // ── ORDER BY ──────────────────────────────────────────────────────
    $orderBy = match($sort) {
        'entity_id', 'latest_frame' => "f.entity_id DESC, f.id DESC",
        'map_run'                   => "f.map_run_id DESC, f.id ASC",
        default                     => "f.id DESC",
    };

    // ── COUNT ─────────────────────────────────────────────────────────
    $countSql = "SELECT COUNT(*) FROM frames f
                 LEFT JOIN $entityTable e ON e.id = f.entity_id
                 WHERE $where";
    $total    = (int)$pdo->query($countSql)->fetchColumn();
    $pages    = $total > 0 ? (int)ceil($total / $perPage) : 1;
    $offset   = ($page - 1) * $perPage;

    // ── FETCH ─────────────────────────────────────────────────────────
    $memberJoin = '';
    $memberCols = '';
    if ($inclMembership) {
        $memberCols = ",
            CASE WHEN EXISTS (
                SELECT 1 FROM storyboard_frames sf WHERE sf.frame_id = f.id LIMIT 1
            ) THEN 1 ELSE 0 END AS in_storyboard,
            CASE WHEN EXISTS (
                SELECT 1 FROM narrative_sequences ns
                WHERE JSON_SEARCH(ns.sequence_data, 'one', CAST(f.entity_id AS CHAR)) IS NOT NULL LIMIT 1
            ) THEN 1 ELSE 0 END AS in_narrative_seq,
            CASE WHEN EXISTS (
                SELECT 1 FROM kg_node_items kni
                WHERE kni.item_id = f.entity_id
                  AND kni.item_type = LEFT(f.entity_type, CHAR_LENGTH(f.entity_type)-1)
                LIMIT 1
            ) THEN 1 ELSE 0 END AS in_kg";
    }

    $dataSql = "SELECT
            f.id         AS frame_id,
            f.filename,
            f.name       AS frame_name,
            f.entity_id,
            f.entity_type,
            f.map_run_id,
            COALESCE(e.name, '') AS entity_name,
            CASE WHEN EXISTS (
                SELECT 1 FROM animatics a WHERE a.img2img_frame_id = f.id
            ) THEN 1 ELSE 0 END AS is_imported,
            CASE WHEN EXISTS (
                SELECT 1 FROM frame_enhancements fe WHERE fe.img2img_frame_id = f.id
            ) THEN 1 ELSE 0 END AS is_enhanced
            $memberCols
        FROM frames f
        LEFT JOIN $entityTable e ON e.id = f.entity_id
        WHERE $where
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";

    $rows = $pdo->query($dataSql)->fetchAll(PDO::FETCH_ASSOC);

    // ── Normalise types ───────────────────────────────────────────────
    $data = array_map(function($r) use ($inclMembership) {
        $out = [
            'frame_id'    => (int)$r['frame_id'],
            'filename'    => $r['filename'],
            'frame_name'  => $r['frame_name'],
            'entity_id'   => $r['entity_id'] !== null ? (int)$r['entity_id'] : null,
            'entity_name' => $r['entity_name'],
            'entity_type' => $r['entity_type'],
            'map_run_id'  => $r['map_run_id'] !== null ? (int)$r['map_run_id'] : null,
            'is_imported' => (int)$r['is_imported'],
            'is_enhanced' => (int)$r['is_enhanced'],
        ];
        if ($inclMembership) {
            $out['in_storyboard']   = (int)($r['in_storyboard']   ?? 0);
            $out['in_narrative_seq']= (int)($r['in_narrative_seq'] ?? 0);
            $out['in_kg']           = (int)($r['in_kg']           ?? 0);
        }
        return $out;
    }, $rows);

    _respond('list_frames', $data, $total, $page, $pages, $entityType,
             _buildFilterSummary($fuzzId, $docId, $docEntityName, $docEntityNames,
                                  $kgNodeId, $seqId, $storyboardId, $vectorText, $entityId));
}


// ═════════════════════════════════════════════════════════════════════
// LIST ENTITIES — returns entity rows (not frames)
// ═════════════════════════════════════════════════════════════════════
function _listEntities(
    PDO     $pdo,
    string  $entityType,
    ?int    $fuzzId,
    ?int    $docId,
    ?string $docEntityName,
    array   $docEntityNames,
    ?int    $kgNodeId,
    ?int    $seqId,
    ?int    $storyboardId,
    ?string $vectorText,
    ?int    $entityId,
    ?string $search,
    string  $sort,
    int     $page,
    int     $perPage,
    string  $filterMode
): void {

    $table    = '`' . str_replace('`', '', $entityType) . '`';
    $mapTable = '`frames_2_' . str_replace('`', '', $entityType) . '`';

    $intersectIds = computeForgeIntersection(
        $pdo, $entityType,
        $fuzzId, $docId, $docEntityName, $docEntityNames,
        $kgNodeId, $seqId, $storyboardId, $vectorText,
        $entityId, $filterMode
    );

    $where  = "1=1";
    if ($search) {
        $safe   = $pdo->quote('%' . $search . '%');
        $safeId = (int)$search;
        $where .= " AND (name LIKE $safe OR id = $safeId)";
    }

    if ($intersectIds !== null) {
        if (empty($intersectIds)) {
            _respond('list_entities', [], 0, $page, 0, $entityType,
                     _buildFilterSummary($fuzzId, $docId, $docEntityName, $docEntityNames,
                                         $kgNodeId, $seqId, $storyboardId, $vectorText, $entityId));
            return;
        }
        $in     = implode(',', array_map('intval', $intersectIds));
        $where .= " AND $table.id IN ($in)";
    }

    $orderBy = $sort === 'latest_frame'
        ? "(SELECT MAX(from_id) FROM $mapTable WHERE to_id = $table.id) DESC, $table.id DESC"
        : "$table.id DESC";

    $total  = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
    $pages  = $total > 0 ? (int)ceil($total / $perPage) : 1;
    $offset = ($page - 1) * $perPage;

    $sql  = "SELECT *, (SELECT COUNT(from_id) FROM $mapTable WHERE to_id = $table.id) AS frame_count
             FROM $table WHERE $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Normalise: cast id and frame_count
    $data = array_map(function($r) use ($entityType) {
        $r['id']          = (int)$r['id'];
        $r['frame_count'] = (int)($r['frame_count'] ?? 0);
        $r['entity_type'] = $entityType;
        return $r;
    }, $rows);

    _respond('list_entities', $data, $total, $page, $pages, $entityType,
             _buildFilterSummary($fuzzId, $docId, $docEntityName, $docEntityNames,
                                  $kgNodeId, $seqId, $storyboardId, $vectorText, $entityId));
}


// ═════════════════════════════════════════════════════════════════════
// CHECK MEMBERSHIP — is a frame in storyboard / narrative seq / KG?
// ═════════════════════════════════════════════════════════════════════
function _checkMembership(PDO $pdo, array $frameIds): void {
    if (empty($frameIds)) {
        _respond('check_membership', [], 0, 1, 1, '', []);
        return;
    }

    $in   = implode(',', $frameIds);
    $rows = $pdo->query(
        "SELECT
            f.id AS frame_id,
            f.entity_id,
            f.entity_type,
            CASE WHEN EXISTS (
                SELECT 1 FROM storyboard_frames sf WHERE sf.frame_id = f.id LIMIT 1
            ) THEN 1 ELSE 0 END AS in_storyboard,
            CASE WHEN EXISTS (
                SELECT 1 FROM narrative_sequences ns
                WHERE JSON_SEARCH(ns.sequence_data, 'one', CAST(f.entity_id AS CHAR)) IS NOT NULL
                LIMIT 1
            ) THEN 1 ELSE 0 END AS in_narrative_seq,
            CASE WHEN EXISTS (
                SELECT 1 FROM kg_node_items kni
                WHERE kni.item_id = f.entity_id
                  AND kni.item_type = LEFT(f.entity_type, CHAR_LENGTH(f.entity_type)-1)
                LIMIT 1
            ) THEN 1 ELSE 0 END AS in_kg
        FROM frames f
        WHERE f.id IN ($in)"
    )->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(fn($r) => [
        'frame_id'       => (int)$r['frame_id'],
        'entity_id'      => (int)$r['entity_id'],
        'entity_type'    => $r['entity_type'],
        'in_storyboard'  => (int)$r['in_storyboard'],
        'in_narrative_seq' => (int)$r['in_narrative_seq'],
        'in_kg'          => (int)$r['in_kg'],
    ], $rows);

    _respond('check_membership', $data, count($data), 1, 1, '', []);
}


// ═════════════════════════════════════════════════════════════════════
// RESOLVE RELATIONSHIPS — walk KG from a node with BFS hops
// ═════════════════════════════════════════════════════════════════════
function _resolveRelationships(PDO $pdo, int $nodeId, int $hops, string $entityType): void {
    if (!$nodeId) {
        _error('resolve_relationships', 'node_id required');
        return;
    }

    // BFS over kg_node_items.relationship links
    $visited = [];
    $queue   = [$nodeId];
    $edges   = [];

    for ($h = 0; $h < $hops && !empty($queue); $h++) {
        $in       = implode(',', array_map('intval', $queue));
        $nextQueue = [];

        $rows = $pdo->query(
            "SELECT kni.node_id, kni.item_id, kni.item_type, kni.relationship, kni.item_label,
                    kn.name AS node_name, kn.node_type
             FROM kg_node_items kni
             JOIN kg_nodes kn ON kn.id = kni.node_id
             WHERE kni.node_id IN ($in)
               AND kni.item_type = 'kg_node'
               AND kni.item_id IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $edges[] = [
                'from_node_id'   => (int)$r['node_id'],
                'from_node_name' => $r['node_name'],
                'to_node_id'     => (int)$r['item_id'],
                'to_node_name'   => $r['item_label'],
                'relationship'   => $r['relationship'],
            ];
            $targetId = (int)$r['item_id'];
            if (!in_array($targetId, $visited, true)) {
                $visited[]   = $targetId;
                $nextQueue[] = $targetId;
            }
        }
        $queue = $nextQueue;
        array_push($visited, ...(array_map('intval', $queue)));
    }

    // Also return entity IDs linked to all visited nodes (for frame lookup)
    $allNodes = array_unique(array_merge([$nodeId], $visited));
    $in2      = implode(',', $allNodes);
    $singularMap = [
        'sketches'  => 'sketch',  'characters' => 'character',
        'locations' => 'location','artifacts'  => 'artifact',
    ];
    $singular = $singularMap[$entityType] ?? rtrim($entityType, 's');

    $linkedEntities = $pdo->query(
        "SELECT DISTINCT item_id FROM kg_node_items
         WHERE node_id IN ($in2) AND item_type = '$singular' AND item_id IS NOT NULL"
    )->fetchAll(PDO::FETCH_COLUMN);

    $data = [
        'root_node_id'    => $nodeId,
        'hops'            => $hops,
        'edges'           => $edges,
        'linked_node_ids' => $allNodes,
        'linked_entity_ids' => array_map('intval', $linkedEntities),
    ];

    _respond('resolve_relationships', [$data], 1, 1, 1, $entityType, []);
}


// ═════════════════════════════════════════════════════════════════════
// HELPERS
// ═════════════════════════════════════════════════════════════════════

function _buildFilterSummary(
    ?int    $fuzzId,
    ?int    $docId,
    ?string $docEntityName,
    array   $docEntityNames,
    ?int    $kgNodeId,
    ?int    $seqId,
    ?int    $storyboardId,
    ?string $vectorText,
    ?int    $entityId
): array {
    $summary = [];
    if ($fuzzId)       $summary[] = ['type' => 'fuzz',       'id' => $fuzzId];
    if ($docId)        $summary[] = ['type' => 'doc',        'id' => $docId,
                                      'entity_name' => $docEntityName,
                                      'entity_names' => $docEntityNames];
    if ($kgNodeId)     $summary[] = ['type' => 'kg',         'id' => $kgNodeId];
    if ($seqId)        $summary[] = ['type' => 'seq',        'id' => $seqId];
    if ($storyboardId) $summary[] = ['type' => 'storyboard', 'id' => $storyboardId];
    if ($vectorText)   $summary[] = ['type' => 'vector',     'text' => $vectorText];
    if ($entityId)     $summary[] = ['type' => 'entity_id',  'id' => $entityId];
    return $summary;
}

function _respond(
    string $action,
    array  $data,
    int    $total,
    int    $page,
    int    $pages,
    string $entityType,
    array  $filterSummary
): void {
    echo json_encode([
        'status'  => 'success',
        'action'  => $action,
        'meta'    => [
            'total'          => $total,
            'page'           => $page,
            'pages'          => $pages,
            'per_page'       => (int)($_GET['per_page'] ?? 50),
            'entity_type'    => $entityType,
            'filter_summary' => $filterSummary,
            'filter_mode'    => ($_GET['filter_mode'] ?? 'intersection'),
        ],
        'data'    => $data,
        'message' => null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function _error(string $action, string $message): void {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'action'  => $action,
        'meta'    => null,
        'data'    => [],
        'message' => $message,
    ]);
}
