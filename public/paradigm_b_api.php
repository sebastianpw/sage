<?php
// public/paradigm_b_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Audio type -> [entity table, junction table]
const SHOT_AUDIO_TYPES = [
    'ambiences' => ['audio_ambiences',  'editorial_shots_2_audio_ambiences'],
    'cues'      => ['audio_cues',       'editorial_shots_2_audio_cues'],
    'foleys'    => ['audio_foleys',     'editorial_shots_2_audio_foleys'],
    'fxsounds'  => ['audio_fxsounds',  'editorial_shots_2_audio_fxsounds'],
    'themes'    => ['audio_themes',    'editorial_shots_2_audio_themes'],
];

try {
    switch ($action) {
        
        // --- DRILL-DOWN NAVIGATION ---
        
        case 'list_episodes':
            $stmt = $pdo->query("SELECT id, name, number FROM editorial_episodes ORDER BY number DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_sequences':
            $epId = (int)($_GET['episode_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name FROM editorial_sequences WHERE episode_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$epId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_scenes':
            $seqId = (int)($_GET['sequence_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name FROM editorial_scenes WHERE sequence_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$seqId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // --- DEEP LINKING RESOLVER ---
        
        case 'get_shot_context':
            $shotId = (int)($_GET['shot_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT sh.id as shot_id, sc.id as scene_id, sq.id as sequence_id, ep.id as episode_id
                FROM editorial_shots sh
                JOIN editorial_scenes sc ON sh.scene_id = sc.id
                JOIN editorial_sequences sq ON sc.sequence_id = sq.id
                JOIN editorial_episodes ep ON sq.episode_id = ep.id
                WHERE sh.id = ?
            ");
            $stmt->execute([$shotId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Shot not found']);
            }
            break;

        case 'get_scene_context':
            $sceneId = (int)($_GET['scene_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT sc.id as scene_id, sq.id as sequence_id, ep.id as episode_id
                FROM editorial_scenes sc
                JOIN editorial_sequences sq ON sc.sequence_id = sq.id
                JOIN editorial_episodes ep ON sq.episode_id = ep.id
                WHERE sc.id = ?
            ");
            $stmt->execute([$sceneId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Scene not found']);
            }
            break;

        // --- SCRIPT & DIALOGUE LOGIC ---

        case 'load_script':
            $sceneId = (int)($_GET['scene_id'] ?? 0);
            if (!$sceneId) throw new Exception('Scene ID required');

            // 1. Get Shots
            $stmt = $pdo->prepare("SELECT s.id, s.name, s.duration_est, s.video_id, v.url as video_url, v.thumbnail 
                                   FROM editorial_shots s 
                                   LEFT JOIN videos v ON s.video_id = v.id 
                                   WHERE s.scene_id = ? ORDER BY s.sort_order ASC, s.id ASC");
            $stmt->execute([$sceneId]);
            $shots = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Get Dialogue Lines mapped to these shots
            $shotIds = array_column($shots, 'id');
            $dialoguesByShot = [];
            
            if (!empty($shotIds)) {
                $inClause = implode(',', $shotIds);
                $sql = "
                    SELECT esd.shot_id, esd.sort_order as mapping_order,
                           adl.id as dialogue_id, adl.character_id, adl.description as text_line, adl.name,
                           adl.active_audio_id,
                           COALESCE(
                               (SELECT a.filename FROM audios a WHERE a.id = adl.active_audio_id LIMIT 1),
                               (SELECT a.filename 
                                FROM audios a 
                                JOIN audios_2_audio_dialogue_lines a2d ON a.id = a2d.from_id 
                                WHERE a2d.to_id = adl.id 
                                ORDER BY a.created_at DESC LIMIT 1)
                           ) as latest_audio_url
                    FROM editorial_shot_dialogues esd
                    JOIN audio_dialogue_lines adl ON esd.dialogue_line_id = adl.id
                    WHERE esd.shot_id IN ($inClause)
                    ORDER BY esd.shot_id ASC, esd.sort_order ASC, esd.id ASC
                ";
                $lines = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                foreach ($lines as $line) {
                    $dialoguesByShot[$line['shot_id']][] = $line;
                }
            }

            // Combine
            foreach ($shots as &$shot) {
                $shot['dialogues'] = $dialoguesByShot[$shot['id']] ?? [];
            }

            echo json_encode(['success' => true, 'data' => $shots]);
            break;

        case 'add_line':
            $shotId = (int)($_POST['shot_id'] ?? 0);
            if (!$shotId) throw new Exception('Shot ID required');
            
            $pdo->beginTransaction();
            
            $name = 'Line ' . time();
            $stmt = $pdo->prepare("INSERT INTO audio_dialogue_lines (name, description) VALUES (?, '')");
            $stmt->execute([$name]);
            $dialogueId = $pdo->lastInsertId();

            // Find the current max sort_order for this shot so new line appends at end
            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM editorial_shot_dialogues WHERE shot_id = ?");
            $maxStmt->execute([$shotId]);
            $maxOrder = (int)$maxStmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO editorial_shot_dialogues (shot_id, dialogue_line_id, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$shotId, $dialogueId, $maxOrder + 1]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'dialogue_id' => $dialogueId]);
            break;

        case 'update_line':
            $dialogueId = (int)($_POST['dialogue_id'] ?? 0);
            $text = $_POST['text'] ?? '';
            if (!$dialogueId) throw new Exception('Dialogue ID required');
            
            $stmt = $pdo->prepare("UPDATE audio_dialogue_lines SET description = ? WHERE id = ?");
            $stmt->execute([$text, $dialogueId]);
            
            echo json_encode(['success' => true]);
            break;

        case 'delete_line':
            $dialogueId = (int)($_POST['dialogue_id'] ?? 0);
            if (!$dialogueId) throw new Exception('Dialogue ID required');
            
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM editorial_shot_dialogues WHERE dialogue_line_id = ?")->execute([$dialogueId]);
            $pdo->prepare("DELETE FROM audio_dialogue_lines WHERE id = ?")->execute([$dialogueId]);
            $pdo->commit();
            
            echo json_encode(['success' => true]);
            break;

        case 'reorder_lines':
            $shotId = (int)($_POST['shot_id'] ?? 0);
            $orderRaw = $_POST['order'] ?? '';
            if (!$shotId || !$orderRaw) throw new Exception('Shot ID and order required');

            $ids = array_filter(array_map('intval', explode(',', $orderRaw)));
            if (empty($ids)) throw new Exception('No valid IDs in order');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE editorial_shot_dialogues SET sort_order = ? WHERE shot_id = ? AND dialogue_line_id = ?");
            foreach ($ids as $i => $dialogueId) {
                $stmt->execute([$i, $shotId, $dialogueId]);
            }
            $pdo->commit();

            echo json_encode(['success' => true]);
            break;

        case 'import_lines':
            $shotId = (int)($_POST['shot_id'] ?? 0);
            $jsonRaw = $_POST['json_data'] ?? '';
            if (!$shotId) throw new Exception('Shot ID required');
            if (!$jsonRaw) throw new Exception('JSON data required');

            $data = json_decode($jsonRaw, true);
            if (!is_array($data)) throw new Exception('Invalid JSON format. Expected a JSON array of objects.');

            $pdo->beginTransaction();

            // Find current max sort order for shot
            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM editorial_shot_dialogues WHERE shot_id = ?");
            $maxStmt->execute([$shotId]);
            $maxOrder = (int)$maxStmt->fetchColumn();

            $insertAdl = $pdo->prepare("INSERT INTO audio_dialogue_lines (name, description, character_id, audio_voice_identity_id, pitch_shift) VALUES (?, ?, ?, ?, ?)");
            $insertEsd = $pdo->prepare("INSERT INTO editorial_shot_dialogues (shot_id, dialogue_line_id, sort_order) VALUES (?, ?, ?)");

            foreach ($data as $row) {
                if (!is_array($row)) continue;
                
                // Allow missing fields cleanly
                $name = $row['name'] ?? ('Import Line ' . time());
                $desc = $row['description'] ?? '';
                $charId = isset($row['character_id']) ? (int)$row['character_id'] : null;
                $voiceId = isset($row['audio_voice_identity_id']) ? (int)$row['audio_voice_identity_id'] : null;
                $pitch = isset($row['pitch_shift']) ? (int)$row['pitch_shift'] : 0;

                $insertAdl->execute([$name, $desc, $charId, $voiceId, $pitch]);
                $dialogueId = $pdo->lastInsertId();

                $maxOrder++;
                $insertEsd->execute([$shotId, $dialogueId, $maxOrder]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'set_active_take':
            $dialogueId = (int)($_POST['dialogue_id'] ?? 0);
            $audioId    = (int)($_POST['audio_id'] ?? 0);
            if (!$dialogueId || !$audioId) throw new Exception('Dialogue ID and Audio ID required');

            $stmt = $pdo->prepare("UPDATE audio_dialogue_lines SET active_audio_id = ? WHERE id = ?");
            $stmt->execute([$audioId, $dialogueId]);

            echo json_encode(['success' => true]);
            break;

        // --- DIALOGUE QUICK UPDATE (dash inline fields) ---

        case 'update_dialogue_quick':
            $dialogueId = (int)($_POST['dialogue_id'] ?? 0);
            if (!$dialogueId) throw new Exception('Dialogue ID required');

            $allowed = ['character_id', 'audio_voice_identity_id', 'regenerate_audios'];
            $sets = []; $params = [];

            foreach ($allowed as $col) {
                if (array_key_exists($col, $_POST)) {
                    $val = $_POST[$col];
                    if ($val === '' || $val === null) {
                        $sets[] = "`$col` = NULL";
                    } else {
                        $sets[] = "`$col` = ?";
                        $params[] = ($col === 'regenerate_audios') ? (int)$val : (int)$val;
                    }
                }
            }

            if (empty($sets)) throw new Exception('No valid fields to update');

            $params[] = $dialogueId;
            $pdo->prepare("UPDATE audio_dialogue_lines SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

            echo json_encode(['success' => true]);
            break;

        // --- SHOT AUDIO REFERENCES ---

        case 'save_shot_audio_notes':
            $shotId = (int)($_POST['shot_id'] ?? 0);
            $notes  = $_POST['notes'] ?? '';
            if (!$shotId) throw new Exception('Shot ID required');

            $pdo->prepare("UPDATE editorial_shots SET audio_notes = ? WHERE id = ?")->execute([$notes, $shotId]);
            echo json_encode(['success' => true]);
            break;

        case 'search_audio_entity':
            $type = $_GET['type'] ?? '';
            $q    = trim($_GET['q'] ?? '');
            $shotId = (int)($_GET['shot_id'] ?? 0);

            if (!array_key_exists($type, SHOT_AUDIO_TYPES)) throw new Exception('Invalid type');
            [$table, $junction] = SHOT_AUDIO_TYPES[$type];

            // Exclude already-linked entities
            $alreadyLinked = [];
            if ($shotId) {
                $s = $pdo->prepare("SELECT to_id FROM `$junction` WHERE from_id = ?");
                $s->execute([$shotId]);
                $alreadyLinked = $s->fetchAll(PDO::FETCH_COLUMN);
            }

            $audioJunction = "audios_2_" . $table;
            $sql = "SELECT id, name,
                           (SELECT a.filename 
                            FROM audios a 
                            JOIN `$audioJunction` a2t ON a.id = a2t.from_id 
                            WHERE a2t.to_id = `$table`.id 
                            ORDER BY a.created_at DESC LIMIT 1) as latest_audio_url
                    FROM `$table` 
                    WHERE name LIKE ? 
                    ORDER BY name ASC LIMIT 20";
            $s = $pdo->prepare($sql);
            $s->execute(['%' . $q . '%']);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);

            // Filter out already linked
            if (!empty($alreadyLinked)) {
                $rows = array_values(array_filter($rows, fn($r) => !in_array($r['id'], $alreadyLinked)));
            }

            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'add_shot_audio_ref':
            $shotId   = (int)($_POST['shot_id'] ?? 0);
            $type     = $_POST['type'] ?? '';
            $entityId = (int)($_POST['entity_id'] ?? 0);

            if (!$shotId || !$entityId) throw new Exception('Shot ID and Entity ID required');
            if (!array_key_exists($type, SHOT_AUDIO_TYPES)) throw new Exception('Invalid type');
            [, $junction] = SHOT_AUDIO_TYPES[$type];

            $pdo->prepare("INSERT IGNORE INTO `$junction` (from_id, to_id) VALUES (?, ?)")->execute([$shotId, $entityId]);
            echo json_encode(['success' => true]);
            break;

        case 'remove_shot_audio_ref':
            $shotId   = (int)($_POST['shot_id'] ?? 0);
            $type     = $_POST['type'] ?? '';
            $entityId = (int)($_POST['entity_id'] ?? 0);

            if (!$shotId || !$entityId) throw new Exception('Shot ID and Entity ID required');
            if (!array_key_exists($type, SHOT_AUDIO_TYPES)) throw new Exception('Invalid type');
            [, $junction] = SHOT_AUDIO_TYPES[$type];

            $pdo->prepare("DELETE FROM `$junction` WHERE from_id = ? AND to_id = ?")->execute([$shotId, $entityId]);
            echo json_encode(['success' => true]);
            break;

        // --- GENERIC ENTITY SEARCH (used by dash character quick-edit) ---

        case 'search_entity':
            $entityType = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['entity_type'] ?? ''));
            $q = trim($_GET['q'] ?? '');
            if (!$entityType) throw new Exception('Entity type required');

            // Whitelist of searchable entity tables
            $searchable = ['characters', 'audio_voice_identity', 'audio_ambiences', 'audio_cues', 'audio_foleys', 'audio_fxsounds', 'audio_themes'];
            if (!in_array($entityType, $searchable)) throw new Exception('Entity type not searchable');

            $stmt = $pdo->prepare("SELECT id, name FROM `$entityType` WHERE name LIKE ? ORDER BY name ASC LIMIT 20");
            $stmt->execute(['%' . $q . '%']);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


