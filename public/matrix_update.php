<?php
// matrix_update.php
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$matrixId = isset($_POST['matrix_id']) ? (int)$_POST['matrix_id'] : null;
$selectedRaw = $_POST['selected'] ?? 'null';

if (!$matrixId) {
    echo json_encode(['success' => false, 'error' => 'Missing matrix_id']);
    exit;
}

try {
    $selected = json_decode($selectedRaw, true);
    if (!is_array($selected)) throw new RuntimeException("Invalid selected payload");

    // load matrix
    $stmt = $pdo->prepare("SELECT id, entity_type, entity_id FROM prompt_matrix WHERE id = ?");
    $stmt->execute([$matrixId]);
    $matrix = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$matrix) throw new RuntimeException("Matrix not found");

    // Resolve addition texts for given ids and produce snapshot structure
    $slots = [];
    foreach ($selected as $slotKey => $ids) {
        $slot = (int)$slotKey;
        if (!is_array($ids) || count($ids) === 0) continue;

        // resolve descriptions for provided addition ids
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, description FROM prompt_additions WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($dbRows as $r) $byId[(int)$r['id']] = $r['description'];

        $arr = [];
        foreach ($ids as $aid) {
            if (is_numeric($aid) && isset($byId[(int)$aid])) {
                $arr[] = ['addition_id' => (int)$aid, 'text' => $byId[(int)$aid]];
            } else {
                // skip unknown ids (you could also allow free-text by creating prompt_matrix_additions rows)
            }
        }
        if (count($arr) > 0) $slots[$slot] = $arr;
    }

    if (empty($slots)) throw new RuntimeException("No valid slot variants found.");

    ksort($slots);
    $counts = array_map(function($a){ return count($a); }, $slots);
    $total_combinations = array_product($counts);

    // Build snapshot JSON: array of objects { slot, addition_id, text }
    $snapshot = [];
    foreach ($slots as $slot => $arr) {
        foreach ($arr as $v) {
            $snapshot[] = ['slot' => (int)$slot, 'addition_id' => (int)$v['addition_id'], 'text' => $v['text']];
        }
    }

    // Begin transaction: delete old mapping, insert new mapping rows and update matrix row
    $pdo->beginTransaction();

    // Delete old mapping rows
    $del = $pdo->prepare("DELETE FROM prompt_matrix_additions WHERE matrix_id = ?");
    $del->execute([$matrixId]);

    // Insert new mapping rows (traceable)
    $ins = $pdo->prepare("INSERT INTO prompt_matrix_additions (matrix_id, addition_id, slot, `order`, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    foreach ($slots as $slot => $arr) {
        $order = 0;
        foreach ($arr as $v) {
            $ins->execute([$matrixId, $v['addition_id'], $slot, $order, $v['text']]);
            $order++;
        }
    }

    // Update prompt_matrix row: snapshot, counts, total_combinations, updated_at
    $upd = $pdo->prepare("UPDATE prompt_matrix SET additions_snapshot = ?, additions_count = ?, total_combinations = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([json_encode($snapshot, JSON_UNESCAPED_UNICODE), count($snapshot), $total_combinations, $matrixId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'matrix_id' => $matrixId, 'total_combinations' => $total_combinations, 'additions_count' => count($snapshot)]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
