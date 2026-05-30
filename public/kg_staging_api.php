<?php
// public/kg_staging_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

function kg_json($ok, $data = [], $error = null) {
    $out = ['ok' => $ok];
    if ($error) $out['error'] = $error;
    foreach ($data as $k => $v) $out[$k] = $v;
    echo json_encode($out);
    exit;
}

$input  = [];
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
    // TREE
    // -------------------------------------------------------
    if ($action === 'fetch_tree') {
        $cats = $pdo->query("SELECT id, parent_id, name FROM kg_staging_categories ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $nodes = $pdo->query("SELECT id, category_id, name, node_type FROM kg_staging_nodes WHERE status='active' ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $tree = [];
        foreach ($cats as $c) {
            $tree[] = [
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
            $tree[] = [
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
    // MOVE NODE (drag & drop)
    // -------------------------------------------------------
    if ($action === 'move_node') {
        $id       = $input['id'] ?? '';
        $parentId = $input['parent'] ?? '#';
        $dbParent = ($parentId !== '#' && str_starts_with($parentId, 'c_')) ? (int)substr($parentId, 2) : null;

        if (str_starts_with($id, 'c_')) {
            $pdo->prepare("UPDATE kg_staging_categories SET parent_id=? WHERE id=?")->execute([$dbParent, (int)substr($id, 2)]);
        } elseif (str_starts_with($id, 'n_')) {
            $pdo->prepare("UPDATE kg_staging_nodes SET category_id=? WHERE id=?")->execute([$dbParent, (int)substr($id, 2)]);
        }
        kg_json(true);
    }

    // -------------------------------------------------------
    // CREATE CATEGORY
    // -------------------------------------------------------
    if ($action === 'create_category') {
        $name     = trim($input['name'] ?? '');
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
        if (!$name) kg_json(false, [], 'Name required');
        $pdo->prepare("INSERT INTO kg_staging_categories (name, parent_id) VALUES (?,?)")->execute([$name, $parentId]);
        kg_json(true, ['id' => $pdo->lastInsertId()]);
    }

    // -------------------------------------------------------
    // RENAME CATEGORY
    // -------------------------------------------------------
    if ($action === 'rename_category') {
        $id   = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id || !$name) kg_json(false, [], 'Missing params');
        $pdo->prepare("UPDATE kg_staging_categories SET name=? WHERE id=?")->execute([$name, $id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // DELETE CATEGORY
    // -------------------------------------------------------
    if ($action === 'delete_category') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) kg_json(false, [], 'Missing id');
        // move orphaned nodes to uncategorized
        $pdo->prepare("UPDATE kg_staging_nodes SET category_id=NULL WHERE category_id=?")->execute([$id]);
        // move orphaned subcategories to root
        $pdo->prepare("UPDATE kg_staging_categories SET parent_id=NULL WHERE parent_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM kg_staging_categories WHERE id=?")->execute([$id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // CREATE NODE
    // -------------------------------------------------------
    if ($action === 'create_node') {
        $name     = trim($input['name'] ?? 'New Node');
        $catId    = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $nodeType = trim($input['node_type'] ?? 'note');
        $pdo->prepare("INSERT INTO kg_staging_nodes (name, category_id, node_type, content) VALUES (?,?,?,?)")
            ->execute([$name, $catId, $nodeType, '']);
        $newId = $pdo->lastInsertId();
        kg_json(true, ['id' => $newId]);
    }

    // -------------------------------------------------------
    // GET NODE
    // -------------------------------------------------------
    if ($action === 'get_node') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) kg_json(false, [], 'Missing id');
        $stmt = $pdo->prepare("SELECT * FROM kg_staging_nodes WHERE id=?");
        $stmt->execute([$id]);
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$node) kg_json(false, [], 'Node not found');

        // Outgoing: edges this node owns
        $outgoing = $pdo->prepare("SELECT *, 'outgoing' AS direction FROM kg_staging_node_items WHERE node_id=? ORDER BY sort_order ASC");
        $outgoing->execute([$id]);

        // Incoming: edges from other nodes that point to this node
        $incoming = $pdo->prepare("
            SELECT kni.*, 'incoming' AS direction, kn.name AS source_node_name, kn.node_type AS source_node_type
            FROM kg_staging_node_items kni
            JOIN kg_staging_nodes kn ON kn.id = kni.node_id
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
            $pdo->prepare("UPDATE kg_staging_nodes SET name=?, content=?, description=?, keywords=?, node_type=?, category_id=?, updated_at=NOW() WHERE id=?")
                ->execute([$name, $content, $description, $keywords, $nodeType, $catId, $id]);
            kg_json(true, ['id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO kg_staging_nodes (name, content, description, keywords, node_type, category_id) VALUES (?,?,?,?,?,?)")
                ->execute([$name, $content, $description, $keywords, $nodeType, $catId]);
            kg_json(true, ['id' => $pdo->lastInsertId()]);
        }
    }

    // -------------------------------------------------------
    // DELETE NODE
    // -------------------------------------------------------
    if ($action === 'delete_node') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) kg_json(false, [], 'Missing id');
        $pdo->prepare("UPDATE kg_staging_nodes SET status='archived' WHERE id=?")->execute([$id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // RENAME NODE (quick rename from tree)
    // -------------------------------------------------------
    if ($action === 'rename_node') {
        $id   = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id || !$name) kg_json(false, [], 'Missing params');
        $pdo->prepare("UPDATE kg_staging_nodes SET name=? WHERE id=?")->execute([$name, $id]);
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
        if (!$nodeId || !$itemType) kg_json(false, [], 'Missing params');
        $max = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM kg_staging_node_items WHERE node_id=?");
        $max->execute([$nodeId]);
        $nextOrder = $max->fetchColumn();
        $pdo->prepare("INSERT INTO kg_staging_node_items (node_id, item_type, item_id, item_label, relationship, note, sort_order) VALUES (?,?,?,?,?,?,?)")
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
        if (!$id) kg_json(false, [], 'Missing id');
        $pdo->prepare("
            UPDATE kg_staging_node_items
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
        if (!$id) kg_json(false, [], 'Missing id');
        $pdo->prepare("DELETE FROM kg_staging_node_items WHERE id=?")->execute([$id]);
        kg_json(true);
    }

    // -------------------------------------------------------
    // SEARCH
    // -------------------------------------------------------
    if ($action === 'search') {
        $q    = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_staging_nodes WHERE status='active' AND (name LIKE ? OR content LIKE ? OR keywords LIKE ?) LIMIT 50");
        $stmt->execute([$q, $q, $q]);
        kg_json(true, ['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // -------------------------------------------------------
    // EXPORT SNAPSHOT (full graph, columnar, no content)
    // -------------------------------------------------------
    if ($action === 'export_snapshot') {
        $withEdges = !isset($_GET['with_edges']) || (int)$_GET['with_edges'] !== 0;

        $catsRaw = $pdo->query("
            SELECT c.id, c.parent_id, c.name, c.sort_order,
                   GROUP_CONCAT(child.id ORDER BY child.sort_order) as child_category_ids
            FROM kg_staging_categories c
            LEFT JOIN kg_staging_categories child ON child.parent_id = c.id
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $catFields = ['id','parent_id','name','sort_order','child_category_ids'];
        $catRows   = [];
        foreach ($catsRaw as $c) {
            $catRows[] = [
                (int)$c['id'],
                $c['parent_id'] ? (int)$c['parent_id'] : null,
                $c['name'],
                (int)$c['sort_order'],
                $c['child_category_ids']
                    ? array_map('intval', explode(',', $c['child_category_ids']))
                    : [],
            ];
        }

        $nodesRaw = $pdo->query("
            SELECT id, category_id, name, node_type, keywords,
                   sort_order, status, created_at, updated_at,
                   CHAR_LENGTH(COALESCE(content,'')) as content_chars
            FROM kg_staging_nodes
            WHERE status = 'active'
            ORDER BY category_id ASC, sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $nodeFields = ['id','category_id','name','node_type','keywords',
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

        $edgeFields = ['id','node_id','item_type','item_id','item_label','relationship','note','sort_order'];
        $edgeRows   = [];
        if ($withEdges) {
            $edgesRaw = $pdo->query("
                SELECT id, node_id, item_type, item_id, item_label, relationship, note, sort_order
                FROM kg_staging_node_items
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
        $byStatus   = ['empty'=>0,'stub'=>0,'partial'=>0,'filled'=>0];
        foreach ($nodeRows as $r) $byStatus[$r[11]]++;

        $snapshot = [
            'export_meta' => [
                'generated_at'           => date('c'),
                'source'                 => 'kg_staging',
                'schema_tables'          => ['kg_staging_categories','kg_staging_nodes','kg_staging_node_items'],
                'columnar_format'        => true,
                'columnar_note'          => 'Each section has a "fields" array (column names, declared once) and a "rows" array (value arrays in matching order). No key repetition per row.',
                'total_categories'       => count($catRows),
                'total_nodes'            => $totalNodes,
                'total_edges'            => count($edgeRows),
                'with_edges'             => $withEdges,
                'content_status_summary' => $byStatus,
                'note'                   => 'Content text excluded. Use content_chars/content_status to identify gaps.',
            ],
            'categories' => ['fields' => $catFields, 'rows' => $catRows],
            'nodes'      => ['fields' => $nodeFields, 'rows' => $nodeRows],
            'edges'      => ['fields' => $edgeFields, 'rows' => $edgeRows],
        ];

        kg_json(true, ['snapshot' => $snapshot]);
    }

    // -------------------------------------------------------
    // SEMANTIC QUERY
    // -------------------------------------------------------
    if ($action === 'semantic_query') {
        $query    = trim($input['query'] ?? '');
        $nResults = min((int)($input['n_results'] ?? 20), 60);
        if (!$query) kg_json(false, [], 'Query required');

        $pyapiEchoScript = dirname(__DIR__) . '/bash/pyapi_echo.sh';
        $pyapiUrl = rtrim(trim(shell_exec('sh ' . escapeshellarg($pyapiEchoScript))) ?: 'http://127.0.0.1:8009', '/');

        $collections = [
            ['name' => 'sage_kg_nodes_content', 'weight' => 1.0, 'n' => $nResults],
            ['name' => 'sage_kg_nodes_meta',    'weight' => 0.6, 'n' => (int)ceil($nResults * 0.6)],
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
            curl_setopt_array($ch, [
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
            $ids       = $result['ids'][0]       ?? [];
            $distances = $result['distances'][0] ?? [];
            $metas     = $result['metadatas'][0] ?? [];
            $docs      = $result['documents'][0] ?? [];

            foreach ($ids as $i => $chromaId) {
                $nodeId = (int)($metas[$i]['node_id'] ?? 0);
                if (!$nodeId) continue;

                $distance   = (float)($distances[$i] ?? 1.0);
                $similarity = max(0.0, 1.0 - ($distance / 2.0));
                $weighted   = $similarity * $coll['weight'];

                if (!isset($scoreMap[$nodeId]) || $weighted > $scoreMap[$nodeId]['score']) {
                    $scoreMap[$nodeId] = [
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
            FROM kg_staging_nodes n
            LEFT JOIN kg_staging_categories c ON c.id = n.category_id
            WHERE n.id IN ($placeholders) AND n.status = 'active'
        ");
        $stmt->execute($nodeIds);
        $dbRows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dbRows[(int)$r['id']] = $r;
        }

        $hits = [];
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

            $hits[] = [
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

        kg_json(true, ['hits' => $hits, 'query' => $query, 'total' => count($hits)]);
    }

    // -------------------------------------------------------
    // FOCUSED SNAPSHOT
    // -------------------------------------------------------
    if ($action === 'focused_snapshot') {
        $nodeIds     = $input['node_ids']     ?? [];
        $withContent = !empty($input['with_content']);
        $withEdges   = !isset($input['with_edges']) || !empty($input['with_edges']);

        if (empty($nodeIds) || !is_array($nodeIds)) {
            kg_json(false, [], 'node_ids array required');
        }

        $nodeIds = array_values(array_filter(array_map('intval', $nodeIds)));
        if (empty($nodeIds)) kg_json(false, [], 'No valid node IDs');

        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));

        $cols = $withContent
            ? "id, category_id, name, node_type, keywords, sort_order, status, created_at, updated_at, content,
               CHAR_LENGTH(COALESCE(content,'')) as content_chars"
            : "id, category_id, name, node_type, keywords, sort_order, status, created_at, updated_at,
               CHAR_LENGTH(COALESCE(content,'')) as content_chars";

        $stmt = $pdo->prepare("
            SELECT $cols
            FROM kg_staging_nodes
            WHERE id IN ($placeholders) AND status = 'active'
            ORDER BY category_id ASC, sort_order ASC, name ASC
        ");
        $stmt->execute($nodeIds);
        $nodesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nodeFields = $withContent
            ? ['id','category_id','name','node_type','keywords','sort_order','status',
               'created_at','updated_at','content','content_chars','content_words_approx','content_status']
            : ['id','category_id','name','node_type','keywords','sort_order','status',
               'created_at','updated_at','content_chars','content_words_approx','content_status'];

        $nodeRows     = [];
        $catIdsNeeded = [];
        foreach ($nodesRaw as $n) {
            $chars  = (int)$n['content_chars'];
            $status = match(true) {
                $chars === 0  => 'empty',
                $chars < 200  => 'stub',
                $chars < 600  => 'partial',
                default       => 'filled',
            };
            if ($n['category_id']) $catIdsNeeded[(int)$n['category_id']] = true;

            $row = [
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
        $catFields = ['id','parent_id','name','sort_order'];
        if (!empty($catIdsNeeded)) {
            $catPH   = implode(',', array_fill(0, count($catIdsNeeded), '?'));
            $catStmt = $pdo->prepare("
                SELECT id, parent_id, name, sort_order
                FROM kg_staging_categories WHERE id IN ($catPH)
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

        $edgeFields = ['id','node_id','item_type','item_id','item_label','relationship','note','sort_order'];
        $edgeRows   = [];
        if ($withEdges) {
            $edgeStmt = $pdo->prepare("
                SELECT id, node_id, item_type, item_id, item_label, relationship, note, sort_order
                FROM kg_staging_node_items
                WHERE node_id IN ($placeholders)
                ORDER BY node_id ASC, sort_order ASC
            ");
            $edgeStmt->execute($nodeIds);

            foreach ($edgeStmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
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

        $byStatus   = ['empty'=>0,'stub'=>0,'partial'=>0,'filled'=>0];
        $statusIdx  = $withContent ? 12 : 11;
        foreach ($nodeRows as $r) $byStatus[$r[$statusIdx]]++;

        $snapshot = [
            'export_meta' => [
                'generated_at'           => date('c'),
                'export_type'            => 'focused_snapshot',
                'source'                 => 'kg_staging',
                'query_node_ids'         => $nodeIds,
                'with_content'           => $withContent,
                'with_edges'             => $withEdges,
                'columnar_format'        => true,
                'columnar_note'          => 'fields declared once; rows are positional value arrays.',
                'total_categories'       => count($catRows),
                'total_nodes'            => count($nodeRows),
                'total_edges'            => count($edgeRows),
                'content_status_summary' => $byStatus,
                'note'                   => $withContent
                    ? 'Full markdown content included for all selected nodes.'
                    : 'Content text excluded. Use content_chars/content_status to identify gaps.',
            ],
            'categories' => ['fields' => $catFields, 'rows' => $catRows],
            'nodes'      => ['fields' => $nodeFields, 'rows' => $nodeRows],
            'edges'      => ['fields' => $edgeFields, 'rows' => $edgeRows],
        ];

        kg_json(true, ['snapshot' => $snapshot]);
    }

    // -------------------------------------------------------
    // PROMOTE NODES — copy staging nodes (+ their items) into live kg tables
    //
    // Input:
    //   node_ids        int[]  — staging node IDs to promote
    //   with_edges      bool   — also promote kg_staging_node_items (default true)
    //   overwrite       bool   — if a live node with the same name+type exists, update it
    //                            (default false = always INSERT new)
    //
    // Strategy (overwrite=false):
    //   • Each promoted staging node is INSERTed fresh into kg_nodes (new auto-inc id).
    //   • If the staging node belongs to a staging category whose name matches a live
    //     kg_category, the new live node is placed in that category. Otherwise NULL.
    //   • Staging node items whose item_type='kg_node' are NOT remapped (the staging
    //     item_id would point to a staging node, not a live one — caller must reconcile).
    //     All other item types are copied verbatim.
    //   • A mapping of staging_node_id → new_live_node_id is returned.
    // -------------------------------------------------------
    if ($action === 'promote_nodes') {
        $stagingNodeIds = $input['node_ids'] ?? [];
        $withEdges      = !isset($input['with_edges']) || !empty($input['with_edges']);
        $overwrite      = !empty($input['overwrite']);

        if (empty($stagingNodeIds) || !is_array($stagingNodeIds)) {
            kg_json(false, [], 'node_ids array required');
        }
        $stagingNodeIds = array_values(array_filter(array_map('intval', $stagingNodeIds)));
        if (empty($stagingNodeIds)) kg_json(false, [], 'No valid node IDs');

        $placeholders = implode(',', array_fill(0, count($stagingNodeIds), '?'));

        // Fetch staging nodes
        $stmt = $pdo->prepare("
            SELECT sn.*, sc.name AS staging_cat_name
            FROM kg_staging_nodes sn
            LEFT JOIN kg_staging_categories sc ON sc.id = sn.category_id
            WHERE sn.id IN ($placeholders) AND sn.status = 'active'
        ");
        $stmt->execute($stagingNodeIds);
        $stagingNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($stagingNodes)) kg_json(false, [], 'No active staging nodes found for given IDs');

        // Build live category name → id map for fuzzy placement
        $liveCatsRaw = $pdo->query("SELECT id, name FROM kg_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $liveCatMap  = [];
        foreach ($liveCatsRaw as $lc) {
            $liveCatMap[strtolower(trim($lc['name']))] = (int)$lc['id'];
        }

        $pdo->beginTransaction();
        try {
            $idMap       = [];   // staging_id => live_id
            $promoted    = 0;
            $skipped     = 0;

            $insertNode = $pdo->prepare("
                INSERT INTO kg_nodes (name, category_id, node_type, content, description, keywords, status, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $updateNode = $pdo->prepare("
                UPDATE kg_nodes SET content=?, description=?, keywords=?, node_type=?, category_id=?, updated_at=NOW()
                WHERE id=?
            ");
            $checkDupe = $pdo->prepare("
                SELECT id FROM kg_nodes WHERE name=? AND node_type=? AND status='active' LIMIT 1
            ");

            foreach ($stagingNodes as $sn) {
                $liveCatId = null;
                if ($sn['staging_cat_name']) {
                    $key = strtolower(trim($sn['staging_cat_name']));
                    $liveCatId = $liveCatMap[$key] ?? null;
                }

                if ($overwrite) {
                    $checkDupe->execute([$sn['name'], $sn['node_type']]);
                    $existingId = $checkDupe->fetchColumn();
                    if ($existingId) {
                        $updateNode->execute([
                            $sn['content'],
                            $sn['description'],
                            $sn['keywords'],
                            $sn['node_type'],
                            $liveCatId,
                            $existingId,
                        ]);
                        $idMap[(int)$sn['id']] = (int)$existingId;
                        $promoted++;
                        continue;
                    }
                }

                $insertNode->execute([
                    $sn['name'],
                    $liveCatId,
                    $sn['node_type'],
                    $sn['content'],
                    $sn['description'],
                    $sn['keywords'],
                    (int)$sn['sort_order'],
                ]);
                $liveId = (int)$pdo->lastInsertId();
                $idMap[(int)$sn['id']] = $liveId;
                $promoted++;
            }

            // Promote edges
            $promotedEdges = 0;
            if ($withEdges && !empty($idMap)) {
                $stagingIdsPromoted = array_keys($idMap);
                $edgePH   = implode(',', array_fill(0, count($stagingIdsPromoted), '?'));
                $edgeStmt = $pdo->prepare("
                    SELECT * FROM kg_staging_node_items
                    WHERE node_id IN ($edgePH)
                    ORDER BY node_id ASC, sort_order ASC
                ");
                $edgeStmt->execute($stagingIdsPromoted);
                $stagingEdges = $edgeStmt->fetchAll(PDO::FETCH_ASSOC);

                $insertEdge = $pdo->prepare("
                    INSERT INTO kg_node_items (node_id, item_type, item_id, item_label, relationship, note, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($stagingEdges as $se) {
                    $liveNodeId = $idMap[(int)$se['node_id']] ?? null;
                    if (!$liveNodeId) continue;

                    // Remap internal kg_node edges where possible
                    $liveItemId = $se['item_id'];
                    if ($se['item_type'] === 'kg_node' && $se['item_id']) {
                        $liveItemId = $idMap[(int)$se['item_id']] ?? $se['item_id'];
                    }

                    $insertEdge->execute([
                        $liveNodeId,
                        $se['item_type'],
                        $liveItemId,
                        $se['item_label'],
                        $se['relationship'],
                        $se['note'],
                        (int)$se['sort_order'],
                    ]);
                    $promotedEdges++;
                }
            }

            $pdo->commit();

            kg_json(true, [
                'promoted_nodes' => $promoted,
                'promoted_edges' => $promotedEdges,
                'skipped'        => $skipped,
                'id_map'         => $idMap,
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            kg_json(false, [], 'Promote failed: ' . $e->getMessage());
        }
    }

    kg_json(false, [], 'Unknown action: ' . $action);

} catch (Exception $e) {
    http_response_code(500);
    kg_json(false, [], $e->getMessage());
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
