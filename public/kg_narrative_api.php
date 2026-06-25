<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/../src/KgNarrative/KgNarrative.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

ini_set('display_errors', '0');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    $kgn = new \App\KgNarrative\KgNarrative($pdo);

    // ── Tree (categories + nodes, same shape as sketchup_api.php) ──
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

    // ── Search nodes (for focal node search box) ──
    if ($action === 'search_nodes') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        $limit = 20;
        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE status='active' AND (id = ? OR name LIKE ?) ORDER BY name ASC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE status='active' AND name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Mini graph (for modal) ──
    if ($action === 'fetch_mini_graph') {
        header('Content-Type: application/json; charset=utf-8');
        $nodeId = (int)($input['node_id'] ?? 0);
        $hops   = max(1, min(\App\KgNarrative\KgNarrative::MAX_HOPS, (int)($input['hops'] ?? 1)));
        if ($nodeId <= 0) { echo json_encode(['ok' => false, 'error' => 'Invalid node_id']); exit; }
        $res = $kgn->fetchMiniGraph($nodeId, $hops);
        echo json_encode(['ok' => true, 'nodes' => $res['nodes'], 'edges' => $res['edges']]);
        exit;
    }

    // ── Hop preview: given focal + mode, return resulting node id set + names (for pot population) ──
    if ($action === 'hop_preview') {
        header('Content-Type: application/json; charset=utf-8');
        $focalId = (int)($input['focal_id'] ?? 0);
        $hopMode = $input['hop_mode'] ?? 'manual';
        if ($focalId <= 0) { echo json_encode(['ok' => false, 'error' => 'focal_id required']); exit; }

        $hops = $hopMode === '2hop' ? 2 : ($hopMode === '1hop' ? 1 : 0);
        $ids = $hops > 0 ? $kgn->expandHops($focalId, $hops) : [$focalId];

        $nodes = [];
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id IN ($ph) AND status='active'");
            $stmt->execute($ids);
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['ok' => true, 'nodes' => $nodes]);
        exit;
    }

    // ── Node detail (for modal "view details") ──
    if ($action === 'get_node') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM kg_nodes WHERE id = ?");
        $stmt->execute([$id]);
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($node) echo json_encode(['ok' => true, 'node' => $node]);
        else echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }

    // ── Export preview (counts + warnings, no file) ──
    if ($action === 'export_preview') {
        header('Content-Type: application/json; charset=utf-8');
        $config = $input['config'] ?? [];
        $data = $kgn->generateExportData($config);
        echo json_encode([
            'ok' => true,
            'counts' => [
                'nodes'    => count($data['kg']['kg_nodes']),
                'edges'    => count($data['kg']['kg_node_items']),
                'sketches' => count($data['sketches']),
                'frames'   => count($data['frames']),
            ],
            'warnings' => $data['warnings'],
        ]);
        exit;
    }

    // ── Export (download JSON file) ──
    if ($action === 'export') {
        $config = $input['config'] ?? [];
        $data = $kgn->generateExportData($config);
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="kg_narrative_export.json"');
        echo $content;
        exit;
    }

    // ── Search narrative sequences (for browsing existing imports) ──
    if ($action === 'search_sequences') {
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

    // ── Validate AI-produced import JSON (no save) ──
    if ($action === 'validate_import') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = $input['payload'] ?? null;
        if (!is_array($payload)) {
            echo json_encode(['ok' => false, 'errors' => ['Malformed JSON payload.'], 'warnings' => [], 'items' => []]);
            exit;
        }
        $result = $kgn->validateImport($payload);
        echo json_encode($result + ['ok' => $result['ok']]);
        exit;
    }

    // ── Save validated sequence into narrative_sequences ──
    if ($action === 'save_sequence') {
        header('Content-Type: application/json; charset=utf-8');
    
        $payload = $input['payload'] ?? null;
        if (!is_array($payload)) {
            echo json_encode(['ok' => false, 'error' => 'Malformed JSON payload.']);
            exit;
        }
    
        $name = trim((string)($input['sequence_name'] ?? ($payload['sequence_name'] ?? 'Untitled Sequence')));
        $desc = $input['sequence_description'] ?? ($payload['sequence_description'] ?? null);
    
        $validated = $kgn->validateImport($payload);
        if (!$validated['ok']) {
            echo json_encode(['ok' => false, 'errors' => $validated['errors'], 'warnings' => $validated['warnings']]);
            exit;
        }
    
        $id = $kgn->saveSequence($name, $desc, $validated['items']);
        echo json_encode(['ok' => true, 'id' => $id, 'warnings' => $validated['warnings']]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
