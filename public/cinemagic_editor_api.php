<?php
// public/cinemagic_editor_api.php
// Handles overlay CRUD for the Cinemagic Editor, with full multilingual support
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

        case 'add_overlay':
            $sketchId = (int)($_POST['sketch_id'] ?? 0);
            if (!$sketchId) throw new Exception('Sketch ID required');

            $pdo->beginTransaction();

            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), -1) FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en'");
            $maxStmt->execute([$sketchId]);
            $maxOrder = (int)$maxStmt->fetchColumn();
            $newOrder = $maxOrder + 1;

            $stmt = $pdo->prepare("INSERT INTO sketch_overlay_texts (sketch_id, text_content, language_code, display_order) VALUES (?, '', 'en', ?)");
            $stmt->execute([$sketchId, $newOrder]);
            $overlayId = $pdo->lastInsertId();

            $pdo->commit();
            echo json_encode(['success' => true, 'overlay_id' => $overlayId, 'display_order' => $newOrder]);
            break;

        case 'update_overlay':
            $sketchId     = (int)($_POST['sketch_id'] ?? 0);
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $lang         = $_POST['lang'] ?? 'en';
            $text         = $_POST['text'] ?? '';
            
            if (!$sketchId) throw new Exception('Sketch ID required');

            if ($lang === 'en') {
                $overlayId = (int)($_POST['overlay_id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE sketch_overlay_texts SET text_content = ? WHERE id = ? AND language_code = 'en'");
                $stmt->execute([$text, $overlayId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = ? AND display_order = ?");
                $stmt->execute([$sketchId, $lang, $displayOrder]);
                $existingId = $stmt->fetchColumn();

                if ($existingId) {
                    $uStmt = $pdo->prepare("UPDATE sketch_overlay_texts SET text_content = ? WHERE id = ?");
                    $uStmt->execute([$text, $existingId]);
                } else {
                    $iStmt = $pdo->prepare("INSERT INTO sketch_overlay_texts (sketch_id, text_content, language_code, display_order) VALUES (?, ?, ?, ?)");
                    $iStmt->execute([$sketchId, $text, $lang, $displayOrder]);
                }
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete_overlay':
            $overlayId = (int)($_POST['overlay_id'] ?? 0);
            if (!$overlayId) throw new Exception('Overlay ID required');

            $stmt = $pdo->prepare("SELECT sketch_id, display_order FROM sketch_overlay_texts WHERE id = ? AND language_code = 'en'");
            $stmt->execute([$overlayId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $delStmt = $pdo->prepare("DELETE FROM sketch_overlay_texts WHERE sketch_id = ? AND display_order = ?");
                $delStmt->execute([$row['sketch_id'], $row['display_order']]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'reorder_overlays':
            $sketchId = (int)($_POST['sketch_id'] ?? 0);
            $orderRaw = $_POST['order'] ?? '';
            if (!$sketchId || !$orderRaw) throw new Exception('Sketch ID and order required');

            $ids = array_filter(array_map('intval', explode(',', $orderRaw)));
            if (empty($ids)) throw new Exception('No valid IDs in order');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, display_order FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en'");
            $stmt->execute([$sketchId]);
            $oldOrders = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $oldOrders[(int)$row['id']] = (int)$row['display_order'];
            }

            $uEn = $pdo->prepare("UPDATE sketch_overlay_texts SET display_order = ? WHERE id = ?");
            $uTr = $pdo->prepare("UPDATE sketch_overlay_texts SET display_order = ? WHERE sketch_id = ? AND language_code != 'en' AND display_order = ?");
            
            foreach ($ids as $newIndex => $overlayId) {
                if (isset($oldOrders[$overlayId])) {
                    $oldIndex = $oldOrders[$overlayId];
                    $uTr->execute([-$newIndex - 1000, $sketchId, $oldIndex]);
                }
                $uEn->execute([$newIndex, $overlayId]);
            }

            $uTrFinal = $pdo->prepare("UPDATE sketch_overlay_texts SET display_order = ? WHERE sketch_id = ? AND language_code != 'en' AND display_order = ?");
            foreach ($ids as $newIndex => $overlayId) {
                $uTrFinal->execute([$newIndex, $sketchId, -$newIndex - 1000]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'export_overlay_texts':
            $overwrite = (isset($_POST['overwrite']) && $_POST['overwrite'] === '1');
            $beatsJson = $_POST['beats'] ?? '[]';
            $beats = json_decode($beatsJson, true);

            if (!is_array($beats)) {
                throw new Exception('Invalid beats payload');
            }

            $inserted = 0;
            $skipped = 0;

            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en'");
            $delStmt = $pdo->prepare("DELETE FROM sketch_overlay_texts WHERE sketch_id = ?");
            $insStmt = $pdo->prepare("INSERT INTO sketch_overlay_texts (sketch_id, text_content, language_code, display_order) VALUES (?, ?, 'en', 0)");

            foreach ($beats as $b) {
                $sketchId = (int)($b['sketch_id'] ?? 0);
                $summary  = trim($b['beat_summary'] ?? '');

                if (!$sketchId || !$summary) {
                    continue;
                }

                $checkStmt->execute([$sketchId]);
                $count = (int)$checkStmt->fetchColumn();

                if ($count > 0) {
                    if (!$overwrite) {
                        $skipped++;
                        continue;
                    }
                    $delStmt->execute([$sketchId]);
                }

                $insStmt->execute([$sketchId, $summary]);
                $inserted++;
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'inserted' => $inserted, 'skipped' => $skipped]);
            break;

        // ── Sequence Overlay Texts (1:1 per sequence per language) ─────────
        case 'get_sequence_overlay':
            $sequenceId = (int)($_GET['sequence_id'] ?? 0);
            $lang       = strtolower(trim($_GET['lang'] ?? 'en'));
            if (!$sequenceId) throw new Exception('sequence_id required');

            $stmt = $pdo->prepare("SELECT name_overlay, description_overlay FROM sequence_overlay_texts WHERE sequence_id = ? AND language_code = ?");
            $stmt->execute([$sequenceId, $lang]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'              => true,
                'name_overlay'         => $row['name_overlay'] ?? '',
                'description_overlay'  => $row['description_overlay'] ?? '',
            ]);
            break;

        case 'save_sequence_overlay':
            $sequenceId  = (int)($_POST['sequence_id'] ?? 0);
            $lang        = strtolower(trim($_POST['lang'] ?? 'en'));
            $nameOverlay = trim($_POST['name_overlay'] ?? '');
            $descOverlay = trim($_POST['description_overlay'] ?? '');
            if (!$sequenceId) throw new Exception('sequence_id required');
            if (strlen($lang) !== 2) throw new Exception('Valid 2-letter language code required');

            $stmt = $pdo->prepare(
                "INSERT INTO sequence_overlay_texts (sequence_id, language_code, name_overlay, description_overlay)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name_overlay = VALUES(name_overlay), description_overlay = VALUES(description_overlay)"
            );
            $stmt->execute([$sequenceId, $lang, $nameOverlay ?: null, $descOverlay ?: null]);
            echo json_encode(['success' => true]);
            break;

        // ── System Languages CRUD ───────────────────────────────────────────
        case 'get_languages':
            $langs = $pdo->query("SELECT * FROM system_languages ORDER BY is_main DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'languages' => $langs]);
            break;

        case 'save_language':
            $code = strtolower(trim($_POST['code'] ?? ''));
            $name = trim($_POST['name'] ?? '');
            if (strlen($code) !== 2 || !$name) throw new Exception('Valid 2-letter code and name required');
            $stmt = $pdo->prepare("INSERT INTO system_languages (code, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?");
            $stmt->execute([$code, $name, $name]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_language':
            $code = strtolower(trim($_POST['code'] ?? ''));
            if ($code === 'en') throw new Exception('Cannot delete main language');
            $stmt = $pdo->prepare("DELETE FROM system_languages WHERE code = ?");
            $stmt->execute([$code]);
            echo json_encode(['success' => true]);
            break;

        // ── Cinemagic management ────────────────────────────────────────────
        case 'create_cinemagic':
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (!$name) throw new Exception('Name required');

            $stmt = $pdo->prepare("INSERT INTO cinemagics (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            echo json_encode(['success' => true, 'cinemagic_id' => (int)$pdo->lastInsertId()]);
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

        case 'reorder_cinemagic_sequences':
            $cinemagicId = (int)($_POST['cinemagic_id'] ?? 0);
            $orderRaw    = $_POST['order'] ?? '';
            if (!$cinemagicId || !$orderRaw) throw new Exception('cinemagic_id and order required');

            $ids = array_filter(array_map('intval', explode(',', $orderRaw)));
            if (empty($ids)) throw new Exception('No valid IDs in order');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE cinemagics_2_sequences SET sort_order = ? WHERE cinemagic_id = ? AND sequence_id = ?");
            foreach ($ids as $i => $seqId) {
                $stmt->execute([$i, $cinemagicId, $seqId]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}