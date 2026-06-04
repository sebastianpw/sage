<?php
// daw/classes/DawApi.php
// API handler for SAGE DAW — all api_action request processing

class DawApi
{
    private PDO $pdo;
    private array $audioEntities;
    private string $selectedEntity;

    public function __construct(PDO $pdo, array $audioEntities, string $selectedEntity)
    {
        $this->pdo            = $pdo;
        $this->audioEntities  = $audioEntities;
        $this->selectedEntity = $selectedEntity;
    }

    /**
     * Dispatch the current request. Outputs JSON and exits.
     */
    public function dispatch(): void
    {
        header('Content-Type: application/json');
        $action    = $_REQUEST['api_action'];
        $reqEntity = $_REQUEST['entity'] ?? $this->selectedEntity;
        if (!in_array($reqEntity, $this->audioEntities, true)) {
            $reqEntity = $this->selectedEntity;
        }

        try {
            switch ($action) {
                case 'get_entities':      $this->getEntities($reqEntity);      break;
                case 'get_playlist':      $this->getPlaylist($reqEntity);      break;
                case 'add_entity':        $this->addEntity($reqEntity);        break;
                case 'delete_entity':     $this->deleteEntity($reqEntity);     break;
                case 'update_field':      $this->updateField($reqEntity);      break;
                case 'toggle_regenerate': $this->toggleRegenerate($reqEntity); break;
                
                case 'create_project':      $this->createProject();            break;
                case 'get_projects':        $this->getProjects();              break;
                case 'save_project_file':   $this->saveProjectFile();          break;
                case 'update_project_file': $this->updateProjectFile();        break;
                case 'get_project_files':   $this->getProjectFiles();          break;
                case 'load_project_file':   $this->loadProjectFile();          break;

                // ── Shot audio lane pre-population ───────────────────────────
                case 'load_shot_audio':   $this->loadShotAudio();             break;

                // ── Shot-anchored DAW saves ───────────────────────────────────
                case 'get_shot_daw_saves':   $this->getShotDawSaves();        break;
                case 'save_shot_daw':        $this->saveShotDaw();            break;
                case 'update_shot_daw_save': $this->updateShotDawSave();      break;
                case 'load_shot_daw_save':   $this->loadShotDawSave();        break;
                case 'delete_shot_daw_save': $this->deleteShotDawSave();      break;

                // ── PyAPI Bounce Registration ────────────────────────────────
                case 'daw_bounce_poll':      $this->pollDawBounce();          break;
                case 'register_bounce':      $this->registerBounce();         break;






                default:
                    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
            }
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function getEntities(string $reqEntity): void
    {
        $limit  = (int)($_GET['limit']  ?? 6);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = trim($_GET['search']  ?? '');
        $table  = '`' . str_replace('`', '', $reqEntity) . '`';
        $where  = '1=1';
        if ($search) {
            $safeSearch = $this->pdo->quote("%{$search}%");
            $safeId     = (int)$search;
            $where     .= " AND (name LIKE {$safeSearch} OR id = {$safeId})";
        }
        $total = $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        $rows  = $this->pdo->query("SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $rows, 'total' => (int)$total]);
    }

    private function getPlaylist(string $reqEntity): void
    {
        $entityId = (int)($_GET['entity_id'] ?? 0);
        $search   = trim($_GET['search']     ?? '');
        if (!$entityId) { echo json_encode(['status' => 'success', 'data' => []]); return; }

        $viewName   = 'v_player_' . $reqEntity;
        $viewExists = false;
        try { $this->pdo->query("SELECT 1 FROM `{$viewName}` LIMIT 1"); $viewExists = true; } catch (Exception $e) {}

        if ($viewExists) {
            $where = 'entity_id = ' . $entityId;
            if ($search) $where .= ' AND (name LIKE ' . $this->pdo->quote("%{$search}%") . ' OR filename LIKE ' . $this->pdo->quote("%{$search}%") . ')';
            $rows = $this->pdo->query("SELECT * FROM `{$viewName}` WHERE {$where} ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $where = "entity_type = " . $this->pdo->quote($reqEntity) . " AND entity_id = {$entityId}";
            if ($search) $where .= ' AND filename LIKE ' . $this->pdo->quote("%{$search}%");
            $rows = $this->pdo->query("SELECT id AS audio_id, name, filename, created_at FROM audios WHERE {$where} ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    private function addEntity(string $reqEntity): void
    {
        $table = '`' . $reqEntity . '`';
        $cols  = $this->pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
        $name  = 'New ' . ucfirst(str_replace(['audio_', '_'], ['', ' '], $reqEntity)) . ' ' . time();
        $iCols = ['name']; $iVals = ['?']; $params = [$name];
        if (in_array('order', $cols)) { $iCols[] = '`order`'; $iVals[] = '0'; }
        $this->pdo->prepare("INSERT INTO {$table} (" . implode(',', $iCols) . ") VALUES (" . implode(',', $iVals) . ")")->execute($params);
        echo json_encode(['status' => 'success', 'id' => $this->pdo->lastInsertId()]);
    }

    private function deleteEntity(string $reqEntity): void
    {
        $id = (int)($_POST['entity_id'] ?? 0);
        if ($id > 0) $this->pdo->prepare("DELETE FROM `{$reqEntity}` WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success']);
    }

    private function updateField(string $reqEntity): void
    {
        $id    = (int)($_POST['entity_id'] ?? 0);
        $field = $_POST['field']            ?? '';
        $value = $_POST['value']            ?? '';
        $table = '`' . str_replace('`', '', $reqEntity) . '`';
        $cols  = $this->pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
        if ($id > 0 && in_array($field, $cols)) {
            $this->pdo->prepare("UPDATE {$table} SET `{$field}` = ? WHERE id = ?")->execute([$value, $id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid field']);
        }
    }

    private function toggleRegenerate(string $reqEntity): void
    {
        $id  = (int)($_POST['entity_id'] ?? 0);
        $val = (int)($_POST['value']     ?? 0);
        $col = $_POST['column']           ?? '';
        $table = '`' . str_replace('`', '', $reqEntity) . '`';
        $cols  = $this->pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
        if ($id > 0 && in_array($col, $cols)) {
            $this->pdo->prepare("UPDATE {$table} SET `{$col}` = ? WHERE id = ?")->execute([$val, $id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }

    // ─── Project Save / Load Methods ─────────────────────────────────────────

    private function getProjects(): void
    {
        $rows = $this->pdo->query("SELECT id, name, folder_name FROM daw_projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    private function createProject(): void
    {
        $name = $_POST['name'] ?? 'New Project';
        $folder = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)) . '_' . time();
        $this->pdo->prepare("INSERT INTO daw_projects (name, folder_name) VALUES (?, ?)")->execute([$name, $folder]);
        echo json_encode(['status' => 'success']);
    }

    private function getProjectFiles(): void
    {
        $pid = (int)($_GET['project_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT id, filename, created_at FROM daw_project_files WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->execute([$pid]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function saveProjectFile(): void
    {
        $pid   = (int)($_POST['project_id'] ?? 0);
        $fname = $_POST['filename'] ?? 'untitled';
        $state = $_POST['state_data'] ?? '{}';
        
        $this->pdo->prepare("INSERT INTO daw_project_files (project_id, filename, state_data) VALUES (?, ?, ?)")
            ->execute([$pid, $fname, $state]);
        
        echo json_encode(['status' => 'success']);
    }

    private function updateProjectFile(): void
    {
        $fid   = (int)($_POST['file_id'] ?? 0);
        $state = $_POST['state_data'] ?? '{}';
        
        if ($fid > 0) {
            $this->pdo->prepare("UPDATE daw_project_files SET state_data = ? WHERE id = ?")->execute([$state, $fid]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file ID']);
        }
    }

    private function loadProjectFile(): void
    {
        $fid = (int)($_GET['file_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT state_data FROM daw_project_files WHERE id = ?");
        $stmt->execute([$fid]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
        }
    }

    // ─── Load Shot Audio ─────────────────────────────────────────────────────

    private function loadShotAudio(): void
    {
        $shotId = (int)($_GET['shot_id'] ?? 0);
        if (!$shotId) {
            echo json_encode(['status' => 'error', 'message' => 'shot_id required']);
            return;
        }

        $lanes = [];

        // ── 1. Non-dialogue audio entity types ───────────────────────────────
        $nonDialogueTypes = [
            'audio_ambiences' => 'editorial_shots_2_audio_ambiences',
            'audio_cues'      => 'editorial_shots_2_audio_cues',
            'audio_foleys'    => 'editorial_shots_2_audio_foleys',
            'audio_fxsounds'  => 'editorial_shots_2_audio_fxsounds',
            'audio_themes'    => 'editorial_shots_2_audio_themes',
        ];

        foreach ($nonDialogueTypes as $entityTable => $junctionTable) {
            // Get linked entity IDs for this shot
            $stmt = $this->pdo->prepare(
                "SELECT j.to_id AS entity_id, e.name AS entity_name
                 FROM `{$junctionTable}` j
                 JOIN `{$entityTable}` e ON e.id = j.to_id
                 WHERE j.from_id = ?"
            );
            $stmt->execute([$shotId]);
            $linkedEntities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $audioJunction = 'audios_2_' . $entityTable;

            foreach ($linkedEntities as $entity) {
                // Latest audio for this entity
                $aStmt = $this->pdo->prepare(
                    "SELECT a.filename
                     FROM audios a
                     JOIN `{$audioJunction}` aj ON a.id = aj.from_id
                     WHERE aj.to_id = ?
                     ORDER BY a.created_at DESC, a.id DESC
                     LIMIT 1"
                );
                $aStmt->execute([$entity['entity_id']]);
                $filename = $aStmt->fetchColumn();

                if ($filename) {
                    $lanes[] = [
                        'lane_label'   => $entityTable . ' — ' . $entity['entity_name'],
                        'entity_type'  => $entityTable,
                        'entity_id'    => (int)$entity['entity_id'],
                        'entity_name'  => $entity['entity_name'],
                        'audio_filename' => $filename,
                    ];
                }
            }
        }

        // ── 2. Dialogue lines via editorial_shot_dialogues ───────────────────
        $dlStmt = $this->pdo->prepare(
            "SELECT adl.id AS dialogue_id,
                    adl.name AS dialogue_name,
                    adl.active_audio_id,
                    COALESCE(
                        (SELECT a.filename FROM audios a WHERE a.id = adl.active_audio_id LIMIT 1),
                        (SELECT a.filename
                         FROM audios a
                         JOIN audios_2_audio_dialogue_lines a2d ON a.id = a2d.from_id
                         WHERE a2d.to_id = adl.id
                         ORDER BY a.created_at DESC, a.id DESC
                         LIMIT 1)
                    ) AS audio_filename
             FROM editorial_shot_dialogues esd
             JOIN audio_dialogue_lines adl ON adl.id = esd.dialogue_line_id
             WHERE esd.shot_id = ?
             ORDER BY esd.sort_order ASC, esd.id ASC"
        );
        $dlStmt->execute([$shotId]);
        $dialogueLines = $dlStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dialogueLines as $dl) {
            if (!$dl['audio_filename']) continue;
            $lanes[] = [
                'lane_label'    => 'Dialogue — ' . ($dl['dialogue_name'] ?: 'Line #' . $dl['dialogue_id']),
                'entity_type'   => 'audio_dialogue_lines',
                'entity_id'     => (int)$dl['dialogue_id'],
                'entity_name'   => $dl['dialogue_name'] ?: 'Line #' . $dl['dialogue_id'],
                'audio_filename' => $dl['audio_filename'],
            ];
        }

        // ── 3. Shot video URL for the player ────────────────────────────────
        $vStmt = $this->pdo->prepare(
            "SELECT v.url AS video_url
             FROM editorial_shots s
             LEFT JOIN videos v ON v.id = s.video_id
             WHERE s.id = ?
             LIMIT 1"
        );
        $vStmt->execute([$shotId]);
        $videoUrl = $vStmt->fetchColumn() ?: null;

        echo json_encode([
            'status'    => 'success',
            'lanes'     => $lanes,
            'video_url' => $videoUrl,
        ]);
    }

    // ─── Shot-Anchored DAW Save / Load ────────────────────────────────────────

    private function getShotDawSaves(): void
    {
        $shotId = (int)($_GET['shot_id'] ?? 0);
        if (!$shotId) { echo json_encode(['status' => 'error', 'message' => 'shot_id required']); return; }

        $stmt = $this->pdo->prepare(
            "SELECT id, name, created_at, updated_at
             FROM daw_shot_saves
             WHERE shot_id = ?
             ORDER BY updated_at DESC"
        );
        $stmt->execute([$shotId]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function saveShotDaw(): void
    {
        $shotId = (int)($_POST['shot_id']  ?? 0);
        $name   = trim($_POST['name']      ?? '');
        $state  = $_POST['state_data']     ?? '{}';

        if (!$shotId) { echo json_encode(['status' => 'error', 'message' => 'shot_id required']); return; }
        if ($name === '') { $name = 'Save ' . date('Y-m-d H:i'); }

        $stmt = $this->pdo->prepare(
            "INSERT INTO daw_shot_saves (shot_id, name, state_data) VALUES (?, ?, ?)"
        );
        $stmt->execute([$shotId, $name, $state]);
        echo json_encode(['status' => 'success', 'id' => (int)$this->pdo->lastInsertId()]);
    }

    private function updateShotDawSave(): void
    {
        $id    = (int)($_POST['save_id']  ?? 0);
        $state = $_POST['state_data']     ?? '{}';

        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'save_id required']); return; }

        $this->pdo->prepare(
            "UPDATE daw_shot_saves SET state_data = ? WHERE id = ?"
        )->execute([$state, $id]);
        echo json_encode(['status' => 'success']);
    }

    private function loadShotDawSave(): void
    {
        $id = (int)($_GET['save_id'] ?? 0);
        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'save_id required']); return; }

        $stmt = $this->pdo->prepare("SELECT state_data FROM daw_shot_saves WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Save not found']);
        }
    }

    private function deleteShotDawSave(): void
    {
        $id = (int)($_POST['save_id'] ?? 0);
        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'save_id required']); return; }

        $this->pdo->prepare("DELETE FROM daw_shot_saves WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success']);
    }

    // ─── PyAPI Mixdown DB Storage & Mapping ──────────────────────────────────
    
    
    
    
    
    
   
    private function pollDawBounce(): void
    {
        $pyapiUrl = $_POST['pyapi_url'] ?? '';
        $taskId   = $_POST['task_id'] ?? '';

        if (!$pyapiUrl || !$taskId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing pyapi_url or task_id']);
            return;
        }

        // Route polling through PHP exactly like the submission
        $url = rtrim($pyapiUrl, '/') . '/daw/status/' . $taskId;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 400 || !$response) {
            echo json_encode(['status' => 'error', 'message' => "PyAPI returned HTTP $httpCode: $err"]);
            return;
        }

        $data = json_decode($response, true);
        if (!$data) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON from PyAPI']);
            return;
        }

        // Return PyAPI's exact response structure to JS
        echo json_encode([
            'status' => 'success',
            'task_status' => $data['status'] ?? 'pending',
            'error' => $data['error'] ?? ''
        ]);
    }


    
    
    
    
    

    private function registerBounce(): void
    {
        $taskId     = $_POST['task_id']     ?? '';
        $entityType = $_POST['entity_type'] ?? '';
        $entityId   = (int)($_POST['entity_id'] ?? 0);
        $bounceName = $_POST['name']        ?? 'DAW Mixdown';
        $pyapiUrl   = $_POST['pyapi_url']   ?? '';

        if (!$taskId || !preg_match('/^[a-f0-9\-]+$/i', $taskId)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid task ID']);
            return;
        }

        $sourceFile = realpath(__DIR__ . '/../../../services_data/daw_renders/bounce_' . $taskId . '.wav');
        if (!$sourceFile || !file_exists($sourceFile)) {
            echo json_encode(['status' => 'error', 'message' => 'Bounce file not found on server']);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Create a Map Run for this Bounce action
            $mapType = $entityType ?: 'daw_bounce';
            $stmt = $this->pdo->prepare("INSERT INTO map_runs (entity_type, note) VALUES (?, ?)");
            $stmt->execute([$mapType, 'Generated by SAGE DAW Mixdown']);
            $mapRunId = (int)$this->pdo->lastInsertId();

            // 2. Claim next available audio ID from audio_counter
            $this->pdo->query("UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1)");
            $counterId = (int)$this->pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            
            // Format strictly as audio0000123.wav matching SAGE convention
            $audioBasename = 'audio' . str_pad($counterId, 7, '0', STR_PAD_LEFT);
            $filenameOnly  = $audioBasename . '.wav';

            // 3. Move the file out of Python's temporary output folder into public/audios
            $targetDir = __DIR__ . '/../../../public/audios';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $targetFile = $targetDir . '/' . $filenameOnly;
            
            if (!rename($sourceFile, $targetFile)) {
                throw new Exception('Failed to move the mixdown into public/audios directory');
            }

            // 4. Register Audios row
            $stmt = $this->pdo->prepare("
                INSERT INTO audios (filename, name, entity_type, entity_id, map_run_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                '/audios/' . $filenameOnly,
                $bounceName,
                $entityType ?: null,
                $entityId ?: null,
                $mapRunId
            ]);
            $audioId = (int)$this->pdo->lastInsertId();

            // 5. Connect the Mapping Table (if provided & exists)
            if ($entityType && $entityId) {
                $mapTable = 'audios_2_' . $entityType;
                $tableExists = $this->pdo->query("SHOW TABLES LIKE '{$mapTable}'")->rowCount() > 0;
                
                if ($tableExists) {
                    $this->pdo->prepare("INSERT IGNORE INTO `{$mapTable}` (from_id, to_id) VALUES (?, ?)")
                              ->execute([$audioId, $entityId]);
                }
            }

            $this->pdo->commit();

            // Cleanup PyAPI Temp folder now that we safely moved the WAV file via PHP
            if ($pyapiUrl) {
                $cleanupUrl = rtrim($pyapiUrl, '/') . "/daw/cleanup/{$taskId}";
                $ch = curl_init($cleanupUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }

            echo json_encode([
                'status'   => 'success',
                'audio_id' => $audioId,
                'filename' => '/audios/' . $filenameOnly
            ]);

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }




}