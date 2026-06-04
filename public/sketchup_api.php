<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/../src/SketchUp/SketchUp.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

ini_set('display_errors', '0');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    // ── Search Sketches ──
    if ($action === 'search_sketches') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        $limit = 20;
        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, name FROM sketches WHERE id = ? OR name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM sketches WHERE name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Search Narrative Sequences ──
    if ($action === 'search_narrative_sequences') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        $limit = 20;
        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE id = ? OR name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Resolve Narrative Sequence → Sketch IDs ──
    // Looks at sequence_data JSON for sketch references and frames_2_sketches for any
    // frames that are linked to this sequence's sketches.
    if ($action === 'resolve_sequence_sketches') {
        header('Content-Type: application/json; charset=utf-8');
        $seqId = (int)($input['sequence_id'] ?? 0);
        if ($seqId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'sequence_id required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, sequence_data FROM narrative_sequences WHERE id = ?");
        $stmt->execute([$seqId]);
        $seq = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seq) {
            echo json_encode(['ok' => false, 'error' => 'Sequence not found']);
            exit;
        }

        $sketchIdMap = [];

        // 1. Parse sequence_data JSON for sketch references
        // sequence_data is a JSON array of items; each item may have entity_type='sketches' and entity_id,
        // or a nested 'sketches' array, or a direct sketch_id field — we scan all numeric values
        // that appear under keys hinting at sketch identity.
        if (!empty($seq['sequence_data'])) {
            $seqData = json_decode($seq['sequence_data'], true);
            if (is_array($seqData)) {
                _extractSketchIdsFromSequenceData($seqData, $sketchIdMap);
            }
        }

        // 2. Also resolve via frames_2_sketches: any frame whose entity_type='sketches' linked
        //    to this sequence (frames may carry entity_type/entity_id pointing at sketches).
        //    We look for frames that have entity_type='narrative_sequences' AND entity_id = $seqId,
        //    then follow frames_2_sketches from those frame IDs.
        $stmtF = $pdo->prepare(
            "SELECT f2s.to_id AS sketch_id
             FROM frames f
             JOIN frames_2_sketches f2s ON f2s.from_id = f.id
             WHERE f.entity_type = 'narrative_sequences' AND f.entity_id = ?"
        );
        $stmtF->execute([$seqId]);
        foreach ($stmtF->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            $sketchIdMap[(int)$sid] = true;
        }

        // 3. Fetch sketch names for the resolved IDs
        $sketches = [];
        if (!empty($sketchIdMap)) {
            $ids = array_keys($sketchIdMap);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $stmtS = $pdo->prepare("SELECT id, name FROM sketches WHERE id IN ($in) ORDER BY id ASC");
            $stmtS->execute($ids);
            $sketches = $stmtS->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['ok' => true, 'sketches' => $sketches, 'sequence_id' => $seqId]);
        exit;
    }

    // ── Search PLUSH stories or collections ──
    if ($action === 'search_plush') {
        header('Content-Type: application/json; charset=utf-8');
        $q          = trim($input['q'] ?? '');
        $plushType  = $input['plush_type'] ?? 'story'; // 'story' | 'collection'
        $limit      = 20;
        $table      = $plushType === 'collection' ? 'plush_collections' : 'plush_stories';

        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, title AS name FROM `$table` WHERE id = ? OR title LIKE ? ORDER BY title ASC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, title AS name FROM `$table` WHERE title LIKE ? ORDER BY title ASC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Resolve PLUSH story/collection → linked entities ──
    // Gathers all plush_highlight_block_entities for all blocks in all scenes of the story
    // (or all stories in the collection). Groups sketch-type entities for sketch export,
    // returns all entity types for reference inclusion.
    if ($action === 'resolve_plush_entities') {
        header('Content-Type: application/json; charset=utf-8');
        $plushId   = (int)($input['plush_id']   ?? 0);
        $plushType = $input['plush_type'] ?? 'story';

        if ($plushId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'plush_id required']);
            exit;
        }

        // Gather story IDs
        $storyIds = [];
        if ($plushType === 'collection') {
            $stmtSt = $pdo->prepare("SELECT story_id FROM plush_collections_2_stories WHERE collection_id = ? ORDER BY sort_order ASC");
            $stmtSt->execute([$plushId]);
            $storyIds = $stmtSt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $storyIds = [$plushId];
        }

        if (empty($storyIds)) {
            echo json_encode(['ok' => true, 'sketches' => [], 'entities' => []]);
            exit;
        }

        // Gather scene IDs for those stories
        $inSt   = implode(',', array_fill(0, count($storyIds), '?'));
        $stmtSc = $pdo->prepare("SELECT id FROM plush_scenes WHERE story_id IN ($inSt)");
        $stmtSc->execute($storyIds);
        $sceneIds = $stmtSc->fetchAll(PDO::FETCH_COLUMN);

        if (empty($sceneIds)) {
            echo json_encode(['ok' => true, 'sketches' => [], 'entities' => []]);
            exit;
        }

        // Gather block IDs for those scenes (EN blocks only to avoid duplicates)
        $inSc     = implode(',', array_fill(0, count($sceneIds), '?'));
        $stmtBl   = $pdo->prepare("SELECT id FROM plush_highlight_blocks WHERE scene_id IN ($inSc) AND language_code = 'en'");
        $stmtBl->execute($sceneIds);
        $blockIds = $stmtBl->fetchAll(PDO::FETCH_COLUMN);

        if (empty($blockIds)) {
            echo json_encode(['ok' => true, 'sketches' => [], 'entities' => []]);
            exit;
        }

        // Fetch all entity references for those blocks
        $inBl   = implode(',', array_fill(0, count($blockIds), '?'));
        $stmtEn = $pdo->prepare(
            "SELECT entity_type, entity_id, entity_label
             FROM plush_highlight_block_entities
             WHERE block_id IN ($inBl)
             GROUP BY entity_type, entity_id, entity_label
             ORDER BY entity_type ASC, entity_label ASC"
        );
        $stmtEn->execute($blockIds);
        $allEntities = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

        // Split sketches out for sketch export pipeline; collect the rest as reference entities
        $sketchIdMap  = [];
        $otherEntities = [];
        foreach ($allEntities as $ent) {
            if ($ent['entity_type'] === 'sketches') {
                $sketchIdMap[(int)$ent['entity_id']] = true;
            } else {
                $otherEntities[] = $ent;
            }
        }

        // Fetch sketch names
        $sketches = [];
        if (!empty($sketchIdMap)) {
            $skIds = array_keys($sketchIdMap);
            $inSk  = implode(',', array_fill(0, count($skIds), '?'));
            $stmtSk = $pdo->prepare("SELECT id, name FROM sketches WHERE id IN ($inSk) ORDER BY id ASC");
            $stmtSk->execute($skIds);
            $sketches = $stmtSk->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'ok'       => true,
            'sketches' => $sketches,
            'entities' => $otherEntities,
        ]);
        exit;
    }

    // ── Export Handling ──
    if ($action === 'export') {
        $config = $input['config'] ?? [];
        $format = $input['format'] ?? 'json';

        // In PLUSH mode, inject resolved plush_entities into the export payload
        // so they appear as a dedicated section alongside sketches/kg.
        $plushEntities = $config['plush_entities'] ?? [];

        $sketchUp = new \App\SketchUp\SketchUp($pdo);
        $data = $sketchUp->generateExportData($config);

        // Attach plush entity references as a top-level section when present
        if (!empty($plushEntities)) {
            $data['plush_entities'] = $plushEntities;
        }

        if ($format === 'sql') {
            $content = $sketchUp->generateSql($data);
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="sketchup_export.sql"');
            echo $content;
        } else {
            $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="sketchup_export.json"');
            echo $content;
        }
        exit;
    }

    // ── Knowledge Graph Endpoints (Cloned exactly for UI functionality) ──
    if ($action === 'fetch_tree') {
        header('Content-Type: application/json; charset=utf-8');
        $tree = [];
        $stmt = $pdo->query("SELECT id, parent_id, name FROM kg_categories ORDER BY sort_order ASC, name ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cat) {
            $tree[] = [
                'id' => 'cat_' . $cat['id'],
                'parent' => $cat['parent_id'] ? 'cat_' . $cat['parent_id'] : '#',
                'text' => $cat['name'],
                'type' => 'folder',
                'data' => []
            ];
        }
        $stmt = $pdo->query("SELECT id, category_id, name, node_type FROM kg_nodes WHERE status='active' ORDER BY sort_order ASC, name ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $node) {
            $tree[] = [
                'id' => 'n_' . $node['id'],
                'parent' => $node['category_id'] ? 'cat_' . $node['category_id'] : '#',
                'text' => $node['name'],
                'type' => 'node',
                'data' => [
                    'db_id' => (int)$node['id'],
                    'node_type' => $node['node_type']
                ]
            ];
        }
        echo json_encode(['ok' => true, 'tree' => $tree]);
        exit;
    }

    if ($action === 'kg_subpot_preview') {
        header('Content-Type: application/json; charset=utf-8');
        $nodeIds = array_map('intval', (array)($input['node_ids'] ?? []));
        $includeEdges = !empty($input['include_edges']);

        if (empty($nodeIds)) { echo json_encode(['ok' => true, 'preview' => '']); exit; }

        $ph    = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt  = $pdo->prepare("SELECT id, name, node_type, description FROM kg_nodes WHERE id IN ($ph) AND status='active'");
        $stmt->execute($nodeIds);
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lines = ['[Knowledge Graph Selection]'];
        foreach ($nodes as $n) {
            $desc   = trim(strip_tags($n['description'] ?? ''));
            $line   = "• [{$n['node_type']}] {$n['name']}";
            if ($desc) $line .= ": {$desc}";
            $lines[] = $line;
        }

        if ($includeEdges && count($nodeIds) > 1) {
            $ph2  = implode(',', array_fill(0, count($nodeIds), '?'));
            $stmt = $pdo->prepare("
                SELECT kni.relationship, kni.item_label, kn_src.name AS src_name
                FROM kg_node_items kni
                JOIN kg_nodes kn_src ON kn_src.id = kni.node_id
                WHERE kni.item_type = 'kg_node'
                  AND kni.node_id IN ($ph2)
                  AND kni.item_id IN ($ph2)
                  AND kni.item_id IS NOT NULL
                LIMIT 20
            ");
            $stmt->execute(array_merge($nodeIds, $nodeIds));
            $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($edges) {
                $lines[] = '';
                $lines[] = '[Relationships]';
                foreach ($edges as $e) {
                    $rel   = $e['relationship'] ?: 'relates to';
                    $lines[] = "→ {$e['src_name']} {$rel} {$e['item_label']}";
                }
            }
        }
        echo json_encode(['ok' => true, 'preview' => implode("\n", $lines)]);
        exit;
    }

    if ($action === 'fetch_mini_graph') {
        header('Content-Type: application/json; charset=utf-8');
        $nodeId = (int)($input['node_id'] ?? 0);
        $hops   = max(1, min(4, (int)($input['hops'] ?? 1)));
        if ($nodeId <= 0) { echo json_encode(['ok' => false, 'error' => 'Invalid node_id']); exit; }

        $visited = [$nodeId => true];
        $frontier = [$nodeId];

        for ($h = 0; $h < $hops; $h++) {
            if (empty($frontier)) break;
            $ph = implode(',', array_fill(0, count($frontier), '?'));

            $stmt = $pdo->prepare("SELECT DISTINCT item_id FROM kg_node_items WHERE item_type = 'kg_node' AND item_id IS NOT NULL AND node_id IN ($ph)");
            $stmt->execute($frontier);
            $out = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare("SELECT DISTINCT node_id FROM kg_node_items WHERE item_type = 'kg_node' AND item_id IN ($ph)");
            $stmt->execute($frontier);
            $in = $stmt->fetchAll(PDO::FETCH_COLUMN);

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

        $ids = array_keys($visited);
        $nodes = [];
        $edges = [];

        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id IN ($ph) AND status = 'active'");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
                $nodes[] = ['id' => (int)$n['id'], 'name' => $n['name'], 'node_type' => $n['node_type']];
            }

            $stmt = $pdo->prepare("
                SELECT id, node_id AS source, item_id AS target, relationship, item_label 
                FROM kg_node_items 
                WHERE item_type = 'kg_node' AND item_id IS NOT NULL AND node_id IN ($ph) AND item_id IN ($ph)
            ");
            $stmt->execute(array_merge($ids, $ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $edges[] = ['id' => (int)$e['id'], 'source' => (int)$e['source'], 'target' => (int)$e['target'], 'relationship' => $e['relationship'] ?? '', 'item_label' => $e['item_label'] ?? ''];
            }
        }
        echo json_encode(['ok' => true, 'nodes' => $nodes, 'edges' => $edges]);
        exit;
    }

    if ($action === 'get_node') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM kg_nodes WHERE id = ?");
        $stmt->execute([$id]);
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($node) echo json_encode(['ok' => true, 'node' => $node]);
        else echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ── Helper: recursively extract sketch IDs from sequence_data structure ──────
// sequence_data can be a deeply nested array; we scan for any key that looks
// like a sketch reference: entity_type='sketches'/entity_id, sketch_id, or
// arrays keyed 'sketches' containing id values.
function _extractSketchIdsFromSequenceData(array $data, array &$map): void {
    // Check if this node itself is a sketch reference
    if (isset($data['entity_type']) && $data['entity_type'] === 'sketches' && !empty($data['entity_id'])) {
        $map[(int)$data['entity_id']] = true;
    }
    if (!empty($data['sketch_id'])) {
        $map[(int)$data['sketch_id']] = true;
    }
    // A 'sketches' key holding an array of objects or IDs
    if (isset($data['sketches']) && is_array($data['sketches'])) {
        foreach ($data['sketches'] as $item) {
            if (is_array($item) && !empty($item['id'])) {
                $map[(int)$item['id']] = true;
            } elseif (is_numeric($item)) {
                $map[(int)$item] = true;
            }
        }
    }
    // Recurse into all array children
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            _extractSketchIdsFromSequenceData($value, $map);
        }
    }
}
