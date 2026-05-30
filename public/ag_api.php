<?php
// public/ag_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

function ag_json($ok, $data =[], $error = null) {
    $out = ['ok' => $ok];
    if ($error) $out['error'] = $error;
    foreach ($data as $k => $v) $out[$k] = $v;
    echo json_encode($out);
    exit;
}

$input  =[];
$raw    = file_get_contents('php://input');
if ($raw) {
    $dec = json_decode($raw, true);
    if (is_array($dec)) $input = $dec;
}
$input  = array_merge($input, $_POST);
$action = $input['action'] ?? $_GET['action'] ?? '';
$docId  = (int)($input['doc_id'] ?? $_GET['doc_id'] ?? 0);

try {
    // -------------------------------------------------------
    // FETCH AVAILABLE DOCUMENTS
    // -------------------------------------------------------
    if ($action === 'fetch_docs') {
        $docs = $pdo->query("
            SELECT a.doc_id as id, d.name
            FROM md_doc_analysis a
            JOIN documentations d ON a.doc_id = d.id
            ORDER BY d.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        ag_json(true, ['docs' => $docs]);
    }

    if (!$docId && $action !== 'fetch_docs') {
        ag_json(false,[], 'Missing doc_id context');
    }

    // -------------------------------------------------------
    // FETCH VISUALS
    // -------------------------------------------------------
    if ($action === 'fetch_visuals') {
        $entityName = trim($input['entity_name'] ?? '');
        
        // Removed entity_type strictness because graph uses singular types while curated docs use plural.
        // We solely rely on a robust document & case-insensitive entity_name lookup to locate the sketches safely.
        $sqlHistory = "
            SELECT slh.sketch_id, s.name, s.description,
                   sa.overall_quality, sa.classification, sa.scoring, sa.entities, sa.thematics, sa.recommendations
            FROM sketch_lore_history slh
            JOIN sketches s ON slh.sketch_id = s.id
            LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
            WHERE slh.doc_id = ? 
              AND LOWER(slh.entity_name) = LOWER(?)
            ORDER BY slh.id DESC LIMIT 1
        ";
        $stmt = $pdo->prepare($sqlHistory);
        $stmt->execute([$docId, $entityName]);
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
        ag_json(true, ['sketch' => $sketchData]);
    }

    // -------------------------------------------------------
    // TREE
    // -------------------------------------------------------
    if ($action === 'fetch_tree') {
        $cats = $pdo->prepare("SELECT id, parent_id, name FROM ag_categories WHERE doc_id=? ORDER BY sort_order ASC, name ASC");
        $cats->execute([$docId]);
        $cats = $cats->fetchAll(PDO::FETCH_ASSOC);

        $nodes = $pdo->prepare("SELECT id, category_id, name, node_type FROM ag_nodes WHERE doc_id=? AND status='active' ORDER BY sort_order ASC, name ASC");
        $nodes->execute([$docId]);
        $nodes = $nodes->fetchAll(PDO::FETCH_ASSOC);

        $tree = [];
        foreach ($cats as $c) {
            $tree[] =[
                'id'     => 'c_' . $c['id'],
                'parent' => $c['parent_id'] ? 'c_' . $c['parent_id'] : '#',
                'text'   => $c['name'],
                'icon'   => 'bi bi-folder2',
                'type'   => 'folder',
                'data'   => ['db_id' => $c['id'], 'type' => 'category'],
            ];
        }
        foreach ($nodes as $n) {
            $tree[] = [
                'id'     => 'n_' . $n['id'],
                'parent' => $n['category_id'] ? 'c_' . $n['category_id'] : '#',
                'text'   => $n['name'],
                'icon'   => ag_node_icon($n['node_type']),
                'type'   => 'node',
                'data'   => ['db_id' => $n['id'], 'type' => 'node', 'node_type' => $n['node_type']],
            ];
        }
        ag_json(true,['tree' => $tree]);
    }

    // -------------------------------------------------------
    // MOVE NODE
    // -------------------------------------------------------
    if ($action === 'move_node') {
        $id       = $input['id'] ?? '';
        $parentId = $input['parent'] ?? '#';
        $dbParent = ($parentId !== '#' && str_starts_with($parentId, 'c_')) ? (int)substr($parentId, 2) : null;

        if (str_starts_with($id, 'c_')) {
            $pdo->prepare("UPDATE ag_categories SET parent_id=? WHERE id=? AND doc_id=?")->execute([$dbParent, (int)substr($id, 2), $docId]);
        } elseif (str_starts_with($id, 'n_')) {
            $pdo->prepare("UPDATE ag_nodes SET category_id=? WHERE id=? AND doc_id=?")->execute([$dbParent, (int)substr($id, 2), $docId]);
        }
        ag_json(true);
    }

    // -------------------------------------------------------
    // CREATE / RENAME / DELETE CATEGORY
    // -------------------------------------------------------
    if ($action === 'create_category') {
        $name     = trim($input['name'] ?? '');
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
        if (!$name) ag_json(false,[], 'Name required');
        $pdo->prepare("INSERT INTO ag_categories (doc_id, name, parent_id) VALUES (?,?,?)")->execute([$docId, $name, $parentId]);
        ag_json(true, ['id' => $pdo->lastInsertId()]);
    }

    if ($action === 'rename_category') {
        $id   = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $pdo->prepare("UPDATE ag_categories SET name=? WHERE id=? AND doc_id=?")->execute([$name, $id, $docId]);
        ag_json(true);
    }

    if ($action === 'delete_category') {
        $id = (int)($input['id'] ?? 0);
        $pdo->prepare("UPDATE ag_nodes SET category_id=NULL WHERE category_id=? AND doc_id=?")->execute([$id, $docId]);
        $pdo->prepare("UPDATE ag_categories SET parent_id=NULL WHERE parent_id=? AND doc_id=?")->execute([$id, $docId]);
        $pdo->prepare("DELETE FROM ag_categories WHERE id=? AND doc_id=?")->execute([$id, $docId]);
        ag_json(true);
    }

    // -------------------------------------------------------
    // CREATE NODE
    // -------------------------------------------------------
    if ($action === 'create_node') {
        $name     = trim($input['name'] ?? 'New Node');
        $catId    = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $nodeType = trim($input['node_type'] ?? 'note');
        $pdo->prepare("INSERT INTO ag_nodes (doc_id, name, category_id, node_type, content) VALUES (?,?,?,?,?)")
            ->execute([$docId, $name, $catId, $nodeType, '']);
        ag_json(true, ['id' => $pdo->lastInsertId()]);
    }

    // -------------------------------------------------------
    // SAVE NODE
    // -------------------------------------------------------
    if ($action === 'save_node') {
        $id       = (int)($input['id'] ?? 0);
        $name     = trim($input['name'] ?? '');
        $content  = $input['content'] ?? '';
        $keywords = $input['keywords'] ?? '';
        $nodeType = trim($input['node_type'] ?? 'note');
        $catId    = !empty($input['category_id']) ? (int)$input['category_id'] : null;

        if (!$id) ag_json(false,[], 'Missing id');

        $pdo->prepare("
            UPDATE ag_nodes
            SET name=?, content=?, keywords=?, node_type=?, category_id=?, updated_at=NOW()
            WHERE id=? AND doc_id=?
        ")->execute([$name, $content, $keywords, $nodeType, $catId, $id, $docId]);

        ag_json(true,['id' => $id]);
    }

    // -------------------------------------------------------
    // DELETE NODE
    // -------------------------------------------------------
    if ($action === 'delete_node') {
        $id = (int)($input['id'] ?? 0);
        $pdo->prepare("UPDATE ag_nodes SET status='archived' WHERE id=? AND doc_id=?")->execute([$id, $docId]);
        ag_json(true);
    }

    // -------------------------------------------------------
    // RENAME NODE
    // -------------------------------------------------------
    if ($action === 'rename_node') {
        $id   = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id || !$name) ag_json(false,[], 'Missing params');
        $pdo->prepare("UPDATE ag_nodes SET name=? WHERE id=? AND doc_id=?")->execute([$name, $id, $docId]);
        ag_json(true);
    }

    // -------------------------------------------------------
    // GET NODE (includes edges)
    // -------------------------------------------------------
    if ($action === 'get_node') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) ag_json(false,[], 'Missing id');
        $stmt = $pdo->prepare("SELECT * FROM ag_nodes WHERE id=? AND doc_id=?");
        $stmt->execute([$id, $docId]);
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$node) ag_json(false,[], 'Node not found');

        $outgoing = $pdo->prepare("SELECT *, 'outgoing' AS direction FROM ag_node_items WHERE node_id=? AND doc_id=? ORDER BY sort_order ASC");
        $outgoing->execute([$id, $docId]);

        $incoming = $pdo->prepare("
            SELECT ani.*, 'incoming' AS direction, an.name AS source_node_name, an.node_type AS source_node_type
            FROM ag_node_items ani
            JOIN ag_nodes an ON an.id = ani.node_id
            WHERE ani.item_type IN ('ag_node', 'unknown')
              AND ani.item_label = ?
              AND ani.node_id != ?
              AND ani.doc_id = ?
            ORDER BY ani.node_id ASC
        ");
        $incoming->execute([$node['name'], $id, $docId]);

        $node['items'] = array_merge(
            $outgoing->fetchAll(PDO::FETCH_ASSOC),
            $incoming->fetchAll(PDO::FETCH_ASSOC)
        );
        ag_json(true, ['node' => $node]);
    }

    // -------------------------------------------------------
    // ADD / UPDATE / REMOVE LINKED ITEM
    // -------------------------------------------------------
    if ($action === 'add_item') {
        $nodeId       = (int)($input['node_id'] ?? 0);
        $itemType     = trim($input['item_type'] ?? '');
        $itemId       = !empty($input['item_id']) ? (int)$input['item_id'] : null;
        $itemLabel    = trim($input['item_label'] ?? '');
        $relationship = trim($input['relationship'] ?? '');
        $note         = trim($input['note'] ?? '');
        if (!$nodeId || !$itemType) ag_json(false,[], 'Missing params');
        $pdo->prepare("INSERT INTO ag_node_items (doc_id, node_id, item_type, item_id, item_label, relationship, note) VALUES (?,?,?,?,?,?,?)")
            ->execute([$docId, $nodeId, $itemType, $itemId, $itemLabel, $relationship, $note]);
        ag_json(true, ['id' => $pdo->lastInsertId()]);
    }

    if ($action === 'update_item') {
        $id           = (int)($input['id'] ?? 0);
        $itemType     = trim($input['item_type'] ?? '');
        $itemId       = !empty($input['item_id']) ? (int)$input['item_id'] : null;
        $itemLabel    = trim($input['item_label'] ?? '');
        $relationship = trim($input['relationship'] ?? '');
        $note         = trim($input['note'] ?? '');
        if (!$id) ag_json(false,[], 'Missing id');
        $pdo->prepare("
            UPDATE ag_node_items
            SET item_type=?, item_id=?, item_label=?, relationship=?, note=?
            WHERE id=? AND doc_id=?
        ")->execute([$itemType, $itemId, $itemLabel, $relationship, $note, $id, $docId]);
        ag_json(true);
    }

    if ($action === 'remove_item') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) ag_json(false,[], 'Missing id');
        $pdo->prepare("DELETE FROM ag_node_items WHERE id=? AND doc_id=?")->execute([$id, $docId]);
        ag_json(true);
    }

    // -------------------------------------------------------
    // EXPORT SNAPSHOT
    // -------------------------------------------------------
    if ($action === 'export_snapshot') {
        $nodes = $pdo->prepare("SELECT id, name, node_type, content FROM ag_nodes WHERE doc_id=? AND status='active'");
        $nodes->execute([$docId]);
        $edges = $pdo->prepare("SELECT node_id, item_label, relationship, note FROM ag_node_items WHERE doc_id=?");
        $edges->execute([$docId]);

        $snapshot =[
            'doc_id' => $docId,
            'nodes'  => $nodes->fetchAll(PDO::FETCH_ASSOC),
            'edges'  => $edges->fetchAll(PDO::FETCH_ASSOC),
        ];
        ag_json(true, ['snapshot' => $snapshot]);
    }

    // -------------------------------------------------------
    // SEMANTIC QUERY (SQL keyword fallback — no Chroma needed)
    // -------------------------------------------------------
    if ($action === 'semantic_query') {
        $query = trim($input['query'] ?? '');
        $terms = array_filter(explode(' ', strtolower($query)), fn($t) => strlen($t) > 2);
        $hits  =[];

        if (!empty($terms)) {
            $stmt = $pdo->prepare("SELECT id as node_id, name, node_type, content FROM ag_nodes WHERE doc_id=? AND status='active'");
            $stmt->execute([$docId]);
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($nodes as $n) {
                $score      = 0;
                $searchable = strtolower($n['name'] . ' ' . $n['content']);
                foreach ($terms as $t) {
                    if (strpos($searchable, $t) !== false) {
                        $score += (strpos(strtolower($n['name']), $t) !== false) ? 3 : 1;
                    }
                }
                if ($score > 0) {
                    $n['score']   = $score;
                    $n['excerpt'] = mb_substr(strip_tags($n['content'] ?? ''), 0, 150) . '…';
                    unset($n['content']);
                    $hits[] = $n;
                }
            }
            usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
        }
        ag_json(true, ['hits' => array_slice($hits, 0, 20)]);
    }

    // -------------------------------------------------------
    // FOCUSED SNAPSHOT
    // -------------------------------------------------------
    if ($action === 'focused_snapshot') {
        $nodeIds = $input['node_ids'] ??[];
        if (empty($nodeIds) || !is_array($nodeIds)) ag_json(false,[], 'node_ids array required');
        $nodeIds      = array_values(array_filter(array_map('intval', $nodeIds)));
        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));

        $withContent = !empty($input['with_content']);
        $cols        = $withContent ? 'id, name, node_type, content' : 'id, name, node_type';

        $nodes = $pdo->prepare("SELECT $cols FROM ag_nodes WHERE doc_id=? AND id IN ($placeholders)");
        $nodes->execute(array_merge([$docId], $nodeIds));

        $edges = $pdo->prepare("SELECT node_id, item_label, relationship, note FROM ag_node_items WHERE doc_id=? AND node_id IN ($placeholders)");
        $edges->execute(array_merge([$docId], $nodeIds));

        ag_json(true, ['snapshot' =>[
            'doc_id' => $docId,
            'nodes'  => $nodes->fetchAll(PDO::FETCH_ASSOC),
            'edges'  => $edges->fetchAll(PDO::FETCH_ASSOC),
        ]]);
    }

    ag_json(false,[], 'Unknown action: ' . $action);

} catch (Exception $e) {
    http_response_code(500);
    ag_json(false,[], $e->getMessage());
}

function ag_node_icon(string $type): string {
    return match($type) {
        'relationship' => 'bi bi-arrow-left-right',
        'character'    => 'bi bi-person-fill',
        'location'     => 'bi bi-geo-alt-fill',
        'event'        => 'bi bi-calendar-event',
        'concept'      => 'bi bi-lightbulb-fill',
        'arc'          => 'bi bi-bezier2',
        'episode'      => 'bi bi-camera-reels',
        default        => 'bi bi-journal-text',
    };
}
?>