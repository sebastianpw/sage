<?php
// public/kg_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

function kg_json($ok, $data =[], $error = null) {
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
// merge POST for form-encoded (POST wins over JSON body for existing actions)
$input  = array_merge($input, $_POST);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {

// -------------------------------------------------------
// FETCH VISUALS
// -------------------------------------------------------
if ($action === 'fetch_visuals') {
    $entityName = trim($input['entity_name'] ?? '');
    
    $sqlHistory = "
        SELECT slh.sketch_id, s.name, s.description,
               sa.overall_quality, sa.classification, sa.scoring, sa.entities, sa.thematics, sa.recommendations
        FROM sketch_lore_history slh
        JOIN sketches s ON slh.sketch_id = s.id
        LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
        WHERE LOWER(slh.entity_name) = LOWER(?)
        ORDER BY slh.id DESC LIMIT 1
    ";
    $stmt = $pdo->prepare($sqlHistory);
    $stmt->execute([$entityName]);
    $historyRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sketchData = null;
    $kgNodeId = 0;
    $agNodeId = 0;

    // Look up KG node by name
    $stmtKg = $pdo->prepare("SELECT id FROM kg_nodes WHERE LOWER(name) = LOWER(?) AND status = 'active' LIMIT 1");
    $stmtKg->execute([$entityName]);
    $kgNodeId = (int)($stmtKg->fetchColumn() ?: 0);

    if ($historyRow) {
        $sketchId = $historyRow['sketch_id'];

        // --- AG node detection via sketch_lore_history ---
        $histStmt = $pdo->prepare("
            SELECT slh.doc_id, slh.entity_type, slh.entity_name
            FROM sketch_lore_history slh
            WHERE slh.sketch_id = ?
            ORDER BY slh.id DESC
        ");
        $histStmt->execute([$sketchId]);
        $histRows = $histStmt->fetchAll(PDO::FETCH_ASSOC);

        $loreDocId = null;
        $loreEntityName = null;

        foreach ($histRows as $row) {
            if ($loreDocId === null && substr($row['entity_type'], -1) === 's') {
                $loreDocId = (int)$row['doc_id'];
                $loreEntityName = $row['entity_name'];
            }
            if ($loreDocId !== null) break;
        }

        if ($loreDocId) {
            $docCheck = $pdo->prepare("SELECT id FROM documentations WHERE id = ? AND is_active = 1 LIMIT 1");
            $docCheck->execute([$loreDocId]);
            if ($docCheck->fetch()) {
                $agStmt = $pdo->prepare("
                    SELECT id FROM ag_nodes
                    WHERE doc_id = ? AND LOWER(name) = LOWER(?) AND status = 'active'
                    LIMIT 1
                ");
                $agStmt->execute([$loreDocId, $loreEntityName]);
                $agNodeId = (int)($agStmt->fetchColumn() ?: 0);
            }
        }

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
        
        $sketchData = [
            'id' => $sketchId,
            'name' => $historyRow['name'],
            'description' => $historyRow['description'],
            'frames' => $frames
        ];

        if (!empty($historyRow['classification'])) {
            $sketchData['curation'] = [
                'score' => $historyRow['overall_quality'],
                'class' => json_decode($historyRow['classification'], true),
                'score_breakdown' => json_decode($historyRow['scoring'], true),
                'entities' => json_decode($historyRow['entities'], true),
                'themes' => json_decode($historyRow['thematics'], true),
                'recs' => json_decode($historyRow['recommendations'], true)
            ];
        }
    }

    kg_json(true, [
        'sketch'     => $sketchData,
        'kg_node_id' => $kgNodeId,
        'ag_node_id' => $agNodeId,
        'ag_doc_id'  => $loreDocId ?? 0,
    ]);
}

    // -------------------------------------------------------
    // TREE
    // -------------------------------------------------------
    if ($action === 'fetch_tree') {
        $cats = $pdo->query("SELECT id, parent_id, name FROM kg_categories ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $nodes = $pdo->query("SELECT id, category_id, name, node_type FROM kg_nodes WHERE status='active' ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $tree =[];
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
            $icon = kg_node_icon($n['node_type']);
            $tree[] =[
                'id'     => 'n_' . $n['id'],
                'parent' => $n['category_id'] ? 'c_' . $n['category_id'] : '#',
                'text'   => $n['name'],
                'icon'   => $icon,
                'type'   => 'node',
                'data'   => ['db_id' => $n['id'], 'type' => 'node', 'node_type' => $n['node_type']],
            ];
        }
        kg_json(true, ['tree' => $tree]);
    }

    // -------------------------------------------------------
    // MOVE NODE
    // -------------------------------------------------------
    if ($action === 'move_node') {
        $id       = $input['id'] ?? '';
        $parentId = $input['parent'] ?? '#';
        $dbParent = ($parentId !== '#' && str_starts_with($parentId, 'c_')) ? (int)substr($parentId, 2) : null;

        if (str_starts_with($id, 'c_')) {
            $pdo->prepare("UPDATE kg_categories SET parent_id=? WHERE id=?")->execute([$dbParent, (int)substr($id, 2)]);
        } elseif (str_starts_with($id, 'n_')) {
            $pdo->prepare("UPDATE kg_nodes SET category_id=? WHERE id=?")->execute([$dbParent, (int)substr($id, 2)]);
        }
        kg_json(true);
    }

    // -------------------------------------------------------
    // CREATE CATEGORY
    // -------------------------------------------------------
    if ($action === 'create_category') {
        $name     = trim($input['name'] ?? '');
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
        if (!$name) kg_json(false,[], 'Name required');
        $pdo->prepare("INSERT INTO kg_categories (name, parent_id) VALUES (?,?)")->execute([$name, $parentId]);
        kg_json(true, ['id' => $pdo->lastInsertId()]);
    }

    // -------------------------------------------------------
    // RENAME CATEGORY
    // -------------------------------------------------------
    if ($action === 'rename_category') {
        $id   = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id || !$name) kg_json(false,[], 'Missing params');
        $pdo->prepare("UPDATE kg_categories SET name=? WHERE id=?")->execute([$name, $id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // DELETE CATEGORY
    // -------------------------------------------------------
    if ($action === 'delete_category') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) kg_json(false,[], 'Missing id');
        $pdo->prepare("UPDATE kg_nodes SET category_id=NULL WHERE category_id=?")->execute([$id]);
        $pdo->prepare("UPDATE kg_categories SET parent_id=NULL WHERE parent_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM kg_categories WHERE id=?")->execute([$id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // CREATE NODE
    // -------------------------------------------------------
    if ($action === 'create_node') {
        $name     = trim($input['name'] ?? 'New Node');
        $catId    = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $nodeType = trim($input['node_type'] ?? 'note');
        $pdo->prepare("INSERT INTO kg_nodes (name, category_id, node_type, content) VALUES (?,?,?,?)")
            ->execute([$name, $catId, $nodeType, '']);
        $newId = $pdo->lastInsertId();
        kg_json(true, ['id' => $newId]);
    }

    // -------------------------------------------------------
    // GET NODE
    // -------------------------------------------------------
    if ($action === 'get_node') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) kg_json(false,[], 'Missing id');
        $stmt = $pdo->prepare("SELECT * FROM kg_nodes WHERE id=?");
        $stmt->execute([$id]);
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$node) kg_json(false,[], 'Node not found');

        $outgoing = $pdo->prepare("SELECT *, 'outgoing' AS direction FROM kg_node_items WHERE node_id=? ORDER BY sort_order ASC");
        $outgoing->execute([$id]);

        $incoming = $pdo->prepare("
            SELECT kni.*, 'incoming' AS direction, kn.name AS source_node_name, kn.node_type AS source_node_type
            FROM kg_node_items kni
            JOIN kg_nodes kn ON kn.id = kni.node_id
            WHERE kni.item_type = 'kg_node'
              AND kni.item_id = ?
              AND kni.node_id != ?
            ORDER BY kni.node_id ASC
        ");
        $incoming->execute([$id, $id]);

        $node['items'] = array_merge(
            $outgoing->fetchAll(PDO::FETCH_ASSOC),
            $incoming->fetchAll(PDO::FETCH_ASSOC)
        );

        kg_json(true, ['node' => $node]);
    }

    // -------------------------------------------------------
    // SAVE NODE
    // -------------------------------------------------------
    if ($action === 'save_node') {
        $id          = (int)($input['id'] ?? 0);
        $name        = trim($input['name'] ?? '');
        $content     = $input['content'] ?? '';
        $description = $input['description'] ?? '';
        $keywords    = $input['keywords'] ?? '';
        $nodeType    = trim($input['node_type'] ?? 'note');
        $catId       = !empty($input['category_id']) ? (int)$input['category_id'] : null;

        if ($id) {
            $pdo->prepare("UPDATE kg_nodes SET name=?, content=?, description=?, keywords=?, node_type=?, category_id=?, updated_at=NOW() WHERE id=?")
                ->execute([$name, $content, $description, $keywords, $nodeType, $catId, $id]);
            kg_json(true,['id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO kg_nodes (name, content, description, keywords, node_type, category_id) VALUES (?,?,?,?,?,?)")
                ->execute([$name, $content, $description, $keywords, $nodeType, $catId]);
            kg_json(true, ['id' => $pdo->lastInsertId()]);
        }
    }

    // -------------------------------------------------------
    // DELETE NODE
    // -------------------------------------------------------
    if ($action === 'delete_node') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) kg_json(false,[], 'Missing id');
        $pdo->prepare("UPDATE kg_nodes SET status='archived' WHERE id=?")->execute([$id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // RENAME NODE
    // -------------------------------------------------------
    if ($action === 'rename_node') {
        $id   = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id || !$name) kg_json(false,[], 'Missing params');
        $pdo->prepare("UPDATE kg_nodes SET name=? WHERE id=?")->execute([$name, $id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // ADD LINKED ITEM
    // -------------------------------------------------------
    if ($action === 'add_item') {
        $nodeId       = (int)($input['node_id'] ?? 0);
        $itemType     = trim($input['item_type'] ?? '');
        $itemId       = !empty($input['item_id']) ? (int)$input['item_id'] : null;
        $itemLabel    = trim($input['item_label'] ?? '');
        $relationship = trim($input['relationship'] ?? '');
        $note         = trim($input['note'] ?? '');
        if (!$nodeId || !$itemType) kg_json(false,[], 'Missing params');
        $max = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM kg_node_items WHERE node_id=?");
        $max->execute([$nodeId]);
        $nextOrder = $max->fetchColumn();
        $pdo->prepare("INSERT INTO kg_node_items (node_id, item_type, item_id, item_label, relationship, note, sort_order) VALUES (?,?,?,?,?,?,?)")
            ->execute([$nodeId, $itemType, $itemId, $itemLabel, $relationship, $note, $nextOrder]);
        kg_json(true, ['id' => $pdo->lastInsertId()]);
    }

    // -------------------------------------------------------
    // UPDATE LINKED ITEM
    // -------------------------------------------------------
    if ($action === 'update_item') {
        $id           = (int)($input['id'] ?? 0);
        $itemType     = trim($input['item_type'] ?? '');
        $itemId       = !empty($input['item_id']) ? (int)$input['item_id'] : null;
        $itemLabel    = trim($input['item_label'] ?? '');
        $relationship = trim($input['relationship'] ?? '');
        $note         = trim($input['note'] ?? '');
        if (!$id) kg_json(false,[], 'Missing id');
        $pdo->prepare("
            UPDATE kg_node_items
            SET item_type=?, item_id=?, item_label=?, relationship=?, note=?
            WHERE id=?
        ")->execute([$itemType, $itemId, $itemLabel, $relationship, $note, $id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // REMOVE LINKED ITEM
    // -------------------------------------------------------
    if ($action === 'remove_item') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) kg_json(false,[], 'Missing id');
        $pdo->prepare("DELETE FROM kg_node_items WHERE id=?")->execute([$id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // SAVE GRAPH LAYOUT
    // -------------------------------------------------------
    if ($action === 'save_layout') {
        $positions = $input['positions'] ?? [];
        if (!is_array($positions) || empty($positions)) {
            kg_json(false, [], 'No positions provided');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO kg_node_coordinates (node_id, x, y) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE x=VALUES(x), y=VALUES(y)");
        foreach ($positions as $pos) {
            $stmt->execute([(int)$pos['id'], (float)$pos['x'], (float)$pos['y']]);
        }
        $pdo->commit();
        
        kg_json(true);
    }

    // -------------------------------------------------------
    // SEARCH
    // -------------------------------------------------------
    if ($action === 'search') {
        $q    = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE status='active' AND (name LIKE ? OR content LIKE ? OR keywords LIKE ?) LIMIT 50");
        $stmt->execute([$q, $q, $q]);
        kg_json(true, ['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // -------------------------------------------------------
    // EXPORT SNAPSHOT
    // -------------------------------------------------------
    if ($action === 'export_snapshot') {
        $withEdges = !isset($_GET['with_edges']) || (int)$_GET['with_edges'] !== 0;

        $catsRaw = $pdo->query("
            SELECT c.id, c.parent_id, c.name, c.sort_order,
                   GROUP_CONCAT(child.id ORDER BY child.sort_order) as child_category_ids
            FROM kg_categories c
            LEFT JOIN kg_categories child ON child.parent_id = c.id
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $catFields =['id','parent_id','name','sort_order','child_category_ids'];
        $catRows   =[];
        foreach ($catsRaw as $c) {
            $catRows[] = [
                (int)$c['id'],
                $c['parent_id'] ? (int)$c['parent_id'] : null,
                $c['name'],
                (int)$c['sort_order'],
                $c['child_category_ids'] ? array_map('intval', explode(',', $c['child_category_ids'])) :[],
            ];
        }

        $nodesRaw = $pdo->query("
            SELECT id, category_id, name, node_type, keywords,
                   sort_order, status, created_at, updated_at,
                   CHAR_LENGTH(COALESCE(content,'')) as content_chars
            FROM kg_nodes
            WHERE status = 'active'
            ORDER BY category_id ASC, sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $nodeFields =['id','category_id','name','node_type','keywords',
                       'sort_order','status','created_at','updated_at',
                       'content_chars','content_words_approx','content_status'];
        $nodeRows   = [];
        foreach ($nodesRaw as $n) {
            $chars  = (int)$n['content_chars'];
            $status = match(true) {
                $chars === 0  => 'empty',
                $chars < 200  => 'stub',
                $chars < 600  => 'partial',
                default       => 'filled',
            };
            $nodeRows[] = [
                (int)$n['id'],
                $n['category_id'] ? (int)$n['category_id'] : null,
                $n['name'],
                $n['node_type'],
                $n['keywords'],
                (int)$n['sort_order'],
                $n['status'],
                $n['created_at'],
                $n['updated_at'],
                $chars,
                $chars > 0 ? (int)round($chars / 5.5) : 0,
                $status,
            ];
        }

        $edgeFields =['id','node_id','item_type','item_id','item_label','relationship','note','sort_order'];
        $edgeRows   =[];
        if ($withEdges) {
            $edgesRaw = $pdo->query("
                SELECT id, node_id, item_type, item_id, item_label, relationship, note, sort_order
                FROM kg_node_items
                ORDER BY node_id ASC, sort_order ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($edgesRaw as $e) {
                $edgeRows[] = [
                    (int)$e['id'],
                    (int)$e['node_id'],
                    $e['item_type'],
                    $e['item_id'] ? (int)$e['item_id'] : null,
                    $e['item_label'],
                    $e['relationship'],
                    $e['note'],
                    (int)$e['sort_order'],
                ];
            }
        }

        $totalNodes = count($nodeRows);
        $byStatus   =['empty'=>0,'stub'=>0,'partial'=>0,'filled'=>0];
        foreach ($nodeRows as $r) $byStatus[$r[11]]++;

        $snapshot =[
            'export_meta' =>[
                'generated_at'           => date('c'),
                'schema_tables'          => ['kg_categories','kg_nodes','kg_node_items'],
                'columnar_format'        => true,
                'total_categories'       => count($catRows),
                'total_nodes'            => $totalNodes,
                'total_edges'            => count($edgeRows),
                'with_edges'             => $withEdges,
                'content_status_summary' => $byStatus,
            ],
            'categories' =>['fields' => $catFields, 'rows' => $catRows],
            'nodes'      =>['fields' => $nodeFields, 'rows' => $nodeRows],
            'edges'      =>['fields' => $edgeFields, 'rows' => $edgeRows],
        ];

        kg_json(true,['snapshot' => $snapshot]);
    }

    // -------------------------------------------------------
    // EDGE CANDIDATE PACK
    // -------------------------------------------------------
    if ($action === 'edge_candidate_pack') {
        $nodeId      = (int)($input['node_id'] ?? 0);
        $nResults    = min(max((int)($input['n_results'] ?? 12), 1), 20);
        $maxExcerpt  = min(max((int)($input['max_excerpt'] ?? 120), 40), 240);

        if (!$nodeId) {
            kg_json(false, [], 'Missing node_id');
        }

        $nodeStmt = $pdo->prepare("
            SELECT
                n.id,
                n.name,
                n.node_type,
                COALESCE(n.content, '') AS content,
                COALESCE(n.description, '') AS description,
                COALESCE(n.keywords, '') AS keywords,
                CHAR_LENGTH(COALESCE(n.content, '')) AS content_chars,
                c.name AS category_name
            FROM kg_nodes n
            LEFT JOIN kg_categories c ON c.id = n.category_id
            WHERE n.id = ? AND n.status = 'active'
            LIMIT 1
        ");
        $nodeStmt->execute([$nodeId]);
        $focal = $nodeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$focal) {
            kg_json(false, [], 'Node not found or inactive');
        }

        $focalText = trim(
            $focal['name'] . "\n\n" .
            $focal['description'] . "\n\n" .
            $focal['keywords'] . "\n\n" .
            mb_substr($focal['content'], 0, 4000)
        );

        if ($focalText === '') {
            kg_json(false, [], 'Focal node has no usable text');
        }

        $pyapiEchoScript = dirname(__DIR__) . '/bash/pyapi_echo.sh';
        $pyapiUrl = rtrim(trim(shell_exec('sh ' . escapeshellarg($pyapiEchoScript))) ?: 'http://127.0.0.1:8009', '/');

        $collections = [
            ['name' => 'sage_kg_nodes_content', 'weight' => 1.0, 'n' => $nResults],
            ['name' => 'sage_kg_nodes_meta',    'weight' => 0.6, 'n' => (int)ceil($nResults * 0.6)],
        ];

        $scoreMap = [];

        foreach ($collections as $coll) {
            $payload = json_encode([
                'text'       => $focalText,
                'collection'  => $coll['name'],
                'n_results'   => $coll['n'],
                'modality'    => 'text',
            ]);

            $ch = curl_init($pyapiUrl . '/chroma/query_json');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
            ]);

            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp === false || $httpCode !== 200) {
                continue;
            }

            $data = json_decode($resp, true);
            if (empty($data['result'])) {
                continue;
            }

            $result    = $data['result'];
            $ids       = $result['ids'][0] ?? [];
            $distances = $result['distances'][0] ?? [];
            $metas     = $result['metadatas'][0] ?? [];
            $docs      = $result['documents'][0] ?? [];

            foreach ($ids as $i => $chromaId) {
                $candNodeId = (int)($metas[$i]['node_id'] ?? 0);
                if (!$candNodeId || $candNodeId === $nodeId) {
                    continue;
                }

                $distance   = (float)($distances[$i] ?? 1.0);
                $similarity = max(0.0, 1.0 - ($distance / 2.0));
                $weighted   = $similarity * $coll['weight'];

                $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($docs[$i] ?? ''))));
                if ($excerpt !== '' && mb_strlen($excerpt) > $maxExcerpt) {
                    $excerpt = mb_substr($excerpt, 0, $maxExcerpt - 1) . '…';
                }

                if (!isset($scoreMap[$candNodeId]) || $weighted > $scoreMap[$candNodeId]['score']) {
                    $scoreMap[$candNodeId] = [
                        'score'   => $weighted,
                        'meta'    => $metas[$i],
                        'excerpt' => $excerpt,
                        'source'  => $coll['name'],
                    ];
                }
            }
        }

        if (empty($scoreMap)) {
            kg_json(true, [
                'focal_node' => [
                    'id'            => (int)$focal['id'],
                    'name'          => $focal['name'],
                    'node_type'     => $focal['node_type'],
                    'category_name' => $focal['category_name'] ?? '',
                    'content_chars' => (int)$focal['content_chars'],
                ],
                'hits' => [],
                'total' => 0,
            ]);
        }

        uasort($scoreMap, fn($a, $b) => $b['score'] <=> $a['score']);

        $nodeIds = array_keys($scoreMap);
        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));

        $stmt = $pdo->prepare("
            SELECT
                n.id,
                n.name,
                n.node_type,
                n.keywords,
                c.name AS category_name,
                CHAR_LENGTH(COALESCE(n.content, '')) AS content_chars
            FROM kg_nodes n
            LEFT JOIN kg_categories c ON c.id = n.category_id
            WHERE n.id IN ($placeholders)
              AND n.status = 'active'
        ");
        $stmt->execute($nodeIds);

        $dbRows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dbRows[(int)$r['id']] = $r;
        }

        $hits = [];
        foreach ($scoreMap as $candNodeId => $entry) {
            $db = $dbRows[$candNodeId] ?? null;
            if (!$db) {
                continue;
            }

            $chars = (int)$db['content_chars'];
            $contentStatus = match (true) {
                $chars === 0  => 'empty',
                $chars < 200  => 'stub',
                $chars < 600  => 'partial',
                default       => 'filled',
            };

            $hits[] = [
                'node_id'        => $candNodeId,
                'score'          => round($entry['score'], 4),
                'name'           => $db['name'],
                'node_type'      => $db['node_type'],
                'category_name'  => $db['category_name'] ?? '',
                'keywords'       => $db['keywords'] ?? '',
                'content_status' => $contentStatus,
                'content_chars'  => $chars,
                'excerpt'        => $entry['excerpt'],
                'source'         => $entry['source'],
            ];
        }

        kg_json(true, [
            'focal_node' => [
                'id'            => (int)$focal['id'],
                'name'          => $focal['name'],
                'node_type'     => $focal['node_type'],
                'category_name' => $focal['category_name'] ?? '',
                'content_chars' => (int)$focal['content_chars'],
            ],
            'hits'  => $hits,
            'total' => count($hits),
        ]);
    }

    // -------------------------------------------------------
    // SEMANTIC QUERY  
    // -------------------------------------------------------
    if ($action === 'semantic_query') {
        $query    = trim($input['query'] ?? '');
        $nResults = min((int)($input['n_results'] ?? 20), 60);
        if (!$query) kg_json(false,[], 'Query required');

        $pyapiEchoScript = dirname(__DIR__) . '/bash/pyapi_echo.sh';
        $pyapiUrl = rtrim(trim(shell_exec('sh ' . escapeshellarg($pyapiEchoScript))) ?: 'http://127.0.0.1:8009', '/');

        $collections = [['name' => 'sage_kg_nodes_content', 'weight' => 1.0, 'n' => $nResults],['name' => 'sage_kg_nodes_meta',    'weight' => 0.6, 'n' => (int)ceil($nResults * 0.6)],
        ];

        $scoreMap = [];

        foreach ($collections as $coll) {
            $payload = json_encode([
                'text'       => $query,
                'collection' => $coll['name'],
                'n_results'  => $coll['n'],
                'modality'   => 'text',
            ]);

            $ch = curl_init($pyapiUrl . '/chroma/query_json');
            curl_setopt_array($ch,[
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$resp) continue;

            $data = json_decode($resp, true);
            if (empty($data['ok']) || empty($data['result'])) continue;

            $result    = $data['result'];
            $ids       = $result['ids'][0]       ??[];
            $distances = $result['distances'][0] ?? [];
            $metas     = $result['metadatas'][0] ?? [];
            $docs      = $result['documents'][0] ??[];

            foreach ($ids as $i => $chromaId) {
                $nodeId = (int)($metas[$i]['node_id'] ?? 0);
                if (!$nodeId) continue;

                $distance   = (float)($distances[$i] ?? 1.0);
                $similarity = max(0.0, 1.0 - ($distance / 2.0));
                $weighted   = $similarity * $coll['weight'];

                if (!isset($scoreMap[$nodeId]) || $weighted > $scoreMap[$nodeId]['score']) {
                    $scoreMap[$nodeId] =[
                        'score'   => $weighted,
                        'meta'    => $metas[$i],
                        'excerpt' => mb_substr($docs[$i] ?? '', 0, 200),
                        'source'  => $coll['name'],
                    ];
                }
            }
        }

        if (empty($scoreMap)) {
            kg_json(true, ['hits' => [], 'query' => $query, 'total' => 0]);
        }

        uasort($scoreMap, fn($a, $b) => $b['score'] <=> $a['score']);

        $nodeIds      = array_keys($scoreMap);
        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt = $pdo->prepare("
            SELECT n.id, n.name, n.node_type, n.keywords,
                   c.name AS category_name,
                   CHAR_LENGTH(COALESCE(n.content,'')) AS content_chars
            FROM kg_nodes n
            LEFT JOIN kg_categories c ON c.id = n.category_id
            WHERE n.id IN ($placeholders) AND n.status = 'active'
        ");
        $stmt->execute($nodeIds);
        $dbRows =[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dbRows[(int)$r['id']] = $r;
        }

        $hits =[];
        foreach ($scoreMap as $nodeId => $entry) {
            $db = $dbRows[$nodeId] ?? null;
            if (!$db) continue;

            $chars = (int)$db['content_chars'];
            $contentStatus = match(true) {
                $chars === 0  => 'empty',
                $chars < 200  => 'stub',
                $chars < 600  => 'partial',
                default       => 'filled',
            };

            $hits[] =[
                'node_id'        => $nodeId,
                'score'          => round($entry['score'], 4),
                'name'           => $db['name'],
                'node_type'      => $db['node_type'],
                'category_name'  => $db['category_name'] ?? '',
                'keywords'       => $db['keywords'] ?? '',
                'content_status' => $contentStatus,
                'content_chars'  => $chars,
                'excerpt'        => $entry['excerpt'],
                'source'         => $entry['source'],
            ];
        }

        kg_json(true,['hits' => $hits, 'query' => $query, 'total' => count($hits)]);
    }

    // -------------------------------------------------------
    // FOCUSED SNAPSHOT
    // -------------------------------------------------------
    if ($action === 'focused_snapshot') {
        $nodeIds     = $input['node_ids']     ??[];
        $withContent = !empty($input['with_content']);
        $withEdges   = !isset($input['with_edges']) || !empty($input['with_edges']);

        if (empty($nodeIds) || !is_array($nodeIds)) {
            kg_json(false,[], 'node_ids array required');
        }

        $nodeIds = array_values(array_filter(array_map('intval', $nodeIds)));
        if (empty($nodeIds)) kg_json(false,[], 'No valid node IDs');

        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));

        $cols = $withContent
            ? "id, category_id, name, node_type, keywords, sort_order, status, created_at, updated_at, content,
               CHAR_LENGTH(COALESCE(content,'')) as content_chars"
            : "id, category_id, name, node_type, keywords, sort_order, status, created_at, updated_at,
               CHAR_LENGTH(COALESCE(content,'')) as content_chars";

        $stmt = $pdo->prepare("
            SELECT $cols
            FROM kg_nodes
            WHERE id IN ($placeholders) AND status = 'active'
            ORDER BY category_id ASC, sort_order ASC, name ASC
        ");
        $stmt->execute($nodeIds);
        $nodesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nodeFields = $withContent
            ?['id','category_id','name','node_type','keywords','sort_order','status',
               'created_at','updated_at','content','content_chars','content_words_approx','content_status']
            :['id','category_id','name','node_type','keywords','sort_order','status',
               'created_at','updated_at','content_chars','content_words_approx','content_status'];

        $nodeRows     = [];
        $catIdsNeeded =[];
        foreach ($nodesRaw as $n) {
            $chars  = (int)$n['content_chars'];
            $status = match(true) {
                $chars === 0  => 'empty',
                $chars < 200  => 'stub',
                $chars < 600  => 'partial',
                default       => 'filled',
            };
            if ($n['category_id']) $catIdsNeeded[(int)$n['category_id']] = true;

            $row =[
                (int)$n['id'],
                $n['category_id'] ? (int)$n['category_id'] : null,
                $n['name'],
                $n['node_type'],
                $n['keywords'],
                (int)$n['sort_order'],
                $n['status'],
                $n['created_at'],
                $n['updated_at'],
            ];
            if ($withContent) $row[] = $n['content'] ?? '';
            $row[] = $chars;
            $row[] = $chars > 0 ? (int)round($chars / 5.5) : 0;
            $row[] = $status;
            $nodeRows[] = $row;
        }

        $catRows   = [];
        $catFields =['id','parent_id','name','sort_order'];
        if (!empty($catIdsNeeded)) {
            $catPH   = implode(',', array_fill(0, count($catIdsNeeded), '?'));
            $catStmt = $pdo->prepare("
                SELECT id, parent_id, name, sort_order
                FROM kg_categories WHERE id IN ($catPH)
                ORDER BY sort_order ASC, name ASC
            ");
            $catStmt->execute(array_keys($catIdsNeeded));
            foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $catRows[] = [
                    (int)$c['id'],
                    $c['parent_id'] ? (int)$c['parent_id'] : null,
                    $c['name'],
                    (int)$c['sort_order'],
                ];
            }
        }

        $edgeFields =['id','node_id','item_type','item_id','item_label','relationship','note','sort_order'];
        $edgeRows   =[];
        if ($withEdges) {
            $edgeStmt = $pdo->prepare("
                SELECT id, node_id, item_type, item_id, item_label, relationship, note, sort_order
                FROM kg_node_items
                WHERE node_id IN ($placeholders)
                ORDER BY node_id ASC, sort_order ASC
            ");
            $edgeStmt->execute($nodeIds);

            foreach ($edgeStmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $edgeRows[] =[
                    (int)$e['id'],
                    (int)$e['node_id'],
                    $e['item_type'],
                    $e['item_id'] ? (int)$e['item_id'] : null,
                    $e['item_label'],
                    $e['relationship'],
                    $e['note'],
                    (int)$e['sort_order'],
                ];
            }
        }

        $byStatus   =['empty'=>0,'stub'=>0,'partial'=>0,'filled'=>0];
        $statusIdx  = $withContent ? 12 : 11;
        foreach ($nodeRows as $r) $byStatus[$r[$statusIdx]]++;

        $snapshot = [
            'export_meta' =>[
                'generated_at'           => date('c'),
                'export_type'            => 'focused_snapshot',
                'query_node_ids'         => $nodeIds,
                'with_content'           => $withContent,
                'with_edges'             => $withEdges,
                'columnar_format'        => true,
                'total_categories'       => count($catRows),
                'total_nodes'            => count($nodeRows),
                'total_edges'            => count($edgeRows),
                'content_status_summary' => $byStatus,
            ],
            'categories' =>['fields' => $catFields, 'rows' => $catRows],
            'nodes'      =>['fields' => $nodeFields, 'rows' => $nodeRows],
            'edges'      =>['fields' => $edgeFields, 'rows' => $edgeRows],
        ];

        kg_json(true, ['snapshot' => $snapshot]);
    }
    
    // -------------------------------------------------------
    // GET FUZZ CANDIDATE FOR KG NODE
    // -------------------------------------------------------
    if ($action === 'get_fuzz_candidate_for_node') {
        $nodeId = (int)($input['node_id'] ?? $_GET['node_id'] ?? 0);
        if (!$nodeId) kg_json(false, [], 'Missing node_id');

        $stmt = $pdo->prepare("
            SELECT id, label, status, concept_type, confidence
            FROM fuzz_candidates
            WHERE kg_node_id = ?
              AND status = 'promoted'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$nodeId]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($candidate) {
            kg_json(true, ['candidate' => $candidate]);
        } else {
            kg_json(true, ['candidate' => null]);
        }
    }

    kg_json(false,[], 'Unknown action: ' . $action);

} catch (Exception $e) {
    http_response_code(500);
    kg_json(false,[], $e->getMessage());
}

function kg_node_icon(string $type): string {
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

