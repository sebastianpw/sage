<?php
// public/scrollmagic_images_narseq.php
require __DIR__ . '/bootstrap.php';

// Helper: JSON error
function sendJsonError($message, $code = 400, $details = '') {
    header('Content-Type: application/json; charset=utf-8', true, $code);
    $response = ['error' => $message];
    if (!empty($details) && (getenv('APP_ENV') !== 'production')) {
        $response['details'] = $details;
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    sendJsonError('Server misconfiguration: Database connection missing.', 500);
}

// --- Params ---
$offset = max(0, (int)($_GET['offset'] ?? 0));
$batchSize = max(1, min(500, (int)($_GET['batch_size'] ?? 100)));
$sequence_id = isset($_GET['sequence_id']) ? (int)$_GET['sequence_id'] : (int)($_GET['id'] ?? 0);

if ($sequence_id <= 0) {
    sendJsonError('Invalid Narrative Sequence ID');
}

try {
    $stmt = $pdo->prepare("SELECT id, name, sequence_data FROM `narrative_sequences` WHERE id = :id");
    $stmt->execute(['id' => $sequence_id]);
    $seqRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seqRow) {
        sendJsonError('Narrative Sequence not found', 404);
    }

    $rawItems = json_decode($seqRow['sequence_data'] ?? '[]', true) ?: [];
    $total = count($rawItems);

    $batchItems = array_slice($rawItems, $offset, $batchSize, true);
    $processedCount = count($batchItems);

    if (empty($batchItems)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'total'     => $total,
            'offset'    => $offset,
            'limit'     => $batchSize,
            'processed' => 0,
            'count'     => 0,
            'images'    => []
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // Collect sketch IDs and frame IDs needed for this batch
    $batchSketchIds = [];
    $batchFrameIds  = [];
    foreach ($batchItems as $item) {
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        if ($sid > 0) {
            $batchSketchIds[] = $sid;
        }
        if (is_array($item) && !empty($item['frame_id'])) {
            $batchFrameIds[] = (int)$item['frame_id'];
        }
    }
    $batchSketchIds = array_values(array_unique($batchSketchIds));
    $batchFrameIds  = array_values(array_unique(array_filter($batchFrameIds)));

    // Fetch sketches info
    $sketchesMap = [];
    if (!empty($batchSketchIds)) {
        $inSketches = implode(',', array_fill(0, count($batchSketchIds), '?'));
        $stmtSk = $pdo->prepare("SELECT id, name, description FROM `sketches` WHERE id IN ($inSketches)");
        $stmtSk->execute($batchSketchIds);
        foreach ($stmtSk->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $sketchesMap[(int)$s['id']] = $s;
        }
    }

    // Fetch explicit frames
    $framesMap = [];
    if (!empty($batchFrameIds)) {
        $inFrames = implode(',', array_fill(0, count($batchFrameIds), '?'));
        $stmtFr = $pdo->prepare("SELECT id, filename, name, prompt, entity_type, entity_id FROM `frames` WHERE id IN ($inFrames)");
        $stmtFr->execute($batchFrameIds);
        foreach ($stmtFr->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $framesMap[(int)$f['id']] = $f;
        }
    }

    // For any items missing an explicit frame or where explicit frame wasn't found, find fallback frames for the sketches
    $sketchIdsNeedingFrames = [];
    foreach ($batchItems as $item) {
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        $fid = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : 0;
        if ($sid > 0 && (!$fid || !isset($framesMap[$fid]))) {
            $sketchIdsNeedingFrames[] = $sid;
        }
    }
    $sketchIdsNeedingFrames = array_values(array_unique($sketchIdsNeedingFrames));

    $fallbackFramesBySketch = [];
    if (!empty($sketchIdsNeedingFrames)) {
        $inNeed = implode(',', array_fill(0, count($sketchIdsNeedingFrames), '?'));
        $stmtFb = $pdo->prepare("
            SELECT id, filename, name, prompt, entity_type, entity_id, entity_id AS sketch_id
            FROM frames
            WHERE entity_type = 'sketches' AND entity_id IN ($inNeed)
            UNION
            SELECT f.id, f.filename, f.name, f.prompt, f.entity_type, f.entity_id, f2s.to_id AS sketch_id
            FROM frames f
            JOIN frames_2_sketches f2s ON f2s.from_id = f.id
            WHERE f2s.to_id IN ($inNeed)
            ORDER BY id DESC
        ");
        $stmtFb->execute(array_merge($sketchIdsNeedingFrames, $sketchIdsNeedingFrames));
        foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $fr) {
            $sid = (int)$fr['sketch_id'];
            if (!isset($fallbackFramesBySketch[$sid])) {
                $fallbackFramesBySketch[$sid] = $fr;
            }
        }
    }

    // Build the result in exact sequence order
    $images = [];
    foreach ($batchItems as $idx => $item) {
        $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
        $fid = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : 0;

        $frame = null;
        if ($fid && isset($framesMap[$fid])) {
            $frame = $framesMap[$fid];
        } elseif ($sid && isset($fallbackFramesBySketch[$sid])) {
            $frame = $fallbackFramesBySketch[$sid];
        }

        if (!$frame) {
            continue;
        }

        $filename = $frame['filename'] ?? '';
        if ($filename === '') continue;

        if (strpos($filename, 'http') !== 0 && strpos($filename, 'view_frame.php') === false) {
            $parts = array_map('rawurlencode', explode('/', ltrim($filename, '/')));
            $url = '/' . implode('/', $parts);
        } else {
            $url = $filename;
        }

        $sketch = $sketchesMap[$sid] ?? null;
        $caption = '';
        if ($sketch && !empty($sketch['name'])) {
            $caption = $sketch['name'];
        } elseif (!empty($frame['name'])) {
            $caption = $frame['name'];
        } else {
            $caption = pathinfo($filename, PATHINFO_FILENAME);
        }

        $images[] = [
            'id'          => (int)$frame['id'],
            'filename'    => $filename,
            'url'         => $url,
            'caption'     => $caption,
            'entity'      => ($sid > 0) ? 'sketches' : (!empty($frame['entity_type']) ? $frame['entity_type'] : 'frames'),
            'entity_id'   => ($sid > 0) ? $sid : (!empty($frame['entity_id']) ? (int)$frame['entity_id'] : (int)$frame['id']),
            'sketch_id'   => $sid,
            'sketch_name' => $sketch['name'] ?? '',
            'sequence_idx'=> (int)$idx
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total'     => $total,
        'offset'    => $offset,
        'limit'     => $batchSize,
        'processed' => $processedCount,
        'count'     => count($images),
        'images'    => $images
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    sendJsonError('Database Query Failed', 500, $e->getMessage());
}