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

    // ── Export Handling ──
    if ($action === 'export') {
        $config = $input['config'] ?? [];
        $format = $input['format'] ?? 'json';

        $sketchUp = new \App\SketchUp\SketchUp($pdo);
        $data = $sketchUp->generateExportData($config);

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