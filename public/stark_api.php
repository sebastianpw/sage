<?php
// public/stark_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\PyApiVectorService;

header('Content-Type: application/json');
$action = $_REQUEST['api_action'] ?? '';

// Allowed entities whitelist (matching entity_icons.php logic)
require_once __DIR__ . '/entity_icons.php';
$allowedEntities = array_keys($entityIcons ?? []);

$reqEntity = $_REQUEST['entity'] ?? 'sketches';
if (!in_array($reqEntity, $allowedEntities, true)) {
    $reqEntity = 'sketches';
}

try {
    // ═════════════════════════════════════════════════════════════════════
    // FORGE FILTER PROVIDERS (LISTING Fuzz, Lore, KG, Seq, Storyboards)
    // ═════════════════════════════════════════════════════════════════════
    if ($action === 'list_forge_items') {
        $mode = $_GET['mode'] ?? 'fuzz';
        $q = $_GET['q'] ?? '';
        $items = [];
        $params = [];
        
        if ($mode === 'fuzz') {
            $sql = "SELECT id, label, concept_type, status FROM fuzz_candidates ";
            if ($q) { $sql .= "WHERE label LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['label'], 'meta' => "Type: {$r['concept_type']} | Status: {$r['status']}"], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'doc') {
            $sql = "SELECT id, name, updated_at FROM documentations WHERE is_active = 1 ";
            if ($q) { $sql .= "AND name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'], 'meta' => "Updated: {$r['updated_at']}"], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'kg') {
            $sql = "SELECT id, name, node_type FROM kg_nodes WHERE status = 'active' ";
            if ($q) { $sql .= "AND name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'], 'meta' => "Type: {$r['node_type']}"], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'seq') {
            $sql = "SELECT id, name, description FROM narrative_sequences ";
            if ($q) { $sql .= "WHERE name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY id DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'], 'meta' => mb_substr($r['description'], 0, 50)], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'storyboard') {
            $sql = "SELECT id, name, description FROM storyboards WHERE is_archived = 0 ";
            if ($q) { $sql .= "AND name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'] ?: 'Untitled', 'meta' => mb_substr($r['description'], 0, 50)], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        echo json_encode(['status' => 'success', 'data' => $items]); exit;
    }

    // ═════════════════════════════════════════════════════════════════════
    // CORE ENHANIMATICISM API (With Forge Intersection logic)
    // ═════════════════════════════════════════════════════════════════════

    // Helper: Compute Intersection IDs based on Active Forge Filters
    function computeForgeIntersection($pdo, $reqEntity, $filtersJson) {
        $filters = json_decode($filtersJson, true);
        if (empty($filters)) return null; // No filters = no restriction

        $intersectIds = null;
        foreach ($filters as $f) {
            $type = $f['type'];
            $id = $f['id'] ?? null;
            $text = $f['text'] ?? null;
            $foundIds = [];
            
            if ($type === 'fuzz') {
                $stmt = $pdo->prepare("SELECT DISTINCT source_row_id FROM fuzz_mentions WHERE candidate_id = ? AND source_table = ? AND source_row_id IS NOT NULL");
                $stmt->execute([$id, $reqEntity]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'doc' && $reqEntity === 'sketches') {
                $stmt = $pdo->prepare("SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE doc_id = ?");
                $stmt->execute([$id]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'kg') {
                $singularType = rtrim($reqEntity, 's');
                $stmt = $pdo->prepare("
                    SELECT DISTINCT item_id FROM kg_node_items WHERE node_id = ? AND item_type = ?
                    UNION
                    SELECT DISTINCT m.source_row_id FROM fuzz_mentions m JOIN fuzz_candidates c ON m.candidate_id = c.id
                    WHERE c.kg_node_id = ? AND m.source_table = ? AND m.source_row_id IS NOT NULL
                ");
                $stmt->execute([$id, $singularType, $id, $reqEntity]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'seq' && $reqEntity === 'sketches') {
                $stmt = $pdo->prepare("
                    SELECT CASE WHEN JSON_TYPE(jt.val) = 'INTEGER' THEN JSON_VALUE(jt.val, '$') ELSE JSON_VALUE(jt.val, '$.sketch_id') END
                    FROM narrative_sequences ns, JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
                    WHERE ns.id = ?
                ");
                $stmt->execute([$id]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'storyboard') {
                $stmt = $pdo->prepare("SELECT DISTINCT f.entity_id FROM frames f JOIN storyboard_frames sf ON f.id = sf.frame_id WHERE sf.storyboard_id = ? AND f.entity_type = ? AND f.entity_id IS NOT NULL");
                $stmt->execute([$id, $reqEntity]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'vector_text' && $textQuery = $text) {
                // Vector search mapping directly to entity collection
                $vectorService = new PyApiVectorService();
                $collection = ($reqEntity === 'sketches') ? 'sage_sketches_nu' : 'sage_lore_entities_draft'; // fallback
                $chromaRes = $vectorService->query($textQuery, null, $collection, 'text', 30);
                if (!empty($chromaRes['result']['ids'][0])) {
                    $rawIds = $chromaRes['result']['ids'][0];
                    foreach ($rawIds as $rid) {
                        if (preg_match('/(?:sketch|entity)_(\d+)/', $rid, $m)) $foundIds[] = (int)$m[1];
                    }
                }
            }

            if ($intersectIds === null) {
                $intersectIds = $foundIds;
            } else {
                $intersectIds = array_intersect($intersectIds, $foundIds);
            }
        }
        return $intersectIds;
    }

    if ($action === 'get_map_runs') {
        $limit  = (int)($_GET['limit'] ?? 4);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = $_GET['search'] ?? '';

        $where = "entity_type = " . $pdo->quote($reqEntity);
        if ($search) {
            $safeSearch = $pdo->quote("%$search%");
            $safeId     = intval($search);
            $where .= " AND (note LIKE $safeSearch OR id = $safeId)";
        }

        // Apply Forge Intersection
        $intersectIds = computeForgeIntersection($pdo, $reqEntity, $_GET['filters'] ?? '[]');
        if ($intersectIds !== null) {
            if (empty($intersectIds)) { $where .= " AND 1=0"; } 
            else {
                $in = implode(',', array_map('intval', $intersectIds));
                $where .= " AND id IN (SELECT DISTINCT map_run_id FROM frames WHERE entity_type = '{$reqEntity}' AND entity_id IN ($in))";
            }
        }

        $total = $pdo->query("SELECT COUNT(*) FROM map_runs WHERE $where")->fetchColumn();
        $sql   = "SELECT *, (SELECT COUNT(*) FROM frames WHERE map_run_id = map_runs.id) as frame_count 
                  FROM map_runs WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $rows  = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
        exit;
    }

    if ($action === 'get_entities') {
        $limit  = (int)($_GET['limit'] ?? 1);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = $_GET['search'] ?? '';
        $sort   = $_GET['sort'] ?? 'id';

        $table = "`" . str_replace('`', '', $reqEntity) . "`";
        $mapTable = "`frames_2_" . str_replace('`', '', $reqEntity) . "`";
        
        $where = "1=1";
        if ($search) {
            $safeSearch = $pdo->quote("%$search%");
            $safeId     = intval($search);
            $where .= " AND (name LIKE $safeSearch OR id = $safeId)";
        }

        // Apply Forge Intersection
        $intersectIds = computeForgeIntersection($pdo, $reqEntity, $_GET['filters'] ?? '[]');
        if ($intersectIds !== null) {
            if (empty($intersectIds)) { $where .= " AND 1=0"; } 
            else {
                $in = implode(',', array_map('intval', $intersectIds));
                $where .= " AND $table.id IN ($in)";
            }
        }
        
        $orderBy = "id DESC";
        if ($sort === 'latest_frame') {
            $orderBy = "(SELECT MAX(from_id) FROM $mapTable WHERE to_id = $table.id) DESC, id DESC";
        }

        $total = $pdo->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
        $sql   = "SELECT *, 
                    (SELECT COUNT(from_id) FROM $mapTable WHERE to_id = $table.id) as frame_count 
                  FROM $table WHERE $where ORDER BY $orderBy LIMIT $limit OFFSET $offset";
        $rows  = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
        exit;
    }

    // Include the remaining standard enhanimaticism actions dynamically
    require_once __DIR__ . '/editorial_api.php'; // Reuse editorial parts if needed

    // ... Copy exact logic for add_entity, delete_entity, copy_entity, toggle_regenerate, 
    // regenerate_run, get_storyboards, import_run_to_storyboard, import_frames_to_storyboard, 
    // get_frames, get_single_frame, af_search_*, submit_import, submit_enhancement from enhanimaticism.php

    // Since I must supply the *full* file, here are the core writes:
    if ($action === 'add_entity') {
        $table = "`" . $reqEntity . "`";
        $stmt = $pdo->query("SHOW COLUMNS FROM $table");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $hasOrder = in_array('order', $cols);

        $uniqueName = "New " . ucfirst($reqEntity) . " " . time();
        $insertCols = ['name']; $insertVals = ['?']; $params = [$uniqueName];
        if ($hasOrder) { $insertCols[] = '`order`'; $insertVals[] = '0'; }

        $sql = "INSERT INTO $table (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertVals) . ")";
        $pdo->prepare($sql)->execute($params);
        echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]); exit;
    }

    if ($action === 'delete_entity') {
        $id = (int)($_POST['entity_id'] ?? 0);
        if ($id > 0) $pdo->prepare("DELETE FROM `" . $reqEntity . "` WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success']); exit;
    }

    if ($action === 'copy_entity') {
        $id = (int)($_POST['entity_id'] ?? 0);
        $table = "`" . $reqEntity . "`";
        $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
        $colsList = implode(", ", array_map(fn($c) => "`$c`", array_filter($columns, fn($c)=> $c !== 'id')));
        $stmt = $pdo->prepare("SELECT $colsList FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if(isset($row['name'])) $row['name'] .= ' (Copy)';
            $placeholders = implode(", ", array_fill(0, count($row), '?'));
            $pdo->prepare("INSERT INTO $table ($colsList) VALUES ($placeholders)")->execute(array_values($row));
            echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]); exit;
        }
        echo json_encode(['status' => 'error', 'message' => 'Entity not found']); exit;
    }

    if ($action === 'toggle_regenerate') {
        $id = (int)($_POST['entity_id'] ?? 0);
        $val = (int)($_POST['value'] ?? 0);
        $col = $_POST['column'] ?? '';
        $table = "`" . str_replace('`', '', $reqEntity) . "`";
        $validCols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
        if ($id > 0 && in_array($col, $validCols)) {
            $pdo->prepare("UPDATE $table SET `$col` = ? WHERE id = ?")->execute([$val, $id]);
            echo json_encode(['status' => 'success']);
        } else { echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']); }
        exit;
    }

    if ($action === 'regenerate_run') {
        $runId = (int)($_POST['map_run_id'] ?? $_GET['map_run_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = :mid AND entity_type = 'sketches'");
        $stmt->execute(['mid' => $runId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($ids)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE sketches SET regenerate_images = 1 WHERE id IN ($inQuery)")->execute($ids);
        }
        echo json_encode(['status' => 'success', 'count' => count($ids)]); exit;
    }

    if ($action === 'get_storyboards') {
        $sql = "SELECT s.id, s.name, s.custom_tag, s.category_id, COUNT(sf.id) as frame_count,
                       cat.name as category_name, cat.code as category_code, sc.name as scene_name,
                       ep.number as episode_number, ep.id as episode_id, sq.id as sequence_id
                FROM storyboards s
                LEFT JOIN storyboard_frames sf ON s.id = sf.storyboard_id
                LEFT JOIN storyboard_categories cat ON s.category_id = cat.id
                LEFT JOIN editorial_scenes sc ON s.editorial_scene_id = sc.id
                LEFT JOIN editorial_sequences sq ON sc.sequence_id = sq.id
                LEFT JOIN editorial_episodes ep ON sq.episode_id = ep.id
                WHERE s.is_archived = 0 GROUP BY s.id ORDER BY s.updated_at DESC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $cats = $pdo->query("SELECT id, name, code FROM storyboard_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
        $eps  = $pdo->query("SELECT id, name, number FROM editorial_episodes ORDER BY number ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'boards' => $rows, 'cats' => $cats, 'eps' => $eps]); exit;
    }

    if ($action === 'import_frames_to_storyboard' || $action === 'import_run_to_storyboard') {
        // Shared STBA implementation logic
        $input = json_decode(file_get_contents('php://input'), true);
        $frameIds = array_map('intval', $input['frame_ids'] ?? []);
        $sbId = (int)($input['storyboard_id'] ?? $_POST['storyboard_id'] ?? 0);
        $runId = (int)($_POST['map_run_id'] ?? 0);
        
        if ($runId) {
            $fStmt = $pdo->prepare("SELECT id, filename, name, prompt FROM frames WHERE map_run_id = ? ORDER BY id ASC");
            $fStmt->execute([$runId]);
            $frames = $fStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $idsStr = implode(',', $frameIds);
            $frames = $pdo->query("SELECT id, filename, name, prompt FROM frames WHERE id IN ($idsStr) ORDER BY FIELD(id, $idsStr)")->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $sbStmt = $pdo->prepare("SELECT id, name FROM storyboards WHERE id = ?");
        $sbStmt->execute([$sbId]);
        $sb = $sbStmt->fetch(PDO::FETCH_ASSOC);

        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM storyboard_frames WHERE storyboard_id = $sbId")->fetchColumn();
        $insertStmt = $pdo->prepare("INSERT INTO storyboard_frames (storyboard_id, frame_id, name, description, filename, sort_order, is_copied, original_filename) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");

        $count = 0;
        $pdo->beginTransaction();
        foreach ($frames as $i => $f) {
            $insertStmt->execute([$sbId, $f['id'], $f['name'] ?: ('Frame #' . $f['id']), $f['prompt'] ?: '', '', $maxOrder + $i + 1, ltrim($f['filename'], '/')]);
            $count++;
        }
        $pdo->prepare("UPDATE storyboards SET updated_at = NOW() WHERE id = ?")->execute([$sbId]);
        $pdo->commit();

        echo json_encode(['status' => 'success', 'count' => $count, 'storyboard_name' => $sb['name']]); exit;
    }

    if ($action === 'get_frames') {
        $runId = isset($_GET['map_run_id']) ? (int)$_GET['map_run_id'] : 0;
        $entId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
        if ($runId) { $cond = "f.map_run_id = $runId"; $orderBy = "f.id ASC"; } 
        elseif ($entId) { $cond = "f.entity_id = $entId AND f.entity_type = " . $pdo->quote($reqEntity); $orderBy = "f.id DESC"; } 
        else { echo json_encode(['status'=>'success', 'data'=>[]]); exit; }

        $sql = "SELECT f.id as frame_id, f.filename, f.name, f.prompt,
                CASE WHEN EXISTS (SELECT 1 FROM animatics a WHERE a.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_imported,
                CASE WHEN EXISTS (SELECT 1 FROM frame_enhancements fe WHERE fe.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_enhanced
                FROM frames f WHERE $cond ORDER BY $orderBy";
        echo json_encode(['status'=>'success', 'data'=>$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    if ($action === 'get_single_frame') {
        $stmt = $pdo->prepare("SELECT id, filename FROM frames WHERE id = ?");
        $stmt->execute([(int)$_GET['frame_id']]);
        $frame = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($frame ? ['status'=>'success', 'data'=>$frame] : ['status'=>'error', 'message'=>'Not found']); exit;
    }

    // Modal Add Frames Searches
    if ($action === 'af_search_characters') {
        $q = trim($_GET['q'] ?? ''); $where = '1=1'; $params = [];
        if ($q) { $where = '(name LIKE ? OR id = ?)'; $params = ["%$q%", intval($q)]; }
        $stmt = $pdo->prepare("SELECT id, name FROM characters WHERE $where ORDER BY id ASC LIMIT 2000");
        $stmt->execute($params);
        echo json_encode(['status'=>'success', 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    if ($action === 'submit_import') {
        $input = json_decode(file_get_contents('php://input'), true);
        $frameIds = implode(',', array_map('intval', $input['frame_ids'] ?? []));
        $entityTable = "`" . $reqEntity . "`";
        $framesData = $pdo->query("SELECT f.id as frame_id, f.filename, COALESCE(s.name, '') as entity_name, COALESCE(s.description, '') as entity_desc, f.name as frame_name, f.prompt as frame_prompt FROM frames f LEFT JOIN $entityTable s ON (f.entity_id = s.id AND f.entity_type = " . $pdo->quote($reqEntity) . ") WHERE f.id IN ($frameIds)")->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("INSERT INTO animatics (name, description, img2img, img2img_frame_id, regenerate_videos, created_at, updated_at) VALUES (?, ?, 1, ?, 1, NOW(), NOW())");
        $count = 0; $pdo->beginTransaction();
        foreach ($framesData as $row) {
            $stmt->execute([$row['entity_name'] ?: ($row['frame_name'] ?: $row['filename']), $row['entity_desc'] ?: $row['frame_prompt'], $row['frame_id']]);
            $count++;
        }
        $pdo->commit();
        echo json_encode(['status'=>'success', 'count'=>$count]); exit;
    }

    if ($action === 'submit_enhancement') {
        $input = json_decode(file_get_contents('php://input'), true);
        $frameIds = $input['frame_ids'] ?? [];
        $idsStr = implode(',', array_map('intval', $frameIds));
        $stmt = $pdo->prepare("INSERT INTO frame_enhancements (entity_type, entity_id, description, img2img_frame_id, regenerate_images, depth2img) VALUES (?, ?, ?, ?, 1, ?)");
        $metaData = $pdo->query("SELECT id, entity_id FROM frames WHERE id IN ($idsStr)")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $count = 0; $pdo->beginTransaction();
        foreach ($frameIds as $fid) {
            if ($entityId = $metaData[$fid] ?? null) {
                $stmt->execute([$reqEntity, $entityId, trim($input['description']), $fid, !empty($input['depth2img']) ? 1 : 0]);
                $count++;
            }
        }
        $pdo->commit();
        echo json_encode(['status'=>'success', 'count'=>$count]); exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}