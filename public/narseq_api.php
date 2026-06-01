<?php
// public/narseq_api.php
// API for the Narrative Sequencer Split/Copy & Reorder Tool
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'copy_sequence':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $newName    = trim($_POST['new_name'] ?? '');
            
            if (!$sequenceId || !$newName) {
                throw new Exception('Sequence ID and new name are required.');
            }

            $pdo->beginTransaction();

            // Fetch original sequence
            $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
            $stmt->execute([$sequenceId]);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$seq) throw new Exception('Original sequence not found.');

            // Insert new sequence
            $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id) VALUES (?, ?, ?, ?)");
            $ins->execute([
                $newName,
                $seq['description'],
                $seq['sequence_data'],
                $seq['linked_doc_id']
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Copy sequence_overlay_texts if any
            $ovStmt = $pdo->prepare("SELECT language_code, name_overlay, description_overlay FROM sequence_overlay_texts WHERE sequence_id = ?");
            $ovStmt->execute([$sequenceId]);
            $overlays = $ovStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($overlays) {
                $insOv = $pdo->prepare("INSERT INTO sequence_overlay_texts (sequence_id, language_code, name_overlay, description_overlay) VALUES (?, ?, ?, ?)");
                foreach ($overlays as $ov) {
                    $insOv->execute([$newId, $ov['language_code'], $ov['name_overlay'], $ov['description_overlay']]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'new_sequence_id' => $newId]);
            break;

        case 'split_sequence':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $splitIndex = (int)($_POST['split_index'] ?? 0);
            $newName    = trim($_POST['new_name'] ?? '');
            
            if (!$sequenceId || !$newName || $splitIndex <= 0) {
                throw new Exception('Invalid parameters for split.');
            }

            $pdo->beginTransaction();

            // Fetch original sequence
            $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
            $stmt->execute([$sequenceId]);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$seq) throw new Exception('Original sequence not found.');

            $data = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
            if ($splitIndex >= count($data)) {
                throw new Exception('Split index is out of bounds.');
            }

            // Slice the JSON array
            $part1 = array_slice($data, 0, $splitIndex);
            $part2 = array_slice($data, $splitIndex);

            // Update original sequence to retain only Part 1
            $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
            $upd->execute([json_encode($part1, JSON_UNESCAPED_UNICODE), $sequenceId]);

            // Insert new sequence for Part 2
            $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id) VALUES (?, ?, ?, ?)");
            $ins->execute([
                $newName,
                $seq['description'],
                json_encode($part2, JSON_UNESCAPED_UNICODE),
                $seq['linked_doc_id']
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Copy sequence_overlay_texts to the new sequence
            $ovStmt = $pdo->prepare("SELECT language_code, name_overlay, description_overlay FROM sequence_overlay_texts WHERE sequence_id = ?");
            $ovStmt->execute([$sequenceId]);
            $overlays = $ovStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($overlays) {
                $insOv = $pdo->prepare("INSERT INTO sequence_overlay_texts (sequence_id, language_code, name_overlay, description_overlay) VALUES (?, ?, ?, ?)");
                foreach ($overlays as $ov) {
                    $insOv->execute([$newId, $ov['language_code'], $ov['name_overlay'], $ov['description_overlay']]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'new_sequence_id' => $newId, 'original_id' => $sequenceId]);
            break;

        case 'reorder_sequence':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $orderRaw   = $_POST['order'] ?? '';
            
            if (!$sequenceId || $orderRaw === '') {
                throw new Exception('Invalid parameters for reorder.');
            }

            $indices = array_filter(explode(',', $orderRaw), 'is_numeric');
            $indices = array_map('intval', $indices);

            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
            $stmt->execute([$sequenceId]);
            $json = $stmt->fetchColumn();
            
            if (!$json) throw new Exception('Sequence not found.');

            $data = json_decode($json, true) ?: [];
            
            $newData = [];
            foreach ($indices as $oldIdx) {
                if (isset($data[$oldIdx])) {
                    $newData[] = $data[$oldIdx];
                }
            }
            
            // Failsafe: if the elements count drifted, abort to protect data
            if (count($newData) !== count($data)) {
                throw new Exception('Data mismatch during reorder operation.');
            }

            $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
            $upd->execute([json_encode($newData, JSON_UNESCAPED_UNICODE), $sequenceId]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'update_item_frame':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $itemIndex  = (int)($_POST['item_index'] ?? -1);
            $frameId    = (int)($_POST['frame_id'] ?? 0);
            
            if (!$sequenceId || $itemIndex < 0 || !$frameId) {
                throw new Exception('Invalid parameters for frame update.');
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
            $stmt->execute([$sequenceId]);
            $json = $stmt->fetchColumn();
            
            if (!$json) throw new Exception('Sequence not found.');
            
            $data = json_decode($json, true) ?: [];
            if (!isset($data[$itemIndex])) {
                throw new Exception('Item index out of bounds.');
            }
            
            // Ensure format is object, not plain int
            if (!is_array($data[$itemIndex])) {
                $data[$itemIndex] = ['sketch_id' => (int)$data[$itemIndex]];
            }
            
            // Update the frame id and save
            $data[$itemIndex]['frame_id'] = $frameId;
            
            $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
            $upd->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $sequenceId]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'insert_sequence_item':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $sketchId   = (int)($_POST['sketch_id'] ?? 0);
            $frameId    = (int)($_POST['frame_id'] ?? 0);
            $insertIdx  = (int)($_POST['insert_index'] ?? -1);

            if (!$sequenceId || !$sketchId) {
                throw new Exception('Invalid parameters for insert.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
            $stmt->execute([$sequenceId]);
            $json = $stmt->fetchColumn();
            
            if (!$json) throw new Exception('Sequence not found.');
            
            $data = json_decode($json, true) ?: [];

            $newItem = ['sketch_id' => $sketchId, 'frame_id' => $frameId];
            if ($insertIdx >= 0 && $insertIdx <= count($data)) {
                array_splice($data, $insertIdx, 0, [$newItem]);
            } else {
                $data[] = $newItem;
                $insertIdx = count($data) - 1;
            }

            $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
            $upd->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $sequenceId]);

            // Fetch sketch details
            $sStmt = $pdo->prepare("SELECT name, description FROM sketches WHERE id = ?");
            $sStmt->execute([$sketchId]);
            $sketch = $sStmt->fetch(PDO::FETCH_ASSOC);

            // Fetch all frames for cycle UI
            $stmtAllF = $pdo->prepare("
                SELECT id, filename, entity_id AS sketch_id FROM frames WHERE entity_type='sketches' AND entity_id = ?
                UNION
                SELECT f.id, f.filename, f2s.to_id AS sketch_id FROM frames f JOIN frames_2_sketches f2s ON f2s.from_id = f.id WHERE f2s.to_id = ?
                ORDER BY id DESC
            ");
            $stmtAllF->execute([$sketchId, $sketchId]);
            
            $frames = [];
            $finalFrameId = $frameId;
            $finalFrameFile = '';
            foreach ($stmtAllF->fetchAll(PDO::FETCH_ASSOC) as $fr) {
                $candidate = $fr['filename'];
                if (strpos($candidate, 'http') !== 0 && strpos($candidate, 'view_frame.php') === false) {
                    $parts = array_map('rawurlencode', explode('/', ltrim($candidate, '/')));
                    $candidate = '/' . implode('/', $parts);
                }
                $frames[] = [
                    'id' => (int)$fr['id'],
                    'filename' => $candidate
                ];
            }
            
            foreach ($frames as $f) {
                if ($f['id'] == $frameId) {
                    $finalFrameFile = $f['filename'];
                    break;
                }
            }
            if (!$finalFrameFile && count($frames) > 0) {
                $finalFrameId = $frames[0]['id'];
                $finalFrameFile = $frames[0]['filename'];
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'insert_index' => $insertIdx,
                'sketch' => [
                    'id' => $sketchId,
                    'name' => $sketch['name'] ?? 'Unknown Sketch',
                    'description' => $sketch['description'] ?? ''
                ],
                'frame' => [
                    'id' => $finalFrameId,
                    'filename' => $finalFrameFile
                ],
                'all_frames' => $frames
            ]);
            break;
            
            
        case 'remove_sequence_item':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $itemIndex  = (int)($_POST['item_index'] ?? -1);
            
            if (!$sequenceId || $itemIndex < 0) {
                throw new Exception('Invalid parameters for removal.');
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
            $stmt->execute([$sequenceId]);
            $json = $stmt->fetchColumn();
            
            if (!$json) throw new Exception('Sequence not found.');
            
            $data = json_decode($json, true) ?: [];
            if (!isset($data[$itemIndex])) {
                throw new Exception('Item index out of bounds.');
            }
            
            // Remove the item at the specific index and re-index the array
            array_splice($data, $itemIndex, 1);
            
            $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
            $upd->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $sequenceId]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

            
            
            

        case 'export_sequence':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            if (!$sequenceId) throw new Exception('Sequence ID required.');

            $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
            $stmt->execute([$sequenceId]);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$seq) throw new Exception('Sequence not found.');

            // Fetch specific overlays
            $ovStmt = $pdo->prepare("SELECT language_code, name_overlay, description_overlay FROM sequence_overlay_texts WHERE sequence_id = ?");
            $ovStmt->execute([$sequenceId]);
            $overlays = $ovStmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse sequence data array
            $itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
            
            
            
            
            
            
            
             // Pre-fetch all sketch data
            $sketchIds = [];
            foreach ($itemIds as $item) {
                $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
                if ($sid > 0) $sketchIds[] = $sid;
            }
            $sketchIds = array_values(array_unique($sketchIds));
            
            $sketchesData = [];
            
            
            
            
            
            if (!empty($sketchIds)) {
                $in = implode(',', array_fill(0, count($sketchIds), '?'));
                $sStmt = $pdo->prepare("SELECT id, name, description FROM sketches WHERE id IN ($in)");
                $sStmt->execute($sketchIds);
                foreach ($sStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                    $sketchesData[(int)$s['id']] = $s;
                }
            }

            // Assemble rich item list
            $items = [];
            foreach ($itemIds as $idx => $item) {
                $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
                $fid = is_array($item) ? (int)($item['frame_id'] ?? 0) : null;
                $sketch = $sketchesData[$sid] ?? null;
                
                $items[] = [
                    'order'              => $idx + 1,
                    'sketch_id'          => $sid,
                    'frame_id'           => $fid,
                    'sketch_name'        => $sketch['name'] ?? '',
                    'sketch_description' => $sketch['description'] ?? ''
                ];
            }

            $exportData = [
                'sequence_id' => $seq['id'],
                'name'        => $seq['name'],
                'description' => $seq['description'],
                'created_at'  => $seq['created_at'],
                'updated_at'  => $seq['updated_at'],
                'overlays'    => $overlays,
                'items'       => $items
            ];

            echo json_encode(['success' => true, 'export' => $exportData]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}