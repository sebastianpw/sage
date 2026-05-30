<?php
// public/plush_api.php
// API for the PLUSH Editor (PLot Us Story Highlights)

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        
        // ── Stories ────────────────────────────────────────────────────────
        case 'create_story':
            $title = trim($_POST['title'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (!$title) throw new Exception('Title is required');

            $stmt = $pdo->prepare("INSERT INTO plush_stories (title, description) VALUES (?, ?)");
            $stmt->execute([$title, $desc]);
            echo json_encode(['success' => true, 'story_id' => (int)$pdo->lastInsertId()]);
            break;

        // ── Scenes ─────────────────────────────────────────────────────────
        case 'add_scene':
            $storyId = (int)($_POST['story_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            if (!$storyId || !$title) throw new Exception('Story ID and title required');

            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(scene_order), -1) FROM plush_scenes WHERE story_id = ?");
            $maxStmt->execute([$storyId]);
            $maxOrder = (int)$maxStmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO plush_scenes (story_id, title, scene_order) VALUES (?, ?, ?)");
            $stmt->execute([$storyId, $title, $maxOrder + 1]);
            echo json_encode(['success' => true, 'scene_id' => (int)$pdo->lastInsertId()]);
            break;

        // ── Groups ─────────────────────────────────────────────────────────
        case 'add_group':
            $sceneId = (int)($_POST['scene_id'] ?? 0);
            if (!$sceneId) throw new Exception('Scene ID required');

            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(group_order), -1) FROM plush_highlight_groups WHERE scene_id = ?");
            $maxStmt->execute([$sceneId]);
            $maxOrder = (int)$maxStmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO plush_highlight_groups (scene_id, label, group_order) VALUES (?, '', ?)");
            $stmt->execute([$sceneId, $maxOrder + 1]);
            echo json_encode(['success' => true, 'group_id' => (int)$pdo->lastInsertId()]);
            break;

        case 'delete_group':
            $groupId = (int)($_POST['group_id'] ?? 0);
            if (!$groupId) throw new Exception('Group ID required');

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM plush_highlight_blocks WHERE group_id = ?")->execute([$groupId]);
            $pdo->prepare("DELETE FROM plush_highlight_groups WHERE id = ?")->execute([$groupId]);
            $pdo->commit();

            echo json_encode(['success' => true]);
            break;

        case 'update_group_label':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            if (!$groupId) throw new Exception('Group ID required');

            $stmt = $pdo->prepare("UPDATE plush_highlight_groups SET label = ? WHERE id = ?");
            $stmt->execute([$label, $groupId]);
            echo json_encode(['success' => true]);
            break;

        // ── Highlight Blocks ───────────────────────────────────────────────
        case 'add_block':
            $sceneId = (int)($_POST['scene_id'] ?? 0);
            $groupId = (int)($_POST['group_id'] ?? 0);
            if (!$sceneId) throw new Exception('Scene ID required');

            $pdo->beginTransaction();

            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), -1) FROM plush_highlight_blocks WHERE scene_id = ? AND group_id = ? AND language_code = 'en'");
            $maxStmt->execute([$sceneId, $groupId]);
            $newOrder = (int)$maxStmt->fetchColumn() + 1;

            $stmt = $pdo->prepare("INSERT INTO plush_highlight_blocks (scene_id, group_id, text_content, language_code, display_order) VALUES (?, ?, '', 'en', ?)");
            $stmt->execute([$sceneId, $groupId, $newOrder]);
            $blockId = (int)$pdo->lastInsertId();

            $pdo->commit();
            echo json_encode(['success' => true, 'block_id' => $blockId, 'display_order' => $newOrder]);
            break;

        case 'update_block':
            $blockId      = (int)($_POST['block_id'] ?? 0);
            $sceneId      = (int)($_POST['scene_id'] ?? 0);
            $groupId      = (int)($_POST['group_id'] ?? 0);
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $lang         = $_POST['lang'] ?? 'en';
            $text         = $_POST['text'] ?? '';
            
            if (!$blockId || !$sceneId) throw new Exception('Block ID and Scene ID required');

            if ($lang === 'en') {
                $stmt = $pdo->prepare("UPDATE plush_highlight_blocks SET text_content = ? WHERE id = ? AND language_code = 'en'");
                $stmt->execute([$text, $blockId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM plush_highlight_blocks WHERE scene_id = ? AND group_id = ? AND language_code = ? AND display_order = ?");
                $stmt->execute([$sceneId, $groupId, $lang, $displayOrder]);
                $existingId = $stmt->fetchColumn();

                if ($existingId) {
                    $uStmt = $pdo->prepare("UPDATE plush_highlight_blocks SET text_content = ? WHERE id = ?");
                    $uStmt->execute([$text, $existingId]);
                } else {
                    $iStmt = $pdo->prepare("INSERT INTO plush_highlight_blocks (scene_id, group_id, text_content, language_code, display_order) VALUES (?, ?, ?, ?, ?)");
                    $iStmt->execute([$sceneId, $groupId, $text, $lang, $displayOrder]);
                }
            }
            echo json_encode(['success' => true]);
            break;

        case 'update_block_color':
            $blockId = (int)($_POST['block_id'] ?? 0);
            $color = $_POST['color'] ?? '';
            if (!$blockId) throw new Exception('Block ID required');
            
            $stmt = $pdo->prepare("UPDATE plush_highlight_blocks SET bg_color = ? WHERE id = ?");
            $stmt->execute([$color ?: null, $blockId]);
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_block':
            $blockId = (int)($_POST['block_id'] ?? 0);
            if (!$blockId) throw new Exception('Block ID required');

            $stmt = $pdo->prepare("SELECT scene_id, group_id, display_order FROM plush_highlight_blocks WHERE id = ? AND language_code = 'en'");
            $stmt->execute([$blockId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Delete all language versions associated with this block
                $delStmt = $pdo->prepare("DELETE FROM plush_highlight_blocks WHERE scene_id = ? AND group_id = ? AND display_order = ?");
                $delStmt->execute([$row['scene_id'], $row['group_id'], $row['display_order']]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'reorder_blocks':
            $sceneId = (int)($_POST['scene_id'] ?? 0);
            $groupId = (int)($_POST['group_id'] ?? 0);
            $orderRaw = $_POST['order'] ?? '';
            if (!$sceneId || !$orderRaw) throw new Exception('Scene ID and order required');

            $ids = array_filter(array_map('intval', explode(',', $orderRaw)));
            if (empty($ids)) throw new Exception('No valid IDs in order');

            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT id, display_order FROM plush_highlight_blocks WHERE scene_id = ? AND group_id = ? AND language_code = 'en'");
            $stmt->execute([$sceneId, $groupId]);
            $oldOrders = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $oldOrders[(int)$row['id']] = (int)$row['display_order'];
            }

            $uEn = $pdo->prepare("UPDATE plush_highlight_blocks SET display_order = ? WHERE id = ?");
            $uTr = $pdo->prepare("UPDATE plush_highlight_blocks SET display_order = ? WHERE scene_id = ? AND group_id = ? AND language_code != 'en' AND display_order = ?");
            
            foreach ($ids as $newIndex => $blockId) {
                if (isset($oldOrders[$blockId])) {
                    $oldIndex = $oldOrders[$blockId];
                    // Temporarily move to a negative value to prevent unique constraint conflict if added later
                    $uTr->execute([-$newIndex - 1000, $sceneId, $groupId, $oldIndex]);
                }
                $uEn->execute([$newIndex, $blockId]);
            }

            // Restore from negative mapped placeholders
            $uTrFinal = $pdo->prepare("UPDATE plush_highlight_blocks SET display_order = ? WHERE scene_id = ? AND group_id = ? AND language_code != 'en' AND display_order = ?");
            foreach ($ids as $newIndex => $blockId) {
                $uTrFinal->execute([$newIndex, $sceneId, $groupId, -$newIndex - 1000]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        // ── Entity tags ────────────────────────────────────────────────────
        case 'add_block_entity':
            $blockId    = (int)($_POST['block_id']    ?? 0);
            $entityType = trim($_POST['entity_type']  ?? '');
            $entityId   = (int)($_POST['entity_id']   ?? 0);
            if (!$blockId || !$entityType || !$entityId) throw new Exception('block_id, entity_type, entity_id required');

            $allowed = ['characters', 'factions', 'locations', 'animas', 'sketches', 'kg_nodes'];
            
            
            
            
            
            if (!in_array($entityType, $allowed)) throw new Exception('Invalid entity_type');

            $labelCol = 'name';
            $lStmt = $pdo->prepare("SELECT $labelCol AS lbl FROM `$entityType` WHERE id = ?");
            $lStmt->execute([$entityId]);
            $lRow = $lStmt->fetch(PDO::FETCH_ASSOC);
            $label = $lRow['lbl'] ?? '';

            $stmt = $pdo->prepare("INSERT IGNORE INTO plush_highlight_block_entities (block_id, entity_type, entity_id, entity_label) VALUES (?, ?, ?, ?)");
            $stmt->execute([$blockId, $entityType, $entityId, $label]);
            echo json_encode(['success' => true, 'entity_label' => $label]);
            break;

        case 'remove_block_entity':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('id required');
            $stmt = $pdo->prepare("DELETE FROM plush_highlight_block_entities WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_block_entities':
            $blockId = (int)($_GET['block_id'] ?? 0);
            if (!$blockId) throw new Exception('block_id required');
            $stmt = $pdo->prepare("SELECT * FROM plush_highlight_block_entities WHERE block_id = ? ORDER BY entity_type, entity_label ASC");
            $stmt->execute([$blockId]);
            echo json_encode(['success' => true, 'entities' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'search_entities':
            $type  = trim($_GET['entity_type'] ?? '');
            $q     = trim($_GET['q'] ?? '');
            $limit = min((int)($_GET['limit'] ?? 15), 50);

            $allowed = ['characters', 'factions', 'locations', 'animas', 'sketches', 'kg_nodes'];
            
            
            
            
            if (!in_array($type, $allowed)) throw new Exception('Invalid entity_type');

            if (strlen($q) < 1) {
                $stmt = $pdo->query("SELECT id, name FROM `$type` ORDER BY name ASC LIMIT $limit");
            } elseif (is_numeric($q)) {
                $stmt = $pdo->prepare("SELECT id, name FROM `$type` WHERE id = ? OR name LIKE ? ORDER BY name ASC LIMIT $limit");
                $stmt->execute([(int)$q, '%' . $q . '%']);
            } else {
                $stmt = $pdo->prepare("SELECT id, name FROM `$type` WHERE name LIKE ? ORDER BY name ASC LIMIT $limit");
                $stmt->execute(['%' . $q . '%']);
            }
            echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Collections ────────────────────────────────────────────────────
        case 'create_collection':
            $title = trim($_POST['title'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (!$title) throw new Exception('Title required');

            $stmt = $pdo->prepare("INSERT INTO plush_collections (title, description) VALUES (?, ?)");
            $stmt->execute([$title, $desc]);
            echo json_encode(['success' => true, 'collection_id' => (int)$pdo->lastInsertId()]);
            break;

        case 'assign_to_collection':
            $collectionId = (int)($_POST['collection_id'] ?? 0);
            $storyId  = (int)($_POST['story_id']  ?? 0);
            $sortOrder   = (int)($_POST['sort_order']   ?? 0);
            $label       = trim($_POST['arc_label'] ?? '');
            if (!$collectionId || !$storyId) throw new Exception('collection_id and story_id required');

            $stmt = $pdo->prepare(
                "INSERT INTO plush_collections_2_stories (collection_id, story_id, sort_order, arc_label)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), arc_label = VALUES(arc_label)"
            );
            $stmt->execute([$collectionId, $storyId, $sortOrder, $label ?: null]);
            echo json_encode(['success' => true]);
            break;

        case 'remove_from_collection':
            $collectionId = (int)($_POST['collection_id'] ?? 0);
            $storyId  = (int)($_POST['story_id']  ?? 0);
            if (!$collectionId || !$storyId) throw new Exception('collection_id and story_id required');

            $stmt = $pdo->prepare("DELETE FROM plush_collections_2_stories WHERE collection_id = ? AND story_id = ?");
            $stmt->execute([$collectionId, $storyId]);
            echo json_encode(['success' => true]);
            break;

        // ── Translation Languages (Global Platform Use) ────────────────────
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

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}