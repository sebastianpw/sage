<?php
// matrix_create.php
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$MAX_COMBINATIONS = 1000000; // absolute safety guard for creation
$entity_type = $_POST['entity_type'] ?? null;
$entity_id = isset($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;
$selectedRaw = $_POST['selected'] ?? 'null';

try {
    $selected = json_decode($selectedRaw, true);
    if (!is_array($selected) || empty($selected)) throw new RuntimeException("No selections provided");

    // load base prompt (description)
    $stmt = $pdo->prepare("SELECT description FROM prompt_ideations WHERE id = ?");
    $stmt->execute([$entity_id]);
    $basePrompt = $stmt->fetchColumn();
    if ($basePrompt === false) $basePrompt = '';

    // Resolve addition texts for given ids and produce snapshot structure
    $slots = [];
    foreach ($selected as $slotKey => $ids) {
        $slot = (int)$slotKey;
        if (!is_array($ids) || count($ids) === 0) continue;
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
            }
        }
        if (count($arr) > 0) $slots[$slot] = $arr;
    }
    if (empty($slots)) throw new RuntimeException("No valid slot variants found.");

    ksort($slots);
    $counts = array_map(function($a){ return count($a); }, $slots);
    $total_combinations = array_product($counts);

    if ($total_combinations > $MAX_COMBINATIONS) throw new RuntimeException("Too many combinations ({$total_combinations}).");

    // Build snapshot JSON: array of objects { slot, addition_id, text }
    $snapshot = [];
    foreach ($slots as $slot => $arr) {
        foreach ($arr as $v) {
            $snapshot[] = ['slot' => (int)$slot, 'addition_id' => (int)$v['addition_id'], 'text' => $v['text']];
        }
    }

    $pdo->beginTransaction();

    // insert matrix row
    /*$ins = $pdo->prepare("INSERT INTO prompt_matrix (entity_type, entity_id, `order`, description, additions_snapshot, additions_count, total_combinations, regenerate_images, created_at, updated_at)
        VALUES (?, ?, 0, ?, ?, ?, 0, 0, NOW(), NOW())");
    $desc = "Matrix generated from UI";
    $ins->execute([$entity_type, $entity_id, $desc, json_encode($snapshot, JSON_UNESCAPED_UNICODE), count($snapshot), $total_combinations]);
     */


$ins = $pdo->prepare("INSERT INTO prompt_matrix (
    entity_type, entity_id, `order`, description,
    additions_snapshot, additions_count, total_combinations,
    regenerate_images, created_at, updated_at
) VALUES (?, ?, 0, ?, ?, ?, ?, 0, NOW(), NOW())");

$desc = "Matrix generated from UI";
$ins->execute([
    $entity_type,
    $entity_id,
    $desc,
    json_encode($snapshot, JSON_UNESCAPED_UNICODE),
    count($snapshot),
    $total_combinations
]);


    $matrixId = (int)$pdo->lastInsertId();

    // insert mapping rows (one per addition selection)
    $ins2 = $pdo->prepare("INSERT INTO prompt_matrix_additions (matrix_id, addition_id, entity_type, entity_id, slot, `order`, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($slots as $slot => $arr) {
        $order = 0;
        foreach ($arr as $v) {
            $ins2->execute([$matrixId, $v['addition_id'], $entity_type, $entity_id, $slot, $order, $v['text']]);
            $order++;
        }
    }

    $pdo->commit();

    echo json_encode(['success'=>true, 'matrix_id'=>$matrixId, 'total_combinations'=>$total_combinations], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    exit;
}
