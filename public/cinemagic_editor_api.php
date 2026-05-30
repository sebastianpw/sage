<?php
// public/cinemagic_editor_api.php
// Handles overlay CRUD for the Cinemagic Editor, with full multilingual support
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

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
                // Determine if a translation record already exists
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

            // Find the display order of the English record being deleted
            $stmt = $pdo->prepare("SELECT sketch_id, display_order FROM sketch_overlay_texts WHERE id = ? AND language_code = 'en'");
            $stmt->execute([$overlayId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Delete all language versions for this block
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
            // Need to update the display_order of the 'en' block AND all matching translation blocks.
            // First, determine old display_orders to map them correctly for translations.
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
                    // Temporarily set old index to a negative space to avoid unique constraint collisions if any were added
                    $uTr->execute([-$newIndex - 1000, $sketchId, $oldIndex]);
                }
                $uEn->execute([$newIndex, $overlayId]);
            }

            // Restore negative spaces to correct new index
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

            // Check if any English overlay exists for the sketch
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en'");
            // Delete all overlays (including translations) for the sketch if replacing
            $delStmt = $pdo->prepare("DELETE FROM sketch_overlay_texts WHERE sketch_id = ?");
            // Insert the new beat summary as the primary English overlay
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
                    // Overwrite: delete all existing overlay texts for this sketch
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
