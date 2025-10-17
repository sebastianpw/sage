<?php
// matrix2blueprints.php
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$matrixId = isset($_POST['matrix_id']) ? (int)$_POST['matrix_id'] : null;
if (!$matrixId) {
    echo json_encode(['success'=>false,'error'=>'Missing matrix_id']);
    exit;
}

try {
    $pdo->beginTransaction();

    // fetch matrix row
    $stmt = $pdo->prepare("SELECT entity_type, entity_id, description AS matrix_description FROM prompt_matrix WHERE id = ?");
    $stmt->execute([$matrixId]);
    $matrix = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$matrix) throw new RuntimeException("Matrix not found");

    $entityType = $matrix['entity_type'];
    $entityId = $matrix['entity_id'];

    // fetch entity description dynamically
    $stmt = $pdo->prepare("SELECT description FROM `$entityType` WHERE id = ?");
    $stmt->execute([$entityId]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entity) throw new RuntimeException("Entity not found");

    $baseDescription = trim($entity['description'] ?? '');

    // fetch all additions for this matrix, ordered by slot
    $stmt = $pdo->prepare("SELECT addition_id, slot, description FROM prompt_matrix_additions WHERE matrix_id = ? ORDER BY slot, addition_id");
    $stmt->execute([$matrixId]);
    $allAdditions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$allAdditions) throw new RuntimeException("No additions found for this matrix");

    // organize additions by slot
    $slots = [];
    foreach ($allAdditions as $a) {
        $slot = (int)$a['slot'];
        if (!isset($slots[$slot])) $slots[$slot] = [];
        $slots[$slot][] = $a;
    }
    ksort($slots);
    $slotKeys = array_keys($slots);
    $slotCounts = array_map(fn($arr)=>count($arr), $slots);
    $totalCombinations = array_product($slotCounts);

    // compute factors for mixed-radix indexing
    $n = count($slotKeys);
    $factors = [];
    for ($i=0;$i<$n;$i++){
        $prod=1;
        for ($j=$i+1;$j<$n;$j++) $prod*=$slotCounts[$j];
        $factors[$i]=$prod;
    }

    // prepare bulk insert
    $ins = $pdo->prepare("
        INSERT INTO prompt_matrix_blueprints
        (name, matrix_id, matrix_additions_id, entity_type, entity_id, `order`, description, created_at, updated_at, img2img, cnmap)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0, 0)
    ");

    for ($idx=0; $idx<$totalCombinations; $idx++) {
        $i = $idx;
        $parts = [];
        $additionIds = [];
        foreach ($slotKeys as $si => $slotKey) {
            $digit = intdiv($i, $factors[$si]);
	    $i = $i % $factors[$si];


/*
if (!isset($slots[$slotKey][$digit])) {
    throw new RuntimeException("Invalid slot access: slot $slotKey index $digit");
}
$variant = $slots[$slotKey][$digit];
 */


$variant = $slots[$slotKey][$digit] ?? null;
if (!$variant) continue; // skip, just in case

//            $variant = $slots[$slotKey][$digit];
            $parts[] = $variant['description'];
            $additionIds[] = $variant['addition_id'];
        }

        // build the name like "matrix1_12_7_3" etc.
        $name = "matrix{$matrixId}_" . implode('_', $additionIds);

        // concatenate entity description + all addition texts
        $prompt = trim($baseDescription . ' ' . implode(' ', $parts));

        // insert row
        $ins->execute([$name, $matrixId, null, $entityType, $entityId, $idx, $prompt]);
    }

    $pdo->commit();
    echo json_encode([
        'success'=>true,
        'matrix_id'=>$matrixId,
        'total_combinations'=>$totalCombinations
    ]);

} catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
