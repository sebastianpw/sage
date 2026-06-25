<?php
// public/kg_travel_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

function api_json($ok, $data = [], $error = null) {
    $out = ['ok' => $ok];
    if ($error) $out['error'] = $error;
    foreach ($data as $k => $v) $out[$k] = $v;
    echo json_encode($out);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!$action && isset($input['action'])) $action = $input['action'];

try {
    if ($action === 'travel_context') {
        $nodeId = (int)($input['node_id'] ?? $_GET['node_id'] ?? 0);
        $hops   = max(1, min(4, (int)($input['hops'] ?? 1))); // Default 1, Max 4
        
        // If no node given, pick a random active node (preferably with content)
        if (!$nodeId) {
            $stmt = $pdo->query("SELECT id FROM kg_nodes WHERE status='active' AND content IS NOT NULL ORDER BY RAND() LIMIT 1");
            $nodeId = (int)$stmt->fetchColumn();
        }
        
        if (!$nodeId) api_json(false, [], "No node found.");
        
        // 1. Focal Node
        $stmt = $pdo->prepare("SELECT id, name, node_type, category_id FROM kg_nodes WHERE id = ?");
        $stmt->execute([$nodeId]);
        $focalNode = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$focalNode) api_json(false, [], "Node not found.");
        
        // 2. Visuals (Resolved via sketch history using the exact entity name)
        $entityName = $focalNode['name'];
        $sqlHistory = "
            SELECT slh.sketch_id, s.name, s.description
            FROM sketch_lore_history slh
            JOIN sketches s ON slh.sketch_id = s.id
            WHERE LOWER(slh.entity_name) = LOWER(?)
            ORDER BY slh.id DESC LIMIT 1
        ";
        $stmt = $pdo->prepare($sqlHistory);
        $stmt->execute([trim($entityName)]);
        $historyRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $frames = [];
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
        }
        
        // 3. Mini Graph (BFS up to $hops)
        $visited = [$nodeId => true];
        $frontier = [$nodeId];

        for ($h = 0; $h < $hops; $h++) {
            if (empty($frontier)) break;
            $ph = implode(',', array_fill(0, count($frontier), '?'));

            // Outgoing edges
            $stmt = $pdo->prepare("
                SELECT DISTINCT item_id AS neighbour
                FROM kg_node_items
                WHERE item_type = 'kg_node' AND item_id IS NOT NULL AND node_id IN ($ph)
            ");
            $stmt->execute($frontier);
            $out = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Incoming edges
            $stmt = $pdo->prepare("
                SELECT DISTINCT node_id AS neighbour
                FROM kg_node_items
                WHERE item_type = 'kg_node' AND item_id IN ($ph)
            ");
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

        $neighbourIds = array_keys($visited);
        
        $nodes = [];
        $edges = [];
        if (!empty($neighbourIds)) {
            $ph = implode(',', array_fill(0, count($neighbourIds), '?'));
            
            $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id IN ($ph) AND status = 'active'");
            $stmt->execute($neighbourIds);
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT id, node_id AS source, item_id AS target, relationship, item_label
                FROM kg_node_items
                WHERE item_type = 'kg_node' AND item_id IS NOT NULL
                  AND node_id IN ($ph) AND item_id IN ($ph)
            ");
            $stmt->execute(array_merge($neighbourIds, $neighbourIds));
            $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // 4. Precompute layout via PyApi to speed up Sigma render
        $pyapiUrl = $GLOBALS['WORDNET_PYAPI_URL'] ?? 'http://127.0.0.1:8009';
        $payload = json_encode(['nodes' => $nodes, 'edges' => $edges, 'iterations' => 150]);
        $ch = curl_init(rtrim($pyapiUrl, '/') . '/graph/layout');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $layoutData = json_decode($response, true);
            if (!empty($layoutData['positions'])) {
                $pos = $layoutData['positions'];
                foreach ($nodes as &$n) {
                    if (isset($pos[$n['id']])) {
                        $n['x'] = $pos[$n['id']]['x'];
                        $n['y'] = $pos[$n['id']]['y'];
                    }
                }
            }
        }
        
        api_json(true, [
            'focal_node' => $focalNode,
            'visuals' => $frames,
            'sketch' => $historyRow,
            'graph' => [
                'nodes' => $nodes,
                'edges' => $edges
            ]
        ]);
    }
    
    api_json(false, [], "Unknown action");
} catch (Exception $e) {
    http_response_code(500);
    api_json(false, [], $e->getMessage());
}