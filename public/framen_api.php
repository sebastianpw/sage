<?php
// public/framen_api.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'fetch_enhancements') {
        $limit = (int)($_POST['limit'] ?? 3);
        $page = (int)($_POST['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        
        $sqlSelect = "SELECT fe.*, f.filename as source_filename 
                      FROM frame_enhancements fe 
                      LEFT JOIN frames f ON f.id = fe.img2img_frame_id 
                      ORDER BY fe.id DESC LIMIT :limit OFFSET :offset";
        $countSql = "SELECT COUNT(*) FROM frame_enhancements";
        
        $totalRows = $pdo->query($countSql)->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $limit));
        
        $stmt = $pdo->prepare($sqlSelect);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'rows' => $rows,
            'totalRows' => (int)$totalRows,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ]);
        exit;
    }
    
    if ($action === 'update_enhancement') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];
        $allowedFields = ['description', 'regenerate_images', 'depth2img'];
        if (in_array($field, $allowedFields)) {
            $stmt = $pdo->prepare("UPDATE frame_enhancements SET `$field` = ? WHERE id = ?");
            $stmt->execute([$value, $id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid field']);
        }
        exit;
    }
    
    if ($action === 'delete_enhancement') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM frame_enhancements WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    if ($action === 'get_frames') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM frame_enhancements WHERE id = ?");
        $stmt->execute([$id]);
        $enh = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enh) {
            echo json_encode(['status' => 'error', 'message' => 'Not found']);
            exit;
        }
        
        $frames = [];
        $excludeIds = [];
        
        // 1. Source Frame
        if ($enh['img2img_frame_id']) {
            $sStmt = $pdo->prepare("SELECT id as frame_id, filename, name, prompt, 'source' as relation FROM frames WHERE id = ?");
            $sStmt->execute([$enh['img2img_frame_id']]);
            if ($src = $sStmt->fetch(PDO::FETCH_ASSOC)) {
                $frames[] = $src;
                $excludeIds[] = $src['frame_id'];
            }
        }
        
        // 2. Extra Reference Frames
        $eStmt = $pdo->prepare("
            SELECT f.id as frame_id, f.filename, f.name, f.prompt, 'extra' as relation 
            FROM frame_enhancement_frames fef
            JOIN frames f ON f.id = fef.frame_id
            WHERE fef.frame_enhancement_id = ?
            ORDER BY fef.created_at ASC
        ");
        $eStmt->execute([$enh['id']]);
        $extras = $eStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($extras as $ext) {
            $frames[] = $ext;
            $excludeIds[] = $ext['frame_id'];
        }
        
        // 3. Generated Output Frames
        $q = "SELECT id as frame_id, filename, name, prompt, 'generated' as relation 
              FROM frames 
              WHERE entity_type = ? AND entity_id = ?";
        $params = [$enh['entity_type'], $enh['entity_id']];
        
        if (!empty($excludeIds)) {
            $in = str_repeat('?,', count($excludeIds) - 1) . '?';
            $q .= " AND id NOT IN ($in)";
            $params = array_merge($params, $excludeIds);
        }
        $q .= " ORDER BY id DESC LIMIT 100";
        
        $fStmt = $pdo->prepare($q);
        $fStmt->execute($params);
        $gen = $fStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $frames = array_merge($frames, $gen);
        
        echo json_encode(['status' => 'success', 'data' => $frames]);
        exit;
    }

    // ── Extra Frames Management ──
    if ($action === 'add_extra_frames') {
        $enhId = (int)($_POST['id'] ?? 0);
        $frameIds = $_POST['frame_ids'] ?? [];
        if (!$enhId || empty($frameIds)) {
            echo json_encode(['status' => 'error', 'message' => 'No frames or enhancement provided']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO frame_enhancement_frames (frame_enhancement_id, frame_id) VALUES (?, ?)");
        $count = 0;
        foreach ($frameIds as $fid) {
            $fid = (int)$fid;
            if ($fid > 0) {
                $stmt->execute([$enhId, $fid]);
                $count++;
            }
        }
        echo json_encode(['status' => 'success', 'count' => $count]);
        exit;
    }

    if ($action === 'remove_extra_frames') {
        $enhId = (int)($_POST['id'] ?? 0);
        $frameIds = $_POST['frame_ids'] ?? [];
        if (!$enhId || empty($frameIds)) {
            echo json_encode(['status' => 'error', 'message' => 'No frames or enhancement provided']);
            exit;
        }
        $frameIds = array_map('intval', $frameIds);
        $inPh = implode(',', array_fill(0, count($frameIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM frame_enhancement_frames WHERE frame_enhancement_id = ? AND frame_id IN ($inPh)");
        $stmt->execute(array_merge([$enhId], $frameIds));
        echo json_encode(['status' => 'success', 'count' => $stmt->rowCount()]);
        exit;
    }

    // ── Modal Frame Discovery Endpoints (Cloned from enhanimaticism_api) ──
    if ($action === 'get_single_frame') {
        $fid = (int)($_REQUEST['frame_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, filename FROM frames WHERE id = ?");
        $stmt->execute([$fid]);
        $frame = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($frame) {
            echo json_encode(['status' => 'success', 'data' => $frame]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Frame not found']);
        }
        exit;
    }

    if ($action === 'af_search_characters') {
        $q = trim($_GET['q'] ?? '');
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(name LIKE ? OR id = ?)';
            $params = ["%$q%", intval($q)];
        }
        $stmt = $pdo->prepare("SELECT id, name FROM characters WHERE $where ORDER BY id ASC LIMIT 2000");
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'af_search_variants') {
        $area = $_GET['area'] ?? 'poses';
        $q = trim($_GET['q'] ?? '');
        $tableMap = ['poses' => 'poses', 'expressions' => 'anivoc_expressions', 'anima_poses' => 'poses_anima'];
        $table = $tableMap[$area] ?? 'poses';
        $safeTable = '`' . str_replace('`', '', $table) . '`';
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(name LIKE ? OR id = ?)';
            $params = ["%$q%", intval($q)];
        }
        $stmt = $pdo->prepare("SELECT id, name FROM $safeTable WHERE $where ORDER BY id ASC LIMIT 2000");
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'af_get_variant_frames') {
        $area = $_GET['area'] ?? 'poses';
        $char_id = (int)($_GET['char_id'] ?? 0);
        $variant_id = (int)($_GET['variant_id'] ?? 0);
        if (!$char_id) {
            echo json_encode(['status' => 'success', 'frames' => []]);
            exit;
        }

        $cfg = [
            'poses'       => ['entity_table' => 'character_poses',       'junction_table' => 'frames_2_character_poses',       'entity_fk' => 'pose_id'],
            'expressions' => ['entity_table' => 'character_expressions', 'junction_table' => 'frames_2_character_expressions', 'entity_fk' => 'expression_id'],
            'anima_poses' => ['entity_table' => 'character_anima_poses', 'junction_table' => 'frames_2_character_anima_poses', 'entity_fk' => 'pose_id'],
        ];
        $c = $cfg[$area] ?? $cfg['poses'];
        $et = '`' . str_replace('`', '', $c['entity_table']) . '`';
        $jt = '`' . str_replace('`', '', $c['junction_table']) . '`';
        $fk = '`' . str_replace('`', '', $c['entity_fk']) . '`';

        $where = "character_id = ?";
        $params = [$char_id];
        if ($variant_id > 0) {
            $where .= " AND $fk = ?";
            $params[] = $variant_id;
        }
        $entityIds = $pdo->prepare("SELECT id FROM $et WHERE $where ORDER BY id ASC");
        $entityIds->execute($params);
        $ids = $entityIds->fetchAll(PDO::FETCH_COLUMN);
        if (empty($ids)) {
            echo json_encode(['status' => 'success', 'frames' => []]);
            exit;
        }

        $inPh = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT f.id as frame_id, f.filename FROM $jt m JOIN frames f ON f.id=m.from_id WHERE m.to_id IN ($inPh) ORDER BY f.id DESC");
        $stmt->execute($ids);
        echo json_encode(['status' => 'success', 'frames' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;