<?php
// public/popkorn_api.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$pdo = $spw->getPDO();

try {
    // -------------------------------------------------------------------------
    // 0. AUTO-CREATE TABLES (Zero Setup)
    // -------------------------------------------------------------------------
    $pdo->exec("CREATE TABLE IF NOT EXISTS `popkorn_pots` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `popkorn_pot_videos` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `pot_id` int(11) NOT NULL,
      `video_id` int(11) NOT NULL,
      `sort_order` int(11) DEFAULT 0,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_pot_video` (`pot_id`,`video_id`),
      KEY `idx_pot` (`pot_id`),
      KEY `idx_video` (`video_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // -------------------------------------------------------------------------
    // 1. LIST FUZZ CANDIDATES (Auto Categories)
    // -------------------------------------------------------------------------
    if ($action === 'list_fuzz_candidates') {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;
        
        $params = [];
        $whereSQL = "WHERE status NOT IN ('rejected','deferred')";
        
        if ($search !== '') {
            $whereSQL .= " AND label LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM fuzz_candidates c $whereSQL");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT c.id, c.label, c.concept_type, c.status,
                   (SELECT COUNT(*) FROM fuzz_mentions m WHERE m.candidate_id = c.id) as mention_count
            FROM fuzz_candidates c
            $whereSQL
            ORDER BY mention_count DESC, c.updated_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => $rows,
            'total_pages' => ceil($total / $limit),
            'page' => $page
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. GET VIDEOS FOR CATEGORY OR SEARCH (Paginated)
    // -------------------------------------------------------------------------
    if ($action === 'get_videos') {
        $fuzzCandId  = (int)($_GET['fuzz_cand_id'] ?? 0);
        $searchQuery = trim($_GET['search_query'] ?? '');
        $page        = max(1, (int)($_GET['page'] ?? 1));
        $limit       = 24; // Fits perfectly in a 3-column grid
        $offset      = ($page - 1) * $limit;
        
        $whereParts = [];
        $params = [];

        if ($fuzzCandId) {
            $whereParts[] = "v.id IN (
                SELECT DISTINCT va2.from_id
                FROM videos_2_animatics va2
                JOIN animatics an ON va2.to_id = an.id
                JOIN frames fr ON an.img2img_frame_id = fr.id
                WHERE (
                    (fr.entity_type = 'sketches' AND fr.entity_id IN (
                        SELECT DISTINCT source_row_id FROM fuzz_mentions WHERE candidate_id = ? AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients') AND source_row_id IS NOT NULL
                    ))
                    OR fr.id IN (
                        SELECT f2s.from_id FROM frames_2_sketches f2s WHERE f2s.to_id IN (
                            SELECT DISTINCT source_row_id FROM fuzz_mentions WHERE candidate_id = ? AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients') AND source_row_id IS NOT NULL
                        )
                    )
                )
            )";
            $params[] = $fuzzCandId;
            $params[] = $fuzzCandId;
        } elseif ($searchQuery) {
            $like = "%$searchQuery%";
            $whereParts[] = "v.id IN (
                SELECT DISTINCT va2.from_id
                FROM videos_2_animatics va2
                JOIN animatics an ON va2.to_id = an.id
                JOIN frames fr ON an.img2img_frame_id = fr.id
                LEFT JOIN frames_2_sketches f2s ON f2s.from_id = fr.id
                JOIN sketches s ON (s.id = f2s.to_id OR (fr.entity_type = 'sketches' AND fr.entity_id = s.id))
                WHERE s.description LIKE ? OR s.name LIKE ? OR an.name LIKE ?
            )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        } else {
            echo json_encode(['status' => 'success', 'data' => [], 'total' => 0, 'total_pages' => 1, 'page' => 1]);
            exit;
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        
        // 1. Get total count
        $countSql = "SELECT COUNT(*) FROM videos v $where";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($total / $limit);

        // 2. Fetch paginated data (Includes highly efficient EXISTS subquery for pot usage)
        $sql = "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, v.file_size, v.description,
                       (CASE WHEN EXISTS(SELECT 1 FROM popkorn_pot_videos ppv WHERE ppv.video_id = v.id) THEN 1 ELSE 0 END) as in_any_pot
                FROM videos v $where 
                ORDER BY v.id DESC 
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'status' => 'success', 
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. POTS CRUD
    // -------------------------------------------------------------------------
    if ($action === 'list_pots') {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;
        
        $params = [];
        $whereSQL = "WHERE 1=1";
        if ($search !== '') {
            $whereSQL .= " AND p.name LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM popkorn_pots p $whereSQL");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT p.*, COUNT(pv.id) as video_count
            FROM popkorn_pots p
            LEFT JOIN popkorn_pot_videos pv ON p.id = pv.pot_id
            $whereSQL
            GROUP BY p.id
            ORDER BY p.updated_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        
        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total_pages' => ceil($total / $limit),
            'page' => $page
        ]);
        exit;
    }

    if ($action === 'create_pot') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        if (!$name) throw new Exception('Name is required');
        
        $stmt = $pdo->prepare("INSERT INTO popkorn_pots (name) VALUES (?)");
        $stmt->execute([$name]);
        $newId = $pdo->lastInsertId();
        
        echo json_encode(['status' => 'success', 'id' => $newId, 'name' => $name]);
        exit;
    }

    if ($action === 'rename_pot') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id || !$name) throw new Exception('Invalid parameters');
        
        $pdo->prepare("UPDATE popkorn_pots SET name = ?, updated_at = NOW() WHERE id = ?")->execute([$name, $id]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete_pot') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if (!$id) throw new Exception('Invalid pot ID');
        
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM popkorn_pot_videos WHERE pot_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM popkorn_pots WHERE id = ?")->execute([$id]);
        $pdo->commit();
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. POT VIDEOS MANAGEMENT
    // -------------------------------------------------------------------------
    if ($action === 'get_pot_videos') {
        $potId = (int)($_GET['pot_id'] ?? 0);
        if (!$potId) throw new Exception('Missing pot ID');

        $sql = "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, pv.id as mapping_id
                FROM videos v
                JOIN popkorn_pot_videos pv ON v.id = pv.video_id
                WHERE pv.pot_id = ?
                ORDER BY pv.sort_order ASC, pv.created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$potId]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'toggle_pot_video') {
        $input = json_decode(file_get_contents('php://input'), true);
        $potId = (int)($input['pot_id'] ?? 0);
        $videoId = (int)($input['video_id'] ?? 0);
        
        if (!$potId || !$videoId) throw new Exception('Invalid parameters');

        $stmt = $pdo->prepare("SELECT id FROM popkorn_pot_videos WHERE pot_id = ? AND video_id = ?");
        $stmt->execute([$potId, $videoId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $pdo->prepare("DELETE FROM popkorn_pot_videos WHERE id = ?")->execute([$exists]);
            $added = false;
        } else {
            $pdo->prepare("INSERT INTO popkorn_pot_videos (pot_id, video_id) VALUES (?, ?)")->execute([$potId, $videoId]);
            $added = true;
        }
        $pdo->prepare("UPDATE popkorn_pots SET updated_at = NOW() WHERE id = ?")->execute([$potId]);

        echo json_encode(['status' => 'success', 'added' => $added]);
        exit;
    }

    if ($action === 'batch_add_to_pot') {
        $input = json_decode(file_get_contents('php://input'), true);
        $potId = (int)($input['pot_id'] ?? 0);
        $videoIds = $input['video_ids'] ?? [];
        
        if (!$potId || empty($videoIds)) throw new Exception('Invalid parameters');

        $stmt = $pdo->prepare("INSERT IGNORE INTO popkorn_pot_videos (pot_id, video_id) VALUES (?, ?)");
        $count = 0;
        foreach ($videoIds as $vid) {
            $stmt->execute([$potId, (int)$vid]);
            if ($stmt->rowCount() > 0) $count++;
        }
        if ($count > 0) {
            $pdo->prepare("UPDATE popkorn_pots SET updated_at = NOW() WHERE id = ?")->execute([$potId]);
        }

        echo json_encode(['status' => 'success', 'added_count' => $count]);
        exit;
    }

    if ($action === 'clear_pot') {
        $input = json_decode(file_get_contents('php://input'), true);
        $potId = (int)($input['pot_id'] ?? 0);
        if (!$potId) throw new Exception('Invalid pot ID');

        $pdo->prepare("DELETE FROM popkorn_pot_videos WHERE pot_id = ?")->execute([$potId]);
        $pdo->prepare("UPDATE popkorn_pots SET updated_at = NOW() WHERE id = ?")->execute([$potId]);

        echo json_encode(['status' => 'success']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}