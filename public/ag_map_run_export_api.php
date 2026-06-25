<?php
// public/ag_map_run_export_api.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_GET;
if (empty($input) && !empty($_POST)) $input = $_POST;

$action = $input['action'] ?? '';

try {
    // ------------------------------------------------------------------
    // 1. SEARCH MAP RUNS
    // ------------------------------------------------------------------
    if ($action === 'search_map_runs') {
        $q = trim($input['q'] ?? '');
        $limit = 20;
        
        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT mr.id, mr.entity_type, mr.note, COUNT(f.id) as frame_count FROM map_runs mr LEFT JOIN frames f ON mr.id = f.map_run_id WHERE mr.id = ? GROUP BY mr.id");
            $stmt->execute([(int)$q]);
        } else {
            $stmt = $pdo->prepare("SELECT mr.id, mr.entity_type, mr.note, COUNT(f.id) as frame_count FROM map_runs mr LEFT JOIN frames f ON mr.id = f.map_run_id WHERE mr.note LIKE ? OR mr.entity_type LIKE ? GROUP BY mr.id ORDER BY mr.id DESC LIMIT $limit");
            $stmt->execute(["%$q%", "%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ------------------------------------------------------------------
    // 2. EXPORT OR SAVE MAP RUNS
    // ------------------------------------------------------------------
    if ($action === 'process_runs') {
        $mapRunIds = $input['map_run_ids'] ?? [];
        $outputMode = $input['output_mode'] ?? 'export'; 
        
        if (empty($mapRunIds) || !is_array($mapRunIds)) {
            echo json_encode(['ok' => false, 'error' => 'map_run_ids array is required']); exit;
        }

        $mapRunIds = array_values(array_filter(array_map('intval', $mapRunIds)));
        if (empty($mapRunIds)) {
            echo json_encode(['ok' => false, 'error' => 'No valid map_run_ids provided.']); exit;
        }

        $placeholders = implode(',', array_fill(0, count($mapRunIds), '?'));

        $sqlFrames = "
            SELECT mr.id AS map_run_id, mr.entity_type AS run_entity_type, f.id AS frame_id, f.filename, f.prompt, s.id AS sketch_id, s.name AS sketch_name
            FROM map_runs mr
            JOIN frames f ON f.map_run_id = mr.id
            LEFT JOIN frames_2_sketches f2s ON f.id = f2s.from_id
            LEFT JOIN sketches s ON s.id = f2s.to_id OR (f.entity_type = 'sketches' AND f.entity_id = s.id)
            WHERE mr.id IN ($placeholders)
            ORDER BY mr.id ASC, f.id ASC
        ";
        
        $stmt = $pdo->prepare($sqlFrames);
        $stmt->execute($mapRunIds);
        $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $exportData = [];
        $sequenceData = []; 
        $agNodeCache = []; 

        foreach ($frames as $frame) {
            $agNode = null;
            if ($frame['sketch_id']) {
                $hStmt = $pdo->prepare("SELECT entity_name FROM sketch_lore_history WHERE sketch_id = ? ORDER BY id DESC LIMIT 1");
                $hStmt->execute([$frame['sketch_id']]);
                $history = $hStmt->fetch(PDO::FETCH_ASSOC);

                if ($history && !empty($history['entity_name'])) {
                    $cacheKey = strtolower($history['entity_name']);
                    if (!array_key_exists($cacheKey, $agNodeCache)) {
                        $agStmt = $pdo->prepare("SELECT id, name, node_type, content FROM ag_nodes WHERE LOWER(name) = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
                        $agStmt->execute([$cacheKey]);
                        $fetched = $agStmt->fetch(PDO::FETCH_ASSOC);
                        if ($fetched && !empty($fetched['content'])) $fetched['content'] = strip_tags($fetched['content']);
                        $agNodeCache[$cacheKey] = $fetched ?: null;
                    }
                    $agNode = $agNodeCache[$cacheKey];
                }
            }

            $exportData[] = [
                'map_run_id' => $frame['map_run_id'],
                'frame_id' => $frame['frame_id'],
                'filename' => $frame['filename'],
                'prompt' => $frame['prompt'],
                'sketch_id' => $frame['sketch_id'],
                'sketch_name' => $frame['sketch_name'],
                'lore_origin' => $agNode
            ];

            $sequenceData[] = [
                'ag_node_id' => $agNode ? (int)$agNode['id'] : null,
                'sketch_id' => (int)$frame['sketch_id'],
                'frame_id' => (int)$frame['frame_id'],
                'role' => 'beat',
                'reason' => $agNode ? $agNode['name'] : $frame['sketch_name']
            ];
        }

        if ($outputMode === 'database') {
            $seqName = "AG Map Run Sequence (" . implode(', ', $mapRunIds) . ")";
            $seqDesc = "Auto-generated narrative sequence derived from map run outputs.";
            $stmt = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$seqName, $seqDesc, json_encode($sequenceData)]);
            echo json_encode(['ok' => true, 'mode' => 'database', 'sequence_id' => $pdo->lastInsertId()]);
            exit;
        }

        $content = json_encode(['metadata' => ['exported_at' => date('Y-m-d H:i:s'), 'total_frames' => count($exportData), 'map_runs' => $mapRunIds], 'data' => $exportData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ag_map_runs_export_' . date('Ymd_His') . '.json"');
        echo $content;
        exit;
    }

    // ------------------------------------------------------------------
    // 3. FETCH ROBUST MINI GRAPH (For the Viewer Overlay)
    // ------------------------------------------------------------------
    if ($action === 'fetch_mini_graph') {
        $nodeId = (int)($input['node_id'] ?? 0);
        $docId  = (int)($input['doc_id'] ?? 0);
        $maxHops = max(1, min(3, (int)($input['hops'] ?? 1)));

        if (!$nodeId || !$docId) { echo json_encode(['ok' => false, 'error' => 'Missing node_id or doc_id']); exit; }

        $stmtFocal = $pdo->prepare("SELECT id, name, node_type FROM ag_nodes WHERE id = ? AND doc_id = ? AND status='active'");
        $stmtFocal->execute([$nodeId, $docId]);
        $focalNode = $stmtFocal->fetch(PDO::FETCH_ASSOC);
        
        if (!$focalNode) { echo json_encode(['ok' => false, 'error' => 'Focal node not found']); exit; }

        $nodes = [$focalNode['id'] => $focalNode];
        $edges = [];
        $frontier = [$focalNode['id'] => $focalNode['name']];
        $visited = [$focalNode['id'] => true];

        for ($h = 0; $h < $maxHops; $h++) {
            $nextFrontier = [];
            if (empty($frontier)) break;

            foreach ($frontier as $fId => $fName) {
                // Outgoing Edges (Resolved via Join)
                $out = $pdo->prepare("
                    SELECT ani.relationship, an_tgt.id AS target_id, an_tgt.name AS target_name, an_tgt.node_type AS target_type
                    FROM ag_node_items ani
                    JOIN ag_nodes an_tgt ON an_tgt.name = ani.item_label AND an_tgt.doc_id = ani.doc_id
                    WHERE ani.node_id = ? AND ani.doc_id = ? AND an_tgt.status='active'
                ");
                $out->execute([$fId, $docId]);
                foreach ($out->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $tid = $r['target_id'];
                    $edges[] = ['source' => $fId, 'target' => $tid, 'relationship' => $r['relationship']];
                    if (!isset($visited[$tid])) {
                        $visited[$tid] = true;
                        $nodes[$tid] = ['id' => $tid, 'name' => $r['target_name'], 'node_type' => $r['target_type']];
                        $nextFrontier[$tid] = $r['target_name'];
                    }
                }

                // Incoming Edges (Resolved via Join)
                $inc = $pdo->prepare("
                    SELECT ani.relationship, an_src.id AS source_id, an_src.name AS source_name, an_src.node_type AS source_type
                    FROM ag_node_items ani
                    JOIN ag_nodes an_src ON an_src.id = ani.node_id
                    WHERE ani.item_label = ? AND ani.doc_id = ? AND an_src.status='active'
                ");
                $inc->execute([$fName, $docId]);
                foreach ($inc->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $sid = $r['source_id'];
                    $edges[] = ['source' => $sid, 'target' => $fId, 'relationship' => $r['relationship']];
                    if (!isset($visited[$sid])) {
                        $visited[$sid] = true;
                        $nodes[$sid] = ['id' => $sid, 'name' => $r['source_name'], 'node_type' => $r['source_type']];
                        $nextFrontier[$sid] = $r['source_name'];
                    }
                }
            }
            $frontier = $nextFrontier;
        }

        echo json_encode(['ok' => true, 'focal_node' => $focalNode, 'graph' => ['nodes' => array_values($nodes), 'edges' => $edges]]);
        exit;
    }

    // ------------------------------------------------------------------
    // 4. GET ROBUST NODE CONTEXT (For Details Modal)
    // ------------------------------------------------------------------
    if ($action === 'get_node_context') {
        $nodeId = (int)($input['node_id'] ?? 0);
        $docId  = (int)($input['doc_id'] ?? 0);

        $stmtFocal = $pdo->prepare("SELECT id, name, node_type, content FROM ag_nodes WHERE id = ? AND doc_id = ? AND status='active'");
        $stmtFocal->execute([$nodeId, $docId]);
        $node = $stmtFocal->fetch(PDO::FETCH_ASSOC);
        if (!$node) { echo json_encode(['ok' => false, 'error' => 'Not found']); exit; }

        $out = $pdo->prepare("SELECT ani.relationship, an_tgt.id, an_tgt.name AS label, an_tgt.node_type AS type FROM ag_node_items ani JOIN ag_nodes an_tgt ON an_tgt.name = ani.item_label AND an_tgt.doc_id = ani.doc_id WHERE ani.node_id = ? AND ani.doc_id = ? AND an_tgt.status='active'");
        $out->execute([$nodeId, $docId]);
        
        $inc = $pdo->prepare("SELECT ani.relationship, an_src.id, an_src.name AS label, an_src.node_type AS type FROM ag_node_items ani JOIN ag_nodes an_src ON an_src.id = ani.node_id WHERE ani.item_label = ? AND ani.doc_id = ? AND an_src.status='active'");
        $inc->execute([$node['name'], $docId]);

        $node['outgoing'] = $out->fetchAll(PDO::FETCH_ASSOC);
        $node['incoming'] = $inc->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'node' => $node]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}