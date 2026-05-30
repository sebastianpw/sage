<?php
// public/ved/classes/VedApi.php
// API handler for SAGE VED — Video Edit DAW
// All api_action dispatching lives here; index.php exits early via this class.

class VedApi
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
   private function getPyApiUrl(): string
{
    $script = __DIR__ . '/../../../bash/pyapi_echo.sh';
    $url = trim((string)shell_exec('sh ' . escapeshellarg($script)));
    return $url !== '' ? rtrim($url, '/') : 'http://127.0.0.1:8009';
}



   private function getPyApiUrlAction(): void
{
    echo json_encode([
        'status' => 'success',
        'url' => $this->getPyApiUrl(),
    ]);
}
    

    /**
     * Dispatch the current request. Outputs JSON and exits.
     */
    public function dispatch(): void
    {
        header('Content-Type: application/json');
        $action = $_REQUEST['api_action'] ?? '';

        try {
            switch ($action) {
                // ── Animatic browser ──────────────────────────────────────────
                case 'list_animatics':        $this->listAnimatics();       break;
                case 'get_animatic':          $this->getAnimatic();         break;

                // ── Video asset bin & multi-tab logic ─────────────────────────
                case 'list_videos':              $this->listVideos();              break;
                case 'list_narrative_sequences': $this->listNarrativeSequences();  break;
                case 'list_storyboards':         $this->listStoryboards();         break;
                case 'list_fuzz_candidates':     $this->listFuzzCandidates();      break;

                // ── Project save / load ───────────────────────────────────────
                case 'get_projects':          $this->getProjects();         break;
                case 'create_project':        $this->createProject();       break;
                case 'get_project_files':     $this->getProjectFiles();     break;
                case 'save_project_file':     $this->saveProjectFile();     break;
                case 'update_project_file':   $this->updateProjectFile();   break;
                case 'load_project_file':     $this->loadProjectFile();     break;
                
               case 'get_pyapi_url':            $this->getPyApiUrlAction();        break;

                // ── Bounce registration (after PyAPI render) ──────────────────
                case 'register_bounce':       $this->registerBounce();      break;

                default:
                    echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
            }
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Filter Tabs / Picker Sources ─────────────────────────────────────────

    private function listNarrativeSequences(): void
    {
        $stmt = $this->pdo->query("SELECT id, name, description FROM narrative_sequences ORDER BY id DESC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function listStoryboards(): void
    {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $whereSQL = "WHERE is_archived = 0";
        $params   = [];

        $cols = [];
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM storyboards")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}

        $hasName  = in_array('name', $cols);
        $hasTitle = in_array('title', $cols);
        $hasDesc  = in_array('description', $cols);

        if ($search !== '') {
            $conds = [];
            if ($hasName)  { $conds[] = "name LIKE ?";        $params[] = '%' . $search . '%'; }
            if ($hasTitle) { $conds[] = "title LIKE ?";       $params[] = '%' . $search . '%'; }
            if ($hasDesc)  { $conds[] = "description LIKE ?"; $params[] = '%' . $search . '%'; }
            if (!empty($conds)) {
                $whereSQL .= " AND (" . implode(" OR ", $conds) . ")";
            } else {
                $whereSQL .= " AND id = ?";
                $params[] = (int)$search;
            }
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM storyboards $whereSQL");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT * FROM storyboards $whereSQL ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'     => 'success',
            'data'       => $rows,
            'pagination' => [
                'page'  => $page,
                'pages' => max(1, (int)ceil($total / $limit)),
                'total' => $total,
            ],
        ]);
    }

    private function listFuzzCandidates(): void
    {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;

        $whereSQL = "WHERE status IN ('promoted','canonized')";
        $params   = [];

        if ($search !== '') {
            $whereSQL .= " AND label LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM fuzz_candidates $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.label, c.concept_type, c.status
             FROM fuzz_candidates c
             $whereSQL
             ORDER BY c.updated_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'     => 'success',
            'data'       => $rows,
            'pagination' => [
                'page'  => $page,
                'pages' => max(1, (int)ceil($total / $limit)),
                'total' => $total,
            ],
        ]);
    }

    private function listAnimatics(): void
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        $q       = trim($_GET['q'] ?? '');
        $offset  = ($page - 1) * $perPage;

        if ($q !== '') {
            $like = '%' . $q . '%';
            $idQ  = is_numeric($q) ? (int)$q : -1;
            $total = (int)$this->pdo->prepare("SELECT COUNT(*) FROM animatics WHERE id = ? OR name LIKE ?")
                        ->execute([$idQ, $like]) ? $this->pdo->query("SELECT COUNT(*) FROM animatics WHERE id = $idQ OR name LIKE " . $this->pdo->quote($like))->fetchColumn() : 0;
            $stmt = $this->pdo->prepare(
                "SELECT id, name, description FROM animatics WHERE id = ? OR name LIKE ? ORDER BY id DESC LIMIT $perPage OFFSET $offset"
            );
            $stmt->execute([$idQ, $like]);
        } else {
            $total = (int)$this->pdo->query("SELECT COUNT(*) FROM animatics")->fetchColumn();
            $stmt  = $this->pdo->prepare("SELECT id, name, description FROM animatics ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
        }

        echo json_encode([
            'status'      => 'success',
            'data'        => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'       => (int)$total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'page'        => $page,
        ]);
    }

    private function getAnimatic(): void
    {
        $id = (int)($_GET['animatic_id'] ?? 0);
        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'animatic_id required']); return; }
        $stmt = $this->pdo->prepare("SELECT id, name, description FROM animatics WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['status' => 'error', 'message' => 'Animatic not found']); return; }
        echo json_encode(['status' => 'success', 'data' => $row]);
    }

    // ─── Video asset bin ──────────────────────────────────────────────────────

    private function listVideos(): void
    {
        $animaticId   = (int)($_GET['animatic_id'] ?? 0);
        $nodeId       = (int)($_GET['node_id'] ?? 0);
        $seqId        = (int)($_GET['seq_id'] ?? 0);
        $fuzzCandId   = (int)($_GET['fuzz_cand_id'] ?? 0);
        $storyboardId = (int)($_GET['storyboard_id'] ?? 0);
        $inclDesc     = (int)($_GET['include_descendants'] ?? 1);
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $perPage      = 20;
        $q            = trim($_GET['q'] ?? '');
        $offset       = ($page - 1) * $perPage;

        $joins  = "";
        $where  = [];
        $params = [];

        if ($storyboardId) {
            $sfStmt = $this->pdo->prepare(
                "SELECT sf.frame_id, f.entity_type, f.entity_id
                 FROM storyboard_frames sf
                 JOIN frames f ON f.id = sf.frame_id
                 WHERE sf.storyboard_id = ?
                   AND f.entity_type IS NOT NULL AND f.entity_id IS NOT NULL"
            );
            $sfStmt->execute([$storyboardId]);
            $sbFrames = $sfStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($sbFrames)) {
                $where[] = "1 = 0";
            } else {
                $entityGroups = [];
                foreach ($sbFrames as $row) {
                    $key = $row['entity_type'] . '|' . $row['entity_id'];
                    $entityGroups[$key] = ['entity_type' => $row['entity_type'], 'entity_id' => (int)$row['entity_id']];
                }
                $allFrameIds = [];
                $allowedTables = ['sketches','characters','locations','spawns','generatives','animas',
                                  'artifacts','lotations','character_poses','character_anima_poses',
                                  'character_expressions','animatics','composites'];
                foreach ($entityGroups as $eg) {
                    $eType = $eg['entity_type'];
                    $eId   = $eg['entity_id'];
                    if (!in_array($eType, $allowedTables)) continue;
                    $directStmt = $this->pdo->prepare("SELECT id FROM frames WHERE entity_type = ? AND entity_id = ?");
                    $directStmt->execute([$eType, $eId]);
                    foreach ($directStmt->fetchAll(PDO::FETCH_COLUMN) as $fid) { $allFrameIds[] = (int)$fid; }
                    $mapTable = "frames_2_{$eType}";
                    $checkMap = $this->pdo->query("SHOW TABLES LIKE " . $this->pdo->quote($mapTable));
                    if ($checkMap && $checkMap->rowCount() > 0) {
                        $mapStmt = $this->pdo->prepare("SELECT from_id FROM `$mapTable` WHERE to_id = ?");
                        $mapStmt->execute([$eId]);
                        foreach ($mapStmt->fetchAll(PDO::FETCH_COLUMN) as $fid) { $allFrameIds[] = (int)$fid; }
                    }
                }
                $allFrameIds = array_unique($allFrameIds);
                if (empty($allFrameIds)) {
                    $where[] = "1 = 0";
                } else {
                    $inClause = implode(',', $allFrameIds);
                    $where[] = "v.id IN (
                        SELECT va2.from_id FROM videos_2_animatics va2
                        JOIN animatics an ON va2.to_id = an.id
                        WHERE an.img2img_frame_id IN ($inClause)
                    )";
                }
            }

        } elseif ($fuzzCandId) {
            $where[] = "v.id IN (
                SELECT DISTINCT va2.from_id FROM videos_2_animatics va2
                JOIN animatics an ON va2.to_id = an.id
                JOIN frames fr ON an.img2img_frame_id = fr.id
                WHERE (
                    (fr.entity_type = 'sketches' AND fr.entity_id IN (
                        SELECT DISTINCT source_row_id FROM fuzz_mentions
                        WHERE candidate_id = ?
                          AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients')
                          AND source_row_id IS NOT NULL
                    ))
                    OR fr.id IN (
                        SELECT f2s.from_id FROM frames_2_sketches f2s
                        WHERE f2s.to_id IN (
                            SELECT DISTINCT source_row_id FROM fuzz_mentions
                            WHERE candidate_id = ?
                              AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients')
                              AND source_row_id IS NOT NULL
                        )
                    )
                )
            )";
            $params[] = $fuzzCandId;
            $params[] = $fuzzCandId;

        } elseif ($seqId) {
            $where[] = "v.id IN (
                SELECT DISTINCT va2.from_id FROM videos_2_animatics va2
                JOIN animatics an ON va2.to_id = an.id
                JOIN frames fr ON an.img2img_frame_id = fr.id
                WHERE (
                    (fr.entity_type = 'sketches' AND fr.entity_id IN (
                        SELECT CASE WHEN JSON_TYPE(jt.val) = 'INTEGER'
                                    THEN JSON_VALUE(jt.val, '$')
                                    ELSE JSON_VALUE(jt.val, '$.sketch_id')
                               END
                        FROM narrative_sequences ns,
                        JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
                        WHERE ns.id = ?
                    ))
                    OR fr.id IN (
                        SELECT f2s.from_id FROM frames_2_sketches f2s
                        WHERE f2s.to_id IN (
                            SELECT CASE WHEN JSON_TYPE(jt2.val) = 'INTEGER'
                                        THEN JSON_VALUE(jt2.val, '$')
                                        ELSE JSON_VALUE(jt2.val, '$.sketch_id')
                                   END
                            FROM narrative_sequences ns2,
                            JSON_TABLE(ns2.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt2
                            WHERE ns2.id = ?
                        )
                    )
                )
            )";
            $params[] = $seqId;
            $params[] = $seqId;

        } elseif ($nodeId) {
            if ($inclDesc) {
                $where[] = "v.id IN (
                    SELECT vti.video_id FROM video_tree_items vti
                    WHERE vti.node_id IN (
                        WITH RECURSIVE desc_nodes AS (
                            SELECT id FROM video_tree_nodes WHERE id = ?
                            UNION ALL
                            SELECT n.id FROM video_tree_nodes n
                            INNER JOIN desc_nodes d ON n.parent_id = d.id
                        )
                        SELECT id FROM desc_nodes
                    )
                )";
                $params[] = $nodeId;
            } else {
                $where[] = "v.id IN (SELECT vti.video_id FROM video_tree_items vti WHERE vti.node_id = ?)";
                $params[] = $nodeId;
            }
        } elseif ($animaticId > 0) {
            $joins = "JOIN animatic_videos av ON av.video_id = v.id";
            $where[] = "av.animatic_id = ?";
            $params[] = $animaticId;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $idQ  = is_numeric($q) ? (int)$q : -1;
            $where[] = "(v.id = ? OR v.name LIKE ? OR v.url LIKE ?)";
            $params[] = $idQ;
            $params[] = $like;
            $params[] = $like;
        }

        $whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $countSql = "SELECT COUNT(DISTINCT v.id) FROM videos v $joins $whereStr";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $orderBy = ($animaticId > 0 && !$storyboardId && !$fuzzCandId && !$seqId && !$nodeId) 
            ? "ORDER BY av.created_at DESC" 
            : "ORDER BY v.id DESC";

        $dataSql = "SELECT DISTINCT v.id, v.name, v.url, v.thumbnail, v.duration, v.width, v.height, v.type
                    FROM videos v $joins $whereStr
                    $orderBy
                    LIMIT $perPage OFFSET $offset";
        $dataStmt = $this->pdo->prepare($dataSql);
        $dataStmt->execute($params);

        echo json_encode([
            'status'      => 'success',
            'data'        => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'total'       => (int)$total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'page'        => $page,
        ]);
    }

    // ─── Project Save / Load ──────────────────────────────────────────────────

    private function getProjects(): void
    {
        $this->ensureTables();
        $rows = $this->pdo->query("SELECT id, name, folder_name FROM ved_projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    private function createProject(): void
    {
        $this->ensureTables();
        $name   = trim($_POST['name'] ?? 'New Project');
        if ($name === '') $name = 'New Project';
        $folder = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)) . '_' . time();
        $this->pdo->prepare("INSERT INTO ved_projects (name, folder_name) VALUES (?, ?)")->execute([$name, $folder]);
        echo json_encode(['status' => 'success', 'id' => (int)$this->pdo->lastInsertId()]);
    }

    private function getProjectFiles(): void
    {
        $this->ensureTables();
        $pid  = (int)($_GET['project_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT id, filename, created_at FROM ved_project_files WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->execute([$pid]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function saveProjectFile(): void
    {
        $this->ensureTables();
        $pid   = (int)($_POST['project_id'] ?? 0);
        $fname = trim($_POST['filename'] ?? 'untitled');
        $state = $_POST['state_data'] ?? '{}';
        if (!$pid) { echo json_encode(['status' => 'error', 'message' => 'project_id required']); return; }
        $this->pdo->prepare("INSERT INTO ved_project_files (project_id, filename, state_data) VALUES (?, ?, ?)")
            ->execute([$pid, $fname, $state]);
        echo json_encode(['status' => 'success', 'id' => (int)$this->pdo->lastInsertId()]);
    }

    private function updateProjectFile(): void
    {
        $this->ensureTables();
        $fid   = (int)($_POST['file_id'] ?? 0);
        $state = $_POST['state_data'] ?? '{}';
        if (!$fid) { echo json_encode(['status' => 'error', 'message' => 'file_id required']); return; }
        $this->pdo->prepare("UPDATE ved_project_files SET state_data = ? WHERE id = ?")->execute([$state, $fid]);
        echo json_encode(['status' => 'success']);
    }

    private function loadProjectFile(): void
    {
        $this->ensureTables();
        $fid  = (int)($_GET['file_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT state_data FROM ved_project_files WHERE id = ?");
        $stmt->execute([$fid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) echo json_encode(['status' => 'success', 'data' => $row]);
        else       echo json_encode(['status' => 'error', 'message' => 'File not found']);
    }

    // ─── Bounce registration ──────────────────────────────────────────────────

    private function registerBounce(): void
    {
        $taskId     = $_POST['task_id']     ?? '';
        $animaticId = (int)($_POST['animatic_id'] ?? 0);
        $bounceName = trim($_POST['name']   ?? 'VED Export');
        $projectId  = (int)($_POST['project_id'] ?? 0);
        $canvasW    = (int)($_POST['canvas_w'] ?? 0);
        $canvasH    = (int)($_POST['canvas_h'] ?? 0);
        $durationS  = (int)($_POST['duration_s'] ?? 0);

        if (!$taskId || !preg_match('/^[a-f0-9\-]+$/i', $taskId)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid task ID']);
            return;
        }

        // Fetch the file from PyAPI
        $pyApiUrl = $this->getPyApiUrl() . '/ved/download/' . $taskId;
        
        $tempFile = tempnam(sys_get_temp_dir(), 'ved_');
        $fp = fopen($tempFile, 'w+');
        $ch = curl_init($pyApiUrl);
        
        $ext = 'mp4';
        $mimeType = 'video/mp4';

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Use headers to grab the correct extension/mimetype (mp4 vs webm)
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$ext, &$mimeType) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                $value = strtolower(trim($parts[1]));
                if ($name === 'content-type') {
                    if (strpos($value, 'webm') !== false) {
                        $ext = 'webm';
                        $mimeType = 'video/webm';
                    }
                }
            }
            return $len;
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200) {
            @unlink($tempFile);
            echo json_encode(['status' => 'error', 'message' => 'Failed to download render from PyAPI (HTTP ' . $httpCode . ')']);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Create map run
            $this->pdo->prepare("INSERT INTO map_runs (entity_type, note) VALUES (?, ?)")
                ->execute(['animatics', 'Generated by SAGE VED Export']);
            $mapRunId = (int)$this->pdo->lastInsertId();

            // 2. Claim next video counter
            $this->pdo->query("UPDATE video_counter SET next_video = LAST_INSERT_ID(next_video + 1)");
            $counterId = (int)$this->pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            $basename  = 'video' . str_pad($counterId, 7, '0', STR_PAD_LEFT);
            $filename  = $basename . '.' . $ext;

            // 3. Move video file into public/videos
            $targetDir = __DIR__ . '/../../../public/videos';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $targetFile = $targetDir . '/' . $filename;

            if (!rename($tempFile, $targetFile)) {
                throw new Exception('Failed to move render into public/videos');
            }
            chmod($targetFile, 0644);
            
            
            
            
            
            
            
            
            
            
            
           // 4. Parse state data (needed for thumbnail & description)
$stateData   = json_decode($_POST['state_json'] ?? '{}', true);
$clipsCount  = is_array($stateData['clips'] ?? null) ? count($stateData['clips']) : 0;

// 5. Generate Blue Info Thumbnail
$thumbDir       = $targetDir . '/thumbnails';
if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0777, true);
}
$thumbFilename  = $basename . '.jpg';          // e.g., video0015476.jpg
$thumbTarget    = $thumbDir . '/' . $thumbFilename;
$thumbUrl       = null;

$tw = 640;
$th = ($canvasW > 0 && $canvasH > 0) ? (int)(($canvasH / $canvasW) * $tw) : 360;
if ($th <= 0) $th = 360;

if (function_exists('imagecreatetruecolor')) {
    $im = @imagecreatetruecolor($tw, $th);
    if ($im) {
        
        /*
        $bg        = imagecolorallocate($im, 15, 15, 24);
        $border    = imagecolorallocate($im, 28, 28, 46);
        */
        
        
        $bg     = imagecolorallocate($im, 0, 0, 170);     // BSOD blue (#0000AA)
        $border = imagecolorallocate($im, 0, 0, 100);     // darker blue frame (#000064)
        
        $amber     = imagecolorallocate($im, 245, 158, 11);
        $textWhite = imagecolorallocate($im, 228, 228, 240);
        $textDim   = imagecolorallocate($im, 107, 107, 138);

        imagefill($im, 0, 0, $bg);
        imagerectangle($im, 0, 0, $tw - 1, $th - 1, $border);
        imagerectangle($im, 1, 1, $tw - 2, $th - 2, $border);

        $titleStr = "VED EXPORT";
        $nameStr  = mb_strlen($bounceName) > 60 ? mb_substr($bounceName, 0, 57) . '...' : $bounceName;
        $infoStr  = "Duration: " . $durationS . "s | Res: " . $canvasW . "x" . $canvasH;
        $trackStr = "Clips: " . $clipsCount;

        $startY = ($th / 2) - 30;
        imagestring($im, 5, 30, $startY, $titleStr, $amber);
        imagestring($im, 4, 30, $startY + 20, $nameStr, $textWhite);
        imagestring($im, 3, 30, $startY + 40, $infoStr, $textDim);
        imagestring($im, 3, 30, $startY + 55, $trackStr, $textDim);

        imagejpeg($im, $thumbTarget, 85);
        imagedestroy($im);

        if (file_exists($thumbTarget)) {
            $thumbUrl = 'videos/thumbnails/' . $thumbFilename;   // new URL pattern
        }
    }
}

// 6. Build description string (matching the thumbnail meta)
$description = "VED Export: $bounceName · Duration: {$durationS}s · Res: {$canvasW}x{$canvasH} · Clips: $clipsCount";

// 7. Register videos row (now with description)
$this->pdo->prepare("
    INSERT INTO videos (map_run_id, name, description, url, thumbnail, duration, type, width, height, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
")->execute([
    $mapRunId,
    $bounceName,
    $description,
    'videos/' . $filename,
    $thumbUrl,
    $durationS,
    $mimeType,
    $canvasW,
    $canvasH
]);
$videoId = (int)$this->pdo->lastInsertId();
            
            
            
            
            
            
            
            
            
            /*
           // 4. Generate Blue Info Thumbnail
            $thumbDir   = $targetDir . '/thumbnails';              // new subfolder
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0777, true);
            }
            $thumbFilename = $basename . '.jpg';                    // just basename.jpg
            $thumbTarget   = $thumbDir . '/' . $thumbFilename;
            $thumbUrl      = null;
            
            $tw = 640;
            $th = ($canvasW > 0 && $canvasH > 0) ? (int)(($canvasH / $canvasW) * $tw) : 360;
            if ($th <= 0) $th = 360;
            
            if (function_exists('imagecreatetruecolor')) {
                $im = @imagecreatetruecolor($tw, $th);
                if ($im) {
                    $bg        = imagecolorallocate($im, 15, 15, 24);
                    $border    = imagecolorallocate($im, 28, 28, 46);
                    $amber     = imagecolorallocate($im, 245, 158, 11);
                    $textWhite = imagecolorallocate($im, 228, 228, 240);
                    $textDim   = imagecolorallocate($im, 107, 107, 138);
            
                    imagefill($im, 0, 0, $bg);
                    imagerectangle($im, 0, 0, $tw - 1, $th - 1, $border);
                    imagerectangle($im, 1, 1, $tw - 2, $th - 2, $border);
            
                    $titleStr = "VED EXPORT";
                    $nameStr  = mb_strlen($bounceName) > 60 ? mb_substr($bounceName, 0, 57) . '...' : $bounceName;
                    $infoStr  = "Duration: " . $durationS . "s | Res: " . $canvasW . "x" . $canvasH;
                    $stateData = json_decode($_POST['state_json'] ?? '{}', true);
                    $clipsCount = is_array($stateData['clips'] ?? null) ? count($stateData['clips']) : 0;
                    $trackStr = "Clips: " . $clipsCount;
            
                    $startY = ($th / 2) - 30;
                    imagestring($im, 5, 30, $startY, $titleStr, $amber);
                    imagestring($im, 4, 30, $startY + 20, $nameStr, $textWhite);
                    imagestring($im, 3, 30, $startY + 40, $infoStr, $textDim);
                    imagestring($im, 3, 30, $startY + 55, $trackStr, $textDim);
            
                    imagejpeg($im, $thumbTarget, 85);
                    imagedestroy($im);
            
                    if (file_exists($thumbTarget)) {
                        $thumbUrl = 'videos/thumbnails/' . $thumbFilename;   // new URL pattern
                    }
                }
            }
            
/*
            // 4. Generate Blue Info Thumbnail
            $thumbFilename = $basename . '_thumb.jpg';
            $thumbTarget   = $targetDir . '/' . $thumbFilename;
            $thumbUrl      = null;

            // Standardize thumbnail width to 640, scale height proportionately
            $tw = 640;
            $th = ($canvasW > 0 && $canvasH > 0) ? (int)(($canvasH / $canvasW) * $tw) : 360;
            if ($th <= 0) $th = 360;

            if (function_exists('imagecreatetruecolor')) {
                $im = @imagecreatetruecolor($tw, $th);
                if ($im) {
                    // SAGE UI Deep Blue background
                    $bg = imagecolorallocate($im, 15, 15, 24); // Matching roughly var(--surface)
                    $border = imagecolorallocate($im, 28, 28, 46); // var(--border)
                    $amber = imagecolorallocate($im, 245, 158, 11); // var(--amber)
                    $textWhite = imagecolorallocate($im, 228, 228, 240); // var(--text)
                    $textDim = imagecolorallocate($im, 107, 107, 138); // var(--text-dim)
                    
                    imagefill($im, 0, 0, $bg);
                    
                    // Simple border
                    imagerectangle($im, 0, 0, $tw - 1, $th - 1, $border);
                    imagerectangle($im, 1, 1, $tw - 2, $th - 2, $border);

                    // Text drawing
                    $titleStr = "VED EXPORT";
                    $nameStr  = mb_strlen($bounceName) > 60 ? mb_substr($bounceName, 0, 57) . '...' : $bounceName;
                    $infoStr  = "Duration: " . $durationS . "s | Res: " . $canvasW . "x" . $canvasH;
                    $trackStr = "Clips: " . count(json_decode($_POST['state_json'] ?? '{}', true)['clips'] ?? []);
                    
                    // Center vertically roughly
                    $startY = ($th / 2) - 30;

                    // Write strings (using built-in fonts 1-5 to avoid TTF dependencies)
                    imagestring($im, 5, 30, $startY, $titleStr, $amber);
                    imagestring($im, 4, 30, $startY + 20, $nameStr, $textWhite);
                    imagestring($im, 3, 30, $startY + 40, $infoStr, $textDim);
                    imagestring($im, 3, 30, $startY + 55, $trackStr, $textDim);

                    imagejpeg($im, $thumbTarget, 85);
                    imagedestroy($im);
                    
                    if (file_exists($thumbTarget)) {
                        $thumbUrl = 'videos/' . $thumbFilename;
                    }
                }
            }
*  -- /



            // 5. Register videos row
            $this->pdo->prepare("
                INSERT INTO videos (map_run_id, name, url, thumbnail, duration, type, width, height, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $mapRunId, 
                $bounceName, 
                'videos/' . $filename, 
                $thumbUrl, 
                $durationS, 
                $mimeType, 
                $canvasW, 
                $canvasH
            ]);
            $videoId = (int)$this->pdo->lastInsertId();
            
            */
            
            

            // 7. Map to animatic if provided
            if ($animaticId > 0) {
                $this->pdo->prepare("INSERT IGNORE INTO videos_2_animatics (from_id, to_id) VALUES (?, ?)")
                    ->execute([$videoId, $animaticId]);
                $this->pdo->prepare("INSERT IGNORE INTO animatic_videos (animatic_id, video_id, created_at) VALUES (?, ?, NOW())")
                    ->execute([$animaticId, $videoId]);
            }

            $this->pdo->commit();

            echo json_encode([
                'status'   => 'success',
                'video_id' => $videoId,
                'filename' => 'videos/' . $filename,
                'map_run_id' => $mapRunId,
            ]);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            @unlink($tempFile);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─── Table bootstrap ──────────────────────────────────────────────────────

    private function ensureTables(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `ved_projects` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL DEFAULT 'Project',
            `folder_name` varchar(120) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `ved_project_files` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `project_id` int(11) NOT NULL,
            `filename` varchar(120) NOT NULL,
            `state_data` longtext NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_project` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}