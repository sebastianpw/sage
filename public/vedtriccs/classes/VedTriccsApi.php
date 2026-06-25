<?php
// public/vedtriccs/classes/VedTriccsApi.php
// Unified API for VedTriccs — handles VED save/load, asset browsing,
// transition metadata (per clip-boundary connector), and MuviTriccs render pipeline.

class VedTriccsApi
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function dispatch(): void
    {
        header('Content-Type: application/json');
        $action = $_REQUEST['api_action'] ?? '';
        try {
            $this->ensureTables();
            switch ($action) {
                // ── Asset browsing (same as VED) ──────────────────────────────
                case 'list_animatics':           $this->listAnimatics();           break;
                case 'get_animatic':             $this->getAnimatic();             break;
                case 'list_videos':              $this->listVideos();              break;
                case 'list_narrative_sequences': $this->listNarrativeSequences();  break;
                case 'list_storyboards':         $this->listStoryboards();         break;
                case 'list_fuzz_candidates':     $this->listFuzzCandidates();      break;

                // ── Project save / load ───────────────────────────────────────
                case 'get_projects':             $this->getProjects();             break;
                case 'create_project':           $this->createProject();           break;
                case 'get_project_files':        $this->getProjectFiles();         break;
                case 'save_project_file':        $this->saveProjectFile();         break;
                case 'update_project_file':      $this->updateProjectFile();       break;
                case 'load_project_file':        $this->loadProjectFile();         break;

                // ── PyAPI ─────────────────────────────────────────────────────
                case 'get_pyapi_url':            $this->getPyApiUrlAction();       break;

                // ── Transition metadata (NEW) ─────────────────────────────────
                case 'list_transitions':         $this->listTransitions();         break;
                case 'save_connector':           $this->saveConnector();           break;
                case 'load_connectors':          $this->loadConnectors();          break;

                // ── Transition render pipeline (from MuviTriccs) ──────────────
                case 'queue_transition_render':  $this->queueTransitionRender();   break;
                case 'poll_transition_render':   $this->pollTransitionRender();    break;
                case 'get_video_url':            $this->getVideoUrl();             break;

                // ── Browse rendered demos ─────────────────────────────────────
                case 'browse_transition_demos':  $this->browseTransitionDemos();   break;
                case 'assign_demo':              $this->assignDemo();              break;
                case 'unassign_demo':            $this->unassignDemo();            break;

                // ── VED-style export (full timeline via PyAPI) ─────────────────
                case 'ved_bounce_submit':        $this->submitVedBounce();         break;
                case 'ved_bounce_poll':          $this->pollVedBounce();           break;
                case 'register_bounce':          $this->registerBounce();          break;

                default:
                    echo json_encode(['status' => 'error', 'message' => "Unknown action: $action"]);
            }
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ─── PyAPI ────────────────────────────────────────────────────────────────

    private function getPyApiUrl(): string
    {
        return VedTriccsConfig::getPyApiUrl();
    }

    private function getPyApiUrlAction(): void
    {
        echo json_encode(['status' => 'success', 'url' => $this->getPyApiUrl()]);
    }

    // ─── Transition type list (proxied from PyAPI) ────────────────────────────

    private function listTransitions(): void
    {
        $pyUrl = $this->getPyApiUrl();
        $ch    = curl_init("$pyUrl/muvitriccs/transitions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code !== 200 || !$body) {
            echo json_encode([
                'status' => 'success',
                'transitions' => $this->_offlineTransitions(),
            ]);
            return;
        }
        
        $data = json_decode($body, true);
        $transitions = (!empty($data['transitions'])) ? $data['transitions'] : $this->_offlineTransitions();
        echo json_encode(['status' => 'success', 'transitions' => $transitions]);
    }

    private function _offlineTransitions(): array
    {
        return [
            ['name' => 'cross_dissolve', 'family' => 'core', 'description' => 'Gamma-correct alpha blend A to B'],
            ['name' => 'fade_to_black', 'family' => 'core', 'description' => 'Fade A to black, reveal B from black'],
            ['name' => 'fade_to_white', 'family' => 'core', 'description' => 'Fade A to white, reveal B from white'],
            ['name' => 'luma_wipe', 'family' => 'core', 'description' => 'Brightness-driven reveal mask with noise edge'],
            ['name' => 'slide_left', 'family' => 'motion', 'description' => 'A exits left, B enters right with motion blur'],
            ['name' => 'slide_right', 'family' => 'motion', 'description' => 'A exits right, B enters left with motion blur'],
            ['name' => 'slide_up', 'family' => 'motion', 'description' => 'A exits up, B enters below with motion blur'],
            ['name' => 'slide_down', 'family' => 'motion', 'description' => 'A exits down, B enters above with motion blur'],
            ['name' => 'push_left', 'family' => 'motion', 'description' => 'Both clips move left together'],
            ['name' => 'push_right', 'family' => 'motion', 'description' => 'Both clips move right together'],
            ['name' => 'zoom_in', 'family' => 'motion', 'description' => 'A zooms in with radial blur, B fades in'],
            ['name' => 'zoom_out', 'family' => 'motion', 'description' => 'A zooms out, B scales up from small'],
            ['name' => 'spin_cw', 'family' => 'motion', 'description' => 'Clockwise rotation with rotational blur'],
            ['name' => 'spin_ccw', 'family' => 'motion', 'description' => 'Counter-clockwise rotation with rotational blur'],
            ['name' => 'whip_pan_left', 'family' => 'motion', 'description' => 'Fast lateral blur sweep left'],
            ['name' => 'whip_pan_right', 'family' => 'motion', 'description' => 'Fast lateral blur sweep right'],
            ['name' => 'motion_blur_cut', 'family' => 'optical', 'description' => 'Directional smear conceals the splice'],
            ['name' => 'radial_blur_cut', 'family' => 'optical', 'description' => 'Zoom burst at the cut point'],
            ['name' => 'defocus_cut', 'family' => 'optical', 'description' => 'Gaussian lens blur in/out transition'],
            ['name' => 'flash', 'family' => 'stylized', 'description' => 'Luminance spike at cut'],
            ['name' => 'glitch', 'family' => 'stylized', 'description' => 'RGB channel split + scanline block tears'],
            ['name' => 'rgb_split', 'family' => 'stylized', 'description' => 'Chromatic aberration dissolve, peak at cut'],
            ['name' => 'wave_warp', 'family' => 'stylized', 'description' => 'Sinusoidal row-displacement warp'],
            ['name' => 'lens_distortion', 'family' => 'stylized', 'description' => 'Barrel/pincushion radial warp'],
            ['name' => 'film_burn', 'family' => 'stylized', 'description' => 'Smooth-noise radial burn reveal'],
            ['name' => 'light_leak', 'family' => 'stylized', 'description' => 'Additive warm-light bloom sweep'],
            ['name' => 'scanline_tear', 'family' => 'stylized', 'description' => 'Horizontal block corruption + channel drift'],
            ['name' => 'vhs_dropout', 'family' => 'stylized', 'description' => 'VHS tape tracking dropout artifacts'],
            ['name' => 'optical_flow_warp', 'family' => 'flow', 'description' => 'Content-aware Farneback warp morphing'],
            ['name' => 'depth_parallax', 'family' => 'depth', 'description' => 'MiDaS depth map drives per-layer parallax shift'],
            ['name' => 'pixel_sort', 'family' => 'creative', 'description' => 'Glitchy column/row sort reveal'],
            ['name' => 'ink_wash', 'family' => 'creative', 'description' => 'Diffusion-style organic ink bleed reveal'],
            ['name' => 'shatter', 'family' => 'creative', 'description' => 'A shatters into voronoi shards that fall/spin off'],
            ['name' => 'smear_frame', 'family' => 'creative', 'description' => 'Motion-smear echo of A smears into B'],
            ['name' => 'cube_rotate_left', 'family' => 'creative', 'description' => '3-D cube face rotation left'],
            ['name' => 'cube_rotate_right', 'family' => 'creative', 'description' => '3-D cube face rotation right'],
            ['name' => 'page_curl', 'family' => 'creative', 'description' => 'Flat page-curl peel revealing B underneath'],
            ['name' => 'kaleidoscope', 'family' => 'creative', 'description' => 'Kaleidoscopic mirror fold collapse'],
            ['name' => 'ripple_water', 'family' => 'creative', 'description' => 'Concentric water-ripple displacement warp'],
            ['name' => 'dream_blur', 'family' => 'creative', 'description' => 'Dreamy glow bloom dissolve with hue rotation'],
            ['name' => 'speed_ramp', 'family' => 'epic', 'description' => 'Time-remap freeze-burst'],
            ['name' => 'shockwave', 'family' => 'epic', 'description' => 'Radial pressure-ring expands from centre'],
            ['name' => 'strobe_cut', 'family' => 'epic', 'description' => 'Stroboscopic A/B alternation'],
            ['name' => 'motion_trail', 'family' => 'epic', 'description' => 'Luminance ghost trails of A screen-blend over B'],
            ['name' => 'glare_hit', 'family' => 'epic', 'description' => 'Full-frame directional lens-glare streak'],
            ['name' => 'iris_wipe', 'family' => 'movie', 'description' => 'Circular iris opens from centre'],
            ['name' => 'venetian_blind', 'family' => 'movie', 'description' => 'Staggered horizontal strip wipe'],
            ['name' => 'cross_zoom', 'family' => 'movie', 'description' => 'A zooms in, B zooms out, collide at cut'],
            ['name' => 'tilt_shift_cut', 'family' => 'movie', 'description' => 'Rack-focus: A tilt-shift, B sharp focus'],
            ['name' => 'cinematic_bars', 'family' => 'movie', 'description' => 'Letterbox bars squeeze A, retract to B'],
            ['name' => 'whip_zoom', 'family' => 'movie', 'description' => 'Directional whip pan + simultaneous radial zoom burst'],
        ];
    }

    // ─── Connector metadata ───────────────────────────────────────────────────

    private function saveConnector(): void
    {
        $fileId    = (int)($_POST['file_id']    ?? 0);
        $key       = trim($_POST['connector_key'] ?? '');
        $params    = $_POST['params']  ?? '{}';
        if (!$fileId || !$key) {
            echo json_encode(['status' => 'error', 'message' => 'file_id and connector_key required']);
            return;
        }
        $check = $this->pdo->prepare("SELECT id FROM vedtriccs_connectors WHERE file_id=? AND connector_key=?");
        $check->execute([$fileId, $key]);
        $existing = $check->fetchColumn();
        if ($existing) {
            $this->pdo->prepare("UPDATE vedtriccs_connectors SET params_json=?, updated_at=NOW() WHERE id=?")
                ->execute([$params, $existing]);
        } else {
            $this->pdo->prepare("INSERT INTO vedtriccs_connectors (file_id, connector_key, params_json) VALUES (?,?,?)")
                ->execute([$fileId, $key, $params]);
        }
        echo json_encode(['status' => 'success']);
    }

    private function loadConnectors(): void
    {
        $fileId = (int)($_GET['file_id'] ?? 0);
        $stmt   = $this->pdo->prepare("SELECT connector_key, params_json FROM vedtriccs_connectors WHERE file_id=?");
        $stmt->execute([$fileId]);
        $rows   = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[$r['connector_key']] = json_decode($r['params_json'], true);
        }
        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    // ─── Transition render (MuviTriccs pipeline) ──────────────────────────────

    private function queueTransitionRender(): void
    {
        $connKey   = trim($_POST['connector_key']   ?? '');
        $fileId    = (int)($_POST['file_id']         ?? 0);
        $urlA      = trim($_POST['url_a']            ?? '');
        $urlB      = trim($_POST['url_b']            ?? '');
        $transition= trim($_POST['transition_name'] ?? 'cross_dissolve');
        $durFrames = (int)($_POST['duration_frames'] ?? 24);
        $fps       = (int)($_POST['fps']             ?? 30);
        $outW      = (int)($_POST['output_w']        ?? 1024);
        $outH      = (int)($_POST['output_h']        ?? 1024);
        $intensity = (float)($_POST['intensity']     ?? 1.0);
        $easing    = trim($_POST['easing']           ?? 'ease_in_out_cubic');
        $seed      = (int)($_POST['seed']            ?? 42);

        $trimStartA = (float)($_POST['trim_start_a'] ?? 0);
        $trimEndA   = (float)($_POST['trim_end_a']   ?? 0);
        $trimStartB = (float)($_POST['trim_start_b'] ?? 0);
        $trimEndB   = (float)($_POST['trim_end_b']   ?? 0);
        $speedA     = max(0.1, (float)($_POST['speed_a'] ?? 1.0));
        $speedB     = max(0.1, (float)($_POST['speed_b'] ?? 1.0));

        if (!$urlA || !$urlB) {
            echo json_encode(['status' => 'error', 'message' => 'url_a and url_b required']);
            return;
        }

        $projectPath = __DIR__ . '/../../';
        $pathA = $projectPath . ltrim($urlA, '/');
        $pathB = $projectPath . ltrim($urlB, '/');

        if (!file_exists($pathA)) { echo json_encode(['status'=>'error','message'=>"File A not found: $urlA"]); return; }
        if (!file_exists($pathB)) { echo json_encode(['status'=>'error','message'=>"File B not found: $urlB"]); return; }

        $this->pdo->prepare("INSERT INTO vedtriccs_render_jobs
            (file_id, connector_key, transition_name, status)
            VALUES (?,?,?,'queued')")
            ->execute([$fileId, $connKey, $transition]);
        $jobId = (int)$this->pdo->lastInsertId();

        $mimeA = function_exists('mime_content_type') ? @mime_content_type($pathA) : 'video/mp4';
        $mimeB = function_exists('mime_content_type') ? @mime_content_type($pathB) : 'video/mp4';

        $pyUrl   = $this->getPyApiUrl();
        $ch      = curl_init("$pyUrl/muvitriccs/render");
        $postFields = [
            'transition_name'            => $transition,
            'duration_frames'            => $durFrames,
            'fps'                        => $fps,
            'output_w'                   => $outW,
            'output_h'                   => $outH,
            'intensity'                  => $intensity,
            'easing'                     => $easing,
            'seed'                       => $seed,
            'trim_start_a'               => $trimStartA,
            'trim_end_a'                 => $trimEndA,
            'trim_start_b'               => $trimStartB,
            'trim_end_b'                 => $trimEndB,
            'speed_a'                    => $speedA,
            'speed_b'                    => $speedB,
            'tail_a_frames'              => -1,
            'head_b_frames'              => -1,
            'asset_a'                    => new CURLFile($pathA, $mimeA ?: 'video/mp4', basename($pathA)),
            'asset_b'                    => new CURLFile($pathB, $mimeB ?: 'video/mp4', basename($pathB)),
        ];
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1200);
        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $this->pdo->prepare("UPDATE vedtriccs_render_jobs SET status='failed', error_msg=? WHERE id=?")
                ->execute(["PyAPI HTTP $code", $jobId]);
            echo json_encode(['status'=>'error','message'=>"PyAPI returned HTTP $code"]);
            return;
        }

        $result   = json_decode($body, true);
        $pyTaskId = $result['task_id'] ?? null;
        $this->pdo->prepare("UPDATE vedtriccs_render_jobs SET pyapi_task_id=?, status='processing' WHERE id=?")
            ->execute([$pyTaskId, $jobId]);

        echo json_encode(['status' => 'success', 'job_id' => $jobId, 'pyapi_task_id' => $pyTaskId]);
    }

    private function pollTransitionRender(): void
    {
        $jobId = (int)($_GET['job_id'] ?? 0);
        $stmt  = $this->pdo->prepare("SELECT * FROM vedtriccs_render_jobs WHERE id=?");
        $stmt->execute([$jobId]);
        $job   = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) { echo json_encode(['status'=>'error','message'=>'Job not found']); return; }

        if (in_array($job['status'], ['completed', 'failed'])) {
            echo json_encode(['status' => 'success', 'job_status' => $job['status'],
                'video_id' => $job['video_id'], 'error' => $job['error_msg']]);
            return;
        }

        $pyUrl    = $this->getPyApiUrl();
        $taskId   = $job['pyapi_task_id'];
        $ch       = curl_init("$pyUrl/muvitriccs/status/$taskId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body   = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            echo json_encode(['status' => 'success', 'job_status' => 'processing', 'progress' => 0]);
            return;
        }
        $py = json_decode($body, true);
        $pyState = $py['status'] ?? 'processing';

        if ($pyState === 'completed') {
            $dlUrl   = "$pyUrl/muvitriccs/download/$taskId";
            $tmpFile = tempnam(sys_get_temp_dir(), 'vtriccs_') . '.mp4';
            $fh = fopen($tmpFile, 'wb');
            $ch = curl_init($dlUrl);
            curl_setopt($ch, CURLOPT_FILE, $fh);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_exec($ch);
            $dlCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fh);

            if ($dlCode !== 200) {
                $this->pdo->prepare("UPDATE vedtriccs_render_jobs SET status='failed', error_msg='Download failed' WHERE id=?")
                    ->execute([$jobId]);
                echo json_encode(['status' => 'success', 'job_status' => 'failed', 'error' => 'Download failed']);
                return;
            }

            $videoId = $this->_registerTransitionVideo($tmpFile, $job['transition_name'] ?? 'transition', $jobId);
            @unlink($tmpFile);
            $this->pdo->prepare("UPDATE vedtriccs_render_jobs SET status='completed', video_id=?, updated_at=NOW() WHERE id=?")
                ->execute([$videoId, $jobId]);

            echo json_encode(['status' => 'success', 'job_status' => 'completed',
                'video_id' => $videoId, 'progress' => 100]);
        } elseif ($pyState === 'failed') {
            $err = $py['error'] ?? 'PyAPI render failed';
            $this->pdo->prepare("UPDATE vedtriccs_render_jobs SET status='failed', error_msg=?, updated_at=NOW() WHERE id=?")
                ->execute([$err, $jobId]);
            echo json_encode(['status' => 'success', 'job_status' => 'failed', 'error' => $err]);
        } else {
            echo json_encode(['status' => 'success', 'job_status' => 'processing',
                'progress' => (int)($py['progress'] ?? 0)]);
        }
    }

    private function _registerTransitionVideo(string $tmpPath, string $transName, int $jobId): int
    {
        $publicPath = __DIR__ . '/../../';
        $videosDir  = $publicPath . 'videos';
        $thumbsDir  = $videosDir . '/thumbnails';
        if (!is_dir($videosDir)) mkdir($videosDir, 0755, true);
        if (!is_dir($thumbsDir)) mkdir($thumbsDir, 0755, true);

        $this->pdo->prepare("INSERT INTO map_runs (entity_type, note) VALUES ('animatics', 'Generated by VedTriccs Transition')")->execute();
        $mapRunId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("UPDATE video_counter SET next_video = next_video + 1");
        $next     = (int)$this->pdo->query("SELECT next_video FROM video_counter LIMIT 1")->fetchColumn();
        $basename = 'video' . str_pad($next, 7, '0', STR_PAD_LEFT);
        $filename = $basename . '.mp4';
        $dest     = $videosDir . '/' . $filename;

        if (!copy($tmpPath, $dest)) throw new Exception("Failed to copy video to $dest");
        chmod($dest, 0644);

        $thumbName = $basename . '.jpg';
        $thumbDest = $thumbsDir . '/' . $thumbName;
        $im        = @imagecreatetruecolor(640, 360);
        if ($im) {
            $bg     = imagecolorallocate($im, 0, 0, 170);
            $border = imagecolorallocate($im, 0, 0, 100);
            $amber  = imagecolorallocate($im, 245, 158, 11);
            $textWhite = imagecolorallocate($im, 228, 228, 240);
            $textDim   = imagecolorallocate($im, 107, 107, 138);

            imagefill($im, 0, 0, $bg);
            imagerectangle($im, 0, 0, 639, 359, $border);
            imagerectangle($im, 1, 1, 638, 358, $border);

            imagestring($im, 5, 30, 140, "VEDTRICCS TRANSITION", $amber);
            imagestring($im, 4, 30, 165, str_replace('_', ' ', $transName), $textWhite);
            imagestring($im, 3, 30, 185, "Job #$jobId", $textDim);
            imagejpeg($im, $thumbDest, 85);
            imagedestroy($im);
        }
        $thumbUrl = file_exists($thumbDest) ? "videos/thumbnails/$thumbName" : null;

        $dur = 0;
        $raw = trim((string)shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($dest)));
        if (is_numeric($raw)) $dur = (int)round((float)$raw);

        $fileSize = file_exists($dest) ? filesize($dest) : null;
        $desc = "VedTriccs Transition: " . str_replace('_', ' ', $transName) . " · Job #$jobId";

        $this->pdo->prepare("INSERT INTO videos (map_run_id, name, description, url, thumbnail, duration, type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'video/mp4', ?, NOW())")
            ->execute([$mapRunId, $filename, $desc, "videos/$filename", $thumbUrl, $dur, $fileSize]);
            
        return (int)$this->pdo->lastInsertId();
    }

    private function getVideoUrl(): void
    {
        $vid = (int)($_GET['video_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT url, thumbnail FROM videos WHERE id=?");
        $stmt->execute([$vid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['status'=>'error','message'=>'Not found']); return; }
        echo json_encode(['status'=>'success', 'url'=>$row['url'], 'thumbnail'=>$row['thumbnail']]);
    }

    // ─── Browse / assign transition demo videos ───────────────────────────────

    private function browseTransitionDemos(): void
    {
        $transName = trim($_GET['transition_name'] ?? '');
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 5;
        $q         = trim($_GET['q'] ?? '');
        $offset    = ($page - 1) * $perPage;

        $where  = "j.transition_name = ? AND j.status = 'completed' AND j.video_id IS NOT NULL";
        $params = [$transName];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= " AND (v.name LIKE ? OR v.url LIKE ?)";
            $params[] = $like; $params[] = $like;
        }

        $cntStmt = $this->pdo->prepare("SELECT COUNT(DISTINCT j.video_id) FROM vedtriccs_render_jobs j
            JOIN videos v ON j.video_id = v.id WHERE $where");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataParams = $params;
        array_unshift($dataParams, $transName, $transName);

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT j.video_id, j.id AS job_id, j.transition_name,
                v.name AS video_name, v.url AS video_url, v.thumbnail, j.created_at AS rendered_at,
                (SELECT COUNT(*) FROM vedtriccs_transition_demos d WHERE d.video_id=j.video_id AND d.transition_name=?) AS is_assigned,
                (SELECT d2.is_primary FROM vedtriccs_transition_demos d2 WHERE d2.video_id=j.video_id AND d2.transition_name=? LIMIT 1) AS is_primary
             FROM vedtriccs_render_jobs j
             JOIN videos v ON j.video_id = v.id
             WHERE $where
             ORDER BY j.id DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($dataParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['is_assigned'] = (int)$r['is_assigned'] > 0;
            $r['is_primary']  = (int)($r['is_primary'] ?? 0) === 1;
        }
        unset($r);

        echo json_encode(['status'=>'success','data'=>$rows,'total'=>$total,
            'total_pages'=>max(1,(int)ceil($total/$perPage)),'page'=>$page]);
    }

    private function assignDemo(): void
    {
        $transName  = trim($_POST['transition_name'] ?? '');
        $videoId    = (int)($_POST['video_id']   ?? 0);
        $jobId      = (int)($_POST['job_id']     ?? 0);
        $setPrimary = (int)($_POST['set_primary'] ?? 0);
        if (!$transName || !$videoId) { echo json_encode(['status'=>'error','message'=>'Params missing']); return; }
        $check = $this->pdo->prepare("SELECT id FROM vedtriccs_transition_demos WHERE transition_name=? AND video_id=?");
        $check->execute([$transName, $videoId]);
        $existing = $check->fetchColumn();
        if (!$existing) {
            $this->pdo->prepare("INSERT INTO vedtriccs_transition_demos (transition_name, video_id, job_id, is_primary) VALUES (?,?,?,0)")
                ->execute([$transName, $videoId, $jobId]);
        }
        if ($setPrimary) {
            $this->pdo->prepare("UPDATE vedtriccs_transition_demos SET is_primary=0 WHERE transition_name=?")->execute([$transName]);
            $this->pdo->prepare("UPDATE vedtriccs_transition_demos SET is_primary=1 WHERE transition_name=? AND video_id=?")->execute([$transName, $videoId]);
        }
        echo json_encode(['status'=>'success']);
    }

    private function unassignDemo(): void
    {
        $transName = trim($_POST['transition_name'] ?? '');
        $videoId   = (int)($_POST['video_id'] ?? 0);
        $this->pdo->prepare("DELETE FROM vedtriccs_transition_demos WHERE transition_name=? AND video_id=?")
            ->execute([$transName, $videoId]);
        echo json_encode(['status'=>'success']);
    }

    // ─── Asset browsing (same logic as VedApi) ────────────────────────────────

    private function listAnimatics(): void
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        $q       = trim($_GET['q'] ?? '');
        $offset  = ($page - 1) * $perPage;

        if ($q !== '') {
            $like = '%' . $q . '%';
            $idQ  = is_numeric($q) ? (int)$q : -1;
            $total = (int)$this->pdo->query("SELECT COUNT(*) FROM animatics WHERE id=$idQ OR name LIKE " . $this->pdo->quote($like))->fetchColumn();
            $stmt  = $this->pdo->prepare("SELECT id, name, description FROM animatics WHERE id=? OR name LIKE ? ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute([$idQ, $like]);
        } else {
            $total = (int)$this->pdo->query("SELECT COUNT(*) FROM animatics")->fetchColumn();
            $stmt  = $this->pdo->prepare("SELECT id, name, description FROM animatics ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
        }
        echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$perPage)),'page'=>$page]);
    }

    private function getAnimatic(): void
    {
        $id   = (int)($_GET['animatic_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT id, name, description FROM animatics WHERE id=?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['status'=>'error','message'=>'Not found']); return; }
        echo json_encode(['status'=>'success','data'=>$row]);
    }

    private function listVideos(): void
    {
        $animaticId   = (int)($_GET['animatic_id']   ?? 0);
        $nodeId       = (int)($_GET['node_id']        ?? 0);
        $seqId        = (int)($_GET['seq_id']         ?? 0);
        $fuzzCandId   = (int)($_GET['fuzz_cand_id']   ?? 0);
        $storyboardId = (int)($_GET['storyboard_id']  ?? 0);
        $inclDesc     = (int)($_GET['include_descendants'] ?? 1);
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $perPage      = 20;
        $q            = trim($_GET['q'] ?? '');
        $offset       = ($page - 1) * $perPage;

        $joins  = "";
        $where  = ["v.is_active = 1"];
        $params = [];

        if ($animaticId > 0) {
            $joins   = "JOIN animatic_videos av ON av.video_id = v.id";
            $where[] = "av.animatic_id = ?";
            $params[]= $animaticId;
        } elseif ($nodeId) {
            $clause = $inclDesc
                ? "v.id IN (SELECT vti.video_id FROM video_tree_items vti WHERE vti.node_id IN (WITH RECURSIVE d AS (SELECT id FROM video_tree_nodes WHERE id=? UNION ALL SELECT n.id FROM video_tree_nodes n INNER JOIN d ON n.parent_id=d.id) SELECT id FROM d))"
                : "v.id IN (SELECT vti.video_id FROM video_tree_items vti WHERE vti.node_id=?)";
            $where[] = $clause; $params[] = $nodeId;
        } elseif ($seqId) {
            $where[] = "v.id IN (SELECT DISTINCT va2.from_id FROM videos_2_animatics va2 JOIN animatics an ON va2.to_id=an.id JOIN frames fr ON an.img2img_frame_id=fr.id WHERE (fr.entity_type='sketches' AND fr.entity_id IN (SELECT CASE WHEN JSON_TYPE(jt.val)='INTEGER' THEN JSON_VALUE(jt.val,'$') ELSE JSON_VALUE(jt.val,'$.sketch_id') END FROM narrative_sequences ns, JSON_TABLE(ns.sequence_data,'$[*]' COLUMNS(val JSON PATH '$')) jt WHERE ns.id=?)) OR fr.id IN (SELECT f2s.from_id FROM frames_2_sketches f2s WHERE f2s.to_id IN (SELECT CASE WHEN JSON_TYPE(jt2.val)='INTEGER' THEN JSON_VALUE(jt2.val,'$') ELSE JSON_VALUE(jt2.val,'$.sketch_id') END FROM narrative_sequences ns2, JSON_TABLE(ns2.sequence_data,'$[*]' COLUMNS(val JSON PATH '$')) jt2 WHERE ns2.id=?)))";
            $params[] = $seqId; $params[] = $seqId;
        } elseif ($fuzzCandId) {
            $where[] = "v.id IN (SELECT DISTINCT va2.from_id FROM videos_2_animatics va2 JOIN animatics an ON va2.to_id=an.id JOIN frames fr ON an.img2img_frame_id=fr.id WHERE (fr.entity_type='sketches' AND fr.entity_id IN (SELECT DISTINCT source_row_id FROM fuzz_mentions WHERE candidate_id=? AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients') AND source_row_id IS NOT NULL)) OR fr.id IN (SELECT f2s.from_id FROM frames_2_sketches f2s WHERE f2s.to_id IN (SELECT DISTINCT source_row_id FROM fuzz_mentions WHERE candidate_id=? AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients') AND source_row_id IS NOT NULL)))";
            $params[] = $fuzzCandId; $params[] = $fuzzCandId;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = "(v.id=? OR v.name LIKE ? OR v.url LIKE ?)";
            $params[] = is_numeric($q) ? (int)$q : -1;
            $params[] = $like; $params[] = $like;
        }

        $whereStr  = "WHERE " . implode(" AND ", $where);
        $cntStmt   = $this->pdo->prepare("SELECT COUNT(DISTINCT v.id) FROM videos v $joins $whereStr");
        $cntStmt->execute($params);
        $total     = (int)$cntStmt->fetchColumn();
        $orderBy   = ($animaticId > 0) ? "ORDER BY av.created_at DESC" : "ORDER BY v.id DESC";
        $dataStmt  = $this->pdo->prepare("SELECT DISTINCT v.id, v.name, v.url, v.thumbnail, v.duration, v.width, v.height, v.type FROM videos v $joins $whereStr $orderBy LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);

        echo json_encode(['status'=>'success','data'=>$dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$perPage)),'page'=>$page]);
    }

    private function listNarrativeSequences(): void
    {
        $stmt = $this->pdo->query("SELECT id, name, description FROM narrative_sequences ORDER BY id DESC");
        echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function listStoryboards(): void
    {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;
        $where  = "WHERE is_archived=0";
        $params = [];
        if ($search !== '') {
            $where .= " AND (name LIKE ? OR title LIKE ?)";
            $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%';
        }
        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM storyboards $where");
        $cnt->execute($params);
        $total = $cnt->fetchColumn();
        $stmt  = $this->pdo->prepare("SELECT * FROM storyboards $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination'=>['page'=>$page,'pages'=>ceil($total/$limit),'total'=>$total]]);
    }

    private function listFuzzCandidates(): void
    {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        $where  = "WHERE status IN ('promoted','canonized')";
        $params = [];
        if ($search !== '') { $where .= " AND label LIKE ?"; $params[] = '%'.$search.'%'; }
        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM fuzz_candidates $where");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        $stmt  = $this->pdo->prepare("SELECT id, label, concept_type, status FROM fuzz_candidates $where ORDER BY updated_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination'=>['page'=>$page,'pages'=>max(1,(int)ceil($total/$limit)),'total'=>$total]]);
    }

    // ─── Project save / load ──────────────────────────────────────────────────

    private function getProjects(): void
    {
        $rows = $this->pdo->query("SELECT id, name, folder_name FROM vedtriccs_projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status'=>'success','data'=>$rows]);
    }

    private function createProject(): void
    {
        $name   = trim($_POST['name'] ?? 'New Project') ?: 'New Project';
        $folder = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)) . '_' . time();
        $this->pdo->prepare("INSERT INTO vedtriccs_projects (name, folder_name) VALUES (?,?)")->execute([$name, $folder]);
        echo json_encode(['status'=>'success','id'=>(int)$this->pdo->lastInsertId()]);
    }

    private function getProjectFiles(): void
    {
        $pid  = (int)($_GET['project_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT id, filename, created_at FROM vedtriccs_project_files WHERE project_id=? ORDER BY created_at DESC");
        $stmt->execute([$pid]);
        echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function saveProjectFile(): void
    {
        $pid   = (int)($_POST['project_id'] ?? 0);
        $fname = trim($_POST['filename'] ?? 'untitled');
        $state = $_POST['state_data'] ?? '{}';
        if (!$pid) { echo json_encode(['status'=>'error','message'=>'project_id required']); return; }
        $this->pdo->prepare("INSERT INTO vedtriccs_project_files (project_id, filename, state_data) VALUES (?,?,?)")
            ->execute([$pid, $fname, $state]);
        echo json_encode(['status'=>'success','id'=>(int)$this->pdo->lastInsertId()]);
    }

    private function updateProjectFile(): void
    {
        $fid   = (int)($_POST['file_id'] ?? 0);
        $state = $_POST['state_data'] ?? '{}';
        if (!$fid) { echo json_encode(['status'=>'error','message'=>'file_id required']); return; }
        $this->pdo->prepare("UPDATE vedtriccs_project_files SET state_data=? WHERE id=?")->execute([$state, $fid]);
        echo json_encode(['status'=>'success']);
    }

    private function loadProjectFile(): void
    {
        $fid  = (int)($_GET['file_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT state_data FROM vedtriccs_project_files WHERE id=?");
        $stmt->execute([$fid]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) echo json_encode(['status'=>'success','data'=>$row]);
        else      echo json_encode(['status'=>'error','message'=>'File not found']);
    }

    // ─── VED-style full bounce ────────────────────────────────────────────────

    private function submitVedBounce(): void
    {
        // PHP intercepts the bounce request, reads the local files directly, 
        // and manually streams them to PyAPI to bypass the browser network routing.
        $stateJson = $_POST['state_json'] ?? '';
        $canvasW   = (int)($_POST['canvas_w'] ?? 1024);
        $canvasH   = (int)($_POST['canvas_h'] ?? 1024);

        if (!$stateJson) {
            echo json_encode(['status' => 'error', 'message' => 'state_json required']);
            return;
        }

        $state = json_decode($stateJson, true);
        if (!$state || empty($state['clips'])) {
            echo json_encode(['status' => 'error', 'message' => 'Timeline is empty or invalid']);
            return;
        }

        $uniqueUrls = [];
        foreach ($state['clips'] as $c) {
            if (!empty($c['url'])) {
                $uniqueUrls[] = $c['url'];
            }
        }
        $uniqueUrls = array_values(array_unique($uniqueUrls));

        $urlToFilename = [];
        $publicPathAbs = __DIR__ . '/../../';
        $boundary = "----SAGEFormBoundary" . bin2hex(random_bytes(16));
        $body = "";

        foreach ($uniqueUrls as $i => $url) {
            $localPath = ltrim(parse_url($url, PHP_URL_PATH), '/');
            $absPath   = realpath($publicPathAbs . $localPath);

            if (!$absPath || !file_exists($absPath)) {
                echo json_encode(['status' => 'error', 'message' => "Asset not found on server: $localPath"]);
                return;
            }

            $ext   = pathinfo($absPath, PATHINFO_EXTENSION) ?: 'mp4';
            $fname = "asset_{$i}.{$ext}";
            $urlToFilename[$url] = $fname;

            $mime = function_exists('mime_content_type') ? @mime_content_type($absPath) : 'video/mp4';
            $content = file_get_contents($absPath);

            $body .= "--" . $boundary . "\r\n";
            $body .= "Content-Disposition: form-data; name=\"files\"; filename=\"" . $fname . "\"\r\n";
            $body .= "Content-Type: " . ($mime ?: 'application/octet-stream') . "\r\n\r\n";
            $body .= $content . "\r\n";
        }

        foreach ($state['clips'] as &$c) {
            if (!empty($c['url']) && isset($urlToFilename[$c['url']])) {
                $c['bounce_filename'] = $urlToFilename[$c['url']];
            }
        }
        unset($c);

        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"state_json\"\r\n\r\n";
        $body .= json_encode($state) . "\r\n";

        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"canvas_w\"\r\n\r\n";
        $body .= $canvasW . "\r\n";

        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"canvas_h\"\r\n\r\n";
        $body .= $canvasH . "\r\n";

        $body .= "--" . $boundary . "--\r\n";

        $pyUrl = rtrim($this->getPyApiUrl(), '/');
        $ch = curl_init("$pyUrl/ved/compose-async");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: multipart/form-data; boundary=" . $boundary,
            "Content-Length: " . strlen($body)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1200);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code >= 400 || !$response) {
            echo json_encode(['status' => 'error', 'message' => "PyAPI HTTP $code: $err"]);
            return;
        }

        $res = json_decode($response, true);
        if (!$res || empty($res['task_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid response from PyAPI: ' . $response]);
            return;
        }

        echo json_encode([
            'status'    => 'success',
            'task_id'   => $res['task_id'],
            'pyapi_url' => $pyUrl
        ]);
    }

    private function pollVedBounce(): void
    {
        $taskId   = $_POST['task_id'] ?? '';

        if (!$taskId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing task_id']);
            return;
        }

        $url = rtrim($this->getPyApiUrl(), '/') . '/ved/status/' . $taskId;
        
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

        echo json_encode([
            'status' => 'success',
            'task_status' => $data['status'] ?? 'pending',
            'error' => $data['error'] ?? ''
        ]);
    }

    private function registerBounce(): void
    {
        $taskId     = $_POST['task_id']     ?? '';
        $animaticId = (int)($_POST['animatic_id'] ?? 0);
        $bounceName = trim($_POST['name']   ?? 'VedTriccs Export');
        $canvasW    = (int)($_POST['canvas_w'] ?? 0);
        $canvasH    = (int)($_POST['canvas_h'] ?? 0);
        $durationS  = (int)($_POST['duration_s'] ?? 0);
        $pyapiUrl   = $this->getPyApiUrl();

        if (!$taskId || !preg_match('/^[a-f0-9\-]+$/i', $taskId)) {
            echo json_encode(['status'=>'error','message'=>'Invalid task ID']); return;
        }

        $pyDlUrl = rtrim($pyapiUrl, '/') . '/ved/download/' . $taskId;
        $tmpFile = tempnam(sys_get_temp_dir(), 'vtexport_');
        $fp      = fopen($tmpFile, 'w+');
        $ext     = 'mp4';
        $ch      = curl_init($pyDlUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$ext) {
            if (stripos($header, 'content-type:') !== false && stripos($header, 'webm') !== false) $ext = 'webm';
            return strlen($header);
        });
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($code !== 200) { @unlink($tmpFile); echo json_encode(['status'=>'error','message'=>"PyAPI HTTP $code"]); return; }

        try {
            $this->pdo->beginTransaction();
            
            $this->pdo->prepare("INSERT INTO map_runs (entity_type, note) VALUES ('animatics', 'VedTriccs Export')")->execute();
            $mapRunId = (int)$this->pdo->lastInsertId();
            
            $this->pdo->query("UPDATE video_counter SET next_video=LAST_INSERT_ID(next_video+1)");
            $cid = (int)$this->pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            $basename = 'video' . str_pad($cid, 7, '0', STR_PAD_LEFT);
            $filename = $basename . '.' . $ext;
            
            $publicPath = __DIR__ . '/../../';
            $target = $publicPath . 'videos/' . $filename;
            
            if (!rename($tmpFile, $target)) throw new Exception("Failed to move video");
            chmod($target, 0644);
            
            $thumbUrl = null;
            $thumbDir = $publicPath . 'videos/thumbnails';
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
            $thumbFile = $thumbDir . '/' . $basename . '.jpg';
            
            $tw = 640; 
            $th = ($canvasW > 0 && $canvasH > 0) ? (int)(($canvasH / $canvasW) * $tw) : 360;
            if ($th <= 0) $th = 360;
            
            $im = @imagecreatetruecolor($tw, max(1, $th));
            if ($im) {
                $bg     = imagecolorallocate($im, 0, 0, 170);     
                $border = imagecolorallocate($im, 0, 0, 100);     
                $amber  = imagecolorallocate($im, 245, 158, 11);
                $textWhite = imagecolorallocate($im, 228, 228, 240);
                $textDim   = imagecolorallocate($im, 107, 107, 138);

                imagefill($im, 0, 0, $bg);
                imagerectangle($im, 0, 0, $tw - 1, $th - 1, $border);
                imagerectangle($im, 1, 1, $tw - 2, $th - 2, $border);

                $titleStr = "VEDTRICCS EXPORT";
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

                imagejpeg($im, $thumbFile, 85); 
                imagedestroy($im);
                if (file_exists($thumbFile)) $thumbUrl = 'videos/thumbnails/' . $basename . '.jpg';
            }
            
            $fileSize = file_exists($target) ? filesize($target) : null;
            $description = "VEDTRICCS Export: $bounceName · Duration: {$durationS}s · Res: {$canvasW}x{$canvasH} · Clips: $clipsCount";
            
            $this->pdo->prepare("INSERT INTO videos (map_run_id, name, description, url, thumbnail, duration, type, width, height, file_size, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$mapRunId, $bounceName, $description, "videos/$filename", $thumbUrl, $durationS, "video/$ext", $canvasW, $canvasH, $fileSize]);
            $videoId = (int)$this->pdo->lastInsertId();
            
            if ($animaticId > 0) {
                $this->pdo->prepare("INSERT IGNORE INTO videos_2_animatics (from_id, to_id) VALUES (?,?)")->execute([$videoId, $animaticId]);
                $this->pdo->prepare("INSERT IGNORE INTO animatic_videos (animatic_id, video_id, created_at) VALUES (?,?,NOW())")->execute([$animaticId, $videoId]);
            }
            $this->pdo->commit();
            
            // Cleanup PyAPI Temp folder
            $cleanupUrl = rtrim($pyapiUrl, '/') . "/ved/cleanup/{$taskId}";
            $chC = curl_init($cleanupUrl);
            curl_setopt($chC, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($chC, CURLOPT_RETURNTRANSFER, true);
            curl_exec($chC);
            curl_close($chC);

            echo json_encode(['status'=>'success','video_id'=>$videoId,'filename'=>"videos/$filename"]);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            @unlink($tmpFile);
            echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        }
    }

    // ─── Table bootstrap ──────────────────────────────────────────────────────

    private function ensureTables(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `vedtriccs_projects` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL DEFAULT 'Project',
            `folder_name` varchar(120) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `vedtriccs_project_files` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `project_id` int(11) NOT NULL,
            `filename` varchar(120) NOT NULL,
            `state_data` longtext NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_project` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `vedtriccs_connectors` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `file_id` int(11) NOT NULL COMMENT 'vedtriccs_project_files.id',
            `connector_key` varchar(200) NOT NULL COMMENT 'hash key identifying this clip-pair boundary',
            `params_json` text NOT NULL,
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_file_key` (`file_id`, `connector_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `vedtriccs_render_jobs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `file_id` int(11) DEFAULT NULL,
            `connector_key` varchar(200) DEFAULT NULL,
            `transition_name` varchar(64) NOT NULL DEFAULT 'cross_dissolve',
            `pyapi_task_id` varchar(64) DEFAULT NULL,
            `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
            `video_id` int(11) DEFAULT NULL,
            `error_msg` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`),
            KEY `idx_trans` (`transition_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `vedtriccs_transition_demos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `transition_name` varchar(64) NOT NULL,
            `video_id` int(11) NOT NULL,
            `job_id` int(11) DEFAULT NULL,
            `is_primary` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_trans_vid` (`transition_name`, `video_id`),
            KEY `idx_trans` (`transition_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}