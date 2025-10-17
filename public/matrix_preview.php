<?php
// matrix_preview.php
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$MAX_COMBINATIONS = 200000; // safety guard for preview
$entity_type = $_POST['entity_type'] ?? null;
$entity_id = isset($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;
$selectedRaw = $_POST['selected'] ?? 'null';
$page = max(1, (int)($_POST['page'] ?? 1));
$page_size = max(1, min(200, (int)($_POST['page_size'] ?? 10)));

/*
// DEBUG:
http_response_code(400);                                echo json_encode(['success'=>false, 'error' => '$entity_type: ' . $entity_type]);
exit;
 */

try {
    $selected = json_decode($selectedRaw, true);
    if (!is_array($selected) || empty($selected)) {
        throw new RuntimeException("No selections provided");
    }

    // fetch description of entity as base prompt
    $stmt = $pdo->prepare("SELECT description FROM " . $entity_type . " WHERE id = ?");
    $stmt->execute([$entity_id]);
    $basePrompt = $stmt->fetchColumn();
    if ($basePrompt === false) $basePrompt = '';

    // for each slot, fetch addition descriptions for provided ids and maintain order
    $slots = []; // slot => [ {id, text}, ... ]
    foreach ($selected as $slotKey => $ids) {
        $slot = (int)$slotKey;
        if (!is_array($ids) || count($ids) === 0) continue;
        // retrieve additions for these ids (some ids might be unknown)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, description FROM prompt_additions WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($dbRows as $r) $byId[(int)$r['id']] = $r['description'];

        // preserve provided order; allow unknown ids (skip)
        $arr = [];
        foreach ($ids as $aid) {
            if (is_numeric($aid) && isset($byId[(int)$aid])) {
                $arr[] = ['id' => (int)$aid, 'text' => $byId[(int)$aid]];
            } else {
                // unknown id or free text - cannot resolve -> skip
            }
        }
        if (count($arr) > 0) $slots[$slot] = $arr;
    }

    if (empty($slots)) throw new RuntimeException("No valid slot variants found.");

    ksort($slots);

    // compute counts and product
    $counts = array_map(function($a){ return count($a); }, $slots);
    $total = array_product($counts);

    if ($total > $MAX_COMBINATIONS) {
        throw new RuntimeException("Too many combinations ({$total}). Reduce variants or increase preview limit.");
    }

    // pagination math
    $totalPages = max(1, (int)ceil($total / $page_size));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $page_size;
    $limit = min($page_size, $total - $offset);

    // prepare slot counts and factors for mixed-radix
    $slotKeys = array_keys($slots); // ordered slots
    $slotCounts = array_map(function($k) use ($slots){ return count($slots[$k]); }, $slotKeys);
    // compute positional factors: factor[i] = product of counts of slots after i
    $factors = [];
    $n = count($slotCounts);
    for ($i=0;$i<$n;$i++){
        $prod = 1;
        for ($j=$i+1;$j<$n;$j++) $prod *= $slotCounts[$j];
        $factors[$i] = $prod;
    }

    // generator for index -> combination (mixed-radix)
    $rows = [];
    for ($idx = $offset; $idx < $offset + $limit; $idx++) {
        $i = $idx;
        $parts = [];
        for ($si=0;$si<$n;$si++){
            $digit = intdiv($i, $factors[$si]);
            $i = $i % $factors[$si];
            $slotKey = $slotKeys[$si];
            $variant = $slots[$slotKey][$digit];
            $parts[] = $variant['text'];
        }
        // build final prompt string (basePrompt + parts)
        $promptText = trim($basePrompt . ' ' . implode(' ', $parts));
        $rows[] = ['prompt' => $promptText];
    }

    echo json_encode([
      'success'=>true,
      'page'=>$page,
      'page_size'=>$page_size,
      'total'=>$total,
      'rows'=>$rows
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'error' => $e->getMessage()]);
    exit;
}
