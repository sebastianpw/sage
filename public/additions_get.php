<?php
// additions_get.php
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$entity_type = $_GET['entity_type'] ?? null;
$entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
$matrix_id = isset($_GET['matrix_id']) ? (int)$_GET['matrix_id'] : null;

try {
    // Basic validation for entity_type (simple whitelist-ish check)
    if ($entity_type !== null && !preg_match('/^[a-z0-9_]+$/i', $entity_type)) {
        throw new RuntimeException("Invalid entity_type");
    }

    // fetch additions (global + optionally tagged for entity_type)
    $sql = "SELECT id, slot, description, entity_type, entity_id FROM prompt_additions WHERE active = 1";
    $params = [];
    if ($entity_type) {
        $sql .= " AND (entity_type IS NULL OR entity_type = ?)";
        $params[] = $entity_type;
    }
    $sql .= " ORDER BY slot, `order`, id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $slot = (int)$r['slot'];
        if (!isset($out[$slot])) $out[$slot] = [];
        $out[$slot][] = ['id' => (int)$r['id'], 'slot' => $slot, 'description' => $r['description']];
    }

    // if matrix_id provided, fetch selected additions for that matrix
    $selected = [];
    $matrix = null;
    if ($matrix_id) {
        $stmt2 = $pdo->prepare("SELECT slot, addition_id FROM prompt_matrix_additions WHERE matrix_id = ? ORDER BY slot, `order`");
        $stmt2->execute([$matrix_id]);
        $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows2 as $r) {
            $s = (int)$r['slot'];
            if (!isset($selected[$s])) $selected[$s] = [];
            if (!empty($r['addition_id'])) $selected[$s][] = (int)$r['addition_id'];
        }

        // matrix meta
        $stmt3 = $pdo->prepare("SELECT id, additions_count, total_combinations, created_at, updated_at FROM prompt_matrix WHERE id = ?");
        $stmt3->execute([$matrix_id]);
        $matrix = $stmt3->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode(['success' => true, 'data' => $out, 'selected' => $selected, 'matrix' => $matrix], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
