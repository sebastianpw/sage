<?php
// ============================================================================
// File: public/cvbatch_api.php
// ============================================================================
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$mysqli = $spw->getMysqli();

ini_set('display_errors', '0');

// Ensure sessions table exists (zero-touch deployment)
$pdo->exec("CREATE TABLE IF NOT EXISTS `cv_batch_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `config_data` longtext DEFAULT NULL,
  `state_data` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Ensure session logs table exists for detailed debugging
$pdo->exec("CREATE TABLE IF NOT EXISTS `cv_batch_session_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `frame_id` int(11) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_frame_id` (`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

// Helper to fetch the latest frame linked to a sketch ID
function getLatestFrameForSketch(PDO $pdo, $sId) {
    $stmt = $pdo->prepare("SELECT id FROM frames WHERE entity_type = 'sketches' AND entity_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sId]);
    $fId = $stmt->fetchColumn();
    if (!$fId) {
        $stmt2 = $pdo->prepare("SELECT from_id FROM frames_2_sketches WHERE to_id = ? ORDER BY from_id DESC LIMIT 1");
        $stmt2->execute([$sId]);
        $fId = $stmt2->fetchColumn();
    }
    return $fId;
}

// Helper to parse Narrative Sequence JSON for sketch IDs
function _extractSketchIdsFromSequenceData(array $data, array &$map): void {
    if (isset($data['entity_type']) && $data['entity_type'] === 'sketches' && !empty($data['entity_id'])) {
        $map[(int)$data['entity_id']] = true;
    }
    if (!empty($data['sketch_id'])) {
        $map[(int)$data['sketch_id']] = true;
    }
    if (isset($data['sketches']) && is_array($data['sketches'])) {
        foreach ($data['sketches'] as $item) {
            if (is_array($item) && !empty($item['id'])) {
                $map[(int)$item['id']] = true;
            } elseif (is_numeric($item)) {
                $map[(int)$item] = true;
            }
        }
    }
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            _extractSketchIdsFromSequenceData($value, $map);
        }
    }
}

try {
    // ── Session Management Endpoints ──
    
    if ($action === 'list_sessions') {
        header('Content-Type: application/json; charset=utf-8');
        $stmt = $pdo->query("SELECT id, name FROM cv_batch_sessions ORDER BY updated_at DESC");
        echo json_encode(['ok' => true, 'sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'save_session') {
        header('Content-Type: application/json; charset=utf-8');
        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? 'Untitled Session');
        $config = json_encode($input['config'] ?? []);
        $state = json_encode($input['state'] ?? new stdClass());

        if ($id) {
            $stmt = $pdo->prepare("UPDATE cv_batch_sessions SET name = ?, config_data = ?, state_data = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $config, $state, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cv_batch_sessions (name, config_data, state_data) VALUES (?, ?, ?)");
            $stmt->execute([$name, $config, $state]);
            $id = $pdo->lastInsertId();
        }
        echo json_encode(['ok' => true, 'id' => (int)$id]);
        exit;
    }

    if ($action === 'load_session') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($input['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT id, name, config_data, state_data FROM cv_batch_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $sess = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sess) {
                echo json_encode([
                    'ok' => true,
                    'session' => [
                        'id' => (int)$sess['id'],
                        'name' => $sess['name'],
                        'config' => json_decode($sess['config_data'], true),
                        'state' => json_decode($sess['state_data'], true)
                    ]
                ]);
                exit;
            }
        }
        echo json_encode(['ok' => false, 'error' => 'Session not found']);
        exit;
    }

    if ($action === 'update_session_state') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($input['id'] ?? 0);
        $state = json_encode($input['state'] ?? new stdClass());
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE cv_batch_sessions SET state_data = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$state, $id]);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    // ── Search Sketches ──
    if ($action === 'search_sketches') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        $limit = 20;
        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, name FROM sketches WHERE id = ? OR name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM sketches WHERE name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // ── Search Map Runs (Sketches only) ──
    if ($action === 'search_map_runs') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        $limit = 20;
        if (empty($q)) {
            $stmt = $pdo->prepare("SELECT id, note, created_at FROM map_runs WHERE entity_type = 'sketches' ORDER BY id DESC LIMIT $limit");
            $stmt->execute();
        } elseif (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, note, created_at FROM map_runs WHERE entity_type = 'sketches' AND (id = ? OR note LIKE ?) ORDER BY id DESC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, note, created_at FROM map_runs WHERE entity_type = 'sketches' AND note LIKE ? ORDER BY id DESC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Search Narrative Sequences ──
    if ($action === 'search_narrative_sequences') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($input['q'] ?? '');
        $limit = 20;
        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE id = ? OR name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE name LIKE ? ORDER BY name ASC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Resolve Narrative Sequence → Sketch IDs ──
    if ($action === 'resolve_sequence_sketches') {
        header('Content-Type: application/json; charset=utf-8');
        $seqId = (int)($input['sequence_id'] ?? 0);
        if ($seqId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'sequence_id required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, sequence_data FROM narrative_sequences WHERE id = ?");
        $stmt->execute([$seqId]);
        $seq = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seq) {
            echo json_encode(['ok' => false, 'error' => 'Sequence not found']);
            exit;
        }

        $sketchIdMap = [];
        if (!empty($seq['sequence_data'])) {
            $seqData = json_decode($seq['sequence_data'], true);
            if (is_array($seqData)) {
                _extractSketchIdsFromSequenceData($seqData, $sketchIdMap);
            }
        }

        $stmtF = $pdo->prepare(
            "SELECT f2s.to_id AS sketch_id
             FROM frames f
             JOIN frames_2_sketches f2s ON f2s.from_id = f.id
             WHERE f.entity_type = 'narrative_sequences' AND f.entity_id = ?"
        );
        $stmtF->execute([$seqId]);
        foreach ($stmtF->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            $sketchIdMap[(int)$sid] = true;
        }

        $sketches = [];
        if (!empty($sketchIdMap)) {
            $ids = array_keys($sketchIdMap);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $stmtS = $pdo->prepare("SELECT id, name FROM sketches WHERE id IN ($in) ORDER BY id ASC");
            $stmtS->execute($ids);
            $sketches = $stmtS->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['ok' => true, 'sketches' => $sketches, 'sequence_id' => $seqId]);
        exit;
    }

    // ── Search PLUSH stories or collections ──
    if ($action === 'search_plush') {
        header('Content-Type: application/json; charset=utf-8');
        $q          = trim($input['q'] ?? '');
        $plushType  = $input['plush_type'] ?? 'story';
        $limit      = 20;
        $table      = $plushType === 'collection' ? 'plush_collections' : 'plush_stories';

        if (is_numeric($q)) {
            $stmt = $pdo->prepare("SELECT id, title AS name FROM `$table` WHERE id = ? OR title LIKE ? ORDER BY title ASC LIMIT $limit");
            $stmt->execute([(int)$q, "%$q%"]);
        } else {
            $stmt = $pdo->prepare("SELECT id, title AS name FROM `$table` WHERE title LIKE ? ORDER BY title ASC LIMIT $limit");
            $stmt->execute(["%$q%"]);
        }
        echo json_encode(['ok' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Resolve PLUSH source → Sketch IDs ──
    if ($action === 'resolve_plush_entities') {
        header('Content-Type: application/json; charset=utf-8');
        $plushId   = (int)($input['plush_id']   ?? 0);
        $plushType = $input['plush_type'] ?? 'story';

        if ($plushId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'plush_id required']);
            exit;
        }

        $storyIds = [];
        if ($plushType === 'collection') {
            $stmtSt = $pdo->prepare("SELECT story_id FROM plush_collections_2_stories WHERE collection_id = ? ORDER BY sort_order ASC");
            $stmtSt->execute([$plushId]);
            $storyIds = $stmtSt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $storyIds = [$plushId];
        }

        if (empty($storyIds)) {
            echo json_encode(['ok' => true, 'sketches' => [], 'entities' => []]);
            exit;
        }

        $inSt   = implode(',', array_fill(0, count($storyIds), '?'));
        $stmtSc = $pdo->prepare("SELECT id FROM plush_scenes WHERE story_id IN ($inSt)");
        $stmtSc->execute($storyIds);
        $sceneIds = $stmtSc->fetchAll(PDO::FETCH_COLUMN);

        if (empty($sceneIds)) {
            echo json_encode(['ok' => true, 'sketches' => [], 'entities' => []]);
            exit;
        }

        $inSc     = implode(',', array_fill(0, count($sceneIds), '?'));
        $stmtBl   = $pdo->prepare("SELECT id FROM plush_highlight_blocks WHERE scene_id IN ($inSc) AND language_code = 'en'");
        $stmtBl->execute($sceneIds);
        $blockIds = $stmtBl->fetchAll(PDO::FETCH_COLUMN);

        if (empty($blockIds)) {
            echo json_encode(['ok' => true, 'sketches' => [], 'entities' => []]);
            exit;
        }

        $inBl   = implode(',', array_fill(0, count($blockIds), '?'));
        $stmtEn = $pdo->prepare(
            "SELECT entity_type, entity_id, entity_label
             FROM plush_highlight_block_entities
             WHERE block_id IN ($inBl)
             GROUP BY entity_type, entity_id, entity_label
             ORDER BY entity_type ASC, entity_label ASC"
        );
        $stmtEn->execute($blockIds);
        $allEntities = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

        $sketchIdMap  = [];
        $otherEntities = [];
        foreach ($allEntities as $ent) {
            if ($ent['entity_type'] === 'sketches') {
                $sketchIdMap[(int)$ent['entity_id']] = true;
            } else {
                $otherEntities[] = $ent;
            }
        }

        $sketches = [];
        if (!empty($sketchIdMap)) {
            $skIds = array_keys($sketchIdMap);
            $inSk  = implode(',', array_fill(0, count($skIds), '?'));
            $stmtSk = $pdo->prepare("SELECT id, name FROM sketches WHERE id IN ($inSk) ORDER BY id ASC");
            $stmtSk->execute($skIds);
            $sketches = $stmtSk->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'ok'       => true,
            'sketches' => $sketches,
            'entities' => $otherEntities,
        ]);
        exit;
    }

    // ── Resolve Batch Configuration to Frame IDs ──
    if ($action === 'resolve_frames') {
        header('Content-Type: application/json; charset=utf-8');
        $config = $input['config'] ?? [];
        
        $frameIds = [];
        
        // 1. Explicit Frame Ranges
        $frameRanges = $config['frame_ranges'] ?? [];
        foreach ($frameRanges as $r) {
            $start = (int)($r['start'] ?? 0);
            $end = (int)($r['end'] ?? 0);
            if ($start > 0 && $end >= $start) {
                $stmt = $pdo->prepare("SELECT id FROM frames WHERE id BETWEEN ? AND ?");
                $stmt->execute([$start, $end]);
                $frameIds = array_merge($frameIds, $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
        }
        
        // 2. Map Runs (resolve to sketches first, then to map run specific frames or latest)
        $mapRunIds = $config['map_run_ids'] ?? [];
        foreach ($mapRunIds as $mId) {
            $mId = (int)$mId;
            if ($mId <= 0) continue;
            
            // Get all sketches mapped to this run
            $stmt = $pdo->prepare("SELECT id FROM sketches WHERE active_map_run_id = ?");
            $stmt->execute([$mId]);
            $mrSketchIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt2 = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = ? AND entity_type = 'sketches'");
            $stmt2->execute([$mId]);
            $mrSketchIds = array_merge($mrSketchIds, $stmt2->fetchAll(PDO::FETCH_COLUMN));
            
            $mrSketchIds = array_unique($mrSketchIds);
            
            // Resolve frames for these sketches
            foreach ($mrSketchIds as $sId) {
                if (!$sId) continue;
                
                // Try to find the exact frame generated in this map run
                $stmtF = $pdo->prepare("SELECT id FROM frames WHERE map_run_id = ? AND entity_type = 'sketches' AND entity_id = ? ORDER BY id DESC LIMIT 1");
                $stmtF->execute([$mId, $sId]);
                $fId = $stmtF->fetchColumn();
                
                if (!$fId) {
                    $stmtF2 = $pdo->prepare("SELECT f.id FROM frames f JOIN frames_2_sketches f2s ON f2s.from_id = f.id WHERE f.map_run_id = ? AND f2s.to_id = ? ORDER BY f.id DESC LIMIT 1");
                    $stmtF2->execute([$mId, $sId]);
                    $fId = $stmtF2->fetchColumn();
                }
                
                // Fallback to latest mapped frame if map run frame is missing
                if (!$fId) {
                    $fId = getLatestFrameForSketch($pdo, $sId);
                }
                
                if ($fId) {
                    $frameIds[] = $fId;
                }
            }
        }
        
        // 3. Sketches (manual, range, sequence, plush are already in sketch_ids)
        $allSketchIds = $config['sketch_ids'] ?? [];
        $sketchRanges = $config['sketch_ranges'] ?? [];
        foreach ($sketchRanges as $r) {
            $start = (int)($r['start'] ?? 0);
            $end = (int)($r['end'] ?? 0);
            if ($start > 0 && $end >= $start) {
                $stmt = $pdo->prepare("SELECT id FROM sketches WHERE id BETWEEN ? AND ?");
                $stmt->execute([$start, $end]);
                $allSketchIds = array_merge($allSketchIds, $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
        }
        
        $allSketchIds = array_unique($allSketchIds);
        foreach ($allSketchIds as $sId) {
            if (!$sId) continue;
            $fId = getLatestFrameForSketch($pdo, $sId);
            if ($fId) {
                $frameIds[] = $fId;
            }
        }
        
        $frameIds = array_values(array_unique(array_map('intval', $frameIds)));
        sort($frameIds);
        
        echo json_encode(['ok' => true, 'frame_ids' => $frameIds]);
        exit;
    }

    // ── Analyze Single Frame Endpoint ──
    if ($action === 'analyze_frame') {
        header('Content-Type: application/json; charset=utf-8');

        $fId = (int)($input['frame_id'] ?? 0);
        $model = trim($input['model'] ?? '');
        $prompt = trim($input['prompt'] ?? '');
        
        $rawResponseLog = null;
        $errorLog = null;

        try {
            if ($fId <= 0) throw new Exception("Invalid frame ID");

            $fd = new \App\Gallery\FrameDetails($mysqli);
            if (!$fd->load($fId)) {
                throw new Exception("Frame not found: " . ($fd->error ?? ''));
            }

            $filename = $fd->frameData['filename'] ?? '';
            if (empty($filename)) {
                throw new Exception("Frame has no filename");
            }
            $absPath = $spw->getProjectPath() . '/public/' . $filename;

            if (!file_exists($absPath)) {
                throw new Exception("Image file not found on server: " . basename($absPath));
            }

            $cv = new \App\Core\PyApiCVService();
            
            if (empty($prompt)) {
                $prompt = "Describe this image in a comma-separated format suitable for Stable Diffusion prompting. Describe every detail. Focus on visual elements, style, lighting, and composition. Do not include introductory text.";
            }

            if ($model !== '') {
                $result = $cv->analyze($absPath, $prompt, $model);
            } else {
                $result = $cv->analyze($absPath, $prompt);
            }

            $rawResponseLog = json_encode($result, JSON_UNESCAPED_UNICODE);

            if (!isset($result['description'])) {
                throw new Exception("API response missing 'description'. Raw output: " . $rawResponseLog);
            }

            // Extract, clean, and save to sketches
            $rawDesc = $result['description'] ?? '';
            if (is_array($rawDesc)) {
                $rawDesc = json_encode($rawDesc, JSON_UNESCAPED_UNICODE);
            }
            
            if (strlen(trim($rawDesc)) > 0) {
                // Fix: Remove codeblock markers without removing inner content
                $cleaned = preg_replace('/```(?:json|text|markdown)?/i', '', $rawDesc);
                $cleaned = str_replace('**', '', $cleaned);
                $cleaned = preg_replace('/^(Here is|Sure|I can|Certainly|The image|This image).*?:/is', '', $cleaned);
                $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

                $result['description'] = $cleaned;

                // Only save to DB if we still have text after cleanup
                if (strlen($cleaned) > 0) {
                    $sketchIds = [];
                    $stmtS = $pdo->prepare("SELECT entity_id FROM frames WHERE id = ? AND entity_type = 'sketches'");
                    $stmtS->execute([$fId]);
                    if ($sid = $stmtS->fetchColumn()) {
                        $sketchIds[] = (int)$sid;
                    }
                    $stmtM = $pdo->prepare("SELECT to_id FROM frames_2_sketches WHERE from_id = ?");
                    $stmtM->execute([$fId]);
                    while ($sid = $stmtM->fetchColumn()) {
                        $sketchIds[] = (int)$sid;
                    }
                    
                    $sketchIds = array_unique($sketchIds);
                    
                    if (!empty($sketchIds)) {
                        $updateStmt = $pdo->prepare("UPDATE sketches SET description_raw = description, description = ? WHERE id = ?");
                        foreach ($sketchIds as $sid) {
                            $updateStmt->execute([$cleaned, $sid]);
                        }
                        $result['saved_to_sketches'] = $sketchIds;
                    }
                }
            } else {
                $result['description'] = ""; // ensure it's at least an empty string
            }

            echo json_encode(['status' => 'success', 'data' => $result]);

        } catch (\Throwable $e) {
            $errorLog = $e->getMessage();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $errorLog]);
        }
        
        // Insert Log record
        try {
            $stmtLog = $pdo->prepare("INSERT INTO cv_batch_session_logs (frame_id, model, prompt, raw_response, error_message) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([
                $fId > 0 ? $fId : null,
                $model ?: null,
                $prompt ?: null,
                $rawResponseLog,
                $errorLog
            ]);
        } catch (\Throwable $logE) {
            // Ignore DB log failures gracefully
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}