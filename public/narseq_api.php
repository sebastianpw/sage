<?php
// public/narseq_api.php
// API for the Narrative Sequencer Split/Copy & Reorder Tool
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Defensive check to ensure category table exists
try {
    $pdo->query("SELECT 1 FROM narrative_sequence_categories LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS narrative_sequence_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $ex) {}
}

try {
    switch ($action) {

        case 'update_sketch_name':
            $sketchId = (int)($_POST['sketch_id'] ?? 0);
            $newName = trim($_POST['name'] ?? '');
            
            if (!$sketchId || !$newName) {
                throw new Exception('Sketch ID and name are required.');
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE sketches SET name = ? WHERE id = ?");
            $stmt->execute([$newName, $sketchId]);
            $pdo->commit();
            
            echo json_encode(['success' => true]);
            break;

        case 'list_storyboards':
            $q = trim($_GET['q'] ?? '');
            $sql = "SELECT id, name, description FROM storyboards";
            $params = [];
            if ($q !== '') {
                $sql .= " WHERE name LIKE ? OR description LIKE ?";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }
            $sql .= " ORDER BY id DESC LIMIT 50";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $meta = $row['description'] ?? '';
                if (mb_strlen($meta) > 40) {
                    $meta = mb_substr($meta, 0, 40) . '...';
                }
                $res[] = [
                    'id' => $row['id'],
                    'label' => $row['name'],
                    'meta' => $meta
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $res]);
            break;

        case 'search_sequences':
            $q = trim($_GET['q'] ?? '');
            $sql = "SELECT id, name FROM narrative_sequences";
            $params = [];
            if ($q !== '') {
                $sql .= " WHERE name LIKE ? OR id = ?";
                $params[] = "%$q%";
                $params[] = (int)$q;
            }
            $sql .= " ORDER BY id DESC LIMIT 20";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $res[] = [
                    'id' => $row['id'],
                    'label' => $row['name'] ?: 'Untitled Sequence'
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $res]);
            break;

        case 'get_sequence_categories':
            try {
                $stmt = $pdo->query("SELECT id, name FROM narrative_sequence_categories ORDER BY name ASC");
                $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $cats]);
            } catch (Exception $e) {
                echo json_encode(['success' => true, 'data' => []]);
            }
            break;

        case 'list_sequence_categories':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 8)));
            $search = trim($_GET['search'] ?? '');

            $where = ["1=1"];
            $params = [];
            if ($search !== '') {
                $where[] = "c.name LIKE ?";
                $params[] = "%$search%";
            }
            $whereSql = implode(' AND ', $where);

            try {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM narrative_sequence_categories c WHERE $whereSql");
                $countStmt->execute($params);
                $total = (int)$countStmt->fetchColumn();

                $offset = ($page - 1) * $perPage;

                // Check if narrative_sequences has category_id column
                $hasCatCol = false;
                try {
                    $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1");
                    $hasCatCol = true;
                } catch (Exception $e) {}

                $countSub = $hasCatCol 
                    ? "(SELECT COUNT(*) FROM narrative_sequences ns WHERE ns.category_id = c.id)" 
                    : "0";

                $sql = "SELECT c.id, c.name, $countSub AS seq_count 
                        FROM narrative_sequence_categories c 
                        WHERE $whereSql 
                        ORDER BY c.name ASC 
                        LIMIT $perPage OFFSET $offset";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'data' => $rows,
                    'meta' => [
                        'total' => $total,
                        'page' => $page,
                        'total_pages' => max(1, ceil($total / $perPage))
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'create_sequence_category':
            $name = trim($_POST['name'] ?? '');
            if (!$name) throw new Exception('Category name is required.');

            $stmt = $pdo->prepare("INSERT INTO narrative_sequence_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            $newCatId = (int)$pdo->lastInsertId();

            echo json_encode(['success' => true, 'id' => $newCatId, 'name' => $name]);
            break;

        case 'update_sequence_category':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if (!$id || !$name) throw new Exception('Category ID and name are required.');

            $stmt = $pdo->prepare("UPDATE narrative_sequence_categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);

            echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
            break;

        case 'delete_sequence_category':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Category ID is required.');

            $pdo->beginTransaction();
            try {
                $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1");
                $upd = $pdo->prepare("UPDATE narrative_sequences SET category_id = NULL WHERE category_id = ?");
                $upd->execute([$id]);
            } catch (Exception $e) {}

            $stmt = $pdo->prepare("DELETE FROM narrative_sequence_categories WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();

            echo json_encode(['success' => true]);
            break;

        case 'get_cinemagics':
            try {
                $stmt = $pdo->query("SELECT id, name FROM cinemagics ORDER BY name ASC");
                $cms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $cms]);
            } catch (Exception $e) {
                echo json_encode(['success' => true, 'data' => []]);
            }
            break;

        case 'get_sequence_cinemagics':
            $seqId = (int)($_GET['sequence_id'] ?? 0);
            if (!$seqId) throw new Exception('Sequence ID required.');
            try {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, cs.sort_order, cs.chapter_label
                    FROM cinemagics c
                    JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = c.id
                    WHERE cs.sequence_id = ?
                    ORDER BY c.name ASC
                ");
                $stmt->execute([$seqId]);
                $cms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $cms]);
            } catch (Exception $e) {
                echo json_encode(['success' => true, 'data' => []]);
            }
            break;

        case 'assign_to_cinemagic':
            $cinemagicId = (int)($_POST['cinemagic_id'] ?? 0);
            $sequenceId  = (int)($_POST['sequence_id']  ?? 0);
            $sortOrder   = (int)($_POST['sort_order']   ?? 0);
            $label       = trim($_POST['chapter_label'] ?? '');
            if (!$cinemagicId || !$sequenceId) throw new Exception('cinemagic_id and sequence_id required');

            $stmt = $pdo->prepare(
                "INSERT INTO cinemagics_2_sequences (cinemagic_id, sequence_id, sort_order, chapter_label)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), chapter_label = VALUES(chapter_label)"
            );
            $stmt->execute([$cinemagicId, $sequenceId, $sortOrder, $label ?: null]);
            echo json_encode(['success' => true]);
            break;

        case 'remove_from_cinemagic':
            $cinemagicId = (int)($_POST['cinemagic_id'] ?? 0);
            $sequenceId  = (int)($_POST['sequence_id']  ?? 0);
            if (!$cinemagicId || !$sequenceId) throw new Exception('cinemagic_id and sequence_id required');

            $stmt = $pdo->prepare("DELETE FROM cinemagics_2_sequences WHERE cinemagic_id = ? AND sequence_id = ?");
            $stmt->execute([$cinemagicId, $sequenceId]);
            echo json_encode(['success' => true]);
            break;

        case 'create_cinemagic':
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $label = trim($_POST['chapter_label'] ?? '');
            if (!$name) throw new Exception('Name required');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO cinemagics (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $newCmId = (int)$pdo->lastInsertId();

            if ($sequenceId > 0) {
                $maxSortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM cinemagics_2_sequences WHERE cinemagic_id = ?");
                $maxSortStmt->execute([$newCmId]);
                $nextSort = (int)$maxSortStmt->fetchColumn() + 1;

                $insC2s = $pdo->prepare("INSERT INTO cinemagics_2_sequences (cinemagic_id, sequence_id, sort_order, chapter_label) VALUES (?, ?, ?, ?)");
                $insC2s->execute([$newCmId, $sequenceId, $nextSort, $label ?: null]);
            }
            $pdo->commit();

            echo json_encode(['success' => true, 'cinemagic_id' => $newCmId, 'name' => $name]);
            break;
            
        case 'edit_sequence':
            $id = (int)($_POST['sequence_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $catId = (int)($_POST['category_id'] ?? 0);
            
            if (!$id || !$name) throw new Exception('Sequence ID and Name are required.');
            
            $catVal = $catId > 0 ? $catId : null;
            $hasCat = false;
            try { $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1"); $hasCat = true; } catch (Exception $e) {}

            if ($hasCat) {
                $upd = $pdo->prepare("UPDATE narrative_sequences SET name = ?, category_id = ? WHERE id = ?");
                $upd->execute([$name, $catVal, $id]);
            } else {
                $upd = $pdo->prepare("UPDATE narrative_sequences SET name = ? WHERE id = ?");
                $upd->execute([$name, $id]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'delete_sequence':
            $id = (int)($_POST['sequence_id'] ?? 0);
            if (!$id) throw new Exception('Invalid sequence ID.');
            
            $pdo->prepare("DELETE FROM narrative_sequences WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_sequences_list':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 30;
            $search = trim($_GET['search'] ?? '');
            $categoryId = (int)($_GET['category_id'] ?? 0);

            $where = ["1=1"];
            $params = [];

            if ($search !== '') {
                $where[] = "(name LIKE ? OR id = ?)";
                $params[] = "%$search%";
                $params[] = (int)$search;
            }

            // Defensively check for category_id existence
            $hasCat = false;
            try {
                $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1");
                $hasCat = true;
            } catch (Exception $e) { }

            if ($hasCat && $categoryId > 0) {
                $where[] = "category_id = ?";
                $params[] = $categoryId;
            }

            $whereSql = implode(' AND ', $where);

            try {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM narrative_sequences WHERE $whereSql");
                $countStmt->execute($params);
                $total = (int)$countStmt->fetchColumn();

                $offset = ($page - 1) * $perPage;
                
                // Fetch category_id if the column exists
                $catCol = $hasCat ? ", category_id" : "";
                $stmt = $pdo->prepare("SELECT id, name, created_at, sequence_data $catCol FROM narrative_sequences WHERE $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $data = [];
                foreach ($rows as $row) {
                    $sData = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
                    $data[] = [
                        'id' => $row['id'],
                        'name' => $row['name'] ?: 'Untitled Sequence',
                        'created_at' => date('Y-m-d', strtotime($row['created_at'])),
                        'skt_count' => count($sData),
                        'category_id' => $hasCat ? (int)($row['category_id'] ?? 0) : 0
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'meta' => [
                        'total' => $total,
                        'page' => $page,
                        'total_pages' => max(1, ceil($total / $perPage))
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'list_storyboard_frames':
            $sbId = (int)($_GET['storyboard_id'] ?? 0);
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 9);
            if ($page < 1) $page = 1;
            $offset = ($page - 1) * $perPage;
            
            $where = ["sf.storyboard_id = ?"];
            $params = [$sbId];
            
            if (!empty($_GET['entity_type'])) {
                $where[] = "f.entity_type = ?";
                $params[] = $_GET['entity_type'];
            }
            
            if (!empty($_GET['search'])) {
                $where[] = "(s.name LIKE ? OR f.name LIKE ?)";
                $params[] = "%" . $_GET['search'] . "%";
                $params[] = "%" . $_GET['search'] . "%";
            }
            if (!empty($_GET['entity_id'])) {
                $where[] = "f.entity_id = ?";
                $params[] = (int)$_GET['entity_id'];
            }
            if (!empty($_GET['frame_id'])) {
                $where[] = "sf.frame_id = ?";
                $params[] = (int)$_GET['frame_id'];
            }
            
            $whereClause = implode(" AND ", $where);
            
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM storyboard_frames sf JOIN frames f ON f.id = sf.frame_id LEFT JOIN sketches s ON s.id = f.entity_id WHERE $whereClause");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            
            $sql = "
                SELECT sf.frame_id, sf.filename as sf_filename, f.entity_id as sketch_id, f.name as frame_name, s.name as sketch_name, f.filename as f_filename
                FROM storyboard_frames sf
                JOIN frames f ON f.id = sf.frame_id
                LEFT JOIN sketches s ON s.id = f.entity_id
                WHERE $whereClause
                ORDER BY sf.sort_order ASC, sf.id ASC
                LIMIT $perPage OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $data = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $filename = $row['sf_filename'] ?: $row['f_filename'];
                if (strpos($filename, 'http') !== 0 && strpos($filename, 'view_frame.php') === false) {
                    $parts = array_map('rawurlencode', explode('/', ltrim($filename, '/')));
                    $filename = '/' . implode('/', $parts);
                }
                $data[] = [
                    'entity_id' => $row['sketch_id'],
                    'frame_id' => $row['frame_id'],
                    'filename' => $filename,
                    'entity_name' => $row['sketch_name'],
                    'frame_name' => $row['frame_name']
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'meta' => [
                    'total' => $total,
                    'pages' => max(1, ceil($total / $perPage))
                ],
                'data' => $data
            ]);
            break;

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

            // Insert new sequence (now checking if category_id exists to copy it)
            $hasCat = false;
            try { $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1"); $hasCat = true; } catch (Exception $e) {}

            if ($hasCat) {
                $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id, category_id) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$newName, $seq['description'], $seq['sequence_data'], $seq['linked_doc_id'], $seq['category_id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id) VALUES (?, ?, ?, ?)");
                $ins->execute([$newName, $seq['description'], $seq['sequence_data'], $seq['linked_doc_id']]);
            }
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
            $hasCat = false;
            try { $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1"); $hasCat = true; } catch (Exception $e) {}

            if ($hasCat) {
                $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id, category_id) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$newName, $seq['description'], json_encode($part2, JSON_UNESCAPED_UNICODE), $seq['linked_doc_id'], $seq['category_id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id) VALUES (?, ?, ?, ?)");
                $ins->execute([$newName, $seq['description'], json_encode($part2, JSON_UNESCAPED_UNICODE), $seq['linked_doc_id']]);
            }
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
            
        case 'create_sequence':
            $name = trim($_POST['name'] ?? '');
            $catId = (int)($_POST['category_id'] ?? 0);
            $cinemagicId = (int)($_POST['cinemagic_id'] ?? 0);
            $newCmName = trim($_POST['new_cinemagic_name'] ?? '');
            $chapterLabel = trim($_POST['chapter_label'] ?? '');

            if (!$name) throw new Exception('Name is required.');

            $hasCat = false;
            try { $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1"); $hasCat = true; } catch (Exception $e) {}

            $catVal = $catId > 0 ? $catId : null;

            $pdo->beginTransaction();

            if ($hasCat && $catVal) {
                $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, sequence_data, category_id) VALUES (?, '[]', ?)");
                $ins->execute([$name, $catVal]);
            } else {
                $ins = $pdo->prepare("INSERT INTO narrative_sequences (name, sequence_data) VALUES (?, '[]')");
                $ins->execute([$name]);
            }
            $newId = (int)$pdo->lastInsertId();

            if ($newCmName !== '') {
                $insCm = $pdo->prepare("INSERT INTO cinemagics (name) VALUES (?)");
                $insCm->execute([$newCmName]);
                $cinemagicId = (int)$pdo->lastInsertId();
            }

            if ($cinemagicId > 0) {
                $maxSortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM cinemagics_2_sequences WHERE cinemagic_id = ?");
                $maxSortStmt->execute([$cinemagicId]);
                $nextSort = (int)$maxSortStmt->fetchColumn() + 1;

                $insC2s = $pdo->prepare("INSERT INTO cinemagics_2_sequences (cinemagic_id, sequence_id, sort_order, chapter_label) VALUES (?, ?, ?, ?)");
                $insC2s->execute([$cinemagicId, $newId, $nextSort, $chapterLabel ?: null]);
            }

            $pdo->commit();

            echo json_encode(['success' => true, 'new_sequence_id' => $newId]);
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

        case 'insert_map_run_items':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $mapRunId   = (int)($_POST['map_run_id'] ?? 0);

            if (!$sequenceId || !$mapRunId) {
                throw new Exception('Sequence ID and Map Run ID are required.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
            $stmt->execute([$sequenceId]);
            $json = $stmt->fetchColumn();
            
            if ($json === false) throw new Exception('Sequence not found.');
            
            $data = json_decode($json ?? '[]', true) ?: [];

            // Query frames associated with map_run_id
            $stmtF = $pdo->prepare("
                SELECT id AS frame_id, entity_id AS sketch_id
                FROM frames
                WHERE map_run_id = ? AND entity_type = 'sketches' AND entity_id IS NOT NULL AND entity_id > 0
                ORDER BY id ASC
            ");
            $stmtF->execute([$mapRunId]);
            $frames = $stmtF->fetchAll(PDO::FETCH_ASSOC);

            if (empty($frames)) {
                $pdo->rollBack();
                throw new Exception('No sketch frames found for this Map Run.');
            }

            $addedCount = 0;
            foreach ($frames as $fr) {
                $data[] = [
                    'sketch_id' => (int)$fr['sketch_id'],
                    'frame_id'  => (int)$fr['frame_id']
                ];
                $addedCount++;
            }

            $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
            $upd->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $sequenceId]);

            $pdo->commit();

            echo json_encode(['success' => true, 'count' => $addedCount]);
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

        case 'mass_remove_sequence_items':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $indices = json_decode($_POST['indices'] ?? '[]', true) ?: [];
            
            if (!$sequenceId || empty($indices)) {
                throw new Exception('Invalid parameters for mass removal.');
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
            $stmt->execute([$sequenceId]);
            $json = $stmt->fetchColumn();
            
            if (!$json) throw new Exception('Sequence not found.');
            
            $data = json_decode($json, true) ?: [];
            
            // Remove from array backwards to prevent index shifting while deleting
            rsort($indices);
            foreach ($indices as $idx) {
                $idx = (int)$idx;
                if (isset($data[$idx])) {
                    array_splice($data, $idx, 1);
                }
            }
            
            $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
            $upd->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $sequenceId]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'mass_copy_sequence_items':
            $sourceId = (int)($_POST['source_id'] ?? 0);
            $targetId = (int)($_POST['target_id'] ?? 0);
            $offset   = (int)($_POST['offset'] ?? 0);
            $isMove   = (int)($_POST['is_move'] ?? 0);
            $indices  = json_decode($_POST['indices'] ?? '[]', true) ?: [];

            if (!$sourceId || !$targetId || empty($indices)) {
                throw new Exception('Invalid parameters for mass copy/move.');
            }

            $pdo->beginTransaction();
            
            // Fetch Source
            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
            $stmt->execute([$sourceId]);
            $srcJson = $stmt->fetchColumn();
            if (!$srcJson) throw new Exception('Source sequence not found.');
            $srcData = json_decode($srcJson, true) ?: [];

            // Extract selected items
            $copiedItems = [];
            foreach ($indices as $idx) {
                $idx = (int)$idx;
                if (isset($srcData[$idx])) {
                    $copiedItems[] = $srcData[$idx];
                }
            }

            if ($sourceId === $targetId) {
                // Modify a single array
                if ($isMove) {
                    rsort($indices);
                    foreach ($indices as $idx) {
                        array_splice($srcData, (int)$idx, 1);
                    }
                }
                array_splice($srcData, $offset, 0, $copiedItems);
                
                $upd = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
                $upd->execute([json_encode($srcData, JSON_UNESCAPED_UNICODE), $sourceId]);
            } else {
                // Fetch Target
                $stmtTarget = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ? FOR UPDATE");
                $stmtTarget->execute([$targetId]);
                $tgtJson = $stmtTarget->fetchColumn();
                if (!$tgtJson) throw new Exception('Target sequence not found.');
                $tgtData = json_decode($tgtJson, true) ?: [];
                
                // Inject
                array_splice($tgtData, $offset, 0, $copiedItems);
                $updTgt = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
                $updTgt->execute([json_encode($tgtData, JSON_UNESCAPED_UNICODE), $targetId]);
                
                // Handle deletion from source if it's a "move"
                if ($isMove) {
                    rsort($indices);
                    foreach ($indices as $idx) {
                        array_splice($srcData, (int)$idx, 1);
                    }
                    $updSrc = $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?");
                    $updSrc->execute([json_encode($srcData, JSON_UNESCAPED_UNICODE), $sourceId]);
                }
            }

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