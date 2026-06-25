<?php
// public/api_kg_edge_queue.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\AIProvider;

header('Content-Type: application/json; charset=utf-8');

function kg_edge_json(bool $ok, array $data = [], ?string $error = null): void {
    $out = ['ok' => $ok];
    if ($error !== null) {
        $out['error'] = $error;
    }
    foreach ($data as $k => $v) {
        $out[$k] = $v;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function kg_edge_input(): array {
    $input = [];

    $raw = file_get_contents('php://input');
    if ($raw) {
        $dec = json_decode($raw, true);
        if (is_array($dec)) {
            $input = $dec;
        }
    }

    if (!empty($_POST) && is_array($_POST)) {
        $input = array_merge($input, $_POST);
    }

    return $input;
}

function kg_edge_excerpt(string $text, int $maxChars = 180): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $maxChars) {
        return $text;
    }
    return mb_substr($text, 0, $maxChars - 1) . '…';
}

function kg_edge_log(PDO $pdo, int $runId, string $stepKey, string $message, array $context = []): void {
    $stmt = $pdo->prepare("
        INSERT INTO kg_edge_run_logs (run_id, step_key, message, context_text)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $runId,
        $stepKey,
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null
    ]);
}

function kg_edge_select_focal_node(PDO $pdo, array $poolIds = []): ?array {
    $where = "n.status = 'active' AND (CHAR_LENGTH(COALESCE(n.content, '')) > 20 OR CHAR_LENGTH(COALESCE(n.description, '')) > 20)";
    $params = [];

    if (!empty($poolIds)) {
        $placeholders = implode(',', array_fill(0, count($poolIds), '?'));
        $where .= " AND n.id IN ($placeholders)";
        $params = $poolIds;
    }

    $stmt = $pdo->prepare("
        SELECT n.id, n.name, n.node_type,
               COALESCE(n.content, '') AS content,
               COALESCE(n.description, '') AS description,
               COALESCE(n.keywords, '') AS keywords,
               CHAR_LENGTH(COALESCE(n.content, '')) AS content_chars,
               CHAR_LENGTH(COALESCE(n.description, '')) AS description_chars
        FROM kg_nodes n
        WHERE $where
          AND NOT EXISTS (
              SELECT 1
              FROM kg_edge_proposals p
              WHERE p.focal_node_id = n.id
                AND p.status = 'pending'
          )
        ORDER BY
            (
                SELECT MAX(created_at)
                FROM kg_edge_runs
                WHERE focal_node_id = n.id
            ) IS NOT NULL,
            (
                SELECT MAX(created_at)
                FROM kg_edge_runs
                WHERE focal_node_id = n.id
            ) ASC,
            RAND()
        LIMIT 1
    ");

    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function kg_edge_load_run(PDO $pdo, string $runUuid): ?array {
    $stmt = $pdo->prepare("SELECT * FROM kg_edge_runs WHERE run_uuid = ? LIMIT 1");
    $stmt->execute([$runUuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function kg_edge_load_config(PDO $pdo): ?array {
    $stmt = $pdo->prepare("SELECT * FROM generator_config WHERE config_id = 'kg_edge_finder' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function kg_edge_is_offline(PDO $pdo): bool {
    $stmt = $pdo->query("SELECT is_offline FROM kg_edge_offline_state WHERE id = 1 LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (bool)(int)$row['is_offline'] : false;
}

function kg_edge_parse_ai_json(string $response): ?array {
    $response = trim($response);

    $response = preg_replace('/^```(?:json)?\s*/i', '', $response);
    $response = preg_replace('/\s*```$/', '', $response);
    $response = trim($response);

    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $startObj = strpos($response, '{');
    $endObj   = strrpos($response, '}');
    if ($startObj !== false && $endObj !== false && $endObj > $startObj) {
        $slice = substr($response, $startObj, $endObj - $startObj + 1);
        $decoded = json_decode($slice, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $startArr = strpos($response, '[');
    $endArr   = strrpos($response, ']');
    if ($startArr !== false && $endArr !== false && $endArr > $startArr) {
        $slice = substr($response, $startArr, $endArr - $startArr + 1);
        $decoded = json_decode($slice, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function kg_edge_build_state(PDO $pdo, array $runRow): array {
    $runId = (int)$runRow['id'];

    $logStmt = $pdo->prepare("
        SELECT step_key, message, context_text, created_at
        FROM kg_edge_run_logs
        WHERE run_id = ?
        ORDER BY id ASC
    ");
    $logStmt->execute([$runId]);
    $logs = [];
    foreach ($logStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $logs[] = [
            'ts'      => $row['created_at'],
            'step'    => $row['step_key'],
            'message' => $row['message'],
            'context' => $row['context_text'] ? (json_decode($row['context_text'], true) ?: $row['context_text']) : null,
        ];
    }

    $candStmt = $pdo->prepare("
        SELECT node_id, target_name, node_type, category_name, keywords,
               content_status, content_chars, score, excerpt, source, sort_order
        FROM kg_edge_run_candidates
        WHERE run_id = ?
        ORDER BY sort_order ASC, score DESC, node_id ASC
    ");
    $candStmt->execute([$runId]);
    $candidates = $candStmt->fetchAll(PDO::FETCH_ASSOC);

    $aiStmt = $pdo->prepare("
        SELECT target_node_id, target_name, relationship_label, rationale, created_at, sort_order
        FROM kg_edge_run_ai_edges
        WHERE run_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $aiStmt->execute([$runId]);
    $aiEdges = $aiStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'run_id' => $runRow['run_uuid'],
        'status' => $runRow['status'],
        'step' => (int)$runRow['step'],
        'step_label' => $runRow['step_label'],
        'created_at' => $runRow['created_at'],
        'updated_at' => $runRow['updated_at'],
        'focal_node' => [
            'id'            => (int)$runRow['focal_node_id'],
            'name'          => $runRow['focal_node_name'],
            'node_type'     => $runRow['focal_node_type'],
            'content_chars' => (int)$runRow['focal_text_chars'],
            'snippet'       => $runRow['focal_snippet'] ?? '',
        ],
        'candidate_pack' => [
            'total' => count($candidates),
            'hits'  => array_slice($candidates, 0, 12),
        ],
        'ai' => [
            'model' => $runRow['ai_model'],
            'prompt_chars' => (int)($runRow['ai_prompt_chars'] ?? 0),
            'response_excerpt' => $runRow['ai_response_excerpt'] ?? '',
            'error' => $runRow['ai_error'] ?? null,
            'parsed_edges' => count($aiEdges),
            'summary' => [
                'top_valid' => array_slice($aiEdges, 0, 15),
            ],
        ],
        'logs' => array_slice($logs, -40),
        'result' => [
            'inserted' => (int)($runRow['result_inserted'] ?? 0),
            'skipped'  => (int)($runRow['result_skipped'] ?? 0),
            'message'  => $runRow['message'] ?? null,
        ],
    ];
}

function kg_edge_compact_pack(PDO $pdo, array $focalNode, int $nResults = 12, int $maxExcerpt = 1000): array {
    $focalText = trim(
        $focalNode['name'] . "\n\n" .
        ($focalNode['description'] ?? '') . "\n\n" .
        ($focalNode['keywords'] ?? '') . "\n\n" .
        mb_substr((string)($focalNode['content'] ?? ''), 0, 6000)
    );

    if ($focalText === '') {
        return [
            'ok' => false,
            'message' => 'Focal node has no usable text.'
        ];
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
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
            if (!$candNodeId || $candNodeId === (int)$focalNode['id']) {
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
        return [
            'ok' => true,
            'focal_node' => [
                'id'            => (int)$focalNode['id'],
                'name'          => $focalNode['name'],
                'node_type'     => $focalNode['node_type'],
                'content_chars' => (int)($focalNode['content_chars'] ?? 0),
            ],
            'hits' => [],
            'total' => 0,
            'focal_text_chars' => mb_strlen($focalText),
            'focal_text' => $focalText,
        ];
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
    $sort = 1;
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
            'sort_order'    => $sort++,
            'node_id'       => $candNodeId,
            'score'         => round($entry['score'], 4),
            'name'          => $db['name'],
            'node_type'     => $db['node_type'],
            'category_name' => $db['category_name'] ?? '',
            'keywords'      => $db['keywords'] ?? '',
            'content_status'=> $contentStatus,
            'content_chars' => $chars,
            'excerpt'       => $entry['excerpt'],
            'source'         => $entry['source'],
        ];
    }

    return [
        'ok' => true,
        'focal_node' => [
            'id'            => (int)$focalNode['id'],
            'name'          => $focalNode['name'],
            'node_type'     => $focalNode['node_type'],
            'content_chars' => (int)($focalNode['content_chars'] ?? 0),
        ],
        'hits'  => $hits,
        'total' => count($hits),
        'focal_text_chars' => mb_strlen($focalText),
        'focal_text' => $focalText,
    ];
}

function kg_edge_pot_pack(PDO $pdo, array $focalNode, array $potNodeIds, int $maxExcerpt = 1000): array {
    $potNodeIds = array_values(array_filter($potNodeIds, fn($id) => (int)$id !== (int)$focalNode['id']));

    if (empty($potNodeIds)) {
        return [
            'ok'    => true,
            'focal_node' => [
                'id'            => (int)$focalNode['id'],
                'name'          => $focalNode['name'],
                'node_type'     => $focalNode['node_type'],
                'content_chars' => (int)($focalNode['content_chars'] ?? 0),
            ],
            'hits'  => [],
            'total' => 0,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($potNodeIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            n.id,
            n.name,
            n.node_type,
            n.keywords,
            c.name AS category_name,
            COALESCE(n.description, '') AS description,
            CHAR_LENGTH(COALESCE(n.content, '')) AS content_chars,
            COALESCE(n.content, '') AS content_preview
        FROM kg_nodes n
        LEFT JOIN kg_categories c ON c.id = n.category_id
        WHERE n.id IN ($placeholders)
          AND n.status = 'active'
        ORDER BY n.name ASC
    ");
    $stmt->execute($potNodeIds);

    $hits = [];
    $sort = 1;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $chars = (int)$r['content_chars'];
        $contentStatus = match (true) {
            $chars === 0  => 'empty',
            $chars < 200  => 'stub',
            $chars < 600  => 'partial',
            default       => 'filled',
        };

        $excerptSource = trim(($r['description'] ?? '') . " " . ($r['keywords'] ?? '') . " " . mb_substr($r['content_preview'] ?? '', 0, $maxExcerpt));
        $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags($excerptSource)));
        if (mb_strlen($excerpt) > $maxExcerpt) {
            $excerpt = mb_substr($excerpt, 0, $maxExcerpt - 1) . '…';
        }

        $hits[] = [
            'sort_order'     => $sort++,
            'node_id'        => (int)$r['id'],
            'score'          => 1.0,
            'name'           => $r['name'],
            'node_type'      => $r['node_type'],
            'category_name'  => $r['category_name'] ?? '',
            'keywords'       => $r['keywords'] ?? '',
            'content_status' => $contentStatus,
            'content_chars'  => $chars,
            'excerpt'        => $excerpt,
            'source'         => 'target_pot',
        ];
    }

    return [
        'ok' => true,
        'focal_node' => [
            'id'            => (int)$focalNode['id'],
            'name'          => $focalNode['name'],
            'node_type'     => $focalNode['node_type'],
            'content_chars' => (int)($focalNode['content_chars'] ?? 0),
        ],
        'hits'  => $hits,
        'total' => count($hits),
    ];
}

$input  = kg_edge_input();
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    // --------------------------------------------------------------
    // FETCH MINI GRAPH (for Target Pot graph modal)
    // --------------------------------------------------------------
    if ($action === 'fetch_mini_graph') {
        $nodeId = (int)($input['node_id'] ?? 0);
        $hops   = max(1, min(4, (int)($input['hops'] ?? 1)));
        if ($nodeId <= 0) {
            kg_edge_json(false, [], 'Invalid node_id');
        }

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
                $edges[] = [
                    'id'           => (int)$e['id'],
                    'source'       => (int)$e['source'],
                    'target'       => (int)$e['target'],
                    'relationship' => $e['relationship'] ?? '',
                    'item_label'   => $e['item_label'] ?? '',
                ];
            }
        }

        kg_edge_json(true, ['nodes' => $nodes, 'edges' => $edges]);
    }

    // --------------------------------------------------------------
    // GET / SET OFFLINE MODE
    // --------------------------------------------------------------
    if ($action === 'get_offline_mode') {
        kg_edge_json(true, ['offline' => kg_edge_is_offline($pdo)]);
    }

    if ($action === 'set_offline_mode') {
        $offline = !empty($input['offline']) ? 1 : 0;
        $pdo->prepare("
            INSERT INTO kg_edge_offline_state (id, is_offline) VALUES (1, ?)
            ON DUPLICATE KEY UPDATE is_offline = VALUES(is_offline)
        ")->execute([$offline]);
        kg_edge_json(true, ['offline' => (bool)$offline]);
    }

    // --------------------------------------------------------------
    // FETCH QUEUE
    // --------------------------------------------------------------
    if ($action === 'fetch_queue') {
        $stmt = $pdo->query("
            SELECT id, run_uuid, focal_node_id, focal_node_name, status, step, step_label, ai_error, created_at, message,
                   offline_requested_at, offline_ingested_at
            FROM kg_edge_runs 
            ORDER BY FIELD(status, 'running', 'queued', 'awaiting_offline', 'error', 'completed'), id DESC 
            LIMIT 60
        ");
        $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        kg_edge_json(true, ['queue' => $runs, 'offline' => kg_edge_is_offline($pdo)]);
    }

    // --------------------------------------------------------------
    // QUEUE ACTION
    // --------------------------------------------------------------
    if ($action === 'queue_action') {
        $cmd = $input['cmd'] ?? '';
        $runId = $input['run_id'] ?? '';
        
        if ($cmd === 'delete') {
            $pdo->prepare("DELETE FROM kg_edge_runs WHERE run_uuid = ?")->execute([$runId]);
        } elseif ($cmd === 'reset') {
            $pdo->prepare("UPDATE kg_edge_runs SET status='queued', step=0, step_label='Queued', ai_error=NULL, ai_response_excerpt=NULL, offline_requested_at=NULL, offline_ingested_at=NULL WHERE run_uuid = ?")->execute([$runId]);
        }
        kg_edge_json(true, []);
    }

    // --------------------------------------------------------------
    // ENQUEUE BATCH
    // --------------------------------------------------------------
    if ($action === 'enqueue_batch') {
        $nodeIds = $input['node_ids'] ?? [];
        if (!is_array($nodeIds)) $nodeIds = [];
        $nodeIds = array_values(array_filter(array_map('intval', $nodeIds)));

        $potNodeIds = $input['pot_node_ids'] ?? [];
        if (!is_array($potNodeIds)) $potNodeIds = [];
        $potNodeIds = array_values(array_filter(array_map('intval', $potNodeIds)));

        // If pool is empty, explicitly query 10 random eligible nodes
        if (empty($nodeIds)) {
            $stmt = $pdo->query("
                SELECT n.id 
                FROM kg_nodes n 
                WHERE n.status = 'active' 
                  AND (CHAR_LENGTH(COALESCE(n.content, '')) > 20 OR CHAR_LENGTH(COALESCE(n.description, '')) > 20)
                  AND NOT EXISTS (
                      SELECT 1 FROM kg_edge_proposals p WHERE p.focal_node_id = n.id AND p.status = 'pending'
                  )
                ORDER BY RAND() LIMIT 10
            ");
            $nodeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($nodeIds)) {
                kg_edge_json(false, [], 'No eligible focal nodes found in the graph.');
            }
        }

        $enqueued = 0;
        foreach ($nodeIds as $nid) {
            // Directly query the node instead of re-filtering so that manual explicit selections are always respected
            $stmt = $pdo->prepare("
                SELECT id, name, node_type, content, description, keywords,
                       CHAR_LENGTH(COALESCE(content, '')) AS content_chars
                FROM kg_nodes
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$nid]);
            $focalNode = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$focalNode) continue;

            $focalText = trim(
                $focalNode['name'] . "\n\n" .
                ($focalNode['description'] ?? '') . "\n\n" .
                ($focalNode['keywords'] ?? '') . "\n\n" .
                mb_substr((string)($focalNode['content'] ?? ''), 0, 6000)
            );

            $runUuid = bin2hex(random_bytes(16));
            $msg = !empty($potNodeIds) ? json_encode(['pot_node_ids' => $potNodeIds]) : null;

            $insertStmt = $pdo->prepare("
                INSERT INTO kg_edge_runs
                    (run_uuid, focal_node_id, focal_node_name, focal_node_type, focal_text_chars, focal_snippet, status, step, step_label, message)
                VALUES
                    (?, ?, ?, ?, ?, ?, 'queued', 0, 'Queued', ?)
            ");
            $insertStmt->execute([
                $runUuid,
                (int)$focalNode['id'],
                $focalNode['name'],
                $focalNode['node_type'],
                mb_strlen($focalText),
                kg_edge_excerpt($focalText, 220),
                $msg
            ]);
            
            $runId = (int)$pdo->lastInsertId();
            kg_edge_log($pdo, $runId, 'queued', 'Run added to queue.', [
                'focal_node_id' => (int)$focalNode['id'],
                'focal_node'    => $focalNode['name'],
                'pot_node_count' => count($potNodeIds)
            ]);
            $enqueued++;
        }

        kg_edge_json(true, ['enqueued' => $enqueued]);
    }

    // --------------------------------------------------------------
    // ADVANCE BATCH
    // --------------------------------------------------------------
    if ($action === 'advance_batch') {
        $runUuid = trim((string)($input['run_id'] ?? ''));
        if ($runUuid === '') {
            kg_edge_json(false, [], 'Missing run_id');
        }

        $runRow = kg_edge_load_run($pdo, $runUuid);
        if (!$runRow) {
            kg_edge_json(false, [], 'Run not found');
        }

        if ($runRow['status'] !== 'running' && $runRow['status'] !== 'queued') {
            kg_edge_json(true, [
                'state' => kg_edge_build_state($pdo, $runRow)
            ]);
        }

        $runId = (int)$runRow['id'];
        $step  = (int)$runRow['step'];

        // If it was queued, immediately move to running + step 1
        if ($step === 0) {
             $pdo->prepare("UPDATE kg_edge_runs SET status='running', step=1, step_label='Retrieval' WHERE id=?")->execute([$runId]);
             $runRow = kg_edge_load_run($pdo, $runUuid);
             $step = 1;
             kg_edge_log($pdo, $runId, 'start', 'Run started from queue.', []);
        }

        // STEP 1: retrieval (chroma OR target pot)
        if ($step === 1) {
            $focalNode = [
                'id'          => (int)$runRow['focal_node_id'],
                'name'        => $runRow['focal_node_name'],
                'node_type'   => $runRow['focal_node_type'],
                'content'     => $runRow['focal_snippet'] ?? '',
                'description' => '',
                'keywords'    => '',
                'content_chars' => (int)$runRow['focal_text_chars'],
            ];

            $pdo->prepare("DELETE FROM kg_edge_run_candidates WHERE run_id = ?")->execute([$runId]);

            $potNodeIds = [];
            if (!empty($runRow['message'])) {
                $msgData = json_decode($runRow['message'], true);
                if (is_array($msgData) && !empty($msgData['pot_node_ids'])) {
                    $potNodeIds = array_values(array_filter(array_map('intval', $msgData['pot_node_ids'])));
                }
            }

            $usePot = !empty($potNodeIds);

            if ($usePot) {
                kg_edge_log($pdo, $runId, 'retrieval', 'Building candidate pack from Target Pot.', [
                    'node_id'       => (int)$focalNode['id'],
                    'pot_count'     => count($potNodeIds),
                    'retrieval_mode' => 'target_pot',
                ]);

                $pack = kg_edge_pot_pack($pdo, $focalNode, $potNodeIds, 1000);
            } else {
                kg_edge_log($pdo, $runId, 'retrieval', 'Building compact candidate pack via Chroma.', [
                    'node_id' => (int)$focalNode['id'],
                    'retrieval_mode' => 'chroma',
                ]);

                $pack = kg_edge_compact_pack($pdo, $focalNode, 12, 1000);
            }

            if (!empty($pack['hits'])) {
                $ins = $pdo->prepare("
                    INSERT INTO kg_edge_run_candidates
                        (run_id, sort_order, node_id, target_name, node_type, category_name, keywords, content_status, content_chars, score, excerpt, source)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($pack['hits'] as $hit) {
                    $ins->execute([
                        $runId,
                        (int)$hit['sort_order'],
                        (int)$hit['node_id'],
                        $hit['name'],
                        $hit['node_type'],
                        $hit['category_name'],
                        $hit['keywords'],
                        $hit['content_status'],
                        (int)$hit['content_chars'],
                        (float)$hit['score'],
                        $hit['excerpt'],
                        $hit['source'],
                    ]);
                }

                $pdo->prepare("
                    UPDATE kg_edge_runs
                    SET candidate_count = ?, step = 2, step_label = 'AI reasoning'
                    WHERE id = ?
                ")->execute([count($pack['hits']), $runId]);

                kg_edge_log($pdo, $runId, 'retrieval', 'Candidate pack ready.', [
                    'hits' => count($pack['hits']),
                    'retrieval_mode' => $usePot ? 'target_pot' : 'chroma',
                    'top_hit' => [
                        'node_id' => $pack['hits'][0]['node_id'] ?? null,
                        'name'    => $pack['hits'][0]['name'] ?? null,
                        'score'   => $pack['hits'][0]['score'] ?? null,
                        'snippet' => kg_edge_excerpt((string)($pack['hits'][0]['excerpt'] ?? ''), 100),
                    ],
                ]);

                $runRow = kg_edge_load_run($pdo, $runUuid);
                kg_edge_json(true, ['state' => kg_edge_build_state($pdo, $runRow)]);
            }

            $pdo->prepare("
                UPDATE kg_edge_runs
                SET status = 'completed',
                    step = 4,
                    step_label = 'Done',
                    message = 'No candidates found.',
                    completed_at = NOW()
                WHERE id = ?
            ")->execute([$runId]);

            kg_edge_log($pdo, $runId, 'retrieval', 'No candidates found.', [
                'retrieval_mode' => $usePot ? 'target_pot' : 'chroma',
            ]);
            $runRow = kg_edge_load_run($pdo, $runUuid);
            kg_edge_json(true, ['state' => kg_edge_build_state($pdo, $runRow)]);
        }

        // STEP 2: AI reasoning
        if ($step === 2) {

            // OFFLINE MODE GATE: if offline mode is active, do not call the
            // AI API. Park the job in 'awaiting_offline' so the user can
            // export the request, run it externally, then ingest the answer.
            if (kg_edge_is_offline($pdo)) {
                $pdo->prepare("
                    UPDATE kg_edge_runs
                    SET status = 'awaiting_offline',
                        step_label = 'Awaiting offline answer',
                        offline_requested_at = NOW()
                    WHERE id = ?
                ")->execute([$runId]);

                kg_edge_log($pdo, $runId, 'offline', 'Offline mode active — job parked awaiting manual AI answer.', []);

                $runRow = kg_edge_load_run($pdo, $runUuid);
                kg_edge_json(true, ['state' => kg_edge_build_state($pdo, $runRow)]);
            }

            $config = kg_edge_load_config($pdo);
            if (!$config) {
                $pdo->prepare("
                    UPDATE kg_edge_runs
                    SET status = 'error', step_label = 'Config missing', ai_error = ?, completed_at = NOW()
                    WHERE id = ?
                ")->execute(['Missing kg_edge_finder configuration in database.', $runId]);

                kg_edge_log($pdo, $runId, 'ai', 'Missing kg_edge_finder configuration.', []);
                $runRow = kg_edge_load_run($pdo, $runUuid);
                kg_edge_json(false, ['state' => kg_edge_build_state($pdo, $runRow)], 'Missing kg_edge_finder configuration in database.');
            }

            $candStmt = $pdo->prepare("
                SELECT node_id, target_name, node_type, category_name, keywords,
                       content_status, content_chars, score, excerpt, source, sort_order
                FROM kg_edge_run_candidates
                WHERE run_id = ?
                ORDER BY sort_order ASC, score DESC, node_id ASC
            ");
            $candStmt->execute([$runId]);
            $hits = $candStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch existing edges to pass to AI so it avoids duplicates
            $existStmt = $pdo->prepare("SELECT item_id, item_label, relationship FROM kg_node_items WHERE node_id = ? AND item_type = 'kg_node'");
            $existStmt->execute([(int)$runRow['focal_node_id']]);
            $existingEdges = $existStmt->fetchAll(PDO::FETCH_ASSOC);

            // Query the FULL lore fresh from the DB to give AI maximum context (snippet is strictly for UI)
            $focalStmt = $pdo->prepare("
                SELECT content, description, keywords 
                FROM kg_nodes 
                WHERE id = ?
            ");
            $focalStmt->execute([(int)$runRow['focal_node_id']]);
            $fRow = $focalStmt->fetch(PDO::FETCH_ASSOC);

            $fullLore = trim(
                ($fRow['description'] ?? '') . "\n\n" . 
                ($fRow['keywords'] ?? '') . "\n\n" . 
                mb_substr((string)($fRow['content'] ?? ''), 0, 6000)
            );

            $focalNode = [
                'id'             => (int)$runRow['focal_node_id'],
                'name'           => $runRow['focal_node_name'],
                'node_type'      => $runRow['focal_node_type'],
                'full_lore'      => $fullLore,
                'existing_edges' => $existingEdges
            ];

            // Re-map node_id explicitly to target_node_id for the JSON Schema 
            $formattedHits = [];
            foreach ($hits as $h) {
                $formattedHits[] = [
                    'target_node_id' => (int)$h['node_id'],
                    'target_name'    => $h['target_name'],
                    'node_type'      => $h['node_type'],
                    'category_name'  => $h['category_name'],
                    'keywords'       => $h['keywords'],
                    'excerpt'        => $h['excerpt'],
                    'score'          => $h['score']
                ];
            }

            $aiPayload = [
                'focal_node' => $focalNode,
                'hits'       => $formattedHits,
            ];

            $sysPrompt = $config['system_role'] . "\n\n" . implode("\n", json_decode($config['instructions'], true) ?: []);
            $sysPrompt .= "\n\nReturn raw JSON only. Do not wrap the response in markdown fences. Do not include commentary. Only choose target_node_id values from the provided hits.";

            $userPrompt = "Analyze this compact knowledge graph candidate pack and propose only meaningful missing edges. "
                . "Only use target_node_id values that exist in the hits array. "
                . "Return strict JSON only, with no markdown fences or commentary.\n\n"
                . json_encode($aiPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $params = json_decode($config['parameters'], true);
            if (!is_array($params)) {
                $params = [];
            }

            $schema = json_decode($config['output_schema'], true);
            if (!is_array($schema)) {
                $schema = [];
            }

            $pdo->prepare("
                UPDATE kg_edge_runs
                SET ai_model = ?, ai_prompt_chars = ?, ai_response_excerpt = NULL, ai_error = NULL
                WHERE id = ?
            ")->execute([
                $config['model'],
                mb_strlen($userPrompt),
                $runId
            ]);

            kg_edge_log($pdo, $runId, 'ai', 'AI call started.', [
                'model' => $config['model'],
                'prompt_chars' => mb_strlen($userPrompt),
                'candidate_count' => count($hits),
            ]);

            $ai = new AIProvider();

            try {
                $response = $ai->sendPrompt(
                    $config['model'],
                    $userPrompt,
                    $sysPrompt,
                    $params,
                    $schema
                );
            } catch (Throwable $e) {
                $pdo->prepare("
                    UPDATE kg_edge_runs
                    SET status = 'error', step_label = 'AI error', ai_error = ?, completed_at = NOW()
                    WHERE id = ?
                ")->execute([$e->getMessage(), $runId]);

                kg_edge_log($pdo, $runId, 'ai', 'AI call failed.', ['error' => $e->getMessage()]);
                $runRow = kg_edge_load_run($pdo, $runUuid);
                kg_edge_json(false, ['state' => kg_edge_build_state($pdo, $runRow)], $e->getMessage());
            }

            $excerpt = kg_edge_excerpt((string)$response, 500);
            $pdo->prepare("
                UPDATE kg_edge_runs
                SET ai_response_excerpt = ?, step = 3, step_label = 'Saving proposals'
                WHERE id = ?
            ")->execute([$excerpt, $runId]);

            $parsed = kg_edge_parse_ai_json((string)$response);
            if (!is_array($parsed)) {
                $pdo->prepare("
                    UPDATE kg_edge_runs
                    SET status = 'error', step_label = 'Parse error', ai_error = ?, completed_at = NOW()
                    WHERE id = ?
                ")->execute(['AI response was not valid JSON.', $runId]);

                kg_edge_log($pdo, $runId, 'ai', 'Could not parse AI response as JSON.', [
                    'response_excerpt' => $excerpt,
                ]);

                $runRow = kg_edge_load_run($pdo, $runUuid);
                kg_edge_json(false, ['state' => kg_edge_build_state($pdo, $runRow)], 'AI response was not valid JSON.');
            }

            if (isset($parsed['proposed_edges']) && is_array($parsed['proposed_edges'])) {
                $edges = $parsed['proposed_edges'];
            } elseif (array_is_list($parsed)) {
                $edges = $parsed;
            } else {
                $edges = [];
            }

            $pdo->prepare("DELETE FROM kg_edge_run_ai_edges WHERE run_id = ?")->execute([$runId]);

            $validEdges = [];
            $candidateMap = [];
            foreach ($hits as $h) {
                $candidateMap[(int)$h['node_id']] = $h;
            }

            foreach ($edges as $edge) {
                if (!is_array($edge)) {
                    continue;
                }

                $targetId = (int)($edge['target_node_id'] ?? $edge['source_node_id'] ?? 0);
                if (!$targetId || !isset($candidateMap[$targetId])) {
                    continue;
                }

                $rel = trim((string)($edge['relationship_label'] ?? $edge['relationship'] ?? 'linked_to'));
                if ($rel === '') {
                    $rel = 'linked_to';
                }
                $rel = substr($rel, 0, 255);

                $rat = trim((string)($edge['rationale'] ?? ''));

                $validEdges[] = [
                    'target_node_id'      => $targetId,
                    'target_name'         => $candidateMap[$targetId]['target_name'] ?? $candidateMap[$targetId]['name'] ?? 'Unknown',
                    'relationship_label'  => $rel,
                    'rationale'           => $rat,
                ];
            }

            $ins = $pdo->prepare("
                INSERT INTO kg_edge_run_ai_edges
                    (run_id, sort_order, target_node_id, target_name, relationship_label, rationale)
                VALUES
                    (?, ?, ?, ?, ?, ?)
            ");
            foreach ($validEdges as $idx => $e) {
                $ins->execute([
                    $runId,
                    $idx + 1,
                    (int)$e['target_node_id'],
                    $e['target_name'],
                    $e['relationship_label'],
                    $e['rationale'],
                ]);
            }

            kg_edge_log($pdo, $runId, 'ai', 'AI response parsed.', [
                'proposed_edges_seen' => count($edges),
                'valid_edges'         => count($validEdges),
                'top_valid'           => array_slice($validEdges, 0, 3),
            ]);

            $pdo->prepare("
                UPDATE kg_edge_runs
                SET step = 3, step_label = 'Saving proposals'
                WHERE id = ?
            ")->execute([$runId]);

            $runRow = kg_edge_load_run($pdo, $runUuid);
            kg_edge_json(true, ['state' => kg_edge_build_state($pdo, $runRow)]);
        }

        // STEP 3: save to proposal tables
        if ($step === 3) {
            $edgesStmt = $pdo->prepare("
                SELECT target_node_id, target_name, relationship_label, rationale
                FROM kg_edge_run_ai_edges
                WHERE run_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $edgesStmt->execute([$runId]);
            $edges = $edgesStmt->fetchAll(PDO::FETCH_ASSOC);

            $inserted = 0;
            $skipped  = 0;

            $pdo->beginTransaction();
            try {
                foreach ($edges as $edge) {
                    $targetId = (int)$edge['target_node_id'];
                    $tName    = (string)$edge['target_name'];
                    $rel      = substr(trim((string)$edge['relationship_label']), 0, 255);
                    $rat      = trim((string)$edge['rationale']);

                    if (!$targetId) {
                        $skipped++;
                        continue;
                    }

                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO kg_edge_proposals
                            (focal_node_id, target_node_id, target_name, relationship, rationale, status)
                        VALUES
                            (?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        (int)$runRow['focal_node_id'],
                        $targetId,
                        $tName,
                        $rel,
                        $rat,
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $inserted++;
                    } else {
                        $skipped++;
                    }
                }

                $pdo->prepare("
                    UPDATE kg_edge_runs
                    SET status = 'completed',
                        step = 4,
                        step_label = 'Done',
                        result_inserted = ?,
                        result_skipped = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ")->execute([$inserted, $skipped, $runId]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pdo->prepare("
                    UPDATE kg_edge_runs
                    SET status = 'error',
                        step_label = 'Save error',
                        ai_error = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ")->execute([$e->getMessage(), $runId]);

                kg_edge_log($pdo, $runId, 'save', 'Failed while saving proposals.', [
                    'error' => $e->getMessage(),
                ]);

                $runRow = kg_edge_load_run($pdo, $runUuid);
                kg_edge_json(false, ['state' => kg_edge_build_state($pdo, $runRow)], $e->getMessage());
            }

            kg_edge_log($pdo, $runId, 'save', 'Proposals saved.', [
                'inserted' => $inserted,
                'skipped'  => $skipped,
            ]);

            $runRow = kg_edge_load_run($pdo, $runUuid);
            kg_edge_json(true, ['state' => kg_edge_build_state($pdo, $runRow)]);
        }

        kg_edge_json(false, [], 'Unknown step');
    }

    // --------------------------------------------------------------
    // GET CURRENT STATUS
    // --------------------------------------------------------------
    if ($action === 'get_batch_status') {
        $runUuid = trim((string)($input['run_id'] ?? ''));
        if ($runUuid === '') {
            kg_edge_json(false, [], 'Missing run_id');
        }

        $runRow = kg_edge_load_run($pdo, $runUuid);
        if (!$runRow) {
            kg_edge_json(false, [], 'Run not found');
        }

        kg_edge_json(true, ['state' => kg_edge_build_state($pdo, $runRow)]);
    }

    // --------------------------------------------------------------
    // FETCH PENDING PROPOSALS (With Duplicate Detection)
    // --------------------------------------------------------------
    if ($action === 'fetch_pending') {
        $stmt = $pdo->query("
            SELECT p.focal_node_id, n.name AS focal_name, n.node_type, COUNT(p.id) as pending_count
            FROM kg_edge_proposals p
            JOIN kg_nodes n ON n.id = p.focal_node_id
            WHERE p.status = 'pending'
            GROUP BY p.focal_node_id, n.name, n.node_type
            ORDER BY n.name ASC
        ");
        $focalNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $propStmt = $pdo->query("
            SELECT p.id, p.focal_node_id, p.target_node_id, p.target_name, p.relationship, p.rationale,
                   CASE WHEN kni.id IS NOT NULL THEN 1 ELSE 0 END AS is_duplicate
            FROM kg_edge_proposals p
            LEFT JOIN kg_node_items kni
              ON kni.node_id = p.focal_node_id
             AND kni.item_id = p.target_node_id
             AND kni.item_type = 'kg_node'
            WHERE p.status = 'pending'
            ORDER BY p.focal_node_id ASC, p.target_name ASC
        ");
        $proposals = $propStmt->fetchAll(PDO::FETCH_ASSOC);

        kg_edge_json(true, [
            'focal_nodes' => $focalNodes,
            'proposals'   => $proposals,
        ]);
    }

    // --------------------------------------------------------------
    // PROCESS CURATION
    // --------------------------------------------------------------
    if ($action === 'process_curation') {
        $rawBody = json_decode(file_get_contents('php://input'), true);
        if (!is_array($rawBody)) {
            $rawBody = [];
        }

        $promotedIds = array_values(array_filter(array_map('intval', (array)($rawBody['promoted_ids'] ?? []))));
        $rejectedIds = array_values(array_filter(array_map('intval', (array)($rawBody['rejected_ids'] ?? []))));

        $promotedCount = 0;
        $rejectedCount = 0;

        $pdo->beginTransaction();
        try {
            if (!empty($promotedIds)) {
                $placeholders = implode(',', array_fill(0, count($promotedIds), '?'));
                $pStmt = $pdo->prepare("
                    SELECT *
                    FROM kg_edge_proposals
                    WHERE id IN ($placeholders)
                      AND status = 'pending'
                ");
                $pStmt->execute($promotedIds);
                $toPromote = $pStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($toPromote as $row) {
                    $dupCheck = $pdo->prepare("SELECT id FROM kg_node_items WHERE node_id = ? AND item_id = ? AND item_type = 'kg_node'");
                    $dupCheck->execute([(int)$row['focal_node_id'], (int)$row['target_node_id']]);

                    if (!$dupCheck->fetch()) {
                        $soStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM kg_node_items WHERE node_id=?");
                        $soStmt->execute([(int)$row['focal_node_id']]);
                        $nextSort = (int)$soStmt->fetchColumn();

                        $ins = $pdo->prepare("
                            INSERT INTO kg_node_items
                                (node_id, item_type, item_id, item_label, relationship, note, sort_order)
                            VALUES
                                (?, 'kg_node', ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            (int)$row['focal_node_id'],
                            (int)$row['target_node_id'],
                            $row['target_name'],
                            $row['relationship'],
                            'AI Edge Finder: ' . $row['rationale'],
                            $nextSort,
                        ]);
                    }
                    $promotedCount++;
                }

                if (!empty($toPromote)) {
                    $upd = $pdo->prepare("
                        UPDATE kg_edge_proposals
                        SET status = 'promoted'
                        WHERE id IN ($placeholders)
                    ");
                    $upd->execute($promotedIds);
                }
            }

            if (!empty($rejectedIds)) {
                $placeholders = implode(',', array_fill(0, count($rejectedIds), '?'));
                $upd = $pdo->prepare("
                    UPDATE kg_edge_proposals
                    SET status = 'rejected'
                    WHERE id IN ($placeholders)
                      AND status = 'pending'
                ");
                $upd->execute($rejectedIds);
                $rejectedCount = $upd->rowCount();
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            kg_edge_json(false, [], $e->getMessage());
        }

        kg_edge_json(true, [
            'promoted' => $promotedCount,
            'rejected' => $rejectedCount,
        ]);
    }

    // --------------------------------------------------------------
    // FETCH ARCHIVE COUNTS
    // --------------------------------------------------------------
    if ($action === 'fetch_archive_counts') {
        $counts = [];
        
        // Manual relationships (or promoted which exist in graph)
        $stmt = $pdo->query("SELECT node_id, COUNT(*) as c FROM kg_node_items WHERE item_type='kg_node' GROUP BY node_id");
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $counts[(int)$r['node_id']] = ['all' => (int)$r['c'], 'pro' => 0, 'rej' => 0];
        }

        // Promoted/Rejected proposals
        $stmt = $pdo->query("SELECT focal_node_id, status, COUNT(*) as c FROM kg_edge_proposals WHERE status IN ('promoted','rejected') GROUP BY focal_node_id, status");
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int)$r['focal_node_id'];
            if (!isset($counts[$id])) $counts[$id] = ['all'=>0, 'pro'=>0, 'rej'=>0];
            if ($r['status'] === 'promoted') $counts[$id]['pro'] += (int)$r['c'];
            if ($r['status'] === 'rejected') $counts[$id]['rej'] += (int)$r['c'];
        }
        
        kg_edge_json(true, ['counts' => $counts]);
    }

    // --------------------------------------------------------------
    // FETCH ARCHIVE EDGES
    // --------------------------------------------------------------
    if ($action === 'fetch_archive_edges') {
        $focalId = (int)($input['focal_id'] ?? $_GET['focal_id'] ?? 0);

        $itemStmt = $pdo->prepare("SELECT id, item_id as target_node_id, item_label as target_name, relationship, note as rationale, 'active' as edge_source FROM kg_node_items WHERE node_id = ? AND item_type = 'kg_node'");
        $itemStmt->execute([$focalId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $propStmt = $pdo->prepare("SELECT id, target_node_id, target_name, relationship, rationale, status as edge_source FROM kg_edge_proposals WHERE focal_node_id = ? AND status IN ('promoted','rejected')");
        $propStmt->execute([$focalId]);
        $props = $propStmt->fetchAll(PDO::FETCH_ASSOC);

        $merged = [];
        $seenTargets = [];
        foreach ($props as $p) {
            $merged[] = [
                'target_node_id' => $p['target_node_id'],
                'target_name' => $p['target_name'],
                'relationship' => $p['relationship'],
                'rationale' => $p['rationale'],
                'status' => $p['edge_source'], // 'promoted' or 'rejected'
                'is_ai' => true
            ];
            $seenTargets[$p['target_node_id']] = true;
        }
        foreach ($items as $i) {
            if (!isset($seenTargets[$i['target_node_id']])) {
                $merged[] = [
                    'target_node_id' => $i['target_node_id'],
                    'target_name' => $i['target_name'],
                    'relationship' => $i['relationship'],
                    'rationale' => $i['rationale'],
                    'status' => 'manual',
                    'is_ai' => false
                ];
            }
        }
        kg_edge_json(true, ['edges' => $merged]);
    }

    // --------------------------------------------------------------
    // REVERT EDGE
    // --------------------------------------------------------------
    if ($action === 'revert_edge') {
        $focalId = (int)($input['focal_id'] ?? 0);
        $targetId = (int)($input['target_id'] ?? 0);

        if (!$focalId || !$targetId) kg_edge_json(false, [], 'Missing params');

        $propStmt = $pdo->prepare("SELECT id FROM kg_edge_proposals WHERE focal_node_id=? AND target_node_id=?");
        $propStmt->execute([$focalId, $targetId]);
        $propId = $propStmt->fetchColumn();

        if ($propId) {
            $pdo->prepare("UPDATE kg_edge_proposals SET status='pending' WHERE id=?")->execute([$propId]);
        } else {
            $pdo->prepare("INSERT INTO kg_edge_proposals (focal_node_id, target_node_id, target_name, relationship, rationale, status)
                           SELECT node_id, item_id, item_label, relationship, 'Manual relationship re-queued', 'pending'
                           FROM kg_node_items WHERE node_id=? AND item_id=? AND item_type='kg_node'")->execute([$focalId, $targetId]);
        }

        $pdo->prepare("DELETE FROM kg_node_items WHERE node_id=? AND item_id=? AND item_type='kg_node'")->execute([$focalId, $targetId]);

        kg_edge_json(true);
    }

    // --------------------------------------------------------------
    // ADD MANUAL EDGE (PROPOSAL OR DIRECT COMMIT)
    // --------------------------------------------------------------
    if ($action === 'add_manual_edge') {
        $focalId = (int)($input['focal_id'] ?? 0);
        $targetId = (int)($input['target_id'] ?? 0);
        $targetName = trim($input['target_name'] ?? '');
        $relationship = trim($input['relationship'] ?? '');
        $itemLabel = trim($input['item_label'] ?? '');
        $commitImm = !empty($input['commit_immediate']);

        if (!$focalId || !$targetId || !$targetName) {
            kg_edge_json(false, [], 'Missing required fields.');
        }

        try {
            if ($commitImm) {
                // Check dup
                $dupCheck = $pdo->prepare("SELECT id FROM kg_node_items WHERE node_id = ? AND item_id = ? AND item_type = 'kg_node'");
                $dupCheck->execute([$focalId, $targetId]);
                if (!$dupCheck->fetch()) {
                    $soStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM kg_node_items WHERE node_id=?");
                    $soStmt->execute([$focalId]);
                    $nextSort = (int)$soStmt->fetchColumn();

                    $ins = $pdo->prepare("INSERT INTO kg_node_items (node_id, item_type, item_id, item_label, relationship, note, sort_order) VALUES (?, 'kg_node', ?, ?, ?, ?, ?)");
                    $ins->execute([$focalId, $targetId, $itemLabel ?: $targetName, $relationship, 'Manual addition', $nextSort]);
                }
            } else {
                // Insert proposal
                $rationale = 'Manual addition';
                if ($itemLabel) {
                    $rationale .= ' (Label: ' . $itemLabel . ')';
                }
                $ins = $pdo->prepare("INSERT IGNORE INTO kg_edge_proposals (focal_node_id, target_node_id, target_name, relationship, rationale, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $ins->execute([$focalId, $targetId, $targetName, $relationship, $rationale]);
            }
            kg_edge_json(true);
        } catch (Throwable $e) {
            kg_edge_json(false, [], $e->getMessage());
        }
    }

    // --------------------------------------------------------------
    // UPDATE PROPOSAL (INLINE EDIT)
    // --------------------------------------------------------------
    if ($action === 'update_proposal') {
        $id = (int)($input['id'] ?? 0);
        $field = $input['field'] ?? '';
        $value = trim($input['value'] ?? '');

        if (!$id) {
            kg_edge_json(false, [], 'Missing proposal ID');
        }

        if (!in_array($field, ['relationship', 'rationale'])) {
            kg_edge_json(false, [], 'Invalid field for update');
        }

        $col = ($field === 'relationship') ? 'relationship' : 'rationale';

        try {
            $stmt = $pdo->prepare("UPDATE kg_edge_proposals SET {$col} = ? WHERE id = ?");
            $stmt->execute([$value, $id]);
            kg_edge_json(true, []);
        } catch (Throwable $e) {
            kg_edge_json(false, [], $e->getMessage());
        }
    }

    // --------------------------------------------------------------
    // EXPORT RUN CONTEXT (QUEUE)
    // --------------------------------------------------------------
    if ($action === 'export_run_data') {
        $runUuid = trim($input['run_id'] ?? $_GET['run_id'] ?? '');
        $runRow = kg_edge_load_run($pdo, $runUuid);
        if (!$runRow) {
            kg_edge_json(false, [], 'Run not found');
        }

        $candStmt = $pdo->prepare("SELECT node_id, target_name, node_type, excerpt, score, source FROM kg_edge_run_candidates WHERE run_id = ? ORDER BY sort_order ASC");
        $candStmt->execute([$runRow['id']]);
        
        $aiStmt = $pdo->prepare("SELECT target_node_id, target_name, relationship_label, rationale FROM kg_edge_run_ai_edges WHERE run_id = ? ORDER BY sort_order ASC");
        $aiStmt->execute([$runRow['id']]);

        $out = [
            'export_type' => 'queue_run_context',
            'run_uuid' => $runUuid,
            'status' => $runRow['status'],
            'created_at' => $runRow['created_at'],
            'focal_node' => [
                'id' => (int)$runRow['focal_node_id'],
                'name' => $runRow['focal_node_name'],
                'type' => $runRow['focal_node_type'],
                'snippet' => $runRow['focal_snippet']
            ],
            'ai_parameters' => [
                'model' => $runRow['ai_model'],
                'prompt_chars' => (int)$runRow['ai_prompt_chars']
            ],
            'candidates_provided_to_ai' => $candStmt->fetchAll(PDO::FETCH_ASSOC),
            'ai_raw_answers' => $aiStmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        kg_edge_json(true, ['export_data' => $out]);
    }

    // --------------------------------------------------------------
    // EXPORT PROPOSALS (CURATION VIEW)
    // --------------------------------------------------------------
    if ($action === 'export_proposals') {
        $focalId = (int)($input['focal_id'] ?? $_GET['focal_id'] ?? 0);
        
        $nodeStmt = $pdo->prepare("SELECT id, name FROM kg_nodes WHERE id = ?");
        $nodeStmt->execute([$focalId]);
        $node = $nodeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$node) {
            kg_edge_json(false, [], 'Node not found');
        }

        $propStmt = $pdo->prepare("SELECT target_node_id, target_name, relationship, rationale, status FROM kg_edge_proposals WHERE focal_node_id = ? ORDER BY status ASC, target_name ASC");
        $propStmt->execute([$focalId]);
        
        $out = [
            'export_type' => 'ai_proposals',
            'focal_node' => [
                'id' => (int)$node['id'],
                'name' => $node['name']
            ],
            'proposals' => $propStmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        kg_edge_json(true, ['export_data' => $out]);
    }

    // --------------------------------------------------------------
    // EXPORT OFFLINE JOB (full AI request package + instructions)
    // --------------------------------------------------------------
    if ($action === 'export_offline_job') {
        $runUuid = trim((string)($input['run_id'] ?? $_GET['run_id'] ?? ''));
        $runRow = kg_edge_load_run($pdo, $runUuid);
        if (!$runRow) {
            kg_edge_json(false, [], 'Run not found');
        }

        $runId = (int)$runRow['id'];

        $config = kg_edge_load_config($pdo);
        if (!$config) {
            kg_edge_json(false, [], 'Missing kg_edge_finder configuration in database.');
        }

        $candStmt = $pdo->prepare("
            SELECT node_id, target_name, node_type, category_name, keywords,
                   content_status, content_chars, score, excerpt, source, sort_order
            FROM kg_edge_run_candidates
            WHERE run_id = ?
            ORDER BY sort_order ASC, score DESC, node_id ASC
        ");
        $candStmt->execute([$runId]);
        $hits = $candStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($hits)) {
            kg_edge_json(false, [], 'This job has no candidate pack yet (it must reach the "AI reasoning" step first, which happens automatically once retrieval completes).');
        }

        $existStmt = $pdo->prepare("SELECT item_id, item_label, relationship FROM kg_node_items WHERE node_id = ? AND item_type = 'kg_node'");
        $existStmt->execute([(int)$runRow['focal_node_id']]);
        $existingEdges = $existStmt->fetchAll(PDO::FETCH_ASSOC);

        $focalStmt = $pdo->prepare("SELECT content, description, keywords FROM kg_nodes WHERE id = ?");
        $focalStmt->execute([(int)$runRow['focal_node_id']]);
        $fRow = $focalStmt->fetch(PDO::FETCH_ASSOC);

        $fullLore = trim(
            ($fRow['description'] ?? '') . "\n\n" .
            ($fRow['keywords'] ?? '') . "\n\n" .
            mb_substr((string)($fRow['content'] ?? ''), 0, 6000)
        );

        $focalNode = [
            'id'             => (int)$runRow['focal_node_id'],
            'name'           => $runRow['focal_node_name'],
            'node_type'      => $runRow['focal_node_type'],
            'full_lore'      => $fullLore,
            'existing_edges' => $existingEdges,
        ];

        $formattedHits = [];
        foreach ($hits as $h) {
            $formattedHits[] = [
                'target_node_id' => (int)$h['node_id'],
                'target_name'    => $h['target_name'],
                'node_type'      => $h['node_type'],
                'category_name'  => $h['category_name'],
                'keywords'       => $h['keywords'],
                'excerpt'        => $h['excerpt'],
                'score'          => $h['score'],
            ];
        }

        $aiPayload = [
            'focal_node' => $focalNode,
            'hits'       => $formattedHits,
        ];

        $sysPrompt = $config['system_role'] . "\n\n" . implode("\n", json_decode($config['instructions'], true) ?: []);
        $sysPrompt .= "\n\nReturn raw JSON only. Do not wrap the response in markdown fences. Do not include commentary. Only choose target_node_id values from the provided hits.";

        $userPrompt = "Analyze this compact knowledge graph candidate pack and propose only meaningful missing edges. "
            . "Only use target_node_id values that exist in the hits array. "
            . "Return strict JSON only, with no markdown fences or commentary.\n\n"
            . json_encode($aiPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $schema = json_decode($config['output_schema'], true);
        if (!is_array($schema)) {
            $schema = [];
        }

        $expectedAnswerExample = [
            'proposed_edges' => [
                [
                    'target_node_id'     => $formattedHits[0]['target_node_id'] ?? 0,
                    'relationship_label' => 'allied_with',
                    'rationale'          => 'One short sentence explaining why this edge exists, based on the text provided.',
                ],
            ],
        ];

        $instructions = [
            'how_to_use_this_file' => [
                "1. Copy the value of system_prompt into the SYSTEM / INSTRUCTIONS field of your external AI app (or prepend it to your message if the app has no separate system field).",
                "2. Copy the value of user_prompt as the user message / question.",
                "3. Send it to the AI and wait for the answer.",
                "4. Copy the AI's full raw reply (ideally just the JSON object it returns, with no extra commentary).",
                "5. Return to KG Edge Curator, open the Queue tab, find this job, and tap the 'Ingest' (upload) button on this item.",
                "6. Paste the AI's reply into the ingestion dialog and submit. The app will parse it the same way it parses a live API response and move the job forward to the review stage.",
            ],
            'required_answer_format' => "The AI's reply must be a single JSON object (optionally wrapped in ```json fences, which will be stripped automatically) shaped exactly like 'expected_answer_example' below. Only use target_node_id values that appear in ai_payload.hits — do not invent new ids. relationship_label should be short (e.g. allied_with, located_in, part_of, created_by). rationale should be one sentence grounded in the provided text.",
            'expected_answer_example' => $expectedAnswerExample,
        ];

        $out = [
            'export_type'   => 'offline_ai_request',
            'run_uuid'      => $runUuid,
            'focal_node_id' => (int)$runRow['focal_node_id'],
            'focal_node_name' => $runRow['focal_node_name'],
            'model_used_online' => $config['model'],
            'instructions'  => $instructions,
            'system_prompt' => $sysPrompt,
            'user_prompt'   => $userPrompt,
            'ai_payload'    => $aiPayload,
            'output_schema' => $schema,
        ];

        $pdo->prepare("
            UPDATE kg_edge_runs
            SET offline_requested_at = NOW()
            WHERE id = ?
        ")->execute([$runId]);

        kg_edge_log($pdo, $runId, 'offline', 'Offline request package exported for manual AI processing.', []);

        kg_edge_json(true, ['export_data' => $out]);
    }

    // --------------------------------------------------------------
    // INGEST OFFLINE RESULT (paste-back from an external AI app)
    // --------------------------------------------------------------
    if ($action === 'ingest_offline_result') {
        $runUuid = trim((string)($input['run_id'] ?? ''));
        $answerText = (string)($input['answer_text'] ?? '');

        if ($runUuid === '') {
            kg_edge_json(false, [], 'Missing run_id');
        }
        if (trim($answerText) === '') {
            kg_edge_json(false, [], 'Please paste the AI answer text before ingesting.');
        }

        $runRow = kg_edge_load_run($pdo, $runUuid);
        if (!$runRow) {
            kg_edge_json(false, [], 'Run not found');
        }

        $runId = (int)$runRow['id'];

        if (!in_array($runRow['status'], ['awaiting_offline', 'error'], true)) {
            kg_edge_json(false, [], 'This job is not currently awaiting an offline answer.');
        }

        $candStmt = $pdo->prepare("
            SELECT node_id, target_name, node_type, category_name, keywords,
                   content_status, content_chars, score, excerpt, source, sort_order
            FROM kg_edge_run_candidates
            WHERE run_id = ?
            ORDER BY sort_order ASC, score DESC, node_id ASC
        ");
        $candStmt->execute([$runId]);
        $hits = $candStmt->fetchAll(PDO::FETCH_ASSOC);

        $excerpt = kg_edge_excerpt($answerText, 500);
        $pdo->prepare("
            UPDATE kg_edge_runs
            SET ai_response_excerpt = ?, ai_error = NULL
            WHERE id = ?
        ")->execute([$excerpt, $runId]);

        $parsed = kg_edge_parse_ai_json($answerText);
        if (!is_array($parsed)) {
            $pdo->prepare("
                UPDATE kg_edge_runs
                SET status = 'error', step_label = 'Offline parse error', ai_error = ?
                WHERE id = ?
            ")->execute(['Pasted offline answer was not valid JSON.', $runId]);

            kg_edge_log($pdo, $runId, 'offline', 'Could not parse pasted offline answer as JSON.', [
                'response_excerpt' => $excerpt,
            ]);

            $runRow = kg_edge_load_run($pdo, $runUuid);
            kg_edge_json(false, ['state' => kg_edge_build_state($pdo, $runRow)], 'Pasted offline answer was not valid JSON.');
        }

        if (isset($parsed['proposed_edges']) && is_array($parsed['proposed_edges'])) {
            $edges = $parsed['proposed_edges'];
        } elseif (array_is_list($parsed)) {
            $edges = $parsed;
        } else {
            $edges = [];
        }

        $pdo->prepare("DELETE FROM kg_edge_run_ai_edges WHERE run_id = ?")->execute([$runId]);

        $validEdges = [];
        $candidateMap = [];
        foreach ($hits as $h) {
            $candidateMap[(int)$h['node_id']] = $h;
        }

        foreach ($edges as $edge) {
            if (!is_array($edge)) {
                continue;
            }

            $targetId = (int)($edge['target_node_id'] ?? $edge['source_node_id'] ?? 0);
            if (!$targetId || !isset($candidateMap[$targetId])) {
                continue;
            }

            $rel = trim((string)($edge['relationship_label'] ?? $edge['relationship'] ?? 'linked_to'));
            if ($rel === '') {
                $rel = 'linked_to';
            }
            $rel = substr($rel, 0, 255);

            $rat = trim((string)($edge['rationale'] ?? ''));

            $validEdges[] = [
                'target_node_id'      => $targetId,
                'target_name'         => $candidateMap[$targetId]['target_name'] ?? $candidateMap[$targetId]['name'] ?? 'Unknown',
                'relationship_label'  => $rel,
                'rationale'           => $rat,
            ];
        }

        $ins = $pdo->prepare("
            INSERT INTO kg_edge_run_ai_edges
                (run_id, sort_order, target_node_id, target_name, relationship_label, rationale)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");
        foreach ($validEdges as $idx => $e) {
            $ins->execute([
                $runId,
                $idx + 1,
                (int)$e['target_node_id'],
                $e['target_name'],
                $e['relationship_label'],
                $e['rationale'],
            ]);
        }

        kg_edge_log($pdo, $runId, 'offline', 'Offline answer ingested and parsed.', [
            'proposed_edges_seen' => count($edges),
            'valid_edges'         => count($validEdges),
            'top_valid'           => array_slice($validEdges, 0, 3),
        ]);

        $pdo->prepare("
            UPDATE kg_edge_runs
            SET status = 'running',
                step = 3,
                step_label = 'Saving proposals',
                ai_error = NULL,
                offline_ingested_at = NOW()
            WHERE id = ?
        ")->execute([$runId]);

        $runRow = kg_edge_load_run($pdo, $runUuid);
        kg_edge_json(true, ['state' => kg_edge_build_state($pdo, $runRow)]);
    }

    kg_edge_json(false, [], 'Unknown action: ' . $action);

} catch (Throwable $e) {
    http_response_code(500);
    kg_edge_json(false, [], $e->getMessage());
}
?>
